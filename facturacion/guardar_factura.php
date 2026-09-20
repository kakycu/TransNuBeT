<?php
// guardar_factura.php
// Incluir solo la inicialización SIN HTML

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezados JSON primero
header('Content-Type: application/json; charset=utf-8');

// Incluir solo la configuración de base de datos
require_once 'config/database.php';

// Buffer de salida para capturar cualquier salida no deseada
ob_start();

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }
    
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception("Sesión no válida. Por favor, inicie sesión nuevamente.");
    }
    
    // Obtener conexión a la base de datos
    $db = Database::getConnection();
    
    // Validar datos básicos
    $cliente_id = $_POST['cliente_id'] ?? '';
    $no_fact = $_POST['no_fact'] ?? '';
    $estado = $_POST['estado'] ?? 'PENDIENTE';
    
    if (empty($cliente_id) || $cliente_id == "0") {
        throw new Exception("Debe seleccionar un cliente");
    }
    
    if (empty($no_fact)) {
        throw new Exception("Número de documento inválido");
    }
    
    // Validar que el número de factura no exista
    $sql_verificar = "SELECT COUNT(*) as count FROM tbl_fact WHERE no_fact = :no_fact";
    $stmt_verificar = $db->prepare($sql_verificar);
    $stmt_verificar->execute(['no_fact' => $no_fact]);
    $existe = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if ($existe['count'] > 0) {
        throw new Exception("El número de documento $no_fact ya existe. Recargue la página.");
    }
    
    // Calcular totales
    $servicios_ids = $_POST['servicio_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $precios = $_POST['precio_unitario'] ?? [];
    
    $subtotal_total = 0;
    $total_general = 0;
    $servicios_validos = 0;
    
    for ($i = 0; $i < count($servicios_ids); $i++) {
        if (!empty($servicios_ids[$i]) && !empty($cantidades[$i]) && floatval($cantidades[$i]) > 0) {
            $precio_unitario = floatval($precios[$i]);
            $cantidad = floatval($cantidades[$i]);
            $subtotal_linea = $precio_unitario * $cantidad;
            
            $subtotal_total += $subtotal_linea;
            $total_general += $subtotal_linea;
            $servicios_validos++;
        }
    }
    
    if ($servicios_validos === 0) {
        throw new Exception("El Doocumento debe tener al menos un servicio");
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Preparar datos de la factura
    $fecha_emision = ($_POST['fecha_emision'] ?? date('Y-m-d')) . ' ' . date('H:i:s');
    $cliente_id = intval($_POST['cliente_id']);
    $tipo_pago_id = intval($_POST['tipo_pago_id'] ?? 0);
    $subtotal = $subtotal_total;
    $total_general = $total_general;
    $estado = $_POST['estado'];
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
    $fecha_contabilizacion = null;
    $usuario_contabilizacion = null;
    $fecha_pago = null;
    $ref_pago = null;

// Limpiar observaciones de saltos de línea problemáticos
$observaciones = $_POST['observaciones'] ?? null;
if ($observaciones !== null) {
    // Eliminar caracteres de control problemáticos
    $observaciones = preg_replace('/[\x00-\x1F\x7F]/', ' ', $observaciones);
    // O alternativamente, conservar saltos de línea pero asegurar que no rompan JS
    $observaciones = trim($observaciones);
}
$observaciones = !empty(trim($observaciones)) ? trim($observaciones) : null;

    // Si el estado es PAGADA o CONTABILIZADA
    if ($estado === 'PAGADA' || $estado === 'CONTABILIZADA') {
        $fecha_contabilizacion = date('Y-m-d H:i:s');
        $usuario_contabilizacion = $usuario_id;
    }
    
    // Si el estado es PAGADA y tenemos información de pago
    if ($estado === 'PAGADA') {
        if (isset($_POST['fecha_pago']) && isset($_POST['hora_pago'])) {
            // Convertir hora de formato AM/PM a 24 horas
            $hora_pago = $_POST['hora_pago'];
            $fecha_pago_input = $_POST['fecha_pago'];
            
            // Convertir hora AM/PM a formato 24 horas
            $fecha_pago = convertirHoraAMPMa24($fecha_pago_input, $hora_pago);
        } else {
            // Si no se proporcionó fecha/hora específica, usar la actual
            $fecha_pago = date('Y-m-d H:i:s');
        }
        
        // Referencia de pago (opcional)
        $ref_pago = $_POST['referencia_pago'] ?? null;
    }
    
    // Insertar factura - USANDO LOS NOMBRES EXACTOS DE LAS COLUMNAS
    $sql_factura = "INSERT INTO tbl_fact (
					no_fact, 
					fecha_emision, 
					cliente_id, 
					tipo_pago_id, 
					subtotal, 
					total_general, 
					usuario_id, 
					estado, 
					fecha_contabilizacion, 
					usuario_contabilizacion,
					fecha_pago,
					Ref_pago,
					observaciones
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_factura = $db->prepare($sql_factura);
    $stmt_factura->execute([
        $no_fact, 
        $fecha_emision, 
        $cliente_id, 
        $tipo_pago_id,
        $subtotal, 
        $total_general, 
        $usuario_id, 
        $estado, 
        $fecha_contabilizacion, 
        $usuario_contabilizacion,
        $fecha_pago,
        $ref_pago,
		$observaciones
    ]);
    
    $factura_id = $db->lastInsertId();
    
    if (!$factura_id || $factura_id == 0) {
        throw new Exception("Error: No se pudo crear el documento en la base de datos.");
    }
    
    // Insertar detalles
    for ($i = 0; $i < count($servicios_ids); $i++) {
        $servicio_id = trim($servicios_ids[$i]);
        $cantidad = trim($cantidades[$i]);
        $precio_unitario = trim($precios[$i]);
        
        if (!empty($servicio_id) && $servicio_id != "0" && is_numeric($servicio_id) &&
            !empty($cantidad) && floatval($cantidad) > 0 &&
            !empty($precio_unitario) && is_numeric($precio_unitario)) {
            
            $servicio_id = intval($servicio_id);
            $cantidad = floatval($cantidad);
            $precio_unitario = floatval($precio_unitario);
            $total_linea = ($precio_unitario * $cantidad);
            
            // Insertar detalle directamente
            $sql_detalle = "INSERT INTO tbl_fact_detalle 
                           (factura_id, servicio_id, cantidad, precio_unitario, total_linea) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt_detalle = $db->prepare($sql_detalle);
            $stmt_detalle->execute([
                $factura_id, 
                $servicio_id, 
                $cantidad, 
                $precio_unitario,
                $total_linea
            ]);
        }
    }
    
    // Registrar actividad en el histórico
    $descripcion_log = 'Documento ' . $no_fact . ' creado';
if (!empty($observaciones)) {
    $descripcion_log .= ' (Obs: ' . substr($observaciones, 0, 50) . (strlen($observaciones) > 50 ? '...' : '') . ')';
}
    if ($estado === 'PAGADA') {
        $descripcion_log .= ' y marcada como pagado';
        if ($ref_pago) {
            $descripcion_log .= ' (Ref: ' . $ref_pago . ')';
        }
    } elseif ($estado === 'CONTABILIZADA') {
        $descripcion_log .= ' y contabilizada';
    }
    
    $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
               VALUES (?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        'CREAR_FACTURA',
        $descripcion_log,
        $usuario_id,
        $usuario_nombre,
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    // Limpiar buffer de salida completamente
    ob_end_clean();
    
    // Enviar respuesta JSON exitosa
    $response = [
        'success' => true,
        'message' => 'Documento creado exitosamente' . 
                    ($estado === 'PAGADA' ? ' y registrada como pagado' : '') .
                    ($estado === 'CONTABILIZADA' ? ' y contabilizado' : ''),
        'factura_id' => $factura_id,
        'no_fact' => $no_fact,
        'redirect' => 'ver_factura.php?id=' . $factura_id . '&success=1'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
    
} catch (Exception $e) {
    // Limpiar buffer de salida primero
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    
    // Hacer rollback si hay transacción activa
    if (isset($db) && $db->inTransaction()) {
        try {
            $db->rollBack();
        } catch (PDOException $rollbackError) {
            // Solo registrar en log
            error_log("Error al hacer rollback: " . $rollbackError->getMessage());
        }
    }
    
    // Registrar error en log
    error_log("Error en guardar_factura.php: " . $e->getMessage());
    
    // Enviar respuesta de error JSON
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Función para convertir hora AM/PM a formato 24 horas
function convertirHoraAMPMa24($fecha, $hora_ampm) {
    // La hora ya viene en formato "02:45 PM" del MDTimePicker
    // Separar hora y AM/PM
    $partes = explode(' ', $hora_ampm);
    
    if (count($partes) !== 2) {
        // Si no tiene formato AM/PM, devolver como está
        return $fecha . ' ' . $hora_ampm;
    }
    
    $tiempo = $partes[0]; // "02:45"
    $periodo = strtoupper($partes[1]); // "PM"
    
    // Separar horas y minutos
    list($horas, $minutos) = explode(':', $tiempo);
    
    // Convertir a 24 horas
    if ($periodo === 'PM' && $horas != 12) {
        $horas = (int)$horas + 12;
    } elseif ($periodo === 'AM' && $horas == 12) {
        $horas = 0;
    }
    
    // Formatear a 2 dígitos
    $horas = str_pad($horas, 2, '0', STR_PAD_LEFT);
    
    // Devolver fecha y hora en formato MySQL
    return $fecha . ' ' . $horas . ':' . $minutos . ':00';
}

// Si llegamos aquí sin salir antes, algo salió mal
$response = [
    'success' => false,
    'message' => 'Error desconocido al procesar la solicitud'
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>