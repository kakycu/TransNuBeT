<?php
// logout.php
require_once 'config/database.php';

session_start();

// Obtener nombre del sistema desde config/database.php
$SITE_NAME = defined('SITE_NAME') ? SITE_NAME : 'TRANSNUBET - Nóminas';


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

// Redirigir al login con mensaje
header('Location: login.php?logout=1');
exit();
?>