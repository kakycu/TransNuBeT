<?php
http_response_code(403);
$current_directory = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$page_title = "Acceso Restringido | SISFAC PDL Visiones";
$incident_id = strtoupper(substr(md5(time()), 0, 8));
$urlActual = $_SERVER['REQUEST_URI']; // Ej: "/mi-app/soporte.php"
$partesUrl = explode('/', trim($urlActual, '/'));
$CarpetaSistema = $partesUrl[0] ?? 'sisfactvisiones';
$BASE_URL = '/' . $CarpetaSistema . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" type="image/x-icon" href="<?php echo $BASE_URL; ?>assets/logov.png">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="<?php echo $BASE_URL; ?>css/font-awesome6.4.0/css/all.min.css">
    <style>
        :root {
            /* Paleta Windows 11 Dark Mode */
            --bg-dark: #0a0a0a;
            --mica-bg: rgba(28, 28, 28, 0.75);
            --mica-border: rgba(255, 255, 255, 0.1);
            --accent: #d13438; /* Rojo seguridad Windows */
            --accent-hover: #e81123;
            --text-main: #ffffff;
            --text-muted: #a0a0a0;
            --glass-shine: rgba(255, 255, 255, 0.05);
            --info-blue: #0078d4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(209, 52, 56, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 120, 212, 0.1) 0%, transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Efecto de ruido sutil para simular textura Mica */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("<?php echo $BASE_URL; ?>assets/carbon-fibre.png");
            opacity: 0.03;
            pointer-events: none;
            z-index: -1;
        }

        /* --- HEADER --- */
        .header {
            padding: 15px 40px;
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(25px);
            border-bottom: 1px solid var(--mica-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 35px; height: 35px;
            background: var(--accent);
            border-radius: 8px;
            display: grid; place-items: center;
            box-shadow: 0 0 20px rgba(209, 52, 56, 0.4);
        }

        .brand-text h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, #ccc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-badge {
            font-size: 11px;
            background: rgba(209, 52, 56, 0.2);
            color: #ff6e6e;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(209, 52, 56, 0.3);
            display: flex; align-items: center; gap: 6px;
        }

        .status-dot {
            width: 6px; height: 6px;
            background: #ff6e6e;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        /* --- MAIN CONTENT --- */
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
        }

        .card {
            background: var(--mica-bg);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            width: 100%;
            max-width: 850px;
            border-radius: 20px;
            border: 1px solid var(--mica-border);
            padding: 10px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            position: relative;
            overflow: hidden;
        }

        .card::after {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--mica-border), transparent);
        }

        .error-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .error-header i {
            font-size: 60px;
            color: var(--accent);
            margin-bottom: 15px;
            filter: drop-shadow(0 0 15px rgba(209, 52, 56, 0.3));
        }

        .error-header h2 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .error-header p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* --- GRID DE INFORMACIÓN --- */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--mica-border);
            border-radius: 12px;
            padding: 20px;
        }

        .info-box h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 15px;
            display: flex; align-items: center; gap: 8px;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .data-label { color: var(--text-muted); }
        .data-value { font-family: 'Consolas', monospace; color: var(--info-blue); }

        .requirement-item {
            display: flex; align-items: center; gap: 10px;
            font-size: 14px;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .requirement-item i { font-size: 12px; color: #32d74b; }

        /* --- BOTONES --- */
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 8px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(209, 52, 56, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 1px solid var(--mica-border);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--text-muted);
        }

        /* --- FOOTER --- */
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid var(--mica-border);
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .card { padding: 30px 20px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .header { padding: 15px 20px; }
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="brand">
            <div class="brand-logo">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="brand-text">
                <h1>SISFAC PDL Visiones</h1>
            </div>
        </div>
        <div class="status-badge">
            <div class="status-dot"></div>
            Cortafuegos Activo
        </div>
    </header>

    <main class="main">
        <section class="card">
            <div class="error-header">
                <i class="fas fa-ban"></i>
                <h2>ERROR: Sin Página de Inicio</h2>
				<h3>No se ha encontrado la Página de Inicio</h3>
                <p>El listado de directorios ha sido bloqueado para proteger la integridad del servidor.</p>
            </div>

            <div class="info-grid">
                <!-- Auditoría Técnica -->
                <div class="info-box">
                    <h3><i class="fas fa-fingerprint"></i> Auditoría Técnica</h3>
                    <div class="data-row">
                        <span class="data-label">Recurso:</span>
                        <span class="data-value"><?php echo htmlspecialchars($current_directory); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">IP Cliente:</span>
                        <span class="data-value"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">ID Incidente:</span>
                        <span class="data-value">#<?php echo $incident_id; ?></span>
                    </div>
                </div>

                <!-- Requisitos de Acceso -->
                <div class="info-box">
                    <h3><i class="fas fa-key"></i> Requisitos de Acceso</h3>
                    <div class="requirement-item">
                        <i class="fas fa-check"></i> Poseer archivo index (.php, .html)
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-check"></i> Permisos de lectura CHMOD 644
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-check"></i> Token de sesión administrativa
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="<?php echo $BASE_URL; ?>login.php" class="btn btn-primary">
                    <i class="fas fa-fingerprint"></i> Acceder al Sistema
                </a>
                <button onclick="history.back()" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Regresar
                </button>
                <a href="<?php echo $BASE_URL; ?>soporte.php" class="btn btn-outline">
                    <i class="fas fa-headset"></i> Soporte IT
                </a>
            </div>

            <p style="margin-top: 40px; text-align: center; font-size: 11px; color: var(--text-muted);">
                Seguridad de nivel empresarial aplicada automáticamente por SISFAC Kernel v2.1.0
            </p>
        </section>
    </main>

    <footer class="footer">
        © <?php echo date('Y'); ?> PDL Visiones - Todos los derechos reservados. <br>
        Sistema de Protección de Datos y Gestión de Facturación.
    </footer>

    <script>
        // Registrar el intento de acceso en consola para auditoría local
        console.warn("%c[SEGURIDAD]%c Intento de acceso a directorio protegido: <?php echo $current_directory; ?>", 
                     "background: #d13438; color: white; padding: 2px 5px; border-radius: 3px;", 
                     "color: #a0a0a0;");
    </script>
</body>
</html>