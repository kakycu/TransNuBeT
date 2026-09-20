<?php
// historial.php - Historial del usuario actual con paginación
require_once 'config/header.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener configuración del tema Windows 11 (usar misma configuración que dashboard)
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Configuración de paginación - CORREGIDO con validación completa
$opciones_registros = [10, 20, 50, 100, 200];
$registros_por_pagina = 20; // Valor por defecto

if (isset($_GET['registros_por_pagina']) && is_numeric($_GET['registros_por_pagina'])) {
    $registros_por_pagina = (int)$_GET['registros_por_pagina'];
} elseif (isset($_SESSION['registros_por_pagina']) && is_numeric($_SESSION['registros_por_pagina'])) {
    $registros_por_pagina = (int)$_SESSION['registros_por_pagina'];
}

// Asegurar que esté dentro de las opciones permitidas
if (!in_array($registros_por_pagina, $opciones_registros)) {
    $registros_por_pagina = 20;
}

$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Guardar preferencia de registros por página en sesión
$_SESSION['registros_por_pagina'] = $registros_por_pagina;

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

// Variables de inicialización
$usuario = [];
$historial = [];
$estadisticas_operaciones = [];
$total_registros = 0;
$total_paginas = 1;

// Filtros por defecto
$filtro_operacion = $_GET['operacion'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Variables de estadísticas para sidebar
$cant_categ = 0;
$cant_Users = 0;
$cant_UsersAct = 0;
$clientes_todos = 0;
$servicios_count = 0;
$total_facturas = 0;

$avatar_placeholder = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));
$mensaje = '';
$tipo_mensaje = '';

// --- LÓGICA DE ORDENAMIENTO ---
$columnas_permitidas = ['fecha_hora', 'operacion', 'descripcion', 'ip_address'];
$columna_orden = $_GET['columna'] ?? 'fecha_hora';
$direccion_orden = $_GET['orden'] ?? 'DESC';

// Validación de seguridad (Whitelist)
if (!in_array($columna_orden, $columnas_permitidas)) {
    $columna_orden = 'fecha_hora';
}
// Validación de dirección
$direccion_orden = strtoupper($direccion_orden) === 'ASC' ? 'ASC' : 'DESC';

try {
    $db = Database::getConnection();
    
    // Obtener información del usuario actual
    $sql_usuario = "SELECT 
                    u.*, 
                    r.descripcion as rol_nombre,
                    r.codigo as rol_codigo,
                    u.foto as foto_perfil 
                FROM clasif_usuarios u
                LEFT JOIN clasif_rol r ON u.rol_id = r.id
                WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    // Obtener estadísticas para sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cant_users_activos = "SELECT COUNT(*) as total FROM clasif_usuarios WHERE activo = 1";
    $stmt = $db->prepare($sql_cant_users_activos);
    $stmt->execute();
    $cant_UsersAct = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cant_users = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_cant_users);
    $stmt->execute();
    $cant_Users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_todos = "SELECT COUNT(*) as totalc FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_todos);
    $stmt->execute();
    $clientes_todos = $stmt->fetch(PDO::FETCH_ASSOC)['totalc'];
    
    $sql_cant_categ = "SELECT COUNT(*) as totalcat FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_cant_categ);
    $stmt->execute();
    $cant_categ = $stmt->fetch(PDO::FETCH_ASSOC)['totalcat'];

    $sql_servicios_count = "SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 1";
    $stmt = $db->prepare($sql_servicios_count);
    $stmt->execute();
    $servicios_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Construir consulta base para el historial del usuario actual
    $sql_base = "FROM historico_operaciones WHERE usuario_id = :usuario_id";
    $parametros = ['usuario_id' => $_SESSION['usuario_id']];
    
    // Aplicar filtros
    if (!empty($filtro_operacion)) {
        $sql_base .= " AND operacion LIKE :operacion";
        $parametros['operacion'] = '%' . $filtro_operacion . '%';
    }
    
    if (!empty($filtro_fecha_desde)) {
        $sql_base .= " AND DATE(fecha_hora) >= :fecha_desde";
        $parametros['fecha_desde'] = $filtro_fecha_desde;
    }
    
    if (!empty($filtro_fecha_hasta)) {
        $sql_base .= " AND DATE(fecha_hora) <= :fecha_hasta";
        $parametros['fecha_hasta'] = $filtro_fecha_hasta;
    }
	
    // Obtener estadísticas para el histórico
    try {
        $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total_historico);
        $estadisticas_historico = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_historico = $estadisticas_historico['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $total_historico = 0;
    }
    
    // Obtener total de registros
    $sql_total = "SELECT COUNT(*) as total " . $sql_base;
    $stmt = $db->prepare($sql_total);
    $stmt->execute($parametros);
    $total_registros = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total']; // Forzar a entero
    
    // Calcular total de páginas - CORREGIDO: forzar tipos a entero
    $total_paginas = ($registros_por_pagina > 0) ? ceil($total_registros / $registros_por_pagina) : 1;
    
    // Asegurar que la página actual sea válida
    $pagina_actual = max(1, min($pagina_actual, $total_paginas));
    
    // Calcular offset para paginación
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    // Construir ordenamiento
    $sql_order_by = $columna_orden . " " . $direccion_orden;
    
    // Obtener registros paginados
    $sql_historial = "SELECT * " . $sql_base . " 
                      ORDER BY $sql_order_by
                      LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql_historial);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    
    // Agregar parámetros adicionales si existen
    foreach ($parametros as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas por tipo de operación para el usuario actual
    $sql_estadisticas = "SELECT 
                        operacion, 
                        COUNT(*) as cantidad,
                        MIN(fecha_hora) as primera_fecha,
                        MAX(fecha_hora) as ultima_fecha
                    FROM historico_operaciones 
                    WHERE usuario_id = :usuario_id 
                    GROUP BY operacion 
                    ORDER BY cantidad DESC";
    $stmt_estadisticas = $db->prepare($sql_estadisticas);
    $stmt_estadisticas->execute(['usuario_id' => $_SESSION['usuario_id']]);
    $estadisticas_operaciones = $stmt_estadisticas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error al cargar historial: " . $e->getMessage());
    $mensaje = "Error al cargar el historial. Por favor, intente nuevamente.";
    $tipo_mensaje = 'danger';
}

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

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

// --- FUNCIÓN PARA GENERAR ENCABEZADOS ORDENABLES ---
function crearEncabezadoOrdenable($texto, $columna_clave, $col_actual, $dir_actual) {
    // Si es la columna actual, invertimos la dirección para el próximo click
    $nueva_dir = ($columna_clave === $col_actual && $dir_actual === 'DESC') ? 'ASC' : 'DESC';
    
    // Mantenemos los filtros actuales en la URL
    $params = $_GET;
    $params['columna'] = $columna_clave;
    $params['orden'] = $nueva_dir;
    // Reiniciamos a página 1 al ordenar para evitar offsets inválidos
    $params['pagina'] = 1; 
    
    $url = '?' . http_build_query($params);
    
    // Icono de ordenamiento
    $icono = '<i class="fas fa-sort text-muted ms-1" style="opacity: 0.3; font-size: 0.8em;"></i>';
    if ($columna_clave === $col_actual) {
        if ($dir_actual === 'ASC') {
            $icono = '<i class="fas fa-sort-up ms-1" style="color: var(--win-accent);"></i>';
        } else {
            $icono = '<i class="fas fa-sort-down ms-1" style="color: var(--win-accent);"></i>';
        }
    }
    
    return '<a href="'.$url.'" class="text-decoration-none d-flex align-items-center justify-content-between" style="color: inherit;">' 
           . $texto . $icono . '</a>';
}

// --- FUNCIÓN PARA OBTENER ICONO SEGÚN OPERACIÓN ---
function obtenerIconoTipo($operacion) {
    $iconos = [
        'LOGIN' => ['icono' => 'fa-sign-in-alt', 'color' => 'success'],
        'LOGOUT' => ['icono' => 'fa-sign-out-alt', 'color' => 'warning'],
        'PERFIL' => ['icono' => 'fa-user-edit', 'color' => 'primary'],
        'FACTURA' => ['icono' => 'fa-file-invoice', 'color' => 'info'],
        'CLIENTE' => ['icono' => 'fa-user-plus', 'color' => 'info'],
        'SERVICIO' => ['icono' => 'fa-list', 'color' => 'warning'],
        'CONFIG' => ['icono' => 'fa-cog', 'color' => 'secondary'],
        'PASSWORD' => ['icono' => 'fa-key', 'color' => 'danger']
    ];
    
    $operacion_upper = strtoupper($operacion);
    foreach ($iconos as $key => $icono) {
        if (strpos($operacion_upper, $key) !== false) {
            return $icono;
        }
    }
    
    return ['icono' => 'fa-info-circle', 'color' => 'secondary'];
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
    <!-- Windows 11 Styles (mismos estilos que historico_view.php) -->
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
            border-bottom: 1px solid var(--win-border-color);
            padding: 1rem 1.25rem;
        }

        .win-main-content .card-body {
            padding: 1.25rem;
            color: var(--win-text-primary);
        }

        .win-main-content .form-control,
        .win-main-content .form-select,
        .win-main-content .form-check-input {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }

        .win-main-content .form-control:focus,
        .win-main-content .form-select:focus,
        .win-main-content .form-check-input:focus {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-accent);
            color: var(--win-text-primary);
            box-shadow: 0 0 0 0.25rem var(--win-accent-light);
        }

        .win-main-content .form-label {
            color: var(--win-text-primary);
            font-weight: 500;
        }

        .win-main-content .form-check-label {
            color: var(--win-text-primary);
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

        .win-main-content .btn-outline-secondary {
            color: var(--win-text-secondary);
            border-color: var(--win-border-color);
        }

        .win-main-content .btn-outline-secondary:hover {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
        }

        .win-main-content .border-bottom {
            border-bottom-color: var(--win-border-color) !important;
        }

        .win-main-content .text-muted {
            color: var(--win-text-secondary) !important;
        }

        /* Tablas */
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
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }

        .win-main-content .table-hover tbody tr:hover {
            background: var(--win-bg-tertiary);
        }

        /* Estilos para paginación mejorada */
        .pagination-card {
            border: none;
            border-radius: var(--win-radius);
            background: var(--win-bg-secondary);
            margin-bottom: 1.5rem;
        }

        .pagination-card .card-body {
            padding: 1rem 1.25rem;
        }

        .registros-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .registros-selector label {
            margin-bottom: 0;
            color: var(--win-text-secondary);
            font-size: 0.9rem;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            color: var(--win-text-secondary);
            font-size: 0.9rem;
        }

        .pagination-controls {
            display: flex;
            gap: 5px;
        }

        .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            transition: var(--win-transition);
        }

        .page-link:hover {
            background-color: var(--win-accent-light);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .page-item.active .page-link {
            background-color: var(--win-accent);
            border-color: var(--win-accent);
            color: white;
        }

        .page-item.disabled .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-secondary);
        }

        .registros-selector select {
            width: 70px;
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 4px 8px;
        }

        .jump-to-page {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .jump-to-page input {
            width: 60px;
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 4px 8px;
            text-align: center;
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

        /* Dropdown de usuario */
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

        /* Quick Actions */
        .win-quick-actions {
            position: fixed;
            bottom: 80px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
            align-items: flex-end;
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
            visibility: hidden;
            transform: translateY(20px) scale(0.8);
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1002;
        }

        .win-quick-actions-expanded.show {
            opacity: 1;
            visibility: visible;
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
            pointer-events: all;
            cursor: pointer;
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
                bottom: 70px;
                right: 16px;
            }
            
            .pagination-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .pagination-info {
                text-align: center;
            }
            
            .pagination-controls {
                justify-content: center;
            }
            
            .registros-selector {
                justify-content: center;
            }
            
            .jump-to-page {
                justify-content: center;
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
        }

        /* Para el tema oscuro */
        .text-muted, 
        .win-main-content .text-muted,
        .form-text,
        small.text-muted {
            color: #e0e0e0 !important;
            opacity: 0.9;
        }

        /* Para el tema claro */
        [data-theme="light"] .text-muted,
        [data-theme="light"] .win-main-content .text-muted,
        [data-theme="light"] .form-text,
        [data-theme="light"] small.text-muted {
            color: #555555 !important;
            opacity: 1;
        }

        /* Ajuste específico para los placeholders */
        ::placeholder {
            color: #cccccc !important;
            opacity: 0.7 !important;
        }

        [data-theme="light"] ::placeholder {
            color: #666666 !important;
        }

        /* Estadísticas cards */
        .stats-card {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            border: 1px solid var(--win-border-color);
            transition: var(--win-transition);
        }

        .stats-card:hover {
            border-color: var(--win-accent);
            transform: translateY(-2px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--win-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 1rem;
            background: var(--win-accent-light);
            color: var(--win-accent);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--win-accent);
            line-height: 1;
        }

        .stats-label {
            color: var(--win-text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
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
        
        <div class="win-theme-options">
            <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" 
                 data-theme="dark">
                <i class="fas fa-moon mb-2"></i>
                <div>Oscuro</div>
            </div>
            <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" 
                 data-theme="light">
                <i class="fas fa-sun mb-2"></i>
                <div>Claro</div>
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
            <i class="fas fa-user-clock"></i>
            <span style="color: var(--win-text-primary);">MI HISTORIAL - SISFACT PDL Visiones</span>
        </div>
        
        <!-- Buscador -->
        <div class="win-nav-search d-none d-md-block">
            <input type="text" placeholder="Buscar en mi historial...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
        <!-- Notificaciones -->
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
                        <?php echo $avatar_placeholder; ?>
                    </div>
                    <div class="win-sidebar-user-info">
                        <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></h6>
                        <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Usuario'); ?></small>
                    </div>
                </div>
            </div>
        </a>
        <ul class="win-nav">
            <li class="win-nav-item">
                <a href="dashboard.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tachometer-alt"></i>
                    <span class="win-nav-text">Dashboard</span>
                    <span class="win-nav-badge" 
                          title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>" 
                          style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
                        F/Cierre: <?php echo date('t'); ?> / <?php echo substr($mes_actual_es, 0, 3); ?>
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
                    <span class="win-nav-badge"><?php echo $clientes_todos; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-layer-group"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $cant_categ; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $servicios_count; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $cant_Users; ?></span>
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
					<span class="win-nav-badge"><?php echo $total_historico; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="historial.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-user-clock"></i>
                    <span class="win-nav-text">Mi Historial</span>
                    <span class="win-nav-badge"><?php echo $total_registros; ?></span>
                </a>
            </li>
        </ul>
        
        
<?php
// Obtener datos globales de facturación (CUP)
$finanzas = Database::getProgresoFinanciero();
?>

<div class="mt-4 px-3">
    <!-- Título y Estado -->
    <div class="d-flex justify-content-between align-items-end mb-1">
        <div>
            <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small>
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
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="fas <?php echo $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div class="d-flex align-items-center">
                <!-- Foto del usuario -->
                <div class="me-3">
                    <?php 
                    // Verificar si el campo 'foto' existe y contiene datos base64
                    $foto_usuario = $usuario['foto'] ?? '';
                    
                    if (!empty($foto_usuario) && (strpos($foto_usuario, 'data:image/') === 0 || strpos($foto_usuario, 'base64') !== false)): 
                        // Si la foto está en formato base64
                    ?>
                        <img src="<?php echo htmlspecialchars($foto_usuario); ?>" 
                             alt="Foto de perfil" 
                             class="rounded-circle" 
                             style="width: 60px; height: 60px; object-fit: cover; border: 2px solid var(--win-accent);">
                    <?php elseif (!empty($foto_usuario) && file_exists($foto_usuario)): 
                        // Si la foto es una ruta de archivo y el archivo existe
                    ?>
                        <img src="<?php echo htmlspecialchars($foto_usuario); ?>" 
                             alt="Foto de perfil" 
                             class="rounded-circle" 
                             style="width: 60px; height: 60px; object-fit: cover; border: 2px solid var(--win-accent);">
                    <?php else: ?>
                        <!-- Mostrar avatar con inicial si no hay foto -->
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--win-accent), #4a6fa5); color: white; font-size: 24px; font-weight: bold; border: 2px solid var(--win-accent); box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            <?php 
                            // Mostrar la primera letra del nombre
                            $nombre_completo = ($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? '');
                            $iniciales = '';
                            
                            // Obtener iniciales del nombre y apellido
                            if (!empty($usuario['nombre'])) {
                                $iniciales .= strtoupper(substr($usuario['nombre'], 0, 1));
                            }
                            if (!empty($usuario['apellidos'])) {
                                $apellidos_array = explode(' ', $usuario['apellidos']);
                                if (!empty($apellidos_array[0])) {
                                    $iniciales .= strtoupper(substr($apellidos_array[0], 0, 1));
                                }
                            }
                            
                            echo !empty($iniciales) ? $iniciales : 'U';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h1 class="h2 mb-1" style="color: var(--win-text-primary);">
                        <i class="fas fa-user-clock me-2" style="color: var(--win-accent);"></i>Mi Historial de Actividades
                    </h1>
                    <p class="text-muted mb-0">
                        Registro completo de mis actividades en el sistema <?php echo $mes_actual_es . ' ' . date('Y'); ?>
                        <span class="ms-2 badge" style="background-color: var(--win-accent);">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? '')); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left me-1"></i>Volver
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                    
                    <!-- Dropdown de exportación -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-file-export me-1"></i>Exportar
                        </button>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Exportar mi historial</h6></li>
                            <li><a class="dropdown-item" href="#" onclick="exportarHistorial('excel')">
                                <i class="fas fa-file-excel text-success"></i> Excel (.xls)
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportarHistorial('pdf')">
                                <i class="fas fa-file-pdf text-danger"></i> PDF (.pdf)
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportarHistorial('csv')">
                                <i class="fas fa-file-csv text-info"></i> CSV (.csv)
                            </a></li>
                        </ul>
                    </div>
                    
                    <?php if (!empty($filtro_operacion) || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                    <a href="historial.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Limpiar Filtros
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div> 
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Total Actividades</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($total_registros); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-history fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($estadisticas_operaciones)): 
                $operaciones_principales = array_slice($estadisticas_operaciones, 0, 3);
                foreach ($operaciones_principales as $index => $estadistica):
                    $icono = obtenerIconoTipo($estadistica['operacion']);
            ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: <?php echo 0.2 + ($index * 0.1); ?>s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">
                                    <?php echo htmlspecialchars(substr($estadistica['operacion'], 0, 20)); ?>
                                </div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($estadistica['cantidad']); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas <?php echo $icono['icono']; ?> fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Panel de información del usuario CON FOTO GRANDE -->
        <div class="card mb-4 animate__animated animate__fadeIn">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-user me-2"></i>Mi Información
                </h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center mb-3 mb-md-0">
                        <!-- Foto del usuario MUY GRANDE -->
                        <?php 
                        $foto_usuario = $usuario['foto'] ?? '';
                        
                        if (!empty($foto_usuario)): 
                            // Mostrar foto si existe (base64 o ruta)
                        ?>
                            <img src="<?php echo htmlspecialchars($foto_usuario); ?>" 
                                 alt="Foto de perfil" 
                                 class="rounded-circle shadow-lg" 
                                 style="width: 120px; height: 120px; object-fit: cover; border: 4px solid var(--win-accent);">
                        <?php else: ?>
                            <!-- Mostrar avatar con inicial si no hay foto -->
                            <div class="rounded-circle d-flex align-items-center justify-content-center shadow-lg mx-auto" 
                                 style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #000000)); color: white; font-size: 48px; font-weight: bold; border: 4px solid var(--win-accent);">
                                <?php 
                                // Obtener iniciales del nombre y apellido
                                $iniciales = '';
                                if (!empty($usuario['nombre'])) {
                                    $iniciales .= strtoupper(substr($usuario['nombre'], 0, 1));
                                }
                                if (!empty($usuario['apellidos'])) {
                                    $apellidos_array = explode(' ', $usuario['apellidos']);
                                    if (!empty($apellidos_array[0])) {
                                        $iniciales .= strtoupper(substr($apellidos_array[0], 0, 1));
                                    }
                                }
                                echo !empty($iniciales) ? $iniciales : 'U';
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Nombre debajo de la foto -->
                        <div class="mt-3">
                            <h5 class="mb-1" style="color: var(--win-text-primary); font-weight: 600;">
                                <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>
                            </h5>
                            <p class="text-muted mb-0">
                                <span class="badge <?php echo $usuario['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                                <span class="ms-2 badge bg-primary">
                                    <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Usuario</label>
                                    <p class="mb-2">
                                        <i class="fas fa-user-circle me-2" style="color: var(--win-accent);"></i>
                                        <strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong>
                                    </p>
                                </div>
                                
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Rol</label>
                                    <p class="mb-2">
                                        <i class="fas fa-user-tag me-2" style="color: var(--win-accent);"></i>
                                        <strong><?php echo htmlspecialchars($usuario['rol_nombre']); ?></strong>
                                        <small class="text-muted ms-2">(<?php echo htmlspecialchars($usuario['rol_codigo'] ?? ''); ?>)</small>
                                    </p>
                                </div>
                                
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Estado</label>
                                    <p class="mb-2">
                                        <i class="fas fa-circle me-2" style="color: <?php echo $usuario['activo'] ? '#28a745' : '#dc3545'; ?>;"></i>
                                        <span class="badge <?php echo $usuario['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Fecha Registro</label>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-plus me-2" style="color: var(--win-accent);"></i>
                                        <strong><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></strong>
                                    </p>
                                </div>
                                
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Última Actualización</label>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-check me-2" style="color: var(--win-accent);"></i>
                                        <strong>
                                            <?php echo $usuario['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($usuario['fecha_actualizacion'])) : 'Nunca'; ?>
                                        </strong>
                                    </p>
                                </div>
                                
                                <div class="info-item">
                                    <label class="text-muted small mb-1">Última Actividad</label>
                                    <p class="mb-0">
                                        <i class="fas fa-history me-2" style="color: var(--win-accent);"></i>
                                        <strong>
                                            <?php if (!empty($historial)): 
                                                echo date('d/m/Y H:i:s', strtotime($historial[0]['fecha_hora']));
                                            else: 
                                                echo 'No hay actividades';
                                            endif; ?>
                                        </strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <a href="perfil.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit me-1"></i>Editar Perfil
                                    </a>
                                    <a href="configuracion.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-cog me-1"></i>Configuración
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de filtros -->
        <div class="card mb-4 animate__animated animate__fadeIn">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-filter me-2"></i>Filtrar Mi Historial
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="operacion" class="form-label">Tipo de Operación</label>
                        <input type="text" class="form-control" id="operacion" name="operacion" 
                               placeholder="Ej: LOGIN, FACTURA, CLIENTE..."
                               value="<?php echo htmlspecialchars($filtro_operacion); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="fecha_desde" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" 
                               name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="fecha_hasta" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" 
                               name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Mostrando <?php echo count($historial); ?> de <?php echo $total_registros; ?> actividades
                                </span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <a href="historial.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-redo me-1"></i>Restablecer
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de historial -->
        <div class="card animate__animated animate__fadeIn">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                        <i class="fas fa-list me-2"></i>Mis Actividades
                    </h6>
                </div>
                <span class="badge" style="background-color: var(--win-accent);">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </span>
            </div>
            
            <?php if (empty($historial)): ?>
                <div class="card-body text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay actividades registradas</h5>
                    <p class="text-muted">Todavía no has realizado ninguna acción en el sistema</p>
                    <a href="dashboard.php" class="btn btn-primary mt-2">
                        <i class="fas fa-play-circle me-1"></i>Comenzar a usar el sistema
                    </a>
                </div>
            <?php else: ?>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr style="background-color: var(--win-bg-tertiary); color: var(--win-text-primary);">
                                    <th width="50">#</th>
                                    <th width="120">
                                        <?php echo crearEncabezadoOrdenable('Fecha/Hora', 'fecha_hora', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="150">
                                        <?php echo crearEncabezadoOrdenable('Operación', 'operacion', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th>
                                        <?php echo crearEncabezadoOrdenable('Descripción', 'descripcion', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="150">
                                        <?php echo crearEncabezadoOrdenable('IP Address', 'ip_address', $columna_orden, $direccion_orden); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $contador = $offset + 1;
                                foreach ($historial as $registro): 
                                    $icono = obtenerIconoTipo($registro['operacion']);
                                ?>
                                    <tr style="color: var(--win-text-primary);" class="animate__animated animate__fadeIn">
                                        <td class="text-muted"><?php echo $contador++; ?></td>
                                        <td>
                                            <small class="text-muted d-block">
                                                <?php echo date('d/m/Y', strtotime($registro['fecha_hora'])); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo date('H:i:s', strtotime($registro['fecha_hora'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="fas <?php echo $icono['icono']; ?>" 
                                                       style="color: var(--win-accent);"></i>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?php echo $icono['color']; ?>">
                                                        <?php echo htmlspecialchars($registro['operacion']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($registro['descripcion'])): ?>
                                                <span class="d-inline-block text-truncate" style="max-width: 300px;" 
                                                      title="<?php echo htmlspecialchars($registro['descripcion']); ?>">
                                                    <?php echo htmlspecialchars($registro['descripcion']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Sin descripción</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($registro['ip_address'])): ?>
                                                <code class="text-muted"><?php echo htmlspecialchars($registro['ip_address']); ?></code>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Card de Paginación Mejorada -->
        <?php if ($total_paginas > 1 && !empty($historial)): ?>
        <div class="card pagination-card animate__animated animate__fadeIn" style="animation-delay: 0.5s;">
            <div class="card-body">
                <div class="pagination-container">
                    <!-- Selector de registros por página -->
                    <div class="registros-selector">
                        <label for="registrosPorPagina">Registros por página:</label>
                        <select id="registrosPorPagina" class="form-select form-select-sm">
                            <?php foreach ($opciones_registros as $opcion): ?>
                                <option value="<?php echo $opcion; ?>" 
                                        <?php echo $registros_por_pagina == $opcion ? 'selected' : ''; ?>>
                                    <?php echo $opcion; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Información de paginación -->
                    <div class="pagination-info">
                        Mostrando 
                        <strong><?php echo $total_registros > 0 ? min($total_registros, ($offset + 1)) : 0; ?> - <?php echo $total_registros > 0 ? min($offset + $registros_por_pagina, $total_registros) : 0; ?></strong> 
                        de <strong><?php echo $total_registros; ?></strong> actividades
                        (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                    </div>
                    
                    <!-- Controles de paginación -->
                    <nav aria-label="Paginación del historial">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Botón Primera Página -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>"
                                   aria-label="Primera">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Anterior -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>"
                                   aria-label="Anterior">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <!-- Páginas -->
                            <?php
                            // Mostrar máximo 5 páginas alrededor de la actual
                            $pagina_inicio = max(1, $pagina_actual - 2);
                            $pagina_fin = min($total_paginas, $pagina_actual + 2);
                            
                            // Si estamos cerca del inicio, mostrar más páginas al final
                            if ($pagina_inicio == 1) {
                                $pagina_fin = min(5, $total_paginas);
                            }
                            
                            // Si estamos cerca del final, mostrar más páginas al inicio
                            if ($pagina_fin == $total_paginas) {
                                $pagina_inicio = max(1, $total_paginas - 4);
                            }
                            
                            // Mostrar primera página si no está en el rango
                            if ($pagina_inicio > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">1</a>
                                </li>
                                <?php if ($pagina_inicio > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif;
                            endif;
                            
                            // Mostrar páginas en el rango
                            for ($i = $pagina_inicio; $i <= $pagina_fin; $i++): ?>
                                <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;
                            
                            // Mostrar última página si no está en el rango
                            if ($pagina_fin < $total_paginas): ?>
                                <?php if ($pagina_fin < $total_paginas - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>">
                                        <?php echo $total_paginas; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>"
                                   aria-label="Siguiente">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Última Página -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>"
                                   aria-label="Última">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <!-- Ir a página específica -->
                    <div class="jump-to-page">
                        <label for="jumpToPage" class="mb-0">Ir a:</label>
                        <input type="number" id="jumpToPage" 
                               min="1" max="<?php echo $total_paginas; ?>" 
                               value="<?php echo $pagina_actual; ?>"
                               class="form-control form-control-sm">
                        <button class="btn btn-sm btn-outline-primary" onclick="jumpToPage()">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Quick Actions -->
    <div class="win-quick-actions">
        <!-- Botones expandidos -->
        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-factura" onclick="window.location.href='dashboard.php'" title="Ir al Dashboard">
                <i class="fas fa-tachometer-alt"></i>
            </button>
            <button class="win-quick-action action-cliente" onclick="window.location.href='perfil.php'" title="Mi Perfil">
                <i class="fas fa-user"></i>
            </button>
            <button class="win-quick-action action-servicio" onclick="window.location.href='perfil.php'" title="Regresar al Perfil">
                <i class="fas fa-history"></i>
            </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions(event)" title="Opciones" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let quickActionsOpen = false; 
        
        // Funciones del sidebar y UI
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('open');
            } else {
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                guardarPreferencia('sidebar_mini', sidebarMini);
            }
        }
        
        function abrirPanelTemas() {
            document.getElementById('themePanel').classList.add('open');
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
                customClass: {
                    popup: 'sweetalert-dark'
                },
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
                        showConfirmButton: false,
                        customClass: {
                            popup: 'sweetalert-dark'
                        }
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
                body: `${clave}=${valor}` 
            }).catch(e => {});
        }
        
        // Gestión de Quick Actions
        function toggleQuickActions(event) {
            if (event) { 
                event.stopPropagation(); 
                event.preventDefault(); 
            }
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            if (!expandedActions || !mainButton) return;
            
            quickActionsOpen = !quickActionsOpen;
            if (quickActionsOpen) {
                expandedActions.classList.add('show');
                mainButton.innerHTML = '<i class="fas fa-times"></i>';
                mainButton.title = 'Ocultar acciones rápidas';
                mainButton.style.transform = 'rotate(45deg)';
            } else {
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            }
        }
        
        // Cambio de registros por página
        document.getElementById('registrosPorPagina')?.addEventListener('change', function() {
            const registros = this.value;
            const params = new URLSearchParams(window.location.search);
            params.set('registros_por_pagina', registros);
            params.set('pagina', '1'); // Volver a página 1
            window.location.href = '?' + params.toString();
        });
        
        // Ir a página específica
        function jumpToPage() {
            const pageInput = document.getElementById('jumpToPage');
            const pagina = parseInt(pageInput.value);
            const totalPaginas = <?php echo $total_paginas; ?>;
            
            if (pagina >= 1 && pagina <= totalPaginas) {
                const params = new URLSearchParams(window.location.search);
                params.set('pagina', pagina);
                window.location.href = '?' + params.toString();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Página inválida',
                    text: `Ingresa un número entre 1 y ${totalPaginas}`,
                    timer: 2000,
                    showConfirmButton: false
                });
                pageInput.value = <?php echo $pagina_actual; ?>;
            }
        }
        
        // Exportar historial
        function exportarHistorial(formato) {
            const params = new URLSearchParams(window.location.search);
            params.append('exportar', formato);
            
            Swal.fire({
                title: 'Exportando historial...',
                text: 'Preparando archivo para descarga',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                window.location.href = 'exportar_mi_historial.php?' + params.toString();
                Swal.close();
            }, 1000);
        }
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar paneles al hacer clic fuera
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Configurar fecha máxima en filtros
            const hoy = new Date().toISOString().split('T')[0];
            const fechaHasta = document.getElementById('fecha_hasta');
            const fechaDesde = document.getElementById('fecha_desde');
            
            if (fechaHasta) fechaHasta.max = hoy;
            if (fechaDesde) fechaDesde.max = hoy;
            
            // Validar rango de fechas
            if (fechaDesde) {
                fechaDesde.addEventListener('change', function() {
                    if (fechaHasta && this.value && fechaHasta.value && this.value > fechaHasta.value) {
                        fechaHasta.value = this.value;
                    }
                    if (fechaHasta) fechaHasta.min = this.value;
                });
            }
            
            if (fechaHasta) {
                fechaHasta.addEventListener('change', function() {
                    if (fechaDesde && this.value && fechaDesde.value && this.value < fechaDesde.value) {
                        fechaDesde.value = this.value;
                    }
                });
            }
            
            // Auto cerrar alertas
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(a => {
                    const alert = new bootstrap.Alert(a);
                    alert.close();
                });
            }, 5000);
            
            // Cerrar Quick Actions al hacer clic fuera
            document.addEventListener('click', function(e) {
                const quickActions = document.querySelector('.win-quick-actions');
                const mainButton = document.getElementById('mainQuickAction');
                
                if (quickActionsOpen && quickActions && 
                    !quickActions.contains(e.target) && 
                    e.target !== mainButton) {
                    toggleQuickActions();
                }
            });
        });
    </script>
    
    <?php
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
    ?>
</body>
</html>