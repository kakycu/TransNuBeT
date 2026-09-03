<?php
// modules/snc225.php - Tarjeta SNC-225
require_once '../config/database.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

setlocale(LC_TIME, 'es_ES.utf8', 'spanish');

$es_admin = permiso_puede('empleados', 'ver');

$stmt = $pdo->prepare("
    SELECT 
        t.id, 
        t.codigo, 
        t.ci, 
        t.nombre_completo, 
        t.fecha_alta, 
        t.fecha_baja,
        t.activo,
        t.centro_costo_id,
        t.area_id,
        cc.codigo AS c_costo, 
        cc.descripcion AS c_costo_desc, 
        cc.nombre AS c_costo_nombre,
        a.nombre_area AS area_nombre,
        COALESCE(
            (SELECT SUM(n.total_salario_devengado) 
             FROM nominas n 
             WHERE n.trabajador_id = t.id), 
            0
        ) AS total_devengado
    FROM trabajadores t
    LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
    LEFT JOIN areas a ON t.area_id = a.id
    ORDER BY t.codigo ASC
");
$stmt->execute();
$trabajadores = $stmt->fetchAll();

$stAlta = $pdo->query("SELECT DISTINCT YEAR(fecha_alta) AS y FROM trabajadores WHERE fecha_alta IS NOT NULL AND fecha_alta != '0000-00-00' ORDER BY y");
$aniosAlta = $stAlta->fetchAll(PDO::FETCH_COLUMN);

$stAltaMes = $pdo->query("SELECT YEAR(fecha_alta) AS y, MONTH(fecha_alta) AS m, COUNT(*) AS cnt FROM trabajadores WHERE fecha_alta IS NOT NULL AND fecha_alta != '0000-00-00' GROUP BY y, m ORDER BY y, m");
$mesesAltaRaw = $stAltaMes->fetchAll(PDO::FETCH_ASSOC);

$sinFechaAlta = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE fecha_alta IS NULL OR fecha_alta = '0000-00-00'")->fetchColumn();
$sinFechaBaja = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE fecha_baja IS NULL OR fecha_baja = '0000-00-00'")->fetchColumn();

$stBaja = $pdo->query("SELECT DISTINCT YEAR(fecha_baja) AS y FROM trabajadores WHERE fecha_baja IS NOT NULL AND fecha_baja != '0000-00-00' ORDER BY y");
$aniosBaja = $stBaja->fetchAll(PDO::FETCH_COLUMN);

$stBajaMes = $pdo->query("SELECT YEAR(fecha_baja) AS y, MONTH(fecha_baja) AS m, COUNT(*) AS cnt FROM trabajadores WHERE fecha_baja IS NOT NULL AND fecha_baja != '0000-00-00' GROUP BY y, m ORDER BY y, m");
$mesesBajaRaw = $stBajaMes->fetchAll(PDO::FETCH_ASSOC);

$mesesAlta = [];
foreach ($mesesAltaRaw as $row) {
    $mesesAlta[$row['y']][(int)$row['m']] = (int)$row['cnt'];
}
$mesesBaja = [];
foreach ($mesesBajaRaw as $row) {
    $mesesBaja[$row['y']][(int)$row['m']] = (int)$row['cnt'];
}

$ccostos = $pdo->query("SELECT DISTINCT cc.codigo AS codigo, cc.nombre AS nombre FROM trabajadores t LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id WHERE cc.codigo IS NOT NULL ORDER BY cc.codigo")->fetchAll(PDO::FETCH_ASSOC);

$areas = $pdo->query("SELECT DISTINCT a.nombre_area FROM trabajadores t LEFT JOIN areas a ON t.area_id = a.id WHERE a.nombre_area IS NOT NULL ORDER BY a.nombre_area")->fetchAll(PDO::FETCH_COLUMN);

$totalActivos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1")->fetchColumn();
$totalInactivos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 0")->fetchColumn();

$bajasUltimoMes = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE fecha_baja IS NOT NULL AND fecha_baja != '0000-00-00' AND fecha_baja >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")->fetchColumn();
$altasUltimoMes = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE fecha_alta IS NOT NULL AND fecha_alta != '0000-00-00' AND fecha_alta >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")->fetchColumn();
$bajasUltimoTrim = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE fecha_baja IS NOT NULL AND fecha_baja != '0000-00-00' AND fecha_baja >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)")->fetchColumn();

$areaMayorFluct = $pdo->query("
    SELECT a.nombre_area AS area, COUNT(*) AS total_bajas
    FROM trabajadores t
    JOIN areas a ON t.area_id = a.id
    WHERE t.fecha_baja IS NOT NULL AND t.fecha_baja != '0000-00-00'
      AND t.fecha_baja >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    GROUP BY a.nombre_area
    ORDER BY total_bajas DESC
    LIMIT 1
")->fetch();
$areaMayorFluctNombre = $areaMayorFluct['area'] ?? '—';
$areaMayorFluctCount = $areaMayorFluct['total_bajas'] ?? 0;

$promAntiguedad = $pdo->query("
    SELECT ROUND(AVG(DATEDIFF(COALESCE(fecha_baja, CURDATE()), fecha_alta) / 365.25), 1) AS promedio
    FROM trabajadores
    WHERE fecha_alta IS NOT NULL AND fecha_alta != '0000-00-00'
")->fetchColumn();
$promAntiguedad = $promAntiguedad ?? 0;

$promSalario = $pdo->query("
    SELECT ROUND(AVG(e.salario_mensual), 2) AS promedio
    FROM trabajadores t
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    WHERE t.activo = 1
")->fetchColumn();
$promSalario = $promSalario ?? 0;

$hoy = new DateTime();
foreach ($trabajadores as &$t) {
    if (!empty($t['fecha_alta'])) {
        $desde = new DateTime($t['fecha_alta']);
        if (!empty($t['fecha_baja']) && $t['fecha_baja'] !== '0000-00-00') {
            $hasta = new DateTime($t['fecha_baja']);
        } else {
            $hasta = $hoy;
        }
        $intervalo = $desde->diff($hasta);
        $t['t_anos'] = str_pad($intervalo->y, 2, '0', STR_PAD_LEFT);
        $t['t_meses'] = str_pad($intervalo->m, 2, '0', STR_PAD_LEFT);
        $t['t_dias'] = str_pad($intervalo->d, 2, '0', STR_PAD_LEFT);
    } else {
        $t['t_anos'] = '—';
        $t['t_meses'] = '—';
        $t['t_dias'] = '—';
    }
}
unset($t);

$total = count($trabajadores);

$stNominasDet = $pdo->query("SELECT trabajador_id, periodo_hasta, total_salario_devengado FROM nominas WHERE total_salario_devengado > 0 ORDER BY trabajador_id, periodo_hasta");
$nominasDetalle = $stNominasDet->fetchAll(PDO::FETCH_ASSOC);
$nominasPorTrabajador = [];
foreach ($nominasDetalle as $nd) {
    $nid = $nd['trabajador_id'];
    if (!isset($nominasPorTrabajador[$nid])) $nominasPorTrabajador[$nid] = [];
    $nominasPorTrabajador[$nid][] = ['ph' => $nd['periodo_hasta'], 'dev' => (float)$nd['total_salario_devengado']];
}

$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas',
    'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH',
    'nit' => defined('NIT') ? NIT : '319-1-02264'
];
try {
    $stCfg = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa','jefe_proyecto','especialista_gestion','especialista_gestionRRHH')");
    while ($row = $stCfg->fetch(PDO::FETCH_ASSOC)) {
        if ($row['valor'] && trim($row['valor']) !== '') $config_empresa[$row['parametro']] = $row['valor'];
    }
} catch (PDOException $e) {}

$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SisGesNom'; ?> | SNC - 225</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">

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

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            position: relative !important; z-index: 100 !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        .glass-card {
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            position: relative;
            overflow: hidden;
        }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        .glass-card .card-icon-bg {
            position: absolute; right:-0.625rem; bottom:-0.625rem;
            font-size:5rem; opacity: 0.08; pointer-events: none; z-index: 0; transform: rotate(-8deg);
        }
        .glass-card .card-content { position: relative; z-index: 1; }

        .btn-win {
            background: rgba(96, 165, 250, 0.15);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            color: #60a5fa;
            padding:0.5rem 1rem; border-radius: 0.625rem; font-size:0.82rem; font-weight: 500;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap:0.375rem;
        }
        .btn-win:hover { background: rgba(96, 165, 250, 0.25); border-color: rgba(96, 165, 250, 0.5); color: #ffffff; }
        .btn-win-primary { background: rgba(59, 130, 246, 0.25); border-color: rgba(59, 130, 246, 0.4); color: #60a5fa; }
        .btn-win-primary:hover { background: rgba(59, 130, 246, 0.4); color: #ffffff; }
        .btn-win-outline { background: transparent; border-color: rgba(96, 165, 250, 0.3); color: #60a5fa; }
        .btn-win-outline:hover { background: rgba(96, 165, 250, 0.15); }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }

        .badge-codigo {
            background: rgba(96, 165, 250, 0.15); color: #60a5fa;
            padding:0.1875rem 0.625rem; border-radius: 0.375rem; font-size:0.78rem; font-weight: 600;
            font-family: 'Consolas', 'Courier New', monospace;
        }

        /* DataTables dark theme */
        .dataTables_wrapper .dataTables_filter input {
            background: var(--panel); border: 0.0625rem solid rgba(255, 255, 255, 0.15);
            border-radius: 0.5rem; color: #ffffff; padding:0.375rem 0.75rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #60a5fa; outline: none; box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2); }
        .dataTables_wrapper .dataTables_filter input::placeholder { color: rgba(255,255,255,0.4); }
        .dataTables_wrapper .dataTables_filter { color: rgba(255,255,255,0.6); font-size:0.82rem; }
        .dt-search .input-group-text { background-color: var(--panel); border-color: rgba(255, 255, 255, 0.2); color: rgba(255, 255, 255, 0.7); }
        .dt-search input { background-color: var(--panel); border-color: rgba(255, 255, 255, 0.2); color: var(--txt); }

        .dataTables_wrapper .dataTables_info { color: rgba(255,255,255,0.45); font-size:0.78rem; padding-top:0.625rem; }
        .dataTables_wrapper .dataTables_paginate { padding-top:0.625rem; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--txt) !important; background: var(--panel) !important; border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 0.5rem !important; padding:0.375rem 0.75rem !important; margin:0 0.1875rem !important; font-weight: 500 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(96, 165, 250, 0.3) !important; border-color: #60a5fa !important; color: #ffffff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important; border-color: #60a5fa !important; color: #ffffff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: rgba(255, 255, 255, 0.4) !important; background: var(--panel-2) !important; border-color: rgba(255, 255, 255, 0.08) !important; cursor: not-allowed !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: rgba(255,255,255,0.2) !important; border-color: transparent !important;
        }

        table.dataTable thead th {
            background: rgba(96, 165, 250, 0.12); color: #93c5fd;
            font-size:0.75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing:0.05rem; padding:0.75rem 1rem;
            border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.2) !important;
        }
        table.dataTable thead th.sorting, table.dataTable thead th.sorting_asc, table.dataTable thead th.sorting_desc {
            cursor: pointer;
        }
        table.dataTable thead th.sorting::after, table.dataTable thead th.sorting_asc::after, table.dataTable thead th.sorting_desc::after {
            opacity: 0.4;
        }
        table.dataTable tbody tr {
            background: transparent;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.04);
            transition: background 0.15s;
        }
        table.dataTable tbody tr:hover { background: rgba(96, 165, 250, 0.08) !important; }
        table.dataTable tbody td {
            padding:0.625rem 1rem; font-size:0.85rem; color: #e2e8f0;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.04);
        }

        table.dataTable.no-footer { border-bottom: none !important; }
        table.dataTable { border: none !important; }

        .dataTables_scrollBody { overflow-x: auto !important; }

        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }

        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }

        .swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }

        /* Grupo Imprimir / Exportar */
        .btn-export-main { border-radius: 0.75rem 0 0 0.75rem; padding:0.5rem 1rem; }
        .btn-export-toggle { border-radius: 0 0.75rem 0.75rem 0; padding:0.5rem 0.75rem; border-left: 0.0625rem solid rgba(255, 255, 255, 0.3); }
        .btn-export-toggle::after { margin-left:0; }

        .dropdown-menu-win { background: var(--panel) !important; border: 0.0625rem solid rgba(255, 255, 255, 0.12) !important; border-radius: 0.75rem !important; padding: 0.5rem !important; box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.5) !important; }
        .dropdown-menu-win .dropdown-item { color: #e2e8f0 !important; border-radius: 0.5rem !important; padding:0.625rem 1rem !important; font-size:0.88rem !important; transition: all 0.15s !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(148, 163, 184, 0.35) !important; border-top-width: 0.0625rem !important; opacity: 1 !important; margin:0.5rem 0.25rem !important; }
        [data-theme="light"] .dropdown-menu-win { background: #ffffff !important; border-color: rgba(0,0,0,0.15) !important; }
        [data-theme="light"] .dropdown-menu-win .dropdown-item { color: #374151 !important; }
        [data-theme="light"] .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.1) !important; color: #111827 !important; }

        /* Chevron toggle wrapper */
        .snc-select-wrap { position: relative; display: inline-flex; align-items: center; }
        .snc-select-wrap select { appearance: none; -webkit-appearance: none; background-image: none !important; padding-right:1.75rem !important; cursor: pointer; }
        [data-theme="light"] .snc-select-wrap select { background-image: none !important; }
        .snc-select-wrap .snc-chevron {
            position: absolute; right:0.5rem; top:50%; transform: translateY(-50%);
            pointer-events: none; font-size:0.65rem; color: rgba(255,255,255,0.5);
            transition: transform 0.2s ease, color 0.2s ease;
        }
        [data-theme="light"] .snc-select-wrap .snc-chevron { color: rgba(0,0,0,0.45); }
        .snc-select-wrap.open .snc-chevron { transform: translateY(-50%) rotate(180deg); color: #60a5fa; }
        [data-theme="light"] .snc-select-wrap.open .snc-chevron { color: #0078d4; }

        /* ======== SNC-225 Complete Light Theme Overrides ======== */
        /* Using html[data-theme="light"] for higher specificity than user_menu.php */

        /* --- Stat cards --- */
        html[data-theme="light"] .snc-stat-card {
            color: #1f2937 !important;
            border: 0.0625rem solid rgba(0,0,0,0.2) !important;
            background: #ffffff !important;
            box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.06), 0 0 0 0.0625rem rgba(0,0,0,0.04) !important;
        }
        html[data-theme="light"] .snc-stat-card:hover {
            box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.1), 0 0 0 0.0625rem rgba(0,0,0,0.08) !important;
            transform: translateY(-0.125rem) !important;
            border-color: rgba(0,0,0,0.25) !important;
        }
        html[data-theme="light"] .snc-stat-card .snc-stat-label { color: rgba(0,0,0,0.45) !important; }
        html[data-theme="light"] .snc-stat-card .snc-stat-sub { color: rgba(0,0,0,0.4) !important; }

        /* Card-specific backgrounds */
        html[data-theme="light"] .snc-card-blue { background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(37,99,235,0.02)) !important; border-color: rgba(37,99,235,0.25) !important; }
        html[data-theme="light"] .snc-card-green,
        html[data-theme="light"] .snc-card-green2 { background: linear-gradient(135deg, rgba(22,163,74,0.1), rgba(22,163,74,0.02)) !important; border-color: rgba(22,163,74,0.25) !important; }
        html[data-theme="light"] .snc-card-red { background: linear-gradient(135deg, rgba(220,38,38,0.1), rgba(220,38,38,0.02)) !important; border-color: rgba(220,38,38,0.25) !important; }
        html[data-theme="light"] .snc-card-amber { background: linear-gradient(135deg, rgba(217,119,6,0.1), rgba(217,119,6,0.02)) !important; border-color: rgba(217,119,6,0.25) !important; }
        html[data-theme="light"] .snc-card-orange { background: linear-gradient(135deg, rgba(234,88,12,0.1), rgba(234,88,12,0.02)) !important; border-color: rgba(234,88,12,0.25) !important; }
        html[data-theme="light"] .snc-card-purple { background: linear-gradient(135deg, rgba(147,51,234,0.1), rgba(147,51,234,0.02)) !important; border-color: rgba(147,51,234,0.25) !important; }
        html[data-theme="light"] .snc-card-teal { background: linear-gradient(135deg, rgba(13,148,136,0.1), rgba(13,148,136,0.02)) !important; border-color: rgba(13,148,136,0.25) !important; }
        html[data-theme="light"] .snc-card-indigo { background: linear-gradient(135deg, rgba(124,58,237,0.1), rgba(124,58,237,0.02)) !important; border-color: rgba(124,58,237,0.25) !important; }

        /* Icon boxes */
        html[data-theme="light"] .snc-icon-box.snc-icon-blue { background: rgba(37,99,235,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-green,
        html[data-theme="light"] .snc-icon-box.snc-icon-green2 { background: rgba(22,163,74,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-red { background: rgba(220,38,38,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-amber { background: rgba(217,119,6,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-orange { background: rgba(234,88,12,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-purple { background: rgba(147,51,234,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-teal { background: rgba(13,148,136,0.12) !important; }
        html[data-theme="light"] .snc-icon-box.snc-icon-indigo { background: rgba(124,58,237,0.12) !important; }

        /* Icon children */
        html[data-theme="light"] .snc-card-blue .snc-icon-box i { color: #2563eb !important; }
        html[data-theme="light"] .snc-card-green .snc-icon-box i,
        html[data-theme="light"] .snc-card-green2 .snc-icon-box i { color: #16a34a !important; }
        html[data-theme="light"] .snc-card-red .snc-icon-box i { color: #dc2626 !important; }
        html[data-theme="light"] .snc-card-amber .snc-icon-box i { color: #d97706 !important; }
        html[data-theme="light"] .snc-card-orange .snc-icon-box i { color: #ea580c !important; }
        html[data-theme="light"] .snc-card-purple .snc-icon-box i { color: #9333ea !important; }
        html[data-theme="light"] .snc-card-teal .snc-icon-box i { color: #0d9488 !important; }
        html[data-theme="light"] .snc-card-indigo .snc-icon-box i { color: #7c3aed !important; }

        /* Value numbers */
        html[data-theme="light"] .snc-card-blue .snc-stat-value { color: #2563eb !important; }
        html[data-theme="light"] .snc-card-green .snc-stat-value,
        html[data-theme="light"] .snc-card-green2 .snc-stat-value { color: #16a34a !important; }
        html[data-theme="light"] .snc-card-red .snc-stat-value { color: #dc2626 !important; }
        html[data-theme="light"] .snc-card-amber .snc-stat-value { color: #d97706 !important; }
        html[data-theme="light"] .snc-card-orange .snc-stat-value { color: #ea580c !important; }
        html[data-theme="light"] .snc-card-purple .snc-stat-value { color: #9333ea !important; }
        html[data-theme="light"] .snc-card-teal .snc-stat-value { color: #0d9488 !important; }
        html[data-theme="light"] .snc-card-indigo .snc-stat-value { color: #7c3aed !important; }

        /* --- Filtros panel --- */
        html[data-theme="light"] #sncFiltrosWrap {
            background: #ffffff !important;
            border: 0.0625rem solid rgba(0,0,0,0.18) !important;
            box-shadow: 0 0.0625rem 0.1875rem rgba(0,0,0,0.04);
        }
        html[data-theme="light"] #sncFiltrosToggle span { color: rgba(0,0,0,0.65) !important; }
        html[data-theme="light"] #sncFiltrosToggle .fa-filter { color: #2563eb !important; }
        html[data-theme="light"] #sncFiltrosChevron { color: rgba(0,0,0,0.35) !important; }
        html[data-theme="light"] #sncFiltrosWrap label { color: rgba(0,0,0,0.5) !important; }
        html[data-theme="light"] .snc-filtro-label { color: #2563eb !important; }
        html[data-theme="light"] .snc-filtro-label-red { color: #dc2626 !important; }
        html[data-theme="light"] .snc-filtro-label-violet { color: #7c3aed !important; }
        html[data-theme="light"] .snc-filtro-label-green { color: #16a34a !important; }
        html[data-theme="light"] .snc-filtro-label-amber { color: #d97706 !important; }
        html[data-theme="light"] .snc-filtro-divider { background: rgba(0,0,0,0.15) !important; }

        /* --- Selects --- */
        html[data-theme="light"] .snc-select-wrap select {
            background-color: #f8fafc !important;
            color: #1f2937 !important;
            border-color: rgba(0,0,0,0.2) !important;
        }
        html[data-theme="light"] .snc-select-wrap select:hover { border-color: rgba(0,0,0,0.35) !important; }
        html[data-theme="light"] .snc-select-wrap select:focus { border-color: #2563eb !important; box-shadow: 0 0 0 0.125rem rgba(37,99,235,0.15) !important; }
        html[data-theme="light"] .snc-select-wrap .snc-chevron { color: rgba(0,0,0,0.4) !important; }
        html[data-theme="light"] .snc-select-wrap.open .snc-chevron { color: #2563eb !important; }

        /* --- Table --- */
        html[data-theme="light"] .table-snc { color: #1f2937 !important; }
        html[data-theme="light"] .table-snc thead th { background: rgba(37,99,235,0.06) !important; color: #1e40af !important; border-bottom: 0.125rem solid rgba(0,0,0,0.12) !important; }
        html[data-theme="light"] .table-snc tbody tr { border-bottom: 0.0625rem solid rgba(0,0,0,0.08) !important; }
        html[data-theme="light"] .table-snc tbody tr:hover { background: rgba(37,99,235,0.06) !important; }
        html[data-theme="light"] .table-snc tbody td { color: #1f2937 !important; border-bottom-color: rgba(0,0,0,0.08) !important; }
        html[data-theme="light"] .badge-codigo { background: rgba(37,99,235,0.1) !important; color: #2563eb !important; }
        html[data-theme="light"] .table-snc td a { color: #2563eb !important; }

        /* --- Glass card header --- */
        html[data-theme="light"] .card-content h5 { color: #1f2937 !important; }
        html[data-theme="light"] .card-content h5 i { color: #2563eb !important; }
        html[data-theme="light"] .card-content p { color: rgba(0,0,0,0.45) !important; }

        @media print {
            .no-print { display: none !important; }
            .main-container { margin-left:0 !important; padding:0.625rem !important; }
            .glass-card { border: 0.0625rem solid #ccc !important; box-shadow: none !important; transform: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate { display: none !important; }
        }
        .snc-modal-nota { margin-top:0.4rem; padding-top:0.4rem; border-top:0.0625rem solid rgba(251,191,36,0.2); font-size:1rem; font-style:italic; background:rgba(251,191,36,0.08); border:0.0625rem solid rgba(251,191,36,0.25); border-radius:0.375rem; padding:0.5rem 0.75rem; color:#d97706; }
        html[data-theme="light"] .snc-modal-nota { background:rgba(234,179,8,0.12); color:#92400e; border-color:rgba(234,179,8,0.3); border-top:none; }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" data-tooltip="Menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-id-card me-2" style="color: #60a5fa;"></i>Tarjeta SNC - 225</h1>
                <p><i class="fas fa-list-alt me-1"></i> Listado de todos los trabajadores para exportar SNC-225</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <div class="mb-3 fade-in-up" style="animation-delay: 0.05s;">
        <a href="empleados.php" class="btn-win btn-win-outline btn-win-sm" title="Volver a Empleados" data-tooltip="Volver a Empleados" data-tooltip-theme="primary">
            <i class="fas fa-arrow-left me-1"></i> Volver a Empleados
        </a>
    </div>

    <div class="glass-card fade-in-up p-0" style="animation-delay: 0.1s;">
        <div class="card-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1" style="color: #e2e8f0; font-weight: 600;">
                        <i class="fas fa-users me-2" style="color: #60a5fa;"></i>Listado de Trabajadores
						<p>SC-4-09 Certificación de años de Servicios y Salarios Devengados</p>
                    </h5>
                    <?php
                    $totalBajas = 0;
                    foreach ($trabajadores as $tr) {
                        if (!empty($tr['fecha_baja']) && $tr['fecha_baja'] !== '0000-00-00') $totalBajas++;
                    }
                    ?>
                </div>
                <div class="d-flex gap-2 no-print">
                    <div class="btn-group" role="group" aria-label="Acciones SNC-225">
                        <button class="btn-win btn-win-primary btn-export-main" id="btnPrintSNC" title="Imprimir listado SNC-225" data-tooltip="Imprimir listado" data-tooltip-theme="primary">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>
                        <button type="button" class="btn-win btn-win-primary btn-export-toggle dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar listado SNC-225" data-tooltip="Exportar listado" data-tooltip-theme="info">
                            <span class="visually-hidden">Exportar</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win" id="exportSNCMenu">
                            <li><a class="dropdown-item" href="#" id="btnExportPdfSNC" title="Exportar a PDF"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                            <li><a class="dropdown-item" href="#" id="btnExportWordSNC" title="Exportar a Word"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word</a></li>
                            <li><a class="dropdown-item" href="#" id="btnExportExcelSNC" title="Exportar a Excel"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="btnExportCsvSNC" title="Exportar a CSV"><i class="fas fa-file-csv me-2" style="color: #22c55e;"></i>Exportar a CSV</a></li>
                            <li><a class="dropdown-item" href="#" id="btnExportTxtSNC" title="Exportar a TXT"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mb-3 flex-wrap no-print">
                <div class="snc-stat-card snc-card-blue" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(96,165,250,0.12), rgba(96,165,250,0.04)); border: 0.0625rem solid rgba(96,165,250,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-blue" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(96,165,250,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-users" style="color: #60a5fa; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Total</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #60a5fa; line-height:1.2;"><?php echo $total; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">trabajador(es)</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-green" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(34,197,94,0.04)); border: 0.0625rem solid rgba(34,197,94,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-green" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(34,197,94,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-user-check" style="color: #22c55e; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Activos</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #22c55e; line-height:1.2;"><?php echo $totalActivos; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">en nómina</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-red" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(248,113,113,0.12), rgba(248,113,113,0.04)); border: 0.0625rem solid rgba(248,113,113,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-red" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(248,113,113,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-user-xmark" style="color: #f87171; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Bajas</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #f87171; line-height:1.2;"><?php echo $totalBajas; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">dados de baja</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-amber" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(251,191,36,0.12), rgba(251,191,36,0.04)); border: 0.0625rem solid rgba(251,191,36,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-amber" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(251,191,36,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-calendar-xmark" style="color: #fbbf24; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Bajas Mes</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #fbbf24; line-height:1.2;"><?php echo $bajasUltimoMes; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">últimos 30 días</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-orange" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(249,115,22,0.12), rgba(249,115,22,0.04)); border: 0.0625rem solid rgba(249,115,22,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-orange" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(249,115,22,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-calendar-minus" style="color: #f97316; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Bajas Trimestre</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #f97316; line-height:1.2;"><?php echo $bajasUltimoTrim; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">últimos 90 días</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-teal" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(20,184,166,0.12), rgba(20,184,166,0.04)); border: 0.0625rem solid rgba(20,184,166,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-teal" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(20,184,166,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-clock" style="color: #14b8a6; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Antigüedad Prom.</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #14b8a6; line-height:1.2;"><?php echo $promAntiguedad; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">años</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-purple" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(168,85,247,0.12), rgba(168,85,247,0.04)); border: 0.0625rem solid rgba(168,85,247,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-purple" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(168,85,247,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-arrow-right-arrow-left" style="color: #a855f7; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Mayor Fluctuación</div>
                        <div class="snc-stat-value" style="font-size:0.95rem; font-weight: 700; color: #a855f7; line-height:1.2;" data-tooltip="<?php echo htmlspecialchars($areaMayorFluctNombre); ?> — <?php echo $areaMayorFluctCount; ?> bajas en 12 meses" data-tooltip-theme="gradient"><?php echo htmlspecialchars(mb_strimwidth((string)$areaMayorFluctNombre, 0, 18, '...')); ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);"><?php echo $areaMayorFluctCount; ?> bajas / 12m</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-indigo" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(139,92,246,0.12), rgba(139,92,246,0.04)); border: 0.0625rem solid rgba(139,92,246,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-indigo" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(139,92,246,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-money-bill-wave" style="color: #8b5cf6; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Salario Prom.</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #8b5cf6; line-height:1.2;">$<?php echo number_format($promSalario, 2); ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">activos (CUP)</div>
                    </div>
                </div>
                <div class="snc-stat-card snc-card-green2" style="flex: 1; min-width:8.75rem; background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(34,197,94,0.04)); border: 0.0625rem solid rgba(34,197,94,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display: flex; align-items: center; gap:0.875rem;">
                    <div class="snc-icon-box snc-icon-green2" style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; background: rgba(34,197,94,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-user-plus" style="color: #22c55e; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div class="snc-stat-label" style="font-size:0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing:0.0312rem; font-weight: 600;">Altas Mes</div>
                        <div class="snc-stat-value" style="font-size:1.4rem; font-weight: 700; color: #22c55e; line-height:1.2;"><?php echo $altasUltimoMes; ?></div>
                        <div class="snc-stat-sub" style="font-size:0.7rem; color: rgba(255,255,255,0.4);">últimos 30 días</div>
                    </div>
                </div>
            </div>

            <div class="mb-3 flex-wrap no-print" id="sncFiltrosWrap" style="padding:0.75rem 1rem; background: rgba(255,255,255,0.03); border: 0.0625rem solid rgba(255,255,255,0.06); border-radius: 0.625rem;">
                <div class="d-flex align-items-center gap-2" id="sncFiltrosToggle" style="cursor: pointer; user-select: none; padding:0.125rem 0;">
                    <i class="fas fa-filter" style="color: #60a5fa; font-size:0.82rem;"></i>
                    <span style="color: rgba(255,255,255,0.7); font-size:0.82rem; font-weight: 600;">FILTROS</span>
                    <i class="fas fa-chevron-down ms-auto" id="sncFiltrosChevron" style="color: rgba(255,255,255,0.4); font-size:0.7rem; transition: transform 0.3s ease;"></i>
                </div>
                <div id="sncFiltrosBody">
                    <div class="d-flex align-items-end gap-4 flex-wrap pt-2">
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label" style="color: #60a5fa; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">F. Alta</span>
                            <div class="d-flex align-items-center gap-1">
                                <label for="filtroAltaAnio" style="color: rgba(255,255,255,0.5); font-size:0.78rem; margin:0;">Año:</label>
                                <div class="snc-select-wrap">
                                    <select id="filtroAltaAnio" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:5.625rem;"></select>
                                    <i class="fas fa-chevron-down snc-chevron"></i>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <label for="filtroAltaMes" style="color: rgba(255,255,255,0.5); font-size:0.78rem; margin:0;">Mes:</label>
                                <div class="snc-select-wrap">
                                    <select id="filtroAltaMes" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:8.125rem;"></select>
                                    <i class="fas fa-chevron-down snc-chevron"></i>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label-red" style="color: #f87171; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">F. Baja</span>
                            <div class="d-flex align-items-center gap-1">
                                <label for="filtroBajaAnio" style="color: rgba(255,255,255,0.5); font-size:0.78rem; margin:0;">Año:</label>
                                <div class="snc-select-wrap">
                                    <select id="filtroBajaAnio" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:5.625rem;"></select>
                                    <i class="fas fa-chevron-down snc-chevron"></i>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <label for="filtroBajaMes" style="color: rgba(255,255,255,0.5); font-size:0.78rem; margin:0;">Mes:</label>
                                <div class="snc-select-wrap">
                                    <select id="filtroBajaMes" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:8.125rem;"></select>
                                    <i class="fas fa-chevron-down snc-chevron"></i>
                                </div>
                            </div>
                        </div>
                        <div class="snc-filtro-divider" style="width:0.0625rem; height:2.5rem; background: rgba(255,255,255,0.1); align-self: center;"></div>
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label-violet" style="color: #a78bfa; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">C. Costo</span>
                            <div class="snc-select-wrap">
                                <select id="filtroCosto" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:8.75rem;"></select>
                                <i class="fas fa-chevron-down snc-chevron"></i>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label-green" style="color: #34d399; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">Área</span>
                            <div class="snc-select-wrap">
                                <select id="filtroArea" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:8.75rem;"></select>
                                <i class="fas fa-chevron-down snc-chevron"></i>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label-amber" style="color: #fbbf24; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">Estado</span>
                            <div class="snc-select-wrap">
                                <select id="filtroEstado" class="form-select form-select-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 1.75rem 0.25rem 0.625rem; font-size:0.82rem; min-width:8.125rem;"></select>
                                <i class="fas fa-chevron-down snc-chevron"></i>
                            </div>
                        </div>
                        <div class="snc-filtro-divider" style="width:0.0625rem; height:2.5rem; background: rgba(255,255,255,0.1); align-self: center;"></div>
                        <div class="d-flex flex-column gap-1">
                            <span class="snc-filtro-label-amber" style="color: #fb923c; font-size:0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing:0.0312rem;">Fecha Final de Cálculo</span>
                            <input type="date" id="filtroFechaCalculo" class="form-control form-control-sm" style="background-color: var(--panel); color: var(--txt); border: 0.0625rem solid rgba(255,255,255,0.15); border-radius: 0.5rem; padding:0.25rem 0.625rem; font-size:0.82rem; min-width:10rem; color-scheme: dark;" title="Solo incluir nóminas hasta esta fecha" data-tooltip="Fecha final de cálculo para Total Devengado" data-tooltip-theme="warning">
                        </div>
                        <button class="btn-win btn-win-sm" id="btnLimpiarFiltrosSNC" type="button" style="margin-bottom:0.125rem;" data-tooltip="Limpiar filtros y mostrar todo" data-tooltip-theme="warning">
                            <i class="fas fa-times me-1"></i>Limpiar Filtros
                        </button>
                    </div>
                </div>

            <?php if ($total > 0): ?>
            <table class="table-snc display nowrap" id="tablaSNC225" style="width:100%;">
                <thead>
                    <tr>
                        <th rowspan="2" style="text-align: center; width:0.625rem;"><i class="fas fa-eye"></i></th>
                        <th rowspan="2" style="text-align: center; width:0.625rem;">#</th>
                        <th rowspan="2" style="text-align: center; width:0.625rem;">No. Exped.</th>
                        <th rowspan="2" style="text-align: center;">C.I.</th>
                        <th rowspan="2">Nombre y Apellidos</th>
                        <th rowspan="2" style="text-align: center;">C. Costo</th>
                        <th colspan="2" style="text-align: center;">Fechas</th>
                        <th colspan="3" style="text-align: center;">Tiempo<br>Trabajado</th>
                        <th rowspan="2" style="text-align: center;">Total<br>Devengado</th>
                    </tr>
                    <tr>
                        <th style="text-align: center;">Alta</th>
                        <th style="text-align: center;">Baja</th>
                        <th style="text-align: center;">A</th>
                        <th style="text-align: center;">M</th>
                        <th style="text-align: center;">D</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trabajadores as $i => $t): ?>
                    <?php
                        $fa = (!empty($t['fecha_alta']) && $t['fecha_alta'] !== '0000-00-00') ? $t['fecha_alta'] : null;
                        $fb = (!empty($t['fecha_baja']) && $t['fecha_baja'] !== '0000-00-00') ? $t['fecha_baja'] : null;
                    ?>
                    <tr data-id="<?php echo $t['id']; ?>" data-fecha-alta="<?php echo $fa ?? ''; ?>" data-fecha-baja="<?php echo $fb ?? ''; ?>" data-tanos="<?php echo htmlspecialchars($t['t_anos']); ?>" data-tmeses="<?php echo htmlspecialchars($t['t_meses']); ?>" data-tdias="<?php echo htmlspecialchars($t['t_dias']); ?>" data-alta-year="<?php echo $fa ? date('Y', strtotime($fa)) : ''; ?>" data-alta-month="<?php echo $fa ? date('m', strtotime($fa)) : ''; ?>" data-baja-year="<?php echo $fb ? date('Y', strtotime($fb)) : ''; ?>" data-baja-month="<?php echo $fb ? date('m', strtotime($fb)) : ''; ?>" data-costo="<?php echo htmlspecialchars($t['c_costo'] ?? ''); ?>" data-area="<?php echo htmlspecialchars($t['area_nombre'] ?? ''); ?>" data-activo="<?php echo $t['activo']; ?>">
                        <td style="text-align: center;">
                            <button class="btn-icon btn-icon-primary" onclick="abrirTarjeta(<?php echo htmlspecialchars(json_encode($t)); ?>)" data-tooltip="Ver Tarjeta SNC-225" data-tooltip-theme="info" style="background: rgba(96,165,250,0.15); border: 0.0625rem solid rgba(96,165,250,0.3); color: #60a5fa; width:2rem; height:2rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-id-card" style="font-size:0.82rem;"></i>
                            </button>
                        </td>
                        <td style="text-align: center; color: rgba(255,255,255,0.4); font-size:0.78rem;"><?php echo $i + 1; ?></td>
                        <td style="text-align: center;"><a href="empleados.php?editar=<?php echo $t['id']; ?>" style="color: #60a5fa; text-decoration: none; font-family: 'Consolas', monospace; font-weight: 600;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo htmlspecialchars($t['codigo']); ?></a></td>
                        <td style="text-align: center;"><a href="empleados.php?editar=<?php echo $t['id']; ?>" style="color: #93c5fd; text-decoration: none; font-family: 'Consolas', monospace;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo htmlspecialchars($t['ci']); ?></a></td>
                        <td><a href="empleados.php?editar=<?php echo $t['id']; ?>" style="color: #e2e8f0; text-decoration: none;" onmouseover="this.style.color='#60a5fa'; this.style.textDecoration='underline'" onmouseout="this.style.color='#e2e8f0'; this.style.textDecoration='none'"><?php echo htmlspecialchars($t['nombre_completo']); ?></a></td>
                        <td style="text-align: center;" data-tooltip="<?php echo htmlspecialchars($t['c_costo_nombre'] ?? 'Sin centro de costo'); ?>" data-tooltip-theme="secondary"><span class="badge-codigo"><?php echo htmlspecialchars($t['c_costo'] ?? '—'); ?></span></td>
                        <td><?php echo $t['fecha_alta'] ? date('d/m/Y', strtotime($t['fecha_alta'])) : '—'; ?></td>
                        <td><?php echo $t['fecha_baja'] ? date('d/m/Y', strtotime($t['fecha_baja'])) : '—'; ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($t['t_anos']); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($t['t_meses']); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($t['t_dias']); ?></td>
                        <td style="text-align: right; font-family: 'Consolas', monospace; color: #34d399;">$<?php echo number_format($t['total_devengado'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users-slash" style="font-size:3rem; color: rgba(255,255,255,0.15); margin-bottom:1rem;"></i>
                <h5 style="color: rgba(255,255,255,0.4);">No hay trabajadores registrados</h5>
                <p style="color: rgba(255,255,255,0.3); font-size:0.85rem;">No se encontraron registros de trabajadores en el sistema.</p>
            </div>
            <?php endif; ?>
        </div>
        <i class="fas fa-id-card card-icon-bg" style="color: #60a5fa;"></i>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script>
var SNC_FILTROS = {
    aniosAlta: <?php echo json_encode($aniosAlta); ?>,
    mesesAlta: <?php echo json_encode($mesesAlta); ?>,
    sinFechaAlta: <?php echo intval($sinFechaAlta); ?>,
    aniosBaja: <?php echo json_encode($aniosBaja); ?>,
    mesesBaja: <?php echo json_encode($mesesBaja); ?>,
    sinFechaBaja: <?php echo intval($sinFechaBaja); ?>,
    ccostos: <?php echo json_encode($ccostos); ?>,
    areas: <?php echo json_encode($areas); ?>,
    totalActivos: <?php echo intval($totalActivos); ?>,
    totalInactivos: <?php echo intval($totalInactivos); ?>,
    nominas: <?php echo json_encode($nominasPorTrabajador); ?>,
    originales: <?php
        $orig = [];
        foreach ($trabajadores as $t) {
            $orig[$t['id']] = (float)$t['total_devengado'];
        }
        echo json_encode($orig);
    ?>
};
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mainContainer = document.getElementById('mainContainer');
    if (toggleBtn && mainContainer) {
        toggleBtn.addEventListener('click', function() {
            mainContainer.classList.toggle('expanded');
        });
    }

    if ($.fn.DataTable.isDataTable('#tablaSNC225')) {
        $('#tablaSNC225').DataTable().destroy();
    }

    $('#tablaSNC225').DataTable({
        language: {
            search: '',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            paginate: { first: '<i class="fas fa-step-backward"></i>', last: '<i class="fas fa-step-forward"></i>', next: '<i class="fas fa-chevron-right"></i>', previous: '<i class="fas fa-chevron-left"></i>' },
            zeroRecords: 'No se encontraron resultados'
        },
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        pagingType: 'full_numbers',
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, 'Todos']],
        order: [[2, 'asc']],
        scrollX: true,
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [0, 1] },
            { className: 'text-center', targets: [0, 1, 2, 3, 5, 6, 7, 8, 9, 10] },
            { className: 'text-right', targets: [11] },
            { type: 'date-dd-mmm-yyyy', targets: [6, 7] }
        ]
    });

    $('.dt-search').html(`
        <div class="input-group input-group-sm" style="width:20rem;">
            <span class="input-group-text bg-dark border-secondary"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Buscar empleado..." id="customSearchSNC">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="clearSearchSNC" style="background: var(--panel);"><i class="fas fa-times"></i></button>
        </div>
    `);
    var tableSNC = $('#tablaSNC225').DataTable();
    $('#customSearchSNC').on('keyup', function() { tableSNC.search(this.value).draw(); });
    $('#clearSearchSNC').on('click', function() { $('#customSearchSNC').val(''); tableSNC.search('').draw(); });

    var F = SNC_FILTROS;
    $('#tablaSNC225 tbody tr').each(function() {
        var id = $(this).data('id');
        var val = F.originales[id] !== undefined ? F.originales[id] : 0;
        $(this).find('td').last().attr('data-valor-dev', val);
    });

    var meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    var SIN = '_sin';

    var $altaAnio = $('#filtroAltaAnio');
    $altaAnio.append('<option value="">Todos</option>');
    F.aniosAlta.forEach(function(a) { $altaAnio.append('<option value="' + a + '">' + a + '</option>'); });
    if (F.sinFechaAlta > 0) $altaAnio.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaAlta + ')</option>');

    var $altaMes = $('#filtroAltaMes');
    $altaMes.append('<option value="">Todos</option>');

    function cargarMesesAlta(anio) {
        $altaMes.find('option:gt(0)').remove();
        if (anio === SIN) {
            $altaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (anio && F.mesesAlta[anio]) {
            Object.keys(F.mesesAlta[anio]).sort(function(a,b){return a-b;}).forEach(function(m) {
                var cnt = F.mesesAlta[anio][m];
                $altaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + cnt + ')</option>');
            });
            $altaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (!anio) {
            var todos = {};
            Object.values(F.mesesAlta).forEach(function(obj) { Object.keys(obj).forEach(function(m) { todos[m] = (todos[m] || 0) + obj[m]; }); });
            Object.keys(todos).sort(function(a,b){return a-b;}).forEach(function(m) {
                $altaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + todos[m] + ')</option>');
            });
            if (F.sinFechaAlta > 0) $altaMes.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaAlta + ')</option>');
        }
    }
    cargarMesesAlta('');

    var $bajaAnio = $('#filtroBajaAnio');
    $bajaAnio.append('<option value="">Todos</option>');
    F.aniosBaja.forEach(function(a) { $bajaAnio.append('<option value="' + a + '">' + a + '</option>'); });
    if (F.sinFechaBaja > 0) $bajaAnio.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaBaja + ')</option>');

    var $bajaMes = $('#filtroBajaMes');
    $bajaMes.append('<option value="">Todos</option>');

    function cargarMesesBaja(anio) {
        $bajaMes.find('option:gt(0)').remove();
        if (anio === SIN) {
            $bajaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (anio && F.mesesBaja[anio]) {
            Object.keys(F.mesesBaja[anio]).sort(function(a,b){return a-b;}).forEach(function(m) {
                var cnt = F.mesesBaja[anio][m];
                $bajaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + cnt + ')</option>');
            });
            $bajaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (!anio) {
            var todos = {};
            Object.values(F.mesesBaja).forEach(function(obj) { Object.keys(obj).forEach(function(m) { todos[m] = (todos[m] || 0) + obj[m]; }); });
            Object.keys(todos).sort(function(a,b){return a-b;}).forEach(function(m) {
                $bajaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + todos[m] + ')</option>');
            });
            if (F.sinFechaBaja > 0) $bajaMes.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaBaja + ')</option>');
        }
    }
    cargarMesesBaja('');

    var $costo = $('#filtroCosto');
    $costo.append('<option value="">Todos</option>');
    F.ccostos.forEach(function(c) { $costo.append('<option value="' + c.codigo + '">' + c.codigo + ' - ' + c.nombre + '</option>'); });

    var $area = $('#filtroArea');
    $area.append('<option value="">Todos</option>');
    F.areas.forEach(function(a) { $area.append('<option value="' + a + '">' + a + '</option>'); });

    var $estado = $('#filtroEstado');
    $estado.append('<option value="">Todos</option>');
    $estado.append('<option value="1">Activo (' + F.totalActivos + ')</option>');
    $estado.append('<option value="0">Inactivo (' + F.totalInactivos + ')</option>');

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = tableSNC.row(dataIndex).node();
        var fAltaAnio = $('#filtroAltaAnio').val();
        var fAltaMes = $('#filtroAltaMes').val();
        var fBajaAnio = $('#filtroBajaAnio').val();
        var fBajaMes = $('#filtroBajaMes').val();
        var fCosto = $('#filtroCosto').val();
        var fArea = $('#filtroArea').val();
        var fEstado = $('#filtroEstado').val();
        var altaYear = $(row).attr('data-alta-year') || '';
        var altaMonth = $(row).attr('data-alta-month') || '';
        var bajaYear = $(row).attr('data-baja-year') || '';
        var bajaMonth = $(row).attr('data-baja-month') || '';
        var costo = $(row).attr('data-costo') || '';
        var area = $(row).attr('data-area') || '';
        var activo = $(row).attr('data-activo');

        if (fAltaAnio) {
            if (fAltaAnio === SIN) { if (altaYear !== '') return false; }
            else if (altaYear === '' || fAltaAnio !== altaYear) return false;
        }
        if (fAltaMes) {
            if (fAltaMes === SIN) { if (altaMonth !== '') return false; }
            else if (altaMonth === '' || fAltaMes !== altaMonth) return false;
        }
        if (fBajaAnio) {
            if (fBajaAnio === SIN) { if (bajaYear !== '') return false; }
            else if (bajaYear === '' || fBajaAnio !== bajaYear) return false;
        }
        if (fBajaMes) {
            if (fBajaMes === SIN) { if (bajaMonth !== '') return false; }
            else if (bajaMonth === '' || fBajaMes !== bajaMonth) return false;
        }
        if (fCosto && costo !== fCosto) return false;
        if (fArea && area !== fArea) return false;
        if (fEstado !== '' && activo !== fEstado) return false;
        return true;
    });

    $('#filtroAltaAnio').on('change', function() { cargarMesesAlta(this.value); tableSNC.draw(); });
    $('#filtroAltaMes').on('change', function() { tableSNC.draw(); });
    $('#filtroBajaAnio').on('change', function() { cargarMesesBaja(this.value); tableSNC.draw(); });
    $('#filtroBajaMes').on('change', function() { tableSNC.draw(); });
    $('#filtroCosto, #filtroArea, #filtroEstado').on('change', function() { tableSNC.draw(); });

    $('#btnLimpiarFiltrosSNC').on('click', function() {
        cargarMesesAlta('');
        cargarMesesBaja('');
        $('#filtroAltaAnio').val('');
        $('#filtroAltaMes').val('');
        $('#filtroBajaAnio').val('');
        $('#filtroBajaMes').val('');
        $('#filtroCosto').val('');
        $('#filtroArea').val('');
        $('#filtroEstado').val('');
        $('#filtroFechaCalculo').val('');
        $('#customSearchSNC').val('');
        tableSNC.search('').draw();
        restaurarTotalesOriginales();
    });

    function restaurarTotalesOriginales() {
        $('#tablaSNC225 tbody tr').each(function() {
            var $tr = $(this);
            var id = $tr.data('id');
            if (id && F.originales[id] !== undefined) {
                var val = F.originales[id];
                $tr.find('td').last().text('$' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $tr.find('td').last().attr('data-valor-dev', val);
            }
            $tr.find('td').eq(8).text($tr.data('tanos'));
            $tr.find('td').eq(9).text($tr.data('tmeses'));
            $tr.find('td').eq(10).text($tr.data('tdias'));
        });
    }

    function diffFechas(fechaDesde, fechaHasta) {
        var d1 = new Date(fechaDesde + 'T00:00:00');
        var d2 = new Date(fechaHasta + 'T23:59:59');
        if (d2 <= d1) return { a: '00', m: '00', d: '00' };
        var y = d2.getFullYear() - d1.getFullYear();
        var m = d2.getMonth() - d1.getMonth();
        var d = d2.getDate() - d1.getDate();
        if (d < 0) { m--; var tmp = new Date(d2.getFullYear(), d2.getMonth(), 0); d += tmp.getDate(); }
        if (m < 0) { y--; m += 12; }
        return { a: String(y).padStart(2, '0'), m: String(m).padStart(2, '0'), d: String(d).padStart(2, '0') };
    }

    function recalcularPorFecha(fechaStr) {
        if (!fechaStr) { restaurarTotalesOriginales(); return; }
        var fechaLimite = fechaStr;
        $('#tablaSNC225 tbody tr').each(function() {
            var $tr = $(this);
            var id = $tr.data('id');
            if (!id) return;
            var nominas = F.nominas[id];
            if (!nominas || !nominas.length) {
                $tr.find('td').last().text('$0.00');
                $tr.find('td').last().attr('data-valor-dev', 0);
            } else {
                var suma = 0;
                nominas.forEach(function(n) {
                    var ph = new Date(n.ph + 'T23:59:59');
                    if (ph <= new Date(fechaLimite + 'T23:59:59')) suma += n.dev;
                });
                $tr.find('td').last().text('$' + suma.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $tr.find('td').last().attr('data-valor-dev', suma);
            }
            var fechaAlta = $tr.data('fecha-alta');
            var fechaBaja = $tr.data('fecha-baja');
            if (fechaAlta && !fechaBaja) {
                var diff = diffFechas(fechaAlta, fechaLimite);
                $tr.find('td').eq(8).text(diff.a);
                $tr.find('td').eq(9).text(diff.m);
                $tr.find('td').eq(10).text(diff.d);
            }
        });
    }

    $('#filtroFechaCalculo').on('change', function() {
        recalcularPorFecha(this.value);
    });

    $('.snc-select-wrap select').on('mousedown', function() {
        $(this).closest('.snc-select-wrap').addClass('open');
    }).on('blur change', function() {
        var $wrap = $(this).closest('.snc-select-wrap');
        setTimeout(function() { $wrap.removeClass('open'); }, 150);
    });

    $('#sncFiltrosToggle').on('click', function() {
        var $body = $('#sncFiltrosBody');
        var $chevron = $('#sncFiltrosChevron');
        if ($body.is(':visible')) {
            $body.slideUp(200);
            $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            $body.slideDown(200);
            $chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });
});
</script>

<div class="modal fade" id="modalTarjeta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel); border: 0.0625rem solid rgba(96,165,250,0.3); border-radius: 1rem; color: #e2e8f0;">
            <div class="modal-header" style="border-bottom: 0.0625rem solid rgba(96,165,250,0.2); padding:1rem 1.5rem;">
                <h5 class="modal-title" style="font-weight: 600;">
                    <i class="fas fa-id-card me-2" style="color: #60a5fa;"></i>
                    <span id="tarjetaTitulo">Tarjeta SNC - 225</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="tarjetaBody" style="padding:1.5rem;">
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(96,165,250,0.2); padding:0.75rem 1.5rem;">
                <button type="button" class="btn-win btn-win-outline btn-win-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    </div>
                </div>
            </div>
            </div>
    </div>
</div>

<script>
var SNC_EMPRESA = <?php echo json_encode($config_empresa['nombre_empresa']); ?>;
var SNC_NIT = <?php echo json_encode($config_empresa['nit']); ?>;
var SNC_LOGO = <?php echo json_encode($logo_base64); ?>;
var SNC_JEFE = <?php echo json_encode($config_empresa['jefe_proyecto']); ?>;
var SNC_ESP_GESTION = <?php echo json_encode($config_empresa['especialista_gestion']); ?>;
var SNC_ESP_RRHH = <?php echo json_encode($config_empresa['especialista_gestionRRHH']); ?>;
var SNC_USUARIO = <?php echo json_encode($_SESSION['user_nombre'] ?? 'Usuario'); ?>;

var PRINT_TOOLBAR_HTML = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style><div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">'
    + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
    + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#16a34a\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#22c55e\';this.style.transform=\'translateY(0)\';">'
    + '🖨️ Imprimir</button>'
    + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#ef4444\';this.style.transform=\'translateY(0)\';">'
    + '✖ Cerrar</button>'
    + '</div><div style="height:3.4375rem;" class="no-print"></div>'
    + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';

function escHtmlSNC(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }

function formatFechaSNC(fecha) {
    if (!fecha || fecha === '0000-00-00' || fecha === '—') return '—';
    var parts = fecha.split('-');
    if (parts.length !== 3) return fecha;
    return parts[2] + '/' + parts[1] + '/' + parts[0];
}

function abrirTarjeta(data) {
    document.getElementById('tarjetaTitulo').textContent = 'Tarjeta SNC - 225 — PERSONAL DE: ' + (data.nombre_completo || '');
    var dev = (parseFloat(data.total_devengado) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    var tanos = data.t_anos || '—', tmeses = data.t_meses || '—', tdias = data.t_dias || '—';
    var $row = null;
    $('#tablaSNC225 tbody tr').each(function() { if ($(this).data('id') == data.id) { $row = $(this); } });
    if ($row) {
        var $lastTd = $row.find('td').last();
        if ($lastTd.attr('data-valor-dev') !== undefined) {
            dev = parseFloat($lastTd.attr('data-valor-dev')).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        tanos = $row.find('td').eq(8).text() || tanos;
        tmeses = $row.find('td').eq(9).text() || tmeses;
        tdias = $row.find('td').eq(10).text() || tdias;
    }
    var filtros = obtenerFiltrosActivos();
    var fc = '';
    var fcVal = $('#filtroFechaCalculo').val();
    if (fcVal) {
        var p = fcVal.split('-');
        var meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        fc = parseInt(p[2]) + '/' + meses[parseInt(p[1])] + '/' + p[0];
    }
    var html = '';
    html += '<div style="background:rgba(251,191,36,0.1); border:0.0625rem solid rgba(251,191,36,0.3); border-radius:0.5rem; padding:0.5rem 0.75rem; margin-bottom:0.75rem; font-size:0.78rem; color:#fbbf24;">';
    html += '<i class="fas fa-filter me-1"></i><strong>Filtros:</strong> ' + escHtmlSNC(filtros);
    if (fc) html += '<br><i class="fas fa-calendar-check me-1"></i><strong>Fecha Final de Cálculo:</strong> ' + fc;
	html += '<div class="snc-modal-nota"><i class="fas fa-info-circle me-1"></i>NOTA: El tiempo trabajado y salario devengado se calculan según la Fecha Final de Cálculo. Activos: desde inicio contrato hasta fecha filtro. Inactivos: desde inicio contrato hasta fecha de baja.</div>';
    html += '</div>';
    html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 1.5rem;">';
    html += '<p style="margin:0;"><strong>No. Exped.:</strong> <span style="color:#60a5fa;">' + escHtmlSNC(data.codigo || '') + '</span></p>';
    html += '<p style="margin:0;"><strong>C.I.:</strong> ' + escHtmlSNC(data.ci || '—') + '</p>';
    html += '<p style="margin:0; grid-column:1/-1;"><strong>Nombre y Apellidos:</strong> <span style="color:#c084fc; font-weight:600;">' + escHtmlSNC(data.nombre_completo || '') + '</span></p>';
    html += '<p style="margin:0;"><strong>C. Costo:</strong> ' + escHtmlSNC(data.c_costo || '—') + '</p>';
    html += '<p style="margin:0;"><strong>Área:</strong> ' + escHtmlSNC(data.area_nombre || '—') + '</p>';
    html += '<p style="margin:0;"><strong>F. Alta:</strong> ' + escHtmlSNC(formatFechaSNC(data.fecha_alta)) + '</p>';
    html += '<p style="margin:0;"><strong>F. Baja:</strong> ' + escHtmlSNC(formatFechaSNC(data.fecha_baja)) + '</p>';
    html += '</div>';
    html += '<div style="margin-top:0.75rem; padding:0.6rem 0.75rem; background:rgba(96,165,250,0.08); border:0.0625rem solid rgba(96,165,250,0.2); border-radius:0.5rem;">';
    html += '<div style="font-size:0.7rem; text-transform:uppercase; font-weight:600; color:#60a5fa; letter-spacing:0.05rem; margin-bottom:0.4rem;">Tiempo Trabajado</div>';
    html += '<div style="display:flex; gap:1.5rem;">';
    html += '<div style="text-align:center;"><div style="font-size:1.4rem; font-weight:bold; color:#e2e8f0;">' + escHtmlSNC(tanos) + '</div><div style="font-size:0.7rem; color:rgba(255,255,255,0.5);">AÑOS</div></div>';
    html += '<div style="text-align:center;"><div style="font-size:1.4rem; font-weight:bold; color:#e2e8f0;">' + escHtmlSNC(tmeses) + '</div><div style="font-size:0.7rem; color:rgba(255,255,255,0.5);">MESES</div></div>';
    html += '<div style="text-align:center;"><div style="font-size:1.4rem; font-weight:bold; color:#e2e8f0;">' + escHtmlSNC(tdias) + '</div><div style="font-size:0.7rem; color:rgba(255,255,255,0.5);">DÍAS</div></div>';
    html += '</div></div>';
    html += '<div style="margin-top:0.75rem; padding:0.6rem 0.75rem; background:rgba(52,211,153,0.08); border:0.0625rem solid rgba(52,211,153,0.2); border-radius:0.5rem; display:flex; justify-content:space-between; align-items:center;">';
    html += '<strong style="font-size:0.85rem;">Total Devengado</strong>';
    html += '<span style="font-size:1.15rem; font-weight:bold; color:#34d399; font-family:Consolas,monospace;">$' + dev + '</span>';
    html += '</div>';
    document.getElementById('tarjetaBody').innerHTML = html;
    var modal = new bootstrap.Modal(document.getElementById('modalTarjeta'));
    modal.show();
}

function nombreArchivoSNC(ext) {
    var f = new Date();
    return 'SNC225_' + f.getFullYear() + ('0'+(f.getMonth()+1)).slice(-2) + ('0'+f.getDate()).slice(-2) + '.' + ext;
}

function obtenerFechaHoraSNC() {
    var f = new Date();
    var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return f.getDate() + ' de ' + meses[f.getMonth()] + ' ' + f.getFullYear() + ', ' + ('0'+f.getHours()).slice(-2) + ':' + ('0'+f.getMinutes()).slice(-2);
}

function obtenerFechaCalculoLabel() {
    var v = $('#filtroFechaCalculo').val();
    if (!v) return '';
    var partes = v.split('-');
    var meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return parseInt(partes[2]) + '/' + meses[parseInt(partes[1])] + '/' + partes[0];
}

function sncFechaCalculoHtml() {
    var lbl = obtenerFechaCalculoLabel();
    if (!lbl) return '';
    return '<div class="sn-filtros" style="background:#fef3c7; border-color:#f59e0b; color:#92400e; font-weight:bold; margin-top:0.5rem;">FECHA FINAL DE CALCULO: ' + lbl + '</div>';
}

function sncFechaCalculoPdfLine() {
    var lbl = obtenerFechaCalculoLabel();
    if (!lbl) return '';
    return 'FECHA FINAL DE CALCULO: ' + lbl;
}

function sncFechaCalculoRow() {
    var lbl = obtenerFechaCalculoLabel();
    if (!lbl) return null;
    return ['FECHA FINAL DE CALCULO: ' + lbl];
}

// ============================================
// OBTENER FILTROS ACTIVOS
// ============================================
function obtenerFiltrosActivos() {
    var partes = [];
    var v;
    v = $('#filtroAltaAnio').val(); if (v) { var m = $('#filtroAltaMes').val(); partes.push('F. Alta: ' + v + (m ? '/' + m : '')); }
    v = $('#filtroBajaAnio').val(); if (v) { var m = $('#filtroBajaMes').val(); partes.push('F. Baja: ' + v + (m ? '/' + m : '')); }
    v = $('#filtroCosto').val(); if (v) partes.push('C. Costo: ' + v);
    v = $('#filtroArea').val(); if (v) partes.push('Área: ' + v);
    v = $('#filtroEstado').val(); if (v !== '') partes.push('Estado: ' + (v === '1' ? 'Activo' : 'Inactivo'));
    v = $('#customSearchSNC').val(); if (v) partes.push('Búsqueda: "' + v + '"');
    return partes.length ? 'Filtros: ' + partes.join(' | ') : 'Sin filtros (todos los registros)';
}

// ============================================
// OBTENER DATOS VISIBLES DE LA TABLA + TOTALES
// ============================================
function obtenerDatosTabla() {
    var table = $('#tablaSNC225').DataTable();
    var cols = ['No.', 'No. Exped.', 'C.I.', 'Nombre y Apellidos', 'C. Costo', 'F. Alta', 'F. Baja', 'Años', 'Meses', 'Días', 'Total Devengado'];
    var datos = [];
    var devNumerico = [];
    var totalDevengado = 0;
    table.rows({ search: 'applied' }).every(function() {
        var node = this.node();
        if (!$(node).is(':visible')) return;
        var $tds = $(node).find('td');
        var fila = [];
        var valDev = 0;
        $tds.each(function(i) {
            if (i === 0) return;
            var txt = $(this).text().trim();
            if (i === $tds.length - 1) {
                var customVal = $(this).attr('data-valor-dev');
                valDev = customVal !== undefined ? parseFloat(customVal) : (parseFloat(txt.replace(/[$,\s]/g, '')) || 0);
                totalDevengado += valDev;
                txt = '$' + valDev.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            fila.push(txt);
        });
        datos.push(fila);
        devNumerico.push(valDev);
    });
    return { columnas: cols, filas: datos, devNumerico: devNumerico, totalDevengado: totalDevengado };
}

// ============================================
// CSS COMÚN PARA IMPRESIÓN / WORD (Carta vertical)
// ============================================
function sncPrintCSS() {
    var css = '';
    css += '@page { size: letter portrait; margin: 12mm 14mm 18mm 14mm; }';
    css += 'body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #111; margin: 0; }';
    css += '.sn-cabecera { width: 100%; border-bottom: 0.125rem solid #004B87; padding-bottom: 0.5rem; margin-bottom: 0.75rem; }';
    css += '.sn-cabecera-logo { width: 3cm; text-align: left; vertical-align: middle; }';
    css += '.sn-cabecera-logo img { width: 3cm !important; height: 3cm !important; display: block; }';
    css += '.sn-cabecera-titulo { text-align: center; vertical-align: middle; }';
    css += '.sn-empresa { font-size: 14pt; font-weight: bold; color: #1f2937; }';
    css += '.sn-titulo { font-size: 12pt; font-weight: bold; color: #004B87; }';
    css += '.sn-alcance { font-size: 8pt; color: #444; }';
    css += '.sn-cabecera-datos { font-size: 7.5pt; text-align: right; vertical-align: middle; }';
    css += '.sn-tabla { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin: 0 auto; }';
    css += '.sn-tabla th { background: #004B87; color: #fff; border: 0.0625rem solid #004B87; padding: 3px 5px; text-align: center; text-transform: uppercase; font-size: 7pt; }';
    css += '.sn-tabla th.th-l { text-align: left; }';
    css += '.sn-tabla th.th-r { text-align: right; }';
    css += '.sn-tabla td { border: 0.0625rem solid #cbd5e1; padding: 2px 4px; }';
    css += '.sn-tabla td.c { text-align: center; }';
    css += '.sn-tabla td.r { text-align: right; font-family: Consolas, monospace; }';
    css += '.sn-tabla td.l { text-align: left; }';
    css += '.sn-tabla tbody tr:nth-child(even) td { background: #f1f5f9; }';
    css += '.sn-tabla .total-row td { background: #e2e8f0 !important; font-weight: bold; border-top: 0.125rem solid #004B87; }';
    css += '.sn-filtros { font-size: 7pt; color: #666; margin-bottom: 0.5rem; padding: 4px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; }';
    css += '.sn-firmas { margin-top: 2rem; page-break-inside: avoid; }';
    css += '.sn-firmas-tabla { width: 100%; border-collapse: collapse; }';
    css += '.sn-firmas-tabla td { text-align: center; vertical-align: top; padding: 0.3125rem; width: 33%; }';
    css += '.sn-firma-label { font-size: 8pt; font-weight: bold; margin: 0 0 2.5rem; }';
    css += '.sn-firma-linea { border-bottom: 0.0625rem solid #000; margin: 0 0.625rem; }';
    css += '.sn-firma-cargo { font-size: 9pt; font-weight: bold; margin: 0.25rem 0 0; }';
    css += '.sn-firma-subcargo { font-size: 7pt; color: #444; margin: 0; }';
    css += '.sn-pie { position: fixed; bottom: 5mm; left: 14mm; right: 14mm; font-size: 7pt; color: #888; border-top: 0.0625rem solid #ddd; padding-top: 3px; display: flex; justify-content: space-between; }';
    css += '@media print { .no-print { display: none !important; } }';
    return css;
}

// ============================================
// CONSTRUIR TABLA HTML (centrado + nombre izq + total der)
// ============================================
function sncTablaHtml(data) {
    var html = '<table class="sn-tabla"><thead><tr>';
    var numCols = data.columnas.length;
    data.columnas.forEach(function(c, i) {
        var cls = '';
        if (i === 3) cls = ' class="th-l"';
        else if (i === numCols - 1) cls = ' class="th-r"';
        html += '<th' + cls + '>' + c + '</th>';
    });
    html += '</tr></thead><tbody>';
    data.filas.forEach(function(fila) {
        html += '<tr>';
        fila.forEach(function(c, i) {
            var cls = '';
            if (i === 3) cls = 'l';
            else if (i === numCols - 1) cls = 'r';
            else cls = 'c';
            html += '<td class="' + cls + '">' + escHtmlSNC(c) + '</td>';
        });
        html += '</tr>';
    });
    html += '<tr class="total-row">';
    html += '<td class="c" colspan="' + (numCols - 1) + '">TOTAL (' + data.filas.length + ' registros)</td>';
    html += '<td class="r">$' + data.totalDevengado.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>';
    html += '</tr></tbody></table>';
    return html;
}

// ============================================
// FIRMAS HTML
// ============================================
function sncFirmasHtml() {
    return '<div class="sn-firmas"><table class="sn-firmas-tabla"><tr>'
        + '<td><p class="sn-firma-label">Elaborado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_ESP_RRHH || SNC_USUARIO) + '</p><p class="sn-firma-subcargo">Especialista de Recursos Humanos</p></td>'
        + '<td><p class="sn-firma-label">Revisado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_ESP_GESTION) + '</p><p class="sn-firma-subcargo">Especialista en Gestión Económica</p></td>'
        + '<td><p class="sn-firma-label">Aprobado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_JEFE) + '</p><p class="sn-firma-subcargo">Director de Proyecto</p></td>'
        + '</tr></table></div>';
}

// ============================================
// CONSTRUIR HTML COMPLETO (para impresión y Word)
// ============================================
function construirHtmlSNC(modo) {
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return null; }
    var paraWord = modo === 'word';
    var filtros = obtenerFiltrosActivos();
    var html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>SNC-225 - Certificación de Años de Servicios</title>';
    if (paraWord) html += '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->';
    html += '<style>';
    if (paraWord) {
        html += '@page WordSection1 { size: 612pt 792pt; mso-page-orientation: portrait; margin: 0.5in 0.55in 0.7in 0.55in; } div.WordSection1 { page: WordSection1; }';
    }
    html += sncPrintCSS();
    html += '</style></head><body>';
    if (paraWord) html += '<div class="WordSection1">';
    html += '<table class="sn-cabecera" cellspacing="0" cellpadding="0"><tr>';
    if (SNC_LOGO) html += '<td class="sn-cabecera-logo"><img src="' + SNC_LOGO + '" alt="Logo"></td>';
    html += '<td class="sn-cabecera-titulo"><div class="sn-empresa">' + escHtmlSNC(SNC_EMPRESA) + '</div><div class="sn-titulo">Resumen General De Salario Devengado</div><div class="sn-alcance">Certificación de Años de Servicios y Salarios Devengados</div></td>';
    html += '<td class="sn-cabecera-datos"><strong>Emisión:</strong> ' + obtenerFechaHoraSNC() + '<br><strong>NIT:</strong> ' + escHtmlSNC(SNC_NIT) + '<br><strong>Registros:</strong> ' + data.filas.length + '</td>';
    html += '</tr></table>';
    html += '<div class="sn-filtros">' + filtros + '</div>';
    html += sncFechaCalculoHtml();
    html += sncTablaHtml(data);
    html += sncFirmasHtml();
    if (paraWord) html += '</div>';
    html += '</body></html>';
    return html;
}

// ============================================
// IMPRIMIR (con toolbar auto-hide)
// ============================================
document.getElementById('btnPrintSNC')?.addEventListener('click', function() {
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return; }
    var filtros = obtenerFiltrosActivos();
    var contenido = '<table class="sn-cabecera" cellspacing="0" cellpadding="0"><tr>';
    if (SNC_LOGO) contenido += '<td class="sn-cabecera-logo"><img src="' + SNC_LOGO + '" alt="Logo"></td>';
    contenido += '<td class="sn-cabecera-titulo"><div class="sn-empresa">' + escHtmlSNC(SNC_EMPRESA) + '</div><div class="sn-titulo">Resumen General De Salario Devengado</div><div class="sn-alcance">Certificación de Años de Servicios y Salarios Devengados</div></td>';
    contenido += '<td class="sn-cabecera-datos"><strong>Emisión:</strong> ' + obtenerFechaHoraSNC() + '<br><strong>NIT:</strong> ' + escHtmlSNC(SNC_NIT) + '<br><strong>Registros:</strong> ' + data.filas.length + '</td>';
    contenido += '</tr></table>';
    contenido += '<div class="sn-filtros">' + filtros + '</div>';
    contenido += sncFechaCalculoHtml();
    contenido += sncTablaHtml(data);
    contenido += sncFirmasHtml();
    var win = window.open('', '_blank');
    if (!win) { Swal.fire({ icon: 'warning', title: 'Aviso', text: 'El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes.' }); return; }
    win.document.open();
    win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>SNC-225</title><style>' + sncPrintCSS() + '</style></head><body>' + PRINT_TOOLBAR_HTML + contenido + '</body></html>');
    win.document.close();
});

// ============================================
// EXPORTAR A WORD
// ============================================
document.getElementById('btnExportWordSNC')?.addEventListener('click', function(e) {
    e.preventDefault();
    var html = construirHtmlSNC('word');
    if (!html) return;
    var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nombreArchivoSNC('doc');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
});

// ============================================
// EXPORTAR A PDF (Carta vertical con autotable)
// ============================================
document.getElementById('btnExportPdfSNC')?.addEventListener('click', function(e) {
    e.preventDefault();
    exportarPdfSNC();
});
function exportarPdfSNC() {
    if (typeof window.jspdf === 'undefined') { Swal.fire({ icon: 'error', title: 'Error', text: 'Las librerías de PDF no están disponibles.' }); return; }
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return; }
    Swal.fire({ title: 'Generando PDF...', text: 'Espere unos segundos.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        var { jsPDF } = window.jspdf;
        var doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
        var pageW = doc.internal.pageSize.getWidth();
        var pageH = doc.internal.pageSize.getHeight();
        var margenL = 14, margenR = 14, margenSup = 14, margenInf = 18;
        var anchoUtil = pageW - margenL - margenR;
        var y = margenSup;

        if (SNC_LOGO) { try { doc.addImage(SNC_LOGO, 'PNG', margenL, y, 16, 16); } catch(e) {} }
        doc.setFontSize(14); doc.setFont('helvetica', 'bold'); doc.setTextColor(31, 41, 55);
        doc.text(SNC_EMPRESA, pageW / 2, y + 6, { align: 'center' });
        doc.setFontSize(11); doc.setTextColor(0, 75, 135);
        doc.text('Resumen General De Salario Devengado', pageW / 2, y + 12, { align: 'center' });
        doc.setFontSize(8); doc.setTextColor(80);
        doc.text('Certificación de Años de Servicios y Salarios Devengados', pageW / 2, y + 16, { align: 'center' });
        doc.setFontSize(7); doc.setTextColor(100);
        doc.text('NIT: ' + SNC_NIT + '  |  Emisión: ' + obtenerFechaHoraSNC() + '  |  Registros: ' + data.filas.length, pageW / 2, y + 20, { align: 'center' });
        doc.setDrawColor(0, 75, 135); doc.setLineWidth(0.4);
        doc.line(margenL, y + 22, pageW - margenR, y + 22);
        y += 25;

        doc.setFontSize(6.5); doc.setTextColor(120);
        doc.text(obtenerFiltrosActivos(), margenL, y);
        y += 4;
        var fechaCalcPdf = sncFechaCalculoPdfLine();
        if (fechaCalcPdf) { doc.setFontSize(7); doc.setFont('helvetica', 'bold'); doc.setTextColor(146, 64, 14); doc.text(fechaCalcPdf, margenL, y); y += 4; }

        var head = [data.columnas];
        var body = data.filas.map(function(f) { return f; });
        body.push(data.columnas.slice(0, -1).concat(['TOTAL: $' + data.totalDevengado.toLocaleString('en-US', {minimumFractionDigits:2})]));

        doc.autoTable({
            startY: y,
            head: head,
            body: body,
            styles: { fontSize: 7, cellPadding: 1.5, overflow: 'linebreak', font: 'helvetica' },
            headStyles: { fillColor: [0, 75, 135], textColor: 255, fontStyle: 'bold', halign: 'center', fontSize: 6.5 },
            alternateRowStyles: { fillColor: [241, 245, 249] },
            columnStyles: {
                0: { halign: 'center', cellWidth: 8 },
                1: { halign: 'center', cellWidth: 18 },
                2: { halign: 'center', cellWidth: 20 },
                3: { halign: 'left', cellWidth: 'auto' },
                4: { halign: 'center', cellWidth: 16 },
                5: { halign: 'center', cellWidth: 18 },
                6: { halign: 'center', cellWidth: 18 },
                7: { halign: 'center', cellWidth: 10 },
                8: { halign: 'center', cellWidth: 10 },
                9: { halign: 'center', cellWidth: 10 },
                10: { halign: 'right', cellWidth: 24, fontStyle: 'bold' }
            },
            margin: { left: margenL, right: margenR },
            didParseCell: function(d) {
                if (d.section === 'body' && d.row.index === body.length - 1) {
                    d.cell.styles.fontStyle = 'bold';
                    d.cell.styles.fillColor = [226, 232, 240];
                    if (d.column.index === data.columnas.length - 1) { d.cell.styles.halign = 'right'; }
                    else { d.cell.styles.halign = 'center'; }
                }
            },
            didDrawPage: function() {
                doc.setFontSize(6); doc.setTextColor(150);
                doc.text('SNC-225 / ' + SNC_EMPRESA + '  —  Página ' + doc.internal.getCurrentPageInfo().pageNumber, margenL, pageH - 8);
                doc.setDrawColor(200); doc.setLineWidth(0.2);
                doc.line(margenL, pageH - 10, pageW - margenR, pageH - 10);
            }
        });

        var lastY = doc.lastAutoTable.finalY + 8;
        var firmasY = pageH - margenInf - 28;
        if (lastY + 30 > firmasY) { doc.addPage(); firmasY = margenSup + 10; }
        var colW = anchoUtil / 3;
        var firmas = [
            { label: 'Elaborado por:', nombre: SNC_ESP_RRHH || SNC_USUARIO, cargo: 'Especialista de Recursos Humanos' },
            { label: 'Revisado por:', nombre: SNC_ESP_GESTION, cargo: 'Especialista en Gestión Económica' },
            { label: 'Aprobado por:', nombre: SNC_JEFE, cargo: 'Director de Proyecto' }
        ];
        firmas.forEach(function(f, i) {
            var cx = margenL + (colW * i) + (colW / 2);
            doc.setFontSize(7); doc.setFont('helvetica', 'bold'); doc.setTextColor(0);
            doc.text(f.label, cx, firmasY, { align: 'center' });
            doc.setDrawColor(0); doc.setLineWidth(0.2);
            doc.line(cx - 20, firmasY + 20, cx + 20, firmasY + 20);
            doc.setFontSize(7); doc.setFont('helvetica', 'bold');
            doc.text(f.nombre, cx, firmasY + 24, { align: 'center' });
            doc.setFontSize(6); doc.setFont('helvetica', 'normal'); doc.setTextColor(80);
            doc.text(f.cargo, cx, firmasY + 28, { align: 'center' });
        });

        doc.save(nombreArchivoSNC('pdf'));
        Swal.close();
        Swal.fire({ icon: 'success', title: 'PDF generado', text: 'El archivo se ha descargado correctamente.', timer: 2000, showConfirmButton: false });
    } catch(err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + err.message });
    }
}

// ============================================
// EXPORTAR A EXCEL (con totales y filtros)
// ============================================
document.getElementById('btnExportExcelSNC')?.addEventListener('click', function(e) {
    e.preventDefault();
    exportarExcelSNC();
});
function exportarExcelSNC() {
    if (typeof XLSX === 'undefined') { Swal.fire({ icon: 'error', title: 'Error', text: 'La librería de Excel no cargó correctamente.' }); return; }
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return; }
    var filtros = obtenerFiltrosActivos();
    var colIdx = data.columnas.length - 1;
    var filas = [];
    filas.push([SNC_EMPRESA]);
    filas.push(['Resumen General De Salario Devengado - Certificación de Años de Servicios y Salarios Devengados']);
    filas.push(['NIT: ' + SNC_NIT]);
    filas.push(['Generado: ' + obtenerFechaHoraSNC(), 'Registros: ' + data.filas.length]);
    filas.push([filtros]);
    var fcRow = sncFechaCalculoRow();
    if (fcRow) filas.push(fcRow);
    filas.push([]);
    filas.push(data.columnas);
    data.filas.forEach(function(f, idx) {
        var row = f.slice(0, colIdx);
        row.push(data.devNumerico[idx]);
        filas.push(row);
    });
    var totalRow = data.columnas.slice(0, colIdx).map(function() { return ''; });
    totalRow.push(data.totalDevengado);
    filas.push(totalRow);
    filas.push([]);
    filas.push(['Elaborado por:', SNC_ESP_RRHH || SNC_USUARIO, '', '', '', '', '', '', '', '', 'Especialista de Recursos Humanos']);
    filas.push(['Revisado por:', SNC_ESP_GESTION, '', '', '', '', '', '', '', '', 'Especialista en Gestión Económica']);
    filas.push(['Aprobado por:', SNC_JEFE, '', '', '', '', '', '', '', '', 'Director de Proyecto']);
    var ws = XLSX.utils.aoa_to_sheet(filas);
    ws['!cols'] = [{wch:5},{wch:12},{wch:14},{wch:32},{wch:10},{wch:12},{wch:12},{wch:7},{wch:7},{wch:7},{wch:18}];
    for (var r = 6; r < 6 + data.filas.length + 1; r++) {
        var addr = XLSX.utils.encode_cell({ r: r, c: colIdx });
        if (ws[addr]) { ws[addr].t = 'n'; ws[addr].z = '$#,##0.00'; }
    }
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'SNC-225');
    XLSX.writeFile(wb, nombreArchivoSNC('xlsx'));
}

// ============================================
// EXPORTAR A CSV (con totales)
// ============================================
document.getElementById('btnExportCsvSNC')?.addEventListener('click', function(e) {
    e.preventDefault();
    exportarCsvSNC();
});
function exportarCsvSNC() {
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return; }
    var sep = ';';
    var csv = data.columnas.join(sep) + '\n';
    data.filas.forEach(function(fila) {
        csv += fila.map(function(c) { return '"' + c.replace(/"/g, '""') + '"'; }).join(sep) + '\n';
    });
    csv += data.columnas.slice(0, -1).concat(['TOTAL: $' + data.totalDevengado.toLocaleString('en-US', {minimumFractionDigits:2})]).join(sep) + '\n';
    csv += '\n' + obtenerFiltrosActivos() + '\n';
    var fcCsv = sncFechaCalculoPdfLine();
    if (fcCsv) csv += fcCsv + '\n';
    csv += 'Elaborado por: ' + (SNC_ESP_RRHH || SNC_USUARIO) + ' (Especialista de Recursos Humanos)\n';
    csv += 'Revisado por: ' + SNC_ESP_GESTION + ' (Especialista en Gestión Económica)\n';
    csv += 'Aprobado por: ' + SNC_JEFE + ' (Director de Proyecto)\n';
    var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nombreArchivoSNC('csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

// ============================================
// EXPORTAR A TXT (con totales)
// ============================================
document.getElementById('btnExportTxtSNC')?.addEventListener('click', function(e) {
    e.preventDefault();
    exportarTxtSNC();
});
function exportarTxtSNC() {
    var data = obtenerDatosTabla();
    if (!data.filas.length) { Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para exportar.' }); return; }
    var sep = ' | ';
    var txt = SNC_EMPRESA + '\n';
    txt += 'Resumen General De Salario Devengado - Certificación de Años de Servicios y Salarios Devengados\n';
    txt += 'NIT: ' + SNC_NIT + '\n';
    txt += 'Emisión: ' + obtenerFechaHoraSNC() + '\n';
    txt += obtenerFiltrosActivos() + '\n';
    var fcTxt = sncFechaCalculoPdfLine();
    if (fcTxt) txt += fcTxt + '\n';
    txt += data.columnas.join(sep) + '\n';
    txt += data.columnas.map(function() { return '----------'; }).join(sep) + '\n';
    data.filas.forEach(function(fila) { txt += fila.join(sep) + '\n'; });
    txt += data.columnas.slice(0, -1).concat(['TOTAL: $' + data.totalDevengado.toLocaleString('en-US', {minimumFractionDigits:2})]).join(sep) + '\n';
    txt += '\nElaborado por: ' + (SNC_ESP_RRHH || SNC_USUARIO) + ' (Especialista de Recursos Humanos)\n';
    txt += 'Revisado por: ' + SNC_ESP_GESTION + ' (Especialista en Gestión Económica)\n';
    txt += 'Aprobado por: ' + SNC_JEFE + ' (Director de Proyecto)\n';
    var blob = new Blob([txt], { type: 'text/plain;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nombreArchivoSNC('txt');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
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
