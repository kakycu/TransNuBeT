<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$estado = intval($_POST['estado'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$stmt = $pdo->prepare("UPDATE clasif_usuarios SET activo = ? WHERE id = ?");
$stmt->execute([$estado, $id]);

echo json_encode(['success' => true, 'message' => $estado ? 'Usuario activado' : 'Usuario desactivado']);