<?php
// modules/abrir_carpeta.php
// Abre en el explorador de archivos una carpeta permitida del sistema (solo Windows local).
// Carpetas permitidas: exportaciones (modules/exports) y descargas (Descargas del usuario).
// Acceso: solo Administrador (Admin), Contador/Editor (Editor) y Programador (Soft).
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

if (!carpetas_sistema_permitido() || !carpetas_sistema_puede('abrir')) {
    echo json_encode(['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Metodo no permitido.']);
    exit();
}

if (PHP_OS_FAMILY !== 'Windows') {
    echo json_encode(['success' => false, 'mensaje' => 'La apertura de carpetas solo funciona en Windows.']);
    exit();
}

$resuelta = carpetas_sistema_resolver($_POST['carpeta'] ?? '');
if ($resuelta === null) {
    echo json_encode(['success' => false, 'mensaje' => 'Carpeta no permitida o inexistente.']);
    exit();
}

list($carpetaSolicitada, $rutaCarpeta) = $resuelta;

@exec('explorer.exe "' . $rutaCarpeta . '"');

logAction('dashboard', 'abrir_carpeta',
    'Apertura de carpeta del sistema en el explorador',
    ['carpeta' => $carpetaSolicitada, 'ruta' => str_replace('\\', '/', $rutaCarpeta)],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success' => true,
    'carpeta' => $carpetaSolicitada,
    'ruta'    => str_replace('\\', '/', $rutaCarpeta)
]);