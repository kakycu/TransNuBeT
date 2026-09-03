<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Solo quienes pueden ver el módulo de usuarios pueden ver la información de otros
$id_actual = intval($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
if (!permiso_puede('usuarios', 'ver') && $id_actual != $id) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    unset($usuario['password']);
    echo json_encode(['success' => true, 'usuario' => $usuario]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
}