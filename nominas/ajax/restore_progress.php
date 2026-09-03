<?php
/**
 * ajax/restore_progress.php
 * Devuelve el avance de la restauración en curso (sondeado por el cliente).
 */

// No iniciar sesión para no bloquear el lock de la sesión durante la restauración
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_write_close();
}

$temp_dir = '../temp/';
$progressFile = $temp_dir . 'restore_progress_' . session_id() . '.json';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!file_exists($progressFile)) {
    echo json_encode(['percent' => 0, 'table' => null, 'step' => 'Iniciando...']);
    exit;
}

$content = file_get_contents($progressFile);
$data = json_decode($content, true);

if (!is_array($data)) {
    echo json_encode(['percent' => 0, 'table' => null, 'step' => 'Procesando...']);
    exit;
}

echo json_encode([
    'percent' => isset($data['percent']) ? (int)$data['percent'] : 0,
    'table' => isset($data['table']) ? $data['table'] : null,
    'step' => isset($data['step']) ? $data['step'] : '',
    'processed' => isset($data['processed']) ? (int)$data['processed'] : null,
    'total' => isset($data['total']) ? (int)$data['total'] : null
]);
exit;
