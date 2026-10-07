<?php
// modules/cierres.php - Cierre de períodos de nómina (mes y año)
//
// Acceso: Administrador, Supervisor General y Contador/Editor (solo lectura).
// Cerrar y reabrir períodos exige permiso 'crear'/'editar' sobre el módulo
// 'cierres', que solo tienen Administrador y Supervisor General.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once '../config/migraciones.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!permiso_puede('cierres', 'ver')) {
    permiso_denegar_acceso('Cierres de nómina');
}

// La tabla debe existir aunque el usuario entre por otra vía que no pase por login.php.
asegurarTablaCierresPeriodo($pdo);
asegurarPeriodoNominasEnCurso($pdo);

// ==========================================
// CAPACIDADES DEL ROL
// ==========================================
$puedeCerrar  = permiso_puede('cierres', 'crear');
$puedeReabrir = permiso_puede('cierres', 'editar');

$usuarioActual    = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuarioActualId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$usuarioAuditoria = $_SESSION['usuario'] ?? $usuarioActual;

/**
 * Responde en JSON y termina. Todo el router AJAX sale por aquí.
 */
function cierres_json($payload, $codigo = 200)
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function cierres_param($clave, $pordefecto = null)
{
    return isset($_REQUEST[$clave]) ? trim((string)$_REQUEST[$clave]) : $pordefecto;
}

/**
 * Un año es admisible si es un entero de 4 cifras dentro del rango que soporta el
 * calendario gregoriano (1000-9999).
 *
 * Antes se limitaba a 2000-2100 porque las fechas se calculaban con
 * date('Y-m-t', strtotime(...)), y el PHP de 32 bits de este servidor no puede
 * representar epochs posteriores al 19/01/2038. Con ultimoDiaDePeriodo() /
 * fechaComoDateTime() ya no hay tope, asi que un año como 2974 es una fecha
 * perfectamente valida y no debe rechazarse.
 */
function cierres_anio_admite($anio)
{
    return is_numeric($anio) && (int)$anio >= 1000 && (int)$anio <= 9999;
}

// ==========================================
// ROUTER AJAX
// ==========================================
$accion = cierres_param('accion', '');

if ($accion !== '') {

    switch ($accion) {

        // Previsualización de un mes: totales, desglose y si se puede cerrar.
        case 'verificar_mes': {
            $anio = (int)cierres_param('anio', 0);
            $mes  = (int)cierres_param('mes', 0);
            if (!cierres_anio_admite($anio) || $mes < 1 || $mes > 12) {
                cierres_json(['success' => false, 'mensaje' => 'Período inválido']);
            }

            $desde = sprintf('%04d-%02d-01', $anio, $mes);
            $hasta = ultimoDiaDePeriodo($desde);

            $check  = puedeCerrarMesNominas($pdo, $anio, $mes);
            $stats  = resumenTotalesPeriodoNominas($pdo, $desde, $hasta);
            $desglo = desgloseTiposNominaPeriodo($pdo, $desde, $hasta);
            $cierre = obtenerCierrePeriodoNominas($pdo, $anio, $mes);

            cierres_json([
                'success'    => true,
                'puede_cerrar' => !empty($check['puede_cerrar']),
                'cierre_en_cero' => !empty($check['cierre_en_cero']),
                'mensaje'    => $check['mensaje'] ?? '',
                'pendientes' => $check['pendientes'] ?? [],
                'estadisticas' => $stats,
                'desglose'   => $desglo,
                'cierre'     => $cierre,
                'etiqueta'   => etiquetaMesNominas($mes),
                'en_curso'   => periodoNominasEnCurso($pdo),
            ]);
        }

        // Previsualización del año: estado de los 12 meses.
        case 'verificar_ano': {
            $anio = (int)cierres_param('anio', 0);
            if (!cierres_anio_admite($anio)) {
                cierres_json(['success' => false, 'mensaje' => 'Año inválido']);
            }

            $check  = puedeCerrarAnioNominas($pdo, $anio);
            $meses  = mesesDelAnioConEstado($pdo, $anio);
            $cierre = obtenerCierrePeriodoNominas($pdo, $anio, 0);

            cierres_json([
                'success'     => true,
                'puede_cerrar' => !empty($check['puede_cerrar']),
                'mensaje'     => $check['mensaje'] ?? '',
                'pendientes'  => $check['pendientes'] ?? [],
                'sin_datos'   => $check['sin_datos_etiquetas'] ?? [],
                'meses'       => $meses,
                'automatica'  => array_keys(mesesConNominaTipo($pdo, $anio)),
                'cierre'      => $cierre,
                'en_curso'    => periodoNominasEnCurso($pdo),
            ]);
        }

        case 'cerrar_mes': {
            if (!$puedeCerrar) {
                cierres_json(['success' => false, 'mensaje' => 'Su rol no tiene permiso para cerrar períodos de nómina'], 403);
            }
            $anio = (int)cierres_param('anio', 0);
            $mes  = (int)cierres_param('mes', 0);
            $nota = cierres_param('observaciones', '');

            $res = registrarCierreMesNominas($pdo, $anio, $mes, $usuarioAuditoria, $nota);

            if (!empty($res['success'])) {
                logAction('cerrar_periodo_nomina', 'cierres',
                    'Cierre de ' . etiquetaMesNominas($mes) . ' ' . $anio,
                    ['anio' => $anio, 'mes' => $mes, 'estadisticas' => $res['estadisticas']],
                    $usuarioActualId);

                $res['meses'] = mesesDelAnioConEstado($pdo, $anio);
            }
            cierres_json($res);
        }

        case 'cerrar_ano': {
            if (!$puedeCerrar) {
                cierres_json(['success' => false, 'mensaje' => 'Su rol no tiene permiso para cerrar períodos de nómina'], 403);
            }
            $anio = (int)cierres_param('anio', 0);
            $nota = cierres_param('observaciones', '');

            $res = registrarCierreAnioNominas($pdo, $anio, $usuarioAuditoria, $nota);

            if (!empty($res['success'])) {
                logAction('cerrar_periodo_nomina', 'cierres',
                    'Cierre del año ' . $anio,
                    ['anio' => $anio, 'estadisticas' => $res['estadisticas'], 'sin_datos' => $res['sin_datos']],
                    $usuarioActualId);

                $res['meses'] = mesesDelAnioConEstado($pdo, $anio);
            }
            cierres_json($res);
        }

        case 'reabrir': {
            if (!$puedeReabrir) {
                cierres_json(['success' => false, 'mensaje' => 'Su rol no tiene permiso para reabrir cierres'], 403);
            }
            $tipo  = (int)cierres_param('tipo', 0);
            $anio  = (int)cierres_param('anio', 0);
            $mes   = (int)cierres_param('mes', 0);
            $motivo = cierres_param('motivo', '');

            $res = reabrirCierreNominas($pdo, $tipo, $anio, $mes, $usuarioAuditoria, $motivo);

            if (!empty($res['success'])) {
                $que = $tipo === 2
                    ? 'el año ' . $anio
                    : etiquetaMesNominas($mes) . ' ' . $anio;
                logAction('reabrir_periodo_nomina', 'cierres', 'Reapertura del cierre de ' . $que,
                    ['tipo' => $tipo, 'anio' => $anio, 'mes' => $mes, 'motivo' => truncarParaAuditoria($motivo, 300)],
                    $usuarioActualId);
            }
            cierres_json($res);
        }

        case 'historial': {
            $anio = (int)cierres_param('anio', 0);
            $filtro = [];
            if ($anio > 0) { $filtro['anio'] = $anio; }

            $filas = [];
            foreach (listarCierresNominas($pdo, $filtro) as $c) {
                $esAnual = ((int)$c['tipo'] === 2);
                $anioCierre = (int)$c['periodo_anio'];

                // El cierre anual es DEFINITIVO: ni el ano ni sus meses se pueden
                // reabrir despues. El flag viaja al frontend para no ofrecer un
                // boton que el servidor va a rechazar.
                $anualVigente = $esAnual
                    ? ($c['estado'] === 'cerrado')
                    : (cierreAnualVigenteNominas($pdo, $anioCierre) !== null);
                $reabrible = ($c['estado'] === 'cerrado') && !$esAnual && !$anualVigente;

                $motivoNoReabrible = '';
                if ($c['estado'] === 'cerrado' && !$reabrible) {
                    $motivoNoReabrible = $esAnual
                        ? 'El cierre de ' . $anioCierre . ' es definitivo: una vez cerrado el año no se puede reabrir.'
                        : 'El año ' . $anioCierre . ' está cerrado. El cierre anual es definitivo, '
                          . 'así que este mes ya no se puede reabrir.';
                }

                $filas[] = [
                    'id'           => (int)$c['id'],
                    'tipo'         => (int)$c['tipo'],
                    'tipo_texto'   => $esAnual ? 'Anual' : 'Mensual',
                    'anio'         => $anioCierre,
                    'mes'          => (int)$c['periodo_mes'],
                    'etiqueta'     => $esAnual ? 'Año completo' : etiquetaMesNominas((int)$c['periodo_mes']),
                    'estado'       => $c['estado'],
                    'reabrible'    => $reabrible,
                    'motivo_no_reabrible' => $motivoNoReabrible,
                    'total_lotes'         => (int)$c['total_lotes'],
                    'total_trabajadores'  => (int)$c['total_trabajadores'],
                    'total_devengado'     => (float)$c['total_devengado'],
                    'total_deducciones'   => (float)$c['total_deducciones'],
                    'total_neto'          => (float)$c['total_neto'],
                    'total_contribucion'  => (float)$c['total_contribucion'],
                    'observaciones'       => (string)$c['observaciones'],
                    'fecha_cierre'        => (string)$c['fecha_cierre'],
                    'usuario_cierre'      => (string)$c['usuario_cierre'],
                    'motivo_reapertura'   => (string)($c['motivo_reapertura'] ?? ''),
                    'fecha_reapertura'    => (string)($c['fecha_reapertura'] ?? ''),
                    'usuario_reapertura'  => (string)($c['usuario_reapertura'] ?? ''),
                ];
            }
            cierres_json(['success' => true, 'cierres' => $filas]);
        }

        default:
            cierres_json(['success' => false, 'mensaje' => 'Acción no reconocida'], 400);
    }
}

// ==========================================
// DATOS PARA LA VISTA
// ==========================================
$enCurso  = periodoNominasEnCurso($pdo);
$anioBase = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)$enCurso['anio'];
if ($anioBase < 2000 || $anioBase > 2100) {
    $anioBase = (int)$enCurso['anio'];
}

// Años disponibles: los que tienen cierres, los que tienen nominas (aunque no
// tengan ni un cierre: si tiene nominas puede cerrarse, asi que tiene que
// aparecer), más el año en curso y el actual.
$aniosCandidatos = [(int)$enCurso['anio'], (int)date('Y')];
foreach (listarCierresNominas($pdo) as $c) {
    $aniosCandidatos[] = (int)$c['periodo_anio'];
}
foreach (aniosConNominas($pdo) as $a) {
    $aniosCandidatos[] = (int)$a;
}
$aniosCandidatos = array_values(array_unique(array_filter($aniosCandidatos)));
rsort($aniosCandidatos);

$mesesAnio     = mesesDelAnioConEstado($pdo, $anioBase);
$checkAnio     = puedeCerrarAnioNominas($pdo, $anioBase);
$cierreAnio    = obtenerCierrePeriodoNominas($pdo, $anioBase, 0);
// El historial se limita al año que está seleccionado: el combo y la grilla
// siempre trabajan sobre $anioBase, y un listado con cierres de otros años
// hace creer que ese año está cerrado cuando no lo está.
$historial     = listarCierresNominas($pdo, ['anio' => $anioBase]);

// Datos del mes en curso para abrir el panel con algo útil.
$mesInicial    = (int)$enCurso['mes'];
$cierreMesActual = obtenerCierrePeriodoNominas($pdo, $anioBase, $mesInicial);

$desdeMesInicial = sprintf('%04d-%02d-01', $anioBase, $mesInicial);
$hastaMesInicial = ultimoDiaDePeriodo($desdeMesInicial);
$statsMesInicial = resumenTotalesPeriodoNominas($pdo, $desdeMesInicial, $hastaMesInicial);
$desglosMesInicial = desgloseTiposNominaPeriodo($pdo, $desdeMesInicial, $hastaMesInicial);
$checkMesInicial  = puedeCerrarMesNominas($pdo, $anioBase, $mesInicial);

$etiquetasTipo = [
    'automatica'     => 'Automática',
    'extraordinaria' => 'Extraordinaria',
    'vacaciones'     => 'Vacaciones',
    'bono'           => 'Bono',
    'ajuste'         => 'Ajuste',
];

$pageTitle = 'Cierres de nómina';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_config.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle); ?> · TransNuBeT</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sweetalert2.min.css" rel="stylesheet">

    <link rel="stylesheet" href="CSS/cierres.css?v=<?php echo @filemtime(__DIR__ . '/CSS/cierres.css') ?: time(); ?>">
</head>
<body data-puede-cerrar="<?php echo $puedeCerrar ? '1' : '0'; ?>"
      data-puede-reabrir="<?php echo $puedeReabrir ? '1' : '0'; ?>"
      data-anio="<?php echo $anioBase; ?>"
      data-motivo-mes="<?php echo htmlspecialchars($checkMesInicial['mensaje'] ?? '', ENT_QUOTES); ?>"
      data-motivo-anio="<?php echo htmlspecialchars($checkAnio['mensaje'] ?? '', ENT_QUOTES); ?>">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<main class="main-container" id="mainContainer">
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p>Congelamiento de nóminas por mes y por año</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Aviso de solo lectura para roles sin permiso de cierre -->
    <?php if (!$puedeCerrar): ?>
    <div class="cierre-aviso-lectura fade-in-up">
        <i class="fas fa-lock"></i>
        <div>
            <strong>Modo solo lectura.</strong>
            Su rol puede consultar el estado de los cierres y exportar el historial,
            pero no puede cerrar ni reabrir períodos.
        </div>
    </div>
    <?php endif; ?>

    <!-- Selector de año + período en curso -->
    <div class="cierre-toolbar fade-in-up">
        <div class="cierre-periodo-actual">
            <div class="cierre-periodo-icono"><i class="fas fa-play"></i></div>
            <div>
                <div class="cierre-periodo-label">Período en curso</div>
                <div class="cierre-periodo-valor">
                    <?php echo htmlspecialchars(etiquetaMesNominas($enCurso['mes'])); ?> <?php echo (int)$enCurso['anio']; ?>
                </div>
            </div>
        </div>

        <div class="cierre-selector-anio">
            <label for="selAnio"><i class="fas fa-calendar-days"></i><span>Año</span></label>
            <span class="cierre-select-wrap">
                <select id="selAnio" class="form-select">
                    <?php foreach ($aniosCandidatos as $a): ?>
                    <option value="<?php echo $a; ?>" <?php echo $a === $anioBase ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
        </div>

        <a href="../dashboard.php" class="btn-win btn-win-outline btn-win-sm" title="Volver al Dashboard" data-tooltip="Volver al Dashboard" data-tooltip-theme="primary">
            <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
        </a>
    </div>

    <!-- Estado del año -->
    <div class="cierre-panel fade-in-up">
        <div class="cierre-panel-header">
            <h2><i class="fas fa-calendar me-1"></i>Estado del año <?php echo $anioBase; ?></h2>
            <?php if ($cierreAnio && $cierreAnio['estado'] === 'cerrado'): ?>
            <span class="cierre-chip cierre-chip-cerrado"><i class="fas fa-lock"></i>Año cerrado</span>
            <?php elseif ($cierreAnio && $cierreAnio['estado'] === 'revertido'): ?>
            <span class="cierre-chip cierre-chip-revertido"><i class="fas fa-unlock"></i>Cierre revertido</span>
            <?php elseif (!empty($checkAnio['puede_cerrar'])): ?>
            <span class="cierre-chip cierre-chip-listo"><i class="fas fa-circle-check"></i>Listo para cierre</span>
            <?php else: ?>
            <span class="cierre-chip cierre-chip-pendiente"><i class="fas fa-hourglass-half"></i>Con meses abiertos</span>
            <?php endif; ?>
        </div>

        <div class="cierre-meses" id="grillaMeses">
            <?php
            $iconoEstado = [
                'cerrado'   => 'fa-lock',
                'revertido' => 'fa-unlock',
                'pendiente' => 'fa-clock',
                'sin_nomina'=> 'fa-minus',
            ];
            ?>
            <?php foreach ($mesesAnio as $num => $info): ?>
            <button type="button"
                    class="cierre-mes estado-<?php echo htmlspecialchars($info['estado']); ?><?php echo $num === $mesInicial ? ' activo' : ''; ?>"
                    data-mes="<?php echo (int)$num; ?>"
                    data-estado="<?php echo htmlspecialchars($info['estado']); ?>"
                    title="<?php echo htmlspecialchars($info['etiqueta'] . ' ' . $anioBase . ' · ' . $info['estado']); ?>"
                    data-tooltip="<?php echo htmlspecialchars($info['etiqueta'] . ' ' . $anioBase . ' · ' . ucfirst(str_replace('_', ' ', $info['estado']))); ?>"
                    data-tooltip-theme="dark">
                <span class="cierre-mes-cabecera">
                    <span class="cierre-mes-num"><?php echo $num; ?></span>
                    <span class="cierre-mes-nombre"><?php echo htmlspecialchars(substr($info['etiqueta'], 0, 3)); ?></span>
                </span>
                <i class="fas <?php echo htmlspecialchars($iconoEstado[$info['estado']] ?? 'fa-minus'); ?>"></i>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="cierre-leyenda">
            <span><i class="fas fa-lock"></i> Cerrado</span>
            <span><i class="fas fa-unlock"></i> Revertido</span>
            <span><i class="fas fa-clock"></i> Con datos, abierto</span>
            <span><i class="fas fa-minus"></i> Sin nómina</span>
        </div>
    </div>

    <div class="row g-3">
        <!-- Panel del mes seleccionado -->
        <div class="col-12 col-xl-8">
            <div class="cierre-panel fade-in-up">
                <div class="cierre-panel-header">
                    <h2><i class="fas fa-file-invoice-dollar me-1"></i>Cierre mensual <span id="tituloMesSeleccionado"><?php echo htmlspecialchars(etiquetaMesNominas($mesInicial)); ?> <?php echo $anioBase; ?></span></h2>
                    <span id="chipEstadoMes" class="cierre-chip">Cargando…</span>
                </div>

                <div id="cargandoMes" class="cierre-estado-carga">
                    <div class="spinner-border" role="status" aria-hidden="true"></div>
                    <span>Cargando datos del período…</span>
                </div>

                <div id="cuerpoMes" hidden>
                    <div class="cierre-alerta" id="alertaMes" hidden>
                        <i class="fas fa-circle-info"></i>
                        <span id="alertaMesTexto"></span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-layer-group"></i></span>
                                    <span class="cierre-stat-valor" id="mLotes">—</span>
                                </div>
                                <div class="cierre-stat-label">Lotes</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-users"></i></span>
                                    <span class="cierre-stat-valor" id="mTrabajadores">—</span>
                                </div>
                                <div class="cierre-stat-label">Trabajadores</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-money-bill-trend-up"></i></span>
                                    <span class="cierre-stat-valor" id="mDevengado">—</span>
                                </div>
                                <div class="cierre-stat-label">Total Devengado</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-minus-circle"></i></span>
                                    <span class="cierre-stat-valor" id="mDeducciones">—</span>
                                </div>
                                <div class="cierre-stat-label">Deducciones</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-sack-dollar"></i></span>
                                    <span class="cierre-stat-valor" id="mNeto">—</span>
                                </div>
                                <div class="cierre-stat-label">Total Neto</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="cierre-stat">
                                <div class="cierre-stat-cifra">
                                    <span class="cierre-stat-icono"><i class="fas fa-piggy-bank"></i></span>
                                    <span class="cierre-stat-valor" id="mContribucion">—</span>
                                </div>
                                <div class="cierre-stat-label">Contribución</div>
                            </div>
                        </div>
                    </div>

                    <h3 class="cierre-subtitulo"><i class="fas fa-chart-pie me-1"></i>Desglose por tipo de nómina</h3>
                    <div id="desgloseTipos"></div>

                    <div class="cierre-acciones">
                        <button type="button" class="btn-win btn-win-primary" id="btnCerrarMes"
                                data-tooltip="Cerrar el mes y congelar sus nóminas" data-tooltip-theme="primary">
                            <i class="fas fa-lock me-1"></i> Cerrar mes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel del año -->
        <div class="col-12 col-xl-4">
            <div class="cierre-panel fade-in-up">
                <div class="cierre-panel-header">
                    <h2><i class="fas fa-calendar-check me-1"></i>Cierre anual</h2>
                </div>

                <div id="cargandoAnio" class="cierre-estado-carga">
                    <div class="spinner-border" role="status" aria-hidden="true"></div>
                    <span>Cargando estado del año…</span>
                </div>

                <div id="cuerpoAnio" hidden>
                    <div class="cierre-alerta" id="alertaAnio" hidden>
                        <i class="fas fa-circle-info"></i>
                        <span id="alertaAnioTexto"></span>
                    </div>

                    <ul class="cierre-lista-anio" id="listaMesesAnio"></ul>

                    <div class="cierre-acciones">
                        <button type="button" class="btn-win btn-win-primary" id="btnCerrarAnio"
                                data-tooltip="Cerrar el año completo" data-tooltip-theme="primary">
                            <i class="fas fa-calendar-check me-1"></i> Cerrar año <?php echo $anioBase; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de cierres -->
    <div class="cierre-panel fade-in-up">
        <div class="cierre-panel-header">
            <h2><i class="fas fa-clock-rotate-left me-1"></i>Historial de cierres</h2>
            <button type="button" class="btn-win btn-win-outline btn-win-sm" id="btnExportar"
                    data-tooltip="Exportar el historial a CSV" data-tooltip-theme="primary">
                <i class="fas fa-file-csv me-1"></i> Exportar CSV
            </button>
        </div>

        <div id="cargandoHistorial" class="cierre-estado-carga">
            <div class="spinner-border" role="status" aria-hidden="true"></div>
            <span>Cargando historial…</span>
        </div>

        <div id="vacioHistorial" class="cierre-vacio" hidden>
            <i class="fas fa-folder-open"></i>
            Todavía no hay períodos cerrados.
        </div>

        <div id="tablaHistorialWrap" class="cierre-tabla-wrap" hidden>
            <table class="cierre-tabla" id="tablaHistorial">
                <thead>
                    <tr>
                        <th class="js-orden" data-orden="periodo" tabindex="0" role="button"
                            data-tooltip="Ordenar por período" data-tooltip-theme="dark">Período</th>
                        <th class="js-orden" data-orden="tipo" tabindex="0" role="button"
                            data-tooltip="Ordenar por tipo" data-tooltip-theme="dark">Tipo</th>
                        <th class="js-orden" data-orden="estado" tabindex="0" role="button"
                            data-tooltip="Ordenar por estado" data-tooltip-theme="dark">Estado</th>
                        <th class="js-orden text-end" data-orden="total_lotes" tabindex="0" role="button"
                            data-tooltip="Ordenar por lotes" data-tooltip-theme="dark">Lotes</th>
                        <th class="js-orden text-end" data-orden="total_trabajadores" tabindex="0" role="button"
                            data-tooltip="Ordenar por trabajadores" data-tooltip-theme="dark">Trabajadores</th>
                        <th class="js-orden text-end" data-orden="total_devengado" tabindex="0" role="button"
                            data-tooltip="Ordenar por devengado" data-tooltip-theme="dark">Devengado</th>
                        <th class="js-orden text-end" data-orden="total_neto" tabindex="0" role="button"
                            data-tooltip="Ordenar por neto" data-tooltip-theme="dark">Neto</th>
                        <th class="js-orden" data-orden="fecha_cierre" tabindex="0" role="button"
                            data-tooltip="Ordenar por fecha de cierre" data-tooltip-theme="dark">Cerrado</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

</main>

<script>
    window.CIERRES_CONFIG = {
        puedeCerrar: <?php echo $puedeCerrar ? 'true' : 'false'; ?>,
        puedeReabrir: <?php echo $puedeReabrir ? 'true' : 'false'; ?>,
        anio: <?php echo $anioBase; ?>,
        mesInicial: <?php echo $mesInicial; ?>,
        etiquetasTipo: <?php echo json_encode($etiquetasTipo, JSON_UNESCAPED_UNICODE); ?>
    };
</script>
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/cierres.js?v=<?php echo @filemtime(__DIR__ . '/../js/cierres.js') ?: time(); ?>"></script>
</body>
</html>