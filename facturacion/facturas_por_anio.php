<?php
// facturas_por_anio.php - VERSIÓN CORREGIDA

// 1. INICIAR SESIÓN PRIMERO
session_start();

// 2. ESTABLECER HEADERS DE JSON
header('Content-Type: application/json; charset=utf-8');

// 3. LIMPIAR BUFFER (por si hay algo antes)
if (ob_get_length()) ob_clean();

// 4. VERIFICAR AUTENTICACIÓN
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// 5. OBTENER AÑO
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

try {
    // 6. CONEXIÓN A BD
    require_once 'config/database.php';
    $db = Database::getConnection();
    
    // 7. OBTENER TOTAL DE TODAS LAS FACTURAS
    $sql_total_bd = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt_total = $db->prepare($sql_total_bd);
    $stmt_total->execute();
    $total_bd = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 8. OBTENER TOTAL DE FACTURAS DEL AÑO
    $sql_count = "SELECT COUNT(*) as total FROM tbl_fact WHERE YEAR(fecha_emision) = :anio";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute(['anio' => $anio]);
    $total_facturas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 9. OBTENER FACTURAS DEL AÑO
    $sql_facturas = "SELECT f.*, c.nombre as cliente_nombre 
                     FROM tbl_fact f 
                     LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                     WHERE YEAR(f.fecha_emision) = :anio
                     ORDER BY f.fecha_emision DESC 
                     LIMIT 20";
    
    $stmt = $db->prepare($sql_facturas);
    $stmt->execute(['anio' => $anio]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. ENVIAR RESPUESTA JSON
    $response = [
        'success' => true,
        'anio' => $anio,
        'total_facturas' => (int)$total_facturas,
        'total_bd' => (int)$total_bd,
        'facturas' => $facturas
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // 11. MANEJO DE ERRORES
    error_log("Error en facturas_por_anio.php: " . $e->getMessage());
    
    // Limpiar buffer por si hay salida previa
    if (ob_get_length()) ob_clean();
    
    // Enviar error como JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

// 12. TERMINAR EJECUCIÓN
exit();
?>