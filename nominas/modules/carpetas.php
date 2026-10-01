<?php
// modules/carpetas.php - Explorador de carpetas del sistema (Exportaciones / Descargas)
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
    permiso_denegar_acceso('Carpetas de Exportaciones y Descargas');
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
$carpetaLista = $rutaReal !== false && is_dir($rutaReal);

// ==========================================
// LISTADO DE ARCHIVOS
// ==========================================
/**
 * Devuelve los archivos de una carpeta, ordenados por fecha descendente.
 * Si $permitirDescarga es true, agrega la ruta relativa de descarga.
 */
function carpetas_listar($ruta, $permitirDescarga)
{
    $archivos = array();
    $entradas = @scandir($ruta);
    if (!is_array($entradas)) {
        return $archivos;
    }

    foreach ($entradas as $entrada) {
        if ($entrada === '.' || $entrada === '..') {
            continue;
        }
        $rutaArchivo = $ruta . DIRECTORY_SEPARATOR . $entrada;
        if (!is_file($rutaArchivo)) {
            continue;
        }
        $extension = strtolower(pathinfo($entrada, PATHINFO_EXTENSION));
        $archivos[] = [
            'nombre'    => $entrada,
            'extension' => $extension,
            'bytes'     => (int)@filesize($rutaArchivo),
            'fecha'     => (int)@filemtime($rutaArchivo),
            'descarga'  => $permitirDescarga ? 'exports/' . rawurlencode($entrada) : null,
        ];
    }

    usort($archivos, function ($a, $b) {
        return $b['fecha'] <=> $a['fecha'];
    });

    return $archivos;
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

$archivos      = $carpetaLista ? carpetas_listar($rutaReal, (bool)$info['descargable']) : [];
$totalBytes    = array_sum(array_column($archivos, 'bytes'));
$totalArchivos = count($archivos);
$extensiones   = carpetas_extensiones($archivos);

$pageTitle = $info['titulo'];
$rutaMostrada = $rutaReal !== false ? str_replace('\\', '/', $rutaReal) : '';

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

    <!-- Barra de contexto: volver + selector de carpeta + acción de apertura -->
    <div class="carpeta-toolbar fade-in-up">
        <div class="carpeta-selector">
            <a href="../dashboard.php" class="btn-win btn-win-outline btn-win-sm" title="Volver al Dashboard" data-tooltip="Volver al Dashboard" data-tooltip-theme="primary">
                <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
            </a>
            <?php foreach ($CARPETAS as $clave => $item): ?>
            <a class="btn <?php echo $clave === $solicitada ? 'btn-activa' : 'btn-inactiva'; ?>"
               href="<?php echo $clave === $solicitada ? '#' : '?carpeta=' . urlencode($clave); ?>"
               <?php echo $clave === $solicitada ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo htmlspecialchars($item['icono']); ?>"></i><?php echo htmlspecialchars($item['titulo']); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php $puedeAbrir = carpetas_sistema_puede('abrir'); ?>
        <button type="button" class="btn btn-win btn-win-primary btn-abrir-carpeta<?php echo $puedeAbrir ? '' : ' btn-deshabilitado'; ?>" id="btnAbrirCarpeta"
                data-carpeta="<?php echo htmlspecialchars($solicitada); ?>"
                <?php echo ($carpetaLista && $puedeAbrir) ? '' : 'disabled'; ?>
                title="<?php echo $puedeAbrir ? 'Abrir la carpeta en el explorador de archivos' : 'Su rol no tiene permiso para abrir carpetas'; ?>">
            <i class="fas <?php echo $puedeAbrir ? 'fa-folder-open' : 'fa-lock'; ?> me-2"></i>Abrir Carpeta
        </button>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
            <div class="d-flex align-items-center gap-2">
                <span class="h6 mb-0"><i class="fas fa-list me-2"></i>Contenido de la carpeta</span>
                <button type="button" class="btn btn-sm btn-outline-secondary carpeta-restablecer-anchos"
                        id="btnRestablecerAnchos"
                        data-bs-toggle="tooltip" data-bs-title="Restablecer el ancho de las columnas"
                        aria-label="Restablecer el ancho de las columnas">
                    <i class="fas fa-rotate-left"></i>
                </button>
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
            </div>
        </div>

        <?php if (empty($archivos)): ?>
        <div class="carpeta-vacio">
            <i class="fas fa-folder-open"></i>
            No hay archivos en esta carpeta.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabla-carpetas" id="tablaCarpetas">
                <colgroup>
                    <col data-col="0">
                    <col data-col="1">
                    <col data-col="2">
                    <col data-col="3">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="0"><button type="button" class="th-sort" data-orden="nombre"><span>Archivo</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="1" class="text-end"><button type="button" class="th-sort" data-orden="bytes"><span>Tamaño</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="2" class="text-end"><button type="button" class="th-sort" data-orden="fecha"><span>Modificado</span><i class="fas fa-sort th-sort-icono"></i></button><span class="th-grip" title="Arrastrar para ajustar el ancho; doble clic para ajustar al contenido"></span></th>
                        <th data-col="3" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody id="tbodyCarpetas">
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
                ];
                foreach ($archivos as $a):
                    $icono = $ICONOS_EXT[$a['extension']] ?? 'fa-file';
                ?>
                    <tr data-extension="<?php echo htmlspecialchars($a['extension']); ?>"
                        data-nombre="<?php echo htmlspecialchars($a['nombre']); ?>"
                        data-bytes="<?php echo (int)$a['bytes']; ?>"
                        data-fecha="<?php echo (int)$a['fecha']; ?>">
                        <td class="celda-archivo" title="<?php echo htmlspecialchars($a['nombre']); ?>">
                            <span class="carpeta-archivo">
                                <i class="fas <?php echo $icono; ?> carpeta-icono-archivo"></i><span class="carpeta-nombre"><?php echo htmlspecialchars($a['nombre']); ?></span>
                            </span>
                        </td>
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