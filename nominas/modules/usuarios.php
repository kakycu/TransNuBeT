<?php
// modules/usuarios.php - Gestión de Usuarios del Sistema
require_once '../config/database.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Control de acceso por rol: solo quienes tienen permiso de ver usuarios
if (!permiso_puede('usuarios', 'ver')) {
    permiso_denegar_acceso('Usuarios');
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';

$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// Usuario a editar directamente vía URL (?editar=ID)
$editar_usuario_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
// Solo quienes tienen permiso de edición de usuarios pueden editar a otros; el resto solo su propio perfil
if ($editar_usuario_id > 0 && !permiso_puede('usuarios', 'editar') && $editar_usuario_id !== $usuario_actual_id) {
    $editar_usuario_id = 0;
}

// Configuración empresa
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Ruta del logo
$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// Obtener usuarios para la tabla
$stmt_usuarios = $pdo->query("
    SELECT u.*, r.descripcion as rol_nombre 
    FROM clasif_usuarios u 
    LEFT JOIN clasif_rol r ON u.rol_id = r.id 
    ORDER BY u.nombre, u.apellidos
");
$usuarios_lista = $stmt_usuarios->fetchAll();

// --- Estadísticas reales de usuarios ---
$stats_total = count($usuarios_lista);
$stats_activos = 0;
$stats_inactivos = 0;
$stats_roles = [];
$stats_edad_rangos = ['<= 30' => 0, '31 - 40' => 0, '41 - 50' => 0, '51 - 60' => 0, '> 60' => 0];
$stats_sin_edad = 0;

// La edad se deriva del Carnet de Identidad cubano (11 dígitos: AAMMDD...)
function edadUsuarioDesdeCI($ci) {
    $ci = preg_replace('/\D/', '', (string)$ci);
    if (strlen($ci) !== 11) return null;
    $yy = (int)substr($ci, 0, 2);
    $mm = (int)substr($ci, 2, 2);
    $dd = (int)substr($ci, 4, 2);
    if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) return null;
    $anio2 = (int)date('y');
    $anio = ($yy <= $anio2) ? (2000 + $yy) : (1900 + $yy);
    try {
        $nac = new DateTime(sprintf('%04d-%02d-%02d', $anio, $mm, $dd));
        $hoy = new DateTime('today');
        return $hoy->diff($nac)->y;
    } catch (Exception $e) {
        return null;
    }
}

foreach ($usuarios_lista as $u) {
    if (!empty($u['activo'])) $stats_activos++; else $stats_inactivos++;
    $rol = $u['rol_nombre'] ?: 'Sin rol';
    $stats_roles[$rol] = ($stats_roles[$rol] ?? 0) + 1;
    $edad = edadUsuarioDesdeCI($u['no_ci'] ?? '');
    if ($edad === null) {
        $stats_sin_edad++;
    } elseif ($edad <= 30) {
        $stats_edad_rangos['<= 30']++;
    } elseif ($edad <= 40) {
        $stats_edad_rangos['31 - 40']++;
    } elseif ($edad <= 50) {
        $stats_edad_rangos['41 - 50']++;
    } elseif ($edad <= 60) {
        $stats_edad_rangos['51 - 60']++;
    } else {
        $stats_edad_rangos['> 60']++;
    }
}
arsort($stats_roles);
$stats_roles_resumen = implode(' · ', array_map(fn($r, $c) => $r . ': ' . $c, array_keys($stats_roles), $stats_roles));
$stats_edades_resumen = implode(' · ', array_map(fn($r, $c) => $r . ': ' . $c, array_keys($stats_edad_rangos), $stats_edad_rangos));

// Todos los roles con su cantidad (incluyendo los que no tienen usuarios)
$stats_roles_todos = [];
$stmt_roles_stats = $pdo->query("SELECT r.id, r.descripcion, COUNT(u.id) AS total 
                                 FROM clasif_rol r 
                                 LEFT JOIN clasif_usuarios u ON u.rol_id = r.id 
                                 GROUP BY r.id, r.descripcion 
                                 ORDER BY total DESC, r.descripcion");
foreach ($stmt_roles_stats->fetchAll() as $rs) {
    $stats_roles_todos[] = ['descripcion' => $rs['descripcion'], 'total' => (int)$rs['total']];
}

$puede_crear_usuario = permiso_puede('usuarios', 'crear');
$puede_editar_usuario = permiso_puede('usuarios', 'editar');
$puede_eliminar_usuario = permiso_puede('usuarios', 'eliminar');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Gestión de Usuarios</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/cropper.css">
    
    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

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

        .glass-card {
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(11.875rem, 1fr)); gap:1rem; margin-bottom:1.25rem; }
        .stat-card { padding:1.25rem; display: flex; align-items: center; justify-content: space-between; z-index: 1 !important; }
        .stat-card .stat-icon { width:3rem; height:3rem; border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; font-size:1.5rem; background: rgba(255,255,255,0.05); flex-shrink: 0; }
        .stat-card h6 { font-size:0.8rem; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; margin-bottom:0.3125rem; }
        .stat-card h3 { font-size:1.8rem; font-weight: 700; margin:0; color: white; }

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

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

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        /* User Menu & Dropdowns */
/* ========================================== */
/* FIX: Menú de usuario por encima de todo    */
/* ========================================== */

.user-menu {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-toggle {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-menu {
    z-index: 99999 !important;
    position: absolute !important;
    top:100% !important;
    right:0 !important;
    left:auto !important;
    min-width:13.75rem !important;
    background: rgba(32, 32, 40, 0.98) !important;
    backdrop-filter: blur(1.25rem) !important;
    border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 0.75rem !important;
    padding:0.5rem !important;
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.5) !important;
}

/* Asegurar que el topbar no interfiera */
.win-topbar {
    position: relative !important;
    z-index: 100 !important;
}

/* Asegurar que el main-container no bloquee el dropdown */
.main-container {
    position: relative !important;
    z-index: 1 !important;
}

/* Para el botón del avatar */
.user-avatar {
    position: relative !important;
    z-index: 9999 !important;
}
        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1);
            border-radius: 0.625rem; color: white; font-size:0.85rem; transition: all 0.2s;
            cursor: pointer; padding:0.5rem 1rem; display: inline-flex; align-items: center; gap:0.5rem;
            text-decoration: none;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-0.0625rem); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-0.0625rem); }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }
        .btn-win-success { background: rgba(var(--color-success-rgb), 0.2); border-color: rgba(var(--color-success-rgb), 0.5); }
        .btn-win-success:hover { background: rgba(var(--color-success-rgb), 0.4); border-color: var(--color-success); }
        .btn-win-warning { background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5); }
        .btn-win-warning:hover { background: rgba(245, 158, 11, 0.4); border-color: #f59e0b; }

        .form-label { color: rgba(255, 255, 255, 0.85); font-size:0.8rem; font-weight: 500; margin-bottom:0.375rem; }
        
        .form-control, .form-select, input.form-control, textarea.form-control, select.form-select {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 0.625rem !important;
            color: #ffffff !important;
            padding:0.5rem 0.75rem !important;
            font-size:0.85rem !important;
        }

        /* Fix: chevron visible en selects (tema oscuro) */
        .form-select, select.form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            background-color: var(--panel) !important;
            padding-right:2.5rem !important;
        }

        /* Chevron apuntando arriba mientras el dropdown está abierto */
        .form-select.select-open {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 15 12 9 18 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            background-color: var(--panel) !important;
            padding-right:2.5rem !important;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--panel) !important;
            border-color: #60a5fa !important;
            outline: none !important;
            box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2) !important;
            color: #ffffff !important;
        }
        
        .input-group-text {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* Iconos nativos de fecha/hora visibles en tema oscuro */
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
            opacity: 0.9;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover,
        input[type="time"]::-webkit-calendar-picker-indicator:hover,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        /* ========================================== */
        /* ESTILOS OSCUROS PARA TABLAS - DARK THEME   */
        /* ========================================== */
        
        .table-responsive {
            border-radius: 0.75rem;
            overflow-x: auto;
            background: rgba(15, 15, 20, 0.4);
            border: 0.0625rem solid rgba(255, 255, 255, 0.05);
        }
        
        #rangosTable, #tasasTable, #tablaUsuarios {
            background: transparent !important;
            margin-bottom:0;
        }
        #rangosTable thead th, #tasasTable thead th, #tablaUsuarios thead th {
            background: rgba(22, 22, 30, 0.98) !important;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            font-size:0.7rem !important;
            text-transform: uppercase !important;
            letter-spacing:0.0312rem !important;
            padding:0.75rem 0.5rem !important;
            font-weight: 600 !important;
            position: sticky;
            top:0;
            z-index: 10;
        }
        #rangosTable tbody tr, #tasasTable tbody tr, #tablaUsuarios tbody tr {
            background: var(--panel-2) !important;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.05) !important;
        }
        #rangosTable tbody tr:hover, #tasasTable tbody tr:hover, #tablaUsuarios tbody tr:hover {
            background: rgba(96, 165, 250, 0.1) !important;
        }
        #rangosTable td, #tasasTable td, #tablaUsuarios td {
            padding:0.625rem 0.5rem !important;
            vertical-align: middle !important;
            border: none !important;
            background: transparent !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        #rangosTable .form-control-sm {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 0.5rem !important;
            color: #ffffff !important;
            padding:0.375rem 0.625rem !important;
            font-size:0.75rem !important;
        }
        #rangosTable .btn-win-danger, #tasasTable .btn-win-danger {
            background: rgba(220, 53, 69, 0.2) !important;
            border: 0.0625rem solid rgba(220, 53, 69, 0.5) !important;
            border-radius: 0.375rem !important;
            padding:0.25rem 0.5rem !important;
            color: #f87171 !important;
        }
        #rangosTable .btn-win-danger:hover, #tasasTable .btn-win-danger:hover {
            background: rgba(220, 53, 69, 0.4) !important;
            color: #ffffff !important;
        }
        #rangosTable tfoot td {
            background: var(--panel) !important;
            padding:0.75rem 0.5rem !important;
            border-top: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
        }
        #rangosTable .btn-win-sm {
            background: rgba(59, 130, 246, 0.2) !important;
            border: 0.0625rem solid rgba(59, 130, 246, 0.5) !important;
            color: #60a5fa !important;
            padding:0.3125rem 0.75rem !important;
            font-size:0.7rem !important;
            border-radius: 0.5rem !important;
        }
        
        #tablaUsuarios .avatar-small {
            width:2rem;
            height:2rem;
            border-radius: 50%;
            object-fit: cover;
            transition: transform 0.25s ease;
            cursor: zoom-in;
        }
        #tablaUsuarios .avatar-small:hover {
            transform: scale(2.6);
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.45);
        }
        #tablaUsuarios .avatar-iniciales {
            width:2rem;
            height:2rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin:0 auto;
        }
        #tablaUsuarios .badge {
            font-size:0.7rem;
            padding:0.25rem 0.5rem;
        }
        #tablaUsuarios .btn-group-sm .btn-win-sm {
            padding:0.25rem 0.5rem;
            font-size:0.65rem;
        }
        
        /* Avatar preview */
        .avatar-preview {
            width:6.25rem;
            height:6.25rem;
            border-radius: 50%;
            border: 0.1875rem solid #60a5fa;
            margin:0 auto;
            overflow: hidden;
            background: var(--panel);
            position: relative;
            cursor: pointer;
        }
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height:100%;
            width:100%;
            flex-direction: column;
            font-size:2rem;
            color: rgba(255, 255, 255, 0.4);
        }
        .edit-overlay {
            position: absolute;
            bottom:0;
            left:0;
            right:0;
            background: rgba(0, 0, 0, 0.75);
            color: white;
            text-align: center;
            padding:0.5rem;
            font-size:0.5625rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0 0 50% 50%;
        }
        .avatar-preview:hover .edit-overlay { opacity: 1; }
        
        /* Crop modal */
        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 1.25rem 1.25rem;
            min-height:25rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .preview-container {
            width:9.375rem;
            height:9.375rem;
            margin:0 auto;
            overflow: hidden;
            border: 0.1875rem solid #60a5fa;
            border-radius: 50%;
            background: #2d2d2d;
        }
        
        .alert-success { background: rgba(var(--color-success-rgb), 0.2); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.3); color: var(--color-success); border-radius: 0.625rem; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 0.0625rem solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 0.625rem; }
        .alert-info { background: rgba(59, 130, 246, 0.2); border: 0.0625rem solid rgba(59, 130, 246, 0.3); color: #60a5fa; border-radius: 0.625rem; }
        .alert-warning { background: rgba(245, 158, 11, 0.15); border: 0.0625rem solid rgba(245, 158, 11, 0.3); color: #fbbf24; border-radius: 0.625rem; }
        
        .modal-content-win {
            background: linear-gradient(135deg, var(--card), var(--panel-2));
            backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.25rem;
            color: var(--txt);
        }
        .modal-header-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.125rem 1.125rem 0 0;
        }
        .modal-footer-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-top: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 0 0 1.125rem 1.125rem;
        }
        
        .date-badge {
            background: rgba(255, 255, 255, 0.08);
            padding:0.5rem 1rem;
            border-radius: 0.75rem;
            font-size:0.85rem;
            color: #ffffff;
        }
        #liveClock { display: inline-block; min-width:5.3125rem; text-align: center; }
        
        .text-muted, .text-secondary { color: #9ca3af !important; }
        .text-white-50 { color: rgba(255, 255, 255, 0.5) !important; }
        
        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        
        hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        
        .swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }
        .swal2-styled.swal2-confirm { background-color: var(--color-success) !important; }
        .swal2-styled.swal2-cancel { background-color: #6b7280 !important; }
        
        .search-box { position: relative; }
        .search-box i { position: absolute; left:0.75rem; top:50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); z-index: 10; }
        .search-box input { padding-left:2rem !important; }
		#backupNamePreview {
			background: rgba(96, 165, 250, 0.1);
			padding:0.125rem 0.5rem;
			border-radius: 0.25rem;
			font-family: monospace;
			font-size:0.85rem;
		}

		#backupNameModal .form-check-input:checked {
			background-color: var(--color-success);
			border-color: var(--color-success);
		}

		#backupNameModal .form-check-label {
			cursor: pointer;
		}
::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    opacity: 1 !important;
}

:-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

::-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

/* Para inputs específicos que puedan necesitar más visibilidad */
.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4) !important;
}

.form-control-sm::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    font-size:0.75rem !important;
}

        /* ========================================== */
        /* MODAL USUARIO MEJORADO - DISEÑO Y UX       */
        /* ========================================== */

        .modal-avatar-header {
            width:3.5rem; height:3.5rem; border-radius: 50%;
            border: 0.125rem solid #60a5fa; overflow: hidden;
            background: var(--panel); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .modal-avatar-header img { width:100%; height:100%; object-fit: cover; display: block; }
        .modal-avatar-header .avatar-iniciales-lg {
            width:100%; height:100%; font-size:1.15rem; font-weight: 700;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center; color: #fff;
        }

        .mode-badge {
            display: inline-flex; align-items: center; gap:0.375rem;
            padding:0.1875rem 0.625rem; border-radius: 1.25rem; font-size:0.66rem;
            font-weight: 600; letter-spacing:0.0312rem; text-transform: uppercase;
            white-space: nowrap;
        }
        .mode-badge.create { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success-soft); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.4); }
        .mode-badge.edit { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 0.0625rem solid rgba(59, 130, 246, 0.4); }

        .modal-subtitle { font-size:0.75rem; color: rgba(255, 255, 255, 0.5); }

        .section-title {
            display: flex; align-items: center; gap:0.5rem;
            font-size:0.78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing:0.05rem; color: rgba(255, 255, 255, 0.75);
            padding-bottom:0.5rem; margin-bottom:1rem;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08);
        }
        .section-title i { color: #60a5fa; font-size:0.85rem; }

        .card-collapse-title {
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap:0.5rem;
            transition: color 0.2s ease;
        }
        .card-collapse-title:hover {
            color: #60a5fa;
        }
        .card-collapse-title:hover .collapse-chevron {
            color: #60a5fa;
        }
        .card-collapse-title .collapse-chevron {
            font-size:0.7rem;
            color: rgba(255, 255, 255, 0.4);
            transition: transform 0.25s ease;
        }
        .card-collapse-title:not(.collapsed) .collapse-chevron {
            transform: rotate(180deg);
            color: #60a5fa;
        }

        .usuario-modal-body {
            max-height:calc(100vh - 16.25rem);
            overflow-y: auto;
        }
        .usuario-modal-body::-webkit-scrollbar { width:0.375rem; }
        .usuario-modal-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 0.625rem; }

        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right:2.625rem !important; }
        .password-toggle {
            position: absolute; right:0.375rem; top:50%; transform: translateY(-50%);
            background: transparent; border: none; color: rgba(255, 255, 255, 0.5);
            padding:0.375rem 0.5rem; cursor: pointer; z-index: 5;
        }
        .password-toggle:hover { color: #60a5fa; }

        .form-usuario-status {
            display: flex; align-items: center; gap:0.5rem;
            padding:0.625rem 0.875rem; border-radius: 0.625rem; font-size:0.78rem;
            margin-bottom:1.125rem;
        }
        .form-usuario-status.edit { background: rgba(59, 130, 246, 0.1); border: 0.0625rem solid rgba(59, 130, 246, 0.25); color: #93c5fd; }
        .form-usuario-status.create { background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.25); color: var(--color-success-soft); }

        .btn-guardar-loading { pointer-events: none; opacity: 0.7; }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-users me-2" style="color: #60a5fa;"></i>Gestión de Usuarios del Sistema</h1>
                <p><i class="fas fa-user-shield me-1"></i> Administre los usuarios, roles y accesos del sistema</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Estadísticas de usuarios -->
    <div class="stats-grid fade-in-up" style="animation-delay: 0.06s;">
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-users me-1"></i> Total Usuarios</h6>
                <h3><?php echo $stats_total; ?></h3>
                <div style="display: flex; align-items: baseline; gap:0.5rem; margin-top:0.25rem;">
                    <span class="badge" style="font-size:0.75rem; background: rgba(var(--color-success-soft-rgb),0.12); color: var(--color-success-soft); font-weight: 600;"><i class="fas fa-user-check me-1"></i><?php echo $stats_activos; ?> Activos</span>
                    <span class="badge" style="font-size:0.75rem; background: rgba(248,113,113,0.12); color: #f87171; font-weight: 600;"><i class="fas fa-user-slash me-1"></i><?php echo $stats_inactivos; ?> Inactivos</span>
                </div>
            </div>
            <div class="stat-icon" style="color: #60a5fa;"><i class="fas fa-users"></i></div>
        </div>
        <div class="glass-card stat-card">
            <div style="min-width:0;">
                <h6><i class="fas fa-user-shield me-1"></i> Roles</h6>
                <div style="display: flex; flex-wrap: wrap; gap:0.375rem 0.875rem; margin-top:0.375rem;">
                    <?php foreach ($stats_roles_todos as $rol_stat): ?>
                    <span class="badge" style="font-size:0.78rem; background: rgba(255,255,255,0.08); color: #d1d5db; font-weight: 500;">
                        <?php echo htmlspecialchars($rol_stat['descripcion']); ?>
                        <b style="color: #60a5fa; margin-left:0.25rem;"><?php echo $rol_stat['total']; ?></b>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stat-icon" style="color: #a78bfa;"><i class="fas fa-user-shield"></i></div>
        </div>
        <div class="glass-card stat-card">
            <div style="min-width:0;">
                <h6><i class="fas fa-cake-candles me-1"></i> Rangos de Edad</h6>
                <div style="display: flex; flex-wrap: wrap; gap:0.375rem 0.875rem; margin-top:0.375rem;">
                    <?php foreach ($stats_edad_rangos as $rango => $cant): ?>
                    <span class="badge" style="font-size:0.78rem; background: rgba(255,255,255,0.08); color: #d1d5db; font-weight: 500;">
                        <?php echo $rango; ?> años
                        <b style="color: var(--color-success-soft); margin-left:0.25rem;"><?php echo $cant; ?></b>
                    </span>
                    <?php endforeach; ?>
                    <?php if ($stats_sin_edad > 0): ?>
                    <span class="badge" style="font-size:0.78rem; background: rgba(248,113,113,0.12); color: #f87171; font-weight: 500;">
                        Sin CI
                        <b style="margin-left:0.25rem;"><?php echo $stats_sin_edad; ?></b>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-icon" style="color: var(--color-success-soft);"><i class="fas fa-cake-candles"></i></div>
        </div>
    </div>

    <!-- Gestión de Usuarios -->
    <div class="glass-card fade-in-up" style="animation-delay: 0.12s;">
        <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseUsuarios" aria-expanded="true" aria-controls="collapseUsuarios">
                <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-users me-2" style="color: #60a5fa;"></i> Gestión de Usuarios del Sistema
            </h6>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button type="button" class="btn-win btn-win-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Imprimir o exportar el reporte de usuarios" data-tooltip="Imprimir / Exportar" data-tooltip-theme="primary">
                        <i class="fas fa-print me-1"></i> Imprimir / Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" style="z-index: 99999; box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.5);">
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); imprimirUsuarios();" title="Imprimir reporte" data-tooltip="Imprimir reporte" data-tooltip-theme="primary"><i class="fas fa-print me-2" style="color: #60a5fa;"></i>Imprimir Reporte</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); exportarUsuariosPDF();" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar PDF</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); exportarUsuariosExcel();" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel (XLSX)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); exportarUsuariosWord();" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word (DOCX)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); exportarUsuariosCSV();" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="secondary"><i class="fas fa-file-csv me-2" style="color: var(--color-success);"></i>Exportar a CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); exportarUsuariosTXT();" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
                    </ul>
                </div>
                <?php if ($puede_crear_usuario): ?>
                <button type="button" class="btn-win btn-win-primary btn-win-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="limpiarFormularioUsuario()" data-tooltip="Nuevo usuario" data-tooltip-theme="success">
                    <i class="fas fa-user-plus me-1"></i> Nuevo Usuario
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div id="collapseUsuarios" class="collapse show">
        <div class="p-3">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchUsuarioInput" class="form-control form-control-sm" placeholder="Buscar por nombre, usuario o CI..." title="Buscar por nombre o usuario" data-tooltip="Buscar por nombre o usuario" data-tooltip-theme="secondary">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterRolInput" class="form-select form-select-sm" title="Filtrar por rol" data-tooltip="Filtrar por rol" data-tooltip-theme="secondary">
                        <option value="">Todos los roles</option>
                        <?php $stmt_roles = $pdo->query("SELECT id, descripcion FROM clasif_rol ORDER BY descripcion"); while ($rol = $stmt_roles->fetch()): ?>
                            <option value="<?php echo $rol['descripcion']; ?>"><?php echo htmlspecialchars($rol['descripcion']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterEstadoInput" class="form-select form-select-sm" title="Filtrar por estado" data-tooltip="Filtrar por estado" data-tooltip-theme="secondary">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn-win btn-win-sm w-100" onclick="filtrarUsuarios()" title="Buscar usuarios" data-tooltip="Buscar usuarios" data-tooltip-theme="primary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </div>
            
            <div class="table-responsive" style="max-height:25rem; overflow-y: auto;">
                <table class="table table-sm table-hover" id="tablaUsuarios" style="min-width:50rem;">
                    <thead style="position: sticky; top:0; background: rgba(22, 22, 30, 0.95); z-index: 10;">
                        <tr><th>Avatar</th><th>Usuario</th><th>Nombre Completo</th><th>CI</th><th>Rol</th><th>Email</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody id="tablaUsuariosBody">
<?php foreach ($usuarios_lista as $usr):
    $nombre_completo = trim($usr['nombre'] . ' ' . $usr['apellidos']);
    $iniciales = strtoupper(substr($usr['nombre'], 0, 1) . substr($usr['apellidos'], 0, 1));
    $foto_url = !empty($usr['foto']) ? $usr['foto'] : null;
    
    if ($foto_url) {
        if (strpos($foto_url, 'assets/') === 0) {
            $foto_url = '../' . $foto_url;
        }
    }
    
    // Verificar si el archivo existe
    $foto_existe = false;
    if ($foto_url) {
        $ruta_foto_absoluta = $_SERVER['DOCUMENT_ROOT'] . '/nominas/' . str_replace('../', '', $foto_url);
        if (file_exists($ruta_foto_absoluta)) {
            $foto_existe = true;
        }
    }
?>
<tr data-id="<?php echo $usr['id']; ?>" data-rol="<?php echo htmlspecialchars($usr['rol_nombre'] ?? ''); ?>" data-estado="<?php echo $usr['activo']; ?>">
    <td class="text-center">
        <?php if ($foto_existe): ?>
            <img src="<?php echo $foto_url; ?>" class="avatar-small" style="width:2rem; height:2rem; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <div class="avatar-iniciales"><?php echo $iniciales; ?></div>
        <?php endif; ?>
    </td>
    <td><code><?php echo htmlspecialchars($usr['usuario']); ?></code></td>
    <td><strong><?php echo htmlspecialchars($nombre_completo); ?></strong></td>
    <td><?php echo htmlspecialchars($usr['no_ci']); ?></td>
    <td>
        <span class="badge <?php 
            if ($usr['rol_nombre'] == 'Administrador del Sistema') echo 'bg-danger';
            elseif ($usr['rol_nombre'] == 'Programador') echo 'bg-dark';
            elseif ($usr['rol_nombre'] == 'Supervisor General') echo 'bg-warning text-dark';
            elseif ($usr['rol_nombre'] == 'Facturador / Editor') echo 'bg-info';
            else echo 'bg-secondary';
        ?>">
            <?php echo htmlspecialchars($usr['rol_nombre'] ?? 'Sin rol'); ?>
        </span>
    </td>
<td style="color: inherit; text-decoration: none;">
    <?php if (!empty($usr['email'])): ?>
        <a href="mailto:<?php echo rawurlencode($usr['email']); ?>?subject=<?php echo rawurlencode('Correo desde la Página del Registro de usuarios Sistema de Nominas'); ?>" style="color: inherit; text-decoration: none;">
            <?php echo htmlspecialchars($usr['email']); ?>
        </a>
    <?php else: ?>
        -
    <?php endif; ?>
</td>
    <td>
        <span class="badge <?php echo $usr['activo'] ? 'bg-success' : 'bg-danger'; ?>">
            <?php echo $usr['activo'] ? 'Activo' : 'Inactivo'; ?>
        </span>
    </td>
    <td>
        <div class="btn-group btn-group-sm">
            <a class="btn-win btn-win-sm" href="users.php?id=<?php echo $usr['id']; ?>" title="Ver perfil" data-tooltip="Ver perfil" data-tooltip-theme="info"><i class="fas fa-user"></i></a>
            <?php if ($puede_editar_usuario): ?>
            <button class="btn-win btn-win-sm" onclick="editarUsuario(<?php echo $usr['id']; ?>)" title="Editar" data-tooltip="Editar" data-tooltip-theme="warning"><i class="fas fa-edit"></i></button>
            <button class="btn-win btn-win-sm <?php echo $usr['activo'] ? 'btn-win-warning' : 'btn-win-success'; ?>" onclick="toggleEstadoUsuario(<?php echo $usr['id']; ?>, <?php echo $usr['activo']; ?>, '<?php echo addslashes($nombre_completo); ?>')" title="<?php echo $usr['activo'] ? 'Desactivar' : 'Activar'; ?>" data-tooltip="<?php echo $usr['activo'] ? 'Desactivar' : 'Activar'; ?>" data-tooltip-theme="warning"><i class="fas <?php echo $usr['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i></button>
            <?php endif; ?>
            <?php if ($puede_eliminar_usuario): ?>
            <button class="btn-win btn-win-danger btn-win-sm" onclick="eliminarUsuario(<?php echo $usr['id']; ?>, '<?php echo addslashes($nombre_completo); ?>')" title="Eliminar" data-tooltip="Eliminar" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Modal para crear/editar usuarios con crop -->
<div class="modal fade" id="modalUsuario" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-avatar-header" id="headerAvatarModal">
                        <div class="avatar-iniciales-lg" id="headerAvatarIniciales">+</div>
                        <img id="headerAvatarImg" src="" alt="" style="display:none;">
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h5 class="modal-title mb-0" id="modalUsuarioTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                            <span class="mode-badge create" id="modalModeBadge"><i class="fas fa-plus-circle"></i> Crear</span>
                        </div>
                        <span class="modal-subtitle" id="modalUsuarioSubtitle">Registre un nuevo usuario del sistema</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
            </div>
            <form id="formUsuario" method="POST" autocomplete="off">
                <input type="hidden" name="action" id="usuarioAction" value="crear">
                <input type="hidden" name="usuario_id" id="usuarioId" value="">
                <input type="hidden" name="imagen_recortada" id="imagen_recortada_usuario" value="">
                <input type="hidden" name="eliminar_foto" id="eliminarFotoUsuarioInput" value="0">
                <div class="modal-body p-4 usuario-modal-body">
                    <div class="form-usuario-status create" id="formUsuarioStatus">
                        <i class="fas fa-user-plus"></i>
                        <span id="formUsuarioStatusText">Creando un nuevo usuario para el sistema</span>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="avatar-preview mb-2" id="avatarPreviewUsuario" onclick="abrirEditorFotoUsuario()" style="width:7.5rem; height:7.5rem;" title="Editar foto de perfil" data-tooltip="Editar foto de perfil" data-tooltip-theme="gradient">
                                    <div id="fotoPlaceholderUsuario" class="avatar-placeholder"><i class="fas fa-camera"></i><small style="font-size:0.6rem; margin-top:0.25rem;">Foto</small></div>
                                    <img id="imagePreviewUsuario" src="" style="display: none; width:100%; height:100%; object-fit: cover;">
                                    <div class="edit-overlay py-1"><i class="fas fa-crop-alt me-1"></i> Editar</div>
                                </div>
                                <div class="d-grid gap-1">
                                    <label class="btn-win btn-win-sm py-1" style="font-size:0.7rem;" title="Subir foto" data-tooltip="Subir foto" data-tooltip-theme="info"><i class="fas fa-upload me-1"></i> Subir Foto<input type="file" id="imageUploadUsuario" accept="image/jpeg,image/png" hidden onchange="cargarImagenUsuario(this)"></label>
                                    <button type="button" class="btn-win btn-win-danger btn-win-sm py-1" id="btnEliminarFotoUsuario" style="display: none;" onclick="window.eliminarFotoUsuario()" title="Eliminar foto" data-tooltip="Eliminar foto" data-tooltip-theme="danger"><i class="fas fa-trash-alt me-1"></i> Eliminar</button>
                                </div>
                                <div class="form-check form-switch d-flex justify-content-center mt-3 mb-1">
                                    <input type="checkbox" class="form-check-input" name="activo" id="activo_usuario" checked>
                                    <label class="form-check-label" for="activo_usuario" id="estadoUsuarioLabel">Usuario Activo</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="section-title"><i class="fas fa-key"></i> Datos de Acceso</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Usuario <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="usuario" id="usuario" required title="Nombre de usuario para acceso" data-tooltip="Nombre de usuario para acceso" data-tooltip-theme="info">
                                    <small class="text-muted" id="usuarioFeedback"></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contraseña <span class="text-danger" id="passRequired">*</span></label>
                                    <div class="password-wrapper">
                                        <input type="password" class="form-control" name="password" id="password" autocomplete="new-password" title="Contraseña de acceso" data-tooltip="Contraseña de acceso" data-tooltip-theme="warning">
                                        <button type="button" class="password-toggle" id="togglePasswordBtn" onclick="togglePassword()" tabindex="-1" title="Mostrar/ocultar contraseña" data-tooltip="Mostrar/ocultar contraseña" data-tooltip-theme="warning"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <small class="text-muted" id="passHelp">Dejar en blanco para mantener la actual</small>
                                </div>
                            </div>
                            <div class="section-title"><i class="fas fa-id-card"></i> Datos Personales</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" class="form-control" name="nombre" id="nombre" required><small class="text-muted" id="nombreFeedback"></small></div>
                                <div class="col-md-4"><label class="form-label">Primer Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="primer_apellido" id="primer_apellido" required><small class="text-muted" id="apellido1Feedback"></small></div>
                                <div class="col-md-4"><label class="form-label">Segundo Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="segundo_apellido" id="segundo_apellido" required><small class="text-muted" id="apellido2Feedback"></small></div>
                                <div class="col-md-6"><label class="form-label">Carnet de Identidad <span class="text-danger">*</span></label><input type="text" class="form-control" name="no_ci" id="no_ci" maxlength="11" required><small class="text-muted" id="ciFeedbackUsuario"></small></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="email"><small class="text-muted" id="emailFeedback"></small></div>
                                <div class="col-md-6"><label class="form-label">Teléfono de Contacto</label><input type="text" class="form-control" name="telefono_contacto" id="telefono_contacto" maxlength="15"><small class="text-muted" id="telefonoFeedback"></small></div>
                                <div class="col-md-6">
                                    <label class="form-label">Rol <span class="text-danger">*</span></label>
                                    <select class="form-select" name="rol_id" id="rol_id" required title="Asignar rol al usuario" data-tooltip="Asignar rol al usuario" data-tooltip-theme="primary">
                                        <option value="">-- Seleccione un rol --</option>
                                        <?php $stmt_roles2 = $pdo->query("SELECT id, descripcion FROM clasif_rol ORDER BY id"); while ($rol2 = $stmt_roles2->fetch()): ?>
                                        <option value="<?php echo $rol2['id']; ?>"><?php echo htmlspecialchars($rol2['descripcion']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-12"><label class="form-label">Dirección Particular</label><textarea class="form-control" name="direccion_particular" id="direccion_particular" rows="2"></textarea></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-win">
                    <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cancelar</button>
                    <button type="submit" class="btn-win btn-win-primary btn-win-sm" id="btnGuardarUsuario" title="Guardar usuario" data-tooltip="Guardar usuario" data-tooltip-theme="success"><i class="fas fa-save me-1"></i> <span id="btnGuardarUsuarioText">Guardar Usuario</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para recortar imagen -->
<div class="modal fade" id="cropModalUsuario" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: rgba(25, 25, 35, 0.98); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255, 255, 255, 0.1); border-radius: 1.25rem;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-crop-alt me-2"></i> Recortar imagen de perfil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()" title="Cerrar recortador" data-tooltip="Cerrar recortador" data-tooltip-theme="danger"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8"><div class="img-container" style="max-height:31.25rem; overflow: hidden; background: #333; border-radius: 0.5rem; padding:0.625rem;"><img id="imageToCropUsuario" src="" style="max-width:100%; display: block;"></div></div>
                    <div class="col-md-4">
                        <div class="text-center mb-3"><h6>Vista previa</h6><div class="preview-container"><canvas id="previewCanvasUsuario" width="150" height="150"></canvas></div></div>
                        <div class="crop-controls p-3">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(-90)" title="Rotar izquierda 90°" data-tooltip="Rotar izquierda 90°" data-tooltip-theme="primary"><i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(90)" title="Rotar derecha 90°" data-tooltip="Rotar derecha 90°" data-tooltip-theme="primary"><i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetearCropUsuario()" title="Resetear recorte" data-tooltip="Resetear recorte" data-tooltip-theme="secondary"><i class="fas fa-sync-alt me-2"></i>Resetear recorte</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(0.1)" title="Acercar" data-tooltip="Acercar" data-tooltip-theme="info"><i class="fas fa-search-plus me-2"></i>Acercar</button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(-0.1)" title="Alejar" data-tooltip="Alejar" data-tooltip-theme="info"><i class="fas fa-search-minus me-2"></i>Alejar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="aplicarRecorteUsuario()" title="Aplicar recorte" data-tooltip="Aplicar recorte" data-tooltip-theme="success"><i class="fas fa-check me-2"></i>Aplicar recorte</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/cropper.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>
<script src="../js/FileSaver.min.js"></script>

<script>
// Clock y Sidebar
const usuarioActualId = <?php echo $usuario_actual_id; ?>;

// Variables globales para impresión y exportación de reportes
var logoBase64 = '<?php echo $logo_base64; ?>';
var nombreEmpresa = '<?php echo addslashes($config_empresa['nombre_empresa'] ?? ''); ?>';
var usuarioNombre = '<?php echo addslashes($user_nombre_completo ?? ''); ?>';
var jefeProyecto = '<?php echo addslashes($config_empresa['jefe_proyecto'] ?? ''); ?>';
var especialistaGestion = '<?php echo addslashes($config_empresa['especialista_gestion'] ?? ''); ?>';
var statsTotal = <?php echo (int)$stats_total; ?>;
var statsActivos = <?php echo (int)$stats_activos; ?>;
var statsInactivos = <?php echo (int)$stats_inactivos; ?>;
var statsSinEdad = <?php echo (int)$stats_sin_edad; ?>;
var statsRolesTodos = <?php echo json_encode($stats_roles_todos, JSON_UNESCAPED_UNICODE); ?>;
var statsEdadRangos = <?php echo json_encode($stats_edad_rangos, JSON_UNESCAPED_UNICODE); ?>;

function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockSpan = document.getElementById('liveClock');
    if (clockSpan) clockSpan.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000); updateClock();

// Gestión de Usuarios
let cropperUsuario = null, previewCanvasUsuario = null, previewCtxUsuario = null;

function validarNombreCampo(valor, feedbackId, minLength = 3) {
    const limpio = valor.trim();
    if (limpio.length === 0) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio').show(); return false; }
    if (limpio.length < minLength) { $(feedbackId).html(`<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo ${minLength} caracteres`).show(); return false; }
    if (!/^[a-zA-ZáéíóúñÑüÜ\s]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Solo letras').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function togglePassword() {
    const pass = document.getElementById('password');
    const btn = document.getElementById('togglePasswordBtn');
    if (pass.type === 'password') {
        pass.type = 'text';
        if (btn) btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        pass.type = 'password';
        if (btn) btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

function validarUsuario(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio').show(); return false; }
    if (limpio.length < 4) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo 4 caracteres').show(); return false; }
    if (!/^[a-zA-Z0-9_.\-@]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Solo letras, números, _ - . @').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarCI(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length !== 11 || !/^\d{11}$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Debe tener 11 dígitos').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarEmail(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Email inválido').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarTelefono(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (!/^[\d\s\-()+]{7,15}$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Teléfono inválido').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarPassword(value, feedbackId) {
    if (value.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (value.length < 6) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo 6 caracteres').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

$(document).ready(function() {
    $('#usuario').on('blur', function() { validarUsuario(this.value, '#usuarioFeedback'); });
    $('#nombre').on('blur', function() { validarNombreCampo(this.value, '#nombreFeedback'); });
    $('#primer_apellido').on('blur', function() { validarNombreCampo(this.value, '#apellido1Feedback'); });
    $('#segundo_apellido').on('blur', function() { validarNombreCampo(this.value, '#apellido2Feedback'); });
    $('#no_ci').on('blur', function() { validarCI(this.value, '#ciFeedbackUsuario'); });
    $('#email').on('blur', function() { validarEmail(this.value, '#emailFeedback'); });
    $('#telefono_contacto').on('blur', function() { validarTelefono(this.value, '#telefonoFeedback'); });
    $('#password').on('blur', function() { validarPassword(this.value, '#passHelp'); });

    // Chevron del select: arriba mientras el dropdown está abierto, abajo al cerrar
    $(document)
        .on('mousedown', '.form-select', function() { $(this).addClass('select-open'); })
        .on('keydown', '.form-select', function(e) {
            if (/^(Enter|Space|ArrowDown|ArrowUp|PageDown|PageUp|Home|End)$/.test(e.key)) $(this).addClass('select-open');
        })
        .on('change blur', '.form-select', function() { $(this).removeClass('select-open'); });
});

function abrirEditorFotoUsuario() {
    const imagePreview = document.getElementById('imagePreviewUsuario');
    const imageUpload = document.getElementById('imageUploadUsuario');

    if (!imageUpload) return;

    if (imagePreview && imagePreview.style.display === 'block' && imagePreview.src && imagePreview.src !== '' && imagePreview.src !== window.location.href) {
        fetch(imagePreview.src, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    fetch(imagePreview.src)
                        .then(res => res.blob())
                        .then(blob => {
                            const file = new File([blob], "foto_actual.jpg", { type: "image/jpeg" });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            imageUpload.files = dataTransfer.files;
                            cargarImagenUsuario(imageUpload);
                        })
                        .catch(() => {
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Foto no disponible',
                                text: 'La foto actual no se puede editar. Puede subir una nueva.',
                                icon: 'warning',
                                confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir Nueva',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            }).then(() => {
                                imageUpload.click();
                            });
                        });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-info-circle text-info me-2"></i> Sin foto',
                        html: 'No hay foto registrada o ha sido eliminada.<br>Puede subir una nueva.',
                        icon: 'info',
                        confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir foto',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    }).then(() => {
                        imageUpload.click();
                    });
                }
            })
            .catch(() => {
                imageUpload.click();
            });
    } else {
        imageUpload.click();
    }
}

function cargarImagenUsuario(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    if (!file.type.match(/^image\/(jpeg|png)$/)) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-image me-2"></i> Error', text: 'Solo se permiten imágenes JPG o PNG', background: 'var(--panel)', color: 'var(--txt)' });
        input.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-image me-2"></i> Error', text: 'La imagen no debe superar 5 MB', background: 'var(--panel)', color: 'var(--txt)' });
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageToCrop = document.getElementById('imageToCropUsuario');
        imageToCrop.src = e.target.result;
        imageToCrop.onload = function() {
            previewCanvasUsuario = document.getElementById('previewCanvasUsuario');
            previewCtxUsuario = previewCanvasUsuario.getContext('2d');
            previewCtxUsuario.fillStyle = '#2d2d2d';
            previewCtxUsuario.fillRect(0, 0, 150, 150);
            if (cropperUsuario) cropperUsuario.destroy();
            cropperUsuario = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 1, cropBoxResizable: true, cropBoxMovable: true, guides: true, center: true, highlight: true, background: true, responsive: true, restore: true, zoomable: true, rotatable: true, scalable: true, wheelZoomRatio: 0.1, minContainerWidth: 500, minContainerHeight: 400, crop: function(event) { actualizarPreviewUsuario(); }, ready: function() { const containerData = cropperUsuario.getContainerData(); const cropBoxSize = Math.min(containerData.width, containerData.height)*0.8; cropperUsuario.setCropBoxData({ width: cropBoxSize, height: cropBoxSize, left: (containerData.width-cropBoxSize)/2, top: (containerData.height-cropBoxSize)/2 }); setTimeout(() => actualizarPreviewUsuario(), 100); } });
            new bootstrap.Modal(document.getElementById('cropModalUsuario')).show();
        };
    };
    reader.readAsDataURL(file);
}

function actualizarPreviewUsuario() {
    if (!cropperUsuario || !previewCtxUsuario) return;
    try {
        const canvas = cropperUsuario.getCroppedCanvas({ width: 150, height: 150 });
        if (canvas) previewCtxUsuario.drawImage(canvas, 0, 0, 150, 150);
    } catch (error) { console.error('Error actualizando preview:', error); }
}

function rotarImagenUsuario(deg) { if(cropperUsuario) cropperUsuario.rotate(deg); }

function resetearCropUsuario() { if(cropperUsuario) { cropperUsuario.reset(); setTimeout(() => actualizarPreviewUsuario(), 50); } }

function zoomImagenUsuario(factor) { if(cropperUsuario) cropperUsuario.zoom(factor); setTimeout(() => actualizarPreviewUsuario(), 50); }

function limpiarCropperUsuario() { if(cropperUsuario){ cropperUsuario.destroy(); cropperUsuario=null; } document.getElementById('imageUploadUsuario').value=''; }

function aplicarRecorteUsuario() {
    if(cropperUsuario) {
        const b64 = cropperUsuario.getCroppedCanvas({width:500,height:500}).toDataURL('image/jpeg',0.9);
        document.getElementById('imagen_recortada_usuario').value = b64;
        
        const imagePreview = document.getElementById('imagePreviewUsuario');
        const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
        const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
        
        if (imagePreview) {
            imagePreview.src = b64;
            imagePreview.style.display = 'block';
            imagePreview.style.objectFit = 'cover';
            imagePreview.style.borderRadius = '50%';
        }
        if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        if (btnEliminarFoto) btnEliminarFoto.style.display = 'inline-flex';
        
        const headerImg = document.getElementById('headerAvatarImg');
        const headerIniciales = document.getElementById('headerAvatarIniciales');
        if (headerImg && headerIniciales) {
            headerImg.src = b64;
            headerImg.style.display = 'block';
            headerIniciales.style.display = 'none';
        }
        
        document.getElementById('eliminarFotoUsuarioInput').value = '0';
        
        bootstrap.Modal.getInstance(document.getElementById('cropModalUsuario')).hide();
        limpiarCropperUsuario();
    }
}

function eliminarFotoUsuario() {
    Swal.fire({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar foto',
        text: '¿Seguro que desea eliminar la foto de este usuario?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            const imagePreview = document.getElementById('imagePreviewUsuario');
            const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
            const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
            const headerImg = document.getElementById('headerAvatarImg');
            const headerIniciales = document.getElementById('headerAvatarIniciales');
            
            if (imagePreview) { imagePreview.style.display = 'none'; imagePreview.src = ''; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
            if (headerImg && headerIniciales) { headerImg.style.display = 'none'; headerImg.src = ''; headerIniciales.style.display = 'flex'; }
            
            document.getElementById('eliminarFotoUsuarioInput').value = '1';
            document.getElementById('imagen_recortada_usuario').value = '';
        }
    });
}

function limpiarFormularioUsuario() {
    document.getElementById('formUsuario').reset();
    document.getElementById('usuarioAction').value = 'crear';
    document.getElementById('usuarioId').value = '';
    document.getElementById('imagen_recortada_usuario').value = '';
    document.getElementById('eliminarFotoUsuarioInput').value = '0';
    
    $('#usuarioFeedback, #nombreFeedback, #apellido1Feedback, #apellido2Feedback, #ciFeedbackUsuario, #emailFeedback, #telefonoFeedback, #passHelp').html('').hide();
    
    const title = document.getElementById('modalUsuarioTitle');
    const subtitle = document.getElementById('modalUsuarioSubtitle');
    const badge = document.getElementById('modalModeBadge');
    const status = document.getElementById('formUsuarioStatus');
    const statusText = document.getElementById('formUsuarioStatusText');
    const btnText = document.getElementById('btnGuardarUsuarioText');
    const passRequired = document.getElementById('passRequired');
    
    if (title) title.innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Usuario';
    if (subtitle) subtitle.textContent = 'Registre un nuevo usuario del sistema';
    if (badge) { badge.className = 'mode-badge create'; badge.innerHTML = '<i class="fas fa-plus-circle"></i> Crear'; }
    if (status) { status.className = 'form-usuario-status create'; status.querySelector('i').className = 'fas fa-user-plus'; }
    if (statusText) statusText.textContent = 'Creando un nuevo usuario para el sistema';
    if (btnText) btnText.textContent = 'Guardar Usuario';
    if (passRequired) passRequired.style.display = '';
    
    const passHelp = document.getElementById('passHelp');
    if (passHelp) passHelp.textContent = 'Dejar en blanco para mantener la actual';
    const pass = document.getElementById('password');
    if (pass) { pass.value = ''; pass.type = 'password'; }
    const toggleBtn = document.getElementById('togglePasswordBtn');
    if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    
    const headerImg = document.getElementById('headerAvatarImg');
    const headerIniciales = document.getElementById('headerAvatarIniciales');
    if (headerImg && headerIniciales) { headerImg.style.display = 'none'; headerImg.src = ''; headerIniciales.style.display = 'flex'; headerIniciales.textContent = '+'; }
    
    const imagePreview = document.getElementById('imagePreviewUsuario');
    const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
    const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
    if (imagePreview) { imagePreview.style.display = 'none'; imagePreview.src = ''; }
    if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
    if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
    
    document.getElementById('rol_id').value = '';
    document.getElementById('rol_id').disabled = false;
    document.getElementById('activo_usuario').checked = true;
    document.getElementById('activo_usuario').disabled = false;
    document.getElementById('activo_usuario').removeAttribute('title');
    const estadoLabel = document.getElementById('estadoUsuarioLabel');
    if (estadoLabel) estadoLabel.textContent = 'Usuario Activo';
}

function editarUsuario(id) {
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });
    fetch('../ajax/get_usuario.php?id=' + id)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message || 'No se pudo cargar el usuario', background: 'var(--panel)', color: 'var(--txt)' });
            return;
        }
        const u = data.usuario;
        
        document.getElementById('formUsuario').reset();
        document.getElementById('usuarioAction').value = 'editar';
        document.getElementById('usuarioId').value = u.id;
        document.getElementById('imagen_recortada_usuario').value = '';
        document.getElementById('eliminarFotoUsuarioInput').value = '0';
        
        document.getElementById('usuario').value = u.usuario || '';
        document.getElementById('password').value = '';
        document.getElementById('nombre').value = u.nombre || '';
        
        const apellidos = (u.apellidos || '').split(' ');
        document.getElementById('primer_apellido').value = apellidos[0] || '';
        document.getElementById('segundo_apellido').value = apellidos.slice(1).join(' ') || '';
        
        document.getElementById('no_ci').value = u.no_ci || '';
        document.getElementById('email').value = u.email || '';
        document.getElementById('telefono_contacto').value = u.telefono_contacto || '';
        document.getElementById('direccion_particular').value = u.direccion_particular || '';
        document.getElementById('rol_id').value = u.rol_id || '';
        document.getElementById('activo_usuario').checked = u.activo == 1;

        // El propio usuario logueado no puede desactivarse a sí mismo, aunque sea admin
        const activoChkUsuario = document.getElementById('activo_usuario');
        const esPropioUsuario = u.id == usuarioActualId;
        activoChkUsuario.disabled = esPropioUsuario;
        if (esPropioUsuario) {
            activoChkUsuario.setAttribute('title', 'No puede desactivar su propia cuenta');
        } else {
            activoChkUsuario.removeAttribute('title');
        }
        
        $('#usuarioFeedback, #nombreFeedback, #apellido1Feedback, #apellido2Feedback, #ciFeedbackUsuario, #emailFeedback, #telefonoFeedback, #passHelp').html('').hide();
        
        const title = document.getElementById('modalUsuarioTitle');
        const subtitle = document.getElementById('modalUsuarioSubtitle');
        const badge = document.getElementById('modalModeBadge');
        const status = document.getElementById('formUsuarioStatus');
        const statusText = document.getElementById('formUsuarioStatusText');
        const btnText = document.getElementById('btnGuardarUsuarioText');
        const passRequired = document.getElementById('passRequired');
        
        const nombreCompleto = (u.nombre || '') + ' ' + (u.apellidos || '');
        if (title) title.innerHTML = '<i class="fas fa-user-edit me-2"></i> Editar Usuario';
        if (subtitle) subtitle.textContent = nombreCompleto;
        if (badge) { badge.className = 'mode-badge edit'; badge.innerHTML = '<i class="fas fa-edit"></i> Editar'; }
        if (status) { status.className = 'form-usuario-status edit'; status.querySelector('i').className = 'fas fa-user-edit'; }
        if (statusText) statusText.textContent = 'Editando el usuario ' + (u.usuario || '');
        if (btnText) btnText.textContent = 'Guardar Cambios';
        if (passRequired) passRequired.style.display = 'none';
        
        const passHelp = document.getElementById('passHelp');
        if (passHelp) passHelp.textContent = 'Dejar en blanco para mantener la contraseña actual';
        const toggleBtn = document.getElementById('togglePasswordBtn');
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        const pass = document.getElementById('password');
        if (pass) pass.type = 'password';
        
        const headerImg = document.getElementById('headerAvatarImg');
        const headerIniciales = document.getElementById('headerAvatarIniciales');
        const imagePreview = document.getElementById('imagePreviewUsuario');
        const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
        const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
        
        let foto_url = u.foto || '';
        let foto_existe = false;
        if (foto_url) {
            if (strpos_eq(foto_url, 'assets/')) foto_url = '../' + foto_url;
            foto_existe = true;
        }
        
        if (foto_existe && foto_url) {
            if (headerImg && headerIniciales) { headerImg.src = foto_url; headerImg.style.display = 'block'; headerIniciales.style.display = 'none'; }
            if (imagePreview) { imagePreview.src = foto_url; imagePreview.style.display = 'block'; imagePreview.style.objectFit = 'cover'; imagePreview.style.borderRadius = '50%'; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'inline-flex';
        } else {
            const iniciales = ((u.nombre || '')[0] || '') + ((u.apellidos || '')[0] || '');
            if (headerImg && headerIniciales) { headerImg.style.display = 'none'; headerImg.src = ''; headerIniciales.style.display = 'flex'; headerIniciales.textContent = iniciales.toUpperCase(); }
            if (imagePreview) { imagePreview.style.display = 'none'; imagePreview.src = ''; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
        }
        
        const estadoLabel = document.getElementById('estadoUsuarioLabel');
        if (estadoLabel) estadoLabel.textContent = u.activo == 1 ? 'Usuario Activo' : 'Usuario Inactivo';
        
        new bootstrap.Modal(document.getElementById('modalUsuario')).show();
    })
    .catch(() => {
        Swal.close();
        Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión al cargar el usuario', background: 'var(--panel)', color: 'var(--txt)' });
    });
}

function strpos_eq(haystack, needle) { return haystack.indexOf(needle) === 0; }

function toggleEstadoUsuario(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual ? 0 : 1;
    if (id === usuarioActualId && nuevoEstado === 0) {
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-ban me-2" style="color: #dc3545;"></i> No puede desactivarse a sí mismo',
            text: 'Usted es el usuario que está logueado actualmente. Para desactivar su cuenta solicite a otro administrador.',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
            confirmButtonColor: '#dc3545',
            background: '#1a1a2e',
            color: '#fff'
        });
        return;
    }
    Swal.fire({
        title: (estadoActual ? '<i class="fas fa-user-slash me-2" style="color: #f59e0b;"></i> Desactivar usuario' : '<i class="fas fa-user-check me-2" style="color: 1;"></i> Activar usuario'),
        text: '¿Seguro que desea ' + (estadoActual ? 'desactivar' : 'activar') + ' a "' + nombre + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: estadoActual ? '#f59e0b' : '#10b981',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Sí, ' + (estadoActual ? 'desactivar' : 'activar'),
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('estado', nuevoEstado);
            fetch('../ajax/toggle_usuario.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                Swal.fire({ icon: data.success ? 'success' : 'error', title: '<i class="fas fa-check-circle me-2"></i> ' + (data.success ? 'Completado' : 'Error'), text: data.message, timer: data.success ? 1500 : 0, showConfirmButton: !data.success, background: 'var(--panel)', color: 'var(--txt)' });
                if (data.success) setTimeout(() => location.reload(), 1500);
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión', background: 'var(--panel)', color: 'var(--txt)' });
            });
        }
    });
}

function eliminarUsuario(id, nombre) {
    Swal.fire({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar usuario',
        html: '¿Seguro que desea eliminar a <strong>' + nombre + '</strong>?<br><small>Esta acción no se puede deshacer.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('../ajax/eliminar_usuario.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                Swal.fire({ icon: data.success ? 'success' : 'error', title: '<i class="fas fa-check-circle me-2"></i> ' + (data.success ? 'Completado' : 'Error'), text: data.message, confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido', showConfirmButton: !data.success, timer: data.success ? 1500 : 0, background: 'var(--panel)', color: 'var(--txt)' });
                if (data.success) setTimeout(() => location.reload(), 1500);
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión', background: 'var(--panel)', color: 'var(--txt)' });
            });
        }
    });
}

function filtrarUsuarios() {
    const texto = (document.getElementById('searchUsuarioInput')?.value || '').toLowerCase().trim();
    const rol = document.getElementById('filterRolInput')?.value || '';
    const estado = document.getElementById('filterEstadoInput')?.value || '';
    
    const filas = document.querySelectorAll('#tablaUsuarios tbody tr');
    filas.forEach(fila => {
        const filaTexto = fila.textContent.toLowerCase();
        const filaRol = fila.getAttribute('data-rol') || '';
        const filaEstado = fila.getAttribute('data-estado') || '';
        
        let visible = true;
        if (texto && filaTexto.indexOf(texto) === -1) visible = false;
        if (rol && filaRol !== rol) visible = false;
        if (estado !== '' && filaEstado !== estado) visible = false;
        
        fila.style.display = visible ? '' : 'none';
    });
    
    actualizarEstadisticasUsuarios();
}

function actualizarEstadisticasUsuarios() {
    const filasVisibles = Array.from(document.querySelectorAll('#tablaUsuarios tbody tr')).filter(f => f.style.display !== 'none');
    const total = filasVisibles.length;
    const activos = filasVisibles.filter(f => f.getAttribute('data-estado') === '1').length;
    const roles = new Set(filasVisibles.map(f => f.getAttribute('data-rol') || '').filter(r => r !== ''));
    
    const totalEl = document.getElementById('totalUsuariosCount');
    const activosEl = document.getElementById('usuariosActivosCount');
    const rolesEl = document.getElementById('rolesCount');
    if (totalEl) totalEl.textContent = total;
    if (activosEl) activosEl.textContent = activos;
    if (rolesEl) rolesEl.textContent = roles.size;
    const inactivosEl = document.getElementById('usuariosInactivosCount');
    if (inactivosEl) inactivosEl.textContent = total - activos;
}

document.getElementById('formUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const action = document.getElementById('usuarioAction').value;
    const btnGuardar = document.getElementById('btnGuardarUsuario');
    const btnTexto = document.getElementById('btnGuardarUsuarioText');
    
    const okUsuario = validarUsuario(document.getElementById('usuario').value, '#usuarioFeedback');
    const okNombre = validarNombreCampo(document.getElementById('nombre').value, '#nombreFeedback');
    const okAp1 = validarNombreCampo(document.getElementById('primer_apellido').value, '#apellido1Feedback');
    const okAp2 = validarNombreCampo(document.getElementById('segundo_apellido').value, '#apellido2Feedback');
    const okCI = validarCI(document.getElementById('no_ci').value, '#ciFeedbackUsuario');
    const okEmail = validarEmail(document.getElementById('email').value, '#emailFeedback');
    const okTel = validarTelefono(document.getElementById('telefono_contacto').value, '#telefonoFeedback');
    
    let okPassword = true;
    const passwordVal = document.getElementById('password').value;
    if (action === 'crear') {
        if (!passwordVal) { $('#passHelp').html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio para nuevos usuarios').show(); okPassword = false; }
        else okPassword = validarPassword(passwordVal, '#passHelp');
    } else if (passwordVal) {
        okPassword = validarPassword(passwordVal, '#passHelp');
    }
    
    const rolVal = document.getElementById('rol_id').value;
    if (!rolVal) { Swal.fire({ icon: 'warning', title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Seleccione un rol', text: 'Debe seleccionar el rol del usuario', background: 'var(--panel)', color: 'var(--txt)' }); return; }
    
    if (!okUsuario || !okNombre || !okAp1 || !okAp2 || !okCI || !okEmail || !okTel || !okPassword) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Campos inválidos', text: 'Revise los campos marcados en rojo', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }
    
    btnGuardar.classList.add('btn-guardar-loading');
    btnGuardar.disabled = true;
    if (btnTexto) btnTexto.textContent = action === 'crear' ? 'Creando...' : 'Guardando...';
    
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });
    const fdUsuario = new FormData(this);
    // Si el select/checkbox están deshabilitados (sin permiso o propio perfil) no viajan en FormData
    const rolSel = document.getElementById('rol_id');
    const actChk = document.getElementById('activo_usuario');
    if (rolSel.disabled) fdUsuario.append('rol_id', rolSel.value);
    if (actChk.disabled) fdUsuario.append('activo', actChk.checked ? '1' : '0');
    fetch('../ajax/guardar_usuario.php', { method: 'POST', body: fdUsuario })
    .then(response => response.json())
    .then(data => {
        if (data.success) { Swal.fire({ icon: 'success', title: '<i class="fas fa-check-circle me-2"></i> Completado', text: data.message, timer: 1500, showConfirmButton: false, background: 'var(--panel)', color: 'var(--txt)' }); setTimeout(() => location.reload(), 1500); }
        else {
            Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message, background: 'var(--panel)', color: 'var(--txt)' });
            btnGuardar.classList.remove('btn-guardar-loading');
            btnGuardar.disabled = false;
            if (btnTexto) btnTexto.textContent = action === 'crear' ? 'Guardar Usuario' : 'Guardar Cambios';
            $('#btnGuardarUsuario i').attr('class', 'fas fa-save me-1');
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión', background: 'var(--panel)', color: 'var(--txt)' });
        btnGuardar.classList.remove('btn-guardar-loading');
        btnGuardar.disabled = false;
        if (btnTexto) btnTexto.textContent = action === 'crear' ? 'Guardar Usuario' : 'Guardar Cambios';
        $('#btnGuardarUsuario i').attr('class', 'fas fa-save me-1');
    });
});

document.getElementById('searchUsuarioInput')?.addEventListener('input', filtrarUsuarios);
document.getElementById('filterRolInput')?.addEventListener('change', filtrarUsuarios);
document.getElementById('filterEstadoInput')?.addEventListener('change', filtrarUsuarios);

// ==========================================
// IMPRESIÓN Y EXPORTACIÓN DE USUARIOS
// ==========================================
var PRINT_TOOLBAR_USUARIOS = '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;"><span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span><button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">🖨️ Imprimir</button><button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">✖ Cerrar</button></div>'
    + '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style>'
    + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';

var ESTILOS_REPORTE_USUARIOS = '.tabla-usuarios { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }'
    + '.tabla-usuarios th, .tabla-usuarios td { border: 0.0625rem solid #333333; padding: 0.3125rem 0.4375rem; font-size: 8.5pt; text-align: left; vertical-align: middle; }'
    + '.tabla-usuarios th { background-color: #1e3a8a; color: #ffffff; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.0312rem; }'
    + '.tabla-usuarios tbody tr:nth-child(even) { background-color: #f1f5f9; }'
    + '.seccion-titulo { font-size: 10pt; font-weight: bold; color: #1e3a8a; border-bottom: 0.0625rem solid #cbd5e1; margin: 0.875rem 0 0.5rem 0; padding-bottom: 0.1875rem; }';

function escapeHtmlExport(valor) {
    return String(valor === null || valor === undefined ? '' : valor)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function obtenerFechaHoraExport() {
    const d = new Date();
    return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES');
}

function nombreArchivoUsuarios(ext) {
    const d = new Date();
    const ts = d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0')
        + '_' + String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0');
    return 'Reporte_Usuarios_' + ts + '.' + ext;
}

function descargarBlob(blob, nombre) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
}

// Lee las columnas Usuario..Estado (omite Avatar y Acciones) respetando los filtros aplicados
function obtenerDatosUsuariosTabla() {
    const headers = [];
    document.querySelectorAll('#tablaUsuarios thead th').forEach((th, idx) => {
        if (idx >= 1 && idx <= 6) headers.push(th.textContent.trim());
    });
    const rows = [];
    document.querySelectorAll('#tablaUsuarios tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return;
        const celdas = tr.querySelectorAll('td');
        if (celdas.length < 7) return;
        const fila = [];
        for (let i = 1; i <= 6; i++) fila.push((celdas[i].textContent || '').replace(/\s+/g, ' ').trim());
        rows.push(fila);
    });
    return { headers: headers, rows: rows };
}

function validarDatosExportacion(datos) {
    if (!datos.rows.length) {
        Swal.fire({ icon: 'warning', title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Sin datos', text: 'No hay usuarios visibles para exportar.', background: 'var(--panel)', color: 'var(--txt)' });
        return false;
    }
    return true;
}

function construirEncabezadoReporteHtml(titulo) {
    return '<table style="width:100%;border:none;border-bottom:0.125rem solid #1e3a8a;padding-bottom:0.625rem;margin-bottom:0.625rem;">'
        + '<tr>'
        + '<td style="border:none;width:5rem;text-align:center;vertical-align:middle;">' + (logoBase64 ? '<img src="' + logoBase64 + '" alt="Logo" style="width:4.375rem;height:auto;">' : '') + '</td>'
        + '<td style="border:none;text-align:center;vertical-align:middle;">'
        + '<h2 style="margin:0;font-size:15pt;color:#1e3a8a;letter-spacing:0.0625rem;">' + escapeHtmlExport(titulo) + '</h2>'
        + '<div style="font-size:11pt;font-weight:bold;margin-top:0.1875rem;">' + escapeHtmlExport(nombreEmpresa) + '</div>'
        + '</td>'
        + '<td style="border:none;width:11.875rem;text-align:right;vertical-align:middle;font-size:8pt;color:#374151;">'
        + 'Fecha: ' + obtenerFechaHoraExport() + '<br>Generado por: ' + escapeHtmlExport(usuarioNombre)
        + '</td>'
        + '</tr>'
        + '</table>';
}

function construirResumenEstadisticoHtml(compacto) {
    const fs = compacto ? '8pt' : '9pt';
    let rolesHtml = '';
    statsRolesTodos.forEach(r => {
        rolesHtml += '<span style="display:inline-block;background:#eef2ff;border:0.0625rem solid #c7d2fe;border-radius:0.25rem;padding:0.125rem 0.5rem;margin:0.125rem 0.1875rem;font-size:' + fs + ';color:#1e3a8a;">' + escapeHtmlExport(r.descripcion) + ': <b>' + r.total + '</b></span>';
    });
    let edadesHtml = '';
    Object.keys(statsEdadRangos).forEach(r => {
        edadesHtml += '<span style="display:inline-block;background:#f0fdf4;border:0.0625rem solid #bbf7d0;border-radius:0.25rem;padding:0.125rem 0.5rem;margin:0.125rem 0.1875rem;font-size:' + fs + ';color:#166534;">' + escapeHtmlExport(r) + ' años: <b>' + statsEdadRangos[r] + '</b></span>';
    });
    if (statsSinEdad > 0) {
        edadesHtml += '<span style="display:inline-block;background:#fef2f2;border:0.0625rem solid #fecaca;border-radius:0.25rem;padding:0.125rem 0.5rem;margin:0.125rem 0.1875rem;font-size:' + fs + ';color:#991b1b;">Sin CI: <b>' + statsSinEdad + '</b></span>';
    }
    return '<table style="width:100%;border-collapse:separate;border-spacing:0.375rem;margin-bottom:0.5rem;">'
        + '<tr>'
        + '<td style="background:#eff6ff;border:0.0625rem solid #bfdbfe;border-radius:0.375rem;padding:0.5rem 0.875rem;text-align:center;width:33%;"><div style="font-size:8pt;color:#374151;text-transform:uppercase;">Total de Usuarios</div><div style="font-size:16pt;font-weight:bold;color:#1e3a8a;">' + statsTotal + '</div></td>'
        + '<td style="background:#f0fdf4;border:0.0625rem solid #bbf7d0;border-radius:0.375rem;padding:0.5rem 0.875rem;text-align:center;width:33%;"><div style="font-size:8pt;color:#374151;text-transform:uppercase;">Activos</div><div style="font-size:16pt;font-weight:bold;color:#166534;">' + statsActivos + '</div></td>'
        + '<td style="background:#fef2f2;border:0.0625rem solid #fecaca;border-radius:0.375rem;padding:0.5rem 0.875rem;text-align:center;width:33%;"><div style="font-size:8pt;color:#374151;text-transform:uppercase;">Inactivos</div><div style="font-size:16pt;font-weight:bold;color:#991b1b;">' + statsInactivos + '</div></td>'
        + '</tr>'
        + '</table>'
        + '<div style="margin-bottom:0.25rem;"><b>Distribución por Roles:</b></div><div style="margin-bottom:0.5rem;">' + (rolesHtml || '-') + '</div>'
        + '<div style="margin-bottom:0.25rem;"><b>Rangos de Edad:</b></div><div>' + (edadesHtml || '-') + '</div>';
}

function construirTablaUsuariosHtml() {
    const datos = obtenerDatosUsuariosTabla();
    let html = '<table class="tabla-usuarios"><thead><tr>';
    datos.headers.forEach(h => { html += '<th>' + escapeHtmlExport(h) + '</th>'; });
    html += '</tr></thead><tbody>';
    datos.rows.forEach(fila => {
        html += '<tr>';
        fila.forEach(c => { html += '<td>' + escapeHtmlExport(c) + '</td>'; });
        html += '</tr>';
    });
    html += '</tbody></table>';
    return html;
}

function construirFirmasHtml() {
    return '<table style="width:100%;border:none;margin-top:3.125rem;">'
        + '<tr>'
        + '<td style="border:none;width:33%;text-align:center;vertical-align:bottom;padding-top:2.1875rem;"><div style="border-top:0.0625rem solid #000000;width:85%;margin:0 auto 0.375rem auto;"></div><b>Elaborado por:</b><br>' + escapeHtmlExport(usuarioNombre) + '</td>'
        + '<td style="border:none;width:33%;text-align:center;vertical-align:bottom;padding-top:2.1875rem;"><div style="border-top:0.0625rem solid #000000;width:85%;margin:0 auto 0.375rem auto;"></div><b>Revisado por:</b><br>' + escapeHtmlExport(jefeProyecto) + '</td>'
        + '<td style="border:none;width:33%;text-align:center;vertical-align:bottom;padding-top:2.1875rem;"><div style="border-top:0.0625rem solid #000000;width:85%;margin:0 auto 0.375rem auto;"></div><b>Aprobado por:</b><br>' + escapeHtmlExport(especialistaGestion) + '</td>'
        + '</tr>'
        + '</table>';
}

function imprimirUsuarios() {
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;
    const html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de Usuarios - ' + escapeHtmlExport(nombreEmpresa) + '</title><style>'
        + '* { margin:0; padding:0; box-sizing: border-box; }'
        + 'body { font-family: Arial, Helvetica, sans-serif; color: #000000; background: #ffffff; padding:4.375rem 1.875rem 1.875rem 1.875rem; font-size:9pt; }'
        + '@page { margin:10mm; }'
        + '@media print { .no-print { display: none !important; } body { padding-top:0.9375rem; } }'
        + ESTILOS_REPORTE_USUARIOS
        + '</style></head><body>'
        + PRINT_TOOLBAR_USUARIOS
        + construirEncabezadoReporteHtml('REPORTE DE USUARIOS')
        + '<div class="seccion-titulo">Resumen Estadístico</div>'
        + construirResumenEstadisticoHtml(true)
        + '<div class="seccion-titulo">Listado de Usuarios (' + datos.rows.length + ')</div>'
        + construirTablaUsuariosHtml()
        + construirFirmasHtml()
        + '</body></html>';
    const win = window.open('', '_blank', 'width=1150,height=850');
    if (!win) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-window-restore me-2"></i> Ventana bloqueada', text: 'El navegador bloqueó la ventana emergente. Permita las ventanas emergentes para imprimir.', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
}

function exportarUsuariosPDF() {
    if (typeof window.jspdf === 'undefined') {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-file-pdf me-2" style="color: #dc3545;"></i> Error', text: 'La librería de PDF no cargó correctamente.', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
        const anchoPagina = doc.internal.pageSize.getWidth();

        if (logoBase64) { try { doc.addImage(logoBase64, 'PNG', 40, 28, 55, 55); } catch (errLogo) {} }
        doc.setFont('helvetica', 'bold'); doc.setFontSize(15);
        doc.text('REPORTE DE USUARIOS', anchoPagina / 2, 45, { align: 'center' });
        doc.setFontSize(11);
        doc.text(nombreEmpresa, anchoPagina / 2, 62, { align: 'center' });
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8);
        doc.text('Generado por: ' + usuarioNombre + '    |    Fecha: ' + obtenerFechaHoraExport(), anchoPagina / 2, 76, { align: 'center' });

        doc.autoTable({
            startY: 90,
            head: [['Total de Usuarios', 'Activos', 'Inactivos']],
            body: [[String(statsTotal), String(statsActivos), String(statsInactivos)]],
            theme: 'grid',
            headStyles: { fillColor: [30, 58, 138], textColor: 255, halign: 'center', fontSize: 8 },
            bodyStyles: { halign: 'center', fontSize: 10, fontStyle: 'bold' },
            margin: { left: 40, right: 40 },
            tableWidth: anchoPagina - 80
        });

        const rolesBody = statsRolesTodos.map(r => [r.descripcion, String(r.total)]);
        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 10,
            head: [['Distribución por Roles', 'Cantidad']],
            body: rolesBody.length ? rolesBody : [['Sin roles registrados', '0']],
            theme: 'grid',
            headStyles: { fillColor: [76, 29, 149], textColor: 255, halign: 'left', fontSize: 8 },
            bodyStyles: { fontSize: 8 },
            columnStyles: { 1: { halign: 'center', cellWidth: 90 } },
            margin: { left: 40, right: 40 },
            tableWidth: anchoPagina - 80
        });

        const edadesBody = Object.keys(statsEdadRangos).map(r => [r + ' años', String(statsEdadRangos[r])]);
        if (statsSinEdad > 0) edadesBody.push(['Sin CI válido', String(statsSinEdad)]);
        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 10,
            head: [['Rangos de Edad', 'Cantidad']],
            body: edadesBody.length ? edadesBody : [['Sin datos de edad', '0']],
            theme: 'grid',
            headStyles: { fillColor: [21, 128, 61], textColor: 255, halign: 'left', fontSize: 8 },
            bodyStyles: { fontSize: 8 },
            columnStyles: { 1: { halign: 'center', cellWidth: 90 } },
            margin: { left: 40, right: 40 },
            tableWidth: anchoPagina - 80
        });

        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 12,
            head: [datos.headers],
            body: datos.rows,
            theme: 'striped',
            headStyles: { fillColor: [30, 58, 138], textColor: 255, fontSize: 7.5 },
            styles: { fontSize: 7.5, cellPadding: 3, overflow: 'linebreak' },
            alternateRowStyles: { fillColor: [241, 245, 249] },
            margin: { left: 40, right: 40 }
        });

        let yFirma = doc.lastAutoTable.finalY + 45;
        if (yFirma > doc.internal.pageSize.getHeight() - 70) { doc.addPage(); yFirma = 70; }
        const firmas = [
            ['Elaborado por:', usuarioNombre],
            ['Revisado por:', jefeProyecto],
            ['Aprobado por:', especialistaGestion]
        ];
        const zonaAncho = (anchoPagina - 80) / 3;
        doc.setFontSize(8);
        firmas.forEach((f, i) => {
            const centro = 40 + zonaAncho * i + zonaAncho / 2;
            doc.setLineWidth(0.7);
            doc.line(centro - zonaAncho * 0.38, yFirma, centro + zonaAncho * 0.38, yFirma);
            doc.setFont('helvetica', 'bold');
            doc.text(f[0], centro, yFirma + 12, { align: 'center' });
            doc.setFont('helvetica', 'normal');
            doc.text(f[1], centro, yFirma + 24, { align: 'center' });
        });

        doc.save(nombreArchivoUsuarios('pdf'));
        Swal.close();
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: 'No se pudo generar el PDF: ' + err.message, background: 'var(--panel)', color: 'var(--txt)' });
    }
}

function exportarUsuariosExcel() {
    if (typeof XLSX === 'undefined') {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-file-excel me-2" style="color: #21a366;"></i> Error', text: 'La librería de Excel no cargó correctamente.', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;

    const wb = XLSX.utils.book_new();

    const wsUsuarios = XLSX.utils.aoa_to_sheet([
        [nombreEmpresa],
        ['REPORTE DE USUARIOS'],
        ['Generado por: ' + usuarioNombre, '', 'Fecha: ' + obtenerFechaHoraExport()],
        [],
        datos.headers,
        ...datos.rows
    ]);
    wsUsuarios['!cols'] = [{ wch: 18 }, { wch: 32 }, { wch: 14 }, { wch: 26 }, { wch: 34 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, wsUsuarios, 'Usuarios');

    const wsStatsData = [
        ['ESTADÍSTICAS DE USUARIOS'],
        [],
        ['Indicador', 'Valor'],
        ['Total de usuarios', statsTotal],
        ['Usuarios activos', statsActivos],
        ['Usuarios inactivos', statsInactivos],
        [],
        ['Distribución por Roles', 'Cantidad']
    ];
    statsRolesTodos.forEach(r => wsStatsData.push([r.descripcion, r.total]));
    wsStatsData.push([]);
    wsStatsData.push(['Rangos de Edad', 'Cantidad']);
    Object.keys(statsEdadRangos).forEach(r => wsStatsData.push([r + ' años', statsEdadRangos[r]]));
    if (statsSinEdad > 0) wsStatsData.push(['Sin CI válido', statsSinEdad]);
    const wsStats = XLSX.utils.aoa_to_sheet(wsStatsData);
    wsStats['!cols'] = [{ wch: 32 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, wsStats, 'Estadísticas');

    XLSX.writeFile(wb, nombreArchivoUsuarios('xlsx'));
}

function exportarUsuariosWord() {
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;
    const html = '<!DOCTYPE html><html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" lang="es"><head><meta charset="UTF-8"><title>Reporte de Usuarios</title>'
        + '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View></w:WordDocument></xml><![endif]-->'
        + '<style>@page { size: A4 portrait; margin:2cm; } body { font-family: Arial, Helvetica, sans-serif; font-size:9pt; color: #000000; background: #ffffff; }'
        + ESTILOS_REPORTE_USUARIOS
        + '</style></head><body>'
        + construirEncabezadoReporteHtml('REPORTE DE USUARIOS')
        + '<div class="seccion-titulo">Resumen Estadístico</div>'
        + construirResumenEstadisticoHtml(false)
        + '<div class="seccion-titulo">Listado de Usuarios (' + datos.rows.length + ')</div>'
        + construirTablaUsuariosHtml()
        + construirFirmasHtml()
        + '</body></html>';
    const blob = new Blob(['\ufeff' + html], { type: 'application/msword;charset=utf-8' });
    if (typeof saveAs === 'function') saveAs(blob, nombreArchivoUsuarios('doc'));
    else descargarBlob(blob, nombreArchivoUsuarios('doc'));
}

function exportarUsuariosCSV() {
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;
    const escaparCsv = v => {
        const s = String(v === null || v === undefined ? '' : v);
        return /[;"\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };
    const csv = [datos.headers, ...datos.rows].map(f => f.map(escaparCsv).join(';')).join('\r\n');
    descargarBlob(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }), nombreArchivoUsuarios('csv'));
}

function exportarUsuariosTXT() {
    const datos = obtenerDatosUsuariosTabla();
    if (!validarDatosExportacion(datos)) return;
    const lineas = [];
    lineas.push('='.repeat(100));
    lineas.push('REPORTE DE USUARIOS - ' + nombreEmpresa.toUpperCase());
    lineas.push('Generado por: ' + usuarioNombre);
    lineas.push('Fecha: ' + obtenerFechaHoraExport());
    lineas.push('');
    lineas.push('RESUMEN ESTADISTICO');
    lineas.push('-'.repeat(100));
    lineas.push('Total de usuarios: ' + statsTotal + '   |   Activos: ' + statsActivos + '   |   Inactivos: ' + statsInactivos);
    lineas.push('Roles: ' + (statsRolesTodos.map(r => r.descripcion + ' (' + r.total + ')').join(', ') || '-'));
    lineas.push('Rangos de edad: ' + (Object.keys(statsEdadRangos).map(r => r + ' (' + statsEdadRangos[r] + ')').join(', ') || '-'));
    if (statsSinEdad > 0) lineas.push('Sin CI valido: ' + statsSinEdad);
    lineas.push('');
    lineas.push('LISTADO DE USUARIOS (' + datos.rows.length + ')');
    lineas.push('-'.repeat(100));
    const anchos = datos.headers.map((h, i) => Math.max(h.length, ...datos.rows.map(r => (r[i] || '').length)));
    const fmtFila = fila => fila.map((c, i) => String(c === null || c === undefined ? '' : c).padEnd(anchos[i])).join('  ');
    lineas.push(fmtFila(datos.headers));
    lineas.push('-'.repeat(100));
    datos.rows.forEach(r => lineas.push(fmtFila(r)));
    lineas.push('-'.repeat(100));
    lineas.push('Total de registros: ' + datos.rows.length);
    lineas.push('='.repeat(100));
    descargarBlob(new Blob(['\ufeff' + lineas.join('\r\n')], { type: 'text/plain;charset=utf-8' }), nombreArchivoUsuarios('txt'));
}

// Abrir el modal de edición directamente si se recibe ?editar=ID
const editarUsuarioId = <?php echo $editar_usuario_id; ?>;
if (editarUsuarioId > 0) {
    $(function() {
        setTimeout(() => editarUsuario(editarUsuarioId), 300);
    });
}
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
