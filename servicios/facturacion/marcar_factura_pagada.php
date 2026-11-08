<?php
// marcar_factura_pagada.php
session_start();
require_once 'config/database.php';

// Configurar la zona horaria para que coincida con el resto del sistema
date_default_timezone_set('America/New_York'); 

// 1. Verificación de sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
    exit();
}

$response = ['success' => false, 'message' => ''];

try {
    $db = Database::getConnection();
    
    // 2. Validación de datos recibidos por POST
    if (!isset($_POST['id']) || !isset($_POST['fecha_hora']) || !isset($_POST['ref_pago'])) {
        throw new Exception('Faltan datos obligatorios para procesar el pago.');
    }
    
    $factura_id = (int)$_POST['id'];
    $fecha_pago_seleccionada = $_POST['fecha_hora']; // Fecha/Hora elegida en el modal
    $ref_pago = trim($_POST['ref_pago']);
    $usuario_actual_id = $_SESSION['usuario_id'];
    $usuario_nombre = $_SESSION['usuario_nombre'];
    
    if (empty($ref_pago)) {
        throw new Exception('La Referencia de Pago (comprobante) es obligatoria.');
    }
    
    // 3. Verificar existencia de la factura y estado actual
    $sql_verificar = "SELECT id, no_fact, estado, fecha_contabilizacion FROM tbl_fact WHERE id = :id";
    $stmt_verificar = $db->prepare($sql_verificar);
    $stmt_verificar->execute(['id' => $factura_id]);
    $factura = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        throw new Exception('La factura no existe en la base de datos.');
    }

	// No permitir pagar facturas ANULADAS o PENDIENTES
	if ($factura['estado'] === 'ANULADA' || $factura['estado'] === 'PENDIENTE') {
		throw new Exception('No se puede registrar un pago para una factura que está ANULADA o PENDIENTE.');
	}
    
    /**
     * 4. PROCESO DE ACTUALIZACIÓN (Query Principal)
     * - CORRECCIÓN: Si el estado actual es 'CERRADA', se mantiene como 'CERRADA'
     * - En cualquier otro caso (PENDIENTE, CONTABILIZADA, etc.), se cambia a 'PAGADA'
     * - fecha_pago: Se registra la fecha indicada en el modal.
     * - Ref_pago: Se guarda la referencia del comprobante.
     * - usuario_contabilizacion: Se registra el ID del usuario que guarda el pago.
     * - fecha_contabilizacion: Si estaba vacía, se pone la fecha/hora ACTUAL del servidor.
     */
    $db->beginTransaction();

    // Aseguramos que la referencia esté en mayúsculas y sin espacios extra
    $ref_pago_final = strtoupper(trim($ref_pago));

    // CORRECCIÓN: Determinar el nuevo estado
    // Si el estado actual es 'CERRADA', lo mantenemos; si no, lo cambiamos a 'PAGADA'
    $nuevo_estado = ($factura['estado'] === 'CERRADA') ? 'CERRADA' : 'PAGADA';
    
    $sql_actualizar = "UPDATE tbl_fact 
                      SET estado = :nuevo_estado,
                          fecha_pago = :fecha_pago,
                          Ref_pago = :ref_pago,
                          usuario_contabilizacion = :user_cont_id,
                          fecha_contabilizacion = IFNULL(fecha_contabilizacion, NOW())
                      WHERE id = :id";
    
    $stmt_actualizar = $db->prepare($sql_actualizar);
    $stmt_actualizar->execute([
        'nuevo_estado' => $nuevo_estado,
        'fecha_pago'   => $fecha_pago_seleccionada,
        'ref_pago'     => $ref_pago_final,
        'user_cont_id' => $usuario_actual_id,
        'id'           => $factura_id
    ]);
    
    // 5. Registro en el Histórico de Operaciones para Auditoría
    $sql_historico = "INSERT INTO historico_operaciones 
                     (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora)
                     VALUES (:operacion, :descripcion, :u_id, :u_nombre, :ip, NOW())";
    
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Mensaje de log según el caso
    if ($nuevo_estado === 'CERRADA') {
        $desc_log = "REGISTRO DE PAGO: Factura {$factura['no_fact']} - PAGADA pero se MANTIENE estado CERRADA. " .
                    "Ref: {$ref_pago}. Fecha Pago: {$fecha_pago_seleccionada}. " .
                    "Procesado por: {$usuario_nombre}";
        $operacion_log = 'REGISTRO_PAGO_CERRADA';
    } else {
        $desc_log = "PAGO Y CONTABILIZACIÓN: Factura {$factura['no_fact']} marcada como PAGADA. " .
                    "Ref: {$ref_pago}. Fecha Pago: {$fecha_pago_seleccionada}. " .
                    "Contabilizado por: {$usuario_nombre}";
        $operacion_log = 'REGISTRO_PAGO';
    }

    $stmt_historico = $db->prepare($sql_historico);
    $stmt_historico->execute([
        'operacion'   => $operacion_log,
        'descripcion' => $desc_log,
        'u_id'        => $usuario_actual_id,
        'u_nombre'    => $usuario_nombre,
        'ip'          => $ip_cliente
    ]);
    
    $db->commit();

    // Mensaje de éxito según el caso
    if ($nuevo_estado === 'CERRADA') {
        $response['message'] = "Factura {$factura['no_fact']}: Pago registrado.";
    } else {
        $response['message'] = "Factura {$factura['no_fact']} actualizada a PAGADA correctamente.";
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en marcar_factura_pagada.php: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Retornar respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($response);