<?php
/**
 * api/api_datos_locales.php
 * API para obtener datos de la base de datos local
 * Se ejecuta en la PC secundaria donde está la BD
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración de la base de datos local
$host = 'localhost';
$dbname = 'sisfact_imdl';
$username = 'root';
$password = 'frl8110kaky';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'clientes':
        obtenerClientes($pdo);
        break;
    case 'tipos_pago':
        obtenerTiposPago($pdo);
        break;
    case 'categorias':
        obtenerCategorias($pdo);
        break;
    case 'servicios':
        obtenerServicios($pdo);
        break;
    case 'todos':
        obtenerTodos($pdo);
        break;
    case 'ultima_factura':
        obtenerUltimaFactura($pdo);
        break;
	case 'fecha_operativa':
    obtenerFechaOperativa($pdo);
    break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Obtener fecha operativa del sistema
 */
function obtenerFechaOperativa($pdo) {
    try {
        $sql = "SELECT fecha_inicio_operaciones FROM configuracion_sistema LIMIT 1";
        $stmt = $pdo->query($sql);
        $fecha = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fecha && $fecha['fecha_inicio_operaciones']) {
            $fechaObj = new DateTime($fecha['fecha_inicio_operaciones']);
            echo json_encode(['success' => true, 'data' => $fechaObj->format('d/m/Y')]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fecha no configurada']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}



/**
 * Obtener clientes de clasif_clientes
 */
function obtenerClientes($pdo) {
    try {
        $sql = "SELECT * 
                FROM clasif_clientes 
                WHERE activo = 1 
                ORDER BY nombre";
        
        $stmt = $pdo->query($sql);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $clientes]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener tipos de pago de tipos_pago
 */
function obtenerTiposPago($pdo) {
    try {
        $sql = "SELECT id, codigo, descripcion FROM tipos_pago ORDER BY descripcion";
        $stmt = $pdo->query($sql);
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $tipos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener categorías de clasif_cat_de_serv
 */
function obtenerCategorias($pdo) {
    try {
        $sql = "SELECT id, codigo, descripcion, activo 
                FROM clasif_cat_de_serv 
                ORDER BY descripcion";
        
        $stmt = $pdo->query($sql);
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $categorias]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener servicios de clasif_serv
 */
function obtenerServicios($pdo) {
    try {
        $sql = "SELECT s.id, s.codigo, s.descripcion, s.categoria_id, s.costo, s.activo,
                       c.descripcion as categoria_nombre, c.codigo as categoria_codigo
                FROM clasif_serv s
                LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                WHERE s.activo = 1
                ORDER BY s.descripcion";
        
        $stmt = $pdo->query($sql);
        $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $servicios]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener todos los datos maestros de una vez
 */
function obtenerTodos($pdo) {
    try {
        $resultado = [];
        
        // Clientes
        $sql = "SELECT *  
                FROM clasif_clientes ORDER BY nombre";
        $resultado['clientes'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Tipos de pago
        $sql = "SELECT id, codigo, descripcion FROM tipos_pago ORDER BY descripcion";
        $resultado['tiposPago'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Categorías
        $sql = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv ORDER BY descripcion";
        $resultado['categorias'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Servicios
        $sql = "SELECT s.id, s.codigo, s.descripcion, s.categoria_id, s.costo, s.activo,
                       c.descripcion as categoria_nombre
                FROM clasif_serv s
                LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
                WHERE s.activo = 1
                ORDER BY s.descripcion";
        $resultado['servicios'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $resultado]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener última factura registrada
 */
function obtenerUltimaFactura($pdo) {
    try {
        $sql = "SELECT no_fact, fecha_emision, total_general 
                FROM tbl_fact 
                ORDER BY id DESC 
                LIMIT 1";
        
        $stmt = $pdo->query($sql);
        $ultima = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $ultima ?: null]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>