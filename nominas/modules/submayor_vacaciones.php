<?php
// modules/submayor_vacaciones.php - Refactorizado bajo Metodología Laboral Cubana (9.09%)

// 1. Carga de configuración (config/database.php lee las credenciales desde config.php)
require_once '../config/database.php';

// 2. Control de seguridad por si la sesión no se inició en el paso anterior
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar privilegios de acceso
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Evitar caché del navegador para que siempre se apliquen los cambios
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Control de acceso por rol
if (!permiso_puede('submayor', 'ver')) {
    permiso_denegar_acceso('Submayor de Vacaciones');
}
$puede_crear_submayor = permiso_puede('submayor', 'crear');
$puede_editar_submayor = permiso_puede('submayor', 'editar');

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';

// Configuración empresa
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas', 'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_gestionRRHH')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestionRRHH') $config_empresa['especialista_gestionRRHH'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Ruta del logo - UNIFICADA
$ruta_logo = '../../images/logotn.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}
// Variable para usar en el reporte VERSAT
$logo_base64_global = $logo_base64;

// Obtener días laborables mensuales desde configuración
$stmt_dias = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'");
$dias_mensuales_config = $stmt_dias->fetchColumn();
$dias_laborables = $dias_mensuales_config ? (int)$dias_mensuales_config : 24;

// Detectar si es petición AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// Formateadores auxiliares
if (!function_exists('parseFloat')) {
    function parseFloat($val) {
        return floatval(str_replace([' ', '$', ','], ['', '', ''], $val));
    }
}
if (!function_exists('formatearMoneda')) {
    function formatearMoneda($valor) { return '$' . number_format($valor, 2, '.', ','); }
}
if (!function_exists('numeroRomano')) {
    function numeroRomano($numero) {
        $romanos = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII',13=>'XIII',14=>'XIV',15=>'XV',16=>'XVI'];
        return $romanos[$numero] ?? $numero;
    }
}
if (!function_exists('nombreMesEspanol')) {
    function nombreMesEspanol($mes) {
        $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
        return $meses[$mes] ?? '';
    }
}

// Registro transaccional de un movimiento del submayor (con validación previa desde el POST)
if (!function_exists('registrarMovimientoVacacionesValidado')) {
    function registrarMovimientoVacacionesValidado($pdo, $trabajador_id, $periodo_d, $periodo_h, $tipo_mov, $dias, $importe, $fecha_mov, $ref, $obs, $usuario) {
        $response = ['success' => false, 'message' => ''];
        try {
            $pdo->beginTransaction();

            // 1. Insertar movimiento en submayor
            $stmt_ins = $pdo->prepare("
                INSERT INTO submayor_vacaciones (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, fecha_movimiento, referencia, usuario_registro, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_ins->execute([
                $trabajador_id, $periodo_d, $periodo_h, $tipo_mov, $dias, $importe, $fecha_mov, $ref, $usuario, $obs
            ]);

            // 2. Actualizar el saldo acumulado en la ficha del trabajador
            // Disfrute descuenta; acumulación suma; ajuste aplica el signo del día (permite ajustes negativos)
            $mod_dias = ($tipo_mov === 'disfrute') ? -$dias : $dias;

            $stmt_upd = $pdo->prepare("
                UPDATE trabajadores 
                SET vacaciones_acumuladas = COALESCE(vacaciones_acumuladas, 0) + ? 
                WHERE id = ?
            ");
            $stmt_upd->execute([$mod_dias, $trabajador_id]);

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Movimiento de vacaciones registrado exitosamente en el submayor.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $response['message'] = 'Error en transacción: ' . $e->getMessage();
        }
        return $response;
    }
}

// -----------------------------------------------------------------------------
// PROCESAMIENTO DE ACCIONES CONTABLES (AJAX/POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    // --- Obtener historial individual del trabajador (AJAX) ---
    if ($action === 'obtener_historial_trabajador') {
        $id_trab = intval($_POST['trabajador_id'] ?? 0);
        if ($id_trab <= 0) {
            $response['message'] = 'ID de trabajador inválido.';
        } else {
            try {
                $stmt_h = $pdo->prepare("
                    SELECT sv.*, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, a.nombre_area, cp.nombre_cargo as cargo
                    FROM submayor_vacaciones sv
                    JOIN trabajadores t ON sv.trabajador_id = t.id
                    LEFT JOIN areas a ON t.area_id = a.id
                    LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
                    WHERE sv.trabajador_id = ?
                    ORDER BY sv.fecha_movimiento ASC, sv.periodo_desde ASC
                ");
                $stmt_h->execute([$id_trab]);
                $movs = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt_saldo = $pdo->prepare("SELECT vacaciones_acumuladas FROM trabajadores WHERE id = ?");
                $stmt_saldo->execute([$id_trab]);
                $saldo_act = $stmt_saldo->fetchColumn() ?: 0;
                
                $response['success'] = true;
                $response['data'] = $movs;
                $response['saldo_actual'] = floatval($saldo_act);
            } catch (Exception $e) {
                $response['message'] = 'Error al consultar historial: ' . $e->getMessage();
            }
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($action === 'registrar_movimiento') {
        if (!$puede_crear_submayor) {
            $response['success'] = false;
            $response['denied'] = true;
            $response['message'] = 'No tiene permisos suficientes para realizar esta operación. Contacte al administrador del sistema.';
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        $trabajador_id_post = $_POST['trabajador_id'] ?? '';
        $tipo_mov = $_POST['tipo_movimiento'] ?? '';
        $dias_post = parseFloat($_POST['dias'] ?? 0);
        $importe_post = parseFloat($_POST['importe'] ?? 0);
        $fecha_mov = $_POST['fecha_movimiento'] ?? date('Y-m-d H:i:s');
        $periodo_d = $_POST['periodo_desde'] ?? date('Y-m-01');
        $periodo_h = $_POST['periodo_hasta'] ?? date('Y-m-t');
        $ref = $_POST['referencia'] ?? '';
        $obs = $_POST['observaciones'] ?? '';

        $tipos_validos = ['acumulacion', 'disfrute', 'ajuste'];

        if (empty($trabajador_id_post) || empty($tipo_mov)) {
            $response['message'] = 'Error: Los datos requeridos están incompletos o son inválidos.';
        } elseif (!in_array($tipo_mov, $tipos_validos, true)) {
            $response['message'] = 'Error: Tipo de movimiento inválido. Debe ser acumulacion, disfrute o ajuste.';
        } elseif (!empty($periodo_d) && !empty($periodo_h) && $periodo_h < $periodo_d) {
            $response['message'] = 'Error: La fecha "Período Hasta" no puede ser anterior a "Período Desde".';
        } elseif (($tipo_mov === 'acumulacion' || $tipo_mov === 'disfrute') && $dias_post <= 0) {
            $response['message'] = 'Error: Para acumulación o disfrute los días deben ser positivos.';
        } elseif ($tipo_mov === 'ajuste' && $dias_post == 0) {
            $response['message'] = 'Error: Un ajuste no puede registrar 0 días.';
        } elseif ($tipo_mov === 'disfrute' && $dias_post > 0) {
            // Validar que el disfrute no supere el saldo acumulado disponible
            try {
                $stmt_saldo_disp = $pdo->prepare("SELECT COALESCE(vacaciones_acumuladas, 0) FROM trabajadores WHERE id = ?");
                $stmt_saldo_disp->execute([$trabajador_id_post]);
                $saldo_disponible = floatval($stmt_saldo_disp->fetchColumn());
                if ($dias_post > $saldo_disponible + 0.005) {
                    $response['message'] = 'Saldo insuficiente: el trabajador dispone de ' . number_format($saldo_disponible, 2) . ' días y solicita disfrutar ' . number_format($dias_post, 2) . '.';
                } else {
                    $response = registrarMovimientoVacacionesValidado($pdo, $trabajador_id_post, $periodo_d, $periodo_h, $tipo_mov, $dias_post, $importe_post, $fecha_mov, $ref, $obs, $user_nombre_completo);
                }
            } catch (Exception $e) {
                $response['message'] = 'Error en transacción: ' . $e->getMessage();
            }
        } else {
            try {
                $response = registrarMovimientoVacacionesValidado($pdo, $trabajador_id_post, $periodo_d, $periodo_h, $tipo_mov, $dias_post, $importe_post, $fecha_mov, $ref, $obs, $user_nombre_completo);
            } catch (Exception $e) {
                $response['message'] = 'Error en transacción: ' . $e->getMessage();
            }
        }
    }

    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// -----------------------------------------------------------------------------
// PROCESAMIENTO DE REPORTE VERSAT (MINCOM) - CON IMPORTE Y SUBTOTALES POR GRUPO
// -----------------------------------------------------------------------------
if (isset($_GET['versat_report']) && $_GET['versat_report'] == '1') {
    // Obtener filtros del reporte
    $versat_agrupar = $_GET['versat_agrupar'] ?? 'area';
    $versat_dias_min = $_GET['versat_dias_min'] ?? '';
    $versat_dias_max = $_GET['versat_dias_max'] ?? '';
    $versat_tipo_contrato = $_GET['versat_tipo_contrato'] ?? '';
    $versat_anio = $_GET['versat_anio'] ?? '';
    $versat_mes = $_GET['versat_mes'] ?? '';
    $versat_ocultar_cero = $_GET['versat_ocultar_cero'] ?? '';
    $versat_solo_bajas = $_GET['versat_solo_bajas'] ?? '';
    
    // Obtener días laborables mensuales desde configuración para cálculo del importe
    $stmt_dias_versat = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'");
    $dias_mensuales_versat = $stmt_dias_versat->fetchColumn();
    $dias_laborables_versat = $dias_mensuales_versat ? (int)$dias_mensuales_versat : 24;
    
    // Período del reporte: año/mes seleccionados o todo el histórico del submayor
    if ($versat_anio !== '' && $versat_mes !== '') {
        $versat_desde = "$versat_anio-$versat_mes-01";
        $versat_hasta = date('Y-m-t', strtotime($versat_desde));
    } elseif ($versat_anio !== '') {
        $versat_desde = "$versat_anio-01-01";
        $versat_hasta = "$versat_anio-12-31";
    } else {
        $versat_desde = '1970-01-01';
        $versat_hasta = '9999-12-31';
    }
    
    // Construir consulta base para el reporte (saldo real calculado desde el submayor)
    $sql_versat = "
        SELECT 
            t.id,
            t.codigo,
            t.nombre_completo,
            t.ci,
            t.tipo_contrato,
            a.id as area_id,
            a.nombre_area as area,
            a.codigo as codigo_area,
            cc.id as cc_id,
            cc.codigo as cc_codigo,
            cc.nombre as centro_costo,
            cp.nombre_cargo as cargo,
            e.salario_mensual,
            COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias
                              WHEN sv.tipo_movimiento = 'disfrute' THEN -sv.dias
                              ELSE sv.dias END), 0) as saldo_periodo,
            COALESCE((SELECT SUM(CASE WHEN s.tipo_movimiento = 'acumulacion' THEN s.dias
                                      WHEN s.tipo_movimiento = 'disfrute' THEN -s.dias
                                      ELSE 0 END)
                      FROM submayor_vacaciones s
                      WHERE s.trabajador_id = t.id
                        AND s.tipo_movimiento IN ('acumulacion', 'disfrute')
                        AND s.periodo_hasta < :desde_ini), 0) as saldo_inicial
        FROM trabajadores t
        LEFT JOIN submayor_vacaciones sv ON t.id = sv.trabajador_id
            AND (sv.tipo_movimiento = 'ajuste' OR (sv.periodo_desde >= :desde AND sv.periodo_hasta <= :hasta))
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
        LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        WHERE 1=1
    ";
    
    $params_versat = [
        ':desde' => $versat_desde,
        ':hasta' => $versat_hasta,
        ':desde_ini' => $versat_desde,
    ];
    
    // Aplicar filtro de tipo de contrato
    if ($versat_tipo_contrato !== '') {
        $sql_versat .= " AND t.tipo_contrato = :tipo_contrato";
        $params_versat[':tipo_contrato'] = $versat_tipo_contrato;
    }

    // Aplicar filtro de solo trabajadores de baja
    if ($versat_solo_bajas === '1') {
        $sql_versat .= " AND t.activo = 0";
    }
    
    // Agrupación necesaria para sumar el saldo del período por trabajador
    $sql_versat .= " GROUP BY t.id, t.codigo, t.nombre_completo, t.ci, t.tipo_contrato, a.id, a.nombre_area, a.codigo, cc.id, cc.codigo, cc.nombre, cp.nombre_cargo, e.salario_mensual";
    
    // Agregar ORDER BY según agrupación
    switch ($versat_agrupar) {
        case 'area':
            $sql_versat .= " ORDER BY a.nombre_area, t.nombre_completo";
            break;
        case 'centro_costo':
            $sql_versat .= " ORDER BY cc.nombre, t.nombre_completo";
            break;
        case 'contrato':
            $sql_versat .= " ORDER BY t.tipo_contrato, t.nombre_completo";
            break;
        default:
            $sql_versat .= " ORDER BY t.nombre_completo";
    }
    
    $stmt_versat = $pdo->prepare($sql_versat);
    $stmt_versat->execute($params_versat);
    $reporte_data = $stmt_versat->fetchAll(PDO::FETCH_ASSOC);
    
    // Función para calcular importe de vacaciones
    function calcularImporteVacaciones($dias, $salario_mensual, $dias_laborables) {
        if ($salario_mensual <= 0) return 0;
        $valor_dia = $salario_mensual / $dias_laborables;
        return $dias * $valor_dia;
    }
    
    // Calcular saldo real del submayor y aplicar filtros de días (excluye saldo en cero)
    foreach ($reporte_data as &$row) {
        $row['saldo_dias'] = round(floatval($row['saldo_inicial']) + floatval($row['saldo_periodo']), 2);
        $row['importe'] = calcularImporteVacaciones(
            $row['saldo_dias'], 
            $row['salario_mensual'] ?? 0, 
            $dias_laborables_versat
        );
    }
    unset($row);
    
    $reporte_data = array_values(array_filter($reporte_data, function ($row) use ($versat_dias_min, $versat_dias_max, $versat_ocultar_cero) {
        $dias = $row['saldo_dias'];
        if ($versat_dias_min !== '' && $dias < floatval($versat_dias_min)) return false;
        if ($versat_dias_max !== '' && $dias > floatval($versat_dias_max)) return false;
        // Opción de filtro: ocultar trabajadores con saldo en cero
        if ($versat_ocultar_cero === '1' && $dias <= 0) return false;
        return true;
    }));
    
    // Organizar datos por grupos según la agrupación seleccionada
    $grupos = [];
    $total_general_dias = 0;
    $total_general_importe = 0;
    $total_general_trabajadores = 0;
    
    foreach ($reporte_data as $row) {
        // Determinar la clave del grupo
        switch ($versat_agrupar) {
            case 'area':
                $grupo_id = $row['area_id'] ?? 0;
                $grupo_nombre = $row['area'] ?? 'Sin Área';
                $grupo_codigo = $row['codigo_area'] ?? '';
                $grupo_tipo = 'area';
                break;
            case 'centro_costo':
                $grupo_id = $row['cc_id'] ?? 0;
                $grupo_nombre = $row['centro_costo'] ?? 'Sin Centro de Costo';
                $grupo_codigo = $row['cc_codigo'] ?? '';
                $grupo_tipo = 'centro_costo';
                break;
            case 'contrato':
                $grupo_id = $row['tipo_contrato'] ?? 'sin_contrato';
                $grupo_nombre = $row['tipo_contrato'] ?? 'Sin Tipo de Contrato';
                $grupo_codigo = '';
                $grupo_tipo = 'contrato';
                break;
            default:
                $grupo_id = 'todos';
                $grupo_nombre = 'Todos los Trabajadores';
                $grupo_codigo = '';
                $grupo_tipo = 'general';
        }
        
        if (!isset($grupos[$grupo_id])) {
            $grupos[$grupo_id] = [
                'nombre' => $grupo_nombre,
                'codigo' => $grupo_codigo,
                'tipo' => $grupo_tipo,
                'trabajadores' => [],
                'total_dias' => 0,
                'total_importe' => 0,
                'total_trabajadores' => 0
            ];
        }
        
        $grupos[$grupo_id]['trabajadores'][] = $row;
        $grupos[$grupo_id]['total_dias'] += $row['saldo_dias'];
        $grupos[$grupo_id]['total_importe'] += $row['importe'];
        $grupos[$grupo_id]['total_trabajadores']++;
        
        $total_general_dias += $row['saldo_dias'];
        $total_general_importe += $row['importe'];
        $total_general_trabajadores++;
    }
    
    // Mostrar el reporte en formato HTML
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte Vacaciones Acumuladas - <?php echo strtoupper($versat_agrupar); ?><?php if ($versat_anio !== ''): ?> - <?php echo htmlspecialchars($versat_anio); ?><?php if ($versat_mes !== ''): ?> - <?php echo htmlspecialchars(nombreMesEspanol($versat_mes)); endif; endif; ?></title>
        <style>
            * { margin:0; padding:0; box-sizing: border-box; }
            @page { size: Letter landscape; margin:0; }
            body { 
                font-family: 'Arial', 'Helvetica', sans-serif; 
                background: white; 
                color: black; 
                padding:0;
            }
            .page-sheet {
                width:279.4mm;
                height:215.9mm;
                padding:12mm 10mm 20mm 10mm;
                position: relative;
                background: white;
                page-break-after: always;
            }
            .page-sheet.last {
                page-break-after: auto;
            }
            .report-header {
                display: flex;
                align-items: center;
                justify-content: center;
                gap:1.5rem;
                flex-wrap: wrap;
                text-align: center;
                margin-bottom:1.5625rem;
                border-bottom: 0.1875rem solid #004B87;
                padding-bottom:1.125rem;
            }
            .report-header .logo-wrap {
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .report-header .logo-wrap img {
                max-height:4.5rem;
                max-width:10.625rem;
                object-fit: contain;
            }
            .report-header h1 {
                color: #004B87;
                font-size:18pt;
                margin:0 0 0.3125rem 0;
            }
            .report-header h2 {
                font-size:14pt;
                margin:0 0 0.3125rem 0;
            }
            .report-header p {
                font-size:10pt;
                color: #555;
                margin:0;
            }
            .filtros-aplicados {
                background: #f5f5f5;
                padding:0.625rem;
                margin-bottom:1.25rem;
                font-size:9pt;
                border-left: 0.25rem solid #004B87;
            }
            .grupo-container {
                margin-bottom:1.875rem;
                page-break-inside: avoid;
            }
            .grupo-header {
                background-color: #e8e8e8;
                padding:0.625rem;
                margin-top:1.25rem;
                margin-bottom:0.625rem;
                border-left: 0.25rem solid #004B87;
                font-weight: bold;
            }
            .grupo-header h3 {
                font-size:11pt;
                color: #004B87;
            }
            table {
                width:100%;
                border-collapse: collapse;
                font-size:9pt;
            }
            th, td {
                border: 0.0625rem solid #ccc;
                padding:0.5rem;
                text-align: left;
            }
            th {
                background-color: #e8e8e8;
                color: #000000;
                text-align: center;
                font-weight: bold;
            }
            .subtotal-row {
                background-color: #e8e8e8;
                font-weight: bold;
            }
            .total-general-row {
                background-color: #004B87;
                color: white;
                font-weight: bold;
            }
            .total-general-row td {
                color: white;
            }
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .text-left {
                text-align: left;
            }
            .footer {
                margin-top:2.5rem;
                text-align: center;
                font-size:8pt;
                border-top: 0.0625rem solid #ccc;
                padding-top:0.9375rem;
            }
            /* Forzar texto negro en todas las celdas */
            table, table td, table th, table tr, tbody td, thead th, tfoot td {
                color: #000000 !important;
            }
            .total-general-row td {
                color: #ffffff !important;
            }
            td, th, .text-right, .text-center, .text-left {
                color: #000000 !important;
            }
            .grupo-header h3 {
                color: #004B87 !important;
            }
            .subtotal-row td {
                color: #000000 !important;
                font-weight: bold;
            }
            @media print {
                body { padding:0; }
                .no-print { display: none; }
                .page-sheet { height:100vh; }
            }
            #auto-hide-toolbar { transition: transform 0.3s ease; }
            #auto-hide-toolbar.hidden { transform: translateY(-100%); }
            .print-footer {
                position: absolute;
                bottom:10mm;
                left:10mm;
                right:10mm;
                border-top: 0.0625rem solid #bbb;
                padding-top:0.3125rem;
                font-size:7.5pt;
                color: #555;
                display: flex;
                justify-content: space-between;
            }
            .report-header, .filtros-aplicados, .grupo-header {
                page-break-inside: avoid;
            }
        </style>
    </head>
    <body>
        <div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">
            <span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>
            <button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;">🖨️ Imprimir</button>
            <button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);transition:all 0.2s;">✖ Cerrar</button>
            
        </div>
        
    <?php
    // ======================================================================
    // PAGINACIÓN DEL REPORTE (patrón .page-sheet, igual que reportes.php)
    // Hoja Letter horizontal (279.4 x 215.9 mm) con margen @page = 0; cada
    // .page-sheet lleva el padding y un pie absoluto con "Página N de M".
    // Las tablas se parten fila a fila; la cabecera de tabla se repite en
    // cada hoja y el subtotal solo aparece en la hoja final del grupo.
    // Alturas estimadas en mm: fila/cabecera ≈ 8.6 mm (9pt + padding 0.5rem).
    // ======================================================================
    define('VERSAT_BUDGET', 172); // alto útil de contenido por hoja (mm)
    define('VERSAT_HDR', 32);     // cabecera (logo + títulos)
    define('VERSAT_FILT', 34);    // caja de parámetros
    define('VERSAT_GH', 18);      // encabezado de grupo
    define('VERSAT_THEAD', 8.6);  // fila de cabecera de tabla
    define('VERSAT_ROW', 8.6);    // fila de datos
    define('VERSAT_FOOT', 8.6);   // fila de subtotal
    define('VERSAT_TOT', 15);     // total general
    define('VERSAT_FOOTR', 23);   // pie del documento
    define('VERSAT_SIG', 25);     // firmas

    function versat_grupo_header($grupo, $versat_agrupar) {
        $html = '<div class="grupo-header"><h3>';
        switch ($versat_agrupar) {
            case 'area':
                $html .= '📁 Área: ' . htmlspecialchars($grupo['nombre']);
                if (!empty($grupo['codigo'])) $html .= ' <span style="font-weight: normal;">(Código: ' . htmlspecialchars($grupo['codigo']) . ')</span>';
                break;
            case 'centro_costo':
                $html .= '💰 Centro de Costo: ' . htmlspecialchars($grupo['nombre']);
                if (!empty($grupo['codigo'])) $html .= ' <span style="font-weight: normal;">(Código: ' . htmlspecialchars($grupo['codigo']) . ')</span>';
                break;
            case 'contrato':
                $html .= '📄 Tipo de Contrato: ' . htmlspecialchars($grupo['nombre']);
                break;
            default:
                $html .= '📊 ' . htmlspecialchars($grupo['nombre']);
        }
        $html .= '</h3></div>';
        return $html;
    }

    function versat_table_part($grupo, $start, $take, $isLast) {
        $html = '<table><thead><tr>';
        $html .= '<th>#</th><th>Código</th><th class="text-left">Trabajador</th><th>CI</th><th class="text-left">Cargo</th><th>Días Acumulados</th><th>Importe (CUP)</th>';
        $html .= '</tr></thead><tbody>';
        $filas = array_slice($grupo['trabajadores'], $start, $take);
        $nro = $start + 1;
        foreach ($filas as $trabajador) {
            $html .= '<tr>';
            $html .= '<td class="text-center">' . $nro++ . '</td>';
            $html .= '<td class="text-center">' . htmlspecialchars($trabajador['codigo']) . '</td>';
            $html .= '<td class="text-left">' . htmlspecialchars($trabajador['nombre_completo']) . '</td>';
            $html .= '<td class="text-center">' . htmlspecialchars($trabajador['ci']) . '</td>';
            $html .= '<td class="text-left">' . htmlspecialchars($trabajador['cargo'] ?? '-') . '</td>';
            $html .= '<td class="text-center">' . number_format($trabajador['saldo_dias'], 2) . ' días</td>';
            $html .= '<td class="text-center">$' . number_format($trabajador['importe'], 2, '.', ',') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        if ($isLast) {
            $html .= '<tfoot><tr class="subtotal-row">';
            $html .= '<td colspan="5" class="text-center"><strong>TOTAL DEL GRUPO:</strong> (' . $grupo['total_trabajadores'] . ' trabajadores)</td>';
            $html .= '<td class="text-center"><strong>' . number_format($grupo['total_dias'], 2) . ' días</strong></td>';
            $html .= '<td class="text-center"><strong>$' . number_format($grupo['total_importe'], 2, '.', ',') . '</strong></td>';
            $html .= '</tr></tfoot>';
        }
        $html .= '</table>';
        return $html;
    }

    function versat_add(&$frag, &$used, $html, $h) { $frag[] = $html; $used += $h; }
    function versat_flush(&$sheets, &$frag, &$used) { $sheets[] = $frag; $frag = []; $used = 0; }
    function versat_next(&$sheets, &$frag, &$used, $header_html) {
        versat_flush($sheets, $frag, $used);
        versat_add($frag, $used, $header_html, VERSAT_HDR);
    }

    $header_html = '<div class="report-header">';
    if (!empty($logo_base64_global)) {
        $header_html .= '<div class="logo-wrap"><img src="' . $logo_base64_global . '" alt="Logo"></div>';
    }
    $header_html .= '<div>';
    $header_html .= '<h1>' . htmlspecialchars($config_empresa['nombre_empresa']) . '</h1>';
    $header_html .= '<h2>REPORTE DE VACACIONES ACUMULADAS</h2>';
    $header_html .= '<p>Fecha de emisión: ' . date('d/m/Y h:i:s A') . '</p>';
    $header_html .= '</div></div>';

    $filtros_html = '<div class="filtros-aplicados">';
    $filtros_html .= '<strong>📊 Parámetros del reporte:</strong><br>';
    $filtros_html .= '• Agrupado por: ';
    switch ($versat_agrupar) {
        case 'area': $filtros_html .= 'Área / Departamento'; break;
        case 'centro_costo': $filtros_html .= 'Centro de Costo'; break;
        case 'contrato': $filtros_html .= 'Tipo de Contrato'; break;
        default: $filtros_html .= 'Sin agrupación';
    }
    $filtros_html .= '<br>• Período: ';
    if ($versat_anio !== '' && $versat_mes !== '') {
        $filtros_html .= htmlspecialchars(nombreMesEspanol($versat_mes) . ' de ' . $versat_anio);
    } elseif ($versat_anio !== '') {
        $filtros_html .= 'Año ' . htmlspecialchars($versat_anio);
    } else {
        $filtros_html .= 'Todos (histórico del submayor)';
    }
    $filtros_html .= '<br>• Rango de días: ';
    $min_texto = ($versat_dias_min !== '') ? "≥ {$versat_dias_min} días" : 'sin mínimo';
    $max_texto = ($versat_dias_max !== '') ? "≤ {$versat_dias_max} días" : 'sin máximo';
    $filtros_html .= $min_texto . ' | ' . $max_texto;
    $filtros_html .= '<br>• Tipo de contrato: ' . ($versat_tipo_contrato !== '' ? htmlspecialchars($versat_tipo_contrato) : 'Todos');
    $filtros_html .= '<br>• Base de cálculo: ' . $dias_laborables_versat . ' días laborables por mes (9.09%)';
    $filtros_html .= '</div>';

    // Hoja 1: cabecera + parámetros
    versat_add($frag, $used, $header_html, VERSAT_HDR);
    versat_add($frag, $used, $filtros_html, VERSAT_FILT);

    if (empty($grupos)) {
        versat_add($frag, $used, '<div style="text-align: center; padding:3.125rem; color: #999;"><h3>No se encontraron trabajadores con vacaciones acumuladas</h3><p>No hay datos que coincidan con los filtros seleccionados.</p></div>', 80);
    } else {
        foreach ($grupos as $grupo) {
            if ($used + VERSAT_GH > VERSAT_BUDGET) versat_next($sheets, $frag, $used, $header_html);
            versat_add($frag, $used, versat_grupo_header($grupo, $versat_agrupar), VERSAT_GH);

            $n = count($grupo['trabajadores']);
            $start = 0;
            while ($start < $n) {
                $avail = VERSAT_BUDGET - $used;
                $take = 0;
                $sum = VERSAT_THEAD;
                while ($start + $take < $n) {
                    $needFoot = ($start + $take + 1 === $n) ? VERSAT_FOOT : 0;
                    if ($sum + VERSAT_ROW + $needFoot > $avail) break;
                    $sum += VERSAT_ROW;
                    $take++;
                }
                if ($take === 0) {
                    if (count($frag) === 0) { $take = 1; $sum = VERSAT_THEAD + VERSAT_ROW; }
                    else { versat_next($sheets, $frag, $used, $header_html); continue; }
                }
                $isLast = ($start + $take >= $n);
                if ($isLast) $sum += VERSAT_FOOT;
                versat_add($frag, $used, versat_table_part($grupo, $start, $take, $isLast), $sum);
                $start += $take;
                if ($start < $n && $used + VERSAT_THEAD + VERSAT_ROW > VERSAT_BUDGET) {
                    versat_next($sheets, $frag, $used, $header_html);
                }
            }
        }

        // Total general + pie + firmas
        if ($used + VERSAT_TOT + VERSAT_FOOTR + VERSAT_SIG > VERSAT_BUDGET) versat_next($sheets, $frag, $used, $header_html);
        $total_html = '<div class="grupo-container"><table style="margin-top:1.25rem;"><tfoot><tr class="total-general-row">';
        $total_html .= '<td colspan="5" class="text-center"><strong>TOTAL GENERAL DEL REPORTE:</strong> (' . $total_general_trabajadores . ' trabajadores)</td>';
        $total_html .= '<td class="text-center"><strong>' . number_format($total_general_dias, 2) . ' días</strong></td>';
        $total_html .= '<td class="text-center"><strong>$' . number_format($total_general_importe, 2, '.', ',') . '</strong></td>';
        $total_html .= '</tr></tfoot></table></div>';
        versat_add($frag, $used, $total_html, VERSAT_TOT);

        $footer_html = '<div class="footer">';
        $footer_html .= '<div>Documento generado por: ' . htmlspecialchars($user_nombre_completo) . ' | Fecha de impresión: ' . date('d/m/Y h:i:s A') . '</div>';
        $footer_html .= '<div>Sistema de Gestión de Nóminas - ' . htmlspecialchars($config_empresa['nombre_empresa']) . '</div>';
        $footer_html .= '</div>';
        versat_add($frag, $used, $footer_html, VERSAT_FOOTR);

        $firmas_html = '<table style="width:100%; margin-top:3.125rem; border: none;"><tr>';
        $firmas_html .= '<td style="width:33%; text-align: center; border: none; padding-top:2.5rem; vertical-align: bottom;">';
        $firmas_html .= '<div style="border-top: 0.0625rem solid #000000; width:85%; margin:0 auto 0.3125rem auto;"></div>';
        $firmas_html .= '<strong>Elaborado por:</strong><br>' . htmlspecialchars(!empty($config_empresa['especialista_gestionRRHH']) ? $config_empresa['especialista_gestionRRHH'] : $user_nombre_completo) . '<br>';
        $firmas_html .= '<span style="font-size:7pt;">(Especialista de Recursos Humanos)</span></td>';
        $firmas_html .= '<td style="width:33%; text-align: center; border: none; padding-top:2.5rem; vertical-align: bottom;">';
        $firmas_html .= '<div style="border-top: 0.0625rem solid #000000; width:85%; margin:0 auto 0.3125rem auto;"></div>';
        $firmas_html .= '<strong>Revisado por:</strong><br>' . htmlspecialchars($config_empresa['especialista_gestion']) . '<br>';
        $firmas_html .= '<span style="font-size:7pt;">(Especialista de Gestión)</span></td>';
        $firmas_html .= '<td style="width:33%; text-align: center; border: none; padding-top:2.5rem; vertical-align: bottom;">';
        $firmas_html .= '<div style="border-top: 0.0625rem solid #000000; width:85%; margin:0 auto 0.3125rem auto;"></div>';
        $firmas_html .= '<strong>Aprobado por:</strong><br>' . htmlspecialchars($config_empresa['jefe_proyecto']) . '<br>';
        $firmas_html .= '<span style="font-size:7pt;">(Jefe de Proyecto)</span></td>';
        $firmas_html .= '</tr></table>';
        versat_add($frag, $used, $firmas_html, VERSAT_SIG);
    }
    versat_flush($sheets, $frag, $used);

    $total_paginas = count($sheets);
    foreach ($sheets as $idx => $hoja) {
        $clase_hoja = ($idx === $total_paginas - 1) ? ' last' : '';
        echo '<div class="page-sheet' . $clase_hoja . '">';
        foreach ($hoja as $h) echo $h;
        echo '<div class="print-footer">';
        echo '<div>Sistema de Gestión de Nóminas - ' . htmlspecialchars($config_empresa['nombre_empresa']) . '</div>';
        echo '<div>Página ' . ($idx + 1) . ' de ' . $total_paginas . '</div>';
        echo '</div>';
        echo '</div>';
    }
    ?>
    <script>
    (function(){
        var tb=document.getElementById('auto-hide-toolbar');
        if(!tb)return;
        var lastY=window.scrollY||window.pageYOffset,ticking=false;
        window.addEventListener('scroll',function(){
            if(!ticking){
                window.requestAnimationFrame(function(){
                    var curY=window.scrollY||window.pageYOffset;
                    if(curY>lastY&&curY>60)tb.classList.add('hidden');
                    else tb.classList.remove('hidden');
                    lastY=curY;ticking=false;
                });
                ticking=true;
            }
        });
    })();
    </script>
    </body>
    </html>
    <?php
    exit();
}

// -----------------------------------------------------------------------------
// GESTIÓN Y NORMALIZACIÓN DE FILTROS TEMPORALES (AÑO, MES Y FECHAS)
// -----------------------------------------------------------------------------
$consulta_anio = $_GET['consulta_anio'] ?? '';
$consulta_mes = $_GET['consulta_mes'] ?? '';
$periodo_desde = $_GET['periodo_desde'] ?? '';
$periodo_hasta = $_GET['periodo_hasta'] ?? '';

// Si se define Año y Mes específico
if ($consulta_anio != '' && $consulta_mes != '') {
    $periodo_desde = "$consulta_anio-$consulta_mes-01";
    $periodo_hasta = date('Y-m-t', strtotime($periodo_desde));
} 
// Si se selecciona solo un Año completo
elseif ($consulta_anio != '' && $consulta_mes == '') {
    $periodo_desde = "$consulta_anio-01-01";
    $periodo_hasta = "$consulta_anio-12-31";
} 
// Si no hay valores temporales definidos, inicializar por defecto en el año en curso
if (empty($periodo_desde) || empty($periodo_hasta)) {
    $periodo_desde = date('Y-01-01');
    $periodo_hasta = date('Y-12-31');
}

$trabajador_id = $_GET['trabajador_id'] ?? '';
$tipo_movimiento = $_GET['tipo_movimiento'] ?? '';
$area_id = $_GET['area_id'] ?? '';
$centro_costo_id = $_GET['centro_costo_id'] ?? '';
$ocultar_saldo_cero = $_GET['ocultar_saldo_cero'] ?? '';
$solo_bajas = $_GET['solo_bajas'] ?? '';

// Obtener áreas y centros de costo para los filtros
$areas = $pdo->query("SELECT id, nombre_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);
$centros_costo = $pdo->query("SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);

// Obtener trabajadores para filtros, modales y cálculo del lado del cliente
$stmt = $pdo->query("
    SELECT t.id, t.codigo, t.nombre_completo, t.ci, t.activo, t.vacaciones_acumuladas,
           e.salario_mensual, e.salario_hora_ordinaria, a.nombre_area, cp.nombre_cargo
    FROM trabajadores t
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    LEFT JOIN areas a ON t.area_id = a.id
    LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
    ORDER BY t.nombre_completo
");
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$trabajadores_map_js = [];
foreach ($trabajadores as $t) {
    $trabajadores_map_js[$t['id']] = [
        'codigo'              => $t['codigo'],
        'nombre_completo'     => $t['nombre_completo'],
        'ci'                  => $t['ci'],
        'activo'              => (int)($t['activo'] ?? 0),
        'salario_mensual'     => floatval($t['salario_mensual'] ?? 0),
        'salario_hora'        => floatval($t['salario_hora_ordinaria'] ?? 0),
        'saldo_acumulado'     => floatval($t['vacaciones_acumuladas'] ?? 0),
        'nombre_area'         => $t['nombre_area'] ?? '',
        'nombre_cargo'        => $t['nombre_cargo'] ?? ''
    ];
}

// Obtener movimientos filtrados
$sql = "SELECT sv.*, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, a.nombre_area, cp.nombre_cargo as cargo
        FROM submayor_vacaciones sv
        JOIN trabajadores t ON sv.trabajador_id = t.id
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
        WHERE 1=1";
$params = [];

if ($trabajador_id) {
    $sql .= " AND sv.trabajador_id = ?";
    $params[] = $trabajador_id;
}
if ($periodo_desde) {
    $sql .= " AND sv.periodo_desde >= ?";
    $params[] = $periodo_desde;
}
if ($periodo_hasta) {
    $sql .= " AND sv.periodo_hasta <= ?";
    $params[] = $periodo_hasta;
}
if ($tipo_movimiento && $tipo_movimiento != '') {
    $sql .= " AND sv.tipo_movimiento = ?";
    $params[] = $tipo_movimiento;
}
if ($area_id) {
    $sql .= " AND t.area_id = ?";
    $params[] = $area_id;
}
if ($centro_costo_id) {
    $sql .= " AND t.centro_costo_id = ?";
    $params[] = $centro_costo_id;
}

$sql .= " ORDER BY sv.fecha_movimiento DESC, sv.periodo_desde DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular saldos corrientes reales por trabajador aplicando filtros dinámicos temporales
$sql_saldos = "
    SELECT t.id, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, t.activo, a.nombre_area, cp.nombre_cargo as cargo,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias ELSE 0 END), 0) as total_acumulado,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.importe ELSE 0 END), 0) as importe_acumulado,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'disfrute' THEN sv.dias ELSE 0 END), 0) as total_disfrutado,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'disfrute' THEN sv.importe ELSE 0 END), 0) as importe_disfrutado,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'ajuste' THEN sv.dias ELSE 0 END), 0) as total_ajuste_dias,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'ajuste' THEN sv.importe ELSE 0 END), 0) as total_ajuste_importe,
        COALESCE(SUM(CASE WHEN sv.tipo_movimiento = 'acumulacion' THEN sv.dias
                          WHEN sv.tipo_movimiento = 'disfrute' THEN -sv.dias
                          ELSE sv.dias END), 0) as saldo_periodo,
        COALESCE((SELECT SUM(CASE WHEN s.tipo_movimiento = 'acumulacion' THEN s.dias
                                  WHEN s.tipo_movimiento = 'disfrute' THEN -s.dias
                                  ELSE 0 END)
                  FROM submayor_vacaciones s
                  WHERE s.trabajador_id = t.id
                    AND s.tipo_movimiento IN ('acumulacion', 'disfrute')
                    AND s.periodo_hasta < :desde_ini), 0) as saldo_inicial
    FROM trabajadores t
    LEFT JOIN submayor_vacaciones sv ON t.id = sv.trabajador_id
        AND (sv.tipo_movimiento = 'ajuste' OR (sv.periodo_desde >= :desde AND sv.periodo_hasta <= :hasta))
    LEFT JOIN areas a ON t.area_id = a.id
    LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id    WHERE 1=1";
$params_saldos = [
    ':desde' => $periodo_desde,
    ':hasta' => $periodo_hasta,
    ':desde_ini' => $periodo_desde
];

if ($area_id) {
    $sql_saldos .= " AND t.area_id = :area_id";
    $params_saldos[':area_id'] = $area_id;
}
if ($centro_costo_id) {
    $sql_saldos .= " AND t.centro_costo_id = :cc_id";
    $params_saldos[':cc_id'] = $centro_costo_id;
}

$sql_saldos .= " GROUP BY t.id, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, a.nombre_area, cp.nombre_cargo, t.vacaciones_acumuladas ORDER BY t.nombre_completo";
$stmt_saldos = $pdo->prepare($sql_saldos);
$stmt_saldos->execute($params_saldos);
$saldos = $stmt_saldos->fetchAll(PDO::FETCH_ASSOC);

// Saldo al cierre del período = saldo inicial + movimientos del período (firma aplicada)
foreach ($saldos as &$s) {
    $s['saldo_cierre'] = round(floatval($s['saldo_inicial']) + floatval($s['saldo_periodo']), 2);
}
unset($s);

// Ocultar trabajadores con saldo en cero (o sin movimientos en el período) si el filtro lo indica
if ($ocultar_saldo_cero === '1') {
    $saldos = array_values(array_filter($saldos, function ($s) {
        return floatval($s['saldo_cierre']) != 0;
    }));
}

// Clasificar trabajadores: activos y bajas (por el campo activo)
$activos = array_filter($saldos, function ($s) { return (int)($s['activo'] ?? 0) === 1; });
$bajas = array_filter($saldos, function ($s) { return (int)($s['activo'] ?? 0) !== 1; });

// Trabajadores con saldo total en submayor > 20 días para marcarlos como vacaciones excedidas
$trabajadores_excedidos_ids = [];
try {
    $stmt_exc = $pdo->query("
        SELECT trabajador_id,
               COALESCE(SUM(CASE WHEN tipo_movimiento = 'disfrute' THEN -dias ELSE dias END), 0) as saldo_submayor
        FROM submayor_vacaciones
        GROUP BY trabajador_id
        HAVING saldo_submayor > 20
    ");
    foreach ($stmt_exc->fetchAll(PDO::FETCH_ASSOC) as $fila_exc) {
        $trabajadores_excedidos_ids[(int)$fila_exc['trabajador_id']] = (float)$fila_exc['saldo_submayor'];
    }
} catch (PDOException $e) {}

// Definir cadena descriptiva del período consultado para exportaciones
if (!empty($consulta_anio) && !empty($consulta_mes)) {
    $rango_string = nombreMesEspanol($consulta_mes) . " de " . $consulta_anio;
} elseif (!empty($consulta_anio)) {
    $rango_string = "Año " . $consulta_anio;
} else {
    $rango_string = "Desde " . date('d/m/Y', strtotime($periodo_desde)) . " hasta " . date('d/m/Y', strtotime($periodo_hasta));
}

// Obtener detalle individual del trabajador con cargo corregido
if ($trabajador_id) {
    $stmt_detalle = $pdo->prepare("
        SELECT sv.*, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, a.nombre_area, cp.nombre_cargo as cargo
        FROM submayor_vacaciones sv
        JOIN trabajadores t ON sv.trabajador_id = t.id
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
        WHERE sv.trabajador_id = ?
        ORDER BY sv.fecha_movimiento ASC, sv.periodo_desde ASC
    ");
    $stmt_detalle->execute([$trabajador_id]);
    $detalle_trabajador = $stmt_detalle->fetchAll();
    
    $stmt_resumen = $pdo->prepare("
        SELECT t.*, a.nombre_area, e.salario_mensual, e.escala_numero, cp.nombre_cargo as cargo
        FROM trabajadores t
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
        WHERE t.id = ?
    ");
    $stmt_resumen->execute([$trabajador_id]);
    $resumen_trabajador = $stmt_resumen->fetch();
}

// =========================================================
// CUADRE DEL SUBMAYOR DE VACACIONES (3 TABLAS)
//  - Pierna A (Saldo): submayor_vacaciones debe cuadrar con trabajadores.vacaciones_acumuladas
//  - Pierna B (Trazabilidad): toda nómina contabilizada con efecto de vacaciones (automática/ajuste
//    por acumulación, o tipo 'vacaciones' por disfrute) debe tener su movimiento en submayor_vacaciones,
//    enlazado por nomina_id y con los mismos días.
// Los trabajadores con no_acumular_vacaciones = 1 se excluyen: su acumulación se paga en efectivo y
// por diseño no genera movimientos en submayor ni modifica su saldo.
// =========================================================
$descuadres = [];
$excluidos_no_acumular = 0;
$cuadre_totales = ['submayor' => 0.0, 'trabajador' => 0.0, 'nominas' => 0.0];
try {
    $excluidos_no_acumular = (int)$pdo->query("SELECT COUNT(*) FROM trabajadores WHERE no_acumular_vacaciones = 1")->fetchColumn();

    // Saldo de cada trabajador según las tres fuentes
    $stmt_cuadre = $pdo->query("
        SELECT t.id, t.codigo, t.nombre_completo, t.activo, t.no_acumular_vacaciones,
               ROUND(t.vacaciones_acumuladas, 2) as acum_trab,
               ROUND(COALESCE((SELECT SUM(CASE WHEN s.tipo_movimiento = 'disfrute' THEN -s.dias ELSE s.dias END)
                               FROM submayor_vacaciones s WHERE s.trabajador_id = t.id), 0), 2) as saldo_submayor,
               ROUND(COALESCE((SELECT SUM(CASE WHEN n.tipo_nomina = 'vacaciones' THEN -n.dias_vacaciones_tomados
                                               WHEN n.tipo_nomina IN ('automatica','ajuste') THEN n.vacaciones_acumuladas_mes
                                               ELSE 0 END)
                               FROM nominas n WHERE n.trabajador_id = t.id AND n.estado = 'contabilizado'), 0), 2) as saldo_nominas
        FROM trabajadores t
    ");
    $saldos_cuadre = $stmt_cuadre->fetchAll(PDO::FETCH_ASSOC);

    // Nóminas contabilizadas sin su contrapartida en el submayor (o con días distintos)
    $stmt_trazabilidad = $pdo->query("
        SELECT n.trabajador_id,
               SUM(CASE WHEN n.tipo_nomina IN ('automatica','ajuste') AND n.vacaciones_acumuladas_mes > 0
                         AND (sm.id IS NULL OR ABS(COALESCE(sm.dias, 0) - n.vacaciones_acumuladas_mes) > 0.009)
                         THEN 1 ELSE 0 END) AS nomina_sin_acumulacion,
               SUM(CASE WHEN n.tipo_nomina = 'vacaciones' AND n.dias_vacaciones_tomados > 0
                         AND (sd.id IS NULL OR ABS(COALESCE(sd.dias, 0) - n.dias_vacaciones_tomados) > 0.009)
                         THEN 1 ELSE 0 END) AS nomina_sin_disfrute
        FROM nominas n
        JOIN trabajadores t ON t.id = n.trabajador_id AND t.no_acumular_vacaciones = 0
        LEFT JOIN submayor_vacaciones sm ON sm.nomina_id = n.id AND sm.tipo_movimiento = 'acumulacion'
        LEFT JOIN submayor_vacaciones sd ON sd.nomina_id = n.id AND sd.tipo_movimiento = 'disfrute'
        WHERE n.estado = 'contabilizado'
          AND n.tipo_nomina IN ('automatica','ajuste','vacaciones')
        GROUP BY n.trabajador_id
        HAVING nomina_sin_acumulacion > 0 OR nomina_sin_disfrute > 0
    ");
    $trazabilidad = [];
    foreach ($stmt_trazabilidad->fetchAll(PDO::FETCH_ASSOC) as $tr) {
        $trazabilidad[(int)$tr['trabajador_id']] = [
            'sin_acum' => (int)$tr['nomina_sin_acumulacion'],
            'sin_disfrute' => (int)$tr['nomina_sin_disfrute'],
        ];
    }

    foreach ($saldos_cuadre as $sc) {
        if ((int)$sc['no_acumular_vacaciones'] === 1) continue;
        $id = (int)$sc['id'];
        $motivos = [];

        // Pierna A: saldo contable vs acumulado en ficha
        $difiere_saldo = abs(floatval($sc['acum_trab']) - floatval($sc['saldo_submayor'])) > 0.009;
        if ($difiere_saldo) $motivos[] = 'saldo';

        // Pierna B: nóminas sin reflejo en submayor
        $lb = $trazabilidad[$id] ?? null;
        if ($lb) {
            if ($lb['sin_acum'] > 0) $motivos[] = 'nomina_acum:' . $lb['sin_acum'];
            if ($lb['sin_disfrute'] > 0) $motivos[] = 'nomina_disfrute:' . $lb['sin_disfrute'];
        }

        if (empty($motivos)) continue;

        $descuadres[] = [
            'id' => $id,
            'codigo' => $sc['codigo'],
            'nombre_completo' => $sc['nombre_completo'],
            'activo' => $sc['activo'],
            'saldo_submayor' => (float)$sc['saldo_submayor'],
            'acum_trab' => (float)$sc['acum_trab'],
            'saldo_nominas' => (float)$sc['saldo_nominas'],
            'motivos' => $motivos
        ];
    }
    usort($descuadres, function ($a, $b) {
        return strcmp($a['nombre_completo'], $b['nombre_completo']);
    });

    // Totales globales de las tres fuentes (solo trabajadores que acumulan)
    foreach ($saldos_cuadre as $sc) {
        if ((int)$sc['no_acumular_vacaciones'] === 1) continue;
        $cuadre_totales['submayor'] += floatval($sc['saldo_submayor']);
        $cuadre_totales['trabajador'] += floatval($sc['acum_trab']);
        $cuadre_totales['nominas'] += floatval($sc['saldo_nominas']);
    }
    $cuadre_totales['submayor'] = round($cuadre_totales['submayor'], 2);
    $cuadre_totales['trabajador'] = round($cuadre_totales['trabajador'], 2);
    $cuadre_totales['nominas'] = round($cuadre_totales['nominas'], 2);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Submayor de Vacaciones | <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Libraries CSS -->
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="../css/datatables/1.13.6/buttons.dataTables.min.css">
    
<style>
    * { margin:0; padding:0; box-sizing: border-box; }
    body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

    /* ===== MODO SOLO LECTURA (permisos por rol) ===== */
    /* Nota: el backup (#btnBackupManual) se permite a todos los roles autenticados */
    body.solo-lectura [data-accion-ajuste] { display: none !important; }

    /* Windows 11 Acrylic Background */
    .win11-bg {
        position: fixed; top:0; left:0; width:100%; height:100%; z-index: -2;
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
    }
    .win11-bg::before {
        content: ''; position: absolute; top:0; left:0; width:100%; height:100%;
        background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                          radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    /* Glassmorphism Effect */
    .glass-card {
        background: var(--panel-2); backdrop-filter: blur(0.625rem);
        border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }
    .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

    /* Sidebar Windows 11 Style */
    .win-sidebar {
        position: fixed; left:0; top:0; height:100vh; width:16.25rem;
        background: var(--panel); backdrop-filter: blur(1.875rem);
        border-right: 0.0625rem solid rgba(255, 255, 255, 0.08); z-index: 1000;
        transition: all 0.3s ease; display: flex; flex-direction: column;
    }
    .win-sidebar.collapsed { width:5rem; }
    .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
    .win-sidebar.collapsed .nav-item { justify-content: center; padding:0.75rem; }
    .win-sidebar.collapsed .nav-item i { margin:0; font-size:1.5rem; }

    .sidebar-logo { padding:1.5rem 1.25rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); margin-bottom:1.25rem; text-align: center; }
    .sidebar-logo h3 { font-size:1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin:0; }
    .sidebar-logo small { font-size:0.7rem; color: rgba(255, 255, 255, 0.5); }

    .sidebar-nav { flex: 1; padding:0 0.75rem; }
    .nav-item {
        display: flex; align-items: center; gap:0.875rem; padding:0.75rem 1rem;
        margin-bottom:0.375rem; border-radius: 0.75rem;
        color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
        cursor: pointer; text-decoration: none;
    }

    .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
    .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 0.1875rem solid #60a5fa; }
    .nav-item i { width:1.5rem; font-size:1.2rem; text-align: center; }
    .nav-item span { font-size:0.9rem; font-weight: 500; }

    /* Main Content */
    .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
    .main-container.expanded { margin-left:5rem; }

    /* Top Bar Windows 11 */
    .win-topbar {
        background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
        padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
        display: flex; justify-content: space-between; align-items: center;
        z-index: 100 !important; position: relative !important;
    }
    .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
    .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
    .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; color: white; }
    .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.6); margin:0.25rem 0 0; }

    /* User Menu & Dropdowns */
    .user-menu { display: flex; align-items: center; gap:1rem; }

    .user-avatar { width:2.5rem; height:2.5rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
    .user-avatar:hover { transform: scale(1.05); }

    .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
    .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
    .dropdown-menu-win {
        background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(1.25rem) !important;
        border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important; border-radius: 0.75rem !important;
        padding:0.5rem !important; box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
    }
    .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 0.5rem !important; padding:0.625rem 1rem !important; font-size:0.9rem !important; }
    .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2) !important; color: #ffffff !important; }
    .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
    .dropdown-menu-win .dropdown-divider { border-color: rgba(148, 163, 184, 0.35) !important; border-top-width: 0.0625rem !important; opacity: 1 !important; margin:0.5rem 0.25rem !important; }
    .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
    .dropdown-menu-win .dropdown-item small { font-size:0.65rem; color: rgba(255,255,255,0.6) !important; }
    .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }
    /* Divisores (hr) visibles según el tema oscuro */
    hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }

    /* Text visibility overrides */
    .text-muted { color: rgba(255, 255, 255, 0.65) !important; }

    .btn-win { background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1); padding:0.5rem 1rem; border-radius: 0.625rem; color: white; font-size:0.85rem; transition: all 0.2s; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; color: white; }
    .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; padding:0.625rem 1.25rem; border-radius: 0.625rem; color: white; transition: all 0.3s; }
    .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-0.125rem); color: white; }
    .btn-win-danger { background: linear-gradient(135deg, #dc3545, #c82333); border: none; padding:0.625rem 1.25rem; border-radius: 0.625rem; color: white; }
    .btn-win-warning { background: linear-gradient(135deg, #ffc107, #e0a800); border: none; padding:0.625rem 1.25rem; border-radius: 0.625rem; color: #000; font-weight: 600; }
    .btn-win-success { background: linear-gradient(135deg, var(--color-success), var(--color-success)); border: none; padding:0.625rem 1.25rem; border-radius: 0.625rem; color: white; }
    .btn-win-sm { background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1); padding:0.3125rem 0.625rem; border-radius: 0.375rem; color: white; font-size:0.7rem; transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; gap:0.3125rem; }
    .btn-win-sm:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; color: white; }

    /* Tarjetas de estadísticas (patrón común de la app) */
    .stat-card {
        background: linear-gradient(135deg, var(--panel-2), var(--panel));
        border-radius: 1rem; padding:1rem; border: 0.0625rem solid var(--border);
        transition: all 0.3s ease; z-index: 1 !important;
    }
    .stat-card:hover { transform: translateY(-0.1875rem); border-color: rgba(96, 165, 250, 0.3); }
    .stat-icon { width:3rem; height:3rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-size:1.5rem; }
    .stat-value { font-size:1.6rem; font-weight: 700; line-height:1.2; }
    .stat-label { font-size:0.75rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing:0.0312rem; margin-top:0.125rem; }
    .stat-sub { font-size:0.75rem; color: rgba(255, 255, 255, 0.55); margin-top:0.125rem; }

    /* Píldora informativa (factor / contadores) */
    .badge-factor { background: rgba(96, 165, 250, 0.15); border: 0.0625rem solid rgba(96, 165, 250, 0.4); color: #93c5fd; padding:0.1875rem 0.625rem; border-radius: 1.25rem; font-size:0.72rem; font-weight: 600; letter-spacing:0.0188rem; display: inline-flex; align-items: center; gap:0.3125rem; }

    .form-control-custom, .form-select { background: var(--panel) !important; border: 0.0625rem solid var(--border-2) !important; color: var(--txt) !important; border-radius: 0.625rem !important; padding:0.625rem 0.875rem !important; }
    .form-control-custom:focus, .form-select:focus { border-color: #60a5fa !important; box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2) !important; }
    .form-label { color: rgba(255,255,255, 0.9) !important; font-weight: 500; font-size:0.9rem; margin-bottom:0.375rem; }

    /* Placeholders legibles sobre fondo oscuro */
    input::placeholder, textarea::placeholder, .form-control::placeholder, .form-select::placeholder {
        color: rgba(255, 255, 255, 0.45) !important;
        opacity: 1 !important;
    }
    option { background: var(--panel); color: var(--txt); }
    
    .form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 0.875rem 0.75rem !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        padding-right:2rem !important;
    }

    /* Textos y etiquetas */
    label, .form-label, .text-muted, .small { color: rgba(255, 255, 255, 0.85) !important; }
    small, .small { color: rgba(255, 255, 255, 0.7) !important; }
    .text-muted i { color: rgba(255, 255, 255, 0.5); }

    /* DataTables - Solo para pantalla */
    .dataTables_wrapper { color: #ffffff; }
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter, 
    .dataTables_wrapper .dataTables_info { color: rgba(255, 255, 255, 0.7); }
    .dataTables_wrapper .dataTables_paginate .paginate_button { color: var(--txt); background: var(--panel); border: 0.0625rem solid var(--border-2); border-radius: 0.5rem; padding:0.375rem 0.75rem; margin:0 0.1875rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(96, 165, 250, 0.3); border-color: #60a5fa; color: var(--txt); }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-color: #60a5fa; color: #ffffff; }
    .dataTables_wrapper .dataTables_length select, 
    .dataTables_wrapper .dataTables_filter input { background: var(--panel); border: 0.0625rem solid var(--border-2); border-radius: 0.5rem; color: var(--txt); }
    
    /* Tablas en pantalla - texto blanco */
    .table-custom { width:100%; border-collapse: collapse; font-size:0.85rem; }
    .table-custom th, .table-custom td { padding:0.75rem 0.625rem; border-bottom: 0.0625rem solid rgba(255,255,255,0.06); text-align: left; vertical-align: middle; }
    .table-custom th { background: var(--panel-2); font-weight: 600; text-transform: uppercase; font-size:0.75rem; letter-spacing:0.0312rem; color: rgba(255,255,255,0.8); text-align: center; border-bottom: 0.0625rem solid rgba(255,255,255,0.1); }
    .table-custom td { color: #ffffff; }
    .table-custom tr:hover td { background: rgba(255,255,255,0.03); }

    /* Marcado de trabajadores con más de 20 días en el submayor (mismo estilo que empleados.php) */
    #tablaSaldos tbody tr.vacaciones-excedidas td { background-color: rgba(220, 53, 69, 0.12) !important; color: #ff8888 !important; }
    #tablaSaldos tbody tr.vacaciones-excedidas td:first-child { border-left: 0.1875rem solid #dc3545; }
    
    .table-custom tfoot td {
        background: #181820;
        box-shadow: inset 0 0.0625rem 0 rgba(255,255,255,0.1);
        color: #ffffff;
    }

    /* DataTables cuerpo */
    table.dataTable tbody tr td { color: #ffffff; }

    /* Badges para pantalla */
    .badge-acumulacion { background: rgba(var(--color-success-rgb), 0.2); border: 0.0625rem solid var(--color-success); color: var(--color-success-soft); padding:0.25rem 0.75rem; border-radius: 1.25rem; font-size:0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap:0.25rem; }
    .badge-disfrute { background: rgba(var(--amber-soft-rgb), 0.2); border: 0.0625rem solid var(--amber-soft); color: var(--amber-soft); padding:0.25rem 0.75rem; border-radius: 1.25rem; font-size:0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap:0.25rem; }
    .badge-ajuste { background: rgba(var(--blue-soft-rgb), 0.2); border: 0.0625rem solid var(--blue-soft); color: var(--blue-soft); padding:0.25rem 0.75rem; border-radius: 1.25rem; font-size:0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap:0.25rem; }

    /* Contenedor de Tabla (el scroll vertical lo gestiona DataTables con scrollY) */
    .data-table-wrapper {
        width:100%;
        overflow-x: auto !important;
        border: 0.0625rem solid rgba(255, 255, 255, 0.08);
        border-radius: 0.5rem;
        background: rgba(15, 15, 20, 0.4);
        position: relative;
        padding:0.75rem 0.875rem;
    }
    .data-table-wrapper .dataTables_scrollHead { border-radius: 0.375rem 0.375rem 0 0; }
    .data-table-wrapper .dataTables_scrollBody { border-radius: 0 0 0.375rem 0.375rem; }
    .data-table-wrapper .dataTables_wrapper .dataTables_info,
    .data-table-wrapper .dataTables_wrapper .dataTables_paginate { padding-top:0.625rem; }

    /* Encabezados fijos de DataTables (scrollY) */
    .dataTables_scrollHead .table-custom thead th {
        background: #1b1b24 !important;
        color: rgba(255, 255, 255, 0.85);
        border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.1);
    }
    .dataTables_scrollFoot .table-custom tfoot td {
        background: #181820 !important;
        color: #ffffff;
        font-weight: 700;
        box-shadow: inset 0 0.0625rem 0 rgba(255, 255, 255, 0.1);
    }

    /* Encabezado fijo de la tabla del auxiliar (modal, tabla simple) */
    #tablaHistorialIndividual thead th { position: sticky; background: #1b1b24 !important; z-index: 5; }
    #tablaHistorialIndividual thead tr:first-child th { top:0; }
    #tablaHistorialIndividual thead tr:last-child th { top:2.4375rem; }

    .text-muted-win {
        color: rgba(255, 255, 255, 0.6) !important;
        font-weight: 500;
        letter-spacing:0.0188rem;
    }

    .footer-card { margin-top:1.5rem; padding:1.25rem; font-size:0.8rem; color: rgba(255, 255, 255, 0.6); }
    .modal-content-modern { background: rgba(32, 32, 40, 0.95); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255, 255, 255, 0.1); border-radius: 1rem; color: white; }
    .btn-close-custom { background: transparent; border: 0.125rem solid white; border-radius: 0.5rem; width:2.5rem; height:2.5rem; color: white; font-size:1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .btn-close-custom:hover { background-color: #ef4444; border-color: #ef4444; }

    #liveClock { display: inline-block; min-width:5.3125rem; text-align: center; font-variant-numeric: tabular-nums; letter-spacing:0.0312rem; }
    .date-badge { background: rgba(255, 255, 255, 0.08); padding:0.5rem 1rem; border-radius: 0.75rem; font-size:0.85rem; color: white; }

    .info-card { background: rgba(255, 255, 255, 0.03); border-left: 0.25rem solid #60a5fa; border-radius: 0.5rem; padding:0.75rem 1rem; }
    .info-card-label { font-size:0.75rem; color: rgba(255, 255, 255, 0.6); margin-bottom:0.25rem; }
    .info-card-value { font-size:1.1rem; font-weight: 600; color: white; }

    .dt-buttons { display: flex !important; gap:0.3125rem; flex-wrap: wrap; margin-right:0.625rem; }
    .dataTables_length { display: block !important; margin-right:0.9375rem; }
    .dataTables_length label { color: rgba(255, 255, 255, 0.7) !important; display: flex !important; align-items: center; gap:0.5rem; white-space: nowrap; }

    /* Dropdown de DataTables Buttons (Opciones / Columnas) */
    div.dt-button-collection {
        background: #1e2330 !important;
        border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
        border-radius: 0.625rem !important;
        box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.55) !important;
        padding:0.375rem !important;
    }
    div.dt-button-collection .dt-button {
        background: transparent !important;
        color: #e2e8f0 !important;
        border: none !important;
        border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.07) !important;
        text-align: left;
        padding:0.5rem 0.875rem !important;
    }
    div.dt-button-collection .dt-button:hover,
    div.dt-button-collection .dt-button.active {
        background: rgba(59, 130, 246, 0.18) !important;
        color: #ffffff !important;
    }
    div.dt-button-collection .dt-button:last-child { border-bottom: none !important; }
    div.dt-button-collection div.dt-button-collection {
        margin-top:0.25rem !important;
    }

    /* Botones flotantes de navegación rápida (ir al inicio / ir al final) */
    .scroll-quick-btns {
        position: fixed;
        right:1.25rem;
        bottom:1.25rem;
        display: flex;
        flex-direction: column;
        gap:0.625rem;
        z-index: 999;
    }
    .scroll-quick-btn {
        width:2.75rem;
        height:2.75rem;
        border-radius: 0.75rem;
        border: 0.0625rem solid rgba(255, 255, 255, 0.15);
        background: linear-gradient(135deg, #0078d4, #00a8e8);
        color: #ffffff;
        font-size:1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.4);
        transition: all 0.2s ease;
    }
    .scroll-quick-btn:hover { transform: translateY(-0.125rem); background: linear-gradient(135deg, #0086e8, #00b8ff); box-shadow: 0 0.625rem 1.75rem rgba(0, 0, 0, 0.5); }
    .scroll-quick-btn.to-bottom { background: linear-gradient(135deg, #8b5cf6, #6366f1); }
    .scroll-quick-btn.to-bottom:hover { background: linear-gradient(135deg, #9b6cf6, #7373ff); }
    .scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }

    /* ============================================= */
    /* ESTILOS DE IMPRESIÓN - ENCABEZADOS SIN FONDO */
    /* ============================================= */
    @media print {
        /* Ocultar todo lo que no sea el reporte */
        .win-sidebar, 
        .win-topbar, 
        .btn-win, 
        .btn-win-primary, 
        .btn-win-danger,
        .btn-win-success,
        .btn-win-warning,
        .btn-win-sm,
        .stats-grid, 
        .glass-card:not(.has-table), 
        .dropdown, 
        .dt-buttons, 
        .dataTables_length, 
        .dataTables_filter, 
        .dataTables_info, 
        .dataTables_paginate,
        .buttons-colvis,
        button,
        .sidebar-toggle, 
        .user-menu, 
        .date-badge,
        .dropdown-menu,
        .modal,
        .modal-backdrop,
        .win11-bg,
        .win11-bg::before {
            display: none !important;
        }

        /* Resetear fondos a blanco */
        * {
            background: white !important;
            background-color: white !important;
            box-shadow: none !important;
            text-shadow: none !important;
        }

        /* Fondo blanco para el body y contenedores principales */
        body, 
        html,
        .main-container, 
        .glass-card, 
        .data-table-wrapper,
        .table-custom,
        .report-container {
            background: white !important;
            background-color: white !important;
            margin:0 !important;
            padding:0 !important;
            border: none !important;
        }

        /* FUERZA TEXTO NEGRO EN ABSOLUTAMENTE TODO */
        body, html, div, span, p, h1, h2, h3, h4, h5, h6,
        table, thead, tbody, tfoot, tr, th, td,
        .table-custom, .table-custom *, 
        .dataTable, .dataTable *,
        .text-right, .text-left, .text-center,
        .text-success, .text-warning, .text-info, 
        .text-danger, .text-primary, .text-secondary,
        .fw-bold, .font-weight-bold, strong, b,
        .badge, .badge-acumulacion, .badge-disfrute, .badge-ajuste,
        .info-card, .info-card-value, .info-card-label,
        td span, td div, td strong, td small, td i,
        th span, th div, th strong, th small, th i {
            color: #000000 !important;
        }

        /* ESTILOS DE ENCABEZADOS - SOBREESCRIBEN LOS STICKY HEADERS DE PANTALLA */
        .table-custom thead tr:first-child th,
        .table-custom thead tr:last-child th,
        .table-custom thead tr th,
        .table-custom th, 
        .dataTable th,
        .table-custom thead th, 
        .dataTable thead th,
        thead th,
        th {
            background: #e8e8e8 !important;
            background-color: #e8e8e8 !important;
            color: #000000 !important;
            border: 0.0625rem solid #000000 !important;
            font-weight: bold !important;
            font-size:11pt !important;
        }

        /* ESTILOS DE CELDAS - FONDO BLANCO, TEXTO NEGRO */
        td, 
        .table-custom td, 
        .dataTable td,
        .table-custom tbody td, 
        .dataTable tbody td {
            background: white !important;
            background-color: white !important;
            color: #000000 !important;
            border: 0.0625rem solid #000000 !important;
        }

        /* DataTables específico */
        .dataTables_wrapper,
        .dataTables_wrapper .dataTable,
        .dataTables_wrapper .dataTable td,
        .dataTables_wrapper .dataTable th,
        .dataTables_wrapper .dataTable tbody tr td,
        .dataTables_wrapper .dataTable thead th {
            background: white !important;
            background-color: white !important;
            color: #000000 !important;
        }

        /* Badges en impresión - sin fondo, borde negro */
        .badge-acumulacion, 
        .badge-disfrute, 
        .badge-ajuste,
        .badge {
            background: white !important;
            background-color: white !important;
            border: 0.0625rem solid #000000 !important;
            color: #000000 !important;
            padding:0.125rem 0.5rem !important;
        }

        /* Filas de totales */
        tfoot td, tfoot th,
        .total-row td, .subtotal-row td {
            background: white !important;
            background-color: white !important;
            color: #000000 !important;
            font-weight: bold !important;
            border: 0.0625rem solid #000000 !important;
        }

        /* Firmas */
        .signatures, .signatures td, .signatures div,
        .footer-note, .footer-note * {
            color: #000000 !important;
            background: white !important;
            border: none !important;
        }
        
        .signatures-line {
            border-top: 0.0625rem solid #000000 !important;
        }

        /* Asegurar que NO haya ningún fondo gris en los encabezados */
        th[class*="sorting"],
        th[class*="sorting_asc"],
        th[class*="sorting_desc"],
        .sorting, .sorting_asc, .sorting_desc {
            background: white !important;
            background-color: white !important;
            background-image: none !important;
        }
    }
:root {
    /* Colores tomados de las variables globales de user_menu.php (oscuro/claro) */
    --radius:1rem;
    --radius-sm:0.625rem;
    --font:'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
}

/* ============ PORTADO DESDE VERSION 1 ============ */
/* ============ Botones ============ */
.btn-prof {
    display: inline-flex; align-items: center; gap:0.5rem;
    background: rgba(255,255,255,0.06); border: 0.0625rem solid var(--border);
    color: var(--txt); padding:0.5625rem 0.9375rem; border-radius: var(--radius-sm);
    font-size:0.82rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.btn-prof:hover { background: rgba(255,255,255,0.1); border-color: var(--border-2); color: #fff; }
.btn-primary-solid { background: linear-gradient(135deg, #2563eb, #7c3aed); border: none; color: #fff; }
.btn-primary-solid:hover { background: linear-gradient(135deg, #2f6ff2, #8b5cf6); color: #fff; box-shadow: 0 0.5rem 1.375rem rgba(59,130,246,0.35); transform: translateY(-0.0625rem); }
.btn-success-solid { background: linear-gradient(135deg, var(--color-success), var(--color-success)); border: none; color: #fff; }
.btn-success-solid:hover { background: linear-gradient(135deg, #06b574, #16c48b); color: #fff; }
.btn-danger-soft { background: rgba(239,68,68,0.12); border: 0.0625rem solid rgba(239,68,68,0.35); color: #fca5a5; }
.btn-danger-soft:hover { background: rgba(239,68,68,0.22); color: #fecaca; }
.btn-icon-sm {
    width:2.125rem; height:2.125rem; display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.05); border: 0.0625rem solid var(--border); border-radius: 0.5625rem;
    color: var(--muted); cursor: pointer; transition: all 0.2s; font-size:0.85rem;
}
.btn-icon-sm:hover { background: rgba(96,165,250,0.15); color: #93c5fd; }

/* 

CARDS ============ */
.card-prof {
    background: var(--card);
    border: 0.0625rem solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 0.375rem 1.5rem rgba(0,0,0,0.25);
    backdrop-filter: blur(0.625rem);
    transition: border-color 0.25s, transform 0.25s, box-shadow 0.25s;
}
.card-prof:hover { border-color: var(--border-2); }

.card-head {
    display: flex; align-items: center; justify-content: space-between; gap:0.75rem;
    padding:1rem 1.25rem; border-bottom: 0.0625rem solid var(--border); flex-wrap: wrap;
}
.card-head h5 { margin:0; font-size:0.98rem; font-weight: 700; display: flex; align-items: center; gap:0.625rem; }
.card-body-prof { padding:1.125rem 1.25rem; }

/* Píldoras / badges */
.badge-pill-prof {
    display: inline-flex; align-items: center; gap:0.375rem;
    padding:0.3125rem 0.75rem; border-radius: 6.1875rem; font-size:0.72rem; font-weight: 600;
    background: rgba(255,255,255,0.05); border: 0.0625rem solid var(--border); color: var(--muted);
}
.badge-9 {
    background: linear-gradient(135deg, rgba(59,130,246,0.16), rgba(139,92,246,0.16));
    border: 0.0625rem solid rgba(139,92,246,0.4); color: #c4b5fd;
}

/* Tarjetas de cristal (banner / resumen) */

/* ============ STATS ============ */
.stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap:0.875rem; }
.stat-card-prof {
    background: linear-gradient(160deg, var(--panel-2), var(--panel));
    border: 0.0625rem solid var(--border); border-radius: var(--radius);
    padding:1rem 1.125rem; position: relative; overflow: hidden;
    transition: transform 0.22s, border-color 0.22s;
}
.stat-card-prof:hover { transform: translateY(-0.1875rem); border-color: var(--border-2); }
.stat-card-prof::after {
    content: ''; position: absolute; top:-40%; right:-25%; width:8.125rem; height:8.125rem;
    border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.05), transparent 65%);
    pointer-events: none;
}
.stat-top { display: flex; align-items: flex-start; justify-content: space-between; gap:0.5rem; }
.stat-icon {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; flex: 0 0 2.75rem;
    display: flex; align-items: center; justify-content: center; font-size:1.15rem;
}
.stat-value { font-size:1.45rem; font-weight: 800; line-height:1.15; letter-spacing:-0.0188rem; }
.stat-value small { font-size:0.68rem; font-weight: 600; opacity: 0.75; }
.stat-label { font-size:0.7rem; color: var(--muted); text-transform: uppercase; letter-spacing:0.0438rem; margin-top:0.1875rem; font-weight: 600; }
.stat-sub { font-size:0.74rem; color: var(--faint); margin-top:0.1875rem; }
.stat-bar { height:0.25rem; border-radius: 6.1875rem; margin-top:0.75rem; background: rgba(255,255,255,0.06); overflow: hidden; }
.stat-bar > i { display: block; height:100%; border-radius: 6.1875rem; }

/* ============ 

FILTROS ============ */
.filtros-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap:0.75rem 0.875rem; }
.filtros-grid .f-item { display: flex; flex-direction: column; gap:0.375rem; min-width:0; }
.f-label { font-size:0.72rem; font-weight: 600; color: var(--muted); letter-spacing:0.0188rem; }
.f-label i { margin-right:0.3125rem; color: var(--faint); }

.form-control-prof, .form-select-prof {
    background: var(--panel) !important;
    border: 0.0625rem solid var(--border-2) !important;
    color: var(--txt) !important;
    border-radius: var(--radius-sm) !important;
    padding:0.5rem 0.75rem !important;
    font-size:0.83rem !important;
}
.form-control-prof:focus, .form-select-prof:focus {
    border-color: var(--blue) !important;
    box-shadow: 0 0 0 0.1875rem rgba(59,130,246,0.18) !important;
    outline: none !important;
}
.form-control-prof::placeholder, .form-select-prof::placeholder { color: var(--faint) !important; }
option { background: var(--panel); color: var(--txt); }

/* Inputs de fecha: el icono del calendario se ajusta al tema */
input[type="date"].form-control-prof::-webkit-calendar-picker-indicator,
input[type="time"].form-control-prof::-webkit-calendar-picker-indicator,
input[type="datetime-local"].form-control-prof::-webkit-calendar-picker-indicator {
    filter: invert(1) !important;
    cursor: pointer !important;
    opacity: 0.9 !important;
}
[data-theme="light"] input[type="date"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="light"] input[type="time"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="light"] input[type="datetime-local"].form-control-prof::-webkit-calendar-picker-indicator {
    filter: invert(0) !important;
}
input[type="date"].form-control-prof::-webkit-calendar-picker-indicator:hover,
input[type="time"].form-control-prof::-webkit-calendar-picker-indicator:hover,
input[type="datetime-local"].form-control-prof::-webkit-calendar-picker-indicator:hover { opacity: 1 !important; }
input[type="date"].form-control-prof::-webkit-datetime-edit-fields-wrapper { color: var(--txt) !important; }

/* Select2 en el modal */
.select2-container--default .select2-selection--single {
    background-color: var(--panel) !important;
    border: 0.0625rem solid var(--border-2) !important;
    border-radius: var(--radius-sm) !important;
    height:2.375rem !important;
    display: flex; align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: var(--txt) !important; line-height:2.375rem !important; font-size:0.83rem !important; padding-left:0.75rem !important;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder { color: var(--faint) !important; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height:2.25rem !important; right:0.5rem !important; }
.select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: #cbd5e1 transparent transparent transparent !important; }
.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: var(--blue) !important;
    box-shadow: 0 0 0 0.1875rem rgba(59,130,246,0.18) !important;
    outline: none !important;
}
.select2-dropdown {
    background-color: var(--panel) !important; border: 0.0625rem solid var(--border-2) !important; border-radius: var(--radius-sm) !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: var(--panel) !important; border: 0.0625rem solid var(--border-2) !important;
    color: var(--txt) !important; border-radius: 0.375rem !important; padding:0.375rem 0.625rem !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field::placeholder { color: var(--faint) !important; }
.select2-container--default .select2-results__option { color: var(--txt) !important; font-size:0.83rem !important; padding:0.5rem 0.75rem !important; }
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background: rgba(59,130,246,0.25) !important; color: #fff !important; }
.select2-container--default .select2-results__option[aria-selected="true"],
.select2-container--default .select2-results__option--selected { background: rgba(139,92,246,0.25) !important; color: #e9d5ff !important; }
.select2-container--default .select2-results > .select2-results__options { max-height:17.5rem !important; }
.form-select-prof {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 0.875rem 0.75rem !important;
    appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important;
    padding-right:2rem !important;
}

/* Switch */
.form-switch-prof { display: flex; align-items: center; gap:0.5rem; cursor: pointer; user-select: none; }
.form-switch-prof .form-check-input { width:2.4em; height:1.25em; cursor: pointer; background-color: rgba(255,255,255,0.12); border-color: var(--border-2); }
.form-switch-prof .form-check-input:checked { background-color: var(--blue); border-color: var(--blue); }

/* ============ 

TABS ============ */
.tabs-prof {
    display: flex; align-items: center; gap:0.25rem; flex-wrap: wrap;
    background: var(--panel); border: 0.0625rem solid var(--border);
    border-radius: var(--radius-sm); padding:0.3125rem; margin-bottom:1rem;
}
.tab-prof {
    display: inline-flex; align-items: center; gap:0.5rem;
    padding:0.5625rem 1rem; border-radius: 0.5rem; border: 0.0625rem solid transparent;
    background: transparent; color: var(--muted); font-size:0.83rem; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
}
.tab-prof:hover { color: var(--txt); background: rgba(255,255,255,0.05); }
.tab-prof.active {
    background: linear-gradient(135deg, rgba(59,130,246,0.22), rgba(139,92,246,0.18));
    color: #dbeafe; border-color: rgba(96,165,250,0.35);
    box-shadow: inset 0 0 0 0.0625rem rgba(96,165,250,0.12);
}
.tab-prof .count {
    background: rgba(255,255,255,0.1); border-radius: 6.1875rem; padding:0.0625rem 0.5rem;
    font-size:0.68rem; font-weight: 700;
}
.tab-pane-prof { display: none; }
.tab-pane-prof.active { display: block; animation: fadeUp 0.28s ease; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(0.375rem); } to { opacity: 1; transform: none; } }

/* ============ 

TABLAS ============ */
.table-prof-wrap { overflow-x: auto; padding:0.375rem 0.375rem 0.75rem; }
.table-prof { width:100%; border-collapse: separate; border-spacing: 0; font-size:0.82rem; }
.table-prof thead th {
    background: var(--panel-2); color: var(--muted); font-weight: 700; font-size:0.7rem;
    text-transform: uppercase; letter-spacing:0.0312rem; padding:0.75rem 0.75rem;
    border-bottom: 0.125rem solid rgba(59,130,246,0.25); white-space: normal; text-align: center;
}
.table-prof thead th span { display: block; }
.table-prof thead th:first-child { border-top-left-radius: 0.625rem; }
.table-prof thead th:last-child { border-top-right-radius: 0.625rem; }
.table-prof tbody td { padding:0.6875rem 0.75rem; border-bottom: 0.0625rem solid rgba(255,255,255,0.05); color: var(--txt); vertical-align: middle; }
.table-prof tbody tr { transition: background 0.15s; }
.table-prof tbody tr:hover td { background: rgba(255,255,255,0.035); }
.table-prof tfoot td {
    background: var(--panel-2); color: var(--txt); font-weight: 700; padding:0.6875rem 0.75rem;
    border-top: 0.125rem solid rgba(59,130,246,0.25);
}
.table-prof tfoot td:first-child { border-bottom-left-radius: 0.625rem; }
.table-prof tfoot td:last-child { border-bottom-right-radius: 0.625rem; }

/* Filas excedidas >20 días */
#tablaSaldos tbody tr.vacaciones-excedidas td { background: rgba(239,68,68,0.10) !important; }
#tablaSaldos tbody tr.vacaciones-excedidas td:first-child { border-left: 0.1875rem solid var(--red); }
#tablaSaldos tbody tr.vacaciones-excedidas .saldo-cierre { color: #fca5a5 !important; }

/* Columnas numéricas */
.c-num { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
.c-center { text-align: center; }
.c-left { text-align: left; }

/* Badges de operación */
.badge-op { display: inline-flex; align-items: center; gap:0.375rem; padding:0.25rem 0.75rem; border-radius: 6.1875rem; font-size:0.72rem; font-weight: 600; white-space: nowrap; }
.badge-acumulacion { background: rgba(var(--color-success-rgb),0.13); border: 0.0625rem solid rgba(var(--color-success-rgb),0.4); color: var(--color-success-soft); }
.badge-disfrute { background: rgba(var(--amber-soft-rgb),0.13); border: 0.0625rem solid rgba(var(--amber-soft-rgb),0.4); color: var(--amber-soft); }
.badge-ajuste { background: rgba(var(--blue-soft-rgb),0.13); border: 0.0625rem solid rgba(var(--blue-soft-rgb),0.4); color: var(--blue-soft); }
.badge-activo { background: rgba(var(--color-success-rgb),0.13); border: 0.0625rem solid rgba(var(--color-success-rgb),0.4); color: var(--color-success-soft); }
.badge-baja { background: rgba(245,158,11,0.13); border: 0.0625rem solid rgba(245,158,11,0.4); color: #fcd34d; }
.badge-excedido { background: rgba(239,68,68,0.13); border: 0.0625rem solid rgba(239,68,68,0.4); color: #fca5a5; }

/* 

DataTables dark */
.dataTables_wrapper { color: var(--txt); }
.dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info { color: var(--muted); }
.dataTables_wrapper .dataTables_filter input {
    background: var(--panel); border: 0.0625rem solid var(--border-2); border-radius: 0.5rem;
    color: var(--txt); padding:0.375rem 0.625rem; margin-left:0.375rem;
}
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--blue); box-shadow: 0 0 0 0.1875rem rgba(59,130,246,0.15); outline: none; }
/* Buscador personalizado (lupa + botón limpiar) */
.dt-search .input-group-text { background-color: var(--panel); border-color: var(--border-2); color: #60a5fa; }
.dt-search .form-control { background-color: var(--panel); border-color: var(--border-2); color: var(--txt); }
.dt-search .form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 0.1875rem rgba(59,130,246,0.15); outline: none; }
.dt-search .btn-outline-secondary { border-color: rgba(255, 255, 255, 0.2); color: rgba(255, 255, 255, 0.7); }
.dataTables_wrapper .dataTables_length select {
    background: var(--panel); border: 0.0625rem solid var(--border-2); border-radius: 0.5rem; color: var(--txt); padding:0.25rem 0.5rem;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: var(--muted) !important; background: rgba(255,255,255,0.05) !important;
    border: 0.0625rem solid var(--border) !important; border-radius: 0.5rem !important; padding:0.3125rem 0.6875rem !important; margin:0 0.125rem !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(96,165,250,0.2) !important; color: #fff !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #2563eb, #7c3aed) !important; border-color: transparent !important; color: #fff !important;
}
.dt-buttons { display: flex !important; gap:0.375rem; flex-wrap: wrap; }
.btn-win-sm {
    display: inline-flex; align-items: center; gap:0.375rem;
    background: rgba(255,255,255,0.06); border: 0.0625rem solid var(--border);
    color: var(--txt); padding:0.375rem 0.6875rem; border-radius: 0.5rem;
    font-size:0.74rem; font-weight: 600; cursor: pointer; transition: all 0.18s;
}
.btn-win-sm:hover { background: rgba(96,165,250,0.18); border-color: rgba(96,165,250,0.4); color: #fff; }

/* Toolbar de DataTables */
.dt-toolbar { display: flex; align-items: center; justify-content: space-between; gap:0.625rem; flex-wrap: wrap; margin-bottom:0.75rem; }

/* ============ 

INFO CARDS (Auxiliar) ============ */
.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap:0.75rem; margin-bottom:1rem; }
.info-card-prof {
    background: var(--card-hover); border: 0.0625rem solid var(--border);
    border-left: 0.1875rem solid var(--blue); border-radius: var(--radius-sm); padding:0.75rem 0.875rem;
}
.info-card-prof .lbl { font-size:0.68rem; color: var(--faint); text-transform: uppercase; letter-spacing:0.0312rem; margin-bottom:0.25rem; font-weight: 600; }
.info-card-prof .val { font-size:1rem; font-weight: 700; color: var(--txt); }
.info-card-prof.ok { border-left-color: var(--green); }

/* ============ 

MODALES / OFF-CANVAS ============ */
.modal-content-prof {
    background: var(--card) !important;
    border: 0.0625rem solid var(--border-2) !important; border-radius: var(--radius) !important;
    color: var(--txt) !important;
}
.modal-content-prof .modal-header { border-bottom: 0.0625rem solid var(--border); padding:1rem 1.25rem; }
.modal-content-prof .modal-footer { border-top: 0.0625rem solid var(--border); padding:0.875rem 1.25rem; }
.modal-content-prof .modal-title { font-weight: 700; font-size:1rem; display: flex; align-items: center; gap:0.625rem; }

/* ===== Secciones del modal de ajuste ===== */
.ajuste-seccion { margin-bottom:0.75rem; padding:0.625rem 0.875rem; background: var(--panel-2); border: 0.0625rem solid var(--border); border-radius: var(--radius-sm); }
.ajuste-seccion-titulo {
    display: flex; align-items: center; gap:0.5rem; margin-bottom:0.625rem;
    font-size:0.78rem; font-weight: 700; color: var(--txt); text-transform: uppercase; letter-spacing:0.0375rem;
}
.ajuste-seccion-titulo i { color: var(--blue); font-size:0.95rem; }
.ajuste-seccion-num {
    width:1.375rem; height:1.375rem; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff;
    font-size:0.7rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center;
}
.ajuste-hint { font-size:0.7rem; color: var(--faint); }
.ajuste-hint i { color: var(--faint); }

/* Ficha básica de saldo (columna derecha de la sección trabajador) */
.ajuste-ficha-basica {
    display: flex; align-items: center; justify-content: center; text-align: center;
    height:2.625rem; padding:0.375rem 0.75rem; border-radius: var(--radius-sm);
    background: rgba(var(--color-success-rgb), 0.12); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.35);
    color: var(--color-success-soft); font-weight: 700; font-size:0.9rem;
}
.ajuste-ficha-basica .lbl { color: var(--muted); font-weight: 600; font-size:0.78rem; }
.ajuste-ficha-basica.vacio { background: rgba(255, 255, 255, 0.04); border-color: var(--border-2); color: var(--faint); font-weight: 500; font-size:0.75rem; }

/* Ficha ampliada del trabajador */
.ajuste-ficha {
    margin-top:0.625rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(9.375rem, 1fr));
    gap:0.5rem; padding:0.625rem 0.75rem; border-radius: var(--radius-sm);
    background: rgba(59, 130, 246, 0.06); border: 0.0625rem solid rgba(59, 130, 246, 0.22);
}
.ajuste-ficha-item { display: flex; flex-direction: column; gap:0.1875rem; min-width:0; }
.ajuste-ficha-item .lbl { font-size:0.66rem; font-weight: 600; color: var(--muted); letter-spacing:0.0188rem; }
.ajuste-ficha-item .lbl i { margin-right:0.25rem; color: var(--faint); }
.ajuste-ficha-item .val { font-size:0.9rem; font-weight: 700; color: var(--txt); }

/* Vista previa del efecto del movimiento */
.ajuste-preview {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(10.625rem, 1fr));
    gap:0.75rem; padding:0.875rem 1rem; border-radius: var(--radius-sm);
    background: rgba(var(--color-success-rgb), 0.07); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.28);
    margin-top:0.25rem;
}
.ajuste-preview-item { display: flex; flex-direction: column; gap:0.1875rem; min-width:0; }
.ajuste-preview-item .lbl { font-size:0.66rem; font-weight: 600; color: var(--muted); letter-spacing:0.0188rem; }
.ajuste-preview-item .lbl i { margin-right:0.25rem; color: var(--faint); }
.ajuste-preview-item .val { font-size:1rem; font-weight: 800; color: var(--txt); }
.ajuste-preview .prev-resultado { border-left: 0.125rem solid rgba(var(--color-success-rgb), 0.5); padding-left:0.75rem; }
.ajuste-preview .prev-resultado .val { color: var(--color-success-soft); }
.ajuste-preview.aviso-disfrute { background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.4); }
.ajuste-preview.aviso-disfrute .prev-resultado { border-left-color: rgba(245, 158, 11, 0.6); }
.ajuste-preview.aviso-disfrute .prev-resultado .val { color: #fbbf24; }
.btn-close-custom {
    background: transparent; border: 0.0625rem solid var(--border-2); border-radius: 0.5625rem;
    width:2.25rem; height:2.25rem; color: var(--muted); font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.18s;
}
.btn-close-custom:hover { background: rgba(239,68,68,0.18); border-color: rgba(239,68,68,0.5); color: #fca5a5; }

.offcanvas-prof { background: var(--panel) !important; border-left: 0.0625rem solid var(--border-2) !important; color: var(--txt); width:min(55rem, 96vw) !important; }
.offcanvas-prof .offcanvas-header { border-bottom: 0.0625rem solid var(--border); padding:1rem 1.25rem; }
.offcanvas-prof .offcanvas-body { padding:1.125rem 1.25rem; }

/* Scroll rápido */
.scroll-quick-btns { position: fixed; right:1.25rem; bottom:1.25rem; display: flex; flex-direction: column; gap:0.625rem; z-index: 950; }
.scroll-quick-btn {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; border: 0.0625rem solid var(--border-2);
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: var(--shadow); transition: all 0.2s;
}
.scroll-quick-btn:hover { transform: translateY(-0.125rem); filter: brightness(1.15); }
.scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }

/* Footer del módulo */
.module-footer { margin-top:1.375rem; padding:1.125rem 1.25rem; font-size:0.78rem; color: var(--faint); }

/* Animación de entrada */
.fade-in-up { animation: fadeUp 0.4s ease both; }

/* ============ 

RESPONSIVE ============ */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 1024px) {
    .filtros-grid { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .app-topbar { flex-direction: column; align-items: stretch; }
    .topbar-actions { justify-content: flex-start; }
    .filtros-grid { grid-template-columns: 1fr 1fr; }
    .info-grid { grid-template-columns: 1fr; }
    .offcanvas-body .info-grid { grid-template-columns: 1fr !important; }
}

/* ============ 

IMPRESIÓN ============ */
@media print {
    .app-topbar, .win-sidebar, .dropdown, .dt-buttons, .dataTables_length,
    .dataTables_filter, .dataTables_info, .dataTables_paginate, .scroll-quick-btns,
    .card-head .btn, .tab-prof, .tabs-prof, .user-menu, .badge-pill-prof,
    .banner-method .icon-wrap, button, .modal, .modal-backdrop, .win11-bg, .win11-bg::before { display: none !important; }

    * { background: white !important; background-color: white !important; box-shadow: none !important; text-shadow: none !important; }
    body, html, .main-container, .card-prof, .table-prof-wrap { background: white !important; margin:0 !important; padding:0 !important; border: none !important; }

    body, table, thead, tbody, tfoot, tr, th, td, h1, h2, h3, h4, h5, h6, span, div, p, strong, b, small {
        color: #000 !important; }
    .table-prof th, .table-prof thead th {
        background: #e8e8e8 !important; color: #000 !important; border: 0.0625rem solid #000 !important; font-size:10pt !important;
    }
    .table-prof th, .table-prof thead th, .table-prof .c-center, .table-prof .c-num { text-align: center !important; }
    .table-prof .c-left { text-align: left !important; }
    .table-prof td, .table-prof tbody td { background: #fff !important; color: #000 !important; border: 0.0625rem solid #000 !important; }
    .table-prof tfoot td { background: #f0f0f0 !important; color: #000 !important; border: 0.0625rem solid #000 !important; }
    .badge-acumulacion, .badge-disfrute, .badge-ajuste { background: #fff !important; border: 0.0625rem solid #000 !important; color: #000 !important; }
    .tab-pane-prof { display: block !important; }
}


</style>

</head>
<body class="<?php echo !$puede_crear_submayor ? 'solo-lectura' : ''; ?>">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    
<!-- Top Bar Windows 11 -->
<div class="win-topbar fade-in-up">
    <div class="d-flex align-items-center gap-3">
        <button class="sidebar-toggle" id="sidebarToggleBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
            <h1><i class="fas fa-book me-2" style="color: #60a5fa;"></i>Submayor de Vacaciones</h1>
            <p>Control de acumulado, disfrute e histórico de vacaciones</p>
        </div>
    </div>
    
    <!-- Contenedor de elementos del lado derecho -->
    <div class="d-flex align-items-center gap-3">
        <?php if ($puede_crear_submayor): ?>
        <button type="button" class="btn-prof btn-primary-solid" data-bs-toggle="modal" data-bs-target="#modalAjuste" data-accion-ajuste title="Ajustar Saldo o Ajuste en Días" data-tooltip="Ajustar Saldo o Ajuste en Días" data-tooltip-theme="primary">
            <i class="fas fa-sliders-h"></i>Ajuste
        </button>
        <?php endif; ?>
        <!-- Dropdown de Opciones (se mantiene) -->
        <div class="dropdown">
            <button class="btn-win" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: none;" title="Opciones de vista y exportación" data-tooltip="Opciones de vista y exportación" data-tooltip-theme="primary">
                <i class="fas fa-cog me-1"></i> Opciones <i class="fas fa-chevron-down ms-2" style="color: rgba(255,255,255,0.6);"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalAjuste" data-accion-ajuste>
                        <i class="fas fa-sliders-h me-2" style="color: #3b82f6;"></i> 
                        Ajustar / Saldo Inicial
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" id="btnReporteFisico" title="Imprimir reporte en formato tradicional" data-tooltip="Imprimir reporte en formato tradicional" data-tooltip-theme="success">
                        <i class="fas fa-print me-2" style="color: var(--color-success);"></i> 
                        Reporte Físico / Impresión
                        <small class="d-block text-muted" style="font-size:0.65rem;">Imprimir reporte en formato tradicional</small>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalReporteVersat">
                        <i class="fas fa-chart-bar me-2" style="color: var(--color-success);"></i> 
                        Reporte Vac. Acum.
                        <small class="d-block text-muted" style="font-size:0.65rem;">Vacaciones acumuladas por área/centro costo</small>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1" style="font-size:0.7rem;">Personalizar Vista</h6></li>
                <li><a class="dropdown-item" href="#" id="menuColumnas" title="Mostrar/Ocultar Columnas de la tabla" data-tooltip="Mostrar/Ocultar Columnas" data-tooltip-theme="primary"><i class="fas fa-columns me-2" style="color: #60a5fa;"></i> Mostrar/Ocultar Columnas</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1" style="font-size:0.7rem;">Exportar y Reportes</h6></li>
                <li><a class="dropdown-item" href="#" id="menuExportPrint" title="Imprimir Reporte" data-tooltip="Imprimir Reporte" data-tooltip-theme="warning"><i class="fas fa-print text-warning me-2"></i> Imprimir Reporte</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportPDF" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="danger"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportWord" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word text-primary me-2"></i> Exportar a Word</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportExcel" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="success"><i class="fas fa-file-excel text-success me-2"></i> Exportar a Excel</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportCSV" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="info"><i class="fas fa-file-csv text-info me-2"></i> Exportar a CSV</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportTXT" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="secondary"><i class="fas fa-file-alt text-secondary me-2"></i> Exportar a TXT</a></li>
            </ul>
        </div>
        
        <?php include '../includes/user_menu.php'; ?>
    </div>
</div>

<!-- Metodología Ley 116 Cuba Banner -->
<?php 
$total_acumulado = array_sum(array_column($saldos, 'total_acumulado'));
$total_devengado = array_sum(array_column($saldos, 'importe_acumulado'));
$total_disfrutado = array_sum(array_column($saldos, 'total_disfrutado'));
$total_importe_disfrutado = array_sum(array_column($saldos, 'importe_disfrutado'));
$total_ajuste_dias = array_sum(array_column($saldos, 'total_ajuste_dias'));
$total_ajuste_importe = array_sum(array_column($saldos, 'total_ajuste_importe'));
$total_saldo_inicial = array_sum(array_column($saldos, 'saldo_inicial'));
$total_saldo = array_sum(array_column($saldos, 'saldo_cierre'));

// Contar trabajadores con más de 20 días en el submayor (mismos que el marcado rojo, no el campo de trabajadores)
$total_excedidos = count(array_filter($saldos, function ($s) use ($trabajadores_excedidos_ids) {
    return isset($trabajadores_excedidos_ids[(int)$s['id']]);
}));
?>

<div class="glass-card mb-4 p-4 fade-in-up">
    <div class="d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1" style="min-width:16.25rem;">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <i class="fas fa-scale-balanced fa-2x" style="color: #60a5fa;"></i>
                <strong>Bases de Auditoría Cubana</strong>
                <span class="badge-factor"><i class="fas fa-percent me-1"></i>Base 9.09%</span>
            </div>
            <p class="mb-1 text-muted-win" style="font-size:0.85rem;">
                El submayor acumula el <strong style="color:var(--color-success-soft);">tiempo de descanso</strong> y el <strong style="color:var(--color-success-soft);">importe acumulado</strong> sobre la base del 9.09% del total de salarios devengados en el período de pago.
                Toda baja laboral o disfrute físico exige la liquidación del saldo que muestra este libro.
            </p>
        </div>
    </div>
</div>

<!-- Línea de Resumen del Período -->
<div class="glass-card mb-4 p-4 fade-in-up">
    <small class="d-flex align-items-start gap-3 flex-wrap">
        <div class="text-muted-win">
            <i class="fas fa-calendar-week me-1" style="color:#60a5fa;"></i>
            Período consultado: <strong><?php echo htmlspecialchars($rango_string); ?></strong> · 
            <span class="badge px-2 py-1 rounded-pill" style="background: rgba(var(--color-success-rgb),0.15); border: 0.0625rem solid rgba(var(--color-success-rgb),0.4); color: var(--color-success-soft);">
                <i class="fas fa-users me-1"></i><?php echo count($saldos); ?> trabajadores
            </span>
            De ellos: 
            <span class="badge px-2 py-1 rounded-pill" style="background: rgba(var(--blue-soft-rgb),0.15); border: 0.0625rem solid rgba(var(--blue-soft-rgb),0.4); color: var(--blue-soft);">
                <i class="fas fa-user-check me-1"></i><?php echo count($activos); ?> activos
            </span>
            <span class="badge px-2 py-1 rounded-pill" style="background: rgba(var(--amber-soft-rgb),0.15); border: 0.0625rem solid rgba(var(--amber-soft-rgb),0.4); color: var(--amber-soft);">
                <i class="fas fa-user-slash me-1"></i><?php echo count($bajas); ?> Bajas
            </span>
            <span class="badge px-2 py-1 rounded-pill" style="background: rgba(239,68,68,0.13); border: 0.0625rem solid rgba(239,68,68,0.4); color: var(--red);">
                <i class="fas fa-exclamation-triangle me-1"></i><?php echo $total_excedidos; ?> con más de 20 días
            </span>
        </div>
    </small>
</div>

<!-- ========================================== -->
<!-- ============ TARJETAS DE ESTADÍSTICAS ============ -->
        <div class="stats-grid mb-3 fade-in-up" style="animation-delay:0.05s;">
            <div class="stat-card-prof" style="border-left:0.1875rem solid var(--green);">
                <div class="stat-top">
                    <div>
                        <div class="stat-value" style="color:var(--color-success-soft);"><?php echo number_format($total_acumulado, 2); ?><small> días</small></div>
                        <div class="stat-label">Días Acumulados</div>
                        <div class="stat-sub"><?php echo formatearMoneda($total_devengado); ?> devengado</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(var(--color-success-rgb),0.14); color:var(--color-success-soft);"><i class="fas fa-calendar-plus"></i></div>
                </div>
                <div class="stat-bar"><i style="width:100%; background:linear-gradient(90deg,var(--color-success),var(--color-success-soft));"></i></div>
            </div>

            <div class="stat-card-prof" style="border-left:0.1875rem solid var(--amber);">
                <div class="stat-top">
                    <div>
                        <div class="stat-value" style="color:var(--amber-soft);"><?php echo number_format($total_disfrutado, 2); ?><small> días</small></div>
                        <div class="stat-label">Días Disfrutados</div>
                        <div class="stat-sub"><?php echo formatearMoneda($total_importe_disfrutado); ?> disfrutado</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(245,158,11,0.14); color:#fbbf24;"><i class="fas fa-calendar-minus"></i></div>
                </div>
                <div class="stat-bar"><i style="width:<?php echo ($total_acumulado > 0) ? max(2, min(100, round($total_disfrutado / $total_acumulado * 100))) : 0; ?>%; background:linear-gradient(90deg,#d97706,#fbbf24);"></i></div>
            </div>

            <div class="stat-card-prof" style="border-left:0.1875rem solid var(--blue);">
                <div class="stat-top">
                    <div>
                        <div class="stat-value" style="color:#93c5fd;"><?php echo number_format($total_saldo_inicial, 2); ?><small> días</small></div>
                        <div class="stat-label">Saldo Inicial</div>
                        <div class="stat-sub">Acumulado previo al período</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(59,130,246,0.14); color:#818cf8;"><i class="fas fa-book-open"></i></div>
                </div>
                <div class="stat-bar"><i style="width:100%; background:linear-gradient(90deg,#2563eb,#818cf8);"></i></div>
            </div>

            <div class="stat-card-prof" style="border-left:0.1875rem solid var(--violet);">
                <div class="stat-top">
                    <div>
                        <div class="stat-value" style="color:#c4b5fd;"><?php echo number_format($total_saldo, 2); ?><small> días</small></div>
                        <div class="stat-label">Saldo Pendiente</div>
                        <div class="stat-sub">Fondo acumulado vigente</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(139,92,246,0.14); color:#a78bfa;"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="stat-bar"><i style="width:<?php echo ($total_acumulado > 0) ? max(2, min(100, round(max(0,$total_saldo) / $total_acumulado * 100))) : 0; ?>%; background:linear-gradient(90deg,#7c3aed,#a78bfa);"></i></div>
            </div>

            <div class="stat-card-prof" style="border-left:0.1875rem solid var(--red);">
                <div class="stat-top">
                    <div>
                        <div class="stat-value" style="color:#fca5a5;"><?php echo $total_excedidos; ?><small> trab.</small></div>
                        <div class="stat-label">&gt; 20 Días</div>
                        <div class="stat-sub">Vacaciones excedidas</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(239,68,68,0.14); color:#f87171;"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-bar"><i style="width:<?php echo ($saldos ? max(3, min(100, round($total_excedidos / count($saldos) * 100))) : 0); ?>%; background:linear-gradient(90deg,#dc2626,#f87171);"></i></div>
            </div>
        </div>

        <!-- ============ CUADRE ENTRE LAS 3 TABLAS ============ -->
        <?php $hay_descuadres = count($descuadres) > 0; ?>
        <div class="card-prof mb-3 fade-in-up" style="animation-delay:0.075s;">
            <div class="card-head">
                <h5><i class="fas fa-scale-balanced" style="color:<?php echo $hay_descuadres ? '#f87171' : 'var(--color-success-soft)'; ?>;"></i> Estado actual del Cuadre del submayor
                    <?php if ($hay_descuadres): ?>
                        <span style="color:#f87171; text-transform:uppercase; font-weight:700;">(No cuadra <i class="fas fa-times-circle" style="color:#f87171;"></i>)</span>
                    <?php else: ?>
                        <span style="color:var(--color-success-soft); text-transform:uppercase; font-weight:700;">(Cuadrado <i class="fas fa-check-circle" style="color:var(--color-success-soft);"></i>)</span>
                    <?php endif; ?>
                </h5>
                <?php if ($hay_descuadres): ?>
                    <span class="badge-op badge-excedido"><i class="fas fa-exclamation-triangle"></i> <?php echo count($descuadres); ?> descuadre<?php echo count($descuadres) === 1 ? '' : 's'; ?></span>
                <?php else: ?>
                    <span class="badge-op badge-acumulacion"><i class="fas fa-check-circle"></i> Todo cuadra</span>
                <?php endif; ?>
            </div>
            <div class="card-body-prof">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <div class="ajuste-seccion" style="text-align:center;">
                            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.0312rem;color:#aeb9cf;">Saldo Submayor</div>
                            <div class="stat-value" style="color:#93c5fd;margin-top:0.25rem;"><?php echo number_format($cuadre_totales['submayor'], 2); ?><small> días</small></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ajuste-seccion" style="text-align:center;">
                            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.0312rem;color:#aeb9cf;">Acumulado Trabajador</div>
                            <div class="stat-value" style="color:#a78bfa;margin-top:0.25rem;"><?php echo number_format($cuadre_totales['trabajador'], 2); ?><small> días</small></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ajuste-seccion" style="text-align:center;">
                            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.0312rem;color:#aeb9cf;">Acumulado Nóminas</div>
                            <div class="stat-value" style="color:var(--color-success-soft);margin-top:0.25rem;"><?php echo number_format($cuadre_totales['nominas'], 2); ?><small> días</small></div>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php if ($hay_descuadres): ?>
                        <button type="button" class="btn-prof btn-danger-soft" data-bs-toggle="modal" data-bs-target="#modalDescuadres" title="Ver detalle de descuadres" data-tooltip="Ver detalle de descuadres" data-tooltip-theme="danger">
                            <i class="fas fa-eye me-2"></i> Ver detalle de descuadres
                        </button>
                    <?php else: ?>
                        <span class="badge-op badge-acumulacion"><i class="fas fa-check-circle"></i> Submayor, nóminas y fichas de trabajadores coinciden</span>
                    <?php endif; ?>
                    <?php if ($excluidos_no_acumular > 0): ?>
                        <span class="badge-op badge-baja" style="opacity:0.85;"><i class="fas fa-user-slash"></i> <?php echo $excluidos_no_acumular; ?> excluido<?php echo $excluidos_no_acumular === 1 ? '' : 's'; ?> (no acumulan)</span>
                    <?php endif; ?>
                    <span class="text-muted-win" style="font-size:0.8rem;">Comparación global: nóminas contabilizadas vs submayor vs ficha del trabajador.</span>
                </div>
            </div>
        </div>

        <!-- ============ FILTROS ============ -->
        <div class="card-prof mb-3 fade-in-up" style="animation-delay:0.1s;">
            <div class="card-head">
                <h5><i class="fas fa-filter" style="color:#818cf8;"></i> Filtros de Búsqueda</h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn-win-sm" id="btnLimpiarFiltros" title="Restablecer consulta" data-tooltip="Restablecer consulta" data-tooltip-theme="warning">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                    <button type="button" class="btn-icon-sm" id="btnToggleFiltros" title="Mostrar/ocultar panel de filtros" data-tooltip="Mostrar/ocultar filtros" data-tooltip-theme="primary">
                        <i class="fas fa-chevron-up" id="filtrosChevron"></i>
                    </button>
                </div>
            </div>
            <div id="filtrosPanel">
                <form method="GET" id="filtroForm" class="card-body-prof">
                    <div class="filtros-grid">
                        <div class="f-item" style="grid-column: span 2;">
                            <label class="f-label"><i class="fas fa-calendar-alt"></i> Año</label>
                            <select name="consulta_anio" id="consultaAnio" class="form-select-prof" title="Seleccionar año de consulta" data-tooltip="Seleccionar año" data-tooltip-theme="primary">
                                <option value="">-- Todos --</option>
                                <?php for($y = date('Y')-2; $y <= date('Y')+5; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $consulta_anio == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 2;">
                            <label class="f-label"><i class="fas fa-moon"></i> Mes</label>
                            <select name="consulta_mes" id="consultaMes" class="form-select-prof" title="Seleccionar mes de consulta" data-tooltip="Seleccionar mes" data-tooltip-theme="primary">
                                <option value="">-- Todos --</option>
                                <?php for($m = 1; $m <= 12; $m++): $m_pad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $m_pad; ?>" <?php echo $consulta_mes == $m_pad ? 'selected' : ''; ?>><?php echo nombreMesEspanol($m_pad); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 2;">
                            <label class="f-label"><i class="fas fa-calendar-day"></i> Desde</label>
                            <input type="date" name="periodo_desde" id="periodoDesde" class="form-control-prof" value="<?php echo $periodo_desde; ?>">
                        </div>
                        <div class="f-item" style="grid-column: span 2;">
                            <label class="f-label"><i class="fas fa-calendar-day"></i> Hasta</label>
                            <input type="date" name="periodo_hasta" id="periodoHasta" class="form-control-prof" value="<?php echo $periodo_hasta; ?>">
                        </div>
                        <div class="f-item" style="grid-column: span 4;">
                            <label class="f-label"><i class="fas fa-user"></i> Trabajador</label>
                            <select name="trabajador_id" id="filtroTrabajador" class="form-select-prof" title="Filtrar por trabajador" data-tooltip="Filtrar por trabajador" data-tooltip-theme="secondary">
                                <option value="">-- Todos --</option>
                                <?php foreach ($trabajadores as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo $trabajador_id == $t['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['codigo'] . ' - ' . $t['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-building"></i> Área</label>
                            <select name="area_id" id="filtroArea" class="form-select-prof" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
                                <option value="">-- Todas --</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?php echo $a['id']; ?>" <?php echo $area_id == $a['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['nombre_area']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-chart-pie"></i> Centro de Costo</label>
                            <select name="centro_costo_id" id="filtroCentroCosto" class="form-select-prof" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
                                <option value="">-- Todos --</option>
                                <?php foreach ($centros_costo as $cc): ?>
                                    <option value="<?php echo $cc['id']; ?>" <?php echo $centro_costo_id == $cc['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-tags"></i> Movimiento</label>
                            <select name="tipo_movimiento" id="filtroTipoMovimiento" class="form-select-prof" title="Filtrar por tipo de movimiento" data-tooltip="Filtrar por tipo de movimiento" data-tooltip-theme="secondary">
                                <option value="">-- Todos --</option>
                                <option value="acumulacion" <?php echo $tipo_movimiento == 'acumulacion' ? 'selected' : ''; ?>>Acumulación</option>
                                <option value="disfrute" <?php echo $tipo_movimiento == 'disfrute' ? 'selected' : ''; ?>>Disfrute</option>
                                <option value="ajuste" <?php echo $tipo_movimiento == 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-hourglass-end"></i> Vacaciones &gt; 20 días</label>
                            <select id="filtroVacacionesExcedidas" class="form-select-prof" title="Filtrar vacaciones con más de 20 días" data-tooltip="Vacaciones > 20 días" data-tooltip-theme="warning">
                                <option value="">-- Todos --</option>
                                <option value="mayor20"><i class="fas fa-circle"></i> Mayor a 20 días</option>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-user-tag"></i> Estadostado</label>
                            <select id="filtroEstadoLaboral" class="form-select-prof" title="Filtrar por estado laboral" data-tooltip="Filtrar por estado laboral" data-tooltip-theme="secondary">
                                <option value="">-- Todos --</option>
                                <option value="activos">Activos</option>
                                <option value="bajas">Baja</option>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 3;">
                            <label class="f-label"><i class="fas fa-balance-scale"></i> Saldos</label>
                            <select id="filtroSaldoMenorDia" class="form-select-prof" title="Filtrar por saldo acumulado" data-tooltip="Filtrar por saldo" data-tooltip-theme="secondary">
                                <option value="">-- Todos --</option>
                                <option value="menor_uno">Menos de 1 día</option>
                                <option value="uno_o_mas">1 día o más</option>
                            </select>
                        </div>
                        <div class="f-item" style="grid-column: span 4; justify-content: flex-end;">
                            <div class="d-flex gap-3 align-items-end flex-wrap">
                                <label class="form-switch-prof mb-2">
                                    <input type="checkbox" class="form-check-input" id="chkOcultarSaldoCero" <?php echo $ocultar_saldo_cero === '1' ? 'checked' : ''; ?>>
                                    <span style="font-size:0.8rem;">Ocultar saldo en cero</span>
                                </label>
                                <label class="form-switch-prof mb-2">
                                    <input type="checkbox" class="form-check-input" id="chkSoloBajas" <?php echo $solo_bajas === '1' ? 'checked' : ''; ?>>
                                    <span style="font-size:0.8rem;">Solo bajas</span>
                                </label>
                            </div>
                            <input type="hidden" name="ocultar_saldo_cero" id="ocultarSaldoCero" value="<?php echo $ocultar_saldo_cero; ?>">
                            <input type="hidden" name="solo_bajas" id="soloBajas" value="<?php echo $solo_bajas; ?>">
                        </div>
                        <div class="f-item" style="grid-column: span 8; align-items: flex-end;">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn-prof btn-primary-solid">
                                    <i class="fas fa-search"></i> Aplicar filtros
                                </button>
                                <button type="button" class="btn-prof" id="btnVerTodos" title="Ver todos los registros sin filtros" data-tooltip="Ver todos los registros" data-tooltip-theme="primary">
                                    <i class="fas fa-eye"></i> Ver todos
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============ PESTAÑAS ============ -->
        <div class="tabs-prof fade-in-up" style="animation-delay:0.15s;" role="tablist">
            <button type="button" class="tab-prof active" data-tab="tab-consolidado" role="tab">
                <i class="fas fa-chart-pie"></i> Consolidado de Saldos
                <span class="count"><?php echo count($saldos); ?></span>
            </button>
            <button type="button" class="tab-prof" data-tab="tab-historial" role="tab">
                <i class="fas fa-history"></i> Historial de Operaciones
                <span class="count"><?php echo count($movimientos); ?></span>
            </button>
            <?php if ($trabajador_id && isset($detalle_trabajador)): ?>
            <button type="button" class="tab-prof" data-tab="tab-auxiliar" role="tab">
                <i class="fas fa-book-open"></i> Libro Auxiliar Individual
            </button>
            <?php endif; ?>
        </div>

        <!-- ============ PANEL: CONSOLIDADO ============ -->
        <div class="tab-pane-prof active" id="tab-consolidado">
            <div class="card-prof fade-in-up">
                <div class="card-head">
                    <h5><i class="fas fa-chart-pie" style="color:#818cf8;"></i> Consolidado de Saldos por Trabajador</h5>
                    <span class="badge-pill-prof"><i class="fas fa-database"></i> <?php echo count($saldos); ?> registros</span>
                </div>
                <div class="table-prof-wrap">
                    <table class="table-prof" id="tablaSaldos">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th class="c-center">Expediente</th>
                                <th class="c-center">Estado</th>
                                <th class="c-left">Trabajador</th>
                                <th class="c-left">Cargo</th>
                                <th class="c-left">Área</th>
                                <th class="c-center"><span>Días </span><span>Acum.</span></th>
                                <th class="c-center"><span>Importe </span><span>Acum.</span></th>
                                <th class="c-center"><span>Días </span><span>Disfr.</span></th>
                                <th class="c-center"><span>Importe </span><span>Disfr.</span></th>
                                <th class="c-center"><span>Días </span><span>Ajuste</span></th>
                                <th class="c-center"><span>Saldo </span><span>Inicial</span></th>
                                <th class="c-center"><span>Saldo al </span><span>Cierre</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saldos as $s): ?>
                            <?php $fila_excedida = isset($trabajadores_excedidos_ids[(int)$s['id']]); ?>
                            <tr data-trabajador-id="<?php echo $s['id']; ?>" data-ci="<?php echo htmlspecialchars($s['ci']); ?>" data-estado="<?php echo ((int)($s['activo'] ?? 0) === 1) ? 'activo' : 'baja'; ?>" class="<?php echo $fila_excedida ? 'vacaciones-excedidas' : ''; ?>">
                                <td class="c-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn-icon-sm" onclick="verHistorialTrabajador(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nombre_completo']); ?>')" title="Ver Auxiliar individual" data-tooltip="Ver Auxiliar individual" data-tooltip-theme="primary">
                                            <i class="fas fa-book"></i>
                                        </button>
                                        <a href="?trabajador_id=<?php echo $s['id']; ?>&consulta_anio=<?php echo $consulta_anio; ?>&consulta_mes=<?php echo $consulta_mes; ?>&periodo_desde=<?php echo $periodo_desde; ?>&periodo_hasta=<?php echo $periodo_hasta; ?>&area_id=<?php echo $area_id; ?>&centro_costo_id=<?php echo $centro_costo_id; ?>" class="btn-icon-sm" style="text-decoration:none;" title="Vista fija del Libro" data-tooltip="Vista fija del Libro" data-tooltip-theme="info">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                    </div>
                                </td>
                                <td class="c-center fw-bold" style="color:#93c5fd;"><?php echo htmlspecialchars($s['codigo']); ?></td>
                                <td class="c-center">
                                    <?php if ((int)($s['activo'] ?? 0) === 1): ?>
                                        <span class="badge-op badge-activo"><i class="fas fa-user-check"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-op badge-baja"><i class="fas fa-user-slash"></i> Baja</span>
                                    <?php endif; ?>
                                    <?php if ($fila_excedida): ?>
                                        <span class="badge-op badge-excedido"><i class="fas fa-exclamation-triangle"></i> &gt;20</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($s['nombre_completo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['cargo'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($s['nombre_area'] ?? '-'); ?></td>
                                <td class="c-num"><?php echo number_format($s['total_acumulado'], 2); ?></td>
                                <td class="c-num" style="color:var(--color-success-soft);"><?php echo formatearMoneda($s['importe_acumulado']); ?></td>
                                <td class="c-num"><?php echo number_format($s['total_disfrutado'], 2); ?></td>
                                <td class="c-num" style="color:var(--amber-soft);"><?php echo formatearMoneda($s['importe_disfrutado']); ?></td>
                                <td class="c-num"><?php echo number_format($s['total_ajuste_dias'], 2); ?></td>
                                <td class="c-num"><?php echo number_format($s['saldo_inicial'], 2); ?></td>
                                <td class="c-num saldo-cierre fw-bold <?php echo $fila_excedida ? 'text-danger' : ''; ?>" style="<?php echo $fila_excedida ? '' : 'color:#a78bfa;'; ?>"><?php echo number_format($s['saldo_cierre'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td></td>
                                <td colspan="5" class="text-end">TOTALES CONSOLIDADOS</td>
                                <td class="c-num"><?php echo number_format($total_acumulado, 2); ?></td>
                                <td class="c-num"><?php echo formatearMoneda($total_devengado); ?></td>
                                <td class="c-num"><?php echo number_format($total_disfrutado, 2); ?></td>
                                <td class="c-num"><?php echo formatearMoneda($total_importe_disfrutado); ?></td>
                                <td class="c-num"><?php echo number_format($total_ajuste_dias, 2); ?></td>
                                <td class="c-num"><?php echo number_format($total_saldo_inicial, 2); ?></td>
                                <td class="c-num"><?php echo number_format($total_saldo, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ PANEL: HISTORIAL ============ -->
        <div class="tab-pane-prof" id="tab-historial">
            <div class="card-prof fade-in-up">
                <div class="card-head">
                    <h5><i class="fas fa-history" style="color:#818cf8;"></i> Historial de Operaciones del Submayor</h5>
                    <span class="badge-pill-prof"><i class="fas fa-exchange-alt"></i> <?php echo count($movimientos); ?> movimientos</span>
                </div>
                <div class="table-prof-wrap">
                    <table class="table-prof" id="tablaMovimientos">
                        <thead>
                            <tr>
                                <th class="c-center">Fecha Mov.</th>
                                <th class="c-center">Período</th>
                                <th class="c-center">Expediente</th>
                                <th class="c-left">Trabajador</th>
                                <th class="c-left">Cargo</th>
                                <th class="c-left">Área</th>
                                <th class="c-center">Operación</th>
                                <th class="c-center">Días</th>
                                <th class="c-center">Importe</th>
                                <th class="c-left">Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td class="c-center"><?php echo date('d/m/Y h:i A', strtotime($m['fecha_movimiento'])); ?></td>
                                <td class="c-center"><?php echo date('m/Y', strtotime($m['periodo_desde'])); ?></td>
                                <td class="c-center fw-bold" style="color:#93c5fd;"><?php echo htmlspecialchars($m['codigo']); ?></td>
                                <td><strong><?php echo htmlspecialchars($m['nombre_completo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($m['cargo'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($m['nombre_area'] ?? '-'); ?></td>
                                <td class="c-center">
                                    <?php if ($m['tipo_movimiento'] === 'acumulacion'): ?>
                                        <span class="badge-op badge-acumulacion"><i class="fas fa-calendar-plus"></i> Acumulado</span>
                                    <?php elseif ($m['tipo_movimiento'] === 'disfrute'): ?>
                                        <span class="badge-op badge-disfrute"><i class="fas fa-calendar-minus"></i> Disfrutado</span>
                                    <?php else: ?>
                                        <span class="badge-op badge-ajuste"><i class="fas fa-balance-scale"></i> Ajuste</span>
                                    <?php endif; ?>
                                </td>
                                <td class="c-num fw-bold <?php echo $m['tipo_movimiento'] === 'acumulacion' ? 'text-success' : 'text-warning'; ?>"><?php echo number_format($m['dias'], 2); ?></td>
                                <td class="c-num fw-bold"><?php echo formatearMoneda($m['importe']); ?></td>
                                <td><small style="color:var(--muted);"><?php echo htmlspecialchars($m['referencia'] ?? '-'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ PANEL: LIBRO AUXILIAR INDIVIDUAL ============ -->
        <?php if ($trabajador_id && isset($detalle_trabajador)): ?>
        <div class="tab-pane-prof" id="tab-auxiliar">
            <div class="card-prof fade-in-up">
                <div class="card-head">
                    <h5><i class="fas fa-book-open" style="color:var(--indigo-soft);"></i> Libro Submayor de Vacaciones de
                        <span style="color:var(--violet-soft);"><?php echo htmlspecialchars($resumen_trabajador['nombre_completo'] ?? ''); ?></span>
                    </h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn-win-sm" id="btnActualizarAuxiliar" onclick="actualizarLibroAuxiliar()" title="Actualizar libro auxiliar" data-tooltip="Actualizar libro auxiliar" data-tooltip-theme="info">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                        <a href="submayor_vacaciones.php" class="btn-win-sm" title="Cerrar vista del libro auxiliar" data-tooltip="Cerrar vista" data-tooltip-theme="danger"><i class="fas fa-times"></i> Cerrar vista</a>
                    </div>
                </div>

                <div class="card-body-prof">
                    <div class="info-grid">
                        <div class="info-card-prof">
                            <div class="lbl">Expediente / Ficha</div>
                            <div class="val"><?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? '-'); ?></div>
                        </div>
                        <div class="info-card-prof">
                            <div class="lbl">Cargo</div>
                            <div class="val" style="font-size:0.9rem;"><?php echo htmlspecialchars($resumen_trabajador['cargo'] ?? '-'); ?></div>
                        </div>
                        <div class="info-card-prof">
                            <div class="lbl">Área / Departamento</div>
                            <div class="val" style="font-size:0.9rem;"><?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? '-'); ?></div>
                        </div>
                        <div class="info-card-prof ok">
                            <div class="lbl" style="color:var(--color-success-soft);">Salario Escala</div>
                            <div class="val" style="color:var(--color-success-soft);"><?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?></div>
                        </div>
                    </div>

                    <div class="table-prof-wrap">
                        <table class="table-prof" id="tablaDetalleIndividual">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="c-center"><span>Fecha </span><span>Mov.</span></th>
                                    <th rowspan="2" class="c-center"><span>Período </span><span>Nómina</span></th>
                                    <th rowspan="2" class="c-center"><span>Operación / </span><span>Concepto</span></th>
                                    <th colspan="2" class="c-center" style="color:var(--color-success-soft);"><span>ENTRADAS </span><span>(+)</span></th>
                                    <th colspan="2" class="c-center" style="color:var(--amber-soft);"><span>SALIDAS </span><span>(-)</span></th>
                                    <th colspan="2" class="c-center" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.08);"><span>SALDOS </span><span>CORRIDOS</span></th>
                                    <th rowspan="2" class="c-left"><span>Referencia / </span><span>Observaciones</span></th>
                                </tr>
                                <tr>
                                    <th class="c-center" style="color:var(--color-success-soft);">Días</th>
                                    <th class="c-center" style="color:var(--color-success-soft);">Importe</th>
                                    <th class="c-center" style="color:var(--amber-soft);">Días</th>
                                    <th class="c-center" style="color:var(--amber-soft);">Importe</th>
                                    <th class="c-center" style="color:var(--blue-soft);">Días</th>
                                    <th class="c-center" style="color:var(--blue-soft);">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $saldo_dias_corrido = 0;
                                $saldo_importe_corrido = 0;
                                foreach ($detalle_trabajador as $mov):
                                    $es_entrada = ($mov['tipo_movimiento'] === 'acumulacion' || ($mov['tipo_movimiento'] === 'ajuste' && $mov['dias'] >= 0));
                                    $dias_ent = $es_entrada ? abs($mov['dias']) : 0;
                                    $imp_ent = $es_entrada ? abs($mov['importe']) : 0;
                                    $dias_sal = !$es_entrada ? abs($mov['dias']) : 0;
                                    $imp_sal = !$es_entrada ? abs($mov['importe']) : 0;
                                    if ($es_entrada) { $saldo_dias_corrido += $dias_ent; $saldo_importe_corrido += $imp_ent; }
                                    else { $saldo_dias_corrido -= $dias_sal; $saldo_importe_corrido -= $imp_sal; }
                                ?>
                                <tr>
                                    <td class="c-center"><?php echo date('d/m/Y h:i A', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td class="c-center"><?php echo date('m/Y', strtotime($mov['periodo_desde'])); ?></td>
                                    <td class="c-center">
                                        <?php if ($mov['tipo_movimiento'] === 'acumulacion'): ?>
                                            <span class="badge-op badge-acumulacion"><i class="fas fa-plus"></i> Acumulación</span>
                                        <?php elseif ($mov['tipo_movimiento'] === 'disfrute'): ?>
                                            <span class="badge-op badge-disfrute"><i class="fas fa-minus"></i> Disfrute</span>
                                        <?php else: ?>
                                            <span class="badge-op badge-ajuste"><i class="fas fa-balance-scale"></i> Ajuste Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="c-num" style="color:var(--color-success-soft);"><?php echo $dias_ent > 0 ? number_format($dias_ent, 2) : '-'; ?></td>
                                    <td class="c-num" style="color:var(--color-success-soft);"><?php echo $imp_ent > 0 ? formatearMoneda($imp_ent) : '-'; ?></td>
                                    <td class="c-num" style="color:var(--amber-soft);"><?php echo $dias_sal > 0 ? number_format($dias_sal, 2) : '-'; ?></td>
                                    <td class="c-num" style="color:var(--amber-soft);"><?php echo $imp_sal > 0 ? formatearMoneda($imp_sal) : '-'; ?></td>
                                    <td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);"><?php echo number_format($saldo_dias_corrido, 2); ?></td>
                                    <td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);"><?php echo formatearMoneda($saldo_importe_corrido); ?></td>
                                    <td><small style="color:var(--muted);"><?php echo htmlspecialchars($mov['referencia'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($detalle_trabajador)): ?>
                                    <tr><td colspan="10" class="c-center py-4" style="color:var(--faint);">No se han registrado movimientos contables para este trabajador.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php include '../includes/footer.php'; ?>
</div>

</div>

<!-- ==========================================
     MODAL DE REGISTRO / AJUSTE METODOLÓGICO
     ========================================== -->
<div class="modal fade" id="modalAjuste" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-prof">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h" style="color:#818cf8;"></i> Nuevo Ajuste / Saldo Inicial</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <form id="ajusteVacacionesForm" method="POST" novalidate>
                <input type="hidden" name="action" value="registrar_movimiento">
                <div class="modal-body py-3 px-4">

                    <!-- SECCIÓN 1: TRABAJADOR -->
                    <div class="ajuste-seccion">
                        <div class="ajuste-seccion-titulo">
                            <span class="ajuste-seccion-num">1</span>
                            <i class="fas fa-user-tie"></i> Trabajador
                        </div>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="f-label mb-1"><i class="fas fa-id-badge"></i> Trabajador <span class="text-danger">*</span></label>
                                <select name="trabajador_id" id="modalTrabajadorSelect" class="form-select-prof" required>
                                    <option value="">-- Seleccionar trabajador --</option>
                                    <?php foreach ($trabajadores as $t): ?>
                                        <option value="<?php echo $t['id']; ?>">
                                            <?php echo htmlspecialchars($t['nombre_completo'] . ' (' . $t['codigo'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="f-label mb-1 mt-2"><i class="fas fa-filter"></i> Filtrar por estado</label>
                                        <select id="modalFiltroEstadoTrabajador" class="form-select-prof">
                                            <option value="todos">Todos (activos y bajas)</option>
                                            <option value="activos">Solo activos</option>
                                            <option value="bajas">Solo de baja</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="f-label mb-1 mt-2"><i class="fas fa-balance-scale"></i> Filtrar por saldo</label>
                                        <select id="modalFiltroSaldoTrabajador" class="form-select-prof">
                                            <option value="todos">Todos los saldos</option>
                                            <option value="menor_uno">Menos de 1 día</option>
                                            <option value="uno_o_mas">1 día o más</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="f-label mb-1"><i class="fas fa-sync-alt"></i> Saldo acumulado actual</label>
                                <div class="ajuste-ficha-basica" id="fichaSaldoBasico">
                                    <span class="lbl">Seleccione un trabajador para ver su saldo</span>
                                </div>
                            </div>
                        </div>
                        <div id="ajusteFichaTrabajador" class="ajuste-ficha d-none">
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-archive"></i> Saldo acumulado</span>
                                <span class="val" id="fichaSaldoActual">0.00 días</span>
                            </div>
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-money-bill-wave"></i> Salario mensual</span>
                                <span class="val" id="fichaSalario">$0.00</span>
                            </div>
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-clock"></i> Salario por hora</span>
                                <span class="val" id="fichaSalarioHora">$0.00</span>
                            </div>
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-briefcase"></i> Cargo</span>
                                <span class="val" id="fichaCargo">-</span>
                            </div>
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-building"></i> Área</span>
                                <span class="val" id="fichaArea">-</span>
                            </div>
                            <div class="ajuste-ficha-item">
                                <span class="lbl"><i class="fas fa-user-check"></i> Estado</span>
                                <span class="val" id="fichaEstado">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: MOVIMIENTO + PERÍODO -->
                    <div class="ajuste-seccion">
                        <div class="ajuste-seccion-titulo">
                            <span class="ajuste-seccion-num">2</span>
                            <i class="fas fa-exchange-alt"></i> Datos del movimiento
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="f-label mb-1"><i class="fas fa-tag"></i> Tipo de Movimiento <span class="text-danger">*</span></label>
                                        <select name="tipo_movimiento" id="modalTipoMovimiento" class="form-select-prof" required>
                                            <option value="ajuste">Ajuste Metodológico</option>
                                            <option value="acumulacion">Saldo Inicial de Apertura (Acumulación)</option>
                                            <option value="disfrute">Disfrute Manual</option>
                                        </select>
                                        <small id="tipoMovimientoHint" class="d-block mt-1 ajuste-hint">
                                            <i class="fas fa-info-circle me-1"></i>Escriba días positivos o negativos; suman o restan según su signo.
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <label class="f-label mb-1"><i class="fas fa-sun"></i> Días de Vacación <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" name="dias" id="inputDiasAjuste" class="form-control-prof" placeholder="Ej: 2.00" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="f-label mb-1"><i class="fas fa-calculator"></i> Importe ($ CUP)</label>
                                        <input type="number" step="0.02" name="importe" id="inputImporteAjuste" class="form-control-prof" placeholder="0.00" required readonly>
                                        <small class="d-block mt-1 ajuste-hint"><i class="fas fa-calculator me-1"></i>días × salario ÷ <?php echo (int)$dias_laborables; ?> días laborables.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="f-label mb-1"><i class="fas fa-calendar-week"></i> Período contable</label>
                                    </div>
                                    <div class="col-12">
                                        <label class="f-label mb-1"><i class="fas fa-arrow-right"></i> Período Desde</label>
                                        <input type="date" name="periodo_desde" class="form-control-prof" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="f-label mb-1"><i class="fas fa-arrow-right"></i> Período Hasta</label>
                                        <input type="date" name="periodo_hasta" class="form-control-prof" value="<?php echo date('Y-m-t'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: JUSTIFICACIÓN -->
                    <div class="ajuste-seccion">
                        <div class="ajuste-seccion-titulo">
                            <span class="ajuste-seccion-num">3</span>
                            <i class="fas fa-file-signature"></i> Justificación documental
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="f-label mb-1"><i class="fas fa-file-alt"></i> Referencia Documental / Justificación <span class="text-danger">*</span></label>
                                <textarea name="referencia" class="form-control-prof" placeholder="Ej: Saldo inicial según auditoría de tránsito de sistema / Resolución No. X" rows="5" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="f-label mb-1"><i class="fas fa-pen"></i> Observaciones</label>
                                <textarea name="observaciones" class="form-control-prof" placeholder="Detalles contables adicionales..." rows="5"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- VISTA PREVIA DEL EFECTO -->
                    <div id="ajusteVistaPrevia" class="ajuste-preview d-none">
                        <div class="ajuste-preview-item">
                            <span class="lbl"><i class="fas fa-archive"></i> Saldo actual</span>
                            <span class="val" id="prevSaldoActual">0.00 días</span>
                        </div>
                        <div class="ajuste-preview-item">
                            <span class="lbl"><i class="fas fa-exchange-alt"></i> Efecto del movimiento</span>
                            <span class="val" id="prevEfecto">+0.00 días</span>
                        </div>
                        <div class="ajuste-preview-item">
                            <span class="lbl"><i class="fas fa-coins"></i> Importe</span>
                            <span class="val" id="prevImporte">$0.00</span>
                        </div>
                        <div class="ajuste-preview-item prev-resultado">
                            <span class="lbl"><i class="fas fa-flag-checkered"></i> Saldo resultante</span>
                            <span class="val" id="prevSaldoResultante">0.00 días</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-prof btn-danger-soft" data-bs-dismiss="modal" title="Cancelar y cerrar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="submit" class="btn-prof btn-primary-solid" id="btnAplicarAjuste" title="Aplicar ajuste al submayor" data-tooltip="Aplicar Ajuste" data-tooltip-theme="success"><i class="fas fa-check"></i> Aplicar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- OFF-CANVAS: LIBRO AUXILIAR INDIVIDUAL (AJAX)                     -->
<!-- ================================================================ -->
<style>
.icono-girando { animation: iconoGiro 0.7s linear infinite; }
@keyframes iconoGiro { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
<div class="offcanvas offcanvas-end offcanvas-prof" id="modalHistorialIndividual" tabindex="-1" data-bs-backdrop="true">
    <div class="offcanvas-header">
        <h5 class="modal-title m-0">
            <i class="fas fa-book-open me-2" style="color:var(--indigo-soft);"></i>
            Libro Auxiliar Individual: <span id="lblTrabajadorHistorial" style="color:var(--violet-soft);">-</span>
        </h5>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn-win-sm" id="btnActualizarAuxIndividual" onclick="actualizarAuxiliarIndividual()" title="Actualizar auxiliar individual" data-tooltip="Actualizar auxiliar" data-tooltip-theme="info"><i class="fas fa-sync-alt"></i> Actualizar</button>
            <button type="button" class="btn-close-custom" data-bs-dismiss="offcanvas" aria-label="Cerrar" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="offcanvas-body">
        <div class="info-grid mb-3" style="grid-template-columns:repeat(3,1fr);">
            <div class="info-card-prof">
                <div class="lbl">Cargo asignado</div>
                <div class="val" id="modalCargoTrabajador" style="font-size:0.9rem;">-</div>
            </div>
            <div class="info-card-prof">
                <div class="lbl">Área o Departamento</div>
                <div class="val" id="modalAreaTrabajador" style="font-size:0.9rem;">-</div>
            </div>
            <div class="info-card-prof ok">
                <div class="lbl" style="color:var(--color-success-soft);">Saldo acumulado actual</div>
                <div class="val" id="modalSaldoTrabajador" style="color:var(--color-success-soft);">-</div>
            </div>
        </div>

        <div class="table-prof-wrap" style="max-height:60vh; overflow-y: auto;">
            <table class="table-prof" id="tablaHistorialIndividual">
                <thead>
                    <tr>
                        <th rowspan="2">Fecha Mov.</th>
                        <th rowspan="2">Período Nómina</th>
                        <th rowspan="2">Operación</th>
                        <th colspan="2" style="color:var(--color-success-soft);">ENTRADAS (+)</th>
                        <th colspan="2" style="color:var(--amber-soft);">SALIDAS (-)</th>
                        <th colspan="2" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.08);">SALDOS CORRIDOS</th>
                        <th rowspan="2">Referencia</th>
                    </tr>
                    <tr>
                        <th style="color:var(--color-success-soft);">Días</th>
                        <th style="color:var(--color-success-soft);">Importe</th>
                        <th style="color:var(--amber-soft);">Días</th>
                        <th style="color:var(--amber-soft);">Importe</th>
                        <th style="color:var(--blue-soft);">Días</th>
                        <th style="color:var(--blue-soft);">Importe</th>
                    </tr>
                </thead>
                <tbody id="tablaBodyIndividual">
                    <tr><td colspan="10" class="c-center py-4" style="color:var(--faint);">No hay datos para mostrar.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- SCRIPTS -->
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/select2.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/datatables/1.13.6/dataTables.buttons.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.html5.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.print.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.colVis.min.js"></script>
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>

<script>
/* ============================================================
   SUBMAYOR DE VACACIONES · LÓGICA DE INTERFAZ
   ============================================================ */

// Variables globales para impresión
var globalNombreEmpresa = '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>';
var globalUsuarioNombre = '<?php echo htmlspecialchars($user_nombre_completo); ?>';
var globalPeriodoFiltroTexto = '<?php echo $rango_string; ?>';
var globalDiasLaborables = <?php echo $dias_laborables; ?>;
var globalJefeProyecto = '<?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?>';
var globalEspecialistaGestion = '<?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?>';
var globalEspecialistaRRHH = '<?php echo htmlspecialchars($config_empresa['especialista_gestionRRHH'] ?? ''); ?>';
var globalLogoBase64 = '<?php echo $logo_base64; ?>';

// Buscador personalizado con lupa y botón X para cada DataTable
function aplicarBuscadorDataTable(dt, placeholder) {
    if (!dt) return;
    var $tabla = $(dt.table().node());
    var inputId = 'buscar_' + ($tabla.attr('id') || 'tabla');
    var $search = $tabla.closest('.dataTables_wrapper').find('.dt-search');
    if (!$search.length) return;
    $search.html('' +
        '<div class="input-group input-group-sm" style="width:20rem;">' +
        '<span class="input-group-text bg-dark border-secondary"><i class="fas fa-search"></i></span>' +
        '<input type="text" class="form-control form-control-sm bg-dark border-secondary text-white" id="' + inputId + '" placeholder="' + (placeholder || 'Buscar...') + '" autocomplete="off">' +
        '<button class="btn btn-sm btn-outline-secondary" type="button" data-limpiar="' + inputId + '" style="background: var(--panel);" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>' +
        '</div>'
    );
    $('#' + inputId).on('keyup', function () { dt.search(this.value).draw(); });
    $('[data-limpiar="' + inputId + '"]').on('click', function () { $('#' + inputId).val(''); dt.search('').draw(); });
}

$(document).ready(function () {
    var logoBase64 = '<?php echo $logo_base64; ?>';
    var nombreEmpresa = '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>';
    var usuarioNombre = '<?php echo htmlspecialchars($user_nombre_completo); ?>';
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var periodoFiltroTexto = '<?php echo $rango_string; ?>';
    const trabajadoresData = <?php echo json_encode($trabajadores_map_js); ?>;

    /* ---------- Modal de ajuste: ficha del trabajador, importe y vista previa ---------- */
    function fmtMoneda(v) {
        return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtDias(v) {
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' días';
    }

    function obtenerTrabajadorSeleccionado() {
        var id = $('#modalTrabajadorSelect').val();
        return id ? (trabajadoresData[id] || null) : null;
    }

    // Exponer globalmente las funciones usadas por el bloque AJAX de ajustes
    window.obtenerTrabajadorSeleccionado = obtenerTrabajadorSeleccionado;
    window.fmtDias = fmtDias;
    window.fmtMoneda = fmtMoneda;

    function actualizarFichaTrabajador() {
        var t = obtenerTrabajadorSeleccionado();
        var $ficha = $('#ajusteFichaTrabajador');
        var $basica = $('#fichaSaldoBasico');
        if (!t) {
            $ficha.addClass('d-none');
            $basica.removeClass('vacio').addClass('vacio');
            $basica.html('<span class="lbl"><i class="fas fa-user-slash"></i> Seleccione un trabajador para ver su saldo</span>');
            return;
        }
        $ficha.removeClass('d-none');
        $('#fichaSaldoActual').text(fmtDias(t.saldo_acumulado));
        $('#fichaSalario').text(fmtMoneda(t.salario_mensual));
        $('#fichaSalarioHora').text(fmtMoneda(t.salario_hora) + '/h');
        $('#fichaCargo').text(t.nombre_cargo || '-');
        $('#fichaArea').text(t.nombre_area || '-');
        var estadoHtml = t.activo === 1
            ? '<span style="color:3;"><i class="fas fa-circle me-1" style="font-size:0.55rem;"></i>Activo</span>'
            : '<span style="color:#f87171;"><i class="fas fa-circle me-1" style="font-size:0.55rem;"></i>Baja</span>';
        $('#fichaEstado').html(estadoHtml);
        $basica.removeClass('vacio');
        $basica.html('<span class="lbl"><i class="fas fa-archive"></i> Saldo actual:</span>&nbsp;' + fmtDias(t.saldo_acumulado));
    }

    function actualizarVistaPrevia() {
        var t = obtenerTrabajadorSeleccionado();
        var tipo = $('#modalTipoMovimiento').val();
        var diasVal = parseFloat($('#inputDiasAjuste').val()) || 0;
        var $preview = $('#ajusteVistaPrevia');

        if (!t) {
            $preview.addClass('d-none');
            return;
        }
        $preview.removeClass('d-none');

        var saldoActual = t.saldo_acumulado;
        var delta = 0;
        if (tipo === 'disfrute') delta = -Math.abs(diasVal);
        else if (tipo === 'acumulacion') delta = Math.abs(diasVal);
        else delta = diasVal;

        var importe = 0;
        if (t.salario_mensual > 0 && diasLaborables > 0) {
            importe = (diasVal * t.salario_mensual) / diasLaborables;
        }
        $('#inputImporteAjuste').val(importe.toFixed(2));

        var resultado = saldoActual + delta;
        $('#prevSaldoActual').text(fmtDias(saldoActual));
        $('#prevEfecto').text((delta >= 0 ? '+' : '') + fmtDias(delta));
        $('#prevImporte').text(fmtMoneda(importe));
        $('#prevSaldoResultante').text(fmtDias(resultado));

        var avisoDisfrute = (tipo === 'disfrute' && diasVal > saldoActual + 0.005);
        $preview.toggleClass('aviso-disfrute', avisoDisfrute);
        $('#prevSaldoResultante').css('color', resultado < 0 ? '#f87171' : '');
    }

    function actualizarHintTipoMovimiento() {
        var tipo = $('#modalTipoMovimiento').val();
        var hints = {
            'ajuste': '<i class="fas fa-info-circle me-1"></i>Los días positivos suman y los negativos restan al saldo.',
            'acumulacion': '<i class="fas fa-info-circle me-1"></i>Se suma al saldo acumulado (apertura inicial).',
            'disfrute': '<i class="fas fa-info-circle me-1"></i>Se descuenta del saldo acumulado. El sistema valida el saldo disponible.'
        };
        $('#tipoMovimientoHint').html(hints[tipo] || '');
    }

    function recalcularModalAjuste() {
        actualizarFichaTrabajador();
        actualizarHintTipoMovimiento();
        actualizarVistaPrevia();
    }

    $('#modalTrabajadorSelect').on('change', recalcularModalAjuste);
    $('#modalTipoMovimiento').on('change', recalcularModalAjuste);
    $('#inputDiasAjuste').on('input', recalcularModalAjuste);

    function initSelect2Trabajador() {
        $('#modalTrabajadorSelect').select2({
            dropdownParent: $('#modalAjuste'),
            width: '100%',
            placeholder: '-- Seleccionar trabajador --',
            allowClear: false
        });
    }
    initSelect2Trabajador();

    // Foco automático en el buscador al abrir el desplegable (el foco del modal
    // Bootstrap puede impedir escribir en la búsqueda)
    $('#modalTrabajadorSelect').on('select2:open', function () {
        setTimeout(function () {
            var sf = document.querySelector('.select2-container--open .select2-search__field');
            if (sf) sf.focus();
        }, 60);
    });

    // Filtros Activos/Bajas y saldo: reconstruyen las opciones del combo de trabajadores
    function aplicarFiltrosTrabajador() {
        var filtroEstado = $('#modalFiltroEstadoTrabajador').val();
        var filtroSaldo = $('#modalFiltroSaldoTrabajador').val();
        var selVal = $('#modalTrabajadorSelect').val();
        var $sel = $('#modalTrabajadorSelect');
        $sel.empty().append('<option value=""></option>');
        Object.keys(trabajadoresData).forEach(function (id) {
            var t = trabajadoresData[id];
            var estadoOk = filtroEstado === 'todos'
                || (filtroEstado === 'activos' && t.activo === 1)
                || (filtroEstado === 'bajas' && t.activo !== 1);
            var saldo = parseFloat(t.saldo_acumulado) || 0;
            var saldoOk = filtroSaldo === 'todos'
                || (filtroSaldo === 'menor_uno' && saldo < 1)
                || (filtroSaldo === 'uno_o_mas' && saldo >= 1);
            if (estadoOk && saldoOk) {
                $sel.append(new Option(t.nombre_completo + ' (' + t.codigo + ')', id));
            }
        });
        if (selVal && $sel.find('option[value="' + selVal + '"]').length) {
            $sel.val(selVal);
        }
        $sel.select2('destroy');
        initSelect2Trabajador();
        $sel.trigger('change');
    }
    $('#modalFiltroEstadoTrabajador, #modalFiltroSaldoTrabajador').on('change', aplicarFiltrosTrabajador);

    $('#modalTrabajadorSelect').on('select2:select', recalcularModalAjuste);
    $('#modalTrabajadorSelect').on('select2:clear', recalcularModalAjuste);

    $('#modalAjuste').on('hidden.bs.modal', function () {
        $('#ajusteVacacionesForm')[0].reset();
        $('#modalFiltroEstadoTrabajador').val('todos');
        $('#modalFiltroSaldoTrabajador').val('todos');
        aplicarFiltrosTrabajador();
        $('#modalTrabajadorSelect').val('').trigger('change');
        recalcularModalAjuste();
    });

    /* ---------- Panel de filtros plegable ---------- */
    $('#btnToggleFiltros').on('click', function () {
        $('#filtrosPanel').slideToggle(180);
        $('#filtrosChevron').toggleClass('fa-chevron-up fa-chevron-down');
    });

    /* ---------- Auto-rellenar fechas con Año/Mes y envío ---------- */
    $('#consultaAnio, #consultaMes, #periodoDesde, #periodoHasta, #filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroTipoMovimiento').on('change', function () {
        if (this.id === 'consultaAnio' || this.id === 'consultaMes') {
            var anio = $('#consultaAnio').val();
            var mes = $('#consultaMes').val();
            var anioActual = new Date().getFullYear();
            if (mes && !anio) anio = anioActual;
            if (anio && mes) {
                $('#periodoDesde').val(anio + '-' + mes + '-01');
                $('#periodoHasta').val(new Date(Date.UTC(parseInt(anio), parseInt(mes), 0)).toISOString().slice(0, 10));
            } else if (anio) {
                $('#periodoDesde').val(anio + '-01-01');
                $('#periodoHasta').val(anio + '-12-31');
            } else {
                $('#periodoDesde').val('');
                $('#periodoHasta').val('');
            }
        }
        $('#filtroForm').submit();
    });

    /* ---------- Ocultar saldo en cero ---------- */
    $('#chkOcultarSaldoCero').on('change', function () {
        if (this.checked && $('#chkSoloBajas').is(':checked')) {
            $('#chkSoloBajas').prop('checked', false);
            $('#soloBajas').val('');
        }
        $('#ocultarSaldoCero').val(this.checked ? '1' : '');
        $('#filtroForm').submit();
    });

    /* ---------- Mostrar solo trabajadores de baja ---------- */
    function aplicarFiltroSoloBajas(activo) {
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'filtroSoloBajasSubmayor';
        });
        if (activo) {
            $.fn.dataTable.ext.search.push(function filtroSoloBajasSubmayor(settings, data, dataIndex) {
                if (settings.nTable.id !== 'tablaSaldos') return true;
                var tr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                return tr ? $(tr).attr('data-estado') === 'baja' : false;
            });
        }
    }

    $('#chkSoloBajas').on('change', function () {
        var activo = this.checked;
        if (activo) {
            if ($('#chkOcultarSaldoCero').is(':checked')) {
                $('#chkOcultarSaldoCero').prop('checked', false);
                $('#ocultarSaldoCero').val('');
            }
            $('#soloBajas').val('1');
            $('#filtroForm').submit();
            return;
        }
        $('#soloBajas').val('');
        aplicarFiltroSoloBajas(false);
        $('#tablaSaldos').DataTable().draw();
    });

    $('#btnLimpiarFiltros').on('click', function () {
        window.location.href = 'submayor_vacaciones.php';
    });

    $('#btnVerTodos').on('click', function () {
        window.location.href = 'submayor_vacaciones.php';
    });

    /* ---------- Pestañas ---------- */
    $('.tab-prof[data-tab]').on('click', function () {
        var target = $(this).data('tab');
        $('.tab-prof').removeClass('active');
        $(this).addClass('active');
        $('.tab-pane-prof').removeClass('active');
        $('#' + target).addClass('active');
        // Recalcular columnas de DataTables en el panel visible
        var $panel = $('#' + target);
        $panel.find('table.dataTable').each(function () {
            try { $(this).DataTable().columns.adjust(); } catch (e) {}
        });
        window.scrollTo({ top: $panel.offset().top - 90, behavior: 'smooth' });
    });

    /* ---------- Filtro cliente: >20 días ---------- */
    $('#filtroVacacionesExcedidas').on('change', function () {
        var activo = this.value === 'mayor20';
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'filtroVacacionesExcedidasSubmayor';
        });
        if (activo) {
            $.fn.dataTable.ext.search.push(function filtroVacacionesExcedidasSubmayor(settings, data, dataIndex) {
                if (settings.nTable.id !== 'tablaSaldos') return true;
                var tr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                return tr ? $(tr).hasClass('vacaciones-excedidas') : false;
            });
        }
        $('#tablaSaldos').DataTable().draw();
    });

    /* ---------- Filtro cliente: estado (activos / bajas) ---------- */
    $('#filtroEstadoLaboral').on('change', function () {
        var valor = this.value;
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'filtroEstadoLaboralSubmayor';
        });
        if (valor) {
            $.fn.dataTable.ext.search.push(function filtroEstadoLaboralSubmayor(settings, data, dataIndex) {
                if (settings.nTable.id !== 'tablaSaldos') return true;
                var tr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                if (!tr) return false;
                var estado = tr.getAttribute('data-estado');
                return valor === 'activos' ? estado === 'activo' : estado === 'baja';
            });
        }
        $('#tablaSaldos').DataTable().draw();
    });

    /* ---------- Filtro cliente: saldo acumulado (< 1 día / 1 día o más) ---------- */
    $('#filtroSaldoMenorDia').on('change', function () {
        var valor = this.value;
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
            return fn.name !== 'filtroSaldoMenorDiaSubmayor';
        });
        if (valor) {
            $.fn.dataTable.ext.search.push(function filtroSaldoMenorDiaSubmayor(settings, data, dataIndex) {
                if (settings.nTable.id !== 'tablaSaldos') return true;
                var tr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                if (!tr) return false;
                var saldo = parseFloat($(tr).find('.saldo-cierre').text().replace(/,/g, '')) || 0;
                return valor === 'menor_uno' ? saldo < 1 : saldo >= 1;
            });
        }
        $('#tablaSaldos').DataTable().draw();
    });

    $('#btnReporteFisico').on('click', function (e) {
        e.preventDefault();
        imprimirReporteFisico();
    });

    /* ---------- Configuración en español ---------- */
    var configSpanish = {
        "decimal": "",
        "emptyTable": "No hay datos disponibles en la tabla",
        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
        "infoEmpty": "Mostrando 0 a 0 de 0 registros",
        "infoFiltered": "(filtrado de _MAX_ registros totales)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Mostrar _MENU_ registros",
        "loadingRecords": "Cargando...",
        "processing": "Procesando...",
        "search": "Buscar:",
        "zeroRecords": "No se encontraron registros coincidentes",
        "paginate": { "first": '<i class="fas fa-step-backward"></i>', "last": '<i class="fas fa-step-forward"></i>', "next": '<i class="fas fa-chevron-right"></i>', "previous": '<i class="fas fa-chevron-left"></i>' },
        "aria": { "sortAscending": ": activar para ordenar ascendente", "sortDescending": ": activar para ordenar descendente" },
        buttons: { colvisRestore: '<i class="fas fa-undo me-1"></i>Restaurar columnas' }
    };

    /* ---------- Exportadores genéricos (Word / TXT) ---------- */
    function mostrarSwalExportando(formato) {
        Swal.fire({
            title: '<i class="fas fa-file-export me-2" style="color:#60a5fa;"></i> Exportando a: ' + formato,
            text: 'Se está generando el archivo, por favor espere...',
            icon: 'info',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            allowOutsideClick: true
        });
    }

    $('#tablaSaldos, #tablaMovimientos, #tablaDetalleIndividual').on('buttons-action.dt', function (e, buttonApi, dataTables, node, config) {
        var cn = config.className || '';
        var formato = '';
        if (cn.indexOf('buttons-pdf') !== -1) formato = 'PDF';
        else if (cn.indexOf('buttons-excel') !== -1) formato = 'Excel';
        else if (cn.indexOf('buttons-csv') !== -1) formato = 'CSV';
        else if (cn.indexOf('buttons-print') !== -1) formato = 'Impresión';
        if (formato) mostrarSwalExportando(formato);
    });

    function exportarWordDesdeTabla(dt, titulo, colsOmitir) {
        mostrarSwalExportando('Word');
        var esIndividual = (dt.table().node().id === 'tablaDetalleIndividual');
        var wNombre = '<?php echo htmlspecialchars(mb_strtoupper($resumen_trabajador['nombre_completo'] ?? '', 'UTF-8'), ENT_QUOTES); ?>';
        var wCodigo = '<?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? '', ENT_QUOTES); ?>';
        var wArea = '<?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? '', ENT_QUOTES); ?>';
        var wCargo = '<?php echo htmlspecialchars($resumen_trabajador['cargo'] ?? '', ENT_QUOTES); ?>';
        var wSalario = '<?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?>';
        var $clonedTable = $(dt.table().node()).clone();
        if (colsOmitir && colsOmitir.length) {
            $clonedTable.find('tr').each(function () {
                var $cells = $(this).find('th, td');
                var $quitar = $();
                colsOmitir.forEach(function (ci) {
                    var el = $cells.eq(ci);
                    if (el.length) $quitar = $quitar.add(el);
                });
                $quitar.remove();
            });
        }
        var htmlContent = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8"><style>
            @page WordSection1 { size: 792pt 612pt; mso-page-orientation: landscape; margin-top:0.5in; margin-bottom:0.4in; margin-left:0.4in; margin-right:0.4in; }
            div.WordSection1 { page: WordSection1; }
            body { font-family: Arial, sans-serif; font-size:10pt; color:#000000; }
            .encabezado { width:100%; border:none; border-collapse:collapse; margin-bottom:0.9375rem; }
            .encabezado td { border:none !important; padding:0; background:#ffffff !important; vertical-align:middle; }
            .encabezado img { width:3cm !important; height:3cm !important; }
            .empresa { color:#004B87; margin:0; }
            .subtitulo { color:#444; margin:0.25rem 0 0 0; }
            .meta { color:#004B87; font-size:9pt; margin:0.125rem 0 0 0; }
            .metadatos { color:#555; font-size:9pt; }
            table { border-collapse: collapse; width:100%; }
            th, td { border: 0.0625rem solid #000000; padding:0.375rem; }
            th { background-color: #e8e8e8; color: #000000; text-align: center !important; }
            td { background-color: #ffffff; color: #000000; }
            .c-num, .c-center { text-align: center !important; }
            .c-left { text-align: left !important; }
            tfoot td { background-color:#f0f0f0; font-weight:bold; color:#000000; text-align:center !important; }
            .firmas { width:100%; margin-top:2.5rem; border:none; }
            .firmas td { border:none; width:33%; text-align:center; vertical-align:bottom; padding-top:2.5rem; }
            .firma-linea { border-bottom:0.0625rem solid #000000; width:85%; margin:0 auto 0.75rem auto; }
        </style></head><body>
        <div class="WordSection1">
            <table class="encabezado">
                <tr>
                    <td style="width:3cm;">
                        ${logoBase64 ? `<img src="${logoBase64}" alt="Logo" width="113" height="113" style="width:3cm; height:3cm;">` : ''}
                    </td>
                    <td style="padding-left:0.9375rem;">
                        <h2 class="empresa">${nombreEmpresa}</h2>
                        <h3 class="subtitulo">${titulo}</h3>
                        <p class="meta">Período consultado: <strong>${periodoFiltroTexto}</strong></p>
                        ${esIndividual ? `<p class="meta"><strong>Trabajador:</strong> ${wNombre} &nbsp;|&nbsp; <strong>Código / Expediente:</strong> ${wCodigo} &nbsp;|&nbsp; <strong>Área:</strong> ${wArea}</p><p class="meta"><strong>Cargo:</strong> ${wCargo} &nbsp;|&nbsp; <strong>Salario Escala:</strong> ${wSalario}</p>` : ''}
                    </td>
                </tr>
            </table>
            <p class="metadatos">Generado por: ${usuarioNombre} &nbsp;|&nbsp; Fecha: ${new Date().toLocaleDateString('es-ES')} ${new Date().toLocaleTimeString('es-ES')}</p>
            <hr>
            ${$clonedTable[0].outerHTML}
            <table class="firmas">
                <tr>
                    <td><p class="firma-linea">&nbsp;</p><strong>Elaborado por:</strong><br>${globalEspecialistaRRHH || usuarioNombre}<br><span style="font-size:7pt;">(Especialista de Recursos Humanos)</span></td>
                    <td><p class="firma-linea">&nbsp;</p><strong>Revisado por:</strong><br>${globalEspecialistaGestion}<br><span style="font-size:7pt;">(Especialista de Gestión)</span></td>
                    <td><p class="firma-linea">&nbsp;</p><strong>Aprobado por:</strong><br>${globalJefeProyecto}<br><span style="font-size:7pt;">(Jefe de Proyecto)</span></td>
                </tr>
            </table>
        </div>
        </body></html>`;
        var blob = new Blob(['\ufeff' + htmlContent], { type: 'application/msword' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Submayor_Vacaciones_' + titulo.replace(/\s+/g, '_') + '.doc';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function exportarTxtDesdeTabla(dt, titulo, colsOmitir) {
        mostrarSwalExportando('TXT');
        var colSet = {};
        (colsOmitir || []).forEach(function (ci) { colSet[ci] = true; });
        var numCols = dt.columns().count();
        var txt = "SUBMAYOR DE VACACIONES\n";
        txt += "Empresa: " + nombreEmpresa + "\n";
        txt += "Título: " + titulo + "\n";
        txt += "Período: " + periodoFiltroTexto + "\n";
        txt += "Generado por: " + usuarioNombre + "\n\n";
        txt += "Firmas:\n";
        txt += "Elaborado por: " + (globalEspecialistaRRHH || usuarioNombre) + "\n";
        txt += "Revisado por: " + globalEspecialistaGestion + "\n";
        txt += "Aprobado por: " + globalJefeProyecto + "\n\n";
        var headers = [];
        $(dt.table().header()).find('th').each(function (idx) {
            if (!colSet[idx]) headers.push($(this).text().trim());
        });
        txt += headers.join("\t") + "\n";
        dt.rows({ search: 'applied' }).every(function () {
            var rowData = this.data();
            var rowArray = [];
            for (var i = 0; i < numCols; i++) {
                if (!colSet[i]) rowArray.push(String(rowData[i]).replace(/<[^>]*>/g, '').trim());
            }
            txt += rowArray.join("\t") + "\n";
        });
        var blob = new Blob(['\ufeff' + txt], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Submayor_Vacaciones_' + titulo.replace(/\s+/g, '_') + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function buscarTablaPdf(objs) {
        for (var i = 0; i < objs.length; i++) {
            if (objs[i] && objs[i].table) {
                if (objs[i].table.headerRows) return objs[i];
                if (!objs[i].layout || objs[i].layout !== 'noBorders') return objs[i];
            }
            if (objs[i] && objs[i].stack) {
                var r = buscarTablaPdf(objs[i].stack);
                if (r) return r;
            }
        }
        return null;
    }

    function crearFilaCabeceraTopPdf(srcThead, colsExportar) {
        var out = { fila: [], blank: {} };
        var row1 = srcThead ? srcThead.querySelector('tr') : null;
        if (!row1) return out;
        var pos = 0;
        Array.prototype.forEach.call(row1.children, function (th) {
            var cs = parseInt(th.getAttribute('colspan'), 10) || 1;
            var rs = parseInt(th.getAttribute('rowspan'), 10) || 1;
            var label = (th.textContent || '').replace(/\s+/g, ' ').trim();
            var covered = [];
            for (var c = 0; c < cs; c++) covered.push(pos + c);
            pos += cs;
            var firstExp = -1;
            var anyExp = false;
            covered.forEach(function (col) {
                if (colsExportar.indexOf(col) !== -1) {
                    anyExp = true;
                    if (firstExp === -1) firstExp = colsExportar.indexOf(col);
                }
            });
            if (!anyExp) return;
            while (out.fila.length < firstExp) out.fila.push({ text: '', style: 'tableHeader', alignment: 'center' });
            var cell = { text: label, style: 'tableHeader', alignment: 'center' };
            if (cs > 1) cell.colSpan = cs;
            out.fila.push(cell);
            for (var k = 1; k < cs; k++) out.fila.push({ text: '', style: 'tableHeader', alignment: 'center' });
            if (rs > 1 && cs === 1) {
                covered.forEach(function (col) {
                    if (colsExportar.indexOf(col) !== -1) out.blank[colsExportar.indexOf(col)] = true;
                });
            }
        });
        return out;
    }

    function crearBotonesDataTable(colsExportar, colsNumericas, tituloExport, colsOmitir, colsCentrar, colExpediente, tipoImpresion) {
        tipoImpresion = tipoImpresion || 'consolidado';
        var IND_NOMBRE = '<?php echo htmlspecialchars(mb_strtoupper($resumen_trabajador['nombre_completo'] ?? '', 'UTF-8'), ENT_QUOTES); ?>';
        var IND_CODIGO = '<?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? '', ENT_QUOTES); ?>';
        var IND_AREA = '<?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? '', ENT_QUOTES); ?>';
        var IND_CARGO = '<?php echo htmlspecialchars($resumen_trabajador['cargo'] ?? '', ENT_QUOTES); ?>';
        var IND_SALARIO = '<?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?>';

        function alineacionExport(dt) {
            var map = {};
            var out = {};
            try {
                var $tabla = $(dt.table().node());
                var col = 0;
                $tabla.find('thead tr:first-child th').each(function () {
                    var $th = $(this);
                    var span = $th.attr('colspan') ? parseInt($th.attr('colspan'), 10) : 1;
                    var cls = $th.attr('class') || '';
                    var algn = null;
                    if (/\bc-center\b/.test(cls)) algn = 'center';
                    else if (/\bc-left\b/.test(cls)) algn = 'left';
                    for (var k = 0; k < span; k++) { map[col] = algn; col++; }
                });
                colsExportar.forEach(function (srcIdx, expIdx) {
                    if (map[srcIdx]) out[expIdx] = map[srcIdx];
                });
            } catch (e) {}
            return out;
        }
        var botones = [
            {
                extend: 'print',
                text: '<i class="fas fa-print me-2" style="color:#fbbf24;"></i> Imprimir',
                className: 'btn-win-sm',
                title: '',
                footer: true,
                exportOptions: { columns: colsExportar },
                customize: function (win, opts, dt) {
                    win._origPrint = win.print.bind(win);
                    win._origClose = win.close.bind(win);
                    win.print = function () {};
                    win.close = function () {};
                    var styleElement = win.document.createElement('style');
                    styleElement.type = 'text/css';
                    styleElement.innerHTML = `
                        @page { size: Letter landscape; margin: 0; }
                        *, *::before, *::after { box-sizing: border-box; }
                        body { margin: 0; background:#ffffff !important; color:#000000 !important; font-family:Arial, sans-serif !important; font-size:10pt !important; }
                        .page-sheet { width: 279.4mm; height: 215.9mm; padding: 12mm 10mm 20mm 10mm; position: relative; background: #ffffff; page-break-after: always; overflow: hidden; }
                        .page-sheet.last { page-break-after: auto; }
                        @media print { .page-sheet { height: 100vh; } }
                        @media print { .no-print { display: none !important; } }
                        #auto-hide-toolbar.hidden { transform: translateY(-100%); }
                        .print-footer { position: absolute; bottom: 10mm; left: 10mm; right: 10mm; border-top: 0.0625rem solid #bbb; padding-top: 0.3125rem; font-size: 7.5pt; color: #555; display: flex; justify-content: space-between; }
                        .dtp-header { padding-bottom: 0.0625rem; }
                        .dtp-sig { padding-top: 0.0625rem; padding-bottom: 0.0625rem; }
                        table { width:100% !important; border-collapse:collapse !important; background:#fff !important; color:#000 !important; margin-top: 0.5rem; margin-bottom: 0.5rem; }
                        table thead th, th { background:#e8e8e8 !important; color:#000 !important; border:0.0625rem solid #000 !important; font-weight:bold !important; text-align:center !important; white-space:normal !important; }
                        th span { display:block !important; }
                        td { background:#ffffff !important; color:#000 !important; border:0.0625rem solid #000 !important; padding:0.375rem 0.5rem !important; }
                        .c-num, .c-center { text-align:center !important; }
                        .c-left { text-align:left !important; }
                        .total-row td, tfoot td, tfoot th { background:#f0f0f0 !important; font-weight:bold !important; color:#000 !important; text-align:center !important; }
                        .dtp-sig-table { border:none !important; margin:0 !important; }
                        .dtp-sig-table td { border:none !important; padding:2.5rem 0.5rem 0 0.5rem !important; text-align:center !important; vertical-align:bottom !important; }
                        .dtp-sig-table .sig-line { border-top: 0.0625rem solid #000000; width: 85%; margin: 0 auto 0.3125rem auto; }
                    `;
                    win.document.head.appendChild(styleElement);
                    var fFecha = new Date().toLocaleDateString('es-ES');
                    var fHora = new Date().toLocaleTimeString('es-ES');

                    var headerHtml = '<div class="dtp-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:0.125rem solid #004B87; padding-bottom:0.9375rem; margin-bottom:0;">';
                    headerHtml += '<div style="display:flex; align-items:center;">';
                    if (logoBase64) headerHtml += '<img src="' + logoBase64 + '" style="width:6.25rem; height:2.8125rem; object-fit:contain; margin-right:1.25rem;">';
                    headerHtml += '<div>';
                    headerHtml += '<h2 style="color:#004B87; margin:0;">' + nombreEmpresa + '</h2>';
                    if (tipoImpresion === 'individual') {
                        headerHtml += '<h4 style="color:#444; margin:0.3125rem 0 0 0;">SUBMAYOR DE VACACIONES - LIBRO AUXILIAR INDIVIDUAL</h4>';
                        headerHtml += '<p style="color:#004B87; margin:0.1875rem 0 0 0; font-size:0.625rem;">Período consultado: <strong>' + periodoFiltroTexto + '</strong></p>';
                        headerHtml += '<div style="margin-top:0.5rem; font-size:0.625rem; color:#222; line-height:1.5;">';
                        headerHtml += '<strong>Trabajador:</strong> ' + IND_NOMBRE + '<br>';
                        headerHtml += '<strong>Código / Expediente:</strong> ' + IND_CODIGO + ' &nbsp;|&nbsp; <strong>Área:</strong> ' + IND_AREA + '<br>';
                        headerHtml += '<strong>Cargo:</strong> ' + IND_CARGO + ' &nbsp;|&nbsp; <strong>Salario Escala:</strong> ' + IND_SALARIO;
                        headerHtml += '</div>';
                    } else if (tipoImpresion === 'movimientos') {
                        headerHtml += '<h4 style="color:#444; margin:0.3125rem 0 0 0;">SUBMAYOR DE VACACIONES - MOVIMIENTOS</h4>';
                        headerHtml += '<p style="color:#004B87; margin:0.1875rem 0 0 0; font-size:0.625rem;">Período consultado: <strong>' + periodoFiltroTexto + '</strong></p>';
                    } else {
                        headerHtml += '<h4 style="color:#444; margin:0.3125rem 0 0 0;">SUBMAYOR DE VACACIONES - CONSOLIDADO</h4>';
                        headerHtml += '<p style="color:#004B87; margin:0.1875rem 0 0 0; font-size:0.625rem;">Período consultado: <strong>' + periodoFiltroTexto + '</strong></p>';
                    }
                    headerHtml += '</div>';
                    headerHtml += '</div>';
                    headerHtml += '<div style="text-align:right; font-size:0.6875rem; color:#555;">Generado por: ' + usuarioNombre + '<br>Fecha: ' + fFecha + ' ' + fHora + '</div>';
                    headerHtml += '</div>';

                    var signaturesHtml = '<div class="dtp-sig" style="padding-top:3.125rem;">';
                    signaturesHtml += '<table class="dtp-sig-table" style="width:100%;">';
                    signaturesHtml += '<tr>';
                    signaturesHtml += '<td><div class="sig-line"></div><strong>Elaborado por:</strong><br>' + (globalEspecialistaRRHH || usuarioNombre) + '<br><span style="font-size:7pt;">(Especialista de Recursos Humanos)</span></td>';
                    signaturesHtml += '<td><div class="sig-line"></div><strong>Revisado por:</strong><br>' + globalEspecialistaGestion + '<br><span style="font-size:7pt;">(Especialista de Gestión)</span></td>';
                    signaturesHtml += '<td><div class="sig-line"></div><strong>Aprobado por:</strong><br>' + globalJefeProyecto + '<br><span style="font-size:7pt;">(Jefe de Proyecto)</span></td>';
                    signaturesHtml += '</tr>';
                    signaturesHtml += '</table>';
                    signaturesHtml += '</div>';

                    var $winTbl = $(win.document).find('table').first();
                    if ($winTbl.length) $winTbl.attr('id', 'dtpTable');

                    var srcNode = dt.table().node();
                    var srcThead = srcNode ? srcNode.querySelector('thead') : null;
                    if (srcThead && srcThead.querySelectorAll('tr').length > 1) {
                        $winTbl.find('thead').remove();
                        var $cl = $(srcThead).clone(true);
                        $cl.find('tr').removeAttr('style');
                        $cl.find('th').removeAttr('style').removeAttr('aria-controls').removeAttr('aria-describedby');
                        $cl.find('.dataTables_sizing').each(function () {
                            $(this).replaceWith($(this).contents());
                        });
                        $winTbl.prepend($cl);
                    }

                    $(win.document).find('tfoot tr').each(function () {
                        var $celdas = $(this).find('th');
                        if ($celdas.length > 5) {
                            var texto0 = $celdas.eq(0).text().trim();
                            var texto1 = $celdas.eq(1).text().trim();
                            if (texto0 && texto0 === texto1) {
                                $celdas.eq(0).attr('colspan', '5');
                                $celdas.slice(1, 5).remove();
                            }
                        }
                    });

                    var $pgRoot = $('<div id="pgRoot" style="width:279.4mm; padding:12mm 10mm 20mm 10mm; box-sizing:border-box;"></div>');
                    $pgRoot.append(headerHtml);
                    if ($winTbl.length) $pgRoot.append($winTbl);
                    $pgRoot.append(signaturesHtml);
                    $(win.document.body).prepend($pgRoot);

                    paginarImpresion(win.document, {
                        headerSel: '.dtp-header',
                        tableSel: '#dtpTable',
                        sigSel: '.dtp-sig',
                        footerLeft: nombreEmpresa + ' - Submayor de Vacaciones',
                        tableTop: 8,
                        tableBottom: 8,
                        safety: 8
                    });

                    var toolbarHtml = '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;">';
                    toolbarHtml += '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>';
                    toolbarHtml += '<button onclick="window._origPrint();window._origClose()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">🖨️ Imprimir</button>';
                    toolbarHtml += '<button onclick="window._origClose()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">✖ Cerrar</button>';
                    toolbarHtml += '';
                    toolbarHtml += '</div>';
                    $(win.document.body).prepend(toolbarHtml);

                    var ahScript = win.document.createElement('script');
                    ahScript.textContent = '(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();';
                    win.document.body.appendChild(ahScript);
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf me-2" style="color:#f87171;"></i> PDF',
                className: 'btn-win-sm',
                orientation: 'landscape',
                pageSize: 'LETTER',
                title: '',
                footer: true,
                exportOptions: { columns: colsExportar },
                customize: function (doc, cfg, dt) {
                    doc.pageMargins = [30, 36, 30, 40];
                    doc.styles.tableHeader = { fontSize: 8, bold: true, color: '#000000', fillColor: '#e8e8e8', alignment: 'center' };
                    doc.styles.tableBody = { fontSize: 7, color: '#000000' };
                    doc.defaultStyle = { fontSize: 7, color: '#000000' };
                    var logoImg = '<?php echo base64_encode(file_get_contents('../../images/logotn.png')); ?>';
                    doc.content.splice(0, 0, {
                        columns: [
                            { width: '*', text: '' },
                            {
                                width: 'auto',
                                columns: [
                                    { width: 'auto', image: 'data:image/png;base64,' + logoImg, fit: [80, 45], alignment: 'center', margin: [0, 0, 15, 0] },
                                    { width: 'auto', text: nombreEmpresa + '\nREPORTE DE VACACIONES ACUMULADAS', alignment: 'left', fontSize: 14, bold: true, valign: 'middle' }
                                ]
                            },
                            { width: '*', text: '' }
                        ],
                        margin: [0, 0, 0, 15]
                    });
                    if (tipoImpresion === 'individual') {
                        doc.content.splice(1, 0, {
                            stack: [
                                { text: 'LIBRO AUXILIAR INDIVIDUAL', bold: true, fontSize: 11, alignment: 'center', margin: [0, 0, 0, 2] },
                                { text: 'Período consultado: ' + periodoFiltroTexto, fontSize: 8, alignment: 'center', margin: [0, 0, 0, 4] },
                                { text: 'Trabajador: ' + IND_NOMBRE + '    |    Código / Expediente: ' + IND_CODIGO + '    |    Área: ' + IND_AREA + '    |    Cargo: ' + IND_CARGO + '    |    Salario Escala: ' + IND_SALARIO, fontSize: 8, alignment: 'center', margin: [0, 0, 0, 8] }
                            ]
                        });
                    }
                    var tablaPdf = buscarTablaPdf(doc.content);
                    if (tablaPdf && tablaPdf.table && tablaPdf.table.body) {
                        var alignMap = alineacionExport(dt);
                        var srcNode = dt.table().node();
                        var srcThead = srcNode ? srcNode.querySelector('thead') : null;
                        var top = { fila: null, blank: {} };
                        if (srcThead && srcThead.querySelectorAll('tr').length > 1) {
                            top = crearFilaCabeceraTopPdf(srcThead, colsExportar);
                        }
                        var hasTop = top.fila && top.fila.length > 0;
                        if (hasTop) tablaPdf.table.body.splice(0, 0, top.fila);
                        var headerIdx = hasTop ? 1 : 0;
                        tablaPdf.table.headerRows = hasTop ? 2 : 1;
                        var filaHeader = tablaPdf.table.body[headerIdx];
                        if (filaHeader) {
                            filaHeader.forEach(function (cel, ci) {
                                if (top.blank[ci] && filaHeader[ci] !== undefined && filaHeader[ci] !== null) {
                                    filaHeader[ci] = { text: '', style: 'tableHeader', alignment: 'center' };
                                }
                            });
                            for (var hci in alignMap) {
                                if (!alignMap.hasOwnProperty(hci)) continue;
                                var hc = parseInt(hci, 10);
                                var hv = filaHeader[hc];
                                if (hv === undefined || hv === null) continue;
                                if (typeof hv === 'string') filaHeader[hc] = { text: hv };
                                filaHeader[hc].alignment = alignMap[hci];
                                filaHeader[hc].style = 'tableHeader';
                            }
                        }
                        tablaPdf.table.body.forEach(function (fila, fi) {
                            if (fi <= headerIdx) return;
                            for (var ci in alignMap) {
                                if (!alignMap.hasOwnProperty(ci)) continue;
                                var c = parseInt(ci, 10);
                                if (fila[c] === undefined || fila[c] === null) continue;
                                var celda = fila[c];
                                var texto = (typeof celda === 'object' && celda.text !== undefined) ? celda.text : celda;
                                var nueva = { text: String(texto), alignment: alignMap[ci] };
                                if (typeof celda === 'object' && celda.style) nueva.style = celda.style;
                                fila[c] = nueva;
                            }
                        });
                        var cuerpoPdf = tablaPdf.table.body;
                        var filaTotal = cuerpoPdf[cuerpoPdf.length - 1];
                        if (filaTotal && filaTotal.length > 1 &&
                            filaTotal[0] && typeof filaTotal[0] === 'object' && filaTotal[0].style === 'tableFooter') {
                            var etiqueta = filaTotal[0].text;
                            if (typeof filaTotal[1] === 'object' && filaTotal[1].text === etiqueta) {
                                filaTotal[0].colSpan = 5;
                                filaTotal[0].alignment = 'center';
                                for (var li = 1; li <= 4; li++) filaTotal[li] = { text: '', style: 'tableFooter' };
                            }
                            for (var lj = 0; lj < filaTotal.length; lj++) {
                                if (filaTotal[lj] && typeof filaTotal[lj] === 'object') filaTotal[lj].alignment = 'center';
                            }
                        }
                    }
                    doc.content.push({ text: '\n\n', margin: [0, 30, 0, 0] });
                    doc.content.push({
                        columns: [
                            { text: '', width: '*' },
                            { alignment: 'center', stack: [{ text: '_________________________', alignment: 'center' }, { text: globalEspecialistaRRHH || globalUsuarioNombre, alignment: 'center', bold: true }, { text: 'Elaborado por', alignment: 'center', fontSize: 7 }], width: '30%' },
                            { alignment: 'center', stack: [{ text: '_________________________', alignment: 'center' }, { text: globalEspecialistaGestion, alignment: 'center', bold: true }, { text: 'Revisado por', alignment: 'center', fontSize: 7 }], width: '30%' },
                            { alignment: 'center', stack: [{ text: '_________________________', alignment: 'center' }, { text: globalJefeProyecto, alignment: 'center', bold: true }, { text: 'Aprobado por', alignment: 'center', fontSize: 7 }], width: '30%' },
                            { text: '', width: '*' }
                        ]
                    });
                }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-2" style="color:#22c55e;"></i> Excel',
                className: 'btn-win-sm',
                title: nombreEmpresa + ' - Submayor de Vacaciones - ' + periodoFiltroTexto,
                footer: true,
                exportOptions: { columns: colsExportar },
                customize: function (p, cfg, dt) {
                    try {
                        var XN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                        var stylesDoc = p.xl['styles.xml'];
                        var xfs = stylesDoc.getElementsByTagName('cellXfs')[0];
                        var xfList = xfs.getElementsByTagName('xf');
                        var centerIdx = null, leftIdx = null;
                        var mapNumCenter = {};
                        var headerCenterIdx = null, headerLeftIdx = null;
                        for (var i = 0; i < xfList.length; i++) {
                            var xf = xfList[i];
                            var al = xf.getElementsByTagName('alignment')[0];
                            if (al && (xf.getAttribute('numFmtId') || '0') === '0') {
                                if (al.getAttribute('horizontal') === 'center' && centerIdx === null) centerIdx = i;
                                else if (al.getAttribute('horizontal') === 'left' && leftIdx === null) leftIdx = i;
                            }
                        }
                        var xfHeader = xfList[2];
                        function cloneXfAlign(src, hor) {
                            var nx = src.cloneNode(true);
                            var oldAl = nx.getElementsByTagName('alignment');
                            for (var j = oldAl.length - 1; j >= 0; j--) nx.removeChild(oldAl[j]);
                            var nAl = stylesDoc.createElementNS(XN, 'alignment');
                            nAl.setAttribute('horizontal', hor);
                            nx.setAttribute('applyAlignment', '1');
                            nx.appendChild(nAl);
                            xfs.appendChild(nx);
                            return xfs.getElementsByTagName('xf').length - 1;
                        }
                        if (xfHeader) {
                            headerCenterIdx = cloneXfAlign(xfHeader, 'center');
                            headerLeftIdx = cloneXfAlign(xfHeader, 'left');
                        }
                        for (var ns = 56; ns <= 67; ns++) {
                            if (ns >= xfList.length) break;
                            var nx = xfList[ns].cloneNode(true);
                            var oldAl = nx.getElementsByTagName('alignment');
                            for (var j = oldAl.length - 1; j >= 0; j--) nx.removeChild(oldAl[j]);
                            var nAl = stylesDoc.createElementNS(XN, 'alignment');
                            nAl.setAttribute('horizontal', 'center');
                            nx.setAttribute('applyAlignment', '1');
                            nx.appendChild(nAl);
                            xfs.appendChild(nx);
                            mapNumCenter[ns] = xfs.getElementsByTagName('xf').length - 1;
                        }
                        var cCount = parseInt(xfs.getAttribute('count'), 10);
                        if (!isNaN(cCount)) xfs.setAttribute('count', String(cCount + 14));
                        var alignMap = alineacionExport(dt);
                        var sheet = p.xl.worksheets['sheet1.xml'];
                        var rows = sheet.getElementsByTagName('row');
                        function colLetra(idx) {
                            var s = '';
                            idx++;
                            while (idx > 0) {
                                var m = (idx - 1) % 26;
                                s = String.fromCharCode(65 + m) + s;
                                idx = Math.floor((idx - 1) / 26);
                            }
                            return s;
                        }
                        var topRow = null;
                        var srcNodeE = dt.table().node();
                        var srcTheadE = srcNodeE ? srcNodeE.querySelector('thead') : null;
                        if (srcTheadE && srcTheadE.querySelectorAll('tr').length > 1) {
                            topRow = crearFilaCabeceraTopPdf(srcTheadE, colsExportar);
                        }
                        var hasTop = topRow && topRow.fila && topRow.fila.length > 0;
                        if (hasTop) {
                            var newRowE = sheet.createElementNS(XN, 'row');
                            var newRowNumE = 2;
                            newRowE.setAttribute('r', String(newRowNumE));
                            var oldRowsE = Array.prototype.slice.call(rows);
                            for (var sri = 1; sri < oldRowsE.length; sri++) {
                                var oldR = parseInt(oldRowsE[sri].getAttribute('r'), 10);
                                if (!isNaN(oldR)) {
                                    oldRowsE[sri].setAttribute('r', String(oldR + 1));
                                    var rowCellsE = oldRowsE[sri].getElementsByTagName('c');
                                    for (var rci = 0; rci < rowCellsE.length; rci++) {
                                        var ref = rowCellsE[rci].getAttribute('r');
                                        if (ref) rowCellsE[rci].setAttribute('r', ref.replace(/(\d+)$/, String(oldR + 1)));
                                    }
                                }
                            }
                            var mergesE = [];
                            topRow.fila.forEach(function (cel, ci) {
                                var cell = sheet.createElementNS(XN, 'c');
                                cell.setAttribute('r', colLetra(ci) + newRowNumE);
                                cell.setAttribute('t', 'inlineStr');
                                var algnT = alignMap[ci];
                                cell.setAttribute('s', String((algnT === 'left' && headerLeftIdx !== null) ? headerLeftIdx : headerCenterIdx));
                                var isN = sheet.createElementNS(XN, 'is');
                                var tN = sheet.createElementNS(XN, 't');
                                tN.textContent = cel.text || '';
                                isN.appendChild(tN);
                                cell.appendChild(isN);
                                newRowE.appendChild(cell);
                                if (cel.colSpan > 1) mergesE.push([ci, ci + cel.colSpan - 1]);
                            });
                            if (oldRowsE.length > 0) {
                                var sheetDataE = sheet.getElementsByTagName('sheetData')[0];
                                if (sheetDataE) sheetDataE.insertBefore(newRowE, oldRowsE[1]);
                                else sheet.insertBefore(newRowE, oldRowsE[1]);
                            }
                            if (mergesE.length) {
                                var mc = sheet.getElementsByTagName('mergeCells')[0];
                                if (!mc) {
                                    mc = sheet.createElementNS(XN, 'mergeCells');
                                    var wsRoot = sheet.getElementsByTagName('worksheet')[0];
                                    var sheetDataE = sheet.getElementsByTagName('sheetData')[0];
                                    if (wsRoot && sheetDataE && sheetDataE.nextSibling) wsRoot.insertBefore(mc, sheetDataE.nextSibling);
                                    else if (wsRoot) wsRoot.appendChild(mc);
                                }
                                var mcCount = parseInt(mc.getAttribute('count'), 10) || 0;
                                mergesE.forEach(function (mg) {
                                    var mcCell = sheet.createElementNS(XN, 'mergeCell');
                                    mcCell.setAttribute('ref', colLetra(mg[0]) + newRowNumE + ':' + colLetra(mg[1]) + newRowNumE);
                                    mc.appendChild(mcCell);
                                });
                                mc.setAttribute('count', String(mcCount + mergesE.length));
                            }
                        }
                        var expIdx = (colExpediente !== null && colExpediente !== undefined) ? colsExportar.indexOf(colExpediente) : -1;
                        var headerRowIdx = hasTop ? 2 : 1;
                        if (rows.length > headerRowIdx) {
                            var hCells = rows[headerRowIdx].getElementsByTagName('c');
                            for (var hci = 0; hci < hCells.length; hci++) {
                                var hAlgn = alignMap[hci];
                                if (hasTop && topRow.blank[hci]) {
                                    var hIs = hCells[hci].getElementsByTagName('is');
                                    for (var hk = hIs.length - 1; hk >= 0; hk--) hCells[hci].removeChild(hIs[hk]);
                                    var hV = hCells[hci].getElementsByTagName('v');
                                    for (var hv2 = hV.length - 1; hv2 >= 0; hv2--) hCells[hci].removeChild(hV[hv2]);
                                }
                                if (hAlgn === 'center' && headerCenterIdx !== null) hCells[hci].setAttribute('s', String(headerCenterIdx));
                                else if (hAlgn === 'left' && headerLeftIdx !== null) hCells[hci].setAttribute('s', String(headerLeftIdx));
                            }
                        }
                        for (var ri = (hasTop ? 3 : 2); ri < rows.length; ri++) {
                            var cells = rows[ri].getElementsByTagName('c');
                            for (var ci = 0; ci < cells.length; ci++) {
                                var cell = cells[ci];
                                if (ci === expIdx) {
                                    var vNode = cell.getElementsByTagName('v')[0];
                                    var hasIs = cell.getElementsByTagName('is').length > 0;
                                    if (vNode && !hasIs) {
                                        var val = vNode.textContent || '';
                                        cell.removeChild(vNode);
                                        cell.setAttribute('t', 'inlineStr');
                                        var isNode = stylesDoc.createElementNS(XN, 'is');
                                        var tNode = stylesDoc.createElementNS(XN, 't');
                                        tNode.textContent = val;
                                        isNode.appendChild(tNode);
                                        cell.appendChild(isNode);
                                    }
                                }
                                var algn = alignMap[ci];
                                var sAttr = null;
                                if (algn === 'center') {
                                    var curS = cell.getAttribute('s');
                                    if (curS !== null && mapNumCenter[curS] !== undefined) sAttr = mapNumCenter[curS];
                                    else if (centerIdx !== null) sAttr = centerIdx;
                                } else if (algn === 'left' && leftIdx !== null) {
                                    sAttr = leftIdx;
                                }
                                if (sAttr !== null) cell.setAttribute('s', String(sAttr));
                            }
                        }
                        function textoCeldaExcel(cell) {
                            var tn = cell.getElementsByTagName('t')[0];
                            if (tn) return tn.textContent || '';
                            var vn = cell.getElementsByTagName('v')[0];
                            if (vn) return vn.textContent || '';
                            return '';
                        }
                        var filaFinal = rows[rows.length - 1];
                        if (filaFinal) {
                            var fCells = filaFinal.getElementsByTagName('c');
                            if (fCells.length > 5 && textoCeldaExcel(fCells[0]) !== '' && textoCeldaExcel(fCells[0]) === textoCeldaExcel(fCells[1])) {
                                for (var bi = 1; bi <= 4; bi++) {
                                    var isN = fCells[bi].getElementsByTagName('is')[0];
                                    if (isN) fCells[bi].removeChild(isN);
                                    var vDel = fCells[bi].getElementsByTagName('v')[0];
                                    if (vDel) fCells[bi].removeChild(vDel);
                                    var tDel = fCells[bi].getElementsByTagName('t')[0];
                                    if (tDel) fCells[bi].removeChild(tDel);
                                }
                            }
                        }
                    } catch (e) {}
                }
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv me-2" style="color:#22d3ee;"></i> CSV',
                className: 'btn-win-sm',
                footer: true,
                exportOptions: { columns: colsExportar }
            },
            {
                text: '<i class="fas fa-file-word me-2" style="color:#60a5fa;"></i> Word',
                className: 'btn-win-sm buttons-word',
                action: function (e, dt) {
                    exportarWordDesdeTabla(dt, tituloExport, colsOmitir);
                }
            },
            {
                text: '<i class="fas fa-file-alt me-2" style="color:#a78bfa;"></i> TXT',
                className: 'btn-win-sm buttons-txt',
                action: function (e, dt) {
                    exportarTxtDesdeTabla(dt, tituloExport, colsOmitir);
                }
            }
        ];
        return [
            {
                text: '<i class="fas fa-step-backward me-1"></i>',
                className: 'btn-win-sm buttons-first',
                titleAttr: 'Ir al primer registro',
                action: function (e, dt) {
                    dt.page('first').draw(false);
                }
            },
            {
                text: '<i class="fas fa-step-forward me-1"></i>',
                className: 'btn-win-sm buttons-last',
                titleAttr: 'Ir al último registro',
                action: function (e, dt) {
                    dt.page('last').draw(false);
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns me-1"></i> Columnas',
                className: 'btn-win-sm buttons-colvis',
                postfixButtons: ['colvisRestore']
            },
            {
                extend: 'collection',
                text: '<i class="fas fa-cog me-1"></i> Opciones',
                className: 'btn-win-sm',
                buttons: botones
            }
        ];
    }

    /* ---------- DataTables ---------- */
    var tableSaldos = $('#tablaSaldos').DataTable({
        language: configSpanish,
        pagingType: 'full_numbers',
        pageLength: 10,
        responsive: false,
        scrollX: true,
        scrollY: '26.875rem',
        scrollCollapse: true,
        order: [[1, 'asc']],
        columnDefs: [{ targets: [0], orderable: false }, { width: '1.875rem', targets: [3, 4] }],
        footerCallback: function (row, data, start, end, display) {
            var api = this.api();
            function sumarColumna(idx) {
                var total = 0;
                api.column(idx, { search: 'applied' }).data().each(function (val) {
                    total += parseFloat(String(val).replace(/[^0-9.\-]/g, '')) || 0;
                });
                return total;
            }
            function fmt(n) {
                return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            var footCells = $(api.table().footer()).find('td');
            if (footCells.length >= 9) {
                $(footCells[2]).text(fmt(sumarColumna(6)));
                $(footCells[3]).text('$' + fmt(sumarColumna(7)));
                $(footCells[4]).text(fmt(sumarColumna(8)));
                $(footCells[5]).text('$' + fmt(sumarColumna(9)));
                $(footCells[6]).text(fmt(sumarColumna(10)));
                $(footCells[7]).text(fmt(sumarColumna(11)));
                $(footCells[8]).text(fmt(sumarColumna(12)));
                $(footCells[1]).text('TOTALES CONSOLIDADOS (' + api.rows({ search: 'applied' }).count() + ' trabajadores)');
            }
        },
        dom: '<"dt-toolbar"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: crearBotonesDataTable([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12], [5, 6, 7, 8, 9, 10, 11], 'Consolidado Subsidiario del Submayor de Vacaciones', [0], [0, 1], 1, 'consolidado')
    });
    aplicarBuscadorDataTable(tableSaldos, 'Buscar trabajador, cargo o área...');

    if ($('#chkSoloBajas').is(':checked')) {
        aplicarFiltroSoloBajas(true);
        tableSaldos.draw();
    }

    var tableMovimientos = $('#tablaMovimientos').DataTable({
        language: configSpanish,
        pagingType: 'full_numbers',
        pageLength: 15,
        responsive: false,
        scrollX: true,
        scrollY: '26.875rem',
        scrollCollapse: true,
        order: [[0, 'desc']],
        dom: '<"dt-toolbar"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: crearBotonesDataTable([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [7, 8], 'Movimientos del Submayor de Vacaciones', [], [2], 2, 'movimientos')
    });
    aplicarBuscadorDataTable(tableMovimientos, 'Buscar movimiento...');

    if ($.fn.DataTable.isDataTable('#tablaDetalleIndividual')) {
        $('#tablaDetalleIndividual').DataTable().destroy();
    }
    if ($('#tablaDetalleIndividual').length > 0) {
        $('#tablaDetalleIndividual').DataTable({
            language: configSpanish,
        pagingType: 'full_numbers',
            pageLength: 10,
            responsive: false,
            scrollX: true,
            scrollY: '25rem',
            scrollCollapse: true,
            order: [[0, 'asc']],
            dom: '<"dt-toolbar"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
            buttons: crearBotonesDataTable([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [3, 4, 5, 6, 7, 8], 'Libro Auxiliar Individual del Submayor de Vacaciones', [], [], null, 'individual')
        });
        aplicarBuscadorDataTable($('#tablaDetalleIndividual').DataTable(), 'Buscar en libro auxiliar...');
    }

    /* ---------- Menú de acciones ---------- */
    $('#menuColumnas').on('click', function (e) { e.preventDefault(); tableSaldos.button('.buttons-colvis').trigger(); });
    $('#menuExportPrint').on('click', function (e) { e.preventDefault(); tableSaldos.button('.buttons-print').trigger(); });
    $('#menuExportPDF').on('click', function (e) { e.preventDefault(); tableSaldos.button('.buttons-pdf').trigger(); });
    $('#menuExportExcel').on('click', function (e) { e.preventDefault(); tableSaldos.button('.buttons-excel').trigger(); });
    $('#menuExportCSV').on('click', function (e) { e.preventDefault(); tableSaldos.button('.buttons-csv').trigger(); });

    $('#menuExportWord').on('click', function (e) {
        e.preventDefault();
        exportarWordDesdeTabla(tableSaldos, 'Consolidado Subsidiario del Submayor de Vacaciones', [0]);
    });

$('#menuExportTXT').on('click', function (e) {
        e.preventDefault();
        exportarTxtDesdeTabla(tableSaldos, 'CONSOLIDADO', [0]);
    });

    /* ============================================================
   AUXILIAR INDIVIDUAL (OFF-CANVAS, AJAX)
   ============================================================ */
var auxiliarDataTable = null;
window.verHistorialTrabajador = function (trabajadorId, nombreCompleto) {
    window.__auxTrabajadorId = trabajadorId;
    Swal.fire({
        title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Auxiliar</strong>',
        html: '<p class="mb-0">Calculando saldos corridos e importes acumulados. Por favor espere...</p>',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#121722',
        color: '#ffffff'
    });

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            action: 'obtener_historial_trabajador',
            trabajador_id: trabajadorId
        },
        dataType: 'json',
        success: function (response) {
            Swal.close();
            if (response.success) {
                $('#lblTrabajadorHistorial').text(nombreCompleto);
                if (response.data && response.data.length > 0) {
                    var primerReg = response.data[0];
                    $('#modalCargoTrabajador').text(primerReg.cargo || '—');
                    $('#modalAreaTrabajador').text(primerReg.nombre_area || '—');
                } else {
                    $('#modalCargoTrabajador').text('—');
                    $('#modalAreaTrabajador').text('—');
                }
                $('#modalSaldoTrabajador').text(parseFloat(response.saldo_actual).toFixed(2) + ' días');

                var html = '';
                var saldoDias = 0;
                var saldoImporte = 0;

                response.data.forEach(function (reg) {
                    var fMov = reg.fecha_movimiento;
                    if (fMov) {
                        var f = fMov.split(' ');
                        var p = f[0].split('-');
                        if (p.length === 3) fMov = p[2] + '/' + p[1] + '/' + p[0] + ' ' + (f[1] || '');
                    }
                    var esEntrada = (reg.tipo_movimiento === 'acumulacion' || (reg.tipo_movimiento === 'ajuste' && parseFloat(reg.dias) >= 0));
                    var diasEnt = esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                    var impEnt = esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                    var diasSal = !esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                    var impSal = !esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                    if (esEntrada) { saldoDias += diasEnt; saldoImporte += impEnt; }
                    else { saldoDias -= diasSal; saldoImporte -= impSal; }

                    var conceptBadge = '';
                    if (reg.tipo_movimiento === 'acumulacion') {
                        conceptBadge = '<span class="badge-op badge-acumulacion"><i class="fas fa-plus me-1"></i>Acumulado</span>';
                    } else if (reg.tipo_movimiento === 'disfrute') {
                        conceptBadge = '<span class="badge-op badge-disfrute"><i class="fas fa-minus me-1"></i>Disfrute</span>';
                    } else {
                        conceptBadge = '<span class="badge-op badge-ajuste"><i class="fas fa-balance-scale me-1"></i>Ajuste</span>';
                    }

                    var impEntText = impEnt > 0 ? new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(impEnt) : '-';
                    var impSalText = impSal > 0 ? new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(impSal) : '-';
                    var saldoImpText = new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(saldoImporte);

                    html += '<tr>';
                    html += '<td class="c-center">' + fMov + '</td>';
                    html += '<td class="c-center">' + (reg.periodo_desde || '').split('-')[1] + '/' + (reg.periodo_desde || '').split('-')[0] + '</td>';
                    html += '<td class="c-center">' + conceptBadge + '</td>';
                    html += '<td class="c-num" style="color:var(--color-success-soft);">' + (diasEnt > 0 ? diasEnt.toFixed(2) : '-') + '</td>';
                    html += '<td class="c-num" style="color:var(--color-success-soft);">' + impEntText + '</td>';
                    html += '<td class="c-num" style="color:var(--amber-soft);">' + (diasSal > 0 ? diasSal.toFixed(2) : '-') + '</td>';
                    html += '<td class="c-num" style="color:var(--amber-soft);">' + impSalText + '</td>';
                    html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + saldoDias.toFixed(2) + '</td>';
                    html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + saldoImpText + '</td>';
                    html += '<td><small style="color:var(--muted);">' + (reg.referencia || '—') + '</small></td>';
                    html += '</tr>';
                });

                if (response.data.length === 0) {
                    html = '<tr><td colspan="10" class="c-center py-4" style="color:var(--faint);">No se registran movimientos en el submayor para este trabajador.</td></tr>';
                }

                $('#tablaBodyIndividual').html(html);

                if (auxiliarDataTable) {
                    auxiliarDataTable.destroy();
                    auxiliarDataTable = null;
                }
                auxiliarDataTable = $('#tablaHistorialIndividual').DataTable({
                    language: configSpanishDataTable(),
                    pagingType: 'full_numbers',
                    pageLength: 10,
                    responsive: false,
                    scrollX: true,
                    order: [[0, 'asc']],
                    dom: '<"dt-toolbar"<"dt-length"l><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>'
                });
                aplicarBuscadorDataTable(auxiliarDataTable, 'Buscar en historial...');

                var offEl = document.getElementById('modalHistorialIndividual');
                var off = bootstrap.Offcanvas.getInstance(offEl) || new bootstrap.Offcanvas(offEl);
                off.show();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: response.message, background: '#121722', color: '#ffffff' });
            }
        },
        error: function () {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor para consultar el historial.', background: '#121722', color: '#ffffff' });
        }
    });
};

window.actualizarLibroAuxiliar = function () {
    var tid = new URLSearchParams(window.location.search).get('trabajador_id');
    if (!tid) { return; }
    var btn = document.getElementById('btnActualizarAuxiliar');
    var icon = btn ? btn.querySelector('i') : null;
    if (icon) { icon.classList.add('icono-girando'); }
    if (btn) { btn.disabled = true; }

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { action: 'obtener_historial_trabajador', trabajador_id: tid },
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: response.message, background: '#121722', color: '#ffffff' });
                return;
            }
            var data = response.data || [];
            var fmt = new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' });
            var html = '';
            var saldoDias = 0;
            var saldoImporte = 0;

            data.forEach(function (reg) {
                var fMov = reg.fecha_movimiento || '';
                if (fMov) {
                    var f = fMov.split(' ');
                    var p = f[0].split('-');
                    if (p.length === 3) fMov = p[2] + '/' + p[1] + '/' + p[0] + (f[1] ? ' ' + f[1] : '');
                }
                var periodo = (reg.periodo_desde || '').split('-');
                var periodoText = periodo.length >= 2 ? periodo[1] + '/' + periodo[0] : (reg.periodo_desde || '');

                var esEntrada = (reg.tipo_movimiento === 'acumulacion' || (reg.tipo_movimiento === 'ajuste' && parseFloat(reg.dias) >= 0));
                var diasEnt = esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                var impEnt = esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                var diasSal = !esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                var impSal = !esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                if (esEntrada) { saldoDias += diasEnt; saldoImporte += impEnt; }
                else { saldoDias -= diasSal; saldoImporte -= impSal; }

                var badge = '';
                if (reg.tipo_movimiento === 'acumulacion') {
                    badge = '<span class="badge-op badge-acumulacion"><i class="fas fa-plus"></i> Acumulación</span>';
                } else if (reg.tipo_movimiento === 'disfrute') {
                    badge = '<span class="badge-op badge-disfrute"><i class="fas fa-minus"></i> Disfrute</span>';
                } else {
                    badge = '<span class="badge-op badge-ajuste"><i class="fas fa-balance-scale"></i> Ajuste Manual</span>';
                }

                html += '<tr>';
                html += '<td class="c-center">' + fMov + '</td>';
                html += '<td class="c-center">' + periodoText + '</td>';
                html += '<td class="c-center">' + badge + '</td>';
                html += '<td class="c-num" style="color:var(--color-success-soft);">' + (diasEnt > 0 ? diasEnt.toFixed(2) : '-') + '</td>';
                html += '<td class="c-num" style="color:var(--color-success-soft);">' + (impEnt > 0 ? fmt.format(impEnt) : '-') + '</td>';
                html += '<td class="c-num" style="color:var(--amber-soft);">' + (diasSal > 0 ? diasSal.toFixed(2) : '-') + '</td>';
                html += '<td class="c-num" style="color:var(--amber-soft);">' + (impSal > 0 ? fmt.format(impSal) : '-') + '</td>';
                html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + saldoDias.toFixed(2) + '</td>';
                html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + fmt.format(saldoImporte) + '</td>';
                html += '<td><small style="color:var(--muted);">' + (reg.referencia || '-') + '</small></td>';
                html += '</tr>';
            });

            if (data.length === 0) {
                html = '<tr><td colspan="10" class="c-center py-4" style="color:var(--faint);">No se han registrado movimientos contables para este trabajador.</td></tr>';
            }

            var $table = $('#tablaDetalleIndividual');
            if ($.fn.DataTable.isDataTable($table)) {
                var dt = $table.DataTable();
                dt.clear();
                dt.rows.add($(html));
                dt.draw();
            } else {
                $table.find('tbody').html(html);
            }
        },
        error: function () {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor para actualizar el libro auxiliar.', background: '#121722', color: '#ffffff' });
        },
        complete: function () {
            if (icon) {
                setTimeout(function () { icon.classList.remove('icono-girando'); }, 700);
            }
            if (btn) { btn.disabled = false; }
        }
    });
};

window.actualizarAuxiliarIndividual = function () {
    var tid = window.__auxTrabajadorId;
    if (!tid) { return; }
    var btn = document.getElementById('btnActualizarAuxIndividual');
    var icon = btn ? btn.querySelector('i') : null;
    if (icon) { icon.classList.add('icono-girando'); }

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { action: 'obtener_historial_trabajador', trabajador_id: tid },
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: response.message, background: '#121722', color: '#ffffff' });
                return;
            }
            var html = '';
            var saldoDias = 0;
            var saldoImporte = 0;
            var fmt = new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' });

            (response.data || []).forEach(function (reg) {
                var fMov = reg.fecha_movimiento || '';
                if (fMov) {
                    var f = fMov.split(' ');
                    var p = f[0].split('-');
                    if (p.length === 3) fMov = p[2] + '/' + p[1] + '/' + p[0] + (f[1] ? ' ' + f[1] : '');
                }
                var periodo = (reg.periodo_desde || '').split('-');
                var periodoText = periodo.length >= 2 ? periodo[1] + '/' + periodo[0] : (reg.periodo_desde || '');

                var esEntrada = (reg.tipo_movimiento === 'acumulacion' || (reg.tipo_movimiento === 'ajuste' && parseFloat(reg.dias) >= 0));
                var diasEnt = esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                var impEnt = esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                var diasSal = !esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                var impSal = !esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                if (esEntrada) { saldoDias += diasEnt; saldoImporte += impEnt; }
                else { saldoDias -= diasSal; saldoImporte -= impSal; }

                var badge = '';
                if (reg.tipo_movimiento === 'acumulacion') {
                    badge = '<span class="badge-op badge-acumulacion"><i class="fas fa-plus me-1"></i>Acumulado</span>';
                } else if (reg.tipo_movimiento === 'disfrute') {
                    badge = '<span class="badge-op badge-disfrute"><i class="fas fa-minus me-1"></i>Disfrute</span>';
                } else {
                    badge = '<span class="badge-op badge-ajuste"><i class="fas fa-balance-scale me-1"></i>Ajuste</span>';
                }

                var impEntText = impEnt > 0 ? fmt.format(impEnt) : '-';
                var impSalText = impSal > 0 ? fmt.format(impSal) : '-';
                var saldoImpText = fmt.format(saldoImporte);

                html += '<tr>';
                html += '<td class="c-center">' + fMov + '</td>';
                html += '<td class="c-center">' + periodoText + '</td>';
                html += '<td class="c-center">' + badge + '</td>';
                html += '<td class="c-num" style="color:var(--color-success-soft);">' + (diasEnt > 0 ? diasEnt.toFixed(2) : '-') + '</td>';
                html += '<td class="c-num" style="color:var(--color-success-soft);">' + impEntText + '</td>';
                html += '<td class="c-num" style="color:var(--amber-soft);">' + (diasSal > 0 ? diasSal.toFixed(2) : '-') + '</td>';
                html += '<td class="c-num" style="color:var(--amber-soft);">' + impSalText + '</td>';
                html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + saldoDias.toFixed(2) + '</td>';
                html += '<td class="c-num fw-bold" style="color:var(--blue-soft); background:rgba(var(--blue-soft-rgb),0.05);">' + saldoImpText + '</td>';
                html += '<td><small style="color:var(--muted);">' + (reg.referencia || '—') + '</small></td>';
                html += '</tr>';
            });

            if ((response.data || []).length === 0) {
                html = '<tr><td colspan="10" class="c-center py-4" style="color:var(--faint);">No se registran movimientos en el submayor para este trabajador.</td></tr>';
            }

            var $hist = $('#tablaHistorialIndividual');
            if ($.fn.DataTable.isDataTable($hist)) {
                var dt = $hist.DataTable();
                dt.clear();
                dt.rows.add($(html));
                dt.draw();
                auxiliarDataTable = dt;
            } else {
                $('#tablaBodyIndividual').html(html);
                auxiliarDataTable = $hist.DataTable({
                    language: configSpanishDataTable(),
                    pagingType: 'full_numbers',
                    pageLength: 10,
                    responsive: false,
                    scrollX: true,
                    order: [[0, 'asc']],
                    dom: '<"dt-toolbar"<"dt-length"l><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>'
                });
                aplicarBuscadorDataTable(auxiliarDataTable, 'Buscar en historial...');
            }
        },
        error: function () {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor para actualizar el auxiliar.', background: '#121722', color: '#ffffff' });
        },
        complete: function () {
            if (icon) {
                setTimeout(function () { icon.classList.remove('icono-girando'); }, 700);
            }
        }
    });
};

function configSpanishDataTable() {
    return {
        "emptyTable": "No hay datos disponibles en la tabla",
        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
        "infoEmpty": "Mostrando 0 a 0 de 0 registros",
        "infoFiltered": "(filtrado de _MAX_ registros totales)",
        "lengthMenu": "Mostrar _MENU_ registros",
        "search": "Buscar:",
        "zeroRecords": "No se encontraron registros coincidentes",
        "paginate": { "first": '<i class="fas fa-step-backward"></i>', "last": '<i class="fas fa-step-forward"></i>', "next": '<i class="fas fa-chevron-right"></i>', "previous": '<i class="fas fa-chevron-left"></i>' },
        "aria": { "sortAscending": ": activar para ordenar ascendente", "sortDescending": ": activar para ordenar descendente" }
    };
}

/* ============================================================
   FORMULARIO DE AJUSTES (AJAX)
   ============================================================ */
document.getElementById('ajusteVacacionesForm').addEventListener('submit', function (e) {
    e.preventDefault();

    var frm = this;
    if (!frm.checkValidity()) {
        frm.classList.add('was-validated');
        Swal.fire({
            icon: 'warning',
            title: 'Datos incompletos',
            text: 'Complete los campos obligatorios del formulario antes de continuar.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#121722',
            color: '#ffffff'
        });
        return;
    }

    var t = obtenerTrabajadorSeleccionado();
    if (!t) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione un trabajador',
            text: 'Debe elegir el trabajador al que se le registrará el movimiento.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#121722',
            color: '#ffffff'
        });
        return;
    }
    var tipo = $('#modalTipoMovimiento').val();
    var diasVal = parseFloat($('#inputDiasAjuste').val()) || 0;

    if ((tipo === 'acumulacion' || tipo === 'disfrute') && diasVal <= 0) {
        notificarError('Días inválidos', 'Para acumulación o disfrute los días deben ser positivos.');
        return;
    }
    if (tipo === 'ajuste' && diasVal === 0) {
        notificarError('Días inválidos', 'Un ajuste no puede registrar 0 días.');
        return;
    }
    if (tipo === 'disfrute' && t && diasVal > t.saldo_acumulado + 0.005) {
        Swal.fire({
            icon: 'warning',
            title: 'Saldo insuficiente',
            html: 'El trabajador dispone de <strong>' + fmtDias(t.saldo_acumulado) + '</strong> y solicita disfrutar <strong>' + fmtDias(diasVal) + '</strong>.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#121722',
            color: '#ffffff'
        });
        return;
    }

    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Procesando ajuste...',
        html: 'Por favor, espere mientras actualizamos los libros contables del submayor.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#121722',
        color: '#ffffff'
    });

    fetch(window.location.href, {
        method: 'POST',
        body: new FormData(frm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Ajuste Procesado',
                text: data.message,
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                background: '#121722',
                color: '#ffffff'
            }).then(() => { window.location.reload(); });
        } else if (data.denied) {
            notificarAccesoDenegado(data.message);
        } else {
            notificarError('No se pudo procesar el ajuste', data.message || 'Ocurrió un problema al registrar el movimiento de vacaciones.');
        }
    })
    .catch(() => {
        notificarError('Fallo en la conexión', 'No se pudo conectar con el servidor para procesar el ajuste. Verifique su conexión e inténtelo de nuevo.');
    });
});
});

/* ============================================================
   CIERRE DE SESIÓN
   ============================================================ */
function cerrarSesion() {
    fetch('../logout.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(() => { window.location.href = '../login.php?logout=1'; })
        .catch(() => { window.location.href = '../login.php?logout=1'; });
}

document.getElementById('logoutBtn')?.addEventListener('click', function (e) {
    e.preventDefault();
    confirmarSalida();
});
document.getElementById('logoutSidebarBtn')?.addEventListener('click', function (e) {
    e.preventDefault();
    confirmarSalida();
});

function confirmarSalida() {
    Swal.fire({
        title: '<i class="fas fa-sign-out-alt" style="color: #ef4444"></i> Cerrar sesión',
        text: '¿Está seguro que desea salir del sistema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#D13438',
        cancelButtonColor: '#2D2D2D',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, salir',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#121722',
        color: '#FFFFFF'
    }).then((result) => { if (result.isConfirmed) cerrarSesion(); });
}

/* ============================================================
   IMPRESIÓN FÍSICA TRADICIONAL
   ============================================================ */
function imprimirReporteFisico() {
    var nombreEmpresa = globalNombreEmpresa;
    var usuarioNombre = globalUsuarioNombre;
    var periodoFiltroTexto = globalPeriodoFiltroTexto;
    var jefeProyecto = globalJefeProyecto;
    var especialistaGestion = globalEspecialistaGestion;
var especialistaRRHH = globalEspecialistaRRHH;
    var logoBase64 = globalLogoBase64;

    var printWindow = window.open('', '_blank', 'height=700,width=1100');
        printWindow.document.write('<html><head><title>Submayor de Vacaciones - Reporte Físico</title>');
        printWindow.document.write('<meta charset="UTF-8">');
        printWindow.document.write('<style>');
        printWindow.document.write('@page { size: Letter landscape; margin:0; }');
        printWindow.document.write('* { margin:0; padding:0; box-sizing: border-box; }');
        printWindow.document.write('body { font-family: "Arial", "Helvetica", sans-serif; color: #000000; background: #ffffff; font-size:10pt; line-height:1.4; }');
        printWindow.document.write('.page-sheet { width:279.4mm; height:215.9mm; padding:12mm 10mm 20mm 10mm; position: relative; background: #ffffff; page-break-after: always; overflow: hidden; }');
        printWindow.document.write('.page-sheet.last { page-break-after: auto; }');
        printWindow.document.write('@media print { .page-sheet { height:100vh; } }');
        printWindow.document.write('@media print { .no-print { display: none !important; } }');
        printWindow.document.write('#auto-hide-toolbar { transition: transform 0.3s ease; }');
        printWindow.document.write('#auto-hide-toolbar.hidden { transform: translateY(-100%); }');
        printWindow.document.write('.print-footer { position: absolute; bottom:10mm; left:10mm; right:10mm; border-top: 0.0625rem solid #bbb; padding-top:0.3125rem; font-size:7.5pt; color: #555; display: flex; justify-content: space-between; }');
        printWindow.document.write('.header-logo { display: flex; align-items: center; justify-content: center; gap:1.25rem; margin-bottom:0.9375rem; min-height:3.75rem; }');
        printWindow.document.write('.header-logo img { max-height:3.75rem; max-width:7.5rem; object-fit: contain; }');
        printWindow.document.write('table { width:100%; border-collapse: collapse; margin-top:0.9375rem; margin-bottom:1.5625rem; }');
        printWindow.document.write('th, td { border: 0.0625rem solid #000000; padding:0.375rem 0.5rem; text-align: left; vertical-align: top; color: #000000 !important; }');
        printWindow.document.write('th { background-color: #e8e8e8 !important; color: #000000 !important; text-align: center; font-weight: bold; font-size:9pt; }');
        printWindow.document.write('table thead { display: table-header-group; }');
        printWindow.document.write('.header-table { width:100%; border: none; margin-bottom:1.25rem; }');
        printWindow.document.write('.header-table td { border: none; padding:0.25rem 0; }');
        printWindow.document.write('.title { text-align: center; font-size:14pt; font-weight: bold; text-transform: uppercase; margin-bottom:0.3125rem; letter-spacing:0.0625rem; }');
        printWindow.document.write('.subtitle { text-align: center; font-size:11pt; font-weight: bold; margin-bottom:1.25rem; }');
        printWindow.document.write('.company-info { text-align: center; font-size:9pt; margin-bottom:0.9375rem; border-bottom: 0.125rem solid #333; padding-bottom:0.625rem; }');
        printWindow.document.write('.text-right { text-align: right; }');
        printWindow.document.write('.text-center { text-align: center; }');
        printWindow.document.write('.text-left { text-align: left; }');
        printWindow.document.write('.signatures { width:100%; margin-top:3.125rem; border: none; }');
        printWindow.document.write('.signatures td { border: none; text-align: center; width:33%; padding-top:2.5rem; font-size:9pt; vertical-align: bottom; }');
        printWindow.document.write('.signatures-line { border-top: 0.0625rem solid #000000; width:85%; margin:0 auto 0.3125rem auto; }');
        printWindow.document.write('.footer-note { text-align: center; font-size:8pt; margin-top:1.875rem; color: #555; border-top: 0.0625rem solid #ccc; padding-top:0.625rem; }');
        printWindow.document.write('.total-row { font-weight: bold; background-color: #f0f0f0; }');
        printWindow.document.write('.pg-header { padding-bottom:0.0625rem; }');
        printWindow.document.write('.pg-sig { padding-top:0.0625rem; padding-bottom:0.0625rem; }');
        printWindow.document.write('</style></head><body>');
    
        var trabajadorId = '<?php echo $trabajador_id; ?>';
        var fechaActual = new Date().toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    
        // --- Cabecera (logo + títulos + datos) que se repite en cada hoja ---
        var headerHtml = '<div class="header-logo">';
        if (logoBase64) headerHtml += '<img src="' + logoBase64 + '" alt="Logo">';
        headerHtml += '</div>';
    
        // --- Recopilar filas de datos y cabecera de tabla según el tipo ---
        var filasHtml = [];
        var theadHtml = '';
        var HDR, THEAD_H, ROW_H, TBL_M;
    
        if (trabajadorId !== '') {
            var nombreTrab = '<?php echo htmlspecialchars(mb_strtoupper($resumen_trabajador['nombre_completo'] ?? '', 'UTF-8')); ?>';
            var codigoTrab = '<?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? ''); ?>';
            var areaTrab = '<?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? ''); ?>';
            var salarioTrab = '<?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?>';
    
            headerHtml += '<div class="title">' + nombreEmpresa.toUpperCase() + '</div>';
            headerHtml += '<div class="subtitle">SUBMAYOR DE VACACIONES<br>(Método Laboral Cubano - 9.09%)</div>';
    
            headerHtml += '<table class="header-table">';
            headerHtml += '<tr><td width="50%"><strong>Código / Expediente:</strong> ' + codigoTrab + '</td>';
            headerHtml += '<td class="text-right"><strong>Fecha Reporte:</strong> ' + fechaActual + '</td></tr>';
            headerHtml += '<tr><td width="50%"><strong>Trabajador:</strong> ' + nombreTrab + '</td>';
            headerHtml += '<td class="text-right"><strong>Período:</strong> ' + periodoFiltroTexto + '</td></tr>';
            headerHtml += '<tr><td width="50%"><strong>Área / Departamento:</strong> ' + areaTrab + '</td>';
            headerHtml += '<td class="text-right"><strong>Salario Escala:</strong> ' + salarioTrab + '</td></tr>';
            headerHtml += '</table>';
    
            theadHtml = '<thead>';
            theadHtml += '<tr><th style="text-align:center;" rowspan="2">Fecha<br>Mov.</th><th style="text-align:center;" rowspan="2">Período</th><th style="text-align:left;" rowspan="2">Operación</th>';
            theadHtml += '<th style="text-align:center;" colspan="2">ENTRADAS<br>(+)</th><th style="text-align:center;" colspan="2">SALIDAS<br>(-)</th><th style="text-align:center;" colspan="2">SALDOS<br>CORRIDOS</th>';
            theadHtml += '</tr>';
            theadHtml += '<tr><th style="text-align:center;">Días</th><th style="text-align:center;">Importe<br>(CUP)</th><th style="text-align:center;">Días</th><th style="text-align:center;">Importe<br>(CUP)</th><th style="text-align:center;">Días</th><th style="text-align:center;">Importe<br>(CUP)</th></tr>';
            theadHtml += '</thead>';
    
            var tablaDetalle = document.getElementById('tablaDetalleIndividual');
            if (tablaDetalle) {
                var filas = tablaDetalle.querySelectorAll('tbody tr');
                filas.forEach(function (fila) {
                    var celdas = fila.querySelectorAll('td');
                    if (celdas.length >= 9) {
                        var operacion = celdas[2] ? celdas[2].innerText.trim() : '';
                        operacion = operacion.replace(/<[^>]*>/g, '').trim();
                        var r = '<tr>';
                        r += '<td style="text-align:center;">' + (celdas[0] ? celdas[0].innerText.trim() : '') + '</td>';
                        r += '<td style="text-align:center;">' + (celdas[1] ? celdas[1].innerText.trim() : '') + '</td>';
                        r += '<td style="text-align:left;">' + operacion + '</td>';
                        r += '<td style="text-align:center;">' + (celdas[3] ? celdas[3].innerText.trim() : '-') + '</td>';
                        r += '<td style="text-align:center;">' + (celdas[4] ? celdas[4].innerText.trim() : '-') + '</td>';
                        r += '<td style="text-align:center;">' + (celdas[5] ? celdas[5].innerText.trim() : '-') + '</td>';
                        r += '<td style="text-align:center;">' + (celdas[6] ? celdas[6].innerText.trim() : '-') + '</td>';
                        r += '<td style="text-align:center;"><strong>' + (celdas[7] ? celdas[7].innerText.trim() : '-') + '</strong></td>';
                        r += '<td style="text-align:center;"><strong>' + (celdas[8] ? celdas[8].innerText.trim() : '-') + '</strong></td>';
                        r += '</tr>';
                        filasHtml.push(r);
                    }
                });
            }
            HDR = 64; THEAD_H = 18; ROW_H = 8.2; TBL_M = 10.6;
        } else {
            headerHtml += '<div class="title">' + nombreEmpresa.toUpperCase() + '</div>';
            headerHtml += '<div class="company-info">NIT: 12345678-9 | Régimen General</div>';
            headerHtml += '<div class="subtitle">CONSOLIDADO SUBSIDIARIO DEL SUBMAYOR DE VACACIONES</div>';
    
            headerHtml += '<table class="header-table">';
            headerHtml += '<tr><td width="50%"><strong>Período Consulta:</strong> ' + periodoFiltroTexto + '</td>';
            headerHtml += '<td class="text-right"><strong>Fecha Emisión:</strong> ' + fechaActual + '</td></tr>';
            headerHtml += '<tr><td width="50%"><strong>Días Laborables por Mes:</strong> ' + globalDiasLaborables + '</td>';
            headerHtml += '<td class="text-right"><strong>Generado por:</strong> ' + usuarioNombre + '</td></tr>';
            headerHtml += '</table>';
    
            theadHtml = '<thead><tr>';
            theadHtml += '<th style="text-align:center;">Exp.</th><th style="text-align:left;">Trabajador</th><th style="text-align:left;">Cargo</th><th style="text-align:left;">Área</th>';
            theadHtml += '<th style="text-align:center;">Días<br>Acum.</th><th style="text-align:center;">Imp.<br>Acum.</th>';
            theadHtml += '<th style="text-align:center;">Días<br>Disf.</th><th style="text-align:center;">Imp.<br>Disf.</th>';
            theadHtml += '<th style="text-align:center;">Días<br>Ajuste</th>';
            theadHtml += '<th style="text-align:center;">Saldo<br>Inicial</th><th style="text-align:center;">Saldo al<br>Cierre</th>';
            theadHtml += '</tr></thead>';
    
            var tablaSaldosEl = document.getElementById('tablaSaldos');
            if (tablaSaldosEl) {
                var filasS = tablaSaldosEl.querySelectorAll('tbody tr');
                filasS.forEach(function (fila) {
                    var celdas = fila.querySelectorAll('td');
                    if (celdas.length >= 13) {
                        var r = '<tr>';
                        r += '<td style="text-align:center;">' + (celdas[1] ? celdas[1].innerText.trim() : '') + '</td>';
                        r += '<td style="text-align:left;">' + (celdas[3] ? celdas[3].innerText.trim() : '') + '</td>';
                        r += '<td style="text-align:left;">' + (celdas[4] ? celdas[4].innerText.trim() : '') + '</td>';
                        r += '<td style="text-align:left;">' + (celdas[5] ? celdas[5].innerText.trim() : '') + '</td>';
                        for (var k = 6; k <= 12; k++) {
                            r += '<td style="text-align:center;">' + (celdas[k] ? celdas[k].innerText.trim() : '-') + '</td>';
                        }
                        r += '</tr>';
                        filasHtml.push(r);
                    }
                });
            }
    
            var footCols = $('#tablaSaldos tfoot tr td');
            if (footCols.length === 0) footCols = $('.dataTables_scrollFootInner tfoot tr td');
            if (footCols.length === 0 && $.fn.DataTable.isDataTable('#tablaSaldos')) {
                var dtFoot = $('#tablaSaldos').DataTable().table().footer();
                footCols = dtFoot ? $(dtFoot).find('td') : $();
            }
            if (footCols.length > 0) {
                var totalsRow = '<tr class="total-row">';
                totalsRow += '<td colspan="4" style="text-align:center;"><strong>TOTALES GENERALES:</strong></td>';
                for (var k2 = 2; k2 <= 8; k2++) {
                    totalsRow += '<td style="text-align:center;"><strong>' + (footCols[k2] ? $(footCols[k2]).text().trim() : '0') + '</strong></td>';
                }
                totalsRow += '</tr>';
                filasHtml.push(totalsRow);
            }
            HDR = 58; THEAD_H = 8.2; ROW_H = 8.2; TBL_M = 10.6;
        }
    
        // --- Firmas y nota final (siempre al final del documento) ---
        var firmaHtml = '<table class="signatures">';
        firmaHtml += '<tr>';
        firmaHtml += '<td><div class="signatures-line"></div><strong>Elaborado por:</strong><br>' + (especialistaRRHH || usuarioNombre) + '<br><span style="font-size:7pt;">(Especialista de Recursos Humanos)</span></td>';
        firmaHtml += '<td><div class="signatures-line"></div><strong>Revisado por:</strong><br>' + especialistaGestion + '<br><span style="font-size:7pt;">(Especialista de Gestión)</span></td>';
        firmaHtml += '<td><div class="signatures-line"></div><strong>Aprobado por:</strong><br>' + jefeProyecto + '<br><span style="font-size:7pt;">(Jefe de Proyecto)</span></td>';
        firmaHtml += '</tr>';
        firmaHtml += '</table>';
        firmaHtml += '<div class="footer-note">';
        firmaHtml += '<br>Este reporte es de carácter oficial y refleja los movimientos registrados en el Submayor de Vacaciones.';
        firmaHtml += '</div>';
    
// --- Paginación por medición real (hojas .page-sheet + pie "Página N de M") ---
        // #pgRoot replica exactamente la caja de una .page-sheet para que la
        // medición ocurra al ancho real de impresión (259.4mm de contenido).
        printWindow.document.write('<div id="pgRoot" style="width:279.4mm; padding:12mm 10mm 20mm 10mm; box-sizing:border-box;">');
        printWindow.document.write('<div class="pg-header">' + headerHtml + '</div>');
        printWindow.document.write('<table id="pgTable">' + theadHtml + '<tbody>' + filasHtml.join('') + '</tbody></table>');
        printWindow.document.write('<div class="pg-sig">' + firmaHtml + '</div>');
        printWindow.document.write('</div>');

        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        paginarImpresion(printWindow.document, {
            headerSel: '.pg-header',
            tableSel: '#pgTable',
            sigSel: '.pg-sig',
            footerLeft: nombreEmpresa + ' - Submayor de Vacaciones',
            tableTop: 15,
            tableBottom: 25,
            safety: 8
        });

        var tbDoc = printWindow.document;
        var tbWrap = tbDoc.createElement('div');
        tbWrap.id = 'auto-hide-toolbar';
        tbWrap.className = 'no-print';
        tbWrap.setAttribute('style', 'position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;');
        tbWrap.innerHTML = '<span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span>'
            + '<button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">🖨️ Imprimir</button>'
            + '<button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">✖ Cerrar</button>'
            + '';
        tbDoc.body.appendChild(tbWrap);

        var ahCSS = tbDoc.createElement('style');
        ahCSS.textContent = '#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}';
        tbDoc.head.appendChild(ahCSS);

        var ahScript = tbDoc.createElement('script');
        ahScript.textContent = '(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();';
        tbDoc.body.appendChild(ahScript);
}

/* ============================================================
   PAGINACIÓN MEDIDA (hojas .page-sheet + pie "Página N de M")
   Reutilizable por el reporte físico y por el print de DataTables
   ============================================================ */
function makeFooterPrint(doc, n, total, cfg) {
    var footer = doc.createElement('div');
    footer.className = 'print-footer';
    var l = doc.createElement('div');
    l.textContent = cfg.footerLeft || '';
    var r = doc.createElement('div');
    r.textContent = 'Página ' + n + ' de ' + total;
    footer.appendChild(l);
    footer.appendChild(r);
    return footer;
}

function paginarImpresion(doc, cfg) {
    if (!doc || !cfg) return;
    var MM = 25.4 / 96;
    var SHEET_H = cfg.sheetH || 215.9;
    var PAD_TOP = cfg.padTop || 12;
    var PAD_BOT = cfg.padBot || 8;
    var FOOT_H = cfg.footH || 12;
    var SAFETY = (typeof cfg.safety === 'number') ? cfg.safety : 6;
    var tableTop = cfg.tableTop || 0;
    var tableBottom = cfg.tableBottom || 0;

    var headerEl = cfg.headerSel ? doc.querySelector(cfg.headerSel) : null;
    var tableEl = doc.querySelector(cfg.tableSel);
    var sigEl = cfg.sigSel ? doc.querySelector(cfg.sigSel) : null;
    var body = doc.body;
    if (!tableEl || !body) return;

    var thead = tableEl.querySelector('thead');
    var tbody = tableEl.querySelector('tbody');
    var tfoot = tableEl.querySelector('tfoot');
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.children);

    var leafThs = tableEl.querySelectorAll('thead tr:last-child th');
    var widths = [], totalW = 0;
    Array.prototype.forEach.call(leafThs, function (th) {
        var w = th.offsetWidth || 0;
        widths.push(w);
        totalW += w;
    });

    tableEl.style.tableLayout = 'fixed';
    if (totalW > 0) {
        var colgroup = doc.createElement('colgroup');
        widths.forEach(function (w) {
            var col = doc.createElement('col');
            col.style.width = (w / totalW * 100).toFixed(4) + '%';
            colgroup.appendChild(col);
        });
        tableEl.insertBefore(colgroup, tableEl.firstChild);
    }

    var headerH = headerEl ? headerEl.offsetHeight : 0;
    var theadH = thead ? thead.offsetHeight : 0;
    var rowHs = [];
    var maxRow = 0;
    rows.forEach(function (r) {
        var h = r.offsetHeight || 0;
        rowHs.push(h);
        if (h > maxRow) maxRow = h;
    });
    var tfootH = tfoot ? tfoot.offsetHeight : 0;
    var sigH = sigEl ? sigEl.offsetHeight : 0;
    var contentPx = (SHEET_H - PAD_TOP - PAD_BOT - FOOT_H - SAFETY) / MM;
    var budgetPx = contentPx - tableTop - tableBottom;

    if (rows.length === 0) {
        var emptySheet = doc.createElement('div');
        emptySheet.className = 'page-sheet last';
        if (headerEl) emptySheet.appendChild(headerEl);
        emptySheet.appendChild(tableEl);
        if (sigEl) emptySheet.appendChild(sigEl);
        emptySheet.appendChild(makeFooterPrint(doc, 1, 1, cfg));
        body.innerHTML = '';
        body.appendChild(emptySheet);
        return;
    }

    if (maxRow === 0) return;

    var lastBudget = budgetPx - sigH - tfootH;
    if (lastBudget < theadH + headerH + maxRow) lastBudget = budgetPx;

    var pagesIdx = [];
    var i = rows.length - 1;
    var lastRows = [];
    var used = headerH + theadH;
    while (i >= 0) {
        var h = rowHs[i];
        if (lastRows.length > 0 && used + h > lastBudget) break;
        lastRows.push(i);
        used += h;
        i--;
    }
    if (lastRows.length === 0 && i >= 0) { lastRows.push(i); i--; }

    var cur = [];
    used = headerH + theadH;
    while (i >= 0) {
        var h2 = rowHs[i];
        if (cur.length > 0 && used + h2 > budgetPx) {
            pagesIdx.push(cur);
            cur = [];
            used = headerH + theadH;
        }
        cur.push(i);
        used += h2;
        i--;
    }
    if (cur.length > 0) pagesIdx.push(cur);

    pagesIdx.reverse();
    pagesIdx.forEach(function (p) { p.reverse(); });
    lastRows.reverse();
    pagesIdx.push(lastRows);

    body.innerHTML = '';
    var total = pagesIdx.length;
    for (var p = 0; p < total; p++) {
        var isLast = (p === total - 1);
        var sheet = doc.createElement('div');
        sheet.className = 'page-sheet' + (isLast ? ' last' : '');
        if (headerEl) sheet.appendChild(headerEl.cloneNode(true));
        var tbl = doc.createElement('table');
        if (thead) tbl.appendChild(thead.cloneNode(true));
        var tb = doc.createElement('tbody');
        pagesIdx[p].forEach(function (idx) { tb.appendChild(rows[idx].cloneNode(true)); });
        tbl.appendChild(tb);
        if (isLast && tfoot) tbl.appendChild(tfoot.cloneNode(true));
        sheet.appendChild(tbl);
        if (isLast && sigEl) sheet.appendChild(sigEl.cloneNode(true));
        sheet.appendChild(makeFooterPrint(doc, p + 1, total, cfg));
        body.appendChild(sheet);
    }
}

/* ============================================================
   REPORTE VERSAT - FILTROS RÁPIDOS
   ============================================================ */
function aplicarFiltroRapidoVersat(min, max) {
    document.getElementById('versatDiasMin').value = (min === null) ? '' : String(min);
    document.getElementById('versatDiasMax').value = (max === null) ? '' : String(max);
}
function limpiarFiltrosDiasVersat() {
    document.getElementById('versatDiasMin').value = '';
    document.getElementById('versatDiasMax').value = '';
}

</script>

<!-- ==========================================
     MODAL DE REPORTE VERSAT - FILTROS AVANZADOS
     ========================================== -->
<div class="modal fade" id="modalReporteVersat" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-prof">
            <div class="modal-header" style="border-bottom: 0.125rem solid rgba(var(--color-success-rgb),0.4);">
                <h5 class="modal-title"><i class="fas fa-chart-bar" style="color:var(--color-success-soft);"></i> Reporte de Vacaciones Acumuladas</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <form id="formReporteVersat" method="GET" action="" target="_blank">
                    <input type="hidden" name="versat_report" value="1">
                    
                    <!-- Opciones de Agrupación -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-layer-group me-1" style="color:#818cf8;"></i> Agrupar por:</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="versat_agrupar" id="agruparArea" value="area" autocomplete="off" checked>
                                <label class="btn btn-outline-primary" for="agruparArea"><i class="fas fa-building me-1"></i> Área</label>
                                
                                <input type="radio" class="btn-check" name="versat_agrupar" id="agruparCentroCosto" value="centro_costo" autocomplete="off">
                                <label class="btn btn-outline-primary" for="agruparCentroCosto"><i class="fas fa-chart-pie me-1"></i> Centro Costo</label>
                                
                                <input type="radio" class="btn-check" name="versat_agrupar" id="agruparContrato" value="contrato" autocomplete="off">
                                <label class="btn btn-outline-primary" for="agruparContrato"><i class="fas fa-file-contract me-1"></i> Tipo Contrato</label>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro de Período (Año y Mes) -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-calendar-alt me-1" style="color:#818cf8;"></i> Año:</label>
                            <select name="versat_anio" id="versatAnio" class="form-select-prof">
                                <option value="">-- Todos (histórico) --</option>
                                <?php for($y = date('Y')-2; $y <= date('Y')+5; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($versat_anio ?? '') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-moon me-1" style="color:#818cf8;"></i> Mes:</label>
                            <select name="versat_mes" id="versatMes" class="form-select-prof">
                                <option value="">-- Todos --</option>
                                <?php for($m = 1; $m <= 12; $m++): $m_pad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $m_pad; ?>" <?php echo ($versat_mes ?? '') == $m_pad ? 'selected' : ''; ?>><?php echo nombreMesEspanol($m_pad); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Filtro de Intervalo de Días -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-sliders-h me-1" style="color:#fbbf24;"></i> Intervalo de Días Acumulados:</label>
                        </div>
                        <div class="col-md-5">
                            <select name="versat_dias_min" id="versatDiasMin" class="form-select-prof">
                                <option value="">Mínimo (sin límite)</option>
                                <option value="0">0 días</option>
                                <option value="5">Mayor a 5 días</option>
                                <option value="10">Mayor a 10 días</option>
                                <option value="15">Mayor a 15 días</option>
                                <option value="20">Mayor a 20 días</option>
                                <option value="30">Mayor a 30 días</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-center align-self-end pb-2">
                            <span class="fw-bold">HASTA</span>
                        </div>
                        <div class="col-md-5">
                            <select name="versat_dias_max" id="versatDiasMax" class="form-select-prof">
                                <option value="">Máximo (sin límite)</option>
                                <option value="0">0 días</option>
                                <option value="5">Menor a 5 días</option>
                                <option value="10">Menor a 10 días</option>
                                <option value="15">Menor a 15 días</option>
                                <option value="20">Menor a 20 días</option>
                                <option value="30">Menor a 30 días</option>
                                <option value="50">Menor a 50 días</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filtro Rápido por Rangos -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-bolt me-1" style="color:#a78bfa;"></i> Filtros Rápidos:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(null, 20)"><i class="fas fa-hourglass-half"></i> Menos de 20 días</button>
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(20, null)"><i class="fas fa-hourglass-end"></i> 20 días o más</button>
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(30, null)"><i class="fas fa-exclamation-triangle"></i> 30 días o más (Prioritario)</button>
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(0, 0)"><i class="fas fa-circle"></i> Con 0 días</button>
                                <button type="button" class="btn-win-sm" onclick="limpiarFiltrosDiasVersat()"><i class="fas fa-eraser"></i> Limpiar filtros</button>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Tipo de Contrato -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-file-signature me-1" style="color:#06b6d4;"></i> Tipo de Contrato:</label>
                            <select name="versat_tipo_contrato" id="versatTipoContrato" class="form-select-prof">
                                <option value="">-- Todos los contratos --</option>
                                <?php 
                                $stmt_tc = $pdo->query("SELECT DISTINCT tipo_contrato FROM trabajadores WHERE tipo_contrato IS NOT NULL AND tipo_contrato != '' ORDER BY tipo_contrato");
                                while($tc = $stmt_tc->fetch()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($tc['tipo_contrato']); ?>">
                                        <?php echo htmlspecialchars($tc['tipo_contrato']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Filtros adicionales: Ocultar saldo en cero y Solo bajas -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="f-label fw-bold mb-2"><i class="fas fa-filter me-1" style="color:#f472b6;"></i> Opciones adicionales:</label>
                            <div class="d-flex gap-4 flex-wrap">
                                <label class="form-switch-prof mb-2">
                                    <input type="checkbox" class="form-check-input" name="versat_ocultar_cero" value="1" id="versatOcultarCero">
                                    <span style="font-size:0.9rem;">Ocultar saldo en cero</span>
                                </label>
                                <label class="form-switch-prof mb-2">
                                    <input type="checkbox" class="form-check-input" name="versat_solo_bajas" value="1" id="versatSoloBajas">
                                    <span style="font-size:0.9rem;">Solo bajas</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3" style="border-color: rgba(148, 163, 184, 0.25); opacity: 1;">

                    <!-- Botones de acción -->
                    <div class="row g-3">
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn-prof btn-danger-soft" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">Cancelar</button>
                            <button type="submit" class="btn-prof btn-success-solid ms-2" title="Generar reporte de vacaciones acumuladas" data-tooltip="Generar Reporte" data-tooltip-theme="success">
                                <i class="fas fa-eye"></i> Generar Reporte
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (count($descuadres) > 0): ?>
<!-- ==========================================
     MODAL DE DESCUADRES EN EL SUBMAYOR
     ========================================== -->
<div class="modal fade" id="modalDescuadres" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-prof">
            <div class="modal-header" style="border-bottom: 0.125rem solid rgba(239,68,68,0.4);">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Descuadres en el Submayor de Vacaciones</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <div class="alert" style="background:rgba(239,68,68,0.12); border:0.0625rem solid rgba(239,68,68,0.35); color:#fecaca; border-radius:0.625rem; padding:0.75rem 1rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    Se detectaron <strong><?php echo count($descuadres); ?></strong> trabajador<?php echo count($descuadres) === 1 ? '' : 'es'; ?> con descuadres
                    entre <strong>nóminas</strong>, <strong>submayor</strong> y la <strong>ficha del trabajador</strong>.
                    Corrija cada caso con un movimiento de <strong>Ajuste Manual</strong> (o <strong>Saldo Inicial de Apertura</strong>).
                    <?php if ($excluidos_no_acumular > 0): ?>
                        <br><span style="opacity:0.75;"><i class="fas fa-user-slash me-1"></i><?php echo $excluidos_no_acumular; ?> trabajador<?php echo $excluidos_no_acumular === 1 ? '' : 'es'; ?> con "No acumular vacaciones" excluid<?php echo $excluidos_no_acumular === 1 ? 'o' : 'os'; ?> del cuadre (su acumulación se paga en efectivo).</span>
                    <?php endif; ?>
                </div>
                <div class="table-prof-wrap" style="max-height:55vh; overflow-y:auto;">
                    <table class="table-prof" id="tablaDescuadres">
                        <thead>
                            <tr>
                                <th class="c-center">Expediente</th>
                                <th class="c-center">Estado</th>
                                <th class="c-left">Trabajador</th>
                                <th class="c-center"><span>Saldo </span><span>Submayor</span></th>
                                <th class="c-center"><span>Acumulado </span><span>Trabajador</span></th>
                                <th class="c-center"><span>Acumulado </span><span>Nóminas</span></th>
                                <th class="c-left">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($descuadres as $dc): ?>
                            <?php $diff_saldo = round($dc['saldo_submayor'] - $dc['acum_trab'], 2); ?>
                            <tr>
                                <td class="c-center fw-bold" style="color:#93c5fd;"><?php echo htmlspecialchars($dc['codigo']); ?></td>
                                <td class="c-center">
                                    <?php if ((int)($dc['activo'] ?? 0) === 1): ?>
                                        <span class="badge-op badge-activo"><i class="fas fa-user-check"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="badge-op badge-baja"><i class="fas fa-user-slash"></i> Baja</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($dc['nombre_completo']); ?></strong></td>
                                <td class="c-num"><?php echo number_format($dc['saldo_submayor'], 2); ?></td>
                                <td class="c-num"><?php echo number_format($dc['acum_trab'], 2); ?></td>
                                <td class="c-num"><?php echo number_format($dc['saldo_nominas'], 2); ?></td>
                                <td class="c-left">
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($dc['motivos'] as $motivo): ?>
                                            <?php if ($motivo === 'saldo'): ?>
                                                <span class="badge-op badge-excedido" title="Diferencia: <?php echo ($diff_saldo > 0 ? '+' : '') . number_format($diff_saldo, 2); ?> días"><i class="fas fa-balance-scale"></i> Saldo no coincide</span>
                                            <?php elseif (strpos($motivo, 'nomina_acum:') === 0): ?>
                                                <span class="badge-op badge-baja"><i class="fas fa-file-invoice"></i> <?php echo (int)substr($motivo, 12); ?> nómina(s) acumulada(s) sin submayor</span>
                                            <?php elseif (strpos($motivo, 'nomina_disfrute:') === 0): ?>
                                                <span class="badge-op badge-baja"><i class="fas fa-file-invoice"></i> <?php echo (int)substr($motivo, 16); ?> disfrute(s) sin submayor</span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $tot_sm = $tot_tr = $tot_nom = 0;
                            foreach ($descuadres as $dc) {
                                $tot_sm += $dc['saldo_submayor'];
                                $tot_tr += $dc['acum_trab'];
                                $tot_nom += $dc['saldo_nominas'];
                            }
                            ?>
                            <tr>
                                <td colspan="3" class="c-left" style="color:#aeb9cf;">Total descuadre (<?php echo count($descuadres); ?> trabajador<?php echo count($descuadres) === 1 ? '' : 'es'; ?>)</td>
                                <td class="c-num" style="color:#fca5a5;"><?php echo number_format($tot_sm, 2); ?></td>
                                <td class="c-num" style="color:#fca5a5;"><?php echo number_format($tot_tr, 2); ?></td>
                                <td class="c-num" style="color:#fca5a5;"><?php echo number_format($tot_nom, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-prof btn-danger-soft" data-bs-dismiss="modal" title="Entendido, cerrar" data-tooltip="Entendido" data-tooltip-theme="danger"><i class="fas fa-check me-2"></i>Entendido</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modalDescuadres');
    if (el && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(el).show();
    }
});
</script>
<?php endif; ?>

<!-- Botones flotantes de navegación rápida -->
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn to-bottom" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>

<script>
(function() {
    var btnTop = document.getElementById('btnScrollTop');
    var btnBottom = document.getElementById('btnScrollBottom');
    function actualizarVisibilidad() {
        var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        var y = window.scrollY || document.documentElement.scrollTop;
        btnTop.classList.toggle('hidden', y < 150);
        btnBottom.classList.toggle('hidden', maxScroll <= 150 || y > maxScroll - 150);
    }
    btnTop.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    btnBottom.addEventListener('click', function() {
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    });
    window.addEventListener('scroll', actualizarVisibilidad);
    actualizarVisibilidad();
})();

/* Lógica de exclusión mutua de los checkboxes del modal Versat */
(function () {
    var chkVersatOcultarCero = document.getElementById('versatOcultarCero');
    var chkVersatSoloBajas = document.getElementById('versatSoloBajas');
    if (chkVersatOcultarCero && chkVersatSoloBajas) {
        chkVersatOcultarCero.addEventListener('change', function () {
            if (this.checked && chkVersatSoloBajas.checked) {
                chkVersatSoloBajas.checked = false;
            }
        });
        chkVersatSoloBajas.addEventListener('change', function () {
            if (this.checked && chkVersatOcultarCero.checked) {
                chkVersatOcultarCero.checked = false;
            }
        });
    }
})();
</script>

</body>
</html>