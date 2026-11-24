<?php
/**
 * Portada alternativa (index2.php) con el lenguaje visual de cyberforja.com:
 * fondo abismo, acentos cian y fuego, titulares en Bebas Neue, etiquetas y
 * botones en Exo 2, y el monitor en perspectiva con móvil y ficha del producto.
 *
 * Es una página independiente: no reutiliza el CSS de index.php. Todas las
 * imágenes viven en /images (hero*, nominas-* y facturacion-*) y no se hace
 * ninguna petición externa.
 */

date_default_timezone_set('America/Havana');

$base = '/servicios';

/* ---------------------------------------------------------------------------
 * Estado de la licencia local (mismo módulo que usa el resto del sistema).
 * ------------------------------------------------------------------------- */
$licenciaArchivo = __DIR__ . '/servicios/nominas/includes/licencia.php';
$licenciaDatos   = null;
$licenciaActiva  = false;
if (is_file($licenciaArchivo)) {
    require_once $licenciaArchivo;
    $licenciaDatos  = licencia_leer();
    $licenciaActiva = licencia_activada();
}
/* Versión de MySQL/Apache/phpMyAdmin y salud BD (misma lógica que servicios/phpinfo) */
$mysqlVersionDisplay = 'No disponible';
$apacheVersion = 'No detectado';
$phpmyadminVersion = 'No detectado';
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = '';
$dbConnected = false;
$dbConn = null;
$dbSize = '0 MB';
$dbTables = '0';
$dbUptime = 'Sin datos';
$dbUptimeTitle = 'Servidor MySQL no disponible';

$configNominas = __DIR__ . '/servicios/nominas/config.php';
if (is_file($configNominas)) {
    $cfg = file_get_contents($configNominas);
    if (preg_match("/define\\s*\\(\\s*['\\\"]DB_HOST['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/", $cfg, $m)) $dbHost = $m[1];
    if (preg_match("/define\\s*\\(\\s*['\\\"]DB_USER['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/", $cfg, $m)) $dbUser = $m[1];
    if (preg_match("/define\\s*\\(\\s*['\\\"]DB_PASS['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/", $cfg, $m)) $dbPass = $m[1];
    if (preg_match("/define\\s*\\(\\s*['\\\"]DB_NAME['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/", $cfg, $m)) $dbName = $m[1];
}

$phpVersionDisplay = phpversion();
if (function_exists('apache_get_version')) {
    $apacheRaw = apache_get_version();
    if (is_string($apacheRaw) && preg_match('/Apache\/([0-9\.]+)/', $apacheRaw, $matches)) $apacheVersion = $matches[1];
} elseif (isset($_SERVER['SERVER_SOFTWARE']) && preg_match('/([0-9\.]+)/', (string)$_SERVER['SERVER_SOFTWARE'], $matches)) {
    $apacheVersion = $matches[1];
}

$rutaPMAEncontrada = '';
$dirActual = __DIR__;
for ($i = 0; $i < 5; $i++) {
    if (file_exists($dirActual . '/USBWebserver.exe') || file_exists($dirActual . '/usbwebserver.exe')) {
        $rutaPMAEncontrada = $dirActual . '/phpmyadmin';
        break;
    }
    $dirActual = dirname($dirActual);
    if ($dirActual === '/' || $dirActual === '\\' || $dirActual === '') break;
}
if ($rutaPMAEncontrada === '') {
    foreach ([dirname(__DIR__) . '/phpmyadmin', dirname(dirname(__DIR__)) . '/phpmyadmin', 'C:/USBWebserver/phpmyadmin'] as $ruta) {
        if (is_dir($ruta)) { $rutaPMAEncontrada = $ruta; break; }
    }
}
if ($rutaPMAEncontrada !== '' && is_dir($rutaPMAEncontrada)) {
    $readmePath = $rutaPMAEncontrada . '/README';
    $composerPath = $rutaPMAEncontrada . '/composer.json';
    if (file_exists($readmePath)) {
        $content = file_get_contents($readmePath);
        if (is_string($content) && preg_match('/Version ([0-9]+\.[0-9]+\.[0-9]+)/i', $content, $m)) $phpmyadminVersion = $m[1];
    } elseif (file_exists($composerPath)) {
        $content = file_get_contents($composerPath);
        if (is_string($content) && preg_match('/"version":\s*"([^"]+)"/', $content, $m)) $phpmyadminVersion = $m[1];
    }
}

if (!function_exists('BD_shell_disabled')) {
    function BD_shell_disabled(string $fn): bool {
        $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
        return in_array(strtolower($fn), $disabled, true);
    }
}
if (!function_exists('BD_mysql_start_from_sql')) {
    function BD_mysql_start_from_sql($conn): ?int {
        if (!($conn instanceof mysqli)) return null;
        foreach (["SHOW GLOBAL STATUS LIKE 'Uptime'", "SELECT VARIABLE_VALUE AS Value FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'UPTIME'", "SELECT VARIABLE_VALUE AS Value FROM performance_schema.global_status WHERE VARIABLE_NAME = 'UPTIME'"] as $sql) {
            try {
                $res = @mysqli_query($conn, $sql);
                if (!($res instanceof mysqli_result)) continue;
                $row = @mysqli_fetch_assoc($res);
                @mysqli_free_result($res);
                if (!$row) continue;
                $valor = null;
                foreach (['Value','VARIABLE_VALUE','Uptime','UPTIME'] as $clave) { if (isset($row[$clave]) && $row[$clave] !== '') { $valor = $row[$clave]; break; } }
                if ($valor === null) { $valores = array_values($row); $valor = end($valores); }
                if ($valor !== null && is_numeric($valor)) return max(0, (int)$valor);
            } catch (Throwable $e) {}
        }
        return null;
    }
}
if (!function_exists('BD_parse_wmi_date')) {
    function BD_parse_wmi_date(string $valor): ?int {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.\d+([+-])(\d{3})$/', $valor, $m)) return null;
        $local = gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
        if ($local === false) return null;
        $offset = ($m[7] === '+' ? 1 : -1) * ((int)$m[8] * 60);
        return $local - $offset;
    }
}
if (!function_exists('BD_mysql_start_from_pidfile')) {
    function BD_mysql_start_from_pidfile(): ?int {
        $candidatos = [dirname(__DIR__, 2) . '/mysql/my.ini', dirname(__DIR__) . '/mysql/my.ini', 'C:/Users/kakyc/Desktop/sistemas/mysql/my.ini', 'C:/USBWebserver/mysql/my.ini', 'C:/xampp/mysql/bin/my.ini'];
        $datadirs = [];
        foreach ($candidatos as $ini) {
            if (!is_file($ini)) continue;
            $contenido = (string)@file_get_contents($ini);
            if (preg_match('/^\s*datadir\s*=\s*"?([^"\r\n]+)"?/mi', $contenido, $m)) $datadirs[] = str_replace('\\\\', '\\', trim($m[1]));
            $datadirs[] = dirname($ini) . '/data';
        }
        $mejor = null;
        foreach (array_unique($datadirs) as $dir) {
            if (!is_dir($dir)) continue;
            foreach ((array)glob(rtrim($dir, "/\\") . '/*.pid') as $pidFile) {
                $ts = @filemtime($pidFile);
                if ($ts === false) continue;
                if ($mejor === null || $ts > $mejor) $mejor = $ts;
            }
        }
        return $mejor;
    }
}
if (!function_exists('BD_mysql_start_from_os')) {
    function BD_mysql_start_from_os(): ?int {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            if (!BD_shell_disabled('shell_exec') && function_exists('shell_exec')) {
                $wmic = @shell_exec('wmic process where "name like \'%mysqld%\'" get CreationDate /format:list 2>nul');
                if (is_string($wmic) && preg_match_all('/(\d{14}\.\d{6}[+-]\d{3})/', $wmic, $m)) {
                    $mejor = null;
                    foreach ($m[1] as $fecha) { $ts = BD_parse_wmi_date($fecha); if ($ts !== null && ($mejor === null || $ts < $mejor)) $mejor = $ts; }
                    if ($mejor !== null) return $mejor;
                }
            }
            return BD_mysql_start_from_pidfile();
        }
        return BD_mysql_start_from_pidfile();
    }
}
if (!function_exists('BD_mysql_uptime')) {
    function BD_mysql_uptime($conn): int {
        $desdeSql = BD_mysql_start_from_sql($conn);
        if ($desdeSql !== null) return $desdeSql;
        $start = BD_mysql_start_from_os();
        if ($start !== null && $start > 0) return max(0, time() - $start);
        return 0;
    }
}
if (!function_exists('BD_formato_uptime')) {
    function BD_formato_uptime(int $segundos): string {
        $segundos = max(0, $segundos);
        $dias = intdiv($segundos, 86400);
        $horas = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        if ($dias > 0) return $dias . 'd ' . $horas . 'h ' . $minutos . 'm';
        if ($horas > 0) return $horas . 'h ' . $minutos . 'm';
        if ($minutos > 0) return $minutos . 'm';
        return 'menos de 1 min';
    }
}

if (function_exists('mysqli_connect')) {
    try {
        $dbConn = @mysqli_connect($dbHost, $dbUser, $dbPass);
        if ($dbConn) {
            $dbConnected = true;
            $info = mysqli_get_server_info($dbConn);
            if (is_string($info) && $info !== '') $mysqlVersionDisplay = $info;
            if ($dbName !== '') {
                @mysqli_select_db($dbConn, $dbName);
                try {
                    $res = @mysqli_query($dbConn, "SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = '" . mysqli_real_escape_string($dbConn, $dbName) . "'");
                    if ($res && $row = mysqli_fetch_assoc($res)) $dbSize = round(((float)($row['size'] ?? 0)) / (1024 * 1024), 2) . ' MB';
                } catch (Throwable $e) {}
                try {
                    $res = @mysqli_query($dbConn, "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE table_schema = '" . mysqli_real_escape_string($dbConn, $dbName) . "'");
                    if ($res && $row = mysqli_fetch_assoc($res)) $dbTables = (string)($row['total'] ?? '0');
                } catch (Throwable $e) {}
            }
            $uptimeSeg = BD_mysql_uptime($dbConn);
            $dbUptime = BD_formato_uptime($uptimeSeg);
            if ($uptimeSeg >= 0) $dbUptimeTitle = 'Activo desde ' . date('d/m/Y H:i:s T', time() - $uptimeSeg) . ' · ' . number_format($uptimeSeg) . ' segundos';
            @mysqli_close($dbConn);
        }
    } catch (Throwable $e) {
        $mysqlVersionDisplay = 'No disponible';
        if ($dbConn instanceof mysqli) { @mysqli_close($dbConn); }
    }
}

/* ---------------------------------------------------------------------------
 * Activación y renovación de la licencia desde esta misma portada.
 * Misma lógica que index.php: se procesa por POST antes de pintar la página.
 * ------------------------------------------------------------------------- */
$licForm = [
    'nombre'  => ($licenciaDatos !== null) ? (string)$licenciaDatos['registro'] : '',
    'usuario' => ($licenciaDatos !== null) ? (string)$licenciaDatos['usuario']  : '',
    'serial'  => '',
    'texto'   => '',
];
$licError        = '';
$licAviso        = '';
$licAvisoTipo    = '';
$licOk           = false;
$licOkTipo       = '';
$licOkHasta      = '';
$licModalAbierto = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['licencia_accion']) && function_exists('licencia_guardar')) {
    $licAccion       = (string)$_POST['licencia_accion'];
    $licModalAbierto = true;
    $licForm['nombre']  = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : $licForm['nombre'];
    $licForm['usuario'] = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : $licForm['usuario'];
    $licForm['serial']  = isset($_POST['serial'])  ? trim((string)$_POST['serial'])  : '';
    $licForm['texto']   = isset($_POST['licencia_texto']) ? (string)$_POST['licencia_texto'] : '';

    if ($licAccion === 'examinar_texto') {
        $verif = licencia_texto_verificar($licForm['texto']);
        if ($verif['ok']) {
            $licForm['nombre']  = $verif['datos']['registro'];
            $licForm['usuario'] = $verif['datos']['usuario'];
            $licForm['serial']  = $verif['datos']['serial'];
            $licAvisoTipo = 'ok';
            $licAviso = 'Licencia válida para este equipo (' . $verif['datos']['termino'] . '). Revise los datos y pulse «Activar licencia».';
        } else {
            $licAvisoTipo = 'error';
            $licAviso = $verif['mensaje'];
            if ($verif['huella_destino'] !== '') {
                $licAviso .= ' Huella destino: ' . $verif['huella_destino']
                    . ' · Este equipo: ' . $verif['huella_equipo'] . '.';
            }
        }
    } elseif ($licAccion === 'examinar_archivo') {
        $licAvisoTipo = 'error';
        if (empty($_FILES['archivo_lic']['tmp_name'])
            || (($_FILES['archivo_lic']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            $licAviso = 'Debe seleccionar un archivo .lic válido para importar.';
        } elseif (strtolower(pathinfo($_FILES['archivo_lic']['name'], PATHINFO_EXTENSION)) !== 'lic') {
            $licAviso = 'El archivo debe tener extensión .lic.';
        } else {
            $impDatos = licencia_leer_archivo_lic($_FILES['archivo_lic']['tmp_name']);
            if ($impDatos === null) {
                $licAviso = 'El archivo .lic no es válido o su llave no corresponde.';
            } elseif (licencia_vencida($impDatos)) {
                $licAviso = 'La licencia del archivo .lic está vencida y no puede importarse. Solicite una licencia vigente al proveedor.';
            } elseif (function_exists('licencia_fecha_futura') && licencia_fecha_futura($impDatos)) {
                $licAviso = 'La fecha de la licencia es posterior a la fecha del equipo y no puede utilizarse. Verifique la fecha/hora del PC o solicite una licencia válida.';
            } elseif ($impDatos['huella'] !== ''
                && !hash_equals($impDatos['huella'], licencia_fingerprint_machine())) {
                $licAviso = 'Esta licencia fue emitida para otro equipo. Huella destino: '
                    . licencia_formatear_fingerprint($impDatos['huella'])
                    . ' · Este equipo: '
                    . licencia_formatear_fingerprint(licencia_fingerprint_machine()) . '.';
            } else {
                $licForm['nombre']  = $impDatos['registro'];
                $licForm['usuario'] = $impDatos['usuario'];
                $licForm['serial']  = licencia_formatear_serial($impDatos['serial']);
                $licAvisoTipo = 'ok';
                $licAviso = 'Archivo .lic válido ('
                    . (isset($impDatos['info']['nombre']) ? $impDatos['info']['nombre'] : 'Permanente')
                    . '). Revise los datos y pulse «Activar licencia».';
            }
        }
    } else {
        if ($licForm['nombre'] === '') {
            $licError = 'Debe indicar el <b>Nombre de Registro</b>.';
        } elseif ($licForm['usuario'] === '') {
            $licError = 'Debe indicar el <b>Usuario del Registro</b>.';
        } elseif (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){4}$/', strtoupper($licForm['serial'])) !== 1) {
            $licError = 'La <b>Llave Serial</b> no tiene el formato válido (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX).';
        } elseif (!licencia_validar_serial($licForm['nombre'], $licForm['usuario'], $licForm['serial'])) {
            $licError = 'La <b>Llave Serial</b> no corresponde a los datos de registro introducidos.';
        } else {
            $guardado = licencia_guardar($licForm['nombre'], $licForm['usuario'], $licForm['serial'], null, $licenciaActiva);
            if ($guardado === false) {
                $licError = 'No se pudo guardar la licencia en este equipo. Compruebe que la aplicación tiene permisos de escritura.';
            } else {
                $licOk      = true;
                $licOkTipo  = isset($guardado['info']['nombre']) ? $guardado['info']['nombre'] : 'Permanente';
                $licOkHasta = licencia_texto_vencimiento($guardado, 'Sin vencimiento (Permanente)');
                $licenciaDatos  = $guardado;
                $licenciaActiva = licencia_activada();
                $licForm['serial'] = '';
            }
        }
    }
}

if ($licOk) {
    $licenciaEstadoTxt = 'Licencia registrada correctamente en este equipo.';
} elseif ($licenciaActiva && function_exists('licencia_texto_vencimiento')) {
    $licenciaEstadoTxt = 'Licencia vigente hasta ' . licencia_texto_vencimiento($licenciaDatos)
        . '. Puede introducir una nueva llave para renovarla.';
} elseif ($licenciaDatos !== null) {
    $licenciaEstadoTxt = 'La licencia guardada no está vigente en este equipo. Active una nueva llave para continuar.';
} else {
    $licenciaEstadoTxt = 'Este equipo aún no tiene licencia. Introduzca la llave serial para activarla.';
}

if ($licenciaActiva) {
    $licEdoNombre = 'Activa';
    $licEdoTono   = 'is-ok';
    $licEdoIcono  = 'fa-circle-check';
} elseif ($licenciaDatos !== null) {
    $licEdoNombre = 'Vencida';
    $licEdoTono   = 'is-warn';
    $licEdoIcono  = 'fa-hourglass-end';
} elseif (function_exists('licencia_tiene_valor_guardado') && licencia_tiene_valor_guardado()) {
    $licEdoNombre = 'No válida';
    $licEdoTono   = 'is-warn';
    $licEdoIcono  = 'fa-triangle-exclamation';
} else {
    $licEdoNombre = 'Sin activar';
    $licEdoTono   = 'is-off';
    $licEdoIcono  = 'fa-circle-minus';
}

$licRegistro = ($licenciaDatos !== null) ? (string)$licenciaDatos['registro'] : '';
$licUsuario  = ($licenciaDatos !== null) ? (string)$licenciaDatos['usuario']  : '';
$licTipo     = ($licenciaDatos !== null && isset($licenciaDatos['info']['nombre'])) ? (string)$licenciaDatos['info']['nombre'] : '';
$licVence    = ($licenciaDatos !== null) ? licencia_texto_vencimiento($licenciaDatos) : '';
$licHuella   = function_exists('licencia_fingerprint_equipo') ? licencia_fingerprint_equipo() : '';
$licEdoActivada = ($licenciaDatos !== null && !empty($licenciaDatos['fecha_activacion']))
    ? date('d/m/Y', (int)$licenciaDatos['fecha_activacion']) : '';
$licEdoRegistro = $licRegistro;
$licEdoUsuario  = $licUsuario;
$licEdoTipo     = $licTipo;
$licEdoVence    = $licVence;
$licEdoRestante = '—';
if ($licenciaDatos !== null && function_exists('licencia_vencimiento')) {
    $venceTs = licencia_vencimiento($licenciaDatos);
    if ($venceTs === null) {
        $licEdoRestante = 'No aplica (licencia permanente)';
    } elseif (function_exists('licencia_vencida') && licencia_vencida($licenciaDatos)) {
        $diasVencida = max(0, (int)floor((time() - (int)$venceTs) / 86400));
        $licEdoRestante = $diasVencida === 1 ? 'Vencida hace 1 día' : 'Vencida hace ' . $diasVencida . ' días';
    } elseif (function_exists('licencia_dias_restantes')) {
        $dias = (int)licencia_dias_restantes($licenciaDatos);
        if ($dias === 0) {
            $licEdoRestante = 'Vence hoy';
        } elseif ($dias === 1) {
            $licEdoRestante = '1 día';
        } else {
            $licEdoRestante = $dias . ' días';
        }
    }
}

/* ---------------------------------------------------------------------------
 * Contenido de la portada.
 * ------------------------------------------------------------------------- */
$ecosistema = [
    [
        'titulo'    => 'Ecosistema Financiero',
        'texto'     => 'Facturación, nóminas, costos e inventarios en un solo lugar: entra al sistema completo y elige el módulo que necesitas.',
        'icono'     => 'fa-solid fa-wallet',
        'url'       => $base . '/index.php',
        'etiqueta'  => 'Portada del sistema',
        'destacada' => true,
    ],
    [
        'titulo'   => 'Gestión de Nóminas',
        'texto'    => 'Cálculo de nóminas, cierres mensuales, vacaciones, contribuciones y exportaciones bancarias.',
        'icono'    => 'fa-solid fa-money-check-dollar',
        'url'      => $base . '/nominas/',
        'etiqueta' => 'Módulo de nóminas',
    ],
    [
        'titulo'   => 'Facturación Electrónica',
        'texto'    => 'Emisión de facturas, clientes, servicios y reportes de cierre con control de usuarios.',
        'icono'    => 'fa-solid fa-file-invoice-dollar',
        'url'      => $base . '/facturacion/index.php',
        'etiqueta' => 'Módulo de facturación',
    ],
    [
        'titulo'   => 'Gestión de Costos',
        'texto'    => 'Fichas de costo, gastos operativos, márgenes y proyecciones para controlar la rentabilidad.',
        'icono'    => 'fa-solid fa-calculator',
        'url'      => $base . '/costos/index.php',
        'etiqueta' => 'Módulo de costos',
    ],
    [
        'titulo'   => 'Inventarios',
        'texto'    => 'Control de stock, entradas y salidas, alertas de inventario mínimo y gestión de proveedores.',
        'icono'    => 'fa-solid fa-boxes-stacked',
        'url'      => $base . '/inventarios/index.php',
        'etiqueta' => 'Módulo de inventarios',
    ],
    [
        'titulo'   => 'Administración',
        'texto'    => 'Usuarios, roles, parámetros globales, auditoría y configuración del sistema en un solo panel.',
        'icono'    => 'fa-solid fa-user-shield',
        'url'      => $base . '/admin/index.php',
        'etiqueta' => 'Módulo de administración',
    ],
    [
        'titulo'   => 'Panel del Servidor',
        'texto'    => 'Estado de Apache, PHP y MySQL: versión, uptime real del motor y lanzadores de herramientas.',
        'icono'    => 'fa-solid fa-server',
        'url'      => $base . '/phpinfo/',
        'etiqueta' => 'Información del servidor',
    ],
    [
        'titulo'   => 'Instalador de Base de Datos',
        'texto'    => 'Asistente para crear o restaurar la base de datos del sistema cuando se prepara un equipo nuevo.',
        'icono'    => 'fa-solid fa-database',
        'url'      => $base . '/InstalarBD/InstalarBD.php',
        'etiqueta' => 'Preparar el sistema',
    ],
    [
        'titulo'   => 'Soporte y Contacto',
        'texto'    => 'Documentación de soporte, contacto directo y canales de atención para la operación diaria.',
        'icono'    => 'fa-solid fa-headset',
        'url'      => $base . '/soporte.php',
        'etiqueta' => 'Ayuda y contacto',
    ],
];

$servicios = [
    ['icono' => 'fa-solid fa-database',      'titulo' => 'Base de datos',  'texto' => 'Motor MySQL con integridad referencial, respaldo periódico y restauración confiable.'],
    ['icono' => 'fa-brands fa-php',          'titulo' => 'PHP ' . $phpVersionDisplay,        'texto' => 'Compatibilidad con el stack local y rendimiento estable para todos los módulos.'],
    ['icono' => 'fa-solid fa-shield-halved', 'titulo' => 'Seguridad',      'texto' => 'Comunicación cifrada, autenticación reforzada y secretos fuera del código.'],
    ['icono' => 'fa-solid fa-arrows-rotate', 'titulo' => 'Sincronización', 'texto' => 'Información siempre al día entre módulos, sin cálculos repetidos ni desfases.'],
    ['icono' => 'fa-solid fa-clock',         'titulo' => 'Soporte 24/7',   'texto' => 'Acompañamiento continuo para la operación y resolución de incidencias.'],
    ['icono' => 'fa-solid fa-lock',          'titulo' => 'Licencia local', 'texto' => 'Todo funciona en el equipo del cliente: los datos no salen de la red local.'],
];

$faq = [
    [
        'p' => '¿Qué es UnicornioSoftware?',
        'r' => 'Es un grupo de programación y desarrollo de software, patrocinado por el Ms.C. Franklin Ramos Lamadrid. Se dedica a crear soluciones tecnológicas, herramientas empresariales y plataformas digitales adaptadas a las necesidades reales de los negocios, combinando experiencia técnica, visión práctica y acompañamiento cercano.',
    ],
    [
        'p' => '¿Qué es SiGesEmp?',
        'r' => 'SiGesEmp es un conjunto de aplicaciones para tu negocio, diseñado para integrar y simplificar la gestión diaria. Reúne módulos como facturación, nóminas, costos, inventarios y administración, permitiendo trabajar con información organizada en una misma plataforma y con mayor control de cada proceso.',
    ],
    [
        'p' => '¿Cómo funciona la licencia del sistema?',
        'r' => 'La licencia se guarda cifrada y solo en este equipo. Desde la portada puedes <a href="#licencia-estado">revisar su estado</a>, introducir una llave serial, importar un archivo <code>.lic</code> o solicitar la licencia por correo, sin salir del sistema.',
    ],
    [
        'p' => '¿Qué pasa si la licencia vence?',
        'r' => 'Si la licencia vence, los módulos dejan de abrir hasta registrar una llave válida. Todo el trámite de activación o renovación se realiza desde la portada, de forma rápida y sin perder la información de tu equipo.',
    ],
    [
        'p' => '¿Cómo adquiero una licencia?',
        'r' => 'Revisa los <a href="#precios">planes y precios</a> y elige el que mejor se adapte a tu negocio. Luego, desde la sección <a href="#licencia-estado">Estado de la licencia</a> puedes solicitarla por correo, recibir tu llave o archivo <code>.lic</code> y activarla en este mismo equipo. Si necesitas ayuda, escribe a <a href="mailto:kakycu@gmail.com">kakycu@gmail.com</a> o por WhatsApp al <a href="https://wa.me/5352712861">+53 5 2712861</a>.',
    ],
    [
        'p' => '¿Necesito internet para usar el sistema?',
        'r' => 'No. El sistema corre en tu propio equipo o en tu red local con Apache, PHP y MySQL. Tus datos se mantienen dentro de tu red.',
    ],
    [
        'p' => '¿Pueden desarrollar algo a la medida de mi negocio?',
        'r' => 'Sí. Además de la plataforma, desarrollamos software a medida: herramientas internas, plataformas completas, sitios web, APIs e integraciones. Conoce más en <a href="/desamedida.php">Software a medida</a> o en <a href="/about.php">Sobre nosotros</a>.',
    ],
    [
        'p' => '¿Cómo puedo empezar o solicitar soporte?',
        'r' => 'Si el equipo es nuevo, puedes comenzar por el <a href="' . $base . '/InstalarBD/InstalarBD.php">Instalador de base de datos</a>. Para soporte o consultas, escribe a <a href="mailto:kakycu@gmail.com">kakycu@gmail.com</a> o llama al <a href="tel:+5352712861">+53 5 2712861</a>.',
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>UnicornioSoftware Cuba · SOFTWARE PARA TRANSFORMAR TU NEGOCIO</title>
<meta name="description" content="Facturación, nóminas, costos e inventarios en una plataforma que corre en tu propia red. UnicornioSoftware Cuba.">
<link rel="icon" type="image/png" href="/images/logo.png">
<link rel="stylesheet" href="/css/font-awesome6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/fonts/fuentes.css">
<link rel="stylesheet" href="/css/portada-licencia.css">
<link rel="stylesheet" href="/css/styleindex.css">
<script>document.documentElement.classList.add('js');</script>
</head>
<body id="top" class="rv-mov">

<!-- Intro animada: piezas del logo en images/anim. Se retira sola y se puede
     saltar; con «prefers-reduced-motion» no se muestra. -->
<div class="intro<?= $licModalAbierto ? ' is-out' : '' ?>" id="introAnim">
    <div class="intro-stage">
        <div class="intro-halo"></div>
        <div class="intro-layer" data-paso="1"><img src="/images/anim/01_Circulo.png" alt="" width="498" height="490" decoding="async"></div>
        <div class="intro-layer" data-paso="2"><img src="/images/anim/02_Unicornio.png" alt="" width="456" height="303" decoding="async"></div>
        <div class="intro-layer" data-paso="3"><img src="/images/anim/03_Trabajadores.png" alt="" width="446" height="319" decoding="async"></div>
        <div class="intro-layer" data-paso="4"><img src="/images/anim/04_Elementos_Nomina.png" alt="" width="412" height="372" decoding="async"></div>
        <div class="intro-layer" data-paso="5"><img src="/images/anim/05_Logo_SiGesNom.png" alt="" width="546" height="183" decoding="async"></div>
        <div class="intro-final">
            <div class="intro-logo">
                <img src="/images/anim/final.png" alt="UnicornioSoftware Cuba" width="429" height="440" decoding="async">
                <span class="intro-sheen" aria-hidden="true" style="-webkit-mask-image:url('/images/anim/final.png');mask-image:url('/images/anim/final.png');"></span>
            </div>
        </div>
    </div>
    <div class="intro-bar" aria-hidden="true"><span id="introBar"></span></div>
    <div class="intro-acciones">
        <button type="button" class="intro-skip" id="introNoMostrar">No volver a mostrar </button>
        <button type="button" class="intro-skip" id="introReplay">Repetir</button>
        <button type="button" class="intro-skip" id="introSkip">Saltar</button>
    </div>
</div>
<script>(function(){var i=document.getElementById('introAnim');if(localStorage.getItem('introOculta')==='1'){if(i)i.classList.add('is-out');}else{document.documentElement.classList.add('intro-lock');}})();</script>

<!-- ===== Barra de progreso de lectura ===== -->
<div class="scroll-progress" aria-hidden="true"><span id="scrollProgress"></span></div>

<!-- ===== Navegación ===== -->
<nav class="nav">
    <div class="wrap nav-in">
        <a class="marca" href="/index2.php" title="UnicornioSoftware Cuba">
            <img src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <span class="marca-txt">
                <b><i>UNICORNIO</i><em>SOFTWARE</em></b>
                <small>Cuba · Gestión empresarial</small>
            </span>
        </a>

        <div class="nav-links">
            <a href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            <a href="#ecosistema"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ingeniería</a>
            <a href="#medida"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
            <a href="#proceso"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Proceso</a>
            <a href="#nosotros"><i class="fa-solid fa-users" aria-hidden="true"></i> Nosotros</a>
            <a href="#plataforma"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Plataforma</a>
            <div class="nav-drop" id="navLic">
                <button type="button" class="nav-drop-toggle" id="navLicBtn" data-nav-scroll="#licencia-estado" aria-haspopup="true" aria-expanded="false" aria-controls="navLicMenu">
                    <i class="fa-solid fa-key" aria-hidden="true"></i> Licencia
                    <i class="fa-solid fa-chevron-down nav-drop-caret" aria-hidden="true"></i>
                </button>
                <div class="nav-drop-menu" id="navLicMenu" role="menu" aria-labelledby="navLicBtn">
                    <button type="button" role="menuitem" data-lic-open><i class="fa-solid fa-key" aria-hidden="true"></i> Activar / Renovar</button>
                    <button type="button" role="menuitem" data-estado-lic-open><i class="fa-solid fa-id-card" aria-hidden="true"></i> Ver ficha completa</button>
                    <button type="button" role="menuitem" data-sol-open><i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar por correo</button>
                    <a role="menuitem" href="#precios"><i class="fa-solid fa-tags" aria-hidden="true"></i> Ver Planes / Precios</a>
                    <a role="menuitem" href="/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Solicitar Soporte</a>
                </div>
            </div>
            <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
        </div>

        <div class="nav-acc">
            <div class="nav-login" id="navLogin">
                <button type="button" class="btn btn-cian btn-sm" id="navLoginBtn"
                        aria-haspopup="true" aria-expanded="false" aria-controls="navLoginMenu">
                    <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Iniciar sesión
                    <i class="fa-solid fa-chevron-down nav-login-caret" aria-hidden="true"></i>
                </button>
                <div class="nav-login-menu" id="navLoginMenu" role="menu" aria-labelledby="navLoginBtn">
                    <a role="menuitem" href="<?= $base ?>/nominas/login.php">
                        <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas
                    </a>
                    <a role="menuitem" href="<?= $base ?>/facturacion/login.php">
                        <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación
                    </a>
                    <a role="menuitem" href="<?= $base ?>/costos/login.php">
                        <i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos
                    </a>
                    <a role="menuitem" href="<?= $base ?>/inventarios/login.php">
                        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios
                    </a>
                    <a role="menuitem" href="<?= $base ?>/administracion/index.php">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración
                    </a>
                </div>
            </div>
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Abrir menú" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="menu-movil" id="menuMovil">
        <a href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
        <a href="#ecosistema"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Ecosistema</a>
        <a href="#servicios"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ingeniería</a>
        <a href="#medida"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
        <a href="#proceso"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Proceso</a>
        <a href="#nosotros"><i class="fa-solid fa-users" aria-hidden="true"></i> Nosotros</a>
        <a href="#plataforma"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Plataforma</a>
        <span class="menu-movil-titulo">Licencia</span>
        <button type="button" class="menu-movil-item" data-lic-open><i class="fa-solid fa-key" aria-hidden="true"></i> Activar / Renovar</button>
        <button type="button" class="menu-movil-item" data-estado-lic-open><i class="fa-solid fa-id-card" aria-hidden="true"></i> Ver ficha completa</button>
        <button type="button" class="menu-movil-item" data-sol-open><i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar por correo</button>
        <a href="#precios"><i class="fa-solid fa-tags" aria-hidden="true"></i> Ver Planes / Precios</a>
        <a href="/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Solicitar Soporte</a>
        <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
        <span class="menu-movil-titulo">Iniciar sesión</span>
        <a href="<?= $base ?>/nominas/login.php"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a>
        <a href="<?= $base ?>/facturacion/login.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a>
        <a href="<?= $base ?>/costos/login.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a>
        <a href="<?= $base ?>/inventarios/login.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a>
        <a href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a>
    </div>
</nav>

<!-- ===== Hero ===== -->
<header class="hero" id="inicio">
    <div class="hero-fondo" aria-hidden="true">
        <img class="is-on" id="heroFondoA" src="/images/nominas-hero.webp"
             width="1376" height="768" alt="" fetchpriority="high" decoding="async">
        <img id="heroFondoB" src="/images/hero.webp"
             width="1376" height="768" alt="" loading="lazy" decoding="async">
    </div>
    <div class="hero-velo" aria-hidden="true"></div>

    <div class="wrap hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">UnicornioSoftware · Gestión empresarial <span>· Cuba</span></span>
            <h1 class="bebas">SOFTWARE PARA <span>TRANSFORMAR TU NEGOCIO.</span></h1>
            <p class="hero-bajada">
                Facturación, nóminas, costos e inventarios en una plataforma que corre en tu propia red,
                pensada para resolver el día a día real de tu operación.
            </p>
            <div class="hero-acciones">
                <a class="btn btn-fuego" href="#ecosistema">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Explorar el ecosistema
                </a>
                <a class="btn btn-cian" href="/desamedida.php">
                    <i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> Desarrollo a medida
                </a>
            </div>
            <div class="tags">
                <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Productos propios</span>
                <span class="sep" aria-hidden="true">·</span>
                <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Tecnología Aplicada</span>
                <span class="sep" aria-hidden="true">·</span>
                <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Soporte técnico</span>
            </div>
            <p style="margin-top:1rem;font-family:'Inter',sans-serif;font-size:.85rem;color:var(--gris-3);letter-spacing:.01em;"><i class="fa-solid fa-shield-halved" style="color:var(--cian);margin-right:.4rem" aria-hidden="true"></i>Instalación local &middot; sin dependencia de la nube &middot; respaldo periódico</p>
            <p style="margin-top:.55rem;font-family:'Inter',sans-serif;font-size:.9rem;color:var(--gris-2);">¿Ya lo usas, licencia vencida? <button type="button" data-lic-open style="background:none;border:0;padding:0;color:var(--cian);font:inherit;font-weight:600;text-decoration:underline;text-underline-offset:4px;cursor:pointer;transition:color .2s;">Renueva o activa tu licencia</button>, <a href="#precios" style="color:var(--fuego);font-weight:600;text-decoration:underline;text-underline-offset:4px;transition:color .2s;">consulta precios</a></p>


        </div>

        <div class="escena">
            <div class="escena-nav">
                <button type="button" class="pill is-on" data-slide="0" style="--brillo: 37, 99, 235" aria-label="Ver Nóminas en el monitor">
                    <span class="dot" aria-hidden="true"></span> Nóminas
                </button>
                <button type="button" class="pill" data-slide="1" style="--brillo: 0, 200, 83" aria-label="Ver Facturación en el monitor">
                    <span class="dot" aria-hidden="true"></span> Facturación
                </button>
                <button type="button" class="pill" data-slide="2" style="--brillo: 255, 167, 38" aria-label="Ver Costos en el monitor">
                    <span class="dot" aria-hidden="true"></span> Costos
                </button>
                <button type="button" class="pill" data-slide="3" style="--brillo: 139, 92, 246" aria-label="Ver Inventarios en el monitor">
                    <span class="dot" aria-hidden="true"></span> Inventarios
                </button>
                <button type="button" class="pill" data-slide="4" style="--brillo: 0, 212, 255" aria-label="Ver Administración en el monitor">
                    <span class="dot" aria-hidden="true"></span> Administración
                </button>
            </div>

            <div class="diapo is-on" style="--brillo: 37, 99, 235">
                <div class="pantalla">
                    <img src="/images/nominas-monitor.webp"
                         width="1672" height="940" alt="Panel del subsistema de nóminas SisGeNom" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/images/nominas-movil.webp"
                         width="540" height="1054" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/images/nominas-icono.webp"
                                 width="40" height="40" alt="" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <strong>Subsistema de Nóminas · SisGeNom</strong>
                            <span>Mantén todo en un solo lugar: nóminas, trabajadores, aportes, banco</span>
                        </div>
                    </div>
                    <a class="chip-btn" href="#ecosistema">
                        <span>Ver más</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="diapo" style="--brillo: 0, 200, 83">
                <div class="pantalla">
                    <img src="/images/facturacion-monitor.webp"
                         width="1672" height="940" alt="Panel del subsistema de facturación SisGeFac" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/images/facturacion-movil.webp"
                         width="540" height="1054" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/images/facturacion-icono.webp"
                                 width="40" height="40" alt="" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <strong>Subsistema de Facturación · SisGeFac</strong>
                            <span>Factura, contrata, cotiza. Controla todas tus ventas</span>
                        </div>
                    </div>
                    <a class="chip-btn" href="#ecosistema">
                        <span>Ver más</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="diapo" style="--brillo: 255, 167, 38">
                <div class="pantalla">
                    <img src="/images/costos.png"
                         width="1024" height="1024" alt="Panel del subsistema de Costos" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/images/costos.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/images/costos.png"
                                 width="40" height="40" alt="" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <strong>Subsistema de Costos</strong>
                            <span>Controla gastos, centros de costo y análisis de tu operación</span>
                        </div>
                    </div>
                    <a class="chip-btn" href="#ecosistema">
                        <span>Ver más</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="diapo" style="--brillo: 139, 92, 246">
                <div class="pantalla">
                    <img src="/images/inventario.png"
                         width="1024" height="1024" alt="Panel del subsistema de Inventarios" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/images/inventario.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/images/inventario.png"
                                 width="40" height="40" alt="" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <strong>Subsistema de Inventarios</strong>
                            <span>Gestiona almacenes, existencias y movimientos en tiempo real</span>
                        </div>
                    </div>
                    <a class="chip-btn" href="#ecosistema">
                        <span>Ver más</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="diapo" style="--brillo: 0, 212, 255">
                <div class="pantalla">
                    <img src="/images/admin.png"
                         width="1024" height="1024" alt="Panel del subsistema de Administración" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/images/admin.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/images/admin.png"
                                 width="40" height="40" alt="" loading="lazy" decoding="async">
                        </span>
                        <div>
                            <strong>Subsistema de Administración</strong>
                            <span>Configura usuarios, permisos y parámetros del sistema</span>
                        </div>
                    </div>
                    <a class="chip-btn" href="#ecosistema">
                        <span>Ver más</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <a href="#ecosistema" class="hero-scroll" aria-label="Desplazarse hacia abajo"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></a>
</header>

<section class="apk-download" aria-label="Descarga la app para Android" id="apk-download" data-apk-version-url="android-apk/dist/version.json" data-apk-version-fallback="https://raw.githubusercontent.com/kakycu/TransNuBeT/main/android-apk/dist/version.json">
    <div class="wrap apk-download__inner">
        <img src="/images/logo.png" alt="Logo UnicornioSoftware" class="apk-download__logo" width="56" height="56" loading="lazy" decoding="async">
        <div class="apk-download__copy">
            <p class="apk-download__text">¡DESCARGA LA APK PARA ANDROID!</p>
            <p class="apk-download__version" id="apkVersionInfo">Comprobando versión disponible...</p>
            <p class="apk-download__status" id="apkUpdateStatus" aria-live="polite"></p>
        </div>
        <a class="btn btn-fuego apk-download__btn" id="apkDownloadBtn" href="https://github.com/kakycu/TransNuBeT/raw/main/android-apk/dist/EcosisUnicorn.apk" download>
            <i class="fa-brands fa-android" aria-hidden="true"></i> Descargar APK
        </a>
    </div>
</section>

    <!-- Strip de beneficios (estilo Reven/CyberForja) -->
    <section class="reven-feats-strip" aria-label="Beneficios principales">
        <div class="reven-feats-grid">
            <div class="rv-ap reven-feat" style="--rv-i:0">
                <div class="reven-feat-icon">
                    <i class="fas fa-wifi" aria-hidden="true"></i>
                </div>
                <p class="reven-feat-copy"><span class="reven-feat-title">Sin internet</span><span class="reven-feat-desc">Facturas, Vendes, Cotizas, Pagas Aunque Se Caiga La Red</span></p>
            </div>
            <div class="rv-ap reven-feat" style="--rv-i:1">
                <div class="reven-feat-icon">
                    <i class="fas fa-desktop" aria-hidden="true"></i>
                </div>
                <p class="reven-feat-copy"><span class="reven-feat-title">PC modesta</span><span class="reven-feat-desc">Windows 7 y 2 GB de RAM</span></p>
            </div>
            <div class="rv-ap reven-feat" style="--rv-i:2">
                <div class="reven-feat-icon">
                    <i class="fas fa-coins" aria-hidden="true"></i>
                </div>
                <p class="reven-feat-copy"><span class="reven-feat-title">CUP &middot; USD &middot; EUR</span><span class="reven-feat-desc">Con la tasa que tú decides</span></p>
            </div>
            <div class="rv-ap reven-feat" style="--rv-i:3">
                <div class="reven-feat-icon">
                    <i class="fas fa-gift" aria-hidden="true"></i>
                </div>
                <p class="reven-feat-copy"><span class="reven-feat-title">15 días gratis</span><span class="reven-feat-desc">Sin tarjeta ni registro</span></p>
            </div>
        </div>
    </section>

<main>
    <!-- ===== Ecosistema ===== -->
    <section class="sec" id="ecosistema">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">El ecosistema</span>
                <h2 class="bebas">Todo el sistema, a un clic</h2>
                <p>Facturación, nóminas, costos, inventarios y administración corriendo en tu propia red. Elige el módulo con el que quieres trabajar.</p>
            </div>

            <div class="grid">
                <?php foreach ($ecosistema as $mod): ?>
                <article class="card reveal<?= !empty($mod['destacada']) ? ' destacada' : '' ?>" data-spotlight
                         style="--card-sheen: <?= !empty($mod['destacada']) ? 'rgba(255, 107, 0, .22)' : 'rgba(0, 212, 255, .18)' ?>">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="<?= htmlspecialchars($mod['icono'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                        <div>
                            <h3><?= htmlspecialchars($mod['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="card-sub"><?= htmlspecialchars($mod['etiqueta'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <p><?= htmlspecialchars($mod['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="card-link" href="<?= htmlspecialchars($mod['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <span>Abrir módulo</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== Nuestra ingeniería ===== -->
        <section id="servicios">
        <div class="circuit-bg">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%">
                <defs>
                    <pattern id="circuit-grid-pattern-index2" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(30, 41, 59, 0.7)" stroke-width="1"></path>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#circuit-grid-pattern-index2)"></rect>
                <g stroke="none" fill="none" stroke-linecap="round">
                    <path class="circuit-line-cian" d="M -100 120 L 500 120 L 560 180 L 1100 180 L 1160 120 L 2200 120" stroke="#00D4FF" stroke-width="1.5"></path>
                    <path class="circuit-line-cian-delay" d="M 180 -100 L 180 500 L 300 620 L 300 1500" stroke="#00D4FF" stroke-width="1.5"></path>
                    <path class="circuit-line-fuego" d="M 960 -100 L 960 400 L 1020 460 L 1020 1500" stroke="#FF6B00" stroke-width="1.5"></path>
                    <path class="circuit-line-fuego-slow" d="M -100 840 L 1400 840 L 1460 780 L 2200 780" stroke="#FF6B00" stroke-width="1.5"></path>
                </g>
            </svg>
        </div>
        <div class="circuit-radial"></div>

        <div class="ing-wrap">
            <div class="reveal" style="max-width:48rem;margin-bottom:2.5rem">
                <span class="ing-eyebrow">Nuestra ingeniería</span>
                <h2 class="ing-title">NUESTRA INGENIERÍA.</h2>
                <div class="ing-line"></div>
                <p class="ing-desc">
                    Elegimos la arquitectura y las herramientas según las necesidades de cada proyecto, priorizando utilidad, seguridad y mantenimiento.
                </p>
            </div>

            <div class="ing-grid">
                <div data-spotlight="true" style="--card-sheen:rgba(0,212,255,0.18);" class="ing-card cian reveal">
                    <div class="card__sheen" aria-hidden="true"></div>
                    <div class="ing-icon cian"><i class="fas fa-hand-pointer"></i></div>
                    <h3>Interfaces claras</h3>
                    <p>Diseñadas para las tareas de quienes utilizan el sistema en su día a día.</p>
                </div>

                <div data-spotlight="true" style="--card-sheen:rgba(255,107,0,0.18);" class="ing-card fuego reveal">
                    <div class="card__sheen" aria-hidden="true"></div>
                    <div class="ing-icon fuego"><i class="fas fa-layer-group"></i></div>
                    <h3>Arquitectura adecuada</h3>
                    <p>Soluciones locales, web o móviles según los requisitos reales del proyecto.</p>
                </div>

                <div data-spotlight="true" style="--card-sheen:rgba(0,212,255,0.18);" class="ing-card cian reveal">
                    <div class="card__sheen" aria-hidden="true"></div>
                    <div class="ing-icon cian"><i class="fas fa-shield-halved"></i></div>
                    <h3>Protección de datos</h3>
                    <p>Control de accesos y respaldos automáticos según las necesidades del negocio.</p>
                </div>

                <div data-spotlight="true" style="--card-sheen:rgba(255,107,0,0.18);" class="ing-card fuego reveal">
                    <div class="card__sheen" aria-hidden="true"></div>
                    <div class="ing-icon fuego"><i class="fas fa-arrow-trend-up"></i></div>
                    <h3>Evolución del software</h3>
                    <p>Mejoras y nuevas funcionalidades continuas durante todo su ciclo de vida.</p>
                </div>
            </div>

            <div class="integraciones-block reveal">
                <h3>Integraciones disponibles en nuestros productos de gestión</h3>
                <p>No todas aplican a cada producto: las de Reven están verificadas; en desarrollos a medida se definen con el cliente.</p>
                <div class="integraciones-grid">
                    <div class="integracion-item int-wa">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        <span class="int-name">WhatsApp</span>
                        <span class="int-desc">Comprobantes y soporte</span>
                    </div>
                    <div class="integracion-item int-cian">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                        <span class="int-name">Transfermóvil</span>
                        <span class="int-desc">Cobros por QR</span>
                    </div>
                    <div class="integracion-item int-cian">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                        <span class="int-name">EnZona</span>
                        <span class="int-desc">Cobros por QR</span>
                    </div>
                    <div class="integracion-item int-fuego">
                        <i class="fab fa-google-drive" aria-hidden="true"></i>
                        <span class="int-name">Google Drive</span>
                        <span class="int-desc">Respaldos remotos</span>
                    </div>
                    <div class="integracion-item int-cian">
                        <i class="fas fa-barcode" aria-hidden="true"></i>
                        <span class="int-name">Lectores USB</span>
                        <span class="int-desc">Ópticos y cámara</span>
                    </div>
                    <div class="integracion-item int-fuego">
                        <i class="fas fa-print" aria-hidden="true"></i>
                        <span class="int-name">Térmicas</span>
                        <span class="int-desc">Tickets 58 y 80 mm</span>
                    </div>
                    <div class="integracion-item int-excel">
                        <i class="fas fa-file-excel" aria-hidden="true"></i>
                        <span class="int-name">Excel</span>
                        <span class="int-desc">Importación de datos</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ===== Ingeniería personalizada ===== -->
    <section id="medida" class="medida-cf section-seam">
        <div class="medida-cf__wrap">
            <div class="medida-cf__head reveal">
                <span class="medida-cf__eyebrow">Ingeniería personalizada</span>
                <h2 class="medida-cf__title">SOFTWARE A MEDIDA.</h2>
                <div class="medida-cf__rule" aria-hidden="true"></div>
                <p class="medida-cf__desc">
                    Analizamos tus procesos y desarrollamos aplicaciones adaptadas a tu organización, desde herramientas internas hasta plataformas completas.
                </p>
                <a href="/desamedida.php" id="btn-cta-medida" class="medida-cf__cta">
                    Cuéntanos tu proyecto →
                </a>
            </div>

            <div class="medida-cf__steps reveal">
                <div class="medida-cf__grid">
                    <div class="medida-cf__card">
                        <span class="medida-cf__num is-fuego">01. ANALIZAMOS</span>
                        <h4 class="medida-cf__card-title">Escuchamos y evaluamos</h4>
                        <p class="medida-cf__card-desc">Entendemos tus procesos y objetivos para definir la arquitectura adecuada.</p>
                    </div>
                    <div class="medida-cf__card">
                        <span class="medida-cf__num is-cian">02. DISEÑAMOS</span>
                        <h4 class="medida-cf__card-title">Estructura y experiencia</h4>
                        <p class="medida-cf__card-desc">Modelamos interfaces claras y flujos eficientes para los usuarios.</p>
                    </div>
                    <div class="medida-cf__card">
                        <span class="medida-cf__num is-fuego">03. DESARROLLAMOS</span>
                        <h4 class="medida-cf__card-title">Ingeniería sólida</h4>
                        <p class="medida-cf__card-desc">Escribimos código limpio, seguro y optimizado para cada plataforma.</p>
                    </div>
                    <div class="medida-cf__card">
                        <span class="medida-cf__num is-cian">04. IMPLEMENTAMOS</span>
                        <h4 class="medida-cf__card-title">Puesta en marcha</h4>
                        <p class="medida-cf__card-desc">Desplegamos el sistema y acompañamos su evolución continua.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Nuestro proceso ===== -->
    <section class="sec" id="proceso">
        <div class="wrap">
            <div class="proceso-head reveal">
                <span class="eyebrow">Nuestro proceso</span>
                <h2 class="bebas">Cómo trabajamos.</h2>
                <div class="rule-fuego" aria-hidden="true"></div>
                <p>Combinamos análisis, diseño y desarrollo para construir sistemas que puedan utilizarse, mantenerse y evolucionar.</p>
            </div>

            <div class="proceso-grid">
                <div class="proc-paso">
                    <div>
                        <span class="proc-num">01</span>
                        <h3>Escuchamos y analizamos</h3>
                        <p>Entendemos las necesidades, el entorno de trabajo y los problemas concretos que debe resolver el software.</p>
                    </div>
                </div>
                <div class="proc-paso is-fuego">
                    <div>
                        <span class="proc-num">02</span>
                        <h3>Diseñamos la solución</h3>
                        <p>Definimos las funcionalidades, la arquitectura de datos y la experiencia de usuario para garantizar simplicidad.</p>
                    </div>
                </div>
                <div class="proc-paso">
                    <div>
                        <span class="proc-num">03</span>
                        <h3>Desarrollamos</h3>
                        <p>Construimos el sistema de manera progresiva, con entregas parciales para validar resultados en cada etapa.</p>
                    </div>
                </div>
                <div class="proc-paso is-fuego">
                    <div>
                        <span class="proc-num">04</span>
                        <h3>Probamos e implementamos</h3>
                        <p>Validamos el funcionamiento con pruebas reales, corregimos incidencias y preparamos la puesta en marcha.</p>
                    </div>
                </div>
                <div class="proc-paso">
                    <div>
                        <span class="proc-num">05</span>
                        <h3>Acompañamos su evolución</h3>
                        <p>Ofrecemos soporte técnico, capacitación y mejoras continuas según las condiciones acordadas para cada solución.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Quiénes somos ===== -->
    <section class="sec" id="nosotros">
        <div class="wrap">
            <!-- Encabezado centrado -->
            <div class="equipo-head reveal">
                <span class="eyebrow">Sobre UnicornioSoftware</span>
                <h2 class="bebas">Quiénes somos.</h2>
                <div class="rule-fuego" aria-hidden="true"></div>
                <p>
                    Somos un equipo que combina experiencia en software empresarial, simulación y
                    desarrollo interactivo para crear soluciones propias y proyectos especializados.
                </p>
            </div>

            <!-- Ficha corporativa: tres pilares técnicos + compromiso y visión -->
            <div class="equipo-card reveal" data-spotlight style="--card-sheen: rgba(255, 107, 0, .18)">
                <span class="card__sheen" aria-hidden="true"></span>

                <div class="equipo-grid">
                    <div class="pilares">
                        <div class="pilar">
                            <span class="pilar-ico is-fuego"><i class="fa-solid fa-code" aria-hidden="true"></i></span>
                            <h3>Ingeniería y arquitectura</h3>
                            <p>Más de una década diseñando sistemas de escritorio autónomos, bases de datos locales y servicios web escalables.</p>
                        </div>
                        <div class="pilar">
                            <span class="pilar-ico is-cian"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                            <h3>Diseño y experiencia</h3>
                            <p>Interfaces pensadas para simplificar tareas complejas en el ritmo diario de trabajo comercial.</p>
                        </div>
                        <div class="pilar">
                            <span class="pilar-ico is-fuego"><i class="fa-solid fa-headset" aria-hidden="true"></i></span>
                            <h3>Cercanía y soporte</h3>
                            <p>Trato directo con el equipo que construye el software, con atención cercana y respuestas ágiles.</p>
                        </div>
                    </div>

                    <div class="compromiso">
                        <div>
                            <h4>Compromiso y visión</h4>
                            <p>Conoce nuestra historia, cómo trabajamos cada desarrollo y la trayectoria que respalda a UnicornioSoftware.</p>
                        </div>
                        <a class="btn-outline-fuego" href="/about.php">
                            <i class="fa-solid fa-users" aria-hidden="true"></i> Conoce nuestro equipo &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<section class="sec" id="plataforma">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Bajo el capó</span>
                <h2 class="bebas">La plataforma <button type="button" class="sec-refresh" id="btnRefreshPlataforma" title="Actualizar sección" aria-label="Actualizar sección La plataforma"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button></h2>
                <p>Un stack local probado en producción: Apache, PHP y MySQL, con respaldo y soporte del equipo que lo construyó.</p>
            </div>

                        <div class="metrics" role="list" aria-label="Indicadores de la plataforma" style="padding:0 0 1.5rem">
                <div class="metric" role="listitem">
                    <i class="fa-solid fa-cubes" aria-hidden="true"></i>
                    <div><b><?= count($ecosistema) ?></b><span>Módulos de gestión</span></div>
                </div>
                <div class="metric" role="listitem">
                    <i class="fa-brands fa-php" aria-hidden="true"></i>
                    <div><b>PHP <?= htmlspecialchars($phpVersionDisplay, ENT_QUOTES, 'UTF-8') ?></b><span>Servidor local</span></div>
                </div>
                <div class="metric" role="listitem">
                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                    <div><b>MySQL <?= htmlspecialchars($mysqlVersionDisplay, ENT_QUOTES, 'UTF-8') ?></b><span>Base de datos</span></div>
                </div>
                <div class="metric" role="listitem">
                    <i class="fa-solid fa-server" aria-hidden="true"></i>
                    <div><b>Apache <?= htmlspecialchars($apacheVersion, ENT_QUOTES, 'UTF-8') ?></b><span>HTTPD</span></div>
                </div>
                <div class="metric" role="listitem">
                    <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                    <div><b><?= htmlspecialchars($phpmyadminVersion, ENT_QUOTES, 'UTF-8') ?></b><span>phpMyAdmin</span></div>
                </div>
                <div class="metric" role="listitem">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <div><b>24/7</b><span>Soporte técnico</span></div>
                </div>
            </div>
<div class="serv-grid">
                <?php foreach ($servicios as $srv): ?>
                <div class="serv reveal">
                    <i class="<?= htmlspecialchars($srv['icono'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    <div>
                        <strong><?= htmlspecialchars($srv['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars($srv['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

                        <div class="db-health reveal">
                <div class="db-health-head">
                    <i class="fa-solid fa-heart-pulse" aria-hidden="true"></i>
                    <span>Salud de la Base de Datos (<?= htmlspecialchars($dbName !== '' ? $dbName : 'No conectada', ENT_QUOTES, 'UTF-8') ?>)</span>
                </div>
                <div class="db-health-grid">
                    <div class="db-health-item">
                        <i class="fa-solid fa-database" style="color:var(--cian)" aria-hidden="true"></i>
                        <b><?= htmlspecialchars($dbSize, ENT_QUOTES, 'UTF-8') ?></b>
                        <span>Tamaño BD</span>
                    </div>
                    <div class="db-health-item">
                        <i class="fa-solid fa-table" style="color:#4ade80" aria-hidden="true"></i>
                        <b><?= htmlspecialchars($dbTables, ENT_QUOTES, 'UTF-8') ?></b>
                        <span>Tablas</span>
                    </div>
                    <div class="db-health-item" title="<?= htmlspecialchars($dbUptimeTitle, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa-solid fa-clock" style="color:#fbbf24" aria-hidden="true"></i>
                        <b><?= htmlspecialchars($dbUptime, ENT_QUOTES, 'UTF-8') ?></b>
                        <span>Uptime MySQL</span>
                    </div>
                </div>
            </div>
<div class="lic-acciones reveal" style="margin-top:1.6rem">
                <a class="btn btn-ghost btn-sm" href="<?= $base ?>/phpinfo/">
                    <i class="fa-solid fa-server" aria-hidden="true"></i> Estado del servidor
                </a>
                <a class="btn btn-ghost btn-sm" href="<?= $base ?>/InstalarBD/InstalarBD.php">
                    <i class="fa-solid fa-database" aria-hidden="true"></i> Instalador de base de datos
                </a>
            </div>
        </div>
    </section>


    <!-- ===== Estado de la licencia ===== -->
    <section class="sec" id="licencia-estado">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Registro del equipo</span>
                <h2 class="bebas">Estado de la licencia</h2>
                <p>La licencia se guarda cifrada y solo en este equipo. Aquí puedes revisar su estado y renovarla cuando haga falta.</p>
            </div>

            <div class="lic-grid">
                <div class="panel reveal">
                    <span class="badge-estado <?= $licEdoTono ?>">
                        <i class="fa-solid <?= $licEdoIcono ?>" aria-hidden="true"></i> <?= htmlspecialchars($licEdoNombre, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <dl class="datos">
                        <div class="dato">
                            <dt>Nombre de registro</dt>
                            <dd><?= $licRegistro !== '' ? htmlspecialchars($licRegistro, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                        <div class="dato">
                            <dt>Usuario del registro</dt>
                            <dd><?= $licUsuario !== '' ? htmlspecialchars($licUsuario, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                        <div class="dato">
                            <dt>Tipo de licencia</dt>
                            <dd><?= $licTipo !== '' ? htmlspecialchars($licTipo, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                        <div class="dato">
                            <dt>Vence</dt>
                            <dd><?= $licVence !== '' ? htmlspecialchars($licVence, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                        <div class="dato">
                            <dt>Tiempo restante</dt>
                            <dd><?= htmlspecialchars($licEdoRestante, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="dato">
                            <dt>Código de equipo</dt>
                            <dd class="mono"><?= $licHuella !== '' ? htmlspecialchars($licHuella, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                        </div>
                    </dl>

                    <div class="lic-acciones">
                        <button type="button" class="btn btn-fuego btn-sm" data-lic-open>
                            <i class="fa-solid fa-key" aria-hidden="true"></i> Activar o renovar
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" data-estado-lic-open>
                            <i class="fa-solid fa-id-card" aria-hidden="true"></i> Ver ficha completa
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" data-sol-open>
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar por correo
                        </button>
                        <a class="btn btn-ghost btn-sm" href="<?= $base ?>/soporte.php">
                            <i class="fa-solid fa-headset" aria-hidden="true"></i> Solicitar Soporte
                        </a>
                    </div>
                </div>

                <div class="panel reveal">
                    <h3 class="bebas" style="font-size:1.7rem;text-transform:uppercase">Cómo funciona</h3>
                    <p class="lic-nota" style="margin-top:.7rem">
                        La llave serial se valida contra los datos de registro (<b>nombre</b> y <b>usuario</b>) y se
                        guarda cifrada en el registro de Windows o en un archivo cifrado del disco. Así, la activación queda vinculada a este equipo y el sistema puede verificar su estado de forma segura.
                    </p>
                    <p class="lic-nota" style="margin-top:.9rem">
                        Para renovar, solo debes introducir una llave nueva: la anterior se reemplaza sin perder los datos
                        ni la configuración del sistema. Si la licencia vence, los módulos dejan de abrir hasta registrar una llave válida.
                    </p>
                    <p class="lic-nota" style="margin-top:.9rem">
                        Todo el trámite —activar, renovar, importar un archivo <code>.lic</code> o solicitar la licencia por correo—
                        se realiza aquí mismo, sin salir de la portada. Antes de activar, puedes revisar los planes disponibles y elegir el que mejor se adapte a tu negocio.
                    </p>
                    <div style="margin-top:1.25rem">
                        <a class="btn btn-cian btn-sm" href="#precios"><i class="fa-solid fa-tags" aria-hidden="true"></i> Ver planes y precios</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Precios ===== -->
    <section class="sec" id="precios">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Precios</span>
                <h2 class="bebas">Planes de licenciamiento</h2>
                <p>Elige el plan que mejor se adapte al tamaño y ritmo de tu negocio. Activa tu licencia y pon SiGesEmp a trabajar para ti.</p>
            </div>

            <div class="precios-grid">
                <article class="plan-card reveal" style="--plan-color:#00D4FF">
                    <div class="plan-head">
                        <h3 class="plan-name">Básico</h3>
                        <p class="plan-desc">Para pequeños negocios que quieren ordenar su operación y empezar a trabajar con control.</p>
                    </div>
                    <div class="plan-price">
                        <span class="plan-price-amount">15</span>
                        <span class="plan-price-currency">USD</span>
                        <span class="plan-price-period">/mes</span>
                    </div>
                    <p class="plan-price-cup" data-usd="15">(calculando...)</p>
                    <hr class="plan-divider">
                    <ul class="plan-features">
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>1 módulo principal a elegir</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>1 usuario administrador</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Trabajo local, sin internet</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Respaldo manual de la base de datos</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Soporte por correo y teléfono</span></li>
                    </ul>
                    <a href="#licencia-estado" class="plan-cta plan-cta-secondary"><i class="fa-solid fa-key" aria-hidden="true"></i> Activar licencia</a>
                </article>

                <article class="plan-card reveal" style="--plan-color:#FF6B00">
                    <span class="plan-badge"><i class="fa-solid fa-fire" aria-hidden="true"></i> El más solicitado</span>
                    <div class="plan-head">
                        <h3 class="plan-name">Profesional</h3>
                        <p class="plan-desc">Para negocios en crecimiento que necesitan integrar varios módulos y tener más control.</p>
                    </div>
                    <div class="plan-price">
                        <span class="plan-price-amount">30</span>
                        <span class="plan-price-currency">USD</span>
                        <span class="plan-price-period">/mes</span>
                    </div>
                    <p class="plan-price-cup" data-usd="30">(calculando...)</p>
                    <hr class="plan-divider">
                    <ul class="plan-features">
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Hasta 3 módulos del ecosistema SiGesEmp</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Usuarios y permisos por módulo</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Reportes y cierres operativos</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Respaldo local y exportación manual</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Soporte prioritario</span></li>
                    </ul>
                    <a href="#licencia-estado" class="plan-cta plan-cta-primary"><i class="fa-solid fa-rocket" aria-hidden="true"></i> Empezar ahora</a>
                </article>

                <article class="plan-card is-featured reveal" style="--plan-color:#7949b5">
                    <span class="plan-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Solución Integral</span>
                    <div class="plan-head">
                        <h3 class="plan-name">Empresarial</h3>
                        <p class="plan-desc">Para empresas que necesitan la plataforma completa, acompañamiento y mayor capacidad de operación.</p>
                    </div>
                    <div class="plan-price">
                        <span class="plan-price-amount">50</span>
                        <span class="plan-price-currency">USD</span>
                        <span class="plan-price-period">/mes</span>
                    </div>
                    <p class="plan-price-cup" data-usd="50">(calculando...)</p>
                    <hr class="plan-divider">
                    <ul class="plan-features">
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Todos los módulos de SiGesEmp</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Usuarios y roles según tu operación</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Integración entre módulos y reportes completos</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Asistencia en puesta en marcha</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Soporte técnico y acompañamiento continuo</span></li>
                    </ul>
                    <a href="/desamedida.php" class="plan-cta plan-cta-primary"><i class="fa-solid fa-comments" aria-hidden="true"></i> Solicitar propuesta</a>
                </article>
            </div>

            <div class="precios-trial reveal">
                <p><strong>¿Todavía no decides?</strong>Solicita una demostración o activa tu licencia cuando estés listo. Te orientamos según las necesidades de tu negocio.</p>
                <a href="#licencia-estado" class="btn btn-cian btn-sm"><i class="fa-solid fa-key" aria-hidden="true"></i> Activar / renovar</a>
            </div>

            <div class="precios-links reveal">
                <a href="#licencia-estado" class="precios-link">
                    <i class="fa-solid fa-percent" aria-hidden="true"></i>
                    <span><strong>Paga menos por ciclo</strong>Consulta opciones de pago semestral o anual y elige el plan que mejor te convenga.</span>
                </a>
                <a href="/contacto.php" class="precios-link">
                    <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                    <span><strong>Cómo contratar</strong>Habla con nuestro equipo y recibe orientación sobre el plan, la activación y la puesta en marcha.</span>
                </a>
                <a href="/contacto.php" class="precios-link">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <span><strong>¿Te lo instalan?</strong>Busca un socio técnico acreditado en tu zona, con presupuesto acordado.</span>
                </a>
            </div>
        </div>
    </section>
    <!-- ===== Preguntas frecuentes ===== -->
    <section class="sec" id="preguntas">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Antes de entrar</span>
                <h2 class="bebas">Preguntas frecuentes</h2>
                <p>Respuestas rápidas sobre UnicornioSoftware, SiGesEmp, la licencia, los módulos y cómo empezar.</p>
            </div>

            <div class="faq-lista">
                <?php foreach ($faq as $i => $f): ?>
                <details class="faq reveal"<?= $i === 0 ? ' open' : '' ?>>
                    <summary><span><?= htmlspecialchars($f['p'], ENT_QUOTES, 'UTF-8') ?></span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                    <div class="faq-body"><p><?= $f['r'] ?></p></div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== CTA ===== -->
    <section class="sec">
        <div class="wrap">
            <div class="cta reveal">
                <span class="eyebrow">Empieza ahora</span>
                <h2 class="bebas">Todo el sistema,<br>a un clic desde aquí</h2>
                <p>La portada reúne los accesos a cada módulo, el estado de la licencia y la información de soporte de tu instalación.</p>
                <div class="hero-acciones">
                    <a class="btn btn-fuego" href="<?= $base ?>/index.php">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Ir al Ecosistema Financiero
                    </a>
                    <a class="btn btn-ghost" href="<?= $base ?>/soporte.php">
                        <i class="fa-solid fa-headset" aria-hidden="true"></i> Hablar con soporte
                    </a>
                    <a class="btn btn-ghost" href="#inicio">
                        <i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Regresar al Inicio
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ===== Pie ===== -->
<footer class="pie">
    <div class="wrap">
        <div class="pie-grid">
            <div>
                <div class="pie-marca">
                    <img src="/images/logo.png" alt="">
                    <span class="bebas" style="font-size:1.5rem"><i style="font-style:normal;color:var(--cian)">UNICORNIO</i><i style="font-style:normal;color:var(--fuego)">SOFTWARE</i></span>
                </div>
                <p>Entorno de Aplicaciones Web de UnicornioSoftware&#174; Cuba. Kaky&#174;. Facturación, nóminas, costos e inventarios en tu propia red.</p>
                <p style="margin-top:.6rem;color:var(--gris-2);font-style:italic">&laquo;Donde tu visión toma forma&raquo;</p>
            </div>

            <nav aria-label="Enlaces del pie">
                <h4>Explorar</h4>
                <ul class="pie-lista">
                    <li><a href="<?= $base ?>/index.php"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Ecosistema Financiero</a></li>
                    <li><a href="<?= $base ?>/nominas/"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a></li>
                    <li><a href="<?= $base ?>/facturacion/index.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a></li>
                    <li><a href="<?= $base ?>/costos/index.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a></li>
                    <li><a href="<?= $base ?>/inventarios/index.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a></li>
                    <li><a href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a></li>
                </ul>
            </nav>

            <div>
                <h4>Contacto</h4>
                <p><a href="mailto:kakycu@gmail.com"><i class="fa-solid fa-envelope" aria-hidden="true"></i> kakycu@gmail.com</a></p>
                <p><a href="tel:+5352712861"><i class="fa-solid fa-phone" aria-hidden="true"></i> +53 5 2712861</a></p>
                <p><i class="fa-solid fa-clock" aria-hidden="true"></i> Lun - Vie, 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</p>
                <p><a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Centro de soporte</a></p>
                <p><a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a></p>
            </div>

            <div>
                <h4>Síguenos</h4>
                <div class="pie-social">
                    <a href="https://www.facebook.com/unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                    <a href="https://www.x.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                    <a href="https://www.instagram.com/unicorniosoftcuba" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                    <a href="https://www.youtube.com/@unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                    <a href="https://api.whatsapp.com/send?phone=5352712861" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
                </div>

                <h4 class="pie-legal-title">Legal</h4>
                <p class="pie-legal">
                    <a href="/legal.php#terminos"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                    <a href="/legal.php#privacidad"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                    <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
                </p>
            </div>
        </div>
        <div class="pie-barra">
            <span>© <?= date('Y') ?> UnicornioSoftware® Cuba. Todos los derechos reservados.</span>
            <span>Licencia registrada · datos alojados en este equipo</span>
        </div>
    </div>
</footer>

<!-- Botones flotantes: ir hasta arriba / ir hasta abajo -->
<div class="scroll-fabs">
    <button type="button" class="scroll-fab" id="btnScrollTop"
            title="Ir hasta arriba" aria-label="Ir hasta arriba de la página">
        <i class="fa-solid fa-angles-up" aria-hidden="true"></i>
    </button>
    <button type="button" class="scroll-fab is-visible" id="btnMostrarIntro"
            title="Mostrar animación de inicio" aria-label="Mostrar animación de inicio">
        <i class="fa-solid fa-film" aria-hidden="true"></i>
    </button>
    <button type="button" class="scroll-fab" id="btnScrollBottom"
            title="Ir hasta abajo" aria-label="Ir hasta abajo de la página">
        <i class="fa-solid fa-angles-down" aria-hidden="true"></i>
    </button>
</div>

<?php if (function_exists('licencia_fingerprint_equipo')): ?>
<!-- ===== Activación / renovación de licencia, sin salir de la portada ===== -->
<div class="lic-overlay" id="licencia"<?= $licModalAbierto ? '' : ' hidden' ?>>
    <div class="lic-modal" role="dialog" aria-modal="true" aria-labelledby="licModalTitulo">
        <button type="button" class="lic-modal-x" data-lic-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
            <div>
                <h2 id="licModalTitulo">Activar o renovar licencia</h2>
                <p class="lic-modal-sub"><?= htmlspecialchars($licenciaEstadoTxt, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <?php if ($licOk): ?>
        <div class="lic-ok" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <div>
                <strong>Licencia activada correctamente.</strong>
                <p>Tipo: <b><?= htmlspecialchars($licOkTipo, ENT_QUOTES, 'UTF-8') ?></b> · Vence: <b><?= htmlspecialchars($licOkHasta, ENT_QUOTES, 'UTF-8') ?></b></p>
            </div>
        </div>
        <div class="lic-modal-actions" style="margin-top:1.1rem;">
            <button type="button" class="btn btn-primary" data-lic-close><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cerrar</button>
        </div>
        <?php else: ?>
        <?php if ($licError !== ''): ?>
        <div class="lic-msg lic-msg-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= $licError ?></span>
        </div>
        <?php elseif ($licAviso !== ''): ?>
        <div class="lic-msg <?= $licAvisoTipo === 'ok' ? 'lic-msg-ok' : 'lic-msg-error' ?>" role="status">
            <i class="fa-solid <?= $licAvisoTipo === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
            <span><?= str_replace('«Activar licencia»', '<span class="lic-cta-fade">«Activar licencia»</span>', $licAviso) ?></span>
        </div>
        <?php endif; ?>

        <form class="lic-form" method="post" action="#licencia" autocomplete="off">
            <input type="hidden" name="licencia_accion" value="activar">
            <div class="lic-grid">
                <label class="lic-field">Nombre de Registro
                    <input type="text" name="nombre" value="<?= htmlspecialchars($licForm['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="120" placeholder="Empresa o instalación" required>
                </label>
                <label class="lic-field">Usuario del Registro
                    <input type="text" name="usuario" value="<?= htmlspecialchars($licForm['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="80" placeholder="Administrador responsable" required>
                </label>
            </div>
            <label class="lic-field">Llave Serial
                <input type="text" name="serial" id="licSerial" value="<?= htmlspecialchars($licForm['serial'], ENT_QUOTES, 'UTF-8') ?>"
                       maxlength="29" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" spellcheck="false" required>
            </label>
            <div class="lic-fp">
                <span>Código de este equipo:</span>
                <b><?= htmlspecialchars(licencia_fingerprint_equipo(), ENT_QUOTES, 'UTF-8') ?></b>
                <button type="button" class="btn btn-glass btn-sm" id="licCopiar">
                    <i class="fa-regular fa-copy"></i> Copiar
                </button>
            </div>
            <div class="lic-modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Activar licencia
                </button>
                <button type="button" class="btn btn-glass" data-lic-close>Cancelar</button>
            </div>
        </form>

        <div class="lic-solicitar">
            <button type="button" class="btn btn-glass" data-sol-open>
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar licencia por correo
            </button>
        </div>

        <div class="lic-alt">
            <details>
                <summary><i class="fa-solid fa-file-import" aria-hidden="true"></i> Importar desde un archivo .lic</summary>
                <form method="post" action="#licencia" enctype="multipart/form-data">
                    <input type="hidden" name="licencia_accion" value="examinar_archivo">
                    <input type="file" name="archivo_lic" accept=".lic" required onchange="this.form.submit()">
                </form>
            </details>
            <details>
                <summary><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Pegar el texto de la licencia</summary>
                <form method="post" action="#licencia">
                    <input type="hidden" name="licencia_accion" value="examinar_texto">
                    <textarea name="licencia_texto" rows="3" spellcheck="false"
                              placeholder="Pegue aquí el texto de la licencia..."><?= htmlspecialchars($licForm['texto'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div>
                        <button type="submit" class="btn btn-glass btn-sm">
                            <i class="fa-solid fa-magnifying-glass"></i> Verificar y rellenar
                        </button>
                    </div>
                </form>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Solicitud de licencia por correo, igual que en /servicios/nominas/licencia.php ===== -->
<div class="lic-overlay" id="solLicencia" hidden>
    <div class="lic-modal sol-modal" role="dialog" aria-modal="true" aria-labelledby="solTitulo">
        <button type="button" class="lic-modal-x" data-sol-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
            <div>
                <h2 id="solTitulo">Solicitar licencia por correo</h2>
                <p class="lic-modal-sub">Complete sus datos y el periodo de validez deseado. La licencia se envía al correo electrónico que registre.</p>
            </div>
        </div>

        <p class="sol-intro">
            Se enviará la solicitud con el <b>código de equipo</b> de esta PC, o con el de otro equipo
            si lo escribe con el lápiz, y la licencia será remitida al <b>correo electrónico</b> del solicitante.
        </p>

        <div class="lic-fp">
            <span>Código de este equipo:</span>
            <b><?= htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine()), ENT_QUOTES, 'UTF-8') ?></b>
        </div>

        <div class="lic-msg lic-msg-error" id="solMsg" role="alert" hidden>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span id="solMsgTexto"></span>
        </div>

        <form class="sol-form" id="solForm" autocomplete="off" novalidate>
            <div class="sol-grid">
                <label class="sol-field">Nombre
                    <input type="text" id="solNombre" name="nombre" maxlength="60" placeholder="Nombres">
                </label>
                <label class="sol-field">Apellidos
                    <input type="text" id="solApellidos" name="apellidos" maxlength="60" placeholder="Apellidos">
                </label>
                <label class="sol-field">Carné de identidad
                    <input type="text" id="solCi" name="ci" maxlength="11" inputmode="numeric" placeholder="11 dígitos">
                    <small class="sol-feedback" id="solCiFeedback"></small>
                </label>
                <label class="sol-field">Usuario
                    <input type="text" id="solUsuario" name="usuario" maxlength="60" placeholder="Usuario del sistema">
                </label>
                <label class="sol-field">Entidad
                    <input type="text" id="solEntidad" name="entidad" maxlength="120" placeholder="Empresa o entidad">
                </label>
                <label class="sol-field">Periodo de validez
                    <select id="solPeriodo" name="periodo">
                        <option value="">Seleccione un periodo</option>
                        <?php foreach (licencia_tipos() as $codigoTipo => $infoTipo): ?>
                        <option value="<?= htmlspecialchars($codigoTipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($infoTipo['nombre'] . ' (' . $codigoTipo . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="sol-field">
                    <span>Tipo de licencia</span>
                    <label class="sol-switch" for="solGenerica">
                        <input type="checkbox" id="solGenerica" name="generica" value="1">
                        <span class="sol-switch-track"></span>
                        <span id="solGenericaTxt">Específica</span>
                    </label>
                </div>
                <div class="sol-field">
                    <span>Código de equipo actual</span>
                    <div class="sol-huella-fila">
                        <input type="text" id="solHuellaInput" name="huella" maxlength="29"
                               value="<?= htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine()), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="#####-#####-#####-#####" spellcheck="false" autocapitalize="characters" disabled>
                        <button type="button" class="btn btn-glass btn-sm" id="solEditarHuella"
                                title="Escribir el código de otro equipo" aria-label="Escribir el código de otro equipo">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </button>
                    </div>
                    <small class="sol-feedback" id="solHuellaInputFeedback"></small>
                </div>
                <label class="sol-field">No. soporte WhatsApp
                    <input type="text" id="solWhatsapp" name="whatsapp" maxlength="20" inputmode="numeric"
                           placeholder="5359860773" spellcheck="false" autocomplete="off">
                    <small class="sol-feedback" id="solWhatsappFeedback"></small>
                </label>
                <label class="sol-field">Correo del solicitante
                    <input type="email" id="solEmail" name="email" maxlength="120" placeholder="correo@ejemplo.com" spellcheck="false" autocomplete="email">
                    <small class="sol-feedback" id="solEmailFeedback"></small>
                </label>
                <label class="sol-field sol-full">Email del proveedor de soporte técnico
                    <input type="email" id="solEmailSoporte" name="email_soporte" maxlength="120" placeholder="soporte@ejemplo.com" spellcheck="false" autocomplete="off">
                    <small class="sol-feedback" id="solEmailSoporteFeedback"></small>
                </label>
            </div>
            <div class="sol-actions">
                <button type="button" class="btn btn-primary" id="solEnviar">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar solicitud
                </button>
                <div class="sol-menu">
                    <button type="button" class="btn btn-glass" id="solCorreo" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Enviar con…
                    </button>
                    <div class="sol-menu-lista" id="solEnviarLista" hidden>
                        <button type="button" data-canal="correo"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Enviar con mi correo</button>
                        <button type="button" data-canal="whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Enviar con WhatsApp</button>
                        <button type="button" data-canal="gmail"><i class="fa-brands fa-google" aria-hidden="true"></i> Enviar con Gmail</button>
                    </div>
                </div>
                <button type="button" class="btn btn-glass" data-sol-close>Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Estado de la licencia ===== -->
<div class="lic-overlay" id="estadoLicencia" hidden>
    <div class="lic-modal" role="dialog" aria-modal="true" aria-labelledby="edoLicTitulo">
        <button type="button" class="lic-modal-x" data-estado-lic-close aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="lic-modal-head">
            <span class="lic-modal-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
            <div>
                <h2 id="edoLicTitulo">Estado de la licencia</h2>
                <p class="lic-modal-sub">
                    <span class="lic-estado-badge <?= $licEdoTono ?>">
                        <i class="fa-solid <?= $licEdoIcono ?>" aria-hidden="true"></i> <?= htmlspecialchars($licEdoNombre, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>
            </div>
        </div>

        <dl class="lic-estado-lista">
            <div class="lic-estado-fila">
                <dt>Nombre de registro</dt>
                <dd><?= $licEdoRegistro !== '' ? htmlspecialchars($licEdoRegistro, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Usuario del registro</dt>
                <dd><?= $licEdoUsuario !== '' ? htmlspecialchars($licEdoUsuario, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Tipo de licencia</dt>
                <dd><?= $licEdoTipo !== '' ? htmlspecialchars($licEdoTipo, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Activada el</dt>
                <dd><?= $licEdoActivada !== '' ? htmlspecialchars($licEdoActivada, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Vence</dt>
                <dd><?= $licEdoVence !== '' ? htmlspecialchars($licEdoVence, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Tiempo restante</dt>
                <dd><?= htmlspecialchars($licEdoRestante, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="lic-estado-fila">
                <dt>Código de equipo</dt>
                <dd class="mono"><?= htmlspecialchars(licencia_fingerprint_equipo(), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        </dl>

        <div class="lic-modal-actions" style="margin-top:1.15rem;">
            <button type="button" class="btn btn-primary" data-estado-lic-ir="licencia">
                <i class="fa-solid fa-key" aria-hidden="true"></i> Activar o renovar
            </button>
            <button type="button" class="btn btn-glass" data-estado-lic-ir="solicitud">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar por correo
            </button>
            <button type="button" class="btn btn-glass" data-estado-lic-close><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('licencia');
    if (!overlay) { return; }

    function abrir() {
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
        var campo = overlay.querySelector('input[name="nombre"]');
        if (campo) { window.setTimeout(function () { campo.focus(); }, 40); }
    }
    function cerrar() {
        overlay.hidden = true;
        document.body.classList.remove('lic-abierto');
    }

    var abridores = document.querySelectorAll('[data-lic-open]');
    for (var i = 0; i < abridores.length; i++) {
        abridores[i].addEventListener('click', function (e) { e.preventDefault(); abrir(); });
    }
    var cierres = overlay.querySelectorAll('[data-lic-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrar);
    }
    overlay.addEventListener('mousedown', function (e) {
        // El modal no se cierra al pulsar fuera: solo con la X, Cancelar o Escape.
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { cerrar(); }
    });
    if (!overlay.hidden) { document.body.classList.add('lic-abierto'); }

    // Se expone para el panel «Estado de la licencia».
    window.licAbrir = abrir;

    // La llave serial se agrupa sola en 5 bloques de 5 caracteres.
    var serial = document.getElementById('licSerial');
    if (serial) {
        serial.addEventListener('input', function () {
            var valor = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 25);
            var grupos = valor.match(/.{1,5}/g);
            this.value = grupos ? grupos.join('-') : '';
        });
    }

    var copiar = document.getElementById('licCopiar');
    if (copiar) {
        copiar.addEventListener('click', function () {
            var texto = <?= json_encode(licencia_fingerprint_equipo()) ?>;
            function listo() {
                copiar.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
                window.setTimeout(function () {
                    copiar.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar';
                }, 1600);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(listo);
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = texto;
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); listo(); } catch (err) {}
                document.body.removeChild(tmp);
            }
        });
    }
})();
</script>

<script>
/* Solicitud de licencia por correo desde la portada.
 * Replica el comportamiento de /servicios/nominas/licencia.php: valida los
 * campos en el navegador, envía por el SMTP del sistema al endpoint
 * ?solicitar_licencia=1 y ofrece los canales externos (correo, WhatsApp, Gmail). */
(function () {
    var overlay = document.getElementById('solLicencia');
    if (!overlay) { return; }

    var endpoint  = <?= json_encode($base . '/nominas/licencia.php') ?>;
    var licOverlay = document.getElementById('licencia');
    var btnAbrir  = document.querySelectorAll('[data-sol-open]');
    var btnEnviar = document.getElementById('solEnviar');
    var btnCorreo = document.getElementById('solCorreo');
    var listaEnvio = document.getElementById('solEnviarLista');
    var huellaInput = document.getElementById('solHuellaInput');
    var btnEditar = document.getElementById('solEditarHuella');
    var cajaMsg   = document.getElementById('solMsg');
    var textoMsg  = document.getElementById('solMsgTexto');

    var CAMPOS = [
        { id: 'solNombre',    clave: 'nombre' },
        { id: 'solApellidos', clave: 'apellidos' },
        { id: 'solCi',        clave: 'ci' },
        { id: 'solEmail',     clave: 'email' },
        { id: 'solUsuario',   clave: 'usuario' },
        { id: 'solEntidad',   clave: 'entidad' },
        { id: 'solPeriodo',   clave: 'periodo' }
    ];

    // Correos y WhatsApp tomados de Configuración del sistema. Se piden al abrir
    // el modal para no cargar la base de datos al dibujar la portada.
    var correosConfig = { destino: '', solicitante: '', whatsapp: '', resuelto: false };

    function porId(id) { return document.getElementById(id); }

    function avisar(texto) {
        textoMsg.innerHTML = texto;
        cajaMsg.hidden = false;
        cajaMsg.classList.add('lic-msg-error');
        cajaMsg.classList.remove('lic-msg-ok');
    }
    function celebrar(texto) {
        textoMsg.innerHTML = texto;
        cajaMsg.hidden = false;
        cajaMsg.classList.add('lic-msg-ok');
        cajaMsg.classList.remove('lic-msg-error');
    }
    function limpiarAviso() { cajaMsg.hidden = true; textoMsg.innerHTML = ''; }

    function aplicarCorreosConfig() {
        if (correosConfig.destino) {
            porId('solEmailSoporte').value = correosConfig.destino;
        }
        if (correosConfig.solicitante) {
            porId('solEmail').value = correosConfig.solicitante;
        }
        if (correosConfig.whatsapp) {
            porId('solWhatsapp').value = correosConfig.whatsapp;
        }
    }

    function cargarCorreos() {
        aplicarCorreosConfig();
        if (correosConfig.resuelto) { return; }
        fetch(endpoint + '?solicitar_licencia_destino=1')
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.destino) {
                    correosConfig.destino     = j.destino;
                    correosConfig.solicitante = j.solicitante || '';
                    correosConfig.whatsapp    = j.whatsapp || '';
                    correosConfig.resuelto    = true;
                    aplicarCorreosConfig();
                }
            })
            .catch(function () { /* se conservan los valores escritos */ });
    }

    var licEstabaAbierto = false;
    function abrir() {
        licEstabaAbierto = !!(licOverlay && !licOverlay.hidden);
        if (licEstabaAbierto) { licOverlay.hidden = true; }
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
        limpiarAviso();
        var campo = porId('solNombre');
        if (campo) { window.setTimeout(function () { campo.focus(); }, 40); }
        cargarCorreos();
    }

    function cerrar() {
        overlay.hidden = true;
        limpiarAviso();
        cerrarMenuEnvio();
        if (licEstabaAbierto && licOverlay) {
            licOverlay.hidden = false;
            document.body.classList.add('lic-abierto');
        } else {
            if (!document.querySelector('.lic-overlay:not([hidden])')) {
                document.body.classList.remove('lic-abierto');
            }
        }
    }

    for (var i = 0; i < btnAbrir.length; i++) {
        btnAbrir[i].addEventListener('click', function (e) { e.preventDefault(); abrir(); });
    }
    var cierres = overlay.querySelectorAll('[data-sol-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrar);
    }
    // Sin cierre al pulsar fuera del modal.
    overlay.addEventListener('mousedown', function (e) {
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!listaEnvio.hidden) { cerrarMenuEnvio(); return; }
            if (!overlay.hidden) { cerrar(); }
        }
    });

    // --- Validaciones, con el mismo criterio que el servidor ---
    function validarCI(ci) {
        ci = String(ci).replace(/[\s-]/g, '');
        if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: 'Debe contener 11 dígitos numéricos' };
        var anio = ci.substr(0, 2);
        var mes  = ci.substr(2, 2);
        var dia  = ci.substr(4, 2);
        if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes de nacimiento inválido' };
        var diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var maxDias = diasPorMes[parseInt(mes, 10) - 1];
        if (parseInt(mes, 10) === 2 && parseInt(dia, 10) === 29) {
            var anioLargo = parseInt(anio, 10) < 30 ? 2000 + parseInt(anio, 10) : 1900 + parseInt(anio, 10);
            var bisiesto = (anioLargo % 4 === 0 && anioLargo % 100 !== 0) || (anioLargo % 400 === 0);
            if (!bisiesto) return { valido: false, mensaje: '29/02 solo es válido en años bisiestos' };
        } else if (dia < '01' || parseInt(dia, 10) > maxDias) {
            return { valido: false, mensaje: 'Día de nacimiento inválido' };
        }
        var digitoGenero = parseInt(ci.charAt(9), 10);
        var genero    = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
        var anioTexto = parseInt(anio, 10) < 30 ? '20' + anio : '19' + anio;
        return {
            valido: true,
            mensaje: 'CI válido | ' + genero + ' | Nac: ' + dia + '/' + mes + '/' + anioTexto,
            genero: genero,
            icono: digitoGenero % 2 === 0 ? 'fa-mars' : 'fa-venus'
        };
    }

    var RE_EMAIL  = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
    var RE_HUELLA = /^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/;

    function validarEmail(email) {
        var v = String(email).trim();
        if (v === '') return { valido: false, mensaje: 'Ingrese su correo electrónico' };
        if (v.indexOf('..') !== -1 || v.charAt(0) === '.' || v.charAt(v.length - 1) === '.') {
            return { valido: false, mensaje: 'Formato de correo electrónico inválido' };
        }
        if (!RE_EMAIL.test(v)) return { valido: false, mensaje: 'Formato de correo electrónico inválido' };
        return { valido: true, mensaje: 'Correo electrónico válido' };
    }

    function normalizarWhatsapp(valor) {
        var n = (valor || '').replace(/\D/g, '');
        if (n.indexOf('00') === 0) { n = n.slice(2); }
        if (n.length === 8 && n.slice(0, 2) !== '53') { n = '53' + n; }
        return (n.length >= 8 && n.length <= 15) ? n : '';
    }

    function mostrarFeedback(id, resultado) {
        var caja = porId(id + 'Feedback');
        if (!caja) { return; }
        caja.classList.remove('ok', 'mal');
        if (!resultado) { caja.innerHTML = ''; return; }
        if (resultado.valido) {
            caja.classList.add('ok');
            caja.innerHTML = '<i class="fa-solid fa-circle-check"></i> '
                + (resultado.icono ? '<i class="fa-solid ' + resultado.icono + '"></i> ' : '')
                + resultado.mensaje;
        } else {
            caja.classList.add('mal');
            caja.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + resultado.mensaje;
        }
    }

    function limpiarErrores() {
        for (var k = 0; k < CAMPOS.length; k++) {
            porId(CAMPOS[k].id).classList.remove('error');
            mostrarFeedback(CAMPOS[k].id, null);
        }
        huellaInput.classList.remove('error');
        mostrarFeedback('solHuellaInput', null);
        porId('solWhatsapp').classList.remove('error');
        mostrarFeedback('solWhatsapp', null);
        porId('solEmailSoporte').classList.remove('error');
        mostrarFeedback('solEmailSoporte', null);
        limpiarAviso();
    }

    for (var c = 0; c < CAMPOS.length; c++) {
        porId(CAMPOS[c].id).addEventListener('input', function () { this.classList.remove('error'); });
    }

    porId('solCi').addEventListener('input', function () {
        var digitos = this.value.replace(/\D/g, '').slice(0, 11);
        if (this.value !== digitos) { this.value = digitos; }
        mostrarFeedback('solCi', digitos.length === 11 ? validarCI(digitos) : null);
    });

    porId('solEmail').addEventListener('input', function () {
        var v = this.value.trim();
        mostrarFeedback('solEmail', (v === '' || v.indexOf('@') === -1) ? null : validarEmail(v));
    });

    porId('solEmailSoporte').addEventListener('input', function () {
        this.classList.remove('error');
        mostrarFeedback('solEmailSoporte', null);
    });

    porId('solWhatsapp').addEventListener('input', function () {
        var v = (this.value || '').trim();
        if (v !== '' && normalizarWhatsapp(v) !== '') {
            this.classList.remove('error');
            mostrarFeedback('solWhatsapp', null);
        }
    });

    // --- Tipo de licencia y código de equipo ---
    var chkGenerica = porId('solGenerica');
    var huellaEditando = false;
    var huellaMaquina = huellaInput.value;

    function aplicarEstadoHuella() {
        var generica = !!chkGenerica.checked;
        btnEditar.disabled = generica;
        huellaInput.disabled = generica ? true : !huellaEditando;
        btnEditar.title = generica
            ? 'No se modifica con licencia genérica'
            : (huellaEditando ? 'Restaurar el código de esta PC' : 'Escribir el código de otro equipo');
    }

    function aplicarTipo() {
        porId('solGenericaTxt').textContent = chkGenerica.checked ? 'Genérica' : 'Específica';
        aplicarEstadoHuella();
    }
    chkGenerica.addEventListener('change', aplicarTipo);
    aplicarEstadoHuella();

    huellaInput.addEventListener('input', function () {
        if (this.disabled) { return; }
        var limpio = (this.value.match(/[A-Za-z0-9]/g) || []).join('').toUpperCase().slice(0, 20);
        this.value = limpio.replace(/(.{5})(?=.)/g, '$1-');
    });

    btnEditar.addEventListener('click', function () {
        if (huellaEditando) {
            huellaEditando = false;
            huellaInput.value = huellaMaquina;
        } else {
            huellaEditando = true;
            huellaInput.classList.remove('error');
            mostrarFeedback('solHuellaInput', null);
        }
        aplicarEstadoHuella();
        if (huellaEditando) { huellaInput.focus(); huellaInput.select(); }
    });

    // --- Recolección y envío ---
    function prepararSolicitud() {
        limpiarErrores();

        var valores = {};
        var faltan = [];
        for (var k = 0; k < CAMPOS.length; k++) {
            var campo = porId(CAMPOS[k].id);
            var valor = (campo.value || '').trim();
            valores[CAMPOS[k].clave] = valor;
            if (valor === '') {
                campo.classList.add('error');
                faltan.push(campo);
            }
        }
        if (faltan.length > 0) {
            avisar('Complete todos los campos para enviar la solicitud.');
            faltan[0].focus();
            return null;
        }

        var ci = porId('solCi');
        var resCI = validarCI(ci.value);
        if (!resCI.valido) {
            ci.classList.add('error');
            mostrarFeedback('solCi', resCI);
            avisar('Carné de identidad inválido: ' + resCI.mensaje + '.');
            ci.focus();
            return null;
        }

        var campoWa = porId('solWhatsapp');
        if (campoWa.value.trim() !== '' && normalizarWhatsapp(campoWa.value) === '') {
            campoWa.classList.add('error');
            mostrarFeedback('solWhatsapp', { valido: false, mensaje: 'Número de WhatsApp inválido' });
            avisar('Número de WhatsApp inválido: debe tener entre 8 y 15 dígitos, con el prefijo del país.');
            campoWa.focus();
            return null;
        }

        var mail = porId('solEmail');
        var resMail = validarEmail(mail.value);
        if (!resMail.valido) {
            mail.classList.add('error');
            mostrarFeedback('solEmail', resMail);
            avisar('Correo electrónico inválido: ' + resMail.mensaje + '.');
            mail.focus();
            return null;
        }

        var mailSoporte = porId('solEmailSoporte');
        if (mailSoporte.value.trim() !== '') {
            var resSoporte = validarEmail(mailSoporte.value);
            if (!resSoporte.valido) {
                mailSoporte.classList.add('error');
                mostrarFeedback('solEmailSoporte', resSoporte);
                avisar('Correo del proveedor inválido: ' + resSoporte.mensaje + '.');
                mailSoporte.focus();
                return null;
            }
            mostrarFeedback('solEmailSoporte', resSoporte);
        }

        var huella = (huellaInput.value || '').trim().toUpperCase();
        if (huella !== '' && !RE_HUELLA.test(huella)) {
            huellaInput.classList.add('error');
            mostrarFeedback('solHuellaInput', { valido: false, mensaje: 'Formato de código de equipo inválido' });
            avisar('Código de equipo inválido: debe tener el formato #####-#####-#####-#####.');
            huellaInput.focus();
            return null;
        }

        valores.ci            = ci.value.replace(/\D/g, '');
        valores.email         = mail.value.trim();
        valores.email_soporte = mailSoporte.value.trim();
        valores.generica      = chkGenerica.checked ? '1' : '0';
        valores.huella        = huella;
        return valores;
    }

    btnEnviar.addEventListener('click', function () {
        var datos = prepararSolicitud();
        if (!datos) { return; }

        var fd = new FormData();
        for (var k = 0; k < CAMPOS.length; k++) { fd.append(CAMPOS[k].clave, datos[CAMPOS[k].clave]); }
        fd.append('email_soporte', datos.email_soporte);
        fd.append('huella', datos.huella);
        fd.append('generica', datos.generica);

        var textoBoton = btnEnviar.innerHTML;
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        fetch(endpoint + '?solicitar_licencia=1', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp && resp.ok) {
                    celebrar(resp.mensaje || 'Su solicitud fue enviada.');
                    porId('solForm').reset();
                    huellaInput.value = huellaMaquina;
                    huellaEditando = false;
                    aplicarTipo();
                } else {
                    var errores = (resp && resp.errores) ? resp.errores : {};
                    for (var clave in errores) {
                        if (!Object.prototype.hasOwnProperty.call(errores, clave)) { continue; }
                        for (var m = 0; m < CAMPOS.length; m++) {
                            if (CAMPOS[m].clave === clave) { porId(CAMPOS[m].id).classList.add('error'); }
                        }
                    }
                    if (errores.email_soporte) {
                        porId('solEmailSoporte').classList.add('error');
                        mostrarFeedback('solEmailSoporte', { valido: false, mensaje: errores.email_soporte });
                    }
                    if (errores.huella) {
                        huellaEditando = true;
                        huellaInput.disabled = false;
                        huellaInput.classList.add('error');
                        mostrarFeedback('solHuellaInput', { valido: false, mensaje: errores.huella });
                    }
                    avisar((resp && resp.mensaje) ? resp.mensaje : 'Ocurrió un error inesperado.');
                }
            })
            .catch(function () {
                avisar('No se pudo contactar al servidor. Verifique su conexión e intente nuevamente.');
            })
            .then(function () {
                btnEnviar.disabled = false;
                btnEnviar.innerHTML = textoBoton;
            });
    });

    // --- Canales externos: correo local, WhatsApp y Gmail ---
    function destinoActual() {
        return (porId('solEmailSoporte').value || '').trim()
            || (correosConfig.destino || '').trim();
    }

    function whatsappActual() {
        return normalizarWhatsapp(porId('solWhatsapp').value)
            || normalizarWhatsapp(correosConfig.whatsapp);
    }

    function construirMensaje(d) {
        var sel = porId('solPeriodo');
        var periodo = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : d.periodo;
        var linea = new Array(41).join('=');
        var resCI = validarCI(d.ci);
        var sexo = (resCI && resCI.valido && resCI.genero) ? resCI.genero : '';
        var cuerpo = [
            'SOLICITUD DE LICENCIA SiGesNom',
            linea,
            '',
            'Nombre:             ' + d.nombre,
            'Apellidos:          ' + d.apellidos,
            'Carne de identidad: ' + d.ci,
            'Sexo:                ' + sexo,
            'Correo electronico: ' + d.email,
            'Usuario:            ' + d.usuario,
            'Entidad:            ' + d.entidad,
            'Periodo de validez: ' + periodo,
            'Codigo de equipo:   ' + d.huella,
            'Tipo de Licencia:   ' + (d.generica === '1' ? 'Generica' : 'Especifica')
        ];
        cuerpo.push('', linea);
        return {
            asunto: 'Solicitud de licencia - ' + d.entidad + ' (' + periodo + ')',
            texto: cuerpo.join('\n')
        };
    }

    function abrirEnlace(url) {
        var enlace = document.createElement('a');
        enlace.href = url;
        enlace.target = '_blank';
        enlace.rel = 'noopener noreferrer';
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
    }

    function enviarPor(canal) {
        cerrarMenuEnvio();
        var d = prepararSolicitud();
        if (!d) { return; }
        var m = construirMensaje(d);

        if (canal === 'whatsapp') {
            var numero = whatsappActual();
            if (numero === '') {
                avisar('WhatsApp no está configurado en el sistema.');
                return;
            }
            abrirEnlace('https://api.whatsapp.com/send?phone=' + numero + '&text=' + encodeURIComponent(m.texto));
            return;
        }
        var destino = destinoActual();
        if (destino === '') {
            avisar('No se pudo determinar el correo del proveedor de soporte.');
            return;
        }
        if (canal === 'gmail') {
            abrirEnlace('https://mail.google.com/mail/?view=cm&to=' + encodeURIComponent(destino)
                + '&su=' + encodeURIComponent(m.asunto)
                + '&body=' + encodeURIComponent(m.texto));
            return;
        }
        abrirEnlace('mailto:' + encodeURI(destino)
            + '?subject=' + encodeURIComponent(m.asunto)
            + '&body=' + encodeURIComponent(m.texto));
    }

    function abrirMenuEnvio() {
        listaEnvio.hidden = false;
        btnCorreo.setAttribute('aria-expanded', 'true');
    }
    function cerrarMenuEnvio() {
        if (!listaEnvio || listaEnvio.hidden) { return; }
        listaEnvio.hidden = true;
        btnCorreo.setAttribute('aria-expanded', 'false');
    }

    btnCorreo.addEventListener('click', function (e) {
        e.stopPropagation();
        if (listaEnvio.hidden) { abrirMenuEnvio(); } else { cerrarMenuEnvio(); }
    });
    listaEnvio.addEventListener('click', function (e) {
        var item = e.target.closest('.sol-menu-lista button');
        if (!item) { return; }
        e.preventDefault();
        enviarPor(item.getAttribute('data-canal'));
    });
    document.addEventListener('click', function (e) {
        if (!btnCorreo.parentNode.contains(e.target)) { cerrarMenuEnvio(); }
    });

    // Se expone para el panel «Estado de la licencia».
    window.licSolicitar = abrir;
})();
</script>

<script>
/* Panel «Estado de la licencia»: resume la licencia guardada en el equipo y
 * lleva a los dos trámites que ya existen en la portada. */
(function () {
    var overlay = document.getElementById('estadoLicencia');
    if (!overlay) { return; }

    function abrirEstado() {
        overlay.hidden = false;
        document.body.classList.add('lic-abierto');
    }
    function cerrarEstado() {
        overlay.hidden = true;
        if (!document.querySelector('.lic-overlay:not([hidden])')) {
            document.body.classList.remove('lic-abierto');
        }
    }

    var abridores = document.querySelectorAll('[data-estado-lic-open]');
    for (var i = 0; i < abridores.length; i++) {
        abridores[i].addEventListener('click', function (e) { e.preventDefault(); abrirEstado(); });
    }
    var cierres = overlay.querySelectorAll('[data-estado-lic-close]');
    for (var j = 0; j < cierres.length; j++) {
        cierres[j].addEventListener('click', cerrarEstado);
    }
    // Sin cierre por clic fuera, igual que el resto de modales.
    overlay.addEventListener('mousedown', function (e) {
        if (e.target === overlay) { e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { cerrarEstado(); }
    });

    var irA = overlay.querySelectorAll('[data-estado-lic-ir]');
    for (var k = 0; k < irA.length; k++) {
        irA[k].addEventListener('click', function () {
            var destino = this.getAttribute('data-estado-lic-ir');
            cerrarEstado();
            if (destino === 'solicitud' && typeof window.licSolicitar === 'function') {
                window.licSolicitar();
            } else if (typeof window.licAbrir === 'function') {
                window.licAbrir();
            }
        });
    }
})();
</script>
<?php endif; ?>

<script>
/* Brillos sobre las tarjetas: el resplandor sigue al puntero. */
(function () {
    var tarjetas = document.querySelectorAll('[data-spotlight]');
    function mover(e) {
        var r = this.getBoundingClientRect();
        this.style.setProperty('--pointer-x', (e.clientX - r.left) + 'px');
        this.style.setProperty('--pointer-y', (e.clientY - r.top) + 'px');
    }
    for (var i = 0; i < tarjetas.length; i++) {
        tarjetas[i].addEventListener('pointermove', mover);
    }
})();

/* Intro animada: piezas del logo, destello final y salto con Escape o botón */
(function () {
    var intro = document.getElementById('introAnim');
    if (!intro) { return; }
    var raiz = document.documentElement;
    var barra = document.getElementById('introBar');
    var saltar = document.getElementById('introSkip');
    var noMostrar = document.getElementById('introNoMostrar');
    var replay = document.getElementById('introReplay');
    var capas = intro.querySelectorAll('.intro-layer');
    var capaFinal = intro.querySelector('.intro-final');
    var pasos = capas.length + 1;
    var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var terminado = false;
    var temporizadores = [];

    // Si la página vuelve de tramitar la licencia, no se repite la intro.
    if (intro.classList.contains('is-out') || localStorage.getItem('introOculta') === '1') {
        raiz.classList.remove('intro-lock');
        if (intro.parentNode) { intro.parentNode.removeChild(intro); }
        return;
    }

    function esperar(fn, ms) { temporizadores.push(window.setTimeout(fn, ms)); }
    function pintar(paso) { if (barra) { barra.style.width = Math.round((paso / pasos) * 100) + '%'; } }

    function volarAlLogo() {
        if (!capaFinal || !capaFinal.classList.contains('is-on')) { return; }
        var destino = document.querySelector('.nav .marca img');
        if (!destino) { return; }
        var desde = capaFinal.getBoundingClientRect();
        var hasta = destino.getBoundingClientRect();
        if (!desde.width || !hasta.width) { return; }
        var escala = hasta.width / desde.width;
        var dx = (hasta.left + hasta.width / 2) - (desde.left + desde.width / 2);
        var dy = (hasta.top + hasta.height / 2) - (desde.top + desde.height / 2);
        capaFinal.style.transition = 'transform .65s cubic-bezier(.4, 0, .2, 1)';
        capaFinal.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(' + escala + ')';
    }

    function terminar() {
        if (terminado) { return; }
        terminado = true;
        volarAlLogo();
        for (var i = 0; i < temporizadores.length; i++) { window.clearTimeout(temporizadores[i]); }
        temporizadores = [];
        if (barra) { barra.style.width = '100%'; }
        intro.classList.add('is-out');
        raiz.classList.remove('intro-lock');
        esperar(function () {
            if (intro.parentNode) { intro.parentNode.removeChild(intro); }
        }, 900);
    }

    if (reducido) { terminar(); return; }

    function mostrarFinal() {
        if (terminado) { return; }
        pintar(pasos);
        intro.classList.add('is-final');
        if (capaFinal) { capaFinal.classList.add('is-on'); }
        esperar(function () { if (!terminado) { intro.classList.add('is-sheen'); } }, 1050);
        esperar(terminar, 4100);
    }

    function mostrarCapa(indice) {
        if (terminado) { return; }
        if (indice >= capas.length) { esperar(mostrarFinal, 1000); return; }
        var capa = capas[indice];
        capa.classList.add('is-on');
        pintar(indice + 1);
        esperar(function () { mostrarCapa(indice + 1); }, 820);
    }

    function reiniciar() {
        for (var i = 0; i < temporizadores.length; i++) { window.clearTimeout(temporizadores[i]); }
        temporizadores = [];
        terminado = false;
        intro.classList.remove('is-out', 'is-final', 'is-sheen');
        raiz.classList.add('intro-lock');
        if (barra) { barra.style.width = '0%'; }
        for (var j = 0; j < capas.length; j++) { capas[j].classList.remove('is-on'); }
        if (capaFinal) {
            capaFinal.classList.remove('is-on');
            capaFinal.style.transition = '';
            capaFinal.style.transform = '';
        }
        esperar(terminar, 14000);
        esperar(function () { mostrarCapa(0); }, 220);
    }

    if (saltar) { saltar.addEventListener('click', terminar); }
    if (replay) { replay.addEventListener('click', reiniciar); }
    if (noMostrar) { noMostrar.addEventListener('click', function () { try { localStorage.setItem('introOculta', '1'); } catch (e) {} terminar(); }); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !terminado) { terminar(); }
    });

    esperar(terminar, 14000);
    esperar(function () { mostrarCapa(0); }, 220);
})();

/* Botones flotantes: arriba / abajo según la posición de lectura */
(function () {
    var arriba = document.getElementById('btnScrollTop');
    var abajo = document.getElementById('btnScrollBottom');
    var animar = document.getElementById('btnMostrarIntro');
    var progreso = document.getElementById('scrollProgress');
    if (!arriba && !abajo && !animar) { return; }

    function irArriba() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function irAbajo() {
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        window.scrollTo({ top: max, behavior: 'smooth' });
    }
    if (arriba) { arriba.addEventListener('click', irArriba); }
    if (abajo) { abajo.addEventListener('click', irAbajo); }
    if (animar) {
        animar.addEventListener('click', function () {
            try { localStorage.removeItem('introOculta'); } catch (e) {}
            window.location.reload();
        });
    }

    function actualizar() {
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        if (arriba) { arriba.classList.toggle('is-visible', y > 140); }
        if (abajo) { abajo.classList.toggle('is-visible', y < max - 140); }
        if (progreso) {
            progreso.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%';
        }
    }
    window.addEventListener('scroll', actualizar, { passive: true });
    window.addEventListener('resize', actualizar);
    actualizar();
})();

/* Menú móvil */
(function () {
    var btn = document.getElementById('menuBtn');
    var menu = document.getElementById('menuMovil');
    if (!btn || !menu) { return; }
    btn.addEventListener('click', function () {
        var abierto = menu.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        btn.innerHTML = abierto
            ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'
            : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
    });
    menu.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) {
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        }
    });
})();

/* Rotación automática de la escena y del fondo del hero */
(function () {
    var escena = document.querySelector('.escena');
    if (!escena) { return; }
    var diapos = escena.querySelectorAll('.diapo');
    var pills  = escena.querySelectorAll('[data-slide]');
    if (diapos.length < 2) { return; }
    var capas = [document.getElementById('heroFondoA'), document.getElementById('heroFondoB')];
    /* Mismo par de imágenes para móvil, tablet y PC: cada opción tiene la suya. */
    var fondos = ['/images/nominas-hero.webp', '/images/hero.webp', '/images/costos.webp', '/images/inventarios.webp', '/images/administracion.webp'];
    var actual = 0, capa = 0, timer = null;

    /* Precarga para que el fundido entre sin parpadeos. */
    for (var f = 0; f < fondos.length; f++) { var pre = new Image(); pre.src = fondos[f]; }

    function ponerFondo(destino) {
        if (!capas[0] || !capas[1] || !destino) { return; }
        if (capas[capa].getAttribute('src') === destino) { return; }
        var otra = capas[1 - capa];
        otra.setAttribute('src', destino);
        otra.classList.add('is-on');
        capas[capa].classList.remove('is-on');
        capa = 1 - capa;
    }

    function mostrar(i) {
        actual = ((i % diapos.length) + diapos.length) % diapos.length;
        for (var k = 0; k < diapos.length; k++) { diapos[k].classList.toggle('is-on', k === actual); }
        for (var p = 0; p < pills.length; p++) {
            pills[p].classList.toggle('is-on', parseInt(pills[p].getAttribute('data-slide'), 10) === actual);
        }
        ponerFondo(fondos[actual]);
    }
    function parar() { if (timer) { window.clearInterval(timer); timer = null; } }
    function arrancar() { parar(); timer = window.setInterval(function () { mostrar(actual + 1); }, 7000); }

    for (var j = 0; j < pills.length; j++) {
        pills[j].addEventListener('click', function () {
            mostrar(parseInt(this.getAttribute('data-slide'), 10));
            arrancar();
        });
    }
    mostrar(0);
    arrancar();
})();

/* Desplegables del topbar (servicios y licencia) */
(function () {
    var drops = [].slice.call(document.querySelectorAll('.nav-login, .nav-drop'));
    if (!drops.length) { return; }
    function cerrar(drop) {
        drop.classList.remove('is-open');
        var b = drop.querySelector('button[aria-haspopup]');
        if (b) b.setAttribute('aria-expanded', 'false');
    }
    drops.forEach(function (cont) {
        var btn = cont.querySelector('button[aria-haspopup]');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var scrollTo = btn.getAttribute('data-nav-scroll');
            if (scrollTo) {
                var target = document.querySelector(scrollTo);
                if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                cont.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                drops.forEach(function (d) { if (d !== cont) cerrar(d); });
                return;
            }
            var abierto = cont.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            drops.forEach(function (d) { if (d !== cont) cerrar(d); });
        });
    });
    document.addEventListener('click', function (e) {
        drops.forEach(function (d) { if (!d.contains(e.target)) cerrar(d); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') { drops.forEach(cerrar); }
    });
})();

/* Al abrir o recargar, la portada arranca limpia (sin hash) y arriba del todo.
   Va antes de la lógica de animación por scroll para que el primer repaso se
   haga con la página ya en su posición inicial. */
(function () {
    if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
    if (window.location.hash) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    window.scrollTo(0, 0);
})();

/* Aparición al hacer scroll */
(function () {
    // Los grupos numerosos entran en cadena: cada hijo lleva su propio retardo.
    var grupos = [
        '.grid > .card', '.pasos > .paso', '.serv-grid > .serv',
        '.faq-lista > .faq', '.chips > span', '.pilares > .pilar',
        '.duo > *', '.lic-grid > .panel', '.compromiso', '.proceso-grid > .proc-paso'
    ];
    for (var g = 0; g < grupos.length; g++) {
        var hijos = document.querySelectorAll(grupos[g]);
        var contador = new Map();
        for (var k = 0; k < hijos.length; k++) {
            var hijo = hijos[k];
            var padre = hijo.parentNode;
            var n = contador.get(padre) || 0;
            contador.set(padre, n + 1);
            if (!hijo.classList.contains('reveal')) { hijo.classList.add('reveal'); }
            hijo.style.setProperty('--d', Math.min(n * 90, 360) + 'ms');
        }
    }

    // Aparición al ir pasando: se mide la posición real en cada scroll (con
    // requestAnimationFrame). Es más fiable que un IntersectionObserver aquí y
    // garantiza que el bloque entre justo cuando asoma por el borde inferior.
    var pendientes = [].slice.call(document.querySelectorAll('.reveal, .sec'));
    if (!pendientes.length) { return; }
    var ticking = false;

    function enPantalla(el) {
        var r = el.getBoundingClientRect();
        var alto = window.innerHeight || document.documentElement.clientHeight;
        return r.top < alto * 0.9 && r.bottom > 0;
    }

    function revisar() {
        ticking = false;
        var resto = [];
        for (var i = 0; i < pendientes.length; i++) {
            if (enPantalla(pendientes[i])) { pendientes[i].classList.add('is-in'); }
            else { resto.push(pendientes[i]); }
        }
        pendientes = resto;
        if (!pendientes.length) {
            window.removeEventListener('scroll', pedir);
            window.removeEventListener('resize', pedir);
        }
    }

    function pedir() {
        if (!ticking) { ticking = true; window.requestAnimationFrame(revisar); }
    }

    var iniciado = false;
    function iniciar() {
        if (iniciado) { return; }
        iniciado = true;
        window.addEventListener('scroll', pedir, { passive: true });
        window.addEventListener('resize', pedir);
        window.addEventListener('orientationchange', pedir);
        // Se revisa cuando la página ya tiene su altura definitiva (imágenes y
        // fuentes cargadas); si no, se revelarían de golpe secciones que aún no
        // se han visto y al llegar a ellas ya estarían visibles, sin animación.
        revisar();
        window.setTimeout(revisar, 600);
        window.setTimeout(revisar, 1800);
    }

    if (document.readyState === 'complete') {
        iniciar();
    } else {
        window.addEventListener('load', iniciar);
        // Respaldo: si «load» tarda demasiado (una imagen externa lenta), se
        // arranca igualmente para que la portada no quede en blanco.
        window.setTimeout(iniciar, 3500);
    }
})();
</script>
<script>
(() => {
  const els = document.querySelectorAll('.rv-ap, .rv-ap-hijos');
  if (!els.length) return;
  const show = (el) => el.classList.add('rv-visto');
  const check = () => els.forEach(el => { if (el.classList.contains('rv-visto')) return; const r = el.getBoundingClientRect(); if (r.top < window.innerHeight + 40 && r.bottom > -40) show(el); });
  if (!('IntersectionObserver' in window)) { els.forEach(show); return; }
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { show(e.target); io.unobserve(e.target); } });
  }, { threshold: 0.1 });
  els.forEach(el => io.observe(el));
  check();
  window.addEventListener('scroll', check, { passive: true });
  window.addEventListener('load', check);
})();
</script>
<script>
(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('#btnRefreshPlataforma');
    if(!btn) return;
    if(btn.classList.contains('is-spinning')) return;
    var sec = document.getElementById('plataforma');
    if(!sec) return;
    btn.classList.add('is-spinning');
    fetch(window.location.pathname + window.location.search, {cache:'no-store', credentials:'same-origin'})
      .then(function(r){ if(!r.ok) throw new Error('http'); return r.text(); })
      .then(function(html){
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById('plataforma');
        if(!fresh) throw new Error('no-sec');
        sec.innerHTML = fresh.innerHTML;
        sec.querySelectorAll('.reveal').forEach(function(el){ el.classList.add('is-in'); });
        var nb = document.getElementById('btnRefreshPlataforma');
        if(nb) nb.classList.add('is-spinning');
      })
      .catch(function(){ /* silencio: se mantiene contenido actual */ })
      .finally(function(){
        var b = document.getElementById('btnRefreshPlataforma');
        if(b) b.classList.remove('is-spinning');
      });
  });
})();
<script>
/* Comprobación de versión APK (web y APK embebida) */
(function () {
    var section = document.getElementById("apk-download");
    var info = document.getElementById("apkVersionInfo");
    var status = document.getElementById("apkUpdateStatus");
    var btn = document.getElementById("apkDownloadBtn");
    if (!section || !info || !status || !btn) return;
    var urls = [section.getAttribute("data-apk-version-url"), section.getAttribute("data-apk-version-fallback")].filter(Boolean);
    function getInstalled() {
        try {
            if (window.AndroidApp && typeof window.AndroidApp.getAppVersion === "function") {
                var raw = window.AndroidApp.getAppVersion();
                var obj = typeof raw === "string" ? JSON.parse(raw) : raw;
                return { versionCode: parseInt(obj.versionCode, 10) || 0, versionName: String(obj.versionName || "") };
            }
        } catch (e) {}
        return null;
    }
    function setStatus(text, cls) { status.textContent = text || ""; status.className = "apk-download__status" + (cls ? " " + cls : ""); }
    function fetchJson(url, ms) {
        ms = ms || 6000;
        if (window.AbortController) {
            var ctrl = new AbortController();
            var timer = setTimeout(function () { ctrl.abort(); }, ms);
            return fetch(url, { cache: "no-store", headers: { "Accept": "application/json" }, signal: ctrl.signal })
                .then(function (r) { clearTimeout(timer); if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
                .catch(function (e) { clearTimeout(timer); throw e; });
        }
        return Promise.race([
            fetch(url, { cache: "no-store", headers: { "Accept": "application/json" } }).then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); }),
            new Promise(function (_, rej) { setTimeout(function () { rej(new Error("timeout")); }, ms); })
        ]);
    }
    var safety = setTimeout(function () {
        if (info.textContent.indexOf("Comprobando") !== -1) {
            info.textContent = "No se pudo comprobar la versión más reciente.";
            setStatus("Puedes descargar la APK desde el botón.", "");
        }
    }, 9000);
    function load(i) {
        if (i >= urls.length) {
            clearTimeout(safety);
            info.textContent = "No se pudo comprobar la versión más reciente.";
            setStatus("Puedes descargar la APK desde el botón.", "");
            return;
        }
        fetchJson(urls[i], 6000).then(function (data) {
            clearTimeout(safety);
            var latestCode = parseInt(data.versionCode, 10) || 0;
            var latestName = String(data.versionName || "");
            var apkUrl = String(data.apkUrl || btn.href);
            var changelog = String(data.changelog || "");
            btn.href = apkUrl;
            info.textContent = "Versión actual en servidor: v" + (latestName || latestCode) + " (código " + latestCode + ")";
            var installed = getInstalled();
            if (!installed) { setStatus("Si ya tienes la app instalada, ábrela para comprobar si necesitas actualizar.", ""); return; }
            info.textContent = "Instalada: v" + installed.versionName + " · Última: v" + (latestName || latestCode);
            if (latestCode > installed.versionCode) {
                setStatus("Hay una actualización disponible. " + (changelog ? changelog : ""), "is-update");
                btn.innerHTML = "<i class=\"fa-brands fa-android\" aria-hidden=\"true\"></i> Descargar actualización";
            } else {
                setStatus("Tu app está actualizada.", "is-ok");
                btn.innerHTML = "<i class=\"fa-brands fa-android\" aria-hidden=\"true\"></i> Descargar APK";
            }
        }).catch(function () { load(i + 1); });
    }
    load(0);
})();
</script></script>
<script>
/* Tasa USD->CUP dinámica (inspirado en cyberforja tasa.js/product-page.js) */
(function () {
    var KEY = 'cf_tasa_usd_cup';
    var REFRESH_MS = 6 * 60 * 60 * 1000;
    var FALLBACK = 960;
    function fmt(usd, rate) {
        var cup = Math.round(usd * rate);
        var n = cup.toLocaleString('es-ES');
        return '(Aprox. ' + n + ' CUP a la tasa del día)';
    }
    function apply(rate) {
        document.querySelectorAll('.plan-price-cup[data-usd]').forEach(function (el) {
            var usd = parseFloat(el.getAttribute('data-usd'));
            if (!isFinite(usd)) return;
            el.textContent = fmt(usd, rate);
        });
    }
    function readCache() {
        try {
            var raw = localStorage.getItem(KEY);
            if (!raw) return null;
            var obj = JSON.parse(raw);
            if (!obj || typeof obj.rate !== 'number') return null;
            return obj;
        } catch (e) { return null; }
    }
    function writeCache(rate) {
        try { localStorage.setItem(KEY, JSON.stringify({ rate: rate, ts: Date.now() })); } catch (e) {}
    }
    function parseRate(json) {
        var rate = json && json.success && json.data && json.data.rate;
        if (typeof rate !== 'number' || !isFinite(rate) || rate <= 0) throw new Error('bad');
        return rate;
    }
    function fetchRate() {
        return fetch('/api/exchange-rate.php', { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
            .then(parseRate)
            .catch(function () {
                return fetch('https://cyberforja.com/api/exchange-rate', { credentials: 'omit' })
                    .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
                    .then(parseRate);
            });
    }
    var cached = readCache();
    if (cached && typeof cached.rate === 'number') apply(cached.rate);
    var fresh = cached && (Date.now() - (cached.ts || 0) < REFRESH_MS);
    if (fresh) return;
    fetchRate().then(function (rate) {
        writeCache(rate);
        apply(rate);
    }).catch(function () {
        if (!cached) apply(FALLBACK);
    });
})();
</script>
</body>
</html>
