<?php
// verificar_codigo_servicio.php - VERSIÓN CORREGIDA
ob_start(); // Iniciar buffer de salida

session_start();

// Configurar para NO mostrar errores al usuario
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Obtener código desde GET
$codigo = isset($_GET['codigo']) ? trim(strtoupper($_GET['codigo'])) : '';

// Validaciones básicas
if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 10) {
    ob_end_clean(); // Limpiar buffer
    echo json_encode([
        'disponible' => false,
        'mensaje' => 'Código inválido. Debe tener entre 2 y 10 caracteres.'
    ]);
    exit();
}

// Validar formato: solo letras mayúsculas y números
if (!preg_match('/^[A-Z0-9]+$/', $codigo)) {
    ob_end_clean(); // Limpiar buffer
    echo json_encode([
        'disponible' => false,
        'mensaje' => 'Formato inválido. Solo letras mayúsculas y números.'
    ]);
    exit();
}

try {
    // INCLUIR EL MISMO ARCHIVO DE CONFIGURACIÓN QUE USA nuevo_servicio.php
    require_once 'config/header.php';
    
    // Obtener conexión a la base de datos
    $db = Database::getConnection();
    
    // Verificar si el código ya existe en la base de datos
    $sql = "SELECT COUNT(*) as count FROM clasif_serv WHERE codigo = :codigo";
    $stmt = $db->prepare($sql);
    $stmt->execute(['codigo' => $codigo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        // Obtener información del servicio existente
        $sql_info = "SELECT descripcion, activo FROM clasif_serv WHERE codigo = :codigo";
        $stmt_info = $db->prepare($sql_info);
        $stmt_info->execute(['codigo' => $codigo]);
        $servicio = $stmt_info->fetch(PDO::FETCH_ASSOC);
        
        $estado = $servicio['activo'] == 1 ? 'activo' : 'inactivo';
        
        ob_end_clean(); // Limpiar buffer
        echo json_encode([
            'disponible' => false,
            'mensaje' => "Código ya existe. Servicio: " . htmlspecialchars($servicio['descripcion']) . " (Estado: $estado)"
        ]);
    } else {
        ob_end_clean(); // Limpiar buffer
        echo json_encode([
            'disponible' => true,
            'mensaje' => 'Código disponible ✓'
        ]);
    }
    
} catch (Exception $e) {
    ob_end_clean(); // Limpiar buffer
    echo json_encode([
        'disponible' => false,
        'mensaje' => 'Error al verificar disponibilidad. Intente nuevamente.'
    ]);
}
?>