<?php
/**
 * ajax/restore_db.php
 * Restaura/Importa un backup SQL desde un archivo ZIP o SQL
 */

// Configuración regional y zona horaria
setlocale(LC_ALL, "es_ES");
setlocale(LC_TIME, "spanish");
if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/New_York'); 
} else {
    date_default_timezone_set('America/New_York'); 
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos (solo administradores)
$rolPermitido = $_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? '';
if (!in_array($rolPermitido, ['Admin', 'Super', '1', '4'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para restaurar la base de datos']);
    exit;
}

header('Content-Type: application/json');

// Configuración de la base de datos
require_once '../config/database.php';

// Directorio temporal para procesar archivos
$temp_dir = '../temp/';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

try {
    global $pdo;
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
    
    // Verificar que se recibió un archivo
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] != UPLOAD_ERR_OK) {
        $errorMsg = "Error al subir el archivo";
        if (isset($_FILES['backup_file']['error'])) {
            switch ($_FILES['backup_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = "El archivo excede el tamaño máximo permitido";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = "El archivo se subió parcialmente";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = "No se seleccionó ningún archivo";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg = "Falta la carpeta temporal";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = "No se pudo escribir el archivo en el disco";
                    break;
            }
        }
        throw new Exception($errorMsg);
    }
    
    $uploadedFile = $_FILES['backup_file']['tmp_name'];
    $originalName = $_FILES['backup_file']['name'];
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $fileSize = $_FILES['backup_file']['size'];
    
    // Validar tamaño (máximo 300MB)
    if ($fileSize > 300 * 1024 * 1024) {
        throw new Exception("El archivo es demasiado grande. Máximo 300MB");
    }
    
    // Procesar según extensión
    $sqlContent = '';
    
    if ($fileExtension == 'zip') {
        // Extraer ZIP
        $zip = new ZipArchive();
        $extractPath = $temp_dir . 'restore_' . time() . '_' . uniqid();
        
        if (!mkdir($extractPath, 0755, true)) {
            throw new Exception("No se pudo crear directorio temporal");
        }
        
        if ($zip->open($uploadedFile) !== true) {
            throw new Exception("No se pudo abrir el archivo ZIP");
        }
        
        $zip->extractTo($extractPath);
        $zip->close();
        
        // Buscar archivo .sql dentro del ZIP
        $sqlFiles = glob($extractPath . '/*.sql');
        if (empty($sqlFiles)) {
            // Limpiar directorio
            array_map('unlink', glob("$extractPath/*"));
            rmdir($extractPath);
            throw new Exception("El archivo ZIP no contiene ningún archivo .sql");
        }
        
        // Tomar el primer archivo .sql encontrado
        $sqlFilePath = $sqlFiles[0];
        $sqlContent = file_get_contents($sqlFilePath);
        
        // Limpiar directorio temporal
        array_map('unlink', glob("$extractPath/*"));
        rmdir($extractPath);
        
    } elseif ($fileExtension == 'sql') {
        // Leer archivo SQL directamente
        $sqlContent = file_get_contents($uploadedFile);
        
    } else {
        throw new Exception("Formato no soportado. Solo se permiten archivos .sql o .zip");
    }
    
    if (empty($sqlContent)) {
        throw new Exception("El archivo está vacío o no se pudo leer");
    }
    
    // ============================================
    // PREPARAR Y EJECUTAR LA RESTAURACIÓN
    // ============================================
    
    // Configuración inicial
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    
    // Dividir las consultas SQL (manejando procedimientos almacenados, triggers, etc.)
    $queries = splitSQLQueries($sqlContent);
    
    $successful = 0;
    $failed = 0;
    $errors = [];
    $importedRows = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        try {
            // Ejecutar consulta
            $result = $pdo->exec($query);
            
            if ($result !== false) {
                $successful++;
                if (stripos($query, 'INSERT INTO') === 0) {
                    $importedRows += $result;
                }
            } else {
                $failed++;
                $errorInfo = $pdo->errorInfo();
                $errors[] = "Error en consulta: " . $errorInfo[2] . " | " . substr($query, 0, 100) . "...";
            }
            
        } catch (PDOException $e) {
            $failed++;
            $errors[] = "Error SQL: " . $e->getMessage() . " | " . substr($query, 0, 100) . "...";
            
            // Si el error es por paquete muy grande, abortar
            if (stripos($e->getMessage(), 'packet') !== false || stripos($e->getMessage(), 'gone away') !== false) {
                $errors[] = "CRÍTICO: Paquete demasiado grande. Se requiere aumentar max_allowed_packet en el servidor MySQL.";
                break;
            }
        }
    }
    
    // Restaurar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Registrar en log
    $logEntry = [
        'date' => date('Y-m-d H:i:s'),
        'filename' => $originalName,
        'successful' => $successful,
        'failed' => $failed,
        'imported_rows' => $importedRows,
        'user' => $_SESSION['user_nombre'] ?? $_SESSION['username'] ?? 'sistema'
    ];
    
    $logs = [];
    $logFile = '../logs/restore_log.json';
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    array_unshift($logs, $logEntry);
    if (count($logs) > 20) {
        $logs = array_slice($logs, 0, 20);
    }
    if (!is_dir('../logs')) {
        mkdir('../logs', 0755, true);
    }
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    
    // ============================================
    // RESPUESTA
    // ============================================
    
    $message = "Restauración completada\n";
    $message .= "✅ Consultas exitosas: $successful\n";
    $message .= "📊 Registros importados: " . number_format($importedRows) . "\n";
    
    if ($failed > 0) {
        $message .= "⚠️ Consultas fallidas: $failed\n";
        if (count($errors) > 0) {
            $message .= "\n❌ Errores:\n";
            $message .= implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $message .= "\n... y " . (count($errors) - 10) . " errores más";
            }
        }
    }
    
    echo json_encode([
        'success' => ($failed === 0),
        'message' => $message,
        'data' => [
            'successful_queries' => $successful,
            'failed_queries' => $failed,
            'imported_rows' => $importedRows,
            'errors' => $errors
        ]
    ]);
    
} catch (PDOException $e) {
    // Intentar restaurar foreign keys
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $ex) {}
    
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Divide un script SQL en consultas individuales
 * Respeta cadenas de texto con comillas y escapado
 */
function splitSQLQueries($sql) {
    $queries = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $len = strlen($sql);
    
    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        
        if ($escaped) {
            $current .= $char;
            $escaped = false;
            continue;
        }
        
        if ($char === '\\') {
            $current .= $char;
            $escaped = true;
            continue;
        }
        
        if (($char === "'" || $char === '"')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
        }
        
        $current .= $char;
        
        if ($char === ';' && !$inString) {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $queries[] = $trimmed;
            }
            $current = '';
        }
    }
    
    $remaining = trim($current);
    if ($remaining !== '') {
        $queries[] = $remaining;
    }
    
    return $queries;
}
?>