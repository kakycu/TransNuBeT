<?php
// export-import/modal_view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Evitar cualquier salida previa
while (ob_get_level()) ob_end_clean();
ob_start();

// Configuración de respuesta JSON por defecto
$response = ['success' => false, 'message' => 'Error desconocido'];

// Configuración de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

// Función de depuración
function debug_log($message) {
    error_log("[modal_view.php] " . $message);
}

debug_log("Iniciando modal_view.php");

// Verificación de seguridad
if (!isset($_SESSION['usuario_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'La Sesión ha expirado. Reingrese'], JSON_UNESCAPED_UNICODE));
}

$usuarioRolId = $_SESSION['usuario_rol'] ?? 0;
if (!in_array($usuarioRolId, [1, 4, 5])) {
    ob_end_clean();
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'No tiene permisos para acceder a esta herramienta.']));
}

// Configuración de base de datos
require_once 'export_functions.php';

$servername = "localhost";
$username = "root";
$password = "frl8110kaky";
$dbname = "sisfact_imdl";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    
    if (class_exists('FactExportImport')) {
        $exporter = new FactExportImport($conn);
    } else {
        throw new Exception("Clase FactExportImport no encontrada");
    }
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]));
}

// Procesamiento de IMPORTACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import') {
    ob_end_clean();
    header('Content-Type: application/json');
    header('Content-Type: application/json; charset=utf-8');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($exporter)) {
            throw new Exception("Sistema no inicializado.");
        }
        
        debug_log("=== INICIO IMPORTACIÓN POST ===");
        debug_log("POST: " . print_r($_POST, true));
        debug_log("FILES: " . print_r($_FILES, true));
        
        if (!isset($_FILES['file'])) {
            throw new Exception("No se recibió ningún archivo");
        }
        
        if ($_FILES['file']['error'] != 0) {
            $errorMsg = "Error al subir archivo";
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $errorMsg = "El archivo excede el tamaño máximo permitido por PHP";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = "El archivo excede el tamaño máximo del formulario";
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
                case UPLOAD_ERR_EXTENSION:
                    $errorMsg = "Una extensión de PHP detuvo la subida del archivo";
                    break;
            }
            throw new Exception($errorMsg);
        }
        
        $table = $_POST['table'] ?? '';
        $format = $_POST['format'] ?? '';
        $filePath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        debug_log("Archivo: $fileName");
        debug_log("Extensión: $fileExtension");
        debug_log("Tamaño: $fileSize bytes");
        debug_log("Formato seleccionado: $format");
        debug_log("Tabla: $table");
        
        // Validar archivo temporal
        if (!file_exists($filePath)) {
            throw new Exception("El archivo temporal no existe: $filePath");
        }
        
        // Procesar según formato
        if ($format == 'sql' || $fileExtension == 'sql') {
            debug_log("Procesando como archivo SQL");
            
            $validationErrors = $exporter->validateSQLFile($filePath, $fileName);
            debug_log("Errores de validación SQL: " . print_r($validationErrors, true));
            
            if (!empty($validationErrors)) {
                throw new Exception(implode("\n", $validationErrors));
            }
            
            $dropExisting = isset($_POST['drop_tables']) && $_POST['drop_tables'] == '1';
            $importResult = $exporter->importFromSQL($filePath, $dropExisting);
            
            debug_log("Resultado importación SQL: " . print_r($importResult, true));
            
            $response = [
                'success' => true,
                'message' => 'Importación SQL completada',
                'data' => $importResult
            ];
            
        } else {
            debug_log("Procesando como JSON/XML");
            
            // Validar extensión
            if ($format == 'json' && $fileExtension != 'json') {
                throw new Exception("El formato seleccionado es JSON pero el archivo subido tiene extensión .{$fileExtension}");
            }
            if ($format == 'xml' && $fileExtension != 'xml') {
                throw new Exception("El formato seleccionado es XML pero el archivo subido tiene extensión .{$fileExtension}");
            }
            
            // Validar contenido
            $validationErrors = $exporter->validateImportFile($filePath, $format);
            debug_log("Errores de validación: " . print_r($validationErrors, true));
            
            if (!empty($validationErrors)) {
                throw new Exception(implode("\n", $validationErrors));
            }
            
            // Importar
            if ($format == 'json') {
                debug_log("Iniciando importFromJSON con table: $table");
                $importResult = $exporter->importFromJSON($filePath, $table);
            } else {
                debug_log("Iniciando importFromXML con table: $table");
                $importResult = $exporter->importFromXML($filePath, $table);
            }
            
            debug_log("Resultado importación: " . print_r($importResult, true));
            
            $response = [
                'success' => true,
                'message' => 'Importación completada',
                'data' => $importResult
            ];
        }
        
        debug_log("=== FIN IMPORTACIÓN EXITOSA ===");
        
    } catch (Exception $e) {
        debug_log("=== ERROR EN IMPORTACIÓN ===");
        debug_log("Mensaje: " . $e->getMessage());
        debug_log("Archivo: " . $e->getFile() . " línea " . $e->getLine());
        debug_log("Trace: " . $e->getTraceAsString());
        
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    // Limpiar UTF-8
    function cleanUtf8Strings($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = cleanUtf8Strings($value);
            }
            return $data;
        } elseif (is_string($data)) {
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            $data = iconv('UTF-8', 'UTF-8//IGNORE', $data);
            return $data;
        } else {
            return $data;
        }
    }
    
    $response = cleanUtf8Strings($response);
    
    $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    $jsonResponse = json_encode($response, $jsonOptions);
    
    if ($jsonResponse === false) {
        $simpleResponse = [
            'success' => false,
            'message' => 'Error al procesar la respuesta: ' . json_last_error_msg()
        ];
        $jsonResponse = json_encode($simpleResponse);
    }
    
    echo $jsonResponse;
    exit;
}

// Si no es POST, continuar con visualización normal
ob_end_clean();

// Función de estadísticas
function getSystemStats($conn) {
    $stats = ['total_facturas' => 0, 'total_detalles' => 0, 'ultima_factura' => 'N/A'];
    if (!$conn || $conn->connect_error) return $stats;
    
    try {
        $res = $conn->query("SELECT COUNT(*) as total FROM tbl_fact");
        if ($res) {
            $stats['total_facturas'] = (int)$res->fetch_assoc()['total'];
        }
        
        $res = $conn->query("SELECT COUNT(*) as total FROM tbl_fact_detalle");
        if ($res) {
            $stats['total_detalles'] = (int)$res->fetch_assoc()['total'];
        }
        
        $res = $conn->query("SELECT MAX(fecha_emision) as ultima FROM tbl_fact");
        if ($res) {
            $u = $res->fetch_assoc()['ultima'];
            $stats['ultima_factura'] = $u ? date('d/m/Y', strtotime($u)) : 'N/A';
        }
    } catch (Exception $e) {
        debug_log("Error en getSystemStats: " . $e->getMessage());
    }
    return $stats;
}
function getAllTablesWithCounts($conn) {
    $tables = [];
    
    try {
        // Verificar conexión
        if (!$conn || $conn->connect_error) {
            return [];
        }
        
        // Obtener todas las tablas
        $result = $conn->query("SHOW TABLES");
        if (!$result) {
            return [];
        }
        
        while ($row = $result->fetch_array()) {
            $tableName = $row[0];
            
            // Contar registros
            $countResult = $conn->query("SELECT COUNT(*) as total FROM `$tableName`");
            $count = 0;
            if ($countResult) {
                $rowCount = $countResult->fetch_assoc();
                $count = $rowCount['total'];
            }
            
            $tables[] = [
                'name' => $tableName,
                'count' => $count
            ];
        }
    } catch (Exception $e) {
        // Silenciar error
        return [];
    }
    
    return $tables;
}


// Obtener la última factura (número y fecha) - VERSIÓN CORREGIDA
function getLastInvoiceInfo($conn) {
    // Verificar si la tabla tiene datos
    $checkQuery = "SELECT COUNT(*) as total FROM tbl_fact";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult) {
        $totalRows = $checkResult->fetch_assoc()['total'];
        
        if ($totalRows > 0) {
            // Obtener la última factura
            $query = "SELECT no_fact, fecha_emision FROM tbl_fact ORDER BY fecha_emision DESC, id DESC LIMIT 1";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return [
                    'numero' => $row['no_fact'],
                    'fecha' => date('d/m/Y', strtotime($row['fecha_emision']))
                ];
            }
        }
    }
    
    // Si no hay datos o hay error
    return [
        'numero' => '??',
        'fecha' => 'N/A'
    ];
}

// Obtener la información de la última factura
$lastInvoiceInfo = getLastInvoiceInfo($conn);


// Obtener todas las tablas
$allTables = getAllTablesWithCounts($conn);
$stats = (isset($conn) && !$conn->connect_error) ? getSystemStats($conn) : ['total_facturas' => 0, 'total_detalles' => 0, 'ultima_factura' => 'N/A'];
?>

<!-- ESTILOS UNIFICADOS -->
<style>
    #toolsModalContainer {
        --bg-primary: #11141d;
        --bg-secondary: #1a1d28;
        --bg-card: #222736;
        --text-primary: #ffffff;
        --text-secondary: #e1e5eb;
        --text-muted: #c0c6d4;
        --border-color: #32394e;
        --primary-color: #556ee6;
        --primary-light: rgba(85, 110, 230, 0.15);
        --success-color: #34c38f;
        --warning-color: #f1b44c;
        --sql-color: #f97316;
        --radius: 8px;
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        padding: 15px;
        border-radius: var(--radius);
    }

    /* Header */
    #toolsModalContainer .modal-header-tools { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
    }
    
    #toolsModalContainer .logo-section { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
    }
    
    #toolsModalContainer .logo-img { 
        width: 40px; 
        height: 40px; 
        object-fit: contain; 
    }
    
    #toolsModalContainer .logo-text h2 { 
        font-size: 1.1rem; 
        margin: 0; 
        font-weight: 700; 
        color: var(--text-primary);
    }
    
    #toolsModalContainer .logo-text p { 
        font-size: 0.7rem; 
        margin: 0; 
        color: var(--text-secondary);
    }

    /* Stats Grid */
    #toolsModalContainer .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 10px; 
        margin-bottom: 20px; 
    }
    
    #toolsModalContainer .stat-card { 
        background: var(--bg-card); 
        border-radius: var(--radius); 
        padding: 12px; 
        border: 1px solid var(--border-color); 
        display: flex; 
        align-items: center; 
        gap: 12px; 
    }
    
    #toolsModalContainer .stat-icon { 
        font-size: 1.5rem; 
        opacity: 0.9; 
    }
    
    #toolsModalContainer .stat-content h3 { 
        font-size: 1.2rem; 
        margin: 0; 
        font-weight: 700; 
        color: var(--text-primary); 
    }
    
    #toolsModalContainer .stat-content p { 
        font-size: 0.65rem; 
        color: var(--text-secondary); 
        text-transform: uppercase; 
        margin: 0; 
        letter-spacing: 0.3px; 
    }

    /* Tabs */
    #toolsModalContainer .tab-navigation { 
        display: flex; 
        gap: 5px; 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        padding: 4px; 
        border-radius: var(--radius); 
        border: 1px solid var(--border-color); 
    }
    
    #toolsModalContainer .tab-btn { 
        flex: 1; 
        padding: 8px; 
        border: none; 
        background: transparent; 
        color: var(--text-secondary); 
        cursor: pointer; 
        border-radius: 5px; 
        font-weight: 600; 
        font-size: 0.8rem; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 6px; 
        transition: all 0.2s; 
    }
    
    #toolsModalContainer .tab-btn:hover { 
        color: var(--text-primary); 
        background: rgba(255,255,255,0.05); 
    }
    
    #toolsModalContainer .tab-btn.active { 
        background: var(--primary-color); 
        color: white; 
    }

    /* Sub-tabs */
    #toolsModalContainer .sub-tab-navigation { 
        display: flex; 
        gap: 5px; 
        margin-bottom: 15px; 
        background: var(--bg-primary); 
        padding: 4px; 
        border-radius: var(--radius); 
        border: 1px solid var(--border-color);
    }
    
    #toolsModalContainer .sub-tab-btn { 
        flex: 1; 
        padding: 6px; 
        border: none; 
        background: transparent; 
        color: var(--text-secondary); 
        cursor: pointer; 
        border-radius: 5px; 
        font-weight: 600; 
        font-size: 0.75rem; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 4px; 
        transition: all 0.2s; 
    }
    
    #toolsModalContainer .sub-tab-btn:hover { 
        color: var(--text-primary); 
        background: rgba(255,255,255,0.05); 
    }
    
    #toolsModalContainer .sub-tab-btn.active { 
        background: var(--sql-color); 
        color: white; 
    }

    /* Cards */
    #toolsModalContainer .card { 
        background: var(--bg-card); 
        border-radius: var(--radius); 
        padding: 15px; 
        border: 1px solid var(--border-color); 
        margin-bottom: 15px; 
    }
    
    #toolsModalContainer .card-title { 
        font-size: 0.95rem; 
        margin-bottom: 12px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        border-bottom: 1px solid var(--border-color); 
        padding-bottom: 8px; 
        font-weight: 700; 
        color: var(--text-primary);
    }
    
    #toolsModalContainer .card-title i { 
        color: var(--primary-color); 
    }
    
    #toolsModalContainer .export-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 10px; 
    }
    
    #toolsModalContainer .export-card { 
        background: var(--bg-primary); 
        padding: 12px; 
        border-radius: 6px; 
        border: 1px solid var(--border-color); 
        display: flex; 
        flex-direction: column;
        transition: all 0.2s;
    }
    
    #toolsModalContainer .export-card:hover { 
        border-color: var(--primary-color); 
    }
    
    #toolsModalContainer .export-title { 
        font-size: 0.9rem; 
        font-weight: 700; 
        margin-bottom: 4px; 
        color: var(--text-primary);
    }
    
    #toolsModalContainer .export-desc { 
        font-size: 0.75rem; 
        color: var(--text-secondary); 
        margin-bottom: 10px; 
        flex-grow: 1;
        line-height: 1.4;
    }

    /* Botones */
    #toolsModalContainer .btn { 
        padding: 6px 12px; 
        border-radius: 4px; 
        border: none; 
        cursor: pointer; 
        display: inline-flex; 
        align-items: center; 
        gap: 5px; 
        font-size: 0.8rem; 
        font-weight: 600; 
        transition: 0.2s; 
        text-decoration: none; 
    }
    
    #toolsModalContainer .btn-primary { 
        background: var(--primary-color); 
        color: white; 
    }
    
    #toolsModalContainer .btn-success { 
        background: var(--success-color); 
        color: white; 
    }
    
    #toolsModalContainer .btn-warning { 
        background: var(--warning-color); 
        color: #1a1d28; 
    }
    
    #toolsModalContainer .btn-sql { 
        background: var(--sql-color); 
        color: white; 
    }
    
    #toolsModalContainer .btn-outline { 
        background: transparent; 
        border: 1px solid var(--border-color); 
        color: var(--text-primary); 
    }
    
    #toolsModalContainer .btn-outline:hover { 
        background: rgba(255,255,255,0.05); 
        border-color: var(--primary-color);
    }
    
    #toolsModalContainer .btn:hover { 
        filter: brightness(1.1); 
        transform: translateY(-1px); 
    }

    /* Formularios */
    #toolsModalContainer .form-group { 
        margin-bottom: 12px; 
    }
    
    #toolsModalContainer .form-label { 
        display: block; 
        font-size: 0.8rem; 
        margin-bottom: 4px; 
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    #toolsModalContainer select,
    #toolsModalContainer .form-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        padding: 8px 30px 8px 12px;
        border-radius: 4px;
        width: 100%;
        font-size: 0.85rem;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23e1e5eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 16px;
    }
    
    #toolsModalContainer select option,
    #toolsModalContainer .form-select option {
        background-color: var(--bg-card);
        color: var(--text-primary);
        padding: 10px;
    }
    
    #toolsModalContainer select:focus,
    #toolsModalContainer .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(85, 110, 230, 0.25);
    }
    
    #toolsModalContainer input[type="file"] {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        padding: 6px 8px;
        border-radius: 4px;
        width: 100%;
        font-size: 0.85rem;
    }
    
    #toolsModalContainer input[type="file"]::-webkit-file-upload-button {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 3px;
        margin-right: 10px;
        cursor: pointer;
    }
    
    #toolsModalContainer .format-hint {
        font-size: 0.7rem;
        color: var(--warning-color);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    #toolsModalContainer .info-message {
        background: var(--primary-light);
        padding: 12px;
        border-radius: var(--radius);
        margin-bottom: 15px;
        border-left: 3px solid var(--primary-color);
    }
    
    #toolsModalContainer .info-message p {
        margin: 0;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-primary);
    }
    
    #toolsModalContainer .sql-warning {
        background: rgba(249, 115, 22, 0.1);
        border-left: 3px solid var(--sql-color);
        padding: 12px;
        border-radius: var(--radius);
        margin-bottom: 15px;
        font-size: 14px;
    }
    
    #toolsModalContainer .sql-warning i {
        color: var(--sql-color);
    }
    
    #toolsModalContainer code { 
        background: var(--bg-primary); 
        color: var(--warning-color); 
        padding: 2px 4px; 
        border-radius: 4px; 
        font-family: monospace;
        font-size: 0.85rem;
    }

    /* Grid system */
    #toolsModalContainer .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -8px;
        margin-left: -8px;
    }

    #toolsModalContainer .col-12,
    #toolsModalContainer .col-md-8,
    #toolsModalContainer .col-md-4 {
        position: relative;
        width: 100%;
        padding-right: 8px;
        padding-left: 8px;
    }

    #toolsModalContainer .col-md-8 {
        flex: 0 0 66.666667%;
        max-width: 66.666667%;
    }

    #toolsModalContainer .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }

    #toolsModalContainer .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
    }

    #toolsModalContainer .w-100 {
        width: 100%;
    }

    #toolsModalContainer .mt-1 {
        margin-top: 4px;
    }

    #toolsModalContainer .mb-1 {
        margin-bottom: 4px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #toolsModalContainer .stats-grid, 
        #toolsModalContainer .export-grid,
        #toolsModalContainer .col-md-8,
        #toolsModalContainer .col-md-4 {
            grid-template-columns: 1fr;
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Logo fallback */
    #toolsModalContainer .logo-icon-placeholder { 
        width: 40px; 
        height: 40px; 
        background: var(--primary-color); 
        border-radius: 8px; 
        display: none; 
        align-items: center; 
        justify-content: center;
    }

    #toolsModalContainer .logo-icon-placeholder i { 
        color: white; 
        font-size: 1.2rem;
    }
.swal2-container {
    pointer-events: all !important;
    z-index: 99999 !important;
}

.swal2-popup {
    pointer-events: all !important;
}

/* Si usas Bootstrap, esto evita que el modal de fondo bloquee el teclado */
body.swal2-shown {
    overflow: hidden;
}
</style>
<div id="toolsModalContainer">
<!-- Header - MODIFICADO con botón de recargar -->
<header class="modal-header-tools">
    <div class="logo-section">
        <img src="assets/logov.png" 
             alt="Logo" 
             class="logo-img" 
             id="mainLogo"
             onerror="tryAlternateLogo(this)">
        <div id="logoFallback" class="logo-icon-placeholder" style="display:none;">
            <i class="fas fa-bolt"></i>
        </div>
        <div class="logo-text">
            <h2>SISFACT PDL Visiones</h2>
            <p>Gestión de Importación & Exportación</p>
        </div>
    </div>
    
    <!-- Botón de recargar añadido -->
    <button onclick="reloadTools()" class="btn btn-outline" style="padding: 8px 15px; font-size: 0.9rem;" title="Recargar herramienta">
        <i class="fas fa-sync-alt"></i> Recargar
    </button>
</header>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card" style="border-top: 3px solid var(--primary-color)">
            <i class="fas fa-file-invoice stat-icon" style="color: var(--primary-color)"></i>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_facturas']); ?></h3>
                <p>Facturas</p>
            </div>
        </div>
        <div class="stat-card" style="border-top: 3px solid var(--success-color)">
            <i class="fas fa-list-ul stat-icon" style="color: var(--success-color)"></i>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_detalles']); ?></h3>
                <p>Detalles</p>
            </div>
        </div>
        <div class="stat-card" style="border-top: 3px solid var(--warning-color)">
            <i class="fas fa-clock stat-icon" style="color: var(--warning-color)"></i>
            <div class="stat-content">
                <h3><?php echo $stats['ultima_factura']; ?></h3>
                <p>Último Registro</p>
            </div>
        </div>
    </div>

    <!-- Navegación principal -->
    <nav class="tab-navigation">
        <button class="tab-btn active" onclick="switchToolsTab('export', this)">
            <i class="fas fa-download"></i> Exportar
        </button>
        <button class="tab-btn" onclick="switchToolsTab('import', this)">
            <i class="fas fa-upload"></i> Importar
        </button>
        <button class="tab-btn" onclick="switchToolsTab('sql', this)">
            <i class="fas fa-database"></i> SQL
        </button>
        <button class="tab-btn" onclick="switchToolsTab('help', this)">
            <i class="fas fa-question-circle"></i> Ayuda
        </button>
    </nav>

    <!-- Contenido: Exportar -->
    <div id="tools-export" class="tools-tab-content">
        <div class="card">
            <h2 class="card-title"><i class="fas fa-file-export"></i> Descarga de Datos (JSON/XML)</h2>
            <div class="export-grid">
                <div class="export-card">
                    <p class="export-title">📋 Tabla: tbl_fact</p>
                    <p class="export-desc">Cabeceras de facturación general.</p>
                    <div style="display:flex; gap:5px">
                        <button class="btn btn-success" onclick="runExport('tbl_fact', 'json')">
                            <i class="fas fa-file-code"></i> JSON
                        </button>
                        <button class="btn btn-warning" onclick="runExport('tbl_fact', 'xml')">
                            <i class="fas fa-file-code"></i> XML
                        </button>
                    </div>
                </div>
                <div class="export-card">
                    <p class="export-title">🔍 Tabla: Detalles</p>
                    <p class="export-desc">Items y líneas detalladas.</p>
                    <div style="display:flex; gap:5px">
                        <button class="btn btn-success" onclick="runExport('tbl_fact_detalle', 'json')">
                            <i class="fas fa-file-code"></i> JSON
                        </button>
                        <button class="btn btn-warning" onclick="runExport('tbl_fact_detalle', 'xml')">
                            <i class="fas fa-file-code"></i> XML
                        </button>
                    </div>
                </div>
                <div class="export-card">
                    <p class="export-title">📦 Relacional</p>
                    <p class="export-desc">Pack completo vinculado.</p>
                    <div style="display:flex; gap:5px">
                        <button class="btn btn-primary" onclick="runExportComplete('json')">
                            <i class="fas fa-file-code"></i> JSON
                        </button>
                        <button class="btn btn-primary" onclick="runExportComplete('xml')">
                            <i class="fas fa-file-code"></i> XML
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido: Importar JSON/XML -->
    <div id="tools-import" class="tools-tab-content" style="display:none">
        <div class="card">
            <h2 class="card-title"><i class="fas fa-file-import"></i> Cargar Datos Externos (JSON/XML)</h2>
            
            <div class="info-message">
                <p>
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Importante:</strong> El formato que selecciones debe coincidir con el tipo de archivo que subas.</span>
                </p>
            </div>
            
            <form id="toolsImportForm">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-database"></i> Tabla de destino:</label>
                            <select id="toolsImportType" name="table" class="form-select" required>
                                <option value="">Seleccionar destino...</option>
                                <option value="complete">📦 Relacional Completa (facturas + detalles)</option>
                                <option value="tbl_fact">📋 Solo Cabeceras (tbl_fact)</option>
                                <option value="tbl_fact_detalle">🔍 Solo Detalles (tbl_fact_detalle)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-file-code"></i> Formato:</label>
                            <select id="toolsImportFormat" name="format" class="form-select" required onchange="updateFileAcceptAttribute()">
                                <option value="json">📄 JSON</option>
                                <option value="xml">📄 XML</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-upload"></i> Seleccionar archivo:</label>
                            <input type="file" id="toolsImportFile" name="file" class="form-control" accept=".json,.xml" required>
                            <div id="formatHint" class="format-hint mt-1">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="formatHintText">El archivo debe tener extensión <strong>.json</strong></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100" style="padding:12px">
                            <i class="fas fa-cloud-upload-alt"></i> Subir y Procesar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- Contenido: SQL -->
<div id="tools-sql" class="tools-tab-content" style="display:none">
    <div class="card">
        <h2 class="card-title"><i class="fas fa-database"></i> Gestión de Base de Datos</h2>
        
        <nav class="sub-tab-navigation">
            <button class="sub-tab-btn active" onclick="switchSQLSubTab('sql-import', this)">
                <i class="fas fa-upload"></i> Importar SQL
            </button>
            <button class="sub-tab-btn" onclick="switchSQLSubTab('sql-export', this)">
                <i class="fas fa-download"></i> Exportar SQL (Backup)
            </button>
            <button class="sub-tab-btn" onclick="switchSQLSubTab('sql-excel', this)">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </button>
        </nav>
        
        <!-- Importar SQL -->
        <div id="sql-import" class="sql-sub-content">
            <div class="sql-warning">
                <p style="margin:0; display:flex; align-items:center; gap:8px; color:var(--text-primary);">
                    <i class="fas fa-exclamation-triangle" style="color: var(--sql-color); font-size:1rem;"></i>
                    <span><strong style="color:var(--sql-color);"> Precaución:</strong> La importación SQL reemplazará o modificará datos existentes. Asegúrate de tener un backup antes de continuar.</span>
                </p>
            </div>
            
            <form id="toolsSQLImportForm">
                <div class="row g-3">
                    <input type="hidden" name="format" value="sql">
                    <input type="hidden" name="table" value="complete">
                    <input type="hidden" name="drop_tables" value="1">
                    
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-upload"></i> Seleccionar archivo SQL:</label>
                            <input type="file" id="toolsSQLFile" name="file" class="form-control" accept=".sql" required>
                            <div class="format-hint mt-1">
                                <i class="fas fa-info-circle"></i>
                                <span>El archivo debe tener extensión <strong>.sql</strong> (máximo 300MB)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-sql" style="flex: 2; padding:12px;">
                                <i class="fas fa-cloud-upload-alt"></i> Restaurar desde Salva SQL
                            </button>
                            <button type="button" class="btn btn-outline" onclick="showSQLLog()" style="flex: 1; padding:12px;">
                                <i class="fas fa-history"></i> Historial
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Exportar SQL -->
        <div id="sql-export" class="sql-sub-content" style="display:none;">
            <div class="export-card" style="max-width: 100%; width: 1400px; margin: 0 auto; text-align: center; background:var(--bg-primary);">
                <p class="export-title">💾 Backup de Base de Datos Completa</p>
                <p class="export-desc">Genera un archivo SQL con toda la estructura y datos de la base de datos.</p>
                
                <?php if (!empty($allTables)): ?>
                <div style="background:#1a1d28; border:1px solid #32394e; border-radius:8px; padding:16px; margin:15px 0; color:#e1e5eb; text-align:left; font-family: system-ui, -apple-system, sans-serif;">
                    
                    <!-- Primera línea: Base de Datos y Última Factura -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #32394e;">
                        <div>
                            <i class="fas fa-database" style="color:#f97316; margin-right:8px;"></i>
                            <strong style="color:#fff;">Base de Datos:</strong>
                            <span style="color:#fff; margin-left:5px;">sisfact_imdl</span>
                        </div>
                        <div>
                            <i class="fas fa-file-invoice" style="color:#f97316; margin-right:8px;"></i>
                            <strong style="color:#fff;">Última Factura Ingresada:</strong>
                            <span style="color:#fff; margin-left:5px;">Número:</span>
                            <strong style="color:#34c38f; font-weight:700; margin-right:10px;"><?php echo $lastInvoiceInfo['numero']; ?></strong>
                            <span style="color:#fff;">Fecha:</span>
                            <strong style="color:#34c38f; font-weight:700;"><?php echo $lastInvoiceInfo['fecha']; ?></strong>
                        </div>
                    </div>
                    
                    <!-- Tablas -->
                    <div>
                        <div style="margin-bottom:8px;">
                            <i class="fas fa-table" style="color:#f97316; margin-right:8px;"></i>
                            <strong style="color:#fff;">Tablas y Registros por Tabla:</strong>
                        </div>
                        <div style="line-height:1.8;">
                            <?php 
                            $counter = 1;
                            $tableList = [];
                            foreach ($allTables as $table): 
                                ?>
                                <span style="display:inline-block; margin-right:15px; margin-bottom:5px;">
                                    <?php echo $counter++; ?>.
                                    <span style="color:#fff;"><?php echo $table['name']; ?></span>
                                    <strong style="color:#34c38f; font-weight:700;">(<?php echo number_format($table['count']); ?>)</strong>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button class="btn btn-sql" onclick="runBackup('fijo')" style="flex: 1; padding:12px;">
                        <i class="fas fa-download"></i> DESCARGAR SALVA CON NOMBRE FIJO
                    </button>
                    
                    <button class="btn btn-sql" onclick="mostrarModalNombreBackup()" style="flex: 1; padding:12px; background: #FF5D00;">
                        <i class="fas fa-pen"></i> DESCARGAR SALVA CON NOMBRE VARIABLE
                    </button>
                </div>

                <p style="margin-top:10px; font-size:0.7rem; color:var(--text-muted);">
                    <i class="fas fa-info-circle"></i> La Salva incluye estructura de tablas, índices, auto-increment y datos.
                </p>
            </div>
        </div>
        
        <!-- NUEVO: Exportar a Excel -->
        <div id="sql-excel" class="sql-sub-content" style="display:none;">
            <div class="export-card" style="max-width: 100%; text-align: center; background:var(--bg-primary);">
                <p class="export-title" style="font-size: 1.1rem;">
                    <i class="fas fa-file-excel" style="color: #1e7e34;"></i> Exportar Facturas a Excel
                </p>
                <p class="export-desc" style="font-size: 0.9rem; margin-bottom: 20px;">
                    Genera un archivo Excel con todas las facturas de un período específico.
                    Incluye hoja de resumen y cada factura en una hoja individual con formato profesional.
                </p>
                
                <div style="background: #1a1d28; border-radius: 12px; padding: 20px; margin: 15px 0; text-align: left;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #32394e;">
                        <i class="fas fa-info-circle" style="color: #60a5fa; font-size: 1.2rem;"></i>
                        <span style="color: #e1e5eb; font-size: 0.9rem;">
                            <strong>Formato del archivo:</strong> Excel (.xlsx) con formato profesional, 
                            incluye resumen, cabecera de factura, detalles, totales y número en letras.
                        </span>
                    </div>
                    
                    <form id="toolsExcelExportForm" target="_blank" action="exportar_factura_xlsx.php" method="POST">
                        <div class="row">
                            <div class="col-12" style="margin-bottom: 15px;">
                                <label class="form-label" style="font-size: 0.9rem;">
                                    <i class="fas fa-calendar-alt"></i> Selecciona el período:
                                </label>
                            </div>
                            
                            <div class="col-md-6" style="margin-bottom: 15px;">
                                <label class="form-label" style="font-size: 0.8rem;">📆 Año:</label>
                                <select name="anio" id="excel_anio" class="form-select" >
                                    <option value="">Seleccionar año...</option>
                                    <?php
                                    // Obtener años disponibles de la base de datos
                                    try {
                                        $years_query = $conn->query("SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact ORDER BY anio DESC");
                                        if ($years_query && $years_query->num_rows > 0) {
                                            while ($year_row = $years_query->fetch_assoc()) {
                                                echo '<option value="' . $year_row['anio'] . '">' . $year_row['anio'] . '</option>';
                                            }
                                        } else {
                                            $current_year = date('Y');
                                            for ($y = $current_year; $y >= $current_year - 2; $y--) {
                                                echo '<option value="' . $y . '">' . $y . '</option>';
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $current_year = date('Y');
                                        for ($y = $current_year; $y >= $current_year - 2; $y--) {
                                            echo '<option value="' . $y . '">' . $y . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6" style="margin-bottom: 15px;">
                                <label class="form-label" style="font-size: 0.8rem;">📅 Mes:</label>
                                <select name="mes" id="excel_mes" class="form-select" >
                                    <option value="">Seleccionar mes...</option>
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                            
                            <div class="col-12" style="margin-top: 10px;">
                                <div style="background: rgba(52, 195, 143, 0.1); border-left: 3px solid #34c38f; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                                    <p style="margin: 0; font-size: 0.8rem; color: #c0c6d4;">
                                        <i class="fas fa-lightbulb" style="color: #34c38f; margin-right: 8px;"></i>
                                        <strong>Información:</strong> El archivo generado incluirá todas las facturas del mes y año seleccionados.
                                        Cada factura tendrá su propia hoja con formato profesional.
                                    </p>
                                </div>
                                
                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 14px; font-size: 1rem; background: #1e7e34;">
                                    <i class="fas fa-download"></i> GENERAR EXCEL DE FACTURAS
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div style="margin-top: 20px; padding: 12px; background: rgba(96, 165, 250, 0.1); border-radius: 8px; text-align: left;">
                    <p style="margin: 0; font-size: 0.75rem; color: #a0aec0;">
                        <i class="fas fa-check-circle" style="color: #60a5fa;"></i>
                        <strong>Características del Excel:</strong>
                    </p>
                    <ul style="margin: 8px 0 0 20px; font-size: 0.7rem; color: #a0aec0;">
                        <li>Hoja de resumen con todas las facturas del período</li>
                        <li>Una hoja por cada factura con formato de factura comercial</li>
                        <li>Datos del cliente, detalles de servicios y totales</li>
                        <li>Número en letras automático</li>
                        <li>Datos bancarios y de facturación</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Contenido: Ayuda -->
    <div id="tools-help" class="tools-tab-content" style="display:none">
        <div class="card">
            <h2 class="card-title"><i class="fas fa-question-circle"></i> Ayuda y Soporte</h2>
            <div class="export-grid">
                <div class="export-card">
                    <h4 class="export-title">🔧 Diagnóstico</h4>
                    <p class="export-desc">Verifica conexión y estructura de la base de datos.</p>
                    <div>
                        <a href="export-import/test_connection.php" target="_blank" class="btn btn-primary" style="display: inline-flex; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> Ejecutar
                        </a>
                    </div>
                </div>
                
                <div class="export-card">
                    <h4 class="export-title">📚 Documentación</h4>
                    <p class="export-desc">Especificaciones técnicas y guías de uso.</p>
                    <div>
                        <button class="btn btn-outline" onclick="showDocumentation()">
                            <i class="fas fa-info-circle"></i> Info
                        </button>
                    </div>
                </div>
                
                <div class="export-card">
                    <h4 class="export-title">🎯 Soporte Técnico</h4>
                    <p class="export-desc">Consultas sobre errores de importación.</p>
                    <div>
                        <button class="btn btn-outline" onclick="window.location.href='soporte.php'">
                            <i class="fas fa-envelope"></i> Contactar
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 25px;">
                <h3 class="card-title" style="font-size: 1.1rem;"><i class="fas fa-code-branch"></i> Archivos del Sistema</h3>
                <div style="background: var(--bg-secondary); padding: 15px; border-radius: var(--radius); border: 1px solid var(--border-color);">
                    <ul style="padding-left: 20px; font-size: 0.9rem; line-height: 1.8; color: var(--text-secondary);">
                        <li><code>index.php</code> - Interfaz principal</li>
                        <li><code>download.php</code> - Gestor de descargas</li>
                        <li><code>export_functions.php</code> - Funciones principales</li>
                        <li><code>test_connection.php</code> - Diagnóstico</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>


    const BASE_PATH_TOOLS = 'export-import/';

window.currentErrors = null; // Variable global para almacenar errores

    // ============================================
    // FUNCIONES GLOBALES (definidas al inicio)
    // ============================================
    
/**
 * Copia errores al portapapeles - VERSIÓN MEJORADA
 */
window.copyErrorsToClipboard = function(errors) {
    console.log('Copiando errores:', errors);
    
    // Asegurar que errors es un array
    let errorsArray = Array.isArray(errors) ? errors : [errors];
    
    // Construir texto
    const text = errorsArray.map((err, i) => {
        try {
            if (err === null || err === undefined) return `Error #${i+1}: (vacío)`;
            if (typeof err === 'object') return `Error #${i+1}:\n${JSON.stringify(err, null, 2)}`;
            return `Error #${i+1}:\n${String(err)}`;
        } catch (e) {
            return `Error #${i+1}: (no se pudo serializar)`;
        }
    }).join('\n\n---\n\n');
    
    // Copiar al portapapeles
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                title: '✅ Copiado',
                text: 'Errores copiados al portapapeles',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                background: '#11141d',
                color: '#fff'
            });
        }).catch(err => {
            console.error('Error al copiar:', err);
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
};

// Función fallback simplificada
function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        if (document.execCommand('copy')) {
            Swal.fire({
                title: '✅ Copiado',
                text: 'Errores copiados al portapapeles',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                background: '#11141d',
                color: '#fff'
            });
        } else {
            throw new Error('execCommand failed');
        }
    } catch (err) {
        Swal.fire({
            title: '❌ Error',
            text: 'No se pudo copiar al portapapeles. Selecciona el texto manualmente.',
            icon: 'error',
            background: '#11141d',
            color: '#fff',
            confirmButtonColor: '#d63031'
        });
        
        // Mostrar el texto en un textarea para que el usuario pueda copiar manualmente
        Swal.fire({
            title: '📋 Copiar manualmente',
            html: `<textarea style="width:100%; height:300px; background:#2d2d3a; color:#fff; border:1px solid #444; padding:10px; border-radius:4px;" readonly>${text}</textarea>`,
            background: '#11141d',
            color: '#fff',
            confirmButtonColor: '#556ee6',
            confirmButtonText: '<i class="fas fa-close me-2"></i> Cerrar'
        });
    }
    document.body.removeChild(textarea);
}

/**
 * Función auxiliar para copiar errores desde string codificado - VERSIÓN CORREGIDA
 */
window.copyErrorsFromEncoded = function(encodedErrors) {
    console.log('copyErrorsFromEncoded recibió:', encodedErrors); // Para depuración
    
    try {
        // Intentar diferentes métodos de decodificación
        let decodedString;
        let errors;
        
        // Método 1: decodeURIComponent directo
        try {
            decodedString = decodeURIComponent(encodedErrors);
            console.log('Decodificado con método 1:', decodedString.substring(0, 100));
            errors = JSON.parse(decodedString);
        } catch (e1) {
            console.log('Método 1 falló:', e1.message);
            
            // Método 2: atob (base64)
            try {
                decodedString = atob(encodedErrors);
                console.log('Decodificado con método 2:', decodedString.substring(0, 100));
                errors = JSON.parse(decodedString);
            } catch (e2) {
                console.log('Método 2 falló:', e2.message);
                
                // Método 3: Si es un string que ya es un array, intentar parsear directamente
                try {
                    errors = JSON.parse(encodedErrors);
                    console.log('Parseado directamente como JSON');
                } catch (e3) {
                    console.log('Método 3 falló:', e3.message);
                    
                    // Método 4: Si todo falla, asumir que es un string plano
                    errors = [encodedErrors];
                    console.log('Usando como string plano');
                }
            }
        }
        
        // Asegurarse de que errors sea un array
        if (!Array.isArray(errors)) {
            errors = [errors];
        }
        
        console.log('Errores finales a copiar:', errors);
        
        // Llamar a la función principal de copiado
        copyErrorsToClipboard(errors);
        
    } catch (e) {
        console.error('Error completo en copyErrorsFromEncoded:', e);
        
        // Mostrar el contenido problemático para depuración
        Swal.fire({
            title: '❌ Error',
            html: `
                <div style="color: #e0e0e0; text-align: left;">
                    <p>No se pudieron procesar los errores para copiar.</p>
                    <p style="font-size: 12px; color: #ff6b6b;">${e.message}</p>
                    <p style="font-size: 11px; margin-top: 10px;">Primeros 100 caracteres recibidos:</p>
                    <pre style="background: #2d2d3a; padding: 8px; border-radius: 4px; font-size: 10px; overflow-x: auto;">${encodedErrors.substring(0, 100)}</pre>
                </div>
            `,
            icon: 'error',
            background: '#11141d',
            color: '#fff',
            confirmButtonColor: '#d63031'
        });
    }
};

 

    /**
     * Muestra todos los errores en un modal
     */
window.showAllErrors = function(errors) {
    // Guardar errores en variable global para que el botón pueda acceder
    window.currentErrors = errors;
    
    // Asegurar que errors es un array
    if (!errors || !Array.isArray(errors)) {
        errors = errors ? [String(errors)] : ['No hay detalles de error disponibles'];
    }
    
    // Limitar a 50 errores para evitar problemas de rendimiento
    const maxErrors = 50;
    const hasMore = errors.length > maxErrors;
    const errorsToShow = errors.slice(0, maxErrors);
    
    let errorList = '<div style="text-align: left; max-height: 500px; overflow-y: auto; padding: 5px;">';
    
    errorsToShow.forEach((err, i) => {
        // Convertir a string si es objeto
        let errorText = typeof err === 'object' ? JSON.stringify(err, null, 2) : String(err);
        
        // Escapar HTML para evitar inyección
        errorText = errorText
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        
        // Limitar longitud de cada error
        if (errorText.length > 500) {
            errorText = errorText.substring(0, 500) + '... (truncado)';
        }
        
        errorList += `
            <div style="margin-bottom: 15px; padding: 12px; background: #1a1d28; border-radius: 6px; border-left: 4px solid #f97316;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong style="color: #f97316;">Error #${i+1}</strong>
                    <span style="color: #666; font-size: 11px;">${new Date().toLocaleTimeString()}</span>
                </div>
                <pre style="margin: 0; font-size: 12px; color: #e1e5eb; white-space: pre-wrap; word-wrap: break-word; background: #11141d; padding: 8px; border-radius: 4px;">${errorText}</pre>
            </div>
        `;
    });
    
    if (hasMore) {
        errorList += `
            <div style="text-align: center; padding: 15px; background: #2d2d3a; border-radius: 6px; margin-top: 10px;">
                <i class="fas fa-info-circle" style="color: #f1b44c;"></i>
                <span style="color: #e1e5eb;">... y ${errors.length - maxErrors} errores más</span>
            </div>
        `;
    }
    
    errorList += '</div>';
    
    // Botón simple que llama directamente a la función con la variable global
    const customButtons = `
        <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center; flex-wrap: wrap;">
            <button onclick='copyCurrentErrors()' 
                    style="background: #556ee6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-copy"></i> Copiar todos los errores
            </button>
            <button onclick='Swal.close(); reloadTools()' 
                    style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-sync-alt"></i> Recargar
            </button>
            <button onclick='Swal.close();' 
                    style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    `;

    Swal.fire({
        title: '📋 Detalles de errores',
        html: errorList + customButtons,
        icon: 'error',
        width: '900px',
        background: '#11141d',
        color: '#fff',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: 'animate__animated animate__fadeInDown'
        }
    });
};

// Función para copiar los errores actuales
window.copyCurrentErrors = function() {
    if (!window.currentErrors) {
        Swal.fire({
            title: '❌ Error',
            text: 'No hay errores para copiar',
            icon: 'error',
            background: '#11141d',
            color: '#fff',
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }
    
    // Llamar directamente a copyErrorsToClipboard con los errores guardados
    copyErrorsToClipboard(window.currentErrors);
};

    /**
     * Muestra errores desde respuesta codificada en base64
     */
window.showErrorsFromResponse = function(encodedErrors) {
    console.log('showErrorsFromResponse llamada');
    
    try {
        // Decodificar de base64
        let decodedString = atob(encodedErrors);
        
        // Intentar decodificar UTF-8 si es necesario
        try {
            decodedString = decodeURIComponent(escape(decodedString));
        } catch (e) {
            // Ignorar, ya está decodificado
        }
        
        // Parsear JSON
        const errors = JSON.parse(decodedString);
        console.log('Errores decodificados:', errors);
        
        // Usar showAllErrors
        window.showAllErrors(errors);
    } catch (e) {
        console.error('Error al decodificar errores:', e);
        Swal.fire({
            title: '❌ Error',
            text: 'No se pudieron cargar los detalles de errores',
            icon: 'error',
            background: '#1e1e2d',
            color: '#fff',
            confirmButtonColor: '#d63031'
        });
    }
};

    /**
     * Muestra historial de importaciones SQL
     */
    window.showSQLLog = function() {
        const logs = JSON.parse(localStorage.getItem('sql_import_logs') || '[]');
        
        if (logs.length === 0) {
            Swal.fire({
                title: '📋 Sin registros',
                text: 'No hay importaciones SQL recientes',
                icon: 'info',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#3498db',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido'
            });
            return;
        }
        
        let html = '<div style="text-align: left; max-height: 500px; overflow-y: auto;">';
        logs.reverse().forEach((log, index) => {
            html += `
                <div style="margin-bottom: 15px; padding: 10px; background: #1a1d28; border-radius: 5px; border-left: 3px solid ${log.success ? '#34c38f' : '#f97316'};">
                    <div style="display: flex; justify-content: space-between;">
                        <strong style="color: ${log.success ? '#34c38f' : '#f97316'};">${log.success ? '✅ Éxito' : '❌ Error'}</strong>
                        <span style="color: #c0c6d4;">${log.date}</span>
                    </div>
                    <div style="font-size: 12px;">Archivo: ${log.filename}</div>
                </div>
            `;
        });
        html += '</div>';
        
        Swal.fire({
            title: '📋 Historial SQL',
            html: html,
            icon: 'info',
            width: '600px',
            background: '#1e1e2d',
            color: '#fff',
            confirmButtonColor: '#3498db',
            confirmButtonText: '<i class="fas fa-close"></i> Cerrar',
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-trash-alt"></i> Limpiar historial',
            cancelButtonColor: '#d63031',
            showCloseButton: true
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                localStorage.removeItem('sql_import_logs');
                Swal.fire({
                    title: '🧹 Historial limpiado',
                    text: 'Los registros de importaciones han sido eliminados',
                    icon: 'success',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#00b894',
                    confirmButtonText: '<i class="fas fa-check"></i> Aceptar'
                });
            }
        });
    };

    // ============================================
    // FUNCIONES AUXILIARES
    // ============================================

    /**
     * Recarga la página si es necesario (para usar desde los modales)
     */
    function reloadIfNeeded() {
            reloadTools();
    }

    // Cambiar tabs principales
    function switchToolsTab(tabId, btn) {
        document.querySelectorAll('.tools-tab-content').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tools-' + tabId).style.display = 'block';
        btn.classList.add('active');
    }

    // Cambiar sub-tabs SQL
    function switchSQLSubTab(subTabId, btn) {
        document.querySelectorAll('.sql-sub-content').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.sub-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(subTabId).style.display = 'block';
        btn.classList.add('active');
    }

    // Actualizar atributo accept según formato
    function updateFileAcceptAttribute() {
        const formatSelect = document.getElementById('toolsImportFormat');
        const fileInput = document.getElementById('toolsImportFile');
        const formatHint = document.getElementById('formatHintText');
        const selectedFormat = formatSelect.value;
        
        if (selectedFormat === 'json') {
            fileInput.accept = '.json';
            formatHint.innerHTML = 'El archivo debe tener extensión <strong>.json</strong>';
        } else if (selectedFormat === 'xml') {
            fileInput.accept = '.xml';
            formatHint.innerHTML = 'El archivo debe tener extensión <strong>.xml</strong>';
        }
    }

    // Validar formato de archivo
    function validateFileFormat() {
        const formatSelect = document.getElementById('toolsImportFormat');
        const fileInput = document.getElementById('toolsImportFile');
        const selectedFormat = formatSelect.value;
        
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                title: '⚠️ Ningún archivo seleccionado',
                text: 'Por favor seleccione un archivo para importar',
                icon: 'warning',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#f39c12',
                confirmButtonText: '<i class="fas fa-file-import"></i> Seleccionar archivo'
            });
            return false;
        }
        
        const fileName = fileInput.files[0].name;
        const fileExtension = fileName.split('.').pop().toLowerCase();
        
        if (selectedFormat === 'json' && fileExtension !== 'json') {
            Swal.fire({
                title: '❌ Formato incorrecto',
                html: `
                    <div style="color: #e0e0e0;">
                        <p style="margin-bottom: 10px;">Has seleccionado formato <strong style="color: #3498db;">JSON</strong> pero el archivo es:</p>
                        <div style="background: #2d2d3a; padding: 8px; border-radius: 6px; margin: 10px 0; display: inline-block;">
                            <code style="color: #f39c12; font-size: 16px;">.${fileExtension}</code>
                        </div>
                        <p style="margin-top: 15px; font-size: 13px; color: #999;">
                            <i class="fas fa-info-circle"></i> Selecciona un archivo con extensión <strong style="color: #3498db;">.json</strong>
                        </p>
                    </div>
                `,
                icon: 'error',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#d63031',
                confirmButtonText: '<i class="fas fa-redo"></i> Reintentar'
            });
            return false;
        }
        
        if (selectedFormat === 'xml' && fileExtension !== 'xml') {
            Swal.fire({
                title: '❌ Formato incorrecto',
                html: `
                    <div style="color: #e0e0e0;">
                        <p style="margin-bottom: 10px;">Has seleccionado formato <strong style="color: #f39c12;">XML</strong> pero el archivo es:</p>
                        <div style="background: #2d2d3a; padding: 8px; border-radius: 6px; margin: 10px 0; display: inline-block;">
                            <code style="color: #d63031; font-size: 16px;">.${fileExtension}</code>
                        </div>
                        <p style="margin-top: 15px; font-size: 13px; color: #999;">
                            <i class="fas fa-info-circle"></i> Selecciona un archivo con extensión <strong style="color: #f39c12;">.xml</strong>
                        </p>
                    </div>
                `,
                icon: 'error',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#d63031',
                confirmButtonText: '<i class="fas fa-redo"></i> Reintentar'
            });
            return false;
        }
        
        return true;
    }

    // Exportar tabla individual
    function runExport(table, format) {
        Swal.fire({ 
            title: 'Preparando exportación...', 
            text: `Generando archivo ${format.toUpperCase()}`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading() 
        });
        window.location.href = `${BASE_PATH_TOOLS}download.php?type=table&table=${table}&format=${format}`;
        setTimeout(() => Swal.close(), 1500);
    }

    // Exportar completo
    function runExportComplete(format) {
        Swal.fire({ 
            title: 'Generando Pack Completo', 
            html: `
                <div style="text-align: center;">
                    <i class="fas fa-cubes fa-3x" style="color: #34c38f; margin-bottom: 15px;"></i>
                    <p>Exportación relacional en formato <strong>${format.toUpperCase()}</strong></p>
                    <div style="display: flex; justify-content: center; gap: 15px; margin: 15px 0;">
                        <span style="background: #e8f0fe; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
                            <i class="fas fa-file-invoice" style="color: #556ee6;"></i> Facturas: ${<?php echo $stats['total_facturas']; ?>}
                        </span>
                        <span style="background: #e8f0fe; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
                            <i class="fas fa-list-ul" style="color: #34c38f;"></i> Detalles: ${<?php echo $stats['total_detalles']; ?>}
                        </span>
                    </div>
                    <div style="background: #f8f9fa; border-radius: 4px; padding: 3px;">
                        <div style="width: 0%; height: 6px; background: #34c38f; border-radius: 4px; transition: width 0.3s;" id="export-progress"></div>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    const bar = document.getElementById('export-progress');
                    if (bar) bar.style.width = progress + '%';
                    if (progress >= 100) {
                        clearInterval(interval);
                    }
                }, 100);
            }
        });
        
        setTimeout(() => {
            window.location.href = `${BASE_PATH_TOOLS}download.php?type=complete&format=${format}`;
        }, 1000);
        
        setTimeout(() => Swal.close(), 2000);
    }

/**
 * Muestra modal para ingresar nombre personalizado del backup
 */
function mostrarModalNombreBackup() {
    Swal.fire({
        // Título vacío para usar una barra personalizada en el HTML
        title: '',
        target: document.querySelector('.modal-content') || document.body,
        background: '#1a1a1a',
        color: '#ffffff',
        
        // --- ESTRUCTURA HTML CON TITLEBAR TIPO WINDOWS ---
        html: `
            <!-- Barra de título estilo Windows -->
            <div style="background: #2d2d3a; margin: -20px -20px 20px -20px; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #444; border-top-left-radius: 5px; border-top-right-radius: 5px;">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 500; color: #e0e0e0;">
                    <i class="fas fa-database" style="color: #ffaa33;"></i>
                    Gestión de Salva de Datos
                </div>

            </div>

            <div style="text-align: left; padding: 0 5px;">
                <p style="margin-bottom: 15px; color: #e0e0e0; font-size: 0.95rem;">Ingresa el nombre para tu archivo de salva:</p>
                
                <!-- Bloque de Información Requisitos -->
                <div style="background: rgba(255,170,51,0.1); border-left: 4px solid #ffaa33; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <div style="background: #ffaa33; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-info" style="color: #1a1a1a; font-size: 1rem;"></i>
                        </div>
                        <div>
                            <p style="color: #ffaa33; margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;">
                                📋 Requisitos del nombre:
                            </p>
                            <ul style="color: #e0e0e0; margin: 0; padding-left: 20px; font-size: 0.85rem; line-height: 1.5;">
                                <li>Solo letras (a-z), números (0-9)</li>
                                <li>Guiones medios (-) y bajos (_) permitidos</li>
                                <li>Sin espacios, Ñ, ni caracteres especiales</li>
                                <li>Ejemplo: <span style="color: #ffaa33; font-family: monospace;">salva_contabilidad_2024</span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Vista Previa Dinámica -->
                <div style="margin-top: 20px; text-align: left; background: #252525; border-radius: 8px; padding: 10px 15px; border: 1px solid #333;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-eye" style="color: #ffaa33; font-size: 0.9rem;"></i>
                        <span style="color: #b0b0b0; font-size: 0.85rem;">Vista previa del archivo:</span>
                        <span id="preview-filename" style="color: #ffaa33; font-family: monospace; background: #1a1a1a; padding: 4px 8px; border-radius: 4px; border: 1px solid #444; font-size: 0.9rem;">
                            salva.sql
                        </span>
                    </div>
                </div>
            </div>
        `,

        // --- CONFIGURACIÓN DEL INPUT ---
        input: 'text',
        inputPlaceholder: 'Escribe el nombre de la salva aquí...',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off',
            autocomplete: 'off',
            spellcheck: 'false'
        },

        // --- VALIDACIÓN ---
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return '❌ Por favor ingresa un nombre para la salva';
            }
            if (!/^[a-zA-Z0-9_\-]+$/.test(value)) {
                return '❌ Solo letras, números, guiones y guiones bajos';
            }
            if (value.length < 3) {
                return '❌ El nombre debe tener al menos 3 caracteres';
            }
            return null;
        },

        // --- RESTRICCIONES DE CIERRE ---
        showCloseButton: true, 
        allowOutsideClick: false, 
        allowEscapeKey: false, 
        
        // --- BOTONES ---
        showCancelButton: true,
        confirmButtonColor: '#ffaa33',
        cancelButtonColor: '#444',
        confirmButtonText: '<i class="fas fa-download"></i> GENERAR SALVA',
        cancelButtonText: '<i class="fas fa-times"></i> CERRAR',
        
        // Personalización adicional
        customClass: {
            popup: 'border-windows-style',
            closeButton: 'custom-close-x'
        },

        // --- LÓGICA DE INTERACCIÓN ---
        didOpen: () => {
            const input = Swal.getInput();
            const preview = document.getElementById('preview-filename');

            // Estilizar input nativo
            input.style.background = '#2d2d3a';
            input.style.color = 'white';
            input.style.border = '1px solid #444';
            input.style.borderRadius = '4px';
            input.style.margin = '15px 15px';
            input.style.padding = '12px';
            input.style.width = '95%';
            input.style.boxSizing = 'border-box';
			input.style.fontSize = '16px'; 
			
			/* Object.assign(input.style, {
                background: '#2d2d3a', color: 'white', border: '1px solid #444',
                borderRadius: '4px', margin: '10px 0', padding: '5px 10px',
                height: '32px', fontSize: '0.9rem', width: '100%'
            }); */

            // Actualizar vista previa en tiempo real
            input.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                preview.textContent = val ? `${val}.sql` : 'salva.sql';
            });

            // Forzar el foco para evitar el bloqueo del modal padre
            setTimeout(() => {
                input.focus();
            }, 500);
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Llamar a la función con el nombre de la salva
            runBackup('variable', result.value);
        }
    });
}



function runBackup(tipo = 'fijo', nombrePersonalizado = null) {
    let titulo = '🔄 Generando Backup SQL...';
    let mensajeInicial = 'Creando archivo de respaldo de la base de datos';
    
    if (tipo === 'variable' && nombrePersonalizado) {
        titulo = `🔄 Generando Backup: ${nombrePersonalizado}`;
        mensajeInicial = `Creando archivo "${nombrePersonalizado}.sql"`;
    }
    
    // PRIMER MODAL - Progreso (dark)
    Swal.fire({ 
        title: titulo, 
        html: `
            <div style="color: #e0e0e0;">
                <p style="margin-bottom: 15px;">${mensajeInicial}</p>
                <div style="background: #333; border-radius: 10px; padding: 3px; margin: 15px 0;">
                    <div id="backup-progress-bar" style="width: 0%; height: 20px; background: linear-gradient(90deg, #f97316, #ffaa33); border-radius: 8px; transition: width 0.3s;"></div>
                </div>
                <p id="backup-status" style="font-size: 0.9em; color: #b0b0b0;">Iniciando proceso de backup...</p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        background: '#1a1a1a',
        color: '#ffffff',
        didOpen: () => {
            Swal.showLoading();
            
            // Simular progreso
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress >= 100) {
                    progress = 95;
                    
                    const statusEl = document.getElementById('backup-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color: #f97316;">✓ Backup generado, preparando descarga...</span>';
                    }
                }
                
                updateBackupProgress(progress);
            }, 100);
            
            window.backupInterval = interval;
        }
    });
    
    setTimeout(() => {
        // Limpiar intervalo
        if (window.backupInterval) {
            clearInterval(window.backupInterval);
        }
        
        // Completar barra al 100%
        updateBackupProgress(100, '<span style="color: #4CAF50;">✓ Backup completado, iniciando descarga...</span>');
        
        // Pequeña pausa para mostrar el 100%
        setTimeout(() => {
            // Construir URL con parámetros
            let url = `${BASE_PATH_TOOLS}download.php?type=backup`;
            if (tipo === 'variable' && nombrePersonalizado) {
                url += `&custom_name=${encodeURIComponent(nombrePersonalizado)}`;
            }
            
            // Redirigir a la descarga
            window.location.href = url;
            
            // Cerrar modal de progreso
            Swal.close();
            
            // Mostrar modal de éxito (dark)
            setTimeout(() => {
                let mensajeExito = 'El backup SQL se ha generado correctamente.';
                if (tipo === 'variable' && nombrePersonalizado) {
                    mensajeExito = `El backup <strong>${nombrePersonalizado}.sql</strong> se ha generado correctamente.`;
                }
                
                Swal.fire({ 
                    icon: 'success', 
                    title: '✅ Descarga Iniciada', 
                    html: `
                        <div style="color: #e0e0e0;">
                            <p>${mensajeExito}</p>
                            <p style="font-size: 0.9em; color: #b0b0b0; margin-top: 10px;">
                                <i class="fas fa-download"></i> La descarga debería comenzar automáticamente
                            </p>
                        </div>
                    `,
                    timer: 4000, 
                    showConfirmButton: false,
                    background: '#1a1a1a',
                    color: '#ffffff',
                    iconColor: '#4CAF50'
                });
            }, 500);
        }, 800);
    }, 5000);
}

/**
 * Función para actualizar la barra de progreso del backup
 */
function updateBackupProgress(percent, customMessage = null) {
    const progressBar = document.getElementById('backup-progress-bar');
    const statusEl = document.getElementById('backup-status');
    
    if (progressBar) {
        progressBar.style.width = `${percent}%`;
    }
    
    if (statusEl && !customMessage) {
        if (percent < 30) {
            statusEl.innerHTML = '📦 Preparando estructura de la base de datos...';
        } else if (percent < 60) {
            statusEl.innerHTML = '💾 Exportando tablas y datos...';
        } else if (percent < 90) {
            statusEl.innerHTML = '🔧 Optimizando archivo de backup...';
        } else {
            statusEl.innerHTML = '🚀 Finalizando proceso...';
        }
    } else if (statusEl && customMessage) {
        statusEl.innerHTML = customMessage;
    }
}

    // También puedes agregar estilos CSS para los modales
    const style = document.createElement('style');
    style.textContent = `
        .swal-dark-popup {
            border: 1px solid #333;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        
        .swal-dark-popup .swal2-title {
            color: #ffffff !important;
        }
        
        .swal-dark-popup .swal2-html-container {
            color: #e0e0e0 !important;
        }
        
        /* Animación personalizada para la barra de progreso */
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        #backup-progress-bar {
            animation: pulse 2s infinite;
        }
    `;
    document.head.appendChild(style);

    // Mostrar documentación
    function showDocumentation() {
        Swal.fire({
            title: 'Documentación',
            html: `
                <div style="text-align: left; color: #e1e5eb;">
                    <p><strong>📁 Ubicación:</strong> <code>/docs</code></p>
                    <p><strong>📄 Formatos:</strong> JSON, XML y SQL</p>
                    <p><strong>📊 Tablas:</strong> tbl_fact y tbl_fact_detalle</p>
                    <p style="background: rgba(85, 110, 230, 0.15); padding: 10px; border-radius: 6px;">
                        <strong>⚠️ Importante:</strong> Para importaciones relacionales, el archivo debe contener ambas tablas.
                    </p>
                </div>
            `,
            icon: 'info',
            background: '#1a1d28',
            color: '#e1e5eb',
            confirmButtonColor: '#556ee6',
			confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
    }

    // Submit del formulario de importación JSON/XML
    document.getElementById('toolsImportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateFileFormat()) {
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'import');

        const fileInput = document.getElementById('toolsImportFile');
        const fileName = fileInput.files[0]?.name || 'archivo.json';

        Swal.fire({
            title: '<span style="color: #e1e5eb; font-size: 1.2rem; font-weight: 400;">Importando datos</span>',
            html: `
                <div style="text-align: center; padding: 15px 0;">
                    <!-- Info simple (sin spinner superior) -->
                    <div style="background: #0f172a; border-radius: 12px; padding: 20px; margin: 0 0 15px 0; border: 1px solid #1e293b;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px;">
                            <i class="fas fa-file-code" style="color: #3b82f6;"></i>
                            <span style="color: #f1f5f9; font-weight: 500; background: #1e293b; padding: 6px 16px; border-radius: 20px; font-size: 0.95rem;" id="file-name-display">${fileName}</span>
                        </div>
                        
                        <!-- Barra de progreso animada -->
                        <div style="width: 100%; height: 6px; background: #1e293b; border-radius: 100px; margin: 15px 0; overflow: hidden;">
                            <div style="width: 100%; height: 100%; background: linear-gradient(90deg, #3b82f6, #60a5fa); border-radius: 100px; animation: progress 2s ease-in-out infinite; background-size: 200% 200%;"></div>
                        </div>
                        
                        <!-- Estado actual con spinner pequeño -->
                        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px;">
                            <i class="fas fa-spinner fa-spin" style="color: #3b82f6;"></i>
                            <span style="color: #cbd5e1; font-size: 0.9rem;" id="current-status">Procesando registros...</span>
                        </div>
                    </div>
                    
                    <p style="color: #64748b; font-size: 0.8rem;">
                        <i class="fas fa-hourglass-half"></i> No cierres esta ventana
                    </p>
                </div>
                
                <style>
                    @keyframes progress {
                        0% { background-position: 0% 50%; }
                        50% { background-position: 100% 50%; }
                        100% { background-position: 0% 50%; }
                    }
                </style>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            background: '#0b1120',
            color: '#ffffff',
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(`${BASE_PATH_TOOLS}modal_view.php`, {
            method: 'POST',
            body: formData
        })
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Respuesta no JSON:', text.substring(0, 200));
                throw new Error('La respuesta del servidor no es JSON válido');
            }
        })
        .then(data => {
            if (data.success) {
                let message = '';
                if (data.data) {
                    message = `✅ Importados: ${data.data.imported || 0}<br>`;
                    message += `⚠️ Saltados: ${data.data.skipped || 0}<br>`;
                    if (data.data.errors && data.data.errors.length > 0) {
                        message += `<br>❌ Errores (${data.data.errors.length}):<br>`;
                        message += data.data.errors.slice(0, 5).map(e => `- ${e}`).join('<br>');
                        if (data.data.errors.length > 5) {
                            message += `<br>... y ${data.data.errors.length - 5} más`;
                        }
                        
                        // CORREGIDO: Usar showErrorsFromResponse con base64
                        const encodedErrors = btoa(unescape(encodeURIComponent(JSON.stringify(data.data.errors))));
                        message += `<br><br>
                            <button onclick='showErrorsFromResponse("${encodedErrors}")' 
                                    style="background:#f97316; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
                                <i class="fas fa-exclamation-triangle"></i> Ver todos los errores (${data.data.errors.length})
                            </button>`;
                    }
                } else {
                    message = 'Importación completada correctamente.';
                }
                
                // Modal de éxito
                Swal.fire({
                    title: '✅ ¡Operación Exitosa!',
                    html: message,
                    icon: 'success',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#00b894',
                    confirmButtonText: '<i class="fas fa-check"></i> Actualizar Página',
                    showCloseButton: true,
                    backdrop: `
                        rgba(0,0,0,0.8)
                        left top
                        no-repeat
                    `,
                    customClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    }
                }).then(() => reloadTools());
            } else {
                Swal.fire({
                    title: '❌ Error',
                    text: data.message || 'Ocurrió un error inesperado',
                    icon: 'error',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#d63031',
                    confirmButtonText: '<i class="fas fa-close"></i> Cerrar',
                    showCloseButton: true,
                    timer: 5000,
                    timerProgressBar: true
                });
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire({
                title: '❌ Error de comunicación',
                text: err.message || 'No se pudo conectar con el servidor',
                icon: 'error',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#d63031',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido'
            });
        });
    });

    // Submit del formulario SQL
    document.getElementById('toolsSQLImportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('toolsSQLFile');
        
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire('Error', 'Por favor seleccione un archivo SQL', 'warning');
            return;
        }
        
        const fileName = fileInput.files[0].name;
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const fileSize = fileInput.files[0].size;
        
        if (fileExtension !== 'sql') {
            Swal.fire({
                title: '❌ Formato inválido',
                text: 'El archivo debe tener extensión .sql',
                icon: 'error',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#d63031',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido'
            });
            return;
        }
        
        if (fileSize > 300 * 1024 * 1024) {
            Swal.fire({
                title: '❌ Archivo muy grande',
                text: 'El tamaño máximo permitido es 300MB',
                icon: 'error',
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#d63031',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido'
            });
            return;
        }
        
        // Confirmación
        const result = await Swal.fire({
            title: '⚠️ ¿Restaurar Respaldo?',
            html: `
                <div style="color: #e0e0e0;">
                    <p style="font-size: 1.1em; margin-bottom: 5px;">
                        <strong>Esta acción SOBREESCRIBIRÁ todos los datos actuales</strong>
                    </p>
                    <p style="color: #ff6b6b; margin-bottom: 5px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Los datos existentes serán reemplazados permanentemente.
                    </p>
                    <p style="font-size: 0.95em; color: #b0b0b0;">
                        ¿Estás seguro de continuar?
                    </p>
                </div>
            `,
            icon: 'warning',
            iconColor: '#ffaa33',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#4a5568',
            confirmButtonText: '<i class="fas fa-check-circle"></i> Sí, sobreescribir',
            cancelButtonText: '<i class="fas fa-times-circle"></i> Cancelar',
            background: '#1a1a1a',
            color: '#ffffff',
            reverseButtons: true,
            customClass: {
                popup: 'swal-dark-popup',
                title: 'swal-dark-title',
                htmlContainer: 'swal-dark-html'
            }
        });

        if (!result.isConfirmed) {
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'import');

        // SEGUNDO MODAL - Progreso (dark)
        Swal.fire({
            title: '🔄 Restaurando base de datos...',
            html: `
                <div style="color: #e0e0e0;">
                    <p style="margin-bottom: 15px;">Este proceso puede tomar varios minutos</p>
                    <div style="background: #333; border-radius: 10px; padding: 3px;">
                        <div id="progress-bar" style="width: 0%; height: 20px; background: linear-gradient(90deg, #4CAF50, #45a049); border-radius: 8px; transition: width 0.3s;"></div>
                    </div>
                    <p id="progress-text" style="margin-top: 10px; font-size: 0.9em; color: #b0b0b0;">Iniciando restauración...</p>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            background: '#1a1a1a',
            color: '#ffffff',
            didOpen: () => {
                Swal.showLoading();
                
                // Opcional: Simular progreso (puedes conectar esto con tu progreso real)
                let progress = 0;
                const interval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress >= 100) {
                        progress = 90; // No llegar a 100 hasta que termine realmente
                        clearInterval(interval);
                    }
                    
                    const progressBar = document.getElementById('progress-bar');
                    const progressText = document.getElementById('progress-text');
                    
                    if (progressBar && progressText) {
                        progressBar.style.width = `${progress}%`;
                        progressText.textContent = `Progreso: ${Math.round(progress)}%`;
                    }
                }, 100);
                
                // Guardar el intervalo para limpiarlo después
                window.swalProgressInterval = interval;
            }
        });

        fetch(`${BASE_PATH_TOOLS}modal_view.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            // Guardar en localStorage
            const logs = JSON.parse(localStorage.getItem('sql_import_logs') || '[]');
            logs.push({
                date: new Date().toLocaleString(),
                filename: fileName,
                success: data.success,
                successful: data.data?.successful_queries || 0,
                errors: data.data?.errors?.length || 0
            });
            if (logs.length > 10) logs.shift();
            localStorage.setItem('sql_import_logs', JSON.stringify(logs));
            
            if (data.success) {
                let message = `✅ Consultas exitosas: ${data.data.successful_queries || 0}<br>`;
                message += `📊 Registros importados: ${data.data.imported || 0}<br>`;
                
                if (data.data.created_tables && data.data.created_tables.length > 0) {
                    message += `<br>📋 Tablas creadas: ${data.data.created_tables.join(', ')}<br>`;
                }
                
                // CORREGIDO: Detectar hasErrors para controlar la recarga
                const hasErrors = data.data.errors && data.data.errors.length > 0;
                
                if (hasErrors) {
                    message += `<br>❌ Errores encontrados (${data.data.errors.length}):<br>`;
                    
                    // Mostrar primeros 3 errores
                    message += '<div style="max-height: 150px; overflow-y: auto; margin: 10px 0; padding: 8px; background: #2d2d3a; border-radius: 4px;">';
                    message += data.data.errors.slice(0, 3).map(e => `• ${String(e).substring(0, 100)}${String(e).length > 100 ? '...' : ''}`).join('<br>');
                    message += '</div>';
                    
                    if (data.data.errors.length > 3) {
                        message += `<p style="color: #f97316; margin: 5px 0;">... y ${data.data.errors.length - 3} errores más</p>`;
                    }
                    
                    // Botón para ver todos los errores
                    const encodedErrors = btoa(unescape(encodeURIComponent(JSON.stringify(data.data.errors))));
                    message += `<br><br>
						<button onclick='showErrorsFromResponse("${encodedErrors}")' 
								style="background:#f97316; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; margin-right:5px;">
							<i class="fas fa-exclamation-triangle"></i> Ver errores (${data.data.errors.length})
						</button>
						<button onclick='copyErrorsFromEncoded("${encodedErrors}")' 
								style="background:#556ee6; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
							<i class="fas fa-copy"></i> Copiar errores
						</button>`;
                    
                    // Botón para recargar (cuando hay errores)
                    message += `<br><br>
                        <button onclick='reloadTools()' 
                                style='padding:10px 20px; background:#6c757d; color:white; border:none; border-radius:6px; cursor:pointer; font-size:14px; width:100%; margin-top:5px;'>
                            <i class="fas fa-sync-alt"></i> Recargar página
                        </button>`;
                }
                
                // Si hay errores, NO recargar automáticamente
                if (hasErrors) {
                    Swal.fire({
                        title: '⚠️ Importación SQL con errores',
                        html: message,
                        icon: 'warning',
                        background: '#1e1e2d',
                        color: '#fff',
                        confirmButtonColor: '#f97316',
                        confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                        showCloseButton: true,
                        allowOutsideClick: false,
                        backdrop: `
                            rgba(0, 0, 0, 0.8)
                            left top
                            no-repeat
                        `,
                        customClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        }
                    });
                } else {
                    // Si no hay errores, mostrar éxito con opción de recargar
                    Swal.fire({
                        title: '✅ Importación SQL completada',
                        html: message,
                        icon: 'success',
                        background: '#1e1e2d',
                        color: '#fff',
                        confirmButtonColor: '#00b894',
                        confirmButtonText: '<i class="fas fa-sync-alt"></i> Actualizar vista',
                        showCloseButton: true,
                        backdrop: `
                            rgba(0, 0, 0, 0.8)
                            left top
                            no-repeat
                        `,
                        customClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            reloadTools();
                        }
                    });
                }
            } else {
                Swal.fire({
                    title: '❌ Error en la importación',
                    text: data.message || 'Ocurrió un error inesperado',
                    icon: 'error',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#d63031',
                    confirmButtonText: '<i class="fas fa-times"></i> Cerrar',
                    showCloseButton: true,
                    timer: 5000,
                    timerProgressBar: true,
                    backdrop: `
                        rgba(0, 0, 0, 0.8)
                        left top
                        no-repeat
                    `
                });
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        });
    });

    // Inicialización DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        updateFileAcceptAttribute();
        
        const sqlImportTab = document.getElementById('sql-import');
        if (sqlImportTab) sqlImportTab.style.display = 'block';
        
        const logo = document.getElementById('mainLogo');
        if (logo.complete && logo.naturalHeight === 0) {
            tryAlternateLogo(logo);
        }
    });

 // Validar y enviar formulario de Excel
    const excelForm = document.getElementById('toolsExcelExportForm');
    if (excelForm) {
        excelForm.addEventListener('submit', function(e) {
            const anio = document.getElementById('excel_anio').value;
            const mes = document.getElementById('excel_mes').value;
            
            if (!anio) {
                e.preventDefault();
                Swal.fire({
                    title: '⚠️ Selección requerida',
                    text: 'Por favor selecciona un año',
                    icon: 'warning',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#f39c12',
					confirmButtonText: '<i class="fas fa-check"></i> OK, Gracias'
                });
                return false;
            }
            
            if (!mes) {
                e.preventDefault();
                Swal.fire({
                    title: '⚠️ Selección requerida',
                    text: 'Por favor selecciona un mes',
                    icon: 'warning',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#f39c12',
					confirmButtonText: '<i class="fas fa-check"></i> OK, Gracias'
                });
                return false;
            }
            
            // Mostrar loading
            Swal.fire({
                title: '📊 Generando Excel...',
                html: `
                    <div style="color: #e0e0e0;">
                        <p>Procesando facturas del período seleccionado</p>
                        <div style="background: #333; border-radius: 10px; padding: 3px; margin: 15px 0;">
                            <div style="width: 0%; height: 20px; background: linear-gradient(90deg, #1e7e34, #34c38f); border-radius: 8px; transition: width 0.3s;" id="excel-progress-bar"></div>
                        </div>
                        <p id="excel-status" style="font-size: 0.9em; color: #b0b0b0;">Preparando datos...</p>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                background: '#1a1a1a',
                color: '#ffffff',
                didOpen: () => {
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += Math.random() * 15;
                        if (progress >= 100) progress = 95;
                        const bar = document.getElementById('excel-progress-bar');
                        const status = document.getElementById('excel-status');
                        if (bar) bar.style.width = progress + '%';
                        if (status) {
                            if (progress < 30) status.innerHTML = '📋 Consultando facturas...';
                            else if (progress < 60) status.innerHTML = '📝 Procesando datos...';
                            else if (progress < 90) status.innerHTML = '🎨 Formateando documento...';
                            else status.innerHTML = '✅ Finalizando...';
                        }
                    }, 200);
                    
                    window.excelInterval = interval;
                }
            });
            
            // No prevenimos el submit, solo mostramos loading
            // El loading se cerrará cuando la descarga comience
            setTimeout(() => {
                if (window.excelInterval) clearInterval(window.excelInterval);
                Swal.close();
            }, 3000);
        });
    }
	
    // Manejo de logo alternativo
    function tryAlternateLogo(img) {
        const altPaths = [
            'assets/logov.png',
            './assets/logov.png',
            '../assets/logov.png',
            '../../assets/logov.png',
            'logov.png',
            './logov.png'
        ];
        
        if (!img.dataset.tryIndex) {
            img.dataset.tryIndex = '0';
        }
        
        const currentIndex = parseInt(img.dataset.tryIndex);
        
        if (currentIndex < altPaths.length) {
            img.src = altPaths[currentIndex];
            img.dataset.tryIndex = (currentIndex + 1).toString();
        } else {
            img.style.display = 'none';
            document.getElementById('logoFallback').style.display = 'flex';
        }
    }
// Función para recargar la herramienta
function reloadTools() {
    Swal.fire({
        title: '🔄 Recargando...',
        text: 'Actualizando Datos...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#11141d',
        color: '#fff'
    });
    
    // Recargar la página después de un pequeño retraso
    setTimeout(() => {
        location.reload();
    }, 300);
}

</script>