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
        'url'      => $base . '/costos/login.php',
        'etiqueta' => 'Módulo de costos',
    ],
    [
        'titulo'   => 'Inventarios',
        'texto'    => 'Control de stock, entradas y salidas, alertas de inventario mínimo y gestión de proveedores.',
        'icono'    => 'fa-solid fa-boxes-stacked',
        'url'      => $base . '/inventarios/login.php',
        'etiqueta' => 'Módulo de inventarios',
    ],
    [
        'titulo'   => 'Administración',
        'texto'    => 'Usuarios, roles, parámetros globales, auditoría y configuración del sistema en un solo panel.',
        'icono'    => 'fa-solid fa-user-shield',
        'url'      => $base . '/administracion/index.php',
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
<link rel="stylesheet" href="<?= $base ?>/css/font-awesome6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/fonts/fuentes.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/portada-licencia.css">
<style>
    /* ===== Tokens del tema cyberforja ===== */
    :root {
        --abismo:  #0a0f1a;
        --panel:   #0b0e14;
        --tarjeta: #090e18;
        --acero:   #1e293b;
        --cian:    #00d4ff;
        --fuego:   #ff6b00;
        --fuego-h: #ff8a33;
        --reven:   #8b5cf6;
        --txt:     #ffffff;
        --gris:    #d1d5db;
        --gris-2:  #9ca3af;
        --gris-3:  #6b7280;
        --nav-h:   5rem;
        --wrap:    80rem;
        --radio:   .5rem;
        /* Tokens que consume el panel de licencia (portada-licencia.css) */
        --muted:   #93a4bd;
        --border:  #1e293b;
        --border-strong: #2b3b55;
        --tile:    rgba(255, 255, 255, .04);
        --mint:    #22c55e;
        --cyan:    #00d4ff;
        --accent:  #ff6b00;
        --accent-soft: rgba(255, 107, 0, .14);
        --surface: #0d1422;
        --surface-solid: #0d1422;
        --surface-strong: #131b2c;
        --card-hover-border: #2b3b55;
        --danger:  #ef4444;
        --danger-txt: #fca5a5;
        --danger-soft: rgba(239, 68, 68, .12);
        --danger-border: rgba(239, 68, 68, .38);
        --warn:    #f59e0b;
        --radius-lg: 1rem;
        --shadow:  0 24px 60px -30px #000;
        --shadow-glow: 0 0 0 1px rgba(0, 212, 255, .18);
    }
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; scroll-padding-top: calc(var(--nav-h) + 1rem); }
    body {
        margin: 0;
        background: var(--abismo);
        color: var(--txt);
        font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
        font-size: 16px; line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }
    ::selection { background: var(--fuego); color: #fff; }
    img { max-width: 100%; display: block; }
    a { color: inherit; text-decoration: none; }
    h1, h2, h3, h4 { margin: 0; }
    p { margin: 0; }
    .wrap { width: 100%; max-width: var(--wrap); margin-inline: auto; padding-inline: clamp(1.25rem, 4vw, 3rem); }

    /* ===== Tipografías ===== */
    .bebas { font-family: "Bebas Neue", "Bebas Neue Fallback", Impact, sans-serif; font-weight: 400; letter-spacing: .02em; line-height: .92; }
    .exo   { font-family: "Exo 2", "Inter", system-ui, sans-serif; }

    /* ===== Botones ===== */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .85rem 1.5rem; border-radius: 2px;
        font-family: "Exo 2", "Inter", system-ui, sans-serif;
        font-size: .8rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .09em;
        border: 1px solid transparent; cursor: pointer;
        transition: transform .2s, background .2s, border-color .2s, color .2s, box-shadow .2s;
        white-space: nowrap;
    }
    .btn:hover { transform: translateY(-2px); }
    .btn-fuego {
        background: var(--fuego); color: var(--abismo);
        box-shadow: 0 8px 24px rgba(255, 107, 0, .22);
    }
    .btn-fuego:hover { background: var(--fuego-h); box-shadow: 0 10px 28px rgba(255, 107, 0, .35); }
    .btn-cian {
        background: rgba(0, 212, 255, .1); color: var(--cian);
        border-color: rgba(0, 212, 255, .4);
    }
    .btn-cian:hover { background: var(--cian); color: var(--abismo); border-color: var(--cian); }
    .btn-ghost { background: transparent; color: var(--gris); border-color: var(--acero); }
    .btn-ghost:hover { color: var(--txt); border-color: var(--fuego); }
    .btn-sm { padding: .6rem 1rem; font-size: .72rem; }
    /* Alias que usa el panel de licencia compartido */
    .btn-primary {
        background: linear-gradient(135deg, var(--fuego), #e35f00);
        color: #170a02; border: 1px solid transparent;
        font-weight: 800;
    }
    .btn-primary:hover { background: linear-gradient(135deg, var(--fuego-h), var(--fuego)); color: #170a02; }
    .btn-glass {
        background: rgba(255, 255, 255, .04);
        color: var(--txt);
        border: 1px solid var(--acero);
    }
    .btn-glass:hover { background: rgba(255, 255, 255, .09); border-color: rgba(0, 212, 255, .45); }

    /* ===== Navegación ===== */
    .nav {
        position: fixed; inset: 0 0 auto 0; z-index: 50;
        height: var(--nav-h);
        background: rgba(10, 15, 26, .82);
        backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--acero);
    }
    .nav-in { display: flex; align-items: center; justify-content: space-between; gap: 1rem; height: var(--nav-h); }
    .marca { display: inline-flex; align-items: center; gap: .7rem; }
    .marca img { width: 2.5rem; height: 2.5rem; object-fit: contain; }
    .marca-txt { display: grid; line-height: 1; }
    .marca-txt b {
        font-family: "Bebas Neue", Impact, sans-serif; font-weight: 400;
        font-size: 1.5rem; letter-spacing: .06em;
    }
    .marca-txt b i { font-style: normal; color: var(--cian); }
    .marca-txt b em { font-style: normal; color: var(--fuego); }
    .marca-txt small {
        margin-top: .2rem; font-size: .58rem; letter-spacing: .22em;
        text-transform: uppercase; color: var(--gris-3); font-weight: 600;
    }
    .nav-links { display: none; align-items: center; gap: 1.4rem; }
    .nav-links a {
        font-family: "Exo 2", sans-serif; font-size: .84rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .08em; color: var(--gris-2);
        transition: color .2s;
    }
    .nav-links a:hover, .nav-links a:focus-visible { color: var(--txt); }
    .nav-links a i { margin-right: .38rem; color: var(--gris-3); font-size: 1rem; transition: color .2s; }
    .nav-links a:hover i, .nav-links a:focus-visible i { color: var(--cian); }
    .nav-acc { display: flex; align-items: center; gap: .6rem; }
    .nav-acc .btn { display: none; }
    .menu-btn {
        display: inline-grid; place-items: center;
        width: 2.75rem; height: 2.75rem;
        border: 1px solid var(--acero); border-radius: 2px;
        background: transparent; color: var(--gris-2); font-size: 1.1rem; cursor: pointer;
    }
    .menu-btn:hover { color: var(--txt); border-color: var(--fuego); }
    .menu-movil { display: none; border-top: 1px solid var(--acero); background: var(--abismo); }
    .menu-movil.is-open { display: block; max-height: calc(100dvh - var(--nav-h, 70px)); overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
    .menu-movil a {
        display: block; padding: .9rem 1.5rem;
        font-family: "Exo 2", sans-serif; font-size: .8rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .08em; color: var(--gris-2);
        border-bottom: 1px solid rgba(30, 41, 59, .6);
    }
    .menu-movil a:hover { color: var(--txt); }
    .menu-movil a i { width: 1.1rem; margin-right: .55rem; color: var(--gris-3); }
    .menu-movil a:hover i { color: var(--cian); }
    .menu-movil-titulo {
        display: block; padding: .9rem 1.5rem .4rem;
        font-family: "Exo 2", sans-serif; font-size: .62rem; font-weight: 800;
        letter-spacing: .18em; text-transform: uppercase; color: var(--gris-3);
    }

    /* ===== Acceso a los servicios (desplegable del topbar) ===== */
    .nav-login { position: relative; display: none; }
    .nav-login-caret { font-size: .62em; opacity: .75; transition: transform .2s; }
    .nav-login.is-open .nav-login-caret { transform: rotate(180deg); }
    .nav-login-menu {
        position: absolute; right: 0; top: calc(100% + .6rem);
        display: grid; gap: .1rem;
        min-width: 15.5rem; padding: .45rem;
        background: #0b111d;
        border: 1px solid var(--acero);
        border-radius: .6rem;
        box-shadow: 0 28px 56px -22px rgba(0, 0, 0, .95), 0 0 0 1px rgba(0, 212, 255, .08);
        opacity: 0; visibility: hidden; transform: translateY(-6px);
        transition: opacity .18s ease, transform .18s ease, visibility .18s;
        z-index: 60;
    }
    .nav-login.is-open .nav-login-menu { opacity: 1; visibility: visible; transform: none; }
    .nav-login-menu a {
        display: flex; align-items: center; gap: .6rem;
        padding: .6rem .7rem; border-radius: .4rem;
        font-family: "Exo 2", sans-serif; font-size: .74rem; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; color: var(--gris-2);
        border-bottom: 0;
        transition: background .18s, color .18s;
    }
    .nav-login-menu a i { width: 1.05rem; text-align: center; color: var(--cian); font-size: .84rem; }
    .nav-login-menu a:hover, .nav-login-menu a:focus-visible {
        background: rgba(0, 212, 255, .1); color: var(--txt);
    }
    @media (min-width: 900px) {
        /* Nueve enlaces: primero compactos y sin iconos, luego con más aire. */
        .nav-links { display: flex; gap: .8rem; }
        .nav-links a { font-size: .7rem; letter-spacing: .05em; white-space: nowrap; }
        .nav-links a i { display: none; }
        .nav-acc .btn { display: inline-flex; }
        .nav-login { display: block; }
        .menu-btn { display: none; }
        .menu-movil { display: none !important; }
    }
    @media (min-width: 1180px) {
        .nav-links { gap: .7rem; }
        .nav-links a { font-size: .72rem; letter-spacing: .04em; }
        .nav-links a i { display: inline-block; margin-right: .24rem; font-size: .95rem; }
    }
    @media (min-width: 1400px) {
        .nav-links { gap: .85rem; }
        .nav-links a { font-size: .76rem; }
    }

    /* ===== Hero ===== */
    .hero {
        position: relative;
        display: flex; align-items: flex-start;
        min-height: auto;
        padding-top: var(--nav-h);
        padding-bottom: 2rem;
        overflow: visible;
    }
    .hero-fondo { position: absolute; inset: 0; z-index: 0; }
    .hero-fondo img {
        position: absolute; inset: 0;
        width: 100%; height: 100%; object-fit: cover;
        opacity: 0; transition: opacity .9s ease;
    }
    .hero-fondo img.is-on { opacity: 1; }
    .hero-velo {
        position: absolute; inset: 0; z-index: 1;
        background:
            linear-gradient(90deg, rgba(5, 8, 16, .96) 0%, rgba(5, 8, 16, .82) 44%, rgba(5, 8, 16, .58) 100%),
            radial-gradient(circle at left, rgba(0, 212, 255, .14) 0%, transparent 55%);
    }
    .hero-grid {
        position: relative; z-index: 2;
        display: grid; gap: clamp(2rem, 4vw, 3rem);
        grid-template-columns: minmax(0, 1fr);
        align-items: start;
        padding-block: 9.25rem 0.5rem;
    }
    .hero-copy { max-width: 34rem; margin-top: 0; }
    .hero-copy > :first-child { margin-top: 0; }
    .eyebrow { margin-top: 0;
        display: inline-block;
        font-family: "Exo 2", sans-serif; font-size: .76rem; font-weight: 800;
        letter-spacing: .2em; text-transform: uppercase; color: var(--cian);
        text-shadow: 0 2px 12px rgba(0, 0, 0, .9);
    }
    .eyebrow span { color: var(--gris-3); font-weight: 600; }
    .hero h1 {
        margin: .7rem 0 .9rem;
        font-size: clamp(2.4rem, 5.4vw, 3.9rem);
        max-width: 36rem;
        color: #fff;
        text-shadow: 0 4px 24px rgba(0, 0, 0, .9);
    }
    .hero h1 span { color: var(--cian); filter: drop-shadow(0 0 16px rgba(0, 212, 255, .45)); }
    .hero-bajada {
        max-width: 38rem; color: var(--gris); font-size: 1.05rem; line-height: 1.7;
        text-shadow: 0 2px 12px rgba(0, 0, 0, .85);
    }
    .hero-acciones { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; margin-top: 1.8rem; }
    .hero-acciones .btn { flex: 0 0 auto; }
    .hero-scroll { position:absolute; left:50%; bottom:1.5rem; z-index:5; color:rgba(255,255,255,.55); font-size:1.6rem; line-height:1; text-decoration:none; animation:hero-flecha 2.4s cubic-bezier(.45,0,.55,1) infinite; transition:color .2s; }
    .hero-scroll:hover, .hero-scroll:focus-visible { color:var(--cian); }
    @keyframes hero-flecha { 0%,100%{transform:translateX(-50%) translateY(0);opacity:.55} 50%{transform:translateX(-50%) translateY(8px);opacity:1} }
    .tags {
        display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem;
        margin-top: 1.9rem;
        font-family: "Exo 2", sans-serif; font-size: .72rem; font-weight: 700;
        letter-spacing: .1em; text-transform: uppercase; color: var(--gris-2);
    }
    .tags span { display: inline-flex; align-items: center; gap: .45rem; }
    .tags i { color: var(--cian); font-size: .7rem; }
    .tags .sep { color: rgba(255, 255, 255, .18); }

    /* Escena: monitor en perspectiva + móvil + ficha */
    /* ===== Strip beneficios (mismo estilo/animación que Reven) ===== */
    .reven-feats-strip{background:var(--abismo);border-bottom:1px solid rgba(255,255,255,.04)}
    .reven-feats-grid{max-width:80rem;margin:0 auto;padding:0 1rem;display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:1rem}
    @media(min-width:640px){.reven-feats-grid{padding:0 1.5rem;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem}}
    @media(min-width:1024px){.reven-feats-grid{padding:0 2rem;grid-template-columns:repeat(4,minmax(0,1fr))}}
    .reven-feat{display:flex;align-items:center;gap:.75rem;padding:1.25rem .5rem}
    @media(min-width:640px){.reven-feat{padding:1.25rem 1rem}}
    .reven-feat-icon{width:2.5rem;height:2.5rem;flex-shrink:0;border-radius:.5rem;display:flex;align-items:center;justify-content:center;background:rgba(121,73,181,.12);border:1px solid rgba(121,73,181,.25);color:#7949B5;font-size:1rem}
    .reven-feat-copy{margin:0;line-height:1.2}
    .reven-feat-title{display:block;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.875rem;text-transform:uppercase;letter-spacing:.025em;color:#fff}
    .reven-feat-desc{display:block;font-size:.75rem;color:#9ca3af;margin-top:.15rem}
    @media(min-width:640px){.reven-feat-desc{font-size:.875rem}}
    @keyframes rv-sube{0%{opacity:0;translate:0 22px}100%{opacity:1;translate:0}}
    @keyframes rv-funde{0%{opacity:0}100%{opacity:1}}
    .rv-mov .rv-ap{opacity:0}
    .rv-mov .rv-ap.rv-visto{opacity:1;animation:rv-sube .7s cubic-bezier(.16,1,.3,1) backwards;animation-delay:calc(var(--rv-i,0)*70ms)}
    @media (prefers-reduced-motion:reduce){
      .rv-mov .rv-ap.rv-visto{animation:rv-funde .25s ease backwards}
    }

    /* ===== Métricas / stats ===== */
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;max-width:80rem;margin:0 auto;padding:1.25rem 1rem 0}
    .metric{display:flex;align-items:center;gap:.75rem;padding:1rem 1rem;border-radius:.85rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .metric i{width:2.25rem;height:2.25rem;display:inline-flex;align-items:center;justify-content:center;border-radius:.6rem;background:rgba(0,212,255,.08);color:var(--cian);font-size:.95rem;flex-shrink:0}
    .metric b{display:block;font-family:'Exo 2',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);line-height:1.1}
    .metric span{display:block;font-size:.75rem;color:var(--gris-3);margin-top:.15rem;line-height:1.3}
    @media(max-width:900px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.metrics{grid-template-columns:1fr}}
    .db-health{margin-top:1.5rem;padding:1.25rem;border-radius:1rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .db-health-head{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;font-family:'Exo 2',sans-serif;font-weight:700;color:var(--txt);font-size:.95rem}
    .db-health-head i{color:#ff6b6b}
    .db-health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
    .db-health-item{padding:1rem;border-radius:.85rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);text-align:center}
    .db-health-item i{font-size:1.4rem;margin-bottom:.45rem;display:block}
    .db-health-item b{display:block;font-family:'Exo 2',sans-serif;font-size:1.15rem;font-weight:800;color:var(--txt);line-height:1.1}
    .db-health-item span{display:block;font-size:.74rem;color:var(--gris-3);margin-top:.2rem}
    @media(max-width:640px){.db-health-grid{grid-template-columns:1fr}}
    .integraciones-block{margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,.08)}
    .integraciones-block h3{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;text-align:center;margin:0 0 .5rem}
    .integraciones-block > p{text-align:center;color:#6b7280;font-size:.75rem;line-height:1.5;margin:0 0 2rem;font-family:'Inter',sans-serif}
    .integraciones-grid{display:flex;flex-wrap:nowrap;justify-content:space-between;gap:1rem;overflow-x:auto;padding-bottom:.25rem}
    .integracion-item{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;color:var(--gris-3);transition:color .25s ease}
    .integracion-item i{font-size:1.7rem;line-height:1}
    .integracion-item .int-name{font-family:'Inter',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:inherit;white-space:nowrap}
    .integracion-item .int-desc{font-family:'Inter',sans-serif;font-size:.68rem;color:#6b7280;line-height:1.3}
    .integracion-item:hover{color:#fff}
    .integracion-item.int-wa:hover{color:#25D366}
    .integracion-item.int-cian:hover{color:var(--cian)}
    .integracion-item.int-fuego:hover{color:var(--fuego)}
    .integracion-item.int-drive:hover{color:var(--fuego)}
    .integracion-item.int-excel:hover{color:#21A366}
    @media(max-width:900px){.integraciones-grid{justify-content:flex-start}.integracion-item{flex:0 0 120px}}
    .escena {
        position: relative; justify-self: center;
        width: min(100%, 32rem);
        transform-style: preserve-3d;
    }
    .escena::before {
        content: ""; position: absolute; inset: -10% -8%; z-index: -1;
        background: radial-gradient(ellipse 60% 50% at 55% 50%, rgba(var(--brillo, 139, 92, 246), .22), transparent 72%);
        pointer-events: none;
    }
    .escena-nav { display: flex; flex-wrap: nowrap; justify-content: flex-end; gap: .35rem; margin-bottom: .75rem; overflow-x: auto; }
    .pill {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .26rem .55rem; border-radius: 999px; white-space: nowrap;
        border: 1px solid rgba(255,255,255,.14);
        background: rgba(255,255,255,.04);
        color: var(--gris-3);
        font-family: "Exo 2", sans-serif; font-size: .6rem; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; cursor: pointer;
        transition: all .2s;
    }
    .pill .dot { width: .35rem; height: .35rem; border-radius: 50%; background: rgb(var(--brillo, 139, 92, 246)); }
    .pill.is-on { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.28); color: #fff; }
    .diapo { --brillo: 139, 92, 246; transition: opacity .45s ease, visibility .45s ease; perspective: 1500px; perspective-origin: 50% 50%; transform-style: preserve-3d; }
    .diapo:not(.is-on) { position: absolute; top: 0; left: 0; width: 100%; opacity: 0; visibility: hidden; pointer-events: none; }
    .diapo.is-on { position: relative; opacity: 1; visibility: visible; }
    .pantalla {
        position: relative; z-index: 10;
        padding: 6px; border-radius: 14px;
        background: #111827;
        border: 1px solid rgba(255,255,255,.12);
        transform: rotateX(0deg) rotateY(-10deg) rotateZ(0deg);
        transform-style: preserve-3d;
        box-shadow:
            0 0 0 1px rgba(var(--brillo), .18),
            0 20px 50px -18px rgba(0,0,0,.75),
            0 0 24px -8px rgba(var(--brillo), .2);
    }
    .pantalla img { width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover; object-position: top; border-radius: 9px; display: block; }
    .pantalla::after { content: ""; position: absolute; left: 8%; right: 8%; bottom: -10%; height: 10%; background: radial-gradient(closest-side, rgba(var(--brillo), .28), transparent); pointer-events: none; }
    .movil {
        position: absolute; right: 2%; bottom: -8%; width: 18%; aspect-ratio: 9 / 19;
        padding: .28rem; border-radius: 1.25rem; background: #0c1017; z-index: 50;
        border: 1px solid rgba(255,255,255,.14);
        transform: rotateX(0deg) rotateY(-10deg) rotateZ(0deg);
        box-shadow: -12px 18px 35px rgba(0,0,0,.7), 0 0 0 1px rgba(var(--brillo), .18);
    }
    .movil img { width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 1rem; display: block; }
    .ficha {
        position: absolute; left: -1rem; bottom: -1.2rem; z-index: 60;
        display: flex; flex-direction: column; align-items: flex-start; gap: .35rem;
        max-width: 78%; transform: rotateX(0deg) rotateY(-10deg) rotateZ(0deg);
    }
    .chip {
        display: flex; align-items: center; gap: .55rem;
        padding: .45rem .75rem .45rem .6rem; border-radius: 10px;
        background: rgba(255,255,255,.96); color: #1e1b4b;
        border: 1px solid rgba(var(--brillo), .25);
        box-shadow: 0 8px 24px -8px rgba(0,0,0,.45);
        font-family: "Inter", sans-serif;
        backdrop-filter: blur(4px);
    }
    .chip-icono { width: 1.75rem; height: 1.75rem; flex: 0 0 auto; }
    .chip-icono img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .chip > div { display: flex; flex-direction: column; gap: .08rem; }
    .chip strong { display: block; font-size: .78rem; line-height: 1.2; font-weight: 700; color: #111827; }
    .chip span { display: block; font-size: .68rem; color: #6b7280; line-height: 1.35; }
    .chip-btn {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .28rem .7rem; border-radius: 6px;
        font-family: "Exo 2", sans-serif; font-size: .6rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .05em;
        background: rgba(15,23,42,.92); color: #fff;
        border: 1px solid rgba(var(--brillo), .4);
        transition: background .2s, transform .2s, border-color .2s;
    }
    .chip-btn:hover { background: rgb(var(--brillo)); border-color: rgb(var(--brillo)); transform: translateY(-1px); }
    @media (min-width: 1024px) {
        .hero-grid { grid-template-columns: minmax(0, 1.02fr) minmax(0, 1fr); }
    }
    @media (max-width: 1023px) {
        .hero { max-height: none; padding-bottom: 4rem; }
    
    /* ===== Métricas / stats ===== */
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;max-width:80rem;margin:0 auto;padding:1.25rem 1rem 0}
    .metric{display:flex;align-items:center;gap:.75rem;padding:1rem 1rem;border-radius:.85rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .metric i{width:2.25rem;height:2.25rem;display:inline-flex;align-items:center;justify-content:center;border-radius:.6rem;background:rgba(0,212,255,.08);color:var(--cian);font-size:.95rem;flex-shrink:0}
    .metric b{display:block;font-family:'Exo 2',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);line-height:1.1}
    .metric span{display:block;font-size:.75rem;color:var(--gris-3);margin-top:.15rem;line-height:1.3}
    @media(max-width:900px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.metrics{grid-template-columns:1fr}}
    .db-health{margin-top:1.5rem;padding:1.25rem;border-radius:1rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .db-health-head{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;font-family:'Exo 2',sans-serif;font-weight:700;color:var(--txt);font-size:.95rem}
    .db-health-head i{color:#ff6b6b}
    .db-health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
    .db-health-item{padding:1rem;border-radius:.85rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);text-align:center}
    .db-health-item i{font-size:1.4rem;margin-bottom:.45rem;display:block}
    .db-health-item b{display:block;font-family:'Exo 2',sans-serif;font-size:1.15rem;font-weight:800;color:var(--txt);line-height:1.1}
    .db-health-item span{display:block;font-size:.74rem;color:var(--gris-3);margin-top:.2rem}
    @media(max-width:640px){.db-health-grid{grid-template-columns:1fr}}
    .integraciones-block{margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,.08)}
    .integraciones-block h3{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;text-align:center;margin:0 0 .5rem}
    .integraciones-block > p{text-align:center;color:#6b7280;font-size:.75rem;line-height:1.5;margin:0 0 2rem;font-family:'Inter',sans-serif}
    .integraciones-grid{display:flex;flex-wrap:nowrap;justify-content:space-between;gap:1rem;overflow-x:auto;padding-bottom:.25rem}
    .integracion-item{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;color:var(--gris-3);transition:color .25s ease}
    .integracion-item i{font-size:1.7rem;line-height:1}
    .integracion-item .int-name{font-family:'Inter',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:inherit;white-space:nowrap}
    .integracion-item .int-desc{font-family:'Inter',sans-serif;font-size:.68rem;color:#6b7280;line-height:1.3}
    .integracion-item:hover{color:#fff}
    .integracion-item.int-wa:hover{color:#25D366}
    .integracion-item.int-cian:hover{color:var(--cian)}
    .integracion-item.int-fuego:hover{color:var(--fuego)}
    .integracion-item.int-drive:hover{color:var(--fuego)}
    .integracion-item.int-excel:hover{color:#21A366}
    @media(max-width:900px){.integraciones-grid{justify-content:flex-start}.integracion-item{flex:0 0 120px}}
    .escena { margin-top: 3.25rem; }
    }
    @media (max-width: 40rem) {
        .movil { width: 24%; bottom: -8%; }
        .ficha { left: -.5rem; bottom: -1.4rem; max-width: 92%; }
        .escena-nav { justify-content: flex-start; }
        .hero-acciones { gap: .45rem; }
        .hero-acciones .btn {
            flex: 1 1 0; min-width: 0;
            padding: .72rem .55rem;
            font-size: clamp(.58rem, 2.9vw, .74rem);
            letter-spacing: .04em; gap: .35rem;
        }
    }

    /* ===== Secciones ===== */
    .sec {
        position: relative;
        padding-block: clamp(3.5rem, 7vw, 6rem);
        border-top: 1px solid rgba(30, 41, 59, .7);
    }
    /* Filete de separación que se dibuja al entrar en la sección */
    .sec::before {
        content: ""; position: absolute; inset-inline: 0; top: -1px; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(0, 212, 255, .55), rgba(255, 107, 0, .35), transparent);
        transform: scaleX(0); transform-origin: left center;
        transition: transform 1.1s cubic-bezier(.22, .8, .24, 1);
        pointer-events: none;
    }
    .sec.is-in::before { transform: scaleX(1); }
    .sec-head { max-width: 46rem; }
    .sec-head .eyebrow { margin-top: 0; color: var(--fuego); }
    .sec-head h2 {
        margin: .6rem 0 .8rem;
        font-size: clamp(2rem, 4.4vw, 3rem);
        text-transform: uppercase;
    }
    .sec-head p { color: var(--gris-2); font-size: 1rem; }

    /* ===== Tarjetas del ecosistema ===== */
    .grid { display: grid; gap: 1rem; margin-top: 2.5rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 640px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    /* Tarjetas «card-acero» de cyberforja: degradado de acero, brillo reactivo
       que sigue al puntero (--pointer-x/--pointer-y) y logo que gira al pasar. */
    .card {
        position: relative; display: flex; flex-direction: column; gap: .85rem;
        padding: 1.5rem;
        background: linear-gradient(145deg, #131b2c, #0a0f1a);
        border: 1px solid var(--acero);
        border-radius: .9rem;
        box-shadow: 0 10px 24px -18px rgba(0, 0, 0, .9);
        transition: transform .4s cubic-bezier(.4, 0, .2, 1), border-color .4s ease, box-shadow .4s ease;
        overflow: hidden;
        isolation: isolate;
    }
    .card__sheen {
        position: absolute; inset: 0; z-index: 0; pointer-events: none;
        opacity: 0;
        background: radial-gradient(290px circle at var(--pointer-x, 50%) var(--pointer-y, 50%),
                    var(--card-sheen, rgba(0, 212, 255, .18)), transparent 75%);
        transition: opacity .22s ease;
    }
    .card:hover {
        transform: translateY(-6px);
        border-color: rgba(0, 212, 255, .55);
        box-shadow: 0 26px 54px -28px rgba(0, 0, 0, .95);
    }
    .card:hover .card__sheen { opacity: 1; }
    .card > *:not(.card__sheen) { position: relative; z-index: 1; }
    .card-head {
        display: flex; align-items: center; gap: .85rem;
        padding-bottom: .75rem; margin-bottom: .1rem;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
    }
    .card-ico {
        flex: 0 0 auto;
        display: grid; place-items: center;
        width: 3rem; height: 3rem;
        border-radius: .7rem;
        background: rgba(0, 212, 255, .09);
        border: 1px solid rgba(0, 212, 255, .22);
        color: var(--cian); font-size: 1.15rem;
        transition: transform .35s cubic-bezier(.34, 1.56, .64, 1);
    }
    .card:hover .card-ico { transform: scale(1.12) rotate(6deg); }
    .card h3 {
        font-family: "Bebas Neue", Impact, sans-serif; font-weight: 400;
        font-size: 1.55rem; line-height: 1; letter-spacing: .03em; text-transform: uppercase;
    }
    .card-sub {
        margin-top: .25rem;
        font-family: "Exo 2", sans-serif; font-size: .62rem; font-weight: 800;
        letter-spacing: .14em; text-transform: uppercase; color: var(--cian);
    }
    .card p { color: var(--gris-2); font-size: .9rem; line-height: 1.65; }
    .card-link {
        margin-top: auto; padding-top: .85rem;
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        border-top: 1px solid rgba(255, 255, 255, .06);
        font-family: "Exo 2", sans-serif; font-size: .7rem; font-weight: 800;
        letter-spacing: .1em; text-transform: uppercase; color: var(--cian);
        transition: color .2s;
    }
    .card-link i { transition: transform .3s ease; }
    .card:hover .card-link i { transform: translateX(5px); }
    .card.destacada {
        border-color: rgba(255, 107, 0, .45);
        background: linear-gradient(145deg, #22140c, #0a0f1a 62%);
    }
    .card.destacada .card-ico { background: rgba(255, 107, 0, .12); border-color: rgba(255, 107, 0, .35); color: var(--fuego); }
    .card.destacada .card-sub { color: var(--fuego); }
    .card.destacada .card-link { color: var(--fuego); }
    .card.destacada:hover { border-color: var(--fuego); box-shadow: 0 26px 54px -26px rgba(255, 107, 0, .35); }

    /* ===== Secciones de ingeniería / proceso / quiénes somos ===== */
    .rule { width: 5rem; height: 4px; border-radius: 999px; background: var(--cian); margin: 1rem 0 1.15rem; }
    .duo { display: grid; gap: 2rem; margin-top: 2.4rem; align-items: center; }
    @media (min-width: 900px) { .duo { grid-template-columns: 1.05fr .95fr; gap: 3rem; } }
    .duo-espejo > :first-child { order: 1; }
    @media (min-width: 900px) { .duo-espejo > :first-child { order: 2; } }
    .duo h2 { font-size: clamp(2.1rem, 4.4vw, 3.4rem); text-transform: uppercase; }
    .duo p { color: var(--gris); font-size: 1rem; line-height: 1.75; }
    .duo .mini-lista { list-style: none; margin: 1.3rem 0 0; padding: 0; display: grid; gap: .6rem; }
    .duo .mini-lista li { display: flex; gap: .6rem; align-items: flex-start; color: var(--gris-2); font-size: .92rem; }
    .duo .mini-lista i { color: var(--cian); margin-top: .25rem; font-size: .8rem; }

    .chips { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: 1.1rem; }
    .chips span {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .42rem .75rem;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: .4rem;
        font-family: "Exo 2", sans-serif; font-size: .68rem; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; color: var(--gris);
        transition: border-color .2s, color .2s, background .2s;
    }
    .chips span:hover { border-color: rgba(0, 212, 255, .45); color: var(--txt); background: rgba(0, 212, 255, .08); }
    .chips i { color: var(--cian); font-size: .7rem; }

    .panel-destacado {
        background: linear-gradient(150deg, #101828, #0a0f1a 70%);
        border: 1px solid var(--acero);
        border-radius: 1rem;
        padding: 1.6rem;
        box-shadow: 0 30px 60px -40px #000;
    }
    .panel-destacado h3 {
        font-family: "Exo 2", sans-serif; font-size: .72rem; font-weight: 800;
        letter-spacing: .16em; text-transform: uppercase; color: var(--gris-2);
    }

    .pasos { display: grid; gap: 1rem; margin-top: 2.4rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 640px) { .pasos { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .pasos { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .paso {
        position: relative;
        padding: 1.4rem 1.3rem;
        background: linear-gradient(160deg, #111a2b, #090e18 70%);
        border: 1px solid var(--acero);
        border-radius: .9rem;
        transition: transform .35s cubic-bezier(.4, 0, .2, 1), border-color .35s;
    }
    .paso:hover { transform: translateY(-5px); border-color: rgba(0, 212, 255, .45); }
    .paso-num {
        display: block;
        font-family: "Bebas Neue", Impact, sans-serif; font-size: 2.3rem; line-height: 1;
        color: rgba(0, 212, 255, .28);
        margin-bottom: .5rem;
    }
    .paso strong {
        display: block;
        font-family: "Exo 2", sans-serif; font-size: .82rem; font-weight: 800;
        letter-spacing: .12em; text-transform: uppercase; color: var(--txt);
    }
    .paso p { margin-top: .45rem; color: var(--gris-2); font-size: .86rem; line-height: 1.6; }

    .cifras { display: grid; gap: .8rem; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 1.3rem; }
    .cifra { padding: .9rem 1rem; background: rgba(255, 255, 255, .03); border: 1px solid var(--acero); border-radius: .7rem; }
    .cifra b { display: block; font-family: "Bebas Neue", Impact, sans-serif; font-size: 1.9rem; line-height: 1; color: var(--cian); }
    .cifra span { font-family: "Exo 2", sans-serif; font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gris-3); }

    /* ===== Licencia ===== */
    .lic-grid { display: grid; gap: 1.5rem; margin-top: 2.4rem; align-items: start; }
    @media (min-width: 900px) { .lic-grid { grid-template-columns: 1.1fr .9fr; } }
    .panel {
        background: var(--tarjeta);
        border: 1px solid var(--acero);
        border-radius: 1rem;
        padding: 1.6rem;
    }
    .badge-estado {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .35rem .85rem; border-radius: 999px;
        font-family: "Exo 2", sans-serif; font-size: .72rem; font-weight: 800;
        letter-spacing: .12em; text-transform: uppercase;
    }
    .badge-estado.is-ok   { color: #34d399; background: rgba(52, 211, 153, .1); border: 1px solid rgba(52, 211, 153, .35); }
    .badge-estado.is-warn { color: var(--fuego); background: rgba(255, 107, 0, .1); border: 1px solid rgba(255, 107, 0, .35); }
    .badge-estado.is-off  { color: var(--gris-2); background: rgba(148, 163, 184, .08); border: 1px solid var(--acero); }
    .datos { display: grid; gap: .1rem; margin-top: 1.2rem; }
    .dato {
        display: grid; grid-template-columns: minmax(7rem, 34%) 1fr; gap: .8rem;
        padding: .65rem 0; border-bottom: 1px solid rgba(30, 41, 59, .8);
        font-size: .88rem;
    }
    .dato:last-child { border-bottom: 0; }
    .dato dt { color: var(--gris-3); font-weight: 600; }
    .dato dd { margin: 0; color: var(--txt); font-weight: 600; word-break: break-word; }
    .dato dd.mono { font-family: ui-monospace, Consolas, monospace; letter-spacing: .04em; }
    .lic-acciones { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.4rem; }
    .lic-nota { color: var(--gris-2); font-size: .9rem; line-height: 1.7; }
    .lic-nota b { color: var(--txt); }
    .lic-nota code { color: var(--cian); font-family: ui-monospace, Consolas, monospace; font-size: .85em; }

    /* ===== Servicios ===== */
    .serv-grid { display: grid; gap: .9rem; margin-top: 2.4rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 640px) { .serv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .serv-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .serv {
        display: flex; gap: .9rem; align-items: flex-start;
        padding: 1.15rem 1.2rem;
        background: rgba(9, 14, 24, .7);
        border: 1px solid var(--acero); border-radius: .7rem;
        transition: border-color .2s, transform .2s;
    }
    .serv:hover { border-color: rgba(0, 212, 255, .45); transform: translateY(-3px); }
    .serv i { color: var(--fuego); font-size: 1.05rem; margin-top: .2rem; }
    .serv strong {
        display: block;
        font-family: "Exo 2", sans-serif; font-size: .82rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .08em;
    }
    .serv p { color: var(--gris-2); font-size: .86rem; line-height: 1.6; margin-top: .25rem; }


    /* ===== Precios ===== */
    .precios-grid{display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));align-items:stretch;}
    .plan-card{position:relative;display:flex;flex-direction:column;padding:1.75rem;border-radius:1rem;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.015));border:1px solid rgba(255,255,255,.1);transition:transform .25s,border-color .25s,box-shadow .25s;}
    .plan-card:hover{transform:translateY(-4px);border-color:rgba(0,212,255,.35);box-shadow:0 20px 50px -20px rgba(0,212,255,.2);}
    .plan-card.is-featured{border-color:rgba(255,107,0,.45);box-shadow:0 24px 60px -24px rgba(255,107,0,.25);}
    .plan-card.is-featured:hover{border-color:rgba(255,107,0,.7);box-shadow:0 28px 70px -24px rgba(255,107,0,.35);}
    .plan-badge{position:absolute;top:-.7rem;left:1.5rem;padding:.3rem .75rem;border-radius:999px;background:var(--fuego);color:var(--abismo);font-family:"Exo 2",sans-serif;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}
    .plan-name{font-family:"Exo 2",sans-serif;font-size:1.15rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--txt);margin:0 0 .35rem;}
    .plan-desc{color:var(--gris-2);font-size:.92rem;line-height:1.65;margin:0 0 1.25rem;min-height:3.3rem;}
    .plan-price{display:flex;align-items:flex-end;gap:.35rem;margin-bottom:.25rem;}
    .plan-amount{font-family:"Bebas Neue",sans-serif;font-size:3.5rem;line-height:.9;color:var(--txt);}
    .plan-currency{font-family:"Exo 2",sans-serif;font-size:1rem;font-weight:700;color:var(--cian);margin-bottom:.45rem;}
    .plan-period{font-family:"Inter",sans-serif;font-size:.9rem;color:var(--gris-3);margin-bottom:.5rem;}
    .plan-note{font-size:.78rem;color:var(--gris-3);margin:0 0 1.25rem;}
    .plan-divider{border:0;border-top:1px solid rgba(255,255,255,.08);margin:0 0 1.25rem;}
    .plan-features{list-style:none;padding:0;margin:0 0 1.5rem;display:flex;flex-direction:column;gap:.7rem;flex:1;}
    .plan-features li{display:flex;gap:.65rem;align-items:flex-start;color:var(--gris-2);font-size:.9rem;line-height:1.5;}
    .plan-features i{color:var(--cian);margin-top:.2rem;font-size:.8rem;}
    .plan-card.is-featured .plan-features i{color:var(--fuego);}
    .plan-cta{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.9rem 1rem;border-radius:.5rem;font-family:"Exo 2",sans-serif;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;transition:all .25s;border:1px solid transparent;cursor:pointer;}
    .plan-cta-primary{background:var(--fuego);color:var(--abismo);}
    .plan-cta-primary:hover{background:var(--fuego-h);transform:translateY(-1px);}
    .plan-cta-secondary{background:rgba(0,212,255,.08);color:var(--cian);border-color:rgba(0,212,255,.3);}
    .plan-cta-secondary:hover{background:rgba(0,212,255,.15);border-color:rgba(0,212,255,.55);}
    .precios-trial{margin-top:2rem;padding:1.25rem 1.5rem;border-radius:1rem;background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.2);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;}
    .precios-trial p{margin:0;color:var(--gris-2);font-size:.95rem;}
    .precios-trial strong{display:block;font-family:"Exo 2",sans-serif;color:var(--txt);text-transform:uppercase;font-size:.8rem;letter-spacing:.05em;margin-bottom:.2rem;}
    .precios-links{margin-top:1.25rem;display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}
    .precios-link{display:flex;gap:.75rem;align-items:flex-start;padding:1rem;border-radius:.85rem;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.02);text-decoration:none;transition:border-color .2s,background .2s;}
    .precios-link:hover{border-color:rgba(0,212,255,.35);background:rgba(0,212,255,.04);}
    .precios-link i{color:var(--cian);margin-top:.15rem;}
    .precios-link span{font-size:.88rem;color:var(--gris-2);line-height:1.5;}
    .precios-link strong{display:block;font-family:"Exo 2",sans-serif;color:var(--txt);font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.2rem;}
    
    /* ===== Ingeniería (estilo CyberForja) ===== */
    #servicios{position:relative;overflow:hidden;padding:2rem 0 4rem;background:var(--abismo);isolation:isolate}
    @media(min-width:640px){#servicios{padding:3rem 0 4rem}}
    @media(min-width:768px){#servicios{padding:4rem 0 4rem}}
    #servicios .ing-wrap{max-width:80rem;margin:0 auto;padding:0 1.5rem;position:relative;z-index:10}
    @media(min-width:1024px){#servicios .ing-wrap{padding:0 2rem}}
    #servicios .ing-eyebrow{color:var(--cian);font-family:'Exo 2',sans-serif;font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-size:.75rem}
    @media(min-width:640px){#servicios .ing-eyebrow{font-size:.875rem}}
    #servicios .ing-title{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.05em;color:#fff;margin:.25rem 0 .5rem;line-height:1}
    @media(min-width:640px){#servicios .ing-title{font-size:1.875rem}}
    @media(min-width:768px){#servicios .ing-title{font-size:3rem}}
    #servicios .ing-line{width:4rem;height:.25rem;background:var(--cian);border-radius:9999px;margin-bottom:.75rem}
    #servicios .ing-desc{font-family:'Inter',sans-serif;color:#d1d5db;font-size:.75rem;line-height:1.625;max-width:48rem}
    @media(min-width:640px){#servicios .ing-desc{font-size:.875rem}}
    @media(min-width:768px){#servicios .ing-desc{font-size:1rem}}
    #servicios .ing-grid{display:grid;grid-template-columns:1fr;gap:1rem;margin-top:2.5rem}
    @media(min-width:640px){#servicios .ing-grid{grid-template-columns:repeat(2,1fr);gap:1.25rem}}
    @media(min-width:1024px){#servicios .ing-grid{grid-template-columns:repeat(4,1fr)}}
    #servicios .ing-card{position:relative;overflow:hidden;border-radius:.75rem;padding:1.25rem;background:#080d17;border:1px solid rgba(255,255,255,.08);transition:all .3s ease}
    #servicios .ing-card:hover{transform:translateY(-4px)}
    #servicios .ing-card.cian:hover{border-color:rgba(0,212,255,.4)}
    #servicios .ing-card.fuego:hover{border-color:rgba(255,107,0,.4)}
    #servicios .ing-icon{width:2.5rem;height:2.5rem;border-radius:.5rem;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;position:relative;z-index:10;font-size:1rem}
    #servicios .ing-icon.cian{background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.2);color:var(--cian)}
    #servicios .ing-icon.fuego{background:rgba(255,107,0,.1);border:1px solid rgba(255,107,0,.2);color:var(--fuego)}
    #servicios .ing-card h3{font-family:'Exo 2',sans-serif;font-weight:700;font-size:1rem;color:#fff;margin:0 0 .375rem;text-transform:uppercase;letter-spacing:.025em;position:relative;z-index:10}
    #servicios .ing-card p{font-family:'Inter',sans-serif;color:#9ca3af;font-size:.75rem;line-height:1.625;margin:0;position:relative;z-index:10}
    @media(min-width:640px){#servicios .ing-card p{font-size:.875rem}}
    #servicios .card__sheen{position:absolute;inset:0;pointer-events:none;z-index:1;opacity:0;background:radial-gradient(290px circle at var(--pointer-x,50%) var(--pointer-y,50%),var(--card-sheen,rgba(0,212,255,.18)),transparent 75%);transition:opacity .22s ease}
    #servicios .ing-card:hover .card__sheen{opacity:1}
    #servicios .circuit-bg{position:absolute;inset:0;z-index:0;opacity:.12;pointer-events:none;overflow:hidden}
    #servicios .circuit-radial{position:absolute;inset:0;pointer-events:none;z-index:0;background:radial-gradient(ellipse 60% 60% at 70% 40%,rgba(0,212,255,.04) 0%,transparent 70%)}
    .circuit-line-cian,.circuit-line-cian-delay,.circuit-line-fuego,.circuit-line-fuego-slow{stroke-dasharray:240;stroke-dashoffset:240;animation:flow-pulse-circuit 7s linear infinite}
    .circuit-line-cian{animation-duration:5.5s}
    .circuit-line-cian-delay{animation-duration:7.5s;animation-delay:2.2s}
    .circuit-line-fuego{animation-duration:6.5s;animation-delay:1.2s}
    .circuit-line-fuego-slow{animation-duration:9.5s;animation-delay:3.2s}
    @keyframes flow-pulse-circuit{0%{stroke-dashoffset:480;opacity:.08}12%{opacity:.75}25%{opacity:.08}100%{stroke-dashoffset:0;opacity:.08}}
    #servicios::after{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(to bottom,transparent 0%,rgba(0,212,255,.01) 45%,rgba(255,107,0,.02) 50%,rgba(0,212,255,.01) 55%,transparent 100%);background-size:100% 200%;pointer-events:none;animation:radar-sweep 14s cubic-bezier(.4,0,.2,1) infinite}
    @keyframes radar-sweep{0%{background-position:0% -100%}100%{background-position:0% 100%}}
    @media(prefers-reduced-motion:reduce){.circuit-line-cian,.circuit-line-cian-delay,.circuit-line-fuego,.circuit-line-fuego-slow,#servicios::after{animation:none}}

    /* ===== FAQ ===== */
    details.faq {
        background: var(--tarjeta);
        border: 1px solid var(--acero);
        border-radius: .7rem;
        padding: .2rem 1.15rem;
        margin-top: .7rem;
        transition: border-color .2s;
    }
    details.faq:hover { border-color: rgba(0, 212, 255, .4); }
    details.faq summary {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: .95rem 0; cursor: pointer; list-style: none;
        font-family: "Exo 2", sans-serif; font-size: .88rem; font-weight: 700;
        letter-spacing: .02em;
    }
    details.faq summary::-webkit-details-marker { display: none; }
    details.faq summary i { color: var(--cian); font-size: .8rem; transition: transform .2s; }
    details.faq[open] summary i { transform: rotate(45deg); }
    details.faq .faq-body { padding: 0 0 1.05rem; color: var(--gris-2); font-size: .9rem; line-height: 1.7; }
    details.faq .faq-body a { color: var(--cian); border-bottom: 1px solid rgba(0, 212, 255, .35); }
    details.faq .faq-body code { color: var(--cian); font-family: ui-monospace, Consolas, monospace; font-size: .86em; }
    .faq-lista { margin-top: 2rem; max-width: 56rem; }

    /* ===== CTA final ===== */
    .cta {
        position: relative; overflow: hidden;
        padding: clamp(2rem, 5vw, 3.5rem);
        border: 1px solid rgba(255, 107, 0, .35);
        border-radius: 1rem;
        background:
            radial-gradient(90% 140% at 100% 0%, rgba(255, 107, 0, .16), transparent 60%),
            radial-gradient(80% 120% at 0% 100%, rgba(0, 212, 255, .12), transparent 60%),
            var(--tarjeta);
        text-align: center;
    }
    .cta h2 {
        font-size: clamp(2rem, 4.6vw, 3.2rem);
        text-transform: uppercase; margin-bottom: .7rem;
    }
    .cta p { color: var(--gris-2); max-width: 44rem; margin: 0 auto; }
    .cta .hero-acciones { justify-content: center; }

    /* ===== Barra de progreso de lectura ===== */
    .scroll-progress {
        position: fixed; top: 0; left: 0; right: 0;
        height: 3px; z-index: 70; pointer-events: none;
        background: rgba(10, 15, 26, .35);
    }
    .scroll-progress span {
        display: block; width: 0; height: 100%;
        background: linear-gradient(90deg, var(--cian), var(--fuego));
        box-shadow: 0 0 12px rgba(0, 212, 255, .55);
        transition: width .08s linear;
    }

    /* ===== Pie ===== */
    .pie { border-top: 1px solid var(--acero); padding-block: 3rem 1.5rem; background: #080c15; }
    .pie-grid { display: grid; gap: 2rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 720px) { .pie-grid { grid-template-columns: 1.5fr 1fr 1fr; } }
    @media (min-width: 1080px) { .pie-grid { grid-template-columns: 1.6fr 1.05fr 1fr 1fr; } }
    .pie-lista { list-style: none; display: grid; gap: .2rem; }
    .pie-lista a { display: inline-flex; align-items: center; gap: .45rem; }
    .pie-lista a i { color: var(--gris-3); font-size: .78em; transition: color .2s; }
    .pie-lista a:hover i { color: var(--fuego); }
    .pie-social { display: flex; flex-wrap: nowrap; gap: .45rem; margin-bottom: 1.3rem; }
    .pie .pie-social a {
        display: grid; place-items: center;
        width: 2.1rem; height: 2.1rem; border-radius: 50%;
        border: 1px solid var(--acero); color: var(--gris-2);
        background: rgba(255, 255, 255, .03);
        transition: color .2s, border-color .2s, background .2s, transform .2s;
    }
    .pie .pie-social a:hover,
    .pie .pie-social a:focus-visible {
        color: var(--abismo); background: var(--cian); border-color: var(--cian);
        transform: translateY(-2px);
    }
    .pie .pie-social a i,
    .pie .pie-social a svg { color: inherit; transition: transform .2s; }
    .pie .pie-social a:hover i,
    .pie .pie-social a:hover svg,
    .pie .pie-social a:focus-visible i,
    .pie .pie-social a:focus-visible svg { transform: scale(1.18); }
    .pie-social svg { width: .82rem; height: .82rem; }
    .pie-legal-title { margin-top: .4rem; }
    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: .5rem;
        background: var(--tarjeta);
        border: 1px solid var(--acero);
        border-radius: .5rem;
        padding: .5rem;
        min-width: 140px;
        z-index: 50;
        opacity: 0;
        visibility: hidden;
        transition: opacity .2s, visibility .2s;
    }
    .pie-legal { display: flex; flex-wrap: wrap; gap: .3rem 1rem; }
    .pie h4 {
        font-family: "Exo 2", sans-serif; font-size: .74rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .14em; color: var(--gris-2);
        margin-bottom: .8rem;
    }
    .pie a, .pie p { color: var(--gris-3); font-size: .86rem; line-height: 1.9; }
    .pie a:hover { color: var(--cian); }
    .pie-marca { display: flex; align-items: center; gap: .7rem; margin-bottom: .8rem; }
    .pie-marca img { width: 2.2rem; height: 2.2rem; object-fit: contain; }
    .pie-barra {
        margin-top: 2.5rem; padding-top: 1.2rem;
        border-top: 1px solid rgba(30, 41, 59, .8);
        display: flex; flex-wrap: wrap; gap: .6rem; justify-content: space-between;
        color: var(--gris-3); font-size: .78rem;
    }

    /* ===== Botones flotantes: ir hasta arriba / ir hasta abajo ===== */
    .scroll-fabs {
        position: fixed; right: 1.15rem; bottom: 1.15rem; z-index: 60;
        display: flex; flex-direction: column; gap: .55rem;
    }
    .scroll-fab {
        display: flex; align-items: center; justify-content: center;
        width: 2.9rem; height: 2.9rem; padding: 0;
        border-radius: 50%;
        background: rgba(11, 14, 20, .88);
        color: var(--cian);
        border: 1px solid var(--acero);
        box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .9);
        cursor: pointer;
        opacity: 0; visibility: hidden;
        transform: translateY(8px) scale(.92);
        -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
        transition: opacity .2s, transform .2s, visibility .2s, background .2s, color .2s, border-color .2s;
    }
    .scroll-fab.is-visible { opacity: 1; visibility: visible; transform: none; }
    .scroll-fab:hover {
        color: var(--abismo); background: var(--cian);
        border-color: var(--cian); transform: translateY(-2px);
    }
    .scroll-fab:focus-visible { outline: 2px solid var(--cian); outline-offset: 3px; }
    @media (max-width: 30rem) {
        .scroll-fabs { right: .8rem; bottom: .8rem; }
        .scroll-fab { width: 2.55rem; height: 2.55rem; font-size: .9rem; }
    }

    /* ===== Intro animada: piezas del logo (images/anim) ===== */
    html.intro-lock { overflow: hidden; }
    .intro {
        position: fixed; inset: 0; z-index: 9999;
        display: grid; place-items: center;
        background: radial-gradient(120% 120% at 50% 26%, #111c38 0%, #070a12 56%, #03050a 100%);
        transition: opacity .8s ease, visibility .8s ease;
    }
    .intro.is-out { opacity: 0; visibility: hidden; }
    .intro-stage {
        position: relative;
        width: min(92vmin, 760px);
        aspect-ratio: 429 / 440;
        display: grid; place-items: center;
        isolation: isolate;
    }
    .intro-halo {
        position: absolute; z-index: 0;
        width: 78%; aspect-ratio: 1; border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 212, 255, .34) 0%, rgba(255, 107, 0, .16) 44%, rgba(0, 212, 255, 0) 70%);
        filter: blur(10px);
        opacity: 0; transform: scale(.72);
        transition: opacity 1s ease, transform 1.4s cubic-bezier(.22, .8, .24, 1);
    }
    .intro.is-final .intro-halo { opacity: 1; transform: scale(1); animation: introHalo 4s ease-in-out 1.2s infinite; }
    @keyframes introHalo {
        0%, 100% { opacity: .82; transform: scale(1); }
        50%      { opacity: 1;   transform: scale(1.06); }
    }
    .intro-layer,
    .intro-final {
        position: absolute; inset: 0; z-index: 1;
        display: grid; place-items: center;
        opacity: 0;
        transform: scale(.92) translateY(10px);
        transition: opacity .7s ease, transform 1.15s cubic-bezier(.16, .84, .24, 1);
        will-change: opacity, transform;
        pointer-events: none;
    }
    .intro-layer img {
        position: absolute; height: auto;
        max-width: none; max-height: none;
        filter: drop-shadow(0 24px 46px rgba(0, 0, 0, .62));
    }
    .intro-layer[data-paso="1"] img { left: 0;   top: 0;  width: 100%; }
    .intro-layer[data-paso="2"] img { left: 3%;  top: 7%; width: 78%; }
    .intro-layer[data-paso="3"] img { left: 5%;  top: 40%; width: 66%; }
    .intro-layer[data-paso="4"] img { left: 55%; top: 18%; width: 44%; }
    .intro-layer[data-paso="5"] img { left: 12%; top: 74%; width: 78%; }
    .intro-layer[data-paso="1"] { transform: scale(.5); }
    .intro-layer[data-paso="2"] { transform: translateX(135%) scale(.68); }
    .intro-layer[data-paso="3"] { transform: translateX(-135%) scale(.68); }
    .intro-layer[data-paso="4"] { transform: translateX(135%) scale(.68); }
    .intro-layer[data-paso="5"] { transform: translateY(-185%) scale(.68); }
    .intro-layer.is-on { opacity: 1; transform: translate(0, 0) scale(1); }
    .intro.is-final .intro-layer { opacity: 0; transition: opacity .3s ease; }
    .intro-final { z-index: 2; transform: scale(.92) translateY(8px); }
    .intro-logo { position: relative; width: 100%; }
    .intro-logo img { display: block; width: 100%; height: auto; filter: drop-shadow(0 30px 70px rgba(0, 0, 0, .66)); }
    .intro-final.is-on { opacity: 1; transform: scale(1) translateY(0); }
    .intro-final.is-on .intro-logo { animation: introLogoIn 1.15s cubic-bezier(.2, .72, .2, 1) both; }
    @keyframes introLogoIn {
        0%   { transform: scale(.84); filter: brightness(1.45) saturate(1.15); }
        60%  { transform: scale(1.03); }
        100% { transform: scale(1); filter: none; }
    }
    .intro-sheen {
        position: absolute; inset: 0; pointer-events: none; opacity: 0;
        background-image: linear-gradient(105deg,
            rgba(255, 255, 255, 0) 40%,
            rgba(255, 255, 255, .32) 46%,
            rgba(255, 255, 255, .95) 50%,
            rgba(140, 235, 255, .85) 54%,
            rgba(255, 255, 255, 0) 60%);
        background-size: 260% 100%;
        background-position: 135% 0;
        background-repeat: no-repeat;
        -webkit-mask-size: contain; mask-size: contain;
        -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat;
        -webkit-mask-position: center; mask-position: center;
        mix-blend-mode: screen;
    }
    .intro.is-sheen .intro-sheen { opacity: 1; animation: introSheen 1.4s cubic-bezier(.4, 0, .5, 1) 2; }
    @keyframes introSheen {
        0%   { background-position: 135% 0; }
        100% { background-position: -45% 0; }
    }
    .intro-bar {
        position: absolute; left: 50%; bottom: max(2.25rem, 5vh);
        width: min(64vmin, 520px); height: 3px;
        transform: translateX(-50%);
        background: rgba(255, 255, 255, .14);
        border-radius: 2px; overflow: hidden;
    }
    .intro-bar span {
        display: block; width: 0; height: 100%;
        background: linear-gradient(90deg, var(--cian), var(--fuego));
        transition: width .35s ease;
    }
    .intro-acciones {
        position: absolute; right: max(1rem, 2vw); bottom: max(1rem, 2vh);
        display: flex; flex-direction: column; gap: .5rem; align-items: flex-end;
    }
    .intro-skip {
        padding: .5rem .95rem; font: inherit; font-size: .8rem;
        font-family: "Exo 2", sans-serif; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        color: #cfd6ff;
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 999px;
        cursor: pointer;
        -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
        transition: background .2s, border-color .2s, color .2s;
    }
    .intro-skip:hover, .intro-skip:focus-visible {
        background: rgba(255, 255, 255, .12);
        border-color: rgba(0, 212, 255, .5);
        color: #fff; outline: none;
    }
    @media (max-width: 520px) {
        .intro-acciones { top: max(1rem, 2vh); bottom: auto; right: 1rem; }
        .intro-bar { bottom: max(1.25rem, 3vh); width: min(82vw, 360px); }
    }

    /* ===== Nuestro proceso (réplica de cyberforja) ===== */
    .proceso-head { text-align: center; max-width: 46rem; margin: 0 auto 2.2rem; }
    .proceso-head .eyebrow { margin-top: 0; color: var(--fuego); font-size: .8rem; }
    .proceso-head h2 {
        margin: .45rem 0 .7rem;
        font-size: clamp(2.2rem, 5vw, 3.8rem);
        text-transform: uppercase;
    }
    .proceso-head .rule-fuego { width: 5rem; margin: 0 auto 1rem; }
    .proceso-head p {
        max-width: 42rem; margin-inline: auto;
        color: var(--gris); font-size: 1rem; line-height: 1.7;
    }
    .proceso-grid { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 768px) { .proceso-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1.5rem; } }
    .proc-paso {
        display: flex; flex-direction: column; justify-content: space-between;
        padding: 1rem 1.1rem;
        background: rgba(0, 0, 0, .4);
        border: 1px solid rgba(30, 41, 59, .6);
        border-radius: .75rem;
        transition: border-color .3s ease, transform .35s cubic-bezier(.4, 0, .2, 1);
    }
    @media (min-width: 640px) { .proc-paso { padding: 1.5rem; } }
    .proc-paso:hover { border-color: rgba(0, 212, 255, .4); transform: translateY(-3px); }
    .proc-paso.is-fuego:hover { border-color: rgba(255, 107, 0, .4); }
    .proc-num {
        display: block; margin-bottom: .6rem;
        font-family: "Bebas Neue", Impact, sans-serif; font-size: 2.3rem; line-height: 1;
        color: rgba(0, 212, 255, .4);
        transition: color .3s ease;
    }
    .proc-paso.is-fuego .proc-num { color: rgba(255, 107, 0, .4); }
    .proc-paso:hover .proc-num { color: var(--cian); }
    .proc-paso.is-fuego:hover .proc-num { color: var(--fuego); }
    .proc-paso h3 {
        font-family: "Exo 2", sans-serif; font-size: .95rem; font-weight: 800;
        letter-spacing: .06em; text-transform: uppercase; color: var(--txt); margin-bottom: .5rem;
    }
    .proc-paso p { color: var(--gris-2); font-size: .78rem; line-height: 1.6; }

    /* ===== Aparición al hacer scroll =====
       Cada bloque entra con su propia transición y un retardo escalonado
       (--d), así las tarjetas, pasos, chips y columnas van apareciendo en cadena. */
    .js .reveal {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity .8s cubic-bezier(.22, .8, .24, 1), transform .8s cubic-bezier(.22, .8, .24, 1);
        transition-delay: var(--d, 0ms);
        will-change: opacity, transform;
    }
    .js .reveal[data-anim="left"]  { transform: translateX(-48px); }
    .js .reveal[data-anim="right"] { transform: translateX(48px); }
    .js .reveal[data-anim="zoom"]  { transform: scale(.92); }
    .js .reveal[data-anim="tilt"]  { transform: perspective(1100px) rotateX(10deg) translateY(30px); }
    .js .reveal.is-in { opacity: 1; transform: none; }

    /* El filete de los encabezados crece al aparecer */
    .js .sec-head .rule, .js .equipo-head .rule-fuego {
        transform: scaleX(0); transform-origin: left center;
        transition: transform .95s cubic-bezier(.22, .8, .24, 1) .18s;
    }
    .js .sec-head.is-in .rule, .js .equipo-head.is-in .rule-fuego { transform: scaleX(1); }

    /* Numeración de los pasos y cifras con entrada propia */
    .js .paso .paso-num,
    .js .cifra b {
        opacity: 0; transform: translateY(12px) scale(.9);
        transition: opacity .6s ease .18s, transform .6s cubic-bezier(.34, 1.56, .64, 1) .18s;
    }
    .js .paso.is-in .paso-num,
    .js .cifra.is-in b { opacity: 1; transform: none; }

    /* El hero y la escena también tienen su entrada */
    @media (prefers-reduced-motion: no-preference) {
        .js .hero-copy > * { opacity: 0; }
        html:not(.intro-lock) .hero-copy > * {
            animation: heroIn .9s cubic-bezier(.22, .8, .24, 1) both;
        }
        html:not(.intro-lock) .hero-copy > *:nth-child(1) { animation-delay: .05s; }
        html:not(.intro-lock) .hero-copy > *:nth-child(2) { animation-delay: .16s; }
        html:not(.intro-lock) .hero-copy > *:nth-child(3) { animation-delay: .27s; }
        html:not(.intro-lock) .hero-copy > *:nth-child(4) { animation-delay: .38s; }
        html:not(.intro-lock) .hero-copy > *:nth-child(5) { animation-delay: .49s; }
        html:not(.intro-lock) .escena { animation: escenaIn 1.2s cubic-bezier(.22, .8, .24, 1) .35s both; }
    }
    @keyframes heroIn {
        from { opacity: 0; transform: translateY(26px); }
        to   { opacity: 1; transform: none; }
    }
    @keyframes escenaIn {
        from { opacity: 0; transform: translate3d(0, 34px, 0) scale(.96); }
        to   { opacity: 1; transform: none; }
    }

    /* ===== Quiénes somos (réplica de la sección de cyberforja) ===== */
    .equipo-head { max-width: 48rem; margin-bottom: 2rem; }
    .equipo-head .eyebrow { margin-top: 0; color: var(--fuego); }
    .equipo-head h2 {
        margin: .35rem 0 .55rem;
        font-size: clamp(2rem, 4.4vw, 3.1rem);
        text-transform: uppercase;
    }
    .rule-fuego {
        width: 4rem; height: 4px; border-radius: 999px;
        background: var(--fuego); margin-bottom: 1rem;
    }
    .equipo-head p { color: var(--gris); font-size: 1rem; line-height: 1.75; }

    .equipo-card {
        position: relative; isolation: isolate; overflow: hidden;
        padding: clamp(1.5rem, 3vw, 2rem);
        background: rgba(0, 0, 0, .4);
        border: 1px solid rgba(30, 41, 59, .6);
        border-radius: 1rem;
        -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
        transition: border-color .3s ease, box-shadow .3s ease;
    }
    .equipo-card:hover { border-color: rgba(255, 107, 0, .4); box-shadow: 0 30px 60px -40px rgba(255, 107, 0, .35); }
    .equipo-card > *:not(.card__sheen) { position: relative; z-index: 1; }
    .equipo-grid { display: grid; gap: 2rem; }
    @media (min-width: 1024px) {
        .equipo-grid { grid-template-columns: 8fr 4fr; gap: 2.5rem; align-items: center; }
    }
    .pilares { display: grid; gap: 1.6rem; }
    @media (min-width: 640px) { .pilares { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem; } }
    .pilar-ico {
        display: grid; place-items: center;
        width: 2.5rem; height: 2.5rem;
        border-radius: .5rem; margin-bottom: .75rem; font-size: 1rem;
        transition: transform .35s cubic-bezier(.34, 1.56, .64, 1);
    }
    .pilar-ico.is-fuego { background: rgba(255, 107, 0, .1); border: 1px solid rgba(255, 107, 0, .2); color: var(--fuego); }
    .pilar-ico.is-cian  { background: rgba(0, 212, 255, .1); border: 1px solid rgba(0, 212, 255, .2); color: var(--cian); }
    .pilar:hover .pilar-ico { transform: translateY(-3px) rotate(-6deg); }
    .pilar h3 {
        font-family: "Exo 2", sans-serif; font-size: .8rem; font-weight: 800;
        letter-spacing: .06em; text-transform: uppercase; color: var(--txt); margin-bottom: .4rem;
    }
    .pilar p { color: var(--gris-2); font-size: .78rem; line-height: 1.65; }
    .compromiso { display: flex; flex-direction: column; align-items: flex-start; gap: 1rem; }
    @media (min-width: 1024px) {
        .compromiso { border-left: 1px solid rgba(255, 255, 255, .1); padding-left: 2rem; }
    }
    .compromiso h4 {
        font-family: "Exo 2", sans-serif; font-size: .8rem; font-weight: 800;
        letter-spacing: .06em; text-transform: uppercase; color: var(--txt);
    }
    .compromiso p { margin-top: .35rem; color: var(--gris-2); font-size: .78rem; line-height: 1.65; }
    .btn-outline-fuego {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .8rem 1.4rem; border: 1px solid var(--fuego); border-radius: .25rem;
        color: var(--fuego);
        font-family: "Exo 2", sans-serif; font-size: .72rem; font-weight: 800;
        letter-spacing: .1em; text-transform: uppercase;
        transition: background .3s ease, color .3s ease, transform .3s ease;
    }
    .btn-outline-fuego:hover { background: var(--fuego); color: var(--abismo); transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        .js .reveal, .js .reveal[data-anim] { opacity: 1; transform: none; transition: none; }
        .js .sec-head .rule, .js .equipo-head .rule-fuego { transform: none; transition: none; }
        .js .paso .paso-num, .js .cifra b { opacity: 1; transform: none; transition: none; }
        .sec::before { transform: scaleX(1); transition: none; }
        .js .hero-copy > * { opacity: 1; animation: none; }
    
    /* ===== Métricas / stats ===== */
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;max-width:80rem;margin:0 auto;padding:1.25rem 1rem 0}
    .metric{display:flex;align-items:center;gap:.75rem;padding:1rem 1rem;border-radius:.85rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .metric i{width:2.25rem;height:2.25rem;display:inline-flex;align-items:center;justify-content:center;border-radius:.6rem;background:rgba(0,212,255,.08);color:var(--cian);font-size:.95rem;flex-shrink:0}
    .metric b{display:block;font-family:'Exo 2',sans-serif;font-size:1rem;font-weight:800;color:var(--txt);line-height:1.1}
    .metric span{display:block;font-size:.75rem;color:var(--gris-3);margin-top:.15rem;line-height:1.3}
    @media(max-width:900px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.metrics{grid-template-columns:1fr}}
    .db-health{margin-top:1.5rem;padding:1.25rem;border-radius:1rem;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08)}
    .db-health-head{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;font-family:'Exo 2',sans-serif;font-weight:700;color:var(--txt);font-size:.95rem}
    .db-health-head i{color:#ff6b6b}
    .db-health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
    .db-health-item{padding:1rem;border-radius:.85rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);text-align:center}
    .db-health-item i{font-size:1.4rem;margin-bottom:.45rem;display:block}
    .db-health-item b{display:block;font-family:'Exo 2',sans-serif;font-size:1.15rem;font-weight:800;color:var(--txt);line-height:1.1}
    .db-health-item span{display:block;font-size:.74rem;color:var(--gris-3);margin-top:.2rem}
    @media(max-width:640px){.db-health-grid{grid-template-columns:1fr}}
    .integraciones-block{margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,.08)}
    .integraciones-block h3{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;text-align:center;margin:0 0 .5rem}
    .integraciones-block > p{text-align:center;color:#6b7280;font-size:.75rem;line-height:1.5;margin:0 0 2rem;font-family:'Inter',sans-serif}
    .integraciones-grid{display:flex;flex-wrap:nowrap;justify-content:space-between;gap:1rem;overflow-x:auto;padding-bottom:.25rem}
    .integracion-item{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;color:var(--gris-3);transition:color .25s ease}
    .integracion-item i{font-size:1.7rem;line-height:1}
    .integracion-item .int-name{font-family:'Inter',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:inherit;white-space:nowrap}
    .integracion-item .int-desc{font-family:'Inter',sans-serif;font-size:.68rem;color:#6b7280;line-height:1.3}
    .integracion-item:hover{color:#fff}
    .integracion-item.int-wa:hover{color:#25D366}
    .integracion-item.int-cian:hover{color:var(--cian)}
    .integracion-item.int-fuego:hover{color:var(--fuego)}
    .integracion-item.int-drive:hover{color:var(--fuego)}
    .integracion-item.int-excel:hover{color:#21A366}
    @media(max-width:900px){.integraciones-grid{justify-content:flex-start}.integracion-item{flex:0 0 120px}}
    .escena { animation: none; }
        .pantalla, .movil, .escena::before { transform: none; }
        .btn:hover, .card:hover, .serv:hover, .paso:hover, .equipo-card:hover { transform: none; }
    }
</style>
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
        <button type="button" class="intro-skip" id="introNoMostrar">No volver a mostrar intro</button>
        <button type="button" class="intro-skip" id="introSkip">Saltar intro</button>
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
            <a href="#ecosistema"><i class="fa-solid fa-cubes" aria-hidden="true"></i> ECOSIST.</a>
            <a href="#medida"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
            <a href="#licencia-estado"><i class="fa-solid fa-key" aria-hidden="true"></i> Licencia</a>
            <a href="#servicios"><i class="fa-solid fa-microchip" aria-hidden="true"></i> INGENIERÍA</a>
            <a href="#plataforma"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> SERVICIOS</a>
            <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
            <a href="#proceso"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Proceso</a>
            <a href="#nosotros"><i class="fa-solid fa-users" aria-hidden="true"></i> Nosotros</a>
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
        <a href="#ecosistema"><i class="fa-solid fa-cubes" aria-hidden="true"></i> ECOSIST.</a>
        <a href="#medida"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
        <a href="#licencia-estado"><i class="fa-solid fa-key" aria-hidden="true"></i> Licencia</a>
        <a href="#servicios"><i class="fa-solid fa-microchip" aria-hidden="true"></i> INGENIERÍA</a>
        <a href="#plataforma"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> SERVICIOS</a>
        <a href="#preguntas"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Preguntas</a>
        <a href="#proceso"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Proceso</a>
        <a href="#nosotros"><i class="fa-solid fa-users" aria-hidden="true"></i> Nosotros</a>
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
                    <img src="/servicios/images/costos.png"
                         width="1024" height="1024" alt="Panel del subsistema de Costos" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/servicios/images/costos.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/servicios/images/costos.png"
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
                    <img src="/servicios/images/inventario.png"
                         width="1024" height="1024" alt="Panel del subsistema de Inventarios" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/servicios/images/inventario.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/servicios/images/inventario.png"
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
                    <img src="/servicios/images/admin.png"
                         width="1024" height="1024" alt="Panel del subsistema de Administración" loading="lazy" decoding="async">
                </div>
                <div class="movil">
                    <img src="/servicios/images/admin.png"
                         width="1024" height="1024" alt="" loading="lazy" decoding="async">
                </div>
                <div class="ficha">
                    <div class="chip">
                        <span class="chip-icono">
                            <img src="/servicios/images/admin.png"
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

    <!-- ===== Ingeniería personalizada ===== -->
    <section class="sec" id="medida">
        <div class="wrap">
            <div class="duo duo-espejo">
                <div class="reveal">
                    <span class="eyebrow">Ingeniería personalizada</span>
                    <h2 class="bebas">Software<br>a medida.</h2>
                    <div class="rule" aria-hidden="true"></div>
                    <p>
                        Analizamos tus procesos y desarrollamos aplicaciones adaptadas a tu organización,
                        desde herramientas internas hasta plataformas completas.
                    </p>
                    <div class="hero-acciones" style="margin-top:1.4rem">
                        <a class="btn btn-fuego" href="/desamedida.php">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Contar mi proyecto
                        </a>
                    </div>
                </div>

                <div class="pasos" style="margin-top:0">
                    <div class="paso reveal" style="grid-column:1 / -1">
                        <span class="paso-num">01</span>
                        <strong>Herramientas internas</strong>
                        <p>Control de inventario, producción, compras o cualquier proceso que hoy vive en hojas de cálculo.</p>
                    </div>
                    <div class="paso reveal" style="grid-column:1 / -1">
                        <span class="paso-num">02</span>
                        <strong>Plataformas completas</strong>
                        <p>Sistemas con varios módulos, usuarios y permisos, integrados con tu operación diaria.</p>
                    </div>
                </div>
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
                            <i class="fa-solid fa-headset" aria-hidden="true"></i> Pedir soporte
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

    <!-- ===== Servicios / plataforma ===== -->
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
<section class="sec" id="plataforma">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Bajo el capó</span>
                <h2 class="bebas">La plataforma</h2>
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


    <!-- ===== Precios ===== -->
    <section class="sec" id="precios">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Precios</span>
                <h2 class="bebas">Planes de licenciamiento</h2>
                <p>Elige el plan que mejor se adapte al tamaño y ritmo de tu negocio. Activa tu licencia y pon SiGesEmp a trabajar para ti.</p>
            </div>

            <div class="precios-grid">
                <article class="plan-card reveal">
                    <h3 class="plan-name">Básico</h3>
                    <p class="plan-desc">Para pequeños negocios que quieren ordenar su operación y empezar a trabajar con control.</p>
                    <div class="plan-price">
                        <span class="plan-amount">15</span>
                        <span class="plan-currency">USD</span>
                        <span class="plan-period">/mes</span>
                    </div>
                    <p class="plan-note">Licencia por equipo, con soporte inicial.</p>
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

                <article class="plan-card is-featured reveal">
                    <span class="plan-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Más elegido</span>
                    <h3 class="plan-name">Profesional</h3>
                    <p class="plan-desc">Para negocios en crecimiento que necesitan integrar varios módulos y tener más control.</p>
                    <div class="plan-price">
                        <span class="plan-amount">30</span>
                        <span class="plan-currency">USD</span>
                        <span class="plan-period">/mes</span>
                    </div>
                    <p class="plan-note">Ideal para operaciones con varios procesos.</p>
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

                <article class="plan-card reveal">
                    <h3 class="plan-name">Empresarial</h3>
                    <p class="plan-desc">Para empresas que necesitan la plataforma completa, acompañamiento y mayor capacidad de operación.</p>
                    <div class="plan-price">
                        <span class="plan-amount">50</span>
                        <span class="plan-currency">USD</span>
                        <span class="plan-period">/mes</span>
                    </div>
                    <p class="plan-note">Pensado para gestión integral y crecimiento.</p>
                    <hr class="plan-divider">
                    <ul class="plan-features">
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Todos los módulos de SiGesEmp</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Usuarios y roles según tu operación</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Integración entre módulos y reportes completos</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Asistencia en puesta en marcha</span></li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i><span>Soporte técnico y acompañamiento continuo</span></li>
                    </ul>
                    <a href="/desamedida.php" class="plan-cta plan-cta-secondary"><i class="fa-solid fa-comments" aria-hidden="true"></i> Solicitar propuesta</a>
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
                <a href="<?= $base ?>/contacto.php" class="precios-link">
                    <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                    <span><strong>Cómo contratar</strong>Habla con nuestro equipo y recibe orientación sobre el plan, la activación y la puesta en marcha.</span>
                </a>
                <a href="<?= $base ?>/contacto.php" class="precios-link">
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
            <!-- Encabezado alineado a la izquierda, como en cyberforja -->
            <div class="equipo-head reveal" data-anim="left">
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
                    <li><a href="<?= $base ?>/costos/login.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a></li>
                    <li><a href="<?= $base ?>/inventarios/login.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a></li>
                    <li><a href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a></li>
                </ul>
            </nav>

            <div>
                <h4>Contacto</h4>
                <p><a href="mailto:kakycu@gmail.com"><i class="fa-solid fa-envelope" aria-hidden="true"></i> kakycu@gmail.com</a></p>
                <p><a href="tel:+5352712861"><i class="fa-solid fa-phone" aria-hidden="true"></i> +53 5 2712861</a></p>
                <p><i class="fa-solid fa-clock" aria-hidden="true"></i> Lun - Vie, 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</p>
                <p><a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Centro de soporte</a></p>
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
                    <a href="<?= $base ?>/terminos.php"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                    <a href="<?= $base ?>/privacidad.php"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                    <a href="<?= $base ?>/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
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
            <a class="btn btn-primary" href="<?= $base ?>/nominas/login.php">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar al sistema
            </a>
            <button type="button" class="btn btn-glass" data-lic-close>Cerrar</button>
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
            <span><?= $licAviso ?></span>
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
            <button type="button" class="btn btn-glass" data-estado-lic-close>Cerrar</button>
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

    function abrir() {
        if (licOverlay) { licOverlay.hidden = true; }
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
        if (licOverlay) {
            licOverlay.hidden = false;
            document.body.classList.add('lic-abierto');
        } else {
            document.body.classList.remove('lic-abierto');
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
        window.setTimeout(function () {
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

    if (saltar) { saltar.addEventListener('click', terminar); }
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
    var progreso = document.getElementById('scrollProgress');
    if (!arriba && !abajo) { return; }

    function irArriba() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function irAbajo() {
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        window.scrollTo({ top: max, behavior: 'smooth' });
    }
    if (arriba) { arriba.addEventListener('click', irArriba); }
    if (abajo) { abajo.addEventListener('click', irAbajo); }

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
        if (e.target.closest('a')) {
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

/* Desplegable de acceso a los servicios (topbar) */
(function () {
    var cont = document.getElementById('navLogin');
    var btn = document.getElementById('navLoginBtn');
    if (!cont || !btn) { return; }
    function cerrar() {
        cont.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var abierto = cont.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!cont.contains(e.target)) { cerrar(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') { cerrar(); }
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
</body>
</html>
