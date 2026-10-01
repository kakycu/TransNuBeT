<?php
// modules/domiciliacion_tarjetas.php - Domiciliacion de tarjetas para el banco
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/domiciliacion_dbf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Acceso restringido: Administrador (Admin), Contador/Editor (Editor) y Programador (Soft).
if (!permiso_puede('domiciliacion_tarjetas', 'ver')) {
    permiso_denegar_acceso('Domiciliación de Tarjetas');
}

if (!permiso_puede('empleados', 'ver')) {
    permiso_denegar_acceso('Domiciliacion de Tarjetas');
}

$puede_exportar = permiso_puede('domiciliacion_tarjetas', 'exportar');

$stmt = $pdo->prepare("
    SELECT t.id, t.codigo, t.ci, t.nombres, t.primer_apellido, t.segundo_apellido,
           t.fecha_alta, t.fecha_baja, t.activo, t.cuentabanc, t.area_id, t.centro_costo_id,
           cc.codigo AS centro_costo,
           COALESCE(cc.nombre, cc.descripcion) AS centro_costo_nombre,
           a.nombre_area AS area
    FROM trabajadores t
    LEFT JOIN centros_costo cc ON cc.id = t.centro_costo_id
    LEFT JOIN areas a ON a.id = t.area_id
    ORDER BY t.codigo ASC
");
$stmt->execute();
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$listaAreas = $pdo->query("SELECT id, nombre_area FROM areas ORDER BY nombre_area ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaCentros = $pdo->query("SELECT id, codigo, COALESCE(nombre, descripcion) AS nombre FROM centros_costo ORDER BY codigo ASC")->fetchAll(PDO::FETCH_ASSOC);

$lista = [];
$totalValidos   = 0;
$totalErroneos = 0;
$totalSinCuenta = 0;
$totalConCuenta = 0;

foreach ($trabajadores as $t) {
    $errores = domiciliacion_validar_trabajador($t);
    $baja = (!empty($t['fecha_baja']) && $t['fecha_baja'] !== '0000-00-00');
    $cuentabanc = trim((string)($t['cuentabanc'] ?? ''));
    $tieneCuenta = ($cuentabanc !== '');

    $t['errores']    = $errores;
    $t['es_valido']  = empty($errores);
    $t['es_baja']    = $baja;
    $t['tiene_cuenta'] = $tieneCuenta;
    $t['cuentabanc']  = $cuentabanc;
    $t['area']        = (string)($t['area'] ?? '');
    $t['area_id']     = (int)($t['area_id'] ?? 0);
    $t['centro_costo_id'] = (int)($t['centro_costo_id'] ?? 0);
    $t['centro_costo_nombre'] = (string)($t['centro_costo_nombre'] ?? '');
    $t['dbf_nombre'] = domiciliacion_abreviar_nombres($t['nombres']);
    $t['dbf_completo'] = trim($t['nombres'] . ' ' . $t['primer_apellido'] . ' ' . $t['segundo_apellido']);

    if ($t['es_valido']) $totalValidos++; else $totalErroneos++;
    if ($tieneCuenta) $totalConCuenta++; else $totalSinCuenta++;
    $lista[] = $t;
}

$totalGeneral = count($lista);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SisGesNom'; ?> | Domiciliacion de Tarjetas</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
<link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
<link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/sweetalert2.min.css">

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
            position: relative; overflow: hidden;
        }
        .glass-card:hover { transform: translateY(-0.125rem); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }
        .glass-card .card-icon-bg { position: absolute; right:-0.625rem; bottom:-0.625rem; font-size:5rem; opacity: 0.08; pointer-events: none; z-index: 0; transform: rotate(-8deg); }
        .glass-card .card-content { position: relative; z-index: 1; }

        .btn-win {
            background: rgba(96, 165, 250, 0.15);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            color: #60a5fa;
            padding:0.5rem 1rem; border-radius: 0.625rem; font-size:0.82rem; font-weight: 500;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-win:hover { background: rgba(96, 165, 250, 0.25); border-color: rgba(96, 165, 250, 0.5); color: #ffffff; }
        .btn-win-primary { background: rgba(59, 130, 246, 0.25); border-color: rgba(59, 130, 246, 0.4); color: #60a5fa; }
        .btn-win-primary:hover { background: rgba(59, 130, 246, 0.4); color: #ffffff; }
        .btn-win-success { background: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.45); color: #4ade80; }
        .btn-win-success:hover { background: rgba(34, 197, 94, 0.35); color: #ffffff; }
        .btn-win-outline { background: transparent; border-color: rgba(96, 165, 250, 0.3); color: #60a5fa; }
        .btn-win-outline:hover { background: rgba(96, 165, 250, 0.15); }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }
        .btn-win:disabled { opacity:0.4; cursor: not-allowed; }
        .btn-win:disabled:hover { background: rgba(96, 165, 250, 0.15); border-color: rgba(96, 165, 250, 0.3); color: #60a5fa; }

        table { width:100%; border-collapse: collapse; }
        thead th {
            background: rgba(0, 120, 212, 0.25); color: #93c5fd; padding: 0.625rem 0.5rem;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.0312rem;
            border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.3); text-align: left; white-space: nowrap;
        }
        tbody td { padding: 0.5rem; font-size: 0.8rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.04); vertical-align: middle; }
        tbody tr { transition: background 0.2s; }

        th.th-orden { cursor: pointer; user-select: none; transition: background 0.2s, color 0.2s; }
        th.th-orden:hover { background: rgba(96, 165, 250, 0.35); color: #dbeafe; }
        th.th-orden .th-caret { opacity: 0.4; font-size: 0.65rem; transition: opacity 0.2s; }
        th.th-orden.asc .th-caret { opacity: 1; color: #4ade80; }
        th.th-orden.desc .th-caret { opacity: 1; color: #fbbf24; }
        th.th-orden.asc, th.th-orden.desc { background: rgba(96, 165, 250, 0.32); color: #e2e8f0; }
        tbody tr:hover { background: rgba(96, 165, 250, 0.06); }
        tbody tr.fila-error { background: rgba(239, 68, 68, 0.05); }
        tbody tr.fila-error:hover { background: rgba(239, 68, 68, 0.1); }
        tbody tr.fila-seleccionada { background: rgba(34, 197, 94, 0.09); }
        tbody tr.fila-seleccionada:hover { background: rgba(34, 197, 94, 0.14); }

        .chk-trabajador { width:1.05rem; height:1.05rem; cursor: pointer; accent-color: #22c55e; }
        .chk-trabajador:disabled { cursor: not-allowed; opacity:0.35; }

        .chk-filtro {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.375rem 0.75rem; font-size: 0.82rem; font-weight: 500;
            color: var(--txt); background: var(--panel);
            border: 0.0625rem solid rgba(255, 255, 255, 0.15);
            border-radius: 0.5rem; cursor: pointer; white-space: nowrap;
            transition: all 0.2s;
        }
        .chk-filtro:hover { border-color: rgba(34, 197, 94, 0.5); color: #4ade80; }
        .chk-filtro i { font-size: 0.72rem; color: #4ade80; }
        .chk-filtro:has(input:checked) {
            border-color: rgba(34, 197, 94, 0.55);
            background: rgba(34, 197, 94, 0.12);
            color: #4ade80;
        }
        html[data-theme="light"] .chk-filtro { border-color: rgba(0, 0, 0, 0.2); }

        .badge-mini {
            display: inline-flex; align-items: center; gap:0.25rem;
            padding:0.125rem 0.5rem; border-radius: 1.25rem; font-size:0.6rem; font-weight: 600;
        }
        .badge-mini.ok { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .badge-mini.fail { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-mini.neutral { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.6); }

        .codigo-mono { font-family: 'Consolas', monospace; font-weight: 600; color: #93c5fd; }
        .valor-dbf { font-family: 'Consolas', monospace; font-size: 0.75rem; color: #34d399; }
        .campo-dbf {
            background: rgba(52, 211, 153, 0.08); border: 0.0625rem solid rgba(52, 211, 153, 0.2);
            border-radius: 0.375rem; padding: 0.125rem 0.375rem; display: inline-block; max-width: 100%;
        }

        .search-box {
            background: var(--panel); color: var(--txt);
            border: 0.0625rem solid rgba(255, 255, 255, 0.15); border-radius: 0.5rem;
            padding: 0.375rem 0.75rem; font-size: 0.82rem; color-scheme: dark;
        }
        .search-box:focus { outline: none; border-color: rgba(96, 165, 250, 0.5); }

        .fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        @keyframes fadeInUp { from { opacity:0; transform: translateY(0.75rem); } to { opacity:1; transform: translateY(0); } }

        .seleccion-bar {
            background: rgba(34, 197, 94, 0.12); border: 0.0625rem solid rgba(34, 197, 94, 0.3);
            border-radius: 0.75rem; padding: 0.75rem 1rem;
        }
        .contador-sel { font-size: 1.35rem; font-weight: 700; color: #4ade80; line-height: 1.2; }

        .btn-plantilla-dbf {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.5rem 1.1rem; border-radius: 0.625rem;
            font-size: 0.85rem; font-weight: 600;
            color: #93c5fd; background: rgba(37, 99, 235, 0.12);
            border: 0.0625rem dashed rgba(96, 165, 250, 0.5);
            transition: all 0.2s;
        }
        .btn-plantilla-dbf:hover, .btn-plantilla-dbf:focus {
            color: #ffffff; background: rgba(37, 99, 235, 0.3);
            border-color: #60a5fa; border-style: solid;
        }
        .btn-plantilla-dbf i { color: inherit; }

        .btn-abrir-carpeta {
            display: inline-flex; align-items: center; justify-content: center;
            margin-left: 0.5rem; padding: 0.25rem 0.625rem; border-radius: 0.5rem;
            font-size: 0.75rem; font-weight: 600; text-decoration: none;
            color: #93c5fd; background: rgba(37, 99, 235, 0.14);
            border: 0.0625rem solid rgba(96, 165, 250, 0.4);
            vertical-align: middle; transition: all 0.2s; white-space: nowrap;
        }
        .btn-abrir-carpeta i { color: inherit; font-size: 0.78rem; }
        .btn-abrir-carpeta:hover, .btn-abrir-carpeta:focus {
            color: #ffffff; background: rgba(37, 99, 235, 0.32); border-color: #60a5fa;
        }

        .btn-descarga-dbf,
        .swal2-popup a.btn-descarga-dbf {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.5rem 1.25rem; border-radius: 0.625rem;
            font-size: 0.85rem; font-weight: 600; text-decoration: none;
            color: #ffffff !important;
            background-color: #3b4fd8 !important;
            background-image: linear-gradient(135deg, #1d4ed8, #6d28d9) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.45) !important;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.45);
            transition: all 0.2s;
            text-shadow: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.5);
        }
        .btn-descarga-dbf i, .swal2-popup a.btn-descarga-dbf i { color: #ffffff !important; }
        .btn-descarga-dbf:hover, .btn-descarga-dbf:focus,
        .swal2-popup a.btn-descarga-dbf:hover, .swal2-popup a.btn-descarga-dbf:focus {
            color: #ffffff !important; filter: brightness(1.2); transform: translateY(-0.0625rem);
        }

        /* ---------- DataTables: combo "Mostrar" y paginacion ---------- */
        .dataTables_wrapper .dataTables_length {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
            color: var(--muted); font-size: 0.85rem; font-weight: 500;
        }
        .dataTables_wrapper .dataTables_length label { color: var(--muted); margin: 0; }
        .dataTables_wrapper .dataTables_length select {
            background-color: var(--panel);
            color: var(--txt);
            border: 0.0625rem solid rgba(255, 255, 255, 0.15);
            border-radius: 0.5rem;
            padding: 0.375rem 1.875rem 0.375rem 0.625rem;
            font-size: 0.82rem;
            min-width: 5.5rem;
            cursor: pointer;
        }
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 0.125rem rgba(var(--accent-rgb), 0.2);
        }
        html[data-theme="light"] .dataTables_wrapper .dataTables_length select {
            border: 0.0625rem solid rgba(0, 0, 0, 0.2);
        }
        html[data-theme="light"] .dataTables_wrapper .dataTables_length label { color: #4b5563; }

        .dataTables_wrapper .dataTables_info {
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 500;
            padding-top: 0.625rem;
        }
        .dataTables_wrapper .dataTables_paginate {
            display: flex; align-items: center; flex-wrap: wrap; gap: 0.25rem;
            padding-top: 0.625rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--txt) !important;
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 0.5rem !important;
            padding: 0.375rem 0.75rem !important;
            margin: 0 0.125rem !important;
            min-width: 2.25rem;
            font-size: 0.82rem;
            font-weight: 500 !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(var(--accent-rgb), 0.25) !important;
            border-color: var(--accent) !important;
            color: #ffffff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark)) !important;
            border-color: var(--accent) !important;
            color: #ffffff !important;
            box-shadow: 0 0.1875rem 0.5rem rgba(0, 0, 0, 0.3) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: rgba(255, 255, 255, 0.35) !important;
            background: var(--panel-2) !important;
            border-color: rgba(255, 255, 255, 0.06) !important;
            cursor: not-allowed;
        }
        html[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 0.0625rem solid rgba(0, 0, 0, 0.18) !important;
        }
        html[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            color: rgba(0, 0, 0, 0.3) !important;
            background: #f1f5f9 !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
        }
        html[data-theme="light"] .dataTables_wrapper .dataTables_info { color: #4b5563; }

        .dataTables_wrapper .empty-table td { padding: 1.5rem; text-align: center; }
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
                <h1><i class="fas fa-credit-card me-2" style="color: #60a5fa;"></i>Domiciliación de Tarjetas</h1>
                <p><i class="fas fa-list-alt me-1"></i> Marque los trabajadores y genere la base de datos para el banco</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <div class="glass-card fade-in-up p-0" style="animation-delay: 0.05s;">
        <div class="card-content p-4">

            <div class="d-flex flex-wrap gap-3 mb-4">
                <div style="flex:1; min-width:8.75rem; background: linear-gradient(135deg, rgba(96,165,250,0.12), rgba(96,165,250,0.04)); border: 0.0625rem solid rgba(96,165,250,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display:flex; align-items:center; gap:0.875rem;">
                    <div style="width:2.625rem; height:2.625rem; border-radius:0.625rem; background:rgba(96,165,250,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-users" style="color:#60a5fa; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Total</div>
                        <div style="font-size:1.4rem; font-weight:700; color:#60a5fa; line-height:1.2;"><?php echo $totalGeneral; ?></div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.4);">trabajador(es)</div>
                    </div>
                </div>
                <div style="flex:1; min-width:8.75rem; background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(34,197,94,0.04)); border: 0.0625rem solid rgba(34,197,94,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display:flex; align-items:center; gap:0.875rem;">
                    <div style="width:2.625rem; height:2.625rem; border-radius:0.625rem; background:rgba(34,197,94,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-user-check" style="color:#22c55e; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Listos</div>
                        <div style="font-size:1.4rem; font-weight:700; color:#22c55e; line-height:1.2;"><?php echo $totalValidos; ?></div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.4);">datos completos</div>
                    </div>
                </div>
                <div style="flex:1; min-width:8.75rem; background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.04)); border: 0.0625rem solid rgba(239,68,68,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display:flex; align-items:center; gap:0.875rem;">
                    <div style="width:2.625rem; height:2.625rem; border-radius:0.625rem; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-triangle-exclamation" style="color:#ef4444; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Inconsistencias</div>
                        <div style="font-size:1.4rem; font-weight:700; color:#ef4444; line-height:1.2;"><?php echo $totalErroneos; ?></div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.4);">no exportables</div>
                    </div>
                </div>
                <div style="flex:1; min-width:8.75rem; background: linear-gradient(135deg, rgba(192,132,252,0.12), rgba(192,132,252,0.04)); border: 0.0625rem solid rgba(192,132,252,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display:flex; align-items:center; gap:0.875rem;">
                    <div style="width:2.625rem; height:2.625rem; border-radius:0.625rem; background:rgba(192,132,252,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-building-columns" style="color:#c084fc; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Con cuenta</div>
                        <div style="font-size:1.4rem; font-weight:700; color:#c084fc; line-height:1.2;"><?php echo $totalConCuenta; ?></div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.4);">con cuenta bancaria</div>
                    </div>
                </div>
                <div style="flex:1; min-width:8.75rem; background: linear-gradient(135deg, rgba(251,191,36,0.12), rgba(251,191,36,0.04)); border: 0.0625rem solid rgba(251,191,36,0.2); border-radius: 0.625rem; padding:0.875rem 1.125rem; display:flex; align-items:center; gap:0.875rem;">
                    <div style="width:2.625rem; height:2.625rem; border-radius:0.625rem; background:rgba(251,191,36,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-money-bill-wave" style="color:#fbbf24; font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Sin cuenta</div>
                        <div style="font-size:1.4rem; font-weight:700; color:#fbbf24; line-height:1.2;"><?php echo $totalSinCuenta; ?></div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.4);">sin cuenta bancaria</div>
                    </div>
                </div>
            </div>

            <div class="seleccion-bar mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Seleccionados</div>
                        <div class="contador-sel sel-contador">0</div>
                    </div>
                    <div style="width:0.0625rem; height:2.5rem; background: rgba(255,255,255,0.1);"></div>
                    <button class="btn-win btn-win-sm btn-sel-todos" type="button" data-tooltip="Marcar todos los trabajadores visibles" data-tooltip-theme="success">
                        <i class="fas fa-check-double me-1"></i>Todos
                    </button>
                    <button class="btn-win btn-win-sm btn-sel-ninguno" type="button" data-tooltip="Desmarcar todo" data-tooltip-theme="warning">
                        <i class="fas fa-xmark me-1"></i>Ninguno
                    </button>
                </div>
                <?php if ($puede_exportar): ?>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button class="btn-win btn-plantilla-dbf" type="button" data-tooltip="Descargar Plantilla en blanco" data-tooltip-theme="info">
                        <i class="fas fa-file-circle-plus me-2"></i>Plantilla Domiciliación Vacía
                    </button>
                    <button class="btn-win btn-win-success btn-generar-dbf" type="button">
                        <i class="fas fa-file-export me-2"></i>Generar Base de Datos (DBF)
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($totalErroneos > 0): ?>
            <div class="mb-3" style="background: rgba(239,68,68,0.1); border: 0.0625rem solid rgba(239,68,68,0.3); border-radius: 0.625rem; padding: 0.75rem 1rem;">
                <i class="fas fa-circle-info me-2" style="color:#f87171;"></i>
                <span style="color: #fecaca; font-size: 0.82rem;">
                    <strong><?php echo $totalErroneos; ?> trabajador(es)</strong> no pueden exportarse porque les falta el nombre, el primer apellido, el segundo apellido o tienen el carnet incompleto. No se pueden marcar hasta corregir sus datos en Empleados.
                </span>
            </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-3 mb-3 align-items-end">
                <div class="d-flex flex-column gap-1" style="flex:0 1 24rem; min-width:17rem;">
                    <span style="color:#93c5fd; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Buscar</span>
                    <input type="search" id="buscarDom" class="search-box" style="width:100%;" placeholder="CI, código, nombre o cuenta..." data-tooltip="Filtrar por CI, expediente, nombre o cuenta" data-tooltip-theme="primary">
                </div>
                <div class="d-flex flex-column gap-1" style="width:9.5rem;">
                    <span style="color:#93c5fd; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Área</span>
                    <select id="filtroArea" class="search-box" style="width:100%;" data-tooltip="Filtrar por área" data-tooltip-theme="primary">
                        <option value="0">Todas</option>
                        <?php foreach ($listaAreas as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1" style="width:11.5rem;">
                    <span style="color:#93c5fd; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Centro de Costos</span>
                    <select id="filtroCc" class="search-box" style="width:100%;" data-tooltip="Filtrar por centro de costos" data-tooltip-theme="primary">
                        <option value="0">Todos</option>
                        <?php foreach ($listaCentros as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <span style="color:#c4b5fd; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Datos</span>
                    <select id="filtroValidez" class="search-box" data-tooltip="Mostrar todos, solo los listos o solo los que tienen inconsistencias" data-tooltip-theme="info">
                        <option value="todos">Todos</option>
                        <option value="validos">Solo listos</option>
                        <option value="errores">Con inconsistencias</option>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <span style="color:#fbbf24; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Estado</span>
                    <select id="filtroEstado" class="search-box" data-tooltip="Filtrar por situación laboral" data-tooltip-theme="warning">
                        <option value="todos">Todos</option>
                        <option value="activos">Solo activos</option>
                        <option value="bajas">Solo dados de baja</option>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <span style="color:#c084fc; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Cuenta bancaria</span>
                    <select id="filtroCuenta" class="search-box" data-tooltip="Filtrar por si el trabajador tiene o no cuenta bancaria registrada" data-tooltip-theme="info">
                        <option value="todos">Todas</option>
                        <option value="con">Con cuenta</option>
                        <option value="sin">Sin cuenta</option>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1" style="min-width:8.5rem;">
                    <span style="color:#c084fc; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.0312rem;">Selección</span>
                    <label class="chk-filtro" for="filtroSoloMarcados" data-tooltip="Mostrar únicamente los trabajadores que ya están marcados" data-tooltip-theme="success">
                        <input type="checkbox" id="filtroSoloMarcados" class="chk-trabajador">
                        <i class="fas fa-check-double"></i>Solo marcados
                    </label>
                </div>
                <button class="btn-win btn-win-sm" id="btnLimpiarDom" type="button" data-tooltip="Limpiar filtros y mostrar todo" data-tooltip-theme="warning">
                        <i class="fas fa-times me-1"></i>Limpiar
                    </button>
            </div>

            <?php if ($totalGeneral > 0): ?>
            <div class="table-responsive">
                <table id="tablaDom">
                    <thead>
                        <tr>
                            <th style="text-align:center; width:2.5rem;">
                                <input type="checkbox" class="chk-trabajador" id="chkTodos" data-tooltip="Marcar o desmarcar todos los visibles" data-tooltip-theme="success">
                            </th>
                            <th style="text-align:center; width:2.5rem;" class="th-orden" data-orden="indice" data-tooltip="Ordenar por número de fila" data-tooltip-theme="primary">#<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th style="text-align:center;" class="th-orden" data-orden="codigo" data-tooltip="Ordenar por No. Expediente" data-tooltip-theme="primary">No. Exped.<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th style="text-align:center;" class="th-orden" data-orden="ci" data-tooltip="Ordenar por C.I." data-tooltip-theme="primary">C.I.<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th class="th-orden" data-orden="nombre" data-tooltip="Ordenar por nombre y apellidos" data-tooltip-theme="primary">Nombre y Apellidos<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th class="th-orden" data-orden="dbfnombre" data-tooltip="Ordenar por el campo NOMBRE del DBF" data-tooltip-theme="primary">DBF: NOMBRE<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th class="th-orden" data-orden="dbfcompleto" data-tooltip="Ordenar por el campo NOMB_APELL del DBF" data-tooltip-theme="primary">DBF: NOMB_APELL<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th style="text-align:center;" class="th-orden" data-orden="cuenta" data-tooltip="Ordenar por cuenta bancaria" data-tooltip-theme="primary">Cta. Bancaria<i class="fas fa-sort ms-1 th-caret"></i></th>
                            <th style="text-align:center;" class="th-orden" data-orden="estado" data-tooltip="Ordenar por estado" data-tooltip-theme="primary">Estado<i class="fas fa-sort ms-1 th-caret"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $i => $t): ?>
                        <?php
                        $estadoTexto = $t['es_baja'] ? 'Dado de baja' : 'Activo';
                        $busqueda = mb_strtolower($t['codigo'] . ' ' . $t['ci'] . ' ' . $t['nombres'] . ' ' . $t['primer_apellido'] . ' ' . $t['segundo_apellido'] . ' ' . $t['cuentabanc']);
                        ?>
                        <tr class="<?php echo $t['es_valido'] ? '' : 'fila-error'; ?>"
                            data-id="<?php echo (int)$t['id']; ?>">
                            <td style="text-align:center;"
                                data-valido="<?php echo $t['es_valido'] ? '1' : '0'; ?>"
                                data-baja="<?php echo $t['es_baja'] ? '1' : '0'; ?>"
                                data-cuenta="<?php echo $t['tiene_cuenta'] ? '1' : '0'; ?>"
                                data-estado="<?php echo $t['es_baja'] ? '2' : '1'; ?>"
                                data-area="<?php echo (int)$t['area_id']; ?>"
                                data-cc="<?php echo (int)$t['centro_costo_id']; ?>"
                                data-busqueda="<?php echo htmlspecialchars($busqueda); ?>">
                                <input type="checkbox" class="chk-trabajador chk-fila"
                                       value="<?php echo (int)$t['id']; ?>"
                                       <?php echo $t['es_valido'] ? '' : 'disabled'; ?>
                                       <?php echo !$t['es_valido'] ? 'data-tooltip="' . htmlspecialchars(implode(' · ', $t['errores'])) . '" data-tooltip-theme="error"' : ''; ?>>
                            </td>
                            <td style="text-align:center; color: rgba(255,255,255,0.4); font-size:0.78rem;"><?php echo $i + 1; ?></td>
                            <td style="text-align:center;"><span class="codigo-mono"><?php echo htmlspecialchars($t['codigo']); ?></span></td>
                            <td style="text-align:center;"><span class="codigo-mono"><?php echo htmlspecialchars($t['ci']); ?></span></td>
                            <td style="color:#e2e8f0;">
                                <?php echo htmlspecialchars(trim($t['nombres'] . ' ' . $t['primer_apellido'] . ' ' . $t['segundo_apellido'])); ?>
                                <?php if ($t['centro_costo']): ?>
                                <span class="badge-mini neutral ms-1"><?php echo htmlspecialchars($t['centro_costo']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="campo-dbf valor-dbf"><?php echo htmlspecialchars($t['dbf_nombre']); ?></span></td>
                            <td><span class="campo-dbf valor-dbf"><?php echo htmlspecialchars($t['dbf_completo']); ?></span></td>
                            <td style="text-align:center;">
                                <?php if ($t['tiene_cuenta']): ?>
                                <span class="codigo-mono" style="font-size:0.75rem;"><?php echo htmlspecialchars($t['cuentabanc']); ?></span>
                                <?php else: ?>
                                <span class="badge-mini neutral" data-tooltip="Este trabajador no tiene cuenta bancaria registrada" data-tooltip-theme="warning"><i class="fas fa-circle-minus"></i>Sin cuenta</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if (!$t['es_valido']): ?>
                                <span class="badge-mini fail" data-tooltip="<?php echo htmlspecialchars(implode(' · ', $t['errores'])); ?>" data-tooltip-theme="error">
                                    <i class="fas fa-triangle-exclamation"></i>Incompleto
                                </span>
                                <?php elseif ($t['es_baja']): ?>
                                <span class="badge-mini neutral"><i class="fas fa-user-slash me-1"></i>BAJA</span>
                                <?php else: ?>
                                <span class="badge-mini ok"><i class="fas fa-circle-check"></i>Listo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="sinResultados" class="text-center py-5" style="display:none;">
                <i class="fas fa-user-slash" style="font-size:3rem; color: rgba(255,255,255,0.15); margin-bottom:1rem;"></i>
                <h5 style="color: rgba(255,255,255,0.4);">Sin resultados</h5>
                <p style="color: rgba(255,255,255,0.3); font-size:0.85rem;">Ningún trabajador coincide con los filtros aplicados.</p>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users-slash" style="font-size:3rem; color: rgba(255,255,255,0.15); margin-bottom:1rem;"></i>
                <h5 style="color: rgba(255,255,255,0.4);">No hay trabajadores registrados</h5>
                <p style="color: rgba(255,255,255,0.3); font-size:0.85rem;">No se encontraron registros de trabajadores en el sistema.</p>
            </div>
            <?php endif; ?>

            <i class="fas fa-database card-icon-bg" style="color:#60a5fa;"></i>
        </div>
    </div>

    <div class="seleccion-bar fade-in-up mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3" style="animation-delay: 0.1s;">
        <div class="d-flex align-items-center gap-3">
            <div>
                <div style="font-size:0.7rem; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:0.0312rem; font-weight:600;">Seleccionados</div>
                <div class="contador-sel sel-contador">0</div>
            </div>
            <div style="width:0.0625rem; height:2.5rem; background: rgba(255,255,255,0.1);"></div>
            <button class="btn-win btn-win-sm btn-sel-todos" type="button" data-tooltip="Marcar todos los trabajadores visibles" data-tooltip-theme="success">
                <i class="fas fa-check-double me-1"></i>Todos
            </button>
            <button class="btn-win btn-win-sm btn-sel-ninguno" type="button" data-tooltip="Desmarcar todo" data-tooltip-theme="warning">
                <i class="fas fa-xmark me-1"></i>Ninguno
            </button>
        </div>
        <?php if ($puede_exportar): ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button class="btn-win btn-plantilla-dbf" type="button" data-tooltip="Descargar Plantilla en blanco" data-tooltip-theme="info">
                <i class="fas fa-file-circle-plus me-2"></i>Plantilla Domiciliación Vacía
            </button>
            <button class="btn-win btn-win-success btn-generar-dbf" type="button">
                <i class="fas fa-file-export me-2"></i>Generar Base de Datos (DBF)
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mainContainer = document.getElementById('mainContainer');
    if (toggleBtn && mainContainer) {
        toggleBtn.addEventListener('click', function() {
            mainContainer.classList.toggle('expanded');
        });
    }

    const chkTodos = document.getElementById('chkTodos');
    const contadores = Array.from(document.querySelectorAll('.sel-contador'));
    const botonesGenerar = Array.from(document.querySelectorAll('.btn-generar-dbf'));
    const botonesPlantilla = Array.from(document.querySelectorAll('.btn-plantilla-dbf'));
    const buscar = document.getElementById('buscarDom');
    const filtroValidez = document.getElementById('filtroValidez');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroCuenta = document.getElementById('filtroCuenta');
    const filtroArea = document.getElementById('filtroArea');
    const filtroCc = document.getElementById('filtroCc');
    const filtroSoloMarcados = document.getElementById('filtroSoloMarcados');

const seleccionados = new Set();

let tabla = null;

tabla = $('#tablaDom').DataTable({
        language: {
            search: '',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            paginate: { first: '<i class="fas fa-step-backward"></i>', last: '<i class="fas fa-step-forward"></i>', next: '<i class="fas fa-chevron-right"></i>', previous: '<i class="fas fa-chevron-left"></i>' },
            zeroRecords: 'No se encontraron resultados'
        },
        dom: '<"d-flex justify-content-end align-items-center flex-wrap mb-3"<"dt-length"l>>rt<"d-flex justify-content-between align-items-center flex-wrap mt-2"<"dt-info"i><"dt-pagination"p>>',
        pagingType: 'full_numbers',
        pageLength: -1,
        lengthMenu: [[-1, 5, 10, 15, 25, 50, 75, 100], ['Todos', 5, 10, 15, 25, 50, 75, 100]],
        order: [[2, 'asc']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [0] }
        ],
        drawCallback: function() {
            if (!tabla) return;
            sincronizarChecks();
            actualizarContador();
            actualizarCarets();
        }
    });

    function visibles() {
        if (!tabla) return [];
        return tabla.rows({ search: 'applied' }).nodes().toArray();
    }

    function marcablesVisibles() {
        return visibles().filter(f => {
            const chk = f.querySelector('.chk-fila');
            return chk && !chk.disabled;
        });
    }

    function sincronizarChecks() {
        $('#tablaDom tbody tr').each(function() {
            const $tr = $(this);
            const id = $tr.data('id');
            const chk = $tr.find('.chk-fila')[0];
            if (!chk) return;
            chk.checked = seleccionados.has(String(id));
            $tr.toggleClass('fila-seleccionada', chk.checked);
        });
    }

    function actualizarContador() {
        contadores.forEach(c => c.textContent = seleccionados.size);
        botonesGenerar.forEach(b => b.disabled = seleccionados.size === 0);
        if (!tabla) return;

        const vis = marcablesVisibles();
        const marcadasVis = vis.filter(f => seleccionados.has(String(f.dataset.id))).length;

        if (chkTodos) {
            chkTodos.checked = vis.length > 0 && marcadasVis === vis.length;
            chkTodos.indeterminate = marcadasVis > 0 && marcadasVis < vis.length;
            chkTodos.disabled = vis.length === 0;
        }
    }

    function redibujarSiMarcaActiva() {
        if (!tabla || !filtroSoloMarcados || !filtroSoloMarcados.checked) return;
        tabla.draw();
    }

    function botonAbrirCarpeta(archivo) {
        return '<button type="button" class="btn-abrir-carpeta" data-archivo="' + archivo + '"'
            + ' data-tooltip="Abrir la carpeta y ver el archivo exportado" data-tooltip-theme="primary">'
            + '<i class="fas fa-folder-open"></i> Abrir</button>';
    }

    function registrarBotonesAbrirCarpeta() {
        document.querySelectorAll('.btn-abrir-carpeta').forEach(btn => {
            btn.addEventListener('click', function() {
                const archivo = this.dataset.archivo;
                if (!archivo) return;
                const cuerpo = new URLSearchParams();
                cuerpo.append('archivo', archivo);
                this.disabled = true;

                fetch('abrir_exportacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: cuerpo.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        this.disabled = false;
                        if (!data.success) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No se pudo abrir la carpeta',
                                text: data.mensaje || 'Intente abrir la carpeta manualmente.',
                                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                                confirmButtonColor: '#f59e0b'
                            });
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo comunicar con el servidor.',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            });
        });
    }

    function actualizarCarets() {
        if (!tabla) return;
        const orden = tabla.order();
        document.querySelectorAll('th.th-orden').forEach(th => {
            const col = th.cellIndex;
            const activo = orden.length && orden[0][0] === col;
            th.classList.remove('asc', 'desc');
            const caret = th.querySelector('.th-caret');
            if (!caret) return;
            if (!activo) { caret.className = 'fas fa-sort ms-1 th-caret'; return; }
            const asc = orden[0][1] === 'asc';
            th.classList.add(asc ? 'asc' : 'desc');
            caret.className = 'fas fa-sort-' + (asc ? 'up' : 'down') + ' ms-1 th-caret';
        });
    }

    function aplicarFiltros() {
        tabla.draw();
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tablaDom') return true;

        const tr = settings.aoData[dataIndex].nTr;
        if (!tr) return true;
        const $td0 = $(tr).find('td').eq(0);
        const d = $td0.data() || {};

        const texto = (buscar.value || '').trim().toLowerCase();
        const busq = (d.busqueda !== undefined ? String(d.busqueda) : '').toLowerCase();
        if (texto !== '' && busq.indexOf(texto) === -1) return false;

        const validez = filtroValidez.value;
        if (validez === 'validos' && String(d.valido) !== '1') return false;
        if (validez === 'errores' && String(d.valido) !== '0') return false;

        const estado = filtroEstado.value;
        if (estado === 'activos' && String(d.baja) !== '0') return false;
        if (estado === 'bajas' && String(d.baja) !== '1') return false;

        const cuenta = filtroCuenta.value;
        if (cuenta === 'con' && String(d.cuenta) !== '1') return false;
        if (cuenta === 'sin' && String(d.cuenta) !== '0') return false;

        const area = filtroArea.value;
        if (area !== '0' && String(d.area) !== String(area)) return false;

        const cc = filtroCc.value;
        if (cc !== '0' && String(d.cc) !== String(cc)) return false;

        if (filtroSoloMarcados.checked && !seleccionados.has(String(tr.dataset.id))) return false;

        return true;
    });

    document.getElementById('sinResultados').style.display = 'none';

    $('#tablaDom tbody').on('change', '.chk-fila', function() {
        const id = String($(this).closest('tr').data('id'));
        if (this.checked) seleccionados.add(id); else seleccionados.delete(id);
        $(this).closest('tr').toggleClass('fila-seleccionada', this.checked);
        actualizarContador();
        redibujarSiMarcaActiva();
    });

    $('#tablaDom tbody').on('click', 'tr', function(e) {
        if ($(e.target).is('input, label, a, button')) return;
        const chk = $(this).find('.chk-fila')[0];
        if (!chk || chk.disabled) return;
        chk.checked = !chk.checked;
        chk.dispatchEvent(new Event('change', { bubbles: true }));
    });

    if (chkTodos) {
        chkTodos.addEventListener('change', function() {
            const marcar = this.checked;
            marcablesVisibles().forEach(f => {
                const id = String(f.dataset.id);
                if (marcar) seleccionados.add(id); else seleccionados.delete(id);
            });
            sincronizarChecks();
            actualizarContador();
            redibujarSiMarcaActiva();
        });
    }

    document.querySelectorAll('.btn-sel-todos').forEach(btn => {
        btn.addEventListener('click', function() {
            marcablesVisibles().forEach(f => seleccionados.add(String(f.dataset.id)));
            sincronizarChecks();
            actualizarContador();
            redibujarSiMarcaActiva();
        });
    });

    document.querySelectorAll('.btn-sel-ninguno').forEach(btn => {
        btn.addEventListener('click', function() {
            seleccionados.clear();
            sincronizarChecks();
            actualizarContador();
            redibujarSiMarcaActiva();
        });
    });

    document.getElementById('btnLimpiarDom').addEventListener('click', function() {
        buscar.value = '';
        filtroValidez.value = 'todos';
        filtroEstado.value = 'todos';
        filtroCuenta.value = 'todos';
        filtroArea.value = '0';
        filtroCc.value = '0';
        filtroSoloMarcados.checked = false;
        tabla.search('').order([[2, 'asc']]).draw();
    });

    buscar.addEventListener('input', aplicarFiltros);
    filtroValidez.addEventListener('change', aplicarFiltros);
    filtroEstado.addEventListener('change', aplicarFiltros);
    filtroCuenta.addEventListener('change', aplicarFiltros);
    filtroArea.addEventListener('change', aplicarFiltros);
    filtroCc.addEventListener('change', aplicarFiltros);
    filtroSoloMarcados.addEventListener('change', aplicarFiltros);

    botonesGenerar.forEach(btnGenerar => {
        btnGenerar.addEventListener('click', function() {
            if (seleccionados.size === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Debe marcar al menos un trabajador para generar la base de datos.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            Swal.fire({
                title: 'Generar base de datos',
                html: 'Se exportarán <strong>' + seleccionados.size + '</strong> trabajador(es) al archivo DBF para el banco.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-file-export me-2"></i> Generar',
                cancelButtonText: '<i class="fas fa-xmark me-2"></i> Cancelar',
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#6b7280',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;

                Swal.fire({ title: 'Generando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                const cuerpo = new URLSearchParams();
                seleccionados.forEach(id => cuerpo.append('ids[]', id));

                fetch('exportar_domiciliacion_tarjetas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: cuerpo.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();
                        if (!data.success) {
                            let extra = '';
                            if (data.rechazados && data.rechazados.length) {
                                extra = '<br><small style="color:#f87171">' +
                                    data.rechazados.slice(0, 5).map(x => x.codigo + ': ' + x.errores.join(', ')).join('<br>') +
                                    (data.rechazados.length > 5 ? '<br>... y ' + (data.rechazados.length - 5) + ' más' : '') +
                                    '</small>';
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo generar',
                                html: (data.mensaje || 'Error inesperado.') + extra,
                                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                                confirmButtonColor: '#ef4444'
                            });
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: data.plantilla ? 'Base de datos en blanco generada' : 'Base de datos generada',
                            html: '<div style="text-align:left">' +
                                  '<div style="font-size:0.8rem; color:rgba(255,255,255,0.6); margin-bottom:0.25rem;"><i class="fas fa-file me-2"></i>Archivo</div>' +
                                  '<div style="font-family:Consolas,monospace; font-size:0.85rem; word-break:break-all; margin-bottom:0.75rem;">' + data.archivo + '</div>' +
                                  '<div style="font-size:0.8rem; color:rgba(255,255,255,0.6); margin-bottom:0.25rem;"><i class="fas fa-folder-open me-2"></i>Ubicación</div>' +
                                  '<div>' + data.ruta + botonAbrirCarpeta(data.archivo) + '</div>' +
                                  '<div style="font-size:0.85rem; color:#4ade80; font-weight:600; margin-top:0.75rem;"><i class="fas fa-circle-check me-2"></i>' + data.registros + ' registro(s) exportados.</div>' +
                                  (data.rechazados > 0 ? '<div style="font-size:0.8rem; color:#fbbf24; margin-top:0.25rem;"><i class="fas fa-triangle-exclamation me-2"></i>' + data.rechazados + ' descartado(s) por datos incompletos.</div>' : '') +
                                  (data.plantilla ? '<div style="font-size:0.8rem; color:#93c5fd; margin-top:0.25rem;"><i class="fas fa-circle-info me-2"></i>Plantilla vacía, sin registros.</div>' : '') +
                                  '</div>' +
                                  '<div style="margin-top:1rem;"><a href="' + data.descarga + '" class="btn-descarga-dbf" download><i class="fas fa-download me-2"></i> Descargar DBF</a></div>',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#22c55e'
                        });
                        registrarBotonesAbrirCarpeta();
                    })
                    .catch(err => {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: err.message,
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            });
        });
    });

    botonesPlantilla.forEach(btnPlantilla => {
        btnPlantilla.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="fas fa-file-circle-plus me-2"></i> Base de datos en blanco',
                html: 'Se generara <strong>domiciliacion_tarjetas_plantilla.dbf</strong> con los 16 campos del banco y <strong>sin registros</strong>, listo para usar como plantilla.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i> Generar plantilla',
                cancelButtonText: '<i class="fas fa-xmark me-2"></i> Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;

                Swal.fire({ title: 'Generando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                const cuerpo = new URLSearchParams();
                cuerpo.append('accion', 'plantilla');

                fetch('exportar_domiciliacion_tarjetas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: cuerpo.toString()
                })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();
                        if (!data.success) {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo generar',
                                text: data.mensaje || 'Error inesperado.',
                                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                                confirmButtonColor: '#ef4444'
                            });
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Base de datos en blanco generada',
                            html: '<div style="text-align:left">' +
                                  '<div style="font-size:0.8rem; color:rgba(255,255,255,0.6); margin-bottom:0.25rem;"><i class="fas fa-file me-2"></i>Archivo</div>' +
                                  '<div style="font-family:Consolas,monospace; font-size:0.85rem; word-break:break-all; margin-bottom:0.75rem;">' + data.archivo + '</div>' +
                                  '<div style="font-size:0.8rem; color:rgba(255,255,255,0.6); margin-bottom:0.25rem;"><i class="fas fa-folder-open me-2"></i>Ubicacion</div>' +
                                  '<div>' + data.ruta + botonAbrirCarpeta(data.archivo) + '</div>' +
                                  '<div style="font-size:0.85rem; color:#4ade80; font-weight:600; margin-top:0.75rem;"><i class="fas fa-circle-check me-2"></i>Estructura lista, sin registros.</div>' +
                                  '<div style="font-size:0.8rem; color:#93c5fd; margin-top:0.25rem;"><i class="fas fa-circle-info me-2"></i>16 campos del banco, plantilla vacia.</div>' +
                                  '</div>' +
                                  '<div style="margin-top:1rem;"><a href="' + data.descarga + '" class="btn-descarga-dbf" download><i class="fas fa-download me-2"></i> Descargar DBF</a></div>',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#22c55e'
                        });
                        registrarBotonesAbrirCarpeta();
                    })
                    .catch(err => {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexion',
                            text: err.message,
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            });
        });
    });

    actualizarContador();
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
