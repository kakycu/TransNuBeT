<?php
// modules/nominas.php - V3.2.1 (redeploy forzado) - VERSIÓN COMPLETA CON FILTRO DE CUENTA BANCARIA, REGLA DE NO ACUMULACIÓN DE VACACIONES Y SELECCIÓN DE TIPO DE DESCUENTO PARA TODAS LAS NÓMINAS
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
Hora Normal *				salario_hora × horas_normales	100×1=100
Hora Extra Diurna *			salario_hora × recargo_extra_diurna × horas		100×1.5×1=150 (configurable, default 1.5 = 150%)
Hora Nocturna (Nt 7-23h) *		salario_hora × recargo_extra_nocturna × horas_nocturnas	100×2.0×1=200 (configurable, default 2.0 = 200%)(7pm-11pm)
Hora Nocturna (Nt 23-7h) *		salario_hora × recargo_extra_nocturna × horas_nocturnas	100×2.0×1=200 (configurable, default 2.0 = 200%)(11pm-7am)
Doble Turno *				salario_hora × recargo_doble_turno × horas_doble_turno	100×2.0×1=200 (configurable, default 2.0 = 200%)
Día Feriado					salario_diario × 2 × días_feriados		800×2×1=1,600
Importe Nocturno (cálculo automático)
* Multiplicadores configurables en Configuración > Configuración General (recargo_extra_diurna, recargo_extra_nocturna, recargo_doble_turno)
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
require_once __DIR__ . '/../logger.php';
require_once '../includes/funciones.php';

// Control de acceso por rol
if (!permiso_puede('nominas', 'ver')) {
    permiso_denegar_acceso('Nóminas');
}
$puede_crear_nomina = permiso_puede('nominas', 'crear');
$puede_editar_nomina = permiso_puede('nominas', 'editar');
$puede_eliminar_nomina = permiso_puede('nominas', 'eliminar');

// Clasificadores vacíos: impiden el correcto funcionamiento del sistema y la
// generación de nóminas. Se muestran en un SweetAlert y deshabilitan los
// botones de nueva nómina (automatica, extraordinaria, vacaciones, bono, ajuste).
$clasificadores_vacios = getClasificadoresVacios($pdo);
$hay_clasificadores_vacios = !empty($clasificadores_vacios);
$nombres_clasificadores_vacios = array_map(function ($c) { return $c['nombre']; }, $clasificadores_vacios);


// Obtener el multiplicador de recargo nocturno genérico (por defecto 1.25 si no existe)
try {
    $stmt_recargo = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'recargo_nocturno'");
    $stmt_recargo->execute();
    $recargo_nocturno = floatval($stmt_recargo->fetchColumn()) ?: 1.25;
} catch (PDOException $e) {
    $recargo_nocturno = 1.25;
}

// Recargos de horas extraordinarias (PDL/MIPYME): HE diurnas 150%, HE nocturnas 200%, doble turno 200%
try {
    $stmt_re1 = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'recargo_extra_diurna'");
    $stmt_re1->execute();
    $recargo_extra_diurna = floatval($stmt_re1->fetchColumn()) ?: 1.50;
} catch (PDOException $e) {
    $recargo_extra_diurna = 1.50;
}
try {
    $stmt_re2 = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'recargo_extra_nocturna'");
    $stmt_re2->execute();
    $recargo_extra_nocturna = floatval($stmt_re2->fetchColumn()) ?: 2.00;
} catch (PDOException $e) {
    $recargo_extra_nocturna = 2.00;
}
try {
    $stmt_re3 = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'recargo_doble_turno'");
    $stmt_re3->execute();
    $recargo_doble_turno = floatval($stmt_re3->fetchColumn()) ?: 2.00;
} catch (PDOException $e) {
    $recargo_doble_turno = 2.00;
}

// Tarifas fijas de nocturnidad sector presupuestado (Resolución 15/2026 MTSS)
try {
    $stmt_t1 = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'tarifa_nocturnidad_temprana'");
    $stmt_t1->execute();
    $tarifa_nocturnidad_temprana = floatval($stmt_t1->fetchColumn()) ?: 0.60;
} catch (PDOException $e) {
    $tarifa_nocturnidad_temprana = 0.60;
}
try {
    $stmt_t2 = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'tarifa_nocturnidad_tardia'");
    $stmt_t2->execute();
    $tarifa_nocturnidad_tardia = floatval($stmt_t2->fetchColumn()) ?: 1.15;
} catch (PDOException $e) {
    $tarifa_nocturnidad_tardia = 1.15;
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
// AJAX: MESES CON DATOS PARA EL LISTADO POR TRABAJADOR
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'get_meses_listado' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $anio = intval($_GET['anio']);
    $trabajador_id = intval($_GET['trabajador_id'] ?? 0);
    $estado = $_GET['estado'] ?? '';
    $meses = [];
    
    if ($anio) {
        $sql = "SELECT DISTINCT MONTH(periodo_desde) as mes 
                FROM nominas 
                WHERE YEAR(periodo_desde) = ?";
        $params = [$anio];
        
        if ($trabajador_id > 0) {
            $sql .= " AND trabajador_id = ?";
            $params[] = $trabajador_id;
        }
        
        if ($estado != '') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }
        
        $sql .= " ORDER BY mes";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $meses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode(['success' => true, 'meses' => $meses]);
    exit;
}

// ==========================================================
// AJAX: LISTADO TOTAL SALARIO DEVENGADO POR TRABAJADOR
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'get_listado_devengado_trabajador' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $trabajador_id = intval($_GET['trabajador_id'] ?? 0);
    $anio = intval($_GET['anio'] ?? 0);
    $mes = intval($_GET['mes'] ?? 0);
    $estado = $_GET['estado'] ?? '';
    $modo = $_GET['modo'] ?? 'consolidado';
    
    if ($trabajador_id <= 0 || $anio <= 0) {
        echo json_encode(['success' => false, 'error' => 'Parámetros incompletos.']);
        exit;
    }
    
    try {
        $stmt_t = $pdo->prepare("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores WHERE id = ?");
        $stmt_t->execute([$trabajador_id]);
        $trabajador = $stmt_t->fetch();
        
        if (!$trabajador) {
            echo json_encode(['success' => false, 'error' => 'Trabajador no encontrado.']);
            exit;
        }
        
        $sql = "SELECT tipo_nomina, 
                       COUNT(*) as cantidad,
                       COALESCE(SUM(COALESCE(total_salario_devengado, 0)), 0) as devengado,
                       COALESCE(SUM(COALESCE(total_deducciones, 0)), 0) as deducciones,
                       COALESCE(SUM(COALESCE(contribucion_especial, 0)), 0) as cess,
                       COALESCE(SUM(COALESCE(importe_neto, 0)), 0) as neto";
        
        if ($modo === 'completo') {
            $sql .= ", MONTH(periodo_desde) as mes_num";
        }
        
        $sql .= " FROM nominas 
                WHERE trabajador_id = ? 
                  AND YEAR(periodo_desde) = ?";
        $params = [$trabajador_id, $anio];
        
        if ($mes > 0) {
            $sql .= " AND MONTH(periodo_desde) = ?";
            $params[] = $mes;
        }
        
        if ($estado != '') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }
        
        if ($modo === 'completo') {
            $sql .= " GROUP BY tipo_nomina, MONTH(periodo_desde) ORDER BY tipo_nomina, MONTH(periodo_desde)";
        } else {
            $sql .= " GROUP BY tipo_nomina ORDER BY tipo_nomina";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();
        
        $totales = ['cantidad' => 0, 'devengado' => 0, 'deducciones' => 0, 'cess' => 0, 'neto' => 0];
        foreach ($filas as $f) {
            $totales['cantidad'] += intval($f['cantidad']);
            $totales['devengado'] += floatval($f['devengado']);
            $totales['deducciones'] += floatval($f['deducciones']);
            $totales['cess'] += floatval($f['cess']);
            $totales['neto'] += floatval($f['neto']);
        }
        
        echo json_encode([
            'success' => true,
            'trabajador' => $trabajador,
            'filas' => $filas,
            'totales' => $totales,
            'modo' => $modo
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
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

if (isset($_POST['actualizar_nomina'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    if (!$puede_editar_nomina) {
        echo json_encode(['success' => false, 'error' => 'No tiene permisos suficientes para realizar esta operación. Contacte al administrador del sistema.', 'denied' => true]);
        exit;
    }
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
            $horas_nocturnas_tempranas = floatval($_POST['nocturnidad_temprana'] ?? $_POST['horas_nocturnas_tempranas'] ?? 0);
            $horas_nocturnas_tardias = floatval($_POST['nocturnidad_tardia'] ?? $_POST['horas_nocturnas_tardias'] ?? 0);
            $horas_doble_turno = floatval($_POST['doble_turno'] ?? $_POST['horas_doble_turno'] ?? 0);
            $horas_nocturnas = $horas_nocturnas_tempranas + $horas_nocturnas_tardias;
        }
        
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
        
        if ($tipo == 'extraordinaria') {
            $importe_he_diurnas = roundExcel($salario_hora * $recargo_extra_diurna * $horas, 2);
            $importe_noct_temprana = roundExcel($salario_hora * $recargo_extra_nocturna * $horas_nocturnas_tempranas, 2);
            $importe_noct_tardia = roundExcel($salario_hora * $recargo_extra_nocturna * $horas_nocturnas_tardias, 2);
            $importe_doble_turno = roundExcel($salario_hora * $recargo_doble_turno * $horas_doble_turno, 2);
            $importe_nocturnas = $importe_noct_temprana + $importe_noct_tardia;
            $total_devengado = roundExcel($importe_he_diurnas + $importe_nocturnas + $importe_doble_turno, 2);
            $importe_vacaciones_adicional = 0;
            $importe_feriados = 0;
        } else {
            $salario_laboral = roundExcel($salario_hora * $horas, 2);
            $valor_hora_nocturna = $salario_hora * $recargo_nocturno;
            $importe_nocturnas = 0;
            $salario_diario = $salario_mensual / $dias_mensuales_db;
            $importe_feriados = roundExcel($salario_diario * $dias_feriados * 2, 2);
            $dias_vac_proporcional = roundExcel(($horas * $factor_909_db) / $jornada_diaria_db, 2);
            $importe_vac_proporcional = roundExcel($dias_vac_proporcional * $salario_diario, 2);
            $importe_vacaciones_adicional = ($no_acumular_vacaciones == 1) ? $importe_vac_proporcional : 0;
            $total_devengado = roundExcel($salario_laboral + $importe_feriados + $otros_pagos + $importe_vacaciones_adicional, 2);
        }
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_cess_general / 100), 2);
            $impuesto = calcularImpuestoAjax($total_devengado, $rangos_impuesto);
        }
        
        $neto_antes_descuentos = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
        if ($descuentos_manuales > $neto_antes_descuentos) $descuentos_manuales = $neto_antes_descuentos;
        if ($descuentos_manuales < 0) $descuentos_manuales = 0;
        $neto_final = roundExcel($neto_antes_descuentos - $descuentos_manuales, 2);
        
        if ($tipo == 'extraordinaria') {
            $result = $pdo->prepare("UPDATE nominas SET
                horas_laboradas=?, horas_nocturnas=?, importe_horas_nocturnas=?,
                horas_nocturnas_tempranas=?, importe_nocturnas_tempranas=?,
                horas_nocturnas_tardias=?, importe_nocturnas_tardias=?,
                horas_doble_turno=?, importe_doble_turno=?,
                importe_salario_laboral=?, total_salario_devengado=?,
                descuentos=?, contribucion_especial=?, ingresos_personales=?,
                importe_neto=?, total_deducciones=?
                WHERE id=?");
            $result->execute([
                $horas, $horas_nocturnas, $importe_nocturnas,
                $horas_nocturnas_tempranas, $importe_noct_temprana,
                $horas_nocturnas_tardias, $importe_noct_tardia,
                $horas_doble_turno, $importe_doble_turno,
                $importe_he_diurnas + $importe_doble_turno,
                $total_devengado,
                $descuentos_manuales, $contribucion, $impuesto,
                $neto_final, roundExcel($contribucion + $impuesto + $descuentos_manuales, 2),
                $id
            ]);
        } else {
            $salario_laboral = roundExcel($salario_hora * $horas, 2);
            $dias_vac_proporcional = roundExcel(($horas * $factor_909_db) / $jornada_diaria_db, 2);
            $importe_vac_proporcional = roundExcel($dias_vac_proporcional * $salario_diario, 2);
            $result = $pdo->prepare("UPDATE nominas SET
                horas_laboradas=?, horas_nocturnas=?, importe_horas_nocturnas=?,
                dias_feriados=?, importe_dias_feriados=?, otros_salarios=?,
                importe_salario_laboral=?, total_salario_devengado=?,
                descuentos=?, contribucion_especial=?, ingresos_personales=?,
                importe_neto=?, total_deducciones=?,
                vacaciones_acumuladas_mes=?, importe_vacaciones_acumulado_mes=?
                WHERE id=?");
            $result->execute([
                $horas, $horas_nocturnas, $importe_nocturnas,
                $dias_feriados, $importe_feriados, $otros_pagos,
                $salario_laboral, $total_devengado,
                $descuentos_manuales, $contribucion, $impuesto,
                $neto_final, roundExcel($contribucion + $impuesto + $descuentos_manuales, 2),
                $dias_vac_proporcional, $importe_vac_proporcional,
                $id
            ]);
        }
        
        if ($result) {
            logAction('actualizar_nomina_borrador', 'nominas', 'Actualización de nómina en borrador (ID ' . $id . ', tipo ' . $tipo . ')', ['nomina_id' => (int)$id, 'tipo_nomina' => $tipo, 'total_devengado' => $total_devengado, 'importe_neto' => $neto_final], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        }
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
        if ($result) {
            logAction('actualizar_nomina_borrador', 'nominas', 'Actualización de bono en nómina en borrador (ID ' . $id . ')', ['nomina_id' => (int)$id, 'tipo_nomina' => $tipo, 'monto' => $monto, 'importe_neto' => $neto], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        }
        echo json_encode(['success' => $result, 'neto_maximo' => $neto_antes_descuentos]);
        
    } elseif ($tipo == 'ajuste') {
        $monto = floatval($_POST['monto_bono'] ?? 0);
        $descuentos = floatval($_POST['descuentos'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $otros_pagos_aj = floatval($_POST['otros_salarios'] ?? 0);
        $horas_ajuste_edit = floatval($_POST['horas_laboradas'] ?? 0);
        $importe_laboral_edit = 0;
        $dias_vac_edit = 0;
        $importe_vac_edit = 0;
        
        if ($horas_ajuste_edit > 0) {
            // Modo "por horas trabajadas": recalcular importe y acumulación de vacaciones
            $stmt_sw = $pdo->prepare("SELECT e.salario_hora_ordinaria, e.salario_mensual, t.no_acumular_vacaciones
                                      FROM nominas n
                                      JOIN trabajadores t ON n.trabajador_id = t.id
                                      JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                                      WHERE n.id = ?");
            $stmt_sw->execute([$id]);
            $worker_edit = $stmt_sw->fetch();
            $salario_hora_edit = floatval($worker_edit['salario_hora_ordinaria'] ?? 0);
            $salario_mensual_edit = floatval($worker_edit['salario_mensual'] ?? 0);
            $no_acum_edit = intval($worker_edit['no_acumular_vacaciones'] ?? 0);
            
            $importe_laboral_edit = roundExcel($salario_hora_edit * $horas_ajuste_edit, 2);
            $dias_vac_edit = roundExcel(($horas_ajuste_edit * $factor_909_db) / $jornada_diaria_db, 2);
            $importe_vac_edit = roundExcel($dias_vac_edit * ($salario_mensual_edit / $dias_mensuales_db), 2);
            $importe_vac_adicional_edit = ($no_acum_edit == 1) ? $importe_vac_edit : 0;
            $monto = roundExcel($importe_laboral_edit + $importe_vac_adicional_edit, 2);
        }
        
        if ($monto <= 0) {
            echo json_encode(['success' => false, 'error' => 'El monto del ajuste debe ser mayor a cero.']);
            exit;
        }
        
        $total_devengado = roundExcel($monto + $otros_pagos_aj, 2);
        
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
        
        $update = $pdo->prepare("UPDATE nominas SET pago_resultado=?, total_salario_devengado=?, horas_laboradas=?, importe_salario_laboral=?, vacaciones_acumuladas_mes=?, importe_vacaciones_acumulado_mes=?, otros_salarios=?, contribucion_especial=?, ingresos_personales=?, descuentos=?, importe_neto=?, total_deducciones=?, descripcion=? WHERE id=?");
        $result = $update->execute([$monto, $total_devengado, $horas_ajuste_edit, $importe_laboral_edit, $dias_vac_edit, $importe_vac_edit, $otros_pagos_aj, $contribucion, $impuesto, $descuentos, $neto, roundExcel($contribucion + $impuesto + $descuentos, 2), $descripcion, $id]);
        if ($result) {
            logAction('actualizar_nomina_borrador', 'nominas', 'Actualización de ajuste en nómina en borrador (ID ' . $id . ')', ['nomina_id' => (int)$id, 'tipo_nomina' => $tipo, 'total_devengado' => $total_devengado, 'importe_neto' => $neto], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        }
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
        $neto_antes_descuentos = roundExcel($importe - ($contribucion + $impuesto), 2);
        
        $descuentos_vac = abs(floatval($_POST['descuentos'] ?? 0));
        if ($descuentos_vac > $neto_antes_descuentos) {
            $descuentos_vac = $neto_antes_descuentos;
        }
        
        $neto = roundExcel($neto_antes_descuentos - $descuentos_vac, 2);
        $total_deducciones_vac = roundExcel($contribucion + $impuesto + $descuentos_vac, 2);
        
        $update = $pdo->prepare("UPDATE nominas SET dias_vacaciones_tomados=?, importe_vacaciones=?, total_salario_devengado=?, contribucion_especial=?, ingresos_personales=?, descuentos=?, importe_neto=?, total_deducciones=? WHERE id=?");
        $result = $update->execute([$dias, $importe, $importe, $contribucion, $impuesto, $descuentos_vac, $neto, $total_deducciones_vac, $id]);
        if ($result) {
            logAction('actualizar_nomina_borrador', 'nominas', 'Actualización de vacaciones en nómina en borrador (ID ' . $id . ')', ['nomina_id' => (int)$id, 'tipo_nomina' => $tipo, 'dias' => $dias, 'importe' => $importe, 'importe_neto' => $neto], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        }
        echo json_encode(['success' => $result, 'neto_maximo' => $neto_antes_descuentos]);
    }
    exit;
}

// ============================================
// CONFIGURACIÓN Y LÓGICA NO AJAX
// ============================================
$config_empresa = ['nombre_empresa' => COMPANY_NAME, 'jefe_proyecto' => JEFE_PROYECTO, 'especialista_gestion' => ESPECIALISTA, 'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH', 'especialista_nominas' => defined('ESPECIALISTA_NOMINAS') ? ESPECIALISTA_NOMINAS : ''];
try {
    // Se agregan 'reeup' y 'reeup_empresa' a la consulta SQL
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_gestionRRHH', 'especialista_nominas', 'reeup_empresa', 'nit_empresa')");
    while ($row = $stmt->fetch()) {
        $config_empresa[$row['parametro']] = $row['valor'];
    }
} catch (PDOException $e) {}
$nit_empresa = $config_empresa['nit_empresa'] ?? 'S/R';

// Logo de la empresa para los reportes de impresión
$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo_logo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $logo_base64 = 'data:image/' . $tipo_logo . ';base64,' . base64_encode(file_get_contents($ruta_logo));
}

$periodo = $_GET['periodo'] ?? date('Y-m');
$anio = substr($periodo, 0, 4);
$mes = substr($periodo, 5, 2);
$periodo_desde = "$anio-$mes-01";
$periodo_hasta = date('Y-m-t', strtotime($periodo_desde));
$tipo_nomina_activa = $_GET['tipo'] ?? 'automatica';
$filtro_cuenta = $_GET['filtro_cuenta'] ?? '';

// Capturar el número de nómina seleccionado (si viene en la URL)
$filtro_numero_nomina = $_GET['numero_nomina'] ?? '';

//function getDiasMensuales($pdo) {
//    $stmt = $pdo->query("SELECT valor FROM configuracion_general WHERE parametro = 'dias_mensuales'");
//    return intval($stmt->fetchColumn()) ?: 24;
//}

$dias_laborables = getDiasMensuales($pdo);

$tipos_nomina = [
    'automatica' => ['nombre' => 'Automática', 'icono' => 'fa-calendar-alt', 'color' => 'primary'],
    'extraordinaria' => ['nombre' => 'Extraordinaria', 'icono' => 'fa-clock', 'color' => 'info'],
    'vacaciones' => ['nombre' => 'Vacaciones', 'icono' => 'fa-umbrella-beach', 'color' => 'success'],
    'bono' => ['nombre' => 'Rend. y Otros Pagos', 'icono' => 'fa-gift', 'color' => 'warning'],
	'ajuste' => ['nombre' => 'Ajustes', 'icono' => 'fa-pen', 'color' => 'secondary']
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

if (!function_exists('numeroRomano')) {
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

// ============================================
// CUADRE DE NÓMINAS CONTABILIZADAS
// Recalcula la composición del devengado, la aritmética y los impuestos
// (CESS / ISIP) de TODAS las nóminas contabilizadas y las compara contra
// los valores almacenados en la base de datos.
// ============================================
function verificarCuadreValores($pdo, $filtro = []) {
    try {
        $cfg = [];
        $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('dias_mensuales','horas_jornada_diaria','recargo_nocturno')");
        while ($r = $stmt->fetch()) { $cfg[$r['parametro']] = $r['valor']; }
        $dias_mensuales = floatval($cfg['dias_mensuales'] ?? 0) ?: 24;
        $jornada = floatval($cfg['horas_jornada_diaria'] ?? 0) ?: 8;
        $recargo = floatval($cfg['recargo_nocturno'] ?? 0) ?: 1.25;
    } catch (Exception $e) {
        $dias_mensuales = 24; $jornada = 8; $recargo = 1.25;
    }
    try {
        $factor = floatval($pdo->query("SELECT factor_calculo FROM configuracion_vacaciones WHERE activo = 1 LIMIT 1")->fetchColumn()) ?: 0.0909;
    } catch (Exception $e) { $factor = 0.0909; }
    try {
        $tasa = floatval($pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1")->fetchColumn()) ?: 5;
    } catch (Exception $e) { $tasa = 5; }
    try {
        $rangos = $pdo->query("SELECT * FROM configuracion_rangos_impuesto ORDER BY desde")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rangos = []; }

    $where = "n.estado = 'contabilizado'";
    $params = [];
    if (!empty($filtro['pendientes']) && !empty($filtro['periodo_desde']) && !empty($filtro['periodo_hasta']) && !empty($filtro['tipo'])) {
        $where = "n.periodo_desde = ? AND n.periodo_hasta = ? AND n.tipo_nomina = ? AND n.estado = 'borrador' AND n.numero_nomina IS NULL";
        $params = [$filtro['periodo_desde'], $filtro['periodo_hasta'], $filtro['tipo']];
    }
    
    $stmt = $pdo->prepare("SELECT n.*, t.nombre_completo, t.no_acumular_vacaciones, e.salario_hora_ordinaria, e.salario_mensual,
                                  n.importe_vacaciones_acumulado_mes, n.vacaciones_acumuladas_mes
                             FROM nominas n
                             JOIN trabajadores t ON t.id = n.trabajador_id
                             LEFT JOIN escalas_salariales e ON e.id = t.escala_salarial_id
                             WHERE $where
                             ORDER BY n.periodo_desde, n.tipo_nomina, n.id");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $desglose = ['devengado' => 0, 'contribucion' => 0, 'impuesto' => 0, 'total_deducciones' => 0, 'importe_neto' => 0];
    $errores = [];
    $filas_con_error = [];
    $correctos = [];
    $filas_por_tipo = [];
    $tipo_por_id = [];

    foreach ($rows as $n) {
        $id = intval($n['id']);
        $tipo = $n['tipo_nomina'];
        $filas_por_tipo[$tipo] = ($filas_por_tipo[$tipo] ?? 0) + 1;
        $tipo_por_id[$id] = $tipo;
        $td = $n['tipo_descuento'] ?? 'solo_cess';
        $dev = floatval($n['total_salario_devengado']);
        $sh = floatval($n['salario_hora_ordinaria'] ?? 0);
        $sm = floatval($n['salario_mensual'] ?? 0);
        $noac = intval($n['no_acumular_vacaciones'] ?? 0);
        $horas = floatval($n['horas_laboradas'] ?? 0);
        $descuentos = floatval($n['descuentos'] ?? 0);
        $od = floatval($n['otras_deducciones'] ?? 0);

        $detectar = function ($categoria, $detalle, $esperado, $encontrado, $tol) use (&$errores, &$desglose, &$filas_con_error, $id, $n, $tipo) {
            $esp = round($esperado, 2);
            $enc = round($encontrado, 2);
            if (abs($enc - $esp) > $tol) {
                $desglose[$categoria]++;
                $filas_con_error[$id] = true;
                $errores[] = [
                    'id' => $id,
                    'trabajador_id' => intval($n['trabajador_id'] ?? 0),
                    'trabajador' => $n['nombre_completo'] ?? '',
                    'tipo' => $tipo,
                    'numero' => $n['numero_nomina'] ?? '',
                    'periodo' => ($n['periodo_desde'] ?? ''),
                    'check' => $categoria,
                    'detalle' => $detalle,
                    'esperado' => number_format($esp, 2, '.', ''),
                    'encontrado' => number_format($enc, 2, '.', ''),
                    'diferencia' => number_format($enc - $esp, 2, '.', ''),
                ];
            }
        };

        // =========================================================
        // A) COMPOSICIÓN DEL DEVENGADO
        // =========================================================
        if ($tipo == 'automatica') {
            $vaca = 0;
            $tol_dev = 0.02;
            
            // Si NO acumula vacaciones → se le PAGA el 9.0909% dentro del devengado del mes.
            // Se recalcula SIEMPRE desde la fórmula: la columna importe_vacaciones_acumulado_mes
            // puede estar en 0/NULL (p. ej. filas generadas masivamente) aunque el pago sí se hizo.
            if ($noac == 1) {
                $vaca = roundExcel(roundExcel(($horas * $factor) / $jornada, 2) * ($sm / $dias_mensuales), 2);
                $tol_dev = 0.05;
            }
            
            $dev_ok = roundExcel(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']) + floatval($n['importe_dias_feriados']) + floatval($n['otros_salarios']) + $vaca, 2);
            $detectar('devengado', 'Composición del devengado', $dev_ok, $dev, $tol_dev);
            
        } elseif ($tipo == 'extraordinaria') {
            $dev_ok = roundExcel(floatval($n['importe_salario_laboral']) + floatval($n['importe_horas_nocturnas']), 2);
            $detectar('devengado', 'Composición del devengado (HE + Noc + DT)', $dev_ok, $dev, 0.02);
            
        } elseif ($tipo == 'vacaciones') {
            $dev_ok = floatval($n['importe_vacaciones']);
            $detectar('devengado', 'Devengado = Importe de vacaciones', $dev_ok, $dev, 0.001);
            
        } elseif ($tipo == 'bono') {
            $dev_ok = floatval($n['pago_resultado']);
            $detectar('devengado', 'Devengado = Pago resultado', $dev_ok, $dev, 0.001);
            
        } elseif ($tipo == 'ajuste') {
            // En ajustes por horas de trabajadores que NO acumulan vacaciones,
            // pago_resultado YA incluye el pago del 9.0909% (importe_laboral + importe_vacaciones).
            // No se vuelve a sumar: devengado esperado = pago_resultado + otros_salarios.
            $dev_ok = roundExcel(floatval($n['pago_resultado']) + floatval($n['otros_salarios']), 2);
            $detectar('devengado', 'Composición del devengado', $dev_ok, $dev, 0.02);
        }

        // =========================================================
        // B) IMPUESTOS RECALCULADOS
        // =========================================================
        if ($td == 'solo_cess') {
            $contrib_calc = calcularCessProgresivoPHP($dev);
            $imp_calc = 0;
        } else {
            $contrib_calc = roundExcel($dev * ($tasa / 100), 2);
            $imp_calc = calcularTotalImpuesto($dev, $rangos);
        }
        $detectar('contribucion', 'Contribución especial (CESS)', $contrib_calc, floatval($n['contribucion_especial']), 0.001);
        $detectar('impuesto', 'Impuesto sobre ingresos (ISIP)', $imp_calc, floatval($n['ingresos_personales']), 0.001);

        // =========================================================
        // C) ARITMÉTICA
        // =========================================================
        $ded_calc = roundExcel($contrib_calc + $imp_calc + $descuentos + $od, 2);
        $detectar('total_deducciones', 'Total de deducciones', $ded_calc, floatval($n['total_deducciones']), 0.02);
        $neto_antes = roundExcel($dev - ($contrib_calc + $imp_calc), 2);
        $neto_calc = roundExcel($neto_antes - $descuentos - $od, 2);
        $detectar('importe_neto', 'Importe neto', $neto_calc, floatval($n['importe_neto']), 0.02);

        // =========================================================
        // D) VALORES CORRECTOS
        // =========================================================
        $contrib_ok = ($td == 'solo_cess') ? calcularCessProgresivoPHP($dev_ok) : roundExcel($dev_ok * ($tasa / 100), 2);
        $imp_ok = ($td == 'solo_cess') ? 0 : calcularTotalImpuesto($dev_ok, $rangos);
        $ded_ok = roundExcel($contrib_ok + $imp_ok + $descuentos + $od, 2);
        $neto_ok = roundExcel($dev_ok - $contrib_ok - $imp_ok - $descuentos - $od, 2);
        $correctos[$id] = [
            'devengado' => $dev_ok,
            'contribucion' => $contrib_ok,
            'impuesto' => $imp_ok,
            'total_deducciones' => $ded_ok,
            'importe_neto' => $neto_ok,
        ];
    }

    $por_tipo = [];
    foreach (['automatica', 'extraordinaria', 'vacaciones', 'bono', 'ajuste'] as $t) {
        $por_tipo[$t] = ['filas' => $filas_por_tipo[$t] ?? 0, 'con_error' => 0, 'errores' => 0];
    }
    foreach (array_keys($filas_con_error) as $id) {
        if (isset($tipo_por_id[$id], $por_tipo[$tipo_por_id[$id]])) {
            $por_tipo[$tipo_por_id[$id]]['con_error']++;
        }
    }
    foreach ($errores as $e) {
        if (isset($por_tipo[$e['tipo']])) {
            $por_tipo[$e['tipo']]['errores']++;
        }
    }

    // =========================================================
    // E) VERIFICACIÓN DE CIERRES (cierres_nomina vs filas contabilizadas)
    // Solo aplica al modo contabilizadas (no a borradores pendientes).
    // =========================================================
    $reporte_cierres = isset($filtro['pendientes']) && $filtro['pendientes']
        ? ['cierres' => 0, 'cierres_con_error' => 0, 'errores_total' => 0, 'errores_cierre' => []]
        : verificarCuadreCierres($pdo);
    foreach ($reporte_cierres['errores_cierre'] as $ce) {
        $ce['trabajador_id'] = 0;
        $ce['trabajador'] = 'CIERRE';
        $ce['es_cierre'] = true;
        $errores[] = $ce;
        if (isset($por_tipo[$ce['tipo']])) {
            $por_tipo[$ce['tipo']]['errores']++;
        }
    }

    return [
        'filas' => count($rows),
        'nominas' => count(array_unique(array_filter(array_column($rows, 'numero_nomina')))),
        'errores_total' => count($errores),
        'filas_con_error' => count($filas_con_error),
        'errores_aritmetica' => $desglose['devengado'] + $desglose['total_deducciones'] + $desglose['importe_neto'],
        'errores_impuestos' => $desglose['contribucion'] + $desglose['impuesto'],
        'desglose' => $desglose,
        'por_tipo' => $por_tipo,
        'ids_error' => array_keys($filas_con_error),
        'correctos' => $correctos,
        'errores' => array_slice($errores, 0, 200),
        // Verificación de cierres_nomina
        'cierres_total' => intval($reporte_cierres['cierres']),
        'cierres_con_error' => intval($reporte_cierres['cierres_con_error']),
        'errores_cierre_total' => intval($reporte_cierres['errores_total']),
        'errores_cierre' => $reporte_cierres['errores_cierre'],
    ];
}
// AJAX: Verificar cuadre de nóminas contabilizadas
if (isset($_GET['action']) && $_GET['action'] === 'verificar_cuadre' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(verificarCuadreValores($pdo), JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX (solo lectura): Chequeo del cuadre de los borradores del período/tipo antes de contabilizar
if (isset($_GET['action']) && $_GET['action'] === 'chequear_cuadre_pendiente' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $per = trim($_GET['periodo'] ?? '');
    $tip = trim($_GET['tipo'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $per) || !in_array($tip, ['automatica', 'extraordinaria', 'vacaciones', 'bono', 'ajuste'])) {
        echo json_encode(['filas' => 0, 'errores_total' => 0, 'filas_con_error' => 0, 'errores_impuestos' => 0, 'errores_aritmetica' => 0, 'errores' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pd_c = $per . '-01';
    $ph_c = date('Y-m-t', strtotime($pd_c));
    echo json_encode(verificarCuadreValores($pdo, [
        'pendientes' => true,
        'periodo_desde' => $pd_c,
        'periodo_hasta' => $ph_c,
        'tipo' => $tip,
    ]), JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: Verificar si ya existe una nómina automática en el período (botón Generar Nómina Automática)
if (isset($_GET['action']) && $_GET['action'] === 'verificar_nomina_automatica' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $resp_va = ['exists' => false, 'numero_nomina' => '', 'periodo_label' => '', 'cantidad' => 0, 'devengado' => 0, 'neto' => 0];
    $per_va = trim($_GET['periodo'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $per_va)) {
        $pd_va = $per_va . '-01';
        $ph_va = date('Y-m-t', strtotime($pd_va));
        $stmt_va = $pdo->prepare("SELECT COALESCE(MAX(numero_nomina), '') AS numero_nomina,
                                         COUNT(DISTINCT trabajador_id) AS cantidad,
                                         ROUND(SUM(total_salario_devengado), 2) AS devengado,
                                         ROUND(SUM(importe_neto), 2) AS neto
                                  FROM nominas
                                  WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'automatica'");
        $stmt_va->execute([$pd_va, $ph_va]);
        $fila_va = $stmt_va->fetch(PDO::FETCH_ASSOC);
        if ($fila_va && intval($fila_va['cantidad']) > 0) {
            $meses_va = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $resp_va = [
                'exists' => true,
                'numero_nomina' => (string)($fila_va['numero_nomina'] ?: 'S/N'),
                'periodo_label' => ($meses_va[substr($per_va, 5, 2)] ?? '') . ' ' . substr($per_va, 0, 4),
                'cantidad' => intval($fila_va['cantidad']),
                'devengado' => floatval($fila_va['devengado']),
                'neto' => floatval($fila_va['neto']),
            ];
        }
    }
    echo json_encode($resp_va, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: Corregir valores de nóminas contabilizadas con descuadres
if (isset($_GET['action']) && $_GET['action'] === 'corregir_cuadre' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$puede_editar_nomina) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para corregir los valores.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $report = verificarCuadreValores($pdo);
        $ids = array_values(array_filter(array_map('intval', $report['ids_error'] ?? [])));
        $st = $pdo->prepare("UPDATE nominas SET total_salario_devengado = ?, contribucion_especial = ?, ingresos_personales = ?, total_deducciones = ?, importe_neto = ? WHERE id = ? AND estado = 'contabilizado'");
        $actualizadas = 0;
        foreach ($ids as $fid) {
            $c = $report['correctos'][$fid] ?? null;
            if (!$c) continue;
            $st->execute([
                roundExcel($c['devengado'], 2),
                roundExcel($c['contribucion'], 2),
                roundExcel($c['impuesto'], 2),
                roundExcel($c['total_deducciones'], 2),
                roundExcel($c['importe_neto'], 2),
                $fid
            ]);
            $actualizadas++;
        }
        $nuevo = verificarCuadreValores($pdo);
        logAction('corregir_cuadre_nominas', 'nominas', 'Corrección de cuadre de nóminas contabilizadas', ['filas_corregidas' => $actualizadas, 'filas_con_error' => $nuevo['filas_con_error']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        echo json_encode([
            'success' => true,
            'filas_corregidas' => $actualizadas,
            'filas_con_error' => $nuevo['filas_con_error'],
            'errores_total' => $nuevo['errores_total'],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// AJAX: Corregir valores de los borradores PENDIENTES de contabilizar con descuadres
if (isset($_GET['action']) && $_GET['action'] === 'corregir_pendientes' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$puede_editar_nomina) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para corregir los valores.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pd = $_GET['pd'] ?? '';
    $ph = $_GET['ph'] ?? '';
    $tp = $_GET['tipo'] ?? '';
    if ($pd === '' || $ph === '' || $tp === '') {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros del período/tipo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $report = verificarCuadreValores($pdo, [
            'pendientes' => true,
            'periodo_desde' => $pd,
            'periodo_hasta' => $ph,
            'tipo' => $tp,
        ]);
        $ids = array_values(array_filter(array_map('intval', $report['ids_error'] ?? [])));
        $st = $pdo->prepare("UPDATE nominas SET total_salario_devengado = ?, contribucion_especial = ?, ingresos_personales = ?, total_deducciones = ?, importe_neto = ? WHERE id = ? AND estado = 'borrador' AND numero_nomina IS NULL");
        $actualizadas = 0;
        foreach ($ids as $fid) {
            $c = $report['correctos'][$fid] ?? null;
            if (!$c) continue;
            $st->execute([
                roundExcel($c['devengado'], 2),
                roundExcel($c['contribucion'], 2),
                roundExcel($c['impuesto'], 2),
                roundExcel($c['total_deducciones'], 2),
                roundExcel($c['importe_neto'], 2),
                $fid
            ]);
            $actualizadas++;
        }
        $nuevo = verificarCuadreValores($pdo, [
            'pendientes' => true,
            'periodo_desde' => $pd,
            'periodo_hasta' => $ph,
            'tipo' => $tp,
        ]);
        logAction('corregir_cuadre_pendientes', 'nominas', 'Corrección de cuadre de borradores pendientes de contabilizar', ['periodo_desde' => $pd, 'periodo_hasta' => $ph, 'tipo' => $tp, 'filas_corregidas' => $actualizadas, 'filas_con_error' => $nuevo['filas_con_error']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        echo json_encode([
            'success' => true,
            'filas_corregidas' => $actualizadas,
            'filas_con_error' => $nuevo['filas_con_error'],
            'errores_total' => $nuevo['errores_total'],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// AJAX: Corregir (recalcular) los totales de cierres_nomina descuadrados
// Recalcula cada cierre con descuadre desde la suma real de sus filas contabilizadas.
if (isset($_GET['action']) && $_GET['action'] === 'corregir_cierres' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$puede_editar_nomina) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para corregir los cierres.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $reporte = verificarCuadreCierres($pdo);
        $actualizados = 0;
        if ($reporte['cierres_con_error'] > 0) {
            // Identificar los cierres con descuadre
            $cierres_err = [];
            foreach ($reporte['errores_cierre'] as $e) {
                $clave = $e['periodo'] . '|' . $e['tipo'] . '|' . $e['numero'];
                $cierres_err[$clave] = true;
            }
            // Recalcular totales reales por nómina contabilizada
            $stmt_sum = $pdo->prepare("SELECT periodo_desde, periodo_hasta, tipo_nomina, numero_nomina,
                                              COUNT(*) as total_trabajadores,
                                              SUM(total_salario_devengado) as total_devengado,
                                              SUM(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0) + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0)) as total_deducciones,
                                              SUM(importe_neto) as total_neto,
                                              SUM(contribucion_especial) as total_contribucion,
                                              SUM(COALESCE(importe_vacaciones, 0)) as total_vacaciones_pagadas
                                         FROM nominas
                                        WHERE estado = 'contabilizado' AND numero_nomina IS NOT NULL
                                        GROUP BY periodo_desde, periodo_hasta, tipo_nomina, numero_nomina");
            $stmt_sum->execute();
            $sumas = $stmt_sum->fetchAll(PDO::FETCH_ASSOC);

            $stmt_upd = $pdo->prepare("UPDATE cierres_nomina
                                          SET total_trabajadores = ?, total_devengado = ?, total_deducciones = ?,
                                              total_neto = ?, total_contribucion = ?, total_vacaciones_pagadas = ?
                                        WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND numero_nomina = ?");
            foreach ($sumas as $s) {
                $clave = $s['periodo_desde'] . '|' . $s['tipo_nomina'] . '|' . $s['numero_nomina'];
                if (!isset($cierres_err[$clave])) continue;
                $stmt_upd->execute([
                    $s['total_trabajadores'],
                    $s['total_devengado'] ?? 0,
                    $s['total_deducciones'] ?? 0,
                    $s['total_neto'] ?? 0,
                    $s['total_contribucion'] ?? 0,
                    $s['total_vacaciones_pagadas'] ?? 0,
                    $s['periodo_desde'],
                    $s['periodo_hasta'],
                    $s['tipo_nomina'],
                    $s['numero_nomina'],
                ]);
                $actualizados++;
            }
        }
        $nuevo = verificarCuadreCierres($pdo);
        logAction('corregir_cierres_nomina', 'nominas', 'Corrección de cierres de nómina descuadrados', ['cierres_corregidos' => $actualizados, 'cierres_con_error' => $nuevo['cierres_con_error']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        echo json_encode([
            'success' => true,
            'cierres_corregidos' => $actualizados,
            'cierres_con_error' => $nuevo['cierres_con_error'],
            'errores_total' => $nuevo['errores_total'],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
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
					t.cargo_id,
					COALESCE((SELECT SUM(CASE WHEN pa.tipo_calculo = 'monto_fijo' THEN pa.monto ELSE 0 END)
					          FROM trabajador_pago_adicional tpa
					          JOIN pagos_adicionales pa ON pa.id = tpa.pago_adicional_id AND pa.activo = 1
					          WHERE tpa.trabajador_id = t.id), 0) as pago_adicional_fijo_total
				FROM trabajadores t 
				JOIN escalas_salariales e ON t.escala_salarial_id = e.id 
				LEFT JOIN cargos_plantilla cp ON t.cargo_id = cp.id
				LEFT JOIN areas a ON t.area_id = a.id 
				LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id 
				WHERE t.activo = 1 
				ORDER BY t.nombre_completo";
		
		$stmt = $pdo->query($sql);
		$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($trabajadores as &$w) {
			$w['pagos_adicionales'] = getPagosPorTrabajador($pdo, $w['id']);
		}
		unset($w);
		return $trabajadores;
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

// Restaura el saldo de vacaciones y elimina los movimientos de disfrute del
// submayor de los registros borrador de liquidación de fracciones que se borren.
if (!function_exists('revertirDisfrutesLiquidacion')) {
    function revertirDisfrutesLiquidacion($pdo, $ids) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT nomina_id, trabajador_id, dias 
                               FROM submayor_vacaciones 
                               WHERE nomina_id IN ($ph) AND tipo_movimiento = 'disfrute'");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sv) {
            $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas + ? WHERE id = ?")
                ->execute([floatval($sv['dias']), $sv['trabajador_id']]);
        }
        $pdo->prepare("DELETE FROM submayor_vacaciones WHERE nomina_id IN ($ph) AND tipo_movimiento = 'disfrute'")->execute($ids);
    }
}

if (isset($_POST['eliminar_nomina_completa'])) {
    if (!$puede_eliminar_nomina) { header('Location: ../dashboard.php'); exit; }
    $tipo_eliminar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $check = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? AND estado != 'borrador'");
    $check->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
    if ($check->fetchColumn() == 0) {
        $stmt_ids = $pdo->prepare("SELECT id FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?");
        $stmt_ids->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
        $ids_eliminar = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
        revertirDisfrutesLiquidacion($pdo, $ids_eliminar);
        $pdo->prepare("DELETE FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
        $pdo->prepare("DELETE FROM cierres_nomina WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ?")->execute([$periodo_desde, $periodo_hasta, $tipo_eliminar]);
    }
    logAction('eliminar_nomina_completa', 'nominas', 'Eliminación completa de nóminas del período', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_eliminar], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_eliminar&msg=deleted");
    exit;
}

// 🔽 NUEVO: Eliminar solo las filas en estado Borrador enviadas por el botón "Eliminar Todo" del ajuste
if (isset($_POST['eliminar_borrador_por_ids'])) {
    if (!$puede_eliminar_nomina) { header('Location: ../dashboard.php'); exit; }
    $tipo_eliminar = $_POST['tipo_nomina'] ?? $tipo_nomina_activa;
    $ids_borrador = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
    $ids_borrador = array_values(array_filter(array_map('intval', $ids_borrador)));
    if (!empty($ids_borrador)) {
        revertirDisfrutesLiquidacion($pdo, $ids_borrador);
        $placeholders = implode(',', array_fill(0, count($ids_borrador), '?'));
        $stmt_eb = $pdo->prepare("DELETE FROM nominas WHERE id IN ($placeholders) AND estado = 'borrador'");
        $stmt_eb->execute($ids_borrador);
    }
    logAction('eliminar_nomina_borradores', 'nominas', 'Eliminación de nóminas en borrador', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_eliminar, 'cantidad' => count($ids_borrador)], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_eliminar&msg=deleted");
    exit;
}

if (isset($_POST['eliminar_nomina_individual'])) {
    if (!$puede_eliminar_nomina) { header('Location: ../dashboard.php'); exit; }
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("SELECT estado FROM nominas WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() == 'borrador') {
        revertirDisfrutesLiquidacion($pdo, [$id]);
        $pdo->prepare("DELETE FROM nominas WHERE id = ?")->execute([$id]);
    }
    logAction('eliminar_nomina_individual', 'nominas', 'Eliminación de nómina individual', ['id' => (int)$id], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo_nomina_activa&msg=deleted");
    exit;
}

// REGENERAR NÓMINA
if (isset($_POST['regenerar_nomina'])) {
    if (!$puede_editar_nomina) { header('Location: ../dashboard.php'); exit; }
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
            $base = $salario_laboral;
            $pago_adicional_total = 0.0;
            foreach (($trabajador['pagos_adicionales'] ?? []) as $pa) {
                if ($pa['tipo_calculo'] === 'monto_fijo') {
                    $pago_adicional_total += (float)$pa['monto'];
                } elseif ($pa['tipo_calculo'] === 'porcentaje') {
                    $pago_adicional_total += (float)$pa['monto'] / 100 * $base;
                }
            }
            $pago_adicional_total = roundExcel($pago_adicional_total, 2);
            $pago_adicional_fijo_total = $trabajador['pago_adicional_fijo_total'] ?? 0.0;
            
            $importe_vacaciones_adicional = 0;
            if ($trabajador['no_acumular_vacaciones'] == 1) {
                $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
                $valor_por_dia = $salario_mensual / $dias_laborables;
                $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
            }
            
            $total_devengado = $salario_laboral + $importe_vacaciones_adicional + $pago_adicional_total;
            
            if ($tipo_descuento_reg == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($total_devengado);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($total_devengado * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($total_devengado, $rangos_impuesto);
            }
            
            $neto = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
            $stmt_insert = $pdo->prepare("INSERT INTO nominas (trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, importe_salario_laboral, otros_salarios, total_salario_devengado, contribucion_especial, ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_insert->execute([$trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0, $salario_laboral, $pago_adicional_total, $total_devengado, $contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $tipo_regenerar, 'borrador', $tipo_descuento_reg]);
            $nomina_id_insertado = $pdo->lastInsertId();
            foreach (($trabajador['pagos_adicionales'] ?? []) as $pa) {
                $importe = $pa['tipo_calculo'] === 'monto_fijo'
                    ? (float)$pa['monto']
                    : (float)$pa['monto'] / 100 * $base;
                $pdo->prepare("INSERT INTO nomina_pagos_adicionales (nomina_id, trabajador_id, pago_adicional_id, importe_aplicado) VALUES (?,?,?,?)")
                    ->execute([$nomina_id_insertado, $trabajador['id'], $pa['id'], roundExcel($importe, 2)]);
            }
        }
        logAction('regenerar_nomina', 'nominas', 'Regeneración de nómina', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_regenerar], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_regenerar&msg=generated");
    } elseif ($tipo_regenerar == 'extraordinaria') {
        logAction('regenerar_nomina', 'nominas', 'Regeneración de nómina', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_regenerar], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_regenerar&msg=regenerado_listo");
    }
    exit;
}

if (isset($_POST['generar_nomina_automatica'])) {
    if (!$puede_crear_nomina) { header('Location: ../dashboard.php'); exit; }
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
        $base = $salario_laboral;
        $pago_adicional_total = 0.0;
        foreach (($trabajador['pagos_adicionales'] ?? []) as $pa) {
            if ($pa['tipo_calculo'] === 'monto_fijo') {
                $pago_adicional_total += (float)$pa['monto'];
            } elseif ($pa['tipo_calculo'] === 'porcentaje') {
                $pago_adicional_total += (float)$pa['monto'] / 100 * $base;
            }
        }
        $pago_adicional_total = roundExcel($pago_adicional_total, 2);
        $pago_adicional_fijo_total = $trabajador['pago_adicional_fijo_total'] ?? 0.0;
        
        $importe_vacaciones_adicional = 0;
        if ($trabajador['no_acumular_vacaciones'] == 1) {
            $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
            $valor_por_dia = $salario_mensual / $dias_laborables;
            $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
        }
        
        $total_devengado = $salario_laboral + $importe_vacaciones_adicional + $pago_adicional_total;
        
        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCESSProgresivo($total_devengado);
            $impuesto = 0;
            $neto = roundExcel($total_devengado - $contribucion, 2);
            
            $stmt_insert = $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, 
                importe_salario_laboral, otros_salarios, total_salario_devengado, contribucion_especial, 
                ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_insert->execute([
                $trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
                $salario_laboral, $pago_adicional_total, $total_devengado, $contribucion, $impuesto, $neto,
                roundExcel($contribucion + $impuesto, 2),
                $tipo, 'borrador', 'CESS progresivo: 5% hasta 15,000 CUP, 10% exceso', 'solo_cess'
            ]);
            $nomina_id_insertado = $pdo->lastInsertId();
            foreach (($trabajador['pagos_adicionales'] ?? []) as $pa) {
                $importe = $pa['tipo_calculo'] === 'monto_fijo'
                    ? (float)$pa['monto']
                    : (float)$pa['monto'] / 100 * $base;
                $pdo->prepare("INSERT INTO nomina_pagos_adicionales (nomina_id, trabajador_id, pago_adicional_id, importe_aplicado) VALUES (?,?,?,?)")
                    ->execute([$nomina_id_insertado, $trabajador['id'], $pa['id'], roundExcel($importe, 2)]);
            }
        } else {
            $contribucion = roundExcel($total_devengado * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado, $rangos_impuesto);
            $neto = roundExcel($total_devengado - ($contribucion + $impuesto), 2);
            
            $stmt_insert = $pdo->prepare("INSERT INTO nominas (
                trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, dias_feriados, 
                importe_salario_laboral, otros_salarios, total_salario_devengado, contribucion_especial, 
                ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_insert->execute([
                $trabajador['id'], $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
                $salario_laboral, $pago_adicional_total, $total_devengado, $contribucion, $impuesto, $neto,
                roundExcel($contribucion + $impuesto, 2), $tipo, 'borrador', 'total_rangos'
            ]);
            $nomina_id_insertado = $pdo->lastInsertId();
            foreach (($trabajador['pagos_adicionales'] ?? []) as $pa) {
                $importe = $pa['tipo_calculo'] === 'monto_fijo'
                    ? (float)$pa['monto']
                    : (float)$pa['monto'] / 100 * $base;
                $pdo->prepare("INSERT INTO nomina_pagos_adicionales (nomina_id, trabajador_id, pago_adicional_id, importe_aplicado) VALUES (?,?,?,?)")
                    ->execute([$nomina_id_insertado, $trabajador['id'], $pa['id'], roundExcel($importe, 2)]);
            }
        }
    }
    
    logAction('generar_nomina_automatica', 'nominas', 'Generación de nómina automática', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo_descuento' => $tipo_descuento], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=generated");
    exit;
}

// GENERAR NÓMINA EXTRAORDINARIA — Sector Presupuestado (Resolución 15/2026 MTSS)
if (isset($_POST['generar_nomina_extraordinaria']) && isset($_POST['confirmar_extraordinaria'])) {
    if (!$puede_crear_nomina) { header('Location: ../dashboard.php'); exit; }
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $horas_por_trabajador = $_POST['horas_trabajadas'] ?? [];
    $noct_temprana_por_trabajador = $_POST['nocturnidad_temprana_trabajadas'] ?? [];
    $noct_tardia_por_trabajador = $_POST['nocturnidad_tardia_trabajadas'] ?? [];
    $doble_turno_por_trabajador = $_POST['doble_turno_trabajadas'] ?? [];
    $tasa_contribucion = getTasaContribucion($pdo);
    $tipo = 'extraordinaria';
    $tipo_descuento_extra = $_POST['tipo_descuento_extra'] ?? 'total_rangos';

    $agregados = 0;

    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $horas_normales = floatval($horas_por_trabajador[$index] ?? 0);
        $noct_temprana = floatval($noct_temprana_por_trabajador[$index] ?? 0);
        $noct_tardia = floatval($noct_tardia_por_trabajador[$index] ?? 0);
        $doble_turno = floatval($doble_turno_por_trabajador[$index] ?? 0);

        if ($horas_normales <= 0 && $noct_temprana <= 0 && $noct_tardia <= 0 && $doble_turno <= 0) {
            continue;
        }

        $stmt = $pdo->prepare("SELECT e.salario_hora_ordinaria, e.salario_mensual
                               FROM trabajadores t
                               JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                               WHERE t.id = ?");
        $stmt->execute([$trabajador_id]);
        $salario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$salario) { continue; }

        $salario_hora = floatval($salario['salario_hora_ordinaria']);

        // Cálculo de importes — Sector Presupuestado
$importe_he_diurnas = roundExcel($salario_hora * $recargo_extra_diurna * $horas_normales, 2);
            $importe_noct_temprana = roundExcel($salario_hora * $recargo_extra_nocturna * $noct_temprana, 2);
            $importe_noct_tardia = roundExcel($salario_hora * $recargo_extra_nocturna * $noct_tardia, 2);
            $importe_doble_turno = roundExcel($salario_hora * $recargo_doble_turno * $doble_turno, 2);

        $total_devengado_nuevo = roundExcel($importe_he_diurnas + $importe_noct_temprana + $importe_noct_tardia + $importe_doble_turno, 2);

        $total_horas_noct = $noct_temprana + $noct_tardia;
        $total_importe_noct = $importe_noct_temprana + $importe_noct_tardia;
        $total_horas_con_extra = $horas_normales + $doble_turno;
        $total_importe_con_extra = $importe_he_diurnas + $importe_doble_turno;

        $stmt_check = $pdo->prepare("SELECT id, horas_laboradas, horas_nocturnas, importe_horas_nocturnas,
                                            horas_nocturnas_tempranas, importe_nocturnas_tempranas,
                                            horas_nocturnas_tardias, importe_nocturnas_tardias,
                                            horas_doble_turno, importe_doble_turno,
                                            importe_salario_laboral, total_salario_devengado, tipo_descuento
                                     FROM nominas
                                     WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ?
                                     AND tipo_nomina = 'extraordinaria' AND estado = 'borrador'");
        $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
        $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $ht = $existente['horas_laboradas'] + $horas_normales;
            $hnt = $existente['horas_nocturnas'] + $total_horas_noct;
            $hint = $existente['importe_horas_nocturnas'] + $total_importe_noct;
            $hntt = $existente['horas_nocturnas_tempranas'] + $noct_temprana;
            $intt = $existente['importe_nocturnas_tempranas'] + $importe_noct_temprana;
            $hntard = $existente['horas_nocturnas_tardias'] + $noct_tardia;
            $intard = $existente['importe_nocturnas_tardias'] + $importe_noct_tardia;
            $hdt = $existente['horas_doble_turno'] + $doble_turno;
            $idt = $existente['importe_doble_turno'] + $importe_doble_turno;
            $sl = $existente['importe_salario_laboral'] + $total_importe_con_extra;
            $tdt = $existente['total_salario_devengado'] + $total_devengado_nuevo;

            if ($tipo_descuento_extra == 'solo_cess') {
                $contribucion = calcularCessProgresivoPHP($tdt);
                $impuesto = 0;
            } else {
                $contribucion = roundExcel($tdt * ($tasa_contribucion / 100), 2);
                $impuesto = calcularTotalImpuesto($tdt, $rangos_impuesto);
            }
            $neto = roundExcel($tdt - ($contribucion + $impuesto), 2);

            $pdo->prepare("UPDATE nominas SET
                horas_laboradas=?, horas_nocturnas=?, importe_horas_nocturnas=?,
                horas_nocturnas_tempranas=?, importe_nocturnas_tempranas=?,
                horas_nocturnas_tardias=?, importe_nocturnas_tardias=?,
                horas_doble_turno=?, importe_doble_turno=?,
                importe_salario_laboral=?, total_salario_devengado=?,
                contribucion_especial=?, ingresos_personales=?, importe_neto=?,
                total_deducciones=?, tipo_descuento=?,
                descripcion=CONCAT(descripcion, ', +HE:', ?, ' Nt:', ?, '/', ?, ' DT:', ?)
                WHERE id=?")
            ->execute([$ht, $hnt, $hint, $hntt, $intt, $hntard, $intard, $hdt, $idt,
                       $sl, $tdt, $contribucion, $impuesto, $neto,
                       roundExcel($contribucion + $impuesto, 2), $tipo_descuento_extra,
                       $horas_normales, $noct_temprana, $noct_tardia, $doble_turno,
                       $existente['id']]);
        } else {
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
                horas_nocturnas_tempranas, importe_nocturnas_tempranas,
                horas_nocturnas_tardias, importe_nocturnas_tardias,
                horas_doble_turno, importe_doble_turno,
                importe_salario_laboral, total_salario_devengado,
                contribucion_especial, ingresos_personales, importe_neto,
                total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $trabajador_id, $periodo_desde, $periodo_hasta,
                $horas_normales, $total_horas_noct, $total_importe_noct,
                $noct_temprana, $importe_noct_temprana,
                $noct_tardia, $importe_noct_tardia,
                $doble_turno, $importe_doble_turno,
                $total_importe_con_extra, $total_devengado_nuevo,
                $contribucion, $impuesto, $neto,
                roundExcel($contribucion + $impuesto, 2),
                $tipo, 'borrador',
                "HE:{$horas_normales}h Nt7-23:{$noct_temprana}h Nt23-7:{$noct_tardia}h DT:{$doble_turno}h",
                $tipo_descuento_extra
            ]);
        }
        $agregados++;
    }

    if ($agregados > 0) {
        logAction('generar_nomina_extraordinaria', 'nominas', 'Generación de nómina extraordinaria', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'trabajadores' => $agregados], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=extraordinaria_added&count=$agregados");
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
    if ($agregados > 0 || $actualizados > 0) {
        logAction('generar_nomina_bono', 'nominas', 'Generación de nómina de bono', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'trabajadores' => ($agregados + $actualizados)], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    }
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&" . (($agregados > 0 || $actualizados > 0) ? "msg=bono_added&count=" . ($agregados + $actualizados) : "error=no_bonos"));
    exit;
}
if (isset($_POST['generar_nomina_ajuste']) && isset($_POST['confirmar_ajuste'])) {
    if (!$puede_crear_nomina) { header('Location: ../dashboard.php'); exit; }
    $trabajadores_ids = $_POST['trabajador_id'] ?? [];
    $montos_ajuste = $_POST['monto_ajuste'] ?? [];
    $horas_ajuste = $_POST['horas_ajuste'] ?? [];
    $concepto = trim($_POST['concepto_ajuste']);
    $tipo = 'ajuste';
    $modo_ajuste = $_POST['modo_ajuste'] ?? 'directo';
    $tipo_descuento_ajuste = $_POST['tipo_descuento_ajuste'] ?? 'total_rangos';
    $tasa_contribucion = getTasaContribucion($pdo);
    
    $agregados = 0;
    $actualizados = 0;
    
    foreach ($trabajadores_ids as $index => $trabajador_id) {
        $trabajador_id = intval($trabajador_id);
        $horas_nuevas = 0;
        $monto_nuevo = floatval($montos_ajuste[$index] ?? 0);
        $importe_laboral = 0;
        $dias_vac_calc = 0;
        $importe_vac_calc = 0;
        
        // Datos del trabajador (salario/hora, salario mensual y política de vacaciones)
        $stmt_wa = $pdo->prepare("SELECT e.salario_hora_ordinaria, e.salario_mensual, t.no_acumular_vacaciones, t.vacaciones_acumuladas
                                  FROM trabajadores t JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                                  WHERE t.id = ?");
        $stmt_wa->execute([$trabajador_id]);
        $worker_aj = $stmt_wa->fetch(PDO::FETCH_ASSOC);
        if (!$worker_aj) continue;
        $salario_hora_aj = floatval($worker_aj['salario_hora_ordinaria'] ?? 0);
        $salario_mensual_aj = floatval($worker_aj['salario_mensual'] ?? 0);
        $no_acum_aj = intval($worker_aj['no_acumular_vacaciones'] ?? 0);
        $vac_acumuladas_aj = floatval($worker_aj['vacaciones_acumuladas'] ?? 0);
        
        if ($modo_ajuste == 'horas') {
            // Pago por horas trabajadas: acumula vacaciones (9.09%) y descuenta CESS
            $horas_nuevas = floatval($horas_ajuste[$index] ?? 0);
            if ($horas_nuevas <= 0) continue;
            $importe_laboral = roundExcel($salario_hora_aj * $horas_nuevas, 2);
            $dias_vac_calc = roundExcel(($horas_nuevas * $factor_909) / $horas_jornada, 2);
            $importe_vac_calc = roundExcel($dias_vac_calc * ($salario_mensual_aj / $dias_laborables), 2);
            $importe_vac_adicional = ($no_acum_aj == 1) ? $importe_vac_calc : 0;
            $monto_nuevo = roundExcel($importe_laboral + $importe_vac_adicional, 2);
        }
        if ($modo_ajuste == 'liquidacion') {
            // Liquidación final de vacaciones: se liquida el saldo TOTAL acumulado del
            // trabajador (queda en cero días en la tabla trabajadores). Si el borrador de
            // este período ya fue generado antes, se suma lo ya liquidado en él para que
            // la regeneración quede consistente con el submayor.
            $stmt_frac = $pdo->prepare("SELECT COALESCE(SUM(sv.dias), 0) 
                                        FROM submayor_vacaciones sv
                                        JOIN nominas n ON n.id = sv.nomina_id
                                        WHERE sv.trabajador_id = ? AND sv.tipo_movimiento = 'disfrute'
                                          AND n.tipo_nomina = 'ajuste'
                                          AND n.periodo_desde = ? AND n.periodo_hasta = ?
                                          AND n.estado = 'borrador'");
            $stmt_frac->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
            $dias_vac_calc = roundExcel($vac_acumuladas_aj + floatval($stmt_frac->fetchColumn()), 2);
            $importe_vac_calc = roundExcel($dias_vac_calc * ($salario_mensual_aj / $dias_laborables), 2);
            $monto_nuevo = roundExcel($importe_vac_calc, 2);
        }
        if ($monto_nuevo <= 0) continue;
        
        // 1. Verificar si ya existe un ajuste BORRADOR para este trabajador en el mismo período
        $stmt_check = $pdo->prepare("SELECT id, pago_resultado, descripcion, horas_laboradas, vacaciones_acumuladas_mes 
                                     FROM nominas 
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
            
            $horas_store = $existente['horas_laboradas'] ?? 0;
            $importe_laboral_store = 0;
            $dias_vac_store = 0;
            $importe_vac_store = 0;
            
            if ($modo_ajuste == 'horas') {
                $horas_store = floatval($horas_store) + $horas_nuevas;
                $importe_laboral_store = roundExcel($salario_hora_aj * $horas_store, 2);
                $dias_vac_store = roundExcel(($horas_store * $factor_909) / $horas_jornada, 2);
                $importe_vac_store = roundExcel($dias_vac_store * ($salario_mensual_aj / $dias_laborables), 2);
                $importe_vac_adicional_total = ($no_acum_aj == 1) ? $importe_vac_store : 0;
                $monto_total = roundExcel($importe_laboral_store + $importe_vac_adicional_total, 2);
            } elseif ($modo_ajuste == 'liquidacion') {
                // La liquidación es determinista: al regenerar se reemplazan
                // el monto, días y descripción por los valores recalculados.
                $dias_vac_store = $dias_vac_calc;
                $importe_vac_store = $importe_vac_calc;
                $monto_total = $monto_nuevo;
                $descripcion_nueva = $concepto;
            } elseif (floatval($horas_store) > 0) {
                // El ajuste existente es por horas; el monto directo se suma encima
                $importe_laboral_store = $existente['importe_salario_laboral'] ?? 0;
                $dias_vac_store = $existente['vacaciones_acumuladas_mes'] ?? 0;
                $importe_vac_store = $existente['importe_vacaciones_acumulado_mes'] ?? 0;
            }
            
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
                                         horas_laboradas = ?,
                                         importe_salario_laboral = ?,
                                         vacaciones_acumuladas_mes = ?,
                                         importe_vacaciones_acumulado_mes = ?,
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
                $horas_store,
                $importe_laboral_store,
                $dias_vac_store,
                $importe_vac_store,
                $contribucion,
                $impuesto,
                $neto,
                roundExcel($contribucion + $impuesto, 2),
                $descripcion_nueva,
                $tipo_descuento_ajuste,
                $existente['id']
            ]);
            $nomina_id_final = $existente['id'];
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
                trabajador_id, periodo_desde, periodo_hasta, horas_laboradas, importe_salario_laboral,
                pago_resultado, total_salario_devengado, vacaciones_acumuladas_mes, importe_vacaciones_acumulado_mes,
                contribucion_especial, ingresos_personales, importe_neto,
                total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $trabajador_id, $periodo_desde, $periodo_hasta,
                $horas_nuevas, $importe_laboral,
                $monto_nuevo, $monto_nuevo, $dias_vac_calc, $importe_vac_calc,
                $contribucion, $impuesto, $neto,
                roundExcel($contribucion + $impuesto, 2),
                $tipo, 'borrador', "$concepto", $tipo_descuento_ajuste
            ]);
            $nomina_id_final = (int)$pdo->lastInsertId();
            $agregados++;
        }
        
        if ($modo_ajuste == 'liquidacion') {
            // Liquidación final de fracciones: rebajar el submayor de vacaciones
            // registrando el disfrute y descontando los días del acumulado del trabajador.
            $stmt_disf_prev = $pdo->prepare("SELECT dias FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'disfrute'");
            $stmt_disf_prev->execute([$nomina_id_final]);
            $dias_disf_prev = $stmt_disf_prev->fetchColumn();
            if ($dias_disf_prev !== false && floatval($dias_disf_prev) > 0) {
                $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas + ? WHERE id = ?")
                    ->execute([floatval($dias_disf_prev), $trabajador_id]);
                $pdo->prepare("DELETE FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'disfrute'")->execute([$nomina_id_final]);
            }
            $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas - ? WHERE id = ?")
                ->execute([$dias_vac_calc, $trabajador_id]);
            $pdo->prepare("INSERT INTO submayor_vacaciones 
                (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, 
                 nomina_id, tipo_nomina, referencia, usuario_registro, observaciones) 
                VALUES (?, ?, ?, 'disfrute', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $trabajador_id, $periodo_desde, $periodo_hasta,
                    $dias_vac_calc, $importe_vac_calc,
                    $nomina_id_final, $tipo,
                    "Liquidación fracción de vacaciones - Período: " . date('m/Y', strtotime($periodo_desde)),
                    $user_nombre_completo,
                    "Liquidación final de {$dias_vac_calc} días del submayor de vacaciones"
                ]);
        }
    }
    
    // Redirección con mensaje adecuado
    if ($agregados > 0 || $actualizados > 0) {
        $total = $agregados + $actualizados;
        logAction('generar_nomina_ajuste', 'nominas', 'Generación de nómina de ajuste' . ($modo_ajuste == 'liquidacion' ? ' (liquidación final)' : ''), ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'modo' => $modo_ajuste, 'trabajadores' => $total, 'nuevos' => $agregados, 'actualizados' => $actualizados], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        if ($modo_ajuste == 'liquidacion') {
            header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=liquidacion_procesada&count=$total&nuevos=$agregados&actualizados=$actualizados");
        } else {
            header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=ajuste_procesado&count=$total&nuevos=$agregados&actualizados=$actualizados");
        }
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
        logAction('agregar_vacaciones_nomina', 'nominas', 'Agregado de vacaciones a nómina', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'trabajadores' => $agregados], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
    logAction('agregar_bono_trabajador', 'nominas', 'Adición de bono a trabajador en nómina en borrador', ['trabajador_id' => (int)$trabajador_id, 'monto_bono' => $monto_bono, 'concepto' => $concepto], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=bono_added&count=1");
    exit;
}

if (isset($_POST['contabilizar_nomina'])) {
    if (!$puede_editar_nomina) { header('Location: ../dashboard.php'); exit; }
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
    
    // 1.5 REVISIÓN DE CUADRE PRE-CONTABILIZACIÓN
    // Antes de contabilizar, los borradores del período/tipo deben pasar el cuadre:
    // composición del devengado, aritmética de deducciones y neto, e impuestos (CESS/ISIP).
    $revision_cuadre = verificarCuadreValores($pdo, [
        'pendientes' => true,
        'periodo_desde' => $periodo_desde,
        'periodo_hasta' => $periodo_hasta,
        'tipo' => $tipo_contabilizar,
    ]);
    if ($revision_cuadre['filas_con_error'] > 0) {
        $_SESSION['cuadre_pendiente_errores'] = $revision_cuadre['errores'];
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo_contabilizar&error=cuadre_pendiente&filas=" . $revision_cuadre['filas_con_error'] . "&pd=" . urlencode($periodo_desde) . "&ph=" . urlencode($periodo_hasta));
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
            // LÓGICA ESPECÍFICA POR TIPO DE NÓMINA (AJUSTE POR HORAS)
            // =========================================================
            if ($tipo_contabilizar == 'ajuste') {
                $stmt = $pdo->prepare("
                    SELECT n.id, n.trabajador_id, n.horas_laboradas, n.vacaciones_acumuladas_mes,
                           n.importe_vacaciones_acumulado_mes, t.no_acumular_vacaciones
                    FROM nominas n
                    JOIN trabajadores t ON n.trabajador_id = t.id
                    WHERE n.periodo_desde = ? AND n.periodo_hasta = ?
                      AND n.tipo_nomina = 'ajuste' AND n.estado = 'borrador' AND n.numero_nomina IS NULL
                ");
                $stmt->execute([$periodo_desde, $periodo_hasta]);
                $ajustes_pendientes = $stmt->fetchAll();
                
                foreach ($ajustes_pendientes as $ajuste) {
                    $horas_laboradas_aj = floatval($ajuste['horas_laboradas'] ?? 0);
                    $dias_a_acumular = floatval($ajuste['vacaciones_acumuladas_mes'] ?? 0);
                    $importe_a_acumular = floatval($ajuste['importe_vacaciones_acumulado_mes'] ?? 0);
                    
                    // Solo acumula vacaciones la nómina de ajuste por horas trabajadas
                    if ($horas_laboradas_aj <= 0 || $dias_a_acumular <= 0) continue;
                    if (!empty($ajuste['no_acumular_vacaciones']) && $ajuste['no_acumular_vacaciones'] == 1) continue;
                    
                    $stmt_dias = $pdo->prepare("SELECT vacaciones_acumuladas FROM trabajadores WHERE id = ?");
                    $stmt_dias->execute([$ajuste['trabajador_id']]);
                    $dias_actuales = floatval($stmt_dias->fetchColumn());
                    
                    $stmt_importe = $pdo->prepare("SELECT COALESCE(SUM(importe_vacaciones_acumulado_mes), 0) 
                                                   FROM nominas 
                                                   WHERE trabajador_id = ? 
                                                   AND tipo_nomina IN ('automatica','ajuste')
                                                   AND estado = 'contabilizado'
                                                   AND periodo_desde < ?");
                    $stmt_importe->execute([$ajuste['trabajador_id'], $periodo_desde]);
                    $importe_anterior = floatval($stmt_importe->fetchColumn());
                    
                    $nuevo_total_dias = $dias_actuales + $dias_a_acumular;
                    $nuevo_total_importe = $importe_anterior + $importe_a_acumular;
                    
                    $pdo->prepare("UPDATE trabajadores 
                                  SET vacaciones_acumuladas = vacaciones_acumuladas + ? 
                                  WHERE id = ?")->execute([$dias_a_acumular, $ajuste['trabajador_id']]);
                    
                    $pdo->prepare("UPDATE nominas 
                                  SET total_vacaciones_acumuladas = ?,
                                      total_importe_vacaciones_acumuladas = ?
                                  WHERE id = ?")->execute([
                        $nuevo_total_dias,
                        $nuevo_total_importe,
                        $ajuste['id']
                    ]);
                    
                    // REGISTRAR EN SUBMAYOR DE VACACIONES - ACUMULACIÓN
                    $stmt_check_submayor = $pdo->prepare("SELECT COUNT(*) FROM submayor_vacaciones 
                                                          WHERE nomina_id = ? AND tipo_movimiento = 'acumulacion'");
                    $stmt_check_submayor->execute([$ajuste['id']]);
                    $ya_registrado = $stmt_check_submayor->fetchColumn();
                    
                    if (!$ya_registrado) {
                        $stmt_submayor = $pdo->prepare("INSERT INTO submayor_vacaciones 
                            (trabajador_id, periodo_desde, periodo_hasta, tipo_movimiento, dias, importe, 
                             nomina_id, tipo_nomina, referencia, usuario_registro, observaciones) 
                            VALUES (?, ?, ?, 'acumulacion', ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_submayor->execute([
                            $ajuste['trabajador_id'],
                            $periodo_desde,
                            $periodo_hasta,
                            $dias_a_acumular,
                            $importe_a_acumular,
                            $ajuste['id'],
                            'ajuste',
                            "Acumulación ajuste por horas (Período: " . date('m/Y', strtotime($periodo_desde)) . ")",
                            $user_nombre_completo,
                            "Acumulación de {$dias_a_acumular} días por {$horas_laboradas_aj} horas - Ajuste"
                        ]);
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
            
            logAction('contabilizar_nomina', 'nominas', 'Contabilización de nómina', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_contabilizar, 'numero_nomina' => $numero_nomina], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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

        if ($tipo_revertir == 'ajuste') {
            // Deshacer el efecto de vacaciones según el modo del ajuste:
            //  - modo 'horas': se sumaron días al contabilizar (submayor 'acumulacion') -> restarlos.
            //  - modo 'liquidacion': se restaron días al generar (submayor 'disfrute') -> devolverlos.
            //  - modo 'directo': no mueve días de vacaciones -> no hacer nada.
            foreach ($nominas_revertir as $nomina_r) {
                $stmt_sv = $pdo->prepare("SELECT dias FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'acumulacion' LIMIT 1");
                $stmt_sv->execute([$nomina_r['id']]);
                $fila_acum = $stmt_sv->fetch();
                if ($fila_acum) {
                    $dias_acum = floatval($fila_acum['dias']);
                    if ($dias_acum > 0) {
                        $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas - ? WHERE id = ?")->execute([$dias_acum, $nomina_r['trabajador_id']]);
                    }
                } else {
                    $stmt_sv2 = $pdo->prepare("SELECT dias FROM submayor_vacaciones WHERE nomina_id = ? AND tipo_movimiento = 'disfrute' LIMIT 1");
                    $stmt_sv2->execute([$nomina_r['id']]);
                    $fila_disf = $stmt_sv2->fetch();
                    if ($fila_disf) {
                        $dias_disf = floatval($fila_disf['dias']);
                        if ($dias_disf > 0) {
                            $pdo->prepare("UPDATE trabajadores SET vacaciones_acumuladas = vacaciones_acumuladas + ? WHERE id = ?")->execute([$dias_disf, $nomina_r['trabajador_id']]);
                        }
                    }
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

        logAction('revertir_nomina', 'nominas', 'Reversión de nómina contabilizada', ['periodo' => $periodo_desde . '-' . $periodo_hasta, 'tipo' => $tipo_revertir, 'numero_nomina' => $numero_revertir], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
    $noct_temprana = floatval($_POST['nocturnidad_temprana'] ?? 0);
    $noct_tardia = floatval($_POST['nocturnidad_tardia'] ?? 0);
    $doble_turno = floatval($_POST['doble_turno'] ?? 0);
    $concepto = trim($_POST['concepto_extra'] ?? '');
    $tipo = 'extraordinaria';
    $tasa_contribucion = getTasaContribucion($pdo);

    $check_auto = $pdo->prepare("SELECT COUNT(*) FROM nominas WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'automatica'");
    $check_auto->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
    if ($check_auto->fetchColumn() > 0) {
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&error=worker_in_automatica");
        exit;
    }

    $stmt_td_check = $pdo->prepare("SELECT tipo_descuento FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = 'extraordinaria' LIMIT 1");
    $stmt_td_check->execute([$periodo_desde, $periodo_hasta]);
    $tipo_descuento = $stmt_td_check->fetchColumn() ?: 'total_rangos';

    $stmt = $pdo->prepare("SELECT e.salario_hora_ordinaria FROM trabajadores t JOIN escalas_salariales e ON t.escala_salarial_id = e.id WHERE t.id = ?");
    $stmt->execute([$trabajador_id]);
    $salario_hora = floatval($stmt->fetchColumn());

    $importe_he_diurnas = roundExcel($salario_hora * $recargo_extra_diurna * $horas_extra, 2);
    $importe_noct_temprana = roundExcel($salario_hora * $recargo_extra_nocturna * $noct_temprana, 2);
    $importe_noct_tardia = roundExcel($salario_hora * $recargo_extra_nocturna * $noct_tardia, 2);
    $importe_doble_turno = roundExcel($salario_hora * $recargo_doble_turno * $doble_turno, 2);
    $total_devengado_nuevo = roundExcel($importe_he_diurnas + $importe_noct_temprana + $importe_noct_tardia + $importe_doble_turno, 2);

    $stmt_check = $pdo->prepare("SELECT id, total_salario_devengado FROM nominas
                                 WHERE trabajador_id = ? AND periodo_desde = ? AND periodo_hasta = ?
                                 AND tipo_nomina = 'extraordinaria' AND estado = 'borrador'");
    $stmt_check->execute([$trabajador_id, $periodo_desde, $periodo_hasta]);
    $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $total_horas_noct = $noct_temprana + $noct_tardia;
        $total_importe_noct = $importe_noct_temprana + $importe_noct_tardia;
        $total_importe_con_extra = $importe_he_diurnas + $importe_doble_turno;
        $total_horas_con_extra = $horas_extra + $doble_turno;

        $pdo->prepare("UPDATE nominas SET
            horas_laboradas = horas_laboradas + ?,
            horas_nocturnas = horas_nocturnas + ?,
            importe_horas_nocturnas = importe_horas_nocturnas + ?,
            horas_nocturnas_tempranas = horas_nocturnas_tempranas + ?,
            importe_nocturnas_tempranas = importe_nocturnas_tempranas + ?,
            horas_nocturnas_tardias = horas_nocturnas_tardias + ?,
            importe_nocturnas_tardias = importe_nocturnas_tardias + ?,
            horas_doble_turno = horas_doble_turno + ?,
            importe_doble_turno = importe_doble_turno + ?,
            importe_salario_laboral = importe_salario_laboral + ?,
            total_salario_devengado = total_salario_devengado + ?,
            descripcion = CONCAT(descripcion, ', ', ?)
            WHERE id = ?")
        ->execute([
            $total_horas_con_extra, $total_horas_noct, $total_importe_noct,
            $noct_temprana, $importe_noct_temprana,
            $noct_tardia, $importe_noct_tardia,
            $doble_turno, $importe_doble_turno,
            $total_importe_con_extra, $total_devengado_nuevo,
            $concepto, $existente['id']
        ]);

        $stmt_tot = $pdo->prepare("SELECT total_salario_devengado FROM nominas WHERE id = ?");
        $stmt_tot->execute([$existente['id']]);
        $total_devengado_final = floatval($stmt_tot->fetchColumn());

        if ($tipo_descuento == 'solo_cess') {
            $contribucion = calcularCessProgresivoPHP($total_devengado_final);
            $impuesto = 0;
        } else {
            $contribucion = roundExcel($total_devengado_final * ($tasa_contribucion / 100), 2);
            $impuesto = calcularTotalImpuesto($total_devengado_final, $rangos_impuesto);
        }
        $neto = roundExcel($total_devengado_final - ($contribucion + $impuesto), 2);

        $pdo->prepare("UPDATE nominas SET contribucion_especial = ?, ingresos_personales = ?, importe_neto = ?, total_deducciones = ? WHERE id = ?")
            ->execute([$contribucion, $impuesto, $neto, roundExcel($contribucion + $impuesto, 2), $existente['id']]);
        logAction('agregar_extraordinaria_trabajador', 'nominas', 'Adición de horas extraordinarias a trabajador (nómina existente)', ['trabajador_id' => (int)$trabajador_id, 'horas_extra' => $horas_extra, 'total_devengado' => $total_devengado_final], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        header("Location: nominas.php?periodo=$periodo&tipo=$tipo&msg=extra_added_existing");
    } else {
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
            horas_nocturnas_tempranas, importe_nocturnas_tempranas,
            horas_nocturnas_tardias, importe_nocturnas_tardias,
            horas_doble_turno, importe_doble_turno,
            importe_salario_laboral, total_salario_devengado,
            contribucion_especial, ingresos_personales, importe_neto,
            total_deducciones, tipo_nomina, estado, descripcion, tipo_descuento
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $trabajador_id, $periodo_desde, $periodo_hasta,
            $horas_extra, ($noct_temprana + $noct_tardia), ($importe_noct_temprana + $importe_noct_tardia),
            $noct_temprana, $importe_noct_temprana,
            $noct_tardia, $importe_noct_tardia,
            $doble_turno, $importe_doble_turno,
            $importe_he_diurnas + $importe_doble_turno, $total_devengado_nuevo,
            $contribucion, $impuesto, $neto,
            roundExcel($contribucion + $impuesto, 2),
            $tipo, 'borrador',
            "HE:{$horas_extra}h Nt7-23:{$noct_temprana}h Nt23-7:{$noct_tardia}h DT:{$doble_turno}h",
            $tipo_descuento
        ]);
        logAction('agregar_extraordinaria_trabajador', 'nominas', 'Adición de horas extraordinarias a trabajador (nómina nueva)', ['trabajador_id' => (int)$trabajador_id, 'horas_extra' => $horas_extra, 'total_devengado' => $total_devengado_nuevo], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
        $base = $salario_laboral;
        $pago_adicional_total = 0.0;
        $pagos_adicionales = getPagosPorTrabajador($pdo, $trabajador_id);
        foreach ($pagos_adicionales as $pa) {
            if ($pa['tipo_calculo'] === 'monto_fijo') {
                $pago_adicional_total += (float)$pa['monto'];
            } elseif ($pa['tipo_calculo'] === 'porcentaje') {
                $pago_adicional_total += (float)$pa['monto'] / 100 * $base;
            }
        }
        $pago_adicional_total = roundExcel($pago_adicional_total, 2);
        
        // Vacaciones proporcional
        $importe_vacaciones_adicional = 0;
        if ($trabajador['no_acumular_vacaciones'] == 1) {
            $dias_a_acumular = roundExcel(($horas_mensuales * $factor_909) / $horas_jornada, 2);
            $valor_por_dia = $salario_mensual / $dias_laborables;
            $importe_vacaciones_adicional = roundExcel($dias_a_acumular * $valor_por_dia, 2);
        }
        
        $total_devengado = $salario_laboral + $importe_vacaciones_adicional + $pago_adicional_total;
        
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
             importe_salario_laboral, otros_salarios, total_salario_devengado, contribucion_especial, 
             ingresos_personales, importe_neto, total_deducciones, tipo_nomina, estado, tipo_descuento) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        
        $result = $insert->execute([
            $trabajador_id, $periodo_desde, $periodo_hasta, $horas_mensuales, 0,
            $salario_laboral, $pago_adicional_total, $total_devengado, $contribucion, $impuesto, $neto,
            roundExcel($contribucion + $impuesto, 2),
            'automatica', 'borrador', $tipo_descuento
        ]);
        
        if ($result) {
            $id_nomina_insertado = $pdo->lastInsertId();
            foreach ($pagos_adicionales as $pa) {
                $importe = $pa['tipo_calculo'] === 'monto_fijo'
                    ? (float)$pa['monto']
                    : (float)$pa['monto'] / 100 * $base;
                $pdo->prepare("INSERT INTO nomina_pagos_adicionales (nomina_id, trabajador_id, pago_adicional_id, importe_aplicado) VALUES (?,?,?,?)")
                    ->execute([$id_nomina_insertado, $trabajador_id, $pa['id'], roundExcel($importe, 2)]);
            }
            $agregados++;
        }
    }
    
    if ($agregados > 0) {
        logAction('agregar_trabajador_nomina_automatica', 'nominas', 'Adición de trabajadores a nómina automática existente', ['trabajadores_agregados' => $agregados, 'con_errores' => count($errores)], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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

// Días a liquidar por trabajador (liquidación final de vacaciones): es el saldo actual
// acumulado (vacaciones_acumuladas). Al liquidarlo, el trabajador queda en cero días.
foreach ($trabajadores as &$t) {
    $t['dias_submayor_periodo'] = roundExcel(floatval($t['vacaciones_acumuladas'] ?? 0), 2);
}
unset($t);

// Lista ampliada para la liquidación final: incluye trabajadores de baja (inactivos),
// para poder liquidar la fracción de vacaciones de quien ya salió de la entidad.
$trabajadores_liquidacion = $trabajadores;
if ($tipo_nomina_activa == 'ajuste') {
    $stmt_todos = $pdo->prepare("SELECT t.*, e.salario_mensual, e.salario_hora_ordinaria, a.nombre_area, c.nombre as categoria_nombre, t.cuentabanc
                                 FROM trabajadores t
                                 JOIN escalas_salariales e ON t.escala_salarial_id = e.id
                                 LEFT JOIN areas a ON t.area_id = a.id
                                 LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
                                 ORDER BY t.nombre_completo");
    $stmt_todos->execute();
    $ids_activos = array_map(fn($t) => $t['id'], $trabajadores);
    foreach ($stmt_todos->fetchAll(PDO::FETCH_ASSOC) as $ti) {
        if (!in_array($ti['id'], $ids_activos)) {
            $trabajadores_liquidacion[] = $ti;
        }
    }
    foreach ($trabajadores_liquidacion as &$t) {
        $t['dias_acumulados'] = $t['vacaciones_acumuladas'] ?? 0;
        $t['valor_por_dia'] = $t['salario_mensual'] / $dias_laborables;
        $t['valor_acumulado'] = $t['dias_acumulados'] * $t['valor_por_dia'];
        $t['dias_submayor_periodo'] = roundExcel(floatval($t['vacaciones_acumuladas'] ?? 0), 2);
    }
    unset($t);
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
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Gestión de Nóminas - <?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></title>
    
	    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link href="../css/select2.min.css" rel="stylesheet">
	</title><link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">
	
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap5.3.0/buttons.dataTables.min.css">  
	
	<link href="CSS/nominas.css" rel="stylesheet">
<!-- ============================================ -->
<!-- CSS RESPONSIVE COMPLETO (PC / TABLET / MÓVIL) -->
<!-- Solo afecta a cada breakpoint; NO sobrescribe PC -->
<!-- ============================================ -->
<style>
/* ---------- BASE: solo lo seguro para todos los tamaños ---------- */
* { -webkit-tap-highlight-color: transparent; }

/* El wrapper de la tabla siempre permite scroll horizontal (útil en PC y móvil) */
.data-table-wrapper {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Evitar desbordes de modales muy anchos en pantallas medianas */
@media (max-width: 1200px) {
    .modal-dialog.modal-xl {
        max-width: calc(100% - 2rem);
    }
}

/* ============================================ */
/* TABLET (≤ 992px)                             */
/* ============================================ */
@media (max-width: 992px) {
    .page-title h1 { font-size: 1rem; }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .win-topbar {
        flex-wrap: wrap;
        gap: 0.625rem;
    }
    .win-topbar > div {
        flex-wrap: wrap;
    }

    #tablaNominas th,
    #tablaNominas td {
        font-size: 0.72rem;
        padding: 0.35rem 0.4rem;
    }
}

/* ============================================ */
/* MÓVIL (≤ 768px)                              */
/* ============================================ */
@media (max-width: 768px) {

    /* ---------- TOPBAR ---------- */
    .win-topbar {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.5rem;
        padding: 0.625rem !important;
    }
    .win-topbar > div {
        justify-content: space-between;
        width: 100%;
    }
    .page-title h1 {
        font-size: 0.95rem;
        line-height: 1.2;
    }
    .page-title p { display: none; }

    /* ---------- SELECTOR TIPO NÓMINA: solo en móvil ---------- */
    .tipo-nomina-selector-custom {
        width: 100% !important;
        max-width: 100%;
    }
    .tipo-nomina-preview {
        justify-content: space-between;
        width: 100%;
    }

    /* ---------- STATS ---------- */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem;
    }
    .stat-card h3 { font-size: 1rem !important; }
    .stat-card h6 { font-size: 0.68rem !important; }

    /* ---------- FILTROS DEL CARD CONSULTA RÁPIDA ---------- */
    .glass-card .row.g-2 > [class*="col-md-"] {
        flex: 1 1 100%;
        max-width: 100%;
    }

    /* ---------- BOTONES DE OPCIONES EN FILA COMPLETA ---------- */
    #btnActualizarPagina,
    #btnRegresarInicio,
    #btnFullHistorialBonos,
    #btnListadoDevengado {
        width: 100%;
        justify-content: center;
    }

    /* ---------- TABLA PRINCIPAL ---------- */
    .data-table-wrapper {
        border-radius: 0.5rem;
        position: relative;
    }
    .data-table-wrapper::after {
        content: '⟷ Desliza para ver más';
        display: block;
        text-align: center;
        font-size: 0.65rem;
        color: rgba(255,255,255,0.4);
        padding: 0.25rem 0;
        background: rgba(0,0,0,0.2);
    }
    #tablaNominas th,
    #tablaNominas td {
        font-size: 0.68rem;
        padding: 0.3rem 0.35rem;
        white-space: nowrap;
    }

    /* ---------- DATATABLE CONTROLS ---------- */
    .dt-length,
    .dt-search,
    .dt-buttons,
    .dt-colvis {
        width: 100% !important;
        text-align: center !important;
        margin-bottom: 0.5rem;
    }
    .dt-buttons .btn-win {
        margin: 0.125rem !important;
        padding: 0.3rem 0.5rem !important;
        font-size: 0.7rem !important;
    }
    .dt-search .input-group {
        width: 100% !important;
    }

    /* ---------- FILTROS DE LA TABLA ---------- */
    #nomFiltrosBody .col-md-3,
    #nomFiltrosBody .col-md-4 {
        flex: 1 1 100%;
        max-width: 100%;
    }

    /* ---------- MODALES A PANTALLA COMPLETA ---------- */
    .modal-dialog {
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .modal-dialog.modal-lg,
    .modal-dialog.modal-xl {
        max-width: 100% !important;
    }
    .modal-content {
        border-radius: 0 !important;
        min-height: 100vh;
    }
    .modal-body {
        max-height: calc(100vh - 10rem);
        padding: 0.875rem !important;
        overflow-y: auto;
    }
    .modal-header,
    .modal-footer {
        padding: 0.625rem 0.875rem !important;
    }
    .modal-footer button {
        flex: 1 1 100%;
    }

    /* ---------- NAVEGACIÓN DE REGISTROS DEL MODAL ---------- */
    #btnModalPrimero, #btnModalAnterior,
    #btnModalSiguiente, #btnModalUltimo {
        flex: 1 1 45% !important;
        font-size: 0.7rem !important;
        padding: 0.4rem 0.5rem !important;
    }
    #btnModalActualizar, #btnModalReset {
        flex: 1 1 100% !important;
    }

    /* ---------- BUSCADOR EN MODAL ---------- */
    .buscador-trabajador-modal {
        min-width: 0 !important;
        width: 100% !important;
    }

    /* ---------- CARDS DE IMPRESIÓN ---------- */
    .print-option-card {
        padding: 0.75rem !important;
    }
    .print-option-card .card-title { font-size: 0.8rem !important; }
    .print-option-card .card-desc  { font-size: 0.68rem !important; }

    /* ---------- PANEL DE CUADRE ---------- */
    .glass-card .d-flex.align-items-center.justify-content-between {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.75rem;
    }

    /* ---------- BOTONES FLOTANTES ---------- */
    .scroll-quick-btns {
        right: 0.75rem !important;
        bottom: 0.75rem !important;
        gap: 0.5rem !important;
    }
    .scroll-quick-btn {
        width: 2.25rem !important;
        height: 2.25rem !important;
        font-size: 0.85rem !important;
    }

    /* ---------- HEADER DE AJUSTE ---------- */
    #accionesBorradorAjuste {
        flex-wrap: wrap;
    }
    #accionesBorradorAjuste .btn-win-success,
    #accionesBorradorAjuste .btn-win-info,
    #accionesBorradorAjuste .btn-win-warning,
    #accionesBorradorAjuste .btn-win-danger {
        flex: 1 1 45%;
        font-size: 0.72rem;
    }

    /* ---------- PAGINACIÓN DATATABLES ---------- */
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        font-size: 0.7rem !important;
        text-align: center !important;
        width: 100% !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.2rem 0.4rem !important;
        font-size: 0.7rem !important;
    }
}

/* ============================================ */
/* MÓVIL PEQUEÑO (≤ 480px)                      */
/* ============================================ */
@media (max-width: 480px) {
    .page-title h1 { font-size: 0.85rem; }

    .stats-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 0.375rem;
    }
    .stat-card h3 { font-size: 0.9rem !important; }
    .stat-card h6 { font-size: 0.6rem !important; }
    .stat-card .stat-icon { font-size: 1.2rem !important; }

    .tipo-nomina-preview span { font-size: 0.72rem; }
    .tipo-nomina-preview { padding: 0.4rem 0.6rem; }

    #tablaNominas th,
    #tablaNominas td {
        font-size: 0.62rem;
        padding: 0.25rem 0.3rem;
    }

    .btn-win, .btn-win-primary, .btn-win-success,
    .btn-win-info, .btn-win-warning, .btn-win-danger {
        font-size: 0.7rem !important;
        padding: 0.35rem 0.55rem !important;
    }

    #btnModalPrimero, #btnModalAnterior,
    #btnModalSiguiente, #btnModalUltimo {
        font-size: 0.62rem !important;
        padding: 0.35rem 0.3rem !important;
        flex: 1 1 48% !important;
    }
}

/* ============================================ */
/* PANTALLAS GRANDES (≥ 1400px) — NO sobrescribe */
/* ============================================ */
@media (min-width: 1400px) {
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
}
</style>

</head>
<body class="<?php echo trim((!$puede_editar_nomina ? 'solo-lectura' : '') . (!$puede_eliminar_nomina ? ' solo-eliminar' : '')); ?>">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    
<!-- Top Bar Windows 11 -->
<div class="win-topbar fade-in-up">
    <div class="d-flex align-items-center gap-3">
        <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
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
            <div class="tipo-nomina-selector-custom" title="Seleccione el Tipo de Nómina" data-tooltip="Seleccione el Tipo de Nómina" data-tooltip-theme="warning">
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
                <li><h6 class="dropdown-header text-light px-3 py-1">Reportes Oficiales</h6></li>
                <li>
                    <a class="dropdown-item" href="#" id="menuNominaImpresa">
                        <i class="fas fa-print text-primary me-2"></i> 
                        Nómina Impresa
                        <small class="d-block text-muted">Modelo SC-4-06 con agrupaciones</small>
                    </a>
                </li>
                <li><a class="dropdown-item" href="#" id="menuHistorialMontos"><i class="fas fa-history text-warning me-2"></i> Historial de Montos <small class="d-block text-muted">Historial Montos Redistribuidos</small></a></li>
                <li><a class="dropdown-item" href="#" id="menuResumenSalarial"><i class="fas fa-user-tie text-success me-2"></i> RESUMEN SALARIAL <small class="d-block text-muted">Resumen por trabajador o por cuenta bancaria</small></a></li>
                <li><a class="dropdown-item" href="#" id="menuSinNomina"><i class="fas fa-user-slash text-danger me-2"></i> Trabajadores Sin Nómina <small class="d-block text-muted">Alta en el período sin nómina en ese mes</small></a></li>
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
        
        <!-- Menú de usuario unificado (sin reloj) -->
        <?php include '../includes/user_menu.php'; 
        ?>
    </div>
</div>

    <!-- CUADRE DE NÓMINAS CONTABILIZADAS -->
    <?php $cuadre = verificarCuadreValores($pdo); ?>
    <div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.15s;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:2.625rem; height:2.625rem; border-radius: 0.625rem; display: flex; align-items: center; justify-content: center; background: <?php echo $cuadre['filas_con_error'] > 0 ? 'rgba(248,113,113,0.15)' : 'rgba(var(--color-success-soft-rgb),0.15)'; ?>;">
                    <i class="fas fa-scale-balanced" style="color: <?php echo $cuadre['filas_con_error'] > 0 ? '#f87171' : 'var(--color-success-soft)'; ?>;"></i>
                </div>
                <div>
                    <h5 class="text-white mb-0" style="font-weight: 600; font-size:0.95rem;">Cuadre de Nóminas Contabilizadas
                        <?php if (($cuadre['filas_con_error'] > 0) || (($cuadre['cierres_con_error'] ?? 0) > 0)): ?>
                            <span class="badge" style="background: rgba(248,113,113,0.15); color: #f87171; margin-left:0.5rem;"><i class="fas fa-exclamation-triangle me-1"></i><?php echo $cuadre['filas_con_error']; ?> fila<?php echo $cuadre['filas_con_error'] === 1 ? '' : 's'; ?><?php if (($cuadre['cierres_con_error'] ?? 0) > 0): ?> · <?php echo $cuadre['cierres_con_error']; ?> cierre<?php echo $cuadre['cierres_con_error'] === 1 ? '' : 's'; ?> con descuadres<?php endif; ?></span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(var(--color-success-soft-rgb),0.15); color: var(--color-success-soft); margin-left:0.5rem;"><i class="fas fa-check-circle me-1"></i>Sin descuadres</span>
                        <?php endif; ?>
                    </h5>
                    <p class="mb-0" style="font-size:0.75rem; color: rgba(255,255,255,0.5);">Revalida la composición del devengado, la aritmética, los impuestos (CESS / ISIP) y los totales de los cierres registrados.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <div class="text-center px-2">
                    <div style="font-size:1.2rem; font-weight: 700; color: #93c5fd;"><?php echo $cuadre['filas']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Filas contabilizadas</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: #a78bfa;"><?php echo $cuadre['nominas']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Nóminas contabilizadas</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: #fbbf24;"><?php echo $cuadre['cierres_total']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Cierres registrados</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: <?php echo ($cuadre['cierres_con_error'] ?? 0) > 0 ? '#f87171' : 'var(--color-success-soft)'; ?>;"><?php echo $cuadre['cierres_con_error'] ?? 0; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Cierres con error</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: <?php echo $cuadre['errores_cierre_total'] > 0 ? '#f87171' : 'var(--color-success-soft)'; ?>;"><?php echo $cuadre['errores_cierre_total']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Errores cierres</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: <?php echo $cuadre['errores_aritmetica'] > 0 ? '#f87171' : 'var(--color-success-soft)'; ?>;"><?php echo $cuadre['errores_aritmetica']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Errores aritmética</div>
                </div>
                <div class="text-center px-2" style="border-left: 0.0625rem solid rgba(255,255,255,0.1);">
                    <div style="font-size:1.2rem; font-weight: 700; color: <?php echo $cuadre['errores_impuestos'] > 0 ? '#f87171' : 'var(--color-success-soft)'; ?>;"><?php echo $cuadre['errores_impuestos']; ?></div>
                    <div style="font-size:0.68rem; color: rgba(255,255,255,0.55);">Errores impuestos</div>
                </div>
                <button type="button" class="btn-win-primary" id="btnVerificarCuadre" title="Verificar cuadre de nómina" data-tooltip="Verificar cuadre de nómina" data-tooltip-theme="primary" style="padding:0.5rem 1rem; font-size:0.8rem;">
                    <i class="fas fa-clipboard-check me-1"></i> Verificar Cuadre
                </button>
            </div>
        </div>
    </div>

<style>
@keyframes cuadrePulse {
    0%, 100% { color: #f87171; text-shadow: 0 0 0.5rem rgba(248,113,113,0.6); }
    50% { color: #fca5a5; text-shadow: 0 0 1rem rgba(248,113,113,0.9); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnVerificarCuadre');
    var modalEl = document.getElementById('modalCuadre');
    if (!btn || !modalEl) return;
    var CUADRE = <?php echo json_encode($cuadre, JSON_UNESCAPED_UNICODE); ?>;

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function filasHtml(r) {
        if (r.filas_con_error <= 0 && (r.cierres_con_error || 0) <= 0) {
            return '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-check-circle me-2 cuadre-val-recalculado"></i>Sin descuadres: <b>todas las nóminas contabilizadas cuadran.</b></td></tr>';
        }
        var html = '';
        for (var i = 0; i < r.errores.length; i++) {
            var e = r.errores[i];
            if (e.es_cierre) {
                html += '<tr>'
                    + '<td><a class="cuadre-link-num" href="nominas.php?periodo=' + escHtml(e.periodo ? e.periodo.substring(0, 7) : '') + '&tipo=' + escHtml(e.tipo) + '&numero_nomina=' + escHtml(e.numero) + '" title="Abrir esta nómina">' + escHtml(e.numero) + '</a> <small class="text-muted">(' + escHtml(e.tipo) + ')</small></td>'
                    + '<td><span class="badge" style="background:rgba(251,191,36,0.15);color:#fbbf24;">CIERRE</span></td>'
                    + '<td>' + escHtml(e.periodo ? e.periodo.substring(0, 7) : '') + '</td>'
                    + '<td class="text-muted">— (verificar totales del cierre)</td>'
                    + '<td>' + escHtml(e.detalle) + '</td>'
                    + '<td class="text-end cuadre-val-almacenado">' + escHtml(e.encontrado) + '</td>'
                    + '<td class="text-end cuadre-val-recalculado">' + escHtml(e.esperado) + '</td>'
                    + '<td class="text-end cuadre-val-diferencia">' + escHtml(e.diferencia) + '</td>'
                    + '</tr>';
                continue;
            }
            html += '<tr>'
                + '<td><a class="cuadre-link-num" href="nominas.php?periodo=' + escHtml(e.periodo ? e.periodo.substring(0, 7) : '') + '&tipo=' + escHtml(e.tipo) + '&numero_nomina=' + escHtml(e.numero) + '" title="Abrir esta nómina">' + escHtml(e.numero) + '</a> <small class="text-muted">(' + escHtml(e.tipo) + ')</small></td>'
                + '<td><a class="cuadre-link-id" href="empleados.php?editar=' + escHtml(e.trabajador_id) + '" title="Abrir ficha del empleado">' + escHtml(e.trabajador_id) + '</a></td>'
                + '<td>' + escHtml(e.periodo ? e.periodo.substring(0, 7) : '') + '</td>'
                + '<td><a class="cuadre-link" href="empleados.php?editar=' + escHtml(e.trabajador_id) + '" title="Abrir ficha del empleado">' + escHtml(e.trabajador) + '</a></td>'
                + '<td>' + escHtml(e.detalle) + '</td>'
                + '<td class="text-end cuadre-val-almacenado">' + escHtml(e.encontrado) + '</td>'
                + '<td class="text-end cuadre-val-recalculado">' + escHtml(e.esperado) + '</td>'
                + '<td class="text-end cuadre-val-diferencia">' + escHtml(e.diferencia) + '</td>'
                + '</tr>';
        }
        return html;
    }

    var TIPOS_CUADRE = [
        ['automatica', 'fa-calendar-alt', 'Automática'],
        ['extraordinaria', 'fa-clock', 'Extraordinaria'],
        ['vacaciones', 'fa-umbrella-beach', 'Vacaciones'],
        ['bono', 'fa-gift', 'Bono'],
        ['ajuste', 'fa-pen', 'Ajuste']
    ];

    function resumenTiposHtml(r) {
        var html = '';
        for (var i = 0; i < TIPOS_CUADRE.length; i++) {
            var t = TIPOS_CUADRE[i];
            var d = (r.por_tipo && r.por_tipo[t[0]]) ? r.por_tipo[t[0]] : { filas: 0, con_error: 0, errores: 0 };
            var err = parseInt(d.errores || 0, 10);
            var fil = parseInt(d.filas || 0, 10);
            var color = err > 0 ? '#f87171' : '#34d399';
            var bg = err > 0 ? 'rgba(248,113,113,0.12)' : 'rgba(52,211,153,0.12)';
            html += '<span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded" style="font-size:0.72rem; font-weight:600; color:' + color + '; background:' + bg + '; border:0.0625rem solid ' + color + '44;">'
                + '<i class="fas ' + t[1] + '"></i>' + escHtml(t[2]) + ': ' + err
                + (fil > 0 ? '<small style="opacity:0.7;">(' + fil + ' fila' + (fil === 1 ? '' : 's') + ')</small>' : '')
                + '</span>';
        }
        return html;
    }

    function mostrarModal(r) {
        var hay = r.filas_con_error > 0 || (r.cierres_con_error || 0) > 0;
        var alerta = document.getElementById('cuadreAlerta');
        if (hay) {
            var partes = [];
            if (r.filas_con_error > 0) {
                partes.push('<strong>' + r.filas_con_error + '</strong> fila(s)');
            }
            if (r.cierres_con_error > 0) {
                partes.push('<strong>' + r.cierres_con_error + '</strong> cierre(s)');
            }
            alerta.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Se detectaron <strong>' + r.errores_total + '</strong> descuadre(s) en ' + partes.join(' y ') + ': <strong>' + r.errores_impuestos + '</strong> de impuestos, <strong>' + r.errores_aritmetica + '</strong> de aritmética (devengado, deducciones o neto) y <strong>' + (r.errores_cierre_total || 0) + '</strong> de cierres.';
            alerta.style.background = 'rgba(239,68,68,0.12)';
            alerta.style.color = '#fecaca';
            alerta.style.borderColor = 'rgba(239,68,68,0.35)';
        } else {
            alerta.innerHTML = '<i class="fas fa-check-circle me-2"></i>Las <strong>' + r.filas + '</strong> filas de <strong>' + r.nominas + '</strong> nóminas contabilizadas cuadran correctamente, incluyendo los <strong>' + (r.cierres_total || 0) + '</strong> cierres registrados: devengado, CESS, ISIP, deducciones, neto y vacaciones coinciden.';
            alerta.style.background = 'rgba(52,211,153,0.12)';
            alerta.style.color = '#a7f3d0';
            alerta.style.borderColor = 'rgba(52,211,153,0.35)';
        }
        document.getElementById('cuadreTbody').innerHTML = filasHtml(r);
        document.getElementById('cuadreNota').textContent = (r.errores.length < r.errores_total) ? 'Mostrando los primeros ' + r.errores.length + ' de ' + r.errores_total + ' descuadres.' : '';
        var resumenTipos = document.getElementById('cuadreResumenTipos');
        if (resumenTipos) resumenTipos.innerHTML = resumenTiposHtml(r);
        document.getElementById('modalCuadreTitulo').innerHTML = hay
            ? '<i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Cuadre de Nóminas Contabilizadas'
            : '<i class="fas fa-check-circle" style="color:#34d399;"></i> Cuadre de Nóminas Contabilizadas';
        document.getElementById('modalCuadreTitulo').style.color = hay ? '#f87171' : '';
        document.getElementById('modalCuadreTitulo').style.animation = hay ? 'cuadrePulse 0.5s ease-in-out infinite' : '';
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        var btnCorr = document.getElementById('btnCorregirCuadre');
        if (btnCorr) btnCorr.style.display = (r.filas_con_error > 0) ? '' : 'none';
        var btnCorrCierres = document.getElementById('btnCorregirCierres');
        if (btnCorrCierres) btnCorrCierres.style.display = (r.cierres_con_error > 0) ? '' : 'none';
        var cuadreDropModal = document.getElementById('cuadreDropModal');
        if (cuadreDropModal) {
            window.cuadreDropData = window.cuadreDropData || {};
            window.cuadreDropData['cuadre_modal'] = { rep: r, titulo: 'N\u00d3MINAS CONTABILIZADAS' };
            if (!hay) {
                cuadreDropModal.style.opacity = '0.3';
                cuadreDropModal.style.pointerEvents = 'none';
            } else {
                cuadreDropModal.style.opacity = '';
                cuadreDropModal.style.pointerEvents = '';
            }
        }
    }

    var btnCorregir = document.getElementById('btnCorregirCuadre');
    if (btnCorregir) {
        btnCorregir.addEventListener('click', function () {
            Swal.fire({
                title: '<i class="fas fa-calculator me-2" style="color:#f59e0b;"></i>¿Procede a recalcular?',
                html: 'Se recalcularán y guardarán los valores correctos (devengado, CESS, ISIP, deducciones y neto) en <strong>todas las nóminas contabilizadas con descuadres</strong>.<br><br>Esta acción sobrescribe los valores actuales y es irreversible.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#475569',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Sí, recalcular',
                cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i>Recalculando...',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); },
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
                fetch('nominas.php?action=corregir_cuadre&ajax=1', { cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            Swal.fire({
                                title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Recálculo completado',
                                html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> fila(s).<br>Descuadres restantes: <strong>' + data.errores_total + '</strong>.',
                                icon: 'success',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            }).then(function () { location.reload(); });
                        } else {
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                                text: (data && data.message) ? data.message : 'No se pudo recalcular.',
                                icon: 'error',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            });
                        }
                    })
                    .catch(function () {
                        Swal.fire({
                            title: '<i class="fas fa-wifi me-2"></i>Error',
                            text: 'No se pudo conectar para recalcular.',
                            icon: 'error',
                            background: '#1a1a2e',
                            color: '#ffffff',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                        });
                    });
            });
        });
    }

    var btnCorrCierres = document.getElementById('btnCorregirCierres');
    if (btnCorrCierres) {
        btnCorrCierres.addEventListener('click', function () {
            Swal.fire({
                title: '<i class="fas fa-rotate me-2" style="color:#f59e0b;"></i>¿Recalcular los cierres descuadrados?',
                html: 'Se recalcularán los totales de <strong>cierres_nomina</strong> (trabajadores, devengado, deducciones, neto, contribución y vacaciones) desde la suma real de sus filas contabilizadas. Esta acción sobrescribe los valores actuales y es irreversible.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#475569',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Sí, recalcular cierres',
                cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
                background: '#1a1a2e',
                color: '#ffffff'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                Swal.fire({
                    title: '<i class="fas fa-spinner fa-spin me-2"></i>Recalculando cierres...',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); },
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
                fetch('nominas.php?action=corregir_cierres&ajax=1', { cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            Swal.fire({
                                title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Recálculo de cierres completado',
                                html: 'Se corrigieron <strong>' + data.cierres_corregidos + '</strong> cierre(s).<br>Cierres con descuadre restantes: <strong>' + data.cierres_con_error + '</strong>.',
                                icon: 'success',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            }).then(function () { location.reload(); });
                        } else {
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                                text: (data && data.message) ? data.message : 'No se pudieron recalcular los cierres.',
                                icon: 'error',
                                background: '#1a1a2e',
                                color: '#ffffff',
                                confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                            });
                        }
                    })
                    .catch(function () {
                        Swal.fire({
                            title: '<i class="fas fa-wifi me-2"></i>Error',
                            text: 'No se pudo conectar para recalcular los cierres.',
                            icon: 'error',
                            background: '#1a1a2e',
                            color: '#ffffff',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                        });
                    });
            });
        });
    }

    btn.addEventListener('click', function () {
        fetch('nominas.php?action=verificar_cuadre&ajax=1', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (report) {
                if (report && typeof report.filas !== 'undefined') {
                    mostrarModal(report);
                } else {
                    mostrarModal(CUADRE);
                }
            })
            .catch(function () { mostrarModal(CUADRE); });
    });

    // Auto-disparo: si hay descuadres (filas o cierres) al cargar la página, se muestra el modal automáticamente.
    if ((CUADRE.filas_con_error || 0) > 0 || (CUADRE.cierres_con_error || 0) > 0) {
        mostrarModal(CUADRE);
    }
});
</script>

<!-- ==========================================
     MODAL DE CUADRE DE NÓMINAS CONTABILIZADAS
     ========================================== -->
<style>
    .cuadre-link { color: inherit; text-decoration: none; }
    .cuadre-link:hover { text-decoration: underline; opacity: 0.85; }
    .cuadre-link-id { color: #93c5fd; text-decoration: none; font-weight: 600; }
    .cuadre-link-id:hover { text-decoration: underline; color: #bfdbfe; }
    .cuadre-link-num { color: #60a5fa; text-decoration: none; font-weight: 600; }
    .cuadre-link-num:hover { text-decoration: underline; color: #bfdbfe; }
</style>
<div class="modal fade" id="modalCuadre" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCuadreTitulo"><i class="fas fa-scale-balanced" style="color:#60a5fa;"></i> Cuadre de Nóminas Contabilizadas</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <div class="alert mb-3" id="cuadreAlerta" style="border-radius:0.625rem; padding:0.75rem 1rem; <?php echo ($cuadre['filas_con_error'] > 0) || (($cuadre['cierres_con_error'] ?? 0) > 0) ? 'border:0.0625rem solid rgba(239,68,68,0.35); background:rgba(239,68,68,0.12); color:#fecaca;' : 'border:0.0625rem solid rgba(var(--color-success-soft-rgb),0.35); background:rgba(var(--color-success-soft-rgb),0.12); color:#a7f3d0;'; ?>">
                    <?php if (($cuadre['filas_con_error'] > 0) || (($cuadre['cierres_con_error'] ?? 0) > 0)): ?>
                        <i class="fas fa-exclamation-triangle me-2"></i>Se detectaron <strong><?php echo $cuadre['errores_total']; ?></strong> descuadre(s) en <strong><?php echo $cuadre['filas_con_error']; ?></strong> fila(s)<?php if (($cuadre['cierres_con_error'] ?? 0) > 0): ?> y <strong><?php echo $cuadre['cierres_con_error']; ?></strong> cierre(s)<?php endif; ?>: <strong><?php echo $cuadre['errores_impuestos']; ?></strong> de impuestos y <strong><?php echo $cuadre['errores_aritmetica']; ?></strong> de aritmética (devengado, deducciones o neto)<?php if (($cuadre['errores_cierre_total'] ?? 0) > 0): ?> y <strong><?php echo $cuadre['errores_cierre_total']; ?></strong> de cierres<?php endif; ?>.
                    <?php else: ?>
                        <i class="fas fa-check-circle me-2"></i>Las <strong><?php echo $cuadre['filas']; ?></strong> filas de <strong><?php echo $cuadre['nominas']; ?></strong> nóminas contabilizadas cuadran correctamente, incluyendo los <strong><?php echo $cuadre['cierres_total']; ?></strong> cierres registrados: devengado, CESS, ISIP, deducciones y neto coinciden.
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3" id="cuadreResumenTipos">
                    <?php
                    $tipos_cuadre_meta = [
                        'automatica' => ['fa-calendar-alt', 'Automática'],
                        'extraordinaria' => ['fa-clock', 'Extraordinaria'],
                        'vacaciones' => ['fa-umbrella-beach', 'Vacaciones'],
                        'bono' => ['fa-gift', 'Bono'],
                        'ajuste' => ['fa-pen', 'Ajuste'],
                    ];
                    foreach ($tipos_cuadre_meta as $tk => $tm):
                        $td_c = $cuadre['por_tipo'][$tk] ?? ['filas' => 0, 'con_error' => 0, 'errores' => 0];
                        $err_c = intval($td_c['errores']);
                        $chip_color = $err_c > 0 ? '#f87171' : 'var(--color-success-soft)';
                        $chip_bg = $err_c > 0 ? 'rgba(248,113,113,0.12)' : 'rgba(var(--color-success-soft-rgb),0.12)';
                    ?>
                    <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded" style="font-size:0.72rem; font-weight:600; color:<?php echo $chip_color; ?>; background:<?php echo $chip_bg; ?>; border:0.0625rem solid <?php echo $chip_color; ?>44;">
                        <i class="fas <?php echo $tm[0]; ?>"></i>
                        <?php echo $tm[1]; ?>: <?php echo $err_c; ?>
                        <?php if (intval($td_c['filas']) > 0): ?><small style="opacity:0.7;">(<?php echo intval($td_c['filas']); ?> fila<?php echo intval($td_c['filas']) === 1 ? '' : 's'; ?>)</small><?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div style="max-height:55vh; overflow-x:auto; overflow-y:auto; border:0.0625rem solid rgba(255,255,255,0.1); border-radius:0.5rem;">
                    <table class="table table-sm table-dark align-middle mb-0" style="font-size:0.78rem;">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Período</th>
                                <th>Trabajador</th>
                                <th>Verificación</th>
                                <th class="text-end">Almacenado</th>
                                <th class="text-end">Recalculado</th>
                                <th class="text-end">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody id="cuadreTbody">
                            <?php if ($cuadre['filas_con_error'] > 0 || (($cuadre['cierres_con_error'] ?? 0) > 0)): ?>
                                <?php foreach ($cuadre['errores'] as $e): ?>
                                    <?php if (!empty($e['es_cierre'])): ?>
                                        <tr>
                                            <td><a class="cuadre-link-num" href="nominas.php?periodo=<?php echo htmlspecialchars(substr($e['periodo'], 0, 7)); ?>&tipo=<?php echo htmlspecialchars($e['tipo']); ?>&numero_nomina=<?php echo htmlspecialchars($e['numero']); ?>" title="Abrir esta nómina" data-tooltip="Abrir esta nómina" data-tooltip-theme="primary"><?php echo htmlspecialchars($e['numero']); ?></a> <small class="text-muted">(<?php echo htmlspecialchars($e['tipo']); ?>)</small></td>
                                            <td><span style="background:rgba(251,191,36,0.15);color:#fbbf24;padding:0.125rem 0.5rem;border-radius:0.375rem;font-size:0.7rem;font-weight:600;">CIERRE</span></td>
                                            <td><?php echo htmlspecialchars(substr($e['periodo'], 0, 7)); ?></td>
                                            <td class="text-muted">— (verificar totales del cierre)</td>
                                            <td><?php echo htmlspecialchars($e['detalle']); ?></td>
                                            <td class="text-end cuadre-val-almacenado"><?php echo htmlspecialchars($e['encontrado']); ?></td>
                                            <td class="text-end cuadre-val-recalculado"><?php echo htmlspecialchars($e['esperado']); ?></td>
                                            <td class="text-end cuadre-val-diferencia"><?php echo htmlspecialchars($e['diferencia']); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td><a class="cuadre-link-num" href="nominas.php?periodo=<?php echo htmlspecialchars(substr($e['periodo'], 0, 7)); ?>&tipo=<?php echo htmlspecialchars($e['tipo']); ?>&numero_nomina=<?php echo htmlspecialchars($e['numero']); ?>" title="Abrir esta nómina" data-tooltip="Abrir esta nómina" data-tooltip-theme="primary"><?php echo htmlspecialchars($e['numero']); ?></a> <small class="text-muted">(<?php echo htmlspecialchars($e['tipo']); ?>)</small></td>
                                            <td><a class="cuadre-link-id" href="empleados.php?editar=<?php echo (int)$e['trabajador_id']; ?>" title="Abrir ficha del empleado" data-tooltip="Abrir ficha del empleado" data-tooltip-theme="info"><?php echo htmlspecialchars($e['trabajador_id']); ?></a></td>
                                            <td><?php echo htmlspecialchars(substr($e['periodo'], 0, 7)); ?></td>
                                            <td><a class="cuadre-link" href="empleados.php?editar=<?php echo (int)$e['trabajador_id']; ?>" title="Abrir ficha del empleado" data-tooltip="Abrir ficha del empleado" data-tooltip-theme="info"><?php echo htmlspecialchars($e['trabajador']); ?></a></td>
                                            <td><?php echo htmlspecialchars($e['detalle']); ?></td>
                                            <td class="text-end cuadre-val-almacenado"><?php echo htmlspecialchars($e['encontrado']); ?></td>
                                            <td class="text-end cuadre-val-recalculado"><?php echo htmlspecialchars($e['esperado']); ?></td>
                                            <td class="text-end cuadre-val-diferencia"><?php echo htmlspecialchars($e['diferencia']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center py-4"><i class="fas fa-check-circle me-2 cuadre-val-recalculado"></i>Sin descuadres: <b>todas las nóminas contabilizadas cuadran.</b></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="cuadreNota" class="mt-2 cuadre-nota"></div>
            </div>
            <div class="modal-footer" style="border-top:0.0625rem solid rgba(255,255,255,0.1);">
                <?php if ($cuadre['filas_con_error'] > 0): ?>
                <button type="button" class="btn-win-success" id="btnCorregirCuadre" title="Recalcular y corregir cuadre" data-tooltip="Recalcular y corregir cuadre" data-tooltip-theme="success" style="padding:0.5rem 1.125rem; font-size:0.8rem;">
                    <i class="fas fa-calculator me-1"></i> Recalcular
                </button>
                <?php endif; ?>
                <?php if (($cuadre['cierres_con_error'] ?? 0) > 0): ?>
                <button type="button" class="btn-win-warning" id="btnCorregirCierres" title="Recalcular totales de cierres descuadrados" data-tooltip="Recalcular totales de cierres descuadrados" data-tooltip-theme="warning" style="padding:0.5rem 1.125rem; font-size:0.8rem;">
                    <i class="fas fa-rotate me-1"></i> Recalcular cierres
                </button>
                <?php endif; ?>
                <div id="cuadreDropModal">
                    <div class="cuadre-export-group">
                        <button type="button" class="cuadre-export-btn cuadre-export-btn--imprimir" onclick="window.cuadreAccion('cuadre_modal','imprimir')"><i class="fas fa-print"></i>Imprimir</button>
                        <button type="button" class="cuadre-export-btn cuadre-export-btn--xls" onclick="window.cuadreAccion('cuadre_modal','xls')"><i class="fas fa-file-excel"></i>XLS</button>
                        <button type="button" class="cuadre-export-btn cuadre-export-btn--pdf" onclick="window.cuadreAccion('cuadre_modal','pdf')"><i class="fas fa-file-pdf"></i>PDF</button>
                        <button type="button" class="cuadre-export-btn cuadre-export-btn--txt" onclick="window.cuadreAccion('cuadre_modal','txt')"><i class="fas fa-file-lines"></i>TXT</button>
                        <button type="button" class="cuadre-export-btn cuadre-export-btn--docx" onclick="window.cuadreAccion('cuadre_modal','docx')"><i class="fas fa-file-word"></i>DOCX</button>
                    </div>
                </div>
                <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>


<!-- Selector de Años/Meses para consulta rápida -->
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.05s;">
    <div class="row align-items-end">
        <!-- Título -->
        <div class="row g-2 align-items-end p-3" style="background: rgba(255,255,255,0.02); border-radius: 0.75rem; border: 0.0625rem solid rgba(255,255,255,0.05);">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-chart-line" style="font-size:1.3rem; color: #60a5fa;"></i>
                    <div>
                        <h2 style="font-size:1.1rem; font-weight: 600; margin:0; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Consultar de Forma Rápida</h2>
                        <p class="text-muted" style="font-size:0.85rem; margin:0; color: rgba(255,255,255,0.4) !important;">
                            Filtrar por período
                        </p>
                    </div>
                </div>
<div class="d-flex gap-2">
    
    <!-- Botón condicionado por PHP según la pestaña activa (La condicion se la comente , siempre sale)-->
    <?php //if ($tipo_nomina_activa == 'bono'): ?>
        <button class="btn-win btn-win-sm" id="btnFullHistorialBonos" title="Ver historial de montos definidos" data-tooltip="Ver historial de montos definidos" data-tooltip-theme="info" style="padding:0.375rem 0.75rem; background: rgba(147, 51, 234, 0.2); border: 0.0625rem solid #a855f7;">
            <i class="fas fa-history me-1"></i> Historial Montos
        </button>
    <?php //endif; ?>

    <!--Botón Listado Total Salario Devengado por Trabajador -->
    <button class="btn-win btn-win-sm" id="btnListadoDevengado" title="Listado Total Salario Devengado por trabajador y mes" data-tooltip="Resumen Por Trabajador/Por Cuenta Bancaria" data-tooltip-theme="info" style="padding:0.375rem 0.75rem; background: rgba(var(--color-success-rgb), 0.2); border: 0.0625rem solid var(--color-success);">
        <i class="fas fa-user-tie me-1"></i> Resumen Salarial
    </button>

    <!-- Botón Actualizar Página (Ya existente) -->
    <button class="btn-win btn-win-sm" id="btnActualizarPagina" title="Actualizar página actual limpiando filtros" data-tooltip="Actualizar página actual limpiando filtros" data-tooltip-theme="warning" style="padding:0.375rem 0.75rem; background: rgba(59,130,246,0.2); border: 0.0625rem solid #3b82f6;">
        <i class="fas fa-sync-alt me-1"></i> Eliminar Filtros y Actualizar
    </button>
    
    <!-- Botón Regresar a Inicio (Ya existente) -->
    <button class="btn-win btn-win-sm" id="btnRegresarInicio" title="Regresar a la página principal de nóminas" data-tooltip="Regresar a la página principal de nóminas" data-tooltip-theme="primary" style="padding:0.375rem 0.75rem; background: rgba(var(--color-success-rgb),0.2); border: 0.0625rem solid var(--color-success);">
        <i class="fas fa-home me-1"></i> Inicio
    </button>
</div>
            </div>
        </div>
        
        <!-- 1. Año -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-calendar-alt me-1"></i> Seleccionar Año
            </label>
            <select class="form-select form-select-sm" id="consultaAnioSelect" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary">
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
            <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-tag me-1"></i> Tipo de Nómina
            </label>
            <select class="form-select form-select-sm" id="consultaTipoSelect" title="Tipo de nómina" data-tooltip="Tipo de nómina" data-tooltip-theme="secondary">
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
            <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-circle-info me-1"></i> Estado
            </label>
            <select class="form-select form-select-sm" id="consultaEstadoSelect" title="Estado de nómina" data-tooltip="Estado de nómina" data-tooltip-theme="secondary">
                <option value="">-- Todos los estados --</option>
                <option value="borrador">📝 Borrador</option>
                <option value="contabilizado">🔒 Contabilizado</option>
            </select>
        </div>
        
        <!-- 4. Mes -->
        <div class="col-md-2">
            <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-moon me-1"></i> Seleccionar Mes
            </label>
			<select class="form-select form-select-sm" id="consultaMesSelect" title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary">
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
            <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
                <i class="fas fa-hashtag me-1"></i> Nº de Nómina
            </label>
            <select class="form-select form-select-sm" id="consultaNumeroSelect" disabled title="Número de nómina" data-tooltip="Número de nómina" data-tooltip-theme="secondary">
                <option value="">-- Seleccione mes --</option>
            </select>
        </div>
        
        <!-- 5. Botón Ir al período -->
<!-- Modificación en el col-md-2 de la Acción del card Consultar de Forma Rápida -->
<div class="col-md-2">
    <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;">
        <i class="fas fa-play me-1"></i> Acción
    </label>
    <button class="btn-fluent w-100" id="consultaRapidaBtn" style="margin-top:0;">
        <i class="fas fa-calendar-check"></i>
        <span>Ir al período</span>
    </button>
    
    <!-- NUEVO: Botón de Historial (se mostrará solo si tipo = bono) -->
    <button class="btn-win btn-win-info w-100 mt-2" id="btnFullHistorialBonos" title="Ver historial completo de bonos" data-tooltip="Ver historial completo de bonos" data-tooltip-theme="info" style="display: none; padding:0.375rem; font-size:0.75rem;">
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
<div class="glass-card mb-4 fade-in-up" style="animation-delay: 0.075s; background: rgba(96, 165, 250, 0.08); border-left: 0.25rem solid #60a5fa; padding:0.75rem 1.25rem;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <div style="width:2rem; height:2rem; background: rgba(96, 165, 250, 0.15); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-down" style="font-size:1rem; color: #60a5fa; animation: bounce 1.5s infinite;"></i>
                </div>
                <h5 style="font-size:0.8rem; font-weight: 600; margin:0; color: #60a5fa;">
                    <i class="fas fa-chart-simple me-1"></i> A continuación:
                </h5>
            </div>
            <div class="d-flex flex-wrap gap-3" style="font-size:0.7rem; color: rgba(255,255,255,0.7);">
				<span><i class="text-success fas fa-money-bill-wave me-1"></i> Seleccionar Tipo de Nómina</span>
                <span><i class="fas fa-calendar-alt me-1" style="color: #60a5fa;"></i> Seleccionar período</span>
                <span><i class="fas fa-plus-circle me-1" style="color: var(--color-success);"></i> Consultar/Generar</span>
                <span><i class="fas fa-edit me-1" style="color: #f59e0b;"></i> Editar cálculos</span>
                <span><i class="fas fa-chart-line me-1" style="color: #a78bfa;"></i> Estadísticas</span>
                <span><i class="fas fa-file-export me-1" style="color: #3b82f6;"></i> Exportar</span>
                <span><i class="fas fa-print me-1" style="color: #3b82f6;"></i> Imprimir</span>
            </div>
        </div>
        <div class="d-none d-md-block">
            <i class="fas fa-hand-point-right" style="font-size:1.2rem; color: #60a5fa; opacity: 0.6;"></i>
        </div>
    </div>
</div>

<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(0.3125rem); }
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
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.3);
}

.btn-win:active, .btn-win-primary:active, .btn-win-success:active,
.btn-win-info:active, .btn-win-warning:active, .btn-win-danger:active,
.btn-fluent:active, .btn-nomina-impresa:active,
#btnActualizarPagina:active, #btnRegresarInicio:active {
    filter: brightness(0.9);
}

/* Botones deshabilitados (p. ej. bloqueados por clasificadores vacíos) */
.btn-win:disabled, .btn-win-primary:disabled, .btn-win-success:disabled,
.btn-win-info:disabled, .btn-win-warning:disabled, .btn-win-danger:disabled,
.btn-fluent:disabled, .btn-nomina-impresa:disabled,
#btnActualizarPagina:disabled, #btnRegresarInicio:disabled {
    opacity: 0.45;
    filter: grayscale(0.6);
    cursor: not-allowed;
    pointer-events: none;
    box-shadow: none;
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
    box-shadow: 0 0.25rem 0.75rem rgba(96, 165, 250, 0.2);
}

/* Hover para dropdown items */
.dropdown-item {
    transition: all 0.15s ease;
}

.dropdown-item:hover {
    background: rgba(96, 165, 250, 0.25) !important;
    color: #60a5fa !important;
    border-left: 0.1875rem solid #60a5fa;
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
    border-left: 0.1875rem solid #60a5fa !important;
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
    width:12.5rem;
}

.tipo-nomina-preview {
    display: flex;
    align-items: center;
    gap:0.5rem;
    background: linear-gradient(135deg, var(--card), var(--panel));
    backdrop-filter: blur(0.625rem);
    border: 0.0625rem solid rgba(96, 165, 250, 0.3);
    border-radius: 0.75rem;
    padding:0.375rem 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size:0.8rem;
    font-weight: 500;
    color: var(--txt);
}

.tipo-nomina-preview:hover {
    border-color: #60a5fa;
    background: linear-gradient(135deg, var(--panel-2), var(--card));
    box-shadow: 0 0.125rem 0.5rem rgba(96, 165, 250, 0.15);
}

.tipo-nomina-preview i:first-child {
    color: #60a5fa;
    font-size:0.9rem;
    width:1.125rem;
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
    font-size:0.7rem;
    transition: transform 0.2s ease;
}

.tipo-nomina-selector.open .tipo-nomina-preview i:last-child {
    transform: rotate(180deg);
}

.tipo-nomina-select {
    position: absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    opacity: 0;
    cursor: pointer;
    z-index: 10;
}

/* Estilo para las opciones del select nativo (cuando se despliega) */
.tipo-nomina-select option {
    background: var(--panel);
    color: var(--txt);
    padding:0.625rem;
}

/* Alternativa: Estilo personalizado con dropdown personalizado (opcional) */
.tipo-nomina-dropdown {
    position: absolute;
    top:100%;
    left:0;
    right:0;
    margin-top:0.5rem;
    background: var(--card);
    backdrop-filter: blur(0.75rem);
    border: 0.0625rem solid rgba(96, 165, 250, 0.2);
    border-radius: 0.75rem;
    padding:0.375rem 0;
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
    gap:0.625rem;
    padding:0.5rem 0.875rem;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size:0.8rem;
    color: var(--txt);
}

.tipo-nomina-option:hover {
    background: rgba(96, 165, 250, 0.15);
    color: #60a5fa;
}

.tipo-nomina-option i {
    width:1.25rem;
    text-align: center;
    font-size:0.9rem;
}

.tipo-nomina-option.selected {
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
    border-left: 0.1875rem solid #60a5fa;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-0.625rem);
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
    width:11.25rem;
}

.tipo-nomina-preview {
    display: flex;
    align-items: center;
    gap:0.625rem;
    background: linear-gradient(135deg, var(--card), var(--panel));
    backdrop-filter: blur(0.625rem);
    border: 0.0625rem solid rgba(96, 165, 250, 0.3);
    border-radius: 0.75rem;
    padding:0.5rem 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size:0.8rem;
    font-weight: 500;
    color: var(--txt);
}

.tipo-nomina-preview:hover {
    border-color: #60a5fa;
    background: linear-gradient(135deg, var(--panel-2), var(--card));
    box-shadow: 0 0.125rem 0.625rem rgba(96, 165, 250, 0.2);
}

.tipo-nomina-preview i:first-child {
    color: #60a5fa;
    font-size:1rem;
    width:1.25rem;
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
    font-size:0.7rem;
    transition: transform 0.25s ease;
}

.tipo-nomina-selector-custom.open .tipo-nomina-preview i:last-child {
    transform: rotate(180deg);
}

/* Dropdown personalizado */
.tipo-nomina-dropdown {
    position: absolute;
    top:calc(100% + 0.5rem);
    left:0;
    right:0;
    background: var(--card);
    backdrop-filter: blur(0.75rem);
    border: 0.0625rem solid rgba(96, 165, 250, 0.2);
    border-radius: 0.75rem;
    padding:0.375rem 0;
    z-index: 1000;
    display: none;
    animation: fadeInDown 0.2s ease;
    box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.3);
}

.tipo-nomina-selector-custom.open .tipo-nomina-dropdown {
    display: block;
}

/* Opciones del dropdown */
.tipo-nomina-option {
    display: flex;
    align-items: center;
    gap:0.625rem;
    padding:0.625rem 0.875rem;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size:0.8rem;
    color: var(--txt);
}

.tipo-nomina-option:hover {
    background: rgba(96, 165, 250, 0.15);
    color: #60a5fa;
}

.tipo-nomina-option i {
    width:1.25rem;
    text-align: center;
    font-size:0.95rem;
    color: #60a5fa;
}

.tipo-nomina-option.selected {
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
    border-left: 0.1875rem solid #60a5fa;
}

.tipo-nomina-option.selected i {
    color: #60a5fa;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-0.625rem);
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
    top:100%;
    left:0;
    right:0;
    z-index: 1052;
    margin-top:0.25rem;
    border-radius: 0.5rem;
    box-shadow: 0 0.25rem 1.25rem rgba(0,0,0,0.4);
}

.resultado-item {
    padding:0.625rem 0.75rem;
    cursor: pointer;
    border-bottom: 0.0625rem solid rgba(255,255,255,0.05);
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
    font-size:0.85rem;
}

.resultado-detalle {
    font-size:0.7rem;
    color: rgba(255,255,255,0.5);
    margin-top:0.125rem;
}

.resultado-detalle i {
    margin-right:0.25rem;
    width:0.875rem;
}

#listadoResultadosBusqueda,
#sinCuentaResultadosBusqueda {
    position: absolute;
    top:100%;
    left:0;
    right:0;
    z-index: 1100;
    margin-top:0.25rem;
    border-radius: 0.5rem;
    box-shadow: 0 0.25rem 1.25rem rgba(0,0,0,0.4);
    padding:0;
    overflow-y: auto;
    max-height:15.625rem;
    min-width:100%;
}

#listadoResultadosBusqueda .listado-opt-trabajador,
#sinCuentaResultadosBusqueda .sinCuenta-opt-trabajador {
    color: #ffffff;
    padding:0.5rem 0.75rem;
    border-bottom: 0.0625rem solid rgba(255,255,255,0.06);
    white-space: normal;
}

#listadoResultadosBusqueda .listado-opt-trabajador:hover,
#listadoResultadosBusqueda .listado-opt-trabajador:focus,
#sinCuentaResultadosBusqueda .sinCuenta-opt-trabajador:hover,
#sinCuentaResultadosBusqueda .sinCuenta-opt-trabajador:focus {
    background: rgba(96, 165, 250, 0.15);
    color: #ffffff;
}

#listadoResultadosBusqueda .text-white-50,
#sinCuentaResultadosBusqueda .text-white-50 {
    color: rgba(255,255,255,0.6) !important;
}

/* ===== Select2: filtro "Por Nombre" con búsqueda ===== */
.select2-container--default .select2-selection--single {
    background: rgba(20, 20, 25, 0.8) !important;
    border: 0.0625rem solid rgba(255,255,255,0.15) !important;
    border-radius: 10px !important;
    height: 2.4375rem !important;
    display: flex;
    align-items: center;
    transition: border-color 0.15s ease;
}
.select2-container--default .select2-selection--single:hover { border-color: #60a5fa !important; }
.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #60a5fa !important;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2) !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: white !important;
    line-height: normal !important;
    padding-left: 0.875rem !important;
    padding-right: 1.875rem !important;
    font-size: 0.9rem;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder { color: rgba(255,255,255,0.5) !important; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 100% !important; right: 0.5rem !important; }
.select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: #ffffff transparent transparent transparent !important; }
.select2-dropdown {
    background: #14141c !important;
    border: 0.0625rem solid rgba(255,255,255,0.15) !important;
    border-radius: 10px !important;
    overflow: hidden;
}
.select2-search--dropdown .select2-search__field {
    background: rgba(20, 20, 25, 0.9) !important;
    border: 0.0625rem solid rgba(255,255,255,0.15) !important;
    color: white !important;
    border-radius: 8px !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 0.85rem;
}
.select2-search--dropdown .select2-search__field:focus { outline: none; border-color: #60a5fa !important; }
.select2-search--dropdown .select2-search__field::placeholder { color: rgba(255,255,255,0.4); }
.select2-results__option { color: white !important; font-size: 0.85rem; padding: 0.5rem 0.75rem; }
.select2-results__option--highlighted.select2-results__option--selectable {
    background: rgba(96, 165, 250, 0.3) !important;
    color: #ffffff !important;
}
.select2-results__option[aria-selected="true"],
.select2-results__option--selected { background: rgba(139, 92, 246, 0.25) !important; color: #e9d5ff !important; }
.select2-results > .select2-results__options { max-height: 17.5rem !important; }

html[data-theme="light"] .select2-container--default .select2-selection--single {
    background: #ffffff !important;
    border-color: rgba(0,0,0,0.2) !important;
}
html[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered { color: #1f2937 !important; }
html[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: #333 transparent transparent transparent !important; }
html[data-theme="light"] .select2-dropdown { background-color: #ffffff !important; border-color: rgba(0,0,0,0.15) !important; }
html[data-theme="light"] .select2-search--dropdown .select2-search__field { background: #ffffff !important; border-color: rgba(0,0,0,0.15) !important; color: #1f2937 !important; }
html[data-theme="light"] .select2-results__option { color: #1f2937 !important; }
html[data-theme="light"] .select2-results__option[aria-selected="true"] { background: rgba(var(--accent-rgb),0.15) !important; color: #5b21b6 !important; }
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
            <i class="fas fa-chart-line" style="font-size:1.3rem; color: #60a5fa;"></i>
            <div>
                <h2 style="font-size:1.1rem; font-weight: 600; margin:0; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Período de Nómina</h2>
                <p class="text-muted" style="font-size:0.85rem; margin:0; color: rgba(255,255,255,0.4) !important;">Seleccione Año/Mes. Consulte antes de crear</p>
            </div>
        </div>
    </div>
    
    <!-- Separador visual -->
    <div class="col-md-auto">
        <div style="width:0.0625rem; height:2.5rem; background: rgba(255,255,255,0.1);"></div>
    </div>
    
    <!-- Selector Año -->
    <div class="col-md-2">
        <label class="form-label fw-semibold mb-1" style="font-size:0.7rem;">
            <i class="fas fa-calendar-alt me-1" style="color: #60a5fa;"></i> Año
        </label>
        <select class="form-select form-select-custom" id="anioSelect" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary" style="padding:0.375rem 0.75rem; font-size:0.85rem;">
            <?php for ($y = 2023; $y <= date('Y')+10; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $anio ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    
    <!-- Selector Mes -->
    <div class="col-md-2">
        <label class="form-label fw-semibold mb-1" style="font-size:0.7rem;">
            <i class="fas fa-moon me-1" style="color: #60a5fa;"></i> Mes
        </label>
        <select class="form-select form-select-custom" id="mesSelect" title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary" style="padding:0.375rem 0.75rem; font-size:0.85rem;">
            <?php for ($m = 1; $m <= 12; $m++): $m_pad = str_pad($m,2,'0',STR_PAD_LEFT); ?>
                <option value="<?php echo $m_pad; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>><?php echo nombreMesEspanol($m_pad); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    
    <!-- Botón Consultar -->
    <div class="col-md-auto">
        <button class="btn-win-primary" id="consultarBtn" style="margin-top:1.25rem; padding:0.375rem 1.125rem;">
            <i class="fas fa-search me-1"></i> Consultar
        </button>
    </div>
    
<!-- Botones de Acción (Dinámicos) -->
<div class="col-md">
    <?php 
    // Para ajuste, siempre mostrar el botón de nueva nómina, independientemente de si existen registros.
    // Además, solo con permiso de crear en el módulo de nóminas.
    $mostrarBotonCreacion = (($tipo_nomina_activa == 'ajuste') ? true : (!$existe_nomina)) && permiso_puede('nominas', 'crear');
    ?>
    <?php if ($mostrarBotonCreacion): ?>
        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <button type="button" class="btn-win-primary" id="btnGenerarAutomaticaModal" title="Generar nómina automática" data-tooltip="Generar nómina automática" data-tooltip-theme="success" style="margin-top:1.25rem; padding:0.375rem 1.125rem;" <?php echo $hay_clasificadores_vacios ? 'disabled' : ''; ?>>
                <i class="fas fa-play me-1"></i> Generar Nómina Automática
            </button>
            <form method="POST" id="formGenerarAutomatica" style="display: none;">
                <input type="hidden" name="generar_nomina_automatica" value="1">
                <input type="hidden" name="tipo_descuento" id="tipoDiscountHidden" value="">
            </form>
        <?php elseif ($tipo_nomina_activa == 'extraordinaria'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="extraordinaria" style="margin-top:1.25rem; padding:0.375rem 1.125rem;" <?php echo $hay_clasificadores_vacios ? 'disabled' : ''; ?>>
                <i class="fas fa-clock me-1"></i> Nueva Nómina Ext.
            </button>
        <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="vacaciones" style="margin-top:1.25rem; padding:0.375rem 1.125rem;" <?php echo $hay_clasificadores_vacios ? 'disabled' : ''; ?>>
                <i class="fas fa-umbrella-beach me-1"></i> Nueva Nómina Vac.
            </button>
        <?php elseif ($tipo_nomina_activa == 'bono'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="bono" style="margin-top:1.25rem; padding:0.375rem 1.125rem;" <?php echo $hay_clasificadores_vacios ? 'disabled' : ''; ?>>
                <i class="fas fa-gift me-1"></i> Generar Bonos y Otros Pagos
            </button>
        <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
            <button type="button" class="btn-win-primary" data-bs-toggle="modal" data-bs-target="#modalSeleccionDescuentoGeneral" data-target-type="ajuste" style="margin-top:1.25rem; padding:0.375rem 1.125rem;" <?php echo $hay_clasificadores_vacios ? 'disabled' : ''; ?>>
                <i class="fas fa-pen me-1"></i> Nueva Nómina de Ajuste
            </button>
        <?php else: ?>
            <button class="btn-win w-100" disabled style="margin-top:1.25rem;">
                <i class="fas fa-ban me-1"></i> Seleccione tipo
            </button>
        <?php endif; ?>
    <?php else: ?>
        <!-- Ya existe nómina y NO es ajuste, mostramos botones de regenerar, agregar, eliminar -->
        <div class="d-flex gap-2" style="margin-top:1.25rem;">
            <?php if ($tipo_nomina_activa == 'automatica' && !$contabilizada && $puede_editar_nomina): ?>
                <form method="POST" id="formRegenerarNomina" style="flex:1">
                    <input type="hidden" name="tipo_nomina" value="<?php echo $tipo_nomina_activa; ?>">
                    <input type="hidden" name="regenerar_nomina" value="1">
                    <button type="button" id="btnRegenerarNomina" class="btn-win-info w-100" title="Regenerar nómina" data-tooltip="Regenerar nómina" data-tooltip-theme="info" style="padding:0.375rem 0.75rem;">
                        <i class="fas fa-sync-alt me-1"></i> Regenerar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($tipo_nomina_activa == 'vacaciones' && !$contabilizada && $puede_editar_nomina): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalVacaciones" style="padding:0.375rem 0.75rem;">
                    <i class="fas fa-plus-circle me-1"></i> Add Vacaciones
                </button>
            <?php endif; ?>
            <?php if ($tipo_nomina_activa == 'bono' && !$contabilizada && $puede_editar_nomina): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalBono" style="padding:0.375rem 0.75rem;">
                    <i class="fas fa-plus-circle me-1"></i> Add Bono
                </button>
            <?php endif; ?>
            <?php if ($tipo_nomina_activa == 'extraordinaria' && !$contabilizada && $puede_editar_nomina): ?>
                <button type="button" class="btn-win-success" data-bs-toggle="modal" data-bs-target="#modalExtraordinaria" style="padding:0.375rem 0.75rem;">
                    <i class="fas fa-plus-circle me-1"></i> Add Trab.
                </button>
            <?php endif; ?>
            <!-- Botón Eliminar Todo (solo para tipos que NO sean ajuste) -->
            <?php if ($puede_eliminar_nomina): ?>
                <button type="button" 
                        class="<?php echo $contabilizada ? 'btn-win' : 'btn-win-danger'; ?>" 
                        id="eliminarTodoBtn" 
                        <?php echo $contabilizada ? 'disabled style="padding:0.375rem 0.75rem; opacity: 0.5; cursor: not-allowed;"' : 'style="padding:0.375rem 0.75rem;"'; ?>>
                    <i class="fas fa-trash-alt me-1"></i> Eliminar Todo
                </button>
            <?php endif; ?>
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
                <div class="stat-icon" style="color: var(--color-success-soft);"><i class="fas fa-arrow-up"></i></div>
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
    <div class="d-flex align-items-center gap-2 mb-1" id="nomFiltrosToggle" style="cursor: pointer; user-select: none;">
        <i class="fas fa-filter" style="color: #60a5fa; font-size:1rem;"></i>
        <h5 class="mb-0" style="font-size:0.95rem; font-weight:600; color: rgba(255,255,255,0.9);">Filtros:</h5>
        <i class="fas fa-chevron-down ms-auto" id="nomFiltrosChevron" style="color: rgba(255,255,255,0.4); font-size:0.7rem; transition: transform 0.3s ease;"></i>
    </div>
    <div id="nomFiltrosBody">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-user me-1"></i> Por Nombre</label>
            <select id="filtroTrabajador" class="form-select" title="Filtrar por trabajador" data-tooltip="Filtrar por trabajador" data-tooltip-theme="secondary">
                <option value="">-- Todos --</option>
                <?php foreach ($trabajadores as $trab): ?>
                    <option value="<?php echo $trab['id']; ?>"><?php echo htmlspecialchars($trab['codigo'] . ' - ' . $trab['nombre_completo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-building me-1"></i> Área</label>
            <select id="filtroArea" class="form-select" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
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
            <select id="filtroCentroCosto" class="form-select" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
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
            <select id="filtroCuenta" class="form-select" title="Filtrar por cuenta bancaria" data-tooltip="Filtrar por cuenta bancaria" data-tooltip-theme="secondary">
                <option value="">-- Todos --</option>
                <option value="si" <?php echo $filtro_cuenta == 'si' ? 'selected' : ''; ?>>Con cuenta bancaria</option>
                <option value="no" <?php echo $filtro_cuenta == 'no' ? 'selected' : ''; ?>>Sin cuenta bancaria</option>
            </select>
        </div>
    </div>
    
    <div class="row g-3 align-items-end mt-3">
        <div class="col-md-3">
            <?php $stmt_num_filtro = $pdo->prepare("SELECT DISTINCT COALESCE(numero_nomina, 'Borrador') as numero_nomina FROM nominas WHERE periodo_desde = ? AND periodo_hasta = ? AND tipo_nomina = ? ORDER BY numero_nomina ASC");
            $stmt_num_filtro->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]); ?>
            <label class="form-label"><i class="fas fa-hashtag me-1"></i> No. Nómina</label>
            <select id="filtroNumeroNomina" class="form-select" title="Filtrar por número de nómina" data-tooltip="Filtrar por número de nómina" data-tooltip-theme="secondary">
                <option value="">-- Todos --</option>
                <?php foreach ($stmt_num_filtro->fetchAll(PDO::FETCH_COLUMN) as $num_op_filtro): ?>
                    <option value="<?php echo htmlspecialchars($num_op_filtro); ?>" <?php echo ($num_op_filtro === $filtro_numero_nomina) ? 'selected' : ''; ?>><?php echo htmlspecialchars($num_op_filtro); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Acumulación Vacaciones</label>
            <select id="filtroAcumulaVacaciones" class="form-select" title="Filtrar por acumulación de vacaciones" data-tooltip="Filtrar por acumulación de vacaciones" data-tooltip-theme="secondary">
                <option value="">-- Todos --</option>
                <option value="si">Acumulan vacaciones (9.09%)</option>
                <option value="no">No acumulan vacaciones</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-chart-line me-1"></i> Días Acumulados</label>
            <select id="filtroRangoVacaciones" class="form-select" title="Filtrar por días acumulados" data-tooltip="Filtrar por días acumulados" data-tooltip-theme="secondary">
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
            <button class="btn-win btn-win-sm" id="btnLimpiarFiltros" title="Limpiar todos los filtros" data-tooltip="Limpiar todos los filtros" data-tooltip-theme="warning"><i class="fas fa-eraser me-1"></i> Limpiar todos</button>
        </div>
    </div>
    </div>
</div>

    <!-- Alertas Generales -->
    <div id="alertasGenerales"></div>
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
		 <?php elseif ($_GET['msg'] == 'liquidacion_procesada'): ?>
				<div class="alert alert-success bg-success bg-opacity-25 border-success text-white">
					<i class="fas fa-check-circle me-2"></i> 
					Liquidación de fracciones procesada: <?php echo intval($_GET['nuevos'] ?? 0); ?> nuevos, 
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
            <?php elseif ($_GET['error'] == 'cuadre_pendiente'): ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white">
                <i class="fas fa-ban me-2"></i>
                <strong>Revisión de cuadre previa:</strong> la nómina <strong>NO fue contabilizada</strong>. Hay
                <strong><?php echo intval($_GET['filas'] ?? 0); ?></strong> fila(s) en borrador con descuadres
                (devengado, CESS/ISIP, deducciones o neto).
                <button type="button" class="btn-close" style="float:right;" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-warning btn-sm" id="btnCorregirPendientes"
                        data-pd="<?php echo htmlspecialchars($_GET['pd'] ?? ''); ?>"
                        data-ph="<?php echo htmlspecialchars($_GET['ph'] ?? ''); ?>"
                        data-tipo="<?php echo htmlspecialchars($_GET['tipo'] ?? ''); ?>"
                        style="border-radius:0.5rem;">
                        <i class="fas fa-calculator me-1"></i> Recalcular y corregir
                    </button>
                    <div id="cuadreDropServidor"></div>
                    <small class="d-block w-100 mt-1" style="opacity:0.8;">Recalcula desde los valores base y guarda devengado, CESS, ISIP, deducciones y neto correctos en los borradores con descuadre.</small>
                </div>
            </div>
            <?php if (!empty($_SESSION['cuadre_pendiente_errores'])): ?>
            <script>
            window.cuadrePendienteAlertaData = <?php echo json_encode([
                'periodo' => substr($_GET['pd'] ?? '', 0, 7),
                'tipo' => $_GET['tipo'] ?? '',
                'filas' => intval($_GET['filas'] ?? 0),
                'filas_con_error' => intval($_GET['filas'] ?? 0),
                'errores_total' => count($_SESSION['cuadre_pendiente_errores']),
                'errores' => array_slice($_SESSION['cuadre_pendiente_errores'], 0, 200),
            ], JSON_UNESCAPED_UNICODE); ?>;
            </script>
            <?php endif; ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var cuadreDropSrv = document.getElementById('cuadreDropServidor');
                if (cuadreDropSrv) {
                    window.cuadreDropData['cuadre_servidor'] = { rep: window.cuadrePendienteAlertaData || { errores: [] }, titulo: 'N\u00d3MINAS EN BORRADOR' };
                    cuadreDropSrv.innerHTML = window.cuadreBotonesHtml('cuadre_servidor');
                }
                var b = document.getElementById('btnCorregirPendientes');
                if (!b) return;
                b.addEventListener('click', function () {
                    var pd = b.getAttribute('data-pd'), ph = b.getAttribute('data-ph'), tp = b.getAttribute('data-tipo');
                    if (!pd || !ph || !tp) { return; }
                    Swal.fire({
                        title: '<i class="fas fa-calculator me-2" style="color:#f59e0b;"></i>¿Recalcular pendientes?',
                        html: 'Se recalcularán y guardarán los valores correctos en <strong>los borradores de este período/tipo</strong> que presenten descuadres.<br><br>Esta acción sobrescribe los valores actuales y es irreversible.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#475569',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Sí, recalcular',
                        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    }).then(function (res) {
                        if (!res.isConfirmed) return;
                        Swal.fire({
                            title: '<i class="fas fa-spinner fa-spin me-2"></i>Recalculando...',
                            allowOutsideClick: false,
                            didOpen: function () { Swal.showLoading(); },
                            background: '#1a1a2e',
                            color: '#ffffff'
                        });
                        fetch('nominas.php?action=corregir_pendientes&ajax=1&pd=' + encodeURIComponent(pd) + '&ph=' + encodeURIComponent(ph) + '&tipo=' + encodeURIComponent(tp), { cache: 'no-store' })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data && data.success) {
                                    Swal.fire({
                                        title: '<i class="fas fa-check-circle me-2" style="color:3;"></i>Recálculo completado',
                                        html: 'Se corrigieron <strong>' + data.filas_corregidas + '</strong> borrador(es).<br>Descuadres pendientes restantes: <strong>' + data.errores_total + '</strong>.',
                                        icon: 'success',
                                        background: '#1a1a2e',
                                        color: '#ffffff',
                                        confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                                    }).then(function () {
                                        if (data.errores_total === 0) {
                                            var alerta = b.closest('.alert');
                                            if (alerta) alerta.style.display = 'none';
                                            var detalle = document.getElementById('cuadreDetalleAlert');
                                            if (detalle) detalle.style.display = 'none';
                                            var periodo = (pd || '').substring(0, 7);
                                            location.href = location.pathname + '?periodo=' + encodeURIComponent(periodo) + '&tipo=' + encodeURIComponent(tp);
            } else {
                                            location.reload();
                                        }
                                    });
                                } else {
                                    Swal.fire({
                                        title: '<i class="fas fa-exclamation-circle me-2"></i>Error',
                                        text: (data && data.message) ? data.message : 'No se pudo recalcular.',
                                        icon: 'error',
                                        background: '#1a1a2e',
                                        color: '#ffffff',
                                        confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                                    });
                                }
                            })
                            .catch(function () {
                                Swal.fire({
                                    title: '<i class="fas fa-wifi me-2"></i>Error',
                                    text: 'No se pudo conectar para recalcular.',
                                    icon: 'error',
                                    background: '#1a1a2e',
                                    color: '#ffffff',
                                    confirmButtonText: '<i class="fas fa-check me-1"></i>Aceptar'
                                });
                            });
                    });
                });
            });
            </script>
            <?php if (!empty($_SESSION['cuadre_pendiente_errores'])): 
                $pendientes_lista = $_SESSION['cuadre_pendiente_errores'];
                unset($_SESSION['cuadre_pendiente_errores']);
            ?>
            <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white" id="cuadreDetalleAlert" style="max-height:20rem; overflow-x:auto; overflow-y:auto;">
                <strong><i class="fas fa-list me-1"></i> Detalle de los descuadres pendientes:</strong>
                <table class="table table-sm table-dark align-middle mt-2 mb-0" style="font-size:0.75rem;">
                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Tipo</th>
                            <th>Verificación</th>
                            <th class="text-end">Almacenado</th>
                            <th class="text-end">Recalculado</th>
                            <th class="text-end">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($pendientes_lista, 0, 100) as $e): ?>
                        <tr>
                            <td><a class="cuadre-link" href="empleados.php?editar=<?php echo (int)$e['trabajador_id']; ?>" title="Abrir ficha del empleado" data-tooltip="Abrir ficha del empleado" data-tooltip-theme="info"><?php echo htmlspecialchars($e['trabajador']); ?></a></td>
                            <td><?php echo htmlspecialchars($e['tipo']); ?></td>
                            <td><?php echo htmlspecialchars($e['detalle']); ?></td>
                            <td class="text-end cuadre-val-almacenado"><?php echo htmlspecialchars($e['encontrado']); ?></td>
                            <td class="text-end cuadre-val-recalculado"><?php echo htmlspecialchars($e['esperado']); ?></td>
                            <td class="text-end cuadre-val-diferencia"><?php echo htmlspecialchars($e['diferencia']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($pendientes_lista) > 100): ?>
                    <div class="mt-2" style="color:#fbbf24;">Mostrando los primeros 100 de <?php echo count($pendientes_lista); ?> descuadres.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

        <!-- TABLA PRINCIPAL DE NÓMINA -->
        <div class="glass-card fade-in-up" style="animation-delay: 0.4s;">
		<!-- Título de la Tarjeta del DataTable (Card Title) -->
		<div class="card-header-custom mb-3 pb-2" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.1);">
			<div class="d-flex justify-content-between align-items-center">
				<h5 class="text-white mb-0" style="font-weight: 600; font-size:0.95rem;">
					<i class="fas fa-file-invoice-dollar me-2" style="color: #60a5fa;"></i>
					Detalle de Nómina: <span class="text-info"><?php echo htmlspecialchars($tipos_nomina[$tipo_nomina_activa]['nombre']); ?></span> 
					<span id="tituloCardNumeroNomina"><?php echo ($num_nomina_actual !== 'Borrador') ? 'No.: <span class="text-warning">' . htmlspecialchars($num_nomina_actual) .'</span>': '<span class="text-info">(Borrador)</span>'; ?></span>
					<span class="mx-2" style="color: rgba(255,255,255,0.3);">|</span> 
					Período: <span class="text-success"><?php echo htmlspecialchars($nombre_mes . ' ' . $anio); ?></span>
				</h5>
			</div>
			<div class="alert alert-info mt-2 mb-0" id="alertaObservacionesCierre" style="<?php echo ($contabilizada && !empty($observaciones_cierre)) ? '' : 'display:none;'; ?> background: rgba(59, 130, 246, 0.12); border: 0.0625rem solid rgba(59, 130, 246, 0.25); font-size:0.85rem; color: #93c5fd; padding:0.5rem 0.75rem; border-radius: 0.5rem;">
				<i class="fas fa-comment-alt me-2" style="color: #60a5fa;"></i>
				<strong>Observaciones de Cierre:</strong> <span id="textoObservacionesCierre"><?php echo htmlspecialchars($observaciones_cierre); ?></span>
			</div>
		</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detalle de Nómina</h5>
<div class="d-flex gap-2">
    <?php if ($tipo_nomina_activa == 'ajuste'): ?>
        <!-- 🔽 AJUSTE: botones y badge siempre en el DOM; el JS los alterna según la nómina filtrada -->
        <div id="accionesBorradorAjuste" style="display: flex; gap:0.5rem; <?php echo (!$contabilizada && $hay_borrador_ajuste) ? '' : 'display: none;'; ?>">
            <button class="btn-win-success" id="btnGuardarTodo" title="Guardar todo" data-tooltip="Guardar todo" data-tooltip-theme="success" style="background: linear-gradient(135deg, var(--color-success), var(--color-success));">
                <i class="fas fa-save me-1"></i> Guardar Todo
            </button>
            <button type="button" class="btn-win-info" id="btnAgregarPersonasAjusteDirecto" title="Agregar personas al ajuste" data-tooltip="Agregar personas al ajuste" data-tooltip-theme="info">
                <i class="fas fa-user-plus me-1"></i> Agregar Personas
            </button>
            <button class="btn-win-warning" id="contabilizarBtn" title="Contabilizar nómina" data-tooltip="Contabilizar nómina" data-tooltip-theme="warning">
                <i class="fas fa-lock me-1"></i> Contabilizar
            </button>
            <button type="button" class="btn-win-danger" id="btnEliminarTodoAjuste" title="Eliminar todo el ajuste" data-tooltip="Eliminar todo el ajuste" data-tooltip-theme="danger" style="display: none;">
                <i class="fas fa-trash-alt me-1"></i> Eliminar Todo
            </button>
        </div>
        <span id="badgeContabilizadaAjuste" class="badge-contabilizado" style="<?php echo (!$contabilizada && $hay_borrador_ajuste) ? 'display:none;' : ''; ?>">
            <i class="fas fa-check-circle me-1"></i> Contabilizada
        </span>
        <span id="accionesRevertirAjuste" style="display: none; gap:0.5rem;">
            <button type="button" class="btn-win-danger btn-revertir-nomina" id="revertirBtnAjuste" title="Revertir nómina" data-tooltip="Revertir nómina" data-tooltip-theme="danger" style="padding:0.375rem 0.75rem;">
                <i class="fas fa-undo-alt me-1"></i> Revertir
            </button>
        </span>
    <?php elseif (!$contabilizada): ?>
        <button class="btn-win-success" id="btnGuardarTodo" title="Guardar todo" data-tooltip="Guardar todo" data-tooltip-theme="success" style="background: linear-gradient(135deg, var(--color-success), var(--color-success));">
            <i class="fas fa-save me-1"></i> Guardar Todo
        </button>

        <?php if ($tipo_nomina_activa == 'automatica'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresAuto" title="Agregar trabajadores a nómina automática" data-tooltip="Agregar trabajadores a nómina automática" data-tooltip-theme="info" data-bs-toggle="modal" data-bs-target="#modalAgregarTrabajadoresAuto">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'vacaciones'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresVac" title="Agregar trabajadores a vacaciones" data-tooltip="Agregar trabajadores a vacaciones" data-tooltip-theme="info" data-bs-toggle="modal" data-bs-target="#modalVacaciones">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'extraordinaria'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresExtra" title="Agregar trabajadores a extraordinaria" data-tooltip="Agregar trabajadores a extraordinaria" data-tooltip-theme="info" data-bs-toggle="modal" data-bs-target="#modalExtraordinaria">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php elseif ($tipo_nomina_activa == 'bono'): ?>
            <button type="button" class="btn-win-info" id="btnAgregarTrabajadoresBono" title="Agregar trabajadores a bonos" data-tooltip="Agregar trabajadores a bonos" data-tooltip-theme="info" data-bs-toggle="modal" data-bs-target="#modalBono">
                <i class="fas fa-user-plus me-1"></i> Agregar Trabajador
            </button>
        <?php endif; ?>

        <button class="btn-win-warning" id="contabilizarBtn" title="Contabilizar nómina" data-tooltip="Contabilizar nómina" data-tooltip-theme="warning">
            <i class="fas fa-lock me-1"></i> Contabilizar
        </button>
    <?php else: ?>
        <span class="badge-contabilizado">
            <i class="fas fa-check-circle me-1"></i> Contabilizada
        </span>
        <?php if (!empty($num_nomina_actual) && $num_nomina_actual !== 'Borrador'): ?>
            <button type="button" class="btn-win-danger btn-revertir-nomina" title="Revertir nómina" data-tooltip="Revertir nómina" data-tooltip-theme="danger" data-numero="<?php echo htmlspecialchars($num_nomina_actual); ?>" style="padding:0.375rem 0.75rem;">
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
            <th class="col-horas" rowspan="2"><?php echo $tipo_nomina_activa == 'extraordinaria' ? 'HE<br>Diurnas' : 'Horas<br>Trab'; ?></th>
            
            <?php if ($tipo_nomina_activa == 'extraordinaria'): ?>
                <th class="col-noct-t" rowspan="2">Nt<br>7-23h</th>
                <th class="col-noct-t-imp" rowspan="2">$/Nt<br>7-23h</th>
                <th class="col-noct-d" rowspan="2">Nt<br>23-7h</th>
                <th class="col-noct-d-imp" rowspan="2">$/Nt<br>23-7h</th>
                <th class="col-dt" rowspan="2">Doble<br>Turno</th>
                <th class="col-dt-imp" rowspan="2">$/DT</th>
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
            <th class="col-salario-basico" rowspan="2">Salario<br>Básico</th>
            <th class="col-valor-hora" rowspan="2">Tarf.</th>
            <th class="col-horas" rowspan="2">Días Tomados</th>
            <th class="col-horas" rowspan="2">Días Restantes</th>

        <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
            <th class="col-nombre" rowspan="2">Concepto</th>
            <th class="col-salario-basico" rowspan="2">Monto/hrs</th>
            <th class="col-otros-pagos" rowspan="2">Otros<br>Pagos</th>
            <th colspan="2" class="col-vacaciones-header">Acum. Vacaciones</th>
        <?php endif; ?>
        
<!-- Columnas comunes de Totales y Deducciones (Unificadas para todos los tipos de nómina) -->
        <th class="col-total-devengado" rowspan="2">Total<br>Devengado</th>
        <th class="col-otros-descuentos" rowspan="2">Otros<br>Desc.</th>
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
            <th class="col-feriados-dias" style="font-size:0.7rem;">Días</th>
            <th class="col-feriados-imp" style="font-size:0.7rem;">Importe</th>
            <th class="col-vacaciones-dias" style="font-size:0.7rem;">Días</th>
            <th class="col-vacaciones-imp" style="font-size:0.7rem;">Importe</th>
        <?php elseif ($tipo_nomina_activa == 'ajuste'): ?>
            <th class="col-vacaciones-dias" style="font-size:0.7rem;">Días</th>
            <th class="col-vacaciones-imp" style="font-size:0.7rem;">Importe</th>
        <?php endif; ?>
        
        <?php foreach ($rangos_impuesto as $rango): 
            $tasa = $rango['tasa'] * 100;
            $desde = number_format($rango['desde'], 0, '.', '');
            $hasta = $rango['hasta'] ? number_format($rango['hasta'], 0, '.', '') : '∞';
        ?>
            <th style="font-size:0.7rem;" class="col-impuestos-det"><?php echo $tasa; ?>%<br>(<?php echo $desde; ?>-<?php echo $hasta; ?>)</th>
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
    $total_noct_tempranas = 0; $total_importe_noct_tempranas = 0;
    $total_noct_tardias = 0; $total_importe_noct_tardias = 0;
    $total_doble_turno = 0; $total_importe_doble_turno = 0;
    $total_pago_resultado = 0;
    
    $pagos_mn_por_nomina = [];
    if ($tipo_nomina_activa == 'automatica') {
        $stmt_pm = $pdo->prepare("SELECT npa.nomina_id, npa.trabajador_id, pa.nombre, pa.monto, pa.tipo_calculo, npa.importe_aplicado FROM nomina_pagos_adicionales npa JOIN pagos_adicionales pa ON pa.id = npa.pago_adicional_id JOIN nominas nm ON nm.id = npa.nomina_id WHERE nm.periodo_desde = ? AND nm.periodo_hasta = ? AND nm.tipo_nomina = ? ORDER BY pa.nombre");
        $stmt_pm->execute([$periodo_desde, $periodo_hasta, $tipo_nomina_activa]);
        foreach ($stmt_pm->fetchAll(PDO::FETCH_ASSOC) as $pm) {
            $pagos_mn_por_nomina[$pm['nomina_id']][] = $pm;
        }
    }
    
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
        $total_noct_tempranas += $n['horas_nocturnas_tempranas'] ?? 0;
        $total_importe_noct_tempranas += $n['importe_nocturnas_tempranas'] ?? 0;
        $total_noct_tardias += $n['horas_nocturnas_tardias'] ?? 0;
        $total_importe_noct_tardias += $n['importe_nocturnas_tardias'] ?? 0;
        $total_doble_turno += $n['horas_doble_turno'] ?? 0;
        $total_importe_doble_turno += $n['importe_doble_turno'] ?? 0;
        $total_devengado_calc += $n['total_salario_devengado'] ?? 0;
        $total_pago_resultado += $n['pago_resultado'] ?? 0;
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
		echo htmlspecialchars($romano . ' - Escala Salarial Grupo ' . $num_escala . ' ($' . number_format($salario, 2) . ')');
	?>"
	data-tipo-contrato="<?php echo htmlspecialchars($n['tipo_contrato'] ?? ''); ?>"
    data-foto-ruta="<?php echo htmlspecialchars($n['foto_ruta'] ?? ''); ?>"
    data-tipo="<?php echo $tipo_nomina_activa; ?>" 
    data-horas-ajuste="<?php echo floatval($n['horas_laboradas'] ?? 0); ?>"
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
    data-vacaciones-acumuladas-mes="<?php echo $n['vacaciones_acumuladas_mes'] ?? 0; ?>"
    data-importe-vacaciones-mes="<?php echo $n['importe_vacaciones_acumulado_mes'] ?? 0; ?>"
    data-tiene-cuenta="<?php echo $tiene_cuenta ? 'si' : 'no'; ?>"
    data-tipo-descuento="<?php echo htmlspecialchars($tipo_descuento_n); ?>"
    data-no-acumular-vacaciones="<?php echo intval($n['no_acumular_vacaciones'] ?? 0); ?>"
    data-numero-nomina="<?php echo htmlspecialchars(!empty($n['numero_nomina']) ? $n['numero_nomina'] : 'Borrador'); ?>"
    data-cargo="<?php echo htmlspecialchars($n['cargo'] ?? 'S/D'); ?>"
	data-cargo-id="<?php echo $n['cargo_id'] ?? 0; ?>"
	data-escala-numero="<?php echo $n['escala_numero'] ?? 0; ?>"
	data-centro-costo-codigo="<?php echo $n['centro_costo_codigo'] ?? 0; ?>"
	data-dias-acumulados-value="<?php echo $n['dias_acumulados'] ?? 0; ?>"
	data-pagos-mn="<?php echo htmlspecialchars(json_encode($pagos_mn_por_nomina[$n['id']] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">
	
	
    
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
            <td class="text-center col-noct-t">
                <?php if (!$contabilizada): ?>
                    <input type="text" class="edit-input edit-noct-temprana" value="<?php echo number_format($n['horas_nocturnas_tempranas'] ?? 0, 2); ?>">
                <?php else: echo number_format($n['horas_nocturnas_tempranas'] ?? 0, 2); endif; ?>
            </td>
            <td class="text-end col-noct-t-imp">$<?php echo number_format($n['importe_nocturnas_tempranas'] ?? 0, 2); ?></td>
            <td class="text-center col-noct-d">
                <?php if (!$contabilizada): ?>
                    <input type="text" class="edit-input edit-noct-tardia" value="<?php echo number_format($n['horas_nocturnas_tardias'] ?? 0, 2); ?>">
                <?php else: echo number_format($n['horas_nocturnas_tardias'] ?? 0, 2); endif; ?>
            </td>
            <td class="text-end col-noct-d-imp">$<?php echo number_format($n['importe_nocturnas_tardias'] ?? 0, 2); ?></td>
            <td class="text-center col-dt">
                <?php if (!$contabilizada): ?>
                    <input type="text" class="edit-input edit-doble-turno" value="<?php echo number_format($n['horas_doble_turno'] ?? 0, 2); ?>">
                <?php else: echo number_format($n['horas_doble_turno'] ?? 0, 2); endif; ?>
            </td>
            <td class="text-end col-dt-imp">$<?php echo number_format($n['importe_doble_turno'] ?? 0, 2); ?></td>
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
        <td class="text-end col-salario-basico salario-basico">$<?php echo number_format($n['salario_mensual'] ?? 0, 2); ?></td>
        <td class="text-end col-valor-hora salario-hora-real">$<?php echo number_format($n['salario_hora_ordinaria'] ?? 0, 2); ?></td>
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
            <?php if (floatval($n['horas_laboradas'] ?? 0) > 0): ?>
                <span class="badge bg-info text-dark ms-1" title="Nómina por horas trabajadas (acumula vacaciones)"><?php echo number_format($n['horas_laboradas'], 2); ?> h</span>
            <?php endif; ?>
        </td>
        <td class="text-end col-otros-pagos">
            <?php if ($n['estado'] == 'borrador'): ?>
                <input type="text" class="edit-input edit-otros-pagos" value="<?php echo number_format($n['otros_salarios'] ?? 0, 2); ?>">
            <?php else: echo "$" . number_format($n['otros_salarios'] ?? 0, 2); endif; ?>
        </td>
        <td class="text-center col-vacaciones-dias vacaciones-dias">
            <?php echo number_format((($n['estado'] == 'contabilizado') ? ($n['total_vacaciones_acumuladas'] ?? $n['dias_acumulados'] ?? 0) : ($n['dias_acumulados'] + $dias_acum_proy)), 2); ?>
        </td>
        <td class="text-end col-vacaciones-imp vacations-importe">
            <?php echo "$" . number_format((($n['estado'] == 'contabilizado') ? ($n['total_importe_vacaciones_acumuladas'] ?? 0) : (($n['dias_acumulados'] + $dias_acum_proy) * ($n['salario_mensual'] / $dias_laborables))), 2); ?>
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
            <span class="text-white-50" style="font-size:0.72rem;">Borrador</span>
        <?php endif; ?>
    </td>
    <td class="text-center col-acciones">
        <?php if ($n['estado'] == 'borrador'): ?>
            <button class="btn-icon btn-icon-success guardar-fila" title="Guardar" data-tooltip="Guardar" data-tooltip-theme="success"><i class="fas fa-save"></i></button>
            <button class="btn-icon btn-icon-danger eliminar-fila" data-id="<?php echo $n['id']; ?>" data-nombre="<?php echo htmlspecialchars($n['nombre_completo']); ?>" title="Eliminar" data-tooltip="Eliminar" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button>
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
        <!-- TFOOT PARA NÓMINA EXTRAORDINARIA (28 + N columnas) -->
        <!-- ========================================== -->
        <tr class="fw-bold">
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            <td class="text-end"></td><td class="text-end"></td><td class="text-end"></td>
            
            <td class="text-end fw-bold">TOTALES:</td>
            <td class="text-end total-salario-basico-footer">$<?php echo number_format($total_salario_basico, 2); ?></td>
            <td class="text-center total-horas-footer"><?php echo number_format($total_horas, 2); ?></td>
            <td class="text-center"><?php echo number_format($total_noct_tempranas, 2); ?></td>
            <td class="text-end">$<?php echo number_format($total_importe_noct_tempranas, 2); ?></td>
            <td class="text-center"><?php echo number_format($total_noct_tardias, 2); ?></td>
            <td class="text-end">$<?php echo number_format($total_importe_noct_tardias, 2); ?></td>
            <td class="text-center"><?php echo number_format($total_doble_turno, 2); ?></td>
            <td class="text-end">$<?php echo number_format($total_importe_doble_turno, 2); ?></td>
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
            <td class="text-end total-salario-basico-footer">$<?php echo number_format($total_salario_basico, 2); ?></td> <!-- S. Básico -->
            <td class="text-end">-</td> <!-- Tarf. -->
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
            <td class="text-center"><?php echo $total_horas > 0 ? number_format($total_horas, 2) . ' h' : '-'; ?></td> <!-- Columna Concepto (horas totales) -->
            <td class="text-end total-monto-bono-footer">$<?php echo number_format($total_pago_resultado, 2); ?></td> <!-- Monto Ajuste -->
            <td class="text-end total-otros-pagos-footer">$<?php echo number_format($total_otros_pagos, 2); ?></td> <!-- Otros Pagos -->
            <td class="text-center total-vacaciones-dias-footer"><?php echo number_format($total_vacaciones_dias, 2); ?></td> <!-- Vacaciones Días -->
            <td class="text-end total-vacaciones-importe-footer">$<?php echo number_format($total_vacaciones_importe, 2); ?></td> <!-- Vacaciones Importe -->

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
                    <i class="fas fa-user-plus me-2" style="color: var(--color-success);"></i>
                    Agregar trabajadores a nómina automática
                </h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3" style="background: rgba(var(--color-success-rgb), 0.15); border: 0.0625rem solid var(--color-success); border-radius: 0.625rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    Se agregarán <strong>solo los trabajadores que NO estén actualmente en esta nómina</strong>.
                    Usarán los valores por defecto (horas mensuales, sin feriados, sin descuentos).
                </div>
                
                <!-- Filtros -->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Filtrar por Área</label>
                        <select id="filterAutoArea" class="form-select form-select-sm" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
                            <option value="">-- Todas --</option>
                            <?php foreach ($all_areas as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Centro de Costo</label>
                        <select id="filterAutoCC" class="form-select form-select-sm" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
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
                <div id="autoWorkerList" class="worker-list-container" style="max-height:21.875rem; overflow-y: auto;">
                    <div class="text-center p-3 text-white-50">Cargando trabajadores...</div>
                </div>
            </div>
<div class="modal-footer">
    <div class="me-auto text-muted small">
        <i class="fas fa-check-circle text-success me-1"></i>
        <span id="selectedAutoCount">0</span> trabajadores seleccionados
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn-win-info" id="btnSeleccionarTodosAuto" title="Seleccionar todos disponibles" data-tooltip="Seleccionar todos disponibles" data-tooltip-theme="success" style="background: rgba(var(--color-success-rgb), 0.2); border-color: var(--color-success);">
            <i class="fas fa-check-double me-2"></i>Seleccionar todos disponibles
        </button>
        <button type="button" class="btn-win" data-bs-dismiss="modal" data-tooltip="Cancelar" data-tooltip-theme="danger">
            <i class="fas fa-times me-2"></i>Cancelar
        </button>
        <button type="button" class="btn-win-success" id="btnConfirmarAgregarAuto" title="Agregar trabajadores seleccionados a nómina" data-tooltip="Agregar trabajadores seleccionados a nómina" data-tooltip-theme="success" disabled>
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
            <div class="modal-header" style="background: linear-gradient(135deg, var(--color-success), #0ea5e9); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-percent fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Seleccionar Tipo de Descuento</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Elija el método de cálculo para la nómina</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <input type="hidden" id="descuentoGeneralTarget" value="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionTotalDescuentosGen" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-chart-line fa-2x" style="color: #f59e0b;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Total Descuentos por rangos (ISIP)</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Aplica descuentos progresivos del 3%, 5%, 7.5% hasta 50% según rangos de ingreso</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionSoloCessGen" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-shield-alt fa-2x" style="color: var(--color-success);"></i>
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
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding:1rem 1.5rem;">
                <button type="button" class="btn-win" data-bs-dismiss="modal" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-win-primary" id="btnConfirmarDescuentoGen" title="Siguiente paso" data-tooltip="Siguiente paso" data-tooltip-theme="primary" disabled>
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
				<button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formExtraordinaria">
                <input type="hidden" name="tipo_descuento_extra" id="tipoDescuentoExtra" value="total_rangos">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterExtraArea" class="form-select form-select-sm filter-modal-worker" data-modal="extra" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
<select id="filterExtraCC" class="form-select form-select-sm filter-modal-worker" data-modal="extra" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
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
                            <div id="selectedExtraList" class="selected-workers-container" style="min-height:12.5rem;">
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
                    <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_extraordinaria" class="btn-win-primary" id="btnGenerarExtraordinaria" title="Generar nómina extraordinaria" data-tooltip="Generar nómina extraordinaria" data-tooltip-theme="success"><i class="fas fa-save me-2"></i>Generar Nómina</button>
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
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
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
                                    <select id="filterVacArea" class="form-select form-select-sm filter-modal-worker" data-modal="vac" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
                                    <select id="filterVacCC" class="form-select form-select-sm filter-modal-worker" data-modal="vac" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
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
                            <div id="selectedWorkersList" class="selected-workers-container" style="min-height:12.5rem;">
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
                    <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="agregar_vacaciones" class="btn-win-primary" id="btnAgregarVacaciones" title="Guardar vacaciones" data-tooltip="Guardar vacaciones" data-tooltip-theme="success"><i class="fas fa-save me-2"></i>Guardar</button>
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
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formBonos">
                <input type="hidden" name="tipo_descuento_bono" id="tipoDescuentoBono" value="total_rangos">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label"><i class="fas fa-sliders-h me-1"></i> Tipo de Ajuste de la Nómina</label>
                            <div class="d-flex align-items-center gap-2" id="tipoAjusteBadge">
                                <span class="badge rounded-pill" id="badgeTipoAjuste" style="background: rgba(var(--color-success-rgb),0.15); color: var(--color-success); padding:0.5rem 0.875rem; font-size:0.9rem; font-weight: 600;"><i class="fas fa-money-bill-wave me-1"></i> Pago directo específico</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterBonoArea" class="form-select form-select-sm filter-modal-worker" data-modal="bono" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
                                        <option value="">-- Todas --</option>
                                        <?php foreach ($all_areas as $a): ?>
                                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre_area']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Centro de Costo</label>
                                    <select id="filterBonoCC" class="form-select form-select-sm filter-modal-worker" data-modal="bono" title="Filtrar por centro de costo" data-tooltip="Filtrar por centro de costo" data-tooltip-theme="secondary">
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
    <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 0.0625rem solid rgba(255,255,255,0.08);">
        <label class="form-label text-warning" style="font-weight: 600;">
            <i class="fas fa-calculator me-1"></i> Fondo Inicial para Distribución ($)
        </label>
        <input type="number" id="montoInicialBono" class="form-control form-control-custom w-100 text-white" 
               style="background: rgba(20,20,30,0.8); border: 0.0625rem solid rgba(255,255,255,0.15);" 
               placeholder="Ingrese el monto total disponible para repartir..." min="0" step="0.01">
        <small class="text-muted d-block mt-1">Este fondo servirá de límite y referencia durante el cálculo.</small>
    </div>

    <label class="form-label"><i class="fas fa-list me-1"></i> Bonos y/o Pagos a generar</label>
    <div id="bonosList" class="bono-list-container mb-3" style="min-height:11.25rem;"></div>
    
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
        <div class="info-row" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding-top:0.5rem; margin-top:0.5rem;">
            <span>Monto Restante en fondo:</span>
            <span id="previewMontoRestante" class="info-value-success" style="font-weight: bold; font-size:1.1rem;">$0.00</span>
        </div>
    </div>
</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_bono" class="btn-win-primary" id="btnGenerarBonos" title="Generar pago de bonos" data-tooltip="Generar pago de bonos" data-tooltip-theme="success"><i class="fas fa-save me-2"></i>Generar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- MODAL TRABAJADORES SIN NÓMINA -->
<div class="modal fade" id="modalSinNomina" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #b91c1c, #ef4444); border-bottom: none;">
                <h5 class="modal-title text-white"><i class="fas fa-user-slash me-2"></i> Trabajadores Sin Nómina</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-3">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;"><i class="fas fa-calendar-alt me-1"></i> Año</label>
                        <select id="sinNominaAnio" class="form-select form-select-sm" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary">
                            <?php
                            $stmt_sn = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) as anio FROM nominas ORDER BY anio DESC");
                            $anios_sn = $stmt_sn->fetchAll(PDO::FETCH_COLUMN);
                            if (empty($anios_sn)) $anios_sn = [(int)date('Y')];
                            $periodo_ref_sn = date('Y-m');
                            $anio_ref_sn = (int)substr($periodo_ref_sn, 0, 4);
                            $mes_ref_sn = (int)substr($periodo_ref_sn, 5, 2);
                            if (!in_array($anio_ref_sn, $anios_sn)) $anios_sn[] = $anio_ref_sn;
                            foreach ($anios_sn as $yy): ?>
                            <option value="<?php echo $yy; ?>" <?php echo $yy == $anio_ref_sn ? 'selected' : ''; ?>><?php echo $yy; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #60a5fa;"><i class="fas fa-calendar-day me-1"></i> Mes</label>
                        <select id="sinNominaMes" class="form-select form-select-sm" title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary">
                            <?php for ($mm = 1; $mm <= 12; $mm++): ?>
                            <option value="<?php echo $mm; ?>" <?php echo $mm == $mes_ref_sn ? 'selected' : ''; ?>><?php echo ucfirst(nombreMesEspanol($mm)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn-win btn-win-sm" id="btnBuscarSinNomina" title="Buscar trabajadores sin nómina" data-tooltip="Buscar trabajadores sin nómina" data-tooltip-theme="primary"><i class="fas fa-search me-1"></i> Buscar</button>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <span class="badge" id="sinNominaResumen" style="background: rgba(248,113,113,0.15); color: #f87171; font-size:0.8rem;"><i class="fas fa-user-slash me-1"></i>Sin datos</span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:26.25rem; overflow-y: auto;">
                    <table class="table table-sm table-dark table-hover border-secondary align-middle" id="tablaSinNomina">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Expediente</th>
                                <th>CI</th>
                                <th>Nombre Completo</th>
                                <th>Área</th>
                                <th>Centro de Costo</th>
                                <th>Fecha Alta</th>
                                <th>Fecha Baja</th>
                                <th>Última Nómina</th>
                                <th>Total</th>
                                <th>Devengado</th>
                                <th>Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="12" class="text-center text-muted"><i class="fas fa-spinner fa-pulse me-1"></i> Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top:0.0625rem solid rgba(255,255,255,0.1);">
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaPrint" title="Imprimir listado sin nómina" data-tooltip="Imprimir listado sin nómina" data-tooltip-theme="info"><i class="fas fa-print text-warning me-1"></i> Imprimir</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaPDF" title="Exportar como PDF" data-tooltip="Exportar como PDF" data-tooltip-theme="info"><i class="fas fa-file-pdf text-danger me-1"></i> PDF</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaWord" title="Exportar como Word" data-tooltip="Exportar como Word" data-tooltip-theme="info"><i class="fas fa-file-word text-primary me-1"></i> WORD</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaExcel" title="Exportar como Excel" data-tooltip="Exportar como Excel" data-tooltip-theme="info"><i class="fas fa-file-excel text-success me-1"></i> EXCEL</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaCSV" title="Exportar como CSV" data-tooltip="Exportar como CSV" data-tooltip-theme="info"><i class="fas fa-file-csv text-info me-1"></i> CSV</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinNominaTXT" title="Exportar como TXT" data-tooltip="Exportar como TXT" data-tooltip-theme="info"><i class="fas fa-file-alt text-secondary me-1"></i> TXT</button>
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Selección Tipo de Resumen Salarial -->
<div class="modal fade" id="modalSeleccionResumenSalarial" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #059669, #0ea5e9); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-tie fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Seleccionar Tipo de Resumen</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Elija el tipo de resumen salarial a generar</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionResumenPorTrabajador" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-user fa-2x" style="color: #34d399;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">POR TRABAJADOR</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Listado Total Salario Devengado por Trabajador según año, mes y estado</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionResumenPorCuentaBancaria" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-credit-card fa-2x" style="color: #f59e0b;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">POR CUENTA BANCARIA</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Resumen Sin Tarjeta por tipos de nóminas (sin, con o todas las cuentas)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding:1rem 1.5rem;">
                <button type="button" class="btn-win" data-bs-dismiss="modal" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSinCuenta" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, #92400e, #d97706); border-bottom: none;">
                <h5 class="modal-title text-white" id="sinCuentaModalTitle"><i class="fas fa-credit-card me-2"></i> Resumen Sin Tarjeta</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body py-4">
                <div class="row g-3 align-items-end mb-2">
                    <div class="col-md-auto" style="width:5.5rem;">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #f59e0b;"><i class="fas fa-calendar-alt me-1"></i> Año</label>
                        <select id="sinCuentaAnio" class="form-select form-select-sm" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary">
                            <?php
                            $stmt_sc = $pdo->query("SELECT DISTINCT YEAR(periodo_desde) as anio FROM nominas ORDER BY anio DESC");
                            $anios_sc = $stmt_sc->fetchAll(PDO::FETCH_COLUMN);
                            if (empty($anios_sc)) $anios_sc = [(int)date('Y')];
                            $periodo_ref_sc = date('Y-m');
                            $anio_ref_sc = (int)substr($periodo_ref_sc, 0, 4);
                            $mes_ref_sc = (int)substr($periodo_ref_sc, 5, 2);
                            if (!in_array($anio_ref_sc, $anios_sc)) $anios_sc[] = $anio_ref_sc;
                            foreach ($anios_sc as $yy): ?>
                            <option value="<?php echo $yy; ?>" <?php echo $yy == $anio_ref_sc ? 'selected' : ''; ?>><?php echo $yy; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto" style="width:7rem;">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #f59e0b;"><i class="fas fa-calendar-day me-1"></i> Mes</label>
                        <select id="sinCuentaMes" class="form-select form-select-sm" title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary">
                            <?php for ($mm = 1; $mm <= 12; $mm++): ?>
                            <option value="<?php echo $mm; ?>" <?php echo $mm == $mes_ref_sc ? 'selected' : ''; ?>><?php echo ucfirst(nombreMesEspanol($mm)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #f59e0b;"><i class="fas fa-list-check me-1"></i> Estado</label>
                        <select id="sinCuentaEstado" class="form-select form-select-sm" title="Filtrar por estado" data-tooltip="Filtrar por estado" data-tooltip-theme="secondary">
                            <option value="contabilizado" selected>Contabilizado</option>
                            <option value="borrador">Borrador</option>
                            <option value="todos">Todos los estados</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #f59e0b;"><i class="fas fa-credit-card me-1"></i> Cuenta Bancaria</label>
                        <select id="sinCuentaCuenta" class="form-select form-select-sm" title="Filtrar por cuenta bancaria" data-tooltip="Filtrar por cuenta bancaria" data-tooltip-theme="secondary">
                            <option value="sin" selected>Sin cuenta</option>
                            <option value="con">Con cuenta</option>
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                    <div class="col-md">
                        <label class="form-label mb-1" style="font-size:0.7rem; font-weight: 600; color: #f59e0b;"><i class="fas fa-user me-1 text-info"></i> Nombre del Trabajador</label>
                        <div class="buscador-trabajador-modal">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="background: rgba(96, 165, 250, 0.15); border: 0.0625rem solid rgba(96, 165, 250, 0.3); color: #60a5fa;">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="sinCuentaBuscarTrabajador" class="form-control" placeholder="Buscar por nombre, código o CI (opcional)..." autocomplete="off" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                                <button class="btn btn-sm" type="button" id="sinCuentaLimpiarTrabajador" data-tooltip="Limpiar búsqueda" data-tooltip-theme="danger" style="background: rgba(239,68,68,0.2); border: 0.0625rem solid rgba(239,68,68,0.3); color: #fca5a5;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="sinCuentaResultadosBusqueda" class="dropdown-menu" style="width:100%; max-height:15.625rem; overflow-y: auto; display: none; background: #1e1e2e; border: 0.0625rem solid rgba(96,165,250,0.3); z-index: 1100;">
                            </div>
                        </div>
                        <input type="hidden" id="sinCuentaTrabajadorId" value="">
                        <div id="sinCuentaTrabajadorInfo" class="mt-2 small text-white-50"></div>
                    </div>
                    <div class="col-md-auto">
                        <button type="button" class="btn-win btn-win-sm" id="btnBuscarSinCuenta" title="Buscar trabajadores" data-tooltip="Buscar trabajadores" data-tooltip-theme="warning"><i class="fas fa-search me-1"></i> Buscar</button>
                    </div>
                </div>
                <div class="mb-3">
                    <span id="sinCuentaResumenWrap" title="Filtros aplicados" data-tooltip="Filtros aplicados" data-tooltip-theme="secondary" style="display:block; width:100%; color:#fbbf24; font-size:0.75rem; line-height:1.35; text-align:left; background:rgba(245,158,11,0.08); border:0.0625rem solid rgba(245,158,11,0.2); border-radius:0.5rem; padding:0.375rem 0.625rem;">
                        <span class="d-block fw-semibold" id="sinCuentaResumen"><i class="fas fa-filter me-1"></i>Sin datos</span>
                    </span>
                </div>
                <div class="table-responsive" style="max-height:26.25rem; overflow-y: auto;">
                    <table class="table table-sm table-dark table-hover border-secondary align-middle" id="tablaSinCuenta">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>No CI.</th>
                                <th>Nombre y Apellidos</th>
                                <th class="text-end">SALAR. BÁSICO</th>
                                <th class="text-end">NOCT. H. EXT</th>
                                <th class="text-end">VACAC.</th>
                                <th class="text-end">AJUSTE Y/O LIQUID.</th>
                                <th class="text-end">RENDIM.</th>
                                <th class="text-end">TOTAL A PAGAR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="9" class="text-center text-muted"><i class="fas fa-spinner fa-pulse me-1"></i> Cargando...</td></tr>
                        </tbody>
                        <tfoot>
                            <tr style="background: rgba(245,158,11,0.12); font-weight:600;">
                                <td colspan="3" class="text-end">TOTAL GENERAL</td>
                                <td class="text-end" id="sinCuentaTotBasico">0.00</td>
                                <td class="text-end" id="sinCuentaTotNoct">0.00</td>
                                <td class="text-end" id="sinCuentaTotVacac">0.00</td>
                                <td class="text-end" id="sinCuentaTotAjuste">0.00</td>
                                <td class="text-end" id="sinCuentaTotRendim">0.00</td>
                                <td class="text-end" id="sinCuentaTotTotal">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top:0.0625rem solid rgba(255,255,255,0.1);">
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaPrint" title="Imprimir resumen sin tarjeta" data-tooltip="Imprimir resumen sin tarjeta" data-tooltip-theme="info"><i class="fas fa-print text-warning me-1"></i> Imprimir</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaPDF" title="Exportar como PDF" data-tooltip="Exportar como PDF" data-tooltip-theme="info"><i class="fas fa-file-pdf text-danger me-1"></i> PDF</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaWord" title="Exportar como Word" data-tooltip="Exportar como Word" data-tooltip-theme="info"><i class="fas fa-file-word text-primary me-1"></i> WORD</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaExcel" title="Exportar como Excel" data-tooltip="Exportar como Excel" data-tooltip-theme="info"><i class="fas fa-file-excel text-success me-1"></i> EXCEL</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaCSV" title="Exportar como CSV" data-tooltip="Exportar como CSV" data-tooltip-theme="info"><i class="fas fa-file-csv text-info me-1"></i> CSV</button>
                <button type="button" class="btn-win btn-win-sm" id="btnSinCuentaTXT" title="Exportar como TXT" data-tooltip="Exportar como TXT" data-tooltip-theme="info"><i class="fas fa-file-alt text-secondary me-1"></i> TXT</button>
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<!-- 1. Librerías Base (jQuery y Bootstrap) -->
<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/select2.min.js"></script>

<!-- 2. Dependencias de Exportación (Deben cargarse ANTES de los botones de DataTables) -->
<script src="../js/jszip.min.js"></script>
<script src="../js/pdfmake.min.js"></script>
<script src="../js/vfs_fonts.js"></script>
<script src="../js/xlsx.full.min.js"></script>
<script src="../js/exceljs.min.js"></script>
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

<?php include __DIR__ . '/js/modulo_nominas_unico_js.php'; ?>

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
                <div class="modal-body" style="padding:1.5rem;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-pen-alt me-2" style="color: #f59e0b;"></i>
                            Descripción / Observaciones
                            <span class="text-danger">*</span>
                        </label>
                        <textarea name="observaciones_cierre" id="observacionesCierre" class="form-control" rows="4" 
                                  placeholder="Ej: Nómina correspondiente al mes de enero 2026..."
                                  style="background: rgba(20,20,30,0.8); border: 0.0625rem solid rgba(255,255,255,0.15); color: white; border-radius: 0.75rem; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding:1.25rem 1.5rem;">
                    <button type="button" class="btn-win" id="btnCancelarContabilizar" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn-win-warning" id="btnConfirmarContabilizar" title="Confirmar contabilización" data-tooltip="Confirmar contabilización" data-tooltip-theme="warning" disabled>
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
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionTotalDescuentos" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-chart-line fa-2x" style="color: #f59e0b;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #ffffff;">Total Descuentos por rangos (ISIP)</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Aplica descuentos progresivos del 3%, 5%, 7.5% hasta 50% según rangos de ingreso</p>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-chevron-right" style="color: #60a5fa;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="card-option-descuento" id="opcionSoloCess" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-shield-alt fa-2x" style="color: var(--color-success);"></i>
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
                
                <div class="alert-dark" id="infoCess" style="display: none; margin-top:1.25rem; background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid var(--color-success); border-radius: 0.75rem; padding:0.75rem;">
                    <i class="fas fa-info-circle me-2" style="color: var(--color-success);"></i>
                    <strong style="color: var(--color-success);">CESS progresivo:</strong>
                    <ul class="mt-2 mb-0 small" style="color: rgba(255,255,255,0.7);">
                        <li>Salario hasta 15,000 CUP → <strong>5%</strong></li>
                        <li>Exceso sobre 15,000 CUP → <strong>10%</strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding:1rem 1.5rem;">
                <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-win-primary" id="btnConfirmarTipoDescuento" title="Generar nómina" data-tooltip="Generar nómina" data-tooltip-theme="success" disabled>
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
                        <span class="badge" id="modalRegistroContador" style="font-size:0.75rem; background-color: rgba(255,255,255,0.12) !important; border: 0.0625rem solid rgba(255,255,255,0.1); color: #60a5fa;">
                            Cargando...
                        </span>
                    </div>
                    
                    <!-- NUEVO: Combo editable para buscar trabajador -->
                    <div class="buscador-trabajador-modal" style="min-width:17.5rem;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="background: rgba(96, 165, 250, 0.15); border: 0.0625rem solid rgba(96, 165, 250, 0.3); color: #60a5fa;">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   id="buscadorTrabajadorModal" 
                                   class="form-control" 
                                   placeholder="Buscar por nombre, código o CI..."
                                   autocomplete="off"
                                   style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96, 165, 250, 0.3); color: white;">
                            <button class="btn btn-sm" type="button" id="limpiarBuscadorModal" data-tooltip="Limpiar búsqueda" data-tooltip-theme="danger" style="background: rgba(239,68,68,0.2); border: 0.0625rem solid rgba(239,68,68,0.3); color: #fca5a5;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="resultadosBusquedaModal" class="dropdown-menu" style="width:100%; max-height:18.75rem; overflow-y: auto; display: none; background: #1e1e2e; border: 0.0625rem solid rgba(96, 165, 250, 0.3);">
                            <!-- Resultados dinámicos -->
                        </div>
                    </div>
                    
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
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
                    <button type="button" class="btn-win" id="btnModalReset" title="Restablecer cambios" data-tooltip="Restablecer cambios" data-tooltip-theme="warning" style="display: none;"><i class="fas fa-undo-alt me-2"></i>Restablecer</button>
                </div>
                <button type="button" class="btn-win" id="btnModalPrimero" title="Ir al primer registro" data-tooltip="Ir al primer registro" data-tooltip-theme="primary"><i class="fas fa-angle-double-left me-2"></i>Primero</button>
                <button type="button" class="btn-win" id="btnModalAnterior" title="Ir al registro anterior" data-tooltip="Ir al registro anterior" data-tooltip-theme="primary"><i class="fas fa-chevron-left me-2"></i>Anterior</button>
                <button type="button" class="btn-win" id="btnModalSiguiente" title="Ir al siguiente registro" data-tooltip="Ir al siguiente registro" data-tooltip-theme="primary"><i class="fas fa-chevron-right me-2"></i>Siguiente</button>
                <button type="button" class="btn-win" id="btnModalUltimo" title="Ir al último registro" data-tooltip="Ir al último registro" data-tooltip-theme="primary"><i class="fas fa-angle-double-right me-2"></i>Último</button>
                <button type="button" class="btn-win-primary" id="btnModalActualizar" title="Actualizar registro" data-tooltip="Actualizar registro" data-tooltip-theme="success" style="display: none;"><i class="fas fa-save me-2"></i>Actualizar</button>
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

function procederGeneracionAutomatica() {
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
}

$('#btnConfirmarTipoDescuento').on('click', function() {
    if (!tipoDescuentoSeleccionado) return;
    
    // Pre-validación: impedir generar una nómina automática ya existente en el período
    var anioSel = document.getElementById('anioSelect');
    var mesSel = document.getElementById('mesSelect');
    var periodoCheck = (anioSel && mesSel && anioSel.value && mesSel.value)
        ? anioSel.value + '-' + mesSel.value
        : (new URLSearchParams(window.location.search)).get('periodo') || '';
    
    fetch('nominas.php?action=verificar_nomina_automatica&ajax=1&periodo=' + encodeURIComponent(periodoCheck) + '&t=' + Date.now(), { cache: 'no-store' })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            if (data && data.exists) {
                var fmtCUP = new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP', minimumFractionDigits: 2 });
                Swal.fire({
                    icon: 'warning',
                    title: 'Imposible generar nuevamente una Nómina Automática ya Existente:',
                    html: '<div style="text-align:left; font-size:0.85rem; line-height:1.8;">'
                        + '<p style="margin:0 0 0.5rem;"><strong>Detalle de Nómina:</strong> No.: <strong>' + (data.numero_nomina || '-') + '</strong> | Período: <strong>' + (data.periodo_label || '') + '</strong></p>'
                        + '<p style="margin:0;"><strong>CANTIDAD DE TRABAJADORES EN NÓMINA:</strong> ' + data.cantidad + '</p>'
                        + '<p style="margin:0;"><strong>IMPORTE DEVENGADO:</strong> ' + fmtCUP.format(data.devengado || 0) + '</p>'
                        + '<p style="margin:0;"><strong>IMPORTE NETO PAGADO:</strong> ' + fmtCUP.format(data.neto || 0) + '</p>'
                        + '</div>',
                    allowOutsideClick: false,
                    showCloseButton: true,
                    confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido'
                });
                return;
            }
            procederGeneracionAutomatica();
        })
        .catch(function() {
            procederGeneracionAutomatica();
        });
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
                <div class="info-vacaciones-tirilla" style="margin-top:0.3125rem; font-size:8.5pt; text-align: left; padding:0.25rem 0.375rem; background-color: #f9f9f9; border: 0.0625rem solid #000; border-top: none; line-height:1.4;">
                    <strong>Vacaciones Acumuladas:</strong> ${diasAcumulados.toFixed(2)} días (${horasVacaciones.toFixed(2)} horas)
                    ${t.tiempoImp > 0 ? ` | <strong>Importe:</strong> $${t.tiempoImp.toFixed(2)}` : ''}
                </div>
            `;
        }

        let tablaTirillaHtml = '';
        if (tipoNominaActiva === 'extraordinaria') {
            tablaTirillaHtml = `
                <table class="tabla-tirilla">
                    <thead>
                        <tr>
                            <th class="centrado">HE/D</th>
                            <th class="centrado">$HE/D</th>
                            <th class="centrado">Nt 7-23h</th>
                            <th class="centrado">$/Nt 7-23h</th>
                            <th class="centrado">Nt 23-7h</th>
                            <th class="centrado">$/Nt 23-7h</th>
                            <th class="centrado">D/T</th>
                            <th class="centrado">$/DT</th>
                            <th class="centrado">Deven.</th>
                            <th class="centrado">Imp. CESS</th>
                            <th class="centrado">Dsctos.</th>
                            <th class="centrado">Ret. Tot.</th>
                            <th class="centrado">Pagado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="centrado">${t.horas || 0}</td>
                            <td class="derecha">$${(t.importeHE || 0).toFixed(2)}</td>
                            <td class="centrado">${t.noctT || 0}</td>
                            <td class="derecha">$${(t.importeNtT || 0).toFixed(2)}</td>
                            <td class="centrado">${t.noctD || 0}</td>
                            <td class="derecha">$${(t.importeNtD || 0).toFixed(2)}</td>
                            <td class="centrado">${t.dt || 0}</td>
                            <td class="derecha">$${(t.importeDT || 0).toFixed(2)}</td>
                            <td class="derecha">$${(t.devengado || 0).toFixed(2)}</td>
                            <td class="derecha">$${(t.impS || 0).toFixed(2)}</td>
                            <td class="derecha">$${(t.descuentos || 0).toFixed(2)}</td>
                            <td class="derecha">$${(t.retenciones || 0).toFixed(2)}</td>
                            <td class="derecha"><span style="color:red;">$${(t.pagado || 0).toFixed(2)}</span></td>
                        </tr>
                    </tbody>
                </table>
            `;
        } else if (tipoNominaActiva === 'automatica') {
            tablaTirillaHtml = `
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
                            <th class="centrado" colspan="2">Vacaciones</th>
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
                            <td class="centrado">${(t.vacAcumMes || 0).toFixed(2)}</td>
                            <td class="derecha">$${(t.importeVacMes || 0).toFixed(2)}</td>
                        </tr>
                    </tbody>
                </table>
            `;
        } else {
            tablaTirillaHtml = `
                <table class="tabla-tirilla">
                    <thead>
                        <tr>
                            ${tipoNominaActiva === 'vacaciones' ? '<th class="centrado">SAL. BÁS.</th>' : ''}
                            ${tipoNominaActiva === 'vacaciones' ? '<th class="centrado">ESC.</th>' : ''}
                            <th class="centrado">TARIFA</th>
                            <th class="centrado">${tipoNominaActiva === 'vacaciones' ? 'DÍAS' : 'HRS'}</th>
                            <th class="derecha">A COBRAR</th>
                            ${tipoNominaActiva === 'vacaciones' ? '' : '<th class="derecha">BONO</th>'}
                            ${tipoNominaActiva === 'vacaciones' ? '' : '<th class="derecha">$/Feriad.</th>'}
                            ${tipoNominaActiva === 'vacaciones' ? '' : '<th class="derecha">DEVENG</th>'}
                            <th class="derecha">CESS</th>
                            <th class="derecha">RET</th>
                            <th class="derecha">PAGADO</th>
                            ${tipoNominaActiva === 'vacaciones' ? '<th class="centrado">DÍAS REST.</th>' : ''}
                            ${tipoNominaActiva === 'vacaciones' ? '<th class="derecha">IMP. SUBMAYOR</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            ${tipoNominaActiva === 'vacaciones' ? `<td class="centrado">$${(t.salarioMensual || 0).toFixed(2)}</td>` : ''}
                            ${tipoNominaActiva === 'vacaciones' ? `<td class="centrado">${(t.escala || '-')}</td>` : ''}
                            <td class="centrado">$${t.tarifaSal.toFixed(2)}</td>
                            <td class="centrado">${tipoNominaActiva === 'vacaciones' ? (t.diasTomados || 0).toFixed(2) : t.horas}</td>
                            <td class="derecha">$${t.aCobrar.toFixed(2)}</td>
                            ${tipoNominaActiva === 'vacaciones' ? '' : `<td class="derecha">$${t.bono.toFixed(2)}</td>`}
                            ${tipoNominaActiva === 'vacaciones' ? '' : `<td class="derecha">$${(t.feriadoImp || 0).toFixed(2)}</td>`}
                            ${tipoNominaActiva === 'vacaciones' ? '' : `<td class="derecha">$${t.devengado.toFixed(2)}</td>`}
                            <td class="derecha">$${t.impS.toFixed(2)}</td>
                            <td class="derecha">$${t.retenciones.toFixed(2)}</td>
                            <td class="derecha">
								<span style="color:red;">$${t.pagado.toFixed(2)}</span>
							</td>
                            ${tipoNominaActiva === 'vacaciones' ? `<td class="centrado">${(t.vacDias || 0).toFixed(2)}</td>` : ''}
                            ${tipoNominaActiva === 'vacaciones' ? `<td class="derecha">$${(t.tiempoImp || 0).toFixed(2)}</td>` : ''}
                        </tr>
                    </tbody>
                </table>
            `;
        }

        filasHtml += `
            <div class="tirilla">
                <div class="header-tirilla">
                    ${escapeHtml(nombreEmpresa)} - NOTIFICACION DE PAGO - <span style="color:red;">${mesAnio}</span> - ${escapeHtml(t.ci)}  <span style="color:red;">${escapeHtml(t.nombre)}</span> (${tipoNomina})
                </div>
                ${tablaTirillaHtml}
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
                margin:0;
                padding:0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Arial', 'Helvetica', sans-serif;
                font-size:12pt;
                background: white;
                margin:0.3125rem;
                padding:0.1875rem;
            }
            .tirilla {
                width:100%;
                margin:0;
                padding:0;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .header-tirilla {
                font-weight: bold;
                font-size:10pt;
                margin:0.1875rem 0;
                padding:0.1875rem 0;
                background-color: #e8e8e8;
                text-align: center;
                border: 0.0625rem solid #aaa;
            }
            .linea-corte {
                font-family: monospace;
                font-size:8pt;
                font-weight: bold;
                color: #000;
                letter-spacing:0;
                margin:0.125rem 0;
                white-space: pre;
                overflow-x: hidden;
                line-height:1;
                background-color: #fff;
            }
            .tabla-tirilla {
                width:100%;
                border-collapse: collapse;
                margin:0.125rem 0;
                font-size:10pt;
            }
            .tabla-tirilla th {
                background-color: #e8e8e8;
                font-weight: bold;
                padding:0.25rem 0.3125rem;
                border: 0.0625rem solid #000;
            }
            .tabla-tirilla td {
                padding:0.25rem 0.3125rem;
                border: 0.0625rem solid #000;
            }
            .centrado {
                text-align: center;
            }
            .derecha {
                text-align: right;
            }
            @media print {
                .no-print { display: none !important; }
                body {
                    margin:0;
                    padding:0.125rem;
                }
                .header-tirilla {
                    background-color: #ddd;
                    border: 0.0625rem solid #000;
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
        ${PRINT_TOOLBAR_HTML}
        ${filasHtml}
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
        <div class="modal-content modal-content-modern" style="border: 0.0625rem solid rgba(255,255,255,0.08); background: var(--card); backdrop-filter: blur(1.25rem);">
            
            <!-- Cabecera Mejorada -->
            <div class="modal-header px-4 py-3" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08); background: linear-gradient(135deg, rgba(30,64,175,0.2) 0%, rgba(37,99,235,0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center" style="width:3rem; height:3rem; background: linear-gradient(135deg, #1e3a8a, #1e40af); border-radius: 0.875rem;">
                        <i class="fas fa-print fa-lg text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color: #ffffff; font-weight: 700; font-size:1.2rem;">Impresión de Nómina Oficial</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.6);">Modelo SC-4-06 - Seleccione alcance y opciones de agrupación</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body px-4 py-4">
                <!-- Información de la nómina actual -->
                <div class="info-nomina-bar mb-4 p-3 rounded" style="background: rgba(59,130,246,0.1); border: 0.0625rem solid rgba(59,130,246,0.2); border-radius: 0.75rem;">
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
                    <label class="form-label d-block fw-semibold mb-3" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">
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
                            <div class="print-option-card selected" data-target-radio="alcanceGeneral" title="Imprimir toda la nómina sin filtro" data-tooltip="Imprimir toda la nómina" data-tooltip-theme="primary">
                                <div class="card-icon"><i class="fas fa-globe-americas"></i></div>
                                <div class="card-text">
                                    <div class="card-title">General</div>
                                    <div class="card-desc">Toda la nómina sin filtrar</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCargo" title="Agrupar por cargo del trabajador" data-tooltip="Agrupar por cargo" data-tooltip-theme="info">
                                <div class="card-icon"><i class="fas fa-briefcase"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Por Cargo</div>
                                    <div class="card-desc">Agrupado por cargo del trabajador</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceArea" title="Agrupar por área de trabajo" data-tooltip="Agrupar por área" data-tooltip-theme="info">
                                <div class="card-icon"><i class="fas fa-building"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Por Área</div>
                                    <div class="card-desc">Agrupado por área de trabajo</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCentro" title="Agrupar por centro de costo" data-tooltip="Agrupar por centro de costo" data-tooltip-theme="info">
                                <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Centro de Costo</div>
                                    <div class="card-desc">Por centro de costo asignado</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceCategoria" title="Agrupar por categoría ocupacional" data-tooltip="Agrupar por categoría" data-tooltip-theme="info">
                                <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Categoría Ocupacional</div>
                                    <div class="card-desc">Por categoría del trabajador</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceEscala" title="Agrupar por escala salarial" data-tooltip="Agrupar por escala" data-tooltip-theme="info">
                                <div class="card-icon"><i class="fas fa-layer-group"></i></div>
                                <div class="card-text">
                                    <div class="card-title">Escala Salarial</div>
                                    <div class="card-desc">Por grupo salarial (I-VI)</div>
                                </div>
                                <div class="card-check"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="print-option-card" data-target-radio="alcanceContrato" title="Agrupar por tipo de contrato" data-tooltip="Agrupar por contrato" data-tooltip-theme="info">
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
                            <div class="print-option-card" data-target-radio="alcanceTirillas" title="Generar tirillas de pago para recortar" data-tooltip="Generar tirillas de pago" data-tooltip-theme="success">
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
                    <div class="mt-3 p-3 rounded" style="background: rgba(0, 0, 0, 0.3); border: 0.0625rem solid rgba(255,255,255,0.08); border-radius: 0.75rem;">
                        <div id="contenedorSelectores"></div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer px-4 py-3" style="border-top: 0.0625rem solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win" data-bs-dismiss="modal" data-tooltip="Cancelar" data-tooltip-theme="danger" style="background: rgba(255,255,255,0.05); border: 0.0625rem solid rgba(255,255,255,0.1);">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                
                <!-- Dropdown de acciones -->
                <div class="dropdown">
                    <button class="btn-nomina-impresa dropdown-toggle" type="button" data-bs-toggle="dropdown" data-tooltip="Opciones de impresión" data-tooltip-theme="primary" style="padding:0.625rem 1.5rem;">
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
        <div class="modal-content modal-content-modern" style="border: 0.0625rem solid rgba(255,255,255,0.08); background: var(--card); backdrop-filter: blur(1.25rem);">
            
            <!-- Cabecera -->
            <div class="modal-header px-4 py-3" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08); background: linear-gradient(135deg, rgba(30,64,175,0.2) 0%, rgba(37,99,235,0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center" style="width:3rem; height:3rem; background: linear-gradient(135deg, #1e3a8a, #1e40af); border-radius: 0.875rem;">
                        <i class="fas fa-file-export fa-lg text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color: #ffffff; font-weight: 700; font-size:1.2rem;">Centro de Exportación y Reportes</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.6);">Seleccione el formato oficial de salida para el procesamiento de datos</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <!-- Opción 1: Nómina Impresa Oficial -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="imprimir_oficial" title="Vista previa para imprimir con formato SC-4-06" data-tooltip="Imprimir nómina oficial" data-tooltip-theme="warning">
                            <div class="card-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1);"><i class="fas fa-print"></i></div>
                            <div class="card-text">
                                <div class="card-title">Nómina Impresa Oficial</div>
                                <div class="card-desc">Vista previa, firmas y formato físico SC-4-06</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 2: PDF -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="pdf_oficial" title="Exportar como documento PDF en formato Carta horizontal" data-tooltip="Exportar a PDF" data-tooltip-theme="danger">
                            <div class="card-icon" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);"><i class="fas fa-file-pdf"></i></div>
                            <div class="card-text">
                                <div class="card-title">Documento PDF Oficial</div>
                                <div class="card-desc">Exportación directa en formato Carta horizontal</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 3: Excel -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="excel_oficial" title="Exportar como hoja de cálculo Excel (.XLS)" data-tooltip="Exportar a Excel" data-tooltip-theme="success">
                            <div class="card-icon" style="color: var(--color-success); background: rgba(var(--color-success-rgb), 0.1);"><i class="fas fa-file-excel"></i></div>
                            <div class="card-text">
                                <div class="card-title">Libro de Excel (.XLS)</div>
                                <div class="card-desc">Hoja de cálculo estructurada con fórmulas base</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 4: Word -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="word_oficial" title="Exportar como documento Word (.DOC) paginado" data-tooltip="Exportar a Word" data-tooltip-theme="primary">
                            <div class="card-icon" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1);"><i class="fas fa-file-word"></i></div>
                            <div class="card-text">
                                <div class="card-title">Documento Word (.DOC)</div>
                                <div class="card-desc">Esquema de procesamiento de textos paginado</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 5: CSV -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="csv_delimitado" title="Exportar como archivo plano CSV para intercambio de datos" data-tooltip="Exportar a CSV" data-tooltip-theme="info">
                            <div class="card-icon" style="color: #06b6d4; background: rgba(6, 182, 212, 0.1);"><i class="fas fa-file-csv"></i></div>
                            <div class="card-text">
                                <div class="card-title">Valores Separados por Comas</div>
                                <div class="card-desc">Archivo plano (.CSV) para intercambio de sistemas</div>
                            </div>
                        </div>
                    </div>
                    <!-- Opción 6: Copiar Datos -->
                    <div class="col-md-6">
                        <div class="print-option-card action-export-card" data-action="copiar_portapapeles" title="Copiar datos visibles al portapapeles" data-tooltip="Copiar al portapapeles" data-tooltip-theme="secondary">
                            <div class="card-icon" style="color: #a855f7; background: rgba(168, 85, 247, 0.1);"><i class="fas fa-copy"></i></div>
                            <div class="card-text">
                                <div class="card-title">Copiar al Portapapeles</div>
                                <div class="card-desc">Copia temporal en memoria de los registros visibles</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer px-4 py-3" style="border-top: 0.0625rem solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win" data-bs-dismiss="modal" data-tooltip="Cerrar panel" data-tooltip-theme="danger" style="background: rgba(255,255,255,0.05); border: 0.0625rem solid rgba(255,255,255,0.1);">
                    <i class="fas fa-times me-2"></i>Cerrar Panel
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Selección Tipo de Ajuste -->
<div class="modal fade" id="modalSeleccionTipoAjuste" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-modern">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--color-success), #0ea5e9); border-bottom: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-sliders-h fa-2x me-3" style="color: #ffffff;"></i>
                    <div>
                        <h5 class="modal-title" style="color: white; font-weight: 600;">Seleccionar Tipo de Ajuste</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.8);">Elija el método de cálculo para la nómina de ajuste</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card-option-descuento tipo-ajuste-option selected" id="opcionTipoAjusteDirecto" data-modo="directo" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3"><i class="fas fa-money-bill-wave fa-2x" style="color: var(--color-success);"></i></div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #fff; font-size:0.95rem;">Pago directo específico</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Solo un importe con sus contribuciones. No acumula vacaciones ni requiere horas trabajadas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card-option-descuento tipo-ajuste-option" id="opcionTipoAjusteHoras" data-modo="horas" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3"><i class="fas fa-clock fa-2x" style="color: #f59e0b;"></i></div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #fff; font-size:0.95rem;">Pago por horas trabajadas</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Calcula el importe por horas, acumula vacaciones (9.09%), descuenta CESS y otras retenciones.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card-option-descuento tipo-ajuste-option" id="opcionTipoAjusteLiquidacion" data-modo="liquidacion" style="cursor: pointer; padding:1.25rem; border-radius: 1rem; background: var(--panel-2); border: 0.125rem solid rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <div class="d-flex align-items-center">
                                <div class="me-3"><i class="fas fa-sun fa-2x" style="color: #8b5cf6;"></i></div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1" style="color: #fff; font-size:0.95rem;">Liquidación de fracciones del submayor de vacaciones</h5>
                                    <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">Liquidación final de vacaciones: paga automáticamente el importe de TODOS los días acumulados del trabajador (queda en cero) e incluye fracciones ya liquidadas en ajustes previos.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.1); padding:1rem 1.5rem;">
                <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn-win-primary" id="btnConfirmarTipoAjuste" title="Siguiente paso" data-tooltip="Siguiente paso" data-tooltip-theme="primary">
                    <i class="fas fa-arrow-right me-2"></i>Siguiente
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalAjuste" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-modern">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Generar Nómina de Ajuste</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="formAjuste">
                <input type="hidden" name="tipo_descuento_ajuste" id="tipoDescuentoAjuste" value="total_rangos">
                <input type="hidden" name="modo_ajuste" id="modoAjuste" value="directo">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small">Filtrar por Área</label>
                                    <select id="filterAjusteArea" class="form-select form-select-sm filter-modal-worker" data-modal="ajuste" title="Filtrar por área" data-tooltip="Filtrar por área" data-tooltip-theme="secondary">
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
                            <div id="ajusteSelectedList" class="selected-workers-container" style="min-height:11.25rem;"></div>
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
                    <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="submit" name="generar_nomina_ajuste" class="btn-win-primary" id="btnGenerarAjuste" title="Generar ajuste" data-tooltip="Generar ajuste" data-tooltip-theme="success"><i class="fas fa-save me-2"></i>Generar Ajuste</button>
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
    box-shadow: 0 0 0 0.125rem rgba(99, 102, 241, 0.2);
}

#filtroAnio option {
    background: var(--panel);
    color: #fff;
    padding:0.375rem;
}

#filtroAnio option:hover {
    background: #6366f1;
}

/* Barras de comparación */
.barra-comparacion-container {
    position: relative;
    width:100%;
    height:1.375rem;
    background: rgba(255,255,255,0.06);
    border-radius: 0.25rem;
    overflow: hidden;
}

.barra-comparacion-fill {
    height:100%;
    background: linear-gradient(90deg, #6366f1, #a855f7);
    border-radius: 0.25rem;
    transition: width 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}

.barra-comparacion-label {
    position: absolute;
    right:0.5rem;
    top:50%;
    transform: translateY(-50%);
    font-size:0.7rem;
    font-weight: 600;
    color: #fff;
    text-shadow: 0 0.0625rem 0.1875rem rgba(0,0,0,0.6);
    line-height:1;
}
</style>
<div class="modal fade" id="modalFullHistorial" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-modern" style="background: var(--card); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255,255,255,0.1); position: relative;">
            
            <!-- BOTÓN X FLOTANTE TOP DERECHA -->
            <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger" style="
                position: absolute;
                top:0.75rem;
                right:1rem;
                z-index: 1050;
                background: rgba(255,255,255,0.08);
                border: none;
                border-radius: 50%;
                width:2rem;
                height:2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                color: rgba(255,255,255,0.5);
                font-size:0.85rem;
                cursor: pointer;
                transition: all 0.25s ease;
                backdrop-filter: blur(0.25rem);
                padding:0;
            " 
            onmouseover="this.style.background='rgba(255,255,255,0.18)'; this.style.color='#fff'; this.style.transform='rotate(90deg)';"
            onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='rgba(255,255,255,0.5)'; this.style.transform='rotate(0deg)';">
                <i class="fas fa-times"></i>
            </button>

            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #a855f7); border: none; padding-right:3.75rem;">
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
                            <label for="filtroAnio" class="text-white-50" style="font-size:0.85rem; font-weight: 500;">
                                <i class="fas fa-calendar-alt me-1"></i>Filtrar por año:
                            </label>
                            <select id="filtroAnio" class="form-select form-select-sm" style="
                                background: rgba(255,255,255,0.06);
                                border: 0.0625rem solid rgba(255,255,255,0.1);
                                color: #fff;
                                border-radius: 0.5rem;
                                padding:0.25rem 1.875rem 0.25rem 0.75rem;
                                font-size:0.85rem;
                                cursor: pointer;
                                min-width:7.5rem;
                                transition: all 0.2s ease;
                            " 
                            onmouseover="this.style.background='rgba(255,255,255,0.12)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.06)'"
                            onchange="filtrarHistorialPorAnio()">
                                <option value="todos" style="background: var(--panel); color: var(--txt);">Todos los años</option>
                                <!-- Los años se llenan con JS -->
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="limpiarFiltroAnio()" data-tooltip="Limpiar filtro" data-tooltip-theme="danger" style="
                            border-radius: 0.5rem;
                            font-size:0.75rem;
                            padding:0.25rem 0.75rem;
                            border-color: rgba(255,255,255,0.15);
                            color: rgba(255,255,255,0.6);
                        " 
                        onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.color='#fff';"
                        onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.6)';">
                            <i class="fas fa-undo me-1"></i>Limpiar
                        </button>
                    </div>

                    <div class="table-responsive" style="max-height:21.875rem; overflow-y: auto;">
                        <table class="table table-dark table-hover border-secondary" id="tablaFullHistorial">
                            <thead class="sticky-top" style="background: var(--panel); z-index: 10;">
                                <tr class="text-info">
                                    <th>Año</th>
                                    <th>Mes</th>
                                    <th class="text-center" style="min-width:13.75rem;">Comparación</th>
                                    <th class="text-end">Importe Registrado ($)</th>
                                </tr>
                            </thead>
                            <tbody><!-- Se llena con JS --></tbody>
                        </table>
                    </div>
                    <div class="mt-3 p-3 rounded d-flex justify-content-between align-items-center" style="background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.2);">
                        <span class="text-white-50">TOTAL ACUMULADO HISTÓRICO:</span>
                        <h4 class="text-success mb-0 fw-bold" id="totalFullHistorial">$0.00</h4>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-close me-1"></i>Cerrar</button>
                <div class="btn-group" role="group">
                    <button type="button" class="btn-win-primary" id="btnImprimirFull" title="Imprimir historial de montos" data-tooltip="Imprimir historial" data-tooltip-theme="info"><i class="fas fa-print me-1"></i> Imprimir</button>
                    <button type="button" class="btn-win-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar historial" data-tooltip="Exportar" data-tooltip-theme="success"><span class="visually-hidden">Exportar</span></button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win">
                        <li><a class="dropdown-item" href="#" id="btnExportPDFFull" title="Exportar a PDF"><i class="fas fa-file-pdf me-2" style="color:#f40f02;"></i>Exportar a PDF</a></li>
                        <li><a class="dropdown-item" href="#" id="btnExportWordFull" title="Exportar a Word"><i class="fas fa-file-word me-2" style="color:#2b579a;"></i>Exportar a Word (DOCX)</a></li>
                        <li><a class="dropdown-item" href="#" id="btnExportExcelFull" title="Exportar a Excel"><i class="fas fa-file-excel me-2" style="color:#21a366;"></i>Exportar a Excel (XLSX)</a></li>
                        <li><a class="dropdown-item" href="#" id="btnExportCsvFull" title="Exportar a CSV"><i class="fas fa-file-csv me-2" style="color:var(--color-success);"></i>Exportar a CSV</a></li>
                        <li><a class="dropdown-item" href="#" id="btnExportTxtFull" title="Exportar a TXT"><i class="fas fa-file-alt me-2" style="color:#eab308;"></i>Exportar a TXT</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL LISTADO TOTAL SALARIO DEVENGADO POR TRABAJADOR        -->
<!-- ========================================================== -->
<div class="modal fade" id="modalListadoDevengadoTrabajador" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-modern" style="border: 0.0625rem solid rgba(255,255,255,0.08); background: var(--card); backdrop-filter: blur(1.25rem);">
            <div class="modal-header px-4 py-3" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08); background: linear-gradient(135deg, rgba(30,64,175,0.2) 0%, rgba(37,99,235,0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center" style="width:3rem; height:3rem; background: linear-gradient(135deg, #1e3a8a, #1e40af); border-radius: 0.875rem;">
                        <i class="fas fa-user-tie fa-lg text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color: #ffffff; font-weight: 700; font-size:1.2rem;">Listado Total Salario Devengado por Trabajador</h5>
                        <p class="small mb-0" style="color: rgba(255,255,255,0.6);">Busque un trabajador y seleccione el mes o "Todos" para sumar el total del año por tipo de nómina</p>
                    </div>
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-times"></i></button>
            </div>

            <div class="modal-body px-4 py-4">
                <!-- Controles -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label mb-1" style="color: rgba(255,255,255,0.85); font-size:0.8rem;">
                            <i class="fas fa-user me-1 text-info"></i> Nombre del Trabajador
                        </label>
                        <div class="buscador-trabajador-modal">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="background: rgba(96, 165, 250, 0.15); border: 0.0625rem solid rgba(96, 165, 250, 0.3); color: #60a5fa;">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="listadoBuscarTrabajador" class="form-control" placeholder="Buscar por nombre, código o CI..." autocomplete="off" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                                <button class="btn btn-sm" type="button" id="listadoLimpiarTrabajador" data-tooltip="Limpiar búsqueda" data-tooltip-theme="danger" style="background: rgba(239,68,68,0.2); border: 0.0625rem solid rgba(239,68,68,0.3); color: #fca5a5;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="listadoResultadosBusqueda" class="dropdown-menu" style="width:100%; max-height:15.625rem; overflow-y: auto; display: none; background: #1e1e2e; border: 0.0625rem solid rgba(96,165,250,0.3); z-index: 1100;">
                            </div>
                        </div>
                        <input type="hidden" id="listadoTrabajadorId" value="">
                        <div id="listadoTrabajadorInfo" class="mt-2 small text-white-50"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="color: rgba(255,255,255,0.85); font-size:0.8rem;">
                            <i class="fas fa-calendar-alt me-1 text-info"></i> Año
                        </label>
                        <select id="listadoAnio" class="form-select form-select-sm" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                            <option value="">-- Años --</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="color: rgba(255,255,255,0.85); font-size:0.8rem;">
                            <i class="fas fa-circle-info me-1 text-info"></i> Estado
                        </label>
                        <select id="listadoEstado" class="form-select form-select-sm" title="Estado de nómina" data-tooltip="Estado de nómina" data-tooltip-theme="secondary" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                            <option value="">Todos</option>
                            <option value="borrador">Borrador</option>
                            <option value="contabilizado">Contabilizado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="color: rgba(255,255,255,0.85); font-size:0.8rem;">
                            <i class="fas fa-layer-group me-1 text-info"></i> Modo
                        </label>
                        <select id="listadoModo" class="form-select form-select-sm" title="Modo de reporte" data-tooltip="Modo de reporte" data-tooltip-theme="secondary" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                            <option value="consolidado">Consolidado</option>
                            <option value="completo">Completo (por mes)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" style="color: rgba(255,255,255,0.85); font-size:0.8rem;">
                            <i class="fas fa-calendar-day me-1 text-info"></i> Mes
                        </label>
                        <select id="listadoMes" class="form-select form-select-sm" disabled title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary" style="background: rgba(20,20,30,0.9); border: 0.0625rem solid rgba(96,165,250,0.3); color: white;">
                            <option value="">-- Mes --</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn-fluent w-100" id="btnGenerarListadoDevengado" title="Generar reporte de devengado" data-tooltip="Generar reporte de devengado" data-tooltip-theme="info">
                            <i class="fas fa-chart-bar"></i>
                            <span>Generar Reporte</span>
                        </button>
                    </div>
                </div>

                <!-- Área de resultados -->
                <div id="listadoResultadoArea" style="display: none;">
                    <div id="printAreaListadoDevengado">
                        <div class="text-center mb-3">
                            <h4 class="text-white mb-1">LISTADO TOTAL SALARIO DEVENGADO</h4>
                            <p class="text-white-50 small mb-1">Empresa: <span id="listadoEmpresaNombre"></span></p>
                            <div class="d-flex justify-content-center gap-4 small flex-wrap">
                                <span style="color: #60a5fa;"><strong>MES:</strong> <span id="listadoMesTexto"></span></span>
                                <span style="color: var(--color-success);"><strong>NOMBRE DEL TRABAJADOR:</strong> <span id="listadoNombreTexto"></span></span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover border-secondary" id="tablaListadoDevengado">
                                <thead class="sticky-top" style="background: var(--panel);">
                                    <tr class="text-info">
                                        <th>TIP. NOMINA</th>
                                        <th style="text-align:center;">CANT. NOM</th>
                                        <th class="text-end">DEVENGADO</th>
                                        <th class="text-end">DEDUCCIONES</th>
                                        <th class="text-end">CESS</th>
                                        <th class="text-end">SAL. NETO</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr style="background: rgba(var(--color-success-rgb),0.1); font-weight: bold;">
                                        <th class="text-white">TOTAL GENERAL</th>
                                        <th class="text-success" id="listadoTotalCantidad" style="text-align:center;">0</th>
                                        <th class="text-success" id="listadoTotalDevengado" style="text-align:right;">$0.00</th>
                                        <th class="text-success" id="listadoTotalDeducciones" style="text-align:right;">$0.00</th>
                                        <th class="text-success" id="listadoTotalCess" style="text-align:right;">$0.00</th>
                                        <th class="text-success" id="listadoTotalNeto" style="text-align:right;">$0.00</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer px-4 py-3" style="border-top: 0.0625rem solid rgba(255,255,255,0.08);">
                <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-2"></i>Cerrar
                </button>
                <button type="button" class="btn-win-primary" id="btnImprimirListadoDevengado" title="Imprimir reporte de devengado" data-tooltip="Imprimir reporte de devengado" data-tooltip-theme="info">
                    <i class="fas fa-print me-2"></i> Imprimir Reporte
                </button>
                <div class="dropdown">
                    <button class="btn-win-success dropdown-toggle" type="button" data-bs-toggle="dropdown" id="btnExportarListadoDevengado" title="Exportar reporte de devengado" data-tooltip="Exportar reporte de devengado" data-tooltip-theme="info">
                        <i class="fas fa-file-export me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" data-export-listado="xls" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="info"><i class="fas fa-file-excel text-success me-2"></i> Exportar a Excel (.xls)</a></li>
                        <li><a class="dropdown-item" href="#" data-export-listado="docx" title="Exportar a Word" data-tooltip="Exportar a Word" data-tooltip-theme="info"><i class="fas fa-file-word text-primary me-2"></i> Exportar a Word (.doc)</a></li>
                        <li><a class="dropdown-item" href="#" data-export-listado="pdf" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="info"><i class="fas fa-file-pdf text-danger me-2"></i> Exportar a PDF</a></li>
                        <li><a class="dropdown-item" href="#" data-export-listado="txt" title="Exportar a TXT" data-tooltip="Exportar a TXT" data-tooltip-theme="info"><i class="fas fa-file-alt text-secondary me-2"></i> Exportar a TXT</a></li>
                        <li><a class="dropdown-item" href="#" data-export-listado="csv" title="Exportar a CSV" data-tooltip="Exportar a CSV" data-tooltip-theme="info"><i class="fas fa-file-csv text-info me-2"></i> Exportar a CSV</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('abrir_descuento') !== '1') return;
    var hayClasificadoresVacios = <?php echo $hay_clasificadores_vacios ? 'true' : 'false'; ?>;
    if (hayClasificadoresVacios) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '<i class="fas fa-database me-2" style="color:#f59e0b;"></i>Datos pendientes de registrar',
                html: 'Para el correcto funcionamiento del sistema y la generaci&oacute;n de n&oacute;minas, todos los clasificadores deben tener datos.',
                background: '#1F1F1F',
                color: '#FFFFFF',
                confirmButtonColor: '#f59e0b',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                allowOutsideClick: false
            });
        }
        return;
    }
    var tipo = window.tipoNomina;
    if (tipo === 'automatica') {
        var btnAuto = document.getElementById('btnGenerarAutomaticaModal');
        if (btnAuto) {
            btnAuto.click();
        } else {
            var modalEl = document.getElementById('modalSeleccionDescuento');
            if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        return;
    }
    var btnGen = document.createElement('button');
    btnGen.type = 'button';
    btnGen.setAttribute('data-bs-toggle', 'modal');
    btnGen.setAttribute('data-bs-target', '#modalSeleccionDescuentoGeneral');
    btnGen.setAttribute('data-target-type', tipo);
    btnGen.style.display = 'none';
    document.body.appendChild(btnGen);
    btnGen.click();
});
</script>

<!-- ========================================================== -->
<!-- JAVASCRIPT: LISTADO TOTAL SALARIO DEVENGADO POR TRABAJADOR  -->
<!-- ========================================================== -->
<script>
(function() {
    var MESES_ESP = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var LISTADO_ESTADO_TXT = { '': 'TODOS', 'borrador': 'BORRADOR', 'contabilizado': 'CONTABILIZADO' };

    var datosActual = null;
    var trabajadorActual = null;
    var anioActual = 0;
    var mesActual = 0;
    var mesTextoActual = '';

    function listadoEscapeHtml(text) {
        return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function listadoFormatoNumero(valor) {
        return parseFloat(valor || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function listadoMoneda(valor) {
        return '$' + listadoFormatoNumero(valor);
    }

    function listadoTipoNominaLabel(tipo) {
        var map = {
            'automatica': 'AUTOMÁTICA',
            'extraordinaria': 'HORAS EXTRAS Y NOCTURNIDAD',
            'vacaciones': 'VACACIONES',
            'bono': 'BONO (RENDIMIENTO)',
            'ajuste': 'NÓMINA DE AJUSTES'
        };
        return map[tipo] || String(tipo || '').toUpperCase();
    }

    function listadoTipoNominaBadge(tipo) {
        var map = {
            'automatica':     { label: 'AUTOMÁTICA', bg: 'rgba(96,165,250,0.18)', border: '#60a5fa', color: '#93c5fd' },
            'extraordinaria': { label: 'HORAS EXTRAS Y NOCTURNIDAD', bg: 'rgba(245,158,11,0.18)', border: '#f59e0b', color: '#fcd34d' },
            'vacaciones':     { label: 'VACACIONES', bg: 'rgba(139,92,246,0.18)', border: '#8b5cf6', color: '#c4b5fd' },
            'bono':           { label: 'BONO (RENDIMIENTO)', bg: 'rgba(16,185,129,0.18)', border: '#10b981', color: '#6ee7b7' },
            'ajuste':         { label: 'NÓMINA DE AJUSTES', bg: 'rgba(6,182,212,0.18)', border: '#06b6d4', color: '#67e8f9' }
        };
        var c = map[tipo] || { label: String(tipo || '').toUpperCase(), bg: 'rgba(148,163,184,0.18)', border: '#94a3b8', color: '#cbd5e1' };
        return '<span class="badge" style="background:' + c.bg + '; border:0.0625rem solid ' + c.border + '; color:' + c.color + '; padding:0.25rem 0.625rem; border-radius:0.75rem; font-size:0.72rem; font-weight:600; white-space:normal;">' + listadoEscapeHtml(c.label) + '</span>';
    }

    function listadoSwalError(mensaje) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Atención',
            text: mensaje,
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
            confirmButtonColor: '#f59e0b',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }

    // ----------------------------------------------------------
    // Búsqueda de trabajador
    // ----------------------------------------------------------
    function listadoBuscar(texto) {
        var term = (texto || '').toLowerCase().trim();
        var cont = $('#listadoResultadosBusqueda');
        if (term.length < 2) { cont.hide().empty(); return; }

        var matches = [];
        $.each(window.trabajadoresTodos || [], function(i, t) {
            var hayNombre = (t.nombre_completo || '').toLowerCase().indexOf(term) !== -1;
            var hayCodigo = (t.codigo || '').toLowerCase().indexOf(term) !== -1;
            var hayCI = (t.ci || '').toLowerCase().indexOf(term) !== -1;
            if (hayNombre || hayCodigo || hayCI) matches.push(t);
        });

        if (!matches.length) {
            cont.html('<div class="dropdown-item text-muted">Sin resultados</div>').show();
            return;
        }

        var html = '';
        matches.slice(0, 40).forEach(function(t) {
            var nombreLimpio = listadoEscapeHtml(t.nombre_completo);
            html += '<a class="dropdown-item listado-opt-trabajador" href="#" data-id="' + t.id + '">'
                + '<i class="fas fa-user me-2 text-info"></i>' + nombreLimpio
                + ' <small class="text-white-50">(' + listadoEscapeHtml(t.codigo || 'S/C') + ' · ' + listadoEscapeHtml(t.ci || 'S/CI') + ')</small>'
                + '</a>';
        });
        cont.html(html).show();
    }

    function listadoSeleccionarTrabajador(t) {
        $('#listadoTrabajadorId').val(t.id);
        $('#listadoBuscarTrabajador').val(t.nombre_completo);
        $('#listadoResultadosBusqueda').hide().empty();
        $('#listadoTrabajadorInfo').html('<i class="fas fa-check-circle text-success me-1"></i>Seleccionado: <strong>' + listadoEscapeHtml(t.nombre_completo) + '</strong> <small class="text-white-50">(' + listadoEscapeHtml(t.codigo || 'S/C') + ' · ' + listadoEscapeHtml(t.ci || 'S/CI') + ')</small>');
        listadoCargarMeses();
    }

    // ----------------------------------------------------------
    // Carga de años y meses
    // ----------------------------------------------------------
    function listadoCargarAnios() {
        var select = $('#listadoAnio');
        var html = '<option value="">-- Años --</option>';
        $.each(window.aniosDisponibles || [], function(i, a) {
            html += '<option value="' + a + '">' + a + '</option>';
        });
        select.html(html);
        var anioActualPagina = parseInt(anioGlobal) || 0;
        if (anioActualPagina) {
            select.val(anioActualPagina);
            listadoCargarMeses();
        }
    }

    function listadoToggleBotones(habilitar) {
        $('#btnGenerarListadoDevengado').prop('disabled', !habilitar).css('opacity', habilitar ? 1 : 0.45);
        $('#btnImprimirListadoDevengado').prop('disabled', !habilitar).css('opacity', habilitar ? 1 : 0.45);
        $('#btnExportarListadoDevengado').prop('disabled', !habilitar).css('opacity', habilitar ? 1 : 0.45);
    }

    function listadoCargarMeses() {
        var anio = parseInt($('#listadoAnio').val()) || 0;
        var trabajadorId = parseInt($('#listadoTrabajadorId').val()) || 0;
        var estado = $('#listadoEstado').val() || '';
        var select = $('#listadoMes');

        select.prop('disabled', true).html('<option value="">-- Mes --</option>');
        listadoToggleBotones(false);
        if (!anio) return;

        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: { action: 'get_meses_listado', ajax: 1, anio: anio, trabajador_id: trabajadorId, estado: estado },
            dataType: 'json',
            success: function(r) {
                if (r.success && r.meses.length) {
                    var html = '<option value="">-- Mes --</option>';
                    html += '<option value="0">Todos</option>';
                    $.each(r.meses, function(i, m) {
                        html += '<option value="' + m + '">' + MESES_ESP[m] + '</option>';
                    });
                    select.html(html).prop('disabled', false);
                    listadoToggleBotones(true);
                    if (parseInt(mesGlobal) && $('#listadoMes option[value="' + mesGlobal + '"]').length) {
                        select.val(mesGlobal);
                    }
                } else {
                    select.html('<option value="">(Sin datos)</option>');
                    listadoToggleBotones(false);
                }
            }
        });
    }

    // ----------------------------------------------------------
    // Generar y renderizar reporte
    // ----------------------------------------------------------
    function listadoGenerarReporte() {
        var trabajadorId = parseInt($('#listadoTrabajadorId').val()) || 0;
        var anio = parseInt($('#listadoAnio').val()) || 0;
        var mesVal = $('#listadoMes').val();
        var mes = mesVal === '' ? 0 : parseInt(mesVal) || 0;
        var estado = $('#listadoEstado').val() || '';

        if (!trabajadorId) { listadoSwalError('Debe buscar y seleccionar un trabajador.'); return; }
        if (!anio || mesVal === '') { listadoSwalError('Debe seleccionar el año y el mes que contengan datos.'); return; }

        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin me-2"></i> Consultando...',
            text: 'Buscando nóminas del trabajador en el período seleccionado.',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); },
            background: '#1a1a2e',
            color: '#ffffff'
        });

        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: {
                action: 'get_listado_devengado_trabajador',
                ajax: 1,
                trabajador_id: trabajadorId,
                anio: anio,
                mes: mes,
                estado: estado,
                modo: $('#listadoModo').val() || 'consolidado'
            },
            dataType: 'json',
            success: function(r) {
                Swal.close();
                if (!r.success) {
                    Swal.fire({
                        title: 'Error',
                        text: r.error || 'No se pudo obtener el reporte.',
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                    return;
                }
                datosActual = r;
                trabajadorActual = r.trabajador;
                anioActual = anio;
                mesActual = mes;
                mesTextoActual = (mes === 0) ? 'TODOS LOS MESES - ' + anio : MESES_ESP[mes] + ' ' + anio;
                listadoRender();
            },
            error: function() {
                Swal.close();
                listadoSwalError('Error de conexión al intentar obtener el reporte.');
            }
        });
    }

    function listadoRender() {
        if (!datosActual) return;
        var esCompleto = (datosActual.modo === 'completo');
        $('#listadoEmpresaNombre').text(nombreEmpresa);
        $('#listadoMesTexto').text(mesTextoActual);
        $('#listadoNombreTexto').text(trabajadorActual.nombre_completo);

        var thead = $('#tablaListadoDevengado thead tr').empty();
        if (esCompleto) {
            thead.append('<th>TIP. NOMINA</th><th style="text-align:right;">DEVENGADO</th><th style="text-align:right;">DEDUCCIONES</th><th style="text-align:right;">CESS</th><th style="text-align:right;">SAL. NETO</th>');
        } else {
            thead.append('<th>TIP. NOMINA</th><th style="text-align:center;">CANT. NOM</th><th style="text-align:right;">DEVENGADO</th><th style="text-align:right;">DEDUCCIONES</th><th style="text-align:right;">CESS</th><th style="text-align:right;">SAL. NETO</th>');
        }

        var tbody = $('#tablaListadoDevengado tbody').empty();
        if (!datosActual.filas.length) {
            tbody.html('<tr><td colspan="6" class="text-center text-white-50 py-3"><i class="fas fa-info-circle me-2"></i>No hay nomimas registradas para este trabajador en el perido seleccionado</td></tr>');
        } else if (esCompleto) {
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    tbody.append(
                        '<tr style="background: rgba(30,64,175,0.15); font-weight: bold;">'
                        + '<td colspan="5" class="text-info">' + listadoTipoNominaBadge(f.tipo_nomina) + '</td>'
                        + '</tr>'
                    );
                }
                tbody.append(
                    '<tr>'
                    + '<td class="ps-4" style="font-style: italic; color: rgba(255,255,255,0.7);">' + MESES_ESP[f.mes_num] + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.devengado) + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.deducciones) + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.cess) + '</td>'
                    + '<td class="fw-bold text-success" style="text-align:right;">' + listadoMoneda(f.neto) + '</td>'
                    + '</tr>'
                );
            });
        } else {
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    tbody.append(
                        '<tr style="background: rgba(30,64,175,0.15); font-weight: bold;">'
                        + '<td colspan="6" class="text-info">' + listadoTipoNominaBadge(f.tipo_nomina) + '</td>'
                        + '</tr>'
                    );
                }
                tbody.append(
                    '<tr>'
                    + '<td></td>'
                    + '<td style="text-align:center;">' + f.cantidad + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.devengado) + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.deducciones) + '</td>'
                    + '<td style="text-align:right;">' + listadoMoneda(f.cess) + '</td>'
                    + '<td class="fw-bold text-success" style="text-align:right;">' + listadoMoneda(f.neto) + '</td>'
                    + '</tr>'
                );
            });
        }
        if (esCompleto) {
            $('#listadoTotalCantidad').hide();
        } else {
            $('#listadoTotalCantidad').show();
        }
        $('#listadoTotalCantidad').text(datosActual.totales.cantidad);
        $('#listadoTotalDevengado').text(listadoMoneda(datosActual.totales.devengado));
        $('#listadoTotalDeducciones').text(listadoMoneda(datosActual.totales.deducciones));
        $('#listadoTotalCess').text(listadoMoneda(datosActual.totales.cess));
        $('#listadoTotalNeto').text(listadoMoneda(datosActual.totales.neto));
        $('#listadoResultadoArea').show();
    }

    // ----------------------------------------------------------
    // HTML de la tabla (sin estilos oscuros) para impresión/exportación
    // ----------------------------------------------------------
    function listadoTablaHtml() {
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var filas = '';
        if (esCompleto) {
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas += '<tr style="background:#e8edf3;"><td colspan="' + numCols + '"><b>' + listadoEscapeHtml(listadoTipoNominaLabel(f.tipo_nomina)) + '</b></td></tr>';
                }
                filas += '<tr>'
                    + '<td style="padding-left:1.25rem; font-style:italic;">' + listadoEscapeHtml(MESES_ESP[f.mes_num]) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.devengado) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.deducciones) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.cess) + '</td>'
                    + '<td><b>' + listadoFormatoNumero(f.neto) + '</b></td>'
                    + '</tr>';
            });
        } else {
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas += '<tr style="background:#e8edf3;"><td colspan="' + numCols + '"><b>' + listadoEscapeHtml(listadoTipoNominaLabel(f.tipo_nomina)) + '</b></td></tr>';
                }
                filas += '<tr>'
                    + '<td></td>'
                    + '<td class="text-center-col">' + f.cantidad + '</td>'
                    + '<td>' + listadoFormatoNumero(f.devengado) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.deducciones) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.cess) + '</td>'
                    + '<td><b>' + listadoFormatoNumero(f.neto) + '</b></td>'
                    + '</tr>';
            });
        }
        if (!datosActual.filas.length) {
            filas = '<tr><td colspan="' + numCols + '" style="text-align:center;">No hay nóminas registradas para este trabajador en el período seleccionado.</td></tr>';
        }
        var t = datosActual.totales;
        var theadHtml;
        if (esCompleto) {
            theadHtml = '<thead><tr><th>TIP. NÓMINA</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr></thead>';
        } else {
            theadHtml = '<thead><tr><th>TIP. NÓMINA</th><th class="text-center-col">CANT. NOM</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr></thead>';
        }
        return theadHtml
            + '<tbody>' + filas + '</tbody>'
            + '<tfoot><tr class="total-row">'
            + '<td><b>TOTAL GENERAL</b></td>'
            + (esCompleto ? '' : '<td class="text-center-col"><b>' + t.cantidad + '</b></td>')
            + '<td><b>' + listadoFormatoNumero(t.devengado) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.deducciones) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.cess) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.neto) + '</b></td>'
            + '</tr></tfoot>';
    }

    // ----------------------------------------------------------
    // Imprimir reporte
    // ----------------------------------------------------------
    function listadoImprimir() {
        if (!datosActual) { listadoSwalError('Primero genere el reporte.'); return; }
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var estadoTxt = LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS';
        var tablaHtml = listadoTablaHtml();

        var win = window.open('', '_blank');
        if (!win) { listadoSwalError('No se pudo abrir la ventana de impresión.'); return; }
        win.document.write(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            + '<title>Listado Total Salario Devengado</title>'
            + '<style>'
            + '@page { size: portrait; margin:12mm; }'
            + 'body { font-family: Arial, sans-serif; font-size:10pt; color: #000; margin:0; padding:0; }'
            + '.header-container { width:100%; border: 0.0625rem solid #000; border-collapse: collapse; margin-bottom:1.125rem; }'
            + '.header-container td { border: 0.0625rem solid #000; padding:0.5rem; vertical-align: middle; }'
            + '.logo-cell { width:3.75rem; text-align: center; }'
            + '.title-cell { text-align: center; font-weight: bold; font-size:12pt; }'
            + '.meta-cell { font-size:8pt; width:11.875rem; }'
            + '.main-table { border-collapse: collapse; margin-top:0.5rem; table-layout: auto; width:auto; margin-left:auto; margin-right:auto; }'
            + '.main-table th { background-color: #004B87; color: #ffffff; font-weight: bold; padding:0.5rem; border: 0.0625rem solid #000; text-align: right; -webkit-print-color-adjust: exact; }'
            + '.main-table th:first-child { text-align: left; }'
            + '.main-table td { border: 0.0625rem solid #000; padding:0.375rem 0.625rem; text-align: right; }'
            + '.main-table td:first-child { text-align: left; }'
            + '.text-center-col { text-align: center !important; }'
            + '.text-end { text-align: right; }'
            + '.total-row { background-color: #f0f4f8; font-weight: bold; }'
            + '.total-row td { border-top: 0.125rem solid #000; color: #004B87; }'
            + '.info-line { text-align: center; margin:0.25rem 0; }'
            + '.signature-section { margin-top:3.4375rem; page-break-inside: avoid; }'
            + '.signature-table { width:100%; border: none !important; border-collapse: collapse; }'
            + '.signature-table td { border: none !important; width:25%; text-align: center; padding:0.625rem; font-size:8.5pt; vertical-align: top; }'
            + '.sig-line { border-top: 0.0625rem solid #000; width:90%; margin:2.8125rem auto 0.3125rem auto; }'
            + '.sig-name { font-weight: bold; display: block; }'
            + '.sig-label { color: #444; font-size:7.5pt; }'
            + '.footer-info { margin-top:0.9375rem; font-size:8pt; color: #666; text-align: center; border-top: 0.5pt solid #eee; padding-top:0.3125rem; }'
            + '.filtro-info { background-color: #f8f9fa; font-weight: bold; color: #004B87; padding:0.25rem 0.625rem; border-radius: 0.25rem; display: inline-block; }'
            + '@media print { .no-print { display: none !important; } }'
            + '</style></head><body>' + PRINT_TOOLBAR_HTML
            + '<table class="header-container"><tr>'
            + '<td class="logo-cell">' + (logoBase64 ? '<img src="' + logoBase64 + '" width="50">' : '') + '</td>'
            + '<td class="title-cell">LISTADO TOTAL SALARIO DEVENGADO<br><span style="font-size:10pt;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</span></td>'
            + '<td class="meta-cell"><strong>Emisión:</strong> ' + fechaHora + '<br><strong>REEUP:</strong> ' + listadoEscapeHtml(reeup || '') + '<br><strong>NIT:</strong> ' + listadoEscapeHtml(nitEmpresa || '') + '</td>'
            + '</tr></table>'
            + '<div style="text-align:center; margin-bottom:0.375rem;"><span class="filtro-info">ESTADO: ' + estadoTxt + '</span> &nbsp; <span class="filtro-info">MODO: ' + (datosActual.modo === 'completo' ? 'COMPLETO (POR MES)' : 'CONSOLIDADO') + '</span></div>'
            + '<div class="info-line"><strong>MES:</strong> ' + listadoEscapeHtml(mesTextoActual) + '</div>'
            + '<div class="info-line"><strong>NOMBRE DEL TRABAJADOR:</strong> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</div>'
            + '<table class="main-table">' + tablaHtml + '</table>'
            + '<div class="signature-section"><table class="signature-table"><tr>'
            + '<td><p><b>Elaborado por:</b></p><div class="sig-line"></div><span class="sig-name">' + listadoEscapeHtml(especialistaNominas || '') + '</span><span class="sig-label">Especialista de Nóminas</span></td>'
            + '<td><p><b>Revisado por:</b></p><div class="sig-line"></div><span class="sig-name">' + listadoEscapeHtml(especialistaGestion || '') + '</span><span class="sig-label">Especialista en Gestión Económica</span></td>'
            + '<td><p><b>Aprobado por:</b></p><div class="sig-line"></div><span class="sig-name">' + listadoEscapeHtml(jefeProyecto || '') + '</span><span class="sig-label">Director de Proyecto</span></td>'
            + '<td><p><b>Contabilizado por:</b></p><div class="sig-line"></div><span class="sig-label">Área Contable y Financiera</span></td>'
            + '</tr></table></div>'
            + '<div class="footer-info">Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + listadoEscapeHtml(usuarioNombre || '') + '</div>'
            + '</body></html>'
        );
        win.document.close();
    }

    // ----------------------------------------------------------
    // Descarga de archivos
    // ----------------------------------------------------------
    function listadoDescargar(contenido, nombreArchivo, mime) {
        var blob = new Blob(['\ufeff' + contenido], { type: mime || 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
    }

    function listadoNombreArchivo(extension) {
        var sufijo = anioActual + (mesActual > 0 ? '-' + String(mesActual).padStart(2, '0') : '_TODOS');
        return 'Listado_Salario_Devengado_' + (trabajadorActual ? trabajadorActual.nombre_completo.replace(/[^A-Za-z0-9]+/g, '_') : 'Trabajador') + '_' + sufijo + '.' + extension;
    }

    // ----------------------------------------------------------
    // Exportaciones: XLS / CSV / TXT / DOCX / PDF
    // ----------------------------------------------------------
    function listadoExportarXls() {
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var estadoTxt = LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS';
        var tabla = '<table border="1">';
        tabla += '<tr><th colspan="' + numCols + '" style="text-align:center;font-size:0.875rem;">LISTADO TOTAL SALARIO DEVENGADO</th></tr>'
            + '<tr><td colspan="' + numCols + '" style="text-align:center;font-weight:bold;font-size:0.75rem;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>REEUP:</b> ' + listadoEscapeHtml(reeup || '') + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>NIT:</b> ' + listadoEscapeHtml(nitEmpresa || '') + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>Emisión:</b> ' + fechaHora + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>ESTADO:</b> ' + estadoTxt + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>MES:</b> ' + listadoEscapeHtml(mesTextoActual) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>NOMBRE DEL TRABAJADOR:</b> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</td></tr>';
        if (esCompleto) {
            tabla += '<tr><th>TIP. NÓMINA</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr>';
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    tabla += '<tr><td colspan="' + numCols + '"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                tabla += '<tr><td>' + MESES_ESP[f.mes_num] + '</td><td>' + listadoFormatoNumero(f.devengado) + '</td><td>' + listadoFormatoNumero(f.deducciones) + '</td><td>' + listadoFormatoNumero(f.cess) + '</td><td>' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        } else {
            tabla += '<tr><th>TIP. NÓMINA</th><th style="text-align:center;">CANT. NOM</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr>';
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    tabla += '<tr><td colspan="' + numCols + '"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                tabla += '<tr><td></td><td style="text-align:center;">' + f.cantidad + '</td><td>' + listadoFormatoNumero(f.devengado) + '</td><td>' + listadoFormatoNumero(f.deducciones) + '</td><td>' + listadoFormatoNumero(f.cess) + '</td><td>' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        }
        var t = datosActual.totales;
        tabla += '<tr><td><b>TOTAL GENERAL</b></td>' + (esCompleto ? '' : '<td><b>' + t.cantidad + '</b></td>') + '<td><b>' + listadoFormatoNumero(t.devengado) + '</b></td><td><b>' + listadoFormatoNumero(t.deducciones) + '</b></td><td><b>' + listadoFormatoNumero(t.cess) + '</b></td><td><b>' + listadoFormatoNumero(t.neto) + '</b></td></tr>';
        tabla += '</table>';

        var colspanFirmas = numCols;
        tabla += '<table border="0" cellspacing="0" cellpadding="4" style="width:100%;margin-top:3.4375rem;">'
            + '<tr>'
            + '<td style="text-align:center;width:25%;"><b>Elaborado por:</b><br><br><br><br><b>' + listadoEscapeHtml((especialistaNominas || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista de Nóminas</span></td>'
            + '<td style="text-align:center;width:25%;"><b>Revisado por:</b><br><br><br><br>_____________________________________<br><b>' + listadoEscapeHtml((especialistaGestion || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista en Gestión Económica</span></td>'
            + '<td style="text-align:center;width:25%;"><b>Aprobado por:</b><br><br><br><br>_____________________________________<br><b>' + listadoEscapeHtml((jefeProyecto || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Director de Proyecto</span></td>'
            + '<td style="text-align:center;width:25%;"><b>Contabilizado por:</b><br><br><br><br>_____________________________________<br><span style="font-size:8pt;color:#444;">Área Contable y Financiera</span></td>'
            + '</tr>'
            + '</table>'
            + '<p style="font-size:8pt;color:#666;text-align:center;margin-top:0.9375rem;">Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + listadoEscapeHtml(usuarioNombre || '') + '</p>';

        listadoDescargar(tabla, listadoNombreArchivo('xls'), 'application/vnd.ms-excel;charset=utf-8');
    }

    function listadoExportarCsv() {
        var num = function(v) { return parseFloat(v || 0).toFixed(2); };
        var esCompleto = (datosActual.modo === 'completo');
        var filas = [];
        filas.push('LISTADO TOTAL SALARIO DEVENGADO');
        filas.push('EMPRESA,' + '"' + (nombreEmpresa || '') + '"');
        filas.push('REEUP,' + '"' + (reeup || '') + '"');
        filas.push('NIT,' + '"' + (nitEmpresa || '') + '"');
        filas.push('MES,' + '"' + mesTextoActual + '"');
        filas.push('NOMBRE DEL TRABAJADOR,' + '"' + trabajadorActual.nombre_completo + '"');
        filas.push('ESTADO,' + '"' + (LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS') + '"');
        filas.push('');
        if (esCompleto) {
            filas.push('TIP. NÓMINA,DEVENGADO,DEDUCCIONES,CESS,SAL. NETO');
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas.push('"' + listadoTipoNominaLabel(f.tipo_nomina) + '",,,,');
                }
                filas.push('"' + MESES_ESP[f.mes_num] + '",' + num(f.devengado) + ',' + num(f.deducciones) + ',' + num(f.cess) + ',' + num(f.neto));
            });
        } else {
            filas.push('TIP. NÓMINA,CANT. NOM,DEVENGADO,DEDUCCIONES,CESS,SAL. NETO');
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas.push('"' + listadoTipoNominaLabel(f.tipo_nomina) + '",,,,');
                }
                filas.push('",' + f.cantidad + ',' + num(f.devengado) + ',' + num(f.deducciones) + ',' + num(f.cess) + ',' + num(f.neto));
            });
        }
        var t = datosActual.totales;
        if (esCompleto) {
            filas.push('"TOTAL GENERAL",' + num(t.devengado) + ',' + num(t.deducciones) + ',' + num(t.cess) + ',' + num(t.neto));
        } else {
            filas.push('"TOTAL GENERAL",' + t.cantidad + ',' + num(t.devengado) + ',' + num(t.deducciones) + ',' + num(t.cess) + ',' + num(t.neto));
        }
        listadoDescargar(filas.join('\r\n'), listadoNombreArchivo('csv'), 'text/csv;charset=utf-8');
    }

    function listadoExportarTxt() {
        function padRight(s, n) { s = String(s || ''); return s.length >= n ? s : s + ' '.repeat(n - s.length); }
        function padLeft(s, n) { s = String(s || ''); return s.length >= n ? s : ' '.repeat(n - s.length) + s; }
        function padCenter(s, n) { s = String(s || ''); if (s.length >= n) return s; var left = Math.floor((n - s.length) / 2); var right = n - s.length - left; return ' '.repeat(left) + s + ' '.repeat(right); }
        var esCompleto = (datosActual.modo === 'completo');
        var lineas = [];
        lineas.push('========================================================================');
        lineas.push('                LISTADO TOTAL SALARIO DEVENGADO');
        lineas.push('========================================================================');
        lineas.push('Empresa: ' + nombreEmpresa);
        lineas.push('MES: ' + mesTextoActual);
        lineas.push('NOMBRE DEL TRABAJADOR: ' + trabajadorActual.nombre_completo);
        lineas.push('ESTADO: ' + (LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS'));
        lineas.push('------------------------------------------------------------------------');
        if (esCompleto) {
            lineas.push(padRight('TIP. NÓMINA', 28) + padLeft('DEVENGADO', 14) + padLeft('DEDUCCIONES', 14) + padLeft('CESS', 12) + padLeft('SAL. NETO', 14));
            lineas.push('------------------------------------------------------------------------');
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    lineas.push(padRight('--- ' + listadoTipoNominaLabel(f.tipo_nomina) + ' ---', 72));
                }
                lineas.push(padRight(MESES_ESP[f.mes_num], 28) + padLeft(listadoFormatoNumero(f.devengado), 14) + padLeft(listadoFormatoNumero(f.deducciones), 14) + padLeft(listadoFormatoNumero(f.cess), 12) + padLeft(listadoFormatoNumero(f.neto), 14));
            });
        } else {
            lineas.push(padRight('TIP. NÓMINA', 28) + padCenter('CANT', 8) + padLeft('DEVENGADO', 14) + padLeft('DEDUCCIONES', 14) + padLeft('CESS', 12) + padLeft('SAL. NETO', 14));
            lineas.push('------------------------------------------------------------------------');
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    lineas.push(padRight('--- ' + listadoTipoNominaLabel(f.tipo_nomina) + ' ---', 82));
                }
                lineas.push(padRight('', 28) + padCenter(f.cantidad, 8) + padLeft(listadoFormatoNumero(f.devengado), 14) + padLeft(listadoFormatoNumero(f.deducciones), 14) + padLeft(listadoFormatoNumero(f.cess), 12) + padLeft(listadoFormatoNumero(f.neto), 14));
            });
        }
        lineas.push('------------------------------------------------------------------------');
        var t = datosActual.totales;
        if (esCompleto) {
            lineas.push(padRight('TOTAL GENERAL', 28) + padLeft(listadoFormatoNumero(t.devengado), 14) + padLeft(listadoFormatoNumero(t.deducciones), 14) + padLeft(listadoFormatoNumero(t.cess), 12) + padLeft(listadoFormatoNumero(t.neto), 14));
        } else {
            lineas.push(padRight('TOTAL GENERAL', 28) + padCenter(t.cantidad, 8) + padLeft(listadoFormatoNumero(t.devengado), 14) + padLeft(listadoFormatoNumero(t.deducciones), 14) + padLeft(listadoFormatoNumero(t.cess), 12) + padLeft(listadoFormatoNumero(t.neto), 14));
        }
        lineas.push('========================================================================');
        listadoDescargar(lineas.join('\r\n'), listadoNombreArchivo('txt'), 'text/plain;charset=utf-8');
    }

    function listadoExportarDocx() {
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var estadoTxt = LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS';
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;

        var contenido = '<div class="WordSection1">'
            + '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;">'
            + '<tr>'
            + '<td style="width:3.75rem;text-align:center;">' + (logoBase64 ? '<img src="' + logoBase64 + '" width="34" height="41" style="width:0.9cm;height:1.08cm;">' : '') + '</td>'
            + '<td style="text-align:center;font-weight:bold;font-size:0.875rem;">LISTADO TOTAL SALARIO DEVENGADO<br><span style="font-size:0.6875rem;font-weight:normal;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</span></td>'
            + '<td style="width:11.875rem;font-size:8pt;"><b>Emisión:</b> ' + fechaHora + '<br><b>REEUP:</b> ' + listadoEscapeHtml(reeup || '') + '<br><b>NIT:</b> ' + listadoEscapeHtml(nitEmpresa || '') + '</td>'
            + '</tr>'
            + '</table>'
            + '<p style="text-align:center;"><b>ESTADO:</b> ' + estadoTxt + '</p>'
            + '<p><b>MES:</b> ' + listadoEscapeHtml(mesTextoActual) + '</p>'
            + '<p><b>NOMBRE DEL TRABAJADOR:</b> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</p>'
            + '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:auto; margin:0 auto;">';
        if (esCompleto) {
            contenido += '<tr style="background:#004B87;"><th style="color:#fff;">TIP. NOMINA</th><th style="color:#fff;text-align:right;">DEVENGADO</th><th style="color:#fff;text-align:right;">DEDUCCIONES</th><th style="color:#fff;text-align:right;">CESS</th><th style="color:#fff;text-align:right;">SAL. NETO</th></tr>';
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    contenido += '<tr><td colspan="' + numCols + '" style="background:#e8edf3;"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                contenido += '<tr><td style="font-style:italic;">' + MESES_ESP[f.mes_num] + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.devengado) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.deducciones) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.cess) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        } else {
            contenido += '<tr style="background:#004B87;"><th style="color:#fff;">TIP. NOMINA</th><th style="color:#fff;text-align:center;">CANT. NOM</th><th style="color:#fff;text-align:right;">DEVENGADO</th><th style="color:#fff;text-align:right;">DEDUCCIONES</th><th style="color:#fff;text-align:right;">CESS</th><th style="color:#fff;text-align:right;">SAL. NETO</th></tr>';
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    contenido += '<tr><td colspan="' + numCols + '" style="background:#e8edf3;"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                contenido += '<tr><td></td><td style="text-align:center;">' + f.cantidad + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.devengado) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.deducciones) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.cess) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        }
        var t = datosActual.totales;
        contenido += '<tr style="background:#eee;"><th>TOTAL GENERAL</th>' + (esCompleto ? '' : '<th style="text-align:center;">' + t.cantidad + '</th>') + '<th style="text-align:right;">' + listadoFormatoNumero(t.devengado) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.deducciones) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.cess) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.neto) + '</th></tr>';
        contenido += '</table>';

        contenido += '<table border="0" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;margin-top:3.4375rem;">'
            + '<tr>'
            + '<td style="text-align:center;width:25%;"><p><b>Elaborado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><b>' + listadoEscapeHtml((especialistaNominas || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista de Nóminas</span></td>'
            + '<td style="text-align:center;width:25%;"><p><b>Revisado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><b>' + listadoEscapeHtml((especialistaGestion || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista en Gestión Económica</span></td>'
            + '<td style="text-align:center;width:25%;"><p><b>Aprobado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><b>' + listadoEscapeHtml((jefeProyecto || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Director de Proyecto</span></td>'
            + '<td style="text-align:center;width:25%;"><p><b>Contabilizado por:</b></p><p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p><span style="font-size:8pt;color:#444;">Área Contable y Financiera</span></td>'
            + '</tr>'
            + '</table>'
            + '<p style="font-size:8pt;color:#666;text-align:center;margin-top:0.9375rem;">Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + listadoEscapeHtml(usuarioNombre || '') + '</p>'
            + '</div>';

        var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>Listado Total Salario Devengado</title><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]--><style>img { width:0.9cm !important; height:1.08cm !important; }</style></head><body>' + contenido + '</body></html>';
        listadoDescargar(html, listadoNombreArchivo('doc'), 'application/msword;charset=utf-8');
    }

    function listadoExportarPdf() {
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var body = [];
        if (esCompleto) {
            body.push([
                { text: 'TIP. NOMINA', style: 'tableHeader' },
                { text: 'DEVENGADO', style: 'tableHeader', alignment: 'right' },
                { text: 'DEDUCCIONES', style: 'tableHeader', alignment: 'right' },
                { text: 'CESS', style: 'tableHeader', alignment: 'right' },
                { text: 'SAL. NETO', style: 'tableHeader', alignment: 'right' }
            ]);
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    body.push([
                        { text: listadoTipoNominaLabel(f.tipo_nomina), bold: true, colSpan: numCols },
                        {}, {}, {}, {}
                    ]);
                }
                body.push([
                    { text: MESES_ESP[f.mes_num] },
                    { text: listadoFormatoNumero(f.devengado), alignment: 'right' },
                    { text: listadoFormatoNumero(f.deducciones), alignment: 'right' },
                    { text: listadoFormatoNumero(f.cess), alignment: 'right' },
                    { text: listadoFormatoNumero(f.neto), alignment: 'right' }
                ]);
            });
        } else {
            body.push([
                { text: 'TIP. NOMINA', style: 'tableHeader' },
                { text: 'CANT. NOM', style: 'tableHeader', alignment: 'center' },
                { text: 'DEVENGADO', style: 'tableHeader', alignment: 'right' },
                { text: 'DEDUCCIONES', style: 'tableHeader', alignment: 'right' },
                { text: 'CESS', style: 'tableHeader', alignment: 'right' },
                { text: 'SAL. NETO', style: 'tableHeader', alignment: 'right' }
            ]);
            var tipoActual = '';
            $.each(datosActual.filas, function(i, f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    body.push([
                        { text: listadoTipoNominaLabel(f.tipo_nomina), bold: true, colSpan: numCols },
                        {}, {}, {}, {}, {}
                    ]);
                }
                body.push([
                    { text: '' },
                    { text: String(f.cantidad), alignment: 'center' },
                    { text: listadoFormatoNumero(f.devengado), alignment: 'right' },
                    { text: listadoFormatoNumero(f.deducciones), alignment: 'right' },
                    { text: listadoFormatoNumero(f.cess), alignment: 'right' },
                    { text: listadoFormatoNumero(f.neto), alignment: 'right' }
                ]);
            });
        }
        var t = datosActual.totales;
        if (esCompleto) {
            body.push([
                { text: 'TOTAL GENERAL', bold: true, fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.devengado), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.deducciones), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.cess), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.neto), bold: true, alignment: 'right', fillColor: '#f0f4f8' }
            ]);
        } else {
            body.push([
                { text: 'TOTAL GENERAL', bold: true, fillColor: '#f0f4f8' },
                { text: String(t.cantidad), bold: true, alignment: 'center', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.devengado), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.deducciones), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.cess), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.neto), bold: true, alignment: 'right', fillColor: '#f0f4f8' }
            ]);
        }

        var pdfWidths5 = ['*', '*', '*', '*', '*'];
        var pdfWidths6 = ['*', '*', '*', '*', '*', '*'];
        var docDefinition = {
            pageSize: 'LETTER',
            pageOrientation: 'portrait',
            pageMargins: [30, 30, 30, 40],
            content: [
                {
                    table: {
                        widths: [50, '*', 160],
                        body: [
                            [
                                logoBase64 ? { image: logoBase64, width: 40, alignment: 'center' } : { text: '' },
                                {
                                    stack: [
                                        { text: 'LISTADO TOTAL SALARIO DEVENGADO', fontSize: 12, bold: true, alignment: 'center' },
                                        { text: (nombreEmpresa || '').toUpperCase(), fontSize: 9, alignment: 'center', margin: [0, 2, 0, 0] }
                                    ]
                                },
                                {
                                    stack: [
                                        { text: 'Emisión: ' + new Date().toLocaleDateString('es-ES') + ' - ' + new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }), fontSize: 8, alignment: 'right' },
                                        { text: 'REEUP: ' + (reeup || ''), fontSize: 8, alignment: 'right' },
                                        { text: 'NIT: ' + (nitEmpresa || ''), fontSize: 8, alignment: 'right' }
                                    ]
                                }
                            ]
                        ]
                    },
                    layout: 'noBorders',
                    margin: [0, 0, 0, 10]
                },
                { text: 'MES: ' + mesTextoActual, fontSize: 10, bold: true, margin: [0, 4, 0, 2] },
                { text: 'NOMBRE DEL TRABAJADOR: ' + trabajadorActual.nombre_completo, fontSize: 10, bold: true, margin: [0, 0, 0, 2] },
                { text: 'ESTADO: ' + (LISTADO_ESTADO_TXT[$('#listadoEstado').val() || ''] || 'TODOS'), fontSize: 9, margin: [0, 0, 0, 8] },
                {
                    columns: [
                        { width: '*', text: '' },
                        {
                            width: 'auto',
                            table: {
                                headerRows: 1,
                                widths: esCompleto ? pdfWidths5 : pdfWidths6,
                                body: body
                            },
                            layout: {
                                fillColor: function(rowIndex) { return rowIndex === 0 ? '#004B87' : null; }
                            }
                        },
                        { width: '*', text: '' }
                    ]
                },
                { text: '', margin: [0, 22, 0, 0] },
                {
                    columns: [
                        {
                            width: '*',
                            stack: [
                                { text: 'Elaborado por:', bold: true, fontSize: 9 },
                                { text: (especialistaNominas || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 30, 0, 0] },
                                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                { text: 'Especialista de Nóminas', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                            ]
                        },
                        {
                            width: '*',
                            stack: [
                                { text: 'Revisado por:', bold: true, fontSize: 9 },
                                { text: (especialistaGestion || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 30, 0, 0] },
                                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                { text: 'Especialista en Gestión Económica', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                            ]
                        },
                        {
                            width: '*',
                            stack: [
                                { text: 'Aprobado por:', bold: true, fontSize: 9 },
                                { text: (jefeProyecto || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 30, 0, 0] },
                                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                { text: 'Director de Proyecto', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                            ]
                        },
                        {
                            width: '*',
                            stack: [
                                { text: 'Contabilizado por:', bold: true, fontSize: 9 },
                                { text: '', margin: [0, 30, 0, 0] },
                                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] },
                                { text: 'Área Contable y Financiera', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] }
                            ]
                        }
                    ]
                }
            ],
            styles: {
                tableHeader: { color: '#ffffff', bold: true }
            },
            footer: function(currentPage, pageCount) {
                return { text: 'Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + (usuarioNombre || '') + '   Página ' + currentPage + ' de ' + pageCount, fontSize: 7, color: '#666', alignment: 'center', margin: [0, 10, 0, 0] };
            }
        };

        pdfMake.createPdf(docDefinition).download(listadoNombreArchivo('pdf'));
    }

    function listadoExportar(tipo) {
        if (!datosActual) { listadoSwalError('Primero genere el reporte.'); return; }
        if (tipo === 'xls') listadoExportarXls();
        else if (tipo === 'csv') listadoExportarCsv();
        else if (tipo === 'txt') listadoExportarTxt();
        else if (tipo === 'docx') listadoExportarDocx();
        else if (tipo === 'pdf') listadoExportarPdf();
    }

    // ----------------------------------------------------------
    // Eventos
    // ----------------------------------------------------------
    function listadoInit() {
        if (!$('#modalListadoDevengadoTrabajador').length) return;

        $('#btnListadoDevengado').on('click', function(e) {
            e.preventDefault();
            var modalSel = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSeleccionResumenSalarial'));
            modalSel.show();
        });

        $('#opcionResumenPorTrabajador').on('click', function() {
            var sel = bootstrap.Modal.getInstance(document.getElementById('modalSeleccionResumenSalarial'));
            if (sel) sel.hide();
            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalListadoDevengadoTrabajador'));
            if (!$('#listadoAnio option').length || $('#listadoAnio option').length <= 1) {
                listadoCargarAnios();
            }
            modal.show();
        });

        $('#listadoBuscarTrabajador').on('input', function() { listadoBuscar($(this).val()); });

        $('#listadoLimpiarTrabajador').on('click', function() {
            $('#listadoTrabajadorId').val('');
            $('#listadoBuscarTrabajador').val('');
            $('#listadoResultadosBusqueda').hide().empty();
            $('#listadoTrabajadorInfo').empty();
            $('#listadoResultadoArea').hide();
            listadoCargarMeses();
        });

        $(document).on('click', '.listado-opt-trabajador', function(e) {
            e.preventDefault();
            var id = parseInt($(this).data('id')) || 0;
            var t = null;
            $.each(window.trabajadoresTodos || [], function(i, item) {
                if (parseInt(item.id) === id) { t = item; return false; }
            });
            if (t) listadoSeleccionarTrabajador(t);
        });

        // Cerrar lista de resultados al hacer clic fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.buscador-trabajador-modal').length) {
                $('#listadoResultadosBusqueda').hide().empty();
            }
        });

        $('#listadoAnio').on('change', function() {
            $('#listadoResultadoArea').hide();
            listadoCargarMeses();
        });

        $('#listadoMes').on('change', function() {
            $('#listadoResultadoArea').hide();
        });

        $('#listadoEstado').on('change', function() {
            listadoCargarMeses();
        });

        $('#btnGenerarListadoDevengado').on('click', function(e) {
            e.preventDefault();
            listadoGenerarReporte();
        });

        $('#btnImprimirListadoDevengado').on('click', function(e) {
            e.preventDefault();
            listadoImprimir();
        });

        $(document).on('click', '[data-export-listado]', function(e) {
            e.preventDefault();
            listadoExportar($(this).data('export-listado'));
        });

        $('#modalListadoDevengadoTrabajador').on('shown.bs.modal', function() {
            datosActual = null;
            trabajadorActual = null;
            $('#listadoTrabajadorId').val('');
            $('#listadoBuscarTrabajador').val('');
            $('#listadoResultadosBusqueda').hide().empty();
            $('#listadoTrabajadorInfo').empty();
            $('#listadoResultadoArea').hide();
            $('#listadoEstado').val('');
            $('#listadoModo').val('consolidado');
            if ($('#listadoMes').length) {
                $('#listadoMes').val('').prop('disabled', true).empty().append('<option value="">(Seleccionar mes)</option>');
            }
            listadoCargarAnios();
        });

        $('#modalListadoDevengadoTrabajador').on('hidden.bs.modal', function() {
            datosActual = null;
            trabajadorActual = null;
            $('#listadoTrabajadorId').val('');
            $('#listadoBuscarTrabajador').val('');
            $('#listadoResultadosBusqueda').hide().empty();
            $('#listadoTrabajadorInfo').empty();
            $('#listadoResultadoArea').hide();
            $('#listadoEstado').val('');
            $('#listadoModo').val('consolidado');
            if ($('#listadoMes').length) {
                $('#listadoMes').val('').prop('disabled', true).empty().append('<option value="">(Seleccionar mes)</option>');
            }
        });
    }

    $(listadoInit);
})();
</script>

<!-- Botones flotantes de navegación rápida -->
<style>
.scroll-quick-btns { position: fixed; right:1.25rem; bottom:1.25rem; display: flex; flex-direction: column; gap:0.625rem; z-index: 950; }
.scroll-quick-btn {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; border: 0.0625rem solid rgba(148, 163, 184, .35);
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, .5); transition: all .2s;
}
.scroll-quick-btn:hover { transform: translateY(-0.125rem); filter: brightness(1.15); }
.scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }
@media print { .scroll-quick-btns { display: none !important; } }
</style>
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>
<script>
(function () {
    var btnTop = document.getElementById('btnScrollTop');
    var btnBottom = document.getElementById('btnScrollBottom');
    if (!btnTop || !btnBottom) return;
    function actualizarVisibilidad() {
        var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        var y = window.scrollY || document.documentElement.scrollTop;
        btnTop.classList.toggle('hidden', y < 150);
        btnBottom.classList.toggle('hidden', maxScroll <= 150 || y > maxScroll - 150);
    }
    btnTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    btnBottom.addEventListener('click', function () { window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }); });
    window.addEventListener('scroll', actualizarVisibilidad);
    actualizarVisibilidad();
})();
</script>
<?php if ($hay_clasificadores_vacios): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal === 'undefined') return;
    var nombres = <?php echo json_encode($nombres_clasificadores_vacios); ?>;
    var lista = '<ul style="text-align:left;margin:0.625rem auto 0;display:inline-block;">';
    nombres.forEach(function(n) {
        lista += '<li style="margin:0.25rem 0;"><i class="fas fa-exclamation-triangle me-2" style="color:#f59e0b;"></i>' + n + '</li>';
    });
    lista += '</ul>';
    Swal.fire({
        icon: 'warning',
        title: '<i class="fas fa-database me-2" style="color:#f59e0b;"></i>Datos pendientes de registrar',
        html: 'Para el correcto funcionamiento del sistema y la generaci&oacute;n de n&oacute;minas, todos los clasificadores deben tener datos.<br><br><strong style="color:#fbbf24;">Faltan por registrar:</strong>' + lista,
        background: '#1F1F1F',
        color: '#FFFFFF',
        confirmButtonColor: '#f59e0b',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
        allowOutsideClick: false,
        footer: '<a href="clasificadores.php" style="color:#60a5fa;">Ir a Clasificadores para registrar los datos</a>'
    });
});
</script>
<?php endif; ?>
<script>
// =========================================================
// AVISO DE DESCUADRES EN EL CUADRE DE NÓMINAS CONTABILIZADAS
// SweetAlert adaptado al tema aplicado (oscuro / claro).
// Usa el resultado de verificarCuadreValores() ya calculado
// en $cuadre; solo informa; no modifica datos.
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') return;
    var r = <?php echo json_encode($cuadre, JSON_UNESCAPED_UNICODE); ?>;
    if (!r || !r.filas_con_error || r.filas_con_error <= 0) return;
    var esc = function (t) {
        return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };
    var oscuro = document.documentElement.getAttribute('data-theme') !== 'light';
    var fondo = oscuro ? '#10151f' : '#ffffff';
    var texto = oscuro ? '#e8edf6' : '#1f2937';
    var muted = oscuro ? '#97a5bb' : '#4b5563';
    var rojo = oscuro ? '#f87171' : '#b91c1c';
    var verde = oscuro ? '#34d399' : '#047857';

    var TIPOS = [
        ['automatica', 'fa-calendar-alt', 'Automática'],
        ['extraordinaria', 'fa-clock', 'Extraordinaria'],
        ['vacaciones', 'fa-umbrella-beach', 'Vacaciones'],
        ['bono', 'fa-gift', 'Bono'],
        ['ajuste', 'fa-pen', 'Ajuste']
    ];
    var chips = '';
    for (var i = 0; i < TIPOS.length; i++) {
        var t = TIPOS[i];
        var d = (r.por_tipo && r.por_tipo[t[0]]) ? r.por_tipo[t[0]] : { filas: 0, con_error: 0, errores: 0 };
        var err = parseInt(d.errores || 0, 10);
        var fil = parseInt(d.filas || 0, 10);
        var col = err > 0 ? rojo : verde;
        chips += '<span style="display:inline-flex;align-items:center;gap:0.3125rem;margin:0.125rem 0.25rem 0.125rem 0;padding:0.1875rem 0.5rem;border-radius:0.5rem;font-size:0.72rem;font-weight:600;color:' + col + ';background:' + (err > 0 ? 'rgba(248,113,113,0.12)' : 'rgba(52,211,153,0.12)') + ';border:0.0625rem solid ' + col + '44;">'
            + '<i class="fas ' + t[1] + '"></i>' + t[2] + ': ' + err
            + (fil > 0 ? '<small style="opacity:0.7;">(' + fil + ' fila' + (fil === 1 ? '' : 's') + ')</small>' : '')
            + '</span>';
    }

    var listaErrores = r.errores || [];
    var items = '';
    var primera = listaErrores.slice(0, 8);
    for (var j = 0; j < primera.length; j++) {
        var e = primera[j];
        items += '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.625rem;padding:0.375rem 0.5rem;border-radius:0.5rem;font-size:0.78rem;background:' + (oscuro ? 'rgba(239,68,68,0.07)' : 'rgba(239,68,68,0.05)') + ';">'
            + '<span style="min-width:0;"><strong>' + esc(e.numero || '') + '</strong> <small style="color:' + muted + ';">(' + esc(e.tipo) + ' &middot; ' + esc(e.periodo ? e.periodo.substring(0, 7) : '') + ')</small><br>' + esc(e.trabajador || '') + ' <small style="color:' + muted + ';">&mdash; ' + esc(e.detalle || e.check || '') + '</small></span>'
            + '<span style="text-align:right;flex-shrink:0;font-size:0.7rem;color:' + rojo + ';">' + esc(e.encontrado || '') + ' vs ' + esc(e.esperado || '') + '<br>dif. ' + esc(e.diferencia || '') + '</span>'
            + '</div>';
    }

    var sw = r.errores_total === 1 ? 'descuadre' : 'descuadres';
    Swal.fire({
        icon: 'warning',
        title: '<i class="fas fa-scale-balanced me-2" style="color:' + rojo + ';"></i><span style="color:' + texto + ';animation:cuadrePulse 0.5s ease-in-out infinite;">Cuadre de N\u00f3minas Contabilizadas</span>',
        html:
            '<div style="text-align:left;">' +
                '<p style="margin:0 0 0.375rem;font-size:0.85rem;">Se detectaron <strong style="color:' + rojo + ';">' + r.errores_total + '</strong> ' + sw + ' en <strong>' + r.filas_con_error + '</strong> fila(s): <strong>' + r.errores_impuestos + '</strong> de impuestos y <strong>' + r.errores_aritmetica + '</strong> de aritm\u00e9tica (devengado, deducciones o neto).</p>' +
                '<div style="margin:0.125rem 0 0.25rem;">' + chips + '</div>' +
                items +
                (listaErrores.length < r.errores_total ? '<p style="margin:0.375rem 0 0;font-size:0.75rem;color:' + muted + ';">Mostrando los primeros ' + listaErrores.length + ' de ' + r.errores_total + ' descuadres.</p>' : '') +
                '<p style="margin:0.5rem 0 0;font-size:0.76rem;color:' + muted + ';"><i class="fas fa-info-circle me-1"></i>Puede ver el detalle completo y corregir los valores desde el bot\u00f3n "Verificar Cuadre".</p>' +
            '</div>',
        background: fondo,
        color: texto,
        confirmButtonColor: oscuro ? '#3b82f6' : '#0078d4',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
        showCancelButton: true,
        cancelButtonColor: oscuro ? '#64748b' : '#6b7280',
        cancelButtonText: '<i class="fas fa-clipboard-check me-2"></i>Ver Cuadre',
        allowOutsideClick: false
    }).then(function (res) {
        if (res.dismiss === 'cancel') {
            var b = document.getElementById('btnVerificarCuadre');
            if (b) b.click();
        }
    });
});
</script>
</body>
</html>