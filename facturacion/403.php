<?php
http_response_code(403);
$page_title = "403 - Acceso Denegado | SISFAC PDL Visiones";
$urlActual = $_SERVER['REQUEST_URI']; // Ej: "/mi-app/soporte.php"
$partesUrl = explode('/', trim($urlActual, '/'));
$CarpetaSistema = $partesUrl[0] ?? 'sisfactvisiones';
$BASE_URL = '/' . $CarpetaSistema . '/';
?>
<!DOCTYPE html>
<html lang="es" class="dark-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
	<link rel="icon" type="image/x-icon" href="<?php echo $BASE_URL; ?>assets/logov.png">
    <style>
        :root {
            --mica-background: rgba(32, 32, 32, 0.85);
            --mica-border: rgba(255, 255, 255, 0.08);
            --accent-color: #D13438;
            --accent-light: #FF4F4F;
            --text-primary: #F3F3F3;
            --text-secondary: #C0C0C0;
            --surface-dark: #1E1E1E;
            --surface-darker: #141414;
            --shadow-dark: rgba(0, 0, 0, 0.4);
            --shadow-light: rgba(255, 255, 255, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #1a0f2e 0%, #2d1b4e 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        /* Header (igual que 404) */
        .header {
            background: var(--mica-background);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--mica-border);
            padding: 10px 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 12px var(--shadow-dark);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #D13438, #FF4F4F);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(209, 52, 56, 0.3);
        }

        .logo-icon i {
            font-size: 20px;
            color: white;
        }

        .logo-text h1 {
            font-size: 24px;
            font-weight: 600;
            background: linear-gradient(90deg, #FF4F4F, #FF8E53);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-text .subtitle {
            font-size: 12px;
            color: var(--text-secondary);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-menu {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .nav-item {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            color: #FF8E53;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            color: #FF8E53;
            background: rgba(209, 52, 56, 0.15);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid var(--mica-border);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D13438 0%, #FF8E53 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
        }

        .error-container {
            max-width: 700px;
            text-align: center;
            background: var(--mica-background);
            backdrop-filter: blur(30px) saturate(200%);
            -webkit-backdrop-filter: blur(30px) saturate(200%);
            border-radius: 24px;
            padding: 18px;
            border: 1px solid var(--mica-border);
            box-shadow: 
                0 20px 40px var(--shadow-dark),
                inset 0 1px 0 var(--shadow-light);
        }

        .error-icon {
            font-size: 80px;
            margin-bottom: 24px;
            color: var(--accent-color);
            opacity: 0.9;
        }

        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, #D13438, #FF8E53);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin: 0px 0;
        }

        .error-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .error-description {
            color: var(--text-secondary);
            font-size: 16px;
            margin-bottom: 12px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .security-alert {
            background: rgba(209, 52, 56, 0.1);
            border: 1px solid rgba(209, 52, 56, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin: 10px 0;
            text-align: left;
        }

        .security-alert h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #FF8E53;
            margin-bottom: 12px;
        }

        .security-alert ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .security-alert li {
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 12px;
        }

        .btn {
            padding: 10px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Segoe UI', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-light));
            color: white;
            box-shadow: 0 4px 12px rgba(209, 52, 56, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(209, 52, 56, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--mica-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-logout {
            background: linear-gradient(135deg, #6b21a8, #8b5cf6);
            color: white;
            box-shadow: 0 4px 12px rgba(107, 33, 168, 0.3);
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(107, 33, 168, 0.4);
        }

        /* Footer */
        .footer {
            background: var(--mica-background);
            backdrop-filter: blur(20px) saturate(180%);
            border-top: 1px solid var(--mica-border);
            padding: 14px 12px;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

.footer-logo-icon {
    width: 32px;
    height: 32px;
    background-image: url("<?php echo $BASE_URL; ?>assets/logov.png");
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    border-radius: 8px;
    /* Opcional: añadir un leve brillo si el logo es oscuro */
    filter: drop-shadow(0 0 2px rgba(255,255,255,0.2));
}

        .footer-info {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .footer-link:hover {
            color: #FF8E53;
        }

        /* Mica Effect */
        .mica {
            background: var(--mica-background);
            backdrop-filter: blur(30px) saturate(200%);
            -webkit-backdrop-filter: blur(30px) saturate(200%);
            border: 1px solid var(--mica-border);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content, .footer-content {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .error-code {
                font-size: 80px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo $BASE_URL; ?>css/font-awesome6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header mica">
        <div class="header-content">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">
                    <h1>SISFAC PDL Visiones</h1>
                    <div class="subtitle">Sistema de Facturación</div>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="<?php echo $BASE_URL; ?>index.php" class="nav-item"><i class="fas fa-home"></i> Inicio</a>
                <a href="<?php echo $BASE_URL; ?>login.php" class="nav-item"><i class="fas fa-key"></i> Acceder al Sistema</a>
                <a href="<?php echo $BASE_URL; ?>soporte.php" class="nav-item active"><i class="fas fa-headset"></i> Soporte</a>
            </nav>
            
            <div class="user-info">
                <div class="user-avatar">SA</div>
                <div>
                    <div class="user-name">Soporte</div>
                    <div class="user-role">Administrador</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-ban"></i>  <span class="error-code">403</span>
            </div>
            
            <div class="error-title">Acceso Denegado</div>
            <div class="error-description">
                No tienes los permisos necesarios para acceder a este recurso. 
                Este intento ha sido registrado en el sistema de seguridad.
            </div>
            
            <div class="security-alert">
                <h4><i class="fas fa-exclamation-triangle"></i> Medidas de Seguridad Activadas</h4>
                <ul>
                    <li>Acceso restringido por políticas del sistema</li>
                    <li>Registro de auditoría generado automáticamente</li>
                    <li>Notificación enviada al administrador del sistema</li>
                </ul>
                <p style="font-size: 14px; color: #FF8E53;">
                    <i class="fas fa-clock"></i> Timestamp: <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo $BASE_URL; ?>index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver Atrás
                </button>
                <a href="<?php echo $BASE_URL; ?>login.php" class="btn btn-logout">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
            </div>
            
            <div style="margin-top: 10px; padding: 20px; background: rgba(209, 52, 56, 0.05); border-radius: 12px; border-left: 4px solid var(--accent-color);">
                <h4 style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-shield"></i> Información de Seguridad
                </h4>
                <p style="font-size: 14px; color: var(--text-secondary);">
                    <strong>SISFAC PDL Visiones v2.3.3</strong> | Nivel de seguridad: Alto | 
                    ID de incidente: INC-<?php echo date('YmdHis'); ?>
                </p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mica">
        <div class="footer-content">
            <div class="footer-logo">
                <div class="footer-logo-icon"></div>
                <div>
                    <div style="font-weight: 600;">SISFAC PDL Visiones</div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Sistema de Facturación</div>
                </div>
            </div>
            
            <div class="footer-info">
                © <?php echo date('Y'); ?> PDL Visiones. Sistema protegido por medidas de seguridad.
            </div>
            
            <div class="footer-links">
                <a href="/sisfactvisiones/soporte.php" class="footer-link"><i class="fas fa-headset"></i> Soporte Técnico</a>
                <a href="/sisfactvisiones/politica-seguridad" class="footer-link"><i class="fas fa-shield-alt"></i> Política de Seguridad</a>
                <a href="contacto-admin" class="footer-link"><i class="fas fa-envelope"></i> Contactar Admin</a>
            </div>
        </div>
    </footer>

    <script>
        // Efecto de parpadeo para el código de error
        const errorCode = document.querySelector('.error-code');
        setInterval(() => {
            errorCode.style.opacity = errorCode.style.opacity === '0.8' ? '1' : '0.8';
        }, 1000);
    </script>
</body>
</html>