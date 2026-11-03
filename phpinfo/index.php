<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>USBWebserverUnicornio · Panel de Información</title>

    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet" />
    
    <!-- Script para evitar el "parpadeo" al cargar el tema -->
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg app-navbar sticky-top py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 bg-transparent" href="#">
            <i class="fa-solid fa-server text-primary fs-4"></i>
            <span class="fw-bold theme-text mb-0">USBWebserverUnicornio</span>
            <span class="badge text-bg-primary ms-1">v10.0.1</span>
        </a>
        
        <div class="d-flex align-items-center gap-2 order-lg-last">
            <!-- Interruptor deslizante de tema (sol / luna) -->
            <button class="theme-switch" id="themeToggle" type="button" role="switch"
                    aria-checked="true" aria-label="Cambiar entre tema claro y oscuro"
                    title="Cambiar tema">
                <span class="theme-switch-track">
                    <i class="fa-solid fa-sun theme-switch-icon theme-switch-sun"></i>
                    <i class="fa-solid fa-moon theme-switch-icon theme-switch-moon"></i>
                    <span class="theme-switch-thumb"></span>
                </span>
            </button>

            <!-- Botón hamburguesa (móvil) -->
            <button class="navbar-toggler border-0 shadow-none d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        
        <div class="collapse navbar-collapse justify-content-end" id="navMenu">
            <ul class="navbar-nav gap-2 mt-3 mt-lg-0 pe-lg-3">
                <li class="nav-item">
                    <a class="nav-link theme-text" href="http://www.unicorniosoftware.gt.tc/" target="_blank">
                        <span class="nav-icon-unicorn me-2" aria-hidden="true"></span> UnicornioSoftware Cuba
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link theme-text" href="http://www.usbwebserver.com" target="_blank">
                        <i class="fa-solid fa-globe me-1"></i> USBWebserver.com
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link theme-text" href="http://www.border-it.nl" target="_blank">
                        <i class="fa-solid fa-building me-1"></i> Border-IT
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero / Header -->
<header class="app-hero text-center py-5">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3 theme-text">
            <i class="fa-solid fa-rocket text-primary me-2"></i>
            USBWebserverUnicornio V10.0.1
        </h1>
        <p class="theme-muted mb-0 fs-5">Stack local listo para desarrollo · PHP · MySQL · Apache</p>
    </div>
</header>

<!-- Contenido -->
<main class="container my-5">
    <div class="row g-4">
        <div class="col-lg-8">
            
            <?php
            // ---------------------------------------------------------
            // 1. LEER CREDENCIALES DESDE /nominas/config.php
            // ---------------------------------------------------------
            $dbHost = '127.0.0.1';
            $dbUser = 'root';
            $dbPass = '';
            $dbName = '';
            $siteVersion = 'Desconocida';
            $configEncontrado = false;

            $configPaths = [
                __DIR__ . '/../nominas/config.php',
                __DIR__ . '/../../nominas/config.php',
                'C:/USBWebserver/nominas/config.php',
                'C:/xampp/htdocs/nominas/config.php'
            ];

            foreach ($configPaths as $path) {
                if (file_exists($path)) {
                    $configContent = file_get_contents($path);
                    if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) $dbHost = $m[1];
                    if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) $dbUser = $m[1];
                    if (preg_match("/define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) $dbPass = $m[1];
                    if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) $dbName = $m[1];
                    if (preg_match("/define\s*\(\s*['\"]SITE_VERSION['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) $siteVersion = $m[1];
                    $configEncontrado = true;
                    break;
                }
            }

            // ---------------------------------------------------------
            // 2. VERSIONES DINÁMICAS (PHP, APACHE, MYSQL)
            // ---------------------------------------------------------
            $phpVersion = phpversion();
            
            $apacheVersion = 'No detectado';
            if (function_exists('apache_get_version')) {
                $apacheRaw = apache_get_version();
                if (preg_match('/Apache\/([0-9\.]+)/', $apacheRaw, $matches)) $apacheVersion = $matches[1];
            } elseif (isset($_SERVER['SERVER_SOFTWARE']) && preg_match('/([0-9\.]+)/', $_SERVER['SERVER_SOFTWARE'], $matches)) {
                $apacheVersion = $matches[1];
            }

            // Variables de conexión
            $mysqlVersion = 'No disponible';
            $dbConnected = false;
            $conn = null;
            
            if (function_exists('mysqli_connect')) {
                try {
                    $conn = mysqli_connect($dbHost, $dbUser, $dbPass);
                    if ($conn) {
                        $mysqlVersion = mysqli_get_server_info($conn);
                        $dbConnected = true;
                    }
                } catch (mysqli_sql_exception $e) {
                    $mysqlVersion = strpos($e->getMessage(), 'Access denied') !== false ? 'Acceso denegado' : 'Desconectado';
                }
            }

            // ---------------------------------------------------------
            // 3. BÚSQUEDA INTELIGENTE DE PHPMYADMIN
            // ---------------------------------------------------------
            $phpmyadminVersion = 'No detectado';
            $idiomasCount = 'N/A';
            $rutaPMAEncontrada = '';
            
            $dirActual = __DIR__;
            for ($i = 0; $i < 5; $i++) {
                if (file_exists($dirActual . '/USBWebserver.exe') || file_exists($dirActual . '/usbwebserver.exe')) {
                    $rutaPMAEncontrada = $dirActual . '/phpmyadmin';
                    break;
                }
                $dirActual = dirname($dirActual);
                if ($dirActual === '/' || $dirActual === '\\' || empty($dirActual)) break;
            }
            if (empty($rutaPMAEncontrada)) {
                $rutasComunes = [dirname(__DIR__) . '/phpmyadmin', dirname(dirname(__DIR__)) . '/phpmyadmin', 'C:/USBWebserver/phpmyadmin'];
                foreach ($rutasComunes as $ruta) {
                    if (is_dir($ruta)) { $rutaPMAEncontrada = $ruta; break; }
                }
            }
            if (!empty($rutaPMAEncontrada) && is_dir($rutaPMAEncontrada)) {
                $readmePath = $rutaPMAEncontrada . '/README';
                $composerPath = $rutaPMAEncontrada . '/composer.json';
                if (file_exists($readmePath)) {
                    $content = file_get_contents($readmePath);
                    if (preg_match('/Version ([0-9]+\.[0-9]+\.[0-9]+)/i', $content, $m)) $phpmyadminVersion = $m[1];
                } elseif (file_exists($composerPath)) {
                    $content = file_get_contents($composerPath);
                    if (preg_match('/"version":\s*"([^"]+)"/', $content, $m)) $phpmyadminVersion = $m[1];
                }
                $localePath = $rutaPMAEncontrada . '/locale';
                $langPath = $rutaPMAEncontrada . '/lang';
                if (is_dir($localePath)) $idiomasCount = count(array_diff(scandir($localePath), ['.', '..']));
                elseif (is_dir($langPath)) $idiomasCount = count(array_diff(scandir($langPath), ['.', '..']));
            }

            $arquitectura = php_uname('m');

            $componentes = [
                ['PHP', $phpVersion, 'fa-brands fa-php', 'primary'],
                ['Apache HTTPD', $apacheVersion, 'fa-solid fa-server', 'danger'],
                ['MySQL', $mysqlVersion, 'fa-solid fa-database', 'warning'],
                ['phpMyAdmin', $phpmyadminVersion, 'fa-solid fa-table-columns', 'info'],
                ['Idiomas', $idiomasCount . ' disponibles', 'fa-solid fa-language', 'success'],
                ['Arquitectura', $arquitectura, 'fa-solid fa-microchip', 'secondary'],
                ['Base de Datos', $dbConnected ? $dbName : 'No conectada', 'fa-solid fa-hard-drive', 'primary'],
                ['Versión Sistema', $siteVersion, 'fa-solid fa-code-branch', 'success'],
            ];

            // ---------------------------------------------------------
            // 4. UPTIME REAL DE MySQL
            //    Se intenta primero por SQL; si performance_schema está
            //    roto/deshabilitado, se usa el arranque real del proceso.
            // ---------------------------------------------------------
            if (!function_exists('transnubet_shell_disabled')) {
                function transnubet_shell_disabled(string $fn): bool
                {
                    $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
                    return in_array(strtolower($fn), $disabled, true);
                }
            }

            if (!function_exists('transnubet_mysql_start_from_sql')) {
                /**
                 * Obtiene el uptime (segundos) desde el propio MySQL.
                 */
                function transnubet_mysql_start_from_sql($conn): ?int
                {
                    if (!($conn instanceof mysqli)) {
                        return null;
                    }

                    $consultas = [
                        "SHOW GLOBAL STATUS LIKE 'Uptime'",
                        "SELECT VARIABLE_VALUE AS Value FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'UPTIME'",
                        "SELECT VARIABLE_VALUE AS Value FROM performance_schema.global_status WHERE VARIABLE_NAME = 'UPTIME'",
                    ];

                    foreach ($consultas as $sql) {
                        try {
                            $res = @mysqli_query($conn, $sql);
                            if (!($res instanceof mysqli_result)) {
                                continue;
                            }
                            $row = @mysqli_fetch_assoc($res);
                            @mysqli_free_result($res);
                            if (!$row) {
                                continue;
                            }

                            $valor = null;
                            foreach (['Value', 'VARIABLE_VALUE', 'Uptime', 'UPTIME'] as $clave) {
                                if (isset($row[$clave]) && $row[$clave] !== '') {
                                    $valor = $row[$clave];
                                    break;
                                }
                            }
                            if ($valor === null) {
                                $valores = array_values($row);
                                $valor = end($valores);
                            }
                            if ($valor !== null && is_numeric($valor)) {
                                return max(0, (int)$valor);
                            }
                        } catch (Throwable $e) {
                            // Esta consulta no está disponible, probar la siguiente
                        }
                    }

                    return null;
                }
            }

            if (!function_exists('transnubet_mysql_start_from_os')) {
                /**
                 * Marca de tiempo (epoch) del arranque real del proceso de MySQL.
                 */
                function transnubet_mysql_start_from_os(): ?int
                {
                    // ---- Windows: WMI (wmic) ----
                    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                        if (!transnubet_shell_disabled('shell_exec') && function_exists('shell_exec')) {
                            // wmic (rápido)
                            $wmic = @shell_exec('wmic process where "name like \'%mysqld%\'" get CreationDate /format:list 2>nul');
                            if (is_string($wmic) && preg_match_all('/(\d{14}\.\d{6}[+-]\d{3})/', $wmic, $m)) {
                                $mejor = null;
                                foreach ($m[1] as $fecha) {
                                    $ts = transnubet_parse_wmi_date($fecha);
                                    if ($ts !== null && ($mejor === null || $ts < $mejor)) {
                                        $mejor = $ts;
                                    }
                                }
                                if ($mejor !== null) {
                                    return $mejor;
                                }
                            }

                            // PowerShell (respaldo si wmic no está disponible)
                            $ps = 'powershell -NoProfile -NonInteractive -Command "Get-CimInstance Win32_Process | '
                                . 'Where-Object { $_.Name -like \'*mysqld*\' } | '
                                . 'ForEach-Object { $_.CreationDate.ToUniversalTime().ToString(\'yyyy-MM-ddTHH:mm:ssZ\') }"';
                            $salida = @shell_exec($ps);
                            if (is_string($salida) && preg_match_all('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $salida, $m2)) {
                                foreach ($m2[0] as $iso) {
                                    $ts = strtotime($iso);
                                    if ($ts !== false) {
                                        return $ts;
                                    }
                                }
                            }
                        }

                        return transnubet_mysql_start_from_pidfile();
                    }

                    // ---- Linux: /proc ----
                    if (is_dir('/proc')) {
                        $btime = null;
                        if (preg_match('/^btime\s+(\d+)/m', (string)@file_get_contents('/proc/stat'), $bm)) {
                            $btime = (int)$bm[1];
                        }
                        foreach ((array)glob('/proc/[0-9]*') as $dir) {
                            $comm = @file_get_contents($dir . '/comm');
                            if ($comm === false || stripos(trim($comm), 'mysqld') !== 0) {
                                continue;
                            }
                            $stat = @file_get_contents($dir . '/stat');
                            if ($stat === false || $btime === null) {
                                continue;
                            }
                            $pos = strrpos($stat, ')');
                            if ($pos === false) {
                                continue;
                            }
                            $campos = preg_split('/\s+/', trim(substr($stat, $pos + 1)));
                            // El campo 22 de /proc/<pid>/stat es starttime (índice 19 tras ")")
                            if (isset($campos[19]) && is_numeric($campos[19])) {
                                return $btime + (int)floor(((int)$campos[19]) / 100);
                            }
                        }

                        return transnubet_mysql_start_from_pidfile();
                    }

                    // ---- macOS / otros: ps ----
                    if (!transnubet_shell_disabled('shell_exec') && function_exists('shell_exec')) {
                        $pid = trim((string)@shell_exec('pgrep -x mysqld 2>/dev/null'));
                        if ($pid !== '' && ctype_digit($pid)) {
                            $inicio = trim((string)@shell_exec('ps -o lstart= -p ' . (int)$pid . ' 2>/dev/null'));
                            if ($inicio !== '') {
                                $ts = strtotime($inicio);
                                if ($ts !== false) {
                                    return $ts;
                                }
                            }
                        }
                    }

                    return transnubet_mysql_start_from_pidfile();
                }
            }

            if (!function_exists('transnubet_parse_wmi_date')) {
                /**
                 * Convierte "20261101205803.318779-240" (CIM_DATETIME) a epoch UTC.
                 */
                function transnubet_parse_wmi_date(string $valor): ?int
                {
                    if (!preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.\d+([+-])(\d{3})$/', $valor, $m)) {
                        return null;
                    }

                    $local = gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
                    if ($local === false) {
                        return null;
                    }

                    // El offset CIM es el desfase de la hora local respecto a UTC
                    // (p. ej. -240 = UTC-4). Por eso: UTC = local - offset.
                    $offset = ($m[7] === '+' ? 1 : -1) * ((int)$m[8] * 60);

                    return $local - $offset;
                }
            }

            if (!function_exists('transnubet_mysql_start_from_pidfile')) {
                /**
                 * Respaldo: fecha de escritura del archivo .pid de MySQL.
                 */
                function transnubet_mysql_start_from_pidfile(): ?int
                {
                    $candidatos = [
                        dirname(__DIR__, 2) . '/mysql/my.ini',
                        dirname(__DIR__) . '/mysql/my.ini',
                        'C:/Users/kakyc/Desktop/TRANSNUBET/mysql/my.ini',
                        'C:/USBWebserver/mysql/my.ini',
                        'C:/xampp/mysql/bin/my.ini',
                        '/etc/mysql/my.cnf',
                        '/etc/my.cnf',
                    ];

                    $datadirs = [];
                    foreach ($candidatos as $ini) {
                        if (!is_file($ini)) {
                            continue;
                        }
                        $contenido = (string)@file_get_contents($ini);
                        if (preg_match('/^\s*datadir\s*=\s*"?([^"\r\n]+)"?/mi', $contenido, $m)) {
                            $datadirs[] = str_replace('\\\\', '\\', trim($m[1]));
                        }
                        $datadirs[] = dirname($ini) . '/data';
                    }

                    $mejor = null;
                    foreach (array_unique($datadirs) as $dir) {
                        if (!is_dir($dir)) {
                            continue;
                        }
                        foreach ((array)glob(rtrim($dir, "/\\") . '/*.pid') as $pidFile) {
                            $ts = @filemtime($pidFile);
                            if ($ts === false) {
                                continue;
                            }
                            if ($mejor === null || $ts > $mejor) {
                                $mejor = $ts;
                            }
                        }
                    }

                    return $mejor;
                }
            }

            if (!function_exists('transnubet_mysql_uptime')) {
                /**
                 * Uptime (segundos) de MySQL: SQL -> proceso real -> .pid -> tracker local.
                 */
                function transnubet_mysql_uptime($conn): int
                {
                    $desdeSql = transnubet_mysql_start_from_sql($conn);
                    if ($desdeSql !== null) {
                        return $desdeSql;
                    }

                    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                        . 'transnubet_mysql_start_' . substr(md5(__DIR__), 0, 8) . '.json';

                    // Reutilizar el arranque detectado durante 2 minutos (evita llamar a WMI en cada carga)
                    if (is_file($cacheFile)) {
                        $data = json_decode((string)@file_get_contents($cacheFile), true);
                        if (is_array($data) && isset($data['start'], $data['checked'])) {
                            $start = (int)$data['start'];
                            if ($start > 0 && $start <= time() && (time() - (int)$data['checked']) < 120) {
                                return time() - $start;
                            }
                        }
                    }

                    $start = transnubet_mysql_start_from_os();
                    if ($start !== null && $start > 0) {
                        @file_put_contents($cacheFile, json_encode(['start' => $start, 'checked' => time()]), LOCK_EX);
                        return max(0, time() - $start);
                    }

                    // Último recurso: recordar la primera vez que se vio el servidor
                    $tracker = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                        . 'transnubet_mysql_seen_' . substr(md5(__DIR__), 0, 8) . '.json';

                    if (is_file($tracker)) {
                        $data = json_decode((string)@file_get_contents($tracker), true);
                        if (is_array($data) && isset($data['start'])) {
                            return max(0, time() - (int)$data['start']);
                        }
                    }

                    @file_put_contents($tracker, json_encode(['start' => time()]), LOCK_EX);

                    return 0;
                }
            }

            if (!function_exists('transnubet_formato_uptime')) {
                /**
                 * Formatea segundos a un texto legible (nunca "0h 0m").
                 */
                function transnubet_formato_uptime(int $segundos): string
                {
                    $segundos = max(0, $segundos);
                    $dias     = intdiv($segundos, 86400);
                    $horas    = intdiv($segundos % 86400, 3600);
                    $minutos  = intdiv($segundos % 3600, 60);

                    if ($dias > 0) {
                        return $dias . 'd ' . $horas . 'h ' . $minutos . 'm';
                    }
                    if ($horas > 0) {
                        return $horas . 'h ' . $minutos . 'm';
                    }
                    if ($minutos > 0) {
                        return $minutos . 'm';
                    }

                    return 'menos de 1 min';
                }
            }
            ?>

            <!-- Tarjeta: Componentes Instalados -->
            <div class="card app-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fa-solid fa-layer-group text-primary"></i>
                    <span class="fw-semibold theme-text">Componentes instalados (Datos Reales)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($componentes as $c): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="component-tile p-3 rounded-3 h-100">
                                    <i class="fa-solid <?= $c[2] ?> fs-4 text-<?= $c[3] ?> mb-2"></i>
                                    <div class="small theme-muted"><?= $c[0] ?></div>
                                    <div class="fw-semibold theme-text"><?= $c[1] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$configEncontrado): ?>
                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            No se encontró el archivo <code>config.php</code> de nóminas. Se usaron credenciales por defecto.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tarjeta: Salud de la Base de Datos -->
            <div class="card app-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-danger"></i>
                    <span class="fw-semibold theme-text">Salud de la Base de Datos (<?= $dbConnected ? $dbName : 'No conectada' ?>)</span>
                </div>
                <div class="card-body">
                    <?php
                    $dbSize = '0 MB';
                    $dbTables = '0';
                    $dbUptime = 'Sin datos';
                    $dbUptimeTitle = 'Servidor MySQL no disponible';
                    
                    if ($dbConnected && $conn) {
                        // 1. Tamaño de la BD
                        try {
                            $res = mysqli_query($conn, "SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = '$dbName'");
                            if ($res && $row = mysqli_fetch_assoc($res)) {
                                $dbSize = round(($row['size'] ?? 0) / (1024 * 1024), 2) . ' MB';
                            }
                        } catch (mysqli_sql_exception $e) { }

                        // 2. Contar tablas
                        try {
                            $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE table_schema = '$dbName'");
                            if ($res && $row = mysqli_fetch_assoc($res)) {
                                $dbTables = $row['total'];
                            }
                        } catch (mysqli_sql_exception $e) { }
                        
                        // 3. Uptime Real de MySQL (SQL -> arranque del proceso -> .pid)
                        $uptimeSeg = transnubet_mysql_uptime($conn);

                        $dbUptime = transnubet_formato_uptime($uptimeSeg);
                        if ($uptimeSeg >= 0) {
                            $dbUptimeTitle = 'Activo desde '
                                . date('d/m/Y H:i:s T', time() - $uptimeSeg)
                                . ' · ' . number_format($uptimeSeg) . ' segundos';
                        }
                    }
                    ?>
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="p-3 rounded-3 theme-box h-100">
                                <i class="fa-solid fa-database text-primary fs-3 mb-2"></i>
                                <h4 class="theme-text mb-0"><?= $dbSize ?></h4>
                                <small class="theme-muted">Tamaño BD</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-3 theme-box h-100">
                                <i class="fa-solid fa-table text-success fs-3 mb-2"></i>
                                <h4 class="theme-text mb-0"><?= $dbTables ?></h4>
                                <small class="theme-muted">Tablas</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-3 theme-box h-100" title="<?= htmlspecialchars($dbUptimeTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-clock text-warning fs-3 mb-2"></i>
                                <h4 class="theme-text mb-0"><?= $dbUptime ?></h4>
                                <small class="theme-muted">Uptime MySQL</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta: PHP info -->
            <div class="card app-card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-brands fa-php text-primary fs-5"></i>
                        <span class="fw-semibold theme-text">PHP <?= phpversion() ?> · phpinfo()</span>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" id="btnToggleInfo">
                        <i class="fa-solid fa-eye me-1"></i> Mostrar / Ocultar
                    </button>
                </div>
                <div class="card-body phpinfo-wrapper" id="phpinfoBox">
                    <?php
                    ob_start();
                    phpinfo();
                    $i = ob_get_contents();
                    ob_end_clean();
                    echo str_replace('module_Zend Optimizer', 'module_Zend_Optimizer', preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $i));
                    ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card app-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fa-solid fa-link text-primary"></i>
                    <span class="fw-semibold theme-text">Accesos rápidos</span>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/phpmyadmin/" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <i class="fa-solid fa-table-columns text-info fs-5"></i>
                        <div>
                            <div class="fw-semibold theme-text">phpMyAdmin</div>
                            <small class="theme-muted">Administrar MySQL</small>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-auto small theme-muted"></i>
                    </a>
                    <a href="/" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <i class="fa-solid fa-file-invoice-dollar text-success fs-5"></i>
                        <div>
                            <div class="fw-semibold theme-text">Ecosistema Financiero</div>
                            <small class="theme-muted">Ir a la aplicación</small>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-auto small theme-muted"></i>
                    </a>
                    <a href="http://www.usbwebserver.com" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <i class="fa-solid fa-globe text-primary fs-5"></i>
                        <div>
                            <div class="fw-semibold theme-text">USBWebserver.com</div>
                            <small class="theme-muted">Sitio oficial</small>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-auto small theme-muted"></i>
                    </a>
                    <a href="http://www.border-it.nl" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <i class="fa-solid fa-building text-warning fs-5"></i>
                        <div>
                            <div class="fw-semibold theme-text">Border-IT</div>
                            <small class="theme-muted">Desarrollador</small>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-auto small theme-muted"></i>
                    </a>
                    <a href="http://www.unicorniosoftware.gt.tc/" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <span class="nav-icon-unicorn text-danger fs-5" aria-hidden="true"></span>
                        <div>
                            <div class="fw-semibold theme-text">Unicornio Software Cuba</div>
                            <small class="theme-muted">Desarrollo de Software</small>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-auto small theme-muted"></i>
                    </a>
                    <div class="list-group-item d-flex align-items-center gap-3 flex-wrap" id="btnSerialGenerador">
                        <i class="fa-solid fa-key text-primary fs-5"></i>
                        <div class="me-auto pe-2">
                            <div class="fw-semibold theme-text">Serial Generador</div>
                            <small class="theme-muted">Generar y exportar licencias</small>
                        </div>
                        <div class="serial-modes" role="group" aria-label="Modos del Serial Generador">
                            <button type="button" class="serial-mode js-serial-mode" data-modo="web"
                                    title="Versión web (sg.php)">
                                <i class="fa-solid fa-globe" aria-hidden="true"></i><span>WEB</span>
                            </button>
                            <button type="button" class="serial-mode js-serial-mode" data-modo="gui"
                                    title="Aplicación de escritorio (VB6)">
                                <i class="fa-solid fa-window-maximize" aria-hidden="true"></i><span>GUI</span>
                            </button>
                            <button type="button" class="serial-mode js-serial-mode" data-modo="tui"
                                    title="Terminal (Licencia_SISGESNOM.cmd)">
                                <i class="fa-solid fa-terminal" aria-hidden="true"></i><span>TUI</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta: Rendimiento y Hardware -->
            <div class="card app-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fa-solid fa-gauge-high text-primary"></i>
                    <span class="fw-semibold theme-text">Rendimiento y Hardware</span>
                </div>
                <div class="card-body">
                    <?php
                    $os = php_uname('s') . ' ' . php_uname('r');
                    $discoTotal = round(disk_total_space("C:") / (1024 * 1024 * 1024), 2) . ' GB';
                    $discoLibre = round(disk_free_space("C:") / (1024 * 1024 * 1024), 2) . ' GB';
                    $memLimit = ini_get('memory_limit');
                    $maxUpload = ini_get('upload_max_filesize');
                    $timezone = date_default_timezone_get();
                    $tiempoCarga = round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000, 2) . ' ms';
                    ?>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex align-items-center justify-content-between py-2 border-bottom theme-border">
                            <span class="theme-text"><i class="fa-brands fa-windows text-info me-2"></i>Sistema Op.</span>
                            <span class="badge theme-badge"><?= $os ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between py-2 border-bottom theme-border">
                            <span class="theme-text"><i class="fa-solid fa-hard-drive text-warning me-2"></i>Disco (C:)</span>
                            <span class="badge theme-badge"><?= $discoLibre ?> / <?= $discoTotal ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between py-2 border-bottom theme-border">
                            <span class="theme-text"><i class="fa-solid fa-memory text-danger me-2"></i>Límite RAM PHP</span>
                            <span class="badge theme-badge"><?= $memLimit ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between py-2 border-bottom theme-border">
                            <span class="theme-text"><i class="fa-solid fa-upload text-success me-2"></i>Max Subida</span>
                            <span class="badge theme-badge"><?= $maxUpload ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between py-2 border-bottom theme-border">
                            <span class="theme-text"><i class="fa-solid fa-globe text-primary me-2"></i>Zona Horaria</span>
                            <span class="badge theme-badge"><?= $timezone ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between py-2">
                            <span class="theme-text"><i class="fa-solid fa-bolt text-warning me-2"></i>Carga del Panel</span>
                            <span class="badge theme-badge"><?= $tiempoCarga ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="app-footer text-center border-top theme-border">
    <div class="container">
        <div class="app-footer-brand small mb-2">
            <i class="fa-solid fa-server me-2"></i>
            <span class="fw-semibold theme-text">USBWebserverUnicornio</span>
            <span class="theme-muted">V10.0.1 · <?= date('Y') ?> · Ejecutándose en PHP <?= phpversion() ?></span>
        </div>
        <div class="app-footer-legal theme-muted small">
            <span>
                Copyright &copy; <?= date('Y') ?>
                <a href="http://www.unicorniosoftware.gt.tc/" target="_blank" rel="noopener">Unicornio Software Cuba</a><sup>&reg;</sup>
            </span>
            <span class="app-footer-sep">·</span>
            <span>
                by
                <a class="app-footer-mail" title="Enviar correo a Franklin Ramos Lamadrid"
                   href="mailto:unicorniosoftware_cuba@gmail.com?subject=<?= rawurlencode('Sobre USBWebServerUnicornio') ?>">
                    <i class="fa-solid fa-envelope me-1"></i>Franklin Ramos Lamadrid (Kaky&deg;)
                </a>
            </span>
            <span class="app-footer-sep">·</span>
            <span>Todos los derechos reservados</span>
        </div>
    </div>
</footer>

<!-- Botones flotantes: ir al principio / ir al final -->
<div class="scroll-fabs">
    <button type="button" class="scroll-fab" id="btnScrollTop"
            title="Ir al principio" aria-label="Ir al principio de la página">
        <i class="fa-solid fa-angles-up"></i>
    </button>
    <button type="button" class="scroll-fab" id="btnScrollBottom"
            title="Ir al final" aria-label="Ir al final de la página">
        <i class="fa-solid fa-angles-down"></i>
    </button>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script>
    // Toggle phpinfo
    document.getElementById('btnToggleInfo').addEventListener('click', function () {
        const box = document.getElementById('phpinfoBox');
        box.classList.toggle('d-none');
    });

    // Lógica del Tema Dark/Light
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;

    // Sincroniza el estado del interruptor con el tema activo
    function updateThemeSwitch(theme) {
        const esDark = theme === 'dark';
        themeToggle.setAttribute('aria-checked', esDark ? 'true' : 'false');
        themeToggle.title = esDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
    }

    // Inicializar estado del interruptor
    updateThemeSwitch(htmlElement.getAttribute('data-bs-theme'));

    // Evento del interruptor
    themeToggle.addEventListener('click', () => {
        const currentTheme = htmlElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        htmlElement.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeSwitch(newTheme);
    });

    // ---------- Footer fijo + botones flotantes ----------
    const appFooter = document.querySelector('.app-footer');
    const btnScrollTop = document.getElementById('btnScrollTop');
    const btnScrollBottom = document.getElementById('btnScrollBottom');

    // El alto real del footer (varía según el viewport) alimenta las variables CSS
    function syncFooterHeight() {
        if (!appFooter) return;
        document.documentElement.style.setProperty('--footer-h', appFooter.offsetHeight + 'px');
    }

    syncFooterHeight();
    window.addEventListener('resize', syncFooterHeight);
    window.addEventListener('orientationchange', syncFooterHeight);
    window.addEventListener('load', syncFooterHeight);
    if (window.ResizeObserver && appFooter) {
        new ResizeObserver(syncFooterHeight).observe(appFooter);
    }

    // Mostrar cada botón sólo cuando tiene sentido
    function updateScrollButtons() {
        const y = window.scrollY || document.documentElement.scrollTop || 0;
        const max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        if (btnScrollTop) btnScrollTop.classList.toggle('is-visible', y > 140);
        if (btnScrollBottom) btnScrollBottom.classList.toggle('is-visible', y < max - 140);
    }

    window.addEventListener('scroll', updateScrollButtons, { passive: true });
    window.addEventListener('resize', updateScrollButtons);
    updateScrollButtons();

    if (btnScrollTop) {
        btnScrollTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
    if (btnScrollBottom) {
        btnScrollBottom.addEventListener('click', () => window.scrollTo({
            top: document.documentElement.scrollHeight,
            behavior: 'smooth'
        }));
    }

    // Serial Generador: una sola entrada con los modos WEB | GUI | TUI
    async function lanzarSerial(modo) {
        const estilos = getComputedStyle(document.documentElement);
        const fondo = (estilos.getPropertyValue('--app-surface') || '').trim() || '#161b22';
        const color = (estilos.getPropertyValue('--app-text') || '').trim() || '#e6edf3';

        // WEB → sg.php en una pestaña nueva
        if (modo === 'web') {
            window.open('../sg.php', '_blank');
            return;
        }

        // GUI / TUI → se lanzan en el equipo y se traen al frente.
        // Tarda un par de segundos, así que primero se avisa.
        const url = 'abrir_serial.php?modo=' + modo;
        const nombre = modo === 'gui' ? 'GUI' : 'TUI';

        Swal.fire({
            toast: true,
            position: 'top-end',
            title: 'Abriendo Serial Generador (' + nombre + ')…',
            showConfirmButton: false,
            background: fondo,
            color: color,
            didOpen: () => Swal.showLoading()
        });

        try {
            const respuesta = await fetch(url + '&format=json', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const datos = await respuesta.json();

            Swal.close();
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: datos.ok ? 'success' : 'error',
                title: datos.mensaje,
                showConfirmButton: false,
                timer: datos.ok ? 3600 : 6200,
                timerProgressBar: true,
                background: fondo,
                color: color
            });
        } catch (error) {
            Swal.close();
            window.open(url, '_blank');
        }
    }

    document.querySelectorAll('.js-serial-mode').forEach((boton) => {
        boton.addEventListener('click', async () => {
            if (boton.classList.contains('is-busy')) return;
            boton.classList.add('is-busy');
            try {
                await lanzarSerial(boton.dataset.modo);
            } finally {
                setTimeout(() => boton.classList.remove('is-busy'), 900);
            }
        });
    });
</script>

<?php
// Cerrar conexión a MySQL de forma segura al finalizar
if ($dbConnected && $conn) {
    mysqli_close($conn);
}
?>
</body>
</html>