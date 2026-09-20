<?php

// dashboard.php - Versión Windows 11 Dark Mode con contenido existente
require_once 'config/header.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}


if (!isset($lastInvoiceInfo)) {
    $lastInvoiceInfo = getLastInvoiceInfo();
}

// Obtener estado del sidebar de la sesión (compatibilidad)
$sidebar_colapsado = $_SESSION['sidebar_colapsado'] ?? false; // Valor por defecto

// Inicializar variables
$facturas_mes = 0;
$ingresos_mes = 0;
$clientes_activos = 0;
$clientes_inactivos = 0; 
$cant_categ = 0;
$cant_Users = 0;
$cant_UsersAct = 0;
$clientes_todos = 0;
$servicios_count = 0;
$servicios_inactivos = 0;
$variacion_ingresos = 0;
$nuevos_clientes = 0;
$ultimas_facturas = [];
$ultimas_cat = [];
$ultimos_clientes = [];
$ultimos_servicios = [];
$actividad_reciente = [];
$ingresos_anual = [];
$servicios_solicitados = [];
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$datos_mensuales = array_fill(0, 12, 0);
$datos_trimestrales = array_fill(0, 4, 0);
$trimestres = ['Q1 (Ene-Mar)', 'Q2 (Abr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dic)'];
$facturas_anual = 0;
$ingresos_anual_total = 0;
$ingresos_trimestre = 0;

$trimestre_actual = ceil(date('n') / 3);

// Definimos los nombres
$nombres_trimestres = [
    1 => '1er',
    2 => '2do',
    3 => '3er',
    4 => '4to'
];

// Obtenemos el valor final trimestre
$trimestre_actual_Completo = $nombres_trimestres[$trimestre_actual];

// =============================================================================
// 1. CONFIGURACIÓN DE FECHA BASADA EN CIERRE DE OPERACIONES
// =============================================================================

// Obtener la fecha maestra de la base de datos
$mes_cierre_num = obtenerMesCierreOperaciones();
$anio_cierre_num = obtenerAnioCierreOperaciones();

// Calcular el ÚLTIMO DÍA del mes de cierre
$total_dias_mes = cal_days_in_month(CAL_GREGORIAN, $mes_cierre_num, $anio_cierre_num);

// Variables de tiempo MAESTRAS (usando el último día del mes de cierre)
$anio_actual = $anio_cierre_num;
$mes_actual  = $mes_cierre_num;
$dia_actual  = $total_dias_mes;  // ← Último día del mes (28,29,30,31)
$total_dias_mes = $total_dias_mes;

// Fecha para queries SQL (formato YYYY-MM-DD)
$fecha_op_raw = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_actual);
$timestamp_op = strtotime($fecha_op_raw);
$hoy = $fecha_op_raw;
$timestamp_combinado = $timestamp_op;

$dia_semana = date('N', $timestamp_combinado);

// Calcular Trimestre de la fecha de cierre
$trimestre_actual = ceil($mes_actual / 3);


// Arrays de textos
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$trimestres = ['Q1 (Ene-Mar)', 'Q2 (Abr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dic)'];
$nombres_trimestres = [1 => '1er', 2 => '2do', 3 => '3er', 4 => '4to'];

$trimestre_actual_Completo = $nombres_trimestres[$trimestre_actual];

// Variables para gráficas
$datos_mensuales = array_fill(0, 12, 0);
$datos_trimestrales = array_fill(0, 4, 0);
$ingresos_tabla_mensual = [];
$ingresos_tabla_trimestral = [];
$ingresos_tabla_diario = [];

// =============================================================================
// 2. OBTENCIÓN DE DATOS (SQL PARAMETRIZADO)
// =============================================================================

try {
    $db = Database::getConnection();
    
    // Datos de Usuario
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    // Filtro de Año (Por defecto el año de la fecha de cierre, NO el del servidor)
    $sql_anios_facturas = "SELECT DISTINCT YEAR(fecha_emision) as anio FROM tbl_fact ORDER BY anio DESC";
    $stmt_anios = $db->prepare($sql_anios_facturas);
    $stmt_anios->execute();
    $anios_disponibles = $stmt_anios->fetchAll(PDO::FETCH_ASSOC);

    $anio_seleccionado = $_GET['anio'] ?? $anio_actual; // <--- Sincronizado

    // --- ESTADÍSTICAS GENERALES ---
    
    // 1. Facturas del Mes (Basado en fecha cierre)
    $sql_facturas_mes = "SELECT COUNT(*) as total FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes 
                         AND YEAR(fecha_emision) = :anio";
    $stmt = $db->prepare($sql_facturas_mes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $facturas_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 2. Facturas del Año (Basado en fecha cierre)
    $sql_facturas_anual = "SELECT COUNT(*) as total FROM tbl_fact 
                           WHERE YEAR(fecha_emision) = :anio";
    $stmt = $db->prepare($sql_facturas_anual);
    $stmt->execute(['anio' => $anio_actual]);
    $facturas_anual = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total global (no depende de fecha)
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->query($sql_facturas_total);
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 3. Ingresos del Mes (Contabilizadas en el mes de cierre)
    $sql_ingresos_mes = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes 
                         AND YEAR(fecha_emision) = :anio
                         AND estado IN ('CONTABILIZADA', 'PAGADA')";
    $stmt = $db->prepare($sql_ingresos_mes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $ingresos_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 4. Ingresos del Año Total
    $sql_ingresos_anual_total = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                                 WHERE YEAR(fecha_emision) = :anio
                                 AND !estado = 'ANULADA'";
    $stmt = $db->prepare($sql_ingresos_anual_total);
    $stmt->execute(['anio' => $anio_actual]);
    $ingresos_anual_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 5. Ingresos Trimestre
    $sql_ingresos_trimestre = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                               WHERE QUARTER(fecha_emision) = :trimestre
                               AND YEAR(fecha_emision) = :anio
                               AND !estado = 'ANULADA'";
    $stmt = $db->prepare($sql_ingresos_trimestre);
    $stmt->execute(['trimestre' => $trimestre_actual, 'anio' => $anio_actual]);
    $ingresos_trimestre = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 6. Variación con Mes Anterior (Calculado desde fecha cierre)
    // Calculamos fecha mes anterior en PHP
    $fecha_anterior_php = date('Y-m-d', strtotime("-1 month", $timestamp_op));
    $mes_anterior = date('n', strtotime($fecha_anterior_php));
    $anio_anterior = date('Y', strtotime($fecha_anterior_php));

    $sql_ingresos_mes_anterior = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes
                                   AND YEAR(fecha_emision) = :anio
                                   AND !estado = 'ANULADA'";
    $stmt = $db->prepare($sql_ingresos_mes_anterior);
    $stmt->execute(['mes' => $mes_anterior, 'anio' => $anio_anterior]);
    $ingresos_mes_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $variacion_ingresos = $ingresos_mes_anterior > 0 ? 
        (($ingresos_mes - $ingresos_mes_anterior) / $ingresos_mes_anterior) * 100 : 0;

    // --- CONTEOS GENERALES ---
    $cant_UsersAct = $db->query("SELECT COUNT(*) FROM clasif_usuarios WHERE activo = 1")->fetchColumn();
    $cant_Users = $db->query("SELECT COUNT(*) FROM clasif_usuarios")->fetchColumn();
    $clientes_todos = $db->query("SELECT COUNT(*) FROM clasif_clientes")->fetchColumn();
    $clientes_activos = $db->query("SELECT COUNT(*) FROM clasif_clientes WHERE activo = 1")->fetchColumn();
    $clientes_inactivos = $db->query("SELECT COUNT(*) FROM clasif_clientes WHERE activo = 0")->fetchColumn();
    $cant_categ = $db->query("SELECT COUNT(*) FROM clasif_cat_de_serv")->fetchColumn();
    $servicios_count = $db->query("SELECT COUNT(*) FROM clasif_serv WHERE activo = 1")->fetchColumn();
    $servicios_inactivos = $db->query("SELECT COUNT(*) FROM clasif_serv WHERE activo = 0")->fetchColumn();

    // 7. Nuevos Clientes (En el mes de cierre)
    $sql_nuevos_clientes = "SELECT COUNT(*) as total FROM clasif_clientes 
                            WHERE MONTH(fechaRegistro) = :mes 
                            AND YEAR(fechaRegistro) = :anio";
    $stmt = $db->prepare($sql_nuevos_clientes);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $nuevos_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 8. Gráficos Anuales
    $sql_ingresos_anual = "SELECT 
                            MONTH(fecha_emision) as mes,
                            COALESCE(SUM(total_general), 0) as total,
                            COUNT(*) as cantidad
                           FROM tbl_fact 
                           WHERE YEAR(fecha_emision) = :anio
                           AND !estado = 'ANULADA'
                           GROUP BY MONTH(fecha_emision)";
    $stmt = $db->prepare($sql_ingresos_anual);
    $stmt->execute(['anio' => $anio_actual]);
    $ingresos_anual = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ingresos_anual as $dato) {
        $datos_mensuales[$dato['mes'] - 1] = floatval($dato['total']);
        $ingresos_tabla_mensual[] = [
            'mes' => $meses_completos[$dato['mes'] - 1],
            'total' => floatval($dato['total']),
            'cantidad' => intval($dato['cantidad'])
        ];
    }

// =============================================================================
// OBTENER DATOS DE LOS ÚLTIMOS 5 AÑOS PARA COMPARACIÓN ANUAL
// =============================================================================

// Array para almacenar ingresos de los últimos 5 años
$ingresos_ultimos_5_anios = [];
$anios_comparacion = [];

// Calcular los últimos 5 años (incluyendo el actual)
$anio_inicio_comparacion = $anio_actual - 4; // Últimos 5 años

for ($anio = $anio_inicio_comparacion; $anio <= $anio_actual; $anio++) {
    $anios_comparacion[] = $anio;
    
    $sql_ingresos_anio = "SELECT COALESCE(SUM(total_general), 0) as total 
                          FROM tbl_fact 
                          WHERE YEAR(fecha_emision) = :anio
                          AND !estado = 'ANULADA'";
    
    $stmt = $db->prepare($sql_ingresos_anio);
    $stmt->execute(['anio' => $anio]);
    $ingresos_ultimos_5_anios[$anio] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Preparar datos para JavaScript
$anios_comparacion_js = json_encode($anios_comparacion);
$ingresos_comparacion_js = json_encode(array_values($ingresos_ultimos_5_anios));

    // 9. Gráficos Trimestrales
    $sql_ingresos_trimestral = "SELECT 
        QUARTER(fecha_emision) as trimestre,
        COALESCE(SUM(total_general), 0) as total
    FROM tbl_fact 
    WHERE YEAR(fecha_emision) = :anio
    AND !estado = 'ANULADA'
    GROUP BY QUARTER(fecha_emision)";
    
    $stmt = $db->prepare($sql_ingresos_trimestral);
    $stmt->execute(['anio' => $anio_actual]);
    $ingresos_trimestral_res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ingresos_trimestral_res as $dato) {
        $datos_trimestrales[$dato['trimestre'] - 1] = floatval($dato['total']);
        $ingresos_tabla_trimestral[] = [
            'trimestre' => $trimestres[$dato['trimestre'] - 1],
            'total' => floatval($dato['total'])
        ];
    }

    // 10. Datos Diarios (Mes de cierre)
    $datos_diarios = [];
    $cantidades_diarias = [];
    $dias_del_mes = $total_dias_mes; // Usamos el calculado al inicio

    for ($dia = 1; $dia <= $dias_del_mes; $dia++) {
        $datos_diarios[$dia] = 0;
        $cantidades_diarias[$dia] = 0;
    }

    $sql_ingresos_diarios = "SELECT 
    DAY(fecha_emision) as dia,
    COALESCE(SUM(total_general), 0) as total,
    COUNT(*) as cantidad  
    FROM tbl_fact 
    WHERE MONTH(fecha_emision) = :mes
    AND YEAR(fecha_emision) = :anio
    AND estado NOT IN ('ANULADA')
    GROUP BY DAY(fecha_emision)";

    $stmt = $db->prepare($sql_ingresos_diarios);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $ingresos_diarios_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ingresos_diarios_result as $dato) {
        $datos_diarios[$dato['dia']] = floatval($dato['total']);
        $cantidades_diarias[$dato['dia']] = intval($dato['cantidad']);
    }

    // Preparar datos para JS (Solo hasta el día "actual" de la fecha de cierre)
    $datos_diarios_grafico = [];
    $labels_diarios = [];
    $dias_con_datos = 0;
    
    // Iteramos hasta el día de la fecha de cierre (no fecha real)
    for ($i = 1; $i <= $dia_actual; $i++) {
        $val = $datos_diarios[$i] ?? 0;
        $datos_diarios_grafico[] = $val;
        $labels_diarios[] = 'Día ' . $i;
        
        if ($val > 0) $dias_con_datos++;
        
        $ingresos_tabla_diario[] = [
            'dia' => $i,
            'fecha' => date('d/m', mktime(0, 0, 0, $mes_actual, $i, $anio_actual)),
            'total' => $val
        ];
    }
    
    $porcentaje_datos = $dia_actual > 0 ? round(($dias_con_datos / $dia_actual) * 100) : 0;

    // --- ESTADÍSTICAS FINANCIERAS RESUMEN ---
    
    // Cotizado HOY (Fecha Cierre)
    $sql_total_cotizado_hoy = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact WHERE DATE(fecha_emision) = :fecha_op";
    // Ingresos Reales Mes
    $sql_ingresos_reales_mes = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact 
                            WHERE MONTH(fecha_emision) = :mes 
                            AND YEAR(fecha_emision) = :anio
                            AND  estado != 'ANULADA'";
    // Total Facturado Mes
    $sql_total_facturado_mes = "SELECT COALESCE(SUM(total_general), 0) as total_facturado
                            FROM tbl_fact 
                            WHERE MONTH(fecha_emision) = :mes
                            AND YEAR(fecha_emision) = :anio
                            AND estado != 'ANULADA'";
    // Total Pagado Mes
    $sql_total_pagado_mes = "SELECT COALESCE(SUM(total_general), 0) as total_pagado
                         FROM tbl_fact 
                         WHERE MONTH(fecha_emision) = :mes
                         AND YEAR(fecha_emision) = :anio
                         AND (estado = 'PAGADA' OR (estado = 'CERRADA' AND fecha_pago IS NOT NULL AND fecha_pago != ''))";

    // --- DATOS EXTRAS (Últimas, Top Servicios, etc) ---
    
    $sql_ultimas_facturas = "SELECT f.*, c.nombre as cliente_nombre 
                             FROM tbl_fact f 
                             LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
                             ORDER BY f.fecha_emision DESC LIMIT 5";
    $stmt = $db->query($sql_ultimas_facturas);
    $ultimas_facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_actividad = "SELECT * FROM historico_operaciones ORDER BY fecha_hora DESC LIMIT 5";
    $stmt = $db->query($sql_actividad);
    $actividad_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Servicios más solicitados (Basado en todo el año de cierre)
				$sql_servicios_solicitados = "SELECT 
					s.descripcion,
					COALESCE(SUM(fd.cantidad), 0) as total_vendido
				FROM clasif_serv s
				LEFT JOIN tbl_fact_detalle fd ON s.id = fd.servicio_id
				LEFT JOIN tbl_fact f ON fd.factura_id = f.id
				WHERE f.estado != 'ANULADA'
				AND YEAR(f.fecha_emision) = :anio
				AND s.activo = 1
				GROUP BY s.id
				ORDER BY total_vendido DESC
				LIMIT 5";
    $stmt = $db->prepare($sql_servicios_solicitados);
    $stmt->execute(['anio' => $anio_actual]);
    $servicios_solicitados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categorías Stats
    $sql_categorias_stats = "SELECT c.descripcion, c.codigo, COUNT(s.id) as total_servicios
                               FROM clasif_cat_de_serv c
                               LEFT JOIN clasif_serv s ON c.id = s.categoria_id
                               WHERE c.activo = 1
                               GROUP BY c.id ORDER BY total_servicios DESC LIMIT 5";
    $stmt = $db->query($sql_categorias_stats);
    $categorias_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ultimos_clientes = $db->query("SELECT * FROM clasif_clientes ORDER BY fechaRegistro DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $estadisticas['total'] = $db->query("SELECT COUNT(*) FROM historico_operaciones")->fetchColumn();


// --- DATOS SEMANALES (CORREGIDO) ---

// Usar el mes de cierre correcto
$mes_cierre = obtenerMesCierreOperaciones();
$anio_cierre = obtenerAnioCierreOperaciones();
$total_dias_mes = cal_days_in_month(CAL_GREGORIAN, $mes_cierre, $anio_cierre);

// Calcular el primer día de la semana del mes (1=Lunes, 7=Domingo)
$primer_dia_semana = date('N', strtotime("$anio_cierre-$mes_cierre-01"));
//$semanas_en_mes = ceil(($total_dias_mes + $primer_dia_semana - 1) / 7);
$semanas_en_mes = ceil($total_dias_mes / 7);

$semanas_labels = [];
$datos_semanales = array_fill(0, $semanas_en_mes, 0);

for ($i = 1; $i <= $semanas_en_mes; $i++) {
    $dia_inicio = ($i - 1) * 7 - $primer_dia_semana + 2;
    if ($dia_inicio < 1) $dia_inicio = 1;
    
    $dia_fin = $dia_inicio + 6;
    if ($dia_fin > $total_dias_mes) $dia_fin = $total_dias_mes;
    
    $semanas_labels[] = "Semana $i ($dia_inicio-$dia_fin)";
}

	
$sql_ingresos_semanales = "SELECT 
    FLOOR((DAY(fecha_emision) - 1) / 7) + 1 as semana_mes,
    COALESCE(SUM(total_general), 0) as total
    FROM tbl_fact 
    WHERE MONTH(fecha_emision) = :mes
    AND YEAR(fecha_emision) = :anio
    AND estado != 'ANULADA'
    GROUP BY semana_mes
    ORDER BY semana_mes";

    $stmt = $db->prepare($sql_ingresos_semanales);
    $stmt->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
    $ingresos_semanales_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ingresos_semanales_result as $dato) {
        $semana_index = $dato['semana_mes'] - 1;
        if ($semana_index >= 0 && $semana_index < $semanas_en_mes) {
            $datos_semanales[$semana_index] = floatval($dato['total']);
        }
    }

    // Datos para JS
    $datos_semanales_js = json_encode($datos_semanales);
    $semanas_labels_js = json_encode($semanas_labels);
    $semanas_en_mes_js = $semanas_en_mes;
} catch (PDOException $e) {
    // -------------------------------------------------------------------------
    // LOGICA DE EXTRACCIÓN
    // -------------------------------------------------------------------------
    $mensajeError = $e->getMessage();
    $nombreTabla = "Desconocida";
    if (preg_match("/'([^']+)'/", $mensajeError, $coincidencias)) {
        $nombreTabla = $coincidencias[1];
    }

    // -------------------------------------------------------------------------
    // VISTA DE ERROR - DARK MODE + ÍCONOS
    // -------------------------------------------------------------------------
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error Crítico</title>
        
        <!-- SweetAlert2 -->
        <script src="js/sweetalert211.js"></script>
        <!-- Font Awesome (Para los íconos) -->
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
<!-- Chatbot CSS -->
<link rel="stylesheet" href="css/chatbot.css">
        <style>
            body {
                background-color: #121212;
                color: #e0e0e0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .error-card {
                background-color: #1e1e1e;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.6);
                text-align: center;
                max-width: 600px;
                border: 1px solid #333;
                border-top: 4px solid #ff3333;
            }
            .error-title {
                color: #ff3333;
                font-size: 26px;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .error-desc {
                color: #b0b0b0;
                font-size: 18px;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .tabla-resaltada {
                color: #ff3333;
                font-weight: 800;
                background-color: rgba(255, 51, 51, 0.1);
                padding: 2px 8px;
                border-radius: 4px;
                font-family: monospace;
                border: 1px dashed #ff3333;
            }
            .btn-reload {
                background-color: #3085d6;
                color: white;
                border: none;
                padding: 14px 35px;
                font-size: 15px;
                border-radius: 50px;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
                font-weight: bold;
                letter-spacing: 0.5px;
                display: inline-flex;
                align-items: center;
                gap: 10px; /* Espacio entre ícono y texto */
            }
            .btn-reload:hover {
                background-color: #256bb0;
                transform: scale(1.05);
                box-shadow: 0 0 15px rgba(48, 133, 214, 0.5);
            }
            /* Estilo para el icono del botón */
            .btn-icon {
                font-size: 18px;
            }
        </style>
    </head>
    <body>

        <div class="error-card">
            <!-- Icono grande de advertencia arriba del título -->
            <div style="font-size: 50px; color: #ff3333; margin-bottom: 15px;">
                <i class="fas fa-triangle-exclamation"></i>
            </div>

            <h1 class="error-title">Error de Base de Datos</h1>
            
            <p class="error-desc">
                No se encontró la tabla requerida:<br><br>
                <span class="tabla-resaltada">
                    <i class="fas fa-table"></i> <?php echo $nombreTabla; ?>
                </span>
            </p>
            
            <!-- Botón con ícono de recarga -->
            <button class="btn-reload" onclick="location.reload()">
                <i class="fas fa-rotate-right btn-icon"></i> VOLVER A CARGAR PÁGINA
            </button>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tablaFaltante = "<?php echo $nombreTabla; ?>";

                Swal.fire({
                    icon: 'error',
                    title: 'Tabla no encontrada',
                    html: 'Falta la tabla <span style="color:#ff3333; font-weight:bold">' + tablaFaltante + '</span> en la BD.',
                    
                    // Estilos Dark
                    background: '#1e1e1e',
                    color: '#ffffff',
                    confirmButtonColor: '#3085d6',
                    // Botón con ícono dentro de SweetAlert
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    allowOutsideClick: false
                });
            });
        </script>

    </body>
    </html>
    <?php
    exit();
}
// Función auxiliar para semana actual basada en la fecha de cierre
function obtenerSemanaActualFechaCierre($dia_actual, $primer_dia_mes_timestamp) {
    $dia_semana_primer_dia = date('N', $primer_dia_mes_timestamp);
    return ceil(($dia_actual + $dia_semana_primer_dia - 1) / 7);
}

$semana_actual = obtenerSemanaActualFechaCierre($dia_actual, strtotime(date('Y-m-01', $timestamp_op)));
$total_semanas_mes = $semanas_en_mes;
$mes_actual_es = $meses_completos[$mes_actual - 1];


// Calcular la semana actual del mes
function obtenerSemanaActual($mes_actual, $anio_actual) {
    $dia_actual = date('j');
    $primer_dia_mes = date('N', strtotime("$anio_actual-$mes_actual-01"));
    return ceil(($dia_actual + $primer_dia_mes - 1) / 7);
}

// Uso
$semana_actual = obtenerSemanaActual($mes_actual, $anio_actual);
$total_semanas_mes = $semanas_en_mes; // Ya lo calculaste antes

// Obtener mes actual en español
$mes_actual_es = $meses_completos[$mes_actual - 1];

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
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SISFACT PDL Visiones</title>
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
    <script src="js/chart.umd.js"></script>
    
    <!-- Windows 11 CSS -->
    <link rel="stylesheet" href="css/windows11.css">
    
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


    
    /* Arreglar bordes redondeados al envolver */
    .btn-group > .btn:first-child {
        border-top-left-radius: var(--win-radius-sm) !important;
        border-bottom-left-radius: var(--win-radius-sm) !important;
    }
    .btn-group > .btn:last-child {
        border-top-right-radius: var(--win-radius-sm) !important;
        border-bottom-right-radius: var(--win-radius-sm) !important;
    }
}
.btn-group::-webkit-scrollbar {
    display: none; /* Oculta la barra de desplazamiento */
}
    </style>
<style>
/* Estilos para el selector de facturas */
#selectFactura {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-border-color);
    color: var(--win-text-primary);
    border-radius: var(--win-radius-sm);
}

#selectFactura:focus {
    border-color: var(--win-accent);
    box-shadow: 0 0 0 0.25rem var(--win-accent-light);
}

/* Estilos para la información de factura */
#infoFacturaContainer .card {
    background: linear-gradient(135deg, var(--win-bg-secondary) 0%, var(--win-bg-tertiary) 100%);
    border: 1px solid var(--win-border-color);
    border-radius: var(--win-radius);
}

/* Responsive */
@media (max-width: 768px) {
    .input-group {
        margin-bottom: 10px;
    }
    
    #infoFacturaContainer {
        margin-top: 20px;
    }
    
    #selectFactura {
        font-size: 0.9rem;
    }
}

/* Estilo para el mensaje de no selección */
#noFacturaSelected .card {
    background: var(--win-bg-tertiary);
    border: 1px dashed var(--win-border-color);
    border-radius: var(--win-radius);
}

/* Estilos para el dropdown de años */
#dropdownAnioSeleccionado {
    min-width: 150px;
    justify-content: space-between;
}

#listaAnios {
    max-height: 300px;
    overflow-y: auto;
}

#listaAnios .dropdown-item.active {
    background-color: var(--win-accent-light);
    color: var(--win-accent);
    font-weight: 600;
}

#listaAnios .dropdown-item:hover {
    background-color: var(--win-bg-tertiary);
}

/* Badges responsivos */
@media (max-width: 768px) {
    .card-header .d-flex {
        flex-wrap: wrap;
        gap: 0.5rem !important;
    }
    
    #dropdownAnioSeleccionado {
        min-width: 120px;
    }
}
/* Estilo para el watermark "PAGADA" */
.pagada-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-30deg);
    font-size: 5rem;
    font-weight: 900;
    color: rgba(13, 110, 253, 0.08); /* Color azul muy claro */
    z-index: 0;
    pointer-events: none;
    white-space: nowrap;
    opacity: 0.6;
    text-transform: uppercase;
    letter-spacing: 5px;
}

/* Para tema oscuro */
[data-theme="dark"] .pagada-watermark {
    color: rgba(13, 110, 253, 0.65);
}

/* Para tema claro */
[data-theme="light"] .pagada-watermark {
    color: rgba(13, 110, 253, 0.65);
}

/* Asegurar que el contenido quede encima del watermark */
#infoFacturaContainer .card-body {
    position: relative;
    z-index: 1;
}

#infoFacturaContainer .card-header {
    position: relative;
    z-index: 1;
}
/* Estilos para tarjetas de estadísticas */
.stat-card {
    border: 1px solid transparent;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-card-success {
    background-color: rgba(25, 135, 84, 0.05);
    border-color: rgba(25, 135, 84, 0.15);
}

.stat-card-danger {
    background-color: rgba(220, 53, 69, 0.05);
    border-color: rgba(220, 53, 69, 0.15);
}

.stat-card-warning {
    background-color: rgba(255, 193, 7, 0.05);
    border-color: rgba(255, 193, 7, 0.15);
}

.stat-icon-wrapper {
    border-radius: 50%;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-label {
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.stat-value {
    color: var(--win-text-primary, #212529);
    font-weight: 700;
    font-size: 1.75rem;
}

.stat-description {
    font-size: 0.8rem;
}

.min-width-0 {
    min-width: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-value {
        font-size: 1.5rem;
    }
    
    .stat-icon-wrapper {
        width: 40px;
        height: 40px;
    }
}

/* Para temas oscuros */
@media (prefers-color-scheme: dark) {
    .stat-card-success {
        background-color: rgba(25, 135, 84, 0.1);
        border-color: rgba(25, 135, 84, 0.25);
    }
    
    .stat-card-danger {
        background-color: rgba(220, 53, 69, 0.1);
        border-color: rgba(220, 53, 69, 0.25);
    }
    
    .stat-card-warning {
        background-color: rgba(255, 193, 7, 0.1);
        border-color: rgba(255, 193, 7, 0.25);
    }
    
    .stat-value {
        color: var(--win-text-primary, #f8f9fa);
    }
}
/* Estilos para botones de exportación de tablas */
.btn-group-export {
    display: flex;
    gap: 4px;
}

.btn-export-table {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-export-table.excel {
    background: #217346;
    border-color: #217346;
    color: white;
}

.btn-export-table.excel:hover {
    background: #1a5c38;
    border-color: #1a5c38;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(33, 115, 70, 0.3);
}

.btn-export-table.word {
    background: #2b579a;
    border-color: #2b579a;
    color: white;
}

.btn-export-table.word:hover {
    background: #22447d;
    border-color: #22447d;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(43, 87, 154, 0.3);
}

.btn-export-table.pdf {
    background: #f40f02;
    border-color: #f40f02;
    color: white;
}

.btn-export-table.pdf:hover {
    background: #d30d02;
    border-color: #d30d02;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(244, 15, 2, 0.3);
}

.btn-export-table.jpg {
    background: #ff6b35;
    border-color: #ff6b35;
    color: white;
}

.btn-export-table.jpg:hover {
    background: #e55a2b;
    border-color: #e55a2b;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 107, 53, 0.3);
}

.btn-export-table.print {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
}

.btn-export-table.print:hover {
    background: #5a6268;
    border-color: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
}

/* Header de tabla con botones */
.table-header-with-export {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: var(--win-bg-tertiary);
    border-bottom: 1px solid var(--win-border-color);
}

.table-title {
    font-weight: 600;
    color: var(--win-text-primary);
    font-size: 14px;
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
/* Estilo para la información de pago */
#infoPagoContainer .bg-success {
    transition: all 0.3s ease;
}

#infoPagoContainer:hover .bg-success {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(25, 135, 84, 0.2);
}

/* Resaltar fecha y referencia de pago */
#infoFechaPago, #infoRefPago {
    font-size: 1rem;
    word-break: break-word;
}

/* Para facturas pagadas sin información de pago */
.pago-incompleto {
    background-color: rgba(255, 193, 7, 0.1) !important;
    border-color: rgba(255, 193, 7, 0.3) !important;
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
			<span style="color: var(--win-text-primary);">SISFACT PDL Visiones</span>
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
				<a href="dashboard.php" class="win-nav-link active">
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
                    <span class="win-nav-badge"><?php echo isset($total_facturas) ? $total_facturas : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo isset($clientes_todos) ? $clientes_todos : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo isset($cant_categ) ? $cant_categ : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
<span class="win-nav-badge">
    <?php 
    $total_servicios = 0;
    if (isset($servicios_count)) {
        $total_servicios += $servicios_count;
    }
    if (isset($servicios_inactivos)) {
        $total_servicios += $servicios_inactivos;
    }
    echo $total_servicios;
    ?>
</span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
					<span class="win-nav-badge"><?php echo isset($cant_Users) ? $cant_Users : '0'; ?></span>
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

   


   <!-- Tu contenido existente con estilo Windows 11 -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <?php if (isset($error_dashboard)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_dashboard; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header del dashboard -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-tachometer-alt me-2" style="color: var(--win-accent);"></i>Dashboard
                </h1>
                <p class="text-muted mb-0">Resumen general del sistema<br>Fecha de Cierre Operaciones: <span class="badge bg-success"><?php echo ultimoDiaMesFechaInicio(); ?></span></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnActualizarDashboard">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
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
						<?php echo $lastInvoiceInfo['fecha']; ?> - $ <?php echo number_format($lastInvoiceInfo['importe'], 2); ?></span></span>
            </small>
        </li>
    </ul>
</div>
				
				
				</div>
            </div>
        </div>

<!-- Statistics Cards - Tarjetas iguales en tamaño -->
<div class="row mb-4" id="statsCards">
<!-- PRIMERA COLUMNA: Resumen de Facturas e Ingresos uno debajo del otro -->
<div class="col-xl-3 col-lg-6 col-md-6 mb-4">
    <div class="row g-2 h-50">
        <!-- Tarjeta pequeña 1: RESUMEN DE FACTURAS -->
        <div class="col-12">
            <div class="card stat-card h-100 animate__animated animate__fadeIn" style="animation-delay: 0.1s; height: 105px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">RESUMEN DE FACTURAS</div>
                            <div class="h4 mb-0 fw-bold text-success" id="facturasMes"><?php echo $facturas_mes; ?></div>
                            <div class="text-success small">
                                <i class="fas fa-file-invoice me-1"></i>Este mes
                            </div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-file-invoice-dollar fa-lg text-success opacity-50"></i>
                        </div>
                    </div>
                    <!-- Información adicional compacta -->
                    <div class="mt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">En el Año:</small>
                            <small class="fw-bold text-primary"><?php echo $facturas_anual; ?></small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">En toda la Base de Datos:</small>
                            <small class="fw-bold text-secondary"><?php echo $total_facturas; ?></small>
                        </div>
                        <!-- NUEVA LÍNEA: Facturas HOY COTIZADAS -->
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Hoy Cotizadas:</small>
                            <small class="fw-bold text-info">
                                <?php
                                // CORREGIDO: Usar :fecha_op en lugar de CURDATE()
                                $sql_hoy_contabilizadas = "SELECT COUNT(*) as total FROM tbl_fact WHERE DATE(fecha_emision) = :fecha_op";
                                try {
                                    $stmt_hoy = $db->prepare($sql_hoy_contabilizadas);
                                    $stmt_hoy->execute(['fecha_op' => $fecha_op_raw]); // <--- Parametrizado
                                    $hoy_contabilizadas = $stmt_hoy->fetch(PDO::FETCH_ASSOC)['total'];
                                    echo $hoy_contabilizadas;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                                ?>
                            </small>
                        </div>
                        <!-- Nuevo bloque para mostrar las pagadas hoy -->
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Pagadas del mes:</small>
                            <small class="fw-bold text-success">
                                <?php
                                // facturas EMITIDAS y PAGADAS en el mes actual
                                $sql_MES_pagadas = "SELECT COUNT(*) as total 
                                                    FROM `tbl_fact` 
                                                    WHERE MONTH(fecha_emision) = :mes 
                                                      AND YEAR(fecha_emision) = :anio
                                                      AND estado = 'PAGADA'
                                                      AND fecha_pago IS NOT NULL";
                                try {
                                    $stmt_pagadas = $db->prepare($sql_MES_pagadas);
                                    $stmt_pagadas->execute(['mes' => $mes_actual, 'anio' => $anio_actual]); // <--- Parametrizado
                                    $hoy_pagadas = $stmt_pagadas->fetch(PDO::FETCH_ASSOC)['total'];
                                    echo $hoy_pagadas;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                                ?>
                            </small>
                        </div>
                    </div>
<!-- NUEVA LÍNEA: Última factura registrada -->
<div class="mt-2 pt-2" style="border-top: 1px dashed rgba(255,255,255,0.1);">
    <div class="text-muted d-block mb-1">
        <i class="fas fa-clock me-1"></i>
        <span class="text-xs fw-bold text-success text-uppercase mb-1">Última Factura:</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <small class="text-muted">No. Factura:</small>
        <small class="fw-bold text-warning">
            <?php echo $lastInvoiceInfo['numero']; ?>
        </small>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-1">
        <small class="text-muted">Fecha Emisión:</small>
        <small class="fw-bold text-warning">
            <?php echo $lastInvoiceInfo['fecha']; ?>
        </small>
    </div>
    
    <!-- SECCIÓN CLIENTE: como subtítulo con nombre debajo -->
    <div class="mt-2">
        <small class="text-muted d-block text-xs fw-bold text-uppercase" style="color: #28a745 !important;">Cliente:</small>
        <small class="fw-bold text-warning d-block mt-1" style="font-size: 0.8rem;">
            <?php echo $lastInvoiceInfo['cliente'] ?? $lastInvoiceInfo['proveedor']; ?>
        </small>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="text-muted">Importe:</small>
        <small class="fw-bold text-warning">
            $<?php echo number_format($lastInvoiceInfo['importe'], 2); ?>
        </small>
    </div>
</div>
                </div>
            </div>
        
		
		</div>
        
<!-- Tarjeta pequeña 2: RESUMEN DE INGRESOS (Debajo de Facturas) -->
<div class="col-12">
    <div class="card stat-card h-100 animate__animated animate__fadeIn" style="animation-delay: 0.2s; height: 140px;">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">RESUMEN DE INGRESOS <?php echo $mes_actual_es.'/'.$anio_actual; ?></div>
                    <div class="h4 mb-0 fw-bold text-warning" id="ingresosMes">$<?php echo number_format($ingresos_mes, 2); ?></div>
                    <div class="text-warning small" id="variacionIngresos">
                        <i class="fas fa-dollar-sign me-1"></i>
                        <?php if ($variacion_ingresos >= 0): ?>
                            <i class="fas fa-arrow-up me-1"></i>+<?php echo number_format($variacion_ingresos, 1); ?>%
                        <?php else: ?>
                            <i class="fas fa-arrow-down me-1"></i><?php echo number_format($variacion_ingresos, 1); ?>%
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ms-2">
                    <i class="fas fa-money-bill-wave fa-lg text-warning opacity-50"></i>
                </div>
            </div>
            <!-- Información adicional compacta - AGREGADO HOY Y TOTAL COTIZADO -->
            <div class="mt-2" style="font-size: 0.8rem;">
                <!-- Primera fila: HOY y Cotizado Hoy -->
                <div class="row g-1 mb-1">
<div class="col-6">
    <small class="text-muted d-flex align-items-center">
        <i class="fas fa-calendar-day me-1"></i>HOY:
    </small>
    <small class="fw-bold text-primary">
        <?php echo date('d/m/Y', $timestamp_combinado); ?>
    </small>
</div>
<div class="col-6 text-end">
    <small class="text-muted">Cotizado Hoy:</small>
    <small class="fw-bold text-success d-block">
        <?php
        try {
            // CORREGIDO: Parametrizado
            $stmt_cotizado = $db->prepare($sql_total_cotizado_hoy); // Esta variable SQL ya la definimos arriba parametrizada
            $stmt_cotizado->execute(['fecha_op' => date('Y-m-d', $timestamp_combinado)]);
            $total_cotizado_hoy = $stmt_cotizado->fetch(PDO::FETCH_ASSOC)['total'];
            echo '$' . number_format($total_cotizado_hoy, 2);
        } catch (Exception $e) { echo '$0.00'; }
        ?>
    </small>
</div>
</div>

<!-- Segunda fila: Ingresos Reales y Cobranza -->
<div class="row g-1 mb-1">
    <div class="col-6">
        <small class="text-muted">Ingresos Reales:</small>
        <small class="fw-bold text-success d-block" style="font-size:0.9rem;">
            <?php
            try {
                // CORREGIDO: Parametrizado
                $stmt_reales = $db->prepare($sql_ingresos_reales_mes);
                $stmt_reales->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $ingresos_reales_mes = $stmt_reales->fetch(PDO::FETCH_ASSOC)['total'];
                echo '$' . number_format($ingresos_reales_mes, 2);
            } catch (Exception $e) { echo '$0.00'; }
            ?>
        </small>
    </div>
    <div class="col-6 text-end">
        <small class="text-muted">Cobranza:</small>
        <small class="fw-bold text-primary d-block" style="font-size:0.9rem;">
            <?php 
            try {
                // CORREGIDO: Parametrizado
                $stmt_fact_mes = $db->prepare($sql_total_facturado_mes);
                $stmt_fact_mes->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $total_facturado_mes = $stmt_fact_mes->fetch(PDO::FETCH_ASSOC)['total_facturado'];
                
                $stmt_pag_mes = $db->prepare($sql_total_pagado_mes);
                $stmt_pag_mes->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $total_pagado_mes = $stmt_pag_mes->fetch(PDO::FETCH_ASSOC)['total_pagado'];
                
                $porcentaje_cobranza_mes = $total_facturado_mes > 0 ? 
                    ($total_pagado_mes / $total_facturado_mes) * 100 : 0;
                echo '$' . number_format($total_pagado_mes, 2) . ' - ' . number_format($porcentaje_cobranza_mes, 1) . '%';
            } catch (Exception $e) { echo '$0.00 - 0%'; }
            ?>
        </small>
    </div>
    <hr class="my-1 opacity-100">
</div>

<!-- Tercera fila: Estados de facturas (CORREGIDO PARA USAR FECHA CIERRE) -->
<div class="row g-1 mb-1">
    <div class="col-6">
        <small class="text-muted">Contabilizadas:</small>
        <small class="fw-bold text-success d-block">
            <?php
            $sql_contabilizadas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                                       FROM tbl_fact 
                                       WHERE MONTH(fecha_contabilizacion) = :mes 
                                       AND YEAR(fecha_contabilizacion) = :anio
                                       AND estado = 'CONTABILIZADA'";
            try {
                $stmt_cont = $db->prepare($sql_contabilizadas_mes);
                $stmt_cont->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $contabilizadas = $stmt_cont->fetch(PDO::FETCH_ASSOC);
                echo '(' . $contabilizadas['cantidad'] . ') - $' . number_format($contabilizadas['importe'], 2);
            } catch (Exception $e) { echo '(0) - $0.00'; }
            ?>
        </small>
    </div>
    <div class="col-6 text-end">
        <small class="text-muted">Pagadas:</small>
        <small class="fw-bold text-primary d-block">
            <?php
            $sql_pagadas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                                FROM tbl_fact 
                                WHERE MONTH(fecha_contabilizacion) = :mes 
                                AND YEAR(fecha_contabilizacion) = :anio
                                AND estado = 'PAGADA'";
            try {
                $stmt_pag = $db->prepare($sql_pagadas_mes);
                $stmt_pag->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $pagadas = $stmt_pag->fetch(PDO::FETCH_ASSOC);
                echo '(' . $pagadas['cantidad'] . ') - $' . number_format($pagadas['importe'], 2);
            } catch (Exception $e) { echo '(0) - $0.00'; }
            ?>
        </small>
    </div>
</div>

<!-- Cuarta fila: Pendientes y Anuladas (CORREGIDO) -->
<div class="row g-1">
    <div class="col-6">
        <small class="text-muted">Pendientes:</small>
        <small class="fw-bold text-warning d-block">
            <?php
            $sql_pendientes_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                                   FROM tbl_fact 
                                   WHERE MONTH(fecha_emision) = :mes 
                                   AND YEAR(fecha_emision) = :anio
                                   AND estado = 'PENDIENTE'";
            try {
                $stmt_pen = $db->prepare($sql_pendientes_mes);
                $stmt_pen->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $pendientes = $stmt_pen->fetch(PDO::FETCH_ASSOC);
                echo '(' . $pendientes['cantidad'] . ') - $' . number_format($pendientes['importe'], 2);
            } catch (Exception $e) { echo '(0) - $0.00'; }
            ?>
        </small>
    </div>
    <div class="col-6 text-end">
        <small class="text-muted">Anuladas:</small>
        <small class="fw-bold text-danger d-block">
            <?php
            $sql_anuladas_mes = "SELECT COUNT(*) as cantidad, COALESCE(SUM(total_general), 0) as importe 
                                 FROM tbl_fact 
                                 WHERE MONTH(fecha_emision) = :mes 
                                 AND YEAR(fecha_emision) = :anio
                                 AND estado = 'ANULADA'";
            try {
                $stmt_anu = $db->prepare($sql_anuladas_mes);
                $stmt_anu->execute(['mes' => $mes_actual, 'anio' => $anio_actual]);
                $anuladas = $stmt_anu->fetch(PDO::FETCH_ASSOC);
                echo '(' . $anuladas['cantidad'] . ') - $' . number_format($anuladas['importe'], 2);
            } catch (Exception $e) { echo '(0) - $0.00'; }
            ?>
        </small>
    </div>
</div>
                <hr class="my-1 opacity-25">
                <!-- Tercera fila: Pagado y Facturado -->
                <div class="row g-1">
                    <div class="col-6">
                        <small class="text-muted">Facturado:</small>
                        <small class="fw-bold text-warning d-block">
                            $<?php echo number_format($total_facturado_mes ?? 0, 2); ?>
                        </small>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted">Pagado:</small>
                        <small class="fw-bold text-success d-block">
                            $<?php echo number_format($total_pagado_mes ?? 0, 2); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>	
	
	
	</div>
</div>




<!-- TERCERA COLUMNA: Resumen Anual (grande) OPTIMIZADO - VERTICAL -->
<div class="col-xl-6 col-lg-12 col-md-12 mb-4">
    <div class="card stat-card info h-100 animate__animated animate__fadeIn" style="animation-delay: 0.5s; min-height: 220px;">
        <div class="card-body p-4">
            
            <?php
            // ==========================================
            // 1. LÓGICA PHP (Filtro Estricto + Mostrar Ceros)
            // ==========================================
            
            // A. Estructura base para asegurar que salgan los 5 estados
            $datos_para_mostrar = [
                'CONTABILIZADA' => ['cant' => 0, 'total' => 0],
                'PENDIENTE'     => ['cant' => 0, 'total' => 0],
                'ANULADA'       => ['cant' => 0, 'total' => 0],
                'PAGADA'        => ['cant' => 0, 'total' => 0],
                'CERRADA'       => ['cant' => 0, 'total' => 0]
            ];
            
            // B. Consulta Inteligente
            $sql_estados_anual = "SELECT 
                estado,
                COUNT(*) as cantidad,
                COALESCE(SUM(total_general), 0) as total
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio
                GROUP BY estado";

            $stmt = $db->prepare($sql_estados_anual);
            $stmt->execute(['anio' => $anio_actual]);
            $resultados_bd = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // C. Rellenar datos
            $total_docs_reales = 0; // Total de documentos encontrados con este criterio
            
            foreach ($resultados_bd as $fila) {
                $estado_key = strtoupper($fila['estado']);
                
                // Si existe en nuestro array base, lo actualizamos
                if (isset($datos_para_mostrar[$estado_key])) {
                    $datos_para_mostrar[$estado_key]['cant'] = $fila['cantidad'];
                    $datos_para_mostrar[$estado_key]['total'] = $fila['total'];
                }
                $total_docs_reales += $fila['cantidad'];
            }

            // D. Calcular Total Financiero Real (Solo Contabilizadas + Pagadas + Cerradas)
            $gran_total_ingresos = $datos_para_mostrar['CONTABILIZADA']['total'] + 
                                   $datos_para_mostrar['PAGADA']['total'] + 
                                   $datos_para_mostrar['CERRADA']['total'];

            // NUEVO: Calcular Contabilizadas + Pagadas HOY
            $sql_hoy_contab_pagadas = "SELECT 
                COUNT(*) as cantidad,
                COALESCE(SUM(total_general), 0) as total
                FROM tbl_fact 
                WHERE DATE(fecha_contabilizacion) = :fecha_op 
                AND estado IN ('CONTABILIZADA', 'PAGADA')";
            
            $stmt_hoy = $db->prepare($sql_hoy_contab_pagadas);
            $stmt_hoy->execute(['fecha_op' => date('Y-m-d', $timestamp_combinado)]);
            $hoy_result = $stmt_hoy->fetch(PDO::FETCH_ASSOC);
            
            $hoy_cantidad = $hoy_result['cantidad'] ?? 0;
            $hoy_total = $hoy_result['total'] ?? 0;

            // NUEVO: Calcular porcentaje de lo PAGADO vs Total Facturado (excluyendo ANULADAS y PENDIENTES)
            // Las CERRADAS con fecha_pago se consideran como pagadas
            $sql_totales_anio = "SELECT 
                COALESCE(SUM(CASE WHEN estado NOT IN ('ANULADA', 'PENDIENTE') THEN total_general ELSE 0 END), 0) as total_facturado,
                COALESCE(SUM(CASE WHEN estado = 'PAGADA' THEN total_general ELSE 0 END), 0) as total_pagado_directo,
                COALESCE(SUM(CASE WHEN estado = 'CERRADA' AND fecha_pago IS NOT NULL AND fecha_pago != '' THEN total_general ELSE 0 END), 0) as total_cerradas_con_pago
                FROM tbl_fact 
                WHERE YEAR(fecha_emision) = :anio";

            $stmt_total = $db->prepare($sql_totales_anio);
            $stmt_total->execute(['anio' => $anio_actual]);
            $totales = $stmt_total->fetch(PDO::FETCH_ASSOC);

            $total_facturado = $totales['total_facturado'];
            $total_pagado = $totales['total_pagado_directo'] + $totales['total_cerradas_con_pago'];
            $porcentaje_pagado = $total_facturado > 0 ? ($total_pagado / $total_facturado) * 100 : 0;

            // E. Configuración Visual
            $config_visual = [
                'CONTABILIZADA' => ['color' => '#198754', 'bg' => 'bg-success', 'icon' => 'fa-check-circle', 'label' => 'Contabilizadas'],
                'PENDIENTE'     => ['color' => '#ffc107', 'bg' => 'bg-warning', 'icon' => 'fa-clock', 'label' => 'Pendientes'],
                'ANULADA'       => ['color' => '#dc3545', 'bg' => 'bg-danger',  'icon' => 'fa-times-circle', 'label' => 'Anuladas'],
                'PAGADA'        => ['color' => '#0d6efd', 'bg' => 'bg-primary', 'icon' => 'fa-credit-card', 'label' => 'Pagadas'],
                'CERRADA'       => ['color' => '#6f42c1', 'bg' => 'bg-secondary', 'icon' => 'fa-lock', 'label' => 'Cerradas']
            ];
            ?>

            <!-- SECCIÓN SUPERIOR: Encabezado Principal y Desglose -->
            <div class="mb-4 pb-3 border-bottom" style="border-color: var(--win-border_color) !important;">
                
                <!-- Encabezado Principal -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">
                            <i class="fas fa-calendar-alt me-1"></i> RESUMEN ANUAL <?php echo $anio_seleccionado ?>
                        </div>
                        <div class="h2 mb-0 fw-bold text-info">
                            <!-- TOTAL CALCULADO CORRECTAMENTE -->
                            $<?php echo number_format($gran_total_ingresos, 2); ?>
                        </div>
                        <div class="text-success mt-1">Ingresos Reales (Contab.+Pagadas+Cerradas con Fecha Pago)</div>
                    </div>
                    
                    <!-- Métricas Rápidas en Header -->
                    <div class="text-end">
                        <div class="d-flex flex-column align-items-end gap-1">
                            <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 p-2" style="font-size: 0.8rem;">
                                <i class="fas fa-file-invoice me-1"></i>
                                <?php echo $total_docs_reales; ?> Docs
                            </div>
                            <div class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 p-2" style="font-size: 0.8rem;">
                                <i class="fas fa-chart-pie me-1"></i>
                                <?php echo $trimestre_actual_Completo; ?> Trim
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Porcentaje de Cobranza -->
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <small class="text-muted me-2 fw-bold" style="font-size: 0.75rem;">Cobranza Anual:</small>
                        <div class="progress flex-grow-1" style="height: 10px; background: var(--win-bg-tertiary); border-radius: 5px;">
                            <div class="progress-bar" 
                                 style="width: <?php echo min($porcentaje_pagado, 100); ?>%; background: linear-gradient(90deg, #0d6efd, #198754);"
                                 role="progressbar" 
                                 aria-valuenow="<?php echo $porcentaje_pagado; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <small class="fw-bold ms-2" style="color: #0d6efd; min-width: 50px;">
                            <?php echo number_format($porcentaje_pagado, 1); ?>%
                        </small>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="fas fa-arrow-up text-primary me-1"></i>
                                Pagado: <span class="text-primary fw-bold">$<?php echo number_format($total_pagado, 2); ?></span>
                            </small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="fas fa-file-invoice-dollar text-warning me-1"></i>
                                Facturado: <span class="text-warning fw-bold">$<?php echo number_format($total_facturado, 2); ?></span>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Grid de Estados (5 en fila) CON NOMBRES ENCIMA -->
                <div class="mb-2">
                    <small class="text-muted d-block mb-3 fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        Desglose Total de Documentos
                    </small>
                    
                    <!-- Nombres de los Estados ENCIMA de las tarjetas -->
                    <div class="row g-2 mb-2">
                        <?php 
                        // Primero mostramos solo los nombres encima
                        foreach ($datos_para_mostrar as $estado_nombre => $datos): 
                            $cfg = $config_visual[$estado_nombre];
                        ?>
                        <div class="col-2-4" style="flex: 0 0 20%; max-width: 20%;">
                            <div class="text-center">
                                <div class="d-flex align-items-center justify-content-center mb-1">
                                    <i class="fas <?php echo $cfg['icon']; ?> me-1" style="color: <?php echo $cfg['color']; ?>; font-size: 0.8rem;"></i>
                                    <span class="fw-bold text-truncate" style="font-size: 0.75rem; color: var(--win-text-primary);">
                                        <?php echo $cfg['label']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
<!-- Tarjetas de Datos DEBAJO de los nombres -->
<div class="row g-2">
    <?php 
    foreach ($datos_para_mostrar as $estado_nombre => $datos): 
        $cfg = $config_visual[$estado_nombre];
        $porcentaje = $total_docs_reales > 0 ? ($datos['cant'] / $total_docs_reales) * 100 : 0;
    ?>
    <div class="col-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="p-2 rounded-2 h-100 position-relative overflow-hidden d-flex flex-column justify-content-between" 
             style="background: <?php echo $cfg['color']; ?>08; border: 1px solid <?php echo $cfg['color']; ?>30; min-height: 80px;">
            
            <div class="position-absolute top-0 start-0 bottom-0" style="width: 3px; background: <?php echo $cfg['color']; ?>;"></div>
            
            <div class="ps-2 w-100 d-flex flex-column h-100">
                <!-- Monto Principal -->
                <div class="text-center mb-1 flex-grow-1 d-flex align-items-center justify-content-center">
                    <span class="fw-bold text-nowrap d-block" style="color: <?php echo $cfg['color']; ?>; font-size: 0.9rem;">
                        $<?php echo number_format($datos['total'], 0); ?>
                    </span>
                </div>
                
                <!-- Cantidad -->
                <div class="text-center mb-1">
                    <span class="badge <?php echo $cfg['bg']; ?> bg-opacity-10 text-reset border border-opacity-25" 
                          style="color: <?php echo $cfg['color']; ?> !important; font-size: 0.75rem; padding: 3px 8px;">
                        <?php echo $datos['cant']; ?> docs
                    </span>
                </div>
                
                <!-- Porcentaje debajo -->
                <div class="text-center">
                    <small style="color: <?php echo $cfg['color']; ?>; font-size: 0.7rem; font-weight: 500;">
                        <?php echo number_format($porcentaje, 1); ?>% del total
                    </small>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
				
				</div>
            </div>
            
            <!-- SECCIÓN INFERIOR: Métricas Financieras -->
            <div class="pt-2">
                <div class="row g-3">
                    <!-- Columna 1: Métricas Principales -->
                    <div class="col-md-7">
                        <small class="text-muted d-block mb-2 fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Métricas Financieras
                        </small>
                        
                        <div class="row g-2">
                            <!-- Promedio Mensual -->
                            <div class="col-6">
                                <div class="p-2 rounded-2 border" style="border-color: var(--win-border-color) !important;">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-success bg-opacity-10 p-1 me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-divide text-success" style="font-size: 0.7rem;"></i>
                                        </div>
                                        <div style="line-height: 1.2;">
                                            <small class="d-block text-muted" style="font-size: 0.65rem;">Promedio Mensual</small>
                                            <span class="fw-bold text-success" style="font-size: 0.85rem;">
                                                $<?php echo number_format($ingresos_anual_total / max(1, date('n')), 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Trimestre Actual -->
                            <div class="col-6">
                                <div class="p-2 rounded-2 border" style="border-color: var(--win-border-color) !important;">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-warning bg-opacity-10 p-1 me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-chart-pie text-warning" style="font-size: 0.7rem;"></i>
                                        </div>
                                        <div style="line-height: 1.2;">
                                            <small class="d-block text-muted" style="font-size: 0.65rem;"><?php echo $trimestre_actual_Completo; ?> Trimestre</small>
                                            <span class="fw-bold text-warning" style="font-size: 0.85rem;">
                                                $<?php echo number_format($ingresos_trimestre, 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contabilizadas + Pagadas HOY -->
                            <div class="col-6">
                                <div class="p-2 rounded-2 border" style="border-color: var(--win-border-color) !important;">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-purple bg-opacity-10 p-1 me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: rgba(111, 66, 193, 0.1);">
                                            <i class="fas fa-calendar-day" style="font-size: 0.7rem; color: #6f42c1;"></i>
                                        </div>
                                        <div style="line-height: 1.2;">
                                            <small class="d-block text-muted" style="font-size: 0.65rem;">Hoy Contab. + Pagadas</small>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="fw-bold me-1" style="font-size: 0.85rem; color: #6f42c1;">
                                                    <?php echo $hoy_cantidad; ?>
                                                </span>
                                                <span class="fw-bold text-success" style="font-size: 0.8rem;">
                                                    $<?php echo number_format($hoy_total, 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Porcentaje Cobranza -->
                            <div class="col-6">
                                <div class="p-2 rounded-2 border" style="border-color: var(--win-border-color) !important;">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-info bg-opacity-10 p-1 me-2" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-percentage text-info" style="font-size: 0.7rem;"></i>
                                        </div>
                                        <div style="line-height: 1.2;">
                                            <small class="d-block text-muted" style="font-size: 0.65rem;">Cobranza Anual</small>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold text-info" style="font-size: 0.85rem;">
                                                    <?php echo number_format($porcentaje_pagado, 1); ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna 2: Progreso del Año -->
                    <div class="col-md-5">
                        <small class="text-muted d-block mb-2 fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Progreso del Año
                        </small>
                        
                        <div class="p-3 rounded-2 h-100 d-flex flex-column justify-content-center" 
                             style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color);">
                            <div class="text-center mb-2">
                                <div class="display-5 fw-bold text-info mb-1">
                                    <?php echo $trimestre_actual; ?>/4
                                </div>
                                <small class="text-muted d-block" style="font-size: 0.7rem;">
                                    Trimestres Completados
                                </small>
                            </div>
                            
                            <div class="mb-2">
                                <div class="progress" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo min(100, ($trimestre_actual / 4) * 100); ?>%;">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted" style="font-size: 0.65rem;">
                                    <?php echo round(($trimestre_actual / 4) * 100); ?>% del año completado
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--  Clientes, Servicios y Categorías -->
<div class="col-xl-3 col-lg-6 col-md-6 mb-4">
    <div class="row g-2 h-100">
        <!-- Card 1: Clientes -->
        <div class="col-12">
            <div class="card stat-card-small h-100 animate__animated animate__fadeIn" style="animation-delay: 0.3s; height: 105px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Clientes</div>
                            <div class="h4 mb-0 fw-bold" id="clientesActivos"><?php echo $clientes_todos; ?></div>
                            <div class="text-primary small" id="nuevosClientes">
                                <i class="fas fa-users me-1"></i>
                                Total Registrados
                            </div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-users fa-lg text-primary opacity-50"></i>
                        </div>
                    </div>
                    <!-- Información adicional compacta -->
                    <div class="mt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Activos:</small>
                            <small class="fw-bold text-success"><?php echo $clientes_activos; ?></small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Inactivos:</small>
                            <small class="fw-bold text-danger" id="ClientesInactivos"><?php echo $clientes_inactivos; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card 2: Servicios -->
        <div class="col-12">
            <div class="card stat-card-small h-100 animate__animated animate__fadeIn" style="animation-delay: 0.4s; height: 105px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Servicios</div>
                            <div class="h4 mb-0 fw-bold" id="serviciosCount">
                                <?php echo $servicios_count + $servicios_inactivos; ?>
                            </div>
                            <div class="text-danger small">
                                <i class="fas fa-tags me-1"></i>
                                Total Servicios
                            </div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-list fa-lg text-danger opacity-50"></i>
                        </div>
                    </div>
                    <!-- Información adicional compacta -->
                    <div class="mt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Activos:</small>
                            <small class="fw-bold text-success"><?php echo $servicios_count; ?></small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Inactivos:</small>
                            <small class="fw-bold text-danger"><?php echo $servicios_inactivos; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card 3: Categorías -->
        <div class="col-12">
            <div class="card stat-card-small h-100 animate__animated animate__fadeIn" style="animation-delay: 0.5s; height: 105px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Categorías</div>
                            <div class="h4 mb-0 fw-bold" id="categoriasCount"><?php echo $cant_categ; ?></div>
                            <div class="text-success small">
                                <i class="fas fa-layer-group me-1"></i>
                                Total Categorías
                            </div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-folder fa-lg text-success opacity-50"></i>
                        </div>
                    </div>
                    <!-- Información adicional compacta -->
                    <div class="mt-2">
                        <?php
                        // Necesitas obtener el conteo de categorías activas e inactivas
                        // Agrega estas consultas en la parte PHP del inicio del archivo:
                        
                        $sql_cat_activas = "SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 1";
                        $stmt_cat_act = $db->prepare($sql_cat_activas);
                        $stmt_cat_act->execute();
                        $categorias_activas = $stmt_cat_act->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $sql_cat_inactivas = "SELECT COUNT(*) as total FROM clasif_cat_de_serv WHERE activo = 0";
                        $stmt_cat_inact = $db->prepare($sql_cat_inactivas);
                        $stmt_cat_inact->execute();
                        $categorias_inactivas = $stmt_cat_inact->fetch(PDO::FETCH_ASSOC)['total']
                        ?>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Activas:</small>
                            <small class="fw-bold text-success" id="CatActivas">
                                <?php echo isset($categorias_activas) ? $categorias_activas : '0'; ?>
                            </small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Inactivas:</small>
                            <small class="fw-bold text-danger" id="CatInactivas">
                                <?php echo isset($categorias_inactivas) ? $categorias_inactivas : '0'; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Agregar estilo para color morado -->
<style>
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>


<div class="row mb-4">
    <!-- Card para seleccionar factura -->
    <div class="col-xl-12">
        <div class="card animate__animated animate__fadeIn" style="animation-delay: 0.6s;">
<div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="m-0 fw-bold d-flex align-items-center">
        <i class="fas fa-search me-2 text-primary"></i>
        <span style="color: var(--win-text-primary);">Buscar y Gestionar Facturas Rapidamente</span>
    </h6>
    <div class="d-flex align-items-center gap-2">
        <!-- Selector de Años -->
<div class="dropdown">
    <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center" 
            type="button" 
            data-bs-toggle="dropdown"
            id="dropdownAnioSeleccionado">
        <i class="fas fa-calendar-alt me-1"></i>
        <span id="textoAnioSeleccionado">
            <!-- 1. Imprime el año seleccionado (definido arriba) -->
            AÑO <?php echo $anio_seleccionado; ?>
            
            <?php 
            // 2. Lógica para mostrar el badge inmediatamente al cargar PHP
            // Compara el seleccionado con el de la Base de Datos ($anio_actual)
            if ($anio_seleccionado == $anio_actual) {
                echo '<span class="badge bg-success ms-2" title="Año actual de operaciones">OPERACIONES</span>';
            } elseif ($anio_seleccionado < $anio_actual) {
                echo '<span class="badge bg-secondary ms-2" title="Año cerrado">HISTÓRICO</span>';
            } else {
                echo '<span class="badge bg-info ms-2" title="Año futuro">FUTURO</span>';
            }
            ?>
        </span>
    </button>
    <ul class="dropdown-menu" id="listaAnios">
        <?php foreach ($anios_disponibles as $anio): ?>
            <li>
                <a class="dropdown-item <?php echo $anio['anio'] == $anio_seleccionado ? 'active' : ''; ?>" 
                   href="#" 
                   onclick="cambiarAnio(<?php echo $anio['anio']; ?>)">
                    
                    <?php echo $anio['anio']; ?>
                    
                    <!-- Badge dentro de la lista desplegable -->
                    <?php if ($anio['anio'] == $anio_actual): ?>
                        <span class="badge bg-success ms-2">OPERACIONES</span>
                    <?php endif; ?>
                    
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
        
        
		
		<!-- Badges actualizados -->
        <span class="badge bg-secondary" id="badgeTotalBd">
            <i class="fas fa-database me-1"></i><?php echo $total_facturas; ?> Facturas en BD
        </span>
		<span class="badge bg-success" id="badgeAnioActual">
			<i class="fas fa-file-invoice me-1"></i>
			<span id="contadorFacturasAnio"><?php 
				// Corrección: Usamos dos marcadores distintos (:anio_cont y :anio_emi)
				$sql_count_anio = "SELECT COUNT(*) as total FROM tbl_fact 
								   WHERE YEAR(fecha_contabilizacion) = :anio_cont 
								   AND YEAR(fecha_emision) = :anio_emi";
				
				$stmt_count = $db->prepare($sql_count_anio);
				
				// Pasamos el mismo valor ($anio_seleccionado) a ambos marcadores
				$stmt_count->execute([
					'anio_cont' => $anio_seleccionado,
					'anio_emi'  => $anio_seleccionado
				]);
				
				$count_anio = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
				echo $count_anio;
			?></span> Facturas/<span id="anioSeleccionadoBadge"><?php echo $anio_seleccionado; ?></span>
		</span>
        </span>
    </div>
</div>            <div class="card-body">
                <div class="row align-items-center">
                    <!-- Columna izquierda: Selector y búsqueda -->
                    <div class="col-md-8">
                        <div class="row g-3">
                            <!-- Búsqueda por número -->
                            <div class="col-lg-6 col-md-12">
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-hashtag"></i>
                                    </span>
                                    <input type="text" class="form-control" id="buscarNumeroFactura" 
                                           placeholder="Buscar por número de factura..." 
                                           onkeyup="buscarFacturas()">
                                    <button class="btn btn-outline-primary" type="button" onclick="buscarFacturas()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Búsqueda por cliente -->
                            <div class="col-lg-6 col-md-12">
                                <div class="input-group">
                                    <span class="input-group-text bg-warning text-white">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="buscarCliente" 
                                           placeholder="Buscar por nombre de cliente..."
                                           onkeyup="buscarFacturas()">
                                    <button class="btn btn-outline-warning" type="button" onclick="buscarFacturas()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Selector de facturas -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="selectFactura" class="form-label fw-bold">
                                        <i class="fas fa-file-invoice me-1"></i>Seleccionar Factura
                                    </label>
                                    <select class="form-select" id="selectFactura" onchange="cargarInfoFactura()" size="5">
                                        <option value="" disabled selected>-- Seleccione una factura --</option>
                                        <?php
                                        // Obtener lista de facturas recientes para el selector
                                        $sql_facturas_selector = "SELECT f.*, c.nombre as cliente_nombre, 
										 DATE_FORMAT(f.fecha_pago, '%d/%m/%Y') as fecha_pago_formatted,
										 f.Ref_pago
										 FROM tbl_fact f 
										 LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
										 WHERE YEAR(f.fecha_emision) = :anio
										 ORDER BY f.fecha_emision DESC LIMIT 100";
                                        try {
                                            $stmt = $db->prepare($sql_facturas_selector);
											$stmt->execute(['anio' => $anio_seleccionado]); 
											$facturas_selector = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($facturas_selector)) {
                                                echo '<option value="" disabled>No hay facturas disponibles</option>';
                                            } else {
                                                foreach ($facturas_selector as $factura) {
													$fecha = date('d/m/Y', strtotime($factura['fecha_emision']));
													$fechacont = date('d/m/Y', strtotime($factura['fecha_emision']));
													$estado_badge = '';
													
													switch ($factura['estado']) {
														case 'CONTABILIZADA':
															$estado_badge = 'bg-success';
															break;
														case 'PENDIENTE':
															$estado_badge = 'bg-warning';
															break;
														case 'ANULADA':
															$estado_badge = 'bg-danger';
															break;
														case 'PAGADA':
															$estado_badge = 'bg-primary';
															break;
														case 'CERRADA':
															$estado_badge = 'bg-secondary';
															break;
														default:
															$estado_badge = 'bg-secondary';
															break;
													}
													
													$cliente_nombre = htmlspecialchars($factura['cliente_nombre'] ?? 'Cliente no especificado');
													$display_text = "{$factura['no_fact']} - {$cliente_nombre} - {$fecha} - {$factura['total_general']} CUP";
													
													
													 // AGREGAR DATOS DE PAGO SI EXISTEN
														$fecha_pago = !empty($factura['fecha_pago_formatted']) ? $factura['fecha_pago_formatted'] : '';
														$ref_pago = !empty($factura['Ref_pago']) ? htmlspecialchars($factura['Ref_pago']) : '';

                                                    echo '<option value="' . $factura['id'] . '" 
															data-numero="' . $factura['no_fact'] . '"
															data-cliente="' . $cliente_nombre . '"
															data-fecha="' . $fecha . '"
															data-fechacont="' . $fechacont . '"
															data-total="' . $factura['total_general'] . '"
															data-estado="' . $factura['estado'] . '"
															data-estado-color="' . $estado_badge . '"
															data-fecha-pago="' . $factura['fecha_pago'] . '"
															data-ref-pago="' . $ref_pago . '">
															' . $display_text . '
														  </option>';
                                                }
                                            }
                                        } catch (Exception $e) {
                                            echo '<option value="" disabled>Error al cargar facturas</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-info-circle me-1"></i>Seleccione una factura para ver detalles y gestionarla
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

<!-- Columna derecha: Información de factura seleccionada -->
<div class="col-md-4">
    <!-- Tarjeta de información de factura -->
    <div class="card mica-effect border-0 shadow-sm" id="infoFacturaContainer" style="display: none; position: relative; overflow: hidden;">
        <!-- Agregar texto de fondo "PAGADA" cuando corresponda -->
        <div class="pagada-watermark" id="pagadaWatermark" style="display: none;">
            PAGADA
        </div>
        
        <div class="card-header" style="background: var(--win-bg-tertiary);">
            <h6 class="m-0 fw-bold d-flex align-items-center" style="color: var(--win-text-primary);">
                <i class="fas fa-file-invoice me-2 text-primary"></i>
                Información de Factura
            </h6>
        </div>
        
        <div class="card-body">
            <!-- Información organizada según el formato solicitado -->
            <div class="factura-info">
                <!-- Número -->
                <div class="row align-items-center mb-3">
                    <div class="col-4">
                        <strong style="color: var(--win-text-primary);">Número:</strong>
                    </div>
                    <div class="col-8 text-end">
                        <span class="badge bg-primary fs-6 px-3 py-2" id="infoNumero">#0000</span>
                    </div>
                </div>
                
                <!-- Cliente -->
                <div class="row mb-3">
                    <div class="col-12">
                        <small class="text-muted d-block mb-1">Cliente:</small>
                        <strong id="infoCliente" style="color: var(--win-text-primary); font-size: 1.1rem;">
                            Nombre del cliente
                        </strong>
                    </div>
                </div>
                
                <!-- Fechas en la misma línea -->
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="fecha-item">
                            <small class="text-muted d-block mb-1">Fecha Emisión:</small>
                            <span id="infoFecha" style="color: var(--win-text-primary); font-weight: 500;">00/00/0000</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="fecha-item">
                            <small class="text-muted d-block mb-1">Fecha Contabilización:</small>
                            <span id="infoFechaCont" style="color: var(--win-text-primary); font-weight: 500;">00/00/0000</span>
                        </div>
                    </div>
                </div>
                
                <!-- Total y Estado en la misma línea -->
                <div class="row mb-3">
                    <div class="col-7">
                        <div class="total-item">
                            <small class="text-muted d-block mb-1">Total Factura:</small>
                            <strong class="text-success fs-5" id="infoTotal">$0.00 CUP</strong>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="estado-item">
                            <small class="text-muted d-block mb-1">Estado:</small>
                            <span class="badge fs-6 px-3 py-2" id="infoEstado">PENDIENTE</span>
                        </div>
                    </div>
                </div>
                
                <!-- Línea divisoria -->
                <hr class="my-4" style="border-color: var(--win-border-color);">
                
                <!-- Botones en línea -->
                <div class="row g-2">
                    <div class="col-4">
                        <button class="btn btn-primary w-100 py-2" id="btnVerFactura" onclick="verFactura()" title="Ver la Factura Seleccionada" disabled>
                            <i class="fas fa-eye me-1"></i>
                        </button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-warning w-100 py-2" id="btnImprimirFactura" onclick="imprimirFactura()" title="Imprimir la Factura Seleccionada" disabled>
                            <i class="fas fa-print me-1"></i>
                        </button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-secondary w-100 py-2" id="btnEditarFactura" onclick="editarFactura()" title="Editar la Factura Seleccionada" disabled>
                            <i class="fas fa-edit me-1"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Línea divisoria final -->
                <hr class="mt-4 mb-2" style="border-color: var(--win-border-color);">
            </div>
        </div>
    </div>
    
    <!-- Mensaje cuando no hay factura seleccionada -->
    <div class="card bg-light" id="noFacturaSelected">
        <div class="card-body text-center py-4">
            <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
            <h6 class="text-muted">Seleccione una factura</h6>
            <small class="text-muted">Seleccione una factura de la lista para ver información y acciones disponibles.</small>
        </div>
    </div>
</div>
				
				</div>
                
                <!-- Estadísticas rápidas -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-chart-bar me-1"></i>Accesos Rápidos:
                                    </small>
                                </div>
<div class="d-flex gap-2">
    <!-- Enlace dinámico que se actualizará con JavaScript -->
    <a href="#" target="_blank" id="btnVerTodasFacturasAnio" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-list me-1"></i>Ver Todas las Facturas (<span id="anioSeleccionadoEnlace"><?php echo $anio_seleccionado; ?></span>)
    </a>
    <a href="nueva_factura.php" class="btn btn-sm btn-primary">
        <i class="fas fa-plus me-1"></i>Nueva Factura
    </a>
</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



</div>

<!-- CSS adicional para asegurar consistencia -->
<style>
/* Estilos para tarjetas pequeñas */
.stat-card-small {
    border-left: 4px solid;
    transition: all 0.3s ease;
    border-radius: var(--win-radius-sm);
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border-color);
}

/* Colores de borde izquierdo */
.stat-card-small .card-body[class*="text-success"] ~ .stat-card-small {
    border-left-color: #198754 !important;
}

.stat-card-small .card-body[class*="text-warning"] ~ .stat-card-small {
    border-left-color: #ffc107 !important;
}

.stat-card-small .card-body[class*="text-primary"] ~ .stat-card-small {
    border-left-color: #0d6efd !important;
}

.stat-card-small .card-body[class*="text-danger"] ~ .stat-card-small {
    border-left-color: #dc3545 !important;
}

/* Efecto hover */
.stat-card-small:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Asegurar altura fija para tarjetas pequeñas */
.stat-card-small {
    min-height: 205px;
    max-height: 205px;
    overflow: hidden;
}

/* Tarjeta grande de resumen anual */
.stat-card.info {
    min-height: 220px;
}

/* Ajustes para responsive */
@media (max-width: 768px) {
    .col-xl-3, .col-lg-6, .col-md-6 {
        margin-bottom: 0.75rem !important;
    }
    
    .stat-card-small {
        min-height: 110px;
        max-height: 110px;
    }
    
    .stat-card.info {
        min-height: 200px;
    }
}
</style>
		
		<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Panel de Ingresos -->
    <div class="col-lg-8">
        <div class="card mica-effect border-0 shadow-sm">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center border-bottom border-1 gap-3 py-3" 
                 style="background: var(--win-bg-secondary); border-color: var(--win-border-color) !important;">
                <div>
                    <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                        <i class="fas fa-chart-line me-2"></i>Gráficos de Ingresos Facturados Hasta HOY: <span class="badge bg-success"><?php echo date('d/m/Y', $timestamp_combinado); ?></span>
                    </h6>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-md-end flex-grow-1">
                    <!-- Botones de período -->
                    <div class="btn-group btn-group-sm flex-nowrap shadow-sm" role="group" style="overflow-x: auto; max-width: 100%; display: flex; scrollbar-width: none; -ms-overflow-style: none; padding-bottom: 2px;">
                        <?php
                        // Calcular estadísticas para el AÑO
                        $anio_actual = $anio_cierre_num;
                        $mes_actual_num =$mes_cierre_num;
                        $progreso_anual_tiempo = round(($mes_actual / 12) * 100);
    
						$anio_anterior = $anio_actual - 1;
						$sql_ingresos_anio_anterior = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact WHERE YEAR(fecha_emision) = :anio AND estado IN ('CONTABILIZADA','PAGADA')";
						$stmt = $db->prepare($sql_ingresos_anio_anterior);
						$stmt->execute(['anio' => $anio_anterior]);
						$ingresos_anio_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
						
						$variacion_anual = $ingresos_anio_anterior > 0 ? 
							(($ingresos_anual_total - $ingresos_anio_anterior) / $ingresos_anio_anterior) * 100 : 0;
						
						$proyeccion_anual = $progreso_anual_tiempo > 0 ? 
							($ingresos_anual_total / $progreso_anual_tiempo) * 100 : 0;
						
						$promedio_mensual_anual = $mes_actual > 0 ? 
							$ingresos_anual_total / $mes_actual : 0;
                        
                        // Calcular TODOS los meses del año para encontrar mejor/peor mes
                         // Mejor y Peor Mes (CORREGIDO con :anio)
						$sql_meses_anio = "SELECT 
							MONTH(fecha_emision) as mes_num,
							COALESCE(SUM(total_general), 0) as total
							FROM tbl_fact 
							WHERE YEAR(fecha_emision) = :anio
							AND estado IN ('CONTABILIZADA','PAGADA')
							GROUP BY MONTH(fecha_emision)";
						
						$stmt = $db->prepare($sql_meses_anio);
						$stmt->execute(['anio' => $anio_actual]);
						$meses_anio_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Inicializar array con todos los meses
                        $ingresos_por_mes = array_fill(1, 12, 0);
                        foreach ($meses_anio_data as $mes_data) {
                            $ingresos_por_mes[$mes_data['mes_num']] = $mes_data['total'];
                        }
                        
                        // Encontrar mejor y peor mes
                        $mejor_mes_num = 0;
                        $peor_mes_num = 0;
                        $mejor_mes_total = 0;
                        $peor_mes_total = PHP_FLOAT_MAX;
                        
                        foreach ($ingresos_por_mes as $mes_num => $total) {
                            if ($total > $mejor_mes_total) {
                                $mejor_mes_total = $total;
                                $mejor_mes_num = $mes_num;
                            }
                            if ($total > 0 && $total < $peor_mes_total) {
                                $peor_mes_total = $total;
                                $peor_mes_num = $mes_num;
                            }
                        }
                        
                        $mejor_mes_nombre = $mejor_mes_num > 0 ? $meses_completos[$mejor_mes_num - 1] : '--';
                        $peor_mes_nombre = $peor_mes_num > 0 ? $meses_completos[$peor_mes_num - 1] : '--';
                        
                        // Para el tooltip del botón anual
                        $mes_top_nombre = $mejor_mes_nombre;
                        $mes_top_total = $mejor_mes_total;
                        ?>
                        
                        <!-- Botón ANUAL -->
<button type="button" class="btn btn-outline-primary d-flex align-items-center justify-content-center py-2 px-3 active" 
        data-period="year"
        style="
            border-color: var(--win-border-color); 
            color: var(--win-text-secondary); 
            min-width: 100px;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--win-bg-secondary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--win-border-color);
            padding: 6px 10px;
        "
        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.08)'; this.style.borderColor='var(--win-accent)';"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(0, 0, 0, 0.05)'; this.style.borderColor='var(--win-border-color)';"
        title="<?php
// Obtener datos de los últimos 5 años DESDE el año de cierre hacia atrás
$anio_referencia = $anio_cierre ?? $anio_actual; // Este es el año de cierre de la BD
$ultimos_5_anios = [];
$totales = [];

// Obtener datos desde año_referencia-4 hasta año_referencia (5 años)
for ($i = 4; $i >= 0; $i--) {
    $anio = $anio_referencia - $i;
    $sql_ingresos = "SELECT COALESCE(SUM(total_general), 0) as total, COUNT(*) as facturas 
                     FROM tbl_fact 
                     WHERE YEAR(fecha_emision) = :anio
                     AND estado IN ('CONTABILIZADA', 'PAGADA')";
    
    $stmt = $db->prepare($sql_ingresos);
    $stmt->execute(['anio' => $anio]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_anio = $datos['total'] ?? 0;
    $ultimos_5_anios[$anio] = [
        'total' => $total_anio,
        'facturas' => $datos['facturas'] ?? 0,
        'promedio_mensual' => $total_anio > 0 ? $total_anio / 12 : 0
    ];
    
    $totales[] = $total_anio;
}

// Calcular estadísticas de los 5 años
if (!empty($ultimos_5_anios)) {
    $totales_array = array_column($ultimos_5_anios, 'total');
    $anios_keys = array_keys($ultimos_5_anios);
    
    // Encontrar mejor y peor año
    $max_val = max($totales_array);
    $min_val = min($totales_array);
    
    $mejor_anio_key = array_search($max_val, $totales_array);
    $peor_anio_key = array_search($min_val, $totales_array);
    
    $mejor_anio = $anios_keys[$mejor_anio_key] ?? 'S/D';
    $peor_anio = $anios_keys[$peor_anio_key] ?? 'S/D';
    
    $promedio_5_anios = array_sum($totales_array) / 5;
    
    echo "📊 RESUMEN 5 AÑOS:\n";
    echo "────────────\n";
    echo "🏆 Mejor año: " . $mejor_anio . " ($" . number_format($max_val, 2) . ")\n";
    echo "📉 Peor año: " . $peor_anio . " ($" . number_format($min_val, 2) . ")\n";
    echo "📊 Promedio: $" . number_format($promedio_5_anios, 2) . "\n";
    echo "💰 Total 5 años: $" . number_format(array_sum($totales_array), 2) . "\n";
    
    // Calcular crecimiento total
    $primer_año_val = $totales_array[0] ?? 0;
    $ultimo_año_val = $totales_array[4] ?? 0;
    
    if ($primer_año_val > 0) {
        $crecimiento_total = (($ultimo_año_val - $primer_año_val) / $primer_año_val) * 100;
        $tendencia_icono = $crecimiento_total >= 0 ? '↗ ' : '↘ ';
        echo "📈 Tendencia: " . $tendencia_icono . number_format(abs($crecimiento_total), 1) . "%\n";
    }
    
    echo "\n📅 PERÍODO: " . ($anio_referencia-4) . " - " . $anio_referencia;
}
?>">
    <div class="d-flex align-items-center w-100">
        <!-- Icono minimalista -->
        <div style="
            width: 28px;
            height: 28px;
            background: rgba(var(--win-accent-rgb, 13, 110, 253), 0.08);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(var(--win-accent-rgb, 13, 110, 253), 0.15);
            margin-right: 8px;
            flex-shrink: 0;
        ">
            <i class="fas fa-chart-bar" style="color: var(--win-accent); font-size: 12px;"></i>
        </div>
        
        <!-- Contenido compacto -->
        <div class="text-start flex-grow-1" style="min-width: 0;">
            <!-- Título y tendencia en línea -->
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span style="
                    color: var(--win-text-primary); 
                    font-weight: 600;
                    font-size: 12px;
                    white-space: nowrap;
                ">
                    5 Años
                </span>
                <?php
                // Calcular tendencia
                $tendencia_html = '';
                if (!empty($totales) && count($totales) >= 2) {
                    $primer_año_val = $totales[0] ?? 0;
                    $ultimo_año_val = $totales[4] ?? 0;
                    
                    if ($primer_año_val > 0) {
                        $crecimiento = (($ultimo_año_val - $primer_año_val) / $primer_año_val) * 100;
                        $icono = $crecimiento >= 0 ? '↗' : '↘';
                        $color = $crecimiento >= 0 ? '#198754' : '#dc3545';
                        $tendencia_html = '<span style="color: ' . $color . '; font-weight: 700; font-size: 10px;">' . $icono . ' ' . number_format(abs($crecimiento), 0) . '%</span>';
                    } elseif ($ultimo_año_val > 0) {
                        $tendencia_html = '<span style="color: #198754; font-weight: 700; font-size: 10px;">↗ ∞%</span>';
                    } else {
                        $tendencia_html = '<span style="color: #6c757d; font-weight: 700; font-size: 10px;">→ 0%</span>';
                    }
                } else {
                    $tendencia_html = '<span style="color: #6c757d; font-weight: 700; font-size: 10px;">→ 0%</span>';
                }
                ?>
                <div style="
                    background: rgba(var(--win-accent-rgb, 13, 110, 253), 0.05);
                    padding: 1px 4px;
                    border-radius: 4px;
                    border: 1px solid rgba(var(--win-accent-rgb, 13, 110, 253), 0.1);
                ">
                    <?php echo $tendencia_html; ?>
                </div>
            </div>
            
            <!-- Rango de años compacto -->
            <div class="d-flex align-items-center mb-1" style="gap: 4px;">
                <span style="
                    font-size: 9px;
                    color: #6c757d;
                    font-weight: 500;
                    white-space: nowrap;
                ">
                    <?php echo ($anio_referencia-4); ?>
                </span>
                
                <!-- Línea simple -->
                <div style="
                    flex-grow: 1;
                    height: 1px;
                    background: linear-gradient(90deg, #6c757d, #0078d4);
                    position: relative;
                ">
                    <div style="
                        position: absolute;
                        left: 25%;
                        top: -1px;
                        width: 2px;
                        height: 3px;
                        background: #ff8c00;
                        border-radius: 1px;
                    "></div>
                    <div style="
                        position: absolute;
                        left: 50%;
                        top: -1px;
                        width: 2px;
                        height: 3px;
                        background: #e81123;
                        border-radius: 1px;
                    "></div>
                    <div style="
                        position: absolute;
                        left: 75%;
                        top: -1px;
                        width: 2px;
                        height: 3px;
                        background: #107c10;
                        border-radius: 1px;
                    "></div>
                </div>
                
                <span style="
                    font-size: 9px;
                    color: #0078d4;
                    font-weight: 700;
                    white-space: nowrap;
                ">
                    <?php echo $anio_referencia; ?>
                </span>
            </div>
            
            <!-- Indicador de período -->
            <div style="
                font-size: 8px;
                color: var(--win-text-secondary);
                opacity: 0.7;
                text-align: center;
                margin-top: 2px;
                letter-spacing: 0.2px;
            ">
                <?php echo ($anio_referencia-4) . '-' . substr($anio_referencia, -2); ?>
            </div>
        </div>
    </div>
</button>
                        <?php
                        // Calcular estadísticas para el TRIMESTRE
                        $trimestre_nombre = $trimestres[$trimestre_actual - 1];
                        
                        $meses_trimestre = [];
                        switch($trimestre_actual) {
                            case 1: $meses_trimestre = ['Enero', 'Febrero', 'Marzo']; break;
                            case 2: $meses_trimestre = ['Abril', 'Mayo', 'Junio']; break;
                            case 3: $meses_trimestre = ['Julio', 'Agosto', 'Septiembre']; break;
                            case 4: $meses_trimestre = ['Octubre', 'Noviembre', 'Diciembre']; break;
                        }
                        
                        $mes_actual_num = date('n');
                        $mes_en_trimestre = ($mes_actual_num - 1) % 3 + 1;
                        $progreso_trimestre_tiempo = round(($mes_en_trimestre / 3) * 100);
                        
                        $trimestre_anterior = $trimestre_actual > 1 ? $trimestre_actual - 1 : 4;
                        $anio_trimestre_anterior = $trimestre_actual > 1 ? date('Y') : date('Y') - 1;
                        
                        $sql_ingresos_trimestre_anterior = "SELECT COALESCE(SUM(total_general), 0) as total FROM tbl_fact WHERE QUARTER(fecha_emision) = :trimestre AND YEAR(fecha_emision) = :anio AND estado IN ('CONTABILIZADA','PAGADA')";
    $stmt = $db->prepare($sql_ingresos_trimestre_anterior);
    $stmt->execute(['trimestre' => $trimestre_anterior, 'anio' => $anio_trimestre_anterior]);
    $ingresos_trimestre_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $variacion_trimestral = $ingresos_trimestre_anterior > 0 ? (($ingresos_trimestre - $ingresos_trimestre_anterior) / $ingresos_trimestre_anterior) * 100 : 0;
                        
                        $proyeccion_trimestral = $progreso_trimestre_tiempo > 0 ? 
                            ($ingresos_trimestre / $progreso_trimestre_tiempo) * 100 : 0;
                        
                        $promedio_mensual_trimestre = $mes_en_trimestre > 0 ? 
                            $ingresos_trimestre / $mes_en_trimestre : 0;
                        
                          $sql_facturas_trimestre = "SELECT COUNT(*) as total FROM tbl_fact WHERE QUARTER(fecha_emision) = :trimestre AND YEAR(fecha_emision) = :anio AND estado IN ('CONTABILIZADA','PAGADA')";
						$stmt = $db->prepare($sql_facturas_trimestre);
						$stmt->execute(['trimestre' => $trimestre_actual, 'anio' => $anio_actual]);
						$facturas_trimestre = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                        
                        <!-- Botón TRIMESTRAL -->
                        <button type="button" class="btn btn-outline-primary d-flex align-items-center" 
                                data-period="quarter"
                                style="border-color: var(--win-border-color); color: var(--win-text-secondary);"
                                title="Q<?php echo $trimestre_actual; ?>: $<?php echo number_format($ingresos_trimestre, 2); ?> | 
Progreso: <?php echo $progreso_trimestre_tiempo; ?>% | 
Facturas: <?php echo $facturas_trimestre; ?> | 
Tendencia: <?php echo $variacion_trimestral >= 0 ? '+' : ''; ?><?php echo number_format($variacion_trimestral, 1); ?>%">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chart-pie me-2"></i>
                                <div class="text-start">
                                    <div style="color: var(--win-text-primary);">Trimestral</div>
                                    <div class="small">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge" style="background: #0dcaf0 !important;">Q<?php echo $trimestre_actual; ?></span>
                                            <span class="badge ms-1" 
                                                  style="font-size: 10px; background: <?php echo $variacion_trimestral >= 0 ? '#198754' : '#dc3545'; ?> !important;">
                                                <?php echo $variacion_trimestral >= 0 ? '↑' : '↓'; ?>
                                                <?php echo number_format(abs($variacion_trimestral), 0); ?>%
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between" style="width: 70px;">
                                            <span class="fw-bold" style="font-size: 10px; color: #198754;">
                                                $<?php echo number_format($ingresos_trimestre , 2); ?>
                                            </span>
                                            <div class="d-flex" style="gap: 2px;">
                                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                                    <div class="rounded-circle" 
                                                         style="width: 6px; height: 6px; background: <?php 
                                                             echo $i <= $mes_en_trimestre ? '#0dcaf0' : 'var(--win-border-color)'; 
                                                         ?>;"></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </button>

                        <?php
							// Calcular estadísticas para el MES
							$mes_actual_nombre = $meses_completos[$mes_actual - 1];
							$progreso_tiempo_mes = round(($dia_actual / $total_dias_mes) * 100);

							// Efectividad = Días con facturas / Días transcurridos
							$efectividad_mes = $dia_actual > 0 ? round(($dias_con_datos / $dia_actual) * 100) : 0;

							// Calcular estadísticas para DÍA 
							$proyeccion_mensual = $dia_actual > 0 ? ($ingresos_mes / $dia_actual) * $total_dias_mes : 0;
							$progreso_tiempo = round(($dia_actual / $total_dias_mes) * 100);
							$ingresos_promedio = $dia_actual > 0 ? $ingresos_mes / $dia_actual : 0;
							$progreso_vs_anterior = $ingresos_mes_anterior > 0 ? 
								round(($ingresos_mes / $ingresos_mes_anterior) * 100) : 100;
						?>
                        
                        <!-- Botón MENSUAL -->
<button type="button" class="btn btn-outline-primary d-flex align-items-center" 
        data-period="month"
        style="border-color: var(--win-border-color); color: var(--win-text-secondary);"
        title="📊 Estadísticas Mensuales
────────────
📅 Mes actual: <?php echo $mes_actual_es; ?> <?php echo $anio_actual; ?>
📈 Progreso anual: <?php echo $mes_actual; ?>/12 meses (<?php echo round(($mes_actual/12)*100); ?>%)
💰 Ingresos del mes: $<?php echo number_format($ingresos_mes, 2); ?>
📊 Ingresos año <?php echo $anio_actual; ?>: $<?php echo number_format($ingresos_anual_total, 2); ?>
🔄 vs año anterior: <?php 
    $variacion_anual = 0;
    if (isset($ingresos_anio_anterior) && $ingresos_anio_anterior > 0) {
        $variacion_anual = (($ingresos_anual_total - $ingresos_anio_anterior) / $ingresos_anio_anterior) * 100;
        echo $variacion_anual >= 0 ? '+' : '';
        echo number_format($variacion_anual, 1);
    } else {
        echo 'S/D';
    }
?>%">
    <div class="d-flex align-items-center">
        <i class="fas fa-calendar me-2"></i>
        <div>
            <div style="color: var(--win-text-primary);">
				Mensual
				<span class="badge me-2" 
                      style="background: var(--win-accent) !important;"
                      title="Año en proceso: <?php echo $mes_actual; ?>/12 meses (<?php echo round(($mes_actual/12)*100); ?>% del año)">
                     <?php echo $mes_actual; ?>/12 (<?php echo round(($mes_actual/12)*100); ?>%)
                </span>
			</div>
            <div class="d-flex align-items-center mt-1">
                <div class="d-flex flex-column gap-1">
                    <!-- Solo barra de progreso anual -->
                    <div class="d-flex align-items-center">
                        <small class="me-2" style="font-size: 9px; color: #0dcaf0;">Año</small>
                        <div class="progress flex-grow-1" style="height: 4px; background: var(--win-bg-tertiary);">
                            <div class="progress-bar" style="width: <?php echo round(($mes_actual/12)*100); ?>%; background: #0dcaf0 !important;"></div>
                        </div>
                        <small class="ms-2" style="font-size: 9px;"><?php echo round(($mes_actual/12)*100); ?>%</small>
                    </div>
                    
                    <!-- Indicador de ingresos anuales -->
                    <div class="text-center">
                        <small style="font-size: 9px; color: #198754; font-weight: bold;">
                            $<?php echo number_format($ingresos_anual_total, 0); ?>
                        </small>
                        <small style="font-size: 8px; color: var(--win-text-secondary); display: block;">
                            Año <?php echo $anio_actual; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</button>
                        <?php
                        // Calcular estadísticas para DÍA
                        $ingresos_mes_actual = $ingresos_mes;
						$progreso_vs_anterior = $ingresos_mes_anterior > 0 ? 
							round(($ingresos_mes / $ingresos_mes_anterior) * 100) : 100;

						$proyeccion_mensual = $dia_actual > 0 ? ($ingresos_mes / $dia_actual) * $total_dias_mes : 0;

						$progreso_proyeccion = $proyeccion_mensual > 0 ? 
							round(($ingresos_mes / $proyeccion_mensual) * 100) : 0;

						$meta_mensual = 0;
						$progreso_meta = $meta_mensual > 0 ? 
							round(($ingresos_mes / $meta_mensual) * 100) : 0;

						$progreso_tiempo = round(($dia_actual / $total_dias_mes) * 100);
						$ingresos_promedio = $dia_actual > 0 ? $ingresos_mes / $dia_actual : 0;
                        ?>
                        
                        <!-- Botón DIARIO -->
                        <button type="button" class="btn btn-outline-primary d-flex align-items-center" 
        data-period="day"
        style="border-color: var(--win-border-color); color: var(--win-text-secondary);"
                                title="📊 Estadísticas Diarias
────────────
📅 Días transcurridos: <?php echo $dia_actual; ?>/<?php echo $total_dias_mes; ?> (<?php echo $progreso_tiempo; ?>%)
✅ Días con facturas: <?php echo $dias_con_datos; ?> (<?php echo $porcentaje_datos; ?>%)
💰 Ingresos del mes: $<?php echo number_format($ingresos_mes, 2); ?>
📈 Proyección mensual: $<?php echo number_format($proyeccion_mensual, 2); ?>
📊 Promedio diario: $<?php echo number_format($ingresos_promedio, 2); ?>
🔄 vs mes anterior: <?php echo $variacion_ingresos >= 0 ? '+' : ''; ?><?php echo number_format($variacion_ingresos, 1); ?>%">
<div class="d-flex align-items-center">
    <i class="fas fa-calendar-day me-2"></i>
    <div style="flex: 1;">
        <div style="color: var(--win-accent);">Diario</div>
        <div class="mt-1">
            <span class="badge mb-1 d-inline-block" 
                  style="background: #198754 !important;"
                  title="<?php echo $dias_con_datos; ?> días con facturas de <?php echo $hoy; ?> transcurridos">
                <?php echo $dias_con_datos; ?>/<?php echo $hoy; ?>
            </span>
            <div class="mb-1" style="font-size: 0.75rem; color: var(--win-text-secondary);">
                <?php echo $dias_con_datos; ?> días con facturas
            </div>
            <div class="progress" style="height: 8px; background: var(--win-bg-tertiary);" 
                 title="<?php echo $porcentaje_datos; ?>% de efectividad">
                <div class="progress-bar" 
                     style="width: <?php echo $porcentaje_datos; ?>%; background: #198754 !important;">
                    <?php if ($porcentaje_datos > 30): ?>
                        <small class="position-absolute" 
                               style="font-size: 6px; left: 50%; transform: translateX(-50%); color: white;">
                            <?php echo $porcentaje_datos; ?>%
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
                        </button>

                        <?php
                        // Calcular rango de días de la semana actual
                        $dia_inicio_semana = (($semana_actual - 1) * 7) + 1;
                        $dia_fin_semana = min($dia_inicio_semana + 6, date('t'));
                        
                        $fecha_inicio = date('d/m', mktime(0, 0, 0, $mes_actual, $dia_inicio_semana, $anio_actual));
                        $fecha_fin = date('d/m', mktime(0, 0, 0, $mes_actual, $dia_fin_semana, $anio_actual));
                        
                        // Calcular semanas REALES con datos en el mes
                        $semanas_reales = [];
                        for ($i = 0; $i < $semanas_en_mes; $i++) {
                            if (isset($datos_semanales[$i]) && $datos_semanales[$i] > 0) {
                                $semanas_reales[] = $i + 1;
                            }
                        }
                        $total_semanas_reales = count($semanas_reales);
                        ?>
                        
                        <!-- Botón SEMANAL -->
                        <button type="button" class="btn btn-outline-primary d-flex align-items-center" 
                                data-period="week"
                                style="border-color: var(--win-border-color); color: var(--win-text-secondary);"
                                title="Estas en la Semana <?php echo $semana_actual; ?>: <?php echo $fecha_inicio; ?> al <?php echo $fecha_fin; ?>">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar-week me-2"></i>
                                <div class="text-start">
                                    <div style="color: var(--win-text-primary);">Semanal</div>
                                    <div class="small">
                                        <div class="small fw-bold" style="color: #ffc107;">
                                            Sem <?php echo $semana_actual; ?> / <?php echo $semanas_en_mes; ?>
                                        </div>
                                        <small style="color: var(--win-text-secondary);">(<?php echo $fecha_inicio; ?>-<?php echo $fecha_fin; ?>)</small>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <!-- Botones de exportación -->
                    <div class="export-buttons d-flex gap-2 ms-md-2">
						<button type="button" class="btn btn-sm btn-anim-export" 
								onclick="exportChart('ingresosChart', 'png')" 
								title="Exportar como PNG"
								style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
							<i class="fas fa-file-image"></i>
						</button>
						<button type="button" class="btn btn-sm btn-anim-export pdf-btn" 
								onclick="exportChart('ingresosChart', 'pdf')" 
								title="Exportar como PDF"
								style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
							<i class="fas fa-file-pdf"></i>
						</button>
						<button type="button" class="btn btn-sm btn-anim-export json-btn" 
								onclick="exportChartData('ingresosChart', 'json')" 
								title="Exportar datos JSON"
								style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
							<i class="fas fa-code"></i>
						</button>
					</div>
                </div>
            </div>
            
            <div class="card-body" style="background: var(--win-bg-secondary);">
                <!-- Gráfico -->
                <div class="chart-container mb-3" style="height: 250px;">
                    <canvas id="ingresosChart"></canvas>
                </div>
                
                <!-- Tabla de datos -->
                <div class="datos-tabla-container mt-4">
                    <div class="datos-tabla-toggle p-3 rounded-2" 
                         onclick="toggleTablaDatos('ingresos')"
                         style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); cursor: pointer;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="color: var(--win-text-primary);">
                                <i class="fas fa-table me-2"></i>
                                <strong>Ver datos en tabla</strong>
                                <small class="ms-2" style="color: var(--win-text-secondary);">(Haz clic para expandir/contraer)</small>
                            </span>
                            <i class="fas fa-chevron-down" id="tablaIngresosIcon" style="color: var(--win-text-secondary);"></i>
                        </div>
                    </div>
                    
                    <div class="datos-tabla-content mt-2" id="tablaIngresosData" style="display: none;">
                        <!-- Contenido de tablas -->
						<div class="tab-content">
							<!-- Tabla para vista ANUAL (5 años) -->
							<div class="table-responsive" id="tablaDatosAnuales" style="display: none;">
								<table class="table table-sm table-hover mb-0">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="5">
            <div class="table-header-with-export">
                <span class="table-title">
                    <i class="fas fa-calendar-alt me-2"></i>Datos Anuales (Últimos 5 Años)
                </span>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaDatosAnuales', 'datos_anuales')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaDatosAnuales', 'datos_anuales')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaDatosAnuales', 'datos_anuales')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaDatosAnuales', 'datos_anuales', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaDatosAnuales')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);"><i class="fas fa-calendar-alt me-1"></i> Año</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-dollar-sign me-1"></i> Ingresos</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-percentage me-1"></i> Variación</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-chart-line me-1"></i> Tendencia</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-info-circle me-1"></i> Estado</th>
    </tr>
</thead>
									<tbody>
										<?php
										// Calcular datos para la tabla
										$anios_para_tabla = array_reverse($anios_comparacion); // Del más reciente al más antiguo
										
										$prev_year_total = null;
										foreach ($anios_para_tabla as $index => $anio):
											$total_anio = $ingresos_ultimos_5_anios[$anio] ?? 0;
											$variacion = '';
											$variacion_class = '';
											
											if ($prev_year_total !== null && $prev_year_total > 0) {
												$variacion_porcentaje = (($total_anio - $prev_year_total) / $prev_year_total) * 100;
												$variacion = number_format($variacion_porcentaje, 1) . '%';
												$variacion_class = $variacion_porcentaje >= 0 ? 'text-success' : 'text-danger';
											}
											
											$prev_year_total = $total_anio;
											$es_actual = $anio == $anio_actual;
										?>
											<tr <?php echo $es_actual ? 'style="background: var(--win-accent-light);"' : ''; ?>>
												<td>
													<span class="fw-bold" style="color: var(--win-text-primary);">
														<?php echo $anio; ?>
														<?php if ($es_actual): ?>
															<span class="badge bg-primary ms-1">ACTUAL</span>
														<?php endif; ?>
													</span>
												</td>
												<td class="text-end fw-bold" style="color: #198754;">
													$<?php echo number_format($total_anio, 2); ?>
												</td>
												<td class="text-end <?php echo $variacion_class; ?>">
													<?php echo $variacion ?: '---'; ?>
												</td>
												<td class="text-end">
													<!-- Podrías agregar aquí un icono de tendencia -->
												</td>
												<td>
													<?php if ($es_actual): ?>
														<span class="badge bg-primary">En Curso</span>
													<?php else: ?>
														<span class="badge bg-secondary">Cerrado</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
<!-- Tabla para vista DIARIA - CORREGIDA -->
<div class="table-responsive" id="tablaDatosDiarios">
    <table class="table table-sm table-hover mb-0">
        <thead style="background: var(--win-bg-tertiary);">
            <tr>
                <th colspan="5">
                    <div class="table-header-with-export">
                        <span class="table-title">
                            <i class="fas fa-calendar-day me-2"></i>Datos Diarios - <?php echo $mes_actual_es . ' ' . $anio_actual; ?>
                        </span>
                        <div class="btn-group-export">
                            <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaDatosDiarios', 'datos_diarios')" title="Exportar a Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                            <button class="btn btn-export-table word" onclick="exportTableToWord('tablaDatosDiarios', 'datos_diarios')" title="Exportar a Word">
                                <i class="fas fa-file-word"></i>
                            </button>
                            <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaDatosDiarios', 'datos_diarios')" title="Exportar a PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaDatosDiarios', 'datos_diarios', 'jpg')" title="Exportar a JPG">
                                <i class="fas fa-file-image"></i>
                            </button>
                            <button class="btn btn-export-table print" onclick="printTable('tablaDatosDiarios')" title="Imprimir tabla">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                </th>
            </tr>
            <tr>
                <th style="color: var(--win-text-primary);"><i class="fas fa-calendar-day me-1"></i> Día</th>
                <th class="text-center" style="color: var(--win-text-primary);"><i class="fas fa-file-invoice me-1"></i> Cant.</th>
                <th style="color: var(--win-text-primary);"><i class="fas fa-calendar me-1"></i> Fecha</th>
                <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-dollar-sign me-1"></i> Ingresos</th>
                <th style="color: var(--win-text-primary);"><i class="fas fa-info-circle me-1"></i> Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_diario = 0;
            $total_cantidad = 0;
            
            // USAR LAS VARIABLES DE CIERRE DE OPERACIONES
            $ultimo_dia_mes = $total_dias_mes;  // ← 31 para marzo, 30 para abril, etc.
            $mes_operaciones = $mes_actual;
            $anio_operaciones = $anio_actual;
            
            // Encontrar mejor y peor día
            $mejor_dia = null;
            $peor_dia_con_datos = null;
            $mejor_dia_total = 0;
            $peor_dia_total = PHP_FLOAT_MAX;
            
            $todos_los_dias = [];
            for ($dia = 1; $dia <= $ultimo_dia_mes; $dia++) {
                $datos_dia_total = $datos_diarios[$dia] ?? 0;
                $datos_dia_cantidad = $cantidades_diarias[$dia] ?? 0;
                
                $todos_los_dias[$dia] = [
                    'dia' => $dia,
                    'fecha' => date('d/m/Y', mktime(0, 0, 0, $mes_operaciones, $dia, $anio_operaciones)),
                    'total' => $datos_dia_total,
                    'cantidad' => $datos_dia_cantidad,
                    'estado' => ($datos_dia_total > 0) ? 'con_datos' : 'sin_datos'
                ];
                
                $total_diario += $datos_dia_total;
                $total_cantidad += $datos_dia_cantidad;
                
                // Buscar mejor día
                if ($datos_dia_total > $mejor_dia_total) {
                    $mejor_dia_total = $datos_dia_total;
                    $mejor_dia = $todos_los_dias[$dia];
                }
                
                // Buscar peor día con datos (solo si tiene facturas)
                if ($datos_dia_total > 0 && $datos_dia_total < $peor_dia_total) {
                    $peor_dia_total = $datos_dia_total;
                    $peor_dia_con_datos = $todos_los_dias[$dia];
                }
            }
            
            if (empty($todos_los_dias)): ?>
                <tr>
                    <td colspan="5" class="text-center py-3">
                        <i class="fas fa-calendar-times fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                        No hay datos diarios disponibles
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($todos_los_dias as $dia): ?>
                    <tr>
                        <td style="color: var(--win-text-primary);">
                            Día <?php echo $dia['dia']; ?>
                            <?php if ($mejor_dia && $dia['dia'] == $mejor_dia['dia']): ?>
                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;">🏆 Mejor</span>
                            <?php elseif ($peor_dia_con_datos && $dia['dia'] == $peor_dia_con_datos['dia']): ?>
                                <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">📉 Peor</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($dia['cantidad'] > 0): ?>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo $dia['cantidad']; ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--win-text-secondary); opacity: 0.5;">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--win-text-primary);"><?php echo $dia['fecha']; ?></td>
                        <td class="text-end fw-bold" style="color: <?php echo $dia['total'] > 0 ? '#198754' : 'var(--win-text-secondary)'; ?>;">
                            $<?php echo number_format($dia['total'], 2); ?>
                        </td>
                        <td>
                            <?php if ($dia['total'] > 0): ?>
                                <span class="badge" style="background: #198754 !important;">
                                    <i class="fas fa-check me-1"></i>Con facturas (<?php echo $dia['cantidad']; ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--win-bg-tertiary) !important; color: var(--win-text-secondary);">
                                    <i class="fas fa-times me-1"></i>Sin facturas
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Fila de total -->
                <tr style="background: var(--win-bg-tertiary);">
                    <td colspan="2" class="text-end fw-bold" style="color: var(--win-text-primary);">
                        Total del mes:
                        <small class="d-block" style="color: var(--win-text-secondary);"><?php echo $ultimo_dia_mes; ?> días</small>
                    </td>
                    <td class="text-center fw-bold" style="color: var(--win-text-primary);">
                        <?php echo $total_cantidad; ?> Factura(s)
                    </td>
                    <td class="text-end fw-bold" style="color: var(--win-accent);">
                        $<?php echo number_format($total_diario, 2); ?>
                    </td>
                    <td>
                        <span class="badge" style="background: var(--win-accent) !important;">
                            100% del mes
                        </span>
                    </td>
                </tr>
                
                <!-- MEJOR Y PEOR DÍA -->
                <?php if ($mejor_dia && $peor_dia_con_datos): ?>
                <tr style="background: var(--win-bg-tertiary);">
                    <td colspan="5" class="p-2">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.1); border-left: 4px solid #198754;">
                                    <small style="color: var(--win-text-secondary); display: block;">🏆 Mejor día:</small>
                                    <span class="fw-bold" style="color: var(--win-text-primary);">
                                        Día <?php echo $mejor_dia['dia']; ?> (<?php echo $mejor_dia['fecha']; ?>)
                                        <small class="text-success">($<?php echo number_format($mejor_dia['total'], 2); ?>)</small>
                                    </span>
                                    <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                        <div class="progress-bar" style="width: 100%; background: #198754;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2 rounded" style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                                    <small style="color: var(--win-text-secondary); display: block;">📉 Peor día con datos:</small>
                                    <span class="fw-bold" style="color: var(--win-text-primary);">
                                        Día <?php echo $peor_dia_con_datos['dia']; ?> (<?php echo $peor_dia_con_datos['fecha']; ?>)
                                        <small class="text-danger">($<?php echo number_format($peor_dia_con_datos['total'], 2); ?>)</small>
                                    </span>
                                    <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                        <div class="progress-bar" style="width: 100%; background: #dc3545;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
							
							
							<!-- Tabla para vista SEMANAL -->
                            <div class="table-responsive" id="tablaDatosSemanales" style="display: none;">
                                <table class="table table-sm table-hover mb-0">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="7">
            <div class="table-header-with-export">
                <span class="table-title">
                    <i class="fas fa-calendar-week me-2"></i>Datos Semanales - <?php echo $mes_actual_es . ' ' . $anio_actual; ?>
                </span>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaDatosSemanales', 'datos_semanales')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaDatosSemanales', 'datos_semanales')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaDatosSemanales', 'datos_semanales')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaDatosSemanales', 'datos_semanales', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaDatosSemanales')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);"><i class="fas fa-hashtag me-1"></i> #</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-calendar-week me-1"></i> Semana</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-calendar me-1"></i> Rango de Días</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-dollar-sign me-1"></i> Ingresos</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-percentage me-1"></i> % del Mes</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-chart-line me-1"></i> Promedio/Día</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-trend-up me-1"></i> Tendencia</th>
    </tr>
</thead>
                                    <tbody>
                                        <?php
                                        // Filtrar solo semanas con datos
                                        $semanas_con_datos = [];
                                        $total_semanal = 0;
                                        
                                        for ($i = 0; $i < $semanas_en_mes; $i++) {
                                            if (isset($datos_semanales[$i])) {
												//&& $datos_semanales[$i] > 0 mostrar solo las semanas con valores lo quité salgan todas
                                                $semanas_con_datos[$i] = $datos_semanales[$i];
                                                $total_semanal += $datos_semanales[$i];
                                            }
                                        }
                                        
                                        if (empty($semanas_con_datos)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-3">
                                                    <i class="fas fa-calendar-times fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                                                    No hay datos semanales disponibles
                                                </td>
                                            </tr>
<?php else: 
    $semana_anterior = 0;
    $contador_semana = 0;
    
    foreach ($semanas_con_datos as $i => $semana): 
        $contador_semana++;
        $porcentaje = $total_semanal > 0 ? ($semana / $total_semanal) * 100 : 0;
        
        $dia_inicio = ($i * 7) + 1;
        $dia_fin = min($dia_inicio + 6, $total_dias_mes);
        $dias_semana = $dia_fin - $dia_inicio + 1;
        $promedio_diario = $dias_semana > 0 ? $semana / $dias_semana : 0;
        
        $tendencia = '';
        $tendencia_class = 'text-muted';
        
        // Calcular tendencia si hay semana anterior para comparar
        if ($contador_semana > 1 && $semana_anterior > 0) {
            $variacion = (($semana - $semana_anterior) / $semana_anterior) * 100;
            
            if ($variacion > 0) {
                $tendencia = '<i class="fas fa-arrow-up text-success me-1"></i>' . number_format($variacion, 1) . '%';
                $tendencia_class = 'text-success';
            } elseif ($variacion < 0) {
                $tendencia = '<i class="fas fa-arrow-down text-danger me-1"></i>' . number_format(abs($variacion), 1) . '%';
                $tendencia_class = 'text-danger';
            } else {
                $tendencia = '<i class="fas fa-minus text-warning me-1"></i>0%';
                $tendencia_class = 'text-warning';
            }
        } else if ($contador_semana === 1) {
            $tendencia = '<span class="text-info"><i class="fas fa-clock me-1"></i>Inicio</span>';
        }
        
        // Si es la semana 5, añadir "Fin" después de la tendencia
        if ($contador_semana === 5) {
            $tendencia .= ' <span class="text-info"><i class="fas fa-flag-checkered me-1"></i>Fin</span>';
        }
        
        $semana_anterior = $semana;
?>
                                            <tr>
                                                <td>
                                                        <span class="badge" style="background: <?php 
														echo $contador_semana == 1 ? 'var(--win-accent)' : 
															 ($contador_semana == 2 ? '#0dcaf0' : 
															  ($contador_semana == 3 ? '#198754' : 
															   ($contador_semana == 4 ? '#ffc107' : 
																($contador_semana == 5 ? '#dc3545' : 
																 ($contador_semana == 6 ? '#6f42c1' : 'var(--win-bg-tertiary)'))))); 
													?> !important;">
														<?php echo $contador_semana; ?>
													</span>
                                                </td>
                                                <td style="color: var(--win-text-primary);"><strong>Semana <?php echo $i + 1; ?></strong></td>
                                                <td style="color: var(--win-text-primary);">
                                                    <small style="color: var(--win-text-secondary);">Días:</small>
                                                    <span class="fw-bold"><?php echo $dia_inicio . ' - ' . $dia_fin; ?></span>
                                                </td>
                                                <td class="text-end fw-bold" style="color: <?php echo $semana > 0 ? '#198754' : 'var(--win-text-secondary)'; ?>;">
                                                    $<?php echo number_format($semana, 2); ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex align-items-center justify-content-end">
                                                        <div class="progress flex-grow-1" style="height: 16px; max-width: 120px; background: var(--win-bg-tertiary);">
                                                            <div class="progress-bar" 
                                                                 style="width: <?php echo min($porcentaje, 100); ?>%; background: <?php 
                                                                    echo $contador_semana == 1 ? 'var(--win-accent)' : 
                                                                         ($contador_semana == 2 ? '#0dcaf0' : '#198754'); 
                                                                 ?> !important;">
                                                            </div>
                                                        </div>
                                                        <span class="ms-2 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($porcentaje, 1); ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge" style="background: var(--win-bg-tertiary) !important; color: var(--win-text-secondary);">
                                                        $<?php echo number_format($promedio_diario, 2); ?>
                                                    </span>
                                                </td>
                                                <td class="<?php echo $tendencia_class; ?>">
                                                    <?php echo $tendencia; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <tr style="background: var(--win-bg-tertiary);">
                                            <td colspan="3" class="text-end fw-bold" style="color: var(--win-text-primary);">TOTAL DEL MES:</td>
                                            <td class="text-end fw-bold" style="color: var(--win-accent);">$<?php echo number_format($total_semanal, 2); ?></td>
                                            <td class="text-end fw-bold" style="color: var(--win-text-primary);">100%</td>
                                            <td class="text-end fw-bold" style="color: var(--win-text-primary);">
                                                $<?php echo number_format($total_semanal / max(1, count($semanas_con_datos) * 7), 2); ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                        
                                        <!-- Estadísticas adicionales -->
                                        <?php 
                                        // Calcular mejor y peor semana
                                        $mejor_semana_num = 0;
                                        $peor_semana_num = 0;
                                        $mejor_semana_total = 0;
                                        $peor_semana_total = PHP_FLOAT_MAX;
                                        
                                        foreach ($semanas_con_datos as $i => $total) {
                                            if ($total > $mejor_semana_total) {
                                                $mejor_semana_total = $total;
                                                $mejor_semana_num = $i + 1;
                                            }
                                            if ($total < $peor_semana_total) {
                                                $peor_semana_total = $total;
                                                $peor_semana_num = $i + 1;
                                            }
                                        }
                                        ?>
                                        
<div class="row g-2">
    <!-- Mejor semana -->
    <div class="col-md-4">
        <div class="p-2 rounded" style="background: rgba(var(--win-accent-rgb, 13, 110, 253), 0.1); border-left: 4px solid var(--win-accent);">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 28px; height: 28px; background: var(--win-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-crown" style="color: white; font-size: 12px;"></i>
                </div>
                <div>
                    <small style="color: var(--win-text-secondary); display: block;">Mejor semana:</small>
                    <span class="fw-bold" style="color: var(--win-text-primary);">
                        <?php echo $mejor_semana_num > 0 ? 'Semana ' . $mejor_semana_num : 'S/D'; ?>
                        <?php if ($mejor_semana_num > 0): ?>
                            <small class="text-success">($<?php echo number_format($mejor_semana_total, 2); ?>)</small>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Semana más débil -->
    <div class="col-md-4">
        <div class="p-2 rounded" style="background: rgba(220, 53, 69, 0.08); border-left: 4px solid #dc3545;">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 28px; height: 28px; background: #dc3545; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-chart-line" style="color: white; font-size: 12px;"></i>
                </div>
                <div>
                    <small style="color: var(--win-text-secondary); display: block;">Semana más débil:</small>
                    <span class="fw-bold" style="color: var(--win-text-primary);">
                        <?php echo $peor_semana_num > 0 ? 'Semana ' . $peor_semana_num : 'S/D'; ?>
                        <?php if ($peor_semana_num > 0): ?>
                            <small class="text-danger">($<?php echo number_format($peor_semana_total, 2); ?>)</small>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Promedio semanal -->
    <div class="col-md-4">
        <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.08); border-left: 4px solid #198754;">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 28px; height: 28px; background: #198754; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-balance-scale" style="color: white; font-size: 12px;"></i>
                </div>
                <div>
                    <small style="color: var(--win-text-secondary); display: block;">Promedio semanal:</small>
                    <span class="fw-bold" style="color: var(--win-text-primary);">
                        $<?php echo number_format($total_semanal / max(1, count($semanas_con_datos)), 2); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>                                       <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
						<!-- Tabla para vista MENSUAL - MEJORADA: muestra mejor y peor mes -->
                            <div class="table-responsive" id="tablaDatosMensuales" style="display: none;">
                                <table class="table table-sm table-hover mb-0">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="5">
            <div class="table-header-with-export">
                <span class="table-title">
                    <i class="fas fa-calendar-alt me-2"></i>Datos Mensuales - Año <?php echo $anio_actual; ?>
                </span>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaDatosMensuales', 'datos_mensuales')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaDatosMensuales', 'datos_mensuales')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaDatosMensuales', 'datos_mensuales')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaDatosMensuales', 'datos_mensuales', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaDatosMensuales')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);"><i class="fas fa-calendar-alt me-1"></i> Mes</th>
        <th class="text-center" style="color: var(--win-text-primary);"><i class="fas fa-file-invoice me-1"></i> Facturas</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-dollar-sign me-1"></i> Ingresos</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-percentage me-1"></i> % del Total</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-trophy me-1"></i> Ranking</th>
    </tr>
</thead>
                                    <tbody>
                                        <?php if (empty($ingresos_tabla_mensual)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-3"> <!-- Colspan cambiado a 5 -->
                                                    <i class="fas fa-calendar-times fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                                                    No hay datos mensuales disponibles
                                                </td>
                                            </tr>
                                        <?php else: 
                                            // Ordenar meses por ingresos para ranking
                                            $meses_ordenados = $ingresos_tabla_mensual;
                                            usort($meses_ordenados, function($a, $b) {
                                                return $b['total'] <=> $a['total'];
                                            });
                                            
                                            // Crear array de ranking
                                            $ranking_meses = [];
                                            foreach ($meses_ordenados as $index => $mes) {
                                                $ranking_meses[$mes['mes']] = $index + 1;
                                            }
                                            
                                            $total_anual = array_sum(array_column($ingresos_tabla_mensual, 'total'));
                                            $total_facturas_tabla = 0; // Para sumar el total de facturas
                                            
                                            foreach ($ingresos_tabla_mensual as $mes): 
                                                $porcentaje = $total_anual > 0 ? ($mes['total'] / $total_anual) * 100 : 0;
                                                $ranking = $ranking_meses[$mes['mes']] ?? 0;
                                                // Aseguramos que exista la clave cantidad (por si acaso)
                                                $cantidad_fact = isset($mes['cantidad']) ? $mes['cantidad'] : 0;
                                                $total_facturas_tabla += $cantidad_fact;
                                        ?>
                                                <tr>
                                                    <td style="color: var(--win-text-primary);"><?php echo $mes['mes']; ?></td>
                                                    
                                                    <!-- NUEVA COLUMNA DATO -->
                                                    <td class="text-center">
                                                        <span class="badge bg-success">
                                                            <?php echo $cantidad_fact; ?>
                                                        </span>
                                                    </td>
                                                    
                                                    <td class="text-end fw-bold" style="color: <?php echo $mes['total'] > 0 ? '#198754' : 'var(--win-text-secondary)'; ?>;">
                                                        $<?php echo number_format($mes['total'], 2); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="d-flex align-items-center justify-content-end">
                                                            <div class="progress flex-grow-1" style="height: 16px; max-width: 100px; background: var(--win-bg-tertiary);">
                                                                <div class="progress-bar" 
                                                                     style="width: <?php echo min($porcentaje, 100); ?>%; background: <?php 
                                                                        echo $ranking == 1 ? '#198754' : 
                                                                             ($ranking == 2 ? '#0dcaf0' : 
                                                                              ($ranking == 3 ? '#ffc107' : 'var(--win-accent)')); 
                                                                     ?> !important;">
                                                                </div>
                                                            </div>
                                                            <span class="ms-2 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($porcentaje, 1); ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($ranking == 1): ?>
                                                            <span class="badge" style="background: #198754 !important;">
                                                                <i class="fas fa-trophy me-1"></i>Mejor
                                                            </span>
                                                        <?php elseif ($ranking == count($meses_ordenados) && count($meses_ordenados) > 1): ?>
                                                            <span class="badge" style="background: #dc3545 !important;">
                                                                <i class="fas fa-arrow-down me-1"></i>Peor
                                                            </span>
                                                        <?php elseif ($ranking <= 3): ?>
                                                            <span class="badge" style="background: #0dcaf0 !important;">
                                                                #<?php echo $ranking; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: var(--win-bg-tertiary) !important; color: var(--win-text-secondary);">
                                                                #<?php echo $ranking; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr style="background: var(--win-bg-tertiary);">
                                                <td class="text-end fw-bold" style="color: var(--win-text-primary);">Total Anual:</td>
                                                <!-- TOTAL DE FACTURAS -->
                                                <td class="text-center fw-bold" style="color: var(--win-text-primary);">
                                                    <?php echo $total_facturas_tabla; ?>
                                                </td>
                                                <td class="text-end fw-bold" style="color: var(--win-accent);">
                                                    $<?php echo number_format($total_anual, 2); ?>
                                                </td>
                                                <td class="text-end fw-bold" style="color: var(--win-text-primary);">100%</td>
                                                <td>
                                                    <span class="badge" style="background: var(--win-accent) !important;">
                                                        <?php echo count($ingresos_tabla_mensual); ?> meses
                                                    </span>
                                                </td>
                                            </tr>
                                            
                                            <!-- Resumen de mejor/peor mes (El resto se mantiene igual) -->
                                            <?php 
                                            // Encontrar mejor y peor mes de los datos reales
                                            $mejor_mes_tabla = null;
                                            $peor_mes_tabla = null;
                                            
                                            foreach ($ingresos_tabla_mensual as $mes) {
                                                if ($mes['total'] > 0) {
                                                    if (!$mejor_mes_tabla || $mes['total'] > $mejor_mes_tabla['total']) {
                                                        $mejor_mes_tabla = $mes;
                                                    }
                                                    if (!$peor_mes_tabla || $mes['total'] < $peor_mes_tabla['total']) {
                                                        $peor_mes_tabla = $mes;
                                                    }
                                                }
                                            }
                                            ?>
                                            
                                            <?php if ($mejor_mes_tabla && $peor_mes_tabla): ?>
                                            <tr style="background: var(--win-bg-tertiary);">
                                                <td colspan="5" class="p-2"> <!-- Colspan cambiado a 5 -->
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.1); border-left: 4px solid #198754;">
                                                                <small style="color: var(--win-text-secondary); display: block;">🏆 Mejor mes:</small>
                                                                <span class="fw-bold" style="color: var(--win-text-primary);">
                                                                    <?php echo $mejor_mes_tabla['mes']; ?>
                                                                    <small class="text-success">($<?php echo number_format($mejor_mes_tabla['total'], 2); ?>)</small>
                                                                </span>
                                                                <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                                                    <div class="progress-bar" style="width: 100%; background: #198754;"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-2 rounded" style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                                                                <small style="color: var(--win-text-secondary); display: block;">📉 Peor mes:</small>
                                                                <span class="fw-bold" style="color: var(--win-text-primary);">
                                                                    <?php echo $peor_mes_tabla['mes']; ?>
                                                                    <small class="text-danger">($<?php echo number_format($peor_mes_tabla['total'], 2); ?>)</small>
                                                                </span>
                                                                <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                                                    <div class="progress-bar" style="width: 100%; background: #dc3545;"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
<!-- Tabla para vista TRIMESTRAL - CON MEJOR/PEOR TRIMESTRE -->
<div class="table-responsive" id="tablaDatosTrimestrales" style="display: none;">
    <table class="table table-sm table-hover mb-0">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="4">
            <div class="table-header-with-export">
                <span class="table-title">
                    <i class="fas fa-chart-pie me-2"></i>Datos Trimestrales - Año <?php echo $anio_actual; ?>
                </span>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaDatosTrimestrales', 'datos_trimestrales')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaDatosTrimestrales', 'datos_trimestrales')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaDatosTrimestrales', 'datos_trimestrales')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaDatosTrimestrales', 'datos_trimestrales', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaDatosTrimestrales')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);"><i class="fas fa-chart-pie me-1"></i> Trimestre</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-dollar-sign me-1"></i> Ingresos</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-percentage me-1"></i> % del Total</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-chart-line me-1"></i> Tendencia</th>
    </tr>
</thead>
        <tbody>
            <?php 
            if (empty($ingresos_tabla_trimestral)): ?>
                <tr>
                    <td colspan="4" class="text-center py-3">
                        <i class="fas fa-calendar-times fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                        No hay datos trimestrales disponibles
                    </td>
                </tr>
            <?php else: 
                // Filtrar solo trimestres con datos
                $trimestres_con_datos = array_filter($ingresos_tabla_trimestral, function($trimestre) {
                    return $trimestre['total'] > 0;
                });
                
                if (empty($trimestres_con_datos)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-3">
                            <i class="fas fa-calendar-times fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                            No hay datos trimestrales con ingresos
                        </td>
                    </tr>
                <?php else: 
                    $total_anual_trim = array_sum(array_column($trimestres_con_datos, 'total'));
                    $prev_total = 0;
                    $contador_trim = 0;
                    
                    // Encontrar mejor y peor trimestre
                    $mejor_trimestre = null;
                    $peor_trimestre = null;
                    $mejor_trimestre_total = 0;
                    $peor_trimestre_total = PHP_FLOAT_MAX;
                    
                    foreach ($trimestres_con_datos as $index => $trimestre) {
                        if ($trimestre['total'] > $mejor_trimestre_total) {
                            $mejor_trimestre_total = $trimestre['total'];
                            $mejor_trimestre = $trimestre;
                            $mejor_trimestre['numero'] = $index + 1;
                        }
                        if ($trimestre['total'] > 0 && $trimestre['total'] < $peor_trimestre_total) {
                            $peor_trimestre_total = $trimestre['total'];
                            $peor_trimestre = $trimestre;
                            $peor_trimestre['numero'] = $index + 1;
                        }
                    }
                    
                    foreach ($trimestres_con_datos as $index => $trimestre): 
                        $contador_trim++;
                        $porcentaje = $total_anual_trim > 0 ? ($trimestre['total'] / $total_anual_trim) * 100 : 0;
                        $tendencia = '';
                        $tendencia_class = '';
                        
                        if ($contador_trim > 1) {
                            $variacion = $prev_total > 0 ? (($trimestre['total'] - $prev_total) / $prev_total) * 100 : 0;
                            if ($variacion > 0) {
                                $tendencia = '<i class="fas fa-arrow-up text-success me-1"></i>' . number_format($variacion, 1) . '%';
                                $tendencia_class = 'text-success';
                            } elseif ($variacion < 0) {
                                $tendencia = '<i class="fas fa-arrow-down text-danger me-1"></i>' . number_format(abs($variacion), 1) . '%';
                                $tendencia_class = 'text-danger';
                            } else {
                                $tendencia = '<i class="fas fa-minus text-warning me-1"></i>0%';
                                $tendencia_class = 'text-warning';
                            }
                        } else {
                            $tendencia = '<span class="text-muted">N/A</span>';
                        }
                        $prev_total = $trimestre['total'];
            ?>
                        <tr>
                            <td style="color: var(--win-text-primary);">
                                <?php echo $trimestre['trimestre']; ?>
                                <?php if ($trimestre === $mejor_trimestre): ?>
                                    <span class="badge bg-success ms-1" style="font-size: 0.6rem;">🏆 Mejor</span>
                                <?php elseif ($trimestre === $peor_trimestre && count($trimestres_con_datos) > 1): ?>
                                    <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">📉 Peor</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold" style="color: <?php echo $trimestre['total'] > 0 ? '#198754' : 'var(--win-text-secondary)'; ?>;">
                                $<?php echo number_format($trimestre['total'], 2); ?>
                            </td>
                            <td class="text-end">
                                <span class="badge" style="background: #0dcaf0 !important;"><?php echo number_format($porcentaje, 1); ?>%</span>
                            </td>
                            <td class="<?php echo $tendencia_class; ?>">
                                <?php echo $tendencia; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: var(--win-bg-tertiary);">
                        <td class="text-end fw-bold" style="color: var(--win-text-primary);">Total Anual:</td>
                        <td class="text-end fw-bold" style="color: var(--win-accent);">
                            $<?php echo number_format($total_anual_trim, 2); ?>
                        </td>
                        <td class="text-end fw-bold" style="color: var(--win-text-primary);">100%</td>
                        <td></td>
                    </tr>
                    
                    <!-- MEJOR Y PEOR TRIMESTRE -->
                    <?php if ($mejor_trimestre && $peor_trimestre && count($trimestres_con_datos) > 1): ?>
                    <tr style="background: var(--win-bg-tertiary);">
                        <td colspan="4" class="p-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.1); border-left: 4px solid #198754;">
                                        <small style="color: var(--win-text-secondary); display: block;">🏆 Mejor trimestre:</small>
                                        <span class="fw-bold" style="color: var(--win-text-primary);">
                                            <?php echo $mejor_trimestre['trimestre']; ?>
                                            <small class="text-success">($<?php echo number_format($mejor_trimestre['total'], 2); ?>)</small>
                                        </span>
                                        <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                            <div class="progress-bar" style="width: 100%; background: #198754;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded" style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                                        <small style="color: var(--win-text-secondary); display: block;">📉 Peor trimestre:</small>
                                        <span class="fw-bold" style="color: var(--win-text-primary);">
                                            <?php echo $peor_trimestre['trimestre']; ?>
                                            <small class="text-danger">($<?php echo number_format($peor_trimestre['total'], 2); ?>)</small>
                                        </span>
                                        <div class="progress mt-1" style="height: 6px; background: var(--win-bg-tertiary);">
                                            <div class="progress-bar" style="width: 100%; background: #dc3545;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
						
						</div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Panel de Servicios -->
    <div class="col-lg-4">
        <div class="card mica-effect border-0 shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom border-1"
                 style="background: var(--win-bg-secondary); border-color: var(--win-border-color) !important;">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-star me-2"></i>Servicios Más Solicitados
                </h6>
                <div class="export-buttons d-flex gap-1">
                    <button type="button" class="btn btn-sm" 
                            onclick="exportChart('serviciosChart', 'png')" 
                            title="Exportar como PNG"
                            style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button type="button" class="btn btn-sm" 
                            onclick="exportChart('serviciosChart', 'pdf')" 
                            title="Exportar como PDF"
                            style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-sm" 
                            onclick="exportChartData('serviciosChart', 'csv')" 
                            title="Exportar datos CSV"
                            style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
                        <i class="fas fa-file-csv"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body" style="background: var(--win-bg-secondary);">
                <!-- Gráfico de servicios -->
                <div class="chart-container mb-3" style="height: 200px;">
                    <canvas id="serviciosChart"></canvas>
                </div>
                
                <!-- Tabla de servicios -->
                <div class="datos-tabla-container mt-3">
                    <div class="datos-tabla-toggle p-3 rounded-2" 
                         onclick="toggleTablaDatos('servicios')"
                         style="background: var(--win-bg-tertiary); border: 1px solid var(--win-border-color); cursor: pointer;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="color: var(--win-text-primary);">
                                <i class="fas fa-table me-2"></i>
                                <strong>Ver datos en tabla</strong>
                                <small class="ms-2" style="color: var(--win-text-secondary);">(Haz clic para expandir/contraer)</small>
                            </span>
                            <i class="fas fa-chevron-down" id="tablaServiciosIcon" style="color: var(--win-text-secondary);"></i>
                        </div>
                    </div>
                    
                    <div class="datos-tabla-content mt-2" id="tablaServiciosData" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="4">
            <div class="table-header-with-export">
                <span class="table-title">
                    <i class="fas fa-star me-2"></i>Servicios Más Solicitados
                </span>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaServiciosData', 'servicios_solicitados')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaServiciosData', 'servicios_solicitados')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaServiciosData', 'servicios_solicitados')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaServiciosData', 'servicios_solicitados', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaServiciosData')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);">#</th>
        <th style="color: var(--win-text-primary);"><i class="fas fa-list me-1"></i> Servicio</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-shopping-cart me-1"></i> Cantidad Vendida</th>
        <th class="text-end" style="color: var(--win-text-primary);"><i class="fas fa-percentage me-1"></i> % del Total</th>
    </tr>
</thead>
                                <tbody>
                                    <?php if (empty($servicios_solicitados)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3">
                                                <i class="fas fa-box-open fa-lg mb-2 d-block" style="color: var(--win-text-secondary);"></i>
                                                No hay datos de servicios disponibles
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $total_servicios = array_sum(array_column($servicios_solicitados, 'total_vendido'));
                                        $contador = 1;
                                        foreach ($servicios_solicitados as $servicio): 
                                            $porcentaje = $total_servicios > 0 ? ($servicio['total_vendido'] / $total_servicios) * 100 : 0;
                                            $descripcion = htmlspecialchars($servicio['descripcion'] ?? 'S/D');
                                            if (strlen($descripcion) > 30) {
                                                //$descripcion = substr($descripcion, 0, 27) . '...';
                                            }
                                        ?>
                                            <tr>
                                                <td>
<span class="badge" style="background: <?php 
    $colors = [
        1 => 'rgba(13, 110, 253, 0.15)',
        2 => 'rgba(13, 202, 240, 0.15)',
        3 => 'rgba(25, 135, 84, 0.15)',
        4 => 'rgba(255, 193, 7, 0.15)',
        5 => 'rgba(220, 53, 69, 0.15)'
    ];
    echo isset($colors[$contador]) ? $colors[$contador] : 'var(--win-bg-tertiary)';
?> !important; color: <?php 
    $textColors = [
        1 => 'var(--win-accent)',
        2 => '#0dcaf0',
        3 => '#198754',
        4 => '#ffc107',
        5 => '#dc3545'
    ];
    echo isset($textColors[$contador]) ? $textColors[$contador] : 'var(--win-text-secondary)';
?>; border: 1px solid <?php 
    $borderColors = [
        1 => 'var(--win-accent)',
        2 => '#0dcaf0',
        3 => '#198754',
        4 => '#ffc107',
        5 => '#dc3545'
    ];
    echo isset($borderColors[$contador]) ? $borderColors[$contador] : 'transparent';
?>;">
    <?php echo $contador; ?>
</span>
                                                </td>
                                                <td style="color: var(--win-text-primary);" title="<?php echo htmlspecialchars($servicio['descripcion'] ?? 'S/D'); ?>">
                                                    <?php echo $descripcion; ?>
                                                </td>
                                                <td class="text-end fw-bold" style="color: #198754;">
                                                    <?php echo number_format($servicio['total_vendido'], 0); ?> uds.
                                                </td>
                                                <td class="text-end">
                                                    <div class="progress" style="height: 16px; background: var(--win-bg-tertiary);">
                                                        <div class="progress-bar" 
                                                             style="width: <?php echo $porcentaje; ?>%; background: <?php 
                                                                echo $contador == 1 ? '#198754' : 
                                                                     ($contador == 2 ? '#0dcaf0' : 
                                                                      ($contador == 3 ? '#ffc107' : 'var(--win-accent)')); 
                                                             ?> !important;"
                                                             aria-valuenow="<?php echo $porcentaje; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo number_format($porcentaje, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                        $contador++;
                                        endforeach; 
                                        ?>
                                        <tr style="background: var(--win-bg-tertiary);">
                                            <td colspan="2" class="text-end fw-bold" style="color: var(--win-text-primary);">Total Vendido:</td>
                                            <td class="text-end fw-bold" style="color: var(--win-accent);">
                                                <?php echo number_format($total_servicios, 0); ?> uds.
                                            </td>
                                            <td class="text-end fw-bold" style="color: var(--win-text-primary);">100%</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
/* Estilos específicos para el dashboard */
.card {
    border-radius: var(--win-radius) !important;
    border: 1px solid var(--win-border-color) !important;
    transition: var(--win-transition);
    background: var(--win-bg-secondary) !important;
}

.card:hover {
    box-shadow: var(--win-shadow) !important;
    transform: translateY(-2px);
}

.btn-outline-primary {
    border-color: var(--win-border-color) !important;
    color: var(--win-text-secondary) !important;
    transition: var(--win-transition);
    border-radius: var(--win-radius-sm) !important;
    padding: 0.375rem 0.75rem !important;
}

.btn-outline-primary:hover {
    background: var(--win-bg-tertiary) !important;
    border-color: var(--win-accent) !important;
    color: var(--win-accent) !important;
}

.btn-outline-primary.active {
    background: var(--win-accent-light) !important;
    border-color: var(--win-accent) !important;
    color: var(--win-accent) !important;
}

.badge {
    font-weight: 500 !important;
    padding: 4px 8px !important;
    border-radius: var(--win-radius-sm) !important;
    font-size: 0.75em !important;
}

.progress {
    border-radius: var(--win-radius-sm) !important;
    overflow: hidden !important;
    background: var(--win-bg-tertiary) !important;
}

.progress-bar {
    border-radius: var(--win-radius-sm) !important;
    transition: width 0.6s ease;
}

.table {
    --bs-table-color: var(--win-text-primary) !important;
    --bs-table-bg: transparent !important;
    --bs-table-border-color: var(--win-border-color) !important;
    margin-bottom: 0 !important;
    font-size: 0.875rem !important;
}

.table thead th {
    border-bottom: 1px solid var(--win-border-color) !important;
    font-weight: 500 !important;
    background: var(--win-bg-tertiary) !important;
}

.table tbody tr {
    border-color: var(--win-border-color) !important;
    transition: var(--win-transition);
}

.table tbody tr:hover {
    background: var(--win-bg-tertiary) !important;
}

.datos-tabla-toggle {
    transition: var(--win-transition);
    border-radius: var(--win-radius-sm) !important;
}

.datos-tabla-toggle:hover {
    background: var(--win-bg-tertiary) !important;
    border-color: var(--win-accent) !important;
}

.chart-container {
    position: relative;
    background: var(--win-bg-tertiary);
    border-radius: var(--win-radius);
    padding: 15px;
    border: 1px solid var(--win-border-color);
}

/* Responsive */
@media (max-width: 1200px) {
    .btn-group .btn {
        min-width: 140px;
    }
}

@media (max-width: 992px) {
    .row > div {
        margin-bottom: 1rem !important;
    }
    
    .btn-group {
        flex-wrap: wrap;
        gap: 0.5rem !important;
    }
    
    .btn-group .btn {
        flex: 1 0 calc(50% - 0.5rem);
        min-width: 140px;
    }
    
    .chart-container {
        height: 200px !important;
    }
}

@media (max-width: 768px) {
    .card-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    
    .card-header .btn-group {
        width: 100% !important;
        justify-content: flex-start !important;
    }
    
    .card-header .export-buttons {
        align-self: flex-end !important;
        margin-top: 0.5rem;
    }
    
    .datos-tabla-toggle {
        padding: 0.75rem !important;
    }
    
    .btn-group .btn {
        flex: 1 0 100%;
    }
    
    .chart-container {
        height: 180px !important;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 1rem !important;
    }
    
    .chart-container {
        padding: 10px !important;
        height: 160px !important;
    }
    
    .table {
        font-size: 0.8rem !important;
    }
    
    .btn-group .btn {
        font-size: 0.8rem !important;
    }
}
/* ANIMACIÓN FUERTE PARA TUS BOTONES ACTUALES */

/* 1. Definimos la transición en los botones que están dentro de export-buttons */
.export-buttons button {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important; /* Efecto rebote */
    transform-origin: center;
    position: relative;
    z-index: 1;
}

/* 2. Efecto al pasar el mouse (HOVER) */
.export-buttons button:hover {
    /* El !important es necesario para sobrescribir el style="..." que tienes en el HTML */
    transform: translateY(-6px) scale(1.2) !important; /* Sube y crece notablemente */
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2) !important; /* Sombra profunda */
    border-color: var(--win-accent) !important; 
    background-color: var(--win-bg-secondary) !important;
    z-index: 10; /* Asegura que el botón animado quede encima de todo */
}

/* 3. Cambiar color del icono e ilumimarlo */
.export-buttons button:hover i {
    color: var(--win-accent) !important;
    text-shadow: 0 0 8px var(--win-accent); /* Resplandor tipo neón */
    transform: scale(1.2); /* El icono crece dentro del botón */
    transition: 0.3s;
}

/* 4. Efecto específico para el botón PDF (el del medio) para que se vea rojo al hover */
.export-buttons button:nth-child(2):hover i {
    color: #dc3545 !important; /* Rojo */
    text-shadow: 0 0 8px rgba(220, 53, 69, 0.6);
}

/* 5. Efecto al hacer clic */
.export-buttons button:active {
    transform: scale(0.9) !important; /* Se encoge al clic */
    box-shadow: none !important;
}
</style>

<script>

// Función para cambiar período activo
document.querySelectorAll('[data-period]').forEach(button => {
    button.addEventListener('click', function() {
        // Remover active de todos
        document.querySelectorAll('[data-period]').forEach(btn => {
            btn.classList.remove('active');
            btn.style.background = '';
            btn.style.color = 'var(--win-text-secondary)';
            btn.style.borderColor = 'var(--win-border-color)';
        });
        
        // Activar este botón
        this.classList.add('active');
        this.style.background = 'var(--win-accent-light)';
        this.style.color = 'var(--win-accent)';
        this.style.borderColor = 'var(--win-accent)';
        
        // Mostrar solo la tabla correspondiente al período
        const period = this.getAttribute('data-period');
        const allTables = ['Diarios', 'Semanales', 'Mensuales', 'Trimestrales'];
        
        // Ocultar todas las tablas primero
        allTables.forEach(tableType => {
            const table = document.getElementById(`tablaDatos${tableType}`);
            if (table) {
                table.style.display = 'none';
            }
        });
        
        // Mostrar la tabla correspondiente
        let targetTable = '';
        switch(period) {
            case 'day':
                targetTable = 'Diarios';
                break;
            case 'week':
                targetTable = 'Semanales';
                break;
            case 'month':
                targetTable = 'Mensuales';
                break;
            case 'quarter':
                targetTable = 'Trimestrales';
                break;
            case 'year':
                targetTable = 'Mensuales'; // El anual muestra meses
                break;
        }
        
        const tableToShow = document.getElementById(`tablaDatos${targetTable}`);
        if (tableToShow) {
            tableToShow.style.display = 'block';
        }
        
    });
});


</script>    

<!-- Nuevo Row para Categorías con Estadísticas (Activas/Inactivas) -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card mica-effect border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom border-1"
                 style="background: var(--win-bg-secondary); border-color: var(--win-border-color) !important;">
                <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                    <i class="fas fa-layer-group me-2"></i>Análisis de Categorías
                </h6>
                <div class="export-buttons d-flex gap-1">
                    <button type="button" class="btn btn-sm" onclick="exportChart('categoriasChart', 'png')" style="border-color: var(--win-border-color); color: var(--win-text-secondary);">
                        <i class="fas fa-file-image"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body" style="background: var(--win-bg-secondary);">
                
                <?php
                // 1. OBTENER TOTALES DE ACTIVAS / INACTIVAS
                $sql_cat_totales = "SELECT 
                                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activas,
                                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivas
                                    FROM clasif_cat_de_serv";
                $stmt_tot = $db->prepare($sql_cat_totales);
                $stmt_tot->execute();
                $cat_totales = $stmt_tot->fetch(PDO::FETCH_ASSOC);

                $total_activas = $cat_totales['activas'] ?? 0;
                $total_inactivas = $cat_totales['inactivas'] ?? 0;

                // 2. DATOS PARA EL TOP Y GRÁFICO
                $stat_top_cat = 'S/D';
                $stat_total_servicios_top = 0; 

                if (!empty($categorias_stats)) {
                    $stat_total_servicios_top = array_sum(array_column($categorias_stats, 'total_servicios'));
                    $stat_top_cat = $categorias_stats[0]['descripcion']; 
                }
                ?>

                <!-- BARRA DE ESTADÍSTICAS -->
<div class="row g-3 mb-4">
    <!-- Stat 1: ACTIVAS -->
    <div class="col-md-4">
        <div class="p-3 rounded-3 d-flex align-items-center justify-content-between stat-card stat-card-success">
            <div class="d-flex align-items-center">
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success p-2 me-3">
                    <i class="fas fa-check-circle fa-lg"></i>
                </div>
                <div>
                    <p class="stat-label text-success fw-semibold mb-0">Activas</p>
                </div>
            </div>
            <div class="text-end">
                <h3 class="stat-value mb-0"><?php echo $total_activas; ?></h3>
            </div>
        </div>
    </div>

    <!-- Stat 2: INACTIVAS -->
    <div class="col-md-4">
        <div class="p-3 rounded-3 d-flex align-items-center justify-content-between stat-card stat-card-danger">
            <div class="d-flex align-items-center">
                <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger p-2 me-3">
                    <i class="fas fa-ban fa-lg"></i>
                </div>
                <div>
                    <p class="stat-label text-danger fw-semibold mb-0">Inactivas</p>
                </div>
            </div>
            <div class="text-end">
                <h3 class="stat-value mb-0"><?php echo $total_inactivas; ?></h3>
            </div>
        </div>
    </div>

    <!-- Stat 3: CATEGORÍA LÍDER -->
<div class="col-md-4">
    <div class="p-3 rounded-3 stat-card stat-card-warning h-100 d-flex flex-column">
        <div class="d-flex align-items-center mb-2">
            <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning p-2 me-3">
                <i class="fas fa-trophy fa-lg"></i>
            </div>
            <div>
                <p class="stat-label text-warning fw-semibold mb-0">Más Solicitada</p>
                <p class="text-muted small mb-0">Categoría líder en solicitudes</p>
            </div>
        </div>
        
        <div class="flex-grow-1 d-flex align-items-center justify-content-center">
            <p class="category-value mb-0 text-center w-100 px-1" 
               title="<?php echo htmlspecialchars($stat_top_cat); ?>">
                <?php echo htmlspecialchars($stat_top_cat); ?>
            </p>
        </div>
    </div>
</div>
</div>
                <!-- CONTENIDO PRINCIPAL (Gráfico + Tabla) -->
                <div class="row align-items-center">
                    <!-- Gráfico (Pequeño: col-md-4) -->
                    <div class="col-md-5">
                        <div class="chart-container" style="height: 180px;">
                            <canvas id="categoriasChart"></canvas>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="fas fa-info-circle me-1"></i>Visualización Polar
                            </small>
                        </div>
                    </div>
                    
                    <!-- Tabla (Grande: col-md-8) -->
                    <div class="col-md-7">
                        
                        <!-- TÍTULO AGREGADO AQUÍ -->
                        <h6 class="mb-3 fw-bold" style="color: var(--win-text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-chart-pie me-2"></i>ANÁLISIS DE LAS 5 MÁS POPULARES
                        </h6>
                        <!-- FIN TÍTULO -->

                        <div class="table-responsive" id="tablaCategorias">
                            <table class="table table-sm table-hover mb-0 align-middle">
<thead style="background: var(--win-bg-tertiary);">
    <tr>
        <th colspan="4">
            <div class="table-header-with-export">
                <h6 class="table-title mb-0">
                    <i class="fas fa-layer-group me-2"></i>Análisis de Categorías
                </h6>
                <div class="btn-group-export">
                    <button class="btn btn-export-table excel" onclick="exportTableToExcel('tablaCategorias', 'categorias_analisis')" title="Exportar a Excel">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button class="btn btn-export-table word" onclick="exportTableToWord('tablaCategorias', 'categorias_analisis')" title="Exportar a Word">
                        <i class="fas fa-file-word"></i>
                    </button>
                    <button class="btn btn-export-table pdf" onclick="exportTableToPDF('tablaCategorias', 'categorias_analisis')" title="Exportar a PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-export-table jpg" onclick="exportTableToImage('tablaCategorias', 'categorias_analisis', 'jpg')" title="Exportar a JPG">
                        <i class="fas fa-file-image"></i>
                    </button>
                    <button class="btn btn-export-table print" onclick="printTable('tablaCategorias')" title="Imprimir tabla">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </th>
    </tr>
    <tr>
        <th style="color: var(--win-text-primary);">Categoría</th>
        <th class="text-center" style="color: var(--win-text-primary);">Cód</th>
        <th class="text-end" style="color: var(--win-text-primary);">Servicios</th>
        <th style="width: 35%;">Impacto</th>
    </tr>
</thead>
                                <tbody>
                                    <?php if (empty($categorias_stats)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No hay datos</td></tr>
                                    <?php else: 
                                        foreach ($categorias_stats as $i => $cat): 
                                            // Porcentaje respecto al total mostrado de las top 5
                                            $pct = $stat_total_servicios_top > 0 ? ($cat['total_servicios'] / $stat_total_servicios_top) * 100 : 0;
                                            $colores = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00'];
                                            $color = $colores[$i % count($colores)];
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-truncate" style="max-width: 150px; color: var(--win-text-primary);" title="<?php echo htmlspecialchars($cat['descripcion']); ?>">
                                            <i class="fas fa-circle me-2" style="font-size: 8px; color: <?php echo $color; ?>"></i>
                                            <?php echo htmlspecialchars($cat['descripcion']); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size: 0.7rem;">
                                                <?php echo $cat['codigo']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold" style="color: var(--win-text-primary);">
                                            <?php echo $cat['total_servicios']; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px; background: var(--win-bg-tertiary);">
                                                    <div class="progress-bar" style="width: <?php echo $pct; ?>%; background-color: <?php echo $color; ?>;"></div>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.7rem; width: 30px; text-align: right;">
                                                    <?php echo round($pct); ?>%
                                                </small>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
		<!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">Últimas Facturas</h6>
                        <div class="btn-group" role="group">
                            <a href="facturas.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-list me-1"></i>Ver Todas
                            </a>
                            <a href="nueva_factura.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>Nueva Factura
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-rounded">
                                <thead>
                                    <tr>
                                        <th>No. Factura</th>
                                        <th>Cliente</th>
                                        <th>F. Emis.</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="ultimasFacturasBody">
                                    <?php if (empty($ultimas_facturas)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-3">
                                                <i class="fas fa-file-invoice fa-2x text-muted mb-2 d-block"></i>
                                                No hay facturas registradas
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimas_facturas as $factura): ?>
                                            <tr>
                                                <td>
													<a href="ver_factura.php?no_fact=<?php echo urlencode($factura['no_fact']); ?>" 
													   target="_blank" 
													   class="text-white text-decoration-none" 
													   title="Ver esta factura" data-bs-toggle="tooltip">
														<span class="badge bg-primary">
															<?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?>
														</span>
													</a>
												</td>
                                                <td><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'S/D'); ?></td>
                                                <td><?php echo !empty($factura['fecha_emision']) ? date('d/m/Y', strtotime($factura['fecha_emision'])) : '-'; ?></td>
                                                <td><strong>$<?php echo number_format($factura['total_general'], 2); ?></strong></td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    switch ($factura['estado']) {
                                                        case 'CONTABILIZADA':
                                                            $badge_class = 'bg-success';
                                                            break;
                                                        case 'PENDIENTE':
                                                            $badge_class = 'bg-warning';
                                                            break;
                                                        case 'ANULADA':
                                                            $badge_class = 'bg-danger';
                                                            break;
														case 'PAGADA':
															$badge_class = 'bd-info';
														default:
															$badge_class = 'bd-secondary';
															break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($factura['estado']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="ver_factura.php?id=<?php echo $factura['id']; ?>" class="btn btn-outline-primary" title="Ver" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="editar_factura.php?id=<?php echo $factura['id']; ?>" class="btn btn-outline-warning" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="ver_factura.php?id=<?php echo $factura['id']; ?>&autoPrint=true" target="_blank" class="btn btn-outline-secondary" title="Imprimir">
                                                            <i class="fas fa-print"></i>
                                                        </a>
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

            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">Actividad Reciente</h6>
                        <a href="historico_view.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-history me-1"></i>Ver Histórico
                        </a>
                    </div>
                    <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                        <div class="timeline" id="actividadReciente">
                            <?php if (empty($actividad_reciente)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-clock fa-3x mb-3"></i>
                                    <p>No hay actividad reciente</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($actividad_reciente as $actividad): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="timeline-marker me-3">
                                                <?php
                                                $icon = 'fa-info-circle';
                                                $color = 'text-primary';
                                                if (strpos($actividad['operacion'], 'LOGIN') !== false) {
                                                    $icon = 'fa-sign-in-alt';
                                                    $color = 'text-success';
                                                } elseif (strpos($actividad['operacion'], 'LOGOUT') !== false) {
                                                    $icon = 'fa-sign-out-alt';
                                                    $color = 'text-warning';
                                                } elseif (strpos($actividad['operacion'], 'FACTURA') !== false) {
                                                    $icon = 'fa-file-invoice';
                                                    $color = 'text-info';
                                                } elseif (strpos($actividad['operacion'], 'CLIENTE') !== false) {
                                                    $icon = 'fa-user';
                                                    $color = 'text-secondary';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?> fa-lg <?php echo $color; ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <small class="text-muted d-block">
                                                    <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_hora'])); ?>
                                                </small>
                                                <p class="mb-1" style="color: var(--win-text-primary);"><?php echo htmlspecialchars($actividad['operacion'] ?? 'Actividad del sistema'); ?></p>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($actividad['descripcion'] ?? ''); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


		
		<!-- Últimos Clientes -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="fas fa-users me-2 text-primary"></i>
                            <span style="color: var(--win-text-primary);">Últimos Clientes</span>
                        </h6>
                        <div class="filter-buttons">
                            <div class="btn-group" role="group">
                                <a href="clientes.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list me-1"></i>Ver Todos
                                </a>
                                <a href="nuevo_cliente.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Nuevo Cliente
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Código</th>
										<th>Cliente</th>
                                        <th>No. Cont.</th>
                                        <th>Responsable</th>
										<th>Estado</th>
                                        <th>F.Inicio</th>
                                        <th>Vigencia</th>
                                        <th>F.Término</th>
										<th>Suplmto.</th>
										<th>F.Final</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ultimos_clientes)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-3">
                                                <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                                                No hay clientes registrados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimos_clientes as $cliente): ?>
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
                                                <!--<small class="text-muted" title="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                                                    <?php echo htmlspecialchars(substr($cliente['direccion'], 0, 40)) . (strlen($cliente['direccion']) > 40 ? '...' : ''); ?>
                                                </small>-->
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
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="ver_cliente.php?id=<?php echo $cliente['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Ver" data-bs-toggle="tooltip">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="editar_cliente.php?id=<?php echo $cliente['id']; ?>" 
                                                   class="btn btn-outline-warning" 
                                                   title="Editar" data-bs-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="facturas.php?cliente_id=<?php echo $cliente['id']; ?>" 
                                                   class="btn btn-outline-info" 
                                                   title="Ver Facturas" data-bs-toggle="tooltip">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
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
        </div>
		

    </main> <!-- Cierre del main -->

<!-- Quick Actions (ÚNICO conjunto de botones flotantes) -->
<div class="win-quick-actions">
    <!-- Acciones expandidas (ocultas por defecto) -->
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
        <!-- NUEVO BOTÓN DE CHATBOT -->
        <button class="win-quick-action action-chatbot" onclick="toggleChatbot()" title="Abrir Asistente Virtual" id="chatbotQuickAction">
            <i class="fas fa-robot"></i>
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
    <script src="js/jspdf.umd.min.js"></script>
    <script>
        window.jspdf = window.jspdf || { jsPDF: jsPDF };
		
		const anioOperaciones = <?php echo $anio_actual; ?>;
		
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let ingresosChartInstance = null;
        let serviciosChartInstance = null;
		let categoriasChartInstance = null; 
        let currentPeriod = 'year'; // Estado del periodo actual

const datosSemanales = <?php echo $datos_semanales_js ?? '[]'; ?>;
const semanasLabels = <?php echo $semanas_labels_js ?? '[]'; ?>;
const semanasEnMes = <?php echo $semanas_en_mes_js ?? 4; ?>;


// Variables PHP necesarias para las exportaciones
const fechaCierrePHP = "<?php echo date('d/m/Y', $timestamp_combinado); ?>";
const mesActualNombre = "<?php echo $mes_actual_es; ?>";


        // Datos de PHP para gráficos
        const datosMensuales = <?php echo json_encode($datos_mensuales); ?>;
        const datosTrimestrales = <?php echo json_encode($datos_trimestrales); ?>;
        const datosDiarios = <?php echo json_encode($datos_diarios_grafico); ?>;
        const labelsDiarios = <?php echo json_encode($labels_diarios); ?>;
        const mesesLabels = <?php echo json_encode($meses); ?>;
        const trimestresLabels = <?php echo json_encode($trimestres); ?>;
        const serviciosData = <?php echo json_encode($servicios_solicitados); ?>;
		const categoriasData = <?php echo json_encode($categorias_stats); ?>;
		
let anioActual = <?php echo $anio_actual; ?>;
let aniosComparacion5Anios = <?php echo $anios_comparacion_js ?? '[]'; ?>;
let ingresosComparacion5Anios = <?php echo $ingresos_comparacion_js ?? '[]'; ?>;
let mesesCompletos = <?php echo json_encode($meses_completos); ?>;
        
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
                
                // Redimensionar gráficos
                setTimeout(() => {
                    if (ingresosChartInstance) ingresosChartInstance.resize();
                    if (serviciosChartInstance) serviciosChartInstance.resize();
                }, 300);
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
        
        // Funciones para gráficos y datos
        function toggleTablaDatos(tipo) {
            const tablaId = tipo === 'ingresos' ? 'tablaIngresosData' : 'tablaServiciosData';
            const iconId = tipo === 'ingresos' ? 'tablaIngresosIcon' : 'tablaServiciosIcon';
            
            const tablaElement = document.getElementById(tablaId);
            const iconElement = document.getElementById(iconId);
            
            if (tablaElement.style.display === 'none' || tablaElement.style.display === '') {
                tablaElement.style.display = 'block';
                iconElement.className = 'fas fa-chevron-up';
                
                // Si es la tabla de ingresos, actualizar según el periodo actual
                if (tipo === 'ingresos') {
                    actualizarTablaPeriodo();
                }
            } else {
                tablaElement.style.display = 'none';
                iconElement.className = 'fas fa-chevron-down';
            }
        }
        
        // Función para actualizar la tabla según el periodo seleccionado
        function actualizarTablaPeriodo() {
    // Ocultar todas las tablas primero
    document.getElementById('tablaDatosDiarios').style.display = 'none';
    document.getElementById('tablaDatosMensuales').style.display = 'none';
    document.getElementById('tablaDatosTrimestrales').style.display = 'none';
    document.getElementById('tablaDatosAnuales').style.display = 'none';
    document.getElementById('tablaDatosSemanales').style.display = 'none';
    
    // Mostrar la tabla correspondiente al periodo actual
    switch(currentPeriod) {
        case 'day':
            document.getElementById('tablaDatosDiarios').style.display = 'block';
            break;
        case 'month':
            document.getElementById('tablaDatosMensuales').style.display = 'block';  // CORREGIDO
            break;
        case 'year':
            document.getElementById('tablaDatosAnuales').style.display = 'block';
            break;
        case 'quarter':
            document.getElementById('tablaDatosTrimestrales').style.display = 'block';
            break;
        case 'week':
            document.getElementById('tablaDatosSemanales').style.display = 'block';
            break;
    }
}
        
function configurarCambioPeriodo() {
    const periodButtons = document.querySelectorAll('[data-period]');
    
    periodButtons.forEach(button => {
        button.addEventListener('click', function() {
            const period = this.getAttribute('data-period');
            currentPeriod = period;
            
            // Remover active de todos los botones
            periodButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = '';
                btn.style.color = 'var(--win-text-secondary)';
                btn.style.borderColor = 'var(--win-border-color)';
            });
            
            // Activar este botón
            this.classList.add('active');
            this.style.background = 'var(--win-accent-light)';
            this.style.color = 'var(--win-accent)';
            this.style.borderColor = 'var(--win-accent)';
			
            // 1. Ocultar todas las tablas de periodo primero
            const todasLasTablasPeriodo = ['Diarios', 'Semanales', 'Mensuales', 'Trimestrales', 'Anuales'];
            todasLasTablasPeriodo.forEach(tablaType => {
                const tablaElement = document.getElementById(`tablaDatos${tablaType}`);
                if (tablaElement) {
                    tablaElement.style.display = 'none';
                }
            });
            
            // 2. Ahora mostrar solo la tabla correspondiente
            let targetTableType = '';
			switch(period) {
				case 'day':
					targetTableType = 'Diarios';
					break;
				case 'week':
					targetTableType = 'Semanales';
					break;
				case 'month':
					targetTableType = 'Mensuales';  // Tabla de 12 meses
					break;
				case 'year':
					targetTableType = 'Anuales';    // Tabla de comparación de 5 años - ¡NUEVA TABLA!
					break;
				case 'quarter':
					targetTableType = 'Trimestrales';
					break;
			}
            
            const tableToShow = document.getElementById(`tablaDatos${targetTableType}`);
            if (tableToShow) {
                tableToShow.style.display = 'block';
            }
            
            let newData = [];
            let newLabels = [];
            let chartType = 'line';
            let newLabel = '';
            let xAxisTitle = 'PERÍODO';
            const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
            
            switch(period) {
                case 'year':
                    // MOSTRAR COMPARACIÓN DE ÚLTIMOS 5 AÑOS
                    newData = ingresosComparacion5Anios;
                    newLabels = aniosComparacion5Anios.map(anio => `AÑO ${anio}`);
                    chartType = 'bar';
                    newLabel = 'Comparación de Ingresos (Últimos 5 Años)';
                    xAxisTitle = 'AÑOS';
					targetTableType = 'Anuales';
                    
					if (ingresosChartInstance && ingresosChartInstance.options.scales.x) {
						ingresosChartInstance.options.scales.x.title.text = xAxisTitle;
					}
                    break;
                    
                case 'quarter':
                    newData = datosTrimestrales;
                    newLabels = trimestresLabels.map(t => `TRIMESTRE ${t.charAt(1)}`);
                    chartType = 'bar';
                    newLabel = 'Ingresos Trimestrales <?php echo $anio_actual; ?> ($)';
                    xAxisTitle = 'TRIMESTRES';
                    
                    // Configurar tooltips para trimestres
                    if (ingresosChartInstance) {
                        ingresosChartInstance.options.plugins.tooltip.callbacks.label = function(context) {
                            const value = context.raw;
                            const trimestre = context.label;
                            const rango = trimestresLabels[context.dataIndex]?.split('(')[1]?.replace(')', '') || '';
                            
                            return [
                                `${trimestre} (${rango}): $${value.toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                                `Promedio mensual: $${(value/3).toLocaleString('es-ES', {minimumFractionDigits: 2})}`
                            ];
                        };
                    }
                    break;
                    
                case 'month':
case 'month':
    // MOSTRAR 12 MESES DEL AÑO ACTUAL
    newData = datosMensuales;  // Array de 12 meses
    newLabels = mesesLabels.map(m => m.toUpperCase()); // ["ENE", "FEB", "MAR", etc.]
    chartType = 'bar';
    newLabel = `Ingresos Mensuales ${anioActual} ($)`;
    xAxisTitle = 'MESES';
    
    // ACTUALIZAR TÍTULO DEL EJE X
    if (ingresosChartInstance && ingresosChartInstance.options.scales.x) {
        ingresosChartInstance.options.scales.x.title.text = xAxisTitle;
    }
    
    // Configurar colores para los meses
    if (ingresosChartInstance) {
        // Colores del arcoíris para los 12 meses
        const monthColors = [
            '#FF6B6B', '#FF8E53', '#FFB347', '#FFD166', '#CCD46B', '#99D578',
            '#66D684', '#33D790', '#00D89C', '#00B4A6', '#0091B0', '#006DBA'
        ];
        
        // Destacar el mes actual
        const currentMonth = new Date().getMonth(); // 0-11
        const highlightedColors = monthColors.map((color, index) => 
            index === currentMonth ? color : color + '80' // Menos opaco para otros meses
        );
        
        ingresosChartInstance.data.datasets[0].backgroundColor = highlightedColors;
        ingresosChartInstance.data.datasets[0].borderColor = monthColors;
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].borderRadius = 6;
        ingresosChartInstance.data.datasets[0].tension = 0;
        
        // Configurar tooltips para meses
        ingresosChartInstance.options.plugins.tooltip.callbacks.label = function(context) {
            const value = context.raw;
            const mesIndex = context.dataIndex;
            const mesNombre = mesesCompletos[mesIndex] || mesesLabels[mesIndex];
            const totalAnual = newData.reduce((a, b) => a + b, 0);
            const porcentaje = totalAnual > 0 ? ((value / totalAnual) * 100).toFixed(1) : 0;
            
            return [
                `${mesNombre}: $${value.toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                `Participación anual: ${porcentaje}%`
            ];
        };
    }
    break;
                    
                case 'week':
                    // Vista SEMANAL - TODAS las semanas del mes actual
                    if (datosSemanales && datosSemanales.length > 0) {
                        newData = datosSemanales;
                        newLabels = semanasLabels.map(s => s.replace('Semana', 'SEMANA'));
                        
                        // Si hay más de 4 semanas, usar gráfico de línea para mejor visualización
                        chartType = semanasEnMes > 4 ? 'line' : 'bar';
                        newLabel = 'Ingresos por Semana - <?php echo $mes_actual_es." / ".$anio_actual; ?> ($)';
                        xAxisTitle = 'SEMANAS';
                        
                        // Configurar colores para múltiples semanas
                        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
                        
                        if (chartType === 'bar' && ingresosChartInstance) {
                            // Para gráfico de barras con pocas semanas, colores diferentes
                            const chartColors = [];
                            for (let i = 0; i < semanasEnMes; i++) {
                                // Degradado de colores
                                const hue = 200 + (i * (280 / semanasEnMes));
                                chartColors.push(`hsl(${hue}, 70%, 50%)`);
                            }
                            
                            ingresosChartInstance.data.datasets[0].backgroundColor = chartColors;
                            ingresosChartInstance.data.datasets[0].borderColor = chartColors.map(color => 
                                color.replace('50%)', '40%)')
                            );
                        } else if (chartType === 'line' && ingresosChartInstance) {
                            // Para gráfico de línea con muchas semanas
                            ingresosChartInstance.data.datasets[0].borderColor = accentColor;
                            ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}20`;
                            ingresosChartInstance.data.datasets[0].borderWidth = 3;
                            ingresosChartInstance.data.datasets[0].tension = 0.4;
                            ingresosChartInstance.data.datasets[0].fill = true;
                        }
                        
                        // Configurar tooltips informativos
                        ingresosChartInstance.options.plugins.tooltip = {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const semanaIndex = context.dataIndex;
                                    const totalMes = newData.reduce((a, b) => a + b, 0);
                                    const porcentaje = totalMes > 0 ? ((value / totalMes) * 100).toFixed(1) : 0;
                                    
                                    return [
                                        `${semanasLabels[semanaIndex]}: $${value.toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                                        `Porcentaje del mes: ${porcentaje}%`,
                                        `Promedio diario: $${(value / 7).toLocaleString('es-ES', {minimumFractionDigits: 2})}`
                                    ];
                                }
                            }
                        };
                    }
                    break;
                    
                case 'day':
                    // Vista DIARIA - Todos los días del mes (hasta hoy)
					const hoy = <?php echo $dia_actual; ?>;
					const totalDiasMes = <?php echo $total_dias_mes; ?>;

                    
                    newLabels = [];
                    newData = [];
                    
                    for (let dia = 1; dia <= totalDiasMes; dia++) {
                        if (dia <= hoy) {
                            const datosDia = datosDiarios[dia - 1] || 0;
                            newData.push(datosDia);
                        } else {
                            newData.push(0);
                        }
                        newLabels.push(`DÍA ${dia}`);
                    }
                    
                    chartType = 'line';
                    newLabel = 'Ingresos Diarios - <?php echo $mes_actual_es . ' / ' . $anio_actual; ?> ($)';
                    xAxisTitle = 'DÍAS DEL MES';
                    
                    // Configurar gráfico de línea
                    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
                    if (ingresosChartInstance) {
                        ingresosChartInstance.data.datasets[0].borderColor = accentColor;
                        ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}20`;
                        ingresosChartInstance.data.datasets[0].borderWidth = 2;
                        ingresosChartInstance.data.datasets[0].tension = 0.3;
                        ingresosChartInstance.data.datasets[0].fill = true;
                        
                        // Configurar puntos solo para días con datos > 0
                        ingresosChartInstance.data.datasets[0].pointBackgroundColor = newData.map(value => 
                            value > 0 ? accentColor : 'rgba(200, 200, 200, 0.5)'
                        );
                        ingresosChartInstance.data.datasets[0].pointBorderColor = newData.map(value => 
                            value > 0 ? '#ffffff' : 'rgba(200, 200, 200, 0.8)'
                        );
                        ingresosChartInstance.data.datasets[0].pointRadius = newData.map(value => 
                            value > 0 ? 4 : 2
                        );
                        ingresosChartInstance.data.datasets[0].pointHoverRadius = newData.map(value => 
                            value > 0 ? 6 : 3
                        );
                    }
                    
                    // Configurar tooltips
                    ingresosChartInstance.options.plugins.tooltip = {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const diaNum = context.dataIndex + 1;
                                const fecha = new Date();
                                fecha.setDate(diaNum);
                                const fechaFormato = fecha.toLocaleDateString('es-ES', { 
                                    day: '2-digit', 
                                    month: '2-digit' 
                                });
                                
                                if (diaNum > hoy) {
                                    return [`Día ${diaNum} (${fechaFormato}): Próximo`];
                                }
                                
                                return [
                                    `Día ${diaNum} (${fechaFormato}): $${value.toLocaleString('es-ES', {minimumFractionDigits: 2})}`,
                                    value === 0 ? 'Sin facturas este día' : ''
                                ];
                            }
                        }
                    };
                    break;
            }
            
// Actualizar el gráfico existente si hay datos
if (ingresosChartInstance && newData.length > 0) {
    // Cambiar tipo de gráfico
    ingresosChartInstance.config.type = chartType;
    
    // Actualizar datos
    ingresosChartInstance.data.labels = newLabels;
    ingresosChartInstance.data.datasets[0].data = newData;
    ingresosChartInstance.data.datasets[0].label = newLabel;
    
    // ===== APLICAR COLORES SEGÚN EL PERÍODO =====
    if (period === 'year') {
        // Configurar colores diferenciados por año
        const colors = [
            '#FF6B6B', // Rojo para año más antiguo
            '#FF8E53', // Naranja
            '#FFD166', // Amarillo
            '#06D6A0', // Verde
            '#0078d4'  // Azul Windows para año actual
        ];
        
        // Asegurar que tenemos suficientes colores
        const backgroundColor = [];
        const borderColor = [];
        
        for (let i = 0; i < newData.length; i++) {
            // El último año (actual) es especial
            if (aniosComparacion5Anios[i] === anioActual) {
                backgroundColor.push('#0078d4'); // Azul Windows
                borderColor.push('#005a9e');     // Borde más oscuro
            } else {
                backgroundColor.push(colors[i] || '#6c757d');
                borderColor.push(colors[i] ? colors[i] + 'CC' : '#495057');
            }
        }
        
        ingresosChartInstance.data.datasets[0].backgroundColor = backgroundColor;
        ingresosChartInstance.data.datasets[0].borderColor = borderColor;
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].borderRadius = 6;
        ingresosChartInstance.data.datasets[0].fill = false;
        ingresosChartInstance.data.datasets[0].tension = 0;
        
    } else if (period === 'quarter') {
        // Colores para trimestres
        const quarterColors = ['#0078d4', '#107c10', '#5c2d91', '#e81123'];
        ingresosChartInstance.data.datasets[0].backgroundColor = quarterColors.slice(0, newData.length);
        ingresosChartInstance.data.datasets[0].borderColor = quarterColors.slice(0, newData.length).map(color => 
            color.replace('#', '#80') // Hacer bordes más oscuros
        );
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].borderRadius = 6;
        ingresosChartInstance.data.datasets[0].fill = false;
        ingresosChartInstance.data.datasets[0].tension = 0;
        
    } else if (period === 'month') {
        // Colores arcoíris para meses
        const rainbowColors = [
            '#FF6B6B', '#FF8E53', '#FFB347', '#FFD166', '#CCD46B', '#99D578',
            '#66D684', '#33D790', '#00D89C', '#00B4A6', '#0091B0', '#006DBA'
        ];
        
        const currentMonth = new Date().getMonth();
        const monthColors = rainbowColors.map((color, index) => 
            index === currentMonth ? color : color + 'CC'
        );
        
        ingresosChartInstance.data.datasets[0].backgroundColor = monthColors.slice(0, newData.length);
        ingresosChartInstance.data.datasets[0].borderColor = rainbowColors.slice(0, newData.length);
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].borderRadius = 6;
        ingresosChartInstance.data.datasets[0].fill = false;
        ingresosChartInstance.data.datasets[0].tension = 0;
        
    } else if (period === 'week') {
        if (chartType === 'bar') {
            // Colores para semanas (gráfico de barras)
            const weekColors = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00', '#0099bc'];
            ingresosChartInstance.data.datasets[0].backgroundColor = weekColors.slice(0, newData.length);
            ingresosChartInstance.data.datasets[0].borderColor = weekColors.slice(0, newData.length).map(color => 
                color.replace('#', '#80')
            );
            ingresosChartInstance.data.datasets[0].borderWidth = 2;
            ingresosChartInstance.data.datasets[0].borderRadius = 6;
            ingresosChartInstance.data.datasets[0].fill = false;
            ingresosChartInstance.data.datasets[0].tension = 0;
        } else {
            // Para gráfico de línea de semanas
            ingresosChartInstance.data.datasets[0].borderWidth = 2;
            ingresosChartInstance.data.datasets[0].fill = true;
            ingresosChartInstance.data.datasets[0].tension = 0.4;
            ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}20`;
            ingresosChartInstance.data.datasets[0].borderColor = accentColor;
            ingresosChartInstance.options.scales.x.grid = { 
                color: 'rgba(255, 255, 255, 0.1)'
            };
        }
        
    } else if (period === 'day') {
        // Para días (siempre es gráfico de línea)
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].fill = true;
        ingresosChartInstance.data.datasets[0].tension = 0.4;
        ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}20`;
        ingresosChartInstance.data.datasets[0].borderColor = accentColor;
        ingresosChartInstance.options.scales.x.grid = { 
            color: 'rgba(255, 255, 255, 0.1)'
        };
        
        // Configurar puntos solo para días con datos > 0
        ingresosChartInstance.data.datasets[0].pointBackgroundColor = newData.map(value => 
            value > 0 ? accentColor : 'rgba(200, 200, 200, 0.5)'
        );
        ingresosChartInstance.data.datasets[0].pointBorderColor = newData.map(value => 
            value > 0 ? '#ffffff' : 'rgba(200, 200, 200, 0.8)'
        );
        ingresosChartInstance.data.datasets[0].pointRadius = newData.map(value => 
            value > 0 ? 4 : 2
        );
        ingresosChartInstance.data.datasets[0].pointHoverRadius = newData.map(value => 
            value > 0 ? 6 : 3
        );
    }
    
    // Para otros casos no cubiertos, usar configuración por defecto
    else if (chartType === 'line') {
        ingresosChartInstance.data.datasets[0].borderWidth = 2;
        ingresosChartInstance.data.datasets[0].fill = true;
        ingresosChartInstance.data.datasets[0].tension = 0.4;
        ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}20`;
        ingresosChartInstance.data.datasets[0].borderColor = accentColor;
        ingresosChartInstance.options.scales.x.grid = { 
            color: 'rgba(255, 255, 255, 0.1)'
        };
    } else {
        // Configuración por defecto para barras
        ingresosChartInstance.data.datasets[0].borderWidth = 1;
        ingresosChartInstance.data.datasets[0].fill = false;
        ingresosChartInstance.data.datasets[0].tension = 0;
        ingresosChartInstance.data.datasets[0].borderRadius = 4;
        ingresosChartInstance.data.datasets[0].backgroundColor = `${accentColor}60`;
        ingresosChartInstance.data.datasets[0].borderColor = accentColor;
    }
    
    // Actualizar título del eje X
    if (ingresosChartInstance.options.scales.x) {
        ingresosChartInstance.options.scales.x.title = {
            display: true,
            text: xAxisTitle,
            color: getComputedStyle(document.documentElement).getPropertyValue('--win-text-primary'),
            font: {
                size: 12,
                weight: 'bold'
            }
        };
    }
    
    // Actualizar el gráfico
    ingresosChartInstance.update();
    
    // Actualizar la tabla de datos si está visible
    if (document.getElementById('tablaIngresosData').style.display === 'block') {
        actualizarTablaPeriodo();
    }
}
        });
    });
}
		
// Función actualizada para dashboard
function actualizarDashboard() {
    const btn = document.getElementById('btnActualizarDashboard');
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
        btn.disabled = true;
        
        // Obtener fecha de cierre actual
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_combinado); ?>";
        
        Swal.fire({
            title: '<span style="color: var(--win-text-primary)">Actualizando Dashboard</span>',
            html: `
                <div style="text-align: center;">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p style="color: var(--win-text-secondary); margin-bottom: 5px;">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Fecha de cierre: <strong style="color: #0dcaf0;">${fechaCierre}</strong>
                    </p>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            background: 'var(--win-bg-secondary)',
            color: 'var(--win-text-primary)',
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Enviar fecha de cierre al servidor
        fetch('api/dashboard_stats.php?fecha_cierre=' + encodeURIComponent(fechaCierre))
            .then(response => response.json())
            .then(data => {
                // Actualizar estadísticas principales
                if (document.getElementById('facturasMes')) {
                    document.getElementById('facturasMes').textContent = data.facturas_mes;
                }
                
                if (document.getElementById('ingresosMes')) {
                    document.getElementById('ingresosMes').textContent = '$' + data.ingresos_mes.toLocaleString('es-ES', {minimumFractionDigits: 2});
                }
                
                // Actualizar variación de ingresos
                const variacionElement = document.getElementById('variacionIngresos');
                if (variacionElement) {
                    const icon = data.variacion_ingresos >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    const signo = data.variacion_ingresos >= 0 ? '+' : '';
                    variacionElement.innerHTML = `<i class="fas fa-dollar-sign me-1"></i>
                        <i class="fas ${icon} me-1"></i>
                        ${signo}${data.variacion_ingresos.toFixed(1)}%
                        vs mes anterior`;
                }
                
                // Actualizar contadores de clientes
                if (document.getElementById('clientesActivos')) {
                    document.getElementById('clientesActivos').textContent = data.clientes_todos || data.clientes_activos;
                }
                
                if (document.getElementById('ClientesInactivos')) {
                    document.getElementById('ClientesInactivos').textContent = data.clientes_inactivos || 0;
                }
                
                const nuevosClientesElement = document.getElementById('nuevosClientes');
                if (nuevosClientesElement) {
                    nuevosClientesElement.innerHTML = `<i class="fas fa-users me-1"></i>+${data.nuevos_clientes} este mes`;
                }
                
                // Servicios y categorías
                if (document.getElementById('serviciosCount')) {
                    const totalServicios = (data.servicios_count || 0) + (data.servicios_inactivos || 0);
                    document.getElementById('serviciosCount').textContent = totalServicios;
                }
                
                if (document.getElementById('categoriasCount')) {
                    document.getElementById('categoriasCount').textContent = data.categorias_count;
                }
                
                if (document.getElementById('CatActivas')) {
                    document.getElementById('CatActivas').textContent = data.categorias_activas;
                }
                
                if (document.getElementById('CatInactivas')) {
                    document.getElementById('CatInactivas').textContent = data.categorias_inactivas;
                }
                
                // Actualizar badges de facturas
                if (document.getElementById('badgeTotalBd')) {
                    document.getElementById('badgeTotalBd').innerHTML = 
                        `<i class="fas fa-database me-1"></i>${data.facturas_total} Facturas en BD`;
                }
                
                if (document.getElementById('contadorFacturasAnio')) {
                    document.getElementById('contadorFacturasAnio').textContent = data.facturas_anio;
                }
                
                // Mostrar notificación de éxito
                Swal.fire({
                    icon: 'success',
                    title: '<span style="color: var(--win-text-primary)">Dashboard actualizado</span>',
                    html: `
                        <div style="text-align: center;">
                            <p style="color: var(--win-text-secondary);">
                                Los datos han sido actualizados correctamente
                            </p>
                            <small style="color: var(--win-text-secondary); opacity: 0.8;">
                                <i class="fas fa-calendar-alt me-1"></i>
                                ${fechaCierre}
                            </small>
                        </div>
                    `,
                    timer: 1500,
                    showConfirmButton: false,
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
            })
            .catch(error => {
                console.error('Error al actualizar:', error);
                Swal.fire({
                    icon: 'error',
                    title: '<span style="color: var(--win-text-primary)">Error</span>',
                    html: `
                        <div style="text-align: center;">
                            <p style="color: var(--win-text-secondary);">
                                No se pudieron actualizar los datos
                            </p>
                            <small class="text-danger">${error.message}</small>
                        </div>
                    `,
                    background: 'var(--win-bg-secondary)',
                    color: 'var(--win-text-primary)'
                });
            })
            .finally(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
    }
}
		
		// Función para inicializar gráficos (COMPATIBLE con Chart.js v4.5.1)
function initCharts() {
    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--win-text-primary');
    const gridColor = getComputedStyle(document.documentElement).getPropertyValue('--win-border-color');
    const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--win-bg-secondary');
    
    // ===== GRÁFICO DE INGRESOS =====
    const ingresosCtx = document.getElementById('ingresosChart');
    
    if (!ingresosCtx) {
        console.error("No se encontró el canvas de ingresos");
        return;
    }
    
    // Destruir gráfico anterior si existe
    if (ingresosChartInstance) {
        ingresosChartInstance.destroy();
    }
    
    // VERIFICACIÓN: ¿Qué datos mostrar por defecto?
    // Si hay datos de 5 años, mostrar esos, sino mostrar mensual
    let datosIniciales = [];
    let labelsIniciales = [];
    let tipoGraficoInicial = 'bar';
    let tituloInicial = '';
    
    if (aniosComparacion5Anios && aniosComparacion5Anios.length > 0) {
        // MOSTRAR COMPARACIÓN DE 5 AÑOS POR DEFECTO
        datosIniciales = ingresosComparacion5Anios;
        labelsIniciales = aniosComparacion5Anios.map(anio => `AÑO ${anio}`);
        tituloInicial = 'Comparación de Ingresos (Últimos 5 Años)';
        currentPeriod = 'year';
        
    } else if (datosMensuales && datosMensuales.length > 0) {
        // Fallback: mostrar mensual
        datosIniciales = datosMensuales;
        labelsIniciales = mesesLabels;
        tituloInicial = 'Ingresos Mensuales';
        currentPeriod = 'month';
        tipoGraficoInicial = 'bar';
        
    } else {
        // Último fallback: diario
        datosIniciales = datosDiarios || [];
        labelsIniciales = labelsDiarios || [];
        tituloInicial = 'Ingresos Diarios';
        currentPeriod = 'day';
        tipoGraficoInicial = 'line';
    }
    
    // CONFIGURAR COLORES ESPECÍFICOS PARA VISTA ANUAL
    let coloresFondo = [];
    let coloresBorde = [];
    
    if (currentPeriod === 'year' && datosIniciales.length > 0) {
        // Colores diferenciados por año (igual que en configurarCambioPeriodo)
        const colors = [
            '#FF6B6B', // Rojo para año más antiguo
            '#FF8E53', // Naranja
            '#FFD166', // Amarillo
            '#06D6A0', // Verde
            '#0078d4'  // Azul Windows para año actual
        ];
        
        for (let i = 0; i < datosIniciales.length; i++) {
            // El último año (actual) es especial
            if (aniosComparacion5Anios && aniosComparacion5Anios[i] === anioActual) {
                coloresFondo.push('#0078d4'); // Azul Windows
                coloresBorde.push('#005a9e'); // Borde más oscuro
            } else {
                coloresFondo.push(colors[i] || '#6c757d');
                coloresBorde.push(colors[i] ? colors[i] + 'CC' : '#495057');
            }
        }
        
    } else {
        // Colores por defecto para otros períodos
        coloresFondo = `${accentColor}60`;
        coloresBorde = accentColor;
    }
    
    try {
        ingresosChartInstance = new Chart(ingresosCtx, {
            type: tipoGraficoInicial,
            data: {
                labels: labelsIniciales,
                datasets: [{
                    label: tituloInicial,
                    data: datosIniciales,
                    borderColor: currentPeriod === 'year' ? coloresBorde : accentColor,
                    backgroundColor: currentPeriod === 'year' ? coloresFondo : `${accentColor}20`,
                    borderWidth: currentPeriod === 'year' ? 2 : 3,
                    borderRadius: currentPeriod === 'year' ? 6 : 0,
                    fill: currentPeriod !== 'year', // Solo rellenar si no es anual
                    tension: currentPeriod === 'year' ? 0 : 0.4,
                    // Configuración específica para gráfico de barras anual
                    ...(currentPeriod === 'year' && {
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    })
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor,
                            font: {
                                size: 13,
                                family: "'Segoe UI', system-ui"
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: bgColor,
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: gridColor,
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        displayColors: currentPeriod === 'year', // Mostrar colores solo en anual
                        callbacks: {
                            label: function(context) {
                                if (currentPeriod === 'year') {
                                    // Tooltip para vista anual
                                    const año = aniosComparacion5Anios[context.dataIndex];
                                    const esActual = año === anioActual;
                                    const etiqueta = esActual ? `AÑO ACTUAL ${año}` : `AÑO ${año}`;
                                    return `${etiqueta}: $${context.raw.toLocaleString('es-ES', {minimumFractionDigits: 2})}`;
                                } else {
                                    // Tooltip para otros períodos
                                    return `Ingresos: $${context.raw.toLocaleString('es-ES', {minimumFractionDigits: 2})}`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: `${gridColor}60`,
                            drawBorder: false,
                            drawTicks: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: `${textColor}90`,
                            font: {
                                size: 11
                            },
                            padding: 10,
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-CU');
                            }
                        },
                        title: {
                            display: true,
                            text: 'Monto ($)',
                            color: textColor,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: `${gridColor}60`,
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: `${textColor}90`,
                            font: {
                                size: currentPeriod === 'year' ? 10 : 11
                            },
                            maxRotation: currentPeriod === 'year' ? 0 : 45,
                            padding: 8
                        },
                        title: {
                            display: true,
                            text: currentPeriod === 'year' ? 'AÑOS' : 
                                  currentPeriod === 'month' ? 'MESES' : 
                                  currentPeriod === 'day' ? 'DÍAS' : 'PERÍODO',
                            color: textColor,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                // ANIMACIONES ESPECÍFICAS
                animations: {
                    ...(currentPeriod === 'year' ? {
                        // Animación para barras anuales
                        y: {
                            duration: 1500,
                            easing: 'easeOutQuart',
                            from: 0
                        }
                    } : {
                        // Animación para líneas
                        tension: {
                            duration: 1500,
                            easing: 'easeOutQuart',
                            from: 0.8,
                            to: 0.4
                        },
                        radius: {
                            duration: 800,
                            easing: 'easeOutBack'
                        }
                    }),
                    colors: {
                        duration: 1000
                    }
                }
            }
        });
        
        
        // ACTUALIZAR EL BOTÓN ACTIVO EN LA UI
        setTimeout(() => {
            const periodButtons = document.querySelectorAll('[data-period]');
            periodButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = '';
                btn.style.color = 'var(--win-text-secondary)';
                btn.style.borderColor = 'var(--win-border-color)';
            });
            
            const botonActivo = document.querySelector(`[data-period="${currentPeriod}"]`);
            if (botonActivo) {
                botonActivo.classList.add('active');
                botonActivo.style.background = 'var(--win-accent-light)';
                botonActivo.style.color = 'var(--win-accent)';
                botonActivo.style.borderColor = 'var(--win-accent)';
            }
            
            // Mostrar tabla correspondiente
            if (currentPeriod === 'year') {
                document.getElementById('tablaDatosAnuales').style.display = 'block';
            }
        }, 100);
        
    } catch (error) {
        console.error("❌ Error al crear gráfico:", error);
    }
            
            // ===== GRÁFICO DE SERVICIOS =====
            const serviciosCtx = document.getElementById('serviciosChart');
            
            if (serviciosCtx && serviciosData && serviciosData.length > 0) {
                
                // Destruir gráfico anterior si existe
                if (serviciosChartInstance) {
                    serviciosChartInstance.destroy();
                }
                
                try {
                    // Preparar datos
                    const serviciosLabels = serviciosData.map(item => {
                        const desc = item.descripcion || 'S/D';
                        return desc;//desc.length > 15 ? desc.substring(0, 12) + '...' : desc;
                    });
                    
                    const serviciosValues = serviciosData.map(item => item.total_vendido || 0);
                    
                    // Paleta de colores vibrante
                    const colorPalette = [
                        accentColor,
                        '#10B981', // Verde esmeralda
                        '#8B5CF6', // Violeta
                        '#EF4444', // Rojo
                        '#F59E0B', // Ámbar
                        '#3B82F6', // Azul
                        '#EC4899', // Rosa
                        '#14B8A6'  // Turquesa
                    ];
                    
                    serviciosChartInstance = new Chart(serviciosCtx, {
                        type: 'doughnut',
                        data: {
                            labels: serviciosLabels,
                            datasets: [{
                                data: serviciosValues,
                                backgroundColor: colorPalette.slice(0, serviciosValues.length),
                                borderColor: bgColor,
                                borderWidth: 2,
                                hoverOffset: 20,
                                hoverBorderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: textColor,
                                        font: {
                                            size: 12,
                                            family: "'Segoe UI', system-ui"
                                        },
                                        padding: 15,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 10,
                                        boxHeight: 10
                                    }
                                },
                                tooltip: {
                                    backgroundColor: bgColor,
                                    titleColor: textColor,
                                    bodyColor: textColor,
                                    borderColor: gridColor,
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    padding: 12,
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                            return `${label}: ${value} unidades (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            cutout: '65%',
                            radius: '85%',
                            // ANIMACIONES ESPECÍFICAS PARA DOUGHNUT
                            animations: {
                                animateScale: true,
                                animateRotate: true,
                                duration: 2000,
                                easing: 'easeOutQuart'
                            }
                        }
                    });
                    
                    
                    // Forzar animación inicial
                    setTimeout(() => {
                        if (serviciosChartInstance) {
                            serviciosChartInstance.update();
                        }
                    }, 100);
                    
                } catch (error) {
                    console.error("❌ Error al crear gráfico de servicios:", error);
                    mostrarErrorGrafico(serviciosCtx.parentElement, error.message);
                }
            } else {
                console.warn("No hay datos para el gráfico de servicios");
                if (serviciosCtx) {
                    mostrarMensajeSinDatos(serviciosCtx.parentElement, "servicios");
                }
            }

// ===== GRÁFICO DE CATEGORÍAS =====
    const categoriasCtx = document.getElementById('categoriasChart');
    if (categoriasCtx && categoriasData && categoriasData.length > 0) {
        
        // Destruir anterior si existe
        if (categoriasChartInstance) categoriasChartInstance.destroy();
        
        const catLabels = categoriasData.map(c => c.descripcion);
        const catValues = categoriasData.map(c => c.total_servicios);
        
        // Colores Windows 11
        const winColors = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00'];
        
// ===== GRÁFICO DE CATEGORÍAS =====
const categoriasCtx = document.getElementById('categoriasChart');
if (categoriasCtx && categoriasData && categoriasData.length > 0) {
    
    // Destruir anterior si existe
    if (categoriasChartInstance) categoriasChartInstance.destroy();
    
    const catLabels = categoriasData.map(c => c.descripcion);
    const catValues = categoriasData.map(c => c.total_servicios);
    const total = catValues.reduce((a, b) => a + b, 0);
    
    // Colores Windows 11
    const winColors = ['#0078d4', '#107c10', '#5c2d91', '#e81123', '#ff8c00'];
    
    // Crear canvas overlay para mostrar valores
    const canvas = categoriasCtx;
    const ctx = canvas.getContext('2d');
    
    categoriasChartInstance = new Chart(categoriasCtx, {
        type: 'polarArea',
        data: {
            labels: catLabels,
            datasets: [{
                data: catValues,
                backgroundColor: winColors.map(c => c + '99'),
                borderColor: winColors.map(c => c),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    grid: { 
                        color: gridColor + '40',
                        circular: true
                    },
                    ticks: { 
                        display: false
                    },
                    // Añadir etiquetas de valor
                    pointLabels: {
                        display: true,
                        centerPointLabels: true,
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        color: textColor,
                        callback: function(value, index) {
                            return catValues[index] + ' (' + 
                                   Math.round((catValues[index] / total) * 100) + '%)';
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const percentage = total > 0 ? 
                                Math.round((context.raw / total) * 100) : 0;
                            return `${context.label}: ${context.raw} servicios (${percentage}%)`;
                        }
                    },
                    backgroundColor: bgColor,
                    bodyColor: textColor,
                    titleColor: textColor,
                    borderColor: gridColor,
                    borderWidth: 1,
                    padding: 10
                }
            },
            // Animación personalizada para mostrar valores
            animation: {
                onComplete: function() {
                    const chart = this;
                    const meta = chart.getDatasetMeta(0);
                    const centerX = chart.scales.r.x;
                    const centerY = chart.scales.r.y;
                    
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.font = 'bold 14px Arial';
                    
                    meta.data.forEach((element, index) => {
                        const angle = element.startAngle + 
                                    (element.endAngle - element.startAngle) / 2;
                        const radius = chart.scales.r.getDistanceFromCenterForValue(
                            chart.data.datasets[0].data[index]
                        ) * 0.5;
                        
                        const x = centerX + Math.cos(angle) * radius;
                        const y = centerY + Math.sin(angle) * radius;
                        
                        // Color del texto (blanco o negro según fondo)
                        const bgColor = winColors[index % winColors.length];
                        const brightness = parseInt(bgColor.slice(1, 3), 16) * 0.299 +
                                         parseInt(bgColor.slice(3, 5), 16) * 0.587 +
                                         parseInt(bgColor.slice(5, 7), 16) * 0.114;
                        ctx.fillStyle = brightness > 128 ? '#000' : '#fff';
                        
                        ctx.fillText(chart.data.datasets[0].data[index], x, y);
                    });
                    
                    ctx.restore();
                }
            }
        }
    });
}
    }

            // Forzar animación de ambos gráficos después de un breve retraso
            setTimeout(animateCharts, 500);
        }
        
        
		
		// Función para animar gráficos
        function animateCharts() {
            
            if (ingresosChartInstance) {
                // Animar gráfico de ingresos con datos actualizados
                ingresosChartInstance.data.datasets.forEach((dataset) => {
                    // Añadir efecto de animación
                    if (dataset.data && dataset.data.length > 0) {
                        dataset.data = [...dataset.data]; // Crear nueva referencia para forzar actualización
                    }
                });
                ingresosChartInstance.update('active');
            }
            
            if (serviciosChartInstance) {
                // Animar gráfico de servicios
                serviciosChartInstance.update('active');
            }
        }
        
        // Función para mostrar mensaje cuando no hay datos
        function mostrarMensajeSinDatos(container, tipo) {
            if (!container) return;
            
            const mensaje = document.createElement('div');
            mensaje.className = 'alert alert-info text-center';
            mensaje.style.margin = '20px';
            mensaje.innerHTML = `
                <i class="fas fa-chart-line fa-2x mb-3 d-block" style="color: var(--win-accent);"></i>
                <h6 class="text-danger">No hay datos disponibles</h6>
                <small class="text-dark">No se encontraron datos de ${tipo} para mostrar en el gráfico</small>
            `;
            
            // Limpiar contenedor y agregar mensaje
            const canvas = container.querySelector('canvas');
            if (canvas) {
                canvas.style.display = 'none';
            }
            container.appendChild(mensaje);
        }
        
        // Función para mostrar error
        function mostrarErrorGrafico(container, errorMsg) {
            if (!container) return;
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger text-center';
            errorDiv.style.margin = '20px';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                <h6>Error al cargar gráfico</h6>
                <small class="text-muted">${errorMsg}</small>
                <button onclick="reintentarGraficos()" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="fas fa-redo me-1"></i>Reintentar
                </button>
            `;
            
            container.appendChild(errorDiv);
        }
        
        // Función para reintentar
        function reintentarGraficos() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
            
            const canvases = document.querySelectorAll('.chart-container canvas');
            canvases.forEach(canvas => {
                canvas.style.display = 'block';
            });
            
            initCharts();
        }
        
        // Función para verificar si Chart.js está cargado
        function verificarChartJS() {
            if (typeof Chart === 'undefined') {
                console.error("Chart.js no está cargado");
                
                Swal.fire({
                    icon: 'error',
                    title: 'Biblioteca no encontrada',
                    html: `
                        <p>Chart.js no se pudo cargar. Verifica que:</p>
                        <ol class="text-start">
                            <li>El archivo <code>chart.umd.js</code> existe en <code>js/</code></li>
                            <li>La ruta en el HTML es correcta</li>
                        </ol>
                        <p>Puedes descargarlo desde: 
                        <a href="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.js" target="_blank">
                            https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.js
                        </a></p>
                    `,
                    confirmButtonText: '<i class="fas fa-fa-sync me-2"></i>Recargar página',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.reload();
                });
                
                return false;
            }
            
            return true;
        }
            // Aplicar estilos de tema a elementos específicos
            function applyThemeStyles() {
                const textColor = getComputedStyle(document.documentElement).getPropertyValue('--win-text-primary');
                const secondaryColor = getComputedStyle(document.documentElement).getPropertyValue('--win-text-secondary');
                
                // Aplicar colores a elementos que puedan no heredarlos
                document.querySelectorAll('.win-main-content h1, .win-main-content h2, .win-main-content h3, .win-main-content h4, .win-main-content h5, .win-main-content h6').forEach(el => {
                    el.style.color = textColor;
                });
                
                document.querySelectorAll('.win-main-content p, .win-main-content span:not(.badge)').forEach(el => {
                    if (!el.classList.contains('text-muted') && !el.classList.contains('text-success') && 
                        !el.classList.contains('text-warning') && !el.classList.contains('text-danger') &&
                        !el.classList.contains('text-primary') && !el.classList.contains('text-info')) {
                        el.style.color = textColor;
                    }
                });
            }
            
            // Aplicar estilos iniciales
            applyThemeStyles();
        // Esperar a que TODO esté completamente cargado
        window.addEventListener('load', function() {
            
            // Verificar que Chart.js esté disponible
            if (!verificarChartJS()) {
                return;
            }
            
            // Inicializar gráficos
            initCharts();
            
            // Configurar botones de periodo
            configurarCambioPeriodo();
            
            // Cerrar panel de temas al hacer clic en overlay
            document.getElementById('themeOverlay')?.addEventListener('click', cerrarPanelTemas);
            
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
                
                // Redimensionar gráficos
                if (ingresosChartInstance) ingresosChartInstance.resize();
                if (serviciosChartInstance) serviciosChartInstance.resize();
            });
            
            // Configurar botón de actualizar dashboard
            const btnActualizar = document.getElementById('btnActualizarDashboard');
            if (btnActualizar) {
                btnActualizar.addEventListener('click', actualizarDashboard);
            }
            
            // Aplicar estilos de tema
            applyThemeStyles();
        });
        
		
		// Función para exportar gráfico como imagen
        function exportChart(chartId, format) {
            const chart = chartId === 'ingresosChart' ? ingresosChartInstance : serviciosChartInstance;
            if (!chart) {
                Swal.fire('Error', 'Gráfico no disponible para exportar', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Exportando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                const canvas = chart.canvas;
                
                if (format === 'png') {
                    // Exportar como PNG
                    const link = document.createElement('a');
                    link.download = `${chartId}_${new Date().toISOString().slice(0,10)}.png`;
                    link.href = canvas.toDataURL('image/png', 1.0);
                    link.click();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Exportado',
                        text: 'Gráfico exportado como PNG',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else if (format === 'pdf') {
                    // Intentar exportar como PDF
                    if (typeof window.jspdf !== 'undefined') {
                        const { jsPDF } = window.jspdf;
                        const pdf = new jsPDF('landscape', 'mm', 'a4');
                        const imgData = canvas.toDataURL('image/png', 1.0);
                        
                        // Ajustar dimensiones
                        const pageWidth = pdf.internal.pageSize.getWidth();
                        const pageHeight = pdf.internal.pageSize.getHeight();
                        const imgWidth = pageWidth - 20;
                        const imgHeight = (canvas.height * imgWidth) / canvas.width;
                        
                        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
                        pdf.save(`${chartId}_${new Date().toISOString().slice(0,10)}.pdf`);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Exportado',
                            text: 'Gráfico exportado como PDF',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        // Fallback a PNG
                        Swal.fire({
                            icon: 'warning',
                            title: 'PDF no disponible',
                            text: 'Exportando como PNG en su lugar',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        const link = document.createElement('a');
                        link.download = `${chartId}_${new Date().toISOString().slice(0,10)}.png`;
                        link.href = canvas.toDataURL('image/png', 1.0);
                        link.click();
                    }
                }
            }, 500);
        }
        
        // Función para exportar datos del gráfico
        function exportChartData(chartId, format) {
            const chart = chartId === 'ingresosChart' ? ingresosChartInstance : serviciosChartInstance;
            if (!chart) {
                Swal.fire('Error', 'Datos no disponibles para exportar', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Exportando datos...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                const labels = chart.data.labels;
                const datasets = chart.data.datasets;
                
                if (format === 'json') {
                    // Exportar como JSON
                    const data = {
                        labels: labels,
                        datasets: datasets.map(dataset => ({
                            label: dataset.label,
                            data: dataset.data
                        }))
                    };
                    
                    const dataStr = JSON.stringify(data, null, 2);
                    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
                    
                    const link = document.createElement('a');
                    link.download = `${chartId}_data_${new Date().toISOString().slice(0,10)}.json`;
                    link.href = dataUri;
                    link.click();
                } else if (format === 'csv') {
                    // Exportar como CSV
                    let csvContent = "data:text/csv;charset=utf-8,";
                    
                    if (chartId === 'serviciosChart') {
                        // Para gráfico de servicios
                        csvContent += "Servicio,Cantidad\n";
                        labels.forEach((label, index) => {
                            csvContent += `"${label}",${datasets[0].data[index]}\n`;
                        });
                    } else {
                        // Para gráfico de ingresos
                        csvContent += "Periodo,Ingresos\n";
                        labels.forEach((label, index) => {
                            csvContent += `"${label}",${datasets[0].data[index]}\n`;
                        });
                    }
                    
                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement('a');
                    link.download = `${chartId}_data_${new Date().toISOString().slice(0,10)}.csv`;
                    link.href = encodedUri;
                    link.click();
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Datos exportados',
                    text: `Datos exportados como ${format.toUpperCase()}`,
                    timer: 1500,
                    showConfirmButton: false
                });
            }, 500);
        }
        
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
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            
			
			// Inicializar gráficos
            initCharts();
            
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
                
                // Redimensionar gráficos
                if (ingresosChartInstance) ingresosChartInstance.resize();
                if (serviciosChartInstance) serviciosChartInstance.resize();
            });
            
            // Actualizar dashboard automáticamente cada 5 minutos
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    // actualizarDashboard(); // Descomentar si quieres actualización automática
                }
            }, 300000);
            
            // Configurar botón de actualizar dashboard
            const btnActualizar = document.getElementById('btnActualizarDashboard');
            if (btnActualizar) {
                btnActualizar.addEventListener('click', actualizarDashboard);
            }
            
            // Configurar animación de Quick Actions
            const quickActions = document.querySelectorAll('.win-quick-action');
            quickActions.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        if (this.id === 'mainQuickAction') {
                            // Para el botón principal, mantener la rotación si está expandido
                            const expandedActions = document.getElementById('quickActionsExpanded');
                            if (expandedActions && expandedActions.classList.contains('show')) {
                                this.style.transform = 'rotate(45deg)';
                            } else {
                                this.style.transform = '';
                            }
                        } else {
                            this.style.transform = '';
                        }
                    }, 150);
                });
            });
            
           
            // Observar cambios en el tema y acento
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && 
                        (mutation.attributeName === 'data-theme' || mutation.attributeName === 'data-accent')) {
                        applyThemeStyles();
                        updateQuickActionsColors(); // Actualizar colores de Quick Actions
                    }
                });
            });
            
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme', 'data-accent']
            });
            
            // Inicializar colores de Quick Actions
            updateQuickActionsColors();
			
			
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
			
        });
        
        // Función para actualizar colores de Quick Actions al cambiar tema
        function updateQuickActionsColors() {
            const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--win-accent');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (mainButton) {
                mainButton.style.backgroundColor = accentColor;
            }
            
            // Actualizar botones expandidos si están visibles
            const expandedActions = document.getElementById('quickActionsExpanded');
            if (expandedActions && expandedActions.classList.contains('show')) {
                const expandedButtons = expandedActions.querySelectorAll('.win-quick-action');
                if (expandedButtons.length >= 3) {
                    expandedButtons[0].style.backgroundColor = accentColor;
                    expandedButtons[1].style.backgroundColor = colorMix(accentColor, '#107c10', 70);
                    expandedButtons[2].style.backgroundColor = colorMix(accentColor, '#5c2d91', 70);
                }
            }
        }
        
        // Función auxiliar para mezclar colores (similar a color-mix de CSS)
        function colorMix(color1, color2, percentage) {
            // Si el navegador soporta color-mix, úsalo
            if (CSS.supports('background-color', `color-mix(in srgb, ${color1} ${percentage}%, ${color2})`)) {
                return `color-mix(in srgb, ${color1} ${percentage}%, ${color2})`;
            }
            
            // Fallback para navegadores que no soportan color-mix
            const p = percentage / 100;
            const r1 = parseInt(color1.slice(1, 3), 16);
            const g1 = parseInt(color1.slice(3, 5), 16);
            const b1 = parseInt(color1.slice(5, 7), 16);
            const r2 = parseInt(color2.slice(1, 3), 16);
            const g2 = parseInt(color2.slice(3, 5), 16);
            const b2 = parseInt(color2.slice(5, 7), 16);
            
            const r = Math.round(r1 * p + r2 * (1 - p));
            const g = Math.round(g1 * p + g2 * (1 - p));
            const b = Math.round(b1 * p + b2 * (1 - p));
            
            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        }
// Variables globales
let facturaSeleccionada = null;

// Función para cargar información de la factura seleccionada (CORREGIDA)
function cargarInfoFactura() {
    const select = document.getElementById('selectFactura');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption || selectedOption.value === '') {
        facturaSeleccionada = null;
        document.getElementById('infoFacturaContainer').style.display = 'none';
        document.getElementById('noFacturaSelected').style.display = 'block';
        deshabilitarBotones();
        return;
    }
    
    // Obtener TODOS los datos de la factura, incluyendo fecha_pago y ref_pago
    let estado = selectedOption.getAttribute('data-estado');
    let estadoColor = selectedOption.getAttribute('data-estado-color');
    const fechaPagoRaw = selectedOption.getAttribute('data-fecha-pago');
    const refPagoRaw = selectedOption.getAttribute('data-ref-pago');
    
    // Formatear fecha de pago si existe
    let fechaPagoFormatted = 'No registrada';
    if (fechaPagoRaw && fechaPagoRaw.trim() !== '' && fechaPagoRaw !== '0000-00-00') {
        try {
            const fecha = new Date(fechaPagoRaw);
            if (!isNaN(fecha.getTime())) {
                fechaPagoFormatted = fecha.toLocaleDateString('es-ES');
            } else {
                fechaPagoFormatted = fechaPagoRaw; // Mostrar como viene si no se puede parsear
            }
        } catch (e) {
            fechaPagoFormatted = fechaPagoRaw;
        }
    }
    
    // Referencia de pago (si está vacía, mostrar mensaje)
    const refPagoMostrar = (refPagoRaw && refPagoRaw.trim() !== '') ? refPagoRaw : 'No especificada';
    
    // MODIFICACIÓN: Si el estado es CERRADA pero tiene fecha de pago, cambiar a PAGADA
    if (estado === 'CERRADA' && fechaPagoRaw && fechaPagoRaw.trim() !== '') {
        estado = 'PAGADA';
        estadoColor = 'bg-success'; // Color verde para pagada
    }
    
    // Guardar factura seleccionada
    facturaSeleccionada = {
        id: selectedOption.value,
        numero: selectedOption.getAttribute('data-numero'),
        cliente: selectedOption.getAttribute('data-cliente'),
        fecha: selectedOption.getAttribute('data-fecha'),
        fechaCont: selectedOption.getAttribute('data-fechacont') || selectedOption.getAttribute('data-fecha'),
        total: selectedOption.getAttribute('data-total'),
        estado: estado,
        estadoColor: estadoColor,
        fechaPago: fechaPagoFormatted,
        refPago: refPagoMostrar,
        fechaPagoRaw: fechaPagoRaw,
        refPagoRaw: refPagoRaw
    };
    
    // Actualizar campos principales
    document.getElementById('infoNumero').textContent = '#' + facturaSeleccionada.numero;
    document.getElementById('infoCliente').textContent = facturaSeleccionada.cliente;
    document.getElementById('infoFecha').textContent = facturaSeleccionada.fecha;
    document.getElementById('infoFechaCont').textContent = facturaSeleccionada.fechaCont;
    document.getElementById('infoTotal').textContent = '$' + parseFloat(facturaSeleccionada.total).toFixed(2) + ' CUP';
    
    // Actualizar estado
    const estadoElement = document.getElementById('infoEstado');
    estadoElement.textContent = facturaSeleccionada.estado;
    estadoElement.className = 'badge fs-6 px-3 py-2 ' + facturaSeleccionada.estadoColor;
    
    // --- GESTIÓN DE INFORMACIÓN DE PAGO ---
    // Buscar o crear el contenedor de información de pago
    let pagoContainer = document.getElementById('infoPagoContainer');
    
    if (!pagoContainer) {
        // Crear el contenedor si no existe
        const facturaInfoDiv = document.querySelector('.factura-info');
        if (facturaInfoDiv) {
            const nuevoHTML = `
                <div class="row mb-3" id="infoPagoContainer" style="display: none;">
                    <div class="col-12">
                        <hr style="border-color: var(--win-border-color); margin: 15px 0;">
                        <div class="bg-success bg-opacity-10 p-3 rounded-2" id="pagoInfoContent">
                            <h6 class="mb-2 text-success">
                                <i class="fas fa-check-circle me-2"></i>Información de Pago
                            </h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block mb-1">Fecha de Pago:</small>
                                    <strong id="infoFechaPago" style="color: var(--win-text-primary); font-weight: 500;">--/--/----</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block mb-1">Referencia de Pago:</small>
                                    <strong id="infoRefPago" style="color: var(--win-text-primary); font-weight: 500; word-break: break-word;">No especificada</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            // Insertar antes de los botones
            const botonesRow = facturaInfoDiv.querySelector('.row.g-2');
            if (botonesRow) {
                facturaInfoDiv.insertBefore(
                    document.createRange().createContextualFragment(nuevoHTML),
                    botonesRow
                );
            } else {
                facturaInfoDiv.insertAdjacentHTML('beforeend', nuevoHTML);
            }
            
            // Reobtener referencia al contenedor recién creado
            pagoContainer = document.getElementById('infoPagoContainer');
        }
    }
    
    // Actualizar la información de pago si el contenedor existe
    if (pagoContainer) {
        const fechaPagoElement = document.getElementById('infoFechaPago');
        const refPagoElement = document.getElementById('infoRefPago');
        const pagoContent = document.getElementById('pagoInfoContent');
        
        // Mostrar información de pago si la factura está pagada o tiene datos de pago
        const tieneDatosPago = (facturaSeleccionada.fechaPagoRaw && facturaSeleccionada.fechaPagoRaw.trim() !== '' && facturaSeleccionada.fechaPagoRaw !== '0000-00-00') || 
                               (facturaSeleccionada.refPagoRaw && facturaSeleccionada.refPagoRaw.trim() !== '');
        
        if (facturaSeleccionada.estado === 'PAGADA' || tieneDatosPago) {
            pagoContainer.style.display = 'block';
            
            if (fechaPagoElement) {
                fechaPagoElement.textContent = facturaSeleccionada.fechaPago;
            }
            if (refPagoElement) {
                refPagoElement.textContent = facturaSeleccionada.refPago;
            }
            
            // Resaltar visualmente
            if (pagoContent) {
                pagoContent.style.backgroundColor = 'rgba(25, 135, 84, 0.15)';
                pagoContent.style.border = '1px solid rgba(25, 135, 84, 0.3)';
            }
        } else {
            pagoContainer.style.display = 'none';
        }
    }
    
    // Mostrar/ocultar watermark "PAGADA"
    const watermarkElement = document.getElementById('pagadaWatermark');
    if (watermarkElement) {
        watermarkElement.style.display = facturaSeleccionada.estado === 'PAGADA' ? 'block' : 'none';
    }
    
    // Mostrar contenedor de factura y ocultar mensaje de no selección
    document.getElementById('infoFacturaContainer').style.display = 'block';
    document.getElementById('noFacturaSelected').style.display = 'none';
    
    // Habilitar botones
    habilitarBotones();
    
    // Efecto visual
    document.getElementById('infoFacturaContainer').style.animation = 'slideIn 0.3s ease';
}


// Función para habilitar botones
function habilitarBotones() {
    document.getElementById('btnVerFactura').disabled = false;
    document.getElementById('btnImprimirFactura').disabled = false;
    document.getElementById('btnEditarFactura').disabled = false;
}

// Función para deshabilitar botones
function deshabilitarBotones() {
    document.getElementById('btnVerFactura').disabled = true;
    document.getElementById('btnImprimirFactura').disabled = true;
    document.getElementById('btnEditarFactura').disabled = true;
}

// Función para ver factura
function verFactura() {
    if (!facturaSeleccionada) {
        Swal.fire('Error', 'No hay factura seleccionada', 'error');
        return;
    }
    
    window.open(`ver_factura.php?id=${facturaSeleccionada.id}`, '_blank');
}

// Función para imprimir factura
function imprimirFactura() {
    if (!facturaSeleccionada) {
        Swal.fire('Error', 'No hay factura seleccionada', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Imprimir Factura',
        html: `¿Desea imprimir la factura <strong>${facturaSeleccionada.numero}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Sí, imprimir',
        cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`ver_factura.php?id=${facturaSeleccionada.id}&autoPrint=true`, '_blank');
        }
    });
}


// Función para editar factura
function editarFactura() {
    if (!facturaSeleccionada) {
        Swal.fire('Error', 'No hay factura seleccionada', 'error');
        return;
    }
    
    window.location.href = `editar_factura.php?id=${facturaSeleccionada.id}`;
}

// Función para buscar facturas en el selector
function buscarFacturas() {
    const buscarNumero = document.getElementById('buscarNumeroFactura').value.toLowerCase();
    const buscarCliente = document.getElementById('buscarCliente').value.toLowerCase();
    const select = document.getElementById('selectFactura');
    const options = select.options;
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        const text = option.text.toLowerCase();
        const cliente = option.getAttribute('data-cliente')?.toLowerCase() || '';
        
        const matchNumero = buscarNumero === '' || text.includes(buscarNumero);
        const matchCliente = buscarCliente === '' || cliente.includes(buscarCliente);
        
        if (matchNumero && matchCliente) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
    // Si no hay ninguna opción seleccionada visible, seleccionar la primera visible
    if (select.selectedIndex === -1 || options[select.selectedIndex].style.display === 'none') {
        for (let i = 0; i < options.length; i++) {
            if (options[i].style.display !== 'none' && options[i].value !== '') {
                select.selectedIndex = i;
                cargarInfoFactura();
                break;
            }
        }
    }
}

// Inicializar

    document.addEventListener('DOMContentLoaded', function() {
        
// 2. Forzamos que la función inicie con ESE año específico
cambiarAnio(ANIO_INICIAL_PHP);

    // Añadir animación CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #selectFactura option {
            padding: 8px;
            border-bottom: 1px solid var(--win-border-color);
        }
        
        #selectFactura option:hover {
            background-color: var(--win-bg-tertiary);
        }
        
        #selectFactura option:checked {
            background-color: var(--win-accent-light);
            color: var(--win-accent);
        }
    `;
    document.head.appendChild(style);
    
	 // Seleccionar la primera factura por defecto si existe
			const select = document.getElementById('selectFactura');
			if(select && select.options.length > 0) {
				for (let i = 0; i < select.options.length; i++) {
					if (select.options[i].value !== '' && select.options[i].value !== '0') {
						select.selectedIndex = i;
						if (typeof cargarInfoFactura === "function") {
							cargarInfoFactura();
						}
						break;
					}
				}
			}
	});

// Función para actualizar el selector de facturas (MEJORADA)
function actualizarSelectorFacturas(facturas) {
    const select = document.getElementById('selectFactura');
    
    // Limpiar opciones actuales (excepto la primera)
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    if (!facturas || facturas.length === 0) {
        // Agregar opción de "no hay facturas"
        const option = document.createElement('option');
        option.value = "";
        option.text = "-- No hay facturas para este año --";
        option.disabled = true;
        select.appendChild(option);
        
        // Limpiar información de factura
        facturaSeleccionada = null;
        document.getElementById('infoFacturaContainer').style.display = 'none';
        document.getElementById('noFacturaSelected').style.display = 'block';
        deshabilitarBotones();
    } else {
        // Agregar nuevas facturas
        facturas.forEach(factura => {
            const option = document.createElement('option');
            option.value = factura.id;
            option.setAttribute('data-numero', factura.no_fact);
            option.setAttribute('data-cliente', factura.cliente_nombre || 'Cliente no especificado');
            option.setAttribute('data-fecha', formatDate(factura.fecha_emision));
            option.setAttribute('data-fechacont', formatDate(factura.fecha_contabilizacion));
            option.setAttribute('data-total', factura.total_general);
            option.setAttribute('data-estado', factura.estado);
            option.setAttribute('data-estado-color', getEstadoBadgeClass(factura.estado));
            
            // --- CORRECCIÓN: AGREGAR DATOS DE PAGO ---
            // Formatear fecha de pago si existe
            let fechaPagoFormatted = '';
            if (factura.fecha_pago && factura.fecha_pago !== '0000-00-00') {
                try {
                    const fechaPago = new Date(factura.fecha_pago);
                    if (!isNaN(fechaPago.getTime())) {
                        fechaPagoFormatted = fechaPago.toLocaleDateString('es-ES');
                    }
                } catch (e) {
                    fechaPagoFormatted = factura.fecha_pago;
                }
            }
            
            option.setAttribute('data-fecha-pago', fechaPagoFormatted || factura.fecha_pago || '');
            option.setAttribute('data-ref-pago', factura.Ref_pago || '');
            // --- FIN CORRECCIÓN ---
            
            const displayText = `${factura.no_fact} - ${factura.cliente_nombre || 'Cliente no especificado'} - ${formatDate(factura.fecha_emision)} - ${parseFloat(factura.total_general).toFixed(2)} CUP`;
            option.text = displayText;
            
            select.appendChild(option);
        });
        
        // Seleccionar la primera factura
        if (select.options.length > 1) {
            select.selectedIndex = 1;
            cargarInfoFactura();
        }
    }
}

// Función auxiliar para formatear fecha
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            // Si no es una fecha válida, devolver la cadena original
            return dateString;
        }
        return date.toLocaleDateString('es-ES');
    } catch (error) {
        console.error('Error formateando fecha:', dateString, error);
        return dateString;
    }
}

// Función auxiliar para obtener clase CSS del estado
function getEstadoBadgeClass(estado) {
    switch(estado) {
        case 'CONTABILIZADA': return 'bg-success';
        case 'PENDIENTE': return 'bg-warning';
        case 'ANULADA': return 'bg-danger';
		case 'PAGADA': return 'bg-primary';
        default: return 'bg-secondary';
    }
}


// Función para cargar detalles adicionales de la factura (CORREGIDA)
function cargarDetallesFactura(facturaId) {
    
    // Verificar que la factura esté seleccionada
    if (!facturaSeleccionada) {
        console.warn('No hay factura seleccionada');
        return;
    }
    
    // Datos de ejemplo - reemplazar con llamada AJAX real
    const datosEjemplo = {
        serie: facturaSeleccionada.numero ? facturaSeleccionada.numero.split('-')[0] || 'S/D' : 'S/D',
        tipo: 'NORMAL',
        descuento: '0.00',
        subtotal: facturaSeleccionada.total ? (parseFloat(facturaSeleccionada.total) * 0.9).toFixed(2) : '0.00',
        productos: Math.floor(Math.random() * 5) + 1,
        base_imponible: facturaSeleccionada.total ? (parseFloat(facturaSeleccionada.total) * 0.8).toFixed(2) : '0.00',
        fecha_contabilizacion: facturaSeleccionada.fecha || 'S/D'
    };
    
    // Actualizar campos con verificaciones de null
    const campos = [
        { id: 'infoSerie', value: 'Serie: ' + datosEjemplo.serie },
        { id: 'infoTipo', value: datosEjemplo.tipo },
        { id: 'infoDescuento', value: datosEjemplo.descuento + '%' },
        { id: 'infoSubtotal', value: '$' + datosEjemplo.subtotal },
        { id: 'infoProductos', value: datosEjemplo.productos.toString() },
        { id: 'infoBaseImponible', value: '$' + datosEjemplo.base_imponible },
        { id: 'infoFechaCont', value: datosEjemplo.fecha_contabilizacion }
    ];
    
    // Actualizar cada campo verificando que el elemento existe
    campos.forEach(campo => {
        const elemento = document.getElementById(campo.id);
        if (elemento) {
            elemento.textContent = campo.value;
        } else {
            console.warn('Elemento no encontrado:', campo.id);
        }
    });
    
}
    // =========================================================================
    // 1. CONSTANTES GLOBALES (Sincronización PHP -> JS)
    // =========================================================================
    
    // El año fijo de la Base de Datos (Para saber qué es Histórico/Futuro)
    const ANIO_BASE_DB = <?php echo $anio_actual; ?>; 

    // El año que PHP decidió mostrar al inicio (puede ser por GET o por defecto)
    const ANIO_INICIAL_PHP = <?php echo $anio_seleccionado; ?>;

    // =========================================================================
    // 2. FUNCIÓN PARA CAMBIAR AÑO (Lógica Blindada)
    // =========================================================================
// Función para actualizar el enlace "Ver Todas las Facturas"
function actualizarEnlaceFacturas(anio) {
    const anioSpan = document.getElementById('anioSeleccionadoEnlace');
    const enlaceBtn = document.getElementById('btnVerTodasFacturasAnio');
    
    if (anioSpan && enlaceBtn) {
        // Actualizar el texto del span
        anioSpan.textContent = anio;
        
        // Actualizar el href del enlace
        enlaceBtn.href = `facturas.php?registros=10000&anio=${anio}`;
        
    }
}

// Modificar la función cambiarAnio para incluir la actualización del enlace
function cambiarAnio(anio) {
    // Prevenir salto de página si viene de un evento click
    if(event && event.type === 'click') event.preventDefault();

    // Convertir a enteros puros para la comparación
    const seleccionado = parseInt(anio);
    const base = parseInt(ANIO_BASE_DB);

    
    // A. Calcular el Badge (Estado)
    let htmlBadge = '';
    
    if (seleccionado === base) {
        htmlBadge = '<span class="badge bg-success ms-2" title="Año actual de operaciones">OPERACIONES</span>';
    } else if (seleccionado < base) {
        htmlBadge = '<span class="badge bg-secondary ms-2" title="Año cerrado">HISTÓRICO</span>';
    } else {
        htmlBadge = '<span class="badge bg-info ms-2" title="Año futuro">FUTURO</span>';
    }

    // B. Actualizar Botón Dropdown (Texto + Badge)
    const textoBtn = document.getElementById('textoAnioSeleccionado');
    if (textoBtn) {
        textoBtn.innerHTML = `AÑO ${seleccionado} ${htmlBadge}`;
    }
    
    // C. Actualizar Badge pequeño del header (si existe)
    const badgeHeader = document.getElementById('anioSeleccionadoBadge');
    if (badgeHeader) badgeHeader.textContent = seleccionado;
    
    // D. Actualizar el enlace "Ver Todas las Facturas"
    actualizarEnlaceFacturas(seleccionado);

    // E. Actualizar clase 'active' en la lista
    const items = document.querySelectorAll('#listaAnios .dropdown-item');
    if (items) {
        items.forEach(item => {
            item.classList.remove('active');
            // Comparamos el texto del item (limpio) con el año
            if (item.innerText.trim().indexOf(seleccionado.toString()) === 0) {
                item.classList.add('active');
            }
        });
    }

    // F. Guardar variable global
    window.anioSeleccionado = seleccionado;

    // G. Cargar datos via AJAX
    const contadorElem = document.getElementById('contadorFacturasAnio');
    if(contadorElem) contadorElem.textContent = '...';

    fetch('facturas_por_anio.php?anio=' + seleccionado)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (document.getElementById('contadorFacturasAnio')) {
                    document.getElementById('contadorFacturasAnio').textContent = data.total_facturas;
                }
                if (data.facturas && typeof actualizarSelectorFacturas === 'function') {
                    actualizarSelectorFacturas(data.facturas);
                }
                if (data.total_bd && document.getElementById('badgeTotalBd')) {
                    document.getElementById('badgeTotalBd').innerHTML = 
                        `<i class="fas fa-database me-1"></i>${data.total_bd} Facturas en BD`;
                }
                // Re-forzar el badge por si el refresco lo borró
                if (textoBtn) textoBtn.innerHTML = `AÑO ${seleccionado} ${htmlBadge}`;
            } else {
                console.error('Error datos:', data.error);
            }
        })
        .catch(err => console.error('Error AJAX:', err));
}

// Función para preparar tabla para exportación (remover estilos problemáticos)
function prepararTablaParaExportacion(tablaOriginal) {
    const tablaClon = tablaOriginal.cloneNode(true);
    
    // 1. Remover botones de exportación
    const exportHeaders = tablaClon.querySelectorAll('.table-header-with-export');
    exportHeaders.forEach(header => header.remove());
    
    // 2. Remover clases CSS problemáticas
    const elementosProblema = tablaClon.querySelectorAll('[style*="color: var("], [style*="background: var("], [style*="border-color: var("]');
    elementosProblema.forEach(el => {
        el.style.cssText = el.style.cssText.replace(/color:\s*var\([^)]+\)/g, 'color: #000000');
        el.style.cssText = el.style.cssText.replace(/background:\s*var\([^)]+\)/g, 'background: #ffffff');
        el.style.cssText = el.style.cssText.replace(/border-color:\s*var\([^)]+\)/g, 'border-color: #dddddd');
    });
    
    // 3. Convertir colores específicos para cada tipo de tabla
    const tipoTabla = tablaOriginal.id;
    
    // Para tabla anual
    if (tipoTabla.includes('Anuales')) {
        const anos = tablaClon.querySelectorAll('td:first-child, th:first-child');
        anos.forEach(celda => {
            celda.style.color = '#000000';
            celda.style.fontWeight = 'bold';
        });
    }
    
    // Para tabla trimestral
    if (tipoTabla.includes('Trimestrales')) {
        const trimestres = tablaClon.querySelectorAll('td:first-child, th:first-child');
        trimestres.forEach(celda => {
            celda.style.color = '#000000';
            celda.innerHTML = celda.innerHTML.replace(/<[^>]*>/g, ''); // Remover badges
        });
        
        // Asegurar que se vean mejor/peor trimestre
        const filas = tablaClon.querySelectorAll('tr');
        filas.forEach(fila => {
            const textoFila = fila.textContent.toLowerCase();
            if (textoFila.includes('mejor') || textoFila.includes('peor')) {
                fila.style.backgroundColor = '#f8f9fa';
                fila.style.fontWeight = 'bold';
            }
        });
    }
    
    // Para tabla mensual
    if (tipoTabla.includes('Mensuales')) {
        const meses = tablaClon.querySelectorAll('td:first-child, th:first-child');
        meses.forEach(celda => {
            celda.style.color = '#000000';
            celda.innerHTML = celda.innerHTML.replace(/<[^>]*>/g, ''); // Remover badges
        });
    }
    
    // Para tabla diaria
    if (tipoTabla.includes('Diarios')) {
        const dias = tablaClon.querySelectorAll('td:first-child, td:nth-child(3), th:first-child, th:nth-child(3)');
        dias.forEach(celda => {
            celda.style.color = '#000000';
            celda.innerHTML = celda.innerHTML.replace(/<[^>]*>/g, ''); // Limpiar HTML
        });
    }
    
    // Para tabla semanal
    if (tipoTabla.includes('Semanales')) {
        const semanas = tablaClon.querySelectorAll('td:nth-child(2), td:nth-child(3), th:nth-child(2), th:nth-child(3)');
        semanas.forEach(celda => {
            celda.style.color = '#000000';
            celda.innerHTML = celda.innerHTML.replace(/<[^>]*>/g, ''); // Limpiar HTML
        });
    }
    
    // 4. Asegurar que todo el texto sea visible (negro sobre blanco)
    tablaClon.querySelectorAll('td, th').forEach(celda => {
        celda.style.color = '#000000 !important';
        celda.style.backgroundColor = '#ffffff !important';
        celda.style.borderColor = '#000000 !important';
    });
    
    return tablaClon;
}



// Función para obtener título según tipo de tabla
function obtenerTituloTabla(tablaId) {
    const titulos = {
        'tablaDatosAnuales': 'COMPARACIÓN DE INGRESOS - ÚLTIMOS 5 AÑOS',
        'tablaDatosDiarios': 'INGRESOS DIARIOS - <?php echo $mes_actual_es . " " . $anio_actual; ?>',
        'tablaDatosSemanales': 'INGRESOS SEMANALES - <?php echo $mes_actual_es . " " . $anio_actual; ?>',
        'tablaDatosMensuales': 'INGRESOS MENSUALES - AÑO <?php echo $anio_actual; ?>',
        'tablaDatosTrimestrales': 'INGRESOS TRIMESTRALES - AÑO <?php echo $anio_actual; ?>',
        'tablaServiciosData': 'SERVICIOS MÁS SOLICITADOS',
        'tablaCategorias': 'ANÁLISIS DE CATEGORÍAS'
    };
    
    return titulos[tablaId] || tablaId.replace('tabla', '').replace('Datos', '');
}

// Función para obtener subtítulo según tipo de tabla
function obtenerSubtituloTabla(tablaId) {
    const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
    
    const subtitulos = {
        'tablaDatosAnuales': `Período: ${aniosComparacion5Anios[0]} - ${aniosComparacion5Anios[aniosComparacion5Anios.length-1]}`,
        'tablaDatosDiarios': `Días del mes hasta: ${fechaCierre}`,
        'tablaDatosSemanales': `Semanas del mes hasta: ${fechaCierre}`,
        'tablaDatosMensuales': `Meses del año hasta: ${fechaCierre}`,
        'tablaDatosTrimestrales': `Trimestres del año hasta: ${fechaCierre}`,
        'tablaServiciosData': `Año de referencia: ${anioActual}`,
        'tablaCategorias': 'Categorías con más servicios asociados'
    };
    
    return subtitulos[tablaId] || `Fecha de cierre: ${fechaCierre}`;
}


// ============================================
// FUNCIONES DE EXPORTACIÓN UNIFORMES - CORREGIDAS
// ============================================

// Función helper para obtener elemento tabla
function getTableElement(param) {
    if (typeof param === 'string') {
        // Buscar por ID
        const element = document.getElementById(param);
        if (element) return element;
        
        // Si no encuentra, buscar en contenedores de datos
        const dataContainer = document.getElementById(param + 'Data');
        if (dataContainer) {
            const table = dataContainer.querySelector('table');
            if (table) return table;
        }
        
        console.error('Tabla no encontrada con ID:', param);
        return null;
    } else if (param && param.nodeType === 1) {
        return param;
    }
    console.error('Parámetro inválido para tabla:', param);
    return null;
}

// Función principal de exportación
function exportTable(tableId, format, filename = '') {
    const table = getTableElement(tableId);
    if (!table) {
        Swal.fire('Error', `Tabla "${tableId}" no encontrada`, 'error');
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: `Exportando a ${format.toUpperCase()}...`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        try {
            switch(format.toLowerCase()) {
                case 'excel':
                    exportToExcel(table, filename || table.id);
                    break;
                case 'word':
                    exportToWord(table, filename || table.id);
                    break;
                case 'pdf':
                    exportToPDF(table, filename || table.id);
                    break;
                case 'jpg':
                case 'png':
                    exportToImage(table, filename || table.id, format);
                    break;
                default:
                    Swal.fire('Error', 'Formato no soportado', 'error');
            }
        } catch (error) {
            console.error('Error exportando:', error);
            Swal.fire('Error', 'Error al exportar: ' + error.message, 'error');
        }
    }, 500);
}

// Versiones simplificadas para onclick
function exportTableToExcel(tableId, filename = '') {
    exportTable(tableId, 'excel', filename);
}

function exportTableToWord(tableId, filename = '') {
    exportTable(tableId, 'word', filename);
}

function exportTableToPDF(tableId, filename = '') {
    exportTable(tableId, 'pdf', filename);
}

function exportTableToImage(tableId, filename = '', format = 'jpg') {
    exportTable(tableId, format, filename);
}

// Función para imprimir
function printTable(tableId) {
    const table = getTableElement(tableId);
    if (!table) {
        Swal.fire('Error', `Tabla no encontrada`, 'error');
        return;
    }
    
    Swal.fire({
        title: 'Preparando para imprimir...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        // Preparar tabla para impresión
        const tableClone = prepararTablaParaExportacion(table);
        
        // Obtener título y subtítulo
        const titulo = obtenerTituloTabla(table.id);
        const subtitulo = obtenerSubtituloTabla(table.id);
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
        
        // Crear ventana de impresión SIN botones
        const printWindow = window.open('', '_blank', 'width=1000,height=700');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Imprimir Tabla - SISFACT PDL Visiones</title>
                <style>
                    
                    body { 
                        font-family: 'Segoe UI', Arial, sans-serif; 
                        margin: 25px; 
                        background: white;
                        color: #000;
                        line-height: 1.4;
                    }
                    .print-container {
                        max-width: 100%;
                        overflow: hidden;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 3px solid #0078d4;
                        background: linear-gradient(to right, #f8f9fa, #e9ecef);
                        padding: 20px;
                        border-radius: 8px;
                    }
                    .print-header h1 {
                        color: #0078d4;
                        margin: 0 0 10px 0;
                        font-size: 32px;
                        font-weight: 600;
                    }
                    .print-header h2 {
                        color: #333;
                        margin: 0 0 8px 0;
                        font-size: 22px;
                        font-weight: 500;
                    }
                    .print-header h3 {
                        color: #666;
                        margin: 0 0 15px 0;
                        font-size: 16px;
                        font-weight: normal;
                    }
                    .print-meta {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 25px;
                        padding: 15px;
                        background: #f8f9fa;
                        border-left: 4px solid #0078d4;
                        border-radius: 4px;
                        font-size: 13px;
                        color: #555;
                    }
                    .print-meta div {
                        flex: 1;
                        text-align: center;
                        padding: 0 10px;
                    }
                    .print-meta strong {
                        color: #0078d4;
                        display: block;
                        margin-bottom: 3px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 25px 0;
                        font-size: 13px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    }
                    th {
                        background-color: #0078d4;
                        color: white;
                        font-weight: 600;
                        text-align: left;
                        padding: 12px 10px;
                        border: 1px solid #005a9e;
                    }
                    td {
                        padding: 10px;
                        border: 1px solid #ddd;
                        color: #000;
                        vertical-align: top;
                    }
                    .total-row {
                        background-color: #e9ecef;
                        font-weight: bold;
                        border-top: 2px solid #0078d4;
                    }
                    .highlight-row {
                        background-color: #fff3cd !important;
                    }
                    .best-section {
                        background-color: #d4edda;
                        padding: 15px;
                        border-radius: 6px;
                        margin: 15px 0;
                        border-left: 5px solid #28a745;
                    }
                    .worst-section {
                        background-color: #f8d7da;
                        padding: 15px;
                        border-radius: 6px;
                        margin: 15px 0;
                        border-left: 5px solid #dc3545;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 40px;
                        padding-top: 20px;
                        border-top: 2px solid #0078d4;
                        font-size: 12px;
                        color: #666;
                    }
                    .footer strong {
                        color: #0078d4;
                    }
                    @media print {
                        body { margin: 15px; }
                        .no-print { display: none; }
                        table { page-break-inside: auto; }
                        tr { page-break-inside: avoid; }
                        .print-header {
                            background: white !important;
                        }
                    }
                    /* Estilos específicos para tipos de tabla */
                    .years-table td:first-child,
                    .trimesters-table td:first-child,
                    .months-table td:first-child,
                    .days-table td:first-child,
                    .weeks-table td:first-child {
                        font-weight: bold;
                        color: #000 !important;
                    }
                    /* Asegurar que los rangos se vean en semanal */
                    .weeks-table td:nth-child(3) {
                        color: #000 !important;
                        font-weight: 500;
                    }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <div class="print-header">
                        <h1>SISFACT PDL Visiones</h1>
                        <h2>${titulo}</h2>
                        <h3>${subtitulo}</h3>
                    </div>
                    
                    <div class="print-meta">
                        <div>
                            <strong>Generado:</strong> 
                            ${new Date().toLocaleDateString('es-ES', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}
                        </div>
                        <div>
                            <strong>Fecha de Cierre:</strong> ${fechaCierre}
                        </div>
                        <div>
                            <strong>Usuario:</strong> ${document.querySelector('.win-sidebar-user-info h6')?.innerText || 'Sistema'}
                        </div>
                    </div>
                    
                    <!-- Aplicar clase específica según tipo de tabla -->
                    <div class="${table.id.includes('Anuales') ? 'years-table' : 
                               table.id.includes('Trimestrales') ? 'trimesters-table' :
                               table.id.includes('Mensuales') ? 'months-table' :
                               table.id.includes('Diarios') ? 'days-table' :
                               table.id.includes('Semanales') ? 'weeks-table' : ''}">
                        ${tableClone.outerHTML}
                    </div>
                    
                    <!-- Agregar sección para mejor/peor si es trimestral -->
                    ${table.id.includes('Trimestrales') ? `
                        <div style="display: flex; gap: 15px; margin-top: 20px;">
                            <div class="best-section" style="flex: 1;">
                                <h4 style="margin: 0 0 8px 0; color: #28a745;">🏆 Mejor Trimestre</h4>
                                <p style="margin: 0;">Se muestra en la tabla con fondo destacado</p>
                            </div>
                            <div class="worst-section" style="flex: 1;">
                                <h4 style="margin: 0 0 8px 0; color: #dc3545;">📉 Peor Trimestre</h4>
                                <p style="margin: 0;">Se muestra en la tabla con fondo destacado</p>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="footer">
                        <p><strong>Sistema de Facturación PDL Visiones</strong></p>
                        <p>Documento generado automáticamente | Fecha de cierre de operaciones: ${fechaCierre}</p>
                        <p>Período contable vigente | © ${new Date().getFullYear()} PDL Visiones</p>
                    </div>
                </div>
                <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
                <script>
                    // Auto-imprimir después de 1.5 segundos
                    setTimeout(() => {
                        window.focus();
                        window.print();
                    }, 1500);
                <\/script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        Swal.close();
        
    }, 1000);
}
async function exportToPDF(tableElement, filename = '') {
    if (typeof html2canvas === 'undefined' || typeof jspdf === 'undefined') {
        await Promise.all([
            loadScript('js/html2canvas.min.js'),
            loadScript('js/jspdf.umd.min.js')
        ]);
    }
    
    try {
        // Preparar tabla para exportación
        const tableClone = prepararTablaParaExportacion(tableElement);
        
        // Obtener título y subtítulo
        const titulo = obtenerTituloTabla(tableElement.id);
        const subtitulo = obtenerSubtituloTabla(tableElement.id);
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
        
        const container = document.createElement('div');
        container.style.padding = '20px';
        container.style.backgroundColor = 'white';
        container.style.fontFamily = 'Arial, sans-serif';
        container.innerHTML = `
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #0078d4;">
                <h1 style="color: #0078d4; margin: 0 0 10px 0; font-size: 24px;">SISFACT PDL Visiones</h1>
                <h2 style="color: #333; margin: 0 0 8px 0; font-size: 18px;">${titulo}</h2>
                <h3 style="color: #666; margin: 0 0 15px 0; font-size: 14px; font-weight: normal;">${subtitulo}</h3>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #555;">
                    <div>Generado: ${new Date().toLocaleDateString('es-ES')}</div>
                    <div>Fecha de Cierre: ${fechaCierre}</div>
                </div>
            </div>
            ${tableClone.outerHTML}
            <div style="margin-top: 30px; text-align: center; color: #666; font-size: 11px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p>Sistema de Facturación PDL Visiones - Documento generado automáticamente</p>
                <p>Fecha de cierre de operaciones: ${fechaCierre}</p>
            </div>
        `;
        
        document.body.appendChild(container);
        
        // Configurar html2canvas con opciones específicas para mejor captura
        const canvas = await html2canvas(container, {
            scale: 2,
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true,
            allowTaint: true,
            // Forzar colores
            onclone: function(clonedDoc) {
                // Asegurar que todo el texto sea negro
                clonedDoc.querySelectorAll('*').forEach(el => {
                    const computedColor = window.getComputedStyle(el).color;
                    if (computedColor.includes('var(') || computedColor.includes('rgba')) {
                        el.style.color = '#000000';
                    }
                    // Forzar fondo blanco
                    el.style.backgroundColor = '#ffffff';
                });
            }
        });
        
        document.body.removeChild(container);
        
        const imgData = canvas.toDataURL('image/jpeg', 1.0);
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const imgWidth = pageWidth - (2 * margin);
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        // Calcular si necesita múltiples páginas
        let heightLeft = imgHeight;
        let position = margin;
        let page = 1;
        
        pdf.addImage(imgData, 'JPEG', margin, position, imgWidth, imgHeight);
        heightLeft -= (pageHeight - (2 * margin));
        
        // Agregar páginas adicionales si es necesario
        while (heightLeft > 0) {
            position = -(pageHeight - (2 * margin)) + heightLeft;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - (2 * margin));
            page++;
        }
        
        // Agregar pie de página en todas las páginas
        const totalPages = pdf.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            pdf.setPage(i);
            pdf.setFontSize(8);
            pdf.setTextColor(100);
            pdf.text(`Página ${i} de ${totalPages} - Fecha de Cierre: ${fechaCierre}`, 
                    pageWidth - margin - 50, pageHeight - 5);
        }
        
        const date = new Date().toISOString().slice(0, 10);
        const exportName = filename ? `${filename}_${date}.pdf` : `${tableElement.id}_${date}.pdf`;
        
        pdf.save(exportName);
        
        Swal.fire({
            icon: 'success',
            title: '¡Exportado!',
            text: `Tabla exportada como ${exportName}`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportToPDF:', error);
        throw new Error('Error al exportar a PDF: ' + error.message);
    }
}

async function exportToWord(tableElement, filename = '') {
    try {
        // Preparar tabla para exportación
        const tableClone = prepararTablaParaExportacion(tableElement);
        
        // Obtener título y subtítulo
        const titulo = obtenerTituloTabla(tableElement.id);
        const subtitulo = obtenerSubtituloTabla(tableElement.id);
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
        
        // Crear contenido HTML para Word mejorado
        const htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { 
                        font-family: 'Segoe UI', Arial, sans-serif; 
                        margin: 2cm 1.5cm; 
                        color: #000000;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        padding-bottom: 15px;
                        border-bottom: 3px solid #0078d4;
                    }
                    .header h1 {
                        color: #0078d4;
                        margin: 0 0 10px 0;
                        font-size: 28px;
                    }
                    .header h2 {
                        color: #333333;
                        margin: 0 0 8px 0;
                        font-size: 20px;
                    }
                    .header h3 {
                        color: #666666;
                        margin: 0 0 15px 0;
                        font-size: 16px;
                        font-weight: normal;
                    }
                    .meta-info {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 25px;
                        padding: 10px;
                        background-color: #f8f9fa;
                        border-left: 4px solid #0078d4;
                        font-size: 12px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                        font-size: 11pt;
                    }
                    th {
                        background-color: #0078d4;
                        color: white;
                        font-weight: bold;
                        text-align: left;
                        padding: 10px 8px;
                        border: 1px solid #005a9e;
                    }
                    td {
                        padding: 8px;
                        border: 1px solid #dddddd;
                        color: #000000;
                    }
                    .total-row {
                        background-color: #e9ecef;
                        font-weight: bold;
                    }
                    .highlight-row {
                        background-color: #fff3cd;
                    }
                    .best-row {
                        background-color: #d4edda;
                        border-left: 4px solid #28a745;
                    }
                    .worst-row {
                        background-color: #f8d7da;
                        border-left: 4px solid #dc3545;
                    }
                    .footer {
                        margin-top: 40px;
                        padding-top: 15px;
                        border-top: 1px solid #ddd;
                        font-size: 10pt;
                        color: #666;
                        text-align: center;
                    }
                    .footer strong {
                        color: #0078d4;
                    }
                    @page {
                        margin: 2cm 1.5cm;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>SISFACT PDL Visiones</h1>
                    <h2>${titulo}</h2>
                    <h3>${subtitulo}</h3>
                </div>
                
                <div class="meta-info">
                    <div>
                        <strong>Generado:</strong> ${new Date().toLocaleDateString('es-ES', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                    </div>
                    <div>
                        <strong>Fecha de Cierre:</strong> ${fechaCierre}
                    </div>
                    <div>
                        <strong>Usuario:</strong> ${document.querySelector('.win-sidebar-user-info h6')?.innerText || 'Sistema'}
                    </div>
                </div>
                
                ${tableClone.outerHTML}
                
                <div class="footer">
                    <p><strong>Sistema de Facturación PDL Visiones</strong> - Documento generado automáticamente</p>
                    <p>Fecha de cierre de operaciones: ${fechaCierre} | Período contable vigente</p>
                    <p>© ${new Date().getFullYear()} PDL Visiones - Todos los derechos reservados</p>
                </div>
            </body>
            </html>
        `;
        
        const date = new Date().toISOString().slice(0, 10);
        const exportName = filename ? `${filename}_${date}.doc` : `${tableElement.id}_${date}.doc`;
        
        // Crear blob y descargar
        const blob = new Blob(['\ufeff', htmlContent], {
            type: 'application/msword'
        });
        
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = exportName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            icon: 'success',
            title: '¡Exportado!',
            text: `Tabla exportada como ${exportName}`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportToWord:', error);
        throw new Error('Error al exportar a Word: ' + error.message);
    }
}

async function exportToExcel(tableElement, filename = '') {
    if (typeof XLSX === 'undefined') {
        await loadScript('js/xlsx.full.min.js');
    }
    
    try {
        // Preparar tabla para exportación
        const tableClone = prepararTablaParaExportacion(tableElement);
        
        // Obtener título y subtítulo
        const titulo = obtenerTituloTabla(tableElement.id);
        const subtitulo = obtenerSubtituloTabla(tableElement.id);
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
        
        // 1. Crear libro de Excel
        const wb = XLSX.utils.book_new();
        
        // 2. Preparar datos para Excel
        const wsData = [];
        
        // 2.1. Agregar encabezado con título y subtítulo
        wsData.push(["SISFACT PDL Visiones"]);
        wsData.push([titulo]);
        wsData.push([subtitulo]);
        wsData.push([]); // Línea vacía
        
        // 2.2. Agregar información de metadatos
        wsData.push(["INFORMACIÓN DEL REPORTE"]);
        wsData.push(["Generado:", new Date().toLocaleDateString('es-ES', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })]);
        wsData.push(["Fecha de Cierre:", fechaCierre]);
        wsData.push(["Usuario:", document.querySelector('.win-sidebar-user-info h6')?.innerText || 'Sistema']);
        wsData.push([]); // Línea vacía
        
        // 2.3. Convertir tabla HTML a datos para Excel
        const tableData = XLSX.utils.table_to_sheet(tableClone);
        
        // 2.4. Obtener el rango de datos de la tabla
        const range = XLSX.utils.decode_range(tableData['!ref']);
        
        // 2.5. Mover la tabla para dejar espacio para el encabezado
        // Agregamos 9 filas de espacio para el encabezado (5 filas de título + 4 filas de metadata)
        const headerRows = 9;
        
        // Crear nueva hoja de cálculo combinando encabezado y tabla
        const combinedData = [];
        
        // Primero, agregar las filas del encabezado
        for (let row of wsData) {
            combinedData.push(row);
        }
        
        // Luego, agregar los datos de la tabla
        for (let R = range.s.r; R <= range.e.r; ++R) {
            const row = [];
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cell_address = XLSX.utils.encode_cell({r: R, c: C});
                const cell = tableData[cell_address];
                row.push(cell ? cell.v : '');
            }
            combinedData.push(row);
        }
        
        // 3. Crear hoja de cálculo con datos combinados
        const ws = XLSX.utils.aoa_to_sheet(combinedData);
        
        // 4. Aplicar estilos y formatos
        const wsCols = [];
        const maxCols = range.e.c - range.s.c + 1;
        
        // 4.1. Determinar anchos de columna basados en contenido
        for (let C = 0; C <= maxCols; C++) {
            let maxLength = 0;
            
            // Buscar la celda más larga en esta columna
            for (let R = 0; R < combinedData.length; R++) {
                const cellValue = combinedData[R]?.[C];
                if (cellValue != null) {
                    const cellLength = cellValue.toString().length;
                    if (cellLength > maxLength) maxLength = cellLength;
                }
            }
            
            // Establecer ancho de columna (mínimo 10, máximo 50)
            wsCols.push({wch: Math.min(Math.max(maxLength + 2, 10), 50)});
        }
        
        ws['!cols'] = wsCols;
        
        // 4.2. Aplicar estilos a las celdas del encabezado
        // Título principal (fila 0)
        if (!ws['A1']) ws['A1'] = {};
        ws['A1'].s = {
            font: { sz: 16, bold: true, color: { rgb: "0078D4" } },
            alignment: { horizontal: "center" }
        };
        
        // Título del reporte (fila 1)
        if (!ws['A2']) ws['A2'] = {};
        ws['A2'].s = {
            font: { sz: 14, bold: true },
            alignment: { horizontal: "center" }
        };
        
        // Subtítulo (fila 2)
        if (!ws['A3']) ws['A3'] = {};
        ws['A3'].s = {
            font: { sz: 12, italic: true },
            alignment: { horizontal: "center" }
        };
        
        // Sección "INFORMACIÓN DEL REPORTE" (fila 4)
        if (!ws['A5']) ws['A5'] = {};
        ws['A5'].s = {
            font: { sz: 11, bold: true, color: { rgb: "FF6B35" } }
        };
        
        // Metadatos (filas 5-8)
        for (let i = 5; i <= 8; i++) {
            const cellA = `A${i + 1}`;
            const cellB = `B${i + 1}`;
            
            if (!ws[cellA]) ws[cellA] = {};
            ws[cellA].s = {
                font: { bold: true }
            };
            
            if (!ws[cellB]) ws[cellB] = {};
            ws[cellB].s = {
                font: { color: { rgb: "666666" } }
            };
        }
        
        // 4.3. Aplicar estilos a los encabezados de la tabla
        const tableHeaderRow = headerRows; // Fila donde empiezan los encabezados de la tabla
        for (let C = 0; C <= maxCols; C++) {
            const cellAddress = XLSX.utils.encode_cell({r: tableHeaderRow, c: C});
            if (!ws[cellAddress]) continue;
            
            if (!ws[cellAddress].s) ws[cellAddress].s = {};
            ws[cellAddress].s = {
                font: { bold: true, color: { rgb: "FFFFFF" } },
                fill: { fgColor: { rgb: "0078D4" } },
                alignment: { horizontal: "center" }
            };
        }
        
        // 4.4. Aplicar formato de moneda a las columnas que contengan montos
        const currencyKeywords = ['total', 'ingresos', 'monto', '$', 'cup', 'importe'];
        
        for (let R = tableHeaderRow + 1; R < combinedData.length; R++) {
            for (let C = 0; C <= maxCols; C++) {
                const cellValue = combinedData[R]?.[C];
                if (cellValue && typeof cellValue === 'string') {
                    // Verificar si la celda superior (encabezado) contiene palabras clave de moneda
                    const headerValue = combinedData[tableHeaderRow]?.[C];
                    if (headerValue && typeof headerValue === 'string') {
                        const headerLower = headerValue.toString().toLowerCase();
                        const cellLower = cellValue.toString().toLowerCase();
                        
                        if (currencyKeywords.some(keyword => headerLower.includes(keyword)) ||
                            cellLower.includes('$') || cellLower.includes('cup')) {
                            
                            const cellAddress = XLSX.utils.encode_cell({r: R, c: C});
                            if (!ws[cellAddress]) ws[cellAddress] = {};
                            
                            if (!ws[cellAddress].s) ws[cellAddress].s = {};
                            ws[cellAddress].s.numFmt = '"$"#,##0.00';
                            ws[cellAddress].s.font = { color: { rgb: "198754" } };
                        }
                    }
                }
            }
        }
        
        // 4.5. Aplicar formato a las filas de totales
        for (let R = tableHeaderRow + 1; R < combinedData.length; R++) {
            const firstCellValue = combinedData[R]?.[0];
            if (firstCellValue && typeof firstCellValue === 'string') {
                const cellLower = firstCellValue.toString().toLowerCase();
                if (cellLower.includes('total') || cellLower.includes('resumen')) {
                    // Aplicar estilo a toda la fila de total
                    for (let C = 0; C <= maxCols; C++) {
                        const cellAddress = XLSX.utils.encode_cell({r: R, c: C});
                        if (!ws[cellAddress]) continue;
                        
                        if (!ws[cellAddress].s) ws[cellAddress].s = {};
                        ws[cellAddress].s.font = { bold: true };
                        ws[cellAddress].s.fill = { fgColor: { rgb: "E9ECEF" } };
                    }
                }
            }
        }
        
        // 5. Agregar fórmulas y análisis si es necesario
        if (tableElement.id.includes('Anuales') || tableElement.id.includes('Mensuales')) {
            // Agregar fila de resumen al final
            const lastRow = combinedData.length;
            
            // Fila vacía
            combinedData.push([]);
            
            // Resumen estadístico
            combinedData.push(["RESUMEN ESTADÍSTICO", "", "", "", ""]);
            combinedData.push(["Total General:", { f: `SUM(B${headerRows + 2}:B${lastRow})` }]);
            combinedData.push(["Promedio:", { f: `AVERAGE(B${headerRows + 2}:B${lastRow})` }]);
            combinedData.push(["Máximo:", { f: `MAX(B${headerRows + 2}:B${lastRow})` }]);
            combinedData.push(["Mínimo:", { f: `MIN(B${headerRows + 2}:B${lastRow})` }]);
            
            // Actualizar la hoja con las nuevas filas
            const newWs = XLSX.utils.aoa_to_sheet(combinedData);
            
            // Copiar estilos de la hoja original
            Object.keys(ws).forEach(key => {
                if (key.startsWith('!')) return;
                if (!newWs[key]) newWs[key] = {};
                newWs[key].s = ws[key].s;
            });
            
            // Aplicar estilos a las filas de resumen
            const resumeStart = lastRow + 2;
            for (let i = resumeStart; i <= resumeStart + 4; i++) {
                const cellA = `A${i}`;
                const cellB = `B${i}`;
                
                if (!newWs[cellA]) newWs[cellA] = {};
                newWs[cellA].s = {
                    font: { bold: true, color: { rgb: "495057" } }
                };
                
                if (!newWs[cellB]) newWs[cellB] = {};
                newWs[cellB].s = {
                    font: { bold: true, color: { rgb: "0078D4" } },
                    numFmt: '"$"#,##0.00'
                };
            }
            
            // Reemplazar la hoja original con la nueva
            Object.assign(ws, newWs);
        }
        
        // 6. Agregar hoja al libro
        XLSX.utils.book_append_sheet(wb, ws, "Reporte");
        
        // 7. Agregar hoja de información adicional
        const infoWs = XLSX.utils.aoa_to_sheet([
            ["INFORMACIÓN ADICIONAL"],
            [],
            ["Sistema:", "SISFACT PDL Visiones"],
            ["Versión:", "2.3.3"],
            ["Fecha de Exportación:", new Date().toLocaleDateString('es-ES')],
            ["Fecha de Cierre Operaciones:", fechaCierre],
            ["Año de Referencia:", "<?php echo $anio_actual; ?>"],
            [],
            ["NOTAS:"],
            ["1. Todos los montos están expresados en CUP"],
            ["2. Fecha de cierre: " + fechaCierre],
            ["3. Documento generado automáticamente"],
            ["4. Para consultas contactar al administrador del sistema"]
        ]);
        
        // Estilizar hoja de información
        infoWs['!cols'] = [{wch: 25}, {wch: 40}];
        
        if (!infoWs['A1']) infoWs['A1'] = {};
        infoWs['A1'].s = {
            font: { sz: 14, bold: true, color: { rgb: "0078D4" } }
        };
        
        if (!infoWs['A9']) infoWs['A9'] = {};
        infoWs['A9'].s = {
            font: { bold: true, color: { rgb: "FF6B35" } }
        };
        
        XLSX.utils.book_append_sheet(wb, infoWs, "Información");
        
        // 8. Generar nombre del archivo
        const date = new Date().toISOString().slice(0, 10);
        const safeTableName = tableElement.id.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const exportName = filename ? `${filename}_${date}.xlsx` : `${safeTableName}_reporte_${date}.xlsx`;
        
        // 9. Guardar archivo
        XLSX.writeFile(wb, exportName);
        
        // 10. Mostrar confirmación
        Swal.fire({
            icon: 'success',
            title: '¡Exportado!',
            text: `Tabla exportada como ${exportName}`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportToExcel:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al exportar',
            html: `
                <div style="text-align: left;">
                    <p>No se pudo exportar a Excel:</p>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p><strong>Solución:</strong></p>
                    <ol style="text-align: left; margin-left: 20px;">
                        <li>Verifica que el archivo <code>xlsx.full.min.js</code> esté cargado</li>
                        <li>Intenta exportar otra tabla</li>
                        <li>Recarga la página e intenta nuevamente</li>
                    </ol>
                </div>
            `,
            confirmButtonText: 'Entendido'
        });
        throw new Error('Error al exportar a Excel: ' + error.message);
    }
}

async function exportToImage(tableElement, filename = '', format = 'jpg') {
    if (typeof html2canvas === 'undefined') {
        await loadScript('js/html2canvas.min.js');
    }
    
    try {
        // Preparar tabla para exportación
        const tableClone = prepararTablaParaExportacion(tableElement);
        
        // Obtener título y subtítulo
        const titulo = obtenerTituloTabla(tableElement.id);
        const subtitulo = obtenerSubtituloTabla(tableElement.id);
        const fechaCierre = "<?php echo date('d/m/Y', $timestamp_op); ?>";
        
        const container = document.createElement('div');
        container.style.padding = '25px';
        container.style.backgroundColor = 'white';
        container.style.border = '3px solid #ff6b35';
        container.style.fontFamily = 'Arial, sans-serif';
        container.style.maxWidth = '1000px';
        container.style.margin = '0 auto';
        container.innerHTML = `
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #ff6b35;">
                <h1 style="color: #ff6b35; margin: 0 0 10px 0; font-size: 26px;">SISFACT PDL Visiones</h1>
                <h2 style="color: #333; margin: 0 0 8px 0; font-size: 20px;">${titulo}</h2>
                <h3 style="color: #666; margin: 0 0 15px 0; font-size: 16px; font-weight: normal;">${subtitulo}</h3>
                <div style="display: flex; justify-content: space-between; background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;">
                    <div><strong>Generado:</strong> ${new Date().toLocaleDateString('es-ES')}</div>
                    <div><strong>Fecha Cierre:</strong> ${fechaCierre}</div>
                    <div><strong>Usuario:</strong> ${document.querySelector('.win-sidebar-user-info h6')?.innerText || 'Sistema'}</div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                ${tableClone.outerHTML}
            </div>
            
            <div style="margin-top: 25px; text-align: center; color: #666; font-size: 11px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p><strong>Sistema de Facturación PDL Visiones</strong></p>
                <p>Fecha de cierre de operaciones: ${fechaCierre} | Documento de referencia</p>
                <p>${new Date().toLocaleDateString('es-ES', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</p>
            </div>
        `;
        
        document.body.appendChild(container);
        
        // Configuración específica para imágenes
        const canvas = await html2canvas(container, {
            scale: 3, // Alta resolución para imágenes
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true,
            allowTaint: true,
            // Configuración específica para mejorar la captura
            windowWidth: container.scrollWidth,
            windowHeight: container.scrollHeight,
            // Forzar estilo para imágenes
            onclone: function(clonedDoc, clonedElement) {
                // Asegurar que todo el texto sea negro y legible
                clonedElement.querySelectorAll('*').forEach(el => {
                    // Forzar color negro
                    el.style.color = '#000000';
                    // Forzar fondo blanco
                    el.style.backgroundColor = el.style.backgroundColor.includes('var') ? '#ffffff' : el.style.backgroundColor;
                    // Asegurar bordes visibles
                    if (el.tagName === 'TD' || el.tagName === 'TH') {
                        el.style.border = '1px solid #000000';
                    }
                });
                
                // Aumentar tamaño de fuente para mejor legibilidad
                clonedElement.querySelectorAll('td, th').forEach(el => {
                    el.style.fontSize = '12px';
                });
                
                // Asegurar que las tablas tengan ancho completo
                clonedElement.querySelectorAll('table').forEach(table => {
                    table.style.width = '100%';
                    table.style.minWidth = '800px';
                });
            }
        });
        
        document.body.removeChild(container);
        
        const date = new Date().toISOString().slice(0, 10);
        const exportName = filename ? `${filename}_${date}.${format}` : `${tableElement.id}_${date}.${format}`;
        
        const link = document.createElement('a');
        link.download = exportName;
        link.href = canvas.toDataURL(`image/${format === 'jpg' ? 'jpeg' : format}`, 1.0);
        link.click();
        
        Swal.fire({
            icon: 'success',
            title: '¡Exportado!',
            text: `Tabla exportada como ${exportName}`,
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error en exportToImage:', error);
        throw new Error('Error al exportar a imagen: ' + error.message);
    }
}



// Función auxiliar para cargar scripts dinámicamente
function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}
// Función para mostrar/ocultar el chatbot desde Quick Actions
function toggleChatbot() {
    // Verificar si ya existe el chatbot
    let chatbot = document.getElementById('sisfact-chatbot');
    
    if (!chatbot) {
        // Si no existe, crearlo (esto inicializaría el chatbot si usas la clase de antes)
        // O puedes simplemente mostrar el contenedor si ya lo tienes pero oculto
        if (typeof window.chatbotInstance === 'undefined') {
            window.chatbotInstance = new SISFACTChatbot();
        }
    } else {
        // Si existe, minimizarlo o maximizarlo
        if (chatbot.classList.contains('minimized')) {
            // Si está minimizado, expandirlo
            chatbot.classList.remove('minimized');
            // Aquí deberías también mostrar los elementos internos
        } else {
            // Si está expandido, minimizarlo
            chatbot.classList.add('minimized');
        }
    }
    
    // Opcional: cerrar el menú de Quick Actions después de seleccionar
    document.getElementById('quickActionsExpanded').classList.remove('show');
    document.getElementById('mainQuickAction').innerHTML = '<i class="fas fa-plus"></i>';
}
    </script>
<!-- Chatbot JS -->
<script src="js/chatbot.js"></script>
    <?php
    // Incluir footer
    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
    ?>
</body>
</html>