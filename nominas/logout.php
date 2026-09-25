<?php
// logout.php - Cierre de sesión
require_once 'config/database.php';
require_once __DIR__ . '/includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Obtener nombre del sistema desde config/database.php
$SITE_NAME = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom';


// Capturar datos del usuario ANTES de destruir la sesión
$user_id_logout      = $_SESSION['user_id'] ?? null;
$usuario_logout      = $_SESSION['username'] ?? '';
$email_logout        = $_SESSION['user_email'] ?? '';
$user_nombre_logout  = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? '';
$auth_provider_logout = $_SESSION['auth_provider'] ?? 'local';

// ===== NUEVO: auditar el cierre de sesión (debe ir antes de destruir la sesión) =====
logAction(
    'cerrar_sesion',
    'login',
    'Cierre de sesión del usuario: ' . ($usuario_logout !== '' ? $usuario_logout : 'no autenticado'),
    [],
    $user_id_logout !== null ? (int)$user_id_logout : null,
    'success',
    null,
    $auth_provider_logout
);

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje y nombre del usuario
$redirect = 'login.php?logout=1';
if (!empty($user_nombre_logout)) {
    $redirect .= '&user=' . urlencode($user_nombre_logout);
}
header('Location: ' . $redirect);
exit();
?>