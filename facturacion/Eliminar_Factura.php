<?php

// eliminar_factura.php

session_start();
require_once 'config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar que se recibió un ID válido
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    $_SESSION['error_message'] = "ID de factura inválido";
    header('Location: facturas.php');
    exit();
}

try {
    $db = Database::getConnection();
    
    // Obtener información de la factura ANTES de eliminar, incluyendo el cliente
    $sql = "SELECT f.no_fact, f.total_general, c.nombre as cliente_nombre 
            FROM tbl_fact f
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            WHERE f.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        $_SESSION['error_message'] = "La factura no existe";
        header('Location: facturas.php');
        exit();
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Eliminar detalles primero (por la restricción de clave foránea)
    $sql_delete_detalles = "DELETE FROM tbl_fact_detalle WHERE factura_id = ?";
    $stmt_delete_detalles = $db->prepare($sql_delete_detalles);
    $stmt_delete_detalles->execute([$id]);
    
    // Eliminar factura
    $sql_delete = "DELETE FROM tbl_fact WHERE id = ?";
    $stmt_delete = $db->prepare($sql_delete);
    $stmt_delete->execute([$id]);
    
    // Verificar que se eliminó realmente
    if ($stmt_delete->rowCount() === 0) {
        throw new Exception("No se pudo eliminar la factura");
    }
    
    // Construir descripción con cliente
    $cliente_texto = !empty($factura['cliente_nombre']) ? " Cliente: " . $factura['cliente_nombre'] : "";
    $descripcion_log = "Factura " . $factura['no_fact'] . " eliminada. Total: $" . number_format($factura['total_general'], 2) . $cliente_texto;
    
    // Registrar actividad
    $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address, fecha_hora) 
               VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        'ELIMINAR_FACTURA',
        $descripcion_log,
        $_SESSION['usuario_id'],
        $_SESSION['usuario_nombre'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $db->commit();
    
    // Configurar mensaje de éxito con cliente
    $cliente_html = !empty($factura['cliente_nombre']) ? "<br>Cliente: <strong>" . htmlspecialchars($factura['cliente_nombre']) . "</strong>" : "";
    $_SESSION['sweet_alert'] = [
        'icon' => 'success',
        'title' => '¡Eliminación Exitosa!',
        'html' => "La Factura No.: <strong>{$factura['no_fact']}</strong><br>Importe: <strong>$" . number_format($factura['total_general'], 2) . "</strong>{$cliente_html}<br>ha sido eliminada correctamente.",
        'confirmButtonText' => '<i class="fas fa-check me-2"></i>Entendido',
        'showConfirmButton' => true,
        'allowOutsideClick' => false,
        'allowEscapeKey' => false,
        'position' => 'center',
        'width' => '500px'
    ];
    
    header('Location: facturas.php');
    exit();
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error al eliminar factura: " . $e->getMessage());
    
    $_SESSION['sweet_alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => 'Error al eliminar la factura: ' . $e->getMessage(),
        'confirmButtonText' => 'Entendido'
    ];
    
    header('Location: facturas.php');
    exit();
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error al eliminar factura: " . $e->getMessage());
    
    $_SESSION['sweet_alert'] = [
        'icon' => 'error',
        'title' => 'Error',
        'text' => $e->getMessage(),
        'confirmButtonText' => 'Entendido'
    ];
    
    header('Location: facturas.php');
    exit();
}
?>