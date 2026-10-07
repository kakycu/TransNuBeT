<?php
// modules/vaciar_carpeta.php - Vacia por completo una carpeta del sistema
//
// Borra TODO el contenido (archivos, carpetas y subcarpetas) de la carpeta que
// se esta viendo: la raiz (Exportaciones, Descargas, Salvas) o cualquier
// subcarpeta abierta (parametro "ruta"), sin tocar la carpeta en si.
// Restringido a los roles 1 Admin, 4 Super y 5 Soft: la validacion reutiliza
// carpetas_sistema_puede('vaciar'), la misma lista que decide si carpetas.php
// pinta el boton.
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

// --- Permisos ---
if (!carpetas_sistema_permitido()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
    exit();
}

if (!carpetas_sistema_puede('vaciar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'Solo los roles Administrador, Supervisor General y Programador pueden vaciar carpetas.']);
    exit();
}

// --- Metodo y carpeta ---
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

// --- Subcarpeta abierta (vaciar la carpeta que se esta viendo, igual que la raiz) ---
$rutaRelativa = carpetas_ruta_relativa($_POST['ruta'] ?? '');
if ($rutaRelativa === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta invalida.']);
    exit();
}

if ($rutaRelativa === '') {
    $rutaObjetivo = $rutaCarpeta;
} else {
    $rutaObjetivo = carpetas_ruta_resolver($rutaCarpeta, $rutaRelativa);
    if ($rutaObjetivo === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'La carpeta indicada no existe o esta fuera de la carpeta de sistema.']);
        exit();
    }
}

/**
 * Borra recursivamente todo el contenido de $ruta.
 *
 * Todo lo que no quede dentro de $raiz (enlaces raros, rutas resueltas fuera)
 * se salta y se reporta como fallo, para no borrar nada fuera de la carpeta.
 *
 * @return array{archivos:int, directorios:int, bytes:int, fallos:string[]}
 */
function vaciar_carpeta_contenido($ruta, $raiz)
{
    $est = ['archivos' => 0, 'directorios' => 0, 'bytes' => 0, 'fallos' => []];

    $entradas = @scandir($ruta);
    if (!is_array($entradas)) {
        $est['fallos'][] = basename($ruta);
        return $est;
    }

    foreach ($entradas as $entrada) {
        if ($entrada === '.' || $entrada === '..') {
            continue;
        }

        $real = realpath($ruta . DIRECTORY_SEPARATOR . $entrada);
        if ($real === false) {
            $est['fallos'][] = $entrada;
            continue;
        }
        if (strpos($real, $raiz . DIRECTORY_SEPARATOR) !== 0) {
            $est['fallos'][] = $entrada;
            continue;
        }

        if (is_dir($real)) {
            $hijos = vaciar_carpeta_contenido($real, $raiz);
            $est['archivos']    += $hijos['archivos'];
            $est['directorios'] += $hijos['directorios'] + 1;
            $est['bytes']       += $hijos['bytes'];
            $est['fallos']       = array_merge($est['fallos'], $hijos['fallos']);

            if (!@rmdir($real)) {
                $est['fallos'][] = $entrada;
                $est['directorios']--;
            }
            continue;
        }

        $est['bytes'] += (int)@filesize($real);
        if (@unlink($real)) {
            $est['archivos']++;
        } else {
            $est['fallos'][] = $entrada;
        }
    }

    return $est;
}

$estadistica = vaciar_carpeta_contenido($rutaObjetivo, $rutaCarpeta);

clearstatcache();

// Carpeta vacia o ya sin contenido: no es un error, pero tampoco hay nada que reportar.
$totalBorrado = $estadistica['archivos'] + $estadistica['directorios'];

if ($totalBorrado === 0 && !empty($estadistica['fallos'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'mensaje' => 'No se pudo vaciar la carpeta. Quedaron sin eliminar: ' .
                     implode(', ', array_slice($estadistica['fallos'], 0, 5)) . '.',
    ]);
    exit();
}

logAction('dashboard', 'vaciar_carpeta',
    'Vaciado completo de una carpeta del sistema',
    [
        'carpeta'      => $carpetaSolicitada,
        'ruta'         => str_replace('\\', '/', $rutaObjetivo),
        'subcarpeta'   => $rutaRelativa,
        'archivos'     => $estadistica['archivos'],
        'directorios'  => $estadistica['directorios'],
        'bytes'        => $estadistica['bytes'],
        'sin_eliminar' => $estadistica['fallos'],
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success'     => true,
    'mensaje'     => 'Carpeta vaciada correctamente.',
    'carpeta'     => $carpetaSolicitada,
    'ruta'        => $rutaRelativa,
    'archivos'    => $estadistica['archivos'],
    'directorios' => $estadistica['directorios'],
    'bytes'       => $estadistica['bytes'],
    'fallos'      => $estadistica['fallos'],
]);
