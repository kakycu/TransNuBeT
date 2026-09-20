<?php
// eliminar_factura_multiple.php

session_start();
require_once 'config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener IDs
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

if ($accion !== 'eliminar_multiple' || empty($ids) || !is_array($ids)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

// Obtener información del usuario actual
$db = Database::getConnection();
$sql_usuario = "SELECT id, nombre, rol_id FROM clasif_usuarios WHERE id = :id";
$stmt_usuario = $db->prepare($sql_usuario);
$stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit();
}

$esAdmin = ($usuario['rol_id'] == 1);
$esEditor = ($usuario['rol_id'] == 3);
$esSuper = ($usuario['rol_id'] == 4);
$rolId = $usuario['rol_id'];
$usuario_nombre = $usuario['nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_id = $usuario['id'];

// Limpiar y validar IDs
$ids_limpios = array_map('intval', $ids);
$ids_limpios = array_filter($ids_limpios, function($id) { return $id > 0; });

if (empty($ids_limpios)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'IDs de factura inválidos']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($ids_limpios), '?'));

try {
    $db->beginTransaction();
    
    // Obtener información COMPLETA de las facturas a eliminar
    $sql_select = "SELECT f.id, f.no_fact, f.total_general, f.estado, f.fecha_pago, f.Ref_pago, 
                          f.fecha_emision, f.fecha_contabilizacion,
                          c.nombre as cliente_nombre, c.id as cliente_id
                   FROM tbl_fact f
                   LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                   WHERE f.id IN ($placeholders)";
    $stmt_select = $db->prepare($sql_select);
    $stmt_select->execute($ids_limpios);
    $facturas = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
    
    $eliminadas = 0;
    $errores = 0;
    $total_eliminado = 0;
    $facturas_eliminadas_info = [];
    $facturas_no_eliminadas = [];
    
    // Estados que NUNCA se pueden eliminar
    $estadosProtegidos = ['CERRADA', 'PAGADA'];
    
    // Array para almacenar las facturas que SÍ se eliminarán (para el registro único)
    $facturas_eliminadas_para_log = [];
    
    foreach ($facturas as $factura) {
        $estado = $factura['estado'];
        $no_fact = $factura['no_fact'];
        $cliente_nombre = $factura['cliente_nombre'] ?? 'N/A';
        $total = floatval($factura['total_general']);
        
        // Verificar si es Cerrada con pago (CerradaPagada)
        $esCerradaConPago = (
            $estado == 'CERRADA' && 
            !empty($factura['fecha_pago']) && 
            $factura['fecha_pago'] != '0000-00-00' && 
            $factura['fecha_pago'] != '0000-00-00 00:00:00' &&
            !empty($factura['Ref_pago'])
        );
        
        // Verificar si la factura está en estado protegido
        $esProtegida = in_array($estado, $estadosProtegidos) || $esCerradaConPago;
        
        // Verificar si se puede eliminar esta factura
        $puedeEliminar = false;
        
        if ($esAdmin) {
            if (!$esProtegida) {
                $puedeEliminar = true;
            }
        } elseif (($esEditor || $esSuper || $rolId == 5) && ($estado == 'ANULADA')) {
            $puedeEliminar = true;
        }
        
        if (!$puedeEliminar) {
            $errores++;
            $motivo = $esProtegida ? "Estado protegido: " . ($esCerradaConPago ? "CerradaPagada" : $estado) : "Sin permisos suficientes";
            $facturas_no_eliminadas[] = [
                'no_fact' => $no_fact,
                'motivo' => $motivo
            ];
            continue;
        }
        
        // Eliminar detalles primero
        $sql_del_detalles = "DELETE FROM tbl_fact_detalle WHERE factura_id = ?";
        $stmt_del_detalles = $db->prepare($sql_del_detalles);
        $stmt_del_detalles->execute([$factura['id']]);
        
        // Eliminar factura
        $sql_del_fact = "DELETE FROM tbl_fact WHERE id = ?";
        $stmt_del_fact = $db->prepare($sql_del_fact);
        $stmt_del_fact->execute([$factura['id']]);
        
        if ($stmt_del_fact->rowCount() > 0) {
            $eliminadas++;
            $total_eliminado += $total;
            $facturas_eliminadas_info[] = [
                'no_fact' => $no_fact,
                'cliente' => $cliente_nombre,
                'total' => $total,
                'estado' => $estado,
                'fecha_emision' => $factura['fecha_emision']
            ];
            
            // Guardar para el registro único del histórico
            $facturas_eliminadas_para_log[] = [
                'no_fact' => $no_fact,
                'cliente' => $cliente_nombre,
                'total' => $total,
                'estado' => $estado,
                'fecha_emision' => $factura['fecha_emision'],
                'ref_pago' => $factura['Ref_pago']
            ];
        } else {
            $errores++;
            $facturas_no_eliminadas[] = [
                'no_fact' => $no_fact,
                'motivo' => 'Error al eliminar de la base de datos'
            ];
        }
    }
    
    // =====================================================
    // REGISTRO ÚNICO EN EL HISTÓRICO - TODAS LAS FACTURAS JUNTAS
    // =====================================================
    if ($eliminadas > 0) {
        // Construir la descripción con todas las facturas eliminadas
$lista_facturas = "";
foreach ($facturas_eliminadas_para_log as $fact) {
    $lista_facturas .= "{$fact['no_fact']} ({$fact['cliente']}: $" . number_format($fact['total'], 2) . " - {$fact['estado']}), ";
}
$lista_facturas = rtrim($lista_facturas, ', ');

$descripcion_log = "ELIMINACIÓN MÚLTIPLE: {$eliminadas} facturas eliminadas. Total: $" . number_format($total_eliminado, 2) . ". Facturas: " . $lista_facturas;
        
        if ($errores > 0) {
            $descripcion_log .= "\n\n⚠️ FACTURAS NO ELIMINADAS:\n";
            foreach ($facturas_no_eliminadas as $fact) {
                $descripcion_log .= "\n   ❌ {$fact['no_fact']} - Motivo: {$fact['motivo']}";
            }
        }
        
        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora) 
                   VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            'ELIMINAR_FACTURA_MULTIPLE',
            $descripcion_log,
            $usuario_id,
            $usuario_nombre,
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    $db->commit();
    
    // Construir mensaje
    $mensaje = "Se eliminaron $eliminadas factura(s) correctamente.";
    if ($errores > 0) {
        $mensaje .= " $errores factura(s) no se pudieron eliminar.";
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'eliminadas' => $eliminadas,
        'errores' => $errores,
        'total_eliminado' => $total_eliminado,
        'facturas' => $facturas_eliminadas_info,
        'facturas_no_eliminadas' => $facturas_no_eliminadas,
        'message' => $mensaje
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en eliminación múltiple: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en eliminación múltiple: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>