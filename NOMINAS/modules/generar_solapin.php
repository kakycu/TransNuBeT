<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener ID del empleado
$empleado_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : 0);

if ($empleado_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de empleado inválido']);
    exit;
}

// Ruta física para guardar de forma temporal
$carpeta = $_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/solapines/';
$archivo_fisico = $carpeta . 'solapin_' . $empleado_id . '.png';
$url_relativa = '/NOMINAS/solapines/solapin_' . $empleado_id . '.png';

// Si es una petición AJAX (para obtener el enlace de descarga o visualización dinámica)
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    header('Content-Type: application/json');
    $resultado = generarSolapinConPlantilla($empleado_id, $pdo, null, $archivo_fisico);
    
    if ($resultado) {
        echo json_encode(['success' => true, 'url' => $url_relativa . '?t=' . time()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al generar el archivo del solapín']);
    }
    exit;
}

// Si es carga/descarga directa del navegador
$resultado = generarSolapinConPlantilla($empleado_id, $pdo);

if ($resultado) {
    $raw_image = base64_decode(str_replace('data:image/png;base64,', '', $resultado));
    header('Content-Type: image/png');
    
    if (isset($_GET['descargar'])) {
        header('Content-Disposition: attachment; filename="solapin_' . $empleado_id . '.png"');
    } else {
        header('Content-Disposition: inline; filename="solapin_' . $empleado_id . '.png"');
    }
    echo $raw_image;
} else {
    http_response_code(500);
    echo "Error al generar el solapín. Verifique la existencia de la plantilla.";
}
exit;
?>

