<?php
// ajax/cambiar_password.php - Cambio de contraseña del usuario autenticado
require_once '../config/database.php';
require_once '../config/mail.php';
require_once __DIR__ . '/../logger.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_actual = intval($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
$password_actual = $_POST['password_actual'] ?? '';
$password_nueva = $_POST['password_nueva'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$user_id = intval($_POST['user_id'] ?? 0);

if ($id_actual <= 0) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($user_id <= 0) {
    $user_id = $id_actual;
}

$es_cambio_ajeno = ($user_id !== $id_actual);

if ($password_nueva !== $password_confirm) {
    echo json_encode(['success' => false, 'message' => 'La confirmación de la contraseña no coincide']);
    exit;
}

if (strlen($password_nueva) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

if ($es_cambio_ajeno) {
    // Cambio de contraseña de otro usuario: solo roles Admin (no se exige la clave actual)
    if (permiso_rol_codigo() !== 'Admin') {
        echo json_encode(['success' => false, 'message' => 'No autorizado para cambiar la contraseña de este usuario']);
        exit;
    }
    $stmtChk = $pdo->prepare("SELECT id FROM clasif_usuarios WHERE id = ?");
    $stmtChk->execute([$user_id]);
    if (!$stmtChk->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'El usuario no existe']);
        exit;
    }
} else {
    // Cambio de la propia contraseña: verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM clasif_usuarios WHERE id = ?");
    $stmt->execute([$id_actual]);
    $hash_actual = $stmt->fetchColumn();

    if (!$hash_actual || !password_verify($password_actual, $hash_actual)) {
        echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
        exit;
    }
}

$hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, reset_token = NULL, reset_expira = NULL, fecha_actualizacion = NOW() WHERE id = ?");
$stmt->execute([$hashed, $user_id]);

// ===== AUDITORÍA =====
if ($es_cambio_ajeno) {
    logAction(
        'cambiar_password_admin',
        'usuarios',
        'Cambio de contraseña de otro usuario',
        ['user_id' => (int)$user_id],
        (int)$id_actual,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );
} else {
    logAction(
        'cambiar_password',
        'usuarios',
        'Cambio de contraseña del usuario autenticado',
        ['user_id' => (int)$id_actual],
        (int)$id_actual,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );
}

// Notificar por correo si el usuario tiene email configurado
$correo = notificarPasswordCambiada($pdo, $user_id);

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
