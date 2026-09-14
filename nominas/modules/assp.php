<?php
// modules/aporte_seguridad_social.php
// Aporte de Seguridad Social Patronal (ASSP) - PDL
require_once '../config/database.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Control de acceso (reutiliza el permiso de nóminas)
if (!permiso_puede('nominas', 'ver')) {
    permiso_denegar_acceso('Aporte Seguridad Social Patronal');
}

$puede_exportar_assp = permiso_puede('nominas', 'exportar') || permiso_puede('nominas', 'ver');

// Configuración empresa
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas'
];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa','jefe_proyecto','especialista_gestion','reeup_empresa','nit_empresa')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config_empresa[$row['parametro']] = $row['valor'];
    }
} catch (PDOException $e) {}

// Logo
$ruta_logo = '../../images/logotn.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode(file_get_contents($ruta_logo));
}

// ============================================
// PARÁMETROS DEL FILTRO (Año/Mes reales de BD)
// ============================================
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : 0;

// Años y meses reales existentes en la tabla nominas (para el filtro)
$stmt_per = $pdo->query(
    "SELECT YEAR(periodo_desde) AS y, MONTH(periodo_desde) AS m FROM nominas
     WHERE estado != 'borrador'
     GROUP BY y, m
     UNION SELECT YEAR(periodo_hasta), MONTH(periodo_hasta) FROM nominas WHERE estado != 'borrador' GROUP BY 1, 2");
$assp_meses_por_anio = [];
while ($row_per = $stmt_per->fetch(PDO::FETCH_ASSOC)) {
    $assp_meses_por_anio[(int)$row_per['y']][(int)$row_per['m']] = true;
}
$assp_anios_reales = array_keys($assp_meses_por_anio);
if (empty($assp_anios_reales)) {
    $assp_anios_reales = [(int)date('Y')];
    $assp_meses_por_anio[(int)date('Y')] = array_fill_keys(range(1, 12), true);
}
rsort($assp_anios_reales);

if (!isset($assp_meses_por_anio[$anio])) {
    $anio = $assp_anios_reales[0];
}
$assp_meses_anio = array_keys($assp_meses_por_anio[$anio]);
sort($assp_meses_anio);
if (!in_array($mes, $assp_meses_anio)) {
    $mes = $assp_meses_anio[count($assp_meses_anio) - 1];
}
$periodo = sprintf('%04d-%02d', $anio, $mes);
$periodo_desde = "$periodo-01";
$periodo_hasta = date('Y-m-t', strtotime($periodo_desde));
$assp_json_meses_por_anio = json_encode($assp_meses_por_anio);

// Tasas de aporte patronal (Resolución 15/2026 MTSS - PDL)
$TASA_SEG_SOCIAL   = 12.5;  // 12.5% Seguridad Social Patronal
$TASA_CORTO_PLAZO  = 1.5;   // 1.5% Prestaciones a Corto Plazo
$TASA_FRZA_TRAB    = 5.0;   // 5% Fuerza de Trabajo (NO aplica a PDL - pero se muestra)

// ============================================
// CONSULTA: DESGLOSE POR TIPO DE NÓMINA
// ============================================
$desglose = [];
$totales = [
    'devengado' => 0,
    'deducciones' => 0,
    'pagado' => 0,
    'aporte_seg' => 0,
    'aporte_corto' => 0,
    'aporte_frza' => 0,
    'aporte_total' => 0
];

// Mapeo de tipos de nómina al label de la tabla
$mapa_labels = [
    'automatica'    => ['label' => 'Salario Básico', 'icono' => 'fa-money-bill-wave', 'color' => '#60a5fa'],
    'extraordinaria'=> ['label' => 'H. Extras Noct', 'icono' => 'fa-clock', 'color' => '#f59e0b'],
    'vacaciones'    => ['label' => 'Vacaciones', 'icono' => 'fa-umbrella-beach', 'color' => '#34d399'],
    'ajuste'        => ['label' => 'Ajustes y Liquid.', 'icono' => 'fa-pen', 'color' => '#a78bfa'],
    'bono'          => ['label' => 'P. Rendim.', 'icono' => 'fa-gift', 'color' => '#f87171'],
];

// Orden de las filas en la tabla
$orden_tipos = ['automatica', 'extraordinaria', 'vacaciones', 'ajuste', 'bono'];

try {
    // Traer todos los registros del período
    $stmt = $pdo->prepare("
        SELECT 
            n.tipo_nomina,
            n.total_salario_devengado,
            n.importe_neto,
            COALESCE(n.contribucion_especial, 0) AS contribucion_especial,
            COALESCE(n.ingresos_personales, 0) AS ingresos_personales,
            COALESCE(n.descuentos, 0) AS descuentos,
            COALESCE(n.otras_deducciones, 0) AS otras_deducciones
        FROM nominas n
        WHERE n.periodo_desde = ?
          AND n.periodo_hasta = ?
          AND n.estado != 'borrador'
    ");
    $stmt->execute([$periodo_desde, $periodo_hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Acumular por tipo
    $acc = [];
    foreach ($filas as $f) {
        $t = strtolower(trim($f['tipo_nomina']));
        if (!isset($acc[$t])) {
            $acc[$t] = [
                'devengado' => 0,
                'deducciones' => 0,
                'pagado' => 0
            ];
        }
        $deducciones_fila = floatval($f['contribucion_especial'])
                          + floatval($f['ingresos_personales'])
                          + floatval($f['descuentos'])
                          + floatval($f['otras_deducciones']);

        $acc[$t]['devengado']   += floatval($f['total_salario_devengado']);
        $acc[$t]['deducciones'] += $deducciones_fila;
        $acc[$t]['pagado']      += floatval($f['importe_neto']);
    }

    // Construir desglose en orden
    foreach ($orden_tipos as $tipo) {
        $meta = $mapa_labels[$tipo] ?? ['label' => ucfirst($tipo), 'icono' => 'fa-tag', 'color' => '#94a3b8'];
        $valores = $acc[$tipo] ?? ['devengado' => 0, 'deducciones' => 0, 'pagado' => 0];

        $dev = round($valores['devengado'], 2);
        $ded = round($valores['deducciones'], 2);
        $pag = round($valores['pagado'], 2);

        // Cálculo de aportes patronales (sobre el PAGADO / importe neto)
        $aporte_seg   = round($pag * ($TASA_SEG_SOCIAL / 100), 2);
        $aporte_corto = round($pag * ($TASA_CORTO_PLAZO / 100), 2);
        $aporte_frza  = round($pag * ($TASA_FRZA_TRAB / 100), 2);
        $aporte_tot   = round($aporte_seg + $aporte_frza, 2);

        $desglose[$tipo] = [
            'label'        => $meta['label'],
            'icono'        => $meta['icono'],
            'color'        => $meta['color'],
            'devengado'    => $dev,
            'deducciones'  => $ded,
            'pagado'       => $pag,
            'aporte_seg'   => $aporte_seg,
            'aporte_corto' => $aporte_corto,
            'aporte_frza'  => $aporte_frza,
            'aporte_total' => $aporte_tot,
        ];

        $totales['devengado']    += $dev;
        $totales['deducciones']  += $ded;
        $totales['pagado']       += $pag;
        $totales['aporte_seg']   += $aporte_seg;
        $totales['aporte_corto'] += $aporte_corto;
        $totales['aporte_frza']  += $aporte_frza;
        $totales['aporte_total'] += $aporte_tot;
    }

} catch (PDOException $e) {
    $desglose = [];
    error_log("Error en aporte_seguridad_social: " . $e->getMessage());
}

// Años y meses reales existentes en la tabla nominas (para el filtro)
$assp_meses_por_anio = [];

$nombre_mes_actual = nombreMesEspanol($mes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Aporte Seguridad Social</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">

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
            position: relative;
            overflow: hidden;
        }
        .glass-card:hover { transform: translateY(-0.125rem); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        .btn-win { background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1); border-radius: 0.625rem; color: white; font-size:0.85rem; cursor: pointer; padding:0.5rem 1rem; display: inline-flex; align-items: center; gap:0.5rem; text-decoration: none; }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); }
        .btn-win-success { background: rgba(var(--color-success-rgb), 0.2); border-color: rgba(var(--color-success-rgb), 0.5); }
        .btn-win-warning { background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5); }
        .btn-win-danger  { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-info    { background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }
        .btn-win.disabled, .btn-win:disabled { opacity: 0.5; cursor: not-allowed; }
        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .btn-export-main { border-radius: 0.75rem 0 0 0.75rem; padding:0.625rem 1rem; }
        .btn-export-toggle { border-radius: 0 0.75rem 0.75rem 0; padding:0.625rem 0.75rem; border-left: 0.0625rem solid rgba(255, 255, 255, 0.3); }
        .btn-export-toggle::after { margin-left:0; }

        .form-control, .form-select {
            background: var(--panel) !important; border: 0.0625rem solid rgba(255,255,255,0.15) !important;
            color: #fff !important; border-radius: 0.625rem !important; padding:0.5rem 0.75rem !important; font-size:0.85rem !important;
        }
        select.form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 1rem 1rem !important;
            padding-right: 2.25rem !important;
        }
        html[data-theme="light"] select.form-select,
        html[data-theme="orgullo"] select.form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23374851' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }
        .form-control:focus, .form-select:focus { border-color: #60a5fa !important; box-shadow: 0 0 0 0.125rem rgba(96,165,250,0.2) !important; }
        .form-label { color: rgba(255,255,255,0.85); font-size:0.8rem; font-weight: 500; margin-bottom:0.375rem; }
        ::placeholder { color: rgba(255,255,255,0.35) !important; }

        /* ============ KPI CARDS ============ */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { padding:1.25rem; display: flex; align-items: center; justify-content: space-between; }
        .stat-card .stat-icon { width:3rem; height:3rem; border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; font-size:1.5rem; background: rgba(255,255,255,0.05); flex-shrink: 0; }
        .stat-card h6 { font-size:0.75rem; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; margin-bottom:0.3125rem; letter-spacing: 0.03rem; }
        .stat-card h3 { font-size:1.6rem; font-weight: 700; margin:0; color: white; }
        .stat-card small { font-size:0.7rem; color: rgba(255,255,255,0.5); }

        /* ============ TABLA DESGLOSE ============ */
        .tabla-assp {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .tabla-assp thead th {
            background: linear-gradient(135deg, rgba(59,130,246,0.25), rgba(139,92,246,0.15));
            color: #ffffff;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            padding: 0.75rem 0.5rem;
            border-bottom: 0.125rem solid rgba(96,165,250,0.4);
            text-align: right;
            white-space: nowrap;
        }
        .tabla-assp thead th:first-child { text-align: left; }
        .tabla-assp thead th.col-12-5 { width: 6.5rem; max-width: 6.5rem; white-space: normal; line-height: 1.2; }
        .tabla-assp thead th.col-deduc { width: 6rem; max-width: 6rem; white-space: normal; line-height: 1.2; }
        .tabla-assp tbody td {
            padding: 0.75rem 0.5rem;
            border-bottom: 0.0625rem solid rgba(255,255,255,0.05);
            text-align: right;
            vertical-align: middle;
            color: #e2e8f0;
            white-space: nowrap;
        }
        .tabla-assp tbody td:first-child { text-align: left; font-weight: 500; }
        .tabla-assp tbody tr:hover { background: rgba(96,165,250,0.08); }
        .tabla-assp tfoot td {
            padding: 0.875rem 0.5rem;
            background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.1));
            font-weight: 700;
            border-top: 0.125rem solid rgba(96,165,250,0.4);
            color: #ffffff;
            text-align: right;
        }
        .tabla-assp tfoot td:first-child { text-align: left; font-size: 0.9rem; }
        .tabla-assp .col-devengado { color: #60a5fa; font-weight: 600; }
        .tabla-assp .col-deducciones { color: #f87171; }
        .tabla-assp .col-pagado { color: #34d399; font-weight: 600; }
        .tabla-assp .col-aporte-seg { color: #fbbf24; font-weight: 600; }
        .tabla-assp .col-aporte-corto { color: #fb923c; font-weight: 600; }
        .tabla-assp .col-aporte-frza { color: #a78bfa; }
        .tabla-assp .col-aporte-total { color: #22d3ee; font-weight: 700; }
        .tabla-assp .fila-cero td { opacity: 0.5; }

        /* Icono de fila */
        .tipo-cell {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tipo-cell .icono-tipo {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        /* Nota al pie */
        .nota-assp {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            background: rgba(245,158,11,0.08);
            border-left: 0.25rem solid #f59e0b;
            border-radius: 0.5rem;
            margin-top: 1.25rem;
            font-size: 0.8rem;
            color: #fcd34d;
        }
        .nota-assp i { font-size: 1rem; flex-shrink: 0; margin-top: 0.125rem; }

        /* Bloque de firmas */
        .firmas-assp {
            display: flex;
            justify-content: space-around;
            margin-top: 3rem;
            padding: 0 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .firma-item {
            flex: 1 1 12rem;
            text-align: center;
            min-width: 0;
        }
        .firma-item .firma-linea {
            border-top: 0.0625rem solid rgba(255,255,255,0.3);
            width: 90%;
            margin: 3rem auto 0.5rem auto;
            padding-top: 0.5rem;
        }
        .firma-item .firma-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.04rem;
        }
        .firma-item .firma-nombre {
            font-weight: 600;
            font-size: 0.85rem;
            color: #ffffff;
            word-break: break-word;
        }

        .text-muted, .text-secondary { color: #9ca3af !important; }
        hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }

        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }

        .swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }

        /* ============ RESPONSIVE ============ */
        @supports (padding: env(safe-area-inset-top)) {
            .main-container {
                padding-top: max(1.25rem, env(safe-area-inset-top));
                padding-left: max(1.25rem, env(safe-area-inset-left));
                padding-right: max(1.25rem, env(safe-area-inset-right));
                padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
            }
        }

        @media (max-width: 1024px) {
            .main-container { margin-left: 5rem !important; padding: 1rem !important; }
            .win-topbar { flex-wrap: wrap; gap: 0.5rem; padding: 0.75rem 1rem !important; }
            .page-title h1 { font-size: 1.15rem; }
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            html, body { overflow-x: hidden; max-width: 100vw; }
            .main-container { margin-left: 0 !important; padding: 0.75rem !important; width: 100% !important; }
            .win-topbar { flex-direction: column; align-items: stretch; padding: 0.5rem 0.75rem !important; }
            .win-topbar > div:first-child { justify-content: space-between; width: 100%; }
            .page-title h1 { font-size: 0.95rem !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .page-title p { display: none; }
            .sidebar-toggle { width: 2.25rem; height: 2.25rem; }

            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .stat-card { padding: 0.875rem !important; }
            .stat-card h3 { font-size: 1.25rem !important; }
            .stat-card h6 { font-size: 0.65rem !important; }
            .stat-card .stat-icon { width: 2.25rem !important; height: 2.25rem !important; font-size: 1.1rem !important; }

            /* Tabla ASSP: scroll horizontal */
            .tabla-assp-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                position: relative;
                border-radius: 0.5rem;
            }
            .tabla-assp { min-width: 44rem; font-size: 0.75rem; }
            .tabla-assp thead th { font-size: 0.62rem; padding: 0.5rem 0.4rem; }
            .tabla-assp tbody td { padding: 0.5rem 0.4rem; font-size: 0.72rem; }
            .tabla-assp tfoot td { padding: 0.625rem 0.4rem; font-size: 0.75rem; }
            .tipo-cell .icono-tipo { width: 1.5rem; height: 1.5rem; font-size: 0.7rem; }

            /* Indicador "⟷ Desliza" */
            .tabla-assp-wrap.has-scroll::after {
                content: '⟷ Desliza para ver más';
                display: block;
                text-align: center;
                font-size: 0.6rem;
                color: rgba(255,255,255,0.35);
                padding: 0.25rem 0;
                background: rgba(0,0,0,0.2);
                pointer-events: none;
            }

            .firmas-assp { flex-direction: column; margin-top: 2rem; }
            .firma-item { flex: 1 1 100%; }
            .firma-item .firma-linea { margin-top: 2rem; }

            .nota-assp { font-size: 0.72rem; padding: 0.625rem 0.75rem; }
        }

        @media (max-width: 480px) {
            .main-container { padding: 0.4rem !important; }
            .kpi-grid { grid-template-columns: 1fr; }
            .page-title h1 { font-size: 0.85rem !important; max-width: 55vw; }
            .win-topbar { padding: 0.4rem 0.5rem !important; }
            .tabla-assp { min-width: 38rem; font-size: 0.68rem; }
            .tabla-assp thead th { font-size: 0.58rem; padding: 0.4rem 0.3rem; }
            .tabla-assp tbody td { padding: 0.4rem 0.3rem; font-size: 0.68rem; }
            .tipo-cell .icono-tipo { width: 1.25rem; height: 1.25rem; font-size: 0.62rem; }
            .btn-win { font-size: 0.72rem; padding: 0.4rem 0.7rem; }
        }

        @media (min-width: 1400px) {
            .main-container { max-width: 1800px; margin: 0 auto 0 16.25rem; }
        }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">

    <!-- ============ TOPBAR ============ -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-shield-halved me-2" style="color: #60a5fa;"></i>Aporte Seguridad Social Patronal</h1>
                <p><i class="fas fa-building-columns me-1"></i> Desglose de aporte patronal por tipo de nómina (PDL)</p>
            </div>
        </div>
        <div class="btn-group" role="group" aria-label="Imprimir y exportar">
            <button class="btn-win btn-win-primary btn-export-main" id="btnImprimir" title="Imprimir" data-tooltip="Imprimir" data-tooltip-theme="primary">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
            <button type="button" class="btn-win btn-win-primary btn-export-toggle dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar" data-tooltip="Exportar" data-tooltip-theme="info">
                <span class="visually-hidden">Exportar</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win">
                <li><a class="dropdown-item" href="#" id="btnExportarPDF" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportarExcel" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel (XLSX)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportarWord" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word (DOC)</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportarCsv" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="info"><i class="fas fa-file-csv me-2" style="color: var(--color-success);"></i>Exportar a CSV</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportarTxt" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
            </ul>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- ============ KPI CARDS ============ -->
    <div class="kpi-grid fade-in-up" style="animation-delay: 0.1s;">
        <div class="glass-card stat-card fade-in-up" style="animation-delay: 0.1s;">
            <div style="width:100%;">
                <h6 class="mb-2"><i class="fas fa-calendar-alt me-1" style="color:#60a5fa;"></i> Filtrar Período</h6>
                <div class="d-flex flex-column gap-2">
                    <select id="asspAnio" class="form-select form-select-sm" title="Año a consultar">
                        <?php foreach ($assp_anios_reales as $a_opt): ?>
                            <option value="<?php echo $a_opt; ?>" <?php echo ($a_opt == $anio ? 'selected' : ''); ?>><?php echo $a_opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="asspMes" class="form-select form-select-sm" title="Mes a consultar">
                        <?php foreach ($assp_meses_anio as $m_opt): ?>
                            <option value="<?php echo str_pad($m_opt, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($m_opt == $mes ? 'selected' : ''); ?>><?php echo nombreMesEspanol($m_opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-win btn-win-primary btn-win-sm w-100" id="btnFiltrar" title="Aplicar filtro">
                        <i class="fas fa-filter me-1"></i> Aplicar Filtrar
                    </button>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-sack-dollar me-1"></i> Devengado</h6>
                <h3 style="color: #60a5fa;">$<?php echo number_format($totales['devengado'], 2); ?></h3>
                <small>Total salario devengado</small>
            </div>
            <div class="stat-icon" style="background: rgba(96,165,250,0.15); color: #60a5fa;">
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-square-minus me-1"></i> DEDUCC. TOTALES</h6>
                <h3 style="color: #f87171;">$<?php echo number_format($totales['deducciones'], 2); ?></h3>
                <small>Total deducciones</small>
            </div>
            <div class="stat-icon" style="background: rgba(248,113,113,0.15); color: #f87171;">
                <i class="fas fa-file-invoice"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-money-bill-wave me-1"></i> Total Pagado</h6>
                <h3 style="color: #34d399;">$<?php echo number_format($totales['pagado'], 2); ?></h3>
                <small>Base para el aporte</small>
            </div>
            <div class="stat-icon" style="background: rgba(52,211,153,0.15); color: #34d399;">
                <i class="fas fa-coins"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-shield-halved me-1"></i> Aporte Seg. Social Patronal(12.5%)</h6>
                <h3 style="color: #fbbf24;">$<?php echo number_format($totales['aporte_seg'], 2); ?></h3>
                <small>Seguridad Social Patronal</small>
            </div>
            <div class="stat-icon" style="background: rgba(251,191,36,0.15); color: #fbbf24;">
                <i class="fas fa-building-columns"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-hand-holding-medical me-1"></i> Prest. Corto Plazo (1.5%)</h6>
                <h3 style="color: #fb923c;">$<?php echo number_format($totales['aporte_corto'], 2); ?></h3>
                <small>Prestac. enfermedad, etc.</small>
            </div>
            <div class="stat-icon" style="background: rgba(34,211,238,0.15); color: #22d3ee;">
                <i class="fas fa-user-doctor"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-users me-1"></i> Aporte Frza. Trab. (5%)</h6>
                <h3 style="color: #a78bfa;">$<?php echo number_format($totales['aporte_frza'], 2); ?></h3>
                <small>No aplica a PDL</small>
            </div>
            <div class="stat-icon" style="background: rgba(167,139,250,0.15); color: #a78bfa;">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div>
                <h6><i class="fas fa-vault me-1"></i> Total Aporte</h6>
                <h3 style="color: #22d3ee;">$<?php echo number_format($totales['aporte_total'], 2); ?></h3>
                <small>12.5% + 5%</small>
            </div>
            <div class="stat-icon" style="background: rgba(52,211,153,0.15); color: #34d399;">
                <i class="fas fa-vault"></i>
            </div>
        </div>
    </div>

    <!-- ============ TABLA DESGLOSE ============ -->
    <div class="glass-card fade-in-up" style="animation-delay: 0.15s; padding: 1.25rem;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-0" style="font-size:1.05rem; font-weight: 600;">
                    <i class="fas fa-file-invoice-dollar me-2" style="color:#60a5fa;"></i>
                    Desglose de Pago de Salarios para el Aporte de la Seguridad Social Patronal
                </h5>
                <p class="mb-0" style="color: rgba(255,255,255,0.5); font-size:0.78rem; margin-top:0.25rem;">
                    <i class="fas fa-calendar-check me-1"></i>
                    Período: <strong style="color:#34d399;"><?php echo htmlspecialchars($nombre_mes_actual . ' / ' . $anio); ?></strong>
                    &nbsp;·&nbsp; <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>
                </p>
            </div>
        </div>

        <?php if (empty($desglose)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
                <h5 style="color: rgba(255,255,255,0.5);">No hay nóminas contabilizadas en este período</h5>
                <p style="color: rgba(255,255,255,0.3); font-size:0.85rem;">Seleccione otro período o contabilice nóminas primero.</p>
            </div>
        <?php else: ?>
            <div class="tabla-assp-wrap">
                <table class="tabla-assp">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Tipo Nómina</th>
                            <th>DEVENGADO</th>
<th class="col-deduc">DEDUCC.<br>TOTALES</th>
<th>PAGADO<br><small style="font-weight:400; opacity:0.7;">(base aporte)</small></th>
<th class="col-12-5">12.5% Seg. Social<br>Patronal</th>
                            <th>1.5% Prest.<br><small style="font-weight:400; opacity:0.7;">Corto Plazo</small></th>
                            <th>5% Frza. Trab.<br><small style="font-weight:400; opacity:0.7;">NO aplica PDL</small></th>
                            <th>Total Aporte<br><small style="font-weight:400; opacity:0.7;">(12.5+5%)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($desglose as $tipo => $d): ?>
                            <?php $fila_cero = ($d['devengado'] == 0 && $d['deducciones'] == 0 && $d['pagado'] == 0); ?>
                            <tr class="<?php echo $fila_cero ? 'fila-cero' : ''; ?>">
                                <td>
                                    <span class="tipo-cell">
                                        <span class="icono-tipo" style="background: <?php echo $d['color']; ?>22; color: <?php echo $d['color']; ?>; border:0.0625rem solid <?php echo $d['color']; ?>44;">
                                            <i class="fas <?php echo $d['icono']; ?>"></i>
                                        </span>
                                        <?php echo htmlspecialchars($d['label']); ?>
                                    </span>
                                </td>
                                <td class="col-devengado">$<?php echo number_format($d['devengado'], 2); ?></td>
                                <td class="col-deducciones">$<?php echo number_format($d['deducciones'], 2); ?></td>
                                <td class="col-pagado">$<?php echo number_format($d['pagado'], 2); ?></td>
                                <td class="col-aporte-seg">$<?php echo number_format($d['aporte_seg'], 2); ?></td>
                                <td class="col-aporte-corto">$<?php echo number_format($d['aporte_corto'], 2); ?></td>
                                <td class="col-aporte-frza">$<?php echo number_format($d['aporte_frza'], 2); ?></td>
                                <td class="col-aporte-total">$<?php echo number_format($d['aporte_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL GRAL.</td>
                            <td>$<?php echo number_format($totales['devengado'], 2); ?></td>
                            <td>$<?php echo number_format($totales['deducciones'], 2); ?></td>
                            <td>$<?php echo number_format($totales['pagado'], 2); ?></td>
                            <td>$<?php echo number_format($totales['aporte_seg'], 2); ?></td>
                            <td>$<?php echo number_format($totales['aporte_corto'], 2); ?></td>
                            <td>$<?php echo number_format($totales['aporte_frza'], 2); ?></td>
                            <td>$<?php echo number_format($totales['aporte_total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Nota -->
            <div class="nota-assp">
                <i class="fas fa-circle-info"></i>
                <div>
                    <strong>Nota:</strong> El aporte patronal se calcula sobre el <strong>PAGADO</strong> (importe neto) de cada tipo de nómina.
                    La tasa del <strong>12.5%</strong> corresponde a la Seguridad Social Patronal y el <strong>1.5%</strong> a las Prestaciones a Corto Plazo.
                    El <strong>5% de Fuerza de Trabajo</strong> NO aplica a los Proyectos de Desarrollo Local (PDL);
                    se muestra con fines informativos/comparativos.
                </div>
            </div>

            <!-- Firmas -->
            <div class="firmas-assp">
                <div class="firma-item">
                    <div class="firma-label">Aprobado por:</div>
                    <div class="firma-linea">
                        <div class="firma-nombre"><?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?></div>
                        <small style="color:rgba(255,255,255,0.5); font-size:0.7rem;">Jefe de Proyecto</small>
                    </div>
                </div>
                <div class="firma-item">
                    <div class="firma-label">Revisado por:</div>
                    <div class="firma-linea">
                        <div class="firma-nombre"><?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?></div>
                        <small style="color:rgba(255,255,255,0.5); font-size:0.7rem;">Especialista en Gestión Económica</small>
                    </div>
                </div>
                <div class="firma-item">
                    <div class="firma-label">Elaborado por:</div>
                    <div class="firma-linea">
                        <div class="firma-nombre"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario'); ?></div>
                        <small style="color:rgba(255,255,255,0.5); font-size:0.7rem;">Especialista de Nóminas</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script src="../js/exceljs.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>

<script>
// ============================================
// DATOS PARA EXPORTACIÓN (desde PHP)
// ============================================
const ASSP_DATA = {
    empresa:        <?php echo json_encode($config_empresa['nombre_empresa']); ?>,
    jefe:           <?php echo json_encode($config_empresa['jefe_proyecto']); ?>,
    especialista:   <?php echo json_encode($config_empresa['especialista_gestion']); ?>,
    reeup:          <?php echo json_encode($config_empresa['reeup_empresa'] ?? ''); ?>,
    nit:            <?php echo json_encode($config_empresa['nit_empresa'] ?? ''); ?>,
    logo:           <?php echo json_encode($logo_base64); ?>,
    usuario:        <?php echo json_encode($_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario'); ?>,
    periodo:        <?php echo json_encode($periodo); ?>,
    periodo_label:  <?php echo json_encode($nombre_mes_actual . ' / ' . $anio); ?>,
    desglose:       <?php echo json_encode(array_values($desglose), JSON_UNESCAPED_UNICODE); ?>,
    totales:        <?php echo json_encode($totales); ?>,
    tasas: {
        seg_social: <?php echo $TASA_SEG_SOCIAL; ?>,
        corto_plazo: <?php echo $TASA_CORTO_PLAZO; ?>,
        frza_trab:  <?php echo $TASA_FRZA_TRAB; ?>
    }
};

// ============================================
// FILTRO DE PERÍODO (Año y Mes)
// ============================================
const ASSP_MESES_POR_ANIO = <?php echo $assp_json_meses_por_anio; ?>;

function asspActualizarMeses() {
    const anioEl = document.getElementById('asspAnio');
    const mesEl = document.getElementById('asspMes');
    if (!anioEl || !mesEl) return;
    const anioSel = parseInt(anioEl.value, 10);
    const previo = parseInt(mesEl.value, 10);
    const meses = ASSP_MESES_POR_ANIO[anioSel] || {};
    mesEl.innerHTML = '';
    const nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    Object.keys(meses).map(Number).sort((a, b) => a - b).forEach(function (m) {
        const opt = document.createElement('option');
        opt.value = String(m).padStart(2, '0');
        opt.textContent = nombres[m - 1] || m;
        if (m === previo) opt.selected = true;
        mesEl.appendChild(opt);
    });
}

document.getElementById('asspAnio')?.addEventListener('change', asspActualizarMeses);
asspActualizarMeses();

document.getElementById('btnFiltrar')?.addEventListener('click', function () {
    const anio = document.getElementById('asspAnio').value;
    const mes = document.getElementById('asspMes').value;
    if (anio && mes) {
        window.location.href = 'assp.php?anio=' + encodeURIComponent(anio) + '&mes=' + encodeURIComponent(mes);
    }
});

// ============================================
// DETECTAR SCROLL HORIZONTAL EN LA TABLA
// ============================================
function marcarTablaScroll() {
    document.querySelectorAll('.tabla-assp-wrap').forEach(function (w) {
        if (w.scrollWidth > w.clientWidth + 5) {
            w.classList.add('has-scroll');
        } else {
            w.classList.remove('has-scroll');
        }
    });
}
marcarTablaScroll();
window.addEventListener('resize', function () {
    clearTimeout(window._tblAsspT);
    window._tblAsspT = setTimeout(marcarTablaScroll, 250);
});

// ============================================
// UTILIDADES
// ============================================
function fmtMoneda(v) {
    return '$' + Number(v || 0).toLocaleString('es-CU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function obtenerFechaHora() {
    const d = new Date();
    return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}
function descargarBlob(blob, nombre) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = nombre;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
}
function construirTablaBodyHTML(paraExcel) {
    let html = '';
    ASSP_DATA.desglose.forEach(function (d) {
        html += '<tr>'
            + '<td style="text-align:left;font-weight:600;">' + d.label + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.devengado) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.deducciones) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.pagado) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_seg) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_corto) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_frza) + '</td>'
            + '<td style="text-align:right;font-weight:bold;">' + fmtMoneda(d.aporte_total) + '</td>'
            + '</tr>';
    });
    return html;
}
function construirTablaTotalHTML() {
    const t = ASSP_DATA.totales;
    return '<tr style="background:#e8eefc;font-weight:bold;">'
        + '<td style="text-align:left;">TOTAL GRAL.</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.devengado) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.deducciones) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.pagado) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_seg) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_corto) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_frza) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_total) + '</td>'
        + '</tr>';
}

// ============================================
// EXPORTAR A EXCEL (con formato y fórmulas)
// ============================================
document.getElementById('btnExportarExcel')?.addEventListener('click', function () {
    if (typeof ExcelJS === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'La librería ExcelJS no cargó.', background: '#1a1a2e', color: '#fff' });
        return;
    }
    const wb = new ExcelJS.Workbook();
    wb.creator = ASSP_DATA.empresa || 'TransNuBeT';
    wb.created = new Date();
    const periodoPartes = String(ASSP_DATA.periodo).split('-');
    const nombreHoja = 'ASSP-' + (periodoPartes[1] || '') + '-' + (periodoPartes[0] || '');
    const ws = wb.addWorksheet(nombreHoja, {
        pageSetup: { orientation: 'landscape', fitToPage: true, paperSize: 1, margins: { left: 0.5, right: 0.5, top: 0.6, bottom: 0.6, header: 0.3, footer: 0.3 } },
        properties: { tabColor: { argb: 'FF004B87' } }
    });
    ws.views = [{ state: 'frozen', ySplit: 5 }];

    const colW = [24, 16, 14, 16, 18, 16, 18, 18];
    for (let cw = 0; cw < colW.length; cw++) { ws.getColumn(cw + 1).width = colW[cw]; }

    const AZUL = 'FF004B87';
    const AZUL_CLARO = 'FFDCE6F1';
    const GRIS_ALT = 'FFF5F8FC';

    function celda(fila, col, valor, opts) {
        opts = opts || {};
        const c = ws.getCell(fila, col);
        c.value = valor;
        c.font = { name: 'Arial', size: opts.size || 10, bold: !!opts.bold, color: opts.color ? { argb: opts.color } : { argb: 'FF111111' } };
        c.alignment = { horizontal: opts.align || 'left', vertical: 'middle', wrapText: !!opts.wrap };
        if (opts.fill) c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: opts.fill } };
        if (opts.border) c.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
        if (opts.numFmt) c.numFmt = opts.numFmt;
        if (opts.formula) c.value = { formula: opts.formula, result: valor };
        return c;
    }

    let f = 1;
    celda(f, 1, 'DESGLOSE DE PAGO DE SALARIOS PARA EL APORTE DE LA SEGURIDAD SOCIAL PATRONAL', { bold: true, size: 13, align: 'center', fill: AZUL, color: 'FFFFFFFF' });
    ws.mergeCells(f, 1, f, 8);
    ws.getRow(f).height = 26;
    f++;
    celda(f, 1, String(ASSP_DATA.empresa).toUpperCase(), { bold: true, size: 11, align: 'center' });
    ws.mergeCells(f, 1, f, 8);
    f++;
    celda(f, 1, 'Período: ' + ASSP_DATA.periodo_label + '    ·    REEUP: ' + (ASSP_DATA.reeup || '—') + '    ·    NIT: ' + (ASSP_DATA.nit || '—') + '    ·    Emisión: ' + obtenerFechaHora(), { align: 'center' });
    ws.mergeCells(f, 1, f, 8);
    f++;
    f++;

    const filaHeader = f;
    const headers = ['Tipo Nómina', 'DEVENGADO', 'DEDUCC. TOTALES', 'PAGADO (base aporte)', '12.5% Seg. Social Patronal', '1.5% Prest. Corto Plazo', '5% Frza. Trab.', 'Total Aporte (12.5+5%)'];
    for (let hi = 0; hi < headers.length; hi++) {
        celda(filaHeader, hi + 1, headers[hi], { bold: true, size: 9, align: 'center', fill: AZUL, color: 'FFFFFFFF', border: true, wrap: true });
    }
    ws.getRow(filaHeader).height = 34;
    f++;

    const filaDatosIni = f;
    const tasaSeg = 12.5, tasaCorto = 1.5, tasaFrza = 5;
    ASSP_DATA.desglose.forEach(function (d) {
        ws.getRow(f).height = 18;
        celda(f, 1, d.label, { bold: true, align: 'left', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 2, d.devengado, { align: 'right', numFmt: '$#,##0.00', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 3, d.deducciones, { align: 'right', numFmt: '$#,##0.00', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 4, d.pagado, { align: 'right', numFmt: '$#,##0.00', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 5, d.aporte_seg, { align: 'right', numFmt: '$#,##0.00', formula: 'ROUND(D' + f + '*(' + tasaSeg + '/100),2)', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 6, d.aporte_corto, { align: 'right', numFmt: '$#,##0.00', formula: 'ROUND(D' + f + '*(' + tasaCorto + '/100),2)', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 7, d.aporte_frza, { align: 'right', numFmt: '$#,##0.00', formula: 'ROUND(D' + f + '*(' + tasaFrza + '/100),2)', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        celda(f, 8, d.aporte_total, { align: 'right', numFmt: '$#,##0.00', formula: 'ROUND(E' + f + '+G' + f + ',2)', fill: (f % 2 === 0 ? GRIS_ALT : null), border: true });
        f++;
    });

    const filaTotal = f;
    const filaIniTotal = filaDatosIni, filaFinTotal = f - 1;
    celda(filaTotal, 1, 'TOTAL GRAL.', { bold: true, align: 'left', fill: AZUL_CLARO, border: true });
    const sumCols = [2, 3, 4, 5, 6, 7, 8];
    sumCols.forEach(function (sc) {
        const letra = String.fromCharCode(64 + sc);
        const valorRef = sc === 2 ? ASSP_DATA.totales.devengado : sc === 3 ? ASSP_DATA.totales.deducciones : sc === 4 ? ASSP_DATA.totales.pagado : sc === 5 ? ASSP_DATA.totales.aporte_seg : sc === 6 ? ASSP_DATA.totales.aporte_corto : sc === 7 ? ASSP_DATA.totales.aporte_frza : ASSP_DATA.totales.aporte_total;
        celda(filaTotal, sc, valorRef, { bold: true, align: 'right', numFmt: '$#,##0.00', formula: 'SUM(' + letra + filaIniTotal + ':' + letra + filaFinTotal + ')', fill: AZUL_CLARO, border: true });
    });
    ws.getRow(filaTotal).height = 20;
    f++;
    f++;

    const firmasL = ['Elaborado por:', 'Revisado por:', 'Aprobado por:'];
    celda(f, 1, firmasL[0], { bold: true });
    celda(f, 4, firmasL[1], { bold: true });
    celda(f, 7, firmasL[2], { bold: true });
    f++;
    celda(f, 1, String(ASSP_DATA.usuario).toUpperCase(), { bold: true });
    celda(f, 4, String(ASSP_DATA.especialista || 'SIN ESPECIALISTA DEFINIDO').toUpperCase(), { bold: true });
    celda(f, 7, String(ASSP_DATA.jefe || '').toUpperCase(), { bold: true });
    f++;
    celda(f, 1, 'Especialista de Nóminas');
    celda(f, 4, 'Especialista en Gestión Económica');
    celda(f, 7, 'Jefe de Proyecto');

    wb.xlsx.writeBuffer().then(function (buffer) {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Aporte_Seg_Social_' + ASSP_DATA.periodo + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
    }).catch(function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el archivo Excel.', background: '#1a1a2e', color: '#fff' });
    });
});

function descargarBlobAssp(blob, nombre) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
}
function nombreArchivoAssp(ext) {
    return 'Aporte_Seg_Social_' + ASSP_DATA.periodo + '.' + ext;
}
function escHtmlAssp(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
function construirHtmlWordAssp() {
    const t = ASSP_DATA.totales;
    const filas = ASSP_DATA.desglose.map(function (d) {
        return '<tr>'
            + '<td style="text-align:left;font-weight:bold;">' + escHtmlAssp(d.label) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.devengado) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.deducciones) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.pagado) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_seg) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_corto) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_frza) + '</td>'
            + '<td style="text-align:right;">' + fmtMoneda(d.aporte_total) + '</td>'
            + '</tr>';
    }).join('');
    const totalRow = '<tr style="background:#dce6f1;font-weight:bold;">'
        + '<td style="text-align:left;">TOTAL GRAL.</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.devengado) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.deducciones) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.pagado) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_seg) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_corto) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_frza) + '</td>'
        + '<td style="text-align:right;">' + fmtMoneda(t.aporte_total) + '</td>'
        + '</tr>';
    const headers = ['Tipo Nómina', 'DEVENGADO', 'DEDUCC. TOTALES', 'PAGADO (base aporte)', '12.5% Seg. Social Patronal', '1.5% Prest. Corto Plazo', '5% Frza. Trab. (NO aplica PDL)', 'Total Aporte (12.5+5%)'];
    const encabezados = headers.map(function (h) { return '<th style="border:0.0625rem solid #000;padding:0.25rem;background:#004b87;color:#fff;text-align:center;font-size:8pt;">' + escHtmlAssp(h) + '</th>'; }).join('');
    return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
        + '<head><meta charset="utf-8"><title>' + escHtmlAssp(ASSP_DATA.empresa) + ' - Aporte Seguridad Social</title>'
        + '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/><w:PageSetup><w:Orientation>Landscape</w:Orientation><w:PageWidth>11in</w:PageWidth><w:PageHeight>8.5in</w:PageHeight></w:PageSetup></w:WordDocument></xml><![endif]-->'
        + '<style>@page WordSection1 { size: 11in 8.5in; margin:0.6in; } div.WordSection1 { page: WordSection1; } body { font-family: Arial, sans-serif; font-size:9pt; color:#000; } h1 { font-size:14pt; text-align:center; margin:0 0 0.25rem; } h2 { font-size:11pt; text-align:center; font-weight:normal; margin:0 0 0.25rem; } .periodo { text-align:center; font-size:9pt; margin-bottom:0.5rem; } table { border-collapse: collapse; width:100%; } td { border:0.0625rem solid #000; padding:0.25rem; font-size:8.5pt; }</style></head>'
        + '<body><div class="WordSection1">'
        + '<h1>' + escHtmlAssp(String(ASSP_DATA.empresa).toUpperCase()) + '</h1>'
        + '<h2>DESGLOSE DE PAGO DE SALARIOS PARA EL APORTE DE LA SEGURIDAD SOCIAL PATRONAL</h2>'
        + '<p class="periodo">Período: ' + escHtmlAssp(ASSP_DATA.periodo_label) + ' &nbsp;·&nbsp; REEUP: ' + escHtmlAssp(ASSP_DATA.reeup || '—') + ' &nbsp;·&nbsp; NIT: ' + escHtmlAssp(ASSP_DATA.nit || '—') + ' &nbsp;·&nbsp; Emisión: ' + escHtmlAssp(obtenerFechaHora()) + '</p>'
        + '<table><thead><tr>' + encabezados + '</tr></thead><tbody>' + filas + totalRow + '</tbody></table>'
        + '<p style="font-size:7.5pt;">Nota: El aporte patronal se calcula sobre el PAGADO (importe neto). El 12.5% es Seguridad Social, el 1.5% Prestaciones a Corto Plazo y el 5% Fuerza de Trabajo. El 5% NO aplica a PDL.</p>'
        + '<table style="width:100%;" border="0"><tr>'
        + '<td style="border:none;text-align:center;font-size:8pt;"><b>Aprobado por:</b><br><br><br><u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u><br>' + escHtmlAssp(String(ASSP_DATA.jefe || '').toUpperCase()) + '<br><span style="font-size:7pt;">Jefe de Proyecto</span></td>'
        + '<td style="border:none;text-align:center;font-size:8pt;"><b>Revisado por:</b><br><br><br><u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u><br>' + escHtmlAssp(String(ASSP_DATA.especialista || '').toUpperCase()) + '<br><span style="font-size:7pt;">Especialista en Gestión Económica</span></td>'
        + '<td style="border:none;text-align:center;font-size:8pt;"><b>Elaborado por:</b><br><br><br><u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u><br>' + escHtmlAssp(String(ASSP_DATA.usuario || '').toUpperCase()) + '<br><span style="font-size:7pt;">Especialista de Nóminas</span></td>'
        + '</tr></table></div></body></html>';
}
document.getElementById('btnExportarWord')?.addEventListener('click', function (e) {
    e.preventDefault();
    const html = construirHtmlWordAssp();
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    descargarBlobAssp(blob, nombreArchivoAssp('doc'));
});
document.getElementById('btnExportarCsv')?.addEventListener('click', function (e) {
    e.preventDefault();
    const t = ASSP_DATA.totales;
    const headers = ['Tipo Nómina', 'DEVENGADO', 'DEDUCC. TOTALES', 'PAGADO (base aporte)', '12.5% Seg. Social Patronal', '1.5% Prest. Corto Plazo', '5% Frza. Trab. (NO aplica PDL)', 'Total Aporte (12.5+5%)'];
    const filas = [headers];
    ASSP_DATA.desglose.forEach(function (d) {
        filas.push([d.label, d.devengado, d.deducciones, d.pagado, d.aporte_seg, d.aporte_corto, d.aporte_frza, d.aporte_total]);
    });
    filas.push(['TOTAL GRAL.', t.devengado, t.deducciones, t.pagado, t.aporte_seg, t.aporte_corto, t.aporte_frza, t.aporte_total]);
    const csv = filas.map(function (f) {
        return f.map(function (v) {
            const num = Number(v);
            const s = (typeof v === 'number' && !isNaN(num)) ? num.toFixed(2) : String(v == null ? '' : v);
            return /[;"\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        }).join(';');
    }).join('\r\n');
    descargarBlobAssp(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }), nombreArchivoAssp('csv'));
});
document.getElementById('btnExportarTxt')?.addEventListener('click', function (e) {
    e.preventDefault();
    const t = ASSP_DATA.totales;
    const lineas = [];
    lineas.push('APORTE SEGURIDAD SOCIAL PATRONAL');
    lineas.push('Empresa: ' + ASSP_DATA.empresa);
    lineas.push('Período: ' + ASSP_DATA.periodo_label + '   |   REEUP: ' + (ASSP_DATA.reeup || '—') + '   |   NIT: ' + (ASSP_DATA.nit || '—'));
    lineas.push('Generado: ' + obtenerFechaHora());
    lineas.push('============================================================');
    lineas.push(['Tipo Nómina', 'DEVENGADO', 'DEDUCC. TOTALES', 'PAGADO (base aporte)', '12.5% Seg. Social Patronal', '1.5% Prest. Corto Plazo', '5% Frza. Trab. (NO aplica PDL)', 'Total Aporte (12.5+5%)'].join('\t'));
    ASSP_DATA.desglose.forEach(function (d) {
        lineas.push([d.label, fmtMoneda(d.devengado), fmtMoneda(d.deducciones), fmtMoneda(d.pagado), fmtMoneda(d.aporte_seg), fmtMoneda(d.aporte_corto), fmtMoneda(d.aporte_frza), fmtMoneda(d.aporte_total)].join('\t'));
    });
    lineas.push(['TOTAL GRAL.', fmtMoneda(t.devengado), fmtMoneda(t.deducciones), fmtMoneda(t.pagado), fmtMoneda(t.aporte_seg), fmtMoneda(t.aporte_corto), fmtMoneda(t.aporte_frza), fmtMoneda(t.aporte_total)].join('\t'));
    lineas.push('============================================================');
    lineas.push('Nota: El aporte patronal se calcula sobre el PAGADO (importe neto). El 12.5% es Seguridad Social, el 1.5% Prestaciones a Corto Plazo y el 5% Fuerza de Trabajo. El 5% NO aplica a PDL.');
    descargarBlobAssp(new Blob([lineas.join('\r\n')], { type: 'text/plain;charset=utf-8' }), nombreArchivoAssp('txt'));
});

// ============================================
// EXPORTAR A PDF (idéntico a la vista)
// ============================================
document.getElementById('btnExportarPDF')?.addEventListener('click', function () {
    if (typeof window.jspdf === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'La librería PDF no cargó.', background: '#1a1a2e', color: '#fff' });
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'letter' });
    const pageW = doc.internal.pageSize.getWidth();
    let y = 40;

    // Logo
    if (ASSP_DATA.logo) {
        try { doc.addImage(ASSP_DATA.logo, 'PNG', 30, 25, 55, 55); } catch (e) {}
    }

    // Encabezado
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.setTextColor(0, 0, 0);
    doc.text(ASSP_DATA.empresa.toUpperCase(), pageW / 2, y, { align: 'center' });
    y += 16;
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.text('Desglose de Pago de Salarios para el Aporte de la Seguridad Social Patronal', pageW / 2, y, { align: 'center' });
    y += 14;
    doc.setFontSize(9);
    doc.setTextColor(0, 0, 0);
    doc.text('Período: ' + ASSP_DATA.periodo_label + '    ·    REEUP: ' + (ASSP_DATA.reeup || '—') + '    ·    NIT: ' + (ASSP_DATA.nit || '—'), pageW / 2, y, { align: 'center' });
    y += 20;

    // Tabla principal
    const head = [[
        { content: 'Tipo Nómina', styles: { halign: 'left' } },
        { content: 'DEVENGADO', styles: { halign: 'right' } },
        { content: 'DEDUCC. TOTALES', styles: { halign: 'right' } },
        { content: 'PAGADO (base aporte)', styles: { halign: 'right' } },
{ content: '12.5% Seg. Social Patronal', styles: { halign: 'right' } },
            { content: '1.5% Prest. Corto Plazo', styles: { halign: 'right' } },
            { content: '5% Frza. Trab. (NO aplica PDL)', styles: { halign: 'right' } },
            { content: 'Total Aporte (12.5+5%)', styles: { halign: 'right' } }
        ]];
        const body = ASSP_DATA.desglose.map(function (d) {
            return [
                d.label,
                fmtMoneda(d.devengado),
                fmtMoneda(d.deducciones),
                fmtMoneda(d.pagado),
                fmtMoneda(d.aporte_seg),
                fmtMoneda(d.aporte_corto),
                fmtMoneda(d.aporte_frza),
                fmtMoneda(d.aporte_total)
            ];
        });
    const t = ASSP_DATA.totales;
    const foot = [[
        { content: 'TOTAL GRAL.', styles: { halign: 'left' } },
        { content: fmtMoneda(t.devengado), styles: { halign: 'right' } },
        { content: fmtMoneda(t.deducciones), styles: { halign: 'right' } },
        { content: fmtMoneda(t.pagado), styles: { halign: 'right' } },
        { content: fmtMoneda(t.aporte_seg), styles: { halign: 'right' } },
        { content: fmtMoneda(t.aporte_corto), styles: { halign: 'right' } },
        { content: fmtMoneda(t.aporte_frza), styles: { halign: 'right' } },
        { content: fmtMoneda(t.aporte_total), styles: { halign: 'right' } }
    ]];

    doc.autoTable({
        startY: y,
        head: head,
        body: body,
        foot: foot,
        theme: 'grid',
        styles: { fontSize: 8.5, cellPadding: 5, textColor: [0, 0, 0], lineColor: [0, 0, 0], lineWidth: 0.6 },
        headStyles: { fillColor: [219, 234, 254], textColor: [0, 0, 0], fontStyle: 'bold', fontSize: 8, lineColor: [0, 0, 0], lineWidth: 0.6 },
        footStyles: { fillColor: [219, 234, 254], textColor: [0, 0, 0], fontStyle: 'bold', fontSize: 9, lineColor: [0, 0, 0], lineWidth: 0.6 },
        alternateRowStyles: { fillColor: [245, 248, 252] },
        columnStyles: {
            0: { halign: 'left', fontStyle: 'bold', cellWidth: 122 },
            1: { halign: 'right' }, 2: { halign: 'right' }, 3: { halign: 'right' },
            4: { halign: 'right' },
            5: { halign: 'right' },
            6: { halign: 'right', cellWidth: 80 },
            7: { halign: 'right', fontStyle: 'bold' }
        },
        margin: { left: 30, right: 30 }
    });

    y = doc.lastAutoTable.finalY + 30;

    // Nota
    doc.setFontSize(8);
    doc.setTextColor(0, 0, 0);
    doc.text('Nota: El aporte patronal se calcula sobre el PAGADO (importe neto). El 12.5% es Seguridad Social, el 1.5% Prestaciones a Corto Plazo y el 5% Fuerza de Trabajo. El 5% NO aplica a PDL.', 30, y);
    y += 30;

    // Firmas
    const firmas = [
        { label: 'Aprobado por:', nombre: ASSP_DATA.jefe, cargo: 'Jefe de Proyecto' },
        { label: 'Revisado por:', nombre: ASSP_DATA.especialista, cargo: 'Especialista en Gestión Económica' },
        { label: 'Elaborado por:', nombre: ASSP_DATA.usuario, cargo: 'Especialista de Nóminas' }
    ];
    const zonaW = (pageW - 60) / 3;
    firmas.forEach(function (f, i) {
        const centro = 30 + zonaW * i + zonaW / 2;
        doc.setDrawColor(120);
        doc.setLineWidth(0.6);
        doc.line(centro - 80, y, centro + 80, y);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        doc.setTextColor(0, 0, 0);
        doc.text(f.nombre, centro, y + 12, { align: 'center' });
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(0, 0, 0);
        doc.text(f.cargo, centro, y + 22, { align: 'center' });
        doc.text(f.label, centro, y + 34, { align: 'center' });
    });

    // Footer
    const totalPages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(7);
        doc.setTextColor(0, 0, 0);
        doc.text('Aporte Seguridad Social Patronal · Generado: ' + obtenerFechaHora() + ' · Página ' + i + ' de ' + totalPages,
                 pageW / 2, doc.internal.pageSize.getHeight() - 15, { align: 'center' });
    }

    doc.save('Aporte_Seg_Social_' + ASSP_DATA.periodo + '.pdf');
});

// ============================================
// IMPRIMIR
// ============================================
document.getElementById('btnImprimir')?.addEventListener('click', function () {
    const filasHtml = ASSP_DATA.desglose.map(function (d) {
        return '<tr>'
            + '<td>' + d.label + '</td>'
            + '<td class="r">' + fmtMoneda(d.devengado) + '</td>'
            + '<td class="r">' + fmtMoneda(d.deducciones) + '</td>'
            + '<td class="r">' + fmtMoneda(d.pagado) + '</td>'
            + '<td class="r">' + fmtMoneda(d.aporte_seg) + '</td>'
            + '<td class="r">' + fmtMoneda(d.aporte_corto) + '</td>'
            + '<td class="r">' + fmtMoneda(d.aporte_frza) + '</td>'
            + '<td class="r b">' + fmtMoneda(d.aporte_total) + '</td>'
            + '</tr>';
    }).join('');
    const t = ASSP_DATA.totales;
    const filaTotal = '<tr class="total">'
        + '<td>TOTAL GRAL.</td>'
        + '<td class="r">' + fmtMoneda(t.devengado) + '</td>'
        + '<td class="r">' + fmtMoneda(t.deducciones) + '</td>'
        + '<td class="r">' + fmtMoneda(t.pagado) + '</td>'
        + '<td class="r">' + fmtMoneda(t.aporte_seg) + '</td>'
        + '<td class="r">' + fmtMoneda(t.aporte_corto) + '</td>'
        + '<td class="r">' + fmtMoneda(t.aporte_frza) + '</td>'
        + '<td class="r">' + fmtMoneda(t.aporte_total) + '</td>'
        + '</tr>';

    const html = `<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Aporte Seguridad Social - ${ASSP_DATA.periodo_label}</title>
        <style>
            @page { size: letter landscape; margin: 14mm 12mm; }
            * { box-sizing: border-box; }
            body { font-family: Arial, Helvetica, sans-serif; color: #111; margin:0; padding:0.875rem; background:#fff; }
            .no-print { margin-bottom: 1rem; }
            .btn-toolbar { position: sticky; top:0; z-index: 100; display:flex; justify-content:center; gap:0.625rem; padding:0.625rem; background:#1e3a8a; border-radius:0.5rem; margin-bottom:1rem; }
            .btn-toolbar button { padding:0.5rem 1.125rem; background:#22c55e; color:#fff; border:none; border-radius:0.375rem; font-size:0.8125rem; font-weight:bold; cursor:pointer; }
            .btn-toolbar button.close { background:#ef4444; }
            .header { display:flex; justify-content:space-between; align-items:center; border-bottom: 0.125rem solid #1e3a8a; padding-bottom:0.5rem; margin-bottom:0.75rem; }
            .header .logo { width:4.375rem; }
            .header .logo img { max-width:3.75rem; max-height:3.75rem; }
            .header .titulo { text-align:center; flex:1; }
            .header .titulo h2 { margin:0; font-size:13pt; color:#1e3a8a; text-transform:uppercase; }
            .header .titulo h3 { margin:0.25rem 0 0; font-size:10pt; color:#374151; font-weight:500; }
            .header .meta { font-size:8pt; color:#6b7280; text-align:right; line-height:1.4; }
            .periodo { text-align:center; font-size:11pt; font-weight:bold; color:#1e3a8a; margin:0.5rem 0 1rem; }
            table { width:100%; border-collapse:collapse; font-size:8.5pt; }
            th, td { border:0.5pt solid #000; padding:0.375rem 0.5rem; }
            th { background:#1e3a8a; color:#fff; font-weight:bold; text-align:right; font-size:7.5pt; text-transform:uppercase; line-height:1.15; }
            th:first-child { text-align:left; }
            th:first-child, td:first-child { width:13%; }
            td { text-align:right; }
            td:first-child { text-align:left; font-weight:500; }
            .r { text-align:right; }
            .b { font-weight:bold; }
            tr.total td { background:#dbeafe; font-weight:bold; font-size:9pt; border-top: 0.125rem solid #1e3a8a; }
            tr:nth-child(even) td { background:#f5f8fc; }
            tr.total:nth-child(even) td { background:#dbeafe; }
            .nota { margin-top:0.75rem; font-size:7.5pt; color:#6b7280; padding:0.5rem 0.75rem; background:#fef3c7; border-left: 0.1875rem solid #f59e0b; border-radius:0.25rem; }
            .firmas { display:flex; justify-content:space-around; margin-top:3.5rem; gap:1rem; }
            .firma { flex:1; text-align:center; font-size:8pt; }
            .firma .linea { border-top: 0.0625rem solid #000; margin:2.5rem 0.625rem 0.375rem; }
            .firma .nombre { font-weight:bold; font-size:9pt; }
            .firma .cargo { color:#6b7280; font-size:7.5pt; }
            @media print { .no-print { display:none !important; } body { padding:0; } }
        </style>
    </head>
    <body>
        <div class="btn-toolbar no-print">
            <button onclick="window.print()">🖨️ Imprimir</button>
            <button class="close" onclick="window.close()">✖ Cerrar</button>
        </div>
        <div class="header">
            <div class="logo">${ASSP_DATA.logo ? '<img src="' + ASSP_DATA.logo + '">' : ''}</div>
            <div class="titulo">
                <h2>${ASSP_DATA.empresa.toUpperCase()}</h2>
                <h3>Desglose de Pago de Salarios para el Aporte de la Seguridad Social Patronal</h3>
            </div>
            <div class="meta">
                <div><strong>REEUP:</strong> ${ASSP_DATA.reeup || '—'}</div>
                <div><strong>NIT:</strong> ${ASSP_DATA.nit || '—'}</div>
                <div><strong>Emisión:</strong> ${obtenerFechaHora()}</div>
            </div>
        </div>
        <div class="periodo">Período: ${ASSP_DATA.periodo_label}</div>
        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">Tipo Nómina</th>
                    <th>DEVENGADO</th>
                    <th>DEDUCC.<br>TOTALES</th>
                    <th>PAGADO<br>(base aporte)</th>
                    <th>12.5% Seg.<br>Soc. Patronal</th>
                    <th>1.5% Prest.<br>Corto Plazo</th>
                    <th>5% Frza. Trab.<br>(NO aplica PDL)</th>
                    <th>Total Aporte<br>(12.5+5%)</th>
                </tr>
            </thead>
            <tbody>${filasHtml}</tbody>
            <tfoot>${filaTotal}</tfoot>
        </table>
        <div class="nota">
            <strong>Nota:</strong> El aporte patronal se calcula sobre el PAGADO (importe neto). El 12.5% es Seguridad Social, el 1.5% Prestaciones a Corto Plazo y el 5% Fuerza de Trabajo. El 5% NO aplica a PDL.
        </div>
        <div class="firmas">
            <div class="firma"><div class="linea"></div><div class="nombre">${ASSP_DATA.jefe || ''}</div><div class="cargo">Jefe de Proyecto</div></div>
            <div class="firma"><div class="linea"></div><div class="nombre">${ASSP_DATA.especialista || ''}</div><div class="cargo">Especialista en Gestión Económica</div></div>
            <div class="firma"><div class="linea"></div><div class="nombre">${ASSP_DATA.usuario || ''}</div><div class="cargo">Especialista de Nóminas</div></div>
        </div>
    </body>
    </html>`;

    const win = window.open('', '_blank');
    if (!win) {
        Swal.fire({ icon: 'warning', title: 'Aviso', text: 'Permita ventanas emergentes para imprimir.', background: '#1a1a2e', color: '#fff' });
        return;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
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