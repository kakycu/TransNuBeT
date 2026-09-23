<?php
// ajax/idle_ping.php - Actualiza el marcador de última actividad del usuario (heartbeat)
define('BLOQUEO_SESION_PERMITIDO', true);

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'auth' => false]));
}

if (!empty($_SESSION['sesion_bloqueada'])) {
    exit(json_encode(['ok' => false, 'locked' => true]));
}

$_SESSION['idle_last'] = time();
exit(json_encode(['ok' => true, 'ts' => $_SESSION['idle_last']]));
