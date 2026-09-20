<?php
// toggle_estado_usuario.php
ob_start();

require_once 'config/header.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != '1') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $db = Database::getConnection();
    
    // Usar $_REQUEST para soportar tanto GET como POST
    $id = intval($_REQUEST['id'] ?? 0);
    $estado = intval($_REQUEST['estado'] ?? 1);
    
    if ($id <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar si el usuario existe
    $sql_check = "SELECT nombre, apellidos, activo FROM clasif_usuarios WHERE id = :id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([':id' => $id]);
    $usuario = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir desactivarse a sí mismo
    if ($id == $_SESSION['usuario_id']) {
        throw new Exception('No puede cambiar su propio estado');
    }
    
    // Si el estado actual es igual al nuevo estado, no hacer nada
    if ($usuario['activo'] == $estado) {
        throw new Exception('El usuario ya se encuentra en ese estado');
    }
    
    // Actualizar estado
    $sql = "UPDATE clasif_usuarios SET activo = :estado, fecha_actualizacion = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':estado' => $estado, ':id' => $id]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected === 0) {
        throw new Exception('No se pudo actualizar el estado del usuario');
    }
    
    // Registrar actividad en el histórico
    $accion = $estado ? 'ACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO';
    $descripcion = $estado 
        ? "Usuario {$usuario['nombre']} {$usuario['apellidos']} activado (ID: {$id})" 
        : "Usuario {$usuario['nombre']} {$usuario['apellidos']} desactivado (ID: {$id})";
    
    try {
        $sql_log = "INSERT INTO historico_operaciones 
                    (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora) 
                    VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address, NOW())";
        
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            ':operacion' => $accion,
            ':descripcion' => $descripcion,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':usuario_nombre' => $_SESSION['usuario_nombre'],
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $log_error) {
        error_log("Error registrando en histórico: " . $log_error->getMessage());
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $estado ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
        'type' => 'success',
        'data' => [
            'id' => $id,
            'estado' => $estado,
            'nombre' => $usuario['nombre'] . ' ' . $usuario['apellidos']
        ]
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    // Usar 200 en lugar de 400 para errores controlados
    http_response_code(200);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'type' => 'error'
    ]);
}
exit();