<?php
// modules/snc125.php - Tarjeta SC-4-09 — Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/snc125_reporte.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Mismo acceso que la tarjeta del SC-4-08: Administrador, Contador/Editor y
// Programador; el Visualizador y el Supervisor General no la usan.
if (!permiso_puede('snc125', 'ver')) {
    permiso_denegar_acceso('Tarjeta SC-4-09');
}

$stmt = $pdo->query("
    SELECT
        t.id,
        t.codigo,
        t.ci,
        t.nombre_completo,
        t.fecha_alta,
        t.fecha_baja,
        t.activo,
        cc.codigo AS c_costo,
        cc.nombre AS c_costo_nombre,
        a.nombre_area AS area_nombre,
        cp.nombre_cargo AS plaza,
        co.nombre AS categoria_ocupacional,
        COALESCE((SELECT SUM(n.total_salario_devengado) FROM nominas n WHERE n.trabajador_id = t.id), 0) AS total_devengado
    FROM trabajadores t
    LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
    LEFT JOIN areas a ON t.area_id = a.id
    LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
    LEFT JOIN categorias_ocupacionales co ON t.categoria_ocupacional_id = co.id
    ORDER BY t.codigo ASC
");
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($trabajadores);
$totalActivos = 0;
$totalInactivos = 0;
$sinFechaAlta = 0;
$sinFechaBaja = 0;
$aniosAlta = [];
$mesesAlta = [];
$aniosBaja = [];
$mesesBaja = [];
$ccostos = [];
$areas = [];
$hoy = new DateTime();

foreach ($trabajadores as &$t) {
    $activo = isset($t['activo']) ? (int)$t['activo'] : 0;
    $t['activo'] = $activo;
    if ($activo === 1) {
        $totalActivos++;
    } else {
        $totalInactivos++;
    }

    $fa = (!empty($t['fecha_alta']) && $t['fecha_alta'] !== '0000-00-00') ? $t['fecha_alta'] : null;
    $fb = (!empty($t['fecha_baja']) && $t['fecha_baja'] !== '0000-00-00') ? $t['fecha_baja'] : null;
    $t['fecha_alta'] = $fa;
    $t['fecha_baja'] = $fb;

    if ($fa) {
        $anio = (int)date('Y', strtotime($fa));
        $mes = (int)date('n', strtotime($fa));
        $aniosAlta[$anio] = true;
        $mesesAlta[$anio][$mes] = ($mesesAlta[$anio][$mes] ?? 0) + 1;
    } else {
        $sinFechaAlta++;
    }

    if ($fb) {
        $anio = (int)date('Y', strtotime($fb));
        $mes = (int)date('n', strtotime($fb));
        $aniosBaja[$anio] = true;
        $mesesBaja[$anio][$mes] = ($mesesBaja[$anio][$mes] ?? 0) + 1;
    } else {
        $sinFechaBaja++;
    }

    $codigoCosto = trim((string)($t['c_costo'] ?? ''));
    if ($codigoCosto !== '' && !isset($ccostos[$codigoCosto])) {
        $ccostos[$codigoCosto] = ['codigo' => $codigoCosto, 'nombre' => trim((string)($t['c_costo_nombre'] ?? ''))];
    }

    $area = trim((string)($t['area_nombre'] ?? ''));
    if ($area !== '') {
        $areas[$area] = true;
    }

    $t['plaza'] = trim((string)($t['plaza'] ?? ''));
    if ($t['plaza'] === '') {
        $t['plaza'] = trim((string)($t['categoria_ocupacional'] ?? ''));
    }

    // Años de servicio acumulados hasta hoy (o hasta la baja).
    if ($fa) {
        $intervalo = (new DateTime($fa))->diff($fb ? new DateTime($fb) : $hoy);
        $t['t_anos'] = $intervalo->y;
        $t['t_meses'] = $intervalo->m;
        $t['antiguedad'] = $intervalo->y . 'a ' . $intervalo->m . 'm';
    } else {
        $t['t_anos'] = 0;
        $t['t_meses'] = 0;
        $t['antiguedad'] = '—';
    }
}
unset($t);

$aniosAlta = array_keys($aniosAlta);
sort($aniosAlta, SORT_NUMERIC);
$aniosBaja = array_keys($aniosBaja);
sort($aniosBaja, SORT_NUMERIC);
ksort($mesesAlta, SORT_NUMERIC);
ksort($mesesBaja, SORT_NUMERIC);
$ccostos = array_values($ccostos);
usort($ccostos, function ($a, $b) {
    return strcmp($a['codigo'], $b['codigo']);
});
$areas = array_keys($areas);
sort($areas, SORT_NATURAL | SORT_FLAG_CASE);

$ruta_logo = '../../images/LogoCorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo_logo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $logo_base64 = 'data:image/' . $tipo_logo . ';base64,' . base64_encode(file_get_contents($ruta_logo));
}

$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SiGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas',
    'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH',
    'nit' => defined('NIT') ? NIT : '',
    'direccion_empresa' => '',
    'ciudad' => '',
];
try {
    $stCfg = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa','jefe_proyecto','especialista_gestion','especialista_gestionRRHH','direccion_empresa','nit_empresa','reeup_empresa','slogan')");
    while ($row = $stCfg->fetch(PDO::FETCH_ASSOC)) {
        if ($row['valor'] !== null && trim((string)$row['valor']) !== '') {
            $config_empresa[$row['parametro']] = $row['valor'];
        }
    }
    if (!empty($config_empresa['nit_empresa'])) {
        $config_empresa['nit'] = $config_empresa['nit_empresa'];
    }
    // La ciudad del pie de la certificación se toma de la dirección de la entidad.
    $partesDir = snc125_partir_direccion((string)($config_empresa['direccion_empresa'] ?? ''));
    $config_empresa['ciudad'] = $partesDir['municipio'];
} catch (PDOException $e) {
}

// Modelo oficial SC-4-09 (vista previa / Word / PDF): se atiende antes de
// cualquier salida HTML, igual que el SC-4-08 en snc225.php.
snc125_reporte_manejar($pdo, $config_empresa);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SiGesNom'; ?> | SC-4-09 · Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter','Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif; background:var(--bg); overflow-x:hidden; color:#fff; }
        .win11-bg { position:fixed; inset:0; z-index:-2; background:linear-gradient(135deg,#0a0a0a 0%,#1a1a2e 50%,#0f0f1a 100%); }
        .main-container { margin-left:16.25rem; transition:all .3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }
        .win-topbar { background:var(--panel); backdrop-filter:blur(1.25rem); border-radius:1rem; padding:.75rem 1.5rem; margin-bottom:1.5rem; border:.0625rem solid rgba(255,255,255,.06); display:flex; justify-content:space-between; align-items:center; position:relative !important; z-index:100 !important; }
        .sidebar-toggle { background:rgba(255,255,255,.05); border:none; color:#fff; width:2.5rem; height:2.5rem; border-radius:.75rem; cursor:pointer; transition:all .2s; }
        .sidebar-toggle:hover { background:rgba(255,255,255,.1); }
        .page-title h1 { font-size:1.5rem; font-weight:600; margin:0; }
        .page-title p { font-size:.8rem; color:rgba(255,255,255,.5); margin:.25rem 0 0; }
        .glass-card { background:var(--panel-2); backdrop-filter:blur(.625rem); border:.0625rem solid rgba(255,255,255,.06); border-radius:.75rem; overflow:hidden; }
        .btn-win { display:inline-flex; align-items:center; gap:.4rem; border:none; border-radius:.5rem; font-size:.82rem; font-weight:500; padding:.5rem .9rem; cursor:pointer; transition:all .18s; }
        .btn-win-primary { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; }
        .btn-win-primary:hover:not(:disabled) { filter:brightness(1.1); }
        .btn-win-primary:disabled { opacity:.45; cursor:not-allowed; }
        .btn-win-outline { background:rgba(255,255,255,.05); border:.0625rem solid rgba(255,255,255,.12); color:#cbd5e1; }
        .btn-win-outline:hover { background:rgba(var(--accent-rgb),.15); color:#fff; }
        .btn-win-sm { font-size:.78rem; padding:.38rem .7rem; }
        .stat { flex:1; min-width:9rem; border-radius:.625rem; padding:.875rem 1.125rem; display:flex; align-items:center; gap:.875rem; }
        .stat-label { font-size:.7rem; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.0312rem; font-weight:600; }
        .stat-value { font-size:1.4rem; font-weight:700; line-height:1.2; }
        .tabla-snc { width:100%; border-collapse:collapse; font-size:.82rem; }
        .tabla-snc th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.03rem; color:rgba(255,255,255,.5); padding:.55rem .6rem; border-bottom:.0625rem solid rgba(255,255,255,.08); }
        .tabla-snc td { padding:.5rem .6rem; border-bottom:.0625rem solid rgba(255,255,255,.05); color:#e2e8f0; vertical-align:middle; }
        .tabla-snc tbody tr { cursor:pointer; }
        .tabla-snc tbody tr:hover { background:rgba(96,165,250,.08); }
        .tabla-snc tr.sel { background:rgba(37,99,235,.18); }
        .badge-codigo { display:inline-block; font-family:'Consolas',monospace; font-size:.72rem; padding:.15rem .45rem; border-radius:.3125rem; background:rgba(167,139,250,.15); color:#c4b5fd; }
        .chip { display:inline-flex; align-items:center; gap:.3rem; font-size:.7rem; padding:.2rem .5rem; border-radius:.3125rem; }
        .chip-ok { background:rgba(34,197,94,.14); color:#4ade80; }
        .chip-baja { background:rgba(248,113,113,.14); color:#f87171; }

        /* ---------- CARD DE FILTROS (igual que snc225.php) ---------- */
        #sncFiltrosWrap { padding:.75rem 1rem; background:rgba(255,255,255,.03); border:.0625rem solid rgba(255,255,255,.06); border-radius:.625rem; }
        #sncFiltrosToggle { cursor:pointer; user-select:none; padding:.125rem 0; }
        .snc-select-wrap { position:relative; display:inline-flex; align-items:center; }
        /* Un solo chevron: el que pinta el tema en .form-select. */
        .snc-select-wrap select { appearance:none; -webkit-appearance:none; cursor:pointer; }
        #btnLimpiarFiltrosSNC { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
        #btnLimpiarFiltrosSNC:hover { filter:brightness(1.08); }

        .dataTables_wrapper { color:#cbd5e1; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background:rgba(255,255,255,.06); border:.0625rem solid rgba(255,255,255,.12);
            border-radius:.375rem; color:#e2e8f0; padding:.3rem .5rem;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter { color:rgba(255,255,255,.6); font-size:.8rem; }
        .pagination .page-link { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1); color:#cbd5e1; }
        .pagination .page-item.active .page-link { background:#2563eb; border-color:#2563eb; color:#fff; }
        .sel-info { font-size:.8rem; color:rgba(255,255,255,.6); }
        @media (max-width: 900px) { .main-container { margin-left:0; padding:.75rem; } }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    <div class="win-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" data-tooltip="Menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-file-signature me-2" style="color:#60a5fa;"></i>Tarjeta SC-4-09 · SNC 1 - 25</h1>
                <p><i class="fas fa-certificate me-1"></i> Certificación de Años de Servicios y Salarios Devengados (SNC 1-25) — listado de trabajadores</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <div class="mb-3">
        <a href="modelos.php" class="btn-win btn-win-outline btn-win-sm" title="Volver a Modelos" data-tooltip="Volver a Modelos" data-tooltip-theme="primary">
            <i class="fas fa-arrow-left me-1"></i> Volver a Modelos
        </a>
    </div>

    <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1" style="color:#e2e8f0;font-weight:600;">
                    <i class="fas fa-users me-2" style="color:#60a5fa;"></i>Listado de Trabajadores
                </h5>
                <p class="mb-0" style="font-size:.8rem;color:rgba(255,255,255,.5);">
                    SC-4-09 Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)
                </p>
            </div>
            <div class="d-flex gap-2 no-print">
                <button class="btn-win btn-win-primary" id="btnPrintSNC" title="Imprimir listado SC-4-09" data-tooltip="Imprimir listado" data-tooltip-theme="primary">
                    <i class="fas fa-print me-1"></i>Imprimir
                </button>
            </div>
        </div>

        <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="stat" style="background:linear-gradient(135deg,rgba(96,165,250,.12),rgba(96,165,250,.04));border:.0625rem solid rgba(96,165,250,.2);">
                <i class="fas fa-users" style="color:#60a5fa;font-size:1.1rem;"></i>
                <div>
                    <div class="stat-label">Total</div>
                    <div class="stat-value" style="color:#60a5fa;"><?php echo $total; ?></div>
                </div>
            </div>
            <div class="stat" style="background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:.0625rem solid rgba(34,197,94,.2);">
                <i class="fas fa-user-check" style="color:#22c55e;font-size:1.1rem;"></i>
                <div>
                    <div class="stat-label">Activos</div>
                    <div class="stat-value" style="color:#22c55e;"><?php echo $totalActivos; ?></div>
                </div>
            </div>
            <div class="stat" style="background:linear-gradient(135deg,rgba(248,113,113,.12),rgba(248,113,113,.04));border:.0625rem solid rgba(248,113,113,.2);">
                <i class="fas fa-user-xmark" style="color:#f87171;font-size:1.1rem;"></i>
                <div>
                    <div class="stat-label">Bajas</div>
                    <div class="stat-value" style="color:#f87171;"><?php echo $totalInactivos; ?></div>
                </div>
            </div>
        </div>

        <div class="mb-3 flex-wrap no-print" id="sncFiltrosWrap">
            <div class="d-flex align-items-center gap-2" id="sncFiltrosToggle">
                <i class="fas fa-filter" style="color:#60a5fa;font-size:.82rem;"></i>
                <span style="color:rgba(255,255,255,.7);font-size:.82rem;font-weight:600;">FILTROS</span>
                <i class="fas fa-chevron-down ms-auto" id="sncFiltrosChevron" style="color:rgba(255,255,255,.4);font-size:.7rem;transition:transform .3s ease;"></i>
            </div>
            <div id="sncFiltrosBody">
                <div class="d-flex align-items-end gap-4 flex-wrap pt-2">
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#60a5fa;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">F. Alta</span>
                        <div class="d-flex align-items-center gap-1">
                            <label for="filtroAltaAnio" style="color:rgba(255,255,255,.5);font-size:.78rem;margin:0;">Año:</label>
                            <div class="snc-select-wrap">
                                <select id="filtroAltaAnio" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:5.625rem;"></select>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <label for="filtroAltaMes" style="color:rgba(255,255,255,.5);font-size:.78rem;margin:0;">Mes:</label>
                            <div class="snc-select-wrap">
                                <select id="filtroAltaMes" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:8.125rem;"></select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#f87171;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">F. Baja</span>
                        <div class="d-flex align-items-center gap-1">
                            <label for="filtroBajaAnio" style="color:rgba(255,255,255,.5);font-size:.78rem;margin:0;">Año:</label>
                            <div class="snc-select-wrap">
                                <select id="filtroBajaAnio" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:5.625rem;"></select>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <label for="filtroBajaMes" style="color:rgba(255,255,255,.5);font-size:.78rem;margin:0;">Mes:</label>
                            <div class="snc-select-wrap">
                                <select id="filtroBajaMes" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:8.125rem;"></select>
                            </div>
                        </div>
                    </div>
                    <div style="width:.0625rem;height:2.5rem;background:rgba(255,255,255,.1);align-self:center;"></div>
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#a78bfa;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">C. Costo</span>
                        <div class="snc-select-wrap">
                            <select id="filtroCosto" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:8.75rem;"></select>
                            
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#34d399;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">Área</span>
                        <div class="snc-select-wrap">
                            <select id="filtroArea" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:8.75rem;"></select>
                            
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#fbbf24;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">Estado</span>
                        <div class="snc-select-wrap">
                            <select id="filtroEstado" class="form-select form-select-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem 1.75rem .25rem .625rem;font-size:.82rem;min-width:8.125rem;"></select>
                            
                        </div>
                    </div>
                    <div style="width:.0625rem;height:2.5rem;background:rgba(255,255,255,.1);align-self:center;"></div>
                    <div class="d-flex flex-column gap-1">
                        <span style="color:#fb923c;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.0312rem;">Fecha de Corte</span>
                        <input type="date" id="filtroFechaCalculo" class="form-control form-control-sm" style="background-color:var(--panel);color:var(--txt);border:.0625rem solid rgba(255,255,255,.15);border-radius:.5rem;padding:.25rem .625rem;font-size:.82rem;min-width:10rem;color-scheme:dark;" title="Solo trabajadores en servicio en esa fecha; la certificación se emite con ese corte" data-tooltip="Corte de la certificación: filtra trabajadores en servicio y genera el modelo a esa fecha" data-tooltip-theme="warning">
                    </div>
                    <button class="btn-win btn-win-sm" id="btnLimpiarFiltrosSNC" type="button" style="margin-bottom:.125rem;color:#fff;" data-tooltip="Limpiar filtros y mostrar todo" data-tooltip-theme="warning">
                        <i class="fas fa-times me-1"></i>Limpiar Filtros
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 no-print">
            <span class="sel-info" id="selInfo">
                <i class="fas fa-hand-pointer me-1"></i>Ningún trabajador seleccionado
            </span>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn-win btn-win-primary btn-win-sm" id="btnVer" disabled title="Vista previa de la certificación" data-tooltip="Vista previa del modelo" data-tooltip-theme="info">
                    <i class="fas fa-eye"></i> Vista previa
                </button>
                <button type="button" class="btn-win btn-win-primary btn-win-sm" id="btnWord" disabled title="Descargar la certificación en Word" data-tooltip="Descargar en Word" data-tooltip-theme="info">
                    <i class="fas fa-file-word"></i> Word
                </button>
                <button type="button" class="btn-win btn-win-primary btn-win-sm" id="btnPdf" disabled title="Imprimir la certificación o guardarla en PDF" data-tooltip="Imprimir / PDF" data-tooltip-theme="info">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>

        <div id="sncVistaOverlay" class="no-print" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483000;background:rgba(2,6,23,0.94);flex-direction:column;">
            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem 1.25rem;background:linear-gradient(135deg,#1e3a8a,#2563eb);border-bottom:0.1875rem solid #1e40af;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;">
                <span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">VISTA PREVIA · SC-4-09</span>
                <span id="sncVistaArchivo" style="color:#c7d2fe;font-size:0.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <div style="margin-left:auto;display:flex;gap:0.5rem;">
                    <button type="button" id="sncVistaImprimir" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.5625rem 1.25rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Imprimir
                    </button>
                    <button type="button" id="sncVistaDescargar" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.5625rem 1.25rem;background:#f59e0b;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Descargar PDF
                    </button>
                    <button type="button" id="sncVistaCerrar" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.5625rem 1.25rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Cerrar
                    </button>
                </div>
            </div>
            <iframe id="sncVistaFrame" title="Vista previa SC-4-09" style="flex:1;width:100%;border:0;background:#475569;"></iframe>
        </div>

        <div class="table-responsive">
            <table class="tabla-snc" id="tablaSNC125" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:2.2rem;"></th>
                        <th>#</th>
                        <th>No. Exped.</th>
                        <th>C.I.</th>
                        <th>Nombre y Apellidos</th>
                        <th>C. Costo</th>
                        <th>Área</th>
                        <th>F. Alta</th>
                        <th>F. Baja</th>
                        <th>Años Serv.</th>
                        <th>Estado</th>
                        <th>Total Devengado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trabajadores as $i => $t):
                        $fa = $t['fecha_alta'];
                        $fb = $t['fecha_baja'];
                        $activo = (int)$t['activo'];
                    ?>
                    <tr data-id="<?php echo (int)$t['id']; ?>"
                        data-nombre="<?php echo htmlspecialchars((string)$t['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-alta-year="<?php echo $fa ? date('Y', strtotime($fa)) : ''; ?>"
                        data-alta-month="<?php echo $fa ? date('m', strtotime($fa)) : ''; ?>"
                        data-baja-year="<?php echo $fb ? date('Y', strtotime($fb)) : ''; ?>"
                        data-baja-month="<?php echo $fb ? date('m', strtotime($fb)) : ''; ?>"
                        data-fecha-alta="<?php echo $fa ?? ''; ?>"
                        data-fecha-baja="<?php echo $fb ?? ''; ?>"
                        data-costo="<?php echo htmlspecialchars((string)($t['c_costo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-area="<?php echo htmlspecialchars((string)($t['area_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-activo="<?php echo $activo; ?>"
                        data-valor-dev="<?php echo number_format((float)$t['total_devengado'], 2, '.', ''); ?>">
                        <td>
                            <input type="radio" name="trabajador_snc125" value="<?php echo (int)$t['id']; ?>"
                                   class="form-check-input sel-trabajador" aria-label="Seleccionar <?php echo htmlspecialchars((string)$t['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <td style="color:rgba(255,255,255,.4);"><?php echo $i + 1; ?></td>
                        <td><a href="empleados.php?editar=<?php echo (int)$t['id']; ?>" style="color:#60a5fa;text-decoration:none;font-family:'Consolas',monospace;font-weight:600;"><?php echo htmlspecialchars((string)$t['codigo'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                        <td><a href="empleados.php?editar=<?php echo (int)$t['id']; ?>" style="color:#93c5fd;text-decoration:none;font-family:'Consolas',monospace;"><?php echo htmlspecialchars((string)$t['ci'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                        <td><?php echo htmlspecialchars((string)$t['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;" data-tooltip="<?php echo htmlspecialchars((string)($t['c_costo_nombre'] ?? 'Sin centro de costo'), ENT_QUOTES, 'UTF-8'); ?>" data-tooltip-theme="secondary">
                            <span class="badge-codigo"><?php echo htmlspecialchars((string)($t['c_costo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars((string)($t['area_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $fa ? date('d/m/Y', strtotime($fa)) : '—'; ?></td>
                        <td><?php echo $fb ? date('d/m/Y', strtotime($fb)) : '—'; ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars((string)$t['antiguedad'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;">
                            <?php if ($activo === 1): ?>
                            <span class="chip chip-ok"><i class="fas fa-circle-check"></i> Activo</span>
                            <?php else: ?>
                            <span class="chip chip-baja"><i class="fas fa-circle-minus"></i> <?php echo $fb ? 'Baja' : 'Inactivo'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;font-family:'Consolas',monospace;">$<?php echo number_format((float)$t['total_devengado'], 2, '.', ','); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script>
var SNC_FILTROS_125 = {
    aniosAlta: <?php echo json_encode($aniosAlta); ?>,
    mesesAlta: <?php echo json_encode($mesesAlta); ?>,
    sinFechaAlta: <?php echo intval($sinFechaAlta); ?>,
    aniosBaja: <?php echo json_encode($aniosBaja); ?>,
    mesesBaja: <?php echo json_encode($mesesBaja); ?>,
    sinFechaBaja: <?php echo intval($sinFechaBaja); ?>,
    ccostos: <?php echo json_encode($ccostos); ?>,
    areas: <?php echo json_encode($areas); ?>,
    totalActivos: <?php echo intval($totalActivos); ?>,
    totalInactivos: <?php echo intval($totalInactivos); ?>
};
var SNC_EMPRESA = <?php echo json_encode($config_empresa['nombre_empresa']); ?>;
var SNC_NIT = <?php echo json_encode($config_empresa['nit']); ?>;
var SNC_LOGO = <?php echo json_encode($logo_base64); ?>;
var SNC_JEFE = <?php echo json_encode($config_empresa['jefe_proyecto']); ?>;
var SNC_ESP_GESTION = <?php echo json_encode($config_empresa['especialista_gestion']); ?>;
var SNC_ESP_RRHH = <?php echo json_encode($config_empresa['especialista_gestionRRHH']); ?>;
var SNC_USUARIO = <?php echo json_encode($_SESSION['user_nombre'] ?? 'Usuario'); ?>;

var PRINT_TOOLBAR_HTML = <?php echo json_encode(snc125_barra_impresion()); ?>;
var tablaSNC125 = null;
var trabajadorSel = 0;
var nombreSel = '';

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mainContainer = document.getElementById('mainContainer');
    if (toggleBtn && mainContainer) {
        toggleBtn.addEventListener('click', function () { mainContainer.classList.toggle('expanded'); });
    }

    if (jQuery.fn.DataTable.isDataTable('#tablaSNC125')) {
        jQuery('#tablaSNC125').DataTable().destroy();
    }

    tablaSNC125 = jQuery('#tablaSNC125').DataTable({
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ trabajadores',
            infoEmpty: 'Sin trabajadores',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            zeroRecords: 'No se encontraron coincidencias',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        },
        order: [[2, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [0] },
            { className: 'text-center', targets: [1, 2, 3, 5, 7, 8, 9, 10] },
            { className: 'text-right', targets: [11] }
        ]
    });

    // ---------- FILTROS (misma lógica que snc225.php) ----------
    var F = SNC_FILTROS_125;
    var meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var SIN = '_sin';

    var $altaAnio = jQuery('#filtroAltaAnio');
    $altaAnio.append('<option value="">Todos</option>');
    F.aniosAlta.forEach(function (a) { $altaAnio.append('<option value="' + a + '">' + a + '</option>'); });
    if (F.sinFechaAlta > 0) $altaAnio.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaAlta + ')</option>');

    var $altaMes = jQuery('#filtroAltaMes');
    $altaMes.append('<option value="">Todos</option>');

    function cargarMesesAlta(anio) {
        $altaMes.find('option:gt(0)').remove();
        if (anio === SIN) {
            $altaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (anio && F.mesesAlta[anio]) {
            Object.keys(F.mesesAlta[anio]).sort(function (a, b) { return a - b; }).forEach(function (m) {
                var cnt = F.mesesAlta[anio][m];
                $altaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + cnt + ')</option>');
            });
            $altaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (!anio) {
            var todos = {};
            Object.values(F.mesesAlta).forEach(function (obj) { Object.keys(obj).forEach(function (m) { todos[m] = (todos[m] || 0) + obj[m]; }); });
            Object.keys(todos).sort(function (a, b) { return a - b; }).forEach(function (m) {
                $altaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + todos[m] + ')</option>');
            });
            if (F.sinFechaAlta > 0) $altaMes.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaAlta + ')</option>');
        }
    }
    cargarMesesAlta('');

    var $bajaAnio = jQuery('#filtroBajaAnio');
    $bajaAnio.append('<option value="">Todos</option>');
    F.aniosBaja.forEach(function (a) { $bajaAnio.append('<option value="' + a + '">' + a + '</option>'); });
    if (F.sinFechaBaja > 0) $bajaAnio.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaBaja + ')</option>');

    var $bajaMes = jQuery('#filtroBajaMes');
    $bajaMes.append('<option value="">Todos</option>');

    function cargarMesesBaja(anio) {
        $bajaMes.find('option:gt(0)').remove();
        if (anio === SIN) {
            $bajaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (anio && F.mesesBaja[anio]) {
            Object.keys(F.mesesBaja[anio]).sort(function (a, b) { return a - b; }).forEach(function (m) {
                var cnt = F.mesesBaja[anio][m];
                $bajaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + cnt + ')</option>');
            });
            $bajaMes.append('<option value="' + SIN + '">Sin Fecha</option>');
        } else if (!anio) {
            var todosB = {};
            Object.values(F.mesesBaja).forEach(function (obj) { Object.keys(obj).forEach(function (m) { todosB[m] = (todosB[m] || 0) + obj[m]; }); });
            Object.keys(todosB).sort(function (a, b) { return a - b; }).forEach(function (m) {
                $bajaMes.append('<option value="' + (m < 10 ? '0' + m : m) + '">' + meses[m] + ' (' + todosB[m] + ')</option>');
            });
            if (F.sinFechaBaja > 0) $bajaMes.append('<option value="' + SIN + '">Sin Fecha (' + F.sinFechaBaja + ')</option>');
        }
    }
    cargarMesesBaja('');

    var $costo = jQuery('#filtroCosto');
    $costo.append('<option value="">Todos</option>');
    F.ccostos.forEach(function (c) { $costo.append('<option value="' + c.codigo + '">' + c.codigo + ' - ' + c.nombre + '</option>'); });

    var $area = jQuery('#filtroArea');
    $area.append('<option value="">Todos</option>');
    F.areas.forEach(function (a) { $area.append('<option value="' + a + '">' + a + '</option>'); });

    var $estado = jQuery('#filtroEstado');
    $estado.append('<option value="">Todos</option>');
    $estado.append('<option value="1">Activo (' + F.totalActivos + ')</option>');
    $estado.append('<option value="0">Inactivo (' + F.totalInactivos + ')</option>');

    jQuery.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable && settings.nTable.id !== 'tablaSNC125') return true;
        var row = tablaSNC125.row(dataIndex).node();
        if (!row) return true;
        var fAltaAnio = jQuery('#filtroAltaAnio').val();
        var fAltaMes = jQuery('#filtroAltaMes').val();
        var fBajaAnio = jQuery('#filtroBajaAnio').val();
        var fBajaMes = jQuery('#filtroBajaMes').val();
        var fCosto = jQuery('#filtroCosto').val();
        var fArea = jQuery('#filtroArea').val();
        var fEstado = jQuery('#filtroEstado').val();
        var corte = jQuery('#filtroFechaCalculo').val();
        var $row = jQuery(row);
        var altaYear = $row.attr('data-alta-year') || '';
        var altaMonth = $row.attr('data-alta-month') || '';
        var bajaYear = $row.attr('data-baja-year') || '';
        var bajaMonth = $row.attr('data-baja-month') || '';
        var costo = $row.attr('data-costo') || '';
        var area = $row.attr('data-area') || '';
        var activo = $row.attr('data-activo');
        var fechaAlta = $row.attr('data-fecha-alta') || '';
        var fechaBaja = $row.attr('data-fecha-baja') || '';

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
        // Corte: solo trabajadores en servicio en esa fecha.
        if (corte) {
            if (fechaAlta && fechaAlta > corte) return false;
            if (fechaBaja && fechaBaja < corte) return false;
        }
        return true;
    });

    jQuery('#filtroAltaAnio').on('change', function () { cargarMesesAlta(this.value); tablaSNC125.draw(); });
    jQuery('#filtroAltaMes').on('change', function () { tablaSNC125.draw(); });
    jQuery('#filtroBajaAnio').on('change', function () { cargarMesesBaja(this.value); tablaSNC125.draw(); });
    jQuery('#filtroBajaMes').on('change', function () { tablaSNC125.draw(); });
    jQuery('#filtroCosto, #filtroArea, #filtroEstado, #filtroFechaCalculo').on('change', function () { tablaSNC125.draw(); });

    jQuery('#btnLimpiarFiltrosSNC').on('click', function () {
        cargarMesesAlta('');
        cargarMesesBaja('');
        jQuery('#filtroAltaAnio').val('');
        jQuery('#filtroAltaMes').val('');
        jQuery('#filtroBajaAnio').val('');
        jQuery('#filtroBajaMes').val('');
        jQuery('#filtroCosto').val('');
        jQuery('#filtroArea').val('');
        jQuery('#filtroEstado').val('');
        jQuery('#filtroFechaCalculo').val('');
        tablaSNC125.search('').draw();
    });

    jQuery('#sncFiltrosToggle').on('click', function () {
        var $body = jQuery('#sncFiltrosBody');
        var $chevron = jQuery('#sncFiltrosChevron');
        if ($body.is(':visible')) {
            $body.slideUp(200);
            $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            $body.slideDown(200);
            $chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });

    // ---------- SELECCIÓN DEL TRABAJADOR ----------
    function actualizarBotonera() {
        var hay = trabajadorSel > 0;
        ['btnVer', 'btnWord', 'btnPdf'].forEach(function (id) {
            var b = document.getElementById(id);
            if (b) b.disabled = !hay;
        });
        var info = document.getElementById('selInfo');
        if (info) {
            info.innerHTML = hay
                ? '<i class="fas fa-user-check me-1" style="color:#34d399;"></i>Seleccionado: <strong style="color:#e2e8f0;">' + escHtmlSNC(nombreSel) + '</strong>'
                : '<i class="fas fa-hand-pointer me-1"></i>Ningún trabajador seleccionado';
        }
    }

    function seleccionar(id, nombre) {
        trabajadorSel = id;
        nombreSel = nombre || '';
        document.querySelectorAll('.sel-trabajador').forEach(function (r) {
            r.checked = String(r.value) === String(id);
        });
        document.querySelectorAll('#tablaSNC125 tbody tr').forEach(function (tr) {
            tr.classList.toggle('sel', String(tr.dataset.id) === String(id));
        });
        actualizarBotonera();
    }

    jQuery('#tablaSNC125 tbody').on('click', 'tr', function (ev) {
        if (jQuery(ev.target).is('input, a, button')) return;
        var $tr = jQuery(this);
        seleccionar(parseInt($tr.attr('data-id'), 10), $tr.attr('data-nombre'));
    });

    jQuery('#tablaSNC125 tbody').on('change', '.sel-trabajador', function () {
        var $tr = jQuery(this).closest('tr');
        seleccionar(parseInt(this.value, 10), $tr.attr('data-nombre'));
    });

    function abrirVista() {
        pedirDatosModelo('ver');
    }

    // Pide la fecha de expedición (opcional) y las observaciones antes de
    // generar el modelo. No se cierra al hacer clic fuera ni con Escape.
    function pedirDatosModelo(accion) {
        if (!trabajadorSel) return;
        Swal.fire({
            title: 'Datos del modelo',
            html: ''
                + '<div style="text-align:left;font-size:.85rem;">'
                + '<label for="swalFechaExp" style="display:block;margin-bottom:.25rem;color:#cbd5e1;">Fecha de expedición (opcional)</label>'
                + '<input type="date" id="swalFechaExp" class="swal2-input" style="width:100%;margin:0 0 .9rem;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:.5rem;padding:.45rem .6rem;color-scheme:dark;">'
                + '<div style="font-size:.72rem;color:#94a3b8;margin:-.6rem 0 .9rem;">Sin fecha, el pie del modelo queda en blanco como el modelo oficial.</div>'
                + '<label for="swalObsModelo" style="display:block;margin-bottom:.25rem;color:#cbd5e1;">Observaciones (opcional)</label>'
                + '<textarea id="swalObsModelo" maxlength="200" rows="3" style="width:100%;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:.5rem;padding:.45rem .6rem;resize:vertical;"></textarea>'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Generar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            background: '#1e293b',
            color: '#e2e8f0',
            allowOutsideClick: false,
            allowEscapeKey: false,
            focusConfirm: false,
            didOpen: function () {
                var campo = document.getElementById('swalFechaExp');
                if (campo) campo.focus();
            },
            preConfirm: function () {
                var f = document.getElementById('swalFechaExp');
                var o = document.getElementById('swalObsModelo');
                return {
                    fecha: f ? f.value : '',
                    obs: o ? (o.value || '').trim() : ''
                };
            }
        }).then(function (res) {
            if (!res.isConfirmed || !res.value) return;
            var fecha = res.value.fecha || '';
            var obs = res.value.obs || '';
            if (accion === 'word') {
                window.location.href = snc125UrlReporte('word', obs, fecha);
                return;
            }
            if (accion === 'pdf') {
                descargarPdfModelo(fecha, obs);
                return;
            }
            // Vista previa: el mismo PDF que se descarga, dentro de la página.
            verPdfModelo(fecha, obs);
        });
    }

    var btnVistaCerrar = document.getElementById('sncVistaCerrar');
    var btnVistaDescargar = document.getElementById('sncVistaDescargar');
    var btnVistaImprimir = document.getElementById('sncVistaImprimir');
    if (btnVistaCerrar) btnVistaCerrar.addEventListener('click', cerrarVistaPdf);
    if (btnVistaDescargar) btnVistaDescargar.addEventListener('click', function () {
        if (sncVistaActual) sncVistaActual.doc.save(sncVistaActual.archivo);
    });
    if (btnVistaImprimir) btnVistaImprimir.addEventListener('click', imprimirVistaPdf);
    document.addEventListener('keydown', function (ev) {
        var capa = document.getElementById('sncVistaOverlay');
        if (ev.key === 'Escape' && capa && capa.style.display === 'flex') cerrarVistaPdf();
    });

    var btnVer = document.getElementById('btnVer');
    var btnWord = document.getElementById('btnWord');
    var btnPdf = document.getElementById('btnPdf');
    if (btnVer) btnVer.addEventListener('click', abrirVista);
    if (btnWord) btnWord.addEventListener('click', function () { pedirDatosModelo('word'); });
    if (btnPdf) btnPdf.addEventListener('click', function () { pedirDatosModelo('pdf'); });

    actualizarBotonera();
    iniciarImpresionListado();
});


// ============================================
// PDF DEL MODELO (jsPDF, sin diálogo del navegador)
// Se dibuja desde los mismos bloques del modelo que usa el Word, así el PDF
// sale idéntico: hoja carta, márgenes del modelo, Arial (Helvetica), datos en
// negrita y la tabla de años con raya solo bajo AÑO e IMPORTE RECIBIDO.
// ============================================
function snc125UrlReporte(formato, obs, fecha) {
    var url = 'snc125.php?snc125_reporte=' + encodeURIComponent(formato) + '&id=' + encodeURIComponent(trabajadorSel);
    var campo = document.getElementById('filtroFechaCalculo');
    var corte = campo ? campo.value : '';
    if (corte) url += '&corte=' + encodeURIComponent(corte);
    if (fecha) url += '&fecha=' + encodeURIComponent(fecha);
    if (obs) url += '&obs=' + encodeURIComponent(obs);
    return url;
}

function descargarPdfModelo(fecha, obs) {
    if (typeof window.jspdf === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Sin librería', text: 'No está disponible jsPDF (js/jspdf.umd.min.js).' });
        return;
    }
    var url = snc125UrlReporte('datos', obs, fecha);
    Swal.fire({
        title: 'Generando PDF...',
        text: 'Espere un momento.',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
    });
    fetch(url, { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) throw new Error('El servidor respondió ' + r.status);
            return r.json();
        })
        .then(function (datos) {
            var doc = dibujarPdfModelo(datos);
            Swal.close();
            doc.save(datos.archivo || 'SC-4-09.pdf');
        })
        .catch(function (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + e.message });
        });
}

// Muestra el PDF generado dentro de la página (sin ventanas emergentes).
function verPdfModelo(fecha, obs) {
    if (typeof window.jspdf === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Sin librería', text: 'No está disponible jsPDF (js/jspdf.umd.min.js).' });
        return;
    }
    Swal.fire({
        title: 'Preparando la vista previa...',
        text: 'Espere un momento.',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
    });
    fetch(snc125UrlReporte('datos', obs, fecha), { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) throw new Error('El servidor respondió ' + r.status);
            return r.json();
        })
        .then(function (datos) {
            var doc = dibujarPdfModelo(datos);
            sncVistaActual = { doc: doc, archivo: datos.archivo || 'SC-4-09.pdf' };
            if (sncVistaUrl) {
                URL.revokeObjectURL(sncVistaUrl);
            }
            sncVistaUrl = URL.createObjectURL(doc.output('blob'));
            var marco = document.getElementById('sncVistaFrame');
            if (marco) marco.src = sncVistaUrl;
            var nombre = document.getElementById('sncVistaArchivo');
            if (nombre) nombre.textContent = sncVistaActual.archivo;
            var capa = document.getElementById('sncVistaOverlay');
            if (capa) capa.style.display = 'flex';
            Swal.close();
        })
        .catch(function (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + e.message });
        });
}

var sncVistaUrl = '';
var sncVistaActual = null;

function imprimirVistaPdf() {
    if (!sncVistaActual) return;
    var marco = document.getElementById('sncVistaFrame');
    try {
        if (marco && marco.contentWindow) {
            marco.contentWindow.focus();
            marco.contentWindow.print();
            return;
        }
    } catch (e) { /* el visor de PDF a veces no expone print() */ }
    // Si el visor no deja imprimir, se recarga el PDF con orden de impresión.
    sncVistaActual.doc.autoPrint();
    var url = URL.createObjectURL(sncVistaActual.doc.output('blob'));
    if (marco) marco.src = url;
}

function cerrarVistaPdf() {
    var capa = document.getElementById('sncVistaOverlay');
    if (capa) capa.style.display = 'none';
    var marco = document.getElementById('sncVistaFrame');
    if (marco) marco.src = 'about:blank';
}

function dibujarPdfModelo(datos) {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'pt', format: 'letter', orientation: 'portrait' });

    var IZQ = 1134 / 20;   // margen izquierdo del modelo
    var DER = 900 / 20;    // margen derecho
    var SUP = 426 / 20;    // margen superior
    var INF = 1021 / 20;   // margen inferior
    var anchoPag = doc.internal.pageSize.getWidth();
    var altoPag = doc.internal.pageSize.getHeight();
    var util = anchoPag - IZQ - DER;
    var aPt = function (twips) { return twips / 20; };

    var y = SUP;

    var estilo = function (bold, italic) {
        if (bold && italic) return 'bolditalic';
        if (bold) return 'bold';
        if (italic) return 'italic';
        return 'normal';
    };

    // Ancho real del texto: las métricas de jsPDF redondean las anchuras de
    // Helvetica a múltiplos de 10, y en frases largas el error acumulado hace
    // que un tramo se dibuje encima del anterior (el nombre de la entidad
    // aparecía pegado a "entidad"). El lienzo mide con Arial/Helvetica, que
    // coincide con lo que dibuja el visor del PDF.
    var contextoMedida = null;
    function anchoReal(texto, bold, italic, size) {
        if (!contextoMedida) {
            contextoMedida = document.createElement('canvas').getContext('2d');
        }
        contextoMedida.font = (italic ? 'italic ' : '') + (bold ? 'bold ' : '') +
            size + 'px Helvetica, Arial, sans-serif';
        return contextoMedida.measureText(texto).width;
    }

    function anchoTexto(texto, bold, italic, size) {
        doc.setFont('helvetica', estilo(bold, italic));
        doc.setFontSize(size);
        return anchoReal(texto, bold, italic, size);
    }

    function nuevaPaginaSiHaceFalta(alto) {
        if (y + alto > altoPag - INF) {
            doc.addPage();
            y = SUP;
        }
    }

    datos.bloques.forEach(function (b) {
        if (b.tipo === 'salto') {
            // Una hoja por cada década trabajada.
            doc.addPage();
            y = SUP;
            return;
        }
        if (b.tipo === 'p') {
            var size = b.size || 11;
            var factor = (b.linea === undefined) ? 1.15 : b.linea;
            var lh = size * 1.15 * factor;
            var after = (b.after === undefined ? 140 : b.after) / 20;
            var x = IZQ + (b.indent && b.indent.left ? aPt(b.indent.left) : 0);
            var xIni = x + (b.indent && b.indent.firstLine ? aPt(b.indent.firstLine) : 0);

            nuevaPaginaSiHaceFalta(lh + after);

            if (b.centro_x) {
                // Centrado sobre un punto concreto (la raya de días): los dos
                // rótulos comparten eje aunque tengan distinto ancho. Si llega
                // el texto de la frase, se mide con las métricas de jsPDF para
                // clavar el centro; si no, se usa el valor en twips del modelo.
                var totalCx = 0;
                (b.runs || []).forEach(function (r) {
                    totalCx += anchoTexto(r.texto, !!r.bold || !!b.bold, !!b.italic, size);
                });
                var centroPt;
                if (b.centro_texto) {
                    var anchoFrase = anchoTexto(b.centro_texto, !!b.bold, !!b.italic, size);
                    var anchoGuion = anchoTexto('_', !!b.bold, !!b.italic, size);
                    centroPt = IZQ + anchoFrase - (anchoGuion * (b.centro_guiones || 7)) / 2;
                } else {
                    centroPt = IZQ + aPt(b.centro_x);
                }
                xIni = centroPt - totalCx / 2;
            } else if (b.centro) {
                var total = 0;
                (b.runs || []).forEach(function (r) {
                    total += anchoTexto(r.texto, !!r.bold || !!b.bold, !!b.italic, size);
                });
                xIni = IZQ + (util - total) / 2;
            }

            // Rótulos centrados sobre la raya (DÍAS / TRABAJADOS): se escriben en
            // una sola línea. El ajuste de línea los partiría al quedar cerca del
            // margen derecho, aunque el centro sea correcto.
            if (b.centro_x) {
                (b.runs || []).forEach(function (r) {
                    var bold = !!r.bold || !!b.bold;
                    doc.setFont('helvetica', estilo(bold, !!r.italic));
                    doc.setFontSize(size);
                    doc.text(r.texto || '', xIni, y + size * 0.92);
                    xIni += anchoTexto(r.texto || '', bold, !!r.italic, size);
                });
                y += lh + after;
                return;
            }

            // Dibujo con ajuste de línea: el texto del modelo se envuelve
            // dentro del ancho útil de la hoja, igual que en Word. Sin esto,
            // las frases largas se salían del margen derecho. Cada tramo se
            // escribe en una sola cadena por línea (el PDF se puede copiar).
            var limiteDer = anchoPag - DER;
            var cx = xIni;
            var enLinea = false;

            function saltoDeLinea() {
                y += lh;
                cx = xIni;
                enLinea = false;
                nuevaPaginaSiHaceFalta(lh + after);
            }

            (b.runs || []).forEach(function (r) {
                var bold = !!r.bold || !!b.bold;
                doc.setFont('helvetica', estilo(bold, !!b.italic));
                doc.setFontSize(size);
                var texto = r.texto || '';
                var pendiente = '';
                var anchoPendiente = 0;

                function volcar() {
                    if (pendiente !== '') {
                        doc.text(pendiente, cx, y + size * 0.92);
                        cx += anchoPendiente;
                        pendiente = '';
                        anchoPendiente = 0;
                        enLinea = true;
                    }
                }

                var i = 0;
                while (i < texto.length) {
                    var fin = texto.indexOf(' ', i);
                    if (fin === -1) {
                        fin = texto.length;
                    } else {
                        fin += 1; // el espacio se arrastra al trozo
                    }
                    var trozo = texto.slice(i, fin);
                    var anchoTrozo = anchoReal(trozo, bold, !!r.italic, size);

                    if ((enLinea || pendiente !== '') && cx + anchoPendiente + anchoTrozo > limiteDer) {
                        volcar();
                        saltoDeLinea();
                        if (trozo === ' ') {
                            i = fin;
                            continue;
                        }
                    }

                    if (cx + anchoPendiente + anchoTrozo > limiteDer) {
                        // Trozo más ancho que la línea (una raya larga): se
                        // parte carácter a carácter.
                        for (var k = 0; k < trozo.length; k++) {
                            var caracter = trozo.charAt(k);
                            var anchoCaracter = anchoReal(caracter, bold, !!r.italic, size);
                            if ((enLinea || pendiente !== '') && cx + anchoPendiente + anchoCaracter > limiteDer) {
                                volcar();
                                saltoDeLinea();
                                if (caracter === ' ') {
                                    continue;
                                }
                            }
                            pendiente += caracter;
                            anchoPendiente += anchoCaracter;
                        }
                    } else {
                        pendiente += trozo;
                        anchoPendiente += anchoTrozo;
                    }
                    i = fin;
                }
                volcar();
            });
            y += lh + after;
            return;
        }

        if (b.tipo === 'anios') {
            var anchos = datos.anchos_anios.map(aPt);
            var xCol = IZQ;
            var xInicios = [];
            anchos.forEach(function (a) { xInicios.push(xCol); xCol += a; });

            // Encabezado sin bordes, como el modelo.
            nuevaPaginaSiHaceFalta(aPt(340) + aPt(420));
            var rotulos = ['AÑO', '', 'IMPORTE RECIBIDO', '', 'AÑO', '', 'IMPORTE RECIBIDO'];
            rotulos.forEach(function (rotulo, i) {
                if (rotulo === '') return;
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(11);
                var w = anchoReal(rotulo, true, false, 11);
                doc.text(rotulo, xInicios[i] + (anchos[i] - w) / 2, y + 12);
            });
            y += aPt(340);

            for (var fila = 0; fila < 5; fila++) {
                var par = (b.filas && b.filas[fila]) ? b.filas[fila] : [null, null];
                var valores = [
                    par[0] ? String(par[0].anio) : '',
                    '',
                    par[0] ? Number(par[0].importe).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '',
                    '',
                    par[1] ? String(par[1].anio) : '',
                    '',
                    par[1] ? Number(par[1].importe).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''
                ];
                var altoFila = aPt(420);
                nuevaPaginaSiHaceFalta(altoFila);
                for (var i = 0; i < anchos.length; i++) {
                    if (i % 2 !== 0) continue;
                    var x0 = xInicios[i];
                    var x1 = x0 + anchos[i];
                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.5);
                    doc.line(x0, y + altoFila - 1, x1, y + altoFila - 1);
                    if (valores[i] !== '') {
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(11);
                        var anchoValor = anchoReal(valores[i], true, false, 11);
                        doc.text(valores[i], x0 + (anchos[i] - anchoValor) / 2, y + altoFila - 3.5);
                    }
                }
                y += altoFila;
            }
            return;
        }

        // Firmas: tabla sin bordes, las líneas son los guiones bajos.
        var anchosF = datos.anchos_firmas.map(aPt);
        var xF = IZQ;
        var xFirma = [];
        anchosF.forEach(function (a) { xFirma.push(xF); xF += a; });
        var altoFirma = aPt(340);
        var textos = [
            ['_________________________________', '', '__________________________'],
            ['Nombre y Apellidos de quien certifica', '', 'Firma y cuño']
        ];
        textos.forEach(function (filaTexto) {
            nuevaPaginaSiHaceFalta(altoFirma);
            filaTexto.forEach(function (texto, i) {
                if (texto === '') return;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(11);
                var w = anchoReal(texto, false, false, 11);
                doc.text(texto, xFirma[i] + (anchosF[i] - w) / 2, y + 12);
            });
            y += altoFirma;
        });
    });

    // Numeración de hojas: "Página X de Y" al pie de cada modelo.
    var totalPaginas = doc.getNumberOfPages();
    for (var pagina = 1; pagina <= totalPaginas; pagina++) {
        doc.setPage(pagina);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        var rotuloPagina = 'Página ' + pagina + ' de ' + totalPaginas;
        var anchoPagina = anchoReal(rotuloPagina, false, false, 9);
        doc.text(rotuloPagina, IZQ + (util - anchoPagina) / 2, altoPag - 22);
    }

    return doc;
}

function escHtmlSNC(v) {
    return jQuery('<div>').text(v == null ? '' : String(v)).html();
}

function obtenerFechaHoraSNC() {
    var f = new Date();
    var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var h = f.getHours();
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12 || 12;
    return f.getDate() + ' de ' + meses[f.getMonth()] + ' ' + f.getFullYear() + ', ' + ('0' + h12).slice(-2) + ':' + ('0' + f.getMinutes()).slice(-2) + ' ' + ampm;
}

function obtenerFiltrosActivosSNC125() {
    var partes = [];
    var v;
    v = jQuery('#filtroAltaAnio').val(); if (v) { var m = jQuery('#filtroAltaMes').val(); partes.push('F. Alta: ' + (v === '_sin' ? 'sin fecha' : v) + (m && m !== '_sin' ? '/' + m : '')); }
    v = jQuery('#filtroBajaAnio').val(); if (v) { var m2 = jQuery('#filtroBajaMes').val(); partes.push('F. Baja: ' + (v === '_sin' ? 'sin fecha' : v) + (m2 && m2 !== '_sin' ? '/' + m2 : '')); }
    v = jQuery('#filtroCosto').val(); if (v) partes.push('C. Costo: ' + v);
    v = jQuery('#filtroArea').val(); if (v) partes.push('Área: ' + v);
    v = jQuery('#filtroEstado').val(); if (v !== '') partes.push('Estado: ' + (v === '1' ? 'Activo' : 'Inactivo'));
    v = jQuery('#filtroFechaCalculo').val(); if (v) partes.push('Corte: ' + v);
    return partes.length ? 'Filtros: ' + partes.join(' | ') : 'Sin filtros (todos los registros)';
}

function sncTablaHtml125(data) {
    var html = '<table class="sn-tabla"><thead><tr>';
    var numCols = data.columnas.length;
    data.columnas.forEach(function (c, i) {
        var cls = '';
        if (i === 3) cls = ' class="th-l"';
        else if (i === numCols - 1) cls = ' class="th-r"';
        html += '<th' + cls + '>' + c + '</th>';
    });
    html += '</tr></thead><tbody>';
    data.filas.forEach(function (fila) {
        html += '<tr>';
        fila.forEach(function (c, i) {
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
    html += '<td class="r">$' + data.totalDevengado.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>';
    html += '</tr></tbody></table>';
    return html;
}

function sncFirmasHtml125() {
    return '<div class="sn-firmas"><table class="sn-firmas-tabla"><tr>'
        + '<td><p class="sn-firma-label">Elaborado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_ESP_RRHH || SNC_USUARIO) + '</p><p class="sn-firma-subcargo">Especialista de Recursos Humanos</p></td>'
        + '<td><p class="sn-firma-label">Revisado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_ESP_GESTION) + '</p><p class="sn-firma-subcargo">Especialista en Gestión Económica</p></td>'
        + '<td><p class="sn-firma-label">Aprobado por:</p><p class="sn-firma-linea"></p><p class="sn-firma-cargo">' + escHtmlSNC(SNC_JEFE) + '</p><p class="sn-firma-subcargo">Director de Proyecto</p></td>'
        + '</tr></table></div>';
}

function iniciarImpresionListado() {
    var btn = document.getElementById('btnPrintSNC');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var columnas = ['No.', 'No. Exped.', 'C.I.', 'Nombre y Apellidos', 'C. Costo', 'Área', 'F. Alta', 'F. Baja', 'Años Serv.', 'Estado', 'Total Devengado'];
        var filas = [];
        var totalDevengado = 0;
        tablaSNC125.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            var $tr = jQuery(node);
            var $tds = $tr.find('td');
            var fila = [];
            for (var i = 1; i < $tds.length; i++) {
                var txt = jQuery($tds[i]).text().trim();
                if (i === $tds.length - 1) {
                    var val = parseFloat($tr.attr('data-valor-dev')) || 0;
                    totalDevengado += val;
                    txt = '$' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                fila.push(txt);
            }
            filas.push(fila);
        });
        if (!filas.length) {
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros visibles para imprimir.' });
            return;
        }

        var contenido = '<table class="sn-cabecera" cellspacing="0" cellpadding="0"><tr>';
        if (SNC_LOGO) contenido += '<td class="sn-cabecera-logo"><img src="' + SNC_LOGO + '" alt="Logo"></td>';
        contenido += '<td class="sn-cabecera-titulo"><div class="sn-empresa">' + escHtmlSNC(SNC_EMPRESA) + '</div><div class="sn-titulo">Certificación de Años de Servicios y Salarios Devengados</div><div class="sn-alcance">SC-4-09 · SNC 1-25</div></td>';
        contenido += '<td class="sn-cabecera-datos"><strong>Emisión:</strong> ' + obtenerFechaHoraSNC() + '<br><strong>NIT:</strong> ' + escHtmlSNC(SNC_NIT) + '<br><strong>Registros:</strong> ' + filas.length + '</td>';
        contenido += '</tr></table>';
        contenido += '<div class="sn-filtros">' + obtenerFiltrosActivosSNC125() + '</div>';
        contenido += sncTablaHtml125({ columnas: columnas, filas: filas, totalDevengado: totalDevengado });
        contenido += sncFirmasHtml125();

        var win = window.open('', '_blank');
        if (!win) {
            Swal.fire({ icon: 'warning', title: 'Aviso', text: 'El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes.' });
            return;
        }
        var css = '@page { size: letter portrait; margin: 12mm 14mm 18mm 14mm; }'
            + 'body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #111; margin: 0; }'
            + '.sn-cabecera { width: 100%; border-bottom: 0.125rem solid #004B87; padding-bottom: 0.5rem; margin-bottom: 0.75rem; }'
            + '.sn-cabecera-logo { width: 3cm; text-align: left; vertical-align: middle; }'
            + '.sn-cabecera-logo img { width: 3cm !important; height: 3cm !important; display: block; }'
            + '.sn-cabecera-titulo { text-align: center; vertical-align: middle; }'
            + '.sn-empresa { font-size: 14pt; font-weight: bold; color: #1f2937; }'
            + '.sn-titulo { font-size: 12pt; font-weight: bold; color: #004B87; }'
            + '.sn-alcance { font-size: 8pt; color: #444; }'
            + '.sn-cabecera-datos { font-size: 7.5pt; text-align: right; vertical-align: middle; }'
            + '.sn-tabla { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin: 0 auto; }'
            + '.sn-tabla th { background: #004B87; color: #fff; border: 0.0625rem solid #004B87; padding: 3px 5px; text-align: center; text-transform: uppercase; font-size: 7pt; }'
            + '.sn-tabla th.th-l { text-align: left; }'
            + '.sn-tabla th.th-r { text-align: right; }'
            + '.sn-tabla td { border: 0.0625rem solid #cbd5e1; padding: 2px 4px; }'
            + '.sn-tabla td.c { text-align: center; }'
            + '.sn-tabla td.r { text-align: right; font-family: Consolas, monospace; }'
            + '.sn-tabla td.l { text-align: left; }'
            + '.sn-tabla tbody tr:nth-child(even) td { background: #f1f5f9; }'
            + '.sn-tabla .total-row td { background: #e2e8f0 !important; font-weight: bold; border-top: 0.125rem solid #004B87; }'
            + '.sn-filtros { font-size: 7pt; color: #666; margin-bottom: 0.5rem; padding: 4px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; }'
            + '.sn-firmas { margin-top: 2rem; page-break-inside: avoid; }'
            + '.sn-firmas-tabla { width: 100%; border-collapse: collapse; }'
            + '.sn-firmas-tabla td { text-align: center; vertical-align: top; padding: 0.3125rem; width: 33%; }'
            + '.sn-firma-label { font-size: 8pt; font-weight: bold; margin: 0 0 2.5rem; }'
            + '.sn-firma-linea { border-bottom: 0.0625rem solid #000; margin: 0 0.625rem; }'
            + '.sn-firma-cargo { font-size: 9pt; font-weight: bold; margin: 0.25rem 0 0; }'
            + '.sn-firma-subcargo { font-size: 7pt; color: #444; margin: 0; }'
            + '@media print { .no-print { display: none !important; } }';

        win.document.open();
        win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>SC-4-09 · Certificación de Años de Servicios y Salarios Devengados (SNC 1-25)</title><style>' + css + '</style></head><body>' + PRINT_TOOLBAR_HTML + contenido + '</body></html>');
        win.document.close();
    });
}
</script>
</body>
</html>
