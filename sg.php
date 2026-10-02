<?php
// herramientas/generar_serial.php
// Herramienta de administración de licencias SISGESNOM.
// CLI + WEB (Login + Sidebar + Topbar + Footer + Tema + SweetAlert2 + Rotar token + Instalar manual + Imprimir/PDF).

error_reporting(E_ALL);
ini_set('display_errors', '1');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

/* =====================================================================
   MÓDULO DE LICENCIA SISGESNOM (EMBEBIDO)
   ===================================================================== */
if (!function_exists('licencia_tipos')) {

    if (!defined('LICENCIA_SECRETO')) {
        define('LICENCIA_SECRETO', 'SISGESNOM!#2026-K3y-L1c3ncia-5014C0E@Siger');
    }
    define('LICENCIA_RUTA_REGISTRO', 'HKCU\Software\SISGESNOM');
    define('LICENCIA_NOMBRE_VALOR', 'Licencia');
    define('LICENCIA_ALFABETO', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

    function licencia_tipos() {
        return array(
            'M' => array('nombre' => '1 Mes',       'meses' => 1),
            'Q' => array('nombre' => '3 Meses',     'meses' => 3),
            'S' => array('nombre' => '5 Meses',     'meses' => 5),
            'A' => array('nombre' => '1 Año',       'meses' => 12),
            'B' => array('nombre' => '2 Años',      'meses' => 24),
            'C' => array('nombre' => '5 Años',      'meses' => 60),
            'P' => array('nombre' => 'Permanente',  'meses' => null),
        );
    }
    function licencia_tipo_info($tipo) {
        $tipos = licencia_tipos();
        $tipo  = strtoupper((string)$tipo);
        return isset($tipos[$tipo]) ? array_merge(array('tipo' => $tipo), $tipos[$tipo]) : null;
    }
    function licencia_backend() {
        return (stripos(PHP_OS, 'win') === 0) ? 'windows' : 'archivo';
    }
    function licencia_b32_encode($bin) {
        $alfabeto = LICENCIA_ALFABETO;
        $resultado = ''; $bits = 0; $valor = 0;
        $longitud = strlen($bin);
        for ($i = 0; $i < $longitud; $i++) {
            $valor = ($valor << 8) | ord($bin[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $resultado .= $alfabeto[($valor >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        if ($bits > 0) $resultado .= $alfabeto[($valor << (5 - $bits)) & 31];
        return $resultado;
    }
    function licencia_normalizar($texto) {
        $texto = (string)$texto;
        if (function_exists('mb_check_encoding') && !mb_check_encoding($texto, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
            } elseif (function_exists('iconv')) {
                $t = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $texto);
                if ($t !== false) $texto = $t;
            }
        }
        if (function_exists('mb_ereg_replace')) {
            $texto = mb_ereg_replace('\s+', ' ', $texto);
            if ($texto === false) $texto = preg_replace('/\s+/u', ' ', $texto);
        } else {
            $texto = preg_replace('/\s+/u', ' ', $texto);
        }
        $texto = trim($texto);
        if (function_exists('mb_strtoupper')) {
            $texto = mb_strtoupper($texto, 'UTF-8');
        } else {
            $mapa = array(
                'á'=>'Á','é'=>'É','í'=>'Í','ó'=>'Ó','ú'=>'Ú','ü'=>'Ü','ñ'=>'Ñ',
                'à'=>'À','è'=>'È','ì'=>'Ì','ò'=>'Ò','ù'=>'Ù',
                'â'=>'Â','ê'=>'Ê','î'=>'Î','ô'=>'Ô','û'=>'Û',
                'ä'=>'Ä','ë'=>'Ë','ï'=>'Ï','ö'=>'Ö',
                'ç'=>'Ç','ý'=>'Ý','ø'=>'Ø','å'=>'Å','æ'=>'Æ','œ'=>'Œ','ß'=>'SS',
            );
            $texto = strtr($texto, $mapa);
            $texto = preg_replace_callback('/[a-z]/', function($m){ return strtoupper($m[0]); }, $texto);
        }
        if (function_exists('mb_convert_encoding')) $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
        return $texto;
    }
    function licencia_normalizar_serial($serial) {
        $s = preg_replace('/[^A-Za-z0-9]/u', '', (string)$serial);
        if ($s === null) $s = preg_replace('/[^A-Za-z0-9]/', '', (string)$serial);
        return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
    }
    function licencia_tipo_desde_serial($serial) {
        $norm = licencia_normalizar_serial($serial);
        if (strlen($norm) !== 25) return null;
        $tipo = $norm[0];
        return (licencia_tipo_info($tipo) !== null) ? $tipo : null;
    }
    function licencia_generar_serial($nombre, $usuario, $tipo = 'M') {
        $tipo  = strtoupper((string)$tipo);
        $info  = licencia_tipo_info($tipo);
        if ($info === null) throw new InvalidArgumentException('Tipo de licencia no válido: ' . $tipo);
        $dato = licencia_normalizar($nombre) . '|' . licencia_normalizar($usuario) . '|' . $tipo;
        $hash = hash_hmac('sha256', $dato, LICENCIA_SECRETO, true);
        $cod  = licencia_b32_encode($hash);
        $cod  = $tipo . substr($cod, 0, 24);
        return substr($cod, 0, 5) . '-' . substr($cod, 5, 5) . '-' . substr($cod, 10, 5) . '-' . substr($cod, 15, 5) . '-' . substr($cod, 20, 5);
    }
    function licencia_validar_serial($nombre, $usuario, $serial) {
        $tipo = licencia_tipo_desde_serial($serial);
        if ($tipo === null) return false;
        $esperado = licencia_normalizar_serial(licencia_generar_serial($nombre, $usuario, $tipo));
        $dado     = licencia_normalizar_serial($serial);
        return ($esperado !== '' && hash_equals($esperado, $dado));
    }
    function licencia_cifrar($plano) {
        $clave = hash('sha256', LICENCIA_SECRETO, true);
        $iv    = random_bytes(16);
        $ct    = openssl_encrypt($plano, 'aes-256-cbc', $clave, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ct);
    }
    function licencia_descifrar($b64) {
        if (!is_string($b64) || $b64 === '') return null;
        $clave = hash('sha256', LICENCIA_SECRETO, true);
        $raw   = base64_decode($b64, true);
        if ($raw === false || strlen($raw) <= 16) return null;
        $iv = substr($raw, 0, 16);
        $ct = substr($raw, 16);
        $plano = openssl_decrypt($ct, 'aes-256-cbc', $clave, OPENSSL_RAW_DATA, $iv);
        return ($plano === false) ? null : $plano;
    }
    function licencia_windows_leer() {
        $ruta = LICENCIA_RUTA_REGISTRO;
        $cmd  = 'reg query "' . $ruta . "\" /v " . LICENCIA_NOMBRE_VALOR . ' 2>NUL';
        $salida = @shell_exec($cmd);
        if ($salida === null || $salida === false) return null;
        foreach (preg_split('/\r?\n/', (string)$salida) as $linea) {
            if (preg_match('/REG_SZ\s+(.+)$/i', trim($linea), $m)) return trim($m[1]);
        }
        return null;
    }
    function licencia_windows_escribir($valor) {
        $ruta = LICENCIA_RUTA_REGISTRO;
        $cmd  = 'reg add "' . $ruta . '" /v ' . LICENCIA_NOMBRE_VALOR . ' /t REG_SZ /d "' . $valor . '" /f 2>NUL';
        $res  = @shell_exec($cmd);
        return ($res !== null && $res !== false);
    }
    function licencia_windows_borrar() {
        $ruta = LICENCIA_RUTA_REGISTRO;
        $cmd  = 'reg delete "' . $ruta . "\" /v " . LICENCIA_NOMBRE_VALOR . ' /f 2>NUL';
        $res  = @shell_exec($cmd);
        return ($res !== null && $res !== false);
    }
    function licencia_archivo_ruta() {
        $home = getenv('HOME');
        if (!is_string($home) || $home === '') $home = getenv('USERPROFILE');
        if (is_string($home) && $home !== '' && is_dir($home) && is_writable($home)) {
            return rtrim($home, '/\\') . '/.sigesnom_licencia.dat';
        }
        return sys_get_temp_dir() . '/sigesnom_licencia.dat';
    }
    function licencia_archivo_leer() {
        $ruta = licencia_archivo_ruta();
        if (!is_file($ruta) || !is_readable($ruta)) return null;
        $contenido = @file_get_contents($ruta);
        if (!is_string($contenido)) return null;
        $contenido = trim($contenido);
        if (strpos($contenido, 'sigesnom://licencia?') === 0) {
            return substr($contenido, strlen('sigesnom://licencia?'));
        }
        return $contenido;
    }
    function licencia_archivo_escribir($valor) {
        $ruta = licencia_archivo_ruta();
        $tmp  = $ruta . '.' . getmypid() . '.tmp';
        $ok = @file_put_contents($tmp, $valor, LOCK_EX);
        if ($ok === false) return false;
        chmod($tmp, 0600);
        $ok = @rename($tmp, $ruta);
        if (!$ok) { @unlink($tmp); return false; }
        return true;
    }
    function licencia_archivo_borrar() {
        $ruta = licencia_archivo_ruta();
        if (is_file($ruta)) return @unlink($ruta);
        return true;
    }
    function licencia_tiene_valor_guardado() {
        if (licencia_backend() === 'windows') return (licencia_windows_leer() !== null);
        $ruta = licencia_archivo_ruta();
        return (is_file($ruta) && is_readable($ruta));
    }
    function licencia_leer() {
        $valor = (licencia_backend() === 'windows') ? licencia_windows_leer() : licencia_archivo_leer();
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
    function licencia_vencimiento($datos) {
        if ($datos === null) return null;
        $meses = isset($datos['info']['meses']) ? $datos['info']['meses'] : null;
        if ($meses === null) return null;
        return strtotime('+' . (int)$meses . ' months', (int)$datos['fecha_activacion']);
    }
    function licencia_vencida($datos) {
        if ($datos === null) return true;
        $vence = licencia_vencimiento($datos);
        if ($vence === null) return false;
        return time() > (int)$vence;
    }
    function licencia_dias_restantes($datos) {
        $vence = licencia_vencimiento($datos);
        if ($vence === null) return null;
        return max(0, (int)ceil(((int)$vence - time()) / 86400));
    }
    function licencia_activada() {
        $datos = licencia_leer();
        if ($datos === null) return false;
        if ($datos['registro'] === '' || $datos['usuario'] === '' || $datos['serial'] === '') return false;
        if (!licencia_validar_serial($datos['registro'], $datos['usuario'], $datos['serial'])) return false;
        if (licencia_vencida($datos)) return false;
        if ($datos['huella'] === '') return false;
        if (!hash_equals($datos['huella'], licencia_fingerprint_machine())) return false;
        return true;
    }
    function licencia_guardar($nombre, $usuario, $serial, $fecha_activacion = null) {
        $tipo = licencia_tipo_desde_serial($serial);
        if ($tipo === null) return false;
        if (licencia_activada()) return false;
        $plano = json_encode(array(
            'r' => licencia_normalizar($nombre),
            'u' => licencia_normalizar($usuario),
            'k' => licencia_normalizar_serial($serial),
            'd' => ($fecha_activacion === null) ? time() : (int)$fecha_activacion,
            'm' => licencia_fingerprint_machine(),
        ), JSON_UNESCAPED_UNICODE);
        $valor = licencia_cifrar($plano);
        $ok = (licencia_backend() === 'windows')
            ? licencia_windows_escribir($valor)
            : licencia_archivo_escribir($valor);
        if (!$ok) return false;
        return licencia_leer();
    }
    function licencia_borrar() {
        if (licencia_backend() === 'windows') return licencia_windows_borrar();
        return licencia_archivo_borrar();
    }
    function licencia_ruta_almacenamiento() {
        return licencia_backend() === 'windows'
            ? LICENCIA_RUTA_REGISTRO . '\\' . LICENCIA_NOMBRE_VALOR
            : licencia_archivo_ruta();
    }
    function licencia_formatear_serial($serial) {
        $norm = licencia_normalizar_serial($serial);
        if (strlen($norm) !== 25) return $serial;
        return substr($norm, 0, 5) . '-' . substr($norm, 5, 5) . '-' . substr($norm, 10, 5) . '-' . substr($norm, 15, 5) . '-' . substr($norm, 20, 5);
    }
    function licencia_normalizar_fingerprint($fp) {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$fp));
    }
    function licencia_formatear_fingerprint($fp) {
        $n = licencia_normalizar_fingerprint($fp);
        if (strlen($n) !== 20) return (string)$fp;
        return substr($n, 0, 5) . '-' . substr($n, 5, 5) . '-' . substr($n, 10, 5) . '-' . substr($n, 15, 5);
    }
    function licencia_fingerprint_machine() {
        static $cache = null;
        if ($cache !== null) return $cache;
        $fuentes = array();
        $host = gethostname();
        if (is_string($host) && $host !== '') $fuentes[] = 'H:' . strtoupper($host);
        if (stripos(PHP_OS, 'win') === 0) {
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
    function licencia_fingerprint_equipo() {
        return licencia_formatear_fingerprint(licencia_fingerprint_machine());
    }
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
        ), JSON_UNESCAPED_UNICODE);
        $contenido = licencia_cifrar($plano);
        if (@file_put_contents($ruta, $contenido, LOCK_EX) === false) return false;
        return licencia_leer_datos_planos($plano);
    }
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
    function licencia_leer_texto_lic($contenido) {
        if (!is_string($contenido)) return null;
        $contenido = trim($contenido);
        if ($contenido === '') return null;
        $valor = $contenido;
        if (strpos($contenido, 'sigesnom://licencia?') === 0) {
            $valor = substr($contenido, strlen('sigesnom://licencia?'));
        }
        $plano = licencia_descifrar($valor);
        if ($plano === null) return null;
        $d = json_decode($plano, true);
        if (!is_array($d) || !isset($d['r'], $d['u'], $d['k'])) return null;
        if (!licencia_validar_serial($d['r'], $d['u'], $d['k'])) return null;
        return licencia_leer_datos_planos($plano);
    }
    function licencia_leer_archivo_lic($ruta) {
        if (!is_file($ruta) || !is_readable($ruta)) return null;
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) return null;
        return licencia_leer_texto_lic($contenido);
    }
    function licencia_importar_archivo($ruta) {
        $datos = licencia_leer_archivo_lic($ruta);
        if ($datos === null) return false;
        if (licencia_activada()) return false;
        $fp = licencia_fingerprint_machine();
        if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fp)) return false;
        $ok = licencia_guardar($datos['registro'], $datos['usuario'], $datos['serial'], $datos['fecha_activacion']);
        if ($ok === false) return false;
        if ($datos['huella'] === '' && is_writable($ruta)) {
            $plano = json_encode(array(
                'r' => $ok['registro'],
                'u' => $ok['usuario'],
                'k' => licencia_normalizar_serial($ok['serial']),
                'd' => $ok['fecha_activacion'],
                'm' => $fp,
            ), JSON_UNESCAPED_UNICODE);
            @file_put_contents($ruta, licencia_cifrar($plano), LOCK_EX);
        }
        return $ok;
    }
    function licencia_descripcion_instalada() {
        if (!licencia_tiene_valor_guardado()) {
            return 'NO HAY LICENCIA INSTALADA en este equipo.' . "\nRuta esperada: " . licencia_ruta_almacenamiento();
        }
        $datos = licencia_leer();
        if ($datos === null) {
            return 'EXISTE UN VALOR DE LICENCIA pero no se pudo descifrar.' . "\nRuta: " . licencia_ruta_almacenamiento();
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
        if ($vence !== null) $lineas[] = 'Días restantes    : ' . licencia_dias_restantes($datos);
        $lineas[] = 'Almacenada en     : ' . licencia_ruta_almacenamiento();
        return implode("\n", $lineas);
    }
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
            'acentos'     => array(
                'entrada'    => 'ROBERTO GARCÍA PÉREZ',
                'normalizado'=> licencia_normalizar('Roberto García Pérez'),
            ),
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
        $malo = str_replace('M', 'Q', licencia_generar_serial($nombre, $usuario, 'M'));
        $resultados['validar_nok'] = !licencia_validar_serial($nombre, $usuario, $malo);
        $r1 = licencia_generar_serial('Roberto García Pérez', 'admin', 'M');
        $r2 = licencia_generar_serial('ROBERTO GARCÍA PÉREZ', 'ADMIN', 'M');
        $resultados['acentos']['serial_minus'] = $r1;
        $resultados['acentos']['serial_mayus'] = $r2;
        $resultados['acentos']['coinciden'] = ($r1 === $r2);
        $persistencia = array('habia_licencia' => licencia_activada());
        if (!licencia_activada()) {
            $serial_m = licencia_generar_serial($nombre, $usuario, 'M');
            $guardado = licencia_guardar($nombre, $usuario, $serial_m);
            $persistencia['guardar'] = ($guardado !== false);
            $leido = ($guardado !== false) ? licencia_leer() : null;
            $persistencia['leer'] = $leido;
            $persistencia['vencida'] = $leido ? licencia_vencida($leido) : null;
            $persistencia['activada'] = licencia_activada();
            licencia_borrar();
        }
        $resultados['persistencia'] = $persistencia;
        return $resultados;
    }
}

/* =====================================================================
   UTILIDADES
   ===================================================================== */
function uso() {
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php generar_serial.php \"Nombre\" \"Usuario\" [TIPO]\n");
    fwrite(STDOUT, "  php generar_serial.php \"Nombre\" \"Usuario\" TIPO export \"archivo.lic\" [HUELLA]\n");
    fwrite(STDOUT, "  php generar_serial.php import \"archivo.lic\" [--force|-f]\n");
    fwrite(STDOUT, "  php generar_serial.php estado | ver | fingerprint | token | rotar-token | test\n");
}
$ES_CLI = (php_sapi_name() === 'cli');

function tool_token_ruta() { return __DIR__ . '/.tool_token'; }
function tool_token_esperado() {
    $t = getenv('SISGESNOM_TOOL_TOKEN');
    if (is_string($t) && $t !== '') return $t;
    $f = tool_token_ruta();
    if (is_file($f)) {
        $t = trim((string)@file_get_contents($f));
        if ($t !== '') return $t;
    }
    $t = bin2hex(random_bytes(16));
    @file_put_contents($f, $t, LOCK_EX);
    @chmod($f, 0600);
    return $t;
}
function tool_token_rotar() {
    $f = tool_token_ruta();
    $env = getenv('SISGESNOM_TOOL_TOKEN');
    if (is_string($env) && $env !== '') {
        return array('ok' => false, 'motivo' => 'env', 'token' => $env);
    }
    if (is_file($f)) @unlink($f);
    $nuevo = bin2hex(random_bytes(16));
    @file_put_contents($f, $nuevo, LOCK_EX);
    @chmod($f, 0600);
    return array('ok' => true, 'token' => $nuevo);
}
function tool_ips_permitidas() {
    $v = getenv('SISGESNOM_TOOL_IPS');
    if (!is_string($v) || trim($v) === '') return array();
    return array_filter(array_map('trim', explode(',', $v)));
}
function tool_ip_actual() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}
function tool_csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['sigesnom_csrf'])) $_SESSION['sigesnom_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['sigesnom_csrf'];
}
function tool_csrf_valido() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $s = isset($_SESSION['sigesnom_csrf']) ? $_SESSION['sigesnom_csrf'] : '';
    $p = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    return ($s !== '' && $p !== '' && hash_equals($s, $p));
}

/* =====================================================================
   MODO CLI
   ===================================================================== */
if ($ES_CLI) {
    $argv = isset($argv) ? $argv : array($_SERVER['argv'] ?? array());
    if (count($argv) < 2) { uso(); exit(1); }
    $modo = strtolower($argv[1]);
    switch ($modo) {
        case 'fingerprint': case 'huella':
            fwrite(STDOUT, "Huella de la PC: " . licencia_fingerprint_equipo() . "\n");
            exit(0);
        case 'token':
            fwrite(STDOUT, "Token de acceso web: " . tool_token_esperado() . "\n");
            exit(0);
        case 'rotar-token': case 'rotar': case 'nuevo-token':
            $r = tool_token_rotar();
            if (!$r['ok']) {
                fwrite(STDOUT, "El token está definido por variable de entorno SISGESNOM_TOOL_TOKEN.\n");
                fwrite(STDOUT, "Token actual: " . $r['token'] . "\n");
                exit(0);
            }
            fwrite(STDOUT, "Token rotado correctamente.\n");
            fwrite(STDOUT, "Nuevo token: " . $r['token'] . "\n");
            exit(0);
        case 'test': case 'self-test':
            $resultados = licencia_test();
            fwrite(STDOUT, "=== AUTO-TEST LICENCIA SISGESNOM ===\n");
            foreach ($resultados as $clave => $valor) {
                if (is_array($valor)) fwrite(STDOUT, $clave . ": " . json_encode($valor, JSON_UNESCAPED_UNICODE) . "\n");
                elseif (is_bool($valor)) fwrite(STDOUT, $clave . ": " . ($valor ? 'OK' : 'FALLO') . "\n");
                else fwrite(STDOUT, $clave . ": " . $valor . "\n");
            }
            $fallo = ($resultados['validar_ok'] !== true || $resultados['validar_nok'] !== true
                   || $resultados['cifrar_descifrar'] !== true
                   || (isset($resultados['acentos']['coinciden']) && $resultados['acentos']['coinciden'] !== true));
            exit($fallo ? 1 : 0);
        case 'ver': case 'leer': case 'info':
            fwrite(STDOUT, licencia_descripcion_instalada() . "\n");
            exit((licencia_leer() === null) ? 1 : 0);
        case 'estado':
            $leida  = licencia_leer();
            $activa = licencia_activada();
            $estado = 'ninguna';
            if ($leida !== null) $estado = ($activa) ? 'activa' : 'instalada';
            fwrite(STDOUT, "estado=" . $estado . "\n");
            fwrite(STDOUT, "hay_datos=" . (($leida !== null) ? '1' : '0') . "\n");
            fwrite(STDOUT, "activa=" . (($activa) ? '1' : '0') . "\n");
            exit(0);
        case 'eliminar': case 'borrar': case 'delete': case 'remove':
            if (!licencia_tiene_valor_guardado()) { fwrite(STDOUT, "No hay licencia instalada.\n"); exit(0); }
            if (!licencia_borrar()) { fwrite(STDERR, "ERROR: no se pudo eliminar.\n"); exit(1); }
            fwrite(STDOUT, "Licencia eliminada correctamente.\n");
            exit(0);
        case 'import': case 'instalar':
            $fuerza = false;
            for ($i = 2; $i < count($argv); $i++) {
                if (strtolower($argv[$i]) === '--force' || strtolower($argv[$i]) === '-f') $fuerza = true;
            }
            $ruta = null;
            for ($i = 2; $i < count($argv); $i++) {
                if (strtolower($argv[$i]) === '--force' || strtolower($argv[$i]) === '-f') continue;
                if (is_file($argv[$i])) { $ruta = $argv[$i]; break; }
                if ($ruta === null) $ruta = $argv[$i];
            }
            if ($ruta === null || !is_file($ruta)) { fwrite(STDERR, "ERROR: debe indicar la ruta del .lic.\n"); exit(1); }
            $datos = licencia_leer_archivo_lic($ruta);
            if ($datos === null) { fwrite(STDERR, "ERROR: el archivo .lic no es válido.\n"); exit(1); }
            $fpMaquina = licencia_fingerprint_machine();
            if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fpMaquina)) {
                fwrite(STDERR, "ERROR: este .lic fue emitido para OTRO equipo.\n"); exit(1);
            }
            if (licencia_activada()) licencia_borrar();
            $instalada = licencia_importar_archivo($ruta);
            if ($instalada === false) { fwrite(STDERR, "ERROR: no se pudo instalar.\n"); exit(1); }
            fwrite(STDOUT, "LICENCIA IMPORTADA CORRECTAMENTE\n");
            exit(0);
        default:
            if (count($argv) < 3) { fwrite(STDERR, "ERROR: debe indicar el usuario.\n"); exit(1); }
            $nombre  = $argv[1];
            $usuario = $argv[2];
            $tipo    = (count($argv) >= 4 && strtolower($argv[3]) !== 'export') ? strtoupper($argv[3]) : 'M';
            $info    = licencia_tipo_info($tipo);
            if ($info === null) { fwrite(STDERR, "ERROR: TIPO no válido ('$tipo').\n"); exit(1); }
            $serial  = licencia_generar_serial($nombre, $usuario, $tipo);
            fwrite(STDOUT, "Llave Serial: " . $serial . "\n");
            exit(0);
    }
}

/* =====================================================================
   MODO WEB
   ===================================================================== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

/* AJAX: verificar token */
if (isset($_GET['check']) && $_GET['check'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $t = isset($_GET['token']) ? (string)$_GET['token'] : '';
    $ok = ($t !== '' && hash_equals(tool_token_esperado(), $t));
    echo json_encode(array('ok' => $ok));
    exit;
}

$TOKEN_ESPERADO = tool_token_esperado();

$token_enviado = '';
if (isset($_GET['token']))  $token_enviado = (string)$_GET['token'];
if ($token_enviado === '' && isset($_POST['token'])) $token_enviado = (string)$_POST['token'];
if ($token_enviado === '' && isset($_COOKIE['sigesnom_tool_token'])) $token_enviado = (string)$_COOKIE['sigesnom_tool_token'];

$token_valido = ($token_enviado !== '' && hash_equals($TOKEN_ESPERADO, $token_enviado));

$ip_bloqueada = false;
$ips = tool_ips_permitidas();
if (!empty($ips) && !in_array(tool_ip_actual(), $ips, true)) $ip_bloqueada = true;

$accion = isset($_REQUEST['accion']) ? strtolower((string)$_REQUEST['accion']) : 'inicio';

/* === LOGOUT: SOLO borra la cookie. El token de disco se conserva. === */
if ($accion === 'logout') {
    setcookie('sigesnom_tool_token', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['sigesnom_tool_token']);
    @session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* === LOGIN (formulario en la misma web) === */
if ($ip_bloqueada || !$token_valido) {
    $login_error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_token'])) {
        $enviado = (string)$_POST['login_token'];
        if ($enviado !== '' && hash_equals($TOKEN_ESPERADO, $enviado)) {
            setcookie('sigesnom_tool_token', $enviado, 0, '/', '', false, true);
            $_COOKIE['sigesnom_tool_token'] = $enviado;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?token=' . urlencode($enviado));
            exit;
        } else {
            $login_error = 'El token introducido no es válido.';
        }
    }
    if ($ip_bloqueada) $login_error = 'Su dirección IP no está autorizada para usar esta herramienta.';

    http_response_code(403);
    ?>
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjIwIiBmaWxsPSIjMGQzYjY2Ii8+PHBhdGggZmlsbD0iIzdlZTc4NyIgZD0iTTUwIDE1TDg1IDI1djI1YzAgMjAtMTUgMzUtMzUgNDAtMjAtNS0zNS0yMC0zNS00MFYyNXoiLz48L3N2Zz4=">
<title>SISGESNOM · Acceso</title>
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/font-awesome6.4.0/css/all.min.css" rel="stylesheet">
<link href="css/sweetalert2.min.css" rel="stylesheet">
<style>
  :root { color-scheme: dark; }

  html[data-theme="dark"] {
    --bg-base: #0b0f14;
    --bg-glow1: rgba(30, 58, 138, .35);
    --bg-glow2: rgba(14, 165, 233, .18);
    --surface: rgba(28, 32, 38, .78);
    --surface-2: rgba(36, 41, 48, .65);
    --border-col: rgba(255,255,255,.08);
    --border-col-soft: rgba(255,255,255,.06);
    --text: #e5e7eb;
    --text-dim: #cbd5e1;
    --text-mute: #94a3b8;
    --accent: #60a5fa;
    --accent-soft: rgba(96, 165, 250, .12);
    --accent-border: rgba(96, 165, 250, .35);
    --input-bg: rgba(15, 18, 22, .7);
    --input-bg-focus: rgba(15, 18, 22, .95);
    --danger: #f87171;
    --danger-soft: rgba(248,113,113,.12);
    --danger-border: rgba(248,113,113,.4);
  }
  html[data-theme="light"] {
    --bg-base: #f3f6fb;
    --bg-glow1: rgba(191, 219, 254, .55);
    --bg-glow2: rgba(186, 230, 253, .55);
    --surface: rgba(255, 255, 255, .95);
    --surface-2: rgba(248, 250, 252, .9);
    --border-col: rgba(15, 23, 42, .18);
    --border-col-soft: rgba(15, 23, 42, .12);
    --text: #0f172a;
    --text-dim: #334155;
    --text-mute: #64748b;
    --accent: #2563eb;
    --accent-soft: rgba(37, 99, 235, .10);
    --accent-border: rgba(37, 99, 235, .45);
    --input-bg: #ffffff;
    --input-bg-focus: #ffffff;
    --danger: #dc2626;
    --danger-soft: rgba(220,38,38,.08);
    --danger-border: rgba(220,38,38,.4);
  }

  html, body { height: 100%; }
  body {
    margin: 0;
    background:
      radial-gradient(1200px 600px at 15% -10%, var(--bg-glow1), transparent 60%),
      radial-gradient(900px 500px at 100% 0%, var(--bg-glow2), transparent 55%),
      var(--bg-base);
    font-family: "Segoe UI Variable", "Segoe UI", system-ui, -apple-system, sans-serif;
    color: var(--text);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 1.25rem;
  }
  .login-card {
    width: 100%; max-width: 440px;
    background: var(--surface);
    border: 1px solid var(--border-col);
    border-radius: 16px;
    padding: 2rem;
    backdrop-filter: blur(24px) saturate(140%);
    -webkit-backdrop-filter: blur(24px) saturate(140%);
    box-shadow: 0 24px 80px rgba(0,0,0,.45);
  }
  html[data-theme="light"] .login-card {
    box-shadow: 0 12px 40px rgba(15,23,42,.12);
    border-color: rgba(15, 23, 42, .18);
  }
  .login-icon {
    width: 64px; height: 64px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--accent-soft);
    border: 1px solid var(--accent-border);
    color: var(--accent);
    font-size: 1.6rem;
    margin-bottom: 1rem;
  }
  .login-title { font-weight: 700; letter-spacing: -.01em; margin: 0; font-size: 1.35rem; }
  .login-sub { color: var(--text-mute); font-size: .92rem; margin-top: .25rem; margin-bottom: 1.5rem; }
  .form-control {
    background: var(--input-bg);
    border: 1px solid var(--border-col);
    color: var(--text);
    border-radius: 10px;
    padding: .75rem .9rem;
  }
  .form-control:focus {
    background: var(--input-bg-focus);
    border-color: var(--accent);
    box-shadow: 0 0 0 .2rem var(--accent-soft);
    color: var(--text);
  }
  label.form-label { color: var(--text-mute); font-size: .85rem; margin-bottom: .35rem; }
  .input-icon-wrap { position: relative; }
  .input-icon-wrap .form-control { padding-right: 2.6rem; }
  .input-icon-wrap .input-action {
    position: absolute; right: .5rem; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--text-mute);
    padding: .35rem .5rem; border-radius: 8px; cursor: pointer;
    font-size: 1rem;
  }
  .input-icon-wrap .input-action:hover { color: var(--text); background: var(--accent-soft); }
  .btn-login {
    background: linear-gradient(180deg, #3b82f6, #2563eb);
    border: none; border-radius: 10px;
    color: #fff; font-weight: 600;
    padding: .75rem 1rem;
    width: 100%;
    box-shadow: 0 8px 24px rgba(37,99,235,.35);
    transition: transform .1s ease, box-shadow .15s ease;
  }
  .btn-login:hover { background: linear-gradient(180deg, #60a5fa, #3b82f6); }
  .btn-login:active { transform: translateY(1px); }

  .alert-soft {
    background: var(--danger-soft);
    border: 1px solid var(--danger-border);
    color: var(--danger);
    border-radius: 10px;
    padding: .65rem .85rem;
    font-size: .88rem;
    margin-bottom: 1rem;
    display: flex; align-items: center; gap: .5rem;
  }
  .theme-toggle-login {
    position: fixed; top: 1rem; right: 1rem;
    background: var(--surface); color: var(--text-dim);
    border: 1px solid var(--border-col);
    border-radius: 10px; width: 40px; height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer;
  }
  .theme-toggle-login:hover { color: var(--text); background: var(--accent-soft); }
  .login-foot {
    color: var(--text-mute); font-size: .78rem;
    text-align: center; margin-top: 1.25rem; line-height: 1.5;
  }
  .login-foot code {
    background: var(--input-bg); color: var(--accent);
    padding: .15rem .35rem; border-radius: 6px;
  }
</style>
</head>
<body>

<button class="theme-toggle-login" id="loginTheme" title="Cambiar tema">
  <i class="fa-solid fa-moon" id="loginThemeIcon"></i>
</button>

<div class="login-card">
  <div class="text-center">
    <div class="login-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <h1 class="login-title">Acceso restringido</h1>
    <p class="login-sub">Introduzca el token para administrar licencias</p>
  </div>

  <?php if ($login_error !== ''): ?>
    <div class="alert-soft">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span><?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="mb-3">
      <label class="form-label">Token de acceso</label>
      <div class="input-icon-wrap">
        <input type="password" name="login_token" id="loginToken" class="form-control"
               placeholder="Pegue el token aquí" required autofocus>
        <button type="button" class="input-action" id="togglePw" title="Mostrar/Ocultar">
          <i class="fa-regular fa-eye"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn-login">
      <i class="fa-solid fa-right-to-bracket me-1"></i> Entrar
    </button>
  </form>
  <div class="login-foot">
    Acceso exclusivo para personal autorizado.<br>
    <code>Consulte un Administrador o al Autor</code>
  </div>
</div>

<script>
/* Tema persistente */
(function() {
  const html = document.documentElement;
  const btn = document.getElementById('loginTheme');
  const icon = document.getElementById('loginThemeIcon');
  const guardado = localStorage.getItem('sigesnom_theme');
  aplicar(guardado || 'dark');
  function aplicar(t) {
    html.setAttribute('data-theme', t);
    html.setAttribute('data-bs-theme', t);
    icon.className = (t === 'dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  }
  btn.addEventListener('click', function() {
    const actual = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    aplicar(actual);
    localStorage.setItem('sigesnom_theme', actual);
  });
})();

/* Toggle password */
document.getElementById('togglePw').addEventListener('click', function() {
  const input = document.getElementById('loginToken');
  const icon = this.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-regular fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-regular fa-eye';
  }
  input.focus();
});
</script>
</body>
</html><?php
    exit;
}

/* === Token válido: guardar cookie si viene por URL === */
if (isset($_GET['token']) && $_GET['token'] !== '') {
    setcookie('sigesnom_tool_token', (string)$_GET['token'], 0, '/', '', false, true);
}

$flash = array('ok' => array(), 'err' => array());

/* === ACCIÓN: rotar token (JSON) === */
if ($accion === 'rotar_token') {
    header('Content-Type: application/json; charset=utf-8');
    if (!tool_csrf_valido()) {
        echo json_encode(array('ok' => false, 'msg' => 'CSRF inválido.'));
        exit;
    }
    $r = tool_token_rotar();
    if (!$r['ok']) {
        echo json_encode(array(
            'ok' => false,
            'msg' => 'El token está definido por variable de entorno SISGESNOM_TOOL_TOKEN. Cámbiela manualmente.'
        ));
        exit;
    }
    setcookie('sigesnom_tool_token', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['sigesnom_tool_token']);
    echo json_encode(array('ok' => true, 'token' => $r['token']));
    exit;
}

/* === POST normales === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion !== 'rotar_token') {
    if (!tool_csrf_valido()) {
        $flash['err'][] = 'Token CSRF inválido. Recargue la página.';
    } else {
        switch ($accion) {
            case 'generar':
                $nombre   = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : '';
                $usuario  = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
                $tipo     = isset($_POST['tipo'])    ? strtoupper((string)$_POST['tipo']) : 'M';
                $destino  = isset($_POST['destino']) ? trim((string)$_POST['destino']) : '';
                $exportar = !empty($_POST['exportar']);

                if ($nombre === '' || $usuario === '') { $flash['err'][] = 'Nombre y Usuario son obligatorios.'; break; }
                $info = licencia_tipo_info($tipo);
                if ($info === null) { $flash['err'][] = 'Tipo de licencia no válido.'; break; }
                if ($destino !== '') {
                    if (strlen(licencia_normalizar_fingerprint($destino)) !== 20) {
                        $flash['err'][] = 'La huella destino no es válida.'; break;
                    }
                    $destino = licencia_formatear_fingerprint($destino);
                }
                try { $serial = licencia_generar_serial($nombre, $usuario, $tipo); }
                catch (Exception $e) { $flash['err'][] = 'Error: ' . $e->getMessage(); break; }
                $valida = licencia_validar_serial($nombre, $usuario, $serial);

                $_SESSION['gen_resultado'] = array(
                    'nombre'   => licencia_normalizar($nombre),
                    'usuario'  => licencia_normalizar($usuario),
                    'tipo'     => $tipo,
                    'tipo_nom' => $info['nombre'],
                    'serial'   => $serial,
                    'valida'   => $valida,
                    'destino'  => $destino,
                    'fecha'    => date('Y-m-d H:i:s'),
                );
                if ($exportar) {
                    $dir = __DIR__ . '/licencias_exportadas';
                    if (!is_dir($dir)) @mkdir($dir, 0700, true);
                    $archivo = $dir . '/licencia_' . preg_replace('/[^A-Za-z0-9_-]/', '_', licencia_normalizar($nombre)) . '_' . $tipo . '_' . date('Ymd_His') . '.lic';
                    $res = licencia_exportar($archivo, $nombre, $usuario, $tipo, null, ($destino === '' ? null : $destino));
                    if ($res === false) $flash['err'][] = 'No se pudo exportar el archivo .lic.';
                    else {
                        $_SESSION['gen_resultado']['archivo_nombre'] = basename($archivo);
                        $flash['ok'][] = 'Licencia exportada: ' . basename($archivo);
                    }
                }
                $flash['ok'][] = 'Serial generado correctamente.';
                break;

            case 'importar':
                if (empty($_FILES['archivo_lic']) || $_FILES['archivo_lic']['error'] !== UPLOAD_ERR_OK) {
                    $flash['err'][] = 'Debe subir un archivo .lic válido.'; break;
                }
                $tmp    = $_FILES['archivo_lic']['tmp_name'];
                $forzar = !empty($_POST['forzar']);
                $datos  = licencia_leer_archivo_lic($tmp);
                if ($datos === null) { $flash['err'][] = 'El archivo .lic no es válido.'; break; }
                $fpMaquina = licencia_fingerprint_machine();
                if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fpMaquina)) {
                    $flash['err'][] = 'Este .lic fue emitido para OTRO equipo.'; break;
                }
                if (licencia_activada()) {
                    if (!$forzar) { $flash['err'][] = 'Ya existe una licencia ACTIVA. Marque "Forzar reemplazo".'; break; }
                    if (!licencia_borrar()) { $flash['err'][] = 'No se pudo eliminar la licencia actual.'; break; }
                }
                $instalada = licencia_importar_archivo($tmp);
                if ($instalada === false) { $flash['err'][] = 'No se pudo instalar la licencia.'; break; }
                $flash['ok'][] = 'Licencia importada correctamente.';
                break;

            case 'instalar_manual':
                $nombre   = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : '';
                $usuario  = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
                $tipo     = isset($_POST['tipo'])    ? strtoupper((string)$_POST['tipo']) : 'M';
                $forzar   = !empty($_POST['forzar']);

                if ($nombre === '' || $usuario === '') {
                    $flash['err'][] = 'Nombre y Usuario son obligatorios.'; break;
                }
                $info = licencia_tipo_info($tipo);
                if ($info === null) { $flash['err'][] = 'Tipo de licencia no válido.'; break; }

                try { $serial = licencia_generar_serial($nombre, $usuario, $tipo); }
                catch (Exception $e) { $flash['err'][] = 'Error: ' . $e->getMessage(); break; }

                if (licencia_activada() && !$forzar) {
                    $flash['err'][] = 'Ya existe una licencia ACTIVA. Marque "Forzar reemplazo".'; break;
                }
                if (licencia_tiene_valor_guardado()) {
                    if (!licencia_borrar()) { $flash['err'][] = 'No se pudo eliminar la licencia actual.'; break; }
                }

                $fp_este = licencia_fingerprint_machine();
                $plano = json_encode(array(
                    'r' => licencia_normalizar($nombre),
                    'u' => licencia_normalizar($usuario),
                    'k' => licencia_normalizar_serial($serial),
                    'd' => time(),
                    'm' => $fp_este,
                ), JSON_UNESCAPED_UNICODE);
                $valor = licencia_cifrar($plano);
                $ok = (licencia_backend() === 'windows')
                    ? licencia_windows_escribir($valor)
                    : licencia_archivo_escribir($valor);

                if (!$ok) { $flash['err'][] = 'No se pudo guardar la licencia (permisos de escritura).'; break; }

                $_SESSION['accion_vista'] = 'instalar_manual';
                $lic = licencia_leer();
                $flash['ok'][] = 'Licencia instalada correctamente para ' . $lic['registro'] . '.';
                break;

            case 'instalar_codigo':
                $codigo = isset($_POST['codigo']) ? trim((string)$_POST['codigo']) : '';
                $forzar = !empty($_POST['forzar_codigo']);

                if ($codigo === '') { $flash['err'][] = 'Pegue o escriba el codigo de la licencia.'; break; }
                if (strlen($codigo) > 8192) { $flash['err'][] = 'El codigo pegado es demasiado largo.'; break; }

                $datos = licencia_leer_texto_lic($codigo);
                if ($datos === null) { $flash['err'][] = 'El texto no corresponde a una licencia valida. Verifique que se copio completo.'; break; }

                $fpMaquina = licencia_fingerprint_machine();
                if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fpMaquina)) {
                    $flash['err'][] = 'Este codigo fue emitido para OTRO equipo.'; break;
                }
                if (licencia_activada()) {
                    if (!$forzar) { $flash['err'][] = 'Ya existe una licencia ACTIVA. Marque "Forzar reemplazo".'; break; }
                    if (!licencia_borrar()) { $flash['err'][] = 'No se pudo eliminar la licencia actual.'; break; }
                }
                $ok = licencia_guardar($datos['registro'], $datos['usuario'], $datos['serial'], $datos['fecha_activacion']);
                if ($ok === false) { $flash['err'][] = 'No se pudo guardar la licencia (permisos de escritura).'; break; }

                $_SESSION['accion_vista'] = 'instalar_manual';
                $lic = licencia_leer();
                $flash['ok'][] = 'Licencia instalada desde el codigo para ' . $lic['registro']
                    . ' (' . $lic['info']['nombre'] . ').';
                break;

            case 'eliminar':
                if (!licencia_tiene_valor_guardado()) { $flash['ok'][] = 'No hay licencia instalada.'; break; }
                if (!licencia_borrar()) { $flash['err'][] = 'No se pudo eliminar la licencia.'; break; }
                $flash['ok'][] = 'Licencia eliminada correctamente.';
                break;
        }
    }
}

$estado = array(
    'leida'  => licencia_leer(),
    'activa' => licencia_activada(),
    'ruta'   => licencia_ruta_almacenamiento(),
);
$fpEquipo = licencia_fingerprint_equipo();
$tipos    = licencia_tipos();
$gen      = isset($_SESSION['gen_resultado']) ? $_SESSION['gen_resultado'] : null;
$csrf     = tool_csrf_token();
$token    = htmlspecialchars(tool_token_esperado(), ENT_QUOTES, 'UTF-8');

$accion_vista_inicial = isset($_SESSION['accion_vista']) ? $_SESSION['accion_vista'] : 'generar';
unset($_SESSION['accion_vista']);

if ($accion === 'test' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(licencia_test(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/* =====================================================================
   VALIDAR EN VIVO EL TEXTO DE LA LICENCIA (sin instalarlo)
   El descifrado y la comprobacion del serial ocurren aqui, en el
   servidor: el secreto nunca llega al navegador.
   ===================================================================== */
if ($accion === 'validar_texto' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $resp = array('ok' => false, 'mensaje' => 'Pegue o escriba el codigo de la licencia.');
    $codigo = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo']))
        ? trim((string)$_POST['codigo'])
        : '';

    if ($codigo !== '') {
        if (strlen($codigo) > 8192) {
            $resp['mensaje'] = 'El texto es demasiado largo para ser una licencia.';
        } else {
            $datos = licencia_leer_texto_lic($codigo);
            if ($datos === null) {
                $resp['mensaje'] = 'El texto no corresponde a una licencia valida. Verifique que se copio completo.';
            } else {
                $fpMaquina = licencia_fingerprint_machine();
                $esGenerica = ($datos['huella'] === '');
                $otraPc     = !$esGenerica && !hash_equals($datos['huella'], $fpMaquina);
                $vence      = licencia_vencimiento($datos);

                $resp['ok']      = true;
                $resp['mensaje'] = 'Licencia valida.';
                $resp['datos']   = array(
                    'registro' => $datos['registro'],
                    'usuario'  => $datos['usuario'],
                    'serial'   => licencia_formatear_serial($datos['serial']),
                    'tipo'     => $datos['info']['nombre'] . ' (' . $datos['tipo'] . ')',
                    'periodo'  => ($datos['info']['meses'] === null) ? 'Permanente' : $datos['info']['meses'] . ' meses',
                    'vence'    => ($vence === null) ? 'Sin vencimiento (Permanente)' : date('d/m/Y', $vence),
                    'generica' => $esGenerica,
                    'otraPc'   => $otraPc,
                    'huella'   => $esGenerica ? '' : licencia_formatear_fingerprint($datos['huella']),
                );
            }
        }
    }

    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($accion === 'descargar' && !empty($_GET['archivo'])) {
    $dir  = __DIR__ . '/licencias_exportadas';
    $nom  = basename((string)$_GET['archivo']);
    $ruta = $dir . '/' . $nom;
    if (is_file($ruta) && strpos(realpath($ruta), realpath($dir)) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$flashJson = json_encode($flash, JSON_UNESCAPED_UNICODE);
?><!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SISGESNOM · Generador de Licencias</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjIwIiBmaWxsPSIjMGQzYjY2Ii8+PHBhdGggZmlsbD0iIzdlZTc4NyIgZD0iTTUwIDE1TDg1IDI1djI1YzAgMjAtMTUgMzUtMzUgNDAtMjAtNS0zNS0yMC0zNS00MFYyNXoiLz48L3N2Zz4=">
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/font-awesome6.4.0/css/all.min.css" rel="stylesheet">
<link href="css/sweetalert2.min.css" rel="stylesheet">
<style>
  :root { color-scheme: light dark; }
  html[data-theme="dark"] {
    --bg-base: #0b0f14;
    --bg-glow1: rgba(30, 58, 138, .35);
    --bg-glow2: rgba(14, 165, 233, .18);
    --surface: rgba(28, 32, 38, .72);
    --surface-2: rgba(36, 41, 48, .55);
    --surface-3: rgba(44, 50, 58, .65);
    --border-col: rgba(255,255,255,.08);
    --border-col-soft: rgba(255,255,255,.06);
    --text: #e5e7eb;
    --text-dim: #cbd5e1;
    --text-mute: #94a3b8;
    --accent: #60a5fa;
    --accent-soft: rgba(96, 165, 250, .12);
    --accent-border: rgba(96, 165, 250, .35);
    --input-bg: rgba(15, 18, 22, .7);
    --input-bg-focus: rgba(15, 18, 22, .95);
    --serial-color: #7dd3fc;
    --fp-bg: rgba(250, 204, 21, .08);
    --fp-color: #fde68a;
    --fp-border: rgba(250, 204, 21, .5);
    --topbar-bg: rgba(15, 18, 22, .65);
    --sidebar-bg: rgba(20, 24, 30, .85);
    --footer-bg: rgba(15, 18, 22, .55);
    --danger: #f87171;
  }
  html[data-theme="light"] {
    --bg-base: #f3f6fb;
    --bg-glow1: rgba(191, 219, 254, .55);
    --bg-glow2: rgba(186, 230, 253, .55);
    --surface: rgba(255, 255, 255, .92);
    --surface-2: rgba(248, 250, 252, .9);
    --surface-3: rgba(241, 245, 249, .95);
    --border-col: rgba(15, 23, 42, .18);
    --border-col-soft: rgba(15, 23, 42, .10);
    --text: #0f172a;
    --text-dim: #334155;
    --text-mute: #64748b;
    --accent: #2563eb;
    --accent-soft: rgba(37, 99, 235, .10);
    --accent-border: rgba(37, 99, 235, .45);
    --input-bg: #ffffff;
    --input-bg-focus: #ffffff;
    --serial-color: #0c4a6e;
    --fp-bg: rgba(250, 204, 21, .18);
    --fp-color: #854d0e;
    --fp-border: rgba(202, 138, 4, .5);
    --topbar-bg: rgba(255, 255, 255, .85);
    --sidebar-bg: rgba(255, 255, 255, .92);
    --footer-bg: rgba(255, 255, 255, .7);
    --danger: #dc2626;
  }

  html, body { height: 100%; }
  body {
    margin: 0;
    background:
      radial-gradient(1200px 600px at 15% -10%, var(--bg-glow1), transparent 60%),
      radial-gradient(900px 500px at 100% 0%, var(--bg-glow2), transparent 55%),
      var(--bg-base);
    font-family: "Segoe UI Variable", "Segoe UI", system-ui, -apple-system, sans-serif;
    color: var(--text);
    min-height: 100vh;
    transition: background .25s ease, color .2s ease;
  }

  .layout { display: flex; min-height: 100vh; }
  .sidebar {
    width: 260px; flex-shrink: 0;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border-col);
    backdrop-filter: blur(24px) saturate(140%);
    -webkit-backdrop-filter: blur(24px) saturate(140%);
    padding: 1.25rem .9rem;
    display: flex; flex-direction: column; gap: .35rem;
    transition: width .2s ease, transform .25s ease;
  }
  .sidebar .brand {
    display: flex; align-items: center; gap: .6rem;
    padding: .35rem .5rem .9rem .5rem;
    border-bottom: 1px solid var(--border-col-soft);
    margin-bottom: .75rem;
  }
  .sidebar .brand i { font-size: 1.4rem; color: var(--accent); }
  .sidebar .brand .title {
    font-weight: 700; letter-spacing: -.01em; font-size: 1.02rem;
    background: linear-gradient(90deg, var(--accent), #a78bfa);
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  .sidebar .brand .subtitle { font-size: .72rem; color: var(--text-mute); }
  .sidebar .nav-btn {
    display: flex; align-items: center; gap: .65rem;
    padding: .65rem .8rem; border-radius: 10px;
    background: transparent; border: 1px solid transparent;
    color: var(--text-dim); cursor: pointer; text-align: left;
    font-size: .92rem; width: 100%;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
  }
  .sidebar .nav-btn i { width: 1.15rem; text-align: center; font-size: 1rem; }
  .sidebar .nav-btn:hover { background: var(--accent-soft); color: var(--text); }
  .sidebar .nav-btn.active {
    background: var(--accent-soft);
    border-color: var(--accent-border);
    color: var(--text);
    box-shadow: 0 2px 10px rgba(96,165,250,.18);
  }
  .sidebar .nav-btn.danger { color: var(--danger); }
  .sidebar .nav-btn.danger:hover { background: rgba(248,113,113,.12); color: var(--danger); }
  .sidebar .sidebar-sep {
    height: 1px; background: var(--border-col-soft);
    margin: .5rem .25rem;
  }
  .sidebar .spacer { flex: 1; }
  .sidebar .side-foot {
    font-size: .74rem; color: var(--text-mute);
    border-top: 1px solid var(--border-col-soft);
    padding-top: .75rem; margin-top: .75rem; line-height: 1.35;
  }

  .main-col { flex: 1; min-width: 0; display: flex; flex-direction: column; }

  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: .65rem 1.1rem;
    background: var(--topbar-bg);
    border-bottom: 1px solid var(--border-col-soft);
    backdrop-filter: blur(18px) saturate(140%);
    -webkit-backdrop-filter: blur(18px) saturate(140%);
    position: sticky; top: 0; z-index: 20;
    gap: .5rem;
  }
  .topbar .page-title { font-weight: 600; letter-spacing: -.01em; }
  .topbar .page-title small { color: var(--text-mute); font-weight: 400; }
  .topbar .toolbar { display: flex; align-items: center; gap: .5rem; }
  .pill {
    background: var(--accent-soft);
    border: 1px solid var(--accent-border);
    color: var(--accent);
    padding: .28rem .65rem; border-radius: 999px;
    font-size: .78rem;
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    letter-spacing: .5px;
  }
  .icon-btn {
    background: transparent; border: 1px solid var(--border-col);
    color: var(--text-dim); border-radius: 10px;
    width: 38px; height: 38px;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
  }
  .icon-btn:hover { background: var(--accent-soft); color: var(--text); border-color: var(--accent-border); }
  .icon-btn.danger:hover { background: rgba(248,113,113,.12); color: var(--danger); border-color: rgba(248,113,113,.5); }

  .content { padding: 1.25rem 1.25rem 1.5rem; flex: 1; }

  .surface {
    background: var(--surface);
    border: 1px solid var(--border-col);
    border-radius: 14px;
    backdrop-filter: blur(24px) saturate(140%);
    -webkit-backdrop-filter: blur(24px) saturate(140%);
    box-shadow: 0 12px 40px rgba(0,0,0,.28);
  }
  html[data-theme="light"] .surface {
    box-shadow: 0 8px 30px rgba(15,23,42,.10);
    border-color: rgba(15, 23, 42, .18);
  }

  .form-control, .form-select {
    background: var(--input-bg);
    border: 1px solid var(--border-col);
    color: var(--text); border-radius: 10px;
  }
  .form-control::placeholder { color: var(--text-mute); }
  textarea.form-control {
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    font-size: .85rem; line-height: 1.5; resize: vertical;
    word-break: break-all;
  }

  /* Validación en vivo del texto de la licencia */
  .lic-feedback {
    display: none; margin-top: .7rem; padding: 0; overflow: hidden;
    border: 1px solid var(--border-col); border-left-width: 4px;
    border-radius: 12px; background: var(--input-bg);
    font-size: .88rem; line-height: 1.5;
    box-shadow: 0 6px 20px rgba(0,0,0,.14);
  }
  .lic-feedback.visible { display: block; animation: licFade .18s ease-out; }
  @keyframes licFade {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: none; }
  }
  .lic-feedback.ok   { border-left-color: #22c55e; }
  .lic-feedback.warn { border-left-color: #f59e0b; }
  .lic-feedback.bad  { border-left-color: var(--danger, #ef4444); }

  .lic-feedback-head {
    display: flex; align-items: center; gap: .6rem;
    padding: .7rem .9rem; font-weight: 600;
  }
  .lic-ico {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.6rem; height: 1.6rem; border-radius: 50%;
    flex: 0 0 auto; font-size: .82rem; color: #0b1220;
  }
  .lic-feedback.ok   .lic-ico { background: #4ade80; }
  .lic-feedback.warn .lic-ico { background: #fbbf24; }
  .lic-feedback.bad  .lic-ico { background: var(--danger, #ef4444); color: #fff; }
  .lic-feedback.ok   .lic-feedback-head { background: rgba(34,197,94,.12);  color: #4ade80; }
  .lic-feedback.warn .lic-feedback-head { background: rgba(245,158,11,.14); color: #fbbf24; }
  .lic-feedback.bad  .lic-feedback-head { background: var(--danger-soft);   color: var(--danger); }

  .lic-feedback-body { padding: .8rem .9rem .9rem; }
  .lic-tiles {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(146px, 1fr)); gap: .5rem;
  }
  .lic-tile {
    min-width: 0; padding: .45rem .6rem .5rem;
    background: var(--surface); border: 1px solid var(--border-col); border-radius: 9px;
  }
  .lic-tile .k {
    display: block; margin-bottom: .12rem;
    color: var(--text-mute); font-size: .67rem;
    text-transform: uppercase; letter-spacing: .05em;
  }
  .lic-tile .v { display: block; color: var(--text); font-weight: 600; word-break: break-word; }
  .lic-tile.mono .v {
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    font-weight: 500; font-size: .78rem; letter-spacing: .4px;
  }
  .lic-tile.val-ok   .v { color: #4ade80; }
  .lic-tile.val-warn .v { color: #fbbf24; }

  .lic-codigos {
    display: flex; flex-direction: column; gap: .4rem; margin-top: .55rem;
  }
  .lic-codigo {
    display: flex; align-items: center; gap: .55rem;
    padding: .45rem .6rem; border: 1px dashed var(--border-col);
    border-radius: 9px; background: var(--input-bg-focus, rgba(127,127,127,.10));
  }
  .lic-codigo .k {
    flex: 0 0 auto; min-width: 3.9rem;
    color: var(--text-mute); font-size: .67rem;
    text-transform: uppercase; letter-spacing: .05em;
  }
  .lic-codigo .v {
    min-width: 0; color: var(--text); font-size: .8rem; letter-spacing: .8px;
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace; word-break: break-all;
  }
  .form-control:focus, .form-select:focus {
    background: var(--input-bg-focus);
    border-color: var(--accent);
    box-shadow: 0 0 0 .2rem var(--accent-soft);
    color: var(--text);
  }
  .form-check-input { background-color: var(--input-bg); border-color: var(--border-col); }
  .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
  label.form-label { color: var(--text-mute); }

  .btn-primary {
    background: linear-gradient(180deg, #3b82f6, #2563eb);
    border: none; border-radius: 10px;
    box-shadow: 0 6px 18px rgba(37,99,235,.28);
  }
  .btn-primary:hover { background: linear-gradient(180deg, #60a5fa, #3b82f6); }
  .btn-outline-primary { color: var(--accent); border-color: var(--accent-border); border-radius: 10px; }
  .btn-outline-primary:hover { background: var(--accent-soft); color: var(--text); border-color: var(--accent); }
  .btn-outline-danger { border-radius: 10px; }
  .btn-outline-success { border-radius: 10px; }

  .serial-box {
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    font-size: 1.2rem; letter-spacing: 2px;
    background: var(--input-bg-focus);
    color: var(--serial-color);
    padding: .9rem 1rem; border-radius: 12px;
    word-break: break-all; user-select: all;
    border: 1px solid var(--accent-border);
  }
  .fp-box {
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    font-size: 1.05rem; letter-spacing: 1.5px;
    background: var(--fp-bg); color: var(--fp-color);
    padding: .65rem .9rem; border-radius: 12px;
    border: 1px dashed var(--fp-border);
    user-select: all;
    word-break: break-all;
  }
  .table { color: var(--text); margin-bottom: 0; }
  .table > :not(caption) > * > * { background: transparent; border-bottom-color: var(--border-col-soft); }
  .table th { color: var(--text-mute); font-weight: 500; }
  code { color: var(--accent); }

  .footer {
    padding: .85rem 1.25rem;
    background: var(--footer-bg);
    border-top: 1px solid var(--border-col-soft);
    color: var(--text-mute);
    font-size: .82rem;
    display: flex; align-items: center; justify-content: space-between;
    backdrop-filter: blur(14px) saturate(140%);
    -webkit-backdrop-filter: blur(14px) saturate(140%);
    gap: .5rem; flex-wrap: wrap;
  }
  .footer a { color: var(--accent); text-decoration: none; }
  .footer a:hover { text-decoration: underline; }

  .swal2-popup {
    background: var(--surface) !important;
    color: var(--text) !important;
    border-radius: 14px !important;
    border: 1px solid var(--border-col) !important;
    font-family: "Segoe UI Variable", "Segoe UI", system-ui, sans-serif !important;
  }
  .swal2-title { color: var(--text) !important; }
  .swal2-html-container { color: var(--text-dim) !important; }
  .swal2-confirm, .swal2-cancel, .swal2-deny {
    border-radius: 10px !important; font-weight: 500 !important; box-shadow: none !important;
  }
  .swal2-icon { display: none !important; }

  .token-result {
    position: relative;
    display: block;
    margin: .6rem 0;
    padding: .75rem 3rem .75rem .9rem;
    background: var(--input-bg-focus);
    border: 1px solid var(--accent-border);
    border-radius: 10px;
    color: var(--serial-color);
    font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
    font-size: .92rem;
    line-height: 1.5;
    word-break: break-all;
    text-align: left;
  }
  html[data-theme="light"] .token-result {
    background: #f8fafc;
    color: #0c4a6e;
    border-color: rgba(37, 99, 235, .45);
  }
  .token-result .token-copy {
    position: absolute;
    top: 50%; right: .5rem; transform: translateY(-50%);
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px;
    background: var(--accent-soft);
    border: 1px solid var(--accent-border);
    color: var(--accent);
    border-radius: 8px;
    cursor: pointer;
    font-size: .95rem;
    transition: background .15s ease, color .15s ease, border-color .15s ease, transform .1s ease;
  }
  .token-result .token-copy:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
  }
  .token-result .token-copy:active { transform: translateY(-50%) scale(.95); }
  .token-result .token-copy.copied {
    background: rgba(74, 222, 128, .18);
    color: #22c55e;
    border-color: rgba(74, 222, 128, .55);
  }
  html[data-theme="light"] .token-result .token-copy.copied {
    background: rgba(34, 197, 94, .12);
    color: #16a34a;
    border-color: rgba(34, 197, 94, .55);
  }

  @media (max-width: 900px) {
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      transform: translateX(-100%); z-index: 40;
    }
    .sidebar.open { transform: translateX(0); }
    .backdrop {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.5); z-index: 30;
    }
    .backdrop.show { display: block; }
    .topbar .page-title small { display: none; }
    .content { padding: 1rem; }
  }
  @media (max-width: 576px) {
    .serial-box { font-size: 1rem; letter-spacing: 1px; }
    .fp-box { font-size: .95rem; letter-spacing: 1px; }
  }

  /* ========== Hoja carta (8.5 x 11 pulg) para impresión / PDF ========== */
  #hojaLicencia {
    position: fixed;
    left: -9999px; top: 0;
    width: 8.5in;
    height: 11in;
    overflow: hidden;
    background: #ffffff;
    color: #0f172a;
    padding: 0.6in 0.7in 0.5in 0.7in;
    font-family: "Segoe UI Variable", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    font-size: 10.5pt;
    line-height: 1.45;
    box-sizing: border-box;
  }
  #hojaLicencia .hl-header {
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 3px solid #0d3b66;
    padding-bottom: 10px;
    margin-bottom: 16px;
  }
  #hojaLicencia .hl-marca { display: flex; align-items: center; gap: 12px; }
  #hojaLicencia .hl-marca img {
    width: 52px; height: 52px; object-fit: contain;
    border-radius: 10px; background: #ffffff;
  }
  #hojaLicencia .hl-marca .hl-nombre {
    font-size: 18pt; font-weight: 800; color: #0d3b66;
    letter-spacing: -.3px; line-height: 1;
  }
  #hojaLicencia .hl-marca .hl-sub { font-size: 9pt; color: #64748b; margin-top: 4px; }
  /* Simbolo de marca registrada */
  #hojaLicencia .hl-reg {
    font-size: .45em;
    font-weight: 700;
    vertical-align: super;
    line-height: 0;
    margin-left: 1px;
    letter-spacing: 0;
  }
  #hojaLicencia .hl-meta {
    text-align: right; font-size: 8.5pt; color: #475569; line-height: 1.4;
  }
  #hojaLicencia .hl-meta .hl-tag {
    display: inline-block; padding: 3px 10px;
    background: #0d3b66; color: #fff; border-radius: 999px;
    font-size: 8pt; letter-spacing: .5px; font-weight: 600;
    margin-bottom: 4px;
  }
  #hojaLicencia h1 {
    font-size: 15pt; font-weight: 700; color: #0d3b66;
    margin: 0 0 2px 0; letter-spacing: -.2px;
  }
  #hojaLicencia .hl-desc { color: #64748b; font-size: 9.5pt; margin-bottom: 16px; }
  #hojaLicencia .hl-bloque {
    border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 12px 14px; margin-bottom: 12px; background: #f8fafc;
  }
  #hojaLicencia .hl-bloque-titulo {
    font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px;
    color: #64748b; font-weight: 700; margin-bottom: 8px;
  }
  #hojaLicencia .hl-fila {
    display: flex; border-bottom: 1px dashed #cbd5e1;
    padding: 5px 0; font-size: 10pt;
  }
  #hojaLicencia .hl-fila:last-child { border-bottom: none; }
  #hojaLicencia .hl-fila .hl-k { width: 42%; color: #475569; font-weight: 600; }
  #hojaLicencia .hl-fila .hl-v { flex: 1; color: #0f172a; word-break: break-word; }
  /* Debe ir DESPUES de .hl-fila (misma especificidad) para poder ocultarlo */
  #hojaLicencia .hl-fila.hl-oculto { display: none; }
  #hojaLicencia .hl-oculto { display: none; }
  #hojaLicencia .hl-serial {
    font-family: "Cascadia Code", "Consolas", ui-monospace, monospace;
    font-size: 14pt; letter-spacing: 2px; font-weight: 700;
    color: #0d3b66; background: #eef6ff;
    border: 1px dashed #60a5fa; border-radius: 8px;
    padding: 10px 14px; text-align: center;
    word-break: break-all; margin-top: 2px;
  }
  #hojaLicencia .hl-badge {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: 8.5pt; font-weight: 700; letter-spacing: .3px;
  }
  #hojaLicencia .hl-badge.ok { background: #dcfce7; color: #15803d; }
  #hojaLicencia .hl-badge.no { background: #fee2e2; color: #b91c1c; }

  #hojaLicencia .hl-firma {
    margin-top: 22px; display: flex; justify-content: space-between; gap: 30px;
  }
  #hojaLicencia .hl-firma .hl-linea {
    flex: 1; border-top: 1px solid #94a3b8; text-align: center;
    font-size: 8.5pt; color: #475569; padding-top: 5px;
  }
  #hojaLicencia .hl-footer {
    position: absolute;
    bottom: 0.35in; left: 0.7in; right: 0.7in;
    border-top: 1px solid #cbd5e1;
    padding-top: 8px; font-size: 7.5pt; color: #94a3b8;
    display: flex; justify-content: space-between;
  }
  #hojaLicencia .hl-watermark {
    position: absolute;
    top: 42%; left: 0; right: 0;
    text-align: center; font-size: 80pt; font-weight: 900;
    color: #0d3b66;
    opacity: .25;
    transform: rotate(-18deg);
    letter-spacing: 14px;
    white-space: nowrap;
    pointer-events: none; user-select: none;
    z-index: 2;
  }
  /* Contenido por debajo de la marca de agua (z-index 2) */
  #hojaLicencia .hl-header,
  #hojaLicencia h1,
  #hojaLicencia .hl-desc,
  #hojaLicencia .hl-bloque,
  #hojaLicencia .hl-firma { position: relative; z-index: 1; }
  #hojaLicencia .hl-footer { z-index: 1; }

  @media print {
    @page { size: letter portrait; margin: 0; }
    html, body { height: auto !important; overflow: visible !important; background: #fff !important; }
    .layout, .topbar, .sidebar, .footer, .backdrop { display: none !important; }
    #hojaLicencia {
      display: block !important;
      position: absolute !important;
      left: 0 !important;
      top: 0 !important;
      width: 8.5in !important;
      height: 11in !important;
      min-height: 11in !important;
      max-height: 11in !important;
      overflow: hidden !important;
      box-shadow: none !important;
      page-break-after: avoid !important;
      page-break-inside: avoid !important;
    }
  }
</style>
</head>
<body>

<div class="layout">

  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <i class="fa-solid fa-key"></i>
      <div>
        <div class="title">SISGESNOM</div>
        <div class="subtitle">Generador de Licencias</div>
      </div>
    </div>

    <button class="nav-btn" data-target="generar"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar</button>
    <button class="nav-btn" data-target="estado"><i class="fa-solid fa-circle-info"></i> Estado</button>
    <button class="nav-btn" data-target="importar"><i class="fa-solid fa-file-import"></i> Importar</button>
    <button class="nav-btn" data-target="instalar_manual"><i class="fa-solid fa-key"></i> Instalar manual</button>
    <button class="nav-btn" data-target="huella"><i class="fa-solid fa-fingerprint"></i> Huella</button>
    <button class="nav-btn" data-target="test"><i class="fa-solid fa-vial-circle-check"></i> Auto-test</button>

    <div class="sidebar-sep"></div>
    <button class="nav-btn" id="sideRotar"><i class="fa-solid fa-arrows-rotate"></i> Rotar token</button>
    <button class="nav-btn danger" id="sideLogout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</button>

    <div class="spacer"></div>
    <div class="side-foot">
      <div><i class="fa-solid fa-circle-check me-1" style="color:#4ade80"></i> v2.7.0</div>
      <div>Licencia administrada localmente</div>
    </div>
  </aside>

  <div class="backdrop" id="backdrop"></div>

  <div class="main-col">

    <header class="topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="icon-btn d-lg-none" id="btnMenu" title="Menú"><i class="fa-solid fa-bars"></i></button>
        <div class="page-title" id="pageTitle">Generar <small class="ms-2">— Cree una nueva llave serial</small></div>
      </div>
      <div class="toolbar">
        <span class="pill d-none d-md-inline-flex align-items-center">
          <i class="fa-solid fa-microchip me-1"></i><?= htmlspecialchars($fpEquipo, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <button class="icon-btn" id="btnTheme" title="Cambiar tema">
          <i class="fa-solid fa-moon" id="themeIcon"></i>
        </button>
        <button class="icon-btn" id="btnRotar" title="Eliminar token actual y generar uno nuevo">
          <i class="fa-solid fa-arrows-rotate"></i>
        </button>
        <button class="icon-btn danger" id="btnLogout" title="Cerrar sesión">
          <i class="fa-solid fa-right-from-bracket"></i>
        </button>
      </div>
    </header>

    <main class="content">

      <section class="section" id="section-generar">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="surface p-4">
              <h6 class="mb-3"><i class="fa-solid fa-wand-magic-sparkles me-2" style="color:var(--accent)"></i>Datos de la licencia</h6>
              <form method="post" id="formGenerar">
                <input type="hidden" name="accion" value="generar">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="mb-3">
                  <label class="form-label small">Nombre de Registro</label>
                  <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($gen['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: PDL Visiones Ntas.">
                </div>
                <div class="mb-3">
                  <label class="form-label small">Usuario del Registro</label>
                  <input type="text" name="usuario" class="form-control" required value="<?= htmlspecialchars($gen['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Periquito Pérez Pérez">
                </div>
                <div class="mb-3">
                  <label class="form-label small">Tipo de Licencia</label>
                  <select name="tipo" class="form-select">
                    <?php foreach ($tipos as $t => $i): ?>
                      <option value="<?= $t ?>" <?= (($gen['tipo'] ?? 'M') === $t ? 'selected' : '') ?>><?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= $t ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label small">Huella destino <span style="color:var(--text-mute)">(opcional)</span></label>
                  <input type="text" name="destino" class="form-control" value="<?= htmlspecialchars($gen['destino'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="XXXXX-XXXXX-XXXXX-XXXXX">
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="exportar" id="chkExportar" checked>
                  <label class="form-check-label" for="chkExportar">Exportar también archivo .lic</label>
                </div>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-key me-1"></i> Generar serial</button>
                  <button type="button" class="btn btn-outline-primary" onclick="limpiarFormulario('formGenerar')" title="Limpiar campos">
                    <i class="fa-solid fa-eraser me-1"></i> Limpiar
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-6">
            <?php if ($gen): ?>
              <div class="surface p-4">
                <h6 class="mb-3"><i class="fa-solid fa-circle-check me-2" style="color:#4ade80"></i>Resultado</h6>
                <p class="mb-1 small" style="color:var(--text-mute)">Registro</p>
                <p class="mb-3"><?= htmlspecialchars($gen['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-1 small" style="color:var(--text-mute)">Usuario</p>
                <p class="mb-3"><?= htmlspecialchars($gen['usuario'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-1 small" style="color:var(--text-mute)">Tipo</p>
                <p class="mb-3"><?= htmlspecialchars($gen['tipo_nom'], ENT_QUOTES, 'UTF-8') ?> <span style="color:var(--text-mute)">(<?= $gen['tipo'] ?>)</span></p>
                <p class="mb-1 small" style="color:var(--text-mute)">Destino</p>
                <p class="mb-3"><?= $gen['destino'] === '' ? '<span style="color:var(--text-mute)">Genérica</span>' : htmlspecialchars($gen['destino'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-1 small" style="color:var(--text-mute)">Válida</p>
                <p class="mb-3"><?= $gen['valida'] ? '<span class="badge bg-success">SÍ</span>' : '<span class="badge bg-danger">NO</span>' ?></p>
                <p class="mb-1 small" style="color:var(--text-mute)">Fecha de generación</p>
                <p class="mb-3" id="fechaGeneracion"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($gen['fecha'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></p>
                <label class="form-label small">Llave Serial</label>
                <div class="serial-box" id="serialBox"><?= htmlspecialchars($gen['serial'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="copiarLicencia(<?= htmlspecialchars(json_encode(array(
                              'registro' => $gen['nombre'],
                              'usuario'  => $gen['usuario'],
                              'tipo'     => $gen['tipo'],
                              'tipo_nom' => $gen['tipo_nom'],
                              'serial'   => $gen['serial'],
                              'valida'   => $gen['valida'],
                              'destino'  => $gen['destino'],
                              'fecha'    => date('d/m/Y H:i:s', strtotime($gen['fecha'] ?? 'now')),
                          ), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>);"
                          title="Copiar todos los datos de la licencia">
                    <i class="fa-regular fa-copy me-1"></i> Copiar
                  </button>

                  <button class="btn btn-sm btn-outline-primary"
                          onclick="prepararHojaLicencia(<?= htmlspecialchars(json_encode(array(
                              'registro' => $gen['nombre'],
                              'usuario'  => $gen['usuario'],
                              'tipo'     => $gen['tipo'],
                              'tipo_nom' => $gen['tipo_nom'],
                              'serial'   => $gen['serial'],
                              'valida'   => $gen['valida'],
                              'destino'  => $gen['destino'],
                              'fecha'    => date('d/m/Y H:i:s', strtotime($gen['fecha'] ?? 'now')),
                          ), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>); imprimirLicencia();">
                    <i class="fa-solid fa-print me-1"></i> Imprimir
                  </button>

                  <button class="btn btn-sm btn-outline-primary"
                          onclick="prepararHojaLicencia(<?= htmlspecialchars(json_encode(array(
                              'registro' => $gen['nombre'],
                              'usuario'  => $gen['usuario'],
                              'tipo'     => $gen['tipo'],
                              'tipo_nom' => $gen['tipo_nom'],
                              'serial'   => $gen['serial'],
                              'valida'   => $gen['valida'],
                              'destino'  => $gen['destino'],
                              'fecha'    => date('d/m/Y H:i:s', strtotime($gen['fecha'] ?? 'now')),
                          ), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>); exportarPDFLicencia();">
                    <i class="fa-solid fa-file-pdf me-1"></i> Exportar PDF
                  </button>

                  <?php if (!empty($gen['archivo_nombre'])): ?>
                    <a class="btn btn-sm btn-outline-success"
                       href="?accion=descargar&archivo=<?= urlencode($gen['archivo_nombre']) ?>&token=<?= $token ?>">
                      <i class="fa-solid fa-download me-1"></i> Descargar .lic
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="surface p-4 text-center py-5">
                <i class="fa-solid fa-key fa-3x mb-3" style="color:var(--accent); opacity:.6"></i>
                <p style="color:var(--text-mute)" class="mb-0">Complete el formulario para generar la llave serial.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="section d-none" id="section-estado">
        <div class="surface p-4">
          <h6 class="mb-3"><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Licencia instalada en este equipo</h6>
          <?php if ($estado['leida'] === null): ?>
            <p style="color:var(--text-mute)" class="mb-0"><i class="fa-solid fa-circle-exclamation me-2" style="color:#fbbf24"></i>No hay licencia instalada.</p>
          <?php else:
            $d = $estado['leida'];
            $vence = licencia_vencimiento($d);
            $activa = $estado['activa'];
          ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <tr><th style="width:220px">Estado</th>
                  <td><?= $activa ? '<span class="badge bg-success">ACTIVA</span>' :
                        (licencia_vencida($d) ? '<span class="badge bg-danger">VENCIDA</span>' : '<span class="badge bg-secondary">INVÁLIDA</span>') ?></td></tr>
                <tr><th>Registro</th><td><?= htmlspecialchars($d['registro'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Usuario</th><td><?= htmlspecialchars($d['usuario'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Tipo</th><td><?= htmlspecialchars($d['info']['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= $d['tipo'] ?>)</td></tr>
                <tr><th>Serial</th><td><code><?= htmlspecialchars(licencia_formatear_serial($d['serial']), ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                <?php if ($d['huella'] !== ''): $coincide = hash_equals($d['huella'], licencia_fingerprint_machine()); ?>
                  <tr><th>Vinculada a</th><td><?= htmlspecialchars(licencia_formatear_fingerprint($d['huella']), ENT_QUOTES, 'UTF-8') ?>
                    <?= $coincide ? '<span class="badge bg-success ms-2">este equipo</span>' : '<span class="badge bg-danger ms-2">OTRO EQUIPO</span>' ?></td></tr>
                <?php endif; ?>
                <tr><th>Activada el</th><td><?= date('d/m/Y H:i:s', $d['fecha_activacion']) ?></td></tr>
                <tr><th>Vence el</th><td><?= $vence === null ? 'Nunca (permanente)' : date('d/m/Y H:i:s', $vence) ?></td></tr>
                <?php if ($vence !== null): ?>
                  <tr><th>Días restantes</th><td><?= (int)licencia_dias_restantes($d) ?></td></tr>
                <?php endif; ?>
                <tr><th>Almacenada en</th><td><code><?= htmlspecialchars($estado['ruta'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
              </table>
            </div>
            <?php
              $datosEstado = array(
                  'modo'      => 'estado',
                  'estado'    => $activa ? 'ACTIVA' : (licencia_vencida($d) ? 'VENCIDA' : 'INVALIDA'),
                  'registro'  => $d['registro'],
                  'usuario'   => $d['usuario'],
                  'tipo'      => $d['tipo'],
                  'tipo_nom'  => $d['info']['nombre'],
                  'serial'    => licencia_formatear_serial($d['serial']),
                  'vinculada' => ($d['huella'] !== '') ? licencia_formatear_fingerprint($d['huella']) : '',
                  'activada'  => date('d/m/Y H:i:s', $d['fecha_activacion']),
                  'vence'     => $vence === null ? 'Nunca (permanente)' : date('d/m/Y H:i:s', $vence),
                  'ruta'      => $estado['ruta'],
                  'valida'    => $activa,
              );
              $jsonEstado = htmlspecialchars(json_encode($datosEstado,
                  JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="d-flex gap-2 mt-3 flex-wrap">
              <button class="btn btn-sm btn-outline-primary"
                      onclick="prepararHojaLicencia(<?= $jsonEstado ?>); imprimirLicencia();">
                <i class="fa-solid fa-print me-1"></i> Imprimir
              </button>

              <button class="btn btn-sm btn-outline-primary"
                      onclick="prepararHojaLicencia(<?= $jsonEstado ?>); exportarPDFLicencia();">
                <i class="fa-solid fa-file-pdf me-1"></i> Exportar PDF
              </button>

              <button class="btn btn-sm btn-outline-primary" onclick="copiarLicencia(<?= $jsonEstado ?>)"
                      title="Copiar todos los datos de la licencia">
                <i class="fa-regular fa-copy me-1"></i> Copiar
              </button>

              <button class="btn btn-outline-danger btn-sm" onclick="confirmarEliminar()"><i class="fa-solid fa-trash me-1"></i> Eliminar licencia</button>
            </div>
            <form method="post" id="formEliminar" class="d-none">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            </form>
          <?php endif; ?>
        </div>
      </section>

      <section class="section d-none" id="section-importar">
        <div class="surface p-4">
          <h6 class="mb-3"><i class="fa-solid fa-file-import me-2" style="color:var(--accent)"></i>Importar licencia (.lic)</h6>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="importar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="mb-3">
              <label class="form-label small">Archivo .lic</label>
              <input type="file" name="archivo_lic" class="form-control" accept=".lic" required>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="forzar" id="chkForzar">
              <label class="form-check-label" for="chkForzar">Forzar reemplazo si ya hay una licencia activa</label>
            </div>
            <button class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i> Importar</button>
          </form>
        </div>
      </section>

      <section class="section d-none" id="section-instalar_manual">
        <div class="surface p-4">
          <h6 class="mb-3"><i class="fa-solid fa-key me-2" style="color:var(--accent)"></i>Instalar licencia manualmente</h6>
          <p style="color:var(--text-mute); font-size:.9rem">
            Escriba los datos y se instalará la licencia directamente en <b>este equipo</b>.
            No necesita archivo <code>.lic</code>: el serial se calcula automáticamente.
          </p>

          <form method="post" class="row g-3" id="formInstalarManual">
            <input type="hidden" name="accion" value="instalar_manual">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="col-md-6">
              <label class="form-label small">Nombre de Registro</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej: PDL Visiones Ntas.">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Usuario del Registro</label>
              <input type="text" name="usuario" class="form-control" required placeholder="Ej: Periquito Pérez Pérez">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Tipo de Licencia</label>
              <select name="tipo" class="form-select">
                <?php foreach ($tipos as $t => $i): ?>
                  <option value="<?= $t ?>"><?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= $t ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Huella <span style="color:var(--text-mute)">(esta máquina)</span></label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($fpEquipo, ENT_QUOTES, 'UTF-8') ?>" disabled>
            </div>

            <?php if ($estado['activa']): ?>
              <div class="col-12">
                <div class="alert-soft" style="background:var(--accent-soft);border-color:var(--accent-border);color:var(--accent)">
                  <i class="fa-solid fa-circle-info"></i>
                  <span>Ya hay una licencia <b>ACTIVA</b>. Marque "Forzar reemplazo" para sustituirla.</span>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="forzar" id="chkForzarManual">
                  <label class="form-check-label" for="chkForzarManual">Forzar reemplazo</label>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary flex-fill"><i class="fa-solid fa-key me-1"></i> Instalar licencia</button>
              <button type="button" class="btn btn-outline-primary" onclick="limpiarFormulario('formInstalarManual')" title="Limpiar campos">
                <i class="fa-solid fa-eraser me-1"></i> Limpiar
              </button>
            </div>
          </form>
        </div>

        <div class="surface p-4 mt-4">
          <h6 class="mb-3"><i class="fa-solid fa-code me-2" style="color:var(--accent)"></i>Instalar licencia pegando el código</h6>
          <p style="color:var(--text-mute); font-size:.9rem">
            Pegue o escriba el <b>código de la licencia</b> que le remitió el proveedor.
            No necesita el archivo <code>.lic</code> ni conocer los datos:
            el nombre, el usuario y el periodo se verifican solos.
          </p>

          <form method="post" id="formInstalarCodigo">
            <input type="hidden" name="accion" value="instalar_codigo">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <label class="form-label small" for="txtCodigoLicencia">Código de la licencia</label>
            <textarea name="codigo" id="txtCodigoLicencia" class="form-control" rows="4" required
                      placeholder="Pegue aquí el código de la licencia que le envió el proveedor..."></textarea>
            <div class="lic-feedback" id="codigoFeedback" role="status" aria-live="polite"></div>

            <?php if ($estado['activa']): ?>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="forzar_codigo" id="chkForzarCodigo">
                <label class="form-check-label" for="chkForzarCodigo">Forzar reemplazo</label>
              </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary flex-fill"><i class="fa-solid fa-code me-1"></i> Instalar desde el código</button>
              <button type="button" class="btn btn-outline-primary" onclick="limpiarFormulario('formInstalarCodigo')" title="Limpiar campos">
                <i class="fa-solid fa-eraser me-1"></i> Limpiar
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="section d-none" id="section-huella">
        <div class="surface p-4">
          <h6 class="mb-3"><i class="fa-solid fa-fingerprint me-2" style="color:var(--accent)"></i>Huella de este equipo</h6>
          <p style="color:var(--text-mute)">Comunique esta huella al proveedor para recibir una licencia vinculada a este equipo.</p>
          <div class="fp-box" id="fpBox"><?= htmlspecialchars($fpEquipo, ENT_QUOTES, 'UTF-8') ?></div>
          <button class="btn btn-sm btn-outline-primary mt-3" onclick="copiar('fpBox')">
            <i class="fa-regular fa-copy me-1"></i> Copiar huella
          </button>
        </div>
      </section>

      <section class="section d-none" id="section-test">
        <div class="surface p-4">
          <h6 class="mb-3"><i class="fa-solid fa-vial-circle-check me-2" style="color:var(--accent)"></i>Auto-diagnóstico</h6>
          <button class="btn btn-primary" onclick="ejecutarTest()"><i class="fa-solid fa-play me-1"></i> Ejecutar test</button>
          <pre id="testResult" class="mt-3 p-3 rounded" style="max-height:400px;overflow:auto;display:none;background:var(--input-bg);color:var(--serial-color);border:1px solid var(--border-col);border-radius:12px"></pre>
        </div>
      </section>

    </main>

    <footer class="footer">
      <div><i class="fa-solid fa-copyright me-1"></i> <?= date('Y') ?> SISGESNOM · Unicornio Software° - Todos los derechos reservados</div>
      <div><i class="fa-solid fa-shield-halved me-1"></i> Administración · <a href="#" onclick="mostrarInfo();return false;">Acerca de</a></div>
    </footer>

  </div>
</div>

<!-- ============ Hoja imprimible (8.5 x 11) para Imprimir / Exportar PDF ============ -->
<div id="hojaLicencia" aria-hidden="true">
  <div class="hl-watermark">SISGESNOM</div>

  <div class="hl-header">
    <div class="hl-marca">
      <img src="images/Unicorn.png" alt="SISGESNOM" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgcng9IjIwIiBmaWxsPSIjMGQzYjY2Ii8+PHBhdGggZmlsbD0iIzdlZTc4NyIgZD0iTTUwIDE1TDg1IDI1djI1YzAgMjAtMTUgMzUtMzUgNDAtMjAtNS0zNS0yMC0zNS00MFYyNXoiLz48L3N2Zz4=';">
      <div>
        <div class="hl-nombre">SISGESNOM<sup class="hl-reg">&reg;</sup></div>
        <div class="hl-sub">Unicornio Software<sup class="hl-reg">&reg;</sup></div>
      </div>
    </div>
    <div class="hl-meta">
      <div class="hl-tag" id="hlTag">CERTIFICADO DE LICENCIA</div>
      <div id="hlFilaEmitido"><span id="hlKEmitido">Emitido</span>: <b id="hlFecha"></b></div>
      <div class="hl-oculto" id="hlFilaDocIdCab"><span id="hlKDocId">Documento</span>: <b id="hlDocId"></b></div>
    </div>
  </div>

  <h1 id="hlTitulo">Licencia de uso de software</h1>
  <div class="hl-desc" id="hlDescripcion">Este documento certifica la autorización de uso del sistema <b>SISGESNOM</b>.</div>

  <div class="hl-bloque">
    <div class="hl-bloque-titulo" id="hlTituloBloque1">Datos del registro</div>
    <div class="hl-fila hl-oculto" id="hlFilaEstado"><div class="hl-k">Estado</div><div class="hl-v" id="hlEstado"></div></div>
    <div class="hl-fila"><div class="hl-k" id="hlKRegistro">Nombre de Registro</div><div class="hl-v" id="hlRegistro"></div></div>
    <div class="hl-fila"><div class="hl-k" id="hlKUsuario">Usuario del Registro</div><div class="hl-v" id="hlUsuario"></div></div>
    <div class="hl-fila"><div class="hl-k" id="hlKTipo">Tipo de Licencia</div><div class="hl-v" id="hlTipo"></div></div>
    <div class="hl-fila" id="hlFilaDuracion"><div class="hl-k">Duración</div><div class="hl-v" id="hlDuracion"></div></div>
    <div class="hl-fila"><div class="hl-k" id="hlKVence">Vencimiento</div><div class="hl-v" id="hlVence"></div></div>
    <div class="hl-fila" id="hlFilaVinculada"><div class="hl-k" id="hlKDestino">Huella destino</div><div class="hl-v" id="hlDestino"></div></div>
    <div class="hl-fila hl-oculto" id="hlFilaActivada"><div class="hl-k">Activada el</div><div class="hl-v" id="hlActivada"></div></div>
  </div>

  <div class="hl-bloque">
    <div class="hl-bloque-titulo">Llave serial</div>
    <div class="hl-serial" id="hlSerial"></div>
  </div>

  <div class="hl-bloque">
    <div class="hl-bloque-titulo" id="hlTituloBloque2">Verificación</div>
    <div class="hl-fila"><div class="hl-k" id="hlKValida">Integridad</div><div class="hl-v" id="hlValida"></div></div>
    <div class="hl-fila"><div class="hl-k" id="hlKOrigen">Generado desde</div><div class="hl-v" id="hlOrigen"></div></div>
    <div class="hl-fila" id="hlFilaDocId"><div class="hl-k">ID del documento</div><div class="hl-v" id="hlDocId2"></div></div>
  </div>

  <div class="hl-firma">
    <div class="hl-linea">Firma del Comercial</div>
    <div class="hl-linea">Sello / Fecha</div>
  </div>

  <div class="hl-footer">
    <span>SISGESNOM &middot; Unicornio Software<sup class="hl-reg">&reg;</sup></span>
    <span>Documento generado automáticamente</span>
  </div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/sweetalert211.js"></script>
<script src="js/html2pdf.bundle.min.js"></script>
<script>
/* Tema */
(function() {
  const html = document.documentElement;
  const btn = document.getElementById('btnTheme');
  const icon = document.getElementById('themeIcon');
  const guardado = localStorage.getItem('sigesnom_theme');
  aplicar(guardado || 'dark');
  function aplicar(t) {
    html.setAttribute('data-theme', t);
    html.setAttribute('data-bs-theme', t);
    icon.className = (t === 'dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  }
  btn.addEventListener('click', function() {
    const actual = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    aplicar(actual);
    localStorage.setItem('sigesnom_theme', actual);
  });
})();

/* Secciones */
const SECCIONES = {
  generar:        { titulo: 'Generar',         sub: 'Cree una nueva llave serial' },
  estado:         { titulo: 'Estado',          sub: 'Licencia instalada en este equipo' },
  importar:       { titulo: 'Importar',        sub: 'Instale una licencia desde un archivo .lic' },
  instalar_manual:{ titulo: 'Instalar manual', sub: 'Escriba los datos y se instalará en este equipo' },
  huella:         { titulo: 'Huella',          sub: 'Identificador único de este equipo' },
  test:           { titulo: 'Auto-test',       sub: 'Diagnóstico del módulo de licencia' }
};
function activarSeccion(nombre) {
  document.querySelectorAll('.sidebar .nav-btn[data-target]').forEach(b => {
    b.classList.toggle('active', b.dataset.target === nombre);
  });
  document.querySelectorAll('.section').forEach(s => s.classList.add('d-none'));
  const sec = document.getElementById('section-' + nombre);
  if (sec) sec.classList.remove('d-none');
  const info = SECCIONES[nombre] || { titulo: '', sub: '' };
  document.getElementById('pageTitle').innerHTML = info.titulo + ' <small class="ms-2">— ' + info.sub + '</small>';

  if (sec) {
    const inputNombre = sec.querySelector('input[name="nombre"]');
    if (inputNombre) {
      setTimeout(() => {
        inputNombre.focus();
        if (inputNombre.value) {
          const len = inputNombre.value.length;
          inputNombre.setSelectionRange(len, len);
        }
      }, 50);
    }
  }

  if (window.innerWidth <= 900) {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('backdrop').classList.remove('show');
  }
}
document.querySelectorAll('.sidebar .nav-btn[data-target]').forEach(b => {
  b.addEventListener('click', () => activarSeccion(b.dataset.target));
});
document.getElementById('btnMenu').addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('backdrop').classList.toggle('show');
});
document.getElementById('backdrop').addEventListener('click', function() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('backdrop').classList.remove('show');
});

/* SweetAlert helpers */
const SWAL_ICONS = {
  success: '<i class="fa-solid fa-circle-check me-2" style="color:#4ade80"></i>',
  error:   '<i class="fa-solid fa-circle-xmark me-2" style="color:#f87171"></i>',
  warning: '<i class="fa-solid fa-triangle-exclamation me-2" style="color:#fbbf24"></i>',
  info:    '<i class="fa-solid fa-circle-info me-2" style="color:#60a5fa"></i>',
  copy:    '<i class="fa-regular fa-copy me-2"></i>',
  key:     '<i class="fa-solid fa-key me-2" style="color:#fbbf24"></i>',
  rotate:  '<i class="fa-solid fa-arrows-rotate me-2" style="color:#60a5fa"></i>',
  logout:  '<i class="fa-solid fa-right-from-bracket me-2" style="color:#f87171"></i>',
  pdf:     '<i class="fa-solid fa-file-pdf me-2" style="color:#f87171"></i>'
};
function cssVars() {
  const cs = getComputedStyle(document.documentElement);
  return { bg: cs.getPropertyValue('--surface').trim() || '#1c2026',
           color: cs.getPropertyValue('--text').trim() || '#e5e7eb' };
}
function swalOk(t, h) {
  const c = cssVars();
  return Swal.fire({ iconHtml: SWAL_ICONS.success, title: t||'Operación exitosa', html: h||'',
    confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Aceptar',
    confirmButtonColor: '#2563eb', background: c.bg, color: c.color, icon: undefined });
}
function swalErr(t, h) {
  const c = cssVars();
  return Swal.fire({ iconHtml: SWAL_ICONS.error, title: t||'Ocurrió un error', html: h||'',
    confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Entendido',
    confirmButtonColor: '#dc2626', background: c.bg, color: c.color, icon: undefined });
}

const FLASH = <?= $flashJson ?>;
function listaFlashHtml(arr, icono) {
  return arr.map(m => '<div style="display:flex;gap:.6rem;align-items:flex-start;' +
    'text-align:left;margin:.35rem 0">' +
    '<i class="fa-solid ' + icono + '" style="margin-top:.2rem;flex:0 0 auto"></i>' +
    '<span>' + m + '</span></div>').join('');
}
(async function mostrarFlash() {
  if (FLASH && FLASH.ok && FLASH.ok.length)
    await swalOk('Operación exitosa', listaFlashHtml(FLASH.ok, 'fa-circle-check'));
  if (FLASH && FLASH.err && FLASH.err.length)
    await swalErr('Ocurrió un error', listaFlashHtml(FLASH.err, 'fa-circle-exclamation'));
})();

function copiarTexto(texto) {
  if (!texto) return;
  navigator.clipboard.writeText(texto).then(() => {
    const c = cssVars();
    Swal.fire({
      iconHtml: SWAL_ICONS.copy, title: 'Copiado al portapapeles',
      html: '<pre style="font-family:ui-monospace,Consolas,monospace;color:#a7f3d0;' +
            'text-align:left;white-space:pre-wrap;word-break:break-all;margin:0">' +
            texto.replace(/[<>&]/g, ch => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[ch])) + '</pre>',
      confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Aceptar',
      confirmButtonColor: '#2563eb', background: c.bg, color: c.color, icon: undefined
    });
  }).catch(() => swalErr('No se pudo copiar', ''));
}

function copiar(id) {
  const el = document.getElementById(id);
  if (!el) return;
  copiarTexto(el.innerText);
}

/* ---------------------------------------------------------------------
   Validación en vivo del texto de la licencia (pegado o escrito).
   El descifrado y la comprobación del serial se hacen en el servidor
   mediante ?accion=validar_texto&ajax=1: el secreto nunca llega al DOM.
   --------------------------------------------------------------------- */
(function validarLicenciaEnVivo() {
  const ta   = document.getElementById('txtCodigoLicencia');
  const caja = document.getElementById('codigoFeedback');
  if (!ta || !caja) return;

  const TOKEN = <?= json_encode(tool_token_esperado(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  let t = null, seq = 0;

  const esc = s => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const ICONOS = { ok: 'fa-circle-check', warn: 'fa-triangle-exclamation', bad: 'fa-circle-exclamation' };

  /* estado: 'ok' | 'warn' | 'bad'
     fichas: [etiqueta, valor, claseOpcional]
     codigos: [etiqueta, valor] en tipografía monoespaciada (serial, huella) */
  function pintar(estado, titulo, fichas, codigos) {
    caja.className = 'lic-feedback visible ' + estado;
    let html = '<div class="lic-feedback-head"><span class="lic-ico"><i class="fa-solid ' +
               ICONOS[estado] + '"></i></span><span>' + esc(titulo) + '</span></div>';
    if (fichas && fichas.length) {
      html += '<div class="lic-feedback-body"><div class="lic-tiles">';
      fichas.forEach(f => {
        html += '<div class="lic-tile' + (f[2] ? ' ' + f[2] : '') + '"><span class="k">' + esc(f[0]) +
                '</span><span class="v">' + esc(f[1]) + '</span></div>';
      });
      html += '</div>';
      if (codigos && codigos.length) {
        html += '<div class="lic-codigos">';
        codigos.forEach(c => {
          html += '<div class="lic-codigo"><span class="k">' + esc(c[0]) + '</span><span class="v">' +
                  esc(c[1]) + '</span></div>';
        });
        html += '</div>';
      }
      html += '</div>';
    }
    caja.innerHTML = html;
  }

  function borrarValidacion() {
    clearTimeout(t);
    seq++;                       // invalida cualquier respuesta en vuelo
    caja.className = 'lic-feedback';
    caja.innerHTML = '';
  }

  async function validar() {
    const codigo = ta.value.trim();
    if (codigo === '') {
      caja.className = 'lic-feedback';
      caja.innerHTML = '';
      return;
    }
    const mio = ++seq;
    try {
      const fd = new FormData();
      fd.append('codigo', codigo);
      const r = await fetch('?accion=validar_texto&ajax=1&token=' + encodeURIComponent(TOKEN),
                             { method: 'POST', body: fd, cache: 'no-store' });
      const j = await r.json();
      if (mio !== seq) return;  // llegó una respuesta más nueva

      if (!j.ok) { pintar('bad', j.mensaje); return; }

      const d = j.datos || {};
      const fichas = [
        ['Registro', d.registro],
        ['Usuario',  d.usuario],
        ['Tipo',     d.tipo],
        ['Periodo',  d.periodo],
        ['Vence',    d.vence]
      ];
      if (d.generica) {
        fichas.push(['Vinculación', 'Genérica · se genera aquí', 'val-ok']);
      } else if (d.otraPc) {
        fichas.push(['Vinculación', 'Otra máquina', 'val-warn']);
      } else {
        fichas.push(['Vinculación', 'Esta máquina', 'val-ok']);
      }
      // Serial y huella se muestran en el mismo formato monoespaciado.
      const codigos = [['Serial', d.serial]];
      if (!d.generica) codigos.push(['Huella', d.huella]);

      pintar(d.otraPc ? 'warn' : 'ok',
             d.otraPc ? 'Licencia válida, pero es de otra máquina' : 'Licencia válida',
             fichas, codigos);
    } catch (e) {
      if (mio !== seq) return;
      pintar('bad', 'No se pudo contactar al servidor para validar el texto.');
    }
  }

  ta.addEventListener('input', function () {
    clearTimeout(t);
    t = setTimeout(validar, 450);
  });
  ta.addEventListener('paste', function () { setTimeout(validar, 60); });
  // "Limpiar" debe borrar tambien la ficha de validación.
  const form = document.getElementById('formInstalarCodigo');
  if (form) {
    form.addEventListener('reset', borrarValidacion);
    const btn = form.querySelector('button[onclick*="limpiarFormulario"]');
    if (btn) btn.addEventListener('click', function () { setTimeout(borrarValidacion, 0); });
  }
})();

/* Copia la licencia completa (no solo el serial) */
function copiarLicencia(datos) {
  if (!datos) return;
  const mes = { M:'1 mes', Q:'3 meses', S:'5 meses', A:'12 meses', B:'24 meses', C:'60 meses', P:'Permanente' };
  let t;
  if (datos.modo === 'estado') {
    t = [
      'SISGESNOM - Estado de la licencia',
      '',
      'Estado:      ' + (datos.estado || ''),
      'Registro:    ' + (datos.registro || ''),
      'Usuario:     ' + (datos.usuario  || ''),
      'Tipo:        ' + (datos.tipo_nom || '') + ' (' + (datos.tipo || '') + ')',
      'Serial:      ' + (datos.serial || ''),
      'Vinculada a: ' + (datos.vinculada || 'Generica'),
      'Activada el: ' + (datos.activada || ''),
      'Vence:       ' + (datos.vence || '')
    ].join('\n');
  } else {
    t = [
      'SISGESNOM' + ' - Licencia de uso de software',
      '',
      'Registro:    ' + (datos.registro || ''),
      'Usuario:     ' + (datos.usuario  || ''),
      'Tipo:        ' + (datos.tipo_nom || '') + ' (' + (datos.tipo || '') + ')',
      'Duracion:    ' + (mes[datos.tipo] || ''),
      'Destino:     ' + (!datos.destino ? 'Generica' : datos.destino),
      'Valida:      ' + (datos.valida ? 'SI' : 'NO'),
      'Generada:    ' + (datos.fecha || ''),
      '',
      'Llave Serial:',
      datos.serial || ''
    ].join('\n');
  }
  copiarTexto(t);
}

function limpiarFormulario(id) {
  const form = document.getElementById(id);
  if (!form) return;
  // Limpiar tambien cualquier panel de validacion asociado al formulario.
  form.querySelectorAll('.lic-feedback').forEach(c => { c.className = 'lic-feedback'; c.innerHTML = ''; });
  form.querySelectorAll('input, select, textarea').forEach(el => {
    if (el.name === 'csrf' || el.name === 'accion') return;
    if (el.type === 'checkbox' || el.type === 'radio') {
      el.checked = (el.id === 'chkExportar');
    } else if (el.tagName === 'SELECT') {
      el.selectedIndex = 0;
    } else if (!el.disabled) {
      el.value = '';
    }
  });
  const c = cssVars();
  Swal.fire({
    iconHtml: '<i class="fa-solid fa-eraser me-2" style="color:#60a5fa"></i>',
    title: 'Campos limpiados',
    timer: 1200,
    timerProgressBar: true,
    showConfirmButton: false,
    background: c.bg, color: c.color, icon: undefined
  });
}

function confirmarEliminar() {
  const c = cssVars();
  Swal.fire({
    iconHtml: SWAL_ICONS.warning, title: '¿Eliminar la licencia?',
    html: 'Se borrará la licencia instalada en este equipo.<br>Esta acción no se puede deshacer.',
    showCancelButton: true,
    confirmButtonText: '<i class="fa-solid fa-trash me-2"></i> Sí, eliminar',
    cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i> Cancelar',
    confirmButtonColor: '#dc2626', cancelButtonColor: '#334155',
    background: c.bg, color: c.color, icon: undefined
  }).then((r) => { if (r.isConfirmed) document.getElementById('formEliminar').submit(); });
}

function ejecutarTest() {
  const pre = document.getElementById('testResult');
  const c = cssVars();
  pre.style.display = 'block';
  pre.textContent = 'Ejecutando...';
  Swal.fire({ title: 'Ejecutando auto-test...', didOpen: () => Swal.showLoading(),
    allowOutsideClick: false, showConfirmButton: false, background: c.bg, color: c.color });
  fetch('?accion=test&ajax=1&token=<?= $token ?>')
    .then(r => r.json())
    .then(d => {
      Swal.close();
      pre.textContent = JSON.stringify(d, null, 2);
      const ok = d.validar_ok === true && d.validar_nok === true && d.cifrar_descifrar === true
              && (!d.acentos || d.acentos.coinciden === true);
      if (ok) swalOk('Auto-test OK', 'Todas las pruebas pasaron.');
      else swalErr('Auto-test con fallos', 'Revise el detalle en pantalla.');
    })
    .catch(e => { Swal.close(); pre.textContent = 'Error: ' + e; swalErr('Error', String(e)); });
}

function mostrarInfo() {
  const c = cssVars();
  Swal.fire({
    iconHtml: SWAL_ICONS.info, title: 'SISGESNOM · Generador de Licencias',
    html: 'Versión 2.7.0<br>Unicornio Software°<br><br>' +
          '<small style="opacity:.7">Desarrollado para uso interno.</small>',
    confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Aceptar',
    confirmButtonColor: '#2563eb', background: c.bg, color: c.color, icon: undefined
  });
}

/* Logout (solo cookie, conserva token) */
document.getElementById('btnLogout').addEventListener('click', function() {
  const c = cssVars();
  Swal.fire({
    iconHtml: SWAL_ICONS.logout, title: '¿Cerrar sesión?',
    html: 'Se eliminará la <b>cookie de acceso</b> en este navegador.<br>' +
          'El token actual se conserva, podrá volver a entrar con el mismo.',
    showCancelButton: true,
    confirmButtonText: '<i class="fa-solid fa-right-from-bracket me-2"></i> Sí, cerrar',
    cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i> Cancelar',
    confirmButtonColor: '#dc2626', cancelButtonColor: '#334155',
    background: c.bg, color: c.color, icon: undefined
  }).then((r) => { if (r.isConfirmed) window.location.href = '?accion=logout'; });
});

/* Rotar token */
document.getElementById('btnRotar').addEventListener('click', function() {
  const c = cssVars();
  Swal.fire({
    iconHtml: SWAL_ICONS.key, title: '¿Rotar el token actual?',
    html: 'Se generará un <b>nuevo token</b> y el anterior dejará de funcionar.<br>' +
          'La cookie actual se invalidará. Deberá introducir el nuevo token para volver a entrar.<br><br>' +
          '<small style="opacity:.7">Si el token está fijado por variable de entorno, la rotación no aplicará.</small>',
    showCancelButton: true,
    confirmButtonText: '<i class="fa-solid fa-arrows-rotate me-2"></i> Sí, rotar',
    cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i> Cancelar',
    confirmButtonColor: '#2563eb', cancelButtonColor: '#334155',
    background: c.bg, color: c.color, icon: undefined
  }).then((r) => {
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('csrf', '<?= htmlspecialchars($csrf) ?>');
    Swal.fire({ title: 'Rotando token...', didOpen: () => Swal.showLoading(),
      allowOutsideClick: false, showConfirmButton: false, background: c.bg, color: c.color });
    fetch('?accion=rotar_token', { method: 'POST', body: fd, cache: 'no-store' })
      .then(r => r.json())
      .then(d => {
        Swal.close();
        if (d.ok === true) {
          Swal.fire({
            iconHtml: SWAL_ICONS.key,
            title: 'Token rotado',
            html:
              'Nuevo token:<br>' +
              '<span class="token-result" id="tokenResultBox">' +
                '<span id="tokenResultText">' + d.token + '</span>' +
                '<button type="button" class="token-copy" id="btnCopyToken" title="Copiar token">' +
                  '<i class="fa-regular fa-copy"></i>' +
                '</button>' +
              '</span>' +
              'Guárdelo. Deberá introducirlo de nuevo al recargar.',
            confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Aceptar',
            confirmButtonColor: '#2563eb',
            background: c.bg, color: c.color, icon: undefined,
            didOpen: () => {
              const btn = document.getElementById('btnCopyToken');
              if (!btn) return;
              btn.addEventListener('click', () => {
                const txt = document.getElementById('tokenResultText').innerText;
                navigator.clipboard.writeText(txt).then(() => {
                  btn.classList.add('copied');
                  btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                  setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fa-regular fa-copy"></i>';
                  }, 1500);
                }).catch(() => {
                  btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                  setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1500);
                });
              });
            }
          }).then(() => { window.location.href = '?accion=logout'; });
        } else {
          swalErr('No se pudo rotar', d.msg || 'Error desconocido.');
        }
      })
      .catch(e => { Swal.close(); swalErr('Error', String(e)); });
  });
});

/* Botones del sidebar → llaman a los mismos handlers */
document.getElementById('sideRotar').addEventListener('click', function() {
  document.getElementById('btnRotar').click();
});
document.getElementById('sideLogout').addEventListener('click', function() {
  document.getElementById('btnLogout').click();
});

/* Sección inicial (mantener instalar_manual tras POST) */
(function() {
  const ini = <?= json_encode($accion_vista_inicial) ?>;
  activarSeccion(ini || 'generar');
})();

/* ========== Hoja imprimible: rellenar, imprimir y exportar a PDF ========== */

function prepararHojaLicencia(datos) {
  window.__hlUltimosDatos = datos;
  const hoy = new Date();
  const fechaTxt = hoy.toLocaleDateString('es-ES', { day:'2-digit', month:'long', year:'numeric' });
  const docId = 'SGS-' + hoy.getFullYear() +
                String(hoy.getMonth()+1).padStart(2,'0') +
                String(hoy.getDate()).padStart(2,'0') + '-' +
                Math.random().toString(36).slice(2,8).toUpperCase();

  const mesesPorTipo = { M:1, Q:3, S:5, A:12, B:24, C:60, P:null };
  const meses = mesesPorTipo[datos.tipo];
  const duracion = (meses === null) ? 'Permanente (sin vencimiento)'
                                    : (meses + (meses === 1 ? ' mes' : ' meses'));

  let venceTxt;
  if (meses === null) {
    venceTxt = 'Nunca (licencia permanente)';
  } else {
    // El vencimiento real se calcula desde la INSTALACION, no desde la
    // generacion: todavia no hay fecha concreta que mostrar.
    venceTxt = duracion + ' desde la instalación';
  }

  const $ = (id) => document.getElementById(id);
  const esEstado = datos.modo === 'estado';

  /* --- adaptation de etiquetas y filas segun el modo --- */
  const mostrar = (id, on) => $(id).classList.toggle('hl-oculto', !on);
  const texto = (id, v) => { $(id).textContent = v; };

  texto('hlKRegistro', esEstado ? 'Registro'     : 'Nombre de Registro');
  texto('hlKUsuario',  esEstado ? 'Usuario'      : 'Usuario del Registro');
  texto('hlKTipo',     esEstado ? 'Tipo'         : 'Tipo de Licencia');
  texto('hlKVence',    esEstado ? 'Vence'        : 'Vencimiento');
  texto('hlKDestino',  esEstado ? 'Vinculada a'  : 'Huella destino');
  texto('hlKValida',   'Integridad');
  texto('hlKOrigen',   esEstado ? 'Almacenada en': 'Generado desde');
  texto('hlKEmitido',  esEstado ? 'Emitido'  : 'Generado');
  texto('hlTituloBloque1', esEstado ? 'Estado de la licencia' : 'Datos del registro');
  texto('hlTituloBloque2', esEstado ? 'Almacenamiento'        : 'Verificación');
  texto('hlTitulo', esEstado ? 'Estado de la licencia de software' : 'Licencia de uso de software');
  texto('hlDescripcion', esEstado
    ? 'Informe del estado de la licencia instalada en este equipo.'
    : 'Este documento certifica la autorización de uso del sistema SISGESNOM.');

  mostrar('hlFilaEstado',   esEstado);
  mostrar('hlFilaActivada', esEstado);
  mostrar('hlFilaDuracion', !esEstado);
  mostrar('hlFilaDocId',    !esEstado);
  mostrar('hlFilaVinculada', true);
  mostrar('hlFilaDocIdCab', !esEstado);

  /* --- contenido --- */
  texto('hlFecha',    fechaTxt);
  texto('hlDocId',    docId);
  texto('hlDocId2',   docId);
  texto('hlRegistro', datos.registro);
  texto('hlUsuario',  datos.usuario);
  texto('hlTipo',     datos.tipo_nom + '  (' + datos.tipo + ')');
  texto('hlSerial',   datos.serial);

  if (esEstado) {
    texto('hlEstado',   datos.estado || '');
    texto('hlActivada', datos.activada || '');
    texto('hlVence',    datos.vence || '');
    texto('hlDestino',  datos.vinculada
      ? datos.vinculada
      : 'Genérica (sin vínculo a un equipo específico)');
    texto('hlOrigen',   datos.ruta || '');
  } else {
    texto('hlDuracion', duracion);
    texto('hlVence',    venceTxt);
    texto('hlDestino',  datos.destino === ''
      ? 'Genérica (se vincula al primer equipo que la importe)'
      : datos.destino);
    texto('hlOrigen',   'Generador de Licencias SISGESNOM (Web)');
  }

  const hlVal = $('hlValida');
  if (datos.valida) {
    hlVal.innerHTML = '<span class="hl-badge ok">✓ Válida</span>';
  } else {
    hlVal.innerHTML = '<span class="hl-badge no">✗ No válida</span>';
  }
}

function imprimirLicencia() {
  window.print();
}

function exportarPDFLicencia() {
  const datos = window.__hlUltimosDatos || null;
  if (!datos) {
    swalErr('Sin datos', 'Genere una licencia primero.');
    return;
  }

  if (typeof html2pdf === 'undefined') {
    window.print();
    return;
  }

  const hoja = document.getElementById('hojaLicencia');

  // 1) Guardar estilos originales para restaurarlos después
  const estilosOriginales = {
    position:   hoja.style.position,
    left:       hoja.style.left,
    top:        hoja.style.top,
    zIndex:     hoja.style.zIndex,
    opacity:    hoja.style.opacity,
    boxShadow:  hoja.style.boxShadow,
    visibility: hoja.style.visibility,
    width:      hoja.style.width,
    height:     hoja.style.height,
    overflow:   hoja.style.overflow,
    background: hoja.style.background,
    margin:     hoja.style.margin,
    padding:    hoja.style.padding,
    display:    hoja.style.display,
    pointerEvents: hoja.style.pointerEvents
  };

  // 2) Mover temporalmente al viewport para que html2canvas lo renderice bien
  //    z-index negativo + pointer-events:none la deja detrás de la interfaz
  //    sin usar opacity (html2canvas rasteriza el opacity computado -> PDF en blanco)
  hoja.style.position   = 'fixed';
  hoja.style.left       = '0';
  hoja.style.top        = '0';
  hoja.style.zIndex     = '-1';
  hoja.style.pointerEvents = 'none';
  hoja.style.boxShadow  = 'none';
  hoja.style.visibility = 'visible';
  hoja.style.width      = '8.5in';
  hoja.style.height     = '11in';
  hoja.style.overflow   = 'hidden';
  hoja.style.background = '#ffffff';
  hoja.style.color      = '#0f172a';
    hoja.style.margin     = '0';
    hoja.style.padding    = '0.6in 0.7in 0.5in 0.7in';
    hoja.style.display    = 'block';

  const nombreArchivo = 'SISGESNOM_' +
    (datos.registro || 'licencia').replace(/[^A-Za-z0-9_-]+/g, '_') +
    '_' + (datos.tipo || 'M') + '_' +
    new Date().toISOString().slice(0,10) + '.pdf';

  const opciones = {
    margin:      0,
    filename:    nombreArchivo,
    image:       { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale:           2,
      useCORS:         true,
      allowTaint:      true,
      backgroundColor: '#ffffff',
      logging:         false,
      // sin width/height/windowWidth/windowHeight: html2canvas usa el
      // boundingBox del elemento, evitando el desplazamiento horizontal
      scrollX:         0,
      scrollY:         -window.scrollY,
      x:               0,
      y:               0
    },
    jsPDF:       { unit: 'pt', format: [612, 792], orientation: 'portrait', compress: true },
    pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
  };

  // 3) Esperar pintado e imágenes antes de rasterizar
  const esperarPaint = () => new Promise(res => {
    requestAnimationFrame(() => requestAnimationFrame(res));
  });
  const esperarImagenes = () => Promise.all(
    Array.from(hoja.querySelectorAll('img')).map(img => {
      if (img.complete && img.naturalWidth > 0) return Promise.resolve();
      return new Promise(res => {
        img.addEventListener('load',  res, { once: true });
        img.addEventListener('error', res, { once: true });
      });
    })
  );

  const restaurar = () => {
    hoja.style.position   = estilosOriginales.position;
    hoja.style.left       = estilosOriginales.left;
    hoja.style.top        = estilosOriginales.top;
    hoja.style.zIndex     = estilosOriginales.zIndex;
    hoja.style.opacity    = estilosOriginales.opacity;
    hoja.style.boxShadow  = estilosOriginales.boxShadow;
    hoja.style.visibility = estilosOriginales.visibility;
    hoja.style.width      = estilosOriginales.width;
    hoja.style.height     = estilosOriginales.height;
    hoja.style.overflow   = estilosOriginales.overflow;
    hoja.style.background = estilosOriginales.background;
    hoja.style.color      = estilosOriginales.color;
    hoja.style.margin     = estilosOriginales.margin;
    hoja.style.padding    = estilosOriginales.padding;
    hoja.style.display    = estilosOriginales.display;
    hoja.style.pointerEvents = estilosOriginales.pointerEvents;
  };

  Promise.all([esperarImagenes(), esperarPaint()]).then(() => {
    setTimeout(() => {
      html2pdf().set(opciones).from(hoja).save()
        .then(() => { restaurar(); })
        .catch(() => { restaurar(); window.print(); });
    }, 200);
  });
}


</script>
</body>
</html>