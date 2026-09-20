<?php
http_response_code(404);
$page_title = "404 - Recurso no encontrado | SISFAC PDL Visiones";
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
	<link rel="icon" type="image/x-icon" href="<?php echo $BASE_URL; ?>assets/logov.png">
    <title><?php echo $page_title; ?></title>
    <style>
        :root {
            --mica-background: rgba(32, 32, 32, 0.85);
            --mica-border: rgba(255, 255, 255, 0.08);
            --accent-color: #0078D4;
            --accent-light: #2997FF;
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
            background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        /* Header Styles */
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
            background: linear-gradient(135deg, var(--accent-color), var(--accent-light));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 120, 212, 0.3);
        }

        .logo-icon i {
            font-size: 20px;
            color: white;
        }

        .logo-text h1 {
            font-size: 24px;
            font-weight: 600;
            background: linear-gradient(90deg, var(--accent-light), #8A2BE2);
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
            color: var(--accent-light);
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            color: var(--accent-light);
            background: rgba(0, 120, 212, 0.15);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 4px 12px rgba(0, 120, 212, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 120, 212, 0.4);
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

        /* Footer Styles */
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
            color: var(--accent-light);
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
                    <i class="fas fa-chart-line"></i>
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

    <main class="main-content">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-ban"></i>  <span class="error-code">404</span>
            </div>
            <div class="error-title">Página no encontrada</div>
            <div class="error-description">
                El recurso que estás buscando no existe o ha sido movido. 
                Verifica la URL o navega a través del menú principal.
            </div>
            
            <div class="action-buttons">
                <a href="/sisfactvisiones/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
                <a href="/sisfactvisiones/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver Atrás
                </button>
            </div>
            
            <div style="margin-top: 40px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 12px; border-left: 4px solid var(--accent-color);">
                <h4 style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i> Información del Sistema
                </h4>
                <p style="font-size: 14px; color: var(--text-secondary);">
                    <strong>SISFAC PDL Visiones v2.3.3</strong> | Última actualización: <?php echo date('d/m/Y'); ?> | 
                    Error registrado en bitácora del sistema.
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
                <a href="<?php echo $BASE_URL; ?>soporte.php" class="footer-link"><i class="fas fa-headset"></i> Soporte Técnico</a>
                <a href="<?php echo $BASE_URL; ?>politica-seguridad" class="footer-link"><i class="fas fa-shield-alt"></i> Política de Seguridad</a>
                <a href="mailto:kakycu@gmail.com?subject=no%20existe%20el%20recurso%20web%20en%20SISFACT%20PDL%20VISIONES" class="footer-link">
					<i class="fas fa-envelope"></i> Contactar Admin
				</a>
            </div>
        </div>
    </footer>

    <script>
        // Efecto Mica dinámico
        document.addEventListener('mousemove', (e) => {
            const cards = document.querySelectorAll('.mica');
            cards.forEach(card => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        });
    </script>
</body>
</html>