<?php
// modules/reportes.php - Sistema de Reportes Estadísticos y Financieros (Sin Listado)
require_once '../config.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Configuración regional para fechas en español
setlocale(LC_TIME, 'es_ES.utf8', 'spanish');

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

// Ruta del logo
$ruta_logo = '../../images/logotn.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// ==================== ESTADÍSTICAS DE TRABAJADORES ====================
$total_activos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())")->fetchColumn();
$total_inactivos = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 0 OR (fecha_baja IS NOT NULL AND fecha_baja <= CURDATE())")->fetchColumn();
$total_general = $total_activos;

// NUEVO: Calcular las bajas producidas en el año fiscal actual
$bajas_del_anio = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE (activo = 0 OR (fecha_baja IS NOT NULL AND fecha_baja <= CURDATE())) AND YEAR(fecha_baja) = YEAR(CURDATE())")->fetchColumn();

// Estadísticas por género
$trabajadores_data = $pdo->query("SELECT id, ci, activo, fecha_baja FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())")->fetchAll();
$hombres = 0;
$mujeres = 0;
foreach ($trabajadores_data as $t) {
    $ci = preg_replace('/\D/', '', $t['ci']);
    if (strlen($ci) >= 10) {
        $digito_gen = intval($ci[9]);
        if ($digito_gen % 2 == 0) $hombres++;
        else $mujeres++;
    }
}
$total_genero = $hombres + $mujeres; // <--- CORREGIDO: Calcular después de que el bucle termine

// Estadísticas por área
$areas_stats = $pdo->query("
    SELECT a.id, a.nombre_area, COUNT(t.id) as total
    FROM areas a
    LEFT JOIN trabajadores t ON t.area_id = a.id AND t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
    WHERE a.activo = 1
    GROUP BY a.id, a.nombre_area
    ORDER BY total DESC
")->fetchAll();

// Estadísticas por centro de costo
$centros_stats = $pdo->query("
    SELECT cc.id, cc.codigo, cc.nombre, COUNT(t.id) as total,
           SUM(e.salario_mensual) as masa_salarial
    FROM centros_costo cc
    LEFT JOIN trabajadores t ON t.centro_costo_id = cc.id AND t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
    LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    WHERE cc.activo = 1
    GROUP BY cc.id, cc.codigo, cc.nombre
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// Estadísticas por categoría ocupacional
$categorias_stats = $pdo->query("
    SELECT co.id, co.nombre, co.codigo, co.factor_incidencia, COUNT(t.id) as total,
           SUM(e.salario_mensual) as masa_salarial
    FROM categorias_ocupacionales co
    LEFT JOIN trabajadores t ON t.categoria_ocupacional_id = co.id AND t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
    LEFT JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    WHERE co.activo = 1
    GROUP BY co.id, co.nombre, co.codigo, co.factor_incidencia
    ORDER BY total DESC
")->fetchAll();

// Estadísticas por tipo de contrato
$contratos_stats = $pdo->query("
    SELECT tipo_contrato, COUNT(*) as total
    FROM trabajadores
    WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
    GROUP BY tipo_contrato
")->fetchAll();

// Rangos de edad
$rangos_edad = ['18-25' => 0, '26-35' => 0, '36-45' => 0, '46-55' => 0, '56-65' => 0, '65+' => 0];
foreach ($trabajadores_data as $t) {
    if ($t['activo'] == 1 && (empty($t['fecha_baja']) || $t['fecha_baja'] > date('Y-m-d'))) {
        $ci = preg_replace('/\D/', '', $t['ci']);
        if (strlen($ci) >= 6) {
            $anio = intval(substr($ci, 0, 2));
            $mes = intval(substr($ci, 2, 2));
            $dia = intval(substr($ci, 4, 2));
            $anio_completo = $anio < 30 ? 2000 + $anio : 1900 + $anio;
            $fecha_nac = mktime(0, 0, 0, $mes, $dia, $anio_completo);
            $edad = date('Y') - date('Y', $fecha_nac);
            if (date('md') < date('md', $fecha_nac)) $edad--;
            
            if ($edad >= 18 && $edad <= 25) $rangos_edad['18-25']++;
            elseif ($edad >= 26 && $edad <= 35) $rangos_edad['26-35']++;
            elseif ($edad >= 36 && $edad <= 45) $rangos_edad['36-45']++;
            elseif ($edad >= 46 && $edad <= 55) $rangos_edad['46-55']++;
            elseif ($edad >= 56 && $edad <= 65) $rangos_edad['56-65']++;
            elseif ($edad > 65) $rangos_edad['65+']++;
        }
    }
}

// Antigüedad promedio
$antiguedad_promedio = round($pdo->query("SELECT AVG(DATEDIFF(CURDATE(), fecha_alta) / 365.25) FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE())")->fetchColumn(), 1);

// Salario promedio
$salario_promedio = $pdo->query("SELECT AVG(e.salario_mensual) FROM trabajadores t JOIN escalas_salariales e ON t.escala_salarial_id = e.id WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())")->fetchColumn() ?: 0;

// Vacaciones excedidas
$vacaciones_excedidas = $pdo->query("SELECT COUNT(*) FROM trabajadores WHERE activo = 1 AND (fecha_baja IS NULL OR fecha_baja > CURDATE()) AND vacaciones_acumuladas > 20")->fetchColumn();

// ==================== ESTADÍSTICAS DE NÓMINAS ====================

// Totales generales de nóminas
$nominas_totales = $pdo->query("
    SELECT 
        COUNT(*) as total_nominas,
        SUM(total_salario_devengado) as total_devengado,
        SUM(COALESCE(descuentos, 0) + COALESCE(contribucion_especial, 0) + COALESCE(ingresos_personales, 0) + COALESCE(otras_deducciones, 0)) as total_deducciones,
        SUM(importe_neto) as total_neto,
        SUM(contribucion_especial) as total_contribucion,
        AVG(total_salario_devengado) as promedio_devengado
    FROM nominas
    WHERE estado IN ('procesado', 'pagado', 'cerrado', 'contabilizado')
")->fetch();

// Nóminas por mes (CORREGIDO - meses en español)
$nominas_por_mes_raw = $pdo->query("
    SELECT 
        DATE_FORMAT(periodo_desde, '%Y-%m') as mes,
        YEAR(periodo_desde) as anio,
        MONTH(periodo_desde) as num_mes,
        COUNT(*) as cantidad,
        SUM(total_salario_devengado) as total_devengado,
        SUM(importe_neto) as total_neto,
        SUM(contribucion_especial) as total_contribucion
    FROM nominas
    WHERE estado IN ('procesado', 'pagado', 'cerrado', 'contabilizado')
    GROUP BY DATE_FORMAT(periodo_desde, '%Y-%m'), YEAR(periodo_desde), MONTH(periodo_desde)
    ORDER BY mes DESC
    LIMIT 12
")->fetchAll();

// Procesar nombres de meses en español
$nominas_por_mes = [];
foreach ($nominas_por_mes_raw as $n) {
    $n['mes_nombre'] = nombreMesEspanol($n['num_mes']) . ' ' . $n['anio'];
    $nominas_por_mes[] = $n;
}

// Top 10 empleados con mayor salario
$top_salarios = $pdo->query("
    SELECT t.id, t.nombre_completo, t.codigo, a.nombre_area, e.salario_mensual,
           co.nombre as categoria_nombre
    FROM trabajadores t
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    LEFT JOIN areas a ON t.area_id = a.id
    LEFT JOIN categorias_ocupacionales co ON t.categoria_ocupacional_id = co.id
    WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
    ORDER BY e.salario_mensual DESC
    LIMIT 10
")->fetchAll();

// Distribución salarial por rangos
$rangos_salariales = $pdo->query("
    SELECT 
        CASE 
            WHEN e.salario_mensual < 3000 THEN 'Menos de 3,000'
            WHEN e.salario_mensual BETWEEN 3000 AND 5000 THEN '3,000 - 5,000'
            WHEN e.salario_mensual BETWEEN 5001 AND 7000 THEN '5,001 - 7,000'
            WHEN e.salario_mensual BETWEEN 7001 AND 9000 THEN '7,001 - 9,000'
            WHEN e.salario_mensual > 9000 THEN 'Más de 9,000'
        END as rango,
        COUNT(*) as cantidad,
        SUM(e.salario_mensual) as total_salarios
    FROM trabajadores t
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
    GROUP BY rango
    ORDER BY MIN(e.salario_mensual)
")->fetchAll();

// Contribución especial por mes (CORREGIDO - meses en español)
$contribucion_mensual_raw = $pdo->query("
    SELECT 
        DATE_FORMAT(periodo_desde, '%Y-%m') as mes,
        YEAR(periodo_desde) as anio,
        MONTH(periodo_desde) as num_mes,
        SUM(contribucion_especial) as total_contribucion,
        COUNT(*) as cantidad_nominas
    FROM nominas
    WHERE estado IN ('procesado', 'pagado', 'cerrado', 'contabilizado')
    GROUP BY DATE_FORMAT(periodo_desde, '%Y-%m'), YEAR(periodo_desde), MONTH(periodo_desde)
    ORDER BY mes DESC
    LIMIT 12
")->fetchAll();

// Procesar nombres de meses en español
$contribucion_mensual = [];
foreach ($contribucion_mensual_raw as $c) {
    $c['mes_nombre'] = nombreMesEspanol($c['num_mes']) . ' ' . $c['anio'];
    $contribucion_mensual[] = $c;
}

// Resumen de vacaciones pagadas
$vacaciones_pagadas = $pdo->query("
    SELECT 
        SUM(importe_vacaciones) as total_vacaciones_pagadas,
        COUNT(CASE WHEN importe_vacaciones > 0 THEN 1 END) as trabajadores_con_vacaciones,
        SUM(dias_vacaciones_tomados) as total_dias_vacaciones
    FROM nominas
    WHERE estado IN ('procesado', 'pagado', 'cerrado', 'contabilizado')
")->fetch();

// Historial de cierres de nómina
$cierres_nomina = $pdo->query("
    SELECT * FROM cierres_nomina 
    ORDER BY fecha_cierre DESC 
    LIMIT 10
")->fetchAll();

// Masa salarial total
$masa_salarial_total = $pdo->query("
    SELECT SUM(e.salario_mensual) as total
    FROM trabajadores t
    JOIN escalas_salariales e ON t.escala_salarial_id = e.id
    WHERE t.activo = 1 AND (t.fecha_baja IS NULL OR t.fecha_baja > CURDATE())
")->fetchColumn() ?: 0;

$ultimas_bajas = $pdo->query("
    SELECT t.id, t.nombre_completo, t.fecha_baja, t.foto_ruta, t.fecha_alta, t.ci, a.nombre_area
    FROM trabajadores t
    LEFT JOIN areas a ON t.area_id = a.id
    WHERE t.fecha_baja IS NOT NULL
    ORDER BY t.fecha_baja DESC
    LIMIT 10
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Reportes Estadísticos</title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">
    
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/datatables/1.13.6/jquery.dataTables.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <script src="../js/chart.js"></script>
    
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

        /* Stats Cards */
        .stat-card {
            background: linear-gradient(135deg, rgba(28, 28, 35, 0.7), rgba(20, 20, 28, 0.8));
            border-radius: 16px; padding: 1rem; border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); border-color: rgba(96, 165, 250, 0.3); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 700; line-height: 1.2; }
        .stat-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Progress Bar */
        .progress-custom { height: 8px; background: rgba(255, 255, 255, 0.1); border-radius: 4px; overflow: hidden; }
        .progress-custom-bar { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

        /* Main Container */
        .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
        .main-container.expanded { margin-left: 80px; }

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

        /* Top Bar Windows 11 */
        .win-topbar {
            background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
            padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100 !important; position: relative !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 4px 0 0; }

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
        small, .small { color: rgba(255, 255, 255, 0.7); }
        hr { border-color: rgba(255, 255, 255, 0.1) !important; }
        
        /* Botones Windows 11 */
        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: white; font-size: 0.85rem; transition: all 0.2s;
            cursor: pointer; padding: 8px 16px; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-1px); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-1px); }
        .btn-win-sm { padding: 4px 12px; font-size: 0.75rem; color: #ffffff !important; }
        .btn-win-sm:hover { color: #ffffff !important; }

        /* Formularios */
        .form-label { color: rgba(255, 255, 255, 0.85); font-size: 0.8rem; font-weight: 500; margin-bottom: 6px; }
        .form-select, .form-control {
            background: rgba(20, 20, 25, 0.8); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: #ffffff; padding: 8px 12px; font-size: 0.85rem;
        }
        .form-select:focus, .form-control:focus {
            background: rgba(20, 20, 25, 0.9); border-color: #60a5fa; outline: none;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
        }

        /* Tablas */
        .table-dark, .table-dark td, .table-dark th { color: #ffffff; }
        .table-dark td small, .table-dark th small { color: rgba(255, 255, 255, 0.7); }
        .table-responsive { overflow-x: auto; }

        /* Donut Chart */
        .donut-container { position: relative; width: 180px; margin: 0 auto; }
        .donut-center {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            text-align: center;
        }
        .donut-center .total { font-size: 2rem; font-weight: 700; color: #60a5fa; }
        .donut-center .label { font-size: 0.7rem; color: rgba(255, 255, 255, 0.6); }

        /* Date Badge */
        .date-badge { background: rgba(255, 255, 255, 0.08); padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; }
        #liveClock { display: inline-block; min-width: 85px; text-align: center; font-variant-numeric: tabular-nums; letter-spacing: 0.5px; }

        /* Charts */
        .chart-container { height: 300px; position: relative; }
        canvas { max-height: 250px; width: 100% !important; }

        /* Tabs */
        .nav-tabs-custom { border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .nav-tabs-custom .nav-link {
            color: rgba(255, 255, 255, 0.6); border: none; padding: 10px 20px;
            transition: all 0.2s; background: transparent;
        }
        .nav-tabs-custom .nav-link:hover { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
        .nav-tabs-custom .nav-link.active {
            color: #60a5fa; border-bottom: 2px solid #60a5fa; background: transparent;
        }

        /* Badges */
        .badge-custom { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }

        /* Select personalizado */
        .form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            padding-right: 2.5rem !important;
        }
/* Efecto hover para fotos de bajas */
.foto-baja {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}
.foto-baja:hover {
    transform: scale(3.5);
    box-shadow: 0 0 20px rgba(96, 165, 250, 0.5);
    z-index: 1000;
    position: relative;
}
/* Efecto hover para nombres de bajas */
.nombre-baja {
    color: #60a5fa;
    text-decoration: none;
    transition: color 0.2s, text-decoration 0.2s;
    cursor: pointer;
}
.nombre-baja:hover {
    color: #a78bfa;
    text-decoration: underline;
}
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<div class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-chart-pie me-2" style="color: #60a5fa;"></i>Reportes Estadísticos</h1>
                <p><i class="fas fa-chart-line me-1"></i> Análisis completo de RRHH y Finanzas</p>
            </div>
        </div>
            <!-- NUEVO: Botón de Impresión de Reporte Ejecutivo + Exportación -->
            <div class="btn-group ms-3" role="group" aria-label="Acciones de Reporte General">
                <button class="btn-win btn-win-primary" id="btnPrintReport">
                    <i class="fas fa-print me-2"></i>Imprimir Reporte General
                </button>
                <button type="button" class="btn-win btn-win-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar Reporte General">
                    <span class="visually-hidden">Exportar</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win" id="exportReportMenu">
                    <li><a class="dropdown-item" href="#" id="btnExportWord"><i class="fas fa-file-word me-2" style="color: #2b579a;"></i>Exportar a Word</a></li>
                    <li><a class="dropdown-item" href="#" id="btnExportExcel"><i class="fas fa-file-excel me-2" style="color: #21a366;"></i>Exportar a Excel</a></li>
                    <li><a class="dropdown-item" href="#" id="btnExportPdf"><i class="fas fa-file-pdf me-2" style="color: #f40f02;"></i>Exportar a PDF</a></li>
                </ul>
            </div>
   <?php include '../includes/user_menu.php'; ?>
	
	</div>

    <!-- TABS NAVEGACIÓN -->
    <ul class="nav nav-tabs-custom mb-4 fade-in-up" id="reporteTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                <i class="fas fa-users me-2"></i>Estadísticas de Personal
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="financiero-tab" data-bs-toggle="tab" data-bs-target="#financiero" type="button" role="tab">
                <i class="fas fa-chart-line me-2"></i>Estadísticas Financieras
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB 1: ESTADÍSTICAS DE PERSONAL -->
        <div class="tab-pane fade show active" id="personal" role="tabpanel">
<!-- Tarjetas Rápidas con la nueva métrica de Bajas del Año -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="stat-value text-info"><?php echo $total_activos; ?></div><div class="stat-label">Activos</div></div>
                            <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15);"><i class="fas fa-user-check" style="color: #60a5fa;"></i></div>
                        </div>
                        <div class="progress-custom mt-2"><div class="progress-custom-bar" style="width: 100%; background: #60a5fa;"></div></div>
                    </div>
                </div>
                <div class="col">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="stat-value text-warning"><?php echo $total_inactivos; ?></div><div class="stat-label">Inactivos (Histórico)</div></div>
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15);"><i class="fas fa-user-slash" style="color: #f59e0b;"></i></div>
                        </div>
                    </div>
                </div>
                <!-- NUEVO: Tarjeta de Bajas del Año -->
                <div class="col">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="stat-value text-danger"><?php echo $bajas_del_anio; ?></div><div class="stat-label">Bajas del Año</div></div>
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15);"><i class="fas fa-user-minus" style="color: #ef4444;"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="stat-value text-success"><?php echo number_format($salario_promedio, 0); ?></div><div class="stat-label">Salario Promedio</div></div>
                            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15);"><i class="fas fa-dollar-sign" style="color: #10b981;"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="stat-value text-danger"><?php echo $vacaciones_excedidas; ?>  Trab.</div><div class="stat-label">Vac. Excedidas</div></div>
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15);"><i class="fas fa-umbrella-beach" style="color: #ef4444;"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Género y Antigüedad -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-5">
                    <div class="glass-card p-4 h-100">
                        <h6 class="mb-3"><i class="fas fa-venus-mars me-2" style="color: #a78bfa;"></i> Distribución por Género</h6>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="donut-container">
                                    <canvas id="generoChart" width="180" height="180" style="width: 180px; height: 180px;"></canvas>
                                    <div class="donut-center">
                                        <div class="total"><?php echo $total_genero; ?></div>
                                        <div class="label">Total</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3"><div class="d-flex justify-content-between"><span><i class="fas fa-mars me-2" style="color:#3b82f6;"></i> Masculino</span><span class="fw-bold"><?php echo $hombres; ?> (<?php echo $total_genero > 0 ? round(($hombres / $total_genero) * 100, 1) : 0; ?>%)</span></div><div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_genero > 0 ? ($hombres / $total_genero) * 100 : 0; ?>%; background: #3b82f6;"></div></div></div>
                                <div><div class="d-flex justify-content-between"><span><i class="fas fa-venus me-2" style="color:#ec489a;"></i> Femenino</span><span class="fw-bold"><?php echo $mujeres; ?> (<?php echo $total_genero > 0 ? round(($mujeres / $total_genero) * 100, 1) : 0; ?>%)</span></div><div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_genero > 0 ? ($mujeres / $total_genero) * 100 : 0; ?>%; background: #ec489a;"></div></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-4 h-100 text-center">
                        <h6 class="mb-3"><i class="fas fa-clock me-2" style="color: #f59e0b;"></i> Antigüedad</h6>
                        <div class="stat-value text-warning" style="font-size: 2.5rem;"><?php echo $antiguedad_promedio; ?></div>
                        <div class="stat-label">años en la empresa</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100">
                        <h6 class="mb-3"><i class="fas fa-file-signature me-2" style="color: #10b981;"></i> Tipo de Contrato</h6>
                        <?php foreach ($contratos_stats as $c): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between"><span><?php echo $c['tipo_contrato'] == 'Indeterminado' ? '♾️' : ($c['tipo_contrato'] == 'Determinado' ? '📅' : '🔍'); ?> <?php echo $c['tipo_contrato']; ?></span><span class="fw-bold"><?php echo $c['total']; ?> (<?php echo $total_activos > 0 ? round(($c['total'] / $total_activos) * 100, 1) : 0; ?>%)</span></div>
                            <div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($c['total'] / $total_activos) * 100 : 0; ?>%; background: #10b981;"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Rangos de Edad y Áreas -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-chart-bar me-2" style="color: #60a5fa;"></i> Rangos de Edad</h6>
                        <div class="row g-3">
                            <?php foreach ($rangos_edad as $rango => $cantidad): ?>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between"><span class="small"><?php echo $rango; ?> años</span><span class="fw-bold"><?php echo $cantidad; ?></span></div>
                                <div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($cantidad / $total_activos) * 100 : 0; ?>%; background: linear-gradient(90deg, #60a5fa, #a78bfa);"></div></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-building me-2" style="color: #60a5fa;"></i> Distribución por Área</h6>
                        <div style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($areas_stats as $area): ?>
                            <div class="mb-2"><div class="d-flex justify-content-between"><span class="small"><?php echo htmlspecialchars($area['nombre_area']); ?></span><span class="fw-bold"><?php echo $area['total']; ?></span></div><div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($area['total'] / $total_activos) * 100 : 0; ?>%; background: #a78bfa;"></div></div></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

<div class="row g-3 mb-4 fade-in-up">
    <div class="col-12">
        <div class="glass-card p-4">
            <h6 class="mb-3"><i class="fas fa-user-slash me-2" style="color: #ef4444;"></i> Últimas 10 Bajas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-dark">
                    <thead>
                        <tr>
                            <th class="text-center">Foto</th>
                            <th>Nombre y Apellidos</th>
                            <th class="text-center">Sexo</th>
                            <th class="text-center">Edad</th>
                            <th>Área</th>
                            <th>Tiempo Trabajado</th>
                            <th class="text-center">Fecha de Alta</th>
                            <th class="text-center">Fecha de Baja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_bajas as $baja): 
                            // Calcular tiempo trabajado
                            $fecha_alta = new DateTime($baja['fecha_alta']);
                            $fecha_baja = new DateTime($baja['fecha_baja']);
                            $diferencia = $fecha_alta->diff($fecha_baja);
                            
                            $tiempo = '';
                            if ($diferencia->y > 0) {
                                $tiempo .= $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '') . ' ';
                            }
                            if ($diferencia->m > 0) {
                                $tiempo .= $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '') . ' ';
                            }
                            if ($diferencia->d > 0) {
                                $tiempo .= $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
                            }
                            if (empty($tiempo)) {
                                $tiempo = 'Menos de 1 día';
                            }
                            
                            // Determinar sexo basado en el CI
                            $sexo = '-';
                            $ci_limpio = preg_replace('/\D/', '', $baja['ci']);
                            if (strlen($ci_limpio) >= 10) {
                                $digito_gen = intval($ci_limpio[9]);
                                $sexo = ($digito_gen % 2 == 0) ? 'M' : 'F';
                            }
                            
                            // Calcular edad basada en el CI
                            $edad = '-';
                            if (strlen($ci_limpio) >= 6) {
                                $anio = intval(substr($ci_limpio, 0, 2));
                                $mes = intval(substr($ci_limpio, 2, 2));
                                $dia = intval(substr($ci_limpio, 4, 2));
                                $anio_completo = $anio < 30 ? 2000 + $anio : 1900 + $anio;
                                $fecha_nac = mktime(0, 0, 0, $mes, $dia, $anio_completo);
                                $edad = date('Y') - date('Y', $fecha_nac);
                                if (date('md') < date('md', $fecha_nac)) $edad--;
                            }
                            
                            // URL para abrir empleados.php con el ID
                            $url_empleado = 'empleados.php?editar=' . $baja['id'];
                        ?>
                        <tr>
                            <td class="text-center">
                                <a href="<?php echo $url_empleado; ?>" style="text-decoration: none;" title="Ver detalles del trabajador">
                                    <?php if (!empty($baja['foto_ruta']) && file_exists('../' . $baja['foto_ruta'])): ?>
                                        <img src="../<?php echo htmlspecialchars($baja['foto_ruta']); ?>" alt="Foto" class="foto-baja">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #333; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; transition: transform 0.3s ease; cursor: pointer; margin: 0 auto;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo $url_empleado; ?>" class="nombre-baja" title="Editar empleado">
                                    <?php echo htmlspecialchars($baja['nombre_completo']); ?>
                                </a>
                            </td>
                            <td class="text-center"><?php echo $sexo; ?></td>
                            <td class="text-center"><?php echo $edad; ?></td>
                            <td><?php echo htmlspecialchars($baja['nombre_area'] ?? 'Sin asignar'); ?></td>
                            <td><?php echo $tiempo; ?></td>
                            <td class="text-center"><?php echo date('d/m/Y', strtotime($baja['fecha_alta'])); ?></td>
                            <td class="text-center"><?php echo date('d/m/Y', strtotime($baja['fecha_baja'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($ultimas_bajas)): ?>
                        <tr><td colspan="8" class="text-center text-muted">No hay bajas registradas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
		  
		  
		  <!-- Categorías y Top Salarios -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-chart-pie me-2" style="color: #60a5fa;"></i> Categorías Ocupacionales</h6>
                        <?php foreach ($categorias_stats as $cat): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between"><div><span class="fw-bold"><?php echo htmlspecialchars($cat['nombre']); ?></span> <span class="text-muted small">(<?php echo $cat['codigo']; ?> - <?php echo ($cat['factor_incidencia'] * 100); ?>%)</span></div><span class="fw-bold"><?php echo $cat['total']; ?></span></div>
                            <div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($cat['total'] / $total_activos) * 100 : 0; ?>%; background: linear-gradient(90deg, #3b82f6, #8b5cf6);"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-chart-line me-2" style="color: #60a5fa;"></i> Top 10 Mejores Salarios</h6>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-dark table-borderless">
                                <thead><tr><th>Empleado</th><th>Área</th><th>Categoría</th><th class="text-end">Salario</th></tr></thead>
                                <tbody>
                                    <?php foreach ($top_salarios as $ts): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ts['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($ts['nombre_area'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($ts['categoria_nombre'] ?? '-'); ?></td>
                                        <td class="text-end text-success">$<?php echo number_format($ts['salario_mensual'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ESTADÍSTICAS FINANCIERAS -->
        <div class="tab-pane fade" id="financiero" role="tabpanel">
            <!-- Tarjetas Financieras -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value text-info">$<?php echo number_format($nominas_totales['total_devengado'] ?? 0, 0); ?></div><div class="stat-label">Total Devengado</div></div>
                            <div class="stat-icon" style="background: rgba(96, 165, 250, 0.15);"><i class="fas fa-chart-line" style="color: #60a5fa;"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value text-danger">$<?php echo number_format($nominas_totales['total_deducciones'] ?? 0, 0); ?></div><div class="stat-label">Total Deducciones</div></div>
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15);"><i class="fas fa-arrow-down" style="color: #ef4444;"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value text-success">$<?php echo number_format($nominas_totales['total_neto'] ?? 0, 0); ?></div><div class="stat-label">Total Neto Pagado</div></div>
                            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15);"><i class="fas fa-money-bill-wave" style="color: #10b981;"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value text-warning">$<?php echo number_format($nominas_totales['total_contribucion'] ?? 0, 0); ?></div><div class="stat-label">Contribución Especial</div></div>
                            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15);"><i class="fas fa-percent" style="color: #f59e0b;"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Masa Salarial y Vacaciones -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100">
                        <h6 class="mb-3"><i class="fas fa-dollar-sign me-2" style="color: #10b981;"></i> Masa Salarial Mensual</h6>
                        <div class="text-center">
                            <div class="stat-value text-success" style="font-size: 2rem;">$<?php echo number_format($masa_salarial_total, 0); ?></div>
                            <div class="stat-label">Masa Salarial Total Mensual</div>
                            <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">
                            <div class="row">
                                <div class="col-6"><small class="text-muted">Promedio por empleado:</small><br><strong>$<?php echo number_format($salario_promedio, 0); ?></strong></div>
                                <div class="col-6"><small class="text-muted">Total nóminas:</small><br><strong><?php echo number_format($nominas_totales['total_nominas'] ?? 0); ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100">
                        <h6 class="mb-3"><i class="fas fa-umbrella-beach me-2" style="color: #f59e0b;"></i> Vacaciones Pagadas</h6>
                        <div class="text-center">
                            <div class="stat-value text-warning" style="font-size: 2rem;">$<?php echo number_format($vacaciones_pagadas['total_vacaciones_pagadas'] ?? 0, 0); ?></div>
                            <div class="stat-label">Total Pagado en Vacaciones</div>
                            <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">
                            <div class="row">
                                <div class="col-6"><small class="text-muted">Días disfrutados:</small><br><strong><?php echo number_format($vacaciones_pagadas['total_dias_vacaciones'] ?? 0, 1); ?></strong></div>
                                <div class="col-6"><small class="text-muted">Trabajadores:</small><br><strong><?php echo $vacaciones_pagadas['trabajadores_con_vacaciones'] ?? 0; ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100">
                        <h6 class="mb-3"><i class="fas fa-chart-pie me-2" style="color: #60a5fa;"></i> Rangos Salariales</h6>
                        <?php foreach ($rangos_salariales as $rs): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between"><span class="small"><?php echo $rs['rango']; ?></span><span class="fw-bold"><?php echo $rs['cantidad']; ?> emp.</span></div>
                            <div class="progress-custom mt-1"><div class="progress-custom-bar" style="width: <?php echo $total_activos > 0 ? ($rs['cantidad'] / $total_activos) * 100 : 0; ?>%; background: linear-gradient(90deg, #3b82f6, #a78bfa);"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Gráficos Mensuales (con meses en español) -->
            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-12">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-chart-line me-2" style="color: #60a5fa;"></i> Evolución Mensual de Nóminas</h6>
                        <div class="chart-container">
                            <canvas id="evolucionNominasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4 fade-in-up">
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-percent me-2" style="color: #f59e0b;"></i> Contribución Especial por Mes</h6>
                        <div class="chart-container">
                            <canvas id="contribucionChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-chart-bar me-2" style="color: #10b981;"></i> Masa Salarial por Centro de Costo</h6>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-dark">
                                <thead>
                                    <tr><th>Centro de Costo</th><th>Cantidad</th><th class="text-end">Masa Salarial</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($centros_stats as $cs): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cs['nombre']); ?></td>
                                        <td><?php echo $cs['total']; ?></td>
                                        <td class="text-end text-success">$<?php echo number_format($cs['masa_salarial'] ?? 0, 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                             </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimos Cierres de Nómina -->
            <div class="row g-3 fade-in-up">
                <div class="col-md-12">
                    <div class="glass-card p-4">
                        <h6 class="mb-3"><i class="fas fa-history me-2" style="color: #a78bfa;"></i> Últimos 10 Cierres de Nómina</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-dark">
                                <thead>
                                    <tr><th>Fecha Cierre</th><th>Número Nómina</th><th>Período</th><th>Trabajadores</th><th class="text-end">Total Devengado</th><th class="text-end">Total Neto</th><th>Estado</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cierres_nomina as $cn): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($cn['fecha_cierre'])); ?></td>
                                        <td><?php echo htmlspecialchars($cn['numero_nomina'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cn['periodo_desde'])); ?> - <?php echo date('d/m/Y', strtotime($cn['periodo_hasta'])); ?></td>
                                        <td><?php echo $cn['total_trabajadores']; ?></td>
                                        <td class="text-end">$<?php echo number_format($cn['total_devengado'] ?? 0, 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($cn['total_neto'] ?? 0, 2); ?></td>
                                        <td><span class="badge-custom badge-success"><?php echo $cn['estado']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($cierres_nomina)): ?>
                                    <td><td colspan="7" class="text-center text-muted">No hay cierres de nómina registrados</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/html2canvas.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>

<script>
// Clock
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockSpan = document.getElementById('liveClock');
    if (clockSpan) clockSpan.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar
const sidebar = document.getElementById('winSidebar');
const mainContainer = document.getElementById('mainContainer');
if (sidebar && mainContainer) {
    if (localStorage.getItem('winSidebarCollapsed') === 'true') { 
        sidebar.classList.add('collapsed'); 
        mainContainer.classList.add('expanded'); 
    }
    document.getElementById('sidebarToggleBtn')?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed'); 
        mainContainer.classList.toggle('expanded');
        localStorage.setItem('winSidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
}

// Logout
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

// Gráfico de Género (Donut con Chart.js)
const generoCanvas = document.getElementById('generoChart');
if (generoCanvas) {
    new Chart(generoCanvas, {
        type: 'doughnut',
        data: {
            labels: ['Masculino', 'Femenino'],
            datasets: [{
                data: [<?php echo $hombres; ?>, <?php echo $mujeres; ?>],
                backgroundColor: ['#3b82f6', '#ec489a'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '55%',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: { 
                    callbacks: { 
                        label: function(ctx) { 
                            const total = <?php echo $hombres + $mujeres; ?>;  // <--- CORREGIDO
                            const porcentaje = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                            return ctx.label + ': ' + ctx.raw + ' (' + porcentaje + '%)'; 
                        } 
                    } 
                }
            }
        }
    });
}

// Gráfico de Evolución de Nóminas (con meses en español)
<?php if (!empty($nominas_por_mes)): ?>
const meses = <?php echo json_encode(array_reverse(array_column($nominas_por_mes, 'mes_nombre'))); ?>;
const devengado = <?php echo json_encode(array_reverse(array_column($nominas_por_mes, 'total_devengado'))); ?>;
const neto = <?php echo json_encode(array_reverse(array_column($nominas_por_mes, 'total_neto'))); ?>;

new Chart(document.getElementById('evolucionNominasChart'), {
    type: 'line',
    data: { 
        labels: meses, 
        datasets: [
            { 
                label: 'Total Devengado', 
                data: devengado, 
                borderColor: '#60a5fa', 
                backgroundColor: 'rgba(96, 165, 250, 0.1)', 
                fill: true, 
                tension: 0.4 
            }, 
            { 
                label: 'Total Neto', 
                data: neto, 
                borderColor: '#10b981', 
                backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                fill: true, 
                tension: 0.4 
            }
        ] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true, 
        plugins: { 
            legend: { labels: { color: '#fff' } }, 
            tooltip: { 
                callbacks: { 
                    label: function(ctx) { 
                        return ctx.dataset.label + ': $' + ctx.raw.toLocaleString(); 
                    } 
                } 
            } 
        }, 
        scales: { 
            y: { 
                ticks: { color: '#fff', callback: function(v) { return '$' + v.toLocaleString(); } }, 
                grid: { color: 'rgba(255,255,255,0.1)' } 
            }, 
            x: { 
                ticks: { color: '#fff' }, 
                grid: { color: 'rgba(255,255,255,0.1)' } 
            } 
        } 
    }
});
<?php endif; ?>

// Gráfico de Contribución Especial por Mes (con meses en español)
<?php if (!empty($contribucion_mensual)): ?>
const mesesContrib = <?php echo json_encode(array_reverse(array_column($contribucion_mensual, 'mes_nombre'))); ?>;
const contribucion = <?php echo json_encode(array_reverse(array_column($contribucion_mensual, 'total_contribucion'))); ?>;

new Chart(document.getElementById('contribucionChart'), {
    type: 'bar',
    data: { 
        labels: mesesContrib, 
        datasets: [{ 
            label: 'Contribución Especial', 
            data: contribucion, 
            backgroundColor: 'rgba(245, 158, 11, 0.7)', 
            borderColor: '#f59e0b', 
            borderWidth: 1 
        }] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true, 
        plugins: { 
            legend: { labels: { color: '#fff' } }, 
            tooltip: { 
                callbacks: { 
                    label: function(ctx) { 
                        return 'Contribución: $' + ctx.raw.toLocaleString(); 
                    } 
                } 
            } 
        }, 
        scales: { 
            y: { 
                ticks: { color: '#fff', callback: function(v) { return '$' + v.toLocaleString(); } }, 
                grid: { color: 'rgba(255,255,255,0.1)' } 
            }, 
            x: { 
                ticks: { color: '#fff' }, 
                grid: { color: 'rgba(255,255,255,0.1)' } 
            } 
        } 
    }
});
<?php endif; ?>

window.printData = {
    empresa: <?php echo json_encode($config_empresa['nombre_empresa']); ?>,
    jefe: <?php echo json_encode($config_empresa['jefe_proyecto']); ?>,
    especialista: <?php echo json_encode($config_empresa['especialista_gestion']); ?>,
    logo: <?php echo json_encode($logo_base64); ?>,
    personal: {
        total_activos: <?php echo (int)$total_activos; ?>,
        total_inactivos: <?php echo (int)$total_inactivos; ?>,
        total_general: <?php echo (int)$total_general; ?>,
		bajas_anio: <?php echo (int)$bajas_del_anio; ?>,
        hombres: <?php echo (int)$hombres; ?>,
        mujeres: <?php echo (int)$mujeres; ?>,
        antiguedad: <?php echo (float)$antiguedad_promedio; ?>,
        salario_promedio: <?php echo (float)$salario_promedio; ?>,
        vacaciones_excedidas: <?php echo (int)$vacaciones_excedidas; ?>,
        contratos: <?php echo json_encode($contratos_stats); ?>,
        edades: <?php echo json_encode($rangos_edad); ?>,
        areas: <?php echo json_encode($areas_stats); ?>,
        categorias: <?php echo json_encode($categorias_stats); ?>,
        top_salarios: <?php echo json_encode($top_salarios); ?>,
		ultimas_bajas: <?php echo json_encode($ultimas_bajas); ?>
    },
    financiero: {
        devengado: <?php echo (float)($nominas_totales['total_devengado'] ?? 0); ?>,
        deducciones: <?php echo (float)($nominas_totales['total_deducciones'] ?? 0); ?>,
        neto: <?php echo (float)($nominas_totales['total_neto'] ?? 0); ?>,
        contribucion: <?php echo (float)($nominas_totales['total_contribucion'] ?? 0); ?>,
        masa_salarial: <?php echo (float)$masa_salarial_total; ?>,
        vacaciones_pagadas: <?php echo (float)($vacaciones_pagadas['total_vacaciones_pagadas'] ?? 0); ?>,
        vacaciones_dias: <?php echo (float)($vacaciones_pagadas['total_dias_vacaciones'] ?? 0); ?>,
        vacaciones_trabajadores: <?php echo (int)($vacaciones_pagadas['trabajadores_con_vacaciones'] ?? 0); ?>,
        rangos_salariales: <?php echo json_encode($rangos_salariales); ?>,
        centros: <?php echo json_encode($centros_stats); ?>,
        cierres: <?php echo json_encode($cierres_nomina); ?>
    }
};
// ==========================================
// CONSTRUIR EL HTML DEL INFORME CONSOLIDADO GENERAL
// (modos: 'print' | 'word' | 'pdf')
// ==========================================
function construirHtmlInformeGeneral(opts) {
    opts = opts || {};
    if (!window.printData) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Los datos del reporte no están disponibles.' });
        return '';
    }

    const data = window.printData;
    const fechaHora = obtenerFechaHora12H();
    
    // Función de formateo interno de moneda
    const fmt = (val) => new Intl.NumberFormat('es-CU', { style: 'currency', currency: 'CUP' }).format(val);

    // Renderizar filas de Áreas
    let areasHtml = '';
    data.personal.areas.forEach(a => {
        areasHtml += `<tr><td>${escapeHtml(a.nombre_area)}</td><td style="text-align: center;">${a.total}</td></tr>`;
    });

    // Renderizar filas de Categorías
    let categoriasHtml = '';
    data.personal.categorias.forEach(c => {
        categoriasHtml += `<tr><td>${escapeHtml(c.nombre)} (${c.codigo})</td><td style="text-align: center;">${c.total}</td><td style="text-align: right;">${fmt(c.masa_salarial || 0)}</td></tr>`;
    });

    // Renderizar tipos de contrato
    let contratosHtml = '';
    data.personal.contratos.forEach(c => {
        contratosHtml += `<tr><td>${escapeHtml(c.tipo_contrato)}</td><td style="text-align: center;">${c.total}</td></tr>`;
    });

    // Renderizar rangos de edad
    let edadesHtml = '';
    Object.entries(data.personal.edades).forEach(([rango, cant]) => {
        edadesHtml += `<tr><td>${rango} años</td><td style="text-align: center;">${cant}</td></tr>`;
    });

    // Renderizar top salarios
    let topSalariosHtml = '';
    data.personal.top_salarios.forEach(ts => {
        topSalariosHtml += `<tr><td>${escapeHtml(ts.nombre_completo)}</td><td>${escapeHtml(ts.nombre_area || '-')}</td><td>${escapeHtml(ts.categoria_nombre || '-')}</td><td style="text-align: right; font-weight: bold;">${fmt(ts.salario_mensual)}</td></tr>`;
    });

    // Renderizar centros de costo
    let centrosHtml = '';
    data.financiero.centros.forEach(cs => {
        centrosHtml += `<tr><td>${escapeHtml(cs.nombre)}</td><td style="text-align: center;">${cs.total}</td><td style="text-align: right;">${fmt(cs.masa_salarial || 0)}</td></tr>`;
    });

    // Renderizar cierres de nómina
    let cierresHtml = '';
    data.financiero.cierres.forEach(cn => {
        const fecha = new Date(cn.fecha_cierre).toLocaleDateString('es-ES');
        cierresHtml += `<tr><td>${fecha}</td><td>${escapeHtml(cn.numero_nomina || '-')}</td><td style="text-align: center;">${cn.total_trabajadores}</td><td style="text-align: right;">${fmt(cn.total_devengado)}</td><td style="text-align: right;">${fmt(cn.total_neto)}</td></tr>`;
    });

	// Generar filas de últimas bajas
	const ultimasBajasHtml = (data.personal.ultimas_bajas || []).map(b => {
		const foto = b.foto_ruta ? `<img src="../${b.foto_ruta}" style="width:30px;height:30px;object-fit:cover;border-radius:50%;">` : '-';
		
		// Determinar sexo basado en el CI
		let sexo = '-';
		let edad = '-';
		if (b.ci) {
			const ciLimpio = b.ci.replace(/\D/g, '');
			if (ciLimpio.length >= 10) {
				const digitoGen = parseInt(ciLimpio.charAt(9));
				sexo = (digitoGen % 2 === 0) ? 'M' : 'F';
			}
			// Calcular edad
			if (ciLimpio.length >= 6) {
				const anio = parseInt(ciLimpio.substring(0, 2));
				const mes = parseInt(ciLimpio.substring(2, 4));
				const dia = parseInt(ciLimpio.substring(4, 6));
				const anioCompleto = anio < 30 ? 2000 + anio : 1900 + anio;
				const fechaNac = new Date(anioCompleto, mes - 1, dia);
				const hoy = new Date();
				let edadCalculada = hoy.getFullYear() - fechaNac.getFullYear();
				const m = hoy.getMonth() - fechaNac.getMonth();
				if (m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())) {
					edadCalculada--;
				}
				edad = edadCalculada;
			}
		}
		
		// Calcular tiempo trabajado
		let tiempo = '-';
		if (b.fecha_alta && b.fecha_baja) {
			const fechaAlta = new Date(b.fecha_alta);
			const fechaBaja = new Date(b.fecha_baja);
			const diffTime = Math.abs(fechaBaja - fechaAlta);
			const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
			
			if (diffDays < 1) {
				tiempo = 'Menos de 1 día';
			} else if (diffDays < 30) {
				tiempo = diffDays + ' día' + (diffDays > 1 ? 's' : '');
			} else if (diffDays < 365) {
				const meses = Math.floor(diffDays / 30);
				const dias = diffDays % 30;
				tiempo = meses + ' mes' + (meses > 1 ? 'es' : '');
				if (dias > 0) tiempo += ' ' + dias + ' día' + (dias > 1 ? 's' : '');
			} else {
				const años = Math.floor(diffDays / 365);
				const meses = Math.floor((diffDays % 365) / 30);
				const dias = Math.floor((diffDays % 365) % 30);
				tiempo = años + ' año' + (años > 1 ? 's' : '');
				if (meses > 0) tiempo += ' ' + meses + ' mes' + (meses > 1 ? 'es' : '');
				if (dias > 0) tiempo += ' ' + dias + ' día' + (dias > 1 ? 's' : '');
			}
		}
		
		const area = b.nombre_area || 'Sin asignar';
		
		return `<tr>
			<td style="text-align:center;">${foto}</td>
			<td style="text-align:left;">${escapeHtml(b.nombre_completo)}</td>
			<td style="text-align:center;">${sexo}</td>
			<td style="text-align:center;">${edad}</td>
			<td style="text-align:left;">${escapeHtml(area)}</td>
			<td style="text-align:left;">${tiempo}</td>
			<td style="text-align:center;">${new Date(b.fecha_alta).toLocaleDateString('es-ES')}</td>
			<td style="text-align:center;">${new Date(b.fecha_baja).toLocaleDateString('es-ES')}</td>
		</tr>`;
	}).join('');

    const paraWord = opts.modo === 'word';

    return `
        <!DOCTYPE html>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <title>Informe Consolidado - ${escapeHtml(data.empresa)}</title>
            <meta charset="UTF-8">
            <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 8.5pt; color: #000; background: white; }
                ${paraWord
                    ? `@page WordSection1 { size: 8.5in 11in portrait; mso-page-orientation: portrait; margin: 0.5in; }
                       div.WordSection1 { page: WordSection1; }
                       .page-sheet { display: block !important; height: auto !important; page-break-after: always; }
                       .two-columns { display: block !important; }
                       .col-half { width: 100% !important; }
                       .print-footer { position: static !important; margin-top: 30px; }`
                    : `@page { size: letter portrait; margin: 0; }`}
                
                .page-sheet {
                    width: 215.9mm;
                    height: 279.4mm;
                    padding: 15mm 15mm 20mm 15mm;
                    position: relative;
                    background: white;
                    page-break-after: always;
                    display: flex;
                    flex-direction: column;
                }
                
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 2px solid #004B87;
                    padding-bottom: 8px;
                    margin-bottom: 15px;
                }
                .logo-area { width: 15%; }
                .logo-area img { max-height: 50px; max-width: 100%; }
                .header-text { text-align: center; width: 60%; }
                .header-text h1 { font-size: 11pt; color: #004B87; text-transform: uppercase; margin: 0; }
                .header-text h2 { font-size: 9pt; color: #333; margin-top: 2px; }
                .header-right { width: 25%; text-align: right; font-size: 7.5pt; color: #555; line-height: 1.3; }

                .section-title {
                    font-size: 9.5pt;
                    font-weight: bold;
                    color: #004B87;
                    border-bottom: 1.5px solid #004B87;
                    padding-bottom: 3px;
                    margin: 12px 0 8px 0;
                    text-transform: uppercase;
                }

				.grid-stats {
                    display: grid;
                    grid-template-columns: repeat(5, 1fr); /* Ajustado a 5 columnas */
                    gap: 10px;
                    margin-bottom: 12px;
                }
                .grid-item {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    padding: 8px;
                    border-radius: 6px;
                    text-align: center;
                }
                .grid-val { font-size: 12pt; font-weight: bold; color: #004B87; }
                .grid-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; margin-top: 2px; }

                table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 7.5pt; }
                th, td { border: 1px solid #cbd5e1; padding: 4px 6px; line-height: 1.2; }
                th { background-color: #004B87; color: white; font-weight: bold; text-transform: uppercase; text-align: left; }
                
                .two-columns { display: flex; gap: 15px; }
                .col-half { width: 50%; }

                .print-footer {
                    position: absolute;
                    bottom: 10mm;
                    left: 15mm;
                    right: 15mm;
                    border-top: 1px solid #bbb;
                    padding-top: 5px;
                    font-size: 7.5pt;
                    color: #555;
                    display: flex;
                    justify-content: space-between;
                }
                
                @media print {
                    .page-sheet { height: 100vh; }
                }
            </style>
        </head>
        <body>
            ${paraWord ? '<div class="WordSection1">' : ''}
            <!-- PÁGINA 1: INFORME GENERAL DE PERSONAL -->
            <div class="page-sheet">
                <div class="print-header">
                    <div class="logo-area">${data.logo ? `<img src="${data.logo}">` : ''}</div>
                    <div class="header-text">
                        <h1>${escapeHtml(data.empresa)}</h1>
                        <h2>Informe Estadístico y Demográfico de Personal</h2>
                    </div>
                    <div class="header-right">Generado: ${fechaHora}</div>
                </div>

				<div class="grid-stats">
                    <div class="grid-item"><div class="grid-val">${data.personal.total_general}</div><div class="grid-lbl">Masa de Personal</div></div>
                    <div class="grid-item"><div class="grid-val" style="color: #15803d;">${data.personal.total_activos}</div><div class="grid-lbl">Trabajadores Activos</div></div>
                    <div class="grid-item"><div class="grid-val" style="color: #b91c1c;">${data.personal.bajas_anio}</div><div class="grid-lbl">Bajas del Año</div></div>
                    <div class="grid-item"><div class="grid-val">${data.personal.hombres}</div><div class="grid-lbl">Hombres</div></div>
                    <div class="grid-item"><div class="grid-val">${data.personal.mujeres}</div><div class="grid-lbl">Mujeres</div></div>
                </div>

                <div class="two-columns" style="margin-top: 10px;">
                    <div class="col-half">
                        <div class="section-title">Distribución por Área de Trabajo</div>
                        <table>
                            <thead><tr><th>Área Organizativa</th><th style="width: 25%; text-align: center;">Trabajadores</th></tr></thead>
                            <tbody>${areasHtml}</tbody>
                        </table>
                    </div>
                    <div class="col-half">
                        <div class="section-title">Categorías Ocupacionales</div>
                        <table>
                            <thead><tr><th>Categoría</th><th style="width: 20%; text-align: center;">Cant.</th><th style="text-align: right;">Masa Salarial</th></tr></thead>
                            <tbody>${categoriasHtml}</tbody>
                        </table>
                    </div>
                </div>
				<!-- Tabla de últimas bajas -->
				<div class="section-title">Últimas 10 Bajas de Personal</div>
				<table>
					<thead>
						<tr>
							<th style="width:6%;">Foto</th>
							<th>Nombre y Apellidos</th>
							<th style="width:4%;">Sexo</th>
							<th style="width:4%;">Edad</th>
							<th style="width:15%;">Área</th>
							<th style="width:16%;">Tiempo Trabajado</th>
							<th style="width:13%;">Fecha de Alta</th>
							<th style="width:13%;">Fecha de Baja</th>
						</tr>
					</thead>
					<tbody>${ultimasBajasHtml || '<tr><td colspan="8" style="text-align:center;">No hay bajas registradas</td></tr>'}</tbody>
				</table>
                <div class="print-footer">
                    <div>Módulo de Reportes Consolidados de Personal</div>
                    <div>Página 1 de 3</div>
                </div>
            </div>

            <!-- PÁGINA 2: ESTRUCTURA DE CONTRATACIÓN Y SALARIOS -->
            <div class="page-sheet">
                <div class="print-header">
                    <div class="logo-area">${data.logo ? `<img src="${data.logo}">` : ''}</div>
                    <div class="header-text">
                        <h1>${escapeHtml(data.empresa)}</h1>
                        <h2>Estructura de Contratación y Escalas Salariales</h2>
                    </div>
                    <div class="header-right">Generado: ${fechaHora}</div>
                </div>

                <div class="two-columns">
                    <div class="col-half">
                        <div class="section-title">Tipos de Contrato</div>
                        <table>
                            <thead><tr><th>Contrato</th><th style="width: 30%; text-align: center;">Asignados</th></tr></thead>
                            <tbody>${contratosHtml}</tbody>
                        </table>
                    </div>
                    <div class="col-half">
                        <div class="section-title">Rangos Etarios del Colectivo</div>
                        <table>
                            <thead><tr><th>Rango de Edad</th><th style="width: 30%; text-align: center;">Trabajadores</th></tr></thead>
                            <tbody>${edadesHtml}</tbody>
                        </table>
                    </div>
                </div>

                <div class="section-title">Top 10 Salarios Más Altos de la Organización</div>
                <table>
                    <thead><tr><th>Nombre y Apellidos</th><th>Área</th><th>Categoría Ocupacional</th><th style="text-align: right; width: 20%;">Salario Mensual</th></tr></thead>
                    <tbody>${topSalariosHtml}</tbody>
                </table>

                <div class="print-footer">
                    <div>Estructura de Plantilla y Compensación</div>
                    <div>Página 2 de 3</div>
                </div>
            </div>

            <!-- PÁGINA 3: INFORME FINANCIERO Y FIRMAS -->
            <div class="page-sheet">
                <div class="print-header">
                    <div class="logo-area">${data.logo ? `<img src="${data.logo}">` : ''}</div>
                    <div class="header-text">
                        <h1>${escapeHtml(data.empresa)}</h1>
                        <h2>Resumen Financiero y Cierre de Ejercicio</h2>
                    </div>
                    <div class="header-right">Generado: ${fechaHora}</div>
                </div>

                <div class="grid-stats">
                    <div class="grid-item"><div class="grid-val">${fmt(data.financiero.devengado)}</div><div class="grid-lbl">Devengado Acumulado</div></div>
                    <div class="grid-item"><div class="grid-val" style="color: #b91c1c;">${fmt(data.financiero.deducciones)}</div><div class="grid-lbl">Deducciones Totales</div></div>
                    <div class="grid-item"><div class="grid-val" style="color: #15803d;">${fmt(data.financiero.neto)}</div><div class="grid-lbl">Líquido Pagado</div></div>
                    <div class="grid-item"><div class="grid-val">${fmt(data.financiero.masa_salarial)}</div><div class="grid-lbl">Masa Salarial Activa</div></div>
                </div>

                <div class="section-title">Distribución de Masa Salarial por Centro de Costo</div>
                <table>
                    <thead><tr><th>Centro de Costo (Área Productiva)</th><th style="text-align: center; width: 15%;">Trabajadores</th><th style="text-align: right; width: 25%;">Masa Salarial Mensual</th></tr></thead>
                    <tbody>${centrosHtml}</tbody>
                </table>

                <div class="section-title">Historial de Cierres de Nómina</div>
                <table>
                    <thead><tr><th>Fecha Cierre</th><th>Nómina</th><th style="text-align: center;">Trabajadores</th><th style="text-align: right;">Total Devengado</th><th style="text-align: right;">Total Neto</th></tr></thead>
                    <tbody>${cierresHtml}</tbody>
                </table>

                <!-- Firmas de Autorización -->
                <div class="signatures-area" style="display: flex; justify-content: space-between; margin-top: 40px; padding: 0 10px;">
                    <div style="width: 42%; text-align: center;">
                        <p style="font-size: 8pt; color: #555; margin-bottom: 45px;">Elaborado por:</p>
                        <div style="border-top: 1.5px solid #000; width: 90%; margin: 0 auto; padding-top: 5px;">
                            <strong style="font-size: 8.5pt;">${escapeHtml(data.especialista)}</strong><br>
                            <span style="font-size: 7.5pt; color: #666;">Especialista en Gestión Económica</span>
                        </div>
                    </div>
                    <div style="width: 42%; text-align: center;">
                        <p style="font-size: 8pt; color: #555; margin-bottom: 45px;">Aprobado por:</p>
                        <div style="border-top: 1.5px solid #000; width: 90%; margin: 0 auto; padding-top: 5px;">
                            <strong style="font-size: 8.5pt;">${escapeHtml(data.jefe)}</strong><br>
                            <span style="font-size: 7.5pt; color: #666;">Jefe de Proyecto</span>
                        </div>
                    </div>
                </div>

                <div class="print-footer">
                    <div>Informe de Contabilidad y Finanzas</div>
                    <div>Página 3 de 3</div>
                </div>
            </div>
            ${paraWord ? '</div>' : ''}
            ${opts.modo !== 'print' ? '' : `
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 300);
                };
            <\/script>
            `}
        </body>
        </html>
    `;
}

// ==========================================
// IMPRIMIR EL INFORME CONSOLIDADO GENERAL
// ==========================================
document.getElementById('btnPrintReport')?.addEventListener('click', function () {
    const html = construirHtmlInformeGeneral({ modo: 'print' });
    if (!html) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire({ icon: 'warning', title: 'Aviso', text: 'El navegador bloqueó la ventana de impresión. Permita las ventanas emergentes.' });
        return;
    }
    ventana.document.write(html);
    ventana.document.close();
});

// ==========================================
// EXPORTAR A WORD (mismo contenido que la impresión)
// ==========================================
document.getElementById('btnExportWord')?.addEventListener('click', function (e) {
    e.preventDefault();
    const html = construirHtmlInformeGeneral({ modo: 'word' });
    if (!html) return;
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const fecha = new Date();
    const fileName = 'Informe_Consolidado_' + fecha.getFullYear() + ('0' + (fecha.getMonth() + 1)).slice(-2) + ('0' + fecha.getDate()).slice(-2) + '.doc';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
});

// ==========================================
// EXPORTAR A EXCEL (hojas con los datos del informe)
// ==========================================
document.getElementById('btnExportExcel')?.addEventListener('click', function (e) {
    e.preventDefault();
    exportarInformeExcel();
});

function exportarInformeExcel() {
    if (!window.printData || typeof XLSX === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Los datos del reporte no están disponibles o la librería de Excel no cargó.' });
        return;
    }
    const data = window.printData;
    const wb = XLSX.utils.book_new();

    const wsResumen = XLSX.utils.aoa_to_sheet([
        ['INFORME CONSOLIDADO DE PERSONAL', data.empresa],
        ['Generado', obtenerFechaHora12H()],
        [],
        ['Masa de Personal', data.personal.total_general],
        ['Trabajadores Activos', data.personal.total_activos],
        ['Trabajadores Inactivos (Histórico)', data.personal.total_inactivos],
        ['Bajas del Año', data.personal.bajas_anio],
        ['Hombres', data.personal.hombres],
        ['Mujeres', data.personal.mujeres],
        ['Antigüedad Promedio (años)', data.personal.antiguedad],
        ['Salario Promedio', data.personal.salario_promedio],
        ['Vacaciones Excedidas', data.personal.vacaciones_excedidas]
    ]);
    wsResumen['!cols'] = [{ wch: 36 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, wsResumen, 'Resumen');

    const wsAreas = XLSX.utils.aoa_to_sheet([
        ['Área Organizativa', 'Trabajadores'],
        ...data.personal.areas.map(a => [a.nombre_area, a.total])
    ]);
    wsAreas['!cols'] = [{ wch: 44 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, wsAreas, 'Áreas');

    const wsCategorias = XLSX.utils.aoa_to_sheet([
        ['Categoría', 'Código', 'Cantidad', 'Masa Salarial'],
        ...data.personal.categorias.map(c => [c.nombre, c.codigo, c.total, c.masa_salarial || 0])
    ]);
    wsCategorias['!cols'] = [{ wch: 32 }, { wch: 12 }, { wch: 10 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, wsCategorias, 'Categorías');

    const wsContratos = XLSX.utils.aoa_to_sheet([
        ['Tipo de Contrato', 'Asignados'],
        ...data.personal.contratos.map(c => [c.tipo_contrato, c.total])
    ]);
    wsContratos['!cols'] = [{ wch: 26 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, wsContratos, 'Contratos');

    const wsEdades = XLSX.utils.aoa_to_sheet([
        ['Rango de Edad', 'Trabajadores'],
        ...Object.entries(data.personal.edades).map(([rango, cant]) => [rango + ' años', cant])
    ]);
    wsEdades['!cols'] = [{ wch: 18 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, wsEdades, 'Rangos de Edad');

    const wsTop = XLSX.utils.aoa_to_sheet([
        ['Nombre y Apellidos', 'Área', 'Categoría Ocupacional', 'Salario Mensual'],
        ...data.personal.top_salarios.map(ts => [ts.nombre_completo, ts.nombre_area || '-', ts.categoria_nombre || '-', ts.salario_mensual])
    ]);
    wsTop['!cols'] = [{ wch: 34 }, { wch: 30 }, { wch: 30 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, wsTop, 'Top Salarios');

    const wsBajas = XLSX.utils.aoa_to_sheet([
        ['Nombre y Apellidos', 'Sexo', 'Edad', 'Área', 'Fecha de Alta', 'Fecha de Baja'],
        ...(data.personal.ultimas_bajas || []).map(b => {
            let sexo = '-';
            let edad = '-';
            if (b.ci) {
                const ci = b.ci.replace(/\D/g, '');
                if (ci.length >= 10) sexo = (parseInt(ci.charAt(9)) % 2 === 0) ? 'M' : 'F';
                if (ci.length >= 6) {
                    const anio = parseInt(ci.substring(0, 2));
                    const anioCompleto = anio < 30 ? 2000 + anio : 1900 + anio;
                    const anioActual = new Date().getFullYear();
                    edad = anioActual - anioCompleto;
                }
            }
            return [b.nombre_completo, sexo, edad, b.nombre_area || 'Sin asignar', b.fecha_alta || '-', b.fecha_baja || '-'];
        })
    ]);
    wsBajas['!cols'] = [{ wch: 34 }, { wch: 8 }, { wch: 8 }, { wch: 30 }, { wch: 14 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, wsBajas, 'Últimas Bajas');

    const wsFinanciero = XLSX.utils.aoa_to_sheet([
        ['RESUMEN FINANCIERO'],
        ['Devengado Acumulado', data.financiero.devengado],
        ['Deducciones Totales', data.financiero.deducciones],
        ['Líquido Pagado', data.financiero.neto],
        ['Masa Salarial Activa', data.financiero.masa_salarial],
        ['Contribución', data.financiero.contribucion],
        ['Vacaciones Pagadas', data.financiero.vacaciones_pagadas],
        ['Vacaciones (días)', data.financiero.vacaciones_dias],
        ['Vacaciones (trabajadores)', data.financiero.vacaciones_trabajadores]
    ]);
    wsFinanciero['!cols'] = [{ wch: 36 }, { wch: 22 }];
    XLSX.utils.book_append_sheet(wb, wsFinanciero, 'Financiero');

    const wsCentros = XLSX.utils.aoa_to_sheet([
        ['Centro de Costo', 'Trabajadores', 'Masa Salarial Mensual'],
        ...data.financiero.centros.map(cs => [cs.nombre, cs.total, cs.masa_salarial || 0])
    ]);
    wsCentros['!cols'] = [{ wch: 40 }, { wch: 14 }, { wch: 24 }];
    XLSX.utils.book_append_sheet(wb, wsCentros, 'Centros de Costo');

    const wsCierres = XLSX.utils.aoa_to_sheet([
        ['Fecha Cierre', 'Nómina', 'Trabajadores', 'Total Devengado', 'Total Neto'],
        ...data.financiero.cierres.map(cn => [cn.fecha_cierre, cn.numero_nomina || '-', cn.total_trabajadores, cn.total_devengado, cn.total_neto])
    ]);
    wsCierres['!cols'] = [{ wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 20 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, wsCierres, 'Cierres de Nómina');

    const fecha = new Date();
    const fileName = 'Informe_Consolidado_' + fecha.getFullYear() + ('0' + (fecha.getMonth() + 1)).slice(-2) + ('0' + fecha.getDate()).slice(-2) + '.xlsx';
    XLSX.writeFile(wb, fileName);
}

// ==========================================
// EXPORTAR A PDF (idéntico a la vista de impresión)
// ==========================================
document.getElementById('btnExportPdf')?.addEventListener('click', async function (e) {
    e.preventDefault();
    await exportarInformePDF();
});

async function exportarInformePDF() {
    if (!window.printData || typeof window.jspdf === 'undefined' || typeof window.html2canvas === 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Las librerías de PDF no están disponibles.' });
        return;
    }
    Swal.fire({ icon: 'info', title: 'Generando PDF...', text: 'Se está generando el PDF, espere unos segundos.', showConfirmButton: false, allowOutsideClick: false });

    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position: fixed; left: -20000px; top: 0; width: 816px; height: 1056px; border: 0; background: #ffffff;';
    document.body.appendChild(iframe);

    try {
        const html = construirHtmlInformeGeneral({ modo: 'pdf' });
        if (!html) throw new Error('No se pudo construir el informe.');

        const baseTag = '<base href="' + location.href + '">';
        const doc = iframe.contentDocument;
        doc.open();
        doc.write(html.replace('<head>', '<head>' + baseTag));
        doc.close();

        await new Promise(resolve => setTimeout(resolve, 400));

        const paginas = doc.querySelectorAll('.page-sheet');
        if (paginas.length === 0) throw new Error('No se encontraron páginas para exportar.');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'px', format: [816, 1056], compress: true });

        for (let i = 0; i < paginas.length; i++) {
            const canvas = await window.html2canvas(paginas[i], {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                windowWidth: 816,
                windowHeight: 1056
            });
            const img = canvas.toDataURL('image/jpeg', 0.95);
            if (i > 0) pdf.addPage([816, 1056], 'portrait');
            pdf.addImage(img, 'JPEG', 0, 0, 816, 1056);
        }

        const fecha = new Date();
        const fileName = 'Informe_Consolidado_' + fecha.getFullYear() + ('0' + (fecha.getMonth() + 1)).slice(-2) + ('0' + fecha.getDate()).slice(-2) + '.pdf';
        pdf.save(fileName);
        Swal.close();
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + err.message });
    } finally {
        document.body.removeChild(iframe);
    }
}

function obtenerFechaHora12H() {
    const ahora = new Date();
    const opciones = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    return ahora.toLocaleString('es-ES', opciones);
}
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
</script>

<?php
if (!function_exists('numeroRomano')) {
    function numeroRomano($numero) {
        $romanos = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII',13=>'XIII',14=>'XIV',15=>'XV',16=>'XVI',17=>'XVII',18=>'XVIII',19=>'XIX',20=>'XX',21=>'XXI',22=>'XXII',23=>'XXIII',24=>'XXIV',25=>'XXV',26=>'XXVI',27=>'XXVII',28=>'XXVIII',29=>'XXIX',30=>'XXX',31=>'XXXI',32=>'XXXII'];
        return $romanos[$numero] ?? $numero;
    }
}
?>

</body>
</html>