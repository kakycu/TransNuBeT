<?php
/**
 * Header unificado para todos los módulos del sistema
 * Este archivo debe ser incluido al inicio de cada página PHP
 * 
 * Uso:
 * require_once '../includes/header.php';
 * 
 * Variables esperadas (opcionales, con valores por defecto):
 * - $page_title: Título de la página (string)
 * - $page_icon: Icono de FontAwesome para la página (string)
 * - $page_subtitle: Subtítulo de la página (string)
 * - $hide_sidebar: Boolean para ocultar sidebar (por defecto false)
 * - $hide_topbar: Boolean para ocultar topbar (por defecto false)
 * - $extra_css: Array de archivos CSS adicionales
 * - $extra_js: Array de archivos JS adicionales (se cargarán después de los scripts base)
 */

// ============================================
// VERIFICAR SESIÓN (Obligatoria)
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// ============================================
// CONFIGURACIÓN BASE
// ============================================
require_once __DIR__ . '/../config/database.php';

// Obtener configuración de la empresa (desde constantes centrales con fallback)
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas',
    'reeup_empresa' => defined('REEUP_EMPRESA') ? REEUP_EMPRESA : 'S/R'
];

try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general 
                         WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'reeup_empresa')");
    while ($row = $stmt->fetch()) {
        if (isset($config_empresa[$row['parametro']])) {
            $config_empresa[$row['parametro']] = $row['valor'];
        }
    }
} catch (PDOException $e) {
    // Si hay error, mantener valores por defecto
}

// ============================================
// DATOS DEL USUARIO (Desde sesión)
// ============================================
$user_nombre_completo = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['user_ci'] ?? $_SESSION['usuario_ci'] ?? '';
$user_email = $_SESSION['user_email'] ?? $_SESSION['usuario_email'] ?? '';

$is_google_auth   = (isset($_SESSION['auth_provider']) && $_SESSION['auth_provider'] === 'google');
$user_foto_header = $_SESSION['user_foto'] ?? '';

// ============================================
// LOGO PARA EXPORTACIONES
// ============================================
$logo_base64 = '';
$ruta_logo = __DIR__ . '/../logotn.png';
if (file_exists($ruta_logo)) {
    $type = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// ============================================
// VARIABLES DE PÁGINA CON VALORES POR DEFECTO
// ============================================
$page_title = $page_title ?? 'Gestión del Sistema';
$page_icon = $page_icon ?? 'fas fa-chart-line';
$page_subtitle = $page_subtitle ?? '';
$hide_sidebar = $hide_sidebar ?? false;
$hide_topbar = $hide_topbar ?? false;

// CSS y JS adicionales
$extra_css = $extra_css ?? [];
$extra_js = $extra_js ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include __DIR__ . '/theme_config.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap5.3.0/buttons.dataTables.min.css">
    
    <!-- Cropper CSS (si se necesita en algún módulo) -->
    <link rel="stylesheet" href="../css/cropper.css">
    
    <!-- Tooltip System -->
    <link rel="stylesheet" href="../assets/css/tooltips.css">
    
    <!-- Base responsive global (mobile-first) -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
    
    <!-- CSS Adicionales por módulo -->
    <?php foreach ($extra_css as $css_file): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>">
    <?php endforeach; ?>
    
    <style>
        /* ============================================
           ESTILOS GLOBALES - Windows 11 Style
           ============================================ */
        * { margin:0; padding:0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: #0c0c0c; 
            overflow-x: hidden; 
            color: #ffffff; 
        }

        /* Windows 11 Acrylic Background */
        .win11-bg {
            position: fixed; top:0; left:0; width:100%; height:100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top:0; left:0; width:100%; height:100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glassmorphism Effect */
        .glass-card {
            background: rgba(28, 28, 35, 0.6); 
            backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); 
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { 
            transform: translateY(-0.125rem); 
            background: rgba(35, 35, 45, 0.7); 
            border-color: rgba(0, 120, 212, 0.3); 
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); 
        }

        /* Sidebar Windows 11 Style */
        .win-sidebar {
            position: fixed; left:0; top:0; height:100vh; width:16.25rem;
            background: rgba(20, 20, 25, 0.85); backdrop-filter: blur(1.875rem);
            border-right: 0.0625rem solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width:5rem; }
        .win-sidebar.collapsed .sidebar-text, 
        .win-sidebar.collapsed .sidebar-expand-only { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding:0.75rem; }
        .win-sidebar.collapsed .nav-item i { margin:0; font-size:1.5rem; }

        .sidebar-logo { 
            padding:1.5rem 1.25rem; 
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); 
            margin-bottom:1.25rem; 
            text-align: center; 
        }
        .sidebar-logo h3 { 
            font-size:1.2rem; 
            font-weight: 600; 
            background: linear-gradient(135deg, var(--accent), var(--accent-light)); 
            -webkit-background-clip: text; 
            background-clip: text; 
            color: transparent; 
            margin:0; 
        }
        .sidebar-logo small { font-size:0.7rem; color: rgba(255, 255, 255, 0.5); }

        .sidebar-nav { flex: 1; padding:0 0.75rem; }
        .nav-item {
            display: flex; align-items: center; gap:0.875rem; padding:0.75rem 1rem;
            margin-bottom:0.375rem; border-radius: 0.75rem;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { 
            background: var(--accent); 
            color: #fff; 
            border-left: 0.1875rem solid var(--accent-dark); 
        }
        .nav-item.active i { color: #fff; }
        .nav-item i { width:1.5rem; font-size:1.2rem; text-align: center; }
        .nav-item span { font-size:0.9rem; font-weight: 500; }

        /* Main Content */
        .main-container { 
            margin-left:16.25rem; 
            transition: all 0.3s ease; 
            min-height:100vh; 
            padding:1.25rem; 
        }
        .main-container.expanded { margin-left:5rem; }

        /* Top Bar Windows 11 */
        .win-topbar {
            background: rgba(20, 20, 25, 0.7); 
            backdrop-filter: blur(1.25rem); 
            border-radius: 1rem;
            padding: 0.75rem 1.5rem; 
            margin-bottom: 1.5rem; 
            border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; 
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between; 
            align-items: center;
            z-index: 100 !important; 
            position: relative !important;
        }
        .sidebar-toggle { 
            background: rgba(255, 255, 255, 0.05); 
            border: none; 
            color: white; 
            width:2.5rem; 
            height:2.5rem; 
            border-radius: 0.75rem; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .sidebar-toggle:hover { background: rgba(var(--accent-rgb), 0.15); transform: scale(1.02); }
        .page-title h1 { font-size: clamp(1.05rem, 3vw, 1.5rem); font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        /* Overlay del sidebar en móvil */
        .sidebar-backdrop {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 999;
        }
        .sidebar-backdrop.show { display: block; }

        /* Contenedor máximo en pantallas extra grandes */
        @media (min-width: 1025px) {
            .main-container > * { max-width: 75rem; margin-left:auto; margin-right:auto; width:100%; }
        }

        /* User Menu & Dropdowns */
        .user-menu { display: flex; align-items: center; gap:1rem; }
        .user-avatar { 
            width:2.5rem; 
            height:2.5rem; 
            background: linear-gradient(135deg, #3b82f6, #8b5cf6); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: all 0.2s; 
            position: relative; 
            z-index: 1050 !important; 
        }
        .user-avatar:hover { transform: scale(2.05); }

        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; 
            backdrop-filter: blur(1.25rem) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important; 
            border-radius: 0.75rem !important;
            padding:0.5rem !important; 
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { 
            color: #ffffff !important; 
            border-radius: 0.5rem !important; 
            padding:0.625rem 1rem !important; 
            font-size:0.9rem !important; 
        }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(255, 255, 255, 0.1) !important; }
        .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }

        /* Buttons Windows 11 */
        .btn-win {
            background: rgba(255, 255, 255, 0.08); 
            border: 0.0625rem solid rgba(255, 255, 255, 0.1); 
            padding:0.5rem 1rem;
            border-radius: 0.625rem; 
            color: white; 
            font-size:0.85rem; 
            transition: all 0.2s; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap:0.5rem;
        }
        .btn-win:hover { 
            background: rgba(0, 120, 212, 0.6); 
            border-color: #0078d4; 
            transform: translateY(-0.0625rem); 
            color: white; 
        }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-0.0625rem); }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }
        .btn-win-success { background: linear-gradient(135deg, var(--color-success), var(--color-success)); border: none; }
        .btn-win-warning { background: linear-gradient(135deg, #f59e0b, #d97706); border: none; }
        .btn-win-info { background: linear-gradient(135deg, #0ea5e9, #0369a1); border: none; }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; color: #ffffff !important; }
        .btn-win-sm:hover { color: #ffffff !important; }

        /* Formularios */
        .form-label { color: rgba(255, 255, 255, 0.85); font-size:0.85rem; font-weight: 500; margin-bottom:0.5rem; }
        .form-select, .form-control { 
            background: rgba(20, 20, 25, 0.8); 
            border: 0.0625rem solid rgba(255, 255, 255, 0.1); 
            border-radius: 0.625rem; 
            color: #ffffff; 
            padding:0.5rem 0.75rem; 
        }
        .form-select:focus, .form-control:focus { 
            background: rgba(20, 20, 25, 0.9); 
            border-color: #60a5fa; 
            outline: none; 
            box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2); 
            color: #ffffff; 
        }

        /* Date Badge */
        .date-badge {
            background: rgba(255, 255, 255, 0.08);
            padding:0.5rem 1rem;
            border-radius: 0.75rem;
            font-size:0.85rem;
        }
        #liveClock {
            display: inline-block;
            min-width:5.3125rem;
            text-align: center;
            font-variant-numeric: tabular-nums;
            letter-spacing:0.0312rem;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }

        /* Responsive: móvil primero, mejora progresiva con min-width.
           !important + prefijo body para vencer al CSS del tema (inyectado al final con margin-left fijo). */
        @media (max-width: 768px) { 
            body .win-sidebar { transform: translateX(-100%) !important; } 
            body .win-sidebar.mobile-open { transform: translateX(0) !important; width: min(16.5625rem, 85vw) !important; }
            body .main-container,
            body .main-container.expanded { margin-left:0 !important; width:auto !important; padding: 0.75rem; } 
            .main-container > .glass-card,
            .main-container > .mb-3,
            .main-container > .fade-in-up,
            .main-container > .win-topbar { max-width:100% !important; margin-left:0 !important; margin-right:0 !important; }
        }

        /* Text colors */
        .text-muted { color: rgba(255, 255, 255, 0.65) !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-success { color: var(--color-success-soft) !important; }
        .text-danger { color: #f87171 !important; }
        .text-info { color: #60a5fa !important; }
        
        /* Select arrow fix */
        .form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            padding-right:2.5rem !important;
        }
        .form-select:active, .form-select:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 15 12 9 18 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }
    </style>
</head>
<body>

<div class="win11-bg"></div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<?php if (!$hide_sidebar): ?>
    <?php include __DIR__ . '/sidebar.php'; ?>
<?php endif; ?>

<script>
(function () {
    var sidebar = document.querySelector('.win-sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !backdrop) return;
    function sync() { var movil = window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil'; backdrop.classList.toggle('show', movil && sidebar.classList.contains('mobile-open')); }
    new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', sync);
    backdrop.addEventListener('click', function () { sidebar.classList.remove('mobile-open'); sync(); });
})();
</script>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    
    <?php if (!$hide_topbar): ?>
    <!-- Top Bar Windows 11 -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" data-tooltip="Abrir / cerrar menú" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="<?php echo $page_icon; ?> me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <?php if ($page_subtitle): ?>
                    <p><?php echo htmlspecialchars($page_subtitle); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="user-menu">
            <!-- Espacio para botones específicos del módulo -->
            <div id="moduleSpecificButtons"></div>
            
            <!-- Fecha y Hora -->
            <div class="date-badge d-none d-md-block" data-tooltip="<?php echo date('l, d \d\e F \d\e Y'); ?>" data-tooltip-theme="secondary">
                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d/m/Y'); ?>
                <span class="mx-1">•</span>
                <i class="fas fa-clock me-1"></i> <span id="liveClock"></span>
            </div>
            
            <!-- User Avatar / Menú de Usuario -->
            <div class="dropdown">
				<div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false" data-tooltip="Menú de usuario" data-tooltip-theme="gradient">
					<?php if (!empty($user_foto_header)): ?>
						<img src="<?php echo htmlspecialchars($user_foto_header); ?>" 
						 alt="Foto" 
						 referrerpolicy="no-referrer"
						 style="width:100%; height:100%; object-fit:cover; border-radius:50%;" 
						 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
						<i class="fas fa-user fa-lg" style="display:none;"></i>
					<?php else: ?>
						<i class="fas fa-user fa-lg"></i>
					<?php endif; ?>
				</div>
                <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                    <li>
                        <div class="dropdown-item-text">
                            <div class="fw-bold mb-1 dropdown-user-name"><?php echo htmlspecialchars($user_nombre_completo); ?></div>
                            <small class="dropdown-user-ci">
                                <i class="fas fa-id-card me-1"></i> CI: <?php echo htmlspecialchars($user_ci); ?>
                            </small>
                            <div class="mt-1">
                                <span class="badge dropdown-user-badge">
                                    <i class="fas fa-user-tag me-1"></i> <?php echo htmlspecialchars($user_rol_descripcion ?: ($user_rol_codigo ?: 'Usuario')); ?>
                                </span>
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-lock me-2"></i>Cambiar Contraseña</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" id="logoutBtn"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Inicio del contenido específico de cada página -->