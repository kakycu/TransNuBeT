<?php
// modules/carpetas.php - Explorador de carpetas del sistema (Exportaciones / Descargas / Salvas)
//
// Acceso: Administrador, Visualizador, Contador/Editor, Supervisor y Programador.
// Cada rol ve el modulo, pero solo dispone de las acciones que le corresponden.
// Para otros roles se emite el modal estándar de "Acceso denegado" y no se navega.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!permiso_puede('dashboard', 'ver')) {
    permiso_denegar_acceso('Carpetas del sistema');
}

if (!carpetas_sistema_permitido()) {
    permiso_denegar_acceso('Carpetas de Exportaciones, Descargas y Salvas');
}

// ==========================================
// DEFINICIÓN DE CARPETAS (centralizada en permisos.php)
// ==========================================
$CARPETAS = carpetas_sistema_definicion();

$solicitada = trim((string)($_GET['carpeta'] ?? 'exportaciones'));
if (!isset($CARPETAS[$solicitada])) {
    $solicitada = 'exportaciones';
}

$info         = $CARPETAS[$solicitada];
$rutaReal     = $info['ruta'] !== false ? $info['ruta'] : false;

// ==========================================
// RUTA RELATIVA (subcarpetas dentro de la carpeta del sistema)
// ==========================================
// Entra por GET "ruta" (p. ej. MI_ZIP/informes). Si es invalida se vuelve a la
// raiz: nunca se permite salir de la carpeta del sistema.
$rutaRelativa = carpetas_ruta_relativa((string)($_GET['ruta'] ?? ''));
if ($rutaRelativa === null) {
    $rutaRelativa = '';
}

$rutaActual = ($rutaReal !== false) ? carpetas_ruta_resolver($rutaReal, $rutaRelativa) : false;
if ($rutaActual === false) {
    $rutaRelativa = '';
    $rutaActual   = $rutaReal;
}

$carpetaLista = $rutaActual !== false && is_dir($rutaActual);
$rutaEnRaiz   = ($rutaRelativa === '');

/**
 * URL de descarga de un archivo, con la ruta relativa y codificada por
 * segmentos (los nombres pueden llevar espacios y acentos).
 */
function carpeta_url_descarga($descargaDir, $rutaRelativa, $nombre)
{
    $ruta = trim((string)$rutaRelativa, '/');
    $partes = $ruta === '' ? [] : explode('/', $ruta);
    $partes[] = $nombre;
    return $descargaDir . '/' . implode('/', array_map('rawurlencode', $partes));
}

/**
 * Enlace de navegacion a una subcarpeta dentro de la carpeta actual.
 */
function carpeta_url_navegacion($clave, $ruta)
{
    $ruta = trim((string)$ruta, '/');
    return '?carpeta=' . urlencode($clave) . ($ruta !== '' ? '&ruta=' . urlencode($ruta) : '');
}

// ==========================================
// LISTADO DE ARCHIVOS
// ==========================================
/**
 * Devuelve el contenido de una carpeta: primero las subcarpetas (ordenadas
 * por nombre) y despues los archivos (fecha descendente).
 * Si $descargaDir no es null, agrega la ruta relativa de descarga.
 */
function carpetas_listar($ruta, $descargaDir, $rutaRelativa = '')
{
    $carpetas = array();
    $archivos = array();

    $entradas = @scandir($ruta);
    if (!is_array($entradas)) {
        return ['carpetas' => $carpetas, 'archivos' => $archivos];
    }

    foreach ($entradas as $entrada) {
        if ($entrada === '.' || $entrada === '..') {
            continue;
        }
        $rutaArchivo = $ruta . DIRECTORY_SEPARATOR . $entrada;

        if (is_dir($rutaArchivo)) {
            $carpetas[] = [
                'nombre' => $entrada,
                'ruta'   => ($rutaRelativa === '' ? '' : $rutaRelativa . '/') . $entrada,
                'fecha'  => (int)@filemtime($rutaArchivo),
            ];
            continue;
        }
        if (!is_file($rutaArchivo)) {
            continue;
        }

        $extension = strtolower(pathinfo($entrada, PATHINFO_EXTENSION));
        $archivos[] = [
            'nombre'    => $entrada,
            'extension' => $extension,
            'bytes'     => (int)@filesize($rutaArchivo),
            'fecha'     => (int)@filemtime($rutaArchivo),
            'descarga'  => $descargaDir !== null
                ? carpeta_url_descarga($descargaDir, $rutaRelativa, $entrada)
                : null,
        ];
    }

    usort($carpetas, function ($a, $b) {
        return strnatcasecmp($a['nombre'], $b['nombre']);
    });

    usort($archivos, function ($a, $b) {
        return $b['fecha'] <=> $a['fecha'];
    });

    return ['carpetas' => $carpetas, 'archivos' => $archivos];
}

/**
 * Formatea un tamaño en bytes a B/KB/MB/GB con formato español.
 */
function carpetas_formato_bytes($bytes)
{
    $bytes   = (float)$bytes;
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $unidades[$i];
}

/**
 * Agrupa los archivos por extensión y devuelve solo las que tienen al menos un archivo.
 */
function carpetas_extensiones($archivos)
{
    $conteo = array();
    foreach ($archivos as $archivo) {
        $ext = $archivo['extension'] !== '' ? strtoupper($archivo['extension']) : 'SIN EXT';
        if (!isset($conteo[$ext])) {
            $conteo[$ext] = 0;
        }
        $conteo[$ext]++;
    }
    ksort($conteo);
    return $conteo;
}

$listado      = $carpetaLista ? carpetas_listar($rutaActual, $info['descargaDir'] ?? null, $rutaRelativa)
                              : ['carpetas' => [], 'archivos' => []];
$carpetasList = $listado['carpetas'];
$archivos     = $listado['archivos'];
$totalBytes   = array_sum(array_column($archivos, 'bytes'));
$totalArchivos = count($archivos);
$totalCarpetas = count($carpetasList);
$extensiones   = carpetas_extensiones($archivos);

// Miga de pan: raiz + cada nivel de la ruta relativa (clicables).
$migas = [];
if ($rutaRelativa !== '') {
    $acumulado = '';
    foreach (explode('/', $rutaRelativa) as $nivel) {
        $acumulado = $acumulado === '' ? $nivel : $acumulado . '/' . $nivel;
        $migas[] = ['nombre' => $nivel, 'ruta' => $acumulado];
    }
}

$pageTitle = $info['titulo'];
$rutaMostrada = $rutaActual !== false ? str_replace('\\', '/', $rutaActual) : '';

// ==========================================
// USUARIO, ROL Y PERMISOS
// ==========================================
$rolActual   = permiso_rol_codigo();
$permisosRol = carpetas_sistema_permisos_rol($rolActual);
$usuarioActual = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
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

    <link rel="stylesheet" href="CSS/carpetas.css?v=<?php echo @filemtime(__DIR__ . '/CSS/carpetas.css') ?: time(); ?>">
</head>
<body data-carpeta="<?php echo htmlspecialchars($solicitada); ?>">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<main class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-folder-open me-2"></i><?php echo htmlspecialchars($info['titulo']); ?></h1>
                <p><?php echo htmlspecialchars($info['descripcion']); ?></p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Barra de contexto: volver + selector de carpeta (dropdown) + acciones -->
    <div class="carpeta-toolbar fade-in-up">
        <div class="carpeta-selector">
            <a href="../dashboard.php" class="btn-win btn-win-outline btn-win-sm" title="Volver al Dashboard" data-tooltip="Volver al Dashboard" data-tooltip-theme="primary">
                <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
            </a>

            <div class="dropdown carpeta-dropdown">
                <button type="button" class="btn carpeta-actual" id="btnCarpetaActual"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        aria-expanded="false" aria-haspopup="true"
                        title="Cambiar de carpeta del sistema">
                    <i class="fas <?php echo htmlspecialchars($info['icono']); ?> carpeta-actual-icono"></i>
                    <span class="carpeta-actual-texto"><?php echo htmlspecialchars($info['titulo']); ?></span>
                    <i class="fas fa-chevron-down carpeta-actual-flecha"></i>
                </button>
                <ul class="dropdown-menu carpeta-menu" aria-labelledby="btnCarpetaActual">
                    <li class="dropdown-header carpeta-menu-titulo">Carpetas del sistema</li>
                    <?php foreach ($CARPETAS as $clave => $item): ?>
                    <li>
                        <a class="dropdown-item <?php echo $clave === $solicitada ? 'active' : ''; ?>"
                           href="<?php echo $clave === $solicitada ? '#' : '?carpeta=' . urlencode($clave); ?>"
                           <?php echo $clave === $solicitada ? 'aria-current="page"' : ''; ?>>
                            <i class="fas <?php echo htmlspecialchars($item['icono']); ?> carpeta-menu-icono"></i>
                            <span class="carpeta-menu-texto">
                                <span class="carpeta-menu-nombre"><?php echo htmlspecialchars($item['titulo']); ?></span>
                                <small class="carpeta-menu-desc"><?php echo htmlspecialchars($item['descripcion']); ?></small>
                            </span>
                            <?php if ($clave === $solicitada): ?>
                            <i class="fas fa-check carpeta-menu-check"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php
        $puedeVer    = permiso_puede('dashboard', 'ver');
        $puedeAbrir  = carpetas_sistema_puede('abrir');
        $puedeVaciar = carpetas_sistema_puede('vaciar');
        $hayContenido = $carpetaLista && ($totalArchivos > 0 || $totalCarpetas > 0);
        $rutaPadre    = $rutaRelativa === '' ? '' : (dirname($rutaRelativa) === '.' ? '' : str_replace('\\', '/', dirname($rutaRelativa)));
        ?>
        <div class="carpeta-toolbar-acciones">
            <?php if ($puedeVer): ?>
            <button type="button" class="btn btn-win btn-win-outline btn-actualizar-carpeta" id="btnActualizarCarpeta"
                    data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                    title="Actualizar el listado de la carpeta"
                    data-tooltip="Actualizar el listado de la carpeta" data-tooltip-theme="primary">
                <i class="fas fa-rotate-right me-2"></i>Actualizar
            </button>
            <?php endif; ?>

            <button type="button" class="btn btn-win btn-win-primary btn-abrir-carpeta<?php echo $puedeAbrir ? '' : ' btn-deshabilitado'; ?>" id="btnAbrirCarpeta"
                    data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                    data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                    <?php echo ($carpetaLista && $puedeAbrir) ? '' : 'disabled'; ?>
                    title="<?php
                        if (!$puedeAbrir) {
                            echo 'Su rol no tiene permiso para abrir carpetas';
                        } else {
                            echo 'Abrir en el explorador de archivos: ' .
                                 htmlspecialchars($info['titulo']) .
                                 ($rutaRelativa !== '' ? ' / ' . htmlspecialchars($rutaRelativa) : '');
                        }
                    ?>">
                <i class="fas <?php echo $puedeAbrir ? 'fa-folder-open' : 'fa-lock'; ?> me-2"></i>Abrir en Explorador
            </button>

            <?php if ($puedeVaciar): ?>
            <button type="button" class="btn btn-win btn-vaciar-carpeta" id="btnVaciarCarpeta"
                    data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                    data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                    data-titulo="<?php echo htmlspecialchars($info['titulo']); ?>"
                    data-total="<?php echo (int)$totalArchivos; ?>"
                    data-carpetas="<?php echo (int)$totalCarpetas; ?>"
                    data-tamano="<?php echo htmlspecialchars(carpetas_formato_bytes($totalBytes)); ?>"
                    <?php echo ($hayContenido && $carpetaLista) ? '' : 'disabled'; ?>
                    title="<?php
                        if (!$hayContenido) {
                            echo 'No hay archivos que eliminar en esta carpeta';
                        } else {
                            echo 'Eliminar todo el contenido de esta carpeta' .
                                 ($rutaRelativa !== '' ? ' (' . str_replace('/', ' / ', $rutaRelativa) . ')' : '');
                        }
                    ?>">
                <i class="fas fa-trash-can me-2"></i>Vaciar Carpeta
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$carpetaLista): ?>
    <div class="carpeta-alerta">
        <i class="fas fa-triangle-exclamation"></i>
        <span>La carpeta no existe o no está disponible en este equipo.</span>
    </div>
    <?php else: ?>

    <!-- Resumen -->
    <div class="row g-2 mb-3">
        <div class="col-12 col-md-3">
            <div class="stat-card stat-card-sm">
                <div class="stat-icon"><i class="fas fa-location-dot"></i></div>
                <div class="stat-label">Ubicación</div>
                <code class="carpeta-ruta mt-1"><?php echo htmlspecialchars($rutaMostrada); ?></code>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-sm">
                <div class="stat-icon"><i class="fas fa-file-lines"></i></div>
                <div class="stat-value"><?php echo number_format($totalArchivos, 0, ',', '.'); ?></div>
                <div class="stat-label">Archivos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-sm">
                <div class="stat-icon"><i class="fas fa-database"></i></div>
                <div class="stat-value"><?php echo htmlspecialchars(carpetas_formato_bytes($totalBytes)); ?></div>
                <div class="stat-label">Tamaño total</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card stat-card-sm stat-card-rol">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-rol-line">
                    <span class="stat-rol-key">Usuario</span>
                    <span class="stat-rol-val"><?php echo htmlspecialchars($usuarioActual); ?></span>
                </div>
                <div class="stat-rol-line">
                    <span class="stat-rol-key">Rol</span>
                    <span class="stat-rol-val"><?php echo htmlspecialchars($rolActual); ?></span>
                </div>
                <div class="stat-rol-permisos">
                    <span class="stat-rol-key">Permisos</span>
                    <?php foreach ($permisosRol as $permiso): ?>
                    <span class="permiso-chip" title="<?php echo htmlspecialchars($permiso['etiqueta']); ?>"
                          data-permiso="<?php echo htmlspecialchars($permiso['clave']); ?>">
                        <i class="fas <?php echo htmlspecialchars($permiso['icono']); ?>"></i>
                        <span class="sr-only"><?php echo htmlspecialchars($permiso['etiqueta']); ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de archivos -->
    <div class="glass-card fade-in-up">
        <!-- Miga de pan: raiz + subcarpetas, todas clicables -->
        <nav class="carpeta-miga" aria-label="Ruta de la carpeta actual">
            <span class="carpeta-nav-btns" role="group" aria-label="Navegacion de carpetas">
                <button type="button" class="carpeta-nav-btn" id="btnCarpetaRaiz"
                        data-url="<?php echo htmlspecialchars(carpeta_url_navegacion($solicitada, '')); ?>"
                        <?php echo ($rutaEnRaiz || !$carpetaLista) ? 'disabled' : ''; ?>
                        title="Ir a la ra&iacute;z de <?php echo htmlspecialchars($info['titulo']); ?>"
                        aria-label="Ir a la raiz">
                    <i class="fas fa-house" aria-hidden="true"></i>
                </button>
                <button type="button" class="carpeta-nav-btn" id="btnCarpetaAtras"
                        <?php echo $carpetaLista ? '' : 'disabled'; ?>
                        title="Atr&aacute;s (carpeta anterior)" aria-label="Atras">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </button>
                <button type="button" class="carpeta-nav-btn" id="btnCarpetaSubir"
                        data-url="<?php echo htmlspecialchars(carpeta_url_navegacion($solicitada, $rutaPadre)); ?>"
                        <?php echo ($rutaEnRaiz || !$carpetaLista) ? 'disabled' : ''; ?>
                        title="Subir una carpeta" aria-label="Subir una carpeta">
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                </button>
                <span class="carpeta-nav-sep" aria-hidden="true">|</span>
                <button type="button" class="carpeta-nav-btn carpeta-restablecer-anchos"
                        id="btnRestablecerAnchos"
                        data-bs-toggle="tooltip" data-bs-title="Restablecer el ancho de las columnas"
                        title="Restablecer el ancho de las columnas"
                        aria-label="Restablecer el ancho de las columnas">
                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                </button>
            </span>
            <a class="carpeta-miga-nivel <?php echo $rutaEnRaiz ? 'activo' : ''; ?>"
               href="<?php echo htmlspecialchars(carpeta_url_navegacion($solicitada, '')); ?>"
               title="<?php echo htmlspecialchars($info['titulo']); ?>">
                <i class="fas fa-folder-open"></i>
                <span><?php echo htmlspecialchars($info['titulo']); ?></span>
            </a>
            <?php $acumulada = ''; foreach ($migas as $miga): ?>
                <?php $acumulada = $miga['ruta']; ?>
                <i class="fas fa-chevron-right carpeta-miga-sep" aria-hidden="true"></i>
                <a class="carpeta-miga-nivel <?php echo $acumulada === $rutaRelativa ? 'activo' : ''; ?>"
                   href="<?php echo htmlspecialchars(carpeta_url_navegacion($solicitada, $acumulada)); ?>"
                   title="<?php echo htmlspecialchars($miga['nombre']); ?>">
                    <i class="fas fa-folder"></i>
                    <span><?php echo htmlspecialchars($miga['nombre']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
            <div class="d-flex align-items-center gap-2">
                <span class="h6 mb-0"><i class="fas fa-list me-2"></i>Contenido de la carpeta: <span style="color: var(--accent); font-weight: 700;"><?php echo htmlspecialchars($info['titulo']); ?><?php echo $rutaRelativa !== '' ? ' &rsaquo; <span class="carpeta-subruta">' . htmlspecialchars($rutaRelativa) . '</span>' : ''; ?></span></span>
            </div>
            <div class="carpeta-filtros">
                <select id="filtroExtension" class="form-select" style="width:auto" aria-label="Filtrar por tipo de archivo">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($extensiones as $ext => $conteo): ?>
                    <option value="<?php echo htmlspecialchars(strtolower($ext)); ?>"><?php echo htmlspecialchars($ext); ?> (<?php echo $conteo; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="search" id="buscarArchivo" class="form-control"
                       placeholder="Buscar archivo..." style="width:12rem" aria-label="Buscar archivo">
                <label class="carpeta-pag-select" for="porPagina" title="Cantidad de archivos por p&aacute;gina">
                    <i class="fas fa-list-ol" aria-hidden="true"></i>
                    <select id="porPagina" class="form-select" aria-label="Cantidad de archivos por pagina">
                        <option value="10">10 archivos</option>
                        <option value="25">25 archivos</option>
                        <option value="50">50 archivos</option>
                        <option value="100">100 archivos</option>
                        <option value="0">Todos</option>
                    </select>
                </label>
            </div>
        </div>

        <?php if (empty($archivos) && empty($carpetasList)): ?>
        <div class="carpeta-vacio">
            <i class="fas fa-folder-open"></i>
            No hay archivos ni carpetas en esta carpeta.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabla-carpetas" id="tablaCarpetas">
                <colgroup>
                    <col data-col="0">
                    <col data-col="1">
                    <col data-col="2">
                    <col data-col="3">
                    <col data-col="4">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="0"><button type="button" class="th-sort" data-orden="nombre"><span>Archivo</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="1" class="text-end"><button type="button" class="th-sort" data-orden="tipo"><span>Tipo</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="2" class="text-end"><button type="button" class="th-sort" data-orden="bytes"><span>Tama&ntilde;o</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="3" class="text-end"><button type="button" class="th-sort" data-orden="fecha"><span>Fecha Modificado</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="4" class="text-end">Acci&oacute;n</th>
                    </tr>
                </thead>
                <tbody id="tbodyCarpetas">
                <?php foreach ($carpetasList as $c): ?>
                    <tr class="fila-carpeta"
                        data-es-carpeta="1"
                        data-extension="carpeta"
                        data-nombre="<?php echo htmlspecialchars($c['nombre']); ?>"
                        data-bytes="0"
                        data-fecha="<?php echo (int)$c['fecha']; ?>">
                        <td class="celda-archivo" title="<?php echo htmlspecialchars($c['nombre']); ?>">
                            <a class="carpeta-archivo carpeta-entrar-carpeta"
                               href="<?php echo htmlspecialchars(carpeta_url_navegacion($solicitada, $c['ruta'])); ?>"
                               title="Abrir la carpeta <?php echo htmlspecialchars($c['nombre']); ?>"
                               aria-label="Abrir la carpeta <?php echo htmlspecialchars($c['nombre']); ?>">
                                <i class="fas fa-folder carpeta-icono-archivo carpeta-icono-carpeta"></i>
                                <span class="carpeta-nombre"><?php echo htmlspecialchars($c['nombre']); ?></span>
                                <i class="fas fa-chevron-right carpeta-ir-carpeta" aria-hidden="true"></i>
                            </a>
                        </td>
                        <td class="text-end carpeta-sin-datos">&mdash;</td>
                        <td class="text-end carpeta-sin-datos">&mdash;</td>
                        <td class="text-end"><?php echo date('d/m/Y h:i:s a', $c['fecha']); ?></td>
                        <td class="text-end">
                            <span class="carpeta-acciones">
                                <?php if (carpetas_sistema_puede('eliminar')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger carpeta-accion-icono btn-eliminar-archivo"
                                        data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                                        data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                                        data-nombre="<?php echo htmlspecialchars($c['nombre']); ?>"
                                        data-tipo="carpeta"
                                        data-bs-toggle="tooltip" data-bs-title="Eliminar carpeta"
                                        aria-label="Eliminar la carpeta <?php echo htmlspecialchars($c['nombre']); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary carpeta-accion-icono" disabled
                                        data-bs-toggle="tooltip" data-bs-title="Eliminar (solo Administrador)"
                                        aria-label="Eliminar (solo Administrador)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php
                $ICONOS_EXT = [
                    'dbf' => 'fa-database', 'xlsx' => 'fa-file-excel', 'xls' => 'fa-file-excel',
                    'csv' => 'fa-file-csv', 'pdf' => 'fa-file-pdf',
                    /* Comprimidos */
                    'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', '7z' => 'fa-file-zipper',
                    /* Imagenes */
                    'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
                    'bmp' => 'fa-file-image', 'gif' => 'fa-file-image', 'webp' => 'fa-file-image',
                    'svg' => 'fa-file-image', 'ico' => 'fa-file-image', 'tif' => 'fa-file-image', 'tiff' => 'fa-file-image',
                    'doc' => 'fa-file-word', 'docx' => 'fa-file-word', 'txt' => 'fa-file-alt',
                    'sql' => 'fa-database',
                    'xml' => 'fa-file-code',
                    'log' => 'fa-file-lines', 'json' => 'fa-file-code', 'md' => 'fa-file-lines',
                    'ps1' => 'fa-terminal', 'cmd' => 'fa-terminal',
                ];
                foreach ($archivos as $a):
                    $icono = $ICONOS_EXT[$a['extension']] ?? 'fa-file';
                ?>
                    <tr data-extension="<?php echo htmlspecialchars($a['extension']); ?>"
                        data-nombre="<?php echo htmlspecialchars($a['nombre']); ?>"
                        data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                        data-bytes="<?php echo (int)$a['bytes']; ?>"
                        data-fecha="<?php echo (int)$a['fecha']; ?>">
                        <?php $previsualizable = in_array($a['extension'], ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd', 'zip', 'rar'], true); ?>
                        <td class="celda-archivo" title="<?php echo htmlspecialchars($a['nombre']); ?>">
                            <?php if ($previsualizable): ?>
                            <button type="button" class="carpeta-archivo carpeta-ver-archivo"
                                    data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                                    data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                                    data-nombre="<?php echo htmlspecialchars($a['nombre']); ?>"
                                    data-tipo="<?php echo htmlspecialchars($a['extension']); ?>"
                                    title="Ver el contenido de <?php echo htmlspecialchars($a['nombre']); ?>"
                                    aria-label="Ver el contenido de <?php echo htmlspecialchars($a['nombre']); ?>">
                                <i class="fas <?php echo $icono; ?> carpeta-icono-archivo"></i><span class="carpeta-nombre"><?php echo htmlspecialchars($a['nombre']); ?></span>
                            </button>
                            <?php elseif ($a['descarga'] !== null): ?>
                            <button type="button" class="carpeta-archivo carpeta-archivo-no-soportado"
                                    data-nombre="<?php echo htmlspecialchars($a['nombre']); ?>"
                                    data-tipo="<?php echo htmlspecialchars($a['extension']); ?>"
                                    title="Descargar <?php echo htmlspecialchars($a['nombre']); ?>"
                                    aria-label="Descargar <?php echo htmlspecialchars($a['nombre']); ?>">
                                <i class="fas <?php echo $icono; ?> carpeta-icono-archivo"></i><span class="carpeta-nombre"><?php echo htmlspecialchars($a['nombre']); ?></span>
                            </button>
                            <?php else: ?>
                            <span class="carpeta-archivo">
                                <i class="fas <?php echo $icono; ?> carpeta-icono-archivo"></i><span class="carpeta-nombre"><?php echo htmlspecialchars($a['nombre']); ?></span>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end carpeta-tipo"><?php echo htmlspecialchars($a['extension']); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(carpetas_formato_bytes($a['bytes'])); ?></td>
                        <td class="text-end"><?php echo date('d/m/Y h:i:s a', $a['fecha']); ?></td>
                        <td class="text-end">
                            <span class="carpeta-acciones">
                                <?php if ($a['descarga'] !== null): ?>
                                <a class="btn btn-sm btn-win carpeta-accion-icono" href="<?php echo htmlspecialchars($a['descarga']); ?>"
                                   download data-bs-toggle="tooltip" data-bs-title="Descargar"
                                   aria-label="Descargar <?php echo htmlspecialchars($a['nombre']); ?>">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (carpetas_sistema_puede('eliminar')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger carpeta-accion-icono btn-eliminar-archivo"
                                        data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                                        data-ruta="<?php echo htmlspecialchars($rutaRelativa); ?>"
                                        data-nombre="<?php echo htmlspecialchars($a['nombre']); ?>"
                                        data-bs-toggle="tooltip" data-bs-title="Eliminar"
                                        aria-label="Eliminar <?php echo htmlspecialchars($a['nombre']); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary carpeta-accion-icono" disabled
                                        data-bs-toggle="tooltip" data-bs-title="Eliminar (solo Administrador)"
                                        aria-label="Eliminar (solo Administrador)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="sinResultados" class="carpeta-vacio" style="display:none;">
            <i class="fas fa-filter-circle-xmark"></i>
            Ningún archivo coincide con el filtro aplicado.
        </div>
        <div class="carpeta-paginador" id="carpetaPaginador">
            <span class="carpeta-pag-info" id="paginaInfo" aria-live="polite">Mostrando 0 de 0 archivos</span>
            <nav class="carpeta-pag-botones" aria-label="Paginaci&oacute;n del listado">
                <button type="button" class="carpeta-pag-btn" data-pag="primera"
                        title="Primera p&aacute;gina" aria-label="Primera pagina">
                    <i class="fas fa-angles-left" aria-hidden="true"></i>
                </button>
                <button type="button" class="carpeta-pag-btn" data-pag="anterior"
                        title="P&aacute;gina anterior" aria-label="Pagina anterior">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <span class="carpeta-pag-num" id="paginaActual">P&aacute;gina 1 de 1</span>
                <button type="button" class="carpeta-pag-btn" data-pag="siguiente"
                        title="P&aacute;gina siguiente" aria-label="Pagina siguiente">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
                <button type="button" class="carpeta-pag-btn" data-pag="ultima"
                        title="Última p&aacute;gina" aria-label="Ultima pagina">
                    <i class="fas fa-angles-right" aria-hidden="true"></i>
                </button>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>

</main>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/carpetas.js?v=<?php echo @filemtime(__DIR__ . '/../js/carpetas.js') ?: time(); ?>"></script>
</body>
</html>