<?php
//bandecnom.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Verificar sesión
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

require_once '../config/database.php';

// Control de acceso por rol
if (!permiso_puede('bandecnom', 'ver')) {
    permiso_denegar_acceso('Exportar Banco');
}

// === MANEJADOR AJAX: OBTENER NOMBRES DESDE LA BASE DE DATOS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_ajax']) && $_POST['accion_ajax'] === 'obtener_nombres') {
    header('Content-Type: application/json');
    try {
        $ci_list = json_decode($_POST['ci_list'] ?? '[]', true);
        if (empty($ci_list)) {
            echo json_encode(['success' => true, 'nombres' => []]);
            exit();
        }

        // Normalizar los CIs recibidos para asegurar coincidencia de formato
        $ci_list_clean = array_map(function($ci) {
            $clean = preg_replace('/\D/', '', $ci);
            return str_pad($clean, 11, '0', STR_PAD_LEFT);
        }, $ci_list);

        // Preparar marcadores seguros (?, ?, ...) para evitar inyección SQL
        $placeholders = implode(',', array_fill(0, count($ci_list_clean), '?'));
        
        // Consultar a la tabla trabajadores usando el $pdo ya disponible de database.php
        $stmt = $pdo->prepare("SELECT ci, nombre_completo FROM trabajadores WHERE ci IN ($placeholders)");
        $stmt->execute($ci_list_clean);
        
        $nombres = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Guardar con y sin normalización para mayor compatibilidad de coincidencia
            $nombres[$row['ci']] = $row['nombre_completo'];
            $nombres[str_pad(preg_replace('/\D/', '', $row['ci']), 11, '0', STR_PAD_LEFT)] = $row['nombre_completo'];
        }
        
        echo json_encode(['success' => true, 'nombres' => $nombres]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Configuración empresa 
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. COntab. y Finanzas'
];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Datos del usuario (Para el Topbar)
$user_nombre_completo = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? '';
$user_ci = $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Obtener logo con URL absoluta
$ruta_logo = '../../images/logotn.png';
$logo_base64 = '';
$logo_url_absoluta = '';

if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
    
    // Obtener URL absoluta de la imagen
    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $ruta_web = '/images/logotn.png'; // Ruta desde la raíz del sitio web
    $logo_url_absoluta = $protocolo . $host . $ruta_web;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Exportador de Banco (BANDEC) - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Libraries CSS -->
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">

<style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

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
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            padding:1.5rem; /* Genera un margen interno para separar el contenido de los bordes */
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        /* Sidebar Windows 11 Style */
        .win-sidebar {
            position: fixed; left:0; top:0; height:100vh; width:16.25rem;
            background: var(--panel); backdrop-filter: blur(1.875rem);
            border-right: 0.0625rem solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width:5rem; }
        .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding:0.75rem; }
        .win-sidebar.collapsed .nav-item i { margin:0; font-size:1.5rem; }

        .sidebar-logo { padding:1.5rem 1.25rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); margin-bottom:1.25rem; text-align: center; }
        .sidebar-logo h3 { font-size:1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin:0; }
        .sidebar-logo small { font-size:0.7rem; color: rgba(255, 255, 255, 0.5); }

        .sidebar-nav { flex: 1; padding:0 0.75rem; }
		.nav-item {
			display: flex; align-items: center; gap:0.875rem; padding:0.75rem 1rem;
			margin-bottom:0.375rem; border-radius: 0.75rem;
			color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
			cursor: pointer; text-decoration: none;
		}

        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 0.1875rem solid #60a5fa; }
        .nav-item i { width:1.5rem; font-size:1.2rem; text-align: center; }
        .nav-item span { font-size:0.9rem; font-weight: 500; }

        /* Main Content */
        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        /* Top Bar Windows 11 */
        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        /* User Menu & Dropdowns */
        .user-menu { display: flex; align-items: center; gap:1rem; }

        .user-avatar { width:2.5rem; height:2.5rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
        .user-avatar:hover { transform: scale(1.05); }

        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(1.25rem) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important; border-radius: 0.75rem !important;
            padding:0.5rem !important; box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 0.5rem !important; padding:0.625rem 1rem !important; font-size:0.9rem !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(148, 163, 184, 0.35) !important; border-top-width: 0.0625rem !important; opacity: 1 !important; margin:0.5rem 0.25rem !important; }
        .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
        .dropdown-menu-win .dropdown-item small { font-size:0.65rem; color: rgba(255,255,255,0.6) !important; }
        .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }
        /* Divisores (hr) visibles según el tema oscuro */
        hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }

.date-badge {
    background: rgba(255, 255, 255, 0.08);
    padding:0.5rem 1rem;
    border-radius: 0.75rem;
    font-size:0.85rem;
    color: white;
}

#liveClock {
    display: inline-block;
    min-width:5.3125rem;
    text-align: center;
    font-variant-numeric: tabular-nums;
    letter-spacing:0.0312rem;
}

/* Filtros */
.filters-container {
    display: flex;
    gap:1.25rem;
    flex-wrap: wrap;
    margin-bottom:1.5rem;
}

.filter-group {
    flex: 1;
    min-width:15.625rem;
}

.filter-label {
    display: block;
    font-size:0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing:0.0312rem;
    color: #9CA3AF;
    margin-bottom:0.5rem;
}

.filter-label i {
    margin-right:0.375rem;
    color: #60a5fa;
}

.dark-select {
    width:100%;
    padding:0.75rem 1rem;
    background: var(--panel);
    border: 0.0625rem solid rgba(255, 255, 255, 0.15);
    border-radius: 0.75rem;
    color: #FFFFFF;
    font-size:0.875rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.dark-select:hover {
    border-color: #60a5fa;
    background: rgba(35, 35, 45, 0.9);
}

.dark-select:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 0.1875rem rgba(96, 165, 250, 0.2);
}

/* Overrides de visibilidad en tema oscuro (modales SweetAlert: Salvar con nombre, etc.) */
.text-muted, .text-secondary { color: #9ca3af !important; }
::placeholder { color: rgba(255, 255, 255, 0.4) !important; opacity: 1 !important; }
:-ms-input-placeholder { color: rgba(255, 255, 255, 0.4) !important; }
::-ms-input-placeholder { color: rgba(255, 255, 255, 0.4) !important; }
.form-control::placeholder { color: rgba(255, 255, 255, 0.45) !important; }
.form-control-sm::placeholder { color: rgba(255, 255, 255, 0.4) !important; font-size:0.75rem !important; }

/* Tarjetas de formato - CON MAYOR PADDING DERECHO */
.cards-row {
    display: flex;
    gap:1.25rem;
    margin-bottom:1.5rem;
    flex-wrap: wrap;
    padding-right:0.9375rem;
}

.card-option {
    flex: 1;
    background: var(--panel-2);
    backdrop-filter: blur(0.625rem);
    border: 0.125rem solid var(--border);
    border-radius: 0.75rem;
    padding:0.875rem 1rem 0.875rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.card-option:hover {
    transform: translateY(-0.125rem);
}

.card-option.selected {
    box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2);
    transform: translateY(-0.125rem);
}

.icon-option {
    font-size:1.75rem;
    margin-bottom:0.375rem;
}

/* Color base del icono según formato */
.card-option[data-format="dbf"] .icon-option { color: #2563eb; }
.card-option[data-format="xlsx"] .icon-option { color: var(--color-success); }
.card-option[data-format="xml"] .icon-option { color: #d97706; }

/* Al seleccionar, la tarjeta adopta el color del botón de exportación: icono en blanco */
.card-option[data-format="dbf"].selected .icon-option { color: #ffffff; filter: drop-shadow(0 0 0.375rem rgba(255, 255, 255, 0.5)); }
.card-option[data-format="xlsx"].selected .icon-option { color: #ffffff; filter: drop-shadow(0 0 0.375rem rgba(255, 255, 255, 0.5)); }
.card-option[data-format="xml"].selected .icon-option { color: #ffffff; filter: drop-shadow(0 0 0.375rem rgba(255, 255, 255, 0.5)); }

.card-option h5 {
    font-size:0.8125rem;
    font-weight: 600;
    margin:0 0 0.25rem 0;
    color: var(--txt);
}

.card-option p {
    font-size:0.5625rem;
    color: var(--muted);
    margin:0;
}

.badge-format {
    display: inline-block;
    margin-top:0.375rem;
    padding:0.125rem 0.5rem;
    background: rgba(96, 165, 250, 0.2);
    border-radius: 1.25rem;
    font-size:0.5rem;
    color: #60a5fa;
}

.badge-format.recomendado {
    background: rgba(var(--color-success-rgb), 0.2);
    color: #34D399;
}
/* Colores específicos para cada formato */
.card-option[data-format="dbf"]:hover {
    border-color: #2563eb;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.3), rgba(37, 99, 235, 0.08));
    box-shadow: 0 0 0 0.125rem rgba(37, 99, 235, 0.25), 0 0.625rem 1.75rem rgba(37, 99, 235, 0.25);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="dbf"]:hover .icon-option { color: #60a5fa; transform: scale(1.12); filter: drop-shadow(0 0 0.5rem rgba(37, 99, 235, 0.6)); }
.card-option[data-format="dbf"]:hover h5 { color: #bfdbfe; }
.card-option[data-format="dbf"]:hover p { color: rgba(191, 219, 254, 0.8); }
.card-option[data-format="dbf"]:hover .badge-format { background: rgba(37, 99, 235, 0.35); border: 0.0625rem solid rgba(37, 99, 235, 0.6); color: #dbeafe; }

.card-option[data-format="dbf"].selected {
    border-color: #1d4ed8;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    box-shadow: 0 0.375rem 1.25rem rgba(37, 99, 235, 0.45);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="dbf"].selected h5 { color: #ffffff; }
.card-option[data-format="dbf"].selected p { color: rgba(255, 255, 255, 0.85); }
.card-option[data-format="dbf"].selected .badge-format { background: rgba(255, 255, 255, 0.2); border: 0.0625rem solid rgba(255, 255, 255, 0.45); color: #ffffff; }
.card-option[data-format="dbf"].selected .card-checkbox { border-top: 0.0625rem solid rgba(255, 255, 255, 0.25); }
.card-option[data-format="dbf"].selected .card-checkbox label { color: rgba(255, 255, 255, 0.9); }

.card-option[data-format="xlsx"]:hover {
    border-color: var(--color-success);
    background: linear-gradient(135deg, rgba(22, 163, 74, 0.3), rgba(22, 163, 74, 0.08));
    box-shadow: 0 0 0 0.125rem rgba(22, 163, 74, 0.25), 0 0.625rem 1.75rem rgba(22, 163, 74, 0.25);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="xlsx"]:hover .icon-option { color: #34D399; transform: scale(1.12); filter: drop-shadow(0 0 0.5rem rgba(22, 163, 74, 0.6)); }
.card-option[data-format="xlsx"]:hover h5 { color: #a7f3d0; }
.card-option[data-format="xlsx"]:hover p { color: rgba(167, 243, 208, 0.8); }
.card-option[data-format="xlsx"]:hover .badge-format { background: rgba(22, 163, 74, 0.35); border: 0.0625rem solid rgba(22, 163, 74, 0.6); color: #d1fae5; }

.card-option[data-format="xlsx"].selected {
    border-color: var(--color-success);
    background: linear-gradient(135deg, var(--color-success), var(--color-success));
    box-shadow: 0 0.375rem 1.25rem rgba(22, 163, 74, 0.45);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="xlsx"].selected h5 { color: #ffffff; }
.card-option[data-format="xlsx"].selected p { color: rgba(255, 255, 255, 0.85); }
.card-option[data-format="xlsx"].selected .badge-format { background: rgba(255, 255, 255, 0.2); border: 0.0625rem solid rgba(255, 255, 255, 0.45); color: #ffffff; }
.card-option[data-format="xlsx"].selected .card-checkbox { border-top: 0.0625rem solid rgba(255, 255, 255, 0.25); }
.card-option[data-format="xlsx"].selected .card-checkbox label { color: rgba(255, 255, 255, 0.9); }

.card-option[data-format="xml"]:hover {
    border-color: #d97706;
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.3), rgba(217, 119, 6, 0.08));
    box-shadow: 0 0 0 0.125rem rgba(217, 119, 6, 0.25), 0 0.625rem 1.75rem rgba(217, 119, 6, 0.25);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="xml"]:hover .icon-option { color: #fb923c; transform: scale(1.12); filter: drop-shadow(0 0 0.5rem rgba(217, 119, 6, 0.6)); }
.card-option[data-format="xml"]:hover h5 { color: #fde68a; }
.card-option[data-format="xml"]:hover p { color: rgba(253, 230, 138, 0.8); }
.card-option[data-format="xml"]:hover .badge-format { background: rgba(217, 119, 6, 0.35); border: 0.0625rem solid rgba(217, 119, 6, 0.6); color: #fef3c7; }

.card-option[data-format="xml"].selected {
    border-color: #b45309;
    background: linear-gradient(135deg, #d97706, #b45309);
    box-shadow: 0 0.375rem 1.25rem rgba(217, 119, 6, 0.45);
    transform: translateY(-0.1875rem);
}

.card-option[data-format="xml"].selected h5 { color: #ffffff; }
.card-option[data-format="xml"].selected p { color: rgba(255, 255, 255, 0.85); }
.card-option[data-format="xml"].selected .badge-format { background: rgba(255, 255, 255, 0.2); border: 0.0625rem solid rgba(255, 255, 255, 0.45); color: #ffffff; }
.card-option[data-format="xml"].selected .card-checkbox { border-top: 0.0625rem solid rgba(255, 255, 255, 0.25); }
.card-option[data-format="xml"].selected .card-checkbox label { color: rgba(255, 255, 255, 0.9); }

/* Estilos para el checkbox de compresión dentro de cada tarjeta */
.card-checkbox {
    margin-top:0.75rem;
    font-size:0.75rem;
    text-align: center;
    border-top: 0.0625rem solid var(--border);
    padding-top:0.5rem;
}
.card-checkbox input {
    margin-right:0.375rem;
    transform: translateY(0.0625rem);
    accent-color: #60a5fa;
    width:1.125rem;
    height:1.125rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.card-checkbox input:hover {
    transform: scale(1.1);
    filter: brightness(1.2);
}
.card-checkbox label {
    color: var(--muted);
    cursor: pointer;
    transition: color 0.2s;
}
.card-checkbox label:hover {
    color: #60a5fa;
}

.icon-option {
    font-size:3rem;
    margin-bottom:1rem;
}

.card-option h5 {
    font-size:1.125rem;
    font-weight: 600;
    margin:0 0 0.5rem 0;
    color: var(--txt);
}

.card-option p {
    font-size:0.75rem;
    color: var(--muted);
    margin:0;
}

.badge-format {
    display: inline-block;
    margin-top:0.75rem;
    padding:0.25rem 0.75rem;
    background: rgba(96, 165, 250, 0.2);
    border-radius: 1.25rem;
    font-size:0.625rem;
    color: #60a5fa;
}

.badge-format.recomendado {
    background: rgba(var(--color-success-rgb), 0.2);
    color: #34D399;
}

/* Botón Exportar con colores dinámicos */
.btn-exportar {
    border: none;
    padding:0.875rem 2rem;
    font-size:1rem;
    font-weight: 600;
    border-radius: 2.5rem;
    transition: all 0.3s ease;
    color: white;
    width:100%;
    cursor: pointer;
}

.btn-exportar i {
    transition: all 0.3s ease;
}

/* DBF - Azul */
.btn-exportar.dbf {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    box-shadow: 0 0.25rem 0.9375rem rgba(37, 99, 235, 0.3);
}

.btn-exportar.dbf i {
    color: #ffffff;
    text-shadow: 0 0 0.3125rem rgba(255, 255, 255, 0.5);
}

.btn-exportar.dbf:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
    box-shadow: 0 0.5rem 1.625rem rgba(37, 99, 235, 0.55);
    transform: translateY(-0.1875rem) scale(1.02);
}

.btn-exportar.dbf:hover i {
    transform: scale(1.18);
    filter: drop-shadow(0 0 0.375rem rgba(96, 165, 250, 0.8));
}

/* Excel - Verde */
.btn-exportar.xlsx {
    background: linear-gradient(135deg, var(--color-success), var(--color-success));
    box-shadow: 0 0.25rem 0.9375rem rgba(22, 163, 74, 0.3);
}

.btn-exportar.xlsx i {
    color: #ffffff;
    text-shadow: 0 0 0.3125rem rgba(255, 255, 255, 0.5);
}

.btn-exportar.xlsx:hover {
    background: linear-gradient(135deg, var(--color-success), #166534);
    box-shadow: 0 0.5rem 1.625rem rgba(22, 163, 74, 0.55);
    transform: translateY(-0.1875rem) scale(1.02);
}

.btn-exportar.xlsx:hover i {
    transform: scale(1.18);
    filter: drop-shadow(0 0 0.375rem rgba(52, 211, 153, 0.8));
}

/* XML - Naranja */
.btn-exportar.xml {
    background: linear-gradient(135deg, #d97706, #b45309);
    box-shadow: 0 0.25rem 0.9375rem rgba(217, 119, 6, 0.3);
}

.btn-exportar.xml i {
    color: #ffffff;
    text-shadow: 0 0 0.3125rem rgba(255, 255, 255, 0.5);
}

.btn-exportar.xml:hover {
    background: linear-gradient(135deg, #b45309, #92400e);
    box-shadow: 0 0.5rem 1.625rem rgba(217, 119, 6, 0.55);
    transform: translateY(-0.1875rem) scale(1.02);
}

.btn-exportar.xml:hover i {
    transform: scale(1.18);
    filter: drop-shadow(0 0 0.375rem rgba(251, 146, 60, 0.8));
}

/* Deshabilitado */
.btn-exportar.disabled,
.btn-exportar:disabled {
    background: linear-gradient(135deg, #4b5563, #374151);
    box-shadow: none;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-exportar.disabled i,
.btn-exportar:disabled i {
    color: #9ca3af;
    text-shadow: none;
}

.btn-exportar:disabled:hover {
    transform: none;
    box-shadow: none;
}

/* Plantillas en blanco */
.plantilla-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap:0.625rem;
    margin-top:0.75rem;
}

.btn-plantilla {
    padding:0.75rem 0.625rem;
    border-radius: 0.75rem;
    border: 0.0625rem dashed rgba(96, 165, 250, 0.5);
    background: rgba(96, 165, 250, 0.08);
    color: #93c5fd;
    font-size:0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap:0.25rem;
    text-align: center;
    transition: all 0.3s ease;
}

.btn-plantilla i {
    font-size:1.3rem;
}

.btn-plantilla small {
    font-weight: 400;
    font-size:0.65rem;
    color: #64748b;
    line-height:1.3;
}

.btn-plantilla.plantilla-dbf {
    border-color: rgba(37, 99, 235, 0.5);
    background: rgba(37, 99, 235, 0.08);
    color: #93c5fd;
}

.btn-plantilla.plantilla-xlsx {
    border-color: rgba(22, 163, 74, 0.5);
    background: rgba(22, 163, 74, 0.08);
    color: #86efac;
}

.btn-plantilla.plantilla-xml {
    border-color: rgba(217, 119, 6, 0.5);
    background: rgba(217, 119, 6, 0.08);
    color: #fdba74;
}

.btn-plantilla:hover {
    transform: translateY(-0.125rem);
}

.btn-plantilla.plantilla-dbf:hover {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.55), rgba(37, 99, 235, 0.3));
    border-color: #60a5fa;
    box-shadow: 0 0.5rem 1.5rem rgba(37, 99, 235, 0.45);
    color: #bfdbfe;
}

.btn-plantilla.plantilla-dbf:hover i {
    color: #93c5fd;
    transform: scale(1.15);
    filter: drop-shadow(0 0 0.375rem rgba(147, 197, 253, 0.9));
}

.btn-plantilla.plantilla-xlsx:hover {
    background: linear-gradient(135deg, rgba(22, 163, 74, 0.55), rgba(22, 163, 74, 0.3));
    border-color: #34D399;
    box-shadow: 0 0.5rem 1.5rem rgba(22, 163, 74, 0.45);
    color: #a7f3d0;
}

.btn-plantilla.plantilla-xlsx:hover i {
    color: #86efac;
    transform: scale(1.15);
    filter: drop-shadow(0 0 0.375rem rgba(134, 239, 172, 0.9));
}

.btn-plantilla.plantilla-xml:hover {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.55), rgba(217, 119, 6, 0.3));
    border-color: #fb923c;
    box-shadow: 0 0.5rem 1.5rem rgba(217, 119, 6, 0.45);
    color: #fde68a;
}

.btn-plantilla.plantilla-xml:hover i {
    color: #fdba74;
    transform: scale(1.15);
    filter: drop-shadow(0 0 0.375rem rgba(253, 186, 116, 0.9));
}

.btn-plantilla:active {
    transform: translateY(0);
}

@media (max-width: 768px) {
    .plantilla-grid {
        grid-template-columns: 1fr;
    }
}

/* Panel de Totales */
.totals-panel {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.7));
    border: 0.0625rem solid rgba(59, 130, 246, 0.4);
    border-radius: 1rem;
    padding:1rem 1.25rem;
    margin-top:1.5rem;
}

.totals-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap:0.9375rem;
}

.total-item {
    flex: 1;
    min-width:11.25rem;
    text-align: center;
    background: var(--panel-2);
    border: 0.0625rem solid var(--border);
    border-radius: 0.75rem;
    padding:0.625rem 0.875rem;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.total-item:hover {
    border-color: rgba(59, 130, 246, 0.7);
    background: var(--card-hover);
    box-shadow: 0 0 0 0.125rem rgba(59, 130, 246, 0.15), 0 0.5rem 1.375rem rgba(59, 130, 246, 0.18);
    transform: translateY(-0.125rem);
}

.total-label {
    font-size:0.7rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing:0.0312rem;
    margin-bottom:0.25rem;
}

.total-value {
    font-size:1.1rem;
    font-weight: 700;
    color: var(--txt);
}

.total-value.positive {
    color: var(--color-success-soft);
}

.total-value.negative {
    color: #f87171;
}

.total-divider {
    width:0.0625rem;
    height:2.5rem;
    background: linear-gradient(to bottom, transparent, #3b82f6, transparent);
}

.total-item-acreditar {
    flex: 1;
    min-width:11.25rem;
    text-align: center;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.42), rgba(37, 99, 235, 0.2));
    border-radius: 0.75rem;
    padding:0.625rem 0.75rem;
    border: 0.0625rem solid rgba(96, 165, 250, 0.8);
    box-shadow: 0 0 0 0.125rem rgba(59, 130, 246, 0.25), 0 0.5rem 1.375rem rgba(59, 130, 246, 0.25);
}

.total-item-acreditar:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.55), rgba(37, 99, 235, 0.3));
    border-color: rgba(147, 197, 253, 0.9);
    box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.3), 0 0.625rem 1.625rem rgba(59, 130, 246, 0.35);
    transform: translateY(-0.125rem);
}

.total-label-acreditar {
    font-size:0.7rem;
    color: #cbd5e1;
    text-transform: uppercase;
    letter-spacing:0.0312rem;
    margin-bottom:0.25rem;
}

.total-value-acreditar {
    font-size:1.2rem;
    font-weight: 700;
    color: var(--color-success-soft);
}

.total-item-stats {
    flex: 0.8;
    min-width:10rem;
    text-align: center;
}

.stats-group {
    display: flex;
    gap:0.5rem;
    justify-content: center;
    margin-top:0.25rem;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap:0.25rem;
    padding:0.25rem 0.5rem;
    border-radius: 1.25rem;
    font-size:0.7rem;
    font-weight: 600;
}

.stat-badge.success {
    background: rgba(var(--color-success-soft-rgb), 0.38);
    color: var(--color-success-soft);
    border: 0.0625rem solid rgba(var(--color-success-soft-rgb), 0.6);
    box-shadow: 0 0.125rem 0.5rem rgba(var(--color-success-soft-rgb), 0.25);
}

.stat-badge.danger {
    background: rgba(248, 113, 113, 0.38);
    color: #f87171;
    border: 0.0625rem solid rgba(248, 113, 113, 0.6);
    box-shadow: 0 0.125rem 0.5rem rgba(248, 113, 113, 0.25);
}

.stat-badge.info {
    background: rgba(59, 130, 246, 0.38);
    color: #60a5fa;
    border: 0.0625rem solid rgba(59, 130, 246, 0.6);
    box-shadow: 0 0.125rem 0.5rem rgba(59, 130, 246, 0.25);
}

.stat-badge i {
    font-size:0.6rem;
}

/* Alert */
.alert-dark {
    background: var(--panel-2);
    backdrop-filter: blur(0.625rem);
    border: 0.0625rem solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    padding:0.875rem 1.125rem;
    color: rgba(255, 255, 255, 0.7);
    font-size:0.8125rem;
    margin-top:1.5rem;
}

.alert-dark i {
    color: #60a5fa;
    margin-right:0.625rem;
}

/* Footer */
.footer-card {
    margin-top:1.5rem;
    padding:1.25rem;
    font-size:0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.footer-card hr {
    border-color: rgba(148, 163, 184, 0.25);
    opacity: 1;
}

/* Animaciones */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(1.25rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.4s ease-out forwards;
}

/* Scrollbar */
::-webkit-scrollbar {
    width:0.5rem;
    height:0.5rem;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 0.625rem;
}

::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 0.625rem;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Estilos para el modal de previsualización */
.modal-preview-table {
    max-height:25rem;
    overflow-y: auto;
}
.modal-preview-table table {
    margin-bottom:0;
}
.modal-preview-table th {
    position: sticky;
    top:0;
    background: #1e1e2f;
    z-index: 10;
}
.preview-summary {
    background: rgba(59, 130, 246, 0.1);
    border-radius: 0.75rem;
    padding:0.625rem 0.9375rem;
    margin-bottom:1rem;
    border-left: 0.25rem solid #60a5fa;
}

/* Estilos para la fila de total en la tabla de preview */
.modal-preview-table tr.total-row {
    background-color: rgba(59, 130, 246, 0.2);
    font-weight: bold;
    border-top: 0.125rem solid #60a5fa;
}
.modal-preview-table tr.total-row td:last-child {
    color: #000000;
    font-weight: bold;
}

/* Mejorar visibilidad de modales */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.8) !important;
    backdrop-filter: blur(0.5rem);
}

.modal {
    --bs-modal-zindex:1060 !important;
}

/* Centrado vertical garantizado del modalPreview en pantalla */
#modalPreview.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
    padding:0.75rem;
}

#modalPreview .modal-dialog {
    display: flex;
    flex-direction: column;
    width:100%;
    max-height:90vh;
    margin:0 auto;
}

#modalPreview .modal-dialog.modal-dialog-centered {
    margin:0 auto;
    min-height:0;
    transform: none !important;
}

#modalPreview .modal-content {
    display: flex;
    flex-direction: column;
    max-height:90vh;
    overflow: hidden;
}

#modalPreview .modal-body {
    overflow-y: auto;
    flex: 1 1 auto;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-1.875rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#modalPreview {
    z-index: 1060 !important;
}

#modalPreview .modal-content {
    box-shadow: 0 1.5625rem 3.125rem -0.75rem rgba(0, 0, 0, 0.5);
}

/* Dropdown de exportación estilo Windows 11 */
.dropdown-export-container {
    position: relative;
}

.btn-export-dropdown {
    transition: all 0.2s ease;
}

.btn-export-dropdown:hover {
    background: rgba(50, 50, 65, 0.95) !important;
    border-color: rgba(96, 165, 250, 0.6) !important;
}

.dropdown-export-menu {
    animation: fadeInDown 0.2s ease;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-0.625rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-export-item {
    transition: all 0.2s ease;
}

.dropdown-export-item:active {
    transform: scale(0.98);
}

/* Mejorar visibilidad de SweetAlert2 */
.swal2-container {
    display: flex !important;
    position: fixed !important;
    inset:0 !important;
    align-items: center !important;
    justify-content: center !important;
    padding:1rem !important;
}

.swal2-popup {
    font-size:1rem !important;
    border-radius: 1.25rem !important;
    border: 0.0625rem solid rgba(96, 165, 250, 0.3) !important;
    box-shadow: 0 1.5625rem 3.125rem -0.75rem rgba(0, 0, 0, 0.5) !important;
    max-height:90vh !important;
    overflow-y: auto !important;
}

.swal2-title {
    font-size:1.3rem !important;
}

.swal2-html-container {
    font-size:0.9rem !important;
}

.swal2-timer-progress-bar {
    background: linear-gradient(90deg, #3b82f6, #8b5cf6) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .win-sidebar {
        transform: translateX(-100%);
    }

    .win-sidebar.mobile-open {
        transform: translateX(0);
    }

    .main-container {
        margin-left:0;
    }

    .main-container.expanded {
        margin-left:0;
    }

    .cards-row {
        flex-direction: column;
        padding-right:0;
    }

    .filters-container {
        flex-direction: column;
    }

    .totals-row {
        flex-direction: column;
        align-items: stretch;
    }

    .total-divider {
        display: none;
    }
    
    .card-option {
        padding:0.75rem 0.75rem;
    }
}

@media (max-width: 1024px) {
    .stats-group {
        flex-direction: column;
        align-items: center;
        gap:0.25rem;
    }

    .stat-badge {
        width:100%;
        justify-content: center;
    }
}
.note {
    color: #f5c542;
    background-color: rgba(255, 193, 7, 0.10);
    padding:0.375rem 0.875rem;
    border-radius: 1.25rem;
    display: inline-block;
    margin-top:0.375rem;
    font-size:0.85em;
    font-weight: 500;
    border-left: 0.1875rem solid #f5c542;
}

/* Para modo oscuro (automático) */
@media (prefers-color-scheme: dark) {
    .note {
        color: #ffd966;
        background-color: rgba(255, 193, 7, 0.07);
        border-left-color: #ffd966;
    }
}

/* Si usas clase .dark en el body */
.dark .note {
    color: #ffd966;
    background-color: rgba(255, 193, 7, 0.07);
    border-left-color: #ffd966;
}
</style>

</head>
<body>

<div class="win11-bg"></div>


<?php include '../includes/sidebar.php'; ?>
    

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    
    <!-- Top Bar Windows 11 -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1>Exportador de Banco (BANDEC)</h1>
                <p><i class="fas fa-file-export me-1"></i> Acreditación de tarjetas para pagos digitales</p>
            </div>
        </div>
        
<?php include '../includes/user_menu.php'; ?>
	</div>

    <!-- Contenido Principal -->
    <div class="glass-card fade-in-up" style="animation-delay: 0.1s;">
    <!-- Título de la sección de selección -->
    <div class="selection-header">
        <div>
            <p><i class="fas fa-sliders-h"></i> Seleccione el tipo de nómina, año y período a exportar</p>
        </div>
    </div>
<!-- Guía rápida de uso -->
<div class="alert-guide fade-in-up" style="animation-delay: 0.05s;">
    <div class="guide-header">
        <i class="fas fa-info-circle"></i>
        <span>¿Cómo usar el exportador?</span>
        <button type="button" class="guide-close" onclick="this.closest('.alert-guide').style.display='none'" title="Cerrar guía de uso" data-tooltip="Cerrar guía de uso" data-tooltip-theme="danger">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="guide-body">
        <div class="guide-steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-text">
                    <strong>Seleccione el tipo de nómina</strong>
                    <small>Automática, Extraordinaria, Vacaciones o Bono</small>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-text">
                    <strong>Seleccione el año y período</strong>
                    <small>Elija el año y luego el mes de la nómina a exportar</small>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">
                    <strong>Elija el formato de exportación</strong>
                    <small>DBF (recomendado para BANDEC), Excel o XML</small>
                </div>
            </div>
<div class="step">
    <div class="step-number">4</div>
    <div class="step-text">
        <strong>Exportar</strong>
        <small>Haga clic en el botón para generar el archivo</small>
        <small class="note"><em>También podría marcar la opción comprimido ZIP, como lo exige el sistema del banco para la importación en línea.</em></small>
    </div>
</div>
        </div>
        <div class="guide-requirements">
            <i class="fas fa-clipboard-list"></i>
            <div>
                <strong>Requisitos para exportar:</strong>
                <ul>
                    <li><i class="fas fa-check-circle" style="color: var(--color-success-soft);"></i> Nómina con estado <span class="badge-contabilizado">contabilizado</span></li>
                    <li><i class="fas fa-id-card" style="color: var(--blue-soft);"></i> Trabajadores con número de CI registrado</li>
                    <li><i class="fas fa-credit-card" style="color: var(--amber-soft);"></i> Trabajadores con Cuenta Bancaria registrada (14 o 16 dígitos)</li>
                </ul>
            </div>
        </div>

        <!-- Separador y nota de advertencia -->
        <hr style="border: none; border-top: 0.125rem solid var(--border-2); margin:1rem 0 0.75rem 0; opacity: 1;">

        <div class="note-advertencia">
            <i class="fas fa-exclamation-triangle text-warning" style="font-size:1.2rem; margin-top:0.125rem;"></i>
            <div>
                <strong>ADVERTENCIA IMPORTANTE:</strong>
                <p>
                    <strong>Fíjese bien cuáles ha exportado antes para no repetir pagos.</strong> 
                    Revise los períodos y tipos de nómina ya procesados para evitar duplicidades en las acreditaciones bancarias.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.alert-guide {
    background: var(--panel-2);
    backdrop-filter: blur(0.625rem);
    border: 0.0625rem solid rgba(var(--accent-rgb), 0.3);
    border-radius: 1rem;
    margin-bottom:1.25rem;
    overflow: hidden;
}

.guide-header {
    padding:0.75rem 1.25rem;
    background: rgba(var(--accent-rgb), 0.15);
    border-bottom: 0.0625rem solid rgba(var(--accent-rgb), 0.2);
    display: flex;
    align-items: center;
    gap:0.625rem;
    font-weight: 600;
    font-size:0.9rem;
    color: var(--accent);
}

.guide-header i:first-child {
    font-size:1rem;
}

.guide-close {
    margin-left:auto;
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    padding:0.25rem 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.guide-close:hover {
    background: rgba(var(--accent-rgb), 0.15);
    color: var(--txt);
}

.guide-body {
    padding:1rem 1.25rem;
    display: flex;
    flex-wrap: wrap;
    gap:1.5rem;
}

.guide-steps {
    flex: 2;
    display: flex;
    flex-wrap: wrap;
    gap:1rem;
}

.step {
    flex: 1;
    min-width:9.375rem;
    display: flex;
    align-items: center;
    gap:0.75rem;
    background: rgba(0, 0, 0, 0.2);
    padding:0.625rem 0.9375rem;
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.step:hover {
    background: rgba(var(--accent-rgb), 0.1);
    transform: translateX(0.1875rem);
}

.step-number {
    width:2rem;
    height:2rem;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size:1rem;
    color: white;
    flex-shrink: 0;
}

.step-text {
    display: flex;
    flex-direction: column;
}

.step-text strong {
    font-size:0.8rem;
    color: var(--txt);
}

.step-text small {
    font-size:0.65rem;
    color: var(--muted);
}

.guide-requirements {
    flex: 1;
    min-width:12.5rem;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 0.75rem;
    padding:0.75rem 1rem;
    display: flex;
    gap:0.75rem;
    border-left: 0.1875rem solid var(--accent);
}

.guide-requirements > i {
    font-size:1.5rem;
    color: var(--accent);
    opacity: 0.7;
}

.guide-requirements ul {
    margin:0.375rem 0 0 0;
    padding-left:1.125rem;
}

.guide-requirements li {
    font-size:0.7rem;
    color: var(--muted);
    margin:0.25rem 0;
}

.guide-requirements li i {
    margin-right:0.375rem;
    width:1rem;
}

.badge-contabilizado {
    display: inline-block;
    background: rgba(var(--color-success-rgb), 0.2);
    color: var(--color-success-soft);
    padding:0.125rem 0.5rem;
    border-radius: 1.25rem;
    font-size:0.65rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .guide-body {
        flex-direction: column;
    }
    
    .step {
        min-width:100%;
    }
}

/* ===== Advertencia (temas oscuros) ===== */
.note-advertencia {
    display: flex;
    align-items: flex-start;
    gap:0.75rem;
    padding:0.625rem 1rem;
    background: rgba(239, 68, 68, 0.08);
    border-left: 0.25rem solid #ef4444;
    border-radius: 0.625rem;
}
.note-advertencia strong {
    color: #fca5a5;
    font-size:0.85rem;
}
.note-advertencia p {
    margin:0.125rem 0 0 0;
    font-size:0.8rem;
    color: #fca5a5;
}

/* ===== Tema claro ===== */
[data-theme="light"] .alert-guide {
    background: var(--panel-2);
    border-color: rgba(var(--accent-rgb), 0.35);
    box-shadow: 0 0.125rem 0.75rem rgba(0, 0, 0, 0.06);
}
[data-theme="light"] .guide-header {
    color: var(--accent-dark);
}
[data-theme="light"] .guide-close:hover {
    background: rgba(var(--accent-rgb), 0.12);
    color: #1f2937;
}
[data-theme="light"] .step {
    background: rgba(0, 0, 0, 0.05);
}
[data-theme="light"] .step:hover {
    background: rgba(var(--accent-rgb), 0.12);
}
[data-theme="light"] .guide-requirements {
    background: rgba(0, 0, 0, 0.04);
    border-left-color: var(--accent-dark);
}
[data-theme="light"] .guide-requirements > i {
    color: var(--accent-dark);
}
[data-theme="light"] .note-advertencia {
    background: rgba(239, 68, 68, 0.07);
}
[data-theme="light"] .note-advertencia strong,
[data-theme="light"] .note-advertencia p {
    color: #b91c1c;
}
[data-theme="light"] .note {
    color: #92400e;
    background-color: rgba(245, 158, 11, 0.12);
    border-left-color: #d97706;
}

@keyframes cuadrePulse {
    0%, 100% { color: #f87171; text-shadow: 0 0 0.375rem rgba(248,113,113,0.5); }
    50% { color: #fca5a5; text-shadow: 0 0 1.25rem rgba(248,113,113,1), 0 0 2.5rem rgba(248,113,113,0.4); }
}
</style>
        <!-- Filtros con Años -->
        <div class="filters-container">
            <div class="filter-group">
                <div class="filter-label">
                    <i class="fas fa-tag"></i> TIPO DE NÓMINA
                </div>
                <select id="tipoNominaSelect" class="dark-select" title="Seleccionar tipo de nómina" data-tooltip="Seleccionar tipo de nómina" data-tooltip-theme="primary">
                    <option value="">-- Seleccione un tipo --</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="filter-label">
                    <i class="fas fa-calendar-alt"></i> AÑO
                </div>
                <select id="anioSelect" class="dark-select" title="Seleccionar año de la nómina" data-tooltip="Seleccionar año de la nómina" data-tooltip-theme="primary">
                    <option value="">-- Seleccione un año --</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="filter-label">
                    <i class="fas fa-calendar-week"></i> PERÍODO
                </div>
                <select id="periodoSelect" class="dark-select" title="Seleccionar período contable a exportar" data-tooltip="Seleccionar período a exportar" data-tooltip-theme="primary">
                    <option value="">-- Seleccione tipo y año --</option>
                </select>
            </div>
        </div>

        <div class="filter-label mt-3">
            <i class="fas fa-file"></i> FORMATO DE EXPORTACIÓN
        </div>
<div class="cards-row">
    <div class="card-option" data-format="dbf">
        <i class="fas fa-database icon-option"></i>
        <h5>DBF</h5>
        <p>dBase III PLUS</p>
        <span class="badge-format recomendado"><i class="fas fa-star me-1"></i>Recomendado</span>
        <div class="card-checkbox">
            <input type="checkbox" class="zip-checkbox" id="zip_dbf" data-format="dbf">
            <label for="zip_dbf"><i class="fas fa-file-archive"></i> Comprimido (ZIP)</label>
        </div>
    </div>
    <div class="card-option" data-format="xlsx">
        <i class="fas fa-file-excel icon-option"></i>
        <h5>Excel (XLSX)</h5>
        <p>Microsoft Excel</p>
        <span class="badge-format"><i class="fas fa-table me-1"></i>Alternativo</span>
        <div class="card-checkbox">
            <input type="checkbox" class="zip-checkbox" id="zip_xlsx" data-format="xlsx">
            <label for="zip_xlsx"><i class="fas fa-file-archive"></i> Comprimido (ZIP)</label>
        </div>
    </div>
    <div class="card-option" data-format="xml">
        <i class="fas fa-code icon-option"></i>
        <h5>XML</h5>
        <p>Formato estructurado</p>
        <span class="badge-format"><i class="fas fa-file-code me-1"></i>Alternativo</span>
        <div class="card-checkbox">
            <input type="checkbox" class="zip-checkbox" id="zip_xml" data-format="xml">
            <label for="zip_xml"><i class="fas fa-file-archive"></i> Comprimido (ZIP)</label>
        </div>
    </div>
</div>
        <input type="hidden" id="formatoSeleccionado" value="">

        <button class="btn-exportar" id="btnExportar">
            <i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa</span>
        </button>

        <div class="plantilla-grid">
            <button type="button" class="btn-plantilla plantilla-dbf" id="btnPlantillaDbf" data-plantilla="dbf">
                <i class="fas fa-database"></i>
                <span>Plantilla DBF</span>
                <small>nomina.dbf · NID, CUENTA, IMPORTE</small>
            </button>
            <button type="button" class="btn-plantilla plantilla-xlsx" id="btnPlantillaXlsx" data-plantilla="xlsx">
                <i class="fas fa-file-excel"></i>
                <span>Plantilla Excel</span>
                <small>nomina.xlsx · NID, CUENTA, IMPORTE</small>
            </button>
            <button type="button" class="btn-plantilla plantilla-xml" id="btnPlantillaXml" data-plantilla="xml">
                <i class="fas fa-code"></i>
                <span>Plantilla XML</span>
                <small>nomina.xml · NID, CUENTA, IMPORTE</small>
            </button>
        </div>

<!-- PANEL DE TOTALES - TODO EN UNA SOLA FILA -->
<div class="totals-panel" id="totalsPanel" style="display: none;">
    <div class="totals-row">
        <div class="total-item">
            <div class="total-label">TOTAL DEVENGADO GENERAL</div>
            <div class="total-value positive" id="totalDevengado">$0.00</div>
        </div>
        <div class="total-divider"></div>
        <div class="total-item">
            <div class="total-label">MENOS TOTAL DEDUCCIONES</div>
            <div class="total-value negative" id="totalDeducciones">$0.00</div>
        </div>
        <div class="total-divider"></div>
        <div class="total-item">
            <div class="total-label">MENOS SIN TARJETAS</div>
            <div class="total-value negative" id="totalSinTarjetaMonto">$0.00</div>
        </div>
        <div class="total-divider"></div>
        <div class="total-item-acreditar">
            <div class="total-label-acreditar">IMPORTE A ACREDITAR</div>
            <div class="total-value-acreditar" id="importeAcreditar">$0.00</div>
        </div>
        <div class="total-divider"></div>
        <!-- DESGLOSE DE TRABAJADORES EN LA MISMA LÍNEA -->
        <div class="total-item-stats">
            <div class="total-label">TOTAL TRABAJADORES EN NÓMINA</div>
            <div class="stats-group">
                <div class="stat-badge success" title="Con Tarjeta">
                    <i class="fas fa-check-circle"></i> <span id="conTarjetaCount">0</span>
                </div>
                <div class="stat-badge danger" title="Sin Tarjeta">
                    <i class="fas fa-times-circle"></i> <span id="sinTarjetaCount">0</span>
                </div>
                <div class="stat-badge info" title="Total en Nómina">
                    <i class="fas fa-users"></i> <span id="totalNominaCount">0</span>
                </div>
            </div>
        </div>
    </div>
</div>
    
<?php include '../includes/footer.php'; ?>
</div>

<!-- Modal de previsualización (más angosto y con botón imprimir) -->
<div class="modal fade" id="modalPreview" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content" style="background: var(--card); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(96, 165, 250, 0.3); border-radius: 1.25rem; color: var(--txt);">
            <div class="modal-header d-flex justify-content-between align-items-center" style="border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.2);">
                <h5 class="modal-title" id="modalPreviewLabel">
                    <i class="fas fa-eye me-2" style="color: #60a5fa;"></i> Previsualización de la exportación
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <!-- Dropdown personalizado de exportación -->
                    <div class="dropdown-export-container">
                        <button class="btn-export-dropdown" id="exportDropdownBtn" data-tooltip="Exportar vista" data-tooltip-theme="info" style="background: rgba(30, 30, 45, 0.9); border: 0.0625rem solid rgba(96, 165, 250, 0.4); border-radius: 0.5rem; padding:0.375rem 0.75rem; color: white; cursor: pointer; display: flex; align-items: center; gap:0.5rem; font-size:0.8rem;">
                            <i class="fas fa-download"></i>
                            <span>Exportar vista</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-export-menu" id="exportDropdownMenu" style="position: absolute; top:100%; right:0; background: rgba(30, 30, 45, 0.98); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(96, 165, 250, 0.3); border-radius: 0.75rem; min-width:11.25rem; z-index: 1070; display: none; margin-top:0.3125rem; box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.4); overflow: hidden;">
                            <div class="dropdown-export-item" data-format="excel" style="padding:0.625rem 1rem; display: flex; align-items: center; gap:0.75rem; cursor: pointer; transition: all 0.2s; color: white; font-size:0.8rem;">
                                <i class="fas fa-file-excel" style="color: var(--color-success); width:1.25rem;"></i>
                                <span>Excel (.xlsx)</span>
                            </div>
                            <div class="dropdown-export-item" data-format="word" style="padding:0.625rem 1rem; display: flex; align-items: center; gap:0.75rem; cursor: pointer; transition: all 0.2s; color: white; font-size:0.8rem; border-top: 0.0625rem solid rgba(255,255,255,0.1);">
                                <i class="fas fa-file-word" style="color: #3b82f6; width:1.25rem;"></i>
                                <span>Word (.docx)</span>
                            </div>
                            <div class="dropdown-export-item" data-format="pdf" style="padding:0.625rem 1rem; display: flex; align-items: center; gap:0.75rem; cursor: pointer; transition: all 0.2s; color: white; font-size:0.8rem; border-top: 0.0625rem solid rgba(255,255,255,0.1);">
                                <i class="fas fa-file-pdf" style="color: #ef4444; width:1.25rem;"></i>
                                <span>PDF (.pdf)</span>
                            </div>
                            <div class="dropdown-export-item" data-format="csv" style="padding:0.625rem 1rem; display: flex; align-items: center; gap:0.75rem; cursor: pointer; transition: all 0.2s; color: white; font-size:0.8rem; border-top: 0.0625rem solid rgba(255,255,255,0.1);">
                                <i class="fas fa-file-csv" style="color: #f59e0b; width:1.25rem;"></i>
                                <span>CSV (.csv)</span>
                            </div>
                            <div class="dropdown-export-item" data-format="txt" style="padding:0.625rem 1rem; display: flex; align-items: center; gap:0.75rem; cursor: pointer; transition: all 0.2s; color: white; font-size:0.8rem; border-top: 0.0625rem solid rgba(255,255,255,0.1);">
                                <i class="fas fa-file-alt" style="color: #9ca3af; width:1.25rem;"></i>
                                <span>TXT (.txt)</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="previewLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-pulse fa-3x" style="color: #60a5fa;"></i>
                    <p class="mt-3">Cargando datos a exportar...</p>
                </div>
                <div id="previewContent" style="display: none;">
                    <div class="preview-summary d-flex flex-wrap justify-content-between align-items-center">
                        <div><i class="fas fa-users me-2"></i> <strong>Total trabajadores c/Tarjeta:</strong> <span id="previewTotalRegistros">0</span></div>
                        <div><i class="fas fa-dollar-sign me-2"></i> <strong>Suma total acreditar:</strong> <span id="previewTotalImporte">$0.00</span></div>
                        <div><i class="fas fa-info-circle me-2"></i> <span id="previewAdvertencia" class="text-warning small"></span></div>
                    </div>
                    <div class="modal-preview-table">
                        <table class="table table-dark table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>CI / NID</th>
                                    <th>Cuenta Bancaria</th>
                                    <th>Importe (CUP)</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody">
                                <!-- filas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(96, 165, 250, 0.2);">
                <button type="button" class="btn btn-info" id="printPreviewBtn" data-tooltip="Imprimir" data-tooltip-theme="info">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-success" id="confirmExportBtn" data-tooltip="Confirmar exportación" data-tooltip-theme="success">
                    <i class="fas fa-check-circle me-2"></i>Confirmar exportación
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>

<!-- Dependencias de Exportación (Ordenadas antes de los scripts de impresión) -->
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>

<script>

// ==========================================
// VARIABLES GLOBALES PARA IMPRESIÓN
// ==========================================
var logoBase64 = '<?php echo $logo_base64; ?>';
var nombreEmpresa = '<?php echo addslashes($config_empresa['nombre_empresa']); ?>';
var jefeProyecto = '<?php echo addslashes($config_empresa['jefe_proyecto']); ?>';
var especialistaGestion = '<?php echo addslashes($config_empresa['especialista_gestion']); ?>';
var periodoTexto = '';
var tipoNominaTexto = '';
var PRINT_TOOLBAR_HTML = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style><div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">'
    + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
    + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#16a34a\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#22c55e\';this.style.transform=\'translateY(0)\';">'
    + '🖨️ Imprimir</button>'
    + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#ef4444\';this.style.transform=\'translateY(0)\';">'
    + '✖ Cerrar</button>'
    + ''
    + '</div><div style="height:3.4375rem;"></div>'
    + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';

// ==========================================
// AUXILIAR: ARREGLAR HORA EN FORMATO 12 HORAS CON SEGUNDOS
// ==========================================
function obtenerFechaHora12H() {
    const ahora = new Date();
    const opciones = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    return ahora.toLocaleString('es-ES', opciones);
}

// ==========================================
// RELOJ EN VIVO
// ==========================================
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const clockElement = document.getElementById('liveClock');
    if (clockElement) clockElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}

// ==========================================
// FORMATO MONEDA
// ==========================================
function formatMoney(value) {
    return new Intl.NumberFormat('es-CU', {
        style: 'currency',
        currency: 'CUP',
        minimumFractionDigits: 2
    }).format(value);
}

// ==========================================
// FUNCIÓN PARA HABILITAR/DESHABILITAR CARDS SEGÚN PERÍODO VÁLIDO
// ==========================================
function toggleFormatCards(habilitar) {
    const cards = document.querySelectorAll('.card-option');
    const checkboxes = document.querySelectorAll('.zip-checkbox');
    
    cards.forEach(card => {
        if (habilitar) {
            card.style.opacity = '1';
            card.style.cursor = 'pointer';
            card.style.pointerEvents = 'auto';
        } else {
            card.style.opacity = '0.5';
            card.style.cursor = 'not-allowed';
            card.style.pointerEvents = 'none';
        }
    });
    
    checkboxes.forEach(cb => {
        if (habilitar) {
            cb.disabled = false;
            cb.style.opacity = '1';
        } else {
            cb.disabled = true;
            cb.style.opacity = '0.5';
        }
    });
    
    // Si se deshabilitan los cards, limpiar selección
    if (!habilitar) {
        cards.forEach(c => c.classList.remove('selected'));
        const formatoSeleccionadoInput = document.getElementById('formatoSeleccionado');
        if (formatoSeleccionadoInput) formatoSeleccionadoInput.value = '';
        window.formatoSeleccionado = '';
        const btnExportar = document.getElementById('btnExportar');
        if (btnExportar) {
            btnExportar.disabled = true;
            btnExportar.classList.remove('dbf', 'xlsx', 'xml');
            btnExportar.innerHTML = '<i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa</span>';
        }
    }
}

// ==========================================
// VERIFICAR SELECCIÓN DE PERÍODO VÁLIDA
// ==========================================
function verificarSeleccionPeriodoValida() {
    const tipoNomina = document.getElementById('tipoNominaSelect').value;
    const anio = document.getElementById('anioSelect').value;
    const periodoData = document.getElementById('periodoSelect').value;
    
    const tieneTipo = tipoNomina && tipoNomina !== '';
    const tieneAnio = anio && anio !== '';
    const tienePeriodo = periodoData && periodoData !== '';
    
    const periodoValido = tieneTipo && tieneAnio && tienePeriodo;
    
    // Habilitar/Deshabilitar cards según período válido
    toggleFormatCards(periodoValido);
    
    return periodoValido;
}

function calcularTotales() {
    const tipoNomina = document.getElementById('tipoNominaSelect').value;
    const periodoData = document.getElementById('periodoSelect').value;
    
    if (!tipoNomina || !periodoData) {
        const totalsPanel = document.getElementById('totalsPanel');
        if (totalsPanel) totalsPanel.style.display = 'none';
        return;
    }
    
    let periodo;
    try {
        periodo = JSON.parse(periodoData);
    } catch(e) {
        return;
    }
    
    // Mostrar loading
    const totalsPanel = document.getElementById('totalsPanel');
    if (totalsPanel) totalsPanel.style.display = 'block';
    const totalDevengado = document.getElementById('totalDevengado');
    const totalDeducciones = document.getElementById('totalDeducciones');
    const totalSinTarjetaMonto = document.getElementById('totalSinTarjetaMonto');
    const importeAcreditar = document.getElementById('importeAcreditar');
    const conTarjetaCount = document.getElementById('conTarjetaCount');
    const sinTarjetaCount = document.getElementById('sinTarjetaCount');
    const totalNominaCount = document.getElementById('totalNominaCount');
    
    if (totalDevengado) totalDevengado.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (totalDeducciones) totalDeducciones.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (totalSinTarjetaMonto) totalSinTarjetaMonto.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (importeAcreditar) importeAcreditar.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (conTarjetaCount) conTarjetaCount.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (sinTarjetaCount) sinTarjetaCount.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    if (totalNominaCount) totalNominaCount.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    
    let formData = new FormData();
    formData.append('accion', 'calcular_totales');
    formData.append('periodo_desde', periodo.desde);
    formData.append('periodo_hasta', periodo.hasta);
    formData.append('tipo_nomina', tipoNomina);
    
fetch('exportar_bandec.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // NUEVO: Guardar valores numéricos crudos para la hoja de impresión
            window.rawTotals = {
                total_devengado: parseFloat(data.total_devengado || 0),
                total_deducciones: parseFloat(data.total_deducciones || 0),
                total_sin_tarjeta_monto: parseFloat(data.total_sin_tarjeta_monto || 0),
                importe_acreditar: parseFloat(data.total_devengado || 0) - parseFloat(data.total_deducciones || 0) - parseFloat(data.total_sin_tarjeta_monto || 0),
                con_tarjeta_count: parseInt(data.con_tarjeta_count || 0),
                sin_tarjeta_count: parseInt(data.sin_tarjeta_count || 0),
                total_nomina_count: parseInt(data.total_nomina_count || 0)
            };

            if (totalDevengado) totalDevengado.innerHTML = formatMoney(data.total_devengado || 0);
            if (totalDeducciones) totalDeducciones.innerHTML = formatMoney(data.total_deducciones || 0);
            if (totalSinTarjetaMonto) totalSinTarjetaMonto.innerHTML = formatMoney(data.total_sin_tarjeta_monto || 0);
            
            let importeAcreditarVal = (data.total_devengado || 0) - (data.total_deducciones || 0) - (data.total_sin_tarjeta_monto || 0);
            if (importeAcreditar) importeAcreditar.innerHTML = formatMoney(importeAcreditarVal);
            
            if (conTarjetaCount) conTarjetaCount.innerHTML = data.con_tarjeta_count || 0;
            if (sinTarjetaCount) sinTarjetaCount.innerHTML = data.sin_tarjeta_count || 0;
            if (totalNominaCount) totalNominaCount.innerHTML = data.total_nomina_count || 0;
        } else {
            if (totalsPanel) totalsPanel.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error al calcular totales:', error);
        if (totalsPanel) totalsPanel.style.display = 'none';
    });
    
    // Verificar si la nómina está contabilizada para habilitar/deshabilitar botón
    verificarNominaContabilizada();
}

// ==========================================
// ACTUALIZAR TEXTO DEL BOTÓN SEGÚN FORMATO
// ==========================================
function actualizarTextoBoton(formato) {
    const btnExportar = document.getElementById('btnExportar');
    const btnTexto = document.getElementById('btnTexto');
    const textos = {
        'dbf': 'Exportar Nómina Acreditativa (DBF)',
        'xlsx': 'Exportar Nómina Acreditativa (Excel)',
        'xml': 'Exportar Nómina Acreditativa (XML)'
    };
    
    if (btnTexto) {
        btnTexto.textContent = textos[formato] || 'Exportar Nómina Acreditativa';
    }
    
    // Cambiar la clase del botón según el formato seleccionado
    if (btnExportar) {
        btnExportar.classList.remove('dbf', 'xlsx', 'xml');
        if (formato && !btnExportar.disabled) {
            btnExportar.classList.add(formato);
        }
    }
}

// ==========================================
// LOGOUT
// ==========================================
function cerrarSesion() {
    fetch('../logout.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(() => { window.location.href = '../login.php?logout=1'; })
        .catch(() => { window.location.href = '../login.php?logout=1'; });
}

const logoutLogic = (e) => {
    e.preventDefault();
    Swal.fire({
        title: '<i class="fas fa-sign-out-alt" style="color: #ef4444"></i> Cerrar sesión',
        text: '¿Está seguro que desea salir del sistema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#D13438',
        cancelButtonColor: '#2D2D2D',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, salir',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1F1F1F',
        color: '#FFFFFF'
    }).then((result) => { if (result.isConfirmed) cerrarSesion(); });
};

// ==========================================
// AUXILIAR: ARREGLO INTEGRADO DE DATOS (CON NOMBRES RESOLVIDOS)
// ==========================================
async function obtenerDatosCompletosExportacion() {
    const registros = window.previewData?.registros || [];
    if (registros.length === 0) return [];

    // Recopilar CIs únicos
    const ciArray = registros.map(r => r.ci.toString().replace(/\D/g, '').padStart(11, '0')).filter((v, i, a) => a.indexOf(v) === i);
    let nombresMapa = {};

    if (ciArray.length > 0) {
        try {
            let formDataNombres = new FormData();
            formDataNombres.append('accion_ajax', 'obtener_nombres');
            formDataNombres.append('ci_list', JSON.stringify(ciArray));

            const response = await fetch(window.location.href, { method: 'POST', body: formDataNombres });
            const data = await response.json();
            if (data.success) {
                nombresMapa = data.nombres;
            }
        } catch (e) {
            console.error("Error al obtener nombres para exportación:", e);
        }
    }

    return registros.map((r, idx) => {
        const ciClean = r.ci.toString().replace(/\D/g, '').padStart(11, '0');
        const nombre = nombresMapa[ciClean] || r.nombre_completo || 'No especificado';
        const cuenta = r.cuentabanc && r.cuentabanc.trim() !== '' ? r.cuentabanc : '--';
        return {
            no: idx + 1,
            ci: r.ci,
            nombre: nombre,
            cuenta: cuenta,
            importe: parseFloat(r.importe || 0)
        };
    });
}

// ==========================================
// FUNCIÓN UNIFICADA PARA IMPRIMIR LA PREVISUALIZACIÓN (3 SECCIONES)
// ==========================================
async function imprimirPreview() {
    if (!window.previewData || !window.rawTotals) {
        await Swal.fire({
            icon: 'warning',
            title: 'No hay datos para imprimir',
            text: 'No se puede imprimir porque no hay registros válidos.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }

    // 1. Obtener datos de cabecera y configuración
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTexto = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTexto = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    const formato = window.formatoSeleccionado ? window.formatoSeleccionado.toUpperCase() : 'No seleccionado';
    const empresa = nombreEmpresa || '<?php echo defined('COMPANY_NAME') ? addslashes(COMPANY_NAME) : 'SisGesNom'; ?>';
    const especialista = especialistaGestion || 'No especificado';
    const jefe = jefeProyecto || 'No especificado';
    const logo = logoBase64 || '';
    const fechaHora = obtenerFechaHora12H();

    // 2. Extraer cifras numéricas desde la memoria global
    const totalDeduccionesGeneral = window.rawTotals.total_deducciones; // DEDUCCIONES DE TODOS
    const acredVal = window.rawTotals.importe_acreditar;
    const conTarjetaCountVal = window.rawTotals.con_tarjeta_count;
    const sinTarjetaCountVal = window.rawTotals.sin_tarjeta_count;
    const totalNominaCountVal = window.rawTotals.total_nomina_count;
    const devengadoGeneralVal = window.rawTotals.total_devengado;

    const registrosConTarjeta = window.previewData.registros || [];
    const registrosSinTarjeta = window.previewData.registros_sin_tarjeta || [];

    // Calcular deducciones y netos reales de trabajadores sin tarjeta
    let dedSinTarjeta = 0;
    let netoSinTarjeta = 0;
    
    registrosSinTarjeta.forEach(r => {
        dedSinTarjeta += parseFloat(r.deducciones || 0);
        netoSinTarjeta += parseFloat(r.neto || 0);
    });

    // CORRECCIÓN: La deducción CON TARJETA es la diferencia
    const dedConTarjetaVal = totalDeduccionesGeneral - dedSinTarjeta;

    // El Devengado SIN TARJETA es la suma del Neto + Deducciones
    const devSinTarjetaVal = netoSinTarjeta + dedSinTarjeta;

    // El importe líquido (efectivo) es solo el neto
    const efectivoVal = netoSinTarjeta;

    // Re-calculamos el devengado con tarjeta
    const devConTarjeta = devengadoGeneralVal - devSinTarjetaVal;

    const FILAS_POR_PAGINA = 32;
    let htmlFinal = '';

    // ==========================================
    // SECCIÓN 1: TRABAJADORES CON TARJETA
    // ==========================================
    const totalPaginasCon = Math.max(1, Math.ceil(registrosConTarjeta.length / FILAS_POR_PAGINA));
    let sumaConTarjeta = 0;

    for (let pag = 0; pag < totalPaginasCon; pag++) {
        const inicio = pag * FILAS_POR_PAGINA;
        const fin = Math.min(inicio + FILAS_POR_PAGINA, registrosConTarjeta.length);
        const chunk = registrosConTarjeta.slice(inicio, fin);

        let filasHtml = '';
        chunk.forEach((reg, index) => {
            const num = inicio + index + 1;
            const importeNum = parseFloat(reg.importe || 0);
            sumaConTarjeta += importeNum;
            filasHtml += `
                <tr>
                    <td style="text-align: center;">${num}</td>
                    <td style="text-align: center;">${reg.ci}</td>
                    <td style="text-align: left;">${reg.nombre_completo || 'No especificado'}</td>
                    <td style="text-align: center;">${reg.cuentabanc || '--'}</td>
                    <td style="text-align: right;">${formatMoney(importeNum)}</td>
                </tr>
            `;
        });

        if (pag === totalPaginasCon - 1) {
            filasHtml += `
                <tr class="total-row" style="background-color: #e6f0ff; font-weight: bold;">
                    <td colspan="4" style="text-align: right; font-weight: bold;">TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCountVal})</td>
                    <td style="text-align: right; font-weight: bold;">${formatMoney(sumaConTarjeta)}</td>
                </tr>
            `;
        }

        htmlFinal += `
            <div class="page-sheet">
                <div class="print-header">
                    <div class="logo-area">
                        ${logo ? `<img src="${logo}" alt="Logo">` : ''}
                    </div>
                    <div class="header-text">
                        <h1>${escapeHtml(empresa)}</h1>
                        <h2>Acreditación BANDEC - Trabajadores CON Tarjeta</h2>
                        <p>${escapeHtml(tipoNominaTexto)} · Período: ${escapeHtml(periodoTexto)} · Formato: ${escapeHtml(formato)}</p>
                    </div>
                    <div class="header-right">
                        <strong>Especialista:</strong> ${escapeHtml(especialista)}<br>
                        <strong>Jefe de Proyecto:</strong> ${escapeHtml(jefe)}<br>
                        <span>${fechaHora}</span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th style="width:5%; text-align: center;">No.</th>
                                <th style="width:15%; text-align: center;">CI / NID</th>
                                <th style="width:40%; text-align: left;">Nombre del Trabajador</th>
                                <th style="width:25%; text-align: center;">Cuenta Bancaria</th>
                                <th style="width:15%; text-align: right;">Importe (CUP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filasHtml || '<tr><td colspan="5" style="text-align: center;">No hay registros</td></tr>'}
                        </tbody>
                    </table>
                </div>

                <div class="print-footer">
                    <div class="footer-left">Nómina - Distribución Electrónica</div>
                    <div class="footer-center">Página ${pag + 1} de ${totalPaginasCon} (Sección 1)</div>
                    <div class="footer-right">Generado el: ${fechaHora}</div>
                </div>
            </div>
        `;
    }

    // ==========================================
    // SECCIÓN 2: TRABAJADORES SIN TARJETA
    // ==========================================
    const totalPaginasSin = Math.max(1, Math.ceil(registrosSinTarjeta.length / FILAS_POR_PAGINA));
    let sumaSinTarjeta = 0;

    for (let pag = 0; pag < totalPaginasSin; pag++) {
        const inicio = pag * FILAS_POR_PAGINA;
        const fin = Math.min(inicio + FILAS_POR_PAGINA, registrosSinTarjeta.length);
        const chunk = registrosSinTarjeta.slice(inicio, fin);

        let filasHtml = '';
        chunk.forEach((reg, index) => {
            const num = inicio + index + 1;
            const importeNum = parseFloat(reg.neto || 0);
            sumaSinTarjeta += importeNum;
            filasHtml += `
                <tr>
                    <td style="text-align: center;">${num}</td>
                    <td style="text-align: center;">${reg.ci}</td>
                    <td style="text-align: left;">${reg.nombre_completo || 'No especificado'}</td>
                    <td style="text-align: center; color: #777;"></td>
                    <td style="text-align: right;">${formatMoney(importeNum)}</td>
                </tr>
            `;
        });

        if (pag === totalPaginasSin - 1) {
            filasHtml += `
                <tr class="total-row" style="background-color: #ffebe6; font-weight: bold;">
                    <td colspan="4" style="text-align: right; font-weight: bold;">TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCountVal})</td>
                    <td style="text-align: right; font-weight: bold;">${formatMoney(sumaSinTarjeta)}</td>
                </tr>
            `;
        }

        htmlFinal += `
            <div class="page-sheet">
                <div class="print-header">
                    <div class="logo-area">
                        ${logo ? `<img src="${logo}" alt="Logo">` : ''}
                    </div>
                    <div class="header-text">
                        <h1>${escapeHtml(empresa)}</h1>
                        <h2>Control de Nómina - Trabajadores SIN Tarjeta</h2>
                        <p>${escapeHtml(tipoNominaTexto)} · Período: ${escapeHtml(periodoTexto)} · Pago por Caja</p>
                    </div>
                    <div class="header-right">
                        <strong>Especialista:</strong> ${escapeHtml(especialista)}<br>
                        <strong>Jefe de Proyecto:</strong> ${escapeHtml(jefe)}<br>
                        <span>${fechaHora}</span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th style="width:5%; text-align: center;">No.</th>
                                <th style="width:15%; text-align: center;">CI / NID</th>
                                <th style="width:40%; text-align: left;">Nombre del Trabajador</th>
                                <th style="width:25%; text-align: center;">Vía de Pago</th>
                                <th style="width:15%; text-align: right;">Importe (CUP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filasHtml || '<tr><td colspan="5" style="text-align: center;">No hay registros</td></tr>'}
                        </tbody>
                    </table>
                </div>

                <div class="print-footer">
                    <div class="footer-left">Nómina - Distribución Física</div>
                    <div class="footer-center">Página ${pag + 1} de ${totalPaginasSin} (Sección 2)</div>
                    <div class="footer-right">Generado el: ${fechaHora}</div>
                </div>
            </div>
        `;
    }

    // ==========================================
    // SECCIÓN 3: HOJA RESUMEN DE TOTALES
    // ==========================================
    htmlFinal += `
        <div class="page-sheet">
            <div class="print-header">
                <div class="logo-area">
                    ${logo ? `<img src="${logo}" alt="Logo">` : ''}
                </div>
                <div class="header-text">
                    <h1>${escapeHtml(empresa)}</h1>
                    <h2>RESUMEN CONSOLIDADO DE CIERRE NOMINAL</h2>
                    <p>${escapeHtml(tipoNominaTexto)} · Período: ${escapeHtml(periodoTexto)}</p>
                </div>
                <div class="header-right">
                    <strong>Especialista:</strong> ${escapeHtml(especialista)}<br>
                    <strong>Jefe de Proyecto:</strong> ${escapeHtml(jefe)}<br>
                    <span>${fechaHora}</span>
                </div>
            </div>

            <!-- Identificadores de la Nómina -->
            <div style="font-size:10pt; line-height:1.6; margin-bottom:1.5625rem; border-bottom: 0.0625rem solid #ddd; padding-bottom:0.9375rem;">
                <div>Tipo de Nómina: <strong style="text-transform: capitalize;">${tipoNominaTexto}</strong></div>
                <div>Período de Pago: <strong><u>${periodoTexto}</u></strong></div>
            </div>

            <div class="summary-container" style="flex-grow: 1;">
                <!-- 1. RESUMEN FINANCIERO GENERAL -->
                <h3 style="font-size:10.5pt; font-weight: bold; margin-bottom:0.9375rem; color: #111;">1. RESUMEN FINANCIERO GENERAL</h3>
                
                <table style="width:100%; border-collapse: collapse; font-size:9.5pt; margin-bottom:1.875rem; line-height:1.6;">
                    <!-- Fila Devengado General -->
                    <tr style="border-bottom: 0.125rem solid #000; font-weight: bold;">
                        <td style="padding:0.5rem 0; color: #111;">TOTAL DEVENGADO GENERAL (NÓMINA COMPLETA)</td>
                        <td style="padding:0.5rem 0; text-align: right; font-size:10pt;">${formatMoney(devengadoGeneralVal)}</td>
                    </tr>
                    
                    <!-- Bloque Trabajadores CON TARJETA -->
                    <tr style="font-weight: bold;">
                        <td style="padding:0.625rem 0 0.25rem 0; color: #111;">TOTAL DEVENGADO GENERAL DE TRABAJADORES (CON TARJETA)</td>
                        <td style="padding:0.625rem 0 0.25rem 0; text-align: right;">${formatMoney(devConTarjeta)}</td>
                    </tr>
                    <tr>
                        <td style="padding:0.25rem 0; color: #555; padding-left:1.25rem;">(-) Deducciones generales aplicadas (Trabajadores con Tarjeta)</td>
                        <td style="padding:0.25rem 0; text-align: right; color: #555;">${formatMoney(dedConTarjetaVal)}</td>
                    </tr>
                    <tr style="font-weight: bold; background-color: #f0f7ff; border-top: 0.0625rem dashed #004B87; border-bottom: 0.0625rem dashed #004B87;">
                        <td style="padding:0.5rem 0.625rem; color: #004B87;">IMPORTE LÍQUIDO A ACREDITAR EN BANCO (BANDEC)</td>
                        <td style="padding:0.5rem 0.625rem; text-align: right; color: 1; font-size:10pt;">${formatMoney(acredVal)}</td>
                    </tr>
                    
                    <!-- Bloque Trabajadores SIN TARJETA -->
                    <tr style="font-weight: bold;">
                        <td style="padding:0.75rem 0 0.25rem 0; color: #111;">TOTAL DEVENGADO GENERAL DE TRABAJADORES (SIN TARJETA)</td>
                        <td style="padding:0.75rem 0 0.25rem 0; text-align: right;">${formatMoney(devSinTarjetaVal)}</td>
                    </tr>
                    <tr>
                        <td style="padding:0.25rem 0; color: #555; padding-left:1.25rem;">(-) Deducciones generales aplicadas (Trabajadores sin Tarjeta)</td>
                        <td style="padding:0.25rem 0; text-align: right; color: #555;">${formatMoney(dedSinTarjeta)}</td>
                    </tr>
                    <tr style="font-weight: bold; background-color: #fff5f5; border-top: 0.0625rem dashed #b91c1c; border-bottom: 0.125rem solid #000;">
                        <td style="padding:0.5rem 0.625rem; color: #b91c1c;">IMPORTE LÍQUIDO A PAGAR EFECTIVO</td>
                        <td style="padding:0.5rem 0.625rem; text-align: right; color: #b91c1c; font-size:10pt;">${formatMoney(efectivoVal)}</td>
                    </tr>
                </table>

                <!-- 2. DESGLOSE OPERATIVO DEL PERSONAL -->
                <h3 style="font-size:10.5pt; font-weight: bold; margin-bottom:0.9375rem; color: #111;">2. DESGLOSE OPERATIVO DEL PERSONAL</h3>
                
                <table style="width:100%; border-collapse: collapse; font-size:9.5pt; margin-bottom:1.875rem; line-height:1.6;">
                    <tr style="border-bottom: 0.0625rem solid #eee;">
                        <td style="padding:0.5rem 0; color: #333;">Trabajadores procesados para depósito electrónico (con tarjeta activa)</td>
                        <td style="padding:0.5rem 0; text-align: right; font-weight: bold;">${conTarjetaCountVal}</td>
                    </tr>
                    <tr style="border-bottom: 0.0625rem solid #eee;">
                        <td style="padding:0.5rem 0; color: #333;">Trabajadores pendientes de bancarización (pago manual por ventanilla)</td>
                        <td style="padding:0.5rem 0; text-align: right; font-weight: bold;">${sinTarjetaCountVal}</td>
                    </tr>
                    <tr style="font-weight: bold; background-color: #f8fafc; border-top: 0.0938rem solid #000; border-bottom: 0.0938rem solid #000;">
                        <td style="padding:0.625rem 0.5rem; color: #000;">TOTAL DE TRABAJADORES EVALUADOS EN EL PERÍODO</td>
                        <td style="padding:0.625rem 0.5rem; text-align: right; color: #000; font-size:10pt;">${totalNominaCountVal}</td>
                    </tr>
                </table>
            </div>

            <!-- Firmas de Autorización -->
            <div class="signatures-area" style="display: flex; justify-content: space-between; margin-top:3.75rem; padding:0 0.625rem;">
                <div style="width:42%; text-align: center;">
                    <p style="font-size:8.5pt; color: #555; margin-bottom:3.4375rem;">Elaborado por:</p>
                    <div style="border-top: 0.0938rem solid #000; width:90%; margin:0 auto; padding-top:0.375rem;">
                        <strong style="font-size:9pt; color: #111;">${escapeHtml(especialista)}</strong><br>
                        <span style="font-size:8pt; color: #666;">Especialista en Gestión Económica</span>
                    </div>
                </div>
                <div style="width:42%; text-align: center;">
                    <p style="font-size:8.5pt; color: #555; margin-bottom:3.4375rem;">Aprobado por:</p>
                    <div style="border-top: 0.0938rem solid #000; width:90%; margin:0 auto; padding-top:0.375rem;">
                        <strong style="font-size:9pt; color: #111;">${escapeHtml(jefe)}</strong><br>
                        <span style="font-size:8pt; color: #666;">Jefe de Proyecto</span>
                    </div>
                </div>
            </div>

            <div class="print-footer" style="position: absolute; bottom:10mm; left:15mm; right:15mm;">
                <div class="footer-left">Resumen de Cierre Financiero Nomina</div>
                <div class="footer-center">Hoja de Totales y Desglose</div>
                <div class="footer-right">Generado el: ${fechaHora}</div>
            </div>
        </div>
    `;

    // 4. Configurar e invocar ventana de impresión con auto-cierre
    const ventana = window.open('', '_blank');
    if (!ventana) {
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo abrir la ventana de impresión. Permita las ventanas emergentes en su navegador.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }

    ventana.document.write(`
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <title>Previsualización Exportación BANDEC - ${escapeHtml(empresa)}</title>
            <meta charset="UTF-8">
            <style>
                * { margin:0; padding:0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    font-size:9pt;
                    background: white;
                    color: #000;
                }
                @page { size: letter portrait; margin:0; }
                .page-sheet {
                    width:215.9mm;
                    height:279.4mm;
                    padding:15mm 15mm 20mm 15mm;
                    position: relative;
                    background: white;
                    page-break-after: always;
                    display: flex;
                    flex-direction: column;
                }
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 0.125rem solid #004B87;
                    padding-bottom:0.625rem;
                    margin-bottom:0.75rem;
                    min-height:5.3125rem;
                }
                .logo-area { width:15%; display: flex; align-items: center; }
                .logo-area img { max-height:3.75rem; max-width:100%; width:auto; }
                .header-text { text-align: center; width:60%; }
                .header-text h1 { font-size:12pt; color: #004B87; margin:0; text-transform: uppercase; }
                .header-text h2 { font-size:9.5pt; color: #333; margin:0.125rem 0 0; }
                .header-text p { font-size:8pt; color: #555; margin:0.125rem 0 0; }
                .header-right { width:25%; text-align: right; font-size:8pt; color: #555; line-height:1.3; }
                
                .table-container { flex-grow: 1; overflow: hidden; }
                .print-table { width:100%; border-collapse: collapse; font-size:8pt; }
                .print-table th, .print-table td { border: 0.0625rem solid #b3b3b3; padding:0.1875rem 0.375rem; line-height:1.2; }
                .print-table th { 
                    background-color: #004B87 !important; 
                    color: white !important; 
                    font-weight: bold; 
                    text-transform: uppercase; 
                    font-size:7.5pt;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .print-table td { color: #111; }
                .print-table .total-row td { 
                    font-weight: bold; 
                    color: #000000 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .print-footer {
                    position: absolute;
                    bottom:10mm;
                    left:15mm;
                    right:15mm;
                    border-top: 0.0625rem solid #bbb;
                    padding-top:0.375rem;
                    font-size:7.5pt;
                    color: #555;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: white;
                }
                .footer-left { flex: 1; text-align: left; }
                .footer-center { flex: 1; text-align: center; font-weight: bold; }
                .footer-right { flex: 1; text-align: right; }
                
                @media print {
                    body { background: none; }
                    .page-sheet { margin:0; box-shadow: none; width:100%; height:100vh; }
                    .print-table th { background-color: #004B87 !important; color: white !important; }
                    .print-table .total-row td { color: #000000 !important; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            ${PRINT_TOOLBAR_HTML}
            ${htmlFinal}
        </body>
        </html>
    `);
    
    ventana.document.close();
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}


// ==========================================
// EXPORTADORES INDIVIDUALES
// ==========================================

// 1. EXCEL - CON AMBAS SECCIONES (CON TARJETA Y SIN TARJETA)
function exportarExcelLocal(datosConTarjeta, datosSinTarjeta) {
    // Asegurar que sean arrays
    datosSinTarjeta = datosSinTarjeta || [];
    datosConTarjeta = datosConTarjeta || [];
    
    const fechaActualStr = obtenerFechaHora12H();
    const logo = typeof logoBase64 !== 'undefined' ? logoBase64 : '';
    
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTexto = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTexto = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    const formato = window.formatoSeleccionado ? window.formatoSeleccionado.toUpperCase() : 'No seleccionado';
    
    const conTarjetaCount = window.rawTotals?.con_tarjeta_count || datosConTarjeta.length;
    const sinTarjetaCount = window.rawTotals?.sin_tarjeta_count || datosSinTarjeta.length;
    
    const totalImporteCon = datosConTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalImporteSin = datosSinTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalGeneral = totalImporteCon + totalImporteSin;
    
    let excelTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; font-size:9pt; color: #000; }
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 0.125rem solid #004B87;
                    padding-bottom:0.625rem;
                    margin-bottom:0.9375rem;
                }
                .logo-area { width:15%; display: flex; align-items: center; }
                .logo-area img { max-height:3.75rem; max-width:100%; width:auto; }
                .header-text { text-align: center; width:60%; }
                .header-text h1 { font-size:12pt; color: #004B87; margin:0; text-transform: uppercase; }
                .header-text h2 { font-size:9.5pt; color: #333; margin:0.125rem 0 0; }
                .header-text p { font-size:8pt; color: #555; margin:0.125rem 0 0; }
                .header-right { width:25%; text-align: right; font-size:8pt; color: #555; line-height:1.3; }
                .section-title { font-size:10pt; font-weight: bold; color: #004B87; margin:1.25rem 0 0.5rem 0; font-family: Arial; }
                .section-title-sin { font-size:10pt; font-weight: bold; color: #b91c1c; margin:1.25rem 0 0.5rem 0; font-family: Arial; }
                table.data-table { border-collapse: collapse; width:100%; font-size:8.5pt; margin-bottom:0.625rem; }
                table.data-table th, table.data-table td { border: 0.0625rem solid #b3b3b3; padding:0.25rem 0.375rem; }
                table.data-table th { background-color: #004B87; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size:7.5pt; }
                table.data-table-sin th { background-color: #b91c1c; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size:7.5pt; }
                .total-row-con { background-color: #e6f0ff; font-weight: bold; }
                .total-row-sin { background-color: #ffebe6; font-weight: bold; }
                .total-general { background-color: #d4edda; font-weight: bold; font-size:9pt; }
                .print-footer {
                    margin-top:1.25rem;
                    border-top: 0.0625rem solid #bbb;
                    padding-top:0.375rem;
                    font-size:7.5pt;
                    color: #555;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
            </style>
        </head>
        <body>
            <!-- ENCABEZADO IDÉNTICO A imprimirPreview -->
            <div class="print-header">
                <div class="logo-area">
                    ${logo ? `<img src="${logo}" alt="Logo">` : ''}
                </div>
                <div class="header-text">
                    <h1>${escapeHtml(nombreEmpresa)}</h1>
                    <h2>Exportación BANDEC - Ambas Secciones</h2>
                    <p>${escapeHtml(tipoNominaTexto)} · Período: ${escapeHtml(periodoTexto)} · Formato: ${escapeHtml(formato)}</p>
                </div>
                <div class="header-right">
                    <strong>Especialista:</strong> ${escapeHtml(especialistaGestion)}<br>
                    <strong>Jefe de Proyecto:</strong> ${escapeHtml(jefeProyecto)}<br>
                    <span>${fechaActualStr}</span>
                </div>
            </div>
            
            <!-- SECCIÓN 1: CON TARJETA -->
            <div class="section-title">SECCIÓN 1: TRABAJADORES CON TARJETA (${conTarjetaCount})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:5%; text-align: center;">No.</th>
                        <th style="width:15%; text-align: center;">CI / NID</th>
                        <th style="width:35%; text-align: left;">Nombre del Trabajador</th>
                        <th style="width:25%; text-align: center;">Cuenta Bancaria</th>
                        <th style="width:20%; text-align: right;">Importe (CUP)</th>
                    </tr>
                </thead>
                <tbody>
    `;

    if (datosConTarjeta.length === 0) {
        excelTemplate += `
            <tr>
                <td colspan="5" style="text-align: center; color: #666;">No hay trabajadores CON TARJETA en este período</td>
            </tr>
        `;
    } else {
        datosConTarjeta.forEach(row => {
            excelTemplate += `
                <tr>
                    <td align="center">${row.no}</td>
                    <td align="center" style="mso-number-format:'@';">${row.ci}</td>
                    <td>${row.nombre}</td>
                    <td align="center" style="mso-number-format:'@';">${row.cuenta || '--'}</td>
                    <td align="right">${(row.importe || 0).toFixed(2)}</td>
                </tr>
            `;
        });
    }
    
    excelTemplate += `
                </tbody>
                <tfoot>
                    <tr class="total-row-con">
                        <td colspan="4" align="right"><strong>TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCount})</strong></td>
                        <td align="right"><strong>${totalImporteCon.toFixed(2)}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- SECCIÓN 2: SIN TARJETA -->
            <div class="section-title-sin">SECCIÓN 2: TRABAJADORES SIN TARJETA (${sinTarjetaCount})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:5%; text-align: center;">No.</th>
                        <th style="width:15%; text-align: center;">CI / NID</th>
                        <th style="width:35%; text-align: left;">Nombre del Trabajador</th>
                        <th style="width:25%; text-align: center;">Vía de Pago</th>
                        <th style="width:20%; text-align: right;">Importe (CUP)</th>
                    </tr>
                </thead>
                <tbody>
    `;

    if (datosSinTarjeta.length === 0) {
        excelTemplate += `
            <tr>
                <td colspan="5" style="text-align: center; color: #666;">No hay trabajadores SIN TARJETA en este período</td>
            </tr>
        `;
    } else {
        datosSinTarjeta.forEach(row => {
            excelTemplate += `
                <tr>
                    <td align="center">${row.no}</td>
                    <td align="center" style="mso-number-format:'@';">${row.ci}</td>
                    <td>${row.nombre}</td>
                    <td align="center"></td>
                    <td align="right">${(row.importe || 0).toFixed(2)}</td>
                </tr>
            `;
        });
    }
    
    excelTemplate += `
                </tbody>
                <tfoot>
                    <tr class="total-row-sin">
                        <td colspan="4" align="right"><strong>TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCount})</strong></td>
                        <td align="right"><strong>${totalImporteSin.toFixed(2)}</strong></td>
                    </tr>
                    <tr class="total-general">
                        <td colspan="4" align="right"><strong>TOTAL GENERAL (AMBAS SECCIONES)</strong></td>
                        <td align="right"><strong>${totalGeneral.toFixed(2)}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- PIE DE PÁGINA -->
            <div class="print-footer">
                <div class="footer-left">Nómina - Distribución Electrónica y Física</div>
                <div class="footer-center">Documento generado por Sistema de Gestión de Nóminas</div>
                <div class="footer-right">Generado el: ${fechaActualStr}</div>
            </div>
        </body>
        </html>
    `;

    const blob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `acreditacion_bandec_completo_${new Date().toISOString().slice(0, 10)}.xls`;
    link.click();
}

// 2. WORD - CON AMBAS SECCIONES (CON TARJETA Y SIN TARJETA)
function exportarWordLocal(datosConTarjeta, datosSinTarjeta) {
    datosSinTarjeta = datosSinTarjeta || [];
    datosConTarjeta = datosConTarjeta || [];
    
    const fechaActualStr = obtenerFechaHora12H();
    const logo = typeof logoBase64 !== 'undefined' ? logoBase64 : '';
    
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTextoWord = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTextoWord = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    const formato = window.formatoSeleccionado ? window.formatoSeleccionado.toUpperCase() : 'No seleccionado';
    
    const conTarjetaCount = window.rawTotals?.con_tarjeta_count || datosConTarjeta.length;
    const sinTarjetaCount = window.rawTotals?.sin_tarjeta_count || datosSinTarjeta.length;
    
    const totalImporteCon = datosConTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalImporteSin = datosSinTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalGeneral = totalImporteCon + totalImporteSin;

    let wordTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Acreditación BANDEC - Completa</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; font-size:9pt; color: #000; }
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 0.125rem solid #004B87;
                    padding-bottom:0.625rem;
                    margin-bottom:0.9375rem;
                }
                .logo-area { width:3.75rem; }
                .logo-area img { max-height:2.5rem; max-width:3.4375rem; width:auto; height:auto; }
                .header-text { text-align: center; width:60%; }
                .header-text h1 { font-size:12pt; color: #004B87; margin:0; text-transform: uppercase; }
                .header-text h2 { font-size:9.5pt; color: #333; margin:0.125rem 0 0; }
                .header-text p { font-size:8pt; color: #555; margin:0.125rem 0 0; }
                .header-right { width:25%; text-align: right; font-size:8pt; color: #555; line-height:1.3; }
                .section-title { font-size:10pt; font-weight: bold; color: #004B87; margin:1.25rem 0 0.5rem 0; }
                .section-title-sin { font-size:10pt; font-weight: bold; color: #b91c1c; margin:1.25rem 0 0.5rem 0; }
                table.data-table { width:100%; border-collapse: collapse; font-size:8.5pt; margin-top:0.5rem; }
                table.data-table th, table.data-table td { border: 0.0625rem solid #b3b3b3; padding:0.25rem 0.375rem; }
                table.data-table th { background-color: #004B87; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size:7.5pt; }
                table.data-table-sin th { background-color: #b91c1c; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size:7.5pt; }
                .total-row-con { background-color: #e6f0ff; font-weight: bold; }
                .total-row-sin { background-color: #ffebe6; font-weight: bold; }
                .total-general { background-color: #d4edda; font-weight: bold; font-size:9pt; }
                .print-footer {
                    margin-top:1.25rem;
                    border-top: 0.0625rem solid #bbb;
                    padding-top:0.375rem;
                    font-size:7.5pt;
                    color: #555;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <!-- ENCABEZADO IDÉNTICO A imprimirPreview -->
            <div class="print-header">
                <div class="logo-area">
                    ${logo ? `<img src="${logo}" alt="Logo">` : ''}
                </div>
                <div class="header-text">
                    <h1>${escapeHtml(nombreEmpresa)}</h1>
                    <h2>Exportación BANDEC - Ambas Secciones</h2>
                    <p>${escapeHtml(tipoNominaTextoWord)} · Período: ${escapeHtml(periodoTextoWord)} · Formato: ${escapeHtml(formato)}</p>
                </div>
                <div class="header-right">
                    <strong>Especialista:</strong> ${escapeHtml(especialistaGestion)}<br>
                    <strong>Jefe de Proyecto:</strong> ${escapeHtml(jefeProyecto)}<br>
                    <span>${fechaActualStr}</span>
                </div>
            </div>
            
            <!-- SECCIÓN 1: CON TARJETA -->
            <div class="section-title">SECCIÓN 1: TRABAJADORES CON TARJETA (${conTarjetaCount})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th align="center" style="width:2.8125rem;">No.</th>
                        <th align="center" style="width:5.625rem;">CI / NID</th>
                        <th align="left">Nombre del Trabajador</th>
                        <th align="center" style="width:7.5rem;">Cuenta Bancaria</th>
                        <th align="right" style="width:5rem;">Importe (CUP)</th>
                    </tr>
                </thead>
                <tbody>
    `;

    if (datosConTarjeta.length === 0) {
        wordTemplate += `
            <tr>
                <td colspan="5" align="center" style="color: #666;">No hay trabajadores CON TARJETA en este período</td>
            </tr>
        `;
    } else {
        datosConTarjeta.forEach(row => {
            wordTemplate += `
                <tr>
                    <td align="center">${row.no}</td>
                    <td align="center">${row.ci}</td>
                    <td>${row.nombre}</td>
                    <td align="center">${row.cuenta || '--'}</td>
                    <td align="right">${(row.importe || 0).toFixed(2)}</td>
                </tr>
            `;
        });
    }
    
    wordTemplate += `
                </tbody>
                <tfoot>
                    <tr class="total-row-con">
                        <td colspan="4" align="right"><strong>TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCount})</strong></td>
                        <td align="right"><strong>${totalImporteCon.toFixed(2)}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- SECCIÓN 2: SIN TARJETA -->
            <div class="section-title-sin">SECCIÓN 2: TRABAJADORES SIN TARJETA (${sinTarjetaCount})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th align="center" style="width:2.8125rem;">No.</th>
                        <th align="center" style="width:5.625rem;">CI / NID</th>
                        <th align="left">Nombre del Trabajador</th>
                        <th align="center" style="width:7.5rem;">Vía de Pago</th>
                        <th align="right" style="width:5rem;">Importe (CUP)</th>
                    </tr>
                </thead>
                <tbody>
    `;

    if (datosSinTarjeta.length === 0) {
        wordTemplate += `
            <tr>
                <td colspan="5" align="center" style="color: #666;">No hay trabajadores SIN TARJETA en este período</td>
            </tr>
        `;
    } else {
        datosSinTarjeta.forEach(row => {
            wordTemplate += `
                <tr>
                    <td align="center">${row.no}</td>
                    <td align="center">${row.ci}</td>
                    <td>${row.nombre}</td>
                    <td align="center"></td>
                    <td align="right">${(row.importe || 0).toFixed(2)}</td>
                </tr>
            `;
        });
    }
    
    wordTemplate += `
                </tbody>
                <tfoot>
                    <tr class="total-row-sin">
                        <td colspan="4" align="right"><strong>TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCount})</strong></td>
                        <td align="right"><strong>${totalImporteSin.toFixed(2)}</strong></td>
                    </tr>
                    <tr class="total-general">
                        <td colspan="4" align="right"><strong>TOTAL GENERAL (AMBAS SECCIONES)</strong></td>
                        <td align="right"><strong>${totalGeneral.toFixed(2)}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- PIE DE PÁGINA -->
            <div class="print-footer">
                <p>Nómina - Distribución Electrónica y Física | Documento generado por Sistema de Gestión de Nóminas | Generado el: ${fechaActualStr}</p>
            </div>
        </body>
        </html>
    `;

    const blob = new Blob([wordTemplate], { type: 'application/msword;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `acreditacion_bandec_completo_${new Date().toISOString().slice(0, 10)}.doc`;
    link.click();
}


// 3. PDF - CON AMBAS SECCIONES (CON TARJETA Y SIN TARJETA)
function exportarPDFLocal(datosConTarjeta, datosSinTarjeta) {
    datosSinTarjeta = datosSinTarjeta || [];
    datosConTarjeta = datosConTarjeta || [];
    
    const fechaActualStr = obtenerFechaHora12H();
    const logo = typeof logoBase64 !== 'undefined' ? logoBase64 : '';

    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTextoPDF = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTextoPDF = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    const formato = window.formatoSeleccionado ? window.formatoSeleccionado.toUpperCase() : 'No seleccionado';
    
    const conTarjetaCount = window.rawTotals?.con_tarjeta_count || datosConTarjeta.length;
    const sinTarjetaCount = window.rawTotals?.sin_tarjeta_count || datosSinTarjeta.length;
    
    const totalImporteCon = datosConTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalImporteSin = datosSinTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalGeneral = totalImporteCon + totalImporteSin;

    const docDefinition = {
        pageSize: 'LETTER',
        pageOrientation: 'portrait',
        pageMargins: [40, 50, 40, 50],
        header: function(currentPage, pageCount) {
            return {
                columns: [
                    { text: `${nombreEmpresa}`, alignment: 'left', fontSize: 8, color: '#004B87', bold: true },
                    { text: `Página ${currentPage} de ${pageCount}`, alignment: 'right', fontSize: 8, color: '#666' }
                ],
                margin: [40, 20, 40, 0]
            };
        },
        footer: function(currentPage, pageCount) {
            return {
                text: `Generado: ${fechaActualStr} | Sistema de Gestión de Nóminas`,
                alignment: 'center',
                fontSize: 7,
                color: '#555',
                margin: [40, 0, 40, 10]
            };
        },
        content: [],
        defaultStyle: {
            fontSize: 8.5
        }
    };

    // ==========================================
    // CABECERA CON LOGO
    // ==========================================
    const headerContent = [];
    
    // Logo
    if (logo && logo.trim() !== '') {
        try {
            headerContent.push({ image: logo, width: 60, alignment: 'left', margin: [0, 0, 0, 5] });
        } catch(e) {
            // Si falla el logo, continuar sin él
        }
    }
    
    headerContent.push({
        stack: [
            { text: nombreEmpresa, fontSize: 14, bold: true, color: '#004B87', alignment: 'center' },
            { text: 'Exportación BANDEC - Ambas Secciones', fontSize: 10, bold: true, alignment: 'center', margin: [0, 2, 0, 0] },
            { text: `${tipoNominaTextoPDF} · Período: ${periodoTextoPDF} · Formato: ${formato}`, fontSize: 8, color: '#555', alignment: 'center', margin: [0, 2, 0, 0] },
            { 
                columns: [
                    { text: `Especialista: ${especialistaGestion}`, fontSize: 7.5, alignment: 'left' },
                    { text: `Jefe de Proyecto: ${jefeProyecto}`, fontSize: 7.5, alignment: 'center' },
                    { text: `Generado: ${fechaActualStr}`, fontSize: 7.5, alignment: 'right' }
                ],
                margin: [0, 8, 0, 0]
            }
        ],
        margin: [0, 0, 0, 10]
    });

    docDefinition.content.push({
        stack: headerContent,
        margin: [0, 0, 0, 10]
    });

    // Línea separadora
    docDefinition.content.push({
        canvas: [{ type: 'line', x1: 0, y1: 5, x2: 520, y2: 5, lineWidth: 2, color: '#004B87' }],
        margin: [0, 0, 0, 10]
    });

    // ==========================================
    // SECCIÓN 1: CON TARJETA
    // ==========================================
    docDefinition.content.push({
        text: `SECCIÓN 1: TRABAJADORES CON TARJETA (${conTarjetaCount})`,
        fontSize: 10,
        bold: true,
        color: '#004B87',
        margin: [0, 15, 0, 5]
    });

    const tableBodyCon = [
        [
            { text: 'No.', bold: true, color: 'white', fillColor: '#004B87', alignment: 'center', fontSize: 7.5 },
            { text: 'CI / NID', bold: true, color: 'white', fillColor: '#004B87', alignment: 'center', fontSize: 7.5 },
            { text: 'Nombre del Trabajador', bold: true, color: 'white', fillColor: '#004B87', fontSize: 7.5 },
            { text: 'Cuenta Bancaria', bold: true, color: 'white', fillColor: '#004B87', alignment: 'center', fontSize: 7.5 },
            { text: 'Importe', bold: true, color: 'white', fillColor: '#004B87', alignment: 'right', fontSize: 7.5 }
        ]
    ];

    if (datosConTarjeta.length === 0) {
        tableBodyCon.push([
            { text: 'No hay trabajadores CON TARJETA en este período', colSpan: 5, alignment: 'center', color: '#666' },
            {}, {}, {}, {}
        ]);
    } else {
        datosConTarjeta.forEach(row => {
            tableBodyCon.push([
                { text: row.no.toString(), alignment: 'center', fontSize: 8 },
                { text: row.ci, alignment: 'center', fontSize: 8 },
                { text: row.nombre, fontSize: 8 },
                { text: row.cuenta || '--', alignment: 'center', fontSize: 8 },
                { text: (row.importe || 0).toFixed(2), alignment: 'right', fontSize: 8 }
            ]);
        });
    }
    
    tableBodyCon.push([
        { text: '', colSpan: 4, alignment: 'right', bold: true, fontSize: 8.5, text: `TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCount})` },
        { text: '' }, { text: '' }, { text: '' },
        { text: totalImporteCon.toFixed(2), alignment: 'right', bold: true, fillColor: '#e6f0ff', fontSize: 8.5 }
    ]);

    docDefinition.content.push({
        table: {
            headerRows: 1,
            widths: [22, 70, '*', 105, 55],
            body: tableBodyCon
        },
        layout: {
            hLineWidth: function (i, node) { return 0.5; },
            vLineWidth: function (i, node) { return 0.5; },
            hLineColor: function (i, node) { return '#aaa'; },
            vLineColor: function (i, node) { return '#aaa'; }
        }
    });

    // ==========================================
    // SECCIÓN 2: SIN TARJETA
    // ==========================================
    docDefinition.content.push({
        text: `SECCIÓN 2: TRABAJADORES SIN TARJETA (${sinTarjetaCount})`,
        fontSize: 10,
        bold: true,
        color: '#b91c1c',
        margin: [0, 20, 0, 5]
    });

    const tableBodySin = [
        [
            { text: 'No.', bold: true, color: 'white', fillColor: '#b91c1c', alignment: 'center', fontSize: 7.5 },
            { text: 'CI / NID', bold: true, color: 'white', fillColor: '#b91c1c', alignment: 'center', fontSize: 7.5 },
            { text: 'Nombre del Trabajador', bold: true, color: 'white', fillColor: '#b91c1c', fontSize: 7.5 },
            { text: 'Vía de Pago', bold: true, color: 'white', fillColor: '#b91c1c', alignment: 'center', fontSize: 7.5 },
            { text: 'Importe', bold: true, color: 'white', fillColor: '#b91c1c', alignment: 'right', fontSize: 7.5 }
        ]
    ];

    if (datosSinTarjeta.length === 0) {
        tableBodySin.push([
            { text: 'No hay trabajadores SIN TARJETA en este período', colSpan: 5, alignment: 'center', color: '#666' },
            {}, {}, {}, {}
        ]);
    } else {
        datosSinTarjeta.forEach(row => {
            tableBodySin.push([
                { text: row.no.toString(), alignment: 'center', fontSize: 8 },
                { text: row.ci, alignment: 'center', fontSize: 8 },
                { text: row.nombre, fontSize: 8 },
                { text: '', alignment: 'center', fontSize: 8 },
                { text: (row.importe || 0).toFixed(2), alignment: 'right', fontSize: 8 }
            ]);
        });
    }
    
    tableBodySin.push([
        { text: '', colSpan: 4, alignment: 'right', bold: true, fontSize: 8.5, text: `TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCount})` },
        { text: '' }, { text: '' }, { text: '' },
        { text: totalImporteSin.toFixed(2), alignment: 'right', bold: true, fillColor: '#ffebe6', fontSize: 8.5 }
    ]);
    
    tableBodySin.push([
        { text: '', colSpan: 4, alignment: 'right', bold: true, fontSize: 9, text: 'TOTAL GENERAL (AMBAS SECCIONES)' },
        { text: '' }, { text: '' }, { text: '' },
        { text: totalGeneral.toFixed(2), alignment: 'right', bold: true, fillColor: '#d4edda', fontSize: 9 }
    ]);

    docDefinition.content.push({
        table: {
            headerRows: 1,
            widths: [22, 70, '*', 105, 55],
            body: tableBodySin
        },
        layout: {
            hLineWidth: function (i, node) { return 0.5; },
            vLineWidth: function (i, node) { return 0.5; },
            hLineColor: function (i, node) { return '#aaa'; },
            vLineColor: function (i, node) { return '#aaa'; }
        }
    });

    pdfMake.createPdf(docDefinition).download(`acreditacion_bandec_completo_${new Date().toISOString().slice(0, 10)}.pdf`);
}

// 4. CSV - CON AMBAS SECCIONES (CON TARJETA Y SIN TARJETA)
function exportarCSVLocal(datosConTarjeta, datosSinTarjeta) {
    datosSinTarjeta = datosSinTarjeta || [];
    datosConTarjeta = datosConTarjeta || [];
    
    const fechaActualStr = obtenerFechaHora12H();
    
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTexto = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTexto = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    
    const conTarjetaCount = window.rawTotals?.con_tarjeta_count || datosConTarjeta.length;
    const sinTarjetaCount = window.rawTotals?.sin_tarjeta_count || datosSinTarjeta.length;
    
    const totalImporteCon = datosConTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalImporteSin = datosSinTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalGeneral = totalImporteCon + totalImporteSin;
    
    let csv = '\uFEFF';
    csv += `"${nombreEmpresa}"\r\n`;
    csv += `"Exportación BANDEC - Ambas Secciones"\r\n`;
    csv += `"${tipoNominaTexto} · Período: ${periodoTexto}"\r\n`;
    csv += `"Especialista: ${especialistaGestion} | Jefe de Proyecto: ${jefeProyecto}"\r\n`;
    csv += `"Fecha generación: ${fechaActualStr}"\r\n`;
    csv += `\r\n`;
    
    // SECCIÓN 1: CON TARJETA
    csv += `"SECCIÓN 1: TRABAJADORES CON TARJETA (${conTarjetaCount})"\r\n`;
    csv += `"No.","CI / NID","Nombre del Trabajador","Cuenta Bancaria","Importe (CUP)"\r\n`;
    
    if (datosConTarjeta.length === 0) {
        csv += `"","No hay trabajadores CON TARJETA en este período","","",""\r\n`;
    } else {
        datosConTarjeta.forEach(row => {
            csv += `"${row.no}","${row.ci}","${row.nombre}","${row.cuenta || '--'}","${(row.importe || 0).toFixed(2)}"\r\n`;
        });
    }
    csv += `"TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCount})","","","","${totalImporteCon.toFixed(2)}"\r\n`;
    csv += `\r\n`;
    
    // SECCIÓN 2: SIN TARJETA
    csv += `"SECCIÓN 2: TRABAJADORES SIN TARJETA (${sinTarjetaCount})"\r\n`;
    csv += `"No.","CI / NID","Nombre del Trabajador","Vía de Pago","Importe (CUP)"\r\n`;
    
    if (datosSinTarjeta.length === 0) {
        csv += `"","No hay trabajadores SIN TARJETA en este período","","",""\r\n`;
    } else {
        datosSinTarjeta.forEach(row => {
            csv += `"${row.no}","${row.ci}","${row.nombre}","","${(row.importe || 0).toFixed(2)}"\r\n`;
        });
    }
    csv += `"TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCount})","","","","${totalImporteSin.toFixed(2)}"\r\n`;
    csv += `"TOTAL GENERAL (AMBAS SECCIONES)","","","","${totalGeneral.toFixed(2)}"\r\n`;
    csv += `\r\n`;
    csv += `"Documento generado por Sistema de Gestión de Nóminas - ${fechaActualStr}"\r\n`;

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `acreditacion_bandec_completo_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
}

// 5. TXT - CON AMBAS SECCIONES (CON TARJETA Y SIN TARJETA)
function exportarTXTLocal(datosConTarjeta, datosSinTarjeta) {
    datosSinTarjeta = datosSinTarjeta || [];
    datosConTarjeta = datosConTarjeta || [];
    
    const fechaActualStr = obtenerFechaHora12H();
    
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const tipoNominaTexto = tipoNominaSelect?.options[tipoNominaSelect.selectedIndex]?.text || 'No especificado';
    const periodoSelect = document.getElementById('periodoSelect');
    const periodoTexto = periodoSelect?.options[periodoSelect.selectedIndex]?.text || 'No especificado';
    
    const conTarjetaCount = window.rawTotals?.con_tarjeta_count || datosConTarjeta.length;
    const sinTarjetaCount = window.rawTotals?.sin_tarjeta_count || datosSinTarjeta.length;
    
    const totalImporteCon = datosConTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalImporteSin = datosSinTarjeta.reduce((sum, row) => sum + (row.importe || 0), 0);
    const totalGeneral = totalImporteCon + totalImporteSin;

    let txt = '';
    // ==========================================
    // ENCABEZADO IDÉNTICO A imprimirPreview
    // ==========================================
    const anchoLinea = 80;
    const lineaSeparadora = '='.repeat(anchoLinea);
    const lineaIntermedia = '-'.repeat(anchoLinea);
    
    txt += `${lineaSeparadora}\r\n`;
    txt += `${nombreEmpresa.toUpperCase()}\r\n`;
    txt += `Exportación BANDEC - Ambas Secciones\r\n`;
    txt += `${lineaSeparadora}\r\n`;
    txt += `${tipoNominaTexto} · Período: ${periodoTexto}\r\n`;
    txt += `Especialista: ${especialistaGestion} | Jefe de Proyecto: ${jefeProyecto}\r\n`;
    txt += `Fecha generación: ${fechaActualStr}\r\n`;
    txt += `${lineaSeparadora}\r\n\r\n`;
    
    // ==========================================
    // SECCIÓN 1: CON TARJETA
    // ==========================================
    txt += `SECCIÓN 1: TRABAJADORES CON TARJETA (${conTarjetaCount})\r\n`;
    txt += `${lineaIntermedia}\r\n`;
    txt += `No.\tCI / NID\tNombre del Trabajador\t\t\tCuenta Bancaria\t\tImporte (CUP)\r\n`;
    txt += `${lineaIntermedia}\r\n`;

    if (datosConTarjeta.length === 0) {
        txt += `\t\tNo hay trabajadores CON TARJETA en este período\r\n`;
    } else {
        datosConTarjeta.forEach(row => {
            const nombreAjustado = (row.nombre || '').padEnd(28, ' ').substring(0, 28);
            const cuentaAjustada = (row.cuenta || '--').padEnd(18, ' ').substring(0, 18);
            txt += `${row.no}\t${row.ci}\t${nombreAjustado}\t${cuentaAjustada}\t${(row.importe || 0).toFixed(2)}\r\n`;
        });
    }
    txt += `${lineaIntermedia}\r\n`;
    txt += `TOTAL SECCIÓN (CON TARJETA: ${conTarjetaCount}):\t\t\t\t\t\t${totalImporteCon.toFixed(2)}\r\n`;
    txt += `\r\n`;
    
    // ==========================================
    // SECCIÓN 2: SIN TARJETA
    // ==========================================
    txt += `SECCIÓN 2: TRABAJADORES SIN TARJETA (${sinTarjetaCount})\r\n`;
    txt += `${lineaIntermedia}\r\n`;
    txt += `No.\tCI / NID\tNombre del Trabajador\t\t\tVía de Pago\t\tImporte (CUP)\r\n`;
    txt += `${lineaIntermedia}\r\n`;

    if (datosSinTarjeta.length === 0) {
        txt += `\t\tNo hay trabajadores SIN TARJETA en este período\r\n`;
    } else {
        datosSinTarjeta.forEach(row => {
            const nombreAjustado = (row.nombre || '').padEnd(28, ' ').substring(0, 28);
            txt += `${row.no}\t${row.ci}\t${nombreAjustado}\t\t\t${(row.importe || 0).toFixed(2)}\r\n`;
        });
    }
    txt += `${lineaIntermedia}\r\n`;
    txt += `TOTAL SECCIÓN (SIN TARJETA: ${sinTarjetaCount}):\t\t\t\t\t\t${totalImporteSin.toFixed(2)}\r\n`;
    txt += `${lineaSeparadora}\r\n`;
    txt += `TOTAL GENERAL (AMBAS SECCIONES):\t\t\t\t\t\t${totalGeneral.toFixed(2)}\r\n`;
    txt += `${lineaSeparadora}\r\n`;
    txt += `Documento generado por Sistema de Gestión de Nóminas - ${fechaActualStr}\r\n`;

    const blob = new Blob([txt], { type: 'text/plain;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `acreditacion_bandec_completo_${new Date().toISOString().slice(0, 10)}.txt`;
    link.click();
}


// ==========================================
// EXPORTADOR BANDEC - CON PREVISUALIZACIÓN MODAL
// ==========================================
let periodosCache = {};
let tiposCache = [];

// Obtener parámetros de la URL
const urlParams = new URLSearchParams(window.location.search);
const periodoParam = urlParams.get('periodo');
const tipoParam = urlParams.get('tipo');

// Función para descargar archivo usando blob
function descargarArchivo(url, nombreArchivo) {
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al descargar el archivo');
            }
            return response.blob();
        })
        .then(blob => {
            const blobUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = nombreArchivo;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(blobUrl);
        })
        .catch(error => {
            console.error('Error en descarga:', error);
            Swal.fire({
                icon: 'error',
                title: '<i class="fas fa-exclamation-triangle me-2"></i> Error al descargar',
                text: 'No se pudo descargar el archivo. Intente nuevamente.',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: '#EF4444',
                background: '#1E1E1E',
                color: '#FFFFFF'
            });
        });
}

// Cargar tipos de nómina
function cargarTiposNomina() {
    const select = document.getElementById('tipoNominaSelect');
    if (!select) return;
    select.innerHTML = '<option value=""><i class="fas fa-spinner fa-pulse me-2"></i>-- Cargando tipos --</option>';
    
    fetch('exportar_bandec.php?accion=tipos_nomina')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tipos && data.tipos.length > 0) {
                tiposCache = data.tipos;
                select.innerHTML = '<option value=""><i class="fas fa-list me-2"></i>-- Seleccione un tipo de nómina --</option>';
                
                data.tipos.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo;
                    let nombreTipo = tipo;
                    if (tipo === 'automatica') {
                        nombreTipo = 'Automática';
                    } else if (tipo === 'extraordinaria') {
                        nombreTipo = 'Extraordinaria';
                    } else if (tipo === 'vacaciones') {
                        nombreTipo = 'Vacaciones';
                    } else if (tipo === 'bono') {
                        nombreTipo = 'Bono';
                    } else {
                        nombreTipo = tipo.charAt(0).toUpperCase() + tipo.slice(1);
                    }
                    option.textContent = nombreTipo;
                    select.appendChild(option);
                });
                
                if (tipoParam && data.tipos.includes(tipoParam)) {
                    select.value = tipoParam;
                    cargarPeriodos(tipoParam, true);
                }
            } else {
                select.innerHTML = '<option value=""><i class="fas fa-exclamation-triangle me-2"></i>-- No hay nóminas contabilizadas --</option>';
            }
        })
        .catch(error => {
            console.error('Error al cargar tipos:', error);
            select.innerHTML = '<option value=""><i class="fas fa-exclamation-circle me-2"></i>-- Error al cargar tipos --</option>';
        });
}

// Cargar períodos por tipo de nómina y año
function cargarPeriodos(tipoNomina, anio, seleccionarPeriodo = false) {
    const periodoSelect = document.getElementById('periodoSelect');
    if (!periodoSelect) return;
    
    if (!tipoNomina || !anio) {
        periodoSelect.innerHTML = '<option value=""><i class="fas fa-info-circle me-2"></i>-- Seleccione tipo y año --</option>';
        return;
    }
    
    // Verificar caché
    if (periodosCache[tipoNomina] && periodosCache[tipoNomina][anio]) {
        llenarSelectPeriodos(periodosCache[tipoNomina][anio], seleccionarPeriodo);
        marcarCuadreEnPeriodos(tipoNomina, anio);
        return;
    }
    
    periodoSelect.innerHTML = '<option value=""><i class="fas fa-spinner fa-pulse me-2"></i>-- Cargando períodos --</option>';
    
    fetch(`exportar_bandec.php?accion=periodos&tipo_nomina=${encodeURIComponent(tipoNomina)}&anio=${encodeURIComponent(anio)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.periodos && data.periodos.length > 0) {
                if (!periodosCache[tipoNomina]) periodosCache[tipoNomina] = {};
                periodosCache[tipoNomina][anio] = data.periodos;
                llenarSelectPeriodos(data.periodos, seleccionarPeriodo);
                marcarCuadreEnPeriodos(tipoNomina, anio);
            } else {
                periodoSelect.innerHTML = '<option value=""><i class="fas fa-calendar-times me-2"></i>-- No hay períodos contabilizados --</option>';
            }
        })
        .catch(error => {
            console.error('Error al cargar períodos:', error);
            periodoSelect.innerHTML = '<option value=""><i class="fas fa-exclamation-circle me-2"></i>-- Error al cargar períodos --</option>';
        });
}

function marcarCuadreEnPeriodos(tipoNomina, anio) {
    fetch(`exportar_bandec.php?accion=verificar_cuadre_lote&tipo_nomina=${encodeURIComponent(tipoNomina)}&anio=${encodeURIComponent(anio)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.resultados) return;
            const select = document.getElementById('periodoSelect');
            if (!select) return;
            data.resultados.forEach(res => {
                if (!res.con_errores) return;
                    for (let i = 0; i < select.options.length; i++) {
                    const opt = select.options[i];
                    if (opt.getAttribute('data-desde') === res.periodo_desde && opt.getAttribute('data-hasta') === res.periodo_hasta) {
                        opt.textContent = '⚠️ ' + opt.textContent.replace('⚠️ ', '').replace(' (DESCUADRE)', '') + ' (DESCUADRE)';
                        opt.style.color = '#f87171';
                        opt.style.fontWeight = '700';
                        opt.setAttribute('data-con-descuadre', '1');
                        break;
                    }
                }
            });
        })
        .catch(() => {});
}

function llenarSelectPeriodos(periodos, seleccionarPeriodo = false) {
    const periodoSelect = document.getElementById('periodoSelect');
    if (!periodoSelect) return;
    periodoSelect.innerHTML = '<option value=""><i class="fas fa-calendar-alt me-2"></i>-- Seleccione un período --</option>';
    
    let periodoEncontrado = null;
    
    periodos.forEach(periodo => {
        const option = document.createElement('option');
        option.value = JSON.stringify({
            desde: periodo.periodo_desde,
            hasta: periodo.periodo_hasta
        });
        option.textContent = periodo.label;
        option.setAttribute('data-desde', periodo.periodo_desde);
        option.setAttribute('data-hasta', periodo.periodo_hasta);
        periodoSelect.appendChild(option);
        
        if (seleccionarPeriodo && periodoParam) {
            const periodoDesde = periodo.periodo_desde.substring(0, 7);
            if (periodoDesde === periodoParam) {
                periodoEncontrado = option;
            }
        }
    });
    
    if (periodoEncontrado) {
        periodoEncontrado.selected = true;
    }
    
    // Verificar si la selección ahora es válida
    verificarSeleccionPeriodoValida();
    calcularTotales();
}

// ==========================================
// EJECUTAR EXPORTACIÓN REAL (con soporte ZIP)
// ==========================================
function ejecutarExportacion(periodoDesde, periodoHasta, tipoNomina, formato, comprimirZip = false) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Exportando...',
        text: 'Por favor espera, generando archivo',
        allowOutsideClick: false,
        background: '#1E1E1E',
        color: '#FFFFFF',
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let formData = new FormData();
    formData.append('formato', formato);
    formData.append('periodo_desde', periodoDesde);
    formData.append('periodo_hasta', periodoHasta);
    formData.append('tipo_nomina', tipoNomina);
    if (window.idsExcluir && window.idsExcluir.length > 0) {
        formData.append('ids_excluir', window.idsExcluir.join(','));
    }

    fetch('exportar_bandec.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const nombreArchivo = data.archivo;
            const urlDescarga = data.descarga;
            const extension = nombreArchivo.split('.').pop();

            if (comprimirZip) {
                fetch(urlDescarga)
                    .then(res => res.blob())
                    .then(blob => {
                        const zip = new JSZip();
                        zip.file(`nomina.${extension}`, blob);
                        zip.generateAsync({ type: 'blob' })
                            .then(zipBlob => {
                                const link = document.createElement('a');
                                link.href = URL.createObjectURL(zipBlob);
                                link.download = `${data.archivo}.zip`;
                                link.click();
                                URL.revokeObjectURL(link.href);
                                Swal.fire({
                                    icon: 'success',
                                    title: '<i class="fas fa-check-circle me-2"></i> Exportación ZIP completada',
                                    html: `
                                        <div style="text-align: center;">
                                            <i class="fas fa-file-archive" style="font-size:3rem; color: #60a5fa; margin-bottom:1rem;"></i>
                                            <p><strong>Archivo ZIP generado:</strong> ${data.archivo}.zip</p>
                                            <p><strong>Registros exportados:</strong> ${data.registros}</p>
                                            <p><strong>Contenido:</strong> nomina.${extension}</p>
                                            <p><strong>Formato:</strong> ${formato.toUpperCase()} comprimido</p>
                                        </div>
                                    `,
                                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                                    confirmButtonColor: '#3B82F6',
                                    background: '#1E1E1E',
                                    color: '#FFFFFF',
                                    timer: 4000,
                                    timerProgressBar: true
                                });
                            })
                            .catch(err => {
                                console.error('Error generando ZIP:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: '<i class="fas fa-exclamation-triangle me-2"></i> Error al comprimir',
                                    text: 'No se pudo crear el archivo ZIP. Intente nuevamente.',
                                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                    confirmButtonColor: '#EF4444',
                                    background: '#1E1E1E',
                                    color: '#FFFFFF'
                                });
                            });
                    })
                    .catch(error => {
                        console.error('Error descargando blob para ZIP:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '<i class="fas fa-wifi me-2"></i> Error de conexión',
                            text: 'No se pudo obtener el archivo para comprimir.',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                            confirmButtonColor: '#EF4444',
                            background: '#1E1E1E',
                            color: '#FFFFFF'
                        });
                    });
            } else {
                descargarArchivo(urlDescarga, nombreArchivo);
                Swal.fire({
                    icon: 'success',
                    title: '<i class="fas fa-check-circle me-2"></i> Exportación completada',
                    html: `
                        <div style="text-align: center;">
                            <i class="fas fa-file-alt" style="font-size:3rem; color: #34D399; margin-bottom:1rem;"></i>
                            <p><strong>Archivo generado:</strong> ${data.archivo}.${extension}</p>
                            <p><strong>Registros exportados:</strong> ${data.registros}</p>
                            <p><strong>Formato:</strong> ${formato.toUpperCase()}</p>
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                    confirmButtonColor: '#3B82F6',
                    background: '#1E1E1E',
                    color: '#FFFFFF',
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: '<i class="fas fa-exclamation-triangle me-2"></i> Error en la exportación',
                text: data.mensaje || 'No se pudo generar el archivo',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: '#EF4444',
                background: '#1E1E1E',
                color: '#FFFFFF'
            });
        }
    })
    .catch(error => {
        console.error('Error en exportación:', error);
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-wifi me-2"></i> Error de conexión',
            text: error.message || 'Ocurrió un error al generar el archivo',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#EF4444',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
    });
}

// ==========================================
// MOSTRAR MODAL CON PREVISUALIZACIÓN
// ==========================================
function mostrarPreviewYExportar() {
    const periodoData = document.getElementById('periodoSelect').value;
    const tipoNomina = document.getElementById('tipoNominaSelect').value;
    
    if (!tipoNomina) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-circle me-2"></i> Selecciona un tipo de nómina',
            text: 'Debes elegir un tipo de nómina para exportar',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3B82F6',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }
    
    if (!periodoData) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-calendar-times me-2"></i> Selecciona un período',
            text: 'Debes elegir un período de nómina para exportar',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3B82F6',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }
    
    if (!window.formatoSeleccionado) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-file me-2"></i> Selecciona un formato',
            text: 'Debes elegir DBF, XLSX o XML para continuar',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3B82F6',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }

    let periodo;
    try {
        periodo = JSON.parse(periodoData);
    } catch(e) {
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-bug me-2"></i> Error',
            text: 'Período inválido',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#EF4444',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        return;
    }

    // Verificar cuadre antes de exportar
    const btnExp = document.getElementById('btnExportar');
    const txtAnterior = btnExp ? btnExp.innerHTML : '';
    if (btnExp) { btnExp.disabled = true; btnExp.innerHTML = '<i class="fas fa-spinner fa-pulse me-2"></i> Verificando cuadre...'; }

    const zipCheckbox = document.querySelector(`.zip-checkbox[data-format="${window.formatoSeleccionado}"]`);
    const comprimirZip = zipCheckbox ? zipCheckbox.checked : false;

    let formCuadre = new FormData();
    formCuadre.append('accion', 'verificar_cuadre');
    formCuadre.append('periodo_desde', periodo.desde);
    formCuadre.append('periodo_hasta', periodo.hasta);
    formCuadre.append('tipo_nomina', tipoNomina);

    fetch('exportar_bandec.php', { method: 'POST', body: formCuadre })
        .then(r => r.json())
        .then(cuadre => {
            window.idsExcluir = [];
            if (cuadre.success && cuadre.con_errores) {
                const excluidas = cuadre.filas_con_error || 0;
                const nums = cuadre.numeros_nomina || [];
                const numsTexto = nums.length > 0 ? ' — Nómina(s): <strong>' + nums.join(', ') + '</strong>' : '';
                const meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
                const fmtFecha = (f) => { const p = f.split('-'); return p[2] + '/' + meses[parseInt(p[1]) - 1] + '/' + p[0]; };
                const cierresErr = cuadre.cierres_con_error || 0;
                const esCierre = cierresErr > 0;
                let detalleHtml = '';
                if (esCierre) {
                    detalleHtml = `<p style="font-size:0.88rem;">Se detectaron <strong style="color:#f87171;">${excluidas}</strong> fila(s) con descuadre(s) y <strong style="color:#f87171;">${cierresErr}</strong> cierre(s) descuadrado(s) en el tipo <strong>${tipoNomina}</strong> del período <strong>Desde: ${fmtFecha(periodo.desde)} Hasta: ${fmtFecha(periodo.hasta)}</strong>${numsTexto}.</p>
                           <p style="font-size:0.82rem;color:#94a3b8;margin-top:0.375rem;">Una nómina con el cierre descuadrado <strong style="color:#f87171;">NO se puede exportar</strong> hasta corregir los totales del cierre (en Cuadre de Nóminas → "Recalcular cierres").</p>`;
                } else {
                    detalleHtml = `<p style="font-size:0.88rem;">Se detectaron <strong style="color:#f87171;">${excluidas}</strong> fila(s) con descuadre(s) en el tipo <strong>${tipoNomina}</strong> del período <strong>Desde: ${fmtFecha(periodo.desde)} Hasta: ${fmtFecha(periodo.hasta)}</strong>${numsTexto}.</p>
                           <p style="font-size:0.82rem;color:#94a3b8;margin-top:0.375rem;">Las Nóminas con descuadre <strong style="color:#f87171;">no se pueden exportar</strong>. Las demás sí se exportarán normalmente.</p>`;
                }
                const soloRegresar = {
                    icon: 'warning',
                    title: '<i class="fas fa-exclamation-triangle me-2" style="color:#f87171;"></i><span style="color:#f87171;animation:cuadrePulse 1.5s ease-in-out infinite;">Nómina con Descuadre</span>',
                    html: detalleHtml,
                    confirmButtonText: '<i class="fas fa-arrow-left me-2"></i>Regresar y Corregir',
                    confirmButtonColor: '#3b82f6',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                };
                const conContinuar = {
                    icon: 'warning',
                    title: '<i class="fas fa-exclamation-triangle me-2" style="color:#f87171;"></i><span style="color:#f87171;animation:cuadrePulse 1.5s ease-in-out infinite;">Nómina con Descuadre</span>',
                    html: detalleHtml,
                    confirmButtonText: '<i class="fas fa-arrow-right me-2"></i>Continuar',
                    confirmButtonColor: '#10b981',
                    showCancelButton: true,
                    cancelButtonText: '<i class="fas fa-arrow-left me-2"></i>Regresar y Verificar',
                    cancelButtonColor: '#3b82f6',
                    background: '#1E1E1E',
                    color: '#FFFFFF',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                };
                Swal.fire(esCierre ? soloRegresar : conContinuar).then(function(result) {
                    if (btnExp) { btnExp.disabled = false; btnExp.innerHTML = txtAnterior; btnExp.style.opacity = ''; }
                    window.idsExcluir = [];
                    if ((esCierre && result.isConfirmed) || (!esCierre && result.dismiss === 'cancel')) {
                        window.location.href = 'nominas.php?periodo=' + encodeURIComponent(periodo.desde.substring(0, 7)) + '&tipo=' + encodeURIComponent(tipoNomina);
                    }
                });
                return;
            }
            if (btnExp) { btnExp.disabled = false; btnExp.innerHTML = txtAnterior; btnExp.style.opacity = ''; }
            _abrirPreviewYExportar(periodo, tipoNomina, comprimirZip);
        })
        .catch(() => {
            if (btnExp) { btnExp.disabled = false; btnExp.innerHTML = txtAnterior; }
            Swal.fire({
                icon: 'error',
                title: '<i class="fas fa-wifi me-2"></i> Error de conexión',
                text: 'No se pudo verificar el cuadre de la nómina. Intente nuevamente.',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: '#EF4444',
                background: '#1E1E1E',
                color: '#FFFFFF'
            });
        });

    return;
}

function _abrirPreviewYExportar(periodo, tipoNomina, comprimirZip) {
    // Obtener estado del checkbox de compresión
    const zipCheckbox = document.querySelector(`.zip-checkbox[data-format="${window.formatoSeleccionado}"]`);
    comprimirZip = comprimirZip || (zipCheckbox ? zipCheckbox.checked : false);

    // Mostrar modal con loading
    const modalElement = document.getElementById('modalPreview');
    if (!modalElement) return;
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: true
    });
    const previewLoading = document.getElementById('previewLoading');
    const previewContent = document.getElementById('previewContent');
    
    if (previewLoading) previewLoading.style.display = 'block';
    if (previewContent) previewContent.style.display = 'none';
    
    // Limpiar contenido anterior
    const previewTableBody = document.getElementById('previewTableBody');
    if (previewTableBody) previewTableBody.innerHTML = '';
    
    modal.show();
    
    let formData = new FormData();
    formData.append('accion', 'preview');
    formData.append('periodo_desde', periodo.desde);
    formData.append('periodo_hasta', periodo.hasta);
    formData.append('tipo_nomina', tipoNomina);
    if (window.idsExcluir && window.idsExcluir.length > 0) {
        formData.append('ids_excluir', window.idsExcluir.join(','));
    }
    
    fetch('exportar_bandec.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (previewLoading) previewLoading.style.display = 'none';
        if (data.success && data.total_registros > 0) {
            if (previewContent) previewContent.style.display = 'block';
            const previewTotalRegistros = document.getElementById('previewTotalRegistros');
            const previewTotalImporte = document.getElementById('previewTotalImporte');
            const previewAdvertencia = document.getElementById('previewAdvertencia');
            
            if (previewTotalRegistros) previewTotalRegistros.innerText = data.total_registros;
            if (previewTotalImporte) previewTotalImporte.innerHTML = formatMoney(data.total_importe);
            
            if (data.mas_registros) {
                if (previewAdvertencia) previewAdvertencia.innerHTML = `<i class="fas fa-info-circle"></i> Mostrando hasta 200 registros. Total real: ${data.total_registros}.`;
            } else {
                if (previewAdvertencia) previewAdvertencia.innerHTML = '';
            }
            
            const tbody = document.getElementById('previewTableBody');
            if (tbody) {
                tbody.innerHTML = '';
                
                let totalImportes = 0;
                
                data.registros.forEach(reg => {
                    const row = tbody.insertRow();
                    row.insertCell(0).textContent = reg.ci;
                    const cuenta = reg.cuentabanc && reg.cuentabanc.trim() !== '' ? reg.cuentabanc : '--';
                    row.insertCell(1).textContent = cuenta;
                    const importeNum = parseFloat(reg.importe);
                    row.insertCell(2).textContent = formatMoney(importeNum);
                    totalImportes += importeNum;
                });
                
// Agregar fila de TOTAL - Texto en columna 1-2, importe en columna 3
const totalRow = tbody.insertRow();
totalRow.className = 'total-row';
totalRow.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
totalRow.style.fontWeight = 'bold';
totalRow.style.borderTop = '0.125rem solid #60a5fa';

const textCell = totalRow.insertCell(0);
textCell.colSpan = 2;
textCell.textContent = 'TOTAL GENERAL:';
textCell.style.textAlign = 'right';
textCell.style.fontWeight = 'bold';
textCell.style.color = '#000000';
textCell.style.paddingRight = '0.9375rem';

const importeCell = totalRow.insertCell(1);
importeCell.textContent = formatMoney(totalImportes);
importeCell.style.textAlign = 'right';
importeCell.style.fontWeight = 'bold';
importeCell.style.color = '#000000';
importeCell.style.paddingRight = '0.9375rem';

				window.previewData = {
					periodoDesde: periodo.desde,
					periodoHasta: periodo.hasta,
					tipoNomina: tipoNomina,
					formato: window.formatoSeleccionado,
					comprimirZip: comprimirZip,
					total_registros: data.total_registros,
					total_importe_total: totalImportes,
					registros: data.registros,
					registros_sin_tarjeta: data.registros_sin_tarjeta // <-- AÑADIR ESTA LÍNEA
				};
            }

            const confirmExportBtn = document.getElementById('confirmExportBtn');
            const printPreviewBtn = document.getElementById('printPreviewBtn');
            if (confirmExportBtn) confirmExportBtn.disabled = false;
            if (printPreviewBtn) printPreviewBtn.disabled = false;
        } else {
            if (previewContent) previewContent.style.display = 'block';
            const previewTotalRegistros = document.getElementById('previewTotalRegistros');
            const previewTotalImporte = document.getElementById('previewTotalImporte');
            const previewAdvertencia = document.getElementById('previewAdvertencia');
            const previewTableBodyElem = document.getElementById('previewTableBody');
            
            if (previewTotalRegistros) previewTotalRegistros.innerText = '0';
            if (previewTotalImporte) previewTotalImporte.innerHTML = formatMoney(0);
            const sinTarjetaCount = (data.registros_sin_tarjeta || []).length;
            if (previewAdvertencia) previewAdvertencia.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> Esta nómina tiene <strong>${sinTarjetaCount}</strong> trabajador(es) sin número de tarjeta (cuenta bancaria) y CI, así que no hay importes que acreditar al banco y la exportación queda bloqueada. Actualice la cuenta bancaria de los trabajadores en su ficha para poder exportar.`;
            if (previewTableBodyElem) previewTableBodyElem.innerHTML = '<tr><td colspan="3" class="text-center text-info">No hay registros válidos</td></tr>';
            window.previewData = null;
            const confirmExportBtn = document.getElementById('confirmExportBtn');
            const printPreviewBtn = document.getElementById('printPreviewBtn');
            if (confirmExportBtn) confirmExportBtn.disabled = true;
            if (printPreviewBtn) printPreviewBtn.disabled = true;
        }
    })
    .catch(error => {
        console.error('Error preview:', error);
        if (previewLoading) previewLoading.style.display = 'none';
        const previewContentElem = document.getElementById('previewContent');
        const previewTableBodyElem = document.getElementById('previewTableBody');
        if (previewContentElem) previewContentElem.style.display = 'block';
        if (previewTableBodyElem) previewTableBodyElem.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error al cargar la previsualización</td></tr>';
        window.previewData = null;
        const confirmExportBtn = document.getElementById('confirmExportBtn');
        const printPreviewBtn = document.getElementById('printPreviewBtn');
        if (confirmExportBtn) confirmExportBtn.disabled = true;
        if (printPreviewBtn) printPreviewBtn.disabled = true;
    });
}

// ==========================================
// CARGAR AÑOS DISPONIBLES
// ==========================================
function cargarAnios() {
    const anioSelect = document.getElementById('anioSelect');
    if (!anioSelect) return;
    anioSelect.innerHTML = '<option value=""><i class="fas fa-spinner fa-pulse me-2"></i> Cargando años...</option>';
    
    fetch('exportar_bandec.php?accion=anios')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.anios && data.anios.length > 0) {
                anioSelect.innerHTML = '<option value=""><i class="fas fa-calendar me-2"></i> -- Seleccione un año --</option>';
                data.anios.forEach(anio => {
                    const option = document.createElement('option');
                    option.value = anio;
                    option.textContent = anio;
                    anioSelect.appendChild(option);
                });
            } else {
                anioSelect.innerHTML = '<option value=""><i class="fas fa-exclamation-triangle me-2"></i> -- No hay años disponibles --</option>';
            }
        })
        .catch(error => {
            console.error('Error al cargar años:', error);
            anioSelect.innerHTML = '<option value=""><i class="fas fa-exclamation-circle me-2"></i> -- Error al cargar años --</option>';
        });
}

// ==========================================
// VERIFICAR SI HAY NÓMINAS CONTABILIZADAS
// ==========================================
function verificarNominaContabilizada() {
    const tipoNomina = document.getElementById('tipoNominaSelect').value;
    const periodoData = document.getElementById('periodoSelect').value;
    const btnExportar = document.getElementById('btnExportar');
    
    if (!btnExportar) return;
    
    if (!tipoNomina || !periodoData) {
        btnExportar.disabled = true;
        btnExportar.classList.remove('dbf', 'xlsx', 'xml');
        btnExportar.innerHTML = '<i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa</span>';
        if (window.formatoSeleccionado) actualizarTextoBoton(window.formatoSeleccionado);
        return;
    }
    
    let periodo;
    try {
        periodo = JSON.parse(periodoData);
    } catch(e) {
        btnExportar.disabled = true;
        return;
    }
    
    btnExportar.disabled = true;
    btnExportar.classList.remove('dbf', 'xlsx', 'xml');
    btnExportar.innerHTML = '<i class="fas fa-spinner fa-pulse me-2"></i> Verificando...';
    
    let formData = new FormData();
    formData.append('accion', 'verificar_contabilizada');
    formData.append('periodo_desde', periodo.desde);
    formData.append('periodo_hasta', periodo.hasta);
    formData.append('tipo_nomina', tipoNomina);
    
    fetch('exportar_bandec.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.contabilizada) {
            btnExportar.disabled = false;
            if (window.formatoSeleccionado) {
                btnExportar.classList.add(window.formatoSeleccionado);
            }
            btnExportar.innerHTML = '<i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa</span>';
            if (window.formatoSeleccionado) actualizarTextoBoton(window.formatoSeleccionado);
        } else {
            btnExportar.disabled = true;
            btnExportar.classList.remove('dbf', 'xlsx', 'xml');
            btnExportar.innerHTML = '<i class="fas fa-ban me-2"></i> No disponible - Nómina no contabilizada';
            
            const desdeParts = periodo.desde.split('-');
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                           'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const nombreMes = meses[parseInt(desdeParts[1]) - 1];
            
            Swal.fire({
                icon: 'warning',
                title: '<i class="fas fa-exclamation-triangle me-2"></i> Nómina no contabilizada',
                html: `<p>La nómina de tipo <strong>${tipoNomina}</strong> para el período <strong>${nombreMes}/${desdeParts[0]}</strong> no está contabilizada.</p>
                       <p>Para exportar al banco, la nómina debe estar en estado <strong style="color: 4;">"contabilizado"</strong>.</p>
                       <hr style="border-top: 0.0625rem solid rgba(148, 163, 184, 0.3); opacity: 1;">
                       <p>Diríjase a <strong>Nóminas → ${tipoNomina}</strong> y contabilice la nómina.</p>`,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: '#3B82F6',
                background: '#1E1E1E',
                color: '#FFFFFF'
            });
        }
    })
    .catch(error => {
        console.error('Error verificando nómina:', error);
        btnExportar.disabled = true;
        btnExportar.classList.remove('dbf', 'xlsx', 'xml');
        btnExportar.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Error de verificación';
        
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-wifi me-2"></i> Error de conexión',
            text: 'No se pudo verificar el estado de la nómina. Intente nuevamente.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#EF4444',
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
    });
}

// ==========================================
// INICIALIZACIÓN PRINCIPAL
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar variables globales
    window.formatoSeleccionado = '';
    
    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', logoutLogic);
    const logoutSidebarBtn = document.getElementById('logoutSidebarBtn');
    if (logoutSidebarBtn) logoutSidebarBtn.addEventListener('click', logoutLogic);
    
    // Botón exportar principal
    const btnExportar = document.getElementById('btnExportar');
    if (btnExportar) {
        btnExportar.addEventListener('click', function() {
            mostrarPreviewYExportar();
        });
    }
    
    // Restaurar botón exportar al cerrar modal de preview
    const modalPreview = document.getElementById('modalPreview');
    if (modalPreview) {
        modalPreview.addEventListener('hidden.bs.modal', function() {
            const btn = document.getElementById('btnExportar');
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '';
                if (window.formatoSeleccionado) {
                    btn.innerHTML = '<i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa (' + window.formatoSeleccionado.toUpperCase() + ')</span>';
                } else {
                    btn.innerHTML = '<i class="fas fa-download me-2"></i><span id="btnTexto">Exportar Nómina Acreditativa</span>';
                }
            }
        });
    }
    
    // Botones de plantillas en blanco
    const plantillaBtns = document.querySelectorAll('.btn-plantilla');
    if (plantillaBtns.length > 0) {
        const plantillaNombres = {
            'dbf': 'DBF',
            'xlsx': 'Excel (XLSX)',
            'xml': 'XML'
        };
        const plantillaIconos = {
            'dbf': 'fa-database',
            'xlsx': 'fa-file-excel',
            'xml': 'fa-code'
        };
        const plantillaArchivos = {
            'dbf': 'nomina.dbf',
            'xlsx': 'nomina.xlsx',
            'xml': 'nomina.xml'
        };

        plantillaBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const formato = this.getAttribute('data-plantilla');
                const nombre = plantillaNombres[formato] || formato;
                const icono = plantillaIconos[formato] || 'fa-file-invoice';
                const archivo = plantillaArchivos[formato] || ('nomina.' + formato);

                Swal.fire({
                    title: '<i class="fas ' + icono + ' me-2"></i> ¿Descargar plantilla ' + nombre + '?',
                    html: '<p>Se generará el archivo <strong>' + archivo + '</strong> en blanco con la estructura:</p>'
                        + '<ul style="text-align:left; display:inline-block; margin-top:0.625rem;">'
                        + '<li><strong>NID</strong> (11 caracteres)</li>'
                        + '<li><strong>CUENTA</strong> (16 caracteres)</li>'
                        + '<li><strong>IMPORTE</strong> (18 dígitos, 2 decimales)</li>'
                        + '</ul>'
                        + '<p style="margin-top:0.625rem;">Sirve como plantilla para llenar los datos manualmente.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-download me-2"></i>Generar plantilla',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    confirmButtonColor: '#3B82F6',
                    cancelButtonColor: '#EF4444',
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando plantilla...',
                        text: 'Creando el archivo ' + archivo,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: '#1E1E1E',
                        color: '#FFFFFF',
                        didOpen: () => { Swal.showLoading(); }
                    });

                    const formData = new FormData();
                    formData.append('accion', 'plantilla_' + formato);

                    fetch('exportar_bandec.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            descargarArchivo(data.descarga, data.archivo);
                            Swal.fire({
                                icon: 'success',
                                title: '<i class="fas fa-check-circle me-2"></i> Plantilla generada',
                                html: '<p><strong>Archivo:</strong> ' + data.archivo + '</p><p>Estructura lista: NID, CUENTA, IMPORTE (sin registros).</p>',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                                confirmButtonColor: '#3B82F6',
                                background: '#1E1E1E',
                                color: '#FFFFFF',
                                timer: 4000,
                                timerProgressBar: true
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '<i class="fas fa-exclamation-triangle me-2"></i> Error',
                                text: data.mensaje || 'No se pudo generar la plantilla',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: '#EF4444',
                                background: '#1E1E1E',
                                color: '#FFFFFF'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error generando plantilla:', error);
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: '<i class="fas fa-wifi me-2"></i> Error de conexión',
                            text: 'No se pudo generar la plantilla. Intente nuevamente.',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                            confirmButtonColor: '#EF4444',
                            background: '#1E1E1E',
                            color: '#FFFFFF'
                        });
                    });
                });
            });
        });
    }
    
    // Selectores de filtros
    const tipoNominaSelect = document.getElementById('tipoNominaSelect');
    const anioSelect = document.getElementById('anioSelect');
    const periodoSelect = document.getElementById('periodoSelect');
    
    if (tipoNominaSelect) {
        tipoNominaSelect.addEventListener('change', function() {
            const tipoNomina = this.value;
            const anio = anioSelect ? anioSelect.value : '';
            if (tipoNomina && anio) {
                cargarPeriodos(tipoNomina, anio, false);
            } else {
                if (periodoSelect) periodoSelect.innerHTML = '<option value=""><i class="fas fa-info-circle me-2"></i>-- Seleccione tipo y año --</option>';
                const totalsPanel = document.getElementById('totalsPanel');
                if (totalsPanel) totalsPanel.style.display = 'none';
                verificarSeleccionPeriodoValida();
            }
        });
    }
    
    if (anioSelect) {
        anioSelect.addEventListener('change', function() {
            const tipoNomina = tipoNominaSelect ? tipoNominaSelect.value : '';
            const anio = this.value;
            if (tipoNomina && anio) {
                cargarPeriodos(tipoNomina, anio, false);
            } else {
                if (periodoSelect) periodoSelect.innerHTML = '<option value=""><i class="fas fa-info-circle me-2"></i>-- Seleccione tipo y año --</option>';
                const totalsPanel = document.getElementById('totalsPanel');
                if (totalsPanel) totalsPanel.style.display = 'none';
                verificarSeleccionPeriodoValida();
            }
        });
    }
    
    if (periodoSelect) {
        periodoSelect.addEventListener('change', function() {
            verificarSeleccionPeriodoValida();
            calcularTotales();
        });
    }
    
    // Cards de formato
    const cards = document.querySelectorAll('.card-option');
    function seleccionarFormato(cardEl) {
        cards.forEach(c => c.classList.remove('selected'));
        cardEl.classList.add('selected');
        window.formatoSeleccionado = cardEl.getAttribute('data-format');
        const formatoSeleccionadoInput = document.getElementById('formatoSeleccionado');
        if (formatoSeleccionadoInput) formatoSeleccionadoInput.value = window.formatoSeleccionado;
        actualizarTextoBoton(window.formatoSeleccionado);
        const btnExportarElem = document.getElementById('btnExportar');
        if (btnExportarElem && !btnExportarElem.disabled) {
            btnExportarElem.classList.remove('dbf', 'xlsx', 'xml');
            btnExportarElem.classList.add(window.formatoSeleccionado);
        }
    }
    if (cards.length > 0) {
        cards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.type === 'checkbox') return;
                seleccionarFormato(this);
                // Selección por clic en la tarjeta: desmarcar todos los checks ZIP
                zipCheckboxes.forEach(cb => cb.checked = false);
            });
        });
    }
    
    // Exclusión mutua de checkboxes ZIP: marcar el check también selecciona su tarjeta
    const zipCheckboxes = document.querySelectorAll('.zip-checkbox');
    if (zipCheckboxes.length > 0) {
        zipCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function(e) {
                e.stopPropagation();
                if (this.checked) {
                    zipCheckboxes.forEach(cb => {
                        if (cb !== this) cb.checked = false;
                    });
                    const cardEl = this.closest('.card-option');
                    if (cardEl) seleccionarFormato(cardEl);
                }
            });
        });
    }
    
    // Botones del modal
    const confirmExportBtn = document.getElementById('confirmExportBtn');
    if (confirmExportBtn) {
        confirmExportBtn.addEventListener('click', function() {
            if (window.previewData) {
                const modalElement = document.getElementById('modalPreview');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
                setTimeout(() => {
                    ejecutarExportacion(
                        window.previewData.periodoDesde,
                        window.previewData.periodoHasta,
                        window.previewData.tipoNomina,
                        window.previewData.formato,
                        window.previewData.comprimirZip
                    );
                }, 200);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No hay datos válidos',
                    html: '<p>No se puede exportar porque ningún trabajador de esta nómina tiene <strong>número de tarjeta (cuenta bancaria) y CI</strong> registrados.</p><p style="font-size:0.9rem; color:#94a3b8;">Para exportar al banco, actualice la cuenta bancaria de los trabajadores en su ficha y vuelva a intentarlo.</p>',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: '#3B82F6',
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                });
            }
        });
    }
    
    const printPreviewBtn = document.getElementById('printPreviewBtn');
    if (printPreviewBtn) {
        printPreviewBtn.addEventListener('click', function() {
            if (window.previewData && window.previewData.total_registros > 0) {
                const modalElement = document.getElementById('modalPreview');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
                setTimeout(() => {
                    imprimirPreview();
                }, 200);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No hay datos para imprimir',
                    text: 'No se puede imprimir porque ningún trabajador de esta nómina tiene tarjeta (cuenta bancaria) y CI.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: '#3B82F6',
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                });
            }
        });
    }

    // Dropdown de exportación personalizado
    const exportBtn = document.getElementById('exportDropdownBtn');
    const exportMenu = document.getElementById('exportDropdownMenu');
    
    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = exportMenu.style.display === 'block';
            exportMenu.style.display = isVisible ? 'none' : 'block';
        });
        
        document.addEventListener('click', function(e) {
            if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
                exportMenu.style.display = 'none';
            }
        });
        
		const exportItems = document.querySelectorAll('.dropdown-export-item');
		exportItems.forEach(item => {
			item.addEventListener('click', async function(e) {
				e.stopPropagation();
				const format = this.getAttribute('data-format');
				exportMenu.style.display = 'none';
				
				const formatNames = {
					'excel': 'Excel (.xlsx)',
					'word': 'Word (.docx)',
					'pdf': 'PDF (.pdf)',
					'csv': 'CSV (.csv)',
					'txt': 'TXT (.txt)'
				};
				const btnText = exportBtn.querySelector('span');
				if (btnText) btnText.textContent = formatNames[format] || 'Exportar vista';
				
				Swal.fire({
					title: '<i class="fas fa-spinner fa-pulse me-2"></i> Preparando exportación...',
					text: 'Procesando datos, por favor espere...',
					allowOutsideClick: false,
					allowEscapeKey: false,
					showConfirmButton: false,
					background: '#1F1F1F',
					color: '#FFFFFF',
					didOpen: () => { Swal.showLoading(); }
				});
				
				await new Promise(resolve => setTimeout(resolve, 500));
				
				// ============================================
				// OBTENER DATOS DE AMBAS SECCIONES
				// ============================================
				
				// 1. Datos CON TARJETA (desde obtenerDatosCompletosExportacion)
				const datosConTarjeta = await obtenerDatosCompletosExportacion();
				
				// 2. Datos SIN TARJETA (desde window.previewData.registros_sin_tarjeta)
				let datosSinTarjeta = [];
				if (window.previewData?.registros_sin_tarjeta) {
					datosSinTarjeta = window.previewData.registros_sin_tarjeta.map((r, idx) => {
						// Obtener nombre del trabajador si está disponible
						let nombre = r.nombre_completo || 'No especificado';
						// Si no tiene nombre_completo, intentar obtenerlo desde el mapa de nombres
						if (nombre === 'No especificado' && window.nombresMapa) {
							const ciClean = r.ci.toString().replace(/\D/g, '').padStart(11, '0');
							nombre = window.nombresMapa[ciClean] || nombre;
						}
						return {
							no: idx + 1,
							ci: r.ci,
							nombre: nombre,
							cuenta: '--',  // Sin tarjeta, no tiene cuenta
							importe: parseFloat(r.neto || r.importe || 0)
						};
					});
				}
				
				await new Promise(resolve => setTimeout(resolve, 300));
				Swal.close();
				
				// Verificar si hay datos CON TARJETA
				if (datosConTarjeta.length === 0 && datosSinTarjeta.length === 0) {
					await Swal.fire({
						icon: 'error',
						title: '<i class="fas fa-exclamation-triangle me-2"></i> Error',
						text: 'No hay registros válidos para exportar en ninguna sección.',
						confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
						confirmButtonColor: '#EF4444',
						background: '#1E1E1E',
						color: '#FFFFFF',
						timer: 3000,
						timerProgressBar: true
					});
					return;
				}
				
				// Si solo hay datos SIN TARJETA, mostrar advertencia
				if (datosConTarjeta.length === 0 && datosSinTarjeta.length > 0) {
					const result = await Swal.fire({
						icon: 'warning',
						title: '<i class="fas fa-info-circle me-2"></i> Solo trabajadores SIN TARJETA',
						html: `<p>No hay trabajadores CON TARJETA en este período.</p>
							   <p>Se exportarán <strong>${datosSinTarjeta.length}</strong> trabajadores SIN TARJETA.</p>`,
						confirmButtonText: '<i class="fas fa-check me-2"></i>Continuar',
						cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
						showCancelButton: true,
						confirmButtonColor: '#3B82F6',
						cancelButtonColor: '#EF4444',
						background: '#1E1E1E',
						color: '#FFFFFF'
					});
					if (!result.isConfirmed) return;
				}
				
				Swal.fire({
					title: '<i class="fas fa-file-export me-2"></i> Generando archivo...',
					text: `Creando archivo ${formatNames[format]}`,
					allowOutsideClick: false,
					showConfirmButton: false,
					background: '#1F1F1F',
					color: '#FFFFFF',
					didOpen: () => { Swal.showLoading(); }
				});
				
				// Llamar a la función de exportación correspondiente con AMBOS conjuntos de datos
				if (format === 'excel') {
					exportarExcelLocal(datosConTarjeta, datosSinTarjeta);
				} else if (format === 'word') {
					exportarWordLocal(datosConTarjeta, datosSinTarjeta);
				} else if (format === 'pdf') {
					exportarPDFLocal(datosConTarjeta, datosSinTarjeta);
				} else if (format === 'csv') {
					exportarCSVLocal(datosConTarjeta, datosSinTarjeta);
				} else if (format === 'txt') {
					exportarTXTLocal(datosConTarjeta, datosSinTarjeta);
				}
				
				setTimeout(() => {
					Swal.close();
					Swal.fire({
						icon: 'success',
						title: '<i class="fas fa-check-circle me-2"></i> Exportación completada',
						text: `Archivo ${formatNames[format]} generado correctamente.`,
						confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
						confirmButtonColor: '#3B82F6',
						background: '#1E1E1E',
						color: '#FFFFFF',
						timer: 2500,
						timerProgressBar: true
					});
				}, 800);
			});
			
			item.addEventListener('mouseenter', function() {
				this.style.backgroundColor = 'rgba(96, 165, 250, 0.2)';
			});
			item.addEventListener('mouseleave', function() {
				this.style.backgroundColor = 'transparent';
			});
		});
    }
    
    // Inicializar cards deshabilitados
    toggleFormatCards(false);
    
    // Cargar datos
    cargarTiposNomina();
    cargarAnios();
    
    const btnExportarInicial = document.getElementById('btnExportar');
    if (btnExportarInicial) btnExportarInicial.disabled = true;
    
    // Iniciar reloj
    updateClock();
    setInterval(updateClock, 1000);
    
});

</script>

<!-- Botones flotantes de navegación rápida -->
<style>
.scroll-quick-btns { position: fixed; right:1.25rem; bottom:1.25rem; display: flex; flex-direction: column; gap:0.625rem; z-index: 950; }
.scroll-quick-btn {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; border: 0.0625rem solid rgba(148, 163, 184, .35);
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, .5); transition: all .2s;
}
.scroll-quick-btn:hover { transform: translateY(-0.125rem); filter: brightness(1.15); }
.scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }
@media print { .scroll-quick-btns { display: none !important; } }
</style>
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>
<script>
(function () {
    var btnTop = document.getElementById('btnScrollTop');
    var btnBottom = document.getElementById('btnScrollBottom');
    if (!btnTop || !btnBottom) return;
    function actualizarVisibilidad() {
        var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        var y = window.scrollY || document.documentElement.scrollTop;
        btnTop.classList.toggle('hidden', y < 150);
        btnBottom.classList.toggle('hidden', maxScroll <= 150 || y > maxScroll - 150);
    }
    btnTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    btnBottom.addEventListener('click', function () { window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }); });
    window.addEventListener('scroll', actualizarVisibilidad);
    actualizarVisibilidad();
})();
</script>
</body>
</html>