<?php
// modules/eliminar_archivo.php - Elimina un archivo o una carpeta (con todo
// su contenido) de las carpetas del sistema.
//
// Restringido al rol Administrador (rol_id = 1). Los demas roles reciben 403.
// La carpeta debe ser una de las permitidas y el archivo debe existir dentro de ella.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// --- Sesion ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

// --- Permisos: acceso a carpetas + rol Administrador ---
if (!carpetas_sistema_permitido()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
    exit();
}

if (permiso_rol_codigo() !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'Solo el Administrador puede eliminar archivos.']);
    exit();
}

// --- Metodo y parametros ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'mensaje' => 'Metodo no permitido.']);
    exit();
}

$resuelta = carpetas_sistema_resolver($_POST['carpeta'] ?? '');
if ($resuelta === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Carpeta no permitida o inexistente.']);
    exit();
}
list($carpetaSolicitada, $rutaCarpeta) = $resuelta;

// Subcarpeta relativa dentro de la carpeta del sistema (extracciones de ZIP).
$rutaRelativa = carpetas_ruta_relativa((string)($_POST['ruta'] ?? ''));
if ($rutaRelativa === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta de subcarpeta no valida.']);
    exit();
}

$rutaBase = carpetas_ruta_resolver($rutaCarpeta, $rutaRelativa);
if ($rutaBase === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'La subcarpeta no existe.']);
    exit();
}

// ==========================================
// Lote: eliminar varios elementos (archivos y carpetas) en una sola
// peticion (seleccion multiple del listado). El tipo se deduce del sistema
// de ficheros (is_dir); cada nombre se valida igual que en la eliminacion
// individual: basename + realpath dentro de $rutaBase.
// ==========================================
if (isset($_POST['archivos']) && is_array($_POST['archivos'])) {
    $nombres = [];
    foreach ($_POST['archivos'] as $bruto) {
        $n = basename(trim((string)$bruto));
        if ($n !== '' && $n !== '.' && $n !== '..') {
            $nombres[$n] = true;
        }
    }
    $nombres = array_keys($nombres);

    if (!$nombres) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'Ningun elemento valido para eliminar.']);
        exit();
    }
    if (count($nombres) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'Demasiados elementos en una sola peticion (maximo 1000).']);
        exit();
    }

    $prefijo          = rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR;
    $eliminados       = [];
    $borradosArchivos = [];
    $borradosCarpetas = [];
    $fallidos         = [];

    foreach ($nombres as $nombre) {
        $rutaReal = realpath($rutaBase . DIRECTORY_SEPARATOR . $nombre);
        if ($rutaReal === false || (!is_file($rutaReal) && !is_dir($rutaReal))) {
            $fallidos[] = ['archivo' => $nombre, 'mensaje' => 'No existe en esa carpeta.'];
            continue;
        }
        if (strpos($rutaReal, $prefijo) !== 0) {
            $fallidos[] = ['archivo' => $nombre, 'mensaje' => 'Ruta no permitida.'];
            continue;
        }

        if (is_dir($rutaReal)) {
            $error = carpetas_eliminar_arbol($rutaReal, $rutaBase);
            if ($error !== null) {
                $fallidos[] = ['archivo' => $nombre, 'mensaje' => $error];
                continue;
            }
            $borradosCarpetas[] = $nombre;
        } else {
            if (!carpetas_borrar_fichero($rutaReal)) {
                $fallidos[] = ['archivo' => $nombre, 'mensaje' => 'No se pudo eliminar.'];
                continue;
            }
            $borradosArchivos[] = $nombre;
        }
        $eliminados[] = $nombre;
    }

    clearstatcache();

    if ($eliminados) {
        logAction('dashboard', 'eliminar_archivo',
            'Eliminacion en lote de ' . count($eliminados) . ' elemento(s) desde el explorador de carpetas',
            [
                'carpeta'  => $carpetaSolicitada,
                'subruta'  => $rutaRelativa,
                'total'    => count($nombres),
                'archivos' => array_slice($borradosArchivos, 0, 50),
                'carpetas' => array_slice($borradosCarpetas, 0, 50),
            ],
            null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    }

    $ok = empty($fallidos);
    $partes = [];
    if ($borradosArchivos) {
        $partes[] = count($borradosArchivos) . ' archivo(s)';
    }
    if ($borradosCarpetas) {
        $partes[] = count($borradosCarpetas) . ' carpeta(s)';
    }
    $resumen = $partes ? implode(' y ', $partes) : '0 elemento(s)';
    $mensaje = $ok
        ? 'Se eliminaron ' . $resumen . '.'
        : 'Se eliminaron ' . $resumen . ' de ' . count($nombres) . ' elemento(s); ' .
          count($fallidos) . ' no se pudieron eliminar.';

    echo json_encode([
        'success'    => $ok,
        'mensaje'    => $mensaje,
        'total'      => count($nombres),
        'eliminados' => $eliminados,
        'archivos'   => $borradosArchivos,
        'carpetas'   => $borradosCarpetas,
        'fallidos'   => $fallidos,
        'carpeta'    => $carpetaSolicitada,
        'ruta'       => $rutaRelativa,
    ]);
    exit();
}

// ==========================================
// Individual: un solo archivo (o carpeta) por peticion
// ==========================================
$archivo = basename(trim((string)($_POST['archivo'] ?? '')));
if ($archivo === '' || $archivo === '.' || $archivo === '..') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Nombre de archivo no valido.']);
    exit();
}

$tipo      = (string)($_POST['tipo'] ?? 'archivo');
$esCarpeta = ($tipo === 'carpeta');

$rutaArchivo = $rutaBase . DIRECTORY_SEPARATOR . $archivo;
$rutaReal    = realpath($rutaArchivo);

// ==========================================
// Carpeta: se borra con todo su contenido (nunca la raiz, nunca fuera de ella)
// ==========================================
if ($esCarpeta) {
    if ($rutaReal === false || !is_dir($rutaReal)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'mensaje' => 'La carpeta no existe en esa ubicacion.']);
        exit();
    }

    $error = carpetas_eliminar_arbol($rutaReal, $rutaBase);
    if ($error !== null) {
        http_response_code(500);
        echo json_encode(['success' => false,
            'mensaje' => 'No se pudo eliminar la carpeta "' . $archivo . '": ' . $error]);
        exit();
    }

    clearstatcache();

    logAction('dashboard', 'eliminar_archivo',
        'Eliminacion de carpeta desde el explorador de carpetas',
        [
            'carpeta'  => $carpetaSolicitada,
            'subruta'  => $rutaRelativa,
            'archivo'  => $archivo,
            'ruta'     => str_replace('\\', '/', $rutaReal),
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success' => true,
        'mensaje' => 'Carpeta eliminada correctamente.',
        'carpeta' => $carpetaSolicitada,
        'nombre'  => $archivo,
        'tipo'    => 'carpeta',
    ]);
    exit();
}

// El archivo debe existir, ser un archivo regular y estar dentro de la carpeta permitida.
if ($rutaReal === false || !is_file($rutaReal)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe en esa carpeta.']);
    exit();
}

if (strpos($rutaReal, rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta de archivo no permitida.']);
    exit();
}

// --- Eliminar ---
$tamano = (int)@filesize($rutaReal);

if (!carpetas_borrar_fichero($rutaReal)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo eliminar el archivo.']);
    exit();
}

clearstatcache();

logAction('dashboard', 'eliminar_archivo',
    'Eliminacion de archivo desde el explorador de carpetas',
    [
        'carpeta'  => $carpetaSolicitada,
        'subruta'  => $rutaRelativa,
        'archivo'  => $archivo,
        'tamano'   => $tamano,
        'ruta'     => str_replace('\\', '/', $rutaReal),
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success' => true,
    'mensaje' => 'Archivo eliminado correctamente.',
    'carpeta' => $carpetaSolicitada,
    'nombre'  => $archivo,
    'tipo'    => 'archivo',
]);