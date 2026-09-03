<?php
// NO se requiere ningún archivo externo que pueda definir $pdo
// Se usa config.php (NOMINAS/config.php) para las credenciales de la base de datos.

// Si no existe la configuración del sistema, avisar y redirigir al instalador
if (!file_exists(__DIR__ . '/nominas/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
        <title>Sistema sin configurar</title>
        <link rel="stylesheet" href="NOMINAS/css/font-awesome6.4.0/css/all.min.css">
        <script src="NOMINAS/js/sweetalert2.all.min.js"></script>
        <style>
            body {
                margin:0;
                padding:0;
                background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height:100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-cogs" style="color:#f87171"></i> Configuración no encontrada',
            html: 'No existe la <b>configuración del sistema</b>.<br>Deberá reinstalar la base de datos y sus archivos.',
            confirmButtonText: '<i class="fas fa-cogs"></i> Ir al Instalador',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6',
            allowOutsideClick: false,
            backdrop: 'rgba(0,0,0,0.85)'
        }).then(function (result) {
            if (result.isConfirmed) {
                var w = window.open('instalarbd/InstalarBD.php', '_blank');
                if (!w) { window.location.href = 'instalarbd/InstalarBD.php'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/nominas/config.php';
session_start();

// Configuración de conexión (desde config.php)
$host = DB_HOST;
$user = DB_USER;
$password = DB_PASS;  // Contraseña de MySQL (desde config.php)
$database = DB_NAME;

$server_status = [
    'php' => true,
    'mysql' => false,
    'mysql_error' => null
];

$sub_estados = [];     // estados de los subsistemas (código => 0/1)
$COMPANY_NAME = defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom';
$SITE_VERSION = defined('SITE_VERSION') ? SITE_VERSION : 'v2.0.1';
$SITE_NAME = $COMPANY_NAME . ' - Centro de Control.';
$SITE_EMAIL = defined('EMAIL_EMPRESA') ? EMAIL_EMPRESA : 'kakycu@gmail.com';
$SITE_PHONE = defined('TELEFONO_EMPRESA') ? TELEFONO_EMPRESA : '+53 5 2712861';
$SITE_PHONE_LINK = preg_replace('/[^0-9+]/', '', $SITE_PHONE);
$SLOGAN = defined('SLOGAN') ? SLOGAN : 'Eslogan Corporativo Identificativo.';

// Intentar conexión a MySQL
$conn = null;
try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        $server_status['mysql'] = false;
        $server_status['mysql_error'] = $conn->connect_error;
    } else {
        $server_status['mysql'] = true;
        
        // 1. Obtener estados de subsistemas
        $res = $conn->query("SELECT codigo, estado FROM subsistemas");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $sub_estados[$row['codigo']] = $row['estado'];
            }
        }
        
        // 2. Obtener configuración general (si la tabla existe)
        $checkTable = $conn->query("SHOW TABLES LIKE 'configuracion_general'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $configQuery = $conn->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'site_name', 'email_empresa', 'telefono_empresa', 'slogan', 'email_soporte')");
            if ($configQuery) {
                while ($row = $configQuery->fetch_assoc()) {
                    if ($row['parametro'] == 'nombre_empresa' && !empty($row['valor'])) {
                        $COMPANY_NAME = $row['valor'];
                        $SITE_NAME = $COMPANY_NAME . ' - Centro de Control.';
                    }
                    if ($row['parametro'] == 'site_name' && !empty($row['valor'])) {
                        $SITE_NAME = $row['valor'];
                    }
                    if ($row['parametro'] == 'email_empresa' && !empty($row['valor'])) {
                        $SITE_EMAIL = $row['valor'];
                    }
                    if ($row['parametro'] == 'email_soporte' && !empty($row['valor'])) {
                        $SITE_EMAIL = $row['valor'];
                    }
                    if ($row['parametro'] == 'telefono_empresa' && !empty($row['valor'])) {
                        $SITE_PHONE = $row['valor'];
                    }
                    if ($row['parametro'] == 'slogan' && !empty($row['valor'])) {
                        $SLOGAN = $row['valor'];
                    }
                }
            }
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $server_status['mysql'] = false;
    $server_status['mysql_error'] = $e->getMessage();
}

$current_year = date('Y');
$server_ok = $server_status['php'] && $server_status['mysql'];

// Función auxiliar para generar el badge según código de subsistema
function getBadge($codigo, $server_ok, $estados) {
    if (!$server_ok) {
        return '<span class="badge-inactivo">⚠️ NO DISPONIBLE</span>';
    }
    $activo = isset($estados[$codigo]) ? $estados[$codigo] : 0;
    if ($activo == 1) {
        return '<span class="badge-activo">✓ Activo</span>';
    } else {
        return '<span class="badge-inactivo">✗ Inactivo</span>';
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($SITE_NAME); ?> · <?php echo htmlspecialchars($SITE_VERSION); ?></title>
    <link rel="icon" type="image/x-icon" href="images/favicons/LogoCorto.ico">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <script src="js/sweetalert211.js"></script>
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/animate.min.css">
    
    <style>
        /* ===== ESTILOS BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
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
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        body::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(59, 130, 246, 0.05) 30px, rgba(59, 130, 246, 0.05) 60px),
                repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(139, 92, 246, 0.03) 35px, rgba(139, 92, 246, 0.03) 70px);
            pointer-events: none;
            animation: waveMove 12s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes waveMove {
            0%, 100% { background-position: 0 0, 0 0; }
            25% { background-position: 30px 30px, -30px -30px; }
            50% { background-position: 60px 60px, -60px -60px; }
            75% { background-position: 30px 30px, -30px -30px; }
        }

        .dashboard-container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }

        .modern-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0px 32px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            margin-bottom: 40px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            animation: fadeInDown 0.6s ease-out;
            overflow: visible;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .logo-icon i {
            font-size: 26px;
            color: white;
        }

        .logo-text h2 {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #94a3f8);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
        }

        .logo-text span {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .header-badge {
            display: flex;
            gap: 12px;
            align-items: center;
            overflow: visible;
        }

        .version-chip {
            background: rgba(59, 130, 246, 0.15);
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-dot {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.4);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .status-dot:hover {
            background: rgba(0, 0, 0, 0.6);
            transform: scale(1.02);
        }

        .status-dot.status-online i { color: #22c55e; }
        .status-dot.status-online { color: #86efac; }
        .status-dot.status-offline i { color: #ef4444; }
        .status-dot.status-offline { color: #fca5a5; }

        .welcome-banner {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(139, 92, 246, 0.1));
            backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 2px 8px;
            margin-bottom: 40px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-banner h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(145deg, #ffffff, #60a5fa, #a78bfa);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom: 12px;
        }

        .welcome-banner p {
            color: #94a3b8;
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .subsystems-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .subsystem-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .subsystem-card.disabled {
            opacity: 0.6;
            pointer-events: none;
            cursor: default;
            filter: grayscale(0.2);
        }

        .subsystem-card.disabled .access-badge {
            background: rgba(100, 116, 139, 0.3);
            color: #94a3b8;
            cursor: not-allowed;
        }

        .subsystem-card.disabled .access-badge:hover {
            transform: none;
            background: rgba(100, 116, 139, 0.3);
            box-shadow: none;
        }

        .subsystem-card.disabled .access-badge i {
            animation: none;
        }

        .subsystem-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: left 0.6s ease;
        }

        .subsystem-card:hover::before {
            left: 100%;
        }

        .subsystem-card:hover:not(.disabled) {
            transform: translateY(-8px);
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .card-image-container {
            width: 100%;
            height: 160px;
            overflow: hidden;
            background: linear-gradient(135deg, #1a1a2e, #0f0f1a);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-image-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
            padding: 15px;
        }

        .subsystem-card:hover:not(.disabled) .card-image-container img {
            transform: scale(1.15);
        }

        .card-content {
            padding: 20px 24px 28px 24px;
            position: relative;
        }

        .subsystem-card h3 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 12px;
        }

        .subsystem-card p {
            color: #94a3b8;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .stat-item i {
            font-size: 0.7rem;
        }

        .access-badge {
            background: rgba(59, 130, 246, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: #60a5fa;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .access-badge:hover {
            background: #3b82f6;
            color: white;
            transform: scale(1.08);
            padding: 6px 18px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }

        .access-badge:hover i {
            color: white;
        }

        .access-badge i {
            animation: arrowMove 1s ease-in-out infinite;
            display: inline-block;
            transition: color 0.3s ease;
        }

        @keyframes arrowMove {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }

        .badge-activo {
            background: #22c55e20;
            color: #22c55e;
            border: 1px solid #22c55e60;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-inactivo {
            background: #ef444420;
            color: #f87171;
            border: 1px solid #ef444460;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .info-panel {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(16px);
            border-radius: 28px;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-item i {
            font-size: 24px;
            color: #3b82f6;
        }

        .info-item .info-text h4 {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-item .info-text p {
            font-size: 1rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 15, 26, 0.95);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(59, 130, 246, 0.2);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-overlay p {
            color: #e2e8f0;
            font-size: 1rem;
        }

        .loading-overlay small {
            color: #64748b;
            font-size: 0.8rem;
        }

        .unicorn-icon {
            width: 30px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .unified-footer-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            overflow: hidden;
            margin-top: 8px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .unified-footer-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: box-shadow 0.3s ease;
        }

        .footer-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 28px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.05));
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            flex-wrap: wrap;
            gap: 5px;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-brand i {
            font-size: 28px;
            color: #3b82f6;
            transition: all 0.3s ease;
        }

        .footer-brand:hover i {
            transform: scale(1.1) rotate(-5deg);
            color: #60a5fa;
        }

        .brand-text h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 4px;
        }

        .brand-text p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .footer-stats {
            display: flex;
            gap: 20px;
        }

        .footer-stats span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .footer-stats span:hover {
            color: #60a5fa;
            transform: translateY(-2px);
            background: rgba(59, 130, 246, 0.1);
        }

        .footer-stats span i {
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .footer-stats span:hover i {
            transform: rotate(15deg);
            color: #3b82f6;
        }

        .footer-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            padding: 20px 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .footer-info-item:hover {
            transform: translateY(-3px);
            background: rgba(59, 130, 246, 0.1);
        }

        .footer-info-item i {
            font-size: 22px;
            color: #3b82f6;
            transition: all 0.3s ease;
        }

        .footer-info-item:hover i {
            transform: scale(1.1);
            color: #60a5fa;
        }

        .info-details h4 {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.3s ease;
        }

        .footer-info-item:hover .info-details h4 {
            color: #60a5fa;
        }

        .info-details p {
            font-size: 0.85rem;
            font-weight: 600;
            color: #e2e8f0;
            transition: color 0.3s ease;
        }

        .footer-info-item:hover .info-details p {
            color: white;
        }

        .footer-copyright {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.7rem;
            color: #64748b;
            text-align: center;
        }

        .footer-copyright:hover .unicorn-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .footer-copyright span {
            transition: color 0.3s ease;
        }

        .footer-copyright:hover span {
            color: #60a5fa;
        }

        /* ===== ESTILOS DEL DROPDOWN (SOLO VISUALES, LA POSICIÓN LA CONTROLA JS) ===== */
        .custom-dropdown {
            position: relative;
            display: inline-block;
        }

        .custom-dropdown-btn {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #e2e8f0;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: inherit;
            white-space: nowrap;
        }

        .custom-dropdown-btn:hover {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(59, 130, 246, 0.6);
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .custom-dropdown-btn i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .custom-dropdown-btn.active i {
            transform: rotate(180deg);
        }

        /* El menú se posiciona con JS, aquí solo estilos visuales */
        .custom-dropdown-menu {
            min-width: 280px;
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8);
            padding: 8px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.98);
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            overflow: visible;
            pointer-events: none;
            /* position, top, left, z-index los pone JS */
        }

        .custom-dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .custom-dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 20px;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .custom-dropdown-menu .dropdown-item:hover {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            padding-left: 24px;
        }

        .custom-dropdown-menu .dropdown-item i {
            width: 22px;
            text-align: center;
            font-size: 1rem;
            color: #3b82f6;
        }

        .custom-dropdown-menu .dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
            margin: 6px 16px;
        }

        .custom-dropdown-menu .dropdown-title {
            padding: 8px 20px 4px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-header {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 12px;
                padding: 12px 16px;
                justify-content: space-between;
            }
            .logo-area {
                flex: 1;
                min-width: 180px;
            }
            .header-badge {
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }
            .custom-dropdown-btn {
                padding: 6px 14px;
                font-size: 0.75rem;
            }
            .custom-dropdown-menu {
                min-width: 240px;
            }
        }

        @media (max-width: 480px) {
            .logo-text h2 {
                font-size: 1rem;
            }
            .logo-text span {
                font-size: 0.6rem;
            }
            .custom-dropdown-btn {
                padding: 5px 12px;
                font-size: 0.7rem;
            }
            .custom-dropdown-menu {
                min-width: 210px;
            }
            .custom-dropdown-menu .dropdown-item {
                padding: 10px 16px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 1199px) {
            .subsystems-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 900px) {
            .subsystems-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.6rem; }
            .subsystems-grid { grid-template-columns: 1fr; gap: 20px; }
            .card-image-container { height: 140px; }
            .footer-main {
                flex-direction: column;
                text-align: center;
                padding: 16px 20px;
            }
            .footer-brand {
                flex-direction: column;
                text-align: center;
            }
            .footer-stats {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            .footer-stats span {
                justify-content: center;
            }
            .footer-info-grid {
                grid-template-columns: 1fr;
                padding: 16px 20px;
            }
            .footer-info-item {
                justify-content: center;
                padding: 10px;
            }
            .footer-copyright {
                flex-direction: column;
                padding: 14px 20px;
            }
        }

        @media (max-width: 480px) {
            .welcome-banner { padding: 24px 20px; }
            .welcome-banner h1 { font-size: 1.3rem; }
            .card-content { padding: 16px 20px 22px 20px; }
            .subsystem-card h3 { font-size: 1.4rem; }
            .card-image-container { height: 130px; }
            .footer-info-item {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            .footer-info-item i {
                font-size: 28px;
            }
            .info-details {
                text-align: center;
            }
        }

        .modal-subsystem-img {
            max-width: 180px;
            max-height: 180px;
            background: linear-gradient(135deg, #1a1a2e, #0f0f1a);
            border-radius: 24px;
            padding: 15px;
            margin: 0 auto 20px auto;
            display: block;
            object-fit: contain;
        }
    </style>
</head>
<body>

<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <p><i class="fas fa-cloud-arrow-up"></i> Cargando módulo...</p>
    <small>Conectando con el subsistema, por favor espera</small>
</div>

<div class="dashboard-container">
    <div class="modern-header">
        <div class="logo-area">
            <div class="logo-icon">
                <img src="images/logotn.png" alt="<?php echo htmlspecialchars($COMPANY_NAME); ?> Logo" 
                     style="width: 100%; height: 100%; object-fit: contain;"
                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon\'></i>';">
            </div>
            <div class="logo-text">
                <h2><?php echo htmlspecialchars($COMPANY_NAME); ?> · <?php echo htmlspecialchars($SLOGAN); ?></h2>
                <span style="font-size: 18px">Plataforma de Desarrollo de Subsistemas · <?php echo htmlspecialchars($SITE_VERSION); ?></span>
            </div>
        </div>
        <div class="header-badge">
            <!-- DROPDOWN: OTROS SERVICIOS -->
            <div class="custom-dropdown" id="otrosServiciosDropdown">
                <button class="custom-dropdown-btn" id="dropdownToggle">
                    <i class="fas fa-ellipsis-h"></i> OTROS SERVICIOS
                    <i class="fas fa-chevron-down" id="dropdownArrow"></i>
                </button>
                <div class="custom-dropdown-menu" id="dropdownMenu">
                    <div class="dropdown-title">Servicios Generales</div>
                    <button class="dropdown-item" data-servicio="salarios">
                        <i class="fas fa-money-bill-wave"></i> Consultar Salario
                    </button>
                    <button class="dropdown-item" data-servicio="charangon">
                        <i class="fas fa-calculator"></i> Ficha Costo Charangón
                    </button>
                    <button class="dropdown-item" data-servicio="papel-adhesivo">
                        <i class="fas fa-print"></i> Ficha Costo Impres. Papel Adhesivo
                    </button>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" data-servicio="contacto">
                        <i class="fas fa-headset"></i> Contactar Soporte
                    </button>
                </div>
            </div>

            <div class="version-chip">
                <i class="fas fa-code-branch"></i> Enterprise · Estable
            </div>
            <div class="status-dot" id="serverStatusDot">
                <i class="fas fa-circle" id="statusIcon"></i>
                <span id="statusText">Verificando...</span>
            </div>
        </div>
    </div>

    <div class="welcome-banner animate__animated animate__fadeInUp">
        <div style="display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
            <img src="logotn.png" alt="<?php echo htmlspecialchars($COMPANY_NAME); ?> Logo" 
                 style="height: 135px; width: auto; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));"
                 onerror="this.onerror=null; this.style.display='none';">
            <div>
                <h1 style="margin: 0;">
                    <i class="fas fa-chalkboard-user"></i> 
                    Ecosistema Financiero · <?php echo htmlspecialchars($COMPANY_NAME); ?>
                </h1>
                <div style="font-size: 1rem; color: #60a5fa; margin-top: 5px; animation: blink 1s ease-in-out infinite;">
                    <i class="fas fa-hand-point-down"></i> 
                    <span style="font-weight: 800;">Selecciona el subsistema que deseas gestionar. Todos los módulos están integrados y sincronizados para una experiencia fluida.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="subsystems-grid">
        <!-- Nóminas (código 0001) -->
        <div class="subsystem-card <?php echo !$server_ok ? 'disabled' : ''; ?> animate__animated animate__fadeInUp" 
             data-module="nominas" 
             data-name="Nóminas" 
             data-url="/nominas/index.php" 
             data-activo="<?php echo isset($sub_estados['0001']) ? $sub_estados['0001'] : 0; ?>">
            <div class="card-image-container">
                <img src="images/nominas.png" alt="Nóminas"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%233b82f6\'/%3E%3Ctext x=\'100\' y=\'85\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\'%3EN%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Nóminas</h3>
                    <?php echo getBadge('0001', $server_ok, $sub_estados); ?>
                </div>
                <p>Gestión completa de salarios, cálculos automáticos de ISR, seguridad social, vacaciones y reportes ejecutivos.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Cálculos en tiempo real</span>
                    </div>
                    <div class="access-badge">
                        <i class="fas fa-arrow-right"></i> Acceder
                    </div>
                </div>
            </div>
        </div>

        <!-- Facturación (código 0002) -->
        <div class="subsystem-card <?php echo !$server_ok ? 'disabled' : ''; ?> animate__animated animate__fadeInUp animate__delay-1s" 
             data-module="facturacion" 
             data-name="Facturación" 
             data-url="/facturacion/login.php" 
             data-activo="<?php echo isset($sub_estados['0002']) ? $sub_estados['0002'] : 0; ?>">
            <div class="card-image-container">
                <img src="images/facturacion.png" alt="Facturación"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%2310b981\'/%3E%3Ctext x=\'100\' y=\'85\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\'%3EF%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Facturación</h3>
                    <?php echo getBadge('0002', $server_ok, $sub_estados); ?>
                </div>
                <p>Emisión de facturas electrónicas, control de clientes, seguimiento de pagos y reportes fiscales automatizados.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span>CFDI 4.0</span>
                    </div>
                    <div class="access-badge">
                        <i class="fas fa-arrow-right"></i> Acceder
                    </div>
                </div>
            </div>
        </div>

        <!-- Costos (código 0003) -->
        <div class="subsystem-card <?php echo !$server_ok ? 'disabled' : ''; ?> animate__animated animate__fadeInUp animate__delay-2s" 
             data-module="costos" 
             data-name="Costos" 
             data-url="/costos/login.php" 
             data-activo="<?php echo isset($sub_estados['0003']) ? $sub_estados['0003'] : 0; ?>">
            <div class="card-image-container">
                <img src="images/costos.png" alt="Costos"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%23f59e0b\'/%3E%3Ctext x=\'100\' y=\'85\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\'%3EC%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Costos</h3>
                    <?php echo getBadge('0003', $server_ok, $sub_estados); ?>
                </div>
                <p>Análisis de costos operativos, margen de ganancia, control de gastos y proyecciones financieras.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard analítico</span>
                    </div>
                    <div class="access-badge">
                        <i class="fas fa-arrow-right"></i> Acceder
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventarios (código 0004) -->
        <div class="subsystem-card <?php echo !$server_ok ? 'disabled' : ''; ?> animate__animated animate__fadeInUp animate__delay-3s" 
             data-module="inventarios" 
             data-name="Inventarios" 
             data-url="/inventarios/login.php" 
             data-activo="<?php echo isset($sub_estados['0004']) ? $sub_estados['0004'] : 0; ?>">
            <div class="card-image-container">
                <img src="images/inventario.png" alt="Inventarios"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%23ef4444\'/%3E%3Ctext x=\'100\' y=\'85\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\'%3EI%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Inventarios</h3>
                    <?php echo getBadge('0004', $server_ok, $sub_estados); ?>
                </div>
                <p>Control de stock, entradas y salidas de productos, alertas de inventario mínimo y gestión de proveedores.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Stock en tiempo real</span>
                    </div>
                    <div class="access-badge">
                        <i class="fas fa-arrow-right"></i> Acceder
                    </div>
                </div>
            </div>
        </div>

        <!-- Administración (código 0005) -->
        <div class="subsystem-card <?php echo !$server_ok ? 'disabled' : ''; ?> animate__animated animate__fadeInUp animate__delay-4s" 
             data-module="administracion" 
             data-name="Administración" 
             data-url="/administracion/index.php" 
             data-activo="<?php echo isset($sub_estados['0005']) ? $sub_estados['0005'] : 0; ?>">
            <div class="card-image-container">
                <img src="images/admin.png" alt="Administración"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%238b5cf6\'/%3E%3Ctext x=\'100\' y=\'85\' text-anchor=\'middle\' fill=\'white\' font-size=\'40\'%3EA%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Administ.</h3>
                    <?php echo getBadge('0005', $server_ok, $sub_estados); ?>
                </div>
                <p>Gestión centralizada de usuarios, roles, parámetros globales, auditoría y configuración del sistema.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Panel de control</span>
                    </div>
                    <div class="access-badge">
                        <i class="fas fa-arrow-right"></i> Acceder
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Unificado -->
    <div class="unified-footer-card">
        <div class="footer-main">
            <div class="footer-brand">
                <i class="fas fa-cog"></i>
                <div class="brand-text">
                    <h4><?php echo htmlspecialchars($COMPANY_NAME); ?></h4>
                    <p><?php echo htmlspecialchars($SLOGAN); ?></p>
                </div>
            </div>
            <div class="footer-stats">
                <span><i class="fas fa-chart-line"></i> Proyecto en crecimiento</span>
                <span><i class="fas fa-cloud-upload-alt"></i> Backup automático</span>
            </div>
        </div>
        
        <div class="footer-info-grid">
            <div class="footer-info-item">
                <i class="fas fa-database"></i>
                <div class="info-details">
                    <h4>Base de Datos</h4>
                    <p>MySQL 8.0 · Alta disponibilidad</p>
                </div>
            </div>
            <div class="footer-info-item">
                <i class="fas fa-shield-alt"></i>
                <div class="info-details">
                    <h4>Seguridad</h4>
                    <p>Cifrado SSL · Autenticación 2FA</p>
                </div>
            </div>
            <div class="footer-info-item">
                <i class="fas fa-sync-alt"></i>
                <div class="info-details">
                    <h4>Sincronización</h4>
                    <p>Actualizaciones en tiempo real</p>
                </div>
            </div>
            <div class="footer-info-item">
                <i class="fas fa-headset"></i>
                <div class="info-details">
                    <h4>Soporte 24/7</h4>
                    <p>Asistencia prioritaria</p>
                </div>
            </div>
        </div>
        
        <div class="footer-copyright">
            <div style="display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 12px;">
                <img src="images/Unicorn.png" alt="Unicornio" class="unicorn-icon">
                <span>Copyright © <?php echo $current_year; ?> UnicornioSoftware° - Kaky&reg;. Todos los derechos reservados.</span>
                <div style="display: inline-flex; gap: 12px; align-items: center;">
                    <a href="terminos.php" style="color: #64748b; text-decoration: none; font-size: 0.7rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-file-contract"></i> Términos
                    </a>
                    <span style="color: #64748b;">•</span>
                    <a href="privacidad.php" style="color: #64748b; text-decoration: none; font-size: 0.7rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-lock"></i> Privacidad
                    </a>
                    <span style="color: #64748b;">•</span>
                    <a href="soporte.php" style="color: #64748b; text-decoration: none; font-size: 0.7rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-headset"></i> Soporte
                    </a>
                    <span style="color: #64748b;">•</span>
                    <a href="contacto.php" style="color: #64748b; text-decoration: none; font-size: 0.7rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-envelope"></i> Cont&aacute;ctenos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============ ESTADO DEL SERVIDOR (desde PHP) ============
const serverStatus = {
    php: <?php echo json_encode($server_status['php']); ?>,
    mysql: <?php echo json_encode($server_status['mysql']); ?>,
    mysqlError: <?php echo json_encode($server_status['mysql_error']); ?>
};
const serverOk = <?php echo json_encode($server_ok); ?>;

let isRedirecting = false;

function updateServerStatusUI() {
    const statusDot = document.getElementById('serverStatusDot');
    const statusIcon = document.getElementById('statusIcon');
    const statusText = document.getElementById('statusText');
    
    if (serverStatus.php && serverStatus.mysql) {
        statusDot.className = 'status-dot status-online';
        statusIcon.style.color = '#22c55e';
        statusText.innerHTML = 'Sistema Operativo';
    } else if (serverStatus.php && !serverStatus.mysql) {
        statusDot.className = 'status-dot status-offline';
        statusIcon.style.color = '#ef4444';
        statusText.innerHTML = 'MySQL desconectado';
    }
}

function showLoading(moduleName) {
    const overlay = document.getElementById('loadingOverlay');
    const message = overlay.querySelector('p');
    message.innerHTML = `<i class="fas fa-cloud-arrow-up"></i> Cargando módulo de ${moduleName}...`;
    overlay.classList.add('active');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.remove('active');
}

function showModuleWelcome(card) {
    const moduleName = card.dataset.name;
    const moduleUrl = card.dataset.url;
    const moduleDescription = card.querySelector('p').innerHTML;
    const moduleActivo = card.dataset.activo;
    const cardImg = card.querySelector('.card-image-container img');
    const imgSrc = cardImg ? cardImg.src : '';
    const imgHtml = imgSrc ? `<img src="${imgSrc}" class="modal-subsystem-img" alt="${moduleName}">` : '';
    
    if (!serverOk) {
        Swal.fire({
            title: '⚠️ Servidor no disponible',
            html: `No es posible acceder porque el servidor (PHP/MySQL) no está funcionando correctamente.<br><br>
                   📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                   ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a><br><br>
                   Contacte al administrador.`,
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
            confirmButtonText: '<i class="fas fa-times"></i> Cerrar'
        });
        return;
    }
    
    if (moduleActivo == '0') {
        Swal.fire({
            title: '⛔ Subsistema inactivo',
            html: `El subsistema de: <b>${moduleName}</b> se encuentra deshabilitado temporalmente.<br><br>
                   📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                   ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a><br><br>
                   Contacte al administrador para más información.`,
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check"></i> Entendido'
        });
        return;
    }
    
    Swal.fire({
        title: `${moduleName}`,
        html: `
            <div style="text-align: center;">
                ${imgHtml}
                <p style="margin-bottom: 15px; text-align: left;">${moduleDescription}</p>
                <div style="background: rgba(59, 130, 246, 0.1); padding: 12px; border-radius: 12px; text-align: left;">
                    <p><i class="fas fa-shield-alt"></i> <strong>Características destacadas:</strong></p>
                    <ul style="margin-top: 8px; margin-left: 20px;">
                        <li>Interfaz intuitiva y moderna</li>
                        <li>Reportes en tiempo real</li>
                        <li>Datos cifrados y seguros</li>
                        <li>Soporte técnico prioritario</li>
                    </ul>
                </div>
                <p style="margin-top: 15px; font-size: 0.85rem; color: #94a3b8;">
                    <i class="fas fa-clock"></i> Último acceso: Hoy a las ${new Date().toLocaleTimeString()}
                </p>
            </div>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: '#3b82f6',
        confirmButtonText: `<i class="fas fa-arrow-right-to-bracket"></i> Ingresar a ${moduleName}`,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
        cancelButtonColor: '#475569',
        backdrop: `rgba(0,0,0,0.8)`,
        showClass: {
            popup: 'animate__animated animate__zoomIn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            accessModule(moduleUrl, moduleName, moduleActivo);
        }
    });
}

async function urlExists(url, timeout = 3000) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    try {
        const response = await fetch(url, { method: 'HEAD', signal: controller.signal });
        clearTimeout(timeoutId);
        return response.ok;
    } catch (error) {
        clearTimeout(timeoutId);
        return false;
    }
}

function accessModule(url, moduleName, moduleActivo) {
    if (isRedirecting) return;
    
    if (!serverOk) {
        Swal.fire({
            title: '⚠️ Servidor no disponible',
            html: `No es posible acceder porque el servidor (PHP/MySQL) no está funcionando correctamente.<br><br>
                   📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                   ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a><br><br>
                   Contacte al administrador.`,
            icon: 'error',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    
    if (moduleActivo == '0') {
        Swal.fire({
            title: '⛔ Subsistema inactivo',
            html: `El subsistema de: <b>${moduleName}</b> se encuentra deshabilitado temporalmente.<br><br>
                   📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                   ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a><br><br>
                   No es posible acceder.`,
            icon: 'warning',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }
    
    Swal.fire({
        title: '🔍 Verificando acceso',
        text: `Comprobando disponibilidad de ${moduleName}...`,
        icon: 'info',
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    urlExists(url).then(exists => {
        Swal.close();
        if (exists) {
            isRedirecting = true;
            showLoading(moduleName);
            setTimeout(() => {
                window.location.href = url;
            }, 800);
        } else {
            Swal.fire({
                title: '❌ Subsistema no disponible',
                html: `El módulo <b>${moduleName}</b> no se encuentra instalado o la ruta es incorrecta.<br><br>
                       📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                       ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a><br><br>
                       Contacte al administrador para instalar o corregir la ruta.`,
                icon: 'error',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fas fa-times"></i> Cerrar'
            });
        }
    }).catch(() => {
        Swal.close();
        Swal.fire({
            title: '⚠️ Error de conexión',
            text: `No se pudo verificar el estado de ${moduleName}. Intente más tarde.`,
            icon: 'warning',
            background: '#0f172a',
            color: '#e2e8f0'
        });
    });
}

// ============ EVENTOS DE TARJETAS ============
document.querySelectorAll('.subsystem-card').forEach(card => {
    card.addEventListener('click', (e) => {
        if (e.target.closest('.access-badge')) return;
        if (isRedirecting) return;
        if (card.classList.contains('disabled')) return;
        showModuleWelcome(card);
    });
    
    card.addEventListener('mouseenter', () => {
        if (!card.classList.contains('disabled')) {
            card.style.cursor = 'pointer';
        } else {
            card.style.cursor = 'default';
        }
    });
});

document.querySelectorAll('.access-badge').forEach(badge => {
    badge.addEventListener('click', (e) => {
        e.stopPropagation();
        if (isRedirecting) return;
        const card = badge.closest('.subsystem-card');
        if (card.classList.contains('disabled')) return;
        showModuleWelcome(card);
    });
});

// ============ FUNCIONES DE VERIFICACIÓN DE ESTADO ============
function verificarEstadoConLoading() {
    Swal.fire({
        title: '🔄 Verificando estado...',
        html: `
            <div style="text-align: center;">
                <div class="loading-spinner-small"></div>
                <p style="margin-top: 15px;">Comprobando conexión con el servidor...</p>
                <p style="font-size: 0.8rem; color: #94a3b8;">Verificando PHP y MySQL</p>
            </div>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            if (!document.querySelector('#spinner-style')) {
                const style = document.createElement('style');
                style.id = 'spinner-style';
                style.textContent = `
                    .loading-spinner-small {
                        width: 50px;
                        height: 50px;
                        border: 3px solid rgba(59, 130, 246, 0.2);
                        border-top-color: #3b82f6;
                        border-radius: 50%;
                        animation: spinSmall 0.8s linear infinite;
                        margin: 0 auto;
                    }
                    @keyframes spinSmall {
                        to { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
    });
    
    setTimeout(() => {
        location.reload();
    }, 1500);
}

const serverStatusDot = document.getElementById('serverStatusDot');
if (serverStatusDot) {
    serverStatusDot.addEventListener('click', () => {
        if (serverStatus.php && serverStatus.mysql) {
            Swal.fire({
                title: '✅ Estado del servidor',
                html: `
                    <div style="text-align: left;">
                        <p><strong>PHP:</strong> <span style="color: #22c55e;">✓ Activo</span></p>
                        <p><strong>MySQL:</strong> <span style="color: #22c55e;">✓ Conectado correctamente</span></p>
                        <p><strong>Base de datos:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
                        <hr style="margin: 15px 0; border-color: rgba(255,255,255,0.1);">
                        <p><i class="fas fa-check-circle" style="color:#22c55e"></i> Todos los sistemas operativos</p>
                    </div>
                `,
                icon: 'success',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: '<i class="fas fa-check"></i> Aceptar',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-sync-alt"></i> Verificar Estado',
                cancelButtonColor: '#475569'
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    verificarEstadoConLoading();
                }
            });
        } else if (serverStatus.php && !serverStatus.mysql) {
            Swal.fire({
                title: '⚠️ Estado del servidor',
                html: `
                    <div style="text-align: left;">
                        <p><strong>PHP:</strong> <span style="color: #22c55e;">✓ Activo</span></p>
                        <p><strong>MySQL:</strong> <span style="color: #ef4444;">✗ Desconectado</span></p>
                        <p><strong>Error:</strong> <span style="color: #fca5a5;">${serverStatus.mysqlError || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</span></p>
                        <hr style="margin: 15px 0; border-color: rgba(255,255,255,0.1);">
                        <p><i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Verifica el servicio de MySQL</p>
                    </div>
                `,
                icon: 'warning',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-sync-alt"></i> Verificar Estado',
                cancelButtonColor: '#475569'
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    verificarEstadoConLoading();
                }
            });
        }
    });
}

// ============ BIENVENIDA ============
function mostrarBienvenida() {
    const justRedirected = sessionStorage.getItem('justRedirected');
    if (!justRedirected) {
        setTimeout(() => {
            Swal.fire({
                title: '✨ Bienvenido al Ecosistema Financiero de <?php echo htmlspecialchars($COMPANY_NAME); ?>',
                html: '<span style="font-size:0.95rem;">Centro de Control Empresarial <?php echo htmlspecialchars($SITE_VERSION); ?><br><br><span style="animation: blink 1s step-start infinite; font-weight: 700; color: #3b82f6;">🔽 SELECCIONE UNO DE LOS SUBSISTEMAS A UTILIZAR.</span><br><br><i class="fas fa-chart-line"></i> Nóminas | <i class="fas fa-file-invoice-dollar"></i> Facturación<br><i class="fas fa-chart-pie"></i> Costos | <i class="fas fa-boxes"></i> Inventarios | <i class="fas fa-user-cog"></i> Administración</span>',
                icon: 'info',
                background: '#0f172a',
                color: '#e2e8f0',
                iconColor: '#3b82f6',
                confirmButtonColor: '#2563eb',
                confirmButtonText: '<i class="fas fa-rocket"></i> Explorar Subsistemas',
                backdrop: `rgba(0,0,0,0.75)`,
                showClass: { popup: 'animate__animated animate__fadeInDown' },
                didOpen: () => {
                    if (!document.querySelector('#blink-style')) {
                        const style = document.createElement('style');
                        style.id = 'blink-style';
                        style.textContent = `
                            @keyframes blink {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0; }
                            }
                        `;
                        document.head.appendChild(style);
                    }
                    const icon = document.querySelector('.swal2-icon');
                    if (icon) {
                        icon.style.animation = 'pulseIcon 0.8s ease-in-out infinite';
                        if (!document.querySelector('#pulse-style')) {
                            const pulseStyle = document.createElement('style');
                            pulseStyle.id = 'pulse-style';
                            pulseStyle.textContent = `
                                @keyframes pulseIcon {
                                    0%, 100% { transform: scale(1); opacity: 1; }
                                    50% { transform: scale(1.1); opacity: 0.8; }
                                }
                            `;
                            document.head.appendChild(pulseStyle);
                        }
                    }
                }
            });
        }, 300);
    }
    sessionStorage.removeItem('justRedirected');
}

// ============ DROPDOWN: OTROS SERVICIOS (VERSIÓN DEFINITIVA) ============
(function() {
    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDropdown);
    } else {
        initDropdown();
    }

    function initDropdown() {
        const dropdownToggle = document.getElementById('dropdownToggle');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const dropdownArrow = document.getElementById('dropdownArrow');
        let isOpen = false;

        if (!dropdownToggle || !dropdownMenu) return;

        // Mover el menú al final del body para evitar cualquier contexto de apilamiento
        document.body.appendChild(dropdownMenu);

        // Establecer estilos fijos para el menú
        dropdownMenu.style.position = 'fixed';
        dropdownMenu.style.zIndex = '9999999';
        dropdownMenu.style.overflow = 'visible';
        dropdownMenu.style.pointerEvents = 'none';
        dropdownMenu.style.maxWidth = '90vw';

        function positionMenu() {
            const rect = dropdownToggle.getBoundingClientRect();
            const menuWidth = dropdownMenu.offsetWidth || 280;
            const menuHeight = dropdownMenu.scrollHeight || 400;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;

            // Calcular posición horizontal (alineado a la derecha del botón)
            let left = rect.right - menuWidth;
            // Asegurar que no se salga por la izquierda
            if (left < 10) left = 10;
            // Asegurar que no se salga por la derecha
            if (left + menuWidth > windowWidth - 10) {
                left = windowWidth - menuWidth - 10;
            }

            // Calcular posición vertical (debajo del botón)
            let top = rect.bottom + 10;
            // Si no hay espacio abajo, poner arriba
            if (top + menuHeight + 20 > windowHeight) {
                top = rect.top - menuHeight - 10;
                if (top < 10) top = 10;
            }

            dropdownMenu.style.top = top + 'px';
            dropdownMenu.style.left = left + 'px';
        }

        function openMenu() {
            isOpen = true;
            dropdownMenu.classList.add('show');
            dropdownMenu.style.pointerEvents = 'auto';
            dropdownToggle.classList.add('active');
            dropdownArrow.style.transform = 'rotate(180deg)';
            positionMenu();
        }

        function closeMenu() {
            isOpen = false;
            dropdownMenu.classList.remove('show');
            dropdownMenu.style.pointerEvents = 'none';
            dropdownToggle.classList.remove('active');
            dropdownArrow.style.transform = 'rotate(0deg)';
        }

        // Toggle
        dropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (isOpen && !dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                closeMenu();
            }
        });

        // Reposicionar al scroll/resize
        window.addEventListener('scroll', function() {
            if (isOpen) positionMenu();
        });
        window.addEventListener('resize', function() {
            if (isOpen) positionMenu();
        });

        // Manejar clics en items del dropdown (solo el ítem de Charangón)
        dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                const servicio = this.dataset.servicio;
                
                // Cerrar el menú
                closeMenu();

                // Acción para "salarios": redirigir a la consulta pública
                if (servicio === 'salarios') {
                    window.location.href = '/nominas/solic_userinfo/';
                } else if (servicio === 'charangon') {
                    // Redirigir a fcostocharangon/
                    window.location.href = 'fcostoCharangon/';
                } else if (servicio === 'papel-adhesivo') {
                    // Redirigir a la ficha de costo del papel adhesivo
                    window.location.href = 'fcostoAdhesivo/';
                } else if (servicio === 'contacto') {
                    // Redirigir al formulario de contacto
                    window.location.href = '/contacto.php';
                } else {
                    // Por si hubiera otros, aunque ahora solo está este
                    Swal.fire({
                        title: 'ℹ️ Información',
                        text: `Servicio "${this.textContent.trim()}" seleccionado.`,
                        background: '#0f172a',
                        color: '#e2e8f0',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            });
        });
    }
})();

// ============ INICIALIZACIÓN ============
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        isRedirecting = false;
        hideLoading();
        if (Swal.isVisible()) {
            Swal.close();
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    isRedirecting = false;
    hideLoading();
    updateServerStatusUI();
    
    // Mostrar verificación al iniciar
    Swal.fire({
        title: '🔄 Verificando estado...',
        html: `
            <div style="text-align: center;">
                <div class="loading-spinner-small"></div>
                <p style="margin-top: 15px;">Comprobando conexión con el servidor...</p>
                <p style="font-size: 0.8rem; color: #94a3b8;">Verificando PHP y MySQL</p>
            </div>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            if (!document.querySelector('#spinner-style')) {
                const style = document.createElement('style');
                style.id = 'spinner-style';
                style.textContent = `
                    .loading-spinner-small {
                        width: 50px;
                        height: 50px;
                        border: 3px solid rgba(59, 130, 246, 0.2);
                        border-top-color: #3b82f6;
                        border-radius: 50%;
                        animation: spinSmall 0.8s linear infinite;
                        margin: 0 auto;
                    }
                    @keyframes spinSmall {
                        to { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
    });
    
    setTimeout(() => {
        Swal.close();
        
        setTimeout(() => {
            if (serverStatus.php && serverStatus.mysql) {
                Swal.fire({
                    title: '✅ Servidor Conectado',
                    html: `
                        <div style="text-align: left;">
                            <p><strong>PHP:</strong> <span style="color: #22c55e;">✓ Activo</span></p>
                            <p><strong>MySQL:</strong> <span style="color: #22c55e;">✓ Conectado</span></p>
                            <p><strong>Base de datos:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
                            <p><strong>Usted ya puede:</strong> Comenzar a Trabajar.</p>
                        </div>
                    `,
                    icon: 'success',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#3b82f6',
                    confirmButtonText: '<i class="fas fa-check"></i> Continuar',
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: () => {
                        const progressBar = document.querySelector('.swal2-timer-progress-bar');
                        if (progressBar) {
                            progressBar.style.background = 'linear-gradient(90deg, #3b82f6, #60a5fa, #93c5fd)';
                            progressBar.style.height = '4px';
                            progressBar.style.boxShadow = '0 0 8px rgba(59, 130, 246, 0.5)';
                        }
                    }
                }).then(() => {
                    mostrarBienvenida();
                });
            } else {
                Swal.fire({
                    title: '⚠️ Error de Conexión',
                    html: `
                        <div style="text-align: left;">
                            <p><strong>PHP:</strong> <span style="color: #22c55e;">✓ Activo</span></p>
                            <p><strong>MySQL:</strong> <span style="color: #ef4444;">✗ Desconectado</span></p>
                            <p><strong>Error:</strong> <span style="color: #fca5a5;">${serverStatus.mysqlError || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</span></p>
                            <hr style="margin: 15px 0; border-color: rgba(255,255,255,0.1);">
                            <p><i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Verifica el servicio de MySQL</p>
                            <br>
                            📱 <strong>Móvil Empresarial:</strong> <a href="tel:<?php echo $SITE_PHONE_LINK; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_PHONE); ?></a><br>
                            ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($SITE_EMAIL); ?></a>
                        </div>
                    `,
                    icon: 'warning',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-sync-alt fa-fw"></i> Verificar Estado',
                    cancelButtonColor: '#475569'
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        verificarEstadoConLoading();
                    }
                });
            }
        }, 200);
    }, 2000);
});

window.addEventListener('beforeunload', () => {
    if (isRedirecting) {
        sessionStorage.setItem('justRedirected', 'true');
    }
});

const cards = document.querySelectorAll('.subsystem-card');
cards.forEach((card, index) => {
    card.style.animationDelay = `${index * 0.1}s`;
    card.classList.add('animate__animated', 'animate__fadeInUp');
});
</script>
</body>
</html>
