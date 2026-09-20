<?php
// procesar_oferta_temporal.php - VERSIÓN CON DEPURACIÓN
session_start();
require_once 'config/header.php';

// Limpiar cualquier salida previa
ob_clean();

// Establecer header JSON
header('Content-Type: application/json');

// Solo aceptar peticiones POST con JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

// Obtener datos JSON
$json = file_get_contents('php://input');

// Verificar que se recibieron datos
if (!$json) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos']);
    exit();
}

// Intentar decodificar JSON
$data = json_decode($json, true);
if ($data === null) {
    echo json_encode([
        'success' => false, 
        'message' => 'JSON inválido',
        'raw_data' => substr($json, 0, 200) // Mostrar primeros 200 caracteres para depurar
    ]);
    exit();
}

try {
    // 1. Verificar datos requeridos
    $required_fields = ['no_oferta', 'cliente', 'servicios'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo requerido faltante: $field"]);
            exit();
        }
    }
    
    // 2. Generar un token único para esta oferta temporal
    $token = bin2hex(random_bytes(32));
    
    // 3. Inicializar array de ofertas temporales si no existe
    if (!isset($_SESSION['ofertas_temporales'])) {
        $_SESSION['ofertas_temporales'] = [];
    }
    
    // 4. Almacenar los datos de la oferta en sesión (temporal)
    $_SESSION['ofertas_temporales'][$token] = [
        'data' => $data,
        'creado' => time(),
        'usuario_id' => $_SESSION['usuario_id']
    ];
    
    // 5. Limpiar ofertas antiguas (más de 1 hora)
    $tiempo_limite = time() - 3600; // 1 hora
    foreach ($_SESSION['ofertas_temporales'] as $key => $oferta) {
        if ($oferta['creado'] < $tiempo_limite) {
            unset($_SESSION['ofertas_temporales'][$key]);
        }
    }
    
    // 6. Responder con éxito y el token
    echo json_encode([
        'success' => true,
        'token' => $token,
        'message' => 'Oferta generada correctamente'
    ]);
    
} catch (Exception $e) {
    error_log("Error al procesar oferta temporal: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}