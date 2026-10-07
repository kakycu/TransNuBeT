<?php
// modules/ver_memo.php - Devuelve el texto de un campo memo de un DBF
//
// Los campos memo (M) no guardan el texto en la tabla: lo dejan en un archivo
// hermano .dbt (dBASE III/IV) o .fpt (FoxPro / Visual FoxPro). Este endpoint
// localiza ese archivo, resuelve el bloque indicado por el registro y devuelve
// el contenido listo para pintar en el modal.
// Acceso: mismos permisos que el visor de archivos.
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once '../includes/permisos.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

define('MEMO_LIMITE', 512 * 1024); // 512 KB de memo leidos como maximo

/**
 * Convierte bytes a UTF-8 (mismo criterio que el visor de archivos).
 */
function memo_a_utf8($texto)
{
    if ($texto === '' || $texto === false) {
        return '';
    }
    if (mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }
    $convertido = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    return $convertido !== false ? $convertido : $texto;
}

/**
 * Limpia el texto de un memo sin perder los saltos de linea.
 */
function memo_limpiar($texto)
{
    $texto = str_replace("\x1A", "\n", (string)$texto);
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', (string)$texto);
    $texto = preg_replace('/[ \t]+/', ' ', (string)$texto);
    $texto = preg_replace('/\n{4,}/', "\n\n\n", (string)$texto);
    return memo_a_utf8(trim((string)$texto));
}

/**
 * Archivo de memo hermano del DBF (.dbt o .fpt), sin distinguir mayusculas.
 */
function memo_buscar_archivo($rutaDbf)
{
    $dir = dirname($rutaDbf);
    $base = pathinfo($rutaDbf, PATHINFO_FILENAME);
    $patron = '/^' . preg_quote($base, '/') . '\.(dbt|fpt)$/i';

    foreach ((array)@scandir($dir) as $entrada) {
        if ($entrada === '.' || $entrada === '..') {
            continue;
        }
        if (preg_match($patron, $entrada)) {
            return $dir . DIRECTORY_SEPARATOR . $entrada;
        }
    }
    return null;
}

/**
 * Bloque indicado por el valor crudo del campo memo.
 * dBASE guarda el numero de bloque en ASCII; FoxPro lo guarda como double.
 */
function memo_bloque_desde_campo($crudo)
{
    $texto = trim((string)$crudo);
    if ($texto !== '' && ctype_digit($texto)) {
        return (int)$texto;
    }
    if (strlen((string)$crudo) >= 8) {
        $d = @unpack('e', (string)$crudo);
        if (is_array($d) && $d[1] >= 1 && $d[1] < 1e9) {
            return (int)$d[1];
        }
    }
    if ($texto !== '' && ctype_xdigit($texto)) {
        return (int)hexdec($texto);
    }
    return 0;
}

/**
 * Lee el texto del bloque. Devuelve [texto, aviso].
 */
function memo_leer($rutaMemo, $bloque)
{
    if ($bloque < 1) {
        return [null, 'El campo memo esta vacio o apunta al bloque 0.'];
    }

    $tamanio = (int)@filesize($rutaMemo);
    $esFpt = strtolower(pathinfo($rutaMemo, PATHINFO_EXTENSION)) === 'fpt';

    $fh = @fopen($rutaMemo, 'rb');
    if ($fh === false) {
        return [null, 'No se pudo abrir el archivo de memo.'];
    }

    if ($esFpt) {
        // Cabecera .fpt: 2 bytes con el ancho de bloque (grande primero).
        $cabecera = (string)fread($fh, 8);
        if (strlen($cabecera) < 8) {
            fclose($fh);
            return [null, 'El archivo .fpt esta vacio.'];
        }
        $anchoBloque = unpack('n', substr($cabecera, 0, 2))[1];
        if ($anchoBloque < 16 || $anchoBloque > 65535) {
            $anchoBloque = 64;
        }

        $offset = $bloque * $anchoBloque;
        if ($offset + 8 > $tamanio) {
            fclose($fh);
            return [null, 'El bloque del memo esta fuera del archivo .fpt.'];
        }
        fseek($fh, $offset);

        $cabBloque = (string)fread($fh, 8);
        $tipo  = unpack('N', substr($cabBloque, 0, 4))[1];
        $largo = unpack('N', substr($cabBloque, 4, 4))[1];
        if ($largo <= 0 || $largo > MEMO_LIMITE) {
            fclose($fh);
            return [null, 'El .fpt reporta una longitud de memo no valida.'];
        }
        $datos = (string)fread($fh, $largo);
        fclose($fh);

        $aviso = null;
        if ($tipo !== 1) {
            $aviso = 'Este memo no es de texto (tipo ' . $tipo . '); se muestra su contenido igual.';
        }
        return [memo_limpiar($datos), $aviso];
    }

    // .dbt: bloques de 512 bytes (dBASE III) o con firma 0x0008FFFF (dBASE IV)
    $offset = $bloque * 512;
    if ($offset <= 0 || $offset >= $tamanio) {
        fclose($fh);
        return [null, 'El bloque del memo esta fuera del archivo .dbt.'];
    }
    fseek($fh, $offset);
    $datos = (string)stream_get_contents($fh, min(MEMO_LIMITE, $tamanio - $offset));
    fclose($fh);

    if ($datos === '') {
        return [null, 'No hay datos en el bloque del memo.'];
    }

    if (substr($datos, 0, 4) === "\xFF\xFF\x08\x00") {
        // dBASE IV: firma de 4 bytes + longitud en little endian
        $largo = unpack('V', substr($datos, 4, 4))[1];
        $texto = $largo > 0 ? substr($datos, 8, min($largo, MEMO_LIMITE)) : '';
    } else {
        // dBASE III: el texto ocupa el bloque y termina en 0x1A
        $fin = strpos($datos, "\x1A");
        $texto = $fin !== false ? substr($datos, 0, $fin) : $datos;
    }

    return [memo_limpiar($texto), null];
}

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

$rutaArchivo = $rutaCarpeta . DIRECTORY_SEPARATOR . $archivo;
$real = realpath($rutaArchivo);
if ($real === false || !is_file($real) || strpos($real, realpath($rutaCarpeta)) !== 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe.']);
    exit();
}
$rutaArchivo = $real;

$registro = filter_input(INPUT_POST, 'registro', FILTER_VALIDATE_INT);
$campoSolicitado = trim((string)($_POST['campo'] ?? ''));

if ($registro === false || $registro === null || $registro < 0 || $campoSolicitado === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Debe indicar el registro y el campo memo.']);
    exit();
}

if (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'dbf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Los memos solo aplican a archivos DBF.']);
    exit();
}

// --- Cabecera y descriptores del DBF ---
$fp = @fopen($rutaArchivo, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo abrir el archivo.']);
    exit();
}

$cabecera = (string)fread($fp, 32);
if (strlen($cabecera) < 32) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Cabecera DBF invalida.']);
    exit();
}

$registrosTot = unpack('V', substr($cabecera, 4, 4))[1];
$headerLen    = unpack('v', substr($cabecera, 8, 2))[1];
$recordLen    = unpack('v', substr($cabecera, 10, 2))[1];

if ($headerLen < 33 || $recordLen < 1) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Cabecera DBF invalida.']);
    exit();
}

$bloqueDesc = (string)fread($fp, $headerLen - 32);
$campos = [];
for ($i = 0; $i + 32 <= strlen($bloqueDesc); $i += 32) {
    $desc = substr($bloqueDesc, $i, 32);
    if (ord($desc[0]) === 0x0D) {
        break;
    }
        $nombre = substr($desc, 0, 11);
        $cero = strpos($nombre, "\x00");
        if ($cero !== false) {
            $nombre = substr($nombre, 0, $cero);
        }
        $nombre = rtrim($nombre, " \x00");
        if ($nombre === '') {
            break;
        }
        $campos[] = [
            'nombre' => memo_a_utf8($nombre),
            'tipo'   => $desc[11],
            'largo'  => ord($desc[16]),
            'offset' => 0,
        ];
}

$offsetDentroRegistro = 1;
$campoEncontrado = null;
foreach ($campos as $clave => $campo) {
    $campos[$clave]['offset'] = $offsetDentroRegistro;
    if (strcasecmp($campo['nombre'], $campoSolicitado) === 0) {
        $campoEncontrado = $campos[$clave];
    }
    $offsetDentroRegistro += $campo['largo'];
}

if ($campoEncontrado === null) {
    fclose($fp);
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El campo no existe en la tabla.']);
    exit();
}

if (!in_array(strtoupper($campoEncontrado['tipo']), ['M', 'G', 'P'], true)) {
    fclose($fp);
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'El campo indicado no es de tipo memo.']);
    exit();
}

$tamanoArchivo = (int)@filesize($rutaArchivo);
$maximoReal = (int)floor(($tamanoArchivo - $headerLen) / $recordLen);
if ($registro >= min((int)$registrosTot, $maximoReal)) {
    fclose($fp);
    http_response_code(404);
    echo json_encode(['success' => false, 'mensaje' => 'El registro no existe en la tabla.']);
    exit();
}

fseek($fp, $headerLen + $registro * $recordLen);
$registroCrudo = (string)fread($fp, $recordLen);
fclose($fp);

if (strlen($registroCrudo) < $recordLen) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo leer el registro.']);
    exit();
}

if ($campoEncontrado['offset'] + $campoEncontrado['largo'] > $recordLen) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'El campo memo excede el ancho del registro.']);
    exit();
}

$valorCrudo = substr($registroCrudo, $campoEncontrado['offset'], $campoEncontrado['largo']);
$bloque = memo_bloque_desde_campo($valorCrudo);

$rutaMemo = memo_buscar_archivo($rutaArchivo);
if ($rutaMemo === null) {
    $faltante = pathinfo($rutaArchivo, PATHINFO_FILENAME) . '.dbt / .fpt';
    logAction('dashboard', 'ver_memo', 'Memo sin archivo hermano',
        ['carpeta' => $carpetaSolicitada, 'archivo' => $archivo, 'campo' => $campoSolicitado],
        null, 'error', 'No se encontro ' . $faltante, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'  => false,
        'mensaje'  => 'No se encontro el archivo de memo junto al DBF (se esperaba ' . $faltante . ').',
    ]);
    exit();
}

list($texto, $aviso) = memo_leer($rutaMemo, $bloque);

if ($texto === null) {
    logAction('dashboard', 'ver_memo', 'Memo ilegible',
        ['carpeta' => $carpetaSolicitada, 'archivo' => $archivo, 'campo' => $campoSolicitado,
         'registro' => $registro, 'bloque' => $bloque],
        null, 'error', $aviso, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode(['success' => false, 'mensaje' => $aviso ?: 'No se pudo leer el memo.']);
    exit();
}

$truncado = $bloque > 0 && strlen($texto) >= MEMO_LIMITE - 1;

logAction('dashboard', 'ver_memo', 'Lectura de campo memo desde el visor de carpetas',
    ['carpeta' => $carpetaSolicitada, 'archivo' => $archivo, 'campo' => $campoSolicitado,
     'registro' => $registro, 'bloque' => $bloque, 'bytes' => strlen($texto)],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success'   => true,
    'tipo'      => 'memo',
    'nombre'    => $archivo,
    'carpeta'   => $carpetaSolicitada,
    'campo'     => $campoEncontrado['nombre'],
    'registro'  => $registro + 1,
    'contenido' => $texto,
    'bytes'     => strlen($texto),
    'lineas'    => substr_count($texto, "\n") + 1,
    'fuente'    => basename($rutaMemo),
    'aviso'     => $aviso,
    'truncado'  => $truncado,
]);
exit();
