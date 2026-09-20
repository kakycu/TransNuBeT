<?php
// check_maintenance.php NO LO ESTOY USANDO
header('Content-Type: application/json');
session_start();

try {
    require_once 'config/database.php';
    
    $db = Database::getConnection();
    
    // Consulta según tu estructura de BD
    $sql = "SELECT modo_mantenimiento, nombre_empresa, nombre_proyecto FROM configuracion_sistema LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si se obtuvo resultado
    if ($config) {
        // modo_mantenimiento es tinyint(1) - 0 o 1
        $maintenance_mode = isset($config['modo_mantenimiento']) && $config['modo_mantenimiento'] == 1;
        
        echo json_encode([
            'success' => true,
            'maintenance_mode' => $maintenance_mode,
            'empresa' => $config['nombre_empresa'] ?? 'PDL Visiones',
            'proyecto' => $config['nombre_proyecto'] ?? 'SISFACT',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $maintenance_mode ? 
                'Sistema en mantenimiento' : 'Sistema operativo',
            'data' => [
                'modo_mantenimiento' => $config['modo_mantenimiento'] ?? 0
            ]
        ]);
    } else {
        // Si no hay configuración en la BD
        echo json_encode([
            'success' => false,
            'maintenance_mode' => false,
            'empresa' => 'PDL Visiones',
            'proyecto' => 'SISFACT',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Configuración no encontrada en BD',
            'data' => null
        ]);
    }
    
} catch (PDOException $e) {
    // Error específico de PDO
    error_log("Error PDO en check_maintenance.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'maintenance_mode' => true, // Por seguridad, asumimos mantenimiento si hay error
        'empresa' => 'PDL Visiones',
        'proyecto' => 'SISFACT',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'message' => 'Error al conectar con la base de datos'
    ]);
    
} catch (Exception $e) {
    // Error general
    error_log("Error general en check_maintenance.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'maintenance_mode' => true, // Por seguridad, asumimos mantenimiento si hay error
        'empresa' => 'PDL Visiones',
        'proyecto' => 'SISFACT',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'message' => 'Error al verificar estado del sistema'
    ]);
}