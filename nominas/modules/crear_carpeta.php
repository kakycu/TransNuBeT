<?php
// modules/crear_carpeta.php - Crea una subcarpeta vacia en el explorador
//
// Restringido al rol Administrador (rol_id = 1). Los demas roles reciben 403.
// La carpeta destino debe ser una de las permitidas y el nombre no puede
// existir ya (en Windows la comprobacion distingue mayusculas por sistema).
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// --- Sesion ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

// --- Permisos: acceso a carpetas + rol Administrador ---
if (!carpetas_sistema_permitido()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
    exit();
}

if (permiso_rol_codigo() !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'Solo el Administrador puede crear carpetas.']);
    exit();
}

// --- Metodo y parametros ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'mensaje' => 'Metodo no permitido.']);
    exit();
}

$resuelta = carpetas_sistema_resolver($_POST['carpeta'] ?? '');
if ($resuelta === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Carpeta no permitida o inexistente.']);
    exit();
}
list($carpetaSolicitada, $rutaCarpeta) = $resuelta;

$rutaRelativa = carpetas_ruta_relativa((string)($_POST['ruta'] ?? ''));
if ($rutaRelativa === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta de subcarpeta no valida.']);
    exit();
}

$rutaBase = carpetas_ruta_resolver($rutaCarpeta, $rutaRelativa);
if ($rutaBase === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'La subcarpeta no existe.']);
    exit();
}

// --- Nombre de la carpeta nueva ---
$nombre = trim((string)($_POST['nombre'] ?? ''));

if ($nombre === '' || $nombre === '.' || $nombre === '..') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Indique el nombre de la carpeta.']);
    exit();
}
if (strlen($nombre) > 150) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'El nombre no puede superar los 150 caracteres.']);
    exit();
}
if (preg_match('/[\/\\\\:*?"<>|\x00-\x1F]/', $nombre)) {
    http_response_code(400);
    echo json_encode(['success' => false,
        'mensaje' => 'El nombre contiene caracteres no permitidos. No se admiten: \ / : * ? " < > |']);
    exit();
}
if (substr($nombre, -1) === '.' || substr($nombre, -1) === ' ') {
    http_response_code(400);
    echo json_encode(['success' => false,
        'mensaje' => 'El nombre no puede terminar en punto ni en espacio.']);
    exit();
}

$nueva = $rutaBase . DIRECTORY_SEPARATOR . $nombre;
if (file_exists($nueva)) {
    http_response_code(409);
    echo json_encode(['success' => false,
        'mensaje' => 'Ya existe una carpeta o archivo con ese nombre.']);
    exit();
}

if (!@mkdir($nueva, 0755)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo crear la carpeta.']);
    exit();
}

clearstatcache();

logAction('dashboard', 'crear_carpeta',
    'Creacion de carpeta desde el explorador de carpetas',
    [
        'carpeta'  => $carpetaSolicitada,
        'subruta'  => $rutaRelativa,
        'nombre'   => $nombre,
        'ruta'     => str_replace('\\', '/', $nueva),
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success' => true,
    'mensaje' => 'Carpeta "' . $nombre . '" creada correctamente.',
    'carpeta' => $carpetaSolicitada,
    'ruta'    => $rutaRelativa,
    'nombre'  => $nombre,
]);
