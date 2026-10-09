<?php
// modules/extraer_zip.php - Lee un .zip de las carpetas del sistema
//
// Tres operaciones (todas exigen token CSRF de sesion):
//   1. GET  accion=ver_entrada &entrada=N  -> descarga binaria de esa entrada.
//   2. POST accion=extraer_todo            -> descomprime todo el ZIP.
//   3. POST accion=extraer_uno   &entrada=N-> descomprime una sola entrada.
// En las dos ultimas, "destino" es la clave de la carpeta del sistema donde
// dejar los archivos (por defecto, la que contiene el ZIP).
//
// Reglas comunes: nunca se sale de la raiz permitida (proteccion zip-slip),
// nunca se pisa un archivo existente (sufijo " (2)"), se comprueba el CRC-32
// de cada entrada y hay limites de entradas, bytes y tiempo de ejecucion.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/visor_anidar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limites de extraccion: una salva no debe poder inundar el disco.
define('EXTRACCION_MAX_ARCHIVOS', 5000);
define('EXTRACCION_MAX_BYTES', 1024 * 1024 * 1024);   // 1 GB descomprimido
define('EXTRACCION_MAX_DESCARGA', 1024 * 1024 * 1024);    // 1 GB por entrada descargada
define('EXTRACCION_DESCARGA_MEMORIA', 64 * 1024 * 1024);  // hasta aqui se lee en RAM con CRC verificado
define('EXTRACCION_INTENTOS_NOMBRE', 99);             // sufijos " (2)", " (3)"...
define('EXTRACCION_BLOQUE', 512 * 1024);              // copia y CRC por bloques

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
        // Cancelado desde la pagina (X/Esc): se cierra y borra el fichero
        // que estaba a medias y se sale; lo ya extraido se queda.
        $parcial = $GLOBALS['extraer_zip_parcial'] ?? null;
        if (is_array($parcial)) {
            if (isset($parcial['mano']) && is_resource($parcial['mano'])) {
                @fclose($parcial['mano']);
            }
            if (isset($parcial['ruta']) && is_file($parcial['ruta'])) {
                @unlink($parcial['ruta']);
            }
        }
        exit();
    }
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

    for ($n = 2; $n <= EXTRACCION_INTENTOS_NOMBRE; $n++) {
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
 * Descomprime las entradas indicadas (o todas si $limitadoA es null) dentro
 * de $rutaDestino, que debe ser una de las carpetas permitidas.
 *
 * @param callable|null $alProgreso  Barra de progreso: se le pasa un array
 *                                  con bytes, bytesTotal, total y actual.
 * @return array{archivos: int, carpetas: int, bytes: int, omitidos: array<string, string>}
 */
function extraer_descomprimir(ZipArchive $zip, $rutaDestino, $limitadoA = null, $alProgreso = null)
{
    $prefijoPermitido = rtrim($rutaDestino, "/\\") . DIRECTORY_SEPARATOR;
    $destino          = ['archivos' => 0, 'carpetas' => 0, 'bytes' => 0, 'omitidos' => []];
    $pesoAcumulado    = 0;

    // Plan de la extraccion: cuantos ficheros y bytes hay previstos.
    $planBytes = 0;
    $planTotal = 0;
    for ($p = 0; $p < $zip->numFiles; $p++) {
        if ($limitadoA !== null && !in_array($p, $limitadoA, true)) {
            continue;
        }
        $nombrePlan = (string)$zip->getNameIndex($p);
        if (substr(str_replace('\\', '/', $nombrePlan), -1) === '/') {
            continue;
        }
        if (carpetas_nombre_seguro($nombrePlan) === null) {
            continue;
        }
        $statPlan = $zip->statIndex($p);
        $planTotal++;
        $planBytes += (int)($statPlan['size'] ?? 0);
    }

    $emitir = function ($bytesHechos, $actual = '', $archivoNum = 0, $archivoPct = null) use ($alProgreso, $planBytes, $planTotal) {
        if ($alProgreso === null) {
            return;
        }
        call_user_func($alProgreso, [
            'pct'        => $planBytes > 0 ? (int)min(99, floor($bytesHechos * 100 / $planBytes)) : 0,
            'bytes'      => (int)$bytesHechos,
            'bytesTotal' => $planBytes,
            'total'      => $planTotal,
            'archivoNum' => (int)$archivoNum,
            'archivoPct' => $archivoPct === null ? null : (int)$archivoPct,
            'actual'     => (string)$actual,
        ]);
    };

    $archivosIniciados = 0;
    $emitir(0, '', 0, 0);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        if ($limitadoA !== null && !in_array($i, $limitadoA, true)) {
            continue;
        }

        $nombreCrudo = (string)$zip->getNameIndex($i);
        $seguro      = carpetas_nombre_seguro($nombreCrudo);

        if ($seguro === null) {
            $destino['omitidos'][$nombreCrudo] = 'ruta no segura';
            continue;
        }

        $esDir   = substr(str_replace('\\', '/', $nombreCrudo), -1) === '/';
        $destinoArchivo = $rutaDestino . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $seguro);

        // El directorio de destino debe seguir dentro de la carpeta permitida.
        $padre = dirname($destinoArchivo);
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

        if ($esDir) {
            if (!extraer_crear_dir($destinoArchivo)) {
                $destino['omitidos'][$seguro] = 'no se pudo crear la carpeta';
                continue;
            }
            $destino['carpetas']++;
            continue;
        }

        $stat           = $zip->statIndex($i);
        $pesoAcumulado += (int)($stat['size'] ?? 0);
        if ($pesoAcumulado > EXTRACCION_MAX_BYTES) {
            extraer_json(413, [
                'success' => false,
                'mensaje' => 'La extraccion superaria los ' .
                    (int)(EXTRACCION_MAX_BYTES / 1048576) . ' MB permitidos.',
            ]);
        }

        $flujo = $zip->getStream($nombreCrudo);
        if ($flujo === false) {
            $destino['omitidos'][$seguro] = 'no se pudo leer la entrada (puede estar protegida)';
            continue;
        }

        // Nunca pisar: si ya existe, el destino recibe un sufijo.
        $destinoFinal = extraer_destino_libre($destinoArchivo);
        $salida = @fopen($destinoFinal, 'wb');
        if ($salida === false) {
            fclose($flujo);
            $destino['omitidos'][$seguro] = 'no se pudo escribir en el destino';
            continue;
        }
        // Fichero en curso: si el cliente cancela, la emision lo cierra y borra.
        $GLOBALS['extraer_zip_parcial'] = ['ruta' => $destinoFinal, 'mano' => $salida];

        // Copia y CRC-32 en una sola pasada: si el dato no cuadra con el
        // central directory, se borra lo escrito y se omite la entrada.
        $contexto   = hash_init('crc32b');
        $escrito    = 0;
        $fallo      = false;
        $ultimoProg = microtime(true);
        $tamano     = (int)($stat['size'] ?? 0);
        $archivosIniciados++;
        if ($alProgreso !== null) {
            // Avisar al empezar cada fichero: mantiene viva la fila
            // "Archivo N de M" y reinicia la primera barra a 0.
            $emitir($pesoAcumulado - $tamano, $seguro, $archivosIniciados, 0);
        }

        while (!feof($flujo)) {
            $bloque = fread($flujo, EXTRACCION_BLOQUE);
            if ($bloque === false) {
                $fallo = true;
                break;
            }
            if ($bloque === '') {
                continue;
            }
            hash_update($contexto, $bloque);
            $escrito += strlen($bloque);
            if (fwrite($salida, $bloque) === false) {
                $fallo = true;
                break;
            }
            if ($alProgreso !== null && microtime(true) - $ultimoProg > 0.2) {
                $ultimoProg = microtime(true);
                $emitir($pesoAcumulado - $tamano + min($escrito, $tamano), $seguro,
                    $archivosIniciados,
                    $tamano > 0 ? (int)min(99, floor($escrito * 100 / $tamano)) : 100);
            }
        }

        fclose($flujo);
        fclose($salida);
        $GLOBALS['extraer_zip_parcial'] = null;

        if ($fallo) {
            @unlink($destinoFinal);
            $destino['omitidos'][$seguro] = 'la copia se interrumpio';
            continue;
        }

        $crcEsperado = carpetas_zip_crc_esperado($stat);
        if ($crcEsperado !== '' && hash_final($contexto) !== $crcEsperado) {
            @unlink($destinoFinal);
            $destino['omitidos'][$seguro] = 'el CRC-32 no coincide (posible corrupcion)';
            continue;
        }

        $mtime = (int)($stat['mtime'] ?? 0);
        if ($mtime > 0) {
            @touch($destinoFinal, $mtime);
        }

        // Fichero completo: la primera barra llega al 100 % y con el
        // siguiente emitir(…, 0) vuelve a empezar.
        $emitir($pesoAcumulado, $seguro, $archivosIniciados, 100);

        $destino['archivos']++;
        $destino['bytes']   += $escrito;
    }

    $emitir($planBytes, '', $planTotal, 100);

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

// El archivo raiz debe ser un .zip. Con cadena anidada (entradas[]) ademas se
// admite un .rar raiz: el recorrido abre cada nivel con la libreria que le
// toque (ZIP o RAR) en includes/visor_anidar.php.
$parseAnidado  = visor_anidar_parsear($fuente);
$extensionRaiz = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

if (count($parseAnidado['entradas']) === 0) {
    if ($extensionRaiz !== 'zip') {
        extraer_json(400, ['success' => false, 'mensaje' => 'Solo se pueden leer archivos .zip.']);
    }
} elseif (!in_array($extensionRaiz, ['zip', 'rar'], true)) {
    extraer_json(400, ['success' => false,
        'mensaje' => 'La ruta de anidamiento debe empezar en un archivo comprimido.']);
}

// Subcarpeta relativa donde esta el ZIP (p. ej. tras haber extraido otro).
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

if (!class_exists('ZipArchive')) {
    extraer_json(500, ['success' => false, 'mensaje' => 'El servidor no tiene habilitada la extension ZIP.']);
}

// ==========================================
// 1) Descargar una entrada concreta del ZIP
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
        if ($pesoDescarga > EXTRACCION_MAX_DESCARGA) {
            extraer_json(413, [
                'success' => false,
                'mensaje' => 'La entrada pesa ' . (int)($pesoDescarga / 1048576) .
                    ' MB y el limite por descarga es ' . (int)(EXTRACCION_MAX_DESCARGA / 1048576) . ' MB.',
            ]);
        }

        @set_time_limit(600);
        $nombreDescarga = basename($preparado['nombre']);

        logAction('dashboard', 'extraer_zip',
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

    // Contraseña del ZIP, si el usuario la introdujo (nunca se registra).
    $password = (string)($fuente['password'] ?? '');

    $zip = new ZipArchive();
    $abierto = $zip->open($rutaArchivo);
    if ($abierto !== true) {
        extraer_json(500, ['success' => false, 'mensaje' => 'No se pudo abrir el ZIP (codigo ' . (int)$abierto . ').']);
    }

    if ($indice >= $zip->numFiles) {
        $zip->close();
        extraer_json(404, ['success' => false, 'mensaje' => 'Esa entrada no existe en el ZIP.']);
    }

    $nombreInterno = (string)$zip->getNameIndex($indice);
    $seguro        = carpetas_nombre_seguro($nombreInterno);
    $esDir         = substr(str_replace('\\', '/', $nombreInterno), -1) === '/';

    if ($seguro === null || $esDir) {
        $zip->close();
        extraer_json(400, ['success' => false, 'mensaje' => 'Entrada no descargable.']);
    }

    $stat  = $zip->statIndex($indice);
    $peso  = (int)($stat['size'] ?? 0);
    if ($peso > EXTRACCION_MAX_DESCARGA) {
        $zip->close();
        extraer_json(413, [
            'success' => false,
            'mensaje' => 'La entrada pesa ' . (int)($peso / 1048576) .
                ' MB y el limite por descarga es ' . (int)(EXTRACCION_MAX_DESCARGA / 1048576) . ' MB.',
        ]);
    }

    // Si esa entrada esta cifrada hace falta la contraseña antes de leerla.
    $flagsZip = carpetas_zip_flags($rutaArchivo);
    $entradaCifrada = !empty($flagsZip['mapa'][$indice]);

    if ($entradaCifrada) {
        if ($password === '') {
            $zip->close();
            extraer_json(401, [
                'success'          => false,
                'requierePassword' => true,
                'codigo'           => 'password_requerida',
                'mensaje'          => 'Esta entrada esta protegida con contraseña.',
            ]);
        }

        carpetas_zip_aplicar_password($zip, $password);
    }

    // Entradas grandes (mas de 64 MB, hasta 1 GB): se envian en streaming
    // porque no caben en memoria (memory_limit 128M). En este caso el CRC
    // no se puede verificar antes de enviar el primer byte.
    if ($peso > EXTRACCION_DESCARGA_MEMORIA) {
        @set_time_limit(600);
        $flujo = $zip->getStream($nombreInterno);
        if ($flujo === false) {
            $zip->close();
            if ($entradaCifrada) {
                extraer_json(403, [
                    'success'            => false,
                    'passwordIncorrecta' => true,
                    'requierePassword'   => true,
                    'codigo'             => 'password_incorrecta',
                    'mensaje'            => 'Contraseña incorrecta.',
                ]);
            }
            extraer_json(500, ['success' => false, 'mensaje' => 'No se pudo abrir esa entrada.']);
        }

        logAction('dashboard', 'extraer_zip',
            'Descarga de una entrada de un ZIP desde el explorador de carpetas',
            [
                'carpeta'  => $carpetaSolicitada,
                'archivo'  => $archivo,
                'entrada'  => basename($seguro),
                'ruta'     => $seguro,
                'bytes'    => $peso,
            ],
            null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($seguro)) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Content-Length: ' . $peso);

        fpassthru($flujo);
        fclose($flujo);
        $zip->close();
        exit();
    }

    // Se lee completa en memoria: asi el CRC se comprueba ANTES de enviar
    // un solo byte (nunca se entrega contenido corrupto a medias).
    $contenido = $zip->getFromIndex($indice);
    $zip->close();

    if ($contenido === false) {
        if ($entradaCifrada) {
            extraer_json(403, [
                'success'          => false,
                'passwordIncorrecta' => true,
                'requierePassword' => true,
                'codigo'           => 'password_incorrecta',
                'mensaje'          => 'Contraseña incorrecta.',
            ]);
        }

        extraer_json(500, ['success' => false, 'mensaje' => 'No se pudo leer esa entrada (puede estar protegida).']);
    }

    $crcEsperado = carpetas_zip_crc_esperado($stat);
    if ($crcEsperado !== '' && hash('crc32b', $contenido) !== $crcEsperado) {
        extraer_json(500, ['success' => false, 'mensaje' => 'El CRC-32 no coincide: la entrada esta corrupta.']);
    }

    logAction('dashboard', 'extraer_zip',
        'Descarga de una entrada de un ZIP desde el explorador de carpetas',
        [
            'carpeta'  => $carpetaSolicitada,
            'archivo'  => $archivo,
            'entrada'  => basename($seguro),
            'ruta'     => $seguro,
            'bytes'    => $peso,
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    @set_time_limit(120);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($seguro)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');

    echo $contenido;
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
// toca nada: manda el camino legado del ZIP raiz.
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
        logAction('dashboard', 'extraer_zip',
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

    // "Extraer todo" sobre un contenedor anidado: este endpoint abre ZIP.
    if ($preparado['extension'] !== 'zip') {
        extraer_json(400, ['success' => false,
            'mensaje' => 'Ese contenedor anidado es un RAR: abra su nivel y extraiga desde alli.']);
    }
    $rutaArchivo   = $preparado['ruta'];
    $archivo       = basename($preparado['nombre']);
    $extensionRaiz = 'zip';
}

$zip = new ZipArchive();
$abierto = $zip->open($rutaArchivo);
if ($abierto !== true) {
    extraer_json(500, ['success' => false, 'mensaje' => 'No se pudo abrir el ZIP (codigo ' . (int)$abierto . ').']);
}

if ($zip->numFiles > EXTRACCION_MAX_ARCHIVOS) {
    $demasiadas = (int)$zip->numFiles;
    $zip->close();
    extraer_json(413, [
        'success' => false,
        'mensaje' => 'El ZIP tiene ' . $demasiadas . ' entradas y el limite es ' .
            EXTRACCION_MAX_ARCHIVOS . '.',
    ]);
}

$limitadoA = null;
if ($accion === 'extraer_uno') {
    $indice = (int)($fuente['entrada'] ?? -1);
    if ($indice < 0 || $indice >= $zip->numFiles) {
        $zip->close();
        extraer_json(404, ['success' => false, 'mensaje' => 'Esa entrada no existe en el ZIP.']);
    }
    $esDir = substr(str_replace('\\', '/', (string)$zip->getNameIndex($indice)), -1) === '/';
    if ($esDir) {
        $zip->close();
        extraer_json(400, ['success' => false, 'mensaje' => 'Ese elemento es una carpeta.']);
    }
    $limitadoA = [$indice];
}

// ==========================================
// Contraseña (si alguna entrada a extraer esta cifrada)
// ==========================================
$password = ($passwordAnidado !== null)
    ? $passwordAnidado
    : (string)($fuente['password'] ?? '');
$flagsZip   = carpetas_zip_flags($rutaArchivo);
$cifradas   = 0;

foreach ($flagsZip['mapa'] as $idxEntrada => $cifrada) {
    if (!$cifrada || (int)$idxEntrada >= $zip->numFiles) {
        continue;
    }
    if ($limitadoA !== null && !in_array((int)$idxEntrada, $limitadoA, true)) {
        continue;
    }
    $cifradas++;
}

if ($cifradas > 0) {
    if ($password === '') {
        $zip->close();
        extraer_json(401, [
            'success'          => false,
            'requierePassword' => true,
            'codigo'           => 'password_requerida',
            'mensaje'          => 'Hay entradas protegidas con contraseña.',
            'cifradas'         => $cifradas,
        ]);
    }

    carpetas_zip_aplicar_password($zip, $password);

    // Prueba de lectura con la primera entrada cifrada: si la contraseña
    // falla se avisa antes de crear ninguna carpeta ni escribir nada.
    foreach ($flagsZip['mapa'] as $idxEntrada => $cifrada) {
        if (!$cifrada || (int)$idxEntrada >= $zip->numFiles) {
            continue;
        }
        if ($limitadoA !== null && !in_array((int)$idxEntrada, $limitadoA, true)) {
            continue;
        }

        $prueba = $zip->getFromIndex((int)$idxEntrada);
        if ($prueba === false && carpetas_zip_password_fallida($zip)) {
            $zip->close();
            extraer_json(403, [
                'success'            => false,
                'passwordIncorrecta' => true,
                'requierePassword'   => true,
                'codigo'             => 'password_incorrecta',
                'mensaje'            => 'Contraseña incorrecta.',
            ]);
        }
        break;
    }
}

@set_time_limit(180);

// "Extraer todo" crea (o reutiliza) UNA carpeta con el nombre del ZIP dentro
// de la carpeta elegida, y dentro va toda la estructura interna del ZIP.
$rutaDestinoFinal = $rutaDestino;
$subcarpeta       = '';

if ($accion === 'extraer_todo') {
    $subcarpeta = pathinfo(basename($archivo), PATHINFO_FILENAME);
    $subcarpeta = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*', "\0"], '_', (string)$subcarpeta);
    $subcarpeta = trim($subcarpeta, " .");

    if ($subcarpeta === '' || $subcarpeta === '.' || $subcarpeta === '..') {
        $subcarpeta = 'ZIP extraido';
    }

    $rutaDestinoFinal = $rutaDestino . DIRECTORY_SEPARATOR . $subcarpeta;
    if (!extraer_crear_dir($rutaDestinoFinal)) {
        extraer_json(500, [
            'success'  => false,
            'mensaje'  => 'No se pudo crear la carpeta destino "' . $subcarpeta . '".',
        ]);
    }
}

$conProgreso = $accion === 'extraer_todo' && (string)($fuente['progreso'] ?? '') === '1';
if ($conProgreso) {
    @set_time_limit(900);
    extraer_progreso_iniciar();
}

$serie = extraer_descomprimir(
    $zip,
    $rutaDestinoFinal,
    $limitadoA,
    $conProgreso ? function (array $datos) {
        extraer_progreso_emitir($datos);
    } : null
);
$zip->close();

// Si no se extrajo nada, no se deja la carpeta recien creada vacia.
if ($subcarpeta !== '' && $serie['archivos'] === 0 && $serie['carpetas'] === 0) {
    @rmdir($rutaDestinoFinal);
    $subcarpeta = '';
}

$definicionDestino = carpetas_sistema_definicion();

logAction('dashboard', 'extraer_zip',
    $accion === 'extraer_uno'
        ? 'Extraccion de una entrada de un ZIP en el explorador de carpetas'
        : 'Extraccion completa de un ZIP en el explorador de carpetas',
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
