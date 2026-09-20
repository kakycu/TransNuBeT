<?php
// cambiar_estado_servicio.php
require_once 'config/database.php'; 

// Iniciar sesión si no está iniciada (necesario para $_SESSION['usuario_id'])
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar cabecera para devolver JSON limpio
header('Content-Type: application/json; charset=utf-8');

// Evitar que errores de PHP se impriman en el JSON (opcional pero recomendado en producción)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Validaciones básicas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

// 2. Obtener datos
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
// Usamos isset para permitir que el valor sea 0
$nuevo_estado = isset($_POST['estado']) ? intval($_POST['estado']) : -1; 

if ($id <= 0 || $nuevo_estado === -1) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // 3. Obtener información del servicio ANTES de actualizar (para el histórico)
    $stmt_info = $db->prepare("SELECT codigo, descripcion FROM clasif_serv WHERE id = :id");
    $stmt_info->execute(['id' => $id]);
    $servicio = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$servicio) {
        echo json_encode(['success' => false, 'message' => 'Servicio no encontrado']);
        exit;
    }

    // 4. Actualizar el estado
    $sql_update = "UPDATE clasif_serv SET activo = :estado WHERE id = :id";
    $stmt_update = $db->prepare($sql_update);
    $resultado = $stmt_update->execute([
        'estado' => $nuevo_estado,
        'id' => $id
    ]);

    if ($resultado) {
        // 5. REGISTRAR EN EL HISTÓRICO
        
        // Definir texto de la operación
        $operacion = ($nuevo_estado == 1) ? 'ACTIVAR_SERVICIO' : 'DESACTIVAR_SERVICIO';
        $estado_texto = ($nuevo_estado == 1) ? 'ACTIVO' : 'INACTIVO';
        
        // Construir descripción detallada
        $descripcion_log = 'Servicio [' . $servicio['codigo'] . '] ' . $servicio['descripcion'] . ' cambiado a estado ' . $estado_texto;

        // Obtener IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            $operacion,
            $descripcion_log,
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nombre'] ?? 'Usuario',
            $ip
        ]);

        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la base de datos']);
    }

} catch (Exception $e) {
    // Log del error en el archivo de logs del servidor, no en la salida JSON
    error_log("Error en cambiar_estado_servicio.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error interno en el servidor']);
}
?>