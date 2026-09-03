<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!permiso_puede('usuarios', 'editar')) {
    echo json_encode(['success' => false, 'message' => 'No autorizado para activar/desactivar usuarios', 'denied' => true]);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$estado = intval($_POST['estado'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

if ($estado === 0 && $id === $usuario_actual_id) {
    echo json_encode(['success' => false, 'message' => 'No puede desactivar su propia cuenta. Solicite a otro administrador.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE clasif_usuarios SET activo = ? WHERE id = ?");
$stmt->execute([$estado, $id]);

echo json_encode(['success' => true, 'message' => $estado ? 'Usuario activado' : 'Usuario desactivado']);