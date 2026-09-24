<?php
// includes/licencia.php - Módulo de licenciamiento del sistema.
// La licencia se introduce UNA sola vez. Se guarda cifrada (AES-256-CBC)
// en el Registro de Windows cuando el servidor es Windows, o en un archivo
// cifrado en el disco duro de la máquina cliente cuando es Linux/otro.
// Este módulo es 100% PHP/AES y NO depende de la base de datos.

if (!defined('LICENCIA_SECRETO')) {
    define('LICENCIA_SECRETO', 'SISGESNOM!#2026-K3y-L1c3ncia-5014C0E@Siger');
}

define('LICENCIA_RUTA_REGISTRO', 'HKCU\Software\SISGESNOM');
define('LICENCIA_NOMBRE_VALOR', 'Licencia');

// Alfabeto del serial (sin I, O, 0, 1 para evitar confusiones visuales)
define('LICENCIA_ALFABETO', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

/**
 * Detecta el backend de almacenamiento según el sistema operativo.
 * @return string 'windows' | 'archivo'
 */
function licencia_backend() {
    return (stripos(PHP_OS, 'win') === 0) ? 'windows' : 'archivo';
}

/**
 * Codifica bytes binarios a Base32 usando el alfabeto del serial.
 * @param string $bin
 * @return string
 */
function licencia_b32_encode($bin) {
    $alfabeto = LICENCIA_ALFABETO;
    $resultado = '';
    $bits = 0;
    $valor = 0;
    $longitud = strlen($bin);
    for ($i = 0; $i < $longitud; $i++) {
        $valor = ($valor << 8) | ord($bin[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $resultado .= $alfabeto[($valor >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }
    if ($bits > 0) {
        $resultado .= $alfabeto[($valor << (5 - $bits)) & 31];
    }
    return $resultado;
}

/**
 * Normaliza un dato de registro/licencia (mayúsculas, sin espacios).
 * @param string $texto
 * @return string
 */
function licencia_normalizar($texto) {
    return strtoupper(preg_replace('/\s+/', ' ', trim((string)$texto)));
}

/**
 * Normaliza un serial quitando guiones, espacios y pasando a mayúsculas.
 * @param string $serial
 * @return string
 */
function licencia_normalizar_serial($serial) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$serial));
}

/**
 * Genera la llave serial determinística para un nombre+usuario dados.
 * Solo esta combinación exacta produce esta llave.
 * @param string $nombre  Nombre de registro
 * @param string $usuario Usuario del registro
 * @return string Serial con formato XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
 */
function licencia_generar_serial($nombre, $usuario) {
    $dato = licencia_normalizar($nombre) . '|' . licencia_normalizar($usuario);
    $hash = hash_hmac('sha256', $dato, LICENCIA_SECRETO, true);
    $cod = licencia_b32_encode($hash);
    $cod = substr($cod, 0, 25);
    return substr($cod, 0, 5) . '-' . substr($cod, 5, 5) . '-' . substr($cod, 10, 5) . '-' . substr($cod, 15, 5) . '-' . substr($cod, 20, 5);
}

/**
 * Valida un serial contra el nombre y usuario de registro introducidos.
 * @param string $nombre
 * @param string $usuario
 * @param string $serial
 * @return bool
 */
function licencia_validar_serial($nombre, $usuario, $serial) {
    $esperado = licencia_normalizar_serial(licencia_generar_serial($nombre, $usuario));
    $dado     = licencia_normalizar_serial($serial);
    return ($esperado !== '' && hash_equals($esperado, $dado));
}

/**
 * Cifra datos con AES-256-CBC. La clave se deriva del secreto embebido.
 * @param string $plano
 * @return string Base64(iv + ciphertext)
 */
function licencia_cifrar($plano) {
    $clave = hash('sha256', LICENCIA_SECRETO, true);
    $iv    = random_bytes(16);
    $ct    = openssl_encrypt($plano, 'aes-256-cbc', $clave, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ct);
}

/**
 * Descifra datos generados por licencia_cifrar().
 * @param string $b64
 * @return string|null
 */
function licencia_descifrar($b64) {
    if (!is_string($b64) || $b64 === '') return null;
    $clave = hash('sha256', LICENCIA_SECRETO, true);
    $raw   = base64_decode($b64, true);
    if ($raw === false || strlen($raw) <= 16) return null;
    $iv      = substr($raw, 0, 16);
    $ct      = substr($raw, 16);
    $plano   = openssl_decrypt($ct, 'aes-256-cbc', $clave, OPENSSL_RAW_DATA, $iv);
    return ($plano === false) ? null : $plano;
}

/* ================= Backend Windows (Registro) ================= */

/**
 * Lee el valor cifrado de licencia desde el Registro de Windows.
 * @return string|null Base64 de la licencia o null si no existe.
 */
function licencia_windows_leer() {
    $ruta = LICENCIA_RUTA_REGISTRO;
    $cmd  = 'reg query "' . $ruta . "\" /v " . LICENCIA_NOMBRE_VALOR . ' 2>NUL';
    $salida = @shell_exec($cmd);
    if ($salida === null || $salida === false) return null;
    $salida = (string)$salida;
    // Línea típica: "    Licencia    REG_SZ    <valor>"
    foreach (preg_split('/\r?\n/', $salida) as $linea) {
        if (preg_match('/REG_SZ\s+(.+)$/i', trim($linea), $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

/**
 * Escribe el valor cifrado de licencia en el Registro de Windows.
 * @param string $valor Base64 a guardar.
 * @return bool
 */
function licencia_windows_escribir($valor) {
    $ruta = LICENCIA_RUTA_REGISTRO;
    $cmd  = 'reg add "' . $ruta . '" /v ' . LICENCIA_NOMBRE_VALOR . ' /t REG_SZ /d "' . $valor . '" /f 2>NUL';
    $res  = @shell_exec($cmd);
    return ($res !== null && $res !== false);
}

/**
 * Elimina el valor de licencia del Registro de Windows (para re-registro).
 * @return bool
 */
function licencia_windows_borrar() {
    $ruta = LICENCIA_RUTA_REGISTRO;
    $cmd  = 'reg delete "' . $ruta . "\" /v " . LICENCIA_NOMBRE_VALOR . ' /f 2>NUL';
    $res  = @shell_exec($cmd);
    return ($res !== null && $res !== false);
}

/* ================= Backend Archivo (Linux / otros) ================= */

/**
 * Ruta del archivo de licencia en el disco de la máquina cliente.
 * Se prefiere el home del usuario; de lo contrario el directorio temporal.
 * @return string
 */
function licencia_archivo_ruta() {
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = getenv('USERPROFILE'); // útil en algunos Windows sin reg
    }
    if (is_string($home) && $home !== '' && is_dir($home) && is_writable($home)) {
        return rtrim($home, '/\\') . '/.sigesnom_licencia.dat';
    }
    return sys_get_temp_dir() . '/sigesnom_licencia.dat';
}

/**
 * Lee el valor cifrado de licencia desde el archivo.
 * @return string|null
 */
function licencia_archivo_leer() {
    $ruta = licencia_archivo_ruta();
    if (!is_file($ruta) || !is_readable($ruta)) return null;
    $contenido = @file_get_contents($ruta);
    if (!is_string($contenido)) return null;
    $url = @parse_url(trim($contenido));
    return isset($url['query']) ? trim($url['query']) : null;
}

/**
 * Escribe el valor cifrado de licencia en el archivo.
 * El valor se guarda como query string para mantenerlo cifrado (los
 * temporales de texto plano se evitan con operaciones atómicas).
 * @param string $valor Base64 a guardar.
 * @return bool
 */
function licencia_archivo_escribir($valor) {
    $ruta = licencia_archivo_ruta();
    $tmp  = $ruta . '.' . getmypid() . '.tmp';
    $datos = 'sigesnom://licencia?' . $valor;
    $ok = @file_put_contents($tmp, $datos, LOCK_EX);
    if ($ok === false) return false;
    chmod($tmp, 0600);
    $ok = @rename($tmp, $ruta);
    if (!$ok) { @unlink($tmp); return false; }
    return true;
}

/**
 * Elimina el archivo de licencia (para re-registro).
 * @return bool
 */
function licencia_archivo_borrar() {
    $ruta = licencia_archivo_ruta();
    if (is_file($ruta)) return @unlink($ruta);
    return true;
}

/* ================= API pública ================= */

/**
 * Lee los datos de licencia guardados y los descifra.
 * @return array|null ['registro'=>string,'usuario'=>string,'serial'=>string]
 */
function licencia_leer() {
    if (licencia_backend() === 'windows') {
        $valor = licencia_windows_leer();
    } else {
        $valor = licencia_archivo_leer();
    }
    if ($valor === null || $valor === '') return null;
    $plano = licencia_descifrar($valor);
    if ($plano === null) return null;
    $datos = json_decode($plano, true);
    if (!is_array($datos)) return null;
    return array(
        'registro' => isset($datos['r']) ? (string)$datos['r'] : '',
        'usuario'  => isset($datos['u']) ? (string)$datos['u'] : '',
        'serial'   => isset($datos['k']) ? (string)$datos['k'] : '',
    );
}

/**
 * Comprueba si el sistema está registrado y el serial es válido.
 * @return bool
 */
function licencia_activada() {
    $datos = licencia_leer();
    if ($datos === null) return false;
    if ($datos['registro'] === '' || $datos['usuario'] === '' || $datos['serial'] === '') return false;
    return licencia_validar_serial($datos['registro'], $datos['usuario'], $datos['serial']);
}

/**
 * Guarda la licencia (una sola vez). Si ya existe una válida no la pisa.
 * @param string $nombre  Nombre de registro
 * @param string $usuario Usuario del registro
 * @param string $serial  Llave serial
 * @return bool
 */
function licencia_guardar($nombre, $usuario, $serial) {
    if (licencia_activada()) return false; // solo se registra una vez
    $plano = json_encode(array(
        'r' => licencia_normalizar($nombre),
        'u' => licencia_normalizar($usuario),
        'k' => licencia_normalizar_serial($serial),
    ));
    $valor = licencia_cifrar($plano);
    if (licencia_backend() === 'windows') {
        return licencia_windows_escribir($valor);
    }
    return licencia_archivo_escribir($valor);
}

/**
 * Elimina la licencia guardada para permitir re-registro.
 * @return bool
 */
function licencia_borrar() {
    if (licencia_backend() === 'windows') {
        return licencia_windows_borrar();
    }
    return licencia_archivo_borrar();
}

/**
 * Ruta donde se almacena la licencia (informativo).
 * @return string
 */
function licencia_ruta_almacenamiento() {
    return licencia_backend() === 'windows'
        ? LICENCIA_RUTA_REGISTRO . '\\' . LICENCIA_NOMBRE_VALOR
        : licencia_archivo_ruta();
}

/* ================= Utilidades para pruebas / CLI ================= */

/**
 * Ejecuta un test rápido de auto-diagnóstico del módulo.
 * @return array
 */
function licencia_test() {
    $nombre  = 'SISGESNOM';
    $usuario = 'admin';
    $serial  = licencia_generar_serial($nombre, $usuario);
    $resultados = array(
        'backend'       => licencia_backend(),
        'serial_ejemplo'=> $serial,
        'longitud'      => strlen($serial),
        'validar_ok'    => licencia_validar_serial($nombre, $usuario, $serial),
        'validar_nok'   => !licencia_validar_serial($nombre, $usuario, $serial . '-X'),
        'cifrar_descifrar' => (licencia_descifrar(licencia_cifrar('prueba-123')) === 'prueba-123'),
        'ruta'          => licencia_ruta_almacenamiento(),
        'guardar'       => null,
        'leer'          => null,
        'activada'      => licencia_activada(),
    );
    // Test de guardado/lectura sin pisar un registro real existente
    if (!licencia_activada()) {
        $guardar_ok = licencia_guardar($nombre, $usuario, $serial);
        $resultados['guardar'] = $guardar_ok;
        $resultados['leer']    = licencia_leer();
        $resultados['leer_activada'] = licencia_activada();
        if ($guardar_ok) licencia_borrar();
    }
    return $resultados;
}