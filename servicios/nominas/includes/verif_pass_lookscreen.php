<?php
// includes/verif_pass_lookscreen.php - Endpoint que verifica la contraseña para desbloquear la sesión
// (Marca BLOQUEO_SESION_PERMITIDO ANTES de cargar database.php para que la guardia anti-retroceso no redirija)
define('BLOQUEO_SESION_PERMITIDO', true);

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0);
if ($uid <= 0 || empty($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Sesión no iniciada', 'logout' => true]));
}

$password_input = trim($_POST['password'] ?? '');
if (empty($password_input)) {
    exit(json_encode(['success' => false, 'error' => 'Debes ingresar tu contraseña']));
}

$user_data = null;
try {
    $stmt = $pdo->prepare("SELECT u.*, r.codigo as rol_codigo, r.descripcion as rol_descripcion
                           FROM clasif_usuarios u
                           LEFT JOIN clasif_rol r ON u.rol_id = r.id
                           WHERE u.id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    exit(json_encode(['success' => false, 'error' => 'Error interno al verificar la contraseña']));
}

if (!$user_data || empty($user_data['password'])) {
    exit(json_encode([
        'success' => false,
        'error'   => 'Este usuario no tiene contraseña configurada. Cierra sesión e inicia sesión nuevamente.',
        'logout'  => true
    ]));
}

if (password_verify($password_input, $user_data['password'])) {
    exit(json_encode(['success' => true]));
}

exit(json_encode(['success' => false, 'error' => 'Contraseña incorrecta']));