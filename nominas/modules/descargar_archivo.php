<?php
// modules/descargar_archivo.php - Descarga en streaming de un archivo de las
// carpetas del sistema.
//
// Hace falta para la carpeta Descargas del equipo: queda fuera del
// DocumentRoot (C:\Users\<perfil>\Downloads), asi que no tiene URL directa de
// Apache como si tienen exports/ y backups/. Los enlaces del listado salen con
// la forma "descargar_archivo.php/<carpeta>/<ruta>/<archivo>" (PATH_INFO) y
// este endpoint resuelve, valida y transmite el fichero.
//
// Mismas garantias que el resto del explorador: sesion iniciada, rol con
// permiso "descargar", carpeta/ruta/archivo validados contra la raiz permitida
// (sin "..", sin salirse por realpath) y cabeceras de descarga sin cache.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sesion expirada. Vuelva a iniciar sesion.');
}

if (!carpetas_sistema_puede('descargar')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Su rol no permite descargar archivos.');
}

// La ruta llega en PATH_INFO (lo normal) o como parametros sueltos (respaldo).
$pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');

if ($pathInfo !== '') {
    $segmentos = explode('/', trim($pathInfo, '/'));
    $segmentos = array_map('rawurldecode', $segmentos);
    $claveCarpeta = (string)array_shift($segmentos);
    $archivo      = $segmentos === [] ? '' : (string)array_pop($segmentos);
    $rutaRelativa = implode('/', $segmentos);
} else {
    $claveCarpeta = (string)($_GET['carpeta'] ?? '');
    $archivo      = (string)($_GET['archivo'] ?? '');
    $rutaRelativa = (string)($_GET['ruta'] ?? '');
}

$resuelta = carpetas_sistema_resolver($claveCarpeta);
if ($resuelta === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Carpeta no permitida o inexistente.');
}
list($claveValida, $rutaCarpeta) = $resuelta;

$archivo = basename(trim($archivo));
if ($archivo === '' || $archivo === '.' || $archivo === '..') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Nombre de archivo no valido.');
}

$ruta = carpetas_ruta_relativa($rutaRelativa);
if ($ruta === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Ruta no valida.');
}

$rutaBase = carpetas_ruta_resolver($rutaCarpeta, $ruta);
if ($rutaBase === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('La subcarpeta no existe.');
}

$rutaArchivo = realpath($rutaBase . DIRECTORY_SEPARATOR . $archivo);
if ($rutaArchivo === false || !is_file($rutaArchivo)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('El archivo no existe en esa carpeta.');
}

if (strpos($rutaArchivo, rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Ruta de archivo no permitida.');
}

$peso = (int)@filesize($rutaArchivo);

// Descargas de cualquier tamano: sin limite de tiempo de ejecucion.
@set_time_limit(0);

logAction('dashboard', 'descargar_archivo',
    'Descarga de un archivo desde el explorador de carpetas',
    [
        'carpeta' => $claveValida,
        'ruta'    => $ruta,
        'archivo' => $archivo,
        'bytes'   => $peso,
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $archivo) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
header('Content-Length: ' . $peso);

// Streaming: la carpeta Descargas puede tener ficheros gigantes.
@readfile($rutaArchivo);
exit();
