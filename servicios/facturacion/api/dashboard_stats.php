<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Mexico_City'); 
} else {
    date_default_timezone_set('America/Mexico_City'); 
}

// Variable global con la fecha
$GLOBALS['fecha_inicio_operaciones'] = null;

// Función para obtener la fecha de cierre (con caché)
function obtenerFechaInicioOperaciones() {
    static $fecha_cache = null;
    
    if ($fecha_cache === null) {
        try {
            $db = Database::getConnection();
            $sql = "SELECT fecha_inicio_operaciones 
                    FROM configuracion_sistema 
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch();
            
            $fecha_cache = $resultado['fecha_inicio_operaciones'] ?? null;
            $GLOBALS['fecha_inicio_operaciones'] = $fecha_cache;
        } catch (Exception $e) {
            $fecha_cache = null;
            $GLOBALS['fecha_inicio_operaciones'] = null;
        }
    }
    
    return $fecha_cache;
}

// Función para obtener año de cierre
function obtenerAnioCierreOperaciones() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('Y', strtotime($fecha)) : date('Y');
}

// Función para obtener mes de cierre
function obtenerMesCierreOperaciones() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('n', strtotime($fecha)) : date('n');
}

// Función para obtener fecha SQL
function obtenerFechaCierreSQL() {
    $fecha = obtenerFechaInicioOperaciones();
    return $fecha ? date('Y-m-d', strtotime($fecha)) : date('Y-m-d');
}

try {
    $db = Database::getConnection();
    
    $stats = [];
    
    // =============================================================================
    // 1. CONFIGURACIÓN DE FECHA BASADA EN CIERRE DE OPERACIONES
    // =============================================================================
    
    $mes_cierre_num = obtenerMesCierreOperaciones();
    $anio_cierre_num = obtenerAnioCierreOperaciones();
    $fecha_op_raw = obtenerFechaCierreSQL() ?? date('Y-m-d');
    $timestamp_op = strtotime($fecha_op_raw);
    
    $anio_actual = date('Y', $timestamp_op);
    $mes_actual = date('n', $timestamp_op);
    $dia_actual = date('j', $timestamp_op);
    $total_dias_mes = date('t', $timestamp_op);
    
    // =============================================================================
    // 2. ESTADÍSTICAS PRINCIPALES (TARJETAS SUPERIORES)
    // =============================================================================
    
    // --- FACTURAS DEL MES ---
    $sql_facturas_mes = "SELECT COUNT(*) as total FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes 
                         AND YEAR(fecha_emision) = :anio";
    $stmt = $db->prepare($sql_facturas_mes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['facturas_mes'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- INGRESOS DEL MES ---
    $sql_ingresos_mes = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes 
                         AND YEAR(fecha_emision) = :anio
                         AND estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')";
    $stmt = $db->prepare($sql_ingresos_mes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $ingresos_mes = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stats['ingresos_mes'] = $ingresos_mes;
    
    // --- VARIACIÓN CON MES ANTERIOR ---
    $mes_anterior = $mes_actual == 1 ? 12 : $mes_actual - 1;
    $anio_anterior = $mes_actual == 1 ? $anio_actual - 1 : $anio_actual;
    
    $sql_ingresos_mes_anterior = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes
                                   AND YEAR(fecha_emision) = :anio
                                   AND estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')";
    $stmt = $db->prepare($sql_ingresos_mes_anterior);
    $stmt->execute(['mes' => $mes_anterior, 'anio' => $anio_anterior]);
    $ingresos_mes_anterior = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stats['variacion_ingresos'] = $ingresos_mes_anterior > 0 ? 
        (($ingresos_mes - $ingresos_mes_anterior) / $ingresos_mes_anterior) * 100 : 0;
    
    // --- CLIENTES ACTIVOS ---
    $sql_clientes_activos = "SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 1";
    $stmt = $db->prepare($sql_clientes_activos);
    $stmt->execute();
    $stats['clientes_activos'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- CLIENTES INACTIVOS ---
    $sql_clientes_inactivos = "SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 0";
    $stmt = $db->prepare($sql_clientes_inactivos);
    $stmt->execute();
    $stats['clientes_inactivos'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- CLIENTES TODOS ---
    $sql_clientes_todos = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_todos);
    $stmt->execute();
    $stats['clientes_todos'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- NUEVOS CLIENTES ESTE MES ---
    $sql_nuevos_clientes = "SELECT COUNT(*) as total FROM clasif_clientes 
                            WHERE MONTH(fechaRegistro) = :mes 
                            AND YEAR(fechaRegistro) = :anio";
    $stmt = $db->prepare($sql_nuevos_clientes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['nuevos_clientes'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- SERVICIOS ---
    $sql_servicios_count = "SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 1";
    $stmt = $db->prepare($sql_servicios_count);
    $stmt->execute();
    $stats['servicios_count'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_servicios_inactivos = "SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 0";
    $stmt = $db->prepare($sql_servicios_inactivos);
    $stmt->execute();
    $stats['servicios_inactivos'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- CATEGORÍAS ---
    $sql_categorias_count = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_count);
    $stmt->execute();
    $stats['categorias_count'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cat_activas = "SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 1";
    $stmt_cat_act = $db->prepare($sql_cat_activas);
    $stmt_cat_act->execute();
    $stats['categorias_activas'] = (int)$stmt_cat_act->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cat_inactivas = "SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 0";
    $stmt_cat_inact = $db->prepare($sql_cat_inactivas);
    $stmt_cat_inact->execute();
    $stats['categorias_inactivas'] = (int)$stmt_cat_inact->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- USUARIOS ---
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $stats['usuarios_total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_usuarios_activos = "SELECT COUNT(*) as total FROM clasif_usuarios WHERE activo = 1";
    $stmt = $db->prepare($sql_usuarios_activos);
    $stmt->execute();
    $stats['usuarios_activos'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // =============================================================================
    // 3. ESTADÍSTICAS DE FACTURACIÓN AVANZADA
    // =============================================================================
    
    // --- FACTURAS HOY COTIZADAS ---
    $sql_hoy_contabilizadas = "SELECT COUNT(*) as total FROM tbl_fact 
                               WHERE DATE(fecha_emision) = :fecha_op";
    $stmt_hoy = $db->prepare($sql_hoy_contabilizadas);
    $stmt_hoy->execute(['fecha_op' => $fecha_op_raw]);
    $stats['hoy_contabilizadas'] = (int)$stmt_hoy->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- FACTURAS PAGADAS DEL MES ---
    $sql_mes_pagadas = "SELECT COUNT(*) as total FROM tbl_fact 
                        WHERE MONTH(fecha_emision) = :mes 
                          AND YEAR(fecha_emision) = :anio
                          AND estado = 'PAGADA'
                          AND fecha_pago IS NOT NULL";
    $stmt_pagadas = $db->prepare($sql_mes_pagadas);
    $stmt_pagadas->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['mes_pagadas'] = (int)$stmt_pagadas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- TOTAL COTIZADO HOY ---
    $sql_total_cotizado_hoy = "SELECT COALESCE(SUM(total_general), 0) as total 
                               FROM tbl_fact 
                               WHERE DATE(fecha_emision) = :fecha_op";
    $stmt_cotizado = $db->prepare($sql_total_cotizado_hoy);
    $stmt_cotizado->execute(['fecha_op' => $fecha_op_raw]);
    $stats['total_cotizado_hoy'] = (float)$stmt_cotizado->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- INGRESOS REALES DEL MES ---
    $sql_ingresos_reales_mes = "SELECT COALESCE(SUM(total_general), 0) as total 
                                FROM tbl_fact 
                                WHERE MONTH(fecha_emision) = :mes 
                                  AND YEAR(fecha_emision) = :anio
                                  AND estado != 'ANULADA'";
    $stmt_reales = $db->prepare($sql_ingresos_reales_mes);
    $stmt_reales->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['ingresos_reales_mes'] = (float)$stmt_reales->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- TOTAL FACTURADO MES ---
    $sql_total_facturado_mes = "SELECT COALESCE(SUM(total_general), 0) as total_facturado
                                FROM tbl_fact 
                                WHERE MONTH(fecha_emision) = :mes
                                  AND YEAR(fecha_emision) = :anio
                                  AND estado != 'ANULADA'";
    $stmt_fact_mes = $db->prepare($sql_total_facturado_mes);
    $stmt_fact_mes->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['total_facturado_mes'] = (float)$stmt_fact_mes->fetch(PDO::FETCH_ASSOC)['total_facturado'];
    
    // --- TOTAL PAGADO MES ---
    $sql_total_pagado_mes = "SELECT COALESCE(SUM(total_general), 0) as total_pagado
                             FROM tbl_fact 
                             WHERE MONTH(fecha_emision) = :mes
                               AND YEAR(fecha_emision) = :anio
                               AND (estado = 'PAGADA' OR (estado = 'CERRADA' AND fecha_pago IS NOT NULL AND fecha_pago != ''))";
    $stmt_pag_mes = $db->prepare($sql_total_pagado_mes);
    $stmt_pag_mes->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $stats['total_pagado_mes'] = (float)$stmt_pag_mes->fetch(PDO::FETCH_ASSOC)['total_pagado'];
    
    // --- ESTADOS DE FACTURAS DEL MES ---
    // Contabilizadas
    $sql_contabilizadas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                               FROM tbl_fact 
                               WHERE MONTH(fecha_contabilizacion) = :mes 
                                 AND YEAR(fecha_contabilizacion) = :anio
                                 AND estado = 'CONTABILIZADA'";
    $stmt_cont = $db->prepare($sql_contabilizadas_mes);
    $stmt_cont->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $contabilizadas = $stmt_cont->fetch(PDO::FETCH_ASSOC);
    $stats['contabilizadas_cantidad'] = (int)$contabilizadas['cantidad'];
    $stats['contabilizadas_importe'] = (float)$contabilizadas['importe'];
    
    // Pagadas
    $sql_pagadas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                        FROM tbl_fact 
                        WHERE MONTH(fecha_contabilizacion) = :mes 
                          AND YEAR(fecha_contabilizacion) = :anio
                          AND estado = 'PAGADA'";
    $stmt_pag = $db->prepare($sql_pagadas_mes);
    $stmt_pag->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $pagadas = $stmt_pag->fetch(PDO::FETCH_ASSOC);
    $stats['pagadas_cantidad'] = (int)$pagadas['cantidad'];
    $stats['pagadas_importe'] = (float)$pagadas['importe'];
    
    // Pendientes
    $sql_pendientes_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                           FROM tbl_fact 
                           WHERE MONTH(fecha_emision) = :mes 
                             AND YEAR(fecha_emision) = :anio
                             AND estado = 'PENDIENTE'";
    $stmt_pen = $db->prepare($sql_pendientes_mes);
    $stmt_pen->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $pendientes = $stmt_pen->fetch(PDO::FETCH_ASSOC);
    $stats['pendientes_cantidad'] = (int)$pendientes['cantidad'];
    $stats['pendientes_importe'] = (float)$pendientes['importe'];
    
    // Anuladas
    $sql_anuladas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                         FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes 
                           AND YEAR(fecha_emision) = :anio
                           AND estado = 'ANULADA'";
    $stmt_anu = $db->prepare($sql_anuladas_mes);
    $stmt_anu->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $anuladas = $stmt_anu->fetch(PDO::FETCH_ASSOC);
    $stats['anuladas_cantidad'] = (int)$anuladas['cantidad'];
    $stats['anuladas_importe'] = (float)$anuladas['importe'];
    
    // =============================================================================
    // 4. ESTADÍSTICAS ANUALES (para la tarjeta grande)
    // =============================================================================
    
    // --- INGRESOS DEL AÑO ---
    $sql_ingresos_anio = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio
                          AND estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')";
    $stmt = $db->prepare($sql_ingresos_anio);
    $stmt->execute(['anio' => $anio_actual]);
    $stats['ingresos_anio'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- FACTURAS DEL AÑO ---
    $sql_facturas_anio = "SELECT COUNT(*) as total FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio";
    $stmt = $db->prepare($sql_facturas_anio);
    $stmt->execute(['anio' => $anio_actual]);
    $stats['facturas_anio'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- FACTURAS TOTALES BD ---
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $stats['facturas_total'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- INGRESOS TRIMESTRE ACTUAL ---
    $trimestre_actual = ceil($mes_actual / 3);
    $sql_ingresos_trimestre = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                               WHERE QUARTER(fecha_emision) = :trimestre
                               AND YEAR(fecha_emision) = :anio
                               AND estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')";
    $stmt = $db->prepare($sql_ingresos_trimestre);
    $stmt->execute(['trimestre' => $trimestre_actual, 'anio' => $anio_actual]);
    $stats['ingresos_trimestre'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- INGRESOS DEL AÑO ANTERIOR (para variación) ---
    $anio_anterior_num = $anio_actual - 1;
    $sql_ingresos_anio_anterior = "SELECT COALESCE(SUM(total_general), 0) as total 
                                   FROM tbl_fact 
                                   WHERE YEAR(fecha_emision) = :anio 
                                   AND estado IN ('CONTABILIZADA','PAGADA', 'CERRADA')";
    $stmt = $db->prepare($sql_ingresos_anio_anterior);
    $stmt->execute(['anio' => $anio_anterior_num]);
    $stats['ingresos_anio_anterior'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // --- ESTADOS ANUALES (resumen) ---
    $sql_estados_anual = "SELECT 
                            estado,
                            COUNT(*) as cantidad,
                            COALESCE(SUM(total_general), 0) as total
                          FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio_emision
                          AND (
                              estado IN ('CONTABILIZADA', 'PAGADA', 'CERRADA')
                              OR 
                              ((estado = 'CONTABILIZADA' OR estado = 'PAGADA' OR estado = 'CERRADA') AND YEAR(fecha_contabilizacion) = :anio_contable)
                          )
                          GROUP BY estado";
    $stmt = $db->prepare($sql_estados_anual);
    $stmt->execute([
        'anio_emision' => $anio_actual,
        'anio_contable' => $anio_actual
    ]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['estados_anual'] = [];
    foreach ($resultados as $row) {
        $stats['estados_anual'][$row['estado']] = [
            'cantidad' => (int)$row['cantidad'],
            'total' => (float)$row['total']
        ];
    }
    
    // --- HOY CONTABILIZADAS + PAGADAS ---
    $sql_hoy_contab_pagadas = "SELECT 
                                COUNT(*) as cantidad,
                                COALESCE(SUM(total_general), 0) as total
                               FROM tbl_fact 
                               WHERE DATE(fecha_contabilizacion) = :fecha_op 
                               AND estado IN ('CONTABILIZADA', 'PAGADA')";
    $stmt_hoy = $db->prepare($sql_hoy_contab_pagadas);
    $stmt_hoy->execute(['fecha_op' => $fecha_op_raw]);
    $hoy_result = $stmt_hoy->fetch(PDO::FETCH_ASSOC);
    $stats['hoy_contab_pagadas_cantidad'] = (int)$hoy_result['cantidad'];
    $stats['hoy_contab_pagadas_total'] = (float)$hoy_result['total'];
    
    // --- TOTAL FACTURADO ANUAL (excluyendo anuladas) ---
    $sql_total_facturado_anual = "SELECT COALESCE(SUM(total_general), 0) as total_facturado
                                   FROM tbl_fact 
                                   WHERE YEAR(fecha_emision) = :anio
                                   AND estado != 'ANULADA'";
    $stmt_total = $db->prepare($sql_total_facturado_anual);
    $stmt_total->execute(['anio' => $anio_actual]);
    $stats['total_facturado_anual'] = (float)$stmt_total->fetch(PDO::FETCH_ASSOC)['total_facturado'];
    
    // --- CERRADAS CON PAGO (para total pagado real) ---
    $sql_cerradas_con_pago = "SELECT COALESCE(SUM(total_general), 0) as total
                              FROM tbl_fact 
                              WHERE estado = 'CERRADA'
                              AND fecha_pago IS NOT NULL 
                              AND fecha_pago != ''
                              AND YEAR(fecha_contabilizacion) = :anio";
    $stmt = $db->prepare($sql_cerradas_con_pago);
    $stmt->execute(['anio' => $anio_actual]);
    $stats['cerradas_con_pago_total'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // =============================================================================
    // 5. ÚLTIMA FACTURA (para la tarjeta superior)
    // =============================================================================
    
    $sql_last_invoice = "SELECT f.*, c.nombre as cliente_nombre 
                         FROM tbl_fact f 
                         LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                         ORDER BY f.fecha_emision DESC, f.id DESC LIMIT 1";
    $stmt = $db->prepare($sql_last_invoice);
    $stmt->execute();
    $last_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_invoice) {
        $stats['last_invoice'] = [
            'id' => $last_invoice['id'],
            'numero' => $last_invoice['no_fact'],
            'fecha' => date('d/m/Y', strtotime($last_invoice['fecha_emision'])),
            'cliente' => $last_invoice['cliente_nombre'] ?? 'Cliente no especificado',
            'importe' => (float)$last_invoice['total_general']
        ];
    } else {
        $stats['last_invoice'] = null;
    }
    
    // =============================================================================
    // 6. METADATOS DE CONSULTA
    // =============================================================================
    
    $stats['fecha_consulta'] = date('Y-m-d H:i:s');
    $stats['mes_actual'] = $mes_actual;
    $stats['mes_nombre'] = obtenerNombreMes($mes_actual);
    $stats['anio_actual'] = $anio_actual;
    $stats['trimestre_actual'] = $trimestre_actual;
    $stats['dia_actual'] = $dia_actual;
    $stats['total_dias_mes'] = $total_dias_mes;
    $stats['fecha_cierre'] = $fecha_op_raw;
    $stats['actualizado'] = true;
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al obtener estadísticas', 
        'mensaje' => $e->getMessage()
    ]);
}

// Función auxiliar para obtener nombre del mes
function obtenerNombreMes($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$mes] ?? 'Desconocido';
}
?>