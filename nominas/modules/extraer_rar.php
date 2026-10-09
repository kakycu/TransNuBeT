<?php
// modules/extraer_rar.php - Lee un .rar de las carpetas del sistema
//
// Complemento RAR de extraer_zip.php con las mismas tres operaciones (todas
// exigen token CSRF de sesion):
//   1. GET  accion=ver_entrada &entrada=N  -> descarga binaria de esa entrada.
//   2. POST accion=extraer_todo            -> descomprime todo el RAR.
//   3. POST accion=extraer_uno   &entrada=N-> descomprime una sola entrada.
// En las dos ultimas, "destino" es la clave de la carpeta del sistema donde
// dejar los archivos (por defecto, la que contiene el RAR).
//
// El indice se lee con includes/rarlib (selective/rar); la descompresion se
// delega a UnRAR.exe con proc_open y array de argumentos (sin shell). El
// binario extrae SIEMPRE a una carpeta temporal y de ahi se mueve a destino
// con las mismas reglas que el ZIP: nunca se sale de la raiz permitida
// (proteccion zip-slip via carpetas_nombre_seguro) y nunca se pisa un
// archivo existente (sufijo " (2)").
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/visor_anidar.php';
require_once __DIR__ . '/../includes/rarlib/autoload.php';
require_once __DIR__ . '/../includes/rarlib/unrar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limites de extraccion: una salva no debe poder inundar el disco.
define('RAR_EXTRACCION_MAX_BYTES', 1024 * 1024 * 1024);   // 1 GB descomprimido
define('RAR_EXTRACCION_MAX_DESCARGA', 1024 * 1024 * 1024); // 1 GB por entrada descargada

/**
 * Escribe la respuesta JSON y termina.
 * Si la extraccion va con progreso (NDJSON), la respuesta viaja como ultima
 * linea "fin" del streaming en vez de un cuerpo JSON suelto.
 */
function extraer_json($codigo, $datos)
{
    if (!empty($GLOBALS['extraer_con_progreso'])) {
        echo json_encode(array_merge(['tipo' => 'fin'], $datos)) . "\n";
        @flush();
        exit();
    }
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit();
}

/**
 * Arranca la salida en streaming (NDJSON) para la barra de progreso.
 */
function extraer_progreso_iniciar()
{
    $GLOBALS['extraer_con_progreso'] = true;
    @ignore_user_abort(true);
    @ini_set('output_buffering', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');
    echo json_encode(['tipo' => 'inicio']) . "\n";
    @flush();
}

/**
 * Emite una linea de progreso (JavaScript la lee con fetch + reader).
 */
function extraer_progreso_emitir(array $datos)
{
    if (empty($GLOBALS['extraer_con_progreso'])) {
        return;
    }
    echo json_encode(array_merge(['tipo' => 'progreso'], $datos)) . "\n";
    @flush();
    if (connection_status() !== CONNECTION_NORMAL) {
        // El cliente cancelo la extraccion (X/Esc): se sale de inmediato y
        // los shutdown functions matan a UnRAR y borran el temporal.
        exit();
    }
}

/**
 * Estado del arbol temporal durante la extraccion: bytes reales extraidos,
 * ficheros empezados (para "Archivo N de M"), relativa y bytes escritos del
 * fichero mas recientemente escrito (UnRAR va escribiendo en orden, asi que
 * el ultimo en tocarse es el que esta extrayendose ahora: su porcentaje
 * alimenta la primera barra, que se vacia con cada fichero nuevo).
 */
function extraer_arbol_estado($ruta)
{
    $estado = ['bytes' => 0, 'actual' => '', 'archivos' => 0, 'actualBytes' => 0];
    if (!is_dir($ruta)) {
        return $estado;
    }

    $base        = rtrim($ruta, "/\\") . DIRECTORY_SEPARATOR;
    $masReciente = -1;
    $iterator    = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $estado['bytes']   += (int)$item->getSize();
        $estado['archivos']++;

        $mtime = (int)$item->getMTime();
        if ($mtime >= $masReciente) {
            $masReciente = $mtime;
            $estado['actual']      = str_replace('\\', '/', substr($item->getPathname(), strlen($base)));
            $estado['actualBytes'] = (int)$item->getSize();
        }
    }
    return $estado;
}

/**
 * Ruta de destino libre: si ya existe un archivo con ese nombre se le
 * anade " (2)", " (3)"... para no pisar una salva previa.
 */
function extraer_destino_libre($destino)
{
    if (!file_exists($destino)) {
        return $destino;
    }

    $directorio = dirname($destino);
    $base       = basename($destino);
    $extension  = pathinfo($base, PATHINFO_EXTENSION);
    $nombre     = $extension !== '' ? substr($base, 0, -(strlen($extension) + 1)) : $base;

    for ($n = 2; $n <= 99; $n++) {
        $candidato = $directorio . DIRECTORY_SEPARATOR .
            $nombre . ' (' . $n . ')' . ($extension !== '' ? '.' . $extension : '');
        if (!file_exists($candidato)) {
            return $candidato;
        }
    }

    return $directorio . DIRECTORY_SEPARATOR . $nombre . ' (' . uniqid() . ')' .
        ($extension !== '' ? '.' . $extension : '');
}

/**
 * Crea el directorio (y sus padres) si no existe.
 */
function extraer_crear_dir($ruta)
{
    if (is_dir($ruta)) {
        return true;
    }
    return @mkdir($ruta, 0775, true) || is_dir($ruta);
}

/**
 * Borra un arbol temporal de extraccion.
 */
function extraer_borrar_arbol($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $elementos = scandir($dir);
    if ($elementos === false) {
        return;
    }
    foreach ($elementos as $elemento) {
        if ($elemento === '.' || $elemento === '..') {
            continue;
        }
        $ruta = $dir . DIRECTORY_SEPARATOR . $elemento;
        if (is_dir($ruta)) {
            extraer_borrar_arbol($ruta);
        } else {
            @unlink($ruta);
        }
    }
    @rmdir($dir);
}

/**
 * Mueve el arbol extraido en $tmpDir dentro de $rutaDestino aplicando las
 * reglas de seguridad: carpetas_nombre_seguro por fichero, ninguna ruta fuera
 * de la carpeta permitida y sufijo " (2)" en vez de sobrescribir.
 *
 * @param array|null $soloRelativa Si se indica, solo se mueve esa ruta relativa.
 * @return array{archivos: int, carpetas: int, bytes: int, omitidos: array<string, string>}
 */
function extraer_mover_arbol($tmpDir, $rutaDestino, $soloRelativa = null)
{
    $prefijoPermitido = rtrim($rutaDestino, "/\\") . DIRECTORY_SEPARATOR;
    $destino          = ['archivos' => 0, 'carpetas' => 0, 'bytes' => 0, 'omitidos' => []];
    $pesoAcumulado    = 0;
    $carpetasVistas   = [];

    if (!is_dir($tmpDir)) {
        return $destino;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $rutaCompleta = (string)$item->getPathname();
        $relativa     = str_replace('\\', '/', substr($rutaCompleta, strlen($tmpDir) + 1));
        if ($relativa === '') {
            continue;
        }

        if ($soloRelativa !== null && $relativa !== $soloRelativa) {
            continue;
        }

        $seguro = carpetas_nombre_seguro($relativa);
        if ($seguro === null) {
            $destino['omitidos'][$relativa] = 'ruta no segura';
            continue;
        }

        $destinoItem = $rutaDestino . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $seguro);

        $padre = dirname($destinoItem);
        if (!extraer_crear_dir($padre)) {
            $destino['omitidos'][$seguro] = 'no se pudo crear el directorio de destino';
            continue;
        }

        $base = realpath($padre);
        if ($base === false ||
            stripos(rtrim($base, "/\\") . DIRECTORY_SEPARATOR, $prefijoPermitido) !== 0) {
            $destino['omitidos'][$seguro] = 'la ruta se sale de la carpeta permitida';
            continue;
        }

        if (is_dir($rutaCompleta)) {
            if (extraer_crear_dir($destinoItem)) {
                $carpetasVistas[$seguro] = true;
            } else {
                $destino['omitidos'][$seguro] = 'no se pudo crear la carpeta';
            }
            continue;
        }

        $peso = (int)$item->getSize();
        $pesoAcumulado += $peso;
        if ($pesoAcumulado > RAR_EXTRACCION_MAX_BYTES) {
            extraer_json(413, [
                'success' => false,
                'mensaje' => 'La extraccion superaria los ' .
                    (int)(RAR_EXTRACCION_MAX_BYTES / 1048576) . ' MB permitidos.',
            ]);
        }

        $destinoFinal = extraer_destino_libre($destinoItem);
        if (!@rename($rutaCompleta, $destinoFinal)) {
            // rename puede fallar entre unidades: copia como alternativa.
            if (!@copy($rutaCompleta, $destinoFinal)) {
                $destino['omitidos'][$seguro] = 'no se pudo escribir en el destino';
                continue;
            }
            @unlink($rutaCompleta);
        }

        $mtime = (int)@filemtime($destinoFinal);
        if ($mtime > 0) {
            @touch($destinoFinal, $mtime);
        }

        $destino['archivos']++;
        $destino['bytes']   += $peso;
    }

    $destino['carpetas'] = count($carpetasVistas);
    return $destino;
}

// ==========================================
// Sesion, acceso y token CSRF
// ==========================================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    extraer_json(401, ['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
}

if (!carpetas_sistema_permitido()) {
    extraer_json(403, ['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
}

$metodo    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$esPost    = $metodo === 'POST';
$fuente    = $esPost ? $_POST : $_GET;
$accion    = (string)($fuente['accion'] ?? '');

if (!carpetas_csrf_valido($fuente['token'] ?? null)) {
    extraer_json(419, ['success' => false, 'mensaje' => 'Token de seguridad caducado. Recargue la pagina.']);
}

// --- Carpeta y archivo validados contra la raiz permitida ---
$resuelta = carpetas_sistema_resolver($fuente['carpeta'] ?? '');
if ($resuelta === null) {
    extraer_json(400, ['success' => false, 'mensaje' => 'Carpeta no permitida o inexistente.']);
}
list($carpetaSolicitada, $rutaCarpeta) = $resuelta;

$archivo = basename(trim((string)($fuente['archivo'] ?? '')));
if ($archivo === '' || $archivo === '.' || $archivo === '..') {
    extraer_json(400, ['success' => false, 'mensaje' => 'Nombre de archivo no valido.']);
}

// El archivo raiz debe ser un .rar. Con cadena anidada (entradas[]) ademas se
// admite un .zip raiz: el recorrido abre cada nivel con la libreria que le
// toque (ZIP o RAR) en includes/visor_anidar.php.
$parseAnidado  = visor_anidar_parsear($fuente);
$extensionRaiz = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

if (count($parseAnidado['entradas']) === 0) {
    if ($extensionRaiz !== 'rar') {
        extraer_json(400, ['success' => false, 'mensaje' => 'Solo se pueden leer archivos .rar.']);
    }
} elseif (!in_array($extensionRaiz, ['zip', 'rar'], true)) {
    extraer_json(400, ['success' => false,
        'mensaje' => 'La ruta de anidamiento debe empezar en un archivo comprimido.']);
}

// Subcarpeta relativa donde esta el RAR (p. ej. tras haber extraido otro).
$rutaRelativa = carpetas_ruta_relativa((string)($fuente['ruta'] ?? ''));
if ($rutaRelativa === null) {
    extraer_json(400, ['success' => false, 'mensaje' => 'Ruta de subcarpeta no valida.']);
}
$rutaBase = carpetas_ruta_resolver($rutaCarpeta, $rutaRelativa);
if ($rutaBase === false) {
    extraer_json(404, ['success' => false, 'mensaje' => 'La subcarpeta no existe.']);
}

$rutaArchivo = realpath($rutaBase . DIRECTORY_SEPARATOR . $archivo);
if ($rutaArchivo === false || !is_file($rutaArchivo)) {
    extraer_json(404, ['success' => false, 'mensaje' => 'El archivo no existe en esa carpeta.']);
}

if (strpos($rutaArchivo, rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR) !== 0) {
    extraer_json(400, ['success' => false, 'mensaje' => 'Ruta de archivo no permitida.']);
}

if (rar_unrar_binario() === null) {
    extraer_json(500, ['success' => false,
        'mensaje' => 'El servidor no tiene instalado UnRAR.exe (WinRAR) para leer RAR.']);
}

/**
 * Lee el indice del RAR (con la password, si el usuario ya la introdujo:
 * los archivos con cabeceras cifradas -hp- no se pueden listar sin ella).
 * Devuelve el array de entradas planas o responde el error JSON y corta.
 */
function rar_indice_o_error($rutaArchivo, $password)
{
    $indice = rar_indice_completo($rutaArchivo, $password);
    if ($indice['ok'] === true) {
        return $indice['entradas'];
    }

    $motivo = $indice['motivo'] ?? 'error';
    if ($motivo === 'password') {
        if ($password === '') {
            extraer_json(401, [
                'success'          => false,
                'requierePassword' => true,
                'codigo'           => 'password_requerida',
                'mensaje'          => 'Este RAR tiene las cabeceras cifradas: falta la contraseña.',
            ]);
        }
        extraer_json(403, [
            'success'            => false,
            'passwordIncorrecta' => true,
            'requierePassword'   => true,
            'codigo'             => 'password_incorrecta',
            'mensaje'            => 'Contraseña incorrecta.',
        ]);
    }
    if ($motivo === 'no_encontrada') {
        extraer_json(404, [
            'success' => false,
            'mensaje' => 'Esa entrada no existe en el RAR.',
        ]);
    }
    extraer_json(500, [
        'success' => false,
        'mensaje' => 'No se pudo abrir el RAR (formato no reconocido o dañado).',
        'detalles' => (string)($indice['detalles'] ?? ''),
    ]);
}

// ==========================================
// 1) Descargar una entrada concreta del RAR
// ==========================================
if ($accion === '' || $accion === 'ver_entrada') {
    if (!carpetas_sistema_puede('descargar')) {
        extraer_json(403, ['success' => false, 'mensaje' => 'Su rol no permite descargar archivos.']);
    }

    // Cadena anidada: se recorre hasta el destino (una entrada concreta o el
    // propio contenedor de la cadena) y se envia el temporal resultante. Solo
    // el nivel raiz sin cadena sigue el camino legado de mas abajo.
    if (count($parseAnidado['entradas']) > 0) {
        $preparado = visor_anidar_recorrer($rutaArchivo, $extensionRaiz,
            (int)@filesize($rutaArchivo), $archivo, $parseAnidado, 'archivo');

        $temporalDescarga = $preparado['ruta'];
        $pesoDescarga     = (int)$preparado['bytes'];
        if ($pesoDescarga > RAR_EXTRACCION_MAX_DESCARGA) {
            extraer_json(413, [
                'success' => false,
                'mensaje' => 'La entrada pesa ' . (int)($pesoDescarga / 1048576) .
                    ' MB y el limite por descarga es ' . (int)(RAR_EXTRACCION_MAX_DESCARGA / 1048576) . ' MB.',
            ]);
        }

        @set_time_limit(600);
        $nombreDescarga = basename($preparado['nombre']);

        logAction('dashboard', 'extraer_rar',
            'Descarga de una entrada anidada desde el explorador de carpetas',
            [
                'carpeta' => $carpetaSolicitada,
                'archivo' => $archivo,
                'entrada' => $nombreDescarga,
                'cadena'  => count($parseAnidado['entradas']),
                'bytes'   => $pesoDescarga,
            ],
            null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombreDescarga) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Content-Length: ' . $pesoDescarga);

        readfile($temporalDescarga);
        exit();
    }

    $indice = (int)($fuente['entrada'] ?? -1);
    if ($indice < 0) {
        extraer_json(400, ['success' => false, 'mensaje' => 'Entrada no indicada.']);
    }

    // Contraseña del RAR, si el usuario la introdujo (nunca se registra).
    $password = (string)($fuente['password'] ?? '');

    $entradas = rar_indice_o_error($rutaArchivo, $password);
    if ($indice >= count($entradas)) {
        extraer_json(404, ['success' => false, 'mensaje' => 'Esa entrada no existe en el RAR.']);
    }

    $entrada = $entradas[$indice];
    $nombreInterno = $entrada['nombre'];
    $seguro = carpetas_nombre_seguro($nombreInterno);

    if ($seguro === null || $entrada['esDir']) {
        extraer_json(400, ['success' => false, 'mensaje' => 'Entrada no descargable.']);
    }

    $peso = (int)$entrada['bytes'];
    if ($peso > RAR_EXTRACCION_MAX_DESCARGA) {
        extraer_json(413, [
            'success' => false,
            'mensaje' => 'La entrada pesa ' . (int)($peso / 1048576) .
                ' MB y el limite por descarga es ' . (int)(RAR_EXTRACCION_MAX_DESCARGA / 1048576) . ' MB.',
        ]);
    }

    if ($entrada['cifrada'] && $password === '') {
        extraer_json(401, [
            'success'          => false,
            'requierePassword' => true,
            'codigo'           => 'password_requerida',
            'mensaje'          => 'Esta entrada esta protegida con contraseña.',
        ]);
    }

    // Entradas de hasta 1 GB: mas tiempo ANTES de lanzar UnRAR.
    @set_time_limit(600);

    $extraida = rar_extraer_entrada_temporal($rutaArchivo, $nombreInterno, $password);
    if ($extraida['ok'] === false) {
        if ($extraida['motivo'] === 'password') {
            extraer_json(403, [
                'success'            => false,
                'passwordIncorrecta' => true,
                'requierePassword'   => true,
                'codigo'             => 'password_incorrecta',
                'mensaje'            => 'Contraseña incorrecta.',
            ]);
        }
        if ($extraida['motivo'] === 'password_falta') {
            extraer_json(401, [
                'success'          => false,
                'requierePassword' => true,
                'codigo'           => 'password_requerida',
                'mensaje'          => 'Esta entrada esta protegida con contraseña.',
            ]);
        }
        if ($extraida['motivo'] === 'no_encontrada') {
            extraer_json(404, [
                'success' => false,
                'mensaje' => 'No se encontro esa entrada dentro del RAR.',
            ]);
        }
        extraer_json(500, [
            'success' => false,
            'mensaje' => (string)($extraida['mensaje'] ?? 'No se pudo leer esa entrada.'),
        ]);
    }

    logAction('dashboard', 'extraer_rar',
        'Descarga de una entrada de un RAR desde el explorador de carpetas',
        [
            'carpeta'  => $carpetaSolicitada,
            'archivo'  => $archivo,
            'entrada'  => basename($seguro),
            'ruta'     => $seguro,
            'bytes'    => (int)$entrada['bytes'],
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($seguro)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
    header('Content-Length: ' . (int)@filesize($extraida['archivo']));

    // Se envia en streaming: 1 GB no cabe en memoria (memory_limit 128M) y
    // UnRAR ya verifico el CRC al extraer al temporal.
    @readfile($extraida['archivo']);
    @unlink($extraida['archivo']);
    @rmdir($extraida['temporal']);
    exit();
}

// ==========================================
// 2) Extraer todo / 3) extraer una sola entrada
// ==========================================
if (!$esPost) {
    extraer_json(405, ['success' => false, 'mensaje' => 'Metodo no permitido.']);
}

if (!in_array($accion, ['extraer_todo', 'extraer_uno'], true)) {
    extraer_json(400, ['success' => false, 'mensaje' => 'Accion no reconocida.']);
}

if (!carpetas_sistema_puede('abrir')) {
    extraer_json(403, ['success' => false, 'mensaje' => 'Su rol no permite escribir en esta carpeta.']);
}

// Carpeta de destino. Valor vacio = MISMA CARPETA: la carpeta que contiene
// el archivo (subcarpeta incluida), es decir, donde esta abierto el visor.
$claveDestino  = trim((string)($fuente['destino'] ?? ''));
$tituloDestino = null;

if ($claveDestino === '') {
    $claveDestinoFinal = $carpetaSolicitada;
    $rutaDestino       = $rutaBase;
    $tituloDestino     = 'MISMA CARPETA';
} else {
    $destinoResuelto = carpetas_sistema_resolver($claveDestino);
    if ($destinoResuelto === null) {
        extraer_json(400, ['success' => false, 'mensaje' => 'La carpeta destino no esta permitida.']);
    }
    list($claveDestinoFinal, $rutaDestino) = $destinoResuelto;
}

// Cadena anidada: extraer UNO copia el temporal de la entrada directamente al
// destino; "extraer todo" deja apuntando $rutaArchivo al contenedor final (en
// su temporal) y el flujo normal de mas abajo lo descomprime. Sin cadena no se
// toca nada: manda el camino legado del RAR raiz.
$passwordAnidado = null;
if (count($parseAnidado['entradas']) > 0) {
    $preparado = visor_anidar_recorrer($rutaArchivo, $extensionRaiz,
        (int)@filesize($rutaArchivo), $archivo, $parseAnidado, 'archivo');
    $passwordAnidado = $preparado['password'];

    if ($accion === 'extraer_uno') {
        $nombreSeguro = carpetas_nombre_seguro(basename($preparado['nombre']));
        if ($nombreSeguro === null) {
            extraer_json(400, ['success' => false, 'mensaje' => 'Entrada no extraible.']);
        }

        $destinoArchivo = $rutaDestino . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $nombreSeguro);
        $padreDestino = dirname($destinoArchivo);
        if (!extraer_crear_dir($padreDestino)) {
            extraer_json(500, ['success' => false,
                'mensaje' => 'No se pudo preparar la carpeta destino.']);
        }

        $baseDestino   = realpath($padreDestino);
        $prefijoDestino = rtrim($rutaDestino, "/\\") . DIRECTORY_SEPARATOR;
        if ($baseDestino === false ||
            stripos(rtrim($baseDestino, "/\\") . DIRECTORY_SEPARATOR, $prefijoDestino) !== 0) {
            extraer_json(400, ['success' => false,
                'mensaje' => 'La ruta de destino se sale de la carpeta permitida.']);
        }

        $destinoFinal = extraer_destino_libre($destinoArchivo);
        if (@copy($preparado['ruta'], $destinoFinal) === false) {
            extraer_json(500, ['success' => false,
                'mensaje' => 'No se pudo escribir la entrada en el destino.']);
        }

        $pesoCopia = (int)@filesize($destinoFinal);
        $mtimeCopia = (int)@filemtime($preparado['ruta']);
        if ($mtimeCopia > 0) {
            @touch($destinoFinal, $mtimeCopia);
        }

        $definicionDestino = carpetas_sistema_definicion();
        logAction('dashboard', 'extraer_rar',
            'Extraccion de una entrada anidada en el explorador de carpetas',
            [
                'carpeta' => $carpetaSolicitada,
                'archivo' => $archivo,
                'entrada' => $nombreSeguro,
                'cadena'  => count($parseAnidado['entradas']),
                'destino' => $claveDestinoFinal,
                'bytes'   => $pesoCopia,
            ],
            null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

        extraer_json(200, [
            'success'       => true,
            'mensaje'       => 'Extraccion completada.',
            'carpeta'       => $carpetaSolicitada,
            'destino'       => $claveDestinoFinal,
            'destinoTitulo' => $tituloDestino ??
                ($definicionDestino[$claveDestinoFinal]['titulo'] ?? $claveDestinoFinal),
            'subcarpeta'    => '',
            'rutaDestino'   => '',
            'archivo'       => $archivo,
            'archivos'      => 1,
            'carpetas'      => 0,
            'bytes'         => $pesoCopia,
            'omitidos'      => 0,
            'detalles'      => [],
        ]);
        exit();
    }

    // "Extraer todo" sobre un contenedor anidado: este endpoint abre RAR.
    if ($preparado['extension'] !== 'rar') {
        extraer_json(400, ['success' => false,
            'mensaje' => 'Ese contenedor anidado es un ZIP: abra su nivel y extraiga desde alli.']);
    }
    $rutaArchivo   = $preparado['ruta'];
    $archivo       = basename($preparado['nombre']);
    $extensionRaiz = 'rar';
}

// Contraseña (si el usuario ya la introdujo): ademas hace falta para poder
// listar un RAR con las cabeceras cifradas (-hp). En cadena anidada manda la
// clave del contenedor final que devolvio el recorrido.
$password = ($passwordAnidado !== null)
    ? $passwordAnidado
    : (string)($fuente['password'] ?? '');

$entradas = rar_indice_o_error($rutaArchivo, $password);

$limitadoA = null;
if ($accion === 'extraer_uno') {
    $indice = (int)($fuente['entrada'] ?? -1);
    if ($indice < 0 || $indice >= count($entradas)) {
        extraer_json(404, ['success' => false, 'mensaje' => 'Esa entrada no existe en el RAR.']);
    }
    if ($entradas[$indice]['esDir']) {
        extraer_json(400, ['success' => false, 'mensaje' => 'Ese elemento es una carpeta.']);
    }
    $limitadoA = [$indice];
}

// ==========================================
// Contraseña (si alguna entrada a extraer esta cifrada)
// ==========================================
$cifradas = 0;

foreach ($entradas as $i => $entrada) {
    if (!$entrada['cifrada']) {
        continue;
    }
    if ($limitadoA !== null && !in_array($i, $limitadoA, true)) {
        continue;
    }
    $cifradas++;
}

if ($cifradas > 0 && $password === '') {
    extraer_json(401, [
        'success'          => false,
        'requierePassword' => true,
        'codigo'           => 'password_requerida',
        'mensaje'          => 'Hay entradas protegidas con contraseña.',
        'cifradas'         => $cifradas,
    ]);
}

@set_time_limit(180);

// "Extraer todo" crea (o reutiliza) UNA carpeta con el nombre del RAR dentro
// de la carpeta elegida, igual que hace el ZIP.
$rutaDestinoFinal = $rutaDestino;
$subcarpeta       = '';

if ($accion === 'extraer_todo') {
    $subcarpeta = pathinfo(basename($archivo), PATHINFO_FILENAME);
    $subcarpeta = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*', "\0"], '_', (string)$subcarpeta);
    $subcarpeta = trim($subcarpeta, " .");

    if ($subcarpeta === '' || $subcarpeta === '.' || $subcarpeta === '..') {
        $subcarpeta = 'RAR extraido';
    }

    $rutaDestinoFinal = $rutaDestino . DIRECTORY_SEPARATOR . $subcarpeta;
    if (!extraer_crear_dir($rutaDestinoFinal)) {
        extraer_json(500, [
            'success'  => false,
            'mensaje'  => 'No se pudo crear la carpeta destino "' . $subcarpeta . '".',
        ]);
    }
}

// La extraccion se hace SIEMPRE en un temporal dentro del destino: asi el
// zip-slip y los renombrados los controla PHP, no UnRAR.
$tmpDir = $rutaDestino . DIRECTORY_SEPARATOR . '.rar_tmp_' . uniqid('', true);
if (!extraer_crear_dir($tmpDir)) {
    extraer_json(500, ['success' => false, 'mensaje' => 'No se pudo preparar la extraccion.']);
}

// Si la cancelacion (X/Esc) o un fallo matan el script a medias, el temporal
// no puede quedarse en la carpeta: el shutdown lo borra siempre que exista.
register_shutdown_function(function () use ($tmpDir) {
    if (is_dir($tmpDir)) {
        extraer_borrar_arbol($tmpDir);
    }
});

$argumentos = ['x', '-o+'];
if ($password !== '') {
    $argumentos[] = '-p' . $password;
}
$argumentos[] = $rutaArchivo;
if ($limitadoA !== null) {
    $argumentos[] = rar_patron($entradas[$limitadoA[0]]['nombre']);
}
$argumentos[] = $tmpDir . DIRECTORY_SEPARATOR;

if ($accion === 'extraer_todo' && (string)($fuente['progreso'] ?? '') === '1') {
    // Con barra de progreso: se sondea el temporal cada 250 ms (los
    // ficheros crecen durante la extraccion) mientras drena UnRAR.
    $bytesTotal   = 0;
    $totalPlan    = 0;
    $planArchivos = [];
    foreach ($entradas as $entradaPlan) {
        $bytesTotal += (int)$entradaPlan['bytes'];
        if (!$entradaPlan['esDir']) {
            $totalPlan++;
            $planArchivos[] = ['nombre' => $entradaPlan['nombre'], 'bytes' => (int)$entradaPlan['bytes']];
        }
    }

    // Prefijo: bytes de los ficheros anteriores a cada uno del plan. El %
    // por fichero sale de restar esos bytes al % global de UnRAR.
    $prefijoBytes = [];
    $acumulado    = 0;
    foreach ($planArchivos as $archivoPlan) {
        $prefijoBytes[] = $acumulado;
        $acumulado     += $archivoPlan['bytes'];
    }

    @set_time_limit(900);
    extraer_progreso_iniciar();
    extraer_progreso_emitir([
        'pct'        => 0,
        'bytes'      => 0,
        'bytesTotal' => $bytesTotal,
        'total'      => $totalPlan,
        'archivoNum' => 0,
        'archivoPct' => 0,
        'actual'     => '',
    ]);

    $resultado = rar_unrar_ejecutar_progreso($argumentos, function ($salidaUnrar) use ($tmpDir, $bytesTotal, $totalPlan, $planArchivos, $prefijoBytes) {
        $estado    = extraer_arbol_estado($tmpDir);
        $hechos    = $estado['bytes'];
        $iniciados = (int)$estado['archivos'];

        // % global real de UnRAR (0-100): sale de su propia salida, que se
        // lee en vivo. Los tamaños del temporal no valen para el progreso:
        // UnRAR preasigna el tamano final de cada fichero al crearlo, asi
        // que filesize() daria 100 % desde el primer instante.
        $globalPct = null;
        if (preg_match_all('/(\d{1,3})\s*%/', (string)$salidaUnrar, $coincidencias)
            && !empty($coincidencias[1])) {
            $globalPct = max(0, min(100, (int)end($coincidencias[1])));
        }

        $bytesGlobal = ($globalPct !== null && $bytesTotal > 0)
            ? $bytesTotal * $globalPct / 100
            : null;

        // Fichero actual = el ultimo empezado: UnRAR los crea en orden de
        // archivo (con el tamano ya puesto), asi que el recuento de ficheros
        // existentes en el temporal apunta al plan directamente.
        $nombreActual = '';
        $archivoPct   = null;
        if ($iniciados > 0 && $iniciados <= count($planArchivos)) {
            $previsto     = $planArchivos[$iniciados - 1];
            $nombreActual = $previsto['nombre'];
            if ($bytesGlobal !== null) {
                $dentro = $bytesGlobal - $prefijoBytes[$iniciados - 1];
                $archivoPct = $previsto['bytes'] > 0
                    ? (int)round($dentro * 100 / $previsto['bytes'])
                    : 100;
                $archivoPct = max(0, min(100, $archivoPct));
            }
        }

        // Barra total: bytes reales (los que lleva UnRAR), no los preasignados.
        if ($bytesGlobal !== null) {
            $bytesBarra = (int)min($bytesTotal, round($bytesGlobal));
            $pctBarra   = (int)min(99, $globalPct);
        } else {
            $bytesBarra = $hechos;
            $pctBarra   = $bytesTotal > 0 ? (int)min(99, floor($hechos * 100 / $bytesTotal)) : 0;
        }

        extraer_progreso_emitir([
            'pct'        => $pctBarra,
            'bytes'      => $bytesBarra,
            'bytesTotal' => $bytesTotal,
            'total'      => $totalPlan,
            'archivoNum' => $iniciados,
            'archivoPct' => $archivoPct,
            'actual'     => $nombreActual,
        ]);
    }, 900);
} else {
    $resultado = rar_unrar_ejecutar($argumentos, 150);
}
$clasificado = rar_unrar_clasificar($resultado);

if ($clasificado === 'password' || $clasificado === 'password_falta') {
    extraer_borrar_arbol($tmpDir);
    if ($password === '') {
        extraer_json(401, [
            'success'          => false,
            'requierePassword' => true,
            'codigo'           => 'password_requerida',
            'mensaje'          => 'Hay entradas protegidas con contraseña.',
            'cifradas'         => max(1, $cifradas),
        ]);
    }
    extraer_json(403, [
        'success'            => false,
        'passwordIncorrecta' => true,
        'requierePassword'   => true,
        'codigo'             => 'password_incorrecta',
        'mensaje'            => 'Contraseña incorrecta.',
    ]);
}

if ($clasificado === 'no_encontrada') {
    extraer_borrar_arbol($tmpDir);
    extraer_json(404, [
        'success' => false,
        'mensaje' => 'No se encontro esa entrada dentro del RAR.',
        'detalles' => trim($resultado['salida'] . ' ' . $resultado['error']),
    ]);
}

if ($clasificado !== 'ok') {
    extraer_borrar_arbol($tmpDir);
    extraer_json(500, [
        'success' => false,
        'mensaje' => $clasificado === 'corrupta'
            ? 'El RAR esta dañado (CRC incorrecto).'
            : 'UnRAR no pudo extraer el archivo.',
        'detalles' => trim($resultado['salida'] . ' ' . $resultado['error']),
    ]);
}

$soloRelativa = null;
if ($limitadoA !== null) {
    $nombreSolo  = $entradas[$limitadoA[0]]['nombre'];
    $seguroSolo  = carpetas_nombre_seguro($nombreSolo);
    if ($seguroSolo === null) {
        extraer_borrar_arbol($tmpDir);
        extraer_json(400, ['success' => false, 'mensaje' => 'Entrada no extraible.']);
    }
    $soloRelativa = $seguroSolo;
}

$serie = extraer_mover_arbol($tmpDir, $rutaDestinoFinal, $soloRelativa);
extraer_borrar_arbol($tmpDir);

if (!empty($GLOBALS['extraer_con_progreso'])) {
    extraer_progreso_emitir([
        'pct'        => 100,
        'bytes'      => 0,
        'bytesTotal' => 0,
        'total'      => $totalPlan,
        'archivoNum' => $totalPlan,
        'archivoPct' => 100,
    ]);
}

// Si no se extrajo nada, no se deja la carpeta recien creada vacia.
if ($subcarpeta !== '' && $serie['archivos'] === 0 && $serie['carpetas'] === 0) {
    @rmdir($rutaDestinoFinal);
    $subcarpeta = '';
}

$definicionDestino = carpetas_sistema_definicion();

logAction('dashboard', 'extraer_rar',
    $accion === 'extraer_uno'
        ? 'Extraccion de una entrada de un RAR en el explorador de carpetas'
        : 'Extraccion completa de un RAR en el explorador de carpetas',
    [
        'carpeta'    => $carpetaSolicitada,
        'archivo'    => $archivo,
        'destino'    => $claveDestinoFinal,
        'subcarpeta' => $subcarpeta,
        'archivos'   => $serie['archivos'],
        'carpetas'   => $serie['carpetas'],
        'bytes'      => $serie['bytes'],
        'omitidos'   => count($serie['omitidos']),
    ],
    null,
    $serie['archivos'] > 0 ? 'success' : 'error',
    null, $_SESSION['auth_provider'] ?? 'local');

extraer_json(200, [
    'success'       => $serie['archivos'] > 0 || $serie['carpetas'] > 0,
    'mensaje'       => $serie['archivos'] > 0
        ? 'Extraccion completada.'
        : 'No se extrajo ningun archivo.',
    'carpeta'       => $carpetaSolicitada,
    'destino'       => $claveDestinoFinal,
    'destinoTitulo' => $tituloDestino ??
        ($definicionDestino[$claveDestinoFinal]['titulo'] ?? $claveDestinoFinal),
    'subcarpeta'    => $subcarpeta,
    'rutaDestino'   => $subcarpeta,
    'archivo'       => $archivo,
    'archivos'      => $serie['archivos'],
    'carpetas'      => $serie['carpetas'],
    'bytes'         => $serie['bytes'],
    'omitidos'      => count($serie['omitidos']),
    'detalles'      => $serie['omitidos'],
]);
