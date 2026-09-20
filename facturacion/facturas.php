<?php
require_once 'config/header.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// ==================== LÓGICA DE FECHAS OPERATIVAS (CORREGIDA) ====================
// Obtener configuración de la BD
$mes_cierre_num = obtenerMesCierreOperaciones(); // Retorna 1-12
$anio_cierre_num = obtenerAnioCierreOperaciones(); // Retorna YYYY

// Obtener día actual real del servidor/PC
$dia_actual_real = (int)date('j');

// --- INICIO CORRECCIÓN: AJUSTE DE DÍAS ---
// 1. Averiguar cuántos días tiene REALMENTE el mes operativo (ej: Abril=30, Feb Bisiesto=29)
$dias_maximos_mes = cal_days_in_month(CAL_GREGORIAN, $mes_cierre_num, $anio_cierre_num);

// 2. Comparar y elegir el menor. 
// Si hoy es 31 y el mes tiene 30, elige 30. Si hoy es 15 y el mes tiene 30, elige 15.
$dia_final = min($dia_actual_real, $dias_maximos_mes);
// --- FIN CORRECCIÓN ---

// Construir la fecha "Hoy Operativo" (Año BD + Mes BD + Día Validado)
$hoy_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_final);

// Nombres de meses
$meses_completos = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Mes actual operativo en texto
$mes_actual_es = $meses_completos[intval($mes_cierre_num)];
// ==================== FIN LÓGICA FECHAS ====================

// ==================== REDIRECCIÓN AUTOMÁTICA POR DEFECTO (CORREGIDA) ====================
// Verificar si la página se cargó SIN NINGÚN PARÁMETRO
$tiene_algun_parametro = !empty($_GET) || !empty($_POST);

if (!$tiene_algun_parametro) {
    // Solo redirigir si NO HAY ABSOLUTAMENTE NINGÚN PARÁMETRO
    // Construir URL con mes y año de operaciones
    $url_redir = 'facturas.php?mes=' . str_pad($mes_cierre_num, 2, '0', STR_PAD_LEFT) . 
                 '&anio=' . $anio_cierre_num;
    
    header('Location: ' . $url_redir);
    exit();
}
// ==================== FIN REDIRECCIÓN ====================

// Inicializar variables para evitar errores
$error = '';
$facturas = [];
$total_facturas = 0;
$total_clientes = 0;
$total_categorias = 0;
$total_servicios = 0;
$total_usuarios = 0;
$clientes = [];
$servicios = [];
$servicios_por_categoria = [];
$anios_disponibles = [];
$estadisticas = ['total' => 0];


// ======= CONFIGURACIÓN DE PAGINACIÓN =========
$registros_por_pagina = isset($_GET['registros']) ? (int)$_GET['registros'] : 10;
if ($registros_por_pagina < 1) $registros_por_pagina = 10;
if ($registros_por_pagina > 100) $registros_por_pagina = 100;

$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$total_registros = 0;
$total_paginas = 1;
// =========== FIN CONFIGURACIÓN PAGINACIÓN ========

// =========== MANEJAR PARÁMETRO MOSTRAR TODO ==========
$mostrar_todo = isset($_GET['mostrar_todo']) && $_GET['mostrar_todo'] == 'true';
if ($mostrar_todo) {
    $registros_por_pagina = 999999; // Un número muy alto para mostrar "todo"
    $pagina_actual = 1;
    $offset = 0;
}
// ==================== FIN MANEJAR MOSTRAR TODO ====================

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

// ==================== MANEJO DE FILTROS ====================
// Inicializar variables de filtro
$filtros = [];
$params = [];
$filtros_inner_join = [];


// ==================== CONFIGURACIÓN DE ORDENAMIENTO ====================
$columnas_permitidas = [
    'no_fact' => 'f.id',  // Ordena por ID (orden de inserción)
    'cliente' => 'cliente_nombre',
    'emision' => 'f.fecha_emision',
    'contabilizacion' => 'f.fecha_contabilizacion',
    'total'   => 'f.total_general',
    'estado'  => 'f.estado'
];

$sort = isset($_GET['sort']) && isset($columnas_permitidas[$_GET['sort']]) ? $_GET['sort'] : 'emision';
$order = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
$order_by_sql = $columnas_permitidas[$sort];
// ==================== FIN CONFIGURACIÓN ORDENAMIENTO ====================


// Variables para mantener los valores de los filtros
$fecha_inicio_val = '';
$fecha_fin_val = '';
$tipo_fecha_val = 'emision'; // Valor por defecto
$cliente_id_val = '';
$estado_val = '';
$no_factura_val = '';
$mes_val = '';
$anio_val = '';
$servicio_id_val = '';

// Procesar filtros - AHORA SIEMPRE USAR GET PARA MANTENER LA PAGINACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger todos los parámetros del POST
    $fecha_inicio_val = $_POST['fecha_inicio'] ?? '';
    $fecha_fin_val = $_POST['fecha_fin'] ?? '';
    $tipo_fecha_val = $_POST['tipo_fecha'] ?? 'emision'; // Capturar tipo fecha
    $cliente_id_val = $_POST['cliente_id'] ?? '';
    $estado_val = $_POST['estado'] ?? '';
    $no_factura_val = $_POST['no_factura'] ?? '';
    $mes_val = $_POST['mes'] ?? '';
    $anio_val = $_POST['anio'] ?? '';
    $servicio_id_val = $_POST['servicio_id'] ?? '';
    
    // Construir URL con parámetros GET
    $query_string = '';
    
    if (!empty($fecha_inicio_val)) $query_string .= '&fecha_inicio=' . urlencode($fecha_inicio_val);
    if (!empty($fecha_fin_val)) $query_string .= '&fecha_fin=' . urlencode($fecha_fin_val);
    if (!empty($tipo_fecha_val)) $query_string .= '&tipo_fecha=' . urlencode($tipo_fecha_val);
    if (!empty($cliente_id_val)) $query_string .= '&cliente_id=' . urlencode($cliente_id_val);
    if (!empty($estado_val)) $query_string .= '&estado=' . urlencode($estado_val);
    if (!empty($no_factura_val)) $query_string .= '&no_factura=' . urlencode($no_factura_val);
    if (!empty($mes_val)) $query_string .= '&mes=' . urlencode($mes_val);
    if (!empty($anio_val)) $query_string .= '&anio=' . urlencode($anio_val);
    
    // Manejar servicio_id que puede ser array
    if (!empty($servicio_id_val)) {
        if (is_array($servicio_id_val)) {
            foreach ($servicio_id_val as $servicio) {
                if (!empty($servicio)) {
                    $query_string .= '&servicio_id[]=' . urlencode($servicio);
                }
            }
        } else {
            $query_string .= '&servicio_id=' . urlencode($servicio_id_val);
        }
    }
    
    // Redirigir a GET para mantener la paginación
    if ($query_string) {
        $query_string = '?' . ltrim($query_string, '&');
    }
    
    header('Location: facturas.php' . $query_string);
    exit();
} else {
    // Si es GET, usar los valores de GET
    $fecha_inicio_val = $_GET['fecha_inicio'] ?? '';
    $fecha_fin_val = $_GET['fecha_fin'] ?? '';
    $tipo_fecha_val = $_GET['tipo_fecha'] ?? 'emision';
    $cliente_id_val = $_GET['cliente_id'] ?? '';
    $estado_val = $_GET['estado'] ?? '';
    $no_factura_val = $_GET['no_factura'] ?? '';
    $mes_val = $_GET['mes'] ?? '';
    $anio_val = $_GET['anio'] ?? '';
    $servicio_id_val = $_GET['servicio_id'] ?? '';
    
    // Si servicio_id es string, convertirlo en array para consistencia
    if (!empty($servicio_id_val) && !is_array($servicio_id_val)) {
        $servicio_id_val = [$servicio_id_val];
    }
}

// === LÓGICA PARA DETERMINAR QUÉ COLUMNA DE FECHA FILTRAR ===
$columna_fecha_sql = "f.fecha_emision"; // Valor por defecto

if ($tipo_fecha_val === 'contabilizacion') {
    $columna_fecha_sql = "f.fecha_contabilizacion";
} elseif ($tipo_fecha_val === 'pago') {
    $columna_fecha_sql = "f.fecha_pago";
}

// Ahora aplicar los filtros a la consulta SQL usando la columna dinámica
if (!empty($fecha_inicio_val)) {
    // Agregamos DATE() para comparar solo la fecha sin la hora
    $filtros[] = "DATE($columna_fecha_sql) >= ?"; 
    $params[] = $fecha_inicio_val;
}

if (!empty($fecha_fin_val)) {
    // Agregamos DATE() para comparar solo la fecha sin la hora
    $filtros[] = "DATE($columna_fecha_sql) <= ?"; 
    $params[] = $fecha_fin_val;
}

if (!empty($cliente_id_val)) {
    $filtros[] = "f.cliente_id = ?";
    $params[] = $cliente_id_val;
}

// Modificación en el manejo de filtros de estado (CORREGIDO PARA INCLUIR CERRADAS PAGADAS)
if (!empty($estado_val)) {
    if ($estado_val === 'PAGADA_SIN_REF') {
        // Filtro especial: (Estado PAGADA O (Estado CERRADA con Fecha Pago)) Y (Referencia es NULL o Vacía)
        $filtros[] = "(f.estado = 'PAGADA' OR (f.estado = 'CERRADA' AND f.fecha_pago IS NOT NULL AND f.fecha_pago != '0000-00-00' AND f.fecha_pago != '0000-00-00 00:00:00')) AND (f.Ref_pago IS NULL OR TRIM(f.Ref_pago) = '')";
    } elseif ($estado_val === 'CERRADA_SIN_REF') {
        // NUEVO: Filtro para facturas CERRADA sin referencia (fecha_pago vacía y sin referencia)
        $filtros[] = "(f.estado = 'CERRADA') AND (f.fecha_pago IS NULL OR f.fecha_pago = '0000-00-00' OR f.fecha_pago = '0000-00-00 00:00:00') AND (f.Ref_pago IS NULL OR TRIM(f.Ref_pago) = '')";
    } elseif ($estado_val === 'SIN_REF') {
        // NUEVO: Filtro para facturas CERRADA sin referencia (fecha_pago vacía y sin referencia)
        $filtros[] = "(f.fecha_pago IS NULL OR f.fecha_pago = '0000-00-00' OR f.fecha_pago = '0000-00-00 00:00:00') AND (f.Ref_pago IS NULL OR TRIM(f.Ref_pago) = '')";
    } elseif ($estado_val === 'PAGADA') {
        // Filtro PAGADA: Incluye las que dicen 'PAGADA' Y las que dicen 'CERRADA' pero tienen fecha de pago
        $filtros[] = "(f.estado = 'PAGADA' OR (f.estado = 'CERRADA' AND f.fecha_pago IS NOT NULL AND f.fecha_pago != '0000-00-00' AND f.fecha_pago != '0000-00-00 00:00:00'))";
    } else {
        // Filtro normal para el resto (PENDIENTE, ANULADA, CONTABILIZADA, CERRADA específica)
        $filtros[] = "f.estado = ?";
        $params[] = $estado_val;
    }
}

if (!empty($no_factura_val)) {
    $filtros[] = "f.no_fact LIKE ?";
    $params[] = '%' . $no_factura_val . '%';
}

// Filtro por mes (Aplicado a la columna seleccionada)
if (!empty($mes_val)) {
    $filtros[] = "MONTH($columna_fecha_sql) = ?";
    $params[] = $mes_val;
}

// Filtro por año (Aplicado a la columna seleccionada)
if (!empty($anio_val)) {
    $filtros[] = "YEAR($columna_fecha_sql) = ?";
    $params[] = $anio_val;
}

// Filtro por servicio
if (!empty($servicio_id_val) && is_array($servicio_id_val)) {
    $filtros_inner_join['tbl_fact_detalle'] = true;
    $placeholders = str_repeat('?,', count($servicio_id_val) - 1) . '?';
    $filtros[] = "df.servicio_id IN ($placeholders)";
    foreach ($servicio_id_val as $servicio_id) {
        $params[] = $servicio_id;
    }
} elseif (!empty($servicio_id_val)) {
    $filtros_inner_join['tbl_fact_detalle'] = true;
    $filtros[] = "df.servicio_id = ?";
    $params[] = $servicio_id_val;
}

// ==================== VARIABLES PARA ESTADÍSTICAS DE FACTURAS ====================
$estadisticas_facturas = [
    'dia' => ['cantidad' => 0, 'importe' => 0],
    'mes_actual' => ['cantidad' => 0, 'importe' => 0],
    'anio_actual' => ['cantidad' => 0, 'importe' => 0],
    'total_bd' => ['cantidad' => 0, 'importe' => 0],
    'contabilizadas' => ['mes' => ['cantidad' => 0, 'importe' => 0], 'anio' => ['cantidad' => 0, 'importe' => 0]],
    'pagadas_mes' => ['cantidad' => 0, 'importe' => 0],
    'estados' => [
        'PAGADA' => ['cantidad' => 0, 'importe' => 0],
        'CONTABILIZADA' => ['cantidad' => 0, 'importe' => 0],
        'PENDIENTE' => ['cantidad' => 0, 'importe' => 0],
        'ANULADA' => ['cantidad' => 0, 'importe' => 0],
        'CERRADA' => ['cantidad' => 0, 'importe' => 0]
    ]
];

// ==================== FIN MANEJO DE FILTROS ====================

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
    $esAdmin = ($usuario['rol_id'] == 1);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);
    $esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);
    
    // Permisos específicos
    $puedeEditarFacturas = ($esAdmin || $esEditor || $esSuper);
    $puedeContabilizar = ($esAdmin || $esEditor || $esSuper);
    $puedeAnular = ($esAdmin || $esEditor || $esSuper);
    $puedeEliminar = $esAdmin; // Solo admin puede eliminar
    
    // ==================== OBTENER ESTADÍSTICAS BASADAS EN CIERRE OPERATIVO ====================
    // NOTA: Se ha reemplazado CURDATE() por las variables de cierre configuradas en BD.
    
    // 1. Facturas del "Hoy Operativo"
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE DATE(fecha_emision) = :hoy_operativo";
        $stmt = $db->prepare($sql);
        $stmt->execute(['hoy_operativo' => $hoy_operativo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['dia']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['dia']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas del día operativo: " . $e->getMessage());
    }
    
    // 2. Facturas del MES OPERATIVO (Emisión)
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE MONTH(fecha_emision) = :mes 
                AND YEAR(fecha_emision) = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes_cierre_num, 'anio' => $anio_cierre_num]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['mes_actual']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['mes_actual']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas del mes operativo: " . $e->getMessage());
    }
    
    // 3. Facturas del AÑO OPERATIVO (Emisión)
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio_cierre_num]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['anio_actual']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['anio_actual']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener estadísticas del año operativo: " . $e->getMessage());
    }
    
    // 4. Total en base de datos (Histórico completo)
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['total_bd']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['total_bd']['importe'] = $result['importe'] ?? 0;
        $total_facturas = $estadisticas_facturas['total_bd']['cantidad'];
    } catch (Exception $e) {
        error_log("Error al obtener totales de la BD: " . $e->getMessage());
    }
    
    // 5. Contabilizadas en el MES OPERATIVO
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE estado IN ('CONTABILIZADA', 'PAGADA')
                AND MONTH(fecha_contabilizacion) = :mes 
                AND YEAR(fecha_contabilizacion) = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes_cierre_num, 'anio' => $anio_cierre_num]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['contabilizadas']['mes']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['contabilizadas']['mes']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener contabilizadas del mes operativo: " . $e->getMessage());
    }
    
    // 6. Contabilizadas en el AÑO OPERATIVO (Acumulado de todo lo finalizado en el año)
    try {
        // Usamos YEAR(fecha_emision) para asegurar que tome todas las facturas del año actual
        // que ya alcanzaron un estado final (Contabilizada, Pagada o Cerrada)
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA') 
                AND YEAR(fecha_emision) = :anio"; 
                
        $stmt = $db->prepare($sql);
        $stmt->execute(['anio' => $anio_cierre_num]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $estadisticas_facturas['contabilizadas']['anio']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['contabilizadas']['anio']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener contabilizadas del año operativo: " . $e->getMessage());
    }
    
    // 7. Pagadas en el MES OPERATIVO (VERSIÓN COMPLETA - Incluye CERRADAS con pago)
    try {
        $sql = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                FROM tbl_fact 
                WHERE (
                    estado = 'PAGADA' 
                    OR 
                    (estado IN ('CONTABILIZADA', 'CERRADA') 
                     AND fecha_pago IS NOT NULL 
                     AND fecha_pago != '0000-00-00' 
                     AND fecha_pago != '0000-00-00 00:00:00'
                     AND Ref_pago IS NOT NULL 
                     AND Ref_pago != ''
                    )
                )
                AND MONTH(fecha_emision) = :mes 
                AND YEAR(fecha_emision) = :anio";
        $stmt = $db->prepare($sql);
        $stmt->execute(['mes' => $mes_cierre_num, 'anio' => $anio_cierre_num]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_facturas['pagadas_mes']['cantidad'] = $result['cantidad'] ?? 0;
        $estadisticas_facturas['pagadas_mes']['importe'] = $result['importe'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al obtener pagadas del mes operativo: " . $e->getMessage());
    }
    
    // 8. Distribución por estados (todas las facturas) - INCLUYE CERRADAS
    try {
        $sql = "SELECT 
                    estado,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total_general), 0) as importe
                FROM tbl_fact 
                GROUP BY estado";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultados as $row) {
            $estado = $row['estado'];
            // Verificar si el estado existe en el array (incluyendo CERRADA)
            if (isset($estadisticas_facturas['estados'][$estado])) {
                $estadisticas_facturas['estados'][$estado]['cantidad'] = $row['cantidad'] ?? 0;
                $estadisticas_facturas['estados'][$estado]['importe'] = $row['importe'] ?? 0;
            } else {
                // Si hay algún estado no contemplado, lo agregamos dinámicamente
                $estadisticas_facturas['estados'][$estado] = [
                    'cantidad' => $row['cantidad'] ?? 0,
                    'importe' => $row['importe'] ?? 0
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error al obtener distribución por estados: " . $e->getMessage());
    }
    
    // Obtener estadísticas para los badges del sidebar
    try {
        $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
        $stmt = $db->prepare($sql_clientes_total);
        $stmt->execute();
        $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar clientes: " . $e->getMessage());
        $total_clientes = 0;
    }
    
    try {
        $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
        $stmt = $db->prepare($sql_categorias_total);
        $stmt->execute();
        $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar categorías: " . $e->getMessage());
        $total_categorias = 0;
    }
    
    try {
        $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
        $stmt = $db->prepare($sql_servicios_total);
        $stmt->execute();
        $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar servicios: " . $e->getMessage());
        $total_servicios = 0;
    }
    
    try {
        $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
        $stmt = $db->prepare($sql_usuarios_total);
        $stmt->execute();
        $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar usuarios: " . $e->getMessage());
        $total_usuarios = 0;
    }
    
    // Obtener clientes para filtro
    try {
        $sql_clientes = "SELECT id, nombre FROM clasif_clientes ORDER BY nombre";
        $stmt_clientes = $db->query($sql_clientes);
        $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al cargar clientes: " . $e->getMessage());
        $clientes = [];
    }
    
    // Obtener servicios para filtro (organizados por categoría)
    try {
        $sql_servicios = "SELECT s.id, s.codigo, s.descripcion as nombre, 
                         c.descripcion as categoria_nombre 
                         FROM clasif_serv s 
                         LEFT JOIN clasif_cat_de_serv c ON s.categoria_id = c.id 
                         ORDER BY c.descripcion, s.descripcion";
        $stmt_servicios = $db->query($sql_servicios);
        $servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar servicios por categoría
        $servicios_por_categoria = [];
        foreach ($servicios as $servicio) {
            $categoria = $servicio['categoria_nombre'] ?: 'Sin Categoría';
            $servicios_por_categoria[$categoria][] = $servicio;
        }
    } catch (Exception $e) {
        error_log("Error al cargar servicios: " . $e->getMessage());
        $servicios = [];
        $servicios_por_categoria = [];
    }
    
    // Obtener años disponibles para el filtro
    try {
        $sql_anios = "SELECT DISTINCT YEAR(fecha_emision) as anio 
                      FROM tbl_fact 
                      WHERE fecha_emision IS NOT NULL 
                      ORDER BY YEAR(fecha_emision) DESC";
        $stmt_anios = $db->query($sql_anios);
        $anios_disponibles = $stmt_anios->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al cargar años: " . $e->getMessage());
        $anios_disponibles = [];
    }
    
    // ==================== CONTAR TOTAL DE REGISTROS ====================
    try {
        $sql_count = "SELECT COUNT(DISTINCT f.id) as total
                      FROM tbl_fact f 
                      LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                      LEFT JOIN tipos_pago t ON f.tipo_pago_id = t.id
                      LEFT JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
                      LEFT JOIN clasif_serv s ON fd.servicio_id = s.id";
        
        // Añadir INNER JOIN con tbl_fact_detalle si se filtra por servicio
        if (isset($filtros_inner_join['tbl_fact_detalle'])) {
            $sql_count .= " INNER JOIN tbl_fact_detalle df ON f.id = df.factura_id";
        }
        
        if (!empty($filtros)) {
            $sql_count .= " WHERE " . implode(" AND ", $filtros);
        }
        
        $stmt_count = $db->prepare($sql_count);
        
        // Bind parameters para el count
        foreach ($params as $key => $value) {
            $stmt_count->bindValue($key + 1, $value);
        }
        
        $stmt_count->execute();
        $result_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $total_registros = $result_count['total'] ?? 0;
        
        // Calcular total de páginas
        $total_paginas = ceil($total_registros / $registros_por_pagina);
        
        // Ajustar página actual si es mayor que el total de páginas
        if ($pagina_actual > $total_paginas && $total_paginas > 0) {
            $pagina_actual = $total_paginas;
            $offset = ($pagina_actual - 1) * $registros_por_pagina;
        }
        
    } catch (Exception $e) {
        error_log("Error en consulta COUNT: " . $e->getMessage());
        $total_registros = 0;
        $total_paginas = 1;
    }
    // ==================== FIN CONTAR REGISTROS ====================
    
    // Construir consulta de facturas con paginación
    try {
        $sql = "SELECT DISTINCT f.*, c.nombre as cliente_nombre, t.descripcion as tipo_pago,
                GROUP_CONCAT(DISTINCT s.descripcion ORDER BY s.descripcion SEPARATOR ', ') as servicios_nombres
                FROM tbl_fact f 
                LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                LEFT JOIN tipos_pago t ON f.tipo_pago_id = t.id
                LEFT JOIN tbl_fact_detalle fd ON f.id = fd.factura_id
                LEFT JOIN clasif_serv s ON fd.servicio_id = s.id";
        
        // Añadir INNER JOIN con tbl_fact_detalle si se filtra por servicio
        if (isset($filtros_inner_join['tbl_fact_detalle'])) {
            $sql .= " INNER JOIN tbl_fact_detalle df ON f.id = df.factura_id";
        }
        
        if (!empty($filtros)) {
            $sql .= " WHERE " . implode(" AND ", $filtros);
        }
        
        $sql .= " GROUP BY f.id ORDER BY $order_by_sql $order";
        
        // ==================== AÑADIR LIMIT PARA PAGINACIÓN ====================
        $sql .= " LIMIT ? OFFSET ?";
        
        // Añadir los parámetros de paginación al array de parámetros
        $params_paginacion = $params;
        $params_paginacion[] = $registros_por_pagina;
        $params_paginacion[] = $offset;
        
        $stmt = $db->prepare($sql);
        
        // Bind parameters - usando parámetros posicionales para todos
        foreach ($params_paginacion as $key => $value) {
            // Determinar el tipo de dato para el bindValue
            $param_type = PDO::PARAM_STR;
            
            // Si es el último parámetro (offset) o penúltimo (limit), son enteros
            if ($key === count($params_paginacion) - 1 || $key === count($params_paginacion) - 2) {
                $param_type = PDO::PARAM_INT;
            }
            // Si el valor es numérico, usar PARAM_INT
            elseif (is_numeric($value)) {
                $param_type = PDO::PARAM_INT;
            }
            
            $stmt->bindValue($key + 1, $value, $param_type);
        }
        
        $stmt->execute();
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular totales SOLO de los registros visibles en esta página
        $total_subtotal = 0;
        $total_general = 0;
        $contador_facturas = count($facturas);
        
        // Calcular totales por estado SOLO de esta página
        $totales_estado = [
            'PENDIENTE' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
            'CONTABILIZADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
            'ANULADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
            'PAGADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0]
        ];
        
        foreach ($facturas as $factura) {
            $total_subtotal += floatval($factura['subtotal'] ?? 0);
            $total_general += floatval($factura['total_general'] ?? 0);
            
            // Calcular por estado
            $estado = $factura['estado'] ?? 'PENDIENTE';
            if (isset($totales_estado[$estado])) {
                $totales_estado[$estado]['subtotal'] += floatval($factura['subtotal'] ?? 0);
                $totales_estado[$estado]['total'] += floatval($factura['total_general'] ?? 0);
                $totales_estado[$estado]['cantidad']++;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error en consulta de facturas: " . $e->getMessage());
        $error = "Error al cargar las facturas: " . $e->getMessage();
        $facturas = [];
    }
    
    // Total registros para histórico
    try {
        $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total);
        $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $estadisticas['total'] = 0;
    }
    
} catch (Exception $e) {
    error_log("Error general en facturas.php: " . $e->getMessage());
    $error = "Error al cargar el sistema: " . $e->getMessage();
}

// Obtener nombre del cliente si se está filtrando por cliente
$nombre_cliente_filtro = '';
if (!empty($cliente_id_val)) {
    foreach ($clientes as $cliente) {
        if ($cliente['id'] == $cliente_id_val) {
            $nombre_cliente_filtro = htmlspecialchars($cliente['nombre'] ?? '');
            break;
        }
    }
}

// Obtener nombre del servicio si se está filtrando por servicio
$nombre_servicio_filtro = '';
if (!empty($servicio_id_val)) {
    $nombres = [];
    if (is_array($servicio_id_val)) {
        foreach ($servicios as $servicio) {
            if (in_array($servicio['id'], $servicio_id_val)) {
                $nombres[] = htmlspecialchars($servicio['nombre'] ?? '');
            }
        }
    } else {
        foreach ($servicios as $servicio) {
            if ($servicio['id'] == $servicio_id_val) {
                $nombres[] = htmlspecialchars($servicio['nombre'] ?? '');
                break;
            }
        }
    }
    $nombre_servicio_filtro = implode(', ', $nombres);
}

// Inicializar variables de totales para evitar errores
$total_subtotal = $total_subtotal ?? 0;
$total_general = $total_general ?? 0;
$contador_facturas = $contador_facturas ?? 0;
$totales_estado = $totales_estado ?? [
    'PENDIENTE' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
    'CONTABILIZADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
    'ANULADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0],
    'PAGADA' => ['subtotal' => 0, 'total' => 0, 'cantidad' => 0]
];

// ==================== FUNCIÓN PARA CONSTRUIR URL DE PAGINACIÓN ====================
function construirUrlPaginacion($pagina, $registros_por_pagina = null) {
    $url = 'facturas.php?';
    $params = [];
    
    // Mantener todos los parámetros de filtro GET
    $filtro_params = [
        'fecha_inicio', 'fecha_fin', 'tipo_fecha', 'cliente_id', 
        'estado', 'no_factura', 'mes', 'anio', 'servicio_id', 
        'mostrar_todo', 'sort', 'order'
    ];
    
    foreach ($filtro_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            if (is_array($_GET[$param])) {
                foreach ($_GET[$param] as $value) {
                    if (!empty($value)) {
                        $params[] = $param . '[]=' . urlencode($value);
                    }
                }
            } else {
                if ($_GET[$param] !== '') {
                    $params[] = $param . '=' . urlencode($_GET[$param]);
                }
            }
        }
    }
    
    // Si se especifica un nuevo valor para registros por página
    if ($registros_por_pagina !== null) {
        // Eliminar el parámetro de registros anterior si existe
        $params = array_filter($params, function($param) {
            return strpos($param, 'registros=') !== 0;
        });
        $params[] = 'registros=' . $registros_por_pagina;
    } else if (isset($_GET['registros'])) {
        $params[] = 'registros=' . urlencode($_GET['registros']);
    }
    
    // Mantener mostrar_todo si está activo
    if (isset($_GET['mostrar_todo']) && $_GET['mostrar_todo'] == 'true') {
        $params[] = 'mostrar_todo=true';
    }
    
    // Añadir todos los parámetros
    if (!empty($params)) {
        $url .= implode('&', $params) . '&';
    }
    
    // Añadir la nueva página
    $url .= 'pagina=' . $pagina;
    
    return $url;
}
// ==================== FIN FUNCIÓN PAGINACIÓN ====================

function linkOrdenamiento($columna, $texto, $sort_actual, $order_actual) {
    $nuevo_order = ($sort_actual == $columna && $order_actual == 'ASC') ? 'DESC' : 'ASC';
    
    // Construir URL manteniendo filtros actuales
    $params = $_GET;
    $params['sort'] = $columna;
    $params['order'] = $nuevo_order;
    $url = 'facturas.php?' . http_build_query($params);
    
    $icon = '';
    if ($sort_actual == $columna) {
        $icon = $order_actual == 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fas fa-sort text-muted opacity-50"></i>';
    }
    
    return "<a href='$url' class='text-decoration-none' style='color: inherit;'>$texto $icon</a>";
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas - SISFACT PDL Visiones</title>
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
    <!-- Chart.js -->
    <script src="js/chart.js"></script>
    
    <!-- MDTimePicker para el reloj analógico -->
    <link rel="stylesheet" href="css/mdtimepicker.css">
    <script src="js/mdtimepicker.min.js"></script>
    
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
        }

        /* Contenido principal actualizado */
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

        /* Estilos para tarjetas y formularios */
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
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
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
            margin-bottom: 0.5rem;
        }

        .win-main-content .table {
            color: var(--win-text-primary);
            border-color: var(--win-border_color);
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

        /* Botones */
        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-primary:hover {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
            border-color: color-mix(in srgb, var(--win-accent) 90%, black);
            transform: translateY(-1px);
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

        /* Badges */
        .badge {
            border-radius: var(--win-radius-sm);
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        /* Estados */
        .estado-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--win-radius-sm);
            font-weight: 500;
            display: inline-block;
        }

        .estado-pendiente {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .estado-contabilizada {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }

        .estado-anulada {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Progress bars */
        .progress {
            background-color: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm);
            overflow: hidden;
        }

        .progress-bar {
            background-color: var(--win-accent);
        }

        /* Ajustes para tema oscuro */
        [data-theme="dark"] .text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.8;
        }

        [data-theme="dark"] small.text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.8;
        }

        /* Ajustes para tema claro */
        [data-theme="light"] .text-muted {
            color: #6c757d !important;
            opacity: 1;
        }

        /* Ajustes para botones de cerrar en tema oscuro */
        [data-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
        }

        [data-theme="dark"] .btn-close:hover {
            opacity: 1;
        }

        /* Ajustes específicos para modales en tema oscuro */
        [data-theme="dark"] .modal-header .btn-close {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e");
        }

        /* Ajustes para botones de cerrar en alertas */
        [data-theme="dark"] .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-theme="dark"] .alert-danger .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) sepia(100%) saturate(500%) hue-rotate(300deg);
        }

        /* Ajustes para el borde de separación */
        [data-theme="dark"] .border-bottom {
            border-color: var(--win-border-color) !important;
        }

        /* Ajustes para los placeholders */
        [data-theme="dark"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7;
        }

        [data-theme="dark"] .form-control::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7;
        }

        /* Estilos específicos para esta página */
        .filtros-resumen-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filtros-card {
            flex: 2;
        }

        .resumen-card {
            flex: 1;
            min-width: 300px;
        }

        .resumen-estado-item {
            padding: 8px 0;
            border-bottom: 1px solid var(--win-border-color);
        }

        .resumen-estado-item:last-child {
            border-bottom: none;
        }

        .estado-badge-small {
            font-size: 0.7em;
            padding: 3px 8px;
        }

        .totales-row {
            background-color: rgba(var(--win-accent-rgb), 0.1);
            font-weight: 600;
        }

        .totales-row td {
            border-top: 2px solid var(--win-accent) !important;
        }

        .resumen-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .resumen-total {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--win-accent);
        }

        /* Tooltips */
        .tooltip {
            --bs-tooltip-bg: var(--win-bg-tertiary);
            --bs-tooltip-color: var(--win-text-primary);
            z-index: 10000 !important;
            opacity: 1 !important;
        }
        .tooltip-inner {
            background-color: var(--win-accent) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-size: 0.85rem;
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .bs-tooltip-top .tooltip-arrow::before { border-top-color: var(--win-accent) !important; }
        .bs-tooltip-bottom .tooltip-arrow::before { border-bottom-color: var(--win-accent) !important; }
        .bs-tooltip-start .tooltip-arrow::before { border-left-color: var(--win-accent) !important; }
        .bs-tooltip-end .tooltip-arrow::before { border-right-color: var(--win-accent) !important; }

        .tooltip-arrow::before {
            border-top-color: var(--win-accent) !important;
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
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Quick Actions Expandibles con animación circular */
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

        /* Responsive */
        @media (max-width: 1200px) {
            .filtros-resumen-container {
                flex-direction: column;
            }
            .filtros-card, .resumen-card {
                width: 100%;
            }
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
                padding: 16px;
            }
        }

        @media (max-width: 768px) {
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
        
        /* Dropdown estilos */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border_color);
            border-radius: var(--win-radius);
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
        
        /* Estilos específicos para filtros */
        .filtro-activo-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(32, 201, 151, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(32, 201, 151, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(32, 201, 151, 0);
            }
        }
        
        /* Estilos para combos con búsqueda */
        .select-search-wrapper {
            position: relative;
        }
        
        .select-search-input {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--win-bg-tertiary) !important;
            border-bottom: 1px solid var(--win-border-color) !important;
            border-radius: 0 !important;
        }
        
        /* ==================== ESTILOS PARA PAGINACIÓN ==================== */
        .pagination .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            transition: var(--win-transition);
        }
        
        .pagination .page-link:hover {
            background-color: var(--win-accent-light);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--win-accent);
            border-color: var(--win-accent);
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-secondary);
            opacity: 0.5;
        }
        
        .pagination-container {
            margin-top: 20px;
        }
        
        .pagination-info {
            font-size: 0.9em;
            color: var(--win-text-secondary);
            margin-bottom: 10px;
        }
        
        .registros-selector {
            width: 80px !important;
            display: inline-block;
            margin-left: 10px;
        }
        /* ==================== FIN ESTILOS PAGINACIÓN ==================== */

        /* ==================== ESTILOS PARA ESTADÍSTICAS DE FACTURAS (COMPACTAS) ==================== */
        .estadisticas-compactas-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .estadistica-compacta-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius-sm);
            padding: 12px;
            transition: var(--win-transition);
            position: relative;
            overflow: hidden;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .estadistica-compacta-card:hover {
            border-color: var(--win-accent);
            transform: translateY(-2px);
            box-shadow: var(--win-shadow);
        }

        .estadistica-compacta-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--win-accent);
            border-radius: var(--win-radius-sm) 0 0 var(--win-radius-sm);
        }

        .estadistica-compacta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .estadistica-compacta-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--win-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .estadistica-compacta-icon {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            background: var(--win-accent-light);
            color: var(--win-accent);
            flex-shrink: 0;
        }

        .estadistica-compacta-values {
            text-align: left;
        }

        .estadistica-compacta-cantidad {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--win-text-primary);
            line-height: 1;
            margin-bottom: 4px;
        }

        .estadistica-compacta-importe {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--win-accent);
        }

        .estadistica-compacta-subtitle {
            font-size: 0.7rem;
            color: var(--win-text-secondary);
            margin-top: 4px;
            opacity: 0.8;
        }

        /* Colores específicos para cada tipo de estadística (más sutiles) */
        .estadistica-hoy::before {
            background: #28a745;
        }

        .estadistica-hoy .estadistica-compacta-icon {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .estadistica-hoy .estadistica-compacta-importe {
            color: #28a745;
        }

        .estadistica-mes::before {
            background: #007bff;
        }

        .estadistica-mes .estadistica-compacta-icon {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }

        .estadistica-mes .estadistica-compacta-importe {
            color: #007bff;
        }

        .estadistica-anio::before {
            background: #6f42c1;
        }

        .estadistica-anio .estadistica-compacta-icon {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .estadistica-anio .estadistica-compacta-importe {
            color: #6f42c1;
        }

        .estadistica-total::before {
            background: #fd7e14;
        }

        .estadistica-total .estadistica-compacta-icon {
            background: rgba(253, 126, 20, 0.1);
            color: #fd7e14;
        }

        .estadistica-total .estadistica-compacta-importe {
            color: #fd7e14;
        }

        .estadistica-contabilizadas::before {
            background: #20c997;
        }

        .estadistica-contabilizadas .estadistica-compacta-icon {
            background: rgba(32, 201, 151, 0.1);
            color: #20c997;
        }

        .estadistica-contabilizadas .estadistica-compacta-importe {
            color: #20c997;
        }

        /* NUEVO ESTILO PARA PAGADAS MES */
        .estadistica-pagadas-mes::before {
            background: #0dcaf0;
        }

        .estadistica-pagadas-mes .estadistica-compacta-icon {
            background: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }

        .estadistica-pagadas-mes .estadistica-compacta-importe {
            color: #0dcaf0;
        }

        /* Estadísticas por estado (compactas) */
        .estadisticas-estado-compactas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .estadistica-estado-compacta {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius-sm);
            padding: 10px;
            text-align: center;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .estado-badge-mini {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.7rem;
            margin-bottom: 6px;
        }

        .estado-cantidad-mini {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .estado-importe-mini {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--win-accent);
        }

        /* Responsive para estadísticas compactas */
        @media (max-width: 1200px) {
            .estadisticas-compactas-container {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .estadisticas-compactas-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .estadistica-compacta-cantidad {
                font-size: 1.2rem;
            }
            
            .estadistica-compacta-importe {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .estadisticas-compactas-container {
                grid-template-columns: 1fr;
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: 1fr;
            }
        }
        /* ==================== FIN ESTILOS PARA ESTADÍSTICAS DE FACTURAS (COMPACTAS) ==================== */

        /* Estilos para el dropdown de usuario */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border_color);
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
        
        /* Botón de colapsar estilo Windows 11 */
        .btn-card-toggle {
            color: var(--win-text-primary) !important;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease-in-out;
            background-color: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
        }

        .btn-card-toggle:hover {
            background-color: var(--win-accent-light);
            color: var(--win-accent) !important;
            border-color: var(--win-accent);
        }

        .btn-card-toggle .toggle-icon {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.85rem;
            -webkit-text-stroke: 0.5px;
        }

        .btn-card-toggle.collapsed .toggle-icon {
            transform: rotate(180deg);
        }
        
        /* Estilos para selección múltiple */
        .factura-checkbox:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .btn-eliminar-multiple {
            transition: all 0.3s ease;
        }
        
        .btn-eliminar-multiple:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .contador-seleccionados {
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 12px;
            background: var(--win-accent-light);
            color: var(--win-accent);
        }
/* Estilos para modales dark mode de eliminación */
.dark-swal-popup-win11 {
    border-radius: 12px !important;
    border: 1px solid #3d3d3d !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
    backdrop-filter: blur(0px) !important;
    background: #1e1e1e !important;
}

.swal-btn-danger-win11 {
    background: #dc3545 !important;
    border: none !important;
    border-radius: 6px !important;
    padding: 10px 20px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
}

.swal-btn-danger-win11:hover {
    background: #bb2d3b !important;
    transform: translateY(-1px) !important;
}

.swal-btn-secondary-win11 {
    background: #3d3d3d !important;
    border: 1px solid #4d4d4d !important;
    border-radius: 6px !important;
    padding: 10px 20px !important;
    font-weight: 500 !important;
    color: #e0e0e0 !important;
    transition: all 0.2s ease !important;
}

.swal-btn-secondary-win11:hover {
    background: #4d4d4d !important;
    transform: translateY(-1px) !important;
}
/* Estilos para modales de eliminación múltiple dark mode */
.dark-swal-multiple-success {
    background: #1e1e1e !important;
    border-radius: 16px !important;
    border: 1px solid #2d5a2d !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
}

.dark-swal-multiple-error {
    background: #1e1e1e !important;
    border-radius: 16px !important;
    border: 1px solid #5a2d2d !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
}

.swal-btn-multiple-success {
    background: #28a745 !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 10px 24px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
}

.swal-btn-multiple-success:hover {
    background: #1f8b3a !important;
    transform: scale(1.02) !important;
}

/* Scrollbar personalizado para la lista */
.dark-swal-multiple-success .swal2-html-container::-webkit-scrollbar {
    width: 6px;
}

.dark-swal-multiple-success .swal2-html-container::-webkit-scrollbar-track {
    background: #2d2d2d;
    border-radius: 3px;
}

.dark-swal-multiple-success .swal2-html-container::-webkit-scrollbar-thumb {
    background: #28a745;
    border-radius: 3px;
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
            <span style="color: var(--win-text-primary);">FACTURAS - SISFACT PDL Visiones</span>
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
                        <?php if (!empty($usuario['foto'])): ?><img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); endif; ?>
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
                          data-bs-toggle="tooltip"
                          title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
                          style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
                        F/Cierre: <?php echo fechaInicioFormateada(9); ?>
                    </span>
                </a>
            </li>
            <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link active">
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
                    <!-- Cantidad de Facturas -->
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
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);">
                    <i class="fas fa-file-invoice me-2" style="color: var(--win-accent);"></i>Gestión de Facturas
                </h1>
                <p class="text-muted mb-0">
                    Consulte y administre las facturas del sistema<br>Fecha Operaciones: 
                    <span class="badge bg-success fw-bold">
                        <?php echo ultimoDiaMesFechaInicio(); ?>
                    </span>
                </p>
                <p class="small text-muted mb-0">
                    <i class="fas fa-clock me-1"></i>Última factura en el Sistema: 
                    <span class="text-info fw-bold"><?php echo $lastInvoiceInfo['numero'] . ' - ' . 
                    $lastInvoiceInfo['fecha']. ' - $ ' . number_format($lastInvoiceInfo['importe'], 2); ?></span>
                </p>
            </div>
            
            <div class="btn-toolbar mb-2 mb-md-0">
                <!-- Búsqueda rápida por número de factura -->
                <form method="GET" action="" class="me-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" style="background: var(--win-bg-tertiary); border-color: var(--win-border-color);">
                            <i class="fas fa-search text-info"></i>
                        </span>
                        <input type="text" 
                               name="no_factura" 
                               class="form-control" 
                               placeholder="Buscar por No. Factura"
                               value="<?php echo htmlspecialchars($no_factura_val); ?>"
                               style="width: 200px; background: var(--win-bg-tertiary); border-color: var(--win-border-color); color: var(--win-text-primary);">
                        <button type="submit" class="btn btn-sm btn-primary">
                            Buscar
                        </button>
                    </div>
                </form>
                
                <?php
                // Determina si puede crear facturas
                $puedeCrearFactura = ($esAdmin || $esEditor || $esSuper);
                ?>

                <!-- Botón Nueva Factura - Estilo Windows 11 compacto -->
                <div class="position-relative">
                    <?php if ($puedeCrearFactura): ?>
                        <!-- Dropdown para NUEVO DOCUMENTO (FACTURA/OFERTA) -->
                        <div class="btn-group">
                            <button type="button" 
                                    class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-3 py-2 fw-medium win-button-primary dropdown-toggle" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    data-bs-placement="bottom"
                                    title="Crear nuevo documento">
                                <div class="win-button-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <span class="win-button-text">Nuevo Documento</span>
                                <span class="win-button-badge text-light">
                                    <i class="fas fa-rocket"></i>
                                </span>
                            </button>
                            
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 220px; background: var(--win-bg-secondary); border: 1px solid var(--win-border-color);">
                                <!-- Opción FACTURA -->
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2" 
                                       href="nueva_factura.php?tipo=FACTURA"
                                       onclick="return confirmarCambioTipoDropdown('FACTURA')">
                                        <div class="d-flex align-items-center gap-3" style="width: 100%;">
                                            <div class="win-icon-circle" style="background: rgba(0, 120, 212, 0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file-invoice" style="color: #0078d4; font-size: 1rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="d-block fw-semibold" style="color: var(--win-text-primary);">FACTURA</span>
                                                <small class="text-muted d-block" style="font-size: 11px;">Documento fiscal para cobro</small>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary" style="font-size: 10px;">
                                                <i class="fas fa-file-invoice me-1"></i>NUEVA
                                            </span>
                                        </div>
                                    </a>
                                </li>
                                
                                <!-- Divisor -->
                                <li><hr class="dropdown-divider my-1" style="border-color: var(--win-border-color);"></li>
                                
                                <!-- Opción OFERTA -->
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2" 
                                       href="nueva_factura.php?tipo=OFERTA"
                                       onclick="return confirmarCambioTipoDropdown('OFERTA')">
                                        <div class="d-flex align-items-center gap-3" style="width: 100%;">
                                            <div class="win-icon-circle" style="background: rgba(40, 167, 69, 0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-tag" style="color: #28a745; font-size: 1rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="d-block fw-semibold" style="color: var(--win-text-primary);">OFERTA</span>
                                                <small class="text-muted d-block" style="font-size: 11px;">Propuesta comercial sin valor fiscal</small>
                                            </div>
                                            <span class="badge bg-success-subtle text-success" style="font-size: 10px;">
                                                <i class="fas fa-tag me-1"></i>NUEVA
                                            </span>
                                        </div>
                                    </a>
                                </li>
                                
                                <!-- Footer con info del tipo actual -->
                                <li><hr class="dropdown-divider my-1" style="border-color: var(--win-border-color);"></li>
                                <li class="px-3 py-2">
                                    <small class="text-muted d-block" style="font-size: 12px; margin-top: 2px;">
                                        <i class="fas fa-clock me-1"></i>Última factura Introducida en el Sistema: 
                                        <br><span class="text-info fw-bold"><?php echo $lastInvoiceInfo['numero']; ?> - 
                                        <?php echo $lastInvoiceInfo['fecha']; ?> - $ <?php echo number_format($lastInvoiceInfo['importe'], 2); ?></span>
                                    </small>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-sm d-flex align-items-center gap-2 px-3 py-2 fw-medium win-button-disabled"
                                data-bs-toggle="tooltip"
                                data-bs-placement="bottom"
                                title="Permisos insuficientes. Contacte al administrador">
                            <div class="win-button-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <span class="win-button-text">Nueva Factura</span>
                            <span class="win-button-badge bg-secondary">
                                <i class="fas fa-lock"></i>
                            </span>
                        </button>
                    <?php endif; ?>
                </div>

                <style>
                    /* Estilos específicos para botones Windows 11 */
                    .win-button-primary {
                        background: var(--win-accent);
                        background: linear-gradient(135deg, 
                            var(--win-accent) 0%, 
                            color-mix(in srgb, var(--win-accent) 85%, #000) 100%);
                        border: none;
                        border-radius: var(--win-radius-sm);
                        padding: 0.5rem 1rem;
                        color: white;
                        font-weight: 500;
                        text-decoration: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        box-shadow: 
                            0 2px 4px rgba(0, 0, 0, 0.1),
                            inset 0 1px 0 rgba(255, 255, 255, 0.15);
                        position: relative;
                        overflow: hidden;
                    }
                    
                    .win-button-primary::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: -100%;
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(90deg, 
                            transparent, 
                            rgba(255, 255, 255, 0.2), 
                            transparent);
                        transition: left 0.6s;
                    }
                    
                    .win-button-primary:hover {
                        background: linear-gradient(135deg, 
                            color-mix(in srgb, var(--win-accent) 90%, #fff) 0%, 
                            var(--win-accent) 100%);
                        transform: translateY(-2px);
                        box-shadow: 
                            0 4px 12px rgba(0, 0, 0, 0.2),
                            inset 0 1px 0 rgba(255, 255, 255, 0.2);
                    }
                    
                    .win-button-primary:hover::before {
                        left: 100%;
                    }
                    
                    .win-button-primary:active {
                        transform: translateY(0);
                        box-shadow: 
                            0 1px 2px rgba(0, 0, 0, 0.1),
                            inset 0 1px 0 rgba(255, 255, 255, 0.1);
                    }
                    
                    .win-button-disabled {
                        background: var(--win-bg-tertiary);
                        border: 1px solid var(--win-border-color);
                        color: var(--win-text-secondary);
                        border-radius: var(--win-radius-sm);
                        cursor: not-allowed;
                        opacity: 0.7;
                        position: relative;
                        user-select: none;
                    }
                    
                    .win-button-disabled::after {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: repeating-linear-gradient(
                            45deg,
                            transparent,
                            transparent 5px,
                            rgba(0, 0, 0, 0.05) 5px,
                            rgba(0, 0, 0, 0.05) 10px
                        );
                        pointer-events: none;
                        border-radius: var(--win-radius-sm);
                    }
                    
                    .win-button-icon {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 20px;
                        height: 20px;
                        transition: transform 0.3s;
                    }
                    
                    .win-button-primary:hover .win-button-icon {
                        transform: scale(1.1) rotate(5deg);
                    }
                    
                    .win-button-badge {
                        font-size: 0.7em;
                        padding: 2px 6px;
                        border-radius: 10px;
                        font-weight: 600;
                        margin-left: 4px;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                    }
                    
                    .win-button-text {
                        letter-spacing: 0.3px;
                    }
                </style>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php if (strpos($error, 'tbl_fact') !== false): ?>
                    <div class="mt-2">
                        <small>Posible problema con la base de datos. Verifique:</small>
                        <ul class="mb-0">
                            <li>La tabla 'tbl_fact' existe</li>
                            <li>Las columnas tienen nombres correctos</li>
                            <li>La conexión a la base de datos está activa</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ==================== ESTADÍSTICAS DE FACTURAS COMPACTAS ==================== -->
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary); font-size: 0.9rem;">
                    <i class="fas fa-chart-line me-2" style="color: var(--win-accent);"></i>Estadísticas de Facturas (Cierre Operativo.)
                </h6>
                
                <button class="btn btn-card-toggle collapsed" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseEstadisticas" 
                        aria-expanded="false"
                        title="Expandir/Minimizar">
                    <i class="fas fa-chevron-up toggle-icon"></i>
                </button>
            </div>
            
            <!-- Envoltura colapsable -->
            <div class="collapse" id="collapseEstadisticas">
                <div class="card-body p-3">
                    <!-- Primera fila: Estadísticas principales compactas -->
                    <div class="estadisticas-compactas-container">
                        <!-- Hoy (Emisión) -->
                        <div class="estadistica-compacta-card estadistica-hoy">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Hoy (Emitido)</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['dia']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['dia']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    <?php echo date('d/m/Y', strtotime($hoy_operativo)); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mes Actual (Emisión) -->
                        <div class="estadistica-compacta-card estadistica-mes">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Mes (Emitido)</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['mes_actual']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['mes_actual']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    <?php echo $mes_actual_es; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pagadas (Mes) -->
                        <div class="estadistica-compacta-card estadistica-pagadas-mes">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Pagadas (Mes)</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['pagadas_mes']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['pagadas_mes']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    Cobrado este mes
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contabilizadas (Mes) -->
                        <div class="estadistica-compacta-card estadistica-contabilizadas">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Contab. (Mes)</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['contabilizadas']['mes']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['contabilizadas']['mes']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    Por fecha contab.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contabilizadas (Año) -->
                        <div class="estadistica-compacta-card estadistica-contabilizadas">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Contab. (Año)</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['contabilizadas']['anio']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['contabilizadas']['anio']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    Acumulado año
                                </div>
                            </div>
                        </div>

                        <!-- Total Base de Datos -->
                        <div class="estadistica-compacta-card estadistica-total">
                            <div class="estadistica-compacta-header">
                                <div class="estadistica-compacta-title">Total BD</div>
                                <div class="estadistica-compacta-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                            </div>
                            <div class="estadistica-compacta-values">
                                <div class="estadistica-compacta-cantidad">
                                    <?php echo $estadisticas_facturas['total_bd']['cantidad']; ?>
                                </div>
                                <div class="estadistica-compacta-importe">
                                    $<?php echo number_format($estadisticas_facturas['total_bd']['importe'], 2); ?>
                                </div>
                                <div class="estadistica-compacta-subtitle">
                                    Total histórico
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Segunda fila: Distribución por estados compactas -->
                    <div class="mt-3">
                        <h6 class="mb-2" style="color: var(--win-text-primary); font-size: 0.85rem;">
                            <i class="fas fa-chart-pie me-2"></i>Distribución por Estados (Total BD)
                        </h6>
                        <div class="estadisticas-estado-compactas">
                            <!-- Pagadas -->
                            <div class="estadistica-estado-compacta">
                                <span class="estado-badge-mini bg-primary">Pagadas</span>
                                <div class="estado-cantidad-mini">
                                    <?php echo $estadisticas_facturas['estados']['PAGADA']['cantidad']; ?>
                                </div>
                                <div class="estado-importe-mini">
                                    $<?php echo number_format($estadisticas_facturas['estados']['PAGADA']['importe'], 2); ?>
                                </div>
                            </div>
                            
                            <!-- Contabilizadas -->
                            <div class="estadistica-estado-compacta">
                                <span class="estado-badge-mini bg-success">Contabilizadas</span>
                                <div class="estado-cantidad-mini">
                                    <?php echo $estadisticas_facturas['estados']['CONTABILIZADA']['cantidad']; ?>
                                </div>
                                <div class="estado-importe-mini">
                                    $<?php echo number_format($estadisticas_facturas['estados']['CONTABILIZADA']['importe'], 2); ?>
                                </div>
                            </div>
                            
                            <!-- Pendientes -->
                            <div class="estadistica-estado-compacta">
                                <span class="estado-badge-mini bg-warning text-dark">Pendientes</span>
                                <div class="estado-cantidad-mini">
                                    <?php echo $estadisticas_facturas['estados']['PENDIENTE']['cantidad']; ?>
                                </div>
                                <div class="estado-importe-mini">
                                    $<?php echo number_format($estadisticas_facturas['estados']['PENDIENTE']['importe'], 2); ?>
                                </div>
                            </div>
                            
                            <!-- CERRADAS - NUEVA SECCIÓN -->
                            <div class="estadistica-estado-compacta">
                                <span class="estado-badge-mini bg-secondary">Cerradas</span>
                                <div class="estado-cantidad-mini">
                                    <?php echo $estadisticas_facturas['estados']['CERRADA']['cantidad'] ?? 0; ?>
                                </div>
                                <div class="estado-importe-mini">
                                    $<?php echo number_format($estadisticas_facturas['estados']['CERRADA']['importe'] ?? 0, 2); ?>
                                </div>
                            </div>
                            
                            <!-- Anuladas -->
                            <div class="estadistica-estado-compacta">
                                <span class="estado-badge-mini bg-danger">Anuladas</span>
                                <div class="estado-cantidad-mini">
                                    <?php echo $estadisticas_facturas['estados']['ANULADA']['cantidad']; ?>
                                </div>
                                <div class="estado-importe-mini">
                                    $<?php echo number_format($estadisticas_facturas['estados']['ANULADA']['importe'], 2); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje informativo cuando se filtra por cliente específico -->
        <?php if (!empty($nombre_cliente_filtro)): ?>
            <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Filtro activo:</strong> Mostrando facturas del cliente 
                <strong>"<?php echo $nombre_cliente_filtro; ?>"</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-2">
                    <a href="facturas.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-times me-1"></i>Mostrar todas las facturas
                    </a>
                    <a href="ver_cliente.php?id=<?php echo $cliente_id_val; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                        <i class="fas fa-user me-1"></i>Ver información del cliente
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mensaje informativo cuando se filtra por servicio específico -->
        <?php if (!empty($nombre_servicio_filtro)): ?>
            <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-filter me-2"></i>
                <strong>Filtro por Servicio:</strong> Mostrando facturas que incluyen el servicio 
                <strong>"<?php echo $nombre_servicio_filtro; ?>"</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-2">
                    <a href="facturas.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-times me-1"></i>Mostrar todas las facturas
                    </a>
                    <a href="servicios.php?id=<?php echo is_array($servicio_id_val) ? $servicio_id_val[0] : $servicio_id_val; ?>" class="btn btn-sm btn-outline-info ms-2">
                        <i class="fas fa-eye me-1"></i>Ver detalles del servicio
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Contenedor de filtros y resumen -->
        <div class="filtros-resumen-container">
            <!-- Filtros (izquierda) -->
            <div class="filtros-card">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-filter me-1"></i>Filtrado y Búsqueda de Facturas
                        </h6>
                        <button class="btn btn-card-toggle" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapseFiltros" 
                                aria-expanded="false">
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </button>
                    </div>
                    <!-- CON clase 'show' para que inicie abierta -->
                    <div class="collapse hide" id="collapseFiltros">
                        <div class="card-body">
                            <!-- IMPORTANTE: Cambiado a GET para mantener la paginación -->
                            <form method="GET" action="">
                                <!-- Mantener parámetros de paginación ocultos -->
                                <input type="hidden" name="pagina" value="1">
                                <input type="hidden" name="registros" value="<?php echo $registros_por_pagina; ?>">
                                
                                <div class="row g-2">
                                    <div class="col-12 mb-2">
                                        <label class="form-label text-primary fw-bold">Criterios de Fecha</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white"><i class="fas fa-filter"></i></span>
                                            <select class="form-select fw-bold" name="tipo_fecha">
                                                <option value="emision" <?php echo ($tipo_fecha_val == 'emision') ? 'selected' : ''; ?>>Fecha de Emisión</option>
                                                <option value="contabilizacion" <?php echo ($tipo_fecha_val == 'contabilizacion') ? 'selected' : ''; ?>>Fecha de Contabilización</option>
                                                <option value="pago" <?php echo ($tipo_fecha_val == 'pago') ? 'selected' : ''; ?>>Fecha de Pago</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- FECHAS (Ahora debajo del tipo) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Desde</label>
                                        <div class="input-group">
                                            <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio"
                                                   value="<?php echo htmlspecialchars($fecha_inicio_val); ?>">
                                            <button type="button" class="btn btn-outline-secondary" onclick="setFechaHoy('fecha_inicio')" title="Hoy (Operativo)">
                                                <i class="fas fa-calendar-day"></i> Hoy
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hasta</label>
                                        <div class="input-group">
                                            <input type="date" class="form-control" name="fecha_fin" id="fecha_fin"
                                                   value="<?php echo htmlspecialchars($fecha_fin_val); ?>">
                                            <button type="button" class="btn btn-outline-secondary" onclick="setFechaHoy('fecha_fin')" title="Hoy (Operativo)">
                                                <i class="fas fa-calendar-day"></i> Hoy
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Filtro por Mes (MODIFICADO) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Mes</label>
                                        <select class="form-select" name="mes">
                                            <option value="">Todos los meses</option>
                                            <?php 
                                            $meses = [
                                                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', 
                                                '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', 
                                                '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', 
                                                '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                                            ];
                                            foreach ($meses as $valor => $nombre_mes): 
                                                // Verificar si es el mes de cierre
                                                $es_mes_operativo = (intval($valor) == intval($mes_cierre_num));
                                                $texto_adicional = $es_mes_operativo ? ' (Actual/Operativo)' : '';
                                                $clase_estilo = $es_mes_operativo ? 'font-weight: bold; color: var(--win-accent);' : '';
                                                
                                                // Determinar si debe estar seleccionado
                                                $selected = ($mes_val == $valor) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $valor; ?>" 
                                                        style="<?php echo $clase_estilo; ?>"
                                                        <?php echo ($mes_val == $valor) ? 'selected' : ''; ?>>
                                                    <?php echo $nombre_mes . $texto_adicional; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Filtro por Año (MODIFICADO) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Año</label>
                                        <select class="form-select" name="anio">
                                            <option value="">Todos los años</option>
                                            <?php 
                                            $anio_actual_real = date('Y');
                                            foreach ($anios_disponibles as $anio_option): 
                                                $val_anio = $anio_option['anio'];
                                                $texto_adicional = '';
                                                $clase_estilo = '';
                                                
                                                if ($val_anio == $anio_cierre_num) {
                                                    $texto_adicional = ' (Operaciones)';
                                                    $clase_estilo = 'font-weight: bold; color: var(--win-accent);';
                                                } elseif ($val_anio == $anio_actual_real) {
                                                    $texto_adicional = ' (Actual Calendario)';
                                                }
                                                // Determinar si debe estar seleccionado
                                                $selected = ($anio_val == $val_anio) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo htmlspecialchars($val_anio); ?>"
                                                        style="<?php echo $clase_estilo; ?>"
                                                        <?php echo ($anio_val == $val_anio) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($val_anio) . $texto_adicional; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Filtro por Cliente -->
                                    <div class="col-md-6">
                                        <label class="form-label">Cliente</label>
                                        <select class="form-select" name="cliente_id" id="clienteSelect" size="20">
                                            <option value="">Todos los clientes</option>
                                            <?php foreach ($clientes as $cliente): ?>
                                                <option value="<?php echo htmlspecialchars($cliente['id']); ?>"
                                                        <?php echo ($cliente_id_val == $cliente['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Filtro por Servicio (organizado por categorías) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Servicio</label>
                                        <div class="select-search-wrapper">
                                            <input type="text" 
                                                   class="form-control form-control-sm mb-2 select-search-input" 
                                                   placeholder="Buscar servicio..." 
                                                   id="servicioSearch">
                                            <select class="form-select" name="servicio_id[]" id="servicioSelect" size="18" multiple>
                                                <option value="">Todos los servicios</option>
                                                
                                                <?php if (isset($servicios_por_categoria) && !empty($servicios_por_categoria)): ?>
                                                    <?php foreach ($servicios_por_categoria as $categoria => $servicios_cat): ?>
                                                        <optgroup label="<?php echo htmlspecialchars($categoria); ?>">
                                                        <?php foreach ($servicios_cat as $servicio): ?>
                                                            <option value="<?php echo htmlspecialchars($servicio['id']); ?>"
                                                                    <?php 
                                                                    // Manejar selección múltiple
                                                                    if (!empty($servicio_id_val) && is_array($servicio_id_val)) {
                                                                        echo in_array($servicio['id'], $servicio_id_val) ? 'selected' : '';
                                                                    }
                                                                    ?>>
                                                                <?php echo htmlspecialchars($servicio['codigo'] ? $servicio['codigo'] . ' - ' . $servicio['nombre'] : $servicio['nombre']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        </optgroup>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php foreach ($servicios as $servicio): ?>
                                                        <option value="<?php echo htmlspecialchars($servicio['id']); ?>"
                                                                <?php echo ($servicio_id_val == $servicio['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($servicio['descripcion']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <small class="text-info"><i class="fas fa-computer-mouse me-1"></i><i class="fas fa-keyboard"></i> Use Ctrl+Click seleccionar múltiples servicios</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Estado de la Factura</label>
                                        <select class="form-select" name="estado">
                                            <option value="">Todos</option>
                                            <option value="CONTABILIZADA" <?php echo ($estado_val == 'CONTABILIZADA') ? 'selected' : ''; ?>>Contabilizadas</option>
                                            <option value="PAGADA" <?php echo ($estado_val == 'PAGADA') ? 'selected' : ''; ?>>Pagadas</option>
                                            <option value="PENDIENTE" <?php echo ($estado_val == 'PENDIENTE') ? 'selected' : ''; ?>>Pendientes</option>
                                            <option value="ANULADA" <?php echo ($estado_val == 'ANULADA') ? 'selected' : ''; ?>>Anuladas</option>
                                            <option value="CERRADA" <?php echo ($estado_val == 'CERRADA') ? 'selected' : ''; ?>>Cerradas</option>
                                            <option value="PAGADA_SIN_REF" <?php echo ($estado_val == 'PAGADA_SIN_REF') ? 'selected' : ''; ?> style="color: #ffc107; font-weight: bold;">
                                                ⚠️ Pagadas (sin Referencia Pago)
                                            </option>
                                            <option value="CERRADA_SIN_REF" <?php echo ($estado_val == 'CERRADA_SIN_REF') ? 'selected' : ''; ?> style="color: #6c757d; font-weight: bold; background-color: #f8f9fa;">
                                                🔒 Cerradas (sin Pagar)
                                            </option>
                                            <option value="SIN_REF" <?php echo ($estado_val == 'SIN_REF') ? 'selected' : ''; ?> style="color: #6c757d; font-weight: bold; background-color: #f8f9fa;">
                                                💲 Sin Referencia de Pago
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Número de Factura</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-hashtag"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="no_factura" 
                                                   placeholder="Ej: FV-aaaammdd####, 053, fv-9"
                                                   value="<?php echo htmlspecialchars($no_factura_val); ?>">
                                        </div>
                                        <small class="text-info"><i class="fas fa-magnifying-glass me-1"></i>Ingrese criterio de búsqueda de la factura</small>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search me-1"></i>Aplicar Filtros
                                        </button>
                                        <a href="facturas.php" class="btn btn-outline-secondary btn-sm ms-2">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                        <?php if ($estado_val == 'PAGADA_SIN_REF'): ?>
                                            <span class="badge bg-warning text-dark ms-2 filtro-activo-badge">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Mostrando: Pagadas sin Referencia
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($estado_val == 'CERRADA_SIN_REF'): ?>
                                            <span class="badge bg-secondary ms-2 filtro-activo-badge">
                                                <i class="fas fa-lock me-1"></i>Mostrando: Cerradas sin Referencia
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($filtros)): ?>
                                            <span class="badge bg-info filtro-activo-badge ms-2">
                                                <i class="fas fa-filter me-1"></i>Filtros activos
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($fecha_inicio_val) || !empty($fecha_fin_val) || !empty($mes_val) || !empty($anio_val)): ?>
                                            <span class="badge bg-primary ms-2">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                Criterio: 
                                                <?php 
                                                    if($tipo_fecha_val == 'emision') echo 'Emisión';
                                                    elseif($tipo_fecha_val == 'contabilizacion') echo 'Contabilización';
                                                    elseif($tipo_fecha_val == 'pago') echo 'Pago';
                                                ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($no_factura_val)): ?>
                                            <span class="badge bg-primary ms-2">
                                                <i class="fas fa-hashtag me-1"></i>Buscando: <?php echo htmlspecialchars($no_factura_val); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($cliente_id_val)): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="fas fa-user me-1"></i>Cliente: <?php echo htmlspecialchars($nombre_cliente_filtro); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($servicio_id_val)): ?>
                                            <span class="badge bg-info text-dark ms-2">
                                                <i class="fas fa-cogs me-1"></i>Servicio: <?php echo htmlspecialchars($nombre_servicio_filtro); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($mes_val) && !empty($anio_val)): ?>
                                            <span class="badge bg-warning ms-2">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php 
                                                $meses = [
                                                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', 
                                                    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', 
                                                    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', 
                                                    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                                                ];
                                                echo $meses[$mes_val] . ' ' . $anio_val; 
                                                ?>
                                            </span>
                                        <?php elseif (!empty($mes_val)): ?>
                                            <span class="badge bg-warning ms-2">
                                                <i class="fas fa-calendar me-1"></i>Mes: <?php 
                                                $meses = [
                                                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', 
                                                    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', 
                                                    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', 
                                                    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                                                ];
                                                echo $meses[$mes_val]; 
                                                ?>
                                            </span>
                                        <?php elseif (!empty($anio_val)): ?>
                                            <span class="badge bg-warning ms-2">
                                                <i class="fas fa-calendar me-1"></i>Año: <?php echo $anio_val; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen (derecha) -->
            <div class="resumen-card">
                <div class="card h-100 d-flex flex-column">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-chart-bar me-1"></i>Resumen (Por Página)
                        </h6>
                        <button class="btn btn-card-toggle" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapseResumen" 
                                aria-expanded="false">
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </button>
                    </div>                    
                    <div class="collapse hide" id="collapseResumen">
                        <div class="card-body p-3">
                            <?php if (!empty($facturas)): ?>
                                <!-- CARD 1: TOTALES -->
                                <div class="card card-totales mb-3">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="m-0 fw-bold">
                                            <i class="fas fa-calculator me-1"></i>Totales
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="resumen-estado-item">
                                            <span class="text-muted">Subtotal:</span>
                                            <span class="float-end fw-bold" style="color: #28a745;">$<?php echo number_format($total_subtotal, 2); ?></span>
                                        </div>
                                        <div class="resumen-estado-item mt-2">
                                            <span class="text-muted">Total:</span>
                                            <span class="float-end resumen-total fw-bold">$<?php echo number_format($total_general, 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- CARD 2: POR ESTADO -->
                                <div class="card card-estado mb-3">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="m-0 fw-bold">
                                            <i class="fas fa-tags me-1"></i>Por Estado
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <!-- Contabilizadas -->
                                        <div class="resumen-estado-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <span class="badge bg-success estado-badge">Contabilizada</span>
                                                    <small class="text-muted ms-1"><?php echo $totales_estado['CONTABILIZADA']['cantidad']; ?></small>
                                                </span>
                                                <span class="fw-bold">$<?php echo number_format($totales_estado['CONTABILIZADA']['total'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Pagadas -->
                                        <div class="resumen-estado-item mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <span class="badge bg-primary estado-badge">Pagada</span>
                                                    <small class="text-muted ms-1"><?php echo $totales_estado['PAGADA']['cantidad']; ?></small>
                                                </span>
                                                <span class="fw-bold">$<?php echo number_format($totales_estado['PAGADA']['total'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Pendientes -->
                                        <div class="resumen-estado-item mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <span class="badge bg-warning text-dark estado-badge">Pendiente</span>
                                                    <small class="text-muted ms-1"><?php echo $totales_estado['PENDIENTE']['cantidad']; ?></small>
                                                </span>
                                                <span class="fw-bold">$<?php echo number_format($totales_estado['PENDIENTE']['total'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Anuladas -->
                                        <div class="resumen-estado-item mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <span class="badge bg-danger estado-badge">Anulada</span>
                                                    <small class="text-muted ms-1"><?php echo $totales_estado['ANULADA']['cantidad']; ?></small>
                                                </span>
                                                <span class="fw-bold">$<?php echo number_format($totales_estado['ANULADA']['total'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- CARD 3: DISTRIBUCIÓN -->
                                <div class="card card-distribucion mb-3">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="m-0 fw-bold">
                                            <i class="fas fa-chart-pie me-1"></i>Distribución
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <?php if ($total_general > 0): ?>
                                            <?php 
                                            $porc_contabilizada = ($totales_estado['CONTABILIZADA']['total'] / $total_general) * 100;
                                            $porc_pagada = ($totales_estado['PAGADA']['total'] / $total_general) * 100;
                                            $porc_pendiente = ($totales_estado['PENDIENTE']['total'] / $total_general) * 100;
                                            $porc_anulada = ($totales_estado['ANULADA']['total'] / $total_general) * 100;
                                            ?>
                                            <div class="mb-2">
                                                <small>Contabilizadas:</small>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $porc_contabilizada; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($porc_contabilizada, 1); ?>%</small>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <small>Pagadas:</small>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" 
                                                             style="width: <?php echo $porc_pagada; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($porc_pagada, 1); ?>%</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small>Pendientes:</small>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-warning" role="progressbar" 
                                                             style="width: <?php echo $porc_pendiente; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($porc_pendiente, 1); ?>%</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small>Anuladas:</small>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-danger" role="progressbar" 
                                                             style="width: <?php echo $porc_anulada; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($porc_anulada, 1); ?>%</small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center mb-0 small">No hay datos para mostrar</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- CARD 4: INFORMACIÓN DE PÁGINA -->
                                <div class="card card-paginacion">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="m-0 fw-bold">
                                            <i class="fas fa-list-ol me-1"></i>Información de Página
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="resumen-estado-item">
                                            <span class="text-muted">Mostrando:</span>
                                            <span class="float-end fw-bold">
                                                <?php 
                                                if ($total_registros > 0) {
                                                    if ($mostrar_todo) {
                                                        echo 'TODOS los ' . $total_registros . ' registros';
                                                    } else {
                                                        $inicio = $offset + 1;
                                                        $fin = min($offset + $registros_por_pagina, $total_registros);
                                                        echo $inicio . ' - ' . $fin . ' de ' . $total_registros;
                                                    }
                                                } else {
                                                    echo '0 resultados';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (!$mostrar_todo): ?>
                                        <div class="resumen-estado-item mt-2">
                                            <span class="text-muted">Registros por página:</span>
                                            <span class="float-end">
                                                <select class="form-select form-select-sm d-inline-block w-auto" 
                                                        id="registrosPorPagina" 
                                                        onchange="cambiarRegistrosPorPagina(this.value)">
                                                    <option value="5" <?php echo $registros_por_pagina == 5 ? 'selected' : ''; ?>>5</option>
                                                    <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                                    <option value="20" <?php echo $registros_por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                                                    <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                                    <option value="100" <?php echo $registros_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                                                </select>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($mostrar_todo): ?>
                                        <div class="alert alert-warning mt-2 p-2 mb-0">
                                            <small><i class="fas fa-exclamation-triangle me-1"></i>Mostrando todas las facturas. Para volver a la paginación normal, haga clic en "Limpiar" o aplique un filtro.</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <!-- Mensaje cuando no hay facturas -->
                                <div class="card">
                                    <div class="card-body text-center py-4">
                                        <i class="fas fa-file-invoice text-muted fa-3x mb-3"></i>
                                        <p class="text-muted">No hay facturas para mostrar</p>
                                        <?php if (!empty($no_factura_val)): ?>
                                            <p class="text-warning small">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                No se encontró la factura con número: <?php echo htmlspecialchars($no_factura_val); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($servicio_id_val)): ?>
                                            <p class="text-warning small">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                No se encontraron facturas con el servicio seleccionado
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div> <!-- Cierre del card-body -->
                    </div> <!-- Cierre del collapse -->
                </div> <!-- Cierre del card principal -->
            </div> <!-- Cierre del resumen-card -->
        </div>

        <!-- Tabla de Facturas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 style="color: var(--win-text-primary);" class="m-0 fw-bold"><i class="fas fa-list me-1"></i>Lista de Facturas</h6>
                <?php if (!empty($facturas)): ?>
                    <div>
                        <span class="badge bg-info me-2">
                            <?php echo $contador_facturas; ?> facturas 
                            <?php echo $mostrar_todo ? '(TODAS)' : 'en esta página'; ?>
                        </span>
                        <span class="badge bg-secondary">Total: <?php echo $total_registros; ?> registros</span>
                        <?php if (!empty($no_factura_val)): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="fas fa-hashtag me-1"></i>
                                Factura: <?php echo htmlspecialchars($no_factura_val); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($cliente_id_val) && !empty($nombre_cliente_filtro)): ?>
                            <span class="badge bg-success ms-2">
                                <i class="fas fa-user me-1"></i>
                                <a style="color: var(--win-text-primary);" href="ver_cliente.php?id=<?php echo $cliente_id_val; ?>">Clientes: <?php echo $nombre_cliente_filtro; ?></a>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($mes_val) && !empty($anio_val)): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php 
                                $meses = [
                                    '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', 
                                    '04' => 'Abr', '05' => 'May', '06' => 'Jun', 
                                    '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', 
                                    '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
                                ];
                                echo $meses[$mes_val] . ' ' . $anio_val; 
                                ?>
                            </span>
                        <?php elseif (!empty($mes_val)): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="fas fa-calendar me-1"></i>Mes: <?php 
                                $meses = [
                                    '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', 
                                    '04' => 'Abr', '05' => 'May', '06' => 'Jun', 
                                    '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', 
                                    '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
                                ];
                                echo $meses[$mes_val]; 
                                ?>
                            </span>
                        <?php elseif (!empty($anio_val)): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="fas fa-calendar me-1"></i>Año: <?php echo $anio_val; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ==================== SECCIÓN DE ELIMINACIÓN MÚLTIPLE ==================== -->
            <?php if ($puedeEliminar && !empty($facturas)): ?>
            <div class="card-header border-bottom-0 py-2" style="background: var(--win-bg-tertiary);">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="seleccionarTodosBtn" 
                                   style="cursor: pointer; width: 18px; height: 18px;">
                            <label class="form-check-label" for="seleccionarTodosBtn" style="color: var(--win-text-primary);">
                                <strong>Seleccionar todos</strong>
                            </label>
                        </div>
                        <span id="contadorSeleccionados" class="badge bg-secondary">0 seleccionadas</span>
                    </div>
                    <div>
                        <button id="btnEliminarSeleccionadas" class="btn btn-danger btn-sm" disabled 
                                style="opacity: 0.5; transition: all 0.3s ease;">
                            <i class="fas fa-trash-alt me-1"></i> Eliminar seleccionadas
                            <span id="contadorEliminar" class="ms-1 badge bg-light text-dark">0</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- ==================== FIN SECCIÓN ELIMINACIÓN MÚLTIPLE ==================== -->
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <?php if ($puedeEliminar && !empty($facturas)): ?>
                                <th style="width: 40px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="seleccionarTodosCheckbox" style="cursor: pointer;">
                                    </div>
                                </th>
                                <?php endif; ?>
                                <th><?php echo linkOrdenamiento('no_fact', 'No. Factura', $sort, $order); ?></th>
                                <th><?php echo linkOrdenamiento('cliente', 'Cliente', $sort, $order); ?></th>
                                <th><?php echo linkOrdenamiento('emision', 'Fecha Em.', $sort, $order); ?></th>
                                <th><?php echo linkOrdenamiento('contabilizacion', 'Fecha Cont.', $sort, $order); ?></th>
                                <th><?php echo linkOrdenamiento('total', 'Total', $sort, $order); ?></th>
                                <th><?php echo linkOrdenamiento('estado', 'Estado', $sort, $order); ?></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($facturas)): ?>
                                <tr>
                                    <td colspan="<?php echo ($puedeEliminar && !empty($facturas)) ? '8' : '7'; ?>" class="text-center py-4">
                                        <i class="fas fa-file-invoice text-muted fa-2x mb-2 d-block"></i>
                                        No hay facturas registradas
                                        <?php if (!empty($filtros)): ?>
                                            <br>
                                            <small class="text-muted">Prueba con otros criterios de búsqueda</small>
                                            <?php if (!empty($no_factura_val)): ?>
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    No se encontró la factura con número: <?php echo htmlspecialchars($no_factura_val); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($cliente_id_val)): ?>
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    El cliente seleccionado no tiene facturas registradas
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($servicio_id_val)): ?>
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    El servicio seleccionado no está asociado a ninguna factura
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($facturas as $factura): ?>
<?php
$estado = $factura['estado'] ?? 'PENDIENTE';

// Estados que NUNCA se pueden eliminar (seguridad)
$estadosNoEliminables = ['CERRADA', 'PAGADA'];
// También verificar si es CerradaPagada (cuando tiene fecha_pago y referencia)
$esCerradaConPago = (
    $estado == 'CERRADA' && 
    !empty($factura['fecha_pago']) && 
    $factura['fecha_pago'] != '0000-00-00' && 
    $factura['fecha_pago'] != '0000-00-00 00:00:00' &&
    !empty($factura['Ref_pago'])
);

// Determinar si esta factura puede ser eliminada
$puedeEliminarEstaFactura = false;

if ($esAdmin) {
    // ADMIN: Puede eliminar SOLO si NO está en estado CERRADA, PAGADA o CerradaConPago
    if (!in_array($estado, $estadosNoEliminables) && !$esCerradaConPago) {
        $puedeEliminarEstaFactura = true;
    }
} elseif (($esEditor || $esSuper || $usuario['rol_id'] == 5) && ($estado == 'ANULADA')) {
    // Roles 3, 4, 5: Solo pueden eliminar facturas ANULADAS (y que no estén en estados protegidos)
    $puedeEliminarEstaFactura = true;
}
?>
                                    <tr data-factura-id="<?php echo $factura['id']; ?>" 
                                        data-factura-no="<?php echo htmlspecialchars($factura['no_fact']); ?>"
                                        data-puede-eliminar="<?php echo $puedeEliminarEstaFactura ? 'true' : 'false'; ?>">
                                        
                                        <?php if ($puedeEliminar && !empty($facturas)): ?>
                                        <td class="align-middle">
                                            <?php if ($puedeEliminarEstaFactura): ?>
                                            <div class="form-check">
                                                <input class="form-check-input factura-checkbox" type="checkbox" 
                                                       value="<?php echo $factura['id']; ?>"
                                                       data-nofact="<?php echo htmlspecialchars($factura['no_fact']); ?>"
                                                       data-cliente="<?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?>"
                                                       data-total="<?php echo $factura['total_general'] ?? 0; ?>"
                                                       style="cursor: pointer;">
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted" title="Esta factura no puede ser eliminada">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <a href="ver_factura.php?no_fact=<?php echo urlencode($factura['no_fact']); ?>" 
                                               class="text-white text-decoration-none" 
                                               title="Ver esta factura" data-bs-toggle="tooltip">
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?>
                                                </span>
                                            </a>
                                        </td>
                                        <td><a style="color: var(--win-text-primary);" href="ver_cliente.php?id=<?php echo htmlspecialchars($factura['cliente_id'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?></a></td>
                                        <td>
                                            <?php 
                                            if (!empty($factura['fecha_emision'])) {
                                                echo date('d/m/Y', strtotime($factura['fecha_emision']));
                                            } else {
                                                echo 'S/F';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($factura['fecha_contabilizacion'])) {
                                                echo date('d/m/Y', strtotime($factura['fecha_contabilizacion']));
                                            } else {
                                                echo 'S/F';
                                            }
                                            ?>
                                        </td>
                                        <td><strong>$<?php echo number_format($factura['total_general'] ?? 0, 2); ?></strong></td>
                                        <td>
                                            <?php
                                            // Determinar si es una factura CERRADA pero con fecha de pago y referencia
                                            $esCerradaConPago = (
                                                $estado == 'CERRADA' && 
                                                !empty($factura['fecha_pago']) && 
                                                $factura['fecha_pago'] != '0000-00-00' && 
                                                $factura['fecha_pago'] != '0000-00-00 00:00:00' &&
                                                !empty($factura['Ref_pago'])
                                            );
                                            
                                            // Si es Cerrada con pago, mostrarlo como CerradaPagada visualmente
                                            if ($esCerradaConPago):
                                            ?>
                                                <span class="badge bg-info text-dark fw-bold">CerradaPagada</span>
                                            <?php else: 
                                                // Lógica normal de badges
                                                $badge_class = '';
                                                switch ($estado) {
                                                    case 'CONTABILIZADA': $badge_class = 'bg-success'; break;
                                                    case 'PAGADA': $badge_class = 'bg-primary'; break;
                                                    case 'PENDIENTE': $badge_class = 'bg-warning'; break;
                                                    case 'ANULADA': $badge_class = 'bg-danger'; break;
                                                    default: $badge_class = 'bg-secondary';
                                                }
                                            ?>
                                                <span class="badge <?php echo $badge_class; ?> fw-bold">
                                                    <?php echo htmlspecialchars($estado); ?>
                                                </span>
                                            <?php endif; ?>

                                            <!-- Mostrar Fecha de Pago si existe -->
                                            <?php if (!empty($factura['fecha_pago']) && $factura['fecha_pago'] != '0000-00-00'): ?>
                                                <small class="d-block mt-1 text-muted">
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    <?php echo date('d/m/Y', strtotime($factura['fecha_pago'])); ?>
                                                </small>
                                            <?php endif; ?>

                                            <!-- Mostrar Referencia de Pago si existe -->
                                            <?php if (!empty($factura['Ref_pago'])): ?>
                                                <small class="d-block text-info">
                                                    <i class="fas fa-hashtag me-1"></i>
                                                    Ref: <?php echo htmlspecialchars($factura['Ref_pago']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php
                                                // Verificar si el estado es CERRADA
                                                $esCerrada = ($estado == 'CERRADA');
                                                
                                                // Verificar si la fecha de emisión es anterior a fecha_inicio_operaciones
                                                $esAnteriorAOperaciones = false;
                                                if (!empty($fecha_inicio_operaciones) && !empty($factura['fecha_emision'])) {
                                                    $fechaEmision = date('Y-m-d', strtotime($factura['fecha_emision']));
                                                    $esAnteriorAOperaciones = (strtotime($fechaEmision) < strtotime($fecha_inicio_operaciones));
                                                }
                                                
                                                // Determinar si debe mostrar solo ver/imprimir
                                                $soloLectura = ($esCerrada || $esAnteriorAOperaciones);
                                                
                                                // 1. Botón VER - Siempre visible
                                                ?>
                                                <a href="ver_factura.php?id=<?php echo $factura['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Ver Detalle" data-bs-toggle="tooltip">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <!-- 2. Botón IMPRIMIR - Siempre visible -->
                                                <a href="ver_factura.php?id=<?php echo $factura['id']; ?>&autoPrint=true" 
                                                   target="_blank"
                                                   class="btn btn-outline-info" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   title="Imprimir Factura">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                
                                                <?php 
                                                // MOSTRAR BOTÓN PARA REFERENCIA DE PAGO SI ESTADO ES CERRADA Y FECHA_PAGO ESTÁ VACÍA
                                                $fechaPagoVacia = (
                                                    empty($factura['fecha_pago']) || 
                                                    $factura['fecha_pago'] == '0000-00-00' || 
                                                    $factura['fecha_pago'] == '0000-00-00 00:00:00'
                                                );

                                                if ($estado == 'CERRADA' && $fechaPagoVacia): 
                                                ?>
                                                    <button onclick="marcarComoPagada(
                                                        <?php echo $factura['id']; ?>, 
                                                        '<?php echo addslashes($factura['no_fact']); ?>', 
                                                        '<?php echo addslashes($factura['cliente_nombre']); ?>', 
                                                        '<?php echo number_format($factura['total_general'], 2); ?>',
                                                        '<?php echo $factura['fecha_emision']; ?>',
                                                        '<?php echo addslashes($factura['tipo_pago']); ?>'
                                                    )" 
                                                    class="btn btn-warning" 
                                                    title="Registrar referencia de pago - Factura CERRADA sin fecha de pago" 
                                                    data-bs-toggle="tooltip">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!$soloLectura): ?>
                                                    <!-- Solo mostrar estos botones si NO está en modo solo lectura -->
                                                    
                                                    <?php
                                                    // 3. Lógica de detección: ¿Es una factura pagada pero sin el dato de referencia?
                                                    $faltaReferencia = ($estado == 'PAGADA' && (empty($factura['Ref_pago']) || trim($factura['Ref_pago']) == ''));

                                                    // 4. Permisos para botones estándar (No se edita ni anula si ya está PAGADA y completa)
                                                    $puedeEditarEstaFactura = ($esAdmin || $esEditor || $esSuper) && ($estado != 'PAGADA');
                                                    $puedeContabilizarEstaFactura = ($esAdmin || $esEditor || $esSuper) && ($estado != 'PAGADA');
                                                    $puedeAnularEstaFactura = ($esAdmin || $esEditor || $esSuper) && ($estado != 'PAGADA');
                                                    
                                                    // 5. Lógica del Botón de Pago: 
                                                    // Se muestra si está CONTABILIZADA (flujo normal) 
                                                    // O si ya está PAGADA pero le falta la referencia (para corrección)
                                                    $puedeMarcarPagadaEstaFactura = ($esAdmin || $esEditor || $esSuper) && ($estado == 'CONTABILIZADA' || $faltaReferencia);
                                                    
                                                    // 6. Permiso especial para ELIMINAR facturas ANULADAS para roles 1, 3, 4, 5
                                                    $puedeEliminarAnulada = ($esAdmin || $esEditor || $esSuper || $usuario['rol_id'] == 5) && ($estado == 'ANULADA');
                                                    ?>
                                                    
                                                    <?php if ($estado != 'PAGADA'): ?>
                                                        <!-- BOTÓN EDITAR: Solo si no está pagada -->
                                                        <?php if ($puedeEditarEstaFactura): ?>
                                                            <a href="editar_factura.php?id=<?php echo $factura['id']; ?>" 
                                                               class="btn btn-outline-warning" 
                                                               title="Editar factura" data-bs-toggle="tooltip">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- BOTÓN CONTABILIZAR: Solo si no está pagada -->
                                                        <?php if ($puedeContabilizarEstaFactura): ?>
                                                            <?php 
                                                            $accionC = ($estado == 'CONTABILIZADA') ? 'descontabilizar' : 'contabilizar';
                                                            $textoC = ($estado == 'CONTABILIZADA') ? 'Descontabilizar' : 'Contabilizar';
                                                            $iconoC = ($estado == 'CONTABILIZADA') ? 'fas fa-times-circle' : 'fas fa-check-circle';
                                                            $claseC = ($estado == 'CONTABILIZADA') ? 'btn-outline-warning' : 'btn-outline-success';
                                                            ?>
                                                            <a href="contabilizar.php?id=<?php echo $factura['id']; ?>&accion=<?php echo $accionC; ?>" 
                                                               class="btn <?php echo $claseC; ?>" 
                                                               title="<?php echo $textoC; ?>" data-bs-toggle="tooltip"
                                                               onclick="event.preventDefault(); confirmarAccion('<?php echo $accionC; ?>', this)">
                                                                <i class="<?php echo $iconoC; ?>"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- BOTÓN ANULAR: Solo si no está pagada -->
                                                        <?php if ($puedeAnularEstaFactura): ?>
                                                            <?php 
                                                            $accionA = ($estado == 'ANULADA') ? 'reactivar' : 'anular';
                                                            $iconoA = ($estado == 'ANULADA') ? 'fas fa-redo' : 'fas fa-ban';
                                                            $claseA = ($estado == 'ANULADA') ? 'btn-outline-success' : 'btn-outline-danger';
                                                            ?>
                                                            <button onclick="anularFactura(<?php echo $factura['id']; ?>, '<?php echo addslashes($factura['no_fact']); ?>', '<?php echo $accionA; ?>')" 
                                                                    class="btn <?php echo $claseA; ?>" 
                                                                    title="<?php echo ucfirst($accionA); ?> Factura" data-bs-toggle="tooltip">
                                                                <i class="<?php echo $iconoA; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- BOTÓN REGISTRAR PAGO: Lógica de corrección incluida -->
                                                    <?php if ($puedeMarcarPagadaEstaFactura): ?>
                                                        <button onclick="marcarComoPagada(
                                                            <?php echo $factura['id']; ?>, 
                                                            '<?php echo addslashes($factura['no_fact']); ?>', 
                                                            '<?php echo addslashes($factura['cliente_nombre']); ?>', 
                                                            '<?php echo number_format($factura['total_general'], 2); ?>',
                                                            '<?php echo $factura['fecha_emision']; ?>',
                                                            '<?php echo addslashes($factura['tipo_pago']); ?>'
                                                            )" 
                                                        class="btn <?php echo ($faltaReferencia) ? 'btn-warning' : 'btn-outline-success'; ?>" 
                                                        title="<?php echo ($faltaReferencia) ? '¡FALTA REFERENCIA DE PAGO!' : 'Registrar Cobro'; ?>" 
                                                        data-bs-toggle="tooltip">
                                                            <i class="fas fa-money-bill-wave"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <!-- BOTÓN ELIMINAR: Solo para Administradores (rol 1) Y para facturas ANULADAS para roles 1, 3, 4, 5 -->
                                                    <?php 
                                                    // Condición 1: Es rol 1 (administrador) - puede eliminar cualquier factura
                                                    // Condición 2: Es rol 1, 3, 4, o 5 Y la factura está ANULADA
                                                    $puedeEliminar = false;
                                                    
                                                    if ($esAdmin) {
                                                        // Administrador puede eliminar cualquier factura
                                                        $puedeEliminar = true;
                                                    } elseif (($esEditor || $esSuper || $usuario['rol_id'] == 5) && ($estado == 'ANULADA')) {
                                                        // Roles 3, 4, 5 solo pueden eliminar facturas ANULADAS
                                                        $puedeEliminar = true;
                                                    }
                                                    ?>
                                                    
<?php 
// Verificar nuevamente que no sea un estado protegido
$estadosProtegidos = ['CERRADA', 'PAGADA'];
$esProtegida = in_array($estado, $estadosProtegidos) || $esCerradaConPago;

if ($puedeEliminar && !$esProtegida): ?>
<button onclick="eliminarFactura(
    <?php echo $factura['id']; ?>, 
    '<?php echo addslashes($factura['no_fact']); ?>', 
    '<?php echo $estado; ?>', 
    '<?php echo addslashes($factura['cliente_nombre'] ?? 'N/A'); ?>', 
    '<?php echo $factura['total_general'] ?? 0; ?>'
)" 
class="btn btn-outline-danger" 
title="Eliminar permanentemente (solo facturas ANULADAS o PENDIENTES)" 
data-bs-toggle="tooltip">
    <i class="fas fa-trash"></i>
</button>
<?php elseif ($esProtegida): ?>
    <button class="btn btn-outline-secondary" disabled 
            title="Las facturas en estado <?php echo $estado . ($esCerradaConPago ? ' (CerradaPagada)' : ''); ?> no se pueden eliminar" 
            data-bs-toggle="tooltip">
        <i class="fas fa-lock"></i>
    </button>
<?php endif; ?>
                                                <?php else: ?>
                                                    <!-- Si está en modo solo lectura, mostrar indicador -->
                                                    <?php if ($esCerrada): ?>
                                                        <span class="badge bg-secondary" data-bs-toggle="tooltip" title="Factura cerrada - solo lectura">
                                                            <i class="fas fa-lock me-1"></i>CERRADA
                                                        </span>
                                                    <?php elseif ($esAnteriorAOperaciones): ?>
                                                        <span class="badge bg-dark" data-bs-toggle="tooltip" title="Factura anterior al inicio de operaciones - solo lectura">
                                                            <i class="fas fa-history me-1"></i>HISTÓRICA
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
<!-- Fila de totales al final de la tabla -->
<tr class="totales-row">
    <?php 
    $columnas_totales = 7; // Número base de columnas
    if ($puedeEliminar && !empty($facturas)) {
        $columnas_totales = 8;
    }
    ?>
    <td colspan="<?php echo $columnas_totales - 2; ?>" class="text-end fw-bold">
        <i class="fas fa-calculator me-1"></i>TOTAL GENERAL (esta página):
    </td>
    <td class="fw-bold text-primary text-end" style="background-color: rgba(var(--win-accent-rgb), 0.15);">
        <strong>$<?php echo number_format($total_general, 2); ?></strong>
    </td>
    <td class="text-center">
        <span class="badge bg-dark">
            <i class="fas fa-file-invoice me-1"></i><?php echo $contador_facturas; ?> facturas
        </span>
    </td>
</tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ==================== PAGINACIÓN ==================== -->
                <?php if ($total_paginas > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navegación de páginas">
                        <div class="pagination-info text-center mb-2">
                            <small class="text-muted">
                                Mostrando página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> 
                                (<?php echo $total_registros; ?> registros totales)
                            </small>
                        </div>
                        
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Botón Anterior -->
                            <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo $pagina_actual > 1 ? construirUrlPaginacion($pagina_actual - 1) : '#'; ?>"
                                   aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <!-- Primera página -->
                            <?php if ($pagina_actual > 3): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo construirUrlPaginacion(1); ?>">1</a>
                                </li>
                                <?php if ($pagina_actual > 4): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Páginas alrededor de la actual -->
                            <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                                <li class="page-item <?php echo $pagina_actual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo construirUrlPaginacion($i); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Última página -->
                            <?php if ($pagina_actual < $total_paginas - 2): ?>
                                <?php if ($pagina_actual < $total_paginas - 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo construirUrlPaginacion($total_paginas); ?>">
                                        <?php echo $total_paginas; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Botón Siguiente -->
                            <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="<?php echo $pagina_actual < $total_paginas ? construirUrlPaginacion($pagina_actual + 1) : '#'; ?>"
                                   aria-label="Siguiente">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Selector de página directa -->
                        <div class="d-flex justify-content-center align-items-center mt-3 flex-wrap gap-2">
                            <span class="me-2 text-muted">Ir a página:</span>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <input type="number" 
                                       id="irPagina" 
                                       class="form-control" 
                                       min="1" 
                                       max="<?php echo $total_paginas; ?>" 
                                       value="<?php echo $pagina_actual; ?>"
                                       style="background: var(--win-bg-tertiary); border-color: var(--win-border-color); color: var(--win-text-primary);">
                                <button class="btn btn-primary" type="button" onclick="irAPagina()">
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                            <!-- Botón Mostrar Todo -->
                            <button class="btn btn-outline-secondary btn-sm ms-2" onclick="mostrarTodo()" title="Mostrar todas las facturas sin paginación">
                                <i class="fas fa-list-alt me-1"></i>Mostrar Todo
                            </button>
                        </div>
                    </nav>
                </div>
                <?php endif; ?>
                <!-- ==================== FIN PAGINACIÓN ==================== -->
            </div>
        </div>
    </main>

    <!-- Quick Actions -->
    <?php if ($puedeEditarFacturas): ?>
    <div class="win-quick-actions">
        <!-- Acciones expandidas (ocultas por defecto) -->
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
            <button class="win-quick-action action-chatbot" onclick="toggleChatbot()" title="Abrir Asistente Virtual" id="chatbotQuickAction">
                <i class="fas fa-robot"></i>
            </button>
        </div>
        
        <!-- Botón principal -->
        <button class="win-quick-action" onclick="toggleQuickActions()" title="Acciones rápidas" id="mainQuickAction">
            <i class="fas fa-plus"></i>
        </button>
    </div>
    <?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>

    <!-- SWEETALERT DE SESIÓN - BARRA DE TIEMPO SIMPLE -->
    <?php if (isset($_SESSION['sweet_alert'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const timerValue = <?php echo $_SESSION['sweet_alert']['timer'] ?? 'null'; ?>;
                
                Swal.fire({
                    icon: '<?php echo $_SESSION['sweet_alert']['icon']; ?>',
                    title: '<?php echo addslashes($_SESSION['sweet_alert']['title']); ?>',
                    html: '<?php echo addslashes($_SESSION['sweet_alert']['html'] ?? ''); ?>',
                    confirmButtonText: '<?php echo addslashes($_SESSION['sweet_alert']['confirmButtonText']); ?>',
                    
                    // Temporizador
                    timer: timerValue,
                    timerProgressBar: timerValue !== null,
                    
                    // Comportamiento
                    allowOutsideClick: <?php echo $_SESSION['sweet_alert']['allowOutsideClick'] ? 'true' : 'false'; ?>,
                    allowEscapeKey: <?php echo $_SESSION['sweet_alert']['allowEscapeKey'] ? 'true' : 'false'; ?>,
                    showCloseButton: true,
                    showConfirmButton: <?php echo $_SESSION['sweet_alert']['showConfirmButton'] ? 'true' : 'false'; ?>,
                    
                    // Tema
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#ffffff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e0e0e0' : '#333333',
                    confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--win-accent').trim(),
                    
                    position: '<?php echo $_SESSION['sweet_alert']['position'] ?? 'center'; ?>',
                    width: '<?php echo $_SESSION['sweet_alert']['width'] ?? '500px'; ?>'
                });
            });
        </script>
        <?php unset($_SESSION['sweet_alert']); ?>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- BLOQUE FALTANTE: ALERTAS DE CONTABILIZAR/DESCONTABILIZAR -->
    <!-- ======================================================= -->

    <?php if (isset($_SESSION['swal_success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '<?php echo addslashes($_SESSION['swal_success']['title']); ?>',
                    text: '<?php echo addslashes($_SESSION['swal_success']['text']); ?>',
                    icon: '<?php echo $_SESSION['swal_success']['icon']; ?>',
                    confirmButtonText: '<?php echo addslashes($_SESSION['swal_success']['confirmButtonText'] ?? 'Aceptar'); ?>',
                    confirmButtonColor: 'var(--win-accent)'
                });
            });
        </script>
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '<?php echo addslashes($_SESSION['swal_error']['title']); ?>',
                    html: '<?php echo addslashes($_SESSION['swal_error']['text']); ?>',
                    icon: '<?php echo $_SESSION['swal_error']['icon']; ?>',
                    confirmButtonText: '<?php echo addslashes($_SESSION['swal_error']['confirmButtonText'] ?? 'Cerrar'); ?>',
                    confirmButtonColor: 'var(--win-accent)',
                    customClass: {
                        popup: 'dark-swal-popup',
                        title: 'dark-swal-title',
                        htmlContainer: 'dark-swal-text'
                    }
                });
            });
        </script>
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_warning'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '<?php echo addslashes($_SESSION['swal_warning']['title']); ?>',
                    html: '<?php echo addslashes($_SESSION['swal_warning']['text']); ?>',
                    icon: '<?php echo $_SESSION['swal_warning']['icon']; ?>',
                    confirmButtonText: '<?php echo addslashes($_SESSION['swal_warning']['confirmButtonText'] ?? 'Entendido'); ?>',
                    confirmButtonColor: 'var(--win-accent)',
                    customClass: {
                        popup: 'dark-swal-popup',
                        title: 'dark-swal-title',
                        htmlContainer: 'dark-swal-text'
                    }
                });
            });
        </script>
        <?php unset($_SESSION['swal_warning']); ?>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- FIN BLOQUE FALTANTE -->
    <!-- ======================================================= -->
    
    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        // --- CONSTANTE PARA BOTONES HOY (INYECTADA DESDE PHP) ---
        const FECHA_HOY_OPERATIVA = "<?php echo $hoy_operativo; ?>";
        
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
        
function eliminarFactura(id, nofact, estado, cliente, total) {
    // Estados protegidos que no se pueden eliminar
    const estadosProtegidos = ['CERRADA', 'PAGADA'];
    
    // Formatear el total con separador de miles y 2 decimales
    const totalFormateado = parseFloat(total).toLocaleString('es-CU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    if (estadosProtegidos.includes(estado)) {
        Swal.fire({
            icon: 'error',
            title: '<span style="color: #ff6b6b;">❌ No se puede eliminar</span>',
            html: `
                <div style="text-align: center;">
                    <i class="fas fa-shield-alt" style="font-size: 48px; color: #dc3545; margin-bottom: 15px; display: block;"></i>
                    <p>La factura <strong style="color: #ffc107;">${nofact}</strong> está en estado <strong class="text-danger">${estado}</strong>.</p>
                    <p class="text-muted">Las facturas en estado CERRADA o PAGADA no se pueden eliminar por razones de seguridad y auditoría.</p>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3085d6',
            background: '#1e1e1e',
            color: '#e0e0e0',
            customClass: {
                popup: 'dark-swal-popup-win11'
            }
        });
        return;
    }
    
    Swal.fire({
        title: '<span style="color: #ff6b6b;">⚠️ ¿Eliminar Factura?</span>',
        html: `
            <div style="text-align: center;">
                <!-- Tarjeta de información de la factura -->
                <div style="background: #2d2d2d; border-radius: 12px; padding: 16px; margin: 10px 0; border-left: 4px solid #dc3545;">
                    <div style="font-size: 11px; color: #a6a6a6; letter-spacing: 1px; margin-bottom: 8px;">FACTURA NÚMERO: <span style="font-size: 18px; font-weight: 600; color: #ff6b6b; font-family: monospace; margin-bottom: 12px;">${nofact}</span></div>
                    
                    
                    <div style="border-top: 1px solid #3d3d3d; padding-top: 12px; margin-top: 4px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #a6a6a6; font-size: 12px;"><i class="fas fa-user me-2"></i>Cliente:</span>
                            <span style="color: #e0e0e0; font-size: 13px; font-weight: 500;">${cliente}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #a6a6a6; font-size: 12px;"><i class="fas fa-dollar-sign me-2"></i>Total:</span>
                            <span style="color: #ffc107; font-size: 16px; font-weight: 700;">$${totalFormateado}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #a6a6a6; font-size: 12px;"><i class="fas fa-tag me-2"></i>Estado:</span>
                            <span style="color: #e0e0e0; font-size: 12px;">
                                <span class="badge ${estado === 'ANULADA' ? 'bg-danger' : (estado === 'PENDIENTE' ? 'bg-warning' : 'bg-secondary')}" style="font-size: 10px;">
                                    ${estado}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Advertencia de eliminación -->
                <div style="background: rgba(220, 53, 69, 0.1); border-radius: 8px; padding: 12px; margin: 15px 0;">
                    
                    <strong style="color: #ff6b6b;"><i class="fas fa-exclamation-triangle me-2" style="font-size: 24px; color: #ffc107; margin-bottom: 8px;"></i>¡ADVERTENCIA!</strong>
                    <p class="mb-0" style="font-size: 13px; margin-top: 5px;">Esta acción es <strong>PERMANENTE</strong> y no se puede deshacer.</p>
                    <span class="mb-0 mt-2" style="font-size: 11px; color: #a6a6a6;">
                        <i class="fas fa-database me-1"></i> Se eliminarán todos los detalles y registros asociados
                    </span>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#3d3d3d',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar permanentemente',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        reverseButtons: true,
        background: '#1e1e1e',
        color: '#e0e0e0',
        customClass: {
            popup: 'dark-swal-popup-win11',
            confirmButton: 'swal-btn-danger-win11',
            cancelButton: 'swal-btn-secondary-win11'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading antes de redirigir
            Swal.fire({
                title: '<span style="color: #e0e0e0;">Eliminando factura...</span>',
                html: '<div class="spinner-border text-danger mt-3" role="status"></div><br><span class="text-muted">Procesando solicitud</span>',
                allowOutsideClick: false,
                showConfirmButton: false,
                background: '#1e1e1e',
                customClass: {
                    popup: 'dark-swal-popup-win11'
                },
                didOpen: () => {
                    setTimeout(() => {
                        window.location.href = 'eliminar_factura.php?id=' + id;
                    }, 800);
                }
            });
        }
    });
}
	   
	   
	   // Actualizar la fecha fin cuando se cambia la fecha inicio
        document.querySelector('input[name="fecha_inicio"]')?.addEventListener('change', function() {
            const fechaFin = document.querySelector('input[name="fecha_fin"]');
            if (fechaFin && fechaFin.value && new Date(fechaFin.value) < new Date(this.value)) {
                fechaFin.value = this.value;
            }
        });
        
        // Cuando se selecciona un mes o año, limpiar los campos de fecha
        document.querySelector('select[name="mes"]')?.addEventListener('change', function() {
            if (this.value) {
                document.querySelector('input[name="fecha_inicio"]').value = '';
                document.querySelector('input[name="fecha_fin"]').value = '';
            }
        });
        
        document.querySelector('select[name="anio"]')?.addEventListener('change', function() {
            if (this.value) {
                document.querySelector('input[name="fecha_inicio"]').value = '';
                document.querySelector('input[name="fecha_fin"]').value = '';
            }
        });
        
        // Cuando se selecciona una fecha, limpiar los filtros de mes y año
        document.querySelector('input[name="fecha_inicio"]')?.addEventListener('change', function() {
            if (this.value) {
                document.querySelector('select[name="mes"]').value = '';
                document.querySelector('select[name="anio"]').value = '';
            }
        });
        
        document.querySelector('input[name="fecha_fin"]')?.addEventListener('change', function() {
            if (this.value) {
                document.querySelector('select[name="mes"]').value = '';
                document.querySelector('select[name="anio"]').value = '';
            }
        });
        
        // Toggle Quick Actions con animación circular
        function toggleQuickActions() {
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (!expandedActions) return;
            
            const isExpanded = expandedActions.classList.contains('show');
            
            if (isExpanded) {
                // Contrae los botones con animación inversa
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            } else {
                // Expande los botones con animación
                expandedActions.classList.add('show');
                mainButton.innerHTML = '<i class="fas fa-times"></i>';
                mainButton.title = 'Ocultar acciones rápidas';
                mainButton.style.transform = 'rotate(45deg)';
            }
        }
        
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
        
        // Funcionalidad de búsqueda en el combo de servicios
        function inicializarBusquedaServicios() {
            const searchInput = document.getElementById('servicioSearch');
            const servicioSelect = document.getElementById('servicioSelect');
            
            if (!searchInput || !servicioSelect) return;
            
            // Al escribir en el campo de búsqueda
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const options = servicioSelect.options;
                const optgroups = servicioSelect.getElementsByTagName('optgroup');
                
                // Si no hay término de búsqueda, mostrar todo
                if (!searchTerm) {
                    for (let i = 0; i < options.length; i++) {
                        options[i].style.display = '';
                        options[i].style.fontWeight = 'normal';
                    }
                    for (let i = 0; i < optgroups.length; i++) {
                        optgroups[i].style.display = '';
                    }
                    return;
                }
                
                // Contador para mantener el seguimiento de las opciones mostradas por optgroup
                const optgroupCounter = {};
                
                // Primero ocultar todo
                for (let i = 0; i < options.length; i++) {
                    options[i].style.display = 'none';
                }
                
                // Buscar coincidencias
                for (let i = 0; i < options.length; i++) {
                    const option = options[i];
                    const text = option.text.toLowerCase();
                    const value = option.value;
                    
                    // No procesar el separador o la opción vacía
                    if (value === '' || text.includes('──────────────')) {
                        continue;
                    }
                    
                    // Verificar si coincide con el término de búsqueda
                    if (text.includes(searchTerm) || 
                        option.value.toLowerCase().includes(searchTerm)) {
                        option.style.display = '';
                        option.style.fontWeight = 'bold';
                        
                        // Contar opciones mostradas por optgroup
                        const parentGroup = option.parentElement;
                        if (parentGroup.tagName === 'OPTGROUP') {
                            const label = parentGroup.label;
                            optgroupCounter[label] = (optgroupCounter[label] || 0) + 1;
                        }
                    } else {
                        option.style.display = 'none';
                        option.style.fontWeight = 'normal';
                    }
                }
                
                // Mostrar/ocultar optgroups basado en si tienen opciones visibles
                for (let i = 0; i < optgroups.length; i++) {
                    const optgroup = optgroups[i];
                    const label = optgroup.label;
                    
                    if (optgroupCounter[label] > 0) {
                        optgroup.style.display = '';
                    } else {
                        optgroup.style.display = 'none';
                    }
                }
            });
            
            // Limpiar búsqueda al cambiar la selección
            servicioSelect.addEventListener('change', function() {
                searchInput.value = '';
                
                // Restaurar visibilidad
                const options = servicioSelect.options;
                const optgroups = servicioSelect.getElementsByTagName('optgroup');
                
                for (let i = 0; i < options.length; i++) {
                    options[i].style.display = '';
                    options[i].style.fontWeight = 'normal';
                }
                
                for (let i = 0; i < optgroups.length; i++) {
                    optgroups[i].style.display = '';
                }
            });
            
            // Limpiar búsqueda al hacer clic en el botón de limpiar
            document.querySelector('a[href="facturas.php"]')?.addEventListener('click', function() {
                searchInput.value = '';
            });
        }
        
        // Mostrar alerta y resaltar filtros según parámetros GET
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const clienteId = urlParams.get('cliente_id');
            const servicioId = urlParams.getAll('servicio_id');
            
            // Inicializar búsqueda en combo de servicios
            inicializarBusquedaServicios();
            
            // Scroll suave al formulario de filtros si hay parámetros
            if (clienteId || servicioId.length > 0) {
                const filtrosCard = document.querySelector('.filtros-card');
                if (filtrosCard) {
                    setTimeout(() => {
                        filtrosCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 500);
                }
                
                // Resaltar los selects correspondientes
                if (clienteId) {
                    const clienteSelect = document.getElementById('clienteSelect');
                    if (clienteSelect) {
                        clienteSelect.style.border = '2px solid var(--win-accent)';
                        clienteSelect.style.boxShadow = '0 0 0 0.25rem var(--win-accent-light)';
                    }
                }
                
                if (servicioId.length > 0) {
                    const servicioSelect = document.getElementById('servicioSelect');
                    if (servicioSelect) {
                        servicioSelect.style.border = '2px solid #ffc107';
                        servicioSelect.style.boxShadow = '0 0 0 0.25rem rgba(255, 193, 7, 0.25)';
                    }
                }
            }
            
            // Enfocar automáticamente el campo de búsqueda si hay un parámetro GET
            <?php if (!empty($no_factura_val)): ?>
                document.querySelector('input[name="no_factura"]')?.focus();
            <?php endif; ?>
        });
        
        // ==================== FUNCIONES DE PAGINACIÓN ====================
        function cambiarRegistrosPorPagina(valor) {
            const url = construirUrlPaginacion(1, valor);
            window.location.href = url;
        }
        
        function mostrarTodo() {
            const urlParams = new URLSearchParams(window.location.search);
            const mostrarTodoParam = urlParams.get('mostrar_todo');
            
            if (mostrarTodoParam === 'true') {
                Swal.fire({
                    title: 'Ya se muestran todas las facturas',
                    text: 'Actualmente se están mostrando todas las facturas sin paginación.',
                    icon: 'info',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: 'var(--win-accent)'
                });
                return;
            }
            
            Swal.fire({
                title: 'Mostrar todas las facturas',
                text: '¿Está seguro que desea mostrar todas las facturas sin paginación? Esto puede afectar el rendimiento si hay muchos registros.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check me-2"></i>Sí, Mostrar Todo',
                cancelButtonText: '<i class="fa fa-close me-2"></i>Cancelar',
                confirmButtonColor: 'var(--win-accent)',
                customClass: {
                    popup: 'dark-swal-popup',
                    title: 'dark-swal-title',
                    htmlContainer: 'dark-swal-text'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const url = new URL(window.location.href);
                    // Remover parámetros de paginación
                    url.searchParams.delete('pagina');
                    url.searchParams.delete('registros');
                    // Agregar parámetro para mostrar todo
                    url.searchParams.set('mostrar_todo', 'true');
                    window.location.href = url.toString();
                }
            });
        }
        
        function irAPagina() {
            const input = document.getElementById('irPagina');
            const pagina = parseInt(input.value);
            const totalPaginas = <?php echo $total_paginas; ?>;
            
            if (isNaN(pagina) || pagina < 1 || pagina > totalPaginas) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Página inválida',
                    text: `Por favor ingrese un número entre 1 y ${totalPaginas}`,
                    confirmButtonColor: 'var(--win-accent)'
                });
                input.value = <?php echo $pagina_actual; ?>;
                return;
            }
            
            const url = construirUrlPaginacion(pagina);
            window.location.href = url;
        }
        
        function construirUrlPaginacion(pagina, registrosPorPagina = null) {
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            
            if (registrosPorPagina !== null) {
                url.searchParams.set('registros', registrosPorPagina);
            }
            
            return url.toString();
        }
        
        // ==================== FIN FUNCIONES PAGINACIÓN ====================
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar panel de temas al hacer clic en overlay
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
            
            // Inicializar tooltips
            // Verificar si Bootstrap está cargado
            if (typeof bootstrap === 'undefined') {
                console.error("Error: Bootstrap no está cargado. Verifique la ruta del archivo js/bootstrap5.3.0/bootstrap.bundle.min.js");
            } else {
                // Inicializar tooltips con configuración robusta
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
            
            // Animar los badges de filtros activos
            const filtroActivoBadges = document.querySelectorAll('.filtro-activo-badge');
            filtroActivoBadges.forEach(badge => {
                setInterval(() => {
                    badge.classList.toggle('filtro-activo-badge');
                    setTimeout(() => {
                        badge.classList.toggle('filtro-activo-badge');
                    }, 100);
                }, 2000);
            });
        });

        function anularFactura(id, noFactura, accion = 'anular') {
            const esReactivar = (accion === 'reactivar');
            const titulo = esReactivar ? '¿Reactivar esta factura?' : '¿Anular esta factura?';
            const textoAccion = esReactivar ? 'reactivar' : 'anular';
            const textoEstado = esReactivar ? 'PENDIENTE' : 'ANULADA';
            const icono = esReactivar ? 'warning' : 'warning';
            const colorConfirmar = esReactivar ? '#198754' : '#d33';
            const textoConfirmar = esReactivar ? '<i class="fas fa-redo me-1"></i> Sí, reactivar' : '<i class="fas fa-ban me-1"></i> Sí, anular';
            
            Swal.fire({
                title: titulo,
                html: '<div class="text-start">' +
                      '<p>Esta acción cambiará el estado de la factura <strong>' + noFactura + '</strong> a <strong class="' + (esReactivar ? 'text-success' : 'text-danger') + '">' + textoEstado + '</strong>.</p>' +
                      '<div class="alert alert-warning mt-2 mb-0 p-2">' +
                      '<i class="fas fa-exclamation-triangle me-1"></i>' +
                      '<small><strong>Nota:</strong> Se registrará esta acción en el histórico del sistema.</small>' +
                      '</div>' +
                      '</div>',
                icon: icono,
                showCancelButton: true,
                confirmButtonColor: colorConfirmar,
                cancelButtonColor: '#3085d6',
                confirmButtonText: textoConfirmar,
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar carga
                    Swal.fire({
                        title: esReactivar ? 'Reactivando factura...' : 'Anulando factura...',
                        html: 'Procesando la ' + textoAccion + ' de la factura ' + noFactura,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Crear FormData para enviar los datos
                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('no_factura', noFactura);
                    formData.append('accion', accion);
                    
                    // Enviar solicitud
                    fetch('anular_factura.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: esReactivar ? '¡Factura reactivada!' : '¡Factura anulada!',
                                html: '<div class="text-start">' +
                                      '<p><strong>' + data.message + '</strong></p>' +
                                      '<div class="alert alert-success mt-2 mb-0 p-2">' +
                                      '<i class="fas fa-check-circle me-1"></i>' +
                                      '<small>La factura ha sido ' + textoAccion + ' correctamente. La página se recargará automáticamente.</small>' +
                                      '</div>' +
                                      '</div>',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: 'var(--win-accent)',
                                timer: 3000,
                                timerProgressBar: true
                            }).then(() => {
                                // Recargar la página para reflejar los cambios
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                html: '<div class="text-start">' +
                                      '<p><strong>' + (data.message || 'No se pudo ' + textoAccion + ' la factura') + '</strong></p>' +
                                      '<div class="alert alert-danger mt-2 mb-0 p-2">' +
                                      '<small>Por favor, intente nuevamente o contacte al administrador del sistema.</small>' +
                                      '</div>' +
                                      '</div>',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: 'var(--win-accent)'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Error completo:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            html: '<div class="text-start">' +
                                  '<p>Error al procesar la solicitud:</p>' +
                                  '<div class="alert alert-danger mt-2 mb-0 p-2">' +
                                  '<small><strong>Detalles:</strong> ' + error.message + '</small>' +
                                  '</div>' +
                                  '<div class="alert alert-info mt-2 p-2">' +
                                  '<small><strong>Posibles soluciones:</strong><br>' +
                                  '1. Verifique su conexión a internet<br>' +
                                  '2. Revise que el archivo anular_factura.php existe<br>' +
                                  '3. Contacte al administrador del sistema</small>' +
                                  '</div>' +
                                  '</div>',
                            confirmButtonText: '<i class="fa fa-check me-2"></i>Entendido',
                            confirmButtonColor: 'var(--win-accent)'
                        });
                    });
                }
            });
        }
        
        // Configuración global para SweetAlert2 (modo oscuro)
        const swalWithDarkMode = Swal.mixin({
            theme: {
                modal: 'dark',
                background: '#1a1a1a',
                title: '#ffffff',
                text: '#e0e0e0',
                confirmButton: '#0d6efd',
                cancelButton: '#6c757d'
            }
        });

        function confirmarAccion(accion, elemento) {
            swalWithDarkMode.fire({
                title: '¿Está seguro?',
                html: `¿Está seguro que desea <strong>${accion}</strong> esta factura?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check me-2"></i>Sí, continuar',
                cancelButtonText: '<i class="fa fa-close me-2"></i>Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: {
                    popup: 'dark-swal-popup',
                    title: 'dark-swal-title',
                    htmlContainer: 'dark-swal-text'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si confirma, redirige al enlace
                    window.location.href = elemento.href;
                }
            });
            
            return false;
        }
        
        // Función para marcar como pagada
        function marcarComoPagada(id, noFactura, cliente, importe, fechaEmisionFactura, tipoPago) {
            // ==========================================
            // 1. CONFIGURACIÓN Y ESTILOS
            // ==========================================
            const theme = document.documentElement.getAttribute('data-theme') || 'dark';
            const isDark = theme === 'dark';
            const style = getComputedStyle(document.documentElement);
            const bgColor = style.getPropertyValue('--win-bg-secondary').trim();
            const textColor = style.getPropertyValue('--win-text-primary').trim();
            const accentColor = style.getPropertyValue('--win-accent').trim();

            // Fallback por si no se envía el tipo de pago
            const textoTipoPago = tipoPago || 'No especificado';

            // Configuración base para todos los modals
            const swalConfig = {
                background: bgColor,
                color: textColor,
                width: '700px',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: { popup: 'mica-effect border-win' }
            };

            // Helpers
            const formatToFull12h = (timeStr) => {
                if (!timeStr) return "00:00:00 AM";
                const parts = timeStr.split(' '); 
                return `${parts[0]}:00 ${parts[1]}`; 
            };

            function formatearFechaDDMMYYYY(fechaISO) {
                if (!fechaISO) return '';
                const fechaPartes = fechaISO.split(' ')[0];
                const [anio, mes, dia] = fechaPartes.split('-');
                return `${dia}/${mes}/${anio}`;
            }

            const fechaEmisionFormateada = formatearFechaDDMMYYYY(fechaEmisionFactura);
            const fechaEmisionISO = fechaEmisionFactura.split(' ')[0];

            // ==========================================
            // 2. FUNCIÓN INTERNA: MOSTRAR FORMULARIO
            // ==========================================
            const mostrarFormularioPago = (datosPrevios = null) => {
                const ahora = new Date();
                
                let valorFecha, valorHora, valorRef;

                // Determinar valores iniciales
                if (datosPrevios) {
                    valorFecha = datosPrevios.f;
                    valorHora = datosPrevios.h;
                    valorRef = datosPrevios.r;
                } else {
                    const fechaOperativaActual = (typeof FECHA_HOY_OPERATIVA !== 'undefined') 
                                                ? FECHA_HOY_OPERATIVA 
                                                : ahora.toISOString().split('T')[0];
                    
                    valorFecha = (fechaEmisionISO > fechaOperativaActual) ? fechaEmisionISO : fechaOperativaActual;
                    
                    valorHora = ahora.toLocaleTimeString('en-US', { 
                        hour: '2-digit', minute: '2-digit', hour12: true 
                    });
                    valorRef = '';
                }

                // Función global para el botón HOY
                window.setPagoHoy = function() {
                    const input = document.getElementById('fechaPago');
                    if(input) {
                        const fOperativa = (typeof FECHA_HOY_OPERATIVA !== 'undefined') 
                                           ? FECHA_HOY_OPERATIVA 
                                           : new Date().toISOString().split('T')[0];
                        input.value = fOperativa;
                        input.dispatchEvent(new Event('input'));
                        input.dispatchEvent(new Event('change'));
                        input.focus();
                    }
                };

                const htmlForm = `
                    <div class="text-start" style="color: ${textColor}">
                        <div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                            <h6 class="mb-0">Registrar cobro para: <span class="fw-bold" style="color: ${accentColor}">${cliente}</span></h6>
                            <!-- AQUI MOSTRAMOS EL TIPO DE PAGO TAMBIÉN EN EL PRIMER MODAL -->
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small class="opacity-100">Factura: <span class="fw-bold" style="color: ${accentColor}">${noFactura}</span> | Importe: <span class="fw-bold" style="color: ${accentColor}">$${importe}</span></small>
                                <span class="badge bg-secondary">${textoTipoPago}</span>
                            </div>
                            <div class="mt-1">
                                <small class="text-warning">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Fecha de emisión: <strong>${fechaEmisionFormateada}</strong>
                                </small>
                            </div>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-md-6 mb-2 container-fecha">
                                <label class="form-label small fw-bold">Fecha de pago:</label>
                                <div class="input-group">
                                    <span class="input-group-text win-input-dynamic"><i class="fas fa-calendar-check"></i></span>
                                    <input type="date" id="fechaPago" class="form-control win-input-dynamic" 
                                           value="${valorFecha}" min="${fechaEmisionISO}"
                                           title="No puede ser anterior a la emisión">
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.setPagoHoy()" 
                                            style="border-color: var(--win-border-color); color: var(--win-text-primary);"
                                            title="Establecer fecha operativa actual">
                                        <i class="fas fa-calendar-day"></i>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">Mínimo: ${fechaEmisionFormateada}</small>
                            </div>
                            
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold">Hora de pago:</label>
                                <div class="input-group">
                                    <span class="input-group-text win-input-dynamic"><i class="fas fa-clock"></i></span>
                                    <input type="text" id="horaPago" class="form-control win-input-dynamic" 
                                           value="${valorHora}" readonly>
                                </div>
                            </div>
                            
                            <div class="col-12 mb-2">
                                <label class="form-label small fw-bold">Referencia de Pago:</label>
                                <div class="input-group">
                                    <span class="input-group-text win-input-dynamic"><i class="fas fa-receipt"></i></span>
                                    <input type="text" id="refPago" class="form-control win-input-dynamic" 
                                           placeholder="EJ: TRANSF-9988" style="text-transform: uppercase;" 
                                           value="${valorRef}" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                    <style>
                        .win-input-dynamic { background-color: var(--win-bg-tertiary) !important; border: 1px solid var(--win-border-color) !important; color: var(--win-text-primary) !important; }
                        input[type="date"]::-webkit-calendar-picker-indicator { filter: ${isDark ? 'invert(1)' : 'invert(0)'}; cursor: pointer; }
                        .border-win { border: 1px solid var(--win-border_color) !important; }
                    </style>
                `;

                Swal.fire({
                    ...swalConfig,
                    title: datosPrevios ? 'Corregir Pago' : 'Detalles del Pago',
                    html: htmlForm,
                    showCancelButton: true,
                    confirmButtonText: 'Siguiente <i class="fas fa-arrow-right ms-1"></i>',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                    didOpen: () => {
                        mdtimepicker('#horaPago', { timeFormat: 'hh:mm tt', theme: isDark ? 'dark' : 'blue', hourPadding: true });
                        
                        const inputRef = document.getElementById('refPago');
                        if (inputRef) {
                            setTimeout(() => {
                                inputRef.focus();
                                inputRef.setSelectionRange(inputRef.value.length, inputRef.value.length);
                            }, 100);
                        }

                        // Validación Visual
                        const fechaPagoInput = document.getElementById('fechaPago');
                        const validarVisualmente = () => {
                            const container = fechaPagoInput.closest('.col-md-6');
                            let errorDiv = container.querySelector('.invalid-feedback-custom');

                            if (fechaPagoInput.value && fechaEmisionISO && fechaPagoInput.value < fechaEmisionISO) {
                                fechaPagoInput.classList.add('is-invalid');
                                fechaPagoInput.classList.remove('is-valid');
                                if (!errorDiv) {
                                    errorDiv = document.createElement('div');
                                    errorDiv.className = 'invalid-feedback-custom text-danger fw-bold mt-1 animate__animated animate__fadeIn';
                                    errorDiv.style.fontSize = '0.85em';
                                    errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Fecha inválida<br>(Anterior a la Fecha de Emitida la Factura)`;
                                    container.appendChild(errorDiv);
                                }
                            } else {
                                fechaPagoInput.classList.remove('is-invalid');
                                if (fechaPagoInput.value) fechaPagoInput.classList.add('is-valid');
                                if (errorDiv) errorDiv.remove();
                            }
                        };
                        fechaPagoInput.addEventListener('change', validarVisualmente);
                        fechaPagoInput.addEventListener('input', validarVisualmente);
                        validarVisualmente();
                    },
                    preConfirm: () => {
                        const f = document.getElementById('fechaPago').value;
                        const h = document.getElementById('horaPago').value;
                        const r = document.getElementById('refPago').value.trim();
                        
                        if (!f || !h || !r) {
                            Swal.showValidationMessage('Todos los campos son obligatorios');
                            return false;
                        }
                        if (fechaEmisionISO && f < fechaEmisionISO) {
                            const [a, m, d] = f.split('-');
                            Swal.showValidationMessage(`La fecha (${d}/${m}/${a}) no puede ser anterior a la emisión`);
                            return false;
                        }
                        return { f, h, r: r.toUpperCase() };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        mostrarConfirmacion(result.value);
                    }
                });
            };

            // ==========================================
            // 3. FUNCIÓN INTERNA: CONFIRMACIÓN
            // ==========================================
            const mostrarConfirmacion = (datosCapturados) => {
                const fechaMostrar = datosCapturados.f.split('-').reverse().join('/');
                const horaConSegundos = formatToFull12h(datosCapturados.h);

                Swal.fire({
                    ...swalConfig,
                    title: '¿Confirmar Datos de Pago?',
                    icon: 'warning',
                    html: `
                        <div class="text-start p-3 rounded" style="background: rgba(128,128,128,0.1); color: ${textColor}; line-height: 1.6;">
                            <p class="mb-1"><b>Cliente:</b> <span style="color:yellow">${cliente}</span></p>
                            <p class="mb-1"><b>Factura:</b> ${noFactura}</p>
                            <p class="mb-1"><b>Método de Pago:</b> <span class="badge bg-secondary">${textoTipoPago}</span></p>
                            <p class="mb-3"><b>Total a Cobrar:</b> <span class="text-success fw-bold">$${importe}</span></p>
                            
                            <div style="border-top: 1px solid var(--win-border-color); padding-top: 10px;">
                                <p class="mb-1"><b>Fecha Pago:</b> ${fechaMostrar} a las ${horaConSegundos}</p>
                                <p class="mb-0"><b>Referencia:</b> <span style="color: ${accentColor}">${datosCapturados.r}</span></p>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Sí, Marcar Pagada',
                    cancelButtonText: '<i class="fas fa-edit me-1"></i> Corregir'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            ...swalConfig,
                            title: 'Procesando...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        const [time, modifier] = datosCapturados.h.split(' ');
                        let [hours, minutes] = time.split(':');
                        if (hours === '12') hours = '00';
                        if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
                        const hora24 = `${hours.toString().padStart(2, '0')}:${minutes}:00`;

                        const formData = new FormData();
                        formData.append('id', id);
                        formData.append('fecha_hora', `${datosCapturados.f} ${hora24}`);
                        formData.append('ref_pago', datosCapturados.r);
                        formData.append('accion', 'marcar_pagada');

                        fetch('marcar_factura_pagada.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ 
                                    ...swalConfig, icon: 'success', title: '¡Completado!', text: data.message,
                                    confirmButtonText: '<i class="fas fa-check me-1"></i> Aceptar'
                                }).then(() => { window.location.reload(); });
                            } else {
                                Swal.fire({ ...swalConfig, icon: 'error', title: 'Error', text: data.message });
                            }
                        })
                        .catch(() => {
                            Swal.fire({ ...swalConfig, icon: 'error', title: 'Error de Red', text: 'No se pudo contactar con el servidor' });
                        });

                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        mostrarFormularioPago(datosCapturados);
                    }
                });
            };

            mostrarFormularioPago();
        }
        
        // Función para establecer la fecha de hoy
        function setFechaHoy(elementId) {
            const input = document.getElementById(elementId);
            
            // Usar la fecha operativa global definida en JS
            if (typeof FECHA_HOY_OPERATIVA !== 'undefined') {
                input.value = FECHA_HOY_OPERATIVA;
            } else {
                // Fallback a fecha PC si algo falla
                const hoy = new Date();
                const yyyy = hoy.getFullYear();
                const mm = String(hoy.getMonth() + 1).padStart(2, '0');
                const dd = String(hoy.getDate()).padStart(2, '0');
                input.value = `${yyyy}-${mm}-${dd}`;
            }
            
            // Disparar el evento 'change' manualmente
            input.dispatchEvent(new Event('change'));
            input.focus();
        }
        
        // ==================== FUNCIONES PARA ELIMINACIÓN MÚLTIPLE ====================
        document.addEventListener('DOMContentLoaded', function() {
            // Referencias a elementos
            const seleccionarTodosCheckbox = document.getElementById('seleccionarTodosCheckbox');
            const seleccionarTodosBtn = document.getElementById('seleccionarTodosBtn');
            const checkboxesFactura = document.querySelectorAll('.factura-checkbox');
            const btnEliminarSeleccionadas = document.getElementById('btnEliminarSeleccionadas');
            const contadorSeleccionados = document.getElementById('contadorSeleccionados');
            const contadorEliminar = document.getElementById('contadorEliminar');
            
            // Función para actualizar el contador y el estado del botón
            function actualizarSeleccion() {
                const checkboxes = document.querySelectorAll('.factura-checkbox:checked');
                const cantidad = checkboxes.length;
                
                // Actualizar contadores
                if (contadorSeleccionados) {
                    contadorSeleccionados.textContent = cantidad + (cantidad === 1 ? ' seleccionada' : ' seleccionadas');
                }
                if (contadorEliminar) {
                    contadorEliminar.textContent = cantidad;
                }
                
                // Habilitar/deshabilitar botón de eliminar
                if (btnEliminarSeleccionadas) {
                    if (cantidad > 0) {
                        btnEliminarSeleccionadas.disabled = false;
                        btnEliminarSeleccionadas.style.opacity = '1';
                    } else {
                        btnEliminarSeleccionadas.disabled = true;
                        btnEliminarSeleccionadas.style.opacity = '0.5';
                    }
                }
                
                // Actualizar checkbox "Seleccionar todos"
                const totalCheckboxes = document.querySelectorAll('.factura-checkbox:not(:disabled)').length;
                const checkedCheckboxes = document.querySelectorAll('.factura-checkbox:checked:not(:disabled)').length;
                
                if (seleccionarTodosCheckbox) {
                    if (totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes) {
                        seleccionarTodosCheckbox.checked = true;
                        seleccionarTodosCheckbox.indeterminate = false;
                    } else if (checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes) {
                        seleccionarTodosCheckbox.checked = false;
                        seleccionarTodosCheckbox.indeterminate = true;
                    } else {
                        seleccionarTodosCheckbox.checked = false;
                        seleccionarTodosCheckbox.indeterminate = false;
                    }
                }
                
                if (seleccionarTodosBtn) {
                    if (totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes) {
                        seleccionarTodosBtn.checked = true;
                    } else {
                        seleccionarTodosBtn.checked = false;
                    }
                }
            }
            
            // Evento para "Seleccionar todos"
            if (seleccionarTodosCheckbox) {
                seleccionarTodosCheckbox.addEventListener('change', function(e) {
                    const isChecked = e.target.checked;
                    document.querySelectorAll('.factura-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                    actualizarSeleccion();
                });
            }
            
            if (seleccionarTodosBtn) {
                seleccionarTodosBtn.addEventListener('change', function(e) {
                    const isChecked = e.target.checked;
                    document.querySelectorAll('.factura-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                    actualizarSeleccion();
                });
            }
            
            // Evento para cada checkbox individual
            if (checkboxesFactura.length) {
                checkboxesFactura.forEach(checkbox => {
                    checkbox.addEventListener('change', actualizarSeleccion);
                });
            }
            
            // Función para eliminar múltiples facturas
            function eliminarFacturasSeleccionadas() {
                const checkboxesSeleccionados = document.querySelectorAll('.factura-checkbox:checked');
                const facturasSeleccionadas = Array.from(checkboxesSeleccionados).map(cb => ({
                    id: cb.value,
                    no_fact: cb.dataset.nofact,
                    cliente: cb.dataset.cliente,
                    total: cb.dataset.total
                }));
                
                if (facturasSeleccionadas.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Ninguna factura seleccionada',
                        text: 'Por favor, seleccione al menos una factura para eliminar.',
                        confirmButtonColor: 'var(--win-accent)'
                    });
                    return;
                }
                
                // Calcular total de las facturas seleccionadas
                const totalImporte = facturasSeleccionadas.reduce((sum, f) => sum + parseFloat(f.total), 0);
                
                // Construir lista de facturas para mostrar
                let listaFacturas = '<ul style="max-height: 300px; overflow-y: auto; text-align: left;">';
                facturasSeleccionadas.forEach(f => {
                    listaFacturas += `<li><strong>${f.no_fact}</strong> - ${f.cliente} - $${parseFloat(f.total).toFixed(2)}</li>`;
                });
                listaFacturas += '</ul>';
                
                Swal.fire({
                    title: '¿Eliminar facturas seleccionadas?',
                    html: `<div style="text-align: left;">
                            <p><strong>Está a punto de eliminar ${facturasSeleccionadas.length} factura(s):</strong></p>
                            ${listaFacturas}
                            <div class="alert alert-danger mt-3 p-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>¡ADVERTENCIA!</strong><br>
                                Esta acción es <strong>PERMANENTE</strong> y no se puede deshacer.<br>
                                Se eliminarán todos los detalles asociados a estas facturas.
                            </div>
                            <p class="mt-2"><strong>Total a eliminar:</strong> <span class="text-danger fw-bold">$${totalImporte.toFixed(2)}</span> Pesos</p>
                        </div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar permanentemente',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    reverseButtons: true,
                    customClass: {
                        popup: 'dark-swal-popup',
                        title: 'dark-swal-title',
                        htmlContainer: 'dark-swal-text'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar loading
                        Swal.fire({
                            title: 'Eliminando facturas...',
                            html: 'Procesando solicitud...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Crear FormData con los IDs
                        const formData = new FormData();
                        formData.append('accion', 'eliminar_multiple');
                        facturasSeleccionadas.forEach(f => {
                            formData.append('ids[]', f.id);
                        });
                        
                        // Enviar solicitud
                        fetch('eliminar_factura_multiple.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
.then(data => {
    Swal.close();
    if (data.success) {
        // Construir lista de facturas eliminadas
        let listaEliminadas = '';
        if (data.facturas && data.facturas.length > 0) {
            listaEliminadas = '<div style="background: #2d2d2d; border-radius: 10px; padding: 12px; margin-top: 15px; max-height: 200px; overflow-y: auto;">';
            listaEliminadas += '<div style="font-size: 12px; color: #a6a6a6; margin-bottom: 8px;"><i class="fas fa-list me-2"></i>Facturas eliminadas:</div>';
            data.facturas.forEach(f => {
                listaEliminadas += `<div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #3d3d3d; font-size: 13px;">
                    <span style="color: #ff6b6b; font-family: monospace;">${f.no_fact}</span>
                    <span style="color: #ffc107;">$${parseFloat(f.total).toFixed(2)}</span>
                </div>`;
            });
            listaEliminadas += '</div>';
        }
        
        // Construir lista de facturas NO eliminadas (errores)
        let listaNoEliminadas = '';
        if (data.facturas_no_eliminadas && data.facturas_no_eliminadas.length > 0) {
            listaNoEliminadas = '<div style="background: rgba(220, 53, 69, 0.1); border-radius: 10px; padding: 12px; margin-top: 10px;">';
            listaNoEliminadas += '<div style="font-size: 12px; color: #ff6b6b; margin-bottom: 8px;"><i class="fas fa-exclamation-triangle me-2"></i>No se pudieron eliminar:</div>';
            data.facturas_no_eliminadas.forEach(f => {
                listaNoEliminadas += `<div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px;">
                    <span style="color: #e0e0e0;">${f.no_fact}</span>
                    <span style="color: #a6a6a6;">${f.motivo}</span>
                </div>`;
            });
            listaNoEliminadas += '</div>';
        }
        
        Swal.fire({
            icon: 'success',
            title: '<span style="color: #28a745;">✓ ¡Eliminación Completada!</span>',
            html: `
                <div style="text-align: left;">
                    <!-- Tarjeta principal de éxito -->
                    <div style="background: linear-gradient(135deg, #1a3a1a 0%, #0d2d0d 100%); border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #2d5a2d; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #28a745;">${data.eliminadas} <span style="font-size: 13px; color: #a6a6a6;">factura(s) eliminada(s)</span></div>
                        
                        <div style="border-top: 1px solid #2d5a2d; margin-top: 12px; padding-top: 10px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #a6a6a6;">Total eliminado:</span>
                                <span style="color: #ffc107; font-weight: 700; font-size: 18px;">$${parseFloat(data.total_eliminado).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${listaEliminadas}
                    ${listaNoEliminadas}
                    
                    <!-- Pie de información -->
                    <div style="background: rgba(40, 167, 69, 0.1); border-radius: 8px; padding: 10px; margin-top: 15px; text-align: center;">
                        <i class="fas fa-history" style="font-size: 14px; color: #28a745; margin-right: 8px;"></i>
                        <span style="font-size: 12px; color: #a6a6a6;">Operación registrada en el historial del sistema</span>
                    </div>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            confirmButtonColor: '#28a745',
            background: '#1e1e1e',
            color: '#e0e0e0',
            width: '550px',
            timer: data.errores > 0 ? null : 40000,
            timerProgressBar: data.errores === 0,
            showConfirmButton: true,
            customClass: {
                popup: 'dark-swal-multiple-success',
                confirmButton: 'swal-btn-multiple-success'
            }
        }).then(() => {
            window.location.reload();
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: '<span style="color: #ff6b6b;">❌ Error en la eliminación</span>',
            html: `
                <div style="text-align: center;">
                    <div style="background: rgba(220, 53, 69, 0.1); border-radius: 12px; padding: 20px; border: 1px solid rgba(220, 53, 69, 0.3);">
                        <i class="fas fa-times-circle" style="font-size: 48px; color: #dc3545; margin-bottom: 15px; display: block;"></i>
                        <p style="color: #e0e0e0;">${data.message || 'Ocurrió un error al eliminar las facturas'}</p>
                    </div>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#dc3545',
            background: '#1e1e1e',
            color: '#e0e0e0',
            customClass: {
                popup: 'dark-swal-multiple-error'
            }
        });
    }
})
                        .catch(error => {
                            Swal.close();
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de conexión',
                                text: 'No se pudo completar la solicitud. Por favor, intente nuevamente.',
                                confirmButtonColor: 'var(--win-accent)'
                            });
                        });
                    }
                });
            }
            
            // Asignar evento al botón de eliminar seleccionadas
            if (btnEliminarSeleccionadas) {
                btnEliminarSeleccionadas.addEventListener('click', eliminarFacturasSeleccionadas);
            }
            
            // Inicializar contadores
            actualizarSeleccion();
        });
        // ==================== FIN FUNCIONES ELIMINACIÓN MÚLTIPLE ====================
    </script>
    <style>
        .dark-swal-popup {
            background-color: #1a1a1a !important;
            border: 1px solid #333 !important;
        }
        .dark-swal-title {
            color: #ffffff !important;
        }
        .dark-swal-text {
            color: #e0e0e0 !important;
        }
    </style>
    <?php
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
    ?>
</body>
</html>