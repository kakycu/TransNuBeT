<?php
// modules/nominas.php - VERSIÓN COMPLETA CON FILTRO DE CUENTA BANCARIA, REGLA DE NO ACUMULACIÓN DE VACACIONES Y SELECCIÓN DE TIPO DE DESCUENTO PARA TODAS LAS NÓMINAS
// ============================================
// DETECTAR PETICIÓN AJAX (DEBE IR PRIMERO ANTES DE CUALQUIER OUTPUT)
// ============================================

/*Te explico claramente qué porcentajes se te descuentan a ti directamente como trabajador de un Proyecto de Desarrollo Local (PDL) en Cuba en 2026.
📊 Lo que te descuentan DIRECTAMENTE de tu salario
1. Contribución a la Seguridad Social (CESS) - 5% a 10%
Este es el descuento mensual obligatorio de tu salario:
Tramo del salario mensual	Porcentaje de descuento
Hasta 15,000 CUP				5%
Exceso sobre 15,000 CUP			10%
Ejemplo práctico: Si ganas 20,000 CUP al mes:
    Por los primeros 15,000 CUP → pagas 5% = 750 CUP
    Por los 5,000 CUP excedentes → pagas 10% = 500 CUP
    Total descuento mensual por CESS = 1,250 CUP
-----------------------------------------------------------------------------------------
Tipo de Hora				Fórmula	Ejemplo 						(base $100/hora)
Hora Normal					salario_hora × horas_normales			100×1=100×1=100
Hora Nocturna				salario_hora × 1.25 × horas_nocturnas	100×1.25×1=∗∗100×1.25×1=∗∗125**(7pm-7am)
Día Feriado					salario_diario × 2 × días_feriados		800×2×1=800×2×1=1,600
Importe Nocturno (cálculo automático)
*/

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// =========================================================================
// CONEXIÓN GLOBAL A LA BASE DE DATOS Y OBTENCIÓN DE CONFIGURACIÓN GENÉRICA
// =========================================================================
require_once '../config/database.php';


// Obtener el multiplicador de recargo nocturno genérico (por defecto 1.25 si no existe)
try {
    $stmt_recargo = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'recargo_nocturno'");
    $stmt_recargo->execute();
    $recargo_nocturno = floatval($stmt_recargo->fetchColumn()) ?: 1.25;
} catch (PDOException $e) {
    $recargo_nocturno = 1.25; // Fallback de seguridad si falla la tabla
}

try {
    // Obtener factor 0.0909 de la tabla de vacaciones
    $stmt_vaca = $pdo->query("SELECT factor_calculo, horas_jornada_diaria FROM configuracion_vacaciones, configuracion_general WHERE configuracion_vacaciones.activo = 1 LIMIT 1");
    $conf_v = $stmt_vaca->fetch();
    $factor_909 = floatval($conf_v['factor_calculo']) ?: 0.0909;
    
    // Obtener jornada de 8h
    $stmt_j = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'horas_jornada_diaria'");
    $stmt_j->execute();
    $horas_jornada = intval($stmt_j->fetchColumn()) ?: 8;
} catch (PDOException $e) {
    $factor_909 = 0.0909;
    $horas_jornada = 8;
}




function roundExcel($number, $precision = 2) {
    $multiplier = pow(10, $precision);
    return floor($number * $multiplier + 0.5) / $multiplier;
}

// Función global PHP para CESS Progresivo
function calcularCessProgresivoPHP($salario) {
    $limite = 15000;
    if ($salario <= $limite) {
        return roundExcel($salario * 0.05, 2);
    } else {
        return roundExcel((15000 * 0.05) + (($salario - 15000) * 0.10), 2);
    }
}

// ============================================
// AJAX: OBTENER MESES CON NÓMINAS POR AÑO (Para consulta rápida)
// ============================================
if (isset($_GET['action']) && $_GET['action'] == 'get_meses_nominas' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $anio = intval($_GET['anio']);
    $estado = $_GET['estado'] ?? '';
    $tipo = $_GET['tipo'] ?? '';
    $meses = [];
    
    if ($anio) {
        $sql = "SELECT DISTINCT MONTH(periodo_desde) as mes 
                FROM nominas 
                WHERE YEAR(periodo_desde) = ?";
        $params = [$anio];
        
        if ($estado && $estado != '') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }
        
        if ($tipo && $tipo != '') {
            $sql .= " AND tipo_nomina = ?";
            $params[] = $tipo;
        }
        
        $sql .= " ORDER BY mes";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $meses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode(['success' => true, 'meses' => $meses]);
    exit;
}
// Endpoint AJAX para obtener los números de nómina dinámicos
if (isset($_GET['action']) && $_GET['action'] === 'get_numeros_nominas' && isset($_GET['ajax'])) {
    $anio = intval($_GET['anio']);
    $mes = intval($_GET['mes']);
    $tipo = $_GET['tipo'] ?? '';
    $estado = $_GET['estado'] ?? '';
    
    try {
        $query = "SELECT DISTINCT COALESCE(numero_nomina, 'Borrador') as numero_nomina 
                  FROM nominas 
                  WHERE YEAR(periodo_desde) = ? AND MONTH(periodo_desde) = ?";
        $params = [$anio, $mes];
        
        if (!empty($tipo)) {
            $query .= " AND tipo_nomina = ?";
            $params[] = $tipo;
        }
        if (!empty($estado)) {
            $query .= " AND estado = ?";
            $params[] = $estado;
        }
        
        $query .= " ORDER BY numero_nomina ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $numeros = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'numeros' => $numeros]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ==========================================================
// AJAX: OBTENER BASE COMPLETA DE MONTOS (HISTORIAL TOTAL)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'get_full_historial_bonos' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        // Consultamos todos los registros de la tabla montos_distrib
        // Ordenamos por año descendente y por orden lógico de meses
        $sql = "SELECT anio, mes, importe_dis 
                FROM montos_distrib 
                ORDER BY anio DESC, 
                FIELD(mes, 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre') DESC";
        
        $stmt = $pdo->query($sql);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'historial' => $historial]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

    $num_nomina_actual = 'Borrador';
// ============================================
// FUNCIONES DE VALIDACIÓN PARA NÓMINAS
// ============================================
function validarConflictoAutoExtra($pdo, $trabajador_id, $periodo_desde, $periodo_hasta, $tipo_nuevo) {
	return true;
    /*if ($tipo_nuevo == 'automatica') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                               WHERE trabajador_id = ? 
                               AND periodo_desde = ? 
                               AND periodo_hasta = ? 
                               AND tipo_nomina = 'extraordinaria'
                               AND estado != 'contabilizado'");
        $stmt->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }
    }
    
    if ($tipo_nuevo == 'extraordinaria') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                               WHERE trabajador_id = ? 
                               AND periodo_desde = ? 
                               AND periodo_hasta = ? 
                               AND tipo_nomina = 'automatica'
                               AND estado != 'contabilizado'");
        $stmt->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }
    }
    
    return true;
}

function validarNominaUnicaPorTipo($pdo, $trabajador_id, $periodo_desde, $periodo_hasta, $tipo_nuevo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                           WHERE trabajador_id = ? 
                           AND periodo_desde = ? 
                           AND periodo_hasta = ? 
                           AND tipo_nomina = ?
                           AND estado != 'contabilizado'");
    $stmt->execute([$trabajador_id, $periodo_desde, $periodo_hasta, $tipo_nuevo]);
    return $stmt->fetchColumn() == 0;
}
*/
}

// Datos del usuario (Para el Topbar)
$user_nombre_completo = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? '';
$user_ci = $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// ============================================
// PROCESAR ACTUALIZACIÓN NÓMINA (AJAX) - ANTES DE CUALQUIER HTML
// ============================================
// ============================================
// PROCESAR ACTUALIZACIÓN NÓMINA (AJAX) - BLOQUE COMPLETO CORREGIDO
// ============================================
if (isset($_POST['actualizar_nomina'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $id = intval($_POST['id']);
    $tipo = $_POST['tipo_nomina'] ?? '';
    
    // 1. OBTENER CONFIGURACIÓN DESDE LA BASE DE DATOS
    // Obtenemos días mensuales (24) y jornada diaria (8)
    $stmt_cg = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('dias_mensuales', 'horas_jornada_diaria')");
    $config_gen = [];
    while ($row_cg = $stmt_cg->fetch()) {
        $config_gen[$row_cg['parametro']] = $row_cg['valor'];
    }
    $dias_mensuales_db = intval($config_gen['dias_mensuales']) ?: 24;
    $jornada_diaria_db = intval($config_gen['horas_jornada_diaria']) ?: 8;

    // Obtenemos el factor de cálculo de vacaciones (0.0909)
    $stmt_cv = $pdo->query("SELECT factor_calculo FROM configuracion_vacaciones WHERE activo = 1 LIMIT 1");
    $factor_909_db = floatval($stmt_cv->fetchColumn()) ?: 0.0909;
    
    // Verificar estado de la nómina
    $stmt = $pdo->prepare("SELECT estado, tipo_descuento FROM nominas WHERE id = ?");
    $stmt->execute([$id]);
    $nomina_data = $stmt->fetch();
    
    if (!$nomina_data || $nomina_data['estado'] != 'borrador') {
        echo json_encode(['success' => false, 'error' => 'No se puede editar una nómina contabilizada']);
        exit;
    }
    
    $tipo_descuento = $nomina_data['tipo_descuento'] ?? 'total_rangos';
    $rangos_impuesto = $pdo->query("SELECT * FROM configuracion_rangos_impuesto ORDER BY desde")->fetchAll();
    
    $stmt_tasa = $pdo->prepare("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1");
    $stmt_tasa->execute();
    $tasa_cess_general = floatval($stmt_tasa->fetchColumn()) ?: 5;

    // Función interna para impuestos
    if (!function_exists('calcularImpuestoAjax')) {
        function calcularImpuestoAjax($salario, $rangos) {
            if (empty($rangos) || $salario <= 0) return 0;
            $total = 0;
            foreach ($rangos as $rango) {
                $desde = floatval($rango['desde']);
                $hasta = $rango['hasta'] ? floatval($rango['hasta']) : PHP_FLOAT_MAX;
                $tasa = floatval($rango['tasa']);
                if ($tasa == 0) continue;
                if ($salario > $desde) {
                    $base = min($salario - $desde, $hasta - $desde);
                    if ($base > 0 && $tasa > 0) {
                        $total += roundExcel($base * $tasa, 2);
                    }
                }
            }
            return $total;
        }
    }

    if ($tipo == 'automatica' || $tipo == 'extraordinaria') {
        $horas = floatval($_POST['horas_laboradas'] ?? 0);
        $descuentos_manuales = floatval($_POST['descuentos'] ?? 0);
        
        if ($tipo == 'automatica') {
            $dias_feriados = floatval($_POST['dias_feriados'] ?? 0);
            $otros_pagos = floatval($_POST['otros_salarios'] ?? 0);
            $horas_nocturnas = 0;
        } else {
            $dias_feriados = 0;
            $otros_pagos = 0;
            $horas_nocturnas = floatval($_POST['horas_nocturnas'] ?? 0);
        }
        
        // Obtener salarios base y política de vacaciones del trabajador
        $stmt_s = $pdo->prepare("SELECT e.salario_hora_ordinaria, e.salario_mensual, t.no_acumular_vacaciones
                               FROM nominas n 
                               JOIN trabajadores t ON n.trabajador_id = t.id 
                               JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
                               WHERE n.id = ?");
        $stmt_s->execute([$id]);
        $worker_salario = $stmt_s->fetch();
        
        $salario_hora = floatval($worker_salario['salario_hora_ordinaria']);
        $salario_mensual = floatval($worker_salario['salario_mensual']);
        $no_acumular_vacaciones = intval($worker_salario['no_acumular_vacaciones'] ?? 0);
        
        // 1. CÁLCULO SALARIO LABORAL (Horas trabajadas en el mes)
        $salario_laboral = roundExcel($salario_hora * $horas, 2);
        
        // 2. CÁLCULO DE OTROS CONCEPTOS
        $valor_hora_nocturna = $salario_hora * $recargo_nocturno;
        $importe_nocturnas = ($tipo == 'extraordinaria') ? roundExcel($valor_hora_nocturna * $horas_nocturnas, 2) : 0;
        
        $salario_diario = $salario_mensual / $dias_mensuales_db;
        $importe_feriados = roundExcel($salario_diario * $dias_feriados * 2, 2);
        
        // =========================================================
        // 3. CÁLCULO PROPORCIONAL DE VACACIONES (MÉTODO 9.09% LEY 116)
        // =========================================================
        // Días devengados = (Horas trabajadas * 0.0909) / Jornada Diaria (8)
        $dias_vac_proporcional = roundExcel(($horas * $factor_909_db) / $jornada_diaria_db, 2);
        
        // Importe devengado = Días devengados * Salario Diario actual
        $importe_vac_proporcional = roundExcel($dias_vac_proporcional * $salario_diario, 2);
        
        // Determinar si se suma al "Total Devengado" del mes (para los que no acumulan)
        $importe_vacaciones_adicional = ($tipo == 'automatica' && $no_acumular_vacaciones == 1) ? $importe_vac_proporcional : 0;
        
        // 4. TOTAL DEVENGADO
        $total_devengado = roundExcel($salario_laboral + $importe_nocturnas + $importe_feriados + $otros_pagos + $importe_vacaciones_adicional, 2);
        
        // 5. CÁLCULO DE DEDUCCIONES
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_cess_general / 100), 2);
            $impuesto = calcularImpuestoAjax($total_devengado, $rangos_impuesto);
        }
        
        $neto_antes_descuentos = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
        
        // Validar que los descuentos manuales no superen el neto
        if ($descuentos_manuales > $neto_antes_descuentos) {
            $descuentos_manuales = $neto_antes_descuentos;
        }
        if ($descuentos_manuales < 0) $descuentos_manuales = 0;
        
        $neto_final = roundExcel($neto_antes_descuentos - $descuentos_manuales, 2);
        
        // 6. ACTUALIZAR BASE DE DATOS
        // Incluimos las columnas de acumulación mensual proporcional para que el dato quede guardado
        $update = $pdo->prepare("UPDATE nominas SET 
            horas_laboradas=?, 
            horas_nocturnas=?, 
            importe_horas_nocturnas=?, 
            dias_feriados=?, 
            importe_dias_feriados=?, 
            otros_salarios=?, 
            importe_salario_laboral=?, 
            total_salario_devengado=?, 
            descuentos=?, 
            contribucion_especial=?, 
            ingresos_personales=?, 
            importe_neto=?,
            total_deducciones=?,
            vacaciones_acumuladas_mes=?,
            importe_vacaciones_acumulado_mes=?
            WHERE id=?");
            
        $result = $update->execute([
            $horas, 
            $horas_nocturnas, 
            $importe_nocturnas, 
            $dias_feriados, 
            $importe_feriados, 
            $otros_pagos, 
            $salario_laboral, 
            $total_devengado, 
            $descuentos_manuales, 
            $contribucion, 
            $impuesto, 
            $neto_final,
            roundExcel($contribucion + $impuesto + $descuentos_manuales, 2),
            $dias_vac_proporcional,
            $importe_vac_proporcional,
            $id
        ]);
        
        echo json_encode(['success' => $result, 'neto_maximo' => $neto_antes_descuentos]);
        
    } elseif ($tipo == 'bono') {
        $monto = floatval($_POST['monto_bono'] ?? 0);
        $descuentos = floatval($_POST['descuentos'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $total_devengado = $monto;
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_cess_general / 100), 2);
            $impuesto = calcularImpuestoAjax($total_devengado, $rangos_impuesto);
        }
        $neto_antes_descuentos = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
        
        if ($descuentos > $neto_antes_descuentos) {
            $descuentos = $neto_antes_descuentos;
        }
        if ($descuentos < 0) $descuentos = 0;
        
        $neto = roundExcel($neto_antes_descuentos - $descuentos, 2);
        
        $update = $pdo->prepare("UPDATE nominas SET pago_resultado=?, total_salario_devengado=?, contribucion_especial=?, ingresos_personales=?, descuentos=?, importe_neto=?, total_deducciones=?, descripcion=? WHERE id=?");
        $result = $update->execute([$monto, $total_devengado, $contribucion, $impuesto, $descuentos, $neto, roundExcel($contribucion + $impuesto + $descuentos, 2), $descripcion, $id]);
        echo json_encode(['success' => $result, 'neto_maximo' => $neto_antes_descuentos]);
        
    } elseif ($tipo == 'ajuste') {
        $monto = floatval($_POST['monto_bono'] ?? 0);
        $descuentos = floatval($_POST['descuentos'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if ($monto <= 0) {
            echo json_encode(['success' => false, 'error' => 'El monto del ajuste debe ser mayor a cero.']);
            exit;
        }
        
        $total_devengado = $monto;
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_cess_general / 100), 2);
            $impuesto = calcularImpuestoAjax($total_devengado, $rangos_impuesto);
        }
        $neto_antes_descuentos = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
        
        if ($descuentos > $neto_antes_descuentos) {
            $descuentos = $neto_antes_descuentos;
        }
        if ($descuentos < 0) $descuentos = 0;
        
        $neto = roundExcel($neto_antes_descuentos - $descuentos, 2);
        
        $update = $pdo->prepare("UPDATE nominas SET pago_resultado=?, total_salario_devengado=?, contribucion_especial=?, ingresos_personales=?, descuentos=?, importe_neto=?, total_deducciones=?, descripcion=? WHERE id=?");
        $result = $update->execute([$monto, $total_devengado, $contribucion, $impuesto, $descuentos, $neto, roundExcel($contribucion + $impuesto + $descuentos, 2), $descripcion, $id]);
        echo json_encode(['success' => $result, 'neto_maximo' => $neto_antes_descuentos]);
        
    } elseif ($tipo == 'vacaciones') {
        $dias = floatval($_POST['dias_vacaciones'] ?? 1);
        $dias = roundExcel($dias * 2) / 2;
        if ($dias < 1) $dias = 1; 
        
        $stmt_trabajador = $pdo->prepare("SELECT t.vacaciones_acumuladas, t.nombre_completo FROM nominas n JOIN trabajadores t ON n.trabajador_id = t.id WHERE n.id = ?");
        $stmt_trabajador->execute([$id]);
        $datos = $stmt_trabajador->fetch();
        $dias_disponibles = floatval($datos['vacaciones_acumuladas'] ?? 0);
        
        if ($dias > $dias_disponibles) {
            echo json_encode(['success' => false, 'error' => 'No se pueden asignar más días de los disponibles. Días disponibles: ' . $dias_disponibles]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT e.salario_mensual FROM nominas n JOIN trabajadores t ON n.trabajador_id = t.id JOIN escalas_salariales e ON t.escala_salarial_id = e.id WHERE n.id = ?");
        $stmt->execute([$id]);
        $salario_mensual = floatval($stmt->fetchColumn());
        
        $valor_por_dia = $salario_mensual / $dias_mensuales_db;
        $importe = roundExcel($dias * $valor_por_dia, 2);
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($importe);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($importe * ($tasa_cess_general / 100), 2);
            $impuesto = calcularTotalImpuesto($importe, $rangos_impuesto);
        }
        $neto = roundExcel($importe - ($contribucion + $impuesto), 2);
        
        $update = $pdo->prepare("UPDATE nominas SET dias_vacaciones_tomados=?, importe_vacaciones=?, total_salario_devengado=?, contribucion_especial=?, ingresos_personales=?, importe_neto=?, total_deducciones=? WHERE id=?");
        $result = $update->execute([$dias, $importe, $importe, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $id]);
        echo json_encode(['success' => $result]);
    }
    exit;
}

// ============================================
// CONFIGURACIÓN Y LÓGICA NO AJAX
// ============================================
$config_empresa = ['nombre_empresa' => 'PDL TransNuBeT'];
try {
    // Se agregan 'reeup' y 'reeup_empresa' a la consulta SQL
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'reeup_empresa', 'nit_empresa')");
    while ($row = $stmt->fetch()) {
        $config_empresa[$row['parametro']] = $row['valor'];
    }
} catch (PDOException $e) {}
$nit_empresa = $config_empresa['nit_empresa'] ?? 'S/R';

if (!defined('JEFE_PROYECTO')) define('JEFE_PROYECTO', $config_empresa['jefe_proyecto'] ?? 'Dainelys León Reyes');
if (!defined('ESPECIALISTA')) define('ESPECIALISTA', $config_empresa['especialista_gestion'] ?? 'Mailén Pérez García');

$periodo = $_GET['periodo'] ?? date('Y-m');
$anio = substr($periodo, 0, 4);
$mes = substr($periodo, 5, 2);
$periodo_desde = "$anio-$mes-01";
$periodo_hasta = date('Y-m-t', strtotime($periodo_desde));
$tipo_nomina_activa = $_GET['tipo'] ?? 'automatica';
$filtro_cuenta = $_GET['filtro_cuenta'] ?? '';

// Capturar el número de nómina seleccionado (si viene en la URL)
$filtro_numero_nomina = $_GET['numero_nomina'] ?? '';

function getDiasMensuales($pdo) {
    $stmt = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'");
    return intval($stmt->fetchColumn()) ?: 24;
}

$dias_laborables = getDiasMensuales($pdo);

$tipos_nomina = [
    'automatica' => ['nombre' => 'Automática Salarios', 'icono' => 'fa-calendar-alt', 'color' => 'primary'],
    'extraordinaria' => ['nombre' => 'Extraordinaria', 'icono' => 'fa-clock', 'color' => 'info'],
    'vacaciones' => ['nombre' => 'Vacaciones', 'icono' => 'fa-umbrella-beach', 'color' => 'success'],
    'bono' => ['nombre' => 'Rend, Bono y/o Otros Pagos', 'icono' => 'fa-gift', 'color' => 'warning'],
	'ajuste' => ['nombre' => 'Nóminas de Ajustes', 'icono' => 'fa-pen', 'color' => 'secondary']
];

$stmt_rangos = $pdo->query("SELECT * FROM configuracion_rangos_impuesto ORDER BY desde");
$rangos_impuesto = $stmt_rangos->fetchAll(PDO::FETCH_ASSOC);
$has_subheader = (count($rangos_impuesto) > 0);

function calcularImpuestosPorRango($salario, $rangos) {
    if ($salario <= 0 || empty($rangos)) return [];
    $resultados = [];
    foreach ($rangos as $rango) {
        $desde = floatval($rango['desde']);
        $hasta = $rango['hasta'] ? floatval($rango['hasta']) : PHP_FLOAT_MAX;
        $tasa = floatval($rango['tasa']);
        
        $base = 0;
        $impuesto = 0;
        if ($tasa > 0 && $salario > $desde) {
            $base = min($salario - $desde, $hasta - $desde);
            if ($base > 0) {
                $impuesto = roundExcel($base * $tasa, 2);
            }
        }
        $resultados[] = ['desde' => $desde, 'hasta' => $hasta, 'tasa' => $tasa, 'base' => $base, 'impuesto' => $impuesto];
    }
    return $resultados;
}

function numeroRomano($numero) {
    if ($numero == 'S/E' || $numero == '?' || $numero === null || $numero === '') return '?';
    $numero = intval($numero);
    if ($numero <= 0) return '0';
    if ($numero >= 4000) return (string)$numero;
    $romanos = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $resultado = '';
    foreach ($romanos as $valor => $letra) {
        while ($numero >= $valor) {
            $resultado .= $letra;
            $numero -= $valor;
        }
    }
    return $resultado;
}

function calcularTotalImpuesto($salario, $rangos) {
    if ($salario <= 0 || empty($rangos)) return 0;
    $total = 0;
    foreach ($rangos as $rango) {
        $desde = floatval($rango['desde']);
        $hasta = $rango['hasta'] ? floatval($rango['hasta']) : PHP_FLOAT_MAX;
        $tasa = floatval($rango['tasa']);
        if ($tasa == 0) continue;
        if ($salario <= $desde) continue;
        $base = min($salario - $desde, $hasta - $desde);
        if ($base > 0 && $tasa > 0) $total += roundExcel($base * $tasa, 2);
    }
    return $total;
}

if (!function_exists('getHorasMensuales')) {
    function getHorasMensuales($pdo) {
        $stmt = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'horas_mensuales'");
        return intval($stmt->fetchColumn()) ?: 192;
    }
}
if (!function_exists('getTasaContribucion')) {
    function getTasaContribucion($pdo) {
        $stmt = $pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1");
        return floatval($stmt->fetchColumn()) ?: 5;
    }
}
if (!function_exists('getTrabajadoresActivos')) {
	function getTrabajadoresActivos($pdo) {
		$sql = "SELECT 
					t.id,
					t.codigo,
					t.ci,
					t.nombre_completo,
					COALESCE(CONCAT(cp.id, ' - ', cp.nombre_cargo), 'S/D - Sin cargo') as cargo,
					t.activo,
					t.vacaciones_acumuladas,
					t.foto_ruta,
					t.centro_costo_id,
					t.area_id,
					e.salario_hora_ordinaria, 
					e.salario_mensual, 
					e.escala_numero, 
					a.nombre_area, 
					cc.codigo as centro_costo_codigo, 
					cc.nombre as centro_costo_nombre,
					t.no_acumular_vacaciones,
					t.cuentabanc,
					t.cargo_id
				FROM trabajadores t 
				JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
				LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
				LEFT JOIN areas a ON t.area_id = a.id 
				LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id 
				WHERE t.activo = 1 
				ORDER BY t.nombre_completo";
		
		$stmt = $pdo->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}
if (!function_exists('nombreMesEspanol')) {
    function nombreMesEspanol($mes) {
        $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
        return $meses[$mes] ?? '';
    }
}

// ============================================
// PROCESAR ACCIONES POST
// ============================================

if (isset($_POST['eliminar_nomina_completa'])) {
    $tipo_eliminar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND estado != 'borrador'");
    $check->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
    if ($check->fetchColumn() == 0) {
        $pdo->prepare("DELETE FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
        $pdo->prepare("DELETE FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_eliminar&msg=deleted");
    exit;
}

// 🔽 NUEVO: Eliminar solo las filas en estado Borrador enviadas por el botón "Eliminar Todo" del ajuste
if (isset($_POST['eliminar_borrador_por_ids'])) {
    $tipo_eliminar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $ids_borrador = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
    $ids_borrador = array_values(array_filter(array_map('intval', $ids_borrador)));
    if (!empty($ids_borrador)) {
        $placeholders = implode(',', array_fill(0, count($ids_borrador), '?'));
        $stmt_eb = $pdo->prepare("DELETE FROM nominas WHERE id IN ($placeholders) AND estado = 'borrador'");
        $stmt_eb->execute($ids_borrador);
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_eliminar&msg=deleted");
    exit;
}

if (isset($_POST['eliminar_nomina_individual'])) {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("SELECT estado FROM nominas WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() == 'borrador') {
        $pdo->prepare("DELETE FROM nominas WHERE id = ?")->execute([$id]);
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_nomina_activa&msg=deleted");
    exit;
}

// REGENERAR NÓMINA
if (isset($_POST['regenerar_nomina'])) {
    $tipo_regenerar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    
    $stmt_td = $pdo->prepare("SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
    $stmt_td->execute([$periodo_desde, $periodo_hasta, $tipo_regenerar]);
    $tipo_descuento_reg = $stmt_td->fetchColumn() ?: 'total_rangos';

    $check_cierre = $pdo->prepare("SELECT COUNT(*) FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $check_cierre->execute([$periodo_desde, $periodo_hasta, $tipo_regenerar]);
    if ($check_cierre->fetchColumn() > 0) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_regenerar&error=already_closed");
        exit;
    }
    $pdo->prepare("DELETE FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_regenerar]);
    
    if ($tipo_regenerar == 'automatica') {
        $horas_mensuales = getHorasMensuales($pdo);
        $tasa_contribucion = getTasaContribucion($pdo);
        $trabajadores = getTrabajadoresActivos($pdo);
        foreach ($trabajadores as $trabajador) {
            $salario_hora = $trabajador['salario_hora_ordinaria'];
            $salario_mensual = $trabajador['salario_mensual'];
            $salario_laboral = roundExcel($salario_hora * $horas_mensuales, 2);
            
            $importe_vacaciones_adicional = 0;
            if ($trabajador['no_acumular_vacaciones'] == 1) {
                $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
                $valor_por_dia = $salario_mensual / $dias_laborables;
                $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
            }
            
            $total_devengado = $salario_laboral + $importe_vacaciones_adicional;
            
            if ($tipo_descuento_reg == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($total_devengado);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($total_devengado * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($total_devengado, $rangos_impuesto);
            }
            
            $neto = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
            $pdo->prepare("INSERT INTO nominas (trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, importe_salario_laboral, total_salario_devengado, contribucion_especial, ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0, $salario_laboral, $total_devengado, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo_regenerar, 'borrador', $tipo_descuento_reg]);
        }
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_regenerar&msg=generated");
    } elseif ($tipo_regenerar == 'extraordinaria') {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_regenerar&msg=regenerado_listo");
    }
    exit;
}

if (isset($_POST['generar_nomina_automatica'])) {
    $horas_mensuales = getHorasMensuales($pdo);
    $tasa_contribucion = getTasaContribucion($pdo);
    $trabajadores = getTrabajadoresActivos($pdo);
    $tipo = 'automatica';
    $tipo_descuento = $_POST['tipo_descuento'] ?? 'total_rangos';
    
    $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $check->execute([$periodo_desde, $periodo_hasta, $tipo]);
    if ($check->fetchColumn() > 0) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&error=already_exists");
        exit;
    }
    
    function calcularCESSProgresivo($salario) {
        $limite = 15000;
        if ($salario <= $limite) {
            return roundExcel($salario * 0.05, 2);
        } else {
            $primeraParte = roundExcel($limite * 0.05, 2);
            $exceso = $salario - $limite;
            $segundaParte = roundExcel($exceso * 0.10, 2);
            return roundExcel($primeraParte + $segundaParte, 2);
        }
    }
    
    foreach ($trabajadores as $trabajador) {
        $salario_hora = $trabajador['salario_hora_ordinaria'];
        $salario_mensual = $trabajador['salario_mensual'];
        $salario_laboral = roundExcel($salario_hora * $horas_mensuales, 2);
        
        $importe_vacaciones_adicional = 0;
        if ($trabajador['no_acumular_vacaciones'] == 1) {
            $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
            $valor_por_dia = $salario_mensual / $dias_laborables;
            $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
        }
        
        $total_devengado = $salario_laboral + $importe_vacaciones_adicional;
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCESSProgresivo($total_devengado);
            $impuesto = 0;
            $neto = roundExcel($total_devengado - $contribucion, 2);
            
            $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, 
                importe_salario_laboral, total_salario_devengado, contribucion_especial, 
                ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
                    $salario_laboral, $total_devengado, $contribucion, $impuesto, $neto,
                    roundExcel($contribucion + $impuesto, 2),
                    $tipo, 'borrador', 'CESS progresivo: 5% hasta 15,000 CUP, 10% exceso', 'solo_cess'
                ]);
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado, $rangos_impuesto);
            $neto = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
            
            $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, 
                importe_salario_laboral, total_salario_devengado, contribucion_especial, 
                ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
                    $salario_laboral, $total_devengado, $contribucion, $impuesto, $neto,
                    roundExcel($contribucion + $impuesto, 2), $tipo, 'borrador', 'total_rangos'
                ]);
        }
    }
    
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=generated");
    exit;
}

// GENERAR NÓMINA EXTRAORDINARIA (CON NOCTURNIDAD - Ley 116)
if (isset($_POST['generar_nomina_extraordinaria']) && isset($_POST['confirmar_extraordinaria'])) {
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $horas_por_trabajador = $_POST['horas_trabajadas'] ?? [];           // Horas extras normales
    $horas_nocturnas_por_trabajador = $_POST['horas_nocturnas_trabajadas'] ?? []; // Horas extras nocturnas
    $nocturnidad_por_trabajador = $_POST['nocturnidad_trabajadas'] ?? []; // Nocturnidad (dentro de jornada)
    $tasa_contribucion = getTasaContribucion($pdo);
    $tipo = 'extraordinaria';
    $tipo_descuento_extra = $_POST['tipo_descuento_extra'] ?? 'total_rangos';
    
    $agregados = 0;
    $duplicados = [];
    
    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $dias_a_pagar = floatval($dias_por_trabajador[$index] ?? 1);
        
        // Redondear al 0.5 más cercano y forzar mínimo de 1 día
        $dias_a_pagar = roundExcel($dias_a_pagar * 2) / 2;
        if ($dias_a_pagar < 1) {
            $dias_a_pagar = 1;
        }
        $horas_normales = floatval($horas_por_trabajador[$index] ?? 0);
        $horas_nocturnas_extra = floatval($horas_nocturnas_por_trabajador[$index] ?? 0);
        $nocturnidad = floatval($nocturnidad_por_trabajador[$index] ?? 0);
        
        if ($horas_normales <= 0 && $horas_nocturnas_extra <= 0 && $nocturnidad <= 0) {
            continue;
        }
        
        /*
		// Verificar si el trabajador tiene nómina automática
        $check_auto = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                                     WHERE trabajador_id = ? 
                                     AND periodo_desde = ? 
                                     AND periodo_hasta = ? 
                                     AND tipo_nomina = 'automatica'");
        $check_auto->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        if ($check_auto->fetchColumn() > 0) {
            $duplicados[] = $trabajador_id;
            continue;
        }
		*/
        
        // Obtener salario del trabajador
        $stmt = $pdo->prepare("SELECT e.salario_hora_ordinaria, e.salario_mensual 
                               FROM trabajadores t 
                               JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
                               WHERE t.id = ?");
        $stmt->execute([$trabajador_id]);
        $salario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$salario) {
            continue;
        }
        
        $salario_hora = floatval($salario['salario_hora_ordinaria']);
        $valor_hora_extra = $salario_hora * $recargo_nocturno; // Todas las horas extras al multiplicador parametrizado
        
        // Cálculo de importes
        $importe_horas_normales = roundExcel($salario_hora * $horas_normales, 2);
        $importe_horas_nocturnas_extra = roundExcel($valor_hora_extra * $horas_nocturnas_extra, 2);
        $importe_nocturnidad = roundExcel($valor_hora_extra * $nocturnidad, 2);
        $total_devengado_nuevo = roundExcel($importe_horas_normales + $importe_horas_nocturnas_extra + $importe_nocturnidad, 2);
        
        // Verificar si ya existe nómina extraordinaria
        $stmt_check = $pdo->prepare("SELECT id, horas_laboradas, horas_nocturnas, importe_horas_nocturnas, 
                                            importe_salario_laboral, total_salario_devengado, tipo_descuento 
                                     FROM nominas 
                                     WHERE trabajador_id = ? 
                                     AND periodo_desde = ? 
                                     AND periodo_hasta = ? 
                                     AND tipo_nomina = 'extraordinaria' 
                                     AND estado = 'borrador'");
        $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existente) {
            // ACTUALIZAR NÓMINA EXISTENTE
            $horas_totales = $existente['horas_laboradas'] + $horas_normales;
            $horas_nocturnas_totales = $existente['horas_nocturnas'] + ($horas_nocturnas_extra + $nocturnidad);
            $importe_nocturnas_total = $existente['importe_horas_nocturnas'] + $importe_horas_nocturnas_extra + $importe_nocturnidad;
            $salario_laboral_total = $existente['importe_salario_laboral'] + $importe_horas_normales;
            $total_devengado_total = $existente['total_salario_devengado'] + $total_devengado_nuevo;
            
            // Recalcular impuestos
            if ($tipo_descuento_extra == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($total_devengado_total);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($total_devengado_total * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($total_devengado_total, $rangos_impuesto);
            }
            $neto = roundExcel($total_devengado_total - ($contribucion + $impuesto), 2);
            
            $update = $pdo->prepare("UPDATE nominas SET 
                horas_laboradas = ?, 
                horas_nocturnas = ?,
                importe_horas_nocturnas = ?,
                importe_salario_laboral = ?, 
                total_salario_devengado = ?, 
                contribucion_especial = ?, 
                ingresos_personales = ?, 
                importe_neto = ?, 
                total_deducciones = ?,
                tipo_descuento = ?,
                descripcion = CONCAT(descripcion, ', +', ?)
                WHERE id = ?");
            $update->execute([
                $horas_totales, 
                $horas_nocturnas_totales, 
                $importe_nocturnas_total, 
                $salario_laboral_total, 
                $total_devengado_total, 
                $contribucion, 
                $impuesto, 
                $neto, 
                roundExcel($contribucion + $impuesto, 2),
                $tipo_descuento_extra,
                "HEN:{$horas_normales} HENoc:{$horas_nocturnas_extra} Noct:{$nocturnidad}",
                $existente['id']
            ]);
        } else {
            // INSERTAR NUEVA NÓMINA
            if ($tipo_descuento_extra == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($total_devengado_nuevo);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($total_devengado_nuevo * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($total_devengado_nuevo, $rangos_impuesto);
            }
            $neto = roundExcel($total_devengado_nuevo - ($contribucion + $impuesto), 2);
            
            $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, 
                horas_laboradas, horas_nocturnas, importe_horas_nocturnas,
                importe_salario_laboral, total_salario_devengado, 
                contribucion_especial, ingresos_personales, importe_neto, 
                total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $trabajador_id, $periodo_desde, $periodo_hasta, 
                $horas_normales,                              // horas_laboradas
                ($horas_nocturnas_extra + $nocturnidad),      // horas_nocturnas
                ($importe_horas_nocturnas_extra + $importe_nocturnidad), // importe_horas_nocturnas
                $importe_horas_normales,                      // importe_salario_laboral
                $total_devengado_nuevo,                       // total_salario_devengado
                $contribucion, $impuesto, $neto, 
                roundExcel($contribucion + $impuesto, 2),
                $tipo, 'borrador', 
                "HE Normales: {$horas_normales}h, HE Nocturnas: {$horas_nocturnas_extra}h, Nocturnidad: {$nocturnidad}h", 
                $tipo_descuento_extra
            ]);
        }
        $agregados++;
	}
    
    // REDIRECCIONAR
    if ($agregados > 0) {
        $url = "Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=extraordinaria_added&count=$agregados";
        header($url);
    } else {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&error=no_trabajadores");
    }
    exit;
}

// GENERAR / ADICIONAR NÓMINA DE BONOS (Con cálculo de deducciones y upsert)
if (isset($_POST['generar_nomina_bono']) && isset($_POST['confirmar_bono'])) {
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $montos_bono = $_POST['monto_bono'] ?? [];
    $concepto = trim($_POST['concepto_bono']);
    $tipo = 'bono';
    $tipo_descuento_bono = $_POST['tipo_descuento_bono'] ?? 'total_rangos';
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $agregados = 0; $actualizados = 0;
    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $monto_bono = floatval($montos_bono[$index] ?? 0);
        if ($monto_bono <= 0) continue;
        
        if ($tipo_descuento_bono == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($monto_bono);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($monto_bono * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($monto_bono, $rangos_impuesto);
        }
        $neto = roundExcel($monto_bono - ($contribucion + $impuesto), 2);
        
        $stmt_check = $pdo->prepare("SELECT id, pago_resultado, descripcion FROM nominas WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'bono' AND estado = 'borrador'");
        $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $existente = $stmt_check->fetch();
        
        if ($existente) {
            $nuevo_monto = $existente['pago_resultado'] + $monto_bono;
            $nueva_descripcion = $existente['descripcion'] . " + " . $concepto;
            
            if ($tipo_descuento_bono == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($nuevo_monto);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($nuevo_monto * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($nuevo_monto, $rangos_impuesto);
            }
            $neto = roundExcel($nuevo_monto - ($contribucion + $impuesto), 2);
            
            $pdo->prepare("UPDATE nominas SET pago_resultado = ?, total_salario_devengado = ?, contribucion_especial = ?, ingresos_personales = ?, importe_neto = ?, total_deducciones = ?, descripcion = ?, tipo_descuento = ? WHERE id = ?")
                ->execute([$nuevo_monto, $nuevo_monto, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $nueva_descripcion, $tipo_descuento_bono, $existente['id']]);
            $actualizados++;
        } else {
            $pdo->prepare("INSERT INTO nominas (trabajador_id, periodo_desde, periodo_hasta, pago_resultado, total_salario_devengado, contribucion_especial, ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$trabajador_id, $periodo_desde, $periodo_hasta, $monto_bono, $monto_bono, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo, 'borrador', "$concepto", $tipo_descuento_bono]);
            $agregados++;
        }
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&" . (($agregados > 0 || $actualizados > 0) ? "msg=bono_added&count=" . ($agregados + $actualizados) : "error=no_bonos"));
    exit;
}
if (isset($_POST['generar_nomina_ajuste']) && isset($_POST['confirmar_ajuste'])) {
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $montos_ajuste = $_POST['monto_ajuste'] ?? [];
    $concepto = trim($_POST['concepto_ajuste']);
    $tipo = 'ajuste';
    $tipo_descuento_ajuste = $_POST['tipo_descuento_ajuste'] ?? 'total_rangos';
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $agregados = 0;
    $actualizados = 0;
    
    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $monto_nuevo = floatval($montos_ajuste[$index] ?? 0);
        if ($monto_nuevo <= 0) continue;
        
        // 1. Verificar si ya existe un ajuste BORRADOR para este trabajador en el mismo período
        $stmt_check = $pdo->prepare("SELECT id, pago_resultado, descripcion FROM nominas 
                                     WHERE trabajador_id = ? 
                                     AND periodo_desde = ? 
                                     AND periodo_hasta = ? 
                                     AND tipo_nomina = 'ajuste' 
                                     AND estado = 'borrador'");
        $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existente) {
            // 2. Si existe, acumulamos el nuevo monto
            $monto_total = $existente['pago_resultado'] + $monto_nuevo;
            $descripcion_nueva = $existente['descripcion'] . " + " . $concepto;
            
            // Recalcular impuestos sobre el total acumulado
            if ($tipo_descuento_ajuste == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($monto_total);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($monto_total * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($monto_total, $rangos_impuesto);
            }
            $neto = roundExcel($monto_total - ($contribucion + $impuesto), 2);
            
            // Actualizar el registro existente
            $update = $pdo->prepare("UPDATE nominas 
                                     SET pago_resultado = ?,
                                         total_salario_devengado = ?,
                                         contribucion_especial = ?,
                                         ingresos_personales = ?,
                                         importe_neto = ?,
                                         total_deducciones = ?,
                                         descripcion = ?,
                                         tipo_descuento = ?
                                     WHERE id = ?");
            $update->execute([
                $monto_total,
                $monto_total,
                $contribucion,
                $impuesto,
                $neto,
                roundExcel($contribucion + $impuesto, 2),
                $descripcion_nueva,
                $tipo_descuento_ajuste,
                $existente['id']
            ]);
            $actualizados++;
        } else {
            // 3. No existe, insertar nuevo registro
            if ($tipo_descuento_ajuste == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($monto_nuevo);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($monto_nuevo * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($monto_nuevo, $rangos_impuesto);
            }
            $neto = roundExcel($monto_nuevo - ($contribucion + $impuesto), 2);
            
            $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, pago_resultado, total_salario_devengado,
                contribucion_especial, ingresos_personales, importe_neto,
                total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $trabajador_id, $periodo_desde, $periodo_hasta,
                $monto_nuevo, $monto_nuevo,
                $contribucion, $impuesto, $neto,
                roundExcel($contribucion + $impuesto, 2),
                $tipo, 'borrador', "$concepto", $tipo_descuento_ajuste
            ]);
            $agregados++;
        }
    }
    
    // Redirección con mensaje adecuado
    if ($agregados > 0 || $actualizados > 0) {
        $total = $agregados + $actualizados;
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=ajuste_procesado&count=$total&nuevos=$agregados&actualizados=$actualizados");
    } else {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&error=no_ajustes");
    }
    exit;
}
// AGREGAR VACACIONES
if (isset($_POST['agregar_vacaciones'])) {
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $dias_por_trabajador = $_POST['dias_vacaciones'] ?? [];
    $tipo = 'vacaciones';
    $tipo_descuento_vac = $_POST['tipo_descuento_vacaciones'] ?? 'total_rangos';
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $agregados = 0;
    $rechazados = 0;
    $errores = [];
    
    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $dias_a_pagar = floatval($dias_por_trabajador[$index] ?? 0);
        if ($dias_a_pagar <= 0) {
            $rechazados++;
            $errores[] = "Días inválidos para trabajador ID $trabajador_id";
            continue;
        }
        
        $stmt_check_dias = $pdo->prepare("SELECT COALESCE(vacaciones_acumuladas, 0) as dias_acumulados FROM trabajadores WHERE id = ?");
        $stmt_check_dias->execute([$trabajador_id]);
        $dias_acumulados = floatval($stmt_check_dias->fetchColumn());
        
        $stmt_existentes = $pdo->prepare("SELECT COALESCE(SUM(dias_vacaciones_tomados), 0) as total_dias 
                                          FROM nominas 
                                          WHERE trabajador_id = ? 
                                          AND periodo_desde = ? 
                                          AND periodo_hasta = ? 
                                          AND tipo_nomina = 'vacaciones' 
                                          AND estado = 'borrador'");
        $stmt_existentes->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $dias_ya_tomados = floatval($stmt_existentes->fetchColumn());
        
        $dias_totales = $dias_ya_tomados + $dias_a_pagar;
        
        if ($dias_acumulados < $dias_totales) {
            $stmt_nombre = $pdo->prepare("SELECT nombre_completo FROM trabajadores WHERE id = ?");
            $stmt_nombre->execute([$trabajador_id]);
            $nombre = $stmt_nombre->fetchColumn();
            $errores[] = "$nombre: No tiene suficientes días. Disponibles: $dias_acumulados, Ya tomados: $dias_ya_tomados, Solicitados: $dias_a_pagar";
            $rechazados++;
            continue;
        }
        
        $stmt_check = $pdo->prepare("SELECT id, dias_vacaciones_tomados, importe_vacaciones 
                                     FROM nominas 
                                     WHERE trabajador_id = ? 
                                     AND periodo_desde = ? 
                                     AND periodo_hasta = ? 
                                     AND tipo_nomina = 'vacaciones' 
                                     AND estado = 'borrador'");
        $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $existente = $stmt_check->fetch();
        
        $stmt_salario = $pdo->prepare("SELECT e.salario_mensual 
                                       FROM trabajadores t 
                                       JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
                                       WHERE t.id = ?");
        $stmt_salario->execute([$trabajador_id]);
        $salario_mensual = floatval($stmt_salario->fetchColumn());
        $valor_por_dia = $salario_mensual / $dias_laborables;
        $importe = roundExcel($dias_totales * $valor_por_dia, 2);
        
        if ($tipo_descuento_vac == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($importe);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($importe * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($importe, $rangos_impuesto);
        }
        $neto = roundExcel($importe - ($contribucion + $impuesto), 2);
        
        if ($existente) {
            $update = $pdo->prepare("UPDATE nominas 
                                      SET dias_vacaciones_tomados = ?, 
                                          importe_vacaciones = ?, 
                                          total_salario_devengado = ?, 
                                          contribucion_especial = ?,
                                          ingresos_personales = ?,
                                          importe_neto = ?,
                                          total_deducciones = ?,
                                          tipo_descuento = ?
                                      WHERE id = ?");
            $update->execute([$dias_totales, $importe, $importe, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo_descuento_vac, $existente['id']]);
            $agregados++;
        } else {
            $insert = $pdo->prepare("INSERT INTO nominas 
                                      (trabajador_id, periodo_desde, periodo_hasta, 
                                       dias_vacaciones_tomados, importe_vacaciones, 
                                       total_salario_devengado, contribucion_especial, 
                                       ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento) 
                                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $insert->execute([$trabajador_id, $periodo_desde, $periodo_hasta, 
                             $dias_a_pagar, $importe, $importe, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo, 'borrador', $tipo_descuento_vac]);
            $agregados++;
        }
    }
    
    if ($agregados > 0) {
        $url = "Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=vacaciones_added&count=" . $agregados;
        if (!empty($errores)) {
            $url .= "&errores=" . urlencode(implode('; ', $errores));
        }
        header($url);
    } else {
        $url = "Location: nominas.php?periodo=$periodo&tipo=$tipo&error=no_validos";
        if (!empty($errores)) {
            $url .= "&errores=" . urlencode(implode('; ', $errores));
        }
        header($url);
    }
    exit;
}

if (isset($_POST['agregar_bono_existente'])) {
    $trabajador_id = intval($_POST['trabajador_id_bono']);
    $monto_bono = floatval($_POST['monto_bono']);
    $concepto = trim($_POST['concepto_bono']);
    $tipo = 'bono';
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $stmt_check = $pdo->prepare("SELECT id, pago_resultado, descripcion, tipo_descuento FROM nominas WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'bono' AND estado = 'borrador'");
    $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
    $existente = $stmt_check->fetch();
    
    if ($existente) {
        $nuevo_monto = $existente['pago_resultado'] + $monto_bono;
        $nueva_descripcion = $existente['descripcion'] . " + " . $concepto;
        $tipo_descuento = $existente['tipo_descuento'] ?? 'total_rangos';
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($nuevo_monto);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($nuevo_monto * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($nuevo_monto, $rangos_impuesto);
        }
        $neto = roundExcel($nuevo_monto - ($contribucion + $impuesto), 2);
        
        $pdo->prepare("UPDATE nominas SET pago_resultado = ?, total_salario_devengado = ?, contribucion_especial = ?, ingresos_personales = ?, importe_neto = ?, total_deducciones = ?, descripcion = ? WHERE id = ?")
            ->execute([$nuevo_monto, $nuevo_monto, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $nueva_descripcion, $existente['id']]);
    } else {
        $tipo_descuento = 'total_rangos';
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($monto_bono);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($monto_bono * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($monto_bono, $rangos_impuesto);
        }
        $neto = roundExcel($monto_bono - ($contribucion + $impuesto), 2);
        
        $pdo->prepare("INSERT INTO nominas (trabajador_id, periodo_desde, periodo_hasta, pago_resultado, total_salario_devengado, contribucion_especial, ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$trabajador_id, $periodo_desde, $periodo_hasta, $monto_bono, $monto_bono, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo, 'borrador', "$concepto", $tipo_descuento]);
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=bono_added&count=1");
    exit;
}

if (isset($_POST['contabilizar_nomina'])) {
    $tipo_contabilizar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $observaciones = isset($_POST['observaciones_cierre']) ? urldecode($_POST['observaciones_cierre']) : '';
    
    // 1. Verificar si existen registros de nómina en este período
    if ($tipo_contabilizar == 'ajuste') {
        // Para ajuste solo se contabilizan borradores sin número asignado
        $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND estado = 'borrador' AND numero_nomina IS NULL");
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    }
    $check->execute([$periodo_desde, $periodo_hasta, $tipo_contabilizar]);
    if ($check->fetchColumn() == 0) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&error=no_nomina");
        exit;
    }
    
    // 2. Verificar si el período ya está cerrado
    // Para ajuste se permiten MÚLTIPLES nóminas en el mismo período, por lo que
    // la existencia de un cierre previo NO bloquea la contabilización de un nuevo borrador.
    if ($tipo_contabilizar == 'ajuste') {
        $check_cierre = 0;
    } else {
        $check_cierre = $pdo->prepare("SELECT COUNT(*) FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
        $check_cierre->execute([$periodo_desde, $periodo_hasta, $tipo_contabilizar]);
        $check_cierre = $check_cierre->fetchColumn();
    }
    
    if ($check_cierre == 0) {
        try {
            // Iniciar transacción de base de datos para asegurar consistencia
            $pdo->beginTransaction();
            
            // =========================================================
            // LÓGICA ESPECÍFICA POR TIPO DE NÓMINA (AUTOMÁTICA)
            // =========================================================
            if ($tipo_contabilizar == 'automatica') {
                $stmt = $pdo->prepare("
                    SELECT n.id, n.trabajador_id, n.horas_laboradas, t.no_acumular_vacaciones, e.salario_mensual
                    FROM nominas n 
                    JOIN trabajadores t ON n.trabajador_id = t.id 
                    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                    WHERE n.periodo_desde = ? 
                    AND n.periodo_hasta = ? 
                    AND n.tipo_nomina = ?
                ");
                $stmt->execute([$periodo_desde, $periodo_hasta, $tipo_contabilizar]);
                $nominas_periodo = $stmt->fetchAll();
                
                foreach ($nominas_periodo as $nomina) {
                    if (empty($nomina['no_acumular_vacaciones']) || $nomina['no_acumular_vacaciones'] == 0) {
                        // Acumulación proporcional al tiempo efectivamente laborado
                        // Método 9.09% (Ley 116, Art. 102): Días = (Horas laboradas × 0.0909) / Jornada diaria
                        $horas_laboradas_n = floatval($nomina['horas_laboradas'] ?? 0);
                        $dias_a_acumular = roundExcel(($horas_laboradas_n * $factor_909) / $horas_jornada, 2);
                        $valor_por_dia = $nomina['salario_mensual'] / $dias_laborables;
                        $importe_a_acumular = roundExcel($dias_a_acumular * $valor_por_dia, 2);
                        
                        $stmt_dias = $pdo->prepare("SELECT vacaciones_acumuladas FROM trabajadores WHERE id = ?");
                        $stmt_dias->execute([$nomina['trabajador_id']]);
                        $dias_actuales = floatval($stmt_dias->fetchColumn());
                        
                        $stmt_importe = $pdo->prepare("SELECT COALESCE(SUM(importe_vacaciones_acumulado_mes), 0) 
                                                       FROM nominas 
                                                       WHERE trabajador_id = ? 
                                                       AND tipo_nomina IN ('automatica')
                                                       AND estado = 'contabilizado'
                                                       AND periodo_desde < ?");
                        $stmt_importe->execute([$nomina['trabajador_id'], $periodo_desde]);
                        $importe_anterior = floatval($stmt_importe->fetchColumn());
                        
                        $nuevo_total_dias = $dias_actuales + $dias_a_acumular;
                        $nuevo_total_importe = $importe_anterior + $importe_a_acumular;
                        
                        if ($dias_a_acumular > 0) {
                            $pdo->prepare("UPDATE trabajadores 
                                          SET vacaciones_acumuladas = vacaciones_acumuladas + ? 
                                          WHERE id = ?")->execute([$dias_a_acumular, $nomina['trabajador_id']]);
                        }
                        
                        $update_nomina = $pdo->prepare("UPDATE nominas 
                                                      SET vacaciones_acumuladas_mes = ?, 
                                                          importe_vacaciones_acumulado_mes = ?,
                                                          total_vacaciones_acumuladas = ?,
                                                          total_importe_vacaciones_acumuladas = ?
                                                      WHERE id = ?");
                        $update_nomina->execute([
                            $dias_a_acumular, 
                            $importe_a_acumular, 
                            $nuevo_total_dias,
                            $nuevo_total_importe,
                            $nomina['id']
                        ]);
                        
                        // REGISTRAR EN SUBMAYOR DE VACACIONES - ACUMULACIÓN
                        $stmt_check_submayor = $pdo->prepare("SELECT COUNT(*) FROM submayor_vacaciones 
                                                              WHERE nomina_id = ? AND tipo_movimiento = 'acumulacion'");
                        $stmt_check_submayor->execute([$nomina['id']]);
                        $ya_registrado = $stmt_check_submayor->fetchColumn();
                        
                        if ($dias_a_acumular > 0 && !$ya_registrado) {
                            $stmt_submayor = $pdo->prepare("INSERT INTO submayor_vacaciones 
                                (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, 
                                 nomina_id, tipo_nomina, referencia, usuario_registro, observaciones) 
                                VALUES (?, ?, ?, 'acumulacion', ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_submayor->execute([
                                $nomina['trabajador_id'],
                                $periodo_desde,
                                $periodo_hasta,
                                $dias_a_acumular,
                                $importe_a_acumular,
                                $nomina['id'],
                                $tipo_contabilizar,
                                "Acumulación (Período: " . date('m/Y', strtotime($periodo_desde)) . ")",
                                $user_nombre_completo,
                                "Acumulación de {$dias_a_acumular} días por {$horas_laboradas_n} horas - Salario: $" . number_format($nomina['salario_mensual'], 2)
                            ]);
                        }
                    }
                }
            }
            
            // =========================================================
            // LÓGICA ESPECÍFICA POR TIPO DE NÓMINA (VACACIONES)
            // =========================================================
            if ($tipo_contabilizar == 'vacaciones') {
                $stmt = $pdo->prepare("SELECT n.id, n.trabajador_id, n.dias_vacaciones_tomados, n.importe_vacaciones
                                       FROM nominas n 
                                       WHERE n.periodo_desde = ? 
                                       AND n.periodo_hasta = ? 
                                       AND n.tipo_nomina = ? 
                                       AND n.estado = 'borrador'");
                $stmt->execute([$periodo_desde, $periodo_hasta, $tipo_contabilizar]);
                $vacaciones_pendientes = $stmt->fetchAll();
                
                $errores_vacaciones = [];
                
                foreach ($vacaciones_pendientes as $vac) {
                    $stmt_check = $pdo->prepare("SELECT COALESCE(vacaciones_acumuladas, 0) as dias_actuales 
                                                 FROM trabajadores WHERE id = ?");
                    $stmt_check->execute([$vac['trabajador_id']]);
                    $dias_actuales = floatval($stmt_check->fetchColumn());
                    $dias_a_restar = floatval($vac['dias_vacaciones_tomados']);
                    
                    if ($dias_actuales < $dias_a_restar) {
                        $stmt_nombre = $pdo->prepare("SELECT nombre_completo FROM trabajadores WHERE id = ?");
                        $stmt_nombre->execute([$vac['trabajador_id']]);
                        $nombre = $stmt_nombre->fetchColumn();
                        $errores_vacaciones[] = "$nombre: No tiene suficientes días para disfrutar. Disponibles: $dias_actuales, Solicitados: $dias_a_restar";
                        $pdo->prepare("UPDATE nominas SET estado = 'error' WHERE id = ?")->execute([$vac['id']]);
                    } else {
                        $dias_restantes = $dias_actuales - $dias_a_restar;
                        $update = $pdo->prepare("UPDATE trabajadores 
                                                  SET vacaciones_acumuladas = vacaciones_acumuladas - ? 
                                                  WHERE id = ?");
                        $update->execute([$dias_a_restar, $vac['trabajador_id']]);
                        $update_nomina = $pdo->prepare("UPDATE nominas 
                                                        SET dias_restantes = ? 
                                                        WHERE id = ?");
                        $update_nomina->execute([$dias_restantes, $vac['id']]);
                        
                        // REGISTRAR EN SUBMAYOR DE VACACIONES - DISFRUTE
                        $stmt_check_submayor = $pdo->prepare("SELECT COUNT(*) FROM submayor_vacaciones 
                                                              WHERE nomina_id = ? AND tipo_movimiento = 'disfrute'");
                        $stmt_check_submayor->execute([$vac['id']]);
                        $ya_registrado = $stmt_check_submayor->fetchColumn();
                        
                        if (!$ya_registrado) {
                            $stmt_submayor = $pdo->prepare("INSERT INTO submayor_vacaciones 
                                (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, 
                                 nomina_id, tipo_nomina, referencia, usuario_registro, observaciones) 
                                VALUES (?, ?, ?, 'disfrute', ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_submayor->execute([
                                $vac['trabajador_id'],
                                $periodo_desde,
                                $periodo_hasta,
                                $dias_a_restar,
                                floatval($vac['importe_vacaciones']),
                                $vac['id'],
                                'vacaciones',
                                "Disfrute  - Período: " . date('m/Y', strtotime($periodo_desde)),
                                $user_nombre_completo,
                                "Disfrute de {$dias_a_restar} días de vacaciones. Saldo restante: {$dias_restantes} días"
                            ]);
                        }
                    }
                }
                
                // Si hay errores de saldo de vacaciones, revertimos los cambios realizados
                if (!empty($errores_vacaciones)) {
                    $_SESSION['error_vacaciones'] = $errores_vacaciones;
                    $pdo->rollBack();
                    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&error=error_vacaciones");
                    exit;
                }
            }
// =========================================================
            // LÓGICA ESPECÍFICA POR TIPO DE NÓMINA (BONOS - REDISTRIBUCIÓN)
            // =========================================================
            if ($tipo_contabilizar == 'bono') {
                // Asegurar creación de la tabla si no existe
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS montos_distrib (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        mes VARCHAR(20) NOT NULL,
                        anio INT NOT NULL,
                        importe_dis DECIMAL(12, 2) NOT NULL,
                        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_mes_anio (mes, anio)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Calcular la suma de lo que se va a contabilizar en estado borrador (antes del UPDATE de estado)
                $stmt_dist = $pdo->prepare("
                    SELECT COALESCE(SUM(pago_resultado), 0) 
                    FROM nominas 
                    WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'bono' AND estado = 'borrador'
                ");
                $stmt_dist->execute([$periodo_desde, $periodo_hasta]);
                $total_redistribuido = floatval($stmt_dist->fetchColumn());

                if ($total_redistribuido > 0) {
                    // Convertir el mes a minúsculas
                    $nombre_mes_esp = mb_strtolower(nombreMesEspanol($mes), 'UTF-8');

                    // Guardar/Actualizar en la tabla montos_distrib
                    $stmt_ins_dist = $pdo->prepare("
                        INSERT INTO montos_distrib (mes, anio, importe_dis) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE importe_dis = ?
                    ");
                    $stmt_ins_dist->execute([
                        $nombre_mes_esp, 
                        intval($anio), 
                        $total_redistribuido, 
                        $total_redistribuido
                    ]);
                }
            }
			
            // =========================================================
            // GENERACIÓN DE NÚMERO DE SECUENCIA UNIFICADO
            // =========================================================
            // 1. Incrementar y bloquear secuencia para evitar concurrencia
            $stmt_seq = $pdo->prepare("
                INSERT INTO secuencias_nominas (tipo_nomina, ultimo_numero) 
                VALUES (?, 1) 
                ON DUPLICATE KEY UPDATE ultimo_numero = ultimo_numero + 1
            ");
            $stmt_seq->execute([$tipo_contabilizar]);
            
            // 2. Obtener el número consecutivo
            $stmt_get = $pdo->prepare("SELECT ultimo_numero FROM secuencias_nominas WHERE tipo_nomina = ?");
            $stmt_get->execute([$tipo_contabilizar]);
            $numero = $stmt_get->fetchColumn();
            
            // 3. Formatear código único por tipo
            switch ($tipo_contabilizar) {
                case 'extraordinaria':
                    $numero_nomina = 'EX' . str_pad($numero, 2, '0', STR_PAD_LEFT);
                    break;
                case 'vacaciones':
                    $numero_nomina = 'VAC' . str_pad($numero, 2, '0', STR_PAD_LEFT);
                    break;
                case 'bono':
                    $numero_nomina = 'BONO' . str_pad($numero, 2, '0', STR_PAD_LEFT);
                    break;
                case 'automatica':
                    $numero_nomina = 'AUT' . str_pad($numero, 2, '0', STR_PAD_LEFT);
                    break;
                default:
                    $numero_nomina = strtoupper(substr($tipo_contabilizar, 0, 3)) . str_pad($numero, 2, '0', STR_PAD_LEFT);
            }
            
            // =========================================================
            // ACTUALIZACIÓN DE NÓMINAS & INSERCIÓN DE CIERRE
            // =========================================================
            
            // 4. Cambiar estado a contabilizado e insertar el número de nómina generado
            $pdo->prepare("
                UPDATE nominas 
                SET estado = 'contabilizado', 
                    fecha_contab = NOW(), 
                    numero_nomina = ?,
                    total_deducciones = ROUND(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0) + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0), 2)
                WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND estado = 'borrador'
				AND numero_nomina IS NULL
            ")->execute([$numero_nomina, $periodo_desde, $periodo_hasta, $tipo_contabilizar]);
            
            // 5. Calcular los totales de la nómina que se acaba de cerrar
            $stmt_totales = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_trabajadores,
                    SUM(total_salario_devengado) as total_devengado,
                    SUM(contribucion_especial) as total_contribucion,
                    SUM(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0) + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0)) as total_deducciones,
                    SUM(importe_neto) as total_neto,
                    SUM(COALESCE(importe_vacaciones, 0)) as total_vacaciones_pagadas
                FROM nominas 
                WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND numero_nomina = ?
            ");
            $stmt_totales->execute([$periodo_desde, $periodo_hasta, $tipo_contabilizar, $numero_nomina]);
            $totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);
            
            // 6. Registrar en cierres_nomina asociando el nuevo código generado
            $pdo->prepare("
                INSERT INTO cierres_nomina 
                (periodo_desde, periodo_hasta, tipo_nomina, numero_nomina, fecha_cierre, estado, observaciones, 
                 total_trabajadores, total_devengado, total_deducciones, total_neto, 
                 total_contribucion, total_vacaciones_pagadas, usuario_cierre) 
                VALUES (?, ?, ?, ?, NOW(), 'cerrado', ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $periodo_desde, 
                $periodo_hasta, 
                $tipo_contabilizar, 
                $numero_nomina,
                $observaciones,
                $totales['total_trabajadores'],
                $totales['total_devengado'] ?? 0,
                $totales['total_deducciones'] ?? 0,
                $totales['total_neto'] ?? 0,
                $totales['total_contribucion'] ?? 0,
                $totales['total_vacaciones_pagadas'] ?? 0,
                $user_nombre_completo
            ]);
            
            // Consolidar todos los cambios si no hubo errores
            $pdo->commit();
            
            header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&msg=contabilized&code=$numero_nomina");
            
        } catch (Exception $e) {
            // Revertir toda la transacción si ocurre un error inesperado
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&error=" . urlencode($e->getMessage()));
        }
    } else {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&error=already_closed");
    }
    exit;
}

// ============================================
// REVERTIR (DESCONTABILIZAR) NÓMINA CONTABILIZADA
// Deshace el estado, el cierre y todos los efectos colaterales
// (acumulación/disfrute de vacaciones, submayor y montos_distrib de bonos)
// ============================================
if (isset($_POST['revertir_nomina'])) {
    $tipo_revertir = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $numero_revertir = trim($_POST['numero_nomina'] ?? '');

    if ($numero_revertir === '') {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_revertir&error=revert_no_numero");
        exit;
    }

    // 1. Verificar que exista el cierre de esa nómina
    $stmt_cierre = $pdo->prepare("SELECT * FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND numero_nomina = ?");
    $stmt_cierre->execute([$periodo_desde, $periodo_hasta, $tipo_revertir, $numero_revertir]);
    $cierre_revertir = $stmt_cierre->fetch();

    if (!$cierre_revertir) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_revertir&error=revert_no_cierre");
        exit;
    }

    // 2. Obtener las filas contabilizadas con ese número de nómina
    $stmt_nom = $pdo->prepare("SELECT id, trabajador_id, vacaciones_acumuladas_mes, importe_vacaciones_acumulado_mes FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND numero_nomina = ? AND estado = 'contabilizado'");
    $stmt_nom->execute([$periodo_desde, $periodo_hasta, $tipo_revertir, $numero_revertir]);
    $nominas_revertir = $stmt_nom->fetchAll();

    if (empty($nominas_revertir)) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_revertir&error=revert_no_nomina");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $ids_revertir = array_column($nominas_revertir, 'id');
        $placeholders_ids = implode(',', array_fill(0, count($ids_revertir), '?'));

        // 3. Deshacer efectos colaterales según el tipo de nómina
        if ($tipo_revertir == 'automatica') {
            // Restar los días acumulados a cada trabajador
            foreach ($nominas_revertir as $nomina_r) {
                $dias_acum = null;
                $stmt_sv = $pdo->prepare("SELECT dias FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'acumulacion' LIMIT 1");
                $stmt_sv->execute([$nomina_r['id']]);
                $fila_sv = $stmt_sv->fetch();
                if ($fila_sv) {
                    $dias_acum = floatval($fila_sv['dias']);
                } elseif ($nomina_r['vacaciones_acumuladas_mes'] !== null) {
                    $dias_acum = floatval($nomina_r['vacaciones_acumuladas_mes']);
                }
                if ($dias_acum !== null && $dias_acum > 0) {
                    $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas - ? WHERE id = ?")->execute([$dias_acum, $nomina_r['trabajador_id']]);
                }
            }
            // Limpiar los totales calculados al contabilizar (no aplican en borrador)
            $pdo->prepare("UPDATE nominas SET total_vacaciones_acumuladas = NULL, total_importe_vacaciones_acumuladas = NULL WHERE id IN ($placeholders_ids)")->execute($ids_revertir);
        }

        if ($tipo_revertir == 'vacaciones') {
            // Devolver los días de disfrute a cada trabajador
            foreach ($nominas_revertir as $nomina_r) {
                $dias_disfrutados = null;
                $stmt_sv = $pdo->prepare("SELECT dias FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'disfrute' LIMIT 1");
                $stmt_sv->execute([$nomina_r['id']]);
                $fila_sv = $stmt_sv->fetch();
                if ($fila_sv) {
                    $dias_disfrutados = floatval($fila_sv['dias']);
                }
                if ($dias_disfrutados !== null && $dias_disfrutados > 0) {
                    $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas + ? WHERE id = ?")->execute([$dias_disfrutados, $nomina_r['trabajador_id']]);
                }
            }
            // Quitar el saldo restante que se calculó al contabilizar
            $pdo->prepare("UPDATE nominas SET dias_restantes = NULL WHERE id IN ($placeholders_ids)")->execute($ids_revertir);
        }

        if ($tipo_revertir == 'bono') {
            // Recalcular el monto distribuido del mes sin esta nómina
            $stmt_restante = $pdo->prepare("SELECT COALESCE(SUM(pago_resultado), 0) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'bono' AND estado = 'contabilizado' AND numero_nomina != ?");
            $stmt_restante->execute([$periodo_desde, $periodo_hasta, $numero_revertir]);
            $restante_bono = floatval($stmt_restante->fetchColumn());

            $nombre_mes_esp = mb_strtolower(nombreMesEspanol($mes), 'UTF-8');
            if ($restante_bono > 0) {
                $pdo->prepare("UPDATE montos_distrib SET importe_dis = ? WHERE mes = ? AND anio = ?")->execute([$restante_bono, $nombre_mes_esp, intval($anio)]);
            } else {
                $pdo->prepare("DELETE FROM montos_distrib WHERE mes = ? AND anio = ?")->execute([$nombre_mes_esp, intval($anio)]);
            }
        }

        // 4. Eliminar los movimientos del submayor creados al contabilizar
        $pdo->prepare("DELETE FROM submayor_vacaciones WHERE nomina_id IN ($placeholders_ids) AND tipo_movimiento IN ('acumulacion', 'disfrute')")->execute($ids_revertir);

        // 5. Volver la nómina a estado borrador sin número asignado
        $pdo->prepare("UPDATE nominas SET estado = 'borrador', numero_nomina = NULL, fecha_contab = NULL WHERE id IN ($placeholders_ids) AND estado = 'contabilizado'")->execute($ids_revertir);

        // 6. Eliminar el cierre de la nómina revertida
        $pdo->prepare("DELETE FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND numero_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_revertir, $numero_revertir]);

        $pdo->commit();

        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_revertir&msg=reverted&code=$numero_revertir");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_revertir&error=" . urlencode($e->getMessage()));
    }
    exit;
}


if (isset($_POST['agregar_extraordinaria_existente'])) {
    $trabajador_id = intval($_POST['trabajador_id_extra']);
    $horas_extra = floatval($_POST['horas_extra']);
    $horas_nocturnas_extra = floatval($_POST['horas_nocturnas_extra'] ?? 0);
    $concepto = trim($_POST['concepto_extra'] ?? '');
    $tipo = 'extraordinaria';
    $tasa_contribucion = getTasaContribucion($pdo);
    
 $check_auto = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'automatica'");
    $check_auto->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
    if ($check_auto->fetchColumn() > 0) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&error=worker_in_automatica");
        exit;
    }
    
    // CORRECCIÓN: Buscamos de forma activa el tipo de descuento que ya tenga la nómina extraordinaria de este período
    $stmt_td_check = $pdo->prepare("SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'extraordinaria' LIMIT 1");
    $stmt_td_check->execute([$periodo_desde, $periodo_hasta]);
    $tipo_descuento = $stmt_td_check->fetchColumn() ?: 'total_rangos';
    
    $stmt = $pdo->prepare("SELECT e.salario_hora_ordinaria FROM trabajadores t JOIN escalas_salariales e ON t.escala_salarial_id = e.id WHERE t.id = ?");
    $stmt->execute([$trabajador_id]);
    $salario_hora = floatval($stmt->fetchColumn());
    
    // ==========================================
    // CÁLCULOS CORRECTOS CON NOCTURNIDAD (Ley 116)
    // ==========================================
    $salario_laboral_normal = roundExcel($salario_hora * $horas_extra, 2);
    $valor_hora_nocturna = roundExcel($salario_hora * $recargo_nocturno, 2);
    $importe_nocturnas = roundExcel($valor_hora_nocturna * $horas_nocturnas_extra, 2);
    $total_devengado_nuevo = roundExcel($salario_laboral_normal + $importe_nocturnas, 2);
    
    if ($existente) {
        // ==========================================
        // ACTUALIZAR NÓMINA EXTRAORDINARIA EXISTENTE
        // ==========================================
        $update = $pdo->prepare("UPDATE nominas SET 
            horas_laboradas = horas_laboradas + ?, 
            horas_nocturnas = horas_nocturnas + ?,
            importe_horas_nocturnas = importe_horas_nocturnas + ?,
            importe_salario_laboral = importe_salario_laboral + ?, 
            total_salario_devengado = total_salario_devengado + ?, 
            descripcion = CONCAT(descripcion, ', ', ?) 
            WHERE id = ?");
        $update->execute([
            $horas_extra, 
            $horas_nocturnas_extra, 
            $importe_nocturnas, 
            $salario_laboral_normal, 
            $total_devengado_nuevo, 
            $concepto, 
            $existente['id']
        ]);
        
        // Obtener el nuevo total después de la actualización
        $stmt_tot = $pdo->prepare("SELECT total_salario_devengado FROM nominas WHERE id = ?");
        $stmt_tot->execute([$existente['id']]);
        $total_devengado_final = floatval($stmt_tot->fetchColumn());
        
        // Recalcular impuestos con el nuevo total
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado_final);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado_final * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado_final, $rangos_impuesto);
        }
        $neto = roundExcel($total_devengado_final - ($contribucion + $impuesto), 2);
        
        $pdo->prepare("UPDATE nominas SET contribucion_especial = ?, ingresos_personales = ?, importe_neto = ?, total_deducciones = ? WHERE id = ?")->execute([$contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $existente['id']]);
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=extra_added_existing");
    } else {
        // ==========================================
        // INSERTAR NUEVA NÓMINA EXTRAORDINARIA
        // ==========================================
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado_nuevo);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado_nuevo * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado_nuevo, $rangos_impuesto);
        }
        $neto = roundExcel($total_devengado_nuevo - ($contribucion + $impuesto), 2);
        
        $pdo->prepare("INSERT INTO nominas (
            trabajador_id, periodo_desde, periodo_hasta, 
            horas_laboradas, horas_nocturnas, importe_horas_nocturnas,
            importe_salario_laboral, total_salario_devengado, 
            contribucion_especial, ingresos_personales, importe_neto, 
            total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $trabajador_id, $periodo_desde, $periodo_hasta, 
            $horas_extra, $horas_nocturnas_extra, $importe_nocturnas,
            $salario_laboral_normal, $total_devengado_nuevo, 
            $contribucion, $impuesto, $neto, 
            roundExcel($contribucion + $impuesto, 2),
            $tipo, 'borrador', 
            "Horas extras: $horas_extra, Horas nocturnas: $horas_nocturnas_extra", 
            $tipo_descuento
        ]);
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=extra_added_new");
    }
    exit;
}

// ============================================
// AGREGAR TRABAJADORES A NÓMINA AUTOMÁTICA EXISTENTE (AJAX)
// ============================================
if (isset($_POST['agregar_trabajadores_auto']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $trabajadores_ids = $_POST['trabajadores'] ?? [];
    $periodo_desde = $_POST['periodo_desde'];
    $periodo_hasta = $_POST['periodo_hasta'];
    $tipo_descuento = $_POST['tipo_descuento'] ?? 'total_rangos';
    $horas_mensuales = getHorasMensuales($pdo);
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $agregados = 0;
    $errores = [];
    
    foreach ($trabajadores_ids as $trabajador_id) {
        // Verificar si ya existe
        $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'automatica'");
        $check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        if ($check->fetchColumn() > 0) {
            $errores[] = $trabajador_id;
            continue;
        }
        
        // Obtener datos del trabajador
        $stmt = $pdo->prepare("SELECT t.*, e.salario_hora_ordinaria, e.salario_mensual, e.escala_numero, t.no_acumular_vacaciones 
                               FROM trabajadores t 
                               JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
                               WHERE t.id = ? AND t.activo = 1");
        $stmt->execute([$trabajador_id]);
        $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trabajador) {
            continue;
        }
        
        $salario_hora = floatval($trabajador['salario_hora_ordinaria']);
        $salario_mensual = floatval($trabajador['salario_mensual']);
        $salario_laboral = roundExcel($salario_hora * $horas_mensuales, 2);
        
        // Vacaciones proporcional
        $importe_vacaciones_adicional = 0;
        if ($trabajador['no_acumular_vacaciones'] == 1) {
            $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
            $valor_por_dia = $salario_mensual / $dias_laborables;
            $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
        }
        
        $total_devengado = $salario_laboral + $importe_vacaciones_adicional;
        
        // Calcular impuestos
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado, $rangos_impuesto);
        }
        
        $neto = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
        
        $insert = $pdo->prepare("INSERT INTO nominas 
            (trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, 
             importe_salario_laboral, total_salario_devengado, contribucion_especial, 
             ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        
        $result = $insert->execute([
            $trabajador_id, $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
            $salario_laboral, $total_devengado, $contribucion, $impuesto, $neto,
            roundExcel($contribucion + $impuesto, 2),
            'automatica', 'borrador', $tipo_descuento
        ]);
        
        if ($result) $agregados++;
    }
    
    echo json_encode(['success' => true, 'agregados' => $agregados, 'errores' => $errores]);
    exit;
}

// ============================================
// DATOS PARA LA VISTA PRINCIPAL
// ============================================
if ($tipo_nomina_activa === 'ajuste') {
    // Para ajuste: existe nómina si hay CUALQUIER registro (borrador o contabilizadas),
    // para que las contabilizadas sigan visibles después de cerrarlas.
    $stmt_existe = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                                  WHERE periodo_desde = ? 
                                  AND periodo_hasta = ? 
                                  AND tipo_nomina = ?");
    $stmt_existe->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    $existe_nomina = $stmt_existe->fetchColumn() > 0;
    
    // 🔽 CORRECCIÓN: Para ajuste, si se filtra por un número de nómina concreto,
    // se trata de una nómina ya contabilizada: se marcan estado y observaciones.
    $contabilizada = false;
    $observaciones_cierre = '';
    if (!empty($filtro_numero_nomina) && $filtro_numero_nomina !== 'Borrador') {
        $stmt_obs_aj = $pdo->prepare("SELECT observaciones FROM cierres_nomina 
                                      WHERE periodo_desde = ? AND periodo_hasta = ? 
                                      AND tipo_nomina = 'ajuste' AND numero_nomina = ? LIMIT 1");
        $stmt_obs_aj->execute([$periodo_desde, $periodo_hasta, $filtro_numero_nomina]);
        $obs_aj = $stmt_obs_aj->fetchColumn();
        if ($obs_aj !== false) {
            $contabilizada = true;
            $observaciones_cierre = $obs_aj;
        }
    }
} else {
    // Lógica original para el resto de tipos
    $existe = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $existe->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    $existe_nomina = $existe->fetchColumn() > 0;

    $contabilizada = false;
    $observaciones_cierre = '';
    
    if ($existe_nomina) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
        $check->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
        $contabilizada = $check->fetchColumn() > 0;
        
        if (!$contabilizada) {
            $stmt = $pdo->prepare("SELECT estado FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
            $stmt->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
            $estado = $stmt->fetchColumn();
            $contabilizada = ($estado == 'contabilizado');
        }

        if ($contabilizada) {
            $stmt_obs = $pdo->prepare("SELECT observaciones FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
            $stmt_obs->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
            $observaciones_cierre = $stmt_obs->fetchColumn() ?: '';
        }
    }
}

$trabajadores = getTrabajadoresActivos($pdo);
foreach ($trabajadores as &$t) {
    $t['dias_acumulados'] = $t['vacaciones_acumuladas'] ?? 0;
    $t['valor_por_dia'] = $t['salario_mensual'] / $dias_laborables;
    $t['valor_acumulado'] = $t['dias_acumulados'] * $t['valor_por_dia'];
}

$ids_con_nomina_vacaciones = [];
if ($tipo_nomina_activa == 'vacaciones') {
    $stmt_ex = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'vacaciones'");
    $stmt_ex->execute([$periodo_desde, $periodo_hasta]);
    $ids_con_nomina_vacaciones = $stmt_ex->fetchAll(PDO::FETCH_COLUMN);
}

$nominas = [];
if ($existe_nomina) {
    // 1. Guardamos la consulta base en una variable de texto (sin el ORDER BY final)
	$sql_nominas = "
		SELECT 
			n.*, 
			t.codigo, 
			t.ci, 
			t.nombre_completo, 
			cp.id as cargo_id,
			cp.nombre_cargo as cargo_nombre,
			CONCAT(cp.id, ' - ', cp.nombre_cargo) as cargo,
			t.no_acumular_vacaciones,
			a.nombre_area,
			a.id as area_id,
			a.codigo as area_codigo,
			e.salario_hora_ordinaria, 
			e.salario_mensual, 
			e.escala_numero,
			COALESCE(t.vacaciones_acumuladas, 0) as dias_acumulados,
			cc.id as centro_costo_id,
			cc.codigo as nombre_centro_costo,
			cc.nombre as cc_nombre,
			t.cuentabanc,
			n.dias_restantes,
			n.vacaciones_acumuladas_mes,
			n.importe_vacaciones_acumulado_mes,
			n.total_vacaciones_acumuladas,
			n.total_importe_vacaciones_acumuladas,
			t.foto_ruta,
			co.codigo as categoria_ocupacional_codigo,
			t.categoria_ocupacional_id,
			co.nombre as categoria_ocupacional_nombre, 
			t.escala_salarial_id,
			t.tipo_contrato
		FROM nominas n 
		JOIN trabajadores t ON n.trabajador_id = t.id 
		JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
		LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id 
		LEFT JOIN areas a ON t.area_id = a.id 
		LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id 
		LEFT JOIN categorias_ocupacionales co ON t.categoria_ocupacional_id = co.id
		WHERE n.periodo_desde = ? 
		AND n.periodo_hasta = ? 
		AND n.tipo_nomina = ?
	";
    
    // Parámetros base que siempre se van a evaluar (el índice de arrays empieza en 0)
	$params_query = [$periodo_desde, $periodo_hasta, $tipo_nomina_activa];

	// Si hay filtro por número, aplicarlo
	if (!empty($filtro_numero_nomina)) {
		if ($filtro_numero_nomina === 'Borrador') {
			$sql_nominas .= " AND n.numero_nomina IS NULL";
		} else {
			$sql_nominas .= " AND n.numero_nomina = ?";
			$params_query[] = $filtro_numero_nomina;
		}
} else {
    // Si NO hay filtro y el tipo es ajuste, mostrar solo borradores
    // ============================================
    // DATOS PARA LA VISTA PRINCIPAL
    // ============================================
    if ($tipo_nomina_activa === 'ajuste') {
        // Para ajuste: existe nómina si hay CUALQUIER registro (borrador o contabilizadas)
        $stmt_existe = $pdo->prepare("SELECT COUNT(*) FROM nominas 
                                      WHERE periodo_desde = ? 
                                      AND periodo_hasta = ? 
                                      AND tipo_nomina = ?");
        $stmt_existe->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
        $existe_nomina = $stmt_existe->fetchColumn() > 0;
        
        // 🔽 CORRECCIÓN: Para ajuste, si se filtra por un número de nómina concreto,
        // se trata de una nómina ya contabilizada: se marcan estado y observaciones.
        $contabilizada = false;
        $observaciones_cierre = '';
        if (!empty($filtro_numero_nomina) && $filtro_numero_nomina !== 'Borrador') {
            $stmt_obs_aj = $pdo->prepare("SELECT observaciones FROM cierres_nomina 
                                          WHERE periodo_desde = ? AND periodo_hasta = ? 
                                          AND tipo_nomina = 'ajuste' AND numero_nomina = ? LIMIT 1");
            $stmt_obs_aj->execute([$periodo_desde, $periodo_hasta, $filtro_numero_nomina]);
            $obs_aj = $stmt_obs_aj->fetchColumn();
            if ($obs_aj !== false) {
                $contabilizada = true;
                $observaciones_cierre = $obs_aj;
            }
        }
    } else {
        // Lógica original para el resto de tipos
        $existe = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
        $existe->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
        $existe_nomina = $existe->fetchColumn() > 0;

        $contabilizada = false;
        $observaciones_cierre = '';
        
        if ($existe_nomina) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
            $check->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
            $contabilizada = $check->fetchColumn() > 0;
            
            if (!$contabilizada) {
                $stmt = $pdo->prepare("SELECT estado FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
                $stmt->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
                $estado = $stmt->fetchColumn();
                $contabilizada = ($estado == 'contabilizado');
            }

            if ($contabilizada) {
                $stmt_obs = $pdo->prepare("SELECT observaciones FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
                $stmt_obs->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
                $observaciones_cierre = $stmt_obs->fetchColumn() ?: '';
            }
        }
    }
	}

	$sql_nominas .= " ORDER BY t.nombre_completo";
    
    // 4. Preparamos y ejecutamos la consulta con sus respectivos parámetros
    $stmt = $pdo->prepare($sql_nominas);
    $stmt->execute($params_query);
    $nominas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Detectar el número de nómina que se está visualizando para actualizar el título de la tarjeta

    if (!empty($nominas)) {
        $primer_registro = reset($nominas);
        if (!empty($primer_registro['numero_nomina'])) {
            $num_nomina_actual = $primer_registro['numero_nomina'];
        }
    }
}

// Para ajuste: detectar si la vista actual contiene al menos una fila en BORRADOR (editable).
// Las filas contabilizadas (con número de nómina) se muestran en modo solo lectura.
$hay_borrador_ajuste = false;
if ($tipo_nomina_activa === 'ajuste' && !empty($nominas)) {
    foreach ($nominas as $n) {
        if (($n['estado'] ?? '') == 'borrador') { $hay_borrador_ajuste = true; break; }
    }
}

// 🔽 NUEVO: Mapa número de nómina -> observaciones de cierres_nomina para AJUSTE.
// Se usa desde JS para mostrar las observaciones de la nómina seleccionada en el filtro.
$observaciones_por_nomina = [];
if ($tipo_nomina_activa === 'ajuste' && $existe_nomina) {
    $stmt_mapa_obs = $pdo->prepare("SELECT numero_nomina, observaciones FROM cierres_nomina 
                                    WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'ajuste'");
    $stmt_mapa_obs->execute([$periodo_desde, $periodo_hasta]);
    foreach ($stmt_mapa_obs->fetchAll() as $fila_obs) {
        if (!empty($fila_obs['numero_nomina'])) {
            $observaciones_por_nomina[$fila_obs['numero_nomina']] = $fila_obs['observaciones'] ?? '';
        }
    }
}

$nombre_mes = nombreMesEspanol($mes);

// Obtener logo para exportaciones
$ruta_logo = '../../images/logotn.png';
$logoBase64 = '';
if (file_exists($ruta_logo)) {
    $type = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}
// ========== NUEVAS VARIABLES PARA NOCTURNIDAD ==========
$total_horas_nocturnas = 0;
$total_importe_nocturno = 0;

// ============================================
// CONSULTAS PARA LOS COMBOS DE FILTRO EN MODALES
// ============================================
$all_areas = $pdo->query("SELECT id, codigo, nombre_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);
$all_centros = $pdo->query("SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Nóminas - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    
	    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
	</title><link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
	
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap5.3.0/buttons.dataTables.min.css">  
	
	<link href="css/nominas.css" rel="stylesheet">


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
            <h1><i class="fas fa-coins me-2" style="color: #60a5fa;"></i>Gestión de Nóminas</h1>
            <p>Control y administración de pagos</p>
        </div>
    </div>
    
    <!-- Contenedor de elementos del lado derecho -->
    <div class="d-flex align-items-center gap-3">
        <!-- Selector de tipo de nómina -->
        <div class="d-flex align-items-center gap-2">
            <div class="tipo-nomina-icon">
                <i class="fas fa-layer-group" style="color: #60a5fa; font-size: 0.85rem;"></i>
            </div>
            <div class="tipo-nomina-selector-custom">
                <div class="tipo-nomina-preview" id="tipoNominaPreview">
                    <i class="fas <?php echo $tipos_nomina[$tipo_nomina_activa]['icono']; ?>"></i>
                    <span><?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="tipo-nomina-dropdown" id="tipoNominaDropdown">
                    <?php foreach ($tipos_nomina as $key => $tipo): ?>
                        <div class="tipo-nomina-option" data-value="<?php echo $key; ?>" data-icon="<?php echo $tipo['icono']; ?>">
                            <i class="fas <?php echo $tipo['icono']; ?>"></i>
                            <span><?php echo $tipo['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Botón Opciones (Dropdown) -->
        <div class="dropdown">
            <button class="btn-win" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: none;">
                <i class="fas fa-cog me-1"></i> Opciones <i class="fas fa-chevron-down ms-2" style="color: rgba(255,255,255,0.6);"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#" id="btnBackupManual">
                        <i class="fas fa-database me-2" style="color: #fbbf24;"></i> 
                        Salva del Sistema Manual
                        <small class="d-block text-muted">Crear copia de seguridad (SQL)</small>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1">Reportes Oficiales</h6></li>
                <li>
                    <a class="dropdown-item" href="#" id="menuNominaImpresa">
                        <i class="fas fa-print text-primary me-2"></i> 
                        Nómina Impresa
                        <small class="d-block text-muted">Modelo SC-4-06 con agrupaciones</small>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" id="menuMontos">
                        <i class="fas fa-history text-warning me-1"></i> 
                        Historial Montos Redistribución
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1">Personalizar Vista</h6></li>
                <li><a class="dropdown-item" href="#" id="menuColumnas"><i class="fas fa-columns me-2" style="color: #60a5fa;"></i> Mostrar/Ocultar Columnas</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-light px-3 py-1">Exportar y Reportes</h6></li>
                <li><a class="dropdown-item" href="#" id="menuExportPrint"><i class="fas fa-print text-warning me-2"></i> Imprimir Reporte</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportPDF"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportWord"><i class="fas fa-file-word text-primary me-2"></i> Exportar a Word</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportExcel"><i class="fas fa-file-excel text-success me-2"></i> Exportar a Excel</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportCSV"><i class="fas fa-file-csv text-info me-2"></i> Exportar a CSV</a></li>
                <li><a class="dropdown-item" href="#" id="menuExportTXT"><i class="fas fa-file-alt text-secondary me-2"></i> Exportar a TXT</a></li>
            </ul>
        </div>
        
        <!-- Botón Exportar al Banco (estilo original) -->
        <button id="btnExportarBanco" class="btn-exportar-banco" data-tooltip="Exportar al Banco">
            <i class="fas fa-university"></i>
        </button>
        
        <!-- Menú de usuario unificado (sin reloj) -->
        <?php include '../includes/user_menu.php'; 
        ?>
    </div>
</div>
    <!-- Alertas Generales -->
    <?php if (isset($_GET['msg']) || isset($_GET['error']) || isset($_GET['duplicados'])): ?>
    <div class="mb-4 fade-in-up">
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'generated'): ?>
            <div class="alert alert-success bg-success bg-opacity-25 border-success text-white"><i class="fas fa-check-circle me-2"></i> Nómina generada correctamente.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'extraordinaria_added'): ?>
            <div class="alert alert-success bg-success bg-opacity-25 border-success text-white"><i class="fas fa-check-circle me-2"></i> Se agregaron <?php echo intval($_GET['count']); ?> trabajadores a la extraordinaria.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'vacaciones_added'): ?>
            <div class="alert alert-success bg-success bg-opacity-25 border-success text-white"><i class="fas fa-check-circle me-2"></i> Se agregaron <?php echo intval($_GET['count']); ?> trabajadores a vacaciones.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'bono_added'): ?>
            <div class="alert alert-success bg-success bg-opacity-25 border-success text-white"><i class="fas fa-check-circle me-2"></i> Se agregaron <?php echo intval($_GET['count']); ?> bonos.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'contabilized'): ?>
            <div class="alert alert-info bg-info bg-opacity-25 border-info text-white"><i class="fas fa-lock me-2"></i> Nómina contabilizada. Ya no se puede modificar.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'reverted'): ?>
            <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white"><i class="fas fa-undo-alt me-2"></i> Nómina <?php echo htmlspecialchars($_GET['code'] ?? ''); ?> revertida a estado Borrador. Ya puede modificarse.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white"><i class="fas fa-trash me-2"></i> Nómina eliminada correctamente.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
		 <?php elseif ($_GET['msg'] == 'ajuste_procesado'): ?>
				<div class="alert alert-success bg-success bg-opacity-25 border-success text-white">
					<i class="fas fa-check-circle me-2"></i> 
					Ajuste procesado: <?php echo intval($_GET['nuevos'] ?? 0); ?> nuevos, 
					<?php echo intval($_GET['actualizados'] ?? 0); ?> acumulados 
					(total <?php echo intval($_GET['count'] ?? 0); ?> registros afectados).
					<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button>
				</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <?php if ($_GET['error'] == 'ya_existentes'): ?>
            <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white"><i class="fas fa-exclamation-triangle me-2"></i> Algunos ya tienen nómina de vacaciones.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'no_validos'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> No se pudieron agregar trabajadores. Verifique días.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'no_bonos'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> No se pudo generar bono. Verifique montos.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'already_closed'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> La nómina ya está contabilizada.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'revert_no_numero'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> No se especificó el número de nómina a revertir.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'revert_no_cierre'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> No se encontró el cierre de esa nómina. Verifique el número.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'revert_no_nomina'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> No hay filas contabilizadas con ese número para revertir.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'already_exists'): ?>
            <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white"><i class="fas fa-exclamation-triangle me-2"></i> Ya existe una nómina para este período. Use "Regenerar".<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'no_trabajadores'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> Seleccione al menos un trabajador con horas válidas.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'worker_in_automatica'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> El trabajador ya tiene nómina automática.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php elseif ($_GET['error'] == 'todos_duplicados'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> Todos los seleccionados ya tienen nómina automática.<button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['duplicados'])): 
            $duplicados_ids = explode(',', $_GET['duplicados']);
            $nombres_duplicados = [];
            foreach ($duplicados_ids as $did) {
                $stmt_nombre = $pdo->prepare("SELECT nombre_completo FROM trabajadores WHERE id = ?");
                $stmt_nombre->execute([$did]);
                $nombres_duplicados[] = $stmt_nombre->fetchColumn();
            }
        ?>
        <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Omitidos (ya tienen nómina automática): <strong><?php echo htmlspecialchars(implode(', ', $nombres_duplicados)); ?></strong>
            <button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<!-- Selector de Años/Meses para consulta rápida -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.05s;">
    <div class="row align-items-end">
        <!-- Título -->
        <div class="row g-2 align-items-end p-3" style="background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-chart-line" style="font-size: 1.3rem; color: #60a5fa;"></i>
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Consultar de Forma Rápida</h2>
                        <p class="text-muted" style="font-size: 0.85rem; margin: 0; color: rgba(255,255,255,0.4) !important;">
                            Filtrar por período
                        </p>
                    </div>
                </div>
<!-- Localiza este bloque en tu código -->
<div class="d-flex gap-2">
    
    <!-- NUEVO: Botón condicionado por PHP según la pestaña activa -->
    <?php if ($tipo_nomina_activa == 'bono'): ?>
        <button class="btn-win btn-win-sm" id="btnFullHistorialBonos" title="Ver historial de montos definidos" style="padding: 6px 12px; background: rgba(147, 51, 234, 0.2); border: 1px solid #a855f7;">
            <i class="fas fa-history me-1"></i> Historial Montos
        </button>
    <?php endif; ?>

    <!-- Botón Actualizar Página (Ya existente) -->
    <button class="btn-win btn-win-sm" id="btnActualizarPagina" title="Actualizar página actual limpiando filtros" style="padding: 6px 12px; background: rgba(59,130,246,0.2); border: 1px solid #3b82f6;">
        <i class="fas fa-sync-alt me-1"></i> Eliminar Filtros y Actualizar
    </button>
    
    <!-- Botón Regresar a Inicio (Ya existente) -->
    <button class="btn-win btn-win-sm" id="btnRegresarInicio" title="Regresar a la página principal de nóminas" style="padding: 6px 12px; background: rgba(16,185,129,0.2); border: 1px solid #10b981;">
        <i class="fas fa-home me-1"></i> Inicio
    </button>
</div>
            </div>
        </div>
        
        <!-- 1. Año -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-calendar-alt me-1"></i> Seleccionar Año
            </label>
            <select class="form-select form-select-sm" id="consultaAnioSelect">
                <option value="">-- Años --</option>
                <?php 
                $stmt_anios_consulta = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) as anio FROM nominas ORDER BY anio DESC");
                $anios_consulta = $stmt_anios_consulta->fetchAll(PDO::FETCH_COLUMN);
                if (empty($anios_consulta)) {
                    for ($y = date('Y')+10; $y >= 2023; $y--) $anios_consulta[] = $y;
                }
                foreach ($anios_consulta as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $anio ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- 2. Tipo de Nómina -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-tag me-1"></i> Tipo de Nómina
            </label>
            <select class="form-select form-select-sm" id="consultaTipoSelect">
                <option value="">-- Todos los tipos --</option>
                <option value="automatica">📊 Automática</option>
                <option value="extraordinaria">⏰ Extraordinaria</option>
                <option value="vacaciones">🏖️ Vacaciones</option>
                <option value="bono">🎁 Bono</option>
				<option value="ajuste">✏️ Ajuste</option>
            </select>
        </div>
        
        <!-- 3. Estado de Nómina -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-circle-info me-1"></i> Estado
            </label>
            <select class="form-select form-select-sm" id="consultaEstadoSelect">
                <option value="">-- Todos los estados --</option>
                <option value="borrador">📝 Borrador</option>
                <option value="contabilizado">🔒 Contabilizado</option>
            </select>
        </div>
        
        <!-- 4. Mes -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-moon me-1"></i> Seleccionar Mes
            </label>
			<select class="form-select form-select-sm" id="consultaMesSelect">
				<option value="">-- Mes --</option>
				<?php for ($m = 1; $m <= 12; $m++): 
					$m_pad = str_pad($m,2,'0',STR_PAD_LEFT);
					$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE YEAR(periodo_desde) = ? AND MONTH(periodo_desde) = ?");
					$stmt_check->execute([$anio, $m]);
					$tiene_nomina = $stmt_check->fetchColumn() > 0;
				?>
					<option value="<?php echo $m_pad; ?>" <?php echo ($m == $mes && $tiene_nomina) ? 'selected' : ''; ?> <?php echo !$tiene_nomina ? 'disabled class="text-muted"' : ''; ?>>
						<?php echo nombreMesEspanol($m_pad); ?>
						<?php echo !$tiene_nomina ? ' (sin nómina)' : ''; ?>
					</option>
				<?php endfor; ?> <!-- Se cambia a 'endfor' -->
			</select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-hashtag me-1"></i> Nº de Nómina
            </label>
            <select class="form-select form-select-sm" id="consultaNumeroSelect" disabled>
                <option value="">-- Seleccione mes --</option>
            </select>
        </div>
        
        <!-- 5. Botón Ir al período -->
<!-- Modificación en el col-md-2 de la Acción del card Consultar de Forma Rápida -->
<div class="col-md-2">
    <label class="form-label mb-1" style="font-size: 0.7rem; font-weight: 600; color: #60a5fa;">
        <i class="fas fa-play me-1"></i> Acción
    </label>
    <button class="btn-fluent w-100" id="consultaRapidaBtn" style="margin-top: 0;">
        <i class="fas fa-calendar-check"></i>
        <span>Ir al período</span>
    </button>
    
    <!-- NUEVO: Botón de Historial (se mostrará solo si tipo = bono) -->
    <button class="btn-win btn-win-info w-100 mt-2" id="btnFullHistorialBonos" style="display: none; padding: 6px; font-size: 0.75rem;">
        <i class="fas fa-history me-1"></i> Ver Historial Montos
    </button>
</div>
    </div>
    <div class="row mt-2">
        <div class="col-12">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i> 
                <span id="infoNominasPeriodo">Seleccione año, tipo, estado, mes para filtrar nóminas</span>
            </small>
        </div>
    </div>
</div>

<!-- CARD INFORMATIVO DE LO QUE VIENE A CONTINUACIÓN - UNA SOLA LÍNEA -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.075s; background: rgba(96, 165, 250, 0.08); border-left: 4px solid #60a5fa; padding: 12px 20px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <div style="width: 32px; height: 32px; background: rgba(96, 165, 250, 0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-down" style="font-size: 1rem; color: #60a5fa; animation: bounce 1.5s infinite;"></i>
                </div>
                <h5 style="font-size: 0.8rem; font-weight: 600; margin: 0; color: #60a5fa;">
                    <i class="fas fa-chart-simple me-1"></i> A continuación:
                </h5>
            </div>
            <div class="d-flex flex-wrap gap-3" style="font-size: 0.7rem; color: rgba(255,255,255,0.7);">
				<span><i class="text-success fas fa-money-bill-wave me-1"></i> Seleccionar Tipo de Nómina</span>
                <span><i class="fas fa-calendar-alt me-1" style="color: #60a5fa;"></i> Seleccionar período</span>
                <span><i class="fas fa-plus-circle me-1" style="color: #10b981;"></i> Consultar/Generar/Regenerar</span>
                <span><i class="fas fa-edit me-1" style="color: #f59e0b;"></i> Editar cálculos</span>
                <span><i class="fas fa-chart-line me-1" style="color: #a78bfa;"></i> Estadísticas</span>
                <span><i class="fas fa-file-export me-1" style="color: #3b82f6;"></i> Exportar</span>
                <span><i class="fas fa-print me-1" style="color: #3b82f6;"></i> Imprimir</span>
            </div>
        </div>
        <div class="d-none d-md-block">
            <i class="fas fa-hand-point-right" style="font-size: 1.2rem; color: #60a5fa; opacity: 0.6;"></i>
        </div>
    </div>
</div>

<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(5px); }
}
/* ========================================== */
/* ANIMACIONES HOVER PARA BOTONES - COLOR NOTORIO */
/* ========================================== */

/* Efecto hover para botones tipo glass */
.btn-win, .btn-win-primary, .btn-win-success, .btn-win-info, .btn-win-warning, .btn-win-danger,
.btn-fluent, .btn-nomina-impresa, #btnActualizarPagina, #btnRegresarInicio {
    transition: all 0.2s ease;
}

.btn-win:hover, .btn-win-primary:hover, .btn-win-success:hover, 
.btn-win-info:hover, .btn-win-warning:hover, .btn-win-danger:hover,
.btn-fluent:hover, .btn-nomina-impresa:hover,
#btnActualizarPagina:hover, #btnRegresarInicio:hover {
    filter: brightness(1.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.btn-win:active, .btn-win-primary:active, .btn-win-success:active,
.btn-win-info:active, .btn-win-warning:active, .btn-win-danger:active,
.btn-fluent:active, .btn-nomina-impresa:active,
#btnActualizarPagina:active, #btnRegresarInicio:active {
    filter: brightness(0.9);
}

/* Hover para botones de iconos */
.btn-icon {
    transition: all 0.15s ease;
}

.btn-icon:hover {
    filter: brightness(1.3);
}

.btn-icon:active {
    filter: brightness(0.85);
}

/* Hover para tarjetas de opciones de impresión */
.print-option-card {
    transition: all 0.2s ease;
    cursor: pointer;
}

.print-option-card:hover {
    border-color: #60a5fa !important;
    background: rgba(96, 165, 250, 0.2) !important;
    box-shadow: 0 4px 12px rgba(96, 165, 250, 0.2);
}

/* Hover para dropdown items */
.dropdown-item {
    transition: all 0.15s ease;
}

.dropdown-item:hover {
    background: rgba(96, 165, 250, 0.25) !important;
    color: #60a5fa !important;
    border-left: 3px solid #60a5fa;
}

/* Hover para botones del modal de edición */
#btnModalPrimero, #btnModalAnterior, #btnModalSiguiente, #btnModalUltimo,
#btnModalActualizar, #btnModalReset {
    transition: all 0.15s ease;
}

#btnModalPrimero:hover, #btnModalAnterior:hover, 
#btnModalSiguiente:hover, #btnModalUltimo:hover,
#btnModalActualizar:hover, #btnModalReset:hover {
    filter: brightness(1.12);
    background: rgba(96, 165, 250, 0.15) !important;
}

/* Hover para pestañas */
.nav-tabs-modern .nav-link {
    transition: all 0.2s ease;
}

.nav-tabs-modern .nav-link:hover {
    background: rgba(96, 165, 250, 0.15);
    color: #60a5fa !important;
}

/* Hover para filas de la tabla */
.table-custom tbody tr {
    transition: all 0.1s ease;
}

.table-custom tbody tr:hover {
    background: rgba(96, 165, 250, 0.12);
}

/* Hover para worker items en modales */
.worker-item {
    transition: all 0.15s ease;
}

.worker-item:hover:not(.disabled) {
    background: rgba(96, 165, 250, 0.15) !important;
    border-left: 3px solid #60a5fa !important;
}

/* Hover para rangos de días */
.rango-btn {
    transition: all 0.15s ease;
}

.rango-btn:hover {
    background: rgba(96, 165, 250, 0.25) !important;
    border-color: #60a5fa !important;
    color: #60a5fa !important;
}
/* ========================================== */
/* ESTILO PARA SELECTOR DE TIPO DE NÓMINA */
/* ========================================== */

.tipo-nomina-selector {
    position: relative;
    width: 200px;
}

.tipo-nomina-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(30, 30, 38, 0.95), rgba(20, 20, 25, 0.95));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(96, 165, 250, 0.3);
    border-radius: 12px;
    padding: 6px 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    font-weight: 500;
    color: #e0e0e0;
}

.tipo-nomina-preview:hover {
    border-color: #60a5fa;
    background: linear-gradient(135deg, rgba(40, 40, 48, 0.95), rgba(30, 30, 38, 0.95));
    box-shadow: 0 2px 8px rgba(96, 165, 250, 0.15);
}

.tipo-nomina-preview i:first-child {
    color: #60a5fa;
    font-size: 0.9rem;
    width: 18px;
    text-align: center;
}

.tipo-nomina-preview span {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tipo-nomina-preview i:last-child {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.7rem;
    transition: transform 0.2s ease;
}

.tipo-nomina-selector.open .tipo-nomina-preview i:last-child {
    transform: rotate(180deg);
}

.tipo-nomina-select {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 10;
}

/* Estilo para las opciones del select nativo (cuando se despliega) */
.tipo-nomina-select option {
    background: #1e1e26;
    color: #e0e0e0;
    padding: 10px;
}

/* Alternativa: Estilo personalizado con dropdown personalizado (opcional) */
.tipo-nomina-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 8px;
    background: rgba(30, 30, 38, 0.98);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: 12px;
    padding: 6px 0;
    z-index: 100;
    display: none;
    animation: fadeInDown 0.2s ease;
}

.tipo-nomina-selector.open .tipo-nomina-dropdown {
    display: block;
}

.tipo-nomina-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.8rem;
    color: #e0e0e0;
}

.tipo-nomina-option:hover {
    background: rgba(96, 165, 250, 0.15);
    color: #60a5fa;
}

.tipo-nomina-option i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
}

.tipo-nomina-option.selected {
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
    border-left: 3px solid #60a5fa;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
/* ========================================== */
/* SELECTOR DE TIPO DE NÓMINA CON ICONOS */
/* ========================================== */

.tipo-nomina-selector-custom {
    position: relative;
    width: 210px;
}

.tipo-nomina-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, rgba(30, 30, 38, 0.95), rgba(20, 20, 25, 0.95));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(96, 165, 250, 0.3);
    border-radius: 12px;
    padding: 8px 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    font-weight: 500;
    color: #e0e0e0;
}

.tipo-nomina-preview:hover {
    border-color: #60a5fa;
    background: linear-gradient(135deg, rgba(40, 40, 48, 0.95), rgba(30, 30, 38, 0.95));
    box-shadow: 0 2px 10px rgba(96, 165, 250, 0.2);
}

.tipo-nomina-preview i:first-child {
    color: #60a5fa;
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.tipo-nomina-preview span {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tipo-nomina-preview i:last-child {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.7rem;
    transition: transform 0.25s ease;
}

.tipo-nomina-selector-custom.open .tipo-nomina-preview i:last-child {
    transform: rotate(180deg);
}

/* Dropdown personalizado */
.tipo-nomina-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: rgba(30, 30, 38, 0.98);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: 12px;
    padding: 6px 0;
    z-index: 1000;
    display: none;
    animation: fadeInDown 0.2s ease;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.tipo-nomina-selector-custom.open .tipo-nomina-dropdown {
    display: block;
}

/* Opciones del dropdown */
.tipo-nomina-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.8rem;
    color: #e0e0e0;
}

.tipo-nomina-option:hover {
    background: rgba(96, 165, 250, 0.15);
    color: #60a5fa;
}

.tipo-nomina-option i {
    width: 20px;
    text-align: center;
    font-size: 0.95rem;
    color: #60a5fa;
}

.tipo-nomina-option.selected {
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
    border-left: 3px solid #60a5fa;
}

.tipo-nomina-option.selected i {
    color: #60a5fa;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
/* Estilos para el buscador en el modal */
.buscador-trabajador-modal {
    position: relative;
}

.buscador-trabajador-modal .input-group {
    position: relative;
    z-index: 1051;
}

#resultadosBusquedaModal {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1052;
    margin-top: 4px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}

.resultado-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: all 0.15s ease;
}

.resultado-item:hover {
    background: rgba(96, 165, 250, 0.15);
}

.resultado-item.active {
    background: rgba(96, 165, 250, 0.25);
}

.resultado-nombre {
    font-weight: 500;
    color: white;
    font-size: 0.85rem;
}

.resultado-detalle {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
}

.resultado-detalle i {
    margin-right: 4px;
    width: 14px;
}
</style>
	
	<!-- Pestañas Tipo de Nómina -->
    <ul class="nav-tabs-modern fade-in-up">
        <?php foreach ($tipos_nomina as $key => $tipo): ?>
        <li><a class="nav-link <?php echo $tipo_nomina_activa == $key ? 'active' : ''; ?>" 
       href="?periodo=<?php echo $periodo; ?>&tipo=<?php echo $key; ?><?php echo $filtro_cuenta ? '&filtro_cuenta='.$filtro_cuenta : ''; ?>">
        <i class="fas <?php echo $tipo['icono']; ?> me-2"></i><?php echo $tipo['nombre']; ?>
        </a></li>
        <?php endforeach; ?>
    </ul>

<!-- Controles Superiores (Año, Mes, Botones de acción general) - Estilo Win11 -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.1s;">
<div class="row align-items-center g-3 fade-in-up" style="animation-delay: 0.05s;">
    <!-- Título -->
    <div class="col-md-auto">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-chart-line" style="font-size: 1.3rem; color: #60a5fa;"></i>
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Período de Nómina</h2>
                <p class="text-muted" style="font-size: 0.85rem; margin: 0; color: rgba(255,255,255,0.4) !important;">Seleccione Año/Mes. Consulte antes de crear</p>
            </div>
        </div>
    </div>
    
    <!-- Separador visual -->
    <div class="col-md-auto">
        <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1);"></div>
    </div>
    
    <!-- Selector Año -->
    <div class="col-md-2">
        <label class="form-label fw-semibold mb-1" style="font-size: 0.7rem;">
            <i class="fas fa-calendar-alt me-1" style="color: #60a5fa;"></i> Año
        </label>
        <select class="form-select form-select-custom" id="anioSelect" style="padding: 6px 12px; font-size: 0.85rem;">
            <?php for ($y = 2023; $y <= date('Y')+10; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $anio ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    
    <!-- Selector Mes -->
    <div class="col-md-2">
        <label class="form-label fw-semibold mb-1" style="font-size: 0.7rem;">
            <i class="fas fa-moon me-1" style="color: #60a5fa;"></i> Mes
        </label>
        <select class="form-select form-select-custom" id="mesSelect" style="padding: 6px 12px; font-size: 0.85rem;">
            <?php for ($m = 1; $m <= 12; $m++): $m_pad = str_pad($m,2,'0',STR_PAD_LEFT); ?>
                <option value="<?php echo $m_pad; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>><?php echo nombreMesEspanol($m_pad); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    
    <!-- Botón Consultar -->
    <div class="col-md-auto">
        <button class="btn-win-primary" id="consultarBtn" style="margin-top: 20px; padding: 6px 18px;">
            <i class="fas fa-search me-1"></i> Consultar
        </button>
    </div>
    
<!-- Botones de Acción (Dinámicos) -->
<div class="col-md">
    <?php 
    // Para ajuste, siempre mostrar el botón de nueva nómina, independientemente de si existen registros.
    $mostrarBotonCreacion = ($tipo_nomina_activa == 'ajuste') ? true : (!$existe_nomina);
    ?>
    <?php if ($mostrarBotonCreacion): ?>
        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <button type="button" class="btn-win-primary" id="btnGenerarAutomaticaModal" style="margin-top: 20px; padding: 6px 18px;">
                <i class="fas fa-play me-1"></i> Generar Nómina Automática
            </button>
            <form method="POST" id="formGenerarAutomatica" style="display: none;">
                <input type="hidden" name="generar_nomina_automatica" value="1">
                <input type="hidden" name="tipo_descuento" id="tipoDiscountHidden" value="">
            </form>
        <?php elseif ($tipo_nomina_activa == 'extraordinaria'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="extraordinaria" style="margin-top: 20px; padding: 6px 18px;">
                <i class="fas fa-clock me-1"></i> Nueva Nómina Ext.
            </button>
        <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="vacaciones" style="margin-top: 20px; padding: 6px 18px;">
                <i class="fas fa-umbrella-beach me-1"></i> Nueva Nómina Vac.
            </button>
        <?php elseif ($tipo_nomina_activa == 'bono'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="bono" style="margin-top: 20px; padding: 6px 18px;">
                <i class="fas fa-gift me-1"></i> Generar Bonos y Otros Pagos
            </button>
        <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="ajuste" style="margin-top: 20px; padding: 6px 18px;">
                <i class="fas fa-pen me-1"></i> Nueva Nómina de Ajuste
            </button>
        <?php else: ?>
            <button class="btn-win w-100" disabled style="margin-top: 20px;">
                <i class="fas fa-ban me-1"></i> Seleccione tipo
            </button>
        <?php endif; ?>
    <?php else: ?>
        <!-- Ya existe nómina y NO es ajuste, mostramos botones de regenerar, agregar, eliminar -->
        <div class="d-flex gap-2" style="margin-top: 20px;">
            <?php if ($tipo_nomina_activa == 'automatica' && !$contabilizada): ?>
                <form method="POST" id="formRegenerarNomina" style="flex:1">
                    <input type="hidden" name="tipo_nomina" value="<?php echo $tipo_nomina_activa; ?>">
                    <input type="hidden" name="regenerar_nomina" value="1">
                    <button type="button" id="btnRegenerarNomina" class="btn-win-info w-100" style="padding: 6px 12px;">
                        <i class="fas fa-sync-alt me-1"></i> Regenerar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($tipo_nomina_activa == 'vacaciones' && !$contabilizada): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalVacaciones" style="padding: 6px 12px;">
                    <i class="fas fa-plus-circle me-1"></i> Add Vacaciones
                </button>
            <?php endif; ?>
            <?php if ($tipo_nomina_activa == 'bono' && !$contabilizada): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalBono" style="padding: 6px 12px;">
                    <i class="fas fa-plus-circle me-1"></i> Add Bono
                </button>
            <?php endif; ?>
            <?php if ($tipo_nomina_activa == 'extraordinaria' && !$contabilizada): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalExtraordinaria" style="padding: 6px 12px;">
                    <i class="fas fa-plus-circle me-1"></i> Add Trab.
                </button>
            <?php endif; ?>
            <!-- Botón Eliminar Todo (solo para tipos que NO sean ajuste) -->
            <?php //if ($tipo_nomina_activa != 'ajuste'): ?>
                <button type="button" 
                        class="<?php echo $contabilizada ? 'btn-win' : 'btn-win-danger'; ?>" 
                        id="eliminarTodoBtn" 
                        <?php echo $contabilizada ? 'disabled style="padding: 6px 12px; opacity: 0.5; cursor: not-allowed;"' : 'style="padding: 6px 12px;"'; ?>>
                    <i class="fas fa-trash-alt me-1"></i> Eliminar Todo
                </button>
            <?php //endif; ?>
        </div>
    <?php endif; ?>
</div>

</div>

</div>



    <!-- ERRORES DE VACACIONES -->
    <?php if (isset($_SESSION['error_vacaciones']) && !empty($_SESSION['error_vacaciones'])): ?>
    <div class="mb-4 fade-in-up">
        <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error al contabilizar vacaciones:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($_SESSION['error_vacaciones'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    </div>
    <?php unset($_SESSION['error_vacaciones']); ?>
    <?php endif; ?>

    <!-- Estado si no hay nómina -->
    <?php if (!$existe_nomina): ?>
        <div class="glass-card text-center p-5 fade-in-up" style="animation-delay: 0.2s;">
            <i class="fas fa-inbox fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
            <h4 class="text-white mb-2">No hay nómina generada</h4>
            <p style="color: rgba(255,255,255,0.5);">No existe nómina de tipo <strong><?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?></strong> para <?php echo $nombre_mes . ' ' . $anio; ?></p>
        </div>
    <?php else: ?>
        <!-- Tarjetas Estadísticas Grid -->
        <?php 
        $total_devengado = 0; $total_contribucion = 0; $total_neto = 0; $total_vacaciones_dias = 0;
        $totales_impuestos = array_fill(0, count($rangos_impuesto), 0);
        foreach ($nominas as $n) {
            $total_devengado += $n['total_salario_devengado'];
            $total_contribucion += $n['contribucion_especial'];
            $tipo_descuento_n = $n['tipo_descuento'] ?? 'total_rangos';
            if ($tipo_descuento_n == 'total_rangos') {
                $impuestos_rango = calcularImpuestosPorRango($n['total_salario_devengado'], $rangos_impuesto);
                foreach ($impuestos_rango as $idx => $imp) {
                    $totales_impuestos[$idx] = ($totales_impuestos[$idx] ?? 0) + $imp['impuesto'];
                }
            }
            $total_neto += $n['importe_neto'];
        }
        ?>
        
        <div class="stats-grid fade-in-up" style="animation-delay: 0.2s;">
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-users me-1"></i> Trabajadores</h6>
                    <h3 id="statTrabajadores"><?php echo count($nominas); ?></h3>
                </div>
                <div class="stat-icon" style="color: #60a5fa;"><i class="fas fa-users"></i></div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-dollar-sign me-1"></i> Total Devengado</h6>
                    <h3 id="totalDevengado">$0.00</h3>
                </div>
                <div class="stat-icon" style="color: #4ade80;"><i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="glass-card stat-card">
                <div>
                    <h6><i class="fas fa-minus-circle me-1"></i> Deducciones Totales</h6>
                    <h3 id="totalDescuentos">$0.00</h3>
                </div>
                <div class="stat-icon" style="color: #f87171;"><i class="fas fa-arrow-down"></i></div>
            </div>
            <div class="glass-card stat-card" style="border-color: rgba(96, 165, 250, 0.4);">
                <div>
                    <h6 style="color: #60a5fa;"><i class="fas fa-wallet me-1"></i> Total a Pagar (Neto)</h6>
                    <h3 id="totalNeto" style="color: #60a5fa;">$0.00</h3>
                </div>
                <div class="stat-icon" style="background: rgba(96, 165, 250, 0.2); color: #60a5fa;"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>

<!-- Filtros DataTables -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.3s;">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-user me-1"></i> Filtrar Datos de la Nómina</label>
            <select id="filtroTrabajador" class="form-select">
                <option value="">-- Todos --</option>
                <?php foreach ($trabajadores as $trab): ?>
                    <option value="<?php echo $trab['id']; ?>"><?php echo htmlspecialchars($trab['codigo'] . ' - ' . $trab['nombre_completo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-building me-1"></i> Área</label>
            <select id="filtroArea" class="form-select">
                <option value="">-- Todas --</option>
                <?php 
                $areas = $pdo->query("SELECT id, nombre_area FROM areas ORDER BY nombre_area")->fetchAll();
                foreach ($areas as $area): ?>
                    <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-chart-pie me-1"></i> Centro Costo</label>
            <select id="filtroCentroCosto" class="form-select">
                <option value="">-- Todos --</option>
                <?php 
                $centros = $pdo->query("SELECT id, codigo, nombre FROM centros_costo ORDER BY codigo")->fetchAll();
                foreach ($centros as $cc): ?>
                    <option value="<?php echo $cc['id']; ?>"><?php echo htmlspecialchars($cc['codigo'] . ' - ' . $cc['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-credit-card me-1"></i> Cuenta Bancaria</label>
            <select id="filtroCuenta" class="form-select">
                <option value="">-- Todos --</option>
                <option value="si" <?php echo $filtro_cuenta == 'si' ? 'selected' : ''; ?>>Con cuenta bancaria</option>
                <option value="no" <?php echo $filtro_cuenta == 'no' ? 'selected' : ''; ?>>Sin cuenta bancaria</option>
            </select>
        </div>
    </div>
    
    <div class="row g-3 align-items-end mt-3">
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-hashtag me-1"></i> No. Nómina</label>
            <select id="filtroNumeroNomina" class="form-select">
                <option value="">-- Todos --</option>
                <?php
                $stmt_num_filtro = $pdo->prepare("SELECT DISTINCT COALESCE(numero_nomina, 'Borrador') as numero_nomina FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? ORDER BY numero_nomina ASC");
                $stmt_num_filtro->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
                foreach ($stmt_num_filtro->fetchAll(PDO::FETCH_COLUMN) as $num_op_filtro): ?>
                    <option value="<?php echo htmlspecialchars($num_op_filtro); ?>" <?php echo ($num_op_filtro === $filtro_numero_nomina) ? 'selected' : ''; ?>><?php echo htmlspecialchars($num_op_filtro); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Acumulación Vacaciones</label>
            <select id="filtroAcumulaVacaciones" class="form-select">
                <option value="">-- Todos --</option>
                <option value="si">Acumulan vacaciones (9.09%)</option>
                <option value="no">No acumulan vacaciones</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-chart-line me-1"></i> Días Acumulados</label>
            <select id="filtroRangoVacaciones" class="form-select">
                <option value="">-- Todos los rangos --</option>
                <option value="0-5">0 - 5 días</option>
                <option value="5-10">5 - 10 días</option>
                <option value="10-15">10 - 15 días</option>
                <option value="15-20">15 - 20 días</option>
                <option value="20-100">20+ días</option>
            </select>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12 text-end">
            <button class="btn-win btn-win-sm" id="btnLimpiarFiltros"><i class="fas fa-eraser me-1"></i> Limpiar todos</button>
        </div>
    </div>
</div>

        <!-- TABLA PRINCIPAL DE NÓMINA -->
        <div class="glass-card fade-in-up" style="animation-delay: 0.4s;">
		<!-- Título de la Tarjeta del DataTable (Card Title) -->
		<div class="card-header-custom mb-3 pb-2" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
			<div class="d-flex justify-content-between align-items-center">
				<h5 class="text-white mb-0" style="font-weight: 600; font-size: 0.95rem;">
					<i class="fas fa-file-invoice-dollar me-2" style="color: #60a5fa;"></i>
					Detalle de Nómina: <span class="text-info"><?php echo htmlspecialchars($tipos_nomina[$tipo_nomina_activa]['nombre']); ?></span> 
					<span id="tituloCardNumeroNomina"><?php echo ($num_nomina_actual !== 'Borrador') ? 'No.: <span class="text-warning">' . htmlspecialchars($num_nomina_actual) .'</span>': '<span class="text-info">(Borrador)</span>'; ?></span>
					<span class="mx-2" style="color: rgba(255,255,255,0.3);">|</span> 
					Período: <span class="text-success"><?php echo htmlspecialchars($nombre_mes . ' ' . $anio); ?></span>
				</h5>
			</div>
			<div class="alert alert-info mt-2 mb-0" id="alertaObservacionesCierre" style="<?php echo ($contabilizada && !empty($observaciones_cierre)) ? '' : 'display:none;'; ?> background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); font-size: 0.85rem; color: #93c5fd; padding: 8px 12px; border-radius: 8px;">
				<i class="fas fa-comment-alt me-2" style="color: #60a5fa;"></i>
				<strong>Observaciones de Cierre:</strong> <span id="textoObservacionesCierre"><?php echo htmlspecialchars($observaciones_cierre); ?></span>
			</div>
		</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detalle de Nómina</h5>
<div class="d-flex gap-2">
    <?php if ($tipo_nomina_activa == 'ajuste'): ?>
        <!-- 🔽 AJUSTE: botones y badge siempre en el DOM; el JS los alterna según la nómina filtrada -->
        <div id="accionesBorradorAjuste" style="display: flex; gap: 0.5rem; <?php echo (!$contabilizada && $hay_borrador_ajuste) ? '' : 'display: none;'; ?>">
            <button class="btn-win-success" id="btnGuardarTodo" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-save me-1"></i> Guardar Todo
            </button>
            <button type="button" class="btn-win-info" id="btnAgregarPersonasAjusteDirecto">
                <i class="fas fa-user-plus me-1"></i> Agregar Personas
            </button>
            <button class="btn-win-warning" id="contabilizarBtn">
                <i class="fas fa-lock me-1"></i> Contabilizar
            </button>
            <button type="button" class="btn-win-danger" id="btnEliminarTodoAjuste" style="display: none;">
                <i class="fas fa-trash-alt me-1"></i> Eliminar Todo
            </button>
        </div>
        <span id="badgeContabilizadaAjuste" class="badge-contabilizado" style="<?php echo (!$contabilizada && $hay_borrador_ajuste) ? 'display:none;' : ''; ?>">
            <i class="fas fa-check-circle me-1"></i> Contabilizada
        </span>
        <span id="accionesRevertirAjuste" style="display: none; gap: 0.5rem;">
            <button type="button" class="btn-win-danger btn-revertir-nomina" id="revertirBtnAjuste" style="padding: 6px 12px;">
                <i class="fas fa-undo-alt me-1"></i> Revertir
            </button>
        </span>
    <?php elseif (!$contabilizada): ?>
        <button class="btn-win-success" id="btnGuardarTodo" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-save me-1"></i> Guardar Todo
        </button>

        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresAuto" data-bs-toggle="modal" data-bs-target="#modalAgregarTrabajadoresAuto">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresVac" data-bs-toggle="modal" data-bs-target="#modalVacaciones">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'extraordinaria'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresExtra" data-bs-toggle="modal" data-bs-target="#modalExtraordinaria">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'bono'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresBono" data-bs-toggle="modal" data-bs-target="#modalBono">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php endif; ?>

        <button class="btn-win-warning" id="contabilizarBtn">
            <i class="fas fa-lock me-1"></i> Contabilizar
        </button>
    <?php else: ?>
        <span class="badge-contabilizado">
            <i class="fas fa-check-circle me-1"></i> Contabilizada
        </span>
        <?php if (!empty($num_nomina_actual) && $num_nomina_actual !== 'Borrador'): ?>
            <button type="button" class="btn-win-danger btn-revertir-nomina" data-numero="<?php echo htmlspecialchars($num_nomina_actual); ?>" style="padding: 6px 12px;">
                <i class="fas fa-undo-alt me-1"></i> Revertir
            </button>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="data-table-wrapper">
    <table class="table-custom" id="tablaNominas">
<thead>
    <tr>
        <!-- Columnas fijas de identificación comunes -->
        <th class="col-codigo" rowspan="2">Código</th>
        <th class="col-ci" rowspan="2">CI</th>
        <th class="col-nombre" rowspan="2">Nombre</th>
        <th class="col-area" rowspan="2">Área</th>
        <th class="col-cargo" rowspan="2">Cargo</th>
        <th class="col-centro" rowspan="2">Centro Costo</th>
        <th class="col-cat-ocup" rowspan="2">Cat.<br>Ocup.</th>
        
        <!-- Columnas específicas según el tipo de nómina activa -->
        <?php if ($tipo_nomina_activa == 'automatica' || $tipo_nomina_activa == 'extraordinaria'): ?>
            <th class="col-escala" rowspan="2">Escala</th>
            <th class="col-salario-basico" rowspan="2">Salario<br>Básico</th>
            <th class="col-horas" rowspan="2">Horas<br>Trab</th>
            
            <?php if ($tipo_nomina_activa == 'extraordinaria'): ?>
                <th class="col-nocturnas" rowspan="2">Horas<br>Nocturnas</th>
                <th class="col-nocturnas-val" rowspan="2">$/Hora<br>Nocturna</th>
                <th class="col-nocturnas-imp" rowspan="2">Importe<br>Nocturno</th>
            <?php endif; ?>
            
            <th class="col-salario-dev" rowspan="2">Salario<br>Dev</th>
            <th class="col-valor-hora" rowspan="2">$/Hora</th>
            
            <?php if ($tipo_nomina_activa == 'automatica'): ?>
                <th colspan="2" class="col-feriados-header">Días Feriados</th>
                <th colspan="2" class="col-vacaciones-header">Acum. Vacaciones</th>
                <th class="col-otros-pagos" rowspan="2">Otros<br>Pagos</th>
            <?php endif; ?>
            
        <?php elseif ($tipo_nomina_activa == 'bono'): ?>
            <th class="col-nombre" rowspan="2">Concepto</th>
            <th class="col-salario-basico" rowspan="2">Monto Bono</th>
            
        <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
            <th class="col-horas" rowspan="2">Días Tomados</th>
            <th class="col-horas" rowspan="2">Días Restantes</th>

        <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
            <th class="col-nombre" rowspan="2">Concepto</th>
            <th class="col-salario-basico" rowspan="2">Monto Ajuste</th>
        <?php endif; ?>
        
<!-- Columnas comunes de Totales y Deducciones (Unificadas para todos los tipos de nómina) -->
        <th class="col-total-devengado" rowspan="2">Total<br>Devengado</th>
        <th class="col-otros-descuentos" rowspan="2">Otras<br>Ret.</th>
        <th class="col-cess-header" rowspan="2">CESS.<br>Hasta10%</th> <!-- Primero CESS -->
        <th class="col-total-deducciones" rowspan="2">Total<br>Deducc.</th> <!-- Segundo Total Deducc -->
        <th colspan="<?php echo count($rangos_impuesto); ?>" class="col-impuestos">Ingresos Personales (Impuesto)</th>
        
        <th class="col-neto" rowspan="2">Salario<br>NETO</th>
        <th class="col-estado" rowspan="2">Estado</th>
        <th class="col-numero-nomina" rowspan="2">No.<br>Nómina</th>
        <th class="col-acciones" rowspan="2">Acciones</th>
    </tr>
    
    <!-- Fila de subencabezados con porcentajes de impuestos y acumulados -->
    <?php if ($has_subheader): ?>
    <tr>
        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <th class="col-feriados-dias" style="font-size: 0.7rem;">Días</th>
            <th class="col-feriados-imp" style="font-size: 0.7rem;">Importe</th>
            <th class="col-vacaciones-dias" style="font-size: 0.7rem;">Días</th>
            <th class="col-vacaciones-imp" style="font-size: 0.7rem;">Importe</th>
        <?php endif; ?>
        
        <?php foreach ($rangos_impuesto as $rango): 
            $tasa = $rango['tasa'] * 100;
            $desde = number_format($rango['desde'], 0, '.', '');
            $hasta = $rango['hasta'] ? number_format($rango['hasta'], 0, '.', '') : '∞';
        ?>
            <th style="font-size: 0.7rem;" class="col-impuestos-det"><?php echo $tasa; ?>%<br>(<?php echo $desde; ?>-<?php echo $hasta; ?>)</th>
        <?php endforeach; ?>
    </tr>
    <?php endif; ?>
</thead>

<tbody>
    <?php 
    $total_horas = 0; $total_feriados_dias = 0; $total_feriados_importe = 0; $total_devengado_calc = 0;
    $total_salario_laboral = 0; $total_descuentos = 0; $total_salario_basico = 0; $total_vacaciones_dias = 0;
    $total_vacaciones_importe = 0; $total_otros_pagos = 0;
    $total_horas_nocturnas = 0; $total_importe_nocturno = 0;
    
    $nominas_filtradas = $nominas;
    if ($filtro_cuenta == 'si') {
        $nominas_filtradas = array_filter($nominas, function($n) {
            return !empty($n['cuentabanc']);
        });
    } elseif ($filtro_cuenta == 'no') {
        $nominas_filtradas = array_filter($nominas, function($n) {
            return empty($n['cuentabanc']);
        });
    }
    
    foreach ($nominas_filtradas as $n): 
        // Acumulación de vacaciones proporcional a las horas de la nómina (Método 9.09% - Ley 116)
        $dias_acum_proy = roundExcel((($n['horas_laboradas'] ?? 0) * $factor_909) / $horas_jornada, 2);
        $importe_acum_proy = roundExcel($dias_acum_proy * (($n['salario_mensual'] ?? 0) / $dias_laborables), 2);
        
        $salario = $n['total_salario_devengado'];
        $tipo_descuento_n = $n['tipo_descuento'] ?? 'total_rangos';
        
        if ($tipo_descuento_n == 'solo_cess') {
            $impuestos_rango = [];
            foreach ($rangos_impuesto as $r) {
                $impuestos_rango[] = ['impuesto' => 0];
            }
        } else {
            $impuestos_rango = calcularImpuestosPorRango($salario, $rangos_impuesto);
        }
        $centro_costo_escala = htmlspecialchars($n['nombre_centro_costo'] ?? 'S/CC');
        $escala = numeroRomano($n['escala_numero'] ?? '?');
        $tiene_cuenta = !empty($n['cuentabanc']);
        
        $total_horas += $n['horas_laboradas'] ?? 0;
        $total_feriados_dias += $n['dias_feriados'] ?? 0;
        $total_feriados_importe += $n['importe_dias_feriados'] ?? 0;
        $total_horas_nocturnas += $n['horas_nocturnas'] ?? 0;
        $total_importe_nocturno += $n['importe_horas_nocturnas'] ?? 0;
        $total_devengado_calc += $n['total_salario_devengado'] ?? 0;
        $total_salario_laboral += $n['importe_salario_laboral'] ?? 0;
        $total_descuentos += $n['descuentos'] ?? 0;
        $total_salario_basico += $n['salario_mensual'] ?? 0;
        $total_vacaciones_dias += ($n['dias_acumulados'] ?? 0) + $dias_acum_proy;
        $total_vacaciones_importe += (($n['dias_acumulados'] ?? 0) + $dias_acum_proy) * ($n['salario_mensual'] / $dias_laborables);
        $total_otros_pagos += $n['otros_salarios'] ?? 0;
    ?>
<tr data-id="<?php echo $n['id']; ?>" 
    data-trabajador-id="<?php echo $n['trabajador_id']; ?>"
    data-centro-costo-id="<?php echo $n['centro_costo_id'] ?? ''; ?>"
    data-area-id="<?php echo $n['area_id'] ?? ''; ?>"
	data-area="<?php echo htmlspecialchars(($n['area_codigo'] ?? '') . ($n['area_codigo'] && $n['nombre_area'] ? ' - ' : '') . ($n['nombre_area'] ?? 'S/D')); ?>"
	data-centro-costo="<?php echo htmlspecialchars(($n['nombre_centro_costo'] ?? '') . ($n['nombre_centro_costo'] && $n['cc_nombre'] ? ' - ' : '') . ($n['cc_nombre'] ?? 'S/CC')); ?>"
	data-categoria="<?php echo htmlspecialchars(($n['categoria_ocupacional_codigo'] ?? '') . ($n['categoria_ocupacional_codigo'] && $n['categoria_ocupacional_nombre'] ? ' - ' : '') . ($n['categoria_ocupacional_nombre'] ?? 'S/D')); ?>"
	data-escala-descripcion="<?php 
		$num_escala = $n['escala_numero'] ?? '';
		$salario = $n['salario_mensual'] ?? 0;
		$romano = numeroRomano($num_escala);
		echo htmlspecialchars($romano . ' - Escala Salarial Grupo ' . $romano . ' ($' . number_format($salario, 2) . ')');
	?>"
	data-tipo-contrato="<?php echo htmlspecialchars($n['tipo_contrato'] ?? ''); ?>"
    data-foto-ruta="<?php echo htmlspecialchars($n['foto_ruta'] ?? ''); ?>"
    data-tipo="<?php echo $tipo_nomina_activa; ?>" 
    data-codigo="<?php echo htmlspecialchars($n['codigo'] ?? 'S/D'); ?>"
    data-ci="<?php echo htmlspecialchars($n['ci'] ?? ''); ?>"
    data-area="<?php echo htmlspecialchars($n['nombre_area'] ?? 'S/D'); ?>"
    data-centro-costo="<?php 
        $cc_val = '';
        if (!empty($n['nombre_centro_costo'])) {
            $cc_val = $n['nombre_centro_costo'];
            if (!empty($n['cc_nombre'])) $cc_val .= ' - ' . $n['cc_nombre'];
        } elseif (!empty($n['cc_nombre'])) {
            $cc_val = $n['cc_nombre'];
        } else {
            $cc_val = 'S/CC';
        }
        echo htmlspecialchars($cc_val);
    ?>"
    data-categoria-ocupacional-id="<?php echo $n['categoria_ocupacional_id'] ?? ''; ?>"
    data-escala-id="<?php echo $n['escala_salarial_id'] ?? ''; ?>"
data-escala-descripcion="<?php 
    $num_escala = $n['escala_numero'] ?? '';
    $salario = $n['salario_mensual'] ?? 0;
    $romano = numeroRomano($num_escala);
    echo htmlspecialchars('Escala ' . $romano . ' ($' . number_format($salario, 2) . ')');
?>"
    data-tipo-contrato="<?php echo htmlspecialchars($n['tipo_contrato'] ?? ''); ?>"
    data-categoria-codigo="<?php echo htmlspecialchars($n['categoria_ocupacional_codigo'] ?? ''); ?>"
	data-categoria-nombre="<?php echo htmlspecialchars($n['categoria_ocupacional_nombre'] ?? ''); ?>"
    data-escala-romana="<?php echo htmlspecialchars(numeroRomano($n['escala_numero'] ?? '?')); ?>"
    data-salario-hora="<?php echo $n['salario_hora_ordinaria']; ?>" 
    data-salario-mensual="<?php echo $n['salario_mensual']; ?>"
    data-dias-acumulados="<?php echo $n['dias_acumulados'] ?? 0; ?>"
    data-dias-ya-tomados="<?php echo $n['dias_vacaciones_tomados'] ?? 0; ?>"
    data-tiene-cuenta="<?php echo $tiene_cuenta ? 'si' : 'no'; ?>"
    data-tipo-descuento="<?php echo htmlspecialchars($tipo_descuento_n); ?>"
    data-no-acumular-vacaciones="<?php echo intval($n['no_acumular_vacaciones'] ?? 0); ?>"
    data-numero-nomina="<?php echo htmlspecialchars(!empty($n['numero_nomina']) ? $n['numero_nomina'] : 'Borrador'); ?>"
    data-cargo="<?php echo htmlspecialchars($n['cargo'] ?? 'S/D'); ?>"
	data-cargo-id="<?php echo $n['cargo_id'] ?? 0; ?>"
	data-escala-numero="<?php echo $n['escala_numero'] ?? 0; ?>"
	data-centro-costo-codigo="<?php echo $n['centro_costo_codigo'] ?? 0; ?>"
	data-dias-acumulados-value="<?php echo $n['dias_acumulados'] ?? 0; ?>">
	
	
    
    <!-- Columnas fijas de Identificación comunes -->
    <td class="text-center col-codigo"><?php echo htmlspecialchars($n['codigo']); ?></td>
    <td class="text-center col-ci"><?php echo htmlspecialchars($n['ci']); ?></td>
    <td class="text-nombre col-nombre"><?php echo htmlspecialchars($n['nombre_completo']); ?></td>
    <td class="text-center col-area"><?php echo htmlspecialchars($n['area_codigo'] ?? $n['area_id'] ?? '-'); ?></td>
    <td class="text-center col-cargo"><?php echo htmlspecialchars($n['cargo_id'] ?? 'S/D'); ?></td>
    <td class="text-center col-centro"><?php echo htmlspecialchars($n['nombre_centro_costo'] ?? 'S/CC'); ?></td>
<td class="text-center col-cat-ocup">
    <?php 
    $cod_cat = $n['categoria_ocupacional_codigo'] ?? '';
    $nom_cat = $n['categoria_ocupacional_nombre'] ?? '';
    echo htmlspecialchars($n['categoria_ocupacional_codigo'] ?? '-');
	?>
</td>

    <!-- Columnas específicas según Tipo de Nómina -->
    <?php if ($tipo_nomina_activa == 'automatica' || $tipo_nomina_activa == 'extraordinaria'): ?>
        <td class="text-center col-escala"><?php echo $escala; ?></td>
        <td class="text-end col-salario-basico salario-basico">$<?php echo number_format($n['salario_mensual'], 2); ?></td>
        <td class="text-center col-horas">
            <?php if (!$contabilizada): ?>
                <input type="text" class="edit-input edit-horas" value="<?php echo number_format($n['horas_laboradas'], 2); ?>">
            <?php else: echo number_format($n['horas_laboradas'], 2); endif; ?>
        </td>
        
        <?php if ($tipo_nomina_activa == 'extraordinaria'): ?>
            <td class="text-center col-nocturnas">
                <?php if (!$contabilizada): ?>
                    <input type="text" class="edit-input edit-nocturnas" value="<?php echo number_format($n['horas_nocturnas'] ?? 0, 2); ?>">
                <?php else: echo number_format($n['horas_nocturnas'] ?? 0, 2); endif; ?>
            </td>
            <td class="text-end col-nocturnas-val">$<?php echo number_format($n['salario_hora_ordinaria'] * $recargo_nocturno, 2); ?></td>
            <td class="text-end col-nocturnas-imp importe-nocturno">$<?php echo number_format($n['importe_horas_nocturnas'] ?? 0, 2); ?></td>
        <?php endif; ?>
        
        <td class="text-end col-salario-dev salario-laboral">$<?php echo number_format($n['importe_salario_laboral'], 2); ?></td>
        <td class="text-end col-valor-hora salario-hora-real">$<?php echo number_format(($n['horas_laboradas'] > 0) ? $n['importe_salario_laboral'] / $n['horas_laboradas'] : 0, 2); ?></td>
        
		<?php if ($tipo_nomina_activa == 'automatica'): ?>
                        <td class="text-center col-feriados-dias">
                            <?php if (!$contabilizada): ?>
                                <input type="text" class="edit-input edit-feriados" value="<?php echo number_format($n['dias_feriados'] ?? 0, 2); ?>">
                            <?php else: echo number_format($n['dias_feriados'] ?? 0, 2); endif; ?>
                        </td>
                        <td class="text-end col-feriados-imp feriados-importe">$<?php echo number_format($n['importe_dias_feriados'] ?? 0, 2); ?></td>
                        
                        <td class="text-center col-vacaciones-dias vacaciones-dias">
                            <?php echo number_format(($contabilizada ? ($n['total_vacaciones_acumuladas'] ?? $n['dias_acumulados'] ?? 0) : ($n['dias_acumulados'] + $dias_acum_proy)), 2); ?>
                        </td>
                        <td class="text-end col-vacaciones-imp vacations-importe">
                            <?php echo "$" . number_format(($contabilizada ? ($n['total_importe_vacaciones_acumuladas'] ?? 0) : (($n['dias_acumulados'] + $dias_acum_proy) * ($n['salario_mensual'] / $dias_laborables))), 2); ?>
                        </td>
                    <?php endif; ?>
        
        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <td class="text-end col-otros-pagos">
                <?php if (!$contabilizada): ?>
                    <input type="text" class="edit-input edit-otros-pagos" value="<?php echo number_format($n['otros_salarios'] ?? 0, 2); ?>">        
                <?php else: echo "$" . number_format($n['otros_salarios'] ?? 0, 2); endif; ?>
            </td>
        <?php endif; ?>

    <?php elseif ($tipo_nomina_activa == 'bono'): ?>
        <td class="text-center col-nombre"><?php echo htmlspecialchars($n['descripcion'] ?? '-'); ?></td>
        <td class="text-end col-salario-basico bono-val-cell">
            <?php if (!$contabilizada): ?>
                <input type="text" class="edit-input edit-bono" value="<?php echo number_format($n['pago_resultado'] ?? 0, 2); ?>">
            <?php else: echo "$" . number_format($n['pago_resultado'] ?? 0, 2); endif; ?>
        </td>

    <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
        <td class="text-center col-horas">
            <?php if (!$contabilizada): ?>
                <input type="text" class="edit-input edit-dias" value="<?php echo number_format($n['dias_vacaciones_tomados'] ?? 0, 2); ?>">
            <?php else: echo number_format($n['dias_vacaciones_tomados'] ?? 0, 2); endif; ?>
        </td>
        <td class="text-center col-horas dias-restantes">
            <?php 
            $dias_restantes = ($contabilizada && isset($n['dias_restantes'])) ? floatval($n['dias_restantes']) : max(0, ($n['dias_acumulados'] ?? 0) - ($n['dias_vacaciones_tomados'] ?? 0));
            $color_clase = ($dias_restantes <= 0) ? 'text-danger' : (($dias_restantes <= 5) ? 'text-warning' : 'text-success');
            echo '<span class="' . $color_clase . ' fw-bold">' . number_format($dias_restantes, 2) . '</span>';
            ?>
        </td>

    <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
        <td class="text-center col-nombre"><?php echo htmlspecialchars($n['descripcion'] ?? '-'); ?></td>
        <td class="text-end col-salario-basico bono-val-cell">
            <?php if ($n['estado'] == 'borrador'): ?>
                <input type="text" class="edit-input edit-bono" value="<?php echo number_format($n['pago_resultado'] ?? 0, 2); ?>">
            <?php else: echo "$" . number_format($n['pago_resultado'] ?? 0, 2); endif; ?>
        </td>
    <?php endif; ?>

<!-- Columnas comunes de Totales y Deducciones (Unificadas para todas las nóminas) -->
    <td class="text-end col-total-devengado total-devengado fw-bold text-white">$<?php echo number_format($n['total_salario_devengado'], 2); ?></td>
    <td class="text-end col-otros-descuentos">
        <?php if ($n['estado'] == 'borrador'): ?>
            <input type="text" class="edit-input edit-descuentos" value="<?php echo number_format($n['descuentos'] ?? 0, 2); ?>">
        <?php else: echo "$" . number_format($n['descuentos'] ?? 0, 2); endif; ?>
    </td>
    <td class="text-end col-cess-header contribucion">$<?php echo number_format($n['contribucion_especial'], 2); ?></td> <!-- Primero CESS -->
    <td class="text-end col-total-deducciones total-deducciones fw-bold text-warning">$<?php echo number_format(($n['contribucion_especial'] + $n['ingresos_personales'] + ($n['descuentos'] ?? 0)), 2); ?></td> <!-- Segundo Total Deducc -->
    
    <!-- Renderizado dinámico de los tramos de impuestos para todas las nóminas -->
    <?php foreach ($impuestos_rango as $idx => $imp): ?>
         <td class="text-end col-impuestos-det impuesto-rango-<?php echo $idx; ?>">$<?php echo number_format($imp['impuesto'] ?: 0, 2); ?></td>
    <?php endforeach; ?>

    <td class="text-end col-neto neto fw-bold text-success">$<?php echo number_format($n['importe_neto'], 2); ?></td>
    <td class="text-center col-estado">
        <span class="badge <?php echo ($n['estado'] == 'borrador') ? 'badge-borrador' : 'badge-contabilizado'; ?>">
            <?php echo ($n['estado'] == 'borrador') ? 'Borrador' : 'Contab.'; ?>
        </span>
    </td>
    <td class="text-center col-numero-nomina">
        <?php if (!empty($n['numero_nomina'])): ?>
            <span class="badge badge-numero-nomina"><?php echo htmlspecialchars($n['numero_nomina']); ?></span>
        <?php else: ?>
            <span class="text-white-50" style="font-size: 0.72rem;">Borrador</span>
        <?php endif; ?>
    </td>
    <td class="text-center col-acciones">
        <?php if ($n['estado'] == 'borrador'): ?>
            <button class="btn-icon btn-icon-success guardar-fila" title="Guardar"><i class="fas fa-save"></i></button>
            <button class="btn-icon btn-icon-danger eliminar-fila" data-id="<?php echo $n['id']; ?>" data-nombre="<?php echo htmlspecialchars($n['nombre_completo']); ?>" title="Eliminar"><i class="fas fa-trash"></i></button>
        <?php else: ?>
            <i class="fas fa-lock text-white-50"></i>
        <?php endif; ?>
    </td>
</tr>
    <?php endforeach; ?>
</tbody>


<?php
// Dentro del tfoot, obtén el valor directamente
$td_activo = 'total_rangos';
if ($existe_nomina) {
    $stmt = $pdo->prepare("SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? LIMIT 1");
    $stmt->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    $td_activo = $stmt->fetchColumn() ?: 'total_rangos';
}
?>
<tfoot>
    <?php if ($tipo_nomina_activa == 'automatica'): ?>
        <!-- ========================================== -->
        <!-- TFOOT PARA NÓMINA AUTOMÁTICA (24 + N columnas) -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <!-- Celdas de identificación vacías -->
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            
            <td class="text-end fw-bold">TOTALES:</td>
            <td class="text-end total-salario-basico-footer">$<?php echo number_format($total_salario_basico, 2); ?></td>
            <td class="text-center total-horas-footer"><?php echo number_format($total_horas, 2); ?></td>
            <td class="text-end total-salario-laboral-footer">$<?php echo number_format($total_salario_laboral, 2); ?></td>
            <td class="text-end total-promedio-hora-footer">$<?php echo number_format($total_horas > 0 ? $total_devengado_calc / $total_horas : 0, 2); ?></td>
            <td class="text-center total-feriados-dias-footer"><?php echo number_format($total_feriados_dias, 2); ?></td>
            <td class="text-end total-feriados-importe-footer">$<?php echo number_format($total_feriados_importe, 2); ?></td>
            <td class="text-center total-vacaciones-dias-footer"><?php echo number_format($total_vacaciones_dias, 2); ?></td>
            <td class="text-end total-vacaciones-importe-footer">$<?php echo number_format($total_vacaciones_importe, 2); ?></td>
            <td class="text-end total-otros-pagos-footer">$<?php echo number_format($total_otros_pagos, 2); ?></td>
            
            <!-- Columnas comunes de totales y retenciones en orden correcto -->
            <td class="text-end total-devengado-footer">$<?php echo number_format($total_devengado, 2); ?></td>
            <td class="text-end total-descuentos-footer">$<?php echo number_format($total_descuentos, 2); ?></td>
            <td class="text-end total-contribucion-footer">$<?php echo number_format($total_contribucion, 2); ?></td> <!-- CESS Primero -->
            <td class="text-end total-deducciones-footer">$<?php echo number_format($total_contribucion + array_sum($totales_impuestos) + $total_descuentos, 2); ?></td> <!-- Deducciones Segundo -->
            
            <!-- Tramos de impuestos dinámicos -->
            <?php foreach ($totales_impuestos as $idx => $total_imp): ?>
                <td class="text-end total-impuesto-<?php echo $idx; ?>-footer col-impuestos-det">$<?php echo number_format($total_imp, 2); ?></td>
            <?php endforeach; ?>
            
            <td class="text-end total-neto-footer">$<?php echo number_format($total_neto, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
        </tr>
        
    <?php elseif ($tipo_nomina_activa == 'extraordinaria'): ?>
        <!-- ========================================== -->
        <!-- TFOOT PARA NÓMINA EXTRAORDINARIA (22 + N columnas) -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <!-- Celdas de identificación vacías -->
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            
            <td class="text-end fw-bold">TOTALES:</td>
            <td class="text-end total-salario-basico-footer">$<?php echo number_format($total_salario_basico, 2); ?></td>
            <td class="text-center total-horas-footer"><?php echo number_format($total_horas, 2); ?></td>
            <td class="text-center total-nocturnas-footer"><?php echo number_format($total_horas_nocturnas, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-end total-importe-nocturno-footer">$<?php echo number_format($total_importe_nocturno, 2); ?></td>
            <td class="text-end total-salario-laboral-footer">$<?php echo number_format($total_salario_laboral, 2); ?></td>
            <td class="text-end total-promedio-hora-footer">$<?php echo number_format($total_horas > 0 ? $total_salario_laboral / $total_horas : 0, 2); ?></td>
            
            <!-- Columnas comunes de totales y retenciones en orden correcto -->
            <td class="text-end total-devengado-footer">$<?php echo number_format($total_devengado, 2); ?></td>
            <td class="text-end total-descuentos-footer">$<?php echo number_format($total_descuentos, 2); ?></td>
            <td class="text-end total-contribucion-footer">$<?php echo number_format($total_contribucion, 2); ?></td> <!-- CESS Primero -->
            <td class="text-end total-deducciones-footer">$<?php echo number_format($total_contribucion + array_sum($totales_impuestos) + $total_descuentos, 2); ?></td> <!-- Deducciones Segundo -->
            
            <!-- Tramos de impuestos dinámicos -->
            <?php foreach ($totales_impuestos as $idx => $total_imp): ?>
                <td class="text-end total-impuesto-<?php echo $idx; ?>-footer col-impuestos-det">$<?php echo number_format($total_imp, 2); ?></td>
            <?php endforeach; ?>
            
            <td class="text-end total-neto-footer">$<?php echo number_format($total_neto, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
        </tr>
        
    <?php elseif ($tipo_nomina_activa == 'bono'): ?>
        <!-- ========================================== -->
        <!-- TFOOT PARA NÓMINA DE BONO (15 + R columnas)  -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <!-- Celdas de identificación vacías -->
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            
            <td class="text-end fw-bold">TOTALES:</td> 
            <td class="text-center">-</td> <!-- Columna Concepto -->
            <td class="text-end total-monto-bono-footer">$<?php echo number_format($total_devengado_calc, 2); ?></td> <!-- Monto Bono -->
            
            <!-- Columnas comunes de totales y retenciones en orden correcto -->
            <td class="text-end total-devengado-footer">$<?php echo number_format($total_devengado, 2); ?></td>
            <td class="text-end total-descuentos-footer">$<?php echo number_format($total_descuentos, 2); ?></td>
            <td class="text-end total-contribucion-footer">$<?php echo number_format($total_contribucion, 2); ?></td> <!-- CESS Primero -->
            <td class="text-end total-deducciones-footer">$<?php echo number_format($total_contribucion + array_sum($totales_impuestos) + $total_descuentos, 2); ?></td> <!-- Deducciones Segundo -->
            
            <!-- Tramos de impuestos dinámicos -->
            <?php foreach ($totales_impuestos as $idx => $total_imp): ?>
                <td class="text-end total-impuesto-<?php echo $idx; ?>-footer col-impuestos-det">$<?php echo number_format($total_imp, 2); ?></td>
            <?php endforeach; ?>
            
            <td class="text-end total-neto-footer">$<?php echo number_format($total_neto, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
        </tr>        

    <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
        <!-- ========================================== -->
        <!-- TFOOT PARA NÓMINA DE VACACIONES (15 + R columnas) -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <!-- Celdas de identificación vacías -->
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            
            <td class="text-end fw-bold">TOTALES:</td> 
            <td class="text-center total-vacaciones-dias-footer"><?php echo number_format($total_vacaciones_dias, 2); ?></td> <!-- Días Tomados -->
            <td class="text-center">-</td> <!-- Días Restantes -->
            
            <!-- Columnas comunes de totales y retenciones en orden correcto -->
            <td class="text-end total-devengado-footer">$<?php echo number_format($total_devengado, 2); ?></td>
            <td class="text-end total-descuentos-footer">$<?php echo number_format($total_descuentos, 2); ?></td>
            <td class="text-end total-contribucion-footer">$<?php echo number_format($total_contribucion, 2); ?></td> <!-- CESS Primero -->
            <td class="text-end total-deducciones-footer">$<?php echo number_format($total_contribucion + array_sum($totales_impuestos) + $total_descuentos, 2); ?></td> <!-- Deducciones Segundo -->
            
            <!-- Tramos de impuestos dinámicos -->
            <?php foreach ($totales_impuestos as $idx => $total_imp): ?>
                <td class="text-end total-impuesto-<?php echo $idx; ?>-footer col-impuestos-det">$<?php echo number_format($total_imp, 2); ?></td>
            <?php endforeach; ?>
            
            <td class="text-end total-neto-footer">$<?php echo number_format($total_neto, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
        </tr>

    <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
        <!-- ========================================== -->
        <!-- TFOOT PARA NÓMINA DE AJUSTE -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>

            <td class="text-end fw-bold">TOTALES:</td>
            <td class="text-center">-</td> <!-- Columna Concepto -->
            <td class="text-end total-monto-bono-footer">$<?php echo number_format($total_devengado_calc, 2); ?></td> <!-- Monto Ajuste -->

            <td class="text-end total-devengado-footer">$<?php echo number_format($total_devengado, 2); ?></td>
            <td class="text-end total-descuentos-footer">$<?php echo number_format($total_descuentos, 2); ?></td>
            <td class="text-end total-contribucion-footer">$<?php echo number_format($total_contribucion, 2); ?></td>
            <td class="text-end total-deducciones-footer">$<?php echo number_format($total_contribucion + array_sum($totales_impuestos) + $total_descuentos, 2); ?></td>

            <?php foreach ($totales_impuestos as $idx => $total_imp): ?>
                <td class="text-end total-impuesto-<?php echo $idx; ?>-footer col-impuestos-det">$<?php echo number_format($total_imp, 2); ?></td>
            <?php endforeach; ?>

            <td class="text-end total-neto-footer">$<?php echo number_format($total_neto, 2); ?></td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
        </tr>
    <?php endif; ?>
</tfoot>


</table
>
		</div>
		</div>
    <?php endif; ?>
    
<?php include '../includes/footer.php'; ?>




</div>

<!-- ========================================== -->
<!-- MODALES (Estilo Glassmorphism)             -->
<!-- ========================================== -->
<!-- MODAL AGREGAR TRABAJADORES A NÓMINA AUTOMÁTICA -->
<div class="modal fade" id="modalAgregarTrabajadoresAuto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2" style="color: #10b981;"></i>
                    Agregar trabajadores a nómina automática
                </h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3" style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 10px;">
                    <i class="fas fa-info-circle me-2"></i>
                    Se agregarán <strong>solo los trabajadores que NO estén actualmente en esta nómina</strong>.
                    Usarán los valores por defecto (horas mensuales, sin feriados, sin descuentos).
                </div>
                
                <!-- Filtros -->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Filtrar por Área</label>
                        <select id="filterAutoArea" class="form-select form-select-sm">
                            <option value="">-- Todas --</option>
                            <?php foreach ($all_areas as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Centro de Costo</label>
                        <select id="filterAutoCC" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <?php foreach ($all_centros as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Buscar</label>
                        <input type="text" id="searchAutoWorker" class="form-control form-control-sm" placeholder="Nombre, código o CI...">
                    </div>
                </div>
                
                <!-- Lista de trabajadores disponibles -->
                <label class="form-label"><i class="fas fa-users me-1"></i> Trabajadores disponibles</label>
                <div id="autoWorkerList" class="worker-list-container" style="max-height: 350px; overflow-y: auto;">
                    <div class="text-center p-3 text-white-50">Cargando trabajadores...</div>
                </div>
            </div>
<div class="modal-footer">
    <div class="me-auto text-muted small">
        <i class="fas fa-check-circle text-success me-1"></i>
        <span id="selectedAutoCount">0</span> trabajadores seleccionados
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn-win-info" id="btnSeleccionarTodosAuto" style="background: rgba(16, 185, 129, 0.2); border-color: #10b981;">
            <i class="fas fa-check-double me-2"></i>Seleccionar todos disponibles
        </button>
        <button type="button" class="btn-win" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cancelar
        </button>
        <button type="button" class="btn-win-success" id="btnConfirmarAgregarAuto" disabled>
            <i class="fas fa-save me-2"></i>Agregar a nómina
        </button>
    </div>
</div>
        </div>
    </div>
</div>
<!-- Modal Selección Tipo de Descuento General (Para Extraordinaria, Vacaciones, Bonos) -->
<div class="modal fade" id="modalSeleccionDescuentoGeneral" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #0ea5e9); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-percent fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Seleccionar Tipo de Descuento</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Elija el método de cálculo para la nómina</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <input type="hidden" id="descuentoGeneralTarget" value="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionTotalDescuentosGen" style="cursor: pointer; padding: 20px; border-radius: 16px; background: rgba(28, 28, 35, 0.6); border: 2px solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-chart-line fa-2x" style="color: #f59e0b;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Total de Descuentos por rangos</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Aplica descuentos progresivos del 3%, 5%, 7.5% hasta 50% según rangos de ingreso</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionSoloCessGen" style="cursor: pointer; padding: 20px; border-radius: 16px; background: rgba(28, 28, 35, 0.6); border: 2px solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-shield-alt fa-2x" style="color: #10b981;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Parcial de descuentos por Rangos (PDL SOLO CESS)</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Solo aplica Contribución a la Seguridad Social: 5% hasta 15,000 CUP, 10% sobre el exceso</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px;">
                <button type="button" class="btn-win" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-win-primary" id="btnConfirmarDescuentoGen" disabled>
                    <i class="fas fa-arrow-right me-2"></i>Siguiente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Extraordinaria -->
<div class="modal fade" id="modalExtraordinaria" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clock me-2"></i>Generar Nómina Extraordinaria</h5>
				<button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formExtraordinaria">
                <input type="hidden" name="tipo_descuento_extra" id="tipoDescuentoExtra" value="total_rangos">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterExtraArea" class="form-select form-select-sm filter-modal-worker" data-modal="extra">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
<select id="filterExtraCC" class="form-select form-select-sm filter-modal-worker" data-modal="extra">
    <option value="">-- Todos --</option>
    <?php foreach ($all_centros as $c): ?>
        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
    <?php endforeach; ?>
</select>
                                </div>
                            </div>
                            <label class="form-label"><i class="fas fa-search me-1"></i> Buscar trabajador</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchExtraWorker" placeholder="Nombre, código o CI..." autocomplete="off">
                            </div>
                            <div class="worker-list-container" id="extraWorkerList"></div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label"><i class="fas fa-list me-1"></i> Seleccionados</label>
                            <div id="selectedExtraList" class="selected-workers-container" style="min-height: 200px;">
                                <em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</em>
                            </div>
                            <div id="previewExtra" class="preview-card" style="display: none;">
                                <h6><i class="fas fa-chart-line me-2"></i>Resumen</h6>
                                <div class="info-row"><span>Trabajadores:</span><span id="previewExtraCount">0</span></div>
                                <div class="info-row"><span>Total Horas:</span><span id="previewExtraTotalHoras">0</span></div>
                                <div class="info-row"><span>Total Devengado:</span><span class="info-value-success" id="previewExtraTotalDevengado">$0.00</span></div>
                                <div class="info-row"><span>Total Neto:</span><span class="info-value-success" id="previewExtraTotalNeto">$0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_extraordinaria" class="btn-win-primary" id="btnGenerarExtraordinaria"><i class="fas fa-save me-2"></i>Generar Nómina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Vacaciones -->
<div class="modal fade" id="modalVacaciones" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-umbrella-beach me-2"></i>Agregar Trabajadores a la Nómina de Vacaciones</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formVacaciones">
                <input type="hidden" name="agregar_vacaciones" value="1">
                <input type="hidden" name="tipo_descuento_vacaciones" id="tipoDescuentoVac" value="total_rangos">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-filter me-1"></i> Filtrar por rango de días disponibles</label>
                        <div class="rango-dias-selector">
                            <button type="button" class="rango-btn" data-rango="1-5"><i class="far fa-calendar me-1"></i>1-5 días</button>
                            <button type="button" class="rango-btn" data-rango="5-10"><i class="far fa-calendar-minus me-1"></i>5-10 días</button>
                            <button type="button" class="rango-btn" data-rango="10-15"><i class="far fa-calendar-plus me-1"></i>10-15 días</button>
                            <button type="button" class="rango-btn" data-rango="15-20"><i class="far fa-calendar-check me-1"></i>15-20 días</button>
                            <button type="button" class="rango-btn" data-rango="20+"><i class="fas fa-calendar-alt me-1"></i>20+ días</button>
                            <button type="button" class="rango-btn active" data-rango="todos"><i class="fas fa-calendar me-1"></i>Todos</button>
                        </div>
                    </div>
                    
                    <div class="row">
						<div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterVacArea" class="form-select form-select-sm filter-modal-worker" data-modal="vac">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
                                    <select id="filterVacCC" class="form-select form-select-sm filter-modal-worker" data-modal="vac">
                                        <option value="">-- Todos --</option>
                                        <?php foreach ($all_centros as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <label class="form-label"><i class="fas fa-search me-1"></i> Buscar trabajador</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchWorker" placeholder="Nombre..." autocomplete="off">
                            </div>
                            <div class="worker-list-container" id="workerList"></div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label"><i class="fas fa-user-clock me-1"></i> Trabajadores seleccionados</label>
                            <div id="selectedWorkersList" class="selected-workers-container" style="min-height: 200px;">
                                <em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</em>
                            </div>
                            <div id="previewMultiple" class="preview-card" style="display: none;">
                                <h6><i class="fas fa-chart-line me-2"></i>Resumen</h6>
                                <div class="info-row"><span>Trabajadores:</span><span id="previewCount">0</span></div>
                                <div class="info-row"><span>Total Días:</span><span id="previewTotalDias">0</span></div>
                                <div class="info-row"><span>Total Devengado:</span><span class="info-value-success" id="previewTotalDevengado">$0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="agregar_vacaciones" class="btn-win-primary" id="btnAgregarVacaciones"><i class="fas fa-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Generar Bonos -->
<div class="modal fade" id="modalBono" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-gift me-2"></i>Generar Bonos y/o Pagos Adicionales.</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formBonos">
                <input type="hidden" name="tipo_descuento_bono" id="tipoDescuentoBono" value="total_rangos">
                <div class="modal-body">
                    <div class="row">
						<div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterBonoArea" class="form-select form-select-sm filter-modal-worker" data-modal="bono">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
                                    <select id="filterBonoCC" class="form-select form-select-sm filter-modal-worker" data-modal="bono">
                                        <option value="">-- Todos --</option>
                                        <?php foreach ($all_centros as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <label class="form-label"><i class="fas fa-search me-1"></i> Buscar trabajador</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchBonoWorker" placeholder="Nombre..." autocomplete="off">
                            </div>
                            <div class="worker-list-container" id="bonoWorkerList"></div>
                        </div>
<!-- Cambios en el panel derecho del modal #modalBono -->
<div class="col-md-7">
    <!-- NUEVO: Campo para definir el Fondo Inicial -->
    <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.08);">
        <label class="form-label text-warning" style="font-weight: 600;">
            <i class="fas fa-calculator me-1"></i> Fondo Inicial para Distribución ($)
        </label>
        <input type="number" id="montoInicialBono" class="form-control form-control-custom w-100 text-white" 
               style="background: rgba(20,20,30,0.8); border: 1px solid rgba(255,255,255,0.15);" 
               placeholder="Ingrese el monto total disponible para repartir..." min="0" step="0.01">
        <small class="text-muted d-block mt-1">Este fondo servirá de límite y referencia durante el cálculo.</small>
    </div>

    <label class="form-label"><i class="fas fa-list me-1"></i> Bonos y/o Pagos a generar</label>
    <div id="bonosList" class="bono-list-container mb-3" style="min-height: 180px;"></div>
    
    <div class="mb-3">
        <label class="form-label">Concepto del Bono o pago</label>
        <input type="text" name="concepto_bono" id="conceptoBono" class="form-control-custom w-100" required placeholder="Ej: Productividad">
    </div>

    <!-- MODIFICADO: Tarjeta de Resumen con control de saldo restante -->
    <div id="previewBonos" class="preview-card" style="display: none;">
        <h6><i class="fas fa-chart-line me-2"></i>Resumen de Distribución de Fondos</h6>
        <div class="info-row">
            <span>Trabajadores seleccionados:</span>
            <span id="previewBonoCount" style="font-weight: bold;">0</span>
        </div>
        <div class="info-row">
            <span>Fondo Inicial asignado:</span>
            <span id="previewFondoInicial" style="font-weight: bold; color: #60a5fa;">$0.00</span>
        </div>
        <div class="info-row">
            <span>Monto Total repartido:</span>
            <span id="previewTotalMonto" style="font-weight: bold; color: #f59e0b;">$0.00</span>
        </div>
        <div class="info-row" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px; margin-top: 8px;">
            <span>Monto Restante en fondo:</span>
            <span id="previewMontoRestante" class="info-value-success" style="font-weight: bold; font-size: 1.1rem;">$0.00</span>
        </div>
    </div>
</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_bono" class="btn-win-primary" id="btnGenerarBonos"><i class="fas fa-save me-2"></i>Generar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- SCRIPTS -->
<!-- 1. Librerías Base (jQuery y Bootstrap) -->
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>

<!-- 2. Dependencias de Exportación (Deben cargarse ANTES de los botones de DataTables) -->
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>

<!-- 3. Núcleo de DataTables -->
<script src="../js/datatables/1.13.6/jquery.dataTables.min.js"></script>

<!-- 4. Extensión Buttons de DataTables (Carga la base primero, luego los submódulos) -->
<script src="../js/datatables/1.13.6/dataTables.buttons.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.html5.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.print.min.js"></script>
<script src="../js/datatables/1.13.6/buttons.colVis.min.js"></script>

<script>

// Función para limpiar valores numéricos (remover comas, signos de dólar, etc.)
function limpiarNumero(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return 0;
    // Convertir a string y limpiar
    var str = valor.toString();
    // Remover todo excepto números, punto decimal y signo negativo
    var limpio = str.replace(/[^0-9.-]/g, '');
    var num = parseFloat(limpio);
    return isNaN(num) ? 0 : num;
}
// Datos desde PHP para los filtros de impresión
var areasDisponibles = <?php echo json_encode($all_areas); ?>;
var centrosCostoDisponibles = <?php echo json_encode($all_centros); ?>;
var categoriasDisponibles = <?php echo json_encode($pdo->query("SELECT id, codigo, nombre FROM categorias_ocupacionales WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC)); ?>;
var escalasDisponibles = <?php echo json_encode($pdo->query("SELECT id, escala_numero, salario_mensual FROM escalas_salariales WHERE activo=1 ORDER BY escala_numero")->fetchAll(PDO::FETCH_ASSOC)); ?>;
var tiposContrato = [
    { id: 'Indeterminado', nombre: 'Indeterminado' },
    { id: 'Determinado', nombre: 'Determinado' },
    { id: 'A Prueba', nombre: 'A Prueba' }
];


// Variable global para almacenar los datos activos del selector
window.currentFilterData = { datos: [], alcance: '' };


// Función unificada para obtener la etiqueta formateada con Código - Descripción
function obtenerLabelPorAlcance(item, alcance) {
    switch(alcance) {
        case 'area':
            return `${item.codigo} - ${item.nombre_area}`;
        case 'centro_costo':
            return `${item.codigo} - ${item.nombre}`;
        case 'categoria':
            //return `${item.codigo} - ${item.nombre}`;
			return item.nombre;
        case 'escala':
            let rom = obtenerRomanoLocal(item.escala_numero);
            return `${rom} - Escala Grupo ${rom} ($${parseFloat(item.salario_mensual).toFixed(2)})`;
        case 'tipo_contrato':
            let cod_tc = 'CONTR';
            if (item.nombre.toLowerCase().includes('indet')) cod_tc = 'IND';
            else if (item.nombre.toLowerCase().includes('determ')) cod_tc = 'DET';
            else if (item.nombre.toLowerCase().includes('prueba')) cod_tc = 'APR';
            return `${cod_tc} - ${item.nombre}`;
        case 'cargo':  // <-- NUEVO CASO
            return item.nombre;  // Ya viene como "1 - Jefa de Proyecto"
        default:
            return item.nombre || item.codigo || '';
    }
}

function cargarSelectores() {
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    const contenedor = $('#contenedorSelectores');
    contenedor.empty();

    // Si es General o Tirillas, ocultar selectores y NO validar filtros
    if (alcance === 'general' || alcance === 'tirillas') {
        $('#selectoresDinamicos').hide();
        contenedor.empty();
        window.currentFilterData = { datos: [], alcance: alcance };
        // IMPORTANTE: Limpiar cualquier valor previo del selector
        $('#selectImpresion').val('');
        return;
    }

    // Resto del código para otros alcances (area, centro_costo, etc.)
    $('#selectoresDinamicos').show();
    let datos = [];
    let nombreCampo = '';

    switch(alcance) {
        case 'area':
            datos = areasDisponibles;
            nombreCampo = 'Área';
            break;
        case 'centro_costo':
            datos = centrosCostoDisponibles;
            nombreCampo = 'Centro de Costo';
            break;
        case 'categoria':
            datos = categoriasDisponibles;
            nombreCampo = 'Categoría Ocupacional';
            break;
        case 'escala':
            datos = escalasDisponibles;
            nombreCampo = 'Escala Salarial';
            break;
        case 'tipo_contrato':
            datos = tiposContrato;
            nombreCampo = 'Tipo de Contrato';
            break;
        case 'cargo':
            datos = cargosDisponibles;
            nombreCampo = 'Cargo';
            break;
        default:
            return;
    }

    if (!datos || datos.length === 0) {
        contenedor.html('<div class="alert alert-warning m-2">No hay datos disponibles para este filtro.</div>');
        return;
    }

    window.currentFilterData = { datos: datos, alcance: alcance };

    let html = `
        <div class="p-1">
            <label class="form-label fw-bold mb-2 d-flex align-items-center justify-content-between" style="font-size: 0.82rem; color: #a3a3a3;">
                <span><i class="fas fa-search me-1 text-info"></i> Seleccione ${nombreCampo}</span>
                <span class="badge" style="background: rgba(255,255,255,0.06); color: #888;" id="badgeResultadosCount">${datos.length} disponibles</span>
            </label>
            <div class="input-group mb-2 shadow-sm">
                <span class="input-group-text border-secondary" style="background: rgba(20,20,30,0.8); border-color: rgba(255,255,255,0.12) !important; color: #60a5fa;"><i class="fas fa-filter"></i></span>
                <input type="text" id="buscarEnSelector" class="form-control border-secondary text-white" placeholder="Escriba para buscar..." style="background: rgba(20,20,30,0.8); border-color: rgba(255,255,255,0.12) !important; font-size: 0.85rem;" autocomplete="off">
            </div>
            <select class="form-select form-select-custom select-scroll-panel" id="selectImpresion" size="4" style="max-height: 150px; overflow-y: auto;">
                <option value="" selected>-- Todos --</option>
    `;

    datos.forEach(item => {
        let value = item.id;
        let label = obtenerLabelPorAlcance(item, alcance);
        html += `<option value="${value}">${label}</option>`;
    });
    
    html += `
            </select>
        </div>
    `;
    
    contenedor.html(html);
}


    // Función auxiliar local para formatear números romanos
    function obtenerRomanoLocal(num) {
        let val = parseInt(num);
        if (isNaN(val) || val <= 0) return num || '?';
        const lookup = {M:1000,CM:900,D:500,CD:400,C:100,XC:90,L:50,XL:40,X:10,IX:9,V:5,IV:4,I:1};
        let roman = '';
        for (let i in lookup) {
            while (val >= lookup[i]) {
                roman += i;
                val -= lookup[i];
            }
        }
        return roman;
    }
	
// ==========================================
// FUNCIÓN DE REDONDEO TIPO EXCEL
// ==========================================
function roundExcel(number, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    var multiplier = Math.pow(10, precision);
    return Math.floor(number * multiplier + 0.5) / multiplier;
}

// Asignar a Math para compatibilidad
Math.roundExcel = function(number, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    return roundExcel(number, precision);
};

// ==========================================
// VARIABLES GLOBALES DE JAVASCRIPT
// ==========================================
var logoBase64 = '<?php echo $logoBase64; ?>';
var nombreEmpresa = '<?php echo addslashes($config_empresa['nombre_empresa']); ?>';
//var jefeProyecto = '<?php echo addslashes(JEFE_PROYECTO); ?>';
//var especialistaGestion = '<?php echo addslashes(ESPECIALISTA); ?>';
var tipoNominaTexto = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
window.tipoNomina = '<?php echo $tipo_nomina_activa; ?>';
var tipoNomina = window.tipoNomina; // Para compatibilidad en ambas llamadas
window.trabajadores = <?php echo json_encode($trabajadores); ?>;
//var periodoTexto = '<?php echo $nombre_mes . " " . $anio; ?>';
var periodoTexto = '<?php echo date("d/m/Y", strtotime($periodo_desde)) . " al " . date("d/m/Y", strtotime($periodo_hasta)); ?>';
var periodo = '<?php echo addslashes($periodo); ?>'; 
var usuarioNombre = '<?php echo addslashes($user_nombre_completo); ?>';
var recargoNocturno = parseFloat('<?php echo $recargo_nocturno; ?>') || 1.25;

var jefeProyecto = '<?php echo addslashes($config_empresa['jefe_proyecto'] ?? 'Dainelys León Reyes'); ?>';
var especialistaGestion = '<?php echo addslashes($config_empresa['especialista_gestion'] ?? 'Mailén Pérez García'); ?>';


var cargosDisponibles = <?php 
    $stmt_cargos = $pdo->query("SELECT id, CONCAT(id, ' - ', nombre_cargo) as nombre FROM cargos_plantilla WHERE activo = 1 ORDER BY id");
    echo json_encode($stmt_cargos->fetchAll(PDO::FETCH_ASSOC)); 
?>;

var numeroNomina = '<?php echo $num_nomina_actual; ?>';
var reeup = '<?php echo isset($config_empresa["reeup_empresa"]) ? $config_empresa["reeup_empresa"] : "309-2-1401"; ?>';
var nitEmpresa = '<?php echo isset($config_empresa["nit_empresa"]) ? $config_empresa["nit_empresa"] : "S/R"; ?>';

// Estado de las filas VISIBLES (aplicando búsqueda y filtros) para decidir si se puede
// generar la Nómina Impresa. En ajuste puede haber varios números de nómina en el mismo
// período, por eso se valida por lote y no con el flag global "contabilizada".
window.obtenerEstadoImpresion = function() {
    var $tabla = $('#tablaNominas');
    if (!$tabla.length || !$.fn.DataTable.isDataTable('#tablaNominas')) {
        return { ok: false, motivo: 'vacio' };
    }
    var dt = $tabla.DataTable();
    var nodos = dt.rows({ search: 'applied' }).nodes();
    if (nodos.length === 0) return { ok: false, motivo: 'vacio' };

    var hayBorrador = false;
    var numeros = {};
    $(nodos).each(function() {
        var $fila = $(this);
        if ($fila.has('.badge-borrador').length) hayBorrador = true;
        var num = $fila.find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numeros[num] = true;
    });
    var nums = Object.keys(numeros);
    if (hayBorrador) return { ok: false, motivo: 'borrador' };
    if (nums.length > 1) return { ok: false, motivo: 'multiples', numeros: nums };
    return { ok: true, numero: nums.length === 1 ? nums[0] : null };
};

var nombreMesGlobal = '<?php echo addslashes($nombre_mes); ?>';
var anioGlobal = '<?php echo addslashes($anio); ?>';
var observacionesCierreGlobal = <?php echo json_encode($observaciones_cierre); ?>;
var observacionesCierreGlobalOriginal = observacionesCierreGlobal;
// 🔽 NUEVO: Mapa nómina -> observaciones (AJUSTE) para actualizarlas según el filtro seleccionado
var observacionesPorNomina = <?php echo json_encode($observaciones_por_nomina ?? []); ?>;

// Horas de la jornada diaria (configuración general) para equivalencias en nómina de vacaciones
var horasJornadaDiaria = <?php
    $stmt_hjd = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'horas_jornada_diaria' LIMIT 1");
    echo (int)($stmt_hjd->fetchColumn() ?: 8);
?>;

var idsEnNominaActual = <?php 
    $stmt_ids = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $stmt_ids->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    echo json_encode($stmt_ids->fetchAll(PDO::FETCH_COLUMN));
?>;

var activeTipoDescuento = '<?php 
    $sql_td = "SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?";
    $params_td = [$periodo_desde, $periodo_hasta, $tipo_nomina_activa];
    
    if (!empty($filtro_numero_nomina)) {
        if ($filtro_numero_nomina === 'Borrador') {
            $sql_td .= " AND numero_nomina IS NULL";
        } else {
            $sql_td .= " AND numero_nomina = ?";
            $params_td[] = $filtro_numero_nomina;
        }
    }
    $sql_td .= " LIMIT 1";
    
    $stmt_td = $pdo->prepare($sql_td);
    $stmt_td->execute($params_td);
    echo $stmt_td->fetchColumn() ?: 'total_rangos'; 
?>';

    <?php
    // Inicializar la variable que se pasará a JavaScript
    $monto_distribuido_db = 0.00;
    
    // Solo calcular y pasar el monto si la nómina activa es "bono"
    if ($tipo_nomina_activa == 'bono') {
        $nombre_mes_esp = mb_strtolower(nombreMesEspanol($mes), 'UTF-8');
        
        // Construir la consulta base para obtener el monto a distribuir
        $sql_monto = "SELECT COALESCE(SUM(n.pago_resultado), 0) as total_monto
                      FROM nominas n
                      WHERE n.tipo_nomina = 'bono'
                        AND n.estado = 'contabilizado'
                        AND MONTH(n.periodo_desde) = ?
                        AND YEAR(n.periodo_desde) = ?";
        $params_monto = [intval($mes), intval($anio)];

        // Si hay un filtro por número de nómina, aplicarlo
        // Esto es crucial para que el monto mostrado sea el de esa nómina específica
        if (!empty($filtro_numero_nomina) && $filtro_numero_nomina !== 'Borrador') {
            $sql_monto .= " AND n.numero_nomina = ?";
            $params_monto[] = $filtro_numero_nomina;
        }

        $stmt_monto = $pdo->prepare($sql_monto);
        $stmt_monto->execute($params_monto);
        $total_contabilizado = floatval($stmt_monto->fetchColumn()) ?: 0.00;

        // Si no se encontró ningún bono contabilizado para este período, se usa el monto del borrador
        if ($total_contabilizado <= 0) {
            // Obtener la suma de los bonos en borrador (sin número de nómina)
            $sql_draft = "SELECT COALESCE(SUM(pago_resultado), 0) FROM nominas 
                          WHERE periodo_desde = ? AND periodo_hasta = ? 
                          AND tipo_nomina = 'bono' AND estado = 'borrador'";
            $stmt_draft = $pdo->prepare($sql_draft);
            $stmt_draft->execute([$periodo_desde, $periodo_hasta]);
            $total_borrador = floatval($stmt_draft->fetchColumn()) ?: 0.00;
            
            // El monto a distribuir será el total contabilizado. Si es 0, se mostrará "(Hasta que se contabilice)"
            $monto_distribuido_db = $total_contabilizado;
        } else {
            $monto_distribuido_db = $total_contabilizado;
        }

        // Si el monto sigue siendo 0, significa que no hay bonos contabilizados ni en borrador.
        // Dejamos el valor en 0 para que el JS muestre "(Hasta que se contabilice)"
    }
    ?>
    var montoDistribuidoGlobal = parseFloat('<?php echo $monto_distribuido_db; ?>') || 0.00;


function ordenarTrabajadoresPorAlcance(trabajadores, alcance) {
    if (!trabajadores || trabajadores.length === 0) return trabajadores;
    
    return [...trabajadores].sort((a, b) => {
        let valorA = 0;
        let valorB = 0;
        
        switch (alcance) {
            case 'centro_costo':
                valorA = a.centroCostoCodigo || 9999;
                valorB = b.centroCostoCodigo || 9999;
                break;
                
            case 'cargo':
                valorA = a.cargoId || 9999;
                valorB = b.cargoId || 9999;
                break;
                
            case 'area':
                valorA = a.areaCodigo || 9999;
                valorB = b.areaCodigo || 9999;
                break;
                
            case 'escala':
                valorA = a.escalaNumero || 9999;
                valorB = b.escalaNumero || 9999;
                break;
                
            case 'general':
                valorA = parseInt(a.codigo) || 9999;
                valorB = parseInt(b.codigo) || 9999;
                break;
                
            default:
                // Orden alfabético para otros casos
                let txtA = (a[alcance] || '').toString().toLowerCase();
                let txtB = (b[alcance] || '').toString().toLowerCase();
                if (txtA < txtB) return -1;
                if (txtA > txtB) return 1;
                return 0;
        }
        
        // Comparación numérica
        if (valorA < valorB) return -1;
        if (valorA > valorB) return 1;
        
        // Desempate por nombre
        let nombreA = (a.nombre || '').toLowerCase();
        let nombreB = (b.nombre || '').toLowerCase();
        if (nombreA < nombreB) return -1;
        if (nombreA > nombreB) return 1;
        
        return 0;
    });
}

function obtenerCampoOrden(alcance) {
    switch (alcance) {
        case 'tipo_contrato': return 'tipoContrato';
        case 'categoria': return 'categoria';
        default: return 'nombre';
    }
}


// ==========================================
// SELECTOR PERSONALIZADO DE TIPO DE NÓMINA
// ==========================================

// Abrir/cerrar dropdown al hacer clic en el preview
$(document).on('click', '#tipoNominaPreview', function(e) {
    e.stopPropagation();
    $('.tipo-nomina-selector-custom').toggleClass('open');
});

// Cerrar dropdown al hacer clic fuera
$(document).on('click', function(e) {
    if (!$(e.target).closest('.tipo-nomina-selector-custom').length) {
        $('.tipo-nomina-selector-custom').removeClass('open');
    }
});

// Seleccionar una opción del dropdown
$(document).on('click', '.tipo-nomina-option', function(e) {
    e.stopPropagation();
    var tipo = $(this).data('value');
    var icono = $(this).data('icon');
    var nombre = $(this).find('span').text();
    
    // Actualizar el preview
    $('#tipoNominaPreview i:first-child').attr('class', 'fas ' + icono);
    $('#tipoNominaPreview span').text(nombre);
    
    // Cerrar dropdown
    $('.tipo-nomina-selector-custom').removeClass('open');
    
    // Marcar la opción seleccionada
    $('.tipo-nomina-option').removeClass('selected');
    $(this).addClass('selected');
    
    // Redirigir a la URL con el nuevo tipo
    var periodoActual = '<?php echo $periodo; ?>';
    var filtroCuenta = '<?php echo $filtro_cuenta; ?>';
    var url = 'nominas.php?periodo=' + periodoActual + '&tipo=' + tipo;
    if (filtroCuenta) url += '&filtro_cuenta=' + filtroCuenta;
    window.location.href = url;
});

// Sincronizar el selector con el tipo de nómina actual
function sincronizarSelectorTipoNomina() {
    var tipoActual = '<?php echo $tipo_nomina_activa; ?>';
    var nombreActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var iconoActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['icono']; ?>';
    
    // Actualizar el preview
    $('#tipoNominaPreview i:first-child').attr('class', 'fas ' + iconoActual);
    $('#tipoNominaPreview span').text(nombreActual);
    
    // Marcar la opción seleccionada
    $('.tipo-nomina-option').removeClass('selected');
    $('.tipo-nomina-option[data-value="' + tipoActual + '"]').addClass('selected');
}

// Llamar a la sincronización cuando cargue la página
$(document).ready(function() {
    sincronizarSelectorTipoNomina();
});


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


// ==========================================
// LÓGICA PRINCIPAL DE NÓMINAS
// ==========================================

// ==========================================
// VARIABLE GLOBAL PARA ALMACENAR DATOS DEL HISTORIAL
// ==========================================
let datosHistorialCompleto = [];

// ==========================================
// FUNCIÓN PARA CARGAR EL HISTORIAL COMPLETO
// ==========================================
function cargarHistorialMontos() {
    Swal.fire({
        title: 'Cargando Base de Datos...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: 'white'
    });

    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: { action: 'get_full_historial_bonos', ajax: 1 },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                // Guardar datos globalmente
                datosHistorialCompleto = response.historial;
                
                // Cargar años en el combo
                cargarAniosEnCombo();
                
                // Mostrar todos los datos inicialmente
                filtrarHistorialPorAnio();
                
                // Abrir el modal
                new bootstrap.Modal(document.getElementById('modalFullHistorial')).show();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.error || 'No se pudieron cargar los datos',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: 'white'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire({
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor: ' + error,
                icon: 'error',
                background: '#1a1a2e',
                color: 'white'
            });
        }
    });
}

// ==========================================
// FUNCIÓN PARA CARGAR AÑOS EN EL COMBO
// ==========================================
function cargarAniosEnCombo() {
    const select = document.getElementById('filtroAnio');
    if (!select) return;
    
    // Obtener años únicos de los datos
    const años = [...new Set(datosHistorialCompleto.map(item => item.anio))];
    
    // Ordenar de más reciente a más antiguo
    años.sort((a, b) => b - a);
    
    // Limpiar opciones existentes (excepto "Todos")
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Agregar años al combo
    años.forEach(anio => {
        const option = document.createElement('option');
        option.value = anio;
        option.textContent = anio;
        option.style.background = '#1e1e26';
        option.style.color = '#fff';
        select.appendChild(option);
    });
}

// ==========================================
// FUNCIÓN PARA FILTRAR POR AÑO
// ==========================================
function filtrarHistorialPorAnio() {
    const select = document.getElementById('filtroAnio');
    const anioSeleccionado = select ? select.value : 'todos';
    const tbody = document.querySelector('#tablaFullHistorial tbody');
    const totalElement = document.getElementById('totalFullHistorial');
    
    if (!tbody) return;
    
    // Filtrar datos
    let datosFiltrados = [];
    if (anioSeleccionado === 'todos') {
        datosFiltrados = datosHistorialCompleto;
    } else {
        datosFiltrados = datosHistorialCompleto.filter(item => 
            parseInt(item.anio) === parseInt(anioSeleccionado)
        );
    }
    
    // Limpiar tabla
    tbody.innerHTML = '';
    
    // Si no hay datos, mostrar mensaje
    if (datosFiltrados.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-white-50 py-3">
                    <i class="fas fa-info-circle me-2"></i>No hay registros para este año
                </td>
            </tr>
        `;
        totalElement.textContent = '$0.00';
        return;
    }
    
    // Llenar tabla con datos filtrados
    let total = 0;
    datosFiltrados.forEach(item => {
        const importe = parseFloat(item.importe_dis) || 0;
        total += importe;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="py-2 text-center">${item.anio}</td>
            <td class="py-2 text-capitalize">${item.mes}</td>
            <td class="py-2 text-end fw-bold text-info">$${importe.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        `;
        tbody.appendChild(tr);
    });
    
    // Actualizar total
    totalElement.textContent = '$' + total.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

//cargarHistorialMontos();

// ==========================================
// FUNCIÓN PARA LIMPIAR FILTRO
// ==========================================
function limpiarFiltroAnio() {
    const select = document.getElementById('filtroAnio');
    if (select) {
        select.value = 'todos';
        filtrarHistorialPorAnio();
    }
}




$(document).ready(function() {
// --- Cargar Base Completa ---
    $('#btnFullHistorialBonos').on('click', function() {
        Swal.fire({
            title: 'Cargando Base de Datos...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            background: '#1a1a2e',
            color: 'white'
        });

        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: { action: 'get_full_historial_bonos', ajax: 1 },
            dataType: 'json',
            success: function(r) {
                Swal.close();
                if (r.success) {
                    let html = '';
                    let total = 0;
                    r.historial.forEach(h => {
                        let imp = parseFloat(h.importe_dis) || 0;
                        total += imp;
                        html += `
                        <tr>
                            <td class="py-2">${h.anio}</td>
                            <td class="py-2 text-capitalize">${h.mes}</td>
                            <td class="py-2 text-end fw-bold text-info">$${imp.toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                        </tr>`;
                    });
                    
                    $('#tablaFullHistorial tbody').html(html || '<tr><td colspan="3" class="text-center">No hay registros</td></tr>');
                    $('#totalFullHistorial').text('$' + total.toLocaleString('en-US',{minimumFractionDigits:2}));
                    
                    new bootstrap.Modal(document.getElementById('modalFullHistorial')).show();
                }
            }
        });
    });

// --- Imprimir Historial con Formato Oficial ---
$('#btnImprimirFull').on('click', function() {
    const tableBody = $('#tablaFullHistorial tbody').html();
    const totalValue = $('#totalFullHistorial').text();
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES');
    
    // Obtener el filtro seleccionado
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : `AÑO ${filtroSeleccionado}`;

    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Historial de Montos Distribuidos - ${nombreEmpresa}</title>
            <style>
                @page { size: portrait; margin: 15mm; }
                body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
                
                /* Estilo de Cabecera similar a SC-4-06 */
                .header-container { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-bottom: 20px; }
                .header-container td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
                .logo-cell { width: 60px; text-align: center; }
                .title-cell { text-align: center; font-weight: bold; font-size: 12pt; }
                .meta-cell { font-size: 8pt; width: 180px; }

                /* Tabla de Datos */
                .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .main-table th { background-color: #004B87; color: #ffffff; font-weight: bold; padding: 8px; border: 1px solid #000; text-align: center; -webkit-print-color-adjust: exact; }
                .main-table td { border: 1px solid #000; padding: 6px 10px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .text-capitalize { text-transform: capitalize; }
                
                /* Fila de Total */
                .total-row { background-color: #f0f4f8; font-weight: bold; }
                .total-row td { border-top: 2px solid #000; color: #004B87; }

                /* Bloque de Firmas Oficiales */
                .signature-section { margin-top: 50px; page-break-inside: avoid; }
                .signature-table { width: 100%; border: none !important; border-collapse: collapse; }
                .signature-table td { border: none !important; width: 25%; text-align: center; padding: 10px; font-size: 8.5pt; vertical-align: top; }
                .sig-line { border-top: 1px solid #000; width: 90%; margin: 45px auto 5px auto; }
                .sig-name { font-weight: bold; display: block; }
                .sig-label { color: #444; font-size: 7.5pt; }

                .footer-info { margin-top: 15px; font-size: 8pt; color: #666; text-align: center; border-top: 0.5pt solid #eee; padding-top: 5px; }
                
                /* Estilo para el filtro en el encabezado */
                .filtro-info { background-color: #f8f9fa; font-weight: bold; color: #004B87; padding: 4px 10px; border-radius: 4px; display: inline-block; }
            </style>
        </head>
        <body>
            <!-- Encabezado Oficial -->
            <table class="header-container">
                <tr>
                    <td class="logo-cell">
                        ${logoBase64 ? `<img src="${logoBase64}" width="50">` : ''}
                    </td>
                    <td class="title-cell">
                        REGISTRO DE BASE DE DATOS DE MONTOS DISTRIBUIDOS<br>
                        <span style="font-size: 10pt;">${nombreEmpresa.toUpperCase()}</span>
                    </td>
                    <td class="meta-cell">
                        <strong>Emisión:</strong> ${fechaHora}<br>
                        <strong>REEUP:</strong> ${reeup}<br>
                        <strong>NIT:</strong> ${nitEmpresa}
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin-bottom: 10px;">
                <h4 style="margin: 0;">HISTORIAL CRONOLÓGICO DE IMPORTES PARA PRODUCTIVIDAD</h4>
                <div style="margin-top: 5px;">
                    <span class="filtro-info">🔍 FILTRO APLICADO: ${filtroTexto}</span>
                </div>
            </div>

            <!-- Tabla de Datos -->
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">AÑO</th>
                        <th style="width: 40%;">MES CORRESPONDIENTE</th>
                        <th style="width: 40%;" class="text-end">IMPORTE REGISTRADO ($)</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableBody}
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="text-end">TOTAL ACUMULADO HISTÓRICO:</td>
                        <td class="text-end" style="font-size: 12pt;">${totalValue}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Bloque de Firmas -->
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td>
                            <p><b>Elaborado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-label">Especialista de Nóminas</span>
                        </td>
                        <td>
                            <p><b>Revisado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-name">${especialistaGestion}</span>
                            <span class="sig-label">Especialista en Gestión Económica</span>
                        </td>
                        <td>
                            <p><b>Aprobado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-name">${jefeProyecto}</span>
                            <span class="sig-label">Director de Proyecto</span>
                        </td>
                        <td>
                            <p><b>Contabilizado por:</b></p>
                            <div class="sig-line"></div>
                            <span class="sig-label">Área Contable y Financiera</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer-info">
                Documento generado por el Sistema de Gestión de Nóminas - Usuario: ${usuarioNombre}
            </div>

            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    }, 300);
                }
            <\/script>
        </body>
        </html>
    `);
    win.document.close();
});

// --- Exportar PDF del Historial con Formato Oficial SC-4-06 ---
$('#btnExportPDFFull').on('click', function() {
    const now = new Date();
    const fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES');
    const totalAcumulado = $('#totalFullHistorial').text();
    
    // Obtener el filtro seleccionado
    const filtroSelect = document.getElementById('filtroAnio');
    const filtroSeleccionado = filtroSelect ? filtroSelect.value : 'todos';
    const filtroTexto = filtroSeleccionado === 'todos' ? 'TODOS LOS AÑOS' : `AÑO ${filtroSeleccionado}`;

    // 1. Extraer los datos de la tabla HTML
    let tablaDatos = [];
    // Encabezado de la tabla de datos
    tablaDatos.push([
        { text: 'AÑO', style: 'tableHeader' },
        { text: 'MES CORRESPONDIENTE', style: 'tableHeader' },
        { text: 'IMPORTE REGISTRADO ($)', style: 'tableHeader', alignment: 'right' }
    ]);

    // Filas de la tabla
    $('#tablaFullHistorial tbody tr').each(function() {
        const anio = $(this).find('td:eq(0)').text();
        const mes = $(this).find('td:eq(1)').text();
        const importe = $(this).find('td:eq(2)').text();
        
        tablaDatos.push([
            { text: anio, style: 'tableCell', alignment: 'center' },
            { text: mes.toUpperCase(), style: 'tableCell' },
            { text: importe, style: 'tableCell', alignment: 'right' }
        ]);
    });

    // 2. Definición del Documento para pdfMake
    const docDefinition = {
        pageSize: 'LETTER',
        pageMargins: [30, 30, 30, 40],
        content: [
            // CABECERA OFICIAL (Logo, Título, Metadatos)
            {
                table: {
                    widths: [50, '*', 160],
                    body: [
                        [
                            logoBase64 ? { image: logoBase64, width: 40, alignment: 'center' } : { text: '' },
                            {
                                stack: [
                                    { text: 'REGISTRO DE BASE DE DATOS DE MONTOS DISTRIBUIDOS', fontSize: 11, bold: true, alignment: 'center' },
                                    { text: nombreEmpresa.toUpperCase(), fontSize: 9, alignment: 'center', margin: [0, 2, 0, 0] },
                                    { text: 'HISTORIAL CRONOLÓGICO DE PRODUCTIVIDAD', fontSize: 8, color: '#444', alignment: 'center', margin: [0, 2, 0, 0] },
                                    { 
                                        text: `🔍 FILTRO APLICADO: ${filtroTexto}`, 
                                        fontSize: 8, 
                                        bold: true, 
                                        color: '#004B87',
                                        alignment: 'center',
                                        margin: [0, 4, 0, 0]
                                    }
                                ]
                            },
                            {
                                stack: [
                                    { text: `Emisión: ${fechaHora}`, fontSize: 7 },
                                    { text: `REEUP: ${reeup}`, fontSize: 7, margin: [0, 2, 0, 0] },
                                    { text: `NIT: ${nitEmpresa}`, fontSize: 7, margin: [0, 2, 0, 0] }
                                ],
                                alignment: 'left'
                            }
                        ]
                    ]
                },
                layout: {
                    hLineWidth: () => 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000'
                }
            },
            { text: ' ', margin: [0, 10] }, // Espaciador

            // TABLA DE DATOS
            {
                table: {
                    headerRows: 1,
                    widths: ['20%', '45%', '35%'],
                    body: tablaDatos
                },
                layout: {
                    hLineWidth: (i, node) => (i === 0 || i === node.table.body.length) ? 1 : 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000',
                    paddingTop: () => 4,
                    paddingBottom: () => 4
                }
            },

            // FILA DE TOTAL
            {
                table: {
                    widths: ['65%', '35%'],
                    body: [
                        [
                            { text: 'TOTAL ACUMULADO HISTÓRICO:', alignment: 'right', bold: true, fontSize: 10, fillColor: '#f0f4f8' },
                            { text: totalAcumulado, alignment: 'right', bold: true, fontSize: 11, color: '#004B87', fillColor: '#f0f4f8' }
                        ]
                    ]
                },
                layout: {
                    hLineWidth: () => 1,
                    vLineWidth: () => 0.5,
                    hLineColor: () => '#000',
                    vLineColor: () => '#000'
                }
            },

            // BLOQUE DE FIRMAS (Idéntico a SC-4-06)
            {
                margin: [0, 50, 0, 0],
                unbreakable: true,
                table: {
                    widths: ['25%', '25%', '25%', '25%'],
                    body: [
                        [
                            { text: 'Elaborado por:', style: 'sigLabel' },
                            { text: 'Revisado por:', style: 'sigLabel' },
                            { text: 'Aprobado por:', style: 'sigLabel' },
                            { text: 'Contabilizado por:', style: 'sigLabel' }
                        ],
                        [
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' },
                            { text: '\n\n______________________', alignment: 'center' }
                        ],
                        [
                            { text: 'Especialista de Nóminas', style: 'sigRole' },
                            { text: especialistaGestion, style: 'sigName' },
                            { text: jefeProyecto, style: 'sigName' },
                            { text: 'Área Contable y Financiera', style: 'sigRole' }
                        ],
                        [
                            { text: '', style: 'sigRole' },
                            { text: 'Especialista en Gestión Económica', style: 'sigRole' },
                            { text: 'Director de Proyecto', style: 'sigRole' },
                            { text: '', style: 'sigRole' }
                        ]
                    ]
                },
                layout: 'noBorders'
            }
        ],
        styles: {
            tableHeader: {
                fillColor: '#004B87',
                color: '#ffffff',
                bold: true,
                fontSize: 9,
                margin: [0, 2, 0, 2]
            },
            tableCell: {
                fontSize: 8.5
            },
            sigLabel: {
                fontSize: 8.5,
                bold: true,
                alignment: 'center'
            },
            sigName: {
                fontSize: 8.5,
                bold: true,
                alignment: 'center',
                margin: [0, 2, 0, 0]
            },
            sigRole: {
                fontSize: 7.5,
                color: '#444',
                alignment: 'center'
            }
        },
        footer: function(currentPage, pageCount) {
            return {
                text: `Sistema de Gestión de Nóminas - Página ${currentPage} de ${pageCount} - Usuario: ${usuarioNombre}`,
                fontSize: 7,
                alignment: 'center',
                margin: [0, 10, 0, 0]
            };
        }
    };

    // 3. Generar y Descargar
    pdfMake.createPdf(docDefinition).download(`Historial_Montos_${now.getTime()}.pdf`);
});
	
	
// =========================================================================
// FUNCIONES AUXILIARES DE MAPEO UNIFICADO PARA EXPORTACIONES
// =========================================================================

// Extrae el valor numérico limpio de una celda, detectando si contiene un input
function parseCell($el) {
    if (!$el.length) return 0;
    
    // Si el elemento en sí es un input
    if ($el.is('input')) {
        return parseNumber($el.val());
    }
    
    // Si contiene un input adentro (caso de celdas editables en borrador)
    var $inputInside = $el.find('input');
    if ($inputInside.length) {
        return parseNumber($inputInside.val());
    }
    
    // Caso estándar (texto plano)
    return parseNumber($el.text());
}

// Mapea una fila completa del DataTable a un objeto unificado de Trabajador
function mapRowToTrabajador($row) {
    var catCod = $row.data('categoria-codigo') || '';
    var catNom = $row.data('categoria-nombre') || '';
    var categoriaFull = (catCod && catNom) ? (catCod + ' - ' + catNom) : (catCod || catNom || '-');

    // 1. Horas / Días
    var horasVal = 0;
    var diasTomadosVal = 0;
    if (tipoNomina === 'vacaciones') {
        // Días de vacaciones pagados/rebajados en la nómina
        diasTomadosVal = parseCell($row.find('.edit-dias')) || parseCell($row.find('.col-horas').first());
        // Horas equivalentes = Días × jornada diaria (configurable)
        horasVal = Math.roundExcel(diasTomadosVal * (horasJornadaDiaria || 8), 2);
    } else {
        horasVal = parseCell($row.find('.edit-horas')) || parseCell($row.find('.col-horas'));
    }

    // 2. Salarios Básicos y Tarifas
    var salarioMensual = parseNumber($row.attr('data-salario-mensual') || $row.data('salario-mensual')) || 0;
    var tarifaSal = parseNumber($row.data('salario-hora')) || 0;

    // 3. A cobrar / Importe Salario Laboral
    var aCobrar = 0;
    if (tipoNomina === 'bono') {
        aCobrar = parseCell($row.find('.edit-bono')) || parseCell($row.find('.bono-val-cell'));
    } else if (tipoNomina === 'vacaciones') {
        aCobrar = parseCell($row.find('.total-devengado'));
    } else {
        aCobrar = parseCell($row.find('.salario-laboral'));
    }

    // 4. Bono / Otros Pagos
    var bono = 0;
    if (tipoNomina === 'bono') {
        bono = aCobrar;
    } else {
        bono = parseCell($row.find('.edit-otros-pagos')) || parseCell($row.find('.col-otros-pagos'));
    }

    // 🔽 NUEVO: Obtener el concepto/descripción (para BONO y AJUSTE)
    var concepto = '';
    if (tipoNomina === 'bono' || tipoNomina === 'ajuste') {
        // Buscar en la columna de concepto (segunda columna .col-nombre)
        var $colsNombre = $row.find('.col-nombre');
        if ($colsNombre.length > 1) {
            concepto = $colsNombre.eq(1).text().trim();
        } else {
            // Fallback: buscar en la columna 7 (índice 7)
            concepto = $row.find('td').eq(7).text().trim();
        }
        if (concepto === '-' || concepto === '') {
            concepto = 'Sin concepto';
        }
    }

    // 5. Descuentos / Otros Descuentos
    var descuentos = parseCell($row.find('.edit-descuentos')) || parseCell($row.find('.col-otros-descuentos'));

    // 6. Devengado
    var devengado = parseCell($row.find('.total-devengado'));

    // 7. Contribución CESS
    var impS = parseCell($row.find('.contribucion'));

    // 8. Retenciones / Total Deducciones
    var retenciones = parseCell($row.find('.total-deducciones'));

    // 9. Pagado / Neto
    var pagado = parseCell($row.find('.neto'));

    // 10. Vacaciones y Feriados
    var vacDias = 0;
    if (tipoNomina === 'vacaciones') {
        // Saldo restante de vacaciones en la BD (tabla trabajadores.vacaciones_acumuladas)
        vacDias = parseNumber($row.data('dias-acumulados')) || 0;
    } else {
        vacDias = parseCell($row.find('.vacaciones-dias'));
    }
    var tiempoImp = parseCell($row.find('.vacations-importe'));
    var feriadoImp = parseCell($row.find('.feriados-importe'));

    return {
        codigo: $row.find('td:eq(0)').text().trim(),
        ci: $row.find('td:eq(1)').text().trim(),
        nombre: $row.find('td:eq(2)').text().trim(),
        area: $row.data('area') || $row.find('td:eq(3)').text().trim(),
        cargoId: $row.data('cargo-id') || 0,
        cargo: $row.find('td:eq(4)').text().trim(),
        centroCosto: $row.data('centro-costo') || $row.find('td:eq(5)').text().trim(),
        centroCostoCodigo: parseInt($row.data('centro-costo-codigo')) || 9999,
        categoria: categoriaFull,
        categoriaCodigo: catCod || '-',
        escala: $row.data('escala-romana') || '-',
        escalaNumero: $row.data('escala-numero') || 0,
        escalaDescripcion: $row.data('escala-descripcion') || '-',
        tipoContrato: $row.data('tipo-contrato') || '',
        tarifaSal: tarifaSal,
        horas: horasVal,
        aCobrar: aCobrar,
        bono: bono,
        concepto: concepto, // 🔽 NUEVO: Se añade el concepto
        devengado: devengado,
        impS: impS,
        retenciones: retenciones,
        descuentos: descuentos,
        pagado: pagado,
        vacDias: vacDias,
        tiempoImp: tiempoImp,
        feriadoImp: feriadoImp,
        vacAcumDias: parseNumber($row.data('dias-acumulados')) || 0,
        salarioMensual: salarioMensual,
        firma: ''
    };
}
	
	// Llamar a la sincronización cuando cargue la página
    sincronizarSelectorTipoNomina();

	// Escuchar parámetro de impresión automática desde el Dashboard
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('abrir_impresion') === '1') {
        // Esperar un breve momento a que DataTables renderice las columnas fijas
        setTimeout(function() {
            // Ejecutar el clic programático en el botón de impresión oficial
            $('#menuNominaImpresa').trigger('click');
            
            // Limpiar el parámetro de la URL para evitar que se reabra al recargar la página manualmente
            urlParams.delete('abrir_impresion');
            var nuevaUrl = window.location.pathname + '?' + urlParams.toString();
            window.history.replaceState({}, document.title, nuevaUrl);
        }, 600);
    }

// ==========================================
// MODAL DE AJUSTE
// ==========================================
var selectedAjuste = [];

function renderAjusteWorkerList(term = '') {
    var areaVal = $('#filterAjusteArea').val();
    var ccVal = $('#filterAjusteCC').val();
    var html = '';
    
    var filtered = trabajadores.filter(w => {
        var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
        var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
        var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
        return matchesSearch && matchesArea && matchesCC;
    });
    
    filtered.forEach(w => {
        var sel = selectedAjuste.some(s => s.id == w.id);

        // Resolución del path de la foto
        var workerFotoUrl = '';
        if (w.foto_ruta && w.foto_ruta.trim() !== '') {
            workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
        }

        var avatarHtml = '';
        if (workerFotoUrl) {
            avatarHtml = `<img src="${workerFotoUrl}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
        } else {
            avatarHtml = `<i class="fas fa-user"></i>`;
        }

        html += `<div class="worker-item ${sel?'selected':''}" data-id="${w.id}" data-nombre="${w.nombre_completo}" data-codigo="${w.codigo}" data-salario="${w.salario_mensual}" data-foto-ruta="${w.foto_ruta || ''}">
            <input type="checkbox" class="worker-checkbox" ${sel?'checked':''}>
            <div class="worker-avatar">${avatarHtml}</div>
            <div class="worker-info">
                <div class="worker-name">${w.codigo} - ${w.nombre_completo}</div>
                <div class="worker-detail"><i class="fas fa-wallet me-1"></i>Salario Básico: $${parseFloat(w.salario_mensual).toFixed(2)}</div>
            </div>
        </div>`;
    });
    $('#ajusteWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
}

function updateAjusteList() {
    var html = selectedAjuste.map(w => {
        var montoVal = w.monto !== undefined ? w.monto : '';
        return `
            <div class="selected-worker-card" data-id="${w.id}">
                <div class="selected-worker-header">
                    <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                    <button type="button" class="ajuste-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                </div>
                <div class="row g-2 align-items-center mt-2">
                    <div class="col-md-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" class="form-control bg-dark border-secondary text-white text-center ajuste-monto-input" 
                                   data-id="${w.id}" value="${montoVal}" step="0.01" min="0" placeholder="Monto del Ajuste">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#ajusteSelectedList').html(html || '<em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista</em>');
    updateAjusteTotals();
}

function updateAjusteTotals() {
    var tDev = 0, val = 0;
    selectedAjuste.forEach(w => { 
        var m = parseFloat(w.monto) || 0; 
        if(m > 0){ tDev += m; val++; } 
    });
    
    if(val > 0){
        $('#previewAjusteCount').text(val);
        $('#previewAjusteTotal').text('$' + tDev.toFixed(2));
        $('#previewAjuste').show();
        $('#btnGenerarAjuste').prop('disabled', false);
    } else {
        $('#previewAjuste').hide();
        $('#btnGenerarAjuste').prop('disabled', true);
    }
}

function recalcularPreviewAjuste() {
    var monto = parseFloat($('#editMontoAjuste').val()) || 0;
    var descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
    var $fila = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]');
    var tipoDescuento = $fila.data('tipo-descuento') || 'total_rangos';
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(monto);
    } else {
        contribucion = Math.roundExcel(monto * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(monto);
    }
    var netoAntesDescuentos = Math.roundExcel(monto - (contribucion + impuesto), 2);
    if (descuentos > netoAntesDescuentos) descuentos = netoAntesDescuentos;
    if (descuentos < 0) descuentos = 0;
    var totalDeducciones = Math.roundExcel(contribucion + impuesto + descuentos, 2);
    var neto = Math.roundExcel(netoAntesDescuentos - descuentos, 2);
    
    $('#previewDevengado').text('$' + monto.toFixed(2));
    $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
    $('#previewNeto').text('$' + Math.max(0, neto).toFixed(2));
}

$(document).on('click', '#ajusteWorkerList .worker-item', function(){
    var id = $(this).data('id'), idx = selectedAjuste.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedAjuste.splice(idx,1); 
    } else {
        selectedAjuste.push({
            id: id, 
            nombre: $(this).data('nombre'), 
            codigo: $(this).data('codigo'), 
            salario: parseFloat($(this).data('salario')) || 0,
            monto: ''
        });
    }
    renderAjusteWorkerList($('#searchAjusteWorker').val()); 
    updateAjusteList();
});

$(document).on('input', '.ajuste-monto-input', function() {
    var id = $(this).data('id');
    var valMonto = parseFloat($(this).val()) || 0;
    var w = selectedAjuste.find(s => s.id == id);
    if (w) {
        w.monto = valMonto;
        updateAjusteTotals();
    }
});

$(document).on('click', '.ajuste-item-remove', function(){
    selectedAjuste.splice(selectedAjuste.findIndex(s => s.id == $(this).data('id')), 1); 
    renderAjusteWorkerList($('#searchAjusteWorker').val()); 
    updateAjusteList();
});

$('#modalAjuste').on('show.bs.modal', function(){ 
    selectedAjuste=[]; 
    renderAjusteWorkerList(); 
    updateAjusteList(); 
    $('#searchAjusteWorker').val(''); 
    $('#conceptoAjuste').val(''); 
    
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoAjuste').val(td);
    
    window.tempSelectedDiscount = null;
});

$('#formAjuste').on('submit', function(e){
    // Limpiar inputs previos dinámicos
    $(this).find('input[name="trabajador_id[]"], input[name="monto_ajuste[]"]').remove();
    
    if(!$('#conceptoAjuste').val().trim()){ 
        e.preventDefault(); 
        Swal.fire({
            title: 'Error', 
            text: 'Ingrese un concepto para el ajuste.', 
            icon: 'error', 
            background: '#1a1a2e', 
            color: 'white', 
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        }); 
        return false; 
    }

    if (selectedAjuste.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Sin Selección',
            text: 'Debe seleccionar al menos un trabajador para el ajuste.',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return false;
    }

    var incompleteWorker = null;
    var valid = false;

    selectedAjuste.forEach(w => {
        var m = parseFloat(w.monto) || 0;
        if (m <= 0) {
            incompleteWorker = w.nombre;
        } else {
            $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="monto_ajuste[]" value="${m}">`);
            valid = true;
        }
    });

    if (incompleteWorker) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto Requerido',
            html: `Por favor, asigne un monto de ajuste válido y mayor a cero para el trabajador:<br><strong>${escapeHtml(incompleteWorker)}</strong>`,
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Corregir'
        });
        return false;
    }

    $(this).append('<input type="hidden" name="confirmar_ajuste" value="1">'); 
    return true;
});

// ==========================================
// SELECTOR DE CONSULTA RÁPIDA (Mover aquí el contenido del segundo ready)
// ==========================================

// Función actualizarMesesConsulta ya existe arriba, no la redefinas.

$('#consultaAnioSelect, #consultaEstadoSelect, #consultaTipoSelect').on('change', function() {
    $('#consultaNumeroSelect').html('<option value="">-- Seleccione mes --</option>').prop('disabled', true);
    actualizarMesesConsulta(); // Esta función ya está definida antes en el mismo bloque
});

$('#consultaMesSelect').on('change', function() {
    var anio = $('#consultaAnioSelect').val();
    var mes = $(this).val();
    var tipo = $('#consultaTipoSelect').val();
    var estado = $('#consultaEstadoSelect').val();
    var numSelect = $('#consultaNumeroSelect');

    if (!mes) {
        numSelect.html('<option value="">-- Seleccione mes --</option>').prop('disabled', true);
        return;
    }

    numSelect.html('<option value="">Cargando números...</option>').prop('disabled', true);

    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: {
            action: 'get_numeros_nominas',
            anio: anio,
            mes: mes,
            tipo: tipo,
            estado: estado,
            ajax: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.numeros.length > 0) {
                var options = '<option value="">-- Todas las corridas --</option>';
                response.numeros.forEach(function(num) {
                    options += `<option value="${num}">${num}</option>`;
                });
                numSelect.html(options).prop('disabled', false);

                // Preseleccionar si hay un número en la URL
                var urlParams = new URLSearchParams(window.location.search);
                var numeroActual = urlParams.get('numero_nomina');
                if (numeroActual && response.numeros.includes(numeroActual)) {
                    numSelect.val(numeroActual);
                }
            } else {
                numSelect.html('<option value="">-- Ninguno disponible --</option>').prop('disabled', true);
            }
        },
        error: function() {
            numSelect.html('<option value="">-- Error al cargar --</option>').prop('disabled', true);
        }
    });
});

$('#consultaRapidaBtn').on('click', function() {
    var anio = $('#consultaAnioSelect').val();
    var mes = $('#consultaMesSelect').val();
    var tipo = $('#consultaTipoSelect').val();
    var estado = $('#consultaEstadoSelect').val();
    var numero = $('#consultaNumeroSelect').val();

    if (!anio || !mes) {
        Swal.fire({
            title: 'Selección incompleta',
            text: 'Debe seleccionar un año y un mes para consultar.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }

    var periodo = anio + '-' + mes;
    var url = 'nominas.php?periodo=' + periodo + '&tipo=' + (tipo || tipoNomina);
    if (estado) url += '&estado=' + estado;
    if (numero && numero !== '') url += '&numero_nomina=' + encodeURIComponent(numero);
    var filtroCuentaActual = '<?php echo $filtro_cuenta; ?>';
    if (filtroCuentaActual) url += '&filtro_cuenta=' + filtroCuentaActual;
    window.location.href = url;
});

// Si el año ya está seleccionado al cargar, actualizar meses
if ($('#consultaAnioSelect').val()) {
    actualizarMesesConsulta();
}

   // Evitar que el foco se quede atrapado en elementos internos cuando un modal se oculta (WAI-ARIA Fix)
    $('.modal').on('hide.bs.modal', function () {
        if (document.activeElement && $(this).has(document.activeElement).length) {
            document.activeElement.blur();
        }
    });
	
	
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var rangosImpuesto = <?php echo json_encode($rangos_impuesto); ?>;
    //var tipoNomina = '<?php echo $tipo_nomina_activa; ?>';
	var existeNomina = <?php echo $existe_nomina ? 'true' : 'false'; ?>;
	var contabilizada = <?php echo $contabilizada ? 'true' : 'false'; ?>;
	var periodoActual = '<?php echo $nombre_mes . ' ' . $anio; ?>';
	var tipoNominaActual = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var selectedWorkers = [];
    var selectedBonos = [];
    var selectedExtra = [];
    var idsConNomina = <?php echo json_encode($ids_con_nomina_vacaciones); ?>;
    var trabajadores = <?php echo json_encode($trabajadores); ?>;
    
    var filtroCuentaActual = '<?php echo $filtro_cuenta; ?>';

// Inicializar estado de los filtros (habilitados/deshabilitados según valores iniciales)
inicializarEstadoFiltros();

// Botón Actualizar Página (recarga la misma URL)
$('#btnActualizarPagina').on('click', function(e) {
    e.preventDefault();
    window.location.href = 'nominas.php';
});

// Botón Regresar a Inicio (va a nominas.php sin parámetros)
$('#btnRegresarInicio').on('click', function(e) {
    e.preventDefault();
    window.location.href = '../dashboard.php';
});

// Botón seleccionar todos los trabajadores disponibles
$('#btnSeleccionarTodosAuto').on('click', function() {
    if (trabajadoresDisponibles.length === 0) {
        Swal.fire({
            title: 'Sin trabajadores disponibles',
            text: 'No hay trabajadores disponibles para agregar a la nómina.',
            icon: 'info',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    trabajadoresSeleccionadosAuto = [];
    
    trabajadoresDisponibles.forEach(function(t) {
        trabajadoresSeleccionadosAuto.push({
            id: t.id,
            nombre: t.nombre_completo,
            codigo: t.codigo,
            salario_mensual: t.salario_mensual,
            salario_hora: t.salario_hora_ordinaria,
            area: t.nombre_area || 'Sin área',
            centro_costo: (t.centro_costo_codigo && t.centro_costo_nombre) ? 
                t.centro_costo_codigo + ' - ' + t.centro_costo_nombre : 'Sin CC'
        });
    });
    
    renderAutoWorkerList();
    
    Swal.fire({
        title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Selección completa',
        html: `Se seleccionaron <strong>${trabajadoresSeleccionadosAuto.length}</strong> trabajadores para agregar a la nómina.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#ffffff'
    });
});

// Evento para el botón "Seleccionar todos" dentro de la lista
$(document).on('click', '.btn-seleccionar-todos-sm', function() {
    $('#btnSeleccionarTodosAuto').click();
});

    // Previene de forma absoluta la entrada de valores negativos en tiempo real (tabla y modal)
    $(document).on('input keyup paste', 
        '#editHoras, #editNocturnas, #editFeriados, #editOtrosPagos, #editDescuentos, #editMontoBono, #editDias, ' +
        '.edit-horas, .edit-nocturnas, .edit-feriados, .edit-descuentos, .edit-otros-pagos, .edit-bono, .edit-dias', 
        function() {
            var $el = $(this);
            var valorTxt = $el.val();

            if (valorTxt.indexOf('-') !== -1) {
                $el.val(valorTxt.replace(/-/g, ''));
            }

            var valorNum = parseFloat($el.val());
            if (valorNum < 0 || isNaN(valorNum)) {
                if ($el.val() !== '') {
                    $el.val(0);
                }
            }
        }
    );
// =======================================================
// GENERAR IMPRESIÓN SEGÚN ALCANCE SELECCIONADO
// =======================================================
$('#btnGenerarImpresion').on('click', function() {
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    const action = $(this).attr('data-action') || 'imprimir';
    let filtroValor = null;
    let filtroNombre = null;

    // IMPORTANTE: Para 'tirillas' NO se necesita filtro de selector
    if (alcance !== 'general' && alcance !== 'tirillas') {
        filtroValor = $('#selectImpresion').val();
        filtroNombre = $('#selectImpresion option:selected').text();
        
        if (filtroValor === '') {
            filtroNombre = 'Todos';
        } else if (!filtroValor) {
            Swal.fire('Error', 'Debe seleccionar un valor para el filtro.', 'error');
            return;
        }
    }

    let isTodos = (filtroValor === ''); 

    // Obtener los nodos visibles del DataTable
    const dataTable = $('#tablaNominas').DataTable();
    const filas = dataTable.rows({ search: 'applied' }).nodes();
    let trabajadoresFiltrados = [];

    // Usar el número de nómina del lote visible para la cabecera del reporte
    var numerosVisibles = {};
    $(filas).each(function() {
        var num = $(this).find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numerosVisibles[num] = true;
    });
    var numKeys = Object.keys(numerosVisibles);
    if (numKeys.length === 1) numeroNomina = numKeys[0];

    $(filas).each(function() {
        const $row = $(this);
        let incluir = true;

        const areaId = $row.data('area-id');
        const centroId = $row.data('centro-costo-id');
        const categoriaId = $row.data('categoria-ocupacional-id');
        const escalaId = $row.data('escala-id');
        const tipoContrato = $row.data('tipo-contrato');
        const cargoId = $row.data('cargo-id');

        switch (alcance) {
            case 'area':
                incluir = (filtroValor === '' || areaId == filtroValor);
                break;
            case 'centro_costo':
                incluir = (filtroValor === '' || centroId == filtroValor);
                break;
            case 'categoria':
                incluir = (filtroValor === '' || categoriaId == filtroValor);
                break;
            case 'escala':
                incluir = (filtroValor === '' || escalaId == filtroValor);
                break;
            case 'tipo_contrato':
                incluir = (filtroValor === '' || tipoContrato == filtroValor);
                break;
            case 'cargo':
                incluir = (filtroValor === '' || cargoId == filtroValor);
                break;
            default:
                incluir = true;
        }

		if (incluir) {
					// Mapeo unificado y seguro de campos
					const trabajador = mapRowToTrabajador($row);
					trabajadoresFiltrados.push(trabajador);
				}
			});

    // NUEVO: Manejo de Tirillas de Pago (esto debe ir ANTES de la validación de trabajadores vacíos)
    if (alcance === 'tirillas') {
        generarTirillasPago(trabajadoresFiltrados);
        return;
    }
    
    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: '<i class="fas fa-database me-2" style="color: #f59e0b;"></i> Sin datos disponibles',
            html: '<div class="text-center"><i class="fas fa-filter fa-3x mb-3" style="color: #f59e0b; opacity: 0.7;"></i><p class="mb-2">No hay registros que coincidan con la agrupación seleccionada.</p></div>',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    // Ejecutar la acción según la opción seleccionada
    if (action === 'imprimir') {
        generarNominaImpresa(trabajadoresFiltrados, alcance, filtroNombre, isTodos);
    } else if (action === 'pdf') {
        exportarPdfOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'excel') {
        exportarExcelOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'word') {
        exportarWordOficial(trabajadoresFiltrados, alcance, filtroNombre);
    }
});

$('#modalOpcionesImpresion').on('hidden.bs.modal', function () {
    // Restablecer radio button a "General"
    $('#alcanceGeneral').prop('checked', true);
    // Ocultar selectores dinámicos
    $('#selectoresDinamicos').hide();
    // Limpiar contenido de selectores
    $('#contenedorSelectores').empty();
    // Habilitar botón de generar por si quedó deshabilitado
    $('#btnGenerarImpresion').prop('disabled', false).html('<i class="fas fa-print me-2"></i>Generar Nómina Impresa');
});
// Dentro de $(document).ready, después de la definición de los otros filtros
$('#filterAjusteArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Ajuste', 'area');
    }
});
$('#filterAjusteCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Ajuste', 'cc');
    }
});
$('#searchAjusteWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Ajuste', 'search');
    } else {
        renderAjusteWorkerList('');
    }
});

// Actualizar la función limpiarFiltrosModal existente para incluir 'Ajuste'
function limpiarFiltrosModal(modalId, filtroActivo) {
    var areaId = '#filter' + modalId + 'Area';
    var ccId = '#filter' + modalId + 'CC';
    var searchId = '#search' + modalId + 'Worker';
    
    if (filtroActivo === 'area') {
        $(ccId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'cc') {
        $(areaId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'search') {
        $(areaId).val('');
        $(ccId).val('');
    }
    
    var term = $(searchId).val();
    if (modalId === 'Extra') renderExtraWorkerList(term);
    else if (modalId === 'Vac') renderWorkerList(term);
    else if (modalId === 'Bono') renderBonoWorkerList(term);
    else if (modalId === 'Ajuste') renderAjusteWorkerList(term);
}
// =========================================================================
// ASIGNACIÓN GLOBAL Y SELECCIÓN DEL MODAL DE IMPRESIÓN MEJORADO
// =========================================================================

// Exponer la función de impresión global para evitar ReferenceError
window.generarNominaImpresa = function(trabajadores, alcance, filtroNombre) {
    const fechaActual = new Date().toLocaleDateString('es-ES');
    const horaActual = new Date().toLocaleTimeString('es-ES');
    const FILAS_POR_PAGINA = 15;

    trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);

    let paginasHtmlArray = [];
    let totalGeneralCompleto = {
        aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0,
        pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0
    };
    
    // Sumamos los totales generales
    trabajadores.forEach(t => {
        totalGeneralCompleto.aCobrar += t.aCobrar || 0;
        totalGeneralCompleto.bono += t.bono || 0;
        totalGeneralCompleto.devengado += t.devengado || 0;
        totalGeneralCompleto.impS += t.impS || 0;
        totalGeneralCompleto.descuentos += t.descuentos || 0;
        totalGeneralCompleto.retenciones += t.retenciones || 0;
        totalGeneralCompleto.pagado += t.pagado || 0;
        totalGeneralCompleto.vacDias += t.vacDias || 0;
        totalGeneralCompleto.tiempoImp += t.tiempoImp || 0;
        totalGeneralCompleto.salarioMensual += t.salarioMensual || 0;
    });

    const esBono = (tipoNomina === 'bono');
    const esAjuste = (tipoNomina === 'ajuste');
    const mostrarConcepto = (esBono || esAjuste);

    if (alcance === 'general') {
        let totalPaginas = Math.ceil(trabajadores.length / FILAS_POR_PAGINA);
        let numeroPagina = 1;
        
        for (let i = 0; i < trabajadores.length; i += FILAS_POR_PAGINA) {
            const trabajadoresPagina = trabajadores.slice(i, i + FILAS_POR_PAGINA);
            let subtotalPagina = {
                aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0,
                pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0
            };
            
            let cuerpoHtml = '';
            trabajadoresPagina.forEach(t => {
                if (esBono) {
                    cuerpoHtml += `<tr>
                        <td class="text-center">${escapeHtml(t.codigo)}</td>
                        <td class="text-center">${escapeHtml(t.ci)}</td>
                        <td class="text-left">${escapeHtml(t.nombre)}</td>
                        <td class="text-center">${escapeHtml(t.categoriaCodigo)}</td>
                        <td class="text-right">$${(t.salarioMensual || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.devengado || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.impS || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.descuentos || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.retenciones || 0).toFixed(2)}</td>
                        <td class="text-right"><strong>$${(t.pagado || 0).toFixed(2)}</strong></td>
                        <td class="text-center"></td>
                    </tr>
                    <tr class="concepto-fila"><td colspan="11" class="text-left">Observación: ${escapeHtml(t.concepto)}</td></tr>`;
                } else {
                    cuerpoHtml += `<tr>
                        <td class="text-center">${escapeHtml(t.codigo)}</td>
                        <td class="text-center">${escapeHtml(t.ci)}</td>
                        <td class="text-left">${escapeHtml(t.nombre)}</td>
                        <td class="text-center">${escapeHtml(t.categoriaCodigo)}</td>
                        <td class="text-right">$${(t.salarioMensual || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.tarifaSal || 0).toFixed(2)}</td>
                        <td class="text-right">${t.horas || 0}</td>
                        <td class="text-right">$${(t.aCobrar || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.bono || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.devengado || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.impS || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.retenciones || 0).toFixed(2)}</td>
                        <td class="text-right"><strong>$${(t.pagado || 0).toFixed(2)}</strong></td>
                        <td class="text-right">${(t.vacDias || 0).toFixed(2)}</td>
                        <td class="text-right">$${(t.tiempoImp || 0).toFixed(2)}</td>
                        <td class="text-center"></td>
                    </tr>`;
                    if (mostrarConcepto) {
                        cuerpoHtml += `<tr class="concepto-fila"><td colspan="16" class="text-left">Observación: ${escapeHtml(t.concepto)}</td></tr>`;
                    }
                }
                
                subtotalPagina.aCobrar += t.aCobrar || 0;
                subtotalPagina.bono += t.bono || 0;
                subtotalPagina.devengado += t.devengado || 0;
                subtotalPagina.impS += t.impS || 0;
                subtotalPagina.descuentos += t.descuentos || 0;
                subtotalPagina.retenciones += t.retenciones || 0;
                subtotalPagina.pagado += t.pagado || 0;
                subtotalPagina.vacDias += t.vacDias || 0;
                subtotalPagina.tiempoImp += t.tiempoImp || 0;
                subtotalPagina.salarioMensual += t.salarioMensual || 0;
            });
            
            if (esBono) {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="5" class="text-right"><strong>SUBTOTAL PÁGINA ${numeroPagina}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.devengado.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.impS.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.descuentos.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.retenciones.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.pagado.toFixed(2)}</strong></td>
                    <td class="text-center">-</td>
                </tr>`;
                
                if (numeroPagina === totalPaginas) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="5" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.devengado.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.impS.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.descuentos.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.retenciones.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-center">-</td>
                    </tr>`;
                }
            } else {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="7" class="text-right"><strong>SUBTOTAL PÁGINA ${numeroPagina}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.aCobrar.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.bono.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.devengado.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.impS.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.retenciones.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.pagado.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>${subtotalPagina.vacDias.toFixed(2)}</strong></td>
                    <td class="text-right"><strong>$${subtotalPagina.tiempoImp.toFixed(2)}</strong></td>
                    <td class="text-center">-</td>
                </tr>`;
                
                if (numeroPagina === totalPaginas) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="7" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.aCobrar.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.bono.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.devengado.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.impS.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.retenciones.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>${totalGeneralCompleto.vacDias.toFixed(2)}</strong></td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.tiempoImp.toFixed(2)}</strong></td>
                        <td class="text-center">-</td>
                    </tr>`;
                }
            }
            
            const paginaHtml = generarHtmlCompletoConPaginacion(
                cuerpoHtml, alcance, filtroNombre, nombreEmpresa, 
                periodoTexto, usuarioNombre, numeroNomina, 
                fechaActual, horaActual, numeroPagina, totalPaginas
            );
            paginasHtmlArray.push(paginaHtml);
            numeroPagina++;
        }
    } else {
        // Lógica agrupada por Área, CC, etc.
        let campoAgrupacion = obtenerCampoAgrupacion(alcance);
        let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
        let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);
        
        let paginas = [];
        let paginaActual = [];
        let contadorFilas = 0;
        let subtotalPagina = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0 };

        function cerrarPagina() {
            if (paginaActual.length === 0) return;
            paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
            paginaActual = [];
            contadorFilas = 0;
            subtotalPagina = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0 };
        }

        Object.entries(grupos).forEach(([clave, empleados]) => {
            let subTotalGrupo = { aCobrar:0, bono:0, devengado:0, impS:0, retenciones:0, pagado:0, vacDias:0, tiempoImp:0, descuentos:0, salarioMensual:0 };
            if (paginaActual.length > 0 && (contadorFilas + empleados.length + 2 > FILAS_POR_PAGINA)) {
                cerrarPagina();
            }
            paginaActual.push({ tipo: 'grupo_header', titulo: clave });
            contadorFilas++;

            empleados.forEach(t => {
                if (contadorFilas >= FILAS_POR_PAGINA) {
                    cerrarPagina();
                    paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (cont.)` });
                    contadorFilas++;
                }
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subTotalGrupo, t);
                acumularTotales(subtotalPagina, t);
                contadorFilas++;
            });

            paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();

        paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let cuerpoHtml = '';
            
            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    let totalColsSpan = esBono ? 11 : 16;
                    cuerpoHtml += `<tr><td colspan="${totalColsSpan}" style="background:#e0e0e0; font-weight:bold;">${escapeHtml(row.titulo)}</td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        cuerpoHtml += `<tr>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.codigo)}</td>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.ci)}</td>
                            <td style="border:0.5pt solid #000;">${escapeHtml(row.data.nombre)}</td>
                            <td class="text-center" style="border:0.5pt solid #000;">${escapeHtml(row.data.categoriaCodigo)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; font-weight:bold;">$${(row.data.pagado || 0).toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>
                        <tr class="concepto-fila"><td colspan="11" class="text-left">Observación: ${escapeHtml(row.data.concepto)}</td></tr>`;
                    } else {
                        // Se inyecta la columna de Salario Básico (columna 5) para nóminas estándar agrupadas
                        cuerpoHtml += `<tr>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.codigo)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.ci)}</td>
                            <td style="border:0.5pt solid #000;">${escapeHtml(row.data.nombre)}</td>
                            <td style="text-align:center; border:0.5pt solid #000;">${escapeHtml(row.data.categoriaCodigo)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.tarifaSal || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${row.data.horas}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.aCobrar || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.bono || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                            <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">$${(row.data.pagado || 0).toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                            <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.tiempoImp || 0).toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                        if (mostrarConcepto) {
                            cuerpoHtml += `<tr class="concepto-fila"><td colspan="16" class="text-left">Observación: ${escapeHtml(row.data.concepto)}</td></tr>`;
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr class="totales-subgrupo" style="font-size:7.5pt;">
                                <td colspan="5" class="text-right"><strong>${escapeHtml(row.titulo)}:</strong></td>
                                <td class="text-right">$${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right">$${row.data.descuentos.toFixed(2)}</td>
                                <td class="text-right">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right"><strong>$${row.data.pagado.toFixed(2)}</strong></td>
                                <td>-</td>
                            </tr>`;
                    } else {
                        cuerpoHtml += `
                            <tr class="totales-subgrupo" style="font-size:7.5pt;">
                                <td colspan="7" class="text-right"><strong>${escapeHtml(row.titulo)}:</strong></td>
                                <td class="text-right">$${row.data.aCobrar.toFixed(2)}</td>
                                <td class="text-right">$${row.data.bono.toFixed(2)}</td>
                                <td class="text-right">$${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right"><strong>$${row.data.pagado.toFixed(2)}</strong></td>
                                <td class="text-right">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right">$${row.data.tiempoImp.toFixed(2)}</td>
                                <td>-</td>
                            </tr>`;
                    }
                }
            });

            if (esBono) {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="5" class="text-right"><strong>SUBTOTAL PÁGINA ${numPag}</strong></td>
                    <td class="text-right">$${pag.subtotal.devengado.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.impS.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.descuentos.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.retenciones.toFixed(2)}</td>
                    <td class="text-right"><strong>$${pag.subtotal.pagado.toFixed(2)}</strong></td>
                    <td>-</td>
                </tr>`;

                if (numPag === paginas.length) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="5" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right">$${totalGeneralCompleto.devengado.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.impS.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.descuentos.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.retenciones.toFixed(2)}</td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td>-</td>
                    </tr>`;
                }
            } else {
                cuerpoHtml += `<tr class="totales-pagina">
                    <td colspan="7" class="text-right"><strong>SUBTOTAL PÁGINA ${numPag}</strong></td>
                    <td class="text-right">$${pag.subtotal.aCobrar.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.bono.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.devengado.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.impS.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.retenciones.toFixed(2)}</td>
                    <td class="text-right"><strong>$${pag.subtotal.pagado.toFixed(2)}</strong></td>
                    <td class="text-right">${pag.subtotal.vacDias.toFixed(2)}</td>
                    <td class="text-right">$${pag.subtotal.tiempoImp.toFixed(2)}</td>
                    <td>-</td>
                </tr>`;

                if (numPag === paginas.length) {
                    cuerpoHtml += `<tr class="totales-nomina">
                        <td colspan="7" class="text-right"><strong>TOTAL NOMINA</strong></td>
                        <td class="text-right">$${totalGeneralCompleto.aCobrar.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.bono.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.devengado.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.impS.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.retenciones.toFixed(2)}</td>
                        <td class="text-right"><strong>$${totalGeneralCompleto.pagado.toFixed(2)}</strong></td>
                        <td class="text-right">${totalGeneralCompleto.vacDias.toFixed(2)}</td>
                        <td class="text-right">$${totalGeneralCompleto.tiempoImp.toFixed(2)}</td>
                        <td>-</td>
                    </tr>`;
                }
            }

            const paginaHtml = generarHtmlCompletoConPaginacion(
                cuerpoHtml, alcance, filtroNombre, nombreEmpresa, 
                periodoTexto, usuarioNombre, numeroNomina, 
                fechaActual, horaActual, numPag, paginas.length
            );
            paginasHtmlArray.push(paginaHtml);
        });
    }

    const ventana = window.open('', '_blank');
    if (!ventana) return;
    ventana.document.write(paginasHtmlArray.join('<div class="page-break"></div>'));
    ventana.document.close();
};

// Control interactivo de las tarjetas de selección
$(document).on('click', '.print-option-card', function() {
    $('.print-option-card').removeClass('selected');
    $(this).addClass('selected');
    
    const targetRadioId = $(this).data('target-radio');
    $('#' + targetRadioId).prop('checked', true).trigger('change');

    if (targetRadioId === 'alcanceGeneral') {
        $('#selectoresDinamicos').slideUp(200);
        $('#resumenSeleccion').slideUp(200);
    } else {
        $('#selectoresDinamicos').slideDown(250);
        $('#resumenSeleccion').slideDown(250);
    }
});

// Limpieza de parámetros al ocultar el modal
$('#modalOpcionesImpresion').on('hidden.bs.modal', function () {
    $('.print-option-card').removeClass('selected');
    $('.print-option-card[data-target-radio="alcanceGeneral"]').addClass('selected');
    $('#alcanceGeneral').prop('checked', true);
    $('#selectoresDinamicos').hide();
    $('#resumenSeleccion').hide();
    $('#contenedorSelectores').empty();
});

// Intercepción y mapeo de acciones del Dropdown del modal
// Intercepción y mapeo de acciones del Dropdown del modal
$(document).on('click', '.opt-impresion', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const action = $(this).data('action');
    const alcance = $('input[name="alcanceImpresion"]:checked').val();
    let filtroValor = null;
    let filtroNombre = null;

    // IMPORTANTE: Para 'tirillas' NO se necesita filtro de selector
    if (alcance !== 'general' && alcance !== 'tirillas') {
        filtroValor = $('#selectImpresion').val();
        filtroNombre = $('#selectImpresion option:selected').text();
        
        if (filtroValor === '') {
            filtroNombre = 'Todos';
        } else if (!filtroValor) {
            Swal.fire({
                title: 'Atención',
                text: 'Por favor, elija un valor en el menú desplegable del alcance seleccionado.',
                icon: 'warning',
                background: '#1e1e24',
                color: '#ffffff'
            });
            return;
        }
    }

    const isTodos = (filtroValor === ''); 

    // Colección de datos y procesamiento de filtros de alcance
    const dataTable = $('#tablaNominas').DataTable();
    const filas = dataTable.rows({ search: 'applied' }).nodes();
    let trabajadoresFiltrados = [];

    $(filas).each(function() {
        const $row = $(this);
        let incluir = true;

        const areaId = $row.data('area-id');
        const centroId = $row.data('centro-costo-id');
        const categoriaId = $row.data('categoria-ocupacional-id');
        const escalaId = $row.data('escala-id');
        const tipoContrato = $row.data('tipo-contrato');
        const cargoId = $row.data('cargo-id');

        switch (alcance) {
            case 'area':
                incluir = (filtroValor === '' || areaId == filtroValor);
                break;
            case 'centro_costo':
                incluir = (filtroValor === '' || centroId == filtroValor);
                break;
            case 'categoria':
                incluir = (filtroValor === '' || categoriaId == filtroValor);
                break;
            case 'escala':
                incluir = (filtroValor === '' || escalaId == filtroValor);
                break;
            case 'tipo_contrato':
                incluir = (filtroValor === '' || tipoContrato == filtroValor);
                break;
            case 'cargo':
                incluir = (filtroValor === '' || cargoId == filtroValor);
                break;
            default:
                incluir = true;
        }

	if (incluir) {
				// Mapeo unificado y seguro de campos
				const trabajador = mapRowToTrabajador($row);
				trabajadoresFiltrados.push(trabajador);
			}
		});

    // NUEVO: Manejo de Tirillas de Pago
    if (alcance === 'tirillas') {
        generarTirillasPago(trabajadoresFiltrados);
        bootstrap.Modal.getInstance(document.getElementById('modalOpcionesImpresion')).hide();
        return;
    }
    
    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: '<i class="fas fa-users-slash me-2" style="color: #ef4444;"></i> Sin trabajadores',
            html: '<div class="text-center"><i class="fas fa-search fa-3x mb-3" style="color: #ef4444; opacity: 0.7;"></i><p>No se encontraron trabajadores con los filtros seleccionados.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    // Ejecución de la acción seleccionada
    if (action === 'imprimir') {
        window.generarNominaImpresa(trabajadoresFiltrados, alcance, filtroNombre, isTodos);
    } else if (action === 'pdf') {
        exportarPdfOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'excel') {
        exportarExcelOficial(trabajadoresFiltrados, alcance, filtroNombre);
    } else if (action === 'word') {
        exportarWordOficial(trabajadoresFiltrados, alcance, filtroNombre);
    }

    // Ocultar modal
    bootstrap.Modal.getInstance(document.getElementById('modalOpcionesImpresion')).hide();
});


function generarHtmlCompletoConPaginacion(cuerpoHtml, alcance, filtroNombre, nombreEmpresa, periodoTexto, usuarioNombre, numeroNomina, fechaActual, horaActual, pagina, totalPaginas) {
    const codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);
    const esBono = (tipoNomina === 'bono');
    const colsCount = esBono ? 11 : 16;

    // Cabeceras dinámicas (11 columnas para Bono / 16 columnas para el resto)
    const cabecerasTabla = esBono ? `
        <tr>
            <th style="width: 5%;">Código</th>
            <th style="width: 10%;">CI</th>
            <th style="width: 30%;">Nombre y Apellidos</th>
            <th style="width: 5%;">Cat.</th>
            <th style="width: 10%;">S. Básico</th>
            <th style="width: 8%;">Deven.</th>
            <th style="width: 8%;">Imp. CESS</th>
            <th style="width: 8%;">Deducc.</th>
            <th style="width: 8%;">Ret Total</th>
            <th style="width: 8%;">Pagado</th>
            <th style="width: 10%;">Firma</th>
        </tr>
    ` : `
        <tr>
            <th>Código</th><th>CI</th><th>Nombre y Apellidos</th><th>Cat.</th><th>S. Básico</th><th>Tarf.</th><th>Horas</th>
            <th>A cobrar</th><th>Bon.</th><th>Deven.</th><th>Imp. CESS.</th>
            <th>Ret.</th><th>Pagado</th><th>Vac.</th><th>Tiem. Imp.</th><th>Firma</th>
        </tr>
    `;

    return `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Nómina de Salarios - ${escapeHtml(nombreEmpresa)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 10pt; margin: 15mm 10mm; background: white; color: black; }
        .nomina-container { width: 100%; max-width: 1300px; margin: 0 auto; }
        .header-nomina { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #000; }
        .header-nomina td { border: 1px solid #000; padding: 4px 6px; font-size: 8.5pt; vertical-align: middle; }
        .header-label { font-weight: bold; background-color: #f0f0f0; }
        .tabla-nomina { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 10px; }
        .tabla-nomina th, .tabla-nomina td { border: 1px solid #000; padding: 4px 2px; }
        .tabla-nomina th { background-color: #004B87; color: white; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .area-header td { background-color: #e0e0e0; font-weight: bold; }
        .totales-subgrupo { background-color: #f9f9f9; font-weight: bold; }
        .totales-pagina { background-color: #fff3cd; font-weight: bold; }
        .totales-nomina { background-color: #d9e1f2; font-weight: bold; }
        .concepto-fila td { text-align: left !important; }
        .footer { margin-top: 20px; font-size: 8pt; text-align: center; border-top: 1px solid #ccc; padding-top: 8px; }
        .page-break { page-break-before: always; }
        @media print { body { margin: 0; padding: 0; } }
    </style>
    </head>
    <body>
    <div class="nomina-container">
        <!-- HEADER SC-4-06 -->
<table class="header-nomina">
    <tr style="height: 28pt;">
        <td width="54" style="width: 40.85pt; text-align: center;">
            ${logoBase64 ? `<img width="40" height="38" src="${logoBase64}" style="display: block; margin: 0 auto;">` : ''}
        </td>
        <td colspan="${colsCount - 2}" style="text-align: center;">
            <span style="font-size: 14pt; font-weight: bold;">MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa)}</span>
        </td>
        <td width="170" style="width: 127.3pt; text-align: center;">
            <span style="font-size: 11pt; font-weight: bold;">Página ${pagina} de ${totalPaginas}</span>
        </td>
    </tr>
    <tr style="height: 13pt;">
        <td colspan="3">
            <strong>Tipo Nómina:</strong> <span style="font-style: italic;font-weight: bold;font-size:12px;">${escapeHtml(tipoNominaTexto)}</span>
        </td>
        <td colspan="${esBono ? 2 : 1}">
            <strong>Código:</strong> <span>${codigoMostrado}</span>
        </td>
        <td colspan="${esBono ? 6 : 10}" rowspan="5" style="padding: 8px 10px; line-height: 1.6; font-size: 8.5pt;">
            <div style="margin-bottom: 6px;">
                <b>REVISADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size: 13pt; color: #000;">${escapeHtml(especialistaGestion)}</span>
            </div>
            <div style="margin-bottom: 6px;">
                <b>APROBADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size: 13pt; color: #000;">${escapeHtml(jefeProyecto)}</span>
            </div>
            <div style="margin-bottom: 6px; font-size: 8.5pt; color: #222;"><b>ELABORADA POR:</b> <span>______________________________________</span></div>
            <div style="font-size: 8.5pt; color: #222;"><b>CONTABILIZADA POR:</b> <span>__________________________________</span></div>
        </td>
    </tr>
    
    <!-- 🔽 FILA 1: Monto a Distribuir (SOLO PARA BONO) o Cheque No. (para otros tipos) -->
	${esBono ? `
		<tr>
			<td colspan="${esBono ? 5 : 4}">
				<strong>Monto a Distribuir:</strong> 
				<span style="${montoDistribuidoGlobal > 0 ? 'font-weight: bold; color: #004B87; font-size: 11pt;' : 'font-weight: bold; color: #ef4444; font-size: 11pt;'}">
					${montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)'}
				</span>
			</td>
		</tr>
	` : `
		<tr>
			<td colspan="${esBono ? 5 : 4}">
				<strong>Cheque No.:</strong> <span></span>
			</td>
		</tr>
	`}
    
    <!-- 🔽 FILA 2: No. Instrum. Pago (SIEMPRE SE MUESTRA) -->
    <tr style="height: 12pt;">
        <td colspan="${esBono ? 5 : 4}">
            <strong>No. Instrum. Pago:</strong> <span>${escapeHtml(numeroNomina)}</span>
        </td>
    </tr>
    
    <!-- 🔽 FILA 3: Alcance y Fecha Impresa -->
    <tr style="height: 12pt;">
        <td colspan="2">
            <strong>Alcance:</strong> <span>${escapeHtml(alcance.toUpperCase())}${filtroNombre ? ': ' + escapeHtml(filtroNombre) : ''}</span>
        </td>
        <td colspan="${esBono ? 3 : 2}">
            <strong>Fecha Impresa:</strong> <span>${fechaActual} ${horaActual}</span>
        </td>
    </tr>
    
    <!-- 🔽 FILA 4: Período de Pago y REEUP/NIT -->
    <tr style="height: 24pt;">
        <td colspan="2" style="vertical-align: middle;">
            <div style="margin-bottom: 2px;"><strong>Período de Pago:</strong> <span>${periodoTexto}</span></div>
            <div><strong>MES / AÑO:</strong> <span>${escapeHtml(nombreMesGlobal)} / ${escapeHtml(anioGlobal)}</span></div>
        </td>
        <td colspan="${esBono ? 3 : 2}" style="vertical-align: middle;">
            <div><strong>Código REEUP:</strong> <span>${escapeHtml(reeup)}</span></div>
            <div><strong>NIT Empresa:</strong> <span>${escapeHtml(nitEmpresa)}</span></div>
        </td>
    </tr>
    
    <!-- 🔽 FILA 5: Observaciones de Cierre (SOLO SI EXISTEN) -->
    ${observacionesCierreGlobal ? `
    <tr style="height: 14pt;">
        <td colspan="${colsCount}" style="background-color: #f8fafc; font-size: 8pt; border: 1px solid #000;">
            <strong>Observaciones de Cierre:</strong> <span>${escapeHtml(observacionesCierreGlobal)}</span>
        </td>
    </tr>` : ''}
</table>

        <table class="tabla-nomina">
            <thead>${cabecerasTabla}</thead>
            <tbody>${cuerpoHtml}</tbody>
        </table>
        <div class="footer">
            <div>Documento generado por el Sistema de Gestión de Nóminas de ${escapeHtml(nombreEmpresa)}</div>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); setTimeout(function() { window.close(); }, 500); }, 200); }<\/script>
    </body>
    </html>`;
}

function generarHtmlCompleto(cuerpoHtml, duplicadaRows, duplicadaTotales, alcance, filtroNombre, nombreEmpresa, periodoTexto, usuarioNombre, numeroNomina, fechaActual, horaActual, jefeProyecto, especialistaGestion) {
    const codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);

    return `<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Nómina de Salarios - ${escapeHtml(nombreEmpresa)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 10pt; margin: 15mm 10mm; background: white; color: black; }
        .nomina-container { width: 100%; max-width: 1300px; margin: 0 auto; }
        .header-nomina { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #000; }
        .header-nomina td { border: 1px solid #000; padding: 4px 6px; font-size: 8.5pt; vertical-align: middle; }
        .header-label { font-weight: bold; background-color: #f0f0f0; }
        .tabla-nomina { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 10px; }
        .tabla-nomina th, .tabla-nomina td { border: 1px solid #000; padding: 4px 2px; }
        .tabla-nomina th { background-color: #004B87; color: white; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .area-header td { background-color: #e0e0e0; font-weight: bold; }
        .totales-subgrupo { background-color: #f9f9f9; font-weight: bold; }
        .totales-nomina { background-color: #d9e1f2; font-weight: bold; }
        .duplicada { margin-top: 30px; border-top: 2px dashed #000; padding-top: 15px; }
        .footer { margin-top: 20px; font-size: 8pt; text-align: center; border-top: 1px solid #ccc; padding-top: 8px; }
        @media print { body { margin: 0; padding: 0; } .page-break { page-break-before: always; } }
    </style>
    </head>
    <body>
    <div class="nomina-container">
        <!-- HEADER SC-4-06 -->
        <table class="header-nomina">
            <tr style="height: 28pt;">
                <td width="54" style="width: 40.85pt; text-align: center;">
                    ${logoBase64 ? `<img width="40" height="38" src="${logoBase64}" style="display: block; margin: 0 auto;">` : ''}
                </td>
                <td width="749" colspan="4" style="width: 561.65pt; text-align: center;">
                    <span style="font-size: 14pt; font-weight: bold;">MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa)}</span>
                </td>
                <td width="170" style="width: 127.3pt; text-align: center;">
                    <span style="font-size: 11pt; font-weight: bold;">Página 1 de 1</span>
                </td>
            </tr>
            <tr style="height: 13pt;">
                <td colspan="3" style="width: 304.55pt;">
                    <strong>Tipo Nómina:</strong> <span style="font-style: italic;">${escapeHtml(tipoNominaTexto)}</span>
                </td>
                <td style="width: 142.8pt;">
                    <strong>Código:</strong> <span>${codigoMostrado}</span>
                </td>
                <td colspan="2" rowspan="5" style="width: 282.45pt; padding: 8px 10px; line-height: 1.6; font-size: 8.5pt;">
                    <div style="margin-bottom: 6px;">
                        <b>REVISADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size: 13pt; color: #000;"><?php echo htmlspecialchars($config_empresa['especialista_gestion'] ?? 'Mailén Pérez García', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div style="margin-bottom: 6px;">
                        <b>APROBADA POR:</b> <span style="text-decoration: underline; font-weight: bold; font-size: 13pt; color: #000;"><?php echo htmlspecialchars($config_empresa['jefe_proyecto'] ?? 'Dainelys León Reyes', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div style="margin-bottom: 6px; font-size: 8.5pt; color: #222;"><b>ELABORADA POR:</b> <span>______________________________________</span></div>
                    <div style="font-size: 8.5pt; color: #222;"><b>CONTABILIZADA POR:</b> <span>______________________________________</span></div>
                </td>
            </tr>
            <tr style="height: 12pt;">
                <td colspan="4">
                    <strong>Cheque No.:</strong> <span></span>
                </td>
            </tr>
            <tr style="height: 12pt;">
                <td colspan="4">
                    <strong>No. Instrum. Pago:</strong> <span>${escapeHtml(numeroNomina)}</span>
                </td>
            </tr>
            <tr style="height: 12pt;">
                <td colspan="2" style="width: 269.1pt;">
                    <strong>Alcance:</strong> <span>${escapeHtml(alcance.toUpperCase())}${filtroNombre ? ': ' + escapeHtml(filtroNombre) : ''}</span>
                </td>
                <td colspan="2" style="width: 178.25pt;">
                    <strong>Fecha Impresa:</strong> <span>${fechaActual} ${horaActual}</span>
                </td>
            </tr>
			<tr style="height: 24pt;">
                <td colspan="2" style="width: 269.1pt; vertical-align: middle;">
                    <div style="margin-bottom: 2px;"><strong>Período de Pago:</strong> <span>${periodoTexto}</span></div>
                    <div><strong>MES / AÑO:</strong> <span><?php echo addslashes($nombre_mes); ?> / <?php echo addslashes($anio); ?></span></div>
                </td>
                <td colspan="2" style="width: 178.25pt; vertical-align: middle;">
                    <div><strong>Código REEUP:</strong> <span>${escapeHtml(reeup)}</span></div>
                    <div><strong>NIT Empresa:</strong> <span>${escapeHtml(nitEmpresa)}</span></div> <!-- <-- NIT INYECTADO -->
                </td>
            </tr>
        </table>

        <table class="tabla-nomina">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>CI</th>
                    <th>Nombre y Apellidos</th>
                    <th>Cat.</th>
                    <th>Tarf.</th>
                    <th>Horas</th>
                    <th>A cobrar</th>
                    <th>Bon.</th>
                    <th>Deven.</th>
                    <th>Imp. CESS.</th>
                    <th>Ret.</th>
                    <th>Pagado</th>
                    <th>Vac.</th>
                    <th>Tiem. Imp.</th>
                    <th>Firma</th>
                </tr>
            </thead>
            <tbody>${cuerpoHtml}</tbody>
        </table>

        <div class="duplicada">
            <h4 style="text-align:center; font-size:10pt;">--- CORTE PARA VOLTEO ---</h4>
            <table class="tabla-nomina">
                <thead><tr><th>Código</th><th>CI</th><th>Nombre</th><th>A cobrar</th><th>Bon.</th><th>Deven.</th><th>Imp. S.</th><th>Ret.</th><th>Pagado</th></tr></thead>
                <tbody>${duplicadaRows}${duplicadaTotales}</tbody>
            </table>
        </div>

        <div class="footer">
            <div>Documento generado por el Sistema de Gestión de Nóminas de ${escapeHtml(nombreEmpresa)}</div>
        </div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); setTimeout(function() { window.close(); }, 500); }, 200); }<\/script>
    </body>
    </html>`;
}




// ==========================================
// FILTROS EN MODALES: ÁREA Y CENTRO DE COSTO
// ==========================================
$(document).on('change', '.filter-modal-worker', function() {
    var modal = $(this).data('modal'); // 'extra', 'vac', 'bono'
    var searchVal = '';
    
    if (modal === 'extra') {
        searchVal = $('#searchExtraWorker').val();
        renderExtraWorkerList(searchVal);
    } else if (modal === 'vac') {
        searchVal = $('#searchWorker').val();
        renderWorkerList(searchVal);
    } else if (modal === 'bono') {
        searchVal = $('#searchBonoWorker').val();
        renderBonoWorkerList(searchVal);
    }
});

    if (filtroCuentaActual) {
        $('#filtroCuenta').val(filtroCuentaActual);
    }
    
$('#filtroCuenta').on('change', function() {
    if (nominasTable) aplicarFiltros();
});

$('#tablaNominas').on('click', 'tbody tr', function(e) {
    // Evitar abrir el modal si hace clic en inputs, botones o selects
    if ($(e.target).closest('input, button, select, .btn-icon, a, .edit-input').length) {
        return;
    }
    
    // Evitar abrir el modal si hace clic en la columna de acciones (última columna)
    if ($(e.target).closest('td').is(':last-child')) {
        return;
    }

    if ($('.modal.show').length > 0) {
        return;
    }

    var $row = $(this);
    var rowId = $row.attr('data-id') || $row.data('id'); // <-- MODIFICADO
    if (!rowId) return;

    var table = $('#tablaNominas').DataTable();
    var allVisibleRows = table.rows({ search: 'applied' }).nodes();
    var currentPos = Array.from(allVisibleRows).findIndex(node => $(node).attr('data-id') == rowId);

    window.editCurrentRowId = rowId;
    window.editCurrentRowIndex = currentPos;
    window.editVisibleRowsIds = Array.from(allVisibleRows).map(node => $(node).attr('data-id') || $(node).data('id'));

    cargarModalEdicion($row);
    
	var modalEl = document.getElementById('modalEdicionRapida');
	if (modalEl) {
		// Verificar si existe el modal en el DOM
		var modal = bootstrap.Modal.getInstance(modalEl);
		if (!modal) {
			modal = new bootstrap.Modal(modalEl, {
				backdrop: 'static',
				keyboard: false
			});
		}
		modal.show();
	} else {
		console.error('Modal no encontrado: modalEdicionRapida');
	}
});

    $(document).on('change', '.edit-dias, #editDias, .dias-input', function() {
        var $input = $(this);
        var valorRaw = parseFloat($input.val()) || 1;
        
        var valorRedondeado = Math.roundExcel(valorRaw * 2) / 2;
        
        if (valorRedondeado < 1) {
            valorRedondeado = 1;
        }
        
        $input.val(valorRedondeado);
        $input.trigger('input');
    });

// ==========================================
// AGREGAR TRABAJADORES A NÓMINA AUTOMÁTICA
// ==========================================
var trabajadoresDisponibles = [];
var trabajadoresSeleccionadosAuto = [];

function cargarTrabajadoresDisponibles() {
    var idsEnNomina = [];
    
    <?php
    $stmt_ids_nomina = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
    $stmt_ids_nomina->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
    $ids_nomina_db = $stmt_ids_nomina->fetchAll(PDO::FETCH_COLUMN);
    ?>
    var idsEnNominaDB = <?php echo json_encode($ids_nomina_db); ?>;
    
    var idsEnTabla = [];
    $('#tablaNominas tbody tr').each(function() {
        var id = $(this).data('trabajador-id');
        if (id) idsEnTabla.push(parseInt(id));
    });
    
    var idsEnNomina = idsEnNominaDB;
    
    var areaVal = $('#filterAutoArea').val();
    var ccVal = $('#filterAutoCC').val();
    var searchVal = $('#searchAutoWorker').val().toLowerCase();
    
    var disponibles = trabajadores.filter(function(t) {
        if (idsEnNomina.includes(parseInt(t.id))) return false;
        if (areaVal && parseInt(t.area_id) !== parseInt(areaVal)) return false;
        if (ccVal && parseInt(t.centro_costo_id) !== parseInt(ccVal)) return false;
        if (searchVal && !t.nombre_completo.toLowerCase().includes(searchVal) && 
            !t.codigo.toLowerCase().includes(searchVal) && 
            !t.ci.toLowerCase().includes(searchVal)) return false;
        return true;
    });
    
    trabajadoresDisponibles = disponibles;
    renderAutoWorkerList(idsEnNomina);
}

function renderAutoWorkerList(idsEnNomina) {
    var html = '';
    
    if (!idsEnNomina) {
        idsEnNomina = [];
        $('#tablaNominas tbody tr').each(function() {
            var id = $(this).data('trabajador-id');
            if (id) idsEnNomina.push(parseInt(id));
        });
    }
    
    var totalTrabajadoresSistema = trabajadores.length;
    var totalEnNomina = idsEnNomina.length;
    var disponiblesCount = trabajadoresDisponibles.length;
    var faltantes = totalTrabajadoresSistema - totalEnNomina;
    
    var coberturaColor = '';
    var coberturaIcono = '';
    var coberturaTexto = '';
    
    if (totalEnNomina === 0) {
        coberturaColor = '#ef4444';
        coberturaIcono = '<i class="fas fa-times-circle"></i>';
        coberturaTexto = '⚠️ NINGÚN trabajador en nómina';
    } else if (totalEnNomina === totalTrabajadoresSistema) {
        coberturaColor = '#10b981';
        coberturaIcono = '<i class="fas fa-check-circle"></i>';
        coberturaTexto = '✅ NÓMINA COMPLETA - Todos los trabajadores están incluidos';
    } else {
        coberturaColor = '#f59e0b';
        coberturaIcono = '<i class="fas fa-chart-line"></i>';
        coberturaTexto = `📊 Cobertura: ${totalEnNomina} de ${totalTrabajadoresSistema} trabajadores (${Math.round(totalEnNomina/totalTrabajadoresSistema*100)}%) - Faltan ${faltantes}`;
    }
    
    html += `<div class="alert alert-info mb-3" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 10px;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-chart-pie me-2"></i>
                        <strong style="color: ${coberturaColor};">${coberturaTexto}</strong>
                    </div>
                    <div>
                        <span class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;">
                            <i class="fas fa-users me-1"></i> Total sistema: ${totalTrabajadoresSistema}
                        </span>
                        <span class="badge ms-2" style="background: rgba(16, 185, 129, 0.2); color: #10b981;">
                            <i class="fas fa-check-circle me-1"></i> En nómina: ${totalEnNomina}
                        </span>
                        <span class="badge ms-2" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                            <i class="fas fa-user-plus me-1"></i> Faltan: ${faltantes}
                        </span>
                    </div>
                </div>
                <hr class="my-2" style="border-color: rgba(255,255,255,0.1);">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Los trabajadores en <span style="color: #ef4444;">ROJO</span> ya están en la nómina. 
                    Los de <span style="color: #10b981;">VERDE</span> están disponibles para agregar.
                </small>
            </div>`;
    
    if (trabajadoresDisponibles.length === 0) {
        html += '<div class="text-center p-4 text-white-50" style="background: rgba(16, 185, 129, 0.05); border-radius: 12px; margin-bottom: 15px;">';
        html += '<i class="fas fa-check-circle fa-3x mb-2 text-success"></i>';
        html += '<br><strong>Todos los trabajadores ya están incluidos en la nómina</strong>';
        html += '<br><small>No hay trabajadores disponibles para agregar</small>';
        html += '</div>';
    } else {
        html += `<div class="mb-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-user-plus me-1" style="color: #10b981;"></i> Trabajadores DISPONIBLES para agregar (${trabajadoresDisponibles.length}):</strong>
                    <button type="button" class="btn-seleccionar-todos-sm" style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 6px; padding: 4px 10px; font-size: 0.7rem; color: #10b981;">
                        <i class="fas fa-check-double me-1"></i> Seleccionar todos
                    </button>
                </div>`;
        
        trabajadoresDisponibles.forEach(function(t) {
            var isSelected = trabajadoresSeleccionadosAuto.some(function(s) { return s.id == t.id; });
            var centroCostoNombre = 'Sin CC';
            if (t.centro_costo_codigo && t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_codigo + ' - ' + t.centro_costo_nombre;
            } else if (t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_nombre;
            }
            
            html += `<div class="worker-item ${isSelected ? 'selected' : ''}" data-id="${t.id}" data-nombre="${t.nombre_completo}" data-codigo="${t.codigo}" data-salario-mensual="${t.salario_mensual}" data-salario-hora="${t.salario_hora_ordinaria}" data-area="${t.nombre_area || 'Sin área'}" data-centro-costo="${centroCostoNombre}" style="border-left: 3px solid #10b981;">
                <input type="checkbox" class="worker-checkbox" ${isSelected ? 'checked' : ''}>
                <div class="worker-avatar" style="background: rgba(16, 185, 129, 0.15);">
                    <i class="fas fa-user" style="color: #10b981;"></i>
                </div>
                <div class="worker-info">
                    <div class="worker-name">${t.codigo} - ${t.nombre_completo}</div>
                    <div class="worker-detail">
                        <i class="fas fa-briefcase me-1"></i> ${t.cargo || 'Sin cargo'} 
                        <i class="fas fa-building ms-2 me-1"></i> ${t.nombre_area || 'Sin área'}
                        <i class="fas fa-chart-pie ms-2 me-1"></i> ${centroCostoNombre}
                    </div>
                    <div class="worker-detail small text-muted">
                        Salario: $${parseFloat(t.salario_mensual).toFixed(2)} mensual | $${parseFloat(t.salario_hora_ordinaria).toFixed(2)}/hora
                    </div>
                </div>
                <div class="worker-dias">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #10b981;">
                        <i class="fas fa-check-circle me-1"></i> Disponible
                    </span>
                </div>
            </div>`;
        });
    }
    
    var trabajadoresYaIncluidos = trabajadores.filter(function(t) {
        return idsEnNomina.includes(parseInt(t.id));
    });
    
    if (trabajadoresYaIncluidos.length > 0) {
        html += `<div class="mt-4 pt-3 border-top border-secondary"><strong class="text-muted"><i class="fas fa-check-circle me-1"></i> Trabajadores YA INCLUIDOS en nómina (${trabajadoresYaIncluidos.length}):</strong></div>`;
        
        trabajadoresYaIncluidos.forEach(function(t) {
            var centroCostoNombre = 'Sin CC';
            if (t.centro_costo_codigo && t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_codigo + ' - ' + t.centro_costo_nombre;
            } else if (t.centro_costo_nombre) {
                centroCostoNombre = t.centro_costo_nombre;
            }
            
            html += `<div class="worker-item disabled" style="opacity: 0.6; border-left: 3px solid #ef4444; cursor: not-allowed; background: rgba(239, 68, 68, 0.05);">
                <div class="worker-avatar" style="background: rgba(239, 68, 68, 0.15);">
                    <i class="fas fa-user-check" style="color: #ef4444;"></i>
                </div>
                <div class="worker-info">
                    <div class="worker-name">${t.codigo} - ${t.nombre_completo}</div>
                    <div class="worker-detail">
                        <i class="fas fa-briefcase me-1"></i> ${t.cargo || 'Sin cargo'} 
                        <i class="fas fa-building ms-2 me-1"></i> ${t.nombre_area || 'Sin área'}
                        <i class="fas fa-chart-pie ms-2 me-1"></i> ${centroCostoNombre}
                    </div>
                </div>
                <div class="worker-dias">
                    <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                        <i class="fas fa-check-circle me-1"></i> Ya incluido
                    </span>
                </div>
            </div>`;
        });
    }
    
    $('#autoWorkerList').html(html);
    $('#selectedAutoCount').text(trabajadoresSeleccionadosAuto.length);
    $('#btnConfirmarAgregarAuto').prop('disabled', trabajadoresSeleccionadosAuto.length === 0);
}

$(document).on('click', '#autoWorkerList .worker-item', function() {
    if ($(this).hasClass('disabled')) return;
    var id = $(this).data('id');
    var idx = trabajadoresSeleccionadosAuto.findIndex(function(s) { return s.id == id; });
    
    if (idx >= 0) {
        trabajadoresSeleccionadosAuto.splice(idx, 1);
    } else {
        trabajadoresSeleccionadosAuto.push({
            id: id,
            nombre: $(this).data('nombre'),
            codigo: $(this).data('codigo'),
            salario_mensual: parseFloat($(this).data('salario-mensual')),
            salario_hora: parseFloat($(this).data('salario-hora')),
            area: $(this).data('area'),
            centro_costo: $(this).data('centro-costo')
        });
    }
    renderAutoWorkerList();
});

$('#filterAutoArea, #filterAutoCC, #searchAutoWorker').on('change input', function() {
    cargarTrabajadoresDisponibles();
});

$('#modalAgregarTrabajadoresAuto').on('show.bs.modal', function() {
    trabajadoresSeleccionadosAuto = [];
    cargarTrabajadoresDisponibles();
});

$('#btnConfirmarAgregarAuto').on('click', function() {
    if (trabajadoresSeleccionadosAuto.length === 0) return;
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Agregando...',
        text: 'Procesando ' + trabajadoresSeleccionadosAuto.length + ' trabajadores',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    var datos = {
        agregar_trabajadores_auto: 1,
        trabajadores: trabajadoresSeleccionadosAuto.map(function(w) { return w.id; }),
        periodo_desde: '<?php echo $periodo_desde; ?>',
        periodo_hasta: '<?php echo $periodo_hasta; ?>',
        tipo_descuento: activeTipoDescuento
    };
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: datos,
        success: function(r) {
            if (r.success) {
                Swal.fire({
                    title: '<i class="fas fa-check-circle text-success me-2"></i> Completado',
                    html: 'Se agregaron <strong>' + r.agregados + '</strong> trabajadores a la nómina.',
                    icon: 'success',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Recargar'
                }).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: r.error || 'No se pudieron agregar los trabajadores',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error de conexión',
                text: 'No se pudo completar la solicitud',
                icon: 'error',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
        }
    });
});

function limpiarFiltroContrario(modalId, filtroQueCambio) {
    if (filtroQueCambio === 'area') {
        $(`#filter${modalId}CC`).val('');
    } else if (filtroQueCambio === 'cc') {
        $(`#filter${modalId}Area`).val('');
    }
    var searchVal = $(`#search${modalId}Worker`).val();
    if (modalId === 'Extra') renderExtraWorkerList(searchVal);
    else if (modalId === 'Vac') renderWorkerList(searchVal);
    else if (modalId === 'Bono') renderBonoWorkerList(searchVal);
}

$('#filtroArea').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroTrabajador').val('');
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

$('#filtroCentroCosto').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroTrabajador').val('');
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

$('#filtroTrabajador').on('change', function() {
    if ($(this).val() !== '') {
        $('#filtroArea').val('');
        $('#filtroCentroCosto').val('');
        $('#filtroCuenta').val('');
        $('#filtroAcumulaVacaciones').val('');
        $('#filtroRangoVacaciones').val('');
        
        $('#filtroRangoVacaciones').prop('disabled', false);
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
        
        if (nominasTable) aplicarFiltros();
    } else {
        if (nominasTable) aplicarFiltros();
    }
});

function limpiarFiltrosModal(modalId, filtroActivo) {
    var areaId = '#filter' + modalId + 'Area';
    var ccId = '#filter' + modalId + 'CC';
    var searchId = '#search' + modalId + 'Worker';
    
    if (filtroActivo === 'area') {
        $(ccId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'cc') {
        $(areaId).val('');
        $(searchId).val('');
    } else if (filtroActivo === 'search') {
        $(areaId).val('');
        $(ccId).val('');
    }
    
    var term = $(searchId).val();
    if (modalId === 'Extra') renderExtraWorkerList(term);
    else if (modalId === 'Vac') renderWorkerList(term);
    else if (modalId === 'Bono') renderBonoWorkerList(term);
}

$('#filterExtraArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Extra', 'area');
    }
});
$('#filterExtraCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Extra', 'cc');
    }
});
$('#searchExtraWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Extra', 'search');
    } else {
        renderExtraWorkerList('');
    }
});

$('#filterVacArea').on('change', function() {
    if ($(this).val() !== '') {
        $('#filterVacCC').val('');
        $('#searchWorker').val('');
        renderWorkerList('');
    }
});

$('#filterVacCC').on('change', function() {
    if ($(this).val() !== '') {
        $('#filterVacArea').val('');
        $('#searchWorker').val('');
        renderWorkerList('');
    }
});

$('#searchWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        $('#filterVacArea').val('');
        $('#filterVacCC').val('');
    }
    renderWorkerList(term);
});

$('#filterBonoArea').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Bono', 'area');
    }
});
$('#filterBonoCC').on('change', function() {
    if ($(this).val() !== '') {
        limpiarFiltrosModal('Bono', 'cc');
    }
});
$('#searchBonoWorker').on('input', function() {
    var term = $(this).val();
    if (term.trim() !== '') {
        limpiarFiltrosModal('Bono', 'search');
    } else {
        renderBonoWorkerList('');
    }
});



// Reemplazar la función cargarModalEdicion completa
function cargarModalEdicion($row) {
    console.log('=== CARGANDO MODAL DE EDICIÓN ===');
    console.log('Fila recibida:', $row);
    
    // Ocultar mensaje de contabilizada por defecto
    $('#modalContabilizadaWarning').hide();
    
    // Obtener el ID de forma robusta
    var id = $row.attr('data-id') || $row.data('id');
    if (!id) {
        console.error('No se pudo obtener el ID de la fila');
        return;
    }
    console.log('ID del trabajador:', id);
    
    // Limpiar el cuerpo del modal antes de cargar nuevos datos
    $('#modalEdicionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando datos...</div>');
    
    // [RESOLUCIÓN ULTRA-REFORZADA PARA COLUMNAS FIJAS]
    // Filtramos para ignorar cualquier fila que pertenezca a los clones de FixedColumns
    var $realRow = $('#tablaNominas tbody tr[data-id="' + id + '"]').filter(function() {
        return $(this).closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length === 0;
    }).first();
    
    if ($realRow.length > 0) {
        $row = $realRow;
    }
    
    // Verificar que la fila tenga datos válidos
    if ($row.length === 0) {
        console.error('No se encontró la fila para el ID:', id);
        $('#modalEdicionBody').html('<div class="alert alert-danger">Error: No se encontraron datos para este trabajador</div>');
        return;
    }

    var actual = (window.editCurrentRowIndex !== undefined && window.editCurrentRowIndex !== null) ? (window.editCurrentRowIndex + 1) : 1;
    var total = (window.editVisibleRowsIds !== undefined && window.editVisibleRowsIds.length > 0) ? window.editVisibleRowsIds.length : 1;
    
    $('#modalRegistroContador').html('<i class="fas fa-list-ol me-1"></i> Registro: ' + actual + ' de ' + total);

    var tipo = tipoNomina;
    var trabajadorId = $row.data('trabajador-id');
    var nombre = $row.find('td:eq(2)').text().trim();
    var fotoRuta = $row.data('foto-ruta') || '';
    
    var codigo = $row.data('codigo') || $row.find('td:eq(0)').text().trim() || 'S/D';
    var ci = $row.data('ci') || $row.find('td:eq(1)').text().trim() || '';
    var area = $row.data('area') || $row.find('td:eq(3)').text().trim() || 'S/D';
    var cargo = $row.data('cargo') || $row.find('td:eq(4)').text().trim() || 'S/D';
    var centroCosto = $row.data('centro-costo') || $row.find('td:eq(5)').text().trim() || 'S/D';
    var escalaRomana = $row.data('escala-romana') || 'S/D';
    var salarioMensual = parseFloat($row.data('salario-mensual')) || 0;
    var salarioHora = parseFloat($row.data('salario-hora')) || 0;
    
    var analisisCI = analizarCubanCI(ci);
    var edadLabel = analisisCI.edad !== 'S/D' ? analisisCI.edad + ' años' : 'S/D';
    var sexoLabel = analisisCI.sexo;
    var fechaNacLabel = analisisCI.fechaNac;

    // Construir la URL de la foto
    var fotoUrl = '';
    if (fotoRuta && fotoRuta.trim() !== '') {
        if (fotoRuta.indexOf('assets/') === 0 || fotoRuta.indexOf('uploads/') === 0) {
            fotoUrl = '../' + fotoRuta;
        } else if (fotoRuta.startsWith('data:image')) {
            fotoUrl = fotoRuta;
        } else {
            fotoUrl = '../assets/imagenes/trabajadores/' + fotoRuta;
        }
    } else {
        fotoUrl = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';
    }
    
    var originalValues = {};
    
    // [FIX NOMINAS MIXTAS] En ajuste puede haber filas contabilizadas y borradores en el mismo período,
    // por eso el estado se detecta POR FILA (badge "Contab.") y no con el flag global de la página.
    var esContabilizada = contabilizada || $row.has('.badge-contabilizado').length > 0;

    var isReadOnlyAttr = esContabilizada ? 'readonly' : '';
    var isDisabledAttr = esContabilizada ? 'disabled' : '';
    
    if (esContabilizada) {
        $('#btnModalActualizar').hide();
        $('#btnModalReset').hide();
        $('#modalContabilizadaWarning').show();
    } else {
        $('#btnModalActualizar').show();
        $('#btnModalReset').show();
        $('#modalContabilizadaWarning').hide();
    }
    
    var html = '<div class="d-flex align-items-center mb-3">';
    if (fotoUrl && !fotoUrl.startsWith('data:image/svg')) {
        html += '<div class="flex-shrink-0">';
        html += '<img src="' + fotoUrl + '" alt="Foto" class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #60a5fa;" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E\'; width:60px; height:60px;">';
        html += '</div>';
    } else {
        html += '<div class="flex-shrink-0">';
        html += '<div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6, #8b5cf6);">';
        html += '<i class="fas fa-user fa-lg text-white"></i>';
        html += '</div></div>';
    }
    html += '<div class="flex-grow-1 ms-3"><h5 class="mb-0">' + escapeHtml(nombre) + '</h5>';
    var workerObj = trabajadores.find(function(t) { return parseInt(t.id) === parseInt(trabajadorId); });
    if (workerObj && workerObj.cargo) {
        html += '<small class="text-white-50 d-block"><i class="fas fa-briefcase me-1"></i>' + escapeHtml(workerObj.cargo) + '</small>';
    }
    html += '<small class="text-white-50 d-block mt-1" style="font-size: 0.75rem; line-height: 1.4;">';
    html += '<strong>Edad:</strong> <span class="text-warning">' + edadLabel + '</span> | ';
    html += '<strong>Sexo:</strong> <span class="text-warning">' + sexoLabel + '</span> | ';
    html += '<strong>F. Nac:</strong> <span class="text-warning">' + fechaNacLabel + '</span> | ';
    html += '<strong>No. CI:</strong> <span class="text-warning">' + escapeHtml(ci) + '</span>';
    html += '</small>';
    html += '</div></div>';
    
    html += `
        <div class="row g-2 mb-4">
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height: 100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-building me-1 text-primary"></i> Datos del Trabajador</small>
                    <span class="text-white small d-block"><strong>Código:</strong> <span class="text-warning">${escapeHtml(codigo)}</span></span>
                    <span class="text-white small d-block mt-1"><strong>CI:</strong> <span class="text-warning">${escapeHtml(ci)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height: 100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-briefcase me-1 text-info"></i> Cargo</small>
                    <span class="text-white small d-block"><strong>Cargo:</strong> <span class="text-warning">${escapeHtml(cargo)}</span></span>
                    <span class="text-white small d-block mt-1"><strong>Área:</strong> <span class="text-warning">${escapeHtml(area)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height: 100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-chart-pie me-1 text-info"></i> Centro Costo</small>
                    <span class="text-white small d-block"><strong>Centro Costo:</strong> <span class="text-warning">${escapeHtml(centroCosto)}</span></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2 rounded border" style="background: rgba(255, 255, 255, 0.02); border-color: rgba(255,255,255,0.08) !important; height: 100%;">
                    <small class="text-white-50 d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-layer-group me-1 text-warning"></i> Escala Salarial</small>
                    <span class="text-white small d-block"><strong>Grupo:</strong> <span class="text-warning">${escalaRomana}</span><br><strong>Salario:</strong> <span class="text-warning">$${salarioMensual.toFixed(2)} / $${salarioHora.toFixed(2)}h</span></span>
                </div>
            </div>
        </div>
    `;
    
    html += '<input type="hidden" id="editId" value="' + id + '">';

    // Obtener valores actuales de la fila
    var horas = 0, descuentos = 0, nocturnas = 0, feriados = 0, otrosPagos = 0;
    var totalDevengadoActual = 0, netoActual = 0;
    
    // Extraer valores según el tipo de nómina
    if (tipo === 'automatica' || tipo === 'extraordinaria') {
        // Horas - buscar en diferentes lugares
        var $horasInput = $row.find('.edit-horas');
        if ($horasInput.length) {
            horas = parseNumber($horasInput.val());
        } else {
            horas = parseFloat($row.find('.col-horas').text().replace(/,/g, '')) || 0;
        }
        console.log('Horas obtenidas:', horas);
        
        // Descuentos
        var $descuentosInput = $row.find('.edit-descuentos');
        if ($descuentosInput.length) {
            descuentos = parseNumber($descuentosInput.val());
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        console.log('Descuentos obtenidos:', descuentos);
        
        totalDevengadoActual = parseFloat($row.find('.total-devengado').text().replace('$', '').replace(/,/g, '')) || 0;
        netoActual = parseFloat($row.find('.neto').text().replace('$', '').replace(/,/g, '')) || 0;
        console.log('Total devengado actual:', totalDevengadoActual);
        console.log('Neto actual:', netoActual);
        
        if (tipo === 'automatica') {
            var $feriadosInput = $row.find('.edit-feriados');
            if ($feriadosInput.length) {
                feriados = parseNumber($feriadosInput.val());
            } else {
                feriados = parseFloat($row.find('.col-feriados-dias').text().replace(/,/g, '')) || 0;
            }
            console.log('Feriados obtenidos:', feriados);
            
            var $otrosPagosInput = $row.find('.edit-otros-pagos');
            if ($otrosPagosInput.length) {
                otrosPagos = parseNumber($otrosPagosInput.val());
            } else {
                otrosPagos = parseFloat($row.find('.col-otros-pagos').text().replace('$', '').replace(/,/g, '')) || 0;
            }
            console.log('Otros pagos obtenidos:', otrosPagos);
            originalValues = { horas: horas, feriados: feriados, otrosPagos: otrosPagos, descuentos: descuentos };
        } else {
            var $nocturnasInput = $row.find('.edit-nocturnas');
            if ($nocturnasInput.length) {
                nocturnas = parseNumber($nocturnasInput.val());
            } else {
                nocturnas = parseFloat($row.find('.col-nocturnas').text().replace(/,/g, '')) || 0;
            }
            console.log('Nocturnas obtenidas:', nocturnas);
            originalValues = { horas: horas, nocturnas: nocturnas, descuentos: descuentos };
        }
        
        var disabledAttr = (netoActual <= 0) ? 'disabled' : '';
        
        html += '<div class="row">';
        html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-clock me-1"></i>Horas laboradas</label>';
        html += '<input type="number" step="0.5" class="form-control edit-field" id="editHoras" value="' + horas.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
        
        if (tipo === 'extraordinaria') {
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-moon me-1" style="color:#8b5cf6;"></i>Horas nocturnas (Ley 116: +25%)</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editNocturnas" value="' + nocturnas.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '</div>';
            
            html += '<div class="row mt-2">';
            html += '<div class="col-md-12 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1"></i>Descuentos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + ' ' + disabledAttr + '></div>';
            html += '</div>';
        } else {
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-calendar-day me-1"></i>Días feriados</label>';
            html += '<input type="number" step="0.5" class="form-control edit-field" id="editFeriados" value="' + feriados.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '</div>';
            
            html += '<div class="row">';
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-coins me-1"></i>Otros pagos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editOtrosPagos" value="' + otrosPagos.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
            html += '<div class="col-md-6 mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1"></i>Descuentos</label>';
            html += '<input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + ' ' + disabledAttr + '></div>';
            html += '</div>';
        }
        
        html += '<div class="card mt-3 text-warning" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);">';
        html += '<div class="card-body">';
        html += '<h6 class="card-title text-success" style="font-size: 0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>';
        html += '<div class="row text-center mt-2">';
        html += '<div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$' + totalDevengadoActual.toFixed(2) + '</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Deducciones</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$' + netoActual.toFixed(2) + '</h5></div>';
        html += '</div></div></div>';
        
    } else if (tipo === 'bono') {
        var montoValido = 0;
        var $bonoInput = $row.find('.edit-bono');
        if ($bonoInput.length) {
            montoValido = parseNumber($bonoInput.val());
        } else {
            montoValido = parseFloat($row.find('.bono-val-cell').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        
        var $descuentosInput = $row.find('.edit-descuentos');
        if ($descuentosInput.length) {
            descuentos = parseNumber($descuentosInput.val()); 
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace('$', '').replace(/,/g, '')) || 0;
        }
        
        // CORRECCIÓN CLAVE: Buscar la segunda columna .col-nombre (Concepto), no la primera (Nombre del Trabajador)
        var descripcionBono = '';
        var $colsNombre = $row.find('.col-nombre');
        if ($colsNombre.length > 1) {
            descripcionBono = $colsNombre.eq(1).text().trim();
        } else {
            // Alternativa en caso de que la estructura de la tabla varíe
            descripcionBono = $row.find('td').eq(7).text().trim();
        }
        if (descripcionBono === '-') descripcionBono = '';
        
        originalValues = { monto: montoValido, descuentos: descuentos, descripcion: descripcionBono };
        
        html += `
            <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.08);">
                <label class="form-label d-block mb-2"><i class="fas fa-calculator me-1 text-warning"></i> Método de Cálculo del Bono</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="editTipoBonoRadio" id="editTipoBonoPorciento" value="porciento" ${isDisabledAttr}>
                    <label class="form-check-label small text-white" for="editTipoBonoPorciento">Porcentaje (%) del Salario Básico</label>
                </div>
                <div class="form-check form-check-inline ms-3">
                    <input class="form-check-input" type="radio" name="editTipoBonoRadio" id="editTipoBonoFijo" value="fijo" checked ${isDisabledAttr}>
                    <label class="form-check-label small text-white" for="editTipoBonoFijo">Monto Fijo ($)</label>
                </div>
            </div>
        `;
        
        html += '<div class="row">';
        html += `
            <div class="col-md-4 mb-3" id="wrapperEditPorciento" style="display: none;">
                <label class="form-label"><i class="fas fa-percent me-1 text-info"></i>Porcentaje del Bono</label>
                <div class="input-group">
                    <input type="number" step="0.1" min="0" class="form-control edit-field" id="editPorcientoBono" value="0" ${isReadOnlyAttr}>
                    <span class="input-group-text bg-dark text-info">%</span>
                </div>
            </div>
            <div class="col-md-4 mb-3" id="wrapperEditCoeficiente" style="display: none;">
                <label class="form-label"><i class="fas fa-calculator me-1 text-warning"></i>Coeficiente</label>
                <input type="number" step="0.01" min="0" class="form-control edit-field" id="editCoeficienteBono" value="0" ${isReadOnlyAttr}>
            </div>
            <div class="col-md-4 mb-3" id="wrapperEditMonto">
                <label class="form-label"><i class="fas fa-dollar-sign me-1 text-success"></i>Monto del Bono</label>
                <input type="number" step="0.01" min="0" class="form-control edit-field" id="editMontoBono" value="${montoValido.toFixed(2)}" ${isReadOnlyAttr}>
                <small class="text-white-50">Monto directo a devengar</small>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label"><i class="fas fa-pen me-1"></i>Concepto del Bono</label>
                <input type="text" class="form-control edit-field" id="editDescripcionBono" value="${escapeHtml(descripcionBono)}" ${isReadOnlyAttr} placeholder="Ej: Productividad...">
            </div>
        `;
        html += '</div>';
        
        html += `<div class="mb-3"><label class="form-label"><i class="fas fa-minus-circle me-1"></i>Otros Descuentos</label>`;
        html += '<input type="number" step="0.01" class="form-control edit-field" id="editDescuentosBono" value="' + descuentos.toFixed(2) + '" ' + isReadOnlyAttr + '></div>';
        
        html += '<div class="card mt-3 text-success" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);"><div class="card-body"><h6 class="card-title" style="font-size: 0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>';
        html += '<div class="row text-center mt-2"><div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$' + montoValido.toFixed(2) + '</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Deducciones (CESS+Impuesto)</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>';
        html += '<div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$' + montoValido.toFixed(2) + '</h5></div></div></div></div>';
        
    } else if (tipo === 'vacaciones') {
        var dias = 0;
        var $diasInput = $row.find('.edit-dias');
        if ($diasInput.length) {
            dias = parseNumber($diasInput.val());
        } else {
            dias = parseFloat($row.find('.col-horas').first().text().replace(/,/g, '')) || 0;
        }
        var diasAcumulados = $row.data('dias-acumulados') || 0;
        originalValues = { dias: dias };
        html += '<div class="mb-3"><label class="form-label"><i class="fas fa-umbrella-beach me-1"></i>Días a tomar</label>';
        html += '<input type="number" step="0.5" class="form-control edit-field" id="editDias" value="' + dias.toFixed(2) + '" max="' + diasAcumulados + '" ' + isReadOnlyAttr + '>';
        html += '<small class="text-info d-block mt-1">Días acumulados disponibles: <span id="disponiblesDisplay">' + diasAcumulados + '</span></small></div>';
        html += '<div class="card mt-3 bg-dark text-success" style="border-color: rgba(255,255,255,0.05);"><div class="card-body"><h6 class="card-title" style="font-size: 0.85rem;">Previsualización</h6>';
        html += '<div class="row text-center mt-2"><div class="col-6"><small class="text-white-50">Importe vacaciones</small><h5 id="previewDevengado" class="text-info mt-1">$0.00</h5></div>';
        html += '<div class="col-6"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$0.00</h5></div></div></div></div>';
        
    } else if (tipo === 'ajuste') {
        var montoVal = parseNumber($row.find('.total-devengado').text());
        var descripcion = $row.find('.col-nombre').eq(1).text().trim(); // Segunda columna de nombre es el concepto
        if (descripcion === '-') descripcion = '';
        var $descInput = $row.find('.edit-descuentos');
        var descuentos = 0;
        if ($descInput.length) {
            descuentos = parseNumber($descInput.val());
        } else {
            descuentos = parseFloat($row.find('.col-otros-descuentos').text().replace(/[^\d.-]/g, '')) || 0;
        }
        
        originalValues = { monto: montoVal, descuentos: descuentos, descripcion: descripcion };

        html += `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-dollar-sign me-1 text-success"></i>Monto del Ajuste</label>
                    <input type="number" step="0.01" class="form-control edit-field" id="editMontoAjuste" value="${montoVal.toFixed(2)}" ${isReadOnlyAttr}>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-minus-circle me-1 text-danger"></i>Otras Ret.</label>
                    <input type="number" step="0.01" class="form-control edit-field" id="editDescuentos" value="${descuentos.toFixed(2)}" ${isReadOnlyAttr}>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label"><i class="fas fa-pen me-1"></i>Concepto / Motivo</label>
                    <input type="text" class="form-control edit-field" id="editConceptoAjuste" value="${escapeHtml(descripcion)}" ${isReadOnlyAttr}>
                </div>
            </div>
            <div class="card mt-3 text-warning" style="background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.05);">
                <div class="card-body">
                    <h6 class="card-title text-success" style="font-size: 0.85rem;"><i class="fas fa-chart-line me-1"></i>Previsualización en tiempo real</h6>
                    <div class="row text-center mt-2">
                        <div class="col-4"><small class="text-white-50">Total Devengado</small><h5 id="previewDevengado" class="text-info mt-1">$${montoVal.toFixed(2)}</h5></div>
                        <div class="col-4"><small class="text-white-50">Deducciones</small><h5 id="previewDeducciones" class="text-warning mt-1">$0.00</h5></div>
                        <div class="col-4"><small class="text-white-50">Neto a Pagar</small><h5 id="previewNeto" class="text-success mt-1">$0.00</h5></div>
                    </div>
                </div>
            </div>
        `;
    }
    
    $('#modalEdicionBody').html(html);
    $('#modalEdicionRapida').data('originalValues', originalValues);
    console.log('Valores originales guardados en modal:', originalValues);
    
    // Re-inicializar los eventos de previsualización
    if (tipo === 'automatica') {
        $('#editHoras, #editFeriados, #editOtrosPagos, #editDescuentos').off('input').on('input', function() {
            recalcularPreviewAuto();
        });
        recalcularPreviewAuto();
    } else if (tipo === 'extraordinaria') {
        $('#editHoras, #editNocturnas, #editDescuentos').off('input').on('input', function() {
            recalcularPreviewAutoExtraordinaria();
        });
        recalcularPreviewAutoExtraordinaria();
    } else if (tipo === 'bono') {
        $('input[name="editTipoBonoRadio"]').off('change').on('change', function() {
            var metodo = $(this).val();
            if (metodo === 'porciento') {
                $('#wrapperEditPorciento').show();
                $('#wrapperEditCoeficiente').show();
                $('#wrapperEditMonto').hide();
            } else {
                $('#wrapperEditPorciento').hide();
                $('#wrapperEditCoeficiente').hide();
                $('#wrapperEditMonto').show();
            }
            recalcularPreviewBono();
        });
        
        $('#editPorcientoBono, #editCoeficienteBono, #editMontoBono, #editDescuentosBono, #editDescripcionBono').off('input').on('input', function() {
            recalcularPreviewBono();
        });
        recalcularPreviewBono();
    } else if (tipo === 'vacaciones') {
        $('#editDias').off('input').on('input', function() {
            recalcularPreviewVacaciones();
        });
        recalcularPreviewVacaciones();
    } else if (tipo === 'ajuste') {
        $('#editMontoAjuste, #editConceptoAjuste, #editDescuentos').on('input', recalcularPreviewAjuste);
        recalcularPreviewAjuste();
    }
}



// ==========================================
// BUSCADOR EN MODAL DE EDICIÓN (CORREGIDO)
// ==========================================

// Variable global para almacenar la referencia a cargarModalEdicion
window.cargarModalEdicionRef = null;

// Función para buscar trabajadores usando la API de DataTables correctamente
function buscarTrabajadoresEnTabla(termino) {
    if (!termino || termino.length < 2) {
        $('#resultadosBusquedaModal').hide();
        return [];
    }
    
    var terminoLower = termino.toLowerCase();
    var resultados = [];
    
    var table = $('#tablaNominas').DataTable();
    var allRows = table.rows({ search: 'applied' }).nodes();
    
    for (var i = 0; i < allRows.length; i++) {
        var $row = $(allRows[i]);
        
        if ($row.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
            continue;
        }
        
        var $codigo = $row.find('td:eq(0)');
        var $ci = $row.find('td:eq(1)');
        var $nombre = $row.find('td:eq(2)');
        
        if ($codigo.length === 0) continue;
        
        var codigo = $codigo.text().trim().toLowerCase();
        var ci = $ci.text().trim().toLowerCase();
        var nombre = $nombre.text().trim().toLowerCase();
        var id = $row.data('id');
        
        if (!id) continue;
        
        if (nombre.includes(terminoLower) || codigo.includes(terminoLower) || ci.includes(terminoLower)) {
            resultados.push({
                id: id,
                nombre: $nombre.text().trim(),
                codigo: $codigo.text().trim(),
                ci: $ci.text().trim(),
                cargo: $row.data('cargo') || '',
                area: $row.data('area') || '',
                rowIndex: i
            });
        }
    }
    
    return resultados;
}

// Obtener la fila real del DataTable
function obtenerFilaReal(trabajadorId) {
    var table = $('#tablaNominas').DataTable();
    var rows = table.rows().nodes();
    
    for (var i = 0; i < rows.length; i++) {
        var $row = $(rows[i]);
        if ($row.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
            continue;
        }
        if ($row.data('id') == trabajadorId) {
            return { $row: $row, index: i };
        }
    }
    return null;
}

// Navegar a un trabajador específico por ID
function navegarATrabajadorPorId(trabajadorId) {
    console.log('Navegando a trabajador ID:', trabajadorId);
    
    $('#modalEdicionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando datos del trabajador...</div>');
    
    var table = $('#tablaNominas').DataTable();
    var resultado = obtenerFilaReal(trabajadorId);
    
    if (!resultado) {
        Swal.fire({
            title: 'Trabajador no encontrado',
            text: 'No se pudo encontrar el trabajador en la nómina actual.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    var idx = resultado.index;
    var $row = resultado.$row;
    var pageLength = table.page.len();
    var currentPage = table.page();
    var targetPage = Math.floor(idx / pageLength);
    
    var actualizarModal = function() {
        var $filaActual = $('#tablaNominas').find('tr[data-id="' + trabajadorId + '"]').filter(function() {
            return $(this).closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length === 0;
        }).first();
        
        if ($filaActual.length === 0) {
            console.error('No se encontró la fila para actualizar');
            $('#modalEdicionBody').html('<div class="alert alert-danger">Error: No se encontraron datos para este trabajador</div>');
            return;
        }
        
        var allVisibleRows = table.rows({ search: 'applied' }).nodes();
        var visibleIds = [];
        var newPos = -1;
        
        for (var i = 0; i < allVisibleRows.length; i++) {
            var $visibleRow = $(allVisibleRows[i]);
            if ($visibleRow.closest('.dtfc-fixed-left, .dtfc-fixed-right, .DTFC_LeftWrapper, .DTFC_RightWrapper').length > 0) {
                continue;
            }
            var rowId = $visibleRow.data('id');
            if (rowId) {
                visibleIds.push(rowId);
                if (rowId == trabajadorId) {
                    newPos = visibleIds.length - 1;
                }
            }
        }
        
        window.editCurrentRowId = trabajadorId;
        window.editCurrentRowIndex = newPos;
        window.editVisibleRowsIds = visibleIds;
        
        var actual = newPos + 1;
        var total = visibleIds.length;
        $('#modalRegistroContador').html('<i class="fas fa-list-ol me-1"></i> Registro: ' + actual + ' de ' + total);
        
        // Usar la función global si está disponible
        if (typeof window.cargarModalEdicionRef === 'function') {
            window.cargarModalEdicionRef($filaActual);
        } else if (typeof cargarModalEdicion === 'function') {
            cargarModalEdicion($filaActual);
        } else {
            console.error('cargarModalEdicion no está definida');
            // Fallback: recargar la página
            location.reload();
        }
    };
    
    if (currentPage !== targetPage) {
        table.page(targetPage).draw('page');
        setTimeout(actualizarModal, 400);
    } else {
        actualizarModal();
    }
}

// Renderizar resultados de búsqueda
function renderizarResultadosBusqueda(resultados, termino) {
    var $container = $('#resultadosBusquedaModal');
    
    if (resultados.length === 0) {
        $container.html('<div class="p-3 text-center text-white-50"><i class="fas fa-search me-2"></i>No se encontraron resultados para "' + escapeHtml(termino) + '"</div>');
        $container.show();
        return;
    }
    
    var html = '';
    resultados.forEach(function(r, idx) {
        html += `
            <div class="resultado-item" data-id="${r.id}" data-index="${idx}">
                <div class="resultado-nombre">
                    <i class="fas fa-user me-2" style="color: #60a5fa;"></i>
                    ${escapeHtml(r.codigo)} - ${escapeHtml(r.nombre)}
                </div>
                <div class="resultado-detalle">
                    <i class="fas fa-id-card"></i> CI: ${escapeHtml(r.ci)}
                    ${r.cargo ? '<i class="fas fa-briefcase ms-2"></i> ' + escapeHtml(r.cargo) : ''}
                    ${r.area ? '<i class="fas fa-building ms-2"></i> ' + escapeHtml(r.area) : ''}
                </div>
            </div>
        `;
    });
    
    $container.html(html);
    $container.show();
}

function actualizarSeleccionResultados(items) {
    items.removeClass('active');
    if (indiceSeleccionadoModal >= 0 && indiceSeleccionadoModal < items.length) {
        $(items[indiceSeleccionadoModal]).addClass('active');
        $(items[indiceSeleccionadoModal])[0].scrollIntoView({ block: 'nearest' });
    }
}

// Inicializar eventos del buscador (esto debe ir dentro del document.ready)
$(document).ready(function() {
	
// Manejador para agregar personas directamente en Nómina de Ajuste
$(document).on('click', '#btnAgregarPersonasAjusteDirecto', function() {
    // 1. Detectar el tipo de descuento que ya tiene la nómina en pantalla
    // Buscamos en la primera fila de la tabla el atributo data-tipo-descuento
    let tipoDescuentoExistente = $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    
    // 2. Asignar ese descuento a la variable temporal para que el modal lo use
    window.tempSelectedDiscount = tipoDescuentoExistente;
    
    // 3. Abrir el modal de Ajuste directamente sin pasar por el selector de CESS
    var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAjuste'));
    modalObj.show();
});

// Ajuste adicional: Asegurarse de que el modal de Ajuste se limpie correctamente al abrirse
$('#modalAjuste').on('show.bs.modal', function() {
    // Si venimos por el camino directo, la lista de seleccionados debe resetearse
    // pero el tipo de descuento debe tomarse de lo que detectamos arriba
    if (window.tempSelectedDiscount) {
        $('#tipoDescuentoAjuste').val(window.tempSelectedDiscount);
    }
});

    // Guardar referencia a cargarModalEdicion si existe
    if (typeof cargarModalEdicion === 'function') {
        window.cargarModalEdicionRef = cargarModalEdicion;
    }
    
    if ($('#buscadorTrabajadorModal').length === 0) {
        console.log('Elemento buscadorTrabajadorModal no encontrado');
        return;
    }
    
    var timeoutBusquedaModal = null;
    var resultadosActivosModal = [];
    var indiceSeleccionadoModal = -1;
    
    $('#buscadorTrabajadorModal').off('input').on('input', function() {
        var termino = $(this).val().trim();
        clearTimeout(timeoutBusquedaModal);
        
        if (termino.length < 2) {
            $('#resultadosBusquedaModal').hide();
            return;
        }
        
        timeoutBusquedaModal = setTimeout(function() {
            var resultados = buscarTrabajadoresEnTabla(termino);
            resultadosActivosModal = resultados;
            indiceSeleccionadoModal = -1;
            renderizarResultadosBusqueda(resultados, termino);
        }, 300);
    });

    $('#buscadorTrabajadorModal').off('keydown').on('keydown', function(e) {
        if (!$('#resultadosBusquedaModal').is(':visible')) return;
        
        var items = $('.resultado-item');
        var totalItems = items.length;
        if (totalItems === 0) return;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                indiceSeleccionadoModal = Math.min(indiceSeleccionadoModal + 1, totalItems - 1);
                actualizarSeleccionResultados(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                indiceSeleccionadoModal = Math.max(indiceSeleccionadoModal - 1, 0);
                actualizarSeleccionResultados(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (indiceSeleccionadoModal >= 0 && resultadosActivosModal[indiceSeleccionadoModal]) {
                    var trabajadorId = resultadosActivosModal[indiceSeleccionadoModal].id;
                    $('#resultadosBusquedaModal').hide();
                    $('#buscadorTrabajadorModal').val('');
                    navegarATrabajadorPorId(trabajadorId);
                }
                break;
            case 'Escape':
                $('#resultadosBusquedaModal').hide();
                $('#buscadorTrabajadorModal').val('');
                break;
        }
    });

    $(document).off('click', '.resultado-item').on('click', '.resultado-item', function() {
        var id = $(this).data('id');
        $('#resultadosBusquedaModal').hide();
        $('#buscadorTrabajadorModal').val('');
        navegarATrabajadorPorId(id);
    });

    $('#limpiarBuscadorModal').off('click').on('click', function() {
        $('#buscadorTrabajadorModal').val('');
        $('#resultadosBusquedaModal').hide();
    });

    $(document).off('click.buscadorModal').on('click.buscadorModal', function(e) {
        if (!$(e.target).closest('#buscadorTrabajadorModal, #resultadosBusquedaModal, .resultado-item').length) {
            $('#resultadosBusquedaModal').hide();
        }
    });

    $('#modalEdicionRapida').off('hidden.bs.modal').on('hidden.bs.modal', function() {
        $('#buscadorTrabajadorModal').val('');
        $('#resultadosBusquedaModal').hide();
    });
    
    $('#modalEdicionRapida').off('shown.bs.modal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#buscadorTrabajadorModal').focus();
        }, 200);
    });
});


function recalcularPreviewAuto() {
    var $filaOriginal = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]');
    var salarioHora = parseFloat($filaOriginal.data('salario-hora')) || 0;
    var salarioMensual = parseFloat($filaOriginal.data('salario-mensual')) || 0;
    var tipoDescuento = $filaOriginal.data('tipo-descuento') || 'total_rangos';
    var noAcumularVacaciones = parseInt($filaOriginal.data('no-acumular-vacaciones')) || 0;
    
    var horas = parseFloat($('#editHoras').val()) || 0;
    var feriados = parseFloat($('#editFeriados').val()) || 0;
    var otrosPagos = parseFloat($('#editOtrosPagos').val()) || 0;
    var descuentos = parseFloat($('#editDescuentos').val()) || 0;
    
    var factor909 = 0.0909;
    var horasJornada = 8;

    var salarioLaboral = Math.roundExcel(salarioHora * horas, 2);
    var salarioDiario = salarioMensual / 24;
    var importeFeriados = Math.roundExcel(salarioDiario * feriados * 2, 2);
    
    // =========================================================
    // CÁLCULO PROPORCIONAL VACACIONES PARA EL PREVIEW DEL MODAL
    // =========================================================
    var diasVacMes = Math.roundExcel(((horas * factor909) / horasJornada), 2);
    var importeVacMes = Math.roundExcel(diasVacMes * salarioDiario, 2);
    
    var importeVacacionesAdicional = 0;
    if (noAcumularVacaciones === 1) {
        importeVacacionesAdicional = importeVacMes;
    }
    
    var totalDevengado = Math.roundExcel(salarioLaboral + importeFeriados + otrosPagos + importeVacacionesAdicional, 2);
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(totalDevengado);
        impuesto = 0;
    } else {
        contribucion = Math.roundExcel(totalDevengado * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(totalDevengado);
    }
    
    var totalDeducciones = Math.roundExcel(contribucion + impuesto + descuentos, 2);
    var neto = Math.roundExcel(totalDevengado - totalDeducciones, 2);
    
    $('#previewDevengado').text('$' + totalDevengado.toFixed(2));
    $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
}

    function recalcularPreviewAutoExtraordinaria() {
        var salarioHora = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('salario-hora')) || 0;
        var horas = parseFloat($('#editHoras').val()) || 0;
        var horasNocturnas = parseFloat($('#editNocturnas').val()) || 0;
        var descuentos = parseFloat($('#editDescuentos').val()) || 0;
        var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('tipo-descuento') || 'total_rangos';
        
        var salarioLaboral = salarioHora * horas;
        var valorHoraNocturna = salarioHora * recargoNocturno;
        var importeNocturnas = valorHoraNocturna * horasNocturnas;
        
        var totalDevengado = salarioLaboral + importeNocturnas;
        
        var contribucion = 0, impuesto = 0;
        if (tipoDescuento === 'solo_cess') {
            contribucion = calcularCessProgresivoJS(totalDevengado);
            impuesto = 0;
        } else {
            contribucion = totalDevengado * 0.05;
            impuesto = calcularImpuestoProgresivo(totalDevengado);
        }
        var totalDeducciones = contribucion + impuesto + descuentos;
        var neto = totalDevengado - totalDeducciones;
        
        var $descuentosModal = $('#editDescuentos');
        if (neto <= 0) {
            $descuentosModal.prop('disabled', true).val(0);
        } else {
            $descuentosModal.prop('disabled', false);
        }
        
        $('#previewDevengado').text('$' + totalDevengado.toFixed(2));
        $('#previewDeducciones').text('$' + totalDeducciones.toFixed(2));
        $('#previewNeto').text('$' + neto.toFixed(2));
    }

function recalcularPreviewBono() {
    var salarioMensual = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').attr('data-salario-mensual')) || 0;
    var metodo = $('input[name="editTipoBonoRadio"]:checked').val() || 'porciento';
    var monto = 0;

    if (metodo === 'porciento') {
        var porciento = parseFloat($('#editPorcientoBono').val()) || 0;
        var coeficiente = parseFloat($('#editCoeficienteBono').val()) || 0; // LÍNEA AGREGADA
        
        // FÓRMULA UNIFICADA: (porcentaje * coeficiente) - salario_escala
        var brutoCalculado = (porciento * coeficiente) - salarioMensual;
        monto = Math.max(0, Math.roundExcel(brutoCalculado, 2));
        
        $('#editMontoBono').val(monto);
    } else {
        monto = parseFloat($('#editMontoBono').val()) || 0;
    }

    var descuentos = parseFloat($('#editDescuentosBono').val()) || 0;
    var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').attr('data-tipo-descuento') || 'total_rangos';
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(monto);
        impuesto = 0;
    } else {
        contribucion = Math.roundExcel(monto * 0.05, 2);
        impuesto = calcularImpuestoProgresivo(monto);
    }
    var netoAntesDesc = monto - (contribucion + impuesto);
    if (netoAntesDesc < 0) netoAntesDesc = 0;
    
    if (descuentos > netoAntesDesc) descuentos = netoAntesDesc;
    var neto = netoAntesDesc - descuentos;
    if (neto < 0) neto = 0;
    
    $('#previewDevengado').text('$' + monto.toFixed(2));
    $('#previewDeducciones').text('$' + (contribucion + impuesto + descuentos).toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
    
    var $descuentosInput = $('#editDescuentosBono');
    if (neto <= 0) {
        $descuentosInput.prop('disabled', true).val(0);
    } else {
        $descuentosInput.prop('disabled', false);
    }
}

function recalcularPreviewVacaciones() {
    var dias = parseFloat($('#editDias').val()) || 0;
    var salarioMensual = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('salario-mensual')) || 0;
    var diasAcumulados = parseFloat($('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('dias-acumulados')) || 0;
    var tipoDescuento = $('#tablaNominas tbody tr[data-id="' + window.editCurrentRowId + '"]').data('tipo-descuento') || 'total_rangos';
    
    console.log('Recalculando vacaciones - días:', dias, 'días acumulados:', diasAcumulados);
    
    if (dias > diasAcumulados) {
        $('#editDias').val(diasAcumulados);
        dias = diasAcumulados;
        mostrarToast('warning', 'Máximo de días disponibles: ' + diasAcumulados);
    }
    $('#disponiblesDisplay').text(diasAcumulados);
    
    var valorPorDia = salarioMensual / diasLaborables;
    var importe = dias * valorPorDia;
    var diasRestantes = diasAcumulados - dias;
    if (diasRestantes < 0) diasRestantes = 0;
    
    // Actualizar días restantes en tiempo real
    $('#previewDiasRestantes').text(diasRestantes.toFixed(2));
    $('#previewDias').text(dias.toFixed(2));
    
    var contribucion = 0, impuesto = 0;
    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(importe);
        impuesto = 0;
    } else {
        contribucion = importe * 0.05;
        impuesto = calcularImpuestoProgresivo(importe);
    }
    var neto = importe - (contribucion + impuesto);
    if (neto < 0) neto = 0;
    $('#previewDevengado').text('$' + importe.toFixed(2));
    $('#previewNeto').text('$' + neto.toFixed(2));
}

    function mostrarToast(tipo, mensaje) {
        var toastHtml = '<div class="alert alert-' + tipo + ' alert-dismissible fade show mb-3" role="alert">' + mensaje + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $('#modalEdicionBody').prepend(toastHtml);
        setTimeout(function() {
            $('.alert').alert('close');
        }, 3000);
    }

    // ==========================================
    // FUNCIÓN CENTRALIZADA PARA ENFOCAR CAMPO ADECUADO
    // ==========================================
    function enfocarCampoEdicion() {
        // Pequeño delay para asegurar que el DOM esté completamente renderizado
        setTimeout(function() {
            try {
                if (tipoNomina === 'bono') {
                    // Para nómina de bonos: enfocar el monto del bono
                    var $montoInput = $('#editMontoBono');
                    if ($montoInput.length && !$montoInput.prop('disabled') && !$montoInput.prop('readonly')) {
                        $montoInput.focus().select();
                        return;
                    }
                    // Fallback: si el monto está deshabilitado, enfocar descuentos
                    var $descuentosInput = $('#editDescuentosBono');
                    if ($descuentosInput.length && !$descuentosInput.prop('disabled')) {
                        $descuentosInput.focus().select();
                        return;
                    }
                } else if (tipoNomina === 'vacaciones') {
                    // Para vacaciones: enfocar días a tomar
                    var $diasInput = $('#editDias');
                    if ($diasInput.length && !$diasInput.prop('disabled') && !$diasInput.prop('readonly')) {
                        $diasInput.focus().select();
                        return;
                    }
                } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                    // Para automática y extraordinaria: enfocar horas laboradas
                    var $horasInput = $('#editHoras');
                    if ($horasInput.length && !$horasInput.prop('disabled') && !$horasInput.prop('readonly')) {
                        $horasInput.focus().select();
                        return;
                    }
                }
                
                // Fallback genérico: primer campo editable visible
                var $firstEditable = $('.edit-field:not([readonly]):not([disabled]):visible').first();
                if ($firstEditable.length) {
                    $firstEditable.focus().select();
                }
            } catch (e) {
                console.warn('Error al enfocar campo:', e);
            }
        }, 150);
    }

    // Enfoque automático al abrir el modal
    $('#modalEdicionRapida').on('shown.bs.modal', function () {
        enfocarCampoEdicion();
    });

    // Sincronizar y actualizar a través de AJAX
    function actualizarFilaDesdeModal(callback) {
        var id = $('#editId').val();
        var $row = $('#tablaNominas').find('tr[data-id="' + id + '"]');
        var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
        
        var valHoras, valNocturnas, valFeriados, valOtros, valDesc, valBono, valDias;
        
        if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
            valHoras = Math.max(0, parseFloat($('#editHoras').val()) || 0);
            valDesc = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);

            $('#editHoras').val(valHoras);
            $('#editDescuentos').val(valDesc);

            datos.horas_laboradas = valHoras;
            datos.descuentos = valDesc;

            if (tipoNomina === 'automatica') {
                valFeriados = Math.max(0, parseFloat($('#editFeriados').val()) || 0);
                valOtros = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
                
                $('#editFeriados').val(valFeriados);
                $('#editOtrosPagos').val(valOtros);

                datos.dias_feriados = valFeriados;
                datos.otros_salarios = valOtros;
                datos.horas_nocturnas = 0;
            } else {
                valNocturnas = Math.max(0, parseFloat($('#editNocturnas').val()) || 0);
                $('#editNocturnas').val(valNocturnas);
                
                datos.horas_nocturnas = valNocturnas;
                datos.dias_feriados = 0;
                datos.otros_salarios = 0;
            }
        
        } else if (tipoNomina === 'bono') {
			valBono = Math.max(0, parseFloat($('#editMontoBono').val()) || 0);
			valDesc = Math.max(0, parseFloat($('#editDescuentosBono').val()) || 0);
			var valDescrip = $('#editDescripcionBono').val() || '';  // ← AGREGAR ESTA LÍNEA
			datos.monto_bono = valBono;
			datos.descuentos = valDesc;
			datos.descripcion = valDescrip; 
		} else if (tipoNomina === 'vacaciones') {
            valDias = Math.max(0, parseFloat($('#editDias').val()) || 0);
            $('#editDias').val(valDias);
            datos.dias_vacaciones = valDias;
        } else if (tipoNomina === 'ajuste') {
			var valMonto = Math.max(0, parseFloat($('#editMontoAjuste').val()) || 0);
			var valConcepto = $('#editConceptoAjuste').val() || '';
			datos.monto_bono = valMonto; // Usamos el campo que tu PHP ya procesa
			datos.descripcion = valConcepto;
		}
        $('#btnModalActualizar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function(r) {
                if (r.success) {
                    if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                        $row.find('.edit-horas').val(valHoras);
                        $row.find('.edit-descuentos').val(valDesc);
                        
                        if (tipoNomina === 'automatica') {
                            $row.find('.edit-feriados').val(valFeriados);
                            $row.find('.edit-otros-pagos').val(valOtros);
                            $row.find('.edit-nocturnas').val(0);
                        } else {
                            $row.find('.edit-nocturnas').val(valNocturnas);
                            $row.find('.edit-feriados').val(0);
                            $row.find('.edit-otros-pagos').val(0);
                        }
                        $row.find('.edit-horas').trigger('input');
                        
                    } else if (tipoNomina === 'bono') {
                        $row.find('.edit-bono').val(valBono);
                        $row.find('.edit-descuentos').val(valDesc);
                        
                        // CORRECCIÓN CLAVE: Buscar la columna Concepto correcta para actualizarla
                        var $colsNombre = $row.find('.col-nombre');
                        if ($colsNombre.length > 1) {
                            $colsNombre.eq(1).text(valDescrip || '-');
                        } else {
                            $row.find('td').eq(7).text(valDescrip || '-');
                        }
                        
                        $row.find('.edit-bono').trigger('input');
					} else if (tipoNomina === 'vacaciones') {
                        $row.find('.edit-dias').val(valDias);
                        $row.find('.edit-dias').trigger('input');
                    } else if (tipoNomina === 'ajuste') {
						$row.find('.edit-bono').val(valMonto);
						$row.find('.col-nombre').eq(1).text(valConcepto || '-');
						$row.find('.edit-bono').trigger('input'); // Esto dispara el recálculo de la fila
					}
                    
                    mostrarToast('success', 'Registro guardado correctamente');
                    
                    // ✅ Enfocar el campo correspondiente después de guardar
                    enfocarCampoEdicion();
                    
                    var originalValues = {};
                    if (tipoNomina === 'automatica') {
                        originalValues = { 
                            horas: valHoras.toString(), 
                            feriados: valFeriados.toString(), 
                            otrosPagos: valOtros.toString(), 
                            descuentos: valDesc.toString() 
                        };
                    } else if (tipoNomina === 'extraordinaria') {
                        originalValues = { 
                            horas: valHoras.toString(), 
                            nocturnas: valNocturnas.toString(), 
                            descuentos: valDesc.toString() 
                        };
                    } else if (tipoNomina === 'bono') {
                        originalValues = { 
                            monto: valBono.toString(), 
                            descuentos: valDesc.toString(),
                            descripcion: valDescrip
                        };
                    } else if (tipoNomina === 'vacaciones') {
                        originalValues = { dias: valDias.toString() };
                    } else if (tipoNomina === 'ajuste') {
						$row.find('.edit-bono').val(valMonto); // actualiza input oculto si existe
						$row.find('.col-nombre').eq(1).text(valConcepto || '-'); // actualiza celda concepto
						$row.find('.total-devengado').text('$' + valMonto.toFixed(2));
						// Forzar recálculo visual de la fila
						recalcularFilaBono($row); 
					}
                    $('#modalEdicionRapida').data('originalValues', originalValues);
                    
                    $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                    if (callback) callback(true);
                } else {
                    mostrarToast('danger', r.error || 'Error al guardar');
                    $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                    if (callback) callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', status, error);
                mostrarToast('danger', 'Error de conexión con el servidor');
                $('#btnModalActualizar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
                if (callback) callback(false);
            }
        });
    }

    function navegarARegistro(targetId, targetIdx) {
        var table = $('#tablaNominas').DataTable();
        var pageLength = table.page.len();
        
        if (pageLength > 0) {
            var pageNum = Math.floor(targetIdx / pageLength);
            table.page(pageNum).draw('page');
        }
        
        var $row = $('#tablaNominas').find('tr[data-id="' + targetId + '"]');
        if ($row.length) {
            window.editCurrentRowIndex = targetIdx;
            window.editCurrentRowId = targetId;
            cargarModalEdicion($row);
            
            // ✅ El enfoque se maneja dentro de cargarModalEdicion -> enfocarCampoEdicion()
        } else {
            console.warn("No se pudo localizar el elemento DOM para el ID: " + targetId);
        }
    }

    // Eventos de Navegación del Modal
    $('#btnModalPrimero').on('click', function() {
        if (window.editVisibleRowsIds.length > 0) {
            var firstId = window.editVisibleRowsIds[0];
            navegarARegistro(firstId, 0);
        }
    });

    $('#btnModalAnterior').on('click', function() {
        var idx = window.editCurrentRowIndex;
        if (idx > 0) {
            var prevId = window.editVisibleRowsIds[idx - 1];
            navegarARegistro(prevId, idx - 1);
        } else {
            Swal.fire({
                title: 'Inicio',
                text: 'Se encuentra en el primer registro de la lista filtrada.',
                icon: 'info',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            // Mantener el foco en el campo actual
            enfocarCampoEdicion();
        }
    });

    $('#btnModalSiguiente').on('click', function() {
        var idx = window.editCurrentRowIndex;
        if (idx < window.editVisibleRowsIds.length - 1) {
            var nextId = window.editVisibleRowsIds[idx + 1];
            navegarARegistro(nextId, idx + 1);
        } else {
            Swal.fire({
                title: 'Final',
                text: 'Se encuentra en el último registro de la lista filtrada.',
                icon: 'info',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            // Mantener el foco en el campo actual
            enfocarCampoEdicion();
        }
    });

    $('#btnModalUltimo').on('click', function() {
        var lastIdx = window.editVisibleRowsIds.length - 1;
        if (lastIdx >= 0) {
            var lastId = window.editVisibleRowsIds[lastIdx];
            navegarARegistro(lastId, lastIdx);
        }
    });

// ==========================================
// EVENTO COMPLETO DEL BOTÓN ACTUALIZAR EN MODAL DE EDICIÓN
// ==========================================
$('#btnModalActualizar').on('click', function() {
    console.log('=== INICIO CLICK BOTON ACTUALIZAR ===');
    
    // Guardar referencia al botón
    var $btn = $(this);
    var id = $('#editId').val();
    console.log('ID del registro:', id);

    if (!id) {
        mostrarToast('danger', 'Error: ID de registro no encontrado');
        return;
    }

    // --- 1. DECLARAR VARIABLES ---
    var monto = 0;
    var descuentos = 0;
    var descripcion = '';
    var horas = 0;
    var feriados = 0;
    var otrosPagos = 0;
    var nocturnas = 0;
    var dias = 0;
    var nombre = '';

    // --- 2. OBTENER NOMBRE DEL TRABAJADOR ---
    var $filaOriginal = $('#tablaNominas tbody tr[data-id="' + id + '"]');
    if ($filaOriginal.length) {
        nombre = $filaOriginal.find('td:eq(2)').text().trim();
    }

    // --- 3. PREPARAR DATOS SEGÚN TIPO DE NÓMINA ---
    var datos = {
        actualizar_nomina: 1,
        id: id,
        tipo_nomina: tipoNomina
    };

    console.log('Tipo de nómina:', tipoNomina);

    // ==========================================
    // VALIDACIONES Y PREPARACIÓN POR TIPO DE NÓMINA
    // ==========================================
    
    if (tipoNomina === 'bono') {
        console.log('=== PROCESANDO BONO ===');
        monto = Math.max(0, parseFloat($('#editMontoBono').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentosBono').val()) || 0);
        descripcion = $('#editDescripcionBono').val() || '';
        
        console.log('Monto capturado:', monto);
        console.log('Descuentos capturados:', descuentos);
        console.log('Descripción capturada:', descripcion);

        // ==========================================
        // ✅ VALIDACIÓN: Monto del Bono en Cero
        // ==========================================
        if (monto === 0 || monto < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto del Bono en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-gift fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 monto del bono</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 monto en la nómina de bonos.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar monto',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editMontoBono').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }

        datos.monto_bono = monto;
        datos.descuentos = descuentos;
        datos.descripcion = descripcion;
        
        console.log('Datos a enviar (BONO):', datos);

    } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
        horas = Math.max(0, parseFloat($('#editHoras').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
        
        // ==========================================
        // ✅ VALIDACIÓN: Horas en Cero
        // ==========================================
        var nombreCampo = getNombreCampo(tipoNomina);
        if (horas === 0 || horas < 0.5) {
            Swal.fire({
                title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
                html: `
                    <div class="text-center">
                        <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 ${nombreCampo} en la nómina.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: `<i class="fas fa-pen me-2"></i>Asignar ${nombreCampo}`,
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editHoras').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }

        datos.horas_laboradas = horas;
        datos.descuentos = descuentos;

        if (tipoNomina === 'automatica') {
            feriados = Math.max(0, parseFloat($('#editFeriados').val()) || 0);
            otrosPagos = Math.max(0, parseFloat($('#editOtrosPagos').val()) || 0);
            datos.dias_feriados = feriados;
            datos.otros_salarios = otrosPagos;
            datos.horas_nocturnas = 0;
        } else {
            nocturnas = Math.max(0, parseFloat($('#editNocturnas').val()) || 0);
            datos.horas_nocturnas = nocturnas;
            datos.dias_feriados = 0;
            datos.otros_salarios = 0;
        }
        
        console.log('Datos a enviar (AUTO/EXTRA):', datos);

    } else if (tipoNomina === 'vacaciones') {
        dias = Math.max(0, parseFloat($('#editDias').val()) || 0);
        
        // ==========================================
        // ✅ VALIDACIÓN: Días en Cero
        // ==========================================
        if (dias === 0 || dias < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Días Tomados en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-umbrella-beach fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 días tomados</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 días en la nómina de vacaciones.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar días',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editDias').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }
        
        datos.dias_vacaciones = dias;
        console.log('Datos a enviar (VACACIONES):', datos);

    } else if (tipoNomina === 'ajuste') {
        monto = Math.max(0, parseFloat($('#editMontoAjuste').val()) || 0);
        descuentos = Math.max(0, parseFloat($('#editDescuentos').val()) || 0);
        descripcion = $('#editConceptoAjuste').val() || '';
        
        // ==========================================
        // ✅ VALIDACIÓN: Monto del Ajuste en Cero
        // ==========================================
        if (monto === 0 || monto < 0.5) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto del Ajuste en Cero',
                html: `
                    <div class="text-center">
                        <i class="fas fa-pen fa-3x mb-3" style="color: #f59e0b;"></i>
                        <p>El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 monto del ajuste</span>.</p>
                        <p class="text-muted small">No se puede guardar un trabajador con 0 monto en la nómina de ajustes.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-pen me-2"></i>Asignar monto',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
                cancelButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    setTimeout(function() {
                        $('#editMontoAjuste').focus().select();
                    }, 200);
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    eliminarTrabajadorPorId(id, nombre);
                }
            });
            return;
        }
        
        datos.monto_bono = monto;
        datos.descuentos = descuentos;
        datos.descripcion = descripcion;
        console.log('Datos a enviar (AJUSTE):', datos);
    }

    // ==========================================
    // 4. DESHABILITAR BOTÓN Y ENVIAR AJAX
    // ==========================================
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

    console.log('Enviando petición AJAX...');
    console.log('Objeto datos FINAL:', datos);

    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: datos,
        success: function(r) {
            console.log('=== RESPUESTA AJAX RECIBIDA ===');
            console.log('Respuesta del servidor:', r);
            console.log('¿Éxito?', r.success);
            
            if (r.success) {
                console.log('✅ Guardado exitoso en el servidor');
                
                // Actualizar la fila en la tabla
                var $row = $('#tablaNominas').find('tr[data-id="' + id + '"]');
                console.log('Fila encontrada:', $row.length > 0);

                if ($row.length) {
                    if (tipoNomina === 'bono') {
                        console.log('Actualizando fila BONO con monto:', monto);
                        $row.find('.edit-bono').val(monto);
                        $row.find('.edit-descuentos').val(descuentos);
                        var $colsNombre = $row.find('.col-nombre');
                        if ($colsNombre.length > 1) {
                            $colsNombre.eq(1).text(descripcion || '-');
                        }
                        if (typeof recalcularFilaBono === 'function') {
                            recalcularFilaBono($row);
                        }
                    } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                        $row.find('.edit-horas').val(horas);
                        $row.find('.edit-descuentos').val(descuentos);
                        if (tipoNomina === 'automatica') {
                            $row.find('.edit-feriados').val(feriados);
                            $row.find('.edit-otros-pagos').val(otrosPagos);
                            $row.find('.edit-nocturnas').val(0);
                        } else {
                            $row.find('.edit-nocturnas').val(nocturnas);
                            $row.find('.edit-feriados').val(0);
                            $row.find('.edit-otros-pagos').val(0);
                        }
                        $row.find('.edit-horas').trigger('input');
                    } else if (tipoNomina === 'vacaciones') {
                        $row.find('.edit-dias').val(dias);
                        $row.find('.edit-dias').trigger('input');
                    } else if (tipoNomina === 'ajuste') {
                        $row.find('.edit-bono').val(monto);
                        $row.find('.edit-descuentos').val(descuentos);
                        $row.find('.col-nombre').eq(1).text(descripcion || '-');
                        $row.find('.edit-bono').trigger('input');
                    }

                    if (typeof actualizarEstadisticas === 'function') {
                        actualizarEstadisticas();
                    }
                }

                // ✅ Guardar valores originales en el modal
                var originalValues = {
                    monto: monto.toString(),
                    descuentos: descuentos.toString(),
                    descripcion: descripcion,
                    horas: horas.toString(),
                    feriados: feriados.toString(),
                    otrosPagos: otrosPagos.toString(),
                    nocturnas: nocturnas.toString(),
                    dias: dias.toString()
                };
                console.log('Guardando valores originales en modal:', originalValues);
                $('#modalEdicionRapida').data('originalValues', originalValues);

                mostrarToast('success', '✅ Registro actualizado correctamente');
                enfocarCampoEdicion();

            } else {
                console.error('❌ Error en el servidor:', r.error || 'Error desconocido');
                mostrarToast('danger', r.error || 'Error al guardar');
            }
        },
        error: function(xhr, status, error) {
            console.error('=== ERROR AJAX ===');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Respuesta del servidor:', xhr.responseText);
            mostrarToast('danger', 'Error de conexión con el servidor: ' + error);
        },
        complete: function() {
            console.log('=== AJAX COMPLETADO ===');
            $btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Actualizar');
        }
    });
});

// EVENTO AGREGADO: Devuelve el foco al primer input cuando se cierra una alerta en el modal
$(document).on('closed.bs.alert', '#modalEdicionBody .alert', function () {
    setTimeout(function() {
        enfocarCampoEdicion();
    }, 50);
});

// EVENTO Controlador para el botón Restablecer
    $('#btnModalReset').on('click', function() {
        var original = $('#modalEdicionRapida').data('originalValues');
        if (!original) return;

        if (tipoNomina === 'automatica') {
            $('#editHoras').val(original.horas);
            $('#editFeriados').val(original.feriados);
            $('#editOtrosPagos').val(original.otrosPagos);
            $('#editDescuentos').val(original.descuentos);
            recalcularPreviewAuto();
        } else if (tipoNomina === 'extraordinaria') {
            $('#editHoras').val(original.horas);
            $('#editNocturnas').val(original.nocturnas);
            $('#editDescuentos').val(original.descuentos);
            recalcularPreviewAutoExtraordinaria();
        } else if (tipoNomina === 'bono') {
            $('#editMontoBono').val(original.monto);
            $('#editDescuentosBono').val(original.descuentos);
            $('#editDescripcionBono').val(original.descripcion);
            $('#editPorcientoBono').val(0);
            $('input[name="editTipoBonoRadio"][value="fijo"]').prop('checked', true).trigger('change');
            recalcularPreviewBono();
            // ✅ Enfocar el monto del bono después de restablecer
            enfocarCampoEdicion();
        } else if (tipoNomina === 'vacaciones') {
            $('#editDias').val(original.dias);
            recalcularPreviewVacaciones();
            enfocarCampoEdicion();
        }
        
        // Notificación visual de restablecimiento exitoso
        mostrarToast('info', 'Valores restablecidos a su estado inicial');
    });
	
	window.escapeHtml = function(str) {
		if (str === null || str === undefined) return '';
		var cadenaSegura = str.toString();
		return cadenaSegura.replace(/[&<>]/g, function(m) {
			if (m === '&') return '&amp;';
			if (m === '<') return '&lt;';
			if (m === '>') return '&gt;';
			return m;
		});
	}

    // =========================================================
    // LÓGICA DEL NUEVO MODAL DE SELECCIÓN DE DESCUENTO GENERAL
    // =========================================================
    var tipoDescuentoGenSeleccionado = null;
    var targetTypeGen = null;

    $('#modalSeleccionDescuentoGeneral').on('show.bs.modal', function(e) {
        var button = $(e.relatedTarget);
        targetTypeGen = button.data('target-type');
        $('#descuentoGeneralTarget').val(targetTypeGen);
        
        tipoDescuentoGenSeleccionado = null;
        $('#opcionTotalDescuentosGen').removeClass('selected');
        $('#opcionSoloCessGen').removeClass('selected');
        $('#infoCessGen').hide();
        $('#btnConfirmarDescuentoGen').prop('disabled', true);
    });

    $('#opcionTotalDescuentosGen').on('click', function() {
        $('#opcionTotalDescuentosGen').addClass('selected');
        $('#opcionSoloCessGen').removeClass('selected');
        $('#infoCessGen').hide();
        tipoDescuentoGenSeleccionado = 'total_rangos';
        $('#btnConfirmarDescuentoGen').prop('disabled', false);
    });

    $('#opcionSoloCessGen').on('click', function() {
        $('#opcionSoloCessGen').addClass('selected');
        $('#opcionTotalDescuentosGen').removeClass('selected');
        $('#infoCessGen').show();
        tipoDescuentoGenSeleccionado = 'solo_cess';
        $('#btnConfirmarDescuentoGen').prop('disabled', false);
    });

$('#btnConfirmarDescuentoGen').on('click', function() {
    if (!tipoDescuentoGenSeleccionado || !targetTypeGen) return;

    var genModalEl = document.getElementById('modalSeleccionDescuentoGeneral');
    var genModal = bootstrap.Modal.getInstance(genModalEl);
    genModal.hide();

    $(genModalEl).one('hidden.bs.modal', function() {
        // Guardamos la elección en una variable global temporal
        window.tempSelectedDiscount = tipoDescuentoGenSeleccionado;

        if (targetTypeGen === 'extraordinaria') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExtraordinaria'));
            modalObj.show();
        } else if (targetTypeGen === 'vacaciones') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVacaciones'));
            modalObj.show();
        } else if (targetTypeGen === 'bono') {
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBono'));
            modalObj.show();
        } else if (targetTypeGen === 'ajuste') {  // <--- NUEVO
            var modalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAjuste'));
            modalObj.show();
        }
    });
});

$('#modalAjuste').on('show.bs.modal', function() {
    // Reiniciamos listas de selección (el tipo de descuento ya lo asignó el primer handler)
    selectedAjuste = [];
    renderAjusteWorkerList();
    updateAjusteList();
    $('#searchAjusteWorker').val('');
    $('#conceptoAjuste').val('');
});



    // =========================================================
    // AJUSTAR COLUMNAS SEGÚN TIPO DESCUENTO EN TIEMPO REAL
    // =========================================================
function ajustarColumnasPorTipoDescuento(apiInstance) {
    var api = apiInstance || nominasTable;
    if (!api) return;
    
    // Detectamos si el descuento activo es "solo_cess"
    var esSoloCess = (activeTipoDescuento === 'solo_cess');
    
    // Seleccionamos las columnas directamente a través de su clase en DataTables
    var colImpuestos = api.columns('.col-impuestos-det');
    
    if (esSoloCess) {
        // Cambiamos el texto de la cabecera CESS a progresivo
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS<br>Prog.');
        
        // Ocultamos las columnas detalladas de impuestos
        colImpuestos.visible(false, false);
        
        // Ocultamos la cabecera principal que agrupa (Ingresos Personales)
        $('.col-impuestos').hide();
    } else {
        // Restauramos el texto de la cabecera CESS habitual
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS.<br>Hasta10%');
        
        // Mostramos las columnas detalladas de impuestos
        colImpuestos.visible(true, false);
        
        // Mostramos la cabecera principal agrupada
        $('.col-impuestos').show();
    }
    
    // Ajustamos la estructura de la tabla y recalculamos las columnas fijas
    api.columns.adjust();
    if (typeof api.fixedColumns === 'function') {
        api.fixedColumns().update();
    }
}



    $('#btnGuardarTodo').on('click', function() {
        guardarTodosLosCambios();
    });

    function guardarTodosLosCambios() {
        if (contabilizada) {
            Swal.fire({
                title: 'Nómina Contabilizada',
                text: 'No se pueden guardar cambios en una nómina contabilizada.',
                icon: 'warning',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            });
            return;
        }
        
        var todasLasFilas = [];
        if (nominasTable) {
            todasLasFilas = nominasTable.rows().nodes();
        } else {
            todasLasFilas = $('#tablaNominas tbody tr');
        }
        
        // 🔽 NUEVO: Solo se guardan las filas con estado Borrador
        todasLasFilas = $.grep(todasLasFilas, function(tr) {
            return $(tr).find('.badge-borrador').length > 0;
        });
        
        var totalFilas = todasLasFilas.length;
        
        if (totalFilas === 0) {
            Swal.fire({
                title: 'Sin datos',
                text: 'No hay registros para guardar.',
                icon: 'info',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
            });
            return;
        }
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i>Guardando cambios...',
            text: 'Procesando ' + totalFilas + ' trabajadores',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        var procesadas = 0;
        var errores = 0;
        var exitosas = 0;
        
        function procesarFila(index) {
            if (index >= totalFilas) {
                Swal.fire({
                    title: '<i class="fas fa-check-circle text-success me-2"></i>Guardado completado',
                    html: '<strong>' + exitosas + '</strong> registros guardados correctamente.<br>' +
                          (errores > 0 ? '<span class="text-danger">' + errores + ' errores encontrados.</span>' : ''),
                    icon: errores > 0 ? 'warning' : 'success',
                    background: '#1a1a2e',
                    color: '#ffffff',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
                }).then(() => {
                    location.reload();
                });
                return;
            }
            
            var fila = $(todasLasFilas[index]);
            var id = fila.data('id');
            
            if (!id) {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            // Omitir filas sin campos editables (p. ej. ajustes ya contabilizados)
            if (fila.find('.edit-bono, .edit-descuentos, .edit-horas, .edit-dias, .edit-salario, .edit-nocturnas').length === 0) {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
            
            if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
                var horasInput = fila.find('.edit-horas');
                var descuentosInput = fila.find('.edit-descuentos');
                
                datos.horas_laboradas = horasInput.length ? parseNumber(horasInput.val()) : 0;
                datos.descuentos = descuentosInput.length ? parseNumber(descuentosInput.val()) : 0;

                if (tipoNomina === 'automatica') {
                    var feriadosInput = fila.find('.edit-feriados');
                    var otrosPagosInput = fila.find('.edit-otros-pagos');
                    datos.dias_feriados = feriadosInput.length ? parseNumber(feriadosInput.val()) : 0;
                    datos.otros_salarios = otrosPagosInput.length ? parseNumber(otrosPagosInput.val()) : 0;
                    datos.horas_nocturnas = 0;
                } else {
                    var nocturnasInput = fila.find('.edit-nocturnas');
                    datos.horas_nocturnas = nocturnasInput.length ? parseNumber(nocturnasInput.val()) : 0;
                    datos.dias_feriados = 0;
                    datos.otros_salarios = 0;
                }
            } else if (tipoNomina === 'bono') {
                var bonoInput = fila.find('.edit-bono');
                var descInput = fila.find('.edit-descuentos');
                datos.monto_bono = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
                datos.descuentos = descInput.length ? parseNumber(descInput.val()) : 0;
            } else if (tipoNomina === 'ajuste') {
                var bonoInput = fila.find('.edit-bono');
                var descInput = fila.find('.edit-descuentos');
                datos.monto_bono = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
                datos.descuentos = descInput.length ? parseNumber(descInput.val()) : 0;
            } else if (tipoNomina === 'vacaciones') {
                var diasInput = fila.find('.edit-dias');
                datos.dias_vacaciones = diasInput.length ? parseNumber(diasInput.val()) : 0;
            } else {
                procesadas++;
                exitosas++;
                procesarFila(index + 1);
                return;
            }
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: datos,
                success: function(r) {
                    procesadas++;
                    if (r.success) {
                        exitosas++;
                    } else {
                        errores++;
                        console.error('Error guardando ID ' + id + ': ' + (r.error || 'Desconocido'));
                    }
                    procesarFila(index + 1);
                },
                error: function(xhr, status, error) {
                    procesadas++;
                    errores++;
                    console.error('Error AJAX guardando ID ' + id + ': ' + error);
                    procesarFila(index + 1);
                }
            });
        }
        
        procesarFila(0);
    }

    var nominasTable = null;
    var $tabla = $('#tablaNominas');

if ($tabla.length && $tabla.find('tbody tr').length > 0) {
    if ($.fn.DataTable.isDataTable('#tablaNominas')) {
        $tabla.DataTable().destroy();
    }
    
    try {
        nominasTable = $tabla.DataTable({
            language: {
                "decimal": "",
                "emptyTable": "No hay datos disponibles en la tabla",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
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
                buttons: {
                    colvisRestore: '<i class="fas fa-undo me-1"></i>Restaurar columnas'
                }
            },
            layout: {
                topStart: {
                    buttons: [
                        'colvis',
                        'colvisRestore',
                        {
                            extend: 'copy',
                            text: '<i class="fas fa-copy me-1"></i> Copiar',
                            className: 'btn-win btn-sm'
                        },
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv me-1"></i> CSV',
                            className: 'btn-win btn-sm',
                            action: function(e, dt, node, config) {
                                exportarCSVDesdeTabla(dt);
                            }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel me-1"></i> Excel',
                            className: 'btn-win btn-sm'
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                            className: 'btn-win btn-sm'
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print me-1"></i> Imprimir',
                            className: 'btn-win btn-sm'
                        }
                    ]
                }
            },
            pageLength: -1,
            autoWidth: false,
            scrollX: true,
            scrollY: "400px",
            scrollCollapse: true,
            fixedColumns: {
                leftColumns: 2
            },
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            responsive: true,
            order: [[2, 'asc']],
            lengthMenu: [[5, 10, 15, 20, 25, 50, 100, -1], [5, 10, 15, 20, 25, 50, 100, "Todos"]],
            dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-colvis"c><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
            buttons: [
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns me-1"></i> Columnas',
                    className: 'btn-win btn-sm',
                    postfixButtons: ['colvisRestore']
                },
                {
                    text: '<i class="fas fa-print text-primary me-2"></i> Nómina Impresa',
                    className: 'btn-win btn-sm',
                    action: function(e, dt, node, config) {
                        var estado = window.obtenerEstadoImpresion();
                        if (estado.motivo === 'vacio') {
                            Swal.fire({
                                title: '<i class="fas fa-inbox me-2" style="color: #60a5fa;"></i> Sin filas visibles',
                                html: '<div class="text-center"><p>No hay registros visibles para generar la Nómina Impresa.</p><p class="text-muted small">Revise la búsqueda y el filtro de número de nómina.</p></div>',
                                icon: 'info',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: '#3b82f6',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                            return;
                        }
                        if (estado.motivo === 'borrador') {
                            Swal.fire({
                                title: '📄 Nómina en estado Borrador',
                                html: '<div style="text-align: left;"><p><i class="fas fa-exclamation-triangle text-warning me-2"></i> <strong>No es posible generar la Nómina Impresa</strong> hasta que la nómina sea <strong>contabilizada</strong>.</p><p class="mt-3">Mientras está en <strong>Borrador</strong>, puedes:</p><ul class="text-start" style="display: inline-block; margin: 0 auto;"><li><i class="fas fa-print me-2"></i>Imprimir un <strong>Reporte Oficial</strong> de la nómina</li><li><i class="fas fa-file-excel me-2"></i>Exportar a <strong>Excel, PDF, CSV o Word</strong></li><li><i class="fas fa-edit me-2"></i>Editar y ajustar los valores libremente</li></ul><hr class="my-3"><p class="text-muted small">Una vez contabilizada, podrás generar la Nómina Impresa definitiva.</p></div>',
                                icon: 'warning',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                width: '550px'
                            });
                            return;
                        }
                        if (estado.motivo === 'multiples') {
                            Swal.fire({
                                title: '<i class="fas fa-layers me-2" style="color: #60a5fa;"></i> Varios números de nómina visibles',
                                html: '<div class="text-center"><p>Hay <strong>' + estado.numeros.length + ' nóminas diferentes</strong> visibles a la vez.</p><p class="mt-3">Use el filtro <strong>"No. Nómina"</strong> para seleccionar una sola nómina y luego genere la <strong>Nómina Impresa</strong>.</p></div>',
                                icon: 'info',
                                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                                confirmButtonColor: '#3b82f6',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            });
                            return;
                        }
                        if (estado.numero) numeroNomina = estado.numero;
                        const modalOpciones = new bootstrap.Modal(document.getElementById('modalOpcionesImpresion'));
                        modalOpciones.show();
                        cargarSelectores();
                        $('input[name="alcanceImpresion"]').off('change').on('change', cargarSelectores);
                    }
                },
				{
					text: '<i class="fas fa-file-pdf text-danger me-2"></i> PDF',
					className: 'btn-win btn-sm',
					action: function(e, dt, node, config) {
						// Obtener los trabajadores filtrados actualmente
						var trabajadoresFiltrados = [];
						var filas = dt.rows({ search: 'applied' }).nodes();
						
						$(filas).each(function() {
							const $row = $(this);
							const catCod = $row.data('categoria-codigo') || '';
							const catNom = $row.data('categoria-nombre') || '';
							const categoriaFull = (catCod && catNom) ? (catCod + ' - ' + catNom) : (catCod || catNom || '-');
							
							var trabajador = {
								codigo: $row.find('td:eq(0)').text().trim(),
								ci: $row.find('td:eq(1)').text().trim(),
								nombre: $row.find('td:eq(2)').text().trim(),
								area: $row.data('area') || $row.find('td:eq(3)').text().trim(),
								cargoId: $row.data('cargo-id') || 0,
								cargo: $row.find('td:eq(4)').text().trim(),
								centroCosto: $row.data('centro-costo') || $row.find('td:eq(5)').text().trim(),
								centroCostoCodigo: parseInt($row.data('centro-costo-codigo')) || 9999,
								categoria: categoriaFull,
								categoriaCodigo: catCod || '-',
								escala: $row.data('escala-romana') || '-',
								escalaNumero: $row.data('escala-numero') || 0,
								escalaDescripcion: $row.data('escala-descripcion') || '-',
								tipoContrato: $row.data('tipo-contrato') || '',
								tarifaSal: parseFloat($row.data('salario-hora')) || 0,
								horas: parseFloat($row.find('.col-horas').text().replace(/,/g, '')) || 0,
								aCobrar: parseFloat($row.find('.salario-laboral').text().replace('$', '').replace(/,/g, '')) || 0,
								bono: parseFloat($row.find('.col-otros-pagos').text().replace('$', '').replace(/,/g, '')) || 0,
								devengado: parseFloat($row.find('.total-devengado').text().replace('$', '').replace(/,/g, '')) || 0,
								impS: parseFloat($row.find('.contribucion').text().replace('$', '').replace(/,/g, '')) || 0,
								retenciones: parseFloat($row.find('.total-deducciones').text().replace('$', '').replace(/,/g, '')) || 0,
								descuentos: (function() {
									var $input = $row.find('.edit-descuentos');
									return $input.length ? (parseFloat($input.val()) || 0) : (parseFloat($row.find('.col-otros-descuentos').text().replace('$', '').replace(/,/g, '').trim()) || 0);
								})(),
								pagado: parseFloat($row.find('.neto').text().replace('$', '').replace(/,/g, '')) || 0,
								vacDias: parseFloat($row.find('.vacaciones-dias').text().replace(/,/g, '')) || 0,
								tiempoImp: parseFloat($row.find('.vacations-importe').text().replace('$', '').replace(/,/g, '')) || 0,
								firma: ''
							};
							trabajadoresFiltrados.push(trabajador);
						});
						
						if (trabajadoresFiltrados.length === 0) {
							Swal.fire({
								title: 'Sin datos',
								text: 'No hay registros visibles para exportar a PDF.',
								icon: 'warning',
								background: '#1a1a2e',
								color: '#ffffff',
								confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
							});
							return;
						}
						
						// Llamar a la función de exportación PDF con alcance 'general'
						exportarPdfOficial(trabajadoresFiltrados, 'general', '');
					}
				},
				 {
						text: '<i class="fas fa-file-word text-primary me-2"></i> Word',
						className: 'btn-win btn-sm',
						action: function(e, dt, node, config) {
							var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
							
							if (trabajadoresFiltrados.length === 0) {
								Swal.fire({
									title: 'Sin datos',
									text: 'No hay registros visibles para exportar a Word.',
									icon: 'warning',
									background: '#1a1a2e',
									color: '#ffffff'
								});
								return;
							}
							
							exportarWordOficial(trabajadoresFiltrados, 'general', '');
						}
					},
					{
						text: '<i class="fas fa-file-excel text-success me-2"></i> Excel',
						className: 'btn-win btn-sm',
						action: function(e, dt, node, config) {
							var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
							
							if (trabajadoresFiltrados.length === 0) {
								Swal.fire({
									title: 'Sin datos',
									text: 'No hay registros visibles para exportar a Excel.',
									icon: 'warning',
									background: '#1a1a2e',
									color: '#ffffff'
								});
								return;
							}
							
							exportarExcelOficial(trabajadoresFiltrados, 'general', '');
						}
					},
								{
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv text-info me-2"></i> CSV',
                    className: 'btn-win btn-sm',
                    title: function() { return nombreEmpresa + '_' + tipoNomina + '_' + '<?php echo $periodo; ?>'; },
                    action: function(e, dt, node, config) {
                        exportarCSVDesdeTabla(dt);
                    },
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column, node) {
                                var $el = $('<div>').html(data);
                                if ($el.find('input').length) return $el.find('input').val();
                                $el.find('button, i').remove();
                                return $el.text().trim();
                            }
                        }
                    }
                },
				{
					text: '<i class="fas fa-file-alt text-secondary me-2"></i> TXT',
					className: 'btn-win btn-sm',
					action: function(e, dt, node, config) {
						var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);
						
						if (trabajadoresFiltrados.length === 0) {
							Swal.fire({
								title: 'Sin datos',
								text: 'No hay registros visibles para exportar a TXT.',
								icon: 'warning',
								background: '#1a1a2e',
								color: '#ffffff'
							});
							return;
						}
						
						// Generar contenido TXT
						var contenido = generarContenidoTXT(trabajadoresFiltrados);
						
						// Crear y descargar archivo
						var blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
						var link = document.createElement('a');
						var now = new Date();
						var timestamp = now.getFullYear() + '' + 
									   String(now.getMonth() + 1).padStart(2, '0') + '' + 
									   String(now.getDate()).padStart(2, '0') + '_' +
									   String(now.getHours()).padStart(2, '0') +
									   String(now.getMinutes()).padStart(2, '0') +
									   String(now.getSeconds()).padStart(2, '0');
						
						link.href = URL.createObjectURL(blob);
						link.download = nombreEmpresa.replace(/[^a-zA-Z0-9]/g, '_') + '_' + 
										tipoNomina + '_' + 
										'<?php echo $periodo; ?>' + '_' + 
										timestamp + '.txt';
						link.click();
						URL.revokeObjectURL(link.href);
						
						Swal.fire({
							title: '¡Exportado!',
							text: 'Archivo TXT generado correctamente.',
							icon: 'success',
							timer: 1500,
							showConfirmButton: false,
							background: '#1a1a2e',
							color: '#ffffff'
						});
					}
				},
{
    extend: 'print',
    text: '<i class="fas fa-print text-warning me-2"></i> Imprimir Rep. Pago',
    className: 'btn-win btn-sm',
    title: function() {
        var cleanTipo = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F _-]/g, '_');
        var cleanPeriodo = periodo.replace(/[^a-zA-Z0-9\u00C0-\u017F _-]/g, '_');
        return 'SC-4-06_' + cleanTipo + '_' + cleanPeriodo;
    },
    exportOptions: {
        columns: ':visible:not(.col-estado, .col-acciones, .col-numero-nomina)',
        footer: true, // <-- CONFIGURACIÓN CLAVE: Fuerza la exportación del tfoot (totales)
        format: {
            body: function (data, row, column, node) {
                var $el = $('<div>').html(data);
                if ($el.find('input').length) return $el.find('input').val();
                $el.find('.badge-borrador, .badge-contabilizado, button, i').remove();
                return $el.text().trim();
            },
            footer: function (data, row, column, node) { // Limpia y formatea la fila de totales
                var $el = $('<div>').html(data);
                $el.find('button, i').remove();
                return $el.text().trim();
            }
        }
    },
customize: function(win) {
    var totalTrabajadores = nominasTable.rows().count();
    var now = new Date();

    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    var hours24 = now.getHours();
    var ampm = hours24 >= 12 ? 'pm' : 'am';
    var hours12 = hours24 % 12 || 12;
    var hoursStr = String(hours12).padStart(2, '0');
    var minutesStr = String(now.getMinutes()).padStart(2, '0');
    var secondsStr = String(now.getSeconds()).padStart(2, '0');
    var fechaHora12h = `${day}/${month}/${year} - ${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;

    var $table = $(win.document.body).find('table');
    var totalColumns = $table.find('tbody tr:first td').length;

    // 🔽 NUEVO: Quitar el título <h1> que agrega el plugin (la cabecera ya trae el título del reporte)
    $(win.document.body).children('h1').remove();

    // 🔽 NUEVO: Determinar si es BONO
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    // Para BONO y AJUSTE, el "Concepto" se imprime debajo de cada trabajador, no como columna
    var quitarConceptoCol = esBono || esAjuste;
    if (quitarConceptoCol) totalColumns = totalColumns - 1;
    var montoDistribuir = montoDistribuidoGlobal || 0;
    var mostrarMonto = (esBono && montoDistribuir > 0);
    var montoTexto = mostrarMonto ? '$' + montoDistribuir.toFixed(2) : '(Hasta que se contabilice)';

    var styles = `
        @page {
            size: letter landscape;
            margin: 15mm 12mm 22mm 12mm;
        }
        html {
            counter-reset: page;
        }
        body {
            font-family: 'Arial', sans-serif !important;
            font-size: 11px !important; 
            color: #000000 !important;
            background-color: #ffffff !important;
            padding-bottom: 35px !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            page-break-inside: auto !important;
        }
        tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        thead {
            display: table-header-group !important;
        }
        tfoot {
            display: table-footer-group !important;
        }
        th, td {
            border: 0.5pt solid #000000 !important;
            padding: 4px 2.5px !important;
            font-family: 'Arial', sans-serif !important;
        }
        th {
            background-color: #004B87 !important;
            color: #ffffff !important;
            text-align: left !important; 
            font-weight: bold !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table tbody td:nth-child(n+${quitarConceptoCol ? 8 : 9}), table thead th:nth-child(n+${quitarConceptoCol ? 8 : 9}) {
            text-align: right !important;
        }
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        
        .col-feriados-header, .col-vacaciones-header, .col-impuestos {
            border-bottom: 0.5pt solid #ffffff !important;
            background-color: #003366 !important;
        }
        
        .print-footer-container {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-top: 1px solid #000000 !important; 
            padding-top: 5px !important;
            height: 25px !important;
            font-size: 8pt !important;
            font-family: 'Arial', sans-serif !important;
            color: #000000 !important; 
            background-color: #ffffff !important; 
            z-index: 999999 !important; 
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 🔽 PAGINACIÓN MANUAL: cada hoja con su propia numeración */
        .print-sheet {
            page-break-after: always !important;
            break-after: page !important;
        }
        .print-sheet-last {
            page-break-after: auto !important;
        }
        .print-sheet-page {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .print-sheet-page:not(.print-sheet-last) {
            page-break-after: always !important;
            break-after: page !important;
        }
        .print-sheet-table {
            width: 100% !important;
            border-collapse: collapse !important;
            page-break-inside: auto !important;
        }
        .print-sheet-footer {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-top: 1px solid #000000 !important;
            padding-top: 5px !important;
            height: 25px !important;
            font-size: 8pt !important;
            font-family: 'Arial', sans-serif !important;
            color: #000000 !important;
            margin-top: 8px !important;
        }
        .print-sheet-footer div {
            font-weight: bold !important;
        }
        .print-sheet-footer-center {
            text-align: center !important;
        }
        .print-sheet-footer-right {
            text-align: right !important;
        }
        
        /* 🔽 Estilo para el Monto a Distribuir */
        .monto-distribuir {
            font-weight: bold !important;
            color: ${mostrarMonto ? '#004B87' : '#ef4444'} !important;
            font-size: 12pt !important;
        }
    `;
    $(win.document.head).append('<style>' + styles + '</style>');

    // 🔽 NUEVO: Monto a Distribuir en el header (si es BONO)
    var montoHeaderHtml = '';
    if (esBono) {
        montoHeaderHtml = `
            <tr>
                <td colspan="${totalColumns}" style="background-color: #ffffff !important; border: none !important; padding: 0 0 15px 0 !important; color: #000000 !important; text-align: left !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #004B87; padding-bottom:10px; font-family: Arial;">
                        <div style="display:flex; align-items:center;">
                            ${logoBase64 ? '<img src="' + logoBase64 + '" style="width:75px; margin-right:15px;">' : ''}
                            <div>
                                <h2 style="color:#004B87 !important; margin:0; font-size:12.5pt; font-weight:bold; font-family: Arial;">${nombreEmpresa}</h2>
                                <h4 style="color:#333; margin:3px 0 0 0; font-size:9.5pt; font-weight:bold; font-family: Arial;">
                                    REPORTE DE PROCESAMIENTO DE PAGO - <span style="color: red !important;">${tipoNominaTexto.toUpperCase()}</span>
                                </h4>
                                <p style="color:#004B87 !important; margin:2px 0 0 0; font-size:8.5pt; font-weight:bold; font-family: Arial;">PERÍODO: ${periodoTexto.toUpperCase()}</p>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:8pt; color:#444; line-height:1.4; font-family: Arial;">
                            <strong>No. Instrum. Pago:</strong> ${numeroNomina}<br>
                            <strong>Emisión:</strong> ${fechaHora12h}<br>
                            <strong>Código REEUP:</strong> ${reeup}<br>
                            <strong>NIT:</strong> ${nitEmpresa}<br>
                            <strong>Total Trabajadores:</strong> <span style="color:#004B87; font-weight:bold;">${totalTrabajadores}</span><br>
                            ${esBono ? `<strong>Monto a Distribuir:</strong> <span class="monto-distribuir">${montoTexto}</span>` : ''}
                        </div>
                    </div>
                    ${observacionesCierreGlobal ? `
                    <div style="margin-top: 8px; padding: 6px 10px; background-color: #f8fafc; border: 0.5pt solid #004B87; font-size: 8.5pt; font-family: Arial; font-weight: normal; color: #333333;">
                        <strong>Observaciones de Cierre:</strong> ${observacionesCierreGlobal}
                    </div>` : ''}
                </th>
            </tr>
        `;
    } else {
        // Header original para otros tipos de nómina
        montoHeaderHtml = `
            <tr>
                <th colspan="${totalColumns}" style="background-color: #ffffff !important; border: none !important; padding: 0 0 15px 0 !important; color: #000000 !important; text-align: left !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #004B87; padding-bottom:10px; font-family: Arial;">
                        <div style="display:flex; align-items:center;">
                            ${logoBase64 ? '<img src="' + logoBase64 + '" style="width:75px; margin-right:15px;">' : ''}
                            <div>
                                <h2 style="color:#004B87 !important; margin:0; font-size:12.5pt; font-weight:bold; font-family: Arial;">${nombreEmpresa}</h2>
                                <h4 style="color:#333; margin:3px 0 0 0; font-size:9.5pt; font-weight:bold; font-family: Arial;">
                                    REPORTE DE PROCESAMIENTO DE PAGO - <span style="color: red !important;">${tipoNominaTexto.toUpperCase()}</span>
                                </h4>
                                <p style="color:#004B87 !important; margin:2px 0 0 0; font-size:8.5pt; font-weight:bold; font-family: Arial;">PERÍODO: ${periodoTexto.toUpperCase()}</p>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:8pt; color:#444; line-height:1.4; font-family: Arial;">
                            <strong>No. Instrum. Pago:</strong> ${numeroNomina}<br>
                            <strong>Emisión:</strong> ${fechaHora12h}<br>
                            <strong>Código REEUP:</strong> ${reeup}<br>
                            <strong>NIT:</strong> ${nitEmpresa}<br>
                            <strong>Total Trabajadores:</strong> <span style="color:#004B87; font-weight:bold;">${totalTrabajadores}</span>
                        </div>
                    </div>
                    ${observacionesCierreGlobal ? `
                    <div style="margin-top: 8px; padding: 6px 10px; background-color: #f8fafc; border: 0.5pt solid #004B87; font-size: 8.5pt; font-family: Arial; font-weight: normal; color: #333333;">
                        <strong>Observaciones de Cierre:</strong> ${observacionesCierreGlobal}
                    </div>` : ''}
                </th>
            </tr>
        `;
    }

    // 🔽 MODIFICADO: Construcción de las cabeceras de la tabla
    var tr1 = '';
    var tr2 = '';
    var visibleTaxesCount = 0;
    var subImpuestosHtml = '';
    
    $('#tablaNominas thead tr:last th.col-impuestos-det').each(function() {
        if ($(this).is(':visible')) {
            visibleTaxesCount++;
            subImpuestosHtml += `<th class="text-right" style="background-color:#004B87 !important; color:#ffffff !important; border:0.5pt solid #000000 !important; font-size:7px !important;">${$(this).html()}</th>`;
        }
    });

    if (tipoNomina === 'automatica') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:140px;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2">Escala</th>
                <th rowspan="2" class="text-right">S. Básico</th>
                <th rowspan="2" class="text-right">Horas</th>
                <th rowspan="2" class="text-right">S. Dev.</th>
                <th rowspan="2" class="text-right">$/Hora</th>
                <th colspan="2" class="col-feriados-header text-right">Días Feriados</th>
                <th colspan="2" class="col-vacaciones-header text-right">Acum. Vacaciones</th>
                <th rowspan="2" class="text-right">Otros Pagos</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = `
            <tr>
                <th class="text-right">Días</th>
                <th class="text-right">Importe</th>
                <th class="text-right">Días</th>
                <th class="text-right">Importe</th>
                ${subImpuestosHtml}
            </tr>
        `;
    } else if (tipoNomina === 'extraordinaria') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:140px;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2">Escala</th>
                <th rowspan="2" class="text-right">S. Básico</th>
                <th rowspan="2" class="text-right">Horas</th>
                <th rowspan="2" class="text-right">Horas Nocturnas</th>
                <th rowspan="2" class="text-right">$/Hora Nocturna</th>
                <th rowspan="2" class="text-right">Imp. Nocturno</th>
                <th rowspan="2" class="text-right">S. Dev.</th>
                <th rowspan="2" class="text-right">$/Hora</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'bono') {
        // 🔽 MODIFICADO: Para BONO el "Concepto" se imprime debajo de cada trabajador (sin columna)
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:140px;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">Monto Bono</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'vacaciones') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:140px;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">Días Tomados</th>
                <th rowspan="2" class="text-right">Días Restantes</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otros Desc.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    } else if (tipoNomina === 'ajuste') {
        tr1 = `
            <tr>
                <th rowspan="2">Código</th>
                <th rowspan="2">CI</th>
                <th rowspan="2" style="width:140px;">Nombre y Apellidos</th>
                <th rowspan="2">Área</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">CC</th>
                <th rowspan="2">Cat.</th>
                <th rowspan="2" class="text-right">Monto Ajuste</th>
                <th rowspan="2" class="text-right">Total Dev.</th>
                <th rowspan="2" class="text-right">Otras Ret.</th>
                <th rowspan="2" class="text-right">CESS</th>
                <th rowspan="2" class="text-right">Total Ret.</th>
                ${visibleTaxesCount > 0 ? `<th colspan="${visibleTaxesCount}" class="col-impuestos text-right">Impuestos Ingresos Personales</th>` : ''}
                <th rowspan="2" class="text-right">NETO</th>
            </tr>
        `;
        tr2 = visibleTaxesCount > 0 ? `<tr>${subImpuestosHtml}</tr>` : '';
    }

    // 🔽 APLICAR LOS HEADERS MODIFICADOS
    $table.find('thead').html(montoHeaderHtml + tr1 + tr2);

    // 🔽 NUEVO: Fondo azul inline en los títulos de columnas (evita que quede transparente en impresión)
    $table.find('thead tr:not(:first) th').each(function() {
        var est = this.getAttribute('style') || '';
        this.setAttribute('style', est + '; background-color: #004B87 !important; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;');
    });

    $table.find('input, button, .btn-icon').remove();

    // 🔽 MODIFICADO: Para BONO, asegurar que el concepto se muestre correctamente
    if (tipoNomina === 'bono') {
        $table.find('tbody tr').each(function() {
            // Buscar la celda de concepto (la que tiene la clase .col-nombre o la columna 7)
            var $cells = $(this).find('td');
            if ($cells.length > 7) {
                // La columna 7 es la de concepto (índice 7)
                var concepto = $cells.eq(7).text().trim();
                if (concepto === '' || concepto === '-') {
                    $cells.eq(7).text('Sin concepto');
                }
            }
        });
    }

    $table.find('tbody tr').each(function() {
        $(this).find('.neto, td:last-child').css({
            'font-weight': 'bold',
            'background-color': '#f0f4f8',
            '-webkit-print-color-adjust': 'exact',
            'print-color-adjust': 'exact'
        });
    });
    
    $table.find('tfoot td').css({
        'background-color': '#f0f4f8',
        'font-weight': 'bold',
        'color': '#004B87',
        'border': '1px solid #000000',
        '-webkit-print-color-adjust': 'exact',
        'print-color-adjust': 'exact'
    });

    // 🔽 NUEVO: Para BONO y AJUSTE, mover el "Concepto" debajo de cada trabajador (sin columna)
    if (quitarConceptoCol) {
        var conceptoIdx = 7;
        $table.find('tbody tr').each(function() {
            var $cells = $(this).find('td');
            if ($cells.length > conceptoIdx) {
                var concepto = $cells.eq(conceptoIdx).text().trim();
                if (concepto === '' || concepto === '-') {
                    concepto = 'Sin concepto';
                }
                var nCols = $cells.length - 1;
                $cells.eq(conceptoIdx).remove();
                $(this).after('<tr><td colspan="' + nCols + '" style="text-align:left !important; border:1px solid #000000 !important;">Observación: ' + concepto + '</td></tr>');
            }
        });
        // Quitar la celda del Concepto en la fila de totales (tfoot)
        $table.find('tfoot tr').each(function() {
            var $cells = $(this).find('td');
            if ($cells.length > conceptoIdx) $cells.eq(conceptoIdx).remove();
        });
    }

    // 🔽 NUEVO: PAGINACIÓN MANUAL DEL REPORTE (sustituye el contador CSS que no se calculaba)
    // Se divide la tabla en hojas de ROWS_POR_PAGINA filas, cada una con su pie "Página X de Y".
    var ROWS_POR_PAGINA = 12;
    var $filasImpresion = $table.find('tbody tr');
    var totalPaginasReales = Math.max(1, Math.ceil($filasImpresion.length / ROWS_POR_PAGINA));
    var theadHtml = $table.find('thead').html();
    var tfootHtml = $table.find('tfoot').html();

    var htmlPaginado = '';
    for (var pIdx = 0; pIdx < totalPaginasReales; pIdx++) {
        var filasPagina = $filasImpresion.slice(pIdx * ROWS_POR_PAGINA, (pIdx + 1) * ROWS_POR_PAGINA);
        var filasHtml = '';
        filasPagina.each(function() { filasHtml += this.outerHTML; });
        var esUltimaHoja = (pIdx === totalPaginasReales - 1);
        htmlPaginado += `
            <div class="print-sheet-page ${esUltimaHoja ? 'print-sheet-last' : ''}">
                <table class="print-sheet-table">
                    <thead>${theadHtml}</thead>
                    <tbody>${filasHtml}</tbody>
                    ${esUltimaHoja ? '<tfoot>' + tfootHtml + '</tfoot>' : ''}
                </table>
                <div class="print-sheet-footer">
                    <div>Reporte de Pago - ${nombreEmpresa}</div>
                    <div class="print-sheet-footer-center">Página ${pIdx + 1} de ${totalPaginasReales}</div>
                    <div class="print-sheet-footer-right">Impresión: ${fechaHora12h}</div>
                </div>
            </div>
        `;
    }
    $table.replaceWith(htmlPaginado);

    // INYECCIÓN DEL BLOQUE OFICIAL DE FIRMAS DE RESPONSABILIDAD
    $(win.document.body).append(`
        <div style="margin-top: 50px; page-break-inside: avoid; break-inside: avoid; font-family: Arial, sans-serif;">
            <table style="width: 100%; border: none !important; margin-top: 30px; border-collapse: collapse;">
                <tr style="border: none !important;">
                    <td style="width: 25%; text-align: center; border: none !important; padding: 10px; font-size: 8.5pt; font-family: Arial, sans-serif; line-height: 1.4;">
                        <p style="margin-bottom: 50px;"><b>Elaborado por:</b></p>
                        <p style="border-top: 1px solid #000; width: 85%; margin: 0 auto; padding-top: 3px;">Firma del Elaborador</p>
                        <p style="font-size: 7.5pt; color: #555; margin-top: 2px;">Especialista de Nóminas</p>
                    </td>
                    <td style="width: 25%; text-align: center; border: none !important; padding: 10px; font-size: 8.5pt; font-family: Arial, sans-serif; line-height: 1.4;">
                        <p style="margin-bottom: 50px;"><b>Revisado por:</b></p>
                        <p style="border-top: 1px solid #000; width: 85%; margin: 0 auto; padding-top: 3px;"><b>${especialistaGestion}</b></p>
                        <p style="font-size: 7.5pt; color: #555; margin-top: 2px;">Especialista en Gestión Económica</p>
                    </td>
                    <td style="width: 25%; text-align: center; border: none !important; padding: 10px; font-size: 8.5pt; font-family: Arial, sans-serif; line-height: 1.4;">
                        <p style="margin-bottom: 50px;"><b>Aprobado por:</b></p>
                        <p style="border-top: 1px solid #000; width: 85%; margin: 0 auto; padding-top: 3px;"><b>${jefeProyecto}</b></p>
                        <p style="font-size: 7.5pt; color: #555; margin-top: 2px;">Director de Proyecto</p>
                    </td>
                    <td style="width: 25%; text-align: center; border: none !important; padding: 10px; font-size: 8.5pt; font-family: Arial, sans-serif; line-height: 1.4;">
                        <p style="margin-bottom: 50px;"><b>Contabilizado por:</b></p>
                        <p style="border-top: 1px solid #000; width: 85%; margin: 0 auto; padding-top: 3px;">Firma del Contador</p>
                        <p style="font-size: 7.5pt; color: #555; margin-top: 2px;">Área Contable y Financiera</p>
                    </td>
                </tr>
            </table>
        </div>
    `);
}
}
			],
            drawCallback: function() { 
                actualizarEstadisticas(); 
                ajustarColumnasPorTipoDescuento(this.api()); 
            },
            initComplete: function() {
                ajustarColumnasPorTipoDescuento(this.api()); 
                actualizarEstadisticas();
                actualizarEstadoVista();
                $('#customSearchInput').on('keyup', function() {
                    setTimeout(function() {
                        actualizarEstadisticas();
                    }, 100);
                });
            }
        });

        // 🔽 NUEVO: Aplicar filtros y estado inicial de la vista tras crear el DataTable
        aplicarFiltros();

// Función auxiliar para obtener trabajadores filtrados del DataTable de forma robusta
function obtenerTrabajadoresFiltrados(dt) {
    var trabajadores = [];
    var filas = dt.rows({ search: 'applied' }).nodes();
    
    $(filas).each(function() {
        const $row = $(this);
        var trabajador = mapRowToTrabajador($row);
        trabajadores.push(trabajador);
    });
    
    return trabajadores;
}

// 🔽 NUEVO: Generador de contenido CSV (mismo formato que TXT, con la Observación debajo de cada trabajador)
function generarContenidoCSV(trabajadores) {
    var lines = [];
    var esc = function(v) {
        v = (v === null || v === undefined) ? '' : String(v);
        return '"' + v.replace(/"/g, '""') + '"';
    };

    // ============================================================
    // ENCABEZADO PRINCIPAL
    // ============================================================
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    var mostrarConcepto = esBono || esAjuste;

    lines.push(esc(nombreEmpresa));
    lines.push(esc('Tipo de Nómina: ' + tipoNominaTexto) + ',' + esc('Período: ' + periodoTexto));
    lines.push(esc('Número Nómina: ' + (numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina)));
    if (esBono) {
        lines.push(esc('Monto a Distribuir: ' + (montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)')));
    }
    lines.push('');

    // ============================================================
    // ENCABEZADOS DE COLUMNAS (sin columna CONCEPTO: se muestra como línea debajo)
    // ============================================================
    var header;
    if (esBono) {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('MONTO BONO'), esc('NETO')];
    } else {
        header = [esc('COD'), esc('CI'), esc('NOMBRE Y APELLIDOS'), esc('DEVENGADO'), esc('DEDUCC.'), esc('NETO')];
    }
    lines.push(header.join(','));

    // ============================================================
    // DATOS DE TRABAJADORES
    // ============================================================
    trabajadores.forEach(function(t) {
        var row;
        if (esBono) {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc('$' + (t.bono || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        } else {
            row = [
                esc(t.codigo), esc(t.ci), esc(t.nombre),
                esc('$' + (t.devengado || 0).toFixed(2)),
                esc('$' + (t.retenciones || 0).toFixed(2)),
                esc('$' + (t.pagado || 0).toFixed(2))
            ];
        }
        lines.push(row.join(','));
        // 🔽 NUEVO: Observación/concepto debajo de cada trabajador (BONO y AJUSTE)
        if (mostrarConcepto) {
            lines.push(esc('Observación: ' + (t.concepto || 'Sin concepto')));
        }
    });

    return '\uFEFF' + lines.join('\r\n');
}

function exportarCSVDesdeTabla(dt) {
    var trabajadoresFiltrados = obtenerTrabajadoresFiltrados(dt);

    if (trabajadoresFiltrados.length === 0) {
        Swal.fire({
            title: 'Sin datos',
            text: 'No hay registros visibles para exportar a CSV.',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }

    var contenido = generarContenidoCSV(trabajadoresFiltrados);

    var blob = new Blob([contenido], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var now = new Date();
    var timestamp = now.getFullYear() + '' +
                    String(now.getMonth() + 1).padStart(2, '0') + '' +
                    String(now.getDate()).padStart(2, '0') + '_' +
                    String(now.getHours()).padStart(2, '0') +
                    String(now.getMinutes()).padStart(2, '0') +
                    String(now.getSeconds()).padStart(2, '0');

    link.href = URL.createObjectURL(blob);
    link.download = nombreEmpresa.replace(/[^a-zA-Z0-9]/g, '_') + '_' +
                    tipoNomina + '_' +
                    '<?php echo $periodo; ?>' + '_' +
                    timestamp + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);

    Swal.fire({
        title: '¡Exportado!',
        text: 'Archivo CSV generado correctamente.',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#ffffff'
    });
}

        // ==========================================
        // CONFIGURAR BÚSQUEDA PERSONALIZADA
        // ==========================================
        $('.dt-search').html(`
            <div class="input-group input-group-sm" style="width: 380px;">
                <span class="input-group-text" style="background: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.15); border-right: none; color: #60a5fa;">
                    <i class="fas fa-search" style="font-size: 14px;"></i>
                </span>
                <input type="text" class="form-control form-control-sm" 
                       id="customSearchInput"
                       placeholder="Buscar en nómina..." 
                       style="background: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.15); color: white;">
                <button class="btn btn-sm" type="button" id="clearSearchBtn" 
                        style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5;"
                        title="Limpiar búsqueda">
                    <i class="fas fa-times" style="font-size: 12px;"></i>
                </button>
            </div>
        `);

        $('#customSearchInput').on('keyup', function() {
            nominasTable.search(this.value).draw();
        });

        $('#clearSearchBtn').on('click', function() {
            $('#customSearchInput').val('');
            nominasTable.search('').draw();
        });
        
        // ==========================================
        // CONTROL DE VISIBILIDAD DEL MENÚ OPCIONES
        // ==========================================
        function actualizarVisibilidadMenuOpciones() {
            var hayDatos = false;
            
            if (nominasTable) {
                var filasVisibles = nominasTable.rows({ search: 'applied' }).count();
                hayDatos = (filasVisibles > 0);
            }
            
            if (hayDatos) {
                $('.menu-personalizar-vista').show();
                $('.menu-exportar-reportes').show();
                $('.menu-separador').show();
            } else {
                $('.menu-personalizar-vista').hide();
                $('.menu-exportar-reportes').hide();
                $('.menu-separador').hide();
            }
        }
        
        // Inicializar visibilidad del menú
        setTimeout(function() {
            actualizarVisibilidadMenuOpciones();
        }, 100);
        
        // Actualizar después de cada filtro o búsqueda
        nominasTable.on('draw', function() {
            actualizarVisibilidadMenuOpciones();
        });
        
        // Actualizar después de limpiar filtros
        $(document).on('click', '#btnLimpiarFiltros', function() {
            setTimeout(function() {
                actualizarVisibilidadMenuOpciones();
            }, 200);
        });
        
        console.log('DataTable inicializado correctamente');
        
    } catch(e) {
        console.error('Error al inicializar DataTable:', e);
    }
} else {
    console.log('No hay datos en la tabla o tabla no encontrada');
}

$('#filtroAcumulaVacaciones').on('change', function() {
    var valor = $(this).val();
    var $rangoSelect = $('#filtroRangoVacaciones');
    
    if (valor !== '') {
        $('#filtroTrabajador').val('');
    }
    
    if (valor === 'no') {
        $rangoSelect.prop('disabled', true);
        $rangoSelect.val('');
        $rangoSelect.attr('title', 'No aplicable: Los trabajadores que no acumulan vacaciones tienen 0 días acumulados');
    } else {
        $rangoSelect.prop('disabled', false);
        $rangoSelect.attr('title', 'Filtrar por rango de días acumulados');
    }
    
    aplicarFiltros();
});

$('#filtroRangoVacaciones').on('change', function() {
    var valor = $(this).val();
    var $acumulaSelect = $('#filtroAcumulaVacaciones');
    
    if (valor !== '') {
        $('#filtroTrabajador').val('');
    }
    
    if (valor && valor !== '') {
        $acumulaSelect.find('option[value="no"]').prop('disabled', true);
        
        if ($acumulaSelect.val() === 'no') {
            $acumulaSelect.val('si');
        }
        
        $acumulaSelect.find('option[value="no"]').css('color', '#6c757d');
    } else {
        $acumulaSelect.find('option[value="no"]').prop('disabled', false);
        $acumulaSelect.find('option[value="no"]').css('color', '');
    }
    
    aplicarFiltros();
});

function inicializarEstadoFiltros() {
    var acumulaVal = $('#filtroAcumulaVacaciones').val();
    var rangoVal = $('#filtroRangoVacaciones').val();
    
    if ($('#filtroTrabajador').val() !== '') {
        $('#filtroRangoVacaciones').prop('disabled', true);
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', true);
        return;
    }
    
    if (acumulaVal === 'no') {
        $('#filtroRangoVacaciones').prop('disabled', true);
        $('#filtroRangoVacaciones').attr('title', 'No aplicable: Los trabajadores que no acumulan vacaciones tienen 0 días acumulados');
    } else {
        $('#filtroRangoVacaciones').prop('disabled', false);
    }
    
    if (rangoVal && rangoVal !== '') {
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', true);
        if ($('#filtroAcumulaVacaciones').val() === 'no') {
            $('#filtroAcumulaVacaciones').val('si');
        }
    } else {
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
    }
}

function actualizarEstadisticas() {
    var tDev = 0, tNeto = 0, tDesc = 0, tDias = 0;
    var tOtrosDescuentos = 0; // Variable exclusiva para acumular "Otros Descuentos"
    var tHorasNormales = 0, tHorasNocturnas = 0, tImporteNocturno = 0;
    var tBono = 0;
    var tSalarioBasico = 0, tSalarioLaboral = 0, tFeriadosDias = 0, tFeriadosImporte = 0;
    var tVacacionesDias = 0, tVacacionesImporte = 0, tOtrosPagos = 0;
    var tContribucion = 0;
    
    var $tabla = $('#tablaNominas');
    
    if (!$tabla.length || $tabla.find('tbody tr').length === 0) {
        $('#totalDevengado').text('$0.00');
        $('#totalNeto').text('$0.00');
        $('#totalDescuentos').text('$0.00');
        $('.total-horas-footer').text('0');
        return;
    }
    
    // Siempre se totalizan TODOS los registros (equivale a "Mostrar Todo"),
    // sin importar la paginación, búsqueda o filtros aplicados en la tabla.
    var filasVisibles = $tabla.find('tbody tr');
    var totalTrabajadores = filasVisibles.length;
    
    var impuestosAcumulados = [];
    var cantidadRangos = $('#tablaNominas thead tr:last th.col-impuestos-det').length;
    for (var i = 0; i < cantidadRangos; i++) {
        impuestosAcumulados.push(0);
    }
    
    filasVisibles.each(function() {
        var $row = $(this);
        
        var devengado = parseNumber($row.find('.total-devengado').text());
        var neto = parseNumber($row.find('.neto').text());
        tDev += devengado;
        tNeto += neto;
        
        // Sumar las deducciones totales de cada fila (CESS + IP + Otros Descuentos)
        var deducciones = parseNumber($row.find('.total-deducciones').text());
        tDesc += deducciones;

        // Sumar exclusivamente los "Otros Descuentos" de la fila
        var otrosDesc = $row.find('.edit-descuentos').length ? 
                        parseNumber($row.find('.edit-descuentos').val()) : 
                        parseNumber($row.find('.col-otros-descuentos').text());
        tOtrosDescuentos += otrosDesc;
        
        $row.find('.col-impuestos-det').each(function(idx) {
            var impVal = parseNumber($(this).text());
            if (impuestosAcumulados[idx] !== undefined) {
                impuestosAcumulados[idx] += impVal;
            }
        });
        
        var contribucion = parseNumber($row.find('.contribucion').text());
        tContribucion += contribucion;
        
        if (tipoNomina === 'bono') {
            var $bonoInput = $row.find('.edit-bono');
            var bonoVal = $bonoInput.length ? parseNumber($bonoInput.val()) : parseNumber($row.find('.bono-val-cell').text());
            tBono += bonoVal;
        } else if (tipoNomina === 'vacaciones') {
            var diasValue = 0;
            if ($row.find('.edit-dias').length) {
                diasValue = parseNumber($row.find('.edit-dias').val());
            } else {
                diasValue = parseNumber($row.find('td').eq(7).text());
            }
            tDias += diasValue;
        } else if (tipoNomina === 'automatica' || tipoNomina === 'extraordinaria') {
            var horasNorm = $row.find('.edit-horas').length ? parseNumber($row.find('.edit-horas').val()) : parseNumber($row.find('td').eq(8).text());
            tHorasNormales += horasNorm;
            
            var salarioBasico = parseNumber($row.find('.salario-basico').text());
            tSalarioBasico += salarioBasico;
            
            var salarioLaboral = parseNumber($row.find('.salario-laboral').text());
            tSalarioLaboral += salarioLaboral;
            
            if (tipoNomina === 'automatica') {
                var feriadosDias = $row.find('.edit-feriados').length ? parseNumber($row.find('.edit-feriados').val()) : parseNumber($row.find('.col-feriados-dias').text());
                tFeriadosDias += feriadosDias;
                tFeriadosImporte += parseNumber($row.find('.feriados-importe').text());
                tOtrosPagos += parseNumber($row.find('.edit-otros-pagos').val() || $row.find('.col-otros-pagos').text());
            }
            
            if (tipoNomina === 'extraordinaria') {
                tHorasNocturnas += $row.find('.edit-nocturnas').length ? parseNumber($row.find('.edit-nocturnas').val()) : parseNumber($row.find('td').eq(9).text());
                tImporteNocturno += parseNumber($row.find('.importe-nocturno').text());
            }
            
            if (tipoNomina === 'automatica') {
                tVacacionesDias += parseNumber($row.find('.vacaciones-dias').text());
                tVacacionesImporte += parseNumber($row.find('.vacations-importe').text());
            }
        }
    });
    
    $('#totalDevengado').text('$' + formatNumber(tDev));
    $('#totalNeto').text('$' + formatNumber(tNeto));
    $('#totalDescuentos').text('$' + formatNumber(tDesc)); // Mantiene el total de deducciones en la tarjeta de estadísticas
    $('#statTrabajadores').text(totalTrabajadores);
    
    if (tipoNomina === 'bono') {
        $('.total-monto-bono-footer').text('$' + formatNumber(tBono));
    }
    
    if (tipoNomina === 'vacaciones') {
        $('.total-vacaciones-dias-footer').text(formatNumber(tDias));
    }
    
    actualizarTfootTotales({
        totalHoras: tHorasNormales,
        totalSalarioBasico: tSalarioBasico,
        totalSalarioLaboral: tSalarioLaboral,
        totalFeriadosDias: tFeriadosDias,
        totalFeriadosImporte: tFeriadosImporte,
        totalNocturnas: tHorasNocturnas,
        totalImporteNocturno: tImporteNocturno,
        totalVacacionesDias: tVacacionesDias,
        totalVacacionesImporte: tVacacionesImporte,
        totalOtrosPagos: tOtrosPagos,
        totalDevengado: tDev,
        totalDescuentos: tOtrosDescuentos, // Pasa la suma real de "Otros Descuentos" al totalizador del pie de la columna
        totalDeducciones: tDesc,            // Pasa la suma real de todas las deducciones a su respectiva columna
        totalContribucion: tContribucion,
        totalNeto: tNeto,
        totalBono: tBono,
        totalVacaciones: tDias,
        impuestosPorRango: impuestosAcumulados
    });
}


function actualizarTfootTotales(totales) {
    $('.total-devengado-footer').text('$' + formatNumber(totales.totalDevengado));
    $('.total-descuentos-footer').text('$' + formatNumber(totales.totalDescuentos));
    $('.total-deducciones-footer').text('$' + formatNumber(totales.totalDeducciones));
    $('.total-contribucion-footer').text('$' + formatNumber(totales.totalContribucion));
    $('.total-neto-footer').text('$' + formatNumber(totales.totalNeto));
    
    if (totales.impuestosPorRango && totales.impuestosPorRango.length > 0) {
        for (var i = 0; i < totales.impuestosPorRango.length; i++) {
            $('.total-impuesto-' + i + '-footer').text('$' + formatNumber(totales.impuestosPorRango[i]));
        }
    }

    if (tipoNomina === 'automatica') {
        $('.total-horas-footer').text(formatNumber(totales.totalHoras));
        $('.total-salario-basico-footer').text('$' + formatNumber(totales.totalSalarioBasico));
        $('.total-salario-laboral-footer').text('$' + formatNumber(totales.totalSalarioLaboral));
        $('.total-feriados-dias-footer').text(formatNumber(totales.totalFeriadosDias));
        $('.total-feriados-importe-footer').text('$' + formatNumber(totales.totalFeriadosImporte));
        $('.total-vacaciones-dias-footer').text(formatNumber(totales.totalVacacionesDias));
        $('.total-vacaciones-importe-footer').text('$' + formatNumber(totales.totalVacacionesImporte));
        $('.total-otros-pagos-footer').text('$' + formatNumber(totales.totalOtrosPagos));
    }
    
    if (tipoNomina === 'extraordinaria') {
        $('.total-horas-footer').text(formatNumber(totales.totalHoras));
        $('.total-salario-basico-footer').text('$' + formatNumber(totales.totalSalarioBasico));
        $('.total-salario-laboral-footer').text('$' + formatNumber(totales.totalSalarioLaboral));
        $('.total-nocturnas-footer').text(formatNumber(totales.totalNocturnas));
        $('.total-importe-nocturno-footer').text('$' + formatNumber(totales.totalImporteNocturno));
    }
    
    if (tipoNomina === 'bono') {
        $('.total-monto-bono-footer').text('$' + formatNumber(totales.totalBono));
    }
    
    if (tipoNomina === 'vacaciones') {
        $('.total-vacaciones-dias-footer').text(formatNumber(totales.totalVacaciones));
    }
}
    
    $('#menuColumnas').on('click', function(e) {
        e.preventDefault();
        $('.buttons-colvis').click();
    });

$('#menuNominaImpresa').on('click', function(e) {
    e.preventDefault();
    
    // 1. Verificar si existe nómina en el período actual
    var existeNomina = <?php echo $existe_nomina ? 'true' : 'false'; ?>;
    var tipoNominaTexto = '<?php echo $tipos_nomina[$tipo_nomina_activa]['nombre']; ?>';
    var periodoTexto = '<?php echo $nombre_mes . ' ' . $anio; ?>';
    
    if (!existeNomina) {
        Swal.fire({
            title: '<i class="fas fa-inbox me-2" style="color: rgba(255,255,255,0.4);"></i> No hay nómina generada',
            html: `
                <div style="text-align: center;">
                    <p style="color: rgba(255,255,255,0.6);">No existe nómina de tipo <strong>${tipoNominaTexto}</strong> para <strong>${periodoTexto}</strong></p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff',
backdrop: 'rgba(0,0,0,0.6)'
        });
        return;
    }
    
    // 2. Verificar el estado de las filas visibles (permitido si todas son contabilizadas de un solo lote)
    var estado = window.obtenerEstadoImpresion();
    
    if (estado.motivo === 'vacio') {
        Swal.fire({
            title: '<i class="fas fa-inbox me-2" style="color: #60a5fa;"></i> Sin filas visibles',
            html: '<div class="text-center"><p>No hay registros visibles para generar la Nómina Impresa.</p><p class="text-muted small">Revise la búsqueda y el filtro de número de nómina.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    if (estado.motivo === 'borrador') {
        Swal.fire({
            title: '<i class="fas fa-file-alt me-2" style="color: #f59e0b;"></i> Nómina en estado Borrador',
            html: `
                <div style="text-align: center;">
                    <i class="fas fa-file-alt fa-4x mb-3" style="color: #f59e0b; opacity: 0.7;"></i>
                    <h4 class="mb-2" style="color: #ffffff;">Nómina en estado Borrador</h4>
                    <p style="color: rgba(255,255,255,0.6);">No es posible generar la <strong>Nómina Impresa</strong> hasta que la nómina sea <strong>contabilizada</strong>.</p>
                    <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        Mientras está en Borrador, puedes usar el botón <strong>"Imprimir Reporte"</strong> para obtener una vista previa.
                    </p>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#f59e0b',
            background: '#1a1a2e',
            color: '#ffffff',
        backdrop: 'rgba(0,0,0,0.6)'
        });
        return;
    }
    
    if (estado.motivo === 'multiples') {
        Swal.fire({
            title: '<i class="fas fa-layers me-2" style="color: #60a5fa;"></i> Varios números de nómina visibles',
            html: '<div class="text-center"><p>Hay <strong>' + estado.numeros.length + ' nóminas diferentes</strong> visibles a la vez.</p><p class="mt-3">Use el filtro <strong>"No. Nómina"</strong> para seleccionar una sola nómina y luego genere la <strong>Nómina Impresa</strong>.</p></div>',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#3b82f6',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    if (estado.numero) numeroNomina = estado.numero;
    
    // 3. Si existe y está contabilizada, mostrar el modal de opciones de impresión
    const modalOpciones = new bootstrap.Modal(document.getElementById('modalOpcionesImpresion'));
    modalOpciones.show();
    cargarSelectores();
    $('input[name="alcanceImpresion"]').off('change').on('change', cargarSelectores);
});

	$('#menuMontos').on('click', function(e) {
		e.preventDefault();
		cargarHistorialMontos();
	});
    $('#menuExportPDF').on('click', function(e) {
        e.preventDefault();
        $('.buttons-pdf').click();
    });

    $('#menuExportWord').on('click', function(e) {
        e.preventDefault();
        $('.buttons-word').click();
    });

    $('#menuExportExcel').on('click', function(e) {
        e.preventDefault();
        $('.buttons-excel').click();
    });

    $('#menuExportCSV').on('click', function(e) {
        e.preventDefault();
        $('.buttons-csv').first().click();
    });

    $('#menuExportTXT').on('click', function(e) {
        e.preventDefault();
        $('.buttons-csv').last().click();
    });

    $('#menuExportPrint').on('click', function(e) {
        e.preventDefault();
        $('.buttons-print').click();
    });

function aplicarFiltros() {
    if (!nominasTable) return;
    
    var filtroTrabajador = $('#filtroTrabajador').val();
    var filtroArea = $('#filtroArea').val();
    var filtroCentro = $('#filtroCentroCosto').val();
    var filtroCuenta = $('#filtroCuenta').val();
    var filtroAcumulaVac = $('#filtroAcumulaVacaciones').val();
    var filtroRangoVac = $('#filtroRangoVacaciones').val();
    var filtroNumeroNomina = $('#filtroNumeroNomina').val();
    
    if ($('#filtroRangoVacaciones').prop('disabled')) {
        filtroRangoVac = '';
    }
    
    if ($('#filtroAcumulaVacaciones').val() === 'no' && 
        $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled')) {
        filtroAcumulaVac = '';
    }
    
    $.fn.dataTable.ext.search.pop();
    
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = nominasTable.row(dataIndex).node();
        var $row = $(row);
        
        var okTrab = !filtroTrabajador || ($row.data('trabajador-id') == filtroTrabajador);
        var okArea = !filtroArea || ($row.data('area-id') == filtroArea);
        var okCentro = !filtroCentro || ($row.data('centro-costo-id') == filtroCentro);
        var okCuenta = !filtroCuenta || ($row.data('tiene-cuenta') === filtroCuenta);
        var okNumero = !filtroNumeroNomina || ($row.data('numero-nomina') === filtroNumeroNomina);
        
        var okAcumulaVac = true;
        if (filtroAcumulaVac) {
            var noAcumula = parseInt($row.data('no-acumular-vacaciones')) || 0;
            okAcumulaVac = (filtroAcumulaVac === 'si' && noAcumula === 0) ||
                           (filtroAcumulaVac === 'no' && noAcumula === 1);
        }
        
        var okRangoVac = true;
        if (filtroRangoVac && !$('#filtroRangoVacaciones').prop('disabled')) {
            var diasAcum = parseFloat($row.data('dias-acumulados')) || 0;
            switch(filtroRangoVac) {
                case '0-5': okRangoVac = (diasAcum >= 0 && diasAcum <= 5); break;
                case '5-10': okRangoVac = (diasAcum > 5 && diasAcum <= 10); break;
                case '10-15': okRangoVac = (diasAcum > 10 && diasAcum <= 15); break;
                case '15-20': okRangoVac = (diasAcum > 15 && diasAcum <= 20); break;
                case '20-100': okRangoVac = (diasAcum > 20); break;
                default: okRangoVac = true;
            }
        }
        
        return okTrab && okArea && okCentro && okCuenta && okAcumulaVac && okRangoVac && okNumero;
    });
    
    nominasTable.draw();
    actualizarEstadoVista();
}

// 🔽 NUEVO: Actualiza el título del card según la nómina filtrada (No. concreto o "(TODAS)").
function actualizarTituloCard() {
    var $spanTitulo = $('#tituloCardNumeroNomina');
    if ($spanTitulo.length === 0) return;
    var filtroNum = $('#filtroNumeroNomina').val();
    if (filtroNum && filtroNum !== 'Borrador') {
        $spanTitulo.html('No.: <span class="text-warning">' + $('<div>').text(filtroNum).html() + '</span>');
    } else if (filtroNum === 'Borrador') {
        $spanTitulo.html('<span class="text-info">(Borrador)</span>');
    } else {
        $spanTitulo.html('<span class="text-warning">(TODAS)</span>');
    }
}

// 🔽 NUEVO: Actualiza dinámicamente botones/badge/observaciones según la nómina de ajuste filtrada.
function actualizarEstadoVista() {
    if (!nominasTable) return;

    // El título dinámico solo aplica a la nómina de ajuste (múltiples números por período)
    if (window.tipoNomina === 'ajuste') {
        actualizarTituloCard();
    }

    if (window.tipoNomina !== 'ajuste') return;

    var $btnBorrador = $('#accionesBorradorAjuste');
    var $badge = $('#badgeContabilizadaAjuste');
    var $alerta = $('#alertaObservacionesCierre');
    var $textoObs = $('#textoObservacionesCierre');
    var $btnEliminarTodoAjuste = $('#btnEliminarTodoAjuste');
    var $revertirAjuste = $('#accionesRevertirAjuste');
    if ($btnBorrador.length === 0 || $badge.length === 0) return;

    var filtroNum = $('#filtroNumeroNomina').val();

    if (filtroNum && filtroNum !== 'Borrador') {
        // Nómina de ajuste específica y contabilizada
        $btnBorrador.hide();
        $badge.show();
        if ($btnEliminarTodoAjuste.length) $btnEliminarTodoAjuste.hide();
        if ($revertirAjuste.length) {
            $revertirAjuste.css('display', 'flex').show();
            $('#revertirBtnAjuste').data('numero', filtroNum);
        }
        var obs = (observacionesPorNomina && observacionesPorNomina[filtroNum] !== undefined)
            ? observacionesPorNomina[filtroNum]
            : observacionesCierreGlobal;
        $textoObs.text(obs || '');
        if (obs) { $alerta.show(); } else { $alerta.hide(); }
        return;
    }

    // "Todos" o "Borrador": alternar según filas visibles
    var filasVisibles = $(nominasTable.rows({ search: 'applied' }).nodes());
    var hayBorradorVisible = false;
    var hayContabilizadaVisible = false;
    var numerosVisibles = {};
    filasVisibles.each(function() {
        var $row = $(this);
        if ($row.find('.badge-borrador').length) hayBorradorVisible = true;
        else hayContabilizadaVisible = true;
        var num = $row.find('.col-numero-nomina').text().trim();
        if (num && num !== 'Borrador') numerosVisibles[num] = true;
    });

    if (hayBorradorVisible) {
        $btnBorrador.show();
        $badge.hide();
    } else {
        $btnBorrador.hide();
        $badge.show();
    }
    if ($revertirAjuste.length) $revertirAjuste.hide();

    // "Eliminar Todo" solo si hay filas visibles y TODAS son Borrador
    if ($btnEliminarTodoAjuste.length) {
        if (filasVisibles.length > 0 && hayBorradorVisible && !hayContabilizadaVisible) {
            $btnEliminarTodoAjuste.show();
        } else {
            $btnEliminarTodoAjuste.hide();
        }
    }

    // Observaciones: si hay exactamente una nómina visible (sin borradores), mostrar la suya
    var claves = Object.keys(numerosVisibles);
    var obsDefault = observacionesCierreGlobalOriginal || '';
    if (!hayBorradorVisible && claves.length === 1 && observacionesPorNomina &&
        observacionesPorNomina[claves[0]] !== undefined && observacionesPorNomina[claves[0]] !== '') {
        obsDefault = observacionesPorNomina[claves[0]];
    }
    $textoObs.text(obsDefault);
    if (obsDefault) { $alerta.show(); } else { $alerta.hide(); }
}
    $('#filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroNumeroNomina').on('change', aplicarFiltros);

$('#btnLimpiarFiltros').off('click').on('click', function(e) {
    e.preventDefault();
    
    $('#filtroTrabajador, #filtroArea, #filtroCentroCosto, #filtroCuenta, #filtroAcumulaVacaciones, #filtroRangoVacaciones, #filtroNumeroNomina').val('');
    
    $('#filtroRangoVacaciones').prop('disabled', false);
    $('#filtroRangoVacaciones').attr('title', 'Filtrar por rango de días acumulados');
    $('#filtroAcumulaVacaciones').find('option[value="no"]').prop('disabled', false);
    $('#filtroAcumulaVacaciones').find('option[value="no"]').css('color', '');
    
    if ($.fn.DataTable.isDataTable('#tablaNominas')) {
        var table = $('#tablaNominas').DataTable();
        table.search('').draw();
        $('#customSearchInput').val('');
    }
    
    $.fn.dataTable.ext.search.pop();
    
    var url = new URL(window.location.href);
    if (url.searchParams.has('filtro_cuenta')) {
        url.searchParams.delete('filtro_cuenta');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
    
    if (nominasTable) nominasTable.draw();
    actualizarEstadoVista();
    
    Swal.fire({
        icon: 'success',
        title: 'Filtros eliminados',
        text: 'Se han restablecido todos los filtros de búsqueda.',
        timer: 1500,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#fff'
    });
});

	function parseNumber(value) {
		if (!value || value === '-' || value === '') return 0;
		// Remover $, comas y cualquier caracter no numérico excepto punto y negativo
		var num = parseFloat(value.toString().replace(/[^0-9.-]/g, ''));
		if (isNaN(num)) return 0;
		return Math.max(0, num);
	}
    function formatNumber(num) { return num.toFixed(2); }

    function obtenerEscalaRomana(num) {
        if (!num || num === '?' || num === 'S/D') return 'S/D';
        var val = parseInt(num);
        if (isNaN(val)) return num; 
        var romanos = {
            1:'I', 2:'II', 3:'III', 4:'IV', 5:'V', 6:'VI', 7:'VII', 8:'VIII', 9:'IX', 10:'X', 
            11:'XI', 12:'XII', 13:'XIII', 14:'XIV', 15:'XV', 16:'XVI', 17:'XVII', 18:'XVIII', 19:'XIX', 20:'XX'
        };
        return romanos[val] || num;
    }

    function calcularCessProgresivoJS(salario) {
        var limite = 15000;
        if (salario <= limite) {
            return Math.roundExcel((salario * 0.05) * 100) / 100;
        } else {
            return Math.roundExcel(((15000 * 0.05) + ((salario - 15000) * 0.10)) * 100) / 100;
        }
    }

    function calcularImpuestoProgresivo(salario) {
        if (!rangosImpuesto || salario <= 0) return 0;
        var total = 0;
        for (var i = 0; i < rangosImpuesto.length; i++) {
            var desde = parseFloat(rangosImpuesto[i].desde);
            var hasta = rangosImpuesto[i].hasta ? parseFloat(rangosImpuesto[i].hasta) : Infinity;
            var tasa = parseFloat(rangosImpuesto[i].tasa);
            if (tasa > 0 && salario > desde) total += Math.min(salario - desde, hasta - desde) * tasa;
        }
        return Math.roundExcel(total * 100) / 100;
    }
    
    function calcularImpuestosPorRangoProgresivo(salario) {
        var impuestos = [];
        for (var i = 0; i < rangosImpuesto.length; i++) {
            var desde = parseFloat(rangosImpuesto[i].desde);
            var hasta = rangosImpuesto[i].hasta ? parseFloat(rangosImpuesto[i].hasta) : Infinity;
            var tasa = parseFloat(rangosImpuesto[i].tasa);
            var imp = (tasa > 0 && salario > desde) ? Math.min(salario - desde, hasta - desde) * tasa : 0;
            impuestos.push(Math.roundExcel(imp * 100) / 100);
        }
        return impuestos;
    }

function recalcularFilaVacaciones(fila) {
    var salarioMensual = parseFloat(fila.data('salario-mensual')) || 0;
    var diasActuales = Math.max(0, parseFloat(fila.find('.edit-dias').val()) || 0);
    var diasAcumuladosOriginal = Math.max(0, parseFloat(fila.data('dias-acumulados')) || 0);
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var descuentos = Math.max(0, parseNumber(fila.find('.edit-descuentos').val()));
    
    if (diasActuales < 0) {
        diasActuales = 0;
        fila.find('.edit-dias').val(0);
    }
    
    var diasDisponibles = diasAcumuladosOriginal;
    if (diasActuales > diasDisponibles) {
        Swal.fire({
            title: 'Advertencia',
            text: 'No se pueden asignar más días de los disponibles. Días disponibles: ' + diasDisponibles.toFixed(2),
            icon: 'warning',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        fila.find('.edit-dias').val(diasDisponibles.toFixed(2));
        diasActuales = diasDisponibles;
    }
    
    var valorPorDia = salarioMensual / diasLaborables;
    var importe = diasActuales * valorPorDia;
    var diasRestantes = diasAcumuladosOriginal - diasActuales;
    if (diasRestantes < 0) diasRestantes = 0;
    
    var contribucion = 0;
    var impuesto = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(importe);
        impuesto = 0;
        for (var i = 0; i < rangosImpuesto.length; i++) {
            impuestosArr.push(0);
        }
    } else {
        contribucion = Math.roundExcel((importe * 0.05) * 100) / 100;
        impuesto = calcularImpuestoProgresivo(importe);
        impuestosArr = calcularImpuestosPorRangoProgresivo(importe);
    }

    var netoAntesDesc = importe - (contribucion + impuesto);
    if (netoAntesDesc < 0) netoAntesDesc = 0;
    if (descuentos > netoAntesDesc) descuentos = netoAntesDesc;
    
    var neto = Math.roundExcel((netoAntesDesc - descuentos) * 100) / 100;
    
    fila.find('.total-devengado').text('$' + formatNumber(importe));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuesto + descuentos));
    
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    
    fila.find('.neto').text('$' + formatNumber(neto));
    fila.find('.dias-restantes span').text(diasRestantes.toFixed(2));
    
    var restantesColor = '#10b981';
    if (diasRestantes <= 0) restantesColor = '#ef4444';
    else if (diasRestantes <= 5) restantesColor = '#f59e0b';
    fila.find('.dias-restantes span').css('color', restantesColor);
    
    actualizarEstadisticas();
}

function recalcularFilaAutomatica(fila) {
    var salarioHora = parseFloat(fila.data('salario-hora')) || 0;
    var salarioMensual = parseFloat(fila.data('salario-mensual')) || 0;
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var noAcumularVacaciones = parseInt(fila.data('no-acumular-vacaciones')) || 0;
    var tipoNominaActual = tipoNomina; 

    var horas = Math.max(0, parseNumber(fila.find('.edit-horas').val()));
    var diasFeriados = 0;
    var descuentos = Math.max(0, parseNumber(fila.find('.edit-descuentos').val()));
    var otrosPagos = 0;
    var horasNocturnas = 0;
    
    if (tipoNominaActual === 'automatica') {
        diasFeriados = Math.max(0, parseNumber(fila.find('.edit-feriados').val()));
        otrosPagos = Math.max(0, parseNumber(fila.find('.edit-otros-pagos').val()));
    } else {
        horasNocturnas = Math.max(0, parseNumber(fila.find('.edit-nocturnas').val()));
    }
    
    // Validar valores negativos
    if (parseFloat(fila.find('.edit-horas').val()) < 0) fila.find('.edit-horas').val(horas);
    if (tipoNominaActual === 'automatica') {
        if (parseFloat(fila.find('.edit-feriados').val()) < 0) fila.find('.edit-feriados').val(diasFeriados);
        if (parseFloat(fila.find('.edit-otros-pagos').val()) < 0) fila.find('.edit-otros-pagos').val(otrosPagos);
    } else {
        if (parseFloat(fila.find('.edit-nocturnas').val()) < 0) fila.find('.edit-nocturnas').val(horasNocturnas);
    }
    if (parseFloat(fila.find('.edit-descuentos').val()) < 0) fila.find('.edit-descuentos').val(descuentos);
    
    // =========================================================
    // 1. CÁLCULOS BÁSICOS
    // =========================================================
    var salarioLaboral = salarioHora * horas;
    var salarioDiario = salarioMensual / 24; // 24 días laborables
    
    var importeFeriados = 0;
    if (tipoNominaActual === 'automatica') {
        importeFeriados = salarioDiario * diasFeriados * 2;
    }
    
    var valorHoraNocturna = salarioHora * recargoNocturno;
    var importeHorasNocturnas = (tipoNominaActual === 'extraordinaria') ? (valorHoraNocturna * horasNocturnas) : 0;

    // =========================================================
    // 2. CÁLCULO PROPORCIONAL DE VACACIONES (MÉTODO 9.09%)
    // =========================================================
    var factor909 = 0.0909; 
    var horasJornadaDB = 8;
    
    // Días del mes = (Horas Trabajadas * 0.0909) / 8
    var diasProporcional = Math.roundExcel(((horas * factor909) / horasJornadaDB), 2);
    
    // Importe del mes = Días del mes * Salario Diario
    var importeVacacionesMes = Math.roundExcel(diasProporcional * salarioDiario, 2);

    // =========================================================
    // 3. DETERMINAR IMPORTE DE VACACIONES ADICIONAL
    // =========================================================
    var importeVacacionesAdicional = 0;
    var diasAcumuladosHistoricos = parseFloat(fila.data('dias-acumulados-value')) || 0;
    
    if (tipoNominaActual === 'automatica') {
        if (noAcumularVacaciones === 1) {
            // Si NO acumula, se le paga el importe en el devengado del mes
            importeVacacionesAdicional = importeVacacionesMes;
            
            // ✅ ACTUALIZAR: Mostrar los días y el importe que se están pagando en el mes
            fila.find('.vacaciones-dias').text(diasProporcional.toFixed(2));
            fila.find('.vacations-importe').text('$' + importeVacacionesMes.toFixed(2));
        } else {
            // Si acumula, sumamos histórico + proporcional del mes
            var totalDiasVis = diasAcumuladosHistoricos + diasProporcional;
            var totalImpVis = totalDiasVis * salarioDiario;
            
            // ✅ ACTUALIZAR: Mostrar el total acumulado (histórico + mes actual)
            fila.find('.vacaciones-dias').text(totalDiasVis.toFixed(2));
            fila.find('.vacations-importe').text('$' + totalImpVis.toFixed(2));
            
            // Actualizar el atributo para el modal
            fila.attr('data-dias-acumulados', totalDiasVis);
        }
    }

    // =========================================================
    // 4. TOTAL DEVENGADO
    // =========================================================
    var totalDevengado = salarioLaboral + importeFeriados + importeHorasNocturnas + otrosPagos + importeVacacionesAdicional;
    
    // =========================================================
    // 5. CÁLCULO DE DEDUCCIONES
    // =========================================================
    var contribucion = 0;
    var impuestoTotal = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(totalDevengado);
        impuestoTotal = 0;
        for (var i = 0; i < rangesImpuestosLength(); i++) {
            impuestosArr.push(0);
        }
    } else {
        contribucion = totalDevengado * 0.05;
        impuestoTotal = calcularImpuestoProgresivo(totalDevengado);
        impuestosArr = calcularImpuestosPorRangoProgresivo(totalDevengado);
    }

    // =========================================================
    // 6. NETO FINAL
    // =========================================================
    var netoAntesDesc = totalDevengado - (contribucion + impuestoTotal);
    if (descuentos > netoAntesDesc && netoAntesDesc > 0) descuentos = netoAntesDesc;
    if (descuentos < 0) descuentos = 0;
    fila.find('.edit-descuentos').val(formatNumber(descuentos));
    
    var netoFinal = netoAntesDesc - descuentos;
    
    // =========================================================
    // 7. ACTUALIZAR CELDAS DE LA TABLA
    // =========================================================
    
    // Salario laboral y valor por hora
    fila.find('.salario-laboral').text('$' + formatNumber(salarioLaboral));
    fila.find('.salario-hora-real').text('$' + formatNumber(horas > 0 ? salarioLaboral/horas : 0));
    
    // Feriados o Nocturnas
    if (tipoNominaActual === 'automatica') {
        fila.find('.feriados-importe').text('$' + formatNumber(importeFeriados));
    } else {
        fila.find('.importe-nocturno').text('$' + formatNumber(importeHorasNocturnas));
    }

    // Totales y deducciones
    fila.find('.total-devengado').text('$' + formatNumber(totalDevengado));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuestoTotal + descuentos));
    
    // Impuestos por rango
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    
    // Neto
    fila.find('.neto').text('$' + formatNumber(netoFinal));
    
    // =========================================================
    // 8. ACTUALIZAR ESTADÍSTICAS GENERALES
    // =========================================================
    actualizarEstadisticas();
}

    function rangesImpuestosLength() {
        return rangosImpuesto ? rangosImpuesto.length : 0;
    }



function recalcularFilaBono(fila) {
    var tipoDescuento = fila.data('tipo-descuento') || 'total_rangos';
    var val = parseNumber(fila.find('.edit-bono').val());
    var descuentos = parseNumber(fila.find('.edit-descuentos').val());

    if (val < 0) val = 0;
    if (descuentos < 0) descuentos = 0;

    var contribucion = 0;
    var impuesto = 0;
    var impuestosArr = [];

    if (tipoDescuento === 'solo_cess') {
        contribucion = calcularCessProgresivoJS(val);
        impuesto = 0;
        for (var i = 0; i < rangesImpuestosLength(); i++) {
            impuestosArr.push(0);
        }
    } else {
        contribucion = Math.roundExcel((val * 0.05) * 100) / 100;
        impuesto = calcularImpuestoProgresivo(val);
        impuestosArr = calcularImpuestosPorRangoProgresivo(val);
    }

    var netoAntesDesc = val - (contribucion + impuesto);
    if (netoAntesDesc < 0) netoAntesDesc = 0;

    if (descuentos > netoAntesDesc) descuentos = netoAntesDesc;
    var netoFinal = netoAntesDesc - descuentos;
    if (netoFinal < 0) netoFinal = 0;

    fila.find('.edit-descuentos').val(formatNumber(descuentos));

    var $descuentosInput = fila.find('.edit-descuentos');
    if (netoFinal <= 0) {
        $descuentosInput.prop('disabled', true).val(0);
        $descuentosInput.css('opacity', '0.6');
        netoFinal = 0;
    } else {
        $descuentosInput.prop('disabled', false);
        $descuentosInput.css('opacity', '1');
    }

    fila.find('.total-devengado').text('$' + formatNumber(val));
    fila.find('.contribucion').text('$' + formatNumber(contribucion));
    fila.find('.total-deducciones').text('$' + formatNumber(contribucion + impuesto + descuentos));
    
    for (var i = 0; i < impuestosArr.length; i++) {
        fila.find('.impuesto-rango-' + i).text('$' + formatNumber(impuestosArr[i]));
    }
    fila.find('.neto').text('$' + formatNumber(netoFinal));

    actualizarEstadisticas();
}

$(document).on('input', '.edit-horas, .edit-feriados, .edit-descuentos, .edit-otros-pagos, .edit-bono, .edit-dias, .edit-nocturnas', function() {
    if (contabilizada) return;
    
    var $input = $(this);
    var valor = parseNumber($input.val());
    
    if (valor < 0 || isNaN(valor)) {
        valor = 0;
        $input.val('0');
    }
    
    var fila = $(this).closest('tr');
    
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL DE CAMPOS CLAVE EN CERO
    // ==========================================
    var esCampoClave = false;
    var selectorCampo = getSelectorCampo(tipoNomina);
    
    // Verificar si el input que cambió es el campo clave de esta nómina
    if ($input.is(selectorCampo)) {
        esCampoClave = true;
    }
    
    // Recalcular según tipo
    if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
        recalcularFilaAutomatica(fila);
    } else if (tipoNomina == 'bono') {
        recalcularFilaBono(fila);
    } else if (tipoNomina == 'vacaciones') {
        recalcularFilaVacaciones(fila);
    } else if (tipoNomina == 'ajuste') {
        recalcularFilaBono(fila);
    }
    
    // ✅ Si es el campo clave, validar después del recalculo
    if (esCampoClave) {
        setTimeout(function() {
            validarCamposCero(fila);
        }, 300);
    }
});

$(document).on('click', '.guardar-fila', function() {
    if (contabilizada) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-lock me-2" style="color: #f59e0b;"></i> Nómina Contabilizada',
            html: '<p>No se pueden guardar cambios en una nómina contabilizada.</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            background: '#1E1E1E',
            color: '#FFFFFF',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }
    
    var fila = $(this).closest('tr');
    var trabajadorNombre = fila.find('td:eq(2)').text().trim();
    var id = fila.data('id');
    
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL ANTES DE GUARDAR
    // ==========================================
    var campoValor = 0;
    var selectorCampo = getSelectorCampo(tipoNomina);
    var nombreCampo = getNombreCampo(tipoNomina);
    
    if (selectorCampo) {
        var $campoInput = fila.find(selectorCampo);
        campoValor = $campoInput.length ? parseNumber($campoInput.val()) : 0;
    }
    
    // Si el campo clave está en cero, mostrar advertencia
    if (campoValor === 0 || campoValor < 0.5) {
        Swal.fire({
            title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
            html: `
                <div class="text-center">
                    <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #f59e0b;"></i>
                    <p>El trabajador <strong>${escapeHtml(trabajadorNombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span>.</p>
                    <p class="text-muted small">No se puede guardar un trabajador con 0 ${nombreCampo} en la nómina.</p>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: `<i class="fas fa-pen me-2"></i>Asignar ${nombreCampo}`,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar',
            cancelButtonColor: '#ef4444',
            background: '#1a1a2e',
            color: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                // Asignar valor: enfocar el input
                setTimeout(function() {
                    var $input = fila.find(selectorCampo);
                    if ($input.length) {
                        $input.focus().select();
                    }
                }, 200);
            } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                // Eliminar trabajador
                eliminarTrabajadorPorId(id, trabajadorNombre);
            }
        });
        return;
    }
    
    // Continuar con el guardado normal...
    var datos = { actualizar_nomina: 1, id: id, tipo_nomina: tipoNomina };
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...',
            html: `Guardando cambios de <strong>${trabajadorNombre}</strong>`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1E1E1E',
            color: '#FFFFFF'
        });
        
        if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
            datos.horas_laboradas = parseNumber(fila.find('.edit-horas').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
            
            if (tipoNomina == 'automatica') {
                datos.dias_feriados = parseNumber(fila.find('.edit-feriados').val());
                datos.otros_salarios = parseNumber(fila.find('.edit-otros-pagos').val());
                datos.horas_nocturnas = 0;
            } else {
                datos.horas_nocturnas = parseNumber(fila.find('.edit-nocturnas').val());
                datos.dias_feriados = 0;
                datos.otros_salarios = 0;
            }
        } else if (tipoNomina == 'bono') {
            datos.monto_bono = parseNumber(fila.find('.edit-bono').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
        } else if (tipoNomina == 'ajuste') {
            datos.monto_bono = parseNumber(fila.find('.edit-bono').val());
            datos.descuentos = parseNumber(fila.find('.edit-descuentos').val());
        } else if (tipoNomina == 'vacaciones') {
            datos.dias_vacaciones = parseNumber(fila.find('.edit-dias').val());
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function(r) {
                if (r.success) {
                    var resumenHtml = '';
                    if (tipoNomina == 'automatica' || tipoNomina == 'extraordinaria') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Horas:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.horas_laboradas}</strong></div>
                                </div>
                                ${tipoNomina == 'automatica' ? `
                                <div class="row">
                                    <div class="col-6 text-start"><small>Días feriados:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.dias_feriados}</strong></div>
                                </div>
                                ` : `
                                <div class="row">
                                    <div class="col-6 text-start"><small>Horas nocturnas:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.horas_nocturnas}</strong></div>
                                </div>
                                `}
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'bono') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Monto Bono:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.monto_bono.toFixed(2)}</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'ajuste') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Monto Ajuste:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.monto_bono.toFixed(2)}</strong></div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-start"><small>Descuentos:</small></div>
                                    <div class="col-6 text-end"><strong>$${datos.descuentos.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    } else if (tipoNomina == 'vacaciones') {
                        resumenHtml = `
                            <div class="mt-3 pt-2 border-top border-secondary">
                                <div class="row">
                                    <div class="col-6 text-start"><small>Días tomados:</small></div>
                                    <div class="col-6 text-end"><strong>${datos.dias_vacaciones.toFixed(2)}</strong></div>
                                </div>
                            </div>
                        `;
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Guardado exitoso',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-user-check fa-3x mb-3" style="color: #10b981;"></i>
                                <p class="mb-1"><strong>${trabajadorNombre}</strong></p>
                                <p class="text-muted small">Los cambios han sido guardados correctamente</p>
                                ${resumenHtml}
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1E1E1E',
                        color: '#FFFFFF',
                        confirmButtonColor: '#10b981',
                        timer: 2500,
                        timerProgressBar: true
                    });
                    
                    actualizarEstadisticas();
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error al guardar',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-times-circle fa-3x mb-3" style="color: #ef4444;"></i>
                                <p><strong>${trabajadorNombre}</strong></p>
                                <p class="text-danger">${r.error || 'No se pudo guardar el registro'}</p>
                            </div>
                        `,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                        background: '#1E1E1E',
                        color: '#FFFFFF',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fas fa-plug me-2" style="color: #ef4444;"></i> Error de conexión',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-wifi fa-3x mb-3" style="color: #ef4444;"></i>
                            <p><strong>${trabajadorNombre}</strong></p>
                            <p class="text-danger">No se pudo conectar con el servidor</p>
                            <p class="text-muted small">Error: ${error}</p>
                        </div>
                    `,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    background: '#1E1E1E',
                    color: '#FFFFFF',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    });

    $('#consultarBtn').on('click', function() {
        var url = 'nominas.php?periodo=' + $('#anioSelect').val() + '-' + $('#mesSelect').val() + '&tipo=' + tipoNomina;
        if (filtroCuentaActual) url += '&filtro_cuenta=' + filtroCuentaActual;
        window.location.href = url;
    });

    $('#btnRegenerarNomina').on('click', function() {
        Swal.fire({
            title: '¿Regenerar nómina?', text: 'Se perderán los cambios manuales no guardados.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#0ea5e9', confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Regenerar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => { if (r.isConfirmed) $('#formRegenerarNomina').submit(); });
    });
    
        $('#eliminarTodoBtn').on('click', function() {
        Swal.fire({
            title: '¿Eliminar toda la nómina?', text: 'Acción irreversible.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                $('<form method="POST"><input type="hidden" name="eliminar_nomina_completa" value="1"><input type="hidden" name="tipo_nomina" value="'+tipoNomina+'"></form>').appendTo('body').submit();
            }
        });
    });

    // 🔽 NUEVO: Eliminar Todo en AJUSTE (solo filas visibles con estado Borrador)
    $('#btnEliminarTodoAjuste').on('click', function() {
        var idsBorrador = [];
        if (nominasTable) {
            $(nominasTable.rows({ search: 'applied' }).nodes()).each(function() {
                var $row = $(this);
                if ($row.find('.badge-borrador').length) {
                    var idFila = $row.data('id');
                    if (idFila) idsBorrador.push(idFila);
                }
            });
        }
        if (idsBorrador.length === 0) {
            Swal.fire({ title: 'Sin registros en borrador', text: 'No hay filas en estado Borrador para eliminar.', icon: 'info', background: '#1a1a2e', color: '#ffffff', confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' });
            return;
        }
        Swal.fire({
            title: '¿Eliminar toda la nómina en borrador?',
            html: 'Se eliminarán <strong>' + idsBorrador.length + ' registro(s)</strong> en estado Borrador. Las nóminas contabilizadas no se verán afectadas.',
            icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Eliminar', cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar', background: '#1a1a2e', color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                var $form = $('<form method="POST">');
                $form.append('<input type="hidden" name="eliminar_borrador_por_ids" value="1">');
                $form.append('<input type="hidden" name="tipo_nomina" value="' + tipoNomina + '">');
                idsBorrador.forEach(function(idFila) {
                    $form.append('<input type="hidden" name="ids[]" value="' + idFila + '">');
                });
                $form.appendTo('body').submit();
            }
        });
    });

    // 🔽 NUEVO: Revertir (descontabilizar) una nómina contabilizada
    $(document).on('click', '.btn-revertir-nomina', function() {
        var numeroNominaRevertir = $(this).data('numero');
        if (!numeroNominaRevertir) {
            Swal.fire({ title: 'Sin número de nómina', text: 'No se pudo identificar el número de la nómina a revertir.', icon: 'error', background: '#1a1a2e', color: '#ffffff', confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar' });
            return;
        }
        var nombreTipo = tipoNominaTexto || tipoNomina;
        Swal.fire({
            title: '<i class="fas fa-undo-alt me-2" style="color: #f59e0b;"></i> Revertir nómina ' + numeroNominaRevertir + '?',
            html: `
                <div class="text-center">
                    <i class="fas fa-undo-alt fa-3x mb-3" style="color: #f59e0b;"></i>
                    <p class="mb-2">La nómina <strong>${numeroNominaRevertir}</strong> (${$('<div>').text(nombreTipo).html()}) volverá a estado <strong>Borrador</strong> para poder modificarse.</p>
                    <div class="p-3 my-2 rounded text-start" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1 text-warning"></i>
                        <span>Se desharán automáticamente:</span>
                        <ul class="mb-0 mt-1" style="padding-left: 18px;">
                            <li>El cierre registrado de la nómina.</li>
                            <li>La acumulación o disfrute de <strong>vacaciones</strong> aplicada a los trabajadores.</li>
                            <li>Los movimientos del <strong>submayor de vacaciones</strong>.</li>
                            <li>El monto en <strong>montos_distrib</strong> (bonos), si aplica.</li>
                        </ul>
                    </div>
                    <p class="text-muted small mb-0">Si la nómina ya fue enviada/exportada al banco (BANDEC), contacte al especialista antes de revertir.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Sí, revertir',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            background: '#1a1a2e',
            color: 'white'
        }).then((r) => {
            if (r.isConfirmed) {
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i> Revertiendo...',
                    text: 'Procesando solicitud, por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); },
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
                var $form = $('<form method="POST">');
                $form.append('<input type="hidden" name="revertir_nomina" value="1">');
                $form.append('<input type="hidden" name="tipo_nomina" value="' + (window.tipoNomina || tipoNomina) + '">');
                $form.append('<input type="hidden" name="numero_nomina" value="' + numeroNominaRevertir + '">');
                $form.appendTo('body').submit();
            }
        });
    });

        $(document).on('click', '.eliminar-fila', function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        Swal.fire({
            title: '<i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i> Eliminar registro',
            html: `
                <div class="text-center">
                    <i class="fas fa-user-slash fa-3x mb-3" style="color: #ef4444;"></i>
                    <p>¿Está seguro que desea eliminar de la nómina A:</p>
                    <p><strong>${nombre}?</strong></p>
                    <p class="text-muted small">Esta acción no se puede deshacer.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
            background: '#1E1E1E',
            color: '#FFFFFF'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i> Eliminando...',
                    text: 'Procesando solicitud',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    background: '#1E1E1E',
                    color: '#FFFFFF'
                });
                
                $('<form method="POST"><input type="hidden" name="eliminar_nomina_individual" value="1"><input type="hidden" name="id" value="'+id+'"></form>').appendTo('body').submit();
            }
        });
    });
    
    // ==========================================
    // CONTABILIZAR NÓMINA CON MODAL OBLIGATORIO
    // ==========================================

    var modalDescripcion = null;

$('#contabilizarBtn').on('click', function() {
    // ==========================================
    // ✅ VALIDACIÓN UNIVERSAL PARA TODAS LAS NÓMINAS
    // ==========================================
    var trabajadoresCero = [];
    var nombreCampo = getNombreCampo(tipoNomina);
    var selectorCampo = getSelectorCampo(tipoNomina);
    
    if (selectorCampo) {
        var $filas = $('#tablaNominas tbody tr');
        
        $filas.each(function() {
            var $fila = $(this);
            var $campoInput = $fila.find(selectorCampo);
            // Omitir filas ya contabilizadas (sin campo editable) en vistas mixtas
            if (!$campoInput.length) return;
            var campoValor = parseNumber($campoInput.val());
            
            if (campoValor === 0 || campoValor < 0.5) {
                var nombre = $fila.find('td:eq(2)').text().trim();
                var id = $fila.data('id');
                trabajadoresCero.push({ id: id, nombre: nombre });
            }
        });
    }
    
    if (trabajadoresCero.length > 0) {
        var listaNombres = trabajadoresCero.map(function(t) {
            return '<li class="text-danger fw-bold">' + escapeHtml(t.nombre) + '</li>';
        }).join('');
        
        Swal.fire({
            title: '<i class="fas fa-times-circle text-danger me-2"></i> No se puede contabilizar',
            html: `
                <div class="text-center">
                    <i class="fas ${getIconoCampo(tipoNomina)} fa-3x mb-3" style="color: #ef4444;"></i>
                    <p class="mb-3">Hay <strong class="text-danger">${trabajadoresCero.length}</strong> trabajador(es) con <span class="text-danger fw-bold">0 ${nombreCampo}</span> en la nómina:</p>
                    <ul class="text-start" style="display: inline-block; margin: 0 auto; padding-left: 20px;">
                        ${listaNombres}
                    </ul>
                    <div class="p-3 mt-3 rounded" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25);">
                        <i class="fas fa-info-circle me-1 text-danger"></i>
                        <span class="text-danger">No se puede contabilizar una nómina con trabajadores en 0 ${nombreCampo}.</span>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-danger" id="btnEliminarTodosCero" style="border-radius: 8px; padding: 6px 18px;">
                            <i class="fas fa-user-minus me-1"></i> Eliminar todos
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btnAsignarValorManual" style="border-radius: 8px; padding: 6px 18px; margin-left: 8px;">
                            <i class="fas fa-pen me-1"></i> Asignar ${nombreCampo} manualmente
                        </button>
                    </div>
                </div>
            `,
            icon: 'error',
            showConfirmButton: false,
            showCancelButton: false,
            background: '#1a1a2e',
            color: '#ffffff',
            width: '550px',
            didOpen: function() {
                document.getElementById('btnEliminarTodosCero').addEventListener('click', function() {
                    Swal.close();
                    eliminarTodosTrabajadoresCero(trabajadoresCero);
                });
                
                document.getElementById('btnAsignarValorManual').addEventListener('click', function() {
                    Swal.close();
                    // Enfocar el primer input del campo clave
                    setTimeout(function() {
                        var $firstInput = $('#tablaNominas tbody tr ' + selectorCampo).first();
                        if ($firstInput.length) {
                            $firstInput.focus().select();
                        }
                    }, 200);
                });
            }
        });
        return;
    }
    
    // Si no hay trabajadores con valor cero, continuar con la contabilización normal
    modalDescripcion = new bootstrap.Modal(document.getElementById('modalDescripcionContabilizar'));
    modalDescripcion.show();
});

    $(document).on('input', '#observacionesCierre', function() {
        var text = $(this).val().trim();
        if (text.length > 0) {
            $('#btnConfirmarContabilizar').prop('disabled', false);
        } else {
            $('#btnConfirmarContabilizar').prop('disabled', true);
        }
    });

    $('#btnConfirmarContabilizar').on('click', function() {
        var observaciones = $('#observacionesCierre').val().trim();
        
        if (observaciones === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Descripción requerida',
                text: 'Debe escribir una descripción para la nómina antes de contabilizar.',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                background: '#1a1a2e',
                color: '#ffffff'
            });
            return;
        }
        
        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i> Contabilizando...',
            text: 'Procesando la nómina, por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1a1a2e',
            color: '#ffffff'
        });
        
        var form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="contabilizar_nomina" value="1">');
        form.append('<input type="hidden" name="tipo_nomina" value="' + tipoNomina + '">');
        form.append('<input type="hidden" name="observaciones_cierre" value="' + encodeURIComponent(observaciones) + '">');
        $('body').append(form);
        form.submit();
    });

    $('#btnCancelarContabilizar').on('click', function() {
        $('#observacionesCierre').val('');
        $('#btnConfirmarContabilizar').prop('disabled', true);
    });


// ==========================================
// VALIDACIÓN DE CAMPOS CLAVE EN CERO PARA TODAS LAS NÓMINAS
// ==========================================
function validarCamposCero(fila) {
    var tipoNominaActual = tipoNomina;
    var id = fila.data('id');
    var nombre = fila.find('td:eq(2)').text().trim();
    var campoValor = 0;
    var nombreCampo = '';
    var icono = '';
    var mensajeAyuda = '';
    var esValido = true;
    
    // Determinar qué campo validar según el tipo de nómina
    switch(tipoNominaActual) {
        case 'automatica':
            var horasInput = fila.find('.edit-horas');
            campoValor = horasInput.length ? parseNumber(horasInput.val()) : 0;
            nombreCampo = 'horas laboradas';
            icono = 'fa-clock';
            mensajeAyuda = 'Asigne las horas trabajadas para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'extraordinaria':
            // ✅ Validación especial: revisar horas normales Y horas nocturnas
            var horasNormalesInput = fila.find('.edit-horas');
            var horasNocturnasInput = fila.find('.edit-nocturnas');
            var horasNormales = horasNormalesInput.length ? parseNumber(horasNormalesInput.val()) : 0;
            var horasNocturnas = horasNocturnasInput.length ? parseNumber(horasNocturnasInput.val()) : 0;
            
            // ✅ Si AL MENOS UNO tiene valor, es válido
            if (horasNormales > 0 || horasNocturnas > 0) {
                esValido = true;
            } else {
                esValido = false;
                campoValor = 0;
                nombreCampo = 'horas laboradas (normales y nocturnas)';
                icono = 'fa-clock';
                mensajeAyuda = 'Asigne horas normales o nocturnas para este empleado.';
            }
            break;
            
        case 'vacaciones':
            var diasInput = fila.find('.edit-dias');
            campoValor = diasInput.length ? parseNumber(diasInput.val()) : 0;
            nombreCampo = 'días tomados';
            icono = 'fa-umbrella-beach';
            mensajeAyuda = 'Asigne los días de vacaciones que tomará el empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'bono':
            var bonoInput = fila.find('.edit-bono');
            campoValor = bonoInput.length ? parseNumber(bonoInput.val()) : 0;
            nombreCampo = 'monto del bono';
            icono = 'fa-gift';
            mensajeAyuda = 'Asigne el monto del bono para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        case 'ajuste':
            var ajusteInput = fila.find('.edit-bono');
            campoValor = ajusteInput.length ? parseNumber(ajusteInput.val()) : 0;
            nombreCampo = 'monto del ajuste';
            icono = 'fa-pen';
            mensajeAyuda = 'Asigne el monto del ajuste para este empleado.';
            esValido = (campoValor > 0 && campoValor >= 0.5);
            break;
            
        default:
            return true;
    }
    
    // Si es válido, retornar true
    if (esValido) {
        return true;
    }
    
    // Mostrar SweetAlert con opciones
    Swal.fire({
        title: `<i class="fas fa-exclamation-triangle text-warning me-2"></i> ${nombreCampo.charAt(0).toUpperCase() + nombreCampo.slice(1)} en Cero`,
        html: `
            <div class="text-center">
                <i class="fas ${icono} fa-3x mb-3" style="color: #f59e0b;"></i>
                <p class="mb-2">El trabajador <strong>${escapeHtml(nombre)}</strong> tiene <span class="text-danger fw-bold">0 ${nombreCampo}</span> registradas en esta nómina.</p>
                <div class="p-3 my-2 rounded" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); font-size: 0.9rem;">
                    <i class="fas fa-info-circle me-1 text-warning"></i>
                    <span>Una nómina con trabajadores en <strong>0 ${nombreCampo}</strong> <span class="text-danger">NO puede ser contabilizada</span>.</span>
                </div>
                <div class="mt-3 d-flex justify-content-center gap-3">
                    <button class="btn btn-sm btn-warning" id="btnEliminarTrabajadorCero" style="border-radius: 8px; padding: 6px 18px;">
                        <i class="fas fa-user-minus me-1"></i> Eliminar de nómina
                    </button>
                    <button class="btn btn-sm btn-secondary" id="btnAsignarValorCero" style="border-radius: 8px; padding: 6px 18px;">
                        <i class="fas fa-pen me-1"></i> Asignar ${nombreCampo}
                    </button>
                </div>
                <p class="text-muted small mt-3">Si eliminas al trabajador, su registro será removido de la nómina actual.</p>
            </div>
        `,
        icon: 'warning',
        showConfirmButton: false,
        showCancelButton: false,
        background: '#1a1a2e',
        color: '#ffffff',
        width: '500px',
        didOpen: function() {
            // Evento para eliminar trabajador
            document.getElementById('btnEliminarTrabajadorCero').addEventListener('click', function() {
                Swal.close();
                eliminarTrabajadorPorId(id, nombre);
            });
            
            // Evento para asignar valor (cierra el modal y enfoca el input)
            document.getElementById('btnAsignarValorCero').addEventListener('click', function() {
                Swal.close();
                setTimeout(function() {
                    var selector = '';
                    switch(tipoNominaActual) {
                        case 'automatica':
                            selector = '.edit-horas';
                            break;
                        case 'extraordinaria':
                            // Para extraordinaria, enfocar el primer campo vacío
                            var $horasNorm = fila.find('.edit-horas');
                            var $horasNoct = fila.find('.edit-nocturnas');
                            var hNorm = parseNumber($horasNorm.val()) || 0;
                            var hNoct = parseNumber($horasNoct.val()) || 0;
                            
                            if (hNorm === 0 && hNoct === 0) {
                                // Ambos están en cero, enfocar horas normales
                                selector = '.edit-horas';
                            } else if (hNorm === 0) {
                                selector = '.edit-horas';
                            } else if (hNoct === 0) {
                                selector = '.edit-nocturnas';
                            } else {
                                selector = '.edit-horas';
                            }
                            break;
                        case 'vacaciones':
                            selector = '.edit-dias';
                            break;
                        case 'bono':
                            selector = '.edit-bono';
                            break;
                        case 'ajuste':
                            selector = '.edit-bono';
                            break;
                    }
                    var $input = fila.find(selector);
                    if ($input.length) {
                        $input.focus().select();
                    }
                }, 200);
            });
        }
    });
    return false;
}
// ==========================================
// OBTENER NOMBRE DEL CAMPO SEGÚN TIPO DE NÓMINA
// ==========================================
function getNombreCampo(tipo) {
    switch(tipo) {
        case 'automatica': return 'horas laboradas';
        case 'extraordinaria': return 'horas laboradas';
        case 'vacaciones': return 'días tomados';
        case 'bono': return 'monto del bono';
        case 'ajuste': return 'monto del ajuste';
        default: return 'valor';
    }
}

function getSelectorCampo(tipo) {
    switch(tipo) {
        case 'automatica':
        case 'extraordinaria':
            return '.edit-horas';
        case 'vacaciones':
            return '.edit-dias';
        case 'bono':
        case 'ajuste':
            return '.edit-bono';
        default:
            return '';
    }
}

function getIconoCampo(tipo) {
    switch(tipo) {
        case 'automatica':
        case 'extraordinaria':
            return 'fa-clock';
        case 'vacaciones':
            return 'fa-umbrella-beach';
        case 'bono':
            return 'fa-gift';
        case 'ajuste':
            return 'fa-pen';
        default:
            return 'fa-file';
    }
}

function eliminarTrabajadorPorId(id, nombre) {
    if (!id) {
        Swal.fire({
            title: 'Error',
            text: 'No se pudo identificar al trabajador.',
            icon: 'error',
            background: '#1a1a2e',
            color: '#ffffff',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return;
    }
    
    Swal.fire({
        title: '<i class="fas fa-trash-alt me-2" style="color: #ef4444;"></i> ¿Eliminar trabajador?',
        html: `
            <div class="text-center">
                <i class="fas fa-user-slash fa-3x mb-3" style="color: #ef4444;"></i>
                <p>¿Está seguro que desea eliminar de la nómina a:</p>
                <p><strong>${escapeHtml(nombre)}</strong>?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-spin me-2"></i> Eliminando...',
                text: 'Procesando solicitud',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: '#1a1a2e',
                color: '#ffffff'
            });
            
            var form = $('<form method="POST"></form>');
            form.append('<input type="hidden" name="eliminar_nomina_individual" value="1">');
            form.append('<input type="hidden" name="id" value="' + id + '">');
            $('body').append(form);
            form.submit();
        }
    });
}

// ==========================================
// VALIDACIÓN UNIVERSAL AL CONTABILIZAR
// ==========================================
function validarTrabajadoresCeroAntesContabilizar() {
    var trabajadoresCero = [];
    var nombreCampo = '';
    var selectorCampo = '';
    var icono = '';
    var esExtraordinaria = (tipoNomina === 'extraordinaria');
    
    // Configurar según tipo de nómina
    switch(tipoNomina) {
        case 'automatica':
            nombreCampo = 'horas laboradas';
            selectorCampo = '.edit-horas';
            icono = 'fa-clock';
            break;
        case 'extraordinaria':
            nombreCampo = 'horas laboradas (normales y nocturnas)';
            icono = 'fa-clock';
            // Para extraordinaria, validamos ambos campos
            break;
        case 'vacaciones':
            nombreCampo = 'días tomados';
            selectorCampo = '.edit-dias';
            icono = 'fa-umbrella-beach';
            break;
        case 'bono':
            nombreCampo = 'monto del bono';
            selectorCampo = '.edit-bono';
            icono = 'fa-gift';
            break;
        case 'ajuste':
            nombreCampo = 'monto del ajuste';
            selectorCampo = '.edit-bono';
            icono = 'fa-pen';
            break;
        default:
            return true;
    }
    
    var $filas = $('#tablaNominas tbody tr');
    
    $filas.each(function() {
        var $fila = $(this);
        var nombre = $fila.find('td:eq(2)').text().trim();
        var id = $fila.data('id');
        var esCero = false;
        
        if (esExtraordinaria) {
            // ✅ Para extraordinaria: validar que AMBOS campos estén en cero
            var $horasNorm = $fila.find('.edit-horas');
            var $horasNoct = $fila.find('.edit-nocturnas');
            var horasNorm = $horasNorm.length ? parseNumber($horasNorm.val()) : 0;
            var horasNoct = $horasNoct.length ? parseNumber($horasNoct.val()) : 0;
            
            if (horasNorm === 0 && horasNoct === 0) {
                esCero = true;
            }
        } else {
            // Para los demás tipos: validar el campo específico
            var $campoInput = $fila.find(selectorCampo);
            var campoValor = $campoInput.length ? parseNumber($campoInput.val()) : 0;
            
            if (campoValor === 0 || campoValor < 0.5) {
                esCero = true;
            }
        }
        
        if (esCero) {
            trabajadoresCero.push({ id: id, nombre: nombre });
        }
    });
    
    return trabajadoresCero;
}

    // ==========================================
    // MODALES DE SELECCIÓN (Extraordinaria, Vacaciones, Bonos)
    // ==========================================
    
function renderExtraWorkerList(term = '') {
    var idsAuto = <?php 
        $ids = [];
        if ($existe_nomina && $tipo_nomina_activa != 'automatica') {
            $st = $pdo->prepare("SELECT DISTINCT trabajador_id FROM nominas WHERE periodo_desde=? AND periodo_hasta=? AND tipo_nomina='automatica'");
            $st->execute([$periodo_desde, $periodo_hasta]);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        echo json_encode($ids); 
    ?>;
    var areaVal = $('#filterExtraArea').val();
    var ccVal = $('#filterExtraCC').val();
    var html = '';
    
    var filtered = trabajadores.filter(w => {
        var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
        var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
        var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
        return matchesSearch && matchesArea && matchesCC;
    });
    
    filtered.forEach(w => {
        var sel = selectedExtra.some(s => s.id == w.id);
        
        // Evaluar si el trabajador ya está en la nómina extraordinaria actual
        var yaEnNomina = idsEnNominaActual.map(Number).includes(Number(w.id));
        
        // Definir estilos y clases visuales condicionales
        var itemStyle = yaEnNomina ? 'border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.08);' : 'border-left: 3px solid #10b981;';
        var checkboxAttr = yaEnNomina ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : (sel ? 'checked' : '');
        var avatarBg = yaEnNomina ? 'background: rgba(245, 158, 11, 0.15);' : 'background: rgba(16, 185, 129, 0.15);';
        var avatarIconColor = yaEnNomina ? 'color: #f59e0b;' : 'color: #10b981;';

        // Resolución del path de la foto
        var workerFotoUrl = '';
        if (w.foto_ruta && w.foto_ruta.trim() !== '') {
            workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
        }

        var avatarHtml = '';
        if (workerFotoUrl) {
            avatarHtml = '<img src="' + workerFotoUrl + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E\';">';
        } else {
            avatarHtml = '<i class="fas fa-user" style="' + avatarIconColor + '"></i>';
        }

        var centroCostoNombre = 'Sin CC';
        if (w.centro_costo_codigo && w.centro_costo_nombre) {
            centroCostoNombre = w.centro_costo_codigo + ' - ' + w.centro_costo_nombre;
        } else if (w.centro_costo_nombre) {
            centroCostoNombre = w.centro_costo_nombre;
        }
        
        html += `<div class="worker-item ${sel?'selected':''}" 
                    data-id="${w.id}" 
                    data-nombre="${w.nombre_completo}" 
                    data-codigo="${w.codigo}" 
                    data-sh="${w.salario_hora_ordinaria}"
                    data-salario-mensual="${w.salario_mensual}"
                    data-area="${w.nombre_area || 'Sin área'}"
                    data-centro-costo="${centroCostoNombre}"
                    data-foto-ruta="${w.foto_ruta || ''}"
                    style="${itemStyle}">
            <input type="checkbox" class="worker-checkbox" ${checkboxAttr}>
            <div class="worker-avatar" style="${avatarBg}">
                ${avatarHtml}
            </div>
            <div class="worker-info">
                <div class="worker-name">${w.codigo} - ${w.nombre_completo}</div>
                <div class="worker-detail">$${parseFloat(w.salario_hora_ordinaria).toFixed(2)}/h</div>
                <div class="worker-detail small text-muted"><i class="fas fa-building"></i> ${w.nombre_area || 'Sin área'} | <i class="fas fa-chart-pie"></i> ${centroCostoNombre}</div>
            </div>
            ${yaEnNomina ? `
                <div class="worker-dias text-end">
                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; font-size: 0.7rem;">
                        <i class="fas fa-exclamation-circle me-1"></i> Ya en nómina
                    </span>
                </div>
            ` : ''}
        </div>`;
    });
    $('#extraWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
}

function updateExtraList(focusId) {
    var diasLaborables = <?php echo $dias_laborables; ?>;
    var html = selectedExtra.map(w => {
        var salarioDiario = w.salario_mensual / diasLaborables;
        var valorHoraNocturna = w.sh * recargoNocturno; // Modificado: Usa variable global
        var porcentajeTexto = Math.round(recargoNocturno * 100) + '%';
        var recargoPorcentajeTexto = '+' + Math.round((recargoNocturno - 1) * 100) + '%';
        
        return `
            <div class="selected-worker-card" style="margin-bottom: 15px; border-left: 3px solid #3b82f6;">
                <div class="selected-worker-header">
                    <div>
                        <span class="worker-name">${w.codigo} - ${w.nombre}</span>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-building me-1"></i> ${w.area} &nbsp;|&nbsp;
                            <i class="fas fa-chart-pie me-1"></i> ${w.centro_costo}
                        </div>
                    </div>
                    <button type="button" class="remove-worker" data-id="${w.id}" style="background:none; border:none; color:#ef4444;">✖</button>
                </div>
                
                <div class="row g-2 mb-3 p-2" style="background: rgba(0,0,0,0.3); border-radius: 10px;">
                    <div class="col-4 text-center">
                        <small class="text-muted"><i class="fas fa-calendar-day"></i> Salario por DÍA</small>
                        <div class="fw-bold text-info">$${salarioDiario.toFixed(2)}</div>
                        <small class="text-muted">(Mensual ÷ ${diasLaborables} días)</small>
                    </div>
                    <div class="col-4 text-center">
                        <small class="text-muted"><i class="fas fa-clock"></i> Salario por HORA</small>
                        <div class="fw-bold text-info">$${w.sh.toFixed(2)}</div>
                        <small class="text-muted">(Base 100%)</small>
                    </div>
                    <div class="col-4 text-center">
                        <small class="text-muted"><i class="fas fa-moon"></i> Hora Nocturna</small>
                        <div class="fw-bold text-success">$${valorHoraNocturna.toFixed(2)}</div>
                        <small class="text-muted">(Base ${recargoPorcentajeTexto} Ley 116)</small>
                    </div>
                </div>
                
                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <label class="small text-info"><i class="fas fa-sun me-1"></i>Horas Extras Normales</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm horas-input" 
                                   data-id="${w.id}" value="${w.horasExtraNormales || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#3b82f6;">
                            <span class="input-group-text bg-dark text-info">${porcentajeTexto}</span>
                        </div>
                        <small class="text-muted">$${w.sh.toFixed(2)}/h → $${(w.sh * recargoNocturno).toFixed(2)}</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="small text-nocturno"><i class="fas fa-moon me-1"></i>Horas Extras Nocturnas</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm horas-nocturnas-input" 
                                   data-id="${w.id}" value="${w.horasExtraNocturnas || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#8b5cf6;">
                            <span class="input-group-text bg-dark text-nocturno">${porcentajeTexto}</span>
                        </div>
                        <small class="text-muted">$${w.sh.toFixed(2)}/h → $${(w.sh * recargoNocturno).toFixed(2)}</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="small text-primary"><i class="fas fa-bed me-1"></i>Nocturnidad<br>(07:00pm - 07:00am)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" class="form-control form-control-sm nocturnidad-input" 
                                   data-id="${w.id}" value="${w.nocturnidad || ''}" 
                                   style="background:rgba(20,20,30,0.9); border-color:#10b981;">
                        </div>
                        <small class="text-muted">$${w.sh.toFixed(2)}/h → $${(w.sh * recargoNocturno).toFixed(2)}</small>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <small class="text-muted">
                            <i class="fas fa-calculator me-1"></i>
                            Cálculo: (HE Normales × $${w.sh.toFixed(2)} × ${recargoNocturno}) + (HE Nocturnas × $${w.sh.toFixed(2)} × ${recargoNocturno}) + (Nocturnidad × $${w.sh.toFixed(2)} × ${recargoNocturno})
                        </small>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#selectedExtraList').html(html || '<em class="text-white-50"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</em>');
    
    if (focusId) {
        setTimeout(function() {
            var $input = $('#selectedExtraList').find(`.horas-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }

    updateExtraTotals();
}

function updateExtraTotals() {
    var tHorasNorm = 0, tHorasNoct = 0, tNocturnidad = 0, tDev = 0, tNeto = 0, val = 0;
    selectedExtra.forEach(w => {
        var hrsNorm = parseFloat(w.horasExtraNormales) || 0;
        var hrsNoct = parseFloat(w.horasExtraNocturnas) || 0;
        var noct = parseFloat(w.nocturnidad) || 0;
        
        if (hrsNorm > 0 || hrsNoct > 0 || noct > 0) { 
            tHorasNorm += hrsNorm;
            tHorasNoct += hrsNoct;
            tNocturnidad += noct;
            
            var valorHoraExtra = w.sh * recargoNocturno;
            var salTotal = (hrsNorm + hrsNoct + noct) * valorHoraExtra;
            tDev += salTotal;
            
            var contribucion = salTotal * 0.05;
            var impuesto = calcularImpuestoProgresivo(salTotal);
            tNeto += salTotal - (contribucion + impuesto);
            val++;
        }
    });
    
    if (val > 0) {
        $('#previewExtraCount').text(val);
        $('#previewExtraTotalHoras').text(tHorasNorm);
        $('#previewExtraTotalDevengado').text('$' + tDev.toFixed(2));
        $('#previewExtraTotalNeto').text('$' + tNeto.toFixed(2));
        $('#previewExtra').show();
        $('#btnGenerarExtraordinaria').prop('disabled', false);
    } else { 
        $('#previewExtra').hide();
        $('#btnGenerarExtraordinaria').prop('disabled', true);
    }
}

    $('#searchExtraWorker').on('input', function(){ renderExtraWorkerList($(this).val()); });

$(document).on('click', '#extraWorkerList .worker-item', function(){
    var id = $(this).attr('data-id');
    var nombre = $(this).attr('data-nombre');
    var fotoRuta = $(this).attr('data-foto-ruta') || '';

    // Verificar si el trabajador ya se encuentra registrado en la nómina activa actual
    if (idsEnNominaActual.map(Number).includes(Number(id))) {
		// Construir la URL de la foto apuntando a la ruta específica de trabajadores
				var fotoUrl = '';
				if (fotoRuta && fotoRuta.trim() !== '') {
					// Si la ruta ya incluye la estructura de carpetas completa
					if (fotoRuta.indexOf('assets/imagenes/trabajadores/') !== -1) {
						fotoUrl = '../' + fotoRuta;
					} else {
						// Si la base de datos almacena solo el nombre del archivo (ej. "trabajador1.png")
						fotoUrl = '../assets/imagenes/trabajadores/' + fotoRuta.split('/').pop();
					}
				} else {
					// Fallback: Silueta SVG en color amarillo/naranja
					fotoUrl = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23f59e0b%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';
				}

        Swal.fire({
            title: 'Atención',
            html: 'Este trabajador (<span class="fw-bold text-danger">' + nombre + '</span>) ya aparece en la nómina extraordinaria, si desea hacer algún cambio, deberá editarlo directo en la tabla.',
            imageUrl: fotoUrl,
            imageWidth: 90,
            imageHeight: 90,
            imageAlt: 'Foto de ' + nombre,
            customClass: {
                image: 'rounded-circle border border-warning'
            },
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return; // Detiene la ejecución y no permite seleccionarlo
    }

    var idx = selectedExtra.findIndex(s => s.id == id);
    var wasAdded = false;
    
    if(idx >= 0) {
        selectedExtra.splice(idx, 1);
    } else {
        selectedExtra.push({
            id: id,
            nombre: nombre,
            codigo: $(this).attr('data-codigo'),
            sh: parseFloat($(this).attr('data-sh')),
            salario_mensual: parseFloat($(this).attr('data-salario-mensual')),
            area: $(this).attr('data-area'),
            centro_costo: $(this).attr('data-centro-costo'),
            horasExtraNormales: '',
            horasExtraNocturnas: '',
            nocturnidad: ''
        });
        wasAdded = true;
    }
    renderExtraWorkerList($('#searchExtraWorker').val());
    updateExtraList(wasAdded ? id : null); // Pasa el ID del trabajador para enfocar el input si fue agregado
});

$(document).on('input', '.horas-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.horasExtraNormales = $(this).val(); 
        updateExtraTotals(); // Solo actualiza el cuadro de totales inferior
    }
});

$(document).on('input', '.horas-nocturnas-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.horasExtraNocturnas = $(this).val(); 
        updateExtraTotals(); // Solo actualiza el cuadro de totales inferior
    }
});

$(document).on('input', '.nocturnidad-input', function(){
    var id = $(this).data('id');
    var w = selectedExtra.find(s => s.id == id);
    if(w) { 
        w.nocturnidad = $(this).val(); 
        updateExtraTotals(); // Solo actualiza el cuadro de totales inferior
    }
});

     
    $(document).on('click', '#selectedExtraList .remove-worker', function(){
        selectedExtra.splice(selectedExtra.findIndex(s=>s.id==$(this).data('id')), 1); renderExtraWorkerList($('#searchExtraWorker').val()); updateExtraList();
    });

$('#modalExtraordinaria').on('show.bs.modal', function(){ 
    selectedExtra=[]; 
    renderExtraWorkerList(); 
    updateExtraList(); 
    $('#searchExtraWorker').val('');
    
    // CORRECCIÓN: Prioriza la selección del modal anterior
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoExtra').val(td);
    
    // Limpiamos la variable para futuras aperturas manuales
    window.tempSelectedDiscount = null;
});

    $('#formExtraordinaria').on('submit', function(e){
        $(this).find('input[name="trabajador_id[]"], input[name="horas_trabajadas[]"], input[name="horas_nocturnas_trabajadas[]"], input[name="nocturnidad_trabajadas[]"]').remove();
        var valid = false;
        
        selectedExtra.forEach(w => { 
            var hNorm = parseFloat(w.horasExtraNormales) || 0;
            var hNoct = parseFloat(w.horasExtraNocturnas) || 0;
            var noct = parseFloat(w.nocturnidad) || 0;
            
            if(hNorm > 0 || hNoct > 0 || noct > 0){ 
                $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}">`);
                $(this).append(`<input type="hidden" name="horas_trabajadas[]" value="${hNorm}">`);
                $(this).append(`<input type="hidden" name="horas_nocturnas_trabajadas[]" value="${hNoct}">`);
                $(this).append(`<input type="hidden" name="nocturnidad_trabajadas[]" value="${noct}">`);
                valid = true; 
            } 
        });
        
        if(!valid){ 
            e.preventDefault(); 
            Swal.fire({
                title: 'Error',
                text: 'Asigne horas a los trabajadores',
                icon: 'error',
                background: '#1a1a2e',
                color: 'white',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            }); 
            return false; 
        }
        $(this).append('<input type="hidden" name="confirmar_extraordinaria" value="1">'); 
        return true;
    });

    // Modal Vacaciones
    var rangoActivo = 'todos';

    function renderWorkerList(term = '') {
        var areaVal = $('#filterVacArea').val();
        var ccVal = $('#filterVacCC').val();
        var html = '';
        
        var filtered = trabajadores.filter(w => {
            var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
            var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
            var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
            return matchesSearch && matchesArea && matchesCC;
        });
        
        if (rangoActivo !== 'todos') {
            filtered = filtered.filter(w => {
                var diasAcum = parseFloat(w.dias_acumulados) || 0;
                switch(rangoActivo) {
                    case '1-5': return diasAcum >= 1 && diasAcum <= 5;
                    case '5-10': return diasAcum > 5 && diasAcum <= 10;
                    case '10-15': return diasAcum > 10 && diasAcum <= 15;
                    case '15-20': return diasAcum > 15 && diasAcum <= 20;
                    case '20+': return diasAcum > 20;
                    default: return true;
                }
            });
        }
        
        filtered.forEach(w => {
            var isSelected = selectedWorkers.some(s => s.id == w.id);
            var yaTieneNomina = idsConNomina.includes(w.id);
            var diasAcum = parseFloat(w.dias_acumulados) || 0;
            var disabled = diasAcum <= 0 || yaTieneNomina;
            var disabledText = diasAcum <= 0 ? 'Sin días disponibles' : (yaTieneNomina ? 'Ya tiene nómina de vacaciones en este período' : '');
            
            var badgeColor = '#10b981';
            var badgeBg = 'rgba(16, 185, 129, 0.15)';
            var icono = '<i class="fas fa-check-circle me-1"></i>';
            var mensajeAlerta = '';
            
            if (diasAcum >= 21 && diasAcum <= 24) {
                badgeColor = '#ef4444';
                badgeBg = 'rgba(239, 68, 68, 0.25)';
                icono = '<i class="fas fa-exclamation-triangle me-1"></i>';
                mensajeAlerta = '<span class="badge" style="background:#ef4444; color:white; font-size:0.6rem; margin-left:8px;">¡EXCESO DE DÍAS!</span>';
            } else if (diasAcum > 20 && diasAcum < 21) {
                badgeColor = '#f97316';
                badgeBg = 'rgba(249, 115, 22, 0.2)';
                icono = '<i class="fas fa-clock me-1"></i>';
                mensajeAlerta = '<span class="badge" style="background:#f97316; color:white; font-size:0.6rem; margin-left:8px;">PRÓXIMO A EXCEDER</span>';
            } else if (diasAcum > 15 && diasAcum <= 20) {
                badgeColor = '#f59e0b';
                badgeBg = 'rgba(245, 158, 11, 0.2)';
                icono = '<i class="fas fa-chart-line me-1"></i>';
            } else if (diasAcum > 10 && diasAcum <= 15) {
                badgeColor = '#eab308';
                badgeBg = 'rgba(234, 179, 8, 0.15)';
                icono = '<i class="fas fa-sun me-1"></i>';
            } else if (diasAcum > 5 && diasAcum <= 10) {
                badgeColor = '#3b82f6';
                badgeBg = 'rgba(59, 130, 246, 0.15)';
                icono = '<i class="fas fa-cloud-sun me-1"></i>';
            } else if (diasAcum >= 1 && diasAcum <= 5) {
                badgeColor = '#10b981';
                badgeBg = 'rgba(16, 185, 129, 0.1)';
                icono = '<i class="fas fa-seedling me-1"></i>';
            } else if (diasAcum <= 0) {
                badgeColor = '#6b7280';
                badgeBg = 'rgba(107, 114, 128, 0.15)';
                icono = '<i class="fas fa-ban me-1"></i>';
            }
            
            var porcentaje = Math.min(Math.roundExcel((diasAcum / 24) * 100), 100);
            var barraColor = porcentaje >= 85 ? '#ef4444' : (porcentaje >= 70 ? '#f59e0b' : '#10b981');

            // Resolución del path de la foto
            var workerFotoUrl = '';
            if (w.foto_ruta && w.foto_ruta.trim() !== '') {
                workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
            }

            var avatarHtml = '';
            if (workerFotoUrl) {
                avatarHtml = `<img src="${workerFotoUrl}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2310b981%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
            } else {
                avatarHtml = `<i class="fas fa-user" style="color: ${badgeColor};"></i>`;
            }
            
            html += `<div class="worker-item ${disabled ? 'disabled' : ''} ${isSelected ? 'selected' : ''}" 
                        data-id="${w.id}" 
                        data-dias="${diasAcum}" 
                        data-salario="${w.salario_mensual}" 
                        data-nombre="${w.nombre_completo}" 
                        data-codigo="${w.codigo}"
                        data-foto-ruta="${w.foto_ruta || ''}"
                        style="${disabled ? 'opacity:0.6;' : ''}">
                        <input type="checkbox" class="worker-checkbox" ${isSelected ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                        <div class="worker-avatar" style="background: ${badgeBg};">
                            ${avatarHtml}
                        </div>
                        <div class="worker-info">
                            <div class="worker-name">
                                ${w.codigo} - ${w.nombre_completo}
                                ${mensajeAlerta}
                            </div>
                            <div class="worker-detail">
                                <i class="fas fa-briefcase me-1"></i> ${w.cargo || 'Sin cargo'} 
                                <i class="fas fa-building ms-2 me-1"></i> ${w.nombre_area || 'Sin área'}
                            </div>
                            <div class="mt-1" style="width: 100%;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.65rem; margin-bottom: 2px;">
                                    <span><i class="fas fa-calendar-alt me-1"></i> Días acumulados:</span>
                                    <span><strong style="color: ${badgeColor};">${diasAcum.toFixed(2)} / 24</strong></span>
                                </div>
                                <div style="width: 100%; background: rgba(255,255,255,0.1); border-radius: 10px; height: 4px;">
                                    <div style="width: ${porcentaje}%; background: ${barraColor}; border-radius: 10px; height: 4px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="worker-dias">
                            <div class="dias-num" style="color: ${badgeColor}; font-weight: bold; font-size: 1rem;">
                                ${icono} ${diasAcum.toFixed(2)} días
                            </div>
                            <div class="worker-detail">
                                <i class="fas fa-dollar-sign me-1"></i> ${parseFloat(w.valor_acumulado).toLocaleString('en-US', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                        ${disabledText ? `<div class="worker-detail text-danger ms-2"><i class="fas fa-info-circle me-1"></i>${disabledText}</div>` : ''}
                    </div>`;
        });
        $('#workerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash fa-2x mb-2 d-block"></i>No hay resultados</div>');
    }

    $('.rango-btn').on('click', function() {
        $('.rango-btn').removeClass('active');
        $(this).addClass('active');
        rangoActivo = $(this).data('rango');
        renderWorkerList($('#searchWorker').val());
    });

function updateSelectedList(focusId) {
    var html = '';
    var tDev = 0, tDias = 0, val = 0;
    
    selectedWorkers.forEach(w => {
        var vpd = w.salario / diasLaborables;
        var dias = parseNumber(w.dias_a_pagar) || 0;
        var importe = dias * vpd;
        var res = w.dias_acumulados - dias;
        
        tDev += importe;
        tDias += dias;
        if(dias > 0 && dias <= w.dias_acumulados) val++;
        
        html += `<div class="selected-worker-card" style="background: rgba(96, 165, 250, 0.08); border-radius: 12px; padding: 12px; margin-bottom: 10px; border: 1px solid rgba(96, 165, 250, 0.25);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-weight: 600; color: #60a5fa;"><i class="fas fa-user-circle me-1"></i> ${w.codigo} - ${w.nombre}</span>
                        <button type="button" class="remove-worker" data-id="${w.id}" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem;">&times;</button>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #ccc; font-size: 0.8rem;"><i class="fas fa-calendar-alt"></i> Días:</span>
                            <input type="number" class="dias-input" data-id="${w.id}" 
                                   value="${dias}" max="${w.dias_acumulados}" step="0.5" 
                                   style="width: 120px; background: #1e1e2e; border: 1px solid #3b82f6; border-radius: 8px; padding: 6px 12px; color: white; text-align: center;">
                            <span style="color: #aaa; font-size: 0.7rem;">máx: ${w.dias_acumulados.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #ccc; font-size: 0.8rem;"><i class="fas fa-dollar-sign"></i> Importe:</span>
                            <span class="importe-preview" style="font-weight: bold; color: #4ade80; background: rgba(16,185,129,0.15); padding: 4px 12px; border-radius: 8px; min-width: 90px; display: inline-block; text-align: center;">
                                $${importe.toFixed(2)}
                            </span>
                        </div>
<div style="font-size: 0.7rem; color: #888;">
    <i class="fas fa-chart-line"></i> Restantes: <span class="dias-restantes-preview" style="color: #60a5fa;">${res.toFixed(2)}</span> | Importe: <span class="importe-total-preview" style="color: #10b981; font-weight: bold;">$${importe.toFixed(2)}</span> (<span style="color: #aaa;">$${vpd.toFixed(2)}/día</span>)
</div>
                    </div>
                </div>`;
    });
    
    $('#selectedWorkersList').html(html || '<div style="color: #aaa; text-align: center; padding: 20px;"><i class="fas fa-users me-1"></i>Seleccione trabajadores de la lista izquierda</div>');
    
    // Verificación defensiva antes de aplicar el foco
    if (typeof focusId !== 'undefined' && focusId) {
        setTimeout(function() {
            var $input = $('#selectedWorkersList').find(`.dias-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }

    if(val > 0){
        $('#previewCount').text(val);
        $('#previewTotalDias').text(tDias.toFixed(2));
        $('#previewTotalDevengado').text('$' + tDev.toFixed(2));
        $('#previewMultiple').show();
        $('#btnAgregarVacaciones').prop('disabled', false);
    } else {
        $('#previewMultiple').hide();
        $('#btnAgregarVacaciones').prop('disabled', true);
    }
}


$(document).on('input', '.dias-input', function() {
    var $input = $(this);
    var id = parseInt($input.attr('data-id')); // Se corrige .data() por .attr() para leer elementos dinámicos
    var dias = parseFloat($input.val()) || 0;
        
        var worker = selectedWorkers.find(w => w.id == id);
        if (!worker) return;
        
        if (dias > worker.dias_acumulados) {
            dias = worker.dias_acumulados;
            $input.val(dias);
        }
        if (dias < 0) dias = 0;
        
		worker.dias_a_pagar = dias;
		
		var valorPorDia = worker.salario / diasLaborables;
		var importe = dias * valorPorDia;
		
    var $card = $input.closest('.selected-worker-card');
    $card.find('.importe-preview').text('$' + importe.toFixed(2));
    
    // Calcular y actualizar visualmente los días restantes en tiempo real
    var restantes = worker.dias_acumulados - dias;
    if (restantes < 0) restantes = 0;
    $card.find('.dias-restantes-preview').text(restantes.toFixed(2));
    
    // Actualizar visualmente el importe total calculado en el texto inferior
    $card.find('.importe-total-preview').text('$' + importe.toFixed(2));
		
		var totalDevengado = 0;
        var totalDias = 0;
        var trabajadoresValidos = 0;
        
        selectedWorkers.forEach(w => {
            var d = parseFloat(w.dias_a_pagar) || 0;
            if (d > 0 && d <= w.dias_acumulados) {
                var vpd = w.salario / diasLaborables;
                totalDevengado += d * vpd;
                totalDias += d;
                trabajadoresValidos++;
            }
        });
        
        $('#previewCount').text(trabajadoresValidos);
        $('#previewTotalDias').text(totalDias.toFixed(2));
        $('#previewTotalDevengado').text('$' + totalDevengado.toFixed(2));
        
        if (trabajadoresValidos > 0) {
            $('#previewMultiple').show();
            $('#btnAgregarVacaciones').prop('disabled', false);
        } else {
            $('#previewMultiple').hide();
            $('#btnAgregarVacaciones').prop('disabled', true);
        }
    });
    
    $('#searchWorker').on('input', function(){ renderWorkerList($(this).val()); });

$(document).on('click', '#workerList .worker-item:not(.disabled)', function(){
    var id = $(this).attr('data-id'), idx = selectedWorkers.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedWorkers.splice(idx,1); 
    } else {
        selectedWorkers.push({
            id: id, 
            nombre: $(this).attr('data-nombre'), 
            codigo: $(this).attr('data-codigo'), 
            salario: parseFloat($(this).attr('data-salario')), 
            dias_acumulados: parseFloat($(this).attr('data-dias')), 
            dias_a_pagar: '' // Valor inicial para el input de días
        });
    }
    renderWorkerList($('#searchWorker').val()); 
    updateSelectedList(id); // Pasa el ID para enfocar el input de días
});

    $(document).on('click', '#selectedWorkersList .remove-worker', function(){
        selectedWorkers.splice(selectedWorkers.findIndex(s=>s.id==$(this).data('id')), 1); renderWorkerList($('#searchWorker').val()); updateSelectedList();
    });

$('#modalVacaciones').on('show.bs.modal', function(){ 
    selectedWorkers=[]; 
    renderWorkerList(); 
    updateSelectedList(); 
    $('#searchWorker').val(''); 
    
    // CORRECCIÓN: Prioriza la selección del modal anterior
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    
    // CORRECCIÓN de ID: Su HTML tiene "tipoDescuentoVac" pero el JS buscaba "tipoDiscountVac"
    $('#tipoDescuentoVac').val(td); 
    
    window.tempSelectedDiscount = null;
});

    $('#formVacaciones').on('submit', function(e){
        $(this).find('input[name="trabajador_id[]"], input[name="dias_vacaciones[]"]').remove();
        var valid = false;
        selectedWorkers.forEach(w=>{ var d=parseNumber(w.dias_a_pagar); if(d>0 && d<=w.dias_acumulados){ $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="dias_vacaciones[]" value="${d}">`); valid=true; } });
        if(!valid){ e.preventDefault(); Swal.fire({title:'Error',text:'Asigne días válidos',icon:'error', background:'#1a1a2e', color:'white', confirmButtonText:'<i class="fas fa-check me-2"></i>Entendido'}); return false; }
        return true;
    });

    // Modal Bonos
    function renderBonoWorkerList(term = '') {
        var areaVal = $('#filterBonoArea').val();
        var ccVal = $('#filterBonoCC').val();
        var html = '';
        
        var filtered = trabajadores.filter(w => {
            var matchesSearch = !term || w.nombre_completo.toLowerCase().includes(term.toLowerCase()) || w.codigo.toLowerCase().includes(term.toLowerCase());
            var matchesArea = !areaVal || parseInt(w.area_id) === parseInt(areaVal);
            var matchesCC = !ccVal || parseInt(w.centro_costo_id) === parseInt(ccVal);
            return matchesSearch && matchesArea && matchesCC;
        });
        
        filtered.forEach(w => {
            var sel = selectedBonos.some(s => s.id == w.id);

            // Resolución del path de la foto
            var workerFotoUrl = '';
            if (w.foto_ruta && w.foto_ruta.trim() !== '') {
                workerFotoUrl = (w.foto_ruta.indexOf('assets/') === 0) ? '../' + w.foto_ruta : '../assets/imagenes/trabajadores/' + w.foto_ruta;
            }

            var avatarHtml = '';
            if (workerFotoUrl) {
                avatarHtml = `<img src="${workerFotoUrl}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2360a5fa%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">`;
            } else {
                avatarHtml = `<i class="fas fa-user"></i>`;
            }

            html += `<div class="worker-item ${sel?'selected':''}" data-id="${w.id}" data-nombre="${w.nombre_completo}" data-codigo="${w.codigo}" data-salario="${w.salario_mensual}" data-foto-ruta="${w.foto_ruta || ''}">
                <input type="checkbox" class="worker-checkbox" ${sel?'checked':''}>
                <div class="worker-avatar">${avatarHtml}</div>
                <div class="worker-info">
                    <div class="worker-name">${w.codigo} - ${w.nombre_completo}</div>
                    <div class="worker-detail"><i class="fas fa-wallet me-1"></i>Salario Básico: $${parseFloat(w.salario_mensual).toFixed(2)}</div>
                </div>
            </div>`;
        });
        $('#bonoWorkerList').html(html || '<div class="p-3 text-white-50 text-center"><i class="fas fa-user-slash me-1"></i>No hay resultados</div>');
    }

// MODIFICADO: Cálculo de totales y presupuesto restante
function updateBonosPreviewTotals() {
    var tDev = 0, val = 0;
    
    // Sumar el devengado de cada trabajador seleccionado
    selectedBonos.forEach(w => { 
        var m = parseFloat(w.monto) || 0; 
        if(m > 0){ tDev += m; val++; } 
    });
    
    // Obtener el monto inicial del input
    var montoInicial = parseFloat($('#montoInicialBono').val()) || 0;
    var montoRestante = montoInicial - tDev;
    
    // Formatear salidas en el resumen
    $('#previewFondoInicial').text('$' + montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#previewTotalMonto').text('$' + tDev.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#previewMontoRestante').text('$' + montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    
    // Alerta visual de presupuesto excedido
    if (montoRestante < 0) {
        $('#previewMontoRestante')
            .removeClass('info-value-success')
            .addClass('text-danger fw-bold')
            .css('text-shadow', '0 0 8px rgba(239, 68, 68, 0.4)');
    } else {
        $('#previewMontoRestante')
            .removeClass('text-danger fw-bold')
            .addClass('info-value-success')
            .css('text-shadow', 'none');
    }
    
    // Mostrar u ocultar la tarjeta de resumen
    if(val > 0 || montoInicial > 0){
        $('#previewBonoCount').text(val); 
        $('#previewBonos').show(); 
        $('#btnGenerarBonos').prop('disabled', false);
    } else { 
        $('#previewBonos').hide(); 
        $('#btnGenerarBonos').prop('disabled', true); 
    }
}

// NUEVO: Escuchar cambios en el input del monto inicial en tiempo real
$(document).on('input change', '#montoInicialBono', function() {
    updateBonosPreviewTotals();
});

// Variable para evitar que la alerta se muestre de forma repetitiva innecesariamente
var fondoBonoAgotadoAlertado = false;

// Función para verificar la disponibilidad del fondo y alertar si se agota
function verificarFondoBonoAgotado() {
    var montoInicial = parseFloat($('#montoInicialBono').val()) || 0;
    if (montoInicial <= 0) {
        fondoBonoAgotadoAlertado = false;
        return;
    }

    var totalRepartido = 0;
    selectedBonos.forEach(w => { 
        totalRepartido += parseFloat(w.monto) || 0; 
    });

    var montoRestante = montoInicial - totalRepartido;

    // Si el monto restante es menor o igual a cero, se activa la alerta
    if (montoRestante <= 0) {
        if (!fondoBonoAgotadoAlertado) {
            fondoBonoAgotadoAlertado = true; // Se marca como alertado
            
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Fondo Agotado',
                html: `
                    <div class="text-center">
                        <p class="mb-3">El monto total asignado ha consumido o superado el fondo disponible.</p>
                        <div class="p-3 my-2 rounded" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); font-size: 0.9rem; line-height: 1.5;">
                            <strong>Fondo Inicial:</strong> $${montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                            <strong>Total Repartido:</strong> <span class="text-danger" style="font-weight: bold;">$${totalRepartido.toLocaleString('en-US', {minimumFractionDigits: 2})}</span><br>
                            <strong>Diferencia:</strong> <span class="text-danger" style="font-weight: bold;">$${montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                        </div>
                        <p class="small text-muted mt-3">Por favor, redistribuya los coeficientes o montos asignados para ajustarse al presupuesto establecido.</p>
                    </div>
                `,
                icon: 'warning',
                background: '#1a1a2e',
                color: '#ffffff',
                confirmButtonColor: '#f59e0b',
                confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Redistribuir'
            });
        }
    } else {
        // Si el usuario reduce los montos y vuelve a haber saldo a favor, se restablece el estado de alerta
        fondoBonoAgotadoAlertado = false;
    }
}
    
function updateBonosList(focusId) {
    var html = selectedBonos.map(w => {
        if (!w.tipo) w.tipo = 'porciento'; 
        var isFijo = w.tipo === 'fijo';
        var pctVal = w.porciento !== undefined ? w.porciento : '';
        var coefVal = w.coeficiente !== undefined ? w.coeficiente : ''; // LÍNEA AGREGADA
        var montoVal = w.monto !== undefined ? w.monto : '';
        
        return `
            <div class="selected-worker-card" data-id="${w.id}">
                <div class="selected-worker-header">
                    <span class="worker-name">${w.codigo} - ${w.nombre} - Sal. Básico: $${w.salario.toFixed(2)}</span>
                    <button type="button" class="bono-item-remove" data-id="${w.id}"><i class="fas fa-times"></i></button>
                </div>
                
                <div class="row g-2 align-items-center mt-2">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input bono-chk-tipo" type="checkbox" data-id="${w.id}" id="chkFijo_${w.id}" ${isFijo ? 'checked' : ''} style="cursor:pointer;">
                            <label class="form-check-label small" for="chkFijo_${w.id}" style="cursor:pointer; color: rgba(255,255,255,0.85) !important;">
                                <i class="fas fa-coins me-1 text-warning"></i> Pago Fijo
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-8 d-flex align-items-center gap-2 flex-wrap">
                        ${!isFijo ? `
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text bg-dark border-secondary text-white">%</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-porciento-input" 
                                       data-id="${w.id}" value="${pctVal}" step="0.1" min="0" placeholder="Bono %">
                            </div>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text bg-dark border-secondary text-white">Coef.</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-coeficiente-input" 
                                       data-id="${w.id}" value="${coefVal}" step="0.01" min="0" placeholder="Coef.">
                            </div>
                            <span class="badge bg-success small text-wrap text-start ms-2" style="max-width: 250px; line-height: 1.4;">
                                Calc: <strong class="bono-calc-preview-text text-dark">$${montoVal ? parseFloat(montoVal).toFixed(2) : '0.00'}</strong> 
                                <br><small class="text-dark">(${pctVal || 0} * ${coefVal || 0}) - $${w.salario.toFixed(2)}</small>
                            </span>
                        ` : `
                            <div class="input-group input-group-sm" style="width: 160px;">
                                <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                <input type="number" class="form-control bg-dark border-secondary text-white text-center bono-monto-fijo-input" 
                                       data-id="${w.id}" value="${montoVal}" step="0.01" min="0" placeholder="Monto Fijo">
                            </div>
                            <span class="badge bg-success text-dark small ms-2"><i class="fas fa-edit me-1"></i>Valor Fijo</span>
                        `}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    $('#bonosList').html(html || '<em class="text-white-50"><i class="fas fa-gift me-1"></i>Seleccione trabajadores de la lista</em>');
    
    if (typeof focusId !== 'undefined' && focusId) {
        setTimeout(function() {
            var $input = $('#bonosList').find(`.bono-monto-fijo-input[data-id="${focusId}"], .bono-porciento-input[data-id="${focusId}"]`);
            if ($input.length) {
                $input.focus().select();
            }
        }, 50);
    }
    
    updateBonosPreviewTotals();
}
    
    $('#searchBonoWorker').on('input', function(){ renderBonoWorkerList($(this).val()); });
    
$(document).on('click', '#bonoWorkerList .worker-item', function(){
    var id = $(this).attr('data-id'), idx = selectedBonos.findIndex(s=>s.id==id);
    if(idx>=0) {
        selectedBonos.splice(idx,1); 
    } else {
        selectedBonos.push({
            id: id, 
            nombre: $(this).attr('data-nombre'), 
            codigo: $(this).attr('data-codigo'), 
            salario: parseFloat($(this).attr('data-salario')) || 0,
            tipo: 'porciento',
            porciento: '',
			coeficiente: '',
            monto: ''
        });
    }
    renderBonoWorkerList($('#searchBonoWorker').val()); 
    updateBonosList(id); // Pasa el ID para enfocar el input de bono si fue agregado
});

    $(document).on('change', '.bono-chk-tipo', function() {
        var id = $(this).data('id');
        var isChecked = $(this).is(':checked');
        var w = selectedBonos.find(s => s.id == id);
        if (w) {
            w.tipo = isChecked ? 'fijo' : 'porciento';
            w.monto = '';
            w.porciento = '';
            updateBonosList(); 
        }
    });

$(document).on('input', '.bono-porciento-input, .bono-coeficiente-input', function() {
    var id = $(this).data('id');
    var $card = $(this).closest('.selected-worker-card');
    
    var valPct = parseFloat($card.find('.bono-porciento-input').val()) || 0;
    var valCoef = parseFloat($card.find('.bono-coeficiente-input').val()) || 0;
    
    var w = selectedBonos.find(s => s.id == id);
    if (w) {
        w.porciento = valPct;
        w.coeficiente = valCoef;
        
        // FÓRMULA SOLICITADA: (porcentaje * coeficiente) - salario_escala (mínimo de 0)
        var calculoBruto = (valPct * valCoef) - w.salario;
        w.monto = Math.max(0, Math.roundExcel(calculoBruto, 2));
        
        $card.find('.bono-calc-preview-text').text('$' + w.monto.toFixed(2));
        $card.find('small').text('(' + valPct + ' * ' + valCoef + ') - $' + w.salario.toFixed(2));
        updateBonosPreviewTotals();
    }
});

    $(document).on('input', '.bono-monto-fijo-input', function() {
        var id = $(this).data('id');
        var valMonto = parseFloat($(this).val()) || 0;
        var w = selectedBonos.find(s => s.id == id);
        if (w) {
            w.monto = valMonto;
            updateBonosPreviewTotals();
        }
    });

$(document).on('click', '.bono-item-remove', function(){
    selectedBonos.splice(selectedBonos.findIndex(s => s.id == $(this).data('id')), 1); 
    renderBonoWorkerList($('#searchBonoWorker').val()); 
    updateBonosList();
    
    // Restablecer bandera para permitir futuras alertas si es necesario
    fondoBonoAgotadoAlertado = false;
    verificarFondoBonoAgotado();
});

$('#modalBono').on('show.bs.modal', function(){ 
    selectedBonos=[]; 
    fondoBonoAgotadoAlertado = false; // <-- Restablecer la variable de control
    renderBonoWorkerList(); 
    updateBonosList(); 
    $('#searchBonoWorker').val(''); 
    $('#conceptoBono').val('');
    $('#montoInicialBono').val(''); 
    
    var td = window.tempSelectedDiscount || $('#tablaNominas tbody tr:first').data('tipo-descuento') || 'total_rangos';
    $('#tipoDescuentoBono').val(td);
    
    window.tempSelectedDiscount = null;
});

$('#formBonos').on('submit', function(e){
    // Limpiar inputs previos dinámicos
    $(this).find('input[name="trabajador_id[]"], input[name="monto_bono[]"]').remove();
    
    if(!$('#conceptoBono').val().trim()){ 
        e.preventDefault(); 
        Swal.fire({
            title: 'Error', 
            text: 'Ingrese un concepto para el bono.', 
            icon: 'error', 
            background: '#1a1a2e', 
            color: 'white', 
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        }); 
        return false; 
    }

    // 1. VALIDACIÓN DEL FONDO INICIAL (Obligatorio y > 0)
    var montoInicialVal = $('#montoInicialBono').val();
    var montoInicial = parseFloat(montoInicialVal) || 0;

    if (!montoInicialVal || montoInicial <= 0) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle text-warning me-2"></i> Fondo No Especificado',
            text: 'Debe especificar el Fondo Inicial para la Distribución (monto mayor a cero).',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        });
        return false;
    }

    // 2. VALIDACIÓN DE SELECCIÓN DE TRABAJADORES
    if (selectedBonos.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Sin Selección',
            text: 'Debe seleccionar al menos un trabajador para la distribución del bono.',
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar'
        });
        return false;
    }

    var totalRepartido = 0;
    var incompleteWorker = null;
    var valid = false;

    // 3. VALIDACIÓN DE MONTOS INDIVIDUALES (> 0)
    selectedBonos.forEach(w => {
        var m = parseFloat(w.monto) || 0;
        if (m <= 0) {
            incompleteWorker = w.nombre;
        } else {
            $(this).append(`<input type="hidden" name="trabajador_id[]" value="${w.id}"><input type="hidden" name="monto_bono[]" value="${m}">`);
            totalRepartido += m;
            valid = true;
        }
    });

    if (incompleteWorker) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Monto Requerido',
            html: `Por favor, asigne un monto de bono válido y mayor a cero para el trabajador:<br><strong>${escapeHtml(incompleteWorker)}</strong>`,
            icon: 'warning',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Corregir'
        });
        return false;
    }

    // 4. VALIDACIÓN DE SALDO RESTANTE NEGATIVO
    var montoRestante = montoInicial - totalRepartido;
    if (montoRestante < 0) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Fondo Excedido',
            html: `
                <div class="text-center">
                    <p class="mb-3">No se puede generar el pago del bono porque el <strong>Monto Restante en fondo</strong> es negativo.</p>
                    <div class="p-3 my-2 rounded" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); font-size: 0.9rem; line-height: 1.5;">
                        <strong>Fondo Inicial:</strong> $${montoInicial.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                        <strong>Monto Asignado:</strong> $${totalRepartido.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                        <strong>Monto Restante:</strong> <span class="text-danger" style="font-weight: bold;">$${montoRestante.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                    </div>
                    <p class="small text-muted mt-2">Por favor, reduzca los montos de la distribución antes de procesar.</p>
                </div>
            `,
            icon: 'error',
            background: '#1a1a2e',
            color: 'white',
            confirmButtonColor: '#ef4444',
            confirmButtonText: '<i class="fas fa-undo me-2"></i>Corregir Distribución'
        });
        return false;
    }

    $(this).append('<input type="hidden" name="confirmar_bono" value="1">'); 
    return true;
});

    $('#btnBackupManual').on('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-database text-warning me-2"></i> Respaldo Manual',
            text: 'Se creará una copia de seguridad completa de la base de datos (SQL).',
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
                            text: 'Respaldo generado exitosamente.',
                            icon: 'success',
                            confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
                            background: '#1a1a2e',
                            color: '#ffffff'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'No se pudo generar el respaldo',
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

    // Selector de Consulta Rápida
    function actualizarMesesConsulta() {
        var anio = $('#consultaAnioSelect').val();
        var estado = $('#consultaEstadoSelect').val();
        var tipo = $('#consultaTipoSelect').val();
        var mesSelect = $('#consultaMesSelect');
        
        if (!anio) {
            mesSelect.html('<option value="">-- Seleccione un año --</option>');
            $('#infoNominasPeriodo').html('Seleccione un año para ver los meses con nóminas');
            return;
        }
        
        mesSelect.html('<option value="">Cargando meses...</option>');
        
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: {
                action: 'get_meses_nominas',
                anio: anio,
                estado: estado,
                tipo: tipo,
                ajax: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.meses.length > 0) {
                    var options = '<option value="">-- Seleccione un mes --</option>';
                    var mesesConNominas = response.meses;
                    var nombresMeses = {
                        1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
                        5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
                        9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
                    };
                    
                    mesesConNominas.forEach(function(mesNum) {
                        var mesNumStr = mesNum.toString().padStart(2, '0');
                        options += `<option value="${mesNumStr}">${nombresMeses[mesNum]}</option>`;
                    });
                    mesSelect.html(options);
                    
                    var estadoTexto = estado ? (estado === 'borrador' ? 'Borrador' : 'Contabilizado') : 'todos';
                    var tipoTexto = tipo ? tipo : 'todos';
                    $('#infoNominasPeriodo').html(`<i class="fas fa-check-circle text-success me-1"></i> ${mesesConNominas.length} mes(es) con nóminas en ${anio} (Estado: ${estadoTexto}, Tipo: ${tipoTexto})`);
                } else {
                    mesSelect.html('<option value="">-- No hay nóminas en este año --</option>');
                    $('#infoNominasPeriodo').html(`<i class="fas fa-info-circle text-warning me-1"></i> No hay nóminas registradas en ${anio} con los filtros seleccionados`);
                }
            },
            error: function() {
                mesSelect.html('<option value="">-- Error al cargar meses --</option>');
                $('#infoNominasPeriodo').html('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error al cargar los meses');
            }
        });
    }

    $('#consultaAnioSelect, #consultaEstadoSelect, #consultaTipoSelect').on('change', function() {
        actualizarMesesConsulta();
    });



    if ($('#consultaAnioSelect').val()) {
        actualizarMesesConsulta();
    }

    function inicializarEstadisticas() {
        setTimeout(function() {
            actualizarEstadisticas();
            if (nominasTable) {
                nominasTable.on('draw', function() {
                    actualizarEstadisticas();
                });
            }
        }, 100);
    }
    
    inicializarEstadisticas();
});

$('#formVacaciones').on('submit', function(e) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...',
        text: 'Agregando trabajadores a la nómina de vacaciones',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });
});

if (window.location.href.indexOf('msg=vacaciones_added') > -1) {
    var urlParams = new URLSearchParams(window.location.search);
    var count = urlParams.get('count') || '0';
    var errores = urlParams.get('errores');
    
    if (errores) {
        Swal.fire({
            icon: 'warning',
            title: 'Vacaciones agregadas con advertencias',
            html: '<p>Se agregaron <strong>' + count + '</strong> trabajadores.</p><p class="text-danger">' + decodeURIComponent(errores) + '</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    } else {
        Swal.fire({
            icon: 'success',
            title: '<i class="fas fa-check-circle me-2"></i> Vacaciones agregadas',
            html: '<p>Se agregaron <strong>' + count + '</strong> trabajadores a la nómina de vacaciones.</p>',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }
    
    window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]msg=vacaciones_added[^&]*/, '').replace(/[?&]count=[^&]*/, '').replace(/[?&]errores=[^&]*/, ''));
}

$('#btnExportarBanco').on('click', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: '<i class="fas fa-university me-2"></i> Exportar al Banco',
        text: '¿Desea generar el archivo para la acreditación bancaria?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Exportar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            var url = 'bandecnom.php?periodo=<?php echo $periodo; ?>&tipo=<?php echo $tipo_nomina_activa; ?>';
            window.open(url, '_self');
        }
    });
});
/*  EXPORTADORES DE LA NOMINA IMPRESA */
// =========================================================================
// PARTE 2: COMPLEMENTO DE FUNCIONES DE APOCYO PARA AGRUPACIÓN Y CONTROL
// =========================================================================

function obtenerCampoAgrupacion(alcance) {
    switch (alcance) {
        case 'area': return 'area';
        case 'centro_costo': return 'centroCosto';
        case 'categoria': return 'categoria';
        case 'escala': return 'escalaDescripcion';
        case 'tipo_contrato': return 'tipoContrato';
        case 'cargo': return 'cargo';
    }
}

function obtenerNombreAgrupacionTexto(alcance) {
    switch (alcance) {
        case 'area': return 'ÁREA';
        case 'centro_costo': return 'CENTRO DE COSTO';
        case 'categoria': return 'CATEGORÍA OCUPACIONAL';
        case 'escala': return 'ESCALA SALARIAL';
        case 'tipo_contrato': return 'TIPO DE CONTRATO';
        case 'cargo': return 'CARGO';
    }
}

function agruparTrabajadores(lista, campo) {
    let grupos = {};
    lista.forEach(t => {
        let clave = t[campo];
        if (!clave || clave === '') clave = 'Sin especificar';
        if (!grupos[clave]) grupos[clave] = [];
        grupos[clave].push(t);
    });
    return grupos;
}

function acumularTotales(objetoDestino, trabajador) {
    objetoDestino.aCobrar += trabajador.aCobrar || 0;
    objetoDestino.bono += trabajador.bono || 0;
    objetoDestino.devengado += trabajador.devengado || 0;
    objetoDestino.impS += trabajador.impS || 0;
    objetoDestino.descuentos += trabajador.descuentos || 0;        // <-- CORRECCIÓN: Agregado
    objetoDestino.retenciones += trabajador.retenciones || 0;
    objetoDestino.pagado += trabajador.pagado || 0;
    objetoDestino.vacDias += trabajador.vacDias || 0;
    objetoDestino.tiempoImp += trabajador.tiempoImp || 0;
    objetoDestino.salarioMensual += trabajador.salarioMensual || 0;  // <-- CORRECCIÓN: Agregado
}

function generarFilaExcel(t) {
    return `
        <tr>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.codigo)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.ci)}</td>
            <td style="border:0.5pt solid #000;">${window.escapeHtml(t.nombre)}</td>
            <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(t.categoriaCodigo)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.tarifaSal.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.horas}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.aCobrar.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.bono.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.devengado.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.impS.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.retenciones.toFixed(2)}</td>
            <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">${t.pagado.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.vacDias.toFixed(2)}</td>
            <td style="text-align:right; border:0.5pt solid #000;">${t.tiempoImp.toFixed(2)}</td>
            <td style="border:0.5pt solid #000;"></td>
        </tr>`;
}

function generarFilaPdf(t) {
    return [
        { text: t.codigo, alignment: 'center', style: 'tableCell' },
        { text: t.ci, alignment: 'center', style: 'tableCell' },
        { text: t.nombre, alignment: 'left', style: 'tableCell' },
        { text: t.categoriaCodigo, alignment: 'center', style: 'tableCell' },
        { text: t.tarifaSal.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.horas.toString(), alignment: 'right', style: 'tableCell' },
        { text: t.aCobrar.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.bono.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.devengado.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.impS.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.retenciones.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.pagado.toFixed(2), alignment: 'right', style: 'tableCellBold' },
        { text: t.vacDias.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: t.tiempoImp.toFixed(2), alignment: 'right', style: 'tableCell' },
        { text: '', style: 'tableCell' }
    ];
}

function construirPaginasDeNomina(trabajadores, alcance) {
    const FILAS_POR_PAGINA = 25;
    let paginas = [];
    let paginaActual = [];
    let contadorFilas = 0;
    
    // CORRECCIÓN: Inicialización de propiedades descuentos y salarioMensual para evitar undefined
    let subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
    let totalGeneral = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };

    function cerrarPagina() {
        if (paginaActual.length === 0) return;
        paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
        paginaActual = [];
        contadorFilas = 0;
        subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
    }

    if (alcance === 'general') {
        trabajadores.forEach((t) => {
            if (contadorFilas >= FILAS_POR_PAGINA) cerrarPagina();
            paginaActual.push({ tipo: 'registro', data: t });
            acumularTotales(subtotalPagina, t);
            acumularTotales(totalGeneral, t);
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();
    } else {
        let campoAgrupacion = obtenerCampoAgrupacion(alcance);
        let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
        let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);

        Object.entries(grupos).forEach(([clave, empleados]) => {
            let subTotalGrupo = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
            let espacioNecesario = empleados.length + 2; 

            if (paginaActual.length > 0 && (contadorFilas + espacioNecesario > FILAS_POR_PAGINA)) {
                cerrarPagina();
            }

            paginaActual.push({ tipo: 'grupo_header', titulo: clave });
            contadorFilas++;

            empleados.forEach((t) => {
                if (contadorFilas >= FILAS_POR_PAGINA) {
                    cerrarPagina();
                    paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (continuación)` });
                    contadorFilas++;
                }
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subTotalGrupo, t);
                acumularTotales(subtotalPagina, t);
                acumularTotales(totalGeneral, t);
                contadorFilas++;
            });

            paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
            contadorFilas++;
        });
        if (paginaActual.length > 0) cerrarPagina();
    }

    return { paginas, totalGeneral };
}

// =========================================================================
// PARTE 3: EXPORTADORES OFICIALES (EXCEL Y PDF) CON RENDERIZADO DETALLADO
// =========================================================================

function exportarExcelOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-success me-2"></i> Generando Excel...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato de hoja de cálculo.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e', color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.xls`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = window.escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = window.escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());

        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : 15;

        let estructura = construirPaginasDeNomina(trabajadores, alcance);
        let htmlBody = '';

        estructura.paginas.forEach((pag, index) => {
            let numPag = index + 1;
            htmlBody += `
                <tr><td colspan="${colsCount}" style="border:none; height:15px;"></td></tr>
                <tr><td colspan="${colsCount}" class="title" style="background-color:#004b87; color:#ffffff; font-weight:bold; font-size:11pt; border:0.5pt solid #000;">PÁGINA ${numPag} de ${estructura.paginas.length}</td></tr>
            `;

            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    htmlBody += `<tr><td colspan="${colsCount}" style="background-color:#e0e0e0; font-weight:bold; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}</b></td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        htmlBody += `
                            <tr>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.codigo)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.ci)}</td>
                                <td style="border:0.5pt solid #000;">${window.escapeHtml(row.data.nombre)}</td>
                                <td style="text-align:center; border:0.5pt solid #000;">${window.escapeHtml(row.data.categoriaCodigo)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.devengado || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.impS || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td style="text-align:right; font-weight:bold; border:0.5pt solid #000;">$${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>
                            <tr>
                                <td colspan="11" style="text-align:left; border:0.5pt solid #000;">Observación: ${window.escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                    } else {
                        htmlBody += generarFilaExcel(row.data);
                        if (esAjuste) {
                            htmlBody += `
                            <tr>
                                <td colspan="15" style="text-align:left; border:0.5pt solid #000;">Observación: ${window.escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        htmlBody += `
                            <tr style="background-color:#f2f2f2; font-weight:bold;">
                                <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}:</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.devengado.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.impS.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.descuentos.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">$${row.data.retenciones.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000; color:#004b87;"><b>$${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else {
                        htmlBody += `
                            <tr style="background-color:#f2f2f2; font-weight:bold;">
                                <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>${window.escapeHtml(row.titulo)}:</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td style="text-align:right; border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    }
                }
            });

            // Subtotal de la página
            if (esBono) {
                htmlBody += `
                    <tr style="background-color:#fff3cd; font-weight:bold;">
                        <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.devengado.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.impS.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">$${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000; color:#b45309;"><b>$${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else {
                htmlBody += `
                    <tr style="background-color:#fff3cd; font-weight:bold;">
                        <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td style="text-align:right; border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            }
        });

        let gTot = estructura.totalGeneral;
        if (esBono) {
            htmlBody += `
                <tr style="background-color:#d9e1f2; font-weight:bold;">
                    <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.devengado.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.impS.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.descuentos.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">$${gTot.retenciones.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000; color:#1e3a8a;"><b>$${gTot.pagado.toFixed(2)}</b></td>
                    <td style="border:0.5pt solid #000;"></td>
                </tr>`;
        } else {
            htmlBody += `
                <tr style="background-color:#d9e1f2; font-weight:bold;">
                    <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                    <td style="text-align:right; border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                    <td style="border:0.5pt solid #000;"></td>
                </tr>`;
        }

        // Títulos de tabla Excel
        let headersExcel = esBono ? `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>S. Básico</td>
                <td>Deven.</td><td>Imp. CESS</td><td>Descuentos</td><td>Ret Total</td><td>Pagado</td><td>Firma</td>
            </tr>
        ` : `
            <tr class="table-header">
                <td>Código</td><td>CI</td><td>Nombre y Apellidos</td><td>Cat.</td><td>Tarf.</td><td>Horas</td>
                <td>A cobrar</td><td>Bon.</td><td>Deven.</td><td>Imp. CESS.</td><td>Ret.</td><td>Pagado</td>
                <td>Vac.</td><td>Tiem. Imp.</td><td>Firma</td>
            </tr>
        `;

        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8">
        <!--[if gte mso 9]>
        <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>NOMINA_${cleanMonthName}-${anioGlobal}</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; }
            td, th { border: 0.5pt solid #000000; padding: 5px; }
            .title { font-size: 13pt; font-weight: bold; text-align: center; }
            .header-meta { background-color: #f2f2f2; font-weight: bold; font-size: 8.5pt; }
            .table-header { background-color: #004B87; color: #ffffff; font-weight: bold; text-align: center; }
        </style>
        </head>
        <body>
            <table>
                <tr><td colspan="${colsCount}" class="title" style="border:none;">MODELO SC-4-06 NOMINA - ${window.escapeHtml(nombreEmpresa)}</td></tr>
                <tr>
                    <td colspan="3" class="header-meta"><b>Tipo Nómina:</b> ${window.escapeHtml(tipoNominaTexto)}</td>
                    <td colspan="2" class="header-meta"><b>Período:</b> ${periodoTexto}</td>
                    <td colspan="${esBono ? 3 : 4}" class="header-meta"><b>Nº Nómina / No. Instrum. Pago:</b> ${window.escapeHtml(numeroNomina)}</td>
                    <td colspan="${esBono ? 3 : 6}" rowspan="2" style="vertical-align:top; border:0.5pt solid #000; font-size: 8.5pt; line-height: 1.4;">
                        <b>REVISADO POR:</b> ${nombreRevisado}<br>
                        <b>APROBADO POR:</b> ${nombreAprobado}<br>
                        <b>ELABORADO POR:</b> ___________________________<br>
                        <b>CONTABILIZADO POR:</b> _______________________
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="header-meta"><b>Alcance:</b> ${window.escapeHtml(scopeText)}</td>
                    <td colspan="2" class="header-meta">
                        <b>${esBono ? 'Monto a Distribuir: ' + '$' + montoDistribuidoGlobal.toFixed(2) : 'REEUP: ' + escapeHtml(reeup)}</b><br>
                        <b>NIT:</b> ${window.escapeHtml(nitEmpresa)}
                    </td>
                    <td colspan="${esBono ? 3 : 4}" class="header-meta"><b>Fecha Emisión:</b> ${fechaActual12h} ${horaActual12h}</td>
                </tr>
                ${observacionesCierreGlobal ? `
                <tr>
                    <td colspan="${colsCount}" class="header-meta" style="background-color:#fff3cd; border:0.5pt solid #000;">
                        <b>Observaciones de Cierre:</b> ${window.escapeHtml(observacionesCierreGlobal)}
                    </td>
                </tr>` : ''}
                <tr><td colspan="${colsCount}" style="border:none; height:10px;"></td></tr>
                ${headersExcel}
                ${htmlBody}
            </table>
        </body>
        </html>`;

        let blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nombreArchivo;
        link.click();
        URL.revokeObjectURL(link.href);

        Swal.close();
        Swal.fire({ title: '¡Completado!', text: 'El archivo Excel oficial se ha descargado correctamente.', icon: 'success', timer: 1800, showConfirmButton: false, background: '#1a1a2e', color: '#ffffff' });
    }, 800);
}

// =========================================================================
// EXPORTADOR PDF OFICIAL CON PAGINACIÓN IDÉNTICA (SC-4-06) (CORREGIDO)
// =========================================================================
function exportarPdfOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-danger me-2"></i> Generando PDF...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato de hoja Carta horizontal.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.pdf`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = window.escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = window.escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());

        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : 16;
        const widthsConfig = esBono ? [35, 55, '*', 22, 55, 55, 50, 55, 55, 55, 55] : [30, 50, '*', 18, 42, 30, 24, 38, 30, 38, 38, 38, 42, 22, 40, 42];

        let estructura = construirPaginasDeNomina(trabajadores, alcance);
        let docContent = [];

        estructura.paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let tableRows = [];

            // Cabeceras de tabla (11 columnas para Bono / 16 para el resto)
            if (esBono) {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'S. Básico', style: 'tableHeader' },
                    { text: 'Deven.', style: 'tableHeader' },
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Ret.', style: 'tableHeader' },
                    { text: 'Ret Total', style: 'tableHeader' },
                    { text: 'Pagado', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            } else {
                tableRows.push([
                    { text: 'Código', style: 'tableHeader' },
                    { text: 'CI', style: 'tableHeader' },
                    { text: 'Nombre y Apellidos', style: 'tableHeader' },
                    { text: 'Cat.', style: 'tableHeader' },
                    { text: 'S. Básico', style: 'tableHeader' },
                    { text: 'Tarf.', style: 'tableHeader' },
                    { text: 'Horas', style: 'tableHeader' },
                    { text: 'A cobrar', style: 'tableHeader' },
                    { text: 'Bon.', style: 'tableHeader' },
                    { text: 'Deven.', style: 'tableHeader' },
                    { text: 'Imp. CESS', style: 'tableHeader' },
                    { text: 'Ret.', style: 'tableHeader' },
                    { text: 'Pagado', style: 'tableHeader' },
                    { text: 'Vac.', style: 'tableHeader' },
                    { text: 'Tiem. Imp.', style: 'tableHeader' },
                    { text: 'Firma', style: 'tableHeader' }
                ]);
            }

            // Filas de Datos
            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    let headerRow = [{ text: row.titulo.toUpperCase(), colSpan: colsCount, style: 'groupHeader', alignment: 'left' }];
                    for (let i = 1; i < colsCount; i++) headerRow.push({});
                    tableRows.push(headerRow);
                } else if (row.tipo === 'registro') {
                    // Obtener Salario Básico de forma segura del origen de datos local
                    var dbWorker = window.trabajadores.find(function(w) { 
                        return w.codigo.toString().trim() === row.data.codigo.toString().trim(); 
                    });
                    var salarioBasicoResuelto = dbWorker ? parseFloat(dbWorker.salario_mensual) : (row.data.salarioMensual || 0);

                    if (esBono) {
                        tableRows.push([
                            { text: row.data.codigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.ci, alignment: 'center', style: 'tableCell' },
                            { text: row.data.nombre, alignment: 'left', style: 'tableCell' },
                            { text: row.data.categoriaCodigo, alignment: 'center', style: 'tableCell' },
                            { text: salarioBasicoResuelto.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.devengado || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.impS || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.descuentos || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.retenciones || 0).toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: (row.data.pagado || 0).toFixed(2), alignment: 'right', style: 'tableCellBold' },
                            { text: '', style: 'tableCell' }
                        ]);
                        tableRows.push([{ text: 'Observación: ' + (row.data.concepto || ''), colSpan: 11, alignment: 'left', style: 'tableCell' }]);
                    } else {
                        tableRows.push([
                            { text: row.data.codigo, alignment: 'center', style: 'tableCell' },
                            { text: row.data.ci, alignment: 'center', style: 'tableCell' },
                            { text: row.data.nombre, alignment: 'left', style: 'tableCell' },
                            { text: row.data.categoriaCodigo, alignment: 'center', style: 'tableCell' },
                            { text: salarioBasicoResuelto.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.tarifaSal.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.horas.toString(), alignment: 'right', style: 'tableCell' },
                            { text: row.data.aCobrar.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.bono.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.devengado.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'tableCellBold' },
                            { text: row.data.vacDias.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: row.data.tiempoImp.toFixed(2), alignment: 'right', style: 'tableCell' },
                            { text: '', style: 'tableCell' }
                        ]);
                        if (esAjuste) {
                            tableRows.push([{ text: 'Observación: ' + (row.data.concepto || ''), colSpan: 16, alignment: 'left', style: 'tableCell' }]);
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        tableRows.push([
                            { text: `${row.titulo}:`, colSpan: 5, alignment: 'right', style: 'groupFooter' },
                            {}, {}, {}, {},
                            { text: row.data.devengado.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.descuentos.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'groupFooterBold' },
                            { text: '', style: 'groupFooter' }
                        ]);
                    } else {
                        tableRows.push([
                            { text: `${row.titulo}:`, colSpan: 7, alignment: 'right', style: 'groupFooter' },
                            {}, {}, {}, {}, {}, {},
                            { text: row.data.aCobrar.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.bono.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.devengado.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.impS.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.retenciones.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.pagado.toFixed(2), alignment: 'right', style: 'groupFooterBold' },
                            { text: row.data.vacDias.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: row.data.tiempoImp.toFixed(2), alignment: 'right', style: 'groupFooter' },
                            { text: '', style: 'groupFooter' }
                        ]);
                    }
                }
            });

            // Subtotal de Página
            if (esBono) {
                tableRows.push([
                    { text: `SUBTOTAL PÁGINA ${numPag}:`, colSpan: 5, alignment: 'right', style: 'pageSubtotal' },
                    {}, {}, {}, {},
                    { text: pag.subtotal.devengado.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.impS.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.descuentos.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.retenciones.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.pagado.toFixed(2), alignment: 'right', style: 'pageSubtotalBold' },
                    { text: '', style: 'pageSubtotal' }
                ]);
            } else {
                tableRows.push([
                    { text: `SUBTOTAL PÁGINA ${numPag}:`, colSpan: 7, alignment: 'right', style: 'pageSubtotal' },
                    {}, {}, {}, {}, {}, {},
                    { text: pag.subtotal.aCobrar.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.bono.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.devengado.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.impS.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.retenciones.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.pagado.toFixed(2), alignment: 'right', style: 'pageSubtotalBold' },
                    { text: pag.subtotal.vacDias.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: pag.subtotal.tiempoImp.toFixed(2), alignment: 'right', style: 'pageSubtotal' },
                    { text: '', style: 'pageSubtotal' }
                ]);
            }

            // Fila de Total General en la última página
            if (numPag === estructura.paginas.length) {
                let gTot = estructura.totalGeneral;
                if (esBono) {
                    tableRows.push([
                        { text: 'TOTAL GENERAL NOMINA:', colSpan: 5, alignment: 'right', style: 'tableFooter' },
                        {}, {}, {}, {},
                        { text: gTot.devengado.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.impS.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.descuentos.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.retenciones.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.pagado.toFixed(2), alignment: 'right', style: 'tableFooterBold' },
                        { text: '', style: 'tableFooter' }
                    ]);
                } else {
                    tableRows.push([
                        { text: 'TOTAL GENERAL NOMINA:', colSpan: 7, alignment: 'right', style: 'tableFooter' },
                        {}, {}, {}, {}, {}, {},
                        { text: gTot.aCobrar.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.bono.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.devengado.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.impS.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.retenciones.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.pagado.toFixed(2), alignment: 'right', style: 'tableFooterBold' },
                        { text: gTot.vacDias.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: gTot.tiempoImp.toFixed(2), alignment: 'right', style: 'tableFooter' },
                        { text: '', style: 'tableFooter' }
                    ]);
                }
            }

            // Configuración del encabezado dinámico de firmas y metadatos
            let headerBlock = {
                table: {
                    widths: [45, '*', 230],
                    body: [
                        [
                            logoBase64 ? { image: logoBase64, width: 32, alignment: 'center' } : { text: '' },
                            {
                                stack: [
                                    { text: `MODELO SC-4-06 NOMINA - ${nombreEmpresa.toUpperCase()}`, fontSize: 10, bold: true },
                                    {
                                        columns: [
                                            { text: `Tipo: ${tipoNominaTexto}`, fontSize: 7, bold: true },
                                            { text: `Período: ${periodoTexto}`, fontSize: 7 },
                                            { text: `Nº Nómina: ${numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina}`, fontSize: 7, bold: true }
                                        ]
                                    },
                                    {
										columns: [
											{ text: `Alcance: ${scopeText}`, fontSize: 7 },
											// 🔽 MODIFICA ESTA LÍNEA
											{ 
												text: esBono 
													? (montoDistribuidoGlobal > 0 
														? `Monto a Distribuir: $${montoDistribuidoGlobal.toFixed(2)}` 
														: 'Monto a Distribuir: (Hasta que se contabilice)') 
													: `REEUP: ${reeup}\nNIT: ${nitEmpresa}`,
												fontSize: 7,
												alignment: 'left'
											},
											{ text: `Emisión: ${fechaActual12h} ${horaActual12h}`, fontSize: 7 }
										]
                                    }
                                ],
                                margin: [5, 2, 0, 0]
                            },
                            {
                                stack: [
                                    { text: `REVISADO POR: ${nombreRevisado}`, fontSize: 6.5, bold: true },
                                    { text: `APROBADO POR: ${nombreAprobado}`, fontSize: 6.5, bold: true, margin: [0, 2, 0, 0] },
                                    { text: 'ELABORADO POR: ___________________________', fontSize: 6.5, margin: [0, 2, 0, 0] },
                                    { text: 'CONTABILIZADO POR: _______________________', fontSize: 6.5, margin: [0, 2, 0, 0] }
                                ],
                                margin: [5, 1, 0, 0]
                            }
                        ]
                    ]
                },
                margin: [0, 0, 0, 8],
                layout: {
                    hLineWidth: function() { return 0.5; },
                    vLineWidth: function() { return 0.5; },
                    hLineColor: function() { return '#000000'; },
                    vLineColor: function() { return '#000000'; }
                }
            };

            // Mostrar el Monto a Distribuir en el Header del PDF si es Bono
            if (esBono) {
                headerBlock.table.body.push([
                    { text: 'Monto a Distribuir:', style: 'tableHeader', colSpan: 2, fillColor: '#004B87', color: '#ffffff', alignment: 'right', fontSize: 7.5 },
                    {},
                    { text: '$' + (montoDistribuidoGlobal || 0).toFixed(2), fontSize: 8, bold: true, alignment: 'left', margin: [5, 2, 0, 0] }
                ]);
            }

            docContent.push(headerBlock);
            docContent.push({
                table: {
                    headerRows: 1,
                    widths: widthsConfig,
                    body: tableRows
                },
                layout: {
                    hLineWidth: function() { return 0.5; },
                    vLineWidth: function() { return 0.5; },
                    hLineColor: function() { return '#000000'; },
                    vLineColor: function() { return '#000000'; },
                    paddingLeft: function() { return 2; }, 
                    paddingRight: function() { return 2; },
                    paddingTop: function() { return 3; }, 
                    paddingBottom: function() { return 3; }
                }
            });

            if (numPag < estructura.paginas.length) {
                docContent.push({ text: '', pageBreak: 'after', margin: [0, 0, 0, 0] });
            }
        });

        var docDefinition = {
            pageOrientation: 'landscape',
            pageSize: 'LETTER',
            pageMargins: [15, 15, 15, 30],
            footer: function(currentPage, pageCount) {
                return {
                    margin: [15, 5, 15, 0],
                    columns: [
                        { text: 'Sistema de Gestión de Nóminas - Modelo Oficial SC-4-06', fontSize: 7, alignment: 'left', color: '#555555' },
                        { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7.5, alignment: 'right', bold: true }
                    ]
                };
            },
            content: docContent,
            styles: {
                tableHeader: { fontSize: 7.2, bold: true, color: '#ffffff', fillColor: '#004B87', alignment: 'center' },
                tableCell: { fontSize: 6.8, color: '#000000' },
                tableCellBold: { fontSize: 6.8, bold: true, color: '#000000' },
                groupHeader: { fontSize: 7, bold: true, color: '#000000', fillColor: '#e0e0e0', margin: [0, 1, 0, 1] },
                groupFooter: { fontSize: 6.8, bold: true, color: '#000000', fillColor: '#f9f9f9' },
                groupFooterBold: { fontSize: 6.8, bold: true, color: '#004B87', fillColor: '#f9f9f9' },
                pageSubtotal: { fontSize: 7, bold: true, color: '#000000', fillColor: '#fff3cd' },
                pageSubtotalBold: { fontSize: 7, bold: true, color: '#b45309', fillColor: '#fff3cd' },
                tableFooter: { fontSize: 7.2, bold: true, color: '#000000', fillColor: '#d9e1f2' },
                tableFooterBold: { fontSize: 7.2, bold: true, color: '#1e3a8a', fillColor: '#d9e1f2' }
            }
        };

        pdfMake.createPdf(docDefinition).download(nombreArchivo);

        Swal.close();
        Swal.fire({
            title: '¡Completado!',
            text: 'El reporte PDF oficial se ha descargado correctamente.',
            icon: 'success',
            timer: 1800,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }, 800);
}
							
							
							
							
function exportarWordOficial(trabajadores, alcance, filtroNombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin text-primary me-2"></i> Generando Word...',
        html: 'Por favor, espere. Estructurando el reporte oficial <b>SC-4-06</b> en formato Word (.doc) con paginación exacta.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });

    setTimeout(function() {
        trabajadores = ordenarTrabajadoresPorAlcance(trabajadores, alcance);
        let now = new Date();
        
        let day = String(now.getDate()).padStart(2, '0');
        let monthNumeric = String(now.getMonth() + 1).padStart(2, '0');
        let year = now.getFullYear();
        let hours24 = now.getHours();
        let ampm = hours24 >= 12 ? 'PM' : 'AM';
        let hours12 = hours24 % 12 || 12;
        let hoursStr = String(hours12).padStart(2, '0');
        let minutesStr = String(now.getMinutes()).padStart(2, '0');
        let secondsStr = String(now.getSeconds()).padStart(2, '0');
        
        let fechaActual12h = `${day}/${monthNumeric}/${year}`;
        let horaActual12h = `${hoursStr}:${minutesStr}:${secondsStr} ${ampm}`;
        
        let hhmmss = `${String(hours24).padStart(2, '0')}${minutesStr}${secondsStr}`;
        let ddmmyyyy = `${day}${monthNumeric}${year}`;
        
        let cleanTipoNomina = tipoNominaTexto.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        let cleanMonthName = nombreMesGlobal.replace(/[^a-zA-Z0-9\u00C0-\u017F]/g, '_');
        
        let nombreArchivo = `SC-4-06_${cleanTipoNomina}-${cleanMonthName}-${anioGlobal}_${hhmmss}-${ddmmyyyy}.doc`;
        let scopeText = alcance.toUpperCase() + (filtroNombre ? ': ' + filtroNombre : '');

        let nombreRevisado = escapeHtml((typeof especialistaGestion !== 'undefined' ? especialistaGestion : '').toUpperCase());
        let nombreAprobado = escapeHtml((typeof jefeProyecto !== 'undefined' ? jefeProyecto : '').toUpperCase());
        let codigoMostrado = (numeroNomina === 'S/N' || numeroNomina === 'Borrador' || !numeroNomina) ? '' : escapeHtml(numeroNomina);

        let cleanLogo = logoBase64 ? logoBase64.replace(/(\r\n|\n|\r)/gm, "") : "";

        const FILAS_POR_PAGINA = 15;
        const esBono = (tipoNomina === 'bono');
        const esAjuste = (tipoNomina === 'ajuste');
        const mostrarConcepto = esBono || esAjuste;
        const colsCount = esBono ? 11 : 15;
        
        let paginas = [];
        let paginaActual = [];
        let contadorFilas = 0;
        
        let subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
        let totalGeneral = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };

        function cerrarPagina() {
            if (paginaActual.length === 0) return;
            paginas.push({ rows: [...paginaActual], subtotal: { ...subtotalPagina } });
            paginaActual = [];
            contadorFilas = 0;
            subtotalPagina = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
        }

        if (alcance === 'general') {
            trabajadores.forEach((t) => {
                if (contadorFilas >= FILAS_POR_PAGINA) cerrarPagina();
                paginaActual.push({ tipo: 'registro', data: t });
                acumularTotales(subtotalPagina, t);
                acumularTotales(totalGeneral, t);
                contadorFilas++;
            });
            if (paginaActual.length > 0) cerrarPagina();
        } else {
            let campoAgrupacion = obtenerCampoAgrupacion(alcance);
            let nombreAgrupacion = obtenerNombreAgrupacionTexto(alcance);
            let grupos = agruparTrabajadores(trabajadores, campoAgrupacion);

            Object.entries(grupos).forEach(([clave, empleados]) => {
                let subTotalGrupo = { aCobrar: 0, bono: 0, devengado: 0, impS: 0, retenciones: 0, pagado: 0, vacDias: 0, tiempoImp: 0, descuentos: 0, salarioMensual: 0 };
                let espacioNecesario = empleados.length + 2;

                if (paginaActual.length > 0 && (contadorFilas + espacioNecesario > FILAS_POR_PAGINA)) {
                    cerrarPagina();
                }

                paginaActual.push({ tipo: 'grupo_header', titulo: clave });
                contadorFilas++;

                empleados.forEach((t) => {
                    if (contadorFilas >= FILAS_POR_PAGINA) {
                        cerrarPagina();
                        paginaActual.push({ tipo: 'grupo_header', titulo: `${clave} (cont.)` });
                        contadorFilas++;
                    }
                    paginaActual.push({ tipo: 'registro', data: t });
                    acumularTotales(subTotalGrupo, t);
                    acumularTotales(subtotalPagina, t);
                    acumularTotales(totalGeneral, t);
                    contadorFilas++;
                });

                paginaActual.push({ tipo: 'grupo_subtotal', titulo: `TOTAL POR ${nombreAgrupacion}`, data: subTotalGrupo });
                contadorFilas++;
            });
            if (paginaActual.length > 0) cerrarPagina();
        }

        let pagesHtml = '';

        paginas.forEach((pag, index) => {
            let numPag = index + 1;
            let cuerpoHtml = '';

            pag.rows.forEach(row => {
                if (row.tipo === 'grupo_header') {
                    cuerpoHtml += `<tr><td colspan="${colsCount}" style="background-color:#e0e0e0; font-weight:bold; border:0.5pt solid #000; font-size:7.5pt;"><b>${escapeHtml(row.titulo)}</b></td></tr>`;
                } else if (row.tipo === 'registro') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.salarioMensual || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.devengado || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.impS || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.descuentos || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">$${(row.data.retenciones || 0).toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">$${(row.data.pagado || 0).toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>
                            <tr>
                                <td class="text-left" colspan="11" style="border:0.5pt solid #000; font-size:7pt;">Observación: ${escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                    } else {
                        cuerpoHtml += `
                            <tr>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.codigo)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.ci)}</td>
                                <td class="text-left" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.nombre)}</td>
                                <td class="text-center" style="border:0.5pt solid #000; font-size:7pt;">${escapeHtml(row.data.categoriaCodigo)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tarifaSal.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.horas}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.aCobrar.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.bono.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt; font-weight:bold;">${row.data.pagado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; font-size:7pt;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;"></td>
                            </tr>`;
                        if (esAjuste) {
                            cuerpoHtml += `
                            <tr>
                                <td class="text-left" colspan="15" style="border:0.5pt solid #000; font-size:7pt;">Observación: ${escapeHtml(row.data.concepto)}</td>
                            </tr>`;
                        }
                    }
                } else if (row.tipo === 'grupo_subtotal') {
                    if (esBono) {
                        cuerpoHtml += `
                            <tr style="background-color:#f9f9f9; font-weight:bold; font-size:7pt;">
                                <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>${escapeHtml(row.titulo)}:</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.descuentos.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">$${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; color:#004b87;"><b>$${row.data.pagado.toFixed(2)}</b></td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    } else {
                        cuerpoHtml += `
                            <tr style="background-color:#f9f9f9; font-weight:bold; font-size:7pt;">
                                <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>${escapeHtml(row.titulo)}:</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.aCobrar.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.bono.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.devengado.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.impS.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.retenciones.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000; color:#004b87;"><b>${row.data.pagado.toFixed(2)}</b></td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.vacDias.toFixed(2)}</td>
                                <td class="text-right" style="border:0.5pt solid #000;">${row.data.tiempoImp.toFixed(2)}</td>
                                <td style="border:0.5pt solid #000;">-</td>
                            </tr>`;
                    }
                }
            });

            // Subtotal de la página
            if (esBono) {
                cuerpoHtml += `
                    <tr style="background-color:#fff3cd; font-weight:bold; font-size:7pt;">
                        <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.devengado.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.impS.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.descuentos.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">$${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000; color:#b45309;"><b>$${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            } else {
                cuerpoHtml += `
                    <tr style="background-color:#fff3cd; font-weight:bold; font-size:7pt;">
                        <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>SUBTOTAL PÁGINA ${numPag}:</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.aCobrar.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.bono.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.devengado.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.impS.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.retenciones.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000; color:#b45309;"><b>${pag.subtotal.pagado.toFixed(2)}</b></td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.vacDias.toFixed(2)}</td>
                        <td class="text-right" style="border:0.5pt solid #000;">${pag.subtotal.tiempoImp.toFixed(2)}</td>
                        <td style="border:0.5pt solid #000;">-</td>
                    </tr>`;
            }

            // Totales acumulados al pie de la última página
            if (numPag === paginas.length) {
                let gTot = totalGeneral;
                if (esBono) {
                    cuerpoHtml += `
                        <tr style="background-color:#d9e1f2; font-weight:bold; font-size:7pt;">
                            <td colspan="5" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.devengado.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.impS.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.descuentos.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">$${gTot.retenciones.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; color:#1e3a8a;"><b>$${gTot.pagado.toFixed(2)}</b></td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                } else {
                    cuerpoHtml += `
                        <tr style="background-color:#d9e1f2; font-weight:bold; font-size:7pt;">
                            <td colspan="6" style="text-align:right; border:0.5pt solid #000;"><b>TOTAL GENERAL NOMINA:</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.aCobrar.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.bono.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.devengado.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.impS.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.retenciones.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000; color:#1e3a8a;"><b>${gTot.pagado.toFixed(2)}</b></td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.vacDias.toFixed(2)}</td>
                            <td class="text-right" style="border:0.5pt solid #000;">${gTot.tiempoImp.toFixed(2)}</td>
                            <td style="border:0.5pt solid #000;"></td>
                        </tr>`;
                }
            }

            let breakBlock = (numPag > 1) ? '<br style="page-break-before:always; clear:both; mso-break-type:section-break" />' : '';

            // Encabezados de tabla para Word
            let headerTR = esBono ? `
                <tr style="background-color:#004b87; color:#ffffff; font-weight:bold; text-align:center;">
                    <th style="border:0.5pt solid #000; padding:3px; width:45px;">Código</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:65px;">CI</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:170px;">Nombre y Apellidos</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:22px;">Cat.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:60px;">S. Básico</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">Deven.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">Imp. CESS</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">Ret.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">Ret Total</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">Pagado</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:65px;">Firma</th>
                </tr>
            ` : `
                <tr style="background-color:#004b87; color:#ffffff; font-weight:bold; text-align:center;">
                    <th style="border:0.5pt solid #000; padding:3px; width:35px;">Código</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:55px;">CI</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:130px;">Nombre y Apellidos</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:22px;">Cat.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:35px;">Tarf.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:28px;">Horas</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:45px;">A cobrar</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:35px;">Bon.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:45px;">Deven.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:45px;">Imp. CESS</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:40px;">Ret.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:48px;">Pagado</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:22px;">Vac.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:45px;">Tiem. Imp.</th>
                    <th style="border:0.5pt solid #000; padding:3px; width:50px;">Firma</th>
                </tr>
            `;

            pagesHtml += `
                ${breakBlock}
                <div class="Section1">
                    <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
                        <tr>
                            <td style="border:0.5pt solid #000; padding:4px; text-align:center; width:45px;">
                                ${cleanLogo ? `<img width="36" height="34" src="${cleanLogo}">` : ''}
                            </td>
                            <td style="border:0.5pt solid #000; padding:6px; font-weight:bold; font-size:10.5pt; text-align:center;">
                                MODELO SC-4-06 NOMINA - ${escapeHtml(nombreEmpresa).toUpperCase()}
                            </td>
                            <td style="border:0.5pt solid #000; padding:6px; font-weight:bold; text-align:center; width:130px; font-size:9pt;">
                                Página ${numPag} de ${paginas.length}
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
                        <tr>
                            <td style="border:0.5pt solid #000; padding:3px; font-size:8pt; width:35%;">
                                <strong>Tipo Nómina:</strong> ${escapeHtml(tipoNominaTexto)}
                            </td>
                            <td style="border:0.5pt solid #000; padding:3px; font-size:8pt; width:35%;">
                                <strong>No. Instrum. Pago:</strong> ${codigoMostrado}
                            </td>
                            <td rowspan="4" style="border:0.5pt solid #000; padding:5px; font-size:7.5pt; vertical-align:top; line-height:1.3;">
                                <b>REVISADO POR:</b> ${nombreRevisado}<br>
                                <b>APROBADO POR:</b> ${nombreAprobado}<br>
                                <b>ELABORADO POR:</b> ___________________<br>
                                <b>CONTABILIZADO:</b> ___________________
                            </td>
                        </tr>
						<tr>
							<td style="border:0.5pt solid #000; padding:3px; font-size:8pt;">
								<strong>Alcance:</strong> ${escapeHtml(scopeText)}
							</td>
							<td style="border:0.5pt solid #000; padding:3px; font-size:8pt;">
								<strong>${esBono ? 'Monto a Distribuir: ' + (montoDistribuidoGlobal > 0 ? '$' + montoDistribuidoGlobal.toFixed(2) : '(Hasta que se contabilice)') : 'REEUP: ' + escapeHtml(reeup)}</strong><br>
								<strong>NIT:</strong> ${escapeHtml(nitEmpresa)}
							</td>
						</tr>
                        <tr>
                            <td style="border:0.5pt solid #000; padding:3px; font-size:8pt;">
                                <strong>Período:</strong> ${periodoTexto}
                            </td>
                            <td style="border:0.5pt solid #000; padding:3px; font-size:8pt;">
                                <strong>MES / AÑO:</strong> ${escapeHtml(nombreMesGlobal)} / ${escapeHtml(anioGlobal)}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border:0.5pt solid #000; padding:3px; font-size:8pt;">
                                <strong>Fecha Emisión:</strong> ${fechaActual12h} ${horaActual12h}
                            </td>
                        </tr>
						${observacionesCierreGlobal ? `
                    <tr>
                        <td colspan="3" style="border:0.5pt solid #000; padding:4px; font-size:8pt; background-color: #f8fafc;">
                            <strong>Observaciones de Cierre:</strong> ${escapeHtml(observacionesCierreGlobal)}
                        </td>
                    </tr>` : ''}
                    </table>

                    <table style="width:100%; border-collapse:collapse; font-size:7.5pt; table-layout:fixed;">
                        <thead>
                            ${headerTR}
                        </thead>
                        <tbody>
                            ${cuerpoHtml}
                        </tbody>
                    </table>
                </div>`;
        });

        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <title>Nómina SC-4-06</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>95</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                @page Section1 {
                    size: 11.0in 8.5in;
                    margin: 0.35in 0.35in 0.35in 0.35in;
                    mso-header-margin: 0.25in;
                    mso-footer-margin: 0.25in;
                    mso-paper-source: 0;
                }
                div.Section1 { page: Section1; }
                body { font-family: Arial, sans-serif; font-size: 8pt; color: #000000; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 5px; }
                th, td { border: 0.5pt solid #000000; padding: 2px 3px; font-size: 7.5pt; vertical-align: middle; line-height: 110%; }
                .text-center { text-align: center; }
                .text-left { text-align: left; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <div class="Section1">
                ${pagesHtml}
            </div>
        </body>
        </html>`;

        let blob = new Blob(['\ufeff' + html], { type: 'application/msword' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = $.trim(nombreArchivo);
        link.click();
        URL.revokeObjectURL(link.href);

        Swal.close();
        Swal.fire({
            title: '¡Completado!',
            text: 'El reporte de Word oficial se ha descargado correctamente.',
            icon: 'success',
            timer: 1800,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }, 800);
}
// FUNCIÓN AGREGADA: Genera el formato de texto alineado para la exportación a TXT
function generarContenidoTXT(trabajadores) {
    var lines = [];
    
    // ============================================================
    // ENCABEZADO PRINCIPAL
    // ============================================================
    lines.push("==========================================================================");
    lines.push("REPORTE OFICIAL DE NÓMINA - " + nombreEmpresa.toUpperCase());
    lines.push("TIPO DE NÓMINA: " + tipoNominaTexto.toUpperCase());
    lines.push("PERÍODO: " + periodoTexto.toUpperCase());
    lines.push("NÚMERO NÓMINA: " + (numeroNomina === 'Borrador' ? 'Borrador' : numeroNomina));
    
    // 🔽 NUEVO: Mostrar Monto a Distribuir si es BONO
    var esBono = (tipoNomina === 'bono');
    var esAjuste = (tipoNomina === 'ajuste');
    var mostrarConcepto = esBono || esAjuste;
    if (esBono) {
        if (montoDistribuidoGlobal > 0) {
            lines.push("MONTO A DISTRIBUIR: $" + montoDistribuidoGlobal.toFixed(2));
        } else {
            lines.push("MONTO A DISTRIBUIR: (Hasta que se contabilice)");
        }
    }
    
    lines.push("FECHA GENERACIÓN: " + new Date().toLocaleString('es-ES'));
    lines.push("==========================================================================");
    lines.push("");
    
    // ============================================================
    // ENCABEZADOS DE COLUMNAS
    // ============================================================
    var header;
    if (esBono) {
        header = padRight("COD", 6) + " | " + 
                 padRight("CI", 11) + " | " + 
                 padRight("NOMBRE Y APELLIDOS", 30) + " | " + 
                 padLeft("MONTO BONO", 14) + " | " + 
                 padLeft("NETO", 14);
    } else {
        header = padRight("COD", 6) + " | " + 
                 padRight("CI", 11) + " | " + 
                 padRight("NOMBRE Y APELLIDOS", 30) + " | " + 
                 padLeft("DEVENGADO", 14) + " | " + 
                 padLeft("DEDUCC.", 14) + " | " + 
                 padLeft("NETO", 14);
    }
    
    lines.push(header);
    lines.push("-".repeat(header.length));
    
    // ============================================================
    // DATOS DE TRABAJADORES
    // ============================================================
    var totalDev = 0;
    var totalDed = 0;
    var totalNet = 0;
    var totalBono = 0;

    trabajadores.forEach(function(t) {
        totalDev += t.devengado || 0;
        totalDed += t.retenciones || 0;
        totalNet += t.pagado || 0;
        totalBono += t.bono || 0;

        var nombreTruncado = t.nombre.substring(0, 30);
        
        var line;
        if (esBono) {
            line = padRight(t.codigo, 6) + " | " + 
                   padRight(t.ci, 11) + " | " + 
                   padRight(nombreTruncado, 30) + " | " + 
                   padLeft("$" + (t.bono || 0).toFixed(2), 14) + " | " + 
                   padLeft("$" + (t.pagado || 0).toFixed(2), 14);
        } else {
            line = padRight(t.codigo, 6) + " | " + 
                   padRight(t.ci, 11) + " | " + 
                   padRight(nombreTruncado, 30) + " | " + 
                   padLeft("$" + t.devengado.toFixed(2), 14) + " | " + 
                   padLeft("$" + t.retenciones.toFixed(2), 14) + " | " + 
                   padLeft("$" + t.pagado.toFixed(2), 14);
        }
        lines.push(line);
        // 🔽 NUEVO: Observación/concepto debajo de cada trabajador (BONO y AJUSTE)
        if (mostrarConcepto) {
            lines.push("    Observación: " + (t.concepto || 'Sin concepto'));
        }
    });
    
    // ============================================================
    // LÍNEA SEPARADORA
    // ============================================================
    lines.push("-".repeat(header.length));
    
    // ============================================================
    // TOTALES
    // ============================================================
    var totalLine;
    if (esBono) {
        totalLine = padRight("TOTAL", 6) + " | " + 
                    padRight("", 11) + " | " + 
                    padRight("TOTALES GENERALES", 30) + " | " + 
                    padLeft("$" + totalBono.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalNet.toFixed(2), 14);
    } else {
        totalLine = padRight("TOTAL", 6) + " | " + 
                    padRight("", 11) + " | " + 
                    padRight("TOTALES GENERALES", 30) + " | " + 
                    padLeft("$" + totalDev.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalDed.toFixed(2), 14) + " | " + 
                    padLeft("$" + totalNet.toFixed(2), 14);
    }
    lines.push(totalLine);
    lines.push("==========================================================================");
    
    // ============================================================
    // PIE DE PÁGINA CON FIRMAS (SOLO PARA BONO CONTABILIZADO)
    // ============================================================
    if (esBono && montoDistribuidoGlobal > 0) {
        lines.push("");
        lines.push("FIRMAS DE RESPONSABILIDAD:");
        lines.push("-".repeat(50));
        lines.push("REVISADO POR:  " + (typeof especialistaGestion !== 'undefined' ? especialistaGestion.toUpperCase() : '___________________'));
        lines.push("APROBADO POR:  " + (typeof jefeProyecto !== 'undefined' ? jefeProyecto.toUpperCase() : '___________________'));
        lines.push("ELABORADO POR: ___________________");
        lines.push("CONTABILIZADO: ___________________");
        lines.push("==========================================================================");
    }

    return lines.join("\r\n");

    // ============================================================
    // FUNCIONES AUXILIARES PARA ALINEACIÓN
    // ============================================================
    function padRight(str, length) {
        str = (str || "").toString();
        return str.length >= length ? str.substring(0, length) : str + " ".repeat(length - str.length);
    }
    
    function padLeft(str, length) {
        str = (str || "").toString();
        return str.length >= length ? str.substring(0, length) : " ".repeat(length - str.length) + str;
    }
}


</script>

<!-- MODAL OBLIGATORIO PARA DESCRIPCIÓN ANTES DE CONTABILIZAR -->
<div class="modal fade" id="modalDescripcionContabilizar" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-alt fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Contabilizar Nómina</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Ingrese una descripción para esta nómina</p>
                    </div>
                </div>
            </div>
            <form method="POST" id="formContabilizarConDescripcion">
                <div class="modal-body" style="padding: 24px;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-pen-alt me-2" style="color: #f59e0b;"></i>
                            Descripción / Observaciones
                            <span class="text-danger">*</span>
                        </label>
                        <textarea name="observaciones_cierre" id="observacionesCierre" class="form-control" rows="4" 
                                  placeholder="Ej: Nómina correspondiente al mes de enero 2026..."
                                  style="background: rgba(20,20,30,0.8); border: 1px solid rgba(255,255,255,0.15); color: white; border-radius: 12px; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1); padding: 20px 24px;">
                    <button type="button" class="btn-win" id="btnCancelarContabilizar" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn-win-warning" id="btnConfirmarContabilizar" disabled>
                        <i class="fas fa-lock me-2"></i>Contabilizar
                    </button>
                </div>
                <input type="hidden" name="contabilizar_nomina" value="1">
                <input type="hidden" name="tipo_nomina" value="<?php echo $tipo_nomina_activa; ?>">
            </form>
        </div>
    </div>
</div>

<!-- Modal Selección Tipo de Descuento para Nómina Automática -->
<div class="modal fade" id="modalSeleccionDescuento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-percent fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Seleccionar Tipo de Descuento</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Elija el método de cálculo para la nómina</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionTotalDescuentos" style="cursor: pointer; padding: 20px; border-radius: 16px; background: rgba(28, 28, 35, 0.6); border: 2px solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-chart-line fa-2x" style="color: #f59e0b;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Total de Descuentos por rangos</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Aplica descuentos progresivos del 3%, 5%, 7.5% hasta 50% según rangos de ingreso</p>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-chevron-right" style="color: #60a5fa;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionSoloCess" style="cursor: pointer; padding: 20px; border-radius: 16px; background: rgba(28, 28, 35, 0.6); border: 2px solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-shield-alt fa-2x" style="color: #10b981;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Parcial de descuentos por Rangos (PDL SOLO CESS)</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Solo aplica Contribución a la Seguridad Social: 5% hasta 15,000 CUP, 10% sobre el exceso</p>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-chevron-right" style="color: #60a5fa;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert-dark" id="infoCess" style="display: none; margin-top: 20px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 12px; padding: 12px;">
                    <i class="fas fa-info-circle me-2" style="color: #10b981;"></i>
                    <strong style="color: #10b981;">CESS progresivo:</strong>
                    <ul class="mt-2 mb-0 small" style="color: rgba(255,255,255,0.7);">
                        <li>Salario hasta 15,000 CUP → <strong>5%</strong></li>
                        <li>Exceso sobre 15,000 CUP → <strong>10%</strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px;">
                <button type="button" class="btn-win" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-win-primary" id="btnConfirmarTipoDescuento" disabled>
                    <i class="fas fa-play me-2"></i>Generar Nómina
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDICIÓN RÁPIDA - MODIFICADO -->
<div class="modal fade" id="modalEdicionRapida" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="flex-wrap: wrap;">
                <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-edit me-2"></i>
                        <h5 class="modal-title me-3">Editar registro</h5>
                        <span class="badge" id="modalRegistroContador" style="font-size: 0.75rem; background-color: rgba(255,255,255,0.12) !important; border: 1px solid rgba(255,255,255,0.1); color: #60a5fa;">
                            Cargando...
                        </span>
                    </div>
                    
                    <!-- NUEVO: Combo editable para buscar trabajador -->
                    <div class="buscador-trabajador-modal" style="min-width: 280px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="background: rgba(96, 165, 250, 0.15); border: 1px solid rgba(96, 165, 250, 0.3); color: #60a5fa;">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   id="buscadorTrabajadorModal" 
                                   class="form-control" 
                                   placeholder="Buscar por nombre, código o CI..."
                                   autocomplete="off"
                                   style="background: rgba(20,20,30,0.9); border: 1px solid rgba(96, 165, 250, 0.3); color: white;">
                            <button class="btn btn-sm" type="button" id="limpiarBuscadorModal" style="background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="resultadosBusquedaModal" class="dropdown-menu" style="width: 100%; max-height: 300px; overflow-y: auto; display: none; background: #1e1e2e; border: 1px solid rgba(96, 165, 250, 0.3);">
                            <!-- Resultados dinámicos -->
                        </div>
                    </div>
                    
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-body" id="modalEdicionBody">
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <div id="modalContabilizadaWarning" style="display: none;">
                        <span class="blink-text">
                            <i class="fas fa-lock me-2"></i> 
                            <strong>⚠️ NÓMINA CONTABILIZADA ⚠️</strong>
                            <i class="fas fa-lock ms-2"></i>
                        </span>
                    </div>
                    <button type="button" class="btn-win" id="btnModalReset" style="display: none;"><i class="fas fa-undo-alt me-2"></i>Restablecer</button>
                </div>
                <button type="button" class="btn-win" id="btnModalPrimero"><i class="fas fa-angle-double-left me-2"></i>Primero</button>
                <button type="button" class="btn-win" id="btnModalAnterior"><i class="fas fa-chevron-left me-2"></i>Anterior</button>
                <button type="button" class="btn-win" id="btnModalSiguiente"><i class="fas fa-chevron-right me-2"></i>Siguiente</button>
                <button type="button" class="btn-win" id="btnModalUltimo"><i class="fas fa-angle-double-right me-2"></i>Último</button>
                <button type="button" class="btn-win-primary" id="btnModalActualizar" style="display: none;"><i class="fas fa-save me-2"></i>Actualizar</button>
            </div>
        </div>
    </div>
</div>

<script>
let tipoDescuentoSeleccionado = null;

$('#btnGenerarAutomaticaModal').on('click', function() {
    tipoDescuentoSeleccionado = null;
    $('#opcionTotalDescuentos').removeClass('selected');
    $('#opcionSoloCess').removeClass('selected');
    $('#btnConfirmarTipoDescuento').prop('disabled', true);
    
    // CORRECCIÓN: Evitar duplicar la instancia del modal
    var modalEl = document.getElementById('modalSeleccionDescuento');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
});

$('#opcionTotalDescuentos').on('click', function() {
    $('#opcionTotalDescuentos').addClass('selected');
    $('#opcionSoloCess').removeClass('selected');
    tipoDescuentoSeleccionado = 'total_rangos';
    $('#btnConfirmarTipoDescuento').prop('disabled', false);
});

$('#opcionSoloCess').on('click', function() {
    $('#opcionSoloCess').addClass('selected');
    $('#opcionTotalDescuentos').removeClass('selected');
    tipoDescuentoSeleccionado = 'solo_cess';
    $('#btnConfirmarTipoDescuento').prop('disabled', false);
});

$('#btnConfirmarTipoDescuento').on('click', function() {
    if (!tipoDescuentoSeleccionado) return;
    
    $('#tipoDiscountHidden').val(tipoDescuentoSeleccionado);
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-spin me-2"></i> Generando...',
        text: 'Procesando cálculos de nómina automática, espere por favor.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    $('#formGenerarAutomatica').submit();
});

function analizarCubanCI(ci) {
    if (!ci) return { sexo: 'S/D', edad: 'S/D', fechaNac: 'S/D' };
    
    var ciStr = ci.toString().replace(/[\s-]/g, '');
    
    if (!/^\d{11}$/.test(ciStr)) {
        return { sexo: 'S/D', edad: 'S/D', fechaNac: 'S/D' };
    }
    
    var año = ciStr.substring(0, 2);
    var mes = ciStr.substring(2, 4);
    var dia = ciStr.substring(4, 6);
    
    var añoInt = parseInt(año);
    var añoCompleto = añoInt < 30 ? 2000 + añoInt : 1900 + añoInt;
    
    var digitoGenero = parseInt(ciStr.charAt(9));
    var sexo = (digitoGenero % 2 === 0) ? 'Masculino' : 'Femenino';
    
    var fechaNac = new Date(añoCompleto, parseInt(mes) - 1, parseInt(dia));
    var hoy = new Date();
    
    var edad = hoy.getFullYear() - fechaNac.getFullYear();
    var difMes = hoy.getMonth() - fechaNac.getMonth();
    if (difMes < 0 || (difMes === 0 && hoy.getDate() < fechaNac.getDate())) {
        pageName = edad--;
    }
    
    var fechaNacFormateada = dia + '/' + mes + '/' + añoCompleto;
    
    return { sexo: sexo, edad: edad, fechaNac: fechaNacFormateada };
}
// [SOLUCIÓN 2] Obtener índices reales de columnas .col-impuestos-det desde el cuerpo de la tabla
function obtenerIndicesColumnasImpuestos() {
    var indices = [];
    var $firstRow = $('#tablaNominas tbody tr:first');
    if ($firstRow.length) {
        $firstRow.find('td').each(function(idx) {
            if ($(this).hasClass('col-impuestos-det')) {
                indices.push(idx);
            }
        });
    }
    return indices;
}

function ajustarColumnasPorTipoDescuento(apiInstance) {
    var api = apiInstance || nominasTable;
    if (!api) return;
    
    var esSoloCess = (activeTipoDescuento === 'solo_cess');
    var indicesImpuestos = obtenerIndicesColumnasImpuestos();
    
    // Mostrar/ocultar usando los índices reales
    indicesImpuestos.forEach(function(idx) {
        api.column(idx).visible(!esSoloCess, false);
    });
    
    // Ajustar cabeceras agrupadas
    if (esSoloCess) {
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS<br>Prog.');
        $('.col-impuestos').hide();
    } else {
        $('#tablaNominas thead tr:first th.col-cess-header').html('CESS.<br>Hasta10%');
        $('.col-impuestos').show();
    }
    
    api.columns.adjust();
    if (typeof api.fixedColumns === 'function') {
        api.fixedColumns().update();
    }

}

document.addEventListener('DOMContentLoaded', function() {
    // Selecciona todos los botones de cerrar dentro de las alertas
    var closeButtons = document.querySelectorAll('.alert .btn-close');
    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // Espera un momento para que Bootstrap oculte la alerta y luego limpia la URL
            setTimeout(function() {
                // Elimina los parámetros de la URL (sin recargar la página)
                var url = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, url);
            }, 150);
        });
    });
});
// Manejar la selección interactiva de tarjetas de impresión
$(document).on('click', '.print-option-card', function() {
    $('.print-option-card').removeClass('selected');
    $(this).addClass('selected');
    
    // Obtener el ID del radio button real asociado y marcarlo
    let targetRadioId = $(this).data('target-radio');
    $('#' + targetRadioId).prop('checked', true).trigger('change');
});

// Limpiar la selección visual al cerrar o restablecer el modal
$('#modalOpcionesImpresion').on('hidden.bs.modal', function () {
    $('.print-option-card').removeClass('selected');
    $('.print-option-card[data-target-radio="alcanceGeneral"]').addClass('selected');
    $('#alcanceGeneral').prop('checked', true);
    $('#selectoresDinamicos').hide();
    $('#contenedorSelectores').empty();
});
// Escuchar la escritura en el cuadro de búsqueda del modal
$(document).on('input', '#buscarEnSelector', function() {
    let term = $(this).val().toLowerCase().trim();
    let dataCache = window.currentFilterData.datos;
    let alcance = window.currentFilterData.alcance;
    
    let htmlOptions = `<option value="" selected>-- Todos --</option>`;
    let coinciden = 0;

    dataCache.forEach(item => {
        let label = obtenerLabelPorAlcance(item, alcance);
        if (label.toLowerCase().includes(term)) {
            let value = item.id;
            htmlOptions += `<option value="${value}">${label}</option>`;
            coinciden++;
        }
    });

    $('#selectImpresion').html(htmlOptions);
    $('#badgeResultadosCount').text(`${coinciden} encontrados`);
});

// Selector personalizado de tipo de nómina con iconos

// =========================================================================
// FUNCIÓN PARA GENERAR TIRILLAS DE PAGO (CON TIPO DE NÓMINA)
// =========================================================================
function generarTirillasPago(trabajadores) {
    // Ordenar trabajadores alfabéticamente por nombre
    trabajadores.sort((a, b) => a.nombre.localeCompare(b.nombre));
    
    // Obtener mes, año y tipo de nómina desde las variables globales
    const mesAnio = `${nombreMesGlobal}/${anioGlobal}`;
    const tipoNomina = window.tipoNominaTexto || 'Nómina';
    const tipoNominaActiva = '<?php echo $tipo_nomina_activa; ?>';
    
    let filasHtml = '';
    
    trabajadores.forEach((t) => {
        // Obtención de días y cálculo de horas de vacaciones acumuladas en la ficha del trabajador
        const diasAcumulados = t.vacAcumDias || 0;
        const horasVacaciones = diasAcumulados * 8; // 8 horas laborables por cada día acumulado

        // Estructura informativa inferior de vacaciones (se ejecuta para automática o vacaciones)
        let infoVacacionesHtml = '';
        if (tipoNominaActiva === 'automatica' || tipoNominaActiva === 'vacaciones') {
            infoVacacionesHtml = `
                <div class="info-vacaciones-tirilla" style="margin-top: 5px; font-size: 8.5pt; text-align: left; padding: 4px 6px; background-color: #f9f9f9; border: 1px solid #000; border-top: none; line-height: 1.4;">
                    <strong>Vacaciones Acumuladas:</strong> ${diasAcumulados.toFixed(2)} días (${horasVacaciones.toFixed(2)} horas)
                    ${t.tiempoImp > 0 ? ` | <strong>Importe:</strong> $${t.tiempoImp.toFixed(2)}` : ''}
                </div>
            `;
        }

        filasHtml += `
            <div class="tirilla">
                <div class="header-tirilla">
                    ${escapeHtml(nombreEmpresa)} - NOTIFICACION DE PAGO - <span style="color:red;">${mesAnio}</span> - ${escapeHtml(t.ci)}  <span style="color:red;">${escapeHtml(t.nombre)}</span> (${tipoNomina})
                </div>
                <table class="tabla-tirilla">
                    <thead>
                        <tr>
                            <th class="centrado">TARIFA</th>
                            <th class="centrado">HRS</th>
                            <th class="derecha">A COBRAR</th>
                            <th class="derecha">BONO</th>
                            <th class="derecha">$/Feriad.</th>
                            <th class="derecha">DEVENG</th>
                            <th class="derecha">CESS</th>
                            <th class="derecha">RET</th>
                            <th class="derecha">PAGADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="centrado">$${t.tarifaSal.toFixed(2)}</td>
                            <td class="centrado">${t.horas}</td>
                            <td class="derecha">$${t.aCobrar.toFixed(2)}</td>
                            <td class="derecha">$${t.bono.toFixed(2)}</td>
                            <td class="derecha">$${(t.feriadoImp || 0).toFixed(2)}</td>
                            <td class="derecha">$${t.devengado.toFixed(2)}</td>
                            <td class="derecha">$${t.impS.toFixed(2)}</td>
                            <td class="derecha">$${t.retenciones.toFixed(2)}</td>
                            <td class="derecha">
								<span style="color:red;">$${t.pagado.toFixed(2)}</span>
							</td>
                        </tr>
                    </tbody>
                </table>
                ${infoVacacionesHtml}
                <div class="linea-corte">════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════</div>
            </div>
        `;
    });
    
    // Construir el HTML completo
    const htmlCompleto = `<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Tirillas de Pago - ${escapeHtml(nombreEmpresa)}</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Arial', 'Helvetica', sans-serif;
                font-size: 12pt;
                background: white;
                margin: 5px;
                padding: 3px;
            }
            .tirilla {
                width: 100%;
                margin: 0;
                padding: 0;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .header-tirilla {
                font-weight: bold;
                font-size: 10pt;
                margin: 3px 0;
                padding: 3px 0;
                background-color: #e8e8e8;
                text-align: center;
                border: 1px solid #aaa;
            }
            .linea-corte {
                font-family: monospace;
                font-size: 8pt;
                font-weight: bold;
                color: #000;
                letter-spacing: 0;
                margin: 2px 0;
                white-space: pre;
                overflow-x: hidden;
                line-height: 1;
                background-color: #fff;
            }
            .tabla-tirilla {
                width: 100%;
                border-collapse: collapse;
                margin: 2px 0;
                font-size: 10pt;
            }
            .tabla-tirilla th {
                background-color: #e8e8e8;
                font-weight: bold;
                padding: 4px 5px;
                border: 1px solid #000;
            }
            .tabla-tirilla td {
                padding: 4px 5px;
                border: 1px solid #000;
            }
            .centrado {
                text-align: center;
            }
            .derecha {
                text-align: right;
            }
            @media print {
                body {
                    margin: 0;
                    padding: 2px;
                }
                .header-tirilla {
                    background-color: #ddd;
                    border: 1px solid #000;
                }
                .tabla-tirilla th {
                    background-color: #ddd;
                }
                .linea-corte {
                    color: #000;
                }
            }
        </style>
    </head>
    <body>
        ${filasHtml}
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                }, 200);
            }
        <\/script>
    </body>
    </html>`;
    
    const ventana = window.open('', '_blank');
    if (ventana) {
        ventana.document.write(htmlCompleto);
        ventana.document.close();
    } else {
        Swal.fire({
            title: 'Error',
            text: 'No se pudo abrir la ventana de impresión.',
            icon: 'error',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }
}



</script>
<!-- MODAL OPCIONES DE IMPRESIÓN MEJORADO (CON TIRILLAS DE PAGO) -->
<div class="modal fade" id="modalOpcionesImpresion" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-modern" style="border: 1px solid rgba(255,255,255,0.08); background: rgba(30,30,38,0.98); backdrop-filter: blur(20px);">
            
            <!-- Cabecera Mejorada -->
            <div class="modal-header px-4 py-3" style="border-bottom: 1px solid rgba(255,255,255,0.08); background: linear-gradient(135deg, rgba(30,64,175,0.2) 0%, rgba(37,99,235,0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #1e3a8a, #1e40af); border-radius: 14px;">
                        <i class="fas fa-print fa-lg text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color: #ffffff; font-weight: 700; font-size: 1.2rem;">Impresión de Nómina Oficial</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.6);">Modelo SC-4-06 - Seleccione alcance y opciones de agrupación</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body px-4 py-4">
                <!-- Información de la nómina actual -->
                <div class="info-nomina-bar mb-4 p-3 rounded" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 12px;">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Tipo de Nómina</small>
                            <strong class="text-info"><?php echo htmlspecialchars($tipos_nomina[$tipo_nomina_activa]['nombre']); ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Período</small>
                            <strong class="text-success"><?php echo $nombre_mes . ' ' . $anio; ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Número de Nómina</small>
                            <strong class="text-warning"><?php echo htmlspecialchars($num_nomina_actual); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Sección de Tarjetas de Alcance -->
                <div class="mb-4">
                    <label class="form-label d-block fw-semibold mb-3" style="color: rgba(255,255,255,0.85); font-size: 0.85rem;">
                        <i class="fas fa-sliders-h me-2 text-info"></i>Alcance del Reporte (Agrupación)
                    </label>
                    
                    <!-- Radio buttons ocultos -->
                    <div class="d-none">
                        <input type="radio" name="alcanceImpresion" id="alcanceGeneral" value="general" checked>
                        <input type="radio" name="alcanceImpresion" id="alcanceCargo" value="cargo">
                        <input type="radio" name="alcanceImpresion" id="alcanceArea" value="area">
                        <input type="radio" name="alcanceImpresion" id="alcanceCentro" value="centro_costo">
                        <input type="radio" name="alcanceImpresion" id="alcanceCategoria" value="categoria">
                        <input type="radio" name="alcanceImpresion" id="alcanceEscala" value="escala">
                        <input type="radio" name="alcanceImpresion" id="alcanceContrato" value="tipo_contrato">
                        <!-- NUEVO: Tirillas de Pago -->
                        <input type="radio" name="alcanceImpresion" id="alcanceTirillas" value="tirillas">
                    </div>

                    <!-- Tarjetas visuales -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="print-option-card selected" data-target-radio="alcanceGeneral">
                                <div class="card-icon"><i class="fas fa-globe-americas"></i></div>
                                <div class="card-text">
                                    <div class="card-title">General</div>
                                    <div class="card-desc">Toda la nómina sin filtrar</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCargo">
                                <div class="card-icon"><i class="fas fa-briefcase"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Por Cargo</div>
                                    <div class="card-desc">Agrupado por cargo del trabajador</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceArea">
                                <div class="card-icon"><i class="fas fa-building"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Por Área</div>
                                    <div class="card-desc">Agrupado por área de trabajo</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCentro">
                                <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Centro de Costo</div>
                                    <div class="card-desc">Por centro de costo asignado</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCategoria">
                                <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Categoría Ocupacional</div>
                                    <div class="card-desc">Por categoría del trabajador</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceEscala">
                                <div class="card-icon"><i class="fas fa-layer-group"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Escala Salarial</div>
                                    <div class="card-desc">Por grupo salarial (I-VI)</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceContrato">
                                <div class="card-icon"><i class="fas fa-file-signature"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Tipo de Contrato</div>
                                    <div class="card-desc">Por tipo de relación laboral</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <!-- NUEVA TARJETA: Tirillas de Pago -->
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceTirillas">
                                <div class="card-icon"><i class="fas fa-receipt"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Tirillas de Pago</div>
                                    <div class="card-desc">Para recortar y dar a los trabajadores. NOTIFICACIÓN DE PRÓXIMO PAGO.</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenedor del Selector Dinámico (solo para alcances que requieren filtro) -->
                <div id="selectoresDinamicos" style="display: none;">
                    <div class="mt-3 p-3 rounded" style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px;">
                        <div id="contenedorSelectores"></div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer px-4 py-3" style="border-top: 1px solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win" data-bs-dismiss="modal" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                
                <!-- Dropdown de acciones -->
                <div class="dropdown">
                    <button class="btn-nomina-impresa dropdown-toggle" type="button" data-bs-toggle="dropdown" style="padding: 10px 24px;">
                        <i class="fas fa-print me-2"></i> Opciones de Impresión
                    </button>
                    <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                        <li><a class="dropdown-item opt-impresion" href="#" data-action="imprimir"><i class="fas fa-print text-warning me-2"></i> Generar Nómina Impresa</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item opt-impresion" href="#" data-action="pdf"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar PDF Oficial (SC-4-06)</a></li>
                        <li><a class="dropdown-item opt-impresion" href="#" data-action="excel"><i class="fas fa-file-excel text-success me-2"></i> Exportar Excel Oficial (SC-4-06)</a></li>
                        <li><a class="dropdown-item opt-impresion" href="#" data-action="word"><i class="fas fa-file-word text-primary me-2"></i> Exportar Word Oficial (SC-4-06)</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>



</div>
<!-- MODAL UNIFICADO DE EXPORTACIÓN Y REPORTES -->
<div class="modal fade" id="modalExportarReportes" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-modern" style="border: 1px solid rgba(255,255,255,0.08); background: rgba(30,30,38,0.98); backdrop-filter: blur(20px);">
            
            <!-- Cabecera -->
            <div class="modal-header px-4 py-3" style="border-bottom: 1px solid rgba(255,255,255,0.08); background: linear-gradient(135deg, rgba(30,64,175,0.2) 0%, rgba(37,99,235,0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #1e3a8a, #1e40af); border-radius: 14px;">
                        <i class="fas fa-file-export fa-lg text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color: #ffffff; font-weight: 700; font-size: 1.2rem;">Centro de Exportación y Reportes</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.6);">Seleccione el formato oficial de salida para el procesamiento de datos</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <!-- Opción 1: Nómina Impresa Oficial -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="imprimir_oficial">
                            <div class="card-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1);"><i class="fas fa-print"></i></div>
                            <div class="card-text">
                                <div class="card-title">Nómina Impresa Oficial</div>
                                <div class="card-desc">Vista previa, firmas y formato físico SC-4-06</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 2: PDF -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="pdf_oficial">
                            <div class="card-icon" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);"><i class="fas fa-file-pdf"></i></div>
                            <div class="card-text">
                                <div class="card-title">Documento PDF Oficial</div>
                                <div class="card-desc">Exportación directa en formato Carta horizontal</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 3: Excel -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="excel_oficial">
                            <div class="card-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1);"><i class="fas fa-file-excel"></i></div>
                            <div class="card-text">
                                <div class="card-title">Libro de Excel (.XLS)</div>
                                <div class="card-desc">Hoja de cálculo estructurada con fórmulas base</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 4: Word -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="word_oficial">
                            <div class="card-icon" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1);"><i class="fas fa-file-word"></i></div>
                            <div class="card-text">
                                <div class="card-title">Documento Word (.DOC)</div>
                                <div class="card-desc">Esquema de procesamiento de textos paginado</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 5: CSV -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="csv_delimitado">
                            <div class="card-icon" style="color: #06b6d4; background: rgba(6, 182, 212, 0.1);"><i class="fas fa-file-csv"></i></div>
                            <div class="card-text">
                                <div class="card-title">Valores Separados por Comas</div>
                                <div class="card-desc">Archivo plano (.CSV) para intercambio de sistemas</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 6: Copiar Datos -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="copiar_portapapeles">
                            <div class="card-icon" style="color: #a855f7; background: rgba(168, 85, 247, 0.1);"><i class="fas fa-copy"></i></div>
                            <div class="card-text">
                                <div class="card-title">Copiar al Portapapeles</div>
                                <div class="card-desc">Copia temporal en memoria de los registros visibles</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer px-4 py-3" style="border-top: 1px solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win" data-bs-dismiss="modal" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-times me-2"></i>Cerrar Panel
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Ajuste -->
<div class="modal fade" id="modalAjuste" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Generar Nómina de Ajuste</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formAjuste">
                <input type="hidden" name="tipo_descuento_ajuste" id="tipoDescuentoAjuste" value="total_rangos">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterAjusteArea" class="form-select form-select-sm filter-modal-worker" data-modal="ajuste">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
                                    <select id="filterAjusteCC" class="form-select form-select-sm filter-modal-worker" data-modal="ajuste">
                                        <option value="">-- Todos --</option>
                                        <?php foreach ($all_centros as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <label class="form-label"><i class="fas fa-search me-1"></i> Buscar trabajador</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchAjusteWorker" placeholder="Nombre..." autocomplete="off">
                            </div>
                            <div class="worker-list-container" id="ajusteWorkerList"></div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label"><i class="fas fa-list me-1"></i> Trabajadores seleccionados</label>
                            <div id="ajusteSelectedList" class="selected-workers-container" style="min-height: 180px;"></div>
                            <div class="mb-3">
                                <label class="form-label">Concepto del Ajuste</label>
                                <input type="text" name="concepto_ajuste" id="conceptoAjuste" class="form-control-custom w-100" required placeholder="Ej: Ajuste por omisión de trabajador">
                            </div>
                            <div id="previewAjuste" class="preview-card" style="display: none;">
                                <h6><i class="fas fa-chart-line me-2"></i>Resumen</h6>
                                <div class="info-row"><span>Trabajadores:</span><span id="previewAjusteCount">0</span></div>
                                <div class="info-row"><span>Monto Total:</span><span class="info-value-success" id="previewAjusteTotal">$0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_ajuste" class="btn-win-primary" id="btnGenerarAjuste"><i class="fas fa-save me-2"></i>Generar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL BASE COMPLETA DE MONTOS CON FILTRO POR AÑOS -->
<style>
/* Para que el combo se vea más moderno */
#filtroAnio:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

#filtroAnio option {
    background: #1e1e26;
    color: #fff;
    padding: 6px;
}

#filtroAnio option:hover {
    background: #6366f1;
}
</style>
<div class="modal fade" id="modalFullHistorial" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-modern" style="background: rgba(30,30,38,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); position: relative;">
            
            <!-- BOTÓN X FLOTANTE TOP DERECHA -->
            <button type="button" class="btn-close-custom" data-bs-dismiss="modal" style="
                position: absolute;
                top: 12px;
                right: 16px;
                z-index: 1050;
                background: rgba(255,255,255,0.08);
                border: none;
                border-radius: 50%;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: rgba(255,255,255,0.5);
                font-size: 0.85rem;
                cursor: pointer;
                transition: all 0.25s ease;
                backdrop-filter: blur(4px);
                padding: 0;
            " 
            onmouseover="this.style.background='rgba(255,255,255,0.18)'; this.style.color='#fff'; this.style.transform='rotate(90deg)';"
            onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='rgba(255,255,255,0.5)'; this.style.transform='rotate(0deg)';">
                <i class="fas fa-times"></i>
            </button>

            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #a855f7); border: none; padding-right: 60px;">
                <h5 class="modal-title text-white"><i class="fas fa-database me-2"></i>Base de Datos: Montos para Distribución</h5>
            </div>

            <div class="modal-body">
                <div id="printAreaFullHistorial">
                    <div class="text-center mb-4">
                        <h4 class="text-white mb-1">Registro Histórico Completo</h4>
                        <p class="text-white-50 small">Todos los importes registrados para bonos y productividad</p>
                    </div>

                    <!-- FILTRO POR AÑOS - COMBO SELECT -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <label for="filtroAnio" class="text-white-50" style="font-size: 0.85rem; font-weight: 500;">
                                <i class="fas fa-calendar-alt me-1"></i>Filtrar por año:
                            </label>
                            <select id="filtroAnio" class="form-select form-select-sm" style="
                                background: rgba(255,255,255,0.06);
                                border: 1px solid rgba(255,255,255,0.1);
                                color: #fff;
                                border-radius: 8px;
                                padding: 4px 30px 4px 12px;
                                font-size: 0.85rem;
                                cursor: pointer;
                                min-width: 120px;
                                transition: all 0.2s ease;
                            " 
                            onmouseover="this.style.background='rgba(255,255,255,0.12)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.06)'"
                            onchange="filtrarHistorialPorAnio()">
                                <option value="todos" style="background: #1e1e26; color: #fff;">Todos los años</option>
                                <!-- Los años se llenan con JS -->
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="limpiarFiltroAnio()" style="
                            border-radius: 8px;
                            font-size: 0.75rem;
                            padding: 4px 12px;
                            border-color: rgba(255,255,255,0.15);
                            color: rgba(255,255,255,0.6);
                        " 
                        onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.color='#fff';"
                        onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.6)';">
                            <i class="fas fa-undo me-1"></i>Limpiar
                        </button>
                    </div>

                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-dark table-hover border-secondary" id="tablaFullHistorial">
                            <thead class="sticky-top" style="background: #1e1e26; z-index: 10;">
                                <tr class="text-info">
                                    <th>Año</th>
                                    <th>Mes</th>
                                    <th class="text-end">Importe Registrado ($)</th>
                                </tr>
                            </thead>
                            <tbody><!-- Se llena con JS --></tbody>
                        </table>
                    </div>
                    <div class="mt-3 p-3 rounded d-flex justify-content-between align-items-center" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <span class="text-white-50">TOTAL ACUMULADO HISTÓRICO:</span>
                        <h4 class="text-success mb-0 fw-bold" id="totalFullHistorial">$0.00</h4>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-win" data-bs-dismiss="modal"><i class="fas fa-close me-1"></i>Cerrar</button>
                <button type="button" class="btn-win-primary" id="btnImprimirFull"><i class="fas fa-print me-1"></i> Imprimir Base</button>
                <button type="button" class="btn-win-success" id="btnExportPDFFull"><i class="fas fa-file-pdf me-1"></i> Exportar PDF</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>