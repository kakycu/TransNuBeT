<?php
// Instalador para Sistema de Facturación SISFACT-PDL VISIONES

// Para depuración (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Iniciar buffer de salida

// ==============================================
// FIX: Deshabilitar performance_schema para evitar errores
// ==============================================
// Esto evita que MySQL intente usar performance_schema.session_variables
// que puede no existir en algunas instalaciones
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// También podemos intentar forzar el modo de compatibilidad
try {
    // Intentar crear una conexión temporal para establecer variables
    $tempConn = @new mysqli('localhost', 'root', '', '', 3306);
    if (!$tempConn->connect_error) {
        // Intentar deshabilitar performance_schema
        @$tempConn->query("SET GLOBAL performance_schema = OFF");
        @$tempConn->query("SET SESSION performance_schema = OFF");
        // Habilitar modo de compatibilidad 5.6 si existe
        @$tempConn->query("SET GLOBAL show_compatibility_56 = ON");
        $tempConn->close();
    }
} catch (Exception $e) {
    // Ignorar errores - esto es solo un intento
}
// ==============================================


// Definir constantes para versiones PHP
define('MIN_PHP_VERSION', '7.4.0');

// Configuración de logs
define('LOG_FILE', 'install_logs.txt');

// Iniciar sesión
session_start();

$CarpetaSistema = basename(dirname(__DIR__));

// Función para escribir logs
function writeLog($message, $type = 'INFO') {
    $timestamp = date('d-m-Y H:i:s'); // Formato dd-mm-aaaa
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Escribir en archivo
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    
    return $logEntry;
}

// Función para leer logs
function readLogs() {
    if (file_exists(LOG_FILE)) {
        return file_get_contents(LOG_FILE);
    }
    return "No hay logs disponibles.";
}

// Función para enviar respuestas JSON
function sendJsonResponse($data, $httpCode = 200) {
    // Limpiar buffer completamente
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Si es una petición POST, procesarla antes de cualquier salida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Limpiar cualquier salida anterior
    if (ob_get_length()) ob_clean();
    
    switch ($action) {
        case 'check_requirements':
            writeLog('Verificando requisitos del sistema', 'INFO');
            checkRequirementsHandler();
            break;
            
        case 'test_connection':
            writeLog('Probando conexión a base de datos', 'INFO');
            testConnectionHandler();
            break;
            
        case 'create_database':
            writeLog('Creando base de datos', 'INFO');
            createDatabaseHandler();
            break;
            
        case 'create_admin':
            writeLog('Creando usuario administrador', 'INFO');
            createAdminHandler();
            break;
            
        case 'save_company_config':
            writeLog('Guardando configuración de empresa', 'INFO');
            saveCompanyConfigHandler();
            break;
            
        case 'save_config':
            writeLog('Guardando configuración del sistema', 'INFO');
            saveConfigHandler();
            break;
            
        case 'delete_installer':
            writeLog('Eliminando instalador', 'INFO');
            deleteInstallerHandler();
            break;
            
        case 'get_logs':
            $logs = readLogs();
            sendJsonResponse(['success' => true, 'logs' => $logs]);
            break;
            
        case 'log_message':
            $message = $_POST['log_message'] ?? '';
            $type = $_POST['log_type'] ?? 'info';
            writeLog($message, strtoupper($type));
            sendJsonResponse(['success' => true]);
            break;
            
        default:
            writeLog('Acción no válida: ' . $action, 'ERROR');
            sendJsonResponse(['success' => false, 'error' => 'Acción no válida'], 400);
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- CORREGIDO: Viewport optimizado para 1024x768 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>INSTALADOR BASE DE DATOS SISFACT-PDL VISIONES</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/sweetalert2.min.css">
    <style>
        :root {
            --bg-primary: #0d0d0d;
            --bg-secondary: #171717;
            --bg-tertiary: #2a2a2a;
            --bg-accent: #0078d4;
            --bg-accent-hover: #106ebe;
            --bg-success: #107c10;
            --bg-error: #d13438;
            --bg-warning: #ffb900;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --text-tertiary: #8a8a8a;
            --border-color: #3a3a3a;
            --border-light: #4a4a4a;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.7);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 10px;
            --radius-xl: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-mono: 'Segoe UI Variable', 'Segoe UI', system-ui, -apple-system, sans-serif;
            
            /* Windows 11 Mica effect variables */
            --mica-bg: rgba(32, 32, 32, 0.85);
            --mica-border: rgba(255, 255, 255, 0.1);
            
            /* Password strength colors */
            --password-weak: #d13438;
            --password-medium: #ffb900;
            --password-good: #107c10;
            --password-strong: #00b294;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Windows 11 Background effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(209, 52, 56, 0.1) 0%, transparent 50%);
            z-index: -1;
            transition: var(--transition);
        }


        .app-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px 20px;
            margin-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .app-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--bg-accent), #4f6bed);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .app-title h1 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .app-title p {
            color: var(--text-tertiary);
            font-size: 11px;
            font-weight: 400;
            line-height: 1.2;
        }

        .nav-steps {
            flex: 1;
            padding: 0 12px;
            overflow-y: auto;
            max-height: 400px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            margin-bottom: 2px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-size: 13px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: rgba(0, 120, 212, 0.15);
            color: var(--text-primary);
            border-left: 3px solid var(--bg-accent);
        }

        .nav-item.completed {
            color: var(--bg-success);
        }

        .nav-item.completed:hover {
            background: rgba(16, 124, 16, 0.1);
        }

        .nav-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .nav-item.active .nav-number {
            background: var(--bg-accent);
            color: white;
            box-shadow: 0 0 0 4px rgba(0, 120, 212, 0.2);
        }

        .nav-item.completed .nav-number {
            background: var(--bg-success);
            color: white;
        }

        .nav-label {
            flex: 1;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-icon {
            font-size: 11px;
            opacity: 0.7;
            flex-shrink: 0;
        }

        .nav-item.completed .nav-icon {
            color: var(--bg-success);
            opacity: 1;
        }

        .sidebar-footer {
            padding: 15px 20px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 15px;
            flex-shrink: 0;
        }

        .progress-container {
            margin-bottom: 8px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            position: absolute;
            height: 100%;
            background: linear-gradient(90deg, var(--bg-accent), #4f6bed);
            width: 0%;
            transition: var(--transition);
            border-radius: 2px;
        }

        .action-btn {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-bottom: 8px;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .action-btn.primary {
            background: var(--bg-accent);
            border-color: var(--bg-accent);
        }

        .action-btn.primary:hover {
            background: var(--bg-accent-hover);
        }



        .content-body {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            min-height: 0;
        }

        .step-content {
            display: none;
            animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 100%;
            width: 100%;
        }

        .step-content.active {
            display: block;
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateX(20px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Welcome Screen - AJUSTADO */
        .welcome-screen {
            text-align: center;
            padding: 10px;
        }

        .welcome-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bg-accent), #4f6bed);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
            box-shadow: 
                0 8px 16px rgba(0, 120, 212, 0.3),
                0 4px 8px rgba(0, 120, 212, 0.2);
        }


        .welcome-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 10px 0;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 12px 8px;
            text-align: center;
            transition: var(--transition);
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(0, 120, 212, 0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: var(--bg-accent);
            font-size: 18px;
        }

        .feature-text {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Requirements Screen - AJUSTADO */
        .requirements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .requirement-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
        }

        .requirement-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .requirement-card.success {
            border-left: 4px solid var(--bg-success);
        }

        .requirement-card.error {
            border-left: 4px solid var(--bg-error);
        }

        .requirement-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .requirement-card.success .requirement-icon {
            background: rgba(16, 124, 16, 0.1);
            color: var(--bg-success);
        }

        .requirement-card.error .requirement-icon {
            background: rgba(209, 52, 56, 0.1);
            color: var(--bg-error);
        }

        .requirement-info {
            flex: 1;
            min-width: 0;
        }

        .requirement-name {
            font-weight: 500;
            margin-bottom: 3px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .requirement-status {
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .requirement-card.success .requirement-status {
            color: var(--bg-success);
        }

        .requirement-card.error .requirement-status {
            color: var(--bg-error);
        }

        /* PHP Version Display */
        .php-version-display {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
        }

        .php-version {
            font-size: 20px;
            font-weight: 600;
            color: var(--bg-accent);
            margin-bottom: 6px;
        }

        .php-status {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .php-status.good {
            color: var(--bg-success);
        }

        .php-status.bad {
            color: var(--bg-error);
        }


        .form-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 16px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            font-size: 11px;
            color: var(--bg-accent);
        }

        .form-input, .form-textarea, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 10px 12px;
            color: var(--text-primary);
            font-size: 13px;
            transition: var(--transition);
            font-family: var(--font-mono);
            resize: vertical;
        }

        .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .form-select:focus option {
            background: var(--bg-tertiary);
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--bg-accent);
            box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-input:hover, .form-textarea:hover, .form-select:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.07);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: var(--text-tertiary);
        }

        /* Password input with toggle */
        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-wrapper .form-input {
            flex: 1;
            padding-right: 40px;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-tertiary);
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
            transition: var(--transition);
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        /* Password Strength Meter */
        .password-strength {
            margin-top: 8px;
            display: none;
        }

        .password-strength.active {
            display: block;
        }

        .strength-meter {
            height: 5px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 6px;
            position: relative;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: var(--transition);
            border-radius: 3px;
        }

        .strength-fill.weak {
            background: var(--password-weak);
            width: 25%;
        }

        .strength-fill.medium {
            background: var(--password-medium);
            width: 50%;
        }

        .strength-fill.good {
            background: var(--password-good);
            width: 75%;
        }

        .strength-fill.strong {
            background: var(--password-strong);
            width: 100%;
        }

        .strength-label {
            font-size: 11px;
            color: var(--text-tertiary);
            display: flex;
            justify-content: space-between;
        }

        .strength-text {
            font-weight: 500;
        }

        .strength-text.weak {
            color: var(--password-weak);
        }

        .strength-text.medium {
            color: var(--password-medium);
        }

        .strength-text.good {
            color: var(--password-good);
        }

        .strength-text.strong {
            color: var(--password-strong);
        }

        .strength-criteria {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 6px;
        }

        .criteria-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .criteria-item.met {
            color: var(--bg-success);
        }

        .criteria-icon {
            width: 14px;
            height: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Checkbox and Toggle Styles */
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-md);
            transition: var(--transition);
            cursor: pointer;
        }

        .toggle-group:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .toggle-wrapper {
            position: relative;
            width: 40px;
            height: 22px;
            flex-shrink: 0;
        }

        .toggle-checkbox {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-track {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: var(--transition);
            border-radius: 34px;
        }

        .toggle-track:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        .toggle-checkbox:checked + .toggle-track {
            background-color: var(--bg-accent);
        }

        .toggle-checkbox:checked + .toggle-track:before {
            transform: translateX(18px);
        }

        .toggle-label {
            flex: 1;
            cursor: pointer;
        }

        .toggle-label strong {
            display: block;
            font-weight: 500;
            margin-bottom: 3px;
            font-size: 13px;
        }

        .toggle-label small {
            display: block;
            font-size: 11px;
            color: var(--text-tertiary);
        }

        /* Success Screen - AJUSTADO */
        .success-screen {
            text-align: center;
            padding: 20px 10px;
        }

        .success-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--bg-success), #2eb82e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            color: white;
            box-shadow: 
                0 8px 16px rgba(16, 124, 16, 0.3),
                0 4px 8px rgba(16, 124, 16, 0.2);
        }

        .credentials-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            backdrop-filter: blur(20px);
        }

        .credentials-card h3 {
            color: var(--bg-accent);
            margin-bottom: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        .credential-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .credential-row:last-child {
            border-bottom: none;
        }

        .credential-label {
            width: 140px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            flex-shrink: 0;
        }

        .credential-value {
            flex: 1;
            font-family: 'Cascadia Code', 'Consolas', monospace;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px;
            word-break: break-all;
            min-width: 0;
        }

        .copy-btn {
            background: rgba(0, 120, 212, 0.1);
            color: var(--bg-accent);
            border: 1px solid rgba(0, 120, 212, 0.2);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 12px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .copy-btn:hover {
            background: rgba(0, 120, 212, 0.2);
            border-color: rgba(0, 120, 212, 0.3);
        }

        .warning-card {
            background: rgba(255, 185, 0, 0.05);
            border: 1px solid rgba(255, 185, 0, 0.2);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin: 20px 0;
            text-align: left;
        }

        .warning-card h3 {
            color: var(--bg-warning);
            margin-bottom: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        /* Action Buttons - AJUSTADO */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--bg-accent);
            color: white;
            border-color: var(--bg-accent);
        }

        .btn-primary:hover {
            background: var(--bg-accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 120, 212, 0.3);
        }

        .btn-primary:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-tertiary);
            border-color: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-success {
            background: var(--bg-success);
            color: white;
            border-color: var(--bg-success);
        }

        .btn-success:hover {
            background: #0e6a0e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(16, 124, 16, 0.3);
        }

        .btn-danger {
            background: var(--bg-error);
            color: white;
            border-color: var(--bg-error);
        }

        .btn-danger:hover {
            background: #c42b1f;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(209, 52, 56, 0.3);
        }

        .btn-dark {
            background: #343a40;
            color: white;
            border-color: #343a40;
        }

        .btn-dark:hover {
            background: #23272b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 58, 64, 0.3);
        }

        .btn-dark:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-tertiary);
            border-color: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Loading States */
        .loading {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .loading-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Test Results */
        .test-results {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            margin-top: 20px;
            overflow: hidden;
        }

        .test-result {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
            font-size: 13px;
        }

        .test-result:last-child {
            border-bottom: none;
        }

        .test-result:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .test-result.success {
            border-left: 4px solid var(--bg-success);
        }

        .test-result.error {
            border-left: 4px solid var(--bg-error);
        }

        /* Database Structure */
        .database-structure {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
        }

        .table-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .table-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .footer-left {
            text-align: left;
            flex: 1;
            min-width: 200px;
        }

        .footer-right {
            text-align: right;
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 200px;
            justify-content: flex-end;
        }

        .footer-buttons {
            display: flex;
            gap: 8px;
            margin-left: auto;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .footer-buttons .btn {
            padding: 8px 12px;
            margin: 0;
            line-height: 1.2;
            min-width: 80px;
            font-size: 12px;
        }

        .content-footer a {
            color: var(--bg-accent);
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .content-footer a:hover {
            text-decoration: underline;
        }

        /* Logs Modal - AJUSTADO */
        .logs-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .logs-modal.active {
            display: flex;
        }

        .logs-content {
            background: var(--mica-bg);
            backdrop-filter: blur(120px) saturate(180%);
            border: 1px solid var(--mica-border);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 700px;
            height: 70vh;
            display: flex;
            flex-direction: column;
            box-shadow: 
                0 16px 32px rgba(0, 0, 0, 0.5),
                0 8px 16px rgba(0, 0, 0, 0.4);
        }

        .logs-header {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logs-header h3 {
            font-size: 18px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logs-body {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            font-family: 'Cascadia Code', 'Consolas', monospace;
            font-size: 12px;
            line-height: 1.4;
            background: rgba(0, 0, 0, 0.2);
        }

        .log-entry {
            margin-bottom: 6px;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.03);
            border-left: 3px solid var(--bg-accent);
            word-break: break-all;
            animation: fadeIn 0.3s ease;
            font-size: 12px;
        }

        .log-entry.info {
            border-left-color: var(--bg-accent);
            color: #99d9ff;
        }

        .log-entry.success {
            border-left-color: var(--bg-success);
            color: #9bf19b;
        }

        .log-entry.error {
            border-left-color: var(--bg-error);
            color: #ffa6a8;
        }

        .log-entry.warning {
            border-left-color: var(--bg-warning);
            color: #ffd966;
        }

        .log-time {
            color: var(--text-tertiary);
            font-size: 10px;
            margin-right: 8px;
            font-family: monospace;
        }

        .logs-actions {
            padding: 12px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Real-time logs console - AJUSTADO */
        .logs-console {
            position: fixed;
            right: 0;
            top: 0;
            width: 320px;
            height: 100vh;
            background: var(--mica-bg);
            backdrop-filter: blur(120px) saturate(180%);
            border-left: 1px solid var(--mica-border);
            display: flex;
            flex-direction: column;
            z-index: 999;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -8px 0 32px rgba(0, 0, 0, 0.3);
        }

        .logs-console.active {
            transform: translateX(0);
        }

        .console-header {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(23, 23, 23, 0.8);
        }

        .console-header h3 {
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .console-toggle {
            position: fixed;
            right: 10px;
            top: 10px;
            z-index: 998;
            background: var(--mica-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--mica-border);
            border-radius: var(--radius-md);
            padding: 8px 12px;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .console-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-5px);
        }

        .console-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            font-family: 'Cascadia Code', 'Consolas', monospace;
            font-size: 11px;
            line-height: 1.3;
        }

        .console-entry {
            margin-bottom: 6px;
            padding: 8px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.03);
            border-left: 3px solid var(--bg-accent);
            animation: slideInRight 0.2s ease;
        }

        .console-entry.info {
            border-left-color: var(--bg-accent);
        }

        .console-entry.success {
            border-left-color: var(--bg-success);
        }

        .console-entry.error {
            border-left-color: var(--bg-error);
        }

        .console-entry.warning {
            border-left-color: var(--bg-warning);
        }

        .console-time {
            color: var(--text-tertiary);
            font-size: 9px;
            margin-right: 6px;
            font-family: monospace;
        }

        .console-controls {
            padding: 12px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 8px;
            background: rgba(23, 23, 23, 0.8);
        }

        @keyframes slideInRight {
            from { 
                opacity: 0;
                transform: translateX(10px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Database Progress Bar */
        .database-progress {
            margin: 16px 0;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Estilos para el sistema de progress bars */
        .database-progress-system {
            margin: 16px 0;
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border-radius: var(--radius-md);
            border: 1px solid rgba(255,255,255,0.05);
            transition: var(--transition);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            position: absolute;
            height: 100%;
            background: linear-gradient(90deg, var(--bg-accent), #4f6bed);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        /* Animación de parpadeo para progress bars activas */
        .progress-fill.active {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Estilos para los detalles de ejecución */
        .execution-step {
            padding: 6px 10px;
            margin: 4px 0;
            border-radius: 4px;
            background: rgba(255,255,255,0.03);
            border-left: 3px solid var(--bg-accent);
            font-size: 10px;
            animation: slideIn 0.3s ease;
        }

        .execution-step.success {
            border-left-color: var(--bg-success);
            color: #9bf19b;
        }

        .execution-step.error {
            border-left-color: var(--bg-error);
            color: #ffa6a8;
        }

        .execution-step.warning {
            border-left-color: var(--bg-warning);
            color: #ffd966;
        }

        .execution-step.info {
            border-left-color: var(--bg-accent);
            color: #99d9ff;
        }

        /* Icon styling */
        .icon {
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Responsive Design para 1024x768 */
        @media (max-width: 1024px) {
            body {
                padding: 5px;
                min-width: 100%;
            }
            
            .main-container {
                width: 100%;
                max-width: 100%;
                margin: 5px 0;
                height: auto;
                max-height: 90vh;
            }
            
            .content-header {
                padding: 10px 16px;
            }
            
            .content-title h2 {
                font-size: 18px;
            }
            
            .welcome-title {
                font-size: 24px;
            }
            
            
            .requirements-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-features {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                min-width: 100%;
            }
            
            .footer-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .content-footer {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 12px 16px;
            }
            
            .footer-left, .footer-right {
                text-align: center;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
                min-width: 100%;
            }
            
            .main-container {
                flex-direction: column;
                height: auto;
                max-height: 95vh;
                border-radius: 0;
                margin: 0;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                max-height: 300px;
            }
            
            .nav-steps {
                display: flex;
                overflow-x: auto;
                padding: 0 12px 12px;
                flex-wrap: nowrap;
            }
            
            .nav-item {
                flex-shrink: 0;
                white-space: nowrap;
            }
            
            .sidebar-footer {
                padding: 12px 16px 0;
                margin-top: 12px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }
            
            .action-btn {
                min-width: 100%;
                margin-bottom: 4px;
            }
            
            .content-body {
                padding: 8px;
            }
            
            .step-content {
                padding: 5px;
            }
        }

        /* Asegurar que el botón siguiente se vea al 100% */
        #next-button {
            width: 100%;
            margin: 0;
            justify-content: center;
            display: flex !important; /* Forzar display */
            visibility: visible !important; /* Forzar visibilidad */
            opacity: 1 !important; /* Forzar opacidad */
        }

        /* Soporte técnico */
        .action-btn.support-btn {
            background: rgba(255, 185, 0, 0.1);
            border-color: rgba(255, 185, 0, 0.2);
            color: var(--bg-warning);
            margin-top: 5px;
        }

        .action-btn.support-btn:hover {
            background: rgba(255, 185, 0, 0.2);
            border-color: rgba(255, 185, 0, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 185, 0, 0.2);
        }

        .action-btn.support-btn i {
            color: var(--bg-warning);
        }

        /* Para asegurar que los botones se vean bien en responsive */
        @media (max-width: 768px) {
            .sidebar-footer {
                padding: 12px 16px 0;
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }
            
            .progress-container {
                flex: 1;
                margin-bottom: 0;
                width: 100%;
            }
            
            .action-btn {
                min-width: 100%;
                margin-bottom: 4px;
            }
            
            .action-btn.support-btn {
                width: 100%;
                margin-top: 2px;
            }
        }
        /* Viewport optimizado para dispositivos y resoluciones de escritorio */
        body {
            font-family: var(--font-mono);
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 10px;
            line-height: 1;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            min-width: 100%;
            max-width: 100%;
            transition: var(--transition);
        }

        /* Contenedor principal ajustado para 1024x768 */
        .main-container {
            display: flex;
            width: 100%;
            max-width: 1024px;
            height: auto;
            min-height: 600px;
            max-height: 90vh;
            background: var(--mica-bg);
            backdrop-filter: blur(120px) saturate(180%);
            -webkit-backdrop-filter: blur(120px) saturate(180%);
            border-radius: var(--radius-xl);
            border: 1px solid var(--mica-border);
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.4),
                0 8px 16px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: var(--transition);
            margin: 10px 0;
        }

        /* Sidebar más compacta */
        .sidebar {
            width: 220px;
            background: rgba(23, 23, 23, 0.8);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            padding: 8px 0;
            position: relative;
            z-index: 1;
            transition: var(--transition);
            flex-shrink: 0;
        }

        /* Content area más compacta */
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
        }

        /* Header más compacto */
        .content-header {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(23, 23, 23, 0.5);
            flex-shrink: 0;
        }

        .content-title h2 {
            font-size: 18px;
            font-weight: 300;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .content-description {
            color: var(--text-secondary);
            font-size: 12px;
            max-width: 100%;
            line-height: 1.3;
        }

        /* Welcome screen ajustado */
        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--bg-accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .welcome-description {
            color: var(--text-secondary);
            font-size: 13px;
            max-width: 100%;
            margin: 0 auto 15px;
            line-height: 1.3;
            font-weight: 300;
            padding: 0 10px;
        }

        /* Form grids ajustados */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        /* Botones más compactos */
        .btn {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-width: 90px;
        }

        /* Footer ajustado */
        .content-footer {
            padding: 12px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            color: var(--text-tertiary);
            font-size: 10px;
            background: rgba(23, 23, 23, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Media queries específicas para 1024x768 */
        @media screen and (max-width: 1024px) and (max-height: 768px) {
            body {
                padding: 5px;
                align-items: flex-start;
            }
            
            .main-container {
                max-height: 95vh;
                margin: 5px 0;
            }
            
            .sidebar {
                width: 200px;
            }
            
            .content-body {
                padding: 8px;
                max-height: calc(95vh - 150px);
                overflow-y: auto;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .welcome-features {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .feature-card {
                padding: 10px 6px;
            }
            
            .feature-icon {
                width: 35px;
                height: 35px;
                font-size: 16px;
                margin-bottom: 10px;
            }
            
            .feature-text {
                font-size: 12px;
            }
        }

        /* Para pantallas más pequeñas que 1024 */
        @media screen and (max-width: 1023px) {
            .main-container {
                flex-direction: column;
                max-width: 100%;
                height: auto;
                max-height: none;
            }
            
            .sidebar {
                width: 100%;
                max-height: 250px;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .nav-steps {
                display: flex;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 10px;
            }
            
            .nav-item {
                flex-shrink: 0;
                white-space: nowrap;
            }
        }

        /* Para pantallas muy pequeñas */
        @media screen and (max-width: 768px) {
            .main-container {
                border-radius: var(--radius-lg);
                margin: 0;
                max-height: 100vh;
            }
            
            .content-header {
                padding: 8px 16px;
            }
            
            .content-title h2 {
                font-size: 16px;
            }
            
            .welcome-title {
                font-size: 20px;
            }
            
            .welcome-description {
                font-size: 12px;
            }
            
            .welcome-features {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
        }
/* Estilos para el slider */
.form-slider {
    -webkit-appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.1);
    outline: none;
    transition: var(--transition);
}

.form-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--bg-accent);
    cursor: pointer;
    border: 2px solid var(--bg-primary);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    transition: var(--transition);
}

.form-slider::-webkit-slider-thumb:hover {
    background: var(--bg-accent-hover);
    transform: scale(1.1);
}

.form-slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--bg-accent);
    cursor: pointer;
    border: 2px solid var(--bg-primary);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    transition: var(--transition);
}

.form-slider::-moz-range-thumb:hover {
    background: var(--bg-accent-hover);
    transform: scale(1.1);
}

.form-slider::-moz-range-track {
    background: rgba(255, 255, 255, 0.1);
    height: 6px;
    border-radius: 3px;
}
    </style>
</head>
<body>
    <!-- Real-time logs console -->
    <div class="logs-console" id="logsConsole">
        <div class="console-header">
            <h3><i class="fas fa-terminal"></i> Consola en Tiempo Real</h3>
            <button class="action-btn" onclick="toggleConsole()" style="padding: 6px 10px; font-size: 12px;">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
        <div class="console-body" id="consoleBody">
            <!-- Los logs en tiempo real se mostrarán aquí -->
        </div>
        <div class="console-controls">
            <button class="btn btn-secondary" onclick="clearConsole()" style="padding: 6px 10px; font-size: 11px;">
                <i class="fas fa-trash"></i> Limpiar
            </button>
            <button class="btn btn-primary" onclick="refreshConsole()" style="padding: 6px 10px; font-size: 11px;">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
    </div>
    
    <button class="console-toggle" onclick="toggleConsole()">
        <i class="fas fa-terminal"></i> Consola
        <span class="badge" id="consoleBadge" style="background: var(--bg-accent); color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px;">0</span>
    </button>

    <!-- Modal de Logs -->
    <div class="logs-modal" id="logsModal">
        <div class="logs-content">
            <div class="logs-header">
                <h3><i class="fas fa-terminal"></i> Logs del Instalador</h3>
                <button class="action-btn" onclick="closeLogs()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
            <div class="logs-body" id="logsBody">
                <!-- Los logs se cargarán aquí -->
            </div>
            <div class="logs-actions">
                <button class="btn btn-secondary" onclick="refreshLogs()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
                <button class="btn btn-primary" onclick="downloadLogs()">
                    <i class="fas fa-download"></i> Descargar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Requisitos -->
    <div class="logs-modal" id="requirementsModal">
        <div class="logs-content">
            <div class="logs-header">
                <h3><i class="fas fa-check-circle"></i> Requisitos del Sistema Verificados</h3>
                <button class="action-btn" onclick="closeRequirementsModal()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
            <div class="logs-body" id="requirementsBody">
                <!-- Los requisitos se mostrarán aquí -->
            </div>
            <div class="logs-actions">
                <button class="btn btn-primary" onclick="closeRequirementsModalAndContinue()">
                    <i class="fas fa-check"></i> Continuar
                </button>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Windows 11 Style Sidebar -->
        <div class="sidebar">
            <div class="app-brand">
                <div class="app-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="app-title">
                    <h1>SISFACT-PDL VISIONES</h1>
                    <p>Sistema de Facturación</p>
                </div>
            </div>
            
            <div class="nav-steps">
                <div class="nav-item active" onclick="goToStep(1)">
                    <div class="nav-number">1</div>
                    <div class="nav-label">Bienvenida</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(2)">
                    <div class="nav-number">2</div>
                    <div class="nav-label">Requisitos</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(3)">
                    <div class="nav-number">3</div>
                    <div class="nav-label">Base de Datos</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(4)">
                    <div class="nav-number">4</div>
                    <div class="nav-label">Administrador</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(5)">
                    <div class="nav-number">5</div>
                    <div class="nav-label">Empresa</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(6)">
                    <div class="nav-number">6</div>
                    <div class="nav-label">Configuración</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
                
                <div class="nav-item" onclick="goToStep(7)">
                    <div class="nav-number">7</div>
                    <div class="nav-label">Completar</div>
                    <i class="fas fa-chevron-right nav-icon"></i>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="progress-container">
                    <div class="progress-label">
                        <span>Progreso de Instalación</span>
                        <span id="progress-percent">14%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 14%"></div>
                    </div>
                </div>
                
                <button class="action-btn" onclick="previousStep()">
                    <i class="fas fa-arrow-left"></i> Atrás
                </button>
                <button class="action-btn primary" onclick="nextStep()" id="next-button">
                    <i class="fas fa-arrow-right"></i> Siguiente
                </button>
                <button class="action-btn support-btn" onclick="openSupport()" title="Abrir soporte técnico">
                    <i class="fas fa-headset"></i> Soporte Técnico
                </button>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="content-area">
            <div class="content-header">
                <div class="content-title">
                    <h2>Bienvenido al Asistente de Instalación de la Base de Datos de SISFACT PDL VISIONES</h2>
                    <p class="content-description">
                        Este asistente te guiará a través del proceso de instalación del Sistema de Facturación SISFACT-PDL VISIONES
                    </p>
                </div>
            </div>
            
            <div class="content-body">
                <!-- Paso 1: Pantalla de Bienvenida -->
                <div class="step-content active" id="step1">
                    <div class="welcome-screen">
                        <div class="welcome-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        
                        <h1 class="welcome-title">SISFACT-PDL VISIONES</h1>
                        
                        <p class="welcome-description">
                            Un sistema de facturación completo y moderno diseñado para gestionar eficientemente 
                            todas las operaciones de facturación, clientes y servicios de tu empresa.
                        </p>
                        
                        <div class="welcome-features">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="feature-text">Instalación Automática</div>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="feature-text">Seguridad Avanzada</div>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="feature-text">Configuración Flexible</div>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div class="feature-text">Soporte 24/7</div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="nextStep()" style="padding: 12px 24px; font-size: 14px;">
                                <i class="fas fa-play-circle"></i> Comenzar Instalación
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Paso 2: Verificación de Requisitos -->
                <div class="step-content" id="step2">
                    <h2 class="section-title">Requisitos del Sistema</h2>
                    <p class="content-description">Verificación de los requisitos necesarios para la instalación.</p>
                    
                    <!-- Mostrar versión de PHP -->
                    <div class="php-version-display">
                        <div class="php-version">PHP <?php echo PHP_VERSION; ?></div>
                        <div class="php-status <?php echo version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? 'good' : 'bad'; ?>">
                            <?php 
                            if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')) {
                                echo '<i class="fas fa-check"></i> Versión compatible (Requiere ' . MIN_PHP_VERSION . '+)';
                            } else {
                                echo '<i class="fas fa-times"></i> Versión incompatible (Requiere ' . MIN_PHP_VERSION . '+)';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="requirements-grid" id="requirements-list">
                        <!-- Las verificaciones se cargarán aquí -->
                    </div>
                    
                    <!-- Botón para mostrar requisitos en modal -->
                    <div class="action-buttons" style="margin-top: 20px;">
                        <button class="btn btn-primary" onclick="checkRequirements()" id="btn-check-req-step2">
                            <i class="fas fa-search"></i> Verificar Requisitos
                        </button>
                        <button class="btn btn-secondary" onclick="showRequirementsModal()" id="btn-show-reqs" style="display: none;">
                            <i class="fas fa-eye"></i> Ver Requisitos
                        </button>
                    </div>
                </div>
                
                <!-- Paso 3: Configuración de Base de Datos -->
                <div class="step-content" id="step3">
                    <h2 class="section-title">Configuración de Base de Datos</h2>
                    <p class="content-description">Configura los datos de conexión a tu servidor MySQL/MariaDB.</p>
                    
                    <!-- Sistema de Progress Bars para creación de BD -->
                    <div class="database-progress-system" id="progressSystem" style="display: none;">
                        <h4 style="margin-bottom: 16px; color: var(--text-primary);">
                            <i class="fas fa-cogs"></i> Progreso de Creación de Base de Datos
                        </h4>
                        
                        <!-- Progress Bar Total -->
                        <div style="margin-bottom: 20px;">
                            <div class="progress-label">
                                <span>Progreso Total</span>
                                <span id="total-progress-percent">0%</span>
                            </div>
                            <div class="progress-bar" style="height: 8px; margin-top: 5px;">
                                <div class="progress-fill" id="total-progress-fill" style="height: 8px; width: 0%; background: linear-gradient(90deg, var(--bg-accent), #4f6bed);"></div>
                            </div>
                        </div>
                        
                        <!-- Progress Bar de Tarea Actual -->
                        <div style="margin-bottom: 20px;">
                            <div class="progress-label">
                                <span>Tarea Actual</span>
                                <span id="task-progress-percent">0%</span>
                            </div>
                            <div class="progress-bar" style="height: 6px; margin-top: 5px;">
                                <div class="progress-fill" id="task-progress-fill" style="height: 6px; width: 0%; background: linear-gradient(90deg, var(--bg-success), #2eb82e);"></div>
                            </div>
                            <div id="current-task" style="margin-top: 6px; font-size: 12px; color: var(--text-secondary);">
                                Esperando inicio...
                            </div>
                        </div>
                        
                        <!-- Detalles de Ejecución -->
                        <div id="execution-details" style="margin-top: 16px; max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.2); padding: 12px; border-radius: var(--radius-sm);">
                            <div id="details-content" style="font-family: 'Cascadia Code', 'Consolas', monospace; font-size: 11px; line-height: 1.3;">
                                <div class="log-entry info">
                                    <span class="log-time"><?php echo date('H:i:s'); ?></span> Sistema listo para crear base de datos
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-database"></i> Conexión a la Base de Datos
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="db_host">
                                    <i class="fas fa-server"></i> Servidor
                                </label>
                                <input type="text" id="db_host" class="form-input" value="localhost" placeholder="localhost o 127.0.0.1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="db_port">
                                    <i class="fas fa-plug"></i> Puerto
                                </label>
                                <input type="text" id="db_port" class="form-input" value="3306" placeholder="3306">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="db_name">
                                    <i class="fas fa-database"></i> Nombre de la Base de Datos
                                </label>
                                <input type="text" id="db_name" class="form-input" value="sisfact_imdl" placeholder="sisfact_imdl" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="db_user">
                                    <i class="fas fa-user"></i> Usuario
                                </label>
                                <input type="text" id="db_user" class="form-input" value="root" placeholder="root">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="db_pass">
                                    <i class="fas fa-key"></i> Contraseña
                                </label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="db_pass" class="form-input" placeholder="Deja vacío si no tiene">
                                    <button type="button" class="password-toggle" onclick="togglePassword('db_pass')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="db_prefix">
                                    <i class="fas fa-tag"></i> Prefijo de Tablas (Predefinido)
                                </label>
                                <input type="text" id="db_prefix" class="form-input" value="" placeholder="sisfacVisiones_" readonly>
                                <small style="color: var(--text-tertiary); font-size: 11px; margin-top: 4px;">
                                    <i class="fas fa-info-circle"></i> No Utilizable
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso para creación de base de datos -->
                    <div class="database-progress" id="databaseProgress" style="display: none;">
                        <div class="progress-label">
                            <span>Creando base de datos...</span>
                            <span id="database-progress-percent">0%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="database-progress-fill" style="width: 0%"></div>
                        </div>
                        <div id="database-steps" style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">
                            <div id="current-step">Iniciando...</div>
                        </div>
                    </div>
                    
                    <div id="test-results" class="test-results" style="display: none;"></div>
                </div>
                
                <!-- Paso 4: Configuración del Administrador -->
                <div class="step-content" id="step4">
                    <h2 class="section-title">Configuración del Administrador</h2>
                    <p class="content-description">Define la información del usuario administrador del sistema.</p>
                    
                    <!-- Toggle Switch -->
                    <div class="toggle-group" onclick="toggleAutoCreateManual()">
                        <div class="toggle-wrapper">
                            <input type="checkbox" id="autocreate_user" class="toggle-checkbox">
                            <label class="toggle-track"></label>
                        </div>
                        <div class="toggle-label">
                            <strong>Autocrear usuario administrador</strong>
                            <small>Al activar esta opción, se usarán los valores por defecto: admin / password</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-shield"></i> Información del Administrador
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="admin_nombre">
                                    <i class="fas fa-user"></i> Nombre
                                </label>
                                <input type="text" id="admin_nombre" class="form-input" value="Administrador" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_apellidos">
                                    <i class="fas fa-user-tag"></i> Apellidos
                                </label>
                                <input type="text" id="admin_apellidos" class="form-input" value="Del Sistema" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_ci">
                                    <i class="fas fa-id-card"></i> Carnet de Identidad
                                </label>
                                <input type="text" id="admin_ci" class="form-input" value="00000000000" required pattern="\d{11}">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_usuario">
                                    <i class="fas fa-user-circle"></i> Nombre de Usuario
                                </label>
                                <input type="text" id="admin_usuario" class="form-input" value="admin" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_email">
                                    <i class="fas fa-envelope"></i> Correo Electrónico
                                </label>
                                <input type="email" id="admin_email" class="form-input" value="admin@ejemplo.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_password">
                                    <i class="fas fa-lock"></i> Contraseña
                                </label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="admin_password" class="form-input" required minlength="8" oninput="checkPasswordStrength(this.value)">
                                    <button type="button" class="password-toggle" onclick="togglePassword('admin_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                                <!-- Medidor de fortaleza de contraseña -->
                                <div class="password-strength" id="passwordStrength">
                                    <div class="strength-meter">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="strength-label">
                                        <span>Fortaleza:</span>
                                        <span class="strength-text" id="strengthText">Muy débil</span>
                                    </div>
                                    <div class="strength-criteria" id="strengthCriteria">
                                        <div class="criteria-item" id="criteriaLength">
                                            <span class="criteria-icon"><i class="fas fa-times"></i></span>
                                            <span>Al menos 8 caracteres</span>
                                        </div>
                                        <div class="criteria-item" id="criteriaLower">
                                            <span class="criteria-icon"><i class="fas fa-times"></i></span>
                                            <span>Letras minúsculas</span>
                                        </div>
                                        <div class="criteria-item" id="criteriaUpper">
                                            <span class="criteria-icon"><i class="fas fa-times"></i></span>
                                            <span>Letras mayúsculas</span>
                                        </div>
                                        <div class="criteria-item" id="criteriaNumber">
                                            <span class="criteria-icon"><i class="fas fa-times"></i></span>
                                            <span>Números</span>
                                        </div>
                                        <div class="criteria-item" id="criteriaSpecial">
                                            <span class="criteria-icon"><i class="fas fa-times"></i></span>
                                            <span>Caracteres especiales</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="admin_password_confirm">
                                    <i class="fas fa-lock"></i> Confirmar Contraseña
                                </label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="admin_password_confirm" class="form-input" required minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('admin_password_confirm')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-match" id="passwordMatch" style="display: none; font-size: 11px; margin-top: 4px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paso 5: Configuración de Datos de la Empresa -->
                <div class="step-content" id="step5">
                    <h2 class="section-title">Configuración de la Empresa</h2>
                    <p class="content-description">Configura los datos de tu empresa o entidad.</p>
					<p class="content-description">POdrás configurarlos luego desde el sistema si lo deseas.</p>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-building"></i> Información General
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="nombre_empresa">
                                    <i class="fas fa-building"></i> Nombre de la Empresa
                                </label>
                                <input type="text" id="nombre_empresa" class="form-input" value="Mi Empresa S.A." required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="nombre_proyecto">
                                    <i class="fas fa-project-diagram"></i> Nombre del Proyecto
                                </label>
                                <input type="text" id="nombre_proyecto" class="form-input" value="Sistema de Facturación" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="direccion">
                                    <i class="fas fa-map-marker-alt"></i> Dirección
                                </label>
                                <textarea id="direccion" class="form-textarea" rows="2" placeholder="Dirección completa de la empresa..." required>Calle Principal #123, Ciudad</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="telefono">
                                    <i class="fas fa-phone"></i> Teléfono
                                </label>
                                <input type="text" id="telefono" class="form-input" value="+53 12345678" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="email">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                <input type="email" id="email" class="form-input" value="info@miempresa.cu" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="sitio_web">
                                    <i class="fas fa-globe"></i> Sitio Web
                                </label>
                                <input type="url" id="sitio_web" class="form-input" placeholder="https://www.ejemplo.com">
                            </div>
<!-- Reemplazar el input type="date" por: -->
<div class="form-group">
    <label class="form-label" for="fecha_inicio_operaciones">
        <i class="fas fa-calendar-alt"></i> Fecha Inicio de Operaciones *
    </label>
    
    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 10px;">
        <div>
            <label style="font-size: 11px; color: var(--text-tertiary); display: block; margin-bottom: 4px;">
                <i class="fas fa-calendar"></i> Mes
            </label>
            <select id="mes_inicio" class="form-select" required>
                <option value="">Seleccionar...</option>
                <option value="1">Enero</option>
                <option value="2">Febrero</option>
                <option value="3">Marzo</option>
                <option value="4">Abril</option>
                <option value="5">Mayo</option>
                <option value="6">Junio</option>
                <option value="7">Julio</option>
                <option value="8">Agosto</option>
                <option value="9">Septiembre</option>
                <option value="10">Octubre</option>
                <option value="11">Noviembre</option>
                <option value="12">Diciembre</option>
            </select>
        </div>
        
        <div>
            <label style="font-size: 11px; color: var(--text-tertiary); display: block; margin-bottom: 4px;">
                <i class="fas fa-calendar"></i> Año
            </label>
            <select id="anio_inicio" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php
                $anio_actual = date('Y');
                for ($i = $anio_actual; $i >= 2010; $i--) {
                    echo "<option value='$i'>$i</option>";
                }
                ?>
            </select>
        </div>
    </div>
    
    <small style="color: var(--text-tertiary); font-size: 11px; margin-top: 4px; display: block;">
        <i class="fas fa-info-circle"></i> La fecha de inicio será: <span id="fecha_seleccionada" style="color: var(--bg-accent); font-weight: bold;">DD/MM/YYYY</span>
    </small>
</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-university"></i> Información Bancaria
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="cod_reeup">
                                    <i class="fas fa-id-card"></i> Código REEUP
                                </label>
                                <input type="text" id="cod_reeup" class="form-input" placeholder="Código REEUP de la empresa">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cod_nit">
                                    <i class="fas fa-id-card"></i> Código NIT
                                </label>
                                <input type="text" id="cod_nit" class="form-input" placeholder="Código Identificación Tributaria">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cuenta_bancaria">
                                    <i class="fas fa-credit-card"></i> Cuenta Bancaria
                                </label>
                                <input type="text" id="cuenta_bancaria" class="form-input" placeholder="Número de cuenta bancaria">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="banco">
                                    <i class="fas fa-university"></i> Banco
                                </label>
                                <input type="text" id="banco" class="form-input" placeholder="Nombre del banco">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="sucursal">
                                    <i class="fas fa-code-branch"></i> Sucursal
                                </label>
                                <input type="text" id="sucursal" class="form-input" placeholder="Sucursal bancaria">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i> Responsables
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="director_nombre">
                                    <i class="fas fa-user-tie"></i> Nombre del Director
                                </label>
                                <input type="text" id="director_nombre" class="form-input" placeholder="Nombre del director">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="director_ci">
                                    <i class="fas fa-id-card"></i> CI del Director
                                </label>
                                <input type="text" id="director_ci" class="form-input" placeholder="Carnet de identidad" pattern="\d{11}">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="director_telefono">
                                    <i class="fas fa-phone"></i> Teléfono del Director
                                </label>
                                <input type="text" id="director_telefono" class="form-input" placeholder="Teléfono del director">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="facturador_nombre">
                                    <i class="fas fa-user"></i> Nombre del Facturador
                                </label>
                                <input type="text" id="facturador_nombre" class="form-input" placeholder="Nombre del facturador">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="facturador_ci">
                                    <i class="fas fa-id-card"></i> CI del Facturador
                                </label>
                                <input type="text" id="facturador_ci" class="form-input" placeholder="Carnet de identidad" pattern="\d{11}">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="facturador_telefono">
                                    <i class="fas fa-phone"></i> Teléfono del Facturador
                                </label>
                                <input type="text" id="facturador_telefono" class="form-input" placeholder="Teléfono del facturador">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paso 6: Configuración del Sistema -->
                <div class="step-content" id="step6">
                    <h2 class="section-title">Configuración del Sistema</h2>
                    <p class="content-description">Configura los parámetros principales del sistema.</p>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-sliders-h"></i> Parámetros Generales
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="nombre_sistema">
                                    <i class="fas fa-cog"></i> Nombre del Sistema
                                </label>
                                <input type="text" id="nombre_sistema" class="form-input" value="Sistema de Facturación" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="version">
                                    <i class="fas fa-code-branch"></i> Versión
                                </label>
                                <input type="text" id="version" class="form-input" value="2.3.3" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="items_por_pagina">
                                    <i class="fas fa-list"></i> Items por Página
                                </label>
                                <input type="number" id="items_por_pagina" class="form-input" value="20" min="5" max="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="timezone">
                                    <i class="fas fa-globe-americas"></i> Zona Horaria
                                </label>
                                <select id="timezone" class="form-select">
                                    <option value="America/Havana">Cuba (Havana)</option>
                                    <option value="America/Mexico_City">México (Ciudad de México)</option>
                                    <option value="America/New_York">USA (New York)</option>
                                    <option value="Europe/Madrid">España (Madrid)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-cogs"></i> Configuraciones Adicionales
                        </h3>
                        
                        <!-- WhatsApp -->
                        <div class="toggle-group" onclick="toggleWhatsApp()">
                            <div class="toggle-wrapper">
                                <input type="checkbox" id="whatsapp_ON" class="toggle-checkbox" checked>
                                <label class="toggle-track"></label>
                            </div>
                            <div class="toggle-label">
                                <strong>Integración con WhatsApp</strong>
                                <small>Habilitar envío de notificaciones por WhatsApp</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="whatsapp_numero">
                                <i class="fab fa-whatsapp"></i> Número de WhatsApp
                            </label>
                            <input type="text" id="whatsapp_numero" class="form-input" placeholder="+53512345678">
                        </div>
                        
                        <!-- Mantenimiento -->
                        <div class="toggle-group" onclick="toggleMaintenance()" style="margin-top: 12px;">
                            <div class="toggle-wrapper">
                                <input type="checkbox" id="modo_mantenimiento" class="toggle-checkbox">
                                <label class="toggle-track"></label>
                            </div>
                            <div class="toggle-label">
                                <strong>Modo Mantenimiento</strong>
                                <small>Activar modo mantenimiento del sistema</small>
                            </div>
                        </div>
						<!-- Restablec Password -->
						<div class="toggle-group" onclick="toggleRestabpw()" style="margin-top: 12px;">
							<div class="toggle-wrapper">
								<input type="checkbox" id="restabpw" class="toggle-checkbox">
								<label class="toggle-track"></label>
							</div>
							<div class="toggle-label">
								<strong>Restablecimiento de Contraseña</strong>
								<small>Permitir restablecimiento de contraseña por email</small>
							</div>
						</div>
                    </div>
                </div>
                
                <!-- Paso 7: Instalación Completa -->
                <div class="step-content" id="step7">
                    <div class="success-screen">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        
                        <h2 class="welcome-title">¡Instalación Completada!</h2>
                        <p class="welcome-description">
                            El sistema de facturación SISFACT-PDL VISIONES se ha instalado correctamente en tu servidor.
                        </p>
                        
                        <div class="credentials-card">
                            <h3><i class="fas fa-key"></i> Credenciales de Acceso (Copie estos datos para poder acceder por primera vez al sistema.)</h3>
                            
                            <div class="credential-row">
                                <div class="credential-label">URL del Sistema:</div>
                                <div class="credential-value" id="system-url"><?php echo dirname($_SERVER['PHP_SELF']); ?></div>
                                <button class="copy-btn" onclick="copyToClipboard('system-url')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                            
                            <div class="credential-row">
                                <div class="credential-label">Usuario:</div>
                                <div class="credential-value" id="final-username"></div>
                                <button class="copy-btn" onclick="copyToClipboard('final-username')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                            
                            <div class="credential-row">
                                <div class="credential-label">Contraseña:</div>
                                <div class="credential-value" id="final-password"></div>
                                <button class="copy-btn" onclick="copyToClipboard('final-password')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                        </div>
                        
                        <div class="warning-card">
                            <h3><i class="fas fa-exclamation-triangle"></i> Importante</h3>
                            <p>Por seguridad, elimina el archivo del instalador después de completar la instalación.</p>
                            <p>Los logs de instalación se han guardado en: <strong>install_logs.txt</strong></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-footer">
                <div class="footer-left">
                    <p>Sistema de Facturación SISFACT-PDL VISIONES © <?php echo date('Y'); ?> - Todos los derechos reservados - Versión del instalador: 2.3.3 | PHP: <?php echo PHP_VERSION; ?></p>
                </div>
                <div class="footer-right">
                    <div class="footer-buttons">
                        <!-- Botones del Paso 1 -->
                        <div id="step1-buttons" style="display: none;">
                            <button class="btn btn-primary" onclick="nextStep()">
                                <i class="fas fa-play-circle"></i> Comenzar Instalación
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 2 -->
                        <div id="step2-buttons" style="display: none;">
                            <button class="btn btn-secondary" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Atrás
                            </button>
                            <button class="btn btn-primary" onclick="checkRequirements()" id="btn-check-req">
                                <i class="fas fa-search"></i> Verificar Requisitos
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 3 -->
                        <div id="step3-buttons" style="display: none;">
                            <button class="btn btn-secondary" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Atrás
                            </button>
                            <button class="btn btn-primary" onclick="testDatabaseConnection()" id="btn-test-db">
                                <i class="fas fa-network-wired"></i> Probar Conexión
                            </button>
                            <button class="btn btn-dark" onclick="createDatabase()" id="btn-create-db" disabled>
                                <i class="fas fa-database"></i> Crear Base de Datos
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 4 -->
                        <div id="step4-buttons" style="display: none;">
                            <button class="btn btn-secondary" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Atrás
                            </button>
                            <button class="btn btn-success" onclick="createAdminUser()" id="btn-create-admin">
                                <i class="fas fa-user-plus"></i> Crear Administrador
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 5 -->
                        <div id="step5-buttons" style="display: none;">
                            <button class="btn btn-secondary" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Atrás
                            </button>
                            <button class="btn btn-success" onclick="saveCompanyConfig()" id="btn-save-company">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 6 -->
                        <div id="step6-buttons" style="display: none;">
                            <button class="btn btn-secondary" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Atrás
                            </button>
                            <button class="btn btn-success" onclick="saveConfig()" id="btn-save-config">
                                <i class="fas fa-check-circle"></i> Completar Instalación
                            </button>
                        </div>
                        
                        <!-- Botones del Paso 7 -->
                        <div id="step7-buttons" style="display: none;">
                            <a href="../index.php" class="btn btn-success" id="btn-go-to-system">
                                <i class="fas fa-rocket"></i> Ir al Sistema
                            </a>
                            <button class="btn btn-danger" onclick="deleteInstaller()">
                                <i class="fas fa-trash"></i> Eliminar Instalador
                            </button>
                        </div>
                    </div>
                    <p><a href="#" onclick="openLogs()"><i class="fas fa-terminal"></i> Ver logs de instalación</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
    <script src="../js/sweetalert211.js"></script>

    <script>
        let currentStep = 1;
        const totalSteps = 7;
        let dbConfig = {};
        let adminConfig = {};
        let companyConfig = {};
        let systemConfig = {};
        let installationData = {};
        let consoleLogs = [];
        let isConsoleOpen = false;
        let canProceedToNextStep = {
            1: true, // Welcome - always proceed
            2: false, // Requirements - need check
            3: false, // Database - need creation
            4: false, // Admin - need creation
            5: false, // Company - need save
            6: false, // System - need save
            7: true // Success - always proceed
        };
        
        // Variables para el sistema de progress bars
        let progressSystem = {
            totalSteps: 10, // Total de pasos para crear la BD
            currentStep: 0,
            tasks: [
                { name: "Conectando al servidor MySQL...", weight: 1 },
                { name: "Eliminando base de datos existente...", weight: 1 },
                { name: "Creando nueva base de datos...", weight: 1 },
                { name: "Creando tabla clasif_cat_de_serv...", weight: 1 },
                { name: "Creando tabla clasif_clientes...", weight: 1 },
                { name: "Creando tabla clasif_rol...", weight: 1 },
                { name: "Creando tabla clasif_serv...", weight: 1 },
                { name: "Creando tabla clasif_usuarios...", weight: 1 },
                { name: "Creando tabla configuracion_sistema...", weight: 1 },
                { name: "Creando tabla historico_operaciones...", weight: 1 },
                { name: "Creando tabla tbl_fact...", weight: 1 },
                { name: "Creando tabla tbl_fact_detalle...", weight: 1 },
                { name: "Creando tabla tbl_planes...", weight: 1 },
                { name: "Creando tabla tipos_pago...", weight: 1 },
                { name: "Insertando datos iniciales...", weight: 2 },
                { name: "Creando archivo de configuración...", weight: 1 }
            ],
            currentTaskIndex: 0,
            taskProgress: 0
        };
        
        // Función para limpiar parámetros generales cuando se completa la creación de BD
        function limpiarParametrosGenerales() {
            // Limpiar todos los campos de configuración de base de datos
            const camposBD = ['db_host', 'db_port', 'db_user', 'db_pass']; //'db_name', no se limpia por si acaso
            camposBD.forEach(id => {
                const campo = document.getElementById(id);
                if (campo && campo.type !== 'password') {
                    campo.value = '';
                } else if (campo && campo.type === 'password') {
                    campo.value = '';
                }
            });
            
            // Limpiar resultados de prueba
            document.getElementById('test-results').innerHTML = '';
            document.getElementById('test-results').style.display = 'none';
            
            // Ocultar barra de progreso
            document.getElementById('databaseProgress').style.display = 'none';
            
            // Deshabilitar botones de base de datos
            document.getElementById('btn-create-db').disabled = true;
            document.getElementById('btn-test-db').disabled = false;
            
            addConsoleLog('Parámetros generales limpiados después de creación exitosa', 'info');
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
            updateNavigation();
            setupToggleSwitches();
            setupPasswordToggles();
            updateNextButtonState();
            updateFooterButtons();
            
            // Deshabilitar botón de crear base de datos inicialmente
            document.getElementById('btn-create-db').disabled = true;
            
            // Verificar coincidencia de contraseñas en tiempo real
            const passwordConfirm = document.getElementById('admin_password_confirm');
            const password = document.getElementById('admin_password');
            
            passwordConfirm.addEventListener('input', function() {
                checkPasswordMatch();
            });
            
            password.addEventListener('input', function() {
                checkPasswordMatch();
            });
            
            // Añadir control de paso a paso para el wizard
            setupWizardControls();
            
            // Añadir logs iniciales a la consola
            addConsoleLog('Instalador iniciado', 'info');
            addConsoleLog('PHP Version: <?php echo PHP_VERSION; ?>', 'info');
            addConsoleLog('Required PHP Version: <?php echo MIN_PHP_VERSION; ?>+', 'info');
			
 // Agregar event listeners para fecha de inicio
    document.getElementById('mes_inicio')?.addEventListener('change', actualizarFechaVisual);
    document.getElementById('anio_inicio')?.addEventListener('change', actualizarFechaVisual);
    
    // Establecer valores por defecto (mes y año actual)
    const hoy = new Date();
    const mesActual = hoy.getMonth() + 1; // 1-12
    const anioActual = hoy.getFullYear();
    
    document.getElementById('mes_inicio').value = mesActual;
    document.getElementById('anio_inicio').value = anioActual;
    
    // Actualizar visualización inicial
    actualizarFechaVisual();
	
        });

        // Configurar controles del wizard
        function setupWizardControls() {
            // Bloquear navegación por clic en items si no está completado
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    const stepNumber = parseInt(this.querySelector('.nav-number').textContent);
                    
                    // Si intenta ir al paso 4 (Administrador) sin haber completado el paso 3 (BD)
                    if (stepNumber === 4 && !canProceedToNextStep[3]) {
                        showSweetAlert('Base de Datos Requerida', 'Debes crear la base de datos antes de configurar el administrador.', 'warning', 'Volver a BD');
                        // Ir al paso 3 automáticamente
                        currentStep = 3;
                        updateProgress();
                        return;
                    }
                    
                    if (stepNumber > currentStep && !canProceedToNextStep[currentStep]) {
                        showSweetAlert('Paso incompleto', 'Debes completar el paso actual antes de avanzar.', 'warning', 'Entendido');
                        return;
                    }
                    
                    currentStep = stepNumber;
                    updateProgress();
                    addConsoleLog(`Navegado al paso ${stepNumber}`, 'info');
                });
            });
        }

        // Configurar los toggle switches
        function setupToggleSwitches() {
            const toggles = document.querySelectorAll('.toggle-checkbox');
            toggles.forEach(toggle => {
                if (toggle.id === 'whatsapp_ON') {
                    toggle.checked = true;
                }
                
                // Actualizar clase inicial
                updateToggleClass(toggle);
                
                toggle.addEventListener('change', function() {
                    updateToggleClass(this);
                    if (this.id === 'autocreate_user') {
                        toggleAutoCreate();
                    }
                });
            });
        }

        // Configurar toggles de contraseñas
        function setupPasswordToggles() {
            // Añadir toggles a los campos de contraseña
            const passwordFields = ['db_pass', 'admin_password', 'admin_password_confirm'];
            passwordFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    // Asegurarse de que el wrapper existe
                    if (!field.parentElement.classList.contains('password-input-wrapper')) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'password-input-wrapper';
                        field.parentNode.insertBefore(wrapper, field);
                        wrapper.appendChild(field);
                        
                        const toggleBtn = document.createElement('button');
                        toggleBtn.type = 'button';
                        toggleBtn.className = 'password-toggle';
                        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                        toggleBtn.onclick = () => togglePassword(fieldId);
                        wrapper.appendChild(toggleBtn);
                    }
                }
            });
        }

        // Actualizar estado del botón siguiente
        function updateNextButtonState() {
            const nextButton = document.getElementById('next-button');
            if (currentStep === totalSteps) {
                nextButton.style.display = 'none';
            } else {
                nextButton.style.display = 'flex';
                nextButton.disabled = !canProceedToNextStep[currentStep];
            }
        }
        
        // Actualizar botones del footer
        function updateFooterButtons() {
            // Ocultar todos los grupos de botones
            document.querySelectorAll('.footer-buttons > div').forEach(el => {
                el.style.display = 'none';
            });
            
            // Mostrar solo los botones del paso actual
            const currentButtons = document.getElementById(`step${currentStep}-buttons`);
            if (currentButtons) {
                currentButtons.style.display = 'flex';
                
                // Si es el paso 2, asegurarse de que los botones sean visibles
                if (currentStep === 2) {
                    currentButtons.style.display = 'flex';
                    currentButtons.style.visibility = 'visible';
                    currentButtons.style.opacity = '1';
                }
            }
        }

        // Función para alternar visibilidad de contraseña
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleBtn = field.parentElement.querySelector('.password-toggle');
            const icon = toggleBtn.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
                addConsoleLog(`Contraseña visible en campo: ${fieldId}`, 'info');
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
                addConsoleLog(`Contraseña oculta en campo: ${fieldId}`, 'info');
            }
        }

        // Actualizar clase del toggle
        function updateToggleClass(toggle) {
            const track = toggle.nextElementSibling;
            if (!track) return;
            
            if (toggle.checked) {
                track.classList.add('checked');
            } else {
                track.classList.remove('checked');
            }
        }

        // Funciones manuales para toggle switches
        function toggleAutoCreateManual() {
            const toggle = document.getElementById('autocreate_user');
            toggle.checked = !toggle.checked;
            updateToggleClass(toggle);
            toggleAutoCreate();
            addConsoleLog(`Autocrear usuario: ${toggle.checked ? 'activado' : 'desactivado'}`, 'info');
        }

        function toggleWhatsApp() {
            const toggle = document.getElementById('whatsapp_ON');
            toggle.checked = !toggle.checked;
            updateToggleClass(toggle);
            addConsoleLog(`WhatsApp: ${toggle.checked ? 'activado' : 'desactivado'}`, 'info');
        }

        function toggleMaintenance() {
            const toggle = document.getElementById('modo_mantenimiento');
            toggle.checked = !toggle.checked;
            updateToggleClass(toggle);
            addConsoleLog(`Modo mantenimiento: ${toggle.checked ? 'activado' : 'desactivado'}`, 'info');
        }

		// Función para toggle del restablecimiento de contraseña
		function toggleRestabpw() {
			const toggle = document.getElementById('restabpw');
			toggle.checked = !toggle.checked;
			updateToggleClass(toggle);
			addConsoleLog(`Restablecimiento de contraseña: ${toggle.checked ? 'activado' : 'desactivado'}`, 'info');
		}

        // Función para actualizar el progreso
        function updateProgress() {
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progress-fill').style.width = `${progress}%`;
            document.getElementById('progress-percent').textContent = `${Math.round(progress)}%`;
            
            // Actualizar navegación
            updateNavigation();
            
            // Actualizar botón siguiente
            updateNextButtonState();
            
            // Actualizar botones del footer
            updateFooterButtons();
        }
        
        // Función para actualizar navegación
        function updateNavigation() {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach((item, index) => {
                const stepNumber = index + 1;
                
                item.classList.remove('active', 'completed');
                
                if (stepNumber === currentStep) {
                    item.classList.add('active');
                } else if (stepNumber < currentStep) {
                    item.classList.add('completed');
                }
            });
            
            // Mostrar solo el paso actual
            document.querySelectorAll('.step-content').forEach(step => {
                step.classList.remove('active');
            });
            document.getElementById(`step${currentStep}`).classList.add('active');
        }
        
        // Función para ir a un paso específico
        function goToStep(step) {
            if (step >= 1 && step <= totalSteps) {
                // Si intenta ir al paso 4 (Administrador) sin haber completado el paso 3 (BD)
                if (step === 4 && !canProceedToNextStep[3]) {
                    showSweetAlert('Base de Datos Requerida', 'Debes crear la base de datos antes de configurar el administrador.', 'warning', 'Volver a BD');
                    // Ir al paso 3 automáticamente
                    step = 3;
                }
                
                if (step > currentStep && !canProceedToNextStep[currentStep]) {
                    showSweetAlert('Paso incompleto', 'Debes completar el paso actual antes de avanzar.', 'warning', 'Entendido');
                    return;
                }
                currentStep = step;
                updateProgress();
                addConsoleLog(`Navegado al paso ${step}`, 'info');
            }
        }
        
        // Función para avanzar al siguiente paso
        function nextStep() {
            if (currentStep < totalSteps) {
                if (!canProceedToNextStep[currentStep]) {
                    showSweetAlert('Paso incompleto', 'Debes completar el paso actual antes de avanzar.', 'warning', 'Entendido');
                    return;
                }
                currentStep++;
                updateProgress();
                addConsoleLog(`Avanzado al paso ${currentStep}`, 'info');
            }
        }
        
        // Función para retroceder al paso anterior
        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                updateProgress();
                addConsoleLog(`Retrocedido al paso ${currentStep}`, 'info');
            }
        }
        
        // Función para mostrar SweetAlert con iconos fontawesome
        function showSweetAlert(title, text, icon = 'info', confirmText = 'Aceptar', cancelText = 'Cancelar', showCancel = false) {
            // Mapear iconos fontawesome según el tipo
            const iconMap = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-times-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle',
                'question': 'fas fa-question-circle'
            };
            
            addConsoleLog(`Alert: ${title} - ${text}`, icon);
            
            return Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: showCancel,
                confirmButtonText: `<i class="fas fa-check"></i> ${confirmText}`,
                cancelButtonText: `<i class="fas fa-times"></i> ${cancelText}`,
                background: '#171717',
                color: '#ffffff',
                confirmButtonColor: '#0078d4',
                cancelButtonColor: '#d13438',
                customClass: {
                    confirmButton: 'swal-confirm-button',
                    cancelButton: 'swal-cancel-button'
                }
            });
        }
        
        // Función para alternar autocreación de usuario
        function toggleAutoCreate() {
            const autoCreate = document.getElementById('autocreate_user').checked;
            const fields = [
                'admin_nombre', 'admin_apellidos', 'admin_ci',
                'admin_usuario', 'admin_email', 'admin_password', 'admin_password_confirm'
            ];
            
            if (autoCreate) {
                // Establecer valores por defecto
                document.getElementById('admin_nombre').value = 'Admin';
                document.getElementById('admin_apellidos').value = 'Sistema';
                document.getElementById('admin_ci').value = '00000000000';
                document.getElementById('admin_usuario').value = 'admin';
                document.getElementById('admin_email').value = 'admin@localhost';
                document.getElementById('admin_password').value = 'password';
                document.getElementById('admin_password_confirm').value = 'password';
                
                // Ocultar medidor de fortaleza
                document.getElementById('passwordStrength').classList.remove('active');
                
                // Actualizar contraseñas
                checkPasswordStrength('password');
                checkPasswordMatch();
                
                addConsoleLog('Modo autocreación activado - Valores por defecto establecidos', 'info');
            } else {
                // Restaurar valores originales
                document.getElementById('admin_nombre').value = 'Administrador';
                document.getElementById('admin_apellidos').value = 'Del Sistema';
                document.getElementById('admin_ci').value = '00000000000';
                document.getElementById('admin_usuario').value = 'admin';
                document.getElementById('admin_email').value = 'admin@ejemplo.com';
                document.getElementById('admin_password').value = '';
                document.getElementById('admin_password_confirm').value = '';
                
                // Mostrar medidor de fortaleza
                document.getElementById('passwordStrength').classList.add('active');
                
                addConsoleLog('Modo autocreación desactivado - Campos habilitados para entrada manual', 'info');
            }
            
            // Habilitar/deshabilitar campos
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.disabled = autoCreate;
                field.style.backgroundColor = autoCreate ? 'rgba(255, 255, 255, 0.05)' : '';
            });
            
            if (!autoCreate) {
                document.getElementById('admin_password').focus();
            }
            
            // Actualizar fortaleza de contraseña
            if (!autoCreate) {
                checkPasswordStrength(document.getElementById('admin_password').value);
            }
        }
        
        // Función para verificar fortaleza de contraseña
        function checkPasswordStrength(password) {
            const strengthMeter = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Mostrar medidor solo si hay contraseña
            if (password.length > 0) {
                strengthMeter.classList.add('active');
            } else {
                strengthMeter.classList.remove('active');
                return;
            }
            
            // Calcular puntuación
            let score = 0;
            const criteria = {
                length: password.length >= 8,
                lower: /[a-z]/.test(password),
                upper: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };
            
            // Actualizar criterios visualmente
            Object.keys(criteria).forEach(key => {
                const element = document.getElementById(`criteria${key.charAt(0).toUpperCase() + key.slice(1)}`);
                const icon = element.querySelector('.criteria-icon i');
                
                if (criteria[key]) {
                    element.classList.add('met');
                    icon.className = 'fas fa-check';
                    score++;
                } else {
                    element.classList.remove('met');
                    icon.className = 'fas fa-times';
                }
            });
            
            // Determinar fortaleza basada en puntuación
            let strength = 'weak';
            let strengthClass = 'weak';
            let fillWidth = '25%';
            
            if (score === 5) {
                strength = 'Muy fuerte';
                strengthClass = 'strong';
                fillWidth = '100%';
            } else if (score >= 4) {
                strength = 'Fuerte';
                strengthClass = 'good';
                fillWidth = '75%';
            } else if (score >= 3) {
                strength = 'Media';
                strengthClass = 'medium';
                fillWidth = '50%';
            } else if (score >= 2) {
                strength = 'Débil';
                strengthClass = 'weak';
                fillWidth = '25%';
            } else {
                strength = 'Muy débil';
                strengthClass = 'weak';
                fillWidth = '25%';
            }
            
            // Actualizar UI
            strengthFill.className = `strength-fill ${strengthClass}`;
            strengthFill.style.width = fillWidth;
            strengthText.textContent = strength;
            strengthText.className = `strength-text ${strengthClass}`;
        }
        
        // Función para verificar coincidencia de contraseñas
        function checkPasswordMatch() {
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchDiv.style.display = 'none';
                return;
            }
            
            matchDiv.style.display = 'block';
            
            if (password === confirm) {
                matchDiv.innerHTML = '<span style="color: var(--bg-success)"><i class="fas fa-check"></i> Las contraseñas coinciden</span>';
                matchDiv.style.color = 'var(--bg-success)';
            } else {
                matchDiv.innerHTML = '<span style="color: var(--bg-error)"><i class="fas fa-times"></i> Las contraseñas no coinciden</span>';
                matchDiv.style.color = 'var(--bg-error)';
            }
        }
        
        // Función para mostrar el modal de requisitos
        function showRequirementsModal() {
            const modal = document.getElementById('requirementsModal');
            modal.classList.add('active');
            
            // Cargar los requisitos en el modal
            const requirementsBody = document.getElementById('requirementsBody');
            const requirementsDiv = document.getElementById('requirements-list');
            
            // Clonar los requisitos verificados al modal
            requirementsBody.innerHTML = requirementsDiv.innerHTML;
            
            addConsoleLog('Modal de requisitos abierto', 'info');
        }

        // Función para cerrar el modal de requisitos
        function closeRequirementsModal() {
            const modal = document.getElementById('requirementsModal');
            modal.classList.remove('active');
            addConsoleLog('Modal de requisitos cerrado', 'info');
        }

        // Función para cerrar el modal y continuar
        function closeRequirementsModalAndContinue() {
            closeRequirementsModal();
            canProceedToNextStep[2] = true;
            updateNextButtonState();
            addConsoleLog('Continuando al siguiente paso después de ver requisitos', 'info');
        }
        
        // Función para verificar requisitos
        function checkRequirements() {
            addConsoleLog('Iniciando verificación de requisitos...', 'info');
            
            const requirementsDiv = document.getElementById('requirements-list');
            requirementsDiv.innerHTML = '<div class="loading"><span class="loading-spinner"></span> Verificando requisitos del sistema...</div>';
            
            const checkBtn = document.getElementById('btn-check-req-step2');
            const showBtn = document.getElementById('btn-show-reqs');
            const originalText = checkBtn.innerHTML;
            checkBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Verificando...</span>';
            checkBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_requirements'
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                    const data = JSON.parse(cleanText);
                    displayRequirements(data);
                    
                    if (data.success && data.requirements.every(req => req.passed)) {
                        // Mostrar el botón para ver requisitos
                        showBtn.style.display = 'inline-flex';
                        checkBtn.style.display = 'none';
                        
                        // Mostrar SweetAlert con opción de ver detalles
                        showSweetAlert(
                            'Requisitos Completos', 
                            'Todos los requisitos del sistema se cumplen correctamente. ¿Deseas ver los detalles antes de continuar?',
                            'success',
                            'Ver Detalles',
                            'Continuar sin ver',
                            true
                        ).then((result) => {
                            if (result.isConfirmed) {
                                // Mostrar modal con los requisitos
                                showRequirementsModal();
                            } else {
                                // Continuar sin ver los detalles
                                canProceedToNextStep[2] = true;
                                updateNextButtonState();
                            }
                        });
                        
                        addConsoleLog('Todos los requisitos cumplidos', 'success');
                    } else if (!data.success) {
                        showSweetAlert('Error', 'Error al verificar requisitos: ' + (data.error || 'Error desconocido'), 'error', 'Reintentar');
                        addConsoleLog('Error al verificar requisitos', 'error');
                        checkBtn.disabled = false;
                        checkBtn.innerHTML = originalText;
                    } else {
                        const failed = data.requirements.filter(req => !req.passed).length;
                        // Mostrar el botón para ver requisitos (incluyendo los fallados)
                        showBtn.style.display = 'inline-flex';
                        checkBtn.style.display = 'none';
                        
                        showSweetAlert(
                            'Requisitos Incompletos', 
                            `${failed} requisitos no se cumplen. Es necesario revisarlos antes de continuar.`,
                            'warning',
                            'Ver Detalles',
                            'Entendido',
                            true
                        ).then((result) => {
                            if (result.isConfirmed) {
                                // Mostrar modal con los requisitos (incluyendo errores)
                                showRequirementsModal();
                            }
                        });
                        
                        addConsoleLog(`${failed} requisitos no cumplidos`, 'warning');
                        checkBtn.disabled = false;
                        checkBtn.innerHTML = originalText;
                    }
                } catch (e) {
                    console.error('Error parseando JSON:', e);
                    showSweetAlert('Error', 'Error en la respuesta del servidor. Formato JSON inválido.', 'error', 'Reintentar');
                    addConsoleLog('Error parseando respuesta JSON', 'error');
                    checkBtn.disabled = false;
                    checkBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showSweetAlert('Error de Conexión', 'Error de conexión con el servidor.', 'error', 'Reintentar');
                addConsoleLog('Error de conexión con el servidor', 'error');
                checkBtn.disabled = false;
                checkBtn.innerHTML = originalText;
            });
        }
        
        // Función para mostrar los requisitos
        function displayRequirements(data) {
            const requirementsDiv = document.getElementById('requirements-list');
            requirementsDiv.innerHTML = '';
            
            if (!data.success || !data.requirements) {
                requirementsDiv.innerHTML = '<div class="alert alert-error">Error al obtener requisitos</div>';
                return;
            }
            
            data.requirements.forEach(req => {
                const requirementDiv = document.createElement('div');
                requirementDiv.className = `requirement-card ${req.passed ? 'success' : 'error'}`;
                
                requirementDiv.innerHTML = `
                    <div class="requirement-icon">
                        <i class="fas fa-${req.passed ? 'check' : 'times'}"></i>
                    </div>
                    <div class="requirement-info">
                        <div class="requirement-name">${req.name}</div>
                        <div class="requirement-status">${req.message}</div>
                    </div>
                `;
                
                requirementsDiv.appendChild(requirementDiv);
            });
        }
        
        // Función para inicializar el sistema de progress bars
        function initializeProgressSystem() {
            const progressSystem = document.getElementById('progressSystem');
            progressSystem.style.display = 'block';
            
            // Resetear progress bars
            document.getElementById('total-progress-fill').style.width = '0%';
            document.getElementById('total-progress-percent').textContent = '0%';
            document.getElementById('task-progress-fill').style.width = '0%';
            document.getElementById('task-progress-percent').textContent = '0%';
            document.getElementById('current-task').textContent = 'Preparando sistema...';
            
            // Limpiar detalles de ejecución
            document.getElementById('details-content').innerHTML = `
                <div class="log-entry info">
                    <span class="log-time">${new Date().toLocaleTimeString()}</span> Inicializando creación de base de datos...
                </div>
            `;
        }

// Función para actualizar el progreso total (con límites)
function updateTotalProgress(percent) {
    const totalProgressFill = document.getElementById('total-progress-fill');
    const totalProgressPercent = document.getElementById('total-progress-percent');
    
    // LIMITAR A MÁXIMO 100%
    percent = Math.min(percent, 100);
    
    totalProgressFill.style.width = `${percent}%`;
    totalProgressPercent.textContent = `${Math.round(percent)}%`;
    
    // Añadir animación solo si está en progreso (entre 0 y 100 exclusivo)
    if (percent > 0 && percent < 100) {
        totalProgressFill.classList.add('active');
    } else {
        totalProgressFill.classList.remove('active');
    }
}

// Función para actualizar el progreso de la tarea actual (con límites)
function updateTaskProgress(percent, taskName) {
    const taskProgressFill = document.getElementById('task-progress-fill');
    const taskProgressPercent = document.getElementById('task-progress-percent');
    const currentTask = document.getElementById('current-task');
    
    // LIMITAR A MÁXIMO 100%
    percent = Math.min(percent, 100);
    
    taskProgressFill.style.width = `${percent}%`;
    taskProgressPercent.textContent = `${Math.round(percent)}%`;
    
    // Actualizar nombre de tarea
    if (taskName) {
        currentTask.textContent = taskName;
    }
    
    // Añadir animación solo si está en progreso (entre 0 y 100 exclusivo)
    if (percent > 0 && percent < 100) {
        taskProgressFill.classList.add('active');
    } else {
        taskProgressFill.classList.remove('active');
    }
}

// Función para añadir un paso a los detalles de ejecución
function addExecutionStep(message, type = 'info') {
    const detailsContent = document.getElementById('details-content');
    const timestamp = new Date().toLocaleTimeString();
    
    const stepDiv = document.createElement('div');
    stepDiv.className = `execution-step ${type}`;
    stepDiv.innerHTML = `<span class="log-time">${timestamp}</span> ${message}`;
    
    detailsContent.appendChild(stepDiv);
    
    // Scroll al final
    const detailsContainer = document.getElementById('execution-details');
    detailsContainer.scrollTop = detailsContainer.scrollHeight;
    
    // También añadir a la consola
    addConsoleLog(message, type);
}
        
        
        // Función para probar conexión a la base de datos
        function testDatabaseConnection() {
            addConsoleLog('Probando conexión a base de datos...', 'info');
            
            // Obtener datos del formulario
            dbConfig = {
                db_host: document.getElementById('db_host').value,
                db_port: document.getElementById('db_port').value,
                db_name: document.getElementById('db_name').value,
                db_user: document.getElementById('db_user').value,
                db_pass: document.getElementById('db_pass').value,
                db_prefix: '' // Prefijo vacío por ahora
            };
            
            // Validar campos requeridos
            if (!dbConfig.db_host || !dbConfig.db_name || !dbConfig.db_user) {
                showSweetAlert('Campos Requeridos', 'Por favor, completa los campos requeridos.', 'warning', 'Entendido');
                return;
            }
            
            addConsoleLog(`Intentando conexión a: ${dbConfig.db_host}:${dbConfig.db_port}`, 'info');
            
            // Deshabilitar botón de crear base de datos mientras se prueba
            document.getElementById('btn-create-db').disabled = true;
            
            // Mostrar animación de carga
            const testBtn = document.getElementById('btn-test-db');
            const originalText = testBtn.innerHTML;
            testBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Probando conexión...</span>';
            testBtn.disabled = true;
            
            // Enviar petición AJAX
            const formData = new URLSearchParams();
            formData.append('action', 'test_connection');
            Object.keys(dbConfig).forEach(key => {
                formData.append(key, dbConfig[key]);
            });
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                    const data = JSON.parse(cleanText);
                    const resultsDiv = document.getElementById('test-results');
                    resultsDiv.style.display = 'block';
                    resultsDiv.innerHTML = '';
                    
                    if (data.success) {
                        showSweetAlert('Conexión Exitosa', 'Conexión a la base de datos establecida correctamente.', 'success', 'Continuar');
                        addConsoleLog('Conexión a base de datos exitosa', 'success');
                        
                        data.tests.forEach(test => {
                            const testDiv = document.createElement('div');
                            testDiv.className = `test-result ${test.passed ? 'success' : 'error'}`;
                            
                            testDiv.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="test-result-icon" style="width: 24px; height: 24px; border-radius: 50%; background: ${test.passed ? 'rgba(16, 124, 16, 0.2)' : 'rgba(209, 52, 56, 0.2)'}; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-${test.passed ? 'check' : 'times'}" style="font-size: 12px; color: ${test.passed ? '#107c10' : '#d13438'}"></i>
                                    </div>
                                    <div>${test.message}</div>
                                </div>
                                <div style="color: ${test.passed ? '#107c10' : '#d13438'}"><i class="fas fa-${test.passed ? 'check' : 'times'}"></i></div>
                            `;
                            
                            resultsDiv.appendChild(testDiv);
                        });
                        
                        // IMPORTANTE: Solo habilitar botón para crear base de datos si TODAS las pruebas pasaron
                        const allTestsPassed = data.tests.every(test => test.passed);
                        if (allTestsPassed) {
                            document.getElementById('btn-create-db').disabled = false;
                            // CAMBIA LA CLASE PARA QUE SEA VERDE
                            document.getElementById('btn-create-db').className = 'btn btn-success';
                            addConsoleLog('Conexión OK - Botón de creación de base de datos habilitado', 'success');
                        } else {
                            document.getElementById('btn-create-db').disabled = true;
                            document.getElementById('btn-create-db').className = 'btn btn-dark';
                            addConsoleLog('Conexión parcial - No se puede crear base de datos', 'warning');
                            showSweetAlert('Pruebas Fallidas', 'No todas las pruebas de conexión fueron exitosas. Revisa los resultados.', 'warning', 'Entendido');
                        }
                        
                    } else {
                        showSweetAlert('Error de Conexión', `Error: ${data.error}`, 'error', 'Reintentar');
                        addConsoleLog(`Error de conexión: ${data.error}`, 'error');
                        
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'test-result error';
                        errorDiv.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="test-result-icon" style="width: 24px; height: 24px; border-radius: 50%; background: rgba(209, 52, 56, 0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-times" style="font-size: 12px; color: #d13438"></i>
                                </div>
                                <div>${data.error}</div>
                            </div>
                            <div style="color: #d13438"><i class="fas fa-times"></i></div>
                        `;
                        
                        resultsDiv.appendChild(errorDiv);
                        
                        // Asegurarse de que el botón esté deshabilitado
                        document.getElementById('btn-create-db').disabled = true;
                        document.getElementById('btn-create-db').className = 'btn btn-dark';
                    }
                } catch (e) {
                    console.error('Error parseando JSON:', e);
                    showSweetAlert('Error', 'Error en la respuesta del servidor.', 'error', 'Reintentar');
                    addConsoleLog('Error parseando respuesta JSON', 'error');
                    document.getElementById('btn-create-db').disabled = true;
                    document.getElementById('btn-create-db').className = 'btn btn-dark';
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showSweetAlert('Error', 'Error en la comunicación con el servidor.', 'error', 'Reintentar');
                addConsoleLog('Error de red en prueba de conexión', 'error');
                document.getElementById('btn-create-db').disabled = true;
                document.getElementById('btn-create-db').className = 'btn btn-dark';
            })
            .finally(() => {
                testBtn.innerHTML = originalText;
                testBtn.disabled = false;
            });
        }
        


// Función para crear la base de datos (versión con progreso controlado)
function createDatabase() {
    showSweetAlert(
        'Confirmación',
        '¿Deseas crear la base de datos? Si ya existe, será eliminada y recreada.',
        'warning',
        'Sí, Continuar',
        'Cancelar',
        true
    ).then((result) => {
        if (result.isConfirmed) {
            // Inicializar sistema de progress bars
            initializeProgressSystem();
            
            addConsoleLog('Creando base de datos...', 'info');
            addExecutionStep('Iniciando proceso de creación de base de datos...', 'info');
            
            const createBtn = document.getElementById('btn-create-db');
            const originalText = createBtn.innerHTML;
            createBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Creando base de datos...</span>';
            createBtn.disabled = true;
            
            // Deshabilitar también el botón de probar conexión durante la creación
            document.getElementById('btn-test-db').disabled = true;
            
            // Ocultar resultados anteriores
            document.getElementById('test-results').style.display = 'none';
            
            // Estado inicial del progreso
            updateTotalProgress(0);
            updateTaskProgress(0, 'Preparando creación de base de datos...');
            
            // Variables para controlar el progreso
            let progressPolling;
            let progressCounter = 0;
            let taskProgressCounter = 0;
            let isComplete = false;
            let currentTaskName = 'Preparando creación de base de datos...';
            
            // Función para actualizar progreso de manera controlada
            function updateControlledProgress(totalPercent, taskPercent, taskName) {
                // Limitar a máximo 100%
                totalPercent = Math.min(totalPercent, 100);
                taskPercent = Math.min(taskPercent, 100);
                
                updateTotalProgress(totalPercent);
                updateTaskProgress(taskPercent, taskName);
                
                // Detener animación cuando llegue al 100%
                if (totalPercent >= 100) {
                    const totalProgressFill = document.getElementById('total-progress-fill');
                    const taskProgressFill = document.getElementById('task-progress-fill');
                    totalProgressFill.classList.remove('active');
                    taskProgressFill.classList.remove('active');
                }
            }
            
            // Iniciar polling de progreso controlado
            function startControlledProgressPolling() {
                progressPolling = setInterval(() => {
                    if (!isComplete) {
                        // Solo aumentar el progreso si no está completo
                        if (progressCounter < 90) { // Dejar 10% para la finalización real
                            progressCounter += Math.random() * 5 + 1; // Incremento aleatorio entre 1-6%
                            taskProgressCounter += Math.random() * 8 + 2; // Incremento aleatorio entre 2-10%
                            
                            // Limitar los contadores
                            progressCounter = Math.min(progressCounter, 90);
                            taskProgressCounter = Math.min(taskProgressCounter, 100);
                            
                            // Actualizar progreso
                            updateControlledProgress(progressCounter, taskProgressCounter, currentTaskName);
                            
                            // Rotar mensajes de tarea según el progreso
                            if (progressCounter < 30) {
                                currentTaskName = 'Conectando al servidor MySQL...';
                            } else if (progressCounter < 60) {
                                currentTaskName = 'Creando estructura de base de datos...';
                            } else {
                                currentTaskName = 'Creando tablas y datos iniciales...';
                            }
                        }
                    }
                }, 500); // Actualizar cada 500ms
            }
            
            // Función para completar el progreso
            function completeProgress() {
                isComplete = true;
                clearInterval(progressPolling);
                
                // Asegurar que se muestre 100%
                updateControlledProgress(100, 100, '¡Base de datos creada exitosamente!');
                
                // Añadir paso final
                addExecutionStep('Base de datos creada exitosamente', 'success');
                
                // Mostrar SweetAlert después de completar
                setTimeout(() => {
                    showSweetAlert(
                        '¡Base de Datos Creada!', 
                        'Base de datos y tablas creadas exitosamente.',
                        'success',
                        'Continuar'
                    ).then(() => {
                        // Habilitar siguiente paso
                        canProceedToNextStep[3] = true;
                        updateNextButtonState();
                        
                        // Limpiar parámetros generales
                        limpiarParametrosGenerales();
                        
                        // Avanzar automáticamente después de 2 segundos
                        setTimeout(() => {
                            nextStep();
                        }, 2000);
                    });
                }, 1000);
            }
            
            // Función para manejar error
            function handleProgressError(errorMessage) {
                isComplete = true;
                clearInterval(progressPolling);
                
                updateControlledProgress(progressCounter, 0, 'Error en la creación');
                addExecutionStep(`Error: ${errorMessage}`, 'error');
                
                showSweetAlert('Error', `Error: ${errorMessage}`, 'error', 'Reintentar');
                createBtn.disabled = false;
                document.getElementById('btn-test-db').disabled = false;
                createBtn.innerHTML = originalText;
            }
            
            // Iniciar polling controlado
            startControlledProgressPolling();
            
            // Enviar petición real al servidor
            sendRealDatabaseCreationRequest();
            
            // Función para enviar la petición real al servidor
            function sendRealDatabaseCreationRequest() {
                addExecutionStep('Conectando al servidor para crear base de datos...', 'info');
                
                const formData = new URLSearchParams();
                formData.append('action', 'create_database');
                Object.keys(dbConfig).forEach(key => {
                    formData.append(key, dbConfig[key]);
                });
                
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                })
                .then(response => {
                    // Detener el polling cuando la respuesta llega
                    isComplete = true;
                    clearInterval(progressPolling);
                    
                    return response.text();
                })
                .then(text => {
                    try {
                        const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                        const data = JSON.parse(cleanText);
                        
                        if (data.success) {
                            // Completar progreso exitosamente
                            completeProgress();
                            
                            if (data.tables && Array.isArray(data.tables)) {
                                addExecutionStep(`Total de tablas creadas: ${data.tables.length}`, 'success');
                                
                                // Mostrar cada tabla creada
                                data.tables.forEach((table, index) => {
                                    setTimeout(() => {
                                        addExecutionStep(`Tabla creada: ${table.name}`, 'success');
                                    }, index * 100);
                                });
                            }
                        } else {
                            handleProgressError(data.error);
                        }
                    } catch (e) {
                        console.error('Error parseando JSON:', e);
                        handleProgressError('Error en la respuesta del servidor');
                    }
                })
                .catch(error => {
                    console.error('Error de red:', error);
                    handleProgressError('Error de conexión con el servidor');
                })
                .finally(() => {
                    createBtn.innerHTML = originalText;
                });
            }
        }
    });
}
		
		
		
		// Función para crear usuario administrador
        function createAdminUser() {
            addConsoleLog('Creando usuario administrador...', 'info');
            
            // Validar contraseñas
            const password = document.getElementById('admin_password').value;
            const passwordConfirm = document.getElementById('admin_password_confirm').value;
            
            if (password.length < 8) {
                showSweetAlert('Contraseña Corta', 'La contraseña debe tener al menos 8 caracteres.', 'warning', 'Corregir');
                addConsoleLog('Contraseña muy corta', 'warning');
                return;
            }
            
            if (password !== passwordConfirm) {
                showSweetAlert('Contraseñas No Coinciden', 'Las contraseñas no coinciden.', 'warning', 'Corregir');
                addConsoleLog('Las contraseñas no coinciden', 'warning');
                return;
            }
            
            // Obtener datos del formulario
            adminConfig = {
                admin_nombre: document.getElementById('admin_nombre').value,
                admin_apellidos: document.getElementById('admin_apellidos').value,
                admin_ci: document.getElementById('admin_ci').value,
                admin_usuario: document.getElementById('admin_usuario').value,
                admin_email: document.getElementById('admin_email').value,
                admin_password: password
            };
            
            // Validar CI
            if (!/^\d{11}$/.test(adminConfig.admin_ci)) {
                showSweetAlert('CI Inválido', 'El carnet de identidad debe tener 11 dígitos.', 'warning', 'Corregir');
                addConsoleLog('CI inválido', 'warning');
                return;
            }
            
            const createBtn = document.getElementById('btn-create-admin');
            const originalText = createBtn.innerHTML;
            createBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Creando usuario...</span>';
            createBtn.disabled = true;
            
            // Combinar configuraciones
            const allConfig = { ...dbConfig, ...adminConfig };
            const formData = new URLSearchParams();
            formData.append('action', 'create_admin');
            Object.keys(allConfig).forEach(key => {
                formData.append(key, allConfig[key]);
            });
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                    const data = JSON.parse(cleanText);
                    if (data.success) {
                        showSweetAlert('Usuario Creado', 'Usuario administrador creado exitosamente.', 'success', 'Continuar');
                        addConsoleLog('Usuario administrador creado exitosamente', 'success');
                        
                        // Guardar datos para mostrar en el último paso
                        installationData = {
                            username: adminConfig.admin_usuario,
                            password: password
                        };
                        
                        // Mostrar credenciales
                        document.getElementById('final-username').textContent = installationData.username;
                        document.getElementById('final-password').textContent = password;
                        
                        // Permitir avanzar al siguiente paso
                        canProceedToNextStep[4] = true;
                        updateNextButtonState();
                        
                        // Avanzar al siguiente paso
                        setTimeout(() => {
                            nextStep();
                        }, 1500);
                        
                    } else {
                        showSweetAlert('Error', `Error: ${data.error}`, 'error', 'Reintentar');
                        addConsoleLog(`Error al crear usuario: ${data.error}`, 'error');
                        createBtn.disabled = false;
                    }
                } catch (e) {
                    console.error('Error parseando JSON:', e);
                    showSweetAlert('Error', 'Error en la respuesta del servidor.', 'error', 'Reintentar');
                    addConsoleLog('Error parseando respuesta JSON', 'error');
                    createBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showSweetAlert('Error', 'Error en la comunicación con el servidor.', 'error', 'Reintentar');
                addConsoleLog('Error de red al crear usuario', 'error');
                createBtn.disabled = false;
            })
            .finally(() => {
                createBtn.innerHTML = originalText;
            });
        }
        
        
		
// Función para guardar configuración de empresa
function saveCompanyConfig() {
    addConsoleLog('Guardando configuración de empresa...', 'info');
    
    // Obtener mes y año seleccionados
    const mesInicio = document.getElementById('mes_inicio').value;
    const anioInicio = document.getElementById('anio_inicio').value;
    
    // Construir fecha como primer día del mes: YYYY-MM-01
    let fechaInicioOperaciones = '';
    if (mesInicio && anioInicio) {
        const mesFormateado = mesInicio.padStart(2, '0');
        fechaInicioOperaciones = `${anioInicio}-${mesFormateado}-01`;
    }
    
    // Obtener datos del formulario
    companyConfig = {
        nombre_empresa: document.getElementById('nombre_empresa').value,
        nombre_proyecto: document.getElementById('nombre_proyecto').value,
        direccion: document.getElementById('direccion').value,
        telefono: document.getElementById('telefono').value,
        email: document.getElementById('email').value,
        sitio_web: document.getElementById('sitio_web').value,
        cod_reeup: document.getElementById('cod_reeup').value,
        cod_nit: document.getElementById('cod_nit').value,
        cuenta_bancaria: document.getElementById('cuenta_bancaria').value,
        banco: document.getElementById('banco').value,
        sucursal: document.getElementById('sucursal').value,
        fecha_inicio_operaciones: fechaInicioOperaciones,
        director_nombre: document.getElementById('director_nombre').value,
        director_ci: document.getElementById('director_ci').value,
        director_telefono: document.getElementById('director_telefono').value,
        facturador_nombre: document.getElementById('facturador_nombre').value,
        facturador_ci: document.getElementById('facturador_ci').value,
        facturador_telefono: document.getElementById('facturador_telefono').value
    };
    
    // ============================================
    // VALIDACIONES COMPLETAS
    // ============================================
    
    // 1. Validar campos obligatorios
    const camposObligatorios = [
        { campo: 'nombre_empresa', nombre: 'Nombre de la Empresa' },
        { campo: 'nombre_proyecto', nombre: 'Nombre del Proyecto' },
        { campo: 'direccion', nombre: 'Dirección' },
        { campo: 'telefono', nombre: 'Teléfono' },
        { campo: 'email', nombre: 'Email' }
    ];
    
    for (const item of camposObligatorios) {
        if (!companyConfig[item.campo] || companyConfig[item.campo].trim() === '') {
            showSweetAlert('Campo Requerido', `${item.nombre} es obligatorio.`, 'warning', 'Corregir');
            addConsoleLog(`${item.nombre} es requerido`, 'warning');
            return;
        }
    }
    
    // 2. Validar fecha de inicio (mes y año obligatorios)
    if (!mesInicio || !anioInicio) {
        showSweetAlert('Fecha Requerida', 'Debe seleccionar mes y año de inicio de operaciones.', 'warning', 'Corregir');
        addConsoleLog('Mes o año de inicio no seleccionado', 'warning');
        return;
    }
    
    // 3. Validar formato de email
    if (companyConfig.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(companyConfig.email)) {
        showSweetAlert('Email Inválido', 'El formato del email no es válido.', 'warning', 'Corregir');
        addConsoleLog('Email con formato inválido', 'warning');
        return;
    }
    
    // 4. Validar formato de sitio web (si está presente)
    if (companyConfig.sitio_web && companyConfig.sitio_web.trim() !== '') {
        if (!/^(https?:\/\/)?([\w-]+\.)+[\w-]+(\/[\w- .\/?%&=]*)?$/.test(companyConfig.sitio_web)) {
            showSweetAlert('Sitio Web Inválido', 'El formato del sitio web no es válido.', 'warning', 'Corregir');
            addConsoleLog('Sitio web con formato inválido', 'warning');
            return;
        }
    }
    
    // 5. Validar CI del director si está presente
    if (companyConfig.director_ci && companyConfig.director_ci.trim() !== '') {
        if (!/^\d{11}$/.test(companyConfig.director_ci)) {
            showSweetAlert('CI Inválido', 'El carnet de identidad del director debe tener 11 dígitos.', 'warning', 'Corregir');
            addConsoleLog('CI del director inválido', 'warning');
            return;
        }
    }
    
    // 6. Validar CI del facturador si está presente
    if (companyConfig.facturador_ci && companyConfig.facturador_ci.trim() !== '') {
        if (!/^\d{11}$/.test(companyConfig.facturador_ci)) {
            showSweetAlert('CI Inválido', 'El carnet de identidad del facturador debe tener 11 dígitos.', 'warning', 'Corregir');
            addConsoleLog('CI del facturador inválido', 'warning');
            return;
        }
    }
    
    // 7. Validar código REEUP si está presente
    if (companyConfig.cod_reeup && companyConfig.cod_reeup.trim() !== '') {
        if (!/^[A-Z0-9]{5,20}$/i.test(companyConfig.cod_reeup)) {
            showSweetAlert('REEUP Inválido', 'El código REEUP debe contener solo letras y números (5-20 caracteres).', 'warning', 'Corregir');
            addConsoleLog('Código REEUP inválido', 'warning');
            return;
        }
    }
    
    // 8. Validar código NIT si está presente
    if (companyConfig.cod_nit && companyConfig.cod_nit.trim() !== '') {
        if (!/^[0-9]{9,15}$/.test(companyConfig.cod_nit)) {
            showSweetAlert('NIT Inválido', 'El código NIT debe contener solo números (9-15 dígitos).', 'warning', 'Corregir');
            addConsoleLog('Código NIT inválido', 'warning');
            return;
        }
    }
    
    // 9. Validar número de teléfono
    if (companyConfig.telefono && !/^[\d\s\-\+\(\)]{7,20}$/.test(companyConfig.telefono)) {
        showSweetAlert('Teléfono Inválido', 'El número de teléfono no tiene un formato válido.', 'warning', 'Corregir');
        addConsoleLog('Teléfono con formato inválido', 'warning');
        return;
    }
    
    // 10. Validar cuenta bancaria si está presente
    if (companyConfig.cuenta_bancaria && companyConfig.cuenta_bancaria.trim() !== '') {
        if (!/^[\d\s\-]{10,25}$/.test(companyConfig.cuenta_bancaria)) {
            showSweetAlert('Cuenta Bancaria Inválida', 'El número de cuenta bancaria no tiene un formato válido.', 'warning', 'Corregir');
            addConsoleLog('Cuenta bancaria inválida', 'warning');
            return;
        }
    }
    
    // 11. Validar fecha de inicio de operaciones
    if (companyConfig.fecha_inicio_operaciones) {
        // Crear fecha de forma explícita para validación
        const fechaInicio = new Date(anioInicio, mesInicio - 1, 1);
        const hoy = new Date();
        
        // Asegurar que la fecha sea válida
        if (isNaN(fechaInicio.getTime())) {
            showSweetAlert('Fecha Inválida', 'La fecha de inicio de operaciones no es válida.', 'warning', 'Corregir');
            addConsoleLog('Fecha de inicio de operaciones inválida', 'warning');
            return;
        }
        
        // Validar que no sea fecha futura (primer día del mes seleccionado)
        const hoySinHora = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const fechaInicioSinHora = new Date(fechaInicio.getFullYear(), fechaInicio.getMonth(), 1);
        
        if (fechaInicioSinHora > hoySinHora) {
            showSweetAlert('Fecha Futura', 'La fecha de inicio no puede ser futura. Seleccione un mes/año actual o anterior.', 'warning', 'Corregir');
            addConsoleLog('Fecha de inicio de operaciones es futura', 'warning');
            return;
        }
        
        // Validar que no sea demasiado antigua (ej: más de 50 años)
        const fechaLimite = new Date();
        fechaLimite.setFullYear(fechaLimite.getFullYear() - 50);
        fechaLimite.setDate(1); // Primer día del mes
        
        if (fechaInicio < fechaLimite) {
            showSweetAlert('Fecha Muy Antigua', 'La fecha de inicio no puede ser hace más de 50 años.', 'warning', 'Corregir');
            addConsoleLog('Fecha de inicio de operaciones demasiado antigua', 'warning');
            return;
        }
    }
    
    // 12. Validar teléfono del director si está presente
    if (companyConfig.director_telefono && companyConfig.director_telefono.trim() !== '') {
        if (!/^[\d\s\-\+\(\)]{7,15}$/.test(companyConfig.director_telefono)) {
            showSweetAlert('Teléfono Inválido', 'El teléfono del director no tiene un formato válido.', 'warning', 'Corregir');
            addConsoleLog('Teléfono del director inválido', 'warning');
            return;
        }
    }
    
    // 13. Validar teléfono del facturador si está presente
    if (companyConfig.facturador_telefono && companyConfig.facturador_telefono.trim() !== '') {
        if (!/^[\d\s\-\+\(\)]{7,15}$/.test(companyConfig.facturador_telefono)) {
            showSweetAlert('Teléfono Inválido', 'El teléfono del facturador no tiene un formato válido.', 'warning', 'Corregir');
            addConsoleLog('Teléfono del facturador inválido', 'warning');
            return;
        }
    }
    
    // Mostrar confirmación de fecha (usando formateo manual)
    const meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    
    const fechaFormateada = `1 de ${meses[mesInicio - 1]} de ${anioInicio}`;
    
    // Usar Swal.fire directamente para permitir HTML
    Swal.fire({
        title: 'Confirmar Fecha de Inicio',
        html: `La fecha de inicio de operaciones será: <br><strong style="color: #0078d4; font-size: 1.2em;">${fechaFormateada}</strong><br><br>Esta será la fecha desde la cual podrán emitirse facturas en el sistema.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Sí, Confirmar',
        cancelButtonText: '<i class="fas fa-times"></i> Cambiar Fecha',
        background: '#171717',
        color: '#ffffff',
        confirmButtonColor: '#0078d4',
        cancelButtonColor: '#d13438',
        customClass: {
            confirmButton: 'swal-confirm-button',
            cancelButton: 'swal-cancel-button'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Continuar con el guardado
            guardarConfiguracionEmpresa();
        } else {
            addConsoleLog('Usuario canceló para cambiar la fecha', 'info');
            return;
        }
    });
    
    function guardarConfiguracionEmpresa() {
        // Todas las validaciones pasaron
        addConsoleLog('Todas las validaciones de empresa pasaron', 'success');
        addConsoleLog(`Fecha a guardar: ${companyConfig.fecha_inicio_operaciones}`, 'info');
        
        const saveBtn = document.getElementById('btn-save-company');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Guardando...</span>';
        saveBtn.disabled = true;
        
        // Combinar configuraciones
        const allConfig = { ...dbConfig, ...companyConfig };
        const formData = new URLSearchParams();
        formData.append('action', 'save_company_config');
        Object.keys(allConfig).forEach(key => {
            formData.append(key, allConfig[key]);
        });
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                const data = JSON.parse(cleanText);
                if (data.success) {
                    addConsoleLog(`Configuración guardada. Fecha inicio: ${data.fecha_inicio || 'N/A'}`, 'success');
                    
                    showSweetAlert('Configuración Guardada', 'Datos de la empresa guardados exitosamente.', 'success', 'Continuar')
                    .then(() => {
                        canProceedToNextStep[5] = true;
                        updateNextButtonState();
                        nextStep();
                    });
                    
                } else {
                    showSweetAlert('Error', `Error: ${data.error}`, 'error', 'Reintentar');
                    addConsoleLog(`Error al guardar configuración: ${data.error}`, 'error');
                    saveBtn.disabled = false;
                }
            } catch (e) {
                console.error('Error parseando JSON:', e);
                showSweetAlert('Error', 'Error en la respuesta del servidor.', 'error', 'Reintentar');
                addConsoleLog('Error parseando respuesta JSON', 'error');
                saveBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
            showSweetAlert('Error', 'Error en la comunicación con el servidor.', 'error', 'Reintentar');
            addConsoleLog('Error de red al guardar configuración', 'error');
            saveBtn.disabled = false;
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
        });
    }
}
		
		// Función para guardar configuración del sistema
        function saveConfig() {
            addConsoleLog('Guardando configuración del sistema...', 'info');
            
            // Obtener datos del formulario
            systemConfig = {
                nombre_sistema: document.getElementById('nombre_sistema').value,
                version: document.getElementById('version').value,
                items_por_pagina: document.getElementById('items_por_pagina').value,
                timezone: document.getElementById('timezone').value,
                // WhatsApp y Mantenimiento
                whatsapp_ON: document.getElementById('whatsapp_ON').checked ? 1 : 0,
                whatsapp_numero: document.getElementById('whatsapp_numero').value,
                modo_mantenimiento: document.getElementById('modo_mantenimiento').checked ? 1 : 0,
				restabpw: document.getElementById('restabpw').checked ? 1 : 0,
            };
            
            // Validar items por página
            const items = parseInt(systemConfig.items_por_pagina);
            if (isNaN(items) || items < 5 || items > 100) {
                showSweetAlert('Items Inválidos', 'Los items por página deben estar entre 5 y 100.', 'warning', 'Corregir');
                addConsoleLog('Items por página inválidos', 'warning');
                return;
            }
            
            const saveBtn = document.getElementById('btn-save-config');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="loading"><span class="loading-spinner"></span> Guardando...</span>';
            saveBtn.disabled = true;
            
            // Combinar configuraciones
            const allConfig = { ...dbConfig, ...systemConfig };
            const formData = new URLSearchParams();
            formData.append('action', 'save_config');
            Object.keys(allConfig).forEach(key => {
                formData.append(key, allConfig[key]);
            });
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                // Depuración: mostrar respuesta cruda
                console.log('Respuesta cruda del servidor:', text.substring(0, 500));
                addConsoleLog('Respuesta del servidor recibida, procesando...', 'info');
                
                try {
                    // Limpiar comentarios HTML antes de parsear
                    const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                    console.log('Texto limpio para JSON:', cleanText.substring(0, 300));
                    
                    const data = JSON.parse(cleanText);
                    if (data.success) {
                        showSweetAlert('Configuración Guardada', 'Configuración del sistema guardada exitosamente.', 'success', 'Completar')
                        .then(() => {
                            canProceedToNextStep[6] = true;
                            updateNextButtonState();
                            nextStep();
                        });
                        addConsoleLog('Configuración del sistema guardada exitosamente', 'success');
                        
                    } else {
                        showSweetAlert('Error', `Error: ${data.error}`, 'error', 'Reintentar');
                        addConsoleLog(`Error al guardar configuración: ${data.error}`, 'error');
                        saveBtn.disabled = false;
                    }
                } catch (e) {
                    console.error('Error parseando JSON:', e);
                    console.error('Texto que causó el error:', text.substring(0, 500));
                    showSweetAlert('Error de respuesta', 'La respuesta del servidor no es JSON válido. Revise los logs del servidor.', 'error', 'Reintentar');
                    addConsoleLog(`Error parseando respuesta JSON: ${e.message}`, 'error');
                    addConsoleLog(`Respuesta recibida: ${text.substring(0, 200)}`, 'error');
                    saveBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showSweetAlert('Error', 'Error en la comunicación con el servidor.', 'error', 'Reintentar');
                addConsoleLog('Error de red al guardar configuración', 'error');
                saveBtn.disabled = false;
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
            });
        }
        
        // Función para copiar al portapapeles
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                showSweetAlert('Copiado', 'Texto copiado al portapapeles', 'success', 'OK');
                addConsoleLog('Texto copiado al portapapeles', 'success');
            }).catch(err => {
                showSweetAlert('Error', 'Error al copiar el texto', 'error', 'Reintentar');
                addConsoleLog('Error al copiar al portapapeles', 'error');
            });
        }
        
        // Función para eliminar el instalador
        function deleteInstaller() {
            showSweetAlert(
                'Eliminar Instalador',
                '¿Estás seguro de que deseas eliminar el instalador? Esta acción no se puede deshacer.',
                'warning',
                'Sí, Eliminar',
                'Cancelar',
                true
            ).then((result) => {
                if (result.isConfirmed) {
                    addConsoleLog('Eliminando instalador...', 'warning');
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_installer'
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const cleanText = text.replace(/<!--[\s\S]*?-->/g, '').trim();
                            const data = JSON.parse(cleanText);
                            if (data.success) {
                                showSweetAlert('Instalador Eliminado', 'Instalador eliminado correctamente. Redirigiendo...', 'success', 'Continuar');
                                addConsoleLog('Instalador eliminado correctamente', 'success');
                                setTimeout(() => {
                                    window.location.href = '../';
                                }, 2000);
                            } else {
                                showSweetAlert('Error', `Error: ${data.error}`, 'error', 'Reintentar');
                                addConsoleLog(`Error al eliminar instalador: ${data.error}`, 'error');
                            }
                        } catch (e) {
                            console.error('Error parseando JSON:', e);
                            showSweetAlert('Error', 'Error en la respuesta del servidor.', 'error', 'Reintentar');
                            addConsoleLog('Error parseando respuesta JSON', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error de red:', error);
                        showSweetAlert('Error', 'Error en la comunicación con el servidor.', 'error', 'Reintentar');
                        addConsoleLog('Error de red al eliminar instalador', 'error');
                    });
                }
            });
        }
        
        // ============================================
        // FUNCIONES PARA LA CONSOLA DE LOGS EN TIEMPO REAL
        // ============================================
        
        function toggleConsole() {
            const consoleElement = document.getElementById('logsConsole');
            isConsoleOpen = !isConsoleOpen;
            
            if (isConsoleOpen) {
                consoleElement.classList.add('active');
                refreshConsole();
                addConsoleLog('Consola abierta', 'info');
            } else {
                consoleElement.classList.remove('active');
                addConsoleLog('Consola cerrada', 'info');
            }
        }
        
        function addConsoleLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = {
                timestamp: timestamp,
                message: message,
                type: type
            };
            
            consoleLogs.push(logEntry);
            
            // Limitar a 100 logs
            if (consoleLogs.length > 100) {
                consoleLogs.shift();
            }
            
            // Actualizar badge
            document.getElementById('consoleBadge').textContent = consoleLogs.length;
            
            // Actualizar consola si está abierta
            if (isConsoleOpen) {
                updateConsoleDisplay();
            }
            
            // También enviar al servidor para registro permanente
            sendLogToServer(message, type);
        }
        
        function updateConsoleDisplay() {
            const consoleBody = document.getElementById('consoleBody');
            consoleBody.innerHTML = '';
            
            consoleLogs.forEach(log => {
                const logDiv = document.createElement('div');
                logDiv.className = `console-entry ${log.type}`;
                
                logDiv.innerHTML = `
                    <span class="console-time">[${log.timestamp}]</span>
                    ${log.message}
                `;
                
                consoleBody.appendChild(logDiv);
            });
            
            // Scroll al final
            consoleBody.scrollTop = consoleBody.scrollHeight;
        }
        
        function clearConsole() {
            consoleLogs = [];
            updateConsoleDisplay();
            document.getElementById('consoleBadge').textContent = '0';
            addConsoleLog('Consola limpiada', 'info');
        }
        
        function refreshConsole() {
            updateConsoleDisplay();
            addConsoleLog('Consola actualizada', 'info');
        }
        
        function sendLogToServer(message, type) {
            const formData = new URLSearchParams();
            formData.append('action', 'log_message');
            formData.append('log_message', message);
            formData.append('log_type', type);
            
            // Enviar asíncrono, no esperar respuesta
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            }).catch(error => {
                console.error('Error enviando log al servidor:', error);
            });
        }
        
        // Funciones para el modal de logs
        function openLogs() {
            const modal = document.getElementById('logsModal');
            modal.classList.add('active');
            refreshLogs();
            addConsoleLog('Modal de logs abierto', 'info');
        }
        
        function closeLogs() {
            const modal = document.getElementById('logsModal');
            modal.classList.remove('active');
            addConsoleLog('Modal de logs cerrado', 'info');
        }
        
        function refreshLogs() {
            const logsBody = document.getElementById('logsBody');
            logsBody.innerHTML = '<div class="loading"><span class="loading-spinner"></span> Cargando logs...</div>';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_logs'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLogs(data.logs);
                    addConsoleLog('Logs del servidor cargados', 'success');
                } else {
                    logsBody.innerHTML = '<div class="log-entry error">Error al cargar logs</div>';
                    addConsoleLog('Error al cargar logs del servidor', 'error');
                }
            })
            .catch(error => {
                console.error('Error parseando logs:', error);
                logsBody.innerHTML = '<div class="log-entry error">Error al procesar logs</div>';
                addConsoleLog('Error parseando logs del servidor', 'error');
            });
        }
        
        function displayLogs(logs) {
            const logsBody = document.getElementById('logsBody');
            const lines = logs.split('\n');
            let html = '';
            
            lines.forEach(line => {
                if (line.trim()) {
                    let type = 'info';
                    let message = line;
                    
                    // Detectar tipo de log basado en contenido
                    if (line.includes('[ERROR]')) {
                        type = 'error';
                    } else if (line.includes('[SUCCESS]')) {
                        type = 'success';
                    } else if (line.includes('[WARNING]')) {
                        type = 'warning';
                    }
                    
                    // Extraer timestamp y mensaje (formato dd-mm-aaaa)
                    const timestampMatch = line.match(/\[(\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2})\]/);
                    const timestamp = timestampMatch ? timestampMatch[1] : '';
                    message = line.replace(/\[\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}\] \[.*?\] /, '');
                    
                    html += `
                        <div class="log-entry ${type}">
                            <span class="log-time">${timestamp}</span>
                            ${message}
                        </div>
                    `;
                }
            });
            
            logsBody.innerHTML = html;
            logsBody.scrollTop = logsBody.scrollHeight;
        }
        
        function downloadLogs() {
            const a = document.createElement('a');
            a.href = 'install_logs.txt';
            a.download = 'install_logs.txt';
            a.click();
            addConsoleLog('Logs descargados', 'info');
        }

        // Función para abrir soporte técnico
        function openSupport() {
            const supportUrl = '../soporte.php';
            
            // Abrir en nueva ventana/pestaña
            window.open(supportUrl, '_blank', 'noopener,noreferrer');
            
            // Registrar en consola
            addConsoleLog('Soporte técnico abierto en nueva ventana', 'info');
            
            // Mostrar notificación opcional
            showSweetAlert(
                'Soporte Técnico',
                'Se ha abierto la página de soporte técnico en una nueva pestaña.',
                'info',
                'OK'
            );
        }
function actualizarFechaVisual() {
    const mes = document.getElementById('mes_inicio').value;
    const anio = document.getElementById('anio_inicio').value;
    
    if (mes && anio) {
        const mesFormateado = mes.padStart(2, '0');
        
        // SOLUCIÓN: Crear fecha de forma explícita para evitar problemas de zona horaria
        const fecha = new Date(anio, mes - 1, 1); // Año, Mes (0-11), Día
        
        // Formatear la fecha manualmente para evitar problemas de localización
        const meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];
        
        const fechaFormateada = `1 de ${meses[mes - 1]} de ${anio}`;
        
        document.getElementById('fecha_seleccionada').textContent = fechaFormateada;
        
        // Para debugging (opcional)
        console.log(`Mes: ${mes}, Año: ${anio}`);
        console.log(`Fecha creada: ${fecha}`);
        console.log(`Fecha formateada: ${fechaFormateada}`);
    } else {
        document.getElementById('fecha_seleccionada').textContent = 'DD/MM/YYYY';
    }
}
    </script>
</body>
</html>

<?php
// ============================================
// FUNCIONES PHP DEL BACKEND
// ============================================

function checkRequirementsHandler() {
    writeLog('Iniciando verificación de requisitos del sistema', 'INFO');
    
    $requirements = [];
    
    // 1. Verificar versión de PHP
    $phpVersion = PHP_VERSION;
    $phpPassed = version_compare($phpVersion, MIN_PHP_VERSION, '>=');
    $requirements[] = [
        'name' => 'PHP ' . MIN_PHP_VERSION . ' o superior',
        'passed' => $phpPassed,
        'message' => $phpPassed ? "PHP $phpVersion (OK)" : "PHP $phpVersion (Requiere " . MIN_PHP_VERSION . "+)"
    ];
    
    // 2. Verificar extensión MySQLi
    $mysqliPassed = extension_loaded('mysqli');
    $requirements[] = [
        'name' => 'Extensión MySQLi',
        'passed' => $mysqliPassed,
        'message' => $mysqliPassed ? 'Extensión cargada (OK)' : 'Extensión no encontrada'
    ];
    
    // 3. Verificar permisos de escritura
    $writeTestFile = __DIR__ . '/test_write.tmp';
    $writePassed = false;
    try {
        $handle = @fopen($writeTestFile, 'w');
        if ($handle) {
            fwrite($handle, 'test');
            fclose($handle);
            @unlink($writeTestFile);
            $writePassed = true;
        }
    } catch (Exception $e) {
        $writePassed = false;
    }
    
    $requirements[] = [
        'name' => 'Permisos de escritura',
        'passed' => $writePassed,
        'message' => $writePassed ? 'Permisos OK' : 'Sin permisos de escritura'
    ];
    
    // 4. Verificar extensión JSON
    $jsonPassed = extension_loaded('json');
    $requirements[] = [
        'name' => 'Extensión JSON',
        'passed' => $jsonPassed,
        'message' => $jsonPassed ? 'Extensión cargada (OK)' : 'Extensión no encontrada'
    ];
    
    // 5. Verificar sesiones
    $sessionPassed = function_exists('session_start');
    $requirements[] = [
        'name' => 'Soporte para sesiones',
        'passed' => $sessionPassed,
        'message' => $sessionPassed ? 'Sesiones disponibles (OK)' : 'Sesiones no disponibles'
    ];
    
    // 6. Verificar memoria mínima
    $memoryLimit = ini_get('memory_limit');
    $memoryBytes = return_bytes($memoryLimit);
    $memoryPassed = $memoryBytes >= 64 * 1024 * 1024; // 64MB mínimo
    $requirements[] = [
        'name' => 'Memoria PHP (mínimo 64MB)',
        'passed' => $memoryPassed,
        'message' => $memoryPassed ? "Memoria: $memoryLimit (OK)" : "Memoria: $memoryLimit (Recomendado 64MB+)"
    ];
    
    // 7. Verificar si se puede crear archivo de logs
    $logFile = LOG_FILE;
    $logPassed = false;
    try {
        $handle = @fopen($logFile, 'a');
        if ($handle) {
            fclose($handle);
            $logPassed = true;
        }
    } catch (Exception $e) {
        $logPassed = false;
    }
    
    $requirements[] = [
        'name' => 'Archivo de logs',
        'passed' => $logPassed,
        'message' => $logPassed ? 'Logs activados (OK)' : 'No se puede crear archivo de logs'
    ];
    
    // 8. Verificar versión de Apache
    $apacheVersion = apache_get_version() ?? '';
    preg_match('/Apache\/([0-9.]+)/', $apacheVersion, $matches);
    $apacheVersionNumber = $matches[1] ?? '0.0.0';
    $apachePassed = version_compare($apacheVersionNumber, '2.0.0', '>=');
    $requirements[] = [
        'name' => 'Apache 2.0 o superior',
        'passed' => $apachePassed,
        'message' => $apachePassed ? "Apache $apacheVersionNumber (OK)" : "Apache $apacheVersionNumber (Requiere 2.0+)"
    ];
    
    writeLog('Verificación de requisitos completada', 'INFO');
    
    sendJsonResponse([
        'success' => true,
        'requirements' => $requirements
    ]);
}

function testConnectionHandler() {
    writeLog('Iniciando prueba de conexión a base de datos', 'INFO');
    
    $host = $_POST['db_host'] ?? 'localhost';
    $port = $_POST['db_port'] ?? 3306;
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? 'sisfact_imdl';
    $prefix = ''; // Prefijo en blanco
    
    $tests = [];
    
    try {
        // 1. Probar conexión al servidor
        $mysqli = @new mysqli($host, $user, $pass, '', $port);
        
        if ($mysqli->connect_error) {
            throw new Exception($mysqli->connect_error);
        }
        
        $tests[] = [
            'message' => 'Conexión al servidor MySQL',
            'passed' => true
        ];
        
        // 2. Verificar versión del servidor
        $version = $mysqli->server_version;
        $versionStr = floor($version / 10000) . '.' . floor(($version % 10000) / 100) . '.' . ($version % 100);
        $tests[] = [
            'message' => "Versión del servidor: $versionStr",
            'passed' => true
        ];
        
        // 3. Verificar si la base de datos existe
        $result = $mysqli->query("SHOW DATABASES LIKE '$name'");
        $dbExists = $result->num_rows > 0;
        
        if ($dbExists) {
            $tests[] = [
                'message' => "Base de datos '$name' ya existe (se eliminará y recreará)",
                'passed' => true
            ];
        } else {
            $tests[] = [
                'message' => "Base de datos '$name' no existe (se creará)",
                'passed' => true
            ];
        }
        
        // 4. Verificar privilegios del usuario
        $privileges = [];
        $result = $mysqli->query("SHOW GRANTS FOR CURRENT_USER");
        while ($row = $result->fetch_array()) {
            $privileges[] = $row[0];
        }
        
        $hasCreateDB = false;
        $hasAllPrivileges = false;
        foreach ($privileges as $privilege) {
            if (stripos($privilege, 'ALL PRIVILEGES') !== false || stripos($privilege, 'ALL PRIVILEGES ON *.*') !== false) {
                $hasAllPrivileges = true;
            }
            if (stripos($privilege, 'CREATE') !== false || stripos($privilege, 'ALL PRIVILEGES') !== false) {
                $hasCreateDB = true;
            }
        }
        
        if ($hasAllPrivileges) {
            $tests[] = [
                'message' => 'Usuario con privilegios completos',
                'passed' => true
            ];
        } else if ($hasCreateDB) {
            $tests[] = [
                'message' => 'Usuario con privilegios suficientes',
                'passed' => true
            ];
        } else {
            $tests[] = [
                'message' => 'Usuario podría no tener todos los privilegios necesarios',
                'passed' => false
            ];
        }
        
        $mysqli->close();
        
        writeLog('Prueba de conexión exitosa', 'SUCCESS');
        
        sendJsonResponse([
            'success' => true,
            'tests' => $tests
        ]);
        
    } catch (Exception $e) {
        writeLog('Error en prueba de conexión: ' . $e->getMessage(), 'ERROR');
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

function createDatabaseHandler() {
    writeLog('Iniciando creación de base de datos', 'INFO');
    
    // Limpiar buffer antes de comenzar
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $host = $_POST['db_host'] ?? 'localhost';
    $port = $_POST['db_port'] ?? 3306;
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? 'sisfact_imdl';
    $prefix = ''; // Prefijo en blanco por ahora
    
    // Variable para almacenar todo el contenido SQL
    $sqlContent = "";
    
    try {
        // Conectar al servidor MySQL (SIN seleccionar base de datos)
        $mysqli = new mysqli($host, $user, $pass, '', $port);
        //                                    ^^ IMPORTANTE: Base de datos vacío
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        writeLog("Conectado al servidor MySQL en $host:$port", 'INFO');
        
        // ============ ELIMINAR BASE DE DATOS SI EXISTE ============
        $dropSQL = "DROP DATABASE IF EXISTS `$name`;";
        $sqlContent .= "-- Eliminar base de datos si existe\n";
        $sqlContent .= $dropSQL . "\n";
        
        if ($mysqli->query($dropSQL)) {
            writeLog("Base de datos '$name' eliminada (si existía)", 'SUCCESS');
        } else {
            writeLog("Error al eliminar base de datos: " . $mysqli->error, 'WARNING');
        }
        
        // ============ CREAR BASE DE DATOS NUEVA ============
        $createDBSQL = "CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $sqlContent .= $createDBSQL . "\n";
        
        if ($mysqli->query($createDBSQL)) {
            writeLog("Base de datos '$name' creada con éxito", 'SUCCESS');
        } else {
            throw new Exception("Error al crear base de datos: " . $mysqli->error);
        }
        
        // ============ SELECCIONAR LA BASE DE DATOS RECIÉN CREADA ============
        if (!$mysqli->select_db($name)) {
            throw new Exception("Error al seleccionar base de datos: " . $mysqli->error);
        }
        writeLog("Base de datos '$name' seleccionada", 'SUCCESS');
        
        // Iniciar transacción
        $mysqli->query("START TRANSACTION");
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // ... resto del código (creación de tablas, etc.) ...
        
        // Configurar SQL mode y timezone
        $mysqli->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $mysqli->query("SET time_zone = '+00:00'");
        $sqlContent .= "\n";

        // ============ ESTABLECER max_allowed_packet SILENCIOSAMENTE ============
        // Intentar establecer max_allowed_packet a 1GB para la sesión actual
        $mysqli->query("SET GLOBAL max_allowed_packet = 1073741824");
        
        // Si falla, intentar establecer solo para la sesión
        if ($mysqli->error) {
            $mysqli->query("SET SESSION max_allowed_packet = 1073741824");
            writeLog("max_allowed_packet Falló en General, establecido en Sesión actual", 'ERROR');
        }
        
        $result = $mysqli->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($result) {
            $row = $result->fetch_assoc();
            $newMaxPacket = $row['Value'] ?? '0';
            $newMB = round($newMaxPacket / (1024*1024), 2);
            writeLog("max_allowed_packet establecido en: {$newMB}MB", 'INFO');
        }
        
        // Configurar SQL mode y timezone
        $mysqli->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $mysqli->query("SET time_zone = '+00:00'");
        $mysqli->query("START TRANSACTION");
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Eliminar base de datos si existe
        $sqlContent .= "-- ============================================\n";
        $sqlContent .= "-- CREAR/ELIMINAR BASE DE DATOS\n";
        $sqlContent .= "-- ============================================\n";
        $dropSQL = "DROP DATABASE IF EXISTS `$name`;";
        $sqlContent .= $dropSQL . "\n";
        
        if ($mysqli->query($dropSQL)) {
            writeLog("Base de datos '$name' eliminada (si existía)", 'INFO');
        } else {
            writeLog("Error al eliminar base de datos: " . $mysqli->error, 'WARNING');
        }
        
        // Crear la base de datos
        $createDBSQL = "CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $sqlContent .= $createDBSQL . "\n";
        $sqlContent .= "USE `$name`;\n\n";
        
        if ($mysqli->query($createDBSQL)) {
            writeLog("Base de datos '$name' creada con éxito", 'SUCCESS');
            $mysqli->select_db($name);
        } else {
            throw new Exception("Error al crear base de datos: " . $mysqli->error);
        }
        
        if ($mysqli->query("USE `$name`")) {
            writeLog("Base de datos '$name' Puesta en Uso", 'SUCCESS');
            $mysqli->select_db($name);
        } else {
            throw new Exception("Error al poner en uso base de datos: " . $mysqli->error);
        }
        
        $tablesCreated = [];
        
        // ============================================
        // CREAR TODAS LAS TABLAS COMPLETAMENTE
        // ============================================
        
        $sqlContent .= "-- ============================================\n";
        $sqlContent .= "-- CREACIÓN DE TABLAS\n";
        $sqlContent .= "-- ============================================\n\n";
        
        // 1. Tabla: clasif_cat_de_serv
        $sql = "CREATE TABLE `clasif_cat_de_serv` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(200) NOT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: clasif_cat_de_serv\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'clasif_cat_de_serv', 'fields' => 5];
            writeLog("Tabla 'clasif_cat_de_serv' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla clasif_cat_de_serv: " . $mysqli->error);
        }
        
        // Insertar datos en clasif_cat_de_serv
        $insertCat = "INSERT INTO `clasif_cat_de_serv` (`id`, `codigo`, `descripcion`, `activo`, `fecha_creacion`) VALUES
        (1, 'IMP1', 'IMPRESIÓN DE PLANTILLAS Y/O MODELOS', 1, '2025-12-31 01:00:00'),
		(2, 'IMP2', 'IMPRESIÓN DE TARJETAS E INVITACIONES', 1, '2025-12-31 01:01:00'),
		(3, 'IMP3', 'IMPRESIÓN EN PAPEL DOCUMENTOS E IMÁGENES', 1, '2025-12-31 01:02:00'),
		(4, 'IMP4', 'IMPRESIÓN EN CARTULINA Y/O PAPEL FOTOGRÁFICO', 1, '2025-12-31 01:03:00'),
		(5, 'PLS1', 'PLATICADOS', 1, '2025-12-31 01:04:00'),
		(6, 'FS01', 'FOTOCOPIAS / SCANERS', 1, '2025-12-31 01:05:00'),
		(7, 'OTIMP', 'OTROS SERVICIOS', 1, '2025-12-31 01:06:00'),
		(8, 'FM01', 'MATERIAL DE OFICINA', 1, '2025-12-31 01:07:00'),
		(9, 'ALB1', 'ALIMENTOS Y BEBIDAS', 1, '2025-12-31 01:08:00'),
		(10, 'EE01', 'EQUIPOS Y ACCESORIOS ELECTRÓNICOS', 1, '2025-12-31 01:09:00'),
		(11, 'PPR1', 'PARTES Y/O PIEZAS DE RESPUESTO', 1, '2025-12-31 01:10:00'),
		(12, 'PRA1', 'PRODUCTOS DE ASEO Y LIMPIEZA', 1, '2025-12-31 01:11:00');";
        
        $sqlContent .= "-- Insertar datos en clasif_cat_de_serv\n";
        $sqlContent .= $insertCat . "\n\n";
        
        if ($mysqli->query($insertCat)) {
            writeLog("Datos insertados en 'clasif_cat_de_serv'", 'SUCCESS');
        }
        
        // 2. Tabla: clasif_clientes
        $sql = "CREATE TABLE `clasif_clientes` (
		  `id` int(11) NOT NULL,
		  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `direccion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `ResponsableEntidad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NoCIResp` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `CodReup` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NIT` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `ContratoNo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `SucursalCobroLocalidad` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `NoCtaDeudor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		  `fechaRegistro` date DEFAULT NULL,
		  `vigenciapor` int(11) DEFAULT 0,
		  `fechaVence` date GENERATED ALWAYS AS (`fechaRegistro` + interval `vigenciapor` year) VIRTUAL,
		  `activo` tinyint(1) DEFAULT NULL,
		  `renovac` tinyint(1) DEFAULT 0 COMMENT '1=Si, 0=No',
		  `si_renova_cant` int(11) DEFAULT 0,
		  `fechafinalcontrato` date GENERATED ALWAYS AS (`fechaRegistro` + interval `vigenciapor` + if(`renovac` = 1,`si_renova_cant`,0) year) VIRTUAL,
		  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: clasif_clientes\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'clasif_clientes', 'fields' => 20];
            writeLog("Tabla 'clasif_clientes' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla clasif_clientes: " . $mysqli->error);
        }
        
        // 3. Tabla: clasif_rol
        $sql = "CREATE TABLE `clasif_rol` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(10) NOT NULL,
            `descripcion` varchar(100) NOT NULL,
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: clasif_rol\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'clasif_rol', 'fields' => 3];
            writeLog("Tabla 'clasif_rol' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla clasif_rol: " . $mysqli->error);
        }
        
        // Insertar roles
        $insertRoles = "INSERT INTO `clasif_rol` (`codigo`, `descripcion`) VALUES
            ('Admin', 'Administrador del Sistema'),
            ('Visor', 'Visualizador'),
            ('Editor', 'Facturador / Editor'),
            ('Super', 'Supervisor General'),
            ('Soft', 'Programador');";
        
        $sqlContent .= "-- Insertar roles\n";
        $sqlContent .= $insertRoles . "\n\n";
        
        if ($mysqli->query($insertRoles)) {
            writeLog("Roles insertados en 'clasif_rol'", 'SUCCESS');
        }
        
        // 4. Tabla: clasif_serv
        $sql = "CREATE TABLE `clasif_serv` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(200) NOT NULL,
            `categoria_id` int(11) DEFAULT NULL,
            `costo` decimal(10,2) NOT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_creacion` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
			UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: clasif_serv\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'clasif_serv', 'fields' => 7];
            writeLog("Tabla 'clasif_serv' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla clasif_serv: " . $mysqli->error);
        }
        
        // Insertar servicios
        $insertServicios = "INSERT INTO `clasif_serv` (`id`, `codigo`, `descripcion`, `categoria_id`, `costo`, `activo`, `fecha_creacion`) VALUES
		(1, 'CR01', 'Credencial (x unidad)', 7, '200.00', 1, '2025-01-15 15:00:00'),
		(2, 'CR02', 'Credencial (x unidad) con colgante', 7, '280.00', 1, '2025-01-15 15:01:00'),
		(3, 'DC01', 'Diploma en Cartulina. Forrado Vinilo', 4, '300.00', 1, '2025-01-15 15:02:00'),
		(4, 'DI01', 'Diseño de Diploma, Tarjetas y Otros (Hasta)', 7, '500.00', 1, '2025-01-15 15:03:00'),
		(5, 'E001', 'Encuadernado', 4, '600.00', 1, '2025-01-15 15:04:00'),
		(6, 'F001', 'Fotografía 5x7″', 4, '75.00', 1, '2025-01-15 15:05:00'),
		(7, 'F002', 'Fotografía 1x1″ - 3 En Adelante', 4, '50.00', 1, '2025-01-15 15:06:00'),
		(8, 'F003', '4 Fotos Pasaporte/Visa', 4, '500.00', 1, '2025-01-15 15:07:00'),
		(9, 'F004', 'Fotografía 8½x11″', 4, '1000.00', 1, '2025-01-15 15:08:00'),
		(10, 'FS01', 'Fotocopia / Scaner', 6, '45.00', 1, '2025-01-15 15:09:00'),
		(11, 'FS02', 'Fotocopia / Scaner D/C', 6, '75.00', 1, '2025-01-15 15:10:00'),
		(12, 'GT01', 'Grabar Tazón/Taza', 7, '2300.00', 1, '2025-01-01 08:46:00'),
		(13, 'I001', 'Impresión de Documentos (B/N)', 3, '25.00', 1, '2025-01-01 08:47:00'),
		(14, 'I002', 'Impresión de Documento (Doble Cara B/N)', 3, '45.00', 1, '2025-01-01 08:48:00'),
		(15, 'I003', 'Impresión de Documentos (Color)', 3, '35.00', 1, '2025-01-01 08:49:00'),
		(16, 'I004', 'Impresión de Documentos a color (Doble Cara)', 3, '65.00', 1, '2025-01-01 08:50:00'),
		(17, 'I005', 'Impresión de imágenes en papel normal', 3, '55.00', 1, '2025-01-01 08:51:00'),
		(18, 'I006', 'Impresión de imágenes en papel normal D/C', 3, '95.00', 1, '2025-01-01 08:52:00'),
		(19, 'IC01', 'Impresión en Cartulina 8½x11″', 4, '300.00', 1, '2025-01-01 08:53:00'),
		(20, 'IC02', 'Impresión en Cartulina 12x18″', 4, '90.00', 1, '2025-01-01 08:54:00'),
		(21, 'IC03', 'Impresión en Cartulina D/C 8½x11″', 4, '570.00', 1, '2025-01-01 08:55:00'),
		(22, 'IC04', 'Impresión en Cartulina D/C 12x18″', 4, '170.00', 1, '2025-01-01 08:56:00'),
		(23, 'IC05', 'Impresión Diplomas en Cartulina 8½x11″', 4, '360.00', 1, '2025-01-01 08:57:00'),
		(24, 'IC06', 'Impresión Diplomas en Cartulina 12x18″', 4, '110.00', 1, '2025-01-01 08:58:00'),
		(25, 'IC07', 'Impresión de imágenes en cartulina 8½x11″', 4, '360.00', 1, '2025-01-01 08:59:00'),
		(26, 'IC08', 'Impresión de imágenes en cartulina 8½x11″', 4, '110.00', 1, '2025-01-01 09:00:00'),
		(27, 'IT01', 'Impresión Sencilla de Tarjetas', 2, '25.00', 1, '2025-01-01 09:01:00'),
		(28, 'IT02', 'Impresión Doble Cara Tarjetas', 2, '65.00', 1, '2025-01-01 09:02:00'),
		(29, 'MP01', 'Mica para platicar(tamaño solapin)', 5, '200.00', 1, '2025-01-01 09:03:00'),
		(30, 'MP02', 'Mica para platicar(tamaño carta)', 5, '1000.00', 1, '2025-01-01 09:04:00'),
		(31, 'P001', 'Impresión de Planillas (B/N)', 1, '25.00', 1, '2025-01-01 09:05:00'),
		(32, 'P002', 'Impresión de Planillas (Doble Cara B/N)', 1, '50.00', 1, '2025-01-01 09:06:00'),
		(33, 'P003', 'Impresión de Planillas (Color)', 1, '50.00', 1, '2025-01-01 09:07:00'),
		(34, 'P004', 'Impresión de Planillas (Doble Cara Color)', 1, '55.00', 1, '2025-01-01 09:08:00'),
		(35, 'ST01', 'Sublimación textil', 7, '1800.00', 1, '2025-01-01 09:09:00'),
		(36, 'TE01', 'Trámites de Embajadas', 7, '1000.00', 1, '2025-01-01 09:10:00'),
		(37, 'LC01', 'Lápiceros', 8, '80.00', 1, '2025-01-01 09:11:00'),
		(38, 'FO01', 'Foliadora', 8, '2596.95', 1, '2025-01-01 09:12:00'),
		(39, 'AR01', 'Archivador DL 5071', 8, '254.29', 1, '2025-01-01 09:13:00'),
		(40, 'AR02', 'Archivador Tipo Libro', 8, '891.75', 1, '2025-01-01 09:14:00'),
		(41, 'AR03', 'Archivador con 1 presilla', 8, '761.25', 1, '2025-01-01 09:15:00'),
		(42, 'AL01', 'Almohadilla para cuño', 8, '95.70', 1, '2025-01-01 09:16:00'),
		(43, 'AR04', 'Archivador con 2 presilla', 8, '761.25', 1, '2025-01-01 09:17:00'),
		(44, 'PD01', 'Porta documentos colores', 8, '98.60', 1, '2025-01-01 09:18:00'),
		(45, 'PC01', 'Paper Clip colores 728', 8, '65.25', 1, '2025-01-01 09:19:00'),
		(46, 'PC02', 'Paper Clip colores 733', 8, '65.25', 1, '2025-01-01 09:20:00'),
		(47, 'PC03', 'Paper Clip colores 750', 8, '137.75', 1, '2025-01-01 09:21:00'),
		(48, 'AR05', 'Archivador con presillas', 8, '761.25', 1, '2025-01-01 09:22:00'),
		(49, 'AR06', 'Archivador DL 5072', 8, '398.75', 1, '2025-01-01 09:23:00'),
		(50, 'EP01', 'Estuche de presillas', 8, '172.50', 1, '2025-01-01 09:24:00'),
		(51, 'SP01', 'Saca Presillas', 8, '113.10', 1, '2025-01-01 09:25:00'),
		(52, 'CP10', 'Café Prensado 10 Oz', 9, '1875.00', 1, '2025-01-01 09:26:00'),
		(53, 'MO01', 'Telefono  móvil, cargador, cable, cover y mica', 10, '80500.00', 1, '2025-01-01 09:27:00'),
		(54, 'TPC1', 'Tinta para Cuño', 8, '488.04', 1, '2025-01-01 09:28:00'),
		(55, 'CPS1', 'Cajas de presilla 50 mm', 8, '195.00', 1, '2025-01-01 09:29:00'),
		(56, 'HI01', 'Hojas de Índice', 8, '156.00', 1, '2025-01-01 09:30:00'),
		(57, 'AR07', 'Archivador Transparente c/corredera', 8, '195.00', 1, '2025-01-01 09:31:00'),
		(58, 'CA01', 'Calculadora Profesional 12 Digitos', 10, '5600.00', 1, '2025-01-01 09:32:00'),
		(59, 'TIL1', 'Tinta para Impresora (1LT)', 8, '48500.00', 1, '2025-01-01 09:33:00'),
		(60, 'CP01', 'Cuño personalizado (Rectangular)', 8, '8450.00', 1, '2025-01-01 09:34:00'),
		(61, 'HC50', 'Paquete Hojas (8½x11″) Carta 500U', 8, '4030.00', 1, '2025-01-01 09:35:00'),
		(62, 'PRA1', 'Piezas Respuesto/Accesorios p/Reparac.Medios de Computo', 11, '340050.00', 1, '2025-01-01 09:36:00'),
		(63, 'CP02', 'Cuño personalizado (Redondo)', 8, '8294.00', 1, '2025-01-01 09:37:00'),
		(64, 'CP03', 'Cuño personalizado (Cuadrado)', 8, '8294.00', 1, '2025-01-01 09:38:00'),
		(65, 'TCE1', 'Tarjeta Estiba', 2, '15.00', 1, '2025-01-01 09:39:00'),
		(66, 'TCE2', 'Tarjeta Firma', 2, '10.00', 1, '2025-01-01 09:40:00'),
		(67, 'CVS1', 'Carné Vacunación Fiebre Amarilla', 2, '350.00', 1, '2025-01-01 09:41:00'),
		(68, 'CVS2', 'Carné Vacunación COVID', 2, '50.00', 1, '2025-01-01 09:42:00'),
		(69, 'CP00', 'Cuño Personalizado(Diferentes Formas)', 2, '9555.00', 1, '2025-01-01 09:43:00'),
		(70, 'CP04', 'Cuño Redondo Personalizado', 2, '10300.00', 1, '2025-01-01 09:44:00'),
		(71, 'AR08', 'Archivador DL', 8, '390.00', 1, '2025-01-01 09:45:00'),
		(72, 'AR09', 'Archivador Tipo Libro', 8, '1086.00', 1, '2025-01-01 09:46:00'),
		(73, 'PY01', 'Partes y Piezas (Triciclos)', 11, '191200.00', 1, '2025-01-01 09:47:00'),
		(74, 'PY02', 'Partes y Piezas (Compresores)', 11, '43500.00', 1, '2025-01-01 09:48:00'),
		(75, 'PY03', 'Partes y Piezas (Máquinas de Soldar)', 11, '60000.00', 1, '2025-01-01 09:49:00'),
		(76, 'RHA7', 'Ron Habanclub Añejo 750ml', 9, '4500.00', 1, '2025-01-01 10:00:00'),
		(77, 'RHA1', 'Ron Habanclub Añejo 1 Litro', 9, '5800.00', 1, '2025-01-01 10:01:00'),
		(78, 'RH3A', 'Ron Habanclub 3 Años 750ml', 9, '5200.00', 1, '2025-01-01 10:02:00'),
		(79, 'RH7A', 'Ron Habanclub 7 Años 750ml', 9, '6800.00', 1, '2025-01-01 10:03:00'),
		(80, 'RCLA', 'Ron Cubano Legendario Añejo', 9, '6200.00', 1, '2025-01-01 10:04:00'),
		(81, 'RHBL', 'Ron Habanclub Blanco', 9, '4200.00', 1, '2025-01-01 10:05:00'),
		(82, 'RSCR', 'Ron Santiago de Cuba 7 Años', 9, '7200.00', 1, '2025-01-01 10:06:00'),
		(83, 'GMAR', 'Galletas María (Diferentes Sabores)', 9, '900.00', 1, '2025-01-01 10:07:00'),
		(84, 'CTMY', 'Caramelos Tomy (Diferentes Sabores)', 9, '450.00', 1, '2025-01-01 10:08:00'),
		(85, 'GDL3', 'Galletas Dulce de Leche 350g', 9, '990.00', 1, '2025-01-01 10:09:00'),
		(86, 'GMNT', 'Galletas de Manteca 300g', 9, '780.00', 1, '2025-01-01 10:10:00'),
		(87, 'GINT', 'Galletas Integrales 400g', 9, '920.00', 1, '2025-01-01 10:11:00'),
		(88, 'GCOC', 'Galletas de Coco 300g', 9, '890.00', 1, '2025-01-01 10:12:00'),
		(89, 'GCHO', 'Galletas de Chocolate 350g', 9, '950.00', 1, '2025-01-01 10:13:00'),
		(90, 'GVAI', 'Galletas Vainilla 350g', 9, '850.00', 1, '2025-01-01 10:14:00'),
		(91, 'DET5', 'Detergente en polvo 500g', 12, '450.00', 1, '2025-01-01 10:15:00'),
		(92, 'DET1', 'Detergente en polvo 1kg', 12, '650.00', 1, '2025-01-01 10:16:00'),
		(93, 'DET3', 'Detergente en polvo 3kg', 12, '1450.00', 1, '2025-01-01 10:17:00'),
		(94, 'JABP', 'Jabón de pasta (Bolívar) 300g', 12, '280.00', 1, '2025-01-01 10:18:00'),
		(95, 'JABL', 'Jabón de lavar (Líquido) 1L', 12, '520.00', 1, '2025-01-01 10:19:00'),
		(96, 'CLOR', 'Cloro 1L', 12, '220.00', 1, '2025-01-01 10:20:00'),
		(97, 'CLR3', 'Cloro 3L', 12, '550.00', 1, '2025-01-01 10:21:00'),
		(98, 'SUAV', 'Suavizante para ropa 1L', 12, '580.00', 1, '2025-01-01 10:22:00'),
		(99, 'LIMP', 'Limpiador multiusos 500ml', 12, '320.00', 1, '2025-01-01 10:23:00'),
		(100, 'DESG', 'Desengrasante 500ml', 12, '380.00', 1, '2025-01-01 10:24:00'),
		(101, 'ESPJ', 'Esponja para platos (3 unidades)', 12, '150.00', 1, '2025-01-01 10:25:00'),
		(102, 'PLH0', 'Papel higiénico (12 rollos)', 12, '1250.00', 1, '2025-01-01 10:26:00'),
		(103, 'SERV', 'Servilletas 100 unidades', 12, '280.00', 1, '2025-01-01 10:27:00'),
		(104, 'JABC', 'Jabón de baño (3 unidades)', 12, '390.00', 1, '2025-01-01 10:28:00'),
		(105, 'PS00', 'Pasta dental 90ml', 12, '320.00', 1, '2025-01-01 10:29:00'),
		(106, 'PS01', 'Pasta dental 120ml', 12, '500.00', 1, '2025-01-01 10:30:00'),
		(107, 'PLH1', 'Papel higiénico (4 rollos)', 12, '600.00', 1, '2025-01-01 10:31:00'),
		(108, 'COT0', 'Colcha de trapear', 12, '350.00', 1, '2025-01-01 10:32:00');
		";
        
        $sqlContent .= "-- Insertar servicios\n";
        $sqlContent .= $insertServicios . "\n\n";
        
        if ($mysqli->query($insertServicios)) {
            writeLog("Servicios insertados en 'clasif_serv'", 'SUCCESS');
        }
        
        // 5. Tabla: clasif_usuarios
        $sql = "CREATE TABLE `clasif_usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(50) NOT NULL,
            `apellidos` varchar(100) NOT NULL,
            `no_ci` varchar(11) NOT NULL,
            `direccion_particular` text DEFAULT NULL,
            `telefono_contacto` varchar(15) DEFAULT NULL,
            `fecha_registro` datetime DEFAULT current_timestamp(),
            `rol_id` int(11) DEFAULT NULL,
            `foto` longtext DEFAULT NULL,
            `usuario` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `no_ci` (`no_ci`),
            UNIQUE KEY `usuario` (`usuario`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: clasif_usuarios\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'clasif_usuarios', 'fields' => 14];
            writeLog("Tabla 'clasif_usuarios' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla clasif_usuarios: " . $mysqli->error);
        }
        
        // 6. Tabla: configuracion_sistema
        $sql = "CREATE TABLE `configuracion_sistema` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre_empresa` varchar(200) NOT NULL,
            `nombre_proyecto` varchar(200) NOT NULL,
            `logo` longtext DEFAULT NULL,
            `fondo_web` longtext DEFAULT NULL,
            `direccion` text DEFAULT NULL,
            `telefono` varchar(15) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `cuenta_bancaria` varchar(50) DEFAULT NULL,
            `banco` varchar(100) DEFAULT NULL,
            `sucursal` varchar(100) DEFAULT NULL,
            `cod_reeup` varchar(50) DEFAULT NULL,
            `cod_nit` varchar(11) DEFAULT NULL,
            `sitio_web` varchar(255) DEFAULT NULL,
            `fecha_inicio_operaciones` DATE NOT NULL,
            `director_nombre` varchar(100) DEFAULT NULL,
            `director_ci` varchar(11) DEFAULT NULL,
            `director_telefono` varchar(15) DEFAULT NULL,
            `facturador_nombre` varchar(100) DEFAULT NULL,
            `facturador_ci` varchar(11) DEFAULT NULL,
            `facturador_telefono` varchar(15) DEFAULT NULL,
            `whatsapp_ON` tinyint(1) DEFAULT 1,
            `whatsapp_numero` varchar(20) DEFAULT NULL,
            `modo_mantenimiento` tinyint(1) NOT NULL DEFAULT 0,
			`restabpw` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: configuracion_sistema\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'configuracion_sistema', 'fields' => 22];
            writeLog("Tabla 'configuracion_sistema' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla configuracion_sistema: " . $mysqli->error);
        }

        // 6.1. Tabla: historico_operaciones
        $sql = "CREATE TABLE `historico_operaciones` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `operacion` varchar(100) NOT NULL,
            `descripcion` text DEFAULT NULL,
            `fecha_hora` datetime DEFAULT current_timestamp(),
            `usuario_id` int(11) DEFAULT NULL,
            `usuario_nombre` varchar(150) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: historico_operaciones\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'historico_operaciones', 'fields' => 7];
            writeLog("Tabla 'historico_operaciones' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla historico_operaciones: " . $mysqli->error);
        }
		
        // 7. Tabla: historico_CIERRES
        $sql = "CREATE TABLE `historico_cierres` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tipo` int(1) DEFAULT 1 NOT NULL,
            `periodo_mes` INT NULL, -- 1 al 12 (solo para tipo MES)
            `periodo_anio` INT NOT NULL, -- Ej: 2026
            `fecha_ejecucion` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `usuario_id` INT NOT NULL,
            `total_facturas` INT DEFAULT 0,
            `importe_total` DECIMAL(15,2) DEFAULT 0.00,
            `cant_pagadas` INT DEFAULT 0,
            `cant_contabilizadas` INT DEFAULT 0,
            `observaciones` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $sqlContent .= "-- Tabla: historico_cierres\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'historico_cierres', 'fields' => 7];
            writeLog("Tabla 'historico_cierres' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla historico_cierres: " . $mysqli->error);
        }
        
        // 8. Tabla: tbl_fact
        $sql = "CREATE TABLE `tbl_fact` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `no_fact` varchar(20) NOT NULL,
            `cliente_id` int(11) DEFAULT NULL,
            `tipo_pago_id` int(11) DEFAULT NULL,
            `subtotal` decimal(10,2) NOT NULL,
            `total_general` decimal(10,2) NOT NULL,
            `usuario_id` int(11) DEFAULT NULL,
            `estado` enum('PENDIENTE','CONTABILIZADA','ANULADA','PAGADA', 'CERRADA') DEFAULT 'PENDIENTE',
            `fecha_emision` datetime NOT NULL,
            `fecha_contabilizacion` datetime DEFAULT NULL,
            `fecha_pago` datetime DEFAULT NULL,
            `Ref_pago` varchar(100) DEFAULT NULL,
            `observaciones` text DEFAULT NULL,
            `usuario_contabilizacion` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `no_fact` (`no_fact`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: tbl_fact\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'tbl_fact', 'fields' => 14];
            writeLog("Tabla 'tbl_fact' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla tbl_fact: " . $mysqli->error);
        }
        
        // 9. Tabla: tbl_fact_detalle
        $sql = "CREATE TABLE `tbl_fact_detalle` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `factura_id` int(11) DEFAULT NULL,
            `servicio_id` int(11) DEFAULT NULL,
            `cantidad` int(11) NOT NULL,
            `precio_unitario` decimal(10,2) NOT NULL,
            `total_linea` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: tbl_fact_detalle\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'tbl_fact_detalle', 'fields' => 6];
            writeLog("Tabla 'tbl_fact_detalle' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla tbl_fact_detalle: " . $mysqli->error);
        }
        
        // 10. Tabla: tbl_planes
        $sql = "CREATE TABLE `tbl_planes` (
            `anio` int(11) NOT NULL AUTO_INCREMENT,
            `mes_plan` int(11) NOT NULL,
            `importe` decimal(10,2) DEFAULT NULL,
            `activo` tinyint(1) DEFAULT 1,
            `observaciones` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`anio`,`mes_plan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: tbl_planes\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'tbl_planes', 'fields' => 5];
            writeLog("Tabla 'tbl_planes' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla tbl_planes: " . $mysqli->error);
        }
        
        // 11. Tabla: tipos_pago
        $sql = "CREATE TABLE `tipos_pago` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `descripcion` varchar(100) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $sqlContent .= "-- Tabla: tipos_pago\n";
        $sqlContent .= $sql . "\n\n";
        
        if ($mysqli->query($sql)) {
            $tablesCreated[] = ['name' => 'tipos_pago', 'fields' => 3];
            writeLog("Tabla 'tipos_pago' creada", 'SUCCESS');
        } else {
            throw new Exception("Error al crear tabla tipos_pago: " . $mysqli->error);
        }
        
        // Insertar tipos de pago
        $insertTiposPago = "INSERT INTO `tipos_pago` (`id`, `codigo`, `descripcion`) VALUES
        (1, 'ABO', 'ABONO'),
        (2, 'ANT', 'ANTICIPO'),
        (3, 'BIM', 'BILLETERA MÓVIL'),
        (4, 'CHC', 'CHEQUE CERTIFICADO'),
        (5, 'CRE', 'CRÉDITO COMERCIAL'),
        (6, 'DEP', 'DEPÓSITO BANCARIO'),
        (7, 'EFE', 'EFECTIVO'),
        (8, 'LET', 'LETRA DE CAMBIO'),
        (9, 'OTR', 'OTROS'),
        (10, 'PCO', 'PAGO CONTRA ENTREGA'),
        (11, 'PDI', 'PAGO DIGITAL'),
        (12, 'PEI', 'PAGO EN LÍNEA'),
        (13, 'PM', 'PAGO MÓVIL (TRANSFERMÓVIL)'),
        (14, 'PPA', 'PAGO PARCIAL'),
        (15, 'PAY', 'PAYPAL'),
        (16, 'TCR', 'TARJETA DE CRÉDITO'),
        (17, 'TDB', 'TARJETA DE DÉBITO'),
        (18, 'TIE', 'TARJETA INTERNACIONAL (MASTERCARD/VISA/AMEX)'),
        (19, 'ACH', 'TRANSFERENCIA ACH'),
        (20, 'TRA', 'TRANSFERENCIA BANCARIA'),
        (21, 'VAR', 'VARIOS MÉTODOS');";
        
        $sqlContent .= "-- Insertar tipos de pago\n";
        $sqlContent .= $insertTiposPago . "\n\n";
        
        if ($mysqli->query($insertTiposPago)) {
            writeLog("Tipos de pago insertados", 'SUCCESS');
        }
        
        // ============================================
        // AGREGAR RESTRICCIONES DE CLAVE FORÁNEA
        // ============================================
        
        $sqlContent .= "-- ============================================\n";
        $sqlContent .= "-- RESTRICCIONES DE CLAVE FORÁNEA\n";
        $sqlContent .= "-- ============================================\n\n";
        
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // 1. Usuarios -> Roles
        $fk0 = "ALTER TABLE clasif_usuarios
        ADD CONSTRAINT fk_usuario_rol
        FOREIGN KEY (rol_id) 
        REFERENCES clasif_rol(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Usuarios -> Roles\n";
        $sqlContent .= $fk0 . "\n\n";
        
        if ($mysqli->query($fk0)) {
            writeLog("Restricción fk_usuario_rol agregada", 'SUCCESS');
        }
        
        // 2. Servicios -> Categorías
        $fk1 = "ALTER TABLE clasif_serv
        ADD CONSTRAINT fk_servicio_categoria
        FOREIGN KEY (categoria_id) 
        REFERENCES clasif_cat_de_serv(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Servicios -> Categorías\n";
        $sqlContent .= $fk1 . "\n\n";
        
        if ($mysqli->query($fk1)) {
            writeLog("Restricción fk_servicio_categoria agregada", 'SUCCESS');
        }
        
        // 3. Facturas -> Clientes
        $fk2 = "ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_cliente
        FOREIGN KEY (cliente_id) 
        REFERENCES clasif_clientes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Facturas -> Clientes\n";
        $sqlContent .= $fk2 . "\n\n";
        
        if ($mysqli->query($fk2)) {
            writeLog("Restricción fk_factura_cliente agregada", 'SUCCESS');
        }
        
        // 4. Facturas -> Tipos de pago
        $fk3 = "ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_tipo_pago
        FOREIGN KEY (tipo_pago_id) 
        REFERENCES tipos_pago(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Facturas -> Tipos de pago\n";
        $sqlContent .= $fk3 . "\n\n";
        
        if ($mysqli->query($fk3)) {
            writeLog("Restricción fk_factura_tipo_pago agregada", 'SUCCESS');
        }
        
        // 5. Facturas -> Usuario creador
        $fk4 = "ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_usuario
        FOREIGN KEY (usuario_id) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Facturas -> Usuario creador\n";
        $sqlContent .= $fk4 . "\n\n";
        
        if ($mysqli->query($fk4)) {
            writeLog("Restricción fk_factura_usuario agregada", 'SUCCESS');
        }
        
        // 6. Facturas -> Usuario contabilizador
        $fk5 = "ALTER TABLE tbl_fact
        ADD CONSTRAINT fk_factura_usuario_cont
        FOREIGN KEY (usuario_contabilizacion) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Facturas -> Usuario contabilizador\n";
        $sqlContent .= $fk5 . "\n\n";
        
        if ($mysqli->query($fk5)) {
            writeLog("Restricción fk_factura_usuario_cont agregada", 'SUCCESS');
        }
        
        // 7. Detalle factura -> Factura
        $fk6 = "ALTER TABLE tbl_fact_detalle
        ADD CONSTRAINT fk_detalle_factura
        FOREIGN KEY (factura_id) 
        REFERENCES tbl_fact(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Detalle factura -> Factura\n";
        $sqlContent .= $fk6 . "\n\n";
        
        if ($mysqli->query($fk6)) {
            writeLog("Restricción fk_detalle_factura agregada", 'SUCCESS');
        }
        
        // 8. Detalle factura -> Servicio
        $fk7 = "ALTER TABLE tbl_fact_detalle
        ADD CONSTRAINT fk_detalle_servicio
        FOREIGN KEY (servicio_id) 
        REFERENCES clasif_serv(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Detalle factura -> Servicio\n";
        $sqlContent .= $fk7 . "\n\n";
        
        if ($mysqli->query($fk7)) {
            writeLog("Restricción fk_detalle_servicio agregada", 'SUCCESS');
        }
        
        // 9. Historial -> Usuario
        $fk8 = "ALTER TABLE historico_operaciones
        ADD CONSTRAINT fk_historico_usuario
        FOREIGN KEY (usuario_id) 
        REFERENCES clasif_usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Historial -> Usuario\n";
        $sqlContent .= $fk8 . "\n\n";
        
        if ($mysqli->query($fk8)) {
            writeLog("Restricción fk_historico_usuario agregada", 'SUCCESS');
        }
        
        // 10. Historial de cierres -> Usuario
        $fk9 = "ALTER TABLE `historico_cierres`
        ADD CONSTRAINT `historico_cierres_ibfk_1`
        FOREIGN KEY (`usuario_id`) 
        REFERENCES `clasif_usuarios`(`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;";
        
        $sqlContent .= "-- Restricción: Historial de cierres -> Usuario\n";
        $sqlContent .= $fk9 . "\n\n";
        
        if ($mysqli->query($fk9)) {
            writeLog("Restricción historico_cierres_ibfk_1 agregada", 'SUCCESS');
        } else {
            writeLog("Error al agregar restricción historico_cierres_ibfk_1: " . $mysqli->error, 'WARNING');
        }
        
        // ============================================
        // AGREGAR ÍNDICES
        // ============================================
        
        $sqlContent .= "-- ============================================\n";
        $sqlContent .= "-- ÍNDICES PARA MEJORAR RENDIMIENTO\n";
        $sqlContent .= "-- ============================================\n\n";
        
        // 1. Índice en clasif_serv para búsquedas por categoría
        $idx1 = "CREATE INDEX idx_clasif_serv_categoria ON clasif_serv(categoria_id);";
        $sqlContent .= $idx1 . "\n";
        
        if ($mysqli->query($idx1)) {
            writeLog("Índice idx_clasif_serv_categoria creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_clasif_serv_categoria: " . $mysqli->error, 'WARNING');
        }
        
        // 2. Índice en tbl_fact para búsquedas por cliente
        $idx2 = "CREATE INDEX idx_tbl_fact_cliente ON tbl_fact(cliente_id);";
        $sqlContent .= $idx2 . "\n";
        
        if ($mysqli->query($idx2)) {
            writeLog("Índice idx_tbl_fact_cliente creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_tbl_fact_cliente: " . $mysqli->error, 'WARNING');
        }
        
        // 3. Índice en tbl_fact para búsquedas por fecha
        $idx3 = "CREATE INDEX idx_tbl_fact_fecha ON tbl_fact(fecha_emision);";
        $sqlContent .= $idx3 . "\n";
        
        if ($mysqli->query($idx3)) {
            writeLog("Índice idx_tbl_fact_fecha creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_tbl_fact_fecha: " . $mysqli->error, 'WARNING');
        }
        
        // 4. Índice en tbl_fact para búsquedas por estado
        $idx4 = "CREATE INDEX idx_tbl_fact_estado ON tbl_fact(estado);";
        $sqlContent .= $idx4 . "\n";
        
        if ($mysqli->query($idx4)) {
            writeLog("Índice idx_tbl_fact_estado creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_tbl_fact_estado: " . $mysqli->error, 'WARNING');
        }
        
        // 5. Índice en tbl_fact_detalle para búsquedas por factura
        $idx5 = "CREATE INDEX idx_tbl_fact_detalle_factura ON tbl_fact_detalle(factura_id);";
        $sqlContent .= $idx5 . "\n";
        
        if ($mysqli->query($idx5)) {
            writeLog("Índice idx_tbl_fact_detalle_factura creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_tbl_fact_detalle_factura: " . $mysqli->error, 'WARNING');
        }
        
        // 6. Índice en clasif_clientes para búsquedas por código
        $idx6 = "CREATE INDEX idx_clasif_clientes_codigo ON clasif_clientes(codigo);";
        $sqlContent .= $idx6 . "\n";
        
        if ($mysqli->query($idx6)) {
            writeLog("Índice idx_clasif_clientes_codigo creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_clasif_clientes_codigo: " . $mysqli->error, 'WARNING');
        }
        
        // 7. Índice en clasif_serv para búsquedas por código
        $idx7 = "CREATE INDEX idx_clasif_serv_codigo ON clasif_serv(codigo);";
        $sqlContent .= $idx7 . "\n\n";
        
        if ($mysqli->query($idx7)) {
            writeLog("Índice idx_clasif_serv_codigo creado", 'SUCCESS');
        } else {
            writeLog("Error creando índice idx_clasif_serv_codigo: " . $mysqli->error, 'WARNING');
        }
        
        // Finalizar transacción
        $sqlContent .= "-- Finalizar transacción\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sqlContent .= "COMMIT;\n";
        
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        $mysqli->query("COMMIT");
        
        // ============================================
        // GUARDAR ARCHIVO SQL
        // ============================================

        // Definir la carpeta y ruta del archivo
        $folderPath = __DIR__ . '/../MYSQL_BD/';
        $sqlFile = $folderPath . 'scriptBD.sql';

        // Crear la carpeta si no existe
        if (!is_dir($folderPath)) {
            if (!mkdir($folderPath, 0777, true)) {
                throw new Exception("No se pudo crear la carpeta MYSQL_BD.");
            }
            writeLog("Carpeta 'MYSQL_BD' creada exitosamente", 'SUCCESS');
        }

        // Eliminar el archivo si ya existe
        if (file_exists($sqlFile)) {
            if (!unlink($sqlFile)) {
                throw new Exception("No se pudo eliminar el archivo SQL existente.");
            }
            writeLog("Archivo SQL existente eliminado", 'INFO');
        }

        // Crear el archivo SQL
        if (file_put_contents($sqlFile, $sqlContent) === false) {
            throw new Exception("No se pudo crear el archivo SQL.");
        }

        writeLog("Archivo SQL 'scriptBD.sql' creado exitosamente en la carpeta MYSQL_BD", 'SUCCESS');
        
        // Crear archivo de configuración
        $configContent = "<?php\n";
        $configContent .= "// Archivo de configuración - Sistema de Facturación SISFACT-PDL VISIONES\n";
        $configContent .= "// Generado automáticamente el " . date('d-m-Y H:i:s') . "\n\n";
        $configContent .= "// Configuración de base de datos\n";
        $configContent .= "define('DB_HOST', '" . addslashes($host) . "');\n";
        $configContent .= "define('DB_PORT', '" . addslashes($port) . "');\n";
        $configContent .= "define('DB_USER', '" . addslashes($user) . "');\n";
        $configContent .= "define('DB_PASS', '" . addslashes($pass) . "');\n";
        $configContent .= "define('DB_NAME', '" . addslashes($name) . "');\n";
        $configContent .= "define('DB_PREFIX', ''); // Prefijo vacío por ahora\n\n";
        $configContent .= "// Configuración de la aplicación\n";
        $configContent .= "define('APP_NAME', 'Sistema de Facturación SISFACT-PDL VISIONES');\n";
        $configContent .= "define('APP_VERSION', '2.3.3');\n";
        $configContent .= "define('TIMEZONE', 'America/Havana');\n";
        $configContent .= "date_default_timezone_set(TIMEZONE);\n\n";
        $configContent .= "// URL base de la aplicación\n";
        $configContent .= "\$protocol = isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';\n";
        $configContent .= "\$host = \$_SERVER['HTTP_HOST'];\n";
        $configContent .= "\$path = rtrim(dirname(\$_SERVER['SCRIPT_NAME']), '/\\\\');\n";
        $configContent .= "define('BASE_URL', \$protocol . \$host . \$path . '/');\n\n";
        $configContent .= "// Iniciar sesión si no está iniciada\n";
        $configContent .= "if (session_status() === PHP_SESSION_NONE) {\n";
        $configContent .= "    session_start();\n";
        $configContent .= "}\n\n";
        $configContent .= "// Conexión a la base de datos\n";
        $configContent .= "function getDBConnection() {\n";
        $configContent .= "    static \$conn = null;\n";
        $configContent .= "    if (\$conn === null) {\n";
        $configContent .= "        \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);\n";
        $configContent .= "        if (\$conn->connect_error) {\n";
        $configContent .= "            die('Error de conexión: ' . \$conn->connect_error);\n";
        $configContent .= "        }\n";
        $configContent .= "        \$conn->set_charset('utf8mb4');\n";
        $configContent .= "    }\n";
        $configContent .= "    return \$conn;\n";
        $configContent .= "}\n\n";
        $configContent .= "// Función para escribir logs del sistema\n";
        $configContent .= "function writeSystemLog(\$accion, \$detalles = '', \$usuario_id = null) {\n";
        $configContent .= "    \$conn = getDBConnection();\n";
        $configContent .= "    \$ip = \$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';\n";
        $configContent .= "    \$user_agent = \$_SERVER['HTTP_USER_AGENT'] ?? '';\n";
        $configContent .= "    \n";
        $configContent .= "    \$stmt = \$conn->prepare('INSERT INTO historico_operaciones (usuario_id, operacion, descripcion, ip_address) VALUES (?, ?, ?, ?)');\n";
        $configContent .= "    \$stmt->bind_param('isss', \$usuario_id, \$accion, \$detalles, \$ip);\n";
        $configContent .= "    \$stmt->execute();\n";
        $configContent .= "    \$stmt->close();\n";
        $configContent .= "}\n\n";
        $configContent .= "// Función para obtener configuración del sistema\n";
        $configContent .= "function getSystemConfig() {\n";
        $configContent .= "    \$conn = getDBConnection();\n";
        $configContent .= "    \$result = \$conn->query('SELECT * FROM configuracion_sistema WHERE id = 1 LIMIT 1');\n";
        $configContent .= "    return \$result->num_rows > 0 ? \$result->fetch_assoc() : null;\n";
        $configContent .= "}\n";
        $configContent .= "?>";
        
        // ============================================
        // GUARDAR ARCHIVO DE CONFIGURACIÓN (en raíz)
        // ============================================

        $configFile = __DIR__ . '/../config.php';

        // Eliminar el archivo si ya existe
        if (file_exists($configFile)) {
            if (!unlink($configFile)) {
                throw new Exception("No se pudo eliminar el archivo de configuración existente.");
            }
            writeLog("Archivo de configuración existente eliminado", 'INFO');
        }

        // Crear el archivo de configuración
        if (file_put_contents($configFile, $configContent) === false) {
            throw new Exception("No se pudo crear el archivo de configuración.");
        }

        writeLog("Archivo de configuración 'config.php' creado exitosamente en la carpeta raíz", 'SUCCESS');

        $mysqli->close();
        
        writeLog("Base de datos creada exitosamente", 'SUCCESS');
        writeLog("Total tablas creadas: " . count($tablesCreated), 'INFO');
        
        // Preparar información detallada de tablas
        $tableDetails = [];
        foreach ($tablesCreated as $table) {
            $tableDetails[] = [
                'name' => $table['name'],
                'fields' => $table['fields']
            ];
        }

        // Asegurarnos de que no hay salida antes del JSON
        if (ob_get_length()) ob_clean();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Base de datos creada exitosamente',
            'tables' => $tableDetails,
            'table_count' => count($tablesCreated),
            'sql_file' => 'scriptBD.sql generado',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        // Rollback en caso de error
        if (isset($mysqli)) {
            $mysqli->query("ROLLBACK");
        }
        
        writeLog('Error al crear base de datos: ' . $e->getMessage(), 'ERROR');
        
        // Limpiar buffer antes del error
        if (ob_get_length()) ob_clean();
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

function createAdminHandler() {
    writeLog('Iniciando creación de usuario administrador', 'INFO');
    
    // Limpiar buffer
    if (ob_get_length()) ob_clean();
    
    $host = $_POST['db_host'] ?? 'localhost';
    $port = $_POST['db_port'] ?? 3306;
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? 'sisfact_imdl';
    
    $adminData = [
        'nombre' => $_POST['admin_nombre'] ?? 'Administrador',
        'apellidos' => $_POST['admin_apellidos'] ?? 'Del Sistema',
        'no_ci' => $_POST['admin_ci'] ?? '00000000000',
        'usuario' => $_POST['admin_usuario'] ?? 'admin',
        'email' => $_POST['admin_email'] ?? 'admin@ejemplo.com',
        'password' => $_POST['admin_password'] ?? 'admin123'
    ];
    
    try {
        // Conectar a la base de datos
        $mysqli = new mysqli($host, $user, $pass, $name, $port);
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        writeLog("Conectado a la base de datos '$name'", 'INFO');
        
        // Obtener ID del rol Admin (de la tabla clasif_rol del dump)
        $result = $mysqli->query("SELECT id FROM clasif_rol WHERE codigo = 'Admin' LIMIT 1");
        if ($result->num_rows === 0) {
            throw new Exception("Rol Admin no encontrado");
        }
        
        $row = $result->fetch_assoc();
        $rolId = $row['id'];
        writeLog("ID del rol Admin obtenido: $rolId", 'INFO');
        
        // Encriptar contraseña
        $passwordHash = password_hash($adminData['password'], PASSWORD_DEFAULT);
        
        // Insertar usuario administrador
        $sql = "INSERT INTO clasif_usuarios 
                (nombre, apellidos, no_ci, usuario, password, email, rol_id, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            'ssssssi',
            $adminData['nombre'],
            $adminData['apellidos'],
            $adminData['no_ci'],
            $adminData['usuario'],
            $passwordHash,
            $adminData['email'],
            $rolId
        );
        
        if (!$stmt->execute()) {
            // Si el usuario ya existe, actualizarlo
            if ($mysqli->errno == 1062) {
                $sql = "UPDATE clasif_usuarios 
                        SET nombre = ?, apellidos = ?, no_ci = ?, password = ?, email = ?, rol_id = ?
                        WHERE usuario = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param(
                    'sssssis',
                    $adminData['nombre'],
                    $adminData['apellidos'],
                    $adminData['no_ci'],
                    $passwordHash,
                    $adminData['email'],
                    $rolId,
                    $adminData['usuario']
                );
                $stmt->execute();
                writeLog("Usuario '{$adminData['usuario']}' actualizado", 'INFO');
            } else {
                throw new Exception("Error al crear usuario: " . $mysqli->error);
            }
        } else {
            writeLog("Usuario '{$adminData['usuario']}' creado exitosamente", 'SUCCESS');
        }
        
        $usuarioId = $stmt->insert_id ?: $mysqli->insert_id;
        
        // Registrar la instalación en el historial
        $operacion = "INSTALACION_SISTEMA";
        $descripcion = "Sistema instalado - Usuario administrador creado: " . $adminData['usuario'];
        $usuarioNombre = $adminData['nombre'] . ' ' . $adminData['apellidos'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt2 = $mysqli->prepare("INSERT INTO historico_operaciones 
                                  (usuario_id, operacion, descripcion, usuario_nombre, ip_address) 
                                  VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param('issss', $usuarioId, $operacion, $descripcion, $usuarioNombre, $ip);
        $stmt2->execute();
        $stmt2->close();
        
        writeLog("Operación de instalación registrada en el historial", 'INFO');
        
        $stmt->close();
        $mysqli->close();
        
        writeLog("Usuario administrador creado exitosamente", 'SUCCESS');
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Usuario administrador creado exitosamente'
        ]);
        
    } catch (Exception $e) {
        writeLog('Error al crear usuario administrador: ' . $e->getMessage(), 'ERROR');
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

function saveCompanyConfigHandler() {
    writeLog('Guardando configuración de empresa...', 'INFO');
    
    // Limpiar buffer
    if (ob_get_length()) ob_clean();
    
    $host = $_POST['db_host'] ?? 'localhost';
    $port = $_POST['db_port'] ?? 3306;
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? 'sisfact_imdl';
    
    // Recoger TODOS los campos, incluyendo fecha_inicio_operaciones
    $companyData = [
        'nombre_empresa' => $_POST['nombre_empresa'] ?? 'Mi Empresa',
        'nombre_proyecto' => $_POST['nombre_proyecto'] ?? 'Sistema de Facturación',
        'direccion' => $_POST['direccion'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'email' => $_POST['email'] ?? '',
        'sitio_web' => $_POST['sitio_web'] ?? '',
        'cod_reeup' => $_POST['cod_reeup'] ?? '',
        'cod_nit' => $_POST['cod_nit'] ?? '',
        'cuenta_bancaria' => $_POST['cuenta_bancaria'] ?? '',
        'banco' => $_POST['banco'] ?? '',
        'sucursal' => $_POST['sucursal'] ?? '',
        'fecha_inicio_operaciones' => $_POST['fecha_inicio_operaciones'] ?? date('Y-m-01'), // ✅ NUEVO
        'director_nombre' => $_POST['director_nombre'] ?? '',
        'director_ci' => $_POST['director_ci'] ?? '',
        'director_telefono' => $_POST['director_telefono'] ?? '',
        'facturador_nombre' => $_POST['facturador_nombre'] ?? '',
        'facturador_ci' => $_POST['facturador_ci'] ?? '',
        'facturador_telefono' => $_POST['facturador_telefono'] ?? ''
    ];
    
    writeLog("Datos de empresa recibidos. Fecha inicio: " . $companyData['fecha_inicio_operaciones'], 'INFO');
    
    try {
        // Conectar a la base de datos
        $mysqli = new mysqli($host, $user, $pass, $name, $port);
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        // Verificar si la tabla tiene el campo fecha_inicio_operaciones
        $result = $mysqli->query("SHOW COLUMNS FROM configuracion_sistema LIKE 'fecha_inicio_operaciones'");
        if ($result->num_rows === 0) {
            // Si no existe el campo, agregarlo
            writeLog("Campo fecha_inicio_operaciones no existe, agregando...", 'WARNING');
            $mysqli->query("ALTER TABLE configuracion_sistema ADD COLUMN fecha_inicio_operaciones DATE NOT NULL AFTER sitio_web");
            writeLog("Campo fecha_inicio_operaciones agregado a la tabla", 'SUCCESS');
        }
        
        // Verificar si ya existe configuración
        $result = $mysqli->query("SELECT id FROM configuracion_sistema LIMIT 1");
        
        // Construir la consulta SQL dinámicamente
        if ($result->num_rows > 0) {
            // Actualizar configuración existente
            $sql = "UPDATE configuracion_sistema SET ";
            $params = [];
            $types = '';
            
            foreach ($companyData as $key => $value) {
                $sql .= "{$key} = ?, ";
                $params[] = $value;
                $types .= 's'; // Todos los campos son strings
            }
            
            $sql = rtrim($sql, ', ');
            $sql .= " WHERE id = 1";
            
            writeLog("Actualizando configuración de empresa existente", 'INFO');
        } else {
            // Insertar nueva configuración
            $keys = implode(', ', array_keys($companyData));
            $placeholders = implode(', ', array_fill(0, count($companyData), '?'));
            
            $sql = "INSERT INTO configuracion_sistema ({$keys}) VALUES ({$placeholders})";
            $params = array_values($companyData);
            $types = str_repeat('s', count($companyData));
            
            writeLog("Insertando nueva configuración de empresa", 'INFO');
        }
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $mysqli->error);
        }
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar configuración: " . $mysqli->error);
        }
        
        writeLog("Configuración de empresa guardada exitosamente", 'SUCCESS');
        writeLog("Fecha inicio operaciones guardada: " . $companyData['fecha_inicio_operaciones'], 'INFO');
        
        // Registrar en logs
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $accion = "CONFIGURACION_EMPRESA_GUARDADA";
        $detalles = "Configuración de empresa actualizada. Fecha inicio: " . $companyData['fecha_inicio_operaciones'];
        $stmt2 = $mysqli->prepare("INSERT INTO historico_operaciones (operacion, descripcion, ip_address) VALUES (?, ?, ?)");
        $stmt2->bind_param('sss', $accion, $detalles, $ip);
        $stmt2->execute();
        $stmt2->close();
        
        $stmt->close();
        $mysqli->close();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Configuración de empresa guardada exitosamente',
            'fecha_inicio' => $companyData['fecha_inicio_operaciones']
        ]);
        
    } catch (Exception $e) {
        writeLog('Error al guardar configuración de empresa: ' . $e->getMessage(), 'ERROR');
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
	


function saveConfigHandler() {
    writeLog('Iniciando guardado de configuración del sistema', 'INFO');
    
    // Limpiar cualquier salida existente
    if (ob_get_length()) ob_clean();
    
    $host = $_POST['db_host'] ?? 'localhost';
    $port = $_POST['db_port'] ?? 3306;
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? 'sisfact_imdl';
    
    // Obtener datos del sistema
    $systemData = [
        'nombre_sistema' => $_POST['nombre_sistema'] ?? 'Sistema de Facturación',
        'version' => $_POST['version'] ?? '2.3.3',
        'items_por_pagina' => intval($_POST['items_por_pagina'] ?? 20),
        'timezone' => $_POST['timezone'] ?? 'America/Havana',
        // WhatsApp y Mantenimiento
        'whatsapp_ON' => isset($_POST['whatsapp_ON']) ? intval($_POST['whatsapp_ON']) : 1,
        'whatsapp_numero' => $_POST['whatsapp_numero'] ?? '',
        'modo_mantenimiento' => isset($_POST['modo_mantenimiento']) ? intval($_POST['modo_mantenimiento']) : 0,
		'restabpw' => isset($_POST['restabpw']) ? intval($_POST['restabpw']) : 0
    ];
    
    writeLog("Datos del sistema recibidos", 'INFO');
    
    try {
        // Conectar a la base de datos
        $mysqli = new mysqli($host, $user, $pass, $name, $port);
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        // Actualizar la configuración de empresa con nombre del sistema en nombre_proyecto
        // y agregar WhatsApp y Mantenimiento
                $sql = "UPDATE configuracion_sistema SET 
                    nombre_proyecto = ?,
                    whatsapp_ON = ?,
                    whatsapp_numero = ?,
                    modo_mantenimiento = ?,
                    restabpw = ?
                WHERE id = 1";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $mysqli->error);
        }
        
        $stmt->bind_param(
            'sisss',
            $systemData['nombre_sistema'],
            $systemData['whatsapp_ON'],
            $systemData['whatsapp_numero'],
            $systemData['modo_mantenimiento'],
            $systemData['restabpw']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar configuración: " . $mysqli->error);
        }
        
        writeLog("Configuración del sistema guardada", 'SUCCESS');
        
        // Actualizar archivo de configuración con timezone
        $configFile = __DIR__ . '/../config.php';
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            
            // Actualizar timezone
            $configContent = preg_replace(
                "/define\('TIMEZONE', '.*?'\);/",
                "define('TIMEZONE', '" . addslashes($systemData['timezone']) . "');",
                $configContent
            );
            
            file_put_contents($configFile, $configContent);
            writeLog("Configuración actualizada en config.php", 'INFO');
        }
        
        // Registrar en logs
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $accion = "INSTALACION_COMPLETADA";
        $detalles = "Configuración del sistema completada exitosamente";
        $stmt2 = $mysqli->prepare("INSERT INTO historico_operaciones (operacion, descripcion, ip_address) VALUES (?, ?, ?)");
        $stmt2->bind_param('sss', $accion, $detalles, $ip);
        $stmt2->execute();
        $stmt2->close();
        
        // Crear archivo .htaccess con mis necesidades de seguridad        
        global $CarpetaSistema;
        $htaccessContent = "# SOLUCIÓN FORZADA\n";
        $htaccessContent .= "Options -Indexes\n";
        $htaccessContent .= "ErrorDocument 404 /$CarpetaSistema/404.php\n";
        $htaccessContent .= "ErrorDocument 403 /$CarpetaSistema/403.php\n";
        $htaccessContent .= "<IfModule mod_rewrite.c>\n";
        $htaccessContent .= "    RewriteEngine On\n";    
        $htaccessContent .= "    # 1. Raíz del servidor → 403.php\n";
        $htaccessContent .= "    RewriteRule ^$ - [R=403,L]\n";    
        $htaccessContent .= "    # 2. Directorio $CarpetaSistema/ sin index.php → no-index.php\n";
        $htaccessContent .= "    # Primero deshabilitar DirectoryIndex para $CarpetaSistema/\n";
        $htaccessContent .= "    <If \"-f '%{DOCUMENT_ROOT}/$CarpetaSistema/index.php'\">\n";
        $htaccessContent .= "        # Si existe index.php, no hacer nada (Apache lo mostrará)\n";
        $htaccessContent .= "    </If>\n";
        $htaccessContent .= "    <Else>\n";
        $htaccessContent .= "        # Si NO existe index.php, mostrar no-index.php\n";
        $htaccessContent .= "        RewriteCond %{REQUEST_URI} ^/$CarpetaSistema/?$\n";
        $htaccessContent .= "        RewriteRule ^ /$CarpetaSistema/no-index.php [L]\n";
        $htaccessContent .= "    </Else>";    
        $htaccessContent .= "    # 3. Directorios fuera de $CarpetaSistema/ → 403.php\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_FILENAME} -d\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_URI} !^/$CarpetaSistema/\n";
        $htaccessContent .= "    RewriteRule ^ - [R=403,L]\n";    
        $htaccessContent .= "    # 4. Subdirectorios dentro de $CarpetaSistema/ → 403.php\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_FILENAME} -d\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_URI} ^/$CarpetaSistema/\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_URI} !^/$CarpetaSistema/?$\n";
        $htaccessContent .= "    RewriteRule ^ - [R=403,L]\n";    
        $htaccessContent .= "    # 5. Archivos no encontrados → 404.php\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccessContent .= "    RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccessContent .= "    RewriteRule ^ - [R=404,L]\n";
        $htaccessContent .= "</IfModule>\n";
        
		// ============================================
		// GUARDAR ARCHIVO .HTACCESS (en raíz)
		// ============================================

		$htaccessFile = __DIR__ . '/../.htaccess';

		// Eliminar el archivo si ya existe
		if (file_exists($htaccessFile)) {
			if (!unlink($htaccessFile)) {
				throw new Exception("No se pudo eliminar el archivo .htaccess existente.");
			}
			writeLog("Archivo .htaccess existente eliminado", 'INFO');
		}

		// Crear el archivo .htaccess
		if (file_put_contents($htaccessFile, $htaccessContent) === false) {
			throw new Exception("No se pudo crear el archivo .htaccess.");
		}

		writeLog("Archivo .htaccess creado exitosamente en la carpeta raíz", 'INFO');

        
        $stmt->close();
        $mysqli->close();
        
        writeLog('Configuración del sistema guardada exitosamente', 'SUCCESS');
        writeLog('INSTALACIÓN COMPLETADA EXITOSAMENTE', 'SUCCESS');
        
        // Asegurar que no hay salida antes del JSON
        if (ob_get_length()) ob_clean();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Configuración del sistema guardada exitosamente'
        ]);
        
    } catch (Exception $e) {
        writeLog('Error al guardar configuración del sistema: ' . $e->getMessage(), 'ERROR');
        
        // Limpiar buffer antes del error
        if (ob_get_length()) ob_clean();
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

function deleteInstallerHandler() {
    writeLog('Iniciando eliminación del instalador', 'INFO');
    
    // Limpiar buffer
    if (ob_get_length()) ob_clean();
    
    try {
        $installerFile = __FILE__;
        $backupFile = __DIR__ . '/instalador_backup_' . date('Ymd_His') . '.php';
        
        writeLog("Archivo instalador: $installerFile", 'INFO');
        writeLog("Archivo backup: $backupFile", 'INFO');
        
        // Crear copia de seguridad
        if (!copy($installerFile, $backupFile)) {
            throw new Exception("No se pudo crear la copia de seguridad.");
        }
        
        writeLog("Copia de seguridad creada", 'SUCCESS');
        
        // Intentar eliminar el instalador
        if (!unlink($installerFile)) {
            // Si no se puede eliminar, renombrar
            $renamedFile = __DIR__ . '/instalador_old_' . date('Ymd_His') . '.php';
            if (!rename($installerFile, $renamedFile)) {
                throw new Exception("No se pudo eliminar ni renombrar el instalador.");
            }
            writeLog("Instalador renombrado a: $renamedFile", 'SUCCESS');
        } else {
            writeLog("Instalador eliminado exitosamente", 'SUCCESS');
        }
        
        // También eliminar el archivo de logs si el usuario quiere
        $logFile = LOG_FILE;
        if (file_exists($logFile)) {
            // Renombrar logs para mantener historial
            $oldLogFile = __DIR__ . '/install_logs_' . date('Ymd_His') . '.txt';
            rename($logFile, $oldLogFile);
            writeLog("Logs movidos a: $oldLogFile", 'INFO');
        }
        
        writeLog('Proceso de eliminación completado', 'SUCCESS');
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Instalador eliminado correctamente'
        ]);
        
    } catch (Exception $e) {
        writeLog('Error al eliminar instalador: ' . $e->getMessage(), 'ERROR');
        
        sendJsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

// Función auxiliar para convertir límites de memoria
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    
    return $val;
}
?>