<?php
// verificar_contrasena.php
session_start();
require_once __DIR__ . '../config/database.php'; // Ajusta la ruta según tu estructura

// Verificar si hay una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sesion_bloqueada'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit();
}

// Obtener datos
$password = $_POST['password'] ?? '';
$usuario_id = $_POST['usuario_id'] ?? '';

// Validar datos
if (empty($password) || empty($usuario_id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

// Verificar contraseña en la base de datos
try {
    $db = Database::getConnection();
    
    // Obtener hash de contraseña del usuario
    $sql = "SELECT password FROM clasif_usuarios WHERE id = :id AND activo = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        exit();
    }
    
    // Verificar contraseña - ajusta según tu método de hashing
    // Si usas password_hash() al crear usuarios:
    if (password_verify($password, $usuario['password'])) {
        // Contraseña correcta - limpiar flag de sesión bloqueada
        unset($_SESSION['sesion_bloqueada']);
        unset($_SESSION['sesion_bloqueada_tiempo']);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>