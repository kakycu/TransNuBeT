<?php
// modules/eliminar_archivo.php - Elimina un archivo de las carpetas del sistema
//
// Restringido al rol Administrador (rol_id = 1). Los demas roles reciben 403.
// La carpeta debe ser una de las permitidas y el archivo debe existir dentro de ella.
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
    echo json_encode(['success' => false, 'mensaje' => 'Solo el Administrador puede eliminar archivos.']);
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

$archivo = basename(trim((string)($_POST['archivo'] ?? '')));
if ($archivo === '' || $archivo === '.' || $archivo === '..') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Nombre de archivo no valido.']);
    exit();
}

// Subcarpeta relativa dentro de la carpeta del sistema (extracciones de ZIP).
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

$tipo      = (string)($_POST['tipo'] ?? 'archivo');
$esCarpeta = ($tipo === 'carpeta');

$rutaArchivo = $rutaBase . DIRECTORY_SEPARATOR . $archivo;
$rutaReal    = realpath($rutaArchivo);

// ==========================================
// Carpeta: se borra con todo su contenido (nunca la raiz, nunca fuera de ella)
// ==========================================
if ($esCarpeta) {
    if ($rutaReal === false || !is_dir($rutaReal)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'mensaje' => 'La carpeta no existe en esa ubicacion.']);
        exit();
    }

    $error = carpetas_eliminar_arbol($rutaReal, $rutaBase);
    if ($error !== null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => $error]);
        exit();
    }

    clearstatcache();

    logAction('dashboard', 'eliminar_archivo',
        'Eliminacion de carpeta desde el explorador de carpetas',
        [
            'carpeta'  => $carpetaSolicitada,
            'subruta'  => $rutaRelativa,
            'archivo'  => $archivo,
            'ruta'     => str_replace('\\', '/', $rutaReal),
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success' => true,
        'mensaje' => 'Carpeta eliminada correctamente.',
        'carpeta' => $carpetaSolicitada,
        'nombre'  => $archivo,
        'tipo'    => 'carpeta',
    ]);
    exit();
}

// El archivo debe existir, ser un archivo regular y estar dentro de la carpeta permitida.
if ($rutaReal === false || !is_file($rutaReal)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe en esa carpeta.']);
    exit();
}

if (strpos($rutaReal, rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta de archivo no permitida.']);
    exit();
}

// --- Eliminar ---
$tamano = (int)@filesize($rutaReal);

if (!@unlink($rutaReal)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo eliminar el archivo.']);
    exit();
}

clearstatcache();

logAction('dashboard', 'eliminar_archivo',
    'Eliminacion de archivo desde el explorador de carpetas',
    [
        'carpeta'  => $carpetaSolicitada,
        'subruta'  => $rutaRelativa,
        'archivo'  => $archivo,
        'tamano'   => $tamano,
        'ruta'     => str_replace('\\', '/', $rutaReal),
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success' => true,
    'mensaje' => 'Archivo eliminado correctamente.',
    'carpeta' => $carpetaSolicitada,
    'nombre'  => $archivo,
    'tipo'    => 'archivo',
]);