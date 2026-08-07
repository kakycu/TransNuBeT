<?php
// modules/submayor_vacaciones.php - Refactorizado bajo Metodología Laboral Cubana (9.09%)

// 1. Carga de configuración de manera prioritaria (config.php iniciará la sesión de forma limpia)
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once '../config/database.php';
}

// 2. Control de seguridad por si la sesión no se inició en el paso anterior
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar privilegios de acceso
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';

// Configuración empresa
$config_empresa = ['nombre_empresa' => 'PDL TransNuBeT', 'jefe_proyecto' => 'Dainelys León Reyes', 'especialista_gestion' => 'Mailén Pérez García'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
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
    
    // Obtener días laborables mensuales desde configuración para cálculo del importe
    $stmt_dias_versat = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'");
    $dias_mensuales_versat = $stmt_dias_versat->fetchColumn();
    $dias_laborables_versat = $dias_mensuales_versat ? (int)$dias_mensuales_versat : 24;
    
    // Construir consulta base para el reporte
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
            t.vacaciones_acumuladas as saldo_dias,
            e.salario_mensual
        FROM trabajadores t
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
        LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        WHERE t.vacaciones_acumuladas > 0
    ";
    
    $params_versat = [];
    
    // Aplicar filtro de días mínimo
    if ($versat_dias_min !== '') {
        $sql_versat .= " AND t.vacaciones_acumuladas >= ?";
        $params_versat[] = floatval($versat_dias_min);
    }
    
    // Aplicar filtro de días máximo
    if ($versat_dias_max !== '') {
        $sql_versat .= " AND t.vacaciones_acumuladas <= ?";
        $params_versat[] = floatval($versat_dias_max);
    }
    
    // Aplicar filtro de tipo de contrato
    if ($versat_tipo_contrato !== '') {
        $sql_versat .= " AND t.tipo_contrato = ?";
        $params_versat[] = $versat_tipo_contrato;
    }
    
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
    
    // Procesar datos y calcular importes
    foreach ($reporte_data as &$row) {
        $row['importe'] = calcularImporteVacaciones(
            $row['saldo_dias'], 
            $row['salario_mensual'] ?? 0, 
            $dias_laborables_versat
        );
    }
    
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
        <title>Reporte Vacaciones Acumuladas - <?php echo strtoupper($versat_agrupar); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Arial', 'Helvetica', sans-serif; 
                background: white; 
                color: black; 
                padding: 20px;
            }
            .report-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #004B87;
                padding-bottom: 15px;
            }
            .report-header h1 {
                color: #004B87;
                font-size: 18pt;
                margin-bottom: 5px;
            }
            .report-header h2 {
                font-size: 14pt;
                margin-bottom: 5px;
            }
            .report-header p {
                font-size: 10pt;
                color: #555;
            }
            .filtros-aplicados {
                background: #f5f5f5;
                padding: 10px;
                margin-bottom: 20px;
                font-size: 9pt;
                border-left: 4px solid #004B87;
            }
            .grupo-container {
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            .grupo-header {
                background-color: #e8e8e8;
                padding: 10px;
                margin-top: 20px;
                margin-bottom: 10px;
                border-left: 4px solid #004B87;
                font-weight: bold;
            }
            .grupo-header h3 {
                font-size: 11pt;
                color: #004B87;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9pt;
            }
            th, td {
                border: 1px solid #ccc;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #004B87;
                color: white;
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
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 8pt;
                border-top: 1px solid #ccc;
                padding-top: 15px;
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
                body { padding: 0; }
                .no-print { display: none; }
            }
            .btn-print {
                background: #004B87;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                margin-bottom: 20px;
            }
            .btn-print:hover {
                background: #003366;
            }
        </style>
    </head>
    <body>
        <button class="btn-print no-print" onclick="window.print();">🖨️ Imprimir / Guardar PDF</button>
        <button class="btn-print no-print" onclick="window.close();" style="background: #666; margin-left: 10px;">✖ Cerrar</button>
        
        <div class="report-header">
            <div style="display: flex; align-items: center; gap: 20px; justify-content: center;">
                <?php if (!empty($logo_base64_global)): ?>
                    <div>
                        <img src="<?php echo $logo_base64_global; ?>" alt="Logo" style="max-height: 70px; max-width: 150px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <div>
                    <h1><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h1>
                    <h2>REPORTE DE VACACIONES ACUMULADAS</h2>
                    <p>Fecha de emisión: <?php echo date('d/m/Y H:i:s'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="filtros-aplicados">
            <strong>📊 Parámetros del reporte:</strong><br>
            • Agrupado por: <?php 
                switch($versat_agrupar) {
                    case 'area': echo "Área / Departamento"; break;
                    case 'centro_costo': echo "Centro de Costo"; break;
                    case 'contrato': echo "Tipo de Contrato"; break;
                    default: echo "Sin agrupación";
                }
            ?><br>
            • Rango de días: 
                <?php 
                $min_texto = ($versat_dias_min !== '') ? "≥ {$versat_dias_min} días" : "sin mínimo";
                $max_texto = ($versat_dias_max !== '') ? "≤ {$versat_dias_max} días" : "sin máximo";
                echo $min_texto . " | " . $max_texto;
                ?><br>
            • Tipo de contrato: <?php echo $versat_tipo_contrato !== '' ? htmlspecialchars($versat_tipo_contrato) : 'Todos'; ?><br>
            • Base de cálculo: <?php echo $dias_laborables_versat; ?> días laborables por mes (9.09%)
        </div>
        
        <?php if (empty($grupos)): ?>
            <div style="text-align: center; padding: 50px; color: #999;">
                <h3>No se encontraron trabajadores con vacaciones acumuladas</h3>
                <p>No hay datos que coincidan con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($grupos as $grupo): ?>
                <div class="grupo-container">
                    <div class="grupo-header">
                        <h3>
                            <?php 
                            switch ($versat_agrupar) {
                                case 'area':
                                    echo '📁 Área: ' . htmlspecialchars($grupo['nombre']);
                                    if ($grupo['codigo']) echo ' <span style="font-weight: normal;">(Código: ' . htmlspecialchars($grupo['codigo']) . ')</span>';
                                    break;
                                case 'centro_costo':
                                    echo '💰 Centro de Costo: ' . htmlspecialchars($grupo['nombre']);
                                    if ($grupo['codigo']) echo ' <span style="font-weight: normal;">(Código: ' . htmlspecialchars($grupo['codigo']) . ')</span>';
                                    break;
                                case 'contrato':
                                    echo '📄 Tipo de Contrato: ' . htmlspecialchars($grupo['nombre']);
                                    break;
                                default:
                                    echo '📊 ' . htmlspecialchars($grupo['nombre']);
                            }
                            ?>
                        </h3>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Trabajador</th>
                                <th>CI</th>
                                <th>Cargo</th>
                                <th class="text-right">Días Acumulados</th>
                                <th class="text-right">Importe (CUP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach ($grupo['trabajadores'] as $trabajador): 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $contador++; ?></td>
                                <td><?php echo htmlspecialchars($trabajador['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($trabajador['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($trabajador['ci']); ?></td>
                                <td><?php echo htmlspecialchars($trabajador['cargo'] ?? '-'); ?></td>
                                <td class="text-right"><?php echo number_format($trabajador['saldo_dias'], 2); ?> días</td>
                                <td class="text-right"><?php echo '$' . number_format($trabajador['importe'], 2, '.', ','); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="subtotal-row">
                                <td colspan="5" class="text-right"><strong>TOTAL DEL GRUPO:</strong> (<?php echo $grupo['total_trabajadores']; ?> trabajadores)</td>
                                <td class="text-right"><strong><?php echo number_format($grupo['total_dias'], 2); ?> días</strong></td>
                                <td class="text-right"><strong><?php echo '$' . number_format($grupo['total_importe'], 2, '.', ','); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <!-- TOTAL GENERAL -->
            <div class="grupo-container">
                <table style="margin-top: 20px;">
                    <tfoot>
                        <tr class="total-general-row">
                            <td colspan="5" class="text-right"><strong>TOTAL GENERAL DEL REPORTE:</strong> (<?php echo $total_general_trabajadores; ?> trabajadores)</td>
                            <td class="text-right"><strong><?php echo number_format($total_general_dias, 2); ?> días</strong></td>
                            <td class="text-right"><strong><?php echo '$' . number_format($total_general_importe, 2, '.', ','); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
        <?php endif; ?>
        
        <div class="footer">
            Documento generado por: <?php echo htmlspecialchars($user_nombre_completo); ?> | 
            Sistema de Gestión de Nóminas - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>
        </div>
        
        <!-- FIRMAS DE RESPONSABILIDAD CON LOGO -->
        <table style="width: 100%; margin-top: 50px; border: none;">
            <?php if (!empty($logo_base64_global)): ?>
            <tr>
                <td colspan="3" style="text-align: center; border: none; padding-bottom: 20px;">
                    <img src="<?php echo $logo_base64_global; ?>" alt="Logo" style="max-height: 50px; max-width: 100px; object-fit: contain;">
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                    <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                    <strong>Elaborado por:</strong><br><?php echo htmlspecialchars($user_nombre_completo); ?><br>
                    <span style="font-size: 7pt;">(Usuario Responsable)</span>
                </td>
                <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                    <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                    <strong>Revisado por:</strong><br><?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?><br>
                    <span style="font-size: 7pt;">(Especialista de Gestión)</span>
                </td>
                <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                    <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                    <strong>Aprobado por:</strong><br><?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?><br>
                    <span style="font-size: 7pt;">(Jefe de Proyecto)</span>
                </td>
            </tr>
        </table>
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

// Obtener áreas y centros de costo para los filtros
$areas = $pdo->query("SELECT id, nombre_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);
$centros_costo = $pdo->query("SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);

// Obtener trabajadores para filtros, modales y cálculo del lado del cliente
$stmt = $pdo->query("
    SELECT t.id, t.codigo, t.nombre_completo, e.salario_mensual 
    FROM trabajadores t 
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
    ORDER BY t.nombre_completo
");
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$trabajadores_map_js = [];
foreach ($trabajadores as $t) {
    $trabajadores_map_js[$t['id']] = [
        'salario_mensual' => floatval($t['salario_mensual'])
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
    SELECT t.id, t.codigo, t.nombre_completo, t.ci, t.foto_ruta, a.nombre_area, cp.nombre_cargo as cargo,
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submayor de Vacaciones | <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Libraries CSS -->
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="../css/datatables/1.13.6/buttons.dataTables.min.css">
    
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0c0c0c; overflow-x: hidden; color: #ffffff; }

    /* Windows 11 Acrylic Background */
    .win11-bg {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
    }
    .win11-bg::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                          radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    /* Glassmorphism Effect */
    .glass-card {
        background: rgba(28, 28, 35, 0.6); backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }
    .glass-card:hover { transform: translateY(-2px); background: rgba(35, 35, 45, 0.7); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }

    /* Sidebar Windows 11 Style */
    .win-sidebar {
        position: fixed; left: 0; top: 0; height: 100vh; width: 260px;
        background: rgba(20, 20, 25, 0.85); backdrop-filter: blur(30px);
        border-right: 1px solid rgba(255, 255, 255, 0.08); z-index: 1000;
        transition: all 0.3s ease; display: flex; flex-direction: column;
    }
    .win-sidebar.collapsed { width: 80px; }
    .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
    .win-sidebar.collapsed .nav-item { justify-content: center; padding: 12px; }
    .win-sidebar.collapsed .nav-item i { margin: 0; font-size: 1.5rem; }

    .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 20px; text-align: center; }
    .sidebar-logo h3 { font-size: 1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0; }
    .sidebar-logo small { font-size: 0.7rem; color: rgba(255, 255, 255, 0.5); }

    .sidebar-nav { flex: 1; padding: 0 12px; }
    .nav-item {
        display: flex; align-items: center; gap: 14px; padding: 12px 16px;
        margin-bottom: 6px; border-radius: 12px;
        color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
        cursor: pointer; text-decoration: none;
    }

    .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
    .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 3px solid #60a5fa; }
    .nav-item i { width: 24px; font-size: 1.2rem; text-align: center; }
    .nav-item span { font-size: 0.9rem; font-weight: 500; }

    /* Main Content */
    .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
    .main-container.expanded { margin-left: 80px; }

    /* Top Bar Windows 11 */
    .win-topbar {
        background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
        padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
        display: flex; justify-content: space-between; align-items: center;
        z-index: 100 !important; position: relative !important;
    }
    .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
    .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
    .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; color: white; }
    .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin: 4px 0 0; }

    /* User Menu & Dropdowns */
    .user-menu { display: flex; align-items: center; gap: 16px; }

    .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 1050 !important; }
    .user-avatar:hover { transform: scale(1.05); }

    .dropdown-menu { z-index: 1050 !important; position: absolute !important; }
    .user-menu .dropdown { position: relative !important; z-index: 1050 !important; }
    .dropdown-menu-win {
        background: rgba(32, 32, 40, 0.98) !important; backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 12px !important;
        padding: 8px !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
    }
    .dropdown-menu-win .dropdown-item { color: #ffffff !important; border-radius: 8px !important; padding: 10px 16px !important; font-size: 0.9rem !important; }
    .dropdown-menu-win .dropdown-item:hover { background: rgba(96, 165, 250, 0.2) !important; color: #ffffff !important; }
    .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; }
    .dropdown-menu-win .dropdown-divider { border-color: rgba(255, 255, 255, 0.1) !important; }
    .dropdown-menu-win .dropdown-item-text { color: rgba(255, 255, 255, 0.8) !important; }
    .dropdown-menu-win .dropdown-item small { font-size: 0.65rem; color: rgba(255,255,255,0.6) !important; }
    .dropdown-menu-win .dropdown-item:hover small { color: #ffffff !important; }

    /* Text visibility overrides */
    .text-muted { color: rgba(255, 255, 255, 0.65) !important; }

    .btn-win { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; border-radius: 10px; color: white; font-size: 0.85rem; transition: all 0.2s; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; color: white; }
    .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; padding: 10px 20px; border-radius: 10px; color: white; transition: all 0.3s; }
    .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-2px); color: white; }
    .btn-win-danger { background: linear-gradient(135deg, #dc3545, #c82333); border: none; padding: 10px 20px; border-radius: 10px; color: white; }
    .btn-win-warning { background: linear-gradient(135deg, #ffc107, #e0a800); border: none; padding: 10px 20px; border-radius: 10px; color: #000; font-weight: 600; }
    .btn-win-success { background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 10px 20px; border-radius: 10px; color: white; }
    .btn-win-sm { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); padding: 5px 10px; border-radius: 6px; color: white; font-size: 0.7rem; transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
    .btn-win-sm:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; color: white; }

    /* Tarjetas de estadísticas (patrón común de la app) */
    .stat-card {
        background: linear-gradient(135deg, rgba(28, 28, 35, 0.7), rgba(20, 20, 28, 0.8));
        border-radius: 16px; padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.3s ease; z-index: 1 !important;
    }
    .stat-card:hover { transform: translateY(-3px); border-color: rgba(96, 165, 250, 0.3); }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
    .stat-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
    .stat-sub { font-size: 0.75rem; color: rgba(255, 255, 255, 0.55); margin-top: 2px; }

    /* Píldora informativa (factor / contadores) */
    .badge-factor { background: rgba(96, 165, 250, 0.15); border: 1px solid rgba(96, 165, 250, 0.4); color: #93c5fd; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 5px; }

    .form-control-custom, .form-select { background: rgba(20, 20, 25, 0.8) !important; border: 1px solid rgba(255,255,255,0.15) !important; color: white !important; border-radius: 10px !important; padding: 10px 14px !important; }
    .form-control-custom:focus, .form-select:focus { border-color: #60a5fa !important; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2) !important; }
    .form-label { color: rgba(255,255,255, 0.9) !important; font-weight: 500; font-size: 0.9rem; margin-bottom: 6px; }

    /* Placeholders legibles sobre fondo oscuro */
    input::placeholder, textarea::placeholder, .form-control::placeholder, .form-select::placeholder {
        color: rgba(255, 255, 255, 0.45) !important;
        opacity: 1 !important;
    }
    option { background: #1a1a2e; color: white; }
    
    .form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 12px center !important;
        background-size: 14px 12px !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        padding-right: 32px !important;
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
    .dataTables_wrapper .dataTables_paginate .paginate_button { color: #ffffff; background: rgba(20, 20, 25, 0.8); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; padding: 6px 12px; margin: 0 3px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(96, 165, 250, 0.3); border-color: #60a5fa; color: #ffffff; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-color: #60a5fa; color: #ffffff; }
    .dataTables_wrapper .dataTables_length select, 
    .dataTables_wrapper .dataTables_filter input { background: rgba(20, 20, 25, 0.8); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #ffffff; }
    
    /* Tablas en pantalla - texto blanco */
    .table-custom { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .table-custom th, .table-custom td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: left; vertical-align: middle; }
    .table-custom th { background: rgba(20, 20, 30, 0.5); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: rgba(255,255,255,0.8); text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .table-custom td { color: #ffffff; }
    .table-custom tr:hover td { background: rgba(255,255,255,0.03); }
    
    .table-custom tfoot td {
        background: #181820;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
        color: #ffffff;
    }

    /* DataTables cuerpo */
    table.dataTable tbody tr td { color: #ffffff; }

    /* Badges para pantalla */
    .badge-acumulacion { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #4ade80; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
    .badge-disfrute { background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fbbf24; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
    .badge-ajuste { background: rgba(96, 165, 250, 0.2); border: 1px solid #60a5fa; color: #93c5fd; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }

    /* Contenedor de Tabla (el scroll vertical lo gestiona DataTables con scrollY) */
    .data-table-wrapper {
        width: 100%;
        overflow-x: auto !important;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        background: rgba(15, 15, 20, 0.4);
        position: relative;
    }

    /* Encabezados fijos de DataTables (scrollY) */
    .dataTables_scrollHead .table-custom thead th {
        background: #1b1b24 !important;
        color: rgba(255, 255, 255, 0.85);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .dataTables_scrollFoot .table-custom tfoot td {
        background: #181820 !important;
        color: #ffffff;
        font-weight: 700;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    /* Encabezado fijo de la tabla del auxiliar (modal, tabla simple) */
    #tablaHistorialIndividual thead th { position: sticky; background: #1b1b24 !important; z-index: 5; }
    #tablaHistorialIndividual thead tr:first-child th { top: 0; }
    #tablaHistorialIndividual thead tr:last-child th { top: 39px; }

    .text-muted-win {
        color: rgba(255, 255, 255, 0.6) !important;
        font-weight: 500;
        letter-spacing: 0.3px;
    }

    .footer-card { margin-top: 24px; padding: 20px; font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); }
    .modal-content-modern { background: rgba(32, 32, 40, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; color: white; }
    .btn-close-custom { background: transparent; border: 2px solid white; border-radius: 8px; width: 40px; height: 40px; color: white; font-size: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .btn-close-custom:hover { background-color: #ef4444; border-color: #ef4444; }

    #liveClock { display: inline-block; min-width: 85px; text-align: center; font-variant-numeric: tabular-nums; letter-spacing: 0.5px; }
    .date-badge { background: rgba(255, 255, 255, 0.08); padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; color: white; }

    .info-card { background: rgba(255, 255, 255, 0.03); border-left: 4px solid #60a5fa; border-radius: 8px; padding: 12px 16px; }
    .info-card-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); margin-bottom: 4px; }
    .info-card-value { font-size: 1.1rem; font-weight: 600; color: white; }

    .dt-buttons { display: flex !important; gap: 5px; flex-wrap: wrap; margin-right: 10px; }
    .dataTables_length { display: block !important; margin-right: 15px; }
    .dataTables_length label { color: rgba(255, 255, 255, 0.7) !important; display: flex !important; align-items: center; gap: 8px; white-space: nowrap; }

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
            margin: 0 !important;
            padding: 0 !important;
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
            border: 1px solid #000000 !important;
            font-weight: bold !important;
            font-size: 11pt !important;
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
            border: 1px solid #000000 !important;
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
            border: 1px solid #000000 !important;
            color: #000000 !important;
            padding: 2px 8px !important;
        }

        /* Filas de totales */
        tfoot td, tfoot th,
        .total-row td, .subtotal-row td {
            background: white !important;
            background-color: white !important;
            color: #000000 !important;
            font-weight: bold !important;
            border: 1px solid #000000 !important;
        }

        /* Firmas */
        .signatures, .signatures td, .signatures div,
        .footer-note, .footer-note * {
            color: #000000 !important;
            background: white !important;
            border: none !important;
        }
        
        .signatures-line {
            border-top: 1px solid #000000 !important;
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
</style>

</head>
<body>

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
        <!-- Dropdown de Opciones (se mantiene) -->
        <div class="dropdown">
            <button class="btn-win" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: none;">
                <i class="fas fa-cog me-1"></i> Opciones <i class="fas fa-chevron-down ms-2" style="color: rgba(255,255,255,0.6);"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#" id="btnBackupManual">
                        <i class="fas fa-database me-2" style="color: #fbbf24;"></i> 
                        Salva del Sistema Manual
                        <small class="d-block text-muted" style="font-size: 0.65rem;">Crear copia de seguridad (SQL)</small>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalAjuste">
                        <i class="fas fa-sliders-h me-2" style="color: #3b82f6;"></i> 
                        Ajustar / Saldo Inicial
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" id="btnReporteFisico">
                        <i class="fas fa-print me-2" style="color: #10b981;"></i> 
                        Reporte Físico / Impresión
                        <small class="d-block text-muted" style="font-size: 0.65rem;">Imprimir reporte en formato tradicional</small>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalReporteVersat">
                        <i class="fas fa-chart-bar me-2" style="color: #10b981;"></i> 
                        Reporte Vac. Acum.
                        <small class="d-block text-muted" style="font-size: 0.65rem;">Vacaciones acumuladas por área/centro costo</small>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1" style="font-size: 0.7rem;">Personalizar Vista</h6></li>
                <li><a class="dropdown-item" href="#" id="menuColumnas"><i class="fas fa-columns me-2" style="color: #60a5fa;"></i> Mostrar/Ocultar Columnas</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1" style="font-size: 0.7rem;">Exportar y Reportes</h6></li>
                <li><a class="dropdown-item" href="#" id="menuExportPrint"><i class="fas fa-print text-warning me-2"></i> Imprimir Reporte</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportPDF"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportWord"><i class="fas fa-file-word text-primary me-2"></i> Exportar a Word</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportExcel"><i class="fas fa-file-excel text-success me-2"></i> Exportar a Excel</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportCSV"><i class="fas fa-file-csv text-info me-2"></i> Exportar a CSV</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportTXT"><i class="fas fa-file-alt text-secondary me-2"></i> Exportar a TXT</a></li>
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
    ?>
    <div class="glass-card mb-4 p-4 fade-in-up">
        <div class="d-flex align-items-start gap-3 flex-wrap">
            <i class="fas fa-scale-balanced fa-2x" style="color: #60a5fa;"></i>
            <div class="flex-grow-1" style="min-width: 260px;">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <strong>Bases de Auditoría Cubana</strong>
                    <span class="badge-factor"><i class="fas fa-percent me-1"></i>Base 9.09% · Ley 116</span>
                </div>
                <p class="mb-1 text-muted-win" style="font-size: 0.85rem;">
                    El submayor acumula el <strong style="color:#4ade80;">tiempo de descanso</strong> y el <strong style="color:#4ade80;">importe acumulado</strong> sobre la base del 9.09% del total de salarios devengados en el período de pago.
                    Toda baja laboral o disfrute físico exige la liquidación del saldo que muestra este libro.
                </p>
                <small class="text-muted-win"><i class="fas fa-calendar-week me-1" style="color:#60a5fa;"></i>Período consultado: <strong><?php echo htmlspecialchars($rango_string); ?></strong> · <i class="fas fa-users me-1" style="color:#4ade80;"></i><?php echo count($saldos); ?> trabajadores</small>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Resumen General -->
    <div class="row g-3 mb-4 fade-in-up">
        <div class="col">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-success"><?php echo number_format($total_acumulado, 2); ?> <small style="font-size:0.7rem;">días</small></div>
                        <div class="stat-label">Días Acumulados</div>
                        <div class="stat-sub"><?php echo formatearMoneda($total_devengado); ?> devengado</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15);"><i class="fas fa-calendar-plus" style="color: #10b981;"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-warning"><?php echo number_format($total_disfrutado, 2); ?> <small style="font-size:0.7rem;">días</small></div>
                        <div class="stat-label">Días Disfrutados</div>
                        <div class="stat-sub"><?php echo formatearMoneda($total_importe_disfrutado); ?> disfrutado</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15);"><i class="fas fa-calendar-minus" style="color: #f59e0b;"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-info"><?php echo number_format($total_saldo_inicial, 2); ?> <small style="font-size:0.7rem;">días</small></div>
                        <div class="stat-label">Saldo Inicial</div>
                        <div class="stat-sub">Acumulado previo al período</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15);"><i class="fas fa-book-open" style="color: #60a5fa;"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-primary"><?php echo number_format($total_saldo, 2); ?> <small style="font-size:0.7rem;">días</small></div>
                        <div class="stat-label">Saldo Pendiente</div>
                        <div class="stat-sub">Fondo acumulado activo</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15);"><i class="fas fa-wallet" style="color: #8b5cf6;"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros de Búsqueda -->
    <div class="glass-card mb-4 fade-in-up">
        <form method="GET" id="filtroForm" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Año</label>
                <select name="consulta_anio" id="consultaAnio" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php for($y = date('Y')-2; $y <= date('Y')+5; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $consulta_anio == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-moon me-1"></i> Mes</label>
                <select name="consulta_mes" id="consultaMes" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php for($m = 1; $m <= 12; $m++): $m_pad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $m_pad; ?>" <?php echo $consulta_mes == $m_pad ? 'selected' : ''; ?>><?php echo nombreMesEspanol($m_pad); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-calendar-day me-1"></i> Desde (Fecha)</label>
                <input type="date" name="periodo_desde" id="periodoDesde" class="form-control" value="<?php echo $periodo_desde; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-calendar-day me-1"></i> Hasta (Fecha)</label>
                <input type="date" name="periodo_hasta" id="periodoHasta" class="form-control" value="<?php echo $periodo_hasta; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-user me-1"></i> Trabajador</label>
                <select name="trabajador_id" id="filtroTrabajador" class="form-select">
                    <option value="">-- Todos los Trabajadores --</option>
                    <?php foreach ($trabajadores as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $trabajador_id == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['codigo'] . ' - ' . $t['nombre_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label"><i class="fas fa-building me-1"></i> Área</label>
                <select name="area_id" id="filtroArea" class="form-select">
                    <option value="">-- Todas --</option>
                    <?php foreach ($areas = $pdo->query("SELECT id, nombre_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC) as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $area_id == $a['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['nombre_area']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label"><i class="fas fa-chart-pie me-1"></i> Centro Costo</label>
                <select name="centro_costo_id" id="filtroCentroCosto" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php foreach ($centros_costo = $pdo->query("SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC) as $cc): ?>
                        <option value="<?php echo $cc['id']; ?>" <?php echo $centro_costo_id == $cc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label"><i class="fas fa-tags me-1"></i> Movimiento</label>
                <select name="tipo_movimiento" id="filtroTipoMovimiento" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="acumulacion" <?php echo $tipo_movimiento == 'acumulacion' ? 'selected' : ''; ?>>Acumulación</option>
                    <option value="disfrute" <?php echo $tipo_movimiento == 'disfrute' ? 'selected' : ''; ?>>Disfrute</option>
                    <option value="ajuste" <?php echo $tipo_movimiento == 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                </select>
            </div>
            <div class="col-md-auto text-end flex-grow-1">
                <button type="submit" class="btn-win btn-win-primary py-2 px-3" title="Filtrar"><i class="fas fa-search"></i> Filtrar</button>
                <button type="button" class="btn-win btn-win-danger py-2 px-3 ms-2" id="btnLimpiarFiltros" title="Limpiar Filtros"><i class="fas fa-eraser"></i></button>
            </div>
        </form>
    </div>

<!-- Tabla de Saldos Consolidados por Trabajador -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.1s;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-chart-pie me-2" style="color: #60a5fa;"></i>Consolidado de Saldos
            <span class="badge-factor ms-2"><i class="fas fa-users me-1"></i><?php echo count($saldos); ?> registros</span>
        </h5>
    </div>
    <div class="data-table-wrapper">
        <table class="table-custom" id="tablaSaldos">
            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Trabajador</th>
                    <th>Cargo</th>
                    <th>Área</th>
                    <th>Días Acum.</th>
                    <th>Importe Acum.</th>
                    <th>Días Disfrutados</th>
                    <th>Importe Disfrutado</th>
                    <th>Días Ajuste</th>
                    <th>Importe Ajuste</th>
                    <th>Saldo Inicial</th>
                    <th>Saldo al Cierre</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saldos as $s): ?>
                <tr data-trabajador-id="<?php echo $s['id']; ?>" data-ci="<?php echo htmlspecialchars($s['ci']); ?>">
                    <td class="text-center fw-bold"><?php echo htmlspecialchars($s['codigo']); ?></td>
                    <td><strong><?php echo htmlspecialchars($s['nombre_completo']); ?></strong></td>
                    <td><?php echo htmlspecialchars($s['cargo'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['nombre_area'] ?? '-'); ?></td>
                    <td class="text-end"><?php echo number_format($s['total_acumulado'], 2); ?></td>
                    <td class="text-end"><?php echo formatearMoneda($s['importe_acumulado']); ?></td>
                    <td class="text-end"><?php echo number_format($s['total_disfrutado'], 2); ?></td>
                    <td class="text-end"><?php echo formatearMoneda($s['importe_disfrutado']); ?></td>
                    <td class="text-end"><?php echo number_format($s['total_ajuste_dias'], 2); ?></td>
                    <td class="text-end"><?php echo formatearMoneda($s['total_ajuste_importe']); ?></td>
                    <td class="text-end"><?php echo number_format($s['saldo_inicial'], 2); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($s['saldo_cierre'], 2); ?></td>
                    <td class="text-center">
                        <button type="button" class="btn-win btn-win-sm me-1 py-1" onclick="verHistorialTrabajador(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nombre_completo']); ?>')" title="Ver Auxiliar Dinámico">
                            <i class="fas fa-book"></i> Auxiliar
                        </button>
                        <a href="?trabajador_id=<?php echo $s['id']; ?>&consulta_anio=<?php echo $consulta_anio; ?>&consulta_mes=<?php echo $consulta_mes; ?>&periodo_desde=<?php echo $periodo_desde; ?>&periodo_hasta=<?php echo $periodo_hasta; ?>&area_id=<?php echo $area_id; ?>&centro_costo_id=<?php echo $centro_costo_id; ?>" class="btn-win btn-win-sm py-1" title="Ver Vista Detallada">
                            <i class="fas fa-folder-open"></i> Vista Fija
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" class="text-end"><strong>TOTALES CONSOLIDADOS:</strong></td>
                    <td class="text-end"><strong><?php echo number_format($total_acumulado, 2); ?></strong></td>
                    <td class="text-end"><strong><?php echo formatearMoneda($total_devengado); ?></strong></td>
                    <td class="text-end"><strong><?php echo number_format($total_disfrutado, 2); ?></strong></td>
                    <td class="text-end"><strong><?php echo formatearMoneda($total_importe_disfrutado); ?></strong></td>
                    <td class="text-end"><strong><?php echo number_format($total_ajuste_dias, 2); ?></strong></td>
                    <td class="text-end"><strong><?php echo formatearMoneda($total_ajuste_importe); ?></strong></td>
                    <td class="text-end"><strong><?php echo number_format($total_saldo_inicial, 2); ?></strong></td>
                    <td class="text-end"><strong><?php echo number_format($total_saldo, 2); ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>


    <!-- DETALLE CRONOLÓGICO INDIVIDUAL (SUBMAYOR CON SALDO CORRIDO) -->
    <?php if ($trabajador_id && isset($detalle_trabajador)): ?>
    <div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.15s;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-book-open me-2" style="color: #60a5fa;"></i>
                Libro Submayor de Vacaciones de: <strong><?php echo htmlspecialchars($resumen_trabajador['nombre_completo'] ?? ''); ?></strong>
            </h5>
            <a href="submayor_vacaciones.php" class="btn-win btn-win-sm"><i class="fas fa-times"></i> Cerrar Vista</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="info-card">
                    <div class="info-card-label">Expediente / Ficha Técnica</div>
                    <div class="info-card-value text-white"><?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <div class="info-card-label">Cargo</div>
                    <div class="info-card-value text-white"><?php echo htmlspecialchars($resumen_trabajador['cargo'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <div class="info-card-label">Área / Departamento</div>
                    <div class="info-card-value text-white"><?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <div class="info-card-label">Salario Base Escala</div>
                    <div class="info-card-value text-success"><?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="data-table-wrapper">
            <table class="table-custom" id="tablaDetalleIndividual">
                <thead>
                    <tr>
                        <th rowspan="2">Fecha Mov.</th>
                        <th rowspan="2">Período de Nómina</th>
                        <th rowspan="2">Operación / Concepto</th>
                        <th colspan="2" class="text-success">ENTRADAS (+)</th>
                        <th colspan="2" class="text-warning">SALIDAS (-)</th>
                        <th colspan="2" class="text-info" style="background: rgba(0,120,212,0.1) !important;">SALDOS CORRIDOS</th>
                        <th rowspan="2">Referencia / Observaciones</th>
                    </tr>
                    <tr>
                        <th class="text-success">Días</th>
                        <th class="text-success">Importe</th>
                        <th class="text-warning">Días</th>
                        <th class="text-warning">Importe</th>
                        <th class="text-info">Días</th>
                        <th class="text-info">Importe</th>
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

                        if ($es_entrada) {
                            $saldo_dias_corrido += $dias_ent;
                            $saldo_importe_corrido += $imp_ent;
                        } else {
                            $saldo_dias_corrido -= $dias_sal;
                            $saldo_importe_corrido -= $imp_sal;
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                        <td class="text-center"><?php echo date('m/Y', strtotime($mov['periodo_desde'])); ?></td>
                        <td class="text-center">
                            <?php if ($mov['tipo_movimiento'] === 'acumulacion'): ?>
                                <span class="badge-acumulacion"><i class="fas fa-plus"></i> Acumulación</span>
                            <?php elseif ($mov['tipo_movimiento'] === 'disfrute'): ?>
                                <span class="badge-disfrute"><i class="fas fa-minus"></i> Disfrute</span>
                            <?php else: ?>
                                <span class="badge-ajuste"><i class="fas fa-balance-scale"></i> Ajuste Manual</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-success"><?php echo $dias_ent > 0 ? number_format($dias_ent, 2) : '-'; ?></td>
                        <td class="text-end text-success"><?php echo $imp_ent > 0 ? formatearMoneda($imp_ent) : '-'; ?></td>
                        <td class="text-end text-warning"><?php echo $dias_sal > 0 ? number_format($dias_sal, 2) : '-'; ?></td>
                        <td class="text-end text-warning"><?php echo $imp_sal > 0 ? formatearMoneda($imp_sal) : '-'; ?></td>
                        
                        <td class="text-end text-info fw-bold" style="background: rgba(0,120,212,0.05) !important;">
                            <?php echo number_format($saldo_dias_corrido, 2); ?>
                        </td>
                        <td class="text-end text-info fw-bold" style="background: rgba(0,120,212,0.05) !important;">
                            <?php echo formatearMoneda($saldo_importe_corrido); ?>
                        </td>
                        
                        <td><small><?php echo htmlspecialchars($mov['referencia'] ?? '-'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($detalle_trabajador)): ?>
                        <tr><td colspan="10" class="text-center text-muted-win py-4">No se han registrado movimientos contables para este trabajador.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla General de Historial de Movimientos -->
    <div class="glass-card fade-in-up" style="animation-delay: 0.2s;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-history me-2" style="color: #60a5fa;"></i>Historial de Operaciones
                <span class="badge-factor ms-2"><i class="fas fa-exchange-alt me-1"></i><?php echo count($movimientos); ?> movimientos</span>
            </h5>
        </div>
        <div class="data-table-wrapper">
            <table class="table-custom" id="tablaMovimientos">
                <thead>
                    <tr>
                        <th>Fecha Mov.</th>
                        <th>Período</th>
                        <th>Expediente</th>
                        <th>Trabajador</th>
                        <th>Cargo</th>
                        <th>Área</th>
                        <th>Operación</th>
                        <th>Días</th>
                        <th>Importe</th>
                        <th>Referencia / Justificación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                    <tr>
                        <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($m['fecha_movimiento'])); ?></td>
                        <td class="text-center"><?php echo date('m/Y', strtotime($m['periodo_desde'])); ?></td>
                        <td class="text-center fw-bold"><?php echo htmlspecialchars($m['codigo']); ?></td>
                        <td><strong><?php echo htmlspecialchars($m['nombre_completo']); ?></strong></td>
                        <td><?php echo htmlspecialchars($m['cargo'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($m['nombre_area'] ?? '-'); ?></td>
                        <td class="text-center">
                            <?php if ($m['tipo_movimiento'] === 'acumulacion'): ?>
                                <span class="badge-acumulacion"><i class="fas fa-calendar-plus me-1"></i>Acumulado</span>
                            <?php elseif ($m['tipo_movimiento'] === 'disfrute'): ?>
                                <span class="badge-disfrute"><i class="fas fa-calendar-minus me-1"></i>Disfrutado</span>
                            <?php else: ?>
                                <span class="badge-ajuste"><i class="fas fa-cog me-1"></i>Ajuste</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold <?php echo $m['tipo_movimiento'] === 'acumulacion' ? 'text-success' : 'text-warning'; ?>">
                            <?php echo number_format($m['dias'], 2); ?>
                        </td>
                        <td class="text-end fw-bold"><?php echo formatearMoneda($m['importe']); ?></td>
                        <td><small><?php echo htmlspecialchars($m['referencia'] ?? '-'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</div>

</div>

<!-- ==========================================
     MODAL DE REGISTRO / AJUSTE METODOLÓGICO
     ========================================== -->
<div class="modal fade" id="modalAjuste" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h me-2" style="color: #60a5fa;"></i>Registrar Ajuste o Saldo Inicial</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <form id="ajusteVacacionesForm" method="POST">
                <input type="hidden" name="action" value="registrar_movimiento">
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Seleccionar Trabajador <span class="text-danger">*</span></label>
                            <select name="trabajador_id" id="modalTrabajadorSelect" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($trabajadores as $t): ?>
                                    <option value="<?php echo $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['codigo'] . ' - ' . $t['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Movimiento <span class="text-danger">*</span></label>
                            <select name="tipo_movimiento" class="form-select" required>
                                <option value="ajuste">Ajuste Metodológico (Ajuste)</option>
                                <option value="acumulacion">Saldo Inicial de Apertura (Acumulación)</option>
                                <option value="disfrute">Disfrute Manual (Disfrute)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Días de Vacación <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="dias" id="inputDiasAjuste" class="form-control" placeholder="Ej: 2.00 o 22.00" required>
                            <div class="form-text" style="color: rgba(255,255,255,0.5); font-size: 0.72rem;"><i class="fas fa-info-circle me-1"></i>Positivo acumula / entrega saldo; negativo descuenta (disfrute).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Importe del Período ($ CUP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.02" name="importe" id="inputImporteAjuste" class="form-control" placeholder="0.00" required readonly style="background-color: rgba(20,20,25,0.4); color: #cbd5e1 !important; cursor: not-allowed;">
                            <div class="form-text" style="color: rgba(255,255,255,0.5); font-size: 0.72rem;"><i class="fas fa-calculator me-1"></i>Calculado: días × salario base ÷ <?php echo (int)$dias_laborables; ?> días laborables.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Período Desde</label>
                            <input type="date" name="periodo_desde" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Período Hasta</label>
                            <input type="date" name="periodo_hasta" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Referencia Documental / Justificación de la Operación <span class="text-danger">*</span></label>
                            <input type="text" name="referencia" class="form-control" placeholder="Ej: Saldo Inicial según auditoría de transito de sistema / Resolución No. X" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" placeholder="Detalles contables adicionales..." rows="2" style="background-color: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: white;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-win btn-win-primary btn-win-sm">Aplicar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL: LIBRO AUXILIAR INDIVIDUAL DETALLADO (AJAX)
     ========================================== -->
<div class="modal fade modal-glass" id="modalHistorialIndividual" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-book-open me-2" style="color: #60a5fa;"></i>
                    <span>Libro Auxiliar Individual: </span><strong id="lblTrabajadorHistorial" style="color: #a78bfa;">-</strong>
                </h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-card-label">Cargo asignado</div>
                            <div class="info-card-value text-white" id="modalCargoTrabajador" style="font-size: 0.95rem;">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-card-label">Área o Departamento</div>
                            <div class="info-card-value text-white" id="modalAreaTrabajador" style="font-size: 0.95rem;">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card" style="border-left-color: #10b981;">
                            <div class="info-card-label" style="color: #10b981;">Saldo acumulado actual de vacaciones</div>
                            <div class="info-card-value text-success" id="modalSaldoTrabajador" style="font-size: 1.1rem; font-weight: bold;">-</div>
                        </div>
                    </div>
                </div>

                <div class="data-table-wrapper" style="max-height: 420px; overflow-y: auto;">
                    <table class="table-custom" id="tablaHistorialIndividual">
                        <thead>
                            <tr>
                                <th rowspan="2">Fecha Mov.</th>
                                <th rowspan="2">Período Nómina</th>
                                <th rowspan="2">Operación / Concepto</th>
                                <th colspan="2" style="color: #4ade80;">ENTRADAS (+)</th>
                                <th colspan="2" style="color: #fbbf24;">SALIDAS (-)</th>
                                <th colspan="2" style="color: #60a5fa; background: rgba(0,120,212,0.1) !important;">SALDOS CORRIDOS</th>
                                <th rowspan="2">Referencia / Observaciones</th>
                            </tr>
                            <tr>
                                <th style="color: #4ade80;">Días</th>
                                <th style="color: #4ade80;">Importe</th>
                                <th style="color: #fbbf24;">Días</th>
                                <th style="color: #fbbf24;">Importe</th>
                                <th style="color: #60a5fa; background: rgba(0,120,212,0.05) !important;">Días</th>
                                <th style="color: #60a5fa; background: rgba(0,120,212,0.05) !important;">Importe</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBodyIndividual">
                            <tr><td colspan="10" class="text-center py-4 text-muted-win">No hay datos para mostrar en este momento. Baseline</td>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal">Cerrar Auxiliar</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/datatables/1.13.6/dataTables.buttons.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.html5.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.print.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.colVis.min.js"></script>
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>

<script>
// Variables globales para la impresión (pasadas desde PHP)
var globalNombreEmpresa = '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>';
var globalUsuarioNombre = '<?php echo htmlspecialchars($user_nombre_completo); ?>';
var globalPeriodoFiltroTexto = '<?php echo $rango_string; ?>';
var globalDiasLaborables = <?php echo $dias_laborables; ?>;
var globalJefeProyecto = '<?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?>';
var globalEspecialistaGestion = '<?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?>';
var globalLogoBase64 = '<?php echo $logo_base64; ?>';

// Manejo de la navegación del Sidebar Windows 11
const sidebar = document.getElementById('winSidebar');
const mainContainer = document.getElementById('mainContainer');
if (localStorage.getItem('winSidebarCollapsed') === 'true') {
    sidebar?.classList.add('collapsed');
    mainContainer?.classList.add('expanded');
}
document.getElementById('sidebarToggleBtn')?.addEventListener('click', () => {
    sidebar?.classList.toggle('collapsed');
    mainContainer?.classList.toggle('expanded');
    localStorage.setItem('winSidebarCollapsed', sidebar?.classList.contains('collapsed'));
});


// Inicialización de las tablas con DataTables
$(document).ready(function() {
    var logoBase64 = '<?php echo $logo_base64; ?>';
    var nombreEmpresa = '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>';
    var usuarioNombre = '<?php echo htmlspecialchars($user_nombre_completo); ?>';
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var periodoFiltroTexto = '<?php echo $rango_string; ?>';
    
    const trabajadoresData = <?php echo json_encode($trabajadores_map_js); ?>;

    function calcularImporteAutomatico() {
        const trabajadorId = $('#modalTrabajadorSelect').val();
        const diasVal = parseFloat($('#inputDiasAjuste').val()) || 0;
        
        if (trabajadorId && trabajadoresData[trabajadorId]) {
            const salarioMensual = trabajadoresData[trabajadorId].salario_mensual;
            const valorDia = salarioMensual / diasLaborables;
            const importeTotal = diasVal * valorDia;
            $('#inputImporteAjuste').val(importeTotal.toFixed(2));
        } else {
            $('#inputImporteAjuste').val('0.00');
        }
    }
    $('#modalTrabajadorSelect').on('change', calcularImporteAutomatico);
    $('#inputDiasAjuste').on('input', calcularImporteAutomatico);

    $('#consultaAnio, #consultaMes, #periodoDesde, #periodoHasta, #filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroTipoMovimiento').on('change', function() {
        if (this.id === 'consultaAnio' || this.id === 'consultaMes') {
            $('#periodoDesde, #periodoHasta').val('');
        }
        $('#filtroForm').submit();
    });

    $('#btnLimpiarFiltros').on('click', function() {
        window.location.href = 'submayor_vacaciones.php';
    });
    
    $('#btnReporteFisico').on('click', function(e) {
        e.preventDefault();
        imprimirReporteFisico();
    });
    
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
        "paginate": {
            "first": "Primero",
            "last": "Último",
            "next": "Siguiente",
            "previous": "Anterior"
        },
        "aria": {
            "sortAscending": ": activar para ordenar de manera ascendente",
            "sortDescending": ": activar para ordenar de manera descendente"
        },
        buttons: {
            colvisRestore: '<i class="fas fa-undo me-1"></i>Restaurar columnas'
        }
    };

    // Fábrica de botones DataTables: recibe el arreglo de columnas a exportar
    // para evitar el error "t.aoColumns[e] is undefined" cuando una tabla tiene
    // menos columnas que las referenciadas en exportOptions.
    function crearBotonesDataTable(colsExportar) {
        return [
        {
            extend: 'colvis',
            text: '<i class="fas fa-columns me-1"></i> Columnas',
            className: 'btn-win-sm buttons-colvis',
            postfixButtons: ['colvisRestore']
        },
        {
            extend: 'print',
            text: '<i class="fas fa-print text-warning me-2"></i> Imprimir',
            className: 'btn-win-sm',
            title: '',
            exportOptions: {
                columns: colsExportar
            },
            customize: function(win) {
                var styleElement = win.document.createElement('style');
                styleElement.type = 'text/css';
                styleElement.innerHTML = `
                    body {
                        background: #ffffff !important;
                        background-color: #ffffff !important;
                        color: #000000 !important;
                        font-family: Arial, Helvetica, sans-serif !important;
                        font-size: 10pt !important;
                    }
                    table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                        background-color: #ffffff !important;
                        color: #000000 !important;
                    }
                    .table-custom thead tr:first-child th,
                    .table-custom thead tr:last-child th,
                    .table-custom thead tr th,
                    .table-custom th,
                    .dataTable thead th,
                    table thead th,
                    th {
                        background: #e8e8e8 !important;
                        background-color: #e8e8e8 !important;
                        color: #000000 !important;
                        border: 1px solid #000000 !important;
                        font-weight: bold !important;
                        text-align: center !important;
                    }
                    td, .table-custom td, .dataTable td, table td {
                        background: #ffffff !important;
                        background-color: #ffffff !important;
                        color: #000000 !important;
                        border: 1px solid #000000 !important;
                        padding: 6px 8px !important;
                    }
                    tr, thead, tbody, tfoot {
                        background-color: #ffffff !important;
                        background: #ffffff !important;
                    }
                    .total-row td, .subtotal-row td, tfoot td {
                        background-color: #f0f0f0 !important;
                        font-weight: bold !important;
                        color: #000000 !important;
                    }
                `;
                win.document.head.appendChild(styleElement);

                var headerHtml = `
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #004B87; padding-bottom:15px; margin-bottom:20px;">
                    <div style="display:flex; align-items:center;">
                        ${logoBase64 ? `<img src="${logoBase64}" style="width:100px; margin-right:20px;">` : ''}
                        <div>
                            <h2 style="color:#004B87; margin:0;">${nombreEmpresa}</h2>
                            <h4 style="color:#444; margin:5px 0 0 0;">SUBMAYOR DE VACACIONES - CONSOLIDADO</h4>
                            <p style="color:#004B87; margin:3px 0 0 0; font-size:10px;">Período consultado: <strong>${periodoFiltroTexto}</strong></p>
                        </div>
                    </div>
                    <div style="text-align:right; font-size:11px; color:#555;">
                        Generado por: ${usuarioNombre}<br>
                        Fecha: ${new Date().toLocaleDateString('es-ES')} ${new Date().toLocaleTimeString('es-ES')}
                    </div>
                </div>`;
                $(win.document.body).prepend(headerHtml);
                
                var signaturesHtml = `
                <div style="margin-top: 50px;">
                    <table style="width: 100%; border: none; margin-top: 40px;">
                        <tr>
                            <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                                <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                                <strong>Elaborado por:</strong><br>${usuarioNombre}<br>
                                <span style="font-size: 7pt;">(Usuario Responsable)</span>
                            </td>
                            <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                                <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                                <strong>Revisado por:</strong><br>${globalEspecialistaGestion}<br>
                                <span style="font-size: 7pt;">(Especialista de Gestión)</span>
                            </td>
                            <td style="width: 33%; text-align: center; border: none; padding-top: 40px; vertical-align: bottom;">
                                <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                                <strong>Aprobado por:</strong><br>${globalJefeProyecto}<br>
                                <span style="font-size: 7pt;">(Jefe de Proyecto)</span>
                            </td>
                        </tr>
                    </table>
                </div>`;
                $(win.document.body).append(signaturesHtml);
            }
        },
        {
            extend: 'pdfHtml5',
            text: '<i class="fas fa-file-pdf text-danger me-2"></i> PDF',
            className: 'btn-win-sm',
            orientation: 'landscape',
            pageSize: 'LETTER',
            exportOptions: {
                columns: colsExportar
            },
            customize: function(doc) {
                doc.pageMargins = [30, 80, 30, 40];
                doc.styles.tableHeader = { 
                    fontSize: 8, 
                    bold: true, 
                    color: '#000000',
                    fillColor: '#e8e8e8',
                    alignment: 'center' 
                };
                doc.styles.tableBody = {
                    fontSize: 7,
                    color: '#000000'
                };
                doc.defaultStyle = {
                    fontSize: 7,
                    color: '#000000'
                };
                
                var logoImg = '<?php echo base64_encode(file_get_contents('../../images/logotn.png')); ?>';
                doc.content.splice(0, 0, {
                    columns: [
                        { 
                            image: 'data:image/png;base64,' + logoImg,
                            width: 80,
                            alignment: 'left'
                        },
                        { 
                            text: nombreEmpresa + '\nREPORTE DE VACACIONES ACUMULADAS',
                            alignment: 'center',
                            fontSize: 14,
                            bold: true
                        }
                    ]
                });
                
                var firmaElaborado = globalUsuarioNombre;
                var firmaRevisado = globalEspecialistaGestion;
                var firmaAprobado = globalJefeProyecto;
                
                doc.content.push({
                    text: '\n\n',
                    margin: [0, 30, 0, 0]
                });
                
                doc.content.push({
                    columns: [
                        { text: '', width: '*'},
                        { 
                            alignment: 'center',
                            stack: [
                                { text: '_________________________', alignment: 'center' },
                                { text: firmaElaborado, alignment: 'center', bold: true },
                                { text: 'Elaborado por', alignment: 'center', fontSize: 7 }
                            ],
                            width: '30%'
                        },
                        { 
                            alignment: 'center',
                            stack: [
                                { text: '_________________________', alignment: 'center' },
                                { text: firmaRevisado, alignment: 'center', bold: true },
                                { text: 'Revisado por', alignment: 'center', fontSize: 7 }
                            ],
                            width: '30%'
                        },
                        { 
                            alignment: 'center',
                            stack: [
                                { text: '_________________________', alignment: 'center' },
                                { text: firmaAprobado, alignment: 'center', bold: true },
                                { text: 'Aprobado por', alignment: 'center', fontSize: 7 }
                            ],
                            width: '30%'
                        },
                        { text: '', width: '*'}
                    ]
                });
            }
        },
        {
            extend: 'excelHtml5',
            text: '<i class="fas fa-file-excel text-success me-2"></i> Excel',
            className: 'btn-win-sm',
            title: nombreEmpresa + ' - Submayor de Vacaciones - ' + periodoFiltroTexto,
            exportOptions: {
                columns: colsExportar
            }
        },
        {
            extend: 'csvHtml5',
            text: '<i class="fas fa-file-csv text-info me-2"></i> CSV',
            className: 'btn-win-sm',
            exportOptions: {
                columns: colsExportar
            }
        }
    ];
    }

    var tableSaldos = $('#tablaSaldos').DataTable({
        language: configSpanish,
        pageLength: 10,
        responsive: false,
        scrollX: true,
        scrollY: '400px',
        scrollCollapse: true,
        order: [[1, 'asc']],
        columnDefs: [
            { targets: [12], orderable: false } // Solo desactivar ordenamiento, la columna se mantiene visible
        ],
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: crearBotonesDataTable([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11])
    });
    
    var tableMovimientos = $('#tablaMovimientos').DataTable({
        language: configSpanish,
        pageLength: 15,
        responsive: false,
        scrollX: true,
        scrollY: '400px',
        scrollCollapse: true,
        order: [[0, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: crearBotonesDataTable([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])
    });
    
	if ($.fn.DataTable.isDataTable('#tablaDetalleIndividual')) {
		$('#tablaDetalleIndividual').DataTable().destroy();
	}
    
	if ($('#tablaDetalleIndividual').length > 0) {
        $('#tablaDetalleIndividual').DataTable({
            language: configSpanish,
            pageLength: 10,
            responsive: false,
            scrollX: true,
            scrollY: '400px',
            scrollCollapse: true,
            order: [[0, 'asc']],
            dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
            buttons: crearBotonesDataTable([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])
        });
    }

    $('#menuColumnas').on('click', function(e) { e.preventDefault(); tableSaldos.button('.buttons-colvis').trigger(); });
    $('#menuExportPrint').on('click', function(e) { e.preventDefault(); tableSaldos.button(1).trigger(); });
    $('#menuExportPDF').on('click', function(e) { e.preventDefault(); tableSaldos.button(2).trigger(); });
    $('#menuExportExcel').on('click', function(e) { e.preventDefault(); tableSaldos.button(3).trigger(); });
    $('#menuExportCSV').on('click', function(e) { e.preventDefault(); tableSaldos.button(4).trigger(); });
    
    $('#menuExportWord').on('click', function(e) {
        e.preventDefault();
        var $clonedTable = $('#tablaSaldos').clone();
        // Eliminar la última columna (Acción) de la copia para Word
        $clonedTable.find('th:last, td:last').remove();
        var htmlContent = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8"><style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000000; padding: 6px; }
            th { background-color: #e8e8e8; color: #000000; }
            td { background-color: #ffffff; color: #000000; }
        </style></head><body>
            <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
                ${logoBase64 ? `<img src="${logoBase64}" style="max-height:70px;">` : ''}
                <div>
                    <h2>${nombreEmpresa}</h2>
                    <h3>Consolidado Subsidiario del Submayor de Vacaciones</h3>
                </div>
            </div>
            <p>Período: <strong>${periodoFiltroTexto}</strong></p>
            <hr>
            ${$clonedTable[0].outerHTML}
            <div style="margin-top: 50px;">
                <table style="width: 100%; border: none; margin-top: 40px;">
                    <tr>
                        <td style="width: 33%; text-align: center; border: none; padding-top: 40px;">
                            <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                            <strong>Elaborado por:</strong><br>${usuarioNombre}<br>
                            <span style="font-size: 7pt;">(Usuario Responsable)</span>
                        </td>
                        <td style="width: 33%; text-align: center; border: none; padding-top: 40px;">
                            <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                            <strong>Revisado por:</strong><br>${globalEspecialistaGestion}<br>
                            <span style="font-size: 7pt;">(Especialista de Gestión)</span>
                        </td>
                        <td style="width: 33%; text-align: center; border: none; padding-top: 40px;">
                            <div style="border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto;"></div>
                            <strong>Aprobado por:</strong><br>${globalJefeProyecto}<br>
                            <span style="font-size: 7pt;">(Jefe de Proyecto)</span>
                        </td>
                    </tr>
                </table>
            </div>
        </body></html>`;
        var blob = new Blob(['\ufeff' + htmlContent], { type: 'application/msword' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Submayor_Vacaciones_Consolidado.doc';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    $('#menuExportTXT').on('click', function(e) {
        e.preventDefault();
        var txt = "SUBMAYOR DE VACACIONES - CONSOLIDADO\n";
        txt += "Empresa: " + nombreEmpresa + "\n";
        txt += "Período: " + periodoFiltroTexto + "\n";
        txt += "Generado por: " + usuarioNombre + "\n\n";
        txt += "Firmas:\n";
        txt += "Elaborado por: " + usuarioNombre + "\n";
        txt += "Revisado por: " + globalEspecialistaGestion + "\n";
        txt += "Aprobado por: " + globalJefeProyecto + "\n\n";
        
        var headers = [];
        $('#tablaSaldos thead th').each(function(idx, el) {
            if (idx < 12) headers.push($(el).text().trim()); // Solo 12 columnas (excluye la de Acción)
        });
        txt += headers.join("\t") + "\n";
        
        tableSaldos.rows({ search: 'applied' }).every(function() {
            var rowData = this.data();
            var rowArray = [];
            for (var i = 0; i < 12; i++) { // Solo 12 columnas (excluye la de Acción)
                rowArray.push(rowData[i].replace(/<[^>]*>/g, '').trim());
            }
            txt += rowArray.join("\t") + "\n";
        });
        
        var blob = new Blob(['\ufeff' + txt], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Submayor_Vacaciones_Consolidado.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    $('#btnBackupManual').on('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-database text-warning me-2"></i> Salva Manual',
            text: 'Se creará un respaldo completo de la base de datos (SQL).',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: '<i class="fas fa-download me-2"></i> Generar',
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
            background: '#1a1a2e',
            color: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i> Generando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
                fetch('../ajax/backup_db.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: '<i class="fas fa-check-circle text-success me-2"></i> Completado',
                            text: 'Backup generado exitosamente.',
                            icon: 'success',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                            background: '#1a1a2e',
                            color: '#ffffff'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'No se pudo generar el backup',
                            icon: 'error',
                            background: '#1a1a2e',
                            color: '#ffffff',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error de conexión',
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#ffffff',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                    });
                });
            }
        });
    });
});

// Gestión de visualización dinámica asíncrona del Libro Auxiliar Individual (AJAX)
window.verHistorialTrabajador = function(trabajadorId, nombreCompleto) {
    Swal.fire({
        title: '<strong><i class="fas fa-spinner fa-pulse text-primary me-2"></i> Generando Auxiliar</strong>',
        html: '<p class="mb-0">Calculando saldos corridos e importes acumulados. Por favor espere...</p>',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
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
        success: function(response) {
            Swal.close();
            if (response.success) {
                $('#lblTrabajadorHistorial').text(nombreCompleto);
                
                if (response.data && response.data.length > 0) {
                    const primerReg = response.data[0];
                    $('#modalCargoTrabajador').text(primerReg.cargo || '—');
                    $('#modalAreaTrabajador').text(primerReg.nombre_area || '—');
                } else {
                    $('#modalCargoTrabajador').text('—');
                    $('#modalAreaTrabajador').text('—');
                }
                $('#modalSaldoTrabajador').text(response.saldo_actual.toFixed(2) + ' días');

                let html = '';
                let saldoDias = 0;
                let saldoImporte = 0;

                response.data.forEach(reg => {
                    let fMov = reg.fecha_movimiento;
                    if (fMov) {
                        const f = fMov.split(' ');
                        const p = f[0].split('-');
                        if (p.length === 3) fMov = `${p[2]}/${p[1]}/${p[0]} ${f[1] || ''}`;
                    }

                    const esEntrada = (reg.tipo_movimiento === 'acumulacion' || (reg.tipo_movimiento === 'ajuste' && parseFloat(reg.dias) >= 0));
                    
                    const diasEnt = esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                    const impEnt = esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;
                    
                    const diasSal = !esEntrada ? Math.abs(parseFloat(reg.dias)) : 0;
                    const impSal = !esEntrada ? Math.abs(parseFloat(reg.importe)) : 0;

                    if (esEntrada) {
                        saldoDias += diasEnt;
                        saldoImporte += impEnt;
                    } else {
                        saldoDias -= diasSal;
                        saldoImporte -= impSal;
                    }

                    let conceptBadge = '';
                    if (reg.tipo_movimiento === 'acumulacion') {
                        conceptBadge = '<span class="badge-acumulacion"><i class="fas fa-plus me-1"></i>Acumulado</span>';
                    } else if (reg.tipo_movimiento === 'disfrute') {
                        conceptBadge = '<span class="badge-disfrute"><i class="fas fa-minus me-1"></i>Disfrute</span>';
                    } else {
                        conceptBadge = '<span class="badge-ajuste"><i class="fas fa-balance-scale me-1"></i>Ajuste</span>';
                    }

                    let impEntText = impEnt > 0 ? new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(impEnt) : '-';
                    let impSalText = impSal > 0 ? new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(impSal) : '-';
                    let saldoImpText = new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(saldoImporte);

                    html += `<tr>`;
                    html += `<td class="text-center"><small>${fMov}</small></td>`;
                    html += `<td class="text-center">${reg.periodo_desde.split('-')[1]}/${reg.periodo_desde.split('-')[0]}</td>`;
                    html += `<td class="text-center">${conceptBadge}</td>`;
                    html += `<td class="text-end text-success">${diasEnt > 0 ? diasEnt.toFixed(2) : '-'}</td>`;
                    html += `<td class="text-end text-success">${impEntText}</td>`;
                    html += `<td class="text-end text-warning">${diasSal > 0 ? diasSal.toFixed(2) : '-'}</td>`;
                    html += `<td class="text-end text-warning">${impSalText}</td>`;
                    html += `<td class="text-end text-info fw-bold" style="background: rgba(0,120,212,0.05) !important;">${saldoDias.toFixed(2)}</td>`;
                    html += `<td class="text-end text-info fw-bold" style="background: rgba(0,120,212,0.05) !important;">${saldoImpText}</td>`;
                    html += `<td><small class="text-muted-win">${reg.referencia || '—'}</small></td>`;
                    html += `</tr>`;
                });

                if (response.data.length === 0) {
                    html = `<tr><td colspan="10" class="text-center py-4 text-muted-win">No se registran movimientos en el submayor para este trabajador. Baseline</tr>`;
                }

                $('#tablaBodyIndividual').html(html);
                $('#modalHistorialIndividual').modal('show');
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: response.message, background: '#1a1a2e', color: '#ffffff' });
            }
        },
        error: function(err) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor para consultar el historial.', background: '#1a1a2e', color: '#ffffff' });
        }
    });
};

// Gestión Ajax de envío de Formulario de Ajustes
document.getElementById('ajusteVacacionesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Procesando ajuste...',
        html: 'Por favor, espere mientras actualizamos los libros contables del submayor.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    fetch(window.location.href, {
        method: 'POST',
        body: new FormData(this),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Ajuste Procesado',
                text: data.message,
                confirmButtonText: 'Aceptar',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error de Procesamiento',
                text: data.message,
                confirmButtonText: 'Aceptar',
                background: '#1a1a2e',
                color: '#ffffff'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Fallo Crítico',
            text: err.toString(),
            confirmButtonText: 'Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
});

// Cierre de Sesión seguro
function cerrarSesion() {
    fetch('../logout.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(() => { window.location.href = '../login.php?logout=1'; })
        .catch(() => { window.location.href = '../login.php?logout=1'; });
}

const logoutLogic = (e) => {
    e.preventDefault();
    Swal.fire({
        title: '<i class="fas fa-sign-out-alt" style="color: #ef4444"></i> Cerrar sesión',
        text: '¿Está seguro que desea salir del sistema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#D13438',
        cancelButtonColor: '#2D2D2D',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, salir',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1F1F1F',
        color: '#FFFFFF'
    }).then((result) => { if (result.isConfirmed) cerrarSesion(); });
};

document.getElementById('logoutBtn')?.addEventListener('click', logoutLogic);
document.getElementById('logoutSidebarBtn')?.addEventListener('click', logoutLogic);

// SISTEMA DE IMPRESIÓN FÍSICA TRADICIONAL
function imprimirReporteFisico() {
    var nombreEmpresa = globalNombreEmpresa;
    var usuarioNombre = globalUsuarioNombre;
    var periodoFiltroTexto = globalPeriodoFiltroTexto;
    var jefeProyecto = globalJefeProyecto;
    var especialistaGestion = globalEspecialistaGestion;
    var logoBase64 = globalLogoBase64;
    
    var printWindow = window.open('', '_blank', 'height=700,width=950');
    printWindow.document.write('<html><head><title>Submayor de Vacaciones - Reporte Físico</title>');
    printWindow.document.write('<meta charset="UTF-8">');
    printWindow.document.write('<style>');
    printWindow.document.write('@page { size: Letter; margin: 1.5cm; }');
    printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
    printWindow.document.write('body { font-family: "Arial", "Helvetica", sans-serif; color: #000000; background: #ffffff; font-size: 10pt; line-height: 1.4; }');
    printWindow.document.write('.report-container { max-width: 100%; margin: 0 auto; }');
    printWindow.document.write('.header-logo { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 15px; }');
    printWindow.document.write('.header-logo img { max-height: 60px; max-width: 120px; object-fit: contain; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 25px; }');
    printWindow.document.write('th, td { border: 1px solid #000000; padding: 6px 8px; text-align: left; vertical-align: top; color: #000000 !important; }');
    printWindow.document.write('th { background-color: #e8e8e8 !important; color: #000000 !important; text-align: center; font-weight: bold; font-size: 9pt; }');
    printWindow.document.write('.header-table { width: 100%; border: none; margin-bottom: 20px; }');
    printWindow.document.write('.header-table td { border: none; padding: 4px 0; }');
    printWindow.document.write('.title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px; }');
    printWindow.document.write('.subtitle { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 20px; }');
    printWindow.document.write('.company-info { text-align: center; font-size: 9pt; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }');
    printWindow.document.write('.text-right { text-align: right; }');
    printWindow.document.write('.text-center { text-align: center; }');
    printWindow.document.write('.text-left { text-align: left; }');
    printWindow.document.write('.signatures { width: 100%; margin-top: 50px; border: none; }');
    printWindow.document.write('.signatures td { border: none; text-align: center; width: 33%; padding-top: 40px; font-size: 9pt; vertical-align: bottom; }');
    printWindow.document.write('.signatures-line { border-top: 1px solid #000000; width: 85%; margin: 0 auto 5px auto; }');
    printWindow.document.write('.footer-note { text-align: center; font-size: 8pt; margin-top: 30px; color: #555; border-top: 1px solid #ccc; padding-top: 10px; }');
    printWindow.document.write('.total-row { font-weight: bold; background-color: #f0f0f0; }');
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<div class="report-container">');
    
    var trabajadorId = '<?php echo $trabajador_id; ?>';
    var fechaActual = new Date().toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'numeric'});
    
    // Encabezado con logo
    printWindow.document.write('<div class="header-logo">');
    if (logoBase64) {
        printWindow.document.write('<img src="' + logoBase64 + '" alt="Logo">');
    }
    printWindow.document.write('</div>');
    
    if (trabajadorId !== '') {
        var nombreTrab = '<?php echo htmlspecialchars(strtoupper($resumen_trabajador['nombre_completo'] ?? '')); ?>';
        var codigoTrab = '<?php echo htmlspecialchars($resumen_trabajador['codigo'] ?? ''); ?>';
        var areaTrab = '<?php echo htmlspecialchars($resumen_trabajador['nombre_area'] ?? ''); ?>';
        var salarioTrab = '<?php echo formatearMoneda($resumen_trabajador['salario_mensual'] ?? 0); ?>';
        
        printWindow.document.write('<div class="title">' + nombreEmpresa.toUpperCase() + '</div>');
        printWindow.document.write('<div class="subtitle">SUBMAYOR DE VACACIONES<br>(Método Laboral Cubano - 9.09%)</div>');
        
        printWindow.document.write('<table class="header-table">');
        printWindow.document.write('<tr><td width="50%"><strong>Código / Expediente:</strong> ' + codigoTrab + '</td>');
        printWindow.document.write('<td class="text-right"><strong>Fecha Reporte:</strong> ' + fechaActual + '</td></tr>');
        printWindow.document.write('<tr><td width="50%"><strong>Trabajador:</strong> ' + nombreTrab + '</td>');
        printWindow.document.write('<td class="text-right"><strong>Período:</strong> ' + periodoFiltroTexto + '</td></tr>');
        printWindow.document.write('<tr><td width="50%"><strong>Área / Departamento:</strong> ' + areaTrab + '</td>');
        printWindow.document.write('<td class="text-right"><strong>Salario Escala:</strong> ' + salarioTrab + '</td>');
        printWindow.document.write('</table>');
        
        printWindow.document.write('<table>');
        printWindow.document.write('<thead>');
        printWindow.document.write('<tr><th rowspan="2">Fecha Mov.</th><th rowspan="2">Período</th><th rowspan="2">Operación</th>');
        printWindow.document.write('<th colspan="2">ENTRADAS (+)</th><th colspan="2">SALIDAS (-)</th><th colspan="2">SALDOS CORRIDOS</th>');
        printWindow.document.write('</tr>');
        printWindow.document.write('<tr><th>Días</th><th>Importe (CUP)</th><th>Días</th><th>Importe (CUP)</th><th>Días</th><th>Importe (CUP)</th></tr>');
        printWindow.document.write('</thead><tbody>');
        
        var tablaDetalle = document.getElementById('tablaDetalleIndividual');
        if (tablaDetalle) {
            var filas = tablaDetalle.querySelectorAll('tbody tr');
            filas.forEach(function(fila) {
                var celdas = fila.querySelectorAll('td');
                if (celdas.length >= 9) {
                    var operacion = celdas[2] ? celdas[2].innerText.trim() : '';
                    operacion = operacion.replace(/<[^>]*>/g, '').trim();
                    
                    printWindow.document.write('<tr>');
                    printWindow.document.write('<td class="text-center">' + (celdas[0] ? celdas[0].innerText.trim() : '') + '</td>');
                    printWindow.document.write('<td class="text-center">' + (celdas[1] ? celdas[1].innerText.trim() : '') + '</td>');
                    printWindow.document.write('<td>' + operacion + '</td>');
                    printWindow.document.write('<td class="text-right">' + (celdas[3] ? celdas[3].innerText.trim() : '-') + '</td>');
                    printWindow.document.write('<td class="text-right">' + (celdas[4] ? celdas[4].innerText.trim() : '-') + '</td>');
                    printWindow.document.write('<td class="text-right">' + (celdas[5] ? celdas[5].innerText.trim() : '-') + '</td>');
                    printWindow.document.write('<td class="text-right">' + (celdas[6] ? celdas[6].innerText.trim() : '-') + '</td>');
                    printWindow.document.write('<td class="text-right"><strong>' + (celdas[7] ? celdas[7].innerText.trim() : '-') + '</strong></td>');
                    printWindow.document.write('<td class="text-right"><strong>' + (celdas[8] ? celdas[8].innerText.trim() : '-') + '</strong></td>');
                    printWindow.document.write('</tr>');
                }
            });
        }
        printWindow.document.write('</tbody></table>');
        
    } else {
        printWindow.document.write('<div class="title">' + nombreEmpresa.toUpperCase() + '</div>');
        printWindow.document.write('<div class="company-info">NIT: 12345678-9 | Régimen General</div>');
        printWindow.document.write('<div class="subtitle">CONSOLIDADO SUBSIDIARIO DEL SUBMAYOR DE VACACIONES</div>');
        
        printWindow.document.write('<table class="header-table">');
        printWindow.document.write('<tr><td width="50%"><strong>Período Consultado:</strong> ' + periodoFiltroTexto + '</td>');
        printWindow.document.write('<td class="text-right"><strong>Fecha Emisión:</strong> ' + fechaActual + '</td>');
        printWindow.document.write('</tr>');
        printWindow.document.write('<tr><td width="50%"><strong>Días Laborables por Mes:</strong> ' + globalDiasLaborables + '</td>');
        printWindow.document.write('<td class="text-right"><strong>Generado por:</strong> ' + usuarioNombre + '</td>');
        printWindow.document.write('</table>');
        
        printWindow.document.write('<table>');
        printWindow.document.write('<thead>');
        printWindow.document.write('<tr>');
        printWindow.document.write('<th>Exp.</th><th>Trabajador</th><th>Cargo</th><th>Área</th>');
        printWindow.document.write('<th>Días Acum.</th><th>Imp. Acum.</th>');
        printWindow.document.write('<th>Días Disf.</th><th>Imp. Disf.</th>');
        printWindow.document.write('<th>Días Ajuste</th><th>Imp. Ajuste</th>');
        printWindow.document.write('<th>Saldo Inicial</th><th>Saldo al Cierre</th>');
        printWindow.document.write('</tr>');
        printWindow.document.write('</thead><tbody>');
        
        var tablaSaldos = document.getElementById('tablaSaldos');
        if (tablaSaldos) {
            var filas = tablaSaldos.querySelectorAll('tbody tr');
            filas.forEach(function(fila) {
                var celdas = fila.querySelectorAll('td');
                // Usamos 12 columnas (excluyendo la de Acción que está en índice 12)
                if (celdas.length >= 13) {
                    printWindow.document.write('<tr>');
                    for (var i = 0; i < 12; i++) {
                        var celda = celdas[i];
                        var contenido = celda ? celda.innerText.trim() : '';
                        // Aplicar clase text-right para columnas numéricas
                        if (i >= 4) {
                            printWindow.document.write('<td class="text-right">' + contenido + '</td>');
                        } else {
                            printWindow.document.write('<td>' + contenido + '</td>');
                        }
                    }
                    printWindow.document.write('</tr>');
                }
            });
        }
        
        var footCols = $('#tablaSaldos tfoot tr td');
        if (footCols.length > 0) {
            printWindow.document.write('<tr class="total-row">');
            printWindow.document.write('<td colspan="3" class="text-right"><strong>TOTALES GENERALES:</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[1] ? $(footCols[1]).text().trim() : '0') + '</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[2] ? $(footCols[2]).text().trim() : '$0.00') + '</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[3] ? $(footCols[3]).text().trim() : '0') + '</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[4] ? $(footCols[4]).text().trim() : '$0.00') + '</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>-</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>-</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[7] ? $(footCols[7]).text().trim() : '0') + '</strong></td>');
            printWindow.document.write('<td class="text-right"><strong>' + (footCols[8] ? $(footCols[8]).text().trim() : '0') + '</strong></td>');
            printWindow.document.write('</tr>');
        }
        
        printWindow.document.write('</tbody></table>');
    }
    
    // Firmas de responsabilidad
    printWindow.document.write('<table class="signatures">');
    printWindow.document.write('<tr>');
    printWindow.document.write('<td><div class="signatures-line"></div><strong>Elaborado por:</strong><br>' + usuarioNombre + '<br><span style="font-size:7pt;">(Usuario Responsable)</span></td>');
    printWindow.document.write('<td><div class="signatures-line"></div><strong>Revisado por:</strong><br>' + especialistaGestion + '<br><span style="font-size:7pt;">(Especialista de Gestión)</span></td>');
    printWindow.document.write('<td><div class="signatures-line"></div><strong>Aprobado por:</strong><br>' + jefeProyecto + '<br><span style="font-size:7pt;">(Jefe de Proyecto)</span></td>');
    printWindow.document.write('</tr>');
    printWindow.document.write('</table>');
    
    printWindow.document.write('<div class="footer-note">');
    printWindow.document.write('Documento de uso interno - Base legal: Ley 116 Código de Trabajo Cubano | Método de acumulación 9.09%');
    printWindow.document.write('<br>Este reporte es de carácter oficial y refleja los movimientos registrados en el Submayor de Vacaciones.');
    printWindow.document.write('</div>');
    
    printWindow.document.write('</div></body></html>');
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Funciones para el Reporte VERSAT
function aplicarFiltroRapidoVersat(min, max) {
    if (min > 0) {
        document.getElementById('versatDiasMin').value = min;
    } else {
        document.getElementById('versatDiasMin').value = '';
    }
    
    if (max < 999) {
        document.getElementById('versatDiasMax').value = max;
    } else {
        document.getElementById('versatDiasMax').value = '';
    }
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
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="border-bottom: 2px solid #10b981;">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar me-2" style="color: #10b981;"></i>
                    <strong>Reporte de Vacaciones Acumuladas</strong>
                </h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <form id="formReporteVersat" method="GET" action="" target="_blank">
                    <input type="hidden" name="versat_report" value="1">
                    
                    <!-- Opciones de Agrupación -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-layer-group me-2" style="color: #60a5fa;"></i>Agrupar por:</label>
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

                    <!-- Filtro de Intervalo de Días -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-sliders-h me-2" style="color: #f59e0b;"></i>Intervalo de Días Acumulados:</label>
                        </div>
                        <div class="col-md-5">
                            <select name="versat_dias_min" id="versatDiasMin" class="form-select">
                                <option value="">Mínimo (sin límite)</option>
                                <option value="0">0 días</option>
                                <option value="5">Mayor a 5 días</option>
                                <option value="10">Mayor a 10 días</option>
                                <option value="15">Mayor a 15 días</option>
                                <option value="20">Mayor a 20 días</option>
                                <option value="30">Mayor a 30 días</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="fw-bold">HASTA</span>
                        </div>
                        <div class="col-md-5">
                            <select name="versat_dias_max" id="versatDiasMax" class="form-select">
                                <option value="">Máximo (sin límite)</option>
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
                            <label class="form-label fw-bold"><i class="fas fa-bolt me-2" style="color: #8b5cf6;"></i>Filtros Rápidos:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(0, 20)"><i class="fas fa-hourglass-half"></i> Menos de 20 días</button>
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(20, 999)"><i class="fas fa-hourglass-end"></i> 20 días o más</button>
                                <button type="button" class="btn-win-sm" onclick="aplicarFiltroRapidoVersat(30, 999)"><i class="fas fa-exclamation-triangle"></i> 30 días o más (Prioritario)</button>
                                <button type="button" class="btn-win-sm" onclick="limpiarFiltrosDiasVersat()"><i class="fas fa-eraser"></i> Limpiar filtros</button>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Tipo de Contrato -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold"><i class="fas fa-file-signature me-2" style="color: #06b6d4;"></i>Tipo de Contrato:</label>
                            <select name="versat_tipo_contrato" id="versatTipoContrato" class="form-select">
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

                    <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">

                    <!-- Botones de acción -->
                    <div class="row g-3">
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn-win btn-win-primary btn-win-sm ms-2">
                                <i class="fas fa-eye me-2"></i> Generar Reporte
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>