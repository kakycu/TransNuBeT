<?php
// categorias.php - Windows 11 Dark Mode
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}
	
// Obtener fechas de operación desde init.php
$fecha_inicio_operaciones = obtenerFechaInicioOperaciones();
$mes_cierre_operaciones = obtenerMesCierreOperaciones();

// Formatear fecha de inicio para mostrarla
$fecha_inicio_formateada = '';
$año_inicio_operaciones = date('Y'); // Fallback
if ($fecha_inicio_operaciones) {
    $fecha_inicio_formateada = date('d/m/Y', strtotime($fecha_inicio_operaciones));
    $año_inicio_operaciones = date('Y', strtotime($fecha_inicio_operaciones));
}

// Obtener nombre del mes de cierre
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_cierre_nombre = $meses_completos[$mes_cierre_operaciones - 1] ?? 'Enero';
    
// Parámetros de paginación
$registros_por_pagina = isset($_GET['registros']) && in_array(intval($_GET['registros']), [5, 10, 15, 20, 25, 30, 50, 100, 99999]) ? intval($_GET['registros']) : 15;

$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Validar valores
$registros_por_pagina = max(5, min(99999, $registros_por_pagina)); // Mínimo 5, máximo 99999
$pagina_actual = max(1, $pagina_actual);

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Colores del tema
$temas_windows = [
    'dark' => [
        'nombre' => 'Windows Dark',
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1f1f1f',
        'bg_tertiary' => '#2d2d2d',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a6a6a6',
        'border_color' => '#3d3d3d',
        'accent_color' => $color_accent
    ],
    'light' => [
        'nombre' => 'Windows Light',
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#000000',
        'text_secondary' => '#666666',
        'border_color' => '#e5e5e5',
        'accent_color' => $color_accent
    ]
];

$tema_actual = $temas_windows[$tema_windows];

// Colores de acento disponibles
$colores_accent = [
    '#0078d4' => 'Azul Windows',
    '#107c10' => 'Verde',
    '#5c2d91' => 'Morado',
    '#e81123' => 'Rojo',
    '#ff8c00' => 'Naranja',
    '#0099bc' => 'Cian',
    '#e3008c' => 'Rosa',
    '#8764b8' => 'Lila'
];

try {
    $db = Database::getConnection();
    
    // Obtener usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener total de registros para paginación
    $sql_count = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt_count = $db->query($sql_count);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular total de páginas
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Ajustar página actual si es mayor que el total de páginas
    if ($pagina_actual > $total_paginas && $total_paginas > 0) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
    }
    
    // Obtener categorías paginadas con estadísticas
    // CONSULTA CORREGIDA: Trae las ventas reales de tbl_fact_detalle
    $sql = "SELECT c.*, 
                   COUNT(DISTINCT s.id) as total_servicios, -- Usamos DISTINCT para no contar repetidos por ventas
                   COALESCE(AVG(s.costo), 0) as costo_promedio,
                   COALESCE(SUM(fd.cantidad * fd.precio_unitario), 0) as total_ventas_reales
            FROM clasif_cat_de_serv c
            LEFT JOIN clasif_serv s ON c.id = s.categoria_id
            -- Unimos con los detalles de las facturas
            LEFT JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
            -- Unimos con la factura para validar el estado (que no sume facturas anuladas)
            LEFT JOIN tbl_fact f ON fd.factura_id = f.id 
                AND f.estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA', 'PENDIENTE')
            GROUP BY c.id
            ORDER BY c.descripcion
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $categorias = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_categorias_activas = 0;
    $total_categorias_inactivas = 0;
    $total_servicios_general = 0;
    $total_ventas_general = 0;
    $costo_promedio_general = 0;
    
    foreach ($categorias as $categoria) {
        if ($categoria['activo'] == 1) {
            $total_categorias_activas++;
        } else {
            $total_categorias_inactivas++;
        }
        $total_servicios_general += intval($categoria['total_servicios']);
        $total_ventas_general += floatval($categoria['total_ventas_reales']);
        $costo_promedio_general += floatval($categoria['costo_promedio']);
    }
    
    // Obtener servicios por categoría para las barras de progreso
    $sql_servicios_por_categoria = "SELECT 
        COALESCE(c.descripcion, 'Sin categoría') as categoria, 
        COUNT(s.id) as total_servicios
    FROM clasif_serv s
    LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id
    WHERE s.activo = 1
    GROUP BY c.descripcion
    HAVING total_servicios > 0
    ORDER BY total_servicios DESC";

    try {
        $stmt_servicios = $db->query($sql_servicios_por_categoria);
        $servicios_por_categoria = $stmt_servicios->fetchAll();
        
        // Calcular porcentajes para barras de progreso
        $total_servicios_todos = 0;
        foreach ($servicios_por_categoria as $item) {
            $total_servicios_todos += intval($item['total_servicios']);
        }
        
        // Agregar porcentajes
        foreach ($servicios_por_categoria as &$item) {
            $item['porcentaje'] = $total_servicios_todos > 0 ? 
                round(($item['total_servicios'] / $total_servicios_todos) * 100, 1) : 0;
        }
        
    } catch (Exception $e) {
        error_log("Error al obtener datos para barras de progreso: " . $e->getMessage());
        $servicios_por_categoria = [];
    }

    $tiene_datos_barras = !empty($servicios_por_categoria);
    
} catch (Exception $e) {
    error_log("Error al cargar categorías: " . $e->getMessage());
    $error = "Error al cargar las categorías";
}

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Estadísticas para el sidebar
$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Función para generar URL de paginación
function generarUrlPaginacion($pagina) {
    global $registros_por_pagina;
    
    $params = [];
    $params['pagina'] = $pagina;
    $params['registros'] = $registros_por_pagina;
    
    return 'categorias.php?' . http_build_query($params);
}

// Logo base64 para exportaciones
$logo_path = 'assets/logov.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $mime_type = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
} else {
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" fill="#0078D4" rx="5"/>
            <text x="25" y="28" font-family="Arial" font-size="16" fill="white" text-anchor="middle" font-weight="bold">PDL</text>
            <text x="25" y="40" font-family="Arial" font-size="9" fill="white" text-anchor="middle">VISIONES</text>
        </svg>
    ');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías de Servicios - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Chart.js -->
    <script src="js/chart.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <!-- ===== LIBRERÍAS PARA EXPORTACIÓN ===== -->
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    
    <style>
        :root {
            --win-bg-primary: <?php echo $tema_actual['bg_primary']; ?>;
            --win-bg-secondary: <?php echo $tema_actual['bg_secondary']; ?>;
            --win-bg-tertiary: <?php echo $tema_actual['bg_tertiary']; ?>;
            --win-text-primary: <?php echo $tema_actual['text_primary']; ?>;
            --win-text-secondary: <?php echo $tema_actual['text_secondary']; ?>;
            --win-border-color: <?php echo $tema_actual['border_color']; ?>;
            --win-accent: <?php echo $tema_actual['accent_color']; ?>;
            --win-accent-light: <?php echo $tema_actual['accent_color']; ?>20;
            --win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            --win-radius: 8px;
            --win-radius-sm: 6px;
            --win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Placeholders personalizados para tema dark */
        [data-theme="dark"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders personalizados para tema light */
        [data-theme="light"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para formularios específicos */
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .form-control::placeholder,
        [data-theme="light"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para el buscador de navbar */
        [data-theme="dark"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para search-box en tarjetas */
        [data-theme="dark"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }

        [data-theme="light"] {
            --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        body {
            background-color: var(--win-bg-primary);
            color: var(--win-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            transition: var(--win-transition);
            min-height: 100vh;
        }

        /* Efecto Mica (Windows 11) */
        .mica-effect {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        [data-theme="light"] .mica-effect {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        /* Navbar estilo Windows 11 */
        .win-navbar {
            height: 48px;
            background: var(--win-bg-secondary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 0 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .win-navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .win-navbar-brand i {
            color: var(--win-accent);
        }

        .win-nav-search {
            flex: 1;
            max-width: 400px;
            position: relative;
            margin-bottom: 5px;
            padding: 0 5px;
        }

        .win-nav-search input {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 8px 12px 8px 30px;
            font-size: 13px;
            width: 100%;
            transition: var(--win-transition);
        }

        .win-nav-search input:focus {
            outline: none;
            border-color: var(--win-accent);
            box-shadow: 0 0 0 2px var(--win-accent-light);
        }

        .win-nav-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
    }

        /* Sidebar inmersivo */
        .win-sidebar {
            width: 260px;
            background: var(--win-bg-secondary);
            border-right: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            position: fixed;
            left: 0;
            top: 48px;
            z-index: 999;
            transition: var(--win-transition);
            overflow-y: auto;
            padding: 16px 0;
        }

        .win-sidebar.mini {
            width: 68px;
        }

        .win-sidebar-header {
            padding: 0 16px 16px;
            border-bottom: 1px solid var(--win-border-color);
            margin-bottom: 16px;
        }

        .win-sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-sidebar-user:hover {
            background: var(--win-bg-tertiary);
        }

        .win-sidebar-user-avatar {
            width: 36px;
            height: 36px;
            background: var(--win-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .win-sidebar-user-info h6 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }

        .win-sidebar-user-info small {
            color: var(--win-text-secondary);
            font-size: 12px;
        }

        .win-sidebar.mini .win-sidebar-user-info {
            display: none;
        }

        /* Menú lateral */
        .win-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .win-nav-item {
            margin: 2px 8px;
        }

        .win-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--win-text-secondary);
            text-decoration: none;
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-size: 14px;
            position: relative;
        }

        .win-nav-link:hover {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
        }

        .win-nav-link.active {
            background: var(--win-accent-light);
            color: var(--win-accent);
            font-weight: 500;
        }

        .win-nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 4px;
            bottom: 4px;
            width: 3px;
            background: var(--win-accent);
            border-radius: 0 2px 2px 0;
        }

        .win-nav-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .win-sidebar.mini .win-nav-text {
            display: none;
        }

        .win-nav-badge {
            margin-left: auto;
            background: var(--win-accent);
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
            font-weight: 600;
            transition: var(--win-transition);
        }

        .win-nav-link:hover .win-nav-badge {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
        }

        /* Contenido principal */
        .win-main-content {
            margin-left: 260px;
            margin-top: 48px;
            padding: 24px;
            transition: var(--win-transition);
            min-height: calc(100vh - 48px);
        }

        .win-main-content.sidebar-mini {
            margin-left: 68px;
        }

        /* Estilos para contenido */
        .win-main-content .card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
            transition: var(--win-transition);
            margin-bottom: 1.5rem;
        }

        .win-main-content .card:hover {
            border-color: var(--win-accent);
            box-shadow: var(--win-shadow);
        }

        .win-main-content .card-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border_color);
            padding: 1rem 1.25rem;
        }

        .win-main-content .card-body {
            padding: 1.25rem;
            color: var(--win-text-primary);
        }

        .win-main-content .table {
            color: var(--win-text-primary);
            border-color: var(--win-border-color);
        }

        .win-main-content .table th {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            font-weight: 600;
            color: var(--win-text-primary);
        }

        .win-main-content .table td {
            border-color: var(--win-border_color);
            color: var(--win-text-primary);
        }

        .win-main-content .table-hover tbody tr:hover {
            background: var(--win-bg-tertiary);
        }

        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-primary:hover {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
            border-color: color-mix(in srgb, var(--win-accent) 90%, black);
        }

        .win-main-content .btn-outline-primary {
            color: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-outline-primary:hover {
            background: var(--win-accent);
            color: white;
        }

        .win-main-content .border-bottom {
            border-bottom-color: var(--win-border_color) !important;
        }

        .win-main-content .text-muted {
            color: var(--win-text-secondary) !important;
        }

        .win-main-content .bg-light {
            background-color: var(--win-bg-tertiary) !important;
        }

        .win-main-content .table-light {
            background-color: var(--win-bg-tertiary) !important;
        }

        .win-main-content .table-secondary {
            background-color: var(--win-bg-tertiary) !important;
        }

        /* Panel de temas */
        .win-theme-panel {
            position: fixed;
            top: 48px;
            right: 0;
            width: 300px;
            background: var(--win-bg-secondary);
            border-left: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            z-index: 1001;
            transform: translateX(100%);
            transition: var(--win-transition);
            padding: 24px;
            overflow-y: auto;
        }

        .win-theme-panel.open {
            transform: translateX(0);
        }

        .win-theme-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .win-theme-overlay.open {
            display: block;
        }

        .win-theme-header {
            margin-bottom: 24px;
        }

        .win-theme-option {
            padding: 16px;
            border: 2px solid var(--win-border-color);
            border-radius: var(--win-radius);
            cursor: pointer;
            transition: var(--win-transition);
            text-align: center;
            color: var(--win-text-primary);
        }

        .win-theme-option:hover {
            border-color: var(--win-accent);
        }

        .win-theme-option.active {
            border-color: var(--win-accent);
            background: var(--win-accent-light);
        }

        .win-theme-option[data-theme="dark"] {
            background: #0d0d0d;
            color: white;
        }

        .win-theme-option[data-theme="light"] {
            background: #f3f3f3;
            color: #000000;
        }

        .win-color-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .win-color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--win-transition);
        }

        .win-color-option:hover {
            transform: scale(1.1);
        }

        .win-color-option.active {
            border-color: white;
            box-shadow: 0 0 0 2px var(--win-bg-secondary);
        }

        /* Quick Actions (Botones flotantes) */
        .win-quick-actions {
            position: fixed;
            bottom: 80px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }

        .win-quick-action {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--win-accent);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 1001;
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Quick Actions Expandibles */
        .win-quick-actions-expanded {
            position: absolute;
            bottom: 100%;
            right: 0;
            display: flex;
            flex-direction: column-reverse;
            gap: 10px;
            opacity: 0;
            transform: translateY(20px) scale(0.8);
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1002;
        }

        .win-quick-actions-expanded.show {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        /* Animación circular para los botones expandidos */
        .win-quick-actions-expanded .win-quick-action {
            width: 48px;
            height: 48px;
            font-size: 18px;
            transform: scale(0);
            animation: expandButton 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(1) {
            animation-delay: 0.1s;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(2) {
            animation-delay: 0.2s;
        }

        .win-quick-actions-expanded.show .win-quick-action:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes expandButton {
            0% {
                transform: scale(0) rotate(-90deg);
                opacity: 0;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .win-quick-actions-expanded .action-factura {
            background: #0078d4;
        }

        .win-quick-actions-expanded .action-cliente {
            background: #107c10;
        }

        .win-quick-actions-expanded .action-servicio {
            background: #5c2d91;
        }

        /* Estilos para el dropdown de usuario */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }
        
        .dropdown-header {
            background-color: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm) var(--win-radius-sm) 0 0;
        }
        
        .dropdown-footer {
            background-color: var(--win-bg-tertiary);
            border-radius: 0 0 var(--win-radius-sm) var(--win-radius-sm);
        }
        
        .dropdown-item {
            color: var(--win-text-primary);
            transition: var(--win-transition);
            border-radius: var(--win-radius-sm);
            margin: 2px 4px;
        }
        
        .dropdown-item:hover {
            background-color: var(--win-accent-light);
            color: var(--win-accent);
        }
        
        .dropdown-divider {
            border-color: var(--win-border-color);
        }
        
        .dropdown-item.text-danger:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545 !important;
        }

        /* Formularios */
        .win-main-content .form-control,
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }

        .win-main-content .form-control:focus,
        .win-main-content .form-select:focus {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-accent);
            color: var(--win-text-primary);
            box-shadow: 0 0 0 0.25rem var(--win-accent-light);
        }

        .win-main-content .form-label {
            color: var(--win-text-primary);
            font-weight: 500;
        }

        /* Stats cards */
        .stat-card {
            border: none;
            border-radius: var(--win-radius);
            border-left: 4px solid;
            background: var(--win-bg-secondary);
            transition: var(--win-transition);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--win-shadow);
        }

        .stat-card.success {
            border-left-color: #28a745;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.info {
            border-left-color: #17a2b8;
        }

        .stat-card .text-success {
            color: #28a745 !important;
        }

        .stat-card .text-warning {
            color: #ffc107 !important;
        }

        .stat-card .text-info {
            color: #17a2b8 !important;
        }

        /* Búsqueda y filtros */
        .filtros-card {
            margin-bottom: 1.5rem;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-right: 40px;
        }

        .search-box .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--win-text-secondary);
        }

        /* Barras de progreso animadas */
        .progress-bar-container {
            margin-bottom: 1.5rem;
        }

        .progress-bar-wrapper {
            margin-bottom: 1rem;
        }

        .progress-bar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-bar-label {
            font-weight: 600;
            color: var(--win-text-primary);
            font-size: 0.9rem;
        }

        .progress-bar-stats {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            font-size: 0.8rem;
        }

        .progress-bar-count {
            color: var(--win-accent);
            font-weight: 600;
        }

        .progress-bar-percentage {
            color: var(--win-text-secondary);
        }

        .progress-bar-track {
            height: 8px;
            background: var(--win-bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: var(--win-accent);
            width: 0;
            transition: width 1.5s ease-in-out;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 120, 212, 0.3);
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-bar-empty {
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            border: 1px dashed var(--win-border-color);
        }

        .progress-bar-empty i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--win-text-secondary);
        }

        /* Paginación */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--win-bg-secondary);
            border-top: 1px solid var(--win-border-color);
            border-radius: 0 0 var(--win-radius) var(--win-radius);
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--win-text-secondary);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pagination-controls .form-select {
            width: auto;
            min-width: 100px;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-link {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            padding: 0.375rem 0.75rem;
            border-radius: var(--win-radius-sm);
            text-decoration: none;
            transition: var(--win-transition);
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: var(--win-accent-light);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .page-link.active {
            background: var(--win-accent);
            border-color: var(--win-accent);
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--win-bg-tertiary);
        }

        .page-link.disabled:hover {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-secondary);
        }

        .page-goto {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-goto-input {
            width: 60px;
            text-align: center;
            padding: 0.25rem;
        }

        .page-dots {
            color: var(--win-text-secondary);
            padding: 0.375rem 0.25rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .win-sidebar {
                transform: translateX(-100%);
            }
            
            .win-sidebar.open {
                transform: translateX(0);
            }
            
            .win-main-content {
                margin-left: 0;
            }
            
            .win-quick-actions {
                bottom: 16px;
                right: 16px;
            }
        }

        @media (max-width: 768px) {
            .win-main-content {
                padding: 16px;
            }
            
            .win-nav-search {
                display: none;
            }
            
            .win-quick-action {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }
            
            .win-quick-actions-expanded .win-quick-action {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .page-goto {
                justify-content: center;
            }
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--win-bg-tertiary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--win-border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--win-accent);
        }
/* ==================== CORRECCIÓN DE LEGIBILIDAD TEXT-MUTED ==================== */

/* Para el tema oscuro (por defecto) - Usamos un gris muy claro */
.text-muted, 
.win-main-content .text-muted,
.form-text,
small.text-muted {
    color: #e0e0e0 !important; /* Casi blanco (mucho más visible) */
    opacity: 0.9;
}

/* Para el tema claro - Usamos un gris oscuro */
[data-theme="light"] .text-muted,
[data-theme="light"] .win-main-content .text-muted,
[data-theme="light"] .form-text,
[data-theme="light"] small.text-muted {
    color: #555555 !important; /* Gris oscuro fuerte */
    opacity: 1;
}

/* Ajuste específico para los placeholders de los inputs para que también se vean */
::placeholder {
    color: #cccccc !important;
    opacity: 0.7 !important;
}

[data-theme="light"] ::placeholder {
    color: #666666 !important;
}
/* Badges de roles */

.badge-user {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
    border: none;
}

.badge-operator {
    background: linear-gradient(135deg, #0dcaf0, #0bb5d4);
    color: white;
    border: none;
}

        .badge-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }
        
        .badge-supervisor {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
        }
        
        .badge-editor {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        .badge-programador {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }
        
        .badge-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
        }

/* Estilo para alertas en SweetAlert */
.swal2-popup .alert {
    border-radius: 6px;
    margin: 10px 0;
}

.swal2-popup .badge {
    font-size: 0.9em;
    padding: 5px 10px;
    border-radius: 20px;
}
/* Estilos para el modal de no admin */
.modal-content {
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
}

.modal-header {
    background: var(--win-bg-tertiary);
}

.modal-footer {
    background: var(--win-bg-tertiary);
}

/* Badges para el modal */
.badge-admin {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.badge-user {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.badge-operator {
    background: linear-gradient(135deg, #0dcaf0, #0bb5d4);
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Estilos para botones de exportación */
.btn-group .btn-outline-danger:hover i { color: white; }
.btn-group .btn-outline-success:hover i { color: white; }
.btn-group .btn-outline-primary:hover i { color: white; }
.btn-group .btn-outline-info:hover i { color: white; }
.btn-group .btn-outline-secondary:hover i { color: white; }
.btn-group .btn-outline-warning:hover i { color: white; }

    </style>

</head>
<body>
    <!-- Overlay para tema panel -->
    <div class="win-theme-overlay" id="themeOverlay"></div>

    <!-- Panel de configuración de temas -->
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" 
                     data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" 
                     data-theme="light">
                    <i class="fas fa-sun mb-2"></i>
                    <div>Claro</div>
                </div>
            </div>
        </div>
        
        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>"
                     style="background-color: <?php echo $color; ?>;"
                     data-color="<?php echo $color; ?>"
                     title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>
        
        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" 
                   <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">
                Sidebar compacto
            </label>
        </div>
        
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAnimations" checked>
            <label class="form-check-label" for="toggleAnimations" style="color: var(--win-text-primary);">
                Animaciones
            </label>
        </div>
        
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()">
            <i class="fas fa-save"></i> Guardar cambios
        </button>
    </div>

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">CATEGORIAS - SISFACT PDL Visiones</span>
        </div>
        
        <!-- Buscador -->

        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>

        <!-- Perfil de usuario -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="d-none d-md-inline" style="color: var(--win-text-primary);">
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-chevron-down ms-1 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px;">
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center">
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0" style="color: var(--win-text-primary); font-size: 14px;">
                                <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 12px;">
                                <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?>
                            </small>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Dashboard</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Panel principal</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="perfil.php">
                        <i class="fas fa-user me-3 text-primary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Mi Perfil</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Ver y editar tu información</small>
                        </div>
                    </a>
                </li>
                
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2" href="configuracion.php">
                        <i class="fas fa-cog me-3 text-secondary" style="width: 20px;"></i>
                        <div>
                            <span class="d-block" style="color: var(--win-text-primary);">Configuración</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Preferencias del sistema</small>
                        </div>
                    </a>
                </li>
                
                <li><hr class="dropdown-divider my-1"></li>
<li>
    <a class="dropdown-item d-flex align-items-center py-2 text-warning" href="bloquear_sesion.php">
        <i class="fas fa-lock me-3" style="width: 20px;"></i>
        <div>
            <span class="d-block fw-bold">Bloquear Sesión</span>
            <small class="text-muted d-block" style="font-size: 12px;">Bloquear pantalla temporalmente</small>
        </div>
    </a>
</li>
<li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i>
                        <div>
                            <span class="d-block fw-bold">Cerrar Sesión</span>
                            <small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small>
                        </div>
                    </a>
                </li>
                
                <?php $ultimo_acceso_texto = obtenerUltimoAcceso($_SESSION['usuario_id'] ?? 0);?>
				<li class="dropdown-footer px-3 py-2 mt-1">
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-shield-alt me-1"></i> Sesión segura
					</small>
					<small class="text-muted d-block" style="font-size: 11px;">
						<i class="fas fa-clock me-1"></i>Último acceso: 
						<span class="fw-bold text-info"><?php echo $ultimo_acceso_texto; ?></span>
					</small>
				</li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar inmersivo -->
    <aside class="win-sidebar mica-effect <?php echo $sidebar_mini ? 'mini' : ''; ?>" id="sidebar">
        <a href="perfil.php" style="text-decoration: none; display: block;">
        <div class="win-sidebar-header">
            <div class="win-sidebar-user">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="win-sidebar-user-info">
                    <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></h6>
                    <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></small>
                </div>
            </div>
        </div>
        </a>
        <ul class="win-nav">
			<li class="win-nav-item">
				<a href="dashboard.php" class="win-nav-link">
					<i class="win-nav-icon fas fa-tachometer-alt"></i>
					<!-- Badge con F/Cierre y Tooltip -->
					Dashboard<span class="win-nav-badge" 
						  title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
						  data-bs-toggle="tooltip" 
						  data-bs-placement="auto"
						  style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
						F/Cierre: <?php echo fechaInicioFormateada(9); ?>
					</span>
				</a>
			</li>
<li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $total_categorias; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $total_servicios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $total_usuarios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="reportes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-chart-bar"></i>
                    <span class="win-nav-text">Reportes</span>
					<span class="win-nav-badge">17</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="rentabilidad.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-trend-up"></i>
                    <span class="win-nav-text">Rentabilidad y Costos</span>
                </a>
            </li>
<li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">CONFIGURACIÓN</small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
					<span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="configuracion.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-cog"></i>
                    <span class="win-nav-text">Configuración</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="historico_view.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-history"></i>
                    <span class="win-nav-text">Histórico</span>
                    <span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span>
                </a>
            </li>
        </ul>
        
<?php
// Obtener datos globales de facturación (CUP)
$finanzas = Database::getProgresoFinanciero();
?>
<?php
// Obtener datos globales de facturación (CUP)
$finanzas = Database::getProgresoFinanciero();
?>
<div class="mt-4 px-3">
    <!-- Título y Estado -->
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo fechaInicioFormateada(9); ?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;">
                        <?php echo $finanzas['mensaje']; ?>
                    </small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php echo number_format($finanzas['porcentaje'], 1); ?>%
                </h5>
            </div>

    <!-- Barra de progreso -->
    <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
        <div class="progress-bar" 
             role="progressbar" 
             style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;" 
             aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" 
             aria-valuemin="0" 
             aria-valuemax="100">
        </div>
    </div>
    
    <!-- Datos numéricos: Dinero y Cantidad -->
    <div class="d-flex justify-content-between mt-2 align-items-center">
        <div class="d-flex flex-column">
            <!-- Dinero Real -->
            <small class="text-muted" style="font-size: 12px;">
                <strong>$<?php echo number_format($finanzas['real'], 2); ?></strong>
            </small>
            <!-- Cantidad de Facturas (Nuevo) -->
            <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas
            </small>
        </div>
        
        <!-- Meta -->
        <small class="text-end text-success" style="font-size: 12px;">
            Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?>
        </small>
    </div>
</div>
    </aside>

    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php 
        echo $_SESSION['error']; 
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php 
        echo $_SESSION['warning']; 
        unset($_SESSION['warning']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-tags me-2" style="color: var(--win-accent);"></i>Gestión de Categorías
                </h1>
                <p class="text-muted mb-0">Administre las categorías de servicios del sistema<br>Fecha de Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="actualizarListaCategorias()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                    <a href="nueva_categoria.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Nueva Categoría
                    </a>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success h-100 animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Categorías</div>
                                <div class="h5 mb-0 fw-bold text-success"><?php echo $total_categorias; ?></div>
                                <div class="text-success small">
                                    <i class="fas fa-tags me-1"></i>Registradas en el sistema
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-layer-group fa-2x text-success opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Activas:</small>
                                <small class="fw-bold text-success"><?php echo $total_categorias_activas; ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Inactivas:</small>
                                <small class="fw-bold text-danger"><?php echo $total_categorias_inactivas; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card warning h-100 animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Servicios Totales</div>
                                <div class="h5 mb-0 fw-bold text-warning"><?php echo $total_servicios_general; ?></div>
                                <div class="text-warning small">
                                    <i class="fas fa-list me-1"></i>Servicios por categoría
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-list-alt fa-2x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Promedio por categoría:</small>
                                <small class="fw-bold text-info"><?php echo $total_categorias > 0 ? number_format($total_servicios_general / $total_categorias, 1) : '0.0'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info h-100 animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Costo Promedio</div>
                                <div class="h5 mb-0 fw-bold text-info">$<?php echo $total_categorias > 0 ? number_format($costo_promedio_general / $total_categorias, 2) : '0.00'; ?></div>
                                <div class="text-info small">
                                    <i class="fas fa-dollar-sign me-1"></i>Por categoría
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-line fa-2x text-info opacity-50"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Total servicios:</small>
                                <small class="fw-bold text-primary"><?php echo $total_servicios; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card h-100 animate__animated animate__fadeIn" style="animation-delay: 0.4s; border-left-color: var(--win-accent);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Tasa de Actividad</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-accent);">
                                    <?php 
                                    $tasa_actividad = $total_categorias > 0 ? ($total_categorias_activas / $total_categorias) * 100 : 0;
                                    echo number_format($tasa_actividad, 1); 
                                    ?>%
                                </div>
                                <div style="color: var(--win-accent);" class="small">
                                    <i class="fas fa-chart-pie me-1"></i>Categorías activas
                                </div>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-bar fa-2x opacity-50" style="color: var(--win-accent);"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $tasa_actividad; ?>%; background-color: var(--win-accent);" 
                                     aria-valuenow="<?php echo $tasa_actividad; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo $total_categorias_activas; ?> de <?php echo $total_categorias; ?> categorías activas
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barras de progreso -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-bar me-1"></i>Distribución de Servicios por Categoría
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($tiene_datos_barras): ?>
                            <div class="progress-bar-container">
                                <?php foreach ($servicios_por_categoria as $index => $item): 
                                    $color_index = $index % 8;
                                    $colors = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00', '#0099bc', '#e3008c', '#8764b8'];
                                    $color = $colors[$color_index];
                                ?>
                                <div class="progress-bar-wrapper" data-percentage="<?php echo $item['porcentaje']; ?>">
                                    <div class="progress-bar-header">
                                        <span class="progress-bar-label"><?php echo htmlspecialchars($item['categoria']); ?></span>
                                        <div class="progress-bar-stats">
                                            <span class="progress-bar-count"><?php echo $item['total_servicios']; ?> servicios</span>
                                            <span class="progress-bar-percentage"><?php echo $item['porcentaje']; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress-bar-track">
                                        <div class="progress-bar-fill" style="background-color: <?php echo $color; ?>; --target-width: <?php echo $item['porcentaje']; ?>%;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="progress-bar-empty">
                                <i class="fas fa-chart-bar"></i>
                                <p class="text-muted mb-2">No hay servicios asignados a categorías</p>
                                <p class="small text-muted text-center">
                                    Asigna servicios a categorías desde la gestión de servicios
                                </p>
                                <a href="servicios.php" class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-list me-1"></i>Ir a Servicios
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-pie me-1"></i>Resumen de Categorías
                        </h6>
                        <span class="badge" style="background-color: var(--win-accent);">
                            <i class="fas fa-sync-alt"></i>
                        </span>
                    </div>
                    <div class="card-body">
                        <!-- Estadísticas rápidas -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Categorías Activas</span>
                                <span class="fw-bold text-success"><?php echo $total_categorias_activas; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Total Servicios</span>
                                <span class="fw-bold text-primary"><?php echo $total_servicios_general; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Servicios/Categoría</span>
                                <span class="fw-bold text-info">
                                    <?php echo $total_categorias > 0 ? number_format($total_servicios_general / $total_categorias, 1) : '0.0'; ?>
                                </span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $total_categorias > 0 ? ($total_categorias_activas / $total_categorias * 100) : 0; ?>%">
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo $total_categorias_activas; ?> de <?php echo $total_categorias; ?> activas
                            </small>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- Top categorías con más servicios -->
                        <div class="mb-3">
                            <h6 class="mb-3" style="color: var(--win-text-primary); font-size: 0.9rem;">
                                <i class="fas fa-trophy me-1" style="color: var(--win-accent);"></i>Top Categorías
                            </h6>
                            <?php
                            // Ordenar categorías por cantidad de servicios (descendente)
                            usort($categorias, function($a, $b) {
                                return $b['total_servicios'] - $a['total_servicios'];
                            });
                            
                            // Tomar las primeras 3 categorías
                            $top_categorias = array_slice($categorias, 0, 3);
                            
                            foreach ($top_categorias as $index => $categoria):
                                if ($categoria['total_servicios'] > 0):
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2">
                                    <span class="badge rounded-pill <?php echo $index == 0 ? 'bg-warning' : ($index == 1 ? 'bg-secondary' : 'bg-info'); ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small fw-bold" style="color: var(--win-text-primary);">
                                        <?php echo htmlspecialchars($categoria['descripcion']); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo $categoria['total_servicios']; ?> servicios
                                        <?php if ($categoria['costo_promedio'] > 0): ?>
                                            • $<?php echo number_format($categoria['costo_promedio'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?php echo $categoria['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $categoria['activo'] ? '✓' : '✗'; ?>
                                    </span>
                                </div>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (empty($top_categorias) || array_sum(array_column($top_categorias, 'total_servicios')) == 0):
                            ?>
                            <div class="text-center py-3">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">Sin servicios asignados</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- Acciones rápidas -->
                        <div>
                            <h6 class="mb-3" style="color: var(--win-text-primary); font-size: 0.9rem;">
                                <i class="fas fa-bolt me-1" style="color: var(--win-accent);"></i>Acciones Rápidas
                            </h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="nueva_categoria.php" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fas fa-plus me-1"></i>Nueva
                                    </a>
                                </div>
                                <div class="col-6">
                                    <button onclick="exportarCategorias()" class="btn btn-sm btn-outline-success w-100">
                                        <i class="fas fa-download me-1"></i>Exportar
                                    </button>
                                </div>
                                <div class="col-6">
                                    <a href="servicios.php" class="btn btn-sm btn-outline-info w-100">
                                        <i class="fas fa-eye me-1"></i>Ver Servicios
                                    </a>
                                </div>
                                <div class="col-6">
                                    <button onclick="imprimirReporte()" class="btn btn-sm btn-outline-secondary w-100">
                                        <i class="fas fa-print me-1"></i>Imprimir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda y filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-search me-1"></i>Buscar Categorías
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8">
                        <div class="search-box">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Buscar categoría por código o descripción...">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="filterStatus">
                            <option value="">Todos los estados</option>
                            <option value="activo">Solo activas</option>
                            <option value="inactivo">Solo inactivas</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Categorías -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">Lista de Categorías</h6>
                <div>
                    <span class="badge bg-primary me-2"><?php echo count($categorias); ?> categorías</span>
                    <span class="badge bg-success">Total servicios: <?php echo $total_servicios_general; ?></span>
                    
					<!-- ===== BOTONES DE EXPORTACIÓN - CON TODOS LOS FORMATOS ===== -->
					<div class="btn-group ms-2" role="group">
						<button type="button" class="btn btn-sm btn-outline-success" onclick="exportarExcel()" 
								title="Exportar a Excel" data-bs-toggle="tooltip">
							<i class="fas fa-file-excel"></i>
						</button>          
						<button type="button" class="btn btn-sm btn-outline-danger" onclick="exportarPDF()" 
								title="Exportar a PDF" data-bs-toggle="tooltip">
							<i class="fas fa-file-pdf"></i>
						</button>
						<button type="button" class="btn btn-sm btn-outline-primary" onclick="exportarWord()" 
								title="Exportar a Word" data-bs-toggle="tooltip">
							<i class="fas fa-file-word"></i>
						</button>
						<button type="button" class="btn btn-sm btn-outline-info" onclick="exportarCSV()" 
								title="Exportar a CSV" data-bs-toggle="tooltip">
							<i class="fas fa-file-csv"></i>
						</button>
						<button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportarTXT()" 
								title="Exportar a TXT" data-bs-toggle="tooltip">
							<i class="fas fa-file-alt"></i>
						</button>
						<button type="button" class="btn btn-sm btn-outline-warning" onclick="imprimirTabla()" 
								title="Imprimir" data-bs-toggle="tooltip">
							<i class="fas fa-print"></i>
						</button>
						<button type="button" class="btn btn-sm btn-outline-success" onclick="exportarCategorias()" 
								title="Todos los formatos" data-bs-toggle="tooltip">
							<i class="fas fa-download"></i>
						</button>
					</div>
				</div>
            </div>
            
            <!-- Paginación superior -->
            <?php if ($total_registros > 0): ?>
            <div class="pagination-container" style="border-bottom: 1px solid var(--win-border-color);">
                <div class="pagination-info">
                    <?php 
                    $inicio = min($offset + 1, $total_registros);
                    $fin = min($offset + $registros_por_pagina, $total_registros);
                    ?>
                    Mostrando <?php echo $inicio; ?> - <?php echo $fin; ?> de <?php echo $total_registros; ?> categorías 
                    (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                </div>
                
                <div class="pagination-controls">
                    <div class="pagination-nav">
                        <!-- Botón Primera Página -->
                        <a href="<?php echo generarUrlPaginacion(1); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Primera página">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- Botón Anterior -->
                        <a href="<?php echo generarUrlPaginacion(max(1, $pagina_actual - 1)); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Página anterior">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        
                        <!-- Números de página -->
                        <?php 
                        $inicio_pagina = max(1, $pagina_actual - 2);
                        $fin_pagina = min($total_paginas, $pagina_actual + 2);
                        
                        if ($inicio_pagina > 1): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio_pagina; $i <= $fin_pagina; $i++): ?>
                            <a href="<?php echo generarUrlPaginacion($i); ?>" class="page-link <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($fin_pagina < $total_paginas): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                        
                        <!-- Botón Siguiente -->
                        <a href="<?php echo generarUrlPaginacion(min($total_paginas, $pagina_actual + 1)); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Página siguiente">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- Botón Última Página -->
                        <a href="<?php echo generarUrlPaginacion($total_paginas); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Última página">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                    
                    <!-- Registros por página -->
                    <select class="form-select" id="registrosPorPaginaTop" onchange="cambiarRegistrosPorPagina(this.value)">
                        <option value="5" <?php echo $registros_por_pagina == 5 ? 'selected' : ''; ?>>5 por página</option>
                        <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
						<option value="15" <?php echo $registros_por_pagina == 15 ? 'selected' : ''; ?>>15 por página</option>
						<option value="20" <?php echo $registros_por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25 por página</option>
						<option value="30" <?php echo $registros_por_pagina == 30 ? 'selected' : ''; ?>>30 por página</option>
                        <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
						<option value="99999" <?php echo $registros_por_pagina == 99999 ? 'selected' : ''; ?>>Todos los Registros</option>
                    </select>
                    
                    <!-- Ir a página -->
                    <div class="page-goto">
                        <span style="color: var(--win-text-secondary);">Ir a:</span>
                        <input type="number" class="form-control page-goto-input" 
                               id="gotoPageTop" 
                               min="1" 
                               max="<?php echo $total_paginas; ?>" 
                               value="<?php echo $pagina_actual; ?>">
                        <button class="btn btn-outline-primary btn-sm" onclick="irAPaginaDesdeTop()">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="categoriasTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No.</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Servicios</th>
                                <th>Costo Promedio</th>
                                <th>Total Ventas</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categorias)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-tags text-muted fa-2x mb-2 d-block"></i>
                                        No hay categorías registradas
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $contador = $offset + 1;
                                foreach ($categorias as $categoria): 
                                ?>
                                    <tr data-status="<?php echo $categoria['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <td>
                                            <span class="badge bg-secondary" style="background-color: var(--win-accent);">
                                                <?php echo $contador++; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($categoria['codigo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: var(--win-text-primary);">
                                                <?php echo htmlspecialchars($categoria['descripcion']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark badge bg-warning">
                                                <?php echo $categoria['total_servicios']; ?> servicios
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo $categoria['costo_promedio'] > 0 ? 'text-success' : 'text-muted'; ?>">
                                                $<?php echo number_format($categoria['costo_promedio'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                $<?php echo number_format($categoria['total_ventas_reales'] ?? 0, 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $categoria['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $categoria['activo'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="editar_categoria.php?id=<?php echo $categoria['id']; ?>" 
                                                   class="btn btn-outline-warning" 
                                                   title="Editar" data-bs-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="servicios.php?categoria_id=<?php echo $categoria['id']; ?>" 
                                                   class="btn btn-outline-info" 
                                                   title="Ver Servicios" data-bs-toggle="tooltip">
                                                    <i class="fas fa-list"></i>
                                                </a>
<button onclick="eliminarCategoria(<?php echo $categoria['id']; ?>, '<?php echo addslashes(htmlspecialchars($categoria['descripcion'])); ?>')" 
        class="btn btn-outline-danger" 
        title="Eliminar" data-bs-toggle="tooltip">
    <i class="fas fa-trash"></i>
</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación inferior -->
                <?php if ($total_registros > 0): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php 
                        $inicio = min($offset + 1, $total_registros);
                        $fin = min($offset + $registros_por_pagina, $total_registros);
                        ?>
                        Mostrando <?php echo $inicio; ?> - <?php echo $fin; ?> de <?php echo $total_registros; ?> categorías 
                        (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                    </div>
                    
                    <div class="pagination-controls">
                        <div class="pagination-nav">
                            <!-- Botón Primera Página -->
                            <a href="<?php echo generarUrlPaginacion(1); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Primera página">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            
                            <!-- Botón Anterior -->
                            <a href="<?php echo generarUrlPaginacion(max(1, $pagina_actual - 1)); ?>" class="page-link <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>" title="Página anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                            
                            <!-- Números de página -->
                            <?php 
                            $inicio_pagina = max(1, $pagina_actual - 2);
                            $fin_pagina = min($total_paginas, $pagina_actual + 2);
                            
                            if ($inicio_pagina > 1): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            
                            <?php for ($i = $inicio_pagina; $i <= $fin_pagina; $i++): ?>
                                <a href="<?php echo generarUrlPaginacion($i); ?>" class="page-link <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($fin_pagina < $total_paginas): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <a href="<?php echo generarUrlPaginacion(min($total_paginas, $pagina_actual + 1)); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Página siguiente">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            
                            <!-- Botón Última Página -->
                            <a href="<?php echo generarUrlPaginacion($total_paginas); ?>" class="page-link <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>" title="Última página">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                        
                        <!-- Registros por página -->
                    <select class="form-select" id="registrosPorPaginaTop" onchange="cambiarRegistrosPorPagina(this.value)">
                        <option value="5" <?php echo $registros_por_pagina == 5 ? 'selected' : ''; ?>>5 por página</option>
                        <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
						<option value="15" <?php echo $registros_por_pagina == 15 ? 'selected' : ''; ?>>15 por página</option>
						<option value="20" <?php echo $registros_por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25 por página</option>
						<option value="30" <?php echo $registros_por_pagina == 30 ? 'selected' : ''; ?>>30 por página</option>
                        <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
						<option value="99999" <?php echo $registros_por_pagina == 99999 ? 'selected' : ''; ?>>Todos los Registros</option>
                    </select>
                        
                        <!-- Ir a página -->
                        <div class="page-goto">
                            <span style="color: var(--win-text-secondary);">Ir a:</span>
                            <input type="number" class="form-control page-goto-input" 
                                   id="gotoPage" 
                                   min="1" 
                                   max="<?php echo $total_paginas; ?>" 
                                   value="<?php echo $pagina_actual; ?>">
                            <button class="btn btn-outline-primary btn-sm" onclick="irAPagina()">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <!-- Acciones expandidas -->
        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-factura" onclick="window.location.href='nueva_factura.php'" title="Nueva Factura">
                <i class="fas fa-file-invoice"></i>
            </button>
            <button class="win-quick-action action-cliente" onclick="window.location.href='nuevo_cliente.php'" title="Nuevo Cliente">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio" onclick="window.location.href='nuevo_servicio.php'" title="Nuevo Servicio">
                <i class="fas fa-plus-circle"></i>
            </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <!-- ===== SCRIPTS DE EXPORTACIÓN - AGREGADOS AL FINAL ===== -->
    <script>
        // ============================================
        // VARIABLES GLOBALES PARA EXPORTACIÓN
        // ============================================
        const totalCategorias = <?php echo $total_categorias; ?>;
        const categoriasActivas = <?php echo $total_categorias_activas; ?>;
        const categoriasInactivas = <?php echo $total_categorias_inactivas; ?>;
        const totalServicios = <?php echo $total_servicios_general; ?>;
        const totalVentas = <?php echo $total_ventas_general; ?>;
        const usuarioNombre = "<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISFACT PDL VISIONES'); ?>";
        const logoBase64 = '<?php echo $logo_base64; ?>';
		
		// ===== NUEVAS VARIABLES PARA FECHAS DE CIERRE DE OPERACIONES =====
		const fechaInicioOperaciones = "<?php echo $fecha_inicio_operaciones; ?>";
		const fechaInicioOperacionesFormateada = "<?php echo $fecha_inicio_formateada; ?>";
		const mesCierreOperaciones = <?php echo $mes_cierre_operaciones; ?>;
		const mesCierreOperacionesNombre = "<?php echo $mes_cierre_nombre; ?>";
		const añoInicioOperaciones = <?php echo $año_inicio_operaciones; ?>; 

		// ============================================
		// FECHA DE CIERRE DE OPERACIONES PARA EXPORTACIONES - CORREGIDO
		// ============================================
		function obtenerFechaOperacion() {
			if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
				// Dividir la fecha manualmente para evitar problemas de zona horaria
				const partes = fechaInicioOperaciones.split('-');
				const año = parseInt(partes[0]);
				const mes = parseInt(partes[1]) - 1; // Los meses en JS son 0-11
				const dia = parseInt(partes[2]);
				
				const fecha = new Date(año, mes, dia);
				const opciones = { 
					weekday: 'long', 
					year: 'numeric', 
					month: 'long', 
					day: 'numeric'
				};
				return fecha.toLocaleDateString('es-ES', opciones);
			}
			
			// Fallback: usar fecha actual
			const fecha = new Date();
			const opciones = { 
				weekday: 'long', 
				year: 'numeric', 
				month: 'long', 
				day: 'numeric'
			};
			return fecha.toLocaleDateString('es-ES', opciones);
		}

		function obtenerFechaOperacionCorta() {
			if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
				return fechaInicioOperaciones; // Formato YYYY-MM-DD
			}
			
			// Fallback: usar fecha actual
			const fecha = new Date();
			const año = fecha.getFullYear();
			const mes = String(fecha.getMonth() + 1).padStart(2, '0');
			const dia = String(fecha.getDate()).padStart(2, '0');
			return `${año}-${mes}-${dia}`;
		}

		function obtenerPeriodoCierre() {
			// Usar el año de la fecha de inicio de operaciones
			if (fechaInicioOperaciones && fechaInicioOperaciones !== '') {
				const partes = fechaInicioOperaciones.split('-');
				const año = partes[0];
				return `${mesCierreOperacionesNombre} / ${año}`;
			}
			return `${mesCierreOperacionesNombre} / ${new Date().getFullYear()}`;
		}

        
        // ============================================
        // FUNCIÓN PARA OBTENER DATOS DE LA TABLA
        // ============================================
        function obtenerDatosTabla() {
            const tabla = document.getElementById('categoriasTable');
            if (!tabla) return { datos: [], totalFiltrado: 0 };
            
            const datos = [];
            const filas = Array.from(tabla.querySelectorAll('tbody tr'));
            
            filas.forEach(fila => {
                if (fila.querySelector('td[colspan]')) return;
                
                const celdas = fila.querySelectorAll('td');
                if (celdas.length >= 8) {
                    const filaDatos = [
                        celdas[0]?.querySelector('.badge')?.textContent?.trim() || '-',           // No.
                        celdas[1]?.querySelector('.badge')?.textContent?.trim() || '-',           // Código
                        celdas[2]?.querySelector('.fw-bold')?.textContent?.trim() || '-',         // Descripción
                        celdas[3]?.querySelector('.badge')?.textContent?.replace('servicios', '').trim() || '0', // Servicios
                        celdas[4]?.textContent?.trim().replace('$', '') || '0.00',               // Costo Promedio
                        celdas[5]?.textContent?.trim().replace('$', '') || '0.00',               // Total Ventas
                        celdas[6]?.querySelector('.badge')?.textContent?.trim() || '-',          // Estado
                    ];
                    datos.push(filaDatos);
                }
            });
            
            return {
                datos: datos,
                totalFiltrado: datos.length,
                totalGeneral: totalCategorias
            };
        }

        // ============================================
        // FORMATEAR FECHA PARA ARCHIVOS
        // ============================================
        function formatearFechaParaArchivo() {
            const fecha = new Date();
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            const horas = String(fecha.getHours()).padStart(2, '0');
            const minutos = String(fecha.getMinutes()).padStart(2, '0');
            const segundos = String(fecha.getSeconds()).padStart(2, '0');
            return `${año}${mes}${dia}_${horas}${minutos}${segundos}`;
        }

// ============================================
// EXPORTAR A PDF - DESCARGA DIRECTA (SIN VENTANA ADICIONAL)
// ============================================
function exportarPDF() {
    try {
        // Mostrar loading
        Swal.fire({
            title: 'Generando PDF...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Obtener datos de la tabla
        const tablaDatos = obtenerDatosTabla();
        
        if (!tablaDatos || tablaDatos.datos.length === 0) {
            Swal.close();
            Swal.fire({
                icon: 'warning',
                title: 'Sin datos',
                text: 'No hay categorías para exportar'
            });
            return;
        }

        // Crear contenido HTML para el PDF (el mismo que funcionó)
        let contenidoHTML = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Reporte de Categorías</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 20px;
                        color: #000;
                    }
                    h1 { 
                        color: #0078d4; 
                        text-align: center;
                        margin: 0 0 10px 0;
                        font-size: 24px;
                    }
                    h2 {
                        text-align: center;
                        margin: 0 0 15px 0;
                        font-size: 18px;
                        color: #333;
                    }
                    .info {
                        text-align: center;
                        margin: 10px 0;
                        font-weight: bold;
                        color: #0056b3;
                    }
                    .stats {
                        display: flex;
                        justify-content: space-between;
                        margin: 20px 0;
                        gap: 10px;
                    }
                    .stat-box {
                        background: #f5f5f5;
                        padding: 10px;
                        border-radius: 5px;
                        text-align: center;
                        flex: 1;
                        border: 1px solid #ddd;
                    }
                    .stat-box strong {
                        display: block;
                        font-size: 18px;
                        color: #0078d4;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                        font-size: 12px;
                    }
                    th {
                        background: #0078d4;
                        color: white;
                        padding: 8px;
                        border: 1px solid #0056b3;
                    }
                    td {
                        padding: 6px;
                        border: 1px solid #ccc;
                    }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .footer {
                        text-align: center;
                        margin-top: 30px;
                        color: #666;
                        font-size: 11px;
                    }
                </style>
            </head>
            <body>
                <h1>SISFACT PDL VISIONES</h1>
                <h2>REPORTE DE CATEGORÍAS DE SERVICIOS</h2>
                
                <div class="info">
                    Fecha: ${new Date().toLocaleDateString('es-ES')} - Usuario: ${usuarioNombre}
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <strong>${totalCategorias}</strong>
                        Total Categorías
                    </div>
                    <div class="stat-box">
                        <strong>${categoriasActivas}</strong>
                        Categorías Activas
                    </div>
                    <div class="stat-box">
                        <strong>${totalServicios}</strong>
                        Total Servicios
                    </div>
                    <div class="stat-box">
                        <strong>$${totalVentas.toFixed(2)}</strong>
                        Ventas Totales
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Servicios</th>
                            <th>Costo Prom.</th>
                            <th>Ventas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Agregar filas
        tablaDatos.datos.forEach((fila, index) => {
            contenidoHTML += `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="text-center">${fila[1] || '-'}</td>
                    <td>${fila[2] || '-'}</td>
                    <td class="text-center">${fila[3] || '0'}</td>
                    <td class="text-right">$${parseFloat(fila[4] || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(fila[5] || 0).toFixed(2)}</td>
                    <td class="text-center">${fila[6] || '-'}</td>
                </tr>
            `;
        });

        contenidoHTML += `
                    </tbody>
                </table>
                
                <div class="footer">
                    Generado por SISFACT PDL Visiones - ${new Date().toLocaleString('es-ES')}
                </div>
            </body>
            </html>
        `;

        // Crear un iframe oculto para la impresión
        const iframe = document.createElement('iframe');
        iframe.style.position = 'absolute';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = 'none';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);

        // Escribir el contenido en el iframe
        iframe.contentDocument.open();
        iframe.contentDocument.write(contenidoHTML);
        iframe.contentDocument.close();

        // Esperar a que el iframe cargue
        iframe.onload = function() {
            setTimeout(() => {
                try {
                    // Llamar a la función de impresión del iframe
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                    
                    // Cerrar el loading después de enviar a imprimir
                    Swal.close();
                    
                    // Mostrar mensaje de éxito
                    Swal.fire({
                        icon: 'success',
                        title: 'PDF Generado',
                        text: 'Se ha abierto el cuadro de diálogo de impresión. Seleccione "Guardar como PDF"',
                        timer: 4000,
                        showConfirmButton: true,
                        confirmButtonColor: '#0078d4'
                    });
                    
                    // Remover el iframe después de un tiempo
                    setTimeout(() => {
                        document.body.removeChild(iframe);
                    }, 1000);
                    
                } catch (e) {
                    console.error('Error al imprimir:', e);
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al generar el PDF'
                    });
                }
            }, 500);
        };

    } catch (error) {
        Swal.close();
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al generar el PDF: ' + error.message
        });
    }
}

        // ============================================
        // EXPORTAR A EXCEL
        // ============================================
        function exportarExcel() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay categorías para exportar' });
                    return;
                }

				const fechaOperacion = obtenerFechaOperacion();
				const fechaOperacionCorta = obtenerFechaOperacionCorta();
				const periodoCierre = obtenerPeriodoCierre();

                const wb = XLSX.utils.book_new();
                
                const ws_data = [
                    ['REPORTE DE CATEGORÍAS - SISFACT PDL VISIONES'],
					[`Fecha de Inicio de Operaciones: ${fechaOperacion}`],
					[`Período de Cierre: ${periodoCierre}`],
					[`Fecha Generación: ${new Date().toLocaleString('es-ES')}`],
					[`Usuario: ${usuarioNombre}`],
                    [],
                    ['ESTADÍSTICAS'],
                    ['Total Categorías', totalCategorias],
                    ['Categorías Activas', categoriasActivas],
                    ['Categorías Inactivas', categoriasInactivas],
                    ['Total Servicios', totalServicios],
                    ['Total Ventas', `$${totalVentas.toFixed(2)}`],
                    [],
                    ['No.', 'Código', 'Descripción', 'Servicios', 'Costo Promedio', 'Total Ventas', 'Estado'],
                    ...tablaDatos.datos.map(fila => [
                        parseInt(fila[0]), 
                        fila[1], 
                        fila[2], 
                        parseInt(fila[3]), 
                        parseFloat(fila[4]), 
                        parseFloat(fila[5]), 
                        fila[6]
                    ])
                ];
                
                const ws = XLSX.utils.aoa_to_sheet(ws_data);
                ws['!cols'] = [
                    { wch: 6 }, { wch: 12 }, { wch: 35 }, { wch: 10 }, 
                    { wch: 15 }, { wch: 15 }, { wch: 12 }
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, 'Categorías');
                XLSX.writeFile(wb, `Categorias_${formatearFechaParaArchivo()}.xlsx`);
                
                Swal.fire({ icon: 'success', title: 'Excel Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A WORD
        // ============================================
        function exportarWord() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay categorías para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
				const periodoCierre = obtenerPeriodoCierre();

                let html = `
                    <html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" xmlns="http://www.w3.org/TR/REC-html40">
                    <head>
                        <meta charset="UTF-8">
                        <title>Reporte de Categorías</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 1.5cm; }
                            h1 { color: #0078d4; border-bottom: 2px solid #0078d4; padding-bottom: 10px; }
                            .fecha-operacion { text-align: center; font-weight: bold; color: #0056b3; margin: 15px 0; }
                            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                            th { background: #0078d4; color: white; padding: 8px; border: 1px solid #0056b3; }
                            td { padding: 6px; border: 1px solid #999; }
                            .stats { display: flex; justify-content: space-between; margin: 20px 0; }
                            .stat-box { background: #f5f5f5; padding: 10px; border-radius: 5px; width: 23%; text-align: center; }
                        </style>
                    </head>
                    <body>
                        <h1>REPORTE DE CATEGORÍAS DE SERVICIOS</h1>
						<div class="fecha-operacion">
							Fecha de Inicio de Operaciones: ${fechaOperacion}
						</div>
						<div style="text-align: center; color: #666; margin-bottom: 20px;">
							Período de Cierre: ${periodoCierre}
						</div>
                        <p><strong>Usuario:</strong> ${usuarioNombre}</p>
                        
                        <div class="stats">
                            <div class="stat-box"><strong>Total:</strong> ${totalCategorias}</div>
                            <div class="stat-box"><strong>Activas:</strong> ${categoriasActivas}</div>
                            <div class="stat-box"><strong>Servicios:</strong> ${totalServicios}</div>
                            <div class="stat-box"><strong>Ventas:</strong> $${totalVentas.toFixed(2)}</div>
                        </div>
                        
                        <table>
                            <tr>
                                <th>No.</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Servicios</th>
                                <th>Costo Promedio</th>
                                <th>Total Ventas</th>
                                <th>Estado</th>
                            </tr>
                            ${tablaDatos.datos.map(fila => `
                                <tr>
                                    <td align="center">${fila[0]}</td>
                                    <td align="center">${fila[1]}</td>
                                    <td>${fila[2]}</td>
                                    <td align="center">${fila[3]}</td>
                                    <td align="right">$${parseFloat(fila[4]).toFixed(2)}</td>
                                    <td align="right">$${parseFloat(fila[5]).toFixed(2)}</td>
                                    <td align="center">${fila[6]}</td>
                                </tr>
                            `).join('')}
                        </table>
                        
                        <p style="margin-top: 30px; text-align: center; color: #666;">
                            Generado por SISFACT PDL Visiones - ${fechaOperacion}
                        </p>
                    </body>
                    </html>
                `;

                const blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Categorias_${formatearFechaParaArchivo()}.doc`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'Word Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A CSV
        // ============================================
        function exportarCSV() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay categorías para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
				const periodoCierre = obtenerPeriodoCierre();

                let csv = '';
                csv += 'REPORTE DE CATEGORÍAS - SISFACT PDL VISIONES\n';
				csv += `Fecha de Inicio de Operaciones,${fechaOperacion}\n`;
				csv += `Período de Cierre,${periodoCierre}\n`;
                csv += `Usuario,${usuarioNombre}\n`;
                csv += `Total Categorías,${totalCategorias}\n`;
                csv += `Categorías Activas,${categoriasActivas}\n`;
                csv += `Total Servicios,${totalServicios}\n`;
                csv += `Total Ventas,$${totalVentas.toFixed(2)}\n`;
                csv += '\n';
                csv += 'No.,Código,Descripción,Servicios,Costo Promedio,Total Ventas,Estado\n';
                
                tablaDatos.datos.forEach(fila => {
                    const filaEscapada = fila.map(celda => {
                        if (typeof celda === 'string' && (celda.includes(',') || celda.includes('"'))) {
                            return `"${celda.replace(/"/g, '""')}"`;
                        }
                        return celda;
                    });
                    csv += filaEscapada.join(',') + '\n';
                });

                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Categorias_${formatearFechaParaArchivo()}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'CSV Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // EXPORTAR A TXT
        // ============================================
        function exportarTXT() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay categorías para exportar' });
                    return;
                }

                const fechaOperacion = obtenerFechaOperacion();
				const periodoCierre = obtenerPeriodoCierre();
                
                let contenido = '';
                contenido += '='.repeat(100) + '\n';
                contenido += 'REPORTE DE CATEGORÍAS DE SERVICIOS - SISFACT PDL VISIONES\n';
                contenido += '='.repeat(100) + '\n\n';
				contenido += `Fecha de Inicio de Operaciones: ${fechaOperacion}\n`;
				contenido += `Período de Cierre: ${periodoCierre}\n`;
                contenido += `Usuario: ${usuarioNombre}\n\n`;
                contenido += 'ESTADÍSTICAS:\n';
                contenido += `  Total Categorías: ${totalCategorias}\n`;
                contenido += `  Categorías Activas: ${categoriasActivas}\n`;
                contenido += `  Categorías Inactivas: ${categoriasInactivas}\n`;
                contenido += `  Total Servicios: ${totalServicios}\n`;
                contenido += `  Total Ventas: $${totalVentas.toFixed(2)}\n\n`;
                contenido += '-'.repeat(100) + '\n\n';
                
                // Encabezados
                contenido += 'No.'.padEnd(6) + 
                            'Código'.padEnd(12) + 
                            'Descripción'.padEnd(35) + 
                            'Servicios'.padEnd(12) + 
                            'Costo Promedio'.padEnd(18) + 
                            'Total Ventas'.padEnd(18) + 
                            'Estado\n';
                contenido += '-'.repeat(100) + '\n';
                
                // Datos
                tablaDatos.datos.forEach(fila => {
                    contenido += fila[0].padEnd(6) + 
                                fila[1].padEnd(12) + 
                                fila[2].substring(0, 30).padEnd(35) + 
                                fila[3].padEnd(12) + 
                                ('$' + parseFloat(fila[4]).toFixed(2)).padEnd(18) + 
                                ('$' + parseFloat(fila[5]).toFixed(2)).padEnd(18) + 
                                fila[6] + '\n';
                });
                
                contenido += '\n' + '='.repeat(100) + '\n';
                contenido += 'FIN DEL REPORTE\n';
                contenido += '='.repeat(100) + '\n';

                const blob = new Blob(['\ufeff' + contenido], { type: 'text/plain;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Categorias_${formatearFechaParaArchivo()}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                Swal.fire({ icon: 'success', title: 'TXT Exportado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // IMPRIMIR TABLA
        // ============================================
        function imprimirTabla() {
            try {
                const tablaDatos = obtenerDatosTabla();
                if (tablaDatos.datos.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay categorías para imprimir' });
                    return;
                }

				const fechaOperacion = obtenerFechaOperacion();
				const periodoCierre = obtenerPeriodoCierre();

                const printWindow = window.open('', '_blank', 'width=1100,height=850');
                
                let html = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Reporte de Categorías</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { color: #0078d4; border-bottom: 2px solid #0078d4; padding-bottom: 10px; }
                            .fecha-operacion { text-align: center; font-weight: bold; color: #0056b3; margin: 15px 0; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th { background: #0078d4; color: white; padding: 8px; border: 1px solid #0056b3; }
                            td { padding: 6px; border: 1px solid #ddd; }
                            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                            .stat-box { background: #f0f0f0; padding: 10px; border-radius: 5px; }
                            @media print { @page { margin: 1cm; } }
                        </style>
                    </head>
                    <body>
                        <h1>REPORTE DE CATEGORÍAS DE SERVICIOS</h1>
						<div class="fecha-operacion">
							Fecha de Inicio de Operaciones: ${fechaOperacion}
						</div>
						<div style="text-align: center; color: #666; margin-bottom: 20px;">
							Período de Cierre: ${periodoCierre}
						</div>
                        <p><strong>Usuario:</strong> ${usuarioNombre}</p>
                        
                        <div class="stats">
                            <div class="stat-box"><strong>Total:</strong> ${totalCategorias}</div>
                            <div class="stat-box"><strong>Activas:</strong> ${categoriasActivas}</div>
                            <div class="stat-box"><strong>Servicios:</strong> ${totalServicios}</div>
                            <div class="stat-box"><strong>Ventas:</strong> $${totalVentas.toFixed(2)}</div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Servicios</th>
                                    <th>Costo Promedio</th>
                                    <th>Total Ventas</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tablaDatos.datos.map(fila => `
                                    <tr>
                                        <td align="center">${fila[0]}</td>
                                        <td align="center">${fila[1]}</td>
                                        <td>${fila[2]}</td>
                                        <td align="center">${fila[3]}</td>
                                        <td align="right">$${parseFloat(fila[4]).toFixed(2)}</td>
                                        <td align="right">$${parseFloat(fila[5]).toFixed(2)}</td>
                                        <td align="center">${fila[6]}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <p style="text-align: center; margin-top: 30px; color: #666;">
                            SISFACT PDL Visiones - ${fechaOperacion}
                        </p>
                    </body>
                    </html>
                `;

                printWindow.document.write(html);
                printWindow.document.close();
                
                setTimeout(() => {
                    printWindow.focus();
                    printWindow.print();
                    setTimeout(() => printWindow.close(), 1000);
                }, 500);

            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message });
            }
        }

        // ============================================
        // TUS FUNCIONES ORIGINALES (SE MANTIENEN IGUAL)
        // ============================================
        
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                // Para móvil
                sidebar.classList.toggle('open');
            } else {
                // Para desktop (toggle mini)
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                
                // Guardar preferencia
                guardarPreferencia('sidebar_mini', sidebarMini);
            }
        }
        
        // Funciones del panel de temas
        function abrirPanelTemas() {
            document.getElementById('themePanel').classList.add('open');
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            document.getElementById('themeOverlay').classList.add('open');
            themePanelOpen = true;
        }
        
        function cerrarPanelTemas() {
            document.getElementById('themePanel').classList.remove('open');
            document.getElementById('themeOverlay').classList.remove('open');
            themePanelOpen = false;
        }
        
        // Cambiar tema
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
            });
        });
        
        // Cambiar color de acento
        document.querySelectorAll('.win-color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-color-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const color = this.dataset.color;
                document.documentElement.style.setProperty('--win-accent', color);
                document.documentElement.style.setProperty('--win-accent-light', color + '20');
            });
        });
        
        // Guardar configuración
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar al servidor
            fetch('guardar_configuracion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tema_windows: tema,
                    color_accent: color,
                    sidebar_mini: sidebarMini
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Configuración guardada!',
                        text: 'Los cambios se han aplicado correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    Swal.fire('Error', 'No se pudo guardar la configuración', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Error de conexión', 'error');
            });
            
            cerrarPanelTemas();
        }
        
        // Guardar preferencia individual
        function guardarPreferencia(clave, valor) {
            fetch('guardar_preferencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `${clave}=${valor}`
            });
        }
// ============================================
// EXPORTAR CATEGORÍAS - TODOS LOS FORMATOS CON ICONOS
// ============================================
function exportarCategorias() {
    Swal.fire({
        title: '📊 Exportar Categorías',
        html: `
            <div style="text-align: left; margin-top: 15px;">
                <p style="margin-bottom: 15px; color: var(--win-text-primary);">
                    <i class="fas fa-download me-2"></i>Seleccione el formato de exportación:
                </p>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <button id="btnExcel" class="btn btn-success" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-file-excel me-2"></i> Microsoft Excel
                    </button>
                    <button id="btnPDF" class="btn btn-danger" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-file-pdf me-2"></i> Adobe PDF
                    </button>
                    <button id="btnWord" class="btn btn-primary" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-file-word me-2"></i> Microsoft Word
                    </button>
                    <button id="btnCSV" class="btn btn-info" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-file-csv me-2"></i> Archivo CSV
                    </button>
                    <button id="btnTXT" class="btn btn-secondary" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-file-alt me-2"></i> Archivo TXT
                    </button>
                    <button id="btnPrint" class="btn btn-warning" style="width: 100%; padding: 10px; border-radius: 6px;">
                        <i class="fas fa-print me-2"></i> Imprimir
                    </button>
                </div>
            </div>
        `,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Exportar Todo',
        confirmButtonColor: '#0078d4',
        showCloseButton: true,
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        },
        didOpen: () => {
            // Asignar eventos a los botones personalizados
            document.getElementById('btnExcel').addEventListener('click', () => {
                Swal.close();
                exportarExcel();
            });
            document.getElementById('btnPDF').addEventListener('click', () => {
                Swal.close();
                exportarPDF();
            });
            document.getElementById('btnWord').addEventListener('click', () => {
                Swal.close();
                exportarWord();
            });
            document.getElementById('btnCSV').addEventListener('click', () => {
                Swal.close();
                exportarCSV();
            });
            document.getElementById('btnTXT').addEventListener('click', () => {
                Swal.close();
                exportarTXT();
            });
            document.getElementById('btnPrint').addEventListener('click', () => {
                Swal.close();
                imprimirTabla();
            });
        },
        preConfirm: () => {
            // Exportar todos los formatos (opcional)
            exportarExcel();
            exportarPDF();
            exportarWord();
            exportarCSV();
            exportarTXT();
            setTimeout(() => imprimirTabla(), 1000);
        }
    });
}

        // Función para imprimir reporte (original)
        function imprimirReporte() {
            imprimirTabla();
        }
        
        // Actualizar lista de categorías
        function actualizarListaCategorias() {
            const btn = document.querySelector('button[onclick="actualizarListaCategorias()"]');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
                btn.disabled = true;
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Filtrar y buscar categorías
        function filtrarCategorias() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterStatus = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('#categoriasTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesStatus = filterStatus === '' || status === filterStatus;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            const counter = document.querySelector('.badge.bg-info');
            if (counter) {
                counter.textContent = visibleCount + ' categorías';
            }
        }
        
        // Cambiar registros por página
        function cambiarRegistrosPorPagina(registros) {
            const url = new URL(window.location.href);
            url.searchParams.set('registros', registros);
            url.searchParams.set('pagina', 1);
            window.location.href = url.toString();
        }
        
        // Ir a página específica
        function irAPagina() {
            const input = document.getElementById('gotoPage');
            let pagina = parseInt(input.value);
            const totalPaginas = parseInt('<?php echo $total_paginas; ?>');
            
            if (isNaN(pagina) || pagina < 1) {
                pagina = 1;
            } else if (pagina > totalPaginas) {
                pagina = totalPaginas;
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        
        // Ir a página específica desde el campo superior
        function irAPaginaDesdeTop() {
            const input = document.getElementById('gotoPageTop');
            let pagina = parseInt(input.value);
            const totalPaginas = parseInt('<?php echo $total_paginas; ?>');
            
            if (isNaN(pagina) || pagina < 1) {
                pagina = 1;
            } else if (pagina > totalPaginas) {
                pagina = totalPaginas;
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        
        // Toggle Quick Actions
        function toggleQuickActions() {
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (!expandedActions) return;
            
            const isExpanded = expandedActions.classList.contains('show');
            
            if (isExpanded) {
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            } else {
                expandedActions.classList.add('show');
                mainButton.innerHTML = '<i class="fas fa-times"></i>';
                mainButton.title = 'Ocultar acciones rápidas';
                mainButton.style.transform = 'rotate(45deg)';
            }
        }
        
        // Animar barras de progreso
        function animarBarrasProgreso() {
            const bars = document.querySelectorAll('.progress-bar-fill');
            bars.forEach(bar => {
                bar.style.width = '0%';
                setTimeout(() => {
                    const targetWidth = bar.style.getPropertyValue('--target-width') || '0%';
                    bar.style.width = targetWidth;
                }, 100);
            });
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof bootstrap === 'undefined') {
                console.error("Error: Bootstrap no está cargado.");
            } else {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        container: 'body',
                        trigger: 'hover focus',
                        placement: 'auto',
                        boundary: 'clippingParents',
                        html: true,
                        fallbackPlacements: ['bottom', 'right', 'left'],
                        delay: { "show": 100, "hide": 100 }
                    });
                });
            }
            
            document.getElementById('searchInput').addEventListener('input', filtrarCategorias);
            document.getElementById('filterStatus').addEventListener('change', filtrarCategorias);
            
            const gotoInput = document.getElementById('gotoPage');
            if (gotoInput) {
                gotoInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        irAPagina();
                    }
                });
            }
            
            const gotoInputTop = document.getElementById('gotoPageTop');
            if (gotoInputTop) {
                gotoInputTop.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        irAPaginaDesdeTop();
                    }
                });
            }
            
            const selectTop = document.getElementById('registrosPorPaginaTop');
            const selectBottom = document.getElementById('registrosPorPagina');
            
            if (selectTop && selectBottom) {
                selectTop.addEventListener('change', function() {
                    selectBottom.value = this.value;
                    cambiarRegistrosPorPagina(this.value);
                });
                
                selectBottom.addEventListener('change', function() {
                    selectTop.value = this.value;
                    cambiarRegistrosPorPagina(this.value);
                });
            }
            
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('open');
                }
            });
            
            document.addEventListener('click', function(event) {
                const quickActions = document.querySelector('.win-quick-actions');
                const expandedActions = document.getElementById('quickActionsExpanded');
                const mainButton = document.getElementById('mainQuickAction');
                
                if (!quickActions || !expandedActions) return;
                
                const isClickInside = quickActions.contains(event.target);
                const isExpanded = expandedActions.classList.contains('show');
                
                if (!isClickInside && isExpanded) {
                    expandedActions.classList.remove('show');
                    if (mainButton) {
                        mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                        mainButton.style.transform = 'rotate(0deg)';
                    }
                }
            });
            
            setTimeout(() => {
                animarBarrasProgreso();
            }, 500);
            
// SweetAlert sencillo pero informativo
<?php if (isset($_SESSION['swal_no_privilegios'])): 
    $swal = $_SESSION['swal_no_privilegios'];
    unset($_SESSION['swal_no_privilegios']);
?>
Swal.fire({
    title: '⚠️ Acceso Denegado',
    html: `
        <div style="text-align: left;">
            <p><strong>❌ <?php echo $swal['mensaje']; ?></strong></p>
            <div style="background: #2d2d2d; padding: 10px; border-radius: 6px; margin-top: 10px;">
                <i class="fas fa-user-circle"></i> <strong>Tu cuenta:</strong> <?php echo $swal['tipo_usuario']; ?><br>
                <i class="fas fa-shield-alt"></i> <strong>Requiere:</strong> Administrador, Editor o Supervisor
            </div>
        </div>
    `,
    icon: 'error',
    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
    confirmButtonColor: '#dc3545',
    background: '#1f1f1f',
    color: '#ffffff',
    showClass: { popup: '' },
    hideClass: { popup: '' }
});
<?php endif; ?>
        });

// Función para eliminar categoría
function eliminarCategoria(id, nombre) {
    const rolUsuario = <?php echo $usuario['rol_id'] ?? 0; ?>;
    const tienePermiso = (rolUsuario === 1 || rolUsuario === 3 || rolUsuario === 4);
    
    if (!tienePermiso) {
        Swal.fire({
            title: '⛔ Acceso Denegado',
            text: 'No tienes permisos para eliminar categorías',
            icon: 'error',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#dc3545',
            background: '#1f1f1f',
            color: '#ffffff',
            showClass: { popup: '' },
            hideClass: { popup: '' }
        });
        return;
    }
    
    // Confirmar eliminación
    Swal.fire({
        title: '⚠️ Confirmar eliminación',
        html: `
            <div style="text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ffc107; margin-bottom: 15px;"></i>
                <p><strong>${nombre}</strong></p>
                <p>¿Estás seguro de que deseas eliminar esta categoría?</p>
                <hr style="margin: 15px 0; border-color: #3d3d3d;">
                <div style="background: #2d2d2d; padding: 10px; border-radius: 6px;">
                    <i class="fas fa-info-circle"></i> Esta acción no se puede deshacer
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        background: '#1f1f1f',
        color: '#ffffff',
        showClass: { popup: '' },
        hideClass: { popup: '' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `eliminar_categoria.php?id=${id}`;
        }
    });
}
    </script>
	<?php
		if (file_exists('config/footer.php')) {
			include 'config/footer.php';
		}
	?>
</body>
</html>