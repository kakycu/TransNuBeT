<?php
// capturador_facturas.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Colores del tema
$temas_windows = [
    'dark' => [
        'nombre' => 'Windows Dark',
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1f1f1f',
        'bg_tertiary' => '#2d2d2d',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a6a6a6',
        'border_color' => '#3d3d3d',
        'accent_color' => $color_accent
    ],
    'light' => [
        'nombre' => 'Windows Light',
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#000000',
        'text_secondary' => '#666666',
        'border_color' => '#e5e5e5',
        'accent_color' => $color_accent
    ]
];

$tema_actual = $temas_windows[$tema_windows];

// Colores de acento disponibles
$colores_accent = [
    '#0078d4' => 'Azul Windows',
    '#107c10' => 'Verde',
    '#5c2d91' => 'Morado',
    '#e81123' => 'Rojo',
    '#ff8c00' => 'Naranja',
    '#0099bc' => 'Cian',
    '#e3008c' => 'Rosa',
    '#8764b8' => 'Lila'
];

try {
    $db = Database::getConnection();
    
    // Obtener usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
  /*  // Determinar permisos según rol
    $esAdmin = ($usuario['rol_id'] == 1);
    $esVisualizador = ($usuario['rol_id'] == 2);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);
    $esProgramador = ($usuario['rol_id'] == 5);
    
    // Verificar permisos para usar el capturador
    if (!$esAdmin && !$esEditor && !$esSuper && !$esProgramador) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Acceso Denegado</title>
            <link rel="stylesheet" href="css/sweetalert2.min.css">
            <script src="js/sweetalert211.js"></script>
            <style>body { background: #1f1f1f; }</style>
        </head>
        <body>
            <script>
            Swal.fire({
                title: "Acceso Denegado",
                text: "No tienes permisos para acceder al Capturador de Facturas.",
                icon: "error",
                background: "#1f1f1f",
                color: "#fff",
                confirmButtonText: '<i class="fas fa-check"></i> Aceptar',
                confirmButtonColor: "#3085d6"
            }).then(() => { window.location.href = "dashboard.php"; });
            </script>
        </body>
        </html>
        <?php
        exit();
    }*/
    
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Obtener última factura para mostrar en header
    $sql_ultima_factura = "SELECT no_fact, fecha_emision FROM tbl_fact ORDER BY id DESC LIMIT 1";
    $stmt_ultima = $db->query($sql_ultima_factura);
    $ultima_factura = $stmt_ultima->fetch(PDO::FETCH_ASSOC);
    
   // Obtener fecha operativa desde configuración del sistema
$fecha_operativa_inicio = '';
$fecha_operativa_fin = '';
$mes_operativo = '';
$anio_operativo = '';

try {
    $sql_config = "SELECT fecha_inicio_operaciones FROM configuracion_sistema LIMIT 1";
    $stmt_config = $db->query($sql_config);
    $config_sistema = $stmt_config->fetch(PDO::FETCH_ASSOC);
    
    if ($config_sistema && $config_sistema['fecha_inicio_operaciones']) {
        $fecha_operativa = $config_sistema['fecha_inicio_operaciones'];
        $partes = explode('-', $fecha_operativa);
        
        if (count($partes) == 3) {
            $anio_operativo = $partes[0];
            $mes_operativo = $partes[1];
            $dia_operativo = $partes[2];
            
            // Fecha inicio = fecha operativa configurada
            $fecha_operativa_inicio = $fecha_operativa;
            
            // Fecha fin = último día del mes de la fecha operativa
            $ultimo_dia = date('t', strtotime($fecha_operativa));
            $fecha_operativa_fin = "{$anio_operativo}-{$mes_operativo}-{$ultimo_dia}";
        }
    }
} catch (PDOException $e) {
    error_log("Error al obtener fecha operativa: " . $e->getMessage());
}

// Obtener última factura (solo para mostrar, NO para la fecha operativa)
$ultima_factura = null;
try {
    $sql_ultima_factura = "SELECT no_fact, fecha_emision FROM tbl_fact ORDER BY id DESC LIMIT 1";
    $stmt_ultima = $db->query($sql_ultima_factura);
    $ultima_factura = $stmt_ultima->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener última factura: " . $e->getMessage());
}
    // Estadísticas para el sidebar
    $estadisticas = [];
    $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total);
    $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error en capturador: " . $e->getMessage());
    die("Error al cargar los datos necesarios");
}

// Meses en español
$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_es = $meses_completos[date('n') - 1];

// Datos financieros para sidebar
$finanzas = Database::getProgresoFinanciero();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capturador de Facturas - PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Chatbot -->
    <link rel="stylesheet" href="css/chatbot.css">
    <script src="js/chatbot.js"></script>

    <!-- LIBRERÍAS DE EXPORTACIÓN -->	
	<script src="js/jspdf.umd.min.js"></script>
	<script src="js/jspdf.plugin.autotable.min.js"></script>
	<script src="js/html2pdf.bundle.min.js"></script>
	<script src="js/xlsx.full.min.js"></script>
	
    <style>
        :root {
            --win-bg-primary: <?php echo $tema_actual['bg_primary']; ?>;
            --win-bg-secondary: <?php echo $tema_actual['bg_secondary']; ?>;
            --win-bg-tertiary: <?php echo $tema_actual['bg_tertiary']; ?>;
            --win-text-primary: <?php echo $tema_actual['text_primary']; ?>;
            --win-text-secondary: <?php echo $tema_actual['text_secondary']; ?>;
            --win-border-color: <?php echo $tema_actual['border_color']; ?>;
            --win-accent: <?php echo $tema_actual['accent_color']; ?>;
            --win-accent-light: <?php echo $tema_actual['accent_color']; ?>20;
            --win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            --win-radius: 8px;
            --win-radius-sm: 6px;
            --win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			--win-accent-rgb: <?php 
				$hex = ltrim($color_accent, '#');
				$r = hexdec(substr($hex, 0, 2));
				$g = hexdec(substr($hex, 2, 2));
				$b = hexdec(substr($hex, 4, 2));
				echo "$r, $g, $b";
			?>;
        }

        [data-theme="light"] { --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }
        [data-theme="dark"] ::placeholder { color: var(--win-text-secondary) !important; opacity: 0.7 !important; }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            transition: var(--win-transition);
            min-height: 100vh;
        }

        /* Navbar */
        .win-navbar {
            height: 48px;
            background: var(--win-bg-secondary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 0 16px;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .win-navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .win-navbar-brand i { color: var(--win-accent); }

        .win-nav-search {
            flex: 1;
            max-width: 400px;
            position: relative;
            margin-bottom: 5px;
            padding: 0 5px;
        }

        .win-nav-search input {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 8px 12px 8px 30px;
            font-size: 13px;
            width: 100%;
        }

        .win-nav-search input:focus {
            outline: none;
            border-color: var(--win-accent);
            box-shadow: 0 0 0 2px var(--win-accent-light);
        }

        .win-nav-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Sidebar */
        .win-sidebar {
            width: 260px;
            background: var(--win-bg-secondary);
            border-right: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            position: fixed;
            left: 0; top: 48px;
            z-index: 999;
            transition: var(--win-transition);
            overflow-y: auto;
            padding: 16px 0;
        }

        .win-sidebar.mini { width: 68px; }

        .win-sidebar-header {
            padding: 0 16px 16px;
            border-bottom: 1px solid var(--win-border-color);
            margin-bottom: 16px;
        }

        .win-sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: var(--win-radius-sm);
        }

        .win-sidebar-user:hover { background: var(--win-bg-tertiary); }

        .win-sidebar-user-avatar {
            width: 36px; height: 36px;
            background: var(--win-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .win-sidebar.mini .win-sidebar-user-info { display: none; }

        .win-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .win-nav-item { margin: 2px 8px; }

        .win-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--win-text-secondary);
            text-decoration: none;
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-size: 14px;
            position: relative;
        }

        .win-nav-link:hover {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
        }

        .win-nav-link.active {
            background: var(--win-accent-light);
            color: var(--win-accent);
            font-weight: 500;
        }

        .win-nav-link.active::before {
            content: '';
            position: absolute;
            left: 0; top: 4px; bottom: 4px;
            width: 3px;
            background: var(--win-accent);
            border-radius: 0 2px 2px 0;
        }

        .win-nav-icon { width: 20px; text-align: center; font-size: 16px; }
        .win-sidebar.mini .win-nav-text { display: none; }

        .win-nav-badge {
            margin-left: auto;
            background: var(--win-accent);
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        /* Main Content */
        .win-main-content {
            margin-left: 260px;
            margin-top: 48px;
            padding: 24px;
            transition: var(--win-transition);
            min-height: calc(100vh - 48px);
        }

        .win-main-content.sidebar-mini { margin-left: 68px; }

        /* Cards y Formularios */
        .win-main-content .card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            margin-bottom: 1.5rem;
        }

        .win-main-content .card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 1rem 1.25rem;
        }

        .win-main-content .card-body {
            padding: 1.25rem;
            color: var(--win-text-primary);
        }

        .win-main-content .form-control,
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }

        .win-main-content .form-control:focus,
        .win-main-content .form-select:focus {
            border-color: var(--win-accent);
            box-shadow: 0 0 0 0.25rem var(--win-accent-light);
        }

        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .table {
            color: var(--win-text-primary);
            border-color: var(--win-border-color);
        }

        .win-main-content .table th {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            font-weight: 600;
        }

        .win-main-content .table td {
            border-color: var(--win-border-color);
        }

        /* Panel de temas */
        .win-theme-panel {
            position: fixed;
            top: 48px; right: 0;
            width: 300px;
            background: var(--win-bg-secondary);
            border-left: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            z-index: 1001;
            transform: translateX(100%);
            transition: var(--win-transition);
            padding: 24px;
            overflow-y: auto;
        }

        .win-theme-panel.open { transform: translateX(0); }

        .win-theme-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .win-theme-overlay.open { display: block; }

        .win-theme-option {
            padding: 16px;
            border: 2px solid var(--win-border-color);
            border-radius: var(--win-radius);
            cursor: pointer;
            text-align: center;
            color: var(--win-text-primary);
        }

        .win-theme-option:hover { border-color: var(--win-accent); }
        .win-theme-option.active { border-color: var(--win-accent); background: var(--win-accent-light); }
        .win-theme-option[data-theme="dark"] { background: #0d0d0d; color: white; }
        .win-theme-option[data-theme="light"] { background: #f3f3f3; color: #000000; }

        .win-color-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .win-color-option {
            width: 40px; height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .win-color-option:hover { transform: scale(1.1); }
        .win-color-option.active { border-color: white; box-shadow: 0 0 0 2px var(--win-bg-secondary); }

        /* Dropdown */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }

        .dropdown-item {
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            margin: 2px 4px;
        }

        .dropdown-item:hover {
            background-color: var(--win-accent-light);
            color: var(--win-accent);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .win-sidebar { transform: translateX(-100%); }
            .win-sidebar.open { transform: translateX(0); }
            .win-main-content { margin-left: 0; padding: 16px; }
        }

        /* Estilos específicos del Capturador */
.header-info-box {
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius);
    padding: 5px 20px;
    margin-top: 8px;
    border: 1px solid var(--win-border-color);
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.header-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.header-info-divider {
    width: 1px;
    height: 24px;
    background: var(--win-border-color);
}

.header-info-item .btn {
    padding: 4px 5px;
    font-size: 13px;
}

.header-info-item .btn i {
    margin-right: 6px;
}

        .cliente-info-card {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            padding: 1rem;
            border: 1px solid var(--win-border-color);
            margin-top: 10px;
        }

        .cliente-info-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid var(--win-border-color);
        }

        .cliente-info-label { color: var(--win-text-secondary); font-size: 0.8rem; }
        .cliente-info-value { color: var(--win-text-primary); font-weight: 500; }

        .cantidad-control { display: flex; align-items: center; gap: 5px; justify-content: center; }
        .cantidad-input { width: 70px; text-align: center; }
        .cantidad-btn { width: 30px; height: 30px; padding: 0; }

        .total-general { color: var(--win-accent); font-size: 1.3rem; font-weight: 700; }

        .sql-preview {
            background: #1a1a2e !important;
            color: #f0f0f0 !important;
            font-family: 'Cascadia Code', 'Courier New', monospace;
            border-radius: var(--win-radius);
            padding: 20px;
            font-size: 14px;
            line-height: 1.6;
            border: 1px solid #5a5a7a !important;
        }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--win-text-secondary); }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.5; }

        .connection-status { position: fixed; top: 20px; right: 20px; z-index: 1000; }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .text-secondary-custom { color: var(--win-text-secondary) !important; }
		
/* ==================== CORRECCIÓN DE LEGIBILIDAD TEXT-MUTED ==================== */

/* Para el tema oscuro (por defecto) - Usamos un gris muy claro */
.text-muted, 
.win-main-content .text-muted,
.form-text,
small.text-muted {
    color: #e0e0e0 !important; /* Casi blanco (mucho más visible) */
    opacity: 0.9;
}

/* Para el tema claro - Usamos un gris oscuro */
[data-theme="light"] .text-muted,
[data-theme="light"] .win-main-content .text-muted,
[data-theme="light"] .form-text,
[data-theme="light"] small.text-muted {
    color: #555555 !important; /* Gris oscuro fuerte */
    opacity: 1;
}
/* Eliminar la flecha por defecto de Bootstrap y usar una personalizada */
select.form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23ffffff' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 12px !important;
    padding-right: 2rem !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
}

/* Eliminar la flecha duplicada de Bootstrap */
select.form-select::-ms-expand {
    display: none;
}

/* Flechas en modo claro */
[data-theme="light"] select.form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23333333' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
}

/* Select deshabilitado (Estado) */
select.form-select:disabled {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23888888' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
    opacity: 0.8;
    cursor: not-allowed;
}

/* Input Date - Calendario */
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    cursor: pointer;
}

[data-theme="light"] input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0);
}
/* Ocultar el indicador nativo del datalist */
input[list]::-webkit-calendar-picker-indicator {
    display: none !important;
    opacity: 0;
}

/* Flecha personalizada para el input de cliente */
input[list] {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23ffffff' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 14px !important;
    padding-right: 2rem !important;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

/* Flecha en modo claro */
[data-theme="light"] input[list] {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23333333' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
}

/* Cuando el input tiene focus, cambiar color de la flecha */
input[list]:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%230078d4' d='M2 5l6 6 6-6'/%3E%3C/svg%3E") !important;
}
/* Contenedor del input de cliente con botón X */
.cliente-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.cliente-input-wrapper .form-control {
    padding-right: 60px !important;
}

.btn-clear-cliente {
    position: absolute;
    right: 30px;  /* Cambiado de 8px a 30px para dejar espacio a la flecha */
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--win-text-secondary);
    cursor: pointer;
    padding: 5px 6px;
    font-size: 12px;
    border-radius: 50%;
    transition: all 0.2s ease;
    display: none;
    z-index: 5;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-clear-cliente:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}

.btn-clear-cliente.visible {
    display: flex !important;
}

[data-theme="light"] .btn-clear-cliente:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #000000;
}

/* Ajustar la posición de la flecha del datalist */
input[list] {
    background-position: right 0.75rem center !important;
    padding-right: 2rem !important;
}
/* Grid responsivo para facturas */
#facturas-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 1200px) {
    #facturas-container {
        grid-template-columns: 1fr;
    }
}

#facturas-container .card {
    margin-bottom: 0 !important;
    height: fit-content;
}
/* SQL Header Box - Estilo profesional */
.sql-header-box {
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius) var(--win-radius) 0 0;
    padding: 12px 20px;
    border: 1px solid var(--win-border-color);
    border-bottom: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.sql-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.sql-header-left i {
    font-size: 18px;
}

.sql-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--win-text-primary);
}

.sql-warning-badge {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.sql-warning-badge i {
    font-size: 12px;
    color: #ffc107;
}

.sql-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sql-header-actions .btn {
    padding: 6px 14px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sql-header-actions .btn i {
    font-size: 13px;
}

/* Ajustar el textarea SQL para que conecte con el header */
.sql-preview {
    border-radius: 0 0 var(--win-radius) var(--win-radius) !important;
    margin-top: 0 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .sql-header-box {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .sql-header-left {
        width: 100%;
    }
    
    .sql-header-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
/* ==================== BARRA DE ACCIONES - ESTILO HEADER INFO BOX ==================== */
.action-bar-box {
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius);
    padding: 10px 20px;
    margin-bottom: 24px;
    border: 1px solid var(--win-border-color);
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.action-bar-divider {
    width: 1px;
    height: 32px;
    background: var(--win-border-color);
}

/* Botones de la barra de acciones */
.action-bar-box button {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--win-radius-sm);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
	 position: relative;
}
.action-bar-box button::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-8px);
    background: #1e1e2d;
    color: #e1e1e6;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: normal;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.action-bar-box button::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-2px);
    border-width: 5px;
    border-style: solid;
    border-color: #1e1e2d transparent transparent transparent;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 10000;
}

.action-bar-box button:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(-12px);
}

.action-bar-box button:hover::before {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(-6px);
}

/* Tema claro */
[data-theme="light"] .action-bar-box button::after {
    background: #ffffff;
    color: #1e1e2d;
    border: 1px solid #e5e5e5;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

[data-theme="light"] .action-bar-box button::before {
    border-color: #ffffff transparent transparent transparent;
}
.action-bar-box button i {
    font-size: 14px;
    transition: transform 0.15s ease;
}

.action-bar-box button:hover i {
    transform: scale(1.08);
}

.action-bar-box button:active {
    transform: translateY(1px);
}

/* Botón Nueva Factura - Verde */
.action-btn-new {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #047857 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}

.action-btn-new:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35);
    color: white;
}

/* Botón Generar SQL - Azul */
.action-btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-color: #1d4ed8 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.25);
}

.action-btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.35);
    color: white;
}

/* Botón Guardar Progreso - Ámbar */
.action-btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-color: #b45309 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
}

.action-btn-warning:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
    color: white;
}

/* Botón Cargar Progreso - Cian */
.action-btn-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    border-color: #0e7490 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(6, 182, 212, 0.25);
}

.action-btn-info:hover {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
    box-shadow: 0 4px 10px rgba(6, 182, 212, 0.35);
    color: white;
}

/* Botón Limpiar Todo - Rojo */
.action-btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: #b91c1c !important;
    color: white;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
}

.action-btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.35);
    color: white;
}

/* Modo claro - ajustes de sombras */
[data-theme="light"] .action-btn-new {
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
}

[data-theme="light"] .action-btn-primary {
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.15);
}

[data-theme="light"] .action-btn-warning {
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
}

[data-theme="light"] .action-btn-info {
    box-shadow: 0 2px 6px rgba(6, 182, 212, 0.15);
}

[data-theme="light"] .action-btn-danger {
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
}

/* Responsive */
@media (max-width: 992px) {
    .action-bar-box {
        gap: 6px;
    }
    
    .action-bar-box button {
        padding: 8px 12px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .action-bar-divider {
        display: none;
    }
    
    .action-bar-box button {
        flex: 1 1 calc(50% - 4px);
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .action-bar-box button {
        flex: 1 1 100%;
    }
    
    .action-bar-box button span {
        display: inline;
    }
}
/* Botón Recargar - Gris/Púrpura */
.action-btn-secondary {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-color: #6d28d9 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(139, 92, 246, 0.25);
}

.action-btn-secondary:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 4px 10px rgba(139, 92, 246, 0.35);
    color: white;
}

[data-theme="light"] .action-btn-secondary {
    box-shadow: 0 2px 6px rgba(139, 92, 246, 0.15);
}
/* ==================== CHEVRON PARA EXPANDIR/COLAPSAR FACTURAS ==================== */
.factura-card {
    position: relative;
}

.card-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--win-border-color);
    background: var(--win-bg-tertiary);
    cursor: pointer;
    user-select: none;
    transition: background 0.2s ease;
}

.card-header-custom:hover {
    background: var(--win-bg-secondary);
}

.card-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.chevron-icon {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--win-text-secondary);
    font-size: 14px;
    width: 20px;
    text-align: center;
}

.chevron-icon.expanded {
    transform: rotate(90deg);
}

.factura-card .card-body {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.factura-card.collapsed .card-body {
    display: none;
}

.factura-card.collapsed .card-footer-custom {
    display: none;
}

.factura-card .card-footer-custom {
    padding: 16px 20px;
    border-top: 1px solid var(--win-border-color);
    background: var(--win-bg-tertiary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

/* Botón Expandir/Colapsar Todas */
.action-btn-toggle-all {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    border-color: #374151 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(107, 114, 128, 0.25);
}

.action-btn-toggle-all:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    box-shadow: 0 4px 10px rgba(107, 114, 128, 0.35);
    color: white;
}

[data-theme="light"] .action-btn-toggle-all {
    box-shadow: 0 2px 6px rgba(107, 114, 128, 0.15);
}

/* Estado colapsado */
.factura-card.collapsed .card-header-custom {
    border-bottom: none;
}
/* Footer de la tarjeta de factura */
.factura-card .card-footer-custom {
    padding: 12px 20px;
    border-top: 1px solid var(--win-border-color);
    background: var(--win-bg-tertiary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.factura-card.collapsed .card-footer-custom {
    display: none;
}

.factura-card .total-general {
    color: var(--win-accent);
    font-size: 1.2rem;
    font-weight: 700;
}
/* Botón Imprimir - Azul oscuro */
.action-btn-print {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    border-color: #3730a3 !important;
    color: white;
    box-shadow: 0 2px 6px rgba(30, 58, 95, 0.25);
}

.action-btn-print:hover {
    background: linear-gradient(135deg, #0d2137 0%, #0a1a2b 100%);
    box-shadow: 0 4px 10px rgba(30, 58, 95, 0.35);
    color: white;
}

[data-theme="light"] .action-btn-print {
    box-shadow: 0 2px 6px rgba(30, 58, 95, 0.15);
}
/* ==================== CSS COMPLETO PARA TABLA DE SERVICIOS - MODO DARK ==================== */

/* Fondo general de la tabla */
#servicioModal .modal-content,
#servicioModal .modal-body,
#serviciosModalBody,
#serviciosModalBody table,
#serviciosModalBody tbody {
    background-color: #3a3a3a !important;
}

/* HEADER DE LA TABLA - TITULOS DE COLUMNAS */
#servicioModal .table thead {
    background-color: #1a1a1a !important;
}

#servicioModal .table thead tr {
    background-color: #1a1a1a !important;
}

#servicioModal .table thead th {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
    font-weight: 600;
    padding: 12px 8px;
    border-bottom: 2px solid #0078d4;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 13px;
}

/* Filas del cuerpo - colores alternados */
#serviciosModalBody tr {
    background-color: #3a3a3a !important;
}

#serviciosModalBody tr:nth-child(even) {
    background-color: #4a4a4a !important;
}

#serviciosModalBody tr:nth-child(odd) {
    background-color: #3a3a3a !important;
}

/* Borde entre filas */
#serviciosModalBody tr {
    border-bottom: 1px solid #555555;
}

/* Celdas del cuerpo */
#serviciosModalBody td {
    background-color: transparent !important;
    color: #e0e0e0 !important;
    padding: 10px 8px;
}

/* Hover sobre las filas */
#serviciosModalBody tr:hover {
    background-color: #0078d4 !important;
    cursor: pointer;
}

#serviciosModalBody tr:hover td {
    color: #ffffff !important;
}

#serviciosModalBody tr:hover td * {
    color: #ffffff !important;
}

#serviciosModalBody tr:hover .badge {
    background-color: #ffffff !important;
    color: #0078d4 !important;
}

/* Filas seleccionadas */
#serviciosModalBody tr.selected-row {
    background-color: #0a5c2e !important;
    border-left: 4px solid #00ff88;
}

#serviciosModalBody tr.selected-row td {
    color: #ffffff !important;
}

#serviciosModalBody tr.selected-row td * {
    color: #ffffff !important;
}

#serviciosModalBody tr.selected-row .badge {
    background-color: #00ff88 !important;
    color: #0a5c2e !important;
}

/* Estilo para los badges (código del servicio) */
#serviciosModalBody .badge {
    background-color: #0078d4 !important;
    color: #ffffff !important;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
}

/* Estilo para la categoría */
#serviciosModalBody td:nth-child(4) small {
    color: #bbbbbb !important;
    background-color: #2d2d2d;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-block;
}

/* Precios */
#serviciosModalBody td:last-child {
    color: #00ff88 !important;
    font-weight: bold;
    font-size: 13px;
}

/* Checkbox */
#serviciosModalBody input[type="checkbox"] {
    accent-color: #0078d4;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Scrollbar */
#servicioModal .modal-body {
    scrollbar-width: thin;
    scrollbar-color: #0078d4 #3a3a3a;
}

#servicioModal .modal-body::-webkit-scrollbar {
    width: 8px;
}

#servicioModal .modal-body::-webkit-scrollbar-track {
    background: #3a3a3a;
    border-radius: 10px;
}

#servicioModal .modal-body::-webkit-scrollbar-thumb {
    background: #0078d4;
    border-radius: 10px;
}

/* Mensaje cuando no hay servicios */
#serviciosModalBody .text-center.py-4 {
    color: #bbbbbb !important;
    background-color: #3a3a3a !important;
}
#serviciosModalBody input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background-color: #3a3a3a;
    border: 2px solid #0078d4;
    border-radius: 4px;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}

#serviciosModalBody input[type="checkbox"]:checked {
    background-color: #f39c12;
    border-color: #f39c12;
}

#serviciosModalBody input[type="checkbox"]:checked::after {
    content: "✓";
    position: absolute;
    color: white;
    font-size: 12px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

#serviciosModalBody input[type="checkbox"]:hover {
    transform: scale(1.1);
}
/* ==================== QUICK ACTIONS - ESTILO WINDOWS 11 ==================== */
.win-quick-actions {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

.win-quick-actions-expanded {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.8);
    transition: all 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
}

.win-quick-actions-expanded.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Animación escalonada para los botones secundarios */
.win-quick-actions-expanded .win-quick-action {
    opacity: 0;
    transform: translateX(20px) scale(0.5);
    transition: all 0.25s cubic-bezier(0.34, 1.2, 0.64, 1);
}

.win-quick-actions-expanded.show .win-quick-action {
    opacity: 1;
    transform: translateX(0) scale(1);
}

/* Retrasos escalonados para cada botón */
.win-quick-actions-expanded.show .win-quick-action:nth-child(1) { transition-delay: 0.00s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(2) { transition-delay: 0.03s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(3) { transition-delay: 0.06s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(4) { transition-delay: 0.09s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(5) { transition-delay: 0.12s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(6) { transition-delay: 0.15s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(7) { transition-delay: 0.18s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(8) { transition-delay: 0.21s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(9) { transition-delay: 0.24s; }
.win-quick-actions-expanded.show .win-quick-action:nth-child(10) { transition-delay: 0.27s; }

/* Animación al cerrar - salida escalonada */
.win-quick-actions-expanded:not(.show) .win-quick-action {
    opacity: 0;
    transform: translateX(-20px) scale(0.5);
    transition: all 0.2s cubic-bezier(0.55, 0, 1, 0.45);
}

.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(1) { transition-delay: 0.24s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(2) { transition-delay: 0.21s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(3) { transition-delay: 0.18s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(4) { transition-delay: 0.15s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(5) { transition-delay: 0.12s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(6) { transition-delay: 0.09s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(7) { transition-delay: 0.06s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(8) { transition-delay: 0.03s; }
.win-quick-actions-expanded:not(.show) .win-quick-action:nth-child(9) { transition-delay: 0.00s; }

.win-quick-action {
    width: 52px;
    height: 52px;
    border-radius: 26px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    position: relative;
}

/* Tooltip solo si tiene data-tooltip */
.win-quick-action[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    right: 68px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--win-bg-secondary);
    color: var(--win-text-primary);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 10000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--win-border-color);
}

.win-quick-action[data-tooltip]:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateY(-50%) translateX(-4px);
}

/* Botón principal - NUEVO COLOR (morado/gradiente) */
#mainQuickAction {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

#mainQuickAction:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
}

#mainQuickAction:active {
    transform: scale(0.96);
}

/* Botones de acciones rápidas - mismos colores que action bar */
.quick-action-new {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.quick-action-new:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.quick-action-facturas {
    position: relative;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
}

.quick-action-facturas:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
}

.quick-action-sql {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.quick-action-sql:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

.quick-action-save {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.quick-action-save:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}

.quick-action-load {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
}

.quick-action-load:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
}

.quick-action-expand-all {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
}

.quick-action-expand-all:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
}

.quick-action-go-start {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
}

.quick-action-go-start:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

.quick-action-go-end {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: white;
}

.quick-action-go-end:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
}

.quick-action-clear {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.quick-action-clear:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

.quick-action-print {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: white;
}

.quick-action-print:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
}

/* Badge contador de facturas */
.facturas-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid var(--win-bg-secondary);
    font-family: monospace;
    transition: all 0.2s ease;
}

/* Tema claro */
[data-theme="light"] .win-quick-action[data-tooltip]::after {
    background: #ffffff;
    color: #1e1e2d;
    border: 1px solid #e5e5e5;
}

[data-theme="light"] #mainQuickAction {
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.2);
}

/* Responsive - ocultar tooltips en móviles */
@media (max-width: 576px) {
    .win-quick-actions {
        bottom: 16px;
        right: 16px;
    }
    
    .win-quick-action {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    
    .win-quick-action[data-tooltip]::after {
        display: none;
    }
}
.factura-card.highlight {
    animation: highlight-pulse 0.6s ease-in-out 2;
}
/* ==================== EFECTO COMPLETO - BORDE + SOMBRA + ESCALA ==================== */
.factura-card {
    position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

/* Efecto de elevación al hover */
.factura-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
}

/* Borde superior animado */
.factura-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, 
        transparent,
        var(--win-accent),
        var(--win-accent-light),
        var(--win-accent),
        transparent
    );
    transform: translateX(-100%);
    transition: transform 0.5s ease;
    z-index: 2;
    border-radius: var(--win-radius) var(--win-radius) 0 0;
}

.factura-card:hover::before {
    transform: translateX(0);
}

/* Borde inferior animado */
.factura-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent,
        var(--win-accent),
        var(--win-accent),
        transparent
    );
    transform: translateX(100%);
    transition: transform 0.5s ease 0.1s;
    z-index: 2;
}

.factura-card:hover::after {
    transform: translateX(0);
}

/* Efecto de brillo en el borde izquierdo */
.factura-card .card-header-custom {
    position: relative;
    overflow: hidden;
}

.factura-card .card-header-custom::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--win-accent);
    transform: scaleY(0);
    transition: transform 0.3s ease 0.15s;
}

.factura-card:hover .card-header-custom::before {
    transform: scaleY(1);
}

/* Efecto de brillo suave en toda la tarjeta */
@keyframes soft-glow {
    0% {
        box-shadow: 0 0 0 0 var(--win-accent-light);
    }
    100% {
        box-shadow: 0 0 0 3px var(--win-accent-light);
    }
}

.factura-card:active {
    transform: translateY(-2px);
    animation: soft-glow 0.3s ease;
}

/* Tema claro - ajustes */
[data-theme="light"] .factura-card:hover {
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

[data-theme="light"] .factura-card:active {
    box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.2);
}
/* Cursor pointer en toda la card */
.factura-card {
    cursor: pointer;
}

/* Pero los botones dentro mantienen su cursor */
.factura-card button,
.factura-card .btn,
.factura-card input,
.factura-card select,
.factura-card textarea {
    cursor: default;
}

.factura-card button:hover,
.factura-card .btn:hover {
    cursor: pointer;
}
/* ==================== QUICK ACTIONS CON DROPDOWN DE FACTURAS ==================== */
.win-quick-actions {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

/* Botón con badge contador */
.quick-action-facturas {
    position: relative;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
}

.quick-action-facturas:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
}

/* Badge contador de facturas */
.facturas-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid var(--win-bg-secondary);
    font-family: monospace;
    transition: all 0.2s ease;
}

/* Dropdown de facturas */
.facturas-dropdown {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 320px;
    max-height: 400px;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px) scale(0.95);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1060;
    overflow: hidden;
}

.facturas-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.facturas-dropdown-header {
    padding: 12px 16px;
    background: var(--win-bg-tertiary);
    border-bottom: 1px solid var(--win-border-color);
    font-weight: 600;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.facturas-dropdown-header i {
    color: var(--win-accent);
}

.facturas-dropdown-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--win-border-color);
}

.facturas-dropdown-search input {
    width: 100%;
    padding: 8px 12px;
    background: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius-sm);
    color: var(--win-text-primary);
    font-size: 12px;
}

.facturas-dropdown-search input:focus {
    outline: none;
    border-color: var(--win-accent);
}

.facturas-dropdown-list {
    max-height: 280px;
    overflow-y: auto;
    padding: 8px;
}

/* Estilos para el botón eliminar en dropdown */
.facturas-dropdown-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    margin: 4px 0;
    border-radius: var(--win-radius-sm);
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.facturas-dropdown-item-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.factura-item-info {
    flex: 1;
}

.factura-item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-eliminar-dropdown {
    background: transparent;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
    font-size: 12px;
    opacity: 0.6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-eliminar-dropdown:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    opacity: 1;
    transform: scale(1.1);
}

.btn-eliminar-dropdown:active {
    transform: scale(0.95);
}

.facturas-dropdown-item:hover .btn-eliminar-dropdown {
    opacity: 1;
}

/* Separador entre items */
.facturas-dropdown-divider {
    height: 1px;
    background: var(--win-border-color);
    margin: 4px 0;
}

.facturas-dropdown-item:hover {
    background: var(--win-bg-tertiary);
    border-color: var(--win-border-color);
    transform: translateX(4px);
}

.facturas-dropdown-item.selected {
    background: var(--win-accent-light);
    border-left: 3px solid var(--win-accent);
}

.factura-item-info {
    flex: 1;
}

.factura-item-numero {
    font-weight: 600;
    font-size: 13px;
    color: var(--win-text-primary);
}

.factura-item-cliente {
    font-size: 11px;
    color: var(--win-text-secondary);
    margin-top: 2px;
}

.factura-item-total {
    font-size: 12px;
    font-weight: 600;
    color: var(--win-accent);
}

.facturas-dropdown-footer {
    padding: -80px 12px;
    border-top: 1px solid var(--win-border-color);
    font-size: 11px;
    color: var(--win-text-secondary);
    text-align: center;
}

.facturas-dropdown-empty {
    text-align: center;
    padding: 30px 20px;
    color: var(--win-text-secondary);
}

/* ==================== DROPDOWN DE FACTURAS EN HEADER ==================== */
.header-facturas-dropdown {
    position: relative;
}

.header-facturas-btn {
    background: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    color: var(--win-text-primary);
    padding: 5px 12px;
    border-radius: var(--win-radius-sm);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.header-facturas-btn:hover {
    background: var(--win-accent-light);
    border-color: var(--win-accent);
}

.header-facturas-btn .badge {
    background: var(--win-accent);
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
}

.header-facturas-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 360px;
    max-height: 450px;
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.header-facturas-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.header-facturas-header {
    padding: 12px 16px;
    background: var(--win-bg-tertiary);
    border-bottom: 1px solid var(--win-border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-facturas-header h6 {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--win-text-primary);
}

.header-facturas-header h6 i {
    color: var(--win-accent);
    margin-right: 8px;
}

.header-facturas-header span {
    font-size: 11px;
    color: var(--win-text-secondary);
}

.header-facturas-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--win-border-color);
}

.header-facturas-search input {
    width: 100%;
    padding: 8px 12px;
    background: var(--win-bg-tertiary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius-sm);
    color: var(--win-text-primary);
    font-size: 12px;
}

.header-facturas-search input:focus {
    outline: none;
    border-color: var(--win-accent);
    box-shadow: 0 0 0 2px var(--win-accent-light);
}

.header-facturas-search input::placeholder {
    color: var(--win-text-secondary);
    font-size: 12px;
}

.header-facturas-list {
    max-height: 320px;
    overflow-y: auto;
    padding: 8px;
}

.header-facturas-list::-webkit-scrollbar {
    width: 6px;
}

.header-facturas-list::-webkit-scrollbar-track {
    background: var(--win-bg-tertiary);
    border-radius: 3px;
}

.header-facturas-list::-webkit-scrollbar-thumb {
    background: var(--win-accent);
    border-radius: 3px;
}

.header-facturas-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    margin: 4px 0;
    border-radius: var(--win-radius-sm);
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.header-facturas-item:hover {
    background: var(--win-bg-tertiary);
    border-color: var(--win-border-color);
}

.header-facturas-item-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.header-factura-info {
    flex: 1;
}

.header-factura-numero {
    font-weight: 600;
    font-size: 13px;
    color: var(--win-text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.header-factura-cliente {
    font-size: 11px;
    color: var(--win-text-secondary);
    margin-top: 2px;
}

.header-factura-total {
    font-size: 12px;
    font-weight: 600;
    color: var(--win-accent);
    margin-left: 12px;
}

.header-factura-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}

.header-btn-eliminar {
    background: transparent;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
    font-size: 11px;
    opacity: 0.5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-btn-eliminar:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    opacity: 1;
    transform: scale(1.1);
}

.header-facturas-item:hover .header-btn-eliminar {
    opacity: 1;
}

.header-facturas-footer {
    padding: 10px 12px;
    border-top: 1px solid var(--win-border-color);
    font-size: 11px;
    color: var(--win-text-secondary);
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-facturas-footer a {
    color: var(--win-accent);
    text-decoration: none;
    font-size: 11px;
}

.header-facturas-footer a:hover {
    text-decoration: underline;
}

.header-facturas-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--win-text-secondary);
}

.header-facturas-empty i {
    font-size: 32px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.header-facturas-empty p {
    margin-bottom: 5px;
    font-size: 13px;
}

.header-facturas-empty small {
    font-size: 11px;
}

/* Responsive */
@media (max-width: 768px) {
    .header-facturas-dropdown-menu {
        width: 320px;
        right: -20px;
    }
}

@media (max-width: 480px) {
    .header-facturas-dropdown-menu {
        width: 280px;
        right: -30px;
    }
    
    .header-facturas-btn span:not(.badge) {
        display: none;
    }
}
/* Botones adicionales para Quick Action */
.quick-action-expand-all {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
}

.quick-action-expand-all:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
}

.quick-action-go-start {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
}

.quick-action-go-start:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);
}

.quick-action-go-end {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: white;
}

.quick-action-go-end:hover {
    transform: scale(1.08);
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
}
/* Animación suave para el icono */
#mainQuickAction i {
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.win-quick-actions-expanded.show ~ #mainQuickAction i {
    transform: rotate(90deg);
}
/* ==================== ESTILOS FORZADOS PARA MODAL DE CLIENTES ==================== */

/* Fuerza el estilo de las filas de la tabla de clientes */
#selectorClienteModal .table tbody tr,
#clientesModalBody tr,
.modal-content .table tbody tr {
    transition: all 0.2s ease-in-out !important;
}

/* Color de texto normal para todas las filas */
#selectorClienteModal .table tbody tr td,
#clientesModalBody tr td,
#selectorClienteModal .table tbody tr td *,
#clientesModalBody tr td * {
    color: var(--win-text-primary) !important;
}

/* HOVER - Color al pasar el mouse */
#selectorClienteModal .table tbody tr:hover,
#clientesModalBody tr:hover {
    background-color: #2a5f8a !important;
    cursor: pointer !important;
    transform: scale(1.01) !important;
}

/* Texto en hover - fuerza color blanco */
#selectorClienteModal .table tbody tr:hover td,
#clientesModalBody tr:hover td,
#selectorClienteModal .table tbody tr:hover td *,
#clientesModalBody tr:hover td * {
    color: #ffffff !important;
}

/* TEMA CLARO - hover */
[data-theme="light"] #selectorClienteModal .table tbody tr:hover,
[data-theme="light"] #clientesModalBody tr:hover {
    background-color: #0078d4 !important;
}

[data-theme="light"] #selectorClienteModal .table tbody tr:hover td,
[data-theme="light"] #clientesModalBody tr:hover td,
[data-theme="light"] #selectorClienteModal .table tbody tr:hover td *,
[data-theme="light"] #clientesModalBody tr:hover td * {
    color: #ffffff !important;
}

/* FILA SELECCIONADA - Color diferente al hover */
#selectorClienteModal .table tbody tr.selected-row,
#clientesModalBody tr.selected-row {
    background-color: #0a5c2e !important;
    border-left: 4px solid #00ff88 !important;
}

/* Texto en fila seleccionada - blanco */
#selectorClienteModal .table tbody tr.selected-row td,
#clientesModalBody tr.selected-row td,
#selectorClienteModal .table tbody tr.selected-row td *,
#clientesModalBody tr.selected-row td * {
    color: #ffffff !important;
}

/* TEMA CLARO - fila seleccionada */
[data-theme="light"] #selectorClienteModal .table tbody tr.selected-row,
[data-theme="light"] #clientesModalBody tr.selected-row {
    background-color: #a5d6a7 !important;
    border-left: 4px solid #2e7d32 !important;
}

/* TEMA CLARO - texto en fila seleccionada */
[data-theme="light"] #selectorClienteModal .table tbody tr.selected-row td,
[data-theme="light"] #clientesModalBody tr.selected-row td,
[data-theme="light"] #selectorClienteModal .table tbody tr.selected-row td *,
[data-theme="light"] #clientesModalBody tr.selected-row td * {
    color: #1a3d2a !important;
}

/* Badges dentro de la tabla - mantener visibles */
#selectorClienteModal .table tbody tr td .badge,
#clientesModalBody tr td .badge {
    transition: all 0.2s ease;
}

/* Badges en hover */
#selectorClienteModal .table tbody tr:hover td .badge,
#clientesModalBody tr:hover td .badge {
    transform: scale(1.05);
}

/* Forzar que las celdas hereden el fondo */
#selectorClienteModal .table tbody tr td,
#clientesModalBody tr td {
    background: transparent !important;
}
/* Mover el badge de conexión a una posición más discreta */
.connection-status {
    position: fixed;
    bottom: 20px;
    left: 20px;
    top: auto !important;
    right: auto !important;
    z-index: 1050;
}

/* Estilo más compacto para el badge */
.connection-status .badge {
    padding: 5px 12px !important;
    font-size: 11px;
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

.connection-status .badge:hover {
    opacity: 1;
}

/* Responsive para móviles */
@media (max-width: 768px) {
    .connection-status {
        bottom: 10px;
        left: 10px;
    }
    .connection-status .badge {
        padding: 3px 8px !important;
        font-size: 9px;
    }
}
/* Animación de parpadeo para el header de factura */
@keyframes flashHeader {
    0% { background-color: rgba(var(--win-accent-rgb), 0.1); box-shadow: none; }
    25% { background-color: var(--win-accent); color: white; box-shadow: 0 0 15px var(--win-accent); }
    75% { background-color: var(--win-accent); color: white; box-shadow: 0 0 15px var(--win-accent); }
    100% { background-color: rgba(var(--win-accent-rgb), 0.1); box-shadow: none; }
}

.card-header-custom.flash {
    animation: flashHeader 0.8s ease-in-out 5 !important;
    border-radius: 6px;
}
    </style>
</head>
<body>
    <!-- Theme Overlay -->
    <div class="win-theme-overlay" id="themeOverlay" onclick="cerrarPanelTemas()"></div>
    
    <!-- Theme Panel -->
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        <div class="win-theme-options mb-4">
            <div class="win-theme-option mb-2 <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark">
                <i class="fas fa-moon mb-2"></i><div>Oscuro</div>
            </div>
            <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light">
                <i class="fas fa-sun mb-2"></i><div>Claro</div>
            </div>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>" 
                     style="background-color: <?php echo $color; ?>;" data-color="<?php echo $color; ?>" 
                     title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">Sidebar compacto</label>
        </div>
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()">
            <i class="fas fa-save"></i> Guardar cambios
        </button>
    </div>

    <!-- Navbar -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">CAPTURADOR DE FACTURAS - SISFACT PDL Visiones</span>
        </div>
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        <div style="flex: 1;"></div>
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
        <?= renderNotificationsDropdown() ?>
        
        <!-- Perfil de usuario -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="d-none d-md-inline" style="color: var(--win-text-primary);">
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-chevron-down ms-1 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px;">
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center">
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0" style="color: var(--win-text-primary); font-size: 14px;">
                                <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 12px;">
                                <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?>
                            </small>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Dashboard</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Panel principal</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="perfil.php">
                        <i class="fas fa-user me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php">
                        <i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Configuración</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small>
                        </div>
                    </a>
                </li>
                
                <li><hr class="dropdown-divider my-1"></li>
<li>
    <a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php">
        <i class="fas fa-lock me-3" style="width: 20px;"></i>
        <div>
            <span class="d-block fw-bold">Bloquear Sesión</span>
            <small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small>
        </div>
    </a>
</li>
<li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Cerrar Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small>
                        </div>
                    </a>
                </li>
                
                <?php $ultimo_acceso_texto = obtenerUltimoAcceso($_SESSION['usuario_id'] ?? 0);?>
				<li class="dropdown-footer px-3 py-2 mt-1">
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-shield-alt me-1"></i> Sesión segura
					</small>
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-clock me-1"></i>Último acceso: 
						<span class="fw-bold text-info"><?php echo $ultimo_acceso_texto; ?></span>
					</small>
				</li>
            </ul>
        </div>
    </nav>


    <!-- Sidebar -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
            <div class="win-sidebar-header">
                <div class="win-sidebar-user">
                    <div class="win-sidebar-user-avatar">
                        <?php if (!empty($usuario['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="win-sidebar-user-info">
                        <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h6>
                        <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Usuario'); ?></small>
                    </div>
                </div>
            </div>
        </a>
        <ul class="win-nav">
            <li class="win-nav-item">
                <a href="dashboard.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tachometer-alt"></i>
                    <span class="win-nav-text">Dashboard</span>
                    <span class="win-nav-badge" title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>">
                        F/Cierre: <?php echo date('t'); ?>/<?php echo substr($mes_actual_es, 0, 3); ?>
                    </span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="capturador_facturas.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-plus-circle"></i>
                    <span class="win-nav-text">Capturador</span>
                    <span class="win-nav-badge">Nuevo</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $total_categorias; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $total_servicios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users-cog"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $total_usuarios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="reportes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-chart-bar"></i>
                    <span class="win-nav-text">Reportes</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="rentabilidad.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-trend-up"></i>
                    <span class="win-nav-text">Rentabilidad y Costos</span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">CONFIGURACIÓN</small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
                    <span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="configuracion.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-cog"></i>
                    <span class="win-nav-text">Configuración</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="historico_view.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-history"></i>
                    <span class="win-nav-text">Histórico</span>
                    <span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span>
                </a>
            </li>
        </ul>
        
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y'); ?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;"><?php echo $finanzas['mensaje']; ?></small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($finanzas['porcentaje'], 1); ?>%</h5>
            </div>
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary);">
                <div class="progress-bar" role="progressbar" 
                     style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;"><strong>$<?php echo number_format($finanzas['real'], 2); ?></strong></small>
                    <small style="font-size: 12px; color: var(--win-text-secondary);"><i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas</small>
                </div>
                <small class="text-end text-success" style="font-size: 12px;">Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?></small>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
	<!-- Opción 1: Funciona -->
        <!-- Connection Status -->
        <div class="connection-status">
            <div id="status-badge" class="badge bg-warning" style="padding: 8px 15px;">
                <i class="fas fa-sync-alt fa-spin me-1"></i> Conectando...
            </div>
        </div>
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <h4>Cargando datos...</h4>
            </div>
        </div>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-2">
            <i class="fas fa-file-invoice me-3" style="color: var(--win-accent);"></i>
            Capturador de Facturas
        </h1>
        <div class="header-info-box">
            <!-- Última factura -->
            <div class="header-info-item">
                <i class="fas fa-file-invoice" style="color: var(--win-accent);"></i>
                <span id="info-ultima-factura">
                    <?php if ($ultima_factura): ?>
                        Última Fac: <strong class="fw-bold text-warning"><?php echo $ultima_factura['no_fact']; ?></strong>
                    <?php else: ?>
                        Sin facturas
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="header-info-divider"></div>
            
            <!-- Período operativo -->
            <div class="header-info-item">
                <i class="fas fa-calendar-alt" style="color: var(--win-accent);"></i>
                <span id="fecha-operaciones-texto">
                    <?php if ($fecha_operativa_inicio && $fecha_operativa_fin): ?>
                        <!---<strong class="fw-bold text-warning">
                            <?php echo date('d/m/Y', strtotime($fecha_operativa_inicio)); ?> - 
                            <?php echo date('d/m/Y', strtotime($fecha_operativa_fin)); ?>
                        </strong>--->
                        <span class="text-warning fw-bold">(<?php echo $meses_completos[intval($mes_operativo)-1] . ' / ' . $anio_operativo; ?>)</span>
                    <?php else: ?>
                        <span class="text-warning fw-bold">⚠️ Sin período configurado</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="header-info-divider"></div>
            
            <!-- Contador de facturas -->
            <div class="header-info-item">
                <i class="fas fa-layer-group" style="color: var(--win-accent);"></i>
                <span>
                    <strong class="fw-bold text-info" id="contador-facturas">0</strong> 
                    <span class="text-muted">Factura(s)</span>
                </span>
            </div>
            
            <div class="header-info-divider"></div>
            
            <!-- Importe Total de Facturas -->
            <div class="header-info-item">
                <i class="fas fa-dollar-sign" style="color: var(--win-accent);"></i>
                <span>
                    <strong class="fw-bold text-success" id="importe-total-facturas">$0.00</strong>
                    <span class="text-muted">Total</span>
                </span>
            </div>
            
<div class="header-info-divider"></div>

<!-- Dropdown de Facturas en Header -->
<div class="header-info-item header-facturas-dropdown">
    <button class="header-facturas-btn" id="headerFacturasBtn" onclick="toggleHeaderFacturasDropdown()">
        <i class="fas fa-list-ul"></i>
        <span>Facturas</span>
        <span class="badge" id="headerFacturasBadge">0</span>
        <i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>
    </button>
    
    <!-- Menú dropdown -->
    <div class="header-facturas-dropdown-menu" id="headerFacturasDropdown">
        <div class="header-facturas-header">
            <h6><i class="fas fa-file-invoice"></i> Mis Facturas</h6>
            <span id="headerDropdownCount">0 facturas</span>
        </div>
        <div class="header-facturas-search">
            <input type="text" id="headerSearchFactura" placeholder="🔍 Buscar por # o cliente..." onkeyup="filtrarHeaderFacturas()">
        </div>
        <div class="header-facturas-list" id="headerFacturasList">
            <div class="header-facturas-empty">
                <i class="fas fa-file-invoice"></i>
                <p>No hay facturas creadas</p>
                <small>Haz clic en "Nueva" para comenzar a crear facturas</small>
            </div>
        </div>
<div class="header-facturas-footer">
    <span><i class="fas fa-mouse-pointer me-1"></i> Click para ir</span>
    <a href="#" onclick="event.preventDefault(); abrirTodasFacturas();">
        <i class="fas fa-expand-alt me-1"></i> Expandir todas
    </a>
</div>
    </div>
</div>

<div class="header-info-divider"></div>

            <!-- Dropdown de Opciones -->
            <div class="header-info-item">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownOpciones" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-1"></i> Opciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownOpciones">
                        <li>
                            <a class="dropdown-item" href="#" onclick="imprimirFacturas(); return false;">
                                <i class="fas fa-print me-2" style="color: #6c757d;"></i> Imprimir
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <h6 class="dropdown-header text-muted">
                                <i class="fas fa-download me-1"></i> Exportar
                            </h6>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportarPDF(); return false;">
                                <i class="fas fa-file-pdf me-1" style="color: #dc3545;"></i> PDF
                            </a>
                        </li>
						<li>
							<a class="dropdown-item" href="#" onclick="exportarWORD(); return false;">
								<i class="fas fa-file-word me-1" style="color: #2b5797;"></i> Word
							</a>
						</li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportarXLS(); return false;">
                                <i class="fas fa-file-excel me-2" style="color: #28a745;"></i> XLS / Excel
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportarCSV(); return false;">
                                <i class="fas fa-file-csv me-1" style="color: #17a2b8;"></i> CSV
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportarTXT(); return false;">
                                <i class="fas fa-file-alt me-2" style="color: #fd7e14;"></i> TXT
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barra de Acciones - Estilo Header Info Box -->
<div class="action-bar-box">
    <button class="action-btn-new" onclick="agregarFactura()" data-tooltip="Crear una nueva factura">
        <i class="fas fa-plus-circle"></i>
        <span>Nueva</span>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-primary" onclick="generarSQL()" data-tooltip="Generar script SQL para importar">
        <i class="fas fa-code"></i>
        <span>Generar</span>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-warning" onclick="guardarProgreso()" data-tooltip="Guardar progreso en archivo JSON">
        <i class="fas fa-save"></i>
        <span>Guardar</span>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <!-- Dropdown Cargar con dos opciones: JSON y SQL -->
    <div class="dropdown d-inline-block">
        <button class="action-btn-info dropdown-toggle" type="button" id="btnCargarDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-tooltip="Cargar facturas desde archivo">
            <i class="fas fa-folder-open"></i>
            <span>Cargar</span>
            <i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="btnCargarDropdown" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
            <li>
                <a class="dropdown-item" href="#" onclick="cargarProgreso(); return false;">
                    <i class="fas fa-file-code me-2" style="color: #f59e0b;"></i>
                    <span>Cargar JSON</span>
                    <small class="text-muted d-block ms-4">Archivo de respaldo (.json)</small>
                </a>
            </li>
            <li><hr class="dropdown-divider m-1"></li>
            <li>
                <a class="dropdown-item" href="#" onclick="cargarSQL(); return false;">
                    <i class="fas fa-database me-2" style="color: #3b82f6;"></i>
                    <span>Cargar SQL</span>
                    <small class="text-muted d-block ms-4">Importar desde script SQL (.sql)</small>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-toggle-all" id="btnToggleAll" onclick="toggleAllFacturas()" data-tooltip="Expandir o colapsar todas las facturas">
        <i class="fas fa-chevron-down" id="toggleAllIcon"></i>
        <span id="toggleAllText">Expandir</span>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-danger" onclick="limpiarTodo()" data-tooltip="Eliminar todas las facturas y limpiar el SQL">
        <i class="fas fa-trash-alt"></i>
        <span>Limpiar Todo</span>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-secondary" onclick="recargarDatos()" data-tooltip="Recargar datos maestros del sistema">
        <i class="fas fa-sync-alt"></i>
    </button>
    
    <div class="action-bar-divider"></div>
    
    <button class="action-btn-print" onclick="imprimirFacturas()" data-tooltip="Imprimir todas las facturas">
        <i class="fas fa-print"></i>
    </button>
</div>
		
		<!-- Contenedor de Facturas -->
        <div id="facturas-container">
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <h4>No hay facturas</h4>
                <p class="text-muted">Haz clic en "Nueva" para comenzar a crear Facturas</p>
            </div>
        </div>

<!-- Vista previa SQL -->
<div class="mt-5">
    <!-- Header de SQL integrado -->
    <div class="sql-header-box">
        <div class="sql-header-left">
            <i class="fas fa-terminal" style="color: var(--win-accent);"></i>
            <span class="sql-title">Vista Previa SQL</span>
            <span class="sql-warning-badge">
                <i class="fas fa-info-circle"></i>
                Siempre que haga cambios, genere nuevamente el archivo para importar
            </span>
        </div>
        <div class="sql-header-actions">
            <button class="btn btn-sm btn-outline-secondary" onclick="limpiarSQL()" title="Limpiar SQL">
                <i class="fas fa-broom"></i> Limpiar
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="copiarSQL()" title="Copiar al portapapeles">
                <i class="fas fa-copy"></i> Copiar
            </button>
            <button class="btn btn-sm btn-success" onclick="descargarSQL()" title="Descargar archivo SQL">
                <i class="fas fa-download"></i> Descargar
            </button>
        </div>
    </div>
    
    <!-- Área de texto SQL -->
    <textarea id="sql-preview" class="form-control sql-preview" rows="15" readonly 
              placeholder="El SQL generado aparecerá aquí..."></textarea>
</div>
	
	</main>

<!-- Quick Actions - Con dropdown de facturas -->
<div class="win-quick-actions">
    <!-- Acciones expandidas -->
    <div class="win-quick-actions-expanded" id="quickActionsExpanded">
        <button class="win-quick-action quick-action-new" onclick="agregarFactura()" data-tooltip="Nueva Factura">
            <i class="fas fa-plus-circle"></i>
        </button>
        
        <!-- Botón de facturas con dropdown -->
        <div style="position: relative;">
            <button class="win-quick-action quick-action-facturas" id="btnFacturasDropdown" onclick="toggleFacturasDropdown()" data-tooltip="Ver Facturas">
                <i class="fas fa-list-ul"></i>
                <span class="facturas-badge" id="facturasBadge">0</span>
            </button>
            
            <!-- Dropdown de facturas -->
            <div class="facturas-dropdown" id="facturasDropdown">
                <div class="facturas-dropdown-header">
                    <span><i class="fas fa-file-invoice me-2"></i>Mis Facturas</span>
                    <span id="dropdownFacturasCount" class="text-muted">0 facturas</span>
                </div>
                <div class="facturas-dropdown-search">
                    <input type="text" id="searchFacturaDropdown" placeholder="🔍 Buscar por # o cliente..." onkeyup="filtrarDropdownFacturas()">
                </div>
                <div class="facturas-dropdown-list" id="facturasDropdownList">
                    <div class="facturas-dropdown-empty">
                        <i class="fas fa-file-invoice"></i>
                        <p>No hay facturas creadas</p>
                        <small>Haz clic en + para crear una</small>
                    </div>
                </div>
                <div class="facturas-dropdown-footer">
                    <i class="fas fa-mouse-pointer me-1"></i> Haz clic en la factura para ir a ella &nbsp;|&nbsp;
                    <!---<i class="fas fa-trash-alt me-1 text-danger"></i> <span class="text-danger">Icono rojo para eliminar</span>--->
                </div>
            </div>
        </div>
        
        <button class="win-quick-action quick-action-sql" onclick="generarSQL()" data-tooltip="Generar SQL">
            <i class="fas fa-code"></i>
        </button>
        
        <button class="win-quick-action quick-action-save" onclick="guardarProgreso()" data-tooltip="Guardar Progreso">
            <i class="fas fa-save"></i>
        </button>
        
        <button class="win-quick-action quick-action-load" onclick="cargarProgreso()" data-tooltip="Cargar Progreso">
            <i class="fas fa-folder-open"></i>
        </button>
        
        <!-- BOTÓN EXPANDIR/COLAPSAR TODAS -->
        <button class="win-quick-action quick-action-expand-all" id="quickActionToggleAll" onclick="toggleAllFacturasQuick()" data-tooltip="Expandir/Colapsar todas">
            <i class="fas fa-chevron-down" id="quickActionToggleIcon"></i>
        </button>
        
        <!-- BOTÓN IR AL INICIO -->
        <button class="win-quick-action quick-action-go-start" onclick="irAlInicio()" data-tooltip="Ir al inicio">
            <i class="fas fa-arrow-up"></i>
        </button>
        
        <!-- BOTÓN IR AL FINAL -->
        <button class="win-quick-action quick-action-go-end" onclick="irAlFinal()" data-tooltip="Ir al final">
            <i class="fas fa-arrow-down"></i>
        </button>
        
        <button class="win-quick-action quick-action-clear" onclick="limpiarTodo()" data-tooltip="Limpiar Todo">
            <i class="fas fa-trash-alt"></i>
        </button>
        
        <button class="win-quick-action quick-action-print" onclick="imprimirFacturas()" data-tooltip="Imprimir">
            <i class="fas fa-print"></i>
        </button>
    </div>
    
    <!-- Botón principal -->
	<button class="win-quick-action" id="mainQuickAction" onclick="toggleQuickActions()" title="Acciones rápidas">
		<i class="fas fa-ellipsis-h" id="mainQuickActionIcon"></i>  <!-- ← Agrega este ID -->
	</button>
</div>
	
    <!-- Modal de Servicios -->
    <div class="modal fade" id="servicioModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
                <div class="modal-header" style="background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color);">
                    <h6 class="modal-title" style="color: var(--win-text-primary);">
                        <i class="fas fa-list-ul me-2" style="color: var(--win-accent);"></i>
                        Seleccionar Servicios
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <select class="form-select" id="selectCategoria" onchange="filtrarServiciosModal()" style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">
                                <option value="">Todas las categorías</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <input type="text" class="form-control" id="searchServicio" 
                                   placeholder="Buscar por código o descripción..." 
                                   onkeyup="filtrarServiciosModal()"
                                   style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted"><span id="numSeleccionados">0</span> servicios seleccionados</small>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="seleccionarTodosModal()">
                                <i class="fas fa-check-double me-1"></i>Seleccionar todos
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deseleccionarTodosModal()">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover" style="color: var(--win-text-primary);">
                            <thead style="position: sticky; top: 0; background: var(--win-bg-tertiary);">
                                <tr>
                                    <th width="40"><input type="checkbox" id="checkAllModal" onchange="toggleTodosModal()"></th>
                                    <th width="100">Código</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th width="100" class="text-end">Precio</th>
                                </tr>
                            </thead>
                            <tbody id="serviciosModalBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="background: var(--win-bg-tertiary); border-top: 1px solid var(--win-border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="agregarServiciosSeleccionados()" id="btnAgregarServicios">
                        <i class="fas fa-plus me-1"></i>Agregar (<span id="btnAgregarCount">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Datalist para clientes -->
    <datalist id="clientes-datalist"></datalist>

    <!-- Scripts -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // ============================================
        // VARIABLES PHP A JAVASCRIPT
        // ============================================
        const fechaOperativaInicio = '<?php echo $fecha_operativa_inicio; ?>';
		const fechaOperativaFin = '<?php echo $fecha_operativa_fin; ?>';
		const fechaOperativaAnio = '<?php echo $anio_operativo; ?>';
		const fechaOperativaMes = '<?php echo $mes_operativo; ?>';
        
        // ============================================
        // CONFIGURACIÓN
        // ============================================
        const API_URL = 'api/api_datos_locales.php';
        
        const swalDark = Swal.mixin({
            background: '#1e1e2d',
            color: '#e1e1e6',
            confirmButtonColor: '#0078d4',
            cancelButtonColor: '#6c757d',
            denyButtonColor: '#dc3545'
        });
        
        let datosMaestros = { clientes: [], tiposPago: [], categorias: [], servicios: [] };
        let facturasData = [];
        let facturaCounter = 0;
        let facturaActualParaServicios = null;
        let serviciosSeleccionadosModal = new Set();
        
        const TIPO_PAGO_TRANSFERENCIA_ID = 20;
        
        // ============================================
        // INICIALIZACIÓN
        // ============================================
        $(document).ready(function() { inicializar(); });
        
        async function inicializar() {
            showLoading(true);
            try {
                await cargarDatosMaestros();
                cargarFacturasLocal();
                actualizarDatalistClientes();
                renderizarFacturas();
                actualizarContador();
                actualizarEstadoConexion(true);
                
            } catch (error) {
                actualizarEstadoConexion(false);
                swalDark.fire({
                    icon: 'error',
                    title: '<i class="fas fa-triangle-exclamation me-2"></i>Error',
                    text: 'No se pudo conectar a la base de datos',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                });
            } finally {
                showLoading(false);
            }
        }
        
        async function cargarDatosMaestros() {
            const response = await fetch(`${API_URL}?action=todos`);
            const result = await response.json();
            if (result.success) {
                datosMaestros = result.data;
                cargarCategoriasEnSelect();
                localStorage.setItem('datos_maestros_cache', JSON.stringify(datosMaestros));
            } else {
                throw new Error(result.message);
            }
        }
        
        function cargarCategoriasEnSelect() {
            const select = document.getElementById('selectCategoria');
            select.innerHTML = '<option value="">Todas las categorías</option>';
            datosMaestros.categorias.forEach(cat => {
                select.innerHTML += `<option value="${cat.id}">${cat.codigo} - ${cat.descripcion}</option>`;
            });
        }
        
        function actualizarDatalistClientes() {
            const datalist = document.getElementById('clientes-datalist');
            datalist.innerHTML = '';
            datosMaestros.clientes.forEach(c => {
                const option = document.createElement('option');
                option.value = `${c.codigo || ''} - ${c.nombre || ''} ${c.ContratoNo ? '[' + c.ContratoNo + ']' : ''}`;
                option.setAttribute('data-id', c.id);
                option.setAttribute('data-codigo', c.codigo || '');
                option.setAttribute('data-nombre', c.nombre || '');
                option.setAttribute('data-contrato', c.ContratoNo || '');
                option.setAttribute('data-telefono', c.telefono || '');
                option.setAttribute('data-email', c.email || '');
                option.setAttribute('data-activo', c.activo || 0);
                option.setAttribute('data-fechavence', c.fechaVence || '');
                option.setAttribute('data-fecharegistro', c.fechaRegistro || '');
                option.setAttribute('data-vigenciapor', c.vigenciapor || '');
                option.setAttribute('data-renovac', c.renovac || 0);
                option.setAttribute('data-fechafinalcontrato', c.fechafinalcontrato || '');
                option.setAttribute('data-responsable', c.ResponsableEntidad || '');
                datalist.appendChild(option);
            });
        }
        
        // ============================================
        // GESTIÓN DE FACTURAS
        // ============================================
		
// ============================================
// NUEVA FUNCIÓN: Agregar Factura con selección de cliente y opción de elegir factura existente
// ============================================

function agregarFactura() {
    // Mostrar modal de selección de cliente primero
    mostrarSelectorClientes((clienteSeleccionado) => {
        if (!clienteSeleccionado) return; // Usuario canceló
        
        // Obtener fecha actual (día de PC, mes/año operativo)
        let fechaActual = obtenerFechaPorDefecto();
        if (!fechaOperativaInicio) {
            fechaActual = new Date().toISOString().slice(0, 10);
        }
        
        // Buscar TODAS las facturas existentes para este cliente
        const facturasExistentes = facturasData.filter(factura => {
            return factura.cliente_id == clienteSeleccionado.id;
        });
        
        
        if (facturasExistentes.length > 0) {
            // Existen facturas para este cliente
            mostrarSelectorFacturasExistentes(facturasExistentes, clienteSeleccionado, fechaActual);
        } else {
            // No existe ninguna factura para este cliente, crear nueva
            crearNuevaFacturaConCliente(clienteSeleccionado, fechaActual);
        }
    });
}

// ============================================
// MODAL PARA SELECCIONAR FACTURA EXISTENTE
// ============================================
function mostrarSelectorFacturasExistentes(facturasExistentes, cliente, fechaActual) {
    // Ordenar facturas por ID (más reciente primero)
    const facturasOrdenadas = [...facturasExistentes].sort((a, b) => b.id - a.id);
    
    // Generar HTML de las facturas
    let facturasHtml = '';
    facturasOrdenadas.forEach(factura => {
        const fechaFormateada = factura.fecha_emision.split('-').reverse().join('/');
        const totalFormateado = formatearMoneda(factura.total_general);
        const serviciosCount = factura.detalles.length;
        const tieneServicios = serviciosCount > 0;
        
        facturasHtml += `
            <div class="factura-existente-item" onclick="seleccionarFacturaExistente(${factura.id}, '${fechaActual}')" style="
                background: var(--win-bg-tertiary);
                border: 1px solid var(--win-border-color);
                border-radius: var(--win-radius);
                padding: 15px;
                margin-bottom: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
            ">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold" style="color: var(--win-accent); font-size: 1.1rem;">
                            <i class="fas fa-file-invoice me-2"></i>Factura #${factura.id}
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-calendar-alt me-1"></i>${fechaFormateada}
                            ${tieneServicios ? `<span class="ms-3"><i class="fas fa-list me-1"></i>${serviciosCount} servicio(s)</span>` : '<span class="ms-3 text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Sin servicios</span>'}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold" style="color: var(--win-accent);">${totalFormateado}</div>
                        <div class="small text-muted">
                            ${factura.estado === 'PENDIENTE' ? '<span class="badge bg-warning text-dark">PENDIENTE</span>' : '<span class="badge bg-success">PAGADA</span>'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    const modalHtml = `
        <div class="modal fade" id="selectorFacturaModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
                    <div class="modal-header" style="background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color);">
                        <h6 class="modal-title" style="color: var(--win-text-primary);">
                            <i class="fas fa-file-invoice-dollar me-2" style="color: var(--win-accent);"></i>
                            Facturas existentes de <strong>${escapeHtml(cliente.nombre)}</strong>
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            El cliente <strong class="text-warning">${escapeHtml(cliente.nombre)}</strong> tiene <strong>${facturasExistentes.length}</strong> factura(s) registrada(s).
                            ¿Qué deseas hacer?
                        </p>
                        <div class="mb-4">
                            <div class="fw-bold mb-2 text-warning">
                                <i class="fas fa-list me-1"></i>Facturas existentes: <br><small class="text-success">(Seleccione una de las existentes o cree una nueva)</small>
                            </div>
                            ${facturasHtml}
                        </div>
                        <div class="alert alert-info py-3 mb-0" style="background: #1e3a5f; border: 1px solid #3b82f6;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-plus-circle me-2" style="color: #10b981;"></i>
                                    <strong class="fw-bold text-warning">¿Crear una nueva factura?</strong>
                                </div>
                                <button class="btn btn-sm btn-success" onclick="crearNuevaFacturaDesdeModal(${cliente.id}, ${Date.now()})">
                                    <i class="fas fa-file-invoice me-1"></i>Crear nueva factura
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: var(--win-bg-tertiary); border-top: 1px solid var(--win-border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente si hay
    const existingModal = document.getElementById('selectorFacturaModal');
    if (existingModal) existingModal.remove();
    
    // Agregar al DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modalElement = document.getElementById('selectorFacturaModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Guardar datos temporales
    window._clienteTemporal = cliente;
    window._fechaActualTemporal = fechaActual;
    
    // Mostrar modal
    modal.show();
    
    // Limpiar al cerrar
    modalElement.addEventListener('hidden.bs.modal', () => {
        modalElement.remove();
        delete window._clienteTemporal;
        delete window._fechaActualTemporal;
    });
}

// ============================================
// SELECCIONAR FACTURA EXISTENTE
// ============================================
function seleccionarFacturaExistente(facturaId, fechaActual) {
    // Cerrar modal
    const modalElement = document.getElementById('selectorFacturaModal');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
    }
    
    // Ir a la factura existente
    swalDark.fire({
        icon: 'info',
        title: '<i class="fas fa-arrow-right me-2"></i>Redirigiendo',
        html: `Serás redirigido a la <strong>Factura #${facturaId}</strong> para continuar editando.`,
        toast: true,
        position: 'top-end',
        timer: 2000,
        showConfirmButton: false
    });
    
    setTimeout(() => {
        if (typeof window.irAFactura === 'function') {
            window.irAFactura(facturaId);
        } else if (typeof irAFactura === 'function') {
            irAFactura(facturaId);
        }
    }, 200);
}

// ============================================
// CREAR NUEVA FACTURA DESDE EL MODAL
// ============================================
function crearNuevaFacturaDesdeModal(clienteId, timestamp) {
    // Cerrar modal
    const modalElement = document.getElementById('selectorFacturaModal');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
    }
    
    // Obtener cliente de la variable temporal
    const cliente = window._clienteTemporal;
    const fechaActual = window._fechaActualTemporal;
    
    if (cliente) {
        crearNuevaFacturaConCliente(cliente, fechaActual);
    } else {
        swalDark.fire({
            icon: 'error',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Error',
            text: 'No se pudo crear la factura',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
    }
}


// Función para crear la factura con el cliente ya asignado
function crearNuevaFacturaConCliente(cliente, fecha) {
    facturaCounter++;
    const nuevaFacturaId = facturaCounter;
    
    facturasData.push({
        id: nuevaFacturaId,
        cliente_id: cliente.id,
        cliente_info: {
            id: cliente.id,
            codigo: cliente.codigo,
            nombre: cliente.nombre,
            ContratoNo: cliente.ContratoNo,
            telefono: cliente.telefono,
            email: cliente.email,
            activo: cliente.activo,
            fechaVence: cliente.fechaVence,
            fechaRegistro: cliente.fechaRegistro,
            vigenciapor: cliente.vigenciapor,
            renovac: cliente.renovac,
            fechafinalcontrato: cliente.fechafinalcontrato,
            ResponsableEntidad: cliente.ResponsableEntidad
        },
        tipo_pago_id: TIPO_PAGO_TRANSFERENCIA_ID,
        tipo_pago_info: datosMaestros.tiposPago.find(t => t.id == TIPO_PAGO_TRANSFERENCIA_ID) || null,
        subtotal: 0, 
        total_general: 0,
        estado: 'PENDIENTE',
        fecha_emision: fecha,
        observaciones: '',
        detalles: []
    });
    
    renderizarFacturas();
    actualizarContador();
    guardarEnLocalStorage();
    
    // Notificación
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-invoice me-2"></i> Factura Creada',
        html: `
            <div style="font-size: 0.95rem;">
                <strong>Factura #${nuevaFacturaId}</strong> creada para<br>
                <strong>${escapeHtml(cliente.nombre || 'Cliente')}</strong><br>
                Fecha: ${fecha.split('-').reverse().join('/')}
            </div>
        `,
        toast: true,
        position: 'top-end',
        timer: 4000,
        showConfirmButton: false,
        background: '#1e2a3e',
        color: '#e2e8f0',
        iconColor: '#10b981'
    });
    
    // Scroll a la nueva factura
    setTimeout(() => {
        const nuevaCard = document.getElementById(`factura-card-${nuevaFacturaId}`);
        if (nuevaCard) {
            nuevaCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            nuevaCard.style.boxShadow = '0 0 0 3px var(--win-accent), 0 4px 20px rgba(0,0,0,0.2)';
            setTimeout(() => { nuevaCard.style.boxShadow = ''; }, 2000);
        }
    }, 100);
    
    actualizarBadgeFacturas();
    if (dropdownOpen) actualizarDropdownFacturas();
}

// ============================================
// MODAL MEJORADO PARA SELECCIONAR CLIENTE
// ============================================

let clienteSeleccionadoTemp = null;

function mostrarSelectorClientes(callback) {
    // Resetear selección temporal
    clienteSeleccionadoTemp = null;
    
    // Crear modal dinámico mejorado
    const modalHtml = `
        <div class="modal fade" id="selectorClienteModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-scrollable modal-xl">
                <div class="modal-content" style="background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius);">
                    <div class="modal-header" style="background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); border-radius: var(--win-radius) var(--win-radius) 0 0;">
                        <h6 class="modal-title" style="color: var(--win-text-primary);">
                            <i class="fas fa-user-plus me-2" style="color: var(--win-accent);"></i>
                            Seleccionar Cliente para la Nueva Factura
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <!-- Barra de búsqueda y filtros mejorada -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="background: var(--win-bg-tertiary); border-color: var(--win-border-color); color: var(--win-text-secondary);">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchClienteModal" 
                                           placeholder="Buscar por nombre, código o contrato..." 
                                           autocomplete="off"
                                           style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border-color: var(--win-border-color);">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="filtroEstadoCliente" style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border-color: var(--win-border-color);">
                                    <option value="todos">📋 Todos los clientes</option>
                                    <option value="activos">✅ Solo activos</option>
                                    <option value="inactivos">❌ Solo inactivos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-secondary w-100" id="btnLimpiarSeleccion" type="button" style="display: none;">
                                    <i class="fas fa-times me-1"></i>Limpiar selección
                                </button>
                            </div>
                        </div>
                        
                        <!-- Cliente seleccionado actualmente - Mejorado -->
                        <div id="clienteSeleccionadoInfo" class="alert mb-4" style="display: none; background: var(--win-accent-light); border: 2px solid var(--win-accent); border-radius: var(--win-radius);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <i class="fas fa-check-circle me-2" style="color: #10b981;"></i>
                                    <strong style="color: var(--win-accent);">Cliente seleccionado:</strong>
                                    <span class="fw-bold" id="clienteSeleccionadoNombre" style="color: var(--win-text-primary);"></span>
                                    <span class="badge ms-2" id="clienteSeleccionadoCodigo" style="background: var(--win-accent);"></span>
                                </div>
                                <button type="button" class="btn btn-sm" id="btnCambiarSeleccion" style="background: var(--win-bg-tertiary); color: var(--win-text-primary); border: 1px solid var(--win-border-color);">
                                    <i class="fas fa-exchange-alt me-1"></i>Cambiar
                                </button>
                            </div>
                        </div>
                        
                        <!-- Tabla de clientes mejorada -->
                        <div class="table-responsive" style="max-height: 450px; border-radius: var(--win-radius);">
                            <table class="table table-hover cliente-selector-table" style="color: var(--win-text-primary); margin-bottom: 0;">
                                <thead style="position: sticky; top: 0; background: var(--win-bg-tertiary); z-index: 10;">
                                    <tr style="border-bottom: 2px solid var(--win-accent);">
                                        <th style="width: 8%; text-align: center;">Acción</th>
                                        <th style="width: 12%;">Código</th>
                                        <th style="width: 35%;">Cliente</th>
                                        <th style="width: 15%;">Contrato</th>
                                        <th style="width: 12%;">Estado</th>
                                        <th style="width: 18%;">Vencimiento</th>
                                    </tr>
                                </thead>
                                <tbody id="clientesModalBody"></tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Mostrando <span id="clientesMostradosCount">0</span> clientes
                                <span class="mx-2">•</span>
                                <i class="fas fa-mouse-pointer me-1"></i> Haz clic en "Seleccionar" o doble clic en la fila
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: var(--win-bg-tertiary); border-top: 1px solid var(--win-border-color); border-radius: 0 0 var(--win-radius) var(--win-radius);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="btnAceptarCliente" disabled style="background: var(--win-accent); border-color: var(--win-accent);">
                            <i class="fas fa-check-circle me-1"></i>Seleccionar Cliente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente si hay
    const existingModal = document.getElementById('selectorClienteModal');
    if (existingModal) existingModal.remove();
    
    // Agregar al DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Agregar estilos CSS adicionales para la tabla
    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        /* Estilos para la tabla de selección de clientes */
        .cliente-selector-table tbody tr {
            cursor: pointer;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .cliente-selector-table tbody tr:hover {
            background: var(--win-accent-light) !important;
            transform: translateX(4px);
        }
        
        .cliente-selector-table tbody tr.selected-row {
            background: var(--win-accent-light) !important;
            border-left: 3px solid var(--win-accent);
        }
        
        .cliente-selector-table tbody tr.selected-row td:first-child {
            position: relative;
        }
        
        .cliente-selector-table tbody tr.selected-row td:first-child::before {
            content: '✓';
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--win-accent);
            font-weight: bold;
        }
        
        .btn-seleccionar-cliente {
            transition: all 0.2s ease;
        }
        
        .btn-seleccionar-cliente:hover {
            transform: scale(1.05);
        }
        
        /* Scrollbar personalizada */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: var(--win-bg-tertiary);
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--win-accent);
            border-radius: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--win-accent-light);
        }
    `;
    document.head.appendChild(styleSheet);
    
    const modalElement = document.getElementById('selectorClienteModal');
    const modal = new bootstrap.Modal(modalElement);
    const searchInput = document.getElementById('searchClienteModal');
    const filtroEstado = document.getElementById('filtroEstadoCliente');
    const tbody = document.getElementById('clientesModalBody');
    const contadorSpan = document.getElementById('clientesMostradosCount');
    const btnAceptar = document.getElementById('btnAceptarCliente');
    const btnLimpiarSeleccion = document.getElementById('btnLimpiarSeleccion');
    const clienteSeleccionadoDiv = document.getElementById('clienteSeleccionadoInfo');
    const clienteSeleccionadoNombre = document.getElementById('clienteSeleccionadoNombre');
    const clienteSeleccionadoCodigo = document.getElementById('clienteSeleccionadoCodigo');
    const btnCambiarSeleccion = document.getElementById('btnCambiarSeleccion');
    
    // Función para actualizar la UI de selección
    function actualizarUISeleccion() {
        if (clienteSeleccionadoTemp) {
            clienteSeleccionadoNombre.textContent = clienteSeleccionadoTemp.nombre || 'Sin nombre';
            clienteSeleccionadoCodigo.textContent = clienteSeleccionadoTemp.codigo || '--';
            clienteSeleccionadoDiv.style.display = 'block';
            btnAceptar.disabled = false;
            if (btnLimpiarSeleccion) btnLimpiarSeleccion.style.display = 'block';
            btnLimpiarSeleccion.innerHTML = '<i class="fas fa-times me-1"></i>Limpiar selección';
            
            // Resaltar la fila seleccionada en la tabla
            document.querySelectorAll('#clientesModalBody tr').forEach(row => {
                const rowClienteId = row.getAttribute('data-cliente-id');
                if (rowClienteId == clienteSeleccionadoTemp.id) {
                    row.classList.add('selected-row');
                } else {
                    row.classList.remove('selected-row');
                }
            });
        } else {
            clienteSeleccionadoDiv.style.display = 'none';
            btnAceptar.disabled = true;
            if (btnLimpiarSeleccion) btnLimpiarSeleccion.style.display = 'none';
            
            // Limpiar resaltados
            document.querySelectorAll('#clientesModalBody tr').forEach(row => {
                row.classList.remove('selected-row');
            });
        }
    }
    
    // Función para seleccionar un cliente
    function seleccionarCliente(cliente) {
        clienteSeleccionadoTemp = cliente;
        actualizarUISeleccion();
        
        // Feedback visual
        const btn = document.querySelector(`.btn-seleccionar-cliente[data-cliente-id="${cliente.id}"]`);
        if (btn) {
            btn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                if (btn) btn.style.transform = '';
            }, 150);
        }
    }
    
    // Función para limpiar selección
    function limpiarSeleccion() {
        clienteSeleccionadoTemp = null;
        actualizarUISeleccion();
    }
    
    // Función para renderizar clientes mejorada
    function renderizarClientes() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const estadoFiltro = filtroEstado ? filtroEstado.value : 'todos';
        
        let clientesFiltrados = datosMaestros.clientes.filter(c => {
            const texto = `${c.codigo || ''} ${c.nombre || ''} ${c.ContratoNo || ''} ${c.ResponsableEntidad || ''}`.toLowerCase();
            if (searchTerm && !texto.includes(searchTerm)) return false;
            if (estadoFiltro === 'activos' && c.activo != 1) return false;
            if (estadoFiltro === 'inactivos' && c.activo == 1) return false;
            return true;
        });
        
        clientesFiltrados.sort((a, b) => {
            if (a.activo === b.activo) {
                return (a.nombre || '').localeCompare(b.nombre || '');
            }
            return a.activo === 1 ? -1 : 1;
        });
        
        if (contadorSpan) contadorSpan.textContent = clientesFiltrados.length;
        
        if (clientesFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5" style="color: var(--win-text-secondary);">
                        <i class="fas fa-search fa-3x mb-3 d-block opacity-50"></i>
                        No se encontraron clientes
                        <div class="small mt-2">Prueba con otro término de búsqueda</div>
                    </td>
                </tr>
            `;
            return;
        }
        
        function getDiasRestantes(fechaVence) {
            if (!fechaVence) return null;
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const vence = new Date(fechaVence);
            vence.setHours(0, 0, 0, 0);
            const diffTime = vence - hoy;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        }
        
        const isSelected = (clienteId) => clienteSeleccionadoTemp && clienteSeleccionadoTemp.id === clienteId;
        
        tbody.innerHTML = clientesFiltrados.map(c => {
            const estadoCliente = c.activo == 1;
            const diasRestantes = getDiasRestantes(c.fechaVence);
            let venceBadge = '';
            
            if (c.fechaVence) {
                if (diasRestantes < 0) {
                    venceBadge = '<span class="badge bg-danger" title="Contrato vencido"><i class="fas fa-skull-crosswalk me-1"></i>Vencido</span>';
                } else if (diasRestantes <= 30) {
                    venceBadge = `<span class="badge bg-warning text-dark" title="Vence en ${diasRestantes} días"><i class="fas fa-hourglass-half me-1"></i>${diasRestantes} d</span>`;
                } else {
                    const fechaFormateada = c.fechaVence.split('-').reverse().join('/');
                    venceBadge = `<span class="badge bg-secondary" title="Vence: ${fechaFormateada}"><i class="fas fa-calendar me-1"></i>${fechaFormateada}</span>`;
                }
            } else {
                venceBadge = '<span class="badge bg-secondary">Sin fecha</span>';
            }
            
            const estadoBadge = estadoCliente 
                ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Activo</span>'
                : '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Inactivo</span>';
            
            const selectedClass = isSelected(c.id) ? 'selected-row' : '';
            
            return `
                <tr data-cliente-id="${c.id}" class="${selectedClass}" ondblclick="seleccionarClienteDirecto(${c.id})">
                    <td class="text-center">
                        <button class="btn btn-sm btn-seleccionar-cliente" data-cliente-id="${c.id}" data-cliente='${escapeHtml(JSON.stringify(c))}' style="background: var(--win-accent); border-color: var(--win-accent); padding: 4px 12px;">
                            <i class="fas fa-check me-1"></i>
                        </button>
                    </td>
                    <td><span class="badge" style="background: var(--win-accent);">${escapeHtml(c.codigo || '--')}</span></td>
                    <td>
                        <strong>${escapeHtml(c.nombre || 'Sin nombre')}</strong>
                        ${c.ResponsableEntidad ? `<div class="small" style="color: var(--win-text-secondary);"><i class="fas fa-user-tie me-1"></i>${escapeHtml(c.ResponsableEntidad)}</div>` : ''}
                    </td>
                    <td><small>${escapeHtml(c.ContratoNo || 'S/C')}</small></td>
                    <td>${estadoBadge}</td>
                    <td>${venceBadge}</td>
                </tr>
            `;
        }).join('');
        
        // Agregar event listeners a los botones "Seleccionar"
        document.querySelectorAll('.btn-seleccionar-cliente').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const clienteData = JSON.parse(btn.getAttribute('data-cliente'));
                seleccionarCliente(clienteData);
            });
        });
    }
    
    // Función global para seleccionar cliente directamente (doble clic)
    window.seleccionarClienteDirecto = function(clienteId) {
        const cliente = datosMaestros.clientes.find(c => c.id == clienteId);
        if (cliente) {
            seleccionarCliente(cliente);
            // Opcional: auto-aceptar después de doble clic
            setTimeout(() => {
                if (clienteSeleccionadoTemp) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                    if (window._selectorClienteCallback) {
                        window._selectorClienteCallback(clienteSeleccionadoTemp);
                    }
                }
            }, 150);
        }
    };
    
    // Eventos de búsqueda y filtro
    if (searchInput) searchInput.addEventListener('input', () => renderizarClientes());
    if (filtroEstado) filtroEstado.addEventListener('change', () => renderizarClientes());
    if (btnLimpiarSeleccion) btnLimpiarSeleccion.addEventListener('click', () => limpiarSeleccion());
    if (btnCambiarSeleccion) btnCambiarSeleccion.addEventListener('click', () => limpiarSeleccion());
    
    // Evento del botón Aceptar
    if (btnAceptar) {
        btnAceptar.addEventListener('click', () => {
            if (clienteSeleccionadoTemp) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) modal.hide();
                if (window._selectorClienteCallback) {
                    window._selectorClienteCallback(clienteSeleccionadoTemp);
                }
            }
        });
    }
    
    // Evento when modal is fully shown
    modalElement.addEventListener('shown.bs.modal', function() {
        setTimeout(() => {
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, 100);
    });
    
    // Renderizar inicial
    renderizarClientes();
    
    // Guardar callback para uso global
    window._selectorClienteCallback = callback;
    
    // Mostrar modal
    modal.show();
    
    // Enfocar el buscador automáticamente
    setTimeout(() => {
        if (searchInput) searchInput.focus();
    }, 300);
    
    // Limpiar al cerrar
    modalElement.addEventListener('hidden.bs.modal', () => {
        modalElement.remove();
        styleSheet.remove();
        delete window._selectorClienteCallback;
        delete window.seleccionarClienteDirecto;
        clienteSeleccionadoTemp = null;
    });
}

// ============================================
// FUNCIÓN MODIFICADA handleClienteChangeFromInput CON VALIDACIÓN DE DUPLICADOS
// ============================================
function handleClienteChangeFromInput(facturaIndex, inputValue) {
    if (!inputValue || inputValue.trim() === '') {
        facturasData[facturaIndex].cliente_id = '';
        facturasData[facturaIndex].cliente_info = null;
        renderizarFacturas();
        guardarEnLocalStorage();
        
        const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
        if (clearBtn) clearBtn.classList.remove('visible');
        return;
    }
    
    const datalist = document.getElementById('clientes-datalist');
    const options = datalist.querySelectorAll('option');
    let clienteEncontrado = null;
    
    for (let opt of options) {
        if (opt.value === inputValue) {
            const clienteId = opt.getAttribute('data-id');
            clienteEncontrado = datosMaestros.clientes.find(c => c.id == clienteId);
            break;
        }
    }
    
    if (!clienteEncontrado) {
        clienteEncontrado = datosMaestros.clientes.find(c => {
            const textoCompleto = `${c.codigo || ''} - ${c.nombre || ''} ${c.ContratoNo ? '[' + c.ContratoNo + ']' : ''}`.trim();
            return textoCompleto.toLowerCase().includes(inputValue.toLowerCase());
        });
    }
    
    if (!clienteEncontrado) {
        facturasData[facturaIndex].cliente_id = '';
        facturasData[facturaIndex].cliente_info = null;
        renderizarFacturas();
        guardarEnLocalStorage();
        
        const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
        if (clearBtn) clearBtn.classList.remove('visible');
        
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Cliente no encontrado',
            text: 'No se encontró un cliente con esos datos',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    // ============================================
    // VALIDACIÓN DE FACTURA DUPLICADA POR CLIENTE Y FECHA
    // ============================================
    const fechaFacturaActual = facturasData[facturaIndex].fecha_emision;
    
    // Buscar si ya existe otra factura del mismo cliente en la misma fecha
    const facturaExistente = facturasData.find((factura, idx) => {
        // No comparar con la misma factura que estamos editando
        if (idx === facturaIndex) return false;
        // Verificar si tiene el mismo cliente
        if (factura.cliente_id == clienteEncontrado.id) {
            // Verificar si es la misma fecha
            return factura.fecha_emision === fechaFacturaActual;
        }
        return false;
    });
    
    if (facturaExistente) {
        // Existe una factura duplicada
        const fechaExistente = facturaExistente.fecha_emision;
        const fechaFormateada = fechaExistente.split('-').reverse().join('/');
        const facturaIdExistente = facturaExistente.id;
        const clienteNombre = clienteEncontrado.nombre || 'Cliente';
        
        swalDark.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Factura ya existe',
            html: `
                <div class="text-start">
                    <p><strong>${escapeHtml(clienteNombre)}</strong> ya tiene una factura registrada el día <strong class="text-warning">${fechaFormateada}</strong>.</p>
                    <div class="alert alert-info mt-3 py-2 px-3" style="background: #1e3a5f; border: 1px solid #3b82f6;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-file-invoice me-2"></i>Factura existente: <strong>#${facturaIdExistente}</strong></span>
                            <span class="badge bg-primary">${fechaFormateada}</span>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted">¿Qué deseas hacer?</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-plus-circle me-2"></i>Crear nueva igual',
            denyButtonText: '<i class="fas fa-arrow-right me-2"></i>Ir a la existente',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            confirmButtonColor: '#10b981',
            denyButtonColor: '#3b82f6',
            cancelButtonColor: '#6c757d',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Crear nueva igual de todas formas (mantener el cliente)
                asignarClienteAFactura(facturaIndex, clienteEncontrado);
                renderizarFacturas();
                guardarEnLocalStorage();
                
                setTimeout(() => {
                    const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
                    if (clearBtn) clearBtn.classList.add('visible');
                }, 50);
                
                swalDark.fire({
                    icon: 'success',
                    title: '<i class="fas fa-check-circle me-2"></i>Cliente asignado',
                    html: `<strong>${escapeHtml(clienteEncontrado.nombre)}</strong> ha sido asignado a la <strong>Factura #${facturaIndex + 1}</strong>`,
                    toast: true,
                    position: 'top-end',
                    timer: 2500,
                    showConfirmButton: false
                });
            } else if (result.isDenied) {
                // Ir a la factura existente
                if (typeof window.irAFactura === 'function') {
                    window.irAFactura(facturaExistente.id);
                } else if (typeof irAFactura === 'function') {
                    irAFactura(facturaExistente.id);
                } else {
                    // Fallback manual si la función no existe
                    const card = document.getElementById(`factura-card-${facturaExistente.id}`);
                    if (card) {
                        // Expandir si está colapsada
                        if (card.classList.contains('collapsed')) {
                            card.classList.remove('collapsed');
                            const chevron = document.getElementById(`chevron-${facturaExistente.id}`);
                            if (chevron) chevron.classList.add('expanded');
                        }
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        card.style.boxShadow = '0 0 0 3px var(--win-accent)';
                        setTimeout(() => { card.style.boxShadow = ''; }, 2000);
                    }
                }
                
                // Limpiar el cliente de la factura actual para evitar duplicados
                facturasData[facturaIndex].cliente_id = '';
                facturasData[facturaIndex].cliente_info = null;
                renderizarFacturas();
                guardarEnLocalStorage();
                
                swalDark.fire({
                    icon: 'info',
                    title: '<i class="fas fa-info-circle me-2"></i>Redirigiendo',
                    html: `Serás redirigido a la <strong>Factura #${facturaExistente.id}</strong> de <strong>${escapeHtml(clienteEncontrado.nombre)}</strong>`,
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                // Cancelar - limpiar el campo cliente
                const input = document.getElementById(`cliente-input-${facturaIndex}`);
                if (input) input.value = '';
                facturasData[facturaIndex].cliente_id = '';
                facturasData[facturaIndex].cliente_info = null;
                renderizarFacturas();
                guardarEnLocalStorage();
            }
        });
        return; // IMPORTANTE: Salir de la función después del duplicado
    }
    
    // No hay duplicado, asignar normalmente
    asignarClienteAFactura(facturaIndex, clienteEncontrado);
    renderizarFacturas();
    guardarEnLocalStorage();
    
    setTimeout(() => {
        const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
        if (clearBtn) clearBtn.classList.add('visible');
    }, 50);
}

// ============================================
// FUNCIÓN AUXILIAR PARA ASIGNAR CLIENTE A FACTURA
// ============================================
function asignarClienteAFactura(facturaIndex, cliente) {
    facturasData[facturaIndex].cliente_id = cliente.id;
    facturasData[facturaIndex].cliente_info = {
        id: cliente.id,
        codigo: cliente.codigo,
        nombre: cliente.nombre,
        ContratoNo: cliente.ContratoNo,
        telefono: cliente.telefono,
        email: cliente.email,
        activo: cliente.activo,
        fechaVence: cliente.fechaVence,
        fechaRegistro: cliente.fechaRegistro,
        vigenciapor: cliente.vigenciapor,
        renovac: cliente.renovac,
        fechafinalcontrato: cliente.fechafinalcontrato,
        ResponsableEntidad: cliente.ResponsableEntidad
    };
}

// ============================================
// FUNCIÓN ADICIONAL: Validar duplicados al cambiar la fecha de una factura
// ============================================
function validarDuplicadosPorFecha(facturaIndex, nuevaFecha) {
    const factura = facturasData[facturaIndex];
    if (!factura.cliente_id) return true; // Sin cliente, no hay duplicado
    
    const facturaDuplicada = facturasData.find((f, idx) => {
        if (idx === facturaIndex) return false;
        return f.cliente_id === factura.cliente_id && f.fecha_emision === nuevaFecha;
    });
    
    if (facturaDuplicada) {
        const fechaFormateada = nuevaFecha.split('-').reverse().join('/');
        const clienteNombre = factura.cliente_info?.nombre || 'Cliente';
        
        swalDark.fire({
            title: '<i class="fas fa-exclamation-triangle me-2"></i> Conflicto de fechas',
            html: `
                <p><strong>${escapeHtml(clienteNombre)}</strong> ya tiene una factura el día <strong class="text-warning">${fechaFormateada}</strong>.</p>
                <div class="alert alert-info mt-2 py-2">
                    Factura existente: <strong>#${facturaDuplicada.id}</strong>
                </div>
                <p class="mt-2">Cambiar la fecha puede crear duplicados. ¿Deseas continuar?</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-calendar-alt me-2"></i>Sí, cambiar igual',
            cancelButtonText: '<i class="fas fa-undo-alt me-2"></i>Cancelar',
            confirmButtonColor: '#f59e0b'
        }).then((result) => {
            if (result.isConfirmed) {
                // Permitir el cambio
                facturasData[facturaIndex].fecha_emision = nuevaFecha;
                renderizarFacturas();
                guardarEnLocalStorage();
            }
        });
        return false;
    }
    
    return true;
}


function duplicarFactura(index) {
    const original = JSON.parse(JSON.stringify(facturasData[index]));
    facturaCounter++;
    const nuevaFacturaId = facturaCounter;
    original.id = nuevaFacturaId;
    original.estado = 'PENDIENTE';
    facturasData.push(original);
    renderizarFacturas();
    actualizarContador();
    guardarEnLocalStorage();
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-copy me-2"></i>Factura duplicada',
        text: `Se ha creado la Factura #${nuevaFacturaId}`,
        timer: 2000, 
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
    
    setTimeout(() => {
        const input = document.getElementById(`cliente-input-${facturasData.length - 1}`);
        if (input && input.value) {
            toggleClearButton(facturasData.length - 1, input.value);
        }
    }, 50);
}
		
		
function eliminarFactura(index) {
    const facturaAEliminar = facturasData[index];
    const facturaId = facturaAEliminar.id;
    const clienteNombre = facturaAEliminar.cliente_info?.nombre || 'Sin cliente';
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Eliminar factura?',
        html: `Se eliminará la <strong>Factura #${facturaId}</strong><br><small class="text-muted">${clienteNombre}</small>`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            facturasData.splice(index, 1);
            renderizarFacturas();
            actualizarContador();
            guardarEnLocalStorage();
        // Actualizar dropdown
        actualizarBadgeFacturas();
        if (dropdownOpen) {
            actualizarDropdownFacturas();
        }
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>Factura eliminada', 
                text: `Factura #${facturaId} eliminada correctamente`,
                timer: 1500, 
                showConfirmButton: false 
            });
        }
    });
}
		
		
		
        // ============================================
        // MODAL DE SERVICIOS
        // ============================================
function abrirModalServicios(facturaIndex) {
    // LIMPIAR FILTROS COMPLETAMENTE
    const searchInput = document.getElementById('searchServicio');
    if (searchInput) {
        searchInput.value = '';  // Limpiar texto de búsqueda
    }
    
    const selectCategoria = document.getElementById('selectCategoria');
    if (selectCategoria) {
        selectCategoria.value = '';  // Resetear categoría a "Todas"
    }
    
    // Limpiar selecciones previas
    serviciosSeleccionadosModal.clear();
    
    // Guardar referencia de la factura actual
    facturaActualParaServicios = facturaIndex;
    
    // Obtener servicios ya agregados en esta factura
    const factura = facturasData[facturaIndex];
    const serviciosAgregados = factura.detalles.map(d => d.servicio_id);
    
    // RENDERIZAR LA LISTA COMPLETA (sin filtros)
    renderizarTablaServiciosModal(serviciosAgregados);
    
    // Resetear el checkbox "Seleccionar todos"
    const checkAll = document.getElementById('checkAllModal');
    if (checkAll) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
    }
    
    // Actualizar contador
    actualizarContadorModal();
    
    // Abrir el modal
    $('#servicioModal').modal('show');
}
        
function renderizarTablaServiciosModal(serviciosAgregados = []) {
    const tbody = document.getElementById('serviciosModalBody');
    const categoriaId = document.getElementById('selectCategoria').value;
    const searchTerm = document.getElementById('searchServicio').value.toLowerCase();
    
    // Filtrar servicios (excluir los ya agregados y los inactivos)
    let serviciosFiltrados = datosMaestros.servicios.filter(s => {
        // Excluir servicios ya agregados a esta factura
        if (serviciosAgregados.includes(s.id)) return false;
        // Excluir servicios inactivos
        if (s.activo != 1) return false;
        return true;
    });
    
    // Aplicar filtro por categoría (si hay una seleccionada)
    if (categoriaId && categoriaId !== '') {
        serviciosFiltrados = serviciosFiltrados.filter(s => s.categoria_id == categoriaId);
    }
    
    // Aplicar filtro por búsqueda (si hay texto)
    if (searchTerm && searchTerm !== '') {
        serviciosFiltrados = serviciosFiltrados.filter(s => {
            const desc = (s.descripcion || '').toLowerCase();
            const cod = (s.codigo || '').toLowerCase();
            return desc.includes(searchTerm) || cod.includes(searchTerm);
        });
    }
    
    // Generar HTML
    let html = '';
    if (serviciosFiltrados.length === 0) {
        html += `
    <tr onclick="toggleSeleccionServicio(${s.id}, this)" class="${serviciosSeleccionadosModal.has(s.id) ? 'selected-row' : ''}">
        <td><input type="checkbox" class="servicio-checkbox" value="${s.id}" ${serviciosSeleccionadosModal.has(s.id) ? 'checked' : ''} onclick="event.stopPropagation()"></td>
        <td><span class="badge bg-secondary">${s.codigo || 'N/A'}</span></td>
        <td>${s.descripcion}</td>
        <td><small>${categoria ? categoria.descripcion : 'Sin categoría'}</small></td>
        <td class="text-end fw-bold">${formatearMoneda(s.costo || 0)}</td>
    </tr>
`;
    } else {
        serviciosFiltrados.forEach(s => {
            const categoria = datosMaestros.categorias.find(c => c.id == s.categoria_id);
            const estaSeleccionado = serviciosSeleccionadosModal.has(s.id);
            html += `
                <tr style="cursor: pointer;" onclick="toggleSeleccionServicio(${s.id}, this)">
                    <td><input type="checkbox" class="servicio-checkbox" value="${s.id}" ${estaSeleccionado ? 'checked' : ''} onclick="event.stopPropagation(); toggleSeleccionServicio(${s.id})"></td>
                    <td><span class="badge bg-secondary">${s.codigo || 'N/A'}</span></td>
                    <td>${s.descripcion}</td>
                    <td><small>${categoria ? categoria.descripcion : 'Sin categoría'}</small></td>
                    <td class="text-end fw-bold">${formatearMoneda(s.costo || 0)}</td>
                </tr>
            `;
        });
    }
    tbody.innerHTML = html;
    actualizarContadorModal();
}
        
function toggleSeleccionServicio(servicioId, rowElement) {
    const row = rowElement;
    
    if (serviciosSeleccionadosModal.has(servicioId)) {
        serviciosSeleccionadosModal.delete(servicioId);
        if (row) row.classList.remove('selected-row');
    } else {
        serviciosSeleccionadosModal.add(servicioId);
        if (row) row.classList.add('selected-row');
    }
    
    const checkbox = document.querySelector(`.servicio-checkbox[value="${servicioId}"]`);
    if (checkbox) checkbox.checked = serviciosSeleccionadosModal.has(servicioId);
    actualizarContadorModal();
}
        
        function actualizarContadorModal() {
            const count = serviciosSeleccionadosModal.size;
            document.getElementById('numSeleccionados').textContent = count;
            document.getElementById('btnAgregarCount').textContent = count;
            document.getElementById('btnAgregarServicios').disabled = count === 0;
            
            const total = document.querySelectorAll('.servicio-checkbox').length;
            const checkAll = document.getElementById('checkAllModal');
            if (total > 0) {
                checkAll.checked = count === total;
                checkAll.indeterminate = count > 0 && count < total;
            }
        }
        
        function seleccionarTodosModal() {
            document.querySelectorAll('.servicio-checkbox').forEach(cb => {
                cb.checked = true;
                serviciosSeleccionadosModal.add(parseInt(cb.value));
            });
            actualizarContadorModal();
        }
        
        function deseleccionarTodosModal() {
            document.querySelectorAll('.servicio-checkbox').forEach(cb => cb.checked = false);
            serviciosSeleccionadosModal.clear();
            actualizarContadorModal();
        }
        
        function toggleTodosModal() {
            document.getElementById('checkAllModal').checked ? seleccionarTodosModal() : deseleccionarTodosModal();
        }
        
		function filtrarServiciosModal() {
			const factura = facturasData[facturaActualParaServicios];
			const serviciosAgregados = factura ? factura.detalles.map(d => d.servicio_id) : [];
			renderizarTablaServiciosModal(serviciosAgregados);
		}
        
        function agregarServiciosSeleccionados() {
            if (serviciosSeleccionadosModal.size === 0 || facturaActualParaServicios === null) return;
            const factura = facturasData[facturaActualParaServicios];
            
            serviciosSeleccionadosModal.forEach(servicioId => {
                const servicio = datosMaestros.servicios.find(s => s.id == servicioId);
                if (servicio) {
                    factura.detalles.push({
                        servicio_id: servicio.id,
                        servicio_info: servicio,
                        cantidad: 1,
                        precio_unitario: parseFloat(servicio.costo || 0),
                        total_linea: parseFloat(servicio.costo || 0)
                    });
                }
            });
            
            actualizarTotalFactura(facturaActualParaServicios);
            renderizarFacturas();
            guardarEnLocalStorage();
            $('#servicioModal').modal('hide');
            
            swalDark.fire({
                icon: 'success', toast: true, position: 'top-end',
                title: '<i class="fas fa-check-circle me-2"></i>' + serviciosSeleccionadosModal.size + ' servicio(s) agregado(s)',
                showConfirmButton: false, timer: 3000
            });
        }
        
        // ============================================
        // CÁLCULOS
        // ============================================
        function actualizarTotalFactura(facturaIndex) {
            const factura = facturasData[facturaIndex];
            let subtotal = 0;
            factura.detalles.forEach(d => {
                d.total_linea = d.cantidad * d.precio_unitario;
                subtotal += d.total_linea;
            });
            factura.subtotal = subtotal;
            factura.total_general = subtotal;
        }
        
        function cambiarCantidad(facturaIndex, servicioIndex, cambio) {
            const detalle = facturasData[facturaIndex].detalles[servicioIndex];
            let nueva = detalle.cantidad + cambio;
            if (nueva < 1) nueva = 1;
            detalle.cantidad = nueva;
            actualizarTotalFactura(facturaIndex);
            renderizarFacturas();
            guardarEnLocalStorage();
        }
        
        function eliminarServicio(facturaIndex, servicioIndex) {
            facturasData[facturaIndex].detalles.splice(servicioIndex, 1);
            actualizarTotalFactura(facturaIndex);
            renderizarFacturas();
            guardarEnLocalStorage();
        }
        
        // ============================================
        // RENDERIZADO
        // ============================================
// ============================================
// RENDERIZADO
// ============================================
function renderizarFacturas() {
    const container = document.getElementById('facturas-container');
    if (facturasData.length === 0) {
        container.style.display = 'block';
        container.innerHTML = `<div class="empty-state"><i class="fas fa-file-invoice"></i><h4>No hay facturas</h4><p class="text-muted">Haz clic en "Nueva " para comenzar a crear Facturas</p></div>`;
        actualizarTotalImporte();
        return;
    }
    
    container.style.display = 'grid';
    container.style.gridTemplateColumns = 'repeat(2, 1fr)';
    container.style.gap = '20px';
    container.innerHTML = '';
    
    facturasData.forEach((factura, fIndex) => {
        const clienteInfo = factura.cliente_info;
        let clienteInputValue = '';
        if (clienteInfo) {
            clienteInputValue = `${clienteInfo.codigo || ''} - ${clienteInfo.nombre || ''} ${clienteInfo.ContratoNo ? '[' + clienteInfo.ContratoNo + ']' : ''}`.trim();
        }
        
        const div = document.createElement('div');
        div.className = 'factura-card card';
        div.id = `factura-card-${factura.id}`;
        div.style.marginBottom = '0';
        div.style.background = 'var(--win-bg-secondary)';
        div.style.border = '1px solid var(--win-border-color)';
        div.style.height = 'fit-content';
        
        // Determinar estado del contrato
        let estadoContratoHtml = '<span class="badge bg-secondary">Sin fecha</span>';
        if (clienteInfo && clienteInfo.fechaVence) {
            const fechaVence = new Date(clienteInfo.fechaVence);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            fechaVence.setHours(0, 0, 0, 0);
            const vencido = fechaVence < hoy;
            estadoContratoHtml = vencido 
                ? '<span class="badge bg-danger">VENCIDO</span>' 
                : '<span class="badge bg-success">VIGENTE</span>';
        }
        
        div.innerHTML = `
            <div class="card-header-custom" onclick="toggleFactura(${factura.id})">
                <div class="card-header-left">
                    <i class="fas fa-chevron-right chevron-icon expanded" id="chevron-${factura.id}"></i>
					<h5 class="mb-0 text-info">
						<i class="fas fa-file-invoice me-2"></i>Factura #${factura.id} 
						${clienteInfo ? `<small class="ms-3 text-warning">${clienteInfo.nombre}</small>` : '<small class="ms-3 text-warning">SIN CLIENTE</small>'}
					</h5>
                </div>
                <div class="card-header-right">
                    <span class="badge bg-primary me-2">Total: ${formatearMoneda(factura.total_general)}</span>
					</span>
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="event.stopPropagation(); duplicarFactura(${fIndex})" title="Duplicar factura">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); eliminarFactura(${fIndex})" title="Eliminar factura">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-user me-2"></i>Cliente:</label>
                        <div class="cliente-input-wrapper">
                            <input type="text" class="form-control" list="clientes-datalist" id="cliente-input-${fIndex}" value="${clienteInputValue}" placeholder="Buscar cliente..." onchange="handleClienteChangeFromInput(${fIndex}, this.value)" oninput="toggleClearButton(${fIndex}, this.value)" autocomplete="off" style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">
                            <button type="button" class="btn-clear-cliente" id="clear-cliente-${fIndex}" onclick="limpiarCliente(${fIndex})" title="Limpiar cliente">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        ${clienteInfo ? `
                            <div class="cliente-info-card">
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: var(--win-border-color) !important;">
                                    <h6 class="mb-0 text-warning"><i class="fas fa-building me-2"></i>${clienteInfo.nombre || 'Cliente'}</h6>
                                    <span class="badge bg-secondary">Cód: ${clienteInfo.codigo || '--'}</span>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">No. Contrato:</span><span class="cliente-info-value"><span class="badge bg-primary">${clienteInfo.ContratoNo || 'S/C'}</span></span></div></div>
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">Responsable:</span><span class="cliente-info-value">${clienteInfo.ResponsableEntidad || '--'}</span></div></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">Estado/Cliente:</span><span class="cliente-info-value"><span class="badge ${clienteInfo.activo == 1 ? 'bg-success' : 'bg-danger'}">${clienteInfo.activo == 1 ? 'Activo' : 'Inactivo'}</span></span></div></div>
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">Estado/Contrato:</span><span class="cliente-info-value">${estadoContratoHtml}</span></div></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">F. Inicio:</span><span class="cliente-info-value">${clienteInfo.fechaRegistro ? new Date(clienteInfo.fechaRegistro).toLocaleDateString('es-ES') : '--'}</span></div></div>
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">F. Término:</span><span class="cliente-info-value">${clienteInfo.fechaVence ? new Date(clienteInfo.fechaVence).toLocaleDateString('es-ES') : '--'}</span></div></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">Vigencia:</span><span class="cliente-info-value">${clienteInfo.vigenciapor ? clienteInfo.vigenciapor + ' año(s)' : '--'}</span></div></div>
                                    <div class="col-6"><div class="cliente-info-item"><span class="cliente-info-label">Suplem:</span><span class="cliente-info-value">${clienteInfo.renovac == 1 ? 'Sí' : 'No'}</span></div></div>
                                </div>
                                <div class="row"><div class="col-12"><div class="alert alert-info py-2 px-3 mb-0"><div class="d-flex justify-content-between"><span class="text-primary cliente-info-label">Fecha Final:</span><span class="fw-bold">${clienteInfo.fechafinalcontrato ? new Date(clienteInfo.fechafinalcontrato).toLocaleDateString('es-ES') : '--'}</span></div></div></div></div>
                                <div class="mt-3 pt-2 border-top" style="border-color: var(--win-border-color) !important;"><div class="d-flex justify-content-between"><small><i class="fas fa-phone me-1"></i> ${clienteInfo.telefono || '--'}</small><small><i class="fas fa-envelope me-1"></i> ${clienteInfo.email || '--'}</small></div></div>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-credit-card me-2"></i>Tipo de Pago:</label>
                        <select class="form-select" id="pago-${fIndex}" onchange="handlePagoChange(${fIndex}, this.value)" style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">
                            <option value="">Seleccione tipo...</option>
                            ${datosMaestros.tiposPago.map(t => `<option value="${t.id}" ${factura.tipo_pago_id == t.id ? 'selected' : ''}>${t.descripcion}</option>`).join('')}
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-tag me-2"></i>Estado:</label>
                        <select class="form-select" disabled style="background: var(--win-bg-secondary); opacity: 0.8;"><option value="PENDIENTE" selected>PENDIENTE</option></select>
                        <input type="hidden" value="PENDIENTE">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar me-2"></i>Fecha:</label>
                        <input type="date" class="form-control" value="${factura.fecha_emision}" min="${fechaOperativaInicio}" max="${fechaOperativaFin}" onchange="facturasData[${fIndex}].fecha_emision = this.value; guardarEnLocalStorage();" style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">
                        ${fechaOperativaInicio && fechaOperativaFin ? `<small class="text-muted d-block mt-1"><i class="fas fa-calendar-alt me-1"></i>${fechaOperativaInicio.split('-').reverse().join('/')} - ${fechaOperativaFin.split('-').reverse().join('/')}</small>` : ''}
                    </div>
                </div>
                
                <div class="row"><div class="col-12 mb-3"><label class="form-label fw-bold"><i class="fas fa-comment me-2"></i>Observaciones:</label><textarea class="form-control" rows="2" onchange="facturasData[${fIndex}].observaciones = this.value; guardarEnLocalStorage();" style="background: var(--win-bg-tertiary); color: var(--win-text-primary);">${factura.observaciones || ''}</textarea></div></div>
                
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
							<i class="fas fa-list me-2"></i>Detalles de Servicios
							<span class="ms-3 badge bg-success">
								<i class="fas fa-dollar-sign me-1"></i>Total: ${formatearMoneda(factura.total_general)}
							</span>
						</h6>
                        <button class="btn btn-sm btn-primary" onclick="abrirModalServicios(${fIndex})"><i class="fas fa-plus me-1"></i>Agregar Servicios</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>#</th><th>Servicio</th><th style="width: 100px; text-align: center;">Cantidad</th><th style="width: 120px; text-align: right;">Precio</th><th style="width: 120px; text-align: right;">Total</th><th style="width: 50px;"></th></tr></thead>
                            <tbody id="servicios-body-${fIndex}"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer-custom">
                <div><i class="text-warning fas fa-calculator me-1"></i> <strong class="text-warning">Subtotal: ${formatearMoneda(factura.subtotal)}</strong></div>
                <div class="total-general"><i class="fas fa-file-invoice-dollar me-1"></i> Total: ${formatearMoneda(factura.total_general)}</div>
            </div>
        `;
        
        container.appendChild(div);
        
        const tbody = document.getElementById(`servicios-body-${fIndex}`);
        factura.detalles.forEach((servicio, sIndex) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${sIndex + 1}</td>
                <td><strong>${servicio.servicio_info?.descripcion || 'Servicio'}</strong><div class="text-muted small">${servicio.servicio_info?.codigo || ''}</div></td>
                <td><div class="cantidad-control"><button class="btn btn-sm btn-outline-secondary cantidad-btn" onclick="cambiarCantidad(${fIndex}, ${sIndex}, -1)">-</button><input type="number" class="form-control form-control-sm cantidad-input" value="${servicio.cantidad}" min="1" onchange="actualizarCantidadInput(${fIndex}, ${sIndex}, this.value)"><button class="btn btn-sm btn-outline-secondary cantidad-btn" onclick="cambiarCantidad(${fIndex}, ${sIndex}, 1)">+</button></div></td>
                <td><input type="text" class="form-control form-control-sm text-end" value="$${servicio.precio_unitario.toFixed(2)}" readonly style="background: var(--win-bg-secondary);"></td>
                <td class="text-end fw-bold">$${servicio.total_linea.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="eliminarServicio(${fIndex}, ${sIndex})"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
        });
    });
    
    actualizarTotalImporte();
    actualizarEstadoBotonToggleAll();
}
        
        function handleClienteChangeFromInput(facturaIndex, inputValue) {
            if (!inputValue || inputValue.trim() === '') {
                facturasData[facturaIndex].cliente_id = '';
                facturasData[facturaIndex].cliente_info = null;
                renderizarFacturas();
                guardarEnLocalStorage();
				
        const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
        if (clearBtn) clearBtn.classList.remove('visible');
		
                return;
            }
            
            const datalist = document.getElementById('clientes-datalist');
            const options = datalist.querySelectorAll('option');
            
            for (let opt of options) {
                if (opt.value === inputValue) {
                    const clienteId = opt.getAttribute('data-id');
                    const cliente = datosMaestros.clientes.find(c => c.id == clienteId);
                    if (cliente) {
                        facturasData[facturaIndex].cliente_id = clienteId;
                        facturasData[facturaIndex].cliente_info = {
                            id: cliente.id, codigo: cliente.codigo, nombre: cliente.nombre,
                            ContratoNo: cliente.ContratoNo, telefono: cliente.telefono, email: cliente.email,
                            activo: cliente.activo, fechaVence: cliente.fechaVence, fechaRegistro: cliente.fechaRegistro,
                            vigenciapor: cliente.vigenciapor, renovac: cliente.renovac, fechafinalcontrato: cliente.fechafinalcontrato,
                            ResponsableEntidad: cliente.ResponsableEntidad
                        };
                    }
                    renderizarFacturas();
                    guardarEnLocalStorage();
					
            setTimeout(() => {
                const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
                if (clearBtn) clearBtn.classList.add('visible');
            }, 50);
			
                    return;
                }
            }
            
            const clienteEncontrado = datosMaestros.clientes.find(c => {
                const textoCompleto = `${c.codigo || ''} - ${c.nombre || ''} ${c.ContratoNo ? '[' + c.ContratoNo + ']' : ''}`.trim();
                return textoCompleto.toLowerCase().includes(inputValue.toLowerCase());
            });
            
            if (clienteEncontrado) {
                facturasData[facturaIndex].cliente_id = clienteEncontrado.id;
                facturasData[facturaIndex].cliente_info = {
                    id: clienteEncontrado.id, codigo: clienteEncontrado.codigo, nombre: clienteEncontrado.nombre,
                    ContratoNo: clienteEncontrado.ContratoNo, telefono: clienteEncontrado.telefono, email: clienteEncontrado.email,
                    activo: clienteEncontrado.activo, fechaVence: clienteEncontrado.fechaVence, fechaRegistro: clienteEncontrado.fechaRegistro,
                    vigenciapor: clienteEncontrado.vigenciapor, renovac: clienteEncontrado.renovac, fechafinalcontrato: clienteEncontrado.fechafinalcontrato,
                    ResponsableEntidad: clienteEncontrado.ResponsableEntidad
                };
				
        setTimeout(() => {
            const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
            if (clearBtn) clearBtn.classList.add('visible');
        }, 50);
		
            } else {
                facturasData[facturaIndex].cliente_id = '';
                facturasData[facturaIndex].cliente_info = null;
				
				const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
				if (clearBtn) clearBtn.classList.remove('visible');
		
            }
            renderizarFacturas();
            guardarEnLocalStorage();
        }


// Mostrar/ocultar botón X según si hay texto
function toggleClearButton(facturaIndex, value) {
    const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
    if (clearBtn) {
        if (value && value.trim() !== '') {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
        }
    }
}

// Limpiar el cliente seleccionado
function limpiarCliente(facturaIndex) {
    const input = document.getElementById(`cliente-input-${facturaIndex}`);
    if (input) {
        input.value = '';
        input.focus();
        
        // Limpiar los datos de la factura
        facturasData[facturaIndex].cliente_id = '';
        facturasData[facturaIndex].cliente_info = null;
        
        // Ocultar el botón X
        const clearBtn = document.getElementById(`clear-cliente-${facturaIndex}`);
        if (clearBtn) {
            clearBtn.classList.remove('visible');
        }
        
        // Re-renderizar TODO para actualizar el título y ocultar la tarjeta de info del cliente
        renderizarFacturas();
        guardarEnLocalStorage();
        
        // Pequeño retraso para asegurar que el DOM se actualice
        setTimeout(() => {
            const cardHeaderTitle = document.querySelector(`#factura-card-${facturaIndex} .card-header-left h5`);
            if (cardHeaderTitle) {
                // Forzar actualización visual del título
                const facturaId = facturasData[facturaIndex]?.id || (facturaIndex + 1);
                cardHeaderTitle.innerHTML = `<i class="fas fa-file-invoice me-2"></i>Factura #${facturaId} <small class="ms-3 text-warning">SIN CLIENTE</small>`;
            }
        }, 50);
    }
}
        function handlePagoChange(facturaIndex, pagoId) {
            const tipoPago = datosMaestros.tiposPago.find(t => t.id == pagoId);
            facturasData[facturaIndex].tipo_pago_id = pagoId;
            facturasData[facturaIndex].tipo_pago_info = tipoPago || null;
            renderizarFacturas();
            guardarEnLocalStorage();
        }
        
        function actualizarCantidadInput(facturaIndex, servicioIndex, valor) {
            let cantidad = parseInt(valor) || 1;
            if (cantidad < 1) cantidad = 1;
            facturasData[facturaIndex].detalles[servicioIndex].cantidad = cantidad;
            actualizarTotalFactura(facturaIndex);
            renderizarFacturas();
            guardarEnLocalStorage();
        }
        
        // ============================================
        // GENERACIÓN SQL
        // ============================================
function generarSQL() {
    const facturasValidas = facturasData.filter(f => f.cliente_id && f.detalles.length > 0);
    if (facturasValidas.length === 0) {
        swalDark.fire({ 
            icon: 'warning', 
            title: '<i class="fas fa-triangle-exclamation me-2"></i>Sin datos', 
            text: 'No hay facturas completas para generar SQL',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
        });
        return { sql: '', total: 0, facturas: [] };
    }
    
    // Formatear fecha y hora actual
    const ahora = new Date();
    const fechaFormateada = ahora.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
    const horaFormateada = ahora.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    }).replace('a.m.', 'a. m.').replace('p.m.', 'p. m.');
    
    // Obtener período operativo
    const inicio = fechaOperativaInicio.split('-').reverse().join('/');
    const fin = fechaOperativaFin.split('-').reverse().join('/');
    const periodo = `${inicio} - ${fin}`;
    
    // Obtener nombre del usuario
    const nombreUsuario = document.querySelector('.win-sidebar-user-info h6')?.innerText || 'Sistema';
    
    // Calcular totales
    let totalSubtotal = 0;
    let totalGeneral = 0;
    let totalServicios = 0;
    
    facturasValidas.forEach(f => {
        totalSubtotal += f.subtotal;
        totalGeneral += f.total_general;
        totalServicios += f.detalles.length;
    });
    
    let sql = `-- ============================================================================\n`;
    sql += `--     SCRIPT PARA IMPORTACION DE FACTURAS OFFLINE - SISFACT PDL VISIONES\n`;
    sql += `-- ============================================================================\n`;
    sql += `-- Fecha generación: ${fechaFormateada} - ${horaFormateada}\n`;
    sql += `-- Usuario: ${nombreUsuario}\n`;
    sql += `-- Período operativo: ${periodo}\n`;
    sql += `-- ============================================================================\n`;
    sql += `-- RESÚMEN DEL SCRIPT:\n`;
    sql += `--   • Facturas a insertar: ${facturasValidas.length}\n`;
    sql += `--   • Total servicios: ${totalServicios}\n`;
    sql += `--   • Subtotal general: ${formatearMoneda(totalSubtotal).replace('$', '').replace(/\./g, ',').replace(/,(\d{2})$/, '.$1')}\n`;
    sql += `--   • Total general: ${formatearMoneda(totalGeneral).replace('$', '').replace(/\./g, ',').replace(/,(\d{2})$/, '.$1')}\n`;
    sql += `-- ============================================================================\n\n`;
    
	facturasValidas.forEach((f, i) => {
			const tid = i + 1;
			
			// Procesar observaciones con el formato: coma + espacio + texto
			let observacionesSQL = 'NULL';
			if (f.observaciones && f.observaciones.trim() !== '') {
				let obsTexto = f.observaciones.trim();
				if (obsTexto.startsWith(', ')) {
					obsTexto = obsTexto.substring(2);
				}
				observacionesSQL = `', ${obsTexto.replace(/'/g, "\\'").replace(/\n/g, ' ')}'`;
			}
			
			sql += `-- FACTURA ${tid}: ${f.cliente_info?.nombre || 'ID ' + f.cliente_id}\n`;
			sql += `INSERT INTO tbl_fact (cliente_id, tipo_pago_id, subtotal, total_general, estado, fecha_emision, observaciones) VALUES (\n`;
			sql += `    ${f.cliente_id}, ${f.tipo_pago_id || 'NULL'}, ${f.subtotal.toFixed(2)}, ${f.total_general.toFixed(2)},\n`;
			sql += `    '${f.estado}', '${f.fecha_emision} 00:00:00', ${observacionesSQL}\n`;
			sql += `);\nSET @fid_${tid} = LAST_INSERT_ID();\n\n`;
		});
    
    sql += `-- DETALLES\n\n`;
    facturasValidas.forEach((f, i) => {
        const tid = i + 1;
        f.detalles.forEach(d => { 
            if (!d.servicio_id) return;
            sql += `INSERT INTO tbl_fact_detalle (factura_id, servicio_id, cantidad, precio_unitario, total_linea) VALUES (\n    @fid_${tid}, ${d.servicio_id}, ${d.cantidad}, ${d.precio_unitario.toFixed(2)}, ${d.total_linea.toFixed(2)}\n);\n`;
        });
        sql += `\n`;
    });
    
    sql += `-- ============================================================================\n`;
    sql += `-- FIN DEL SCRIPT\n`;
    sql += `-- Total facturas procesadas: ${facturasValidas.length}\n`;
    sql += `-- Total líneas de detalle: ${totalServicios}\n`;
    sql += `-- ============================================================================\n`;
    
    document.getElementById('sql-preview').value = sql;
    
    swalDark.fire({
        icon: 'success', 
        title: '<i class="fas fa-check-circle me-2"></i>SQL Generado', 
        text: `${facturasValidas.length} factura(s) listas para exportar`, 
        timer: 1500,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
        focusConfirm: false, 
        returnFocus: false,
        didOpen: () => { 
            const s = window.scrollY; 
            document.body.style.position = 'fixed'; 
            document.body.style.top = `-${s}px`; 
            document.body.style.width = '100%'; 
        },
        willClose: () => { 
            const s = document.body.style.top; 
            document.body.style.position = ''; 
            document.body.style.top = ''; 
            document.body.style.width = ''; 
            window.scrollTo(0, parseInt(s || '0') * -1); 
        },
        didClose: () => { 
            setTimeout(() => { 
                const p = document.querySelector('.sql-preview'); 
                if (p) p.scrollIntoView({ behavior: 'auto', block: 'start' }); 
            }, 50); 
        }
    });
    
    return { sql: sql, total: facturasValidas.length, facturas: facturasValidas };
}
        
        function limpiarSQL() { document.getElementById('sql-preview').value = ''; swalDark.fire({ icon: 'success', title: '<i class="fas fa-broom me-2"></i>Limpiado', timer: 1000, showConfirmButton: false }); }
        
        function copiarSQL() {
    const sql = document.getElementById('sql-preview').value;
    if (!sql) { 
        swalDark.fire({ 
            icon: 'warning', 
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin SQL', 
            text: 'Genera el SQL primero',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        }); 
        return; 
    }
    navigator.clipboard.writeText(sql).then(() => 
        swalDark.fire({ 
            icon: 'success', 
            title: '<i class="fas fa-copy me-2"></i>Copiado', 
            text: 'SQL copiado al portapapeles',
            timer: 1500, 
            showConfirmButton: false 
        })
    ).catch(() => 
        swalDark.fire({ 
            icon: 'error', 
            title: '<i class="fas fa-times-circle me-2"></i>Error', 
            text: 'No se pudo copiar el SQL',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        })
    );
}

        

async function descargarSQL() {
    // Generar SQL y obtener los datos
    const resultado = generarSQL();
    let sql = resultado.sql;
    
    if (!sql || resultado.total === 0) return;
    
    // Usar las facturas válidas del resultado
    const facturasValidas = resultado.facturas;
    
    // Fecha de creación del archivo
    const ahora = new Date();
    const fechaCreado = ahora.toISOString().slice(0, 10).split('-').reverse().join('-');
    const horaCreado = ahora.toTimeString().slice(0, 5).replace(/:/g, '-');
    
    let nombreArchivo = '';
    
    // Determinar nombre del archivo según cantidad de facturas
    if (facturasValidas.length === 1) {
        // Una sola factura: incluir nombre del cliente
        const facturaUnica = facturasValidas[0];
        let nombreCliente = 'SinCliente';
        let fechaEmision = '';
        
        if (facturaUnica.cliente_info && facturaUnica.cliente_info.nombre) {
            nombreCliente = facturaUnica.cliente_info.nombre
                .replace(/\s+/g, '_')
                .replace(/[^\w\d_]/g, '')
                .substring(0, 30);
        }
        if (facturaUnica.fecha_emision) {
            fechaEmision = facturaUnica.fecha_emision.split('-').reverse().join('-');
        }
        
        nombreArchivo = `SQL_${nombreCliente}`;
        if (fechaEmision) {
            nombreArchivo += `_${fechaEmision}`;
        }
        nombreArchivo += `_Creado_${fechaCreado}_${horaCreado}.sql`;
        
    } else {
        // Múltiples facturas: nombre genérico con cantidad y rango de fechas
        const periodoOperativo = obtenerPeriodoOperativo();
        
        // Obtener primera y última fecha de emisión
        let fechasValidas = facturasValidas
            .filter(f => f.fecha_emision)
            .map(f => f.fecha_emision)
            .sort();
        
        let rangoFechas = '';
        if (fechasValidas.length > 0) {
            const primeraFecha = fechasValidas[0].split('-').reverse().join('-');
            const ultimaFecha = fechasValidas[fechasValidas.length - 1].split('-').reverse().join('-');
            
            if (primeraFecha === ultimaFecha) {
                rangoFechas = primeraFecha;
            } else {
                rangoFechas = `${primeraFecha}_a_${ultimaFecha}`;
            }
        }
        
        // Construir nombre para múltiples facturas
        nombreArchivo = `SQL_${facturasValidas.length}_Facturas`;
        
        if (rangoFechas) {
            nombreArchivo += `_${rangoFechas}`;
        }
        
        // Agregar período operativo si existe y es diferente
        if (periodoOperativo && periodoOperativo !== 'No configurado') {
            const periodoLimpo = periodoOperativo
                .replace(/\//g, '-')
                .replace(/\s/g, '_');
            nombreArchivo += `_Periodo_${periodoLimpo}`;
        }
        
        nombreArchivo += `_Creado_${fechaCreado}_${horaCreado}.sql`;
    }
    
    const blob = new Blob([sql], {type: 'text/plain'});
    
    // Verificar si el navegador soporta File System Access API (Chrome/Edge)
    if ('showSaveFilePicker' in window) {
        try {
            const fileHandle = await window.showSaveFilePicker({
                suggestedName: nombreArchivo,
                types: [{
                    description: 'Archivo SQL',
                    accept: {'text/plain': ['.sql']}
                }]
            });
            
            const writable = await fileHandle.createWritable();
            await writable.write(blob);
            await writable.close();
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-download me-2"></i>SQL Exportado', 
                html: `
                    <div class="text-start">
                        <p><strong>${facturasValidas.length}</strong> factura(s) exportada(s)</p>
                        <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                    </div>
                `,
                timer: 2500, 
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
            });
            
        } catch (err) {
            if (err.name !== 'AbortError') {
                descargarSQLTradicional(blob, nombreArchivo, facturasValidas.length);
            }
        }
    } else {
        // Firefox y otros navegadores
        const isFirefox = navigator.userAgent.toLowerCase().includes('firefox');
        
        if (isFirefox) {
            const url = URL.createObjectURL(blob);
            
            swalDark.fire({
                icon: 'info',
                title: '<i class="fas fa-download me-2"></i>Exportar SQL',
                html: `
                    <div class="text-start">
                        <p><strong>${facturasValidas.length}</strong> factura(s) lista(s) para exportar</p>
                        <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                        <div class="alert alert-info mt-3 py-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Firefox:</strong> Haz clic en el botón para descargar. 
                            El archivo se guardará en tu carpeta de descargas predeterminada.
                        </div>
                        <p class="mt-2 mb-0">
                            <small class="text-warning">
                                <i class="fas fa-lightbulb me-1"></i>
                                Para cambiar la carpeta, ve a Opciones de Firefox > General > Descargas
                            </small>
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i>Descargar ahora',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                width: '550px'
            }).then((result) => {
                if (result.isConfirmed) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = nombreArchivo;
                    a.click();
                    URL.revokeObjectURL(url);
                    
                    swalDark.fire({ 
                        icon: 'success', 
                        title: '<i class="fas fa-download me-2"></i>SQL Exportado', 
                        html: `
                            <div class="text-start">
                                <p><strong>${facturasValidas.length}</strong> factura(s) exportada(s)</p>
                                <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>OK, GRACIAS',
                        didClose: () => {
                            document.body.style.position = '';
                            document.body.style.top = '';
                            document.body.style.width = '';
                            const scrollY = parseInt(document.body.style.top || '0') * -1;
                            window.scrollTo(0, scrollY);
                            setTimeout(() => {
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            }, 50);
                        }
                    }).then(() => {
                        setTimeout(() => {
                            document.body.style.position = '';
                            document.body.style.top = '';
                            document.body.style.width = '';
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }, 200);
                    });
                }
            });
        } else {
            // Otros navegadores (Safari, Opera, etc.)
            swalDark.fire({
                icon: 'info',
                title: '<i class="fas fa-download me-2"></i>¿Exportar SQL?',
                html: `
                    <div class="text-start">
                        <p><strong>${facturasValidas.length}</strong> factura(s) para exportar</p>
                        <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                        <div class="alert alert-info mt-3 py-2">
                            <i class="fas fa-info-circle me-2"></i>
                            El archivo se guardará en tu carpeta de descargas predeterminada.
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i>Sí, exportar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    descargarSQLTradicional(blob, nombreArchivo, facturasValidas.length);
                }
            });
        }
    }
}

// Función auxiliar para descarga tradicional de SQL
function descargarSQLTradicional(blob, nombreArchivo, totalFacturas) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    swalDark.fire({ 
        icon: 'success', 
        title: '<i class="fas fa-download me-2"></i>SQL Exportado', 
        html: `
            <div class="text-start">
                <p><strong>${totalFacturas}</strong> factura(s) exportada(s)</p>
                <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
            </div>
        `,
        timer: 2500, 
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
    });
}


// ============================================
// FUNCIÓN PARA OBTENER PERÍODO OPERATIVO FORMATEADO
// ============================================
function obtenerPeriodoOperativo() {
    if (fechaOperativaInicio && fechaOperativaFin) {
        const inicio = fechaOperativaInicio.split('-').reverse().join('/');
        const fin = fechaOperativaFin.split('-').reverse().join('/');
        return `${inicio} - ${fin}`;
    }
    return 'No configurado';
}

function obtenerMesAnioOperativo() {
    if (fechaOperativaInicio && fechaOperativaFin) {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const mes = meses[parseInt(fechaOperativaMes) - 1];
        return `${mes} / ${fechaOperativaAnio}`;
    }
    return '';
}
        
        // ============================================
        // ALMACENAMIENTO
        // ============================================
function cargarFacturasLocal() { 
    const d = localStorage.getItem('facturas_capturadas'); 
    if (d) { 
        try { 
            facturasData = JSON.parse(d); 
            if (facturasData.length > 0) {
                // Ordenar y renumerar al cargar para asegurar consistencia
                facturasData.sort((a, b) => a.id - b.id);
                for (let i = 0; i < facturasData.length; i++) {
                    facturasData[i].id = i + 1;
                }
                facturaCounter = facturasData.length;
                guardarEnLocalStorage(); // Guardar los IDs corregidos
            } else {
                facturaCounter = 0;
            }
        } catch(e) {} 
    }
    actualizarTotalImporte();
}

        function guardarEnLocalStorage() { localStorage.setItem('facturas_capturadas', JSON.stringify(facturasData)); }
        
async function guardarProgreso() {
    // Verificar si hay facturas para guardar
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-triangle-exclamation me-2"></i>Sin facturas',
            text: 'No hay facturas para guardar. Crea al menos una factura primero.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    guardarEnLocalStorage();
    
    // Obtener la primera factura para usar el nombre del cliente
    let nombreCliente = 'SinCliente';
    let fechaEmision = '';
    
    if (facturasData.length > 0) {
        const primeraFactura = facturasData[0];
        if (primeraFactura.cliente_info && primeraFactura.cliente_info.nombre) {
            nombreCliente = primeraFactura.cliente_info.nombre
                .replace(/\s+/g, '_')
                .replace(/[^\w\d_]/g, '')
                .substring(0, 30);
        }
        fechaEmision = primeraFactura.fecha_emision.split('-').reverse().join('-');
    }
    
    // Fecha de guardado (creado)
    const ahora = new Date();
    const fechaCreado = ahora.toISOString().slice(0, 10).split('-').reverse().join('-');
    const horaCreado = ahora.toTimeString().slice(0, 5).replace(/:/g, '-');
    
    // Construir nombre de archivo sugerido
    let nombreArchivo = `Fac_${nombreCliente}`;
    if (fechaEmision) {
        nombreArchivo += `_${fechaEmision}`;
    }
    nombreArchivo += `_Creado_${fechaCreado}_${horaCreado}.json`;
    
    const datosExport = { 
        version: '1.0', 
        fecha: new Date().toISOString(), 
        total_facturas: facturasData.length, 
        facturas: facturasData 
    };
    
    const dataStr = JSON.stringify(datosExport, null, 2);
    const blob = new Blob([dataStr], {type: 'application/json'});
    
    // Verificar si el navegador soporta File System Access API (Chrome/Edge)
    if ('showSaveFilePicker' in window) {
        try {
            const fileHandle = await window.showSaveFilePicker({
                suggestedName: nombreArchivo,
                types: [{
                    description: 'Archivo JSON',
                    accept: {'application/json': ['.json']}
                }]
            });
            
            const writable = await fileHandle.createWritable();
            await writable.write(blob);
            await writable.close();
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-save me-2"></i>Progreso guardado', 
                html: `
                    <div class="text-start">
                        <p><strong>${facturasData.length}</strong> factura(s) guardada(s)</p>
                        <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                    </div>
                `,
                timer: 2500, 
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
            });
            
        } catch (err) {
            if (err.name !== 'AbortError') {
                descargarTradicional(blob, nombreArchivo);
            }
        }
    } else {
        // Firefox y otros navegadores - Mostrar instrucciones
        const isFirefox = navigator.userAgent.toLowerCase().includes('firefox');
        
        if (isFirefox) {
            // Para Firefox: crear un enlace visible para que el usuario haga clic derecho
            const url = URL.createObjectURL(blob);
            
            swalDark.fire({
                icon: 'info',
                title: '<i class="fas fa-download me-2"></i>Descargar archivo',
                html: `
                    <div class="text-start">
                        <p><strong>${facturasData.length}</strong> factura(s) lista(s) para descargar</p>
                        <p><small class="text-muted">Archivo: ${nombreArchivo}</small></p>
                        <div class="alert alert-info mt-3 py-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Firefox:</strong> Haz clic en el botón para descargar. 
                            El archivo se guardará en tu carpeta de descargas predeterminada debido a que Firefox no soporta diálogo de Guardar Como por razones de seguridad
                        </div>
                        <p class="mt-2 mb-0">
                            <small class="text-warning">
                                <i class="fas fa-lightbulb me-1"></i>
                                Para cambiar la carpeta, ve a Opciones de Firefox > General > Descargas
                            </small>
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i>Descargar ahora',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                width: '550px'
            }).then((result) => {
                if (result.isConfirmed) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = nombreArchivo;
                    a.click();
                    URL.revokeObjectURL(url);
                    
                    swalDark.fire({ 
                        icon: 'success', 
                        title: '<i class="fas fa-check-circle me-2"></i>Descargado', 
                        text: 'El archivo se guardó en tu carpeta de descargas.',
						confirmButtonText: '<i class="fas fa-check me-2"></i>Ok, Gracias'
                    });
                }
            });
        } else {
            // Otros navegadores
            swalDark.fire({
                icon: 'info',
                title: '<i class="fas fa-download me-2"></i>¿Descargar archivo?',
                text: 'El archivo se guardará en tu carpeta de descargas predeterminada.',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i>Sí, descargar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    descargarTradicional(blob, nombreArchivo);
                }
            });
        }
    }
}

// Función auxiliar para descarga tradicional
function descargarTradicional(blob, nombreArchivo) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    swalDark.fire({ 
        icon: 'success', 
        title: '<i class="fas fa-save me-2"></i>Progreso guardado', 
        html: `
            <div class="text-start">
                <p><strong>${facturasData.length}</strong> factura(s) guardada(s)</p>
                <p><small class="text-muted">Archivo descargado en carpeta de descargas</small></p>
            </div>
        `,
        timer: 2500, 
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
    });
}
        
		
        
function limpiarTodo() {
    if (facturasData.length === 0) { 
        swalDark.fire({ 
            icon: 'info', 
            title: '<i class="fas fa-info-circle me-2"></i>Sin facturas', 
            text: 'No hay facturas para limpiar',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        }); 
        return; 
    }
    
    const totalFacturas = facturasData.length;
    const totalImporte = calcularTotalGeneralFacturas().toFixed(2);
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Limpiar todo?', 
        html: `
            <div class="text-start">
                <p class="mb-3">Se eliminará todo el contenido del capturador:</p>
                <div class="alert alert-danger py-2 px-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-file-invoice me-2"></i>Facturas a eliminar:</span>
                        <strong class="fs-5">${totalFacturas}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-dollar-sign me-2"></i>Importe total:</span>
                        <strong>${formatearMoneda(totalImporte)}</strong>
                    </div>
                </div>
                <p class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Esta acción no se puede deshacer.</p>
            </div>
        `, 
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545', 
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, limpiar todo', 
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        customClass: {
            popup: 'rounded-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Guardar los valores antes de limpiar para el mensaje
            const facturasEliminadas = facturasData.length;
            const importeEliminado = calcularTotalGeneralFacturas().toFixed(2);
            
            // Limpiar datos
            facturasData = []; 
            facturaCounter = 0; 
            localStorage.removeItem('facturas_capturadas');
            document.getElementById('sql-preview').value = '';
            
            // Actualizar interfaz
            renderizarFacturas(); 
            actualizarContador();
            actualizarTotalImporte();
            
            // Mensaje de confirmación
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>¡Todo limpiado!', 
                html: `
                    <div class="text-start">
                        <p class="mb-2">Se han eliminado correctamente:</p>
                        <div class="alert alert-success py-2 px-3 mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="fas fa-file-invoice me-2"></i>Facturas:</span>
                                <strong>${facturasEliminadas}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-dollar-sign me-2"></i>Importe total:</span>
                                <span class="text-success"><strong>${formatearMoneda(importeEliminado)}</strong></span>
                            </div>
                        </div>
                    </div>
                `,
                timer: 2500, 
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
        }
    });
}

// Actualizar limpiarTodo para reiniciar el contador
const limpiarTodoOriginal = limpiarTodo;
limpiarTodo = function() {
    if (facturasData.length === 0) { 
        swalDark.fire({ 
            icon: 'info', 
            title: '<i class="fas fa-info-circle me-2"></i>Sin facturas', 
            text: 'No hay facturas para limpiar',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        }); 
        return; 
    }
    
    const totalFacturas = facturasData.length;
    const totalImporte = calcularTotalGeneralFacturas().toFixed(2);
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Limpiar todo?', 
        html: `
            <div class="text-start">
                <p class="mb-3">Se eliminará todo el contenido del capturador:</p>
                <div class="alert alert-danger py-2 px-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-file-invoice me-2"></i>Facturas a eliminar:</span>
                        <strong class="fs-5">${totalFacturas}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-dollar-sign me-2"></i>Importe total:</span>
                        <strong>${formatearMoneda(totalImporte)}</strong>
                    </div>
                </div>
                <p class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Esta acción no se puede deshacer.</p>
            </div>
        `, 
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545', 
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, limpiar todo', 
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        customClass: {
            popup: 'rounded-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const facturasEliminadas = facturasData.length;
            const importeEliminado = calcularTotalGeneralFacturas().toFixed(2);
            
            // Limpiar datos
            facturasData = []; 
            facturaCounter = 0; 
            localStorage.removeItem('facturas_capturadas');
            document.getElementById('sql-preview').value = '';
            
            // Actualizar interfaz
            renderizarFacturas(); 
            actualizarContador();
            actualizarTotalImporte();
            
            // Actualizar dropdown
            actualizarBadgeFacturas();
            if (typeof dropdownOpen !== 'undefined' && dropdownOpen) {
                actualizarDropdownFacturas();
            }
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>¡Todo limpiado!', 
                html: `
                    <div class="text-start">
                        <p class="mb-2">Se han eliminado correctamente:</p>
                        <div class="alert alert-success py-2 px-3 mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="fas fa-file-invoice me-2"></i>Facturas:</span>
                                <strong>${facturasEliminadas}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-dollar-sign me-2"></i>Importe total:</span>
                                <span class="text-success"><strong>${formatearMoneda(importeEliminado)}</strong></span>
                            </div>
                        </div>
                    </div>
                `,
                timer: 2500, 
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
        }
    });
};
        
        // ============================================
        // UTILIDADES
        // ============================================
        function actualizarContador() { 
			document.getElementById('contador-facturas').textContent = facturasData.length;
			actualizarTotalImporte();
		}
        function actualizarEstadoConexion(conectado) {
            const badge = document.getElementById('status-badge');
            badge.className = conectado ? 'badge bg-success' : 'badge bg-danger';
            badge.style.padding = '8px 15px';
            badge.innerHTML = conectado ? '<i class="fas fa-check-circle me-1"></i> Conectado a BD Local' : '<i class="fas fa-triangle-exclamation me-1"></i> Error de conexión';
        }
        function showLoading(show) { document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none'; }
        async function recargarDatos() {
            showLoading(true);
            try {
                await cargarDatosMaestros();
                actualizarDatalistClientes();
                actualizarEstadoConexion(true);
                renderizarFacturas();
                swalDark.fire({ icon: 'success', title: '<i class="fas fa-sync-alt me-2"></i>Los Datos de los Clientes y demás se han actualizados', timer: 1500, showConfirmButton: false });
            } catch(e) {
                actualizarEstadoConexion(false);
                swalDark.fire({ icon: 'error', title: '<i class="fas fa-triangle-exclamation me-2"></i>Error', text: 'No se pudo conectar' });
            } finally { showLoading(false); }
        }
        
        // ============================================
        // SIDEBAR Y TEMA
        // ============================================
        let themePanelOpen = false;
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar'), main = document.querySelector('.win-main-content');
            if (window.innerWidth < 992) sidebar.classList.toggle('open');
            else { sidebar.classList.toggle('mini'); main.classList.toggle('sidebar-mini'); }
        }
        function abrirPanelTemas() { document.getElementById('themePanel').classList.add('open'); document.getElementById('themeOverlay').classList.add('open'); themePanelOpen = true; }
        function cerrarPanelTemas() { document.getElementById('themePanel').classList.remove('open'); document.getElementById('themeOverlay').classList.remove('open'); themePanelOpen = false; }
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && themePanelOpen) cerrarPanelTemas(); });
        document.querySelectorAll('.win-theme-option').forEach(o => o.addEventListener('click', function() {
            document.querySelectorAll('.win-theme-option').forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            document.documentElement.setAttribute('data-theme', this.dataset.theme);
        }));
        document.querySelectorAll('.win-color-option').forEach(o => o.addEventListener('click', function() {
            document.querySelectorAll('.win-color-option').forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            const c = this.dataset.color;
            document.documentElement.style.setProperty('--win-accent', c);
            document.documentElement.style.setProperty('--win-accent-light', c + '20');
        }));
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const mini = document.getElementById('toggleSidebarMini').checked;
            fetch('guardar_configuracion.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({tema_windows: tema, color_accent: color, sidebar_mini: mini}) })
            .then(r => r.json()).then(d => { if (d.success) { Swal.fire({icon:'success', title:'Guardado', timer:1500, showConfirmButton:false}); setTimeout(() => location.reload(), 1500); } });
            cerrarPanelTemas();
        }
        window.addEventListener('resize', () => { if (window.innerWidth >= 992) document.getElementById('sidebar').classList.remove('open'); });
        setInterval(() => { if (facturasData.length > 0) guardarEnLocalStorage(); }, 30000);
		
// ============================================
// FUNCIONES DE CÁLCULO
// ============================================
function calcularTotalGeneralFacturas() {
    let total = 0;
    facturasData.forEach(factura => {
        total += factura.total_general || 0;
    });
    return total;
}

function actualizarTotalImporte() {
    const totalImporte = calcularTotalGeneralFacturas();
    const importeElement = document.getElementById('importe-total-facturas');
    if (importeElement) {
        // Formato moneda con separadores de miles y 2 decimales
        importeElement.textContent = '$' + totalImporte.toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}
// ============================================
// VARIABLES PARA EXPANDIR/COLAPSAR
// ============================================
let todasExpandidas = true; // Por defecto todas expandidas

// ============================================
// FUNCIONES PARA EXPANDIR/COLAPSAR FACTURAS
// ============================================
function toggleFactura(facturaId) {
    const card = document.getElementById(`factura-card-${facturaId}`);
    const chevron = document.getElementById(`chevron-${facturaId}`);
    
    if (card && chevron) {
        card.classList.toggle('collapsed');
        chevron.classList.toggle('expanded');
    }
    
    // Actualizar estado del botón "Todas"
    actualizarEstadoBotonToggleAll();
}

function expandirFactura(facturaId) {
    const card = document.getElementById(`factura-card-${facturaId}`);
    const chevron = document.getElementById(`chevron-${facturaId}`);
    
    if (card && chevron) {
        card.classList.remove('collapsed');
        chevron.classList.add('expanded');
    }
}

function colapsarFactura(facturaId) {
    const card = document.getElementById(`factura-card-${facturaId}`);
    const chevron = document.getElementById(`chevron-${facturaId}`);
    
    if (card && chevron) {
        card.classList.add('collapsed');
        chevron.classList.remove('expanded');
    }
}

function toggleAllFacturas() {
    const cards = document.querySelectorAll('.factura-card');
    const chevrons = document.querySelectorAll('.chevron-icon');
    
    if (todasExpandidas) {
        // Colapsar todas
        cards.forEach(card => card.classList.add('collapsed'));
        chevrons.forEach(chevron => chevron.classList.remove('expanded'));
        todasExpandidas = false;
    } else {
        // Expandir todas
        cards.forEach(card => card.classList.remove('collapsed'));
        chevrons.forEach(chevron => chevron.classList.add('expanded'));
        todasExpandidas = true;
    }
    
    actualizarBotonToggleAll();
}

function actualizarBotonToggleAll() {
    const btnIcon = document.getElementById('toggleAllIcon');
    const btnText = document.getElementById('toggleAllText');
    
    if (todasExpandidas) {
        btnIcon.className = 'fas fa-chevron-down';
        btnText.textContent = 'Colapsar';
    } else {
        btnIcon.className = 'fas fa-chevron-right';
        btnText.textContent = 'Expandir';
    }
}

function actualizarEstadoBotonToggleAll() {
    const cards = document.querySelectorAll('.factura-card');
    const collapsedCards = document.querySelectorAll('.factura-card.collapsed');
    
    // Si todas están colapsadas
    if (collapsedCards.length === cards.length && cards.length > 0) {
        todasExpandidas = false;
    } 
    // Si todas están expandidas
    else if (collapsedCards.length === 0 && cards.length > 0) {
        todasExpandidas = true;
    }
    
    actualizarBotonToggleAll();
}

function expandirTodas() {
    const cards = document.querySelectorAll('.factura-card');
    const chevrons = document.querySelectorAll('.chevron-icon');
    
    cards.forEach(card => card.classList.remove('collapsed'));
    chevrons.forEach(chevron => chevron.classList.add('expanded'));
    
    todasExpandidas = true;
    actualizarBotonToggleAll();
}

function colapsarTodas() {
    const cards = document.querySelectorAll('.factura-card');
    const chevrons = document.querySelectorAll('.chevron-icon');
    
    cards.forEach(card => card.classList.add('collapsed'));
    chevrons.forEach(chevron => chevron.classList.remove('expanded'));
    
    todasExpandidas = false;
    actualizarBotonToggleAll();
}
// ============================================
// SINCRONIZACIÓN CON QUICK ACTION
// ============================================

// Función para sincronizar el estado del botón expandir/colapsar del Quick Action
function sincronizarBotonToggleQuick() {
    const cards = document.querySelectorAll('.factura-card');
    const collapsedCards = document.querySelectorAll('.factura-card.collapsed');
    const btnIcon = document.getElementById('quickActionToggleIcon');
    const btn = document.getElementById('quickActionToggleAll');
    
    if (!btnIcon || !btn) return;
    
    if (collapsedCards.length === cards.length && cards.length > 0) {
        // Todas colapsadas
        quickToggleExpandido = false;
        btnIcon.className = 'fas fa-chevron-right';
        btn.setAttribute('data-tooltip', 'Expandir todas');
    } else {
        // Al menos una expandida
        quickToggleExpandido = true;
        btnIcon.className = 'fas fa-chevron-down';
        btn.setAttribute('data-tooltip', 'Colapsar todas');
    }
}

// Sobrescribir la función actualizarEstadoBotonToggleAll para que también sincronice
const originalActualizarEstadoBotonToggleAll = actualizarEstadoBotonToggleAll;
actualizarEstadoBotonToggleAll = function() {
    if (originalActualizarEstadoBotonToggleAll) {
        originalActualizarEstadoBotonToggleAll();
    }
    sincronizarBotonToggleQuick();
};

// Sobrescribir la función toggleAllFacturas para que también sincronice
const originalToggleAllFacturas = toggleAllFacturas;
toggleAllFacturas = function() {
    if (originalToggleAllFacturas) {
        originalToggleAllFacturas();
    }
    sincronizarBotonToggleQuick();
};
// ============================================
// FUNCIÓN AUXILIAR PARA FORMATEAR MONEDA
// ============================================
function formatearMoneda(valor) {
    return '$' + parseFloat(valor).toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Luego úsala en actualizarTotalImporte()
function actualizarTotalImporte() {
    const totalImporte = calcularTotalGeneralFacturas();
    const importeElement = document.getElementById('importe-total-facturas');
    if (importeElement) {
        importeElement.textContent = formatearMoneda(totalImporte);
    }
}
// ============================================
// FUNCIONES DE OPCIONES (IMPRIMIR / EXPORTAR)
// UTILIZANDO LIBRERÍAS PROFESIONALES
// ============================================

// Destructurar jsPDF y autoTable
const { jsPDF } = window.jspdf;

function imprimirFacturas() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para imprimir.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const horaActual = new Date().toLocaleTimeString('es-ES');
    const totalGeneral = calcularTotalGeneralFacturas();
    const periodoOperativo = obtenerPeriodoOperativo();
    const mesAnioOperativo = obtenerMesAnioOperativo();
    
    let contenido = '';
    contenido += '<!DOCTYPE html>';
    contenido += '<html>';
    contenido += '<head>';
    contenido += '<meta charset="UTF-8">';
    contenido += '<title>Facturas - PDL Visiones</title>';
    contenido += '<style>';
    contenido += '* { margin: 0; padding: 0; box-sizing: border-box; }';
    contenido += 'body { font-family: "Segoe UI", Arial, sans-serif; background: #fff; padding: 15px; }';
    
    // Estilos para la portada/resumen
    contenido += '.portada { margin-bottom: 30px; page-break-after: always; }';
    contenido += '.portada-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #0078d4; }';
    contenido += '.portada-header img { width: 50px; height: 50px; }';
    contenido += '.portada-header h1 { color: #0078d4; font-size: 24px; margin-bottom: 5px; }';
    contenido += '.portada-header h3 { color: #666; font-size: 14px; font-weight: normal; }';
    contenido += '.portada-info { text-align: center; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccc; }';
    contenido += '.portada-info p { font-size: 13px; margin: 3px 0; }';
    contenido += '.portada-titulo { text-align: center; font-size: 16px; font-weight: bold; margin: 20px 0 15px; color: #333; }';
    
    // Tabla de resumen
    contenido += '.tabla-resumen { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }';
    contenido += '.tabla-resumen th { background: #fff; color: #000; padding: 10px; border: 1px solid #000; border-bottom: 2px solid #000; font-weight: bold; text-align: left; }';
    contenido += '.tabla-resumen td { padding: 8px 10px; border: 1px solid #000; }';
    contenido += '.tabla-resumen .text-right { text-align: right; }';
    contenido += '.tabla-resumen .text-center { text-align: center; }';
    contenido += '.tabla-resumen .total-row { background: #fff; font-weight: bold; border-top: 2px solid #000; }';
    contenido += '.tabla-resumen .total-row td { border-top: 2px solid #999; }';
    
    // Estilos para facturas
    contenido += '.factura-print { border: 1px solid #000; margin-bottom: 25px; padding: 20px; page-break-inside: avoid; page-break-after: always; background: #fff; }';
    contenido += '.factura-print:last-child { page-break-after: auto; }';
    contenido += '.factura-header-print { background: #fff; color: #000; padding: 12px 20px; margin: -20px -20px 20px -20px; border-bottom: 2px solid #000; }';
    contenido += '.factura-header-print h3 { margin: 0; font-size: 16px; }';
    contenido += '.factura-footer-print { background: #fff; padding: 15px; margin: 20px -20px -20px -20px; border-top: 2px solid #000; }';
    contenido += '.cliente-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px; background: #fff; padding: 15px; border: 1px solid #ccc; font-size: 12px; }';
    contenido += '.table-print { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }';
    contenido += '.table-print th { background: #fff; color: #000; padding: 10px; font-weight: 600; text-align: left; border-bottom: 2px solid #000; }';
contenido += '.table-print td { padding: 8px; border: 1px solid #000; }';
    contenido += '.table-print .text-right { text-align: right; }';
    contenido += '.table-print .text-center { text-align: center; }';
    contenido += '.total-print { color: #000; font-size: 1.2em; font-weight: bold; }';
    contenido += '.badge-estado { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; }';
    contenido += '.badge-pendiente { background: #ffc107; color: #000; }';
    contenido += '.badge-success { background: #28a745; color: #fff; }';
    contenido += '.no-print-buttons { margin-bottom: 20px; display: flex; gap: 10px; justify-content: flex-end; }';
    contenido += '.pagina-numero { text-align: center; font-size: 12px; color: #999; margin-top: 10px; }';
    
    // Estilos de impresión
    contenido += '@media print {';
    contenido += '  body { padding: 5mm; background: white; }';
    contenido += '  .no-print-buttons { display: none !important; }';
    contenido += '  .factura-print { box-shadow: none; border: 1px solid #ccc; }';
    contenido += '  .badge-pendiente { background: #ffc107 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
    contenido += '  .badge-success { background: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
    contenido += '}';
    contenido += '</style>';
    contenido += '</head>';
    contenido += '<body>';
    
    // Botones (no se imprimen)
    contenido += '<div class="no-print-buttons">';
    contenido += '<button onclick="window.print()" style="padding: 10px 25px; background: #0078d4; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">🖨️ Imprimir ahora</button>';
    contenido += '<button onclick="window.close()" style="padding: 10px 25px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">❌ Cerrar</button>';
    contenido += '</div>';
    
    // ============================================
    // PORTADA - RESUMEN DE FACTURACIÓN
    // ============================================
    contenido += '<div class="portada">';
    
    // Header con logo
    contenido += '<div class="portada-header">';
    contenido += '<img src="assets/logov.png" alt="Logo" onerror="this.style.display=\'none\'">';
    contenido += '<div>';
    contenido += '<h1>PDL Visiones</h1>';
    contenido += '<h3>Capturador de Facturas - SISFACT</h3>';
    contenido += '</div>';
    contenido += '</div>';
    
    // Información general
    contenido += '<div class="portada-info">';
    contenido += '<p><strong>Fecha de exportación:</strong> ' + fechaActual + ' ' + horaActual + ' | <strong>Total de facturas:</strong> ' + facturasData.length + ' | <strong>Importe total general:</strong> <strong>' + formatearMoneda(totalGeneral) + '</strong></p>';
    if (periodoOperativo !== 'No configurado') {
        contenido += '<p><strong>Período operativo:</strong> ' + periodoOperativo + ' (' + mesAnioOperativo + ')</p>';
    }
    contenido += '</div>';
    
    // Título de resumen
    contenido += '<div class="portada-titulo">RESUMEN DE FACTURACIÓN</div>';
    
    // Tabla de resumen
    contenido += '<table class="tabla-resumen">';
    contenido += '<thead><tr>';
    contenido += '<th style="width: 15%; text-align: center;">Factura #</th>';
    contenido += '<th style="width: 60%;">Cliente</th>';
    contenido += '<th style="width: 25%; text-align: right;">Total</th>';
    contenido += '</tr></thead>';
    contenido += '<tbody>';
    
    facturasData.forEach(factura => {
        const clienteInfo = factura.cliente_info || {};
        let nombreCliente = clienteInfo.nombre || 'Sin cliente';
        if (nombreCliente.length > 50) {
            nombreCliente = nombreCliente.substring(0, 47) + '...';
        }
        contenido += '<tr>';
        contenido += '<td class="text-center">' + factura.id + '</td>';
        contenido += '<td>' + nombreCliente + '</td>';
        contenido += '<td class="text-right" style="font-weight: bold; font-size: 1.3em;">' + formatearMoneda(factura.total_general) + '</td>';
        contenido += '</tr>';
    });
    
    // Fila de TOTAL GENERAL
    contenido += '<tr class="total-row">';
contenido += '<td colspan="2" class="text-right" style="font-weight: bold;">TOTAL GENERAL:</td>';
contenido += '<td class="text-right" style="font-weight: bold; font-size: 1.9em;">' + formatearMoneda(totalGeneral) + '</td>';
    contenido += '</tr>';
    
    contenido += '</tbody>';
    contenido += '</table>';
    
    contenido += '<div class="pagina-numero">Página 1 de ' + (facturasData.length + 1) + '</div>';
    contenido += '</div>'; // Fin portada
    
    // ============================================
    // FACTURAS INDIVIDUALES
    // ============================================
    facturasData.forEach((factura, index) => {
        const clienteInfo = factura.cliente_info || {};
        const estadoClass = factura.estado === 'PENDIENTE' ? 'badge-pendiente' : 'badge-success';
        
        contenido += '<div class="factura-print">';
        
// Encabezado de factura
contenido += '<div class="factura-header-print">';
contenido += '<div style="display: flex; justify-content: space-between; align-items: center;">';
contenido += '<h3>Factura #' + factura.id + ' - ' + (clienteInfo.nombre || 'Sin cliente') + '</h3>';
contenido += '<span style="font-size: 14px; font-weight: normal;">Estado: <strong>' + factura.estado + '</strong></span>';
contenido += '</div>';
contenido += '</div>';
        
        // Información del cliente
        contenido += '<div class="cliente-grid">';
        contenido += '<div><strong>Código:</strong> ' + (clienteInfo.codigo || '--') + '</div>';
        contenido += '<div><strong>Contrato:</strong> ' + (clienteInfo.ContratoNo || 'S/C') + '</div>';
        contenido += '<div><strong>Teléfono:</strong> ' + (clienteInfo.telefono || '--') + '</div>';
        contenido += '<div><strong>Email:</strong> ' + (clienteInfo.email || '--') + '</div>';
        contenido += '<div><strong>Fecha Emisión:</strong> ' + factura.fecha_emision + '</div>';
        contenido += '<div><strong>Tipo Pago:</strong> ' + (factura.tipo_pago_info?.descripcion || 'Transferencia') + '</div>';
        if (periodoOperativo !== 'No configurado') {
            contenido += '<div><strong>Período Operativo:</strong> ' + periodoOperativo + '</div>';
        }
        contenido += '</div>';
        
        // Tabla de servicios
        contenido += '<table class="table-print">';
        contenido += '<thead><tr>';
        contenido += '<th style="width: 10%; text-align: center;">#</th>';
        contenido += '<th style="width: 45%;">Servicio</th>';
        contenido += '<th style="width: 12%; text-align: center;">Cant.</th>';
        contenido += '<th style="width: 16%; text-align: right;">P. Unit.</th>';
        contenido += '<th style="width: 17%; text-align: right;">Total</th>';
        contenido += '</tr></thead>';
        contenido += '<tbody>';
        
        if (factura.detalles.length === 0) {
            contenido += '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No hay servicios registrados</td></tr>';
        } else {
            factura.detalles.forEach((servicio, sIndex) => {
                let descripcion = servicio.servicio_info?.descripcion || 'Servicio';
                if (descripcion.length > 35) {
                    descripcion = descripcion.substring(0, 32) + '...';
                }
                contenido += '<tr>';
                contenido += '<td class="text-center">' + (sIndex + 1) + '</td>';
                contenido += '<td>' + descripcion;
                if (servicio.servicio_info?.codigo) {
                    contenido += ' <small style="color: #666;">(' + servicio.servicio_info.codigo + ')</small>';
                }
                contenido += '</td>';
                contenido += '<td class="text-center">' + servicio.cantidad + '</td>';
                contenido += '<td class="text-right">' + formatearMoneda(servicio.precio_unitario) + '</td>';
                contenido += '<td class="text-right">' + formatearMoneda(servicio.total_linea) + '</td>';
                contenido += '</tr>';
            });
        }
        
        contenido += '</tbody>';
        contenido += '</table>';
        
        // Footer con totales
        contenido += '<div class="factura-footer-print">';
        contenido += '<div style="display: flex; justify-content: flex-end; gap: 30px;">';
        contenido += '<div style="text-align: right;"><span style="color: #666;">Subtotal:</span> <span style="font-size: 1.1em;">' + formatearMoneda(factura.subtotal) + '</span></div>';
        contenido += '<div style="text-align: right;"><span style="color: #666;">Total General:</span> <span style="font-weight: bold; font-size: 1.1em;">' + formatearMoneda(factura.total_general) + '</span></div>';
        contenido += '</div>';
        
        // Observaciones
        if (factura.observaciones) {
            contenido += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">';
            contenido += '<strong>Observaciones:</strong><br>';
            contenido += '<span style="color: #666;">' + factura.observaciones + '</span>';
            contenido += '</div>';
        }
        contenido += '</div>';
        
        // Número de página
        contenido += '<div class="pagina-numero">Página ' + (index + 2) + ' de ' + (facturasData.length + 1) + '</div>';
        
        contenido += '</div>'; // Fin factura-print
    });
    
    contenido += '</body>';
    contenido += '</html>';
    
const ventana = window.open('', '_blank');
ventana.document.write(contenido);
ventana.document.close();

// Detectar cuando se cierra el diálogo de impresión
ventana.onafterprint = function() {
    ventana.close();
};

// Detectar cuando se presiona Cancelar en el diálogo de impresión
ventana.onbeforeprint = function() {
    // Se ejecuta antes de imprimir
};

// Abrir diálogo de impresión automáticamente
ventana.onload = function() {
    setTimeout(() => {
        ventana.focus();
        ventana.print();
        
        // Fallback: si después de 2 segundos no se ha impreso, cerrar
        setTimeout(() => {
            if (ventana && !ventana.closed) {
                ventana.close();
            }
        }, 1000);
    }, 300);
};
}


function exportarPDF() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para exportar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const totalGeneral = calcularTotalGeneralFacturas();
    const periodoOperativo = obtenerPeriodoOperativo();
    const mesAnioOperativo = obtenerMesAnioOperativo();
    
    // Crear UN SOLO documento PDF tamaño CARTA
    const doc = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'letter'
    });
    
    // Logo en el header
    const logoImg = new Image();
    logoImg.src = 'assets/logov.png';
    
    logoImg.onload = function() {
        doc.addImage(logoImg, 'PNG', 15, 10, 20, 20);
        generarContenidoPDF(doc, fechaActual, totalGeneral, periodoOperativo, mesAnioOperativo);
    };
    
    logoImg.onerror = function() {
        generarContenidoPDF(doc, fechaActual, totalGeneral, periodoOperativo, mesAnioOperativo);
    };
    
    setTimeout(() => {
        if (doc.getNumberOfPages() === 0) {
            generarContenidoPDF(doc, fechaActual, totalGeneral, periodoOperativo, mesAnioOperativo);
        }
    }, 500);
}

function generarContenidoPDF(doc, fechaActual, totalGeneral, periodoOperativo, mesAnioOperativo) {
    let yPos = 10;
    
    // ============================================
    // HEADER CON LOGO Y TÍTULO
    // ============================================
    
    doc.setFontSize(22);
    doc.setTextColor(0, 120, 212);
    doc.text('PDL Visiones', 40, 20);
    
    doc.setFontSize(14);
    doc.setTextColor(100, 100, 100);
    doc.text('Capturador de Facturas - SISFACT', 40, 28);
    
    // Línea separadora
    yPos = 35;
    doc.setDrawColor(200, 200, 200);
    doc.line(10, yPos, 205, yPos);
    
    yPos = 42;
    
    // ============================================
    // INFORMACIÓN EN UNA SOLA LÍNEA
    // ============================================
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    
    const infoLinea1 = `Fecha de exportación: ${fechaActual}    |    Total de facturas: ${facturasData.length}    |    Importe total general: ${formatearMoneda(totalGeneral)}`;
    doc.text(infoLinea1, 107, yPos, { align: 'center' });
    
    yPos += 5;
    
    if (periodoOperativo !== 'No configurado') {
        doc.setFontSize(10);
        const infoLinea2 = `Período operativo: ${periodoOperativo} (${mesAnioOperativo})`;
        doc.text(infoLinea2, 107, yPos, { align: 'center' });
        yPos += 5;
    }
    
    yPos += 3;
    
    doc.setDrawColor(200, 200, 200);
    doc.line(10, yPos, 205, yPos);
    
    yPos += 8;
    
    // ============================================
    // TABLA RESUMEN - ANCHOS CORREGIDOS
    // ============================================
    doc.setFontSize(12);
    doc.setTextColor(0, 0, 0);
    doc.text('RESUMEN DE FACTURACIÓN', 107, yPos, { align: 'center' });
    
    yPos += 6;
    
    const tableData = facturasData.map(f => {
        const clienteInfo = f.cliente_info || {};
let nombreCliente = clienteInfo.nombre || 'Sin cliente'
        return [
            f.id.toString(),
            nombreCliente,
            formatearMoneda(f.total_general)
        ];
    });
    
    tableData.push([
        { content: 'TOTAL GENERAL:', colSpan: 2, styles: { halign: 'right', fontStyle: 'bold' } },
        { content: formatearMoneda(totalGeneral), styles: { halign: 'right', fontStyle: 'bold' } }
    ]);
    
    doc.autoTable({
        startY: yPos,
        head: [['Fact.', 'Cliente', 'Total']],
        body: tableData,
        theme: 'plain',
        headStyles: {
            fillColor: [240, 240, 240],
            textColor: 0,
            fontStyle: 'bold',
            lineWidth: 0.3,
            lineColor: [0, 0, 0],
            fontSize: 10
        },
        styles: {
            fontSize: 9,
            cellPadding: 2,
            lineWidth: 0.3,
            lineColor: [0, 0, 0],
            textColor: 0
        },
        columnStyles: {
            0: { cellWidth: 25, halign: 'center' },
            1: { cellWidth: 110 },
            2: { cellWidth: 40, halign: 'right' }
        },
        margin: { left: 15, right: 15 },
        tableWidth: 175
    });
    
    // ============================================
    // PÁGINAS POR FACTURA
    // ============================================
    facturasData.forEach((factura, index) => {
        doc.addPage();
        
        const clienteInfo = factura.cliente_info || {};
        let pageY = 15;
        
        // Encabezado de factura
        doc.setFillColor(240, 240, 240);
        doc.rect(10, pageY, 195, 10, 'F');
        
        doc.setFontSize(13);
        doc.setTextColor(0, 0, 0);
        
// SIMPLEMENTE ELIMINAR EL TRUNCADO
let nombreEncabezado = clienteInfo.nombre || 'Sin cliente';
doc.setFontSize(11); // Fuente un poco más pequeña para que quepa
doc.text(`Factura #${factura.id} - ${nombreEncabezado}`, 15, pageY + 7);
doc.setFontSize(13); // Restaurar
        
        pageY += 12;
        
        // Información del cliente
        doc.setFontSize(9);
        doc.text(`Cod: ${clienteInfo.codigo || '--'}`, 15, pageY);
        doc.text(`Contrato: ${clienteInfo.ContratoNo || 'S/C'}`, 90, pageY);
        
        pageY += 5;
        doc.text(`Tel: ${clienteInfo.telefono || '--'}`, 15, pageY);
        doc.text(`Email: ${clienteInfo.email || '--'}`, 90, pageY);
        
        pageY += 5;
        doc.text(`Fecha: ${factura.fecha_emision}`, 15, pageY);
        doc.text(`Pago: ${factura.tipo_pago_info?.descripcion || 'Transferencia'}`, 90, pageY);
        
        pageY += 5;
        doc.text(`Estado: ${factura.estado}`, 15, pageY);
        
        if (periodoOperativo !== 'No configurado') {
            pageY += 5;
            doc.text(`Período: ${periodoOperativo}`, 15, pageY);
        }
        
        pageY += 8;
        
        // Tabla de detalles - ANCHOS CORREGIDOS
        const detallesData = factura.detalles.map((servicio, sIndex) => {
            let descripcion = servicio.servicio_info?.descripcion || 'Servicio';
            if (descripcion.length > 30) {
                descripcion = descripcion.substring(0, 27) + '...';
            }
            return [
                (sIndex + 1).toString(),
                descripcion,
                servicio.cantidad.toString(),
                formatearMoneda(servicio.precio_unitario),
                formatearMoneda(servicio.total_linea)
            ];
        });
        
        if (detallesData.length === 0) {
            detallesData.push(['-', 'No hay servicios', '-', '-', '-']);
        }
        
        doc.autoTable({
            startY: pageY,
            head: [['#', 'Servicio', 'Cant', 'P.Unit', 'Total']],
            body: detallesData,
            theme: 'plain',
            headStyles: {
                fillColor: [240, 240, 240],
                textColor: 0,
                fontStyle: 'bold',
                lineWidth: 0.3,
                lineColor: [0, 0, 0],
                fontSize: 9
            },
            styles: {
                fontSize: 8,
                cellPadding: 2,
                lineWidth: 0.3,
                lineColor: [0, 0, 0],
                textColor: 0
            },
            columnStyles: {
                0: { cellWidth: 12, halign: 'center' },
                1: { cellWidth: 73 },
                2: { cellWidth: 18, halign: 'center' },
                3: { cellWidth: 33, halign: 'right' },
                4: { cellWidth: 34, halign: 'right' }
            },
            margin: { left: 10, right: 10 },
            tableWidth: 170
        });
        
        pageY = doc.lastAutoTable.finalY + 6;
        
        // Totales
        doc.setFontSize(10);
        doc.text(`Subtotal: ${formatearMoneda(factura.subtotal)}`, 160, pageY, { align: 'right' });
        
        pageY += 5;
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.text(`TOTAL: ${formatearMoneda(factura.total_general)}`, 160, pageY, { align: 'right' });
        doc.setFont('helvetica', 'normal');
        
        // Observaciones
        if (factura.observaciones) {
            pageY += 8;
            doc.setFontSize(8);
            doc.setTextColor(80, 80, 80);
            const obsTruncada = factura.observaciones.length > 70 
                ? factura.observaciones.substring(0, 67) + '...' 
                : factura.observaciones;
            doc.text(`Obs: ${obsTruncada}`, 15, pageY);
            doc.setTextColor(0, 0, 0);
        }
        
        // Pie de página
        doc.setFontSize(12);
        doc.setTextColor(100, 100, 100);
        doc.text(`Página ${index + 2} de ${facturasData.length + 1}`, 107, 272, { align: 'center' });
    });
    
    // Guardar el PDF
    doc.save(`Facturas_PDL_${new Date().toISOString().slice(0, 10)}.pdf`);
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-pdf me-2"></i>PDF Exportado',
        text: `${facturasData.length} factura(s) exportada(s) en un solo archivo PDF.`,
        timer: 2500,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
}


function exportarWORD() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para exportar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const horaActual = new Date().toLocaleTimeString('es-ES');
    const totalGeneral = calcularTotalGeneralFacturas();
    const periodoOperativo = obtenerPeriodoOperativo();
    const mesAnioOperativo = obtenerMesAnioOperativo();
    
    let contenido = '';
    
    // Encabezado HTML para Word
    contenido += '<!DOCTYPE html>';
    contenido += '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    contenido += '<head>';
    contenido += '<meta charset="UTF-8">';
    contenido += '<title>Facturas - PDL Visiones</title>';
    contenido += '<style>';
    contenido += 'body { font-family: Arial, sans-serif; margin: 20px; background: #fff; }';
    contenido += '.portada { margin-bottom: 30px; page-break-after: always; }';
    contenido += '.portada-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #0078d4; }';
    contenido += '.portada-header h1 { color: #0078d4; font-size: 24px; margin: 0 0 5px 0; }';
    contenido += '.portada-header h3 { color: #666; font-size: 14px; margin: 0; font-weight: normal; }';
    contenido += '.portada-info { text-align: center; margin: 20px 0; padding: 15px; border: 1px solid #ccc; }';
    contenido += '.portada-info p { font-size: 13px; margin: 3px 0; }';
    contenido += '.portada-titulo { text-align: center; font-size: 16px; font-weight: bold; margin: 20px 0 15px; color: #333; }';
    
    // Tabla de resumen
    contenido += '.tabla-resumen { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }';
    contenido += '.tabla-resumen th { background: #e9ecef; color: #000; padding: 10px; border: 1px solid #ccc; font-weight: bold; text-align: left; }';
    contenido += '.tabla-resumen td { padding: 8px 10px; border: 1px solid #ccc; }';
    contenido += '.tabla-resumen .text-right { text-align: right; }';
    contenido += '.tabla-resumen .text-center { text-align: center; }';
    contenido += '.tabla-resumen .total-row { background: #f8f9fa; font-weight: bold; }';
    contenido += '.tabla-resumen .total-row td { border-top: 2px solid #999; }';
    
    // Estilos para facturas
    contenido += '.factura-word { border: 1px solid #ccc; margin-bottom: 30px; padding: 20px; page-break-after: always; background: #fff; }';
    contenido += '.factura-word:last-child { page-break-after: auto; }';
    contenido += '.factura-header-word { background: #0078d4; color: white; padding: 12px 20px; margin: -20px -20px 20px -20px; }';
    contenido += '.factura-header-word h3 { margin: 0; font-size: 16px; }';
    contenido += '.factura-footer-word { background: #f8f9fa; padding: 15px; margin: 20px -20px -20px -20px; border-top: 2px solid #dee2e6; }';
    contenido += '.cliente-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px; background: #f8f9fa; padding: 15px; font-size: 12px; }';
    contenido += '.table-word { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }';
    contenido += '.table-word th { background: #0078d4; color: white; padding: 10px; font-weight: 600; text-align: left; }';
    contenido += '.table-word td { padding: 8px; border: 1px solid #ddd; }';
    contenido += '.table-word .text-right { text-align: right; }';
    contenido += '.table-word .text-center { text-align: center; }';
    contenido += '.total-word { color: #0078d4; font-size: 1.2em; font-weight: bold; }';
    contenido += '.badge-estado { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; }';
    contenido += '.badge-pendiente { background: #ffc107; color: #000; }';
    contenido += '.badge-success { background: #28a745; color: #fff; }';
    contenido += '.pagina-numero { text-align: center; font-size: 11px; color: #999; margin-top: 10px; }';
    contenido += '</style>';
    contenido += '</head>';
    contenido += '<body>';
    
    // ============================================
    // PORTADA - RESUMEN DE FACTURACIÓN
    // ============================================
    contenido += '<div class="portada">';
    
    // Header con logo
    contenido += '<div class="portada-header">';
    contenido += '<div>';
    contenido += '<h1>PDL Visiones</h1>';
    contenido += '<h3>Capturador de Facturas - SISFACT</h3>';
    contenido += '</div>';
    contenido += '</div>';
    
    // Información general
    contenido += '<div class="portada-info">';
    contenido += '<p><strong>Fecha de exportación:</strong> ' + fechaActual + ' ' + horaActual + ' | <strong>Total de facturas:</strong> ' + facturasData.length + ' | <strong>Importe total general:</strong> <strong>' + formatearMoneda(totalGeneral) + '</strong></p>';
    if (periodoOperativo !== 'No configurado') {
        contenido += '<p><strong>Período operativo:</strong> ' + periodoOperativo + ' (' + mesAnioOperativo + ')</p>';
    }
    contenido += '</div>';
    
    // Título de resumen
    contenido += '<div class="portada-titulo">RESUMEN DE FACTURACIÓN</div>';
    
    // Tabla de resumen
    contenido += '<table class="tabla-resumen">';
    contenido += '<thead><tr>';
    contenido += '<th style="width: 15%; text-align: center;">Factura #</th>';
    contenido += '<th style="width: 60%;">Cliente</th>';
    contenido += '<th style="width: 25%; text-align: right;">Total</th>';
    contenido += '</tr></thead>';
    contenido += '<tbody>';
    
    facturasData.forEach(factura => {
        const clienteInfo = factura.cliente_info || {};
        contenido += '<tr>';
        contenido += '<td class="text-center">' + factura.id + '</td>';
        contenido += '<td>' + (clienteInfo.nombre || 'Sin cliente') + '</td>';
        contenido += '<td class="text-right">' + formatearMoneda(factura.total_general) + '</td>';
        contenido += '</tr>';
    });
    
    // Fila de TOTAL GENERAL
    contenido += '<tr class="total-row">';
    contenido += '<td colspan="2" class="text-right" style="font-weight: bold;">TOTAL GENERAL:</td>';
    contenido += '<td class="text-right" style="font-weight: bold; font-size: 1.1em;">' + formatearMoneda(totalGeneral) + '</td>';
    contenido += '</tr>';
    
    contenido += '</tbody>';
    contenido += '</table>';
    
    contenido += '<div class="pagina-numero">Página 1 de ' + (facturasData.length + 1) + '</div>';
    contenido += '</div>'; // Fin portada
    
    // ============================================
    // FACTURAS INDIVIDUALES
    // ============================================
    facturasData.forEach((factura, index) => {
        const clienteInfo = factura.cliente_info || {};
        const estadoClass = factura.estado === 'PENDIENTE' ? 'badge-pendiente' : 'badge-success';
        
        contenido += '<div class="factura-word">';
        
        // Encabezado de factura
        contenido += '<div class="factura-header-word">';
        contenido += '<div style="display: flex; justify-content: space-between; align-items: center;">';
        contenido += '<h3>Factura #' + factura.id + ' - ' + (clienteInfo.nombre || 'Sin cliente') + '</h3>';
        contenido += '<span class="badge-estado ' + estadoClass + '">' + factura.estado + '</span>';
        contenido += '</div>';
        contenido += '</div>';
        
        // Información del cliente
        contenido += '<div class="cliente-grid">';
        contenido += '<div><strong>Código:</strong> ' + (clienteInfo.codigo || '--') + '</div>';
        contenido += '<div><strong>Contrato:</strong> ' + (clienteInfo.ContratoNo || 'S/C') + '</div>';
        contenido += '<div><strong>Teléfono:</strong> ' + (clienteInfo.telefono || '--') + '</div>';
        contenido += '<div><strong>Email:</strong> ' + (clienteInfo.email || '--') + '</div>';
        contenido += '<div><strong>Fecha Emisión:</strong> ' + factura.fecha_emision + '</div>';
        contenido += '<div><strong>Tipo Pago:</strong> ' + (factura.tipo_pago_info?.descripcion || 'Transferencia') + '</div>';
        if (periodoOperativo !== 'No configurado') {
            contenido += '<div><strong>Período Operativo:</strong> ' + periodoOperativo + '</div>';
        }
        contenido += '</div>';
        
        // Tabla de servicios
        contenido += '<table class="table-word">';
        contenido += '<thead><tr>';
        contenido += '<th style="width: 10%; text-align: center;">#</th>';
        contenido += '<th style="width: 45%;">Servicio</th>';
        contenido += '<th style="width: 12%; text-align: center;">Cant.</th>';
        contenido += '<th style="width: 16%; text-align: right;">P. Unit.</th>';
        contenido += '<th style="width: 17%; text-align: right;">Total</th>';
        contenido += '</tr></thead>';
        contenido += '<tbody>';
        
        if (factura.detalles.length === 0) {
            contenido += '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No hay servicios registrados</td></tr>';
        } else {
            factura.detalles.forEach((servicio, sIndex) => {
                contenido += '<tr>';
                contenido += '<td class="text-center">' + (sIndex + 1) + '</td>';
                contenido += '<td>' + (servicio.servicio_info?.descripcion || 'Servicio');
                if (servicio.servicio_info?.codigo) {
                    contenido += ' <small style="color: #666;">(' + servicio.servicio_info.codigo + ')</small>';
                }
                contenido += '</td>';
                contenido += '<td class="text-center">' + servicio.cantidad + '</td>';
                contenido += '<td class="text-right">' + formatearMoneda(servicio.precio_unitario) + '</td>';
                contenido += '<td class="text-right">' + formatearMoneda(servicio.total_linea) + '</td>';
                contenido += '</tr>';
            });
        }
        
        contenido += '</tbody>';
        contenido += '</table>';
        
        // Footer con totales
        contenido += '<div class="factura-footer-word">';
        contenido += '<div style="display: flex; justify-content: flex-end; gap: 30px;">';
        contenido += '<div style="text-align: right;"><span style="color: #666;">Subtotal:</span> <span style="font-size: 1.1em;">' + formatearMoneda(factura.subtotal) + '</span></div>';
        contenido += '<div style="text-align: right;"><span style="color: #666;">Total General:</span> <span class="total-word">' + formatearMoneda(factura.total_general) + '</span></div>';
        contenido += '</div>';
        
        // Observaciones
        if (factura.observaciones) {
            contenido += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">';
            contenido += '<strong>Observaciones:</strong><br>';
            contenido += '<span style="color: #666;">' + factura.observaciones + '</span>';
            contenido += '</div>';
        }
        contenido += '</div>';
        
        // Número de página
        contenido += '<div class="pagina-numero">Página ' + (index + 2) + ' de ' + (facturasData.length + 1) + '</div>';
        
        contenido += '</div>'; // Fin factura-word
    });
    
    contenido += '</body>';
    contenido += '</html>';
    
    // Crear blob y descargar como .doc
    const blob = new Blob(['\ufeff' + contenido], { 
        type: 'application/msword' 
    });
    
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Facturas_PDL_${new Date().toISOString().slice(0, 10)}.doc`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-word me-2"></i>Word Exportado',
        text: `${facturasData.length} factura(s) exportada(s) correctamente.`,
        timer: 2000,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
}

function exportarXLS() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para exportar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const wb = XLSX.utils.book_new();
    const totalGeneral = calcularTotalGeneralFacturas();
    
    // Hoja de resumen general
    const resumenData = [
        ['PDL VISIONES - CAPTURADOR DE FACTURAS'],
        ['Fecha de exportación:', fechaActual],
        ['Total Facturas:', facturasData.length],
        ['Importe Total General:', totalGeneral],
        [],
        ['RESUMEN POR FACTURA:'],
        ['Factura #', 'Cliente', 'Estado', 'Subtotal', 'Total General']
    ];
    
    facturasData.forEach(factura => {
        const clienteInfo = factura.cliente_info || {};
        resumenData.push([
            factura.id,
            clienteInfo.nombre || 'Sin cliente',
            factura.estado,
            factura.subtotal,
            factura.total_general
        ]);
    });
    
    resumenData.push([]);
    resumenData.push(['TOTAL GENERAL:', '', '', '', totalGeneral]);
    
    const wsResumen = XLSX.utils.aoa_to_sheet(resumenData);
    XLSX.utils.book_append_sheet(wb, wsResumen, 'Resumen');
    
    // Hoja por cada factura
    facturasData.forEach((factura, index) => {
        const clienteInfo = factura.cliente_info || {};
        const facturaData = [];
        
        // Encabezado de factura
        facturaData.push([`FACTURA #${factura.id} - PDL VISIONES`]);
        facturaData.push([`Cliente: ${clienteInfo.nombre || 'Sin cliente'}`]);
        facturaData.push([`Código: ${clienteInfo.codigo || '--'}`]);
        facturaData.push([`Contrato: ${clienteInfo.ContratoNo || 'S/C'}`]);
        facturaData.push([`Estado: ${factura.estado}`]);
        facturaData.push([`Fecha Emisión: ${factura.fecha_emision}`]);
        facturaData.push([`Tipo Pago: ${factura.tipo_pago_info?.descripcion || 'Transferencia'}`]);
        facturaData.push([]);
        
        // Detalles de servicios
        facturaData.push(['#', 'Código', 'Servicio', 'Cantidad', 'Precio Unit.', 'Total Línea']);
        
        if (factura.detalles.length === 0) {
            facturaData.push(['-', '-', 'No hay servicios registrados', '-', '-', '-']);
        } else {
            factura.detalles.forEach((servicio, sIndex) => {
                facturaData.push([
                    sIndex + 1,
                    servicio.servicio_info?.codigo || '--',
                    servicio.servicio_info?.descripcion || 'Servicio',
                    servicio.cantidad,
                    servicio.precio_unitario,
                    servicio.total_linea
                ]);
            });
        }
        
        facturaData.push([]);
        
        // Línea de SUBTOTAL
        facturaData.push(['', '', '', '', 'SUBTOTAL:', factura.subtotal]);
        
        // Línea de TOTAL GENERAL
        facturaData.push(['', '', '', '', 'TOTAL GENERAL:', factura.total_general]);
        
        // Observaciones si existen
        if (factura.observaciones) {
            facturaData.push([]);
            facturaData.push(['Observaciones:', factura.observaciones]);
        }
        
        const wsFactura = XLSX.utils.aoa_to_sheet(facturaData);
        
        // Ajustar anchos de columna
        wsFactura['!cols'] = [
            { wch: 8 },  // #
            { wch: 15 }, // Código
            { wch: 40 }, // Servicio
            { wch: 12 }, // Cantidad
            { wch: 18 }, // Precio Unit.
            { wch: 18 }  // Total Línea
        ];
        
        // Nombre de hoja: Factura_XX (máximo 31 caracteres)
        const sheetName = `Factura_${factura.id}`.substring(0, 31);
        XLSX.utils.book_append_sheet(wb, wsFactura, sheetName);
    });
    
    // Guardar archivo
    XLSX.writeFile(wb, `Facturas_PDL_${new Date().toISOString().slice(0, 10)}.xlsx`);
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-excel me-2"></i>Excel Exportado',
        text: `${facturasData.length} factura(s) exportada(s) correctamente.`,
        timer: 2000,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
}

function exportarTXT() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para exportar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const horaActual = new Date().toLocaleTimeString('es-ES');
    const totalGeneral = calcularTotalGeneralFacturas();
    
    let contenido = `╔══════════════════════════════════════════════════════════════════════╗\n`;
    contenido += `║                    PDL VISIONES - CAPTURADOR DE FACTURAS              ║\n`;
    contenido += `╠══════════════════════════════════════════════════════════════════════╣\n`;
    contenido += `║  Fecha: ${fechaActual} ${horaActual.padStart(52, ' ')}║\n`;
    contenido += `║  Total Facturas: ${facturasData.length.toString().padStart(47, ' ')}║\n`;
    contenido += `║  Importe Total: ${formatearMoneda(totalGeneral).padStart(48, ' ')}║\n`;
    contenido += `╚══════════════════════════════════════════════════════════════════════╝\n\n`;
    
    facturasData.forEach((factura, index) => {
        const clienteInfo = factura.cliente_info || {};
        
        contenido += `┌──────────────────────────────────────────────────────────────────────┐\n`;
        contenido += `│ FACTURA #${factura.id.toString().padEnd(62, ' ')}│\n`;
        contenido += `├──────────────────────────────────────────────────────────────────────┤\n`;
        contenido += `│ Cliente....: ${(clienteInfo.nombre || 'Sin cliente').padEnd(52, ' ')}│\n`;
        contenido += `│ Código.....: ${(clienteInfo.codigo || '--').padEnd(52, ' ')}│\n`;
        contenido += `│ Contrato...: ${(clienteInfo.ContratoNo || 'S/C').padEnd(52, ' ')}│\n`;
        contenido += `│ Estado.....: ${factura.estado.padEnd(52, ' ')}│\n`;
        contenido += `│ Fecha......: ${factura.fecha_emision.padEnd(52, ' ')}│\n`;
        contenido += `├──────────────────────────────────────────────────────────────────────┤\n`;
        contenido += `│ DETALLE DE SERVICIOS:                                                │\n`;
        contenido += `├──────┬────────────────────────────────────────┬────────┬─────────────┤\n`;
        contenido += `│  #   │ Servicio                               │  Cant. │    Total    │\n`;
        contenido += `├──────┼────────────────────────────────────────┼────────┼─────────────┤\n`;
        
        if (factura.detalles.length === 0) {
            contenido += `│  -   │ No hay servicios registrados            │   -    │      -      │\n`;
        } else {
            factura.detalles.forEach((servicio, sIndex) => {
                const descripcion = (servicio.servicio_info?.descripcion || 'Servicio').substring(0, 38);
                const total = formatearMoneda(servicio.total_linea);
                contenido += `│ ${(sIndex + 1).toString().padEnd(4)} │ ${descripcion.padEnd(38)} │ ${servicio.cantidad.toString().padEnd(6)} │ ${total.padEnd(11)} │\n`;
            });
        }
        
        contenido += `├──────┴────────────────────────────────────────┴────────┼─────────────┤\n`;
        contenido += `│                                          Subtotal: │ ${formatearMoneda(factura.subtotal).padEnd(11)} │\n`;
        contenido += `│                                     TOTAL GENERAL: │ ${formatearMoneda(factura.total_general).padEnd(11)} │\n`;
        contenido += `└──────────────────────────────────────────────────────┴─────────────┘\n\n`;
    });
    
    contenido += `═══════════════════════════════════════════════════════════════════════════\n`;
    contenido += `                               FIN DEL REPORTE                               \n`;
    contenido += `═══════════════════════════════════════════════════════════════════════════\n`;
    
    const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Facturas_PDL_${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-alt me-2"></i>TXT Exportado',
        text: `${facturasData.length} factura(s) exportada(s) correctamente.`,
        timer: 2000,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
}
function exportarCSV() {
    if (facturasData.length === 0) {
        swalDark.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Sin facturas',
            text: 'No hay facturas para exportar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    const periodoOperativo = obtenerPeriodoOperativo();
    
    // Encabezados del CSV
    let contenido = 'Factura,Cliente,Código,Contrato,Estado,Fecha Emisión,Tipo Pago,Servicio,Código Servicio,Cantidad,Precio Unit.,Total Línea,Subtotal,Total General,Período Operativo,Observaciones\n';
    
    facturasData.forEach(factura => {
        const clienteInfo = factura.cliente_info || {};
        const tipoPago = factura.tipo_pago_info?.descripcion || 'Transferencia';
        const observaciones = factura.observaciones ? `"${factura.observaciones.replace(/"/g, '""').replace(/\n/g, ' ')}"` : '';
        const periodo = periodoOperativo !== 'No configurado' ? periodoOperativo : '';
        
        if (factura.detalles.length === 0) {
            // Factura sin detalles
            contenido += `${factura.id},"${clienteInfo.nombre || ''}",${clienteInfo.codigo || ''},${clienteInfo.ContratoNo || ''},${factura.estado},${factura.fecha_emision},"${tipoPago}",--,--,0,0.00,0.00,${factura.subtotal.toFixed(2)},${factura.total_general.toFixed(2)},"${periodo}",${observaciones}\n`;
        } else {
            // Factura con detalles - una línea por cada servicio
            factura.detalles.forEach(servicio => {
                const servicioInfo = servicio.servicio_info || {};
                const descripcion = (servicioInfo.descripcion || 'Servicio').replace(/"/g, '""');
                const codigoServicio = servicioInfo.codigo || '';
                
                contenido += `${factura.id},"${clienteInfo.nombre || ''}",${clienteInfo.codigo || ''},${clienteInfo.ContratoNo || ''},${factura.estado},${factura.fecha_emision},"${tipoPago}","${descripcion}",${codigoServicio},${servicio.cantidad},${servicio.precio_unitario.toFixed(2)},${servicio.total_linea.toFixed(2)},${factura.subtotal.toFixed(2)},${factura.total_general.toFixed(2)},"${periodo}",${observaciones}\n`;
            });
        }
    });
    
    // Agregar línea de resumen al final
    const totalGeneral = calcularTotalGeneralFacturas();
    contenido += `"","","","","","","","TOTAL GENERAL","","","","",,"${formatearMoneda(totalGeneral)}","",""\n`;
    
    // Crear blob y descargar
    const blob = new Blob(['\ufeff' + contenido], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Facturas_PDL_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    
    swalDark.fire({
        icon: 'success',
        title: '<i class="fas fa-file-csv me-2"></i>CSV Exportado',
        text: `${facturasData.length} factura(s) exportada(s) correctamente.`,
        timer: 2000,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
    });
}
$(document).ready(function() {
    // Cuando el modal se abre, limpiar el filtro
    $('#servicioModal').on('show.bs.modal', function() {
        // Limpiar input de búsqueda
        const searchInput = document.getElementById('searchServicio');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Resetear categoría a "Todas"
        const selectCategoria = document.getElementById('selectCategoria');
        if (selectCategoria) {
            selectCategoria.value = '';
        }
        
        // Limpiar selecciones previas del modal
        serviciosSeleccionadosModal.clear();
        
        // Desmarcar checkbox "Seleccionar todos"
        const checkAll = document.getElementById('checkAllModal');
        if (checkAll) {
            checkAll.checked = false;
            checkAll.indeterminate = false;
        }
    });
});
function obtenerFechaPorDefecto() {
    // Obtener el día actual de la PC
    const diaActual = new Date().getDate();
    
    // Obtener mes y año de la fecha operativa
    const mesOperativo = parseInt(fechaOperativaMes);
    const anioOperativo = parseInt(fechaOperativaAnio);
    
    // Formatear la fecha: YYYY-MM-DD
    const mes = String(mesOperativo).padStart(2, '0');
    const dia = String(diaActual).padStart(2, '0');
    
    return `${anioOperativo}-${mes}-${dia}`;
}

// Toggle Quick Actions con animación circular y colores mejorados
function toggleQuickActions() {
    const expandedActions = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    const mainIcon = document.getElementById('mainQuickActionIcon');
    
    if (!expandedActions) return;
    
    const isExpanded = expandedActions.classList.contains('show');
    
    if (isExpanded) {
        // Cerrar - los botones secundarios se van con animación escalonada
        expandedActions.classList.remove('show');
        if (mainIcon) mainIcon.className = 'fas fa-ellipsis-h';
        if (mainButton) {
            mainButton.title = 'Mostrar acciones rápidas';
            mainButton.style.transform = 'rotate(0deg)';
        }
    } else {
        // Abrir - los botones secundarios aparecen con animación escalonada
        expandedActions.classList.add('show');
        if (mainIcon) mainIcon.className = 'fas fa-times';
        if (mainButton) {
            mainButton.title = 'Ocultar acciones rápidas';
            mainButton.style.transform = 'rotate(90deg)';
        }
    }
}
        
// Cerrar Quick Actions al hacer clic fuera (con animación)
document.addEventListener('click', function(event) {
    const quickActions = document.querySelector('.win-quick-actions');
    const expandedActions = document.getElementById('quickActionsExpanded');
    const mainButton = document.getElementById('mainQuickAction');
    const mainIcon = document.getElementById('mainQuickActionIcon');
    
    if (!quickActions || !expandedActions) return;
    
    const isClickInside = quickActions.contains(event.target);
    const isExpanded = expandedActions.classList.contains('show');
    
    if (!isClickInside && isExpanded) {
        // Cerrar el menú con animación
        expandedActions.classList.remove('show');
        
        // Actualizar el icono a ⋯ (puntos horizontales)
        if (mainIcon) mainIcon.className = 'fas fa-ellipsis-h';
        if (mainButton) {
            mainButton.title = 'Mostrar acciones rápidas';
            mainButton.style.transform = 'rotate(0deg)';
        }
    }
});

// ============================================
// DROPDOWN DE FACTURAS - NAVEGACIÓN RÁPIDA
// ============================================

let dropdownOpen = false;
let dropdownFacturasFiltradas = [];

// Toggle del dropdown de facturas
function toggleFacturasDropdown() {
    const dropdown = document.getElementById('facturasDropdown');
    const btn = document.getElementById('btnFacturasDropdown');
    
    if (!dropdown) return;
    
    dropdownOpen = !dropdownOpen;
    
    if (dropdownOpen) {
        // Actualizar lista antes de mostrar
        actualizarDropdownFacturas();
        dropdown.classList.add('show');
        
        // Cambiar icono del botón mientras está abierto
        if (btn) {
            const originalTooltip = btn.getAttribute('data-tooltip');
            btn.setAttribute('data-tooltip-temp', originalTooltip);
            btn.setAttribute('data-tooltip', 'Cerrar lista');
        }
    } else {
        dropdown.classList.remove('show');
        if (btn) {
            const tempTooltip = btn.getAttribute('data-tooltip-temp');
            if (tempTooltip) {
                btn.setAttribute('data-tooltip', tempTooltip);
                btn.removeAttribute('data-tooltip-temp');
            }
        }
    }
}

// Actualizar el badge contador de facturas
function actualizarBadgeFacturas() {
    const badge = document.getElementById('facturasBadge');
    const dropdownCount = document.getElementById('dropdownFacturasCount');
    
    if (badge) {
        const count = facturasData.length;
        badge.textContent = count;
        
        // Cambiar color si hay muchas facturas
        if (count > 0) {
            badge.style.background = count > 9 ? '#dc2626' : '#ef4444';
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    if (dropdownCount) {
        dropdownCount.textContent = `${facturasData.length} factura${facturasData.length !== 1 ? 's' : ''}`;
    }
}

// Actualizar el contenido del dropdown con botón eliminar
function actualizarDropdownFacturas() {
    const listContainer = document.getElementById('facturasDropdownList');
    const searchInput = document.getElementById('searchFacturaDropdown');
    
    if (!listContainer) return;
    
    // Obtener término de búsqueda
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    
    // Filtrar facturas
    dropdownFacturasFiltradas = facturasData.filter(factura => {
        const clienteNombre = (factura.cliente_info?.nombre || 'Sin cliente').toLowerCase();
        const facturaNumero = factura.id.toString();
        
        if (!searchTerm) return true;
        return facturaNumero.includes(searchTerm) || clienteNombre.includes(searchTerm);
    });
    
    if (dropdownFacturasFiltradas.length === 0) {
        if (facturasData.length === 0) {
            listContainer.innerHTML = `
                <div class="facturas-dropdown-empty">
                    <i class="fas fa-file-invoice"></i>
                    <p>No hay facturas creadas</p>
                    <small>Haz clic en + para crear una</small>
                </div>
            `;
        } else {
            listContainer.innerHTML = `
                <div class="facturas-dropdown-empty">
                    <i class="fas fa-search"></i>
                    <p>No se encontraron facturas</p>
                    <small>Prueba con otro término de búsqueda</small>
                </div>
            `;
        }
        return;
    }
    
    // Generar lista de facturas con botón eliminar
    let html = '';
    dropdownFacturasFiltradas.forEach((factura, idx) => {
        const clienteInfo = factura.cliente_info || {};
        const nombreCliente = clienteInfo.nombre || 'Sin cliente';
        const nombreTruncado = nombreCliente.length > 28 ? nombreCliente.substring(0, 25) + '...' : nombreCliente;
        const tieneServicios = factura.detalles.length > 0;
        const iconoEstado = tieneServicios ? '<i class="fas fa-check-circle" style="color: #10b981; font-size: 10px;"></i>' : '<i class="fas fa-clock" style="color: #f59e0b; font-size: 10px;"></i>';
        
        // Encontrar el índice real en facturasData (no el filtrado)
        const realIndex = facturasData.findIndex(f => f.id === factura.id);
        
        html += `
            <div class="facturas-dropdown-item" data-factura-id="${factura.id}" data-factura-index="${realIndex}">
                <div class="facturas-dropdown-item-content" onclick="irAFactura(${factura.id})">
                    <div class="factura-item-info">
                        <div class="factura-item-numero">
                            ${iconoEstado}
                            <span style="margin-left: 6px;">Factura #${factura.id}</span>
                        </div>
                        <div class="factura-item-cliente">
                            <i class="fas fa-user me-1"></i>${escapeHtml(nombreTruncado)}
                        </div>
                    </div>
                    <div class="factura-item-total">
                        ${formatearMoneda(factura.total_general)}
                    </div>
                </div>
                <div class="factura-item-actions">
                    <button class="btn-eliminar-dropdown" onclick="event.stopPropagation(); eliminarFacturaDesdeDropdown(${realIndex}, ${factura.id})" title="Eliminar factura">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    listContainer.innerHTML = html;
}

// Filtrar dropdown mientras se escribe
function filtrarDropdownFacturas() {
    actualizarDropdownFacturas();
}

// Función para escapar HTML (seguridad)
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// IR A FACTURA ESPECÍFICA - FUNCIÓN SIMPLIFICADA Y CORREGIDA
// ============================================
function irAFactura(facturaId) {
    
    // Cerrar todos los dropdowns
    if (typeof dropdownOpen !== 'undefined' && dropdownOpen) toggleFacturasDropdown();
    if (typeof headerDropdownOpen !== 'undefined' && headerDropdownOpen) toggleHeaderFacturasDropdown();
    
    // Cerrar quick actions expandidas
    const expandedActions = document.getElementById('quickActionsExpanded');
    if (expandedActions && expandedActions.classList.contains('show')) {
        expandedActions.classList.remove('show');
        const mainIcon = document.getElementById('mainQuickActionIcon');
        if (mainIcon) mainIcon.className = 'fas fa-ellipsis-h';
        const mainButton = document.getElementById('mainQuickAction');
        if (mainButton) mainButton.style.transform = 'rotate(0deg)';
    }
    
    const card = document.getElementById(`factura-card-${facturaId}`);
    const chevron = document.getElementById(`chevron-${facturaId}`);
    
    if (card) {
        // Expandir si está colapsada
        if (card.classList.contains('collapsed')) {
            card.classList.remove('collapsed');
            if (chevron) chevron.classList.add('expanded');
        }
        
        // Scroll con offset para que el título quede debajo del navbar
        scrollToElementWithOffset(card, 70);
        
        // Resaltar el header con animación de parpadeo
        const header = card.querySelector('.card-header-custom');
        if (header) {
            header.classList.remove('flash');
            void header.offsetHeight; // Forzar reflow
            header.classList.add('flash');
            setTimeout(() => {
                header.classList.remove('flash');
            }, 4000);
        }
        
        // Mostrar notificación
        const factura = facturasData.find(f => f.id === facturaId);
       
	   /* if (factura) {
            swalDark.fire({
                icon: 'info',
                title: '<i class="fas fa-arrow-right me-2"></i>Factura encontrada',
                html: `Factura <strong>#${facturaId}</strong><br>${factura.cliente_info?.nombre || 'Sin cliente'}`,
                toast: true,
                position: 'top-end',
                timer: 2000,
                showConfirmButton: false
            });
        }*/
    } else {
        console.error("❌ No se encontró la factura con ID:", facturaId);
        swalDark.fire({
            icon: 'error',
            title: '<i class="fas fa-exclamation-triangle me-2"></i>Error',
            text: `No se encontró la factura #${facturaId}.`,
            toast: true,
            position: 'top-end',
            timer: 2000,
            showConfirmButton: false
        });
    }
}

// Asegurar que la función sea accesible globalmente
window.irAFactura = irAFactura;

// Función auxiliar para hacer scroll a una factura (la que ya tenías)
function scrollToFactura(facturaId) {
    return irAFactura(facturaId);
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('facturasDropdown');
    const btn = document.getElementById('btnFacturasDropdown');
    
    if (!dropdown || !btn) return;
    
    const isClickInside = btn.contains(event.target) || dropdown.contains(event.target);
    
    if (!isClickInside && dropdownOpen) {
        toggleFacturasDropdown();
    }
});

// Actualizar el badge cuando cambian las facturas
// Modificar la función actualizarContador() existente
const originalActualizarContador = actualizarContador;
actualizarContador = function() {
    if (originalActualizarContador) originalActualizarContador();
    actualizarBadgeFacturas();
    actualizarDropdownFacturas();
};

// También actualizar cuando se modifiquen facturas
function actualizarTodoFacturas() {
    actualizarContador();
    actualizarBadgeFacturas();
    if (dropdownOpen) {
        actualizarDropdownFacturas();
    }
}
// ============================================
// RENUMERAR FACTURAS AUTOMÁTICAMENTE
// ============================================
function renumeraFacturas() {
    if (facturasData.length === 0) {
        facturaCounter = 0;
        return;
    }
    
    // Ordenar facturas por ID actual (para mantener el orden de creación)
    facturasData.sort((a, b) => a.id - b.id);
    
    // Renumerar secuencialmente
    for (let i = 0; i < facturasData.length; i++) {
        facturasData[i].id = i + 1;
    }
    
    // Actualizar el contador global
    facturaCounter = facturasData.length;
    
    // Guardar cambios
    guardarEnLocalStorage();
}

// Función mejorada de eliminarFactura con renumeración
const eliminarFacturaOriginal = eliminarFactura;
eliminarFactura = function(index) {
    const facturaAEliminar = facturasData[index];
    const facturaId = facturaAEliminar.id;
    const clienteNombre = facturaAEliminar.cliente_info?.nombre || 'Sin cliente';
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Eliminar factura?',
        html: `Se eliminará la <strong>Factura #${facturaId}</strong><br><small class="text-muted">${clienteNombre}</small><br><br><span class="text-warning"><i class="fas fa-sort-numeric-down-alt me-1"></i> Las facturas se renumerarán automáticamente.</span>`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Eliminar la factura
            facturasData.splice(index, 1);
            
            // RENUMERAR TODAS LAS FACTURAS
            renumeraFacturas();
            
            // Re-renderizar todo
            renderizarFacturas();
            actualizarContador();
            guardarEnLocalStorage();
            
            // Actualizar dropdown
            actualizarBadgeFacturas();
            if (typeof dropdownOpen !== 'undefined' && dropdownOpen) {
                actualizarDropdownFacturas();
            }
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>Factura eliminada', 
                html: `Factura <strong>#${facturaId}</strong> eliminada correctamente.<br><small class="text-info">Las facturas han sido renumeradas.</small>`,
                timer: 2000, 
                showConfirmButton: false 
            });
        }
    });
};
// Eliminar factura desde el dropdown
function eliminarFacturaDesdeDropdown(index, facturaId) {
    const factura = facturasData[index];
    const clienteNombre = factura?.cliente_info?.nombre || 'Sin cliente';
    
    // Cerrar dropdown
    if (dropdownOpen) {
        toggleFacturasDropdown();
    }
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Eliminar factura?',
        html: `Se eliminará la <strong>Factura #${facturaId}</strong><br><small class="text-muted">${escapeHtml(clienteNombre)}</small><br><br><span class="text-warning"><i class="fas fa-sort-numeric-down-alt me-1"></i> Las facturas se renumerarán automáticamente.</span>`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Eliminar la factura
            facturasData.splice(index, 1);
            
            // RENUMERAR TODAS LAS FACTURAS
            renumeraFacturas();
            
            // Re-renderizar todo
            renderizarFacturas();
            actualizarContador();
            guardarEnLocalStorage();
            
            // Actualizar dropdown
            actualizarBadgeFacturas();
            actualizarDropdownFacturas();
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>Factura eliminada', 
                html: `Factura <strong>#${facturaId}</strong> eliminada correctamente.<br><small class="text-info">Las facturas han sido renumeradas.</small>`,
                timer: 2000, 
                showConfirmButton: false 
            });
        }
    });
}
// ============================================
// HEADER DROPDOWN DE FACTURAS
// ============================================

let headerDropdownOpen = false;

// Toggle del dropdown de facturas en header
function toggleHeaderFacturasDropdown() {
    const dropdown = document.getElementById('headerFacturasDropdown');
    const btn = document.getElementById('headerFacturasBtn');
    
    if (!dropdown) return;
    
    headerDropdownOpen = !headerDropdownOpen;
    
    if (headerDropdownOpen) {
        actualizarHeaderFacturasList();
        dropdown.classList.add('show');
        // Cambiar ícono del botón
        const chevron = btn.querySelector('.fa-chevron-down');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    } else {
        dropdown.classList.remove('show');
        const chevron = btn.querySelector('.fa-chevron-down');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    }
}

// Cerrar header dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('headerFacturasDropdown');
    const btn = document.getElementById('headerFacturasBtn');
    
    if (!dropdown || !btn) return;
    
    const isClickInside = btn.contains(event.target) || dropdown.contains(event.target);
    
    if (!isClickInside && headerDropdownOpen) {
        toggleHeaderFacturasDropdown();
    }
});

// Actualizar badge del header
function actualizarHeaderBadge() {
    const badge = document.getElementById('headerFacturasBadge');
    const countSpan = document.getElementById('headerDropdownCount');
    
    if (badge) {
        const count = facturasData.length;
        badge.textContent = count;
        if (count === 0) {
            badge.style.display = 'none';
        } else {
            badge.style.display = 'inline-flex';
        }
    }
    
    if (countSpan) {
        countSpan.textContent = `${facturasData.length} factura${facturasData.length !== 1 ? 's' : ''}`;
    }
}

// Actualizar lista del header dropdown
function actualizarHeaderFacturasList() {
    const listContainer = document.getElementById('headerFacturasList');
    const searchInput = document.getElementById('headerSearchFactura');
    
    if (!listContainer) return;
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    
    let facturasFiltradas = facturasData.filter(factura => {
        const clienteNombre = (factura.cliente_info?.nombre || 'Sin cliente').toLowerCase();
        const facturaNumero = factura.id.toString();
        
        if (!searchTerm) return true;
        return facturaNumero.includes(searchTerm) || clienteNombre.includes(searchTerm);
    });
    
    if (facturasFiltradas.length === 0) {
        if (facturasData.length === 0) {
            listContainer.innerHTML = `
                <div class="header-facturas-empty">
                    <i class="fas fa-file-invoice"></i>
                    <p>No hay facturas creadas</p>
                    <small>Haz clic en "Nueva" para comenzar a crear Facturas</small>
                </div>
            `;
        } else {
            listContainer.innerHTML = `
                <div class="header-facturas-empty">
                    <i class="fas fa-search"></i>
                    <p>No se encontraron facturas</p>
                    <small>Prueba con otro término de búsqueda</small>
                </div>
            `;
        }
        return;
    }
    
    // Generar HTML
    let html = '';
    facturasFiltradas.forEach(factura => {
        const clienteInfo = factura.cliente_info || {};
        const nombreCliente = clienteInfo.nombre || 'Sin cliente';
        const nombreTruncado = nombreCliente.length > 25 ? nombreCliente.substring(0, 22) + '...' : nombreCliente;
        const tieneServicios = factura.detalles.length > 0;
        const iconoEstado = tieneServicios ? 
            '<i class="fas fa-check-circle" style="color: #10b981;"></i>' : 
            '<i class="fas fa-clock" style="color: #f59e0b;"></i>';
        
        const realIndex = facturasData.findIndex(f => f.id === factura.id);
        
        html += `
            <div class="header-facturas-item">
                <div class="header-facturas-item-content" onclick="irAFacturaHeader(${factura.id})">
                    <div class="header-factura-info">
                        <div class="header-factura-numero">
                            ${iconoEstado}
                            <span>Factura #${factura.id}</span>
                        </div>
                        <div class="header-factura-cliente">
                            <i class="fas fa-user me-1"></i>${escapeHtml(nombreTruncado)}
                        </div>
                    </div>
                    <div class="header-factura-total">
                        ${formatearMoneda(factura.total_general)}
                    </div>
                </div>
                <div class="header-factura-actions">
                    <button class="header-btn-eliminar" onclick="event.stopPropagation(); eliminarFacturaDesdeHeader(${realIndex}, ${factura.id})" title="Eliminar factura">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    listContainer.innerHTML = html;
}

// Filtrar header dropdown mientras se escribe
function filtrarHeaderFacturas() {
    actualizarHeaderFacturasList();
}

// Ir a factura desde header (scroll + resaltado + expandir si está colapsada)
function irAFacturaHeader(facturaId) {
    if (headerDropdownOpen) toggleHeaderFacturasDropdown();
    irAFactura(facturaId);
}

// Eliminar factura desde header
function eliminarFacturaDesdeHeader(index, facturaId) {
    const factura = facturasData[index];
    const clienteNombre = factura?.cliente_info?.nombre || 'Sin cliente';
    
    if (headerDropdownOpen) {
        toggleHeaderFacturasDropdown();
    }
    
    swalDark.fire({
        title: '<i class="fas fa-trash-alt me-2"></i>¿Eliminar factura?',
        html: `Se eliminará la <strong>Factura #${facturaId}</strong><br><small class="text-muted">${escapeHtml(clienteNombre)}</small><br><br><span class="text-warning"><i class="fas fa-sort-numeric-down-alt me-1"></i> Las facturas se renumerarán automáticamente.</span>`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            facturasData.splice(index, 1);
            renumeraFacturas();
            renderizarFacturas();
            actualizarContador();
            guardarEnLocalStorage();
            
            actualizarHeaderBadge();
            if (headerDropdownOpen) {
                actualizarHeaderFacturasList();
            }
            actualizarBadgeFacturas();
            
            swalDark.fire({ 
                icon: 'success', 
                title: '<i class="fas fa-check-circle me-2"></i>Factura eliminada', 
                text: `Factura #${facturaId} eliminada correctamente.`,
                timer: 2000, 
                showConfirmButton: false 
            });
        }
    });
}

// Función para abrir/ver todas las facturas (expande todas)
function abrirTodasFacturas() {
    if (headerDropdownOpen) {
        toggleHeaderFacturasDropdown();
    }
    
    // Expandir todas las facturas
    expandirTodasFacturas();
    
    // Scroll al inicio de las facturas
    const container = document.getElementById('facturas-container');
    if (container) {
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Integrar con las funciones existentes
// Actualizar header badge cuando cambian las facturas
const originalActualizarContadorHeader = actualizarContador;
actualizarContador = function() {
    if (originalActualizarContadorHeader) originalActualizarContadorHeader();
    actualizarHeaderBadge();
    if (headerDropdownOpen) {
        actualizarHeaderFacturasList();
    }
};
// ============================================
// NUEVAS FUNCIONES PARA QUICK ACTION
// ============================================

// Variable para el estado del botón expandir/colapsar en quick action
let quickToggleExpandido = true;

// Función para Expandir/Colapsar todas desde Quick Action
function toggleAllFacturasQuick() {
    const cards = document.querySelectorAll('.factura-card');
    const chevrons = document.querySelectorAll('.chevron-icon');
    const btnIcon = document.getElementById('quickActionToggleIcon');
    const btn = document.getElementById('quickActionToggleAll');
    
    if (quickToggleExpandido) {
        // Colapsar todas
        cards.forEach(card => card.classList.add('collapsed'));
        chevrons.forEach(chevron => chevron.classList.remove('expanded'));
        quickToggleExpandido = false;
        btnIcon.className = 'fas fa-chevron-right';
        btn.setAttribute('data-tooltip', 'Expandir todas');
    } else {
        // Expandir todas
        cards.forEach(card => card.classList.remove('collapsed'));
        chevrons.forEach(chevron => chevron.classList.add('expanded'));
        quickToggleExpandido = true;
        btnIcon.className = 'fas fa-chevron-down';
        btn.setAttribute('data-tooltip', 'Colapsar todas');
    }
    
    // También actualizar el botón de la barra de acciones
    if (typeof todasExpandidas !== 'undefined') {
        todasExpandidas = quickToggleExpandido;
        actualizarBotonToggleAll();
    }
}

// Función para ir al inicio de las facturas
function irAlInicio() {
    const container = document.getElementById('facturas-container');
    if (container) {
        container.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start',
            inline: 'nearest'
        });
        
        // Efecto visual de feedback
        const btn = event?.target?.closest('.win-quick-action');
        if (btn) {
            btn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                btn.style.transform = '';
            }, 150);
        }
    }
}

// Función para ir al final de las facturas
function irAlFinal() {
    if (facturasData.length === 0) return;
    
    const lastCard = document.querySelector('.factura-card:last-child');
    if (lastCard) {
        lastCard.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'end',
            inline: 'nearest'
        });
        
        // Efecto visual de feedback
        const btn = event?.target?.closest('.win-quick-action');
        if (btn) {
            btn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                btn.style.transform = '';
            }, 150);
        }
    }
}

// Función para sincronizar el estado del botón expandir/colapsar
function sincronizarBotonToggleQuick() {
    const cards = document.querySelectorAll('.factura-card');
    const collapsedCards = document.querySelectorAll('.factura-card.collapsed');
    const btnIcon = document.getElementById('quickActionToggleIcon');
    const btn = document.getElementById('quickActionToggleAll');
    
    if (!btnIcon || !btn) return;
    
    if (collapsedCards.length === cards.length && cards.length > 0) {
        // Todas colapsadas
        quickToggleExpandido = false;
        btnIcon.className = 'fas fa-chevron-right';
        btn.setAttribute('data-tooltip', 'Expandir todas');
    } else {
        // Al menos una expandida
        quickToggleExpandido = true;
        btnIcon.className = 'fas fa-chevron-down';
        btn.setAttribute('data-tooltip', 'Colapsar todas');
    }
}


// ============================================
// MODAL UNIFICADO: CARGAR JSON (CORREGIDO - TEXTOS VISIBLES EN DARK)
// ============================================
function cargarProgreso() {
    const input = document.createElement('input'); 
    input.type = 'file'; 
    input.accept = '.json,application/json';
    
    input.onchange = (e) => {
        const file = e.target.files[0]; 
        if (!file) return;
        
        if (!file.name.toLowerCase().endsWith('.json')) {
            swalDark.fire({ 
                icon: 'warning', 
                title: '<i class="fas fa-triangle-exclamation me-2"></i>Archivo no válido', 
                text: 'Por favor selecciona un archivo con extensión .json',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
            });
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (ev) => {
            try {
                const datos = JSON.parse(ev.target.result);
                const facturasCargadas = datos.facturas || datos;
                
                if (!Array.isArray(facturasCargadas) || facturasCargadas.length === 0) {
                    swalDark.fire({ 
                        icon: 'warning', 
                        title: '<i class="fas fa-triangle-exclamation me-2"></i>Archivo vacío', 
                        text: 'El archivo no contiene facturas válidas.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                    });
                    return;
                }
                
                // Verificar clientes existentes
                let clientesFaltantes = [];
                facturasCargadas.forEach(f => {
                    if (f.cliente_id && !datosMaestros.clientes.find(c => c.id == f.cliente_id)) {
                        clientesFaltantes.push(`ID: ${f.cliente_id} - ${f.cliente_info?.nombre || 'Desconocido'}`);
                    }
                });
                
                // Mostrar modal de confirmación con estilos inline para dark mode
                swalDark.fire({
                    title: '<i class="fas fa-file-code me-2"></i>Cargar archivo JSON',
                    html: `
                        <div class="text-start" style="color: #e2e8f0 !important;">
                            <div class="alert py-2 px-3 mb-3" style="background: #1e3a5f; border: 1px solid #3b82f6; color: #e2e8f0 !important; border-radius: 8px;">
                                <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                <strong style="color: #ffffff !important;">${facturasCargadas.length}</strong> <span style="color: #e2e8f0 !important;">factura(s) encontradas en el archivo</span>
                                <br><small style="color: #94a3b8 !important;">Archivo: <span class="text-warning fw-bold">${file.name}</span></small>
                            </div>
                            ${clientesFaltantes.length > 0 ? `
                                <div class="alert py-2 px-3 mb-3" style="background: #332701; border: 1px solid #f59e0b; color: #fde68a !important; border-radius: 8px;">
                                    <i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i>
                                    <strong style="color: #fbbf24 !important;">⚠️ Atención:</strong> <span style="color: #fde68a !important;">${clientesFaltantes.length} cliente(s) no existen en la base de datos local</span>
                                    <br><small style="color: #d97706 !important;">Las facturas de estos clientes no podrán ser importadas</small>
                                </div>
                            ` : ''}
                            <div class="alert py-2 px-3 mb-0" style="background: #3d1a1a; border: 1px solid #ef4444; color: #fecaca !important; border-radius: 8px;">
                                <i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i>
                                <strong style="color: #fca5a5 !important;">Esta acción reemplazará TODAS las facturas actuales</strong>
                                <br><small style="color: #f87171 !important;">Las facturas existentes se perderán si no las has guardado antes.</small>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-upload me-2"></i>Importar JSON',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    background: '#1e1e2d',
                    color: '#e2e8f0'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoading(true);
                        
                        // Filtrar solo facturas con cliente válido
                        const facturasValidas = facturasCargadas.filter(f => {
                            if (!f.cliente_id) return false;
                            const clienteExiste = datosMaestros.clientes.find(c => c.id == f.cliente_id);
                            return clienteExiste !== undefined;
                        });
                        
                        if (facturasValidas.length === 0) {
                            showLoading(false);
                            swalDark.fire({ 
                                icon: 'error', 
                                title: '<i class="fas fa-times-circle me-2"></i>Importación fallida', 
                                text: 'Ninguna factura pudo ser importada porque los clientes no existen en la base de datos local.',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                            });
                            return;
                        }
                        
                        // Cargar facturas válidas
                        facturasData = facturasValidas;
                        
                        // Renumerar facturas secuencialmente
                        for (let i = 0; i < facturasData.length; i++) {
                            facturasData[i].id = i + 1;
                        }
                        facturaCounter = facturasData.length;
                        
                        // Guardar y renderizar
                        guardarEnLocalStorage();
                        renderizarFacturas();
                        actualizarContador();
                        actualizarTotalImporte();
                        
                        // Actualizar dropdowns
                        actualizarBadgeFacturas();
                        if (dropdownOpen) actualizarDropdownFacturas();
                        if (headerDropdownOpen) actualizarHeaderFacturasList();
                        
                        showLoading(false);
                        
                        const noImportadas = facturasCargadas.length - facturasValidas.length;
                        let mensajeFinal = `<strong style="color: #10b981;">${facturasValidas.length}</strong> <span style="color: #e2e8f0;">factura(s) importada(s) correctamente.</span>`;
                        if (noImportadas > 0) {
                            mensajeFinal += `<br><small style="color: #f59e0b;">⚠️ ${noImportadas} factura(s) no se importaron (clientes no encontrados).</small>`;
                        }
                        
                        swalDark.fire({ 
                            icon: 'success', 
                            title: '<i class="fas fa-check-circle me-2"></i>JSON importado', 
                            html: mensajeFinal,
                            timer: 3000, 
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                            background: '#1e1e2d',
                            color: '#e2e8f0'
                        });
                    }
                });
                
            } catch(er) { 
                console.error('Error al cargar JSON:', er);
                swalDark.fire({ 
                    icon: 'error', 
                    title: '<i class="fas fa-triangle-exclamation me-2"></i>Error', 
                    text: 'El archivo no es un JSON válido o está corrupto.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                }); 
            }
        };
        reader.readAsText(file);
    };
    input.click();
}

// ============================================
// MODAL UNIFICADO: CARGAR SQL (CORREGIDO - TEXTOS VISIBLES EN DARK)
// ============================================
function cargarSQL() {
    const input = document.createElement('input'); 
    input.type = 'file'; 
    input.accept = '.sql,text/plain';
    
    input.onchange = (e) => {
        const file = e.target.files[0]; 
        if (!file) return;
        
        if (!file.name.toLowerCase().endsWith('.sql')) {
            swalDark.fire({ 
                icon: 'warning', 
                title: '<i class="fas fa-triangle-exclamation me-2"></i>Archivo no válido', 
                text: 'Por favor selecciona un archivo con extensión .sql',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
            });
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (ev) => {
            try {
                const sqlContent = ev.target.result;
                showLoading(true);
                const facturasImportadas = parseSQLToFacturasCompleto(sqlContent);
                showLoading(false);
                
                if (facturasImportadas.length === 0) {
                    swalDark.fire({ 
                        icon: 'warning', 
                        title: '<i class="fas fa-triangle-exclamation me-2"></i>No se encontraron facturas', 
                        text: 'El archivo SQL no contiene facturas válidas para importar.\n\nAsegúrate de que el SQL fue generado por el capturador.',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                    });
                    return;
                }
                
                // Verificar clientes existentes
                let clientesFaltantes = [];
                facturasImportadas.forEach(f => {
                    if (f.cliente_id && !datosMaestros.clientes.find(c => c.id == f.cliente_id)) {
                        clientesFaltantes.push(`ID: ${f.cliente_id}`);
                    }
                });
                
                // Mostrar modal de confirmación con estilos inline para dark mode
                swalDark.fire({
                    title: '<i class="fas fa-database me-2"></i>Cargar archivo SQL',
                    html: `
                        <div class="text-start" style="color: #e2e8f0 !important;">
                            <div class="alert py-2 px-3 mb-3" style="background: #1e3a5f; border: 1px solid #3b82f6; color: #e2e8f0 !important; border-radius: 8px;">
                                <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                <strong style="color: #ffffff !important;">${facturasImportadas.length}</strong> <span style="color: #e2e8f0 !important;">factura(s) encontradas en el archivo</span>
                                <br><small style="color: #94a3b8 !important;">Archivo: <span class="text-warning fw-bold">${file.name}</span></small>
                            </div>
                            ${clientesFaltantes.length > 0 ? `
                                <div class="alert py-2 px-3 mb-3" style="background: #332701; border: 1px solid #f59e0b; color: #fde68a !important; border-radius: 8px;">
                                    <i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i>
                                    <strong style="color: #fbbf24 !important;">⚠️ Atención:</strong> <span style="color: #fde68a !important;">${clientesFaltantes.length} cliente(s) no existen en la base de datos local</span>
                                    <br><small style="color: #d97706 !important;">Las facturas de estos clientes no podrán ser importadas</small>
                                </div>
                            ` : ''}
                            <div class="alert py-2 px-3 mb-0" style="background: #3d1a1a; border: 1px solid #ef4444; color: #fecaca !important; border-radius: 8px;">
                                <i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i>
                                <strong style="color: #fca5a5 !important;">Esta acción reemplazará TODAS las facturas actuales</strong>
                                <br><small style="color: #f87171 !important;">Las facturas existentes se perderán si no las has guardado antes.</small>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-upload me-2"></i>Importar SQL',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    background: '#1e1e2d',
                    color: '#e2e8f0'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoading(true);
                        
                        // Filtrar solo facturas con cliente válido
                        const facturasValidas = facturasImportadas.filter(f => f.cliente_info !== null);
                        
                        if (facturasValidas.length === 0) {
                            showLoading(false);
                            swalDark.fire({ 
                                icon: 'error', 
                                title: '<i class="fas fa-times-circle me-2"></i>Importación fallida', 
                                text: 'Ninguna factura pudo ser importada porque los clientes no existen en la base de datos local.',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                            });
                            return;
                        }
                        
                        // Cargar facturas válidas
                        facturasData = facturasValidas;
                        
                        // Renumerar facturas secuencialmente
                        for (let i = 0; i < facturasData.length; i++) {
                            facturasData[i].id = i + 1;
                        }
                        facturaCounter = facturasData.length;
                        
                        // Guardar y renderizar
                        guardarEnLocalStorage();
                        renderizarFacturas();
                        actualizarContador();
                        actualizarTotalImporte();
                        
                        // Actualizar dropdowns
                        actualizarBadgeFacturas();
                        if (dropdownOpen) actualizarDropdownFacturas();
                        if (headerDropdownOpen) actualizarHeaderFacturasList();
                        
                        showLoading(false);
                        
                        const noImportadas = facturasImportadas.length - facturasValidas.length;
                        let mensajeFinal = `<strong style="color: #10b981;">${facturasValidas.length}</strong> <span style="color: #e2e8f0;">factura(s) importada(s) correctamente.</span>`;
                        if (noImportadas > 0) {
                            mensajeFinal += `<br><small style="color: #f59e0b;">⚠️ ${noImportadas} factura(s) no se importaron (clientes no encontrados).</small>`;
                        }
                        
                        swalDark.fire({ 
                            icon: 'success', 
                            title: '<i class="fas fa-check-circle me-2"></i>SQL importado', 
                            html: mensajeFinal,
                            timer: 3000, 
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                            background: '#1e1e2d',
                            color: '#e2e8f0'
                        });
                    }
                });
                
            } catch(er) { 
                showLoading(false);
                console.error('Error al cargar SQL:', er);
                swalDark.fire({ 
                    icon: 'error', 
                    title: '<i class="fas fa-triangle-exclamation me-2"></i>Error', 
                    text: 'El archivo SQL no es válido o está corrupto.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' 
                }); 
            }
        };
        reader.readAsText(file);
    };
    input.click();
}

// ============================================
// PARSEAR SQL COMPLETO A FACTURAS
// ============================================
function parseSQLToFacturasCompleto(sqlContent) {
    const facturas = [];
    const fidMap = new Map();
    
    // Extraer facturas
    const factRegex = /INSERT INTO tbl_fact \(cliente_id, tipo_pago_id, subtotal, total_general, estado, fecha_emision, observaciones\) VALUES\s*\(\s*(\d+),\s*(\d+),\s*([\d.]+),\s*([\d.]+),\s*'([^']+)',\s*'([^']+)',\s*(NULL|'[^']*'|'')\s*\)\s*;/gi;
    let match;
    let idx = 0;
    
    while ((match = factRegex.exec(sqlContent)) !== null) {
        const clienteId = parseInt(match[1]);
        let observaciones = match[7] !== 'NULL' && match[7] !== "''" ? match[7].replace(/^'|'$/g, '') : '';
        if (observaciones.startsWith(', ')) observaciones = observaciones.substring(2);
        
        const clienteInfo = datosMaestros.clientes.find(c => c.id == clienteId);
        
        facturas.push({
            id: 0,
            cliente_id: clienteId,
            cliente_info: clienteInfo ? {
                id: clienteInfo.id, codigo: clienteInfo.codigo, nombre: clienteInfo.nombre,
                ContratoNo: clienteInfo.ContratoNo, telefono: clienteInfo.telefono, email: clienteInfo.email,
                activo: clienteInfo.activo, fechaVence: clienteInfo.fechaVence,
                fechaRegistro: clienteInfo.fechaRegistro, vigenciapor: clienteInfo.vigenciapor,
                renovac: clienteInfo.renovac, fechafinalcontrato: clienteInfo.fechafinalcontrato,
                ResponsableEntidad: clienteInfo.ResponsableEntidad
            } : null,
            tipo_pago_id: parseInt(match[2]),
            tipo_pago_info: datosMaestros.tiposPago.find(t => t.id == parseInt(match[2])) || null,
            subtotal: parseFloat(match[3]),
            total_general: parseFloat(match[4]),
            estado: match[5],
            fecha_emision: match[6].split(' ')[0],
            observaciones: observaciones,
            detalles: []
        });
        idx++;
    }
    
    // Mapear @fid_X
    const fidRegex = /SET @fid_(\d+)\s*=\s*LAST_INSERT_ID\(\)\s*;/gi;
    let fidMatch, fidCounter = 0;
    while ((fidMatch = fidRegex.exec(sqlContent)) !== null) {
        fidMap.set(parseInt(fidMatch[1]), fidCounter++);
    }
    
    // Si no hay SET, asumir orden secuencial
    if (fidMap.size === 0 && facturas.length > 0) {
        for (let i = 0; i < facturas.length; i++) {
            fidMap.set(i + 1, i);
        }
    }
    
    // Extraer detalles
    const detalleRegex = /INSERT INTO tbl_fact_detalle \(factura_id, servicio_id, cantidad, precio_unitario, total_linea\) VALUES\s*\(\s*@fid_(\d+),\s*(\d+),\s*([\d.]+),\s*([\d.]+),\s*([\d.]+)\s*\)\s*;/gi;
    let detalleMatch;
    
    while ((detalleMatch = detalleRegex.exec(sqlContent)) !== null) {
        const facturaIdx = fidMap.get(parseInt(detalleMatch[1]));
        if (facturaIdx !== undefined && facturas[facturaIdx]) {
            const servicioId = parseInt(detalleMatch[2]);
            let servicioInfo = datosMaestros.servicios.find(s => s.id == servicioId);
            if (!servicioInfo) {
                servicioInfo = { 
                    id: servicioId, 
                    codigo: 'DESC', 
                    descripcion: `Servicio ID ${servicioId}`, 
                    costo: parseFloat(detalleMatch[4]) 
                };
            }
            facturas[facturaIdx].detalles.push({
                servicio_id: servicioId,
                servicio_info: servicioInfo,
                cantidad: parseFloat(detalleMatch[3]),
                precio_unitario: parseFloat(detalleMatch[4]),
                total_linea: parseFloat(detalleMatch[5])
            });
        }
    }
    
    return facturas.filter(f => f.cliente_info !== null);
}
function scrollToElementWithOffset(element, offset = 70) {
    if (!element) return;
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
    const offsetPosition = elementPosition - offset;
    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
    });
}
    </script>
    
    <?php if (file_exists('config/footer.php')) include 'config/footer.php'; ?>
</body>
</html>