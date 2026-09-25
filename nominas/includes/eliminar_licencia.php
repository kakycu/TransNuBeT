<?php
// includes/eliminar_licencia.php - Elimina el registro de licencia del equipo,
// cierra la sesión actual y redirige a la pantalla de registro (licencia.php).
// Solo disponible para los roles Admin y Soft.
// Nota: vive en /includes, por lo que las redirecciones suben un nivel con "../".

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

// database.php ya carga includes/licencia.php e includes/permisos.php.

// Acceso protegido: solo POST (evita la ejecución por GET, tipo <img src=...>),
// sesión iniciada, token CSRF y rol Admin/Soft.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

if (!empty($_SESSION['csrf_eliminar_licencia'])
    && !hash_equals($_SESSION['csrf_eliminar_licencia'], (string)($_POST['csrf'] ?? ''))) {
    header('Location: ../index.php');
    exit;
}

// Solo Admin y Soft pueden eliminar el registro de licencia.
$rol_actual = permiso_rol_codigo();
if (!in_array($rol_actual, ['Admin', 'Soft'], true)) {
    header('Location: ../index.php');
    exit;
}

// Capturar datos del usuario ANTES de destruir la sesión.
$user_id_logout     = $_SESSION['user_id'] ?? null;
$usuario_logout     = $_SESSION['username'] ?? '';
$auth_provider_log  = $_SESSION['auth_provider'] ?? 'local';

// Eliminar la licencia guardada (regedit en Windows, archivo en Linux/otros).
$eliminada = licencia_borrar();

// Auditar la acción antes de destruir la sesión.
if (function_exists('logAction')) {
    logAction(
        'eliminar_licencia',
        'licencia',
        'Registro de licencia eliminado por: ' . ($usuario_logout !== '' ? $usuario_logout : 'no autenticado') . ($eliminada ? '' : ' (falló)'),
        [],
        $user_id_logout !== null ? (int)$user_id_logout : null,
        $eliminada ? 'success' : 'error',
        null,
        $auth_provider_log
    );
}

// Cerrar sesión (mismo flujo que logout.php).
$_SESSION = array();
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
session_destroy();

// Ir a la pantalla de registro de licencia (ahora no hay licencia activa).
header('Location: ../licencia.php');
exit;