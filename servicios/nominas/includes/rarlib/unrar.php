<?php
// includes/rarlib/unrar.php - Ejecucion controlada de UnRAR.exe
//
// La libreria rarlib solo LEE el indice de un RAR; para descomprimir (una
// entrada o todo el archivo) se invoca el UnRAR de WinRAR con proc_open y
// array de argumentos (sin shell: no hay riesgo de inyeccion de comandos).
// La password viaja como argumento -p<valor> al proceso hijo: nunca se
// registra ni se expone en la URL.
//
// Codigos de salida de UnRAR: 0 = OK, 1 = aviso (se trata como OK), 2 = error
// fatal, 3 = CRC incorrecto o contrasena mala (se distingue por el texto),
// 10 = el patron no coincide con ninguna entrada, 11 = contrasena incorrecta,
// 255 = pidio contrase\u00f1a por stdin y no la recibio (stdin esta cerrado).
//
// Los patrones de nombre que se pasan a UnRAR usan SIEMPRE barra invertida:
// en Windows UnRAR no hace match con "/" (probado: exit 10), y con eso
// fallaban por ejemplo todos los archivos dentro de subcarpetas.

require_once __DIR__ . '/../permisos.php';

/**
 * Ruta del binario UnRAR.exe (WinRAR). Se puede fijar con la constante
 * RAR_UNRAR_BINARIO en config/ antes de incluir este fichero.
 */
function rar_unrar_binario()
{
    if (defined('RAR_UNRAR_BINARIO')) {
        $ruta = (string)RAR_UNRAR_BINARIO;
        return is_file($ruta) ? $ruta : null;
    }

    $candidatas = [
        'C:\Program Files\WinRAR\UnRAR.exe',
        'C:\Program Files (x86)\WinRAR\UnRAR.exe',
        'C:\Program Files\WinRAR x64\UnRAR.exe',
        'C:\Program Files\UnRAR\UnRAR.exe',
    ];
    foreach ($candidatas as $ruta) {
        if (is_file($ruta)) {
            return $ruta;
        }
    }
    return null;
}

/**
 * Lanza UnRAR.exe con los argumentos dados (array, sin shell).
 *
 * @param array<int, string> $argumentos Sin el propietario del proceso.
 * @param int $timeout Segundos maximos de espera.
 * @return array{codigo: int, salida: string, error: string, fallo: bool}
 */
function rar_unrar_ejecutar(array $argumentos, $timeout = 120)
{
    $binario = rar_unrar_binario();
    if ($binario === null) {
        return [
            'codigo' => -1,
            'salida' => '',
            'error'  => 'No se encontro UnRAR.exe (WinRAR) en el servidor.',
            'fallo'  => true,
        ];
    }

    $comando = array_merge([$binario], array_values($argumentos));

    $descriptores = [
        0 => ['pipe', 'r'],   // stdin: se cierra enseguida (UnRAR no debe preguntar)
        1 => ['pipe', 'w'],   // stdout
        2 => ['pipe', 'w'],   // stderr
    ];

    @set_time_limit($timeout + 30);
    $proceso = @proc_open($comando, $descriptores, $pipes, null, null);
    if (!is_resource($proceso)) {
        return [
            'codigo' => -1,
            'salida' => '',
            'error'  => 'No se pudo lanzar UnRAR.exe.',
            'fallo'  => true,
        ];
    }

    fclose($pipes[0]);

    // Lectura secuencial bloqueante: stream_select no es fiable con pipes de
    // proceso en Windows. UnRAR nunca espera stdin (esta cerrado) y con el
    // modo "x" no escribe binario por stdout, asi que no hay riesgo de
    // bloqueo circular; el tope real lo pone set_time_limit.
    @set_time_limit($timeout);
    $salida  = (string)stream_get_contents($pipes[1]);
    $errores = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $codigo = proc_close($proceso);

    return [
        'codigo' => (int)$codigo,
        'salida' => $salida,
        'error'  => $errores,
        'fallo'  => false,
    ];
}

/**
 * Cola (ultimos bytes) del registro de salida de UnRAR mientras escribe.
 * UnRAR vuelca el % global de extraccion en vivo ("... 42% 43% ..."), con lo
 * que se puede leer durante la corrida; los tamaños de los ficheros del
 * temporal no sirven para el progreso porque UnRAR preasigna el tamano final
 * entero al crear cada fichero.
 *
 * @param string $ruta  Fichero de registro de stdout.
 * @param int    $bytes Cuantos bytes finales leer.
 * @return string
 */
function rar_unrar_cola_salida($ruta, $bytes = 4096)
{
    if (!is_string($ruta) || $ruta === '' || !is_file($ruta)) {
        return '';
    }
    $tamano = (int)@filesize($ruta);
    if ($tamano <= 0) {
        return '';
    }
    $desde = max(0, $tamano - (int)$bytes);
    $datos = @file_get_contents($ruta, false, null, $desde, $tamano - $desde);
    return $datos === false ? '' : $datos;
}

/**
 * Igual que rar_unrar_ejecutar(), pero invoca $alProgreso cada ~250 ms
 * mientras UnRAR trabaja: sirve para la barra de progreso de "Extraer todo".
 *
 * La salida de UnRAR se redirige a dos ficheros temporales (los descriptores
 * 1 y 2 con ['file', ...]) en vez de usar pipes: en Windows stream_set_blocking()
 * falla sobre los pipes de proc_open, el fread quedaba bloqueado y el callback
 * nunca corria (la barra se quedaba en 0 % y Apache cortaba la conexion por
 * Timeout). Sin tuberias que leer, el bucle solo consulta el estado del
 * proceso, sondea y duerme; al final se recogen los ficheros y se borran.
 *
 * @param array         $argumentos Argumentos de UnRAR (sin el binario).
 * @param callable|null $alProgreso Callback que recibe la cola (ultimos 4 KB)
 *                                  de la salida de UnRAR: ahi va el % global
 *                                  de extraccion que imprime UnRAR. Null para
 *                                  no invocarlo.
 * @param int           $timeout    Segundos maximos de espera.
 * @return array{codigo: int, salida: string, error: string, fallo: bool}
 */
function rar_unrar_ejecutar_progreso(array $argumentos, $alProgreso = null, $timeout = 900)
{
    $binario = rar_unrar_binario();
    if ($binario === null) {
        return [
            'codigo' => -1,
            'salida' => '',
            'error'  => 'No se encontro UnRAR.exe (WinRAR) en el servidor.',
            'fallo'  => true,
        ];
    }

    $comando = array_merge([$binario], array_values($argumentos));

    $logSalida = @tempnam(sys_get_temp_dir(), 'unrar_out_');
    $logError  = @tempnam(sys_get_temp_dir(), 'unrar_err_');
    if ($logSalida === false || $logError === false) {
        @unlink($logSalida);
        @unlink($logError);
        return [
            'codigo' => -1,
            'salida' => '',
            'error'  => 'No se pudieron crear los ficheros de registro de UnRAR.',
            'fallo'  => true,
        ];
    }

    $descriptores = [
        0 => ['pipe', 'r'],                 // stdin: se cierra enseguida
        1 => ['file', $logSalida, 'w'],     // stdout -> fichero
        2 => ['file', $logError, 'w'],      // stderr -> fichero
    ];

    @set_time_limit($timeout + 30);
    $proceso = @proc_open($comando, $descriptores, $pipes, null, null);
    if (!is_resource($proceso)) {
        @unlink($logSalida);
        @unlink($logError);
        return [
            'codigo' => -1,
            'salida' => '',
            'error'  => 'No se pudo lanzar UnRAR.exe.',
            'fallo'  => false,
        ];
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }

    // Cancelacion desde la pagina (X/Esc): si PHP muere con el proceso vivo
    // (el cliente corto el fetch), el shutdown mata a UnRAR para que no quede
    // extrayendo en segundo plano y borra los registros. Tras un proc_close
    // normal el recurso ya no es valido y este shutdown no hace nada.
    register_shutdown_function(function () use ($proceso, $logSalida, $logError) {
        if (is_resource($proceso)) {
            @proc_terminate($proceso);
            @proc_close($proceso);
            @unlink($logSalida);
            @unlink($logError);
        }
    });

    $inicio = microtime(true);
    $ultimo = 0.0;
    $codigo = null;

    while (true) {
        $estado = proc_get_status($proceso);
        if (!$estado['running']) {
            $codigo = (int)$estado['exitcode'];
            break;
        }

        $ahora = microtime(true);
        if ($alProgreso !== null && $ahora - $ultimo >= 0.25) {
            $ultimo = $ahora;
            $alProgreso(rar_unrar_cola_salida($logSalida));
        }

        if ($ahora - $inicio > $timeout) {
            @proc_terminate($proceso);
            proc_close($proceso);
            $salida  = (string)@file_get_contents($logSalida);
            $errores = (string)@file_get_contents($logError);
            @unlink($logSalida);
            @unlink($logError);
            if ($alProgreso !== null) {
                $alProgreso($salida);
            }
            return [
                'codigo' => -2,
                'salida' => $salida,
                'error'  => $errores . "\nTiempo de extraccion agotado.",
                'fallo'  => true,
            ];
        }

        usleep(50000);
    }

    $cierre = proc_close($proceso);
    if ($codigo === null || $codigo < 0) {
        $codigo = (int)$cierre;
    }

    $salida  = (string)@file_get_contents($logSalida);
    $errores = (string)@file_get_contents($logError);
    @unlink($logSalida);
    @unlink($logError);

    if ($alProgreso !== null) {
        $alProgreso($salida);
    }

    return [
        'codigo' => (int)$codigo,
        'salida' => $salida,
        'error'  => $errores,
        'fallo'  => false,
    ];
}

/**
 * Clasifica el resultado de UnRAR para traducirlo a los mismos codigos que
 * usa el visor ZIP (password_requerida / password_incorrecta / corrupta).
 *
 * @return string 'password' | 'password_falta' | 'corrupta' | 'no_encontrada' | 'error' | 'ok'
 */
function rar_unrar_clasificar(array $resultado)
{
    if ($resultado['fallo']) {
        return 'error';
    }
    if ($resultado['codigo'] === 0 || $resultado['codigo'] === 1) {
        // 1 = "warnings were generated": no es un error de extraccion.
        return 'ok';
    }

    $texto = $resultado['salida'] . "\n" . $resultado['error'];

    // Password mala (la pide o la rechaza segun la version).
    if (preg_match('/password is incorrect|wrong password|incorrect password|contrase[^\r\n]*incorrecta/i', $texto)) {
        return 'password';
    }
    // Pidio la contrase\u00f1a por stdin y se corto en EOF.
    if (preg_match('/Introduzca la contrase|Enter password|password is required/i', $texto)) {
        return 'password_falta';
    }
    if ($resultado['codigo'] === 11) {
        return 'password';
    }
    if ($resultado['codigo'] === 3 || preg_match('/CRC failed|checksum error/i', $texto)) {
        return 'corrupta';
    }
    // Exit 10: el patron no encontro ficheros (nombre o formato no coincide).
    if ($resultado['codigo'] === 10 ||
        preg_match('/No hay ficheros que extraer|no files found|nothing to extract/i', $texto)) {
        return 'no_encontrada';
    }
    return 'error';
}

/**
 * Patron para UnRAR: barra invertida siempre (ver cabecera del fichero).
 */
function rar_patron($nombre)
{
    return str_replace('/', '\\', (string)$nombre);
}

/**
 * UnRAR escribe su salida en el codepage OEM de la consola (CP850 en
 * Windows espanol): los acentos llegan como bytes de un solo caracter.
 * Esta funcion los pasa a UTF-8 para poder parsearlos y mostrarlos.
 */
function rar_decodificar_salida($texto)
{
    $texto = (string)$texto;
    if ($texto === '') {
        return '';
    }
    $convertido = @iconv('CP850', 'UTF-8//IGNORE', $texto);
    return $convertido !== false && $convertido !== '' ? $convertido : $texto;
}

/**
 * Listado tecnico del RAR (UnRAR lt) devuelto como array de entradas
 * planas. Es la unica via de leer el indice de un archivo con las
 * cabeceras cifradas (-hp): la libreria solo ve datos codificados ahi.
 *
 * @return array{ok: bool, motivo?: string, entradas?: array<int, array{nombre: string, esDir: bool, bytes: int, comprimido: int, fechaTs: int, cifrada: bool}>, detalles?: string}
 */
function rar_listar_unrar($rutaRar, $password = '')
{
    $argumentos = ['lt'];
    if ($password !== '') {
        $argumentos[] = '-p' . $password;
    }
    $argumentos[] = $rutaRar;

    $resultado   = rar_unrar_ejecutar($argumentos, 180);
    $clasificado = rar_unrar_clasificar($resultado);

    if ($clasificado === 'password' || $clasificado === 'password_falta') {
        return ['ok' => false, 'motivo' => 'password'];
    }
    if ($clasificado !== 'ok') {
        return [
            'ok'      => false,
            'motivo'  => $clasificado,
            'detalles' => trim($resultado['salida'] . ' ' . $resultado['error']),
        ];
    }

    $texto    = rar_decodificar_salida($resultado['salida']);
    $bloques  = preg_split('/\r?\n\s*\r?\n/', $texto);
    $entradas = [];

    foreach ($bloques as $bloque) {
        if (!preg_match('/^\s*Nombre:\s*([^\r\n]+)/mi', $bloque, $n)) {
            continue;
        }
        $nombre = trim($n[1]);
        if ($nombre === '') {
            continue;
        }

        preg_match('/^\s*Tipo:\s*([^\r\n]+)/miu', $bloque, $t);
        $tipo  = isset($t[1]) ? trim($t[1]) : '';
        $esDir = (bool)preg_match('/Directorio|Carpeta|Folder|Directory/iu', $tipo) ||
            substr($nombre, -1) === '\\' || substr($nombre, -1) === '/';

        // /u en todos: tras iconv la "ñ" de "Tama\u00f1o" son dos bytes y sin
        // /u \w no la matchea (daba bytes=0 en el listado de UnRAR).
        $bytes = 0;
        if (preg_match('/^\s*Tama\w*:\s*(\d+)/miu', $bloque, $s)) {
            $bytes = (int)$s[1];
        }
        $comprimido = 0;
        if (preg_match('/^\s*Tama\w*\s+comprimido:\s*(\d+)/miu', $bloque, $p) ||
            preg_match('/^\s*Packed size:\s*(\d+)/miu', $bloque, $p)) {
            $comprimido = (int)$p[1];
        }

        $fechaTs = 0;
        if (preg_match('/^\s*(?:Modificado|Modified):\s*(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/miu', $bloque, $d)) {
            $fechaTs = mktime((int)$d[4], (int)$d[5], (int)$d[6], (int)$d[2], (int)$d[3], (int)$d[1]);
        }

        $cifrada = (bool)preg_match('/Marcas?:[^\r\n]*(?:cifrad|encrypted)/iu', $bloque);

        $entradas[] = [
            'nombre'     => $nombre,
            'esDir'      => $esDir,
            'bytes'      => $bytes,
            'comprimido' => $comprimido,
            'fechaTs'    => $fechaTs,
            'cifrada'    => $cifrada,
        ];
    }

    if (count($entradas) === 0) {
        // Sin entradas: o el RAR esta cifrado y la password no valia (algunas
        // versiones devuelven 0 sin error), o UnRAR no pudo leerlo.
        if ($password !== '') {
            return ['ok' => false, 'motivo' => 'password'];
        }
        return [
            'ok'       => false,
            'motivo'   => 'error',
            'detalles' => 'UnRAR no listo ninguna entrada.',
        ];
    }

    return ['ok' => true, 'entradas' => $entradas];
}

/**
 * Indice completo del RAR en formato plano (las tres operaciones del visor
 * usan este mismo array, por lo que los indices de las entradas coinciden
 * siempre entre el indice, la previsualizacion y la extraccion):
 *
 *   ['nombre' => 'carpeta/fichero.txt' (con /), 'esDir' => bool,
 *    'bytes' => int, 'comprimido' => int, 'fechaTs' => int, 'cifrada' => bool]
 *
 * Primero intenta la libreria (sin lanzar UnRAR); si la libreria no puede
 * con el archivo -cabeceras cifradas con -hp, formato antiguo, etc.- se pide
 * el listado a UnRAR. Con cabeceras cifradas y sin password devuelve
 * motivo 'password' para que el llamador responda requierePassword.
 *
 * @return array{ok: bool, origen?: string, motivo?: string, entradas?: array, detalles?: string}
 */
function rar_indice_completo($rutaRar, $password = '')
{
    $entradas = null;

    try {
        $lector   = new \Selective\Rar\RarFileReader();
        $archivoR = $lector->openFile(new SplFileObject($rutaRar));
        $crudas   = $archivoR->getEntries();

        if (is_array($crudas) && count($crudas) > 0) {
            $entradas = [];
            foreach ($crudas as $entrada) {
                $nombre = (string)$entrada->getName();
                // Nombres binarios o invalidos: la libreria esta leyendo
                // datos cifrados como si fueran cabeceras (caso -hp).
                if ($nombre === '' || !preg_match('//u', $nombre) ||
                    preg_match('/[\x00-\x1F\x7F]/', $nombre)) {
                    $entradas = null;
                    break;
                }
                $nombre = trim(str_replace('\\', '/', $nombre), '/');
                if ($nombre === '') {
                    continue;
                }
                $entradas[] = [
                    'nombre'     => $nombre,
                    'esDir'      => (bool)$entrada->isDirectory(),
                    'bytes'      => (int)$entrada->getUnpackedSize(),
                    'comprimido' => (int)$entrada->getPackedSize(),
                    'fechaTs'    => self_fecha_segura($entrada),
                    'cifrada'    => (bool)$entrada->isEncrypted(),
                ];
            }
        }
    } catch (\Throwable $ex) {
        $entradas = null;
    }

    if ($entradas !== null && count($entradas) > 0) {
        return ['ok' => true, 'origen' => 'lib', 'entradas' => $entradas];
    }

    $listado = rar_listar_unrar($rutaRar, $password);
    if ($listado['ok'] !== true) {
        $listado['origen'] = 'unrar';
        return $listado;
    }

    $normalizadas = [];
    foreach ($listado['entradas'] as $entrada) {
        $nombre = trim(str_replace('\\', '/', $entrada['nombre']), '/');
        if ($nombre === '') {
            continue;
        }
        $entrada['nombre'] = $nombre;
        $normalizadas[]    = $entrada;
    }

    if (count($normalizadas) === 0) {
        return ['ok' => false, 'origen' => 'unrar', 'motivo' => 'error', 'detalles' => 'Listado vacio.'];
    }

    return ['ok' => true, 'origen' => 'unrar', 'entradas' => $normalizadas];
}

/**
 * Fecha de la entrada como timestamp; si la libreria falla, 0.
 */
function self_fecha_segura($entrada)
{
    try {
        return (int)$entrada->getFileTime()->getTimestamp();
    } catch (\Throwable $ex) {
        return 0;
    }
}

/**
 * Extrae UNA entrada del RAR a una carpeta temporal y devuelve la ruta del
 * fichero extraido (con la estructura interna preservada por UnRAR).
 *
 * El llamador debe borrar el temporal (registro con register_shutdown_function
 * o borrado manual). Devuelve:
 *   ['ok' => true,  'archivo' => ruta, 'temporal' => dir]
 *   ['ok' => false, 'motivo' => 'password'|'corrupta'|'error', 'mensaje' => string]
 */
function rar_extraer_entrada_temporal($rutaRar, $nombreInterno, $password)
{
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'visrar_' . uniqid('', true);
    if (!@mkdir($tmpDir, 0700, true)) {
        return ['ok' => false, 'motivo' => 'error', 'mensaje' => 'No se pudo crear el temporal de extraccion.'];
    }

    $argumentos = ['x', '-o+'];
    if ($password !== '') {
        $argumentos[] = '-p' . $password;
    }
    $argumentos[] = $rutaRar;
    $argumentos[] = rar_patron($nombreInterno);
    $argumentos[] = $tmpDir . DIRECTORY_SEPARATOR;

    $resultado   = rar_unrar_ejecutar($argumentos, 300);
    $clasificado = rar_unrar_clasificar($resultado);

    if ($clasificado !== 'ok') {
        $mensajes = [
            'password'      => 'Contraseña incorrecta.',
            'password_falta' => 'Esta entrada esta protegida con contraseña.',
            'corrupta'      => 'La entrada esta corrupta (CRC incorrecto).',
            'no_encontrada' => 'No se encontro esa entrada dentro del RAR.',
            'error'         => 'No se pudo extraer la entrada del RAR.',
        ];
        @rmdir($tmpDir);
        return [
            'ok'      => false,
            'motivo'  => $clasificado,
            'mensaje' => $mensajes[$clasificado],
            'detalles' => trim($resultado['salida'] . ' ' . $resultado['error']),
        ];
    }

    $seguro = carpetas_nombre_seguro($nombreInterno);
    $archivo = $seguro === null
        ? null
        : $tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $seguro);

    if ($archivo === null || !is_file($archivo)) {
        @rmdir($tmpDir);
        return [
            'ok'      => false,
            'motivo'  => 'error',
            'mensaje' => 'UnRAR termino sin dejar el archivo extraido.',
        ];
    }

    return ['ok' => true, 'archivo' => $archivo, 'temporal' => $tmpDir];
}
