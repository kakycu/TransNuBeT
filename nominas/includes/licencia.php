<?php
// includes/licencia.php - Módulo de licenciamiento del sistema.
// La licencia se introduce UNA sola vez, según su tipo (1, 3 o 5 meses;
// 1, 2 o 5 años, o permanentente). El vencimiento se calcula a partir de la
// fecha de activación. Se guarda cifrada (AES-256-CBC) en el Registro de
// Windows cuando el servidor es Windows, o en un archivo cifrado en el
// disco duro de la máquina cliente cuando es Linux/otro.
// Este módulo es 100% PHP/AES y NO depende de la base de datos.

if (!defined('LICENCIA_SECRETO')) {
    define('LICENCIA_SECRETO', 'SISGESNOM!#2026-K3y-L1c3ncia-5014C0E@Siger');
}

define('LICENCIA_RUTA_REGISTRO', 'HKCU\Software\SISGESNOM');
define('LICENCIA_NOMBRE_VALOR', 'Licencia');

// Alfabeto del serial (sin I, O, 0, 1 para evitar confusiones visuales)
define('LICENCIA_ALFABETO', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

/**
 * Tipos de licencia disponibles.
 * El PRIMER carácter del serial codifica el tipo.
 * @return array listado [tipo => ['nombre' => ..., 'meses' => int|null]]
 */
function licencia_tipos() {
    return array(
        'M' => array('nombre' => '1 Mes',          'meses' => 1),
        'Q' => array('nombre' => '3 Meses',        'meses' => 3),
        'S' => array('nombre' => '5 Meses',        'meses' => 5),
        'A' => array('nombre' => '1 Año',          'meses' => 12),
        'B' => array('nombre' => '2 Años',         'meses' => 24),
        'C' => array('nombre' => '5 Años',         'meses' => 60),
        'P' => array('nombre' => 'Permanente',     'meses' => null),
    );
}

/**
 * Devuelve la información de un tipo de licencia o null si no existe.
 * @param string $tipo
 * @return array|null
 */
function licencia_tipo_info($tipo) {
    $tipos = licencia_tipos();
    $tipo  = strtoupper((string)$tipo);
    return isset($tipos[$tipo]) ? array_merge(array('tipo' => $tipo), $tipos[$tipo]) : null;
}

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
 * Normaliza un dato de registro/licencia (mayúsculas, espacios simples).
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
 * Extrae el tipo de licencia codificado en el primer carácter del serial.
 * @param string $serial Serial con formato XXXXX-... o plano de 25 chars.
 * @return string|null Tipo ('M','Q','S','A','B','C','P') o null si no válido.
 */
function licencia_tipo_desde_serial($serial) {
    $norm = licencia_normalizar_serial($serial);
    if (strlen($norm) !== 25) return null;
    $tipo = $norm[0];
    return (licencia_tipo_info($tipo) !== null) ? $tipo : null;
}

/**
 * Genera la llave serial determinística para nombre+usuario+tipo dados.
 * Solo esa combinación exacta produce esta llave.
 * El primer carácter indica el tipo de licencia.
 * @param string $nombre  Nombre de registro
 * @param string $usuario Usuario del registro
 * @param string $tipo    Tipo de licencia (M,Q,S,A,B,P); por defecto M
 * @return string Serial con formato XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
 */
function licencia_generar_serial($nombre, $usuario, $tipo = 'M') {
    $tipo  = strtoupper((string)$tipo);
    $info  = licencia_tipo_info($tipo);
    if ($info === null) {
        throw new InvalidArgumentException('Tipo de licencia no válido: ' . $tipo);
    }
    $dato = licencia_normalizar($nombre) . '|' . licencia_normalizar($usuario) . '|' . $tipo;
    $hash = hash_hmac('sha256', $dato, LICENCIA_SECRETO, true);
    $cod  = licencia_b32_encode($hash);
    $cod  = $tipo . substr($cod, 0, 24); // 25 caracteres: tipo + 24 hash
    return substr($cod, 0, 5) . '-' . substr($cod, 5, 5) . '-' . substr($cod, 10, 5) . '-' . substr($cod, 15, 5) . '-' . substr($cod, 20, 5);
}

/**
 * Valida un serial contra el nombre, usuario y tipo codificado.
 * @param string $nombre
 * @param string $usuario
 * @param string $serial
 * @return bool
 */
function licencia_validar_serial($nombre, $usuario, $serial) {
    $tipo = licencia_tipo_desde_serial($serial);
    if ($tipo === null) return false;
    $esperado = licencia_normalizar_serial(licencia_generar_serial($nombre, $usuario, $tipo));
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
 * @return string
 */
function licencia_archivo_ruta() {
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = getenv('USERPROFILE');
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
    $contenido = trim($contenido);
    // Compatibilidad con archivos antiguos con cabecera sigesnom://licencia?...
    if (strpos($contenido, 'sigesnom://licencia?') === 0) {
        return substr($contenido, strlen('sigesnom://licencia?'));
    }
    return $contenido;
}

/**
 * Escribe el valor cifrado de licencia en el archivo.
 * @param string $valor Base64 a guardar.
 * @return bool
 */
function licencia_archivo_escribir($valor) {
    $ruta = licencia_archivo_ruta();
    $tmp  = $ruta . '.' . getmypid() . '.tmp';
    $datos = $valor;
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
 * Comprueba si existe algún valor de licencia guardado (aunque esté dañado).
 * @return bool
 */
function licencia_tiene_valor_guardado() {
    if (licencia_backend() === 'windows') {
        return (licencia_windows_leer() !== null);
    }
    $ruta = licencia_archivo_ruta();
    return (is_file($ruta) && is_readable($ruta));
}

/**
 * Lee los datos de licencia guardados y los descifra.
 * @return array|null Datos de licencia o null.
 *   Campos: registro, usuario, serial, tipo, fecha_activacion (UNIX), info (array tipo)
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
    $d = json_decode($plano, true);
    if (!is_array($d)) return null;
    $serial = isset($d['k']) ? (string)$d['k'] : '';
    $tipo   = licencia_tipo_desde_serial($serial);
    if ($tipo === null) return null;
    return array(
        'registro'         => isset($d['r']) ? (string)$d['r'] : '',
        'usuario'          => isset($d['u']) ? (string)$d['u'] : '',
        'serial'           => $serial,
        'tipo'             => $tipo,
        'fecha_activacion' => isset($d['d']) ? (int)$d['d'] : time(),
        'huella'           => isset($d['m']) ? licencia_normalizar_fingerprint($d['m']) : '',
        'info'             => licencia_tipo_info($tipo),
    );
}

/**
 * Timestamp de vencimiento de la licencia (null si es permanente).
 * @param array|null $datos Datos de licencia (de licencia_leer()).
 * @return int|null
 */
function licencia_vencimiento($datos) {
    if ($datos === null) return null;
    $meses = isset($datos['info']['meses']) ? $datos['info']['meses'] : null;
    if ($meses === null) return null; // permanente
    $fecha = $datos['fecha_activacion'];
    return strtotime('+' . (int)$meses . ' months', (int)$fecha);
}

/**
 * Comprueba si la licencia está vencida (o inválida).
 * @param array|null $datos Datos de licencia o null.
 * @return bool
 */
function licencia_vencida($datos) {
    if ($datos === null) return true;
    $vence = licencia_vencimiento($datos);
    if ($vence === null) return false; // permanente nunca vence
    return time() > (int)$vence;
}

/**
 * Días restantes hasta el vencimiento (0 si vencida).
 * @param array|null $datos
 * @return int|null null si permanente/inválida
 */
function licencia_dias_restantes($datos) {
    $vence = licencia_vencimiento($datos);
    if ($vence === null) return null;
    $dias = (int)ceil(((int)$vence - time()) / 86400);
    return max(0, $dias);
}

/**
 * Comprueba si el sistema está registrado, el serial es válido Y NO ha vencido.
 * @return bool
 */
function licencia_activada() {
    $datos = licencia_leer();
    if ($datos === null) return false;
    if ($datos['registro'] === '' || $datos['usuario'] === '' || $datos['serial'] === '') return false;
    if (!licencia_validar_serial($datos['registro'], $datos['usuario'], $datos['serial'])) return false;
    if (licencia_vencida($datos)) return false;
    // La licencia instalada queda vinculada a la huella del equipo actual.
    if ($datos['huella'] === '') return false;
    if (!hash_equals($datos['huella'], licencia_fingerprint_machine())) return false;
    return true;
}

/**
 * Guarda la licencia UNA sola vez (salvo que esté vencida, lo que permite
 * re-registrar con una nueva llave). Devuelve el estado almacenado.
 * @param string $nombre  Nombre de registro
 * @param string $usuario Usuario del registro
 * @param string $serial  Llave serial
 * @param int|null $fecha_activacion Para pruebas / re-registro (UNIX).
 * @return array|false Estado guardado o false si no se pudo.
 */
function licencia_guardar($nombre, $usuario, $serial, $fecha_activacion = null) {
    $tipo = licencia_tipo_desde_serial($serial);
    if ($tipo === null) return false;
    // Solo se re-registra si la licencia actual no es válida ni está activa.
    if (licencia_activada()) return false;
    $plano = json_encode(array(
        'r' => licencia_normalizar($nombre),
        'u' => licencia_normalizar($usuario),
        'k' => licencia_normalizar_serial($serial),
        'd' => ($fecha_activacion === null) ? time() : (int)$fecha_activacion,
        'm' => licencia_fingerprint_machine(),
    ));
    $valor = licencia_cifrar($plano);
    $ok = (licencia_backend() === 'windows')
        ? licencia_windows_escribir($valor)
        : licencia_archivo_escribir($valor);
    if (!$ok) return false;
    return licencia_leer();
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

/**
 * Da formato legible XXXXX-XXXXX-XXXXX-XXXXX-XXXXX a un serial plano.
 * @param string $serial
 * @return string
 */
function licencia_formatear_serial($serial) {
    $norm = licencia_normalizar_serial($serial);
    if (strlen($norm) !== 25) return $serial;
    return substr($norm, 0, 5) . '-' . substr($norm, 5, 5) . '-' . substr($norm, 10, 5) . '-' . substr($norm, 15, 5) . '-' . substr($norm, 20, 5);
}

/* ================= Huella de máquina (fingerprint) ================= */

/**
 * Normaliza una huella de máquina (quitar guiones, mayúsculas).
 * @param string $fp
 * @return string
 */
function licencia_normalizar_fingerprint($fp) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$fp));
}

/**
 * Formatea una huella en grupos legibles XXXXX-XXXXX-XXXXX-XXXXX (4x5).
 * @param string $fp
 * @return string
 */
function licencia_formatear_fingerprint($fp) {
    $n = licencia_normalizar_fingerprint($fp);
    if (strlen($n) !== 20) return (string)$fp;
    return substr($n, 0, 5) . '-' . substr($n, 5, 5) . '-' . substr($n, 10, 5) . '-' . substr($n, 15, 5);
}

/**
 * Calcula la huella (fingerprint) del equipo actual.
 * Combina identificadores estables del sistema; con cache por request.
 * Devuelve 20 caracteres del alfabeto del serial (4 grupos de 5).
 * @return string
 */
function licencia_fingerprint_machine() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $fuentes = array();

    $host = gethostname();
    if (is_string($host) && $host !== '') $fuentes[] = 'H:' . strtoupper($host);

    if (stripos(PHP_OS, 'win') === 0) {
        // MachineGuid: GUID único de la instalación de Windows (muy estable).
        $out = @shell_exec('reg query "HKLM\SOFTWARE\Microsoft\Cryptography" /v MachineGuid /reg:64 2>NUL');
        if ($out === null || $out === '') $out = @shell_exec('reg query "HKLM\SOFTWARE\Microsoft\Cryptography" /v MachineGuid 2>NUL');
        if (is_string($out)) {
            foreach (preg_split('/\r?\n/', $out) as $linea) {
                if (preg_match('/REG_SZ\s+([0-9A-Fa-f-]+)$/', trim($linea), $m)) {
                    $fuentes[] = 'G:' . strtoupper(trim($m[1]));
                    break;
                }
            }
        }
        // Número de serie del volumen C:
        $vol = @shell_exec('vol c: 2>NUL');
        if (is_string($vol) && preg_match('/([0-9A-F]{4})-([0-9A-F]{4})/i', $vol, $m)) {
            $fuentes[] = 'CV:' . strtoupper($m[1] . $m[2]);
        }
    } else {
        foreach (array('/etc/machine-id', '/sys/class/dmi/id/product_uuid', '/sys/class/dmi/id/board_serial') as $ruta_b) {
            $v = @file_get_contents($ruta_b);
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '') $fuentes[] = basename($ruta_b) . ':' . strtoupper($v);
            }
        }
    }

    sort($fuentes);
    $material = LICENCIA_SECRETO . "\n" . implode("\n", $fuentes);
    $hash = hash('sha256', $material, true);
    $cod  = licencia_b32_encode(substr($hash, 0, 12));
    $cache = strtoupper(substr($cod, 0, 20));
    return $cache;
}

/**
 * Huella del equipo actual en formato legible.
 * @return string
 */
function licencia_fingerprint_equipo() {
    return licencia_formatear_fingerprint(licencia_fingerprint_machine());
}

/* ================= Archivos .lic (exportar / importar) ================= */

/**
 * Exporta una licencia a un archivo .lic cifrado con los valores generados.
 * El archivo contiene el MISMO payload cifrado (AES-256-CBC) que se guardaría
 * en el equipo, con los campos r (registro), u (usuario), k (serial) y d
 * (fecha de activación). Así se puede importar en cualquier instalación.
 * @param string $ruta    Ruta del archivo .lic a crear.
 * @param string $nombre  Nombre de registro.
 * @param string $usuario Usuario del registro.
 * @param string $tipo    Tipo de licencia (M,Q,S,A,B,P).
 * @param int|null $fecha_activacion Para fijar vencimiento (pruebas).
 * @return array|false Datos de la licencia exportada o false si falla.
 */
function licencia_exportar($ruta, $nombre, $usuario, $tipo, $fecha_activacion = null, $huella_destino = null) {
    $info = licencia_tipo_info($tipo);
    if ($info === null) return false;
    $m = '';
    if ($huella_destino !== null && $huella_destino !== '') {
        $m = licencia_normalizar_fingerprint($huella_destino);
        if (strlen($m) !== 20) return false;
    }
    $serial = licencia_generar_serial($nombre, $usuario, $tipo);
    $plano = json_encode(array(
        'r' => licencia_normalizar($nombre),
        'u' => licencia_normalizar($usuario),
        'k' => licencia_normalizar_serial($serial),
        'd' => ($fecha_activacion === null) ? time() : (int)$fecha_activacion,
        'm' => $m,
    ));
    $contenido = licencia_cifrar($plano);
    if (@file_put_contents($ruta, $contenido, LOCK_EX) === false) return false;
    return licencia_leer_datos_planos($plano);
}

/**
 * Lee datos de licencia a partir del JSON plano ya construido.
 * @param string $plano JSON con r,u,k,d.
 * @return array|null
 */
function licencia_leer_datos_planos($plano) {
    $d = json_decode($plano, true);
    if (!is_array($d) || !isset($d['r'], $d['u'], $d['k'])) return null;
    $serial = isset($d['k']) ? (string)$d['k'] : '';
    $tipo   = licencia_tipo_desde_serial($serial);
    if ($tipo === null) return null;
    return array(
        'registro'         => (string)$d['r'],
        'usuario'          => (string)$d['u'],
        'serial'           => $serial,
        'tipo'             => $tipo,
        'fecha_activacion' => isset($d['d']) ? (int)$d['d'] : time(),
        'huella'           => isset($d['m']) ? licencia_normalizar_fingerprint($d['m']) : '',
        'info'             => licencia_tipo_info($tipo),
    );
}

/**
 * Lee un archivo .lic exportado (sin instalarlo).
 * @param string $ruta
 * @return array|null Datos de la licencia del archivo o null si falla.
 */
function licencia_leer_archivo_lic($ruta) {
    if (!is_file($ruta) || !is_readable($ruta)) return null;
    $contenido = @file_get_contents($ruta);
    if ($contenido === false) return null;
    $contenido = trim($contenido);
    $valor = $contenido;
    // Compatibilidad con archivos antiguos con cabecera sigesnom://licencia?...
    if (strpos($contenido, 'sigesnom://licencia?') === 0) {
        $valor = substr($contenido, strlen('sigesnom://licencia?'));
    }
    $plano = licencia_descifrar($valor);
    if ($plano === null) return null;
    $d = json_decode($plano, true);
    if (!is_array($d) || !isset($d['r'], $d['u'], $d['k'])) return null;
    // El serial debe ser válido para los datos que contiene.
    if (!licencia_validar_serial($d['r'], $d['u'], $d['k'])) return null;
    return licencia_leer_datos_planos($plano);
}

/**
 * Importa (instala) una licencia desde un archivo .lic exportado.
 * Se guarda únicamente si no hay una licencia válida activa en el equipo.
 * @param string $ruta Ruta del archivo .lic.
 * @return array|false Datos instalados o false si no se pudo.
 */
function licencia_importar_archivo($ruta) {
    $datos = licencia_leer_archivo_lic($ruta);
    if ($datos === null) return false;
    if (licencia_activada()) return false;
    $fp = licencia_fingerprint_machine();
    if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fp)) return false;
    $ok = licencia_guardar($datos['registro'], $datos['usuario'], $datos['serial'], $datos['fecha_activacion']);
    if ($ok === false) return false;
    // Marcar el .lic como "ya activado en este equipo" (un solo uso).
    if ($datos['huella'] === '' && is_writable($ruta)) {
        $plano = json_encode(array(
            'r' => $ok['registro'],
            'u' => $ok['usuario'],
            'k' => licencia_normalizar_serial($ok['serial']),
            'd' => $ok['fecha_activacion'],
            'm' => $fp,
        ));
        @file_put_contents($ruta, licencia_cifrar($plano), LOCK_EX);
    }
    return $ok;
}

/**
 * Ve la licencia instalada en este equipo (se usa desde CLI/herramienta).
 * @return string|null Descripción multilínea o null si no hay licencia.
 */
function licencia_descripcion_instalada() {
    if (!licencia_tiene_valor_guardado()) {
        return 'NO HAY LICENCIA INSTALADA en este equipo.' . "\nRuta esperada: " . licencia_ruta_almacenamiento();
    }
    $datos = licencia_leer();
    if ($datos === null) {
        return 'EXISTE UN VALOR DE LICENCIA pero no se pudo descifrar (corrupto o alterado).' . "\nRuta: " . licencia_ruta_almacenamiento();
    }
    $vence  = licencia_vencimiento($datos);
    $estado = licencia_activada() ? 'ACTIVA' : (licencia_vencida($datos) ? 'VENCIDA' : 'INVALIDA');
    $lineas  = array();
    $lineas[] = 'Estado            : ' . $estado;
    $lineas[] = 'Registro          : ' . $datos['registro'];
    $lineas[] = 'Usuario           : ' . $datos['usuario'];
    $lineas[] = 'Tipo de licencia  : ' . $datos['info']['nombre'];
    $lineas[] = 'Llave serial      : ' . licencia_formatear_serial($datos['serial']);
    $fp = $datos['huella'];
    if ($fp !== '') {
        $coincide = hash_equals($fp, licencia_fingerprint_machine());
        $lineas[] = 'Vinculada a       : ' . licencia_formatear_fingerprint($fp) . ($coincide ? '  (este equipo)' : '  (OTRO EQUIPO)');
    }
    $lineas[] = 'Activada el       : ' . date('d/m/Y H:i:s', $datos['fecha_activacion']);
    $lineas[] = 'Vence el          : ' . (($vence === null) ? 'Nunca (licencia permanente)' : date('d/m/Y H:i:s', $vence));
    if ($vence !== null) {
        $dias = licencia_dias_restantes($datos);
        $lineas[] = 'Días restantes    : ' . $dias;
    }
    $lineas[] = 'Almacenada en     : ' . licencia_ruta_almacenamiento();
    return implode("\n", $lineas);
}

/* ================= Utilidades para pruebas / CLI ================= */

/**
 * Ejecuta un test rápido de auto-diagnóstico del módulo.
 * @return array
 */
function licencia_test() {
    $nombre  = 'SISGESNOM';
    $usuario = 'admin';
    $resultados = array(
        'backend'     => licencia_backend(),
        'tipos'       => array_keys(licencia_tipos()),
        'ruta'        => licencia_ruta_almacenamiento(),
        'generacion'  => array(),
        'validar_ok'  => true,
        'validar_nok' => true,
        'cifrar_descifrar' => (licencia_descifrar(licencia_cifrar('prueba-123')) === 'prueba-123'),
        'fingerprint' => array(),
    );
    $fp1 = licencia_fingerprint_machine();
    $fp2 = licencia_fingerprint_machine();
    $fpOk = (strlen($fp1) === 20
        && preg_match('/^[A-Z2-9]{20}$/', $fp1) === 1
        && strlen(licencia_normalizar_fingerprint(licencia_formatear_fingerprint($fp1))) === 20
        && hash_equals($fp1, $fp2));
    $resultados['fingerprint'] = array(
        'huella'    => licencia_formatear_fingerprint($fp1),
        'estable'   => $fpOk,
        'longitud'  => strlen($fp1),
        'formato'   => licencia_formatear_fingerprint($fp1),
    );
    foreach (licencia_tipos() as $tipo => $info) {
        $serial = licencia_generar_serial($nombre, $usuario, $tipo);
        $resultados['generacion'][$tipo] = array(
            'serial' => $serial,
            'len'    => strlen($serial),
            'tipo_leido' => licencia_tipo_desde_serial($serial),
            'valida' => licencia_validar_serial($nombre, $usuario, $serial),
        );
        if ($resultados['generacion'][$tipo]['tipo_leido'] !== $tipo) $resultados['validar_ok'] = false;
        if (!$resultados['generacion'][$tipo]['valida']) $resultados['validar_ok'] = false;
    }
    $resultados['validar_ok'] = $resultados['validar_ok'] && true;

    // Serial erróneo
    $malo = str_replace('M', 'Q', licencia_generar_serial($nombre, $usuario, 'M'));
    $resultados['validar_nok'] = !licencia_validar_serial($nombre, $usuario, $malo);

    // Pruebas con persistencia (solo si no hay una licencia real activa)
    $persistencia = array(
        'habia_licencia' => licencia_activada(),
        'guardar'        => null,
        'leer'           => null,
        'vencimiento'    => null,
        'vencida'        => null,
        'activada'       => null,
    );
    if (!licencia_activada()) {
        $serial_m = licencia_generar_serial($nombre, $usuario, 'M');
        $guardado = licencia_guardar($nombre, $usuario, $serial_m);
        $persistencia['guardar'] = ($guardado !== false);
        $leido = ($guardado !== false) ? licencia_leer() : null;
        $persistencia['leer'] = $leido;
        $persistencia['vencimiento'] = $leido ? array(time(), licencia_vencimiento($leido)) : null;
        $persistencia['vencida'] = $leido ? licencia_vencida($leido) : null;
        $persistencia['activada'] = licencia_activada();
        // Simular vencida: re-escribir con fecha de hace 40 días (tipo M = 1 mes)
        $vencida_test = licencia_guardar($nombre, $usuario, $serial_m, time() - 40 * 86400);
        $persistencia['vencida_forzada'] = ($vencida_test !== false) ? licencia_vencida(licencia_leer()) : null;
        licencia_borrar();
    }
    $resultados['persistencia'] = $persistencia;
    return $resultados;
}