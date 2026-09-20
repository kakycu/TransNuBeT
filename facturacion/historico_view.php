<?php
// historico_view.php - Página principal del histórico
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir funciones del histórico
require_once 'historico.php';

// ==================== INICIALIZACIÓN DE VARIABLES POR DEFECTO ====================
$total_paginas = 1;
$total_registros = 0;
$historico = [];
$tipos_operacion = [];
$usuarios = [];
$estadisticas = ['total' => 0, 'hoy' => 0, 'usuarios_activos' => 0, 'tipos_unicos' => 0];
$error_historico = null;
$usuario = [];
$permiso_borrar = false;
$es_supervisor = false;
$total_facturas = 0;
$total_clientes = 0;
$total_categorias = 0;
$total_servicios = 0;
$total_usuarios = 0;
$finanzas = ['real' => 0, 'meta' => 0, 'porcentaje' => 0, 'color' => '#0078d4', 'mensaje' => 'Sin datos', 'cantidad' => 0];

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// ==================== CONFIGURACIÓN DE PAGINACIÓN (CORREGIDA) ====================
$registros_por_pagina = isset($_GET['registros_por_pagina']) 
    ? (int)$_GET['registros_por_pagina'] 
    : (isset($_SESSION['registros_por_pagina']) 
        ? (int)$_SESSION['registros_por_pagina'] 
        : 20);

$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

// Guardar preferencia de registros por página en sesión (como entero)
$_SESSION['registros_por_pagina'] = $registros_por_pagina;

// Opciones de registros por página
$opciones_registros = [10, 20, 50, 100, 200];

// ==================== CONFIGURACIÓN DE TEMAS ====================
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

$tema_actual = $temas_windows[$tema_windows] ?? $temas_windows['dark'];

// ==================== LÓGICA DE FECHAS OPERATIVAS ====================
$mes_cierre_num = obtenerMesCierreOperaciones(); // Retorna 1-12
$anio_cierre_num = obtenerAnioCierreOperaciones(); // Retorna YYYY
$dia_actual_real = (int)date('j');

// Calcular el PRIMER DÍA del mes operativo
$primer_dia_operativo = sprintf("%04d-%02d-01", $anio_cierre_num, $mes_cierre_num);

// Calcular el ÚLTIMO DÍA del mes operativo
$dias_del_mes = cal_days_in_month(CAL_GREGORIAN, $mes_cierre_num, $anio_cierre_num);
$ultimo_dia_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dias_del_mes);

// Filtros por defecto
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? $primer_dia_operativo;
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? $ultimo_dia_operativo;
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Variables para usuario filtrado
$estado_usuario = '';
$nombre_usuario_filtrado = '';

// Variable para determinar si es una exportación
$es_exportacion = isset($_GET['exportar']) && in_array($_GET['exportar'], ['excel', 'word', 'pdf', 'csv']);

// ==================== LÓGICA DE ORDENAMIENTO ====================
$columnas_permitidas = ['id', 'fecha_hora', 'operacion', 'usuario_nombre', 'descripcion', 'ip_address'];
$columna_orden = $_GET['columna'] ?? 'fecha_hora';
$direccion_orden = $_GET['orden'] ?? 'DESC';

if (!in_array($columna_orden, $columnas_permitidas)) {
    $columna_orden = 'fecha_hora';
}
$direccion_orden = strtoupper($direccion_orden) === 'ASC' ? 'ASC' : 'DESC';

// Mapeo de columnas para SQL - CORREGIDO
$mapa_sql = [
    'id' => 'h.id',
    'fecha_hora' => 'h.fecha_hora',
    'operacion' => 'h.operacion',
    'usuario_nombre' => 'u.usuario', // Cambiado: antes era 'usuario_login'
    'descripcion' => 'h.descripcion',
    'ip_address' => 'h.ip_address'
];
$sql_order_by = $mapa_sql[$columna_orden] . " " . $direccion_orden;

// ==================== PROCESAMIENTO PRINCIPAL ====================
try {
    $db = Database::getConnection();
    
    // Obtener información del usuario actual
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre,
                    r.codigo as rol_codigo, u.rol_id
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }

    // --- PERMISOS DE BORRADO ---
    if (isset($usuario['rol_id'])) {
        if ($usuario['rol_id'] == 1 || $usuario['rol_id'] == 4) {
            $permiso_borrar = true;
        }
        if ($usuario['rol_id'] == 4) {
            $es_supervisor = true;
        }
    }
    
    // Construir consulta con filtros
// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_usuario)) {
    $where_conditions[] = "h.usuario_id = :usuario_id";
    $params[':usuario_id'] = $filtro_usuario;
    
    // Obtener información del usuario filtrado
    $sql_usuario_filtrado = "SELECT CONCAT(nombre, ' ', apellidos) as nombre_completo, 
                                     usuario, activo 
                              FROM clasif_usuarios 
                              WHERE id = :id";
    $stmt_usuario_filtrado = $db->prepare($sql_usuario_filtrado);
    $stmt_usuario_filtrado->execute(['id' => $filtro_usuario]);
    $usuario_filtrado = $stmt_usuario_filtrado->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario_filtrado) {
        $nombre_usuario_filtrado = $usuario_filtrado['nombre_completo'];
        $estado_usuario = ($usuario_filtrado['activo'] == 1) ? 'ACTIVO' : 'INACTIVO';
    }
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "h.operacion = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(h.fecha_hora) >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(h.fecha_hora) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}

// ============ FILTRO DE BÚSQUEDA CORREGIDO ============
if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(h.operacion LIKE :busqueda_operacion 
                          OR h.descripcion LIKE :busqueda_descripcion 
                          OR u.usuario LIKE :busqueda_usuario
                          OR CONCAT(u.nombre, ' ', u.apellidos) LIKE :busqueda_nombre_completo)";
    $params[':busqueda_operacion'] = "%$filtro_busqueda%";
    $params[':busqueda_descripcion'] = "%$filtro_busqueda%";
    $params[':busqueda_usuario'] = "%$filtro_busqueda%";
    $params[':busqueda_nombre_completo'] = "%$filtro_busqueda%";
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener total de registros
$sql_count = "SELECT COUNT(*) as total 
              FROM historico_operaciones h
              LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
              $where_sql";

$stmt = $db->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$resultado_count = $stmt->fetch(PDO::FETCH_ASSOC);
$total_registros = isset($resultado_count['total']) ? (int)$resultado_count['total'] : 0;

// Calcular total de páginas
if ($total_registros > 0 && $registros_por_pagina > 0) {
    $total_paginas = ceil($total_registros / $registros_por_pagina);
} else {
    $total_paginas = 1;
}

// Asegurar que la página actual esté dentro del rango válido
$pagina_actual = max(1, min($pagina_actual, $total_paginas));

// Calcular offset para paginación
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Si es exportación, obtener TODOS los registros sin paginación
if ($es_exportacion) {
    $sql_historico = "SELECT h.id, h.fecha_hora, h.operacion, h.descripcion, h.ip_address, 
                             h.usuario_id, u.usuario as usuario_login,
                             CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                      FROM historico_operaciones h
                      LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
                      $where_sql
                      ORDER BY $sql_order_by";
    
    $stmt = $db->prepare($sql_historico);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Obtener registros paginados para vista normal
    $sql_historico = "SELECT h.id, h.fecha_hora, h.operacion, h.descripcion, h.ip_address, 
                             h.usuario_id, u.usuario as usuario_login,
                             CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                      FROM historico_operaciones h
                      LEFT JOIN clasif_usuarios u ON h.usuario_id = u.id
                      $where_sql
                      ORDER BY $sql_order_by
                      LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql_historico);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
}    
    // Obtener tipos de operaciones únicas para filtro
    $tipos_operacion = obtenerTiposOperacion($db);
    
    // Obtener usuarios para filtro
    $sql_usuarios = "SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo, usuario 
                     FROM clasif_usuarios 
                     ORDER BY nombre";
    $stmt = $db->query($sql_usuarios);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Obtener estadísticas del histórico
    $estadisticas = obtenerEstadisticasHistorial($db);
    
    // Obtener datos globales de facturación (CUP)
    if (method_exists('Database', 'getProgresoFinanciero')) {
        $finanzas = Database::getProgresoFinanciero();
    }
    
    // Procesar exportación si es necesario
    if ($es_exportacion) {
        $tipo_exportacion = $_GET['exportar'];
        exportarHistorico($historico, $tipo_exportacion, [
            'filtro_usuario' => $filtro_usuario,
            'filtro_tipo' => $filtro_tipo,
            'filtro_fecha_desde' => $filtro_fecha_desde,
            'filtro_fecha_hasta' => $filtro_fecha_hasta,
            'filtro_busqueda' => $filtro_busqueda,
            'total_registros' => $total_registros,
            'usuario_actual' => $_SESSION['usuario_nombre'] ?? 'Sistema'
        ]);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error al cargar histórico: " . $e->getMessage());
    $error_historico = "Error al cargar el histórico de actividades. Por favor, intente nuevamente.";
    // Las variables ya están inicializadas con valores por defecto
}

// ==================== OBTENER MES ACTUAL EN ESPAÑOL ====================
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Avatar placeholder
$avatar_placeholder = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));

// ==================== FUNCIONES ====================

// Función para generar encabezados ordenables
function crearEncabezadoOrdenable($texto, $columna_clave, $col_actual, $dir_actual) {
    $nueva_dir = ($columna_clave === $col_actual && $dir_actual === 'DESC') ? 'ASC' : 'DESC';
    
    $params = $_GET;
    $params['columna'] = $columna_clave;
    $params['orden'] = $nueva_dir;
    $params['pagina'] = 1; 
    
    $url = '?' . http_build_query($params);
    
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

// Función para exportar histórico
function exportarHistorico($datos, $tipo, $configuracion = []) {
    $fecha_exportacion = date('Y-m-d_H-i-s');
    $nombre_archivo = "historico_actividades_{$fecha_exportacion}";
    
    switch ($tipo) {
        case 'excel':
            exportarExcel($datos, $nombre_archivo, $configuracion);
            break;
        case 'word':
            exportarWord($datos, $nombre_archivo, $configuracion);
            break;
        case 'pdf':
            exportarPDF($datos, $nombre_archivo, $configuracion);
            break;
        case 'csv':
            exportarCSV($datos, $nombre_archivo, $configuracion);
            break;
    }
}

// Función para exportar a Excel
function exportarExcel($datos, $nombre_archivo, $configuracion) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #0078d4; color: white; padding: 8px; border: 1px solid #ddd; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo '.titulo { font-size: 16px; font-weight: bold; margin-bottom: 10px; }';
    echo '.subtitulo { font-size: 14px; margin-bottom: 5px; }';
    echo '.info { font-size: 12px; color: #666; margin-bottom: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="titulo">Histórico de Actividades - PDL Visiones</div>';
    echo '<div class="subtitulo">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</div>';
    echo '<div class="subtitulo">Exportado por: ' . ($configuracion['usuario_actual'] ?? 'Sistema') . '</div>';
    
    if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
        !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
        echo '<div class="info">';
        echo '<strong>Filtros aplicados:</strong><br>';
        if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
        if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
        if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
        if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
        if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
        echo '</div>';
    }
    
    echo '<div class="info">Total de registros: ' . ($configuracion['total_registros'] ?? 0) . '</div>';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Id</th>';
    echo '<th>Fecha/Hora</th>';
    echo '<th>Tipo</th>';
    echo '<th>Usuario</th>';
    echo '<th>Descripción</th>';
    echo '<th>IP</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $contador = 1;
    foreach ($datos as $registro) {
        echo '<tr>';
        echo '<td>' . $contador++ . '</td>';
        echo '<td>' . htmlspecialchars($registro['id'] ?? '') . '</td>';
        echo '<td>' . (isset($registro['fecha_hora']) ? date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) : '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['operacion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
        echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

// Función para exportar a Word
function exportarWord($datos, $nombre_archivo, $configuracion) {
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.doc"');
    header('Cache-Control: max-age=0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #0078d4; color: white; padding: 8px; border: 1px solid #ddd; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo '.titulo { font-size: 16px; font-weight: bold; margin-bottom: 10px; }';
    echo '.subtitulo { font-size: 14px; margin-bottom: 5px; }';
    echo '.info { font-size: 12px; color: #666; margin-bottom: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="titulo">Histórico de Actividades - PDL Visiones</div>';
    echo '<div class="subtitulo">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</div>';
    echo '<div class="subtitulo">Exportado por: ' . ($configuracion['usuario_actual'] ?? 'Sistema') . '</div>';
    
    if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
        !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
        echo '<div class="info">';
        echo '<strong>Filtros aplicados:</strong><br>';
        if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
        if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
        if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
        if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
        if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
        echo '</div>';
    }
    
    echo '<div class="info">Total de registros: ' . ($configuracion['total_registros'] ?? 0) . '</div>';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Id</th>';
    echo '<th>Fecha/Hora</th>';
    echo '<th>Tipo</th>';
    echo '<th>Usuario</th>';
    echo '<th>Descripción</th>';
    echo '<th>IP</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $contador = 1;
    foreach ($datos as $registro) {
        echo '<tr>';
        echo '<td>' . $contador++ . '</td>';
        echo '<td>' . htmlspecialchars($registro['id'] ?? '') . '</td>';
        echo '<td>' . (isset($registro['fecha_hora']) ? date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) : '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['operacion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
        echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

// Función para exportar a PDF
function exportarPDF($datos, $nombre_archivo, $configuracion) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.pdf"');
    header('Cache-Control: max-age=0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo '@media print {';
    echo '  body { font-family: Arial, sans-serif; margin: 20px; }';
    echo '  table { border-collapse: collapse; width: 100%; font-size: 10px; }';
    echo '  th { background-color: #0078d4; color: white; padding: 6px; border: 1px solid #ddd; }';
    echo '  td { padding: 6px; border: 1px solid #ddd; }';
    echo '  .titulo { font-size: 14px; font-weight: bold; margin-bottom: 8px; }';
    echo '  .subtitulo { font-size: 12px; margin-bottom: 4px; }';
    echo '  .info { font-size: 10px; color: #666; margin-bottom: 15px; }';
    echo '  .page-break { page-break-after: always; }';
    echo '}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="titulo">Histórico de Actividades - PDL Visiones</div>';
    echo '<div class="subtitulo">Fecha de exportación: ' . date('d/m/Y H:i:s') . '</div>';
    echo '<div class="subtitulo">Exportado por: ' . ($configuracion['usuario_actual'] ?? 'Sistema') . '</div>';
    
    if (!empty($configuracion['filtro_usuario']) || !empty($configuracion['filtro_tipo']) || 
        !empty($configuracion['filtro_fecha_desde']) || !empty($configuracion['filtro_busqueda'])) {
        echo '<div class="info">';
        echo '<strong>Filtros aplicados:</strong><br>';
        if (!empty($configuracion['filtro_usuario'])) echo '- Usuario ID: ' . $configuracion['filtro_usuario'] . '<br>';
        if (!empty($configuracion['filtro_tipo'])) echo '- Tipo: ' . $configuracion['filtro_tipo'] . '<br>';
        if (!empty($configuracion['filtro_fecha_desde'])) echo '- Desde: ' . $configuracion['filtro_fecha_desde'] . '<br>';
        if (!empty($configuracion['filtro_fecha_hasta'])) echo '- Hasta: ' . $configuracion['filtro_fecha_hasta'] . '<br>';
        if (!empty($configuracion['filtro_busqueda'])) echo '- Búsqueda: ' . $configuracion['filtro_busqueda'] . '<br>';
        echo '</div>';
    }
    
    echo '<div class="info">Total de registros: ' . ($configuracion['total_registros'] ?? 0) . '</div>';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th width="30">#</th>';
    echo '<th width="30">Id</th>';
    echo '<th width="80">Fecha/Hora</th>';
    echo '<th width="80">Tipo</th>';
    echo '<th width="80">Usuario</th>';
    echo '<th width="150">Descripción</th>';
    echo '<th width="80">IP</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $contador = 1;
    $registros_por_pagina_pdf = 30;
    $total_registros = count($datos);
    
    foreach ($datos as $index => $registro) {
        echo '<tr>';
        echo '<td>' . $contador++ . '</td>';
        echo '<td>' . htmlspecialchars($registro['id'] ?? '') . '</td>';
        echo '<td>' . (isset($registro['fecha_hora']) ? date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) : '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['operacion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema') . '</td>';
        echo '<td>' . htmlspecialchars($registro['descripcion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($registro['ip_address'] ?? '') . '</td>';
        echo '</tr>';
        
        if ($index > 0 && $index % $registros_por_pagina_pdf == 0 && $index < $total_registros - 1) {
            echo '</tbody></table>';
            echo '<div class="page-break"></div>';
            echo '<div class="titulo">Histórico de Actividades - PDL Visiones (Continuación)</div>';
            echo '<div class="subtitulo">Página ' . (($index / $registros_por_pagina_pdf) + 1) . ' de ' . ceil($total_registros / $registros_por_pagina_pdf) . '</div>';
            echo '<table border="1">';
            echo '<thead>';
            echo '<tr>';
            echo '<th width="30">#</th>';
            echo '<th width="30">Id</th>';
            echo '<th width="80">Fecha/Hora</th>';
            echo '<th width="80">Tipo</th>';
            echo '<th width="80">Usuario</th>';
            echo '<th width="150">Descripción</th>';
            echo '<th width="80">IP</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

// Función para exportar a CSV
function exportarCSV($datos, $nombre_archivo, $configuracion) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    fwrite($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['Histórico de Actividades - PDL Visiones']);
    fputcsv($output, ['Fecha de exportación:', date('d/m/Y H:i:s')]);
    fputcsv($output, ['Exportado por:', $configuracion['usuario_actual'] ?? 'Sistema']);
    fputcsv($output, ['Total de registros:', $configuracion['total_registros'] ?? 0]);
    fputcsv($output, []);
    
    $filtros = ['Filtros aplicados:'];
    if (!empty($configuracion['filtro_usuario'])) $filtros[] = 'Usuario ID: ' . $configuracion['filtro_usuario'];
    if (!empty($configuracion['filtro_tipo'])) $filtros[] = 'Tipo: ' . $configuracion['filtro_tipo'];
    if (!empty($configuracion['filtro_fecha_desde'])) $filtros[] = 'Desde: ' . $configuracion['filtro_fecha_desde'];
    if (!empty($configuracion['filtro_fecha_hasta'])) $filtros[] = 'Hasta: ' . $configuracion['filtro_fecha_hasta'];
    if (!empty($configuracion['filtro_busqueda'])) $filtros[] = 'Búsqueda: ' . $configuracion['filtro_busqueda'];
    fputcsv($output, $filtros);
    fputcsv($output, []);
    
    fputcsv($output, ['#', 'Id', 'Fecha/Hora', 'Tipo', 'Usuario', 'Descripción', 'IP']);
    
    $contador = 1;
    foreach ($datos as $registro) {
        $fila = [
            $contador++,
            $registro['id'] ?? '',
            isset($registro['fecha_hora']) ? date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) : '',
            $registro['operacion'] ?? '',
            $registro['usuario_nombre'] ?? 'Sistema',
            $registro['descripcion'] ?? '',
            $registro['ip_address'] ?? ''
        ];
        fputcsv($output, $fila);
    }
    
    fclose($output);
}

// Función para obtener icono de tipo (si existe)
if (!function_exists('obtenerIconoTipo')) {
    function obtenerIconoTipo($operacion) {
        $iconos = [
            'login' => 'fa-sign-in-alt',
            'logout' => 'fa-sign-out-alt',
            'crear' => 'fa-plus-circle',
            'editar' => 'fa-edit',
            'eliminar' => 'fa-trash-alt',
            'factura' => 'fa-file-invoice',
            'cliente' => 'fa-user',
            'servicio' => 'fa-cog',
            'configuracion' => 'fa-cogs',
            'perfil' => 'fa-id-card',
            'password' => 'fa-key'
        ];
        
        foreach ($iconos as $clave => $icono) {
            if (stripos($operacion, $clave) !== false) {
                return $icono;
            }
        }
        
        return 'fa-circle';
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Actividades - SISFACT PDL Visiones</title>
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
            color: var(--win-text-secondary);
            font-size: 14px;
        }

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

        .win-main-content .pagination .page-link {
            background-color: var(--win-bg-tertiary) !important;
            border-color: var(--win-border-color) !important;
            color: var(--win-text-primary) !important;
        }

        .win-main-content .pagination .page-item.active .page-link {
            background-color: var(--win-accent) !important;
            border-color: var(--win-accent) !important;
            color: white !important;
        }

        .win-main-content .pagination .page-link:hover {
            background-color: var(--win-bg-tertiary) !important;
            color: var(--win-accent) !important;
        }

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

        .win-quick-action.action-delete-all {
            background: #dc3545 !important;
        }

        .win-quick-action.action-delete-all:hover {
            background: #c82333 !important;
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }

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

        .win-quick-actions-expanded .win-quick-action {
            width: 48px;
            height: 48px;
            font-size: 18px;
            transform: scale(0);
            animation: expandButton 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            pointer-events: all;
            cursor: pointer;
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

        .badge-login { background: linear-gradient(135deg, #28a745, #20c997); }
        .badge-logout { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #212529 !important; }
        .badge-crear { background: linear-gradient(135deg, #007bff, #6610f2); }
        .badge-editar { background: linear-gradient(135deg, #17a2b8, #20c997); }
        .badge-eliminar { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .badge-configuracion { background: linear-gradient(135deg, #6c757d, #868e96); }
        .badge-perfil { background: linear-gradient(135deg, #6f42c1, #e83e8c); }
        .badge-default { background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, black)); }

        .modal-content {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .scrollable-content {
            scrollbar-width: thin;
            scrollbar-color: var(--win-accent) var(--win-bg-tertiary);
        }

        .scrollable-content::-webkit-scrollbar {
            width: 8px;
        }

        .scrollable-content::-webkit-scrollbar-track {
            background: var(--win-bg-tertiary);
            border-radius: 4px;
        }

        .scrollable-content::-webkit-scrollbar-thumb {
            background: var(--win-accent);
            border-radius: 4px;
        }

        .scrollable-content::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--win-accent) 80%, black);
        }

        .win-sidebar-user-avatar {
            transition: transform 0.3s ease;
        }

        .win-sidebar-user-avatar:hover {
            transform: scale(1.1);
        }

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
            .pagination-info, .pagination-controls, .registros-selector, .jump-to-page {
                justify-content: center;
                text-align: center;
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

        .text-muted, 
        .win-main-content .text-muted,
        .form-text,
        small.text-muted {
            color: #e0e0e0 !important;
            opacity: 0.9;
        }

        [data-theme="light"] .text-muted,
        [data-theme="light"] .win-main-content .text-muted,
        [data-theme="light"] .form-text,
        [data-theme="light"] small.text-muted {
            color: #555555 !important;
            opacity: 1;
        }

        ::placeholder {
            color: #cccccc !important;
            opacity: 0.7 !important;
        }

        [data-theme="light"] ::placeholder {
            color: #666666 !important;
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
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">HISTÓRICO - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <div style="flex: 1;"></div>
        
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
<?= renderNotificationsDropdown() ?>
        
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo $avatar_placeholder; ?>
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
                            <?php echo $avatar_placeholder; ?>
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
                            <?php echo $avatar_placeholder; ?>
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
                    Dashboard
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
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-layer-group"></i>
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
                    <i class="win-nav-icon fas fa-users-cog"></i>
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
                <a href="historico_view.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-history"></i>
                    <span class="win-nav-text">Histórico</span>
                    <span class="win-nav-badge"><?php echo $estadisticas['total'] ?? 0; ?></span>
                </a>
            </li>
        </ul>

        <!-- Widget de progreso financiero -->
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div>
                    <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small>
                    <small style="font-size: 10px; color: <?php echo $finanzas['color'] ?? '#0078d4'; ?>;">
                        <?php echo $finanzas['mensaje'] ?? 'Sin datos'; ?>
                    </small>
                </div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                    <?php echo isset($finanzas['porcentaje']) ? number_format($finanzas['porcentaje'], 1) : '0.0'; ?>%
                </h5>
            </div>

            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?php echo isset($finanzas['porcentaje']) ? min($finanzas['porcentaje'], 100) : 0; ?>%; background-color: <?php echo $finanzas['color'] ?? '#0078d4'; ?>; transition: width 1s ease-in-out;" 
                     aria-valuenow="<?php echo $finanzas['porcentaje'] ?? 0; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;">
                        <strong>$<?php echo isset($finanzas['real']) ? number_format($finanzas['real'], 2) : '0.00'; ?></strong>
                    </small>
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                        <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad'] ?? 0; ?> facturas
                    </small>
                </div>
                
                <small class="text-end text-success" style="font-size: 12px;">
                    Meta PLAN:<br>$<?php echo isset($finanzas['meta']) ? number_format($finanzas['meta'], 2) : '0.00'; ?>
                </small>
            </div>
        </div>
    </aside>

    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <?php if (isset($error_historico)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_historico; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-history me-2" style="color: var(--win-accent);"></i>Histórico de Actividades
                </h1>
                <p class="text-muted mb-0">Registro de todas las operaciones del sistema <?php echo $mes_actual_es . ' ' . date('Y'); ?>
                <?php if($es_supervisor): ?>
                    <span class="badge bg-danger ms-2">MODO SUPERVISOR</span>
                <?php else: ?>
                    <?php if(isset($usuario['rol_id'])): ?>
                        <?php if($usuario['rol_id'] == 1): ?>
                            <span class="badge bg-danger ms-2">Administrador</span>
                        <?php elseif($usuario['rol_id'] == 3): ?>
                            <span class="badge bg-warning ms-2">Facturador / Editor</span>
                        <?php elseif($usuario['rol_id'] == 2): ?>
                            <span class="badge bg-primary ms-2">Visualizador</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-2">Rol no definido</span>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                </p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                    
                    <div class="dropdown dropdown-exportacion">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-file-export me-1"></i>Exportar
                        </button>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Exportar histórico</h6></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'excel'])); ?>">
                                <i class="fas fa-file-excel text-success"></i> Excel (.xls)
                            </a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'word'])); ?>">
                                <i class="fas fa-file-word text-primary"></i> Word (.doc)
                            </a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'pdf'])); ?>">
                                <i class="fas fa-file-pdf text-danger"></i> PDF (.pdf)
                            </a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'csv'])); ?>">
                                <i class="fas fa-file-csv text-info"></i> CSV (.csv)
                            </a></li>
                        </ul>
                    </div>
                    
                    <?php if (!empty($filtro_usuario) || !empty($filtro_tipo) || !empty($filtro_busqueda)): ?>
                    <a href="historico_view.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Limpiar Filtros
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4 animate__animated animate__fadeIn">
            <div class="card-header">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" action="historico_view.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="filtro_usuario" class="form-label">Usuario</label>
                        <select class="form-select" id="filtro_usuario" name="usuario">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario_item): ?>
                                <option value="<?php echo $usuario_item['id']; ?>" <?php echo $filtro_usuario == $usuario_item['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario_item['nombre_completo'] . ' (' . $usuario_item['usuario'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="filtro_tipo" class="form-label">Tipo de Operación</label>
                        <select class="form-select" id="filtro_tipo" name="tipo">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_operacion as $tipo): ?>
                                <option value="<?php echo $tipo; ?>" <?php echo $filtro_tipo == $tipo ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="filtro_fecha_desde" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="filtro_fecha_desde" name="fecha_desde" 
                               value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="filtro_fecha_hasta" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="filtro_fecha_hasta" name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="filtro_busqueda" class="form-label">Buscar en operación/descripción</label>
                        <input type="text" class="form-control" id="filtro_busqueda" name="busqueda" 
                               value="<?php echo htmlspecialchars($filtro_busqueda); ?>" 
                               placeholder="Buscar por texto...">
                    </div>
                    
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Mostrando <?php echo count($historico); ?> de <?php echo $total_registros; ?> registros
                                </span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <a href="historico_view.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-redo me-1"></i>Restablecer
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Total Registros</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($estadisticas['total'] ?? 0); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-database fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Registros Hoy</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($estadisticas['hoy'] ?? 0); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-calendar-day fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Usuarios Activos</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($estadisticas['usuarios_activos'] ?? 0); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-user-check fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card h-100 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs fw-bold text-uppercase mb-1" style="color: var(--win-accent);">Tipos de Operación</div>
                                <div class="h5 mb-0 fw-bold" style="color: var(--win-text-primary);">
                                    <?php echo number_format($estadisticas['tipos_unicos'] ?? 0); ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-tags fa-2x" style="color: var(--win-accent); opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de histórico -->
        <div class="card animate__animated animate__fadeIn">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                        <i class="fas fa-list me-2"></i>
                        <?php if (!empty($filtro_usuario) && !empty($nombre_usuario_filtrado)): ?>
                            Registros de Actividades del Usuario (
                            <span style="color: <?php echo $estado_usuario == 'ACTIVO' ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo htmlspecialchars($nombre_usuario_filtrado); ?> - <?php echo $estado_usuario; ?>
                            </span>
                            )
                        <?php else: ?>
                            Registros de Actividades de: TODOS LOS USUARIOS REGISTRADOS
                        <?php endif; ?>
                    </h6>
                    <?php if (!empty($filtro_usuario) && !empty($nombre_usuario_filtrado)): ?>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Usuario: <?php echo htmlspecialchars($nombre_usuario_filtrado); ?> | 
                            Estado: 
                            <span class="badge bg-<?php echo $estado_usuario == 'ACTIVO' ? 'success' : 'danger'; ?>">
                                <?php echo $estado_usuario; ?>
                            </span>
                        </small>
                    <?php endif; ?>
                </div>
                <span class="badge" style="background-color: var(--win-accent);">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </span>
            </div>
            
            <?php if (empty($historico)): ?>
                <div class="card-body text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No se encontraron registros</h5>
                    <p class="text-muted">No hay actividades registradas con los filtros aplicados.</p>
                    <a href="historico_view.php" class="btn btn-primary mt-2">
                        <i class="fas fa-redo me-1"></i>Ver todas las actividades
                    </a>
                </div>
            <?php else: ?>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr style="background-color: var(--win-bg-tertiary); color: var(--win-text-primary);">
                                    <th width="50">#</th>
                                    <th width="50">
                                        <?php echo crearEncabezadoOrdenable('Id', 'id', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="120">
                                        <?php echo crearEncabezadoOrdenable('Fecha/Hora', 'fecha_hora', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="80">
                                        <?php echo crearEncabezadoOrdenable('Tipo', 'operacion', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th>
                                        <?php echo crearEncabezadoOrdenable('Usuario', 'usuario_nombre', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th>
                                        <?php echo crearEncabezadoOrdenable('Descripción', 'descripcion', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="120">
                                        <?php echo crearEncabezadoOrdenable('IP', 'ip_address', $columna_orden, $direccion_orden); ?>
                                    </th>
                                    <th width="80" class="text-center"><i class="fas fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $contador = $offset + 1;
                                foreach ($historico as $registro): 
                                    $fecha = isset($registro['fecha_hora']) ? date('d/m/Y', strtotime($registro['fecha_hora'])) : '';
                                    $hora_am_pm = isset($registro['fecha_hora']) ? date('h:i:s A', strtotime($registro['fecha_hora'])) : '';
                                ?>
                                    <tr style="color: var(--win-text-primary);" class="animate__animated animate__fadeIn" id="fila-<?php echo $registro['id']; ?>">
                                        <td class="text-muted"><?php echo $contador++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($registro['id'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($fecha); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($hora_am_pm); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($registro['operacion'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($registro['usuario_nombre'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-2">
                                                        <i class="fas fa-user-circle" style="color: var(--win-accent);"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($registro['usuario_nombre']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($registro['usuario_login'] ?? ''); ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sistema</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($registro['descripcion'])): ?>
                                                <span class="d-inline-block text-truncate" style="max-width: 250px;" 
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
                                        
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                    onclick="verDescripcionCompleta(
                                                        <?php echo $registro['id']; ?>,
                                                        '<?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Sistema', ENT_QUOTES); ?>',
                                                        '<?php echo htmlspecialchars($registro['usuario_login'] ?? '', ENT_QUOTES); ?>',
                                                        '<?php echo isset($registro['fecha_hora']) ? date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])) : ''; ?>',
                                                        '<?php echo htmlspecialchars($registro['operacion'] ?? '', ENT_QUOTES); ?>',
                                                        '<?php echo htmlspecialchars($registro['ip_address'] ?? '', ENT_QUOTES); ?>',
                                                        `<?php echo htmlspecialchars($registro['descripcion'] ?? '', ENT_QUOTES); ?>`
                                                    )"
                                                    title="Ver descripción completa">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="eliminarRegistroIndividual(<?php echo $registro['id']; ?>, '<?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Desconocido'); ?>')"
                                                    title="<?php echo $permiso_borrar ? 'Eliminar este registro' : 'No tiene permisos para eliminar'; ?>"
                                                    <?php echo !$permiso_borrar ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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
        <?php if ($total_paginas > 1 && !empty($historico)): ?>
        <div class="card pagination-card animate__animated animate__fadeIn" style="animation-delay: 0.5s;">
            <div class="card-body">
                <div class="pagination-container">
                    <div class="registros-selector">
                        <label for="registrosPorPagina">Registros por página:</label>
                        <select id="registrosPorPagina" class="form-select form-select-sm" onchange="cambiarRegistrosPorPagina(this.value)">
                            <?php foreach ($opciones_registros as $opcion): ?>
                                <option value="<?php echo $opcion; ?>" 
                                        <?php echo $registros_por_pagina == $opcion ? 'selected' : ''; ?>>
                                    <?php echo $opcion; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="pagination-info">
                        Mostrando 
                        <strong><?php echo min($total_registros, ($offset + 1)); ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?></strong> 
                        de <strong><?php echo $total_registros; ?></strong> registros
                        (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                    </div>
                    
                    <nav aria-label="Paginación del histórico">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>"
                                   aria-label="Primera">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>"
                                   aria-label="Anterior">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $pagina_inicio = max(1, $pagina_actual - 2);
                            $pagina_fin = min($total_paginas, $pagina_actual + 2);
                            
                            if ($pagina_inicio == 1) {
                                $pagina_fin = min(5, $total_paginas);
                            }
                            if ($pagina_fin == $total_paginas) {
                                $pagina_inicio = max(1, $total_paginas - 4);
                            }
                            
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
                            
                            for ($i = $pagina_inicio; $i <= $pagina_fin; $i++): ?>
                                <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;
                            
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
                            
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>"
                                   aria-label="Siguiente">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>"
                                   aria-label="Última">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
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
        <button class="win-quick-action action-delete-all" 
                onclick="confirmarVaciadoCompleto()" 
                title="<?php echo $permiso_borrar ? 'ELIMINAR TODA LA TRAZA' : 'Acción restringida'; ?>" 
                id="btnDeleteAll"
                style="<?php echo !$permiso_borrar ? 'background-color: #6c757d !important; cursor: not-allowed; opacity: 0.6;' : 'margin-bottom: 10px;'; ?>"
                <?php echo !$permiso_borrar ? 'disabled' : ''; ?>>
            <i class="fas fa-trash"></i>
        </button>

        <div class="win-quick-actions-expanded" id="quickActionsExpanded">
            <button class="win-quick-action action-factura" onclick="window.location.href='nueva_factura.php'" title="Nueva Factura">
                <i class="fas fa-file-invoice"></i>
            </button>
            <button class="win-quick-action action-cliente" onclick="window.location.href='nuevo_cliente.php'" title="Nuevo Cliente/Contrato">
                <i class="fas fa-user-plus"></i>
            </button>
            <button class="win-quick-action action-servicio" onclick="window.location.href='nuevo_servicio.php'" title="Nuevo Servicio">
                <i class="fas fa-plus-circle"></i>
            </button>
        </div>
        
        <button class="win-quick-action" onclick="toggleQuickActions(event)" title="Opciones" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    
    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let quickActionsOpen = false; 
        
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
        
        function guardarPreferencia(clave, valor) {
            fetch('guardar_preferencia.php', { 
                method: 'POST', 
                body: `${clave}=${valor}` 
            }).catch(e => {});
        }
        
        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch('guardar_configuracion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire('Error', 'No se pudo guardar la configuración', 'error');
                }
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Error', 'Error de conexión', 'error');
            });
            
            cerrarPanelTemas();
        }
        
        function toggleQuickActions(event) {
            if (event) { event.stopPropagation(); event.preventDefault(); }
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
        
        function cambiarRegistrosPorPagina(valor) {
            const url = new URL(window.location.href);
            url.searchParams.set('registros_por_pagina', valor);
            url.searchParams.set('pagina', 1);
            window.location.href = url.toString();
        }
        
        function jumpToPage() {
            const pagina = document.getElementById('jumpToPage').value;
            if (pagina && pagina >= 1 && pagina <= <?php echo $total_paginas; ?>) {
                const url = new URL(window.location.href);
                url.searchParams.set('pagina', pagina);
                window.location.href = url.toString();
            }
        }
        
        function eliminarRegistroIndividual(id, user) {
            if (!id) {
                Swal.fire('Error', 'El ID del registro es inválido', 'error');
                return;
            }
            
            Swal.fire({
                title: '¿Estás seguro?',
                html: "Se eliminará el registro ID: <strong>" + id + "</strong><br>Del usuario: <strong>" + user + "</strong>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1f1f1f' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('accion', 'eliminar_item');
                    formData.append('id', id);
                    
                    fetch('ajax_delete_historico.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(textoRespuesta => {
                        try {
                            const data = JSON.parse(textoRespuesta);
                            if(data.success) {
                                const fila = document.getElementById('fila-' + id);
                                if(fila) {
                                    fila.classList.add('animate__animated', 'animate__fadeOutRight');
                                    setTimeout(() => fila.remove(), 500);
                                }
                                Swal.fire({icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false});
                            } else {
                                Swal.fire('Error del Servidor', data.message || 'Error desconocido', 'error');
                            }
                        } catch (e) {
                            console.error("Error al parsear JSON:", e);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de Código PHP',
                                html: 'El servidor devolvió algo que no es JSON.<br>Verifica la consola.'
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error de Red', 'No se pudo conectar con ajax_delete_historico.php', 'error');
                    });
                }
            });
        }
        
        function confirmarVaciadoCompleto() {
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            
            Swal.fire({
                title: '⚠️ Vaciado Completo del Histórico',
                html: `
                    <div style="text-align: center; color: ${isDarkMode ? '#e1e5e9' : '#333'};">
                        <div style="margin: 20px 0; padding: 20px; background: ${isDarkMode ? 'rgba(255, 76, 76, 0.08)' : 'rgba(220, 53, 69, 0.05)'}; border-radius: 12px;">
                            <div style="color: ${isDarkMode ? '#ff6b6b' : '#dc3545'}; font-weight: 600; margin-bottom: 15px;">
                                <i class="fas fa-exclamation-circle"></i> Confirmación Crítica Requerida
                            </div>
                            <div style="background: ${isDarkMode ? 'rgba(0, 0, 0, 0.2)' : 'rgba(255, 255, 255, 0.8)'}; padding: 20px; border-radius: 8px; border: 2px solid ${isDarkMode ? 'rgba(255, 76, 76, 0.3)' : 'rgba(220, 53, 69, 0.3)'};">
                                <div style="color: ${isDarkMode ? '#fff' : '#212529'}; font-size: 1.8rem; font-weight: 700; letter-spacing: 2px; font-family: 'Consolas', monospace;">
                                    BORRAR
                                </div>
                                <div style="color: ${isDarkMode ? '#b0b7c3' : '#6c757d'}; font-size: 0.9rem; margin-top: 10px;">
                                    <i class="fas fa-keyboard me-2"></i>Escribe exactamente la palabra arriba
                                </div>
                            </div>
                            <div style="color: ${isDarkMode ? '#adb5bd' : '#495057'}; font-size: 0.85rem; text-align: left; margin-top: 20px; padding: 15px; background: ${isDarkMode ? 'rgba(255, 76, 76, 0.05)' : 'rgba(220, 53, 69, 0.03)'}; border-radius: 8px; border-left: 3px solid ${isDarkMode ? '#ff6b6b' : '#dc3545'};">
                                <div style="margin-bottom: 8px; font-weight: 600;"><i class="fas fa-info-circle me-2"></i>Importante:</div>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>Esta acción es permanente e irreversible</li>
                                    <li>Se eliminarán todos los registros del histórico</li>
                                    <li>La palabra debe escribirse exactamente como se muestra</li>
                                    <li>Se registrará esta acción en el sistema</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Confirmar Eliminación',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: isDarkMode ? '#1a1a1a' : '#ffffff',
                color: isDarkMode ? '#e1e5e9' : '#212529',
                showLoaderOnConfirm: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                input: 'text',
                inputPlaceholder: 'Escriba "BORRAR" para confirmar...',
                inputAttributes: {
                    'autocomplete': 'off',
                    'spellcheck': 'false'
                },
                preConfirm: (inputText) => {
                    if (inputText !== 'BORRAR') {
                        Swal.showValidationMessage(`
                            <div style="color: ${isDarkMode ? '#ff6b6b' : '#dc3545'}; text-align: center; padding: 15px; font-weight: 600;">
                                <i class="fas fa-exclamation-triangle me-2"></i> La palabra ingresada no coincide
                            </div>
                        `);
                        return false;
                    }
                    
                    const formData = new FormData();
                    formData.append('accion', 'eliminar_todo');
                    
                    return fetch('ajax_delete_historico.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                        return response.json();
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Error de conexión: ${error.message}`);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle text-success me-2"></i>Operación Completada',
                        html: `
                            <div style="text-align: center; padding: 20px; color: ${isDarkMode ? '#e1e5e9' : '#333'};">
                                <div style="background: ${isDarkMode ? 'rgba(40, 167, 69, 0.1)' : 'rgba(40, 167, 69, 0.05)'}; padding: 20px; border-radius: 8px;">
                                    <i class="fas fa-database fa-2x" style="color: #28a745; margin-bottom: 15px;"></i>
                                    <div style="font-weight: 600; margin-bottom: 10px;">Histórico Vaciado Correctamente</div>
                                    <div style="color: ${isDarkMode ? '#adb5bd' : '#6c757d'}; font-size: 0.9rem;">
                                        Todos los registros han sido eliminados permanentemente
                                    </div>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        background: isDarkMode ? '#1a1a1a' : '#ffffff',
                        color: isDarkMode ? '#e1e5e9' : '#212529',
                        timer: 2500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                }
            });
        }
        
        function verDescripcionCompleta(id, usuario, login, fechaHora, tipo, ip, descripcion, infoAdicional) {
            let fechaFormateada = '';
            let horaFormateada12h = '';
            let horaFormateada24h = '';
            
            try {
                if (fechaHora) {
                    const partes = fechaHora.split(' ');
                    fechaFormateada = partes[0] || '';
                    const hora24 = partes[1] || '';
                    
                    if (hora24) {
                        horaFormateada24h = hora24;
                        const [horas, minutos, segundos] = hora24.split(':');
                        let h = parseInt(horas);
                        const amPm = h >= 12 ? 'PM' : 'AM';
                        h = h % 12;
                        h = h ? h : 12;
                        horaFormateada12h = `${h.toString().padStart(2, '0')}:${minutos}:${segundos} ${amPm}`;
                    }
                }
            } catch (error) {
                console.error("Error al procesar fecha/hora:", error);
            }
            
            const avatarElement = document.getElementById('modalAvatar');
            if (avatarElement) {
                const iniciales = usuario ? usuario.charAt(0).toUpperCase() : 'S';
                const colores = {
                    'A': '#0078d4', 'B': '#107c10', 'C': '#5c2d91', 'D': '#e81123',
                    'E': '#ff8c00', 'F': '#0099bc', 'G': '#e3008c', 'H': '#8764b8',
                    'I': '#0078d4', 'J': '#107c10', 'K': '#5c2d91', 'L': '#e81123',
                    'M': '#ff8c00', 'N': '#0099bc', 'O': '#e3008c', 'P': '#8764b8',
                    'Q': '#0078d4', 'R': '#107c10', 'S': '#5c2d91', 'T': '#e81123',
                    'U': '#ff8c00', 'V': '#0099bc', 'W': '#e3008c', 'X': '#8764b8',
                    'Y': '#0078d4', 'Z': '#107c10'
                };
                const color = colores[iniciales] || '#0078d4';
                avatarElement.style.background = color;
                avatarElement.innerHTML = `<span style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-weight: bold; color: white;">${iniciales}</span>`;
            }
            
            if (document.getElementById('modalID')) {
                document.getElementById('modalID').textContent = `ID: #${id}`;
            }
            if (document.getElementById('modalUsuario')) {
                document.getElementById('modalUsuario').textContent = usuario || 'Sistema';
            }
            if (document.getElementById('modalLogin')) {
                document.getElementById('modalLogin').textContent = login ? `@${login}` : '';
            }
            if (document.getElementById('modalFecha')) {
                document.getElementById('modalFecha').textContent = fechaFormateada;
            }
            if (document.getElementById('modalHora')) {
                document.getElementById('modalHora').textContent = horaFormateada12h;
                if (horaFormateada24h) {
                    document.getElementById('modalHora').title = `Formato 24h: ${horaFormateada24h}`;
                    document.getElementById('modalHora').style.cursor = 'help';
                }
            }
            if (document.getElementById('modalTipo')) {
                const badgeClass = obtenerClaseBadgeModal(tipo);
                document.getElementById('modalTipo').innerHTML = 
                    `<span class="badge ${badgeClass} p-2" style="font-size: 0.85rem; font-weight: 600;">${tipo}</span>`;
            }
            if (document.getElementById('modalIP')) {
                document.getElementById('modalIP').textContent = ip || 'No disponible';
            }
            if (document.getElementById('modalLocalizacion')) {
                document.getElementById('modalLocalizacion').textContent = obtenerLocalizacionIP(ip);
            }
            if (document.getElementById('modalDescripcion')) {
                const descripcionTexto = descripcion || 'Sin descripción disponible';
                document.getElementById('modalDescripcion').textContent = descripcionTexto;
                
                if (document.getElementById('modalCaracteres')) {
                    const caracteres = descripcionTexto.length;
                    const palabras = descripcionTexto.split(/\s+/).filter(word => word.length > 0).length;
                    document.getElementById('modalCaracteres').textContent = 
                        `${caracteres} caracteres • ${palabras} palabras`;
                }
            }
            
            const infoElement = document.getElementById('modalInfoAdicional');
            const infoTipoElement = document.getElementById('modalInfoTipo');
            
            if (infoElement && infoTipoElement) {
                try {
                    if (infoAdicional && infoAdicional.trim() !== '') {
                        const infoObj = JSON.parse(infoAdicional);
                        const formattedJson = JSON.stringify(infoObj, null, 2);
                        infoElement.textContent = formattedJson;
                        infoTipoElement.textContent = 'JSON';
                    } else {
                        infoElement.textContent = 'No hay información adicional disponible';
                        infoTipoElement.textContent = 'TEXTO';
                    }
                } catch (e) {
                    infoElement.textContent = infoAdicional || 'No hay información adicional disponible';
                    infoTipoElement.textContent = 'TEXTO';
                }
            }
            
            if (document.getElementById('modalTamanio')) {
                const contenidoCompleto = `${descripcion || ''} ${infoAdicional || ''}`;
                const tamanoBytes = new Blob([contenidoCompleto]).size;
                let tamanoTexto = '';
                
                if (tamanoBytes < 1024) {
                    tamanoTexto = `${tamanoBytes} bytes`;
                } else if (tamanoBytes < 1024 * 1024) {
                    tamanoTexto = `${(tamanoBytes / 1024).toFixed(2)} KB`;
                } else {
                    tamanoTexto = `${(tamanoBytes / (1024 * 1024)).toFixed(2)} MB`;
                }
                
                document.getElementById('modalTamanio').textContent = `Tamaño: ${tamanoTexto}`;
            }
            
            try {
                const modalElement = document.getElementById('descripcionModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            } catch (error) {
                console.error("Error al mostrar modal:", error);
            }
        }
        
        function obtenerClaseBadgeModal(tipo) {
            if (!tipo) return 'badge-default';
            const tipoLower = tipo.toLowerCase();
            if (tipoLower.includes('login')) return 'badge-login';
            if (tipoLower.includes('logout')) return 'badge-logout';
            if (tipoLower.includes('crear') || tipoLower.includes('nuevo') || tipoLower.includes('insert')) return 'badge-crear';
            if (tipoLower.includes('editar') || tipoLower.includes('actualizar') || tipoLower.includes('update')) return 'badge-editar';
            if (tipoLower.includes('eliminar') || tipoLower.includes('borrar') || tipoLower.includes('delete')) return 'badge-eliminar';
            if (tipoLower.includes('config') || tipoLower.includes('setting')) return 'badge-configuracion';
            if (tipoLower.includes('perfil') || tipoLower.includes('profile')) return 'badge-perfil';
            if (tipoLower.includes('factura') || tipoLower.includes('invoice')) return 'badge-factura';
            if (tipoLower.includes('cliente') || tipoLower.includes('client')) return 'badge-cliente';
            if (tipoLower.includes('servicio') || tipoLower.includes('service')) return 'badge-servicio';
            return 'badge-default';
        }
        
        function obtenerLocalizacionIP(ip) {
            if (!ip || ip === 'No disponible') return 'No disponible';
            if (ip === '127.0.0.1' || ip === '::1') return 'Localhost';
            if (ip.startsWith('192.168.')) return 'Red Local';
            if (ip.startsWith('10.')) return 'Red Privada';
            if (ip.startsWith('172.')) {
                const segundoOcteto = parseInt(ip.split('.')[1]);
                if (segundoOcteto >= 16 && segundoOcteto <= 31) return 'Red Privada';
            }
            return 'Externa';
        }
        
function exportarRegistro() {
    // Obtener todos los datos del modal (igual que en copiarDescripcion)
    const usuario = document.getElementById('modalUsuario').textContent;
    const login = document.getElementById('modalLogin').textContent;
    const fecha = document.getElementById('modalFecha').textContent;
    const hora = document.getElementById('modalHora').textContent;
    const tipoElement = document.getElementById('modalTipo');
    const tipo = tipoElement ? tipoElement.textContent : '';
    const ip = document.getElementById('modalIP').textContent;
    const localizacion = document.getElementById('modalLocalizacion').textContent;
    const descripcion = document.getElementById('modalDescripcion').textContent;
    const idTexto = document.getElementById('modalID').textContent;
    
    // Construir el contenido EXACTAMENTE igual que en copiarDescripcion
    const contenido = `DETALLES DEL REGISTRO HISTÓRICO
=====================================
${idTexto}
Usuario: ${usuario}${login ? ' (' + login + ')' : ''}
Fecha: ${fecha}
Hora: ${hora}
Tipo: ${tipo}
IP: ${ip} (${localizacion})

DESCRIPCIÓN:
${descripcion}

=====================================
Exportado: ${new Date().toLocaleString()}
Sistema: PDL Visiones - SISFACT`;

    // Crear archivo y descargar
    const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    
    // Nombre del archivo con timestamp
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    const nombreUsuario = usuario.replace(/\s+/g, '_');
    a.download = `registro_historico_${nombreUsuario}_${timestamp}.txt`;
    
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    // Mostrar SweetAlert de éxito
    Swal.fire({
        icon: 'success',
        title: '¡Exportado!',
        text: 'Registro exportado correctamente',
        timer: 2000,
        showConfirmButton: false,
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1f1f1f' : '#fff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
}
        
function copiarDescripcion() {
    // Obtener todos los datos del modal
    const usuario = document.getElementById('modalUsuario').textContent;
    const login = document.getElementById('modalLogin').textContent;
    const fecha = document.getElementById('modalFecha').textContent;
    const hora = document.getElementById('modalHora').textContent;
    const tipoElement = document.getElementById('modalTipo');
    const tipo = tipoElement ? tipoElement.textContent : '';
    const ip = document.getElementById('modalIP').textContent;
    const localizacion = document.getElementById('modalLocalizacion').textContent;
    const descripcion = document.getElementById('modalDescripcion').textContent;
    const idTexto = document.getElementById('modalID').textContent;
    
    // Construir el contenido a copiar (igual que en exportarRegistro)
    const contenido = `DETALLES DEL REGISTRO HISTÓRICO
=====================================
${idTexto}
Usuario: ${usuario}${login ? ' (' + login + ')' : ''}
Fecha: ${fecha}
Hora: ${hora}
Tipo: ${tipo}
IP: ${ip} (${localizacion})

DESCRIPCIÓN:
${descripcion}

=====================================
Copiado: ${new Date().toLocaleString()}
Sistema: PDL Visiones - SISFACT`;

    // Copiar al portapapeles
    navigator.clipboard.writeText(contenido)
        .then(() => {
            // Mostrar SweetAlert de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Datos copiados!',
                text: 'La información del registro ha sido copiada al portapapeles.',
                timer: 2000,
                showConfirmButton: false,
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1f1f1f' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
                toast: true,
                position: 'top-end',
                showCloseButton: true,
                timerProgressBar: true
            });
            
            // Cambiar estilo del botón temporalmente
            const btn = event?.target?.closest('button');
            if (btn) {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado!';
                btn.className = btn.className.replace('btn-outline-primary', 'btn-success');
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.className = btn.className.replace('btn-success', 'btn-outline-primary');
                }, 1500);
            }
        })
        .catch((err) => {
            console.error('Error al copiar:', err);
            
            // Fallback: mostrar texto seleccionable
            Swal.fire({
                icon: 'error',
                title: 'No se pudo copiar automáticamente',
                html: `
                    <div class="text-start">
                        <p>Puedes copiar manualmente el siguiente texto:</p>
                        <textarea readonly style="width:100%; height:200px; background: #2d2d2d; color: #fff; padding:10px; border-radius:5px; font-family: monospace;">${contenido.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1f1f1f' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
            });
        });
}
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.win-theme-option').forEach(opt => {
                opt.addEventListener('click', function() {
                    document.documentElement.setAttribute('data-theme', this.dataset.theme);
                });
            });
            
            document.querySelectorAll('.win-color-option').forEach(opt => {
                opt.addEventListener('click', function() {
                    document.querySelectorAll('.win-color-option').forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                    const color = this.dataset.color;
                    document.documentElement.style.setProperty('--win-accent', color);
                    document.documentElement.style.setProperty('--win-accent-light', color + '20');
                });
            });
            
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(a => {
                    try { new bootstrap.Alert(a).close(); } catch(e) {}
                });
            }, 5000);
        });
    </script>

    <!-- Modal para Ver Descripción Completa -->
    <div class="modal fade" id="descripcionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: var(--win-bg-secondary); color: var(--win-text-primary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius);">
                <div class="modal-header" style="background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); padding: 1rem 1.5rem;">
                    <div class="d-flex align-items-center w-100">
                        <div class="me-3">
                            <div class="win-sidebar-user-avatar" style="width: 42px; height: 42px; font-size: 16px; background: var(--win-accent);" id="modalAvatar">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title mb-0" style="color: var(--win-text-primary);">
                                <i class="fas fa-file-alt me-2" style="color: var(--win-accent);"></i>Detalles del Registro
                            </h5>
                            <small class="text-muted" id="modalID"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: var(--win-text-primary)"></button>
                    </div>
                </div>
                
                <div class="modal-body p-0">
                    <div class="p-4 border-bottom" style="border-color: var(--win-border-color); background: linear-gradient(to right, var(--win-bg-secondary), var(--win-bg-tertiary));">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-user-circle fa-lg" style="color: var(--win-accent);"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">USUARIO</div>
                                        <div class="fw-bold" id="modalUsuario" style="font-size: 1rem;"></div>
                                        <div class="text-muted small" id="modalLogin"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-clock fa-lg" style="color: var(--win-accent);"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">FECHA Y HORA</div>
                                        <div class="fw-bold" id="modalFecha" style="font-size: 1rem;"></div>
                                        <div class="text-muted small" id="modalHora"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-tag fa-lg" style="color: var(--win-accent);"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">TIPO DE OPERACIÓN</div>
                                        <div id="modalTipo"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-network-wired fa-lg" style="color: var(--win-accent);"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">DIRECCIÓN IP</div>
                                        <div class="fw-bold" id="modalIP" style="font-size: 1rem;"></div>
                                        <div class="text-muted small" id="modalLocalizacion"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 border-bottom" style="border-color: var(--win-border-color);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0" style="color: var(--win-text-primary);">
                                <i class="fas fa-align-left me-2"></i>Descripción Completa
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="copiarDescripcion()" title="Copiar descripción">
                                <i class="fas fa-copy me-1"></i>Copiar
                            </button>
                        </div>
                        <div class="card" style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color);">
                            <div class="card-body">
                                <div class="scrollable-content" style="max-height: 200px; overflow-y: auto;">
                                    <p id="modalDescripcion" class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6; font-size: 0.95rem; color: var(--win-text-primary);"></p>
                                </div>
                                <div class="mt-2 text-end">
                                    <small class="text-muted" id="modalCaracteres"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="background: var(--win-bg-tertiary); border-top: 1px solid var(--win-border-color); padding: 1rem 1.5rem;">
                    <div class="d-flex justify-content-between w-100">
                        <div class="text-muted small">
                            <i class="fas fa-database me-1"></i>
                            <span id="modalTamanio"></span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cerrar
                            </button>
                            <button type="button" class="btn btn-primary" onclick="exportarRegistro()">
                                <i class="fas fa-download me-1"></i>Exportar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
if (file_exists('config/footer.php')) {
    include 'config/footer.php';
}
?>