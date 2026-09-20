<?php
// limpiar_bloqueo.php
session_start();

// Limpiar flags de sesión bloqueada
unset($_SESSION['sesion_bloqueada']);
unset($_SESSION['sesion_bloqueada_tiempo']);

// Devolver respuesta JSON simple
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit();
?>