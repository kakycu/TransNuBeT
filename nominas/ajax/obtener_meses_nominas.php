<?php
require_once '../config/database.php';
require_once '../includes/funciones.php';

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

if (!permiso_puede('nominas', 'ver')) {
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'denied' => true]);
    exit();
}

$trabajador_id = isset($_GET['trabajador_id']) ? (int)$_GET['trabajador_id'] : 0;

if (!$trabajador_id) {
    echo json_encode(['success' => false, 'message' => 'ID de trabajador no válido']);
    exit();
}

try {
    // Obtener todas las nóminas automáticas del trabajador
    $sql = "SELECT 
                n.id as nomina_id,
                n.periodo_desde,
                n.periodo_hasta,
                n.horas_laboradas,
                n.horas_nocturnas,
                n.horas_extras,
                n.total_salario_devengado,
                DATE_FORMAT(n.periodo_desde, '%M %Y') as periodo_texto,
                CONCAT(
                    DATE_FORMAT(n.periodo_desde, '%Y'), '-',
                    DATE_FORMAT(n.periodo_desde, '%m')
                ) as periodo_id
            FROM nominas n
            WHERE n.trabajador_id = ?
              AND n.tipo_nomina = 'automatica'
              AND n.estado IN ('contabilizado', 'cerrado', 'pagado')
            ORDER BY n.periodo_desde DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$trabajador_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular días trabajados basado en horas_laboradas / 8
    $meses = [];
    foreach ($resultados as $row) {
        $horas = (float)$row['horas_laboradas'];
        $dias = round($horas / 8, 2);
        
        $meses[] = [
            'periodo_id' => $row['periodo_id'],
            'periodo_texto' => $row['periodo_texto'],
            'nomina_id' => $row['nomina_id'],
            'horas_laboradas' => $horas,
            'dias_trabajados' => $dias,
            'periodo_desde' => $row['periodo_desde'],
            'periodo_hasta' => $row['periodo_hasta']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'meses' => $meses,
        'total_meses' => count($meses)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>