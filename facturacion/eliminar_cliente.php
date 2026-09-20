<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$id = $_GET['id'] ?? 0;

try {
    $db = Database::getConnection();

 // Obtener usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
	$esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);
    
	if($esSoloLectura) { //Visualizador o Programador no peuden eliminar clientes
		$_SESSION['sweet_alert'] = [
					'type' => 'warning',
					'title' => 'No se puede eliminar',
					'text' => "No Posee los permisos necesarios para realizar esta Operación.",
					'confirmButtonText' => 'Entendido'
				];
				header('Location: clientes.php');
				exit();
			}

    // Obtener información del cliente antes de eliminar
    $sql = "SELECT nombre, codigo FROM clasif_clientes WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        $_SESSION['sweet_alert'] = [
            'type' => 'error',
            'title' => 'Cliente no encontrado',
            'text' => 'El cliente especificado no existe en el sistema',
            'confirmButtonText' => 'Entendido'
        ];
        header('Location: clientes.php');
        exit();
    }
    
    // Verificar si tiene facturas asociadas
    $sql_check = "SELECT COUNT(*) as total FROM tbl_fact WHERE cliente_id = ?";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([$id]);
    $facturas = $stmt_check->fetch();
    $total_facturas = $facturas['total'];
    
    if ($total_facturas > 0) {
        // Cliente tiene facturas - NO eliminar, preguntar por inactivación
        $_SESSION['sweet_alert'] = [
            'type' => 'warning',
            'title' => 'No se puede eliminar',
            'text' => "El cliente: <strong>{$cliente['nombre']}</strong> tiene {$total_facturas} factura(s) registrada(s) en el sistema.",
            'confirmButtonText' => '<i class="fas fa-check me-1"></i>Entendido',
            'showDenyButton' => true,
            'denyButtonText' => '<i class="fas fa-user-slash me-1"></i> Inactivar',
            'id_cliente' => $id,
            'has_invoices' => true
        ];
        header('Location: clientes.php');
        exit();
    }
    
    // Si no tiene facturas, proceder con eliminación
    $sql_delete = "DELETE FROM clasif_clientes WHERE id = ?";
    $stmt_delete = $db->prepare($sql_delete);
    $stmt_delete->execute([$id]);
    
    // Registrar actividad
    $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
               VALUES (?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        'ELIMINAR_CLIENTE',
        'Cliente [' . $cliente['codigo'] . '] ' . $cliente['nombre'] . ' eliminado',
        $_SESSION['usuario_id'],
        $_SESSION['usuario_nombre'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['sweet_alert'] = [
        'type' => 'success',
        'title' => '¡Operación exitosa!',
        'text' => "El cliente <strong>{$cliente['nombre']}</strong>, Código: <strong>{$cliente['codigo']}, ha sido eliminado correctamente.",
        'confirmButtonText' => 'Entendido'
    ];
    header('Location: clientes.php');
    exit();
    
} catch (Exception $e) {
    error_log("Error al eliminar cliente: " . $e->getMessage());
    $_SESSION['sweet_alert'] = [
        'type' => 'error',
        'title' => 'Error del sistema',
        'text' => 'Ocurrió un error al procesar la solicitud.',
        'confirmButtonText' => 'Entendido'
    ];
    header('Location: clientes.php');
    exit();
}
?>