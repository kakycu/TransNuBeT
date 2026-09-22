<?php
// ajax/pendiente_reset.php - Decide sobre una solicitud de cambio de contraseña pendiente
require_once '../config/database.php';
require_once '../config/mail.php';
require_once __DIR__ . '/../logger.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

// Opción "No, ahora no": eliminar el token de la BD (se descarta la solicitud)
if ($action === 'descartar') {
    $stmt = $pdo->prepare("UPDATE clasif_usuarios SET reset_token = NULL, reset_expira = NULL WHERE id = ?");
    $stmt->execute([$id]);
    // ===== AUDITORÍA: descartar solicitud de reset pendiente =====
    logAction(
        'descartar_solicitud_reset',
        'usuarios',
        'Se descartó una solicitud de reset de contraseña pendiente',
        ['user_id' => (int)$id],
        (int)$id,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );
    echo json_encode(['success' => true]);
    exit;
}

// Opción "Sí, restablecer": guardar la nueva contraseña y limpiar el token
if ($action === 'restablecer') {
    $password_nueva = $_POST['password_nueva'] ?? '';
    if (strlen($password_nueva) < 6) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        exit;
    }

    $hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
    $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, reset_token = NULL, reset_expira = NULL, fecha_actualizacion = NOW() WHERE id = ?");
    $stmtUpd->execute([$hashed, $id]);

    // ===== AUDITORÍA: restablecer contraseña pendiente =====
    logAction(
        'restablecer_password_pendiente',
        'usuarios',
        'Restablecimiento de contraseña pendiente desde el aviso',
        ['user_id' => (int)$id],
        (int)$id,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );

    // Notificar por correo si el usuario tiene email configurado
    $correo = notificarPasswordCambiada($pdo, $id);

    $respuesta = ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
    if ($correo['success']) {
        $respuesta['correo'] = 'enviado';
    } elseif ($correo['error'] === 'sin_email') {
        $respuesta['correo'] = 'sin_email';
    } else {
        $respuesta['correo'] = 'no_enviado';
        $respuesta['correo_error'] = ($correo['error'] === 'mail_not_configured')
            ? 'SMTP no configurado'
            : $correo['error'];
    }
    echo json_encode($respuesta);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
