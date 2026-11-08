<?php
session_start();
require_once 'config/database.php';

$nombre_usuario = 'Usuario';

if (isset($_SESSION['usuario_id'])) {
    try {
        $db = Database::getConnection();
        
        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            'LOGOUT',
            'Cierre de sesión',
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nombre'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $nombre_usuario = $_SESSION['usuario_nombre'];
    } catch (Exception $e) {
        error_log("Error al registrar logout: " . $e->getMessage());
    }
}



// Headers para evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: logout_display.php");
exit();
?>