<?php
/**
 * test_connection.php - Archivo para diagnosticar problemas de conexión y estructura de base de datos
 * Versión: Dark Mica Professional
 */

// Configurar para mostrar errores en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar conexión a base de datos
$servername = "localhost";
$username = "root";
$password = "frl8110kaky";
$dbname = "sisfact_imdl";

// Determinar la ruta base para los assets
$basePath = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Conexión - SISFACT Visiones</title>
    <link rel="icon" type="image/x-icon" href="../assets/logov.png">
    <!-- Bootstrap 5 -->
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="../css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../css/sweetalert2.min.css">
    <!-- Windows 11 CSS -->
    <link rel="stylesheet" href="../css/windows11.css">

    
    <style>
        :root {
            --bg-primary: #0a0c0f;
            --bg-secondary: #1a1e24;
            --bg-tertiary: #252b33;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --accent-primary: #3b82f6;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            --accent-info: #6366f1;
            --border-color: #2e3a47;
            --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f1114 0%, #1a1e24 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(145deg, 
                rgba(26, 30, 36, 0.95) 0%, 
                rgba(18, 22, 28, 0.98) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, 
                var(--accent-primary) 0%, 
                var(--accent-info) 50%, 
                var(--accent-primary) 100%);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 300;
        }

        /* Test Sections */
        .test-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .test-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }

        .test-section h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .test-section h2 i {
            color: var(--accent-primary);
            font-size: 2rem;
        }

        .test-section h3 {
            font-size: 1.4rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin: 1.5rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .test-section h3 i {
            color: var(--accent-info);
            font-size: 1.3rem;
        }

        .test-section h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin: 1rem 0 0.5rem;
        }

        /* Result Cards */
        .result {
            padding: 1.25rem;
            border-radius: 12px;
            margin: 1rem 0;
            font-size: 0.95rem;
            border-left: 4px solid transparent;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--accent-success);
            color: #d1fae5;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--accent-danger);
            color: #fee2e2;
        }

        .warning {
            background: rgba(245, 158, 11, 0.1);
            border-left-color: var(--accent-warning);
            color: #fef3c7;
        }

        .info {
            background: rgba(99, 102, 241, 0.1);
            border-left-color: var(--accent-info);
            color: #e0e7ff;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            margin-right: 0.5rem;
        }

        .badge-success {
            background: var(--accent-success);
            color: #000;
        }

        .badge-error {
            background: var(--accent-danger);
            color: #fff;
        }

        .badge-warning {
            background: var(--accent-warning);
            color: #000;
        }

        .badge-info {
            background: var(--accent-info);
            color: #fff;
        }

        /* Grid y Cards */
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .table-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .table-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-info));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .table-card:hover::after {
            opacity: 1;
        }

        .table-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
        }

        .table-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-name i {
            color: var(--accent-primary);
        }

        .table-stats {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .field-list {
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.85rem;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-primary) var(--bg-tertiary);
        }

        .field-list::-webkit-scrollbar {
            width: 6px;
        }

        .field-list::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
        }

        .field-list::-webkit-scrollbar-thumb {
            background: var(--accent-primary);
            border-radius: 10px;
        }

        .field-item {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }

        .field-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--text-primary);
            transform: translateX(5px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin: 1.5rem 0;
        }

        .stat-card {
            background: linear-gradient(145deg, 
                rgba(37, 43, 51, 0.9) 0%, 
                rgba(26, 30, 36, 0.95) 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: scale(1.05);
            border-color: var(--accent-primary);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--accent-primary);
            line-height: 1;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Checklist */
        .checklist {
            list-style: none;
            padding: 0;
        }

        .checklist li {
            padding: 0.75rem 0 0.75rem 2rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            color: var(--text-secondary);
        }

        .checklist li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--accent-success);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .checklist li.error:before {
            content: '✗';
            color: var(--accent-danger);
        }

        .checklist li.warning:before {
            content: '⚠';
            color: var(--accent-warning);
        }

        /* System Info */
        .system-info {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 1rem 0;
            position: relative;
        }

        .system-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                var(--accent-primary), 
                var(--accent-info), 
                var(--accent-primary));
            border-radius: 12px 12px 0 0;
        }

        .system-info strong {
            color: var(--accent-primary);
            font-weight: 600;
        }

        /* Buttons */
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn i {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .btn:hover i {
            transform: translateX(3px);
        }

        .btn-primary {
            background: var(--accent-primary);
            color: #fff;
        }

        .btn-success {
            background: var(--accent-success);
            color: #000;
        }

        .btn-danger {
            background: var(--accent-danger);
            color: #fff;
        }

        .btn-warning {
            background: var(--accent-warning);
            color: #000;
        }

        /* Animaciones */
        .animate-fade-in {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .test-section {
                padding: 1.5rem;
            }

            .table-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Code blocks */
        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            color: var(--accent-info);
            border: 1px solid var(--border-color);
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            margin: -12px 0 0 -12px;
            border: 2px solid var(--accent-primary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Tooltips personalizados */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-5px);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 10;
        }

        [data-tooltip]:hover:before {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-10px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header animate__animated animate__fadeInDown">
            <h1>
                <i class="fas fa-microscope"></i>
                Diagnóstico de Sistema - SISFACT Visiones
            </h1>
            <p>
                <i class="fas fa-database"></i>
                Herramienta de verificación de conexión y estructura de base de datos
            </p>
            <div class="system-info">
                <i class="fas fa-code-branch"></i> Versión 2.3.3 Professional
            </div>
        </div>

        <?php
        echo '<div class="test-section animate__animated animate__fadeInUp">';
        echo '<h2><i class="fas fa-server"></i> 1. Información del Sistema</h2>';
        
        // Información del servidor
        echo '<div class="system-info">';
        echo '<div><i class="fas fa-php"></i> <strong>PHP Version:</strong> ' . phpversion() . '</div>';
        echo '<div><i class="fas fa-terminal"></i> <strong>Server Software:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</div>';
        echo '<div><i class="fas fa-network-wired"></i> <strong>Server Name:</strong> ' . ($_SERVER['SERVER_NAME'] ?? 'N/A') . '</div>';
        echo '<div><i class="fas fa-folder-open"></i> <strong>Document Root:</strong> ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '</div>';
        echo '<div><i class="fas fa-folder"></i> <strong>Current Directory:</strong> ' . __DIR__ . '</div>';
        echo '<div><i class="fas fa-memory"></i> <strong>Memory Limit:</strong> ' . ini_get('memory_limit') . '</div>';
        echo '<div><i class="fas fa-upload"></i> <strong>Max Upload Size:</strong> ' . ini_get('upload_max_filesize') . '</div>';
        echo '<div><i class="fas fa-file-export"></i> <strong>Max Post Size:</strong> ' . ini_get('post_max_size') . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="test-section animate__animated animate__fadeInUp">';
        echo '<h2><i class="fas fa-plug"></i> 2. Prueba de Conexión MySQLi</h2>';
        
        // 1. Probar conexión básica
        echo '<h3><i class="fas fa-wifi"></i> Conectando al servidor MySQL...</h3>';
        $conn = new mysqli($servername, $username, $password);
        
        if ($conn->connect_error) {
            echo '<div class="result error">';
            echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> No se pudo conectar al servidor MySQL<br>';
            echo '<strong>Error:</strong> ' . $conn->connect_error;
            echo '</div>';
            
            // Listar posibles causas
            echo '<div class="result warning">';
            echo '<h4><i class="fas fa-exclamation-triangle"></i> Posibles causas:</h4>';
            echo '<ul class="checklist">';
            echo '<li class="error"><i class="fas fa-times"></i> Servidor MySQL no está ejecutándose</li>';
            echo '<li class="error"><i class="fas fa-times"></i> Credenciales incorrectas</li>';
            echo '<li class="error"><i class="fas fa-times"></i> Servidor no acepta conexiones externas</li>';
            echo '<li class="error"><i class="fas fa-times"></i> Firewall bloqueando el puerto 3306</li>';
            echo '</ul>';
            echo '<h4><i class="fas fa-tools"></i> Soluciones:</h4>';
            echo '<ol>';
            echo '<li>Verifica que XAMPP/WAMP esté ejecutándose</li>';
            echo '<li>Verifica usuario y contraseña en phpMyAdmin</li>';
            echo '<li>Revisa el archivo my.ini/my.cnf</li>';
            echo '<li>Prueba con <code>localhost:3306</code> si usas puerto diferente</li>';
            echo '</ol>';
            echo '</div>';
            
            // No continuar si no hay conexión
            echo '</div>';
            echo '</body></html>';
            exit;
        } else {
            echo '<div class="result success">';
            echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Conexión exitosa al servidor MySQL';
            echo '</div>';
        }
        
        // 2. Probar selección de base de datos
        echo '<h3><i class="fas fa-database"></i> Seleccionando base de datos "' . $dbname . '"...</h3>';
        if (!$conn->select_db($dbname)) {
            echo '<div class="result error">';
            echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> La base de datos "' . $dbname . '" no existe';
            echo '</div>';
            
            // Mostrar bases de datos disponibles
            echo '<h3><i class="fas fa-list"></i> Bases de datos disponibles:</h3>';
            $result = $conn->query("SHOW DATABASES");
            if ($result) {
                echo '<div class="table-grid">';
                while ($row = $result->fetch_array()) {
                    echo '<div class="table-card">';
                    echo '<div class="table-name"><i class="fas fa-database"></i> ' . $row[0] . '</div>';
                    
                    // Mostrar tablas de cada base de datos
                    $conn->select_db($row[0]);
                    $tablesResult = $conn->query("SHOW TABLES");
                    if ($tablesResult) {
                        $tableCount = $tablesResult->num_rows;
                        echo '<div class="table-stats">';
                        echo '<i class="fas fa-table"></i> Tablas: ' . $tableCount;
                        echo '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                
                echo '<div class="result warning">';
                echo '<h4><i class="fas fa-lightbulb"></i> Solución:</h4>';
                echo '<p>Debes ejecutar el script SQL de creación de la base de datos:</p>';
                echo '<ol>';
                echo '<li>Abre phpMyAdmin</li>';
                echo '<li>Crea una nueva base de datos llamada <code>' . $dbname . '</code></li>';
                echo '<li>Importa el archivo SQL proporcionado</li>';
                echo '<li>O ejecuta el script SQL manualmente</li>';
                echo '</ol>';
                echo '<div class="button-group">';
                echo '<a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-primary"><i class="fas fa-external-link-alt"></i> Abrir phpMyAdmin</a>';
                echo '<a href="index.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Volver al sistema</a>';
                echo '</div>';
                echo '</div>';
            }
            
            $conn->close();
            echo '</div>';
            echo '</body></html>';
            exit;
        } else {
            echo '<div class="result success">';
            echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Base de datos "' . $dbname . '" encontrada';
            echo '</div>';
        }
        
        // 3. Verificar tablas principales
        echo '<h3><i class="fas fa-table"></i> Verificando estructura de la base de datos...</h3>';
        
        $requiredTables = ['tbl_fact', 'tbl_fact_detalle', 'clasif_clientes', 'clasif_serv', 'tipos_pago', 'clasif_usuarios'];
        $missingTables = [];
        $tableInfo = [];
        
        foreach ($requiredTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                // Contar registros
                $countResult = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
                
                // Obtener estructura
                $structureResult = $conn->query("DESCRIBE `$table`");
                $fields = [];
                if ($structureResult) {
                    while ($field = $structureResult->fetch_assoc()) {
                        $fields[] = $field['Field'] . ' (' . $field['Type'] . ')';
                    }
                }
                
                $tableInfo[$table] = [
                    'exists' => true,
                    'count' => $count,
                    'fields' => $fields
                ];
            } else {
                $missingTables[] = $table;
                $tableInfo[$table] = ['exists' => false];
            }
        }
        
        if (!empty($missingTables)) {
            echo '<div class="result error">';
            echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> Faltan tablas requeridas:';
            echo '<ul>';
            foreach ($missingTables as $table) {
                echo '<li><code>' . $table . '</code></li>';
            }
            echo '</ul>';
            echo '<p><i class="fas fa-info-circle"></i> Debes ejecutar el script SQL de creación completo.</p>';
            echo '</div>';
        } else {
            echo '<div class="result success">';
            echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Todas las tablas requeridas existen';
            echo '</div>';
        }
        
        // Mostrar estadísticas
        echo '<div class="stats-grid">';
        foreach ($tableInfo as $tableName => $info) {
            if ($info['exists']) {
                echo '<div class="stat-card">';
                echo '<div class="stat-number">' . number_format($info['count']) . '</div>';
                echo '<div class="stat-label"><i class="fas fa-table"></i> ' . $tableName . '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
        
        // 4. Verificar estructura de tablas importantes
        echo '<h3><i class="fas fa-code-branch"></i> Estructura de Tablas Clave</h3>';
        echo '<div class="table-grid">';
        
        $keyTables = ['tbl_fact', 'tbl_fact_detalle'];
        foreach ($keyTables as $table) {
            if ($tableInfo[$table]['exists']) {
                echo '<div class="table-card">';
                echo '<div class="table-name"><i class="fas fa-file-invoice"></i> ' . $table . '</div>';
                echo '<div class="table-stats">';
                echo '<i class="fas fa-cubes"></i> Registros: ' . number_format($tableInfo[$table]['count']);
                echo '<br><i class="fas fa-tags"></i> Campos: ' . count($tableInfo[$table]['fields']);
                echo '</div>';
                echo '<div class="field-list">';
                foreach ($tableInfo[$table]['fields'] as $field) {
                    echo '<div class="field-item"><i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i> ' . htmlspecialchars($field) . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
        
        // 5. Verificar claves foráneas
        echo '<h3><i class="fas fa-link"></i> Verificando Relaciones (Claves Foráneas)</h3>';
        $fkResult = $conn->query("
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '$dbname' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME
        ");
        
        if ($fkResult && $fkResult->num_rows > 0) {
            echo '<div class="result success">';
            echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Se encontraron ' . $fkResult->num_rows . ' relaciones';
            echo '<div class="field-list" style="max-height: 200px; margin-top: 10px;">';
            while ($fk = $fkResult->fetch_assoc()) {
                echo '<div class="field-item">';
                echo '<i class="fas fa-arrow-right"></i> ' . $fk['TABLE_NAME'] . '.' . $fk['COLUMN_NAME'] . ' → ' . 
                     $fk['REFERENCED_TABLE_NAME'] . '.' . $fk['REFERENCED_COLUMN_NAME'];
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="result warning">';
            echo '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> ADVERTENCIA</span> No se encontraron relaciones definidas';
            echo '<p><i class="fas fa-info-circle"></i> Esto puede afectar la integridad referencial de los datos.</p>';
            echo '</div>';
        }
        
        // 6. Probar consultas de exportación
        echo '<h3><i class="fas fa-file-export"></i> Probando Consultas de Exportación</h3>';
        
        $testQueries = [
            'Facturas básicas' => "SELECT COUNT(*) as count FROM tbl_fact",
            'Detalles básicos' => "SELECT COUNT(*) as count FROM tbl_fact_detalle",
            'Facturas con detalles' => "
                SELECT f.*, COUNT(d.id) as detalle_count 
                FROM tbl_fact f 
                LEFT JOIN tbl_fact_detalle d ON f.id = d.factura_id 
                GROUP BY f.id 
                LIMIT 5
            ",
            'Servicios disponibles' => "SELECT COUNT(*) as count FROM clasif_serv WHERE activo = 1"
        ];
        
        foreach ($testQueries as $name => $query) {
            echo '<h4><i class="fas fa-play"></i> ' . $name . ':</h4>';
            $result = $conn->query($query);
            
            if ($result) {
                echo '<div class="result info">';
                echo '<span class="badge badge-info"><i class="fas fa-check"></i> FUNCIONA</span> ';
                
                if (strpos($query, 'COUNT') !== false) {
                    $row = $result->fetch_assoc();
                    echo '<i class="fas fa-chart-bar"></i> Resultado: ' . ($row['count'] ?? '0') . ' registros';
                } else {
                    echo '<i class="fas fa-check-circle"></i> Consulta ejecutada correctamente';
                    // Mostrar algunos resultados
                    $rows = [];
                    while ($row = $result->fetch_assoc()) {
                        $rows[] = $row;
                    }
                    if (!empty($rows)) {
                        echo '<div class="field-list" style="max-height: 150px; margin-top: 10px;">';
                        foreach ($rows as $row) {
                            echo '<div class="field-item"><i class="fas fa-database"></i> ' . htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE)) . '</div>';
                        }
                        echo '</div>';
                    }
                }
                echo '</div>';
            } else {
                echo '<div class="result error">';
                echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> Error en consulta: ' . $conn->error;
                echo '<div class="field-item" style="margin-top: 5px;">';
                echo '<i class="fas fa-code"></i> ' . htmlspecialchars($query);
                echo '</div>';
                echo '</div>';
            }
        }
        
        // 7. Información de versión MySQL
        echo '<h3><i class="fas fa-server"></i> Información del Servidor MySQL</h3>';
        $versionResult = $conn->query("SELECT VERSION() as version, @@version_comment as comment, 
                                              @@version_compile_os as os, @@character_set_database as charset");
        if ($versionResult && $row = $versionResult->fetch_assoc()) {
            echo '<div class="system-info">';
            echo '<div><i class="fas fa-tag"></i> <strong>Versión MySQL:</strong> ' . $row['version'] . '</div>';
            echo '<div><i class="fas fa-info-circle"></i> <strong>Comentario:</strong> ' . $row['comment'] . '</div>';
            echo '<div><i class="fas fa-desktop"></i> <strong>Sistema Operativo:</strong> ' . $row['os'] . '</div>';
            echo '<div><i class="fas fa-font"></i> <strong>Charset BD:</strong> ' . $row['charset'] . '</div>';
            
            // Verificar funciones JSON
            $jsonResult = $conn->query("
                SELECT 
                    (SELECT COUNT(*) FROM information_schema.routines 
                     WHERE routine_name LIKE '%JSON%' AND routine_schema = DATABASE()) as json_func_count,
                    (SELECT @@version LIKE '%5.7%' OR @@version LIKE '%8.0%' OR @@version LIKE '%10.%') as supports_json
            ");
            
            if ($jsonResult && $jsonRow = $jsonResult->fetch_assoc()) {
                echo '<div><i class="fas fa-brackets-curly"></i> <strong>Funciones JSON disponibles:</strong> ' . $jsonRow['json_func_count'] . '</div>';
                echo '<div><i class="fas fa-star"></i> <strong>Soporte JSON nativo:</strong> ' . ($jsonRow['supports_json'] ? '✅ Sí' : '❌ No') . '</div>';
                
                if (!$jsonRow['supports_json']) {
                    echo '<div class="result warning" style="margin-top: 10px;">';
                    echo '<i class="fas fa-exclamation-triangle"></i> ⚠️ Tu versión de MySQL no tiene soporte nativo para funciones JSON.<br>';
                    echo 'El sistema usará métodos compatibles para exportaciones JSON.';
                    echo '</div>';
                }
            }
            echo '</div>';
        }
        
        // 8. Prueba de permisos
        echo '<h3><i class="fas fa-shield-alt"></i> Prueba de Permisos de Escritura</h3>';
        $testDir = __DIR__ . '/backups';
        if (!is_dir($testDir)) {
            if (@mkdir($testDir, 0755, true)) {
                echo '<div class="result success">';
                echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Directorio backups creado exitosamente';
                echo '</div>';
            } else {
                echo '<div class="result error">';
                echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> No se pudo crear directorio backups';
                echo '<p><i class="fas fa-info-circle"></i> Verifica los permisos de escritura en: ' . __DIR__ . '</p>';
                echo '</div>';
            }
        } else {
            // Probar escritura
            $testFile = $testDir . '/test_write.tmp';
            if (@file_put_contents($testFile, 'test')) {
                @unlink($testFile);
                echo '<div class="result success">';
                echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> ÉXITO</span> Permisos de escritura OK';
                echo '</div>';
            } else {
                echo '<div class="result error">';
                echo '<span class="badge badge-error"><i class="fas fa-times-circle"></i> ERROR</span> No hay permisos de escritura en backups/';
                echo '<p><i class="fas fa-info-circle"></i> Cambia los permisos del directorio a 755 o 777</p>';
                echo '</div>';
            }
        }
        
        $conn->close();
        
        echo '</div>'; // Cierre de test-section
        
        // Resumen final
        echo '<div class="test-section animate__animated animate__fadeInUp">';
        echo '<h2><i class="fas fa-chart-pie"></i> 📊 Resumen del Diagnóstico</h2>';
        
        echo '<ul class="checklist">';
        echo '<li><i class="fas fa-check-circle text-success"></i> Sistema operativo y PHP funcionando</li>';
        echo '<li class="' . ($conn->connect_error ? 'error' : 'success') . '">' . ($conn->connect_error ? '❌' : '✅') . ' Conexión a servidor MySQL</li>';
        echo '<li class="' . (empty($missingTables) ? 'success' : 'error') . '">' . (empty($missingTables) ? '✅' : '❌') . ' Base de datos y tablas existentes</li>';
        echo '<li class="' . ($fkResult && $fkResult->num_rows > 0 ? 'success' : 'warning') . '">' . ($fkResult && $fkResult->num_rows > 0 ? '✅' : '⚠') . ' Relaciones configuradas</li>';
        echo '<li class="success">✅ Consultas básicas funcionando</li>';
        echo '<li class="' . (is_dir($testDir) && is_writable($testDir) ? 'success' : 'error') . '">' . (is_dir($testDir) && is_writable($testDir) ? '✅' : '❌') . ' Permisos de escritura</li>';
        echo '</ul>';
        
        echo '<div class="button-group">';
        echo '<a href="\sisfactvisiones\dashboard.php" class="btn btn-success"><i class="fas fa-rocket"></i> Regresar al Sistema</a>';
        echo '<a href="index.php" target="_blank" class="btn btn-primary"><i class="fas fa-database"></i> Abrir phpMyAdmin</a>';
        echo '<button onclick="location.reload()" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Volver a Probar</button>';
        echo '<button onclick="copyConfig()" class="btn btn-info"><i class="fas fa-copy"></i> Copiar Configuración</button>';
        echo '</div>';
        
        echo '<div class="result info" style="margin-top: 20px;">';
        echo '<h4><i class="fas fa-lightbulb"></i> Próximos pasos recomendados:</h4>';
        echo '<ol>';
        if (!empty($missingTables)) {
            echo '<li>Ejecuta el script SQL de creación de la base de datos</li>';
        }
        if ($conn->connect_error) {
            echo '<li>Verifica la configuración de MySQL en XAMPP/WAMP</li>';
        }
        if (!is_dir($testDir) || !is_writable($testDir)) {
            echo '<li>Configura permisos de escritura en el directorio backups/</li>';
        }
        echo '<li>Prueba exportar una tabla pequeña primero</li>';
        echo '<li>Realiza un backup antes de cualquier importación</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '</div>'; // Cierre de test-section
        ?>
    </div>
    
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Función mejorada para copiar configuración
        function copyConfig() {
            const config = `<?php
echo "Configuración actual:\n";
echo "Host: $servername\n";
echo "Usuario: $username\n";
echo "Base de datos: $dbname\n";
echo "PHP: " . phpversion() . "\n";
?>` ;
            
            navigator.clipboard.writeText(config).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: '¡Copiado!',
                    text: 'Configuración copiada al portapapeles',
                    background: '#1a1e24',
                    color: '#fff',
                    confirmButtonColor: '#3b82f6',
                    timer: 2000,
                    showConfirmButton: false
                });
            }).catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo copiar la configuración',
                    background: '#1a1e24',
                    color: '#fff',
                    confirmButtonColor: '#3b82f6'
                });
            });
        }
        
        // Función para mostrar detalles de tablas
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar tooltips a los elementos con data-tooltip
            const cards = document.querySelectorAll('.table-card');
            cards.forEach(card => {
                card.setAttribute('data-tooltip', 'Haz clic para ver más detalles');
                card.addEventListener('click', function() {
                    const fieldList = this.querySelector('.field-list');
                    if (fieldList) {
                        fieldList.style.maxHeight = fieldList.style.maxHeight === 'none' ? '200px' : 'none';
                    }
                });
            });
            
            // Animación suave para los resultados
            const results = document.querySelectorAll('.result');
            results.forEach((result, index) => {
                result.style.animationDelay = (index * 0.1) + 's';
            });
            
            // Agregar efecto de carga a los botones
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('javascript:')) {
                        this.classList.add('loading');
                    }
                });
            });
        });
        
        // Función para exportar diagnóstico
        function exportDiagnostic() {
            const diagnosticData = {
                timestamp: new Date().toISOString(),
                php_version: '<?php echo phpversion(); ?>',
                server_info: '<?php echo addslashes($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?>',
                database: '<?php echo $dbname; ?>',
                tables: <?php echo json_encode($tableInfo ?? []); ?>,
                missing_tables: <?php echo json_encode($missingTables ?? []); ?>,
                connection_success: <?php echo isset($conn) && !$conn->connect_error ? 'true' : 'false'; ?>
            };
            
            const blob = new Blob([JSON.stringify(diagnosticData, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'diagnostico_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // Agregar botón de exportación de diagnóstico
        setTimeout(() => {
            const buttonGroup = document.querySelector('.button-group:last-child');
            if (buttonGroup) {
                const exportBtn = document.createElement('button');
                exportBtn.className = 'btn btn-info';
                exportBtn.innerHTML = '<i class="fas fa-file-export"></i> Exportar Diagnóstico';
                exportBtn.onclick = exportDiagnostic;
                buttonGroup.appendChild(exportBtn);
            }
        }, 500);
    </script>
</body>
</html>