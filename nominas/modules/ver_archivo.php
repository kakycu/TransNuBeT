<?php
// modules/ver_archivo.php - Visor de contenido de archivos
// .txt / .sql / .xml / .csv / .dbf / .zip
//
// Devuelve el contenido listo para pintar en un modal: el texto plano de un
// .txt, las cabeceras + registros de un .dbf (dBase III, el formato que
// exportan exportar_bandec.php y domiciliacion_dbf.php) y el indice de
// entradas de un .zip (extraer uno solo se hace en extraer_zip.php).
// Acceso: cualquier rol que pueda entrar al modulo de carpetas (los mismos
// que ya ven el listado); la carpeta y el archivo se validan contra la raiz.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/visor_anidar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Tope de lectura: el modal no debe cargarse con megas de texto ni con un
// .dbf de decenas de miles de filas.
define('VISOR_LIMITE_TEXTO', 400 * 1024);      // 400 KB de .txt
define('VISOR_LIMITE_XML', 1024 * 1024);       // 1 MB de .xml para formatear
define('VISOR_LIMITE_FILAS', 300);             // filas de .dbf mostradas
define('VISOR_LIMITE_FILAS_CSV', 300);         // filas de .csv mostradas
define('VISOR_LIMITE_LECTURA_CSV', 5000);      // filas de .csv leidas a memoria
define('VISOR_LIMITE_ENTRADAS', 2000);         // entradas del .zip devueltas al navegador
define('VISOR_LIMITE_RECORRIDO_ZIP', 5000);    // entradas recorridas para los contadores

// --- Sesion ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

// --- Permisos: basta con poder ver el modulo ---
if (!carpetas_sistema_permitido()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No tiene acceso a esta opcion por su rol.']);
    exit();
}

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

$archivo = basename(trim((string)($_POST['archivo'] ?? '')));
if ($archivo === '' || $archivo === '.' || $archivo === '..') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Nombre de archivo no valido.']);
    exit();
}

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

$extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
if (!in_array($extension, ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd', 'zip', 'rar'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Solo se pueden ver archivos .txt, .sql, .dbf, .xml, .csv, .log, .json, .md, .ps1, .cmd, .zip y .rar.']);
    exit();
}

$rutaArchivo = realpath($rutaBase . DIRECTORY_SEPARATOR . $archivo);
if ($rutaArchivo === false || !is_file($rutaArchivo)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe en esa carpeta.']);
    exit();
}
if (strpos($rutaArchivo, rtrim($rutaBase, "/\\") . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Ruta de archivo no permitida.']);
    exit();
}

$bytes = (int)@filesize($rutaArchivo);

// ==========================================
// Entradas DENTRO del ZIP/RAR (vista anidada): se descomprimen en temporales y
// se apunta al resultado, de modo que las ramas de txt/sql/dbf/xml/csv trabajan
// igual que con un archivo suelto. El parseo de entradas[]/passwords[] (o del
// legado entrada+password) y el recorrido de la cadena viven en
// includes/visor_anidar.php, compartido con extraer_zip.php y extraer_rar.php
// para poder descargar y extraer entradas anidadas. Los temporales se borran
// al terminar la peticion (register_shutdown de cada helper).
// ==========================================
$parseAnidado = visor_anidar_parsear($_POST);
$password     = $parseAnidado['password'];

// En vista anidada $archivo/$rutaArchivo apuntan al temporal de la cadena:
// no procede armar la URL de descarga directa del fichero raiz (se oculta).
$hayEntradas = count($parseAnidado['entradas']) > 0;

if ($hayEntradas) {
    $preparado = visor_anidar_recorrer(
        $rutaArchivo, $extension, $bytes, $archivo, $parseAnidado, 'visor');

    $rutaArchivo = $preparado['ruta'];
    $extension   = $preparado['extension'];
    $bytes       = $preparado['bytes'];
    $archivo     = basename($preparado['nombre']);
    $password    = $preparado['password'];
}

/**
 * Pasa un texto codificado por el equipo (UTF-8 con/sin BOM, UTF-16 o
 * Windows-1252, que como escribe el proyecto) a UTF-8 para el modal.
 */
function visor_a_utf8($texto)
{
    if ($texto === '' || $texto === false) {
        return '';
    }
    if (strncmp($texto, "\xEF\xBB\xBF", 3) === 0) {
        $texto = substr($texto, 3);
    }
    if (strncmp($texto, "\xFF\xFE", 2) === 0 || strncmp($texto, "\xFE\xFF", 2) === 0) {
        $utf16 = @mb_convert_encoding($texto, 'UTF-8', 'UTF-16');
        return $utf16 !== false ? $utf16 : '';
    }
    if (mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }
    $convertido = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    return $convertido !== false ? $convertido : $texto;
}

/**
 * Deja un registro DBF en una sola tira de texto legible (modo degradado
 * cuando los descriptores de campo no se pueden interpretar).
 */
function visor_texto_crudo($bytes)
{
    $texto = visor_a_utf8((string)$bytes);
    $texto = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$texto);
    $texto = preg_replace('/\s+/', ' ', (string)$texto);
    return trim((string)$texto);
}

/**
 * Identifica el formato DBF a partir del primer byte de la cabecera.
 * Devuelve nombre (para mostrar), generador y si la version es conocida.
 */
function visor_dbf_formato($version)
{
    $mapa = [
        0x01 => ['Vulcan / dBASE I (max. 16 campos)',   'dBASE'],
        0x02 => ['dBASE II / FoxBASE (cabecera de 8 B)', 'dBASE'],
        0x03 => ['dBASE III / III+ (fecha AAAAMMDD)',   'dBASE'],
        0x04 => ['dBASE IV (tipo Float)',               'dBASE'],
        0x05 => ['dBASE V (tipos Binary y General)',    'dBASE'],
        0x06 => ['FoxBase / dBASE',                     'Fox'],
        0x07 => ['dBASE IV con memo',                   'dBASE'],
        0x08 => ['dBASE IV SQL',                        'dBASE'],
        0x0B => ['dBASE IV SQL',                        'dBASE'],
        0x0C => ['dBASE IV SQL (tabla de sistema)',     'dBASE'],
        0x15 => ['FoxPro sin memo',                     'FoxPro'],
        0x1B => ['Clipper / R&KO',                      'Clipper'],
        0x30 => ['Visual FoxPro',                       'FoxPro'],
        0x31 => ['Visual FoxPro (autoincremento)',      'FoxPro'],
        0x32 => ['Visual FoxPro (nchar)',               'FoxPro'],
        0x43 => ['dBASE IV con memo',                   'dBASE'],
        0x63 => ['dBASE IV SQL',                        'dBASE'],
        0x7B => ['dBASE IV con memo',                   'dBASE'],
        0x83 => ['dBASE III+ con memo (.DBT)',          'dBASE'],
        0x8B => ['dBASE IV con memo',                   'dBASE'],
        0x8C => ['dBASE IV con memo (incompleto)',      'dBASE'],
        0x8E => ['dBASE IV SQL con memo',               'dBASE'],
        0xCB => ['Clipper (DBOPT)',                     'Clipper'],
        0xE5 => ['Clipper (EN)',                        'Clipper'],
        0xF5 => ['FoxPro con memo (.FPT)',              'FoxPro'],
    ];

    $hex = sprintf('0x%02X', $version);
    if (isset($mapa[$version])) {
        return [
            'nombre'    => $mapa[$version][0],
            'generador' => $mapa[$version][1],
            'hex'       => $hex,
            'conocido'  => true,
        ];
    }

    return [
        'nombre'    => 'version ' . $hex . ' no identificada',
        'generador' => 'desconocido',
        'hex'       => $hex,
        'conocido'  => false,
    ];
}

/**
 * Fecha de la cabecera DBF (byte 1 = anio desde 1900, 2 = mes, 3 = dia).
 * Si los bytes no forman una fecha real (pasa en dBASE II, cuya cabecera
 * guarda otras cosas ahi) se devuelve null y el modal no la muestra.
 */
function visor_dbf_fecha($cabecera)
{
    $anio = 1900 + ord($cabecera[1]);
    $mes  = ord($cabecera[2]);
    $dia  = ord($cabecera[3]);

    if ($mes < 1 || $mes > 12 || $dia < 1 || $dia > 31 || $anio > 2200) {
        return null;
    }
    return sprintf('%02d/%02d/%04d', $dia, $mes, $anio);
}

/**
 * dBASE II no indica en la cabecera donde empiezan los registros: hay que
 * buscarlo. Se recorre el archivo desde el fin de los descriptores buscando
 * posiciones con marca de borrado valida (' ' o '*') que dejen registros
 * encadenados de punta a punta hasta el 0x1A final o el fin del fichero.
 * Devuelve [inicio, registros] o null si ninguna posicion encaja.
 */
function visor_dbf_inicio_datos($fp, $finDescriptores, $recordLen, $tamanoArchivo)
{
    if ($recordLen < 2) {
        return null;
    }

    $mejor = null;
    $mejorCuenta = 0;
    $limite = min($finDescriptores + 1024, $tamanoArchivo);

    for ($inicio = $finDescriptores; $inicio < $limite; $inicio++) {
        fseek($fp, $inicio);
        $marca = fread($fp, 1);
        if ($marca !== ' ' && $marca !== '*') {
            continue;
        }

        $cuenta  = 0;
        $cerrado = false;
        while (true) {
            $pos = $inicio + $cuenta * $recordLen;
            if ($pos === $tamanoArchivo) {
                $cerrado = true; // el ultimo registro termina justo al final
                break;
            }
            if ($pos > $tamanoArchivo || $pos + $recordLen > $tamanoArchivo) {
                break;
            }
            fseek($fp, $pos);
            $marcaRegistro = fread($fp, 1);
            if ($marcaRegistro === "\x1A") {
                $cerrado = true; // marcador de fin de tabla
                break;
            }
            if ($marcaRegistro !== ' ' && $marcaRegistro !== '*') {
                break;
            }
            $cuenta++;
        }

        if ($cerrado && $cuenta > $mejorCuenta) {
            $mejorCuenta = $cuenta;
            $mejor = $inicio;
        }
    }

    if ($mejor === null || $mejorCuenta < 1) {
        return null;
    }
    return [$mejor, $mejorCuenta];
}

/**
 * Convierte el valor crudo de un campo DBF a texto legible segun su tipo.
 */
function visor_valor_dbf($crudo, $tipo, $decimales)
{
    $tipo = strtoupper((string)$tipo);

    switch ($tipo) {
        case 'D': // fecha AAAAMMDD
            $v = trim((string)$crudo);
            if (strlen($v) === 8 && ctype_digit($v)) {
                return substr($v, 6, 2) . '/' . substr($v, 4, 2) . '/' . substr($v, 0, 4);
            }
            // dBASE II guarda la fecha como MM/DD/AA: se pasa a DD/MM/AA.
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2})$#', $v, $m)) {
                return sprintf('%02d/%02d/%s', (int)$m[2], (int)$m[1], $m[3]);
            }
            return $v;

        case 'L': // logico
            $v = strtoupper(trim((string)$crudo));
            if (in_array($v, ['Y', 'T', 'S'], true))  return 'Si';
            if (in_array($v, ['N', 'F'], true))       return 'No';
            return $v === '?' || $v === '' ? '' : $v;

        case 'N':
        case 'F': // numerico
            $v = trim((string)$crudo);
            if ($v === '' || !is_numeric($v)) {
                return $v;
            }
            // Con decimales si se formatea; sin decimales se deja tal cual para
            // no meter separadores de miles en cuentas o identificadores.
            if ((int)$decimales > 0) {
                return number_format((float)$v, (int)$decimales, ',', '.');
            }
            return $v;

        case 'B': // double de 8 bytes (dBASE V / FoxPro)
            if (strlen((string)$crudo) === 8) {
                $d = @unpack('e', (string)$crudo);
                if (is_array($d) && is_finite($d[1])) {
                    return number_format($d[1], (int)$decimales ?: 4, ',', '.');
                }
            }
            return trim((string)$crudo);

        case 'I': // entero de 4 bytes (Visual FoxPro)
            if (strlen((string)$crudo) >= 4) {
                $n = unpack('l', substr((string)$crudo, 0, 4));
                return (string)$n[1];
            }
            return '';

        case 'Y': // moneda de 8 bytes (Visual FoxPro): 4 decimales fijos
            if (strlen((string)$crudo) >= 8) {
                $n = unpack('P', substr((string)$crudo, 0, 8));
                if (is_array($n)) {
                    return number_format($n[1] / 10000, 4, ',', '.');
                }
            }
            return '';

        case 'T': // fecha y hora (dBASE IV / FoxPro)
        case '@':
            if (strlen((string)$crudo) < 8) {
                return '';
            }
            $d = @unpack('e', substr((string)$crudo, 0, 8));
            if (!is_array($d) || !$d[1] || !is_finite($d[1])) {
                return '';
            }
            $dias = (int)floor($d[1]);
            $seg  = (int)round(($d[1] - $dias) * 86400);
            $jd   = $dias + 2415019; // epoch 30/11/1899

            $a = $jd + 32044;
            $b = intdiv(4 * $a + 3, 146097);
            $c = $a - intdiv(146097 * $b, 4);
            $e = intdiv(4 * $c + 3, 1461);
            $f = $c - intdiv(1461 * $e, 4);
            $m = intdiv(5 * $f + 2, 153);
            $dia   = $f - intdiv(153 * $m + 2, 5) + 1;
            $mes   = $m + 3 - 12 * intdiv($m, 10);
            $anio  = 100 * $b + $e - 4800 + intdiv($m, 10);

            if ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12 || $anio < 1 || $anio > 9999) {
                return '';
            }
            $fecha = sprintf('%02d/%02d/%04d', $dia, $mes, $anio);
            $seg   = max(0, min(86399, $seg));
            if ($seg === 0) {
                return $fecha;
            }
            return $fecha . ' ' . sprintf('%02d:%02d:%02d',
                intdiv($seg, 3600), intdiv($seg % 3600, 60), $seg % 60);

        case 'M': // memo / general / picture: el texto vive en .DBT / .FPT
        case 'G':
        case 'P':
            return '(memo)';

        default: // C (texto) y cualquier tipo desconocido
            return rtrim(visor_a_utf8((string)$crudo), " \x00");
    }
}

/**
 * Fecha de una entrada del ZIP (mtime del descriptor central).
 */
function visor_fecha_zip($mtime)
{
    $mtime = (int)$mtime;
    return $mtime > 0 ? date('d/m/Y H:i', $mtime) : '';
}

if ($extension === 'zip') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => 'El servidor no tiene habilitada la extension ZIP.']);
        exit();
    }

    $zip = new ZipArchive();
    $abierto = $zip->open($rutaArchivo);
    if ($abierto !== true) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'mensaje' => 'No se pudo abrir el ZIP (codigo de error ' . (int)$abierto . ').',
        ]);
        exit();
    }

    $totalEntradas = (int)$zip->numFiles;
    $recorridas    = min($totalEntradas, VISOR_LIMITE_RECORRIDO_ZIP);
    $flagsZip      = carpetas_zip_flags($rutaArchivo);
    $entradas      = [];
    $soloArchivos  = 0;
    $soloCarpetas  = 0;
    $bytesContenidos = 0;
    $comprimidos   = 0;

    for ($i = 0; $i < $recorridas; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) {
            continue;
        }

        $nombreCrudo = (string)($stat['name'] ?? '');
        if ($nombreCrudo === '') {
            continue;
        }

        // Un nombre terminado en separador es un directorio del ZIP.
        $esDir  = substr($nombreCrudo, -1) === '/' || substr($nombreCrudo, -1) === '\\';
        $nombre = str_replace('\\', '/', $nombreCrudo);
        $nombre = ltrim($nombre, '/');
        $nombre = rtrim($nombre, '/');
        $ruta   = dirname($nombre);
        $ruta   = $ruta === '.' || $ruta === '/' ? '' : $ruta;
        $base   = basename($nombre);
        if ($base === '.' || $base === '/' || $base === '') {
            continue;
        }

        $real       = (int)($stat['size'] ?? 0);
        $comprimido = (int)($stat['comp_size'] ?? 0);
        $fecha      = visor_fecha_zip($stat['mtime'] ?? 0);

        if ($esDir) {
            $soloCarpetas++;
        } else {
            $soloArchivos++;
            $bytesContenidos += $real;
            $comprimidos     += $comprimido;
        }

        if (count($entradas) >= VISOR_LIMITE_ENTRADAS) {
            continue;
        }

        $entradas[] = [
            'indice'     => $i,
            'nombre'     => $base,
            'archivo'    => $esDir ? '' : $base,
            'ruta'       => $ruta,
            'esDir'      => $esDir,
            'extension'  => $esDir ? '' : strtolower(pathinfo($base, PATHINFO_EXTENSION)),
            'previsualizable' => !$esDir &&
            in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd'], true),
            'cifrada'    => !empty($flagsZip['mapa'][$i]),
            'bytes'      => $real,
            'comprimido' => $comprimido,
            'ahorro'     => $real > 0 && $comprimido > 0
                ? max(0, min(99, (int)round((1 - $comprimido / $real) * 100)))
                : 0,
            'fecha'      => $fecha,
            'fecha_ts'   => (int)($stat['mtime'] ?? 0),
        ];
    }

    $zip->close();

    $ahorroTotal = 0;
    if ($bytesContenidos > 0 && $comprimidos > 0 && $comprimidos < $bytesContenidos) {
        $ahorroTotal = (int)round((1 - $comprimidos / $bytesContenidos) * 100);
    }

    $definicion  = carpetas_sistema_definicion();
    $descargaDir = $definicion[$carpetaSolicitada]['descargaDir'] ?? null;
    $descargaZip = null;
    if ($descargaDir !== null && !$hayEntradas) {
        $partes = $rutaRelativa === '' ? [] : explode('/', $rutaRelativa);
        $partes[] = $archivo;
        $descargaZip = $descargaDir . '/' . implode('/', array_map('rawurlencode', $partes));
    }

    logAction('dashboard', 'ver_archivo',
        'Lectura del indice de un archivo ZIP desde el explorador de carpetas',
        [
            'carpeta'   => $carpetaSolicitada,
            'archivo'   => $archivo,
            'bytes'     => $bytes,
            'entradas'  => $totalEntradas,
            'mostradas' => count($entradas),
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'          => true,
        'tipo'             => 'zip',
        'etiquetaFormato'  => 'ZIP · archivo comprimido',
        'carpeta'          => $carpetaSolicitada,
        'ruta'             => $rutaRelativa,
        'nombre'           => $archivo,
        'bytes'            => $bytes,
        'totalEntradas'    => $totalEntradas,
        'cifradas'         => (int)$flagsZip['cifradas'],
        'mostradas'        => count($entradas),
        'archivos'         => $soloArchivos,
        'carpetas'         => $soloCarpetas,
        'bytesContenidos'  => $bytesContenidos,
        'comprimidos'      => $comprimidos,
        'ahorro'           => $ahorroTotal,
        'entradas'         => $entradas,
        'truncado'         => count($entradas) < $totalEntradas,
        'descarga'         => $descargaZip,
        'csrf'             => carpetas_csrf_token(),
        'destinos'         => carpetas_zip_destinos($carpetaSolicitada),
        'puedeExtraer'     => carpetas_sistema_puede('abrir'),
        'puedeDescargar'   => carpetas_sistema_puede('descargar'),
        'puedeDescargarTodo' => carpetas_sistema_puede('descargar'),
    ]);
    exit();
}

if ($extension === 'rar') {
    require_once __DIR__ . '/../includes/rarlib/autoload.php';
    require_once __DIR__ . '/../includes/rarlib/unrar.php';

    $indiceRarDatos = rar_indice_completo($rutaArchivo, $password);
    if ($indiceRarDatos['ok'] !== true) {
        $motivoRar = $indiceRarDatos['motivo'] ?? 'error';
        if ($motivoRar === 'password') {
            if ($password === '') {
                http_response_code(401);
                echo json_encode(['success' => false, 'requierePassword' => true,
                    'codigo'  => 'password_requerida',
                    'nivel'   => count($entradasRuta),
                    'mensaje' => 'Este RAR tiene las cabeceras cifradas: falta la contraseña.']);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'passwordIncorrecta' => true,
                    'requierePassword' => true,
                    'codigo'  => 'password_incorrecta',
                    'nivel'   => count($entradasRuta),
                    'mensaje' => 'Contraseña incorrecta.']);
            }
            exit();
        }
        http_response_code(500);
        echo json_encode(['success' => false,
            'mensaje' => 'No se pudo abrir el RAR (formato no reconocido o dañado).']);
        exit();
    }

    $entradasRar = $indiceRarDatos['entradas'];
    $origenRar   = $indiceRarDatos['origen'] ?? 'lib';

    $totalEntradas   = count($entradasRar);
    $entradas        = [];
    $soloArchivos    = 0;
    $soloCarpetas    = 0;
    $bytesContenidos = 0;
    $comprimidos     = 0;
    $cifradasTotal   = 0;

    foreach ($entradasRar as $iRar => $entradaR) {
        $nombre = $entradaR['nombre'];
        $esDir  = $entradaR['esDir'];

        $ruta = dirname($nombre);
        $ruta = $ruta === '.' || $ruta === '/' ? '' : $ruta;
        $base = basename($nombre);
        if ($base === '.' || $base === '/' || $base === '') {
            continue;
        }

        $real       = (int)$entradaR['bytes'];
        $comprimido = (int)$entradaR['comprimido'];
        $fechaTs    = (int)$entradaR['fechaTs'];
        $cifrada    = $entradaR['cifrada'];

        if ($esDir) {
            $soloCarpetas++;
        } else {
            $soloArchivos++;
            $bytesContenidos += $real;
            $comprimidos     += $comprimido;
            if ($cifrada) {
                $cifradasTotal++;
            }
        }

        if (count($entradas) >= VISOR_LIMITE_ENTRADAS) {
            continue;
        }

        $extensionInterna = $esDir ? '' : strtolower(pathinfo($base, PATHINFO_EXTENSION));

        $entradas[] = [
            'indice'     => $iRar,
            'nombre'     => $base,
            'archivo'    => $esDir ? '' : $base,
            'ruta'       => $ruta,
            'esDir'      => $esDir,
            'extension'  => $extensionInterna,
            'previsualizable' => !$esDir &&
                in_array($extensionInterna, ['txt', 'sql', 'dbf', 'xml', 'csv', 'log', 'json', 'md', 'ps1', 'cmd'], true),
            'cifrada'    => $cifrada,
            'bytes'      => $real,
            'comprimido' => $comprimido,
            'ahorro'     => $real > 0 && $comprimido > 0
                ? max(0, min(99, (int)round((1 - $comprimido / $real) * 100)))
                : 0,
            'fecha'      => visor_fecha_zip($fechaTs),
            'fecha_ts'   => $fechaTs,
        ];
    }

    $ahorroTotal = 0;
    if ($bytesContenidos > 0 && $comprimidos > 0 && $comprimidos < $bytesContenidos) {
        $ahorroTotal = (int)round((1 - $comprimidos / $bytesContenidos) * 100);
    }

    $definicion  = carpetas_sistema_definicion();
    $descargaDir = $definicion[$carpetaSolicitada]['descargaDir'] ?? null;
    $descargaRar = null;
    if ($descargaDir !== null && !$hayEntradas) {
        $partes = $rutaRelativa === '' ? [] : explode('/', $rutaRelativa);
        $partes[] = $archivo;
        $descargaRar = $descargaDir . '/' . implode('/', array_map('rawurlencode', $partes));
    }

    logAction('dashboard', 'ver_archivo',
        'Lectura del indice de un archivo RAR desde el explorador de carpetas',
        [
            'carpeta'   => $carpetaSolicitada,
            'archivo'   => $archivo,
            'bytes'     => $bytes,
            'entradas'  => $totalEntradas,
            'mostradas' => count($entradas),
            'origen'    => $origenRar,
            'conClave'  => $password !== '',
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'          => true,
        'tipo'             => 'rar',
        'etiquetaFormato'  => 'RAR · archivo comprimido',
        'carpeta'          => $carpetaSolicitada,
        'ruta'             => $rutaRelativa,
        'nombre'           => $archivo,
        'bytes'            => $bytes,
        'totalEntradas'    => $totalEntradas,
        'cifradas'         => $cifradasTotal,
        'mostradas'        => count($entradas),
        'archivos'         => $soloArchivos,
        'carpetas'         => $soloCarpetas,
        'bytesContenidos'  => $bytesContenidos,
        'comprimidos'      => $comprimidos,
        'ahorro'           => $ahorroTotal,
        'entradas'         => $entradas,
        'truncado'         => count($entradas) < $totalEntradas,
        'descarga'         => $descargaRar,
        'csrf'             => carpetas_csrf_token(),
        'destinos'         => carpetas_zip_destinos($carpetaSolicitada),
        'puedeExtraer'     => carpetas_sistema_puede('abrir'),
        'puedeDescargar'   => carpetas_sistema_puede('descargar'),
        'puedeDescargarTodo' => carpetas_sistema_puede('descargar'),
    ]);
    exit();
}

if (in_array($extension, ['txt', 'sql', 'log', 'json', 'md', 'ps1', 'cmd'], true)) {
    $bruto = @file_get_contents($rutaArchivo);
    if ($bruto === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => 'No se pudo leer el archivo.']);
        exit();
    }

    $truncado = false;
    if (strlen($bruto) > VISOR_LIMITE_TEXTO) {
        $bruto = substr($bruto, 0, VISOR_LIMITE_TEXTO);
        // Se corta en un salto de linea para no partir una linea a la mitad.
        $corte = strrpos($bruto, "\n");
        if ($corte !== false && $corte > VISOR_LIMITE_TEXTO / 2) {
            $bruto = substr($bruto, 0, $corte + 1);
        }
        $truncado = true;
    }

    $contenido = visor_a_utf8($bruto);
    $lineas = $contenido === '' ? 0 : substr_count($contenido, "\n") + (substr($contenido, -1) === "\n" ? 0 : 1);

    logAction('dashboard', 'ver_archivo',
        'Lectura de archivo de texto desde el explorador de carpetas',
        ['carpeta' => $carpetaSolicitada, 'archivo' => $archivo, 'bytes' => $bytes, 'lineas' => $lineas],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'   => true,
        'tipo'      => 'txt',
        'etiquetaFormato' => [
            'sql'  => 'SQL · script de base de datos',
            'log'  => 'LOG · archivo de registro',
            'json' => 'JSON · datos estructurados',
            'md'   => 'MD · documento Markdown',
            'ps1'  => 'PS1 · script de PowerShell',
            'cmd'  => 'CMD · archivo por lotes',
        ][$extension] ?? 'TXT · texto plano',
        'carpeta'   => $carpetaSolicitada,
        'nombre'    => $archivo,
        'bytes'     => $bytes,
        'lineas'    => $lineas,
        'contenido' => $contenido,
        'truncado'  => $truncado,
    ]);
    exit();
}

/**
 * Carga un XML en un DOMDocument sin mostrar los avisos de libxml.
 * Devuelve null si no es XML valido; $error recibe el primer motivo.
 */
function visor_xml_intentar($texto, &$error)
{
    $error = '';
    if (trim((string)$texto) === '') {
        $error = 'El archivo esta vacio.';
        return null;
    }

    $previo = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->preserveWhiteSpace = false;
    $cargado = @$doc->loadXML((string)$texto, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);

    if (!$cargado || $doc->documentElement === null) {
        $errores = libxml_get_errors();
        $error = $errores ? trim($errores[0]->message) : 'El archivo no es un XML valido.';
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        return null;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previo);
    return $doc;
}

if ($extension === 'xml') {
    $bruto = @file_get_contents($rutaArchivo);
    if ($bruto === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => 'No se pudo leer el archivo.']);
        exit();
    }

    $truncado   = false;
    $formateado = false;
    $errorXml   = '';
    $raiz       = '';
    $nodos      = 0;
    $contenido  = '';
    $lineas     = 0;

    if (strlen($bruto) > VISOR_LIMITE_XML) {
        // Demasiado grande para formatear: se muestra el texto tal cual.
        $truncado  = true;
        $contenido = visor_a_utf8(substr($bruto, 0, VISOR_LIMITE_TEXTO));
        $errorXml  = 'El archivo supera 1 MB y se muestra sin formatear.';
        $lineas    = substr_count($contenido, "\n") + 1;
    } else {
        $doc = visor_xml_intentar($bruto, $errorXml);

        // Sin declaracion de codificacion (o mal declarada) el parser se
        // queja; se reintenta con el texto ya convertido a UTF-8.
        if ($doc === null) {
            $utf8 = visor_a_utf8($bruto);
            if ($utf8 !== $bruto) {
                $doc = visor_xml_intentar($utf8, $errorXml);
            }
        }

        if ($doc !== null) {
            $doc->encoding  = 'UTF-8';
            $doc->formatOutput = true;
            $salida = $doc->saveXML();

            if ($salida !== false && $salida !== '') {
                $contenido  = $salida;
                $formateado = true;
                $errorXml   = '';
                $raiz       = $doc->documentElement->tagName;
                $nodos      = $doc->getElementsByTagName('*')->length;
                $lineas     = substr_count($contenido, "\n") + (substr($contenido, -1) === "\n" ? 0 : 1);
            }
        }

        if (!$formateado) {
            $contenido = visor_a_utf8($bruto);
            $lineas = $contenido === '' ? 0
                : substr_count($contenido, "\n") + (substr($contenido, -1) === "\n" ? 0 : 1);
        }
    }

    logAction('dashboard', 'ver_archivo',
        'Lectura de archivo XML desde el explorador de carpetas',
        [
            'carpeta'     => $carpetaSolicitada,
            'archivo'     => $archivo,
            'bytes'       => $bytes,
            'formateado'  => $formateado ? 1 : 0,
            'nodos'       => $nodos,
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'    => true,
        'tipo'       => 'xml',
        'etiquetaFormato' => 'XML · documento',
        'carpeta'    => $carpetaSolicitada,
        'nombre'     => $archivo,
        'bytes'      => $bytes,
        'contenido'  => $contenido,
        'lineas'     => $lineas,
        'formateado' => $formateado,
        'truncado'   => $truncado,
        'raiz'       => $raiz,
        'nodos'      => $nodos,
        'error'      => $errorXml !== '' ? $errorXml : null,
    ]);
    exit();
}

/**
 * Puntua los separadores habituales (, ; tab |) con las primeras lineas: gana
 * el que mas veces consigue el mismo ancho de fila y con dos columnas o mas
 * (una sola columna significa que el separador no aparece en el archivo).
 * Asi un informe con lineas de titulo delante no obliga a caer en la coma.
 */
function visor_csv_separador($texto)
{
    $lineas = [];
    foreach (explode("\n", $texto) as $linea) {
        $linea = rtrim($linea, "\r");
        if (trim($linea) !== '') {
            $lineas[] = $linea;
        }
        if (count($lineas) >= 40) {
            break;
        }
    }
    if (!$lineas) {
        return ',';
    }

    $mejor      = ',';
    $mejorRatio = -1.0;
    $mejorAncho = 0;

    foreach ([',', ';', "\t", '|'] as $sep) {
        $frecuencias = [];
        foreach ($lineas as $linea) {
            $n = count(str_getcsv($linea, $sep));
            $frecuencias[$n] = ($frecuencias[$n] ?? 0) + 1;
        }

        $anchoModal   = 0;
        $repeticiones = 0;
        foreach ($frecuencias as $ancho => $veces) {
            if ($veces > $repeticiones || ($veces === $repeticiones && $ancho > $anchoModal)) {
                $anchoModal   = (int)$ancho;
                $repeticiones = $veces;
            }
        }
        if ($anchoModal < 2) {
            continue;
        }

        $ratio = $repeticiones / count($lineas);
        if ($ratio > $mejorRatio || ($ratio === $mejorRatio && $anchoModal > $mejorAncho)) {
            $mejor      = $sep;
            $mejorRatio = $ratio;
            $mejorAncho = $anchoModal;
        }
    }

    return $mejor;
}

/**
 * Ancho de fila mas frecuente (moda); en empate manda el mas ancho.
 */
function visor_csv_ancho_modal($filas)
{
    $frecuencias = [];
    foreach ($filas as $celdas) {
        $n = count($celdas);
        $frecuencias[$n] = ($frecuencias[$n] ?? 0) + 1;
    }

    $anchoModal   = 0;
    $repeticiones = 0;
    foreach ($frecuencias as $ancho => $veces) {
        if ($veces > $repeticiones || ($veces === $repeticiones && $ancho > $anchoModal)) {
            $anchoModal   = (int)$ancho;
            $repeticiones = $veces;
        }
    }
    return max(1, $anchoModal);
}

if ($extension === 'csv') {
    $bruto = @file_get_contents($rutaArchivo);
    if ($bruto === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => 'No se pudo leer el archivo.']);
        exit();
    }

    $truncado = strlen($bruto) > VISOR_LIMITE_TEXTO;
    if ($truncado) {
        $bruto = substr($bruto, 0, VISOR_LIMITE_TEXTO);
    }

    $texto = visor_a_utf8($bruto);
    $separador = visor_csv_separador($texto);

    // php://temp + fgetcsv respeta comillas, saltos de linea internos y CRLF.
    $flujo = fopen('php://temp', 'r+');
    fwrite($flujo, $texto);
    rewind($flujo);

    // Se guardan las primeras filas (para buscar la cabecera) y el resto solo
    // se cuenta, sin quedarse en memoria.
    $filasBrutas = [];
    $totalFilas  = 0;

    while (($registro = fgetcsv($flujo, 0, $separador)) !== false) {
        if ($registro === [null] || $registro === false) {
            continue; // linea en blanco
        }

        $celdas = [];
        foreach ($registro as $celda) {
            $celdas[] = is_string($celda) ? trim($celda) : $celda;
        }

        $totalFilas++;
        if (count($filasBrutas) < VISOR_LIMITE_LECTURA_CSV) {
            $filasBrutas[] = $celdas;
        }
    }
    fclose($flujo);

    // Los exportes de nomina traen antes lineas de titulo del informe; la
    // fila de encabezados es la primera del ancho habitual cuyos valores son
    // texto (no numeros) y a la que sigue otra fila de datos.
    $anchoModal    = visor_csv_ancho_modal($filasBrutas);
    $indiceCabecera = -1;
    $limiteBusqueda = min(30, count($filasBrutas));

    for ($i = 0; $i < $limiteBusqueda; $i++) {
        $celdas = $filasBrutas[$i];
        if (count($celdas) !== $anchoModal) {
            continue;
        }
        if (!isset($filasBrutas[$i + 1]) || count($filasBrutas[$i + 1]) !== $anchoModal) {
            continue;
        }

        $sinVacias = array_filter($celdas, function ($v) { return $v !== '' && $v !== null; });
        if (count($sinVacias) < 2) {
            continue;
        }
        $soloNumeros = count(array_filter($sinVacias, function ($v) { return is_numeric($v); }))
            === count($sinVacias);
        if ($soloNumeros) {
            continue;
        }

        $indiceCabecera = $i;
        break;
    }

    $preambulo = 0;
    $cabecera  = [];
    if ($indiceCabecera >= 0) {
        $cabecera  = $filasBrutas[$indiceCabecera];
        $preambulo = $indiceCabecera;
        // Se muestran TODAS las lineas: las de titulo del informe van primero y
        // la de encabezados queda como cabecera de la tabla. No se descarta nada.
        $filas = array_merge(
            array_slice($filasBrutas, 0, $indiceCabecera),
            array_slice($filasBrutas, $indiceCabecera + 1)
        );
        $totalFilas = max(0, $totalFilas - 1);
    } else {
        $filas = $filasBrutas;
    }

    // Si alguna linea trae mas celdas que la cabecera, se amplia esta con
    // campos nuevos para no perder ninguna celda del archivo.
    $anchoMax = 0;
    foreach ($filas as $fila) {
        if (is_array($fila) && count($fila) > $anchoMax) {
            $anchoMax = count($fila);
        }
    }
    while (count($cabecera) < $anchoMax) {
        $cabecera[] = '';
    }

    $mostradas = min($totalFilas, VISOR_LIMITE_FILAS_CSV);
    $filas     = array_slice($filas, 0, VISOR_LIMITE_FILAS_CSV);

    // Nombres de columna unicos y legibles (sin vacios ni repetidos).
    $usados = [];
    foreach ($cabecera as $indice => $nombre) {
        $nombre = trim((string)$nombre);
        if ($nombre === '') {
            $nombre = 'Campo ' . ($indice + 1);
        }
        $base = $nombre;
        $intento = 2;
        while (isset($usados[$nombre])) {
            $nombre = $base . ' (' . $intento . ')';
            $intento++;
        }
        $usados[$nombre] = true;
        $cabecera[$indice] = $nombre;
    }

    if (!$cabecera) {
        $columnas = $filas && is_array($filas[0]) ? count($filas[0]) : 0;
        for ($i = 1; $i <= $columnas; $i++) {
            $cabecera[] = 'Campo ' . $i;
        }
    }

    // Tipo y ancho por columna segun los valores mostrados.
    $campos = [];
    foreach ($cabecera as $indice => $nombre) {
        $maxLargo = strlen((string)$nombre);
        $numerica = true;
        $conDatos = false;

        foreach ($filas as $fila) {
            $valor = is_array($fila) && array_key_exists($indice, $fila) ? trim((string)$fila[$indice]) : '';
            if ($valor !== '') {
                $conDatos = true;
                if (!is_numeric($valor)) {
                    $numerica = false;
                }
            }
            if (strlen($valor) > $maxLargo) {
                $maxLargo = strlen($valor);
            }
        }

        $campos[] = [
            'nombre'    => (string)$nombre,
            'tipo'      => $conDatos && $numerica ? 'Num&eacute;rico' : 'Texto',
            'largo'     => $maxLargo,
            'decimales' => 0,
        ];
    }

    // Cada fila como objeto nombre => valor (mismo formato que el DBF).
    $filasFinales = [];
    foreach ($filas as $fila) {
        $objeto = [];
        foreach ($cabecera as $indice => $nombre) {
            $objeto[$nombre] = is_array($fila) && array_key_exists($indice, $fila)
                ? (string)$fila[$indice]
                : '';
        }
        $filasFinales[] = $objeto;
    }

    $textoSeparador = [',' => 'coma', ';' => 'punto y coma', "\t" => 'tabulador', '|' => 'pipe'][$separador] ?? $separador;
    $simboloSeparador = $separador === "\t" ? 'tab' : $separador;

    logAction('dashboard', 'ver_archivo',
        'Lectura de archivo CSV desde el explorador de carpetas',
        [
            'carpeta'   => $carpetaSolicitada,
            'archivo'   => $archivo,
            'bytes'     => $bytes,
            'separador' => $separador,
            'registros' => $totalFilas,
            'preambulo' => $preambulo,
        ],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'          => true,
        'tipo'             => 'csv',
        'etiquetaFormato'  => 'CSV · separado por ' . $textoSeparador . ' (' . $simboloSeparador . ')',
        'carpeta'          => $carpetaSolicitada,
        'nombre'           => $archivo,
        'bytes'            => $bytes,
        'delimitador'      => $separador === "\t" ? '\t' : $separador,
        'delimitadorTexto' => $textoSeparador,
        'preambulo'        => $preambulo,
        'campos'           => $campos,
        'filas'            => $filasFinales,
        'totalRegistros'   => $totalFilas,
        'mostrados'        => $mostradas,
        'truncado'         => $truncado || $totalFilas > $mostradas,
    ]);
    exit();
}

// ==========================
// .dbf
// ==========================
$fp = @fopen($rutaArchivo, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo abrir el archivo.']);
    exit();
}

$cabecera = fread($fp, 32);
if ($cabecera === false || strlen($cabecera) < 32) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'El archivo DBF esta vacio o es invalido.']);
    exit();
}

$version      = ord($cabecera[0]);
$formato      = visor_dbf_formato($version);
$tamanoArchivo = (int)@filesize($rutaArchivo);

// dBASE II (0x02): cabecera de 8 bytes (cuenta en 1-2, ancho de registro en
// 6-7), descriptores de campo de 16 bytes y sin indicar donde empiezan los
// datos. El resto de versiones usan la cabecera estandar de 32 bytes.
$esDbaseII = $version === 0x02;

if ($esDbaseII) {
    $registrosTot = unpack('v', substr($cabecera, 1, 2))[1];
    $recordLen    = unpack('v', substr($cabecera, 6, 2))[1];
    $headerLen    = 0; // se calcula al localizar el inicio de los registros
} else {
    $registrosTot = unpack('V', substr($cabecera, 4, 4))[1];
    $headerLen    = unpack('v', substr($cabecera, 8, 2))[1];
    $recordLen    = unpack('v', substr($cabecera, 10, 2))[1];
}
$fechaDbf = visor_dbf_fecha($cabecera);

if (!$esDbaseII && ($headerLen < 33 || $headerLen > $tamanoArchivo)) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Cabecera DBF invalida.']);
    exit();
}
if ($recordLen < 1 || $recordLen > $tamanoArchivo) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Cabecera DBF invalida.']);
    exit();
}

$camposBrutos = [];

/**
 * Nombre de campo desde un descriptor: es una cadena de 11 bytes terminada
 * en NUL (algunos generadores dejan basura detras del primer \0); se corta
 * ahi y se quitan los espacios de relleno del final.
 */
function visor_dbf_nombre_descriptor($desc)
{
    $nombre = substr($desc, 0, 11);
    $cero = strpos($nombre, "\x00");
    if ($cero !== false) {
        $nombre = substr($nombre, 0, $cero);
    }
    return rtrim($nombre, " \x00");
}

if ($esDbaseII) {
    // Descriptores de 16 bytes (nombre 11 + tipo 1 + ancho 1 + 3 de relleno)
    // que arrancan justo tras la cabecera de 8 y terminan en 0x0D.
    fseek($fp, 8);
    $bloque = (string)fread($fp, 16 * 64); // hasta 64 campos
    $finDescriptores = 8;

    for ($i = 0; $i + 16 <= strlen($bloque); $i += 16) {
        $desc = substr($bloque, $i, 16);
        if (ord($desc[0]) === 0x0D) {
            $finDescriptores = 8 + $i + 1;
            break;
        }
        $nombre = visor_dbf_nombre_descriptor($desc);
        if ($nombre === '') {
            $finDescriptores = 8 + $i;
            break;
        }
        $camposBrutos[] = [
            'nombre'    => $nombre,
            'tipo'      => $desc[11],
            'largo'     => ord($desc[12]),
            'decimales' => 0,
        ];
        $finDescriptores = 8 + $i + 16;
    }

    if ($camposBrutos) {
        $inicioDatos = visor_dbf_inicio_datos($fp, $finDescriptores, $recordLen, $tamanoArchivo);
        if ($inicioDatos === null) {
            fclose($fp);
            http_response_code(400);
            echo json_encode(['success' => false,
                'mensaje' => 'No se pudo localizar el inicio de los registros (variante dBASE II no reconocida).']);
            exit();
        }
        list($headerLen, $escaneados) = $inicioDatos;
        if ($escaneados > 0) {
            // La cuenta real del archivo manda sobre la de la cabecera.
            $registrosTot = $escaneados;
        }
    }
} else {
    // Descriptores de campo: 32 bytes cada uno, desde 32 hasta headerLen - 1
    // (el ultimo byte es el terminador 0x0D, que se salta).
    $bloque = (string)fread($fp, $headerLen - 32);
    for ($i = 0; $i + 32 <= strlen($bloque); $i += 32) {
        $desc = substr($bloque, $i, 32);
        if (ord($desc[0]) === 0x0D) {
            break;
        }
        $nombre = visor_dbf_nombre_descriptor($desc);
        if ($nombre === '') {
            break;
        }
        $camposBrutos[] = [
            'nombre'     => $nombre,
            'tipo'       => $desc[11],
            'largo'      => ord($desc[16]),
            'decimales'  => ord($desc[17]),
        ];
    }
}

if ($esDbaseII && $headerLen < 8) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo interpretar la estructura dBASE II.']);
    exit();
}

// Solo se aceptan descriptores con nombre real, tipo conocido y ancho > 0.
// El nombre admite espacios, dos puntos y letras fuera del alfabeto (casos
// reales: "LEISTNR NR", "EMP:NMBR", "ШАР"); se rechazan los que traen
// caracteres de control, propios de descriptores corruptos.
$campos = [];
foreach ($camposBrutos as $campo) {
    $tipoOk = $campo['tipo'] !== '' && strpos('CNFDLMIBOT@GVY', strtoupper($campo['tipo'])) !== false;
    $nombreOk = $campo['nombre'] !== ''
        && !preg_match('/[\x00-\x1F\x7F]/', $campo['nombre'])
        && preg_match('/[A-Za-z0-9\xC0-\xFF]/', $campo['nombre']) === 1;
    if ($tipoOk && $nombreOk && $campo['largo'] > 0 && $campo['largo'] <= $recordLen) {
        $campo['nombre'] = visor_a_utf8($campo['nombre']); // puede venir en cp1251
        $campos[] = $campo;
    }
}

// Campos memo: su texto vive en el .dbt / .fpt y se pide aparte al hacer clic.
$camposMemo = [];
foreach ($campos as $campo) {
    if (in_array(strtoupper($campo['tipo']), ['M', 'G', 'P'], true)) {
        $camposMemo[] = $campo['nombre'];
    }
}

// Los anchos deben sumar el registro completo: si no, la estructura esta rota.
$aviso = null;
$camposCrudos = false;
$sumaLargos = 1;
foreach ($campos as $campo) {
    $sumaLargos += $campo['largo'];
}

if (empty($campos) || $sumaLargos !== $recordLen) {
    $campos = [[
        'nombre'     => 'Registro',
        'tipo'       => 'Texto',
        'largo'      => max(1, $recordLen - 1),
        'decimales'  => 0,
    ]];
    $camposCrudos = true;
    $aviso = 'Este DBF no tiene una definicion de campos valida (estructura dañada o no estandar), '
           . 'por lo que se muestra el contenido crudo de cada registro.';
}

// Cuantos registros caben realmente en el archivo (protege de cabeceras rotas).
$maximoReal = $recordLen > 0 ? (int)floor(($tamanoArchivo - $headerLen) / $recordLen) : 0;
$registros  = max(0, min((int)$registrosTot, $maximoReal));

fseek($fp, $headerLen);

$filas = [];
$eliminados = 0;
$mostrados = 0;

for ($r = 0; $r < $registros; $r++) {
    $registro = (string)fread($fp, $recordLen);
    if (strlen($registro) < $recordLen) {
        break;
    }

    // Primer byte: ' ' = activo, '*' = marcado como borrado.
    if ($registro[0] === '*') {
        $eliminados++;
        continue;
    }
    if (!$camposCrudos && $registro[0] !== ' ' && $registro[0] !== "\x00") {
        continue;
    }

    if ($mostrados >= VISOR_LIMITE_FILAS) {
        continue; // se sigue recorriendo para contar los borrados
    }

    if ($camposCrudos) {
        $fila = ['Registro' => visor_texto_crudo(substr($registro, 1))];
    } else {
        $posicion = 1;
        $fila = [];
        foreach ($campos as $campo) {
            if ($posicion + $campo['largo'] > $recordLen) {
                break;
            }
            $fila[$campo['nombre']] = visor_valor_dbf(
                substr($registro, $posicion, $campo['largo']),
                $campo['tipo'],
                $campo['decimales']
            );
            $posicion += $campo['largo'];
        }
    }

    if ($camposMemo && !$camposCrudos) {
        $fila['__reg'] = $r; // posicion real del registro en el archivo
    }

    $filas[] = $fila;
    $mostrados++;
}

fclose($fp);

logAction('dashboard', 'ver_archivo',
    'Lectura de archivo DBF desde el explorador de carpetas',
    [
        'carpeta'   => $carpetaSolicitada,
        'archivo'   => $archivo,
        'bytes'     => $bytes,
        'registros' => $registros,
        'campos'    => count($campos),
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success'         => true,
    'tipo'            => 'dbf',
    'carpeta'         => $carpetaSolicitada,
    'nombre'          => $archivo,
    'bytes'           => $bytes,
    'fecha'           => $fechaDbf,
    'version'         => $version,
    'formato'         => $formato['nombre'],
    'formatoHex'      => $formato['hex'],
    'generador'       => $formato['generador'],
    'etiquetaFormato' => 'DBF · ' . $formato['nombre'],
        'totalRegistros'  => $registros,
        'mostrados'       => $mostrados,
        'eliminados'      => $eliminados,
        'campos'          => $campos,
        'camposMemo'      => $camposMemo,
        'filas'           => $filas,
        'aviso'           => $aviso,
        'truncado'        => $mostrados < max(0, $registros - $eliminados),
    ]);
