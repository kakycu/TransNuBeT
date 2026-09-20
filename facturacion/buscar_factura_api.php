<?php
// buscar_factura_api.php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $db = Database::getConnection();
    $termino = $_GET['q'] ?? '';
    
    if (empty($termino)) {
        echo json_encode(['success' => true, 'facturas' => []]);
        exit();
    }
    
    // Limpiar término de búsqueda
    $termino = trim($termino);
    $termino_original = $termino;
    
    // Eliminar ceros a la izquierda para búsqueda numérica
    $termino_sin_ceros = ltrim($termino, '0');
    
    // Preparar diferentes variantes de búsqueda
    $variantes = [
        $termino,
        "%{$termino}%",
        $termino_sin_ceros,
        "%{$termino_sin_ceros}%",
    ];
    
    // Si el término es numérico, agregar variante con 4 dígitos
    if (is_numeric($termino_sin_ceros)) {
        $numero = intval($termino_sin_ceros);
        $con_ceros = str_pad($numero, 4, '0', STR_PAD_LEFT);
        $variantes[] = $con_ceros;
        $variantes[] = "%{$con_ceros}";
    }
    
    // Construir consulta SQL con múltiples condiciones
    $sql = "SELECT f.id, f.no_fact, f.fecha_emision, f.total_general, f.estado, 
                   c.nombre as cliente_nombre
            FROM tbl_fact f
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            WHERE ";
    
    $conditions = [];
    $params = [];
    $param_index = 1;
    
    // Búsqueda por número exacto o parcial
    foreach ($variantes as $variante) {
        $conditions[] = "f.no_fact LIKE :term{$param_index}";
        $params[":term{$param_index}"] = $variante;
        $param_index++;
    }
    
    // Búsqueda por ID (si es numérico)
    if (is_numeric($termino_sin_ceros)) {
        $conditions[] = "f.id = :id";
        $params[":id"] = intval($termino_sin_ceros);
    }
    
    // Búsqueda por número secuencial (últimos 4 dígitos)
    if (is_numeric($termino_sin_ceros)) {
        $secuencial = str_pad(intval($termino_sin_ceros), 4, '0', STR_PAD_LEFT);
        $conditions[] = "RIGHT(f.no_fact, 4) = :secuencial";
        $params[":secuencial"] = $secuencial;
    }
    
    $sql .= implode(' OR ', $conditions);
    $sql .= " ORDER BY f.fecha_emision DESC, f.id DESC LIMIT 20";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'facturas' => $facturas,
        'termino' => $termino_original
    ]);
    
} catch (Exception $e) {
    error_log("Error en buscar_factura_api: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar facturas: ' . $e->getMessage()
    ]);
}