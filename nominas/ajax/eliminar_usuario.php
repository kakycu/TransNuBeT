<?php
require_once '../config/database.php';
require_once __DIR__ . '/../logger.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!permiso_puede('usuarios', 'eliminar')) {
    echo json_encode(['success' => false, 'message' => 'No autorizado para eliminar usuarios', 'denied' => true]);
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

$stmt = $pdo->prepare("SELECT CONCAT(nombre, ' ', apellidos) AS nombre_completo, usuario FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id]);
$eliminado = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("DELETE FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id]);

// ===== AUDITORÍA: eliminación de usuario =====
logAction(
    'eliminar_usuario',
    'usuarios',
    'Eliminación de usuario',
    ['user_id' => (int)$id, 'usuario' => $eliminado['usuario'] ?? null],
    null,
    'success',
    null,
    $_SESSION['auth_provider'] ?? 'local'
);

echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);