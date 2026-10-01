<?php
// modules/abrir_exportacion.php
// Abre en el explorador de archivos el archivo exportado (solo Windows local).
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

if (!permiso_puede('domiciliacion_tarjetas', 'exportar')) {
    permiso_denegar_acceso('Abrir archivo exportado');
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Metodo no permitido.']);
    exit();
}

$carpetaBase = realpath(__DIR__ . '/exports');
if ($carpetaBase === false) {
    echo json_encode(['success' => false, 'mensaje' => 'La carpeta de exportaciones no existe.']);
    exit();
}

$archivo = basename(trim((string)($_POST['archivo'] ?? '')));
if ($archivo === '' || strpbrk($archivo, "\\/:*?\"<>|") !== false) {
    echo json_encode(['success' => false, 'mensaje' => 'Nombre de archivo no valido.']);
    exit();
}

$rutaCompleta = realpath($carpetaBase . DIRECTORY_SEPARATOR . $archivo);

if ($rutaCompleta === false || !is_file($rutaCompleta)) {
    echo json_encode(['success' => false, 'mensaje' => 'El archivo exportado no existe.']);
    exit();
}

if (strpos($rutaCompleta, $carpetaBase . DIRECTORY_SEPARATOR) !== 0) {
    echo json_encode(['success' => false, 'mensaje' => 'Ruta fuera de la carpeta de exportaciones.']);
    exit();
}

if (PHP_OS_FAMILY !== 'Windows') {
    echo json_encode(['success' => false, 'mensaje' => 'La apertura de carpetas solo funciona en Windows.']);
    exit();
}

$comando = 'explorer.exe /select,"' . $rutaCompleta . '"';
@exec($comando);

logAction('exportar_domiciliacion_tarjetas', 'abrir_exportacion',
    'Apertura de archivo exportado en el explorador',
    ['archivo' => $archivo],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success' => true,
    'archivo' => $archivo,
    'ruta'    => str_replace('\\', '/', $rutaCompleta)
]);