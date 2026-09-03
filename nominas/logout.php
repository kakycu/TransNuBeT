<?php
// logout.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Obtener nombre del sistema desde config/database.php
$SITE_NAME = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom';


// Capturar nombre del usuario antes de destruir la sesión
$user_nombre_logout = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? '';

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