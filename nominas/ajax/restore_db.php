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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

// Configuración de la base de datos
require_once '../config/database.php';

// Verificar permisos: la restauración es solo para (Admin, Soft, Editor)
if (!in_array(permiso_rol_codigo(), ['Admin', 'Soft', 'Editor'], true)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para restaurar la base de datos', 'denied' => true]);
    exit;
}

// Liberar el lock de sesión para que el cliente pueda consultar el progreso en paralelo
session_write_close();

// Directorio temporal para procesar archivos
$temp_dir = '../temp/';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

// Archivo de progreso para que el cliente sondee el avance de la importación
$progressFile = $temp_dir . 'restore_progress_' . session_id() . '.json';

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
    $insertQueries = 0;
    
    $totalQueries = count($queries);
    $processed = 0;
    $lastTable = null;
    
    // Progreso inicial
    restore_write_progress($progressFile, 0, null, 'Analizando archivo SQL...', 0, $totalQueries);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        $processed++;
        
        // Detectar la tabla implicada y describir el paso actual
        $table = restore_get_table($query);
        $step = 'Procesando consulta';
        if ($table) {
            if (stripos($query, 'CREATE TABLE') === 0) {
                $step = 'Creando estructura: ' . $table;
            } elseif (stripos($query, 'DROP TABLE') === 0) {
                $step = 'Eliminando tabla: ' . $table;
            } elseif (stripos($query, 'ALTER TABLE') === 0) {
                $step = 'Alterando tabla: ' . $table;
            } elseif (stripos($query, 'INSERT INTO') === 0 || stripos($query, 'REPLACE INTO') === 0) {
                $step = 'Importando datos: ' . $table;
            } else {
                $step = 'Procesando tabla: ' . $table;
            }
        }
        
        try {
            // Determinar si es una consulta INSERT o REPLACE
            $isInsert = stripos($query, 'INSERT INTO') === 0 || 
                       stripos($query, 'REPLACE INTO') === 0;
            
            // Ejecutar consulta
            $result = $pdo->exec($query);
            
            if ($result !== false) {
                $successful++;
                if ($isInsert) {
                    $insertQueries++;
                    if ($result > 0) {
                        $importedRows += $result;
                    }
                    // Para INSERTs que retornan 0 (por ON DUPLICATE KEY UPDATE)
                    if ($result === 0) {
                        // Intentar contar con SELECT ROW_COUNT() para casos especiales
                        try {
                            $rowCount = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
                            if ($rowCount > 0) {
                                $importedRows += $rowCount;
                            }
                        } catch (Exception $e) {
                            // Si falla, mantener el resultado original
                        }
                    }
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
            if (stripos($e->getMessage(), 'packet') !== false || 
                stripos($e->getMessage(), 'gone away') !== false) {
                $errors[] = "CRÍTICO: Paquete demasiado grande. Se requiere aumentar max_allowed_packet en el servidor MySQL.";
                break;
            }
        }
        
        // Actualizar progreso (cada 25 consultas o al cambiar de tabla)
        $writeNow = ($processed % 25 === 0) || ($table !== $lastTable) || ($processed === $totalQueries);
        if ($writeNow) {
            $percent = $totalQueries > 0 ? round(($processed / $totalQueries) * 100) : 100;
            restore_write_progress($progressFile, $percent, $table, $step, $processed, $totalQueries);
            $lastTable = $table;
        }
    }
    
    // Progreso final
    restore_write_progress($progressFile, 100, null, 'Finalizando restauración...', $totalQueries, $totalQueries);
    
    // Restaurar foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Registrar en log
    $logEntry = [
        'date' => date('Y-m-d H:i:s'),
        'filename' => $originalName,
        'successful' => $successful,
        'failed' => $failed,
        'imported_rows' => $importedRows,
        'insert_queries' => $insertQueries,
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
    $message .= "✅ Consultas ejecutadas: " . $successful . "\n";
    $message .= "📊 Filas insertadas/actualizadas: " . number_format($importedRows) . "\n";
    $message .= "📝 Consultas INSERT/REPLACE: " . $insertQueries . "\n";
    
    // Agregar información sobre tablas procesadas
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if ($tables) {
            $message .= "📋 Tablas en la base de datos: " . count($tables) . "\n";
        }
    } catch (Exception $e) {
        // Ignorar
    }
    
    if ($failed > 0) {
        $message .= "\n⚠️ Consultas fallidas: $failed\n";
        if (count($errors) > 0) {
            $message .= "\n❌ Errores:\n";
            $message .= implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $message .= "\n... y " . (count($errors) - 10) . " errores más";
            }
        }
    }
    
    echo json_encode([
        'success' => ($failed === 0 || $successful > 0),
        'message' => $message,
        'data' => [
            'successful_queries' => $successful,
            'failed_queries' => $failed,
            'imported_rows' => $importedRows,
            'insert_queries' => $insertQueries,
            'total_queries' => count($queries),
            'errors' => $errors
        ]
    ]);
    
} catch (PDOException $e) {
    // Intentar restaurar foreign keys
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $ex) {}
    
    if (isset($progressFile)) {
        restore_write_progress($progressFile, 0, null, 'Error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($progressFile)) {
        restore_write_progress($progressFile, 0, null, 'Error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Escribe el avance de la restauración en un archivo JSON para sondeo del cliente
 */
function restore_write_progress($file, $percent, $table = null, $step = '', $processed = null, $total = null) {
    $data = [
        'percent' => (int)$percent,
        'table' => $table,
        'step' => $step
    ];
    if ($processed !== null) {
        $data['processed'] = (int)$processed;
    }
    if ($total !== null) {
        $data['total'] = (int)$total;
    }
    file_put_contents($file, json_encode($data));
}

/**
 * Extrae el nombre de la tabla implicada en una consulta SQL
 */
function restore_get_table($query) {
    if (preg_match('/^(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|TRUNCATE(?:\s+TABLE)?|ALTER\s+TABLE)\s+`?([^\s`(;]+)`?/i', $query, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Divide un script SQL en consultas individuales
 * Respeta cadenas de texto con comillas y escapado
 * Maneja DELIMITER y procedimientos almacenados
 */
function splitSQLQueries($sql) {
    // Remover comentarios de línea y bloque
    $sql = removeSQLComments($sql);
    
    // Manejar DELIMITER
    $delimiter = ';';
    $queries = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $len = strlen($sql);
    $i = 0;
    
    // Buscar y procesar DELIMITER
    while ($i < $len) {
        // Buscar DELIMITER al inicio de línea (ignorando espacios)
        if (preg_match('/^\s*DELIMITER\s+([^\s]+)/i', substr($sql, $i), $matches)) {
            $delimiter = $matches[1];
            $i += strlen($matches[0]);
            // Saltar hasta el siguiente DELIMITER o fin
            continue;
        }
        
        $char = $sql[$i];
        
        if ($escaped) {
            $current .= $char;
            $escaped = false;
            $i++;
            continue;
        }
        
        if ($char === '\\') {
            $current .= $char;
            $escaped = true;
            $i++;
            continue;
        }
        
        // Manejar cadenas
        if (($char === "'" || $char === '"')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            $current .= $char;
            $i++;
            continue;
        }
        
        $current .= $char;
        
        // Verificar si encontramos el delimitador
        if (!$inString && substr($current, -strlen($delimiter)) === $delimiter) {
            // Remover el delimitador del final
            $trimmed = trim(substr($current, 0, -strlen($delimiter)));
            if ($trimmed !== '') {
                $queries[] = $trimmed;
            }
            $current = '';
        }
        
        $i++;
    }
    
    // Si queda algo sin delimitador
    $remaining = trim($current);
    if ($remaining !== '') {
        $queries[] = $remaining;
    }
    
    return $queries;
}

/**
 * Remueve comentarios SQL de manera segura
 */
function removeSQLComments($sql) {
    $result = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $inLineComment = false;
    $inBlockComment = false;
    $i = 0;
    
    while ($i < $len) {
        $char = $sql[$i];
        
        // Manejar caracteres escapados
        if ($escaped) {
            $escaped = false;
            $i++;
            continue;
        }
        
        if ($char === '\\') {
            $escaped = true;
            $i++;
            continue;
        }
        
        // Manejar cadenas de texto
        if (($char === "'" || $char === '"') && !$inLineComment && !$inBlockComment) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            $result .= $char;
            $i++;
            continue;
        }
        
        // Si estamos dentro de una cadena, agregar todo
        if ($inString) {
            $result .= $char;
            $i++;
            continue;
        }
        
        // Comentario de línea --
        if (!$inBlockComment && !$inLineComment && $char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            $inLineComment = true;
            $i += 2;
            continue;
        }
        
        // Comentario de línea #
        if (!$inBlockComment && !$inLineComment && $char === '#') {
            $inLineComment = true;
            $i++;
            continue;
        }
        
        // Comentario de bloque /* */
        if (!$inBlockComment && !$inLineComment && $char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $inBlockComment = true;
            $i += 2;
            continue;
        }
        
        // Cerrar comentario de bloque
        if ($inBlockComment && $char === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
            $inBlockComment = false;
            $i += 2;
            continue;
        }
        
        // Cerrar comentario de línea
        if ($inLineComment && ($char === "\n" || $char === "\r")) {
            $inLineComment = false;
            $result .= $char;
            $i++;
            continue;
        }
        
        // Si estamos en un comentario, no agregamos nada
        if ($inLineComment || $inBlockComment) {
            $i++;
            continue;
        }
        
        // Agregar caracter normal
        $result .= $char;
        $i++;
    }
    
    return $result;
}
?>