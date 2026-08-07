<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

if ($id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puede eliminar su propio usuario']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);