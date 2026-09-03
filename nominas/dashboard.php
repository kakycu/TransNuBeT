<?php
// Si no existe la configuración del sistema, avisar y redirigir al instalador
if (!file_exists(__DIR__ . '/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
        <title>Sistema sin configurar</title>
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script src="js/sweetalert2.all.min.js"></script>
        <style>
            body {
                margin:0;
                padding:0;
                background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height:100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-cogs" style="color:#f87171"></i> Configuración no encontrada',
            html: 'No existe la <b>configuración del sistema</b>.<br>Deberá reinstalar la base de datos y sus archivos.',
            confirmButtonText: '<i class="fas fa-cogs"></i> Ir al Instalador',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6',
            allowOutsideClick: false,
            backdrop: 'rgba(0,0,0,0.85)'
        }).then(function (result) {
            if (result.isConfirmed) {
                var w = window.open('../InstalarBD/InstalarBD.php', '_blank');
                if (!w) { window.location.href = '../InstalarBD/InstalarBD.php'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// index.php - Dashboard estilo Windows 11 CON GRÁFICO DE BARRAS AGRUPADAS Y TODAS LAS MEJORAS
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funciones.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Control de acceso por rol
if (!permiso_puede('dashboard', 'ver')) {
    permiso_denegar_acceso('Dashboard');
}

// Datos del usuario
$user_nombre_completo = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? '';
$user_ci = $_SESSION['user_ci'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Solicitud de cambio de contraseña pendiente (reset_token con valor en la BD)
$pwd_reset_pendiente = false;
$user_id_dash = intval($_SESSION['user_id'] ?? 0);
if ($user_id_dash > 0) {
    try {
        $stmt = $pdo->prepare("SELECT reset_token FROM clasif_usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id_dash]);
        $pwd_reset_pendiente = !empty($stmt->fetchColumn());
    } catch (PDOException $e) {
        $pwd_reset_pendiente = false;
    }
}

// Cumpleaños del usuario logueado (fecha de nacimiento derivada del CI)
$cumpleanios_usuario_hoy = false;
$anios_cumplidos = 0;
$ci_logueado = preg_replace('/\D/', '', $_SESSION['user_ci'] ?? '');
if (strlen($ci_logueado) >= 6) {
    $mm_ci = (int)substr($ci_logueado, 2, 2);
    $dd_ci = (int)substr($ci_logueado, 4, 2);
    if (checkdate($mm_ci, $dd_ci, 2000) && ($mm_ci * 100 + $dd_ci) === (int)date('md')) {
        $cumpleanios_usuario_hoy = true;
        $yy_ci = (int)substr($ci_logueado, 0, 2);
        $anio_nac_ci = ($yy_ci <= (int)date('y')) ? (2000 + $yy_ci) : (1900 + $yy_ci);
        $anios_cumplidos = (int)date('Y') - $anio_nac_ci;
    }
}

// Configuración empresa (desde constantes centrales con fallback)
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director/Jefe', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Conatb. Finanzas'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
    }
} catch (PDOException $e) {}

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
          AND (fecha_baja IS NULL OR fecha_baja = '')
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
        if ($proximo->format('Y-m-d') < $hoy->format('Y-m-d')) {
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

// Cumpleaños de HOY (dias == 0)
$cumple_hoy = array_values(array_filter($cumpleaneros, function ($c) { return $c['dias'] == 0; }));

// ============================================
// ALERTAS DEL SISTEMA
// ============================================
$alertas = [];
$nominas_borrador = [];
$cumpleaneros_proximos = [];
try {
    // Nóminas en borrador
    $nominas_borrador = $pdo->query("
        SELECT 
            DATE_FORMAT(n.periodo_desde, '%Y-%m') as periodo,
            n.tipo_nomina,
            COUNT(DISTINCT n.trabajador_id) as total_empleados,
            COALESCE(SUM(n.total_salario_devengado), 0) as total_devengado,
            COALESCE(SUM(n.importe_neto), 0) as total_neto
        FROM nominas n
        WHERE n.estado = 'borrador'
        GROUP BY YEAR(n.periodo_desde), MONTH(n.periodo_desde), n.tipo_nomina
        ORDER BY MAX(n.periodo_desde) DESC, n.tipo_nomina ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $borradores = count($nominas_borrador);
    if ($borradores > 0) {
        $alertas[] = [
            'tipo' => 'warning',
            'icono' => 'fa-exclamation-triangle',
            'mensaje' => "Hay $borradores nómina(s) en estado BORRADOR que necesitan revisión",
            'ver_detalle' => 'nominas_borrador'
        ];
    }
    
    // Cumpleaños en los próximos 30 días (desde el CI)
    $cumpleaneros_proximos = array_values(array_filter($cumpleaneros, function ($c) {
        return $c['dias'] <= 30;
    }));
    $proximas_vacaciones = count($cumpleaneros_proximos);
    $num_cumple_hoy = count($cumple_hoy);
    if ($proximas_vacaciones > 0 || $num_cumple_hoy > 0) {
        if ($num_cumple_hoy > 0) {
            $mensaje_cumple = "$num_cumple_hoy empleado(s) cumplen años HOY, $proximas_vacaciones en los próximos 30 días";
        } else {
            $mensaje_cumple = "$proximas_vacaciones empleado(s) cumplen años en los próximos 30 días";
        }
        $alertas[] = [
            'tipo' => 'success',
            'icono' => 'fa-birthday-cake',
            'mensaje' => $mensaje_cumple,
            'ver_detalle' => 'cumpleaneros_proximos'
        ];
    }

    // Trabajadores con más de 20 días de vacaciones en el submayor (Ley 116)
    $trabajadores_excedidos = [];
    $stmt_exc = $pdo->query("
        SELECT t.id, t.codigo, t.nombre_completo, a.nombre_area, cc.nombre as centro_costo,
               COALESCE(SUM(
                   CASE WHEN sv.tipo_movimiento = 'disfrute' THEN -sv.dias ELSE sv.dias END
               ), 0) as saldo_submayor
        FROM trabajadores t
        INNER JOIN submayor_vacaciones sv ON sv.trabajador_id = t.id
        LEFT JOIN areas a ON t.area_id = a.id
        LEFT JOIN centros_costo cc ON t.centro_costo_id = cc.id
        WHERE t.activo = 1
        AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
        GROUP BY t.id, t.codigo, t.nombre_completo, a.nombre_area, cc.nombre
        HAVING saldo_submayor > 20
        ORDER BY saldo_submayor DESC
    ");
    $trabajadores_excedidos = $stmt_exc->fetchAll(PDO::FETCH_ASSOC);
    if (count($trabajadores_excedidos) > 0) {
        $alertas[] = [
            'tipo' => 'danger',
            'icono' => 'fa-exclamation-circle',
            'mensaje' => count($trabajadores_excedidos) . " trabajador(es) superan los 20 días de vacaciones en el submayor",
            'ver_detalle' => 'vacaciones_excedidas'
        ];
    }
} catch (PDOException $e) {
    $trabajadores_excedidos = [];
}

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

$ultimo_mes_rendimiento = 0;
$ultimo_mes_rendimiento_nombre = '';
try {
    $stmt = $pdo->query("
        SELECT periodo_desde, COALESCE(SUM(total_salario_devengado), 0) as total
        FROM nominas 
        WHERE (tipo_nomina = 'bono' OR tipo_nomina = 'rendimiento') AND estado != 'borrador'
        GROUP BY periodo_desde
        ORDER BY periodo_desde DESC
        LIMIT 1
    ");
    $ultimo_mes_rend = $stmt->fetch();
    if ($ultimo_mes_rend && $ultimo_mes_rend['periodo_desde']) {
        $ultimo_mes_rendimiento = floatval($ultimo_mes_rend['total']);
        $ultimo_mes_rendimiento_nombre = nombreMesEspanol(date('n', strtotime($ultimo_mes_rend['periodo_desde'])));
    }
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
$centros_colores = ['#3b82f6', '#fbbf24', 'var(--color-success-soft)', '#a78bfa', '#f87171', 'var(--color-success-soft)', '#f472b6', '#60a5fa', '#fb923c', '#22d3ee'];
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
// DISTRIBUCIÓN POR ÁREA DE TRABAJO (EMPLEADOS ACTIVOS)
// ============================================
$areas_labels = [];
$areas_datos = [];
$areas_colores = [];
$areas_palette = ['#3b82f6', '#fbbf24', 'var(--color-success-soft)', '#a78bfa', '#f87171', '#38bdf8', '#f472b6', '#a3e635'];
try {
    $stmt = $pdo->query("
        SELECT a.nombre_area, COUNT(t.id) AS total
        FROM areas a
        LEFT JOIN trabajadores t ON t.area_id = a.id AND t.activo = 1
        GROUP BY a.id, a.nombre_area
        ORDER BY total DESC, a.nombre_area ASC
    ");
    $i = 0;
    foreach ($stmt->fetchAll() as $r) {
        $areas_labels[] = $r['nombre_area'];
        $areas_datos[]  = intval($r['total']);
        $areas_colores[] = $areas_palette[$i % count($areas_palette)];
        $i++;
    }
} catch (PDOException $e) {
    $areas_labels = [];
    $areas_datos = [];
    $areas_colores = [];
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
// 2. TOTALES POR TIPO DE NÓMINA - POR AÑO (desde nominas)
// ============================================
$totales_por_tipo = [];
$totales_generales = [
    'devengado' => 0,
    'deducciones' => 0,
    'neto' => 0,
    'contribucion' => 0,
    'vacaciones' => 0,
    'registros' => 0,
    'nominas' => 0,
    'trabajadores' => 0
];
$anios_disponibles = [];
$anio_tot_seleccionado = 0;
$ultimo_anio_label = '';
try {
    $anios_disponibles = $pdo->query("
        SELECT DISTINCT YEAR(periodo_desde) as anio
        FROM nominas
        WHERE estado != 'borrador'
        ORDER BY anio DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $anio_tot_seleccionado = isset($_GET['anio']) ? intval($_GET['anio']) : 0;
    if (!in_array($anio_tot_seleccionado, $anios_disponibles)) {
        $anio_tot_seleccionado = !empty($anios_disponibles) ? (int)$anios_disponibles[0] : (int)date('Y');
    }
    $ultimo_anio_label = $anio_tot_seleccionado;

    $stmt_anio = $pdo->prepare("
        SELECT 
            tipo_nomina,
            COUNT(DISTINCT numero_nomina) as cantidad_nominas,
            COUNT(DISTINCT trabajador_id) as cantidad_trabajadores,
            COUNT(*) as cantidad_registros,
            SUM(total_salario_devengado) as sum_devengado,
            SUM(total_deducciones) as sum_deducciones,
            SUM(importe_neto) as sum_neto,
            SUM(contribucion_especial) as sum_contribucion,
            SUM(importe_vacaciones) as sum_vacaciones
        FROM nominas
        WHERE estado != 'borrador'
          AND YEAR(periodo_desde) = ?
        GROUP BY tipo_nomina
        ORDER BY tipo_nomina ASC
    ");
    $stmt_anio->execute([$anio_tot_seleccionado]);
    $totales_por_tipo = $stmt_anio->fetchAll();

    foreach ($totales_por_tipo as $t) {
        $totales_generales['devengado'] += floatval($t['sum_devengado']);
        $totales_generales['deducciones'] += floatval($t['sum_deducciones']);
        $totales_generales['neto'] += floatval($t['sum_neto']);
        $totales_generales['contribucion'] += floatval($t['sum_contribucion']);
        $totales_generales['vacaciones'] += floatval($t['sum_vacaciones']);
        $totales_generales['registros'] += intval($t['cantidad_registros']);
        $totales_generales['nominas'] += intval($t['cantidad_nominas']);
        $totales_generales['trabajadores'] += intval($t['cantidad_trabajadores']);
    }
} catch (PDOException $e) {
    $totales_por_tipo = [];
    $totales_generales = [
        'devengado' => 0,
        'deducciones' => 0,
        'neto' => 0,
        'contribucion' => 0,
        'vacaciones' => 0,
        'registros' => 0,
        'nominas' => 0,
        'trabajadores' => 0
    ];
    $anio_tot_seleccionado = (int)date('Y');
    $ultimo_anio_label = '';
}

// ============================================
// 2b. TOTALES POR TIPO - POR MES (desde nominas)
// ============================================
$totales_por_tipo_mes = [];
$totales_generales_mes = [
    'devengado' => 0,
    'deducciones' => 0,
    'neto' => 0,
    'contribucion' => 0,
    'vacaciones' => 0,
    'registros' => 0,
    'nominas' => 0,
    'trabajadores' => 0
];
$meses_disponibles = [];
$mes_tot_seleccionado = '';
$ultimo_mes_label = '';
try {
    $meses_disponibles = $pdo->query("
        SELECT DISTINCT DATE_FORMAT(periodo_desde, '%Y-%m') as mes
        FROM nominas
        WHERE estado != 'borrador'
        ORDER BY mes DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $mes_tot_seleccionado = isset($_GET['mes']) ? $_GET['mes'] : '';
    if (!in_array($mes_tot_seleccionado, $meses_disponibles)) {
        $mes_tot_seleccionado = !empty($meses_disponibles) ? $meses_disponibles[0] : '';
    }

    if ($mes_tot_seleccionado) {
        $anio_mes = (int)substr($mes_tot_seleccionado, 0, 4);
        $num_mes = (int)substr($mes_tot_seleccionado, 5, 2);
        $ultimo_mes_label = nombreMesEspanol($num_mes) . ' ' . $anio_mes;

        $stmt_mes = $pdo->prepare("
            SELECT 
                tipo_nomina,
                COUNT(DISTINCT numero_nomina) as cantidad_nominas,
                COUNT(DISTINCT trabajador_id) as cantidad_trabajadores,
                COUNT(*) as cantidad_registros,
                SUM(total_salario_devengado) as sum_devengado,
                SUM(total_deducciones) as sum_deducciones,
                SUM(importe_neto) as sum_neto,
                SUM(contribucion_especial) as sum_contribucion,
                SUM(importe_vacaciones) as sum_vacaciones
            FROM nominas
            WHERE estado != 'borrador'
              AND DATE_FORMAT(periodo_desde, '%Y-%m') = ?
            GROUP BY tipo_nomina
            ORDER BY tipo_nomina ASC
        ");
        $stmt_mes->execute([$mes_tot_seleccionado]);
        $totales_por_tipo_mes = $stmt_mes->fetchAll();

        foreach ($totales_por_tipo_mes as $t) {
            $totales_generales_mes['devengado'] += floatval($t['sum_devengado']);
            $totales_generales_mes['deducciones'] += floatval($t['sum_deducciones']);
            $totales_generales_mes['neto'] += floatval($t['sum_neto']);
            $totales_generales_mes['contribucion'] += floatval($t['sum_contribucion']);
            $totales_generales_mes['vacaciones'] += floatval($t['sum_vacaciones']);
            $totales_generales_mes['registros'] += intval($t['cantidad_registros']);
            $totales_generales_mes['nominas'] += intval($t['cantidad_nominas']);
            $totales_generales_mes['trabajadores'] += intval($t['cantidad_trabajadores']);
        }
    }
} catch (PDOException $e) {
    $totales_por_tipo_mes = [];
    $totales_generales_mes = [
        'devengado' => 0,
        'deducciones' => 0,
        'neto' => 0,
        'contribucion' => 0,
        'vacaciones' => 0,
        'registros' => 0,
        'nominas' => 0,
        'trabajadores' => 0
    ];
    $mes_tot_seleccionado = '';
    $ultimo_mes_label = '';
}



// VISOR DE CUADRES POR MESES (cierres_nomina)
// ============================================
$cierres_meses = [];
try {
    $ci_todos = $pdo->query("
        SELECT * FROM cierres_nomina
        ORDER BY periodo_desde DESC, fecha_cierre DESC
    ")->fetchAll();

    // Trabajadores únicos por mes: cada trabajador se cuenta UNA vez,
    // aunque participe en varias nóminas del mismo mes (Automática, Bono, Extra, etc.)
    $trab_unicos_por_mes = [];
    $stmt_trab = $pdo->query("
        SELECT DATE_FORMAT(periodo_desde, '%Y-%m') as ym, COUNT(DISTINCT trabajador_id) as total
        FROM nominas
        WHERE estado IN ('procesado','pagado','cerrado','contabilizado')
        GROUP BY DATE_FORMAT(periodo_desde, '%Y-%m')
    ");
    foreach ($stmt_trab as $row) {
        $trab_unicos_por_mes[$row['ym']] = (int)$row['total'];
    }

    $totales_tipo = [
        'automatica'    => ['label' => 'Automática',    'color' => '#60a5fa', 'bg' => 'rgba(96, 165, 250, 0.16)'],
        'bono'          => ['label' => 'Bono',          'color' => '#fbbf24', 'bg' => 'rgba(251, 191, 36, 0.16)'],
        'extraordinaria'=> ['label' => 'Extraordinaria','color' => '#a78bfa', 'bg' => 'rgba(167, 139, 250, 0.16)'],
        'vacaciones'    => ['label' => 'Vacaciones',    'color' => '#34d399', 'bg' => 'rgba(52, 211, 153, 0.16)'],
        'ajuste'        => ['label' => 'Ajuste',        'color' => '#f87171', 'bg' => 'rgba(248, 113, 113, 0.16)'],
    ];

    foreach ($ci_todos as $ci) {
        $ym = date('Y-m', strtotime($ci['periodo_desde']));
        if (!isset($cierres_meses[$ym])) {
            $cierres_meses[$ym] = [
                'label' => ucfirst(nombreMesEspanol(date('n', strtotime($ci['periodo_desde'])))) . ' de ' . date('Y', strtotime($ci['periodo_desde'])),
                'total_trabajadores' => $trab_unicos_por_mes[$ym] ?? 0,
                'total_devengado'    => 0.0,
                'total_deducciones'  => 0.0,
                'total_neto'         => 0.0,
                'total_contribucion' => 0.0,
                'total_vacaciones'   => 0.0,
                'cierres' => [],
            ];
        }
        $cierres_meses[$ym]['total_devengado']    += (float)$ci['total_devengado'];
        $cierres_meses[$ym]['total_deducciones']  += (float)$ci['total_deducciones'];
        $cierres_meses[$ym]['total_neto']         += (float)$ci['total_neto'];
        $cierres_meses[$ym]['total_contribucion'] += (float)$ci['total_contribucion'];
        $cierres_meses[$ym]['total_vacaciones']   += (float)$ci['total_vacaciones_pagadas'];
        $cierres_meses[$ym]['cierres'][] = [
            'id'             => $ci['id'],
            'numero_nomina'  => $ci['numero_nomina'],
            'tipo_nomina'    => $ci['tipo_nomina'],
            'tipo_label'     => $totales_tipo[$ci['tipo_nomina']]['label'] ?? ucfirst($ci['tipo_nomina']),
            'tipo_color'     => $totales_tipo[$ci['tipo_nomina']]['color'] ?? 'var(--muted, #cbd5e1)',
            'tipo_bg'        => $totales_tipo[$ci['tipo_nomina']]['bg'] ?? 'rgba(255,255,255,0.08)',
            'fecha_cierre'   => $ci['fecha_cierre'],
            'usuario_cierre' => $ci['usuario_cierre'],
            'total_trabajadores' => (int)$ci['total_trabajadores'],
            'total_devengado'    => (float)$ci['total_devengado'],
            'total_deducciones'  => (float)$ci['total_deducciones'],
            'total_neto'         => (float)$ci['total_neto'],
            'total_contribucion' => (float)$ci['total_contribucion'],
            'total_vacaciones'   => (float)$ci['total_vacaciones_pagadas'],
            'estado'         => $ci['estado'],
            'observaciones'  => $ci['observaciones'],
        ];
    }

    foreach ($cierres_meses as $ym => &$m) {
        $m['total_trabajadores'] = (int)$m['total_trabajadores'];
        $m['total_devengado']    = round($m['total_devengado'], 2);
        $m['total_deducciones']  = round($m['total_deducciones'], 2);
        $m['total_neto']         = round($m['total_neto'], 2);
        $m['total_contribucion'] = round($m['total_contribucion'], 2);
        $m['total_vacaciones']   = round($m['total_vacaciones'], 2);
    }
    unset($m);
} catch (PDOException $e) {
    $cierres_meses = [];
}
$cierres_meses_total = count($cierres_meses);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Dashboard</title>
    <link rel="icon" type="image/png" href="../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="css/datatables/1.13.6/buttons.dataTables.min.css" rel="stylesheet">
    <link href="css/sweetalert2.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
    <script src="js/chart.umd.min.js"></script>
    
    <script src="js/jszip.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/html2canvas.min.js"></script>
    <script src="js/exceljs.min.js"></script>
    <script src="js/FileSaver.min.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <style>
        /* ===== Visor de Cuadres por Meses - tabla ===== */
        #tablaCierresMeses {
            background: var(--panel-2) !important;
            width: 100%;
        }
        #tablaCierresMeses thead th {
            background: rgba(22, 22, 30, 0.98) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
            letter-spacing: 0.0312rem;
            font-weight: 600;
            padding: 0.625rem 0.5rem !important;
            vertical-align: middle;
        }
        #tablaCierresMeses tbody tr {
            background: var(--panel-2) !important;
        }
        #tablaCierresMeses tbody tr:hover {
            background: rgba(96, 165, 250, 0.1) !important;
        }
        #tablaCierresMeses td {
            background: transparent !important;
            border: none !important;
            border-bottom: 0.0625rem solid var(--border, rgba(255,255,255,0.05)) !important;
            padding: 0.5625rem 0.5rem !important;
            vertical-align: middle;
        }
        #tablaCierresMeses .cierre-numero { color: var(--accent, #60a5fa); font-weight:600; }
        #tablaCierresMeses .cierre-trab { color: var(--txt, #e8edf6); }
        #tablaCierresMeses .cierre-devengado { color: var(--color-success, #10b981); font-weight:600; }
        #tablaCierresMeses .cierre-dedu { color: var(--faint, #64748b); }
        #tablaCierresMeses .cierre-contrib { color: var(--muted, #97a5bb); }
        #tablaCierresMeses .cierre-neto { color: var(--txt, #ffffff); font-weight:600; }
        #tablaCierresMeses .cierre-fecha { color: var(--faint, rgba(255,255,255,0.6)); font-size:0.75rem; }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php if ($pwd_reset_pendiente): ?>
<!-- BARRA: SOLICITUD DE CAMBIO DE CONTRASEÑA PENDIENTE -->
<div id="barraResetPendiente" class="barra-reset-pendiente">
    <div class="brp-wrap">
        <div class="brp-icono"><i class="fas fa-key"></i></div>
        <div class="brp-texto">
            <span class="brp-titulo">Solicitud de cambio de contraseña pendiente</span>
            <span class="brp-sub">Se generó un enlace de restablecimiento para su cuenta. ¿Desea restablecer su contraseña a una nueva?</span>
        </div>
        <div class="brp-botones">
            <button type="button" id="btnResetSi" class="brp-btn brp-btn-si"><i class="fas fa-check me-1"></i> Sí, restablecer</button>
            <button type="button" id="btnResetNo" class="brp-btn brp-btn-no"><i class="fas fa-times me-1"></i> No fui yo.</button>
        </div>
    </div>
    <button type="button" id="btnResetCerrar" class="brp-cerrar" title="Cerrar notificación" data-tooltip="Cerrar notificación" data-tooltip-theme="danger" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
</div>
<style>
.barra-reset-pendiente {
    position: fixed; top:0; left:0; right:0; z-index: 9999;
    display: flex; align-items: center; justify-content: space-between; gap:0.75rem;
    padding:0.625rem 1.125rem;
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.97), rgba(15, 23, 42, 0.97));
    border-bottom: 0.125rem solid #f59e0b;
    box-shadow: 0 0.375rem 1.25rem rgba(0, 0, 0, 0.5);
    color: #fff;
    animation: brpSlide 0.45s ease;
}
@keyframes brpSlide { from { transform: translateY(-100%); } to { transform: translateY(0); } }
.barra-reset-pendiente.brp-oculta { transition: transform 0.45s ease; transform: translateY(-130%); }
.brp-wrap { display: flex; align-items: center; gap:0.75rem; flex: 1; min-width:0; }
.brp-icono { width:2.375rem; height:2.375rem; flex: 0 0 2.375rem; border-radius: 0.625rem; background: rgba(245, 158, 11, 0.15); border: 0.0625rem solid rgba(245, 158, 11, 0.4); display: flex; align-items: center; justify-content: center; color: #fbbf24; font-size:1rem; }
.brp-texto { display: flex; flex-direction: column; gap:0.125rem; min-width:0; }
.brp-titulo { font-weight: 700; font-size:0.92rem; color: #fbbf24; }
.brp-sub { font-size:0.82rem; color: rgba(255, 255, 255, 0.75); }
.brp-botones { display: flex; gap:0.5rem; flex: 0 0 auto; }
.brp-btn { border: none; border-radius: 0.5rem; padding:0.5rem 0.875rem; font-size:0.82rem; font-weight: 600; cursor: pointer; color: #fff; transition: filter 0.2s ease; }
.brp-btn:hover { filter: brightness(1.12); }
.brp-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.brp-btn-si { background: #f59e0b; color: #0f172a; }
.brp-btn-no { background: rgba(255, 255, 255, 0.1); border: 0.0625rem solid rgba(255, 255, 255, 0.2); }
.brp-cerrar { background: transparent; border: none; color: rgba(255, 255, 255, 0.5); font-size:1.1rem; cursor: pointer; padding:0.25rem; }
.brp-cerrar:hover { color: #fff; }
@media (max-width: 768px) {
    .brp-wrap { flex-wrap: wrap; }
    .brp-botones { width:100%; justify-content: flex-end; }
}
</style>
<?php endif; ?>

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
                    $colores_alerta = [
                        'danger'   => ['bg' => 'rgba(239,68,68,0.15)',   'border' => 'rgba(239,68,68,0.35)',   'text' => 'var(--red)'],
                        'success'  => ['bg' => 'rgba(34,197,94,0.15)',   'border' => 'rgba(34,197,94,0.35)',   'text' => 'var(--color-success)'],
                        'warning'  => ['bg' => 'rgba(245,158,11,0.15)',  'border' => 'rgba(245,158,11,0.35)',  'text' => 'var(--amber)'],
                        'info'     => ['bg' => 'rgba(59,130,246,0.15)',  'border' => 'rgba(59,130,246,0.35)',  'text' => 'var(--indigo)'],
                        'primary'  => ['bg' => 'rgba(59,130,246,0.15)',  'border' => 'rgba(59,130,246,0.35)',  'text' => 'var(--indigo)'],
                        'secondary'=> ['bg' => 'rgba(148,163,184,0.15)', 'border' => 'rgba(148,163,184,0.35)', 'text' => 'var(--muted)']
                    ];
                    $color_alerta = $colores_alerta[$alerta['tipo']] ?? $colores_alerta['info'];
                    $bg_color = $color_alerta['bg'];
                    $border_color = $color_alerta['border'];
                    $text_color = $color_alerta['text'];
                ?>
                <div class="alert alert-win alert-dismissible fade show" 
                     style="background: <?php echo $bg_color; ?>; 
                            border: 0.0625rem solid <?php echo $border_color; ?>; 
                            color: <?php echo $text_color; ?>; border-radius: 0.75rem; padding:0.9375rem 1.25rem;">
                    <i class="fas <?php echo $alerta['icono']; ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <?php if (!empty($alerta['ver_detalle'])): ?>
                        <button type="button" class="btn-win btn-win-sm ms-3 align-middle" onclick="mostrarDetalleAlertas('<?php echo $alerta['ver_detalle']; ?>')" title="Ver listado completo" data-tooltip="Ver listado completo" data-tooltip-theme="info">
                            <i class="fas fa-list-ul me-1"></i> Ver Detalle
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" 
                            style=""></button>
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
                    <small style="color: var(--muted);">Último mes procesado</small>
                </div>
                <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15); color: var(--blue);">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-arrow-trend-up me-1"></i> Variación Mensual</p>
                    <h3 class="stat-value" style="color: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'var(--color-success-soft)' : 'var(--red)'; ?>">
                        <?php echo number_format($kpi['variacion'] ?? 0, 1); ?>%
                        <i class="fas fa-<?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down'; ?>" style="font-size:1.2rem;"></i>
                    </h3>
                    <small style="color: var(--muted);">Comparado con mes anterior</small>
                </div>
                <div class="stat-icon" style="background: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'rgba(var(--color-success-soft-rgb), 0.15)' : 'rgba(248, 113, 113, 0.15)'; ?>; 
                            color: <?php echo ($kpi['variacion'] ?? 0) >= 0 ? 'var(--color-success-soft)' : 'var(--red)'; ?>;">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card" id="kpiActualizacion" style="cursor: pointer;" title="Haz clic para actualizar el dashboard" data-tooltip="Haz clic para actualizar el dashboard" data-tooltip-theme="success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">
                        <i class="fas fa-clock me-1"></i> Última Actualización
                        <small style="color: var(--faint); font-size:0.6rem; display: block;">
                            Haz clic para actualizar
                        </small>
                    </p>
                    <h3 class="stat-value" style="font-size:1.1rem;" id="ultimaActualizacion">
                        <i class="fas fa-clock me-2" style="color: var(--muted);"></i>
                        <?php 
                            $timestamp = time();
                            echo date('d/m/Y', $timestamp) . ' - ' . date('h:i A', $timestamp);
                        ?>
                    </h3>
                    <small style="color: var(--muted);">Datos en tiempo real</small>
                </div>
                <div class="stat-icon" style="background: rgba(148, 163, 184, 0.15); color: var(--muted);">
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
                    <small style="color: var(--muted);">Activos: <?php echo number_format($total_empleados); ?> | Inactivos: <?php echo number_format($total_empleados_inactivos); ?></small>
                </div>
                <div class="stat-icon" style="background: rgba(0, 120, 212, 0.15); color: var(--blue);">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-dollar-sign me-1"></i> Total Pagado Ingresos Personales</p>
                    <h3 class="stat-value"><?php echo formatearMoneda($total_pagado_salario); ?></h3>
                    <small style="color: var(--muted);">Suma neta de todos los tipo de nóminas</small>
                </div>
                <div class="stat-icon" style="background: rgba(16, 124, 16, 0.15); color: var(--color-success-soft);">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
        <div class="glass-card stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label"><i class="fas fa-calendar-alt me-1"></i> Períodos Procesados</p>
                    <h3 class="stat-value"><?php echo number_format($total_periodos_procesados); ?></h3>
                    <small style="color: var(--muted);">Meses con nóminas generadas</small>
                </div>
                <div class="stat-icon" style="background: rgba(255, 185, 0, 0.15); color: var(--amber);">
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
                <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseResumenNominas" aria-expanded="true" aria-controls="collapseResumenNominas">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-clipboard-list me-2"></i>Resumen de Nóminas
                </h6>
                <span class="badge-win"><i class="fas fa-database me-1"></i> secuencias + nóminas</span>
            </div>
            <div class="p-3 collapse" id="collapseResumenNominas">

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
                    // Totales de cierres por tipo (para mostrarlos en las tarjetas)
                    $cierres_por_tipo = [];
                    foreach ($totales_por_tipo as $tc) {
                        $cierres_por_tipo[strtolower(trim($tc['tipo_nomina']))] = $tc;
                    }
                    // Tarjetas de secuencias
                    foreach ($secuencias as $item):
                        $tipo = strtolower(trim($item['tipo_nomina']));
                        $color = $colores_tipo[$tipo] ?? 'secondary';
                        $icono = $iconos_tipo[$tipo] ?? 'fa-tag';
                        $numero = intval($item['ultimo_numero']);
                        $ci = $cierres_por_tipo[$tipo] ?? null;
                    ?>
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded text-center" style="background: rgba(<?php echo ($color == 'primary') ? '59,130,246' : (($color == 'success') ? '74,222,128' : (($color == 'warning') ? '245,158,11' : (($color == 'danger') ? '248,113,113' : (($color == 'info') ? '96,165,250' : '148,163,184')))); ?>, 0.1);">
                                <i class="fas <?php echo $icono; ?> fa-2x mb-2" style="color: <?php echo ($color == 'primary') ? 'var(--blue)' : (($color == 'success') ? 'var(--color-success-soft)' : (($color == 'warning') ? 'var(--amber)' : (($color == 'danger') ? 'var(--red)' : (($color == 'info') ? 'var(--blue)' : 'var(--muted)')))); ?>;"></i>
                                <div>
                                    <span class="badge bg-<?php echo $color; ?> mb-1"><?php echo ucfirst($tipo); ?></span>
                                    <h4 class="mb-0"><?php echo number_format($numero); ?></h4>
                                    <small style="color: var(--muted);">último número</small>
                                </div>
                                <?php if ($ci): ?>
                                <div class="mt-2 pt-2" style="border-top: 0.0625rem solid var(--border); text-align: left; font-size:0.78rem;">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span style="color: var(--muted);">Devengado</span>
                                        <span class="text-success fw-semibold"><?php echo formatearMoneda($ci['sum_devengado']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span style="color: var(--muted);">Deducciones</span>
                                        <span class="text-danger fw-semibold"><?php echo formatearMoneda($ci['sum_deducciones']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span style="color: var(--muted);">Neto</span>
                                        <span style="color: var(--color-success-soft); font-weight: 700;"><?php echo formatearMoneda($ci['sum_neto']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Tarjeta extra: Rendimiento Total (total pagado) -->
                    <div class="col-md-3 col-6">
                        <div class="p-3 rounded text-center" style="background: rgba(168,85,247,0.1);">
                            <i class="fas fa-trophy fa-2x mb-2" style="color: var(--violet-soft);"></i>
                            <div>
                                <span class="badge bg-purple mb-1">Rendimiento Total</span>
                                <h4 class="mb-0"><?php echo formatearMoneda($total_pago_rendimiento ?? 0); ?></h4>
                                <small style="color: var(--muted);">total pagado</small>
                            </div>
                            <div class="mt-2 pt-2" style="border-top: 0.0625rem solid var(--border); font-size:0.78rem;">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <span style="color: var(--muted);">último mes<?php if ($ultimo_mes_rendimiento_nombre): ?> (<?php echo $ultimo_mes_rendimiento_nombre; ?>)<?php endif; ?>:</span>
                                    <span style="color: var(--violet-soft); font-weight: 700;"><?php echo formatearMoneda($ultimo_mes_rendimiento); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-info" style="background: rgba(59,130,246,0.1); border: 0.0625rem solid var(--blue); border-radius: 0.5rem; color: var(--txt);">
                        <i class="fas fa-info-circle me-2"></i> No hay registros en <strong>secuencias_nominas</strong> ni datos de rendimiento.
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 2. TABLA DE TOTALES POR TIPO (desde nóminas) -->
                <!-- ========================================== -->
                <?php if (!empty($totales_por_tipo)): ?>
                <div class="mt-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h6 class="fw-semibold mb-0" style="color: var(--cyan);">
                            <i class="fas fa-calculator me-2"></i>Totales por Tipo - Año <span id="anioTotLabel"><?php echo htmlspecialchars($ultimo_anio_label); ?></span> (desde nóminas)
                        </h6>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label for="sel_anio_tot" class="mb-0 text-muted" style="font-size:0.8rem; color: var(--muted) !important;">Año:</label>
                            <select name="anio" id="sel_anio_tot" class="form-select form-select-sm" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: var(--txt); width:auto; border-radius: 0.5rem; padding-right:2.5rem;" title="Seleccionar año" data-tooltip="Seleccionar año" data-tooltip-theme="secondary">
                                <?php foreach ($anios_disponibles as $ano): ?>
                                    <option value="<?php echo (int)$ano; ?>" <?php echo ((int)$anio_tot_seleccionado === (int)$ano) ? 'selected' : ''; ?>>
                                        <?php echo (int)$ano; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-win btn-win-sm">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th style="color: var(--muted);">Tipo</th>
                                    <th class="text-end" style="color: var(--muted);">Nóminas</th>
                                    <th class="text-end" style="color: var(--muted);">Trabajadores</th>
                                    <th class="text-end" style="color: var(--muted);">Registros</th>
                                    <th class="text-end" style="color: var(--muted);">Devengado</th>
                                    <th class="text-end" style="color: var(--muted);">Contribución</th>
                                    <th class="text-end" style="color: var(--muted);">Deducciones</th>
                                    <th class="text-end" style="color: var(--muted);">Neto</th>
                                    <th class="text-end" style="color: var(--muted);">% Neto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($totales_por_tipo as $item):
                                    $tipo = ucfirst($item['tipo_nomina']);
                                    $color = $colores_tipo[strtolower($item['tipo_nomina'])] ?? 'secondary';
                                    $neto_total_anio = floatval($totales_generales['neto']);
                                    $porc_neto = $neto_total_anio > 0 ? (floatval($item['sum_neto']) / $neto_total_anio) * 100 : 0;
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($tipo); ?></span></td>
                                    <td class="text-end"><?php echo number_format($item['cantidad_nominas']); ?></td>
                                    <td class="text-end"><?php echo number_format($item['cantidad_trabajadores']); ?></td>
                                    <td class="text-end"><?php echo number_format($item['cantidad_registros']); ?></td>
                                    <td class="text-end text-success"><?php echo formatearMoneda($item['sum_devengado']); ?></td>
                                    <td class="text-end" style="color: var(--blue);"><?php echo formatearMoneda($item['sum_contribucion']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatearMoneda($item['sum_deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);"><?php echo formatearMoneda($item['sum_neto']); ?></td>
                                    <td class="text-end" style="color: var(--txt);"><?php echo number_format($porc_neto, 2); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="border-top: 0.125rem solid var(--border-2);">
                                    <td style="font-weight: bold; color: var(--txt);">TOTAL GENERAL</td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales['nominas']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales['trabajadores']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales['registros']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatearMoneda($totales_generales['devengado']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--blue);"><?php echo formatearMoneda($totales_generales['contribucion']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo formatearMoneda($totales_generales['deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);"><?php echo formatearMoneda($totales_generales['neto']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);">100.00%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small style="color: var(--faint);">
                        <i class="fas fa-clock me-1"></i> Datos actualizados al <?php echo date('d/m/Y h:i:s A'); ?>
                    </small>
                </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 2c. TABLA DE TOTALES POR TIPO DEL ÚLTIMO MES -->
                <!-- ========================================== -->
                <?php if (!empty($totales_por_tipo_mes)): ?>
                <div class="mt-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h6 class="fw-semibold mb-0" style="color: var(--cyan);">
                            <i class="fas fa-calendar-check me-2"></i>Totales por Tipo - Mes <span id="mesTotLabel"><?php echo htmlspecialchars($ultimo_mes_label); ?></span>
                        </h6>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label for="sel_mes_tot" class="mb-0 text-muted" style="font-size:0.8rem; color: var(--muted) !important;">Mes:</label>
                            <select name="mes" id="sel_mes_tot" class="form-select form-select-sm" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: var(--txt); width:auto; border-radius: 0.5rem; padding-right:2.5rem;" title="Seleccionar mes" data-tooltip="Seleccionar mes" data-tooltip-theme="secondary">
                                <?php foreach ($meses_disponibles as $m): ?>
                                    <?php $m_label = nombreMesEspanol((int)substr($m, 5, 2)) . ' ' . (int)substr($m, 0, 4); ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo ($mes_tot_seleccionado === $m) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-win btn-win-sm">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th style="color: var(--muted);">Tipo</th>
                                    <th class="text-end" style="color: var(--muted);">Nóminas</th>
                                    <th class="text-end" style="color: var(--muted);">Trabajadores</th>
                                    <th class="text-end" style="color: var(--muted);">Registros</th>
                                    <th class="text-end" style="color: var(--muted);">Devengado</th>
                                    <th class="text-end" style="color: var(--muted);">Contribución</th>
                                    <th class="text-end" style="color: var(--muted);">Deducciones</th>
                                    <th class="text-end" style="color: var(--muted);">Neto</th>
                                    <th class="text-end" style="color: var(--muted);">% Neto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($totales_por_tipo_mes as $item):
                                    $tipo = ucfirst($item['tipo_nomina']);
                                    $color = $colores_tipo[strtolower($item['tipo_nomina'])] ?? 'secondary';
                                    $neto_total_mes = floatval($totales_generales_mes['neto']);
                                    $porc_neto = $neto_total_mes > 0 ? (floatval($item['sum_neto']) / $neto_total_mes) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
										<a href="modules/nominas.php?periodo=<?php echo urlencode($mes_tot_seleccionado); ?>&tipo=<?php echo urlencode($item['tipo_nomina']); ?>" 
										   class="badge bg-<?php echo $color; ?> text-decoration-none" 
										   style="color: var(--txt) !important;">
											<?php echo htmlspecialchars($tipo); ?>
										</a>
									</td>
									<td class="text-end"><?php echo number_format($item['cantidad_nominas']); ?></td>
									<td class="text-end"><?php echo number_format($item['cantidad_trabajadores']); ?></td>
									<td class="text-end"><?php echo number_format($item['cantidad_registros']); ?></td>
                                    <td class="text-end text-success"><?php echo formatearMoneda($item['sum_devengado']); ?></td>
                                    <td class="text-end" style="color: var(--blue);"><?php echo formatearMoneda($item['sum_contribucion']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatearMoneda($item['sum_deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);"><?php echo formatearMoneda($item['sum_neto']); ?></td>
                                    <td class="text-end" style="color: var(--txt);"><?php echo number_format($porc_neto, 2); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="border-top: 0.125rem solid var(--border-2);">
                                    <td style="font-weight: bold; color: var(--txt);">TOTAL MES</td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales_mes['nominas']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales_mes['trabajadores']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($totales_generales_mes['registros']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo formatearMoneda($totales_generales_mes['devengado']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--blue);"><?php echo formatearMoneda($totales_generales_mes['contribucion']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo formatearMoneda($totales_generales_mes['deducciones']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);"><?php echo formatearMoneda($totales_generales_mes['neto']); ?></td>
                                    <td class="text-end fw-bold" style="color: var(--color-success-soft);">100.00%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
                <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseCumpleanios" aria-expanded="true" aria-controls="collapseCumpleanios">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-birthday-cake fa-fw me-2" style="font-size:1.05rem;"></i>Cumpleaños Empleados
                </h6>
                <span class="badge-win" id="cumpleCounter">0 de 0</span>
            </div>
            <div class="p-3 collapse" id="collapseCumpleanios">
                <div class="row g-3">
                    <!-- Columna izquierda: 75% carrusel -->
                    <div class="col-lg-5">
                        <div class="text-center" id="cumpleContainer">
                            <div id="cumpleContent">
                                <div class="py-5">
                                    <i class="fas fa-spinner fa-pulse fa-3x" style="color: var(--faint);"></i>
                                    <p class="mt-2" style="color: var(--muted);">Cargando cumpleañeros...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Columna derecha: 25% lista de los 10 más próximos -->
                    <div class="col-lg-7">
                        <div class="glass-card p-2" style="background: rgba(0,0,0,0.2); border: 0.0625rem solid rgba(255,255,255,0.05);">
                            <h6 class="text-center mb-2" style="color: var(--txt); font-size:0.8rem; letter-spacing:0.0312rem;">
                                <i class="fas fa-list me-1"></i> Los 10 más próximos
                            </h6>
                            <div id="listaCumpleaneros" style="max-height:21.875rem; overflow-y: auto; padding-right:0.3125rem;">
                                <!-- Se llena desde JavaScript -->
                                <div class="text-center py-3" style="color: var(--faint); font-size:0.8rem;">
                                    <i class="fas fa-spinner fa-pulse me-1"></i> Cargando...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="glass-card" style="height:100%;">
            <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseAreaTrabajo" aria-expanded="true" aria-controls="collapseAreaTrabajo">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-users me-2"></i>Distrib. por Área de Trabajo
                </h6>
                <div>
                    <button class="btn-win btn-win-sm" onclick="exportarAreaChart()" title="Exportar gráfico a PNG" data-tooltip="Exportar gráfico a PNG" data-tooltip-theme="info">
                        <i class="fas fa-download me-1"></i> PNG
                    </button>
                    <span class="badge-win ms-2"><i class="fas fa-user me-1"></i> <?php echo (int)$total_empleados; ?> empleados</span>
                </div>
            </div>
            <div class="p-3 collapse" id="collapseAreaTrabajo">
                <div style="height:25rem; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($areas_datos)): ?>
                    <canvas id="areaChart"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x mb-2" style="color: var(--faint);"></i>
                        <p class="mb-0" style="color: var(--muted);">No hay empleados activos por área</p>
                    </div>
                <?php endif; ?>
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
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseEvolucionMes" aria-expanded="true" aria-controls="collapseEvolucionMes">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-chart-bar me-2"></i>Evolución de Nóminas por Mes
                    </h6>
                    <div>
                        <button class="btn-win btn-win-sm" onclick="exportarGrafico()" title="Exportar gráfico a PNG" data-tooltip="Exportar gráfico a PNG" data-tooltip-theme="info">
                            <i class="fas fa-download me-1"></i> Exportar PNG
                        </button>
                        <span class="badge-win ms-2"><i class="fas fa-chart-line me-1"></i> Salario Neto</span>
                    </div>
                </div>
                <div class="p-3 collapse" id="collapseEvolucionMes">
                    <div class="chart-container">
                        <canvas id="nominasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass-card" style="height:100%;">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseDistribucionTipo" aria-expanded="true" aria-controls="collapseDistribucionTipo">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-chart-pie me-2"></i>Distribución por Tipo
                    </h6>
                    <button class="btn-win btn-win-sm" onclick="exportarGraficoTorta()" title="Exportar gráfico a PNG" data-tooltip="Exportar gráfico a PNG" data-tooltip-theme="info">
                        <i class="fas fa-download me-1"></i> PNG
                    </button>
                </div>
                <div class="p-3 collapse" id="collapseDistribucionTipo">
                    <div style="height:21.25rem; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($distribucion_tipos)): ?>
                        <canvas id="tipoChart"></canvas>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-3x mb-2" style="color: var(--faint);"></i>
                            <p class="mb-0" style="color: var(--muted);">No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REGISTRO HISTÓRICO DE MONTOS -->
    <div class="row g-4 fade-in-up mb-4" style="animation-delay: 0.18s;">
        <div class="col-12">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseRegistroMontos" aria-expanded="true" aria-controls="collapseRegistroMontos">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-coins me-2"></i>Registro Histórico de Montos para Redistribución
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label for="anio_distrib" class="mb-0 text-muted" style="font-size:0.8rem; color: var(--muted) !important;">Año:</label>
                            <select name="anio_distrib" id="anio_distrib" class="form-select form-select-sm" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: var(--txt); width:auto; border-radius: 0.5rem; padding-right:2.5rem;" title="Año de distribución" data-tooltip="Año de distribución" data-tooltip-theme="secondary">
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
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionExcel()" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="info">
                                <i class="fas fa-file-excel"></i>
                            </button>
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionPDF()" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="info">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn-win btn-win-sm" onclick="exportarDistribucionPNG()" title="Exportar gráfico a PNG" data-tooltip="Exportar gráfico a PNG" data-tooltip-theme="info">
                                <i class="fas fa-image"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-3 collapse" id="collapseRegistroMontos">
                    <?php if (!empty($montos_distrib)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div style="height:15.625rem; position: relative;">
                                    <canvas id="distribucionChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover table-sm" style="color: var(--txt);">
                                <thead>
                                    <tr>
                                        <th style="color: var(--muted);">Mes</th>
                                        <th class="text-end" style="color: var(--muted);">Importe de Distribución</th>
                                        <th class="text-end" style="color: var(--muted);">Variación</th>
                                        <th class="text-end" style="color: var(--muted);">% del Total</th>
                                        <th class="text-end" style="color: var(--muted);">Fecha de Registro</th>
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
                                            $variacion_color = $variacion >= 0 ? 'var(--color-success-soft)' : '#f87171';
                                        }
                                        $mes_anterior = $importe_actual;
                                    ?>
                                        <tr>
                                            <td style="color: var(--txt);"><?php echo htmlspecialchars(ucfirst($item['mes'])); ?></td>
                                            <td class="text-end text-success fw-bold" style="color: var(--color-success-soft) !important;"><?php echo formatearMoneda($item['importe_dis']); ?></td>
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
                                            <td class="text-end" style="color: var(--muted);"><?php echo number_format($porcentaje, 1); ?>%</td>
                                            <td class="text-end fecha-registro" style="color: var(--muted) !important;">
                                                <?php echo $fecha_formateada; ?> <span style="color: #64748b;">|</span> <?php echo $hora_formateada; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active" style="background: rgba(255,255,255,0.05);">
                                        <th class="text-end" style="color: var(--txt);">Total Anual:</th>
                                        <th class="text-end text-success fw-bold" style="color: var(--color-success-soft) !important;">
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
                                    <small style="color: var(--muted);">Promedio Mensual</small>
                                    <h6 class="mb-0" style="color: var(--blue);"><?php echo formatearMoneda($total_anual / count($montos_distrib)); ?></h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(var(--color-success-soft-rgb),0.1);">
                                    <small style="color: var(--muted);">Mes Máximo</small>
                                    <h6 class="mb-0" style="color: var(--color-success-soft);">
                                        <?php echo htmlspecialchars(ucfirst($max_item['mes'])) . ' ' . formatearMoneda($max_item['importe_dis']); ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(248,113,113,0.1);">
                                    <small style="color: var(--muted);">Mes Mínimo</small>
                                    <h6 class="mb-0" style="color: var(--red);">
                                        <?php echo htmlspecialchars(ucfirst($min_item['mes'])) . ' ' . formatearMoneda($min_item['importe_dis']); ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: rgba(168,85,247,0.1);">
                                    <small style="color: var(--muted);">Meses Registrados</small>
                                    <h6 class="mb-0" style="color: var(--violet-soft);"><?php echo count($montos_distrib); ?></h6>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-coins fa-2x mb-2" style="color: var(--faint);"></i>
                            <p class="mb-0" style="color: var(--muted);">No hay registros de montos de distribución para el año <?php echo $anio_seleccionado; ?></p>
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
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseCentrosCosto" aria-expanded="true" aria-controls="collapseCentrosCosto">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-building me-2"></i>Distribución por Centros de Costo
                    </h6>
                    <small style="color: var(--muted); font-size:0.7rem;">
                        <i class="fas fa-calendar-alt me-1"></i> 
                        Período de referencia: <strong><?php echo htmlspecialchars($periodo_referencia); ?></strong>
                        <?php if (!empty($centros_costo_data)): ?>
                            | <?php echo count($centros_costo_data); ?> centros activos
                        <?php endif; ?>
                    </small>
                </div>
                <div>
                    <button class="btn-win btn-win-sm" onclick="exportarCentrosCosto()" title="Exportar gráfico a PNG" data-tooltip="Exportar gráfico a PNG" data-tooltip-theme="info">
                        <i class="fas fa-download me-1"></i> PNG
                    </button>
                    <button class="btn-win btn-win-sm" onclick="exportarCentrosCostoExcel()" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="info">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </button>
                    <span class="badge-win ms-2"><i class="fas fa-chart-bar me-1"></i> Salario vs Empleados</span>
                </div>
            </div>
            <div class="p-3 collapse" id="collapseCentrosCosto">
                <div class="row">
                    <div class="col-lg-8">
                        <div style="height:21.875rem; position: relative;">
                            <canvas id="centrosCostoChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
<div class="table-responsive">
    <table class="table table-dark table-sm table-striped align-middle">
        <thead>
            <tr>
                <th style="color: var(--muted);">Centro de Costo</th>
                <th class="text-end" style="color: var(--muted);">Empleados</th>
                <th class="text-end" style="color: var(--muted);">Total Salario</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($centros_costo_data) || $sin_centro_empleados > 0): 
                // Mostrar centros de costo
                foreach ($centros_costo_data as $item):
                    $color = $centros_colores[array_search($item, $centros_costo_data) % count($centros_colores)];
            ?>
            <tr>
                <td style="color: var(--txt);">
                    <span class="badge" style="background: <?php echo $color; ?>; width:0.625rem; height:0.625rem; display: inline-block; border-radius: 50%; margin-right:0.5rem;"></span>
                    <?php echo htmlspecialchars($item['nombre']); ?>
                </td>
                <td class="text-end" style="color: var(--muted);"><?php echo number_format($item['total_empleados']); ?></td>
                <td class="text-end text-success fw-bold"><?php echo formatearMoneda($item['total_salario']); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Mostrar "Sin Asignar" si hay empleados sin centro -->
            <?php if ($sin_centro_empleados > 0): ?>
            <tr>
                <td style="color: var(--muted);">
                    <span class="badge" style="background: #64748b; width:0.625rem; height:0.625rem; display: inline-block; border-radius: 50%; margin-right:0.5rem;"></span>
                    Sin Asignar
                </td>
                <td class="text-end" style="color: var(--muted);"><?php echo number_format($sin_centro_empleados); ?></td>
                <td class="text-end text-warning fw-bold"><?php echo formatearMoneda($sin_centro_salario); ?></td>
            </tr>
            <?php endif; ?>
            
            <!-- TOTAL -->
            <tr style="border-top: 0.125rem solid var(--border-2);">
                <td style="color: var(--txt); font-weight: bold;">TOTAL</td>
                <td class="text-end" style="color: var(--txt); font-weight: bold;"><?php echo number_format($total_empleados_cc); ?></td>
                <td class="text-end text-success fw-bold"><?php echo formatearMoneda($total_salario_cc); ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="3" class="text-center py-3" style="color: var(--muted);">
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


	
	
    <!-- Visor de Cuadres por Meses (cierres_nomina) -->
    <div class="row g-4 mt-1 fade-in-up" style="animation-delay: 0.2s;">
        <div class="col-12">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseCierresMeses" aria-expanded="false" aria-controls="collapseCierresMeses">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-calendar-check me-2" style="color: var(--color-success-soft, #34d399);"></i> Visor de Cuadres por Meses
                        <span class="badge ms-2" style="background: rgba(var(--color-success-rgb), 0.14); color: var(--color-success-soft, #34d399); border: 0.0625rem solid var(--color-success); font-size:0.65rem;"><?php echo $cierres_meses_total; ?> mes<?php echo $cierres_meses_total === 1 ? '' : 'es'; ?></span>
                    </h6>
                </div>
                <div id="collapseCierresMeses" class="collapse">
                <div class="p-4">
                    <?php if (empty($cierres_meses)): ?>
                        <div class="text-center py-4" style="color: var(--faint, #64748b);">
                            <i class="fas fa-inbox fa-2x mb-2 d-block" style="color: var(--faint, rgba(255,255,255,0.2));"></i>
                            No hay cuadres (cierres) registrados.
                        </div>
                    <?php else: ?>
                        <div class="mb-3 d-flex flex-wrap gap-2" id="cierresLegenda">
                            <?php foreach ($totales_tipo as $tk => $tl): ?>
                                <span class="badge" style="background: <?php echo $tl['bg']; ?>; color: <?php echo $tl['color']; ?>; border: 0.0625rem solid <?php echo $tl['color']; ?>; font-size:0.7rem;">
                                    <i class="fas fa-circle me-1" style="font-size:0.5rem;"></i><?php echo $tl['label']; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($cierres_meses as $ym => $mes): ?>
                        <div class="mb-3" style="border: 0.0625rem solid var(--border, rgba(255,255,255,0.08)); border-radius: 0.75rem; overflow: hidden;">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2" style="background: rgba(var(--color-success-rgb), 0.08); border-bottom: 0.0625rem solid var(--border, rgba(255,255,255,0.08)); cursor: pointer;" onclick="toggleCierresMes('cmes_<?php echo $ym; ?>', this)">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-chevron-down cierres-chevon" style="font-size:0.75rem; color: var(--color-success-soft, #34d399); transition: transform 0.25s ease;"></i>
                                    <strong style="color: var(--color-success-soft, #34d399);"><i class="fas fa-calendar-alt me-2" style="color: var(--color-success-soft, #34d399);"></i><?php echo htmlspecialchars($mes['label']); ?></strong>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap" style="font-size:0.78rem; color: var(--muted, #97a5bb);">
                                    <span title="Trabajadores" data-tooltip="Trabajadores" data-tooltip-theme="info"><i class="fas fa-users me-1" style="color: var(--accent, #60a5fa);"></i><?php echo number_format($mes['total_trabajadores'], 0, '.', ','); ?></span>
                                    <span title="Devengado" data-tooltip="Devengado" data-tooltip-theme="info"><i class="fas fa-money-bill-wave me-1" style="color: var(--color-success, #10b981);"></i><?php echo formatearMoneda($mes['total_devengado']); ?></span>
                                    <span title="Deducciones" data-tooltip="Deducciones" data-tooltip-theme="danger"><i class="fas fa-minus-circle me-1"></i><?php echo formatearMoneda($mes['total_deducciones']); ?></span>
                                    <span title="Neto a pagar" data-tooltip="Neto a pagar" data-tooltip-theme="warning"><i class="fas fa-hand-holding-usd me-1"></i><?php echo formatearMoneda($mes['total_neto']); ?></span>
                                </div>
                            </div>
                            <div id="cmes_<?php echo $ym; ?>" class="cierres-body" style="display:none;">
                                <div class="table-responsive" style="border:none; background: var(--panel-2, #151b2a); padding:0;">
                                    <table id="tablaCierresMeses" class="table table-sm" style="background: var(--panel-2, #151b2a); margin-bottom:0;">
                                        <thead>
                                            <tr>
                                                <th style="font-size:0.68rem; text-transform:uppercase;">Nº Nómina</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase;">Tipo</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase;">Trab.</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase; text-align:right;">Devengado</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase; text-align:right;">Deducciones</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase; text-align:right;">Contrib.</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase; text-align:right;">Neto</th>
                                                <th style="font-size:0.68rem; text-transform:uppercase;">Fecha Cierre</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mes['cierres'] as $c): ?>
                                            <tr>
                                                <td class="cierre-numero">
                                                    <i class="fas fa-file-invoice me-1" style="color: var(--accent, #60a5fa);"></i><?php echo htmlspecialchars($c['numero_nomina']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: <?php echo $c['tipo_bg']; ?>; color: <?php echo $c['tipo_color']; ?>; border: 0.0625rem solid <?php echo $c['tipo_color']; ?>; font-size:0.62rem;"><?php echo htmlspecialchars($c['tipo_label']); ?></span>
                                                </td>
                                                <td class="cierre-trab" style="text-align:center;"><?php echo number_format($c['total_trabajadores'], 0, '.', ','); ?></td>
                                                <td class="cierre-devengado" style="text-align:right;"><?php echo formatearMoneda($c['total_devengado']); ?></td>
                                                <td class="cierre-dedu" style="text-align:right;"><?php echo formatearMoneda($c['total_deducciones']); ?></td>
                                                <td class="cierre-contrib" style="text-align:right;"><?php echo formatearMoneda($c['total_contribucion']); ?></td>
                                                <td class="cierre-neto" style="text-align:right;"><?php echo formatearMoneda($c['total_neto']); ?></td>
                                                <td class="cierre-fecha">
                                                    <i class="fas fa-clock me-1" style="color: var(--accent, #60a5fa); font-size:0.65rem;"></i><?php echo date('d/m/Y H:i', strtotime($c['fecha_cierre'])); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <small class="text-secondary" style="font-size:0.75rem; color: var(--muted, #9ca3af);">
                            <i class="fas fa-info-circle me-1"></i>Datos procedentes de la tabla <code>cierres_nomina</code> (cuadres contables por período).
                        </small>
                    <?php endif; ?>
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
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseUltimasNominas" aria-expanded="true" aria-controls="collapseUltimasNominas">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-history me-2"></i>Últimas Nóminas Generadas
                    </h6>
                    <a href="modules/nominas.php" class="btn-win btn-win-sm" title="Ir a Nóminas del mes" data-tooltip="Ir a Nóminas del mes" data-tooltip-theme="primary">Ir a Nóminas <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="p-3 collapse" id="collapseUltimasNominas">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <select id="filtroTipo" class="form-select form-select-sm" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: var(--txt); border-radius: 0.5rem;" title="Filtrar por tipo" data-tooltip="Filtrar por tipo" data-tooltip-theme="secondary">
                                <option value="">Todos los tipos</option>
                                <option value="automatica">Automática</option>
                                <option value="extraordinaria">Extraordinaria</option>
                                <option value="vacaciones">Vacaciones</option>
                                <option value="bono">Bono/Rendimiento</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select id="filtroEstado" class="form-select form-select-sm" style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.15); color: var(--txt); border-radius: 0.5rem;" title="Filtrar por estado" data-tooltip="Filtrar por estado" data-tooltip-theme="secondary">
                                <option value="">Todos los estados</option>
                                <option value="contabilizado">Contabilizado</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="pagado">Pagado</option>
                                <option value="borrador">Borrador</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group" role="group">
                                <button class="btn-win btn-win-sm" onclick="exportarTablaExcel()" title="Exportar a Excel" data-tooltip="Exportar a Excel" data-tooltip-theme="info">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button class="btn-win btn-win-sm" onclick="exportarTablaPDF()" title="Exportar a PDF" data-tooltip="Exportar a PDF" data-tooltip-theme="info">
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
                                            <i class="fas fa-inbox fa-3x mb-2 d-block" style="color: var(--faint);"></i>
                                            No hay nóminas generadas aún
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($ultimas_nominas)): ?>
                            <tfoot>
                                <tr style="background: rgba(255,255,255,0.05); border-top: 0.125rem solid rgba(255,255,255,0.15);">
                                    <td style="display: none;"></td>
                                    <td colspan="3" style="text-align: right; font-weight: 600; color: var(--txt);">
                                        <i class="fas fa-calculator me-2"></i> TOTALES:
                                    </td>
                                    <td style="font-weight: 600; color: var(--blue);"><?php echo formatearMoneda($totales_devengado); ?></td>
                                    <td style="font-weight: 600; color: var(--red);"><?php echo formatearMoneda($totales_deducciones); ?></td>
                                    <td style="font-weight: 700; color: var(--color-success-soft);"><?php echo formatearMoneda($totales_neto); ?></td>
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
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseAccionesRapidas" aria-expanded="true" aria-controls="collapseAccionesRapidas">
                        <i class="fas fa-chevron-down collapse-chevron"></i>
                        <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                    </h6>
                </div>
                <div class="p-3 collapse" id="collapseAccionesRapidas">
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
                        <?php if (permiso_puede('configuracion', 'ver')): ?>
                        <div class="col-md-3 col-6">
                            <a href="modules/configuracion.php" class="btn-win w-100 text-center py-3">
                                <i class="fas fa-cog fa-2x mb-2 d-block"></i> Configuración
                            </a>
                        </div>
                        <?php endif; ?>
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
<script src="js/datatables/1.13.6/dataTables.buttons.min.js"></script>
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

// Lee una variable CSS del tema (para colores de Chart.js que no aceptan var())
function tmVar(n, fallback) {
    try {
        var v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
        return v || fallback || '#e2e8f0';
    } catch (e) { return fallback || '#e2e8f0'; }
}



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
            paginate: { first: '<i class="fas fa-step-backward"></i>', last: '<i class="fas fa-step-forward"></i>', next: '<i class="fas fa-chevron-right"></i>', previous: '<i class="fas fa-chevron-left"></i>' },
            aria: { sortAscending: ": activar para ordenar la columna de forma ascendente", sortDescending: ": activar para ordenar la columna de forma descendente" }
        },
        pageLength: 5,
        pagingType: 'full_numbers',
        lengthMenu: [[5, 10, 15, -1], [5, 10, 15, "Todos"]],
        responsive: true,
        order: [[1, 'desc']],
        autoWidth: false,
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-3"<"dt-length"l><"dt-buttons"B><"dt-search"f>>rt<"d-flex justify-content-between align-items-center flex-wrap"<"dt-info"i><"dt-pagination"p>>',
        buttons: [
            {
                text: '<i class="fas fa-step-backward me-1"></i>',
                className: 'btn-win btn-sm buttons-first',
                titleAttr: 'Ir al primer registro',
                action: function(e, dt) {
                    dt.page('first').draw(false);
                }
            },
            {
                text: '<i class="fas fa-step-forward me-1"></i>',
                className: 'btn-win btn-sm buttons-last',
                titleAttr: 'Ir al último registro',
                action: function(e, dt) {
                    dt.page('last').draw(false);
                }
            }
        ],
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
// PLUGIN CHART.JS: VALORES EN LOS GRÁFICOS AL EXPORTAR PNG
// Dibuja las etiquetas de valor encima de las barras / sobre los
// sectores de la torta, pero SOLO cuando la exportación lo activa.
// El estado se guarda fuera de chart.options (que en Chart.js v4 es
// un Proxy del tipo resolver y NO debe mutarse, provoca recursión).
// ============================================
var mostrarValoresExportacion = {};
var formattersValoresExportacion = {};

Chart.register({
    id: 'valoresEnGrafico',
    afterDatasetsDraw: function(chart) {
        if (mostrarValoresExportacion[chart.id] !== true) return;
        var cfg = (chart.options && chart.options.plugins && chart.options.plugins.valoresEnGrafico) || {};
        var ctx = chart.ctx;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.font = cfg.font || 'bold 0.6875rem Inter, Segoe UI, sans-serif';
        ctx.fillStyle = cfg.color || (window.themeColor('--txt') || '#ffffff');
        ctx.shadowColor = 'rgba(0, 0, 0, 0.55)';
        ctx.shadowBlur = 4;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 1;
        var skipZero = cfg.skipZero !== false;
        for (var d = 0; d < chart.data.datasets.length; d++) {
            var meta = chart.getDatasetMeta(d);
            if (!meta || meta.hidden) continue;
            var ds = chart.data.datasets[d];
            if (!ds || !ds.data) continue;
            for (var i = 0; i < meta.data.length; i++) {
                var el = meta.data[i];
                if (!el || el.hidden) continue;
                var valor = ds.data[i];
                if (valor === null || valor === undefined) continue;
                if (skipZero && Number(valor) === 0) continue;
                var fmt = formattersValoresExportacion[chart.id];
                var texto = (typeof fmt === 'function') ? fmt(d, i, valor) : String(valor);
                if (texto === '') continue;
                if (typeof el.startAngle === 'number') {
                    // Dona / pastel
                    var medio = (el.startAngle + el.endAngle) / 2;
                    var radio = (el.innerRadius + el.outerRadius) / 2;
                    ctx.textBaseline = 'middle';
                    ctx.fillText(texto, el.x + Math.cos(medio) * radio, el.y + Math.sin(medio) * radio);
                } else if (chart.options && chart.options.indexAxis === 'y') {
                    // Barras horizontales: etiqueta a la derecha del extremo de la barra
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(texto, el.x + 4, el.y);
                } else {
                    // Barras
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(texto, el.x, el.y - 4);
                }
            }
        }
        ctx.restore();
    }
});

function activarValoresExportacion(chart, mostrar) {
    if (!chart || typeof chart.draw !== 'function') return;
    mostrarValoresExportacion[chart.id] = mostrar === true;
    chart.draw();
}

function exportColor() {
    var oscuro = document.documentElement.getAttribute('data-theme') !== 'light';
    var V = function (n, fb) { var v = window.themeColor(n); return v || fb; };
    return {
        a: oscuro ? '#1a1a2e' : '#ffffff',
        b: oscuro ? '#0f0f1a' : '#eef1f7',
        titulo: V('--txt', oscuro ? '#ffffff' : '#1f2937'),
        muted: V('--muted', oscuro ? '#94a3b8' : '#4b5563'),
        faint: V('--faint', oscuro ? '#6b7280' : '#6b7280'),
        blue: V('--blue', oscuro ? '#60a5fa' : '#0078d4'),
        success: V('--color-success-soft', oscuro ? '#4ade80' : '#059669'),
        amber: V('--amber', oscuro ? '#fbbf24' : '#d97706'),
        zebra: oscuro ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.03)',
        borde: oscuro ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.07)',
        bordeCab: oscuro ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
        linea: oscuro ? 'rgba(96,165,250,0.25)' : 'rgba(0,120,212,0.25)',
        lineaFuerte: oscuro ? 'rgba(96,165,250,0.3)' : 'rgba(0,120,212,0.3)',
        lineaAmber: oscuro ? 'rgba(251,191,36,0.3)' : 'rgba(217,119,6,0.3)',
        headerBg: oscuro ? 'rgba(59,130,246,0.15)' : 'rgba(0,120,212,0.08)',
        totalBg: oscuro ? 'rgba(59,130,246,0.12)' : 'rgba(0,120,212,0.08)'
    };
}

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
            container.innerHTML = '<div class="text-center py-5"><i class="fas fa-chart-simple fa-4x mb-3" style="color: var(--faint);"></i><h5 class="mb-2">No hay datos disponibles</h5><p class="mb-0" style="color: var(--muted);">Genera nóminas contabilizadas para visualizar estadísticas</p></div>';
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
                    backgroundColor: 'rgba(' + (window.themeColor('--blue-soft-rgb') || '147, 197, 253') + ', 0.85)',
                    borderColor: (window.themeColor('--blue') || '#3b82f6'),
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.35,
                    categoryPercentage: 0.7,
                    yAxisID: 'y'
                },
                {
                    label: '👥 Cantidad de Trabajadores',
                    data: empleados,
                    backgroundColor: 'rgba(' + (window.themeColor('--color-success-soft-rgb') || '52, 211, 153') + ', 0.85)',
                    borderColor: window.themeColor('--color-success-soft') || '#34d399',
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
                valoresEnGrafico: {
                    display: false
                },
                legend: {
                    position: 'top',
                    labels: {
                        color: (window.themeColor('--txt') || '#e8edf6'),
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: (window.themeColor('--panel') || 'rgba(0, 0, 0, 0.85)'),
                    titleColor: (window.themeColor('--txt') || '#e8edf6'),
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyColor: tmVar('--txt'),
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: (window.themeColor('--blue') || '#3b82f6'),
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: (window.themeColor('--txt') || '#e8edf6'), maxRotation: 45, minRotation: 45 },
                    title: { display: true, text: '📆 Período', color: (window.themeColor('--muted') || '#97a5bb') }
                },
                y: {
                    position: 'left',
                    grid: { color: (window.themeColor('--border') || 'rgba(255,255,255,0.08)') },
                    ticks: { 
                        color: (window.themeColor('--blue') || '#3b82f6'), 
                        callback: function(v) { 
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v); 
                        } 
                    },
                    title: { display: true, text: '💰 Importe Neto', color: (window.themeColor('--blue') || '#3b82f6') }
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    ticks: { 
                        color: (window.themeColor('--color-success-soft') || '#4ade80'), 
                        callback: function(v) { return v + ' 👥'; },
                        stepSize: 1
                    },
                    title: { display: true, text: '👥 Trabajadores', color: (window.themeColor('--color-success-soft') || '#4ade80') }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
    });
    
    formattersValoresExportacion[chartInstance.id] = function(d, i, v) {
        if (d === 0) return '$' + new Intl.NumberFormat('es-ES').format(v);
        return new Intl.NumberFormat('es-ES').format(v);
    };
    
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
    gradiente.addColorStop(0, exportColor().a);
    gradiente.addColorStop(1, exportColor().b);
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // Mostrar temporalmente los valores sobre el gráfico para capturarlos
    activarValoresExportacion(chartInstance, true);
    
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    ctx.fillStyle = exportColor().titulo;
    ctx.font = 'bold 1.5rem Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo, ancho / 2, 14);
    
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = exportColor().muted;
    ctx.font = '0.875rem Inter, Segoe UI, sans-serif';
    ctx.fillText('📅 Generado el ' + fechaCompleta, ancho / 2, 48);
    
    ctx.strokeStyle = exportColor().linea;
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
    
    // Restaurar el gráfico original (sin valores)
    activarValoresExportacion(chartInstance, false);
}
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.chart-container');
    if (container) {
        container.innerHTML = '<div class="text-center py-5"><i class="fas fa-chart-simple fa-4x mb-3" style="color: var(--faint);"></i><h5 class="mb-2">No hay datos disponibles</h5><p class="mb-0" style="color: var(--muted);">Genera nóminas contabilizadas para visualizar estadísticas</p></div>';
    }
});
<?php endif; ?>

// ============================================
// GRÁFICO DE TORTA
// ============================================
<?php if (!empty($distribucion_tipos)): ?>
var tipoChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('tipoChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var labels = <?php echo json_encode($labels_torta); ?>;
    var data = <?php echo json_encode($data_torta); ?>;
    var colors = ['#3b82f6', '#fbbf24', '#4ade80', '#a78bfa', '#f87171'];
    var total = data.reduce(function(a, b) { return a + b; }, 0);
    
    tipoChartInstance = new Chart(ctx, {
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
                borderColor: (window.themeColor('--border') || '#1a1a2e'),
                borderWidth: 3,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                valoresEnGrafico: {
                    display: false
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        color: (window.themeColor('--txt') || '#e8edf6'),
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: (window.themeColor('--panel') || 'rgba(0, 0, 0, 0.85)'),
                    titleColor: (window.themeColor('--txt') || '#e8edf6'),
                    bodyColor: tmVar('--txt'),
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: (window.themeColor('--blue') || '#3b82f6'),
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            cutout: '60%'
        }
    });
    
    formattersValoresExportacion[tipoChartInstance.id] = function(d, i, v) {
        var pct = total > 0 ? ((v / total) * 100).toFixed(1) : '0.0';
        return '$' + new Intl.NumberFormat('es-ES').format(v) + ' (' + pct + '%)';
    };
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
                borderColor: (window.themeColor('--amber') || '#fbbf24'),
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
                valoresEnGrafico: {
                    display: false
                },
                legend: { display: false },
                tooltip: {
                    backgroundColor: (window.themeColor('--panel') || 'rgba(0, 0, 0, 0.85)'),
                    titleColor: (window.themeColor('--txt') || '#e8edf6'),
                    bodyColor: tmVar('--txt'),
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: (window.themeColor('--amber') || '#fbbf24'),
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: (window.themeColor('--txt') || '#e8edf6') },
                    title: { display: true, text: '📆 Mes', color: (window.themeColor('--muted') || '#97a5bb') }
                },
                y: {
                    grid: { color: (window.themeColor('--border') || 'rgba(255,255,255,0.08)') },
                    ticks: { 
                        color: (window.themeColor('--amber') || '#fbbf24'),
                        callback: function(v) {
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v);
                        }
                    },
                    title: { display: true, text: '💰 Monto', color: (window.themeColor('--amber') || '#fbbf24') }
                }
            }
        }
    });
    
    formattersValoresExportacion[distribucionChartInstance.id] = function(d, i, v) {
        return '$' + new Intl.NumberFormat('es-ES').format(v);
    };
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
                    <i class="fas fa-building fa-4x mb-3" style="color: var(--faint);"></i>
                    <h5 class="mb-2">No hay datos disponibles</h5>
                    <p class="mb-0" style="color: var(--muted);">Genera nóminas para visualizar la distribución por centros de costo</p>
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
                    backgroundColor: 'rgba(' + (window.themeColor('--color-success-soft-rgb') || '52, 211, 153') + ', 0.85)',
                    borderColor: window.themeColor('--color-success-soft') || '#34d399',
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
                valoresEnGrafico: {
                    display: false
                },
                legend: {
                    position: 'top',
                    labels: {
                        color: (window.themeColor('--txt') || '#e8edf6'),
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 12,
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: (window.themeColor('--panel') || 'rgba(0, 0, 0, 0.85)'),
                    titleColor: (window.themeColor('--txt') || '#e8edf6'),
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyColor: tmVar('--txt'),
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: (window.themeColor('--blue') || '#3b82f6'),
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
                        color: (window.themeColor('--txt') || '#e8edf6'),
                        maxRotation: 45,
                        minRotation: 45,
                        font: { size: 10 }
                    },
                    title: { display: true, text: '🏢 Centros de Costo', color: (window.themeColor('--muted') || '#97a5bb') }
                },
                y: {
                    position: 'left',
                    grid: { color: (window.themeColor('--border') || 'rgba(255,255,255,0.08)') },
                    ticks: { 
                        color: (window.themeColor('--blue') || '#3b82f6'), 
                        callback: function(v) { 
                            if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000) return '$' + (v / 1000).toFixed(1) + 'K';
                            return '$' + new Intl.NumberFormat('es-CL').format(v); 
                        } 
                    },
                    title: { display: true, text: '💰 Total Salario', color: (window.themeColor('--blue') || '#3b82f6') }
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    ticks: { 
                        color: (window.themeColor('--color-success-soft') || '#4ade80'), 
                        callback: function(v) { return v + ' 👥'; },
                        stepSize: 1
                    },
                    title: { display: true, text: '👥 Empleados', color: (window.themeColor('--color-success-soft') || '#4ade80') }
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
    
    // Datos desde el gráfico para dibujar la tabla debajo
    var labelsCentros = [];
    var salariosCentros = [];
    var empleadosCentros = [];
    var coloresCentros = [];
    if (centrosChartInstance && centrosChartInstance.data) {
        labelsCentros = centrosChartInstance.data.labels || [];
        var dsCentros = centrosChartInstance.data.datasets || [];
        if (dsCentros[0]) salariosCentros = dsCentros[0].data || [];
        if (dsCentros[0]) coloresCentros = dsCentros[0].backgroundColor || [];
        if (dsCentros[1]) empleadosCentros = dsCentros[1].data || [];
    }
    
    var ancho = canvas.width;
    var altoOriginal = canvas.height;
    var alturaTitulo = 100;
    
    // Tabla de datos (los valores van en la tabla, no sobre el gráfico)
    var filaAlto = 34;
    var encabezadoAlto = 36;
    var espacioEncabezado = 18;
    var filasTabla = labelsCentros.length;
    var altoTabla = espacioEncabezado + encabezadoAlto + filasTabla * filaAlto + filaAlto + 16;
    var altoTotal = altoOriginal + alturaTitulo + altoTabla;
    
    var tempCanvas = document.createElement('canvas');
    tempCanvas.width = ancho;
    tempCanvas.height = altoTotal;
    var ctx = tempCanvas.getContext('2d');
    
    // Fondo oscuro
    var gradiente = ctx.createLinearGradient(0, 0, 0, altoTotal);
    gradiente.addColorStop(0, exportColor().a);
    gradiente.addColorStop(1, exportColor().b);
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // Dibujar gráfico (sin valores encima; van en la tabla de abajo)
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    // Título
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    ctx.fillStyle = exportColor().titulo;
    ctx.font = 'bold 1.375rem Inter, Segoe UI, sans-serif';
    ctx.fillText(subtitulo, ancho / 2, 10);
    
    // Subtítulo
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = exportColor().muted;
    ctx.font = '1rem Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo, ancho / 2, 40);
    
    // Período
    ctx.fillStyle = exportColor().blue;
    ctx.font = '0.8125rem Inter, Segoe UI, sans-serif';
    ctx.fillText('📅 ' + periodo, ancho / 2, 64);
    
    // Fecha de generación
    ctx.fillStyle = exportColor().faint;
    ctx.font = '0.6875rem Inter, Segoe UI, sans-serif';
    ctx.fillText('Generado: ' + fechaCompleta, ancho / 2, 86);
    
    // Línea decorativa
    ctx.strokeStyle = exportColor().linea;
    ctx.lineWidth = 1.5;
    ctx.shadowColor = 'transparent';
    ctx.beginPath();
    ctx.moveTo(ancho / 2 - 180, 98);
    ctx.lineTo(ancho / 2 + 180, 98);
    ctx.stroke();
    
    // === TABLA DE DATOS (los valores van aquí, no sobre el gráfico) ===
    var yTabla = alturaTitulo + altoOriginal + espacioEncabezado;
    var xTabla = 24;
    var anchoTabla = ancho - 48;
    var indexW = 45;
    var salarioW = 200;
    var empleadoW = 120;
    var nombreW = anchoTabla - indexW - salarioW - empleadoW;
    
    function fMonedaCentros(v) {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(Number(v) || 0);
    }
    var sumSalarios = salariosCentros.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
    var sumEmpleados = empleadosCentros.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
    
    // Encabezado
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillStyle = exportColor().headerBg;
    ctx.fillRect(xTabla, yTabla, anchoTabla, encabezadoAlto);
    ctx.strokeStyle = exportColor().bordeCab;
    ctx.lineWidth = 1;
    ctx.strokeRect(xTabla, yTabla, anchoTabla, encabezadoAlto);
    ctx.font = 'bold 0.8125rem Inter, Segoe UI, sans-serif';
    ctx.fillStyle = tmVar('--txt');
    ctx.fillText('#', xTabla + 14, yTabla + 10);
    ctx.fillText('Centro de Costo', xTabla + indexW, yTabla + 10);
    ctx.fillText('Total Salario', xTabla + indexW + nombreW, yTabla + 10);
    ctx.fillText('Empleados', xTabla + indexW + nombreW + salarioW, yTabla + 10);
    
    // Filas
    ctx.font = '0.75rem Inter, Segoe UI, sans-serif';
    for (var i = 0; i < filasTabla; i++) {
        var yFila = yTabla + encabezadoAlto + i * filaAlto;
        if (i % 2 === 0) {
            ctx.fillStyle = exportColor().zebra;
            ctx.fillRect(xTabla, yFila, anchoTabla, filaAlto);
        }
        ctx.strokeStyle = exportColor().borde;
        ctx.beginPath();
        ctx.moveTo(xTabla, yFila + filaAlto);
        ctx.lineTo(xTabla + anchoTabla, yFila + filaAlto);
        ctx.stroke();
        
        // Número
        ctx.fillStyle = tmVar('--faint');
        ctx.fillText(String(i + 1), xTabla + 14, yFila + 10);
        
        // Nombre con punto de color
        var colorCelda = coloresCentros[i] || '#3b82f6';
        ctx.fillStyle = colorCelda;
        ctx.beginPath();
        ctx.arc(xTabla + indexW + 10, yFila + 17, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = tmVar('--txt');
        ctx.fillText(String(labelsCentros[i] || ''), xTabla + indexW + 24, yFila + 10);
        
        // Salario
        ctx.fillStyle = exportColor().blue;
        ctx.font = 'bold 0.75rem Inter, Segoe UI, sans-serif';
        ctx.fillText(fMonedaCentros(salariosCentros[i]), xTabla + indexW + nombreW, yFila + 10);
        
        // Empleados
        ctx.fillStyle = exportColor().success;
        ctx.font = '0.75rem Inter, Segoe UI, sans-serif';
        ctx.fillText(String(empleadosCentros[i]) + ' 👥', xTabla + indexW + nombreW + salarioW, yFila + 10);
    }
    
    // Fila de totales
    var yTotal = yTabla + encabezadoAlto + filasTabla * filaAlto;
    ctx.fillStyle = exportColor().totalBg;
    ctx.fillRect(xTabla, yTotal, anchoTabla, filaAlto);
    ctx.strokeStyle = exportColor().lineaFuerte;
    ctx.beginPath();
    ctx.moveTo(xTabla, yTotal);
    ctx.lineTo(xTabla + anchoTabla, yTotal);
    ctx.stroke();
    ctx.font = 'bold 0.75rem Inter, Segoe UI, sans-serif';
    ctx.fillStyle = exportColor().titulo;
    ctx.fillText('TOTAL', xTabla + indexW, yTotal + 10);
    ctx.fillStyle = exportColor().blue;
    ctx.fillText(fMonedaCentros(sumSalarios), xTabla + indexW + nombreW, yTotal + 10);
    ctx.fillStyle = exportColor().success;
    ctx.fillText(String(sumEmpleados) + ' 👥', xTabla + indexW + nombreW + salarioW, yTotal + 10);
    
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
                    <i class="fas fa-building fa-4x mb-3" style="color: var(--faint);"></i>
                    <h5 class="mb-2">No hay datos disponibles</h5>
                    <p class="mb-0" style="color: var(--muted);">Genera nóminas para visualizar la distribución por centros de costo</p>
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
            title: '<i class="fas fa-info-circle me-2" style="color: var(--amber);"></i> Sin datos',
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
            title: '<i class="fas fa-check-circle me-2" style="color: 4;"></i> Exportación Completada',
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
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: var(--red);"></i> Error',
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
            title: '<i class="fas fa-info-circle me-2" style="color: var(--amber);"></i> Sin datos',
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
                padding:1.25rem;
                color: #000000 !important;
                background: #ffffff;
            }
            .titulo {
                text-align: center;
                font-size:1.125rem;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom:0.3125rem;
            }
            .subtitulo {
                text-align: center;
                font-size:0.875rem;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom:0.3125rem;
            }
            .fecha {
                text-align: center;
                font-size:0.625rem;
                color: #666666;
                margin-bottom:0.9375rem;
            }
            table {
                width:100%;
                border-collapse: collapse;
                font-size:0.625rem;
                color: #000000 !important;
            }
            table th {
                background-color: #1A56DB;
                color: #FFFFFF !important;
                padding:0.375rem 0.5rem;
                border: 0.0625rem solid #000000;
                text-align: center;
                font-weight: bold;
            }
            table td {
                padding:0.3125rem 0.5rem;
                border: 0.0625rem solid #000000;
                text-align: left;
                color: #000000 !important;
            }
            table tr:nth-child(even) td {
                background-color: #f5f5f5;
            }
            table tfoot td {
                background-color: #e5e7eb;
                font-weight: bold;
                padding:0.375rem 0.5rem;
                border: 0.0625rem solid #000000;
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
    tempDiv.style.left = '-624.9375rem';
    tempDiv.style.top = '0';
    tempDiv.style.width = '68.75rem';
    tempDiv.style.background = '#ffffff';
    tempDiv.style.padding = '0.9375rem';
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
            title: '<i class="fas fa-check-circle me-2" style="color: 4;"></i> PDF Generado',
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
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: var(--red);"></i> Error',
            text: 'Error al generar el PDF: ' + error.message,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
            background: '#1a1a2e',
            color: '#ffffff'
        });
    });
}


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
    gradiente.addColorStop(0, exportColor().a);
    gradiente.addColorStop(1, exportColor().b);
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, anchoReal, altoFinal / escala);
    
    // === DIBUJAR EL GRÁFICO ORIGINAL ===
    // Mostrar temporalmente los valores sobre el gráfico para capturarlos
    activarValoresExportacion(tipoChartInstance, true);
    ctx.drawImage(canvas, 0, alturaTitulo / escala, anchoReal, altoOriginal / escala);
    
    // === TÍTULO PRINCIPAL ===
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    
    ctx.fillStyle = exportColor().titulo;
    ctx.font = `bold ${24 / escala}px Inter, Segoe UI, sans-serif`;
    ctx.fillText(titulo, anchoReal / 2, 14 / escala);
    
    // === SUBTÍTULO CON FECHA ===
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = exportColor().muted;
    ctx.font = `${14 / escala}px Inter, Segoe UI, sans-serif`;
    ctx.fillText(`📅 Generado el ${fechaCompleta}`, anchoReal / 2, 48 / escala);
    
    // === LÍNEA DECORATIVA ===
    ctx.strokeStyle = exportColor().linea;
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
    
    // Restaurar el gráfico original (sin valores)
    activarValoresExportacion(tipoChartInstance, false);
}
// ============================================
// ACTUALIZAR DASHBOARD (RECARGAR DATOS)
// ============================================
function actualizarDashboard() {
    // Mostrar animación de carga en el KPI
    var kpiElement = document.getElementById('ultimaActualizacion');
    if (kpiElement) {
        kpiElement.innerHTML = '<i class="fas fa-spinner fa-pulse me-2" style="color: var(--blue);"></i> Actualizando...';
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
    elemento.innerHTML = '<i class="fas fa-clock me-2" style="color: var(--muted);"></i> ' + fechaStr + ' - ' + horaStr;
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
            title: '<i class="fas fa-check-circle me-2" style="color: 4;"></i> Exportación Completada',
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
            body { font-family: Arial, sans-serif; padding:1.25rem; color: #000000; background: #ffffff; }
            .titulo { text-align: center; font-size:1.25rem; font-weight: bold; color: #1A56DB; margin-bottom:0.3125rem; }
            .subtitulo { text-align: center; font-size:1rem; font-weight: bold; color: #374151; margin-bottom:0.3125rem; }
            .anio { text-align: center; font-size:0.875rem; font-weight: bold; color: #1A56DB; margin-bottom:0.625rem; }
            .fecha { text-align: center; font-size:0.625rem; color: #666; margin-bottom:1.25rem; }
            table { width:100%; border-collapse: collapse; font-size:0.625rem; margin-bottom:1.25rem; }
            th { background-color: #1A56DB; color: white; padding:0.5rem 0.625rem; border: 0.0625rem solid #000; text-align: center; font-weight: bold; }
            td { padding:0.375rem 0.625rem; border: 0.0625rem solid #000; text-align: center; }
            tr:nth-child(even) td { background-color: #f5f5f5; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .fw-bold { font-weight: bold; }
            .text-success { color: #16a34a; }
            .text-danger { color: #dc2626; }
            
            /* Estilos para el resumen */
            .resumen { margin-top:1.25rem; border-top: 0.125rem solid #1A56DB; padding-top:0.9375rem; }
            .resumen-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:0.625rem; margin-top:0.625rem; }
            .resumen-item { background: #f0f4ff; padding:0.625rem; border-radius: 0.5rem; text-align: center; }
            .resumen-item .label { font-size:0.5625rem; color: #666; }
            .resumen-item .value { font-size:0.875rem; font-weight: bold; color: #1A56DB; }
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
            <div style="font-size:0.875rem; font-weight: bold; color: #1A56DB; text-align: center; margin-bottom:0.625rem;">
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
    tempDiv.style.left = '-624.9375rem';
    tempDiv.style.top = '0';
    tempDiv.style.width = '68.75rem';
    tempDiv.style.background = '#ffffff';
    tempDiv.style.padding = '1.25rem';
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
            title: '<i class="fas fa-check-circle me-2" style="color: 4;"></i> PDF Generado',
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
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: var(--red);"></i> Error',
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
    gradiente.addColorStop(0, exportColor().a);
    gradiente.addColorStop(1, exportColor().b);
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // === DIBUJAR EL GRÁFICO ORIGINAL ===
    // Mostrar temporalmente los valores sobre el gráfico para capturarlos
    activarValoresExportacion(distribucionChartInstance, true);
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    // === TÍTULO PRINCIPAL ===
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    
    ctx.fillStyle = exportColor().amber;
    ctx.font = 'bold 1.25rem Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo + ' - <?php echo $anio_seleccionado; ?>', ancho / 2, 10);
    
    // === SUBTÍTULO CON FECHA ===
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = exportColor().muted;
    ctx.font = '0.75rem Inter, Segoe UI, sans-serif';
    ctx.fillText(`📅 Generado: ${fechaCompleta}`, ancho / 2, 42);
    
    // === LÍNEA DECORATIVA ===
    ctx.strokeStyle = exportColor().lineaAmber;
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
    
    // Restaurar el gráfico original (sin valores)
    activarValoresExportacion(distribucionChartInstance, false);
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
            title: '<i class="fas fa-check-circle me-2" style="color: 4;"></i> Exportación Completada',
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
                <i class="fas fa-birthday-cake fa-4x mb-3" style="color: var(--faint);"></i>
                <h5 class="mb-2">No hay trabajadores con CI válido</h5>
                <p class="mb-0" style="color: var(--muted);">Asegúrate de que los CI tengan 11 dígitos</p>
            </div>
        `;
        if (cumpleCounter) cumpleCounter.textContent = '0 de 0';
        if (listaContainer) listaContainer.innerHTML = '<div class="text-center py-3" style="color: var(--faint);">Sin datos</div>';
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
    ? `<img src="${fotoSrc}" alt="${item.nombre}" class="rounded-circle" style="width:12.5rem; height:12.5rem; object-fit: cover; border: 0.1875rem solid rgba(255,255,255,0.15);" onerror="this.onerror=null; this.outerHTML='<div class=\\'rounded-circle d-flex align-items-center justify-content-center\\' style=\\'width:12.5rem;height:12.5rem;border:0.1875rem solid rgba(255,255,255,0.15);background:linear-gradient(135deg,rgba(251,191,36,0.25),rgba(255,255,255,0.05));\\'><i class=\\'fas fa-birthday-cake\\' style=\\'font-size:5.625rem;color:#fbbf24;\\'></i></div>';">`
    : `<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:12.5rem;height:12.5rem;border:0.1875rem solid rgba(255,255,255,0.15);background:linear-gradient(135deg,rgba(251,191,36,0.25),rgba(255,255,255,0.05));"><i class="fas fa-birthday-cake" style="font-size:5.625rem;color:var(--amber);"></i></div>`;

    var html = `
        <div class="d-flex flex-column align-items-center">
            <div class="mb-3">
                ${fotoHtml}
            </div>
            <div style="width:100%; min-height:2.7em; display:flex; align-items:flex-start; justify-content:center;">
                <h4 class="mb-0" style="cursor: pointer; color: var(--blue); line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" onclick="abrirEmpleado(${item.id})">${item.nombre}</h4>
            </div>
            <p class="mb-0" style="color: var(--muted);">
                <i class="fas fa-calendar-alt me-1"></i> ${item.fecha}
            </p>
            <p class="mb-2" style="color: var(--amber); font-weight: 600;">
                <i class="fas fa-star me-1"></i> Cumple ${item.edad} año${item.edad !== 1 ? 's' : ''}
                <span class="badge bg-success ms-2">${item.dias} día${item.dias !== 1 ? 's' : ''}</span>
            </p>
            <div class="d-flex gap-2 mt-2">
                <button class="btn-win btn-win-sm" id="firstCumple" title="Primero" ${index === 0 ? 'disabled' : ''}>
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button class="btn-win btn-win-sm" id="prevCumple" title="Anterior" ${index === 0 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn-win btn-win-sm" id="nextCumple" title="Siguiente" ${index === total - 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button class="btn-win btn-win-sm" id="lastCumple" title="Último" ${index === total - 1 ? 'disabled' : ''}>
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
        </div>
    `;

    cumpleContainer.innerHTML = html;

    document.getElementById('firstCumple')?.addEventListener('click', function() {
        if (currentIndex !== 0) {
            currentIndex = 0;
            renderCumpleanero(currentIndex);
            renderLista();
        }
    });
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
    document.getElementById('lastCumple')?.addEventListener('click', function() {
        if (currentIndex !== cumpleaneros.length - 1) {
            currentIndex = cumpleaneros.length - 1;
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
        listaContainer.innerHTML = '<div class="text-center py-3" style="color: var(--faint);">Sin datos</div>';
        return;
    }

    // Tomar los 10 primeros (ya ordenados por días)
    var top10 = cumpleaneros.slice(0, 10);
    var html = '';
    top10.forEach(function(emp, idx) {
        var fotoMini = emp.foto && emp.foto.trim() !== '' 
            ? `<img src="${emp.foto.trim()}" style="width:3.125rem; height:3.125rem; object-fit: cover; border-radius: 50%; border: 0.0625rem solid rgba(255,255,255,0.1);" onerror="this.onerror=null; this.outerHTML='<i class=\\'fas fa-birthday-cake\\' style=\\'font-size: 1.375rem; color: #fbbf24; width: 3.125rem; text-align: center;\\'></i>';">`
            : `<i class="fas fa-birthday-cake" style="font-size:1.375rem; color: var(--amber); width:3.125rem; text-align: center;"></i>`;
        
        var activeClass = (idx === currentIndex) ? 'border-left border-primary' : '';
        html += `
            <div class="d-flex align-items-center gap-2 p-1 mb-1 rounded ${activeClass}" style="cursor: pointer; background: ${idx === currentIndex ? 'rgba(96,165,250,0.15)' : 'transparent'}; transition: background 0.2s;" onclick="irACumpleanero(${idx})">
                <div style="flex-shrink: 0;">${fotoMini}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:1rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; color: var(--blue);" onclick="abrirEmpleado(${emp.id})">${emp.nombre}</div>
                    <div style="font-size:0.9rem; color: var(--muted); display: flex; gap:0.5rem;">
                        <span><i class="fas fa-calendar-alt me-1"></i>${emp.fecha}</span>
                        <span style="color: var(--amber);">${emp.edad} años</span>
                        <span class="badge bg-success" style="font-size:0.8rem;">${emp.dias}d</span>
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

// ============================================
// GRÁFICO: DISTRIBUCIÓN POR ÁREA DE TRABAJO
// ============================================
<?php if (!empty($areas_datos)): ?>
var areaChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('areaChart');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var labels = <?php echo json_encode($areas_labels); ?>;
    var datos = <?php echo json_encode($areas_datos); ?>;
    var colores = <?php echo json_encode($areas_colores); ?>;
    
    areaChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Empleados',
                data: datos,
                backgroundColor: colores.map(function(c) { return c + 'CC'; }),
                borderColor: colores,
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.75
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { right: 44 }
            },
            animation: { duration: 1200, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: (window.themeColor('--panel') || 'rgba(0, 0, 0, 0.85)'),
                    titleColor: (window.themeColor('--txt') || '#e8edf6'),
                    bodyColor: tmVar('--txt'),
                    titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    borderColor: (window.themeColor('--blue') || '#3b82f6'),
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            var v = context.raw;
                            return '👥 ' + v + ' empleado' + (v !== 1 ? 's' : '');
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: (window.themeColor('--border') || 'rgba(255,255,255,0.08)') },
                    ticks: {
                        color: (window.themeColor('--muted') || '#94a3b8'),
                        stepSize: 1,
                        precision: 0
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: (window.themeColor('--txt') || '#e8edf6'),
                        font: { size: 11 }
                    }
                }
            }
        }
    });
    
    formattersValoresExportacion[areaChartInstance.id] = function(d, i, v) {
        return String(v);
    };
});
<?php endif; ?>

// ============================================
// EXPORTAR GRÁFICO DE ÁREAS DE TRABAJO A PNG
// ============================================
function exportarAreaChart() {
    var canvas = document.getElementById('areaChart');
    if (!canvas || !areaChartInstance) {
        Swal.fire('Error', 'No hay datos por área para exportar', 'error');
        return;
    }
    
    var ahora = new Date();
    var añoActual = ahora.getFullYear();
    var mesActual = ahora.toLocaleString('es-ES', { month: 'long' });
    var diaActual = ahora.getDate();
    var fechaCompleta = diaActual + ' de ' + mesActual + ' de ' + añoActual;
    
    var titulo = 'Distribución por Área de Trabajo';
    
    var ancho = canvas.width;
    var altoOriginal = canvas.height;
    var alturaTitulo = 85;
    var altoTotal = altoOriginal + alturaTitulo;
    
    var tempCanvas = document.createElement('canvas');
    tempCanvas.width = ancho;
    tempCanvas.height = altoTotal;
    var ctx = tempCanvas.getContext('2d');
    
    var gradiente = ctx.createLinearGradient(0, 0, 0, altoTotal);
    gradiente.addColorStop(0, exportColor().a);
    gradiente.addColorStop(1, exportColor().b);
    ctx.fillStyle = gradiente;
    ctx.fillRect(0, 0, ancho, altoTotal);
    
    // Mostrar temporalmente los valores sobre el gráfico para capturarlos
    activarValoresExportacion(areaChartInstance, true);
    
    ctx.drawImage(canvas, 0, alturaTitulo, ancho, altoOriginal);
    
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
    ctx.shadowBlur = 8;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;
    ctx.fillStyle = exportColor().titulo;
    ctx.font = 'bold 1.5rem Inter, Segoe UI, sans-serif';
    ctx.fillText(titulo, ancho / 2, 14);
    
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = exportColor().muted;
    ctx.font = '0.875rem Inter, Segoe UI, sans-serif';
    ctx.fillText('📅 Generado el ' + fechaCompleta, ancho / 2, 48);
    
    ctx.strokeStyle = exportColor().linea;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(ancho / 2 - 180, 72);
    ctx.lineTo(ancho / 2 + 180, 72);
    ctx.stroke();
    
    var link = document.createElement('a');
    link.download = 'distribucion_areas_' + añoActual + '-' + String(ahora.getMonth()+1).padStart(2,'0') + '-' + String(ahora.getDate()).padStart(2,'0') + '.png';
    link.href = tempCanvas.toDataURL('image/png', 1.0);
    link.click();
    
    // Restaurar el gráfico original (sin valores)
    activarValoresExportacion(areaChartInstance, false);
}

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

<!-- Modal: Trabajadores con más de 20 días de vacaciones en el submayor (Ley 116) -->
<div class="modal fade" id="modalVacacionesExcedidas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: var(--panel); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255,255,255,0.1); border-radius: 1.25rem; color: var(--txt);">
            <div class="modal-header" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08);">
                <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2" style="color: var(--amber);"></i>Vacaciones Excedidas en el Submayor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style=""></button>
            </div>
            <div class="modal-body">
                <p style="color: var(--muted); font-size:0.9rem;">Trabajadores activos con más de 20 días acumulados según el libro submayor de vacaciones.</p>
                <div class="table-responsive" style="max-height:55vh;">
                    <table class="table table-sm table-dark table-striped align-middle mb-0" id="tablaVacacionesExcedidas">
                        <thead>
                            <tr>
                                <th>No. Expediente</th>
                                <th>Nombre y Apellidos</th>
                                <th>Área</th>
                                <th>Centro de Costo</th>
                                <th class="text-end">Días en Submayor</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid var(--border);">
                <button type="button" class="btn-win btn-win-primary btn-win-sm" onclick="window.location.href='modules/submayor_vacaciones.php'" title="Submayor de Vacaciones" data-tooltip="Submayor de Vacaciones" data-tooltip-theme="primary"><i class="fas fa-book-open me-1"></i> Ir al Submayor</button>
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nóminas en BORRADOR -->
<div class="modal fade" id="modalNominasBorrador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: var(--panel); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255,255,255,0.1); border-radius: 1.25rem; color: var(--txt);">
            <div class="modal-header" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08);">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2" style="color: var(--amber);"></i>Nóminas en Estado Borrador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style=""></button>
            </div>
            <div class="modal-body">
                <p style="color: var(--muted); font-size:0.9rem;">Nóminas pendientes de revisión por periodo y tipo de nómina.</p>
                <div class="table-responsive" style="max-height:55vh;">
                    <table class="table table-sm table-dark table-striped align-middle mb-0" id="tablaNominasBorrador">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Tipo de Nómina</th>
                                <th class="text-end">Empleados</th>
                                <th class="text-end">Total Devengado</th>
                                <th class="text-end">Total Deducciones</th>
                                <th class="text-end">Total Neto</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid var(--border);">
                <button type="button" class="btn-win btn-win-primary btn-win-sm" onclick="window.location.href='modules/nominas.php'" title="Ir a Nóminas del mes" data-tooltip="Ir a Nóminas del mes" data-tooltip-theme="primary"><i class="fas fa-file-invoice me-1"></i> Ir a Nóminas</button>
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal: Cumpleaños en los próximos 30 días -->
<div class="modal fade" id="modalCumpleanerosProximos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: var(--panel); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255,255,255,0.1); border-radius: 1.25rem; color: var(--txt);">
            <div class="modal-header" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08);">
                <h5 class="modal-title"><i class="fas fa-birthday-cake me-2" style="color: var(--blue);"></i>Cumpleaños en los Próximos 30 Días</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style=""></button>
            </div>
            <div class="modal-body">
                <p style="color: var(--muted); font-size:0.9rem;">Trabajadores que celebran su cumpleaños en los próximos 30 días (derivado del CI).</p>
                <div class="table-responsive" style="max-height:55vh;">
                    <table class="table table-sm table-dark table-striped align-middle mb-0" id="tablaCumpleanerosProximos">
                        <thead>
                            <tr>
                                <th style="width:3.125rem;">Foto</th>
                                <th>Nombre y Apellidos</th>
                                <th>Fecha</th>
                                <th class="text-end">Días Restantes</th>
                                <th class="text-end">Edad</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 0.0625rem solid var(--border);">
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>


<script>
var TRABAJADORES_VACACIONES_EXCEDIDAS = <?php echo json_encode($trabajadores_excedidos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var NOMINAS_BORRADOR = <?php echo json_encode($nominas_borrador, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var CUMPLEANEROS_PROXIMOS = <?php echo json_encode($cumpleaneros_proximos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

var ETIQUETAS_TIPO_NOMINA = {
    'automatica': 'Automática',
    'ordinaria': 'Ordinaria',
    'extraordinaria': 'Extraordinaria',
    'vacaciones': 'Vacaciones',
    'bono': 'Bono/Rendimiento',
    'rendimiento': 'Rendimiento',
    'ajuste': 'Ajuste'
};

function mostrarDetalleAlertas(tipo) {
    if (tipo === 'vacaciones_excedidas') {
        var tbody = document.querySelector('#tablaVacacionesExcedidas tbody');
        tbody.innerHTML = '';
        (TRABAJADORES_VACACIONES_EXCEDIDAS || []).forEach(function (t) {
            var tr = document.createElement('tr');
            var tdExp = document.createElement('td');
            tdExp.className = 'fw-bold';
            tdExp.textContent = t.codigo || '-';
            var tdNombre = document.createElement('td');
            tdNombre.textContent = t.nombre_completo || '-';
            var tdArea = document.createElement('td');
            tdArea.textContent = t.nombre_area || '-';
            var tdCC = document.createElement('td');
            tdCC.textContent = t.centro_costo || '-';
            var tdDias = document.createElement('td');
            tdDias.className = 'text-end fw-bold text-warning';
            tdDias.textContent = parseFloat(t.saldo_submayor).toFixed(2);
            tr.appendChild(tdExp);
            tr.appendChild(tdNombre);
            tr.appendChild(tdArea);
            tr.appendChild(tdCC);
            tr.appendChild(tdDias);
            tbody.appendChild(tr);
        });
        new bootstrap.Modal(document.getElementById('modalVacacionesExcedidas')).show();
} else if (tipo === 'nominas_borrador') {
    var tbody2 = document.querySelector('#tablaNominasBorrador tbody');
    tbody2.innerHTML = '';
    (NOMINAS_BORRADOR || []).forEach(function (n) {
        var tr = document.createElement('tr');
        var tdPer = document.createElement('td');
        tdPer.className = 'fw-bold';
        tdPer.textContent = n.periodo || '-';
        var tdTipo = document.createElement('td');
        tdTipo.textContent = ETIQUETAS_TIPO_NOMINA[n.tipo_nomina] || n.tipo_nomina || '-';
        var tdEmp = document.createElement('td');
        tdEmp.className = 'text-end';
        tdEmp.textContent = n.total_empleados || 0;
        var tdDev = document.createElement('td');
        tdDev.className = 'text-end text-success';
        tdDev.textContent = '$' + parseFloat(n.total_devengado || 0).toFixed(2);
        var tdDed = document.createElement('td');
        tdDed.className = 'text-end text-danger';
        // Calcular deducciones: devengado - neto (aproximación)
        var deducciones = parseFloat(n.total_devengado || 0) - parseFloat(n.total_neto || 0);
        tdDed.textContent = '$' + deducciones.toFixed(2);
        var tdNeto = document.createElement('td');
        tdNeto.className = 'text-end fw-bold text-warning';
        tdNeto.textContent = '$' + parseFloat(n.total_neto || 0).toFixed(2);
        tr.appendChild(tdPer);
        tr.appendChild(tdTipo);
        tr.appendChild(tdEmp);
        tr.appendChild(tdDev);
        tr.appendChild(tdDed);
        tr.appendChild(tdNeto);
        tbody2.appendChild(tr);
    });
    new bootstrap.Modal(document.getElementById('modalNominasBorrador')).show();
} else if (tipo === 'cumpleaneros_proximos') {
    var tbody3 = document.querySelector('#tablaCumpleanerosProximos tbody');
    tbody3.innerHTML = '';
    (CUMPLEANEROS_PROXIMOS || []).forEach(function (c) {
        var tr = document.createElement('tr');
        
        // Columna Foto
        var tdFoto = document.createElement('td');
        tdFoto.className = 'text-center';
        var fotoSrc = c.foto && c.foto.trim() !== '' ? c.foto.trim() : null;
        if (fotoSrc) {
            tdFoto.innerHTML = `<img src="${fotoSrc}" alt="${c.nombre}" style="width:2.5rem; height:2.5rem; object-fit: cover; border-radius: 50%; border: 0.125rem solid rgba(255,255,255,0.15);" onerror="this.onerror=null; this.outerHTML='<div class=\\'d-flex align-items-center justify-content-center\\' style=\\'width:2.5rem;height:2.5rem;border-radius:50%;background:rgba(251,191,36,0.2);border:0.125rem solid rgba(255,255,255,0.1);\\'><i class=\\'fas fa-user\\' style=\\'color:#fbbf24;font-size:1rem;\\'></i></div>';">`;
        } else {
            tdFoto.innerHTML = `<div class="d-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;border-radius:50%;background:rgba(251,191,36,0.2);border:0.125rem solid rgba(255,255,255,0.1);"><i class="fas fa-user" style="color:var(--amber);font-size:1rem;"></i></div>`;
        }
        
        var tdNombre = document.createElement('td');
        tdNombre.className = 'fw-bold';
        tdNombre.textContent = c.nombre || '-';
        
        var tdFecha = document.createElement('td');
        tdFecha.textContent = c.fecha || '-';
        
        var tdDias = document.createElement('td');
        tdDias.className = 'text-end fw-bold ' + (c.dias === 0 ? 'text-success' : 'text-info');
        tdDias.textContent = c.dias === 0 ? 'HOY' : c.dias + ' día(s)';
        
        var tdEdad = document.createElement('td');
        tdEdad.className = 'text-end';
        tdEdad.textContent = c.edad || 0;
        
        tr.appendChild(tdFoto);
        tr.appendChild(tdNombre);
        tr.appendChild(tdFecha);
        tr.appendChild(tdDias);
        tr.appendChild(tdEdad);
        tbody3.appendChild(tr);
    });
    new bootstrap.Modal(document.getElementById('modalCumpleanerosProximos')).show();
}
}
</script>

<?php if (!empty($cumpleanios_usuario_hoy)): ?>
<script>
window.PERFIL_CUMPLEANOS = {
    nombre: <?php echo json_encode($user_nombre_completo, JSON_UNESCAPED_UNICODE); ?>,
    anios: <?php echo (int)$anios_cumplidos; ?>
};
</script>
<script src="js/birthday_animation.js"></script>
<?php endif; ?>

<script>
// ============================================
// BARRA: SOLICITUD DE CAMBIO DE CONTRASEÑA PENDIENTE
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    var barra = document.getElementById('barraResetPendiente');
    if (!barra) return;

    function ocultarBarra() {
        if (!barra || barra.classList.contains('brp-oculta')) return;
        barra.classList.add('brp-oculta');
        setTimeout(function () {
            if (barra && barra.parentNode) barra.parentNode.removeChild(barra);
        }, 450);
    }

    // Auto-ocultar a los 15 segundos (no borra el token; reaparece al recargar)
    var timer = setTimeout(ocultarBarra, 15000);
    barra.addEventListener('mouseenter', function () { clearTimeout(timer); });
    barra.addEventListener('mouseleave', function () {
        clearTimeout(timer);
        timer = setTimeout(ocultarBarra, 15000);
    });

    var btnCerrar = document.getElementById('btnResetCerrar');
    if (btnCerrar) btnCerrar.addEventListener('click', ocultarBarra);

    // "No, ahora no" -> limpiar reset_token en la BD
    var btnNo = document.getElementById('btnResetNo');
    if (btnNo) btnNo.addEventListener('click', function () {
        btnNo.disabled = true;
        fetch('ajax/pendiente_reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=descartar'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                ocultarBarra();
            } else {
                btnNo.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo procesar la solicitud', background: '#0f172a', color: '#fff', confirmButtonColor: '#ef4444' });
            }
        })
        .catch(function () {
            btnNo.disabled = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', background: '#0f172a', color: '#fff' });
        });
    });

    // "Sí, restablecer" -> pedir la nueva contraseña
    var btnSi = document.getElementById('btnResetSi');
    if (btnSi) btnSi.addEventListener('click', function () {
        Swal.fire({
            title: 'Restablecer Contraseña',
            html: `
                <div style="text-align:left;">
                    <div style="margin-bottom:0.875rem;">
                        <label style="display:block; color:#eee; margin-bottom:0.375rem; font-size:0.9rem;">Nueva contraseña</label>
                        <div style="position: relative;">
                            <input type="password" id="rpNueva" class="form-control" placeholder="Mínimo 6 caracteres" autocomplete="new-password" style="padding-right:2.75rem;">
                            <button type="button" id="toggleRpNueva" title="Mostrar/ocultar contraseña" style="position: absolute; right:0.375rem; top:50%; transform: translateY(-50%); width:2rem; height:2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59,130,246,0.15); border: none; cursor: pointer; color: var(--blue); z-index: 10;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; color:#eee; margin-bottom:0.375rem; font-size:0.9rem;">Confirmar contraseña</label>
                        <div style="position: relative;">
                            <input type="password" id="rpConfirma" class="form-control" placeholder="Repita la contraseña" autocomplete="new-password" style="padding-right:2.75rem;">
                            <button type="button" id="toggleRpConfirma" title="Mostrar/ocultar contraseña" style="position: absolute; right:0.375rem; top:50%; transform: translateY(-50%); width:2rem; height:2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59,130,246,0.15); border: none; cursor: pointer; color: var(--blue); z-index: 10;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>`,
            background: '#0f172a',
            color: '#eee',
            width: 450,
            didOpen: function () {
                function enlazarOjo(idInput, idBoton) {
                    var input = document.getElementById(idInput);
                    var boton = document.getElementById(idBoton);
                    if (!input || !boton) return;
                    boton.addEventListener('click', function () {
                        var esOculto = input.getAttribute('type') === 'password';
                        input.setAttribute('type', esOculto ? 'text' : 'password');
                        var icono = boton.querySelector('i');
                        if (icono) {
                            icono.classList.toggle('fa-eye', !esOculto);
                            icono.classList.toggle('fa-eye-slash', esOculto);
                        }
                    });
                }
                enlazarOjo('rpNueva', 'toggleRpNueva');
                enlazarOjo('rpConfirma', 'toggleRpConfirma');
            },
            confirmButtonText: '<i class="fas fa-save me-1"></i> Guardar',
            confirmButtonColor: '#f59e0b',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#475569',
            preConfirm: function () {
                var p1 = document.getElementById('rpNueva').value;
                var p2 = document.getElementById('rpConfirma').value;
                if (!p1 || p1.length < 6) {
                    Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
                    return false;
                }
                if (p1 !== p2) {
                    Swal.showValidationMessage('Las contraseñas no coinciden');
                    return false;
                }
                return p1;
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            Swal.fire({
                title: 'Guardando...',
                html: '<i class="fas fa-spinner fa-spin fa-2x" style="color:var(--amber);"></i>',
                background: '#0f172a',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            fetch('ajax/pendiente_reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=restablecer&password_nueva=' + encodeURIComponent(result.value)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Contraseña actualizada',
                        text: 'Su contraseña se cambió correctamente. A partir de ahora utilícela para acceder al sistema.',
                        background: '#0f172a', color: '#eee', confirmButtonColor: '#22c55e'
                    });
                    ocultarBarra();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo cambiar la contraseña', background: '#0f172a', color: '#fff', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', background: '#0f172a', color: '#fff' });
            });
        });
    });
});
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
    <button type="button" class="scroll-quick-btn" id="btnCollapseAll" title="Expandir todas las tarjetas" data-tooltip="Expandir todas las tarjetas" data-tooltip-theme="primary">
        <i class="fas fa-chevron-down"></i>
    </button>
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
<script>
(function () {
    var btn = document.getElementById('btnCollapseAll');
    if (!btn) return;

    var titles = document.querySelectorAll('.card-collapse-title');
    var todasColapsadas = true;

    function redimensionarGraficos() {
        var instancias = [chartInstance, tipoChartInstance, distribucionChartInstance, centrosChartInstance, areaChartInstance];
        instancias.forEach(function (c) {
            if (c && typeof c.resize === 'function') {
                try { c.resize(); } catch (e) {}
            }
        });
    }

    function aplicar(expandir) {
        titles.forEach(function (title) {
            var target = title.getAttribute('data-bs-target');
            if (!target) return;
            var el = document.querySelector(target);
            if (!el) return;
            var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            if (expandir) { inst.show(); } else { inst.hide(); }
        });
        todasColapsadas = !expandir;
        actualizarEstado();
        if (expandir) { setTimeout(redimensionarGraficos, 400); }
    }

    function actualizarEstado() {
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = todasColapsadas ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }
        btn.title = todasColapsadas ? 'Expandir todas las tarjetas' : 'Colapsar todas las tarjetas';
    }

    btn.addEventListener('click', function () { aplicar(todasColapsadas); });
    actualizarEstado();
})();
</script>
<script>
(function () {
    var initialTheme = document.documentElement.getAttribute('data-theme');
    window.addEventListener('themechange', function (e) {
        var t = e.detail && e.detail.theme;
        if (t && t !== initialTheme) { window.location.reload(); }
    });
})();
</script>
<!-- Toggle de los cuadres por mes (Visor de Cuadres) -->
<script>
function toggleCierresMes(id, header) {
    var body = document.getElementById(id);
    var chevron = header ? header.querySelector('.cierres-chevon') : null;
    if (!body) return;
    var visible = body.style.display === 'block';
    body.style.display = visible ? 'none' : 'block';
    if (chevron) chevron.style.transform = visible ? '' : 'rotate(180deg)';
}
// Si el card principal se colapsa/expande, mantener coherencia visual del chevron global
document.getElementById('collapseCierresMeses')?.addEventListener('hidden.bs.collapse', function () {
    document.querySelectorAll('.cierres-body').forEach(function (el) { el.style.display = 'none'; });
    document.querySelectorAll('.cierres-chevon').forEach(function (el) { el.style.transform = ''; });
});
</script>
</body>
</html>