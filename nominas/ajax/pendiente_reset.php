<?php
// ajax/pendiente_reset.php - Decide sobre una solicitud de cambio de contraseña pendiente
require_once '../config/database.php';
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

    $stmt = $pdo->prepare("SELECT password FROM clasif_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $hash_actual = $stmt->fetchColumn();

    if ($hash_actual && password_verify($password_nueva, $hash_actual)) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña no puede ser igual a la actual']);
        exit;
    }

    $hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
    $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, reset_token = NULL, reset_expira = NULL, fecha_actualizacion = NOW() WHERE id = ?");
    $stmtUpd->execute([$hashed, $id]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
