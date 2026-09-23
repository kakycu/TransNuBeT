<?php
// limpiar_bloqueo.php - Endpoint que limpia el estado de bloqueo de la sesión tras un desbloqueo válido
define('BLOQUEO_SESION_PERMITIDO', true);

require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');

unset($_SESSION['sesion_bloqueada']);
unset($_SESSION['sesion_bloqueada_tiempo']);
unset($_SESSION['bloqueo_origen']);
unset($_SESSION['bloqueo_motivo']);
$_SESSION['idle_last'] = time();

exit(json_encode(['success' => true]));