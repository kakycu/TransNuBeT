<?php
// ajax/cambiar_password.php - Cambio de contraseña del usuario autenticado
require_once '../config/database.php';
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

if ($id_actual <= 0) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($password_nueva !== $password_confirm) {
    echo json_encode(['success' => false, 'message' => 'La confirmación de la contraseña no coincide']);
    exit;
}

if (strlen($password_nueva) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

// Verificar contraseña actual
$stmt = $pdo->prepare("SELECT password FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id_actual]);
$hash_actual = $stmt->fetchColumn();

if (!$hash_actual || !password_verify($password_actual, $hash_actual)) {
    echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
    exit;
}

$hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, fecha_actualizacion = NOW() WHERE id = ?");
$stmt->execute([$hashed, $id_actual]);

echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
