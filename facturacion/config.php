<?php
// Archivo de configuración - Sistema de Facturación SISFACT-PDL VISIONES
// Generado automáticamente el 17-09-2026 21:59:57

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', 'frl8110kaky');
define('DB_NAME', 'sisfact_imdl');
define('DB_PREFIX', ''); // Prefijo vacío por ahora

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Facturación SISFACT-PDL VISIONES');
define('APP_VERSION', '2.3.3');
define('TIMEZONE', 'America/Havana');
date_default_timezone_set(TIMEZONE);

// URL base de la aplicación
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . $host . $path . '/');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexión a la base de datos
function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die('Error de conexión: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// Función para escribir logs del sistema
function writeSystemLog($accion, $detalles = '', $usuario_id = null) {
    $conn = getDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare('INSERT INTO historico_operaciones (usuario_id, operacion, descripcion, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $usuario_id, $accion, $detalles, $ip);
    $stmt->execute();
    $stmt->close();
}

// Función para obtener configuración del sistema
function getSystemConfig() {
    $conn = getDBConnection();
    $result = $conn->query('SELECT * FROM configuracion_sistema WHERE id = 1 LIMIT 1');
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}
?>