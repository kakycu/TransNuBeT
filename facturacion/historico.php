<?php
// includes/historico.php

/**
 * Registrar operación en el historial del sistema
 * @param string $tipo Tipo de operación
 * @param string $descripcion Descripción detallada
 * @param PDO $db Conexión a la base de datos
 * @param int|null $usuario_id ID del usuario (opcional, usa sesión por defecto)
 * @return bool True si se registró correctamente
 */
function registrarOperacion($tipo, $descripcion, $db, $usuario_id = null) {
    try {
        $usuario_id = $usuario_id ?? ($_SESSION['usuario_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Obtener nombre del usuario
        $usuario_nombre = null;
        if ($usuario_id > 0) {
            $sql_usuario = "SELECT CONCAT(nombre, ' ', apellidos) as nombre_completo 
                           FROM clasif_usuarios 
                           WHERE id = :id";
            $stmt_usuario = $db->prepare($sql_usuario);
            $stmt_usuario->execute(['id' => $usuario_id]);
            $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
            $usuario_nombre = $usuario['nombre_completo'] ?? null;
        }
        
        $sql = "INSERT INTO historico_operaciones 
                (usuario_id, operacion, descripcion, ip_address, fecha_hora, usuario_nombre) 
                VALUES (:usuario_id, :tipo, :descripcion, :ip, NOW(), :usuario_nombre)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':tipo' => $tipo,
            ':descripcion' => $descripcion,
            ':ip' => $ip,
            ':usuario_nombre' => $usuario_nombre
        ]);
        
        return true;
    } catch (Exception $e) {
        // En desarrollo, puedes loggear el error
        error_log("Error al registrar operación: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener historial de operaciones
 * @param PDO $db Conexión a la base de datos
 * @param int $limit Límite de registros
 * @return array Lista de operaciones
 */
function obtenerHistorial($db, $limit = 100) {
    try {
        $sql = "SELECT h.*, u.nombre, u.apellidos, u.usuario as usuario_login
                FROM historico_operaciones h
                LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
                ORDER BY h.fecha_hora DESC 
                LIMIT :limit";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al obtener historial: " . $e->getMessage());
        return [];
    }
}

/**
 * Determinar icono y color según tipo de operación
 * @param string $tipo Tipo de operación
 * @return array Arreglo con icono, color y clase CSS
 */
function obtenerIconoTipo($tipo) {
    $iconos = [
        'LOGIN' => ['icono' => 'fa-sign-in-alt', 'color' => 'success', 'bg' => 'bg-success'],
        'LOGOUT' => ['icono' => 'fa-sign-out-alt', 'color' => 'warning', 'bg' => 'bg-warning'],
        'CREAR_FACTURA' => ['icono' => 'fa-file-invoice', 'color' => 'primary', 'bg' => 'bg-primary'],
        'EDITAR_FACTURA' => ['icono' => 'fa-edit', 'color' => 'info', 'bg' => 'bg-info'],
        'ELIMINAR_FACTURA' => ['icono' => 'fa-trash', 'color' => 'danger', 'bg' => 'bg-danger'],
        'CONTABILIZAR_FACTURA' => ['icono' => 'fa-check-circle', 'color' => 'success', 'bg' => 'bg-success'],
        'CLIENTE_CREAR' => ['icono' => 'fa-user-plus', 'color' => 'success', 'bg' => 'bg-success'],
        'EDITAR_CLIENTE' => ['icono' => 'fa-user-edit', 'color' => 'info', 'bg' => 'bg-info'],
        'CLIENTE_ELIMINAR' => ['icono' => 'fa-user-minus', 'color' => 'danger', 'bg' => 'bg-danger'],
        'SERVICIO_CREAR' => ['icono' => 'fa-plus-circle', 'color' => 'success', 'bg' => 'bg-success'],
        'SERVICIO_EDITAR' => ['icono' => 'fa-edit', 'color' => 'warning', 'bg' => 'bg-warning'],
        'SERVICIO_ELIMINAR' => ['icono' => 'fa-trash', 'color' => 'danger', 'bg' => 'bg-danger'],
        'CONFIGURACION' => ['icono' => 'fa-cog', 'color' => 'secondary', 'bg' => 'bg-secondary'],
        'ACTUALIZAR_CONFIG' => ['icono' => 'fa-cog', 'color' => 'info', 'bg' => 'bg-info'],
        'RESTABLECER_CONFIG' => ['icono' => 'fa-redo', 'color' => 'warning', 'bg' => 'bg-warning'],
        'REPORTE' => ['icono' => 'fa-chart-bar', 'color' => 'info', 'bg' => 'bg-info'],
        'ACTUALIZACION_PERFIL' => ['icono' => 'fa-user-edit', 'color' => 'info', 'bg' => 'bg-info'],
        'ACTUALIZACION_FOTO' => ['icono' => 'fa-camera', 'color' => 'primary', 'bg' => 'bg-primary'],
        'CAMBIO_PASSWORD' => ['icono' => 'fa-key', 'color' => 'warning', 'bg' => 'bg-warning'],
        'CAMBIO_ESTADO' => ['icono' => 'fa-toggle-on', 'color' => 'info', 'bg' => 'bg-info'],
        'BACKUP' => ['icono' => 'fa-database', 'color' => 'primary', 'bg' => 'bg-primary'],
        'RESTAURAR' => ['icono' => 'fa-history', 'color' => 'warning', 'bg' => 'bg-warning']
    ];
    
    return $iconos[$tipo] ?? ['icono' => 'fa-info-circle', 'color' => 'secondary', 'bg' => 'bg-secondary'];
}

/**
 * Obtener tipos únicos de operaciones para filtros
 * @param PDO $db Conexión a la base de datos
 * @return array Lista de tipos de operación
 */
function obtenerTiposOperacion($db) {
    try {
        $sql = "SELECT DISTINCT operacion FROM historico_operaciones WHERE operacion IS NOT NULL ORDER BY operacion";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error al obtener tipos de operación: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener estadísticas del histórico
 * @param PDO $db Conexión a la base de datos
 * @return array Estadísticas
 */
function obtenerEstadisticasHistorial($db) {
    try {
        $estadisticas = [];
        
        // Total registros
        $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total);
        $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Registros hoy
        $sql_hoy = "SELECT COUNT(*) as total FROM historico_operaciones WHERE DATE(fecha_hora) = CURDATE()";
        $stmt = $db->query($sql_hoy);
        $estadisticas['hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Usuarios activos
        $sql_usuarios = "SELECT COUNT(DISTINCT usuario_id) as total FROM historico_operaciones WHERE usuario_id IS NOT NULL";
        $stmt = $db->query($sql_usuarios);
        $estadisticas['usuarios_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Tipos únicos
        $sql_tipos = "SELECT COUNT(DISTINCT operacion) as total FROM historico_operaciones";
        $stmt = $db->query($sql_tipos);
        $estadisticas['tipos_unicos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $estadisticas;
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas: " . $e->getMessage());
        return ['total' => 0, 'hoy' => 0, 'usuarios_activos' => 0, 'tipos_unicos' => 0];
    }
}
?>