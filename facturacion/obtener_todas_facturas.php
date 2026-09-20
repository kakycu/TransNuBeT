<?php
// Iniciar sesión si es necesario (sin output)
session_start();

// Evitar cualquier output antes del JSON
ob_start();

// Incluir configuración sin output
require_once 'config/header.php';

// Limpiar buffer para asegurar que no haya output
ob_end_clean();

// Establecer header JSON
header('Content-Type: application/json');

if (!isset($_GET['servicio_id']) || !is_numeric($_GET['servicio_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de servicio inválido']);
    exit();
}

$servicio_id = $_GET['servicio_id'];

try {
    // Asegurar que Database esté disponible
    if (!class_exists('Database')) {
        require_once 'config/database.php';
    }
    
    $db = Database::getConnection();
    
    // 1. Contar total de facturas que usan este servicio
    $sql_count = "SELECT COUNT(DISTINCT fd.factura_id) as total 
                 FROM tbl_fact_detalle fd
                 WHERE fd.servicio_id = :servicio_id";
    
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute(['servicio_id' => $servicio_id]);
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_facturas = $count_result['total'] ?? 0;
    
    $facturas = [];
    
    if ($total_facturas > 0) {
        // 2. Obtener las facturas (limitado para no sobrecargar)
        $sql_facturas = "SELECT DISTINCT f.id, f.no_fact, f.fecha_emision, c.nombre as cliente_nombre
                        FROM tbl_fact_detalle fd
                        INNER JOIN tbl_fact f ON fd.factura_id = f.id
                        LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                        WHERE fd.servicio_id = :servicio_id
                        ORDER BY f.fecha_emision DESC, f.no_fact DESC";
        
        $stmt_facturas = $db->prepare($sql_facturas);
        $stmt_facturas->execute(['servicio_id' => $servicio_id]);
        $facturas_data = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($facturas_data as $factura) {
            $facturas[] = [
                'id' => $factura['id'],
                'numero' => $factura['no_fact'],
                'fecha' => isset($factura['fecha_emision']) ? date('d/m/Y', strtotime($factura['fecha_emision'])) : 'Sin fecha',
                'cliente' => $factura['cliente_nombre'] ?? 'Sin cliente'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'total' => $total_facturas,
        'facturas' => $facturas,
        'message' => $total_facturas > 0 ? "Se encontraron {$total_facturas} facturas" : "No se encontraron facturas"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}

exit();
?>