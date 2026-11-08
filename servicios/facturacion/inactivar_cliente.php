<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: clientes.php');
    exit();
}

$id = intval($_GET['id']);

try {
    $db = Database::getConnection();
    
    // Obtener información del cliente
    $sql = "SELECT nombre, codigo FROM clasif_clientes WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        $_SESSION['sweet_alert'] = [
            'type' => 'error',
            'title' => 'Cliente no encontrado',
            'text' => 'El cliente no existe en el sistema',
            'confirmButtonText' => 'Entendido'
        ];
        header('Location: clientes.php');
        exit();
    }
    
    // Inactivar cliente
    $sql_inactivar = "UPDATE clasif_clientes SET activo = 0 WHERE id = ?";
    $stmt_inactivar = $db->prepare($sql_inactivar);
    $stmt_inactivar->execute([$id]);
    
    // Registrar actividad
    $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
               VALUES (?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        'INACTIVAR_CLIENTE',
        'Cliente [' . $cliente['codigo'] . '] ' . $cliente['nombre'] . ' inactivado por tener facturas registradas',
        $_SESSION['usuario_id'],
        $_SESSION['usuario_nombre'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['sweet_alert'] = [
        'type' => 'success',
        'title' => '¡Cliente inactivado!',
        'text' => "El cliente <strong>{$cliente['nombre']}</strong> ha sido inactivado exitosamente.",
        'confirmButtonText' => 'Entendido'
    ];
    
} catch (Exception $e) {
    error_log("Error al inactivar cliente: " . $e->getMessage());
    $_SESSION['sweet_alert'] = [
        'type' => 'error',
        'title' => 'Error',
        'text' => 'No se pudo inactivar el cliente.',
        'confirmButtonText' => 'Entendido'
    ];
}

header('Location: clientes.php');
exit();
?>