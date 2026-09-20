<?php
// clientes.php - Windows 11 Dark Mode
require_once 'config/header.php';
	
// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Mostrar SweetAlert si existe en sesión
if (isset($_SESSION['sweet_alert'])) {
    $sweet_alert = $_SESSION['sweet_alert'];
    unset($_SESSION['sweet_alert']); // Limpiar después de usar
}

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


// 1. INICIALIZACIÓN DE VARIABLES POR DEFECTO (Esto evita los Notices)
$offset = 0;
$total_registros = 0;
$total_paginas = 1;
$total_clientes_activos = 0;
$orden = 'nombre';
$direccion = 'ASC';
$clientes = []; // Array vacío por si la consulta falla
$total_contratos = 0;
$contratos_activos = 0;
$contratos_inactivos = 0;
$contratos_vencidos = 0;
$contratos_proximos = 0;
$contratos_vigentes = 0;
$contratos_renovados = 0;
$contratos_sin_fecha = 0;
$porcentaje_activos = 0;



// Configuración de paginación inicial
$registros_por_pagina = $_GET['registros_por_pagina'] ?? $_SESSION['registros_por_pagina'] ?? 10;
$pagina_actual = $_GET['pagina'] ?? 1;

// Guardar preferencia de registros por página en sesión
$_SESSION['registros_por_pagina'] = $registros_por_pagina;

// Opciones de registros por página
$opciones_registros = [5, 10, 20, 50, 100, 'todos'];

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
    
    // Determinar permisos según rol

    $esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);
    
	
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
    
    // Parámetros de filtro
    $filtro_ano = $_GET['ano'] ?? '';
    $filtro_status = $_GET['status'] ?? '';
    $filtro_contract_status = $_GET['contract_status'] ?? '';
    $search_term = $_GET['search'] ?? '';


    // Definir orden antes de usarlo
    $orden = $_GET['orden'] ?? 'nombre';
    $direccion = $_GET['direccion'] ?? 'ASC';
	
	
    // Construir consulta base con filtros
    $sql_where = "WHERE 1=1";
    $params = [];
    
    // Filtro por año
    if (!empty($filtro_ano)) {
        $sql_where .= " AND YEAR(c.fechaRegistro) = :ano";
        $params[':ano'] = $filtro_ano;
    }
    
    // Filtro por estado del cliente
    if ($filtro_status === 'activo') {
        $sql_where .= " AND c.activo = 1";
    } elseif ($filtro_status === 'inactivo') {
        $sql_where .= " AND c.activo = 0";
    }
    
    // Filtro por estado del contrato
    if (!empty($filtro_contract_status)) {
        if ($filtro_contract_status === 'sin_fecha') {
            $sql_where .= " AND (c.fechafinalcontrato IS NULL OR c.fechafinalcontrato = '0000-00-00')";
        } elseif ($filtro_contract_status === 'vencido') {
            $sql_where .= " AND c.fechafinalcontrato < CURDATE() AND c.fechafinalcontrato IS NOT NULL AND c.fechafinalcontrato != '0000-00-00'";
        } elseif ($filtro_contract_status === 'proximo') {
            $sql_where .= " AND c.fechafinalcontrato >= CURDATE() AND c.fechafinalcontrato <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND c.fechafinalcontrato IS NOT NULL AND c.fechafinalcontrato != '0000-00-00'";
        } elseif ($filtro_contract_status === 'vigente') {
            $sql_where .= " AND c.fechafinalcontrato >= CURDATE() AND c.fechafinalcontrato IS NOT NULL AND c.fechafinalcontrato != '0000-00-00'";
        }
    }
    
    // Búsqueda general
// Búsqueda general - CORREGIDA
    $search_term = trim($search_term); // Limpiar espacios al inicio y final
    if (!empty($search_term)) {
        // Usamos marcadores únicos (:s1, :s2, etc.) para evitar conflictos en PDO
        $sql_where .= " AND (
            c.nombre LIKE :s1 
            OR c.direccion LIKE :s2 
            OR c.codigo LIKE :s3 
            OR c.ContratoNo LIKE :s4 
            OR c.ResponsableEntidad LIKE :s5
        )";
        
        $term = "%$search_term%";
        $params[':s1'] = $term;
        $params[':s2'] = $term;
        $params[':s3'] = $term;
        $params[':s4'] = $term;
        $params[':s5'] = $term;
    }
    
    // Contar total de registros
    $sql_count = "SELECT COUNT(*) as total FROM clasif_clientes c $sql_where";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular paginación
    if ($registros_por_pagina == 'todos') {
        $total_paginas = 1;
        $offset = 0;
        $limit_clause = "";
} else {
    // Asegurar que registros_por_pagina sea al menos 1
    $registros_por_pagina = max(1, intval($registros_por_pagina));
    
    // Calcular total de páginas (nunca será 0)
    $total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
    
    // Asegurar que página actual esté dentro del rango
    $pagina_actual = max(1, min(intval($pagina_actual), $total_paginas));
    
    // Calcular offset
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    $limit_clause = "LIMIT :limit OFFSET :offset";
}   
    // Asegurar que la página actual esté dentro del rango válido
    $pagina_actual = max(1, min($pagina_actual, $total_paginas));
    
    // Calcular offset
    $offset = ($pagina_actual - 1) * ($registros_por_pagina == 'todos' ? $total_registros : $registros_por_pagina);

    $orden = $_GET['orden'] ?? 'nombre';
    $direccion = $_GET['direccion'] ?? 'ASC';

    // Validar campo de ordenamiento
    $campos_permitidos = [
        'codigo', 'nombre', 'ContratoNo', 'ResponsableEntidad', 
        'activo', 'fechaRegistro', 'vigenciapor', 'fechaVence', 
        'renovac', 'fechafinalcontrato', 'estado_contrato', 'dias_restantes'
    ];
    $orden = in_array($orden, $campos_permitidos) ? $orden : 'nombre';
    $direccion = strtoupper($direccion) === 'DESC' ? 'DESC' : 'ASC';

    // Obtener clientes con información completa (con paginación)
    $sql = "SELECT c.*, 
               COUNT(f.id) as total_facturas,
               COALESCE(SUM(f.total_general), 0) as total_compras,
               CASE 
                   WHEN c.fechafinalcontrato IS NULL OR c.fechafinalcontrato = '0000-00-00' THEN 'sin_fecha'
                   WHEN c.fechafinalcontrato < CURDATE() THEN 'vencido'
                   WHEN DATEDIFF(c.fechafinalcontrato, CURDATE()) <= 30 THEN 'proximo_vencer'
                   ELSE 'vigente'
               END as estado_contrato,
               DATEDIFF(c.fechafinalcontrato, CURDATE()) as dias_restantes
        FROM clasif_clientes c
        LEFT JOIN tbl_fact f ON c.id = f.cliente_id 
        AND f.estado != 'ANULADA' 
        $sql_where
        GROUP BY c.id
        ORDER BY $orden $direccion
        $limit_clause";
    
    $stmt = $db->prepare($sql);
    
    // Asignar parámetros de filtro
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Si no es "todos", agregar límites
    if ($registros_por_pagina != 'todos') {
        $stmt->bindValue(':limit', (int)$registros_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $clientes = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_clientes_activos = 0;
    $total_clientes_inactivos = 0;
    $total_compras_general = 0;
    
    foreach ($clientes as $cliente) {
        if ($cliente['activo'] == 1) {
            $total_clientes_activos++;
        } else {
            $total_clientes_inactivos++;
        }
        $total_compras_general += floatval($cliente['total_compras']);
    }
    
} catch (Exception $e) {
    error_log("Error al cargar clientes: " . $e->getMessage());
    $error = "Error al cargar los clientes";
}

// Obtener años únicos de contratos desde la base de datos
try {
    $sql_anos = "SELECT DISTINCT YEAR(fechaRegistro) as ano 
                 FROM clasif_clientes 
                 WHERE fechaRegistro IS NOT NULL 
                 AND fechaRegistro != '0000-00-00'
                 AND fechaRegistro != ''
                 ORDER BY ano DESC";
    $stmt_anos = $db->prepare($sql_anos);
    $stmt_anos->execute();
    $anos_contratos = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener años de contratos: " . $e->getMessage());
    $anos_contratos = [];
}
// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];
$estadisticas = [];

// Total registros para histórico
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// 1. OBTENER ESTADÍSTICAS DE CONTRATOS
$sql_contratos_total = "SELECT COUNT(*) as total FROM clasif_clientes";
$stmt = $db->prepare($sql_contratos_total);
$stmt->execute();
$total_contratos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos activos
$sql_contratos_activos = "SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 1";
$stmt = $db->prepare($sql_contratos_activos);
$stmt->execute();
$contratos_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos inactivos
$sql_contratos_inactivos = "SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 0";
$stmt = $db->prepare($sql_contratos_inactivos);
$stmt->execute();
$contratos_inactivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos vencidos
$hoy = date('Y-m-d');
$sql_contratos_vencidos = "SELECT COUNT(*) as total FROM clasif_clientes 
                           WHERE fechafinalcontrato < :hoy 
                           AND fechafinalcontrato IS NOT NULL 
                           AND fechafinalcontrato != '0000-00-00'";
$stmt = $db->prepare($sql_contratos_vencidos);
$stmt->execute(['hoy' => $hoy]);
$contratos_vencidos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos próximos a vencer (30 días)
$fecha_limite = date('Y-m-d', strtotime('+30 days'));
$sql_contratos_proximos = "SELECT COUNT(*) as total FROM clasif_clientes 
                           WHERE fechafinalcontrato >= :hoy 
                           AND fechafinalcontrato <= :fecha_limite 
                           AND fechafinalcontrato IS NOT NULL 
                           AND fechafinalcontrato != '0000-00-00'";
$stmt = $db->prepare($sql_contratos_proximos);
$stmt->execute(['hoy' => $hoy, 'fecha_limite' => $fecha_limite]);
$contratos_proximos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos vigentes (no vencidos)
$sql_contratos_vigentes = "SELECT COUNT(*) as total FROM clasif_clientes 
                           WHERE (fechafinalcontrato >= :hoy 
                           OR fechafinalcontrato IS NULL 
                           OR fechafinalcontrato = '0000-00-00') 
                           AND activo = 1";
$stmt = $db->prepare($sql_contratos_vigentes);
$stmt->execute(['hoy' => $hoy]);
$contratos_vigentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos con renovación
$sql_contratos_renovados = "SELECT COUNT(*) as total FROM clasif_clientes 
                            WHERE renovac = 1";
$stmt = $db->prepare($sql_contratos_renovados);
$stmt->execute();
$contratos_renovados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contratos sin fecha de finalización
$sql_contratos_sin_fecha = "SELECT COUNT(*) as total FROM clasif_clientes 
                            WHERE fechafinalcontrato IS NULL 
                            OR fechafinalcontrato = '0000-00-00'";
$stmt = $db->prepare($sql_contratos_sin_fecha);
$stmt->execute();
$contratos_sin_fecha = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Porcentaje de contratos activos
$porcentaje_activos = $total_contratos > 0 ? ($contratos_activos / $total_contratos) * 100 : 0;

function formatoTiempoLegible($dias) {
    if ($dias >= 365) {
        $anos = floor($dias / 365);
        $meses = floor(($dias % 365) / 30);
        if ($meses > 0) {
            return $anos . ' año' . ($anos != 1 ? 's' : '') . ($meses > 0 ? ', ' . $meses . ' mes' . ($meses != 1 ? 'es' : '') : '');
        }
        return $anos . ' año' . ($anos != 1 ? 's' : '');
    } elseif ($dias >= 30) {
        $meses = floor($dias / 30);
        $dias_restantes = $dias % 30;
        if ($dias_restantes > 0) {
            return $meses . ' mes' . ($meses != 1 ? 'es' : '') . ', ' . $dias_restantes . ' día' . ($dias_restantes != 1 ? 's' : '');
        }
        return $meses . ' mes' . ($meses != 1 ? 'es' : '');
    } else {
        return $dias . ' día' . ($dias != 1 ? 's' : '');
    }
}
// Función para determinar el color del badge según el estado
function obtenerClaseBadge($dias_restantes, $dias_vencido = 0) {
    if ($dias_restantes < 0) {
        // Contrato vencido
        if ($dias_vencido > 365) return 'bg-dark'; // Más de 1 año vencido
        if ($dias_vencido > 180) return 'bg-danger'; // 6 meses a 1 año
        if ($dias_vencido > 90) return 'bg-danger'; // 3 a 6 meses
        if ($dias_vencido > 30) return 'bg-warning text-dark'; // 1 a 3 meses
        return 'bg-warning text-dark'; // Menos de 1 mes
    } elseif ($dias_restantes <= 30) {
        // Próximo a vencer
        return 'bg-warning text-dark';
    } elseif ($dias_restantes <= 90) {
        // Menos de 3 meses
        return 'bg-info';
    } elseif ($dias_restantes <= 180) {
        // 3 a 6 meses
        return 'bg-primary';
    } else {
        // Más de 6 meses
        return 'bg-success';
    }
}

// Función para obtener el icono según el estado
function obtenerIcono($dias_restantes) {
    if ($dias_restantes < 0) {
        return 'fa-exclamation-triangle';
    } elseif ($dias_restantes <= 30) {
        return 'fa-exclamation-circle';
    } else {
        return 'fa-check-circle';
    }
}


$logo_path = 'assets/logov.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $mime_type = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
} else {
    // Logo por defecto si no existe
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" fill="#0078D4" rx="5"/>
            <text x="25" y="28" font-family="Arial" font-size="16" fill="white" text-anchor="middle" font-weight="bold">PDL</text>
            <text x="25" y="40" font-family="Arial" font-size="9" fill="white" text-anchor="middle">VISIONES</text>
        </svg>
    ');
}

// Función para construir URL con parámetros
function construirUrl($params = []) {
    $query_params = $_GET;
    foreach ($params as $key => $value) {
        $query_params[$key] = $value;
    }
    return '?' . http_build_query($query_params);
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - PDL Visiones</title>
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
    <!-- Windows 11 Styles -->
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
            border-bottom-color: var(--win-border-color) !important;
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

        /* Estilos para paginación */
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
            width: 90px;
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
        
        /* Badges para estado */
        .badge.activo {
            background-color: #28a745 !important;
            color: white !important;
        }
        
        .badge.inactivo {
            background-color: #dc3545 !important;
            color: white !important;
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
/* Estilos para encabezados ordenables */
.table th a {
    color: var(--win-text-primary) !important;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.table th a:hover {
    color: var(--win-accent) !important;
}

.table th a i {
    font-size: 12px;
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.table th a:hover i {
    opacity: 1;
}

/* Badge para indicador de orden actual */
.badge-orden-activo {
    background-color: var(--win-accent);
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 4px;
}
/* Estilos para impresión */
@media print {
    body * {
        visibility: hidden;
    }
    
    #printableTable, #printableTable * {
        visibility: visible;
    }
    
    #printableTable {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    
    .no-print {
        display: none !important;
    }
    
    .print-header {
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    
    .print-footer {
        text-align: center;
        margin-top: 20px;
        font-size: 10px;
        color: #666;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    th {
        background-color: #f0f0f0 !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        padding: 8px !important;
        font-weight: bold;
    }
    
    td {
        border: 1px solid #ddd !important;
        padding: 6px !important;
        color: #000 !important;
    }
    
    .badge {
        padding: 3px 8px !important;
        font-size: 10px !important;
        border-radius: 3px !important;
    }
}
/* Agrega esto en la sección de estilos */
.btn-group .dropdown-menu {
    background-color: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
}

.btn-group .dropdown-item {
    color: var(--win-text-primary);
    transition: var(--win-transition);
    border-radius: var(--win-radius-sm);
    margin: 2px 4px;
}

.btn-group .dropdown-item:hover {
    background-color: var(--win-accent-light);
    color: var(--win-accent);
}

.btn-group .dropdown-item i {
    width: 20px;
    text-align: center;
}

/* Mejora visual para el botón de exportación */
.btn-group .btn-success {
    background: linear-gradient(135deg, #28a745, #218838);
    border-color: #28a745;
}

.btn-group .btn-success:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    border-color: #1e7e34;
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
           <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">GESTIÓN DE CLIENTES - SISFACT PDL Visiones</span>
        </div>
        
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
            </li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-file-invoice"></i>
                    <span class="win-nav-text">Facturas</span>
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
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
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;"><?php echo $sidebar_mini ? '...' : 'Configuración'; ?></small></li>
            <li class="win-nav-item">
                <a href="planes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-money-bill-wave"></i>
                    <span class="win-nav-text">Plan de Ingresos</span>
					<span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
                </a>
            </li>            <li class="win-nav-item">
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
<?php if ($esSoloLectura): ?>
<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Modo Solo Lectura:</strong> Estás visualizando los datos en modo consulta. No puedes crear, editar o eliminar registros.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-users me-2" style="color: var(--win-accent);"></i>Gestión de Clientes
                </h1>
                <p class="text-muted mb-0">Administre y consulte la información de sus clientes<br> Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="actualizarListaClientes()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                    <a href="nuevo_cliente.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Nuevo Cliente
                    </a>
            <button type="button" class="btn btn-sm btn-success" onclick="imprimirTablaClientes()">
                <i class="fas fa-print me-1"></i>Imprimir
            </button>
<button type="button" class="btn btn-sm btn-success dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Exportar</span>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportarAPDF()">
                    <i class="fas fa-file-pdf text-danger me-2"></i>Exportar a PDF
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportarAExcel()">
                    <i class="fas fa-file-excel text-success me-2"></i>Exportar a Excel
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportarAWord()">
                    <i class="fas fa-file-word text-primary me-2"></i>Exportar a Word
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportarACSV()">
                    <i class="fas fa-file-csv text-info me-2"></i>Exportar a CSV
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportarATXT()">
                    <i class="fas fa-file-alt text-info me-2"></i>Exportar a TXT
                </a></li>
            </ul>
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
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Contratos</div>
                        <div class="h5 mb-0 fw-bold text-success"><?php echo $total_contratos; ?></div>
                        <div class="text-success small">
                            <i class="fas fa-file-contract me-1"></i>Contratos registrados
                        </div>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-file-signature fa-2x text-success opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Activos:</small>
                        <small class="fw-bold text-success"><?php echo $contratos_activos; ?></small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Inactivos:</small>
                        <small class="fw-bold text-danger"><?php echo $contratos_inactivos; ?></small>
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
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Estado Contratos</div>
                        <div class="h5 mb-0 fw-bold text-warning"><?php echo $contratos_vigentes; ?> Vigentes</div>
                        <div class="text-warning small">
                            <i class="fas fa-calendar-check me-1"></i>Contratos en vigor
                        </div>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-calendar-alt fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Vencidos:</small>
                        <small class="fw-bold text-danger"><?php echo $contratos_vencidos; ?></small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Próximos a vencer:</small>
                        <small class="fw-bold text-warning"><?php echo $contratos_proximos; ?></small>
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
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Renovaciones</div>
                        <div class="h5 mb-0 fw-bold text-info"><?php echo $contratos_renovados; ?></div>
                        <div class="text-info small">
                            <i class="fas fa-sync-alt me-1"></i>Contratos renovados
                        </div>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-redo fa-2x text-info opacity-50"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Sin fecha final:</small>
                        <small class="fw-bold text-secondary"><?php echo $contratos_sin_fecha; ?></small>
                    </div>
                    <?php if ($contratos_renovados > 0): ?>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Promedio renovación:</small>
                        <small class="fw-bold text-primary">
                            <?php 
                                $sql_avg_renovacion = "SELECT AVG(si_renova_cant) as promedio FROM clasif_clientes WHERE renovac = 1";
                                $stmt = $db->prepare($sql_avg_renovacion);
                                $stmt->execute();
                                $promedio_renovacion = $stmt->fetch(PDO::FETCH_ASSOC)['promedio'];
                                echo number_format($promedio_renovacion, 1) . ' años';
                            ?>
                        </small>
                    </div>
                    <?php endif; ?>
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
                            <?php echo number_format($porcentaje_activos, 1); ?>%
                        </div>
                        <div style="color: var(--win-accent);" class="small">
                            <i class="fas fa-chart-line me-1"></i>Contratos activos
                        </div>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-chart-pie fa-2x opacity-50" style="color: var(--win-accent);"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?php echo $porcentaje_activos; ?>%; background-color: var(--win-accent);" 
                             aria-valuenow="<?php echo $porcentaje_activos; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <?php echo $contratos_activos; ?> de <?php echo $total_contratos; ?> contratos activos
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

		
		
        <!-- Búsqueda y filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-search me-1"></i>Filtrado y Búsqueda de Clientes
                </h6>
            </div>
            <div class="card-body">
                <form id="filtrosForm" method="GET" action="">
                    <div class="row g-3">
                        <!-- Búsqueda general -->
                        <div class="col-md-3">
                            <div class="search-box">
                                <input type="text" class="form-control" id="searchInput" name="search"
                                       placeholder="Buscar en cualquier campo..." value="<?php echo htmlspecialchars($search_term); ?>">
                                <div class="search-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>Busca en nombre, dirección, contrato, etc.
                            </small>
                        </div>
                        
                        <!-- Filtro por año -->
                        <div class="col-md-2">
                            <select class="form-select" id="filterAno" name="ano">
                                <option value="" <?php echo empty($filtro_ano) ? 'selected' : ''; ?>>Todos los años</option>
                                <option value="<?php echo date('Y'); ?>" <?php echo $filtro_ano == date('Y') ? 'selected' : ''; ?>><?php echo date('Y'); ?> (Actual)</option>
                                <?php foreach ($anos_contratos as $ano): ?>
                                    <?php if (!empty($ano['ano']) && $ano['ano'] != date('Y')): ?>
                                        <option value="<?php echo $ano['ano']; ?>" <?php echo $filtro_ano == $ano['ano'] ? 'selected' : ''; ?>><?php echo $ano['ano']; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-calendar me-1"></i>Año de inicio del contrato
                            </small>
                        </div>
                        
                        <!-- Filtro por estado del cliente -->
                        <div class="col-md-2">
                            <select class="form-select" id="filterStatus" name="status">
                                <option value="" <?php echo empty($filtro_status) ? 'selected' : ''; ?>>Estado en Base datos</option>
                                <option value="activo" <?php echo $filtro_status == 'activo' ? 'selected' : ''; ?>>Solo activos</option>
                                <option value="inactivo" <?php echo $filtro_status == 'inactivo' ? 'selected' : ''; ?>>Solo inactivos</option>
                            </select>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-user-check me-1"></i>Estado general del cliente
                            </small>
                        </div>
                        
                        <!-- Filtro por estado del contrato -->
                        <div class="col-md-3">
                            <select class="form-select" id="filterContractStatus" name="contract_status">
                                <option value="" <?php echo empty($filtro_contract_status) ? 'selected' : ''; ?>>Estado Contractual</option>
                                <option value="vigente" <?php echo $filtro_contract_status == 'vigente' ? 'selected' : ''; ?>>Vigentes</option>
                                <option value="proximo" <?php echo $filtro_contract_status == 'proximo' ? 'selected' : ''; ?>>Próximos a vencer</option>
                                <option value="vencido" <?php echo $filtro_contract_status == 'vencido' ? 'selected' : ''; ?>>Vencidos</option>
                                <option value="sin_fecha" <?php echo $filtro_contract_status == 'sin_fecha' ? 'selected' : ''; ?>>Sin fecha</option>
                            </select>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-file-contract me-1"></i>Estado de vigencia del contrato
                            </small>
                        </div>
                        
                        <!-- Botones -->
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 mb-1">
                                <i class="fas fa-filter me-1"></i>Aplicar filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">
                                <i class="fas fa-times me-1"></i>Limpiar filtros
                            </button>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-broom me-1"></i>Restablece todos los filtros
                            </small>
                        </div>
                    </div>
                    
                    <!-- Campos ocultos para mantener otros parámetros -->
                    <input type="hidden" name="pagina" value="1">
                    <input type="hidden" name="orden" value="<?php echo htmlspecialchars($orden); ?>">
                    <input type="hidden" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>">
                    <input type="hidden" name="registros_por_pagina" value="<?php echo htmlspecialchars($registros_por_pagina); ?>">
                </form>
            </div>
        </div>

        <!-- Card de Paginación -->
        <div class="card pagination-card animate__animated animate__fadeIn" style="animation-delay: 0.5s;">
            <div class="card-body">
                <div class="pagination-container">
                    <!-- Selector de registros por página -->
                    <div class="registros-selector">
                        <label for="registrosPorPagina">Registros por página:</label>
                        <select id="registrosPorPagina" class="form-select form-select-sm" onchange="cambiarRegistrosPorPagina(this.value)">
                            <?php foreach ($opciones_registros as $opcion): ?>
                                <option value="<?php echo $opcion; ?>" <?php echo $registros_por_pagina == $opcion ? 'selected' : ''; ?>>
                                    <?php echo $opcion == 'todos' ? 'Mostrar Todos' : $opcion; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Información de paginación -->
                    <div class="pagination-info">
                        <?php if ($registros_por_pagina == 'todos'): ?>
                            Mostrando <strong><?php echo $total_registros; ?></strong> clientes (Todos)
                        <?php else: ?>
                            Mostrando 
                            <strong><?php echo ($offset + 1); ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?></strong> 
                            de <strong><?php echo $total_registros; ?></strong> clientes
                            (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                        <?php endif; ?>
                    </div>
                    
                    <!-- Controles de paginación -->
                    <?php if ($registros_por_pagina != 'todos' && $total_paginas > 1): ?>
                    <nav aria-label="Paginación de clientes">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Botón Inicio -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => 1]); ?>"
                                   aria-label="Inicio">
                                    <i class="fas fa-fast-backward"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Anterior -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $pagina_actual - 1]); ?>"
                                   aria-label="Anterior">
                                    <i class="fas fa-chevron-left"></i>
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => 1]); ?>">1</a>
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => $i]); ?>">
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => $total_paginas]); ?>">
                                        <?php echo $total_paginas; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $pagina_actual + 1]); ?>"
                                   aria-label="Siguiente">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Fin -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $total_paginas]); ?>"
                                   aria-label="Fin">
                                    <i class="fas fa-fast-forward"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <!-- Ir a página específica -->
                    <?php if ($registros_por_pagina != 'todos' && $total_paginas > 1): ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla de Clientes -->
        <div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">Listado de Contratos Registrados</h6>
        <small class="text-muted" id="contadorClientes">
            Mostrando <span id="clientesVisibles"><?php echo $total_registros; ?></span> de <?php echo $total_contratos; ?> clientes
        </small>
    </div>
    <div>
        <span class="badge bg-warning text-dark me-2" id="badgeContratos"><?php echo $total_registros; ?> contratos</span>
        <span class="badge bg-success" id="badgeActivos">Activos: <?php echo $total_clientes_activos; ?></span>
        <!-- Botón de imprimir en el header -->
        <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="imprimirTablaClientes()" title="Imprimir listado">
            <i class="fas fa-print"></i>
        </button>
        <div class="btn-group ms-2" role="group">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="exportarAPDF()" 
                    title="Exportar a PDF" data-bs-toggle="tooltip">
                <i class="fas fa-file-pdf"></i>
            </button>          
			<button type="button" class="btn btn-sm btn-outline-success" onclick="exportarAExcel()" 
                    title="Exportar a Excel" data-bs-toggle="tooltip">
                <i class="fas fa-file-excel"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportarAWord()" 
                    title="Exportar a Word" data-bs-toggle="tooltip">
                <i class="fas fa-file-word"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" onclick="exportarACSV()" 
                    title="Exportar a CSV" data-bs-toggle="tooltip">
                <i class="fas fa-file-csv"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" onclick="exportarATXT()" 
                    title="Exportar a TXT" data-bs-toggle="tooltip">
                <i class="fas fa-file-alt"></i>
            </button>
        </div>
    </div>
</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clientesTable">
<thead>
    <tr>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'codigo', 'direccion' => ($orden == 'codigo' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Código
                <?php if ($orden == 'codigo'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'nombre', 'direccion' => ($orden == 'nombre' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Cliente
                <?php if ($orden == 'nombre'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
		<th style="display:none;">Dirección</th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'ContratoNo', 'direccion' => ($orden == 'ContratoNo' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                No. Contrato
                <?php if ($orden == 'ContratoNo'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'ResponsableEntidad', 'direccion' => ($orden == 'ResponsableEntidad' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Responsable Entidad
                <?php if ($orden == 'ResponsableEntidad'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'activo', 'direccion' => ($orden == 'activo' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Estado/Cliente
                <?php if ($orden == 'activo'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'estado_contrato', 'direccion' => ($orden == 'estado_contrato' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Estado/Contrato
                <?php if ($orden == 'estado_contrato'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'fechaRegistro', 'direccion' => ($orden == 'fechaRegistro' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                F. Inicio
                <?php if ($orden == 'fechaRegistro'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'vigenciapor', 'direccion' => ($orden == 'vigenciapor' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Vigencia
                <?php if ($orden == 'vigenciapor'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'fechaVence', 'direccion' => ($orden == 'fechaVence' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                F. Término
                <?php if ($orden == 'fechaVence'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'renovac', 'direccion' => ($orden == 'renovac' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Suplem
                <?php if ($orden == 'renovac'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>
            <a href="<?php echo construirUrl(['orden' => 'fechafinalcontrato', 'direccion' => ($orden == 'fechafinalcontrato' && $direccion == 'ASC' ? 'DESC' : 'ASC')]); ?>" 
               class="text-decoration-none d-flex align-items-center" style="color: inherit;">
                Fecha Final
                <?php if ($orden == 'fechafinalcontrato'): ?>
                    <i class="fas fa-sort-<?php echo $direccion == 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                <?php else: ?>
                    <i class="fas fa-sort ms-1 text-muted"></i>
                <?php endif; ?>
            </a>
        </th>
        <th>Acciones</th>
    </tr>
</thead>
                        
						
						<tbody>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="fas fa-users text-muted fa-2x mb-2 d-block"></i>
                                        No hay clientes registrados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($cliente['codigo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: var(--win-text-primary);">
                                            <a href="ver_cliente.php?id=<?php echo $cliente['id']; ?>">
                                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                                            </a>
                                            </div>
                                            <?php if (!empty($cliente['direccion'])): ?>
                                                <small class="text-muted" title="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                                                    <?php echo htmlspecialchars($cliente['direccion']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                           <?php echo $cliente['ContratoNo']; ?>
                                        </td>
										<td>
											<div class="fw-bold" style="color: var(--win-text-primary);">
												<?php echo htmlspecialchars($cliente['ResponsableEntidad']); ?>
											</div>
											<?php if (!empty($cliente['NoCIResp'])): ?>
												<small class="text-muted" title="Carné de Identidad">
													CI: <?php echo htmlspecialchars($cliente['NoCIResp']); ?>
												</small>
											<?php else: ?>
											<?php endif; ?>
										</td>
                                        <td>
                                            <?php if ($cliente['activo']): ?>
                                                <span class="badge activo">
                                                    <i class="fas fa-check-circle me-1"></i>Activo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge inactivo">
                                                    <i class="fas fa-times-circle me-1"></i>Inactivo
                                                </span>
                                            <?php endif; ?>
                                        </td>
<td>
    <?php 
    // Verificar si hay fecha de finalización
    if (empty($cliente['fechafinalcontrato']) || $cliente['fechafinalcontrato'] == '0000-00-00') {
        echo '<div class="text-center">';
        echo '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="No tiene fecha de finalización de contrato"><i class="fas fa-calendar-times me-1"></i>Sin Fecha</span>';
        echo '</div>';
    } else {
        // Calcular días restantes
        $fecha_actual = new DateTime();
        $fecha_vencimiento = new DateTime($cliente['fechafinalcontrato']);
        $diferencia = $fecha_actual->diff($fecha_vencimiento);
        $dias_restantes = $fecha_actual < $fecha_vencimiento ? $diferencia->days : -$diferencia->days;
        
        if ($dias_restantes == 0) {
            // Vence hoy
            echo '<div class="text-center">';
            echo '<span class="badge bg-danger mb-1" data-bs-toggle="tooltip" title="¡El contrato vence hoy!"><i class="fas fa-exclamation-triangle me-1"></i>VENCE HOY</span>';
            echo '<br><small class="text-danger fw-bold">¡VENCIENDO HOY!</small>';
            echo '</div>';
        } elseif ($dias_restantes < 0) {
            // Ya vencido
            $dias_vencido = abs($dias_restantes);
            $tiempo_vencido = formatoTiempoLegible($dias_vencido);
            echo '<div class="text-center">';
            echo '<span class="badge bg-danger mb-1" data-bs-toggle="tooltip" title="Vencido hace ' . $tiempo_vencido . '"><i class="fas fa-exclamation-triangle me-1"></i>Vencido</span>';
            echo '<br><small class="text-danger">' . $tiempo_vencido . '</small>';
            echo '</div>';
        } elseif ($dias_restantes <= 30) {
            // Próximo a vencer
            $tiempo_restante = formatoTiempoLegible($dias_restantes);
            echo '<div class="text-center">';
            echo '<span class="badge bg-warning text-dark mb-1" data-bs-toggle="tooltip" title="Próximo a vencer en ' . $tiempo_restante . '"><i class="fas fa-exclamation-circle me-1"></i>Próximo</span>';
            echo '<br><small class="text-warning">' . $tiempo_restante . '</small>';
            echo '</div>';
        } else {
            // Vigente
            $tiempo_restante = formatoTiempoLegible($dias_restantes);
            echo '<div class="text-center">';
            echo '<span class="badge bg-success mb-1" data-bs-toggle="tooltip" title="Vigente por ' . $tiempo_restante . '"><i class="fas fa-check-circle me-1"></i>Vigente</span>';
            echo '<br><small class="text-success">' . $tiempo_restante . '</small>';
            echo '</div>';
        }
    }
    ?>
</td>
										<td>
                                            <?php echo !empty($cliente['fechaRegistro']) && $cliente['fechaRegistro'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechaRegistro'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($cliente['vigenciapor'])) {
                                                if ($cliente['vigenciapor'] == 1) {
                                                    echo '<span class="badge bg-info">1 año</span>';
                                                } else {
                                                    echo '<span class="badge bg-primary">' . $cliente['vigenciapor'] . ' años</span>';
                                                }
                                            } else {
                                                echo '<span class="badge bg-secondary">Sin definir</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($cliente['fechaVence']) && $cliente['fechaVence'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechaVence'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($cliente['renovac'] == 1) {
                                                $cantidad = $cliente['si_renova_cant'];
                                                if ($cantidad == 1) {
                                                    echo '1 año';
                                                } else {
                                                    echo $cantidad . ' años';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($cliente['fechafinalcontrato']) && $cliente['fechafinalcontrato'] != '0000-00-00' ? date('d/m/Y', strtotime($cliente['fechafinalcontrato'])) : '-'; ?>
                                        </td>
<!-- Columna de Acciones en la tabla -->
<td>
    <div class="btn-group btn-group-sm" role="group">
        <!-- Botón Ver - Siempre activo -->
        <a href="ver_cliente.php?id=<?php echo $cliente['id']; ?>" 
           class="btn btn-outline-primary" 
           title="Ver" data-bs-toggle="tooltip">
            <i class="fas fa-eye"></i>
        </a>
        
        <!-- Botón Editar - Visible pero con alerta si es solo lectura -->
        <?php if (!$esSoloLectura): ?>
            <a href="editar_cliente.php?id=<?php echo $cliente['id']; ?>" 
               class="btn btn-outline-warning" 
               title="Editar" data-bs-toggle="tooltip">
                <i class="fas fa-edit"></i>
            </a>
        <?php else: ?>
            <button type="button" 
                    class="btn btn-outline-warning" 
                    title="Sin permisos para editar" 
                    onclick="mostrarAlertaSinPermisos('editar')"
                    data-bs-toggle="tooltip">
                <i class="fas fa-edit"></i>
            </button>
        <?php endif; ?>
        
        <!-- Botón Facturas - Siempre visible -->
        <a href="facturas.php?cliente_id=<?php echo $cliente['id']; ?>" 
           class="btn btn-outline-info" 
           title="Ver Facturas" data-bs-toggle="tooltip">
            <i class="fas fa-file-invoice"></i>
        </a>
        
        <!-- Botón Eliminar - Visible pero con alerta si es solo lectura -->
        <?php if (!$esSoloLectura): ?>
            <button onclick="eliminarCliente(
                <?php echo $cliente['id']; ?>,
                '<?php echo addslashes(htmlspecialchars($cliente['nombre'])); ?>',
                <?php echo $cliente['total_facturas']; ?>
            )" 
                    class="btn btn-outline-danger" 
                    title="Eliminar" data-bs-toggle="tooltip">
                <i class="fas fa-trash"></i>
            </button>
        <?php else: ?>
            <button type="button" 
                    class="btn btn-outline-danger" 
                    title="Sin permisos para eliminar" 
                    onclick="mostrarAlertaSinPermisos('eliminar')"
                    data-bs-toggle="tooltip">
                <i class="fas fa-trash"></i>
            </button>
        <?php endif; ?>
    </div>
</td>
									
									
									</tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    
	
	</div>
		
		
		
		<!-- Card de Paginación (inferior) -->
        <?php if ($registros_por_pagina != 'todos' && $total_paginas > 1): ?>
        <div class="card pagination-card animate__animated animate__fadeIn mt-3" style="animation-delay: 0.5s;">
            <div class="card-body">
                <div class="pagination-container">
                    <!-- Información de paginación -->
                    <div class="pagination-info">
                        Mostrando 
                        <strong><?php echo ($offset + 1); ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?></strong> 
                        de <strong><?php echo $total_registros; ?></strong> clientes
                        (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                    </div>
                    
                    <!-- Controles de paginación -->
                    <nav aria-label="Paginación de clientes">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Botón Inicio -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => 1]); ?>"
                                   aria-label="Inicio">
                                    <i class="fas fa-fast-backward"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Anterior -->
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $pagina_actual - 1]); ?>"
                                   aria-label="Anterior">
                                    <i class="fas fa-chevron-left"></i>
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => 1]); ?>">1</a>
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => $i]); ?>">
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
                                    <a class="page-link" href="<?php echo construirUrl(['pagina' => $total_paginas]); ?>">
                                        <?php echo $total_paginas; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $pagina_actual + 1]); ?>"
                                   aria-label="Siguiente">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- Botón Fin -->
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo construirUrl(['pagina' => $total_paginas]); ?>"
                                   aria-label="Fin">
                                    <i class="fas fa-fast-forward"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

<!-- Quick Actions -->
<div class="win-quick-actions">
    <!-- Acciones expandidas -->
    <div class="win-quick-actions-expanded" id="quickActionsExpanded">
        <button class="win-quick-action action-factura" 
                onclick="window.location.href='nueva_factura.php'" 
                title="Nueva Factura">
            <i class="fas fa-file-invoice"></i>
        </button>
        
        <?php if (!$esSoloLectura): ?>
            <!-- Botones habilitados para usuarios con permisos -->
            <button class="win-quick-action action-cliente" 
                    onclick="window.location.href='nuevo_cliente.php'" 
                    title="Nuevo Cliente">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio" 
                    onclick="window.location.href='nuevo_servicio.php'" 
                    title="Nuevo Servicio">
                <i class="fas fa-plus-circle"></i>
            </button>
        <?php else: ?>
            <!-- Botones completamente deshabilitados para solo lectura -->
            <button class="win-quick-action action-cliente disabled-quick-action" 
                    style="background-color: #6c757d; cursor: not-allowed; opacity: 0.5;" 
                    disabled
                    title="Modo solo lectura - No puedes crear clientes">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio disabled-quick-action" 
                    style="background-color: #6c757d; cursor: not-allowed; opacity: 0.5;" 
                    disabled
                    title="Modo solo lectura - No puedes crear servicios">
                <i class="fas fa-plus-circle"></i>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Botón principal -->
    <?php if ($esSoloLectura): ?>
        <!-- Botón principal deshabilitado en modo solo lectura -->
        <button class="win-quick-action" 
                style="background-color: #6c757d; cursor: not-allowed; opacity: 0.5;" 
                disabled
                title="Modo solo lectura - Acciones rápidas deshabilitadas">
            <i class="fas fa-lock"></i>
        </button>
    <?php else: ?>
        <!-- Botón principal habilitado para usuarios con permisos -->
        <button class="win-quick-action" 
                onclick="toggleQuickActions()" 
                title="Acciones rápidas" 
                id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    <?php endif; ?>
</div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
	
<!-- Nuevas librerías para exportación -->
<script src="js/jspdf.umd.min.js"></script>
<script src="js/jspdf.plugin.autotable.min.js"></script>
<script src="js/html2pdf.bundle.min.js"></script>
<script src="js/xlsx.full.min.js"></script>


    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        // Función para cambiar registros por página
        function cambiarRegistrosPorPagina(valor) {
            const url = new URL(window.location.href);
            url.searchParams.set('registros_por_pagina', valor);
            url.searchParams.set('pagina', 1); // Volver a la primera página
            window.location.href = url.toString();
        }
        
        // Función para saltar a página específica
        function jumpToPage() {
            const input = document.getElementById('jumpToPage');
            let page = parseInt(input.value);
            const totalPages = <?php echo $total_paginas; ?>;
            
            // Validar entrada
            if (isNaN(page) || page < 1) page = 1;
            if (page > totalPages) page = totalPages;
            
            // Construir URL
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', page);
            window.location.href = url.toString();
        }
        
        // Permitir Enter en el input de saltar a página
        document.getElementById('jumpToPage')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                jumpToPage();
            }
        });
        
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
        
        // Función para eliminar cliente (mejorada)
        function eliminarCliente(id, nombre, totalFacturas) {
            if (totalFacturas > 0) {
                // Cliente tiene facturas, preguntar si desea inactivar
                Swal.fire({
                    title: '¿Inactivar cliente?',
                    html: `El cliente <strong>${nombre}</strong> tiene ${totalFacturas} factura(s) registrada(s).<br><br>
                           <strong>No puede ser eliminado</strong>, pero puede ser inactivado.<br>
                           ¿Desea inactivar este cliente?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<i class="fas fa-user-slash me-1"></i> Sí, inactivar',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'inactivar_cliente.php?id=' + id;
                    }
                });
            } else {
                // Cliente no tiene facturas, preguntar por eliminación
                Swal.fire({
                    title: '¿Está seguro?',
                    html: `¿Desea eliminar al cliente <strong>${nombre}</strong>?<br><br>
                           Esta acción <strong>no se puede deshacer</strong>.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'eliminar_cliente.php?id=' + id;
                    }
                });
            }
        }
        
        // Mostrar SweetAlert desde sesión
        <?php if (isset($sweet_alert)): ?>
        document.addEventListener('DOMContentLoaded', function() {
			
            Swal.fire({
                icon: '<?php echo $sweet_alert['type']; ?>',
                title: '<?php echo $sweet_alert['title']; ?>',
                html: '<?php echo $sweet_alert['text']; ?>',
                confirmButtonText: '<i class="fas fa-check me-1"></i><?php echo $sweet_alert['confirmButtonText']; ?>',
                confirmButtonColor: '<?php 
                    echo $sweet_alert['type'] == 'success' ? '#28a745' : 
                         ($sweet_alert['type'] == 'warning' ? '#ffc107' : '#dc3545'); 
                ?>',
                <?php if (isset($sweet_alert['showDenyButton']) && $sweet_alert['showDenyButton']): ?>
                showDenyButton: true,
                denyButtonText: '<i class="fas fa-times me-1"></i><?php echo $sweet_alert['denyButtonText']; ?>',
                denyButtonColor: '#3085d6',
                <?php endif; ?>
            }).then((result) => {
                <?php if (isset($sweet_alert['has_invoices']) && $sweet_alert['has_invoices']): ?>
                if (result.isDenied) {
                    // Redirigir a inactivar cliente
                    window.location.href = 'inactivar_cliente.php?id=<?php echo $sweet_alert['id_cliente']; ?>';
                }
                <?php endif; ?>
            });
        });
        <?php endif; ?>
        
        // Actualizar lista de clientes
        function actualizarListaClientes() {
            const btn = document.querySelector('button[onclick="actualizarListaClientes()"]');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
                btn.disabled = true;
                
                // Simular actualización
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Función para limpiar filtros
        function limpiarFiltros() {
            window.location.href = 'clientes.php';
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
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
			
            // Inicializar tooltips
			// Verificar si Bootstrap está cargado
    if (typeof bootstrap === 'undefined') {
        console.error("Error: Bootstrap no está cargado. Verifique la ruta del archivo js/bootstrap5.3.0/bootstrap.bundle.min.js");
    } else {
        // Inicializar tooltips con configuración robusta
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
        container: 'body',      // Mantiene el tooltip fuera de contenedores con overflow
        trigger: 'hover focus', 
        placement: 'auto',      // <--- CAMBIO CLAVE: Se ajusta solo si arriba no cabe
        boundary: 'clippingParents', // Evita que se salga de la pantalla
        html: true,               
        fallbackPlacements: ['bottom', 'right', 'left'], // Si arriba no cabe, intenta abajo
        delay: { "show": 100, "hide": 100 } 
    });
});
    }
            // Cerrar panel de temas
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            // Manejar cambios de tamaño de ventana
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('open');
                }
            });
            
            // Cerrar Quick Actions al hacer clic fuera
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
            
			<?php if (isset($_SESSION['swal_no_privilegios'])): 
                $swal = $_SESSION['swal_no_privilegios'];
                unset($_SESSION['swal_no_privilegios']);
            ?>
            
            Swal.fire({
                title: '<?php echo $swal['titulo']; ?>',
                html: `
                    <div class="text-start">
                        <p><?php echo $swal['mensaje']; ?></p>
                        <div class="alert alert-danger p-3 mt-2">
                            <i class="fas fa-user-tag me-2"></i>
                            <strong>Tipo de Usuario Actual:</strong> 
                            <?php
// Determinar clase del badge según rol_id
switch($swal['rol_id']) {
    case 1: $badgeClass = 'badge-admin'; $icon = 'user-shield'; break;
    case 2: $badgeClass = 'badge-secondary'; $icon = 'eye'; break;
    case 3: $badgeClass = 'badge-editor'; $icon = 'edit'; break;
    case 4: $badgeClass = 'badge-supervisor'; $icon = 'code'; break;
	case 5: $badgeClass = 'badge-programador'; $icon = 'code'; break;
    default: $badgeClass = 'badge-secondary'; $icon = 'user'; break;
}
?>

<span class="badge <?php echo $badgeClass; ?>">
    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
    <?php echo $swal['tipo_usuario']; ?>
</span>
                        </div>
                        <div class="alert alert-info p-2 mt-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                <strong>Privilegios requeridos:</strong> Administrador, Editor, Supervisor
                            </small>
                            <br>
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                <strong>Su privilegio actual:</strong> ROL = <?php echo $swal['rol_id']; ?>
                            </small>
                        </div>
                        <p class="mb-0 small mt-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Contacte al administrador del sistema si necesita acceder a esta función.
                        </p>
                    </div>
                `,
                icon: '<?php echo $swal['icono']; ?>',
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                backdrop: 'rgba(0,0,0,0.8)',
                allowOutsideClick: false,
                allowEscapeKey: false
            });
            <?php endif; ?>
			
            // Ajustar altura del contenido principal
            function ajustarAlturaContenido() {
                const contenido = document.querySelector('.win-main-content');
                const alturaVentana = window.innerHeight;
                const alturaNavbar = 48;
                const margenSuperior = parseInt(window.getComputedStyle(contenido).paddingTop);
                
                contenido.style.minHeight = (alturaVentana - alturaNavbar - (margenSuperior * 2)) + 'px';
            }
            
            // Ejecutar al cargar y al redimensionar
            ajustarAlturaContenido();
            window.addEventListener('resize', ajustarAlturaContenido);
        });


// ============================================
// FUNCIÓN PARA IMPRIMIR TABLA DE CLIENTES
// SIGUIENDO EL MISMO ESTILO QUE imprimirReporteReal()
// ============================================
function imprimirTablaClientes() {
    // ===== CONSTANTES (SIMILAR A imprimirReporteReal) =====
    const LOGO = <?= json_encode($logo_base64) ?>;
    const FECHA = <?= json_encode(date('d/m/Y H:i:s')) ?>;
    const FECHA_CORTA = "<?= date('d/m/Y') ?>";
    const HORA = "<?= date('h:i:s A') ?>";
    const USER = <?= json_encode($usuario['nombre'] . ' ' . ($usuario['apellidos'] ?? '')) ?>;
    
    // Datos de clientes y contratos (desde PHP)
    const TOTAL_CONTRATOS = <?= $total_contratos ?? 0 ?>;
    const CONTRATOS_ACTIVOS = <?= $contratos_activos ?? 0 ?>;
    const CONTRATOS_INACTIVOS = <?= $contratos_inactivos ?? 0 ?>;
    const CONTRATOS_VIGENTES = <?= $contratos_vigentes ?? 0 ?>;
    const CONTRATOS_VENCIDOS = <?= $contratos_vencidos ?? 0 ?>;
    const CONTRATOS_PROXIMOS = <?= $contratos_proximos ?? 0 ?>;
    const CONTRATOS_SIN_FECHA = <?= $contratos_sin_fecha ?? 0 ?>;
    const CONTRATOS_RENOVADOS = <?= $contratos_renovados ?? 0 ?>;
    const PORCENTAJE_ACTIVOS = <?= $porcentaje_activos ?? 0 ?>;
    
    // Datos de filtros actuales
    const FILTROS_ACTIVOS = {
        search: "<?= addslashes($search_term) ?>",
        status: "<?= $filtro_status ?>",
        contract_status: "<?= $filtro_contract_status ?>",
        ano: "<?= $filtro_ano ?>"
    };
    
    // ===== OBTENER DATOS DE LA TABLA CON FILTROS =====
    function obtenerDatosParaImpresion() {
        const tabla = document.getElementById('clientesTable');
        if (!tabla) return { datos: [], totalFiltrado: 0 };
        
        const filas = Array.from(tabla.querySelectorAll('tbody tr'));
        const datos = [];
        
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td');
            if (celdas.length < 11) return;
            
            const filaDatos = [];
            
            // Código
            const codigo = celdas[0]?.querySelector('.badge')?.textContent.trim() || celdas[0]?.textContent.trim() || '-';
            filaDatos.push(codigo);
            
            // Cliente y Dirección
            const nombre = celdas[1]?.querySelector('.fw-bold a')?.textContent.trim() || 
                          celdas[1]?.querySelector('.fw-bold')?.textContent.trim() || 
                          celdas[1]?.textContent.trim().split('\n')[0] || '-';
            const direccion = celdas[1]?.querySelector('small')?.textContent.trim() || 'S/R';
            filaDatos.push(nombre);
            filaDatos.push(direccion);
            
            // No. Contrato
            filaDatos.push(celdas[2]?.textContent.trim() || '-');
            
            // Responsable y CI
            const responsable = celdas[3]?.querySelector('.fw-bold')?.textContent.trim() || 
                               celdas[3]?.textContent.trim().split('\n')[0] || '-';
            filaDatos.push(responsable);
            
            // Estado Cliente
            const estadoCli = celdas[4]?.querySelector('.badge')?.textContent.trim() || '-';
            filaDatos.push(estadoCli);
            
            // Estado Contrato
            const estadoCont = celdas[5]?.textContent.trim().replace(/\s+/g, ' ') || '-';
            filaDatos.push(estadoCont);
            
            // F. Inicio
            filaDatos.push(celdas[6]?.textContent.trim() || '-');
            
            // Vigencia
            const vigencia = celdas[7]?.querySelector('.badge')?.textContent.trim() || '-';
            filaDatos.push(vigencia);
            
            // F. Término
            filaDatos.push(celdas[8]?.textContent.trim() || '-');
            
            // Suplem
            filaDatos.push(celdas[9]?.textContent.trim() || '-');
            
            // F. Final
            filaDatos.push(celdas[10]?.textContent.trim() || '-');
            
            datos.push(filaDatos);
        });
        
        return {
            datos: datos,
            totalFiltrado: datos.length,
            totalGeneral: TOTAL_CONTRATOS
        };
    }
    
    const datosTabla = obtenerDatosParaImpresion();
    const filasFiltradas = datosTabla.datos;
    const totalFiltrado = datosTabla.totalFiltrado;
    
    if (filasFiltradas.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No hay datos para imprimir',
            text: 'No hay clientes que coincidan con los filtros aplicados.',
            confirmButtonText: 'Entendido'
        });
        return;
    }
    
    // ===== CONFIGURACIÓN DE PAGINACIÓN =====
    const FILAS_POR_PAGINA_NORMAL = 10; // Más filas para que quepa mejor
    const FILAS_PRIMERA_PAGINA = 10;    // Primera página con menos filas para que quepa el resumen
    
    let paginaActual = 1;
    let html = '';
    
    // Determinar páginas
    let paginasConfiguradas = [];
    if (filasFiltradas.length <= FILAS_PRIMERA_PAGINA) {
        paginasConfiguradas = [{
            inicio: 0,
            fin: filasFiltradas.length,
            filas: filasFiltradas.slice(0, filasFiltradas.length)
        }];
    } else {
        // Primera página con menos filas
        const primeraPaginaFilas = filasFiltradas.slice(0, FILAS_PRIMERA_PAGINA);
        paginasConfiguradas = [{
            inicio: 0,
            fin: FILAS_PRIMERA_PAGINA,
            filas: primeraPaginaFilas
        }];
        
        // Resto de páginas con filas normales
        const restoFilas = filasFiltradas.slice(FILAS_PRIMERA_PAGINA);
        const paginasRestantes = Math.ceil(restoFilas.length / FILAS_POR_PAGINA_NORMAL);
        
        for (let i = 0; i < paginasRestantes; i++) {
            const inicio = FILAS_PRIMERA_PAGINA + (i * FILAS_POR_PAGINA_NORMAL);
            const fin = Math.min(inicio + FILAS_POR_PAGINA_NORMAL, filasFiltradas.length);
            paginasConfiguradas.push({
                inicio: inicio,
                fin: fin,
                filas: restoFilas.slice(i * FILAS_POR_PAGINA_NORMAL, (i * FILAS_POR_PAGINA_NORMAL) + FILAS_POR_PAGINA_NORMAL)
            });
        }
    }
    
    const TOTAL_PAGINAS = paginasConfiguradas.length;
    
    // ===== GENERAR HTML =====
    
    // --- CARÁTULA 1: RESUMEN GENERAL Y ESTADÍSTICAS ---
    html += `
    <div class="p-page horizontal">
        <!-- Encabezado -->
        <div style="display: flex; align-items: center; margin-bottom: 10px; border-bottom: 2px solid #0078D4; padding-bottom: 5px;">
            <img src="${LOGO}" height="50" style="margin-right: 15px;">
            <div style="line-height: 1;">
                <h1 style="color:#0078D4; margin: 0; font-size: 18pt; line-height: 1;">PDL VISIONES - SISFACT</h1>
                <h2 style="font-size: 14pt; color: #333; margin: 2px 0 0 0; line-height: 1;">Reporte de Clientes y Contratos</h2>
            </div>
        </div>
        
        <!-- PRIMERA FILA: RESUMEN GENERAL -->
        <div style="display: flex; gap: 15px; margin-top: 5px;">
            <!-- Columna Izquierda: Resumen de Clientes -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">📋 Resumen General</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 2px 4px;">Total Contratos:</td><td style="padding: 2px 4px; font-weight:bold;">${TOTAL_CONTRATOS}</td></tr>
                    <tr><td style="padding: 2px 4px;">Contratos Activos:</td><td style="padding: 2px 4px; color: #28a745; font-weight:bold;">${CONTRATOS_ACTIVOS}</td></tr>
                    <tr><td style="padding: 2px 4px;">Contratos Inactivos:</td><td style="padding: 2px 4px; color: #dc3545; font-weight:bold;">${CONTRATOS_INACTIVOS}</td></tr>
                    <tr><td style="padding: 2px 4px;">Tasa de Actividad:</td><td style="padding: 2px 4px; font-weight:bold;">${PORCENTAJE_ACTIVOS.toFixed(1)}%</td></tr>
                </table>
            </div>
            
            <!-- Columna Derecha: Estado de Contratos -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; border-bottom:1px solid #0078D4; padding-bottom:3px; line-height: 1;">📊 Estado de Contratos</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 2px 4px;">✅ Vigentes:</td><td style="padding: 2px 4px; color: #28a745; font-weight:bold;">${CONTRATOS_VIGENTES}</td></tr>
                    <tr><td style="padding: 2px 4px;">⚠️ Próximos a Vencer:</td><td style="padding: 2px 4px; color: #ff9800; font-weight:bold;">${CONTRATOS_PROXIMOS}</td></tr>
                    <tr><td style="padding: 2px 4px;">❌ Vencidos:</td><td style="padding: 2px 4px; color: #dc3545; font-weight:bold;">${CONTRATOS_VENCIDOS}</td></tr>
                    <tr><td style="padding: 2px 4px;">❓ Sin Fecha Final:</td><td style="padding: 2px 4px; color: #6c757d; font-weight:bold;">${CONTRATOS_SIN_FECHA}</td></tr>
                </table>
            </div>
        </div>
        
        <!-- SEGUNDA FILA: ESTADÍSTICAS DETALLADAS Y FILTROS -->
        <div style="display: flex; gap: 15px; margin-top: 15px;">
            <!-- Columna Izquierda: Renovaciones y Datos Adicionales -->
            <div style="flex: 1; background-color: #f9f9f9; padding: 8px; border-radius: 5px;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; color:#0078D4; line-height: 1;">🔄 Renovaciones y Suplementos</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt; line-height: 1;">
                    <tr><td style="padding: 3px 5px;"><strong>Contratos con Suplemento:</strong></td>
                        <td style="padding: 3px 5px; text-align: right; font-weight: bold; color: #17a2b8;">${CONTRATOS_RENOVADOS}</td></tr>
                </table>
            </div>
            
            <!-- Columna Derecha: Filtros Aplicados -->
            <div style="flex: 2; background-color: #f0f7ff; padding: 8px; border-radius: 5px;">
                <h3 style="font-size: 12pt; margin:0 0 5px 0; color:#0078D4; line-height: 1;">🔍 Filtros Aplicados en Vista</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 5px; font-size: 10pt;">`;
    
    // Agregar filtros si existen
    let filtrosAplicados = [];
    if (FILTROS_ACTIVOS.search) filtrosAplicados.push(`Búsqueda: "${FILTROS_ACTIVOS.search}"`);
    if (FILTROS_ACTIVOS.status === 'activo') filtrosAplicados.push('Estado Cliente: Activos');
    if (FILTROS_ACTIVOS.status === 'inactivo') filtrosAplicados.push('Estado Cliente: Inactivos');
    if (FILTROS_ACTIVOS.contract_status === 'vigente') filtrosAplicados.push('Contrato: Vigentes');
    if (FILTROS_ACTIVOS.contract_status === 'proximo') filtrosAplicados.push('Contrato: Próximos a Vencer');
    if (FILTROS_ACTIVOS.contract_status === 'vencido') filtrosAplicados.push('Contrato: Vencidos');
    if (FILTROS_ACTIVOS.contract_status === 'sin_fecha') filtrosAplicados.push('Contrato: Sin Fecha');
    if (FILTROS_ACTIVOS.ano) filtrosAplicados.push(`Año Inicio: ${FILTROS_ACTIVOS.ano}`);
    
    if (filtrosAplicados.length > 0) {
        filtrosAplicados.forEach(filtro => {
            html += `<span style="background: #0078D4; color: white; padding: 2px 8px; border-radius: 12px; font-size: 9pt;">${filtro}</span>`;
        });
    } else {
        html += `<span style="color: #666;">Sin filtros aplicados (mostrando todos los registros)</span>`;
    }
    
    html += `
                </div>
                <div style="margin-top: 8px; font-size: 10pt; border-top: 1px dashed #0078D4; padding-top: 5px;">
                    <strong>Mostrando:</strong> ${totalFiltrado} de ${TOTAL_CONTRATOS} contratos
                </div>
            </div>
        </div>
        
        <!-- Información de generación -->
        <div style="margin-top: 15px; font-size: 10pt; color: #666; text-align: center; padding: 5px; background: #f5f5f5; border-radius: 3px;">
            Reporte generado el ${FECHA_CORTA} a las ${HORA} por ${USER}
        </div>
        
        <!-- FOOTER DE LA CARÁTULA 1 -->
        <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 5px; display: flex; justify-content: space-between; font-size: 9pt; line-height: 1;">
            <div style="text-align: left;">
                Página <span class="pagina-actual">1</span> de <span class="total-paginas">${TOTAL_PAGINAS + 1}</span><br>
                Total Registros: ${totalFiltrado}<br>
            </div>
            <div style="text-align: center;">
                <strong>PDL Visiones</strong><br>
                www.pdlvisiones.com<br>
                "Donde tu visión toma forma"
            </div>
            <div style="text-align: right;">
                <strong>PDL VISIONES - SISFACT</strong><br>
                Gestión de Clientes<br>
                Reporte de Contratación
            </div>
        </div>
    </div>`;
    
    // ===== CARÁTULA 2: ESTADÍSTICAS DETALLADAS Y GRÁFICO DE BARRAS (TEXTO) =====
    html += `
    <div class="p-page horizontal">
        <div style="display: flex; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #0078D4; padding-bottom: 10px;">
            <img src="${LOGO}" height="60" style="margin-right: 20px;">
            <div>
                <h1 style="color:#0078D4; margin: 0; font-size: 18pt;">PDL VISIONES - SISFACT</h1>
                <h2 style="font-size: 14pt; color: #333; margin: 5px 0 0 0;">Estadísticas Detalladas de Contratos</h2>
            </div>
        </div>
        
        <div style="display: flex; gap: 20px; margin-top: 10px;">
            <!-- Columna Izquierda: Distribución de Contratos -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 10px 0; border-bottom:1px solid #0078D4; padding-bottom:5px;">📊 Distribución de Contratos</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt;">
                    <tr><td style="padding: 5px;">Total Contratos:</td><td style="padding: 5px; font-weight:bold;">${TOTAL_CONTRATOS}</td></tr>
                    <tr style="border-top:1px solid #0078D4;"><td style="padding: 5px;">Activos en Sistema:</td><td style="padding: 5px; color:#28a745; font-weight:bold;">${CONTRATOS_ACTIVOS}</td></tr>
                    <tr><td style="padding: 5px; padding-left:20px;">Porcentaje:</td><td style="padding: 5px;">${PORCENTAJE_ACTIVOS.toFixed(1)}%</td></tr>
                    <tr><td style="padding: 5px;">Inactivos en Sistema:</td><td style="padding: 5px; color:#dc3545; font-weight:bold;">${CONTRATOS_INACTIVOS}</td></tr>
                    <tr><td style="padding: 5px; padding-left:20px;">Porcentaje:</td><td style="padding: 5px;">${(100 - PORCENTAJE_ACTIVOS).toFixed(1)}%</td></tr>
                </table>
                
                <h3 style="font-size: 12pt; margin:15px 0 10px 0; border-bottom:1px solid #0078D4; padding-bottom:5px;">⏱️ Estado por Vigencia</h3>
                <table style="width:100%; border-collapse: collapse; font-size: 11pt;">
                    <tr><td style="padding: 5px;">✅ Vigentes:</td><td style="padding: 5px; color:#28a745; font-weight:bold;">${CONTRATOS_VIGENTES}</td></tr>
                    <tr><td style="padding: 5px;">⚠️ Próximos a Vencer (30 días):</td><td style="padding: 5px; color:#ff9800; font-weight:bold;">${CONTRATOS_PROXIMOS}</td></tr>
                    <tr><td style="padding: 5px;">❌ Vencidos:</td><td style="padding: 5px; color:#dc3545; font-weight:bold;">${CONTRATOS_VENCIDOS}</td></tr>
                    <tr><td style="padding: 5px;">❓ Sin Fecha Definida:</td><td style="padding: 5px; color:#6c757d; font-weight:bold;">${CONTRATOS_SIN_FECHA}</td></tr>
                </table>
            </div>
            
            <!-- Columna Derecha: Gráfico de Barras en Texto -->
            <div style="flex: 1;">
                <h3 style="font-size: 12pt; margin:0 0 10px 0; border-bottom:1px solid #0078D4; padding-bottom:5px;">📈 Visualización de Datos</h3>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span>Activos (${CONTRATOS_ACTIVOS})</span>
                            <span>${PORCENTAJE_ACTIVOS.toFixed(1)}%</span>
                        </div>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: ${PORCENTAJE_ACTIVOS}%; background: #28a745;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span>Vigentes (${CONTRATOS_VIGENTES})</span>
                            <span>${TOTAL_CONTRATOS > 0 ? ((CONTRATOS_VIGENTES/TOTAL_CONTRATOS)*100).toFixed(1) : 0}%</span>
                        </div>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: ${TOTAL_CONTRATOS > 0 ? (CONTRATOS_VIGENTES/TOTAL_CONTRATOS)*100 : 0}%; background: #28a745;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span>Próximos (${CONTRATOS_PROXIMOS})</span>
                            <span>${TOTAL_CONTRATOS > 0 ? ((CONTRATOS_PROXIMOS/TOTAL_CONTRATOS)*100).toFixed(1) : 0}%</span>
                        </div>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: ${TOTAL_CONTRATOS > 0 ? (CONTRATOS_PROXIMOS/TOTAL_CONTRATOS)*100 : 0}%; background: #ff9800;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span>Vencidos (${CONTRATOS_VENCIDOS})</span>
                            <span>${TOTAL_CONTRATOS > 0 ? ((CONTRATOS_VENCIDOS/TOTAL_CONTRATOS)*100).toFixed(1) : 0}%</span>
                        </div>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: ${TOTAL_CONTRATOS > 0 ? (CONTRATOS_VENCIDOS/TOTAL_CONTRATOS)*100 : 0}%; background: #dc3545;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span>Suplementos (${CONTRATOS_RENOVADOS})</span>
                            <span>${TOTAL_CONTRATOS > 0 ? ((CONTRATOS_RENOVADOS/TOTAL_CONTRATOS)*100).toFixed(1) : 0}%</span>
                        </div>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; width: ${TOTAL_CONTRATOS > 0 ? (CONTRATOS_RENOVADOS/TOTAL_CONTRATOS)*100 : 0}%; background: #17a2b8;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FOOTER DE LA CARÁTULA 2 -->
        <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9pt;">
            <div style="text-align: left;">
                Página <span class="pagina-actual">2</span> de <span class="total-paginas">${TOTAL_PAGINAS + 1}</span><br>
                Total Registros: ${totalFiltrado}
            </div>
            <div style="text-align: center;">
                <strong>PDL Visiones</strong><br>
                www.pdlvisiones.com<br>
                "Donde tu visión toma forma"
            </div>
            <div style="text-align: right;">
                <strong>PDL VISIONES - SISFACT</strong><br>
                Gestión de Clientes<br>
                Estadísticas Detalladas
            </div>
        </div>
    </div>`;
    
    // ===== PÁGINAS DE TABLA =====
    paginasConfiguradas.forEach((pagina, index) => {
        const pageNum = index + 3; // Comenzamos desde página 3 (después de las 2 carátulas)
        const pageRows = pagina.filas;
        const esPrimeraPaginaTabla = (index === 0);
        
        let tituloTabla = `LISTADO DE CLIENTES Y CONTRATOS`;
        if (TOTAL_PAGINAS > 1) {
            tituloTabla += ` (Página ${index + 1} de ${TOTAL_PAGINAS})`;
        }
        
        html += `
        <div class="p-page horizontal">
            <div style="border-bottom:2px solid #0078D4; font-size: 12pt; margin-bottom:10px; padding-bottom:5px; font-weight:bold; display: flex; justify-content: space-between;">
                <span>${tituloTabla}</span>
                <span style="font-size: 10pt; color: #666;">Mostrando ${pagina.inicio + 1} - ${pagina.fin} de ${totalFiltrado}</span>
            </div>
            
            <table style="width:100%; border-collapse: collapse; font-size: 9pt;">
                <thead>
                    <tr>
                        <th style="width: 5%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Cód</th>
                        <th style="width: 15%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Cliente</th>
                        <th style="width: 15%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Dirección</th>
                        <th style="width: 7%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">No. Cont</th>
                        <th style="width: 12%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Responsable</th>
                        <th style="width: 6%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Est.Cli</th>
                        <th style="width: 8%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Est.Cont</th>
                        <th style="width: 6%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">F.Inicio</th>
                        <th style="width: 5%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Vig</th>
                        <th style="width: 6%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">F.Término</th>
                        <th style="width: 5%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">Sup</th>
                        <th style="width: 6%; background: #f0f0f0; border: 1px solid #999; padding: 4px;">F.Final</th>
                    </tr>
                </thead>
                <tbody>`;
        
        pageRows.forEach(fila => {
            // Determinar clase para fila con problemas
            let claseFila = '';
            let colorEstadoCont = '';
            
            if (fila[6] && (fila[6].includes('Vencido') || fila[6].includes('VENCE HOY'))) {
                claseFila = 'fila-vencida';
                colorEstadoCont = 'color: #dc3545; font-weight: bold;';
            } else if (fila[6] && fila[6].includes('Próximo')) {
                claseFila = 'fila-proxima';
                colorEstadoCont = 'color: #ff9800; font-weight: bold;';
            } else if (fila[6] && fila[6].includes('Vigente')) {
                claseFila = 'fila-vigente';
                colorEstadoCont = 'color: #28a745; font-weight: bold;';
            }
            
            html += `
                    <tr class="${claseFila}">
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[0] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px;"><strong>${fila[1] || ''}</strong></td>
                        <td style="border: 1px solid #ccc; padding: 3px;">${fila[2] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[3] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px;">${fila[4] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[5] ? fila[5].replace('Activo','ACT').replace('Inactivo','INA') : ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center; ${colorEstadoCont}">${fila[6] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[7] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[8] ? fila[8].replace('años','a').replace('año','a') : ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[9] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[10] || ''}</td>
                        <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">${fila[11] || ''}</td>
                    </tr>`;
        });
        
        html += `
                </tbody>
            </table>
            
            <!-- FOOTER DE PÁGINA DE TABLA -->
            <div style="position: absolute; bottom: 0.5in; left: 0.5in; right: 0.5in; border-top: 1px solid #ccc; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9pt;">
                <div style="text-align: left;">
                    Página ${pageNum} de ${TOTAL_PAGINAS + 2}<br>
                    Total Registros: ${totalFiltrado}
                </div>
                <div style="text-align: center;">
                    <strong>PDL Visiones</strong><br>
                    www.pdlvisiones.com<br>
                    "Donde tu visión toma forma"
                </div>
                <div style="text-align: right;">
                    <strong>PDL VISIONES - SISFACT</strong><br>
                    Gestión de Clientes<br>
                    Listado Detallado
                </div>
            </div>
            
            <div style="position: absolute; bottom: 0.3in; left: 0.5in; right: 0.5in; text-align: center; font-size: 8pt; color: #666;">
                Generado por: ${USER} | ${FECHA_CORTA} ${HORA}
            </div>
        </div>`;
    });
    
    // ===== ABRIR VENTANA DE IMPRESIÓN =====
    const win = window.open('', '_blank');
    win.document.write(`<html><head>
        <style>
            @page { 
                size: landscape;
                margin: 0.5in;
            }
            body { 
                font-family: 'Segoe UI', Arial, sans-serif; 
                background: #333; 
                margin:0; 
                padding:0; 
                font-size: 11pt;
            }
            .p-page { 
                background: white; 
                width: 11in;
                min-height: 8.5in;
                margin: 0 auto; 
                padding: 0.5in; 
                position: relative; 
                box-sizing: border-box; 
                page-break-after: always; 
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .p-page.horizontal {
                width: 11in;
                height: 8.5in;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                font-size: 9pt; 
            }
            th { 
                background: #f0f0f0; 
                border: 1px solid #999; 
                padding: 4px; 
                font-weight: bold; 
            }
            td { 
                border: 1px solid #ccc; 
                padding: 3px; 
                vertical-align: middle;
            }
            .fila-vencida { 
                background-color: #ffebee !important; 
            }
            .fila-proxima { 
                background-color: #fff3e0 !important; 
            }
            .fila-vigente { 
                background-color: #e8f5e9 !important; 
            }
            .valor-negativo { 
                color: #d32f2f !important; 
                font-weight: bold; 
            }
            .valor-positivo { 
                color: #2e7d32 !important; 
                font-weight: bold; 
            }
            @media print { 
                body { 
                    background: none; 
                } 
                .p-page { 
                    margin: 0; 
                    box-shadow: none; 
                } 
            }
        </style>
    </head><body>${html}</body></html>`);
    
    win.document.close();
    setTimeout(() => { 
        win.print(); 
        setTimeout(() => { win.close(); }, 500);
    }, 700);
}

// Función para obtener texto de filtros activos
function obtenerTextoFiltros() {
    const filtrosActivos = [];
    const searchTerm = "<?php echo addslashes($search_term); ?>";
    const filterStatus = "<?php echo $filtro_status; ?>";
    const filterContractStatus = "<?php echo $filtro_contract_status; ?>";
    const filterAno = "<?php echo $filtro_ano; ?>";
    
    if (searchTerm && searchTerm.trim() !== '') filtrosActivos.push(`Búsqueda: "${searchTerm}"`);
    if (filterStatus && filterStatus !== '') {
        filtrosActivos.push(filterStatus === 'activo' ? 'Estado: Activos' : 'Estado: Inactivos');
    }
    if (filterContractStatus && filterContractStatus !== '') {
        let textoContrato = '';
        switch(filterContractStatus) {
            case 'vigente': textoContrato = 'Contrato: Vigentes'; break;
            case 'proximo': textoContrato = 'Contrato: Próximos a Vencer'; break;
            case 'vencido': textoContrato = 'Contrato: Vencidos'; break;
            case 'sin_fecha': textoContrato = 'Contrato: Sin Fecha'; break;
            default: textoContrato = `Contrato: ${filterContractStatus}`;
        }
        filtrosActivos.push(textoContrato);
    }
    if (filterAno && filterAno !== '') filtrosActivos.push(`Año Inicio: ${filterAno}`);
    
    return filtrosActivos.length > 0 ? filtrosActivos.join(' | ') : 'Sin filtros aplicados';
}

// ============================================
// FUNCIONES DE EXPORTACIÓN
// ============================================

function obtenerDatosTabla(conFiltros = true) {
    const tabla = document.getElementById('clientesTable');
    const datos = [];
    
    // Obtener encabezados (incluyendo dirección)
    const encabezados = [];
    tabla.querySelectorAll('thead th').forEach(th => {
        // Excluir la columna de acciones
        if (!th.textContent.includes('Acciones')) {
            encabezados.push(th.textContent.trim());
        }
    });
    // Agregar "Dirección" manualmente si no está en los encabezados
    if (!encabezados.includes('Dirección')) {
        // Insertar después de "Cliente"
        const clienteIndex = encabezados.indexOf('Cliente');
        if (clienteIndex !== -1) {
            encabezados.splice(clienteIndex + 1, 0, 'Dirección');
        } else {
            encabezados.push('Dirección');
        }
    }
    
    // Obtener todas las filas de la tabla
    const filas = Array.from(tabla.querySelectorAll('tbody tr'));
    
    // Obtener filas de datos
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        const filaDatos = [];
        
        celdas.forEach((celda, index) => {
            // Excluir la última columna (acciones)
            if (index < celdas.length - 1) {
                let contenido = celda.textContent.trim();
                
                // Limpiar contenido de badges
                if (celda.querySelector('.badge')) {
                    contenido = celda.querySelector('.badge').textContent.trim();
                }
                
                // Para la columna de nombre (índice 1), obtener tanto nombre como dirección
                if (index === 1) {
                    const nombre = celda.querySelector('.fw-bold')?.textContent.trim() || contenido;
                    const direccion = celda.querySelector('small')?.textContent.trim() || 'S/R';
                    
                    filaDatos.push(nombre);
                    filaDatos.push(direccion); // Agregar dirección como columna separada
                    return; // Salir para no procesar doble
                }
                
                filaDatos.push(contenido);
            }
        });
        
        if (filaDatos.length > 0) {
            datos.push(filaDatos);
        }
    });
    
    // Obtener texto de filtros activos desde PHP
    const filtrosActivos = [];
    const searchTerm = "<?php echo addslashes($search_term); ?>";
    const filterStatus = "<?php echo $filtro_status; ?>";
    const filterContractStatus = "<?php echo $filtro_contract_status; ?>";
    const filterAno = "<?php echo $filtro_ano; ?>";
    
    if (searchTerm && searchTerm.trim() !== '') filtrosActivos.push(`Búsqueda: "${searchTerm}"`);
    if (filterStatus && filterStatus !== '') {
        filtrosActivos.push(filterStatus === 'activo' ? 'Estado: Activos' : 'Estado: Inactivos');
    }
    if (filterContractStatus && filterContractStatus !== '') {
        let textoContrato = '';
        switch(filterContractStatus) {
            case 'vigente': textoContrato = 'Contrato: Vigentes'; break;
            case 'proximo': textoContrato = 'Contrato: Próximos a Vencer'; break;
            case 'vencido': textoContrato = 'Contrato: Vencidos'; break;
            case 'sin_fecha': textoContrato = 'Contrato: Sin Fecha'; break;
            default: textoContrato = `Contrato: ${filterContractStatus}`;
        }
        filtrosActivos.push(textoContrato);
    }
    if (filterAno && filterAno !== '') filtrosActivos.push(`Año Inicio: ${filterAno}`);
    
    const tieneFiltros = filtrosActivos.length > 0;
    const textoFiltros = tieneFiltros ? filtrosActivos.join(' | ') : 'Sin filtros aplicados';
    
    return {
        encabezados: encabezados,
        datos: datos,
        tieneFiltros: tieneFiltros,
        textoFiltros: textoFiltros,
        totalFiltrado: datos.length,
        totalGeneral: <?php echo $total_contratos; ?>
    };
}



function exportarAPDF() {
    try {
        // 1. Mostrar estado de carga
        Swal.fire({
            title: 'Generando PDF...',
            html: 'Por favor espere mientras se procesa el documento.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // 2. Obtener datos
        const tablaDatos = obtenerDatosTabla(true);
        const filasFiltradas = tablaDatos.datos;
        const totalFiltrado = tablaDatos.totalFiltrado;
        const totalGeneral = tablaDatos.totalGeneral;
        const textoFiltros = tablaDatos.textoFiltros;
        const tieneFiltros = tablaDatos.tieneFiltros;

        if (filasFiltradas.length === 0) {
            Swal.close();
            Swal.fire({
                icon: 'warning',
                title: 'No hay datos para exportar',
                text: 'No hay clientes que coincidan con los filtros aplicados.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        // 3. Datos generales
        const fechaActual = new Date();
        const fechaFormateada = fechaActual.toLocaleDateString('es-ES', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        
        const logoBase64 = '<?php echo $logo_base64; ?>';
        
        // Variables PHP
        const totalContratos = Number(<?php echo $total_contratos ?? 0; ?>);
        const contratosActivos = Number(<?php echo $contratos_activos ?? 0; ?>);
        const contratosVigentes = Number(<?php echo $contratos_vigentes ?? 0; ?>);
        const contratosProximos = Number(<?php echo $contratos_proximos ?? 0; ?>);
        const contratosVencidos = Number(<?php echo $contratos_vencidos ?? 0; ?>);
        const contratosSinFecha = Number(<?php echo $contratos_sin_fecha ?? 0; ?>);
        const usuarioNombre = "<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISFACT PDL VISIONES'); ?>";

        // 4. Paginación con primera página reducida
        const rowsPerPageNormal = 17;
        const rowsPrimeraPagina = 12;
        
        let paginasConfiguradas = [];
        
        if (filasFiltradas.length <= rowsPrimeraPagina) {
            paginasConfiguradas = [{
                inicio: 0,
                fin: filasFiltradas.length,
                filas: filasFiltradas.slice(0, filasFiltradas.length)
            }];
        } else {
            const primeraPaginaFilas = filasFiltradas.slice(0, rowsPrimeraPagina);
            const restoFilas = filasFiltradas.slice(rowsPrimeraPagina);
            const paginasRestantes = Math.ceil(restoFilas.length / rowsPerPageNormal);
            
            paginasConfiguradas = [{
                inicio: 0,
                fin: rowsPrimeraPagina,
                filas: primeraPaginaFilas
            }];
            
            for (let i = 0; i < paginasRestantes; i++) {
                const inicio = rowsPrimeraPagina + (i * rowsPerPageNormal);
                const fin = Math.min(inicio + rowsPerPageNormal, filasFiltradas.length);
                paginasConfiguradas.push({
                    inicio: inicio,
                    fin: fin,
                    filas: filasFiltradas.slice(inicio, fin)
                });
            }
        }
        
        const totalPages = paginasConfiguradas.length;

        // 5. Construir HTML para PDF - CON TEXTO NEGRO
        const element = document.createElement('div');
        
        let htmlContent = `
            <style>
                /* ===== FORZAR TEXTO NEGRO EN TODAS LAS CELDAS ===== */
                * {
                    color: #000000 !important;
                }
                
                .pdf-container { 
                    font-family: Arial, sans-serif; 
                    font-size: 9px; 
                    line-height: 1.2; 
                    background: #fff; 
                }
                
                .pagina { 
                    width: 100%; 
                    height: 750px;
                    position: relative; 
                    padding: 20px; 
                    box-sizing: border-box; 
                    page-break-after: always;
                }
                .pagina:last-child { page-break-after: auto; }
                
                /* Encabezado */
                .encabezado { 
                    text-align: center; 
                    margin-bottom: 15px; 
                    padding-bottom: 8px; 
                    border-bottom: 2px solid #0078d4; 
                }
                .encabezado h1 { 
                    margin: 0; 
                    color: #0078d4 !important; 
                    font-size: 16px; 
                    font-weight: bold; 
                }
                .encabezado h2 { 
                    margin: 3px 0; 
                    font-size: 12px; 
                    color: #333 !important; 
                    font-weight: normal; 
                }
                
                /* Estadísticas mejoradas */
                .estadisticas-container {
                    margin-bottom: 15px;
                    border: 1px solid #0078d4;
                    border-radius: 5px;
                    overflow: hidden;
                }
                
                .estadisticas-header {
                    background: #0078d4;
                    color: white !important;
                    padding: 6px 10px;
                    font-weight: bold;
                    font-size: 11px;
                }
                
                .estadisticas-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    background: #f5f5f5;
                }
                
                .estadistica-item {
                    padding: 8px 5px;
                    text-align: center;
                    border-right: 1px solid #ddd;
                    border-bottom: 1px solid #ddd;
                }
                
                .estadistica-label {
                    font-size: 10px;
                    color: #555 !important;
                    text-transform: uppercase;
                    display: block;
                }
                
                .estadistica-valor {
                    font-size: 18px !important;
                    font-weight: bold;
                    display: block;
                }
                
                /* Tabla - TODO TEXTO EN NEGRO */
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    font-size: 9px; 
                    table-layout: fixed; 
                }
                
                th { 
                    background-color: #0078d4 !important; 
                    color: white !important; 
                    padding: 5px 3px; 
                    border: 1px solid #0056b3; 
                    text-align: center; 
                    font-weight: bold; 
                }
                
                td { 
                    padding: 4px 3px; 
                    border: 1px solid #ddd; 
                    text-align: center; 
                    vertical-align: middle;
                    color: #000000 !important;  /* FORZADO NEGRO */
                }
                
                .text-left { text-align: left; }
                
                /* Pie de página */
                .pie-pagina { 
                    position: absolute; 
                    bottom: 20px; 
                    left: 20px; 
                    right: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #333 !important; 
                    padding-top: 5px; 
                    border-top: 1px solid #999; 
                }
                
                .numero-pagina {
                    font-weight: bold;
                    color: #0078d4 !important;
                    background: #e6f3ff;
                    padding: 2px 8px;
                    border-radius: 12px;
                }
                
                tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
            <div class="pdf-container">
        `;

        // Generar páginas
        paginasConfiguradas.forEach((pagina, index) => {
            const pageNum = index + 1;
            const pageRows = pagina.filas;

            htmlContent += `
                <div class="pagina">
                    <!-- Encabezado -->
                    <div class="encabezado">
                        <table style="border: none; width: 100%;">
                            <tr style="background: transparent;">
                                <td style="width: 60px; border: none; text-align: left;">
                                    <img src="${logoBase64}" style="width: 50px; height: 50px;">
                                </td>
                                <td style="border: none; text-align: center;">
                                    <h1>SISFACT PDL VISIONES</h1>
                                    <h2>REPORTE DE CLIENTES Y CONTRACTUALES</h2>
                                    <div style="font-size: 10px; color: #333 !important;">${fechaFormateada}</div>
                                    ${tieneFiltros ? `<div style="font-size: 10px; color: #ff9800 !important;">Filtros: ${textoFiltros}</div>` : ''}
                                </td>
                                <td style="width: 60px; border: none;"></td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-bottom: 10px; font-size: 9px; padding: 5px; background: #eef; border-left: 3px solid #0078d4;">
                        <strong>Página ${pageNum} de ${totalPages} | Mostrando:</strong> ${pagina.inicio + 1}-${pagina.fin} de ${totalFiltrado} contratos
                    </div>
            `;

            // Estadísticas solo en primera página
            if (pageNum === 1) {
                htmlContent += `
                    <div class="estadisticas-container">
                        <div class="estadisticas-header">
                            📊 ESTADÍSTICAS GENERALES
                        </div>
                        <div class="estadisticas-grid">
                            <div class="estadistica-item">
                                <span class="estadistica-label">TOTAL</span>
                                <span class="estadistica-valor" style="color: #0078d4 !important;">${totalContratos}</span>
                            </div>
                            <div class="estadistica-item">
                                <span class="estadistica-label">ACTIVOS</span>
                                <span class="estadistica-valor" style="color: #28a745 !important;">${contratosActivos}</span>
                            </div>
                            <div class="estadistica-item">
                                <span class="estadistica-label">VIGENTES</span>
                                <span class="estadistica-valor" style="color: #28a745 !important;">${contratosVigentes}</span>
                            </div>
                            <div class="estadistica-item">
                                <span class="estadistica-label">VENCIDOS</span>
                                <span class="estadistica-valor" style="color: #dc3545 !important;">${contratosVencidos}</span>
                            </div>
                            <div class="estadistica-item">
                                <span class="estadistica-label">PRÓXIMOS</span>
                                <span class="estadistica-valor" style="color: #ff9800 !important;">${contratosProximos}</span>
                            </div>
                            <div class="estadistica-item">
                                <span class="estadistica-label">SIN FECHA</span>
                                <span class="estadistica-valor" style="color: #6c757d !important;">${contratosSinFecha}</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Tabla - TODO TEXTO EN NEGRO
            htmlContent += `
                    <table>
                        <thead>
                            <tr>
                                <th width="6%">Cód</th>
                                <th width="18%" class="text-left">Cliente</th>
                                <th width="18%" class="text-left">Dirección</th>
                                <th width="7%">Contrato</th>
                                <th width="12%" class="text-left">Resp.</th>
                                <th width="6%">Est.Cli</th>
                                <th width="8%">Est.Con</th>
                                <th width="7%">Inicio</th>
                                <th width="5%">Vig.</th>
                                <th width="7%">Término</th>
                                <th width="6%">Suplem</th>
                                <th width="7%">Final</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // === CORREGIDO: TODOS LOS TEXTOS EN NEGRO ===
            pageRows.forEach(fila => {
                // Limpieza de datos - SIN COLORES
                const estCli = fila[5] ? fila[5].replace('Activo', 'ACT').replace('Inactivo', 'INA') : '-';
                const estCon = fila[6] || '-';

                htmlContent += `
                    <tr>
                        <td style="color: #000000 !important;">${fila[0] || ''}</td>
                        <td class="text-left" style="color: #000000 !important;"><strong>${fila[1] || ''}</strong></td>
                        <td class="text-left" style="color: #000000 !important; font-size: 8px;">${fila[2] || ''}</td>
                        <td style="color: #000000 !important;">${fila[3] || ''}</td>
                        <td class="text-left" style="color: #000000 !important; font-size: 8px;">${fila[4] || ''}</td>
                        <td style="color: #000000 !important; font-size: 8px;">${estCli}</td>
                        <td style="color: #000000 !important; font-size: 8px; font-weight: bold;">${estCon}</td>
                        <td style="color: #000000 !important;">${fila[7] || ''}</td>
                        <td style="color: #000000 !important;">${fila[8] ? fila[8].replace('años','a').replace('año','a') : ''}</td>
                        <td style="color: #000000 !important;">${fila[9] || ''}</td>
                        <td style="color: #000000 !important;">${fila[10] || ''}</td>
                        <td style="color: #000000 !important;">${fila[11] || ''}</td>
                    </tr>
                `;
            });

            htmlContent += `
                        </tbody>
                    </table>

                    <!-- Pie de página -->
                    <div class="pie-pagina">
                        <div style="border-bottom: 1px solid #ccc; margin-bottom: 5px;"></div>
                        <span class="numero-pagina">Página ${pageNum} de ${totalPages}</span><br>
                        <span style="color: #333 !important;">Sistema SISFACT PDL Visiones | Usuario: ${usuarioNombre}</span>
                    </div>
                </div>
            `;
        });

        htmlContent += `</div>`;
        element.innerHTML = htmlContent;

        // 6. Configuración de html2pdf
// ===== FORMATO DE FECHA LEGIBLE PARA NOMBRE DE ARCHIVO =====
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

const fechaArchivo = formatearFechaParaArchivo();
const nombreArchivo = tieneFiltros 
    ? `Clientes_Filtrados_${fechaArchivo}.pdf`
    : `Listado_Clientes_${fechaArchivo}.pdf`;

        const opt = {
            margin: [0, 0, 0, 0],
            filename: nombreArchivo,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false, scrollY: 0 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
        };

        // 7. Generar y descargar
        html2pdf().set(opt).from(element).save().then(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'PDF Descargado',
                text: 'El archivo se ha generado correctamente.',
                timer: 2000,
                showConfirmButton: false
            });
        }).catch(err => {
            console.error(err);
            Swal.close();
            Swal.fire('Error', 'No se pudo generar el PDF', 'error');
        });

    } catch (error) {
        console.error('Error en exportarAPDF:', error);
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar el PDF.'
        });
    }
}


function exportarAExcel() {
    try {
        const tablaDatos = obtenerDatosTabla(true); // true = con filtros
        const fecha = obtenerFechaActual();
        
        if (tablaDatos.datos.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No hay datos para exportar',
                text: 'No hay clientes que coincidan con los filtros aplicados.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
        // Crear workbook
        const wb = XLSX.utils.book_new();
        wb.Props = {
            Title: "Reporte de Clientes",
            Subject: "Clientes SISFACT PDL Visiones",
            Author: "SISFACT PDL Visiones",
            CreatedDate: new Date()
        };
        
        // Preparar datos para la hoja principal
        const ws_data = [
            ['REPORTE DE CLIENTES - SISFACT PDL VISIONES'],
            [''],
            [`Fecha de generación: ${fecha.replace('_', ' ')}`],
            [`Usuario: <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema'); ?>`],
            [`Mostrando: ${tablaDatos.totalFiltrado} de ${tablaDatos.totalGeneral} clientes`],
            tablaDatos.tieneFiltros ? [`Filtros aplicados: ${tablaDatos.textoFiltros}`] : [''],
            [''],
            ['ESTADÍSTICAS DEL REPORTE'],
            [`Total de clientes: <?php echo $total_contratos; ?>`],
            [`Clientes activos: <?php echo $contratos_activos; ?>`],
            [`Contratos vigentes: <?php echo $contratos_vigentes; ?>`],
            [`Contratos vencidos: <?php echo $contratos_vencidos; ?>`],
            [`Contratos próximos a vencer: <?php echo $contratos_proximos; ?>`],
            [`Contratos sin fecha: <?php echo $contratos_sin_fecha; ?>`],
            [`Contratos renovados: <?php echo $contratos_renovados; ?>`],
            [''],
            tablaDatos.encabezados,
            ...tablaDatos.datos
        ];
        
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        
        // Aplicar estilos y formato
        const range = XLSX.utils.decode_range(ws['!ref']);
        
        // Estilo para el título (fila 1)
        if (ws['A1']) {
            ws['A1'].s = {
                font: { bold: true, sz: 16, color: { rgb: "0078D4" } },
                alignment: { horizontal: "center" }
            };
        }
        
        // Combinar celdas para el título
        if (!ws['!merges']) ws['!merges'] = [];
        ws['!merges'].push(XLSX.utils.decode_range(`A1:${String.fromCharCode(64 + tablaDatos.encabezados.length)}1`));
        
        // Estilo para información (filas 3-6)
        for (let i = 3; i <= 6; i++) {
            const cell = ws[`A${i}`];
            if (cell) {
                cell.s = {
                    font: { sz: 10, color: { rgb: "666666" } }
                };
            }
        }
        
        // Estilo para "ESTADÍSTICAS DEL REPORTE"
        if (ws['A8']) {
            ws['A8'].s = {
                font: { bold: true, sz: 12, color: { rgb: "333333" } }
            };
        }
        
        // Estilo para encabezados de la tabla
        const headerRow = 16; // Fila donde empiezan los encabezados
        for (let C = 0; C < tablaDatos.encabezados.length; C++) {
            const cell_address = XLSX.utils.encode_cell({ r: headerRow - 1, c: C });
            if (ws[cell_address]) {
                ws[cell_address].s = {
                    fill: { fgColor: { rgb: "0078D4" } },
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    alignment: { horizontal: "center", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "0056B3" } },
                        bottom: { style: "thin", color: { rgb: "0056B3" } },
                        left: { style: "thin", color: { rgb: "0056B3" } },
                        right: { style: "thin", color: { rgb: "0056B3" } }
                    }
                };
            }
        }
        
        // Estilo para datos de la tabla
        for (let R = headerRow; R <= range.e.r; R++) {
            for (let C = 0; C < tablaDatos.encabezados.length; C++) {
                const cell_address = XLSX.utils.encode_cell({ r: R, c: C });
                if (ws[cell_address]) {
                    if (!ws[cell_address].s) ws[cell_address].s = {};
                    ws[cell_address].s.border = {
                        top: { style: "thin", color: { rgb: "E0E0E0" } },
                        bottom: { style: "thin", color: { rgb: "E0E0E0" } },
                        left: { style: "thin", color: { rgb: "E0E0E0" } },
                        right: { style: "thin", color: { rgb: "E0E0E0" } }
                    };
                    
                    // Fondo alternado para filas
                    if ((R - headerRow) % 2 === 0) {
                        ws[cell_address].s.fill = { fgColor: { rgb: "F8F9FA" } };
                    }
                    
                    // Alineación por tipo de columna
                    if (C === 0 || C === 2 || C === 5 || C === 6 || C === 7 || C === 8 || C === 9 || C === 10 || C === 11) {
                        ws[cell_address].s.alignment = { horizontal: "center" };
                    } else if (C === 1 || C === 3 || C === 4) {
                        ws[cell_address].s.alignment = { horizontal: "left" };
                    }
                }
            }
        }
        
        // Ancho de columnas
        ws['!cols'] = [
            { wch: 8 },   // Código
            { wch: 25 },  // Nombre
            { wch: 35 },  // Dirección
            { wch: 12 },  // ContratoNo
            { wch: 20 },  // Responsable
            { wch: 12 },  // Estado Cliente
            { wch: 15 },  // Estado Contrato
            { wch: 12 },  // F. Inicio
            { wch: 10 },  // Vigencia
            { wch: 12 },  // F. Término
            { wch: 8 },   // Suplem
            { wch: 12 }   // Fecha Final
        ];
        
        // Agregar hoja al workbook
        XLSX.utils.book_append_sheet(wb, ws, "Clientes");
        
        // Agregar hoja de estadísticas detalladas
        const porcentajeActivos = ((<?php echo $contratos_activos; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1);
        const porcentajeVigentes = ((<?php echo $contratos_vigentes; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1);
        const porcentajeProximos = ((<?php echo $contratos_proximos; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1);
        const porcentajeVencidos = ((<?php echo $contratos_vencidos; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1);
        
        const ws_stats = XLSX.utils.aoa_to_sheet([
            ['ESTADÍSTICAS DETALLADAS DE CONTRATACIÓN'],
            [''],
            ['Concepto', 'Cantidad', 'Porcentaje', 'Observaciones'],
            ['Total de Contratos', <?php echo $total_contratos; ?>, '100%', 'Todos los contratos registrados'],
            ['Contratos Activos', <?php echo $contratos_activos; ?>, `${porcentajeActivos}%`, 'Clientes con estado activo en sistema'],
            ['Contratos Inactivos', <?php echo $contratos_inactivos; ?>, `${(100 - porcentajeActivos).toFixed(1)}%`, 'Clientes dados de baja temporal'],
            ['Contratos Vigentes', <?php echo $contratos_vigentes; ?>, `${porcentajeVigentes}%`, 'Contratos con fecha vigente'],
            ['Contratos Vencidos', <?php echo $contratos_vencidos; ?>, `${porcentajeVencidos}%`, 'Contratos fuera de fecha'],
            ['Próximos a Vencer', <?php echo $contratos_proximos; ?>, `${porcentajeProximos}%`, 'Vencen en los próximos 30 días'],
            ['Con Renovación', <?php echo $contratos_renovados; ?>, `${((<?php echo $contratos_renovados; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1)}%`, 'Contratos con suplemento'],
            ['Sin Fecha Final', <?php echo $contratos_sin_fecha; ?>, `${((<?php echo $contratos_sin_fecha; ?> / <?php echo $total_contratos; ?>) * 100).toFixed(1)}%`, 'Contratos sin fecha definida']
        ]);
        
        XLSX.utils.book_append_sheet(wb, ws_stats, "Estadísticas");
        
        // Agregar hoja de información del reporte
        const ws_info = XLSX.utils.aoa_to_sheet([
            ['INFORMACIÓN DEL REPORTE'],
            [''],
            ['Sistema:', 'SISFACT PDL Visiones'],
            ['Módulo:', 'Gestión de Clientes'],
            ['Fecha de generación:', fecha.replace('_', ' ')],
            ['Usuario generador:', '<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema'); ?>'],
            ['Total general de contratos:', <?php echo $total_contratos; ?>],
            ['Contratos en este reporte:', tablaDatos.totalFiltrado],
            ['Estado de filtros:', tablaDatos.tieneFiltros ? 'CON FILTROS' : 'SIN FILTROS'],
            [''],
            ['FILTROS APLICADOS:', tablaDatos.textoFiltros],
            [''],
            ['NOTAS:'],
            ['1. Este reporte fue generado automáticamente por el sistema SISFACT PDL-VISIONES.'],
            ['2. Los datos reflejan el estado al momento de la generación.'],
            ['3. Para consultas o aclaraciones, contacte al administrador.']
        ]);
        
        XLSX.utils.book_append_sheet(wb, ws_info, "Información");
        
        // Generar y descargar archivo
// Usar la misma función de formato legible
function formatearFechaParaArchivo() {
    const fecha = new Date();
    const año = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    return `${año}${mes}${dia}_${horas}${minutos}`;
}

const fechaArchivo = formatearFechaParaArchivo();
const nombreArchivo = tablaDatos.tieneFiltros 
    ? `Clientes_Filtrados_${fechaArchivo}.xlsx`
    : `Clientes_Completo_${fechaArchivo}.xlsx`;
        
        XLSX.writeFile(wb, nombreArchivo);
        
        // Mostrar notificación
        Swal.fire({
            icon: 'success',
            title: 'Excel Exportado',
            text: `Se exportaron ${tablaDatos.totalFiltrado} registros en 3 hojas.`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportarAExcel:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al exportar Excel',
            text: 'Ocurrió un error al generar el archivo Excel.',
            confirmButtonText: 'Entendido'
        });
    }
}


function exportarAWord() {
    try {
        const tablaDatos = obtenerDatosTabla(true);
        const fecha = obtenerFechaActual();
        const ahora = new Date();
        const horaFormateada = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const fechaCompleta = ahora.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        if (tablaDatos.datos.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay datos', text: 'No hay registros.', confirmButtonText: 'Entendido' });
            return;
        }
        
        const logoBase64 = '<?php echo addslashes($logo_base64); ?>';
        
        const stats = {
            total: '<?php echo $total_contratos; ?>',
            activos: '<?php echo $contratos_activos; ?>',
            vigentes: '<?php echo $contratos_vigentes; ?>',
            proximos: '<?php echo $contratos_proximos; ?>',
            vencidos: '<?php echo $contratos_vencidos; ?>',
            renovados: '<?php echo $contratos_renovados; ?>',
            sinFecha: '<?php echo $contratos_sin_fecha; ?>'
        };

        let contenidoHTML = `
            <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset="UTF-8">
                <title>Reporte de Clientes</title>
				<link rel="icon" type="image/x-icon" href="assets/logov.png">
                <style>
                    @page Section1 {
                        size: 595.3pt 841.9pt; /* Carta Vertical */
                        margin: 1.0cm 1.0cm 1.0cm 1.0cm;
                        mso-header-margin: 35.4pt;
                        mso-footer-margin: 35.4pt;
                        mso-footer: f1; 
                    }
                    div.Section1 { page: Section1; }
                    body { font-family: 'Arial', sans-serif; font-size: 10pt; }
                    table { border-collapse: collapse; width: 100%; }
                    
                    /* ESTILOS TABLA Y HEADER */
                    .header-table td { border: none; padding: 5px; vertical-align: middle; }
                    
                    .header-titles h1 { color: #0078D4; font-size: 14pt; margin: 0; }
                    .header-titles h2 { color: #333; font-size: 10pt; margin: 2px 0 0 0; font-weight: normal; }
                    
                    .stats-line { font-size: 9pt; padding: 8px 0; border-bottom: 2px solid #0078D4; margin-bottom: 10px; color: #444; }
                    .stat-val { font-weight: bold; }
                    
                    /* Colores Estadísticas */
                    .c-total { color: #000; } .c-activo, .c-vigente { color: #28a745; }
                    .c-proximo { color: #ff9800; } .c-vencido { color: #dc3545; }
                    .c-renovado { color: #17a2b8; } .c-sinfecha { color: #6c757d; }

                    /* TABLA DATOS */
                    .data-table { border: 1px solid #000; font-size: 9pt; width: 100%; table-layout: fixed; }
                    .data-table th { background-color: #0078D4; color: white; border: 1px solid #000; padding: 4px; text-align: center; font-size: 8pt; }
                    .data-table td { border: 1px solid #666; padding: 3px; vertical-align: top; word-wrap: break-word; }
                    
                    .text-center { text-align: center; } .text-bold { font-weight: bold; }
                    
                    /* ESTILO PIE DE PÁGINA */
                    div.MsoFooter { font-family: "Arial", sans-serif; font-size: 9pt; text-align: center; }
                    .footer-line { margin: 2px 0; line-height: 1.1; } /* Espaciado reducido (0.5 aprox visualmente) */
                </style>
            </head>
            <body>
                <div class="Section1">
                
                    <!-- 1. ENCABEZADO -->
                    <table class="header-table">
                        <tr>
                            <!-- SOLUCIÓN LOGO: Atributos width/height directos + style inline -->
                            <td width="45">
                                <img src="${logoBase64}" width="55" height="55" style="width:55px; height:55px;" alt="LogoVisiones">
                            </td>
                            <td>
                                <div class="header-titles">
                                    <h1>REPORTE DE CLIENTES - PDL VISIONES</h1>
                                    <h2>GESTIÓN DE CLIENTES</h2>
                                </div>
                            </td>
                            <td style="text-align: right; font-size: 8pt; color: #555;" width="200">
                                <b>Fecha:</b> ${fechaCompleta}<br>
                                <b>Hora:</b> ${horaFormateada}<br>
                                <b>Usuario:</b> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema'); ?>
                            </td>
                        </tr>
                    </table>

                    <!-- 2. ESTADÍSTICAS -->
                    <div class="stats-line">
                        <span class="c-total">TOTAL: <span class="stat-val">${stats.total}</span></span>, 
                        <span class="c-activo">ACTIVOS: <span class="stat-val">${stats.activos}</span></span>, 
                        <span class="c-vigente">VIGENTES: <span class="stat-val">${stats.vigentes}</span></span>, 
                        <span class="c-proximo">PRÓX: <span class="stat-val">${stats.proximos}</span></span>, 
                        <span class="c-vencido">VENCIDOS: <span class="stat-val">${stats.vencidos}</span></span>, 
                        <span class="c-renovado">RENOV: <span class="stat-val">${stats.renovados}</span></span>, 
                        <span class="c-sinfecha">S/F: <span class="stat-val">${stats.sinFecha}</span></span>
                    </div>

                    ${tablaDatos.tieneFiltros ? `<div style="background:#fff3cd; padding:3px; font-size:9pt; border:1px solid #ffeeba; margin-bottom:5px;">⚠️ Filtros: ${tablaDatos.textoFiltros}</div>` : ''}

                    <!-- 3. TABLA DE DATOS -->
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="5%">COD</th>
                                <th width="19%">CLIENTE</th>
                                <th width="19%">DIRECCIÓN</th>
                                <th width="8%">CONT</th>
                                <th width="12%">RESP.</th>
                                <th width="6%">EST</th>
                                <th width="7%">EST.C</th>
                                <th width="7%">INI</th>
                                <th width="5%">VIG</th>
                                <th width="7%">TER</th>
                                <th width="5%">SUP</th>
                                <th width="7%">FINAL</th>
                            </tr>
                        </thead>
                        <tbody>`;

        tablaDatos.datos.forEach(fila => {
            let colorEstCon = '#000';
            if (fila[6] && (fila[6].includes('Vencido') || fila[6].includes('VENCE'))) colorEstCon = '#dc3545';
            else if (fila[6] && fila[6].includes('Próximo')) colorEstCon = '#ff9800';
            else if (fila[6] && fila[6].includes('Vigente')) colorEstCon = '#28a745';

            contenidoHTML += `
                <tr>
                    <td class="text-center">${fila[0] || ''}</td>
                    <td class="text-bold">${fila[1] || ''}</td>
                    <td>${fila[2] || ''}</td>
                    <td class="text-center">${fila[3] || ''}</td>
                    <td>${fila[4] || ''}</td>
                    <td class="text-center" style="font-size:8pt">${fila[5] ? fila[5].replace('Activo','ACT').replace('Inactivo','INA') : ''}</td>
                    <td class="text-center" style="color:${colorEstCon}; font-weight:bold; font-size:7pt">${fila[6] || ''}</td>
                    <td class="text-center">${fila[7] || ''}</td>
                    <td class="text-center">${fila[8] ? fila[8].replace('años','a').replace('año','a') : ''}</td>
                    <td class="text-center">${fila[9] || ''}</td>
                    <td class="text-center">${fila[10] || ''}</td>
                    <td class="text-center">${fila[11] || ''}</td>
                </tr>`;
        });

        contenidoHTML += `
                        </tbody>
                    </table>
                    
                    <!-- 4. PIE DE PÁGINA (Truco Float) -->
                    <div style="float: left; width: 0px; height: 0px; overflow: hidden;">
                        <div style='mso-element:footer' id="f1">
                            <div class="MsoFooter">
                                <!-- Línea Negra (HR) -->
                                <hr size="1" color="#000000" align="center" style="width:100%; height:1px; margin:0 0 5px 0;">
                                
                                <!-- Contenido con espaciado 0.5 (reducido) -->
                                <p class="footer-line" style="font-weight: bold; font-size: 10pt;">SISFACT PDL VISIONES - LISTADO Y CONTROL DE INFORMACIÓN CONTRACTUAL<br>
								${fechaCompleta} - Hora: ${horaFormateada} - Impreso por Usuario:<b> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema'); ?></b></p>
                                <p class="footer-line" style="margin-top: 5px;">
                                    Página <span style='mso-field-code:" PAGE "'></span> de <span style='mso-field-code:" NUMPAGES "'></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </body>
            </html>`;

        const blob = new Blob(['\ufeff', contenidoHTML], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Reporte_Clientes_${fecha}.doc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        Swal.fire({ icon: 'success', title: 'Exportado', text: 'Reporte generado correctamente.', timer: 2000, showConfirmButton: false });

    } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo exportar.' });
    }
}



function exportarACSV() {
    try {
        const tablaDatos = obtenerDatosTabla(true); // true = con filtros
        const fecha = obtenerFechaActual();
        
        if (tablaDatos.datos.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No hay datos para exportar',
                text: 'No hay clientes que coincidan con los filtros aplicados.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
        // Crear contenido CSV
        let contenidoCSV = 'REPORTE DE CLIENTES - SISFACT PDL VISIONES\n';
        contenidoCSV += `Fecha de generación: ${fecha.replace('_', ' ')}\n`;
        contenidoCSV += `Usuario: <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema'); ?>\n`;
        contenidoCSV += `Mostrando: ${tablaDatos.totalFiltrado} de ${tablaDatos.totalGeneral} clientes\n`;
        
        if (tablaDatos.tieneFiltros) {
            contenidoCSV += `Filtros aplicados: ${tablaDatos.textoFiltros}\n`;
        }
        
        contenidoCSV += '\n';
        contenidoCSV += 'ESTADÍSTICAS\n';
        contenidoCSV += `Total de clientes,<?php echo $total_contratos; ?>\n`;
        contenidoCSV += `Clientes activos,<?php echo $contratos_activos; ?>\n`;
        contenidoCSV += `Contratos vigentes,<?php echo $contratos_vigentes; ?>\n`;
        contenidoCSV += `Contratos vencidos,<?php echo $contratos_vencidos; ?>\n`;
        contenidoCSV += `Próximos a vencer,<?php echo $contratos_proximos; ?>\n`;
        contenidoCSV += `Contratos renovados,<?php echo $contratos_renovados; ?>\n`;
        contenidoCSV += `Contratos sin fecha,<?php echo $contratos_sin_fecha; ?>\n`;
        contenidoCSV += '\n';
        
        // Agregar encabezados
        contenidoCSV += tablaDatos.encabezados.join(',') + '\n';
        
        // Agregar datos
        tablaDatos.datos.forEach(fila => {
            const filaEscapada = fila.map(celda => {
                // Escapar comas, comillas y saltos de línea
                if (typeof celda === 'string') {
                    // Reemplazar saltos de línea por espacio
                    celda = celda.replace(/(\r\n|\n|\r)/gm, ' ');
                    
                    // Si la celda contiene comas, comillas o saltos de línea, encerrar en comillas
                    if (celda.includes(',') || celda.includes('"') || celda.includes('\n')) {
                        // Escapar comillas dobles
                        celda = celda.replace(/"/g, '""');
                        return `"${celda}"`;
                    }
                }
                return celda || '';
            });
            contenidoCSV += filaEscapada.join(',') + '\n';
        });
        
        // Agregar pie de información
        contenidoCSV += '\n';
        contenidoCSV += 'INFORMACIÓN ADICIONAL\n';
        contenidoCSV += 'Sistema,SISFACT PDL Visiones\n';
        contenidoCSV += 'Módulo,Gestión de Clientes\n';
        contenidoCSV += `Registros exportados,${tablaDatos.totalFiltrado}\n`;
        contenidoCSV += `Fecha exportación,${fecha.replace('_', ' ')}\n`;
        contenidoCSV += 'Formato,CSV (Valores separados por comas)\n';
        contenidoCSV += 'Codificación,UTF-8\n';
        
        // Crear blob y descargar
        const blob = new Blob(['\ufeff', contenidoCSV], {
            type: 'text/csv;charset=utf-8;'
        });
        
        // Crear enlace de descarga
        const link = document.createElement('a');
        const nombreArchivo = tablaDatos.tieneFiltros 
            ? `Clientes_Filtrados_PDL_Visiones_${fecha}.csv`
            : `Clientes_PDL_Visiones_${fecha}.csv`;
        
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Mostrar notificación
        Swal.fire({
            icon: 'success',
            title: 'CSV Exportado',
            text: `Se exportaron ${tablaDatos.totalFiltrado} registros en formato CSV.`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportarACSV:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al exportar CSV',
            text: 'Ocurrió un error al generar el archivo CSV.',
            confirmButtonText: 'Entendido'
        });
    }
}
// Función para formatear fecha actual
function obtenerFechaActual() {
    const fecha = new Date();
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const anio = fecha.getFullYear();
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    
    return `${dia}-${mes}-${anio}_${horas}${minutos}`;
}


// ============================================
// EXPORTAR A TXT - FORMATO LEGIBLE
// ============================================
function exportarATXT() {
    try {
        // 1. Obtener datos con filtros aplicados
        const tablaDatos = obtenerDatosTabla(true);
        const filasFiltradas = tablaDatos.datos;
        const totalFiltrado = tablaDatos.totalFiltrado;
        const totalGeneral = tablaDatos.totalGeneral;
        const textoFiltros = tablaDatos.textoFiltros;
        const tieneFiltros = tablaDatos.tieneFiltros;
        const encabezados = tablaDatos.encabezados;
        
        if (filasFiltradas.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No hay datos para exportar',
                text: 'No hay clientes que coincidan con los filtros aplicados.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        // 2. Fecha y hora actual para el encabezado y nombre de archivo
        const fecha = new Date();
        const fechaFormateada = fecha.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const horaFormateada = fecha.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        // 3. Generar nombre de archivo con formato legible
        const año = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        const horas = String(fecha.getHours()).padStart(2, '0');
        const minutos = String(fecha.getMinutes()).padStart(2, '0');
        const segundos = String(fecha.getSeconds()).padStart(2, '0');
        
        const fechaArchivo = `${año}${mes}${dia}_${horas}${minutos}${segundos}`;
        const nombreArchivo = tieneFiltros 
            ? `Clientes_Filtrados_${fechaArchivo}.txt`
            : `Clientes_Completo_${fechaArchivo}.txt`;

        // 4. Obtener estadísticas desde PHP
        const totalContratos = Number(<?php echo $total_contratos ?? 0; ?>);
        const contratosActivos = Number(<?php echo $contratos_activos ?? 0; ?>);
        const contratosVigentes = Number(<?php echo $contratos_vigentes ?? 0; ?>);
        const contratosVencidos = Number(<?php echo $contratos_vencidos ?? 0; ?>);
        const contratosProximos = Number(<?php echo $contratos_proximos ?? 0; ?>);
        const contratosSinFecha = Number(<?php echo $contratos_sin_fecha ?? 0; ?>);
        const contratosRenovados = Number(<?php echo $contratos_renovados ?? 0; ?>);
        const usuarioNombre = "<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISFACT PDL VISIONES'); ?>";

        // 5. CONSTRUIR CONTENIDO DEL ARCHIVO TXT
        let contenido = '';
        
        // ===== LÍNEA 1: TÍTULO PRINCIPAL =====
        contenido += '='.repeat(120) + '\n';
        contenido += 'REPORTE DE CLIENTES Y CONTRATOS - SISFACT PDL VISIONES\n';
        contenido += '='.repeat(120) + '\n\n';
        
        // ===== INFORMACIÓN DEL REPORTE =====
        contenido += '┌' + '─'.repeat(118) + '┐\n';
        contenido += '│ INFORME DE GESTIÓN DE CLIENTES Y CONTRATOS' + ' '.repeat(65) + '│\n';
        contenido += '├' + '─'.repeat(118) + '┤\n';
        contenido += `│ Fecha de generación: ${fechaFormateada} - ${horaFormateada}` + ' '.repeat(58) + '│\n';
        contenido += `│ Usuario: ${usuarioNombre}` + ' '.repeat(98) + '│\n';
        contenido += `│ Total general de contratos: ${totalGeneral}` + ' '.repeat(90) + '│\n';
        contenido += `│ Registros en este reporte: ${totalFiltrado} (${((totalFiltrado/totalGeneral)*100).toFixed(1)}% del total)` + ' '.repeat(45) + '│\n';
        
        if (tieneFiltros) {
            contenido += `│ Filtros aplicados: ${textoFiltros}` + ' '.repeat(92 - textoFiltros.length) + '│\n';
        }
        contenido += '└' + '─'.repeat(118) + '┘\n\n';
        
        // ===== ESTADÍSTICAS GENERALES =====
        contenido += '┌' + '─'.repeat(118) + '┐\n';
        contenido += '│ 📊 ESTADÍSTICAS GENERALES DE CONTRATACIÓN' + ' '.repeat(70) + '│\n';
        contenido += '├' + '─'.repeat(118) + '┤\n';
        
        // Fila 1 de estadísticas
        contenido += `│ Total Contratos: ${totalContratos.toString().padStart(8)}`;
        contenido += `   Activos: ${contratosActivos.toString().padStart(8)}`;
        contenido += `   Vigentes: ${contratosVigentes.toString().padStart(8)}`;
        contenido += `   Vencidos: ${contratosVencidos.toString().padStart(8)}` + ' '.repeat(10) + '│\n';
        
        // Fila 2 de estadísticas
        contenido += `│ Próximos a vencer: ${contratosProximos.toString().padStart(8)}`;
        contenido += `   Sin Fecha: ${contratosSinFecha.toString().padStart(8)}`;
        contenido += `   Renovados: ${contratosRenovados.toString().padStart(8)}`;
        contenido += `   % Actividad: ${((contratosActivos/totalContratos)*100).toFixed(1).padStart(6)}%` + ' '.repeat(15) + '│\n';
        contenido += '└' + '─'.repeat(118) + '┘\n\n';
        
        // ===== TABLA DE DATOS =====
        contenido += '┌' + '─'.repeat(118) + '┐\n';
        contenido += '│ 📋 LISTADO DETALLADO DE CONTRATOS' + ' '.repeat(79) + '│\n';
        contenido += '├' + '─'.repeat(118) + '┤\n';
        
        // ===== ENCABEZADOS DE TABLA =====
        // Formatear encabezados para que coincidan con el ancho
        const headerFormat = [
            'Código'.padEnd(8),
            'Cliente'.padEnd(22),
            'Dirección'.padEnd(25),
            'No.Contrato'.padEnd(12),
            'Responsable'.padEnd(18),
            'Est.Cli'.padEnd(8),
            'Est.Cont'.padEnd(10),
            'F.Inicio'.padEnd(10),
            'Vig'.padEnd(5),
            'F.Término'.padEnd(10),
            'Sup'.padEnd(5),
            'F.Final'.padEnd(10)
        ];
        
        contenido += '│ ' + headerFormat.join(' │ ') + ' │\n';
        contenido += '├' + '─'.repeat(8) + '┼' + '─'.repeat(22) + '┼' + '─'.repeat(25) + '┼' + 
                    '─'.repeat(12) + '┼' + '─'.repeat(18) + '┼' + '─'.repeat(8) + '┼' + 
                    '─'.repeat(10) + '┼' + '─'.repeat(10) + '┼' + '─'.repeat(5) + '┼' + 
                    '─'.repeat(10) + '┼' + '─'.repeat(5) + '┼' + '─'.repeat(10) + '┤\n';
        
        // ===== DATOS DE LA TABLA =====
        filasFiltradas.forEach((fila, index) => {
            // Limpiar y formatear cada campo
            const codigo = (fila[0] || '-').padEnd(8);
            const cliente = (fila[1] || '-').substring(0, 21).padEnd(22);
            const direccion = (fila[2] || 'S/R').substring(0, 24).padEnd(25);
            const contratoNo = (fila[3] || '-').padEnd(12);
            const responsable = (fila[4] || '-').substring(0, 17).padEnd(18);
            
            // Limpiar estados
            let estCli = fila[5] || '-';
            estCli = estCli.replace('Activo', 'ACT').replace('Inactivo', 'INA').substring(0, 7).padEnd(8);
            
            let estCon = fila[6] || '-';
            estCon = estCon.replace('Vigente', 'VIG')
                          .replace('Vencido', 'VEN')
                          .replace('Próximo', 'PROX')
                          .replace('Sin Fecha', 'S/F')
                          .substring(0, 9).padEnd(10);
            
            const fInicio = (fila[7] || '-').padEnd(10);
            const vigencia = (fila[8] || '-').replace('años', 'a').replace('año', 'a').padEnd(5);
            const fTermino = (fila[9] || '-').padEnd(10);
            const suplem = (fila[10] || '-').padEnd(5);
            const fFinal = (fila[11] || '-').padEnd(10);
            
            contenido += `│ ${codigo} │ ${cliente} │ ${direccion} │ ${contratoNo} │ ${responsable} │ ${estCli} │ ${estCon} │ ${fInicio} │ ${vigencia} │ ${fTermino} │ ${suplem} │ ${fFinal} │\n`;
            
            // Línea separadora cada 10 registros para mejor legibilidad
            if ((index + 1) % 10 === 0 && index < filasFiltradas.length - 1) {
                contenido += '├' + '─'.repeat(8) + '┼' + '─'.repeat(22) + '┼' + '─'.repeat(25) + '┼' + 
                            '─'.repeat(12) + '┼' + '─'.repeat(18) + '┼' + '─'.repeat(8) + '┼' + 
                            '─'.repeat(10) + '┼' + '─'.repeat(10) + '┼' + '─'.repeat(5) + '┼' + 
                            '─'.repeat(10) + '┼' + '─'.repeat(5) + '┼' + '─'.repeat(10) + '┤\n';
            }
        });
        
        contenido += '└' + '─'.repeat(8) + '┴' + '─'.repeat(22) + '┴' + '─'.repeat(25) + '┴' + 
                    '─'.repeat(12) + '┴' + '─'.repeat(18) + '┴' + '─'.repeat(8) + '┴' + 
                    '─'.repeat(10) + '┴' + '─'.repeat(10) + '┴' + '─'.repeat(5) + '┴' + 
                    '─'.repeat(10) + '┴' + '─'.repeat(5) + '┴' + '─'.repeat(10) + '┘\n\n';
        
        // ===== RESUMEN FINAL =====
        contenido += '┌' + '─'.repeat(118) + '┐\n';
        contenido += '│ 📌 RESUMEN DEL REPORTE' + ' '.repeat(89) + '│\n';
        contenido += '├' + '─'.repeat(118) + '┤\n';
        contenido += `│ • Total de registros en este reporte: ${totalFiltrado} contratos` + ' '.repeat(67) + '│\n';
        contenido += `│ • Rango mostrado: Registros 1 al ${totalFiltrado}` + ' '.repeat(69) + '│\n';
        contenido += `│ • Fecha de corte: ${fechaFormateada}` + ' '.repeat(83) + '│\n';
        contenido += '│' + ' '.repeat(117) + '│\n';
        contenido += '│ Generado por: ' + usuarioNombre + ' '.repeat(98 - usuarioNombre.length) + '│\n';
        contenido += '│ Sistema SISFACT PDL Visiones - Módulo de Gestión de Clientes' + ' '.repeat(51) + '│\n';
        contenido += '└' + '─'.repeat(118) + '┘\n\n';
        
        // ===== PIE DE PÁGINA =====
        contenido += '='.repeat(120) + '\n';
        contenido += 'FIN DEL REPORTE - SISFACT PDL VISIONES\n';
        contenido += '='.repeat(120) + '\n';

        // 6. Crear blob y descargar archivo
        const blob = new Blob(['\ufeff' + contenido], { 
            type: 'text/plain;charset=utf-8;' 
        });
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);

        // 7. Mostrar notificación de éxito
        Swal.fire({
            icon: 'success',
            title: 'TXT Exportado',
            html: `<strong>${nombreArchivo}</strong><br>
                   Se exportaron ${totalFiltrado} registros en formato TXT.<br>
                   <span style="font-size: 12px;">✅ Formato de tabla con bordes y estadísticas</span>`,
            timer: 3000,
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });

    } catch (error) {
        console.error('Error en exportarATXT:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al exportar TXT',
            text: 'Ocurrió un error al generar el archivo TXT: ' + error.message,
            confirmButtonText: 'Entendido'
        });
    }
}
// Función para mostrar alerta de sin permisos (Sin animaciones - Estilo Dark)
function mostrarAlertaSinPermisos(accion) {
    let titulo = '';
    let texto = '';
    let icono = 'warning';
    let botonTexto = 'Entendido';
    
    switch(accion) {
        case 'editar':
            titulo = '⚠️ Acción no permitida';
            texto = 'No tienes permisos para <strong>editar</strong> clientes.<br><br>' +
                    'Tu cuenta está configurada en <strong>modo solo lectura</strong>.<br>' +
                    'Contacta al administrador si necesitas permisos de edición.';
            break;
        case 'eliminar':
            titulo = '🚫 Acción no permitida';
            texto = 'No tienes permisos para <strong>eliminar</strong> clientes.<br><br>' +
                    'Tu cuenta está configurada en <strong>modo solo lectura</strong>.<br>' +
                    'Contacta al administrador si necesitas permisos de eliminación.';
            break;
        case 'crear':
            titulo = '📝 Acción no permitida';
            texto = 'No tienes permisos para <strong>crear</strong> nuevos registros.<br><br>' +
                    'Tu cuenta está configurada en <strong>modo solo lectura</strong>.<br>' +
                    'Contacta al administrador si necesitas permisos de escritura.';
            break;
        default:
            titulo = '🔒 Acción restringida';
            texto = 'No tienes permisos suficientes para realizar esta acción.<br><br>' +
                    'Tu cuenta está configurada en <strong>modo solo lectura</strong>.';
            break;
    }
    
    Swal.fire({
        title: titulo,
        html: texto,
        icon: icono,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-check me-2"></i>' + botonTexto,
        background: '#1f1f1f',           // Fondo oscuro consistente con Windows 11 dark
        color: '#ffffff',                 // Texto blanco
        iconColor: '#ffc107',             // Color del ícono de advertencia
        backdrop: 'rgba(0, 0, 0, 0.9)',   // Fondo más oscuro
        allowOutsideClick: true,          // Permitir cerrar haciendo clic fuera
        allowEscapeKey: true,             // Permitir cerrar con ESC
        showConfirmButton: true,
        showCancelButton: false,
        timer: null,
        // SIN ANIMACIONES - Eliminadas las propiedades showClass y hideClass
        customClass: {
            container: 'swal-dark-container',
            popup: 'swal-dark-popup',
            title: 'swal-dark-title',
            htmlContainer: 'swal-dark-html',
            confirmButton: 'swal-dark-confirm'
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