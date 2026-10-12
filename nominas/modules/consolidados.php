<?php
// modules/consolidados.php - Consolidado de pagos por tipo de nómina
// Escoge año, marca meses del año y tipos de nómina (uno, dos, tres o todos)
// y genera un resumen consolidado de pagos.
require_once '../config/database.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

if (!permiso_puede('reportes', 'ver')) {
    permiso_denegar_acceso('Consolidado de Pagos');
}

// Configuración empresa
$config_empresa = [
    'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
    'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
    'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas'
];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Logo
$ruta_logo = '../../images/LogoTN.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// ============================================
// TIPOS DE NÓMINA CONOCIDOS
// ============================================
$tipos_conocidos = [
    'automatica'     => ['label' => 'Salario Básico',   'icono' => 'fa-money-bill-wave', 'color' => '#60a5fa'],
    'extraordinaria' => ['label' => 'H. Extras Noct',   'icono' => 'fa-clock',          'color' => '#f59e0b'],
    'vacaciones'     => ['label' => 'Vacaciones',       'icono' => 'fa-umbrella-beach', 'color' => '#34d399'],
    'bono'           => ['label' => 'P. Rendim.',       'icono' => 'fa-gift',           'color' => '#f87171'],
    'ajuste'         => ['label' => 'Ajustes y Liquid.','icono' => 'fa-pen',            'color' => '#a78bfa'],
];
$orden_tipos = ['automatica', 'extraordinaria', 'vacaciones', 'bono', 'ajuste'];

// ============================================
// AÑOS Y MESES REALES (estado contabilizado)
// ============================================
$meses_por_anio = [];
try {
    $stmt_per = $pdo->query(
        "SELECT YEAR(periodo_desde) AS y, MONTH(periodo_desde) AS m FROM nominas
         WHERE estado = 'contabilizado'
         GROUP BY y, m"
    );
    while ($row_per = $stmt_per->fetch(PDO::FETCH_ASSOC)) {
        $meses_por_anio[(int)$row_per['y']][(int)$row_per['m']] = true;
    }
} catch (PDOException $e) {}
$anios_reales = array_keys($meses_por_anio);
if (empty($anios_reales)) {
    $anios_reales = [(int)date('Y')];
    $meses_por_anio[(int)date('Y')] = [];
}
rsort($anios_reales);

// ============================================
// PARÁMETROS DEL FILTRO (GET)
// ============================================
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
if (!in_array($anio, $anios_reales)) {
    $anio = (int)$anios_reales[0];
}
$meses_disponibles = array_map('intval', array_keys($meses_por_anio[$anio] ?? []));
sort($meses_disponibles);

$meses_get = isset($_GET['meses']) && is_array($_GET['meses']) ? array_map('intval', $_GET['meses']) : null;
if ($meses_get === null) {
    // Primera carga: todos los meses con datos del año
    $meses_sel = $meses_disponibles;
} else {
    // Cruza lo pedido con lo disponible en ese año
    $meses_sel = array_values(array_intersect(array_unique($meses_get), $meses_disponibles));
}

$tipos_get = isset($_GET['tipos']) && is_array($_GET['tipos']) ? array_map('strval', $_GET['tipos']) : null;
if ($tipos_get === null) {
    $tipos_sel = $orden_tipos;
} else {
    $tipos_sel = array_values(array_intersect(array_unique($tipos_get), $orden_tipos));
}

$hay_filtro = isset($_GET['anio']) || isset($_GET['meses']) || isset($_GET['tipos']);

// ============================================
// CONSOLIDADO
// ============================================
$detalle = [];            // filas mes x tipo
$consolidado_tipo = [];   // agregado por tipo sobre los meses marcados
$consolidado_mes = [];    // agregado por mes sobre los tipos marcados
$total_gral = [
    'nominas' => 0, 'trabajadores' => 0, 'registros' => 0,
    'devengado' => 0, 'deducciones' => 0, 'contribucion' => 0,
    'vacaciones' => 0, 'neto' => 0
];
$trabajadores_periodo = 0;
$error_consolidado = '';

if (!empty($meses_sel) && !empty($tipos_sel)) {
    try {
        $ph_meses = implode(',', array_fill(0, count($meses_sel), '?'));
        $ph_tipos = implode(',', array_fill(0, count($tipos_sel), '?'));

        $sql = "
            SELECT tipo_nomina,
                   MONTH(periodo_desde) AS mes,
                   COUNT(DISTINCT numero_nomina) AS c_nominas,
                   COUNT(DISTINCT trabajador_id) AS c_trabajadores,
                   COUNT(*) AS c_registros,
                   COALESCE(SUM(total_salario_devengado), 0) AS devengado,
                   COALESCE(SUM(total_deducciones), 0) AS deducciones,
                   COALESCE(SUM(contribucion_especial), 0) AS contribucion,
                   COALESCE(SUM(importe_vacaciones), 0) AS vacaciones,
                   COALESCE(SUM(importe_neto), 0) AS neto
            FROM nominas
            WHERE estado = 'contabilizado'
              AND YEAR(periodo_desde) = ?
              AND MONTH(periodo_desde) IN ($ph_meses)
              AND tipo_nomina IN ($ph_tipos)
            GROUP BY tipo_nomina, mes
            ORDER BY mes ASC, tipo_nomina ASC
        ";
        $params = array_merge([$anio], $meses_sel, $tipos_sel);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Trabajadores únicos del período consolidado (puede haber en varios tipos)
        $stmt_trab = $pdo->prepare("
            SELECT COUNT(DISTINCT trabajador_id)
            FROM nominas
            WHERE estado = 'contabilizado'
              AND YEAR(periodo_desde) = ?
              AND MONTH(periodo_desde) IN ($ph_meses)
              AND tipo_nomina IN ($ph_tipos)
        ");
        $stmt_trab->execute($params);
        $trabajadores_periodo = (int)$stmt_trab->fetchColumn();

        // Acumula por tipo, por mes y total general
        foreach ($detalle as $fila) {
            $t = $fila['tipo_nomina'];
            $m = (int)$fila['mes'];

            if (!isset($consolidado_tipo[$t])) {
                $consolidado_tipo[$t] = [
                    'nominas' => 0, 'trabajadores' => 0, 'registros' => 0,
                    'devengado' => 0, 'deducciones' => 0, 'contribucion' => 0,
                    'vacaciones' => 0, 'neto' => 0
                ];
            }
            if (!isset($consolidado_mes[$m])) {
                $consolidado_mes[$m] = [
                    'nominas' => 0, 'trabajadores' => 0, 'registros' => 0,
                    'devengado' => 0, 'deducciones' => 0, 'contribucion' => 0,
                    'vacaciones' => 0, 'neto' => 0
                ];
            }

            $campos = ['devengado', 'deducciones', 'contribucion', 'vacaciones', 'neto'];
            foreach ($campos as $c) {
                $consolidado_tipo[$t][$c] += floatval($fila[$c]);
                $consolidado_mes[$m][$c] += floatval($fila[$c]);
                $total_gral[$c] += floatval($fila[$c]);
            }
            $contadores = ['nominas' => 'c_nominas', 'registros' => 'c_registros'];
            foreach ($contadores as $c => $col) {
                $consolidado_tipo[$t][$c] += intval($fila[$col]);
                $consolidado_mes[$m][$c] += intval($fila[$col]);
                $total_gral[$c] += intval($fila[$col]);
            }
            // trabajadores: solo acumula al total general por tipo/mes sin sumar duplicados entre filas
            // (se recalcula aparte abajo para no inflar el conteo)
        }

        // Trabajadores por tipo y por mes (DISTINCT por grupo) para no duplicar
        $stmt_grupos = $pdo->prepare("
            SELECT tipo_nomina, MONTH(periodo_desde) AS mes, trabajador_id
            FROM nominas
            WHERE estado = 'contabilizado'
              AND YEAR(periodo_desde) = ?
              AND MONTH(periodo_desde) IN ($ph_meses)
              AND tipo_nomina IN ($ph_tipos)
            GROUP BY tipo_nomina, mes, trabajador_id
        ");
        $stmt_grupos->execute($params);
        $vistos_tipo = [];
        $vistos_mes = [];
        foreach ($stmt_grupos->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $vistos_tipo[$g['tipo_nomina']][$g['trabajador_id']] = true;
            $vistos_mes[(int)$g['mes']][$g['trabajador_id']] = true;
        }
        foreach ($consolidado_tipo as $t => &$agg) {
            $agg['trabajadores'] = isset($vistos_tipo[$t]) ? count($vistos_tipo[$t]) : 0;
        }
        unset($agg);
        foreach ($consolidado_mes as $m => &$agg) {
            $agg['trabajadores'] = isset($vistos_mes[$m]) ? count($vistos_mes[$m]) : 0;
        }
        unset($agg);

    } catch (PDOException $e) {
        $error_consolidado = 'No se pudo generar el consolidado: ' . $e->getMessage();
        $detalle = [];
        $consolidado_tipo = [];
        $consolidado_mes = [];
    }
}

// Etiqueta del período
$meses_label = empty($meses_sel)
    ? 'sin meses'
    : implode(', ', array_map(function ($m) { return nombreMesEspanol($m); }, $meses_sel));
if (count($meses_sel) > 4) {
    $meses_label .= ' (' . count($meses_sel) . ' meses)';
}
$etiqueta_periodo = $anio . ' · ' . $meses_label;
$tipos_label = empty($tipos_sel)
    ? 'sin tipos'
    : implode(', ', array_map(function ($t) use ($tipos_conocidos) { return $tipos_conocidos[$t]['label']; }, $tipos_sel));

$fmt = function ($v) { return number_format(floatval($v), 2); };

// Datos para impresión / exportación (se serializan a JS)
$print_tipos = [];
foreach ($orden_tipos as $t_key) {
    if (!isset($consolidado_tipo[$t_key])) continue;
    $print_tipos[] = [
        'tipo'          => $t_key,
        'label'         => $tipos_conocidos[$t_key]['label'],
        'nominas'       => (int)$consolidado_tipo[$t_key]['nominas'],
        'trabajadores'  => (int)$consolidado_tipo[$t_key]['trabajadores'],
        'registros'     => (int)$consolidado_tipo[$t_key]['registros'],
        'devengado'     => (float)$consolidado_tipo[$t_key]['devengado'],
        'deducciones'   => (float)$consolidado_tipo[$t_key]['deducciones'],
        'contribucion'  => (float)$consolidado_tipo[$t_key]['contribucion'],
        'vacaciones'    => (float)$consolidado_tipo[$t_key]['vacaciones'],
        'neto'          => (float)$consolidado_tipo[$t_key]['neto'],
    ];
}
$print_meses = [];
foreach ($meses_sel as $m_key) {
    if (!isset($consolidado_mes[$m_key])) continue;
    $print_meses[] = [
        'mes'           => (int)$m_key,
        'label'         => nombreMesEspanol($m_key) . ' ' . $anio,
        'nominas'       => (int)$consolidado_mes[$m_key]['nominas'],
        'trabajadores'  => (int)$consolidado_mes[$m_key]['trabajadores'],
        'registros'     => (int)$consolidado_mes[$m_key]['registros'],
        'devengado'     => (float)$consolidado_mes[$m_key]['devengado'],
        'contribucion'  => (float)$consolidado_mes[$m_key]['contribucion'],
        'deducciones'   => (float)$consolidado_mes[$m_key]['deducciones'],
        'neto'          => (float)$consolidado_mes[$m_key]['neto'],
    ];
}
$print_detalle = [];
foreach ($detalle as $fila_det) {
    $t_det = $fila_det['tipo_nomina'];
    $print_detalle[] = [
        'mes_label'     => nombreMesEspanol((int)$fila_det['mes']),
        'tipo_label'    => $tipos_conocidos[$t_det]['label'] ?? ucfirst($t_det),
        'nominas'       => (int)$fila_det['c_nominas'],
        'trabajadores'  => (int)$fila_det['c_trabajadores'],
        'registros'     => (int)$fila_det['c_registros'],
        'devengado'     => (float)$fila_det['devengado'],
        'contribucion'  => (float)$fila_det['contribucion'],
        'deducciones'   => (float)$fila_det['deducciones'],
        'neto'          => (float)$fila_det['neto'],
    ];
}
$print_data = [
    'empresa'        => $config_empresa['nombre_empresa'],
    'jefe'           => $config_empresa['jefe_proyecto'],
    'especialista'   => $config_empresa['especialista_gestion'],
    'logo'           => $logo_base64,
    'anio'           => $anio,
    'periodo_label'  => $etiqueta_periodo,
    'tipos_label'    => $tipos_label,
    'trabajadores'   => $trabajadores_periodo,
    'total'          => [
        'nominas'      => (int)$total_gral['nominas'],
        'trabajadores' => (int)$trabajadores_periodo,
        'registros'    => (int)$total_gral['registros'],
        'devengado'    => (float)$total_gral['devengado'],
        'deducciones'  => (float)$total_gral['deducciones'],
        'contribucion' => (float)$total_gral['contribucion'],
        'vacaciones'   => (float)$total_gral['vacaciones'],
        'neto'         => (float)$total_gral['neto'],
    ],
    'tipos'          => $print_tipos,
    'meses'          => $print_meses,
    'detalle'        => $print_detalle,
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Consolidado de Pagos</title>
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
        }
        .glass-card:hover { transform: translateY(-0.125rem); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap:0.75rem;
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
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }

        /* Dropdown de exportación (patrón reportes.php) */
        .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
        .dropdown-menu-win {
            background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(1.25rem) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important; border-radius: 0.75rem !important;
            padding:0.5rem !important; box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
        }
        .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 0.5rem !important; padding:0.625rem 1rem !important; font-size:0.9rem !important; }
        .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
        .dropdown-menu-win .dropdown-divider { border-color: rgba(148, 163, 184, 0.35) !important; border-top-width: 0.0625rem !important; opacity: 1 !important; margin:0.5rem 0.25rem !important; }
        html[data-theme="light"] .dropdown-menu-win,
        html[data-theme="orgullo"] .dropdown-menu-win {
            background: rgba(255, 255, 255, 0.98) !important;
            border-color: rgba(0, 0, 0, 0.12) !important;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.18) !important;
        }
        html[data-theme="light"] .dropdown-menu-win .dropdown-item,
        html[data-theme="orgullo"] .dropdown-menu-win .dropdown-item { color: #1e293b !important; }
        html[data-theme="light"] .dropdown-menu-win .dropdown-item:hover,
        html[data-theme="orgullo"] .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.12) !important; color: #0f172a !important; }

        .form-control, .form-select {
            background: var(--panel) !important; border: 0.0625rem solid rgba(255,255,255,0.15) !important;
            color: #fff !important; border-radius: 0.625rem !important; padding:0.5rem 0.75rem !important; font-size:0.85rem !important;
        }
        select.form-select {
            appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important; background-position: right 0.75rem center !important;
            background-size: 1rem 1rem !important; padding-right: 2.25rem !important;
        }
        html[data-theme="light"] select.form-select,
        html[data-theme="orgullo"] select.form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237c3aed' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }

        .filtro-card { padding:0; margin-bottom:0; }
        .glass-card.card-cons { margin-bottom:1.25rem; }

        /* Grid superior filtros/reloj: sin márgenes negativos de Bootstrap row */
        .top-grid {
            display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.25rem;
        }
        @media (min-width: 992px) {
            .top-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
        }

        /* ============ COLLAPSE DE CARDS (patrón configuracion.php) ============ */
        .card-collapse-title {
            cursor: pointer; user-select: none; display: flex; flex-grow: 1;
            align-items: center; gap: 0.5rem; font-weight: 700; transition: color 0.2s ease;
            font-size: 0.9rem; color: #ffffff; margin: 0;
        }
        .card-collapse-title:hover { color: #60a5fa; }
        .card-collapse-title .collapse-chevron {
            font-size: 0.7rem; color: rgba(255,255,255,0.4); transition: transform 0.25s ease, color 0.2s ease;
        }
        .card-collapse-title:hover .collapse-chevron { color: #60a5fa; }
        .card-collapse-title:not(.collapsed) .collapse-chevron { transform: rotate(180deg); color: #60a5fa; }
        .contenido-cabecera {
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
            gap:0.75rem; margin-bottom:1rem;
            position: relative; z-index: 1060;
        }
        .contenido-cabecera .subtitulo {
            font-size:0.9rem; color:rgba(255,255,255,0.7); margin:0;
            display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;
        }
        .contenido-cabecera .subtitulo-texto {
            background:linear-gradient(135deg, var(--accent), var(--accent-light));
            -webkit-background-clip:text; background-clip:text; color:transparent;
            font-weight:600;
        }
        html[data-theme="light"] .contenido-cabecera .subtitulo-texto {
            background:linear-gradient(135deg, var(--accent-dark), var(--accent));
            -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .contenido-cabecera .subtitulo i { color:var(--accent); }
        .contenido-cabecera .subtitulo-periodo { color:inherit; font-weight:700; }
        /* Temas claros (light / orgullo): el texto base es blanco y queda ilegible */
        html[data-theme="light"] .contenido-cabecera .subtitulo,
        html[data-theme="orgullo"] .contenido-cabecera .subtitulo { color:var(--txt)!important; }
        html[data-theme="light"] .contenido-cabecera .subtitulo i,
        html[data-theme="orgullo"] .contenido-cabecera .subtitulo i { color:var(--accent-dark)!important; }
        html[data-theme="light"] .contenido-cabecera .subtitulo-periodo,
        html[data-theme="orgullo"] .contenido-cabecera .subtitulo-periodo { color:var(--accent-dark)!important; }
        html[data-theme="orgullo"] .contenido-cabecera .subtitulo-texto {
            background:linear-gradient(135deg, var(--accent-dark), var(--accent));
            -webkit-background-clip:text; background-clip:text; color:transparent!important;
        }
        .filtro-titulo { font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05rem; color:rgba(255,255,255,0.6); margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem; }
        .check-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(7.5rem, 1fr)); gap:0.4rem; }
        #gridMeses { grid-template-columns:repeat(3, 1fr); }
        .check-grid-tipos { grid-template-columns:repeat(auto-fill, minmax(9.5rem, 1fr)); }
        .reloj-cons {
            display:flex; align-items:center; justify-content:center; gap:1.25rem;
            height:100%; min-height:8rem;
        }
        .reloj-cons-analog {
            width:6.5rem; height:6.5rem; flex-shrink:0;
            filter: drop-shadow(0 0.3rem 0.55rem rgba(0,0,0,0.38));
        }
        .reloj-cons-texto { display:flex; flex-direction:column; gap:0.25rem; }
        .reloj-cons-hora {
            font-size:1.5rem; font-weight:700; color:#fff;
            letter-spacing:0.04em; font-variant-numeric:tabular-nums;
            display:flex; align-items:baseline; gap:0.4rem;
        }
        .reloj-cons-hora i { color:var(--accent-light, #67e8f9); font-size:1.1rem; align-self:center; }
        .reloj-cons-hora small { font-size:0.72rem; font-weight:600; color:var(--accent-light, #67e8f9); letter-spacing:0.08em; }
        .reloj-cons-hoy { font-size:0.7rem; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:0.05rem; margin-top:0.25rem; }
        .reloj-cons-hoy i { color:var(--accent-light, #67e8f9); margin-right:0.3rem; }
        .reloj-cons-fecha { font-size:0.82rem; color:rgba(255,255,255,0.75); font-weight:500; }
        .check-opcion {
            display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.625rem;
            border:0.0625rem solid rgba(255,255,255,0.1); border-radius:0.5rem; cursor:pointer;
            font-size:0.8rem; transition: all 0.2s; background:rgba(255,255,255,0.03);
        }
        .check-opcion:hover { border-color:rgba(0,120,212,0.5); background:rgba(0,120,212,0.08); }
        .check-opcion input { accent-color:#0078d4; cursor:pointer; }
        .check-opcion input:disabled + span { opacity:0.4; }
        .check-opcion.sin-datos { opacity:0.45; cursor:not-allowed; }
        .check-opcion.sin-datos:hover { border-color:rgba(255,255,255,0.1); background:rgba(255,255,255,0.03); }

        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(13.5rem, 1fr)); gap:1rem; margin:0 0 1.5rem; }
        .stat-card {
            background: linear-gradient(135deg, var(--panel-2), var(--panel));
            border-radius: 1rem; padding:0.875rem; border: 0.0625rem solid rgba(255, 255, 255, 0.08);
            display:flex; justify-content:space-between; align-items:center; gap:0.5rem;
            transition: all 0.3s ease; overflow:hidden; min-width:0;
        }
        .stat-card > div:first-child { min-width:0; }
        .stat-card:hover { transform: translateY(-0.1875rem); border-color: rgba(96, 165, 250, 0.3); }
        .stat-icon { width:2.5rem; height:2.5rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size:1.15rem; background: rgba(255,255,255,0.05); flex-shrink: 0; }
        .stat-card h6 { font-size:0.7rem; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; margin-bottom:0.3125rem; letter-spacing: 0.03rem; }
        .stat-card h3 { font-size:1.15rem; font-weight: 700; margin:0; color: white; }
        .stat-card small { font-size:0.65rem; color: rgba(255,255,255,0.5); }

        .tabla-cons { width:100%; border-collapse: collapse; font-size: 0.85rem; }
        .tabla-cons thead th {
            background: linear-gradient(135deg, rgba(59,130,246,0.25), rgba(139,92,246,0.15));
            color: #ffffff; font-weight: 600; font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05rem; padding: 0.75rem 0.5rem;
            border-bottom: 0.125rem solid rgba(96,165,250,0.4); text-align: right; white-space: nowrap;
        }
        .tabla-cons thead th:first-child { text-align: left; }
        .tabla-cons thead th.th-cuenta,
        .tabla-cons tbody td.td-cuenta,
        .tabla-cons tfoot td.td-cuenta { text-align: center; }
        .tabla-cons thead th.th-moneda,
        .tabla-cons tbody td.td-moneda,
        .tabla-cons tfoot td.td-moneda { text-align: right; }
        .tabla-cons thead th {
            cursor:pointer; user-select:none; position:relative;
            padding-right:1.25rem; transition: background 0.2s;
        }
        .tabla-cons thead th:hover { background: linear-gradient(135deg, rgba(59,130,246,0.4), rgba(139,92,246,0.28)); }
        .tabla-cons thead th .sort-icon { font-size:0.6rem; margin-left:0.3rem; opacity:0.5; }
        .tabla-cons thead th.orden-asc .sort-icon,
        .tabla-cons thead th.orden-desc .sort-icon { opacity:1; color:#60a5fa; }
        .tabla-cons tbody td {
            padding: 0.75rem 0.5rem; border-bottom: 0.0625rem solid rgba(255,255,255,0.05);
            text-align: right; vertical-align: middle; color: #e2e8f0; white-space: nowrap;
        }
        .tabla-cons tbody td:first-child { text-align: left; font-weight: 500; }
        .tabla-cons tbody tr:hover { background: rgba(96,165,250,0.08); }
        .tabla-cons tfoot td {
            padding: 0.875rem 0.5rem; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.1));
            font-weight: 700; border-top: 0.125rem solid rgba(96,165,250,0.4); color: #ffffff; text-align: right;
        }
        .tabla-cons tfoot td:first-child { text-align: left; font-size: 0.9rem; }
        .tabla-cons .col-dev { color: #60a5fa; font-weight: 600; }
        .tabla-cons .col-ded { color: #f87171; }
        .tabla-cons .col-neto { color: #34d399; font-weight: 600; }
        .tabla-cons .fila-cero td { opacity: 0.5; }

        .tipo-cell { display: inline-flex; align-items: center; gap: 0.5rem; }
        .tipo-cell .icono-tipo {
            width: 1.75rem; height: 1.75rem; border-radius: 0.5rem; display: inline-flex;
            align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0;
        }

        .seccion-titulo {
            font-size:1rem; font-weight:600; margin:1.75rem 0 0.75rem;
            display:flex; align-items:center; gap:0.5rem; color:#fff;
        }
        .seccion-titulo i { color:#60a5fa; }

        .vacio {
            padding:2.5rem 1rem; text-align:center; color:rgba(255,255,255,0.5);
        }
        .vacio i { font-size:2rem; display:block; margin-bottom:0.75rem; }

        .nota-cons {
            display: flex; align-items: flex-start; gap: 0.75rem;
            padding: 0.875rem 1rem; background: rgba(245,158,11,0.08);
            border-left: 0.25rem solid #f59e0b; border-radius: 0.5rem;
            margin-top: 1.25rem; font-size: 0.8rem; color: #fcd34d;
        }
        .nota-cons i { font-size: 1rem; flex-shrink: 0; margin-top: 0.125rem; color:#f59e0b; }
        html[data-theme="light"] .nota-cons {
            background: rgba(245,158,11,0.15); border-left-color:#d97706; color:#92400e;
        }
        html[data-theme="light"] .nota-cons i { color:#d97706; }
        html[data-theme="orgullo"] .nota-cons {
            background: rgba(245,158,11,0.15); border-left-color:#d97706; color:#92400e;
        }
        html[data-theme="orgullo"] .nota-cons i { color:#d97706; }

        .firmas-cons { display:none; justify-content:space-around; margin-top:3rem; padding:0 1rem; gap:1rem; flex-wrap:wrap; }
        .firma-item { flex:1 1 12rem; text-align:center; min-width:0; }
        .firma-item .firma-linea { border-top:0.0625rem solid rgba(255,255,255,0.3); width:90%; margin:3rem auto 0.5rem; padding-top:0.5rem; }
        .firma-item .firma-label { font-size:0.7rem; color:rgba(255,255,255,0.5); margin-bottom:0.25rem; text-transform:uppercase; letter-spacing:0.04rem; }
        .firma-item .firma-nombre { font-weight:600; font-size:0.85rem; color:#ffffff; word-break:break-word; }

        /* ============ BOTONES FLOTANTES (patrón dashboard/configuracion) ============ */
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

        @media (max-width: 992px) {
            .main-container { margin-left: 5rem; padding: 0.75rem; }
            .win-topbar { padding:0.75rem 1rem; }
            .page-title h1 { font-size:1.15rem; }
            .tabla-cons { font-size:0.72rem; }
            .tabla-cons thead th, .tabla-cons tbody td, .tabla-cons tfoot td { padding:0.5rem 0.35rem; }
        }
        @media (max-width: 576px) {
            .check-grid { grid-template-columns:repeat(auto-fill, minmax(8rem, 1fr)); }
            .kpi-grid { grid-template-columns:1fr 1fr; }
            .tabla-cons { font-size:0.62rem; }
        }

        /* ============ IMPRESIÓN ============ */
        @media print {
            @page { size: letter landscape; margin: 0.5in; }
            body { background:#fff !important; color:#000 !important; }
            .win11-bg, .win-topbar, .sidebar-filtros, .no-print, .win-sidebar, .user-menu-container { display:none !important; }
            .main-container { margin:0 !important; padding:0 !important; }
            .glass-card { background:#fff !important; border:0.0625rem solid #999 !important; box-shadow:none !important; transform:none !important; }
            .print-cabecera { display:block !important; text-align:center; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:0.125rem solid #004B87; }
            .print-cabecera h2 { font-size:13pt; color:#004B87; text-transform:uppercase; margin:0; }
            .print-cabecera .sub { font-size:9pt; color:#333; margin-top:0.1875rem; }
            .tabla-cons { font-size:8pt; }
            .tabla-cons thead th { background:#004B87 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .tabla-cons tbody td, .tabla-cons tfoot td { color:#000 !important; border-bottom:0.0625rem solid #bbb; }
            .tabla-cons tfoot td { background:#e8eef5 !important; border-top:0.125rem solid #004B87; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .tabla-cons .col-dev, .tabla-cons .col-ded, .tabla-cons .col-neto { color:#000 !important; }
            .tabla-cons tbody tr:hover { background:transparent !important; }
            .seccion-titulo { color:#004B87 !important; font-size:11pt; }
            .seccion-titulo i { color:#004B87 !important; }
            .kpi-grid { display:none !important; }
            .card-collapse-title { display:none !important; }
            .collapse { display:block !important; height:auto !important; overflow:visible !important; }
            .nota-cons { color:#000 !important; background:#fff8e1 !important; border-left:0.25rem solid #f59e0b; }
            .firmas-cons { display:flex !important; }
            .firma-item .firma-linea { border-top:0.0625rem solid #000; }
            .firma-item .firma-label, .firma-item .firma-nombre { color:#000 !important; }
            .glass-card:hover { transform:none !important; box-shadow:none !important; }
        }
        .print-cabecera { display:none; }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">

    <!-- ============ TOPBAR ============ -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-file-invoice-dollar me-2" style="color: #60a5fa;"></i>Consolidado de Pagos</h1>
				<p><i class="fas fa-layer-group me-1"></i> Resumen Consolidado de pagos por tipo de nómina</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- ============ SUBTÍTULO (contenido, antes del primer card) ============ -->
    <div class="contenido-cabecera fade-in-up no-print">
        <p class="subtitulo">
            <i class="fas fa-layer-group"></i>
            <span class="subtitulo-texto">Resumen Consolidado de pagos por tipo de nómina</span>
            · <span class="subtitulo-periodo" id="subtituloPeriodo"><span class="fw-bold"><?php echo $anio; ?></span><?php echo ' · ' . $meses_label; ?></span>
        </p>
        <div class="btn-group" role="group" aria-label="Acciones del consolidado">
            <button class="btn-win btn-win-primary" id="btnPrintReport" title="Imprimir consolidado" data-tooltip="Imprimir consolidado" data-tooltip-theme="primary">
                <i class="fas fa-print me-1"></i> Imprimir
            </button>
            <button type="button" class="btn-win btn-win-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar consolidado" data-tooltip="Exportar consolidado" data-tooltip-theme="primary">
                <span class="visually-hidden">Exportar</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win" id="exportConsolidadoMenu">
                <li><a class="dropdown-item" href="#" id="btnExportWord" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportExcel" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportPdf" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportCsv" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="info"><i class="fas fa-file-csv me-2" style="color: #21a366;"></i>Exportar a CSV</a></li>
                <li><a class="dropdown-item" href="#" id="btnExportTxt" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt me-2" style="color: #eab308;"></i>Exportar a TXT</a></li>
            </ul>
        </div>
    </div>

    <!-- ============ FILTROS + RELOJ (dos cards) ============ -->
    <div class="top-grid">
        <div class="top-grid-filtros">
            <form method="get" action="consolidados.php" id="formConsolidado" class="no-print">
                <div class="glass-card filtro-card sidebar-filtros fade-in-up" style="animation-delay: 0.05s;">
                    <div class="p-3 border-bottom border-white-10">
                        <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="false" aria-controls="collapseFiltros">
                            <i class="fas fa-chevron-down collapse-chevron"></i>
                            <i class="fas fa-filter me-1" style="color: #60a5fa;"></i> Filtros del consolidado
                        </h6>
                    </div>
                    <div id="collapseFiltros" class="collapse">
                        <div class="p-4">
                            <div class="row g-4">
                                <div class="col-12 col-md-7">
                                    <div class="filtro-titulo"><i class="fas fa-calendar-year"></i> Año</div>
                                    <select name="anio" id="selAnio" class="form-select form-select-sm" title="Año del consolidado" data-tooltip="Año del consolidado" data-tooltip-theme="primary">
                                        <?php foreach ($anios_reales as $a_opt): ?>
                                            <option value="<?php echo $a_opt; ?>" <?php echo ($a_opt == $anio ? 'selected' : ''); ?>><?php echo $a_opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="filtro-titulo mt-3">
                                        <i class="fas fa-calendar-days"></i> Meses del año (marque uno o varios)
                                        <button type="button" class="btn-win btn-win-sm ms-auto" id="btnTodosMeses" title="Marcar o desmarcar todos los meses" data-tooltip="Marcar o desmarcar todos los meses" data-tooltip-theme="primary">
                                            <i class="fas fa-check-double me-1"></i> Todos / Ninguno
                                        </button>
                                    </div>
                                    <div class="check-grid" id="gridMeses">
                                        <?php for ($m = 1; $m <= 12; $m++):
                                            $disponible = in_array($m, $meses_disponibles);
                                            $marcado = in_array($m, $meses_sel);
                                        ?>
                                            <label class="check-opcion <?php echo $disponible ? '' : 'sin-datos'; ?>"
                                                   title="<?php echo $disponible ? 'Marcar ' . nombreMesEspanol($m) : 'Sin nóminas cerradas en ' . nombreMesEspanol($m); ?>"
                                                   data-tooltip="<?php echo $disponible ? 'Marcar ' . nombreMesEspanol($m) : 'Sin nóminas cerradas en ' . nombreMesEspanol($m); ?>"
                                                   data-tooltip-theme="<?php echo $disponible ? 'primary' : 'warning'; ?>">
                                                <input type="checkbox" name="meses[]" value="<?php echo $m; ?>"
                                                       <?php echo $marcado ? 'checked' : ''; ?><?php echo $disponible ? '' : ' disabled'; ?>>
                                                <span><?php echo nombreMesEspanol($m); ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-md-5">
                                    <div class="filtro-titulo">
                                        <i class="fas fa-tags"></i> Tipos de nómina (uno, dos, tres o todos)
                                        <button type="button" class="btn-win btn-win-sm ms-auto" id="btnTodosTipos" title="Marcar o desmarcar todos los tipos" data-tooltip="Marcar o desmarcar todos los tipos" data-tooltip-theme="primary">
                                            <i class="fas fa-check-double me-1"></i> Todos / Ninguno
                                        </button>
                                    </div>
                                    <div class="check-grid check-grid-tipos" id="gridTipos">
                                        <?php foreach ($orden_tipos as $t_key):
                                            $meta = $tipos_conocidos[$t_key];
                                            $marcado = in_array($t_key, $tipos_sel);
                                        ?>
                                            <label class="check-opcion" title="Marcar tipo <?php echo htmlspecialchars($meta['label']); ?>" data-tooltip="Marcar tipo <?php echo htmlspecialchars($meta['label']); ?>" data-tooltip-theme="primary">
                                                <input type="checkbox" name="tipos[]" value="<?php echo $t_key; ?>" <?php echo $marcado ? 'checked' : ''; ?>>
                                                <i class="fas <?php echo $meta['icono']; ?>" style="color:<?php echo $meta['color']; ?>;"></i>
                                                <span><?php echo htmlspecialchars($meta['label']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <hr class="border-white-10 mt-4 mb-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn-win btn-win-primary" title="Generar consolidado con la selección actual" data-tooltip="Generar consolidado con la selección actual" data-tooltip-theme="primary">
                                    <i class="fas fa-gears me-1"></i> Generar Consolidado
                                </button>
                                <a href="consolidados.php" class="btn-win" title="Restablecer filtros por defecto" data-tooltip="Restablecer filtros por defecto" data-tooltip-theme="warning">
                                    <i class="fas fa-rotate-left me-1"></i> Restablecer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="top-grid-reloj">
            <div class="glass-card filtro-card fade-in-up no-print" style="animation-delay: 0.1s; height:100%;">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold" style="display:flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.9rem; color:#ffffff; margin:0;">
                        <i class="fas fa-clock me-1" style="color: #a78bfa;"></i> Reloj y fecha
                    </h6>
                </div>
                <div class="p-4">
                    <div class="reloj-cons">
                        <svg class="reloj-cons-analog" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="47" fill="rgba(10,20,40,0.72)" stroke="var(--accent, #06b6d4)" stroke-width="2.5"/>
                            <g id="consRelojTicks"></g>
                            <line id="consManecillaHora" x1="50" y1="52" x2="50" y2="29" stroke="#ffffff" stroke-width="4.5" stroke-linecap="round"/>
                            <line id="consManecillaMinuto" x1="50" y1="52" x2="50" y2="19" stroke="var(--accent-light, #67e8f9)" stroke-width="3" stroke-linecap="round"/>
                            <line id="consManecillaSegundo" x1="50" y1="56" x2="50" y2="16" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round"/>
                            <circle cx="50" cy="50" r="3.2" fill="var(--accent, #06b6d4)" stroke="#ffffff" stroke-width="1"/>
                        </svg>
                        <div class="reloj-cons-texto">
                            <div class="reloj-cons-hora" id="consRelojHora"><i class="fas fa-clock"></i> --:--:--</div>
                            <div class="reloj-cons-hoy"><i class="fas fa-calendar-day"></i> Hoy es:</div>
                            <div class="reloj-cons-fecha" id="consRelojFecha"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error_consolidado): ?>
        <div class="glass-card filtro-card fade-in-up mt-3" style="border-left:0.25rem solid #ef4444; padding:1rem;">
            <div class="text-danger"><i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error_consolidado); ?></div>
        </div>
    <?php endif; ?>

    <!-- ============ CABECERA DE IMPRESIÓN ============ -->
    <div class="print-cabecera">
        <h2>Consolidado de Pagos por Tipo de Nómina</h2>
        <div class="sub">
            <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> ·
            Período: <?php echo htmlspecialchars($etiqueta_periodo); ?> ·
            Tipos: <?php echo htmlspecialchars($tipos_label); ?>
        </div>
    </div>

    <?php if (empty($meses_sel) || empty($tipos_sel)): ?>
        <div class="glass-card fade-in-up">
            <div class="vacio">
                <i class="fas fa-square-check"></i>
                Marque al menos <strong>un mes</strong> y <strong>un tipo de nómina</strong> y pulse
                <strong>Generar Consolidado</strong>.
            </div>
        </div>
    <?php else: ?>

        <!-- Nota -->
        <div class="nota-cons fade-in-up" style="margin:0 0 1.25rem;">
            <i class="fas fa-circle-info"></i>
            <div>
                <strong>Nota:</strong> El consolidado suma solo nóminas en estado
                <strong>Contabilizadas</strong> de los meses marcados
                dentro del año <strong><?php echo $anio; ?></strong> para los tipos de nómina seleccionados
                (<?php echo htmlspecialchars($tipos_label); ?>).
                Los meses sin datos aparecen deshabilitados en el filtro.
            </div>
        </div>

        <!-- ============ KPIs ============ -->
        <div class="kpi-grid fade-in-up" style="animation-delay: 0.1s;">
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-sack-dollar me-1"></i> Devengado</h6>
                    <h3 style="color: #60a5fa;">$<?php echo $fmt($total_gral['devengado']); ?></h3>
                    <small>Salario devengado consolidado</small>
                </div>
                <div class="stat-icon" style="background: rgba(96,165,250,0.15); color: #60a5fa;">
                    <i class="fas fa-hand-holding-dollar"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-percent me-1"></i> Contrib. Esp.</h6>
                    <h3 style="color: #fbbf24;">$<?php echo $fmt($total_gral['contribucion']); ?></h3>
                    <small>Contribución especial</small>
                </div>
                <div class="stat-icon" style="background: rgba(251,191,36,0.15); color: #fbbf24;">
                    <i class="fas fa-scale-balanced"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-square-minus me-1"></i> Deducciones</h6>
                    <h3 style="color: #f87171;">$<?php echo $fmt($total_gral['deducciones']); ?></h3>
                    <small>Otras deducciones</small>
                </div>
                <div class="stat-icon" style="background: rgba(248,113,113,0.15); color: #f87171;">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-money-bill-wave me-1"></i> Neto Pagado</h6>
                    <h3 style="color: #34d399;">$<?php echo $fmt($total_gral['neto']); ?></h3>
                    <small>Total a pagar consolidado</small>
                </div>
                <div class="stat-icon" style="background: rgba(52,211,153,0.15); color: #34d399;">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-users me-1"></i> Trabajadores</h6>
                    <h3 style="color: #a78bfa;"><?php echo number_format($trabajadores_periodo); ?></h3>
                    <small>Únicos en el período</small>
                </div>
                <div class="stat-icon" style="background: rgba(167,139,250,0.15); color: #a78bfa;">
                    <i class="fas fa-user-group"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-file-lines me-1"></i> Registros</h6>
                    <h3 style="color: #38bdf8;"><?php echo number_format($total_gral['registros']); ?></h3>
                    <small>Líneas consolidadas</small>
                </div>
                <div class="stat-icon" style="background: rgba(56,189,248,0.15); color: #38bdf8;">
                    <i class="fas fa-list-check"></i>
                </div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-file-invoice me-1"></i> Nóminas</h6>
                    <h3 style="color: #fb923c;"><?php echo number_format($total_gral['nominas']); ?></h3>
                    <small>Nóminas contabilizadas</small>
                </div>
                <div class="stat-icon" style="background: rgba(251,146,60,0.15); color: #fb923c;">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>

        <!-- ============ CONSOLIDADO POR TIPO ============ -->
        <div class="glass-card card-cons fade-in-up" style="animation-delay: 0.15s;">
            <div class="p-3 border-bottom border-white-10">
                <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTipo" aria-expanded="false" aria-controls="collapseTipo">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-layer-group me-1" style="color: #60a5fa;"></i> Consolidado por tipo de nómina
                </h6>
            </div>
            <div id="collapseTipo" class="collapse">
                <div class="p-3">
            <?php if (empty($consolidado_tipo)): ?>
                <div class="vacio"><i class="fas fa-inbox"></i> No hay nóminas cerradas para la selección realizada.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="tabla-cons" id="tablaTipo">
                        <thead>
                            <tr>
                                <th>Tipo nómina</th>
                                <th class="th-cuenta">Nóminas</th>
                                <th class="th-cuenta">Trabaj.</th>
                                <th class="th-cuenta">Registros</th>
                                <th class="th-moneda">Devengado</th>
                                <th class="th-moneda">Contrib. Esp.</th>
                                <th class="th-moneda">Deducciones</th>
                                <th class="th-moneda">Neto Pagado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orden_tipos as $t_key):
                                if (!isset($consolidado_tipo[$t_key])) continue;
                                $agg = $consolidado_tipo[$t_key];
                                $meta = $tipos_conocidos[$t_key];
                                $es_cero = (abs($agg['devengado']) < 0.005 && abs($agg['neto']) < 0.005);
                            ?>
                                <tr class="<?php echo $es_cero ? 'fila-cero' : ''; ?>">
                                    <td>
                                        <span class="tipo-cell">
                                            <span class="icono-tipo" style="background:<?php echo $meta['color']; ?>22; color:<?php echo $meta['color']; ?>;">
                                                <i class="fas <?php echo $meta['icono']; ?>"></i>
                                            </span>
                                            <?php echo htmlspecialchars($meta['label']); ?>
                                        </span>
                                    </td>
                                    <td class="td-cuenta"><?php echo number_format($agg['nominas']); ?></td>
                                    <td class="td-cuenta"><?php echo number_format($agg['trabajadores']); ?></td>
                                    <td class="td-cuenta"><?php echo number_format($agg['registros']); ?></td>
                                    <td class="td-moneda col-dev">$<?php echo $fmt($agg['devengado']); ?></td>
                                    <td class="td-moneda">$<?php echo $fmt($agg['contribucion']); ?></td>
                                    <td class="td-moneda col-ded">$<?php echo $fmt($agg['deducciones']); ?></td>
                                    <td class="td-moneda col-neto">$<?php echo $fmt($agg['neto']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL CONSOLIDADO</td>
                                <td class="td-cuenta"><?php echo number_format($total_gral['nominas']); ?></td>
                                <td class="td-cuenta"><?php echo number_format($trabajadores_periodo); ?></td>
                                <td class="td-cuenta"><?php echo number_format($total_gral['registros']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['devengado']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['contribucion']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['deducciones']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['neto']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ CONSOLIDADO POR MES ============ -->
        <div class="glass-card card-cons fade-in-up" style="animation-delay: 0.2s;">
            <div class="p-3 border-bottom border-white-10">
                <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseMes" aria-expanded="false" aria-controls="collapseMes">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-calendar-days me-1" style="color: #34d399;"></i> Consolidado por mes (tipos marcados)
                </h6>
            </div>
            <div id="collapseMes" class="collapse">
                <div class="p-3">
            <?php if (empty($consolidado_mes)): ?>
                <div class="vacio"><i class="fas fa-inbox"></i> No hay datos por mes para la selección realizada.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="tabla-cons" id="tablaMes">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="th-cuenta">Nóminas</th>
                                <th class="th-cuenta">Trabaj.</th>
                                <th class="th-cuenta">Registros</th>
                                <th class="th-moneda">Devengado</th>
                                <th class="th-moneda">Contrib. Esp.</th>
                                <th class="th-moneda">Deducciones</th>
                                <th class="th-moneda">Neto Pagado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meses_sel as $m_key):
                                if (!isset($consolidado_mes[$m_key])) continue;
                                $agg = $consolidado_mes[$m_key];
                                $es_cero = (abs($agg['devengado']) < 0.005 && abs($agg['neto']) < 0.005);
                            ?>
                                <tr class="<?php echo $es_cero ? 'fila-cero' : ''; ?>">
                                    <td data-orden="<?php echo $m_key; ?>"><?php echo nombreMesEspanol($m_key) . ' ' . $anio; ?></td>
                                    <td class="td-cuenta"><?php echo number_format($agg['nominas']); ?></td>
                                    <td class="td-cuenta"><?php echo number_format($agg['trabajadores']); ?></td>
                                    <td class="td-cuenta"><?php echo number_format($agg['registros']); ?></td>
                                    <td class="td-moneda col-dev">$<?php echo $fmt($agg['devengado']); ?></td>
                                    <td class="td-moneda">$<?php echo $fmt($agg['contribucion']); ?></td>
                                    <td class="td-moneda col-ded">$<?php echo $fmt($agg['deducciones']); ?></td>
                                    <td class="td-moneda col-neto">$<?php echo $fmt($agg['neto']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL PERÍODO</td>
                                <td class="td-cuenta"><?php echo number_format($total_gral['nominas']); ?></td>
                                <td class="td-cuenta"><?php echo number_format($trabajadores_periodo); ?></td>
                                <td class="td-cuenta"><?php echo number_format($total_gral['registros']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['devengado']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['contribucion']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['deducciones']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($total_gral['neto']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ DETALLE MES x TIPO ============ -->
        <?php if (!empty($detalle)): ?>
        <div class="glass-card card-cons fade-in-up" style="animation-delay: 0.25s;">
            <div class="p-3 border-bottom border-white-10">
                <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDetalle" aria-expanded="false" aria-controls="collapseDetalle">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-table-list me-1" style="color: #a78bfa;"></i> Detalle por mes y tipo
                </h6>
            </div>
            <div id="collapseDetalle" class="collapse">
                <div class="p-3">
            <div style="overflow-x:auto;">
                <table class="tabla-cons" id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Tipo</th>
                            <th class="th-cuenta">Nóminas</th>
                            <th class="th-cuenta">Trabaj.</th>
                            <th class="th-cuenta">Registros</th>
                            <th class="th-moneda">Devengado</th>
                            <th class="th-moneda">Contrib. Esp.</th>
                            <th class="th-moneda">Deducciones</th>
                            <th class="th-moneda">Neto Pagado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $fila):
                            $t = $fila['tipo_nomina'];
                            $meta = $tipos_conocidos[$t] ?? ['label' => ucfirst($t), 'icono' => 'fa-tag', 'color' => '#94a3b8'];
                        ?>
                            <tr>
                                <td data-orden="<?php echo (int)$fila['mes']; ?>"><?php echo nombreMesEspanol((int)$fila['mes']); ?></td>
                                <td>
                                    <span class="tipo-cell">
                                        <span class="icono-tipo" style="background:<?php echo $meta['color']; ?>22; color:<?php echo $meta['color']; ?>;">
                                            <i class="fas <?php echo $meta['icono']; ?>"></i>
                                        </span>
                                        <?php echo htmlspecialchars($meta['label']); ?>
                                    </span>
                                </td>
                                <td class="td-cuenta"><?php echo number_format($fila['c_nominas']); ?></td>
                                <td class="td-cuenta"><?php echo number_format($fila['c_trabajadores']); ?></td>
                                <td class="td-cuenta"><?php echo number_format($fila['c_registros']); ?></td>
                                <td class="td-moneda col-dev">$<?php echo $fmt($fila['devengado']); ?></td>
                                <td class="td-moneda">$<?php echo $fmt($fila['contribucion']); ?></td>
                                <td class="td-moneda col-ded">$<?php echo $fmt($fila['deducciones']); ?></td>
                                <td class="td-moneda col-neto">$<?php echo $fmt($fila['neto']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">TOTAL DETALLE</td>
                            <td class="td-cuenta"><?php echo number_format($total_gral['nominas']); ?></td>
                            <td class="td-cuenta"><?php echo number_format($trabajadores_periodo); ?></td>
                            <td class="td-cuenta"><?php echo number_format($total_gral['registros']); ?></td>
                            <td class="td-moneda">$<?php echo $fmt($total_gral['devengado']); ?></td>
                            <td class="td-moneda">$<?php echo $fmt($total_gral['contribucion']); ?></td>
                            <td class="td-moneda">$<?php echo $fmt($total_gral['deducciones']); ?></td>
                            <td class="td-moneda">$<?php echo $fmt($total_gral['neto']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Firmas -->
        <div class="firmas-cons">
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
        </div>

    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Botones flotantes de navegación rápida -->
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnCollapseAll" title="Expandir todas las tarjetas" data-tooltip="Expandir todas las tarjetas" data-tooltip-theme="primary">
        <i class="fas fa-chevron-down"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>

<script>
(function() {
    // El toggle del sidebar lo gestiona includes/sidebar.php (#sidebarToggleBtn)

    // Subtítulo interactivo: refleja año/meses/tipos marcados en vivo
    var MESES_SUBTITULO = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var subtituloPeriodo = document.getElementById('subtituloPeriodo');

    function actualizarSubtitulo() {
        if (!subtituloPeriodo || !selAnio) return;
        var anio = selAnio.options[selAnio.selectedIndex].text;
        var meses = Array.prototype.map.call(
            document.querySelectorAll('#gridMeses input[type="checkbox"]:checked'),
            function(b) { return MESES_SUBTITULO[parseInt(b.value, 10)] || b.value; }
        );
        var mesesLabel = meses.length ? meses.join(', ') : 'Sin meses';
        if (meses.length > 4) {
            mesesLabel += ' (' + meses.length + ' meses)';
        }
        subtituloPeriodo.innerHTML = '<span class="fw-bold">' + anio + '</span> · ' + mesesLabel;
    }

    document.querySelectorAll('#gridMeses input[type="checkbox"], #gridTipos input[type="checkbox"]')
        .forEach(function(b) { b.addEventListener('change', actualizarSubtitulo); });

    // Todos / Ninguno meses
    var btnTodosMeses = document.getElementById('btnTodosMeses');
    if (btnTodosMeses) {
        btnTodosMeses.addEventListener('click', function() {
            var boxes = document.querySelectorAll('#gridMeses input[type="checkbox"]:not(:disabled)');
            var alguno = Array.prototype.some.call(boxes, function(b) { return b.checked; });
            boxes.forEach(function(b) { b.checked = !alguno; });
            actualizarSubtitulo();
        });
    }

    // Todos / Ninguno tipos
    var btnTodosTipos = document.getElementById('btnTodosTipos');
    if (btnTodosTipos) {
        btnTodosTipos.addEventListener('click', function() {
            var boxes = document.querySelectorAll('#gridTipos input[type="checkbox"]');
            var alguno = Array.prototype.some.call(boxes, function(b) { return b.checked; });
            boxes.forEach(function(b) { b.checked = !alguno; });
        });
    }

    // Cambio de año -> recarga para refrescar meses disponibles
    var selAnio = document.getElementById('selAnio');
    if (selAnio) {
        selAnio.addEventListener('change', function() {
            actualizarSubtitulo();
            var form = document.getElementById('formConsolidado');
            if (form) form.submit();
        });
    }

    // ============================================
    // ORDENACIÓN DE TABLAS POR COLUMNA (click en el th)
    // - Detecta números ($1,234.56 / 1.234) y texto.
    // - La fila de tfoot nunca se reordena.
    // ============================================
    function valorCelda(td) {
        // Orden numérico explícito (data-orden): p.ej. meses cronológicos
        if (td.dataset.orden !== undefined && td.dataset.orden !== '') {
            return { tipo: 'num', v: parseFloat(td.dataset.orden) };
        }
        var txt = (td.textContent || '').trim();
        // $1,234.56 / 1,234 / 493 -> quita $, espacios y separador de miles
        var limpio = txt.replace(/[$\s]/g, '').replace(/,/g, '');
        var num = parseFloat(limpio);
        return (limpio !== '' && !isNaN(num)) ? { tipo: 'num', v: num } : { tipo: 'txt', v: txt.toLowerCase() };
    }

    document.querySelectorAll('.tabla-cons').forEach(function(tabla) {
        var thead = tabla.querySelector('thead');
        var tbody = tabla.querySelector('tbody');
        if (!thead || !tbody) return;

        thead.querySelectorAll('th').forEach(function(th, idx) {
            th.title = 'Ordenar por ' + th.textContent.trim();
            var icon = document.createElement('i');
            icon.className = 'fas fa-sort sort-icon';
            th.appendChild(icon);

            th.addEventListener('click', function() {
                var asc = !th.classList.contains('orden-asc');
                thead.querySelectorAll('th').forEach(function(o) {
                    o.classList.remove('orden-asc', 'orden-desc');
                    var i = o.querySelector('.sort-icon');
                    if (i) i.className = 'fas fa-sort sort-icon';
                });
                th.classList.add(asc ? 'orden-asc' : 'orden-desc');
                icon.className = 'fas ' + (asc ? 'fa-sort-up' : 'fa-sort-down') + ' sort-icon';

                var filas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                filas.sort(function(a, b) {
                    var ca = a.querySelectorAll('td')[idx];
                    var cb = b.querySelectorAll('td')[idx];
                    if (!ca || !cb) return 0;
                    var va = valorCelda(ca);
                    var vb = valorCelda(cb);
                    var r = 0;
                    if (va.tipo === 'num' && vb.tipo === 'num') {
                        r = va.v - vb.v;
                    } else {
                        r = String(va.v).localeCompare(String(vb.v), 'es');
                    }
                    return asc ? r : -r;
                });
                filas.forEach(function(f) { tbody.appendChild(f); });
            });
        });
    });

    // Validación al generar
    var form = document.getElementById('formConsolidado');
    if (form) {
        form.addEventListener('submit', function(e) {
            var meses = document.querySelectorAll('#gridMeses input[type="checkbox"]:checked');
            var tipos = document.querySelectorAll('#gridTipos input[type="checkbox"]:checked');
            if (meses.length === 0 || tipos.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Selección incompleta',
                    html: meses.length === 0
                        ? 'Marque al menos <strong>un mes</strong> del año para consolidar.'
                        : 'Marque al menos <strong>un tipo de nómina</strong> para consolidar.',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
                });
            }
        });
    }

    // Reloj analógico + digital (patrón solic_userinfo)
    var DIAS_RELOJ = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var MESES_RELOJ = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var svgNSReloj = 'http://www.w3.org/2000/svg';
    var ticksReloj = document.getElementById('consRelojTicks');
    if (ticksReloj) {
        for (var iTick = 0; iTick < 12; iTick++) {
            var mayorTick = (iTick % 3 === 0);
            var lnTick = document.createElementNS(svgNSReloj, 'line');
            lnTick.setAttribute('x1', '50'); lnTick.setAttribute('y1', '7');
            lnTick.setAttribute('x2', '50'); lnTick.setAttribute('y2', mayorTick ? '13' : '11');
            lnTick.setAttribute('stroke', mayorTick ? '#67e8f9' : 'rgba(103,232,249,0.45)');
            lnTick.setAttribute('stroke-width', mayorTick ? '2.5' : '1.5');
            lnTick.setAttribute('stroke-linecap', 'round');
            lnTick.setAttribute('transform', 'rotate(' + (iTick * 30) + ' 50 50)');
            ticksReloj.appendChild(lnTick);
        }
    }
    function p2Reloj(n) { return (n < 10 ? '0' : '') + n; }
    function actualizarRelojCons() {
        var f = new Date();
        var h = f.getHours();
        var sufijo = h >= 12 ? 'p.m.' : 'a.m.';
        var h12 = h % 12; if (h12 === 0) h12 = 12;
        var horaEl = document.getElementById('consRelojHora');
        var fechaEl = document.getElementById('consRelojFecha');
        if (!horaEl || !fechaEl) return;
        horaEl.innerHTML = '<i class="fas fa-clock"></i> ' + h12 + ':' + p2Reloj(f.getMinutes()) + ':' + p2Reloj(f.getSeconds())
            + ' <small>' + sufijo + '</small>';
        fechaEl.textContent = DIAS_RELOJ[f.getDay()] + ', '
            + f.getDate() + ' de ' + MESES_RELOJ[f.getMonth()] + ' de ' + f.getFullYear();
        var seg = f.getSeconds();
        var min = f.getMinutes() + seg / 60;
        var hor = (h % 12) + min / 60;
        var mSeg = document.getElementById('consManecillaSegundo');
        var mMin = document.getElementById('consManecillaMinuto');
        var mHor = document.getElementById('consManecillaHora');
        if (mSeg) mSeg.setAttribute('transform', 'rotate(' + (seg * 6) + ' 50 50)');
        if (mMin) mMin.setAttribute('transform', 'rotate(' + (min * 6) + ' 50 50)');
        if (mHor) mHor.setAttribute('transform', 'rotate(' + (hor * 30) + ' 50 50)');
    }
    actualizarRelojCons();
    setInterval(actualizarRelojCons, 1000);
})();
</script>

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
<script>
(function () {
    var btn = document.getElementById('btnCollapseAll');
    if (!btn) return;

    var titles = document.querySelectorAll('.card-collapse-title');
    var todasColapsadas = true;

    function ajustarScroll() {
        function hacer(suave) {
            var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            if (window.scrollY > maxScroll + 1) {
                if (suave) {
                    window.scrollTo({ top: Math.max(0, maxScroll), behavior: 'smooth' });
                } else {
                    window.scrollTo(0, Math.max(0, maxScroll));
                }
            }
        }
        setTimeout(function () { hacer(true); }, 350);
        setTimeout(function () { hacer(true); }, 750);
        setTimeout(function () { hacer(false); }, 1200);
    }

    function aplicar(expandir) {
        titles.forEach(function (title) {
            var target = title.getAttribute('data-bs-target');
            if (!target) return;
            var el = document.querySelector(target);
            if (!el) return;
            var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            if (expandir) { inst.show(); } else { inst.hide(); }
            title.classList.toggle('collapsed', !expandir);
            title.setAttribute('aria-expanded', expandir ? 'true' : 'false');
        });
        todasColapsadas = !expandir;
        actualizarEstado();
        if (!expandir) { setTimeout(ajustarScroll, 400); }
    }

    function actualizarEstado() {
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = todasColapsadas ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }
        btn.title = todasColapsadas ? 'Expandir todas las tarjetas' : 'Colapsar todas las tarjetas';
        btn.setAttribute('data-tooltip', btn.title);
    }

    btn.addEventListener('click', function () { aplicar(todasColapsadas); });
    actualizarEstado();
})();
</script>

<script>
// Tras pulsar "Generar Consolidado" la página vuelve con query string:
// se expande el card "Consolidado por tipo de nómina" y se hace scroll hasta él.
(function () {
    if (!window.location.search) return; // primera carga (sin filtro): queda colapsado
    var el = document.getElementById('collapseTipo');
    if (!el) return;
    var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
    inst.show();
    var title = document.querySelector('[data-bs-target="#collapseTipo"]');
    if (title) { title.classList.remove('collapsed'); title.setAttribute('aria-expanded', 'true'); }
    setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 350);
})();
</script>

<script>
// ==========================================
// IMPRIMIR / EXPORTAR CONSOLIDADO
// (patrón reportes.php: Word, Excel, PDF + CSV/TXT)
// ==========================================
window.printData = <?php echo json_encode($print_data, JSON_UNESCAPED_UNICODE); ?>;

var PRINT_TOOLBAR_HTML = '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;">'
    + '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
    + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#16a34a\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#22c55e\';this.style.transform=\'translateY(0)\';">'
    + '🖨️ Imprimir</button>'
    + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.transform=\'translateY(-0.0625rem)\';" onmouseout="this.style.background=\'#ef4444\';this.style.transform=\'translateY(0)\';">'
    + '✖ Cerrar</button>'
    + '</div><div style="height:3.4375rem;"></div>'
    + '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style>'
    + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script>';

function obtenerFechaHora12H() {
    return new Date().toLocaleString('es-ES', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
}
function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>]/g, function (m) {
        return m === '&' ? '&amp;' : (m === '<' ? '&lt;' : '&gt;');
    });
}
function nombreArchivoCons(ext) {
    var f = new Date();
    return 'Consolidado_Pagos_' + f.getFullYear()
        + ('0' + (f.getMonth() + 1)).slice(-2)
        + ('0' + f.getDate()).slice(-2) + '.' + ext;
}
function descargarBlobCons(blob, nombre) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = nombre;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
}
function datosConsOk() {
    if (!window.printData) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Los datos del consolidado no están disponibles.' });
        return false;
    }
    var d = window.printData;
    if (!d.meses.length || !d.tipos.length) {
        Swal.fire({ icon: 'warning', title: 'Sin selección', html: 'Marque al menos <strong>un mes</strong> y <strong>un tipo de nómina</strong> y pulse <strong>Generar Consolidado</strong>.' });
        return false;
    }
    return true;
}

function construirHtmlInformeConsolidado(opts) {
    opts = opts || {};
    if (!datosConsOk()) return '';

    const data = window.printData;
    const fechaHora = obtenerFechaHora12H();
    const fmt = (val) => new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(val || 0);
    const paraWord = opts.modo === 'word';

    // ---- Filas: consolidado por tipo ----
    let filasTipo = '';
    data.tipos.forEach(t => {
        filasTipo += `<tr>
            <td>${escapeHtml(t.label)}</td>
            <td style="text-align:center;">${t.nominas}</td>
            <td style="text-align:center;">${t.trabajadores}</td>
            <td style="text-align:center;">${t.registros}</td>
            <td style="text-align:right;">${fmt(t.devengado)}</td>
            <td style="text-align:right;">${fmt(t.contribucion)}</td>
            <td style="text-align:right;">${fmt(t.deducciones)}</td>
            <td style="text-align:right;">${fmt(t.neto)}</td>
        </tr>`;
    });
    const tt = data.total;
    filasTipo += `<tr style="font-weight:bold; background:#eef2f7;">
        <td>TOTAL CONSOLIDADO</td>
        <td style="text-align:center;">${tt.nominas}</td>
        <td style="text-align:center;">${tt.trabajadores}</td>
        <td style="text-align:center;">${tt.registros}</td>
        <td style="text-align:right;">${fmt(tt.devengado)}</td>
        <td style="text-align:right;">${fmt(tt.contribucion)}</td>
        <td style="text-align:right;">${fmt(tt.deducciones)}</td>
        <td style="text-align:right;">${fmt(tt.neto)}</td>
    </tr>`;

    // ---- Filas: consolidado por mes ----
    let filasMes = '';
    data.meses.forEach(m => {
        filasMes += `<tr>
            <td>${escapeHtml(m.label)}</td>
            <td style="text-align:center;">${m.nominas}</td>
            <td style="text-align:center;">${m.trabajadores}</td>
            <td style="text-align:center;">${m.registros}</td>
            <td style="text-align:right;">${fmt(m.devengado)}</td>
            <td style="text-align:right;">${fmt(m.contribucion)}</td>
            <td style="text-align:right;">${fmt(m.deducciones)}</td>
            <td style="text-align:right;">${fmt(m.neto)}</td>
        </tr>`;
    });
    filasMes += `<tr style="font-weight:bold; background:#eef2f7;">
        <td>TOTAL PERÍODO</td>
        <td style="text-align:center;">${tt.nominas}</td>
        <td style="text-align:center;">${tt.trabajadores}</td>
        <td style="text-align:center;">${tt.registros}</td>
        <td style="text-align:right;">${fmt(tt.devengado)}</td>
        <td style="text-align:right;">${fmt(tt.contribucion)}</td>
        <td style="text-align:right;">${fmt(tt.deducciones)}</td>
        <td style="text-align:right;">${fmt(tt.neto)}</td>
    </tr>`;

    // ---- Filas: detalle mes x tipo ----
    const filasDetalle = data.detalle.map(d => `<tr>
        <td>${escapeHtml(d.mes_label)}</td>
        <td>${escapeHtml(d.tipo_label)}</td>
        <td style="text-align:center;">${d.nominas}</td>
        <td style="text-align:center;">${d.trabajadores}</td>
        <td style="text-align:center;">${d.registros}</td>
        <td style="text-align:right;">${fmt(d.devengado)}</td>
        <td style="text-align:right;">${fmt(d.contribucion)}</td>
        <td style="text-align:right;">${fmt(d.deducciones)}</td>
        <td style="text-align:right;">${fmt(d.neto)}</td>
    </tr>`);

    // ---- Helpers de maquetación ----
    const logoHtml = data.logo
        ? (paraWord
            ? `<img src="${data.logo}" width="113" height="113" style="width:3cm; height:3cm;">`
            : `<img src="${data.logo}">`)
        : '';

    const pageHeader = (titulo) => {
        if (paraWord) {
            return `<table class="print-header" style="width:100%;">
                <tr>
                    <td class="logo-area">${logoHtml}</td>
                    <td class="header-text"><h1>${escapeHtml(data.empresa)}</h1><h2>${escapeHtml(titulo)}</h2></td>
                    <td class="header-right">Generado: ${fechaHora}</td>
                </tr>
            </table>`;
        }
        return `<div class="print-header">
            <div class="logo-area">${logoHtml}</div>
            <div class="header-text"><h1>${escapeHtml(data.empresa)}</h1><h2>${escapeHtml(titulo)}</h2></div>
            <div class="header-right">Generado: ${fechaHora}</div>
        </div>`;
    };

    const gridStats = (items) => {
        if (paraWord) {
            const celdas = items.map(it => `<td class="grid-item"><div class="grid-val" ${it.color ? `style="color: ${it.color};"` : ''}>${it.val}</div><div class="grid-lbl">${it.lbl}</div></td>`).join('');
            return `<table class="grid-stats"><tr>${celdas}</tr></table>`;
        }
        const celdas = items.map(it => `<div class="grid-item"><div class="grid-val" ${it.color ? `style="color: ${it.color};"` : ''}>${it.val}</div><div class="grid-lbl">${it.lbl}</div></div>`).join('');
        return `<div class="grid-stats">${celdas}</div>`;
    };

    const signaturesHtml = () => {
        const firma = (titulo, nombre, cargo) => `<div style="width:46%; text-align: center;">
            <p style="font-size:8pt; color:#555; margin-bottom:2.8125rem;">${titulo}</p>
            <div style="border-top: 0.0938rem solid #000; width:90%; margin:0 auto; padding-top:0.3125rem;">
                <strong style="font-size:8.5pt;">${escapeHtml(nombre)}</strong><br>
                <span style="font-size:7.5pt; color:#666;">${cargo}</span>
            </div>
        </div>`;
        const ambas = firma('Aprobado por:', data.jefe, 'Jefe de Proyecto') + firma('Revisado por:', data.especialista, 'Especialista en Gestión Económica');
        if (paraWord) {
            return `<table class="signatures-area" style="margin-top:2.5rem;"><tr><td style="border:none; width:50%;">${firma('Aprobado por:', data.jefe, 'Jefe de Proyecto')}</td><td style="border:none; width:50%;">${firma('Revisado por:', data.especialista, 'Especialista en Gestión Económica')}</td></tr></table>`;
        }
        return `<div class="signatures-area" style="display:flex; justify-content:space-between; margin-top:2.5rem; padding:0 0.625rem;">${ambas}</div>`;
    };

    const pageFooter = (label, num, total) => `<div class="print-footer"><div>Consolidado de Pagos por Tipo de Nómina</div><div>${label} · Página ${num} de ${total}</div></div>`;

    // ---- Páginas del informe ----
    const paginas = [];

    paginas.push({
        titulo: 'Resumen del Consolidado',
        cuerpo: gridStats([
            { val: fmt(tt.devengado), lbl: 'Devengado' },
            { val: fmt(tt.contribucion), lbl: 'Contrib. Esp.', color: '#a16207' },
            { val: fmt(tt.deducciones), lbl: 'Deducciones', color: '#b91c1c' },
            { val: fmt(tt.neto), lbl: 'Neto Pagado', color: '#15803d' },
            { val: tt.trabajadores, lbl: 'Trabajadores' },
            { val: tt.registros, lbl: 'Registros' },
            { val: tt.nominas, lbl: 'Nóminas' }
        ])
        + `<div class="section-title">Consolidado por Tipo de Nómina · ${escapeHtml(data.periodo_label)}</div>
           <table>
               <thead><tr>
                   <th>Tipo Nómina</th><th style="text-align:center; width:8%;">Nóminas</th><th style="text-align:center; width:9%;">Trabaj.</th><th style="text-align:center; width:9%;">Registros</th>
                   <th style="text-align:right;">Devengado</th><th style="text-align:right;">Contrib. Esp.</th><th style="text-align:right;">Deducciones</th><th style="text-align:right;">Neto Pagado</th>
               </tr></thead>
               <tbody>${filasTipo}</tbody>
           </table>
           <div class="section-title">Consolidado por Mes (tipos marcados) · <?php echo (int)$anio; ?></div>
           <table>
               <thead><tr>
                   <th>Mes</th><th style="text-align:center; width:10%;">Nóminas</th><th style="text-align:center; width:11%;">Trabaj.</th><th style="text-align:center; width:11%;">Registros</th>
                   <th style="text-align:right;">Devengado</th><th style="text-align:right;">Contrib. Esp.</th><th style="text-align:right;">Deducciones</th><th style="text-align:right;">Neto Pagado</th>
               </tr></thead>
               <tbody>${filasMes}</tbody>
           </table>
           <div style="font-size:7.5pt; color:#666; margin-top:0.375rem;">Tipos incluidos: ${escapeHtml(data.tipos_label)} · Datos actualizados al ${fechaHora}</div>`
    });

    // Detalle paginado (30 filas por página)
    const CHUNK = 30;
    if (filasDetalle.length) {
        for (let i = 0; i < filasDetalle.length; i += CHUNK) {
            const desde = i / CHUNK;
            const parte = filasDetalle.slice(i, i + CHUNK).join('');
            const esPrimera = i === 0;
            paginas.push({
                titulo: 'Detalle por Mes y Tipo' + (esPrimera ? '' : ' (continuación)'),
                cuerpo: (esPrimera ? `<div class="section-title">Detalle por Mes y Tipo · ${escapeHtml(data.periodo_label)}</div>` : '')
                    + `<table>
                        <thead><tr>
                            <th style="width:16%;">Mes</th><th style="width:22%;">Tipo</th>
                            <th style="text-align:center;">Nóminas</th><th style="text-align:center;">Trabaj.</th><th style="text-align:center;">Registros</th>
                            <th style="text-align:right;">Devengado</th><th style="text-align:right;">Contrib. Esp.</th><th style="text-align:right;">Deducciones</th><th style="text-align:right;">Neto Pagado</th>
                        </tr></thead>
                        <tbody>${parte}</tbody>
                        ${i + CHUNK >= filasDetalle.length ? `<tfoot><tr style="font-weight:bold; background:#eef2f7;">
                            <td colspan="2">TOTAL DETALLE</td>
                            <td style="text-align:center;">${tt.nominas}</td>
                            <td style="text-align:center;">${tt.trabajadores}</td>
                            <td style="text-align:center;">${tt.registros}</td>
                            <td style="text-align:right;">${fmt(tt.devengado)}</td>
                            <td style="text-align:right;">${fmt(tt.contribucion)}</td>
                            <td style="text-align:right;">${fmt(tt.deducciones)}</td>
                            <td style="text-align:right;">${fmt(tt.neto)}</td>
                        </tr></tfoot>` : ''}
                    </table>`,
                firmas: (i + CHUNK >= filasDetalle.length)
            });
        }
    } else {
        paginas.push({
            titulo: 'Detalle por Mes y Tipo',
            cuerpo: '<p style="font-size:9pt; color:#666;">No hay filas de detalle para la selección actual.</p>',
            firmas: true
        });
    }

    const totalPaginas = paginas.length;

    const portadaHtml = `
        <div class="page-sheet portada">
            <div class="portada-inner">
                ${data.logo ? `<div class="portada-logo">${logoHtml}</div>` : ''}
                <h1 class="portada-titulo">Consolidado de Pagos por Tipo de Nómina</h1>
                <div class="portada-empresa">${escapeHtml(data.empresa)}</div>
                <div class="portada-periodo">${escapeHtml(data.periodo_label)}</div>
            </div>
        </div>`;

    let cuerpoPaginas = '';
    paginas.forEach((p, idx) => {
        const num = idx + 1;
        cuerpoPaginas += `
            <div class="page-sheet${num === totalPaginas ? ' last' : ''}">
                ${pageHeader(p.titulo)}
                ${p.cuerpo}
                ${p.firmas ? signaturesHtml() : ''}
                ${pageFooter(p.titulo, num, totalPaginas)}
            </div>`;
    });

    let html = `
        <!DOCTYPE html>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <title>Consolidado de Pagos - ${escapeHtml(data.empresa)}</title>
            <meta charset="UTF-8">
            <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
            <style>
                * { margin:0; padding:0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Arial, sans-serif; font-size:8.5pt; color: #000; background: white; }
                ${paraWord
                    ? `@page WordSection1 { size: 792pt 612pt; mso-page-orientation: landscape; margin:0.5in 0.6in 0.75in 0.6in; }
                       div.WordSection1 { page: WordSection1; }
                       .page-sheet { display: block !important; width:100% !important; height:auto !important; page-break-after: auto !important; break-after: auto !important; }
                       .rep-word-break { page-break-before: always !important; break-before: page !important; }
                       .logo-area img { width:3cm !important; height:3cm !important; max-width:none !important; max-height:none !important; object-fit: contain; }
                       .print-header { display: table !important; width:100% !important; }
                       .print-header .logo-area, .print-header .header-text, .print-header .header-right { display: table-cell !important; vertical-align: middle !important; }
                       .grid-stats { display: table !important; width:100% !important; }
                       .grid-item { display: table-cell !important; vertical-align: middle !important; }
                       .signatures-area { display: table !important; width:100% !important; }
                       .print-footer { position: static !important; margin-top:1.875rem; }`
                    : `@page { size: letter portrait; margin:0; }`}

                .page-sheet {
                    width:215.9mm; height:279.4mm; padding:15mm 15mm 20mm 15mm;
                    position: relative; background: white; page-break-after: always;
                    display: flex; flex-direction: column;
                }
                .page-sheet.last { page-break-after: auto; }

                .print-header {
                    display: flex; justify-content: space-between; align-items: center;
                    border-bottom: 0.125rem solid #004B87; padding-bottom:0.5rem; margin-bottom:0.9375rem;
                }
                .logo-area { width:15%; }
                .logo-area img { max-height:3.125rem; max-width:100%; }
                .header-text { text-align: center; width:60%; }
                .header-text h1 { font-size:11pt; color: #004B87; text-transform: uppercase; margin:0; }
                .header-text h2 { font-size:9pt; color: #333; margin-top:0.125rem; }
                .header-right { width:25%; text-align: right; font-size:7.5pt; color: #555; line-height:1.3; }

                .section-title {
                    font-size:9.5pt; font-weight: bold; color: #004B87;
                    border-bottom: 0.0938rem solid #004B87; padding-bottom:0.1875rem;
                    margin:0.75rem 0 0.5rem 0; text-transform: uppercase;
                }

                .portada { text-align: center; }
                .portada-inner { margin: 48mm auto 0; border: 0.1875rem solid #004B87; padding: 2.5rem 3rem; display: block; width: fit-content; max-width: 82%; box-sizing: border-box; }
                .portada-logo { margin: 0 auto 1.5625rem; }
                .portada-logo img { max-height: 6.25rem; max-width: 6.25rem; object-fit: contain; }
                .portada-titulo { font-size: 17pt; font-weight: bold; color: #004B87; text-transform: uppercase; margin-bottom: 1.5625rem; }
                .portada-empresa { font-size: 24pt; font-weight: bold; color: #111; text-transform: uppercase; }
                .portada-periodo { font-size: 14pt; color: #333; margin-top: 1.5625rem; }

                .grid-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap:0.625rem; margin-bottom:0.75rem; }
                .grid-item { background: #f8fafc; border: 0.0625rem solid #e2e8f0; padding:0.5rem; border-radius: 0.375rem; text-align: center; }
                .grid-val { font-size:11pt; font-weight: bold; color: #004B87; }
                .grid-lbl { font-size:7pt; color: #64748b; text-transform: uppercase; margin-top:0.125rem; }

                table { width:100%; border-collapse: collapse; margin-bottom:0.75rem; font-size:7.5pt; }
                th, td { border: 0.0625rem solid #cbd5e1; padding:0.25rem 0.375rem; line-height:1.2; }
                th { background-color: #004B87; color: white; font-weight: bold; text-transform: uppercase; text-align: left; }

                .print-footer {
                    position: absolute; bottom:10mm; left:15mm; right:15mm;
                    border-top: 0.0625rem solid #bbb; padding-top:0.3125rem;
                    font-size:7.5pt; color: #555; display: flex; justify-content: space-between;
                }

                @media print { .page-sheet { height:100vh; } .no-print { display: none !important; } }
            </style>
        </head>
        <body>
            ${paraWord ? '' : PRINT_TOOLBAR_HTML}
            ${paraWord ? '<div class="WordSection1">' : ''}
            ${portadaHtml}
            ${cuerpoPaginas}
            ${paraWord ? '</div>' : ''}
        </body>
        </html>
    `;

    if (paraWord) {
        let primeraHoja = true;
        html = html.replace(/<div class="page-sheet[ "][^"]*"/g, function (match) {
            if (primeraHoja) { primeraHoja = false; return match; }
            return match.replace('<div class="page-sheet', '<div class="page-sheet rep-word-break');
        });
    }

    return html;
}

// ==========================================
// IMPRIMIR (ventana con vista previa)
// ==========================================
document.getElementById('btnPrintReport')?.addEventListener('click', function () {
    const html = construirHtmlInformeConsolidado({ modo: 'print' });
    if (!html) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire({ icon: 'warning', title: 'Aviso', text: 'El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes.' });
        return;
    }
    ventana.document.write(html);
    ventana.document.close();
});

// ==========================================
// EXPORTAR A WORD
// ==========================================
document.getElementById('btnExportWord')?.addEventListener('click', function (e) {
    e.preventDefault();
    const html = construirHtmlInformeConsolidado({ modo: 'word' });
    if (!html) return;
    descargarBlobCons(new Blob(['\ufeff', html], { type: 'application/msword' }), nombreArchivoCons('doc'));
});

// ==========================================
// EXPORTAR A EXCEL
// ==========================================
document.getElementById('btnExportExcel')?.addEventListener('click', function (e) {
    e.preventDefault();
    if (!datosConsOk()) return;
    if (typeof XLSX === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'La librería de Excel no cargó.' });
        return;
    }
    const data = window.printData;
    const wb = XLSX.utils.book_new();
    const tt = data.total;

    const wsResumen = XLSX.utils.aoa_to_sheet([
        ['CONSOLIDADO DE PAGOS POR TIPO DE NÓMINA', data.empresa],
        ['Generado', obtenerFechaHora12H()],
        ['Período', data.periodo_label],
        ['Tipos incluidos', data.tipos_label],
        [],
        ['Devengado', tt.devengado],
        ['Contribución Especial', tt.contribucion],
        ['Deducciones', tt.deducciones],
        ['Neto Pagado', tt.neto],
        ['Nóminas', tt.nominas],
        ['Trabajadores', tt.trabajadores],
        ['Registros', tt.registros]
    ]);
    wsResumen['!cols'] = [{ wch: 36 }, { wch: 26 }];
    XLSX.utils.book_append_sheet(wb, wsResumen, 'Resumen');

    const wsTipo = XLSX.utils.aoa_to_sheet([
        ['CONSOLIDADO POR TIPO DE NÓMINA'],
        [],
        ['Tipo Nómina', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'],
        ...data.tipos.map(t => [t.label, t.nominas, t.trabajadores, t.registros, t.devengado, t.contribucion, t.deducciones, t.neto]),
        ['TOTAL CONSOLIDADO', tt.nominas, tt.trabajadores, tt.registros, tt.devengado, tt.contribucion, tt.deducciones, tt.neto]
    ]);
    wsTipo['!cols'] = [{ wch: 24 }, { wch: 10 }, { wch: 13 }, { wch: 11 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 }];
    XLSX.utils.book_append_sheet(wb, wsTipo, 'Por Tipo');

    const wsMes = XLSX.utils.aoa_to_sheet([
        ['CONSOLIDADO POR MES'],
        [],
        ['Mes', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'],
        ...data.meses.map(m => [m.label, m.nominas, m.trabajadores, m.registros, m.devengado, m.contribucion, m.deducciones, m.neto]),
        ['TOTAL PERÍODO', tt.nominas, tt.trabajadores, tt.registros, tt.devengado, tt.contribucion, tt.deducciones, tt.neto]
    ]);
    wsMes['!cols'] = [{ wch: 22 }, { wch: 10 }, { wch: 13 }, { wch: 11 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 }];
    XLSX.utils.book_append_sheet(wb, wsMes, 'Por Mes');

    const wsDetalle = XLSX.utils.aoa_to_sheet([
        ['DETALLE POR MES Y TIPO'],
        [],
        ['Mes', 'Tipo', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'],
        ...data.detalle.map(d => [d.mes_label, d.tipo_label, d.nominas, d.trabajadores, d.registros, d.devengado, d.contribucion, d.deducciones, d.neto]),
        ['TOTAL DETALLE', '', tt.nominas, tt.trabajadores, tt.registros, tt.devengado, tt.contribucion, tt.deducciones, tt.neto]
    ]);
    wsDetalle['!cols'] = [{ wch: 16 }, { wch: 24 }, { wch: 10 }, { wch: 13 }, { wch: 11 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 }];
    XLSX.utils.book_append_sheet(wb, wsDetalle, 'Detalle');

    XLSX.writeFile(wb, nombreArchivoCons('xlsx'));
});

// ==========================================
// EXPORTAR A PDF
// ==========================================
document.getElementById('btnExportPdf')?.addEventListener('click', async function (e) {
    e.preventDefault();
    if (!datosConsOk()) return;
    if (typeof window.jspdf === 'undefined' || typeof window.html2canvas === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Las librerías de PDF no están disponibles.' });
        return;
    }
    Swal.fire({ icon: 'info', title: 'Generando PDF...', text: 'Se está generando el PDF, espere unos segundos.', showConfirmButton: false, allowOutsideClick: false });

    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position: fixed; left: -1250rem; top: 0; width: 816px; height: 1056px; border: 0; background: #ffffff;';
    document.body.appendChild(iframe);

    try {
        const html = construirHtmlInformeConsolidado({ modo: 'pdf' });
        if (!html) throw new Error('No se pudo construir el informe.');

        const baseTag = '<base href="' + location.href + '">';
        const doc = iframe.contentDocument;
        doc.open();
        doc.write(html.replace('<head>', '<head>' + baseTag));
        doc.close();

        await new Promise(resolve => setTimeout(resolve, 400));

        const paginas = doc.querySelectorAll('.page-sheet');
        if (paginas.length === 0) throw new Error('No se encontraron páginas para exportar.');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'px', format: [816, 1056], compress: true });

        for (let i = 0; i < paginas.length; i++) {
            const canvas = await window.html2canvas(paginas[i], {
                scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false,
                windowWidth: 816, windowHeight: 1056
            });
            const img = canvas.toDataURL('image/jpeg', 0.95);
            if (i > 0) pdf.addPage([816, 1056], 'portrait');
            pdf.addImage(img, 'JPEG', 0, 0, 816, 1056);
        }

        pdf.save(nombreArchivoCons('pdf'));
        Swal.close();
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + err.message });
    } finally {
        document.body.removeChild(iframe);
    }
});

// ==========================================
// EXPORTAR A CSV
// ==========================================
document.getElementById('btnExportCsv')?.addEventListener('click', function (e) {
    e.preventDefault();
    if (!datosConsOk()) return;
    const data = window.printData;
    const tt = data.total;
    const n2 = (v) => Number(v || 0).toFixed(2);

    const filas = [];
    filas.push(['CONSOLIDADO DE PAGOS POR TIPO DE NÓMINA']);
    filas.push([data.empresa]);
    filas.push(['Período', data.periodo_label]);
    filas.push(['Tipos incluidos', data.tipos_label]);
    filas.push(['Generado', obtenerFechaHora12H()]);
    filas.push([]);

    filas.push(['RESUMEN']);
    filas.push(['Devengado', n2(tt.devengado)]);
    filas.push(['Contribución Especial', n2(tt.contribucion)]);
    filas.push(['Deducciones', n2(tt.deducciones)]);
    filas.push(['Neto Pagado', n2(tt.neto)]);
    filas.push(['Nóminas', tt.nominas]);
    filas.push(['Trabajadores', tt.trabajadores]);
    filas.push(['Registros', tt.registros]);
    filas.push([]);

    filas.push(['CONSOLIDADO POR TIPO DE NÓMINA']);
    filas.push(['Tipo Nómina', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto']);
    data.tipos.forEach(t => filas.push([t.label, t.nominas, t.trabajadores, t.registros, n2(t.devengado), n2(t.contribucion), n2(t.deducciones), n2(t.neto)]));
    filas.push(['TOTAL CONSOLIDADO', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)]);
    filas.push([]);

    filas.push(['CONSOLIDADO POR MES']);
    filas.push(['Mes', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto']);
    data.meses.forEach(m => filas.push([m.label, m.nominas, m.trabajadores, m.registros, n2(m.devengado), n2(m.contribucion), n2(m.deducciones), n2(m.neto)]));
    filas.push(['TOTAL PERÍODO', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)]);
    filas.push([]);

    filas.push(['DETALLE POR MES Y TIPO']);
    filas.push(['Mes', 'Tipo', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto']);
    data.detalle.forEach(d => filas.push([d.mes_label, d.tipo_label, d.nominas, d.trabajadores, d.registros, n2(d.devengado), n2(d.contribucion), n2(d.deducciones), n2(d.neto)]));
    filas.push(['TOTAL DETALLE', '', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)]);

    const csv = filas.map(function (f) {
        return f.map(function (v) {
            const s = String(v == null ? '' : v);
            return /[;"\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        }).join(';');
    }).join('\r\n');

    descargarBlobCons(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }), nombreArchivoCons('csv'));
});

// ==========================================
// EXPORTAR A TXT
// ==========================================
document.getElementById('btnExportTxt')?.addEventListener('click', function (e) {
    e.preventDefault();
    if (!datosConsOk()) return;
    const data = window.printData;
    const tt = data.total;
    const n2 = (v) => Number(v || 0).toFixed(2);
    const linea = '============================================================';
    const lineas = [];

    lineas.push('CONSOLIDADO DE PAGOS POR TIPO DE NÓMINA');
    lineas.push(data.empresa);
    lineas.push('Período: ' + data.periodo_label);
    lineas.push('Tipos incluidos: ' + data.tipos_label);
    lineas.push('Generado: ' + obtenerFechaHora12H());
    lineas.push(linea);

    lineas.push('RESUMEN');
    lineas.push(['Devengado', n2(tt.devengado)].join('\t'));
    lineas.push(['Contribución Especial', n2(tt.contribucion)].join('\t'));
    lineas.push(['Deducciones', n2(tt.deducciones)].join('\t'));
    lineas.push(['Neto Pagado', n2(tt.neto)].join('\t'));
    lineas.push(['Nóminas', tt.nominas].join('\t'));
    lineas.push(['Trabajadores', tt.trabajadores].join('\t'));
    lineas.push(['Registros', tt.registros].join('\t'));
    lineas.push(linea);

    lineas.push('CONSOLIDADO POR TIPO DE NÓMINA');
    lineas.push(['Tipo Nómina', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'].join('\t'));
    data.tipos.forEach(t => lineas.push([t.label, t.nominas, t.trabajadores, t.registros, n2(t.devengado), n2(t.contribucion), n2(t.deducciones), n2(t.neto)].join('\t')));
    lineas.push(['TOTAL CONSOLIDADO', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)].join('\t'));
    lineas.push(linea);

    lineas.push('CONSOLIDADO POR MES');
    lineas.push(['Mes', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'].join('\t'));
    data.meses.forEach(m => lineas.push([m.label, m.nominas, m.trabajadores, m.registros, n2(m.devengado), n2(m.contribucion), n2(m.deducciones), n2(m.neto)].join('\t')));
    lineas.push(['TOTAL PERÍODO', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)].join('\t'));
    lineas.push(linea);

    lineas.push('DETALLE POR MES Y TIPO');
    lineas.push(['Mes', 'Tipo', 'Nóminas', 'Trabajadores', 'Registros', 'Devengado', 'Contribución', 'Deducciones', 'Neto'].join('\t'));
    data.detalle.forEach(d => lineas.push([d.mes_label, d.tipo_label, d.nominas, d.trabajadores, d.registros, n2(d.devengado), n2(d.contribucion), n2(d.deducciones), n2(d.neto)].join('\t')));
    lineas.push(['TOTAL DETALLE', '', tt.nominas, tt.trabajadores, tt.registros, n2(tt.devengado), n2(tt.contribucion), n2(tt.deducciones), n2(tt.neto)].join('\t'));
    lineas.push(linea);
    lineas.push('Nota: Solo se incluyen nóminas en estado Contabilizadas de los meses marcados dentro del año ' + data.anio + '.');

    descargarBlobCons(new Blob([lineas.join('\r\n')], { type: 'text/plain;charset=utf-8' }), nombreArchivoCons('txt'));
});
</script>

</body>
</html>
