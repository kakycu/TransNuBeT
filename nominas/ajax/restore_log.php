<?php
/**
 * ajax/restore_log.php
 * Devuelve las entradas de logs/restore_log.json (solo usuarios autorizados).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';
require_once __DIR__ . '/../logger.php';

if (!in_array(permiso_rol_codigo(), ['Admin', 'Soft', 'Editor'], true)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para ver el log']);
    exit;
}

$logFile = __DIR__ . '/../logs/restore_log.json';

$logs = [];
if (file_exists($logFile)) {
    $decoded = json_decode(file_get_contents($logFile), true);
    if (is_array($decoded)) {
        $logs = $decoded;
    }
}

echo json_encode(['success' => true, 'logs' => $logs]);
