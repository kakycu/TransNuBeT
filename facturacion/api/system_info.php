<?php
// api/system_info.php
require_once '../config/init.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

try {
    // ---------------------------------------------------------
    // 1. DATOS BÁSICOS (Ya los tenías)
    // ---------------------------------------------------------
    $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    
    // ---------------------------------------------------------
    // 2. NUEVOS DATOS: ESPACIO EN DISCO (Donde está instalado el sistema)
    // ---------------------------------------------------------
    // '.' busca en el disco actual (C: en Windows usualmente)
    $disk_total = disk_total_space('.'); 
    $disk_free  = disk_free_space('.');
    $disk_used  = $disk_total - $disk_free;
    
    // Porcentaje de uso
    $disk_percentage = round(($disk_used / $disk_total) * 100, 1);

    // ---------------------------------------------------------
    // 3. NUEVOS DATOS: BASE DE DATOS
    // ---------------------------------------------------------
    // Usamos la conexión $db que ya viene de init.php -> database.php
    try {
        $db_conn = Database::getConnection();
        $db_version = $db_conn->getAttribute(PDO::ATTR_SERVER_VERSION);
        $db_status = "Conectado";
    } catch (Exception $e) {
        $db_version = "Desconocido";
        $db_status = "Error";
    }

    // ---------------------------------------------------------
    // 4. PREPARAR RESPUESTA
    // ---------------------------------------------------------
    $response = [
        'success'        => true,
        
        // Rendimiento del Script PHP
        'php_memory'     => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'execution_time' => number_format(microtime(true) - $start, 4) . ' s',
        
        // Información del Servidor (PC)
        'server_os'      => php_uname('s') . ' ' . php_uname('r') . ' ' . . php_uname('v'), // Ej: Windows NT 10.0
        'php_version'    => PHP_VERSION,
        'server_time'    => date('Y-m-d H:i:s'),
        'system_uptime'  => getSystemUptime(),
        
        // Información de Disco (C:)
        'disk_total'     => formatBytes($disk_total),
        'disk_free'      => formatBytes($disk_free),
        'disk_used_perc' => $disk_percentage . '%', // Para poner en una barra de progreso
        
        // Base de Datos
        'db_version'     => $db_version, // Ej: 10.4.32-MariaDB
        'db_status'      => $db_status,
        
        // Información del Cliente (Quien visita)
        'client_ip'      => $_SERVER['REMOTE_ADDR']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// =============================================================
// FUNCIONES AUXILIARES
// =============================================================


function getSystemUptime() {
    try {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // MÉTODO WMIC CORREGIDO
            $output = shell_exec('wmic os get lastbootuptime 2>&1');
            
            if ($output) {
                // DEBUG: Ver qué devuelve exactamente
                error_log("WMIC RAW OUTPUT: " . $output);
                
                // Buscar el timestamp en formato YYYYMMDDHHMMSS
                if (preg_match('/(\d{14})/', $output, $matches)) {
                    $bootTimeStr = $matches[1];
                    
                    // Formatear correctamente: AAAA-MM-DD HH:MM:SS
                    $year = substr($bootTimeStr, 0, 4);
                    $month = substr($bootTimeStr, 4, 2);
                    $day = substr($bootTimeStr, 6, 2);
                    $hour = substr($bootTimeStr, 8, 2);
                    $minute = substr($bootTimeStr, 10, 2);
                    $second = substr($bootTimeStr, 12, 2);
                    
                    $bootDateTime = "$year-$month-$day $hour:$minute:$second";
                    $bootTime = strtotime($bootDateTime);
                    
                    if ($bootTime) {
                        $currentTime = time();
                        $uptimeSeconds = $currentTime - $bootTime;
                        
                        // Validación: no puede ser negativo ni mayor a 90 días
                        if ($uptimeSeconds > 0 && $uptimeSeconds < 7776000) {
                            $hours = floor($uptimeSeconds / 3600);
                            $minutes = floor(($uptimeSeconds % 3600) / 60);
                            
                            return "$hours h $minutes m";
                        }
                    }
                }
            }
            
            // MÉTODO ALTERNATIVO si WMIC falla
            return getUptimeFallback();
            
        } elseif (is_readable('/proc/uptime')) {
            // LINUX
            $uptime = file_get_contents('/proc/uptime');
            $uptimeSeconds = (float)explode(' ', $uptime)[0];
            $hours = floor($uptimeSeconds / 3600);
            $minutes = floor(($uptimeSeconds % 3600) / 60);
            return "$hours h $minutes m";
        }
    } catch (Exception $e) {
        error_log("Error en getSystemUptime: " . $e->getMessage());
    }
    
    return "N/A";
}

// Función de respaldo
function getUptimeFallback() {
    // Intentar con systeminfo
    $output = shell_exec('systeminfo 2>&1 | find "System Boot Time"');
    
    if (!$output) {
        // En español
        $output = shell_exec('systeminfo 2>&1 | find "Hora de inicio del sistema"');
    }
    
    if ($output && preg_match('/:\s+(.+)/', $output, $matches)) {
        $bootTimeStr = trim($matches[1]);
        $bootTime = strtotime($bootTimeStr);
        
        if ($bootTime) {
            $uptimeSeconds = time() - $bootTime;
            $hours = floor($uptimeSeconds / 3600);
            $minutes = floor(($uptimeSeconds % 3600) / 60);
            return "$hours h $minutes m";
        }
    }
    
    // Último recurso: tiempo desde que inició PHP
    $phpStartTime = $_SERVER['REQUEST_TIME'] ?? time();
    $uptimeSeconds = time() - $phpStartTime;
    $minutes = floor($uptimeSeconds / 60);
    return "~$minutes m";
}

function formatDuration($seconds) {
    // Validación básica
    if ($seconds <= 0 || $seconds > 31536000 * 2) { // Más de 2 años
        return "N/A";
    }
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    
    if ($days > 0) {
        $parts[] = "$days d";
        if ($hours > 0) {
            $parts[] = "$hours h";
        }
    } elseif ($hours > 0) {
        $parts[] = "$hours h";
        $parts[] = "$minutes m";
    } else {
        // Menos de 1 hora
        if ($minutes > 0) {
            $parts[] = "$minutes m";
        } else {
            // Menos de 1 minuto
            $seconds = floor($seconds);
            $parts[] = "$seconds s";
        }
    }
    
    return implode(' ', $parts);
}

// Función bonita para formatear bytes a GB/TB
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}


?>