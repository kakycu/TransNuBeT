<?php
$pageStart = microtime(true);
// Explorer.php
// Ubicación: dentro de /nominas/ (junto a config.php)

require_once __DIR__ . '/nominas/config.php';

// ============================================================
//  INFORMACIÓN DEL SERVIDOR
// ============================================================

// ---------- PHP ----------
$phpVersion      = phpversion();
$phpSapi         = php_sapi_name();
$phpArchitecture = php_uname('m');
$phpIntSize      = PHP_INT_SIZE * 8 . '-bit';
$phpTimezone     = date_default_timezone_get();
$currentTime     = date('Y-m-d H:i:s');
$phpOpcache      = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$phpOpcacheOn    = $phpOpcache && !empty($phpOpcache['opcache_enabled']);

// ---------- BASE DE DATOS ----------
$dbStatus        = false;
$dbType          = 'Desconocido';
$dbVersionExact  = 'No disponible';
$dbVersion       = 'No disponible';
$dbInfo          = 'No disponible';
$dbCharset       = 'N/A';
$dbProtocol      = 'N/A';
$dbHostInfo      = 'N/A';
$dbDefaultEngine = 'N/A';
$dbClientInfo    = 'N/A';
$dbStats         = null;
$dbError         = '';
$dbName          = defined('DB_NAME') ? DB_NAME : 'No definida';
$dbUser          = defined('DB_USER') ? DB_USER : 'No definido';
$dbHost          = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbCharsetCfg    = defined('DB_CHARSET') ? DB_CHARSET : 'N/A';
$dbUptime        = 'N/A';
$dbThreadsConn   = 'N/A';
$dbMaxConn       = 'N/A';
$dbBufferPool    = 'N/A';
$dbSrvCharset    = 'N/A';
$dbSrvCollation  = 'N/A';
$dbQueryCacheType = 'N/A';
$dbQueryCacheSize = 'N/A';

try {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $dbError = $conn->connect_error;
    } else {
        $dbStatus   = true;
        $dbVersion  = $conn->server_info;
        $dbInfo     = $conn->get_server_info();
        $dbCharset  = $conn->character_set_name();
        $dbProtocol = $conn->protocol_version;
        $dbHostInfo = $conn->host_info;
        $dbClientInfo = mysqli_get_client_info();

        if (stripos($dbInfo, 'mariadb') !== false || stripos($dbVersion, 'mariadb') !== false) {
            $dbType = 'MariaDB';
        } else {
            $dbType = 'MySQL';
        }

        $dbCheck = function ($q) use ($conn) {
            try {
                $r = $conn->query($q);
                if (!$r) return null;
                $row = $r->fetch_assoc();
                $r->free();
                return $row;
            } catch (Throwable $e) {
                return null;
            }
        };

        // Versión exacta (ej: 11.4.13-MariaDB-log)
        $row = $dbCheck("SELECT VERSION() AS v");
        if ($row) {
            $dbVersionExact = $row['v'];
        }

        // Comentario de versión
        $row = $dbCheck("SELECT @@version_comment AS v");
        if ($row && stripos($row['v'], 'mariadb') !== false) {
            $dbType = 'MariaDB';
        }

        // Engine por defecto
        $row = $dbCheck("SELECT @@default_storage_engine AS v");
        if ($row) $dbDefaultEngine = $row['v'];

        // Uptime del servidor MySQL
        $row = $dbCheck("SHOW GLOBAL STATUS LIKE 'Uptime'");
        if ($row && isset($row['Value'])) {
            $dbUptime = secsToDur((int)$row['Value']);
        } else {
            $dbUptime = processUptime(['mysqld_usbwv8.exe', 'mysqld.exe', 'mysqld-nt.exe']);
        }

        // Conexiones activas vs máximo
        $row = $dbCheck("SELECT COUNT(*) AS n FROM information_schema.processlist");
        if ($row) $dbThreadsConn = $row['n'];
        $row = $dbCheck("SELECT @@global.max_connections AS v");
        if ($row) $dbMaxConn = $row['v'];

        // InnoDB buffer pool
        $row = $dbCheck("SELECT @@global.innodb_buffer_pool_size AS v");
        if ($row) $dbBufferPool = fmtBytes((float)$row['v']);

        // Charset y collation del servidor
        $row = $dbCheck("SELECT @@global.character_set_server AS cs, @@global.collation_server AS co");
        if ($row) {
            $dbSrvCharset   = $row['cs'];
            $dbSrvCollation = $row['co'];
        }

        // Query cache
        $row = $dbCheck("SELECT @@global.query_cache_type AS qt, @@global.query_cache_size AS qs");
        if ($row) {
            $dbQueryCacheType = strtoupper((string)$row['qt']);
            $dbQueryCacheSize = fmtBytes((float)$row['qs']);
        }

        // Estadísticas de la BD
        $sql = "SELECT
            (SELECT COUNT(*) FROM information_schema.tables  WHERE table_schema = '" . $conn->real_escape_string(DB_NAME) . "') AS total_tables,
            (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '" . $conn->real_escape_string(DB_NAME) . "') AS total_columns,
            (SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                FROM information_schema.tables
                WHERE table_schema = '" . $conn->real_escape_string(DB_NAME) . "') AS db_size_mb";
        $row = $dbCheck($sql);
        if ($row) $dbStats = $row;

        $conn->close();
    }
} catch (Exception $e) {
    $dbStatus = false;
    $dbError  = $e->getMessage();
}

// ---------- SERVIDOR WEB ----------
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido';
$serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'Desconocido';
$serverName     = $_SERVER['SERVER_NAME']     ?? 'localhost';
$serverPort     = $_SERVER['SERVER_PORT']     ?? '80';
$serverAddr     = $_SERVER['SERVER_ADDR']     ?? 'Desconocido';
$clientIP       = $_SERVER['REMOTE_ADDR']     ?? 'Desconocido';
$documentRoot   = $_SERVER['DOCUMENT_ROOT']   ?? 'Desconocido';
$scriptName     = $_SERVER['SCRIPT_NAME']     ?? 'Desconocido';
$https          = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Sí' : 'No';

$webServerType = 'Desconocido';
if (stripos($serverSoftware, 'apache') !== false)        $webServerType = 'Apache';
elseif (stripos($serverSoftware, 'nginx') !== false)     $webServerType = 'Nginx';
elseif (stripos($serverSoftware, 'litespeed') !== false) $webServerType = 'LiteSpeed';
elseif (stripos($serverSoftware, 'iis') !== false)       $webServerType = 'IIS';

$apacheModules = [];
$apacheVersion = 'N/A';
$apacheMPM     = 'N/A';

if ($webServerType === 'Apache' && function_exists('apache_get_modules')) {
    $apacheModules = apache_get_modules();
    sort($apacheModules);
}
if ($webServerType === 'Apache' && function_exists('apache_get_version')) {
    $apacheVersion = apache_get_version();
}
if ($webServerType === 'Apache') {
    if (preg_match('/Apache\/[\d\.]+/', $serverSoftware, $m)) {
        $apacheVersion = $m[0];
    }
    foreach (['prefork', 'worker', 'event'] as $mpm) {
        if (stripos($serverSoftware, $mpm) !== false) {
            $apacheMPM = $mpm;
            break;
        }
    }
}

// ---------- SISTEMA OPERATIVO ----------
$osName    = php_uname('s');
$osRelease = php_uname('r');
$osVersion = php_uname('v');
$osArch    = php_uname('m');
$hostname  = php_uname('n');

// ---------- UPTIME, LOAD, CPU, MEMORIA, DISCO ----------
$uptimeStr  = 'N/A';
$loadAvgStr = 'N/A';
$cpuModel   = '';
$cpuCores   = '';
$memTotal   = '';
$memFree    = '';
$diskTotal  = '';
$diskFree   = '';

if (@is_readable('/proc/uptime')) {
    $up = @file_get_contents('/proc/uptime');
    if ($up !== false) {
        $secs = (int)explode(' ', trim($up))[0];
        $days = floor($secs / 86400);
        $hrs  = floor(($secs % 86400) / 3600);
        $mins = floor(($secs % 3600) / 60);
        $uptimeStr = "{$days}d {$hrs}h {$mins}m";
    }
}
if (function_exists('sys_getloadavg')) {
    $la = @sys_getloadavg();
    if (is_array($la)) {
        $loadAvgStr = sprintf('%.2f, %.2f, %.2f', $la[0], $la[1], $la[2]);
    }
}
if (@is_readable('/proc/cpuinfo')) {
    $cpuinfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuinfo !== false) {
        if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $m)) {
            $cpuModel = trim($m[1]);
        }
        $cpuCores = substr_count($cpuinfo, 'processor');
        if ($cpuCores <= 0) $cpuCores = '';
    }
}
if (@is_readable('/proc/meminfo')) {
    $mem = @file_get_contents('/proc/meminfo');
    if ($mem !== false) {
        if (preg_match('/MemTotal:\s*(\d+)\s*kB/i', $mem, $m)) {
            $memTotal = round($m[1] / 1024 / 1024, 2) . ' GB';
        }
        if (preg_match('/MemAvailable:\s*(\d+)\s*kB/i', $mem, $m)) {
            $memFree = round($m[1] / 1024 / 1024, 2) . ' GB';
        } elseif (preg_match('/MemFree:\s*(\d+)\s*kB/i', $mem, $m)) {
            $memFree = round($m[1] / 1024 / 1024, 2) . ' GB';
        }
    }
}
if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
    $root = (DIRECTORY_SEPARATOR === '\\') ? 'C:\\' : '/';
    $dt = @disk_total_space($root);
    $df = @disk_free_space($root);
    if ($dt !== false) $diskTotal = round($dt / 1024 / 1024 / 1024, 2) . ' GB';
    if ($df !== false) $diskFree  = round($df / 1024 / 1024 / 1024, 2) . ' GB';
}

// ---------- LÍMITES PHP ----------
$memoryLimit       = ini_get('memory_limit');
$maxExecutionTime  = ini_get('max_execution_time');
$maxInputTime      = ini_get('max_input_time');
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize       = ini_get('post_max_size');
$maxInputVars      = ini_get('max_input_vars');
$maxFileUploads    = ini_get('max_file_uploads');
$displayErrors     = ini_get('display_errors') ? 'On' : 'Off';

// ---------- EXTENSIONES ----------
$loadedExtensions = get_loaded_extensions();
sort($loadedExtensions);
$extensionsCount  = count($loadedExtensions);

$hasMySQLi   = extension_loaded('mysqli');
$hasPDO      = extension_loaded('pdo');
$hasPDOMySQL = extension_loaded('pdo_mysql');
$hasGD       = extension_loaded('gd');
$hasCurl     = extension_loaded('curl');
$hasJSON     = extension_loaded('json');
$hasMBString = extension_loaded('mbstring');
$hasXML      = extension_loaded('xml');
$hasZip      = extension_loaded('zip');
$hasOpenSSL  = extension_loaded('openssl');
$hasSession  = extension_loaded('session');
$hasFileInfo = extension_loaded('fileinfo');
$hasIntl     = extension_loaded('intl');
$hasSodium   = extension_loaded('sodium');
$hasBcMath   = extension_loaded('bcmath');

// ---------- HELPERS ----------
function statusBadge($status) {
    return $status
        ? '<span class="tag" style="background: rgba(30, 215, 96, 0.2); color: #1ed760;">✓ Activo</span>'
        : '<span class="tag" style="background: rgba(232, 17, 35, 0.2); color: #e81123;">✗ Inactivo</span>';
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fmtBytes($b, $dec = 2) {
    if (!is_numeric($b) || $b < 0) return 'N/A';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
    return round($b, $dec) . ' ' . $units[$i];
}
function secsToDur($secs) {
    if (!is_numeric($secs) || $secs < 0) return 'N/A';
    $secs = (int)$secs;
    $d = floor($secs / 86400);
    $h = floor(($secs % 86400) / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    return ($d ? $d . 'd ' : '') . $h . 'h ' . $m . 'm ' . $s . 's';
}
function processUptime($names) {
    if (DIRECTORY_SEPARATOR !== '\\') return 'N/A';
    if (!function_exists('shell_exec')) return 'N/A';
    if (!is_array($names)) $names = [$names];
    $oldest = null;
    foreach ($names as $name) {
        $out = @shell_exec('wmic process where "Name=\'' . $name . '\'" get CreationDate /format:list 2>&1');
        if (!$out) continue;
        if (preg_match('/CreationDate=(\d{14})/', $out, $m)) {
            $ts = DateTime::createFromFormat('YmdHis', $m[1]);
            if ($ts && (!$oldest || $ts < $oldest)) $oldest = $ts;
        }
    }
    return $oldest ? secsToDur(time() - $oldest->getTimestamp()) : 'N/A';
}

// ---------- HTTP / RESPONSE ----------
if (function_exists('apache_response_headers')) {
    $respHeaders = @apache_response_headers();
} else {
    $respHeaders = [];
    foreach (headers_list() as $h) {
        $p = explode(':', $h, 2);
        if (isset($p[1])) $respHeaders[trim($p[0])] = trim($p[1]);
    }
}
$respHeaders     = is_array($respHeaders) ? $respHeaders : [];
$headerXPowered  = $respHeaders['X-Powered-By'] ?? 'No definido';
$headerCache     = $respHeaders['Cache-Control'] ?? 'No definido';
$headerCSP       = $respHeaders['Content-Security-Policy'] ?? 'No definido';
$headerXFrame    = $respHeaders['X-Frame-Options'] ?? 'No definido';
$headerXCTO      = $respHeaders['X-Content-Type-Options'] ?? 'No definido';
$opensslVersion  = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N/A';
$tlsProtocol     = $_SERVER['SSL_PROTOCOL'] ?? 'N/D (HTTP)';
$tlsCipher       = $_SERVER['SSL_CIPHER'] ?? 'N/D (HTTP)';

// ---------- RENDIMIENTO ----------
$pageTime      = microtime(true) - $pageStart;
$peakMem       = memory_get_peak_usage(true);
$apacheUptime  = processUptime(['httpd_usbwv8.exe', 'httpd.exe']);
$apacheRequests = 'N/D (requiere mod_status expuesto)';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorador de Portafolio Profesional</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link href="css/font-awesome6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/animate.min.css">
    <link rel="icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAAAlwSFlzAAAOwwAADsMBx2+oZAAAAq5JREFUWEe9l1uITVEYxzEIkVvkwS2SXMaDIkkUDy41jIaSoYYHPMyD4gl5QJTyIjOJCM00ETUpRYrcEmaalHJpkjDy4JZS5DJ+/9Nep9W256xv7U5W/Tp7rfX/vvWtvdflOxW98pcFmL6C+fAROvO7yme5GLPv0J3Qxu/GfK7irCYhPw7vvMFdEPq9BOPjXNrVC5G+6WFgP4inaGbY3dqU45C9NgzuAmlHO8Tm2qbaFzG4C6LW5tqmup0jgHM212FVHySPvAC+8vzFENC1sGubogJZBzTDEpgOU2Ee1MFpeAzfUkFdtrm3qbSo1pWQDqBvJiyFajgFrTbXYVVfJC/hYFhaVFzg6UaEvqR0RzIbvX5L0Zo5DFo3yywGIc1JBHNCooz+XbRtz2H3j8kGWg5FOhqE/jpMi7TLlA+j9RZo9VvL7uQzWPVBXR0K7WttyVDRFr0DI0LCmP7eiFthp8HoPJr1Bl20RNfsQ5hdwnIrfS3RniMMlIjoXqjMsKmh7S6MjfCXS1qN1U3YD1WwBpSkaI2UPQ/wIxxKZWTSMCFZDzojGmEzuPt/cq5plTCaQt8WeAJNAefD6X8OV0H5gM6BwbEBaQa6eI7BPfgMLsn4zXN9Dw770X7W08qmC6xHeOF61RZSmu3neFnPZ9AsAn0KJapaCzr5fO0n6qtAW9iVtTw0ZL2VTTSm7/NQEOp/Dx8yAv5B2/LUmxpDXZ9SeuUXxWN6LpVfhllbAnKa9NU9EP8PQFf0argP21yAynRinIe0b/E3KjX7I94Y+k+hdaGgCmf7izIHcCI1uGb8xxtD6f1Ep9He1mIJzSqmX9e3K/o/4fv/SV2nabHoDVwpcwD+4luJ76PwLBljb+rtFKo6PGQkcTkY7Q2i3TUL9sBFUH75X8sBRtOdsQL6p0f+Cwu+KxTDlSVmAAAAAElFTkSuQmCC" type="image/x-icon">
    <style>
    body {
        font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0;
        background-color: transparent;
        color: white;
        height: 100vh;
        overflow: hidden;
        background: url('images/fondoautor.jpg') no-repeat center center fixed;
        background-size: cover;
    }

    .content-header { border-bottom: 1px solid white; }
    .content-header h1 { margin: 0; font-size: 24px; font-weight: 600; }

    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    :root {
        --accent-color: #0078d7;
        --bg-dark: #1e1e1e;
        --bg-darker: #686868;
        --border-color: #333;
        --mica-bg: rgba(30,30,30,0.7);
        --mica-border: rgba(255,255,255,0.1);
    }

    .title-bar {
        height: 48px; display: flex; align-items: center; justify-content: space-between;
        padding: 0 12px; border-bottom: 1px solid var(--mica-border);
        user-select: none; -webkit-user-select: none;
    }

    .explorer-window {
        position: absolute; width: 80vw; height: 80vh; top: 10vh; left: 10vw;
        display: flex; flex-direction: column;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        border-radius: 8px; overflow: hidden; transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        background-color: rgba(30,30,30,0.85);
        background-image: linear-gradient(to bottom, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 100%);
    }

    .top-bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 12px; user-select: none; -webkit-user-select: none;
        backdrop-filter: blur(15px);
        background-color: rgba(30,30,30,0.6);
        transition: backdrop-filter 0.3s ease, background-color 0.3s ease;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    #top-bar:hover { background-color: rgba(30,30,30,0.7); cursor: move; }
    .top-bar.double-click-active { animation: doubleClickEffect 0.4s ease; }
    @keyframes doubleClickEffect {
        0% { background-color: rgba(255,255,255,0); }
        50% { background-color: rgba(255,255,255,0.1); }
        100% { background-color: rgba(255,255,255,0); }
    }

    .window-title { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; }

    .window-controls button {
        background: none; border: none; color: white; width: 32px; height: 32px;
        border-radius: 4px; cursor: pointer; transition: all 0.2s ease;
    }
    .window-controls button:hover { background-color: rgba(255,255,255,0.1); }
    .window-controls button.close:hover { background-color: #e81123; }

    .fa-solid, .fas, .window-controls i, .menu-button i {
        font-family: 'Font Awesome 6 Free' !important;
        font-weight: 900 !important;
        display: inline-block !important;
        font-style: normal !important;
        font-size: 12px;
    }

    .tabs {
        display: flex; background-color: var(--mica-bg);
        border-bottom: 1px solid var(--mica-border);
        backdrop-filter: blur(10px);
    }
    .tab {
        padding: 10px 16px; cursor: pointer;
        border-right: 1px solid var(--mica-border);
        display: flex; align-items: center; gap: 8px;
        color: #ccc; font-size: 14px; transition: all 0.3s ease;
    }
    .tab.active { background-color: var(--bg-darker); color: white; font-weight: 500; }
    .tab:hover { background-color: #333; }

    .content { flex: 1; display: flex; overflow: hidden; }

    .sidebar {
        width: 220px; background-color: var(--mica-bg);
        border-right: 1px solid var(--mica-border);
        padding: 12px 0; overflow-y: auto; backdrop-filter: blur(10px);
    }
    .sidebar-section { margin-bottom: 16px; }
    .sidebar-title { padding: 8px 16px; font-size: 12px; color: #999; text-transform: uppercase; }
    .sidebar-item {
        padding: 8px 16px 8px 24px; cursor: pointer; color: #ccc;
        display: flex; align-items: center; gap: 8px;
        transition: all 0.3s ease; border-left: 3px solid transparent;
    }
    .sidebar-item:hover { background-color: #333; color: white; }
    .sidebar-item.active {
        background-color: rgba(0,120,215,0.3);
        border-left-color: var(--accent-color);
        color: var(--accent-color);
        padding-left: 21px;
    }

    .main-area { flex: 1; padding: 16px; overflow-y: auto; background-color: var(--bg-darker); }

    .content-section { display: none; animation: fadeIn 0.3s ease; }
    .content-section.active { display: block; }

    .project-card {
        background-color: rgba(30,30,30,0.6);
        border-radius: 6px; padding: 16px; margin-bottom: 16px;
        transition: all 0.3s ease; cursor: pointer;
        border-left: 3px solid transparent;
        border: 1px solid var(--mica-border);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .project-card:hover {
        transform: translateY(-2px);
        background-color: rgba(255,255,255,0.08);
        border-left-color: var(--accent-color);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .project-card h3 { margin-top: 0; color: var(--accent-color); }

    .project-card .tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .project-card .tag {
        background-color: rgba(0,120,215,0.2);
        color: var(--accent-color);
        padding: 4px 8px; border-radius: 4px; font-size: 12px;
    }
    #web .tag { background-color: rgba(66,135,245,0.2); color: #4287f5; }
    #design .tag { background-color: rgba(220,20,140,0.2); color: #dc148c; }
    #apps .tag { background-color: rgba(30,215,96,0.2); color: #1ed760; }

    .context-menu {
        position: absolute; background-color: var(--mica-bg);
        backdrop-filter: blur(20px); color: white; border-radius: 6px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        display: none; z-index: 1000; padding: 6px 0; min-width: 180px;
        border: 1px solid rgba(255,255,255,0.1);
        animation: fadeIn 0.15s ease-out;
    }
    .context-menu ul { list-style: none; padding: 0; margin: 0; }
    .context-menu li {
        padding: 8px 16px; cursor: pointer; display: flex;
        align-items: center; gap: 10px; font-size: 14px;
        transition: all 0.2s ease;
    }
    .context-menu li:hover { background-color: rgba(0,120,215,0.3); }
    .context-menu li.separator {
        height: 1px; background-color: rgba(255,255,255,0.1);
        margin: 6px 0; padding: 0; cursor: default;
    }

    .status-bar {
        background-color: var(--mica-bg); padding: 6px 16px;
        font-size: 12px; color: #ccc;
        border-top: 1px solid var(--mica-border);
        display: flex; align-items: center; gap: 8px;
        backdrop-filter: blur(10px);
    }
    .status-icon { color: var(--accent-color); font-size: 12px; margin-right: 5px; }
    #status-text { flex-grow: 1; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .explorer-window.minimized .content,
    .explorer-window.minimized .tabs,
    .explorer-window.minimized .status-bar { display: none; }

    .explorer-window::after {
        content: ''; position: absolute; bottom: 0; right: 0;
        width: 12px; height: 12px; background-color: transparent;
        cursor: nwse-resize; z-index: 100;
    }
    .explorer-window::before {
        content: ''; position: absolute; bottom: 0; left: 0;
        width: 12px; height: 12px; background-color: transparent;
        cursor: nesw-resize; z-index: 100;
    }
    .explorer-window .resize-handle-bottom {
        position: absolute; bottom: 0; left: 12px; right: 12px;
        height: 12px; background-color: transparent; cursor: ns-resize; z-index: 100;
    }
    .explorer-window .resize-handle-right {
        position: absolute; right: 0; top: 12px; bottom: 12px;
        width: 12px; background-color: transparent; cursor: ew-resize; z-index: 100;
    }

    .main-area, .sidebar { background-color: transparent; }

    .icon-container { text-align: center; display: flex; flex-direction: column; gap: 20px; }
    .icon-container a {
        text-decoration: none; color: #fff;
        display: inline-flex; align-items: center; gap: 10px;
        font-size: 18px; transition: color 0.2s ease-in-out;
    }
    .icon-container a:hover { color: #6e5494; }
    .icon-container a:hover .fa-github   { color: #6e5494; }
    .icon-container a:hover .fa-linkedin { color: #0077B5; }
    .icon-container a:hover .fa-facebook { color: #1877F2; }
    .icon-container i { font-size: 14px; }

    /* ====== SERVIDOR ====== */
    .server-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }
    .server-info-grid .project-card { margin-bottom: 0; }
    .server-info-grid .project-card h3 {
        margin-top: 0; margin-bottom: 12px;
        color: var(--accent-color); font-size: 14px;
        display: flex; align-items: center; gap: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .info-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
        font-size: 13px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: #999; flex-shrink: 0; margin-right: 10px; }
    .info-value {
        color: #fff; font-weight: 500; text-align: right;
        word-break: break-word; max-width: 65%;
    }
    .info-value.highlight { color: #1ed760; }
    .info-value.warning   { color: #ffc107; }
    .info-value.danger    { color: #e81123; }

    .extension-badge {
        display: inline-block; background: rgba(0,120,215,0.2);
        color: #0078d7; padding: 3px 8px; border-radius: 4px;
        font-size: 11px; margin: 2px;
    }
    .db-status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 20px; font-size: 13px;
        font-weight: 600; margin-bottom: 12px;
    }
    .db-status-badge.online {
        background: rgba(30,215,96,0.15); color: #1ed760;
        border: 1px solid rgba(30,215,96,0.3);
    }
    .db-status-badge.offline {
        background: rgba(232,17,35,0.15); color: #e81123;
        border: 1px solid rgba(232,17,35,0.3);
    }

    /* Hero de MariaDB/MySQL destacado */
    .db-hero {
        text-align: center;
        padding: 18px 10px 12px;
        border-radius: 8px;
        background: linear-gradient(135deg, rgba(0,120,215,0.15), rgba(30,215,96,0.10));
        border: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 14px;
    }
    .db-hero .db-hero-label {
        font-size: 12px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #9ad6ff;
        margin-bottom: 4px;
    }
    .db-hero .db-hero-name {
        font-size: 22px;
        font-weight: 700;
        color: #ffffff;
        line-height: 1.15;
        word-break: break-word;
    }
    .db-hero .db-hero-version {
        font-size: 15px;
        font-weight: 600;
        color: #1ed760;
        margin-top: 4px;
        word-break: break-word;
    }
    </style>
</head>
<body>
    <div class="explorer-window" id="explorer-window">
        <div class="resize-handle-bottom"></div>
        <div class="resize-handle-right"></div>

        <!-- Barra superior -->
        <div class="title-bar mica" id="top-bar">
            <div class="window-title">
                <button class="menu-button" id="menu-button" style="background: none; border: none; color: inherit; cursor: pointer;">
                    <i class="fas fa-folder-open"></i>
                </button>
                <span>Explorador de Portafolio Personal:</span><span style="font-weight:bold;"> Franklin Ramos Lamadrid (Kaky°)</span>
            </div>

            <div class="window-controls">
                <button id="minimize-button"><i class="fa-solid fa-window-minimize"></i></button>
                <button id="maximize-button"><i class="fa-solid fa-window-maximize"></i></button>
                <button class="close" id="close-button" onclick="closeWindows();"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>

        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab active" data-content="home"><i class="fa-solid fa-house"></i> Inicio</div>
            <div class="tab" data-content="projects"><i class="fa-solid fa-folder-open"></i> Proyectos</div>
            <div class="tab" data-content="skills"><i class="fa-solid fa-code"></i> Habilidades</div>
            <div class="tab" data-content="server"><i class="fa-solid fa-server"></i> Servidor</div>
            <div class="tab" data-content="contact"><i class="fa-solid fa-envelope"></i> Contacto</div>
        </div>

        <!-- Contenido -->
        <div class="content">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-section">
                    <div class="sidebar-title">Navegación</div>
                    <div class="sidebar-item active" data-content="home"><i class="fa-solid fa-house"></i> Inicio</div>
                    <div class="sidebar-item" data-content="projects"><i class="fa-solid fa-folder-open"></i> Proyectos</div>
                    <div class="sidebar-item" data-content="skills"><i class="fa-solid fa-code"></i> Habilidades</div>
                    <div class="sidebar-item" data-content="server"><i class="fa-solid fa-server"></i> Servidor</div>
                    <div class="sidebar-item" data-content="contact"><i class="fa-solid fa-envelope"></i> Contacto</div>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-title">Categorías</div>
                    <div class="sidebar-item" data-content="web"><i class="fa-solid fa-globe"></i> Desarrollo Web</div>
                    <div class="sidebar-item" data-content="design"><i class="fa-solid fa-paint-brush"></i> Diseño UI/UX</div>
                    <div class="sidebar-item" data-content="apps"><i class="fa-solid fa-mobile-screen"></i> Aplicaciones</div>
                </div>
            </div>

            <!-- Área principal -->
            <div class="main-area" id="main-area">

                <!-- ================= INICIO ================= -->
                <div id="home" class="content-section active">
                    <div class="content-header"><h1>Bienvenido a mi Portafolio</h1></div>
                    <div class="content-body" style="display: flex; align-items: flex-start; gap: 20px;">
                        <div class="profile-photo" style="width:150px;height:150px;border-radius:50%;overflow:hidden;box-shadow:0 4px 8px rgba(0,0,0,0.2);background:linear-gradient(135deg,#0078D7,#00C6FF);display:flex;justify-content:center;align-items:center;">
                            <img src="images/autor.png" alt="Foto de Perfil" style="width:140px;height:140px;border-radius:50%;object-fit:cover;border:3px solid #fff;">
                        </div>
                        <div class="content-text" style="flex:1;">
                            <p>Soy un desarrollador full-stack con amplia experiencia en el diseño y desarrollo de interfaces de usuario, así como en la implementación de arquitecturas escalables y eficientes para sistemas empresariales. Mi enfoque principal es crear soluciones tecnológicas que no solo sean funcionales, sino también intuitivas y atractivas para los usuarios finales.</p>
                            <p>Entre mis proyectos destacados se encuentra el <strong>Sistema de Gestión Empresarial</strong>, una plataforma integral diseñada para optimizar la gestión de clientes, inventarios y facturación. Este sistema combina tecnologías modernas como <strong>React</strong> para el frontend, <strong>Node.js</strong> para el backend y <strong>MongoDB</strong> como base de datos, lo que permite una experiencia de usuario fluida y un rendimiento óptimo en entornos empresariales.</p>

                            <h2>Proyectos Destacados</h2>
                            <div class="project-card">
                                <h3>Calculadora de Consumo Energético</h3>
                                <p>Aplicación web para calcular el consumo eléctrico en el hogar, con gráficos y recomendaciones de ahorro.</p>
                                <div class="tags"><span class="tag">React</span><span class="tag">Node.js</span><span class="tag">Chart.js</span></div>
                            </div>
                            <div class="project-card">
                                <h3>Plataforma de E-learning</h3>
                                <p>Sistema para crear y tomar cursos en línea con videos, cuestionarios y certificados.</p>
                                <div class="tags"><span class="tag">Django</span><span class="tag">Python</span><span class="tag">PostgreSQL</span></div>
                            </div>
                            <div class="project-card">
                                <h3>App de Gestión de Inventarios</h3>
                                <p>Aplicación móvil para gestionar inventarios de productos, entradas y salidas.</p>
                                <div class="tags"><span class="tag">React Native</span><span class="tag">Firebase</span><span class="tag">Redux</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= PROYECTOS ================= -->
                <div id="projects" class="content-section">
                    <div class="content-header"><h1>Proyectos Destacados</h1></div>
                    <br>
                    <div class="project-card">
                        <h3>Sistema de Control de Epidemias</h3>
                        <p>Aplicación para monitorear y controlar brotes epidémicos, con seguimiento de casos y generación de informes.</p>
                        <div class="tags"><span class="tag">Visual Basic</span><span class="tag">MySQL</span><span class="tag">Salud Pública</span><span class="tag">Epidemiología</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Sistema de Gestión de Registros Médicos en Nuevitas</h3>
                        <p>Aplicación para gestionar registros médicos, historias clínicas y seguimiento de pacientes en el área de Salud Pública de Nuevitas.</p>
                        <div class="tags"><span class="tag">Visual Basic</span><span class="tag">MySQL</span><span class="tag">Salud Pública</span><span class="tag">Nuevitas</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Sistema de Gestión de Vacunación</h3>
                        <p>Aplicación para gestionar el registro de vacunación, seguimiento de dosis y generación de certificados de vacunación.</p>
                        <div class="tags"><span class="tag">Visual Basic</span><span class="tag">MySQL</span><span class="tag">Salud Pública</span><span class="tag">Vacunación</span></div>
                    </div>
                    <div class="project-card">
                        <h3>E-commerce Moderno</h3>
                        <p>Tienda online con carrito de compras, pasarela de pagos y panel administrativo.</p>
                        <div class="tags"><span class="tag">Vue.js</span><span class="tag">Laravel</span><span class="tag">MySQL</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Aplicación de Monitoreo</h3>
                        <p>Sistema en tiempo real para monitoreo de servidores y aplicaciones.</p>
                        <div class="tags"><span class="tag">Python</span><span class="tag">WebSockets</span><span class="tag">Docker</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Aplicación de Gestión de Tareas</h3>
                        <p>Aplicación web para gestionar tareas diarias con recordatorios y seguimiento.</p>
                        <div class="tags"><span class="tag">React</span><span class="tag">Node.js</span><span class="tag">MongoDB</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Plataforma de Blogging</h3>
                        <p>Plataforma de blogs con editor WYSIWYG, autenticación y comentarios.</p>
                        <div class="tags"><span class="tag">Next.js</span><span class="tag">Firebase</span><span class="tag">Tailwind CSS</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Sistema de Reservas de Hotel</h3>
                        <p>Sistema para reservar habitaciones de hotel con calendario y pagos en línea.</p>
                        <div class="tags"><span class="tag">Angular</span><span class="tag">Express.js</span><span class="tag">PostgreSQL</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Aplicación de Clima en Tiempo Real</h3>
                        <p>Aplicación móvil para mostrar el clima actual y pronósticos semanales.</p>
                        <div class="tags"><span class="tag">React Native</span><span class="tag">Expo</span><span class="tag">OpenWeather API</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Plataforma de Cursos en Línea</h3>
                        <p>Plataforma para crear y tomar cursos con videos, cuestionarios y certificados.</p>
                        <div class="tags"><span class="tag">Django</span><span class="tag">Python</span><span class="tag">SQLite</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Sistema de Facturación</h3>
                        <p>Sistema para generar facturas, gestionar clientes y productos, y exportar a PDF.</p>
                        <div class="tags"><span class="tag">Java</span><span class="tag">Spring Boot</span><span class="tag">MySQL</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Aplicación de Red Social</h3>
                        <p>Red social con perfiles de usuario, publicaciones, likes y comentarios.</p>
                        <div class="tags"><span class="tag">Flutter</span><span class="tag">Firebase</span><span class="tag">Dart</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Plataforma de Streaming de Video</h3>
                        <p>Plataforma para subir y ver videos con categorías y reproducción en tiempo real.</p>
                        <div class="tags"><span class="tag">Vue.js</span><span class="tag">AWS S3</span><span class="tag">Redis</span></div>
                    </div>
                    <div class="project-card">
                        <h3>Sistema de Gestión de Inventarios</h3>
                        <p>Aplicación para gestionar inventarios de productos, entradas y salidas.</p>
                        <div class="tags"><span class="tag">PHP</span><span class="tag">Laravel</span><span class="tag">PostgreSQL</span></div>
                    </div>
                </div>

                <!-- ================= HABILIDADES ================= -->
                <div id="skills" class="content-section">
                    <div class="content-header"><h1>Habilidades Técnicas</h1></div>
                    <br>
                    <div class="project-card"><h3>Frontend</h3><p>React, Vue.js, Angular, HTML5, CSS3, JavaScript, TypeScript</p></div>
                    <div class="project-card"><h3>Backend</h3><p>Node.js, Python, PHP, Java, REST APIs, GraphQL</p></div>
                    <div class="project-card"><h3>Diseño</h3><p>UI/UX, Figma, Adobe XD, Photoshop, Illustrator</p></div>
                    <div class="project-card"><h3>Desarrollo Móvil</h3><p>React Native, Flutter, Swift, Kotlin, Android Studio, Xcode</p></div>
                    <div class="project-card"><h3>Bases de Datos</h3><p>MySQL, PostgreSQL, MongoDB, Redis, Oracle, SQL Server</p></div>
                    <div class="project-card"><h3>DevOps</h3><p>Docker, Kubernetes, Jenkins, Terraform, AWS, Azure</p></div>
                    <div class="project-card"><h3>Seguridad Informática</h3><p>OWASP, Kali Linux, Metasploit, Nmap, Wireshark, Burp Suite</p></div>
                    <div class="project-card"><h3>Machine Learning</h3><p>Python, TensorFlow, PyTorch, Scikit-learn, Keras, Pandas</p></div>
                    <div class="project-card"><h3>Blockchain</h3><p>Solidity, Ethereum, Hyperledger, Truffle, Ganache, Web3.js</p></div>
                    <div class="project-card"><h3>Automatización</h3><p>Selenium, Puppeteer, UiPath, Power Automate, Python</p></div>
                    <div class="project-card"><h3>Cloud Computing</h3><p>AWS, Google Cloud, Azure, Firebase, Heroku, DigitalOcean</p></div>
                    <div class="project-card"><h3>Realidad Virtual y Aumentada</h3><p>Unity, Unreal Engine, ARKit, ARCore, WebXR, Blender</p></div>
                    <div class="project-card"><h3>Inteligencia Artificial</h3><p>Python, OpenAI, GPT, NLP, Computer Vision, Deep Learning</p></div>
                    <div class="project-card"><h3>Desarrollo de Videojuegos</h3><p>Unity, Unreal Engine, C#, C++, Blender, Maya</p></div>
                    <div class="project-card"><h3>Big Data</h3><p>Hadoop, Spark, Kafka, Hive, Pig, Scala</p></div>
                    <div class="project-card"><h3>Ciberseguridad</h3><p>Kali Linux, Metasploit, Nmap, Wireshark, Burp Suite, OWASP</p></div>
                    <div class="project-card"><h3>Desarrollo de APIs</h3><p>REST, GraphQL, Swagger, Postman, Node.js, Python</p></div>
                    <div class="project-card"><h3>Diseño Gráfico</h3><p>Photoshop, Illustrator, InDesign, CorelDRAW, Canva</p></div>
                    <div class="project-card"><h3>Desarrollo de Aplicaciones Empresariales</h3><p>Java, Spring Boot, .NET, C#, Oracle, SQL Server</p></div>
                    <div class="project-card"><h3>Desarrollo de Aplicaciones Web</h3><p>React, Angular, Vue.js, Node.js, Django, Flask</p></div>
                    <div class="project-card"><h3>Desarrollo de Aplicaciones Móviles</h3><p>React Native, Flutter, Swift, Kotlin, Android Studio, Xcode</p></div>
                    <div class="project-card"><h3>Desarrollo de Aplicaciones de Escritorio</h3><p>C#, Java, Python, Electron, WPF, WinForms</p></div>
                    <div class="project-card"><h3>Desarrollo de Aplicaciones IoT</h3><p>Arduino, Raspberry Pi, Python, Node.js, MQTT, Firebase</p></div>
                </div>

                <!-- ================= SERVIDOR ================= -->
                <div id="server" class="content-section">
                    <div class="content-header"><h1>Información del Servidor</h1></div>
                    <br>

                    <div class="server-info-grid">

                        <!-- BASE DE DATOS -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-database"></i> Base de Datos</h3>

                            <!-- Hero destacado con nombre BD + versión MariaDB -->
                            <div class="db-hero">
                                <div class="db-hero-label">Base de Datos</div>
                                <div class="db-hero-name"><?php echo e($dbName); ?></div>
                                <div class="db-hero-version">
                                    <?php echo e($dbType); ?> <?php echo e($dbVersionExact); ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <span class="info-label"><?php echo e($dbType); ?>:</span>
                                <span class="info-value highlight"><?php echo e($dbVersionExact); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value">
                                    <?php if ($dbStatus): ?>
                                        <span class="db-status-badge online" style="margin:0;padding:2px 10px;font-size:12px;"><i class="fa-solid fa-circle-check"></i> Conectada</span>
                                    <?php else: ?>
                                        <span class="db-status-badge offline" style="margin:0;padding:2px 10px;font-size:12px;"><i class="fa-solid fa-circle-xmark"></i> Desconectada</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-row"><span class="info-label">Host:</span><span class="info-value"><?php echo e($dbHost); ?></span></div>
                            <div class="info-row"><span class="info-label">Base de Datos:</span><span class="info-value"><?php echo e($dbName); ?></span></div>
                            <div class="info-row"><span class="info-label">Usuario:</span><span class="info-value"><?php echo e($dbUser); ?></span></div>
                            <div class="info-row"><span class="info-label">Charset Config:</span><span class="info-value"><?php echo e($dbCharsetCfg); ?></span></div>
                            <div class="info-row"><span class="info-label">Charset Actual:</span><span class="info-value"><?php echo e($dbCharset); ?></span></div>
                            <div class="info-row"><span class="info-label">Protocolo:</span><span class="info-value"><?php echo e($dbProtocol); ?></span></div>
                            <div class="info-row"><span class="info-label">Server Info:</span><span class="info-value"><?php echo e($dbInfo); ?></span></div>
                            <div class="info-row"><span class="info-label">Cliente MySQL:</span><span class="info-value"><?php echo e($dbClientInfo); ?></span></div>
                            <div class="info-row"><span class="info-label">Engine por defecto:</span><span class="info-value"><?php echo e($dbDefaultEngine); ?></span></div>
                            <?php if ($dbStats): ?>
                                <div class="info-row"><span class="info-label">Tablas:</span><span class="info-value"><?php echo e($dbStats['total_tables'] ?? '0'); ?></span></div>
                                <div class="info-row"><span class="info-label">Columnas:</span><span class="info-value"><?php echo e($dbStats['total_columns'] ?? '0'); ?></span></div>
                                <div class="info-row"><span class="info-label">Tamaño BD:</span><span class="info-value"><?php echo e($dbStats['db_size_mb'] ?? '0'); ?> MB</span></div>
                            <?php endif; ?>
                            <?php if (!$dbStatus && $dbError): ?>
                                <div class="info-row"><span class="info-label">Error:</span><span class="info-value danger"><?php echo e($dbError); ?></span></div>
                            <?php endif; ?>
                        </div>

                        <!-- KERNEL / SO -->
                        <div class="project-card">
                            <h3><i class="fa-brands fa-linux"></i> Sistema Operativo</h3>
                            <div class="info-row">
                                <span class="info-label">Kernel OS:</span>
                                <span class="info-value"><?php echo e($osName . ' ' . $osRelease . ' ' . $osArch); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Kernel Version:</span>
                                <span class="info-value"><?php echo e($osVersion); ?></span>
                            </div>
                            <div class="info-row"><span class="info-label">Arquitectura:</span><span class="info-value"><?php echo e($osArch); ?></span></div>
                            <div class="info-row"><span class="info-label">Hostname:</span><span class="info-value"><?php echo e($hostname); ?></span></div>
                            <div class="info-row"><span class="info-label">Uptime:</span><span class="info-value"><?php echo e($uptimeStr); ?></span></div>
                            <div class="info-row"><span class="info-label">Load Average:</span><span class="info-value"><?php echo e($loadAvgStr); ?></span></div>
                            <?php if ($cpuModel): ?>
                                <div class="info-row"><span class="info-label">CPU:</span><span class="info-value"><?php echo e($cpuModel); ?></span></div>
                            <?php endif; ?>
                            <?php if ($cpuCores): ?>
                                <div class="info-row"><span class="info-label">Núcleos CPU:</span><span class="info-value"><?php echo e($cpuCores); ?></span></div>
                            <?php endif; ?>
                            <?php if ($memTotal): ?>
                                <div class="info-row"><span class="info-label">Memoria Total:</span><span class="info-value"><?php echo e($memTotal); ?></span></div>
                                <div class="info-row"><span class="info-label">Memoria Libre:</span><span class="info-value"><?php echo e($memFree); ?></span></div>
                            <?php endif; ?>
                            <?php if ($diskTotal): ?>
                                <div class="info-row"><span class="info-label">Disco Total:</span><span class="info-value"><?php echo e($diskTotal); ?></span></div>
                                <div class="info-row"><span class="info-label">Disco Libre:</span><span class="info-value"><?php echo e($diskFree); ?></span></div>
                            <?php endif; ?>
                        </div>

                        <!-- PHP -->
                        <div class="project-card">
                            <h3><i class="fa-brands fa-php"></i> PHP</h3>
                            <div class="info-row"><span class="info-label">Versión:</span><span class="info-value highlight"><?php echo e($phpVersion); ?></span></div>
                            <div class="info-row"><span class="info-label">SAPI:</span><span class="info-value"><?php echo e($phpSapi); ?></span></div>
                            <div class="info-row"><span class="info-label">Arquitectura:</span><span class="info-value"><?php echo e($phpArchitecture); ?> (<?php echo e($phpIntSize); ?>)</span></div>
                            <div class="info-row"><span class="info-label">OPcache:</span><span class="info-value"><?php echo $phpOpcacheOn ? '<span class="info-value highlight">Habilitado</span>' : 'Deshabilitado'; ?></span></div>
                            <div class="info-row"><span class="info-label">Zona Horaria:</span><span class="info-value"><?php echo e($phpTimezone); ?></span></div>
                            <div class="info-row"><span class="info-label">Hora Actual:</span><span class="info-value"><?php echo e($currentTime); ?></span></div>
                        </div>

                        <!-- SERVIDOR WEB -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-server"></i> Servidor Web (<?php echo e($webServerType); ?>)</h3>
                            <div class="info-row">
                                <span class="info-label"><?php echo e($webServerType); ?>:</span>
                                <span class="info-value highlight"><?php echo e($apacheVersion !== 'N/A' ? $apacheVersion : $serverSoftware); ?></span>
                            </div>
                            <div class="info-row"><span class="info-label">Software Completo:</span><span class="info-value"><?php echo e($serverSoftware); ?></span></div>
                            <?php if ($webServerType === 'Apache'): ?>
                                <div class="info-row"><span class="info-label">MPM:</span><span class="info-value"><?php echo e($apacheMPM); ?></span></div>
                                <div class="info-row"><span class="info-label">Módulos cargados:</span><span class="info-value"><?php echo count($apacheModules); ?></span></div>
                            <?php endif; ?>
                            <div class="info-row"><span class="info-label">Protocolo:</span><span class="info-value"><?php echo e($serverProtocol); ?></span></div>
                            <div class="info-row"><span class="info-label">Servidor:</span><span class="info-value"><?php echo e($serverName); ?></span></div>
                            <div class="info-row"><span class="info-label">Puerto:</span><span class="info-value"><?php echo e($serverPort); ?></span></div>
                            <div class="info-row"><span class="info-label">HTTPS:</span><span class="info-value"><?php echo e($https); ?></span></div>
                            <div class="info-row"><span class="info-label">IP Servidor:</span><span class="info-value"><?php echo e($serverAddr); ?></span></div>
                            <div class="info-row"><span class="info-label">IP Cliente:</span><span class="info-value"><?php echo e($clientIP); ?></span></div>
                        </div>

                        <!-- MÓDULOS APACHE -->
                        <?php if ($webServerType === 'Apache' && !empty($apacheModules)): ?>
                        <div class="project-card">
                            <h3><i class="fa-brands fa-apache"></i> Módulos Apache (<?php echo count($apacheModules); ?>)</h3>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
                                <?php foreach ($apacheModules as $mod): ?>
                                    <span class="extension-badge"><?php echo e($mod); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- LÍMITES PHP -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-gauge-high"></i> Límites PHP</h3>
                            <div class="info-row"><span class="info-label">Memory Limit:</span><span class="info-value"><?php echo e($memoryLimit); ?></span></div>
                            <div class="info-row"><span class="info-label">Max Execution Time:</span><span class="info-value"><?php echo e($maxExecutionTime); ?>s</span></div>
                            <div class="info-row"><span class="info-label">Max Input Time:</span><span class="info-value"><?php echo e($maxInputTime); ?>s</span></div>
                            <div class="info-row"><span class="info-label">Upload Max Filesize:</span><span class="info-value"><?php echo e($uploadMaxFilesize); ?></span></div>
                            <div class="info-row"><span class="info-label">Post Max Size:</span><span class="info-value"><?php echo e($postMaxSize); ?></span></div>
                            <div class="info-row"><span class="info-label">Max Input Vars:</span><span class="info-value"><?php echo e($maxInputVars); ?></span></div>
                            <div class="info-row"><span class="info-label">Max File Uploads:</span><span class="info-value"><?php echo e($maxFileUploads); ?></span></div>
                            <div class="info-row"><span class="info-label">Display Errors:</span><span class="info-value"><?php echo e($displayErrors); ?></span></div>
                        </div>

                        <!-- EXTENSIONES PHP -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-puzzle-piece"></i> Extensiones PHP (<?php echo e($extensionsCount); ?>)</h3>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
                                <?php foreach ($loadedExtensions as $ext): ?>
                                    <span class="extension-badge"><?php echo e($ext); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- EXTENSIONES CRÍTICAS -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-shield-halved"></i> Extensiones Críticas</h3>
                            <div class="info-row"><span class="info-label">MySQLi:</span><span class="info-value"><?php echo statusBadge($hasMySQLi); ?></span></div>
                            <div class="info-row"><span class="info-label">PDO:</span><span class="info-value"><?php echo statusBadge($hasPDO); ?></span></div>
                            <div class="info-row"><span class="info-label">PDO MySQL:</span><span class="info-value"><?php echo statusBadge($hasPDOMySQL); ?></span></div>
                            <div class="info-row"><span class="info-label">GD (Imágenes):</span><span class="info-value"><?php echo statusBadge($hasGD); ?></span></div>
                            <div class="info-row"><span class="info-label">cURL:</span><span class="info-value"><?php echo statusBadge($hasCurl); ?></span></div>
                            <div class="info-row"><span class="info-label">JSON:</span><span class="info-value"><?php echo statusBadge($hasJSON); ?></span></div>
                            <div class="info-row"><span class="info-label">MBString:</span><span class="info-value"><?php echo statusBadge($hasMBString); ?></span></div>
                            <div class="info-row"><span class="info-label">XML:</span><span class="info-value"><?php echo statusBadge($hasXML); ?></span></div>
                            <div class="info-row"><span class="info-label">Zip:</span><span class="info-value"><?php echo statusBadge($hasZip); ?></span></div>
                            <div class="info-row"><span class="info-label">OpenSSL:</span><span class="info-value"><?php echo statusBadge($hasOpenSSL); ?></span></div>
                            <div class="info-row"><span class="info-label">Session:</span><span class="info-value"><?php echo statusBadge($hasSession); ?></span></div>
                            <div class="info-row"><span class="info-label">FileInfo:</span><span class="info-value"><?php echo statusBadge($hasFileInfo); ?></span></div>
                            <div class="info-row"><span class="info-label">Intl:</span><span class="info-value"><?php echo statusBadge($hasIntl); ?></span></div>
                            <div class="info-row"><span class="info-label">Sodium:</span><span class="info-value"><?php echo statusBadge($hasSodium); ?></span></div>
                            <div class="info-row"><span class="info-label">BCMath:</span><span class="info-value"><?php echo statusBadge($hasBcMath); ?></span></div>
                        </div>

                        <!-- RUTAS Y CONFIG -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-folder-tree"></i> Rutas y Configuración</h3>
                            <div class="info-row"><span class="info-label">Document Root:</span><span class="info-value"><?php echo e($documentRoot); ?></span></div>
                            <div class="info-row"><span class="info-label">Script Name:</span><span class="info-value"><?php echo e($scriptName); ?></span></div>
                            <div class="info-row"><span class="info-label">BASE_URL:</span><span class="info-value"><?php echo e(BASE_URL); ?></span></div>
                            <div class="info-row"><span class="info-label">BASE_PATH:</span><span class="info-value"><?php echo e(BASE_PATH); ?></span></div>
                            <div class="info-row"><span class="info-label">SITE_VERSION:</span><span class="info-value highlight"><?php echo e(SITE_VERSION); ?></span></div>
                        </div>

                        <!-- MYSQL SERVIDOR -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-database"></i> MySQL: Estado del Servidor</h3>
                            <div class="info-row"><span class="info-label">Uptime:</span><span class="info-value"><?php echo e($dbUptime); ?></span></div>
                            <div class="info-row"><span class="info-label">Conexiones:</span><span class="info-value"><?php echo e($dbThreadsConn); ?> / <?php echo e($dbMaxConn); ?></span></div>
                            <div class="info-row"><span class="info-label">InnoDB Buffer Pool:</span><span class="info-value"><?php echo e($dbBufferPool); ?></span></div>
                            <div class="info-row"><span class="info-label">Charset servidor:</span><span class="info-value"><?php echo e($dbSrvCharset); ?></span></div>
                            <div class="info-row"><span class="info-label">Collation servidor:</span><span class="info-value"><?php echo e($dbSrvCollation); ?></span></div>
                            <div class="info-row"><span class="info-label">Query Cache:</span><span class="info-value"><?php echo e($dbQueryCacheType); ?> (<?php echo e($dbQueryCacheSize); ?>)</span></div>
                        </div>

                        <!-- HTTP / RESPUESTA -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-arrow-right-arrow-left"></i> HTTP / Respuesta</h3>
                            <div class="info-row"><span class="info-label">Server:</span><span class="info-value"><?php echo e($serverSoftware); ?></span></div>
                            <div class="info-row"><span class="info-label">X-Powered-By:</span><span class="info-value"><?php echo e($headerXPowered); ?></span></div>
                            <div class="info-row"><span class="info-label">Cache-Control:</span><span class="info-value"><?php echo e($headerCache); ?></span></div>
                            <div class="info-row"><span class="info-label">Content-Security:</span><span class="info-value"><?php echo e($headerCSP); ?></span></div>
                            <div class="info-row"><span class="info-label">X-Frame-Options:</span><span class="info-value"><?php echo e($headerXFrame); ?></span></div>
                            <div class="info-row"><span class="info-label">X-Content-Type:</span><span class="info-value"><?php echo e($headerXCTO); ?></span></div>
                            <div class="info-row"><span class="info-label">OpenSSL:</span><span class="info-value"><?php echo e($opensslVersion); ?></span></div>
                            <div class="info-row"><span class="info-label">TLS Protocolo:</span><span class="info-value"><?php echo e($tlsProtocol); ?></span></div>
                            <div class="info-row"><span class="info-label">Cipher:</span><span class="info-value"><?php echo e($tlsCipher); ?></span></div>
                        </div>

                        <!-- RENDIMIENTO -->
                        <div class="project-card">
                            <h3><i class="fa-solid fa-gauge-high"></i> Rendimiento de la Página</h3>
                            <div class="info-row"><span class="info-label">Tiempo de generación:</span><span class="info-value"><?php echo sprintf('%.3f s', $pageTime); ?></span></div>
                            <div class="info-row"><span class="info-label">Pico de memoria:</span><span class="info-value"><?php echo e(fmtBytes($peakMem)); ?></span></div>
                            <div class="info-row"><span class="info-label">Uptime Apache:</span><span class="info-value"><?php echo e($apacheUptime); ?></span></div>
                            <div class="info-row"><span class="info-label">Requests servidos:</span><span class="info-value"><?php echo e($apacheRequests); ?></span></div>
                        </div>

                    </div>
                </div>

                <!-- ================= CONTACTO ================= -->
                <div id="contact" class="content-section">
                    <div class="content-header"><h1>Información de Contacto</h1></div>
                    <h2>Contáctame</h2>
                    <p>¡No dudes en contactarme para oportunidades de colaboración o simplemente para saludar!</p>
                    <div class="project-card">
                        <h3>Información de Contacto</h3>
                        <p><i class="fa-solid fa-envelope"></i> kakycu@gmail.com</p>
                        <p><i class="fa-solid fa-envelope"></i> frlamadrid.cmw@infomed.sld.cu</p>
                        <p><i class="fa-solid fa-envelope"></i> kakycu@nauta.cu</p>
                        <p><i class="fa-solid fa-phone"></i> +53 32 415418</p>
                        <p><i class="fa-solid fa-mobile-screen"></i> +53 52 712861</p>
                        <p><i class="fa-solid fa-map-marker-alt"></i> Nuevitas, Camagüey. Cuba</p>
                    </div>
                    <div class="project-card">
                        <h3>Redes Sociales</h3>
                        <div class="icon-container">
                            <a href="https://github.com/kakycu" target="_blank"><i class="fa-brands fa-github"></i> github.com/kakycu</a>
                            <a href="https://linkedin.com/in/kakycu" target="_blank"><i class="fa-brands fa-linkedin"></i> linkedin.com/in/kakycu</a>
                            <a href="https://facebook.com/kakycu" target="_blank"><i class="fa-brands fa-facebook"></i> facebook.com/kakycu</a>
                        </div>
                    </div>
                </div>

                <!-- ================= DESARROLLO WEB ================= -->
                <div id="web" class="content-section">
                    <div class="content-header"><h1>Desarrollo Web</h1></div>
                    <br>
                    <div class="project-card"><h3>Portafolio Interactivo</h3><p>Sitio web personal con galería interactiva de proyectos y blog integrado.</p><div class="tags"><span class="tag">HTML5</span><span class="tag">CSS3</span><span class="tag">JavaScript</span></div></div>
                    <div class="project-card"><h3>Plataforma de Cursos Online</h3><p>Sistema completo para gestión de cursos con panel de administración y perfil de estudiante.</p><div class="tags"><span class="tag">React</span><span class="tag">Node.js</span><span class="tag">MongoDB</span></div></div>
                    <div class="project-card"><h3>Calculadora de Consumo Energético Web</h3><p>Aplicación web para calcular el consumo eléctrico en el hogar, con gráficos y recomendaciones de ahorro.</p><div class="tags"><span class="tag">React</span><span class="tag">Node.js</span><span class="tag">Chart.js</span></div></div>
                    <div class="project-card"><h3>Calculadora de Consumo Eléctrico (Visual Basic)</h3><p>Aplicación de escritorio para calcular el consumo eléctrico en el hogar, con interfaz gráfica y exportación de informes.</p><div class="tags"><span class="tag">Visual Basic</span><span class="tag">MySQL</span><span class="tag">Excel</span></div></div>
                    <div class="project-card"><h3>Sistema de Gestión de Inventarios</h3><p>Aplicación para gestionar inventarios de productos, entradas y salidas, con panel de administración.</p><div class="tags"><span class="tag">PHP</span><span class="tag">Laravel</span><span class="tag">MySQL</span></div></div>
                    <div class="project-card"><h3>Plataforma de Blogging</h3><p>Plataforma de blogs con editor WYSIWYG, autenticación y comentarios.</p><div class="tags"><span class="tag">Next.js</span><span class="tag">Firebase</span><span class="tag">Tailwind CSS</span></div></div>
                    <div class="project-card"><h3>Sistema de Reservas de Hotel</h3><p>Sistema para reservar habitaciones de hotel con calendario y pagos en línea.</p><div class="tags"><span class="tag">Angular</span><span class="tag">Express.js</span><span class="tag">PostgreSQL</span></div></div>
                    <div class="project-card"><h3>Aplicación de Clima en Tiempo Real</h3><p>Aplicación móvil para mostrar el clima actual y pronósticos semanales.</p><div class="tags"><span class="tag">React Native</span><span class="tag">Expo</span><span class="tag">OpenWeather API</span></div></div>
                    <div class="project-card"><h3>Plataforma de Streaming de Video</h3><p>Plataforma para subir y ver videos con categorías y reproducción en tiempo real.</p><div class="tags"><span class="tag">Vue.js</span><span class="tag">AWS S3</span><span class="tag">Redis</span></div></div>
                    <div class="project-card"><h3>Sistema de Gestión de Facturas</h3><p>Aplicación para generar facturas, gestionar clientes y productos, y exportar a PDF.</p><div class="tags"><span class="tag">Java</span><span class="tag">Spring Boot</span><span class="tag">MySQL</span></div></div>
                    <div class="project-card"><h3>Aplicación de Red Social</h3><p>Red social con perfiles de usuario, publicaciones, likes y comentarios.</p><div class="tags"><span class="tag">Flutter</span><span class="tag">Firebase</span><span class="tag">Dart</span></div></div>
                    <div class="project-card"><h3>Sistema de Gestión de Tareas</h3><p>Aplicación web para gestionar tareas diarias con recordatorios y seguimiento.</p><div class="tags"><span class="tag">React</span><span class="tag">Node.js</span><span class="tag">MongoDB</span></div></div>
                </div>

                <!-- ================= DISEÑO UI/UX ================= -->
                <div id="design" class="content-section">
                    <div class="content-header"><h1>Diseño UI/UX</h1></div>
                    <br>
                    <div class="project-card"><h3>Sistema de Diseño Modular</h3><p>Creación de sistema de diseño completo con componentes reutilizables y documentación.</p><div class="tags"><span class="tag">Figma</span><span class="tag">Storybook</span><span class="tag">Design Tokens</span></div></div>
                    <div class="project-card"><h3>Rediseño de Aplicación Bancaria</h3><p>Mejora de experiencia de usuario para aplicación móvil de banco internacional.</p><div class="tags"><span class="tag">UX Research</span><span class="tag">Prototipado</span><span class="tag">User Testing</span></div></div>
                </div>

                <!-- ================= APLICACIONES ================= -->
                <div id="apps" class="content-section">
                    <div class="content-header"><h1>Aplicaciones</h1></div>
                    <br>
                    <div class="project-card"><h3>App de Gestión de Tareas</h3><p>Aplicación móvil multiplataforma para gestión de proyectos personales y profesionales.</p><div class="tags"><span class="tag">Flutter</span><span class="tag">Firebase</span><span class="tag">BLoC</span></div></div>
                    <div class="project-card"><h3>Cliente de Mensajería Segura</h3><p>Aplicación de escritorio con cifrado end-to-end para comunicación empresarial.</p><div class="tags"><span class="tag">Electron</span><span class="tag">TypeScript</span><span class="tag">WebSockets</span></div></div>
                    <div class="project-card"><h3>Plataforma de E-learning</h3><p>Sistema para crear y tomar cursos en línea con videos, cuestionarios y certificados.</p><div class="tags"><span class="tag">Django</span><span class="tag">Python</span><span class="tag">PostgreSQL</span></div></div>
                    <div class="project-card"><h3>App de Gestión de Inventarios</h3><p>Aplicación móvil para gestionar inventarios de productos, entradas y salidas.</p><div class="tags"><span class="tag">React Native</span><span class="tag">Firebase</span><span class="tag">Redux</span></div></div>
                    <div class="project-card"><h3>Sistema de Reservas de Vuelos</h3><p>Plataforma para reservar vuelos con calendario, pagos en línea y notificaciones.</p><div class="tags"><span class="tag">Angular</span><span class="tag">Node.js</span><span class="tag">MongoDB</span></div></div>
                    <div class="project-card"><h3>App de Seguimiento de Ejercicio</h3><p>Aplicación móvil para registrar y analizar entrenamientos físicos.</p><div class="tags"><span class="tag">Flutter</span><span class="tag">Firebase</span><span class="tag">Google Fit API</span></div></div>
                </div>

            </div><!-- /main-area -->
        </div><!-- /content -->

        <!-- Menú contextual -->
        <div class="context-menu" id="context-menu">
            <ul>
                <li id="maximize-option"><i class="fa-solid fa-window-maximize"></i> Maximizar</li>
                <li id="restore-option"><i class="fa-solid fa-window-restore"></i> Restaurar</li>
                <li class="separator"></li>
                <li id="refresh-option"><i class="fa-solid fa-rotate-right"></i> Actualizar</li>
                <li class="separator"></li>
                <li id="close-option" onclick="closeWindows();"><i class="fa-solid fa-xmark"></i> Cerrar</li>
            </ul>
        </div>

        <!-- Barra de estado -->
        <div class="status-bar" id="status-bar">
            <span class="status-icon"><i class="fa-solid fa-circle-info"></i></span>
            <span id="status-text">Listo</span>
            <span id="status-position">Inicio</span>
        </div>
    </div>

<script>
    let isDragging = false;
    let offsetX = 0, offsetY = 0;
    let isMaximized = false;
    let lastClickTime = 0;
    const doubleClickDelay = 300;
    let originalDimensions = {};

    let isResizing = false;
    let resizeDirection = null;
    let initialX, initialY, initialWidth, initialHeight;

    const explorerWindow = document.getElementById('explorer-window');
    const topBar = document.getElementById('top-bar');
    const menuButton = document.getElementById('menu-button');
    const minimizeButton = document.getElementById('minimize-button');
    const maximizeButton = document.getElementById('maximize-button');
    const closeButton = document.getElementById('close-button');
    const contextMenu = document.getElementById('context-menu');
    const statusText = document.getElementById('status-text');
    const statusPosition = document.getElementById('status-position');

    originalDimensions = {
        width: explorerWindow.style.width,
        height: explorerWindow.style.height,
        left: explorerWindow.style.left,
        top: explorerWindow.style.top
    };

    topBar.addEventListener('mousedown', (e) => {
        const currentTime = new Date().getTime();
        if (currentTime - lastClickTime < doubleClickDelay) { e.preventDefault(); return; }
        lastClickTime = currentTime;
        if (e.target === menuButton || e.target.closest('.window-controls')) return;
        isDragging = true;
        offsetX = e.clientX - explorerWindow.offsetLeft;
        offsetY = e.clientY - explorerWindow.offsetTop;
        updateStatus("Arrastrando ventana...");
    });

    topBar.addEventListener('dblclick', () => {
        topBar.classList.add('double-click-active');
        setTimeout(() => topBar.classList.remove('double-click-active'), 400);
        toggleMaximize();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging || isMaximized || explorerWindow.classList.contains('minimized')) return;
        let newLeft = e.clientX - offsetX;
        let newTop = e.clientY - offsetY;
        newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - explorerWindow.offsetWidth));
        newTop = Math.max(0, Math.min(newTop, window.innerHeight - explorerWindow.offsetHeight));
        explorerWindow.style.left = `${newLeft}px`;
        explorerWindow.style.top = `${newTop}px`;
        explorerWindow.style.transform = 'none';
    });

    document.addEventListener('mouseup', () => { isDragging = false; updateStatus("Listo"); });

    minimizeButton.addEventListener('click', () => {
        if (explorerWindow.classList.contains('minimized')) {
            explorerWindow.classList.remove('minimized');
            explorerWindow.style.height = originalDimensions.height;
            updateStatus("Ventana restaurada");
            minimizeButton.innerHTML = '<i class="fa-solid fa-window-minimize"></i>';
        } else {
            if (!isMaximized) {
                originalDimensions = {
                    width: explorerWindow.style.width,
                    height: explorerWindow.style.height,
                    left: explorerWindow.style.left,
                    top: explorerWindow.style.top
                };
            }
            explorerWindow.classList.add('minimized');
            explorerWindow.style.height = '40px';
            updateStatus("Ventana minimizada");
            minimizeButton.innerHTML = '<i class="fa-solid fa-window-restore"></i>';
        }
    });

    maximizeButton.addEventListener('click', toggleMaximize);
    closeButton.addEventListener('click', () => { explorerWindow.style.display = 'none'; updateStatus("Ventana cerrada"); });

    function toggleMaximize() {
        if (explorerWindow.classList.contains('minimized')) {
            explorerWindow.classList.remove('minimized');
            explorerWindow.style.height = originalDimensions.height;
            minimizeButton.innerHTML = '<i class="fa-solid fa-window-minimize"></i>';
        }
        if (isMaximized) {
            explorerWindow.style.width = originalDimensions.width;
            explorerWindow.style.height = originalDimensions.height;
            explorerWindow.style.left = originalDimensions.left;
            explorerWindow.style.top = originalDimensions.top;
            updateStatus("Ventana restaurada");
            maximizeButton.innerHTML = '<i class="fa-solid fa-window-maximize"></i>';
        } else {
            originalDimensions = {
                width: explorerWindow.style.width,
                height: explorerWindow.style.height,
                left: explorerWindow.style.left,
                top: explorerWindow.style.top
            };
            explorerWindow.style.width = '100vw';
            explorerWindow.style.height = '100vh';
            explorerWindow.style.left = '0';
            explorerWindow.style.top = '0';
            updateStatus("Ventana maximizada");
            maximizeButton.innerHTML = '<i class="fa-solid fa-window-restore"></i>';
        }
        isMaximized = !isMaximized;
    }

    menuButton.addEventListener('click', (e) => {
        e.stopPropagation();
        if (contextMenu.style.display === 'block') { cerrarMenuContextual(); return; }
        const buttonRect = menuButton.getBoundingClientRect();
        const windowRect = explorerWindow.getBoundingClientRect();
        contextMenu.style.left = `${buttonRect.left - windowRect.left}px`;
        contextMenu.style.top = `${buttonRect.bottom - windowRect.top}px`;
        contextMenu.style.display = 'block';
        updateStatus("Menú abierto");
    });

    function cerrarMenuContextual() { contextMenu.style.display = 'none'; }

    document.addEventListener('click', (e) => {
        if (!contextMenu.contains(e.target) && e.target !== menuButton) cerrarMenuContextual();
    });

    document.getElementById('maximize-option').addEventListener('click', () => { toggleMaximize(); cerrarMenuContextual(); });
    document.getElementById('restore-option').addEventListener('click', () => { if (isMaximized) toggleMaximize(); cerrarMenuContextual(); });
    document.getElementById('refresh-option').addEventListener('click', () => { cerrarMenuContextual(); location.reload(); });
    document.getElementById('close-option').addEventListener('click', () => { cerrarMenuContextual(); explorerWindow.style.display = 'none'; updateStatus("Ventana cerrada"); });

    function syncTabsAndNavigation(contentId) {
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.toggle('active', tab.getAttribute('data-content') === contentId);
        });
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.toggle('active', item.getAttribute('data-content') === contentId);
        });
    }

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const contentId = tab.getAttribute('data-content');
            showContent(contentId);
            syncTabsAndNavigation(contentId);
            updateStatus(`Sección ${tab.textContent.trim()} abierta`);
            updatePosition(tab.textContent.trim());
        });
        tab.addEventListener('mouseover', () => updateStatus(`Abrir ${tab.textContent.trim()}`));
    });

    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.addEventListener('click', () => {
            const contentId = item.getAttribute('data-content');
            showContent(contentId);
            syncTabsAndNavigation(contentId);
            updateStatus(`Sección ${item.textContent.trim()} abierta`);
        });
        item.addEventListener('mouseover', () => updateStatus(`Abrir ${item.textContent.trim()}`));
    });

    function showContent(contentId) {
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        const section = document.getElementById(contentId);
        if (section) section.classList.add('active');
        updatePosition(contentId);
    }

    function updatePosition(contentId) {
        const positionText = contentId.charAt(0).toUpperCase() + contentId.slice(1);
        statusPosition.textContent = positionText;
    }

    function updateStatus(message) { statusText.textContent = message; }

    showContent('home');
    syncTabsAndNavigation('home');

    document.querySelectorAll('.resize-handle-bottom, .resize-handle-right').forEach(handle => {
        handle.addEventListener('mousedown', (e) => {
            isResizing = true;
            resizeDirection = getResizeDirection(e.target);
            initialX = e.clientX;
            initialY = e.clientY;
            initialWidth = explorerWindow.offsetWidth;
            initialHeight = explorerWindow.offsetHeight;
            updateStatus("Redimensionando ventana...");
        });
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;
        const deltaX = e.clientX - initialX;
        const deltaY = e.clientY - initialY;
        switch (resizeDirection) {
            case 'bottom':
                explorerWindow.style.height = `${initialHeight + deltaY}px`; break;
            case 'right':
                explorerWindow.style.width = `${initialWidth + deltaX}px`; break;
        }
    });

    document.addEventListener('mouseup', () => {
        if (isResizing) { isResizing = false; resizeDirection = null; updateStatus("Listo"); }
    });

    function getResizeDirection(target) {
        if (target.classList.contains('resize-handle-bottom')) return 'bottom';
        if (target.classList.contains('resize-handle-right')) return 'right';
        return null;
    }

    function closeWindows() {
        window.location.href = 'dashboard.php';
    }
</script>
</body>
</html>