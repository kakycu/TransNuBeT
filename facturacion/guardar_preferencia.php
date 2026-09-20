<?php
// guardar_preferencia.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clave = $_POST['clave'] ?? '';
    $valor = $_POST['valor'] ?? '';
    
    if ($clave) {
        $_SESSION[$clave] = $valor;
        echo 'OK';
    } else {
        echo 'ERROR';
    }
}