<?php
// Si no existe la configuración del sistema, avisar y redirigir al instalador
if (!file_exists(__DIR__ . '/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
        <title>Sistema sin configurar</title>
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script src="js/sweetalert2.all.min.js"></script>
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
                var w = window.open('../InstalarBD/InstalarBD.php', '_blank');
                if (!w) { window.location.href = '../InstalarBD/InstalarBD.php'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// NO se incluye config/database.php para evitar conflictos con $pdo no definido
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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

$COMPANY_NAME = defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom';
$SITE_VERSION = defined('SITE_VERSION') ? SITE_VERSION : 'v2.0.1';
$SITE_NAME = $COMPANY_NAME . ' - Sistema de Nóminas';

// Correo de soporte: desde configuracion_general (parametro 'email_soporte'); si está vacío, kakycu@gmail.com
$index_email_soporte = 'kakycu@gmail.com';

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
        
        // Cargar configuración de empresa (si la tabla existe)
        $checkTable = $conn->query("SHOW TABLES LIKE 'configuracion_general'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $configQuery = $conn->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'site_name', 'email_soporte')");
            if ($configQuery) {
                while ($row = $configQuery->fetch_assoc()) {
                    if ($row['parametro'] == 'nombre_empresa' && !empty($row['valor'])) {
                        $COMPANY_NAME = $row['valor'];
                        $SITE_NAME = $COMPANY_NAME . ' - Sistema de Nóminas';
                    }
                    if ($row['parametro'] == 'site_name' && !empty($row['valor'])) {
                        $SITE_NAME = $row['valor'];
                    }
                    if ($row['parametro'] == 'email_soporte' && !empty($row['valor'])) {
                        $index_email_soporte = trim((string)$row['valor']);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($SITE_NAME); ?> · <?php echo $SITE_VERSION; ?></title>
    <link rel="icon" type="image/png" href="../images/favicons/nominas.ico">
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <!-- Base responsive global (mobile-first) -->
    <link rel="stylesheet" href="assets/css/responsive.css">
    <script src="js/sweetalert211.js"></script>
    <style>
        /* Estilo global para la barra de progreso de SweetAlert2 - MÁS CLARA */
        .swal2-timer-progress-bar {
            background: linear-gradient(90deg, #3b82f6, #60a5fa, #93c5fd) !important;
            height:0.25rem !important;
            border-radius: 0.125rem !important;
            box-shadow: 0 0 0.5rem rgba(59, 130, 246, 0.5) !important;
        }

        /* Efecto de ondas sutiles - VERSIÓN MEJORADA */
        body::after {
            content: "";
            position: fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background-image: 
                repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 1.875rem,
                    rgba(59, 130, 246, 0.08) 1.875rem,
                    rgba(59, 130, 246, 0.08) 3.75rem
                ),
                repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 2.1875rem,
                    rgba(139, 92, 246, 0.06) 2.1875rem,
                    rgba(139, 92, 246, 0.06) 4.375rem
                );
            pointer-events: none;
            animation: waveMove 8s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes waveMove {
            0% { background-position: 0 0, 0 0; }
            25% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
            50% { background-position: 2.5rem 2.5rem, -2.5rem -2.5rem; }
            75% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
            100% { background-position: 0 0, 0 0; }
        }

        .dashboard-container {
            position: relative;
            z-index: 1;
        }
        
        * {
            margin:0;
            padding:0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height:100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding:1.25rem;
        }

        /* Patrón de fondo sutil */
        body::before {
            content: "";
            position: fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background-image: radial-gradient(rgba(59, 130, 246, 0.08) 0.0625rem, transparent 0.0625rem);
            background-size: 2rem 2rem;
            pointer-events: none;
        }

        /* Contenedor principal estilo tarjeta moderna */
        .dashboard-container {
            width:100%;
            max-width:87.5rem;
            margin:0 auto;
            animation: fadeInUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        /* Header moderno */
        .modern-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding:1.25rem 2rem;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(1.25rem);
            border-radius: 1.75rem;
            margin-bottom:1.75rem;
            border: 0.0625rem solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.2);
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap:0.875rem;
        }

        .logo-icon {
            width:2.75rem;
            height:2.75rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 0.375rem 0.875rem rgba(37, 99, 235, 0.3);
        }

        .logo-text h2 {
            font-size:1.3rem;
            font-weight: 700;
            color: #f1f5f9;
            letter-spacing:-0.0188rem;
        }

        .logo-text span {
            font-size:0.7rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .header-badge {
            display: flex;
            gap:0.75rem;
            align-items: center;
        }

        .version-chip {
            background: rgba(59, 130, 246, 0.15);
            padding:0.375rem 1rem;
            border-radius: 2.5rem;
            font-size:0.75rem;
            font-weight: 500;
            color: #60a5fa;
            border: 0.0625rem solid rgba(59, 130, 246, 0.3);
        }

        .status-dot {
            display: flex;
            align-items: center;
            gap:0.5rem;
            background: rgba(0, 0, 0, 0.4);
            padding:0.375rem 0.875rem;
            border-radius: 2.5rem;
            font-size:0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .status-dot:hover {
            background: rgba(0, 0, 0, 0.6);
            transform: scale(1.02);
        }

        .status-dot i {
            font-size:0.55rem;
        }

        .status-dot.status-online i {
            color: var(--color-success);
        }

        .status-dot.status-online {
            color: #86efac;
        }

        .status-dot.status-offline i {
            color: #ef4444;
        }

        .status-dot.status-offline {
            color: #fca5a5;
        }

        /* Grid principal */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap:1.75rem;
        }

        /* Tarjeta de imagen/branding */
        .brand-card {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(1rem);
            border-radius: 2rem;
            border: 0.0625rem solid rgba(255, 255, 255, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 0.75rem 1.875rem rgba(0, 0, 0, 0.3);
        }

        .brand-card:hover {
            transform: translateY(-0.25rem);
            box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.4);
        }

        .image-wrapper {
            padding:2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, rgba(0,0,0,0.2), rgba(0,0,0,0.4));
        }

        .image-wrapper img {
            max-width:80%;
            height:auto;
            border-radius: 1.5rem;
            filter: drop-shadow(0 0.5rem 1.25rem rgba(0, 0, 0, 0.4));
            transition: all 0.3s;
        }

        .brand-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap:1rem;
            padding:1rem;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.8));
            border-radius: 0.75rem;
            backdrop-filter: blur(0.25rem);
            font-size:0.75rem;
            color: #94a3b8;
            border: 0.0625rem solid rgba(59, 130, 246, 0.2);
            margin:1rem;
        }

        .footer-badge {
            display: inline-flex;
            align-items: center;
            gap:0.5rem;
        }

        .footer-badge i {
            color: #3b82f6;
            font-size:0.85rem;
        }

        .footer-divider {
            width:0.0625rem;
            height:1.25rem;
            background: linear-gradient(to bottom, transparent, #3b82f6, transparent);
        }

        /* Tarjeta de bienvenida/acciones */
        .welcome-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(1.25rem);
            border-radius: 2rem;
            border: 0.0625rem solid rgba(59, 130, 246, 0.25);
            padding:2.5rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 0.75rem 1.875rem rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .welcome-card:hover {
            transform: translateY(-0.25rem);
        }

        .greeting-badge {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            display: inline-flex;
            align-items: center;
            gap:0.625rem;
            padding:0.5rem 1.125rem;
            border-radius: 3.75rem;
            width:fit-content;
            margin-bottom:1.5rem;
            border: 0.0625rem solid #334155;
        }

        .greeting-badge i {
            color: #3b82f6;
        }

        .greeting-badge span {
            font-size:0.8rem;
            font-weight: 500;
            color: #cbd5e1;
        }

        .welcome-card h1 {
            font-size:2.4rem;
            font-weight: 800;
            background: linear-gradient(145deg, #ffffff, #94a3f0);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom:1rem;
            letter-spacing:-0.0312rem;
        }

        .description {
            color: #a0afc7;
            line-height:1.5;
            font-size:0.95rem;
            margin-bottom:2rem;
            border-left: 0.1875rem solid #3b82f6;
            padding-left:1.25rem;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap:1rem;
            margin-bottom:2.25rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap:0.75rem;
            background: rgba(255, 255, 255, 0.03);
            padding:0.5rem 0.875rem;
            border-radius: 3.75rem;
            font-size:0.85rem;
            color: #cbd5e6;
        }

        .feature-item i {
            width:1.5rem;
            color: #3b82f6;
            font-size:0.9rem;
        }

        /* Grupo de botones */
        .button-group {
            display: flex;
            flex-direction: column;
            gap:0.75rem;
            width:100%;
        }

        .btn-access {
            background: linear-gradient(95deg, #2563eb, #1d4ed8);
            border: none;
            padding:1rem 1.75rem;
            border-radius: 3.75rem;
            font-weight: 700;
            font-size:1rem;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap:0.75rem;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 0.375rem 1.25rem rgba(37, 99, 235, 0.35);
            width:100%;
        }

        .btn-access:hover:not(:disabled) {
            transform: translateY(-0.125rem);
            filter: brightness(1.08);
            box-shadow: 0 0.75rem 1.75rem rgba(37, 99, 235, 0.5);
        }

        .btn-access:active {
            transform: translateY(0.0625rem);
        }

        .btn-access:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            filter: grayscale(0.2);
        }

        .btn-secondary-info {
            background: rgba(30, 41, 59, 0.8);
            border: 0.0625rem solid #334155;
            padding:0.75rem 1.25rem;
            border-radius: 3.75rem;
            font-weight: 500;
            font-size:0.85rem;
            color: #cbd5e6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap:0.625rem;
            cursor: pointer;
            transition: all 0.2s;
            width:100%;
        }

        .btn-secondary-info:hover {
            background: #334155;
            border-color: #3b82f6;
            color: white;
        }

        .btn-back-home {
            background: rgba(30, 41, 59, 0.6);
            border: 0.0625rem solid #475569;
            padding:0.75rem 1.25rem;
            border-radius: 3.75rem;
            font-weight: 500;
            font-size:0.85rem;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap:0.625rem;
            cursor: pointer;
            transition: all 0.2s;
            width:100%;
        }

        .btn-back-home:hover {
            background: #334155;
            border-color: #ef4444;
            color: #fca5a5;
        }

        /* Footer elegante */
        .modern-footer {
            margin-top:0.75rem;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(0.75rem);
            border-radius: 1.5rem;
            padding:0.3125rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap:1rem;
            border: 0.0625rem solid rgba(255, 255, 255, 0.05);
            font-size:0.75rem;
            color: #7e8aa8;
        }

        .footer-left i, .footer-right i {
            margin-right:0.375rem;
        }

        .footer-right {
            display: flex;
            gap:0.625rem;
        }

        /* Estilo para el logo de copyright */
        .copyright-logo {
            display: inline-flex;
            align-items: center;
            gap:0.375rem;
            vertical-align: middle;
        }

        .copyright-logo img {
            width:1.25rem;
            height:1.25rem;
            vertical-align: middle;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(1.875rem);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
                gap:1.5rem;
            }
            .welcome-card h1 {
                font-size:1.8rem;
            }
            .feature-list {
                grid-template-columns: 1fr;
            }
            .modern-header {
                flex-direction: column;
                gap:0.9375rem;
                text-align: center;
            }
            .logo-area {
                justify-content: center;
            }
            .brand-footer {
                flex-direction: column;
                gap:0.5rem;
            }
            .footer-divider {
                width:80%;
                height:0.0625rem;
            }
        }

        @media (max-width: 480px) {
            .welcome-card {
                padding:1.75rem 1.25rem;
            }
            .modern-footer {
                flex-direction: column;
                text-align: center;
            }
            .footer-right {
                justify-content: center;
            }
        }
        
        .unicorn-icon {
            width:1.875rem;
            height:auto;
            filter: brightness(0) invert(1);
        }

        /* Spinner pequeño para loading */
        .loading-spinner-small {
            width:3.125rem;
            height:3.125rem;
            border: 0.1875rem solid rgba(59, 130, 246, 0.2);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spinSmall 0.8s linear infinite;
            margin:0 auto;
        }
        
        @keyframes spinSmall {
            to { transform: rotate(360deg); }
        }
        .brand-footer a:hover {
            color: #60a5fa !important;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Header sin controles de ventana -->
    <div class="modern-header">
        <div class="logo-area">
            <div class="logo-icon">
                <img src="../images/LogoTN.png" alt="<?php echo htmlspecialchars($COMPANY_NAME); ?> Logo" 
                     style="width:100%; height:100%; object-fit: contain;"
                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon\'></i>';">
            </div>
            <div class="logo-text">
                <h2><?php echo htmlspecialchars($COMPANY_NAME); ?> - NÓMINAS</h2>
                <span>Sistema Integral de Nóminas</span>
            </div>
        </div>
        <div class="header-badge">
            <div class="version-chip">
                <i class="fas fa-code-branch"></i> <?php echo $SITE_VERSION; ?> · Estable
            </div>
            <div class="status-dot" id="serverStatusDot">
                <i class="fas fa-circle" id="statusIcon"></i>
                <span id="statusText">Verificando...</span>
            </div>
        </div>
    </div>

    <!-- Grid principal: Imagen + Acceso -->
    <div class="main-grid">
        <!-- Lado izquierdo: imagen institucional -->
        <div class="brand-card">
            <div class="image-wrapper">
                <img src="../images/nominas.png" 
                     alt="Sistema de Nóminas <?php echo htmlspecialchars($COMPANY_NAME); ?>" 
                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 150\'%3E%3Crect width=\'200\' height=\'150\' fill=\'%231e293b\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%233b82f6\' font-family=\'monospace\' font-size=\'14\'%3EMódulo de Nóminas%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="brand-footer">
                <div class="footer-badge">
                    <i class="fas fa-certificate"></i>
                    <span>Certificación ISO 27001</span>
                </div>
                <div class="footer-divider"></div>
                <div class="footer-badge">
                    <i class="fas fa-laptop-code"></i>
                    <span>Entorno Local · Sin conexión a externos</span>
                </div>
                <div class="footer-divider"></div>
                <div class="footer-badge">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Open Source · Sin fines de lucro</span>
                </div>
                <div class="footer-divider"></div>
                <div class="footer-badge">
                    <i class="fas fa-code-branch"></i>
                    <span>Licencia Shareware <?php echo htmlspecialchars($SITE_VERSION); ?></span>
                </div>
                
                <!-- Separador y enlaces legales -->
                <div class="footer-divider"></div>
                <div class="footer-badge" style="gap:0.75rem;">
                    <a href="../terminos.php" style="color: #94a3b8; text-decoration: none; display: inline-flex; align-items: center; gap:0.3125rem; font-size:0.7rem; transition: 0.2s;">
                        <i class="fas fa-file-contract"></i> Términos
                    </a>
                    <span style="color: #475569;">|</span>
                    <a href="../privacidad.php" style="color: #94a3b8; text-decoration: none; display: inline-flex; align-items: center; gap:0.3125rem; font-size:0.7rem; transition: 0.2s;">
                        <i class="fas fa-lock"></i> Privacidad
                    </a>
                    <span style="color: #475569;">|</span>
                    <a href="../soporte.php" style="color: #94a3b8; text-decoration: none; display: inline-flex; align-items: center; gap:0.3125rem; font-size:0.7rem; transition: 0.2s;">
                        <i class="fas fa-headset"></i> Soporte
                    </a>
                    <span style="color: #475569;">|</span>
                    <a href="../contacto.php" style="color: #94a3b8; text-decoration: none; display: inline-flex; align-items: center; gap:0.3125rem; font-size:0.7rem; transition: 0.2s;">
                        <i class="fas fa-envelope"></i> Contáctenos
                    </a>
                </div>
            </div>
        </div>

        <!-- Lado derecho: bienvenida y acciones -->
        <div class="welcome-card">
            <div class="greeting-badge">
                <i class="fas fa-crown"></i>
                <span>Bienvenido al ecosistema financiero</span>
            </div>
            <h1>
                <i class="fas fa-chart-simple"></i> Nómina <span style="background: linear-gradient(145deg,#60a5fa,#3b82f6); background-clip:text; -webkit-background-clip:text; color:transparent;">Inteligente</span>
            </h1>
            <div class="description">
                Gestión completa de salarios, incidencias, cálculos automáticos de ISR, seguridad social, vacaciones y reportes ejecutivos en tiempo real.
            </div>
            <div class="feature-list">
                <div class="feature-item"><i class="fas fa-calculator"></i> Cálculo automatizado</div>
                <div class="feature-item"><i class="fas fa-file-invoice-dollar"></i> Recibos digitales</div>
                <div class="feature-item"><i class="fas fa-chart-line"></i> Dashboards analíticos</div>
                <div class="feature-item"><i class="fas fa-database"></i> Backup automático</div>
                <div class="feature-item"><i class="fas fa-users"></i> Gestión de personal</div>
                <div class="feature-item"><i class="fas fa-lock"></i> Control de accesos</div>
            </div>
            
            <!-- Grupo de 3 botones -->
            <div class="button-group">
                <button class="btn-access" id="accessMainBtn">
                    <i class="fas fa-arrow-right-to-bracket"></i> Acceder al Sistema
                </button>
                <button class="btn-secondary-info" id="infoSystemBtnModern">
                    <i class="fas fa-circle-info"></i> Especificaciones
                </button>
                <button class="btn-back-home" id="backHomeBtn">
                    <i class="fas fa-arrow-left"></i> Regresar al inicio
                </button>
            </div>
        </div>
    </div>

    <!-- Footer informativo con copyright -->
    <div class="modern-footer">
        <div class="footer-left">
            <i class="fas fa-shield-hawk"></i> Modo seguro · Cumplimiento fiscal cubano
        </div>
        <div class="footer-right">
            <span><i class="fas fa-clock"></i> Respaldo automático cada 6h</span>
            <span><i class="fas fa-sync-alt"></i> Sincronización local</span>
        </div>
    </div>
    
    <!-- Línea de copyright -->
    <div style="text-align: center; margin-top:1rem; padding:0.75rem; font-size:0.7rem; color: #5a6a8a;">
        <div class="copyright-logo">
            <img src="../images/Unicorn.png" alt="Unicornio" class="unicorn-icon">
            <span style="font-weight:bold;font-size:0.875rem;color:white;">Copyright © <?php echo $current_year; ?> UnicornioSoftware° - Kaky&reg;. Todos los derechos reservados.</span>
        </div>
    </div>
</div>

<script>
// Estado del servidor desde PHP
const serverStatus = {
    php: <?php echo json_encode($server_status['php']); ?>,
    mysql: <?php echo json_encode($server_status['mysql']); ?>,
    mysqlError: <?php echo json_encode($server_status['mysql_error']); ?>
};

function updateServerStatusUI() {
    const statusDot = document.getElementById('serverStatusDot');
    const statusIcon = document.getElementById('statusIcon');
    const statusText = document.getElementById('statusText');
    const accessBtn = document.getElementById('accessMainBtn');
    
    if (serverStatus.php && serverStatus.mysql) {
        statusDot.className = 'status-dot status-online';
        statusIcon.style.color = '#22c55e';
        statusText.innerHTML = 'PHP + MySQL activos';
        if (accessBtn) accessBtn.disabled = false;
    } else if (serverStatus.php && !serverStatus.mysql) {
        statusDot.className = 'status-dot status-offline';
        statusIcon.style.color = '#ef4444';
        statusText.innerHTML = 'MySQL desconectado';
        if (accessBtn) accessBtn.disabled = true;
    } else {
        statusDot.className = 'status-dot status-offline';
        statusIcon.style.color = '#ef4444';
        statusText.innerHTML = 'Servidor PHP error';
        if (accessBtn) accessBtn.disabled = true;
    }
}

function verificarEstadoConLoading() {
    Swal.fire({
        title: '🔄 Verificando estado...',
        html: `<div style="text-align:center;"><div class="loading-spinner-small"></div><p style="margin-top:0.9375rem;">Comprobando conexión con el servidor...</p></div>`,
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    setTimeout(() => location.reload(), 1500);
}

window.addEventListener('DOMContentLoaded', () => {
    updateServerStatusUI();
});

// Botón acceso principal: comprueba estado de MySQL y muestra SweetAlert si está desconectado
const accessButton = document.getElementById('accessMainBtn');
if (accessButton) {
    accessButton.addEventListener('click', (e) => {
        e.preventDefault();
        if (!serverStatus.mysql) {
            Swal.fire({
                title: '❌ Error de conexión',
                html: `<div style="text-align:left;">
                        <p><strong>No se puede acceder al panel</strong></p>
                        <p>La base de datos MySQL no está disponible.</p>
                        <p><strong>Error detectado:</strong> ${serverStatus.mysqlError || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</p>
                        <hr style="margin:0.75rem 0; border-color:rgba(255,255,255,0.1);">
                        <p><i class="fas fa-info-circle"></i> Verifica que el servicio de MySQL esté activo.</p>
                        <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                                ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($index_email_soporte, ENT_QUOTES, 'UTF-8'); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($index_email_soporte, ENT_QUOTES, 'UTF-8'); ?></a></p>
                                </div>`,
                icon: 'error',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fas fa-tools"></i> Entendido'
            });
            return;
        }
        window.location.href = 'login.php';
    });
}

// Botón de regresar al inicio
const backButton = document.getElementById('backHomeBtn');
if (backButton) {
    backButton.addEventListener('click', (e) => {
        e.preventDefault();
        Swal.fire({
            title: '¿Regresar al inicio?',
            text: 'Se cerrará el acceso al sistema de nóminas',
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

// Información detallada del sistema (muestra también el estado actual de MySQL)
const infoModern = document.getElementById('infoSystemBtnModern');
if (infoModern) {
    infoModern.addEventListener('click', () => {
        const mysqlStatus = serverStatus.mysql ? 
            '<span style="color:2;"><i class="fas fa-check-circle"></i> Conectado</span>' : 
            '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> Desconectado</span>';
        Swal.fire({
            title: '<i class="fas fa-microchip"></i> Arquitectura del sistema',
            html: `
            <div style="text-align: left; max-height:22.5rem; overflow-y: auto; padding-right:0.5rem;">
                <p><i class="fas fa-cube" style="color:#3b82f6"></i> <strong>Motor:</strong> PHP 8.1 + MySQL 5.7</p>
                <p><i class="fas fa-database" style="color:#3b82f6"></i> <strong>Estado MySQL:</strong> ${mysqlStatus}</p>
                <p><i class="fas fa-calculator" style="color:#3b82f6"></i> <strong>Cálculos:</strong> Salario por escala, horas extras, ISR progresivo, contribución especial (progresivo hasta 10%).</p>
                <p><i class="fas fa-chart-pie" style="color:#3b82f6"></i> <strong>Reportes:</strong> Nóminas mensuales, acumulados por trabajador, vacaciones, cierres contables.</p>
                <p><i class="fas fa-shield-alt" style="color:#3b82f6"></i> <strong>Seguridad:</strong> Validación de roles, autenticación local, trazabilidad.</p>
                <p><i class="fas fa-cloud-upload-alt" style="color:#3b82f6"></i> <strong>Backup:</strong> Automático + manual de la base de datos.</p>
                <p><i class="fas fa-window-restore" style="color:#3b82f6"></i> <strong>UI/UX:</strong> Modo oscuro, diseño fluido, experiencia tipo escritorio.</p>
                <hr style="border-color: #334155; margin:0.75rem 0;">
                <p style="text-align: center; font-size:0.75rem; color: #6b7a9a;">
                    <img src="../images/Unicorn.png" class="unicorn-icon" alt="UnicornioSoftware" style="width:1rem; height:1rem; vertical-align: middle; margin-right:0.25rem;" onerror="this.style.display='none'">
                    Copyright © <?php echo $current_year; ?> UnicornioSoftware° - Kaky&reg;
                </p>
            </div>`,
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check"></i> Comprendido',
            background: '#0f172a',
            width: '36.25rem',
            confirmButtonColor: '#2563eb'
        });
    });
}

// Click en el estado del servidor: muestra detalles y permite verificar manualmente
const serverStatusDot = document.getElementById('serverStatusDot');
if (serverStatusDot) {
    serverStatusDot.addEventListener('click', () => {
        if (serverStatus.php && serverStatus.mysql) {
            Swal.fire({
                title: '✅ Estado del servidor',
                html: `<div style="text-align:left;">
                        <p><strong>PHP:</strong> <span style="color:2;">✓ Activo</span></p>
                        <p><strong>MySQL:</strong> <span style="color:2;">✓ Conectado correctamente</span></p>
                        <p><strong>Base de datos:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
                        <hr style="margin:0.9375rem 0; border-color:rgba(255,255,255,0.1);">
                        <p><i class="fas fa-check-circle" style="color:2"></i> Todos los sistemas operativos</p>
                        </div>`,
                icon: 'success',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: '<i class="fas fa-check"></i> Aceptar',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-sync-alt"></i> Verificar Estado',
                cancelButtonColor: '#475569'
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) verificarEstadoConLoading();
            });
        } else if (serverStatus.php && !serverStatus.mysql) {
            Swal.fire({
                title: '⚠️ Estado del servidor',
                html: `<div style="text-align:left;">
                        <p><strong>PHP:</strong> <span style="color:2;">✓ Activo</span></p>
                        <p><strong>MySQL:</strong> <span style="color:#ef4444;">✗ Desconectado</span></p>
                        <p><strong>Error:</strong> <span style="color:#fca5a5;">${serverStatus.mysqlError || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</span></p>
                        <hr style="margin:0.9375rem 0; border-color:rgba(255,255,255,0.1);">
                        <p><i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Verifica el servicio de MySQL</p>
                        <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                                ✉️ <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($index_email_soporte, ENT_QUOTES, 'UTF-8'); ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($index_email_soporte, ENT_QUOTES, 'UTF-8'); ?></a></p>
                                </div>`,
                icon: 'warning',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-sync-alt fa-fw"></i> Verificar Estado',
                cancelButtonColor: '#475569'
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) verificarEstadoConLoading();
            });
        }
    });
}
// ============================================
// Hacer que el botón "Acceder al sistema" se active al presionar Enter
// ============================================
document.addEventListener('keypress', function(event) {
    // Verificar que la tecla presionada sea Enter
    if (event.key === 'Enter') {
        // Prevenir comportamiento por defecto (evita recargar o enviar formularios fantasmas)
        event.preventDefault();
        
        // Verificar si el botón de acceso está habilitado (no deshabilitado por MySQL)
        const accessBtn = document.getElementById('accessMainBtn');
        if (accessBtn && !accessBtn.disabled) {
            // Simular clic en el botón de acceso
            accessBtn.click();
        }
    }
});
</script>
</body>
</html>