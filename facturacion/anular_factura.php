<?php
// anular_factura.php - VERSIÓN MEJORADA PARA ANULAR, REACTIVAR Y DESCONTABILIZAR
session_start();

// IMPORTANTE: Establecer el header JSON primero
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'No autorizado: Sesión no válida'
    ]);
    exit();
}

// Incluir la conexión a la base de datos
require_once 'config/database.php';

try {
    $db = Database::getConnection();
    
    // Obtener usuario actual para verificar si es administrador o editor
    $sql_usuario = "SELECT rol_id, nombre, apellidos FROM clasif_usuarios WHERE id = ?";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode([
            'success' => false, 
            'message' => 'Usuario no encontrado'
        ]);
        exit();
    }
    
    // Verificar permisos - Solo Admin (id=1) y Editor (id=3) pueden realizar estas acciones
    $tiene_permiso = false;

    // Si es administrador, Super (rol_id = 1,4)
    if ($usuario['rol_id'] == 1 || $usuario['rol_id'] == 4) {
        $tiene_permiso = true;
    }

    if (!$tiene_permiso) {
        echo json_encode([
            'success' => false, 
            'message' => 'No tienes permisos para realizar esta acción.<br><div style="color:red;"><strong>Solo Administradores y Supervisores</strong> pueden realizar esta Acción</div>'
        ]);
        exit();
    }
    
    // Verificar que se envió el ID de factura
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID de factura no especificado'
        ]);
        exit();
    }
    
    $factura_id = intval($_POST['id']);
    $no_factura = $_POST['no_factura'] ?? '';
    $accion = $_POST['accion'] ?? 'anular'; // 'anular', 'reactivar' o 'descontabilizar'
    
    // Validar acción
    if (!in_array($accion, ['anular', 'reactivar', 'descontabilizar'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Acción no válida'
        ]);
        exit();
    }

    // 1. Verificar que la factura existe
    $sql_verificar = "SELECT estado, no_fact, cliente_id, fecha_contabilizacion, usuario_contabilizacion FROM tbl_fact WHERE id = ?";
    $stmt_verificar = $db->prepare($sql_verificar);
    $stmt_verificar->execute([$factura_id]);
    $factura = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode([
            'success' => false, 
            'message' => 'Factura no encontrada'
        ]);
        exit();
    }
    
    // 2. Obtener información del cliente
    $sql_cliente = "SELECT nombre FROM clasif_clientes WHERE id = ?";
    $stmt_cliente = $db->prepare($sql_cliente);
    $stmt_cliente->execute([$factura['cliente_id']]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    $cliente_nombre = $cliente ? $cliente['nombre'] : 'Cliente desconocido';
    
    // 3. Determinar el nuevo estado según la acción
    if ($accion === 'anular') {
        // Verificar que no esté ya anulada
        if ($factura['estado'] == 'ANULADA') {
            echo json_encode([
                'success' => false, 
                'message' => 'La factura ' . $factura['no_fact'] . ' ya está anulada'
            ]);
            exit();
        }
        
        // Verificar que no esté contabilizada
        if ($factura['estado'] == 'CONTABILIZADA') {
            // Si está contabilizada, sugerir descontabilizar primero
            echo json_encode([
                'success' => false, 
                'message' => 'La factura está contabilizada. Primero debe descontabilizarla.'
            ]);
            exit();
        }
        
        $nuevo_estado = 'ANULADA';
        $texto_accion = 'anulada';
        $operacion_historico = 'ANULACION_FACTURA';
        
    } else if ($accion === 'reactivar') {
        // Verificar que esté anulada para poder reactivarla
        if ($factura['estado'] != 'ANULADA') {
            echo json_encode([
                'success' => false, 
                'message' => 'Solo se pueden reactivar facturas anuladas. Esta factura está: ' . $factura['estado']
            ]);
            exit();
        }
        $nuevo_estado = 'PENDIENTE';
        $texto_accion = 'reactivada';
        $operacion_historico = 'REACTIVACION_FACTURA';
        
    } else if ($accion === 'descontabilizar') {
        // Verificar que esté contabilizada para poder descontabilizarla
        if ($factura['estado'] != 'CONTABILIZADA') {
            echo json_encode([
                'success' => false, 
                'message' => 'Solo se pueden descontabilizar facturas contabilizadas. Esta factura está: ' . $factura['estado']
            ]);
            exit();
        }
        $nuevo_estado = 'PENDIENTE';
        $texto_accion = 'descontabilizada';
        $operacion_historico = 'DESCONTABILIZACION_FACTURA';
    }
    
    // 4. Actualizar el estado de la factura
    $sql_actualizar = "UPDATE tbl_fact 
                       SET estado = ?, 
                           fecha_contabilizacion = NULL,
                           usuario_contabilizacion = NULL
                       WHERE id = ?";
    
    $stmt_actualizar = $db->prepare($sql_actualizar);
    $resultado = $stmt_actualizar->execute([
        $nuevo_estado, 
        $factura_id
    ]);
    
    if ($resultado) {
        // 5. Registrar en el histórico
        try {
            $sql_historico = "INSERT INTO historico_operaciones 
                             (operacion, descripcion, fecha_hora, usuario_id, usuario_nombre, ip_address) 
                             VALUES (?, ?, NOW(), ?, ?, ?)";
            
            $descripcion = "Factura " . $factura['no_fact'] . " " . $texto_accion . " - Cliente: " . $cliente_nombre;
            
            $usuario_nombre = ($usuario['nombre'] . ' ' . $usuario['apellidos']) ?? 'Usuario desconocido';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'IP desconocida';
            
            $stmt_historico = $db->prepare($sql_historico);
            $stmt_historico->execute([
                $operacion_historico,
                $descripcion,
                $_SESSION['usuario_id'],
                $usuario_nombre,
                $ip_address
            ]);
        } catch (Exception $e) {
            // Si falla el histórico, continuamos igual pero lo registramos
            error_log("Error al registrar en histórico: " . $e->getMessage());
        }
        
        // 6. Enviar respuesta exitosa
        $response = [
            'success' => true,
            'message' => "Factura " . $factura['no_fact'] . " " . $texto_accion . " correctamente",
            'estado' => $nuevo_estado
        ];
        
        // Si se descontabilizó, agregar información adicional
        if ($accion === 'descontabilizar') {
            $response['descontabilizada'] = true;
            $response['nota'] = 'La factura ahora puede ser anulada si es necesario';
        }
        
        echo json_encode($response);
        
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudo actualizar la factura en la base de datos'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error al procesar factura: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>