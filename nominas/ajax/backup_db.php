<?php
/**
 * ajax/backup_db.php
 * Backup completo de la base de datos usando la misma lógica que FactExportImport
 * Genera SQL + ZIP en un solo archivo
 */

// Configuración regional y zona horaria (EXACTAMENTE como en FactExportImport)
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

// Configuración de la base de datos (desde database.php)
require_once '../config/database.php';

// Directorio de backups
$backup_dir = '../backups/';

// Crear directorio si no existe
if (!file_exists($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de backups']);
        exit;
    }
}

// Verificar permisos de escritura
if (!is_writable($backup_dir)) {
    echo json_encode(['success' => false, 'message' => 'El directorio de backups no tiene permisos de escritura']);
    exit;
}

try {
    global $pdo;
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
    
    // ============================================
    // NUEVO: Obtener nombre personalizado
    // ============================================
    $customName = isset($_GET['nombre_custom']) ? trim($_GET['nombre_custom']) : '';
    $customName = isset($_GET['custom_name']) ? trim($_GET['custom_name']) : $customName; // Soporte para ambos nombres
    
    // Si viene del modal de backup con nombre
    if (!empty($customName)) {
        // Sanitizar nombre (solo letras, números, guiones y guiones bajos)
        $customName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $customName);
        // Limitar longitud
        $customName = substr($customName, 0, 50);
        // Si queda vacío, usar nombre por defecto
        if (empty($customName)) {
            $customName = 'Backup_' . DB_NAME;
        }
    }
    
    // Obtener información del servidor
    $serverInfo = $pdo->query('SELECT VERSION() as version')->fetch(PDO::FETCH_ASSOC);
    $serverVersion = $serverInfo['version'];
    $phpVersion = phpversion();
    $mysqlClientVersion = mysqli_get_client_info();
    
    // Obtener todas las tablas
    $tables = [];
    $result = $pdo->query('SHOW TABLES');
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (empty($tables)) {
        throw new Exception("No se encontraron tablas en la base de datos");
    }
    
    // Fechas para encabezado (CON HORARIO DE VERANO CORREGIDO)
    if (date("I") == 0) {
        $fechaGeneracion = date('d-m-Y');
        $horaGeneracion = date("h:i:s A", strtotime('-1 hour'));
    } else {
        $fechaGeneracion = date('d-m-Y');
        $horaGeneracion = date("h:i:s A");
    }
    
    $usuario = $_SESSION['username'] ?? $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'sistema';
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    
    // ============================================
    // NUEVO: Generar nombre del archivo con nombre personalizado
    // ============================================
    $horaForFilename = str_replace([':', ' '], '', $horaGeneracion);
    $fechaForFilename = str_replace('-', '', $fechaGeneracion);
    
    if (!empty($customName)) {
        // Usar nombre personalizado + fecha/hora
        $filename = $customName . '_' . $horaForFilename . '-' . $fechaForFilename . '.sql';
        $zip_filename_final = $customName . '_' . $horaForFilename . '-' . $fechaForFilename . '.zip';
    } else {
        // Backup automático con nombre por defecto
        $filename = 'Backup_' . DB_NAME . '_' . $horaForFilename . '-' . $fechaForFilename . '.sql';
        $zip_filename_final = 'Backup_' . DB_NAME . '_' . $horaForFilename . '-' . $fechaForFilename . '.zip';
    }
    
    $sql_filepath = $backup_dir . $filename;
    $zip_filepath = $backup_dir . $zip_filename_final;
    
    // ============================================
    // CONSTRUIR EL CONTENIDO SQL
    // ============================================
    
    $content = "-- phpMyAdmin SQL Dump\n";
    $content .= "-- https://www.phpmyadmin.net/\n";
    $content .= "--\n";
    $content .= "-- Servidor: " . DB_HOST . "\n";
    $content .= "-- Versión del servidor: " . $serverVersion . "\n";
    $content .= "-- Versión de PHP: " . $phpVersion . "\n\n";
    
    $content .= "-- Exportaciones Nóminas " . (defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom') . " --\n";
    $content .= "-- Salva SQL de las Bases de Datos del Sistema de Nóminas " . (defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom') . "\n";
    $content .= "-- Webmaster: Franklin Ramos Lamadrid (kaky°)®\n";
    $content .= "-- Email: kakycu@gmail.com\n";
    $content .= "-- Copyright © 2025 - " . date('Y') . ".\n";
    $content .= "-- ------------------------------------------------------------------------------\n";
    $content .= "-- phpMyAdmin Volcado de Datos SQL.\n";
    $content .= "-- version cliente: " . $mysqlClientVersion . "\n";
    $content .= "-- https://www.pdl-visiones.cu/nominas\n";
    $content .= "-- https://nominas.pdl-visiones.cu/\n";
    $content .= "-- ------------------------------------------------------------------------------\n";
    $content .= "-- Nombre del Servidor: " . $serverName . "\n";
    $content .= "-- Dirección del Servidor: " . $serverAddr . "\n";
    $content .= "-- Tiempo de generación: " . $fechaGeneracion . " a las " . $horaGeneracion . "\n\n";
    
    // ============================================
    // NUEVO: Agregar información del nombre personalizado en el encabezado
    // ============================================
    if (!empty($customName)) {
        $content .= "-- ============================================\n";
        $content .= "-- BACKUP PERSONALIZADO: " . $customName . "\n";
        $content .= "-- Generado por: " . $usuario . "\n";
        $content .= "-- ============================================\n\n";
    }
    
    $content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $content .= "START TRANSACTION;\n";
    $content .= "SET time_zone = \"+00:00\";\n\n\n";
    
    $content .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $content .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $content .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $content .= "/*!40101 SET NAMES utf8mb4 */;\n\n";
    
    $content .= "DROP DATABASE IF EXISTS `" . DB_NAME . "`;\n";
    $content .= "CREATE DATABASE `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $content .= "USE `" . DB_NAME . "`;\n\n";
    
    $content .= "--\n-- Base de datos: `" . DB_NAME . "`\n--\n\n";
    
    $columnTypes = [];
    $virtualColumns = [];
    $indicesByTable = [];
    $constraintsByTable = [];
    $autoIncrementByTable = [];
    
    foreach ($tables as $table) {
        // Obtener información de columnas
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $columnTypes[$table] = [];
        $virtualColumns[$table] = [];
        $hasAutoIncrement = false;
        $autoIncrementColumn = null;
        $autoIncrementType = null;
        
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columnTypes[$table][$col['Field']] = $col['Type'];
            
            if (stripos($col['Extra'], 'auto_increment') !== false) {
                $hasAutoIncrement = true;
                $autoIncrementColumn = $col['Field'];
                $autoIncrementType = $col['Type'];
            }
            
            if (stripos($col['Extra'], 'generated') !== false || 
                stripos($col['Extra'], 'virtual') !== false || 
                stripos($col['Extra'], 'stored') !== false) {
                $virtualColumns[$table][] = $col['Field'];
            }
        }
        
        $content .= "-- --------------------------------------------------------\n\n";
        $content .= "--\n-- Estructura de tabla para la tabla `$table`\n--\n\n";
        
        // Obtener CREATE TABLE
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $rawSql = $row[1];
        
        // Extraer AUTO_INCREMENT actual
        $currentAutoIncrement = null;
        if (preg_match('/AUTO_INCREMENT=(\d+)/', $rawSql, $matches)) {
            $currentAutoIncrement = $matches[1];
        }
        
        // Guardar información de AUTO_INCREMENT
        if ($hasAutoIncrement) {
            $autoIncrementByTable[$table] = [
                'field' => $autoIncrementColumn,
                'type' => $autoIncrementType,
                'val' => $currentAutoIncrement !== null ? $currentAutoIncrement : 1
            ];
        }
        
        // Limpiar CREATE TABLE para la salida
        $lines = explode("\n", $rawSql);
        $cleanColumns = [];
        $tableIndices = [];
        $tableConstraints = [];
        $engineInfo = "";
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (preg_match('/^\) ENGINE=/', $trimmed)) {
                $engineLine = preg_replace('/ AUTO_INCREMENT=\d+/', '', $trimmed);
                $engineInfo = ltrim($engineLine, ')'); 
                continue;
            }
            
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY)/i', $trimmed)) {
                $tableIndices[] = rtrim($trimmed, ',');
                continue;
            }
            
            if (preg_match('/^CONSTRAINT/i', $trimmed)) {
                $tableConstraints[] = rtrim($trimmed, ',');
                continue;
            }
            
            if (preg_match('/^`/', $trimmed)) {
                $colDef = preg_replace('/ AUTO_INCREMENT/i', '', $trimmed);
                $cleanColumns[] = rtrim($colDef, ',');
            }
        }
        
        $content .= "CREATE TABLE `$table` (\n  " . implode(",\n  ", array_filter($cleanColumns)) . "\n)" . $engineInfo . ";\n\n";
        
        // Guardar índices y constraints
        if (!empty($tableIndices)) {
            $indicesByTable[$table] = $tableIndices;
        }
        if (!empty($tableConstraints)) {
            $constraintsByTable[$table] = $tableConstraints;
        }
        
        // VOLCADO DE DATOS
        $rows = $pdo->query("SELECT * FROM `$table`");
        $allRows = $rows->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($allRows) > 0) {
            $content .= "--\n-- Volcado de datos para la tabla `$table`\n--\n\n";
            
            $allColumnNames = array_keys($allRows[0]);
            $virtualForThisTable = $virtualColumns[$table] ?? [];
            $columnNamesForInsert = array_diff($allColumnNames, $virtualForThisTable);
            
            if (!empty($columnNamesForInsert)) {
                $columnsList = "`" . implode("`, `", $columnNamesForInsert) . "`";
                $content .= "INSERT INTO `$table` ($columnsList) VALUES\n";
                
                $dataRows = [];
                foreach ($allRows as $r) {
                    $vals = [];
                    foreach ($columnNamesForInsert as $colName) {
                        $v = $r[$colName];
                        if ($v === null) {
                            $vals[] = "NULL";
                        } else {
                            $colType = strtolower(trim($columnTypes[$table][$colName] ?? ''));
                            $isStrictInteger = preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(|$)/', $colType);
                            $isDecimalType = preg_match('/^(decimal|numeric|float|double|real)(\(|$)/', $colType);
                            
                            if ($isStrictInteger && !$isDecimalType) {
                                $vals[] = $v;
                            } else {
                                $vals[] = $pdo->quote((string)$v);
                            }
                        }
                    }
                    $dataRows[] = "(" . implode(", ", $vals) . ")";
                }
                $content .= implode(",\n", $dataRows) . ";\n\n";
            }
        }
    }
    
    // ============================================
    // ÍNDICES
    // ============================================
    if (!empty($indicesByTable)) {
        $content .= "--\n-- Índices para tablas volcadas\n--\n\n";
        foreach ($indicesByTable as $tableName => $indices) {
            $content .= "--\n-- Índices de la tabla `$tableName`\n--\n";
            $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $indices) . ";\n\n";
        }
    }
    
    // ============================================
    // AUTO_INCREMENT - CORREGIDO PARA TODAS LAS TABLAS
    // ============================================
    if (!empty($autoIncrementByTable)) {
        $content .= "--\n-- AUTO_INCREMENT de las tablas volcadas\n--\n\n";
        foreach ($autoIncrementByTable as $tableName => $info) {
            $content .= "--\n-- AUTO_INCREMENT de la tabla `$tableName`\n--\n";
            if ($info['field'] && $info['type']) {
                // Si tiene columna AUTO_INCREMENT
                $content .= "ALTER TABLE `$tableName`\n";
                $content .= "  MODIFY `{$info['field']}` {$info['type']} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$info['val']};\n\n";
            } else {
                // Si no tiene columna específica (por si acaso)
                $content .= "ALTER TABLE `$tableName` AUTO_INCREMENT = {$info['val']};\n\n";
            }
        }
    }
    
    // ============================================
    // FOREIGN KEYS
    // ============================================
    if (!empty($constraintsByTable)) {
        $content .= "--\n-- Restricciones para tablas volcadas\n--\n\n";
        foreach ($constraintsByTable as $tableName => $constraints) {
            $content .= "--\n-- Filtros para la tabla `$tableName`\n--\n";
            $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $constraints) . ";\n\n";
        }
    }
    
    $content .= "COMMIT;\n\n";
    $content .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $content .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $content .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
    
    // ============================================
    // GUARDAR ARCHIVO SQL
    // ============================================
    
    if (file_put_contents($sql_filepath, $content) === false) {
        throw new Exception("No se pudo escribir el archivo SQL");
    }
    
    // ============================================
    // COMPRIMIR EN ZIP
    // ============================================
    
    $zip = new ZipArchive();
    
    if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("No se pudo crear el archivo ZIP");
    }
    
    $zip->addFile($sql_filepath, $filename);
    $zip->close();
    
    // Eliminar el archivo SQL original
    unlink($sql_filepath);
    
    // ============================================
    // RESPUESTA
    // ============================================
    
    $zip_filesize = filesize($zip_filepath);
    
    if ($zip_filesize >= 1073741824) {
        $size_formatted = number_format($zip_filesize / 1073741824, 2) . ' GB';
    } elseif ($zip_filesize >= 1048576) {
        $size_formatted = number_format($zip_filesize / 1048576, 2) . ' MB';
    } elseif ($zip_filesize >= 1024) {
        $size_formatted = number_format($zip_filesize / 1024, 2) . ' KB';
    } else {
        $size_formatted = $zip_filesize . ' B';
    }
    
    $download_url = 'backups/' . basename($zip_filepath);
    
    echo json_encode([
        'success' => true,
        'filename' => basename($zip_filepath),
        'nombre' => !empty($customName) ? $customName : 'Backup automático',
        'size' => $size_formatted,
        'download_url' => $download_url,
        'message' => 'Backup generado exitosamente'
    ]);
    
} catch (PDOException $e) {
    error_log("Error PDO en backup_db.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en backup_db.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>