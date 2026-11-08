<?php
// eliminar_usuario.php - Con verificación de dependencias y backup de foto
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
    
    $id = intval($_REQUEST['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar si el usuario existe y obtener datos incluyendo la foto
    $sql_check = "SELECT nombre, apellidos, usuario, activo, foto FROM clasif_usuarios WHERE id = :id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([':id' => $id]);
    $usuario = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir eliminarse a sí mismo
    if ($id == $_SESSION['usuario_id']) {
        throw new Exception('No puede eliminar su propia cuenta');
    }
    
    // Verificar dependencias en otras tablas
    $dependencias = [];
    
    // 1. Verificar si el usuario creó facturas
    $sql_facturas = "SELECT COUNT(*) as total FROM tbl_fact WHERE usuario_id = :id";
    $stmt_facturas = $db->prepare($sql_facturas);
    $stmt_facturas->execute([':id' => $id]);
    $facturas = $stmt_facturas->fetch(PDO::FETCH_ASSOC);
    if ($facturas['total'] > 0) {
        $dependencias[] = "{$facturas['total']} factura(s) creada(s)";
    }
    
    // 2. Verificar si el usuario tiene registros en el histórico
    $sql_historico = "SELECT COUNT(*) as total FROM historico_operaciones WHERE usuario_id = :id";
    $stmt_historico = $db->prepare($sql_historico);
    $stmt_historico->execute([':id' => $id]);
    $historico = $stmt_historico->fetch(PDO::FETCH_ASSOC);
    if ($historico['total'] > 0) {
        $dependencias[] = "{$historico['total']} registro(s) en histórico";
    }
    
    // Si hay dependencias, lanzar error con detalles
    if (!empty($dependencias)) {
        $mensaje_dependencias = "No se puede eliminar el usuario porque tiene las siguientes dependencias:\n";
        $mensaje_dependencias .= "• " . implode("\n• ", $dependencias);
        $mensaje_dependencias .= "\n\nSolución: Desactive al usuario en lugar de eliminarlo.";
        
        throw new Exception($mensaje_dependencias);
    }
    
    // --- PROCESAR LA FOTO ANTES DE ELIMINAR EL REGISTRO ---
    
    // Verificar si el usuario tiene foto (que no sea nula, vacía o por defecto)
    if (!empty($usuario['foto']) && $usuario['foto'] != 'default.png' && $usuario['foto'] != 'default.jpg') {
        
        // Obtener la ruta base del proyecto
        $ruta_proyecto = str_replace('\\', '/', realpath(__DIR__));
        
        // Si el archivo está en una subcarpeta, ajustamos
        if (basename(__DIR__) == 'config' || basename(__DIR__) == 'includes') {
            $ruta_proyecto = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        }
        
        // La foto ya tiene la ruta completa desde la raíz del proyecto
        // Solo necesitamos quitar el primer slash si existe
        $ruta_relativa_foto = ltrim($usuario['foto'], '/');
        
        // Construir la ruta completa de la foto
        $ruta_origen = $ruta_proyecto . '/' . $ruta_relativa_foto;
        
        // Definir carpeta de destino (carpeta eliminados dentro del mismo directorio de fotos)
        $directorio_fotos = dirname($ruta_origen);
        $carpeta_destino = $directorio_fotos . '/eliminados/';
        
        // Verificar si el archivo de origen existe
        if (!file_exists($ruta_origen)) {
            throw new Exception("La foto del usuario no existe en la ruta: " . $ruta_origen);
        }
        
        // Crear la carpeta de destino si no existe
        if (!file_exists($carpeta_destino)) {
            if (!mkdir($carpeta_destino, 0777, true)) {
                throw new Exception("No se pudo crear la carpeta de destino: " . $carpeta_destino);
            }
        }
        
        // Verificar que la carpeta destino sea escribible
        if (!is_writable($carpeta_destino)) {
            throw new Exception("La carpeta de destino no tiene permisos de escritura: " . $carpeta_destino);
        }
        
        // Obtener solo el nombre del archivo sin la ruta
        $nombre_archivo = basename($usuario['foto']);
        
        // Obtener extensión del archivo
        $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
        
        // Crear nuevo nombre: nombre_usuario-[nombre_original_en_bd].extension
        $nombre_usuario = preg_replace('/[^a-zA-Z0-9_-]/', '', $usuario['usuario']);
        if (empty($nombre_usuario)) {
            $nombre_usuario = 'usuario_' . $id;
        }
        
        $nombre_original_sin_extension = pathinfo($nombre_archivo, PATHINFO_FILENAME);
        
        $nuevo_nombre = $nombre_usuario . '-[' . $nombre_original_sin_extension . '].' . $extension;
        $ruta_destino = $carpeta_destino . $nuevo_nombre;
        
        // Intentar mover el archivo
        if (!rename($ruta_origen, $ruta_destino)) {
            // Si rename falla, intentar con copy+unlink
            if (!copy($ruta_origen, $ruta_destino)) {
                throw new Exception("No se pudo copiar la foto a: " . $ruta_destino);
            }
            if (!unlink($ruta_origen)) {
                throw new Exception("La foto se copió pero no se pudo eliminar el original: " . $ruta_origen);
            }
        }
        
        // Si llegamos aquí, la foto se movió exitosamente
    }
    
    // Registrar en histórico antes de eliminar
    try {
        $accion = 'ELIMINAR_USUARIO';
        $descripcion = "Usuario {$usuario['nombre']} {$usuario['apellidos']} eliminado (ID: {$id})";
        
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
        throw new Exception("Error registrando en histórico: " . $log_error->getMessage());
    }
    
    // Eliminar el usuario
    $sql = "DELETE FROM clasif_usuarios WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Usuario eliminado exitosamente' . (!empty($usuario['foto']) ? ' y foto respaldada' : ''),
        'type' => 'success'
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'type' => 'error'
    ]);
}
exit();
?>