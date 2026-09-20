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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit();
}

$servicio_id = $_GET['id'];

try {
    // Asegurar que Database esté disponible
    if (!class_exists('Database')) {
        require_once 'config/database.php';
    }
    
    $db = Database::getConnection();
    
    // Consulta para obtener facturas que usan este servicio
    $sql = "SELECT DISTINCT factura_id as id 
            FROM tbl_fact_detalle 
            WHERE servicio_id = :servicio_id 
            ORDER BY factura_id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['servicio_id' => $servicio_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $facturas = [];
    
    if (!empty($resultados)) {
        // Intentar obtener más información de cada factura
        foreach ($resultados as $row) {
            $factura_id = $row['id'];
            
            // Consultar información adicional de la factura
            $sql_fact = "SELECT f.no_fact, f.fecha_emision, c.nombre as cliente_nombre
                        FROM tbl_fact f
                        LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                        WHERE f.id = :id 
                        LIMIT 1";
            
            try {
                $stmt_fact = $db->prepare($sql_fact);
                $stmt_fact->execute(['id' => $factura_id]);
                $factura_info = $stmt_fact->fetch(PDO::FETCH_ASSOC);
                
                if ($factura_info) {
                    $facturas[] = [
                        'id' => $factura_id,
                        'numero' => $factura_info['no_fact'] ?? $factura_id,
                        'fecha' => isset($factura_info['fecha_emision']) ? date('d/m/Y', strtotime($factura_info['fecha_emision'])) : 'Sin fecha',
                        'cliente' => $factura_info['cliente_nombre'] ?? 'Sin cliente'
                    ];
                } else {
                    $facturas[] = [
                        'id' => $factura_id,
                        'numero' => $factura_id,
                        'fecha' => 'Sin fecha',
                        'cliente' => 'Sin cliente'
                    ];
                }
            } catch (Exception $e) {
                // Si falla, usar datos mínimos
                $facturas[] = [
                    'id' => $factura_id,
                    'numero' => $factura_id,
                    'fecha' => 'Sin fecha',
                    'cliente' => 'Sin cliente'
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'facturas' => $facturas,
        'total' => count($facturas)
    ]);
    
} catch (Exception $e) {
    // Enviar error en JSON
    echo json_encode([
        'success' => false,
        'message' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}

exit();
?>