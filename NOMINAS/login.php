<?php
session_start();

// ============================================================
// 1. VERIFICAR ESTADO DE MYSQL (capturando excepción)
// ============================================================
$db_ok = false;
$db_error = null;
$host = 'localhost';
$user = 'root';
$password = '';  // Cambia por tu contraseña de MySQL
$database = 'TransNuBeT_nomina';

try {
    $test_conn = new mysqli($host, $user, $password, $database);
    $db_ok = true;
    $test_conn->close();
} catch (mysqli_sql_exception $e) {
    $db_ok = false;
    $db_error = $e->getMessage();
}

// ============================================================
// 2. SI LA BD ESTÁ CONECTADA, CARGAR CONFIGURACIÓN Y PDO
// ============================================================
$COMPANY_NAME = 'PDL TransNuBeT';
$SITE_VERSION = 'v2.0.1';
$SITE_NAME = $COMPANY_NAME . ' - Nóminas';
$restabpw = 0; // valor por defecto
$pdo = null;

if ($db_ok) {
    // Incluir el archivo que define $pdo (asumiendo que está correctamente configurado)
    require_once 'config/database.php';
    
    // Cargar configuración desde la base de datos
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'configuracion_general'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'site_name')");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (isset($configs['nombre_empresa']) && !empty($configs['nombre_empresa'])) {
                $COMPANY_NAME = $configs['nombre_empresa'];
                $SITE_NAME = $COMPANY_NAME . ' - Nóminas';
            }
        }
        
        // Obtener configuración de WhatsApp y restablecimiento
        $stmt = $pdo->prepare("SELECT restabpw FROM configuracion_sistema LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $restabpw = $row['restabpw'];
        }
    } catch (PDOException $e) {
        error_log("Error al cargar configuración: " . $e->getMessage());
    }
}

// Detectar bypass de programador (solo funciona si la BD está OK, pero se muestra igual)
$es_programador = isset($_GET['access_bypass']) && $_GET['access_bypass'] === 'true';

// Verificar si ya está logueado (solo si hay BD)
if ($db_ok && isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$username = '';

// ============================================================
// 3. MANEJADORES AJAX (API INTERNA) - con verificación de BD
// ============================================================
if ((isset($_GET['action']) || isset($_GET['ajax'])) && $db_ok) {
    if (ob_get_length()) ob_clean();
    error_reporting(0);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $action = $_GET['action'] ?? $_GET['ajax'];
    
    try {
        // A) Verificar estado de mantenimiento
        if ($action === 'check_status') {
            // Verificar conexión a MySQL en este momento
            $dbOkNow = false;
            $testConn = @new mysqli($host, $user, $password, $database);
            if (!$testConn->connect_error) {
                $dbOkNow = true;
                $testConn->close();
            }
            $response = ['db_ok' => $dbOkNow, 'maintenance' => false];
            if ($dbOkNow) {
                // Si la BD está OK, también consultar mantenimiento
                try {
                    $stmt = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] != 0) {
                        $response['maintenance'] = true;
                    }
                } catch (Exception $e) { }
            }
            echo json_encode($response);
            exit;
        }
        
        // B) Obtener Imagen y Rol
        if ($action === 'get_imagen') {
            $dato_entrada = trim($_GET['usuario'] ?? '');
            if (empty($dato_entrada)) {
                echo json_encode(['success' => false, 'error' => 'Usuario vacío']);
                exit;
            }
            
            $defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');
            
            $sql = "SELECT foto, rol_id FROM clasif_usuarios 
                    WHERE usuario = ? OR email = ? OR no_ci = ? 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $roles = [1 => 'Administrador', 2 => 'Visualizador', 3 => 'Facturador', 4 => 'Supervisor', 5 => 'Programador'];
                $rol_nombre = isset($result['rol_id']) ? ($roles[$result['rol_id']] ?? 'Usuario') : 'Usuario';
                $imagen = !empty($result['foto']) ? $result['foto'] : $defaultSvg;
                echo json_encode(['success' => true, 'imagen' => $imagen, 'rol' => $rol_nombre]);
            } else {
                echo json_encode(['success' => false, 'imagen' => $defaultSvg, 'rol' => null]);
            }
            exit;
        }
        
        // C) Recuperación de contraseña
        if ($action === 'forgot_password') {
            $usuario = trim($_POST['usuario'] ?? '');
            $no_ci = trim($_POST['no_ci'] ?? '');
            $nombre_completo = trim($_POST['nombre_completo'] ?? '');
            $step = trim($_POST['step'] ?? 'verify');
            
            if (empty($usuario)) { echo json_encode(['success' => false, 'message' => 'Ingrese su usuario']); exit; }
            if (empty($nombre_completo)) { echo json_encode(['success' => false, 'message' => 'Ingrese su nombre completo']); exit; }
            if (empty($no_ci)) { echo json_encode(['success' => false, 'message' => 'Ingrese su carné de identidad']); exit; }
            if (!preg_match('/^\d{11}$/', $no_ci)) { echo json_encode(['success' => false, 'message' => 'El carné debe tener 11 dígitos']); exit; }
            
            $sql = "SELECT id, nombre, apellidos, usuario, no_ci FROM clasif_usuarios WHERE usuario = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) { echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit; }
            
            $no_ci_normalized = preg_replace('/[^0-9]/', '', $no_ci);
            $user_ci_normalized = isset($user['no_ci']) ? preg_replace('/[^0-9]/', '', $user['no_ci']) : '';
            
            if (empty($user_ci_normalized) || $user_ci_normalized !== $no_ci_normalized) {
                echo json_encode(['success' => false, 'message' => 'El número de carné no coincide']); exit;
            }
            
            $nombre_real = $user['nombre'] . ' ' . $user['apellidos'];
            if (strtolower(trim($nombre_completo)) !== strtolower(trim($nombre_real))) {
                echo json_encode(['success' => false, 'message' => 'El nombre completo no coincide']); exit;
            }
            
            if ($step === 'verify') {
                echo json_encode([
                    'success' => true,
                    'action' => 'confirm_required',
                    'usuario' => $user['usuario'],
                    'nombre' => $nombre_real,
                    'no_ci' => $user['no_ci']
                ]);
                exit;
            }
            
            if ($step === 'reset') {
                $nueva_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
                $hashed_password = password_hash($nueva_password, PASSWORD_DEFAULT);
                $sqlUpdate = "UPDATE clasif_usuarios SET password = ? WHERE id = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$hashed_password, $user['id']]);
                
                echo json_encode([
                    'success' => true,
                    'action' => 'reset_success',
                    'nueva_password' => $nueva_password,
                    'usuario' => $user['usuario'],
                    'nombre' => $nombre_real
                ]);
                exit;
            }
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error servidor: ' . $e->getMessage()]);
        exit;
    }
} elseif ((isset($_GET['action']) || isset($_GET['ajax'])) && !$db_ok) {
    // Si la BD está desconectada y se hace una petición AJAX, responder con error
    header('Content-Type: application/json');
    echo json_encode(['db_ok' => false, 'error' => $db_error]);
    exit;
}

// ============================================================
// 4. PROCESAR LOGIN (POST) - SOLO SI LA BD ESTÁ CONECTADA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {
    $dato_entrada = trim($_POST['username'] ?? '');
    $password_input = trim($_POST['password'] ?? '');
    
    if (empty($dato_entrada) || empty($password_input)) {
        $error = 'complete_campos';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.codigo as rol_codigo, r.descripcion as rol_descripcion 
                FROM clasif_usuarios u 
                LEFT JOIN clasif_rol r ON u.rol_id = r.id 
                WHERE (u.usuario = ? OR u.email = ? OR u.no_ci = ?) AND u.activo = 1
            ");
            $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($password_input, $user_data['password'])) {
                $enMantenimiento = false;
                try {
                    $stmtMaint = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
                    $stmtMaint->execute();
                    $maintResult = $stmtMaint->fetch(PDO::FETCH_ASSOC);
                    $enMantenimiento = ($maintResult && $maintResult['modo_mantenimiento'] >= 1);
                } catch (Exception $e) {}
                
                if ($enMantenimiento && $user_data['rol_id'] != 5) {
                    $error = "El sistema se encuentra en MANTENIMIENTO. Su rol no tiene permisos para acceder en este momento.";
                } else {
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['username'] = $user_data['usuario'];
                    $_SESSION['user_nombre'] = $user_data['nombre'] . ' ' . $user_data['apellidos'];
                    $_SESSION['user_ci'] = $user_data['no_ci'];
                    $_SESSION['user_email'] = $user_data['email'];
                    $_SESSION['rol_id'] = $user_data['rol_id'];
                    $_SESSION['rol_codigo'] = $user_data['rol_codigo'];
                    $_SESSION['rol_descripcion'] = $user_data['rol_descripcion'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    try {
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $log_stmt = $pdo->prepare("
                            INSERT INTO sys_log_accesos (usuario_id, ip_address, user_agent, fecha_acceso) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $log_stmt->execute([$user_data['id'], $ip_address, $user_agent]);
                    } catch (PDOException $e) {}
                    
                    header('Location: dashboard.php');
                    exit();
                }
            } else {
                $error = 'credenciales_invalidas';
            }
        } catch (PDOException $e) {
            $error = 'error_sistema';
        }
    }
}

// ============================================================
// 5. FUNCIONES AUXILIARES (solo para la vista)
// ============================================================
function obtenerConfiguracionWhatsApp($pdo) {
    if (!$pdo) return ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    try {
        $stmt = $pdo->prepare("SELECT whatsapp_numero, whatsapp_ON, restabpw FROM configuracion_sistema LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return $config ? [
            'whatsapp_numero' => $config['whatsapp_numero'] ?? null,
            'whatsapp_activo' => ($config['whatsapp_ON'] == 1),
            'restabpw' => $config['restabpw'] ?? 0
        ] : ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    } catch (Exception $e) {
        return ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($SITE_NAME); ?> · Acceso al Sistema</title>
	</title><link rel="icon" type="image/png" href="../images/favicons/nominas.ico">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <script src="js/sweetalert211.js"></script>

<style>
/* ========== RESET Y ESTILOS BASE ========== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 20px;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('../images/logoTN.png');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
    pointer-events: none;
    z-index: 0;
}

body::after {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(59, 130, 246, 0.08) 30px, rgba(59, 130, 246, 0.08) 60px),
        repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(139, 92, 246, 0.06) 35px, rgba(139, 92, 246, 0.06) 70px);
    pointer-events: none;
    animation: waveMove 8s ease-in-out infinite;
    z-index: 0;
}

@keyframes waveMove {
    0%, 100% { background-position: 0 0, 0 0; }
    25% { background-position: 20px 20px, -20px -20px; }
    50% { background-position: 40px 40px, -40px -40px; }
    75% { background-position: 20px 20px, -20px -20px; }
}

/* ========== CONTENEDOR PRINCIPAL ========== */
.login-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 680px;  /* ← más ancho */
    animation: fadeInUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
}

.login-card {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(10px);
    border-radius: 32px;
    border: 1px solid rgba(59, 130, 246, 0.25);
    padding: 40px 48px;  /* ← más espacio lateral */
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}


/* ========== LOGO Y TÍTULOS ========== */
.logo-area {
    text-align: center;
    margin-bottom: 32px;
}

.logo-icon2 {
    width: 100px;
    height: 100px;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
}

.logo-area h1 {
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(145deg, #ffffff, #94a3f0);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    letter-spacing: -0.5px;
}

.logo-area .subtitle {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-top: 8px;
}

/* ========== INPUTS Y GRUPOS ========== */
.input-group {
    margin-bottom: 24px;
}

.input-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: #cbd5e1;
    margin-bottom: 8px;
}

/* Contenedor general de los inputs con iconos */
.input-with-icon {
    position: relative;
    overflow: visible;  /* Para que el avatar pueda sobresalir */
}

/* Icono izquierdo (común para ambos campos) */
.input-with-icon i:first-child {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #5a6a8a;
    font-size: 1rem;
    z-index: 1;
    transition: color 0.2s ease;
}

/* Estilo base de los inputs */
.input-with-icon input {
    width: 100%;
    padding: 14px 70px 14px 45px;  /* Padding derecho ampliado para el avatar grande */
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 16px;
    font-size: 0.95rem;
    color: #f1f5f9;
    transition: all 0.2s ease;
}

.input-with-icon input:focus {
    outline: none;
    border-color: #3b82f6;
    background: rgba(0, 0, 0, 0.6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input-with-icon input::placeholder {
    color: #5a6a8a;
}

/* ========== AVATAR DE USUARIO (GRANDE Y SOBRESALIENTE) ========== */
.user-avatar-container {
    position: absolute;
    right: -8px;           /* Sobresale por la derecha */
    top: 50%;
    transform: translateY(-50%);
    width: 56px;
    height: 56px;
    z-index: 2;
    pointer-events: none;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.user-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 3px solid #3b82f6;
    object-fit: cover;
    background: rgba(0, 0, 0, 0.6);
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3), 0 8px 20px rgba(0, 0, 0, 0.3);
}

.user-avatar.visible {
    opacity: 1;
    transform: scale(1.02);
}

.user-avatar-container:hover .user-avatar {
    transform: scale(1.05);
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.5), 0 8px 20px rgba(0, 0, 0, 0.4);
}

/* ========== BOTÓN TOGGLE PASSWORD (OJITO) ========== */
/* Aseguramos que el contenedor del input de contraseña tenga position relative y overflow visible */
.input-group:has(#password) > div {
    position: relative !important;
}

/* Botón toggle con posicionamiento absoluto (centrado verticalmente) y animaciones */
.toggle-password-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.15);
    border: none;
    cursor: pointer;
    padding: 0;
    margin: 0;
    transition: all 0.2s ease;
    z-index: 10;
}

.toggle-password-btn i {
    font-size: 16px;
    display: block;
    transition: all 0.2s ease;
    color: #60a5fa;
}

.toggle-password-btn:hover {
    background: rgba(59, 130, 246, 0.3);
    transform: translateY(-50%) scale(1.05);
}

.toggle-password-btn:hover i {
    color: #ffffff;
}

.toggle-password-btn:active {
    transform: translateY(-50%) scale(0.95);
}

/* ========== VALIDACIÓN DE CI (BORDES VERDE/ROJO) ========== */
.input-with-icon input.is-valid {
    border-color: #22c55e !important;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.2);
}

.input-with-icon input.is-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
}

.ci-feedback {
    display: none;
    width: 100%;
    text-align: left;
    margin-top: 5px;
    font-size: 0.75rem;
    padding-left: 10px;
}

/* ========== CONTADOR DE CARACTERES ========== */
.char-counter {
    position: absolute;
    bottom: -20px;
    right: 5px;
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
    z-index: 10;
    pointer-events: none;
    transition: all 0.3s ease;
    opacity: 0;
}

.char-counter.show {
    opacity: 1;
}

/* ========== BOTONES Y ENLACES ========== */
.button-group {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.btn-login {
    flex: 2;
    background: linear-gradient(95deg, #2563eb, #1d4ed8);
    border: none;
    padding: 14px 24px;
    border-radius: 60px;
    font-weight: 700;
    font-size: 1rem;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
}

.btn-login:hover {
    transform: translateY(-2px);
    filter: brightness(1.08);
    box-shadow: 0 12px 28px rgba(37, 99, 235, 0.5);
}

.btn-login:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-back-home {
    flex: 1;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid #475569;
    padding: 14px 20px;
    border-radius: 60px;
    font-weight: 500;
    font-size: 0.9rem;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-back-home:hover {
    background: #334155;
    border-color: #ef4444;
    color: #fca5a5;
}

.forgot-link {
    display: block;
    text-align: right;
    margin-bottom: 15px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.forgot-link:hover {
    color: #60a5fa;
    text-decoration: underline;
}

.forgot-link.disabled-link {
    pointer-events: none;
    opacity: 0.5;
}

.btn-login.btn-disabled-maint {
    background: #555 !important;
    color: #aaa !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

/* ========== PIE DE PÁGINA Y MENSAJES ========== */
.login-footer {
    margin-top: 28px;
    text-align: center;
    font-size: 0.7rem;
    color: #FFFFFF;
}

.info-message {
    text-align: center;
    margin-top: 20px;
    padding: 12px;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 12px;
    border-left: 3px solid #3b82f6;
}

.info-message i {
    color: #3b82f6;
    margin-right: 8px;
}

.info-message span {
    font-size: 0.75rem;
    color: #94a3b8;
}

.info-message strong {
    color: #60a5fa;
}

.info-message a {
    color: #60a5fa;
    text-decoration: none;
    transition: all 0.2s ease;
}

.info-message a:hover {
    text-decoration: underline;
    color: #3b82f6;
}

/* ========== BADGES (PROGRAMADOR) ========== */
.programmer-badge {
    position: fixed;
    bottom: 10px;
    left: 10px;
    background: #dc3545;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 999999;
    box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
}

.exit-badge {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #000;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    z-index: 999998;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
    cursor: pointer;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

/* ========== MODALES (RECUPERACIÓN, MANTENIMIENTO) ========== */
.modal-form-field {
    margin-bottom: 15px;
    text-align: left;
}

.modal-form-field label {
    display: block;
    color: #cbd5e1;
    font-size: 0.8rem;
    margin-bottom: 5px;
    font-weight: 500;
}

.modal-form-field input, .modal-form-field select {
    width: 100%;
    padding: 10px 12px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    color: #f1f5f9;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.modal-form-field input:focus, .modal-form-field select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.modal-form-field input::placeholder {
    color: #5a6a8a;
}

.modal-form-field select option {
    background: #0f172a;
    color: #f1f5f9;
}

.row-2-columns {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.row-2-columns .modal-form-field {
    flex: 1;
    margin-bottom: 0;
}

/* Modal mantenimiento estilo Win11 */
.win11-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.win11-modal-overlay.active {
    display: flex;
    opacity: 1;
}

.win11-modal {
    background: rgba(32, 32, 32, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    transform: scale(0.95);
    transition: transform 0.3s cubic-bezier(0.1, 0.9, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.win11-modal-overlay.active .win11-modal {
    transform: scale(1);
}

.win11-modal-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(255, 255, 255, 0.02);
}

.modal-title-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
}

.win11-modal-body {
    padding: 25px 25px;
    color: #ddd;
    font-size: 0.95rem;
    line-height: 1.6;
}

.win11-modal-footer {
    padding: 15px 25px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-btn {
    padding: 8px 20px;
    border-radius: 4px;
    border: 1px solid transparent;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-btn-primary {
    background: #0078d4;
    color: #fff;
    border: 1px solid #0078d4;
}

.modal-btn-primary:hover {
    background: #006cc1;
}

.modal-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.maintenance-icon-pulse {
    animation: pulseMaint 2s infinite;
    color: #ffc107;
}

@keyframes pulseMaint {
    0% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
    50% { transform: scale(1.1); text-shadow: 0 0 20px rgba(255, 193, 7, 1); }
    100% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 480px) {
    .login-card {
        padding: 30px 24px;
    }
    .logo-area h1 {
        font-size: 1.3rem;
    }
    .button-group {
        flex-direction: column;
        gap: 10px;
    }
    .btn-login, .btn-back-home {
        width: 100%;
        flex: auto;
    }
    /* Ajuste del avatar en móviles (un poco más pequeño) */
    .user-avatar-container {
        width: 48px;
        height: 48px;
        right: -4px;
    }
    .input-with-icon input {
        padding-right: 60px;
    }
}
/* Estilos para el icono de la llave (mismo que el de usuario) */
.input-group:has(#password) .input-with-icon i:first-child,
.input-group:has(#password) > div > i:first-child {
    color: #5a6a8a;
    transition: color 0.2s ease;
    font-size: 1rem;
}

/* Botón toggle - animaciones */
.toggle-password-btn {
    transition: all 0.2s ease;
}

.toggle-password-btn i {
    transition: all 0.2s ease;
    font-size: 16px;
    color: #60a5fa;
}

.toggle-password-btn:hover {
    background: rgba(59, 130, 246, 0.3);
    transform: translateY(-50%) scale(1.05);
}

.toggle-password-btn:hover i {
    color: #ffffff;
}

.toggle-password-btn:active {
    transform: translateY(-50%) scale(0.95);
}
/* ========== FILA DE DOS COLUMNAS ========== */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 10px;
}

/* En móviles, vuelven a una columna */
@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}

/* Pequeño ajuste para el avatar en móviles (opcional) */
@media (max-width: 480px) {
    .user-avatar-container {
        width: 48px;
        height: 48px;
        right: -4px;
    }
    .input-with-icon input {
        padding-right: 60px;
    }
}
/* Enlaces legales en el footer */
.login-footer-links {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.login-footer-links a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.7rem;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.login-footer-links a:hover {
    color: #60a5fa;
    text-decoration: underline;
}
        /* Estilo para el logo de copyright */
        .copyright-logo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            vertical-align: middle;
        }

        .copyright-logo img {
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }
        .unicorn-icon {
            width: 30px;
            height: auto;
            filter: brightness(0) invert(1);
        }
</style>

</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon2">
                <img src="../images/logotn.png" alt="TransNuBeT Logo" 
                     style="width: 100%; height: 100%; object-fit: contain;"
                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon\'></i>';">
            </div>
            <h1><?php echo htmlspecialchars($COMPANY_NAME); ?> · <?php echo $SITE_VERSION; ?></h1>
            <div class="subtitle">Sistema de Gestión de Nóminas y Trabajadores</div>
            <h4 style="margin: 10px 0 5px; font-size: 0.9rem; color: #60a5fa;">TECLEE SUS CREDENCIALES DE ACCESO</h4>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="form-row">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Usuario / Email / CI</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope" id="dynamicIcon"></i>
                        <input type="text" name="username" id="username" placeholder="Ingrese su credencial" 
                               value="<?php echo htmlspecialchars($username); ?>" autocomplete="off" autofocus maxlength="100" <?php if (!$db_ok) echo 'disabled'; ?>>
                        <div class="user-avatar-container">
                            <img id="userAvatar" class="user-avatar" src="" alt="Foto de usuario">
                        </div>
                        <div class="char-counter" id="charCounter">0/100</div>
                    </div>
                    <div id="ciFeedback" class="ci-feedback"></div>
                </div>

                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Contraseña</label>
                    <div style="position: relative;">
                        <i class="fas fa-key" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); z-index: 1;"></i>
                        <input type="password" name="password" id="password" placeholder="Ingrese su contraseña" autocomplete="off"
                               style="width: 100%; padding: 14px 50px 14px 45px; background: rgba(0,0,0,0.4); border: 1px solid rgba(59,130,246,0.2); border-radius: 16px; color: #f1f5f9; font-size: 0.95rem;" <?php if (!$db_ok) echo 'disabled'; ?>>
                        <button type="button" class="toggle-password-btn" id="togglePassword" 
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59,130,246,0.15); border: none; cursor: pointer; padding: 0; margin: 0;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <a href="#" class="forgot-link" id="forgotPasswordLink" <?php if (!$db_ok) echo 'style="pointer-events: none; opacity: 0.5;"'; ?>>
                <i class="fas fa-key me-1"></i> ¿Olvidaste la contraseña?
            </a>

            <div class="button-group">
                <button type="submit" class="btn-login" id="btnLogin" <?php if (!$db_ok) echo 'disabled'; ?>>
                    <i class="fas fa-arrow-right-to-bracket"></i> Iniciar Sesión
                </button>
                <button type="button" class="btn-back-home" id="backHomeBtn">
                    <i class="fas fa-arrow-left"></i> Regresar
                </button>
            </div>
        </form>

        <div class="info-message">
            <i class="fas fa-info-circle"></i>
            <span>¿Necesita una cuenta? Solicítela por correo a <strong><a href="#" id="solicitarCuentaLink">soporte_TransNuBeT@gmail.com</a></strong></span>
        </div>

        <div class="login-footer">
            <p><i class="fas fa-shield-alt"></i> Sistema seguro · Datos encriptados</p>
            <p style="margin-top: 8px;">Copyright © <?php echo date('Y'); ?> <span style="font-weight: bold;"><?php echo htmlspecialchars($COMPANY_NAME); ?></span></p>
            <div class="login-footer-links">
                <a href="../terminos.php"><i class="fas fa-file-contract"></i> Términos</a>
                <a href="../privacidad.php"><i class="fas fa-lock"></i> Privacidad</a>
                <a href="../soporte.php"><i class="fas fa-headset"></i> Soporte</a>
            </div>
    <!-- Línea de copyright -->
    <div style="text-align: center; margin-top: 16px; padding: 12px; font-size: 0.7rem; color: #5a6a8a;">
        <div class="copyright-logo">
            <img src="../images/unicorn.png" alt="Unicornio" class="unicorn-icon">
            <span style="font-weight:bold;font-size:14px;color:white;">Copyright © <?php echo date('Y'); ?> UnicornioSoftware° - Kaky&reg;. Todos los derechos reservados.</span>
        </div>
    </div>
        </div>
    </div>
</div>

<!-- MODAL MANTENIMIENTO -->
<div class="win11-modal-overlay" id="maintenanceModal">
  <div class="win11-modal" style="border: 1px solid #ffc107; box-shadow: 0 0 40px rgba(255, 193, 7, 0.2);">
    <div class="win11-modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.3);">
      <div class="modal-title-wrapper">
        <div class="modal-icon maintenance-icon-pulse">
          <i class="fa-solid fa-helmet-safety"></i>
        </div>
        <h3 class="modal-title" style="color: #ffc107;">🛠️ Modo Mantenimiento Activo</h3>
      </div>
    </div>
    <div class="win11-modal-body">
      <p style="font-size: 1.1em; color: #fff; margin-bottom: 20px; text-align: center;">
        <strong><?php echo htmlspecialchars($COMPANY_NAME); ?></strong> se encuentra actualmente en modo de mantenimiento.
      </p>
      <div style="background: rgba(255, 193, 7, 0.05); border-radius: 8px; padding: 15px; border: 1px dashed rgba(255, 193, 7, 0.3);">
        <p style="color: #ffc107; text-align: center; margin-bottom: 0;">Por favor, intente más tarde.</p>
      </div>
    </div>
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-secondary" onclick="closeMaintenanceModal()">
        <i class="fa-solid fa-eye me-1"></i> Solo mirar
      </button>
      <button class="modal-btn modal-btn-primary" onclick="window.location.href='?access_bypass=true'">
        <i class="fa-solid fa-unlock-keyhole me-1"></i> Acceso Programador
      </button>
      <button id="btn-recheck-maint" class="modal-btn modal-btn-primary" onclick="recheckMaintenanceStatus()" 
              style="background-color: #ffc107; color: #000; border: none;">
        <i class="fa-solid fa-rotate-right me-2"></i> Comprobar
      </button>
    </div>
  </div>
</div>

<script>
// ==========================================
// VARIABLES DE ESTADO DEL SERVIDOR (PHP)
// ==========================================
const dbOk = <?php echo json_encode($db_ok); ?>;
const dbErrorMsg = <?php echo json_encode($db_error); ?>;
const companyName = '<?php echo addslashes($COMPANY_NAME); ?>';
const restabpw = <?php echo intval($restabpw); ?>;

// ==========================================
// SI LA BD ESTÁ DESCONECTADA, MOSTRAR ALERTA CON BOTÓN COMPROBAR
// ==========================================
if (!dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '⚠️ Error de conexión con la base de datos',
            html: `<div style="text-align: left;">
                        <p><strong>MySQL:</strong> <span style="color: #ef4444;">✗ Desconectado</span></p>
                        <p><strong>Error:</strong> <span style="color: #fca5a5;">${dbErrorMsg || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</span></p>
                        <hr style="margin: 12px 0; border-color: rgba(255,255,255,0.1);">
                        <p><i class="fas fa-exclamation-triangle"></i> El sistema de nóminas no está disponible temporalmente.</p>
                        <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                        ✉️ <strong>Email:</strong> <a href="mailto:admin_sistemasTransNuBeT@gmail.com" style="color:#60a5fa;">admin_sistemasTransNuBeT@gmail.com</a></p>
                    </div>`,
            icon: 'error',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
            confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
            showCancelButton: true,
            cancelButtonColor: '#3b82f6',
            cancelButtonText: '<i class="fas fa-sync-alt"></i> Comprobar estado',
            allowOutsideClick: false
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                // Se presionó "Comprobar estado"
                checkDatabaseStatus();
            }
        });
        // Deshabilitar campos y botones manualmente
        document.getElementById('username').disabled = true;
        document.getElementById('password').disabled = true;
        document.getElementById('btnLogin').disabled = true;
        document.getElementById('forgotPasswordLink').style.pointerEvents = 'none';
        document.getElementById('forgotPasswordLink').style.opacity = '0.5';
    });
}

// ==========================================
// FUNCIONES AUXILIARES (solo se ejecutan si dbOk)
// ==========================================
let debounceTimer;
const MAX_LENGTH = 100;

function updateInputIcon(value) {
    const dynamicIcon = document.getElementById('dynamicIcon');
    let newIconClass = 'fa-user';
    value = value.trim();
    if (value.length === 0) newIconClass = 'fa-user';
    else if (value.includes('@')) newIconClass = 'fa-envelope';
    else if (/^\d{11}$/.test(value)) newIconClass = 'fa-address-card';
    else if (/^\d+$/.test(value)) newIconClass = 'fa-hashtag';
    dynamicIcon.className = 'fas ' + newIconClass;
}

function cargarImagenUsuario(usuario) {
    if (!usuario.trim()) {
        document.getElementById('userAvatar').classList.remove('visible');
        return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetch(`login.php?ajax=get_imagen&usuario=${encodeURIComponent(usuario)}`)
            .then(response => response.json())
            .then(data => {
                const userAvatar = document.getElementById('userAvatar');
                if (data.success) userAvatar.src = data.imagen;
                else userAvatar.src = data.imagen;
                userAvatar.classList.add('visible');
            })
            .catch(() => {});
    }, 600);
}

function validarCI(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: '11 dígitos requeridos' };
    const año = ci.substr(0, 2);
    const mes = ci.substr(2, 2);
    const dia = ci.substr(4, 2);
    if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes inválido' };
    const diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const maxDias = diasPorMes[parseInt(mes) - 1];
    if (parseInt(mes) === 2 && parseInt(dia) === 29) {
        const añoCompleto = parseInt(año) < 30 ? 2000 + parseInt(año) : 1900 + parseInt(año);
        const esBisiesto = (añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0);
        if (!esBisiesto) return { valido: false, mensaje: '29/02 solo válido en años bisiestos' };
    } else if (dia < '01' || parseInt(dia) > maxDias) {
        return { valido: false, mensaje: 'Día inválido' };
    }
    const digitoGenero = parseInt(ci.charAt(9));
    const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 30 ? `20${año}` : `19${año}`;
    return { valido: true, mensaje: `<i class="fas fa-check-circle me-1"></i> CI válido | ${iconoGenero} ${genero} | Nac: ${dia}/${mes}/${añoCompleto}`, genero };
}

function procesarCI(valor) {
    const ciFeedback = document.getElementById('ciFeedback');
    const usernameInput = document.getElementById('username');
    if (/^\d{11}$/.test(valor)) {
        const resultado = validarCI(valor);
        ciFeedback.style.display = 'block';
        if (resultado.valido) {
            ciFeedback.innerHTML = resultado.mensaje;
            ciFeedback.style.color = '#22c55e';
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
        } else {
            ciFeedback.innerHTML = `<i class="fas fa-times-circle me-1"></i> ${resultado.mensaje}`;
            ciFeedback.style.color = '#ef4444';
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
        }
    } else {
        ciFeedback.style.display = 'none';
        usernameInput.classList.remove('is-valid', 'is-invalid');
    }
}

// ==========================================
// EVENTOS (solo si dbOk)
// ==========================================
if (dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        const usernameInput = document.getElementById('username');
        const charCounter = document.getElementById('charCounter');
        const userAvatar = document.getElementById('userAvatar');
        
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const inputLength = this.value.length;
                const valor = this.value.trim();
                charCounter.textContent = `${inputLength}/${MAX_LENGTH}`;
                if (inputLength === 0) charCounter.classList.remove('show');
                else {
                    charCounter.classList.add('show');
                    if (inputLength > (MAX_LENGTH - 10)) charCounter.style.color = '#ef4444';
                    else charCounter.style.color = 'rgba(255, 255, 255, 0.5)';
                }
                updateInputIcon(valor);
                procesarCI(valor);
                if (valor.length === 0) userAvatar.classList.remove('visible');
                else cargarImagenUsuario(valor);
            });
            usernameInput.addEventListener('focus', function() {
                if (this.value.length > 0) charCounter.classList.add('show');
                if (this.value.trim()) cargarImagenUsuario(this.value);
            });
            usernameInput.addEventListener('blur', function() {
                if (this.value.length === 0) charCounter.classList.remove('show');
            });
            if (usernameInput.value.trim()) {
                const val = usernameInput.value.trim();
                updateInputIcon(val);
                cargarImagenUsuario(val);
                procesarCI(val);
            }
        }
        
        // Toggle password
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
    });
}

// ==========================================
// BOTÓN REGRESAR (siempre funcional)
// ==========================================
const backHomeBtn = document.getElementById('backHomeBtn');
if (backHomeBtn) {
    backHomeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-arrow-left" style="color: #f59e0b; font-size: 2rem;"></i><br>¿Regresar al inicio?',
            html: '<p style="font-size: 1rem;">Se cerrará el acceso al sistema de nóminas.</p>',
            icon: 'question',
            background: '#0f172a',
            color: '#e2e8f0',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: '<i class="fas fa-arrow-left"></i> Sí, regresar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '../index.php';
        });
    });
}

// ==========================================
// RECUPERACIÓN DE CONTRASEÑA (solo si dbOk)
// ==========================================
function openForgotPassword() {
    Swal.fire({
        title: '🔐 Recuperar Contraseña',
        html: `
            <div style="text-align: center; padding: 10px 0;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <div style="background: linear-gradient(135deg, #3b82f6, #2563eb); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                        <i class="fa-solid fa-key" style="font-size: 1.5rem; color: white;"></i>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0; color: #60a5fa; font-size: 1.1rem;">Recuperación de Acceso</h3>
                        <p style="margin: 5px 0 0 0; color: #8899aa; font-size: 0.85rem;">Complete todos los campos requeridos</p>
                    </div>
                </div>
                <div style="background: rgba(59, 130, 246, 0.05); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(59, 130, 246, 0.1);">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fa-solid fa-user" style="color: #3b82f6; margin-right: 8px;"></i> Nombre de Usuario
                        </label>
                        <input type="text" id="forgotUsuario" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size: 0.95rem;" placeholder="Ej: juan.perez">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fa-solid fa-id-card" style="color: #22c55e; margin-right: 8px;"></i> Carné de Identidad (11 dígitos)
                        </label>
                        <input type="text" id="forgotCarnet" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size: 0.95rem;" placeholder="Ej: 85010112345" maxlength="11">
                    </div>
                    <div>
                        <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fa-solid fa-user-circle" style="color: #8b5cf6; margin-right: 8px;"></i> Nombre Completo
                        </label>
                        <input type="text" id="forgotNombre" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size: 0.95rem;" placeholder="Ej: Juan Pérez Rodríguez">
                    </div>
                </div>
            </div>
        `,
        background: '#0f172a',
        color: '#eee',
        width: 500,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-key" style="margin-right: 8px;"></i> Recuperar Contraseña',
        confirmButtonColor: '#3b82f6',
        cancelButtonText: '<i class="fa-solid fa-times" style="margin-right: 8px;"></i> Cancelar',
        cancelButtonColor: '#475569',
        preConfirm: () => {
            const usuario = document.getElementById('forgotUsuario').value.trim();
            const carnet = document.getElementById('forgotCarnet').value.trim();
            const nombre = document.getElementById('forgotNombre').value.trim();
            if (!usuario) { Swal.showValidationMessage('Por favor ingrese su nombre de usuario'); return false; }
            if (!carnet) { Swal.showValidationMessage('Por favor ingrese su número de carné de identidad'); return false; }
            if (!/^\d{11}$/.test(carnet)) { Swal.showValidationMessage('El carné debe tener 11 dígitos'); return false; }
            if (!nombre) { Swal.showValidationMessage('Por favor ingrese su nombre completo'); return false; }
            return { usuario: usuario, no_ci: carnet, nombre_completo: nombre };
        }
    }).then((result) => {
        if (result.isConfirmed) recoverPassword(result.value);
    });
}

function openRecoverModal(e) {
    if(e) e.preventDefault();
    Swal.fire({
        title: '🔒 Recuperación de Contraseña',
        html: `<div style="text-align: center; padding: 20px;">
                    <i class="fa-solid fa-shield-cat" style="font-size: 3rem; color: #3b82f6; margin-bottom: 15px;"></i>
                    <p style="font-size: 1.1em; color: #fff; margin-bottom: 15px;"><strong>¿Has olvidado tu contraseña?</strong></p>
                    <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 20px;">Por razones de seguridad, el restablecimiento de contraseñas debe ser solicitado directamente al administrador del sistema.</p>
                    <div style="background: rgba(59, 130, 246, 0.1); border-radius: 8px; padding: 15px; border: 1px dashed rgba(59, 130, 246, 0.3);">
                        <h5 style="color: #60a5fa; margin-bottom: 10px;">Contacta con soporte:</h5>
                        <a href="mailto:soporte_TransNuBeT@gmail.com" style="display: inline-block; background: #3b82f6; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none;">
                            <i class="fa-solid fa-envelope me-2"></i> Correo
                        </a>
                    </div>
                </div>`,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonText: '<i class="fas fa-check"></i> Entendido',
        confirmButtonColor: '#3b82f6'
    });
}

function recoverPassword(data) {
    Swal.fire({
        title: 'Verificando...',
        html: '<div style="padding: 20px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i></div>',
        background: '#0f172a',
        color: '#eee',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    const formData = new URLSearchParams(data);
    formData.append('step', 'verify');
    fetch('login.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (!result.success) {
            Swal.fire({
                title: '❌ Datos Incorrectos',
                text: result.message,
                icon: 'error',
                background: '#0f172a',
                color: '#eee',
                confirmButtonText: 'Intentar de nuevo',
                confirmButtonColor: '#ef4444'
            }).then(() => { openForgotPassword(); });
            return;
        }
        if (result.action === 'confirm_required') {
            Swal.fire({
                title: '⚠️ Confirmación Requerida',
                html: `<div style="text-align: left; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <div><strong>Usuario:</strong> ${result.usuario}</div>
                        <div><strong>CI:</strong> ${result.no_ci}</div>
                        <div><strong>Nombre:</strong> ${result.nombre}</div>
                        </div><p>¿Está seguro que desea restablecer su contraseña?</p>`,
                icon: 'question',
                background: '#0f172a',
                color: '#eee',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-check"></i> Sí, Restablecer',
                confirmButtonColor: '#ffc107',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                cancelButtonColor: '#475569'
            }).then((confirmResult) => {
                if (confirmResult.isConfirmed) {
                    Swal.fire({
                        title: 'Restableciendo...',
                        html: '<div style="padding: 20px;"><i class="fa-solid fa-cog fa-spin fa-2x" style="color: #ffc107;"></i></div>',
                        background: '#0f172a',
                        showConfirmButton: false
                    });
                    formData.set('step', 'reset');
                    fetch('login.php?action=forgot_password', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                    .then(resp => resp.json())
                    .then(finalResult => {
                        if(finalResult.success && finalResult.action === 'reset_success') {
                            Swal.fire({
                                title: '✅ ¡Contraseña Restablecida!',
                                html: `<div style="text-align: center; padding: 15px;">
                                        <div style="background: rgba(34, 197, 94, 0.1); border: 2px solid #22c55e; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                                            <p style="color: #eee; margin-bottom: 10px;">Su nueva contraseña es:</p>
                                            <code style="font-size: 1.5rem; font-weight: bold; color: #22c55e; letter-spacing: 2px;">${finalResult.nueva_password}</code>
                                        </div>
                                        <p style="color: #94a3b8; font-size: 0.85rem;">Cópiela e inicie sesión inmediatamente.</p>
                                    </div>`,
                                background: '#0f172a',
                                color: '#eee',
                                confirmButtonText: '<i class="fa-solid fa-check"></i> Entendido',
                                confirmButtonColor: '#22c55e'
                            }).then(() => {
                                navigator.clipboard.writeText(finalResult.nueva_password);
                                document.getElementById('username').value = finalResult.usuario;
                                document.getElementById('password').value = finalResult.nueva_password;
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo restablecer' });
                        }
                    });
                }
            });
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Error de conexión', background: '#0f172a', color: '#fff' });
    });
}

if (dbOk) {
    const forgotLink = document.getElementById('forgotPasswordLink');
    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (restabpw == 1) openForgotPassword();
            else openRecoverModal();
        });
    }
}

// ==========================================
// SOLICITAR CUENTA (envío por correo)
// ==========================================
const solicitarCuentaLink = document.getElementById('solicitarCuentaLink');
if (solicitarCuentaLink) {
    solicitarCuentaLink.addEventListener('click', function(e) {
        e.preventDefault();
        const modalContent = `
            <div style="text-align: left; max-height: 500px; overflow-y: auto; padding-right: 10px;">
                <div class="modal-form-field">
                    <label><i class="fas fa-user"></i> Nombre y Apellidos</label>
                    <input type="text" id="nombre_apellidos" placeholder="Ej: Juan Pérez García" autocomplete="off">
                </div>
                <div class="row-2-columns" style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div class="modal-form-field" style="flex:1;">
                        <label><i class="fas fa-id-card"></i> N° de Identidad (CI)</label>
                        <input type="text" id="ci" placeholder="Ej: 12345678901" autocomplete="off">
                    </div>
                    <div class="modal-form-field" style="flex:1;">
                        <label><i class="fas fa-phone-alt"></i> <strong style="color: #60a5fa;">No. Teléfono</strong></label>
                        <input type="tel" id="telefono" placeholder="Ej: 32415418" autocomplete="off">
                    </div>
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <input type="text" id="direccion" placeholder="Ej: Calle 5ta #123, Nuevitas" autocomplete="off">
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-briefcase"></i> Área de trabajo</label>
                    <input type="text" id="area" placeholder="Ej: Recursos Humanos, Contabilidad, TI" autocomplete="off">
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-cubes"></i> Subsistema al que solicita acceso</label>
                    <select id="subsistema">
                        <option value="">-- Seleccione un subsistema --</option>
                        <option value="Nóminas">📊 Nóminas</option>
                        <option value="Facturación">📄 Facturación</option>
                        <option value="Costos">💰 Costos</option>
                        <option value="Inventarios">📦 Inventarios</option>
                        <option value="Administración">🛠️ Administración</option>
                    </select>
                    <small style="color: #ffffff; font-size: 0.65rem; display: block; margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> <strong>Información importante:</strong> Su solicitud será revisada por el equipo de soporte en un plazo máximo de 24 horas hábiles.
                    </small>
                </div>
            </div>
        `;
        Swal.fire({
            title: '<i class="fas fa-envelope" style="color: #3b82f6;"></i> Solicitud de Acceso',
            html: modalContent,
            background: '#0f172a',
            color: '#e2e8f0',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#475569',
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Enviar Solicitud',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            preConfirm: () => {
                const nombre = document.getElementById('nombre_apellidos').value.trim();
                const ci = document.getElementById('ci').value.trim();
                const telefono = document.getElementById('telefono').value.trim();
                const direccion = document.getElementById('direccion').value.trim();
                const area = document.getElementById('area').value.trim();
                const subsistema = document.getElementById('subsistema').value;
                if (!nombre) { Swal.showValidationMessage('❌ Por favor, ingrese su Nombre y Apellidos'); return false; }
                if (!ci) { Swal.showValidationMessage('❌ Por favor, ingrese su N° de Identidad (CI)'); return false; }
                if (!telefono) { Swal.showValidationMessage('❌ Por favor, ingrese su N° de Teléfono'); return false; }
                if (!direccion) { Swal.showValidationMessage('❌ Por favor, ingrese su Dirección'); return false; }
                if (!area) { Swal.showValidationMessage('❌ Por favor, ingrese su Área de trabajo'); return false; }
                if (!subsistema) { Swal.showValidationMessage('❌ Por favor, seleccione un Subsistema'); return false; }
                return { nombre, ci, telefono, direccion, area, subsistema };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const { nombre, ci, telefono, direccion, area, subsistema } = result.value;
                const currentYear = <?php echo date('Y'); ?>;
                const body = `%0D%0A%0D%0A--- DATOS DEL SOLICITANTE ---%0D%0A%0D%0A` +
                    `Nombre y Apellidos: ${encodeURIComponent(nombre)}%0D%0A` +
                    `N° de Identidad (CI): ${encodeURIComponent(ci)}%0D%0A` +
                    `N° de Teléfono: ${encodeURIComponent(telefono)}%0D%0A` +
                    `Dirección: ${encodeURIComponent(direccion)}%0D%0A` +
                    `Área de trabajo: ${encodeURIComponent(area)}%0D%0A%0D%0A` +
                    `--- DATOS DE LA SOLICITUD ---%0D%0A%0D%0A` +
                    `Subsistema al que solicita acceso: ${encodeURIComponent(subsistema)}%0D%0A%0D%0A` +
                    `--- INFORMACIÓN ADICIONAL ---%0D%0A%0D%0A` +
                    `Proyecto de Desarrollo Local TransNuBeT%0D%0A` +
                    `Transformando Nuevitas.%0D%0A%0D%0A` +
                    `Copyright © ${currentYear} UnicornioSoftware°%0D%0A`;
                const mailtoLink = `mailto:soporte_TransNuBeT@gmail.com?subject=SOLICITUD%20DE%20ACCESO%20-%20${encodeURIComponent(subsistema)}%20-%20${encodeURIComponent(nombre)}&body=${body}`;
                Swal.fire({
                    title: '<i class="fas fa-check-circle" style="color: #22c55e;"></i> ¡Solicitud lista!',
                    html: `<p>Se abrirá su cliente de correo para enviar la solicitud.</p>`,
                    icon: 'success',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#22c55e',
                    confirmButtonText: '<i class="fas fa-envelope"></i> Abrir correo'
                }).then(() => { window.location.href = mailtoLink; });
            }
        });
    });
}

// ==========================================
// MANTENIMIENTO Y MODO PROGRAMADOR (solo si dbOk)
// ==========================================
function openMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.remove('active'); document.body.style.overflow = 'auto'; }
}
function disableLoginAccess() {
    const btn = document.getElementById('btnLogin');
    const user = document.getElementById('username');
    const pass = document.getElementById('password');
    const forgotLink = document.querySelector('.forgot-link');
    if(btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento'; }
    if(user) user.disabled = true;
    if(pass) pass.disabled = true;
    if(forgotLink) forgotLink.classList.add('disabled-link');
}
function recheckMaintenanceStatus() {
    let btn = document.getElementById('btn-recheck-maint');
    let originalContent = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }
    fetch('login.php?action=check_status')
        .then(response => response.json())
        .then(data => {
            if (data.maintenance) {
                Swal.fire({ icon: 'info', title: 'Sistema en mantenimiento', text: 'El sistema continúa en mantenimiento.', background: '#0f172a', confirmButtonColor: '#ffc107' });
            } else {
                Swal.fire({ icon: 'success', title: '¡Mantenimiento finalizado!', text: 'El sistema está operativo nuevamente.', timer: 2000, showConfirmButton: false, background: '#0f172a' }).then(() => { location.reload(); });
            }
            if (btn) { btn.innerHTML = originalContent; btn.disabled = false; }
        })
        .catch(() => { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar.' }); if (btn) { btn.innerHTML = originalContent; btn.disabled = false; } });
}

if (dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isProgrammerBypass = urlParams.get('access_bypass') === 'true';
        if (!isProgrammerBypass) {
            fetch('login.php?action=check_status')
                .then(res => res.json())
                .then(data => { if (data.maintenance) { openMaintenanceModal(); disableLoginAccess(); } })
                .catch(e => console.error(e));
        } else {
            const badge = document.createElement('div');
            badge.innerHTML = '<i class="fa-solid fa-user-secret"></i> Modo Programador';
            badge.className = 'programmer-badge';
            document.body.appendChild(badge);
            const exitBadge = document.createElement('div');
            exitBadge.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Salir del modo';
            exitBadge.className = 'exit-badge';
            exitBadge.onclick = function() { window.location.replace(window.location.origin + window.location.pathname); };
            document.body.appendChild(exitBadge);
        }
    });
}

// ==========================================
// MOSTRAR ERRORES DE LOGIN CON SWEETALERT
// ==========================================
<?php if ($error && $db_ok): ?>
document.addEventListener('DOMContentLoaded', function() {
    let titulo = '', mensaje = '', icono = 'error', botonColor = '#ef4444';
    <?php if ($error === 'complete_campos'): ?>
        titulo = 'Campos Vacíos';
        mensaje = 'Por favor, complete todos los campos del formulario.';
        icono = 'warning';
        botonColor = '#f59e0b';
    <?php elseif ($error === 'credenciales_invalidas'): ?>
        titulo = 'Credenciales Incorrectas';
        mensaje = 'Usuario, carné o contraseña incorrectos. Verifique sus credenciales.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'error_sistema'): ?>
        titulo = 'Error del Sistema';
        mensaje = 'Ocurrió un error al procesar su solicitud. Intente más tarde.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php else: ?>
        titulo = 'Acceso Denegado';
        mensaje = '<?php echo addslashes($error); ?>';
        icono = 'error';
        botonColor = '#ef4444';
    <?php endif; ?>
    Swal.fire({
        title: '<i class="fas ' + (icono === 'error' ? 'fa-times-circle' : 'fa-exclamation-triangle') + '" style="color: ' + botonColor + '; font-size: 2rem;"></i><br>' + titulo,
        html: '<p style="font-size: 1rem;">' + mensaje + '</p>',
        icon: icono,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: botonColor,
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        allowOutsideClick: false
    }).then(() => {
        <?php if ($error === 'credenciales_invalidas'): ?>
            document.getElementById('password').value = '';
            document.getElementById('password').focus();
        <?php endif; ?>
    });
});
<?php endif; ?>

// ==========================================
// VALIDACIÓN ANTES DE ENVIAR FORMULARIO (solo si dbOk)
// ==========================================
if (dbOk) {
    const form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const userVal = document.getElementById('username') ? document.getElementById('username').value.trim() : '';
            const passVal = document.getElementById('password') ? document.getElementById('password').value.trim() : '';
            if (!userVal || !passVal) {
                e.preventDefault();
                let mensaje = '';
                if (!userVal && !passVal) mensaje = 'Por favor, complete todos los campos del formulario.';
                else if (!userVal) mensaje = 'El campo <strong>Usuario/Email/CI</strong> es obligatorio.';
                else if (!passVal) mensaje = 'El campo <strong>Contraseña</strong> es obligatorio.';
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size: 2rem;"></i><br><?php echo addslashes($COMPANY_NAME); ?>',
                    html: '<p style="font-size: 1rem;">' + mensaje + '</p>',
                    icon: 'warning',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: '<i class="fas fa-edit me-2"></i> Completar campos'
                });
                return false;
            }
            const btn = this.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verificando...';
            btn.disabled = true;
        });
    }
}

// ==========================================
// MENSAJES DE SESIÓN EXPIRADA O CERRADA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['expired']) && $_GET['expired'] == 1 && $db_ok): ?>
    Swal.fire({
        title: '<i class="fas fa-clock" style="color: #f59e0b; font-size: 2rem;"></i><br><?php echo addslashes($COMPANY_NAME); ?>',
        text: 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.',
        icon: 'warning',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#2563eb',
        confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión'
    });
    <?php elseif (isset($_GET['logout']) && $_GET['logout'] == 1 && $db_ok): ?>
    Swal.fire({
        title: '<i class="fas fa-check-circle" style="color: #22c55e; font-size: 2rem;"></i><br><?php echo addslashes($COMPANY_NAME); ?>',
        text: 'Ha cerrado sesión correctamente.',
        icon: 'success',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#22c55e',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        timer: 2500,
        showConfirmButton: true
    });
    <?php endif; ?>
});

// ==========================================
// FUNCIÓN PARA COMPROBAR ESTADO DE LA BD
// ==========================================
function checkDatabaseStatus() {
    Swal.fire({
        title: 'Comprobando conexión...',
        html: '<div class="loading-spinner-small" style="margin: 0 auto;"></div>',
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    
    fetch('login.php?action=check_status')
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.db_ok) {
                Swal.fire({
                    title: '✅ Base de datos conectada',
                    text: 'MySQL está funcionando correctamente. La página se recargará para activar el sistema.',
                    icon: 'success',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#22c55e',
                    confirmButtonText: '<i class="fas fa-sync-alt"></i> Recargar ahora'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: '❌ MySQL desconectado',
                    html: `<div style="text-align: left;">
                            <p><strong>Error:</strong> ${dbErrorMsg || 'No se puede establecer conexión'}</p>
                            <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                            ✉️ <strong>Email:</strong> <a href="mailto:admin_sistemasTransNuBeT@gmail.com" style="color:#60a5fa;">admin_sistemasTransNuBeT@gmail.com</a></p>
                            </div>`,
                    icon: 'error',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
                    showCancelButton: true,
                    cancelButtonColor: '#3b82f6',
                    cancelButtonText: '<i class="fas fa-sync-alt"></i> Reintentar'
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        checkDatabaseStatus(); // reintentar
                    }
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                title: 'Error de red',
                text: 'No se pudo verificar el estado. Intente más tarde.',
                icon: 'error',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444'
            });
        });
}

// Asociar al botón si existe (se puede agregar un botón flotante si se desea, pero por ahora se usa solo en alerta)
// document.getElementById('btnCheckDb')?.addEventListener('click', checkDatabaseStatus);
</script>
</body>
</html>