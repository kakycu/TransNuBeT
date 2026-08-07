<?php
// index.php - Dashboard estilo Windows 11 CON GRÁFICO DE BARRAS AGRUPADAS Y TODAS LAS MEJORAS
require_once __DIR__ . '/config/database.php';
session_start();

// Verificar sesión
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Datos del usuario
$user_nombre_completo = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? '';
$user_ci = $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

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

// ============================================
// ALERTAS DEL SISTEMA
// ============================================
$alertas = [];
try {
    // Nóminas en borrador
    $borradores = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde), '-', tipo_nomina)) as total 
        FROM nominas WHERE estado = 'borrador'
    ")->fetchColumn();
    if ($borradores > 0) {
        $alertas[] = [
            'tipo' => 'warning',
            'icono' => 'fa-exclamation-triangle',
            'mensaje' => "Hay $borradores nómina(s) en estado BORRADOR que necesitan revisión"
        ];
    }
    
    // Empleados próximos a vacaciones (menos de 30 días)
    $proximas_vacaciones = $pdo->query("
        SELECT COUNT(*) as total 
        FROM trabajadores 
        WHERE activo = 1 
        AND fecha_alta IS NOT NULL
        AND DATEDIFF(DATE_ADD(fecha_alta, INTERVAL 1 YEAR), CURDATE()) BETWEEN 0 AND 30
    ")->fetchColumn();
    if ($proximas_vacaciones > 0) {
        $alertas[] = [
            'tipo' => 'info',
            'icono' => 'fa-umbrella-beach',
            'mensaje' => "$proximas_vacaciones empleado(s) cumplen año en los próximos 30 días"
        ];
    }
} catch (PDOException $e) {}

// ============================================
// CONSULTA PARA ÚLTIMAS NÓMINAS (TODOS LOS ESTADOS)
// ============================================
$ultimas_nominas = [];
$totales_devengado = 0;
$totales_deducciones = 0;
$totales_neto = 0;

try {
    $ultimas_nominas = $pdo->query("
        SELECT 
            DATE_FORMAT(n.periodo_desde, '%Y-%m') as periodo, 
            YEAR(n.periodo_desde) as anio, 
            MONTH(n.periodo_desde) as mes,
            n.tipo_nomina,
            n.estado,
            COUNT(DISTINCT n.trabajador_id) as total_empleados,
            COALESCE(SUM(n.total_salario_devengado), 0) as total_devengado,
            COALESCE(SUM(COALESCE(n.descuentos, 0) + COALESCE(n.contribucion_especial, 0) + COALESCE(n.ingresos_personales, 0) + COALESCE(n.otras_deducciones, 0)), 0) as total_deducciones,
            COALESCE(SUM(n.importe_neto), 0) as total_neto
        FROM nominas n
        GROUP BY YEAR(n.periodo_desde), MONTH(n.periodo_desde), n.tipo_nomina, n.estado
        ORDER BY n.periodo_desde DESC, n.tipo_nomina ASC
        LIMIT 15
    ")->fetchAll();
    
    foreach ($ultimas_nominas as $nomina) {
        $totales_devengado += floatval($nomina['total_devengado']);
        $totales_deducciones += floatval($nomina['total_devengado']) - floatval($nomina['total_neto']);
        $totales_neto += floatval($nomina['total_neto']);
    }
    
} catch (PDOException $e) {
    $ultimas_nominas = [];
    error_log("Error en consulta últimas nóminas: " . $e->getMessage());
}

// ============================================
// CONSULTA PARA NÓMINAS POR MES (GRÁFICO AGRUPADO)
// ============================================
$datos_grafico = [];
$meses_array = [];
$montos_array = [];
$empleados_array = [];

try {
    $stmt_grafico = $pdo->query("
        SELECT 
            DATE_FORMAT(n.periodo_desde, '%Y-%m') as periodo, 
            YEAR(n.periodo_desde) as anio, 
            MONTH(n.periodo_desde) as mes,
            COUNT(DISTINCT n.trabajador_id) as total_empleados, 
            COALESCE(SUM(n.importe_neto), 0) as total_neto
        FROM nominas n
        WHERE n.estado != 'borrador'
        GROUP BY YEAR(n.periodo_desde), MONTH(n.periodo_desde)
        ORDER BY n.periodo_desde ASC
    ");

    if ($stmt_grafico) {
        $nominas_por_mes = $stmt_grafico->fetchAll();
    }

    $agregado_por_mes = [];
    foreach ($nominas_por_mes as $nomina) {
        if (empty($nomina['periodo'])) continue;
        
        $nombre_mes = nombreMesEspanol($nomina['mes']) . ' ' . $nomina['anio'];
        $key = $nomina['anio'] . '-' . str_pad($nomina['mes'], 2, '0', STR_PAD_LEFT);
        
        $agregado_por_mes[$key] = [
            'nombre' => $nombre_mes,
            'total_neto' => floatval($nomina['total_neto']),
            'total_empleados' => intval($nomina['total_empleados']),
            'periodo' => $nomina['periodo']
        ];
    }

    ksort($agregado_por_mes);

    foreach ($agregado_por_mes as $item) {
        $meses_array[] = $item['nombre'];
        $montos_array[] = floatval($item['total_neto']);
        $empleados_array[] = intval($item['total_empleados']);
    }

    $datos_grafico = $agregado_por_mes;

} catch (PDOException $e) {
    error_log("Error en consulta gráfico: " . $e->getMessage());
    $nominas_por_mes = [];
    $datos_grafico = [];
}

// ============================================
// DISTRIBUCIÓN POR TIPO DE NÓMINA (GRÁFICO DE TORTA)
// ============================================
$distribucion_tipos = [];
$labels_torta = [];
$data_torta = [];

try {
    $distribucion_tipos = $pdo->query("
        SELECT 
            CASE 
                WHEN tipo_nomina IN ('automatica', 'ordinaria') THEN 'Automática'
                WHEN tipo_nomina = 'extraordinaria' THEN 'Extraordinaria'
                WHEN tipo_nomina = 'vacaciones' THEN 'Vacaciones'
                WHEN tipo_nomina IN ('bono', 'rendimiento') THEN 'Rendimiento'
                ELSE 'Otros'
            END as tipo_agrupado,
            COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde), '-', tipo_nomina)) as cantidad
        FROM nominas
        WHERE estado != 'borrador'
        GROUP BY tipo_agrupado
        ORDER BY cantidad DESC
    ")->fetchAll();
    
    foreach ($distribucion_tipos as $item) {
        $labels_torta[] = $item['tipo_agrupado'];
        $data_torta[] = intval($item['cantidad']);
    }
} catch (PDOException $e) {
    $distribucion_tipos = [];
}

// ============================================
// KPI - SALARIO PROMEDIO Y VARIACIÓN
// ============================================
$kpi = [];
try {
    $kpi['salario_promedio'] = $pdo->query("
        SELECT AVG(importe_neto) FROM nominas 
        WHERE estado != 'borrador' 
        AND tipo_nomina IN ('automatica', 'ordinaria')
        AND periodo_desde = (
            SELECT MAX(periodo_desde) FROM nominas 
            WHERE estado != 'borrador' AND tipo_nomina IN ('automatica', 'ordinaria')
        )
    ")->fetchColumn();
    
    $ultimo_mes = $pdo->query("
        SELECT DISTINCT 
            DATE_FORMAT(periodo_desde, '%Y-%m-01') as mes_inicio,
            DATE_FORMAT(periodo_desde, '%Y-%m') as mes
        FROM nominas 
        WHERE estado != 'borrador'
        ORDER BY periodo_desde DESC 
        LIMIT 1
    ")->fetch();
    
    if ($ultimo_mes) {
        $mes_actual_total = $pdo->query("
            SELECT COALESCE(SUM(importe_neto), 0) as total
            FROM nominas 
            WHERE estado != 'borrador'
            AND DATE_FORMAT(periodo_desde, '%Y-%m') = '{$ultimo_mes['mes']}'
        ")->fetchColumn();
        
        $mes_anterior = $pdo->query("
            SELECT DISTINCT 
                DATE_FORMAT(periodo_desde, '%Y-%m') as mes
            FROM nominas 
            WHERE estado != 'borrador'
            AND periodo_desde < '{$ultimo_mes['mes_inicio']}'
            ORDER BY periodo_desde DESC 
            LIMIT 1
        ")->fetch();
        
        if ($mes_anterior) {
            $mes_anterior_total = $pdo->query("
                SELECT COALESCE(SUM(importe_neto), 0) as total
                FROM nominas 
                WHERE estado != 'borrador'
                AND DATE_FORMAT(periodo_desde, '%Y-%m') = '{$mes_anterior['mes']}'
            ")->fetchColumn();
            
            if ($mes_anterior_total > 0) {
                $kpi['variacion'] = (($mes_actual_total - $mes_anterior_total) / $mes_anterior_total) * 100;
                $kpi['mes_actual'] = $ultimo_mes['mes'];
                $kpi['mes_anterior'] = $mes_anterior['mes'];
                $kpi['total_actual'] = $mes_actual_total;
                $kpi['total_anterior'] = $mes_anterior_total;
            } else {
                $kpi['variacion'] = 0;
                $kpi['mes_actual'] = $ultimo_mes['mes'];
                $kpi['mes_anterior'] = $mes_anterior['mes'];
                $kpi['total_actual'] = $mes_actual_total;
                $kpi['total_anterior'] = 0;
            }
        } else {
            $kpi['variacion'] = 0;
            $kpi['mes_actual'] = $ultimo_mes['mes'];
            $kpi['mes_anterior'] = 'Sin datos';
            $kpi['total_actual'] = $mes_actual_total;
            $kpi['total_anterior'] = 0;
        }
    } else {
        $kpi['variacion'] = 0;
        $kpi['mes_actual'] = 'Sin datos';
        $kpi['mes_anterior'] = 'Sin datos';
        $kpi['total_actual'] = 0;
        $kpi['total_anterior'] = 0;
    }
    
} catch (PDOException $e) {
    $kpi['salario_promedio'] = 0;
    $kpi['variacion'] = 0;
    $kpi['mes_actual'] = 'Error';
    $kpi['mes_anterior'] = 'Error';
    $kpi['total_actual'] = 0;
    $kpi['total_anterior'] = 0;
}

// ============================================
// REGISTRO HISTÓRICO DE MONTOS PARA REDISTRIBUCIÓN
// ============================================
$montos_distrib = [];
$anos_disponibles = [];
$meses_distrib_array = [];
$montos_distrib_array = [];

try {
    $anos_stmt = $pdo->query("SELECT DISTINCT anio FROM montos_distrib ORDER BY anio DESC");
    $anos_disponibles = $anos_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($anos_disponibles)) {
        $anos_disponibles = [date('Y')];
    }
    
    $anio_seleccionado = isset($_GET['anio_distrib']) ? intval($_GET['anio_distrib']) : $anos_disponibles[0];
    
    $montos_stmt = $pdo->prepare("SELECT mes, importe_dis, fecha_registro FROM montos_distrib WHERE anio = ? ORDER BY fecha_registro ASC");
    $montos_stmt->execute([$anio_seleccionado]);
    $montos_distrib = $montos_stmt->fetchAll();
    
    foreach ($montos_distrib as $item) {
        $meses_distrib_array[] = ucfirst($item['mes']);
        $montos_distrib_array[] = floatval($item['importe_dis']);
    }
    
    $total_anual = array_sum(array_column($montos_distrib, 'importe_dis'));
    if (!empty($montos_distrib)) {
        $max_item = $montos_distrib[array_search(max(array_column($montos_distrib, 'importe_dis')), array_column($montos_distrib, 'importe_dis'))];
        $min_item = $montos_distrib[array_search(min(array_column($montos_distrib, 'importe_dis')), array_column($montos_distrib, 'importe_dis'))];
    } else {
        $max_item = ['mes' => '—', 'importe_dis' => 0];
        $min_item = ['mes' => '—', 'importe_dis' => 0];
    }
    
} catch (PDOException $e) {
    error_log("Error en consulta montos distrib: " . $e->getMessage());
    $montos_distrib = [];
    $anos_disponibles = [date('Y')];
    $total_anual = 0;
    $max_item = ['mes' => '—', 'importe_dis' => 0];
    $min_item = ['mes' => '—', 'importe_dis' => 0];
}

// ============================================
// ESTADÍSTICAS GENERALES
// ============================================
$total_empleados = 0;
$total_empleados_inactivos = 0;
$total_periodos_procesados = 0;
$total_nominas_automaticas = 0;
$total_nominas_extraordinarias = 0;
$total_nominas_vacaciones = 0;
$total_pago_rendimiento = 0;
$total_pagado_salario = 0;

try { $total_empleados = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1")->fetchColumn(); } catch (PDOException $e) {}
try { $total_empleados_inactivos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 0 OR (fecha_baja IS NOT NULL AND fecha_baja <= CURDATE())")->fetchColumn(); } catch (PDOException $e) {}

try { $total_periodos_procesados = $pdo->query("SELECT COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde))) FROM nominas WHERE estado != 'borrador'")->fetchColumn(); } catch (PDOException $e) {}

try { 
    $total_nominas_automaticas = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde), '-', tipo_nomina)) 
        FROM nominas 
        WHERE (tipo_nomina = 'automatica' OR tipo_nomina = 'ordinaria') AND estado != 'borrador'
    ")->fetchColumn(); 
} catch (PDOException $e) {}

try { 
    $total_nominas_extraordinarias = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde), '-', tipo_nomina)) 
        FROM nominas 
        WHERE tipo_nomina = 'extraordinaria' AND estado != 'borrador'
    ")->fetchColumn(); 
} catch (PDOException $e) {}

try { 
    $total_nominas_vacaciones = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(YEAR(periodo_desde), '-', MONTH(periodo_desde), '-', tipo_nomina)) 
        FROM nominas 
        WHERE tipo_nomina = 'vacaciones' AND estado != 'borrador'
    ")->fetchColumn(); 
} catch (PDOException $e) {}

try { 
    $total_pago_rendimiento = $pdo->query("
        SELECT COALESCE(SUM(total_salario_devengado), 0) 
        FROM nominas 
        WHERE (tipo_nomina = 'bono' OR tipo_nomina = 'rendimiento') AND estado != 'borrador'
    ")->fetchColumn(); 
} catch (PDOException $e) {}

try { 
    $total_pagado_salario = $pdo->query("
        SELECT COALESCE(SUM(importe_neto), 0) 
        FROM nominas
        WHERE estado != 'borrador'
    ")->fetchColumn(); 
} catch (PDOException $e) {}


// ============================================
// GRÁFICO DE DISTRIBUCIÓN POR CENTROS DE COSTO
// ============================================
$centros_costo_data = [];
$centros_labels = [];
$centros_salarios = [];
$centros_empleados = [];
$centros_colores = ['#3b82f6', '#fbbf24', '#4ade80', '#a78bfa', '#f87171', '#34d399', '#f472b6', '#60a5fa', '#fb923c', '#22d3ee'];
$periodo_referencia = 'Sin datos';

try {
    // Obtener el período de referencia
    $periodo_stmt = $pdo->query("
        SELECT DISTINCT 
            DATE_FORMAT(periodo_desde, '%Y-%m') as periodo,
            MONTH(periodo_desde) as mes_numero,
            YEAR(periodo_desde) as anio
        FROM nominas 
        WHERE estado != 'borrador'
        ORDER BY periodo_desde DESC 
        LIMIT 1
    ");
    $periodo_ref = $periodo_stmt->fetch();
    if ($periodo_ref) {
        $mes_espanol = nombreMesEspanol($periodo_ref['mes_numero']);
        $periodo_referencia = $mes_espanol . ' ' . $periodo_ref['anio'];
        $periodo_ref_mes = $periodo_ref['mes_numero'];
        $periodo_ref_anio = $periodo_ref['anio'];
    } else {
        // Si no hay datos, usar mes actual
        $periodo_ref_mes = date('m');
        $periodo_ref_anio = date('Y');
        $periodo_referencia = nombreMesEspanol(date('m')) . ' ' . date('Y');
    }
    
    // ============================================
    // CONSULTA PRINCIPAL - OBTENER TODOS LOS CENTROS DE COSTO
    // ============================================
    $centros_stmt = $pdo->prepare("
        SELECT 
            cc.id,
            cc.nombre,
            COUNT(DISTINCT t.id) as total_empleados,
            COALESCE(SUM(n.total_salario_devengado), 0) as total_salario
        FROM centros_costo cc
        LEFT JOIN trabajadores t ON t.centro_costo_id = cc.id AND t.activo = 1
        LEFT JOIN nominas n ON n.trabajador_id = t.id 
            AND n.estado != 'borrador'
            AND MONTH(n.periodo_desde) = ?
            AND YEAR(n.periodo_desde) = ?
        WHERE cc.activo = 1
        GROUP BY cc.id, cc.nombre
        ORDER BY total_salario DESC
        LIMIT 10
    ");
    
    $centros_stmt->execute([$periodo_ref_mes, $periodo_ref_anio]);
    $centros_costo_data = $centros_stmt->fetchAll();
    
    // ============================================
    // EMPLEADOS SIN CENTRO DE COSTO
    // ============================================
    $sin_centro_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total_empleados,
            COALESCE(SUM(n.total_salario_devengado), 0) as total_salario
        FROM trabajadores t
        LEFT JOIN nominas n ON n.trabajador_id = t.id 
            AND n.estado != 'borrador'
            AND MONTH(n.periodo_desde) = ?
            AND YEAR(n.periodo_desde) = ?
        WHERE t.activo = 1 
            AND (t.centro_costo_id IS NULL OR t.centro_costo_id = 0)
    ");
    $sin_centro_stmt->execute([$periodo_ref_mes, $periodo_ref_anio]);
    $sin_centro = $sin_centro_stmt->fetch();
    
    $sin_centro_empleados = intval($sin_centro['total_empleados']);
    $sin_centro_salario = floatval($sin_centro['total_salario']);
    
    // ============================================
    // EMPLEADOS SIN NÓMINA EN EL MES DE REFERENCIA
    // ============================================
    $sin_nomina_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id) as total_empleados
        FROM trabajadores t
        WHERE t.activo = 1 
        AND t.id NOT IN (
            SELECT DISTINCT trabajador_id 
            FROM nominas 
            WHERE estado != 'borrador'
            AND MONTH(periodo_desde) = ?
            AND YEAR(periodo_desde) = ?
        )
    ");
    $sin_nomina_stmt->execute([$periodo_ref_mes, $periodo_ref_anio]);
    $sin_nomina_empleados = intval($sin_nomina_stmt->fetchColumn());
    
    // ============================================
    // PREPARAR ARRAYS PARA EL GRÁFICO
    // ============================================
    $total_empleados_cc = 0;
    $total_salario_cc = 0;
    
    // Agregar centros de costo
    foreach ($centros_costo_data as $index => $item) {
        $nombre_corto = strlen($item['nombre']) > 20 ? substr($item['nombre'], 0, 18) . '...' : $item['nombre'];
        $centros_labels[] = $nombre_corto;
        $centros_salarios[] = floatval($item['total_salario']);
        $centros_empleados[] = intval($item['total_empleados']);
        
        $total_empleados_cc += intval($item['total_empleados']);
        $total_salario_cc += floatval($item['total_salario']);
    }
    
    // Agregar empleados sin centro de costo
    if ($sin_centro_empleados > 0) {
        $centros_labels[] = 'Sin Asignar';
        $centros_salarios[] = $sin_centro_salario;
        $centros_empleados[] = $sin_centro_empleados;
        $centros_colores[] = '#64748b';
        $total_empleados_cc += $sin_centro_empleados;
        $total_salario_cc += $sin_centro_salario;
    }
    
    // Agregar empleados sin nómina en el mes
    if ($sin_nomina_empleados > 0) {
        $centros_labels[] = 'Sin Nómina';
        $centros_salarios[] = 0;
        $centros_empleados[] = $sin_nomina_empleados;
        $centros_colores[] = '#ef4444';
        $total_empleados_cc += $sin_nomina_empleados;
    }
    
} catch (PDOException $e) {
    error_log("Error en consulta centros costo: " . $e->getMessage());
    $centros_costo_data = [];
    $centros_labels = [];
    $centros_salarios = [];
    $centros_empleados = [];
    $total_empleados_cc = 0;
    $total_salario_cc = 0;
    $periodo_referencia = 'Sin datos';
    $sin_centro_empleados = 0;
    $sin_centro_salario = 0;
    $sin_nomina_empleados = 0;
}
$current_page = basename($_SERVER['PHP_SELF']);

// ============================================
// TIPOS DE NÓMINA DISPONIBLES (PARA FILTROS)
// ============================================
$tipos_nomina = [];
try {
    $tipos_nomina = $pdo->query("
        SELECT DISTINCT tipo_nomina 
        FROM nominas 
        WHERE tipo_nomina IS NOT NULL AND tipo_nomina != ''
        ORDER BY tipo_nomina ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tipos_nomina = ['automatica', 'extraordinaria', 'vacaciones', 'bono', 'ajuste'];
}

$tipos_nombre = [
    'automatica' => 'Automática',
    'ordinaria' => 'Ordinaria',
    'extraordinaria' => 'Extraordinaria',
    'vacaciones' => 'Vacaciones',
    'bono' => 'Bono/Rendimiento',
    'rendimiento' => 'Rendimiento',
    'ajuste' => 'Ajuste'
];


// ============================================
// TODOS LOS CUMPLEAÑOS (DESDE EL CI)
// ============================================
$cumpleaneros = [];
try {
    // Obtener TODOS los trabajadores con CI válido (sin importar activo o fecha_baja)
    $stmt = $pdo->query("
        SELECT id, nombre_completo, ci, foto_ruta
        FROM trabajadores
        WHERE ci IS NOT NULL
          AND ci != ''
          AND ci != '00000000000'
          AND LENGTH(ci) >= 6
    ");
    $trabajadores = $stmt->fetchAll();

    $hoy = new DateTime();
    foreach ($trabajadores as $t) {
        $ci_limpio = preg_replace('/\D/', '', $t['ci']);
        if (strlen($ci_limpio) < 6) continue;

        $anio = substr($ci_limpio, 0, 2);
        $mes  = substr($ci_limpio, 2, 2);
        $dia  = substr($ci_limpio, 4, 2);

        if (!checkdate((int)$mes, (int)$dia, 2000)) continue;

        $anio_completo = ($anio < 24) ? (2000 + $anio) : (1900 + $anio);
        // Ajustar si el año es mayor al actual (ej: 25 -> 1925)
        if ($anio_completo > $hoy->format('Y')) {
            $anio_completo -= 100;
        }

        $fecha_nacimiento = DateTime::createFromFormat('Y-m-d', $anio_completo . '-' . $mes . '-' . $dia);
        if (!$fecha_nacimiento) continue;

        // Calcular próximo cumpleaños (mes/día en año actual)
        $proximo = DateTime::createFromFormat('Y-m-d', $hoy->format('Y') . '-' . $fecha_nacimiento->format('m-d'));
        if (!$proximo) continue;
        if ($proximo < $hoy) {
            $proximo->modify('+1 year');
        }

        $dias = $hoy->diff($proximo)->days;
        // Mostrar todos, sin límite de 30 días
        $edad = $proximo->format('Y') - $fecha_nacimiento->format('Y');
        $cumpleaneros[] = [
            'id'        => $t['id'],
            'nombre'    => $t['nombre_completo'],
            'foto'      => $t['foto_ruta'],
            'fecha'     => $proximo->format('d/m/Y'),
            'edad'      => $edad,
            'dias'      => $dias
        ];
    }
    // Ordenar por días restantes (más cercano primero)
    usort($cumpleaneros, function($a, $b) {
        return $a['dias'] <=> $b['dias'];
    });
} catch (PDOException $e) {
    $cumpleaneros = [];
    error_log("Error en cumpleaños (CI): " . $e->getMessage());
}

// ============================================
// 1. SECUENCIAS DE NÓMINAS
// ============================================
$secuencias = [];
$total_secuencias = 0;
try {
    $secuencias = $pdo->query("
        SELECT tipo_nomina, ultimo_numero 
        FROM secuencias_nominas 
        ORDER BY tipo_nomina ASC
    ")->fetchAll();
    foreach ($secuencias as $s) {
        $total_secuencias += intval($s['ultimo_numero']);
    }
} catch (PDOException $e) {
    $secuencias = [];
    $total_secuencias = 0;
}

// ============================================
// 2. TOTALES POR TIPO DE NÓMINA DESDE CIERRES
// ============================================
$totales_cierres = [];
$totales_generales = [
    'devengado' => 0,
    'deducciones' => 0,
    'neto' => 0,
    'contribucion' => 0,
    'vacaciones' => 0,
    'cierres' => 0
];
try {
    $totales_cierres = $pdo->query("
        SELECT 
            tipo_nomina,
            COUNT(*) as cantidad_cierres,
            SUM(total_devengado) as sum_devengado,
            SUM(total_deducciones) as sum_deducciones,
            SUM(total_neto) as sum_neto,
            SUM(total_contribucion) as sum_contribucion,
            SUM(total_vacaciones_pagadas) as sum_vacaciones
        FROM cierres_nomina
        GROUP BY tipo_nomina
        ORDER BY tipo_nomina ASC
    ")->fetchAll();

    foreach ($totales_cierres as $t) {
        $totales_generales['devengado'] += floatval($t['sum_devengado']);
        $totales_generales['deducciones'] += floatval($t['sum_deducciones']);
        $totales_generales['neto'] += floatval($t['sum_neto']);
        $totales_generales['contribucion'] += floatval($t['sum_contribucion']);
        $totales_generales['vacaciones'] += floatval($t['sum_vacaciones']);
        $totales_generales['cierres'] += intval($t['cantidad_cierres']);
    }
} catch (PDOException $e) {
    $totales_cierres = [];
    $totales_generales = [
        'devengado' => 0,
        'deducciones' => 0,
        'neto' => 0,
        'contribucion' => 0,
        'vacaciones' => 0,
        'cierres' => 0
    ];
}



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Dashboard</title>
    <link rel="icon" type="image/png" href="../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="css/sweetalert2.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
    <script src="js/chart.umd.min.js"></script>
    
    <script src="js/jszip.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/html2canvas.min.js"></script>
    <script src="js/exceljs.min.js"></script>
    <script src="js/FileSaver.min.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
</head>
<body>

<div class="win11-bg"></div>

<?php include 'includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> - Dashboard</h1>
                <p><i class="fas fa-chart-line me-1"></i> Panel de control y estadísticas del sistema</p>
            </div>
        </div>
        <?php include 'includes/user_menu.php'; ?>
    </div>
    
    <!-- ALERTAS -->
    <?php if (!empty($alertas)): ?>
    <div class="row g-4 fade-in-up mb-4">
        <div class="col-12">
            <?php foreach ($alertas as $alerta): ?>
                <?php 
                    $bg_color = ($alerta['tipo'] == 'warning') ? 'rgba(245,158,11,0.15)' : 'rgba(59,130,246,0.15)';
                    $border_color = ($alerta['tipo'] == 'warning') ? 'rgba(245,158,11,0.3)' : 'rgba(59,130,246,0.3)';
                ?>
                <div class="alert alert-win alert-dismissible fade show" 
                     style="background: <?php echo $bg_color; ?>; 
                            border: 1px solid <?php echo $border_color; ?>; 
                            color: #fff; border-radius: 12px; padding: 15px 20px;">
                    <i class="fas <?php echo $alerta['icono']; ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" 
                            style="filter: invert(1);"></button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="kpi-grid fade-in-up" style="animation-delay: 0.05s;">
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-wallet me-1"></i> Salario Promedio</p>
                    <h3 class="stat-value"><?php echo formatearMoneda($kpi['salario_promedio'] ?? 0); ?></h3>
                    <small style="color: rgba(255,255,255,0.4);">Último mes procesado</small>
                </div>
                <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15); color: #60a5fa;">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-arrow-trend-up me-1"></i> Variación Mensual</p>
                    <h3 class="stat-value" style="color: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? '#4ade80' : '#f87171'; ?>">
                        <?php echo number_format($kpi['variacion'] ?? 0, 1); ?>%
                        <i class="fas fa-<?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down'; ?>" style="font-size: 1.2rem;"></i>
                    </h3>
                    <small style="color: rgba(255,255,255,0.4);">Comparado con mes anterior</small>
                </div>
                <div class="stat-icon" style="background: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'rgba(74, 222, 128, 0.15)' : 'rgba(248, 113, 113, 0.15)'; ?>; 
                            color: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? '#4ade80' : '#f87171'; ?>;">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card" id="kpiActualizacion" style="cursor: pointer;" title="Haz clic para actualizar el dashboard">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">
                        <i class="fas fa-clock me-1"></i> Última Actualización
                        <small style="color: rgba(255,255,255,0.3); font-size: 0.6rem; display: block;">
                            Haz clic para actualizar
                        </small>
                    </p>
                    <h3 class="stat-value" style="font-size: 1.4rem;" id="ultimaActualizacion">
                        <i class="fas fa-clock me-2" style="color: #94a3b8;"></i>
                        <?php 
                            $timestamp = time();
                            echo date('d/m/Y', $timestamp) . ' - ' . date('h:i A', $timestamp);
                        ?>
                    </h3>
                    <small style="color: rgba(255,255,255,0.4);">Datos en tiempo real</small>
                </div>
                <div class="stat-icon" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8;">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid fade-in-up" style="animation-delay: 0.1s;">
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-users me-1"></i> Total Empleados</p>
                    <h3 class="stat-value"><?php echo number_format($total_empleados); ?></h3>
                    <small style="color: rgba(255,255,255,0.4);">Activos: <?php echo number_format($total_empleados); ?> | Inactivos: <?php echo number_format($total_empleados_inactivos); ?></small>
                </div>
                <div class="stat-icon" style="background: rgba(0, 120, 212, 0.15); color: #60a5fa;">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-dollar-sign me-1"></i> Total Pagado Ingresos Personales</p>
                    <h3 class="stat-value"><?php echo formatearMoneda($total_pagado_salario); ?></h3>
                    <small style="color: rgba(255,255,255,0.4);">Suma neta de todos los tipo de nóminas</small>
                </div>
                <div class="stat-icon" style="background: rgba(16, 124, 16, 0.15); color: #4ade80;">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-calendar-alt me-1"></i> Períodos Procesados</p>
                    <h3 class="stat-value"><?php echo number_format($total_periodos_procesados); ?></h3>
                    <small style="color: rgba(255,255,255,0.4);">Meses con nóminas generadas</small>
                </div>
                <div class="stat-icon" style="background: rgba(255, 185, 0, 0.15); color: #fbbf24;">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
	
<!-- SECCIÓN RESUMEN DE NÓMINAS (Secuencias + Rendimiento + Cierres) -->
<div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.06s;">
    <div class="col-12">
        <div class="glass-card">
            <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="fas fa-clipboard-list me-2"></i>Resumen de Nóminas
                </h6>
                <span class="badge-win"><i class="fas fa-database me-1"></i> secuencias + cierres</span>
            </div>
            <div class="p-3">

                <!-- ========================================== -->
                <!-- 1. TARJETAS DE SECUENCIAS + RENDIMIENTO    -->
                <!-- ========================================== -->
                <?php if (!empty($secuencias) || ($total_pago_rendimiento ?? 0) > 0): ?>
                <div class="row g-3 mb-4">
                    <?php 
                    $colores_tipo = [
                        'automatica'   => 'primary',
                        'ordinaria'    => 'info',
                        'extraordinaria' => 'warning',
                        'vacaciones'   => 'success',
                        'bono'         => 'danger',
                        'rendimiento'  => 'danger',
                        'ajuste'       => 'secondary'
                    ];
                    $iconos_tipo = [
                        'automatica'   => 'fa-robot',
                        'ordinaria'    => 'fa-calendar-alt',
                        'extraordinaria' => 'fa-star',
                        'vacaciones'   => 'fa-umbrella-beach',
                        'bono'         => 'fa-gift',
                        'rendimiento'  => 'fa-trophy',
                        'ajuste'       => 'fa-sliders-h'
                    ];
                    // Tarjetas de secuencias
                    foreach ($secuencias as $item):
                        $tipo = strtolower(trim($item['tipo_nomina']));
                        $color = $colores_tipo[$tipo] ?? 'secondary';
                        $icono = $iconos_tipo[$tipo] ?? 'fa-tag';
                        $numero = intval($item['ultimo_numero']);
                    ?>
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded text-center" style="background: rgba(<?php echo ($color == 'primary') ? '59,130,246' : (($color == 'success') ? '74,222,128' : (($color == 'warning') ? '245,158,11' : (($color == 'danger') ? '248,113,113' : (($color == 'info') ? '96,165,250' : '148,163,184')))); ?>, 0.1);">
                                <i class="fas <?php echo $icono; ?> fa-2x mb-2" style="color: <?php echo ($color == 'primary') ? '#3b82f6' : (($color == 'success') ? '#4ade80' : (($color == 'warning') ? '#fbbf24' : (($color == 'danger') ? '#f87171' : (($color == 'info') ? '#60a5fa' : '#94a3b8')))); ?>;"></i>
                                <div>
                                    <span class="badge bg-<?php echo $color; ?> mb-1"><?php echo ucfirst($tipo); ?></span>
                                    <h4 class="mb-0"><?php echo number_format($numero); ?></h4>
                                    <small style="color: rgba(255,255,255,0.4);">último número</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Tarjeta extra: Rendimiento Total (total pagado) -->
                    <div class="col-md-3 col-6">
                        <div class="p-3 rounded text-center" style="background: rgba(168,85,247,0.1);">
                            <i class="fas fa-trophy fa-2x mb-2" style="color: #a78bfa;"></i>
                            <div>
                                <span class="badge bg-purple mb-1">Rendimiento Total</span>
                                <h4 class="mb-0"><?php echo formatearMoneda($total_pago_rendimiento ?? 0); ?></h4>
                                <small style="color: rgba(255,255,255,0.4);">total pagado</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-info" style="background: rgba(59,130,246,0.1); border: 1px solid #3b82f6; border-radius: 8px; color: #fff;">
                        <i class="fas fa-info-circle me-2"></i> No hay registros en <strong>secuencias_nominas</strong> ni datos de rendimiento.
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 2. TABLA DE TOTALES POR TIPO (cierres)     -->
                <!-- ========================================== -->
                <?php if (!empty($totales_cierres)): ?>
                <div class="mt-3">
                    <h6 class="fw-semibold mb-2" style="color: #94a3b8;">
                        <i class="fas fa-calculator me-2"></i>Totales Acumulados por Tipo (desde cierres)
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm" style="color: #e2e8f0;">
                            <thead>
                                <tr>
                                    <th style="color: #94a3b8;">Tipo</th>
                                    <th class="text-end" style="color: #94a3b8;">Cierres</th>
                                    <th class="text-end" style="color: #94a3b8;">Devengado</th>
                                    <th class="text-end" style="color: #94a3b8;">Deducciones</th>
                                    <th class="text-end" style="color: #94a3b8;">Neto</th>
                                    <th class="text-end" style="color: #94a3b8;">Contribución</th>
                                    <th class="text-end" style="color: #94a3b8;">Vacaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($totales_cierres as $item):
                                    $tipo = ucfirst($item['tipo_nomina']);
                                    $color = $colores_tipo[strtolower($item['tipo_nomina'])] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($tipo); ?></span></td>
                                    <td class="text-end"><?php echo number_format($item['cantidad_cierres']); ?></td>
                                    <td class="text-end text-success"><?php echo formatearMoneda($item['sum_devengado']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatearMoneda($item['sum_deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: #4ade80;"><?php echo formatearMoneda($item['sum_neto']); ?></td>
                                    <td class="text-end" style="color: #60a5fa;"><?php echo formatearMoneda($item['sum_contribucion']); ?></td>
                                    <td class="text-end" style="color: #fbbf24;"><?php echo formatearMoneda($item['sum_vacaciones']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="border-top: 2px solid rgba(255,255,255,0.1);">
                                    <td style="font-weight: bold; color: #e2e8f0;">TOTAL GENERAL</td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales['cierres']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatearMoneda($totales_generales['devengado']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo formatearMoneda($totales_generales['deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: #4ade80;"><?php echo formatearMoneda($totales_generales['neto']); ?></td>
                                    <td class="text-end fw-bold" style="color: #60a5fa;"><?php echo formatearMoneda($totales_generales['contribucion']); ?></td>
                                    <td class="text-end fw-bold" style="color: #fbbf24;"><?php echo formatearMoneda($totales_generales['vacaciones']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small style="color: rgba(255,255,255,0.3);">
                        <i class="fas fa-clock me-1"></i> Datos actualizados al <?php echo date('d/m/Y H:i:s'); ?>
                    </small>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-3" style="background: rgba(245,158,11,0.1); border: 1px solid #fbbf24; border-radius: 8px; color: #fff;">
                        <i class="fas fa-exclamation-triangle me-2"></i> No hay registros en <strong>cierres_nomina</strong>.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
	
<!-- SECCIÓN TODOS LOS CUMPLEAÑOS -->
<div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.08s;">
    <div class="col-6">
        <div class="glass-card">
            <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="fas fa-birthday-cake me-2"></i>Cumpleaños de los Empleados
                </h6>
                <span class="badge-win" id="cumpleCounter">0 de 0</span>
            </div>
            <div class="p-3">
                <div class="row g-3">
                    <!-- Columna izquierda: 75% carrusel -->
                    <div class="col-lg-5">
                        <div class="text-center" id="cumpleContainer">
                            <div id="cumpleContent">
                                <div class="py-5">
                                    <i class="fas fa-spinner fa-pulse fa-3x" style="color: rgba(255,255,255,0.3);"></i>
                                    <p class="mt-2" style="color: rgba(255,255,255,0.5);">Cargando cumpleañeros...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Columna derecha: 25% lista de los 10 más próximos -->
                    <div class="col-lg-7">
                        <div class="glass-card p-2" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);">
                            <h6 class="text-center mb-2" style="color: rgba(255,255,255,0.7); font-size: 0.8rem; letter-spacing: 0.5px;">
                                <i class="fas fa-list me-1"></i> Los 10 más próximos
                            </h6>
                            <div id="listaCumpleaneros" style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
                                <!-- Se llena desde JavaScript -->
                                <div class="text-center py-3" style="color: rgba(255,255,255,0.3); font-size: 0.8rem;">
                                    <i class="fas fa-spinner fa-pulse me-1"></i> Cargando...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
	
	
	
	
	
	<!-- GRÁFICOS -->
    <div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.15s;">
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-bar me-2"></i>Evolución de Nóminas por Mes</h6>
                    <div>
                        <button class="btn-win btn-win-sm" onclick="exportarGrafico()" title="Exportar gráfico a PNG">
                            <i class="fas fa-download me-1"></i> Exportar PNG
                        </button>
                        <span class="badge-win ms-2"><i class="fas fa-chart-line me-1"></i> Salario Neto</span>
                    </div>
                </div>
                <div class="p-3">
                    <div class="chart-container">
                        <canvas id="nominasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass-card" style="height: 100%;">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-pie me-2"></i>Distribución por Tipo</h6>
                    <button class="btn-win btn-win-sm" onclick="exportarGraficoTorta()" title="Exportar gráfico a PNG">
                        <i class="fas fa-download me-1"></i> PNG
                    </button>
                </div>
                <div class="p-3" style="height: 340px; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($distribucion_tipos)): ?>
                        <canvas id="tipoChart"></canvas>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-3x mb-2" style="color: rgba(255,255,255,0.2);"></i>
                            <p class="mb-0" style="color: rgba(255,255,255,0.5);">No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- REGISTRO HISTÓRICO DE MONTOS -->
    <div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.18s;">
        <div class="col-12">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-coins me-2"></i>Registro Histórico de Montos para Redistribución</h6>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label for="anio_distrib" class="mb-0 text-muted" style="font-size:0.8rem; color: #94a3b8 !important;">Año:</label>
                            <select name="anio_distrib" id="anio_distrib" class="form-select form-select-sm" style="background: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.15); color: #fff; width: auto; border-radius: 8px; padding-right: 2.5rem;">
                                <?php foreach ($anos_disponibles as $ano): ?>
                                    <option value="<?php echo $ano; ?>" <?php echo ($anio_seleccionado == $ano) ? 'selected' : ''; ?>>
                                        <?php echo $ano; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-win btn-win-sm">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                        </form>
                        <div class="btn-group" role="group">
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionExcel()" title="Exportar a Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionPDF()" title="Exportar a PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionPNG()" title="Exportar gráfico a PNG">
                                <i class="fas fa-image"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <?php if (!empty($montos_distrib)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div style="height: 250px; position: relative;">
                                    <canvas id="distribucionChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover table-sm" style="color: #e2e8f0;">
                                <thead>
                                    <tr>
                                        <th style="color: #94a3b8;">Mes</th>
                                        <th class="text-end" style="color: #94a3b8;">Importe de Distribución</th>
                                        <th class="text-end" style="color: #94a3b8;">Variación</th>
                                        <th class="text-end" style="color: #94a3b8;">% del Total</th>
                                        <th class="text-end" style="color: #94a3b8;">Fecha de Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $mes_anterior = null;
                                    foreach ($montos_distrib as $item): 
                                        $timestamp = strtotime($item['fecha_registro']);
                                        $fecha_formateada = date('d/m/Y', $timestamp);
                                        $hora_formateada = date('h:i A', $timestamp);
                                        $importe_actual = floatval($item['importe_dis']);
                                        $porcentaje = ($total_anual > 0) ? ($importe_actual / $total_anual) * 100 : 0;
                                        
                                        $variacion = null;
                                        $variacion_color = '';
                                        if ($mes_anterior !== null) {
                                            $variacion = (($importe_actual - $mes_anterior) / $mes_anterior) * 100;
                                            $variacion_color = $variacion >= 0 ? '#4ade80' : '#f87171';
                                        }
                                        $mes_anterior = $importe_actual;
                                    ?>
                                        <tr>
                                            <td style="color: #e2e8f0;"><?php echo htmlspecialchars(ucfirst($item['mes'])); ?></td>
                                            <td class="text-end text-success fw-bold" style="color: #4ade80 !important;"><?php echo formatearMoneda($item['importe_dis']); ?></td>
                                            <td class="text-end">
                                                <?php if ($variacion !== null): ?>
                                                    <span style="color: <?php echo $variacion_color; ?>;">
                                                        <?php echo ($variacion >= 0 ? '+' : ''); ?><?php echo number_format($variacion, 1); ?>%
                                                        <i class="fas fa-<?php echo $variacion >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #64748b;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end" style="color: #94a3b8;"><?php echo number_format($porcentaje, 1); ?>%</td>
                                            <td class="text-end fecha-registro" style="color: #94a3b8 !important;">
                                                <?php echo $fecha_formateada; ?> <span style="color: #64748b;">|</span> <?php echo $hora_formateada; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active" style="background: rgba(255,255,255,0.05);">
                                        <th class="text-end" style="color: #e2e8f0;">Total Anual:</th>
                                        <th class="text-end text-success fw-bold" style="color: #4ade80 !important;">
                                            <?php echo formatearMoneda($total_anual); ?>
                                        </th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="row mt-3 g-3">
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(59,130,246,0.1);">
                                    <small style="color: #94a3b8;">Promedio Mensual</small>
                                    <h6 class="mb-0" style="color: #60a5fa;"><?php echo formatearMoneda($total_anual / count($montos_distrib)); ?></h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(74,222,128,0.1);">
                                    <small style="color: #94a3b8;">Mes Máximo</small>
                                    <h6 class="mb-0" style="color: #4ade80;">
                                        <?php echo htmlspecialchars(ucfirst($max_item['mes'])) . ' ' . formatearMoneda($max_item['importe_dis']); ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(248,113,113,0.1);">
                                    <small style="color: #94a3b8;">Mes Mínimo</small>
                                    <h6 class="mb-0" style="color: #f87171;">
                                        <?php echo htmlspecialchars(ucfirst($min_item['mes'])) . ' ' . formatearMoneda($min_item['importe_dis']); ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(168,85,247,0.1);">
                                    <small style="color: #94a3b8;">Meses Registrados</small>
                                    <h6 class="mb-0" style="color: #a78bfa;"><?php echo count($montos_distrib); ?></h6>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-coins fa-2x mb-2" style="color: rgba(255,255,255,0.2);"></i>
                            <p class="mb-0" style="color: #94a3b8;">No hay registros de montos de distribución para el año <?php echo $anio_seleccionado; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


<!-- ============================================
     GRÁFICO DE DISTRIBUCIÓN POR CENTROS DE COSTO
     ============================================ -->
<div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.19s;">
    <div class="col-12">
        <div class="glass-card">
            <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-building me-2"></i>Distribución por Centros de Costo</h6>
                    <small style="color: rgba(255,255,255,0.4); font-size: 0.7rem;">
                        <i class="fas fa-calendar-alt me-1"></i> 
                        Período de referencia: <strong><?php echo htmlspecialchars($periodo_referencia); ?></strong>
                        <?php if (!empty($centros_costo_data)): ?>
                            | <?php echo count($centros_costo_data); ?> centros activos
                        <?php endif; ?>
                    </small>
                </div>
                <div>
                    <button class="btn-win btn-win-sm" onclick="exportarCentrosCosto()" title="Exportar gráfico a PNG">
                        <i class="fas fa-download me-1"></i> PNG
                    </button>
                    <button class="btn-win btn-win-sm" onclick="exportarCentrosCostoExcel()" title="Exportar a Excel">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </button>
                    <span class="badge-win ms-2"><i class="fas fa-chart-bar me-1"></i> Salario vs Empleados</span>
                </div>
            </div>
            <div class="p-3">
                <div class="row">
                    <div class="col-lg-8">
                        <div style="height: 350px; position: relative;">
                            <canvas id="centrosCostoChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
<div class="table-responsive">
    <table class="table table-dark table-sm" style="color: #e2e8f0;">
        <thead>
            <tr>
                <th style="color: #94a3b8;">Centro de Costo</th>
                <th class="text-end" style="color: #94a3b8;">Empleados</th>
                <th class="text-end" style="color: #94a3b8;">Total Salario</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($centros_costo_data) || $sin_centro_empleados > 0): 
                // Mostrar centros de costo
                foreach ($centros_costo_data as $item):
                    $color = $centros_colores[array_search($item, $centros_costo_data) % count($centros_colores)];
            ?>
            <tr>
                <td style="color: #e2e8f0;">
                    <span class="badge" style="background: <?php echo $color; ?>; width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 8px;"></span>
                    <?php echo htmlspecialchars($item['nombre']); ?>
                </td>
                <td class="text-end" style="color: #94a3b8;"><?php echo number_format($item['total_empleados']); ?></td>
                <td class="text-end text-success fw-bold"><?php echo formatearMoneda($item['total_salario']); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Mostrar "Sin Asignar" si hay empleados sin centro -->
            <?php if ($sin_centro_empleados > 0): ?>
            <tr>
                <td style="color: #94a3b8;">
                    <span class="badge" style="background: #64748b; width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 8px;"></span>
                    Sin Asignar
                </td>
                <td class="text-end" style="color: #94a3b8;"><?php echo number_format($sin_centro_empleados); ?></td>
                <td class="text-end text-warning fw-bold"><?php echo formatearMoneda($sin_centro_salario); ?></td>
            </tr>
            <?php endif; ?>
            
            <!-- TOTAL -->
            <tr style="border-top: 2px solid rgba(255,255,255,0.1);">
                <td style="color: #e2e8f0; font-weight: bold;">TOTAL</td>
                <td class="text-end" style="color: #e2e8f0; font-weight: bold;"><?php echo number_format($total_empleados_cc); ?></td>
                <td class="text-end text-success fw-bold"><?php echo formatearMoneda($total_salario_cc); ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="3" class="text-center py-3" style="color: #94a3b8;">
                    <i class="fas fa-building me-2"></i> No hay datos disponibles
                </td>
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


	
	
    <!-- TABLA DE ÚLTIMAS NÓMINAS -->
    <div class="row g-4 fade-in-up" style="animation-delay: 0.25s;">
        <div class="col-12">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-history me-2"></i>Últimas Nóminas Generadas</h6>
                    <a href="modules/nominas.php" class="btn-win btn-win-sm">Ir a Nóminas <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="p-3">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <select id="filtroTipo" class="form-select form-select-sm" style="background: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 8px;">
                                <option value="">Todos los tipos</option>
                                <option value="automatica">Automática</option>
                                <option value="extraordinaria">Extraordinaria</option>
                                <option value="vacaciones">Vacaciones</option>
                                <option value="bono">Bono/Rendimiento</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select id="filtroEstado" class="form-select form-select-sm" style="background: rgba(20,20,25,0.8); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 8px;">
                                <option value="">Todos los estados</option>
                                <option value="contabilizado">Contabilizado</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="pagado">Pagado</option>
                                <option value="borrador">Borrador</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group" role="group">
                                <button class="btn-win btn-win-sm" onclick="exportarTablaExcel()" title="Exportar a Excel">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button class="btn-win btn-win-sm" onclick="exportarTablaPDF()" title="Exportar a PDF">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="data-table-wrapper">
                        <table class="table" id="nominasTable">
                            <thead>
                                <tr>
                                    <th style="display: none;">Orden</th>
                                    <th>Período</th>
                                    <th>Tipo</th>
                                    <th>Empleados</th>
                                    <th>Devengado</th>
                                    <th>Deducciones</th>
                                    <th>Neto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($ultimas_nominas)): ?>
                                    <?php foreach ($ultimas_nominas as $nomina): 
                                        $fecha_ordenable = $nomina['anio'] . str_pad($nomina['mes'], 2, '0', STR_PAD_LEFT);
                                        $nombre_mes = nombreMesEspanol($nomina['mes']) . ' ' . $nomina['anio'];
                                        $deducciones_fila = floatval($nomina['total_devengado']) - floatval($nomina['total_neto']);
                                    ?>
                                    <tr>
                                        <td style="display: none;"><?php echo $fecha_ordenable; ?></td>
                                        <td><strong><?php echo htmlspecialchars($nombre_mes); ?></strong></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($nomina['tipo_nomina'] ?? 'ordinaria'); ?></span></td>
                                        <td><?php echo number_format($nomina['total_empleados']); ?></td>
                                        <td><?php echo formatearMoneda($nomina['total_devengado']); ?></td>
                                        <td class="text-danger"><?php echo formatearMoneda($deducciones_fila); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatearMoneda($nomina['total_neto']); ?></td>
                                        <td>
                                            <?php 
                                                $estado = trim($nomina['estado'] ?? 'borrador');
                                                $color = ($estado == 'contabilizado') ? 'success' : 'warning';
                                                echo '<span class="badge bg-' . $color . '">' . ucfirst($estado) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="modules/nominas.php?periodo=<?php echo $nomina['periodo']; ?>&tipo=<?php echo urlencode($nomina['tipo_nomina'] ?? 'automatica'); ?>" 
                                                   class="btn-win btn-win-sm">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                <a href="modules/nominas.php?periodo=<?php echo $nomina['periodo']; ?>&tipo=<?php echo urlencode($nomina['tipo_nomina'] ?? 'automatica'); ?>&abrir_impresion=1" 
                                                   class="btn-win btn-win-sm">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x mb-2 d-block" style="color: rgba(255,255,255,0.2);"></i>
                                            No hay nóminas generadas aún
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($ultimas_nominas)): ?>
                            <tfoot>
                                <tr style="background: rgba(255,255,255,0.05); border-top: 2px solid rgba(255,255,255,0.15);">
                                    <td style="display: none;"></td>
                                    <td colspan="3" style="text-align: right; font-weight: 600; color: #e2e8f0;">
                                        <i class="fas fa-calculator me-2"></i> TOTALES:
                                    </td>
                                    <td style="font-weight: 600; color: #60a5fa;"><?php echo formatearMoneda($totales_devengado); ?></td>
                                    <td style="font-weight: 600; color: #f87171;"><?php echo formatearMoneda($totales_deducciones); ?></td>
                                    <td style="font-weight: 700; color: #4ade80;"><?php echo formatearMoneda($totales_neto); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="row g-4 fade-in-up mt-4" style="animation-delay: 0.3s;">
        <div class="col-12">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h6>
                </div>
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="modules/empleados.php" class="btn-win w-100 text-center py-3">
                                <i class="fas fa-user-plus fa-2x mb-2 d-block"></i> Empleados
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/nominas.php" class="btn-win w-100 text-center py-3">
                                <i class="fas fa-calculator fa-2x mb-2 d-block"></i> Generar Nómina
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/submayor_vacaciones.php" class="btn-win w-100 text-center py-3">
                                <i class="fas fa-umbrella-beach fa-2x mb-2 d-block"></i> Vacaciones
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/configuracion.php" class="btn-win w-100 text-center py-3">
                                <i class="fas fa-cog fa-2x mb-2 d-block"></i> Configuración
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</div>

<!-- ============================================
     SCRIPTS (SOLO UNA VEZ)
     ============================================ -->
<script src="js/jquery-3.6.0.min.js"></script>
<script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="js/datatables/1.13.6/jquery.dataTables.min.js"></script>
<script src="js/sweetalert211.js"></script>

<script>
// ============================================
// CONFIGURACIÓN GLOBAL DESDE PHP
// ============================================
var CONFIG = {
    nombreEmpresa: '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>®',
    jefeProyecto: '<?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?>',
    especialistaGestion: '<?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?>'
};
var nombreEmpresa = CONFIG.nombreEmpresa;


// ============================================
// LIVE CLOCK - FORMATO 12 HORAS
// ============================================
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const hoursStr = hours.toString().padStart(2, '0');
    const timeString = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
    const clockElement = document.getElementById('liveClock');
    if (clockElement) clockElement.textContent = timeString;
}
setInterval(updateClock, 1000);
updateClock();

// ============================================
// SIDEBAR TOGGLE
// ============================================
var sidebar = document.getElementById('winSidebar');
var mainContainer = document.getElementById('mainContainer');
var toggleBtn = document.getElementById('sidebarToggleBtn');

if (localStorage.getItem('winSidebarCollapsed') === 'true') {
    if (sidebar) sidebar.classList.add('collapsed');
    if (mainContainer) mainContainer.classList.add('expanded');
}

if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
        if (sidebar) sidebar.classList.toggle('collapsed');
        if (mainContainer) mainContainer.classList.toggle('expanded');
        localStorage.setItem('winSidebarCollapsed', sidebar ? sidebar.classList.contains('collapsed') : false);
    });
}

// ============================================
// LOGOUT FUNCTION
// ============================================
function cerrarSesion() {
    fetch('logout.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function() { window.location.href = 'login.php?logout=1'; })
        .catch(function() { window.location.href = 'login.php?logout=1'; });
}

document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
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
    }).then(function(result) { if (result.isConfirmed) cerrarSesion(); });
});

document.getElementById('logoutSidebarBtn')?.addEventListener('click', function(e) {
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
    }).then(function(result) { if (result.isConfirmed) cerrarSesion(); });
});

// ============================================
// FILTROS PERSONALIZADOS PARA DATATABLE
// ============================================
$.fn.dataTable.ext.search.push(
    function(settings, data, dataIndex) {
        var tipo = $('#filtroTipo').val();
        var estado = $('#filtroEstado').val();
        var tipoFila = data[2] || '';
        var estadoFila = data[7] || '';
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = estadoFila;
        estadoFila = tempDiv.textContent || tempDiv.innerText || '';
        estadoFila = estadoFila.trim().toLowerCase();
        tipoFila = tipoFila.toLowerCase();
        
        if (tipo && !tipoFila.includes(tipo.toLowerCase())) return false;
        if (estado && !estadoFila.includes(estado.toLowerCase())) return false;
        return true;
    }
);

$('#filtroTipo, #filtroEstado').on('change', function() {
    $('#nominasTable').DataTable().draw();
});

// ============================================
// DATATABLE INITIALIZATION
// ============================================
var $tabla = $('#nominasTable');

if ($.fn.DataTable.isDataTable('#nominasTable')) {
    $tabla.DataTable().destroy();
}

$tabla.removeClass('dataTable');

var $filas = $tabla.find('tbody tr');
var hayDatosValidos = false;

if ($filas.length > 0) {
    var numColumnas = $filas.first().find('td').length;
    if (numColumnas >= 9 && $filas.first().find('td').attr('colspan') !== '9') {
        hayDatosValidos = true;
    }
}

if (hayDatosValidos) {
    $tabla.DataTable({
        language: {
            decimal: "", emptyTable: "No hay datos disponibles en la tabla",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            lengthMenu: "Mostrar _MENU_ registros",
            loadingRecords: "Cargando...",
            processing: "Procesando...",
            search: "Buscar:",
            zeroRecords: "No se encontraron registros coincidentes",
            paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
            aria: { sortAscending: ": activar para ordenar la columna de forma ascendente", sortDescending: ": activar para ordenar la columna de forma descendente" }
        },
        pageLength: 5,
        lengthMenu: [[5, 10, 15, -1], [5, 10, 15, "Todos"]],
        responsive: true,
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: 0, visible: false, searchable: false, orderable: true },
            { targets: 1, orderData: [0] }
        ],
        drawCallback: function() {
            $('.paginate_button').addClass('btn-win-sm');
        }
    });
}

document.querySelectorAll('.fade-in-up').forEach(function(el, index) {
    el.style.animationDelay = (index * 0.05) + 's';
});

// ============================================
// GRÁFICO DE BARRAS AGRUPADAS CON CHART.JS
// ============================================
<?php if (!empty($meses_array)): ?>
var chartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('nominasChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var meses = <?php echo json_encode($meses_array); ?>;
    var montos = <?php echo json_encode($montos_array); ?>;
    var empleados = <?php echo json_encode($empleados_array); ?>;
    
    var hayDatosValidos = montos.length > 0 && montos.some(function(m) { return m > 0; });
    
    if (!hayDatosValidos) {
        canvas.style.display = 'none';
        var container = document.querySelector('.chart-container');
        if (container) {
            container.innerHTML = '<div class="text-center py-5"><i class="fas fa-chart-simple fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i><h5 class="mb-2">No hay datos disponibles</h5><p class="mb-0" style="color: rgba(255,255,255,0.5);">Genera nóminas contabilizadas para visualizar estadísticas</p></div>';
        }
        return;
    }
    
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                {
                    label: '💰 Importe Neto',
                    data: montos,
                    backgroundColor: 'rgba(59, 130, 246, 0.85)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.35,
                    categoryPercentage: 0.7,
                    yAxisID: 'y'
                },
                {
                    label: '👥 Cantidad de Trabajadores',
                    data: empleados,
                    backgroundColor: 'rgba(74, 222, 128, 0.85)',
                    borderColor: '#4ade80',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.35,
                    categoryPercentage: 0.7,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#ffffff',
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#ffffff',
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyColor: '#e2e8f0',
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: 'rgba(255,255,255,0.7)', maxRotation: 45, minRotation: 45 },
                    title: { display: true, text: '📆 Período', color: 'rgba(255,255,255,0.5)' }
                },
                y: {
                    position: 'left',
                    grid: { color: 'rgba(255,255,255,0.08)' },
                    ticks: { 
                        color: '#3b82f6', 
                        callback: function(v) { 
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v); 
                        } 
                    },
                    title: { display: true, text: '💰 Importe Neto', color: '#3b82f6' }
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    ticks: { 
                        color: '#4ade80', 
                        callback: function(v) { return v + ' 👥'; },
                        stepSize: 1
                    },
                    title: { display: true, text: '👥 Trabajadores', color: '#4ade80' }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
    });
    
    var resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() { 
            if (chartInstance) {
                chartInstance.resize(); 
                chartInstance.update(); 
            }
        }, 250);
    });
});

function exportarGrafico() {
    var canvas = document.getElementById('nominasChart');
    if (!canvas) {
        Swal.fire('Error', 'No se encontró el gráfico para exportar', 'error');
        return;
    }
    
    var ahora = new Date();
    var añoActual = ahora.getFullYear();
    var mesActual = ahora.toLocaleString('es-ES', { month: 'long' });
    var diaActual = ahora.getDate();
    var fechaCompleta = diaActual + ' de ' + mesActual + ' de ' + añoActual;
    
    var tituloElement = document.querySelector('.glass-card h6');
    var titulo = tituloElement ? tituloElement.textContent.trim() : 'Evolución de Nóminas por Mes';
    
    var ancho = canvas.width;
    var altoOriginal = canvas.height;
    var alturaTitulo = 85;
    var altoTotal = altoOriginal + alturaTitulo;
    
    var tempCanvas = document.createElement('canvas');
    tempCanvas.width = ancho;
    tempCanvas.height = altoTotal;
    var ctx = tempCanvas.getContext('2d');
    
    var gradiente = ctx.createLinearGradient(0, 0, 0, altoTotal);
    gradiente.addColorStop(0, '#1a1a2e');
    gradiente.addColorStop(1, '#0f0f1a');
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo, ancho / 2, 14);
    
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '14px Inter, Segoe UI, sans-serif';
    ctx.fillText('📅 Generado el ' + fechaCompleta, ancho / 2, 48);
    
    ctx.strokeStyle = 'rgba(96, 165, 250, 0.25)';
    ctx.lineWidth = 1.5;
    ctx.shadowColor = 'transparent';
    ctx.beginPath();
    var lineaAncho = 180;
    ctx.moveTo(ancho / 2 - lineaAncho, 72);
    ctx.lineTo(ancho / 2 + lineaAncho, 72);
    ctx.stroke();
    
    var link = document.createElement('a');
    link.download = 'grafico_nominas_' + añoActual + '-' + String(ahora.getMonth()+1).padStart(2,'0') + '-' + String(ahora.getDate()).padStart(2,'0') + '.png';
    link.href = tempCanvas.toDataURL('image/png', 1.0);
    link.click();
}
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.chart-container');
    if (container) {
        container.innerHTML = '<div class="text-center py-5"><i class="fas fa-chart-simple fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i><h5 class="mb-2">No hay datos disponibles</h5><p class="mb-0" style="color: rgba(255,255,255,0.5);">Genera nóminas contabilizadas para visualizar estadísticas</p></div>';
    }
});
<?php endif; ?>

// ============================================
// GRÁFICO DE TORTA
// ============================================
<?php if (!empty($distribucion_tipos)): ?>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('tipoChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var labels = <?php echo json_encode($labels_torta); ?>;
    var data = <?php echo json_encode($data_torta); ?>;
    var colors = ['#3b82f6', '#fbbf24', '#4ade80', '#a78bfa', '#f87171'];
    var total = data.reduce(function(a, b) { return a + b; }, 0);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels.map(function(label, i) {
                var value = data[i];
                var percentage = ((value / total) * 100).toFixed(1);
                return label + ': ' + value + ' (' + percentage + '%)';
            }),
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                borderColor: '#1a1a2e',
                borderWidth: 3,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            cutout: '60%'
        }
    });
});
<?php endif; ?>

// ============================================
// GRÁFICO DE BARRAS - DISTRIBUCIÓN DE MONTOS
// ============================================
<?php if (!empty($montos_distrib)): ?>
var distribucionChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('distribucionChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var meses = <?php echo json_encode($meses_distrib_array); ?>;
    var montos = <?php echo json_encode($montos_distrib_array); ?>;
    
    var gradient = ctx.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(251, 191, 36, 0.9)');
    gradient.addColorStop(0.5, 'rgba(245, 158, 11, 0.8)');
    gradient.addColorStop(1, 'rgba(217, 119, 6, 0.6)');
    
    distribucionChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: '💰 Importe de Distribución',
                data: montos,
                backgroundColor: gradient,
                borderColor: '#fbbf24',
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: '#fbbf24',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: 'rgba(255,255,255,0.7)' },
                    title: { display: true, text: '📆 Mes', color: 'rgba(255,255,255,0.5)' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.08)' },
                    ticks: { 
                        color: '#fbbf24',
                        callback: function(v) {
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v);
                        }
                    },
                    title: { display: true, text: '💰 Monto', color: '#fbbf24' }
                }
            }
        }
    });
});
<?php endif; ?>


// ============================================
// GRÁFICO DE DISTRIBUCIÓN POR CENTROS DE COSTO
// ============================================
<?php if (!empty($centros_labels)): ?>
var centrosChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('centrosCostoChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var labels = <?php echo json_encode($centros_labels); ?>;
    var salarios = <?php echo json_encode($centros_salarios); ?>;
    var empleados = <?php echo json_encode($centros_empleados); ?>;
    var colores = <?php echo json_encode($centros_colores); ?>;
    
    var hayDatos = salarios.some(function(s) { return s > 0; });
    
    if (!hayDatos) {
        canvas.style.display = 'none';
        var container = canvas.parentElement;
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-building fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
                    <h5 class="mb-2">No hay datos disponibles</h5>
                    <p class="mb-0" style="color: rgba(255,255,255,0.5);">Genera nóminas para visualizar la distribución por centros de costo</p>
                </div>
            `;
        }
        return;
    }
    
    // Crear gráfico de barras agrupadas
    centrosChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '💰 Total Salario',
                    data: salarios,
                    backgroundColor: colores.map(function(c) { return c + 'CC'; }),
                    borderColor: colores,
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.35,
                    categoryPercentage: 0.7,
                    yAxisID: 'y',
                    order: 1,
                },
                {
                    label: '👥 Cantidad de Empleados',
                    data: empleados,
                    backgroundColor: 'rgba(74, 222, 128, 0.85)',
                    borderColor: '#4ade80',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.35,
                    categoryPercentage: 0.7,
                    yAxisID: 'y1',
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#ffffff',
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#ffffff',
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyColor: '#e2e8f0',
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        title: function(tooltipItems) {
                            if (!tooltipItems || tooltipItems.length === 0) return '';
                            return '🏢 ' + tooltipItems[0].label;
                        },
                        label: function(context) {
                            var label = context.dataset.label || '';
                            var value = context.raw;
                            value = Number(value);
                            if (isNaN(value)) value = 0;
                            
                            if (context.dataset.label === '💰 Total Salario') {
                                return '💰 ' + label + ': ' + new Intl.NumberFormat('es-CL', {
                                    style: 'currency',
                                    currency: 'CLP',
                                    minimumFractionDigits: 0
                                }).format(value);
                            }
                            return '👥 ' + label + ': ' + new Intl.NumberFormat('es-ES').format(value) + ' empleado' + (value !== 1 ? 's' : '');
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { 
                        color: 'rgba(255,255,255,0.7)',
                        maxRotation: 45,
                        minRotation: 45,
                        font: { size: 10 }
                    },
                    title: { display: true, text: '🏢 Centros de Costo', color: 'rgba(255,255,255,0.5)' }
                },
                y: {
                    position: 'left',
                    grid: { color: 'rgba(255,255,255,0.08)' },
                    ticks: { 
                        color: '#3b82f6', 
                        callback: function(v) { 
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v); 
                        } 
                    },
                    title: { display: true, text: '💰 Total Salario', color: '#3b82f6' }
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    ticks: { 
                        color: '#4ade80', 
                        callback: function(v) { return v + ' 👥'; },
                        stepSize: 1
                    },
                    title: { display: true, text: '👥 Empleados', color: '#4ade80' }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
    });
    
    // Resize handler
    var resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() { 
            if (centrosChartInstance) {
                centrosChartInstance.resize(); 
                centrosChartInstance.update(); 
            }
        }, 250);
    });
});

// ============================================
// EXPORTAR GRÁFICO DE CENTROS DE COSTO A PNG
// ============================================
function exportarCentrosCosto() {
    var canvas = document.getElementById('centrosCostoChart');
    if (!canvas) {
        Swal.fire('Error', 'No se encontró el gráfico para exportar', 'error');
        return;
    }
    
    var ahora = new Date();
    var añoActual = ahora.getFullYear();
    var mesActual = ahora.toLocaleString('es-ES', { month: 'long' });
    var diaActual = ahora.getDate();
    var fechaCompleta = diaActual + ' de ' + mesActual + ' de ' + añoActual;
    
    var titulo = 'Distribución por Centros de Costo';
    var subtitulo = '<?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>';
    var periodo = 'Período: <?php echo htmlspecialchars($periodo_referencia); ?>';
    
    var ancho = canvas.width;
    var altoOriginal = canvas.height;
    var alturaTitulo = 100;
    var altoTotal = altoOriginal + alturaTitulo;
    
    var tempCanvas = document.createElement('canvas');
    tempCanvas.width = ancho;
    tempCanvas.height = altoTotal;
    var ctx = tempCanvas.getContext('2d');
    
    // Fondo oscuro
    var gradiente = ctx.createLinearGradient(0, 0, 0, altoTotal);
    gradiente.addColorStop(0, '#1a1a2e');
    gradiente.addColorStop(1, '#0f0f1a');
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // Dibujar gráfico
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    // Título
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 22px Inter, Segoe UI, sans-serif';
    ctx.fillText(subtitulo, ancho / 2, 10);
    
    // Subtítulo
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '16px Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo, ancho / 2, 40);
    
    // Período
    ctx.fillStyle = '#60a5fa';
    ctx.font = '13px Inter, Segoe UI, sans-serif';
    ctx.fillText('📅 ' + periodo, ancho / 2, 64);
    
    // Fecha de generación
    ctx.fillStyle = '#6b7280';
    ctx.font = '11px Inter, Segoe UI, sans-serif';
    ctx.fillText('Generado: ' + fechaCompleta, ancho / 2, 86);
    
    // Línea decorativa
    ctx.strokeStyle = 'rgba(96, 165, 250, 0.25)';
    ctx.lineWidth = 1.5;
    ctx.shadowColor = 'transparent';
    ctx.beginPath();
    ctx.moveTo(ancho / 2 - 180, 98);
    ctx.lineTo(ancho / 2 + 180, 98);
    ctx.stroke();
    
    // Descargar
    var link = document.createElement('a');
    link.download = 'centros_costo_' + añoActual + '-' + String(ahora.getMonth()+1).padStart(2,'0') + '-' + String(ahora.getDate()).padStart(2,'0') + '.png';
    link.href = tempCanvas.toDataURL('image/png', 1.0);
    link.click();
}
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('centrosCostoChart');
    if (canvas) {
        var container = canvas.parentElement;
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-building fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
                    <h5 class="mb-2">No hay datos disponibles</h5>
                    <p class="mb-0" style="color: rgba(255,255,255,0.5);">Genera nóminas para visualizar la distribución por centros de costo</p>
                </div>
            `;
        }
    }
});
<?php endif; ?>


// ============================================
// EXPORTAR TABLA A EXCEL CON EXCELJS (FORMATO PROFESIONAL)
// ============================================
function exportarTablaExcel() {
    // Mostrar loading
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Excel...',
        text: 'Por favor espere, procesando los datos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    // Obtener datos de la tabla (con filtros aplicados)
    var table = $('#nominasTable').DataTable();
    var data = table.rows({ search: 'applied' }).data();
    
    if (data.length === 0) {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-info-circle me-2" style="color: #fbbf24;"></i> Sin datos',
            text: 'No hay datos para exportar con los filtros aplicados',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    // Crear libro de trabajo
    var workbook = new ExcelJS.Workbook();
    workbook.creator = CONFIG.nombreEmpresa;
    workbook.created = new Date();
    
    // Agregar hoja
    var worksheet = workbook.addWorksheet('Reporte Nóminas', {
        properties: { tabColor: { argb: 'FF1A56DB' } },
        pageSetup: { orientation: 'landscape', fitToPage: true, margins: {
            left: 0.7, right: 0.7, top: 0.7, bottom: 0.7, header: 0.3, footer: 0.3
        }}
    });
    
    // ============================================
    // 1. TÍTULO Y SUBTÍTULO
    // ============================================
    var rowIndex = 1;
    
    // Título de la empresa
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = CONFIG.nombreEmpresa;
    row.getCell(1).font = { name: 'Arial', size: 18, bold: true, color: { argb: 'FF1A56DB' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    row.height = 30;
    worksheet.mergeCells(rowIndex, 1, rowIndex, 7);
    rowIndex++;
    
    // Subtítulo
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Reporte de Nóminas Generadas';
    row.getCell(1).font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FF374151' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    row.height = 25;
    worksheet.mergeCells(rowIndex, 1, rowIndex, 7);
    rowIndex++;
    
    // Fila vacía
    rowIndex++;
    
    // Fecha de generación
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    var horaStr = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false });
    
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Generado el: ' + fechaStr + ' - ' + horaStr;
    row.getCell(1).font = { name: 'Arial', size: 10, color: { argb: 'FF6B7280' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 7);
    rowIndex++;
    
    // Fila vacía
    rowIndex++;
    
    // ============================================
    // 2. ENCABEZADOS DE COLUMNAS
    // ============================================
    var headerRow = rowIndex;
    var headers = ['Período', 'Tipo de Nómina', 'Empleados', 'Total Devengado', 'Total Deducciones', 'Total Neto', 'Estado'];
    var headerCols = [1, 2, 3, 4, 5, 6, 7];
    
    for (var i = 0; i < headers.length; i++) {
        var cell = worksheet.getCell(headerRow, headerCols[i]);
        cell.value = headers[i];
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1A56DB' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
        cell.border = {
            top: { style: 'thin', color: { argb: 'FF1A56DB' } },
            bottom: { style: 'thin', color: { argb: 'FF1A56DB' } },
            left: { style: 'thin', color: { argb: 'FF1A56DB' } },
            right: { style: 'thin', color: { argb: 'FF1A56DB' } }
        };
    }
    rowIndex++;
    
    // ============================================
    // 3. DATOS DE LA TABLA
    // ============================================
    var totalEmpleados = 0;
    var totalDevengado = 0;
    var totalDeducciones = 0;
    var totalNeto = 0;
    
    // Anchos de columna
    worksheet.getColumn(1).width = 18;  // Período
    worksheet.getColumn(2).width = 22;  // Tipo
    worksheet.getColumn(3).width = 14;  // Empleados
    worksheet.getColumn(4).width = 20;  // Devengado
    worksheet.getColumn(5).width = 20;  // Deducciones
    worksheet.getColumn(6).width = 20;  // Neto
    worksheet.getColumn(7).width = 18;  // Estado
    
    data.each(function(row) {
        var periodo = row[1] ? row[1].replace(/<[^>]*>/g, '').trim() : '';
        var tipo = row[2] ? row[2].replace(/<[^>]*>/g, '').trim() : '';
        var empleados = row[3] ? parseInt(row[3].replace(/,/g, '')) || 0 : 0;
        var devengado = row[4] ? parseFloat(row[4].replace(/[$,]/g, '')) || 0 : 0;
        var deducciones = row[5] ? parseFloat(row[5].replace(/[$,]/g, '')) || 0 : 0;
        var neto = row[6] ? parseFloat(row[6].replace(/[$,]/g, '')) || 0 : 0;
        var estado = row[7] ? row[7].replace(/<[^>]*>/g, '').trim() : '';
        
        totalEmpleados += empleados;
        totalDevengado += devengado;
        totalDeducciones += deducciones;
        totalNeto += neto;
        
        // Determinar color de fondo para filas alternas
        var fillColor = (rowIndex % 2 === 0) ? 'FFF3F4F6' : 'FFFFFFFF';
        
        var cells = [periodo, tipo, empleados, devengado, deducciones, neto, estado];
        var colAligns = ['left', 'left', 'center', 'right', 'right', 'right', 'center'];
        
        for (var i = 0; i < cells.length; i++) {
            var cell = worksheet.getCell(rowIndex, headerCols[i]);
            cell.value = cells[i];
            
            // Formato de números
            if (i === 2 && typeof cells[i] === 'number') {
                cell.numFmt = '#,##0';
            }
            if (i >= 3 && i <= 5 && typeof cells[i] === 'number') {
                cell.numFmt = '#,##0.00';
            }
            
            cell.font = { name: 'Arial', size: 10 };
            cell.alignment = { horizontal: colAligns[i], vertical: 'middle' };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillColor } };
            cell.border = {
                top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
            };
        }
        rowIndex++;
    });
    
    // ============================================
    // 4. FILA DE TOTALES
    // ============================================
    var totalRow = rowIndex;
    var totalCells = ['TOTALES GENERALES', '', totalEmpleados, totalDevengado, totalDeducciones, totalNeto, ''];
    var colAlignsTotal = ['left', 'center', 'center', 'right', 'right', 'right', 'center'];
    
    for (var i = 0; i < totalCells.length; i++) {
        var cell = worksheet.getCell(totalRow, headerCols[i]);
        cell.value = totalCells[i];
        
        // Formato de números
        if (i === 2 && typeof totalCells[i] === 'number') {
            cell.numFmt = '#,##0';
        }
        if (i >= 3 && i <= 5 && typeof totalCells[i] === 'number') {
            cell.numFmt = '#,##0.00';
        }
        
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FF000000' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD1D5DB' } };
        cell.alignment = { horizontal: colAlignsTotal[i], vertical: 'middle' };
        cell.border = {
            top: { style: 'medium', color: { argb: 'FF000000' } },
            bottom: { style: 'medium', color: { argb: 'FF000000' } },
            left: { style: 'thin', color: { argb: 'FF000000' } },
            right: { style: 'thin', color: { argb: 'FF000000' } }
        };
    }
    
    // ============================================
    // 5. GENERAR Y DESCARGAR ARCHIVO
    // ============================================
    workbook.xlsx.writeBuffer().then(function(buffer) {
        var blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        var fechaFile = ahora.toISOString().slice(0,10);
        link.download = 'Reporte_Nominas_' + fechaFile + '.xlsx';
        
        Swal.close();
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2" style="color: #4ade80;"></i> Exportación Completada',
            text: 'El archivo Excel se ha generado con formato profesional',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }).catch(function(error) {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f87171;"></i> Error',
            text: 'Error al generar el Excel: ' + error.message,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
}


// ============================================
// EXPORTAR TABLA A PDF (VERSIÓN OPTIMIZADA - HORA 12H)
// ============================================
function exportarTablaPDF() {
    // Mostrar loading
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando PDF...',
        text: 'Por favor espere, procesando los datos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    // Obtener datos de la tabla
    var table = $('#nominasTable').DataTable();
    var data = table.rows({ search: 'applied' }).data();
    
    if (data.length === 0) {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-info-circle me-2" style="color: #fbbf24;"></i> Sin datos',
            text: 'No hay datos para exportar con los filtros aplicados',
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
        return;
    }
    
    // ============================================
    // FECHA Y HORA EN FORMATO 12 HORAS
    // ============================================
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
    
    // Hora en formato 12 horas
    var horas = ahora.getHours();
    var minutos = ahora.getMinutes().toString().padStart(2, '0');
    var ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    var horaStr = horas + ':' + minutos + ' ' + ampm;
    
    // ============================================
    // CONSTRUIR HTML PARA EL PDF (TEXTO NEGRO)
    // ============================================
    var htmlContent = `
    <html>
    <head>
        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                padding: 20px;
                color: #000000 !important;
                background: #ffffff;
            }
            .titulo {
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom: 5px;
            }
            .subtitulo {
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom: 5px;
            }
            .fecha {
                text-align: center;
                font-size: 10px;
                color: #666666;
                margin-bottom: 15px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
                color: #000000 !important;
            }
            table th {
                background-color: #1A56DB;
                color: #FFFFFF !important;
                padding: 6px 8px;
                border: 1px solid #000000;
                text-align: center;
                font-weight: bold;
            }
            table td {
                padding: 5px 8px;
                border: 1px solid #000000;
                text-align: left;
                color: #000000 !important;
            }
            table tr:nth-child(even) td {
                background-color: #f5f5f5;
            }
            table tfoot td {
                background-color: #e5e7eb;
                font-weight: bold;
                padding: 6px 8px;
                border: 1px solid #000000;
                color: #000000 !important;
            }
            .text-right {
                text-align: right !important;
            }
            .text-center {
                text-align: center !important;
            }
            .text-success {
                color: #16a34a !important;
            }
            .text-danger {
                color: #dc2626 !important;
            }
            .fw-bold {
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="titulo"><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>®</div>
        <div class="subtitulo">Reporte de Nóminas Generadas</div>
        <div class="fecha">Generado: ${fechaStr} - ${horaStr}</div>
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Tipo de Nómina</th>
                    <th class="text-center">Empleados</th>
                    <th class="text-right">Total Devengado</th>
                    <th class="text-right">Total Deducciones</th>
                    <th class="text-right">Total Neto</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>`;
    
    // Variables para totales
    var totalEmpleados = 0;
    var totalDevengado = 0;
    var totalDeducciones = 0;
    var totalNeto = 0;
    
    // Agregar filas de datos
    data.each(function(row) {
        var periodo = row[1] ? row[1].replace(/<[^>]*>/g, '').trim() : '';
        var tipo = row[2] ? row[2].replace(/<[^>]*>/g, '').trim() : '';
        var empleados = row[3] ? parseInt(row[3].replace(/,/g, '')) || 0 : 0;
        var devengado = row[4] ? parseFloat(row[4].replace(/[$,]/g, '')) || 0 : 0;
        var deducciones = row[5] ? parseFloat(row[5].replace(/[$,]/g, '')) || 0 : 0;
        var neto = row[6] ? parseFloat(row[6].replace(/[$,]/g, '')) || 0 : 0;
        var estado = row[7] ? row[7].replace(/<[^>]*>/g, '').trim() : '';
        
        totalEmpleados += empleados;
        totalDevengado += devengado;
        totalDeducciones += deducciones;
        totalNeto += neto;
        
        // Formatear números
        var devengadoStr = devengado.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        var deduccionesStr = deducciones.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        var netoStr = neto.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        htmlContent += `
            <tr>
                <td>${periodo}</td>
                <td>${tipo}</td>
                <td class="text-center">${empleados}</td>
                <td class="text-right">${devengadoStr}</td>
                <td class="text-right text-danger">${deduccionesStr}</td>
                <td class="text-right text-success fw-bold">${netoStr}</td>
                <td class="text-center">${estado}</td>
            </tr>`;
    });
    
    // Totales
    var totalDevStr = totalDevengado.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var totalDedStr = totalDeducciones.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var totalNetStr = totalNeto.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    htmlContent += `
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right fw-bold">TOTALES GENERALES</td>
                    <td class="text-center fw-bold">${totalEmpleados}</td>
                    <td class="text-right fw-bold">${totalDevStr}</td>
                    <td class="text-right fw-bold">${totalDedStr}</td>
                    <td class="text-right fw-bold">${totalNetStr}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>`;
    
    // ============================================
    // GENERAR PDF CON jsPDF
    // ============================================
    var { jsPDF } = window.jspdf;
    var pdf = new jsPDF('l', 'mm', 'a4');
    
    // Configurar fuente
    pdf.setFontSize(10);
    pdf.setTextColor(0, 0, 0); // Texto negro
    
    // Usar html2canvas con escala reducida para menor tamaño
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlContent;
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px';
    tempDiv.style.top = '0';
    tempDiv.style.width = '1100px';
    tempDiv.style.background = '#ffffff';
    tempDiv.style.padding = '15px';
    document.body.appendChild(tempDiv);
    
    // Capturar con calidad reducida
    html2canvas(tempDiv, {
        scale: 1.5,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        width: 1100,
        height: tempDiv.scrollHeight,
        dpi: 150
    }).then(function(canvas) {
        var imgData = canvas.toDataURL('image/jpeg', 0.85);
        var imgWidth = 280;
        var pageHeight = 200;
        var imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        // Si la imagen es más alta que una página
        if (imgHeight > pageHeight) {
            var pageCount = Math.ceil(imgHeight / pageHeight);
            for (var i = 0; i < pageCount; i++) {
                if (i > 0) {
                    pdf.addPage();
                }
                var yPos = -(i * pageHeight);
                pdf.addImage(imgData, 'JPEG', 5, yPos, imgWidth, imgHeight);
            }
        } else {
            pdf.addImage(imgData, 'JPEG', 5, 0, imgWidth, imgHeight);
        }
        
        // Limpiar
        document.body.removeChild(tempDiv);
        Swal.close();
        
        // Descargar con compresión
        var pdfBlob = pdf.output('blob');
        var link = document.createElement('a');
        link.href = URL.createObjectURL(pdfBlob);
        link.download = 'reporte_nominas_' + new Date().toISOString().slice(0,10) + '.pdf';
        link.click();
        URL.revokeObjectURL(link.href);
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2" style="color: #4ade80;"></i> PDF Generado',
            text: 'El archivo PDF se ha generado correctamente',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }).catch(function(error) {
        document.body.removeChild(tempDiv);
        Swal.close();
        console.error('Error PDF:', error);
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f87171;"></i> Error',
            text: 'Error al generar el PDF: ' + error.message,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
}


// ============================================
// BACKUP MANUAL
// ============================================
function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: `
            <div style="text-align: left;">
                <p><i class="fas fa-info-circle me-2" style="color: #60a5fa;"></i> Se creará una copia de seguridad completa de la base de datos.</p>
                <p class="mt-2"><small class="text-muted">La copia incluirá:</small></p>
                <ul style="text-align: left;">
                    <li><i class="fas fa-users me-1"></i> Datos de empleados</li>
                    <li><i class="fas fa-calculator me-1"></i> Nóminas generadas</li>
                    <li><i class="fas fa-cog me-1"></i> Configuración del sistema</li>
                    <li><i class="fas fa-umbrella-beach me-1"></i> Historial de vacaciones</li>
                </ul>
                <div class="alert alert-info mt-2" style="background: rgba(59,130,246,0.1); border: 1px solid #3b82f6; border-radius: 8px; padding: 10px;">
                    <i class="fas fa-clock me-1"></i> El archivo se guardará con el formato: <strong>backup_YYYY-MM-DD_HH-MM-SS.sql</strong>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
                text: 'Por favor espere, esto puede tomar unos segundos',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
                background: '#1a1a2e',
                color: '#ffffff'
            });
            
            fetch('ajax/backup_db.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Backup Completado',
                        html: `
                            <p>La copia de seguridad se ha generado correctamente.</p>
                            <p><strong>Archivo:</strong> ${data.filename}</p>
                            <p><strong>Tamaño:</strong> ${data.size}</p>
                            <div class="mt-3">
                                <a href="${data.download_url}" class="btn btn-success" download>
                                    <i class="fas fa-download me-2"></i> Descargar Backup
                                </a>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error',
                        text: data.message || 'No se pudo generar el backup',
                        icon: 'error',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i> Error',
                    text: 'Error de conexión al generar el backup',
                    icon: 'error',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                    background: '#1a1a2e',
                    color: '#ffffff'
                });
            });
        }
    });
}

document.getElementById('btnBackupManual')?.addEventListener('click', (e) => {
    e.preventDefault();
    realizarBackupManual();
});
// ============================================
// EXPORTAR GRÁFICO DE TORTA A PNG (VERSIÓN MEJORADA)
// ============================================
function exportarGraficoTorta() {
    const canvas = document.getElementById('tipoChart');
    if (!canvas) {
        Swal.fire('Error', 'No se encontró el gráfico de distribución para exportar', 'error');
        return;
    }
    
    // Obtener el año y mes actual
    const ahora = new Date();
    const añoActual = ahora.getFullYear();
    const mesActual = ahora.toLocaleString('es-ES', { month: 'long' });
    const diaActual = ahora.getDate();
    const fechaCompleta = `${diaActual} de ${mesActual} de ${añoActual}`;
    
    // Obtener el título del gráfico
    const tituloElement = document.querySelector('.glass-card h6');
    const titulo = tituloElement ? tituloElement.textContent.trim() : 'Distribución de Nóminas por Tipo';
    
    // Obtener las dimensiones reales del canvas
    const anchoReal = canvas.offsetWidth || canvas.width || 400;
    const altoReal = canvas.offsetHeight || canvas.height || 300;
    
    // Asegurar que el canvas tenga el tamaño correcto temporalmente
    const escala = 2; // Para mejor resolución
    const anchoFinal = anchoReal * escala;
    const altoOriginal = altoReal * escala;
    const alturaTitulo = 85 * escala;
    const altoFinal = altoOriginal + alturaTitulo;
    
    // Crear canvas temporal con alta resolución
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = anchoFinal;
    tempCanvas.height = altoFinal;
    const ctx = tempCanvas.getContext('2d');
    
    // Escalar para alta resolución
    ctx.scale(escala, escala);
    
    // === DIBUJAR FONDO OSCURO ===
    const gradiente = ctx.createLinearGradient(0, 0, 0, altoFinal / escala);
    gradiente.addColorStop(0, '#1a1a2e');
    gradiente.addColorStop(1, '#0f0f1a');
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, anchoReal, altoFinal / escala);
    
    // === DIBUJAR EL GRÁFICO ORIGINAL ===
    ctx.drawImage(canvas, 0, alturaTitulo / escala, anchoReal, altoOriginal / escala);
    
    // === TÍTULO PRINCIPAL ===
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold ${24 / escala}px Inter, Segoe UI, sans-serif`;
    ctx.fillText(titulo, anchoReal / 2, 14 / escala);
    
    // === SUBTÍTULO CON FECHA ===
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#94a3b8';
    ctx.font = `${14 / escala}px Inter, Segoe UI, sans-serif`;
    ctx.fillText(`📅 Generado el ${fechaCompleta}`, anchoReal / 2, 48 / escala);
    
    // === LÍNEA DECORATIVA ===
    ctx.strokeStyle = 'rgba(96, 165, 250, 0.25)';
    ctx.lineWidth = 1.5 / escala;
    ctx.shadowColor = 'transparent';
    ctx.beginPath();
    const lineaAncho = 180 / escala;
    ctx.moveTo(anchoReal / 2 - lineaAncho, 72 / escala);
    ctx.lineTo(anchoReal / 2 + lineaAncho, 72 / escala);
    ctx.stroke();
    
    // === DESCARGAR ===
    const link = document.createElement('a');
    link.download = `grafico_distribucion_${añoActual}-${String(ahora.getMonth()+1).padStart(2,'0')}-${String(ahora.getDate()).padStart(2,'0')}.png`;
    link.href = tempCanvas.toDataURL('image/png', 1.0);
    link.click();
}
// ============================================
// ACTUALIZAR DASHBOARD (RECARGAR DATOS)
// ============================================
function actualizarDashboard() {
    // Mostrar animación de carga en el KPI
    var kpiElement = document.getElementById('ultimaActualizacion');
    if (kpiElement) {
        kpiElement.innerHTML = '<i class="fas fa-spinner fa-pulse me-2" style="color: #60a5fa;"></i> Actualizando...';
    }
    
    // Recargar la página completa para obtener datos frescos
    window.location.reload();
}

// Evento click en la tarjeta de "Última Actualización"
document.addEventListener('DOMContentLoaded', function() {
    var kpiActualizacion = document.getElementById('kpiActualizacion');
    if (kpiActualizacion) {
        kpiActualizacion.addEventListener('click', function(e) {
            // Evitar que el click se propague si hay otros elementos
            e.stopPropagation();
            actualizarDashboard();
        });
    }
});

// ============================================
// ACTUALIZAR RELOJ EN VIVO (HORA 12H)
// ============================================
// Función para actualizar la hora en "Última Actualización" cada minuto
function actualizarHoraUltimaActualizacion() {
    var elemento = document.getElementById('ultimaActualizacion');
    if (!elemento) return;
    
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    
    var horas = ahora.getHours();
    var minutos = ahora.getMinutes().toString().padStart(2, '0');
    var ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    
    var horaStr = horas + ':' + minutos + ' ' + ampm;
    
    // Mantener el ícono de reloj
    elemento.innerHTML = '<i class="fas fa-clock me-2" style="color: #94a3b8;"></i> ' + fechaStr + ' - ' + horaStr;
}

// Actualizar la hora cada 30 segundos
setInterval(actualizarHoraUltimaActualizacion, 30000);

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarHoraUltimaActualizacion();
});
// ============================================
// EXPORTAR DISTRIBUCIÓN A EXCEL (VERSIÓN MEJORADA CON TODOS LOS DATOS)
// ============================================
function exportarDistribucionExcel() {
    <?php if (!empty($montos_distrib)): ?>
    
    // Mostrar loading
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Excel...',
        text: 'Por favor espere, procesando los datos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    var workbook = new ExcelJS.Workbook();
    workbook.creator = CONFIG.nombreEmpresa;
    workbook.created = new Date();
    
    // === HOJA 1: DATOS COMPLETOS ===
    var worksheet = workbook.addWorksheet('Datos Completos', {
        properties: { tabColor: { argb: 'FFFBBF24' } }
    });
    
    var rowIndex = 1;
    
    // Título principal
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = CONFIG.nombreEmpresa;
    row.getCell(1).font = { name: 'Arial', size: 18, bold: true, color: { argb: 'FF1A56DB' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
    row.height = 30;
    rowIndex++;
    
    // Subtítulo
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Registro Histórico de Montos para Redistribución';
    row.getCell(1).font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FF374151' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
    row.height = 25;
    rowIndex++;
    
    // Año
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Año: <?php echo $anio_seleccionado; ?>';
    row.getCell(1).font = { name: 'Arial', size: 12, bold: true, color: { argb: 'FF1A56DB' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
    rowIndex++;
    
    // Fecha de generación
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    var horas = ahora.getHours();
    var minutos = ahora.getMinutes().toString().padStart(2, '0');
    var ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    var horaStr = horas + ':' + minutos + ' ' + ampm;
    
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Generado el: ' + fechaStr + ' - ' + horaStr;
    row.getCell(1).font = { name: 'Arial', size: 10, color: { argb: 'FF6B7280' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
    rowIndex++;
    
    // Fila vacía
    rowIndex++;
    
    // ============================================
    // ENCABEZADOS DE COLUMNAS
    // ============================================
    var headerRow = rowIndex;
    var headers = ['Mes', 'Importe de Distribución', 'Variación', '% del Total', 'Fecha Registro', 'Hora Registro'];
    var headerCols = [1, 2, 3, 4, 5, 6];
    
    for (var i = 0; i < headers.length; i++) {
        var cell = worksheet.getCell(headerRow, headerCols[i]);
        cell.value = headers[i];
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1A56DB' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
        cell.border = {
            top: { style: 'thin', color: { argb: 'FF1A56DB' } },
            bottom: { style: 'thin', color: { argb: 'FF1A56DB' } },
            left: { style: 'thin', color: { argb: 'FF1A56DB' } },
            right: { style: 'thin', color: { argb: 'FF1A56DB' } }
        };
    }
    rowIndex++;
    
    // ============================================
    // DATOS DE LA TABLA
    // ============================================
    var total_anual = <?php echo $total_anual ?? 0; ?>;
    var mes_anterior = null;
    var datosExport = [];
    
    <?php 
    $mes_anterior_val = null;
    foreach ($montos_distrib as $index => $item): 
        $importe = floatval($item['importe_dis']);
        $porcentaje = ($total_anual > 0) ? ($importe / $total_anual) * 100 : 0;
        $timestamp = strtotime($item['fecha_registro']);
        $fecha = date('d/m/Y', $timestamp);
        $hora = date('h:i A', $timestamp);
        
        // Calcular variación
        $variacion = null;
        $variacion_str = '—';
        if ($mes_anterior_val !== null) {
            $variacion = (($importe - $mes_anterior_val) / $mes_anterior_val) * 100;
            $variacion_str = ($variacion >= 0 ? '+' : '') . number_format($variacion, 1) . '%';
        }
        $mes_anterior_val = $importe;
    ?>
        var row = worksheet.getRow(rowIndex);
        row.getCell(1).value = '<?php echo ucfirst($item['mes']); ?>';
        row.getCell(2).value = <?php echo $importe; ?>;
        row.getCell(2).numFmt = '#,##0.00';
        row.getCell(3).value = '<?php echo $variacion_str; ?>';
        row.getCell(4).value = <?php echo $porcentaje; ?>;
        row.getCell(4).numFmt = '0.0"%"';
        row.getCell(5).value = '<?php echo $fecha; ?>';
        row.getCell(6).value = '<?php echo $hora; ?>';
        
        // Estilo para filas alternas
        var fillColor = (rowIndex % 2 === 0) ? 'FFF3F4F6' : 'FFFFFFFF';
        for (var c = 1; c <= 6; c++) {
            var cell = worksheet.getCell(rowIndex, c);
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillColor } };
            cell.alignment = { 
                horizontal: c === 1 || c === 5 || c === 6 ? 'left' : 'right', 
                vertical: 'middle' 
            };
            cell.border = {
                top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
            };
        }
        rowIndex++;
    <?php endforeach; ?>
    
    // ============================================
    // FILA DE TOTALES
    // ============================================
    var totalRow = rowIndex;
    var totalCells = ['TOTAL ANUAL', total_anual, '', 100, '', ''];
    var colAlignsTotal = ['left', 'right', 'right', 'right', 'left', 'left'];
    
    for (var i = 0; i < totalCells.length; i++) {
        var cell = worksheet.getCell(totalRow, headerCols[i]);
        cell.value = totalCells[i];
        
        if (i === 1 && typeof totalCells[i] === 'number') {
            cell.numFmt = '#,##0.00';
        }
        if (i === 3 && typeof totalCells[i] === 'number') {
            cell.numFmt = '0.0"%"';
        }
        
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FF000000' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD1D5DB' } };
        cell.alignment = { horizontal: colAlignsTotal[i], vertical: 'middle' };
        cell.border = {
            top: { style: 'medium', color: { argb: 'FF000000' } },
            bottom: { style: 'medium', color: { argb: 'FF000000' } },
            left: { style: 'thin', color: { argb: 'FF000000' } },
            right: { style: 'thin', color: { argb: 'FF000000' } }
        };
    }
    
    // Anchos de columna
    worksheet.getColumn(1).width = 18;
    worksheet.getColumn(2).width = 25;
    worksheet.getColumn(3).width = 18;
    worksheet.getColumn(4).width = 16;
    worksheet.getColumn(5).width = 20;
    worksheet.getColumn(6).width = 18;
    
    // ============================================
    // HOJA 2: RESUMEN ESTADÍSTICO
    // ============================================
    var sheet2 = workbook.addWorksheet('Resumen Estadístico', {
        properties: { tabColor: { argb: 'FF4ADE80' } }
    });
    
    var row = sheet2.getRow(1);
    row.getCell(1).value = 'RESUMEN ESTADÍSTICO - DISTRIBUCIÓN DE MONTOS';
    row.getCell(1).font = { name: 'Arial', size: 16, bold: true, color: { argb: 'FF1A56DB' } };
    row.getCell(1).alignment = { horizontal: 'center' };
    sheet2.mergeCells(1, 1, 1, 2);
    row.height = 30;
    
    // Datos del resumen
    var stats = [
        ['Año', '<?php echo $anio_seleccionado; ?>'],
        ['Total de Meses', '<?php echo count($montos_distrib); ?>'],
        ['Total Anual', '<?php echo formatearMoneda($total_anual); ?>'],
        ['Promedio Mensual', '<?php echo formatearMoneda($total_anual / count($montos_distrib)); ?>'],
        ['Mes Máximo', '<?php echo ucfirst($max_item['mes']) . ' - ' . formatearMoneda($max_item['importe_dis']); ?>'],
        ['Mes Mínimo', '<?php echo ucfirst($min_item['mes']) . ' - ' . formatearMoneda($min_item['importe_dis']); ?>']
    ];
    
    for (var i = 0; i < stats.length; i++) {
        var row = sheet2.getRow(i + 3);
        row.getCell(1).value = stats[i][0];
        row.getCell(1).font = { bold: true };
        row.getCell(1).alignment = { horizontal: 'left' };
        row.getCell(2).value = stats[i][1];
        row.getCell(2).alignment = { horizontal: 'left' };
    }
    
    sheet2.getColumn(1).width = 25;
    sheet2.getColumn(2).width = 35;
    
    // ============================================
    // GENERAR Y DESCARGAR
    // ============================================
    workbook.xlsx.writeBuffer().then(function(buffer) {
        var blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'Distribucion_Montos_<?php echo $anio_seleccionado; ?>_' + new Date().toISOString().slice(0,10) + '.xlsx';
        
        Swal.close();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2" style="color: #4ade80;"></i> Exportación Completada',
            text: 'El archivo Excel se ha generado con formato profesional',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
    
    <?php else: ?>
    Swal.fire('Sin datos', 'No hay registros para exportar', 'info');
    <?php endif; ?>
}

// ============================================
// EXPORTAR DISTRIBUCIÓN A PDF (VERSIÓN MEJORADA CON TODOS LOS DATOS)
// ============================================
function exportarDistribucionPDF() {
    <?php if (!empty($montos_distrib)): ?>
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando PDF...',
        text: 'Por favor espere, procesando los datos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    var horas = ahora.getHours();
    var minutos = ahora.getMinutes().toString().padStart(2, '0');
    var ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    var horaStr = horas + ':' + minutos + ' ' + ampm;
    
    var total_anual = <?php echo $total_anual ?? 0; ?>;
    
    // Construir HTML con todos los datos
    var htmlContent = `
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; color: #000000; background: #ffffff; }
            .titulo { text-align: center; font-size: 20px; font-weight: bold; color: #1A56DB; margin-bottom: 5px; }
            .subtitulo { text-align: center; font-size: 16px; font-weight: bold; color: #374151; margin-bottom: 5px; }
            .anio { text-align: center; font-size: 14px; font-weight: bold; color: #1A56DB; margin-bottom: 10px; }
            .fecha { text-align: center; font-size: 10px; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 20px; }
            th { background-color: #1A56DB; color: white; padding: 8px 10px; border: 1px solid #000; text-align: center; font-weight: bold; }
            td { padding: 6px 10px; border: 1px solid #000; text-align: center; }
            tr:nth-child(even) td { background-color: #f5f5f5; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .fw-bold { font-weight: bold; }
            .text-success { color: #16a34a; }
            .text-danger { color: #dc2626; }
            
            /* Estilos para el resumen */
            .resumen { margin-top: 20px; border-top: 2px solid #1A56DB; padding-top: 15px; }
            .resumen-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
            .resumen-item { background: #f0f4ff; padding: 10px; border-radius: 8px; text-align: center; }
            .resumen-item .label { font-size: 9px; color: #666; }
            .resumen-item .value { font-size: 14px; font-weight: bold; color: #1A56DB; }
        </style>
    </head>
    <body>
        <div class="titulo"><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?>®</div>
        <div class="subtitulo">Registro Histórico de Montos para Redistribución</div>
        <div class="anio">Año: <?php echo $anio_seleccionado; ?></div>
        <div class="fecha">Generado: ${fechaStr} - ${horaStr}</div>
        
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th class="text-right">Importe de Distribución</th>
                    <th class="text-right">Variación</th>
                    <th class="text-right">% del Total</th>
                    <th>Fecha Registro</th>
                    <th>Hora Registro</th>
                </tr>
            </thead>
            <tbody>`;
    
    <?php 
    $mes_anterior_val = null;
    foreach ($montos_distrib as $item): 
        $importe = floatval($item['importe_dis']);
        $porcentaje = ($total_anual > 0) ? ($importe / $total_anual) * 100 : 0;
        $timestamp = strtotime($item['fecha_registro']);
        $fecha = date('d/m/Y', $timestamp);
        $hora = date('h:i A', $timestamp);
        $mes = ucfirst($item['mes']);
        
        $variacion = null;
        $variacion_str = '—';
        $variacion_class = '';
        if ($mes_anterior_val !== null) {
            $variacion = (($importe - $mes_anterior_val) / $mes_anterior_val) * 100;
            $variacion_str = ($variacion >= 0 ? '+' : '') . number_format($variacion, 1) . '%';
            $variacion_class = $variacion >= 0 ? 'text-success' : 'text-danger';
        }
        $mes_anterior_val = $importe;
    ?>
        htmlContent += `
            <tr>
                <td class="text-left"><?php echo $mes; ?></td>
                <td class="text-right"><?php echo number_format($importe, 2); ?></td>
                <td class="text-right <?php echo $variacion_class; ?>"><?php echo $variacion_str; ?></td>
                <td class="text-right"><?php echo number_format($porcentaje, 1); ?>%</td>
                <td class="text-left"><?php echo $fecha; ?></td>
                <td class="text-left"><?php echo $hora; ?></td>
            </tr>`;
    <?php endforeach; ?>
    
    htmlContent += `
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right fw-bold">TOTAL ANUAL</th>
                    <th class="text-right fw-bold"><?php echo number_format($total_anual, 2); ?></th>
                    <th class="text-right fw-bold"></th>
                    <th class="text-right fw-bold">100%</th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        
        <div class="resumen">
            <div style="font-size: 14px; font-weight: bold; color: #1A56DB; text-align: center; margin-bottom: 10px;">
                📊 Resumen Estadístico
            </div>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <div class="label">Total de Meses</div>
                    <div class="value"><?php echo count($montos_distrib); ?></div>
                </div>
                <div class="resumen-item">
                    <div class="label">Promedio Mensual</div>
                    <div class="value"><?php echo formatearMoneda($total_anual / count($montos_distrib)); ?></div>
                </div>
                <div class="resumen-item">
                    <div class="label">Mes Máximo</div>
                    <div class="value"><?php echo ucfirst($max_item['mes']) . ' ' . formatearMoneda($max_item['importe_dis']); ?></div>
                </div>
                <div class="resumen-item">
                    <div class="label">Mes Mínimo</div>
                    <div class="value"><?php echo ucfirst($min_item['mes']) . ' ' . formatearMoneda($min_item['importe_dis']); ?></div>
                </div>
            </div>
        </div>
    </body>
    </html>`;
    
    // Crear PDF
    var { jsPDF } = window.jspdf;
    var pdf = new jsPDF('l', 'mm', 'a4');
    
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlContent;
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px';
    tempDiv.style.top = '0';
    tempDiv.style.width = '1100px';
    tempDiv.style.background = '#ffffff';
    tempDiv.style.padding = '20px';
    document.body.appendChild(tempDiv);
    
    html2canvas(tempDiv, {
        scale: 1.5,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        dpi: 150
    }).then(function(canvas) {
        var imgData = canvas.toDataURL('image/jpeg', 0.85);
        var imgWidth = 280;
        var imgHeight = (canvas.height * imgWidth) / canvas.width;
        var pageHeight = 200;
        var heightLeft = imgHeight;
        var position = 0;
        
        if (imgHeight > pageHeight) {
            var pageCount = Math.ceil(imgHeight / pageHeight);
            for (var i = 0; i < pageCount; i++) {
                if (i > 0) {
                    pdf.addPage();
                }
                var yPos = -(i * pageHeight);
                pdf.addImage(imgData, 'JPEG', 5, yPos, imgWidth, imgHeight);
            }
        } else {
            pdf.addImage(imgData, 'JPEG', 5, 0, imgWidth, imgHeight);
        }
        
        document.body.removeChild(tempDiv);
        Swal.close();
        
        pdf.save('distribucion_montos_<?php echo $anio_seleccionado; ?>_' + new Date().toISOString().slice(0,10) + '.pdf');
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2" style="color: #4ade80;"></i> PDF Generado',
            text: 'El archivo PDF se ha generado correctamente',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    }).catch(function(error) {
        document.body.removeChild(tempDiv);
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f87171;"></i> Error',
            text: 'Error al generar el PDF: ' + error.message,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
    
    <?php else: ?>
    Swal.fire('Sin datos', 'No hay registros para exportar', 'info');
    <?php endif; ?>
}
// ============================================
// EXPORTAR DISTRIBUCIÓN A PNG (VERSIÓN MEJORADA)
// ============================================
function exportarDistribucionPNG() {
    const canvas = document.getElementById('distribucionChart');
    if (!canvas) {
        Swal.fire('Error', 'No se encontró el gráfico para exportar', 'error');
        return;
    }
    
    // Obtener el año y mes actual
    const ahora = new Date();
    const añoActual = ahora.getFullYear();
    const mesActual = ahora.toLocaleString('es-ES', { month: 'long' });
    const diaActual = ahora.getDate();
    const fechaCompleta = `${diaActual} de ${mesActual} de ${añoActual}`;
    
    // Obtener el título del gráfico
    const titulo = 'Registro Histórico de Montos para Redistribución';
    
    // Crear canvas temporal con dimensiones extendidas
    const ancho = canvas.width;
    const altoOriginal = canvas.height;
    const alturaTitulo = 70;
    const altoTotal = altoOriginal + alturaTitulo;
    
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = ancho;
    tempCanvas.height = altoTotal;
    const ctx = tempCanvas.getContext('2d');
    
    // === DIBUJAR FONDO OSCURO ===
    const gradiente = ctx.createLinearGradient(0, 0, 0, altoTotal);
    gradiente.addColorStop(0, '#1a1a2e');
    gradiente.addColorStop(1, '#0f0f1a');
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // === DIBUJAR EL GRÁFICO ORIGINAL ===
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    // === TÍTULO PRINCIPAL ===
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    
    ctx.fillStyle = '#fbbf24';
    ctx.font = 'bold 20px Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo + ' - <?php echo $anio_seleccionado; ?>', ancho / 2, 10);
    
    // === SUBTÍTULO CON FECHA ===
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '12px Inter, Segoe UI, sans-serif';
    ctx.fillText(`📅 Generado: ${fechaCompleta}`, ancho / 2, 42);
    
    // === LÍNEA DECORATIVA ===
    ctx.strokeStyle = 'rgba(251, 191, 36, 0.3)';
    ctx.lineWidth = 1;
    ctx.shadowColor = 'transparent';
    ctx.beginPath();
    ctx.moveTo(ancho / 2 - 200, 62);
    ctx.lineTo(ancho / 2 + 200, 62);
    ctx.stroke();
    
    // === DESCARGAR ===
    const link = document.createElement('a');
    link.download = `distribucion_montos_<?php echo $anio_seleccionado; ?>_${String(ahora.getMonth()+1).padStart(2,'0')}-${String(ahora.getDate()).padStart(2,'0')}.png`;
    link.href = tempCanvas.toDataURL('image/png', 1.0);
    link.click();
}
// ============================================
// EXPORTAR CENTROS DE COSTO A EXCEL
// ============================================
function exportarCentrosCostoExcel() {
    <?php if (!empty($centros_costo_data)): ?>
    
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Excel...',
        text: 'Por favor espere, procesando los datos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        background: '#1a1a2e',
        color: '#ffffff'
    });
    
    var workbook = new ExcelJS.Workbook();
    workbook.creator = CONFIG.nombreEmpresa;
    workbook.created = new Date();
    
    var worksheet = workbook.addWorksheet('Centros de Costo', {
        properties: { tabColor: { argb: 'FF3B82F6' } }
    });
    
    var rowIndex = 1;
    
    // Título
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = CONFIG.nombreEmpresa;
    row.getCell(1).font = { name: 'Arial', size: 18, bold: true, color: { argb: 'FF1A56DB' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 4);
    row.height = 30;
    rowIndex++;
    
    // Subtítulo
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Distribución por Centros de Costo';
    row.getCell(1).font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FF374151' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 4);
    rowIndex++;
    
    // Período de referencia
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Período de referencia: <?php echo htmlspecialchars($periodo_referencia); ?>';
    row.getCell(1).font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FF3B82F6' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 4);
    rowIndex++;
    
    // Fecha y hora de generación
    var ahora = new Date();
    var fechaStr = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    var horas = ahora.getHours();
    var minutos = ahora.getMinutes().toString().padStart(2, '0');
    var ampm = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12;
    horas = horas ? horas : 12;
    var horaStr = horas + ':' + minutos + ' ' + ampm;
    
    var row = worksheet.getRow(rowIndex);
    row.getCell(1).value = 'Generado el: ' + fechaStr + ' - ' + horaStr;
    row.getCell(1).font = { name: 'Arial', size: 10, color: { argb: 'FF6B7280' } };
    row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
    worksheet.mergeCells(rowIndex, 1, rowIndex, 4);
    rowIndex++;
    
    rowIndex++;
    
    // Encabezados
    var headerRow = rowIndex;
    var headers = ['Centro de Costo', 'Empleados', 'Total Salario', '% del Total'];
    var headerCols = [1, 2, 3, 4];
    
    for (var i = 0; i < headers.length; i++) {
        var cell = worksheet.getCell(headerRow, headerCols[i]);
        cell.value = headers[i];
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1A56DB' } };
        cell.alignment = { horizontal: i === 0 ? 'center' : 'right', vertical: 'middle' };
        cell.border = {
            top: { style: 'thin', color: { argb: 'FF1A56DB' } },
            bottom: { style: 'thin', color: { argb: 'FF1A56DB' } },
            left: { style: 'thin', color: { argb: 'FF1A56DB' } },
            right: { style: 'thin', color: { argb: 'FF1A56DB' } }
        };
    }
    rowIndex++;
    
    // Datos
    var totalEmpleados = 0;
    var totalSalario = 0;
    
    <?php foreach ($centros_costo_data as $item): 
        $salario = floatval($item['total_salario']);
        $empleados = intval($item['total_empleados']);
        $nombre = addslashes($item['nombre']);
    ?>
        var row = worksheet.getRow(rowIndex);
        row.getCell(1).value = '<?php echo $nombre; ?>';
        row.getCell(2).value = <?php echo $empleados; ?>;
        row.getCell(2).numFmt = '#,##0';
        row.getCell(3).value = <?php echo $salario; ?>;
        row.getCell(3).numFmt = '#,##0.00';
        row.getCell(4).value = 0; // Se calculará después
        row.getCell(4).numFmt = '0.0"%"';
        
        totalEmpleados += <?php echo $empleados; ?>;
        totalSalario += <?php echo $salario; ?>;
        
        var fillColor = (rowIndex % 2 === 0) ? 'FFF3F4F6' : 'FFFFFFFF';
        for (var c = 1; c <= 4; c++) {
            var cell = worksheet.getCell(rowIndex, c);
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillColor } };
            cell.alignment = { horizontal: c === 1 ? 'left' : 'right', vertical: 'middle' };
            cell.border = {
                top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
            };
        }
        rowIndex++;
    <?php endforeach; ?>
    
    // Totales
    var totalRow = rowIndex;
    var cells = ['TOTAL GENERAL', totalEmpleados, totalSalario, '100%'];
    var colAlignsTotal = ['left', 'right', 'right', 'right'];
    
    for (var i = 0; i < cells.length; i++) {
        var cell = worksheet.getCell(totalRow, i + 1);
        cell.value = cells[i];
        if (i === 2 && typeof cells[i] === 'number') {
            cell.numFmt = '#,##0.00';
        }
        if (i === 1 && typeof cells[i] === 'number') {
            cell.numFmt = '#,##0';
        }
        cell.font = { name: 'Arial', size: 11, bold: true, color: { argb: 'FF000000' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD1D5DB' } };
        cell.alignment = { horizontal: colAlignsTotal[i], vertical: 'middle' };
        cell.border = {
            top: { style: 'medium', color: { argb: 'FF000000' } },
            bottom: { style: 'medium', color: { argb: 'FF000000' } },
            left: { style: 'thin', color: { argb: 'FF000000' } },
            right: { style: 'thin', color: { argb: 'FF000000' } }
        };
    }
    
    // Calcular y actualizar porcentajes (después de tener totalSalario)
    var dataStartRow = headerRow + 1;
    var dataEndRow = totalRow - 1;
    for (var r = dataStartRow; r <= dataEndRow; r++) {
        var salario = worksheet.getCell(r, 3).value;
        if (typeof salario === 'number' && totalSalario > 0) {
            var porcentaje = (salario / totalSalario) * 100;
            worksheet.getCell(r, 4).value = porcentaje;
        }
    }
    
    worksheet.getColumn(1).width = 40;
    worksheet.getColumn(2).width = 18;
    worksheet.getColumn(3).width = 25;
    worksheet.getColumn(4).width = 18;
    
    workbook.xlsx.writeBuffer().then(function(buffer) {
        var blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'Centros_Costo_' + new Date().toISOString().slice(0,10) + '.xlsx';
        
        Swal.close();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        Swal.fire({
            title: '<i class="fas fa-check-circle me-2" style="color: #4ade80;"></i> Exportación Completada',
            text: 'El archivo Excel se ha generado correctamente',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
    
    <?php else: ?>
    Swal.fire('Sin datos', 'No hay registros para exportar', 'info');
    <?php endif; ?>
}

// ============================================
// TODOS LOS CUMPLEAÑOS (CARRUSEL + LISTA)
// ============================================
var cumpleaneros = <?php echo json_encode($cumpleaneros); ?>;
var currentIndex = 0;
var cumpleContainer = document.getElementById('cumpleContent');
var cumpleCounter = document.getElementById('cumpleCounter');
var listaContainer = document.getElementById('listaCumpleaneros');

function renderCumpleanero(index) {
    if (!cumpleContainer) return;
    if (!cumpleaneros || cumpleaneros.length === 0) {
        cumpleContainer.innerHTML = `
            <div class="py-5">
                <i class="fas fa-birthday-cake fa-4x mb-3" style="color: rgba(255,255,255,0.2);"></i>
                <h5 class="mb-2">No hay trabajadores con CI válido</h5>
                <p class="mb-0" style="color: rgba(255,255,255,0.5);">Asegúrate de que los CI tengan 11 dígitos</p>
            </div>
        `;
        if (cumpleCounter) cumpleCounter.textContent = '0 de 0';
        if (listaContainer) listaContainer.innerHTML = '<div class="text-center py-3" style="color: rgba(255,255,255,0.3);">Sin datos</div>';
        return;
    }

    var total = cumpleaneros.length;
    if (cumpleCounter) cumpleCounter.textContent = (index + 1) + ' de ' + total;

    var item = cumpleaneros[index];
    
    // --- CARRUSEL ---
    // Usar exactamente la ruta de foto_ruta
    var fotoSrc = item.foto && item.foto.trim() !== '' ? item.foto.trim() : null;
    // Si la ruta no comienza con http, asumimos que es relativa al directorio raíz del proyecto
    // Pero no añadimos prefijos, usamos tal cual.
var fotoHtml = fotoSrc
    ? `<img src="${fotoSrc}" alt="${item.nombre}" class="rounded-circle" style="width: 200px; height: 200px; object-fit: cover; border: 3px solid rgba(255,255,255,0.15);" ...>`
    : `<i class="fas fa-birthday-cake" style="font-size: 120px; color: #fbbf24;"></i>`;

    var html = `
        <div class="d-flex flex-column align-items-center">
            <div class="mb-3">
                ${fotoHtml}
            </div>
            <h4 class="mb-1" style="cursor: pointer; color: #60a5fa;" onclick="abrirEmpleado(${item.id})">${item.nombre}</h4>
            <p class="mb-0" style="color: rgba(255,255,255,0.6);">
                <i class="fas fa-calendar-alt me-1"></i> ${item.fecha}
            </p>
            <p class="mb-2" style="color: #fbbf24; font-weight: 600;">
                <i class="fas fa-star me-1"></i> Cumple ${item.edad} año${item.edad !== 1 ? 's' : ''}
                <span class="badge bg-success ms-2">${item.dias} día${item.dias !== 1 ? 's' : ''}</span>
            </p>
            <div class="d-flex gap-3 mt-2">
                <button class="btn-win btn-win-sm" id="prevCumple" ${index === 0 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn-win btn-win-sm" id="nextCumple" ${index === total - 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;

    cumpleContainer.innerHTML = html;

    document.getElementById('prevCumple')?.addEventListener('click', function() {
        if (currentIndex > 0) {
            currentIndex--;
            renderCumpleanero(currentIndex);
            renderLista(); // actualizar lista también
        }
    });
    document.getElementById('nextCumple')?.addEventListener('click', function() {
        if (currentIndex < cumpleaneros.length - 1) {
            currentIndex++;
            renderCumpleanero(currentIndex);
            renderLista();
        }
    });

    // --- LISTA DE LOS 10 MÁS PRÓXIMOS ---
    renderLista();
}

function renderLista() {
    if (!listaContainer) return;
    if (!cumpleaneros || cumpleaneros.length === 0) {
        listaContainer.innerHTML = '<div class="text-center py-3" style="color: rgba(255,255,255,0.3);">Sin datos</div>';
        return;
    }

    // Tomar los 10 primeros (ya ordenados por días)
    var top10 = cumpleaneros.slice(0, 10);
    var html = '';
    top10.forEach(function(emp, idx) {
        var fotoMini = emp.foto && emp.foto.trim() !== '' 
            ? `<img src="${emp.foto.trim()}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1);" onerror="this.onerror=null; this.outerHTML='<i class=\\'fas fa-birthday-cake\\' style=\\'font-size: 18px; color: #fbbf24; width: 50px; text-align: center;\\'></i>';">`
            : `<i class="fas fa-birthday-cake" style="font-size: 30px; color: #fbbf24; width: 50px; text-align: center;"></i>`;
        
        var activeClass = (idx === currentIndex) ? 'border-left border-primary' : '';
        html += `
            <div class="d-flex align-items-center gap-2 p-1 mb-1 rounded ${activeClass}" style="cursor: pointer; background: ${idx === currentIndex ? 'rgba(96,165,250,0.15)' : 'transparent'}; transition: background 0.2s;" onclick="irACumpleanero(${idx})">
                <div style="flex-shrink: 0;">${fotoMini}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size: 1rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; color: #60a5fa;" onclick="abrirEmpleado(${emp.id})">${emp.nombre}</div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.5); display: flex; gap: 8px;">
                        <span><i class="fas fa-calendar-alt me-1"></i>${emp.fecha}</span>
                        <span style="color: #fbbf24;">${emp.edad} años</span>
                        <span class="badge bg-success" style="font-size: 0.8rem;">${emp.dias}d</span>
                    </div>
                </div>
            </div>
        `;
    });
    listaContainer.innerHTML = html;
}

function irACumpleanero(index) {
    if (index >= 0 && index < cumpleaneros.length) {
        currentIndex = index;
        renderCumpleanero(currentIndex);
        // La lista se actualiza dentro de renderCumpleanero
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    renderCumpleanero(0);
});
function abrirEmpleado(id) {
    // Redirigir a empleados.php con el parámetro editar
    window.location.href = 'modules/empleados.php?editar=' + id;
}
</script>

<?php
// Helper functions
if (!function_exists('formatearMoneda')) {
    function formatearMoneda($valor) {
        return '$' . number_format(floatval($valor), 0, '.', ',');
    }
}
if (!function_exists('nombreMesEspanol')) {
    function nombreMesEspanol($mes) {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        // Asegurar que $mes sea entero
        $mesInt = intval($mes);
        return $meses[$mesInt] ?? 'Mes inválido';
    }
}
?>
</body>
</html>