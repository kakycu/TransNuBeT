<?php
// reportes.php - Windows 11 Style (Refactorizado Completo)
require_once 'config/header.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// --- 1. GESTIÓN DE ALERTAS Y SESIÓN ---
$alerta_exito = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : null;
$alerta_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : null;

// Limpiamos la sesión inmediatamente para evitar alertas fantasmas
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);
unset($_SESSION['alert_message']);

// Configuración Tema
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Variables CSS
$temas_windows = [
    'dark' => ['bg_primary' => '#0d0d0d', 'bg_secondary' => '#1f1f1f', 'bg_tertiary' => '#2d2d2d', 'text_primary' => '#ffffff', 'text_secondary' => '#a6a6a6', 'border_color' => '#3d3d3d'],
    'light' => ['bg_primary' => '#f3f3f3', 'bg_secondary' => '#ffffff', 'bg_tertiary' => '#fafafa', 'text_primary' => '#000000', 'text_secondary' => '#666666', 'border_color' => '#e5e5e5']
];
$tema_actual = $temas_windows[$tema_windows];
$colores_accent = ['#0078d4'=>'Azul', '#107c10'=>'Verde', '#5c2d91'=>'Morado', '#e81123'=>'Rojo', '#ff8c00'=>'Naranja', '#0099bc'=>'Cian', '#e3008c'=>'Rosa', '#8764b8'=>'Lila'];
$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_es = $meses_completos[date('n') - 1];

// Obtener la fecha maestra de la base de datos (usando tus funciones globales)
$mes_cierre_num = obtenerMesCierreOperaciones();
$anio_cierre_num = obtenerAnioCierreOperaciones();
// Si por error devuelve null, usamos la fecha actual como fallback
$fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d'); 
$timestamp_op = strtotime($fecha_op_raw);

// Variables de tiempo Maestras (Sincronizadas con la BD)
$anio_actual = date('Y', $timestamp_op);
$mes_actual  = date('n', $timestamp_op);
$dia_actual  = date('j');//date('j', $timestamp_op);
$total_dias_mes = date('t', $timestamp_op);
$hoy = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_actual);
$timestamp_combinado = strtotime($hoy);

$ultimodiaMesOperaciones = ultimoDiaMesFechaInicio(1);
$nombre_mes_completo = $meses_completos[date('n', strtotime($fecha_op_raw)) - 1];


// Fechas por defecto basadas en el mes/año de operaciones
// Calcular el primer día del mes de operaciones
$fecha_inicio_default = sprintf("%04d-%02d-01", $anio_cierre_num, $mes_cierre_num);

// Calcular el último día del mes de operaciones
$ultimo_dia_mes = date('t', strtotime($fecha_inicio_default)); // Obtiene el último día del mes
$fecha_fin_default = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $ultimo_dia_mes);

// Calcular el límite máximo para fecha_fin (31 de diciembre del año de operaciones)
$fecha_maxima_permitida = sprintf("%04d-12-31", $anio_cierre_num);

// Parámetros de filtro
$tipo_reporte = $_GET['tipo_reporte'] ?? 'ventas_generales';
$fecha_inicio = $_GET['fecha_inicio'] ?? $fecha_inicio_default;
$fecha_fin = $_GET['fecha_fin'] ?? $fecha_fin_default;

// Validar que la fecha_fin no exceda el 31 de diciembre del año de operaciones
if (isset($_GET['fecha_fin']) && strtotime($_GET['fecha_fin']) > strtotime($fecha_maxima_permitida)) {
    $fecha_fin = $fecha_maxima_permitida;
    // Opcional: mostrar una alerta al usuario
    $_SESSION['swal_warning'] = "La fecha final no puede ser posterior al 31 de diciembre del año de operaciones ($anio_cierre_num). Se ha ajustado automáticamente.";
}

// Parámetros de paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros_por_pagina']) ? intval($_GET['registros_por_pagina']) : 15;
$registros_por_pagina = in_array($registros_por_pagina, [5, 15, 30, 50, 100, 999999]) ? $registros_por_pagina : 15;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Parámetros de ordenamiento
$orden_columna = $_GET['orden_columna'] ?? 0;
$orden_direccion = $_GET['orden_direccion'] ?? 'asc';

$headers = [];
$data = [];
$titulo_reporte = '';
$descripcion_reporte = '';
$mostrar_totales = false;
$columnas_numericas = [];
$columna_dinero = null;
$total_registros = 0;
$total_general = 0;
$cantidad_registros = 0;

// Variables para estadísticas
$total_facturas = $total_clientes = $total_categorias = $total_servicios = $total_usuarios = 0;
$usuario = [];
$finanzas = [];
$estadisticas = [];

try {
    $db = Database::getConnection();
    
    // 1. Obtener usuario actual
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
    
    // 2. Obtener estadísticas del Sidebar
    $stmt = $db->query("SELECT COUNT(*) as total FROM tbl_fact"); $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_clientes"); $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_cat_de_serv"); $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_serv"); $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt = $db->query("SELECT COUNT(*) as total FROM clasif_usuarios"); $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 3. Obtener datos para histórico
    $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total);
    $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 4. Generar reporte según tipo
    switch ($tipo_reporte) {
case 'ventas_generales':
    $headers = ['No. Factura', 'Fecha', 'Cliente', 'Estado', 'Productos', 'Total'];
    $titulo_reporte = 'Facturación Detallada';
    $descripcion_reporte = 'Listado completo de facturas emitidas';
    $mostrar_totales = true;
    $columnas_numericas = [5, 6];
    $columna_dinero = 6;
    
    // **NUEVO: Obtener filtro de cliente**
    $cliente_filtro = isset($_GET['cliente_filtro']) ? $_GET['cliente_filtro'] : 'TODOS';
    
    // **NUEVO: Obtener lista de clientes para el select**
    $stmt_clientes = $db->query("SELECT id, nombre FROM clasif_clientes ORDER BY nombre");
    $lista_clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir condiciones WHERE para el conteo y consulta
    $where_conditions = ["f.fecha_emision BETWEEN ? AND ?"];
    $params = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];
    
    // **NUEVO: Aplicar filtro de cliente**
    if ($cliente_filtro !== 'TODOS') {
        $where_conditions[] = "f.cliente_id = ?";
        $params[] = $cliente_filtro;
    }
    
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
    
    // Total de registros
    $sql_count = "SELECT COUNT(*) as total 
                  FROM tbl_fact f 
                  LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                  $where_sql";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Construir consulta
    $order_by = '';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'CAST(RIGHT(f.no_fact, 4) AS UNSIGNED)',  // Ordena por últimos 4 dígitos
            2 => 'f.fecha_emision',
            3 => 'c.nombre',
            4 => 'f.estado',
            5 => '(SELECT COUNT(*) FROM tbl_fact_detalle fd WHERE fd.factura_id = f.id)',
            6 => 'f.total_general'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    } else {
        $order_by = "ORDER BY f.fecha_emision DESC";
    }
    
    // Modificar la consulta principal para aplicar la lógica del estado
    $sql = "SELECT f.no_fact as col1, 
            DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as col2, 
            c.nombre as col3, 
            CASE 
                WHEN f.estado = 'CERRADA' AND (f.fecha_pago IS NOT NULL AND f.fecha_pago != '') 
                THEN 'PAGADA'
                ELSE f.estado 
            END as col4,
            (SELECT COUNT(*) FROM tbl_fact_detalle fd WHERE fd.factura_id = f.id) as col5,
            f.total_general as col6
            FROM tbl_fact f 
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            $where_sql 
            $order_by 
            LIMIT $inicio, $registros_por_pagina";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Total general - NO incluir las facturas anuladas en la suma
    $sql_total = "SELECT 
					SUM(f.total_general) as total,
					SUM((SELECT COUNT(*) FROM tbl_fact_detalle fd WHERE fd.factura_id = f.id)) as total_items_global
				 FROM tbl_fact f 
				 $where_sql
				 AND f.estado != 'ANULADA'";
	$stmt_total = $db->prepare($sql_total);
	$stmt_total->execute($params);
	$total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);

	$total_general = $total_row['total'] ?? 0;
	$total_productos_global = $total_row['total_items_global'] ?? 0;
    
    // **AGREGAR ESTA CONSULTA PARA OBTENER FACTURAS ANULADAS**
    $sql_anuladas = "SELECT SUM(f.total_general) as total_anuladas, COUNT(*) as cantidad_anuladas
                    FROM tbl_fact f 
                    $where_sql
                    AND f.estado = 'ANULADA'";
    $stmt_anuladas = $db->prepare($sql_anuladas);
    $stmt_anuladas->execute($params);
    $anuladas_row = $stmt_anuladas->fetch(PDO::FETCH_ASSOC);
    
    // Actualizar descripción para incluir el cliente seleccionado
    if ($cliente_filtro !== 'TODOS') {
        // Buscar el nombre del cliente seleccionado
        $nombre_cliente = 'Cliente seleccionado';
        foreach ($lista_clientes as $cliente) {
            if ($cliente['id'] == $cliente_filtro) {
                $nombre_cliente = $cliente['nombre'];
                break;
            }
        }
        $descripcion_reporte .= " - Cliente: " . $nombre_cliente;
    }
    
    
    // También puedes agregar esta información a la descripción para verificar
    if ($anuladas_row && $anuladas_row['cantidad_anuladas'] > 0) {
        $descripcion_reporte .= " - Se excluyeron " . $anuladas_row['cantidad_anuladas'] . 
                               " factura(s) anulada(s) por un total de $" . 
                               number_format($anuladas_row['total_anuladas'] ?? 0, 2);
    }
    break;



case 'rango_correlativos':
    // AÑADIMOS 'Importe' a los encabezados
    $headers = ['Mes', 'Facturas Procesadas', 'Desde Fact. No', 'Hasta Fact. No', 'Importe'];
    $titulo_reporte = 'Rango de Facturación por Meses';
    $descripcion_reporte = 'Control de correlativos, cantidad de facturas e importes emitidos por período';
    $mostrar_totales = true; // Lo activamos para que se muestre el footer de totales
    $columnas_numericas = [2, 5]; // Actualizamos: Columna 2 (Facturas) y Columna 5 (Importe) son números
    $columna_dinero = 5; // La columna 5 (Importe) es dinero

    // Obtener filtros de año y mes
    $anio_filtro = isset($_GET['anio_filtro']) ? $_GET['anio_filtro'] : 'TODOS';
    $mes_filtro = isset($_GET['mes_filtro']) ? $_GET['mes_filtro'] : 'TODOS';
    
    // Obtener lista de años disponibles en la BD
    $stmt_anios = $db->query("SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact WHERE estado != 'ANULADA' ORDER BY anio DESC");
    $lista_anios = $stmt_anios->fetchAll(PDO::FETCH_COLUMN);
    
    // Si no hay años, usar el actual
    if (empty($lista_anios)) {
        $lista_anios = [date('Y')];
    }
    
    // Construir la consulta SQL con filtros dinámicos
    // AÑADIMOS SUM(f.total_general) a la selección
        $sql = "SELECT 
                YEAR(f1.fecha_emision) as anio,
                MONTH(f1.fecha_emision) as mes_num,
                UPPER(ELT(MONTH(f1.fecha_emision), 
                    'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
                    'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE')) as col1,
                COUNT(DISTINCT f1.id) as col2,
                (
                    SELECT f2.no_fact 
                    FROM tbl_fact f2 
                    WHERE YEAR(f2.fecha_emision) = YEAR(f1.fecha_emision) 
                      AND MONTH(f2.fecha_emision) = MONTH(f1.fecha_emision)
                      AND f2.estado != 'ANULADA'
                    ORDER BY CAST(RIGHT(f2.no_fact, 4) AS UNSIGNED) ASC 
                    LIMIT 1
                ) as col3,
                (
                    SELECT f2.no_fact 
                    FROM tbl_fact f2 
                    WHERE YEAR(f2.fecha_emision) = YEAR(f1.fecha_emision) 
                      AND MONTH(f2.fecha_emision) = MONTH(f1.fecha_emision)
                      AND f2.estado != 'ANULADA'
                    ORDER BY CAST(RIGHT(f2.no_fact, 4) AS UNSIGNED) DESC 
                    LIMIT 1
                ) as col4,
                SUM(f1.total_general) as col5
            FROM tbl_fact f1
            WHERE f1.estado != 'ANULADA'";
    
    $params = [];
    
    // Aplicar filtro de año
    if ($anio_filtro !== 'TODOS') {
        $sql .= " AND YEAR(fecha_emision) = :anio";
        $params[':anio'] = $anio_filtro;
    }
    
    // Aplicar filtro de mes
    if ($mes_filtro !== 'TODOS') {
        $sql .= " AND MONTH(fecha_emision) = :mes";
        $params[':mes'] = $mes_filtro;
    }
    
    $sql .= " GROUP BY YEAR(f1.fecha_emision), MONTH(f1.fecha_emision)
              ORDER BY anio DESC, mes_num ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Guardar en variable global
    $GLOBALS['data_correlativos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_registros = count($GLOBALS['data_correlativos']);
    
    // --- CALCULAR TOTAL DE FACTURAS Y TOTAL DE IMPORTE ---
    $total_facturas_general = 0;
    $total_importe_general = 0; // NUEVA VARIABLE
    foreach ($GLOBALS['data_correlativos'] as $row) {
        $total_facturas_general += intval($row['col2']);
        $total_importe_general += floatval($row['col5']); // SUMAMOS LOS IMPORTES
    }
    $GLOBALS['total_facturas_correlativos'] = $total_facturas_general;
    $GLOBALS['total_importe_correlativos'] = $total_importe_general; // GUARDAMOS EL TOTAL DE IMPORTE
    
    // También asignar a $data por compatibilidad
    $data = $GLOBALS['data_correlativos'];
    
    // Forzar valores de paginación para mostrar todos los registros en una sola página
    $pagina_actual = 1;
    $total_paginas = 1;
    $inicio = 0;
    $registros_por_pagina = 999999;
    
    break;

case 'historico_cierres':
    $headers = ['Tipo', 'Período', 'Fecha Ejecución', 'Usuario', 'Total Fact.', 'Importe Total', 'Pagadas', 'Contabilizadas', 'Observaciones'];
    $titulo_reporte = 'Histórico de Cierres';
    $descripcion_reporte = 'Registro de cierres mensuales y anuales ejecutados en el sistema';
    $mostrar_totales = true;
    $columnas_numericas = [5, 6, 7, 8]; // Total Fact., Importe Total, Pagadas, Contabilizadas
    $columna_dinero = 6; // Importe Total
    
    // Parámetros de filtro específicos
    $filtro_tipo = isset($_GET['filtro_tipo']) ? $_GET['filtro_tipo'] : '';
    $filtro_anio = isset($_GET['filtro_anio']) ? $_GET['filtro_anio'] : '';
    
    // Construir condiciones WHERE
    $where_conditions = [];
    $params = [];
    
    if (!empty($filtro_tipo)) {
        $where_conditions[] = "hc.tipo = ?";
        $params[] = $filtro_tipo;
    }
    
    if (!empty($filtro_anio)) {
        $where_conditions[] = "hc.periodo_anio = ?";
        $params[] = $filtro_anio;
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Total de registros
    $sql_count = "SELECT COUNT(*) as total 
                  FROM historico_cierres hc
                  LEFT JOIN clasif_usuarios u ON hc.usuario_id = u.id
                  $where_sql";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Configurar ordenamiento
    $order_by = 'ORDER BY hc.fecha_ejecucion DESC';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'hc.tipo',
            2 => 'hc.periodo_anio, hc.periodo_mes',
            3 => 'hc.fecha_ejecucion',
            4 => 'u.usuario',
            5 => 'hc.total_facturas',
            6 => 'hc.importe_total',
            7 => 'hc.cant_pagadas',
            8 => 'hc.cant_contabilizadas',
            9 => 'hc.observaciones'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    }
    
    // Consulta principal
    $sql = "SELECT 
                CASE 
                    WHEN hc.tipo = 1 THEN 'CIERRE MENSUAL'
                    WHEN hc.tipo = 2 THEN 'CIERRE ANUAL'
                    ELSE 'OTRO'
                END as col1,
                CASE 
                    WHEN hc.tipo = 1 THEN CONCAT(
                        ELT(hc.periodo_mes, 
                            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ), '/', hc.periodo_anio)
                    WHEN hc.tipo = 2 THEN CONCAT('AÑO ', hc.periodo_anio)
                    ELSE CONCAT('Período ', hc.periodo_anio)
                END as col2,
                DATE_FORMAT(hc.fecha_ejecucion, '%d/%m/%Y %H:%i') as col3,
                COALESCE(u.usuario, 'Sistema') as col4,
                hc.total_facturas as col5,
                hc.importe_total as col6,
                hc.cant_pagadas as col7,
                hc.cant_contabilizadas as col8,
                COALESCE(hc.observaciones, '—') as col9
            FROM historico_cierres hc
            LEFT JOIN clasif_usuarios u ON hc.usuario_id = u.id
            $where_sql
            $order_by
            LIMIT $inicio, $registros_por_pagina";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Totales generales - EXCLUIR CIERRES ANUALES CUANDO filtro_tipo = 0 O NO HAY FILTRO
    $where_totales = $where_conditions;
    
    // Si no hay filtro de tipo (filtro_tipo vacío) o filtro_tipo = 0, excluir cierres anuales
    if (empty($filtro_tipo) || $filtro_tipo == '0') {
        $where_totales[] = "hc.tipo = 1"; // Solo cierres mensuales
    }
    // Si hay filtro_tipo específico (1 o 2), respetar ese filtro
    
    $where_sql_totales = !empty($where_totales) ? "WHERE " . implode(" AND ", $where_totales) : "";
    
    $sql_totales = "SELECT 
                        SUM(hc.total_facturas) as total_facturas,
                        SUM(hc.importe_total) as total_importe,
                        SUM(hc.cant_pagadas) as total_pagadas,
                        SUM(hc.cant_contabilizadas) as total_contabilizadas,
                        COUNT(*) as total_registros
                    FROM historico_cierres hc
                    $where_sql_totales";
    
    // Reutilizar los mismos parámetros, pero asegurarnos de que sean consistentes
    $params_totales = $params;
    
    // Si estamos excluyendo anuales y no había filtro de tipo, NO agregar el parámetro tipo
    // Porque lo estamos manejando en la condición WHERE directa
    if (empty($filtro_tipo) || $filtro_tipo == '0') {
        // No agregar parámetro extra
    } else {
        // Ya tenemos los parámetros de $params
    }
    
    $stmt_totales = $db->prepare($sql_totales);
    $stmt_totales->execute($params_totales);
    $totales_row = $stmt_totales->fetch(PDO::FETCH_ASSOC);
    
    $total_general = $totales_row['total_importe'] ?? 0;
    
    // Almacenar totales para el footer
    $totales_cierres = [
        'total_facturas' => $totales_row['total_facturas'] ?? 0,
        'total_importe' => $totales_row['total_importe'] ?? 0,
        'total_pagadas' => $totales_row['total_pagadas'] ?? 0,
        'total_contabilizadas' => $totales_row['total_contabilizadas'] ?? 0,
        'total_registros' => $totales_row['total_registros'] ?? 0
    ];
    
    break;

case 'hojas_utilizadas':
    // Definición de Headers - ACTUALIZADO
    $headers = ['Descripción del Servicio', 'Precio', 'Servicios', 'Hojas(UNO)', 'PQT(500U)', 'PQT(1000U)', 'Facturado', '% Total Hojas'];
    $titulo_reporte = 'Consumibles - Papel Físico por Servicio';
    $descripcion_reporte = 'Desglose de hojas utilizadas por tipo de servicio';
    
    $columnas_numericas = [2, 3, 4, 5, 6, 7, 8];
    $columna_dinero = 7; 
    $mostrar_totales = true;

    // Obtener filtros de año, mes y categoría
    $anio_hojas = isset($_GET['anio_hojas']) ? $_GET['anio_hojas'] : 'TODOS';
    $mes_hojas = isset($_GET['mes_hojas']) ? $_GET['mes_hojas'] : 'TODOS';
    $categoria_hojas = isset($_GET['categoria_hojas']) ? $_GET['categoria_hojas'] : 'TODAS';
    
    // Construir condiciones para fechas
    $fecha_condiciones = [];
    $params_hojas = [];
    
    if ($anio_hojas !== 'TODOS') {
        $fecha_condiciones[] = "YEAR(f.fecha_emision) = ?";
        $params_hojas[] = $anio_hojas;
    }
    
    if ($mes_hojas !== 'TODOS') {
        $fecha_condiciones[] = "MONTH(f.fecha_emision) = ?";
        $params_hojas[] = $mes_hojas;
    }
    
    $where_fecha = !empty($fecha_condiciones) ? "WHERE " . implode(" AND ", $fecha_condiciones) . " AND f.estado != 'ANULADA'" : "WHERE f.estado != 'ANULADA'";
    
    // Actualizar descripción
    $descripcion_reporte = 'Desglose de hojas utilizadas por tipo de servicio';
    if ($anio_hojas !== 'TODOS' && $mes_hojas !== 'TODOS') {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $descripcion_reporte .= " - " . $meses[$mes_hojas-1] . " " . $anio_hojas;
    } elseif ($anio_hojas !== 'TODOS') {
        $descripcion_reporte .= " - Año " . $anio_hojas;
    } elseif ($mes_hojas !== 'TODOS') {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $descripcion_reporte .= " - Mes de " . $meses[$mes_hojas-1] . " (todos los años)";
    }
    
    // Agregar filtro de categoría a la descripción
    if ($categoria_hojas !== 'TODAS') {
        $nombres_categorias = [
            'BOND' => 'Papel Normal',
            'FOTO' => 'Papel Fotográfico',
            'SUBLIMACION' => 'Papel de Sublimación',
            'CART' => 'Cartulina'
        ];
        $descripcion_reporte .= " - Categoría: " . ($nombres_categorias[$categoria_hojas] ?? $categoria_hojas);
    }
    
    $descripcion_reporte .= '<br>Fotos 1x1″: 48 por hoja | Fotos Pasaporte/Visa: 20 por hoja | Paquetes: Bond 8½x11″ (500U/1000U) | Fotográfico (20U) | Sublimación (10U) | Cartulina (100U)';

    // Función para formatear números sin decimales si es entero
    $fmtNum = function($n) {
        return (floor($n) == $n) ? number_format($n, 0) : number_format($n, 2);
    };

    // 1. OBTENER TODOS LOS SERVICIOS DE LA TABLA clasif_serv
    $sql_servicios = "SELECT id, descripcion, costo, categoria_id FROM clasif_serv ORDER BY descripcion";
    $stmt_servicios = $db->prepare($sql_servicios);
    $stmt_servicios->execute();
    $todos_servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);

    // 2. OBTENER VENTAS CON PRECIO ESTÁNDAR DE clasif_serv (SOLO LOS QUE TIENEN VENTAS)
    $sql = "SELECT 
                s.id, 
                s.descripcion, 
                s.costo as precio_estandar,
                s.categoria_id,
                SUM(fd.cantidad) as unidades, 
                SUM(fd.total_linea) as dinero
            FROM tbl_fact_detalle fd
            JOIN tbl_fact f ON fd.factura_id = f.id
            JOIN clasif_serv s ON fd.servicio_id = s.id
            $where_fecha
            GROUP BY s.id, s.descripcion, s.costo, s.categoria_id
            ORDER BY s.descripcion";

    $stmt_h = $db->prepare($sql);
    $stmt_h->execute($params_hojas);
    $ventas_raw = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

    // Crear un array asociativo de ventas por ID de servicio
    $ventas_por_servicio = [];
    foreach ($ventas_raw as $v) {
        $ventas_por_servicio[$v['id']] = $v;
    }
	
	// Grupos de materiales
	$grupos = [
		'BOND' => ['titulo' => 'Papel Normal (Bond)', 'servicios' => 0, 'hojas' => 0, 'paquetes500' => 0, 'paquetes1000' => 0, 'dinero' => 0, 'items' => []],
		'FOTO' => ['titulo' => 'Papel Fotográfico', 'servicios' => 0, 'hojas' => 0, 'paquetes500' => 0, 'paquetes1000' => 0, 'dinero' => 0, 'items' => []],
		'SUBLIMACION' => ['titulo' => 'Papel de Sublimación', 'servicios' => 0, 'hojas' => 0, 'paquetes500' => 0, 'paquetes1000' => 0, 'dinero' => 0, 'items' => []],
		'CART' => ['titulo' => 'Cartulina / Diplomas', 'servicios' => 0, 'hojas' => 0, 'paquetes500' => 0, 'paquetes1000' => 0, 'dinero' => 0, 'items' => []],
		'CREDENCIALES' => ['titulo' => 'Credenciales / Tarjetas', 'servicios' => 0, 'hojas' => 0, 'paquetes500' => 0, 'paquetes1000' => 0, 'dinero' => 0, 'items' => []]
	];

    // Variables para totales filtrados
    $total_hojas_filtrado = 0;
    $total_paquetes500_filtrado = 0;
    $total_paquetes1000_filtrado = 0;
    $total_dinero_filtrado = 0;
    $total_servicios_filtrado = 0;

    // 3. PROCESAR CADA SERVICIO (TODOS, INCLUYENDO LOS QUE NO TIENEN VENTAS)
    foreach ($todos_servicios as $servicio) {
        $id = $servicio['id'];
        $desc = $servicio['descripcion'];
        $desc_mayus = strtoupper($desc);
        $precio = (float)$servicio['costo'];
        $categoria_id = (int)$servicio['categoria_id'];
        
        // **NUEVA VALIDACIÓN: EXCLUIR CATEGORÍAS 8 A LA 12**
        // Si el servicio pertenece a las categorías 8-12, lo saltamos completamente
        if ($categoria_id >= 8 && $categoria_id <= 12) {
            continue; // No procesar este servicio
        }
        
        // Verificar si hay ventas para este servicio
        $tiene_ventas = isset($ventas_por_servicio[$id]);
        $unidades = $tiene_ventas ? (float)$ventas_por_servicio[$id]['unidades'] : 0;
        $dinero = $tiene_ventas ? (float)$ventas_por_servicio[$id]['dinero'] : 0;
        
        // EXCLUIR SERVICIOS QUE NO SON PAPEL (siempre, aunque tengan o no ventas)
        if (preg_match('/CLIP|PORTA DOCUMENTOS|ÍNDICE|MICA|TINTA|CAFé|LáPI|ARCHIVADOR|ARCHIVADO|CUñO|PIEZAS|REPARAC|ENCUADERNADO|FOLIADOR|PRESILLA|CALCULADORA|MÓVIL|CARGADOR|ALMOHADILLA|ESTUCHE|SACA|CAJA|HOJA DE ÍNDICE|PAQUETE|LAPICERO|FOLIADORA|ARCHIVADOR|TINTA PARA|MICAS|CARNE|CARNé|MICAPARA|CABLE|COVER|MICA PLATICAR|MICA PARA PLATICAR/u', $desc_mayus)) {
            continue;
        }

        // CONVERSIONES A HOJAS (solo si tiene ventas, si no, hojas = 0)
        $hojas = $unidades;
        if ($tiene_ventas) {
            if (strpos($desc_mayus, '1000U') !== false) {
                $hojas = $unidades * 1000;
            } elseif (strpos($desc_mayus, '500U') !== false) { 
                $hojas = $unidades * 500; 
            } elseif (strpos($desc_mayus, '1X1') !== false) { 
                $hojas = $unidades / 48; 
            } elseif (preg_match('/FOTO PASAPORTE|F003|4 FOTOS PASAPORTE|PASAPORTE|VISA/i', $desc_mayus)) {
                // 4 Fotos Pasaporte/Visa:
                // Cada servicio son 4 fotos
                // Cada hoja tiene 20 fotos
                // Por lo tanto: hojas = (unidades * 4) / 20 = unidades / 5
                $hojas = $unidades / 5;  // 40 servicios ÷ 5 = 8 hojas
            } elseif (strpos($desc_mayus, 'FOTO') !== false && strpos($desc_mayus, 'PAQUETE') === false && strpos($desc_mayus, 'FOTOCOPIA') === false) {
                // Fotos individuales: 1 foto = 1 hoja (excepto paquetes y fotocopias)
                $hojas = $unidades;
            }
        }

        // CLASIFICAR POR TIPO DE MATERIAL
        $tipo = 'BOND'; // Por defecto, papel normal
        
        // PAPEL DE SUBLIMACIÓN
        if (strpos($desc_mayus, 'SUBLIMACI') !== false || 
            strpos($desc_mayus, 'ST01') !== false || 
            preg_match('/GRABAR.*TAZ[ÓO]N|GRABAR.*TAZA|GT0[12]/i', $desc_mayus) ||
            strpos($desc_mayus, 'GT02') !== false ||
            strpos($desc_mayus, 'GT01') !== false) {
            $tipo = 'SUBLIMACION';
        }
        // PAPEL FOTOGRÁFICO (excluyendo FOTOCOPIA)
        elseif ((strpos($desc_mayus, 'FOTO') !== false || strpos($desc_mayus, 'FOTOGRAF') !== false || 
                 strpos($desc_mayus, 'GLOSSY') !== false || strpos($desc_mayus, 'IMAGEN') !== false) &&
                 strpos($desc_mayus, 'FOTOCOPIA') === false) {
            $tipo = 'FOTO';
        }
        // CREDENCIALES Y TARJETAS (NO cuentan hojas)
        elseif (preg_match('/CREDENCIAL|TARJETA|ESTIBA|FIRMA|CR0[1-2]|TCE[1-2]/i', $desc_mayus)) {
            $tipo = 'CREDENCIALES';
            // IMPORTANTE: Estas NO consumen hojas, así que forzamos hojas = 0
            $hojas = 0;
            $paquetes500 = 0;
            $paquetes1000 = 0;
        }
        // CARTULINA / DIPLOMAS / CREDENCIALES
        elseif (preg_match('/CARTU|DIPLOMA|CREDENCIAL|TARJETA|FORRADO|VINILO/u', $desc_mayus)) {
            $tipo = 'CART';
        }
        // TODO LO DEMÁS (incluyendo FOTOCOPIAS, SCANNER, impresiones) va a BOND

        // Calcular paquetes según el tipo de papel - AHORA CON DOS FORMATOS (500U y 1000U)
        $paquetes500 = 0;
        $paquetes1000 = 0;
        
        if ($tiene_ventas && $hojas > 0) {
            if ($tipo == 'BOND') {
                $paquetes500 = $hojas / 500;      // Bond: 500 hojas por paquete
                $paquetes1000 = $hojas / 1000;     // Bond: 1000 hojas por paquete
            } elseif ($tipo == 'FOTO') {
                $paquetes500 = $hojas / 20;        // Papel fotográfico: 20 hojas por paquete
                $paquetes1000 = $hojas / 20;       // Para consistencia, mismo valor en ambas columnas
            } elseif ($tipo == 'SUBLIMACION') {
                $paquetes500 = $hojas / 10;        // Sublimación: 10 hojas por paquete
                $paquetes1000 = $hojas / 10;       // Para consistencia, mismo valor en ambas columnas
            } elseif ($tipo == 'CART') {
                $paquetes500 = $hojas / 100;       // Cartulina: 100 hojas por paquete
                $paquetes1000 = $hojas / 100;      // Para consistencia, mismo valor en ambas columnas
            }
        }

        // Agregar a grupos (siempre, aunque tenga 0 ventas)
        $grupos[$tipo]['servicios'] += $unidades;
        $grupos[$tipo]['hojas'] += $hojas;
        $grupos[$tipo]['paquetes500'] += $paquetes500;
        $grupos[$tipo]['paquetes1000'] += $paquetes1000;
        $grupos[$tipo]['dinero'] += $dinero;
        $grupos[$tipo]['items'][] = [
            'desc' => $desc,
            'precio' => $precio,
            'servs' => $unidades,
            'hojas' => $hojas,
            'paquetes500' => $paquetes500,
            'paquetes1000' => $paquetes1000,
            'dinero' => $dinero
        ];
        
        // ACUMULAR TOTALES FILTRADOS (si la categoría coincide o es TODAS)
        if ($categoria_hojas === 'TODAS' || $categoria_hojas === $tipo) {
            $total_hojas_filtrado += $hojas;
            $total_paquetes500_filtrado += $paquetes500;
            $total_paquetes1000_filtrado += $paquetes1000;
            $total_dinero_filtrado += $dinero;
            $total_servicios_filtrado += $unidades;
        }
    }

    // 4. APLICAR FILTRO DE CATEGORÍA (si está seleccionada)
    $grupos_filtrados = [];
    if ($categoria_hojas !== 'TODAS' && isset($grupos[$categoria_hojas])) {
        // Solo mostrar la categoría seleccionada
        $grupos_filtrados[$categoria_hojas] = $grupos[$categoria_hojas];
    } else {
        // Mostrar todas las categorías
        $grupos_filtrados = $grupos;
    }

    // 5. CONSTRUIR TABLA CON LOS GRUPOS FILTRADOS
    $data = [];
    foreach ($grupos_filtrados as $g) {
        if (empty($g['items'])) continue;

        // Encabezado de grupo
        $data[] = [
            'col1' => '<strong>' . $g['titulo'] . '</strong>',
            'col2' => '', 'col3' => '', 'col4' => '', 'col5' => '', 'col6' => '', 'col7' => '', 'col8' => ''
        ];

        // Items del grupo
        foreach ($g['items'] as $item) {
            // Calcular porcentaje basado en los totales FILTRADOS
            $porcentaje = ($total_hojas_filtrado > 0) ? round(($item['hojas'] / $total_hojas_filtrado) * 100, 2) : 0;
			
			// Para PQT(500U) y PQT(1000U), mostrar valor solo para tipos que usan papel
			$muestra_paquetes = ($g['titulo'] == 'Papel Normal (Bond)' || $g['titulo'] == 'Papel Fotográfico' || $g['titulo'] == 'Papel de Sublimación' || $g['titulo'] == 'Cartulina / Diplomas');
			
			$pqt500 = $muestra_paquetes ? $fmtNum($item['paquetes500']) : '—';
			$pqt1000 = $muestra_paquetes ? $fmtNum($item['paquetes1000']) : '—';
            
            $data[] = [
                'col1' => $item['desc'],
                'col2' => '$' . number_format($item['precio'], 2),
                'col3' => $fmtNum($item['servs']),
                'col4' => $fmtNum($item['hojas']),
                'col5' => $pqt500,
                'col6' => $pqt1000,
                'col7' => '$' . number_format($item['dinero'], 2),
                'col8' => $porcentaje . '%'
            ];
        }

        // Subtotal del grupo
        $porcentaje_sub = ($total_hojas_filtrado > 0) ? round(($g['hojas'] / $total_hojas_filtrado) * 100, 1) : 0;
        
        // Para PQT(500U) y PQT(1000U) del subtotal, mostrar solo para tipos que usan papel
        $muestra_paquetes_sub = ($g['titulo'] == 'Papel Normal (Bond)' || $g['titulo'] == 'Papel Fotográfico' || $g['titulo'] == 'Papel de Sublimación' || $g['titulo'] == 'Cartulina / Diplomas');
        
        $pqt500_sub = $muestra_paquetes_sub ? '<strong>' . $fmtNum($g['paquetes500']) . '</strong>' : '—';
        $pqt1000_sub = $muestra_paquetes_sub ? '<strong>' . $fmtNum($g['paquetes1000']) . '</strong>' : '—';
        
        $data[] = [
            'col1' => '<strong>Sub Total ' . $g['titulo'] . ':</strong>',
            'col2' => '—',
            'col3' => '<strong>' . $fmtNum($g['servicios']) . '</strong>',
            'col4' => '<strong>' . $fmtNum($g['hojas']) . '</strong>',
            'col5' => $pqt500_sub,
            'col6' => $pqt1000_sub,
            'col7' => '<strong>$' . number_format($g['dinero'], 2) . '</strong>',
            'col8' => '<strong>' . $porcentaje_sub . '%</strong>'
        ];
        $data[] = ['col1' => '', 'col2' => '', 'col3' => '', 'col4' => '', 'col5' => '', 'col6' => '', 'col7' => '', 'col8' => ''];
    }

    // 6. TOTAL GENERAL
    if (!empty($data)) {
        $data[] = [
            'col1' => '<strong>TOTAL GENERAL DEL REPORTE:</strong>',
            'col2' => '—',
            'col3' => '<strong>' . $fmtNum($total_servicios_filtrado) . '</strong>',
            'col4' => '<strong>' . $fmtNum($total_hojas_filtrado) . '</strong>',
            'col5' => '<strong>' . $fmtNum($total_paquetes500_filtrado) . '</strong>',
            'col6' => '<strong>' . $fmtNum($total_paquetes1000_filtrado) . '</strong>',
            'col7' => '<strong>$' . number_format($total_dinero_filtrado, 2) . '</strong>',
            'col8' => '<strong>100%</strong>'
        ];
    }

    $total_registros = count($data);
    $total_general = $total_dinero_filtrado;
	$total_paginas = 1;
    unset($stmt);
    break;


	case 'ventas_servicios':
            $headers = ['Servicio', 'Categoría', 'Cant. Vendida', 'Ingreso Total'];
            $titulo_reporte = 'Rendimiento por Servicios';
            $descripcion_reporte = 'Análisis de servicios más vendidos';
            $mostrar_totales = true;
            $columnas_numericas = [3, 4];
            $columna_dinero = 4;
            
            // Obtener total
            $sql_count = "SELECT COUNT(DISTINCT s.id) as total
                          FROM tbl_fact_detalle fd
                          JOIN clasif_serv s ON fd.servicio_id = s.id
                          JOIN clasif_cat_de_serv cat ON s.categoria_id = cat.id
                          JOIN tbl_fact f ON fd.factura_id = f.id
                          WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            $stmt_count = $db->prepare($sql_count);
            $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            $order_by = 'ORDER BY SUM(fd.total_linea) DESC';
            if ($orden_columna > 0) {
                $col_mapping = [
                    1 => 's.descripcion',
                    2 => 'cat.descripcion',
                    3 => 'SUM(fd.cantidad)',
                    4 => 'SUM(fd.total_linea)'
                ];
                if (isset($col_mapping[$orden_columna])) {
                    $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
                }
            }
            
            $sql = "SELECT s.descripcion as col1, cat.descripcion as col2, 
                           SUM(fd.cantidad) as col3, SUM(fd.total_linea) as col4 
                    FROM tbl_fact_detalle fd
                    JOIN clasif_serv s ON fd.servicio_id = s.id
                    JOIN clasif_cat_de_serv cat ON s.categoria_id = cat.id
                    JOIN tbl_fact f ON fd.factura_id = f.id
                    WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'
                    GROUP BY s.id, cat.id 
                    $order_by
                    LIMIT $inicio, $registros_por_pagina";
            $stmt = $db->prepare($sql);
            $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            
            // Total general
            $sql_total = "SELECT SUM(fd.total_linea) as total 
                         FROM tbl_fact_detalle fd
                         JOIN tbl_fact f ON fd.factura_id = f.id
                         WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            $stmt_total = $db->prepare($sql_total);
            $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
            $total_general = $total_row['total'] ?? 0;
            break;




case 'diferencia_facturas':
    $headers = ['No. Factura', 'Cliente', 'Total Factura', 'Total Detalles', 'Diferencia', 'Estado'];
    $titulo_reporte = 'Control de Diferencias: Factura vs Detalles';
    $descripcion_reporte = 'Comparación de integridad entre cabecera y detalle de facturación.';
    $mostrar_totales = true;
    $columnas_numericas = [3, 4, 5]; 
    $columna_dinero = [3, 4, 5];

    // 1. Obtener filtro de la URL
    $filtro_dif = $_GET['filtro_diferencia'] ?? 'todas';

    // 2. Ajuste para "Todos los registros"
    if ($registros_por_pagina >= 999999) {
        $inicio = 0;
        $limit_sql = ""; 
    } else {
        $limit_sql = "LIMIT $inicio, $registros_por_pagina";
    }

    // 3. Fórmulas de cálculo
    $exp_detalle = "COALESCE((SELECT SUM(fd.total_linea) FROM tbl_fact_detalle fd WHERE fd.factura_id = f.id), 0)";
    $exp_dif = "(f.total_general - $exp_detalle)";

    // 4. Construcción Lógica de Filtros (Sincronizada)
    $condiciones = "f.fecha_emision BETWEEN ? AND ?";
    
    if ($filtro_dif == 'con_diferencia') {
        $condiciones .= " AND f.estado != 'ANULADA' AND ABS($exp_dif) > 0.01";
    } elseif ($filtro_dif == 'sin_detalles') {
        $condiciones .= " AND f.estado != 'ANULADA' AND NOT EXISTS (SELECT 1 FROM tbl_fact_detalle WHERE factura_id = f.id)";
    } elseif ($filtro_dif == 'anuladas') {
        $condiciones .= " AND f.estado = 'ANULADA'";
    } elseif ($filtro_dif == 'pendientes') {
        $condiciones .= " AND f.estado = 'PENDIENTE'";
    } elseif ($filtro_dif == 'ok') {
        $condiciones .= " AND f.estado != 'ANULADA' AND ABS($exp_dif) <= 0.01 AND EXISTS (SELECT 1 FROM tbl_fact_detalle WHERE factura_id = f.id)";
    }

    // 5. Conteo de registros para paginación
    $sql_count = "SELECT COUNT(*) as total FROM tbl_fact f WHERE $condiciones";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 6. Consulta Principal
    $sql = "SELECT 
                f.no_fact as col1,
                c.nombre as col2,
                f.total_general as col3,
                $exp_detalle as col4,
                $exp_dif as col5,
                CASE 
                    WHEN f.estado = 'ANULADA' THEN 'ANULADA'
                    WHEN f.estado = 'PENDIENTE' THEN 'PENDIENTE'
                    WHEN NOT EXISTS (SELECT 1 FROM tbl_fact_detalle WHERE factura_id = f.id) THEN 'SIN DETALLES'
                    WHEN ABS($exp_dif) > 0.01 THEN 'DIFERENCIA'
                    ELSE 'OK'
                END as col6
            FROM tbl_fact f
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            WHERE $condiciones
            ORDER BY ABS($exp_dif) DESC, f.no_fact DESC
            $limit_sql";

    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    
    // 7. Totales para el footer (Excluyendo anuladas de la suma)
    $sql_total = "SELECT SUM($exp_dif) as total_dif FROM tbl_fact f WHERE $condiciones AND f.estado != 'ANULADA'";
    $stmt_total = $db->prepare($sql_total);
    $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $total_general = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_dif'] ?? 0;

    // Alerta de éxito si no hay errores al buscar específicamente errores
    if ($filtro_dif == 'con_diferencia' && $total_registros == 0) {
        $mostrar_alerta_sin_diferencias = true;
    }

    // 8. Botones de Filtro (En una sola línea para evitar errores de JS)
    $descripcion_reporte .= " <small class='no-print' style='margin-left:10px;'>Filtrar: <a href='".getUrlWithParams(['filtro_diferencia'=>'con_diferencia', 'pagina'=>1])."' class='badge bg-danger text-white text-decoration-none'>Errores</a> | <a href='".getUrlWithParams(['filtro_diferencia'=>'pendientes', 'pagina'=>1])."' class='badge bg-warning text-dark text-decoration-none'>Pendientes</a> | <a href='".getUrlWithParams(['filtro_diferencia'=>'anuladas', 'pagina'=>1])."' class='badge bg-secondary text-white text-decoration-none'>Anuladas</a> | <a href='".getUrlWithParams(['filtro_diferencia'=>'todas', 'pagina'=>1])."' class='badge bg-primary text-white text-decoration-none'>Todas</a></small>";
    break;
	
case 'ventas_por_categoria':
            $headers = ['Categoría de Servicio', 'Cant. Facturas', 'Cant. Items', 'Subtotal', 'Monto Total'];
            $titulo_reporte = 'Resumen de Ventas por Categoría';
            $descripcion_reporte = 'Análisis de ingresos agrupados por tipo de servicio';
            $mostrar_totales = true;
            $columnas_numericas = [2, 3, 4, 5];
            $columna_dinero = [4,5];
            
            // 1. Contar filas para paginación (Agrupando por categoría, incluyendo nulos)
            $sql_count = "SELECT COUNT(DISTINCT IFNULL(s.categoria_id, 0)) as total
                          FROM tbl_fact_detalle fd
                          JOIN tbl_fact f ON fd.factura_id = f.id
                          LEFT JOIN clasif_serv s ON fd.servicio_id = s.id
                          WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            
            $stmt_count = $db->prepare($sql_count);
            $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 2. Ordenamiento
            $order_by = 'ORDER BY SUM(fd.total_linea) DESC';
            if ($orden_columna > 0) {
                $col_mapping = [
                    1 => 'cat.descripcion',
                    2 => 'COUNT(DISTINCT f.id)',
                    3 => 'SUM(fd.cantidad)',
                    4 => 'SUM(fd.cantidad * fd.precio_unitario)',
                    5 => 'SUM(fd.total_linea)'
                ];
                if (isset($col_mapping[$orden_columna])) {
                    $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
                }
            }
            
            // 3. Consulta Principal (CORREGIDA: Parte de las ventas hacia las categorías)
            // Usamos LEFT JOIN para que NO desaparezcan ventas si la categoría fue borrada o es nula
            $sql = "SELECT COALESCE(cat.descripcion, 'SIN CATEGORÍA / OTROS') as col1, 
                           COUNT(DISTINCT f.id) as col2,
                           SUM(fd.cantidad) as col3,
                           SUM(fd.cantidad * fd.precio_unitario) as col4, 
                           SUM(fd.total_linea) as col5
                    FROM tbl_fact_detalle fd
                    JOIN tbl_fact f ON fd.factura_id = f.id
                    LEFT JOIN clasif_serv s ON fd.servicio_id = s.id
                    LEFT JOIN clasif_cat_de_serv cat ON s.categoria_id = cat.id
                    WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'
                    GROUP BY s.categoria_id, cat.descripcion
                    $order_by
                    LIMIT $inicio, $registros_por_pagina";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            
            // 4. Total General (CORREGIDO: Suma directa de la tabla de detalles)
            // Esto garantiza que el total sea la suma real de todo lo facturado en el periodo
            $sql_total = "SELECT 
                       COUNT(DISTINCT f.id) as total_facturas,
                       SUM(fd.cantidad) as total_items,
                       SUM(fd.cantidad * fd.precio_unitario) as total_subtotal,
                       SUM(fd.total_linea) as total_monto
                    FROM tbl_fact_detalle fd
                    JOIN tbl_fact f ON fd.factura_id = f.id
                    WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
    
            $stmt_totales = $db->prepare($sql_total);
            $stmt_totales->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $totales_row = $stmt_totales->fetch(PDO::FETCH_ASSOC);
            
            // Asignar totales a variables
            $total_facturas = $totales_row['total_facturas'] ?? 0;
            $total_items = $totales_row['total_items'] ?? 0;
            $total_subtotal = $totales_row['total_subtotal'] ?? 0;
            $total_general = $totales_row['total_monto'] ?? 0; 
            
            break;

case 'ranking_clientes':
    $headers = ['Cliente', 'Responsable', 'Contrato', 'Cant. Facturas', 'Facturación Total'];
    $titulo_reporte = 'Ranking de Clientes';
    $descripcion_reporte = 'Clientes con facturación en el período (ordenados por facturación)';
    $mostrar_totales = true;
    $columnas_numericas = [4,5]; // Cant. Facturas y Facturación Total
    $columna_dinero = 5; // Columna de facturación total
    
    // Preparar fechas para consultas
    $fecha_inicio_completa = $fecha_inicio . ' 00:00:00';
    $fecha_fin_completa = $fecha_fin . ' 23:59:59';
    
    // 1. Contar SOLO clientes con facturas en el período
    $sql_count = "
        SELECT COUNT(DISTINCT c.id) as total 
        FROM clasif_clientes c
        INNER JOIN tbl_fact f ON c.id = f.cliente_id 
            AND f.estado != 'ANULADA'
            AND f.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin
        WHERE f.total_general > 0  -- Solo clientes con importe de facturación
    ";
    
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->bindValue(':fecha_inicio', $fecha_inicio_completa, PDO::PARAM_STR);
    $stmt_count->bindValue(':fecha_fin', $fecha_fin_completa, PDO::PARAM_STR);
    $stmt_count->execute();
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 2. Configurar ordenamiento
    $col_mapping = [
        1 => 'c.nombre',
        2 => 'c.ResponsableEntidad',
        3 => 'c.ContratoNo',
        4 => 'COUNT(f.id)',  // Cantidad de facturas
        5 => 'SUM(f.total_general)'  // Facturación total
    ];
    
    // Ordenamiento por defecto: mayor facturación primero
    $order_by = 'ORDER BY SUM(f.total_general) DESC';
    
    if ($orden_columna > 0 && isset($col_mapping[$orden_columna])) {
        $columna = $col_mapping[$orden_columna];
        $direccion = ($orden_direccion == 'asc') ? 'ASC' : 'DESC';
        $order_by = "ORDER BY $columna $direccion";
    }
    
    // 3. Consulta principal - SOLO clientes con facturación > 0
    $sql = "SELECT c.nombre as col1, 
                   COALESCE(c.ResponsableEntidad, 'No especificado') as col2, 
                   COALESCE(c.ContratoNo, 'Sin contrato') as col3, 
                   COUNT(f.id) as col4, 
                   SUM(f.total_general) as col5 
            FROM clasif_clientes c
            INNER JOIN tbl_fact f ON c.id = f.cliente_id 
                AND f.estado != 'ANULADA' 
                AND f.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin
            WHERE f.total_general > 0  -- Solo facturas con importe
            GROUP BY c.id, c.nombre, c.ResponsableEntidad, c.ContratoNo
            HAVING SUM(f.total_general) > 0  -- Asegurar que tenga facturación
            $order_by
            LIMIT :inicio, :registros_por_pagina";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':fecha_inicio', $fecha_inicio_completa, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_fin', $fecha_fin_completa, PDO::PARAM_STR);
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindValue(':registros_por_pagina', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    
    // 4. Total general de facturación en el período
    $sql_total = "SELECT COALESCE(SUM(f.total_general), 0) as total 
                 FROM tbl_fact f 
                 WHERE f.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin 
                 AND f.estado != 'ANULADA'
                 AND f.total_general > 0";  // Solo facturas con importe
    
    $stmt_total = $db->prepare($sql_total);
    $stmt_total->bindValue(':fecha_inicio', $fecha_inicio_completa, PDO::PARAM_STR);
    $stmt_total->bindValue(':fecha_fin', $fecha_fin_completa, PDO::PARAM_STR);
    $stmt_total->execute();
    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_general = (float) ($total_row['total'] ?? 0);
    
    break;
	case 'metodos_pago':
            $headers = ['Método de Pago', 'Cant. Operaciones', 'Monto Total', 'Participación %'];
            $titulo_reporte = 'Análisis de Métodos de Pago';
            $descripcion_reporte = 'Distribución de ingresos según la vía de pago utilizada.';
            $mostrar_totales = true;
            $columnas_numericas = [2, 3, 4];
            $columna_dinero = 3;
            
            // Obtener total global del período
            $sql_total_ingreso = "SELECT SUM(total_general) FROM tbl_fact f WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            $stmt_total_val = $db->prepare($sql_total_ingreso); 
            $stmt_total_val->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_global_periodo = $stmt_total_val->fetchColumn() ?: 1;
            
            // Obtener total de registros
            $sql_count = "SELECT COUNT(DISTINCT tp.id) as total
                          FROM tipos_pago tp
                          INNER JOIN tbl_fact f ON f.tipo_pago_id = tp.id
                          WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            $stmt_count = $db->prepare($sql_count);
            $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Configurar ordenamiento
            $order_by = 'ORDER BY SUM(f.total_general) DESC';
            if ($orden_columna > 0) {
                $col_mapping = [
                    1 => 'tp.descripcion',
                    2 => 'COUNT(f.id)',
                    3 => 'SUM(f.total_general)',
                    4 => 'ROUND((SUM(f.total_general) / ' . $total_global_periodo . ') * 100, 2)'
                ];
                if (isset($col_mapping[$orden_columna])) {
                    $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
                }
            }
            
            $sql = "SELECT tp.descripcion as col1, 
                           COUNT(f.id) as col2, 
                           SUM(f.total_general) as col3,
                           ROUND((SUM(f.total_general) / $total_global_periodo) * 100, 2) as col4
                    FROM tipos_pago tp
                    INNER JOIN tbl_fact f ON f.tipo_pago_id = tp.id
                    WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'
                    GROUP BY tp.id 
                    $order_by
                    LIMIT $inicio, $registros_por_pagina";
    
            $stmt = $db->prepare($sql);
            $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            
            // Total general
            $sql_total = "SELECT SUM(f.total_general) as total 
                         FROM tbl_fact f 
                         WHERE f.fecha_emision BETWEEN ? AND ? AND f.estado != 'ANULADA'";
            $stmt_total = $db->prepare($sql_total);
            $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
            $total_general = $total_row['total'] ?? 0;
            break;

case 'auditoria':
    $headers = ['Fecha', 'Hora', 'Operación', 'Usuario', 'Descripción', 'IP'];
    $titulo_reporte = 'Registro de Auditoría';
    $descripcion_reporte = 'Historial completo de acciones realizadas en el sistema.';
    $mostrar_totales = false;
    $columnas_numericas = [];
    $columna_dinero = null;
    
    // Obtener filtros
    $fecha_inicio_aud = isset($_GET['fecha_inicio_aud']) ? $_GET['fecha_inicio_aud'] : date('Y-m-d', strtotime('-30 days'));
    $fecha_fin_aud = isset($_GET['fecha_fin_aud']) ? $_GET['fecha_fin_aud'] : date('Y-m-d');
    $operacion_aud = isset($_GET['operacion_aud']) ? $_GET['operacion_aud'] : 'TODAS';
    $usuario_aud = isset($_GET['usuario_aud']) ? $_GET['usuario_aud'] : 'TODOS';
    $ip_aud = isset($_GET['ip_aud']) ? trim($_GET['ip_aud']) : '';
    
    // Construir condiciones WHERE
    $where_conditions = [];
    $params = [];
    
    // Filtro por rango de fechas
    if (!empty($fecha_inicio_aud) && !empty($fecha_fin_aud)) {
        $where_conditions[] = "DATE(h.fecha_hora) BETWEEN ? AND ?";
        $params[] = $fecha_inicio_aud;
        $params[] = $fecha_fin_aud;
    }
    
    // Filtro por tipo de operación
    if ($operacion_aud != 'TODAS') {
        $where_conditions[] = "h.operacion = ?";
        $params[] = $operacion_aud;
    }
    
    // Filtro por usuario
    if ($usuario_aud != 'TODOS') {
        $where_conditions[] = "h.usuario_nombre = ?";
        $params[] = $usuario_aud;
    }
    
    // Filtro por IP (búsqueda parcial)
    if (!empty($ip_aud)) {
        $where_conditions[] = "h.ip_address LIKE ?";
        $params[] = '%' . $ip_aud . '%';
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Actualizar descripción con los filtros aplicados
    $descripcion_reporte = "Historial de acciones";
    if (!empty($fecha_inicio_aud) && !empty($fecha_fin_aud)) {
        $descripcion_reporte .= " del " . date('d/m/Y', strtotime($fecha_inicio_aud)) . " al " . date('d/m/Y', strtotime($fecha_fin_aud));
    }
    if ($operacion_aud != 'TODAS') {
        $descripcion_reporte .= " | Operación: " . $operacion_aud;
    }
    if ($usuario_aud != 'TODOS') {
        $descripcion_reporte .= " | Usuario: " . $usuario_aud;
    }
    if (!empty($ip_aud)) {
        $descripcion_reporte .= " | IP: " . $ip_aud;
    }
    
    // Obtener total de registros con filtros
    $sql_count = "SELECT COUNT(*) as total FROM historico_operaciones h $where_sql";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Configurar ordenamiento
    $order_by = 'ORDER BY h.fecha_hora DESC';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'h.fecha_hora',
            2 => 'h.fecha_hora',
            3 => 'h.operacion',
            4 => 'COALESCE(h.usuario_nombre, "Sistema")',
            5 => 'h.descripcion',
            6 => 'h.ip_address'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    }

    $sql = "SELECT DATE_FORMAT(h.fecha_hora, '%d/%m/%Y') as col1, 
                   DATE_FORMAT(h.fecha_hora, '%h:%i:%s %p') as col2,
                   h.operacion as col3, 
                   COALESCE(h.usuario_nombre, 'Sistema') as col4,
                   h.descripcion as col5, 
                   h.ip_address as col6
            FROM historico_operaciones h
            $where_sql
            $order_by
            LIMIT $inicio, $registros_por_pagina";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    break;

        case 'deudores':
            $headers = ['Cliente',  'Facturas Pendientes', 'Monto Adeudado'];
            $titulo_reporte = 'Reporte de Cuentas por Cobrar';
            $descripcion_reporte = 'Clientes con facturas en estado Pendiente o Contabilizada sin pagar.';
            $mostrar_totales = true;
            $columnas_numericas = [2, 3];
            $columna_dinero = 3;
            
            // Obtener total de registros
                $sql_count = "SELECT COUNT(DISTINCT c.id) as total
					FROM clasif_clientes c
					JOIN tbl_fact f ON c.id = f.cliente_id
					WHERE f.fecha_emision BETWEEN ? AND ?
					AND (
						(f.estado IN ('PENDIENTE', 'CONTABILIZADA') AND f.fecha_pago IS NULL)
						OR
						(f.estado = 'CERRADA' AND f.fecha_pago IS NULL)
					)";
			  
            $stmt_count = $db->prepare($sql_count);
            $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Configurar ordenamiento
            $order_by = 'ORDER BY SUM(f.total_general) DESC';
            if ($orden_columna > 0) {
                $col_mapping = [
                    1 => 'c.nombre',
                    2 => 'COUNT(f.id)',
                    3 => 'SUM(f.total_general)'
                ];
                if (isset($col_mapping[$orden_columna])) {
                    $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
                }
            }
    
            $sql = "SELECT c.nombre as col1, 
               COUNT(f.id) as col2, 
               SUM(f.total_general) as col3
        FROM clasif_clientes c
        JOIN tbl_fact f ON c.id = f.cliente_id
        WHERE f.fecha_emision BETWEEN ? AND ?
        AND (
            (f.estado IN ('PENDIENTE', 'CONTABILIZADA') AND f.fecha_pago IS NULL)
            OR
            (f.estado = 'CERRADA' AND f.fecha_pago IS NULL)
        )
        GROUP BY c.id 
        $order_by
        LIMIT $inicio, $registros_por_pagina";
    
            $stmt = $db->prepare($sql);
            $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            
            // Total general
             $sql_total = "SELECT SUM(f.total_general) as total 
                 FROM tbl_fact f 
                 WHERE f.fecha_emision BETWEEN ? AND ?
                 AND (
                    (f.estado IN ('PENDIENTE', 'CONTABILIZADA') AND f.fecha_pago IS NULL)
                    OR
                    (f.estado = 'CERRADA' AND f.fecha_pago IS NULL)
                 )";
            $stmt_total = $db->prepare($sql_total);
            $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
            $total_general = $total_row['total'] ?? 0;
            break;
        
case 'plan_vs_real':
    $headers = ['Mes/Año', 'Meta Plan', 'Venta Real', 'Cumplimiento %', 'Observaciones'];
    $titulo_reporte = 'Cumplimiento de Metas';
    $mostrar_totales = true;
    $columnas_numericas = [2, 3, 4];
    $columna_dinero = 2;
    
    // Obtener filtros de año y mes
    $anio_plan = isset($_GET['anio_plan']) ? $_GET['anio_plan'] : 'TODOS';
    $mes_plan = isset($_GET['mes_plan']) ? $_GET['mes_plan'] : 'TODOS';
    
    // Preparar condiciones SQL según la selección
    $where_conditions = [];
    $params = [];
    
    if ($anio_plan != 'TODOS') {
        $where_conditions[] = "p.anio = ?";
        $params[] = $anio_plan;
    }
    
    if ($mes_plan != 'TODOS') {
        $where_conditions[] = "p.mes_plan = ?";
        $params[] = $mes_plan;
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE 1=1";
    
    // Personalizar descripción según filtros
    if ($anio_plan == 'TODOS' && $mes_plan == 'TODOS') {
        $descripcion_reporte = "Comparativo Plan vs Real - Todos los años y meses";
    } elseif ($anio_plan != 'TODOS' && $mes_plan == 'TODOS') {
        $descripcion_reporte = "Comparativo Plan vs Real - Año " . $anio_plan . " (Todos los meses)";
    } elseif ($anio_plan == 'TODOS' && $mes_plan != 'TODOS') {
        $mes_nombre = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $descripcion_reporte = "Comparativo Plan vs Real - Mes de " . ($mes_nombre[$mes_plan] ?? $mes_plan) . " (Todos los años)";
    } else {
        $mes_nombre = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $descripcion_reporte = "Comparativo Plan vs Real - " . ($mes_nombre[$mes_plan] ?? $mes_plan) . " " . $anio_plan;
    }
    
    // Contar registros
    $sql_count = "SELECT COUNT(*) as total FROM tbl_planes p $where_sql";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    $order_by = 'ORDER BY p.anio DESC, p.mes_plan ASC';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'CONCAT(ELT(p.mes_plan, "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"), "/", p.anio)',
            2 => 'p.importe',
            3 => 'COALESCE(SUM(f.total_general),0)',
            4 => 'CASE WHEN p.importe > 0 THEN ROUND((COALESCE(SUM(f.total_general),0) / p.importe) * 100, 2) ELSE 0 END',
            5 => 'p.observaciones'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    }
    
    $sql = "SELECT CONCAT(ELT(p.mes_plan, 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'), '/', p.anio) as col1, 
                   p.importe as col2, 
                   COALESCE(SUM(f.total_general),0) as col3, 
                   CASE 
                       WHEN p.importe > 0 THEN ROUND((COALESCE(SUM(f.total_general),0) / p.importe) * 100, 2)
                       ELSE 0 
                   END as col4,
                   p.observaciones as col5
            FROM tbl_planes p
            LEFT JOIN tbl_fact f ON p.anio = YEAR(f.fecha_emision) 
                AND p.mes_plan = MONTH(f.fecha_emision) 
                AND f.estado != 'ANULADA'
            $where_sql
            GROUP BY p.anio, p.mes_plan, p.importe, p.observaciones 
            $order_by
            LIMIT $inicio, $registros_por_pagina";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Total general según los filtros aplicados
    if ($anio_plan == 'TODOS' && $mes_plan == 'TODOS') {
        $sql_total = "SELECT COALESCE(SUM(f.total_general),0) as total 
                     FROM tbl_fact f 
                     WHERE f.estado != 'ANULADA'";
        $stmt_total = $db->prepare($sql_total);
        $stmt_total->execute();
    } elseif ($anio_plan != 'TODOS' && $mes_plan == 'TODOS') {
        $sql_total = "SELECT COALESCE(SUM(f.total_general),0) as total 
                     FROM tbl_fact f 
                     WHERE YEAR(f.fecha_emision) = ? AND f.estado != 'ANULADA'";
        $stmt_total = $db->prepare($sql_total);
        $stmt_total->execute([$anio_plan]);
    } elseif ($anio_plan == 'TODOS' && $mes_plan != 'TODOS') {
        $sql_total = "SELECT COALESCE(SUM(f.total_general),0) as total 
                     FROM tbl_fact f 
                     WHERE MONTH(f.fecha_emision) = ? AND f.estado != 'ANULADA'";
        $stmt_total = $db->prepare($sql_total);
        $stmt_total->execute([$mes_plan]);
    } else {
        $sql_total = "SELECT COALESCE(SUM(f.total_general),0) as total 
                     FROM tbl_fact f 
                     WHERE YEAR(f.fecha_emision) = ? AND MONTH(f.fecha_emision) = ? AND f.estado != 'ANULADA'";
        $stmt_total = $db->prepare($sql_total);
        $stmt_total->execute([$anio_plan, $mes_plan]);
    }
    
    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_general = $total_row['total'] ?? 0;
    break;
	
	
case 'vencimiento_contratos':
    $headers = ['Cliente', 'Contrato No', 'Responsable', 'Fecha Registro', 'Vigencia (años)', 'Fecha Vence', 'Renov.', 'Fecha Final Contrato', 'Estado', 'Días Restantes'];
    $titulo_reporte = 'Control de Contratos';
    
    // Obtener el filtro de estado si existe
    $filtro_estado = isset($_GET['filtro_estado']) ? $_GET['filtro_estado'] : '';
    
    // Personalizar título y descripción según el filtro
    switch($filtro_estado) {
        case 'vencidos':
            $descripcion_reporte = 'Contratos vencidos (días restantes negativos)';
            break;
        case 'por_vencer':
            $descripcion_reporte = 'Contratos por vencer (0-30 días restantes)';
            break;
        case 'vigentes':
            $descripcion_reporte = 'Contratos vigentes (más de 30 días restantes)';
            break;
        case 'indefinidos':
            $descripcion_reporte = 'Contratos con vigencia indefinida';
            break;
        case '':
        default:
            $descripcion_reporte = 'Vencimiento de contratos de clientes - Todos los estados';
            break;
    }
    
    $mostrar_totales = false;
    $columnas_numericas = [5, 10];
    $columna_dinero = null;
    
    // Construir condición WHERE según el filtro
    $filtro_where = '';
    switch($filtro_estado) {
        case 'vencidos':
            $filtro_where = "AND DATEDIFF(fechaVence, CURDATE()) < 0 AND vigenciapor != 0";
            $titulo_reporte = 'Contratos Vencidos';
            break;
        case 'por_vencer':
            $filtro_where = "AND DATEDIFF(fechaVence, CURDATE()) BETWEEN 0 AND 30 AND vigenciapor != 0";
            $titulo_reporte = 'Contratos por Vencer';
            break;
        case 'vigentes':
            $filtro_where = "AND DATEDIFF(fechaVence, CURDATE()) > 30 AND vigenciapor != 0";
            $titulo_reporte = 'Contratos Vigentes';
            break;
        case 'indefinidos':
            $filtro_where = "AND vigenciapor = 0";
            $titulo_reporte = 'Contratos Indefinidos';
            break;
        case '':
        default:
            $filtro_where = "";
            $titulo_reporte = 'Control de Contratos';
            break;
    }
    
    $sql_count = "SELECT COUNT(*) as total FROM clasif_clientes WHERE fechaVence IS NOT NULL $filtro_where";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute();
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    $order_by = 'ORDER BY fechaVence ASC';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'nombre',
            2 => 'ContratoNo',
            3 => 'COALESCE(ResponsableEntidad, "")',
            4 => 'fechaRegistro',
            5 => 'vigenciapor',
            6 => 'fechaVence',
            7 => 'renovac',
            8 => 'fechafinalcontrato',
            9 => 'activo',
            10 => 'DATEDIFF(fechaVence, CURDATE())'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    }
    
    $sql = "SELECT nombre as col1, 
                   COALESCE(ContratoNo, '') as col2,
                   COALESCE(ResponsableEntidad, '') as col3, 
                   DATE_FORMAT(fechaRegistro, '%d/%m/%Y') as col4,
                   COALESCE(vigenciapor, 0) as col5,
                   DATE_FORMAT(fechaVence, '%d/%m/%Y') as col6,
                   CASE 
                       WHEN renovac = 1 THEN CONCAT('Sí (+', si_renova_cant, ' años)')
                       ELSE 'No'
                   END as col7,
                   DATE_FORMAT(fechafinalcontrato, '%d/%m/%Y') as col8,
                   CASE 
                       WHEN activo = 1 THEN 'Activo'
                       ELSE 'Inactivo'
                   END as col9,
                   CASE 
                       WHEN DATEDIFF(fechaVence, CURDATE()) < 0 
                            THEN CONCAT('Vencido hace ', ABS(DATEDIFF(fechaVence, CURDATE())), ' días')
                       WHEN DATEDIFF(fechaVence, CURDATE()) BETWEEN 0 AND 30 
                            THEN CONCAT('Por vencer (', DATEDIFF(fechaVence, CURDATE()), ' días)')
                       ELSE CONCAT('Vigente (', DATEDIFF(fechaVence, CURDATE()), ' días)')
                   END as col10
            FROM clasif_clientes 
            WHERE fechaVence IS NOT NULL 
            $filtro_where
            $order_by
            LIMIT $inicio, $registros_por_pagina";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    break;


case 'estado_facturas':
    $headers = ['Estado', 'Cant. Facturas', 'Monto Total', 'Porcentaje %'];
    $titulo_reporte = 'Estado de las Facturas';
    $descripcion_reporte = 'Distribución por estados.';
    $mostrar_totales = true;
    $columnas_numericas = [2, 3, 4];
    $columna_dinero = 3;
    
    // NUEVA LÓGICA: Clasificación por estado y condición de pago
    $sql_estado_calc = "CASE 
                            WHEN estado = 'PAGADA' THEN 'PAGADA'
                            WHEN estado = 'CERRADA' AND fecha_pago IS NOT NULL AND fecha_pago != '' THEN 'CERRADA/PAGADA'
                            WHEN estado = 'CERRADA' AND (fecha_pago IS NULL OR fecha_pago = '') THEN 'CERRADA'
                            ELSE estado 
                        END";

    // 1. Contar estados únicos basados en la nueva lógica
    $sql_count = "SELECT COUNT(DISTINCT $sql_estado_calc) as total 
                  FROM tbl_fact 
                  WHERE fecha_emision BETWEEN ? AND ?";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 2. Configurar ordenamiento
    $order_by = 'ORDER BY SUM(total_general) DESC';
    if ($orden_columna > 0) {
        $col_mapping = [
            1 => 'col1', // Ordenar por el nombre del estado agrupado
            2 => 'COUNT(*)',
            3 => 'SUM(total_general)',
            4 => 'ROUND((COUNT(*) / (SELECT COUNT(*) FROM tbl_fact WHERE fecha_emision BETWEEN ? AND ?)) * 100, 2)'
        ];
        if (isset($col_mapping[$orden_columna])) {
            $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
        }
    }
    
    // 3. Consulta Principal con la nueva lógica
    $sql = "SELECT 
                $sql_estado_calc as col1,
                COUNT(*) as col2,
                SUM(total_general) as col3,
                ROUND((COUNT(*) / (SELECT COUNT(*) FROM tbl_fact WHERE fecha_emision BETWEEN ? AND ?)) * 100, 2) as col4
            FROM tbl_fact 
            WHERE fecha_emision BETWEEN ? AND ?
            GROUP BY col1 
            $order_by
            LIMIT $inicio, $registros_por_pagina";

    $stmt = $db->prepare($sql);
    // Necesitamos pasar los parámetros de fecha 2 veces (para el subquery de porcentaje y el query principal)
    $stmt->execute([
        $fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59', 
        $fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'
    ]);
    
    // 4. Total general (Suma de todo el periodo, sin importar el estado)
    $sql_total = "SELECT SUM(total_general) as total 
                 FROM tbl_fact 
                 WHERE fecha_emision BETWEEN ? AND ?";
    $stmt_total = $db->prepare($sql_total);
    $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_general = $total_row['total'] ?? 0;
    
    break;
			
			
        case 'productividad_usuarios':
            $headers = ['Nombre y Apellidos', 'No. CI', 'Usuario', 'Facturas Creadas', 'Monto Gestionado'];
            $titulo_reporte = 'Productividad de Usuarios';
            $descripcion_reporte = 'Ventas por usuario del sistema';
            $mostrar_totales = true;
            $columnas_numericas = [4, 5];
            $columna_dinero = 5;
            
            $sql_count = "SELECT COUNT(*) as total FROM clasif_usuarios u";
            $stmt_count = $db->prepare($sql_count);
            $stmt_count->execute();
            $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            $order_by = 'ORDER BY COALESCE(SUM(f.total_general),0) DESC';
            if ($orden_columna > 0) {
                $col_mapping = [
                    1 => 'CONCAT(u.nombre, " ", u.apellidos)',
                    2 => 'u.no_ci', 
                    3 => 'u.usuario', 
                    4 => 'COUNT(f.id)',
                    5 => 'COALESCE(SUM(f.total_general),0)'
                ];
                if (isset($col_mapping[$orden_columna])) {
                    $order_by = "ORDER BY " . $col_mapping[$orden_columna] . " " . ($orden_direccion == 'asc' ? 'ASC' : 'DESC');
                }
            }
            
            $sql = "SELECT CONCAT(u.nombre, ' ', u.apellidos) as col1, 
                           CONCAT(CHAR(0), u.no_ci, CHAR(0)) as col2,
                           u.usuario as col3, 
                           COUNT(f.id) as col4, 
                           COALESCE(SUM(f.total_general),0) as col5 
                    FROM clasif_usuarios u
                    LEFT JOIN tbl_fact f ON u.id = f.usuario_id AND f.fecha_emision BETWEEN ? AND ?
                    GROUP BY u.id 
                    $order_by
                    LIMIT $inicio, $registros_por_pagina";
                    
            $stmt = $db->prepare($sql);
            $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            
            // Total general
            $sql_total = "SELECT COALESCE(SUM(f.total_general),0) as total 
                         FROM tbl_fact f 
                         WHERE f.fecha_emision BETWEEN ? AND ?";
            $stmt_total = $db->prepare($sql_total);
            $stmt_total->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
            $total_general = $total_row['total'] ?? 0;
            break;
            



case 'facturas_vencidas':
    // Obtener fecha de cierre para calcular los días vencidos
    $fecha_cierre = $hoy;
    
    $headers = ['No. Factura', 'Cliente', 'Fecha Emisión', 'Días Vencidos', 'Monto', 'Estado'];
    $titulo_reporte = 'Facturas Vencidas por Antigüedad';
    $descripcion_reporte = 'Facturas pendientes de Pago agrupadas por días de retraso hasta: '.date('d/m/Y',$timestamp_combinado);
    $mostrar_totales = true;
    $columnas_numericas = [5]; 
    $columna_dinero = 5;

    // Obtener filtros
    $anio_vencidas = isset($_GET['anio_vencidas']) ? $_GET['anio_vencidas'] : 'TODOS';
    $dias_filtro = $_GET['dias_vencidos'] ?? '';
    
    $sql_dias_vencidos = "DATEDIFF('$fecha_cierre', f.fecha_emision)";
    
    // Construir condiciones WHERE
    $where_conditions = ["f.estado NOT IN ('ANULADA', 'PAGADA')", 
                         "(f.fecha_pago IS NULL OR f.fecha_pago = '')", 
                         "f.fecha_emision < '$fecha_cierre'"];
    
    // Filtro por año
    if ($anio_vencidas !== 'TODOS') {
        $where_conditions[] = "YEAR(f.fecha_emision) = " . intval($anio_vencidas);
    }
    
    // Filtro por días vencidos
    if (!empty($dias_filtro)) {
        switch($dias_filtro) {
            case '30': $where_conditions[] = "$sql_dias_vencidos BETWEEN 1 AND 30"; break;
            case '60': $where_conditions[] = "$sql_dias_vencidos BETWEEN 31 AND 60"; break;
            case '90': $where_conditions[] = "$sql_dias_vencidos BETWEEN 61 AND 90"; break;
            case '120': $where_conditions[] = "$sql_dias_vencidos BETWEEN 91 AND 120"; break;
            case '150': $where_conditions[] = "$sql_dias_vencidos BETWEEN 121 AND 150"; break;
            case '180': $where_conditions[] = "$sql_dias_vencidos BETWEEN 151 AND 180"; break;
            case 'mas180': $where_conditions[] = "$sql_dias_vencidos > 180"; break;
        }
    }
    
    $where_base = "WHERE " . implode(" AND ", $where_conditions);

    // Actualizar descripción con el año seleccionado
    if ($anio_vencidas !== 'TODOS') {
        $descripcion_reporte .= " | Año: " . $anio_vencidas;
    }

    $sql_count = "SELECT COUNT(*) as total 
                  FROM tbl_fact f 
                  JOIN clasif_clientes c ON f.cliente_id = c.id
                  $where_base";
                  
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute();
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    $order_by = "ORDER BY $sql_dias_vencidos DESC";
    
    $sql = "SELECT f.no_fact as col1,
                   c.nombre as col2,
                   DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as col3,
                   $sql_dias_vencidos as col4,
                   f.total_general as col5,
                   f.estado as col6
            FROM tbl_fact f
            JOIN clasif_clientes c ON f.cliente_id = c.id
            $where_base
            $order_by
            LIMIT $inicio, $registros_por_pagina";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    // Total general
    $sql_total = "SELECT SUM(f.total_general) as total 
                 FROM tbl_fact f 
                 $where_base";
                 
    $stmt_total = $db->prepare($sql_total);
    $stmt_total->execute();
    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_general = $total_row['total'] ?? 0;
    
    $descripcion_reporte .= " | Fecha de cierre: " . $ultimodiaMesOperaciones;
    
    break;


case 'aging_por_cliente':
    $headers = ['No. Factura', 'Fecha', 'Cliente', '1-30', '31-60', '61-90', '91-120', '121-150', '151-180', '+180'];
    $titulo_reporte = 'Aging: Antigüedad de Saldos';
    $descripcion_reporte = 'Distribución de deuda por rangos de días desde la fecha de emisión';
    $mostrar_totales = true;
    $columnas_numericas = [4, 5, 6, 7, 8, 9, 10];
    $columna_dinero = 99; // Esto es un placeholder, no se usa para el footer

    // --- NUEVO: Obtener filtros ---
    $rango_aging = isset($_GET['rango_aging']) ? $_GET['rango_aging'] : 'TODOS';
    $orden_aging = isset($_GET['orden_aging']) ? $_GET['orden_aging'] : 'fecha_asc';
    // **NUEVO: Filtro de año**
    $anio_aging = isset($_GET['anio_aging']) ? $_GET['anio_aging'] : 'TODOS';

    // --- NUEVO: Obtener lista de años disponibles para el filtro ---
    $stmt_anios_aging = $db->query("SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact WHERE estado IN ('PENDIENTE', 'CONTABILIZADA', 'CERRADA') AND (fecha_pago IS NULL OR fecha_pago = '') ORDER BY anio DESC");
    $lista_anios_aging = $stmt_anios_aging->fetchAll(PDO::FETCH_COLUMN);
    if (empty($lista_anios_aging)) {
        $lista_anios_aging = [date('Y')];
    }
    // --- Fin nuevo ---

    // Actualizar descripción según filtros
    $desc_parts = [];
    if ($anio_aging != 'TODOS') {
        $desc_parts[] = "Año: " . $anio_aging;
    }
    if ($rango_aging != 'TODOS') {
        $desc_parts[] = "Rango: " . str_replace('-', ' - ', $rango_aging) . " días";
    }
    if (!empty($desc_parts)) {
        $descripcion_reporte .= " | " . implode(' | ', $desc_parts);
    }


    // --- MODIFICADO: Condición base, ahora incluye el filtro de año ---
    $where_base = "WHERE f.estado IN ('PENDIENTE', 'CONTABILIZADA', 'CERRADA') 
                   AND (f.fecha_pago IS NULL OR f.fecha_pago = '')";
    
    // **NUEVO: Aplicar filtro de año**
    if ($anio_aging !== 'TODOS') {
        $where_base .= " AND YEAR(f.fecha_emision) = " . intval($anio_aging);
    }
    
    // Aplicar filtro por rango de días si se seleccionó uno específico
    // Esto ahora filtrará sobre el año seleccionado (o todos los años)
    if ($rango_aging != 'TODOS') {
        switch($rango_aging) {
            case '1-30':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 1 AND 30";
                break;
            case '31-60':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 31 AND 60";
                break;
            case '61-90':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 61 AND 90";
                break;
            case '91-120':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 91 AND 120";
                break;
            case '121-150':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 121 AND 150";
                break;
            case '151-180':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 151 AND 180";
                break;
            case 'mas180':
                $where_base .= " AND DATEDIFF(CURDATE(), f.fecha_emision) > 180";
                break;
        }
    }
    
    // Configurar ordenamiento
    switch($orden_aging) {
        case 'fecha_asc':
            $order_by = "ORDER BY f.fecha_emision ASC";
            break;
        case 'fecha_desc':
            $order_by = "ORDER BY f.fecha_emision DESC";
            break;
        case 'monto_desc':
            $order_by = "ORDER BY f.total_general DESC";
            break;
        case 'monto_asc':
            $order_by = "ORDER BY f.total_general ASC";
            break;
        default:
            $order_by = "ORDER BY f.fecha_emision ASC";
    }
    
    // Total de registros para paginación
    $sql_count = "SELECT COUNT(*) as total FROM tbl_fact f $where_base";
    $stmt_count = $db->prepare($sql_count); 
    $stmt_count->execute();
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

    // Consulta principal
    $sql = "SELECT 
                f.no_fact as col1, 
                DATE_FORMAT(f.fecha_emision, '%d/%m/%Y') as col2,
                IFNULL(c.nombre, 'Sin Nombre') as col3,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 0 AND 30, f.total_general, 0) as col4,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 31 AND 60, f.total_general, 0) as col5,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 61 AND 90, f.total_general, 0) as col6,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 91 AND 120, f.total_general, 0) as col7,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 121 AND 150, f.total_general, 0) as col8,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 151 AND 180, f.total_general, 0) as col9,
                IF(DATEDIFF(CURDATE(), f.fecha_emision) > 180, f.total_general, 0) as col10
            FROM tbl_fact f 
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
            $where_base 
            $order_by 
            LIMIT $inicio, $registros_por_pagina";
            
    $stmt = $db->prepare($sql); 
    $stmt->execute();

    // Totales por rango (siempre se muestran todos los rangos en el footer)
    // **MODIFICADO: Esta consulta de totales también debe considerar el filtro de año**
    $sql_t = "SELECT 
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) <= 30, f.total_general, 0)) as t1,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 31 AND 60, f.total_general, 0)) as t2,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 61 AND 90, f.total_general, 0)) as t3,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 91 AND 120, f.total_general, 0)) as t4,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 121 AND 150, f.total_general, 0)) as t5,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) BETWEEN 151 AND 180, f.total_general, 0)) as t6,
                SUM(IF(DATEDIFF(CURDATE(), f.fecha_emision) > 180, f.total_general, 0)) as t7
                FROM tbl_fact f 
                WHERE f.estado IN ('PENDIENTE', 'CONTABILIZADA', 'CERRADA') 
                AND (f.fecha_pago IS NULL OR f.fecha_pago = '')";
                
    // **NUEVO: Aplicar el mismo filtro de año a la consulta de totales**
    if ($anio_aging !== 'TODOS') {
        $sql_t .= " AND YEAR(f.fecha_emision) = " . intval($anio_aging);
    }
                
    $st_t = $db->prepare($sql_t); 
    $st_t->execute();
    $r_t = $st_t->fetch(PDO::FETCH_ASSOC);
    
    $aging_totals = [
        4 => $r_t['t1'], 5 => $r_t['t2'], 6 => $r_t['t3'], 
        7 => $r_t['t4'], 8 => $r_t['t5'], 9 => $r_t['t6'], 10 => $r_t['t7']
    ];
    
    // Total general (solo de las facturas filtradas)
    $sql_total = "SELECT SUM(f.total_general) as total 
                 FROM tbl_fact f 
                 $where_base";
    $stmt_total = $db->prepare($sql_total);
    $stmt_total->execute();
    $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_general = $total_row['total'] ?? 0;
    break;
	
    }
    
    if (isset($stmt)) {
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cantidad_registros = count($data);
    }
    
    // Calcular total de páginas
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // 5. Obtener datos de finanzas para sidebar
    $finanzas = Database::getProgresoFinanciero();
    
} catch (Exception $e) {
    $alerta_error = $e->getMessage();
}

// Función mejorada que mantiene el orden correcto de los parámetros
function getUrlWithParams($params = []) {
    $current_params = $_GET;
    
    // Reportes que no usan fechas
    $reportes_sin_fechas = ['auditoria', 'vencimiento_contratos', 'rango_correlativos'];
    $tipo_actual = $current_params['tipo_reporte'] ?? 'ventas_generales';
    
    // Limpiar parámetros según el tipo de reporte
    if (in_array($tipo_actual, $reportes_sin_fechas)) {
        // Para reportes sin fechas, eliminar parámetros de fecha
        unset($current_params['fecha_inicio']);
        unset($current_params['fecha_fin']);
    }
    
    // Sobrescribir con los nuevos parámetros
    foreach ($params as $key => $value) {
        $current_params[$key] = $value;
    }
    
    // Construir URL
    $url_parts = [];
    
    // Siempre poner tipo_reporte primero
    if (isset($current_params['tipo_reporte'])) {
        $url_parts[] = 'tipo_reporte=' . urlencode($current_params['tipo_reporte']);
    }
    
    // Agregar parámetros en orden lógico
    $param_order = ['anio_filtro', 'mes_filtro', 'filtro_tipo', 'filtro_anio', 
                    'fecha_inicio', 'fecha_fin', 'pagina',
                    'fecha_inicio_aud', 'fecha_fin_aud', 'operacion_aud', 'usuario_aud', 'ip_aud',
                    'anio_hojas', 'mes_hojas']; // Nuevos parámetros
    
    foreach ($param_order as $param) {
        if (isset($current_params[$param]) && $current_params[$param] !== '') {
            $url_parts[] = $param . '=' . urlencode($current_params[$param]);
        }
    }
    
    // Agregar cualquier otro parámetro extra
    $parametros_extra = array_diff_key($current_params, array_flip(array_merge(['tipo_reporte'], $param_order)));
    foreach ($parametros_extra as $key => $value) {
        if ($value !== '') {
            $url_parts[] = $key . '=' . urlencode($value);
        }
    }
    
    return '?' . implode('&', $url_parts);
}


/**
 * Determina la alineación CSS de una columna de forma inteligente.
 * 
 * @param string $tipo_reporte     Nombre del reporte actual.
 * @param int    $col_index        Índice de la columna (empezando en 1).
 * @param mixed  $columna_dinero   Índice o Array de índices que son moneda.
 * @param array  $columnas_num     Array de índices que son números/cantidades.
 * @return string                  Clase CSS (text-left, text-center, text-right).
 */
function getAlineacionColumna($tipo_reporte, $col_index, $columna_dinero, $columnas_num = []) {
    // 1. DINERO (Siempre a la derecha)
    $es_dinero = false;
    if (is_array($columna_dinero)) {
        $es_dinero = in_array($col_index, $columna_dinero);
    } else {
        $es_dinero = ($col_index === $columna_dinero);
    }

    // 2. REGLAS ESPECÍFICAS DE ALINEACIÓN
    switch ($tipo_reporte) {
        case 'ventas_generales':
            if ($col_index === 3) return 'text-left';   // Cliente
            if ($col_index === 5) return 'text-center'; // Productos
            break;
        case 'rango_correlativos':
            if ($col_index === 2 || $col_index === 3 || $col_index === 4) return 'text-center'; 
            break;
		case 'hojas_utilizadas':
			if ($col_index === 1) return 'text-left';
			return 'text-right';
			break;
			
        case 'ventas_servicios':
            if ($col_index === 1 || $col_index === 2) return 'text-left'; // Servicio y Categoría
			if ($col_index === 3) return 'text-center';
            break;

        case 'ventas_por_categoria':
            if ($col_index === 1) return 'text-left';   // Categoría
            if ($col_index === 2 || $col_index === 3) return 'text-center'; // Cant. Facturas y Items
            break;

        case 'facturas_vencidas':
            if ($col_index === 2) return 'text-left';   // Cliente
            if ($col_index === 3 || $col_index === 4 || $col_index === 6) return 'text-center'; // Fecha Emisión
            break;

        case 'auditoria':
            if ($col_index === 4 || $col_index === 5) return 'text-left'; // Usuario y Descripción
            break;

        case 'metodos_pago':
            if ($col_index === 1) return 'text-left';   // Método
            if ($col_index === 2 || $col_index === 4) return 'text-center'; // Cant. Operaciones
            break;

        case 'deudores':
            if ($col_index === 1) return 'text-left';   // Cliente
            if ($col_index === 2) return 'text-center'; // Facturas Pendientes
            break;

        case 'plan_vs_real':
            if ($col_index === 1 || $col_index === 5 || $col_index === 2) return 'text-left';
			if ($col_index === 3 || $col_index === 4) return 'text-center';
            break;

        case 'productividad_usuarios':
            if ($col_index === 1 || $col_index === 3) return 'text-left';   // Nombre
            if ($col_index === 4 || $col_index === 2) return 'text-center'; // Facturas Creadas
            break;

        case 'ranking_clientes':
            if ($col_index === 1 || $col_index === 2) return 'text-left';
			if ($col_index === 4) return 'text-center';
			 if ($col_index === 5) return 'text-right';
            break;

        case 'estado_facturas':
            if ($col_index === 2 || $col_index === 4) return 'text-center'; // Cant. Facturas
			if ($col_index === 1) return 'text-left'; 
			if ($col_index === 3) return 'text-right'; 
            break;
        case 'vencimiento_contratos':
            if ($col_index === 2 || $col_index === 4 || $col_index === 5 || $col_index === 6 || $col_index === 7 || $col_index === 8 || $col_index === 9 || $col_index === 10) return 'text-center';
			if ($col_index === 1 ) return 'text-left'; 
            break;
case 'historico_cierres':
    if ($col_index == 1 || $col_index == 2 || $col_index == 9) return 'text-left'; // Tipo, Período, Observaciones
    if ($col_index == 3) return 'text-center'; // Fecha Ejecución
    if ($col_index == 5 || $col_index == 7 || $col_index == 8) return 'text-center'; // Total Fact., Pagadas, Contabilizadas
    if ($col_index == 6) return 'text-right'; // Importe Total (dinero)
    if ($col_index == 4) return 'text-left'; // Usuario
    break;
    }

    // 3. NÚMEROS RESTANTES (A la derecha si no se especificó centro arriba)
    if (in_array($col_index, $columnas_num)) return 'text-right';

    // 4. DEFAULT
    return 'text-left';
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
function getUTCOffset($timezone, $dospuntos=true, $ignoreDST=false)
	{
	  $timezone_identifiers = DateTimeZone::listIdentifiers();
	  $dtz = new DateTimeZone($timezone);
	  $format=($dospuntos)?'%+03d:%02u':'%+03d%02u';

		
	  if (!$ignoreDST)
		{
		  $offset = $dtz->getOffset(new DateTime());
		  return sprintf($format, $offset / 3600, abs($offset) % 3600 / 60).' '.$timezone_identifiers[114].' ('.date('T').')';
		}
	  else
		{
		  $transitions = $dtz->getTransitions(time());
		  foreach ($transitions as $transition)
		{
		  if (!$transition['isdst'])
			return sprintf($format, $transition['offset'] / 3600, abs($transition['offset']) % 3600 / 60).' '.$timezone_identifiers[114];
		}
		  return false;
		}
	}
	


$dias = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
$meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

// Comprobar Horario verano
$timestamp = (date("I") == 0) ? strtotime('-1 hour') : time();

$fechaGeneracion = "Hoy es: " . 
    $dias[date("w", $timestamp)] . ", " . 
    date("d", $timestamp) . " de " . 
    $meses_completos[date("n", $timestamp)-1] . " de " . 
    date("Y", $timestamp) . ", a las: " . 
    date("h:i:s", $timestamp) . " " . 
    strtolower(date("A", $timestamp));
	
	
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- LIBRERÍAS DE EXPORTACIÓN -->	
	<script src="js/jspdf.umd.min.js"></script>
	<script src="js/jspdf.plugin.autotable.min.js"></script>
	<script src="js/html2pdf.bundle.min.js"></script>
	<script src="js/xlsx.full.min.js"></script>

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
            --win-accent: <?php echo $color_accent; ?>;
            --win-accent-light: <?php echo $color_accent; ?>20;
            --win-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            --win-radius: 8px;
            --win-radius-sm: 6px;
            --win-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="light"] { --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }

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
        [data-theme="light"] .mica-effect { background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(0, 0, 0, 0.08); }

        /* Navbar & Sidebar Styles (IDÉNTICO) */
        .win-navbar { height: 48px; background: var(--win-bg-secondary); border-bottom: 1px solid var(--win-border-color); padding: 0 16px; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; align-items: center; gap: 12px; }
        .win-navbar-brand { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .win-nav-search { flex: 1; max-width: 400px; position: relative; margin-bottom: 5px; padding: 0 5px; }
        .win-nav-search input { background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); color: var(--win-text-primary); border-radius: var(--win-radius-sm); padding: 8px 12px 8px 30px; font-size: 13px; width: 100%; transition: var(--win-transition); }
        .win-nav-search input:focus { outline: none; border-color: var(--win-accent); box-shadow: 0 0 0 2px var(--win-accent-light); }
        .win-nav-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--win-text-secondary); font-size: 14px; }
        
        .win-sidebar { width: 260px; background: var(--win-bg-secondary); border-right: 1px solid var(--win-border-color); height: calc(100vh - 48px); position: fixed; left: 0; top: 48px; z-index: 999; transition: var(--win-transition); overflow-y: auto; padding: 16px 0; }
        .win-sidebar.mini { width: 68px; }
        .win-sidebar-header { padding: 0 16px 16px; border-bottom: 1px solid var(--win-border-color); margin-bottom: 16px; }
        .win-sidebar-user { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-sidebar-user:hover { background: var(--win-bg-tertiary); }
        .win-sidebar-user-avatar { width: 36px; height: 36px; background: var(--win-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .win-nav { list-style: none; padding: 0; margin: 0; }
        .win-nav-item { margin: 2px 8px; }
        .win-nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--win-text-secondary); text-decoration: none; border-radius: var(--win-radius-sm); transition: var(--win-transition); font-size: 14px; position: relative; }
        .win-nav-link:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }
        .win-nav-link.active { background: var(--win-accent-light); color: var(--win-accent); font-weight: 500; }
        .win-nav-link.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--win-accent); border-radius: 0 2px 2px 0; }
        .win-nav-icon { width: 20px; text-align: center; font-size: 16px; }
        .win-sidebar.mini .win-nav-text, .win-sidebar.mini .win-sidebar-user-info, .win-sidebar.mini .win-nav-badge { display: none; }
        .win-nav-badge { margin-left: auto; background: var(--win-accent); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; min-width: 20px; text-align: center; font-weight: 600; }

        /* Contenido Principal */
        .win-main-content { margin-left: 260px; margin-top: 48px; padding: 24px; transition: var(--win-transition); min-height: calc(100vh - 48px); }
        .win-main-content.sidebar-mini { margin-left: 68px; }
        .win-main-content .card { background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); margin-bottom: 1.5rem; transition: var(--win-transition); }
        .win-main-content .card:hover { border-color: var(--win-accent); box-shadow: var(--win-shadow); }
        .win-main-content .card-header { background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border-color); padding: 1rem 1.25rem; }
        .win-main-content .card-body { padding: 1.25rem; color: var(--win-text-primary); }
        .win-main-content .form-control, .win-main-content .form-select { background-color: var(--win-bg-tertiary); border-color: var(--win-border-color); color: var(--win-text-primary); }
        .win-main-content .form-control:focus, .win-main-content .form-select:focus { background-color: var(--win-bg-tertiary); border-color: var(--win-accent); color: var(--win-text-primary); box-shadow: 0 0 0 0.25rem var(--win-accent-light); }
        
        /* Stats Card Style */
        .stats-card { background: var(--win-bg-secondary); border: 1px solid var(--win-border_color); border-radius: var(--win-radius); padding: 15px; display: flex; align-items: center; margin-bottom: 20px; transition: var(--win-transition); }
        .stats-card:hover { transform: translateY(-3px); box-shadow: var(--win-shadow); border-color: var(--win-accent); }
        .stats-icon { width: 50px; height: 50px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size: 24px; margin-right: 15px; background: var(--win-bg-tertiary); color: var(--win-accent); }
        
        /* Panel Temas */
        .win-theme-panel { position: fixed; top: 48px; right: 0; width: 300px; background: var(--win-bg-secondary); border-left: 1px solid var(--win-border-color); height: calc(100vh - 48px); z-index: 1001; transform: translateX(100%); transition: var(--win-transition); padding: 24px; overflow-y: auto; }
        .win-theme-panel.open { transform: translateX(0); }
        .win-theme-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: none; }
        .win-theme-overlay.open { display: block; }
        .win-theme-option { padding: 16px; border: 2px solid var(--win-border-color); border-radius: var(--win-radius); cursor: pointer; transition: var(--win-transition); text-align: center; color: var(--win-text-primary); }
        .win-theme-option.active { border-color: var(--win-accent); background: var(--win-accent-light); }
        .win-color-option { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: var(--win-transition); }
        .win-color-option:hover { transform: scale(1.1); }
        .win-color-option.active { border-color: white; box-shadow: 0 0 0 2px var(--win-bg-secondary); }

        /* Dropdown (igual que nuevo_usuario.php) */
        .dropdown-menu { background-color: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); }
        .dropdown-header { background-color: var(--win-bg-tertiary); border-radius: var(--win-radius-sm) var(--win-radius-sm) 0 0; }
        .dropdown-footer { background-color: var(--win-bg-tertiary); border-radius: 0 0 var(--win-radius-sm) var(--win-radius-sm); }
        .dropdown-item { color: var(--win-text-primary); transition: var(--win-transition); border-radius: var(--win-radius-sm); margin: 2px 4px; }
        .dropdown-item:hover { background-color: var(--win-accent-light); color: var(--win-accent); }
        .dropdown-divider { border-color: var(--win-border-color); }
        .dropdown-item.text-danger:hover { background-color: rgba(220, 53, 69, 0.1); color: #dc3545 !important; }

        /* ESTILOS DE IMPRESIÓN */
        @media print {
            .no-print, .win-sidebar, .win-navbar, .export-combo-container, 
            .filter-card, .compact-report-cards, .btn, .dropdown, 
            .form-control, .form-select, .alert, .swal2-container,
            .pagination-container, .table-controls {
                display: none !important;
            }
            
            body, .win-main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                background: white !important;
                color: black !important;
                font-family: 'Arial', sans-serif !important;
                font-size: 12px !important;
            }
            
            .print-header, .print-footer {
                display: block !important;
                color: black !important;
            }
            
            .report-table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 1px solid #000 !important;
                font-size: 11px !important;
                color: black !important;
                page-break-inside: auto !important;
            }
            
            .report-table th {
                background-color: #f0f0f0 !important;
                color: black !important;
                border: 1px solid #000 !important;
                padding: 6px !important;
                text-align: center !important;
                font-weight: bold !important;
            }
            
            .report-table td {
                border: 1px solid #000 !important;
                padding: 5px !important;
                color: black !important;
            }
            
            .text-left { text-align: left !important; }
            .text-center { text-align: center !important; }
            .text-right { text-align: right !important; }
            
            .badge-reporte {
                background: transparent !important;
                color: black !important;
                border: none !important;
                padding: 0 !important;
                font-size: inherit !important;
                display: inline !important;
            }
            
            .tfoot-total {
                background-color: #e0e0e0 !important;
                font-weight: bold !important;
                border-top: 2px solid #000 !important;
            }
            
            .text-dinero, .text-porcentaje {
                color: black !important;
                font-weight: bold !important;
            }
            
            @page {
                size: letter;
                margin: 15mm;
                
                @bottom-center {
                    content: "Página " counter(page) " de " counter(pages);
                    font-size: 10px;
                    color: #666;
                }
            }
            
            tr { page-break-inside: avoid !important; }
        }

        /* TARJETAS DE REPORTES COMPACTAS */
.compact-report-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.compact-report-card {
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
    border-radius: 10px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    min-height: 80px;
    position: relative;
    overflow: hidden;
}

.compact-report-card:hover {
    transform: translateY(-3px);
    border-color: var(--win-accent);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.compact-report-card.selected {
    border-color: var(--win-accent);
    background: var(--win-accent-light);
    box-shadow: 0 0 0 2px var(--win-accent-light);
}

.compact-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white !important;
    flex-shrink: 0;
    margin-right: 15px;
}

.compact-card-icon i {
    color: white !important;
}

.compact-card-content {
    flex: 1;
    display: flex;
    align-items: flex-start;
    width: 100%;
}

.compact-card-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: var(--win-text-primary);
    line-height: 1.3;
}

.compact-card-desc {
    font-size: 11px;
    color: var(--win-text-secondary);
    margin: 0;
    line-height: 1.4;
    opacity: 0.9;
}

/* Responsive para el nuevo diseño */
@media (max-width: 1600px) {
    .compact-report-cards {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1200px) {
    .compact-report-cards {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .compact-report-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .compact-report-card {
        padding: 12px;
        min-height: 70px;
    }
    
    .compact-card-icon {
        width: 35px;
        height: 35px;
        font-size: 16px;
        margin-right: 12px;
    }
    
    .compact-card-title {
        font-size: 13px;
    }
    
    .compact-card-desc {
        font-size: 10px;
    }
}

@media (max-width: 480px) {
    .compact-report-cards {
        grid-template-columns: 1fr;
    }
}

        /* BOTONES DE ACCIÓN */
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-imprimir:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* COMBO DE EXPORTACIÓN */
        .export-combo-container {
            position: relative;
            display: inline-block;
        }

        .export-combo-btn {
            background: linear-gradient(135deg, #0078d4, #005a9e);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .export-combo-btn:hover {
            background: linear-gradient(135deg, #005a9e, #004578);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .export-combo-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: 8px;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            margin-top: 5px;
            overflow: hidden;
        }

        .export-combo-menu.show {
            display: block;
        }

        .export-combo-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--win-text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--win-border-color);
        }

        .export-combo-item:last-child {
            border-bottom: none;
        }

        .export-combo-item:hover {
            background: var(--win-accent-light);
            color: var(--win-accent);
        }

        /* Tabla de reportes */
        .report-container {
            background: var(--win-bg-secondary);
            border-radius: var(--win-radius);
            overflow: hidden;
            border: 1px solid var(--win-border-color);
        }
        
        .report-table {
            width: 100%;
            color: var(--win-text-primary);
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .report-table thead th {
            background: var(--win-bg-tertiary);
            color: var(--win-text-primary);
            font-weight: 600;
            padding: 14px 12px;
            border-bottom: 2px solid var(--win-border-color);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }
        
        .report-table thead th:hover {
            background: var(--win-accent-light);
        }
        
        .report-table thead th .sort-icon {
            margin-left: 5px;
            opacity: 0.5;
        }
        
        .report-table thead th.sorted-asc .sort-icon,
        .report-table thead th.sorted-desc .sort-icon {
            opacity: 1;
        }
        
        .report-table tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .report-table tbody tr:hover {
            background: var(--win-accent-light);
        }
        
        /* ALINEACIONES ESPECÍFICAS */
        .text-left { text-align: left !important; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        /* Estilos específicos por tipo de dato */
        .text-dinero {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .text-porcentaje {
            color: #2196F3;
            font-weight: bold;
        }
        
        /* Pie de tabla con totales */
        .tfoot-total {
            background: var(--win-bg-tertiary);
            font-weight: bold;
            border-top: 2px solid var(--win-accent);
        }
        
        .tfoot-total td {
            padding: 14px 12px;
            color: var(--win-text-primary);
        }
        
        .badge-reporte {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-reporte-success {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
        }
        
        .badge-reporte-info {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
            color: white;
        }
        
        .badge-reporte-warning {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
        }
        
        .badge-reporte-danger {
            background: linear-gradient(135deg, #F44336, #D32F2F);
            color: white;
        }
        
        .badge-reporte-secondary {
            background: linear-gradient(135deg, #757575, #616161);
            color: white;
        }
        .badge-reporte-cerrpag {
            background: linear-gradient(135deg, #5C6BC0, #283593);
            color: white;
        }
        /* PAGINACIÓN */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background: var(--win-bg-secondary);
            border-radius: var(--win-radius);
            border: 1px solid var(--win-border-color);
        }
        
        .pagination-info {
            color: var(--win-text-secondary);
            font-size: 0.9rem;
        }
        
        .page-link {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
        }
        
        .page-link:hover {
            background: var(--win-accent-light);
            border-color: var(--win-accent);
        }
        
        .page-item.active .page-link {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }
        
        /* CONTROLES DE TABLA */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--win-bg-secondary);
            border-radius: var(--win-radius);
            border: 1px solid var(--win-border_color);
        }
        
        .registros-por-pagina {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .registros-por-pagina select {
            background: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            padding: 5px 10px;
            border-radius: 4px;
        }

        /* Filtros compactos */
        .filter-card {
            border: none;
            border-radius: var(--win-radius);
            background: var(--win-bg-secondary);
            margin-bottom: 1.5rem;
            padding: 15px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--win-text-primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Corrección Legibilidad Text Muted (igual que nuevo_usuario.php) */
        .text-muted, .win-main-content .text-muted, .form-text, small.text-muted { color: #e0e0e0 !important; opacity: 0.9; }
        [data-theme="light"] .text-muted, [data-theme="light"] .win-main-content .text-muted, [data-theme="light"] .form-text, [data-theme="light"] small.text-muted { color: #555555 !important; opacity: 1; }
        ::placeholder { color: #cccccc !important; opacity: 0.7 !important; }
        [data-theme="light"] ::placeholder { color: #666666 !important; }

        /* Responsive */
        @media (max-width: 1600px) {
            .compact-report-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .compact-report-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) { 
            .win-sidebar { transform: translateX(-100%); } 
            .win-sidebar.open { transform: translateX(0); } 
            .win-main-content { margin-left: 0; } 
        }

        @media (max-width: 768px) {
            .win-main-content {
                padding: 16px;
                margin-left: 0;
            }
            
            .compact-report-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-imprimir, .export-combo-btn {
                width: 100%;
                justify-content: center;
            }
            
            .export-combo-container {
                width: 100%;
            }
            
            .export-combo-menu {
                width: 100%;
                right: auto;
                left: 0;
            }
            
            .table-controls {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .report-table {
                font-size: 0.8rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 8px 6px;
            }
        }

        @media (max-width: 480px) {
            .compact-report-cards {
                grid-template-columns: 1fr;
            }
        }
        /* Tooltips */
        .tooltip {
            --bs-tooltip-bg: var(--win-bg-tertiary);
            --bs-tooltip-color: var(--win-text-primary);
			z-index: 10000 !important; /* Asegura que flote sobre todo */
			opacity: 1 !important;
        }
.tooltip-inner {
    background-color: var(--win-accent) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3); /* Sombra más pronunciada tipo Win11 */
    font-size: 0.85rem;
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.2); /* Borde sutil */
}

/* Ajuste para que la flecha coincida con el color de acento */
.bs-tooltip-top .tooltip-arrow::before { border-top-color: var(--win-accent) !important; }
.bs-tooltip-bottom .tooltip-arrow::before { border-bottom-color: var(--win-accent) !important; }
.bs-tooltip-start .tooltip-arrow::before { border-left-color: var(--win-accent) !important; }
.bs-tooltip-end .tooltip-arrow::before { border-right-color: var(--win-accent) !important; }

/* La flechita del tooltip */
.tooltip-arrow::before {
    border-top-color: var(--win-accent) !important;
}
    </style>
</head>
<body>
    <!-- Overlay para tema panel -->
    <div class="win-theme-overlay" id="themeOverlay"></div>

    <!-- Panel de configuración de temas (IDÉNTICO) -->
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light">
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

    <!-- Navbar principal (IDÉNTICO) -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">INTELIGENCIA DE EMPRESA - SISFACT PDL Visiones</span>
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
                <li><a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-3" style="width: 20px;"></i><div><span class="d-block fw-bold">Cerrar Sesión</span><small class="text-muted d-block" style="font-size: 12px;">Salir del sistema</small></div></a></li>
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

    <!-- Sidebar inmersivo (IDÉNTICO) -->
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
            <li class="win-nav-item"><a href="facturas.php" class="win-nav-link"><i class="win-nav-icon fas fa-file-invoice"></i><span class="win-nav-text">Facturas</span><span class="win-nav-badge"><?php echo $total_facturas; ?></span></a></li>
            <li class="win-nav-item"><a href="clientes.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Clientes/Contratos</span><span class="win-nav-badge"><?php echo $total_clientes; ?></span></a></li>
            <li class="win-nav-item"><a href="categorias.php" class="win-nav-link"><i class="win-nav-icon fas fa-tags"></i><span class="win-nav-text">Categorías</span><span class="win-nav-badge"><?php echo $total_categorias; ?></span></a></li>
            <li class="win-nav-item"><a href="servicios.php" class="win-nav-link"><i class="win-nav-icon fas fa-list"></i><span class="win-nav-text">Servicios</span><span class="win-nav-badge"><?php echo $total_servicios; ?></span></a></li>
            <li class="win-nav-item"><a href="usuarios.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Usuarios</span><span class="win-nav-badge"><?php echo $total_usuarios; ?></span></a></li>
            <li class="win-nav-item">
                <a href="reportes.php" class="win-nav-link active">
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
            <li class="mt-3 mb-2 px-3">
				<small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Configuración</small>
			</li>
            <li class="win-nav-item">
				<a href="planes.php" class="win-nav-link">
					<i class="win-nav-icon fas fa-money-bill-wave"></i>
					<span class="win-nav-text">Plan de Ingresos</span>
					<span class="win-nav-badge"><?php echo obtenerAnioCierreOperaciones(); ?></span>
				</a>
			</li>
            <li class="win-nav-item"><a href="configuracion.php" class="win-nav-link"><i class="win-nav-icon fas fa-cog"></i><span class="win-nav-text">Configuración</span></a></li>
            <li class="win-nav-item"><a href="historico_view.php" class="win-nav-link"><i class="win-nav-icon fas fa-history"></i><span class="win-nav-text">Histórico</span><span class="win-nav-badge"><?php echo $estadisticas['total']; ?></span></a></li>
        </ul>
        
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
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" role="progressbar" style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>;" aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;"><strong>$<?php echo number_format($finanzas['real'], 2); ?></strong></small>
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;"><i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas</small>
                </div>
                <small class="text-end text-success" style="font-size: 12px;">Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?></small>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        
        <!-- Alerta PHP visible si existe (Backup) -->
        <?php if ($alerta_error): ?>
            <div class="alert alert-danger no-print"><?php echo $alerta_error; ?></div>
        <?php endif; ?>

        <!-- Header del Reporte -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h1 class="h3 fw-bold mb-1"><?php echo $titulo_reporte; ?></h1>
                <p class="text-muted small mb-0"><?php echo $descripcion_reporte; ?></p>
				
                <p class="text-muted small">
                    <i class="fas fa-filter me-1"></i>
                    <?php if($tipo_reporte != 'vencimiento_contratos' && $tipo_reporte != 'auditoria'): ?>
                        <?php echo date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)); ?>
                    <?php else: ?>
                        Todos los registros
                    <?php endif; ?>
                    | Registros totales: <?php echo $total_registros; ?>
                    <?php if($mostrar_totales): ?> | Total: $<?php echo number_format($total_general, 2); ?><?php endif; ?>
                </p>
            </div>
            
            <!-- BOTONES DE ACCIÓN -->
            <div class="action-buttons no-print">
                <button class="btn-imprimir" onclick="imprimirReporte()">
                    <i class="fas fa-print"></i>
                    <span>IMPRIMIR</span>
                </button>
                
                <div class="export-combo-container">
                    <button class="export-combo-btn" onclick="toggleExportMenu()">
                        <i class="fas fa-download"></i>
                        <span>EXPORTAR</span>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </button>
                    <div class="export-combo-menu" id="exportMenu">
                        <a href="javascript:void(0)" class="export-combo-item" onclick="exportarExcel()">
                            <i class="fas fa-file-excel text-success"></i>
                            <span>Excel (.xlsx)</span>
                        </a>
                        <a href="javascript:void(0)" class="export-combo-item" onclick="exportarWord()">
                            <i class="fas fa-file-word text-primary"></i>
                            <span>Word (.doc)</span>
                        </a>
                        <a href="javascript:void(0)" class="export-combo-item" onclick="exportarPDF()">
                            <i class="fas fa-file-pdf text-danger"></i>
                            <span>PDF (.pdf)</span>
                        </a>
                        <a href="javascript:void(0)" class="export-combo-item" onclick="exportarCSV()">
                            <i class="fas fa-file-csv text-info"></i>
                            <span>CSV (.csv)</span>
                        </a>
<a href="javascript:void(0)" class="export-combo-item" onclick="exportarTXT()">
    <i class="fas fa-file-alt text-secondary"></i>
    <span>Texto (.txt)</span>
</a>
                    </div>
                </div>
            </div>
        </div>

<!-- TARJETAS DE REPORTES - DISEÑO NUEVO: ICONO AL LADO DEL TÍTULO -->
<div class="compact-report-cards no-print">
    
    <!-- Facturación General -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'ventas_generales', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'ventas_generales' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Facturación General
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Listado completo de facturas emitidas en el período
                </p>
            </div>
        </div>
    </a>

<!-- Rango de Correlativos -->
<a href="<?= getUrlWithParams(['tipo_reporte' => 'rango_correlativos', 'pagina' => 1]) ?>" 
   class="compact-report-card <?php echo $tipo_reporte == 'rango_correlativos' ? 'selected' : ''; ?>" 
   style="text-decoration: none;">
    <div class="compact-card-content">
        <div class="compact-card-icon" style="background: linear-gradient(135deg, #0078d4, #004578);">
            <i class="fas fa-list-ol"></i>
        </div>
        <div style="flex: 1;">
            <h6 class="compact-card-title">Rango Correlativo</h6>
            <p class="compact-card-desc">Control de correlativos y cantidad de facturas emitidas por período</p>
        </div>
    </div>
</a>
	
    <!-- Histórico de Cierres -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'historico_cierres', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'historico_cierres' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #6f42c1, #4a2e8f); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Histórico de Cierres
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Cierres mensuales y anuales ejecutados
                </p>
            </div>
        </div>
    </a>

    <!-- Consumo de Papel -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'hojas_utilizadas', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'hojas_utilizadas' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #00BCD4, #00838F); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-copy"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Consumo de Papel
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Papel normal, fotográfico y cartulina
                </p>
            </div>
        </div>
    </a>

    <!-- Diferencias Facturas -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'diferencia_facturas', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'diferencia_facturas' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #FF5722, #E64A19); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Diferencias Facturas
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Verifica inconsistencias entre facturas y detalles
                </p>
            </div>
        </div>
    </a>

    <!-- Facturas Vencidas -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'facturas_vencidas', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'facturas_vencidas' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #DC3545, #C82333); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-clock"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Facturas Vencidas
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Fuera de plazo por antigüedad de días
                </p>
            </div>
        </div>
    </a>

    <!-- Envejecimiento -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'aging_por_cliente', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'aging_por_cliente' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #FF5722, #E64A19); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-history"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Envejecimiento
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Saldos por 30, 60, 90+ días de antigüedad
                </p>
            </div>
        </div>
    </a>

    <!-- Rendimiento Servicios -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'ventas_servicios', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'ventas_servicios' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-tools"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Rendimiento Servicios
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Análisis por servicios más vendidos
                </p>
            </div>
        </div>
    </a>

    <!-- Ventas por Categoría -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'ventas_por_categoria', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'ventas_por_categoria' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-tags"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Ventas por Categoría
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Análisis por tipo de servicio
                </p>
            </div>
        </div>
    </a>

    <!-- Métodos de Pago -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'metodos_pago', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'metodos_pago' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #2196F3, #0D47A1); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-credit-card"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Métodos de Pago
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Distribución por vía de pago utilizada
                </p>
            </div>
        </div>
    </a>

    <!-- Auditoría -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'auditoria', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'auditoria' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #FFC107, #FFA000); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-history"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Auditoría
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Historial completo de acciones del sistema
                </p>
            </div>
        </div>
    </a>

    <!-- Deudores -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'deudores', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'deudores' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #F44336, #D32F2F); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Deudores
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Cuentas por cobrar pendientes
                </p>
            </div>
        </div>
    </a>

    <!-- Ranking Clientes -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'ranking_clientes', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'ranking_clientes' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-trophy"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Ranking Clientes
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Top clientes por facturación total
                </p>
            </div>
        </div>
    </a>

    <!-- Plan vs Real -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'plan_vs_real', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'plan_vs_real' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #00BCD4, #00838F); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Plan vs Real
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Cumplimiento de metas mensuales
                </p>
            </div>
        </div>
    </a>

    <!-- Vencimiento Contratos -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'vencimiento_contratos', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'vencimiento_contratos' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Vencimiento Contratos
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Control de contratos por vencer
                </p>
            </div>
        </div>
    </a>

    <!-- Estado Facturas -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'estado_facturas', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'estado_facturas' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Estado Facturas
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Distribución por estado (Pagada, Pendiente, etc.)
                </p>
            </div>
        </div>
    </a>

    <!-- Productividad Usuarios -->
    <a href="<?= getUrlWithParams(['tipo_reporte' => 'productividad_usuarios', 'pagina' => 1]) ?>" 
       class="compact-report-card <?php echo $tipo_reporte == 'productividad_usuarios' ? 'selected' : ''; ?>" 
       style="text-decoration: none;">
        <div class="compact-card-content" style="display: flex; align-items: flex-start; width: 100%;">
            <div class="compact-card-icon" style="background: linear-gradient(135deg, #757575, #616161); margin-right: 15px; margin-bottom: 0; flex-shrink: 0;">
                <i class="fas fa-users"></i>
            </div>
            <div style="flex: 1;">
                <h6 class="compact-card-title" style="margin: 0 0 5px 0; font-size: 14px; font-weight: 600; color: var(--win-text-primary);">
                    Productividad
                </h6>
                <p class="compact-card-desc" style="font-size: 11px; color: var(--win-text-secondary); margin: 0; line-height: 1.4;">
                    Ventas por usuario del sistema
                </p>
            </div>
        </div>
    </a>

</div>
		
<!-- Filtros simplificados -->
<div class="card filter-card mb-4 no-print">
    <form method="GET" class="filter-form">
        <div class="filter-row">
            <div class="filter-group">
                <label><i class="fas fa-chart-bar me-1"></i> Tipo de Reporte</label>
                <select name="tipo_reporte" class="form-select" onchange="this.form.submit()">
                    <option value="ventas_generales" <?=($tipo_reporte=='ventas_generales'?'selected':'')?>>📊 Facturación General</option>
                    <option value="rango_correlativos" <?=($tipo_reporte=='rango_correlativos'?'selected':'')?>>📅 Rango Correlativo</option>
                    <option value="historico_cierres" <?=($tipo_reporte=='historico_cierres'?'selected':'')?>>📅 Histórico de Cierres</option>
                    <option value="hojas_utilizadas" <?=($tipo_reporte=='hojas_utilizadas'?'selected':'')?>>📄 Consumo de Papel</option>
                    <option value="diferencia_facturas" <?=($tipo_reporte=='diferencia_facturas'?'selected':'')?>>⚖️ Diferencias Facturas vs Detalles</option>
                    <option value="facturas_vencidas" <?=($tipo_reporte=='facturas_vencidas'?'selected':'')?>>⏰ Facturas Vencidas</option>
                    <option value="aging_por_cliente" <?=($tipo_reporte=='aging_por_cliente'?'selected':'')?>>⏳ Antigüedad Facturas</option>
                    <option value="ventas_servicios" <?=($tipo_reporte=='ventas_servicios'?'selected':'')?>>🛠 Rendimiento por Servicios</option>
                    <option value="ventas_por_categoria" <?=($tipo_reporte=='ventas_por_categoria'?'selected':'')?>>🏷️ Ventas por Categoría</option>
                    <option value="metodos_pago" <?=($tipo_reporte=='metodos_pago'?'selected':'')?>>💳 Métodos de Pago</option>
                    <option value="auditoria" <?=($tipo_reporte=='auditoria'?'selected':'')?>>👁 Auditoria</option>
                    <option value="deudores" <?=($tipo_reporte=='deudores'?'selected':'')?>>💲 Deudores</option>
                    <option value="ranking_clientes" <?=($tipo_reporte=='ranking_clientes'?'selected':'')?>>🏆 Ranking de Clientes</option>
                    <option value="plan_vs_real" <?=($tipo_reporte=='plan_vs_real'?'selected':'')?>>🎯 Cumplimiento del Plan</option>
                    <option value="vencimiento_contratos" <?=($tipo_reporte=='vencimiento_contratos'?'selected':'')?>>⚠️ Vencimiento de Contratos</option>
                    <option value="estado_facturas" <?=($tipo_reporte=='estado_facturas'?'selected':'')?>>📊 Estado de Facturas</option>
                    <option value="productividad_usuarios" <?=($tipo_reporte=='productividad_usuarios'?'selected':'')?>>👤 Productividad de Usuarios</option>
                </select>
            </div>
            
            <?php if($tipo_reporte == 'rango_correlativos'): ?>
                <!-- FILTROS ESPECÍFICOS PARA RANGO CORRELATIVOS (AÑO Y MES) -->
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> Año</label>
                    <select name="anio_filtro" class="form-select" onchange="this.form.submit()">
                        <option value="TODOS" <?= ($anio_filtro == 'TODOS') ? 'selected' : '' ?>>📅 Todos los años</option>
                        <?php foreach ($lista_anios as $anio): ?>
                            <option value="<?= $anio ?>" <?= ($anio_filtro == $anio) ? 'selected' : '' ?>>
                                📆 Año <?= $anio ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
				
<?php elseif($tipo_reporte == 'hojas_utilizadas'): ?>
    <!-- FILTROS PARA HOJAS UTILIZADAS -->
    <div class="filter-group" style="flex: 0 0 auto; min-width: 150px;">
        <label><i class="fas fa-calendar me-1"></i> Año</label>
        <select name="anio_hojas" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['anio_hojas']) && $_GET['anio_hojas'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los años</option>
            <?php
            $stmt_anios_hojas = $db->query("SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact ORDER BY anio DESC");
            $anios_hojas = $stmt_anios_hojas->fetchAll(PDO::FETCH_COLUMN);
            foreach ($anios_hojas as $anio) {
                $selected = (isset($_GET['anio_hojas']) && $_GET['anio_hojas'] == $anio) ? 'selected' : '';
                echo "<option value='$anio' $selected>Año $anio</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="filter-group" style="flex: 0 0 auto; min-width: 150px;">
        <label><i class="fas fa-calendar-alt me-1"></i> Mes</label>
        <select name="mes_hojas" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los meses</option>
            <option value="1" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '1') ? 'selected' : '' ?>>Enero</option>
            <option value="2" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '2') ? 'selected' : '' ?>>Febrero</option>
            <option value="3" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '3') ? 'selected' : '' ?>>Marzo</option>
            <option value="4" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '4') ? 'selected' : '' ?>>Abril</option>
            <option value="5" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '5') ? 'selected' : '' ?>>Mayo</option>
            <option value="6" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '6') ? 'selected' : '' ?>>Junio</option>
            <option value="7" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '7') ? 'selected' : '' ?>>Julio</option>
            <option value="8" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '8') ? 'selected' : '' ?>>Agosto</option>
            <option value="9" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '9') ? 'selected' : '' ?>>Septiembre</option>
            <option value="10" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '10') ? 'selected' : '' ?>>Octubre</option>
            <option value="11" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '11') ? 'selected' : '' ?>>Noviembre</option>
            <option value="12" <?= (isset($_GET['mes_hojas']) && $_GET['mes_hojas'] == '12') ? 'selected' : '' ?>>Diciembre</option>
        </select>
    </div>
    
    <!-- FILTRO POR CATEGORÍA -->
    <div class="filter-group" style="flex: 0 0 auto; min-width: 200px;">
        <label><i class="fas fa-tags me-1"></i> Categoría</label>
        <select name="categoria_hojas" class="form-select" onchange="this.form.submit()">
            <option value="TODAS" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'TODAS') ? 'selected' : '' ?>>📋 Todas las categorías</option>
            <option value="BOND" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'BOND') ? 'selected' : '' ?>>📄 Papel Normal (Bond)</option>
            <option value="FOTO" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'FOTO') ? 'selected' : '' ?>>📸 Papel Fotográfico</option>
            <option value="SUBLIMACION" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'SUBLIMACION') ? 'selected' : '' ?>>🎨 Papel de Sublimación</option>
            <option value="CART" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'CART') ? 'selected' : '' ?>>📇 Cartulina / Diplomas</option>
            <option value="CREDENCIALES" <?= (isset($_GET['categoria_hojas']) && $_GET['categoria_hojas'] == 'CREDENCIALES') ? 'selected' : '' ?>>🪪 Credenciales / Tarjetas</option>
        </select>
    </div>
    
    <!-- BOTÓN FILTRAR AL LADO -->
    <div class="filter-group" style="flex: 0 0 auto; min-width: 120px;">
        <label style="visibility: hidden;">Filtrar</label>
        <button type="submit" class="btn btn-primary w-100" style="height: 38px;">
            <i class="fas fa-search me-1"></i>Filtrar
        </button>
    </div>



<?php elseif($tipo_reporte == 'aging_por_cliente'): ?>
    <!-- FILTROS ESPECÍFICOS PARA AGING POR CLIENTE -->
    <div class="filter-group">
        <label><i class="fas fa-filter me-1"></i> Rango de Antigüedad</label>
        <select name="rango_aging" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == 'TODOS') ? 'selected' : '' ?>>📊 Todos los rangos</option>
            <option value="1-30" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '1-30') ? 'selected' : '' ?>>1 - 30 días</option>
            <option value="31-60" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '31-60') ? 'selected' : '' ?>>31 - 60 días</option>
            <option value="61-90" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '61-90') ? 'selected' : '' ?>>61 - 90 días</option>
            <option value="91-120" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '91-120') ? 'selected' : '' ?>>91 - 120 días</option>
            <option value="121-150" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '121-150') ? 'selected' : '' ?>>121 - 150 días</option>
            <option value="151-180" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == '151-180') ? 'selected' : '' ?>>151 - 180 días</option>
            <option value="mas180" <?= (isset($_GET['rango_aging']) && $_GET['rango_aging'] == 'mas180') ? 'selected' : '' ?>>Más de 180 días</option>
        </select>
    </div>
  <div class="filter-group">
        <label><i class="fas fa-calendar me-1"></i> Año de la Factura</label>
        <select name="anio_aging" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['anio_aging']) && $_GET['anio_aging'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los años</option>
            <?php
            // Usar la lista de años que obtuvimos en el PHP
            if (isset($lista_anios_aging)) {
                foreach ($lista_anios_aging as $anio): 
                    $selected = (isset($_GET['anio_aging']) && $_GET['anio_aging'] == $anio) ? 'selected' : '';
            ?>
                <option value="<?= $anio ?>" <?= $selected ?>>Año <?= $anio ?></option>
            <?php 
                endforeach; 
            } 
            ?>
        </select>
    </div>
    <div class="filter-group">
        <label><i class="fas fa-sort-amount-down me-1"></i> Ordenar por</label>
        <select name="orden_aging" class="form-select" onchange="this.form.submit()">
            <option value="fecha_asc" <?= (isset($_GET['orden_aging']) && $_GET['orden_aging'] == 'fecha_asc') ? 'selected' : '' ?>>📅 Fecha (más antigua primero)</option>
            <option value="fecha_desc" <?= (isset($_GET['orden_aging']) && $_GET['orden_aging'] == 'fecha_desc') ? 'selected' : '' ?>>📅 Fecha (más reciente primero)</option>
            <option value="monto_desc" <?= (isset($_GET['orden_aging']) && $_GET['orden_aging'] == 'monto_desc') ? 'selected' : '' ?>>💰 Monto (mayor a menor)</option>
            <option value="monto_asc" <?= (isset($_GET['orden_aging']) && $_GET['orden_aging'] == 'monto_asc') ? 'selected' : '' ?>>💰 Monto (menor a mayor)</option>
        </select>
    </div>
    
    <div class="filter-group">
        <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
            <i class="fas fa-search me-1"></i>Filtrar
        </button>
    </div>


<?php elseif($tipo_reporte == 'facturas_vencidas'): ?>
    <!-- FILTROS ESPECÍFICOS PARA FACTURAS VENCIDAS -->
    <div class="filter-group">
        <label><i class="fas fa-calendar me-1"></i> Año</label>
        <select name="anio_vencidas" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['anio_vencidas']) && $_GET['anio_vencidas'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los años</option>
            <?php
            // Obtener años disponibles de la tabla tbl_fact
            $stmt_anios_venc = $db->query("SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact ORDER BY anio DESC");
            $anios_venc = $stmt_anios_venc->fetchAll(PDO::FETCH_COLUMN);
            foreach ($anios_venc as $anio) {
                $selected = (isset($_GET['anio_vencidas']) && $_GET['anio_vencidas'] == $anio) ? 'selected' : '';
                echo "<option value='$anio' $selected>Año $anio</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-filter me-1"></i> Rango de días</label>
        <select name="dias_vencidos" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los rangos</option>
            <option value="30" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '30') ? 'selected' : '' ?>>1 - 30 días</option>
            <option value="60" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '60') ? 'selected' : '' ?>>31 - 60 días</option>
            <option value="90" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '90') ? 'selected' : '' ?>>61 - 90 días</option>
            <option value="120" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '120') ? 'selected' : '' ?>>91 - 120 días</option>
            <option value="150" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '150') ? 'selected' : '' ?>>121 - 150 días</option>
            <option value="180" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == '180') ? 'selected' : '' ?>>151 - 180 días</option>
            <option value="mas180" <?= (isset($_GET['dias_vencidos']) && $_GET['dias_vencidos'] == 'mas180') ? 'selected' : '' ?>>Más de 180 días</option>
        </select>
    </div>
    
    <div class="filter-group">
        <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
            <i class="fas fa-search me-1"></i>Filtrar
        </button>
    </div>
	
	
<?php elseif($tipo_reporte == 'plan_vs_real'): ?>
    <!-- FILTROS ESPECÍFICOS PARA PLAN VS REAL -->
    <div class="filter-group">
        <label><i class="fas fa-calendar me-1"></i> Año</label>
        <select name="anio_plan" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['anio_plan']) && $_GET['anio_plan'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los años</option>
            <?php
            // Obtener años disponibles de la tabla tbl_planes
            $stmt_anios_plan = $db->query("SELECT DISTINCT anio FROM tbl_planes ORDER BY anio DESC");
            $anios_plan = $stmt_anios_plan->fetchAll(PDO::FETCH_COLUMN);
            foreach ($anios_plan as $anio) {
                $selected = (isset($_GET['anio_plan']) && $_GET['anio_plan'] == $anio) ? 'selected' : '';
                echo "<option value='$anio' $selected>Año $anio</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-calendar-alt me-1"></i> Mes</label>
        <select name="mes_plan" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == 'TODOS') ? 'selected' : '' ?>>📅 Todos los meses</option>
            <option value="1" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '1') ? 'selected' : '' ?>>Enero</option>
            <option value="2" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '2') ? 'selected' : '' ?>>Febrero</option>
            <option value="3" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '3') ? 'selected' : '' ?>>Marzo</option>
            <option value="4" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '4') ? 'selected' : '' ?>>Abril</option>
            <option value="5" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '5') ? 'selected' : '' ?>>Mayo</option>
            <option value="6" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '6') ? 'selected' : '' ?>>Junio</option>
            <option value="7" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '7') ? 'selected' : '' ?>>Julio</option>
            <option value="8" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '8') ? 'selected' : '' ?>>Agosto</option>
            <option value="9" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '9') ? 'selected' : '' ?>>Septiembre</option>
            <option value="10" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '10') ? 'selected' : '' ?>>Octubre</option>
            <option value="11" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '11') ? 'selected' : '' ?>>Noviembre</option>
            <option value="12" <?= (isset($_GET['mes_plan']) && $_GET['mes_plan'] == '12') ? 'selected' : '' ?>>Diciembre</option>
        </select>
    </div>
    
    <div class="filter-group">
        <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
            <i class="fas fa-search me-1"></i>Filtrar
        </button>
    </div>
                
            <?php elseif($tipo_reporte == 'historico_cierres'): ?>
                <!-- FILTROS ESPECÍFICOS PARA HISTÓRICO DE CIERRES -->
                <div class="filter-group">
                    <label><i class="fas fa-filter me-1"></i> Tipo de Cierre</label>
                    <select name="filtro_tipo" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los tipos</option>
                        <option value="1" <?= (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == '1') ? 'selected' : '' ?>>Cierres Mensuales</option>
                        <option value="2" <?= (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == '2') ? 'selected' : '' ?>>Cierres Anuales</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> Año</label>
                    <select name="filtro_anio" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los años</option>
                        <?php
                        $stmt_anios_cierres = $db->query("SELECT DISTINCT periodo_anio FROM historico_cierres ORDER BY periodo_anio DESC");
                        $anios_cierres = $stmt_anios_cierres->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($anios_cierres as $anio) {
                            $selected = (isset($_GET['filtro_anio']) && $_GET['filtro_anio'] == $anio) ? 'selected' : '';
                            echo "<option value='$anio' $selected>Año $anio</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
                
            <?php elseif($tipo_reporte != 'vencimiento_contratos' && $tipo_reporte != 'auditoria'): ?>
                <!-- FILTROS DE FECHA PARA OTROS REPORTES -->
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Desde</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?=$fecha_inicio?>" max="<?=ultimoDiaMesFechaInicio(3)?>">
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-calendar-day me-1"></i> Hasta</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?=$fecha_fin?>"
                           max="<?=ultimoDiaMesFechaInicio(3)?>">
                </div>
 <?php if($tipo_reporte == 'ventas_generales'): ?>
    <div class="filter-group">
        <label><i class="fas fa-users me-1"></i> Cliente</label>
        <select name="cliente_filtro" class="form-select" onchange="this.form.submit()">
            <option value="TODOS" <?= (isset($_GET['cliente_filtro']) && $_GET['cliente_filtro'] == 'TODOS') ? 'selected' : '' ?>>👥 Todos los clientes</option>
            <?php
            // Obtener lista de clientes activos (si no está ya definida)
            if (!isset($lista_clientes)) {
                $stmt_clientes_filtro = $db->query("SELECT id, nombre FROM clasif_clientes WHERE activo = 1 ORDER BY nombre");
                $lista_clientes = $stmt_clientes_filtro->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($lista_clientes as $cliente): 
                $selected = (isset($_GET['cliente_filtro']) && $_GET['cliente_filtro'] == $cliente['id']) ? 'selected' : '';
            ?>
                <option value="<?= $cliente['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($cliente['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
                <?php if($tipo_reporte == 'diferencia_facturas'): ?>
                <div class="filter-group">
                    <label><i class="fas fa-filter me-1"></i> Tipo de Diferencia</label>
                    <select name="filtro_diferencia" class="form-select" onchange="this.form.submit()">
                        <option value="todas" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'todas') ? 'selected' : '' ?>>Todas las facturas</option>
                        <option value="con_diferencia" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'con_diferencia') ? 'selected' : '' ?>>Solo con diferencias</option>
                        <option value="sin_detalles" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'sin_detalles') ? 'selected' : '' ?>>Sin detalles</option>
                        <option value="anuladas" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'anuladas') ? 'selected' : '' ?>>Anuladas</option>
                        <option value="pendientes" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'pendientes') ? 'selected' : '' ?>>Pendientes</option>
                        <option value="ok" <?= (isset($_GET['filtro_diferencia']) && $_GET['filtro_diferencia'] == 'ok') ? 'selected' : '' ?>>Correctas (OK)</option>
                    </select>
                </div>
                <?php endif; ?>
                
                
                <input type="hidden" id="ultimo_dia_operaciones" value="<?php echo ultimoDiaMesFechaInicio(3); ?>">
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
                        <i class="fas fa-search me-1"></i>Consultar
                    </button>
                </div>


            <?php elseif($tipo_reporte == 'vencimiento_contratos'): ?>
                <!-- Filtro específico para vencimiento de contratos -->
                <div class="filter-group">
                    <label><i class="fas fa-filter me-1"></i> Estado del Contrato</label>
                    <select name="filtro_estado" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="vigentes" <?= (isset($_GET['filtro_estado']) && $_GET['filtro_estado'] == 'vigentes') ? 'selected' : '' ?>>✅ Vigentes</option>
                        <option value="por_vencer" <?= (isset($_GET['filtro_estado']) && $_GET['filtro_estado'] == 'por_vencer') ? 'selected' : '' ?>>⚠️ Por vencer</option>
                        <option value="vencidos" <?= (isset($_GET['filtro_estado']) && $_GET['filtro_estado'] == 'vencidos') ? 'selected' : '' ?>>❌ Vencidos</option>
                        <option value="indefinidos" <?= (isset($_GET['filtro_estado']) && $_GET['filtro_estado'] == 'indefinidos') ? 'selected' : '' ?>>∞ Indefinidos</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
                        <i class="fas fa-search me-1"></i>Consultar
                    </button>
                </div>
                
<?php elseif($tipo_reporte == 'auditoria'): ?>
    <!-- FILTROS ESPECÍFICOS PARA AUDITORÍA -->
    <div class="filter-group">
        <label><i class="fas fa-calendar-alt me-1"></i> Desde</label>
        <input type="date" name="fecha_inicio_aud" class="form-control" 
               value="<?php echo isset($_GET['fecha_inicio_aud']) ? $_GET['fecha_inicio_aud'] : date('Y-m-d', strtotime('-30 days')); ?>">
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-calendar-day me-1"></i> Hasta</label>
        <input type="date" name="fecha_fin_aud" class="form-control" 
               value="<?php echo isset($_GET['fecha_fin_aud']) ? $_GET['fecha_fin_aud'] : date('Y-m-d'); ?>">
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-filter me-1"></i> Tipo de Operación</label>
        <select name="operacion_aud" class="form-select">
            <option value="TODAS" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'TODAS') ? 'selected' : '' ?>>📋 Todas las operaciones</option>
            <option value="INSERT" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'INSERT') ? 'selected' : '' ?>>➕ Inserción</option>
            <option value="UPDATE" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'UPDATE') ? 'selected' : '' ?>>✏️ Actualización</option>
            <option value="DELETE" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'DELETE') ? 'selected' : '' ?>>❌ Eliminación</option>
            <option value="LOGIN" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'LOGIN') ? 'selected' : '' ?>>🔐 Inicio de sesión</option>
            <option value="LOGOUT" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'LOGOUT') ? 'selected' : '' ?>>🚪 Cierre de sesión</option>
            <option value="EXPORT" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'EXPORT') ? 'selected' : '' ?>>📤 Exportación</option>
            <option value="PRINT" <?= (isset($_GET['operacion_aud']) && $_GET['operacion_aud'] == 'PRINT') ? 'selected' : '' ?>>🖨️ Impresión</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-user me-1"></i> Usuario</label>
        <select name="usuario_aud" class="form-select">
            <option value="TODOS" <?= (isset($_GET['usuario_aud']) && $_GET['usuario_aud'] == 'TODOS') ? 'selected' : '' ?>>👥 Todos los usuarios</option>
            <?php
            // Obtener lista de usuarios que han realizado operaciones
            $stmt_usuarios_aud = $db->query("SELECT DISTINCT usuario_nombre FROM historico_operaciones WHERE usuario_nombre IS NOT NULL AND usuario_nombre != '' ORDER BY usuario_nombre");
            $usuarios_aud = $stmt_usuarios_aud->fetchAll(PDO::FETCH_COLUMN);
            foreach ($usuarios_aud as $usuario_aud_item) {
                $selected = (isset($_GET['usuario_aud']) && $_GET['usuario_aud'] == $usuario_aud_item) ? 'selected' : '';
                echo "<option value='".htmlspecialchars($usuario_aud_item)."' $selected>👤 ".htmlspecialchars($usuario_aud_item)."</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label><i class="fas fa-network-wired me-1"></i> Dirección IP</label>
        <input type="text" name="ip_aud" class="form-control" placeholder="Ej: 192.168.1.1" 
               value="<?php echo isset($_GET['ip_aud']) ? htmlspecialchars($_GET['ip_aud']) : ''; ?>">
    </div>
    
    <div class="filter-group">
        <button type="submit" class="btn btn-primary w-100" style="height: 38px; margin-top: 24px;">
            <i class="fas fa-search me-1"></i>Filtrar
        </button>
    </div>
	
	
	
            <?php endif; ?>
        </div>
    </form>
</div>
		
		
		<!-- Controles de tabla -->
        <div class="table-controls no-print">
<div class="registros-por-pagina">
    <span>Mostrar:</span>
    <select onchange="cambiarRegistrosPorPagina(this.value)">
        <option value="5" <?= $registros_por_pagina == 5 ? 'selected' : '' ?>>5</option>
		<option value="15" <?= $registros_por_pagina == 15 ? 'selected' : '' ?>>15</option>
        <option value="30" <?= $registros_por_pagina == 30 ? 'selected' : '' ?>>30</option>
        <option value="50" <?= $registros_por_pagina == 50 ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $registros_por_pagina == 100 ? 'selected' : '' ?>>100</option>
        <option value="999999" <?= $registros_por_pagina >= 999999 ? 'selected' : '' ?>>Todos los Registros</option>
    </select>
    <span>registros por página</span>
</div>
            <div class="pagination-info">
                Mostrando <?= min($inicio + 1, $total_registros) ?> a <?= min($inicio + $cantidad_registros, $total_registros) ?> de <?= $total_registros ?> registros
            </div>
        </div>

        <!-- ÁREA PARA IMPRESIÓN/EXPORTACIÓN -->
        <div id="printArea">
            <!-- ENCABEZADO PARA IMPRESIÓN -->
            <div class="print-header">
                <table style="width: 100%; border-bottom: 2px solid #000; margin-bottom: 20px;">
                    <tr>
                        <td style="width: 100px; vertical-align: top;">
                            <img src="assets/logov.png" width="80" alt="Logo" style="display: block;">
                        </td>
                        <td style="vertical-align: top; padding-left: 15px;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: bold;">PDL VISIONES</h1>
                            <p style="margin: 3px 0 0 0; font-size: 14px; color: #666;">Sistema Integral de Facturación - SISFACT</p>
                            <h2 style="margin: 15px 0 5px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;">
                                <?= $titulo_reporte ?>
                            </h2>
                            <p style="margin: 0; font-size: 12px; color: #666;"><?= $descripcion_reporte ?></p>
                        </td>
                        <td style="vertical-align: top; text-align: right; font-size: 11px; color: #666;">
                            <p style="margin: 0;"><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
                            <p style="margin: 3px 0;"><strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario') ?></p>
                            <?php if($tipo_reporte != 'vencimiento_contratos' && $tipo_reporte != 'auditoria'): ?>
                            <p style="margin: 3px 0;"><strong>Período:</strong> <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
                            <?php endif; ?>
                            <p style="margin: 0;"><strong>Registros:</strong> <?= $total_registros ?></p>
                            <?php if($mostrar_totales): ?>
                            <p style="margin: 3px 0 0 0;"><strong>Total General:</strong> $<?= number_format($total_general, 2) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TABLA DEL REPORTE -->
            <div class="report-container">
                <table class="report-table" id="tablaReporte">
                    <thead>
                        <tr>
                            <?php foreach($headers as $index => $h): 
                                $col_index = $index + 1;
                                $alineacion = getAlineacionColumna($tipo_reporte, $col_index, $columna_dinero, $columnas_numericas);
                                $sorted_class = '';
                                $sort_icon = '';
                                if ($orden_columna == $col_index) {
                                    $sorted_class = $orden_direccion == 'asc' ? 'sorted-asc' : 'sorted-desc';
                                    $sort_icon = $orden_direccion == 'asc' ? '↑' : '↓';
                                }
                            ?>
                                <th class="<?= $alineacion . ' ' . $sorted_class ?>" onclick="ordenarColumna(<?= $col_index ?>)">
                                    <?= $h ?>
                                    <span class="sort-icon"><?= $sort_icon ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
<tbody>
    <?php 
    // --- RENDERIZADO ESPECÍFICO PARA RANGO CORRELATIVOS ---
    if ($tipo_reporte === 'rango_correlativos'):
        // USAR LA VARIABLE GLOBAL DIRECTAMENTE
        $datos_a_mostrar = isset($GLOBALS['data_correlativos']) ? $GLOBALS['data_correlativos'] : [];

        if (count($datos_a_mostrar) > 0):
            $anio_actual_loop = null;
            $facturas_anio = 0;
            $importe_anio = 0; // NUEVO: Acumulador de importe por año
            $primer_factura_anio = null;
            $ultima_factura_anio = null;

            foreach($datos_a_mostrar as $index => $row):
                // Si cambia el año y no es el primero
                if ($anio_actual_loop !== null && $anio_actual_loop !== $row['anio']):
                    // Mostrar total del año anterior (AHORA CON 5 COLUMNAS)
                    ?>
                    <tr style="background: var(--win-bg-tertiary); font-weight: bold; border-top: 2px solid var(--win-accent);">
                        <td class="text-right fw-bold" colspan="1">
                            <i class="fas fa-chart-pie me-2"></i> TOTAL AÑO <?php echo $anio_actual_loop; ?>:
                        </td>
                        <td class="text-center fw-bold">
                            <span class="badge bg-primary text-white p-2">
                                <?php echo number_format($facturas_anio, 0); ?> facturas
                            </span>
                        </td>
                        <td class="text-center fw-bold" colspan="2">
                            <span style="color: var(--win-text-secondary);">Rango:</span>
                            <code style='background: var(--win-bg-secondary); padding: 4px 8px; border-radius: 4px; display: inline-block;'>
                                <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($primer_factura_anio)); ?>" target="_blank"
                                   style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    <?php echo htmlspecialchars($primer_factura_anio); ?>
                                </a>
                                -
                                <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($ultima_factura_anio)); ?>" target="_blank"
                                   style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    <?php echo htmlspecialchars($ultima_factura_anio); ?>
                                </a>
                            </code>
                        </td>
                        <td class="text-right fw-bold text-dinero">
                            $<?php echo number_format($importe_anio, 2); ?> <!-- NUEVO: Mostrar importe total del año -->
                        </td>
                    </tr>
                    <tr><td colspan="5" style="padding: 5px;"></td></tr> <!-- Espaciador (AHORA COLSPAN=5) -->
                    <?php
                    // Reiniciar contadores para el nuevo año
                    $facturas_anio = 0;
                    $importe_anio = 0; // Reiniciar importe
                    $primer_factura_anio = null;
                    $ultima_factura_anio = null;
                endif;

                // Si es el primer registro del año o cambió el año
                if ($anio_actual_loop !== $row['anio']):
                    $anio_actual_loop = $row['anio'];
                    ?>
                    <tr style="background: var(--win-accent-light); font-weight: bold;">
                        <td colspan="5" style="color: var(--win-accent); font-size: 1.1rem; padding: 10px 15px;"> <!-- COLSPAN=5 -->
                            <i class="fas fa-calendar-alt me-2"></i> Año <?php echo htmlspecialchars($anio_actual_loop); ?>
                        </td>
                    </tr>
                    <?php
                    // Establecer primera factura del año
                    $primer_factura_anio = $row['col3'];
                endif;

                // Acumular facturas e importe del año actual
                $facturas_anio += intval($row['col2']);
                $importe_anio += floatval($row['col5']); // NUEVO: Acumular importe
                $ultima_factura_anio = $row['col4']; // Siempre se actualiza con la última del año
                ?>

                <!-- FILA DE DATOS (AHORA CON 5 COLUMNAS) -->
                <tr>
                    <td class="text-left"><?php echo htmlspecialchars($row['col1'] ?? ''); ?></td> <!-- Mes -->
                    <td class="text-center">
                        <span class="badge bg-warning text-dark p-2"><?php echo number_format($row['col2'] ?? 0, 0); ?> facturas</span>
                    </td> <!-- Facturas Procesadas -->
                    <td class="text-center">
                        <code class="text-light" style="background: var(--win-bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                            <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($row['col3'] ?? '')); ?>" target="_blank"
                               style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($row['col3'] ?? ''); ?>
                            </a>
                        </code>
                    </td> <!-- Desde Fact. No -->
                    <td class="text-center">
                        <code class="text-light" style="background: var(--win-bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                            <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($row['col4'] ?? '')); ?>" target="_blank"
                               style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($row['col4'] ?? ''); ?>
                            </a>
                        </code>
                    </td> <!-- Hasta Fact. No -->
                    <td class="text-right text-dinero"> <!-- NUEVA CELDA PARA IMPORTE -->
                        $<?php echo number_format($row['col5'] ?? 0, 2); ?>
                    </td>
                </tr>
            <?php
            endforeach;

            // Mostrar total del último año (AHORA CON 5 COLUMNAS)
            if ($anio_actual_loop !== null):
            ?>
                <tr style="background: var(--win-bg-tertiary); font-weight: bold; border-top: 2px solid var(--win-accent);">
                    <td class="text-right fw-bold" colspan="1">
                        <i class="fas fa-chart-pie me-2"></i> TOTAL AÑO <?php echo $anio_actual_loop; ?>:
                    </td>
                    <td class="text-center fw-bold">
                        <span class="badge bg-primary text-white p-2">
                            <?php echo number_format($facturas_anio, 0); ?> facturas
                        </span>
                    </td>
                    <td class="text-center fw-bold" colspan="2">
                        <span style="color: var(--win-text-secondary);">Rango:</span>
                        <code style='background: var(--win-bg-secondary); padding: 4px 8px; border-radius: 4px; display: inline-block;'>
                            <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($primer_factura_anio)); ?>" target="_blank"
                               style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($primer_factura_anio); ?>
                            </a>
                            -
                            <a href="ver_factura.php?no_fact=<?php echo urlencode(htmlspecialchars($ultima_factura_anio)); ?>" target="_blank"
                               style="text-decoration: none; font-weight: bold; color: var(--win-accent);"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($ultima_factura_anio); ?>
                            </a>
                        </code>
                    </td>
                    <td class="text-right fw-bold text-dinero">
                        $<?php echo number_format($importe_anio, 2); ?> <!-- NUEVO: Mostrar importe total del año -->
                    </td>
                </tr>
            <?php endif; ?>
        <?php
        else:
            ?>
            <tr>
                <td colspan="5" class="text-center py-5"> <!-- COLSPAN=5 -->
                    <i class="fas fa-database text-muted fa-3x mb-3 d-block"></i>
                    <span class="text-muted">No hay facturas para el año <?php echo $anio_filtro ?? date('Y'); ?></span>
                </td>
            </tr>
        <?php
        endif;    
    // --- PARA EL RESTO DE REPORTES - CÓDIGO GENERICO COMPLETO ---
    else:
        // Inicializar sumas solo para reportes que no son rango_correlativos
        $sumas = [];
        foreach ($columnas_numericas as $col_index) {
            $sumas[$col_index] = 0;
        }
        $anio_actual_loop = null;
        
        if (count($data) > 0): 
            foreach($data as $row): 
                ?>
                <tr>
                <?php for($col = 1; $col <= count($headers); $col++): 
                    $valor = $row['col' . $col] ?? '';
                    $es_numerico = in_array($col, $columnas_numericas);
                    
                    if ($es_numerico && is_numeric($valor)) {
                        $sumas[$col] += $valor;
                    }
                    
                    $alineacion = getAlineacionColumna($tipo_reporte, $col, $columna_dinero, $columnas_numericas);
                    $clase_adicional = '';
                    $es_dinero = false;

                    // Verificar si es columna de dinero
                    if (is_array($columna_dinero)) {
                        $es_dinero = in_array($col, $columna_dinero);
                    } else {
                        $es_dinero = ($col === $columna_dinero);
                    }
                ?>

                <td class="<?= $alineacion . ' ' . $clase_adicional ?>">
                    <?php
                    // 1. ESPECÍFICO PARA DIFERENCIA_FACTURAS - TIENE PRIORIDAD
                    if ($tipo_reporte === 'diferencia_facturas') {
                        if ($col === 1) {
                            // No. Factura con enlace
                            $no_fact = htmlspecialchars($valor);
                            echo '<a href="ver_factura.php?no_fact=' . urlencode($valor) . '" target="_blank" style="text-decoration: none; font-weight: bold; color: var(--win-accent); hover: underline;">' . $no_fact . '</a>';
                        }
                        elseif ($col === 2) {
                            // Cliente - solo mostrar texto
                            echo htmlspecialchars($valor);
                        }
                        elseif ($col === 3) {
                            // Total Factura - mostrar como dinero
                            echo '$' . number_format(floatval($valor), 2);
                        }
                        elseif ($col === 4) {
                            // Total Detalles - mostrar como dinero
                            echo '$' . number_format(floatval($valor), 2);
                        }
                        elseif ($col === 5) {
                            // Diferencia en rojo
                            $diferencia = floatval($valor);
                            $color = $diferencia != 0 ? 'color: #dc3545; font-weight: bold;' : 'color: #28a745;';
                            echo '<span style="' . $color . '">$' . number_format($diferencia, 2) . '</span>';
                        }
                        elseif ($col === 6) {
                            // Estado con badges
                            $estado = strtoupper($valor);
                            $class = '';
                            if ($estado == 'SIN DETALLES') {
                                $class = 'badge-reporte-danger';
                            } elseif ($estado == 'DIFERENCIA') {
                                $class = 'badge-reporte-warning';
                            } elseif ($estado == 'OK') {
                                $class = 'badge-reporte-success';
                            } else {
                                $class = 'badge-reporte-secondary';
                            }
                            echo "<span class='badge-reporte $class'>$estado</span>";
                        }
                    }
                    
                    // 2. PARA OTROS REPORTES ESPECÍFICOS
                    elseif ($tipo_reporte === 'ventas_generales' && $col === 1) {
                        // No. Factura con enlace para ventas_generales
                        echo '<a href="ver_factura.php?no_fact=' . urlencode($valor) . '" target="_blank" style="text-decoration: none; font-weight: bold; color: var(--win-accent); hover: underline;">' . htmlspecialchars($valor) . '</a>';
                    }
                    
                    elseif ($tipo_reporte === 'facturas_vencidas' && $col === 1) {
                        // No. Factura con enlace para facturas_vencidas
                        echo '<a href="ver_factura.php?no_fact=' . urlencode($valor) . '" target="_blank" style="text-decoration: none; font-weight: bold; color: var(--win-accent); hover: underline;">' . htmlspecialchars($valor) . '</a>';
                    }
                    
                    elseif ($tipo_reporte === 'aging_por_cliente' && $col === 1) {
                        // No. Factura con enlace para aging_por_cliente
                        echo '<a href="ver_factura.php?no_fact=' . urlencode($valor) . '" target="_blank" style="text-decoration: none; font-weight: bold; color: var(--win-accent); hover: underline;">' . htmlspecialchars($valor) . '</a>';
                    }
                    
                    // 3. Aging por cliente - valores monetarios
                    elseif ($tipo_reporte === 'aging_por_cliente' && $col >= 4) {
                        if (floatval($valor) > 0) {
                            echo '<strong>$' . number_format($valor, 2) . '</strong>';
                        } else {
                            echo '<span style="color: #ccc;">--</span>';
                        }
                    } 

                    // 4. Facturas vencidas - días con colores
                    elseif ($tipo_reporte === 'facturas_vencidas' && $col == 4) {
                        $dias = intval($valor);
                        $style_base = "display: inline-block; padding: 4px 10px; border-radius: 12px; color: white; font-weight: bold; font-size: 0.85em; min-width: 90px; text-align: center;";
                        
                        if ($dias <= 30) $bg = "linear-gradient(135deg, #4CAF50, #2E7D32)";
                        elseif ($dias <= 60) $bg = "linear-gradient(135deg, #8BC34A, #689F38)";
                        elseif ($dias <= 90) $bg = "linear-gradient(135deg, #FFC107, #FFA000); color: black;";
                        elseif ($dias <= 120) $bg = "linear-gradient(135deg, #FF9800, #F57C00)";
                        elseif ($dias <= 150) $bg = "linear-gradient(135deg, #FF5722, #E64A19)";
                        elseif ($dias <= 180) $bg = "linear-gradient(135deg, #F44336, #D32F2F)";
                        else $bg = "linear-gradient(135deg, #9C27B0, #6A1B9A)";
                        
                        echo '<span style="'.$style_base.' background: '.$bg.'">'.$valor.' días</span>';
                    }

                    // 5. Badges para estados en otros reportes
                    elseif (($tipo_reporte === 'ventas_generales' || $tipo_reporte === 'estado_facturas' || $tipo_reporte === 'facturas_vencidas') && 
                           (($col === 4 && $tipo_reporte != 'facturas_vencidas' && $tipo_reporte != 'aging_por_cliente') || 
                            ($col === 6 && $tipo_reporte == 'facturas_vencidas') || 
                            ($tipo_reporte === 'estado_facturas' && $col === 1))) {
                        
                        if (isset($_GET['export']) || isset($_GET['print'])) {
                            echo $valor;
                        } else {
 $class = ($valor == 'PAGADA') ? 'badge-reporte-info' : 
                 ($valor == 'CONTABILIZADA' ? 'badge-reporte-success' : 
                 ($valor == 'PENDIENTE' ? 'badge-reporte-warning' : 
                 ($valor == 'ANULADA' ? 'badge-reporte-danger' : 
                 ($valor == 'CERRADA/PAGADA' ? 'badge-reporte-cerrpag' : 'badge-reporte-secondary'))));
                            echo "<span class='badge-reporte $class'>$valor</span>";
                        }
                    }

                    // 6. Vencimiento contratos - días
                    elseif ($tipo_reporte === 'vencimiento_contratos' && $col === 10) {
                        if (strpos($valor, 'Vencido') !== false) {
                            echo "<span class='badge-reporte badge-reporte-danger'>$valor</span>";
                        } elseif (strpos($valor, 'Por vencer') !== false) {
                            echo "<span class='badge-reporte badge-reporte-warning'>$valor</span>";
                        } elseif (strpos($valor, 'Vigente') !== false) {
                            echo "<span class='badge-reporte badge-reporte-success'>$valor</span>";
                        } else {
                            echo htmlspecialchars($valor);
                        }
                    }

                    // 7. Vencimiento contratos - vigencia
                    elseif ($tipo_reporte === 'vencimiento_contratos' && $col === 5) {
                        if ($valor == 0) {
                            echo "<span class='badge-reporte badge-reporte-info'>Indefinido</span>";
                        } else {
                            echo "<span class='badge-reporte badge-reporte-primary'>$valor año" . ($valor > 1 ? 's' : '') . "</span>";
                        }
                    }
                    elseif ($tipo_reporte == 'plan_vs_real' && $col == 4) {
                        // Columna de porcentaje (Cumplimiento %)
                        $porcentaje = floatval($valor);
                        $color = $porcentaje >= 100 ? '#28a745' : ($porcentaje >= 80 ? '#ffc107' : '#dc3545');
                        echo '<span style="color: ' . $color . '; font-weight: bold;">' . number_format($porcentaje, 2) . '%</span>';
                    }
                    elseif ($tipo_reporte == 'plan_vs_real' && ($col == 2 || $col == 3)) {
                        // Columnas de dinero (2: Meta Plan, 3: Venta Real)
                        echo '$' . number_format(floatval($valor), 2);
                    }
                    // 8. VALORES NUMÉRICOS GENERALES
                    elseif (is_numeric($valor) && $valor != 0) {
                        // Verificar si es columna de dinero
                        $es_columna_dinero = false;
                        if (is_array($columna_dinero)) {
                            $es_columna_dinero = in_array($col, $columna_dinero);
                        } else {
                            $es_columna_dinero = ($col === $columna_dinero);
                        }
                        
                        // SI ES COLUMNA DE DINERO
                        if ($es_columna_dinero) {
                            echo '$' . number_format($valor, 2);
                        } 
                        // SI ES COLUMNA NUMÉRICA PERO NO ES DINERO
                        elseif (in_array($col, $columnas_numericas)) {
                            // Para ranking_clientes, columna 4 es cantidad de facturas (entero)
                            if ($tipo_reporte === 'ranking_clientes' && $col === 4) {
                                echo number_format($valor, 0);
                            } 
                            // Para otros casos, decidir si mostrar decimales
                            else {
                                echo (floor($valor) == $valor) ? number_format($valor, 0) : number_format($valor, 2);
                            }
                        }
                        // SI ES NUMÉRICO PERO NO ESTÁ EN LAS COLUMNAS NUMÉRICAS
                        else {
                            echo $valor;
                        }
                    }

					// 9. VALORES NO NUMÉRICOS GENERALES (FALLBACK)
					else {
						// Para el reporte de hojas_utilizadas, NO escapar el HTML porque ya viene con formato
						if ($tipo_reporte === 'hojas_utilizadas') {
							echo $valor; // El valor ya contiene las etiquetas <strong> y formato
						} else {
							echo htmlspecialchars($valor);
						}
					}
                    ?>
                </td>
                <?php endfor; ?>
                </tr>
            <?php 
            endforeach; 
        else: ?>
            <tr>
                <td colspan="<?php echo count($headers); ?>" class="text-center py-4">
                    <i class="fas fa-database text-muted fa-2x mb-2 d-block"></i>
                    <span class="text-muted">No hay datos para mostrar</span>
                </td>
            </tr>
        <?php endif; ?>
    <?php endif; ?>
</tbody>

<?php if($tipo_reporte === 'rango_correlativos' && count($GLOBALS['data_correlativos'] ?? []) > 0): ?>
<tfoot class="tfoot-total" style="background: var(--win-accent); color: white; border-top: 3px solid var(--win-border-color);">
    <tr>
        <td class="text-right fw-bold" style="padding: 12px; font-size: 1.1rem;">
            TOTAL GENERAL
        </td>
        <td class="text-center fw-bold" style="padding: 12px; font-size: 1.1rem;">
            <span class="badge bg-light text-dark p-2" style="font-size: 1rem;">
                <?php echo number_format($GLOBALS['total_facturas_correlativos'] ?? 0, 0); ?> facturas
            </span>
        </td>
        <td class="text-center fw-bold" colspan="2">
            <!-- Espacio vacío para las columnas de rango -->
        </td>
        <td class="text-right fw-bold text-dinero" style="padding: 12px; font-size: 1.1rem;">
            $<?php echo number_format($GLOBALS['total_importe_correlativos'] ?? 0, 2); ?>
        </td>
    </tr>
</tfoot>
<?php endif; ?>

<?php if($mostrar_totales && count($data) > 0 && $tipo_reporte !== 'rango_correlativos'): ?>
                <tfoot class="tfoot-total">
                    <tr>
                        <?php 
                        // Calcular totales específicos para cada tipo de reporte basado en $data
                        $total_pagina_facturas = $total_pagina_items = $total_pagina_subtotal = $total_pagina_monto = 0;
                        $total_plan_pagina = $total_real_pagina = 0;
                        $total_facturas_pagina = $total_monto_pagina = 0;
                        $total_operaciones_pagina = 0;
                        $total_cantidad_pagina = 0;
                        $total_monto_vencidas = 0;
                        
                        $total_pagina_detalles = 0;
                        $total_pagina_diferencia = 0;
                        
                        if(count($data) > 0) {
                            foreach($data as $row) {
                                switch($tipo_reporte) {
                                    case 'diferencia_facturas':
                                        $total_pagina_facturas += floatval($row['col3'] ?? 0);    // Total Factura
                                        $total_pagina_detalles += floatval($row['col4'] ?? 0);    // Total Detalles
                                        $total_pagina_diferencia += floatval($row['col5'] ?? 0);  // Diferencia
                                        break;
									case 'ventas_por_categoria':
										$total_pagina_facturas += intval($row['col2'] ?? 0);  // Usar intval() en lugar de floatval()
										$total_pagina_items += intval($row['col3'] ?? 0);     // Usar intval() en lugar de floatval()
										$total_pagina_subtotal += floatval($row['col4'] ?? 0);
										$total_pagina_monto += floatval($row['col5'] ?? 0);
										break;
                                    
                                    case 'plan_vs_real':
                                        $total_plan_pagina += floatval($row['col2'] ?? 0);
                                        $total_real_pagina += floatval($row['col3'] ?? 0);
                                        break;
                                    
                                    case 'estado_facturas':
                                        $total_facturas_pagina += floatval($row['col2'] ?? 0);
                                        $total_monto_pagina += floatval($row['col3'] ?? 0);
                                        break;
                                    
                                    case 'metodos_pago':
                                        $total_operaciones_pagina += floatval($row['col2'] ?? 0);
                                        $total_monto_pagina += floatval($row['col3'] ?? 0);
                                        break;
                                    
                                    case 'ventas_servicios':
                                        $total_cantidad_pagina += floatval($row['col3'] ?? 0);
                                        $total_monto_pagina += floatval($row['col4'] ?? 0);
                                        break;
                                    
                                    case 'ranking_clientes':
                                        $total_facturas_pagina += floatval($row['col3'] ?? 0);
                                        $total_monto_pagina += floatval($row['col4'] ?? 0);
                                        break;
                                    
                                    case 'deudores':
                                        $total_facturas_pagina += floatval($row['col2'] ?? 0);
                                        $total_monto_pagina += floatval($row['col3'] ?? 0);
                                        break;
                                    
                                    case 'productividad_usuarios':
                                        $total_facturas_pagina += floatval($row['col4'] ?? 0);
                                        $total_monto_pagina += floatval($row['col5'] ?? 0);
                                        break;
                                    
                                    case 'facturas_vencidas':
                                        $total_monto_vencidas += floatval($row['col5'] ?? 0);
                                        break;
                                }
                            }
                        }
                        ?>
                        
                        <?php if($tipo_reporte == 'aging_por_cliente'): ?>
                            <!-- AGING POR CLIENTE -->
                            <td colspan="3" class="text-right fw-bold">TOTALES POR RANGO (Página):</td>
                            <?php for($col = 4; $col <= 10; $col++): ?>
                                <td class="fw-bold text-right text-dinero">
                                    <?php 
                                    $valor = $aging_totals[$col] ?? 0;
                                    if($valor > 0) {
                                        echo '$' . number_format($valor, 2);
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                        
<?php elseif($tipo_reporte == 'ventas_por_categoria'): ?>
    <!-- VENTAS POR CATEGORÍA -->
    <td colspan="1" class="text-right fw-bold">TOTALES (Página):</td>
    <td class="fw-bold text-center"><?php echo number_format($total_pagina_facturas, 0); ?></td>  <!-- Sin decimales -->
    <td class="fw-bold text-center"><?php echo number_format($total_pagina_items, 0); ?></td>     <!-- Sin decimales -->
    <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_subtotal, 2); ?></td>
    <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_monto, 2); ?></td>

                        <?php elseif($tipo_reporte == 'diferencia_facturas'): ?>
                            <!-- DIFERENCIAS FACTURAS -->
                            <td colspan="2" class="text-right fw-bold">TOTALES (Página):</td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_facturas, 2); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_detalles, 2); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_diferencia, 2); ?></td>
                            <td class="fw-bold text-center"><?php echo count($data); ?> registros</td>

                        <?php elseif($tipo_reporte == 'estado_facturas'): ?>
                            <!-- ESTADO FACTURAS -->
                            <?php 
                            $porcentaje_pagina = $total_facturas_pagina > 0 ? ($total_facturas_pagina / $total_facturas_pagina) * 100 : 0;
                            ?>
                            <td colspan="1" class="text-right fw-bold">TOTALES (Página):</td>
                            <td class="fw-bold text-center"><?php echo number_format($total_facturas_pagina, 0); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_monto_pagina, 2); ?></td>
                            <td class="fw-bold text-center"><?php echo number_format($porcentaje_pagina, 2) . '%'; ?></td>
                        
                        <?php elseif($tipo_reporte == 'metodos_pago'): ?>
                            <!-- MÉTODOS DE PAGO -->
                            <?php 
                            $porcentaje_pagina = $total_monto_pagina > 0 ? ($total_monto_pagina / $total_monto_pagina) * 100 : 0;
                            ?>
                            <td colspan="1" class="text-right fw-bold">TOTALES (Página):</td>
                            <td class="fw-bold text-center"><?php echo number_format($total_operaciones_pagina, 0); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($total_monto_pagina, 2); ?></td>
                            <td class="fw-bold text-center"><?php echo number_format($porcentaje_pagina, 2) . '%'; ?></td>
                        
                        <?php elseif($tipo_reporte == 'historico_cierres' && isset($totales_cierres)): ?>
                            <!-- HISTÓRICO DE CIERRES -->
                            <?php 
                            $totales_cierres_data = $totales_cierres;
                            ?>
                            <td colspan="4" class="text-right fw-bold">TOTALES GLOBALES:</td>
                            <td class="fw-bold text-center"><?php echo number_format($totales_cierres_data['total_facturas'], 0); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($totales_cierres_data['total_importe'], 2); ?></td>
                            <td class="fw-bold text-center"><?php echo number_format($totales_cierres_data['total_pagadas'], 0); ?></td>
                            <td class="fw-bold text-center"><?php echo number_format($totales_cierres_data['total_contabilizadas'], 0); ?></td>
                            <td class="fw-bold text-center"><?php echo $totales_cierres_data['total_registros']; ?> registros</td>
                        
                        <?php elseif(in_array($tipo_reporte, ['ventas_servicios', 'ranking_clientes', 'deudores', 'productividad_usuarios'])): ?>
                            <!-- REPORTES CON UNA COLUMNA DE DINERO Y UNA DE CANTIDAD -->
                            <?php 
                            $colspan = count($headers) - 2;
                            
                            $cantidad = 0;
                            $monto = 0;
                            
                            if(count($data) > 0) {
                                foreach($data as $row) {
                                    switch($tipo_reporte) {
                                        case 'ventas_servicios':
                                            $cantidad += floatval($row['col3'] ?? 0);
                                            $monto += floatval($row['col4'] ?? 0);
                                            break;
                                        
                                        case 'ranking_clientes':
                                            $cantidad += floatval($row['col4'] ?? 0);
                                            $monto += floatval($row['col5'] ?? 0);
                                            break;
                                        
                                        case 'deudores':
                                            $cantidad += floatval($row['col2'] ?? 0);
                                            $monto += floatval($row['col3'] ?? 0);
                                            break;
                                        
                                        case 'productividad_usuarios':
                                            $cantidad += floatval($row['col4'] ?? 0);
                                            $monto += floatval($row['col5'] ?? 0);
                                            break;
                                    }
                                }
                            }
                            ?>
                            
                            <td colspan="<?php echo $colspan; ?>" class="text-right fw-bold">TOTALES (Página):</td>
                            <td class="fw-bold text-center"><?php echo number_format($cantidad, 0); ?></td>
                            <td class="fw-bold text-right text-dinero">$<?php echo number_format($monto, 2); ?></td>
                        
                        
                        <?php elseif($tipo_reporte == 'ventas_generales'): ?>
							<!-- VENTAS GENERALES: TOTAL PRODUCTOS Y DINERO -->
							<?php 
							$total_pagina_dinero = 0;
							$total_pagina_productos = 0;
							foreach($data as $row) {
								if ($row['col4'] !== 'ANULADA') {
									$total_pagina_productos += intval($row['col5'] ?? 0);
									$total_pagina_dinero += floatval($row['col6'] ?? 0);
								}
							}
							?>
							<td colspan="4" class="text-right fw-bold">TOTALES (Esta página):</td>
							<td class="fw-bold text-center"><?php echo number_format($total_pagina_productos, 0); ?></td>
							<td class="fw-bold text-right text-dinero">$<?php echo number_format($total_pagina_dinero, 2); ?></td>
						<?php else: ?>
                            <!-- OTROS REPORTES -->
                            <td colspan="<?php echo count($headers); ?>" class="text-center fw-bold">
                                <?php echo count($data); ?> registros en esta página
                            </td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

            <!-- PIE DE PÁGINA PARA IMPRESIÓN -->
            <div class="print-footer">
                <table style="width: 100%;">
                    <tr>
                        <td style="vertical-align: top;">
                            <p style="margin: 0; font-weight: bold;">PDL VISIONES - SISFACT</p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Sistema Integral de Facturación y Control</p>
                        </td>
                        <td style="text-align: center; vertical-align: top;">
                            <p style="margin: 0; font-style: italic; font-size: 10px;"> <i class="fas fa-eye" style="transform: rotate(15deg); margin-right: 10px;"></i> 
                                            PDL Visiones 
                                            <i class="fas fa-eye" style="transform: rotate(-15deg); margin-left: 10px;"></i></p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">www.pdlvisiones.com, "Donde tu visión toma forma"</p>
                        </td>
                        <td style="text-align: right; vertical-align: top;">
                            <p style="margin: 0; font-size: 10px;">Página <span class="page-number">1</span> de <span class="total-pages">1</span></p>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Total Registros: <?= $total_registros ?></p>
                            <?php if($mostrar_totales): ?>
                            <p style="margin: 3px 0 0 0; font-size: 10px;">Total General: $<?= number_format($total_general, 2) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1  && $tipo_reporte !== 'hojas_utilizadas' || $tipo_reporte !== 'estado_facturas'): ?>
		
        <div class="pagination-container no-print">
            <nav aria-label="Paginación">
                <ul class="pagination">
                    <!-- Primera página -->
                    <li class="page-item <?= $pagina_actual == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getUrlWithParams(['pagina' => 1]) ?>" aria-label="Primera">
                            <span aria-hidden="true">&laquo;&laquo;</span>
                        </a>
                    </li>
                    
                    <!-- Página anterior -->
                    <li class="page-item <?= $pagina_actual == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getUrlWithParams(['pagina' => $pagina_actual - 1]) ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <!-- Mostrar números de página -->
                    <?php
                    // Calcular rango de páginas a mostrar
                    $paginas_a_mostrar = 5;
                    $inicio_paginas = max(1, $pagina_actual - floor($paginas_a_mostrar/2));
                    $fin_paginas = min($total_paginas, $inicio_paginas + $paginas_a_mostrar - 1);
                    
                    // Ajustar si estamos cerca del inicio
                    if ($fin_paginas - $inicio_paginas + 1 < $paginas_a_mostrar) {
                        $inicio_paginas = max(1, $fin_paginas - $paginas_a_mostrar + 1);
                    }
                    
                    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++):
                    ?>
                    <li class="page-item <?= $i == $pagina_actual ? 'active' : '' ?>">
                        <a class="page-link" href="<?= getUrlWithParams(['pagina' => $i]) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <!-- Página siguiente -->
                    <li class="page-item <?= $pagina_actual == $total_paginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getUrlWithParams(['pagina' => $pagina_actual + 1]) ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    
                    <!-- Última página -->
                    <li class="page-item <?= $pagina_actual == $total_paginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getUrlWithParams(['pagina' => $total_paginas]) ?>" aria-label="Última">
                            <span aria-hidden="true">&raquo;&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Mostrar información de la página -->
            <div class="pagination-info ms-3">
                Mostrando página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> registros totales)
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // --- FUNCIONES DE UI ---
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
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('show');
        }
        
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('exportMenu');
            const btn = document.querySelector('.export-combo-btn');
            if (!btn.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.remove('show');
            }
        });
        
        function seleccionarReporte(tipo) {
            window.location.href = '<?= getUrlWithParams() ?>'.replace(/tipo_reporte=[^&]*/, 'tipo_reporte=' + tipo);
        }
        
        
        function ordenarColumna(columna) {
            const urlParams = new URLSearchParams(window.location.search);
            let direccion = 'asc';
            
            if (urlParams.get('orden_columna') == columna) {
                direccion = urlParams.get('orden_direccion') === 'asc' ? 'desc' : 'asc';
            }
            
            urlParams.set('orden_columna', columna);
            urlParams.set('orden_direccion', direccion);
            urlParams.set('pagina', 1); // Volver a primera página al ordenar
            
            window.location.href = '?' + urlParams.toString();
        }
        
		function cambiarRegistrosPorPagina(valor) {
			const urlParams = new URLSearchParams(window.location.search);
			
			// Si selecciona "Todos los Registros", usar un número muy grande
			if (valor === "999999") {
				urlParams.set('registros_por_pagina', '999999');
			} else {
				urlParams.set('registros_por_pagina', valor);
			}
			
			urlParams.set('pagina', 1); // Volver a primera página
			
			window.location.href = '?' + urlParams.toString();
		}



        // --- FUNCIONES DE EXPORTACIÓN ---
// Datos originales de PHP para exportación
const REPORT_DATA = {
    tipo_reporte: '<?php echo $tipo_reporte; ?>',
    titulo: '<?php echo addslashes($titulo_reporte); ?>',
    descripcion: '<?php echo addslashes($descripcion_reporte); ?>',
    headers: <?php echo json_encode($headers); ?>,
    columnas_numericas: <?php echo json_encode($columnas_numericas); ?>,
    columna_dinero: <?php echo is_array($columna_dinero) ? json_encode($columna_dinero) : ($columna_dinero ?: 'null'); ?>,
    mostrar_totales: <?php echo $mostrar_totales ? 'true' : 'false'; ?>,
    total_general: <?php echo $total_general; ?>,
    total_registros: <?php echo $total_registros; ?>,
    fecha_inicio: '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>',
    fecha_fin: '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>',
    usuario: '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>',
    datos: <?php echo json_encode($data); ?>,
    aging_totals: <?php echo isset($aging_totals) ? json_encode($aging_totals) : 'null'; ?>
};


// 1. EXPORTAR A EXCEL (CON FORMATO DE MONEDA NATIVO)
function exportarExcel() {
    try {
        // --- VARIABLES PHP ---
        const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
        const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
        const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
        const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
        const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
        const TOTAL_REGISTROS = <?php echo $total_registros; ?>;
        const TOTAL_GENERAL = <?php echo $total_general; ?>;
        const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
        const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
        
        // --- FUNCIÓN PARA CONVERTIR TEXTO A NÚMERO ---
        const parsearNumero = (str) => {
            if (!str) return '';
            // Limpiar todo lo que no sea número, punto, coma o signo menos
            let limpio = str.toString().replace(/[^\d.,-]/g, '');
            
            // Lógica para detectar formato (miles vs decimales)
            if (limpio.indexOf(',') > -1 && limpio.indexOf('.') > -1) {
                 if (limpio.indexOf(',') < limpio.indexOf('.')) {
                     limpio = limpio.replace(/,/g, ''); // 1,234.56 -> 1234.56
                 } else {
                     limpio = limpio.replace(/\./g, '').replace(',', '.'); // 1.234,56 -> 1234.56
                 }
            } 
            else if (limpio.indexOf(',') > -1 && limpio.indexOf('.') === -1) {
                // Asumimos que si solo hay coma es separador de miles si son 3 digitos, o decimal
                // Para seguridad en este contexto, quitamos comas (miles)
                limpio = limpio.replace(/,/g, ''); 
            }
            
            let numero = parseFloat(limpio);
            return isNaN(numero) ? str : numero; 
        };

        const tabla = document.getElementById('tablaReporte');
        
        // --- 1. DETECTAR COLUMNAS DE DINERO ---
        // Identificamos qué índices de columna (0, 1, 2...) contienen dinero
        // basándonos en la primera fila de datos o en los encabezados.
        const columnasDinero = new Set();
        const primeraFila = tabla.querySelector('tbody tr');
        
        if (primeraFila) {
            primeraFila.querySelectorAll('td').forEach((td, index) => {
                const texto = td.textContent.trim();
                // Si tiene clase 'text-dinero', 'text-right' o el símbolo $, es columna de moneda
                if (td.classList.contains('text-dinero') || 
                    td.classList.contains('text-right') || 
                    texto.includes('$')) {
                    columnasDinero.add(index);
                }
            });
        }

        // --- 2. EXTRAER DATOS ---
        
        // Headers
        const headers = Array.from(tabla.querySelectorAll('thead th')).map(th => 
            th.textContent.trim().replace(/[↑↓]/g, '')
        );
        
        // Rows (Filas)
        const rows = Array.from(tabla.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map((td, index) => {
                let text = td.innerText.trim(); // innerText maneja mejor los espacios que textContent
                
                // Si la columna fue detectada como dinero, forzamos parseo numérico
                if (columnasDinero.has(index)) {
                    return parsearNumero(text);
                }
                // Si no, intentamos parsear solo si parece número puro, sino texto
                return isNaN(Number(text.replace(/,/g,''))) ? text : parsearNumero(text);
            })
        );
        
        // Totales (Footer)
        let totalesRow = [];
        const tfoot = tabla.querySelector('tfoot');
        if (tfoot) {
            totalesRow = Array.from(tfoot.querySelectorAll('td')).map((td, index) => {
                let text = td.innerText.trim();
                // Aplicar misma lógica de columnas de dinero
                if (columnasDinero.has(index) || td.classList.contains('text-dinero')) {
                    return parsearNumero(text);
                }
                return text;
            });
        }
        
        // --- 3. CONSTRUIR ARRAY PARA EXCEL ---
        const excelData = [];
        
        // Metadatos visuales
        excelData.push(['PDL VISIONES - SISFACT']);
        excelData.push([TITULO_REPORTE]);
        excelData.push([DESCRIPCION]);
        excelData.push([]); // Espacio
        excelData.push(['Fecha:', new Date().toLocaleString()]);
        excelData.push(['Usuario:', USUARIO_ACTUAL]);
        
        if (TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria') {
            excelData.push(['Período:', `${FECHA_INICIO} - ${FECHA_FIN}`]);
        }
        
        // Total general en encabezado (si aplica)
        if (MOSTRAR_TOTALES && TOTAL_GENERAL > 0) {
            excelData.push(['Total General:', TOTAL_GENERAL]); 
        } else {
            excelData.push(['Total registros:', TOTAL_REGISTROS]);
        }
        
        excelData.push([]); // Espacio antes de la tabla
        
        // Variable para saber en qué fila de Excel empieza la tabla (0-indexed)
        // Header row index = excelData.length (actualmente)
        const headerRowIndex = excelData.length;
        
        // Agregar Tabla
        excelData.push(headers);
        rows.forEach(row => excelData.push(row));
        
        // Agregar Totales
        if (totalesRow.length > 0) {
            excelData.push(totalesRow);
        }
        
        // Pie
        excelData.push([]);
        excelData.push(['PDL VISIONES - Sistema Integral de Facturación y Control']);
        
        // --- 4. GENERAR WORKBOOK Y DAR FORMATO ---
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(excelData);
        
        // A) Definir el formato de moneda para Excel
        // "$ #,##0.00" es el formato estándar. 
        const formatoMoneda = '"$"#,##0.00'; 

        // B) Iterar sobre las celdas para aplicar formato
        const range = XLSX.utils.decode_range(ws['!ref']);
        
        // Recorremos columnas (C) y filas (R)
        for (let C = range.s.c; C <= range.e.c; ++C) {
            // Verificamos si esta columna (C) corresponde a una columna de dinero del HTML.
            // Nota: Las columnas en HTML (0,1,2) coinciden con las de Excel (A,B,C) 
            // aunque las filas estén desplazadas hacia abajo.
            if (columnasDinero.has(C)) {
                for (let R = headerRowIndex + 1; R <= range.e.r; ++R) {
                    const cellRef = XLSX.utils.encode_cell({r: R, c: C});
                    if (!ws[cellRef]) continue;
                    
                    // Si la celda es de tipo numérico ('n'), aplicamos formato
                    if (ws[cellRef].t === 'n') {
                        ws[cellRef].z = formatoMoneda;
                    }
                }
            }
        }

        // C) Formatear la celda de "Total General" en la parte superior si existe
        // Buscamos si insertamos el Total General en los metadatos
        if (MOSTRAR_TOTALES) {
            // Generalmente está en la columna B (índice 1), unas filas antes de la tabla.
            // Iteramos las primeras filas para encontrar el valor numérico suelto
            for (let R = 0; R < headerRowIndex; ++R) {
                const cellRef = XLSX.utils.encode_cell({r: R, c: 1}); // Columna B
                if (ws[cellRef] && ws[cellRef].t === 'n' && ws[cellRef].v == TOTAL_GENERAL) {
                    ws[cellRef].z = formatoMoneda;
                }
            }
        }

        // D) Estilos de Ancho de Columna
        const wscols = headers.map(() => ({wch: 20})); // Ancho por defecto
        ws['!cols'] = wscols;

        // E) Estilo Título (Solo funciona en versión Pro de SheetJS, pero dejamos la estructura)
        if(ws['A1']) ws['A1'].s = { font: { bold: true, sz: 14, color: { rgb: "0078D4" } } };

        // --- 5. DESCARGAR ---
        XLSX.utils.book_append_sheet(wb, ws, "Reporte");
        const fecha = new Date().toISOString().split('T')[0];
        const fileName = `Reporte_${TITULO_REPORTE.replace(/[^a-zA-Z0-9]/g, '_')}_${fecha}.xlsx`;
        
        XLSX.writeFile(wb, fileName);
        
        Swal.fire({
            icon: 'success',
            title: 'Excel generado',
            text: 'El archivo se ha generado con formato numérico contable.',
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar Excel:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo Excel: ' + error.message
        });
    }
}





// 2. EXPORTAR A WORD (CON PORTADA PARA ventas_generales)
function exportarWord() {
    try {
        // 1. OBTENER DATOS BÁSICOS DEL REPORTE
        const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
        const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
        const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
        const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
        const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
        const TOTAL_REGISTROS = <?php echo $total_registros; ?>;
        const TOTAL_GENERAL = <?php echo $total_general; ?>;
        const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
        const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
        
        // Nuevas variables para la portada
        const NOMBRE_MES_COMPLETO = '<?php echo isset($nombre_mes_completo) ? addslashes($nombre_mes_completo) : date("F"); ?>';
        const ANIO_CIERRE = '<?php echo isset($anio_cierre) ? $anio_cierre : date("Y"); ?>';
        
        // Fecha y hora formateadas
        const ahora = new Date();
        const horaFormateada = ahora.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true 
        });
        const fechaCompleta = ahora.toLocaleDateString('es-ES', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Logo en base64
        const logoBase64 = '<?php echo addslashes($logo_base64); ?>';
        
        // 2. CONSTRUIR CONTENIDO HTML PARA WORD CON PORTADA
        let contenidoHTML = `
            <html xmlns:o='urn:schemas-microsoft-com:office:office' 
                  xmlns:w='urn:schemas-microsoft-com:office:word' 
                  xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset="UTF-8">
                <title>Reporte - ${TITULO_REPORTE}</title>
                <style>
                    @page Section1 {
                        size: 595.3pt 841.9pt; /* Carta Vertical */
                        margin: 1.0cm 1.0cm 1.0cm 1.0cm;
                        mso-header-margin: 35.4pt;
                        mso-footer-margin: 35.4pt;
                        mso-footer: f1; 
                    }
                    div.Section1 { page: Section1; }
                    body { font-family: 'Arial', sans-serif; font-size: 11pt; } /* Aumentado de 10pt a 11pt */
                    
                    /* ========== PORTADA ========== */
                    .portada-container {
                        width: 100%;
                        min-height: 700pt; /* Fuerza la portada a ocupar toda la página */
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        text-align: center;
                        background-color: #f9f9f9;
                    }
                    
                    .portada-logo {
                        margin-bottom: 15pt;
                    }
                    
                    .portada-logo img {
                        width: 60pt;
                        height: 60pt;
                    }
                    
                    .portada-titulo {
                        color: #0078D4;
                        font-size: 16pt;
                        font-weight: bold;
                        margin: 8pt 0 4pt 0;
                    }
                    
                    .portada-mes {
                        color: #000000;
                        font-size: 24pt;
                        font-weight: bold;
                        margin: 4pt 0 4pt 0;
                        text-transform: uppercase;
                    }
                    
                    .portada-anio {
                        color: #333333;
                        font-size: 18pt;
                        margin: 4pt 0 12pt 0;
                    }
                    
                    .portada-linea {
                        border-top: 1.5pt solid #0078D4;
                        width: 120pt;
                        margin: 8pt auto;
                    }
                    
                    .portada-subtitulo {
                        color: #666666;
                        font-size: 14pt;
                        margin: 12pt 0 20pt 0;
                    }
                    
                    .portada-info {
                        font-size: 11pt;
                        color: #555555;
                        line-height: 1.6;
                        margin: 0 auto;
                        max-width: 300pt;
                    }
                    
                    .portada-info p {
                        margin: 6pt 0;
                    }
                    
                    .portada-pie {
                        font-size: 9pt;
                        color: #888888;
                        margin-top: 25pt;
                        line-height: 1.4;
                    }
                    
                    /* SALTO DE PÁGINA PARA WORD */
                    .page-break {
                        page-break-before: always;
                        mso-page-break-before: always;
                    }
                    
                    /* ========== CONTENIDO REGULAR ========== */
                    .header-table td { border: none; padding: 5px; vertical-align: middle; }
                    .header-titles h1 { color: #0078D4; font-size: 16pt; margin: 0; } /* Aumentado de 14pt a 16pt */
                    .header-titles h2 { color: #333; font-size: 12pt; margin: 2px 0 0 0; font-weight: normal; } /* Aumentado de 10pt a 12pt */
                    
                    /* LÍNEA INFORMATIVA */
                    .info-line { 
                        font-size: 11pt; /* Aumentado de 9pt a 11pt */
                        padding: 10px 0; /* Aumentado padding */
                        border-bottom: 2px solid #0078D4; 
                        margin-bottom: 12px; 
                        color: #444; 
                    }
                    .info-val { font-weight: bold; }
                    
                    /* TABLA DE DATOS - TAMAÑO AUMENTADO */
                    .data-table { 
                        border: 1px solid #000; 
                        font-size: 12pt; /* AUMENTADO de 9pt a 12pt */
                        width: 100%; 
                        table-layout: fixed; 
                        margin-top: 15px;
                    }
                    .data-table th { 
                        background-color: #0078D4; 
                        color: white; 
                        border: 1px solid #000; 
                        padding: 8px; /* Aumentado de 4px a 8px */
                        text-align: center; 
                        font-size: 11pt; /* Aumentado de 8pt a 11pt */
                        font-weight: bold;
                    }
                    .data-table td { 
                        border: 1px solid #666; 
                        padding: 6px; /* Aumentado de 3px a 6px */
                        vertical-align: middle; /* Cambiado de top a middle para mejor alineación */
                        word-wrap: break-word;
                        font-size: 12pt; /* Aseguramos 12pt también en celdas */
                    }
                    
                    /* ALINEACIONES */
                    .text-left { text-align: left; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .text-bold { font-weight: bold; }
                    
                    /* TOTALES */
                    .tfoot-total td { 
                        background-color: #f0f0f0; 
                        font-weight: bold; 
                        border-top: 2px solid #000;
                        font-size: 12pt; /* Aseguramos 12pt en totales */
                        padding: 8px; /* Aumentado padding */
                    }
                    
                    /* PIE DE PÁGINA */
                    div.MsoFooter { 
                        font-family: "Arial", sans-serif; 
                        font-size: 10pt; /* Aumentado de 9pt a 10pt */
                        text-align: center; 
                    }
                    .footer-line { 
                        margin: 3px 0; /* Aumentado margen */
                        line-height: 1.2; /* Aumentado line-height */
                    }
                </style>
            </head>
            <body>
                <div class="Section1">
        `;
        
        // 3. AGREGAR PORTADA SI ES FACTURACIÓN GENERAL
        if (TIPO_REPORTE === 'ventas_generales') {
            contenidoHTML += `
                <!-- PORTADA COMPACTA -->
                <div class="portada-container">
                    <!-- Logo -->
                    <div class="portada-logo">
                        <img src="${logoBase64}" alt="Logo PDL Visiones">
                    </div>
                    
                    <!-- Título principal -->
                    <div class="portada-titulo">
                        CIERRE DEL MES DE
                    </div>
                    
                    <!-- Mes -->
                    <div class="portada-mes">
                        ${NOMBRE_MES_COMPLETO.toUpperCase()}
                    </div>
                    
                    <!-- Año -->
                    <div class="portada-anio">
                        ${ANIO_CIERRE}
                    </div>
                    
                    <!-- Línea decorativa -->
                    <div class="portada-linea"></div>
                    
                    <!-- Subtítulo -->
                    <div class="portada-subtitulo">
                        FACTURACIÓN DETALLADA
                    </div>
                    
                    <!-- Información compacta -->
                    <div class="portada-info">
                        <p><strong>Período:</strong> ${FECHA_INICIO} - ${FECHA_FIN}</p>
                        <p><strong>Total facturación:</strong> $${TOTAL_GENERAL.toFixed(2)}</p>
                        <p><strong>Facturas registradas:</strong> ${TOTAL_REGISTROS}</p>
                    </div>
                    
                    <!-- Pie de portada -->
                    <div class="portada-pie">
                        <p>Generado por: ${USUARIO_ACTUAL}</p>
                        <p>${fechaCompleta} - ${horaFormateada}</p>
                    </div>
                </div>
            `;
        }
        
        // 4. OBTENER DATOS DE LA TABLA
        const tabla = document.getElementById('tablaReporte');
        const headers = Array.from(tabla.querySelectorAll('thead th')).map(th => 
            th.textContent.trim().replace(/[↑↓]/g, '')
        );
        
        const rows = Array.from(tabla.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map(td => {
                let text = td.textContent.trim();
                
                // Limpiar valores de dinero
                if (td.classList.contains('text-dinero')) {
                    text = text.replace(/[^\d.,-]/g, '');
                }
                
                // Extraer texto de badges
                const badge = td.querySelector('.badge-reporte');
                if (badge) {
                    text = badge.textContent.trim();
                }
                
                // Extraer texto de enlaces
                const link = td.querySelector('a');
                if (link) {
                    text = link.textContent.trim();
                }
                
                return text;
            })
        );
        
        if (rows.length === 0 && TIPO_REPORTE !== 'ventas_generales') {
            Swal.fire({ 
                icon: 'warning', 
                title: 'No hay datos', 
                text: 'No hay registros para exportar.', 
                confirmButtonText: 'Entendido' 
            });
            return;
        }
        
        // 5. AGREGAR SALTO DE PÁGINA Y CONTINUAR CON EL CONTENIDO
        if (TIPO_REPORTE === 'ventas_generales') {
            contenidoHTML += `
                <!-- SALTO DE PÁGINA ESPECÍFICO PARA WORD -->
                <div class="page-break"></div>
            `;
        }
        
        contenidoHTML += `
                    <!-- ENCABEZADO DE CONTENIDO -->
                    <table class="header-table">
                        <tr>
                            <td width="45">
                                <img src="${logoBase64}" width="40" height="40" style="width:40px; height:40px;" alt="Logo PDL Visiones">
                            </td>
                            <td>
                                <div class="header-titles">
                                    <h1>${TITULO_REPORTE.toUpperCase()}</h1>
                                    <h2>${DESCRIPCION}</h2>
                                </div>
                            </td>
                            <td style="text-align: right; font-size: 10pt; color: #555;" width="200"> <!-- Aumentado de 8pt a 10pt -->
                                <b>Fecha:</b> ${fechaCompleta}<br>
                                <b>Hora:</b> ${horaFormateada}<br>
                                <b>Usuario:</b> ${USUARIO_ACTUAL}
                            </td>
                        </tr>
                    </table>

                    <!-- INFORMACIÓN DEL REPORTE -->
                    <div class="info-line">
                        <span>REPORTE: <span class="info-val">${TITULO_REPORTE}</span></span> | 
                        <span>REGISTROS: <span class="info-val">${TOTAL_REGISTROS}</span></span>
                        ${TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria' ? 
                            ` | <span>PERÍODO: <span class="info-val">${FECHA_INICIO} - ${FECHA_FIN}</span></span>` : ''
                        }
                        ${MOSTRAR_TOTALES ? 
                            ` | <span>TOTAL: <span class="info-val">$${TOTAL_GENERAL.toFixed(2)}</span></span>` : ''
                        }
                    </div>

                    <!-- TABLA DE DATOS - TEXTO MÁS GRANDE -->
                    <table class="data-table" style="font-size: 12pt;">
                        <thead>
                            <tr>
                                ${headers.map((header, index) => {
                                    // Determinar ancho de columna basado en contenido
                                    let width = 'auto';
                                    if (headers.length <= 5) width = '20%';
                                    else if (headers.length <= 8) width = '12%';
                                    else width = '8%';
                                    
                                    return `<th width="${width}" style="font-size: 11pt; padding: 8px;">${header}</th>`;
                                }).join('')}
                            </tr>
                        </thead>
                        <tbody>`;

        // Agregar filas de datos (si hay)
        if (rows.length > 0) {
            rows.forEach(row => {
                contenidoHTML += '<tr>';
                
                row.forEach((cell, cellIndex) => {
                    const colIndex = cellIndex + 1;
                    let alineacion = 'center';
                    let estiloAdicional = '';
                    
// Determinar alineación (réplica de la lógica PHP)
const columnasDineroWord = <?php echo json_encode($columna_dinero); ?>;
const columnasNumericasWord = <?php echo json_encode($columnas_numericas); ?>;
const tipoReporteWord = '<?php echo $tipo_reporte; ?>';

const esDinero = Array.isArray(columnasDineroWord) 
    ? columnasDineroWord.includes(colIndex) 
    : columnasDineroWord === colIndex;

if (esDinero || columnasNumericasWord.includes(colIndex)) {
    alineacion = 'right';
    estiloAdicional = 'font-weight:bold;';
} else if (colIndex === 1 && ['ventas_servicios', 'ventas_por_categoria', 'ranking_clientes', 'deudores'].includes(tipoReporteWord)) {
    alineacion = 'left';
}
                    
                    contenidoHTML += `<td class="text-${alineacion}" style="${estiloAdicional} font-size: 12pt; padding: 6px;">${cell || ''}</td>`;
                });
                
                contenidoHTML += '</tr>';
            });
        } else {
            contenidoHTML += `
                <tr>
                    <td colspan="${headers.length}" style="text-align: center; padding: 20px; font-size: 12pt;">
                        No hay datos para mostrar
                    </td>
                </tr>`;
        }
        
        contenidoHTML += `
                        </tbody>`;
        
        // Agregar totales si corresponde
        if (MOSTRAR_TOTALES && rows.length > 0) {
            const tfoot = tabla.querySelector('tfoot');
            if (tfoot) {
                const totalRow = Array.from(tfoot.querySelectorAll('tr:first-child td')).map(td => 
                    td.textContent.trim()
                );
                
// ANTES de contenidoHTML, pasa los valores de PHP
const columnasDineroWord = <?php echo json_encode($columna_dinero); ?>;
const columnasNumericasWord = <?php echo json_encode($columnas_numericas); ?>;

// Luego en el template string:
contenidoHTML += `
    <tfoot class="tfoot-total">
        <tr>
            ${totalRow.map((cell, cellIndex) => {
                const colIndex = cellIndex + 1;
                let alineacion = 'center';
                
                const esDinero = Array.isArray(columnasDineroWord) 
                    ? columnasDineroWord.includes(colIndex) 
                    : columnasDineroWord === colIndex;
                
                if (esDinero || columnasNumericasWord.includes(colIndex)) {
                    alineacion = 'right';
                }
                
                return `<td class="text-${alineacion}" style="font-size: 12pt; padding: 8px;">${cell || ''}</td>`;
            }).join('')}
        </tr>
    </tfoot>`;
            }
        }
        
        contenidoHTML += `
                    </table>
                    
                    <!-- PIE DE PÁGINA -->
                    <div style="float: left; width: 0px; height: 0px; overflow: hidden;">
                        <div style='mso-element:footer' id="f1">
                            <div class="MsoFooter">
                                <!-- Línea separadora -->
                                <hr size="1" color="#000000" align="center" style="width:100%; height:1px; margin:0 0 5px 0;">
                                
                                <!-- Contenido del pie -->
                                <p class="footer-line" style="font-weight: bold; font-size: 12pt;"> <!-- Aumentado de 10pt a 12pt -->
                                    SISFACT PDL VISIONES - REPORTE DE INTELIGENCIA DE EMPRESA<br>
                                    ${fechaCompleta} - Hora: ${horaFormateada} - Impreso por: <b>${USUARIO_ACTUAL}</b>
                                </p>
                                <p class="footer-line" style="margin-top: 5px; font-size: 11pt;"> <!-- Aumentado -->
                                    Página <span style='mso-field-code:" PAGE "'></span> de <span style='mso-field-code:" NUMPAGES "'></span>
                                    | Registros: ${TOTAL_REGISTROS}
                                    ${MOSTRAR_TOTALES ? ` | Total: $${TOTAL_GENERAL.toFixed(2)}` : ''}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </body>
            </html>`;

        // 6. CREAR Y DESCARGAR ARCHIVO
        const fechaExportacion = new Date().toISOString().split('T')[0];
        const nombreArchivo = `Reporte_${TITULO_REPORTE.replace(/[^a-zA-Z0-9]/g, '_')}_${fechaExportacion}.doc`;
        
        const blob = new Blob(['\ufeff', contenidoHTML], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        // 7. CONFIRMAR ÉXITO
        Swal.fire({
            icon: 'success',
            title: 'Word generado',
            text: TIPO_REPORTE === 'ventas_generales' 
                ? `Documento con portada generado correctamente.`
                : `Archivo "${nombreArchivo}" descargado correctamente.`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar Word:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el documento Word. Detalle: ' + error.message
        });
    }
}


// 3. EXPORTAR A PDF (VERSIÓN CON PORTADA PARA ventas_generales)
function exportarPDF() {
    // --- 1. Variables desde PHP ---
    const LOGO_PROYECTO = '<?php echo $logo_base64; ?>';
    const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
    const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
    const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
    const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
    const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
    const FECHA_HOY = '<?php echo date("d/m/Y H:i"); ?>';
    const TOTAL_REGISTROS = '<?php echo $total_registros; ?>';
    const TOTAL_GENERAL = '<?php echo number_format($total_general, 2); ?>';
    const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
    const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
    
    // **NUEVAS VARIABLES PARA FILTROS DE HOJAS_UTILIZADAS**
    const ANIO_HOJAS = '<?php echo isset($_GET["anio_hojas"]) ? $_GET["anio_hojas"] : "TODOS"; ?>';
    const MES_HOJAS = '<?php echo isset($_GET["mes_hojas"]) ? $_GET["mes_hojas"] : "TODOS"; ?>';
    const CATEGORIA_HOJAS = '<?php echo isset($_GET["categoria_hojas"]) ? $_GET["categoria_hojas"] : "TODAS"; ?>';
    
    // **NUEVO: Array de meses en español**
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    // **NUEVO: Variables para aging_por_cliente**
    const ANIO_AGING = '<?php echo isset($_GET["anio_aging"]) ? $_GET["anio_aging"] : "TODOS"; ?>';
    const RANGO_AGING = '<?php echo isset($_GET["rango_aging"]) ? $_GET["rango_aging"] : "TODOS"; ?>';
    const ORDEN_AGING = '<?php echo isset($_GET["orden_aging"]) ? $_GET["orden_aging"] : "fecha_asc"; ?>';
    
    // Mapeo de rangos para mostrar
    const RANGOS_MAP = {
        '1-30': '1 - 30 días',
        '31-60': '31 - 60 días', 
        '61-90': '61 - 90 días',
        '91-120': '91 - 120 días',
        '121-150': '121 - 150 días',
        '151-180': '151 - 180 días',
        'mas180': 'Más de 180 días'
    };
    
    const ORDENES_MAP = {
        'fecha_asc': 'Fecha (más antigua)',
        'fecha_desc': 'Fecha (más reciente)',
        'monto_desc': 'Monto (mayor a menor)',
        'monto_asc': 'Monto (menor a mayor)'
    };
	
    // Nuevas variables para la portada
    const NOMBRE_MES_COMPLETO = '<?php echo isset($nombre_mes_completo) ? addslashes($nombre_mes_completo) : date("F"); ?>';
    const ANIO_CIERRE = '<?php echo isset($anio_cierre_num) ? $anio_cierre_num : date("Y"); ?>';
    
    const COLUMNA_DINERO_JS = <?php echo is_array($columna_dinero) ? json_encode($columna_dinero) : (is_numeric($columna_dinero) ? $columna_dinero : 'null'); ?>;

    try {
        const { jsPDF } = window.jspdf;
        
        // Determinar orientación según el tipo de reporte
        let orientacion = 'landscape';
        if (TIPO_REPORTE === 'rango_correlativos' || TIPO_REPORTE === 'hojas_utilizadas') {
            orientacion = 'portrait'; // Vertical para rango_correlativos y hojas_utilizadas
        }
        
        const doc = new jsPDF(orientacion, 'mm', 'letter');
        
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 15;

        // ===========================================
        // PORTADA COMPACTA PARA FACTURACIÓN GENERAL
        // ===========================================
        let tienePortada = false;
        
        if (TIPO_REPORTE === 'ventas_generales') {
            tienePortada = true;
            
            // Fondo gris claro para portada
            doc.setFillColor(245, 245, 245);
            doc.rect(0, 0, pageWidth, pageHeight, 'F');
            
            // Calcular posición vertical centrada
            const contenidoTotalAlto = 180; 
            const startY = (pageHeight - contenidoTotalAlto) / 2;
            
            // Logo centrado
            if (LOGO_PROYECTO && LOGO_PROYECTO.includes('base64')) {
                const logoWidth = 60;
                const logoHeight = 60;
                const logoX = (pageWidth - logoWidth) / 2;
                const logoY = startY;
                doc.addImage(LOGO_PROYECTO, 'PNG', logoX, logoY, logoWidth, logoHeight);
            }
            
            let currentY = startY + 70;
            
            // Título principal
            doc.setTextColor(0, 120, 212);
            doc.setFontSize(22);
            doc.setFont('helvetica', 'bold');
            doc.text('CIERRE DEL MES DE', pageWidth / 2, currentY, { align: 'center' });
            
            currentY += 15;
            
            // Mes en grande
            doc.setTextColor(0, 0, 0);
            doc.setFontSize(30);
            doc.text(NOMBRE_MES_COMPLETO.toUpperCase(), pageWidth / 2, currentY, { align: 'center' });
            
            currentY += 20;
            
            // Año
            doc.setFontSize(18);
            doc.text(ANIO_CIERRE.toString(), pageWidth / 2, currentY, { align: 'center' });
            
            currentY += 15;
            
            // Línea decorativa
            doc.setDrawColor(0, 120, 212);
            doc.setLineWidth(2);
            doc.line(pageWidth / 2 - 50, currentY, pageWidth / 2 + 50, currentY);
            
            currentY += 15;
            
            // Subtítulo
            doc.setFontSize(14);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(100);
            doc.text('FACTURACIÓN DETALLADA', pageWidth / 2, currentY, { align: 'center' });
            
            currentY += 20;
            
            // Información adicional
            doc.setFontSize(10);
            doc.setTextColor(80);
            const infoBlockY = currentY;
            doc.text(`Período: ${FECHA_INICIO} - ${FECHA_FIN}`, pageWidth / 2, infoBlockY, { align: 'center' });
            doc.text(`Total facturación: $${TOTAL_GENERAL}`, pageWidth / 2, infoBlockY + 7, { align: 'center' });
            doc.text(`Facturas registradas: ${TOTAL_REGISTROS}`, pageWidth / 2, infoBlockY + 14, { align: 'center' });
            
            // Añadir nueva página para el contenido (Esta será la "Página 1" del reporte)
            doc.addPage(orientacion, 'letter');
        }

        // ===========================================
        // ENCABEZADO (Solo para la primera página de contenido)
        // ===========================================
        let currentY = 15;

        // Tamaño del logo según reporte
        let logoSize = (TIPO_REPORTE === 'ventas_generales') ? 18 : 22;
        let logoY = currentY;
        
        if (LOGO_PROYECTO && LOGO_PROYECTO.includes('base64')) {
            doc.addImage(LOGO_PROYECTO, 'PNG', margin, logoY, logoSize, logoSize);
        }

        doc.setTextColor(0);
        doc.setFontSize(TIPO_REPORTE === 'ventas_generales' ? 16 : 18);
        doc.setFont('helvetica', 'bold');
        doc.text('PDL VISIONES', margin + (logoSize + 6), currentY + 6);
        
        doc.setFontSize(TIPO_REPORTE === 'ventas_generales' ? 8 : 10);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100);
        doc.text('Sistema Integral de Facturación - SISFACT', margin + (logoSize + 6), currentY + 11);
        
        doc.setFontSize(TIPO_REPORTE === 'ventas_generales' ? 10 : 12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(0);
        doc.text(TITULO_REPORTE.toUpperCase(), margin + (logoSize + 6), currentY + 18);

// ===== NUEVO: LÍNEA DE FILTROS PARA AGING_POR_CLIENTE =====
if (TIPO_REPORTE === 'aging_por_cliente') {
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(80, 80, 80);
    
    let filtrosTexto = [];
    
    if (ANIO_AGING !== 'TODOS') {
        filtrosTexto.push(`Año: ${ANIO_AGING}`);
    }
    
    if (RANGO_AGING !== 'TODOS') {
        filtrosTexto.push(`Rango: ${RANGOS_MAP[RANGO_AGING] || RANGO_AGING}`);
    }
    
    if (ORDEN_AGING !== 'fecha_asc') {
        filtrosTexto.push(`Orden: ${ORDENES_MAP[ORDEN_AGING]}`);
    }
    
    if (filtrosTexto.length > 0) {
        doc.text(`Filtros aplicados: ${filtrosTexto.join(' | ')}`, margin, currentY + 5);
        currentY += 8;
    }
}

        // ===== CORRECCIÓN MEJORADA PARA LA DESCRIPCIÓN =====
        doc.setFontSize(TIPO_REPORTE === 'ventas_generales' ? 8 : 9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100);
        
        // Función para limpiar texto de manera más agresiva
        function limpiarTexto(texto) {
            if (!texto) return '';
            
            // Decodificar entidades HTML
            let limpio = texto
                .replace(/&ntilde;/g, 'ñ')
                .replace(/&Ntilde;/g, 'Ñ')
                .replace(/&aacute;/g, 'á')
                .replace(/&eacute;/g, 'é')
                .replace(/&iacute;/g, 'í')
                .replace(/&oacute;/g, 'ó')
                .replace(/&uacute;/g, 'ú')
                .replace(/&Aacute;/g, 'Á')
                .replace(/&Eacute;/g, 'É')
                .replace(/&Iacute;/g, 'Í')
                .replace(/&Oacute;/g, 'Ó')
                .replace(/&Uacute;/g, 'Ú')
                .replace(/&amp;/g, '&')
                .replace(/&quot;/g, '"')
                .replace(/&apos;/g, "'")
                .replace(/&nbsp;/g, ' ')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>');
            
            // Eliminar etiquetas HTML
            limpio = limpio.replace(/<[^>]*>/g, ' ');
            
            // Reemplazar múltiples espacios por uno solo
            limpio = limpio.replace(/\s+/g, ' ');
            
            // Eliminar espacios al inicio y final
            limpio = limpio.trim();
            
            // Reemplazar caracteres problemáticos
            limpio = limpio.replace(/[^\x20-\x7EáéíóúñÁÉÍÓÚÑ]/g, '');
            
            return limpio;
        }
        
        // Limpiar la descripción
        let descripcionLimpia = limpiarTexto(DESCRIPCION);
        
        // Si la descripción está vacía después de limpiar, usar un texto por defecto
        if (!descripcionLimpia) {
            descripcionLimpia = 'Reporte de Consumibles - Papel Físico por Servicio';
        }
        
        // Calcular el ancho disponible para la descripción
        let anchoDisponible;
        if (orientacion === 'portrait') {
            anchoDisponible = pageWidth - margin - (margin + logoSize + 6) - 50; // Menos espacio para fecha en vertical
        } else {
            anchoDisponible = pageWidth - margin - (margin + logoSize + 6) - 70;
        }
        
        // Dividir el texto en líneas que quepan en el ancho disponible
        const lineasDescripcion = doc.splitTextToSize(descripcionLimpia, anchoDisponible);
        
        // Posición Y para la primera línea de la descripción
        let descY = currentY + 23;
        
        // Dibujar cada línea de la descripción
        lineasDescripcion.forEach((linea, index) => {
            doc.text(linea, margin + (logoSize + 6), descY + (index * 4.5));
        });
        
        // Actualizar currentY basado en cuántas líneas se dibujaron
        currentY = descY + (lineasDescripcion.length * 4.5) + 2;

        // ===== NUEVO: LÍNEA DE FILTROS PARA HOJAS_UTILIZADAS =====
        if (TIPO_REPORTE === 'hojas_utilizadas') {
            // Configurar fuente Arial 11
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(80, 80, 80); // Gris oscuro
            
            // Construir texto de filtros
            let textoFiltros = 'Filtros aplicados: ';
            
            // Año
            if (ANIO_HOJAS !== 'TODOS') {
                textoFiltros += `Año ${ANIO_HOJAS}`;
            } else {
                textoFiltros += 'Todos los años';
            }
            
            // Mes
            if (MES_HOJAS !== 'TODOS' && MES_HOJAS !== '') {
                const mesIndex = parseInt(MES_HOJAS) - 1;
                const mesNombre = MESES[mesIndex] || MES_HOJAS;
                textoFiltros += `, Mes: ${mesNombre}`;
            } else if (ANIO_HOJAS !== 'TODOS') {
                textoFiltros += ', Todos los meses';
            }
            
            // Categoría
            if (CATEGORIA_HOJAS !== 'TODAS') {
                const nombresCategorias = {
                    'BOND': 'Papel Normal',
                    'FOTO': 'Papel Fotográfico',
                    'SUBLIMACION': 'Papel de Sublimación',
                    'CART': 'Cartulina',
                    'CREDENCIALES': 'Credenciales'
                };
                const catNombre = nombresCategorias[CATEGORIA_HOJAS] || CATEGORIA_HOJAS;
                textoFiltros += `, Categoría: ${catNombre}`;
            }
            
            // Dibujar la línea de filtros
            doc.text(textoFiltros, margin, currentY);
            
            // Actualizar currentY
            currentY += 8;
        }

        // Bloque de datos derecha
        doc.setFontSize(8);
        const rightX = pageWidth - margin;
        let metaY = descY; // Alinear con la primera línea de descripción
        
        doc.text(`Fecha: ${FECHA_HOY}`, rightX, metaY, { align: 'right' });
        doc.text(`Usuario: ${USUARIO_ACTUAL}`, rightX, metaY + 4, { align: 'right' });
        if (TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria' && TIPO_REPORTE !== 'hojas_utilizadas') {
            doc.text(`Período: ${FECHA_INICIO} - ${FECHA_FIN}`, rightX, metaY + 8, { align: 'right' });
        }

        // Línea divisoria después del encabezado
        doc.setDrawColor(0);
        doc.setLineWidth(0.6);
        doc.line(margin, currentY, pageWidth - margin, currentY); 
        currentY += 8;

        // === PREPARACIÓN DE DATOS ===
        const tablaHTML = document.getElementById('tablaReporte');
        
        // Limpiar headers
        const headers = Array.from(tablaHTML.querySelectorAll('thead th')).map(th => {
            let texto = th.textContent.trim().replace(/[↑↓]/g, '');
            return limpiarTexto(texto);
        });
        
        // Limpiar rows
        const rows = Array.from(tablaHTML.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map(td => {
                // Limpiar HTML de las celdas
                let content = td.innerHTML;
                
                // Reemplazar <strong> por texto normal
                content = content.replace(/<strong>(.*?)<\/strong>/g, '$1');
                
                // Eliminar otras etiquetas HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = content;
                content = tempDiv.textContent || tempDiv.innerText || '';
                
                // Limpiar texto
                content = limpiarTexto(content);
                
                const badge = td.querySelector('.badge-reporte');
                return badge ? limpiarTexto(badge.textContent) : content;
            })
        );

        // Limpiar footer
        let footerRows = [];
        if (MOSTRAR_TOTALES) {
            const tfoot = tablaHTML.querySelector('tfoot');
            if (tfoot) {
                let footerData = [];
                const tfootCells = tfoot.querySelectorAll('tr');
                
                // Para rango_correlativos, puede haber múltiples filas de totales (por mes)
                tfootCells.forEach(tr => {
                    let rowData = [];
                    const cells = tr.querySelectorAll('td');
                    cells.forEach(cell => {
                        const colspan = cell.colSpan || 1;
                        let content = cell.innerHTML;
                        content = content.replace(/<strong>(.*?)<\/strong>/g, '$1');
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = content;
                        content = tempDiv.textContent || tempDiv.innerText || '';
                        content = limpiarTexto(content);
                        rowData.push(content);
                        for (let i = 1; i < colspan; i++) rowData.push('');
                    });
                    while(rowData.length < headers.length) rowData.push('');
                    footerData.push(rowData.slice(0, headers.length));
                });
                
                footerRows = footerData;
            }
        }

        // === CONFIGURACIÓN ESPECIAL SEGÚN TIPO DE REPORTE ===
        let columnStyles = {};
        let fontSize = 12; // <--- CAMBIADO A 12 PARA TODOS LOS REPORTES
        let cellPadding = 2;
        
        if (TIPO_REPORTE === 'hojas_utilizadas') {
            // Configuración específica para el reporte de hojas (8 columnas) - Vertical
            columnStyles = {
                0: { halign: 'left', cellWidth: 'auto' },   // Descripción
                1: { halign: 'right', cellWidth: 30 },  // Precio
                2: { halign: 'right', cellWidth: 18 },  // Servicios
                3: { halign: 'right', cellWidth: 19 },  // Hojas(UNO)
                4: { halign: 'right', cellWidth: 18 },  // PQT(500U)
                5: { halign: 'right', cellWidth: 18 },  // PQT(1000U)
                6: { halign: 'right', cellWidth: 30 },  // Facturado
                7: { halign: 'right', cellWidth: 21 }   // % Total Hojas
            };
            fontSize = 12; // <--- CAMBIADO A 12
            cellPadding = 1.2;
            
            // Reemplazar guiones largos por guiones normales en los datos
            rows.forEach(row => {
                if (row[4] === '—' || row[4] === '—' || row[4] === '') row[4] = '-';
                if (row[5] === '—' || row[5] === '—' || row[5] === '') row[5] = '-';
            });
            
            if (footerRows.length > 0) {
                footerRows.forEach(row => {
                    if (row[4] === '—' || row[4] === '—' || row[4] === '') row[4] = '-';
                    if (row[5] === '—' || row[5] === '—' || row[5] === '') row[5] = '-';
                });
            }
        } else if (TIPO_REPORTE === 'rango_correlativos') {
            // Configuración para rango_correlativos - Vertical
            // Determinar el número de columnas para ajustar anchos
            const numCols = headers.length;
            
            // Distribuir el ancho disponible entre las columnas
            const anchoTabla = pageWidth - (margin * 2);
            
            if (numCols <= 4) {
                // Pocas columnas
                columnStyles = {
                    0: { halign: 'left', cellWidth: anchoTabla * 0.3 },
                    1: { halign: 'center', cellWidth: anchoTabla * 0.2 },
                    2: { halign: 'center', cellWidth: anchoTabla * 0.2 },
                    3: { halign: 'right', cellWidth: anchoTabla * 0.3 }
                };
            } else if (numCols <= 6) {
                // Columnas medias
                columnStyles = {
                    0: { halign: 'left', cellWidth: anchoTabla * 0.25 },
                    1: { halign: 'center', cellWidth: anchoTabla * 0.15 },
                    2: { halign: 'center', cellWidth: anchoTabla * 0.15 },
                    3: { halign: 'center', cellWidth: anchoTabla * 0.15 },
                    4: { halign: 'right', cellWidth: anchoTabla * 0.15 },
                    5: { halign: 'right', cellWidth: anchoTabla * 0.15 }
                };
            } else {
                // Muchas columnas - distribuir equitativamente
                const anchoColumna = anchoTabla / numCols;
                headers.forEach((_, i) => {
                    let align = 'center';
                    // Las primeras columnas suelen ser descriptivas
                    if (i === 0) align = 'left';
                    // Las últimas columnas suelen ser montos
                    if (i >= numCols - 2) align = 'right';
                    
                    columnStyles[i] = { halign: align, cellWidth: anchoColumna };
                });
            }
            
            fontSize = 12; // <--- CAMBIADO A 12
            cellPadding = 1.5;
        } else {
            // Configuración genérica para otros reportes
            columnStyles = headers.reduce((acc, _, i) => {
                const col = i + 1;
                const esDinero = Array.isArray(COLUMNA_DINERO_JS) ? COLUMNA_DINERO_JS.includes(col) : COLUMNA_DINERO_JS === col;
                if (esDinero) {
                    acc[i] = { halign: 'right' };
                } else {
                    acc[i] = { halign: 'center' }; 
                }
                return acc;
            }, {});
            
            fontSize = 12; // <--- CAMBIADO A 12 PARA TODOS
            cellPadding = TIPO_REPORTE === 'ventas_generales' ? 4 : 2;
        }

        // === 4. LLAMADA A AUTOTABLE ===
        doc.autoTable({
            head: [headers],
            body: rows,
            foot: footerRows.length > 0 ? footerRows : null,
            startY: currentY,
            margin: { left: margin, right: margin, bottom: 25 },
            theme: 'grid',
            styles: { 
                fontSize: fontSize,
                cellPadding: cellPadding,
                textColor: 0,
                lineWidth: 0.1,
                lineColor: 0,
                overflow: 'linebreak',
                font: 'helvetica',
                minCellHeight: 5
            },
            headStyles: { 
                fillColor: [240, 240, 240], 
                fontStyle: 'bold', 
                halign: 'center',
                fontSize: fontSize,
                textColor: 0
            },
            footStyles: { 
                fillColor: [240, 240, 240], 
                fontStyle: 'bold', 
                textColor: 0,
                fontSize: fontSize
            },
            columnStyles: columnStyles,
            
            // Configuración para que el footer solo aparezca en la última página
            showFoot: 'lastPage',
            
            didDrawPage: function(data) {
                // Puedes agregar lógica adicional si es necesario
            }
        });

        // ===========================================
        // 5. INSERCIÓN DEL PIE DE PÁGINA (Paginación Correcta POR TIPO DE REPORTE)
        // ===========================================
        
        const totalPages = doc.internal.getNumberOfPages();
        // Si hay portada, el contenido empieza en la página 2
        const startPage = tienePortada ? 2 : 1;
        // El total de páginas de contenido es el total del PDF menos la portada (si existe)
        const totalContentPages = tienePortada ? (totalPages - 1) : totalPages;

        // **NUEVO: Definir qué reportes deben mostrar "Registros en esta página"**
        const reportesConRegistrosPorPagina = [
            'ventas_generales',
            'diferencia_facturas',
            'facturas_vencidas',
            'aging_por_cliente',
            'ventas_servicios',
            'ventas_por_categoria',
            'ranking_clientes',
            'deudores',
            'productividad_usuarios',
            'estado_facturas',
            'metodos_pago',
            'plan_vs_real',
            'hojas_utilizadas'  // Incluimos hojas_utilizadas
        ];

        // Determinar si debemos mostrar registros por página
        const mostrarRegistrosPorPagina = reportesConRegistrosPorPagina.includes(TIPO_REPORTE) && rows.length > 0;
        
        // Calcular registros por página SOLO si es necesario
        let registrosPorPagina = 0;
        if (mostrarRegistrosPorPagina) {
            registrosPorPagina = Math.ceil(rows.length / totalContentPages);
        }

        for (let i = startPage; i <= totalPages; i++) {
            doc.setPage(i);
            
            const footerY = pageHeight - 15;
            
            // Línea separadora
            doc.setDrawColor(0);
            doc.setLineWidth(0.2);
            doc.line(margin, footerY - 4, pageWidth - margin, footerY - 4);

            doc.setTextColor(0);
            
            // Izquierda
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.text('PDL VISIONES - SISFACT', margin, footerY);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.text('Sistema Integral de Facturación y Control', margin, footerY + 4);

            // Centro
            doc.setFontSize(8);
            doc.setFont('helvetica', 'italic');
            doc.text('PDL Visiones', pageWidth / 2, footerY, { align: 'center' });
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('www.pdlvisiones.com, "Donde tu visión toma forma"', pageWidth / 2, footerY + 4, { align: 'center' });

            // Derecha (Paginación Lógica)
            const pageNumLogico = i - (tienePortada ? 1 : 0);
            
            doc.setFontSize(7);
            const strPaginacion = `Página ${pageNumLogico} de ${totalContentPages}`;
            doc.text(strPaginacion, pageWidth - margin, footerY, { align: 'right' });
            
            // **NUEVO: Mostrar diferente información según el tipo de reporte**
            if (mostrarRegistrosPorPagina) {
                // Calcular registros en esta página específica
                let registrosEnEstaPagina = 0;
                if (i === totalPages) {
                    // Última página: registros restantes
                    registrosEnEstaPagina = rows.length - (registrosPorPagina * (totalContentPages - 1));
                } else {
                    // Páginas anteriores: registrosPorPagina
                    registrosEnEstaPagina = registrosPorPagina;
                }
                doc.text(`Registros en esta página: ${registrosEnEstaPagina}`, pageWidth - margin, footerY + 4, { align: 'right' });
            } else {
                // Para reportes sin paginación detallada, mostrar total general de registros
                doc.text(`Total registros: ${TOTAL_REGISTROS}`, pageWidth - margin, footerY + 4, { align: 'right' });
            }
            
            if (MOSTRAR_TOTALES) {
                doc.text(`Total: $${TOTAL_GENERAL}`, pageWidth - margin, footerY + 8, { align: 'right' });
            }
        }

        const fileName = `Reporte_${TITULO_REPORTE.replace(/\s+/g, '_')}_${new Date().getTime()}.pdf`;
        doc.save(fileName);
        
        Swal.fire({
            icon: 'success',
            title: 'PDF Generado',
            text: 'El archivo PDF se ha descargado correctamente.',
            timer: 2000,
            showConfirmButton: false
        });

    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo generar el PDF', 'error');
    }
}


// 4. EXPORTAR A CSV
function exportarCSV() {
    try {
        // Obtener datos necesarios de PHP
        const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
        const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
        const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
        const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
        const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
        const TOTAL_REGISTROS = <?php echo $total_registros; ?>;
        const TOTAL_GENERAL = <?php echo $total_general; ?>;
        const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
        const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
        
        // Obtener datos de la tabla
        const tabla = document.getElementById('tablaReporte');
        const headers = Array.from(tabla.querySelectorAll('thead th')).map(th => 
            th.textContent.trim().replace(/[↑↓]/g, '')
        );
        
        const rows = Array.from(tabla.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map(td => {
                let text = td.textContent.trim();
                
                // Limpiar valores de dinero
                if (td.classList.contains('text-dinero')) {
                    text = text.replace(/[^\d.,-]/g, '');
                }
                
                // Extraer texto de badges
                const badge = td.querySelector('.badge-reporte');
                if (badge) {
                    text = badge.textContent.trim();
                }
                
                // Extraer texto de enlaces
                const link = td.querySelector('a');
                if (link) {
                    text = link.textContent.trim();
                }
                
                // Escapar comas y comillas para CSV
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                
                return text;
            })
        );
        
        // Obtener totales de la tabla
        let totalesRow = [];
        const tfoot = tabla.querySelector('tfoot');
        if (tfoot) {
            totalesRow = Array.from(tfoot.querySelectorAll('td')).map(td => {
                let text = td.textContent.trim();
                // Escapar comas y comillas para CSV
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            });
        }
        
        // Crear contenido CSV
        let csvContent = '';
        
        // Encabezado del reporte
        csvContent += '"PDL VISIONES - SISFACT"\n';
        csvContent += `"${TITULO_REPORTE}"\n`;
        csvContent += `"${DESCRIPCION}"\n`;
        csvContent += `"Fecha de generación: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}"\n`;
        csvContent += `"Usuario: ${USUARIO_ACTUAL}"\n`;
        
        if (TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria') {
            csvContent += `"Período: ${FECHA_INICIO} - ${FECHA_FIN}"\n`;
        }
        
        csvContent += `"Total registros: ${TOTAL_REGISTROS}"\n`;
        
        if (MOSTRAR_TOTALES) {
            csvContent += `"Total general: $${TOTAL_GENERAL.toFixed(2)}"\n`;
        }
        
        csvContent += '\n'; // Línea en blanco
        
        // Cabecera de la tabla
        csvContent += headers.map(h => `"${h}"`).join(',') + '\n';
        
        // Datos de la tabla
        rows.forEach(row => {
            csvContent += row.join(',') + '\n';
        });
        
        // Línea en blanco
        csvContent += '\n';
        
        // Totales (si existen)
        if (totalesRow.length > 0) {
            csvContent += totalesRow.join(',') + '\n';
            csvContent += '\n';
        }
        
        // Pie de página
        csvContent += '"PDL VISIONES - Sistema Integral de Facturación y Control"\n';
        csvContent += '"www.pdlvisiones.com - \"Donde tu visión toma forma\""\n';
        csvContent += '"Documento generado automáticamente"';
        
        // Descargar archivo CSV
        const fecha = new Date().toISOString().split('T')[0];
        const fileName = `Reporte_${TITULO_REPORTE.replace(/[^a-zA-Z0-9]/g, '_')}_${fecha}.csv`;
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            icon: 'success',
            title: 'CSV generado',
            text: `Archivo "${fileName}" descargado correctamente`,
            timer: 3000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar CSV:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo CSV'
        });
    }
}

// 5. EXPORTAR A TXT
function exportarTXT() {
    try {
        // Obtener datos necesarios de PHP
        const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
        const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
        const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
        const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
        const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
        const TOTAL_REGISTROS = <?php echo $total_registros; ?>;
        const TOTAL_GENERAL = <?php echo $total_general; ?>;
        const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
        const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
        const COLUMNA_DINERO = <?php echo is_array($columna_dinero) ? json_encode($columna_dinero) : ($columna_dinero ?: 'null'); ?>;
        const COLUMNAS_NUMERICAS = <?php echo json_encode($columnas_numericas); ?>;
        
        // Obtener datos de la tabla
        const tabla = document.getElementById('tablaReporte');
        const headers = Array.from(tabla.querySelectorAll('thead th')).map(th => 
            th.textContent.trim().replace(/[↑↓]/g, '')
        );
        
        const rows = Array.from(tabla.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).map(td => {
                let text = td.textContent.trim();
                
                // Limpiar valores de dinero
                if (td.classList.contains('text-dinero')) {
                    text = text.replace(/[^\d.,-]/g, '');
                }
                
                // Extraer texto de badges
                const badge = td.querySelector('.badge-reporte');
                if (badge) {
                    text = badge.textContent.trim();
                }
                
                // Extraer texto de enlaces
                const link = td.querySelector('a');
                if (link) {
                    text = link.textContent.trim();
                }
                
                return text;
            })
        );
        
        // Calcular anchos de columna
        const colWidths = headers.map((header, index) => {
            const headerLength = header.length;
            const maxDataLength = Math.max(...rows.map(row => row[index] ? row[index].length : 0));
            return Math.max(headerLength, maxDataLength, 10) + 2;
        });
        
        // Crear contenido TXT
        let txtContent = '';
        
        // Encabezado
        txtContent += '='.repeat(80) + '\n';
        txtContent += 'PDL VISIONES - SISFACT\n';
        txtContent += '='.repeat(80) + '\n';
        txtContent += `Reporte: ${TITULO_REPORTE}\n`;
        txtContent += `Descripción: ${DESCRIPCION}\n\n`;
        txtContent += `Fecha de generación: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}\n`;
        txtContent += `Usuario: ${USUARIO_ACTUAL}\n`;
        
        if (TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria') {
            txtContent += `Período: ${FECHA_INICIO} - ${FECHA_FIN}\n`;
        }
        
        txtContent += `Total registros: ${TOTAL_REGISTROS}\n`;
        
        if (MOSTRAR_TOTALES) {
            txtContent += `Total general: $${TOTAL_GENERAL.toFixed(2)}\n`;
        }
        
        txtContent += '\n' + '='.repeat(80) + '\n\n';
        
        // Cabecera de tabla
        headers.forEach((header, index) => {
            txtContent += header.padEnd(colWidths[index]);
        });
        txtContent += '\n';
        
        // Línea separadora
        colWidths.forEach(width => {
            txtContent += '-'.repeat(width);
        });
        txtContent += '\n';
        
        // Datos de tabla
        rows.forEach(row => {
            row.forEach((cell, index) => {
                // Determinar alineación
                let alignedCell;
                const colIndex = index + 1;
                
                if (colIndex === COLUMNA_DINERO || 
                    (Array.isArray(COLUMNA_DINERO) && COLUMNA_DINERO.includes(colIndex)) ||
                    COLUMNAS_NUMERICAS.includes(colIndex)) {
                    // Alinear a la derecha para números
                    alignedCell = String(cell || '').padStart(colWidths[index]);
                } else {
                    alignedCell = String(cell || '').padEnd(colWidths[index]);
                }
                
                txtContent += alignedCell;
            });
            txtContent += '\n';
        });
        
        // Pie de tabla (totales)
        const tfoot = tabla.querySelector('tfoot');
        if (tfoot) {
            txtContent += '\n' + '-'.repeat(80) + '\n';
            
            const totalRow = Array.from(tfoot.querySelectorAll('td')).map(td => td.textContent.trim());
            totalRow.forEach((cell, index) => {
                let alignedCell;
                const colIndex = index + 1;
                
                if (colIndex === COLUMNA_DINERO || 
                    (Array.isArray(COLUMNA_DINERO) && COLUMNA_DINERO.includes(colIndex)) ||
                    COLUMNAS_NUMERICAS.includes(colIndex)) {
                    alignedCell = String(cell || '').padStart(colWidths[index]);
                } else {
                    alignedCell = String(cell || '').padEnd(colWidths[index]);
                }
                
                txtContent += alignedCell;
            });
            txtContent += '\n' + '-'.repeat(80) + '\n';
        }
        
        // Pie de página
        txtContent += '\n\n' + '='.repeat(80) + '\n';
        txtContent += 'PDL VISIONES - Sistema Integral de Facturación y Control\n';
        txtContent += 'www.pdlvisiones.com - "Donde tu visión toma forma"\n';
        txtContent += 'Documento generado automáticamente\n';
        txtContent += '='.repeat(80);
        
        // Descargar archivo TXT
        const fecha = new Date().toISOString().split('T')[0];
        const fileName = `Reporte_${TITULO_REPORTE.replace(/[^a-zA-Z0-9]/g, '_')}_${fecha}.txt`;
        const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            icon: 'success',
            title: 'TXT generado',
            text: `Archivo "${fileName}" descargado correctamente`,
            timer: 3000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error al exportar TXT:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar el archivo TXT'
        });
    }
}


function imprimirReporte() {
    // 1. Obtención de datos maestros desde PHP
    const TIPO_REPORTE = '<?php echo $tipo_reporte; ?>';
    const TITULO_REPORTE = '<?php echo addslashes($titulo_reporte); ?>';
    const DESCRIPCION = '<?php echo addslashes($descripcion_reporte); ?>';
    const NOMBRE_MES_COMPLETO = '<?php echo isset($nombre_mes_completo) ? addslashes($nombre_mes_completo) : date("F"); ?>';
    const ANIO_CIERRE = '<?php echo isset($anio_cierre_num) ? $anio_cierre_num : date("Y"); ?>';
    const TOTAL_GENERAL = '<?php echo number_format($total_general, 2); ?>';
    const TOTAL_REGISTROS = '<?php echo $total_registros; ?>';
    const TOTAL_PRODUCTOS = '<?php echo isset($total_productos_global) ? number_format($total_productos_global, 0) : "0"; ?>';
    const USUARIO_ACTUAL = '<?php echo addslashes($_SESSION["usuario_nombre"] ?? "Usuario"); ?>';
    const FECHA_HOY = '<?php echo $fechaGeneracion; ?>';
    const FECHA_INICIO = '<?php echo date("d/m/Y", strtotime($fecha_inicio)); ?>';
    const FECHA_FIN = '<?php echo date("d/m/Y", strtotime($fecha_fin)); ?>';
    const LOGO_BASE64 = '<?php echo $logo_base64; ?>';
    const MOSTRAR_TOTALES = <?php echo $mostrar_totales ? 'true' : 'false'; ?>;
    const FECHA_OPERACIONES = '<?php echo $nombre_mes_completo .'/'.$anio_actual; ?>';
    
    // COLUMNAS QUE SON NUMÉRICAS (desde PHP)
    const COLUMNAS_NUMERICAS = <?php echo json_encode($columnas_numericas); ?>;
    const COLUMNA_DINERO = <?php echo is_array($columna_dinero) ? json_encode($columna_dinero) : ($columna_dinero ? '['.$columna_dinero.']' : '[]'); ?>;
    
    // DATOS ESPECÍFICOS POR TIPO DE REPORTE (desde PHP)
    <?php if ($tipo_reporte == 'ventas_por_categoria'): ?>
    const TOTALES_CATEGORIA = {
        total_facturas: <?php echo $total_facturas ?? 0; ?>,
        total_items: <?php echo $total_items ?? 0; ?>,
        total_subtotal: <?php echo $total_subtotal ?? 0; ?>,
        total_monto: <?php echo $total_general ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'diferencia_facturas'): ?>
    const TOTALES_DIFERENCIA = {
        total_facturas: <?php echo $total_pagina_facturas ?? 0; ?>,
        total_detalles: <?php echo $total_pagina_detalles ?? 0; ?>,
        total_diferencia: <?php echo $total_general ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'metodos_pago'): ?>
    const TOTALES_METODOS = {
        total_operaciones: <?php echo $total_operaciones_pagina ?? 0; ?>,
        total_monto: <?php echo $total_general ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'plan_vs_real'): ?>
    const TOTALES_PLAN = {
        total_plan: <?php echo $total_plan_pagina ?? 0; ?>,
        total_real: <?php echo $total_real_pagina ?? 0; ?>,
        cumplimiento: <?php echo ($total_plan_pagina > 0) ? round(($total_real_pagina / $total_plan_pagina) * 100, 2) : 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'ventas_servicios' || $tipo_reporte == 'ranking_clientes' || $tipo_reporte == 'deudores' || $tipo_reporte == 'productividad_usuarios'): ?>
    const TOTALES_CANTIDAD_MONTO = {
        total_cantidad: <?php echo $total_cantidad_pagina ?? 0; ?>,
        total_monto: <?php echo $total_general ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'historico_cierres' && isset($totales_cierres)): ?>
    const TOTALES_HISTORICO = {
        total_facturas: <?php echo $totales_cierres['total_facturas'] ?? 0; ?>,
        total_importe: <?php echo $totales_cierres['total_importe'] ?? 0; ?>,
        total_pagadas: <?php echo $totales_cierres['total_pagadas'] ?? 0; ?>,
        total_contabilizadas: <?php echo $totales_cierres['total_contabilizadas'] ?? 0; ?>,
        total_registros: <?php echo $totales_cierres['total_registros'] ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'aging_por_cliente' && isset($aging_totals)): ?>
    const TOTALES_AGING = {
        rango1: <?php echo $aging_totals[4] ?? 0; ?>,
        rango2: <?php echo $aging_totals[5] ?? 0; ?>,
        rango3: <?php echo $aging_totals[6] ?? 0; ?>,
        rango4: <?php echo $aging_totals[7] ?? 0; ?>,
        rango5: <?php echo $aging_totals[8] ?? 0; ?>,
        rango6: <?php echo $aging_totals[9] ?? 0; ?>,
        rango7: <?php echo $aging_totals[10] ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'ventas_generales'): ?>
    const TOTALES_VENTAS = {
        total_productos: <?php echo $total_productos_global ?? 0; ?>,
        total_dinero: <?php echo $total_general ?? 0; ?>,
        total_anuladas: <?php echo $anuladas_row['cantidad_anuladas'] ?? 0; ?>,
        monto_anuladas: <?php echo $anuladas_row['total_anuladas'] ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'rango_correlativos'): ?>
    const TOTALES_CORRELATIVOS = {
        total_facturas: <?php echo $GLOBALS['total_facturas_correlativos'] ?? 0; ?>
    };
    <?php endif; ?>
    
    <?php if ($tipo_reporte == 'hojas_utilizadas'): ?>
    const TOTALES_HOJAS = {
        total_servicios: <?php echo $total_servicios_filtrado ?? 0; ?>,
        total_hojas: <?php echo $total_hojas_filtrado ?? 0; ?>,
        total_paquetes500: <?php echo $total_paquetes500_filtrado ?? 0; ?>,
        total_paquetes1000: <?php echo $total_paquetes1000_filtrado ?? 0; ?>,
        total_dinero: <?php echo $total_dinero_filtrado ?? 0; ?>
    };
    <?php endif; ?>

    // ===== CAPTURAR TODOS LOS FILTROS APLICADOS =====
    const filtrosAplicados = [];
    
    // Filtros generales
    if (TIPO_REPORTE !== 'vencimiento_contratos' && TIPO_REPORTE !== 'auditoria') {
        filtrosAplicados.push(`Período: ${FECHA_INICIO} - ${FECHA_FIN}`);
    }
    
    // Filtros específicos por tipo de reporte
    switch(TIPO_REPORTE) {
        case 'ventas_generales':
            const clienteFiltro = '<?php echo isset($_GET["cliente_filtro"]) ? addslashes($_GET["cliente_filtro"]) : "TODOS"; ?>';
            if (clienteFiltro !== 'TODOS') {
                const nombreCliente = '<?php 
                    if (isset($_GET["cliente_filtro"]) && $_GET["cliente_filtro"] !== "TODOS") {
                        $stmt = $db->prepare("SELECT nombre FROM clasif_clientes WHERE id = ?");
                        $stmt->execute([$_GET["cliente_filtro"]]);
                        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo addslashes($cliente["nombre"] ?? "");
                    }
                ?>';
                filtrosAplicados.push(`Cliente: ${nombreCliente || clienteFiltro}`);
            }
            break;
            
        case 'rango_correlativos':
            const anioFiltro = '<?php echo isset($_GET["anio_filtro"]) ? $_GET["anio_filtro"] : "TODOS"; ?>';
            const mesFiltro = '<?php echo isset($_GET["mes_filtro"]) ? $_GET["mes_filtro"] : "TODOS"; ?>';
            if (anioFiltro !== 'TODOS') filtrosAplicados.push(`Año: ${anioFiltro}`);
            if (mesFiltro !== 'TODOS') {
                const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                filtrosAplicados.push(`Mes: ${meses[parseInt(mesFiltro)-1]}`);
            }
            break;
            
        case 'hojas_utilizadas':
            const anioHojas = '<?php echo isset($_GET["anio_hojas"]) ? $_GET["anio_hojas"] : "TODOS"; ?>';
            const mesHojas = '<?php echo isset($_GET["mes_hojas"]) ? $_GET["mes_hojas"] : "TODOS"; ?>';
            const categoriaHojas = '<?php echo isset($_GET["categoria_hojas"]) ? $_GET["categoria_hojas"] : "TODAS"; ?>';
            
            if (anioHojas !== 'TODOS') filtrosAplicados.push(`Año: ${anioHojas}`);
            if (mesHojas !== 'TODOS') {
                const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                filtrosAplicados.push(`Mes: ${meses[parseInt(mesHojas)-1]}`);
            }
            if (categoriaHojas !== 'TODAS') {
                const categorias = {
                    'BOND': 'Papel Normal',
                    'FOTO': 'Papel Fotográfico',
                    'SUBLIMACION': 'Papel de Sublimación',
                    'CART': 'Cartulina',
                    'CREDENCIALES': 'Credenciales'
                };
                filtrosAplicados.push(`Categoría: ${categorias[categoriaHojas] || categoriaHojas}`);
            }
            break;
            
        case 'diferencia_facturas':
            const filtroDif = '<?php echo isset($_GET["filtro_diferencia"]) ? $_GET["filtro_diferencia"] : "todas"; ?>';
            const descDif = {
                'todas': 'Todas las facturas',
                'con_diferencia': 'Solo con diferencias',
                'sin_detalles': 'Sin detalles',
                'anuladas': 'Anuladas',
                'pendientes': 'Pendientes',
                'ok': 'Correctas (OK)'
            };
            filtrosAplicados.push(`Filtro: ${descDif[filtroDif] || filtroDif}`);
            break;
            
        case 'facturas_vencidas':
            const anioVencidas = '<?php echo isset($_GET["anio_vencidas"]) ? $_GET["anio_vencidas"] : "TODOS"; ?>';
            const diasVencidos = '<?php echo isset($_GET["dias_vencidos"]) ? $_GET["dias_vencidos"] : ""; ?>';
            if (anioVencidas !== 'TODOS') filtrosAplicados.push(`Año: ${anioVencidas}`);
            if (diasVencidos) {
                const rangos = {'30': '1-30 días', '60': '31-60 días', '90': '61-90 días', 
                               '120': '91-120 días', '150': '121-150 días', '180': '151-180 días', 
                               'mas180': 'Más de 180 días'};
                filtrosAplicados.push(`Rango: ${rangos[diasVencidos]}`);
            }
            break;
            
 case 'aging_por_cliente':
        const anioAging = '<?php echo isset($_GET["anio_aging"]) ? $_GET["anio_aging"] : "TODOS"; ?>';
        const rangoAging = '<?php echo isset($_GET["rango_aging"]) ? $_GET["rango_aging"] : "TODOS"; ?>';
        const ordenAging = '<?php echo isset($_GET["orden_aging"]) ? $_GET["orden_aging"] : "fecha_asc"; ?>';
        
        if (anioAging !== 'TODOS') {
            filtrosAplicados.push(`Año: ${anioAging}`);
        }
        
        if (rangoAging !== 'TODOS') {
            const rangos = {
                '1-30': '1 - 30 días',
                '31-60': '31 - 60 días',
                '61-90': '61 - 90 días',
                '91-120': '91 - 120 días',
                '121-150': '121 - 150 días',
                '151-180': '151 - 180 días',
                'mas180': 'Más de 180 días'
            };
            filtrosAplicados.push(`Rango: ${rangos[rangoAging] || rangoAging}`);
        }
        
        const ordenes = {
            'fecha_asc': 'Fecha (más antigua)',
            'fecha_desc': 'Fecha (más reciente)',
            'monto_desc': 'Monto (mayor a menor)',
            'monto_asc': 'Monto (menor a mayor)'
        };
        filtrosAplicados.push(`Orden: ${ordenes[ordenAging]}`);
        break;
            
        case 'historico_cierres':
            const filtroTipo = '<?php echo isset($_GET["filtro_tipo"]) ? $_GET["filtro_tipo"] : ""; ?>';
            const filtroAnio = '<?php echo isset($_GET["filtro_anio"]) ? $_GET["filtro_anio"] : ""; ?>';
            if (filtroTipo) {
                const tipos = {'1': 'Cierres Mensuales', '2': 'Cierres Anuales'};
                filtrosAplicados.push(`Tipo: ${tipos[filtroTipo]}`);
            }
            if (filtroAnio) filtrosAplicados.push(`Año: ${filtroAnio}`);
            break;
            
        case 'auditoria':
            const fechaInicioAud = '<?php echo isset($_GET["fecha_inicio_aud"]) ? $_GET["fecha_inicio_aud"] : ""; ?>';
            const fechaFinAud = '<?php echo isset($_GET["fecha_fin_aud"]) ? $_GET["fecha_fin_aud"] : ""; ?>';
            const operacionAud = '<?php echo isset($_GET["operacion_aud"]) ? $_GET["operacion_aud"] : "TODAS"; ?>';
            const usuarioAud = '<?php echo isset($_GET["usuario_aud"]) ? $_GET["usuario_aud"] : "TODOS"; ?>';
            if (fechaInicioAud && fechaFinAud) filtrosAplicados.push(`Período: ${fechaInicioAud} - ${fechaFinAud}`);
            if (operacionAud !== 'TODAS') filtrosAplicados.push(`Operación: ${operacionAud}`);
            if (usuarioAud !== 'TODOS') filtrosAplicados.push(`Usuario: ${usuarioAud}`);
            break;
            
        case 'vencimiento_contratos':
            const filtroEstado = '<?php echo isset($_GET["filtro_estado"]) ? $_GET["filtro_estado"] : ""; ?>';
            const estados = {'vigentes': 'Vigentes', 'por_vencer': 'Por vencer', 
                            'vencidos': 'Vencidos', 'indefinidos': 'Indefinidos'};
            if (filtroEstado) filtrosAplicados.push(`Estado: ${estados[filtroEstado] || filtroEstado}`);
            break;
    }

    // 2. Determinar orientación
    const reportesAnchos = ['aging_por_cliente', 'auditoria', 'historico_cierres', 'vencimiento_contratos', 'diferencia_facturas', 'ventas_generales'];
    const orientacion = reportesAnchos.includes(TIPO_REPORTE) ? 'landscape' : 'portrait';

    // 3. Clonación y limpieza
    const printArea = document.getElementById('printArea');
    const clone = printArea.cloneNode(true);
    clone.querySelectorAll('.no-print, button, .sort-icon, .btn, .export-combo-container').forEach(el => el.remove());
    clone.querySelectorAll('.print-header, .print-footer').forEach(el => el.remove());
    
    // Limpieza de links y badges
    clone.querySelectorAll('a').forEach(a => {
        const span = document.createElement('span');
        span.textContent = a.textContent.trim();
        a.parentNode.replaceChild(span, a);
    });
    clone.querySelectorAll('.badge-reporte, .badge').forEach(badge => {
        const texto = document.createTextNode(badge.textContent.trim());
        badge.parentNode.replaceChild(texto, badge);
    });

    // 4. Procesamiento de la tabla y Fragmentación
    const tablaOriginal = clone.querySelector('table');
    if (!tablaOriginal) return;

    const thead = tablaOriginal.querySelector('thead') ? tablaOriginal.querySelector('thead').outerHTML : '';
    const filas = Array.from(tablaOriginal.querySelectorAll('tbody tr'));
    
    // --- REPORTES QUE NO DEBEN MOSTRAR SUBTOTALES NI TOTAL GENERAL ---
    const reportesSinTotales = ['rango_correlativos', 'vencimiento_contratos'];
    const esReporteSinTotales = reportesSinTotales.includes(TIPO_REPORTE);
    
    const FILAS_POR_PAGINA = 20;
    const paginasContenido = Math.ceil(filas.length / FILAS_POR_PAGINA);
    const TOTAL_PAGINAS = esReporteSinTotales ? paginasContenido + 1 : paginasContenido + 2; // +1 portada, +1 resumen (si aplica)

    // Función auxiliar para limpiar números
    const limpiarNumero = (str) => {
        if (!str) return 0;
        const limpio = str.toString().replace(/[^\d.-]/g, '');
        const numero = parseFloat(limpio);
        return isNaN(numero) ? 0 : numero;
    };

    // Función para verificar si una columna es numérica
    const esColumnaNumerica = (index) => {
        const colNum = index + 1;
        return COLUMNAS_NUMERICAS.includes(colNum) || COLUMNA_DINERO.includes(colNum);
    };

    // Función para formatear números según el tipo de columna
    const formatearNumero = (num, columnaIndex = null) => {
        if (num === 0 || num === null || num === undefined) return '0';
        
        // Determinar decimales según el tipo de columna
        let decimales = 2;
        
        if (columnaIndex !== null) {
            const colNum = columnaIndex + 1;
            
            // Casos especiales según tipo de reporte
            if (TIPO_REPORTE === 'plan_vs_real' && colNum === 4) {
                // Columna de porcentaje
                return num.toFixed(2) + '%';
            }
            else if (TIPO_REPORTE === 'metodos_pago' && colNum === 2) {
                // Cant. Operaciones - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'deudores' && colNum === 2) {
                // Facturas Pendientes - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'estado_facturas' && colNum === 2) {
                // Cant. Facturas - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'historico_cierres' && (colNum === 5 || colNum === 7 || colNum === 8)) {
                // Total Fact., Pagadas, Contabilizadas - enteros
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'ventas_servicios' && colNum === 3) {
                // Cant. Vendida - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'ranking_clientes' && colNum === 4) {
                // Cant. Facturas - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'productividad_usuarios' && colNum === 4) {
                // Facturas Creadas - entero
                decimales = 0;
            }
            else if (TIPO_REPORTE === 'hojas_utilizadas' && colNum === 3) {
                // Servicios - entero
                decimales = 0;
            }
            else if (COLUMNA_DINERO.includes(colNum)) {
                // Columnas de dinero - 2 decimales
                decimales = 2;
            }
            else if (COLUMNAS_NUMERICAS.includes(colNum)) {
                // Otras columnas numéricas - enteras por defecto
                decimales = 0;
            }
        }
        
        return num.toLocaleString('es-ES', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
    };

    // Función Footer Original (3 columnas)
    const createFooter = (pageNum) => `
        <div class="footer-dist">
            <table style="width: 100%; border: none !important; margin-top: 10px;">
                <tr>
                    <td style="border: none !important; width: 33.3%; text-align: left; padding: 0;">
                        <strong style="font-size: 10px;">PDL VISIONES - SISFACT</strong><br>
                        <span style="font-size: 8px; color: #444;">Sistema Integral de Facturación</span>
                    </td>
                    <td style="border: none !important; width: 33.3%; text-align: center; padding: 0;">
                        <strong style="font-size: 11px;">PÁGINA ${pageNum} DE ${TOTAL_PAGINAS}</strong><br>
                        <span style="font-size: 8px; font-style: italic;">"Donde tu visión toma forma"</span>
                    </td>
                    <td style="border: none !important; width: 33.3%; text-align: right; padding: 0;">
                        <span style="font-size: 9px;">${USUARIO_ACTUAL}</span><br>
                        <span style="font-size: 8px;">${FECHA_HOY}</span>
                    </td>
                </tr>
            </table>
        </div>
    `;

    // 6. Construcción del HTML Final
    let htmlFinal = '';

    // --- PÁGINA 1: PORTADA ENMARCADA CON FILTROS ---
    const filtrosHTML = filtrosAplicados.length > 0 ? `
        <div style="margin: 15px 0; padding: 10px; background: #f0f8ff; border-left: 4px solid #0078D4; text-align: left; font-size: 11px;">
            <strong style="color: #0078D4;">FILTROS APLICADOS:</strong><br>
            ${filtrosAplicados.map(f => `<span style="display: inline-block; background: #e6f3ff; padding: 3px 8px; margin: 3px; border-radius: 12px; font-size: 10px;">🔍 ${f}</span>`).join(' ')}
        </div>
    ` : '';

    htmlFinal += `
        <div class="page-sheet page-center">
            <div class="portada-box">
                <img src="${LOGO_BASE64}" style="width: 120px; margin-bottom: 20px;">
                <h1 style="color: #0078D4; font-size: 26px; margin: 0; font-weight: bold;">PDL VISIONES - SISFACT</h1>
                <h2 style="font-size: 32px; font-weight: bold; margin: 15px 0; color: #000; text-transform: uppercase;">
                    ${TIPO_REPORTE === 'ventas_generales' ? NOMBRE_MES_COMPLETO : TITULO_REPORTE}
                </h2>
                <h3 style="font-size: 26px; color: #444; margin: 5px 0;">${ANIO_CIERRE}</h3>
                <div style="border-top: 3px solid #0078D4; width: 200px; margin: 20px auto;"></div>
                
                ${filtrosHTML}
                
                <div class="portada-detalles">
                    <p><strong>Tipo de Reporte:</strong> ${TITULO_REPORTE}</p>
                    <p><strong>Período:</strong> ${FECHA_INICIO} - ${FECHA_FIN}</p>
                    <p><strong>Fecha de Operaciones:</strong> ${FECHA_OPERACIONES}</p>
                    <p><strong>Registros Procesados:</strong> ${TOTAL_REGISTROS}</p>
                    ${TIPO_REPORTE === 'ventas_generales' ? `<p><strong>Total Productos:</strong> ${TOTAL_PRODUCTOS}</p>` : ''}
                    ${MOSTRAR_TOTALES ? `<p><strong>Importe Total:</strong> $ ${TOTAL_GENERAL}</p>` : ''}
                </div>
                <div style="margin-top: 40px; color: #777; font-size: 12px;">
                    <p>Generado por: <strong>${USUARIO_ACTUAL}</strong></p>
                    <p>Fecha de emisión: ${FECHA_HOY}</p>
                </div>
            </div>
            ${createFooter(1)}
        </div>
    `;

    // --- VARIABLES PARA ACUMULAR TOTALES GLOBALES ---
    const numColumnas = Array.from(tablaOriginal.querySelectorAll('thead th')).length;
    let totalesGlobales = {};
    for (let c = 1; c <= numColumnas; c++) {
        totalesGlobales[`col${c}`] = 0;
    }

    // --- VARIABLES ESPECÍFICAS PARA VENCIMIENTO CONTRATOS ---
    let contratosVigentes = 0;
    let contratosVencidos = 0;
    let contratosPorVencer = 0;
    let contratosIndefinidos = 0;

    // Si es vencimiento_contratos, clasificar contratos
    if (TIPO_REPORTE === 'vencimiento_contratos') {
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td');
            if (celdas.length >= 10) {
                const estadoTexto = celdas[9].textContent.trim();
                const vigencia = limpiarNumero(celdas[4].textContent);
                
                if (vigencia === 0) {
                    contratosIndefinidos++;
                }
                else if (estadoTexto.includes('Vencido')) {
                    contratosVencidos++;
                }
                else if (estadoTexto.includes('Por vencer')) {
                    contratosPorVencer++;
                }
                else if (estadoTexto.includes('Vigente')) {
                    contratosVigentes++;
                }
            }
        });
    }

    // --- SI ES REPORTE SIN TOTALES, SOLO MOSTRAR CONTENIDO ---
    if (esReporteSinTotales) {
        for (let i = 0; i < filas.length; i += FILAS_POR_PAGINA) {
            const pageNum = Math.floor(i / FILAS_POR_PAGINA) + 2;
            const chunk = filas.slice(i, i + FILAS_POR_PAGINA);
            
            htmlFinal += `
                <div class="page-sheet">
                    <div class="header-small">
                        <strong>PDL VISIONES</strong> | ${TITULO_REPORTE.toUpperCase()} | ${FECHA_INICIO} - ${FECHA_FIN}
                    </div>
                    <div class="table-container">
                        <table class="report-table-print">
                            ${thead}
                            <tbody>
                                ${chunk.map(f => f.outerHTML).join('')}
                            </tbody>
                        </table>
                    </div>
                    ${createFooter(pageNum)}
                </div>
            `;
        }
        
        // Para vencimiento_contratos, agregar página de resumen aunque sea reporte sin totales
        if (TIPO_REPORTE === 'vencimiento_contratos') {
            const pageNumResumen = TOTAL_PAGINAS + 1;
            const resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 30px; width: 80%; background: white;">
                        <h2 style="color: #0078D4; font-size: 24px; margin-bottom: 30px; text-align: center;">RESUMEN DE CONTRATOS POR ESTADO</h2>
                        
                        <table style="width: 70%; margin: 0 auto; border-collapse: collapse; font-size: 16px;">
                            <tr style="background: #e8f4f8;">
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Vigentes:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4;">${contratosVigentes}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Vencidos:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4; color: #dc3545;">${contratosVencidos}</td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos por Vencer:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4; color: #856404;">${contratosPorVencer}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Indefinidos:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4;">${contratosIndefinidos}</td>
                            </tr>
                            <tr style="background: #f2f2f2;">
                                <td style="padding: 20px; font-weight: bold; font-size: 18px;">TOTAL CONTRATOS:</td>
                                <td style="padding: 20px; text-align: center; font-weight: bold; font-size: 18px; background: #0078D4; color: white;">${filas.length}</td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 40px; font-size: 12px; color: #666; text-align: center;">
                            <p>Resumen generado al ${FECHA_HOY}</p>
                        </div>
                    </div>
                </div>
            `;
            
            htmlFinal += `
                <div class="page-sheet">
                    <div class="header-small">
                        <strong>PDL VISIONES</strong> | ${TITULO_REPORTE.toUpperCase()} | RESUMEN GLOBAL
                    </div>
                    ${resumenGlobalHTML}
                    ${createFooter(pageNumResumen)}
                </div>
            `;
        }
        
        // Renderizar directamente
        const win = window.open('', '_blank');
        win.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${TITULO_REPORTE}</title>
                <style>
                    @page { size: letter ${orientacion}; margin: 0; }
                    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f0f0; color: #000; }
                    .page-sheet {
                        background: white;
                        width: ${orientacion === 'portrait' ? '215.9mm' : '279.4mm'};
                        height: ${orientacion === 'portrait' ? '279.4mm' : '215.9mm'};
                        margin: 0 auto;
                        padding: 15mm;
                        box-sizing: border-box;
                        display: flex;
                        flex-direction: column;
                        page-break-after: always;
                    }
                    .page-center { justify-content: center; align-items: center; }
                    .portada-box {
                        border: 5px double #0078D4;
                        width: 90%;
                        padding: 40px;
                        text-align: center;
                        margin: auto 0;
                    }
                    .portada-detalles {
                        background: #f9f9f9; padding: 20px; text-align: left;
                        margin: 20px auto; border: 1px solid #eee; border-radius: 8px;
                        width: 75%; font-size: 14px;
                    }
                    .header-small { border-bottom: 2px solid #0078D4; font-size: 10px; padding-bottom: 5px; margin-bottom: 15px; }
                    .table-container { flex-grow: 1; width: 100%; }
                    table.report-table-print {
                        width: 100%;
                        border-collapse: collapse;
                        border: 1px solid black !important;
                    }
                    table.report-table-print th {
                        background: #f2f2f2 !important;
                        border: 1px solid black !important;
                        padding: 6px;
                        font-weight: bold;
                        font-size: 10px;
                        text-align: center;
                    }
                    table.report-table-print td {
                        border: 1px solid black !important;
                        padding: 4px;
                        font-size: 10px;
                    }
                    .footer-dist { 
                        border-top: 2px solid #0078D4; 
                        margin-top: auto; 
                        width: 100%; 
                        padding-top: 5px;
                    }
                    .text-right { text-align: right !important; }
                    .text-center { text-align: center !important; }
                    .text-left { text-align: left !important; }
                    @media print {
                        body { background: none; }
                        .page-sheet { margin: 0; box-shadow: none; }
                    }
                </style>
            </head>
            <body>
                ${htmlFinal}
                <script>
                    window.onload = function() {
                        console.log('✅ Documento generado con', ${TOTAL_PAGINAS + (TIPO_REPORTE === 'vencimiento_contratos' ? 1 : 0)}, 'páginas');
                        setTimeout(() => { 
                            window.print(); 
                            setTimeout(() => window.close(), 500); 
                        }, 700);
                    };
                <\/script>
            </body>
            </html>
        `);
        win.document.close();
        return;
    }

    // --- PÁGINAS DE CONTENIDO CON SUBTOTALES (para reportes con totales) ---
    for (let i = 0; i < filas.length; i += FILAS_POR_PAGINA) {
        const pageNum = Math.floor(i / FILAS_POR_PAGINA) + 2;
        const chunk = filas.slice(i, i + FILAS_POR_PAGINA);
        const esUltimaPagina = (i + FILAS_POR_PAGINA >= filas.length);

        // CALCULAR TOTALES DE ESTA PÁGINA
        let subtotalesPagina = {};
        for (let c = 1; c <= numColumnas; c++) {
            subtotalesPagina[`col${c}`] = 0;
        }
        
        // Variables específicas para plan_vs_real
        let totalPlanPagina = 0;
        let totalRealPagina = 0;
        
        chunk.forEach(fila => {
            const celdas = fila.querySelectorAll('td');
            
            celdas.forEach((celda, index) => {
                const colNum = index + 1;
                const texto = celda.textContent.trim();
                
                // Verificar si esta columna es numérica
                if (esColumnaNumerica(index)) {
                    const valor = limpiarNumero(texto);
                    
                    // Excluir columna de precio en hojas_utilizadas
                    if (TIPO_REPORTE === 'hojas_utilizadas' && colNum === 2) {
                        return;
                    }
                    
                    if (valor !== 0) {
                        subtotalesPagina[`col${colNum}`] += valor;
                        totalesGlobales[`col${colNum}`] += valor;
                        
                        // Acumular para plan_vs_real
                        if (TIPO_REPORTE === 'plan_vs_real') {
                            if (colNum === 2) totalPlanPagina += valor;
                            if (colNum === 3) totalRealPagina += valor;
                        }
                    }
                }
            });
        });

        // Crear fila de subtotal para esta página
        let filaSubtotalPagina = '<tr class="tfoot-total-pagina" style="background: #e8f4f8 !important; border-top: 2px solid #0078D4;">';
        
        for (let c = 1; c <= numColumnas; c++) {
            if (c === 1) {
                if (TIPO_REPORTE === 'auditoria') {
                    filaSubtotalPagina += `<td style="text-align: right; font-weight: bold; font-size: 11px;">OPERACIONES EN ESTA PÁGINA: ${chunk.length}</td>`;
                } else {
                    filaSubtotalPagina += `<td style="text-align: right; font-weight: bold; font-size: 11px;">SUBTOTAL PÁG. ${pageNum-1}:</td>`;
                }
            } else {
                // Verificar si se debe totalizar esta columna
                let debeTotalizar = true;
                
                // Excepciones según tipo de reporte
                if (TIPO_REPORTE === 'hojas_utilizadas' && c === 2) debeTotalizar = false;
                
                if (TIPO_REPORTE === 'auditoria') {
                    // Para auditoría, solo mostrar guiones en las demás columnas
                    filaSubtotalPagina += `<td style="text-align: right; font-weight: bold;">—</td>`;
                }
                else if (esColumnaNumerica(c-1) && debeTotalizar) {
                    let valor = subtotalesPagina[`col${c}`];
                    
                    // Para plan_vs_real, calcular porcentaje de cumplimiento
                    if (TIPO_REPORTE === 'plan_vs_real' && c === 4) {
                        valor = totalPlanPagina > 0 ? (totalRealPagina / totalPlanPagina) * 100 : 0;
                    }
                    
                    // Formatear según el tipo de columna
                    if (COLUMNA_DINERO.includes(c)) {
                        filaSubtotalPagina += `<td style="text-align: right; font-weight: bold;">$ ${formatearNumero(valor, c-1)}</td>`;
                    } else {
                        filaSubtotalPagina += `<td style="text-align: right; font-weight: bold;">${formatearNumero(valor, c-1)}</td>`;
                    }
                } else {
                    filaSubtotalPagina += `<td style="text-align: right; font-weight: bold;">—</td>`;
                }
            }
        }
        filaSubtotalPagina += '</tr>';

        // Si es la última página, agregar también la fila de TOTAL GENERAL DEL REPORTE
        let filaTotalGeneral = '';
        if (esUltimaPagina) {
            filaTotalGeneral = '<tr class="tfoot-total-global" style="background: #0078D4 !important; color: white !important; border-top: 3px solid #000;">';
            
            for (let c = 1; c <= numColumnas; c++) {
                if (c === 1) {
                    if (TIPO_REPORTE === 'auditoria') {
                        filaTotalGeneral += `<td style="text-align: right; font-weight: bold; font-size: 12px; padding: 10px;">TOTAL OPERACIONES: ${filas.length}</td>`;
                    } else {
                        filaTotalGeneral += `<td style="text-align: right; font-weight: bold; font-size: 12px; padding: 10px;">TOTAL GENERAL DEL REPORTE:</td>`;
                    }
                } else {
                    let debeTotalizar = true;
                    
                    if (TIPO_REPORTE === 'hojas_utilizadas' && c === 2) debeTotalizar = false;
                    
                    if (TIPO_REPORTE === 'auditoria') {
                        filaTotalGeneral += `<td style="text-align: right; font-weight: bold;">—</td>`;
                    }
                    else if (esColumnaNumerica(c-1) && debeTotalizar) {
                        let valor = totalesGlobales[`col${c}`];
                        
                        if (TIPO_REPORTE === 'plan_vs_real' && c === 4) {
                            const totalPlanGlobal = totalesGlobales.col2 || 0;
                            const totalRealGlobal = totalesGlobales.col3 || 0;
                            valor = totalPlanGlobal > 0 ? (totalRealGlobal / totalPlanGlobal) * 100 : 0;
                        }
                        
                        if (COLUMNA_DINERO.includes(c)) {
                            filaTotalGeneral += `<td style="text-align: right; font-weight: bold;">$ ${formatearNumero(valor, c-1)}</td>`;
                        } else {
                            filaTotalGeneral += `<td style="text-align: right; font-weight: bold;">${formatearNumero(valor, c-1)}</td>`;
                        }
                    } else {
                        filaTotalGeneral += `<td style="text-align: right; font-weight: bold;">—</td>`;
                    }
                }
            }
            filaTotalGeneral += '</tr>';
        }

        htmlFinal += `
            <div class="page-sheet">
                <div class="header-small">
                    <strong>PDL VISIONES</strong> | ${TITULO_REPORTE.toUpperCase()} | ${FECHA_INICIO} - ${FECHA_FIN}
                </div>
                <div class="table-container">
                    <table class="report-table-print">
                        ${thead}
                        <tbody>
                            ${chunk.map(f => f.outerHTML).join('')}
                            ${filaSubtotalPagina}
                            ${filaTotalGeneral}
                        </tbody>
                    </table>
                </div>
                ${createFooter(pageNum)}
            </div>
        `;
    }

    // --- PÁGINA DE RESUMEN GLOBAL (excepto para reportes sin totales) ---
    if (!esReporteSinTotales || TIPO_REPORTE === 'vencimiento_contratos') {
        const pageNumResumen = TOTAL_PAGINAS;
        
        // Generar tabla de resumen global según el tipo de reporte
        let resumenGlobalHTML = '';

        if (TIPO_REPORTE === 'hojas_utilizadas') {
            resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 30px; width: 90%; background: white;">
                        <h2 style="color: #0078D4; font-size: 22px; margin-bottom: 25px; text-align: center;">RESUMEN GLOBAL DEL REPORTE</h2>
                        
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <tr style="background: #f2f2f2;">
                                <td style="padding: 15px; font-weight: bold;">Total Servicios:</td>
                                <td style="padding: 15px; text-align: right;">${formatearNumero(totalesGlobales.col3, 2)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold;">Total Hojas:</td>
                                <td style="padding: 15px; text-align: right;">${formatearNumero(totalesGlobales.col4, 0)}</td>
                            </tr>
                            <tr style="background: #f2f2f2;">
                                <td style="padding: 15px; font-weight: bold;">Paquetes (500U):</td>
                                <td style="padding: 15px; text-align: right;">${formatearNumero(totalesGlobales.col5, 2)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold;">Paquetes (1000U):</td>
                                <td style="padding: 15px; text-align: right;">${formatearNumero(totalesGlobales.col6, 2)}</td>
                            </tr>
                            <tr style="background: #0078D4; color: white;">
                                <td style="padding: 20px; font-weight: bold; font-size: 18px;">TOTAL FACTURADO:</td>
                                <td style="padding: 20px; text-align: right; font-weight: bold; font-size: 18px;">$ ${formatearNumero(totalesGlobales.col7, 2)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
        }
        else if (TIPO_REPORTE === 'ventas_generales') {
            resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 30px; width: 80%; background: white;">
                        <h2 style="color: #0078D4; font-size: 22px; margin-bottom: 25px; text-align: center;">RESUMEN GLOBAL DE FACTURACIÓN</h2>
                        
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <tr>
                                <td style="padding: 15px; font-weight: bold;">Total Productos:</td>
                                <td style="padding: 15px; text-align: right;">${totalesGlobales.col5}</td>
                            </tr>
                            <tr style="background: #f2f2f2;">
                                <td style="padding: 15px; font-weight: bold;">Facturas Anuladas:</td>
                                <td style="padding: 15px; text-align: right;">${TOTALES_VENTAS?.total_anuladas || 0}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold;">Monto Anulado:</td>
                                <td style="padding: 15px; text-align: right;">$ ${formatearNumero(TOTALES_VENTAS?.monto_anuladas || 0, 2)}</td>
                            </tr>
                            <tr style="background: #0078D4; color: white;">
                                <td style="padding: 20px; font-weight: bold; font-size: 18px;">TOTAL GENERAL:</td>
                                <td style="padding: 20px; text-align: right; font-weight: bold; font-size: 18px;">$ ${formatearNumero(totalesGlobales.col6, 2)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
        }
        else if (TIPO_REPORTE === 'auditoria') {
            resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 40px; width: 70%; background: white; text-align: center;">
                        <h2 style="color: #0078D4; font-size: 24px; margin-bottom: 30px;">RESUMEN DE AUDITORÍA</h2>
                        
                        <div style="margin: 30px 0; font-size: 18px;">
                            <p style="margin: 15px 0;"><strong>Total de Operaciones:</strong> ${filas.length}</p>
                            <p style="margin: 15px 0;"><strong>Período Analizado:</strong> ${FECHA_INICIO} - ${FECHA_FIN}</p>
                        </div>
                        
                        <div style="margin-top: 40px; border-top: 2px solid #0078D4; padding-top: 20px; font-size: 14px; color: #666;">
                            <p>Generado el ${FECHA_HOY}</p>
                        </div>
                    </div>
                </div>
            `;
        }
        else if (TIPO_REPORTE === 'vencimiento_contratos') {
            resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 30px; width: 80%; background: white;">
                        <h2 style="color: #0078D4; font-size: 24px; margin-bottom: 30px; text-align: center;">RESUMEN DE CONTRATOS POR ESTADO</h2>
                        
                        <table style="width: 70%; margin: 0 auto; border-collapse: collapse; font-size: 16px;">
                            <tr style="background: #e8f4f8;">
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Vigentes:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4;">${contratosVigentes}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Vencidos:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4; color: #dc3545;">${contratosVencidos}</td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos por Vencer:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4; color: #856404;">${contratosPorVencer}</td>
                            </tr>
                            <tr>
                                <td style="padding: 15px; font-weight: bold; border-bottom: 1px solid #0078D4;">Contratos Indefinidos:</td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; border-bottom: 1px solid #0078D4;">${contratosIndefinidos}</td>
                            </tr>
                            <tr style="background: #f2f2f2;">
                                <td style="padding: 20px; font-weight: bold; font-size: 18px;">TOTAL CONTRATOS:</td>
                                <td style="padding: 20px; text-align: center; font-weight: bold; font-size: 18px; background: #0078D4; color: white;">${filas.length}</td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 40px; font-size: 12px; color: #666; text-align: center;">
                            <p>Resumen generado al ${FECHA_HOY}</p>
                        </div>
                    </div>
                </div>
            `;
        }
        else {
            // Resumen genérico
            resumenGlobalHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 80%;">
                    <div style="border: 3px double #0078D4; padding: 40px; width: 70%; background: white; text-align: center;">
                        <h2 style="color: #0078D4; font-size: 24px; margin-bottom: 30px;">RESUMEN GLOBAL</h2>
                        
                        <div style="margin: 30px 0; font-size: 18px;">
                            <p style="margin: 15px 0;"><strong>Total de Registros:</strong> ${TOTAL_REGISTROS}</p>
                            ${MOSTRAR_TOTALES ? `<p style="margin: 15px 0;"><strong>Importe Total General:</strong> $ ${TOTAL_GENERAL}</p>` : ''}
                            <p style="margin: 15px 0;"><strong>Período Analizado:</strong> ${FECHA_INICIO} - ${FECHA_FIN}</p>
                        </div>
                        
                        <div style="margin-top: 40px; border-top: 2px solid #0078D4; padding-top: 20px; font-size: 14px; color: #666;">
                            <p>Generado el ${FECHA_HOY}</p>
                        </div>
                    </div>
                </div>
            `;
        }

        htmlFinal += `
            <div class="page-sheet">
                <div class="header-small">
                    <strong>PDL VISIONES</strong> | ${TITULO_REPORTE.toUpperCase()} | RESUMEN GLOBAL
                </div>
                ${resumenGlobalHTML}
                ${createFooter(pageNumResumen)}
            </div>
        `;
    }

    // 7. Renderizado en ventana
    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${TITULO_REPORTE}</title>
            <style>
                @page { size: letter ${orientacion}; margin: 0; }
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f0f0; color: #000; }
                
                .page-sheet {
                    background: white;
                    width: ${orientacion === 'portrait' ? '215.9mm' : '279.4mm'};
                    height: ${orientacion === 'portrait' ? '279.4mm' : '215.9mm'};
                    margin: 0 auto;
                    padding: 15mm;
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                    page-break-after: always;
                }
                .page-center { justify-content: center; align-items: center; }

                .portada-box {
                    border: 5px double #0078D4;
                    width: 90%;
                    padding: 40px;
                    text-align: center;
                    margin: auto 0;
                }
                .portada-detalles {
                    background: #f9f9f9; padding: 20px; text-align: left;
                    margin: 20px auto; border: 1px solid #eee; border-radius: 8px;
                    width: 75%; font-size: 14px;
                }

                .header-small { border-bottom: 2px solid #0078D4; font-size: 10px; padding-bottom: 5px; margin-bottom: 15px; }
                .table-container { flex-grow: 1; width: 100%; }
                
                table.report-table-print {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid black !important;
                }
                table.report-table-print th {
                    background: #f2f2f2 !important;
                    border: 1px solid black !important;
                    padding: 6px;
                    font-weight: bold;
                    font-size: 10px;
                    text-align: center;
                }
                table.report-table-print td {
                    border: 1px solid black !important;
                    padding: 4px;
                    font-size: 10px;
                }
                
                /* Estilo fila subtotal de página */
                .tfoot-total-pagina td {
                    background: #e8f4f8 !important;
                    border-top: 2px solid #0078D4 !important;
                    border-bottom: 1px solid #0078D4 !important;
                    padding: 8px 4px !important;
                    font-weight: bold;
                }
                
                /* Estilo fila total general del reporte (última página) */
                .tfoot-total-global td {
                    background: #0078D4 !important;
                    color: white !important;
                    border-top: 3px solid #000 !important;
                    padding: 10px 4px !important;
                    font-weight: bold;
                    font-size: 12px;
                }

                .footer-dist { 
                    border-top: 2px solid #0078D4; 
                    margin-top: auto; 
                    width: 100%; 
                    padding-top: 5px;
                }
                .text-right { text-align: right !important; }
                .text-center { text-align: center !important; }
                .text-left { text-align: left !important; }

                @media print {
                    body { background: none; }
                    .page-sheet { margin: 0; box-shadow: none; }
                }
            </style>
        </head>
        <body>
            ${htmlFinal}
            <script>
                window.onload = function() {
                    console.log('✅ Documento generado con', ${TOTAL_PAGINAS + (TIPO_REPORTE === 'vencimiento_contratos' ? 0 : 0)}, 'páginas');
                    setTimeout(() => { 
                        window.print(); 
                        setTimeout(() => window.close(), 500); 
                    }, 700);
                };
            <\/script>
        </body>
        </html>
    `);
    win.document.close();
}

    // Configurar validación de fechas
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
            const fechaHasta = document.querySelector('input[name="fecha_fin"]');
            const fechaDesde = document.querySelector('input[name="fecha_inicio"]');
            
 if (fechaHasta && fechaDesde) {
        // Obtener el último día del mes de operaciones desde el campo oculto
        const ultimoDiaInput = document.getElementById('ultimo_dia_operaciones');
        let ultimoDiaOperaciones = '';
        
        if (ultimoDiaInput && ultimoDiaInput.value) {
            ultimoDiaOperaciones = ultimoDiaInput.value;
        } else {
            // Si no hay campo oculto, usar la fecha actual como fallback
            ultimoDiaOperaciones = new Date().toISOString().split('T')[0];
        }
        
        // Establecer la fecha máxima
        fechaHasta.max = ultimoDiaOperaciones;
        fechaDesde.max = ultimoDiaOperaciones;
        
        // Función para formatear fecha para mostrar
        const formatDateForDisplay = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });
        };
        
        const mostrarAdvertenciaFecha = (inputName) => {
    const tipo = inputName === 'fecha_inicio' ? 'inicio' : 'fin';
    
    // Si no hay fecha de operaciones definida, usar la fecha actual
    let fechaOperaciones;
    if (typeof ultimoDiaOperaciones !== 'undefined' && ultimoDiaOperaciones) {
        fechaOperaciones = ultimoDiaOperaciones;
    } else {
        // Simular la lógica de PHP: obtenerFechaCierreSQL() ?? date('Y-m-d')
        // Como no tenemos acceso a SQL desde JS, usamos la fecha actual
        fechaOperaciones = new Date().toISOString().split('T')[0];
    }
    
    // Calcular el primer día del mes
    const primerDiaOperaciones = new Date(fechaOperaciones);
    primerDiaOperaciones.setDate(1);
    
    const primerDiaFormateado = formatDateForDisplay(primerDiaOperaciones);
    const ultimoDiaFormateado = formatDateForDisplay(new Date(fechaOperaciones));
    
    Swal.fire({
        icon: 'warning',
        title: 'Fecha fuera del período',
        html: `
            <div class="text-start">
                <p class="mb-3">La fecha de ${tipo} seleccionada está fuera del período de operaciones permitido.</p>
                <div class="alert alert-info mb-2 p-2">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <strong>Fecha de operaciones:</strong> ${ultimoDiaFormateado}
                    <div class="mt-1">
                        <span class="badge bg-primary me-2">Desde</span> ${primerDiaFormateado}<br>
                        <span class="badge bg-danger me-2">Hasta</span> ${ultimoDiaFormateado}
                    </div>
                </div>
                <p class="mb-0 small text-muted">Por favor, seleccione una fecha dentro del período permitido.</p>
            </div>
        `,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
        confirmButtonColor: '#dc3545',
        background: 'var(--win-bg-secondary, #1f1f1f)',
        color: 'var(--win-text-primary, #fff)',
        customClass: {
            popup: 'border border-warning'
        }
    });
};
        
        // Validar fecha desde
        fechaDesde.addEventListener('change', function() {
            // Establecer mínimo para fecha fin
            fechaHasta.min = this.value;
            
            // Validar que no exceda el máximo
            if (new Date(this.value) > new Date(ultimoDiaOperaciones)) {
                this.value = ultimoDiaOperaciones;
                mostrarAdvertenciaFecha('fecha_inicio');
            }
            
            // Si fecha fin es menor que fecha inicio, ajustarla
            if (fechaHasta.value && new Date(fechaHasta.value) < new Date(this.value)) {
                fechaHasta.value = this.value;
            }
        });
        
        // Validar fecha hasta
        fechaHasta.addEventListener('change', function() {
            // Validar que no exceda el máximo
            if (new Date(this.value) > new Date(ultimoDiaOperaciones)) {
                this.value = ultimoDiaOperaciones;
                mostrarAdvertenciaFecha('fecha_fin');
            }
            
            // Validar que no sea menor que fecha inicio
            if (fechaDesde.value && new Date(this.value) < new Date(fechaDesde.value)) {
                this.value = fechaDesde.value;
            }
        });
        
        // También puedes agregar validación al cargar la página
        const validarFechasExistentes = () => {
            if (fechaDesde.value && new Date(fechaDesde.value) > new Date(ultimoDiaOperaciones)) {
                fechaDesde.value = ultimoDiaOperaciones;
            }
            
            if (fechaHasta.value && new Date(fechaHasta.value) > new Date(ultimoDiaOperaciones)) {
                fechaHasta.value = ultimoDiaOperaciones;
            }
            
            if (fechaDesde.value && fechaHasta.value && new Date(fechaHasta.value) < new Date(fechaDesde.value)) {
                fechaHasta.value = fechaDesde.value;
            }
        };
        
        // Ejecutar validación inicial
        setTimeout(validarFechasExistentes, 100);
    }
        });
    </script>
<?php if (isset($mostrar_alerta_sin_diferencias) && $mostrar_alerta_sin_diferencias === true): ?>
<script>
    // Esperar a que la página esté completamente cargada
    window.addEventListener('load', function() {
        // Pequeño delay para asegurar que SweetAlert esté listo
        setTimeout(function() {
            // Determinar el tema actual
            const temaActual = document.documentElement.getAttribute('data-theme') || 'dark';
            const esTemaOscuro = temaActual === 'dark';
            
            Swal.fire({
                title: '¡CUADRE PERFECTO!',
                html: `
                    <div style="text-align: center; padding: 10px;">
                        <i class="fas fa-check-circle fa-4x mb-3" style="color: #28a745;"></i>
                        <p style="font-size: 20px; font-weight: bold; margin-bottom: 15px; color: ${esTemaOscuro ? '#fff' : '#000'}">
                            NO EXISTEN DIFERENCIAS
                        </p>
                        <div style="background: ${esTemaOscuro ? '#2d2d2d' : '#f8f9fa'}; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                            <p style="margin: 5px 0; font-size: 16px; color: ${esTemaOscuro ? '#e0e0e0' : '#333'}">
                                <strong>Período analizado:</strong><br>
                                Desde: <span style="color: #0078d4;"><?= date("d/m/Y", strtotime($fecha_inicio)) ?></span><br>
                                Hasta: <span style="color: #0078d4;"><?= date("d/m/Y", strtotime($fecha_fin)) ?></span>
                            </p>
                        </div>
                        <p style="color: ${esTemaOscuro ? '#a6a6a6' : '#666'}; font-size: 14px;">
                            Todas las facturas coinciden con sus detalles correspondientes.
                        </p>
                    </div>
                `,
                icon: 'success',
                iconColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check-circle me-2"></i>Entendido',
                confirmButtonColor: '#28a745',
                showCloseButton: true,
                allowOutsideClick: true,
                allowEscapeKey: true,
                width: '500px',
                backdrop: esTemaOscuro ? 'rgba(0,0,0,0.7)' : 'rgba(0,0,0,0.4)',
                background: esTemaOscuro ? '#1f1f1f' : '#ffffff',
                color: esTemaOscuro ? '#ffffff' : '#000000',
                customClass: {
                    popup: esTemaOscuro ? 'sweetalert-dark' : 'sweetalert-light',
                    title: esTemaOscuro ? 'sweetalert-title-dark' : 'sweetalert-title-light',
                    htmlContainer: esTemaOscuro ? 'sweetalert-html-dark' : 'sweetalert-html-light',
                    confirmButton: 'sweetalert-confirm-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Alerta de diferencias cerrada');
                }
            });
        }, 300);
    });
</script>
<?php endif; ?>
<?php
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
?>
</body>
</html>