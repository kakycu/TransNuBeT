<?php
/**
 * download.php - Maneja las descargas de exportación
 * Debe ser llamado directamente, sin salida previa
 */

// Evitar que se envíe cualquier contenido antes de los headers
ob_start();

// Verificar si estamos en desarrollo para mostrar errores
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once 'export_functions.php';

// Configurar conexión a base de datos
$servername = "localhost";
$username = "root";
$password = "frl8110kaky";
$dbname = "sisfact_imdl";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Limpiar buffer y mostrar error
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Error de Conexión</h1>";
    echo "<p>No se pudo conectar a la base de datos: " . $conn->connect_error . "</p>";
    echo "<p><a href='index.php'>Volver al inicio</a></p>";
    exit;
}

$conn->set_charset("utf8mb4");

// Verificar si la base de datos existe
if (!$conn->select_db($dbname)) {
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Error de Base de Datos</h1>";
    echo "<p>La base de datos '$dbname' no existe o no se puede acceder.</p>";
    echo "<p><a href='index.php'>Volver al inicio</a></p>";
    exit;
}

function exportToFile($format, $tableName, $filename = null, $withDetails = false) {
    if ($filename === null) {
        $prefix = $withDetails ? 'completo' : $tableName;
        
        // FORMATO: nombrearchivo_055647AM-21022026.ext
        // Hora en formato 12h con AM/PM (05:56:47 AM -> 055647AM)
        // Fecha en formato ddmmaaaa (21-02-2026 -> 21022026)
        
        $hora12 = date('hisa'); // 055647am (o 055647pm)
        $fechaDMY = date('dmY'); // 21022026
        
        // Convertir AM/PM a mayúsculas para consistencia
        $hora12 = strtoupper($hora12); // 055647AM o 055647PM
        
        $filename = $prefix . '_' . $hora12 . '-' . $fechaDMY . '.' . $format;
    }
    
    switch ($format) {
        case 'json':
            $data = $withDetails ? $this->getFactWithDetailsJSON() : $this->getJSONData($tableName);
            $contentType = 'application/json';
            break;
        case 'xml':
            $data = $withDetails ? $this->getFactWithDetailsXMLFormatted(true) : $this->getXMLData($tableName, true);
            $contentType = 'application/xml';
            break;
        default:
            throw new Exception("Formato no válido: $format");
    }
    
    return [
        'filename' => $filename, 
        'contentType' => $contentType, 
        'data' => $data, 
        'format' => $format
    ];
}

$exporter = new FactExportImport($conn);

// Obtener parámetros
$exportType = $_GET['type'] ?? '';
$format = $_GET['format'] ?? '';
$table = $_GET['table'] ?? '';

// Validar parámetros
if (empty($exportType)) {
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Error de Parámetros</h1>";
    echo "<p>No se especificó el tipo de exportación.</p>";
    echo "<p><a href='index.php'>Volver al inicio</a></p>";
    exit;
}

try {
    $filename = '';
    $content = '';
    $contentType = '';
	
	//$fechaGeneracion = strtoupper(date('h:i:sa') .'-'.date('d/m/Y'));
	
    // Comprobar Horario verano
    if (date("I") == 0) {
        $fechaGeneracion = date('dmY') . "-" . date("hisA", strtotime('-1 hour'));
    } else {
        $fechaGeneracion = date('dmY') . "-" . date("hisA");
    }
	
	$fechaGeneracion = str_replace([':', '-', ' '], '', $fechaGeneracion);

	
    switch ($exportType) {
        case 'table':
            if (!in_array($table, ['tbl_fact', 'tbl_fact_detalle'])) {
                throw new Exception("Tabla no válida. Las tablas permitidas son: tbl_fact, tbl_fact_detalle");
            }
            
            if (empty($format)) {
                throw new Exception("No se especificó el formato (csv, json, xml)");
            }
            
            switch ($format) {
                case 'csv':
                    $filename = $table . '_' . $fechaGeneracion . '.csv';
                    $content = $exporter->getCSVData($table);
                    $contentType = 'text/csv; charset=utf-8';
                    break;
                    
                case 'json':
                    $filename = $table . '_' . $fechaGeneracion . '.json';
                    $content = $exporter->getJSONData($table);
                    $contentType = 'application/json; charset=utf-8';
                    break;
                    
                case 'xml':
                    $filename = $table . '_' . $fechaGeneracion . '.xml';
                    // Usar la nueva función con formato
                    $content = $exporter->getXMLData($table, true); // true para formato
                    $contentType = 'application/xml; charset=utf-8';
                    break;
                    
                default:
                    throw new Exception("Formato no soportado. Use: csv, json, xml");
            }
            break;
            
        case 'complete':
            if (empty($format)) {
                throw new Exception("No se especificó el formato (csv, json, xml)");
            }
            
            switch ($format) {
                case 'csv':
                    $filename = 'facturas_completas_' . $fechaGeneracion . '.csv';
                    $content = $exporter->getFactWithDetailsCSV();
                    $contentType = 'text/csv; charset=utf-8';
                    break;
                    
                case 'json':
                    $filename = 'facturas_completas_' . $fechaGeneracion . '.json';
                    $content = $exporter->getFactWithDetailsJSON();
                    $contentType = 'application/json; charset=utf-8';
                    break;
                    
                case 'xml':
                    $filename = 'facturas_completas_' . $fechaGeneracion . '.xml';
                    // IMPORTANTE: Usar la nueva función CON FORMATO
                    $content = $exporter->getFactWithDetailsXMLFormatted(true);
                    $contentType = 'application/xml; charset=utf-8';
                    break;
                    
                default:
                    throw new Exception("Formato no soportado. Use: csv, json, xml");
            }
            break;
            
        case 'backup':
			// Verificar si viene un nombre personalizado
			$customName = isset($_GET['custom_name']) ? $_GET['custom_name'] : '';
			
			// Llamar a la función con el nombre personalizado
			$backupFile = $exporter->backupDatabase($customName);
			
			if (!file_exists($backupFile)) {
				throw new Exception("No se pudo crear el archivo de Salvas en Formato SQL.");
			}
			
			$filename = basename($backupFile);
			$content = file_get_contents($backupFile);
			$contentType = 'application/sql';
			break;            
        default:
            throw new Exception("Tipo de exportación no válido. Use: table, complete, backup");
    }
    
    // Limpiar buffer de salida
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Enviar headers para descarga
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    echo $content;
    exit;
    
} catch (Exception $e) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Mostrar error detallado en desarrollo
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    
    header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>
<html>
<head>
    <title>Error en Descarga de Exportación</title>
    <link rel='icon' type='image/x-icon' href='../assets/logov.png'>
    
    <!-- Bootstrap 5 -->
    <link href='../css/bootstrap5.3.0/bootstrap.min.css' rel='stylesheet'>
    
    <!-- Font Awesome -->
    <link rel='stylesheet' href='../css/font-awesome6.4.0/css/all.min.css'>
    
    <style>
        :root {
            --win-bg-primary: #0d0d0d;
            --win-bg-secondary: #1f1f1f;
            --win-text-primary: #ffffff;
            --win-text-secondary: #a6a6a6;
            --win-border-color: #3d3d3d;
            --win-accent: #0078d4;
        }

        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: var(--win-bg-primary); 
            color: var(--win-text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        /* Efecto Mica */
        .container { 
            max-width: 700px; 
            background: rgba(31, 31, 31, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 35px; 
            border-radius: 12px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 { 
            color: #ff6b6b; 
            border-bottom: 2px solid #ff6b6b; 
            padding-bottom: 12px; 
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        h1 i {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .error { 
            background: rgba(45, 45, 45, 0.6);
            border-left: 4px solid #ff6b6b; 
            border-radius: 8px;
            padding: 20px; 
            margin: 20px 0;
            transition: 0.3s;
        }
        
        .error:hover {
            background: rgba(55, 55, 55, 0.8);
            transform: translateX(5px);
        }
        
        .trace { 
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px; 
            font-family: monospace; 
            font-size: 12px; 
            white-space: pre-wrap; 
            color: #a6e22e;
            margin-top: 15px;
        }
        
        .btn { 
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--win-accent); 
            color: white; 
            padding: 12px 25px; 
            text-decoration: none; 
            border-radius: 8px; 
            margin-top: 20px;
            transition: 0.3s;
            border: none;
        }
        
        .btn:hover { 
            background: #005bb5;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,120,212,0.3);
        }
        
        .btn i {
            transition: 0.3s;
        }
        
        .btn:hover i {
            transform: translateX(-3px);
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-exclamation-triangle'></i> Error en la Exportación</h1>
        
        <div class='error'>
            <h5 style='color: #ff6b6b; margin-bottom: 10px;'>
                <i class='fas fa-circle-info me-2'></i>Mensaje:
            </h5>
            <p class='mb-0' style='color: var(--win-text-secondary);'>$errorMessage</p>
        </div>";
            
// Solo mostrar trace en desarrollo
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    echo "<div class='error' style='border-left-color: var(--win-accent);'>
            <h5 style='color: var(--win-accent); margin-bottom: 10px;'>
                <i class='fas fa-code me-2'></i>Detalles Técnicos:
            </h5>
            <div class='trace'>$errorTrace</div>
          </div>";
}

echo "<a href='../dashboard.php' class='btn'>
            <i class='fas fa-arrow-left'></i>
            Regresar al Sistema
        </a>
    </div>
</body>
</html>";
    exit;
}
?>