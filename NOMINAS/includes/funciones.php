<?php
// includes/functions.php



/**
 * Obtiene todos los centros de costo activos
 * 
 * @param PDO $pdo
 * @return array
 */
function getCentrosCosto($pdo) {
    $stmt = $pdo->query("SELECT id, codigo, nombre FROM centros_costo WHERE activo = 1 ORDER BY codigo");
    return $stmt->fetchAll();
}


/**
 * Calcula el impuesto sobre ingresos personales según los rangos configurados
 * 
 * @param PDO $pdo
 * @param float $ingreso_imponible
 * @return float
 */
function calcularImpuestoPersonal($pdo, $ingreso_imponible) {
    $rangos = getRangosImpuesto($pdo);
    
    foreach ($rangos as $rango) {
        $desde = (float)$rango['desde'];
        $hasta = $rango['hasta'] !== null ? (float)$rango['hasta'] : null;
        $tasa = (float)$rango['tasa'];
        $monto_fijo = (float)$rango['monto_fijo'];
        
        if ($ingreso_imponible >= $desde && ($hasta === null || $ingreso_imponible <= $hasta)) {
            $excedente = $ingreso_imponible - $desde;
            return $monto_fijo + ($excedente * $tasa);
        }
    }
    
    return 0;
}

/**
 * Calcula la contribución especial a la seguridad social
 * 
 * @param PDO $pdo
 * @param float $salario_devengado
 * @return float
 */
function calcularContribucionEspecial($pdo, $salario_devengado) {
    $stmt = $pdo->query("SELECT valor FROM configuracion_tasas WHERE nombre_tasa = 'contribucion_especial' ORDER BY fecha_vigencia DESC LIMIT 1");
    $tasa = $stmt->fetchColumn();
    $tasa = $tasa ? (float)$tasa : 5.0;
    
    return $salario_devengado * ($tasa / 100);
}

/**
 * Obtiene la configuración general
 * 
 * @param PDO $pdo
 * @param string $parametro
 * @return mixed
 */
function getConfiguracion($pdo, $parametro) {
    $stmt = $pdo->prepare("SELECT valor, tipo_dato FROM configuracion_general WHERE parametro = ?");
    $stmt->execute([$parametro]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return null;
    }
    
    $valor = $row['valor'];
    $tipo = $row['tipo_dato'];
    
    switch ($tipo) {
        case 'entero':
            return (int)$valor;
        case 'decimal':
            return (float)$valor;
        case 'booleano':
            return (valor == '1' || valor == 'true' || valor == 'si');
        default:
            return $valor;
    }
}

/**
 * Calcula las vacaciones generadas según la Ley 116
 * Fórmula: Días de vacaciones = Días trabajados ÷ 11
 * Valor a pagar = Salarios devengados ÷ 11
 * 
 * @param float $dias_trabajados
 * @param float $salarios_devengados
 * @return array ['dias' => float, 'valor' => float]
 */
function calcularVacacionesLey116($dias_trabajados, $salarios_devengados) {
    $dias_vacaciones = $dias_trabajados / 11;
    $valor_vacaciones = $salarios_devengados / 11;
    
    return [
        'dias' => round($dias_vacaciones, 2),
        'valor' => round($valor_vacaciones, 2)
    ];
}

/**
 * Obtiene la configuración de vacaciones vigente
 * 
 * @param PDO $pdo
 * @return array
 */
function getConfiguracionVacaciones($pdo) {
    $stmt = $pdo->query("SELECT dias_por_mes, factor_calculo, meses_requeridos FROM configuracion_vacaciones WHERE activo = 1 ORDER BY fecha_vigencia DESC LIMIT 1");
    return $stmt->fetch();
}

/**
 * Convierte un número a su representación en números romanos
 * 
 * @param int $numero
 * @return string
 */
function numeroRomano($numero) {
    if (!is_numeric($numero) || $numero < 1 || $numero > 3999) {
        return "Número inválido";
    }
    
    $romanos = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
        10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
    ];
    
    $resultado = '';
    $valor = (int)$numero;
    
    foreach ($romanos as $valorDecimal => $romano) {
        while ($valor >= $valorDecimal) {
            $resultado .= $romano;
            $valor -= $valorDecimal;
        }
    }
    
    return $resultado;
}

/**
 * Valida el formato del Carnet de Identidad cubano
 * 
 * @param string $ci
 * @return bool
 */
function validarCI($ci) {
    $ci_limpio = preg_replace('/\D/', '', $ci);
    return strlen($ci_limpio) === 11;
}

/**
 * Valida el formato de cuenta bancaria (14 o 16 dígitos)
 * 
 * @param string $cuenta
 * @return bool
 */
function validarCuentaBancaria($cuenta) {
    $cuenta_limpia = preg_replace('/\D/', '', $cuenta);
    $longitud = strlen($cuenta_limpia);
    return $longitud === 0 || $longitud === 14 || $longitud === 16;
}

/**
 * Calcula el salario por hora según el salario mensual y horas mensuales configuradas
 * 
 * @param PDO $pdo
 * @param float $salario_mensual
 * @return float
 */
function calcularSalarioHora($pdo, $salario_mensual) {
    $horas_mensuales = getConfiguracion($pdo, 'horas_mensuales');
    return $salario_mensual / $horas_mensuales;
}

/**
 * Calcula el valor por día según el salario mensual y días mensuales configurados
 * 
 * @param PDO $pdo
 * @param float $salario_mensual
 * @return float
 */
function calcularValorDia($pdo, $salario_mensual) {
    $dias_mensuales = getConfiguracion($pdo, 'dias_mensuales');
    return $salario_mensual / $dias_mensuales;
}

/**
 * Obtiene los motivos de baja activos
 * 
 * @param PDO $pdo
 * @return array
 */
function getMotivosBaja($pdo) {
    $stmt = $pdo->query("SELECT * FROM motivos_baja WHERE activo = 1 ORDER BY codigo");
    return $stmt->fetchAll();
}

/**
 * Obtiene los centros de costo activos (alias de getCentrosCosto para compatibilidad)
 * 
 * @param PDO $pdo
 * @return array
 */
function getCentrosCostoActivos($pdo) {
    return getCentrosCosto($pdo);
}

/**
 * Calcula el total de días trabajados en un período
 * 
 * @param string $fecha_inicio
 * @param string $fecha_fin
 * @param array $dias_no_laborables (opcional, domingos por defecto)
 * @return int
 */
function calcularDiasTrabajados($fecha_inicio, $fecha_fin, $dias_no_laborables = [0]) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day'); // Incluir el día de fin
    
    $dias_trabajados = 0;
    $interval = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $interval, $fin);
    
    foreach ($periodo as $fecha) {
        $dia_semana = $fecha->format('w'); // 0 = domingo, 6 = sábado
        if (!in_array($dia_semana, $dias_no_laborables)) {
            $dias_trabajados++;
        }
    }
    
    return $dias_trabajados;
}

/**
 * Obtiene un trabajador por su ID con todos sus datos relacionados
 * 
 * @param PDO $pdo
 * @param int $id
 * @return array|null
 */
function getTrabajadorCompleto($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT t.*, a.nombre_area, c.nombre as categoria_nombre, c.factor_incidencia,
               e.salario_mensual, e.escala_numero, e.salario_hora_ordinaria,
               cc.nombre as centro_costo_nombre
        FROM trabajadores t
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN categorias_ocupacionales c ON t.categoria_ocupacional_id = c.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        JOIN escalas_salariales e ON t.escala_salarial_id = e.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Obtiene todas las nóminas de un trabajador
 * 
 * @param PDO $pdo
 * @param int $trabajador_id
 * @return array
 */
function getNominasPorTrabajador($pdo, $trabajador_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM nominas 
        WHERE trabajador_id = ? 
        ORDER BY periodo_desde DESC
    ");
    $stmt->execute([$trabajador_id]);
    return $stmt->fetchAll();
}

/**
 * Registra un cierre de nómina
 * 
 * @param PDO $pdo
 * @param array $datos
 * @return int|false
 */
function registrarCierreNomina($pdo, $datos) {
    $stmt = $pdo->prepare("
        INSERT INTO cierres_nomina (
            periodo_desde, periodo_hasta, tipo_nomina, usuario_cierre,
            total_trabajadores, total_devengado, total_deducciones, total_neto,
            total_contribucion, total_vacaciones_pagadas, observaciones, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $datos['periodo_desde'],
        $datos['periodo_hasta'],
        $datos['tipo_nomina'] ?? 'automatica',
        $datos['usuario_cierre'] ?? $_SESSION['usuario'] ?? null,
        $datos['total_trabajadores'],
        $datos['total_devengado'],
        $datos['total_deducciones'],
        $datos['total_neto'],
        $datos['total_contribucion'],
        $datos['total_vacaciones_pagadas'],
        $datos['observaciones'] ?? null,
        $datos['estado'] ?? 'cerrado'
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Obtiene el último cierre de nómina para un período
 * 
 * @param PDO $pdo
 * @param string $periodo_desde
 * @param string $periodo_hasta
 * @return array|null
 */
function getUltimoCierreNomina($pdo, $periodo_desde, $periodo_hasta) {
    $stmt = $pdo->prepare("
        SELECT * FROM cierres_nomina 
        WHERE periodo_desde = ? AND periodo_hasta = ?
        ORDER BY fecha_cierre DESC LIMIT 1
    ");
    $stmt->execute([$periodo_desde, $periodo_hasta]);
    return $stmt->fetch();
}

/**
 * Genera un código único para trabajador (últimos 6 dígitos del CI)
 * 
 * @param string $ci
 * @return string
 */
function generarCodigoTrabajador($ci) {
    $ci_limpio = preg_replace('/\D/', '', $ci);
    return substr($ci_limpio, -6);
}
?>