<?php
// ver_todas_notificaciones.php
require_once 'config/init.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Título de la página
$page_title = 'Todas las Notificaciones';

// Obtener estado del sidebar de la sesión
$sidebar_colapsado = $_SESSION['sidebar_colapsado'] ?? false;

// Obtener datos del usuario actual
try {
    $db = Database::getConnection();
    
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usuario = [
        'nombre' => $_SESSION['usuario_nombre'] ?? 'Usuario',
        'rol_nombre' => 'Usuario'
    ];
}
    // Total global (no depende de fecha)
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->query($sql_facturas_total);
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $cant_Users = $db->query("SELECT COUNT(*) FROM clasif_usuarios")->fetchColumn();
    $clientes_todos = $db->query("SELECT COUNT(*) FROM clasif_clientes")->fetchColumn();
    $cant_categ = $db->query("SELECT COUNT(*) FROM clasif_cat_de_serv")->fetchColumn();
    $servicios_count = $db->query("SELECT COUNT(*) FROM clasif_serv WHERE activo = 1")->fetchColumn();
    $estadisticas['total'] = $db->query("SELECT COUNT(*) FROM historico_operaciones")->fetchColumn();


// Obtener período actual
$periodo = obtenerPeriodoOperaciones($db);

// ============================================
// 1. NOTIFICACIONES DE CIERRE (MEJORADO)
// ============================================
$notificaciones_cierre = [];

// Verificar cierre pendiente por fecha del sistema (NUEVO)
$cierre_pendiente_fecha = verificarCierrePendientePorFecha();
$hay_cierre_pendiente_fecha = $cierre_pendiente_fecha['hay_cierre_pendiente'];

if ($hay_cierre_pendiente_fecha) {
    $notificaciones_cierre[] = [
        'tipo' => 'cierre_pendiente_fecha',
        'titulo' => $cierre_pendiente_fecha['mensaje'],
        'periodo' => $cierre_pendiente_fecha['periodo'],
        'mensaje' => $cierre_pendiente_fecha['texto_completo'],
        'icono' => 'exclamation-triangle',
        'color' => 'danger',
        'link' => 'cierre_mes.php',
        'fecha' => date('Y-m-d H:i:s'),
        'ultimo_dia' => $cierre_pendiente_fecha['ultimo_dia'],
        'fecha_actual' => $cierre_pendiente_fecha['fecha_actual']
    ];
}

// Verificar última factura del mes
$ultimo_dia_info = verificarUltimoDiaFacturacionMes();

if ($ultimo_dia_info['es_ultimo_dia']) {
    $fecha_ultima = $ultimo_dia_info['fecha_ultima_factura'];
    $mes_ultima = date('n', strtotime($fecha_ultima));
    $anio_ultima = date('Y', strtotime($fecha_ultima));
    
    if ($mes_ultima == $periodo['mes'] && $anio_ultima == $periodo['anio']) {
        $notificaciones_cierre[] = [
            'tipo' => 'cierre_mes',
            'titulo' => 'Cierre Mensual Disponible',
            'mensaje' => $ultimo_dia_info['mensaje'],
            'icono' => 'calendar-check',
            'color' => 'success',
            'link' => 'cierre_mes.php',
            'fecha' => $fecha_ultima
        ];
    }
}

// Verificar cierre anual (solo en diciembre)
if ($periodo['mes'] == 12) {
    $verificacion_anio = verificarCierreAnio($periodo['anio'], $db);
    if ($verificacion_anio['puede_cerrar']) {
        $notificaciones_cierre[] = [
            'tipo' => 'cierre_anual',
            'titulo' => 'Cierre Anual Disponible',
            'mensaje' => 'PUEDE CERRAR EL AÑO ' . $periodo['anio'],
            'icono' => 'calendar-alt',
            'color' => 'success',
            'link' => 'cierre_anual.php',
            'fecha' => date('Y-m-d H:i:s')
        ];
    } elseif (isset($verificacion_anio['mensaje']) && $verificacion_anio['mensaje'] != 'Año listo para cierre') {
        $notificaciones_cierre[] = [
            'tipo' => 'cierre_anual_pendiente',
            'titulo' => 'Cierre Anual - Pendiente',
            'mensaje' => $verificacion_anio['mensaje'],
            'icono' => 'exclamation-triangle',
            'color' => 'warning',
            'link' => 'cierre_anual.php',
            'fecha' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================
// 2. FACTURAS PENDIENTES
// ============================================
$sql_facturas = "SELECT f.id, f.no_fact, f.fecha_emision, f.total_general, f.estado, f.cliente_id,
                        c.nombre as cliente_nombre,
                        DATEDIFF(CURDATE(), f.fecha_emision) as dias_antiguedad
                 FROM tbl_fact f
                 LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                 WHERE f.estado NOT IN ('PAGADA', 'ANULADA', 'CERRADA', 'CONTABILIZADA')
                 ORDER BY f.fecha_emision ASC";
$stmt_facturas = $db->prepare($sql_facturas);
$stmt_facturas->execute();
$facturas_pendientes = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 3. FACTURAS SIN PAGO (SIN REFERENCIA)
// ============================================
$sql_sin_pago = "SELECT f.id, f.no_fact, f.fecha_emision, f.total_general, f.estado, f.cliente_id,
                        c.nombre as cliente_nombre,
                        DATEDIFF(CURDATE(), f.fecha_emision) as dias_vencida
                 FROM tbl_fact f
                 LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                 WHERE (f.Ref_pago IS NULL OR f.Ref_pago = '') 
                AND estado NOT IN ('ANULADA', 'PENDIENTE')
				ORDER BY fecha_emision ASC";
$stmt_sin_pago = $db->prepare($sql_sin_pago);
$stmt_sin_pago->execute();
$facturas_sin_pago = $stmt_sin_pago->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 4. CONTRATOS VENCIDOS
// ============================================
$sql_contratos_vencidos = "SELECT id, codigo, nombre, fechafinalcontrato,
                                  DATEDIFF(CURDATE(), fechafinalcontrato) as dias_vencido
                           FROM clasif_clientes 
                           WHERE fechafinalcontrato < CURDATE() 
                           AND activo = 1
                           ORDER BY fechafinalcontrato ASC";
$stmt_contratos_vencidos = $db->prepare($sql_contratos_vencidos);
$stmt_contratos_vencidos->execute();
$contratos_vencidos = $stmt_contratos_vencidos->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 5. CONTRATOS PRÓXIMOS A VENCER
// ============================================
$sql_contratos_proximos = "SELECT id, codigo, nombre, fechafinalcontrato,
                                   DATEDIFF(fechafinalcontrato, CURDATE()) as dias_restantes
                            FROM clasif_clientes 
                            WHERE fechafinalcontrato BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            AND activo = 1
                            ORDER BY fechafinalcontrato ASC";
$stmt_contratos_proximos = $db->prepare($sql_contratos_proximos);
$stmt_contratos_proximos->execute();
$contratos_proximos = $stmt_contratos_proximos->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 6. ÚLTIMOS CIERRES REALIZADOS - MEJORADO
// ============================================
$sql_cierres = "SELECT hc.*, CONCAT(cu.nombre, ' ', cu.apellidos) as usuario_nombre
                FROM historico_cierres hc
                JOIN clasif_usuarios cu ON hc.usuario_id = cu.id
                ORDER BY hc.periodo_anio DESC, hc.periodo_mes DESC, hc.tipo DESC, hc.fecha_ejecucion DESC
                LIMIT 20";
$stmt_cierres = $db->prepare($sql_cierres);
$stmt_cierres->execute();
$cierres_recientes = $stmt_cierres->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_notificaciones = count($facturas_pendientes) + count($facturas_sin_pago) + count($contratos_vencidos) + count($contratos_proximos) + count($notificaciones_cierre);

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
    <title><?php echo $page_title;?> - SISFACT PDL VISIONES</title>
	<link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">

<!-- MDTimePicker para el reloj analógico -->
<link rel="stylesheet" href="css/mdtimepicker.css">
<script src="js/mdtimepicker.min.js"></script>

    <!-- Chart.js -->
    <script src="js/chart.umd.js"></script>

<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>

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

        /* Estilos para Accordion de Cierres */
        .accordion-item {
            background-color: transparent;
            border: none;
        }
        
        .accordion-button {
            background-color: var(--win-bg-tertiary);
            color: var(--win-text-primary);
            border: 1px solid var(--win-border-color);
            border-radius: 0 !important;
            padding: 1rem;
            font-weight: 500;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--win-accent-light);
            color: var(--win-accent);
        }
        
        .accordion-button::after {
            filter: invert(1);
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--win-accent);
        }
        
        .accordion-body {
            padding: 0;
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
				<a href="dashboard.php" class="win-nav-link">
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

    <!-- Contenido principal - HEADER FIJO -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <!-- Título y resumen - SIEMPRE VISIBLE -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-white border-0 shadow sticky-header">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="mb-0">
                                <i class="fas fa-bell text-primary me-2"></i>
                                Todas las Notificaciones
                            </h3>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-primary fs-6 p-2">
                                    Total: <span class="badge bg-danger text-light" style="font-size:14px;"><?= $total_notificaciones ?></span> notificaciones
                                </span>
                                <button class="btn btn-sm btn-outline-secondary" id="toggleAllCardsBtn">
                                    <i class="fas fa-expand-alt me-1"></i>Expandir todo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
			<div class="col-lg-6 mb-4">
            <!-- Columna izquierda - Notificaciones de Cierre (4 columnas) -->
					<div class="card bg-dark text-white border-info mb-3 shadow collapsible-card" id="card-periodo-actual">
						<div class="card-header bg-info bg-opacity-25 border-info">
							<h5 class="mb-0 card-title-hover">
								<i class="fas fa-calendar-alt me-2"></i>
								Período Actual de Operaciones
							</h5>
						</div>
						<div class="card-body">
							<div class="d-flex align-items-center">
								<div class="flex-shrink-0">
									<div class="bg-info bg-opacity-25 rounded-circle p-3">
										<i class="fas fa-calendar-day fa-2x text-info"></i>
									</div>
								</div>
								<div class="flex-grow-1 ms-3">
									<h4 class="mb-1">
										<?php 
										$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
												 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
										echo $meses[$periodo['mes']-1] . ' ' . $periodo['anio'];
										?>
									</h4>
									<p class="text-muted mb-0">Fecha inicio: <span class="fw-bold text-warning"><?= date('d/m/Y', strtotime($periodo['fecha_inicio'])) ?></span></p>
									<p class="text-muted mb-0">Fecha Fin: <span class="fw-bold text-warning"><?= ultimoDiaMesFechaInicio() ?></span></p>
								</div>
							</div>
						</div>
					</div>
			</div>
            <div class="col-lg-6 mb-4">
                <!-- Notificaciones de Cierre - Estilo Windows 11 -->
                <div class="card bg-dark text-white border-success mb-3 shadow win11-card collapsible-card" id="card-cierres-contables">
                    <div class="card-header bg-success bg-opacity-25 border-success win11-card-header">
                        <h5 class="mb-0 d-flex align-items-center card-title-hover">
                            <i class="fas fa-calendar-check me-2 win11-icon-pulse"></i>
                            Cierres Contables
                            <?php if (!empty($notificaciones_cierre)): ?>
                                <span class="badge bg-success ms-2 win11-badge-glow"><?= count($notificaciones_cierre) ?> nuevo<?= count($notificaciones_cierre) != 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush bg-transparent win11-list">
                            <?php if (empty($notificaciones_cierre)): ?>
                                <div class="list-group-item bg-transparent text-muted win11-empty-state">
                                    <i class="fas fa-check-circle me-2"></i>
                                    No hay notificaciones de cierre
                                </div>
                            <?php else: ?>
                                <?php foreach ($notificaciones_cierre as $index => $notif): ?>
                                    <a href="<?= $notif['link'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-secondary win11-notification-item" style="animation-delay: <?= $index * 0.1 ?>s;">
                                        <!-- Efecto mica al hover -->
                                        <div class="win11-mica-effect"></div>
                                        
                                        <div class="d-flex align-items-center position-relative" style="z-index: 2;">
                                            <!-- Icono con efecto reveal -->
                                            <div class="me-3 position-relative">
                                                <div class="win11-icon-glow" style="background: <?php 
                                                    switch($notif['color']) {
                                                        case 'success': echo 'rgba(40, 167, 69, 0.3)'; break;
                                                        case 'warning': echo 'rgba(255, 193, 7, 0.3)'; break;
                                                        case 'danger': echo 'rgba(220, 53, 69, 0.3)'; break;
                                                        default: echo 'rgba(13, 110, 253, 0.3)';
                                                    }
                                                ?>;"></div>
                                                <span class="badge bg-<?= $notif['color'] ?> p-2 win11-notification-icon">
                                                    <i class="fas fa-<?= $notif['icono'] ?>"></i>
                                                </span>
                                            </div>
                                            
                                            <!-- Contenido - Formato especial para cierre pendiente por fecha -->
                                            <div class="flex-grow-1">
                                                <?php if ($notif['tipo'] == 'cierre_pendiente_fecha'): ?>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h6 class="mb-0 text-<?= $notif['color'] ?> win11-notification-title"><?= $notif['titulo'] ?></h6>
                                                        <span class="win11-new-badge" style="opacity: 0; animation: fadeIn 0.5s ease forwards; animation-delay: 1s;">NUEVO</span>
                                                    </div>
                                                    <span class="text-warning fw-bold d-block" style="font-size: 1.1rem;"><?= htmlspecialchars($notif['periodo']) ?></span>
                                                    <span class="text-success fw-bold d-block">YA PUEDE CERRARSE</span>
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <small class="text-muted win11-notification-date">
                                                            <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?>
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            Último día: <?= $notif['ultimo_dia'] ?> | Fecha actual: <?= $notif['fecha_actual'] ?>
                                                        </small>
                                                    </div>
                                                <?php elseif ($notif['tipo'] == 'cierre_mes'): ?>
                                                    <h6 class="mb-0 text-<?= $notif['color'] ?> win11-notification-title"><?= $notif['titulo'] ?></h6>
                                                    <p class="mb-1 small win11-notification-message"><?= $notif['mensaje'] ?></p>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted win11-notification-date">
                                                            <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?>
                                                        </small>
                                                        <span class="win11-reading-time">• Haga clic para realizar cierre</span>
                                                    </div>
                                                <?php elseif ($notif['tipo'] == 'cierre_anual'): ?>
                                                    <h6 class="mb-0 text-<?= $notif['color'] ?> win11-notification-title"><?= $notif['titulo'] ?></h6>
                                                    <p class="mb-1 small win11-notification-message"><?= $notif['mensaje'] ?></p>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted win11-notification-date">
                                                            <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?>
                                                        </small>
                                                        <span class="win11-reading-time">• Haga clic para realizar cierre</span>
                                                    </div>
                                                <?php elseif ($notif['tipo'] == 'cierre_anual_pendiente'): ?>
                                                    <h6 class="mb-0 text-<?= $notif['color'] ?> win11-notification-title"><?= $notif['titulo'] ?></h6>
                                                    <p class="mb-1 small win11-notification-message"><?= $notif['mensaje'] ?></p>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted win11-notification-date">
                                                            <i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?>
                                                        </small>
                                                        <span class="win11-reading-time">• Ver requisitos</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Flecha con animación -->
                                            <div class="ms-2 win11-arrow-container">
                                                <i class="fas fa-chevron-right text-<?= $notif['color'] ?> win11-arrow"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Barra de progreso de lectura -->
                                        <div class="win11-read-progress"></div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
			</div>
				<div class="row">
				<div class="col-lg-12 mb-4">
                <!-- Últimos Cierres Realizados - ORDENADO POR AÑO, MES Y TIPO -->
                <div class="card bg-dark text-white border-secondary shadow collapsible-card">
                    <div class="card-header bg-secondary bg-opacity-25 border-secondary d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 card-title-hover">
                            <i class="fas fa-history me-2"></i>
                            Últimos Cierres Realizados
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info" data-bs-toggle="tooltip" title="Ordenado por año, mes y tipo">📊 Ordenado</span>
                            <?php if (!empty($cierres_recientes)): ?>
                                <span class="badge bg-secondary"><?= count($cierres_recientes) ?> registros</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($cierres_recientes)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <p class="mb-0">No hay cierres registrados</p>
                                <small>Los cierres aparecerán aquí cuando se realicen</small>
                            </div>
                        <?php else: 
                            // Agrupar cierres por año
                            $cierres_por_anio = [];
                            foreach ($cierres_recientes as $cierre) {
                                $anio = $cierre['periodo_anio'];
                                if (!isset($cierres_por_anio[$anio])) {
                                    $cierres_por_anio[$anio] = [];
                                }
                                $cierres_por_anio[$anio][] = $cierre;
                            }
                            
                            // Ordenar años descendente (más reciente primero)
                            krsort($cierres_por_anio);
                        ?>
                            <div class="accordion accordion-flush" id="accordionCierres">
                                <?php foreach ($cierres_por_anio as $anio => $cierres_del_anio): 
                                    // Separar cierres mensuales y anuales
                                    $cierres_mensuales = array_filter($cierres_del_anio, function($c) { return $c['tipo'] == 1; });
                                    $cierres_anuales = array_filter($cierres_del_anio, function($c) { return $c['tipo'] == 2; });
                                    
                                    // Ordenar mensuales por mes descendente
                                    usort($cierres_mensuales, function($a, $b) {
                                        return $b['periodo_mes'] - $a['periodo_mes'];
                                    });
                                    
                                    // Ordenar anuales (suele ser uno solo, pero por si acaso)
                                    usort($cierres_anuales, function($a, $b) {
                                        return strtotime($b['fecha_ejecucion']) - strtotime($a['fecha_ejecucion']);
                                    });
                                ?>
                                <div class="accordion-item bg-transparent">
                                    <h2 class="accordion-header" id="heading-<?= $anio ?>">
                                        <button class="accordion-button <?= $anio == date('Y') ? '' : 'collapsed' ?> bg-dark text-white border-secondary" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse-<?= $anio ?>" 
                                                aria-expanded="<?= $anio == date('Y') ? 'true' : 'false' ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                <span>
                                                    <i class="fas fa-calendar-alt me-2"></i>
                                                    <strong>Año <?= $anio ?></strong>
                                                </span>
                                                <span class="badge bg-info ms-2"><?= count($cierres_del_anio) ?> cierre(s)</span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse-<?= $anio ?>" 
                                         class="accordion-collapse collapse <?= $anio == date('Y') ? 'show' : '' ?>" 
                                         aria-labelledby="heading-<?= $anio ?>" 
                                         data-bs-parent="#accordionCierres">
                                        <div class="accordion-body p-0">
                                            <div class="list-group list-group-flush bg-transparent">
                                                
                                                <!-- Cierres Anuales (si existen) -->
                                                <?php if (!empty($cierres_anuales)): ?>
                                                    <?php foreach ($cierres_anuales as $cierre): ?>
                                                        <div class="list-group-item bg-transparent text-white border-secondary">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                                        <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Cierre Anual">
                                                                            <i class="fas fa-calendar-alt me-1"></i>Anual
                                                                        </span>
                                                                        <span class="badge bg-secondary">Año <?= $cierre['periodo_anio'] ?></span>
                                                                    </div>
                                                                    <p class="mb-1 small">
                                                                        <span class="text-info">Facturas: <?= $cierre['total_facturas'] ?></span> • 
                                                                        <span class="text-success">Pagadas: <?= $cierre['cant_pagadas'] ?></span> • 
                                                                        <span class="text-warning">Contab: <?= $cierre['cant_contabilizadas'] ?></span>
                                                                    </p>
                                                                    <small class="text-muted d-flex align-items-center">
                                                                        <i class="far fa-clock me-1"></i>
                                                                        <?= date('d/m/Y H:i', strtotime($cierre['fecha_ejecucion'])) ?> 
                                                                        <span class="mx-1">•</span> 
                                                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($cierre['usuario_nombre']) ?>
                                                                        <?php if (!empty($cierre['importe_total']) && $cierre['importe_total'] > 0): ?>
                                                                            <span class="ms-2 badge bg-success">$<?= number_format($cierre['importe_total'], 2) ?></span>
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>
                                                                <div class="ms-2">
                                                                    <span class="text-warning fw-bold"><?= $cierre['total_facturas'] ?></span>
                                                                    <small class="text-muted d-block">facturas</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                
                                                <!-- Cierres Mensuales (si existen) -->
                                                <?php if (!empty($cierres_mensuales)): ?>
                                                    <?php foreach ($cierres_mensuales as $cierre): 
                                                        $mes_nombre = $meses[$cierre['periodo_mes']-1] ?? 'Mes ' . $cierre['periodo_mes'];
                                                        $icono_mes = $cierre['periodo_mes'] == 12 ? 'fa-calendar-alt' : 'fa-calendar-day';
                                                    ?>
                                                        <div class="list-group-item bg-transparent text-white border-secondary">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                                        <span class="badge bg-primary" data-bs-toggle="tooltip" title="Cierre Mensual">
                                                                            <i class="<?= $icono_mes ?> me-1"></i>Mensual
                                                                        </span>
                                                                        <span class="badge bg-secondary">
                                                                            <?= $mes_nombre ?>
                                                                        </span>
                                                                        <?php if ($cierre['periodo_mes'] == 12): ?>
                                                                            <span class="badge bg-info">Fin de Año</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <p class="mb-1 small">
                                                                        <span class="text-info">Facturas: <?= $cierre['total_facturas'] ?></span> • 
                                                                        <span class="text-success">Pagadas: <?= $cierre['cant_pagadas'] ?></span> • 
                                                                        <span class="text-warning">Contab: <?= $cierre['cant_contabilizadas'] ?></span>
                                                                    </p>
                                                                    <small class="text-muted d-flex align-items-center">
                                                                        <i class="far fa-clock me-1"></i>
                                                                        <?= date('d/m/Y H:i', strtotime($cierre['fecha_ejecucion'])) ?> 
                                                                        <span class="mx-1">•</span> 
                                                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($cierre['usuario_nombre']) ?>
                                                                        <?php if (!empty($cierre['importe_total']) && $cierre['importe_total'] > 0): ?>
                                                                            <span class="ms-2 badge bg-success">$<?= number_format($cierre['importe_total'], 2) ?></span>
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>
                                                                <div class="ms-2">
                                                                    <span class="text-primary fw-bold"><?= $cierre['total_facturas'] ?></span>
                                                                    <small class="text-muted d-block">facturas</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Resumen de cierres -->
                            <div class="card-footer bg-transparent border-secondary p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block">Total de cierres:</small>
                                        <span class="h5 text-white mb-0"><?= count($cierres_recientes) ?></span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Último cierre:</small>
                                        <?php
                                        $ultimo_cierre = $cierres_recientes[0];
                                        $fecha_ultimo = date('d/m/Y', strtotime($ultimo_cierre['fecha_ejecucion']));
                                        $tipo_ultimo = $ultimo_cierre['tipo'] == 1 ? 'Mensual' : 'Anual';
                                        echo '<span class="badge bg-info">' . $tipo_ultimo . ' - ' . $fecha_ultimo . '</span>';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
				</div>
            </div>

<!-- PRIMERA FILA: Facturas Pendientes (col-12) -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card bg-dark text-white border-primary shadow collapsible-card h-100" id="card-facturas-pendientes">
            <div class="card-header bg-primary bg-opacity-25 border-primary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 card-title-hover">
                    <i class="fas fa-file-invoice me-2"></i>
                    Facturas Pendientes (<?= count($facturas_pendientes) ?>)
                </h5>
                <span class="badge bg-primary">No pagadas/no cerradas/no contabilizadas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($facturas_pendientes)): ?>
                    <div class="p-3 text-muted">
                        <i class="fas fa-check-circle me-2"></i>
                        No hay facturas pendientes
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Fecha Emisión</th>
                                    <th>Importe</th>
                                    <th>Estado</th>
                                    <th>Antigüedad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas_pendientes as $factura): 
                                    $clase_dias = $factura['dias_antiguedad'] > 30 ? 'danger' : ($factura['dias_antiguedad'] > 15 ? 'warning' : 'info');
                                ?>
                                <tr>
                                    <td>
                                        <a href="ver_factura.php?no_fact=<?php echo urlencode($factura['no_fact']); ?>" 
                                           class="text-white" 
                                           title="Ver esta factura" data-bs-toggle="tooltip">
                                                <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td><a title="Ver Información del Cliente" data-bs-toggle="tooltip" style="color: var(--win-text-primary);" href="ver_cliente.php?id=<?php echo htmlspecialchars($factura['cliente_id'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?></a></td>
                                    <td><?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?></td>
                                    <td class="text-end">$ <?= number_format($factura['total_general'], 2) ?></td>
                                    <td>
                                        <span class="text-dark badge bg-<?= $factura['estado'] == 'PENDIENTE' ? 'warning' : 'info' ?>">
                                            <?= $factura['estado'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-dark badge bg-<?= $clase_dias ?>">
                                            <?= $factura['dias_antiguedad'] ?> días
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="ver_factura.php?id=<?= $factura['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver factura">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="editar_factura.php?id=<?= $factura['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar factura">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SEGUNDA FILA: Facturas Sin Pago (col-12) -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card bg-dark text-white border-danger shadow collapsible-card h-100" id="card-facturas-sinpago">
            <div class="card-header bg-danger bg-opacity-25 border-danger d-flex justify-content-between align-items-center">
                <h5 class="mb-0 card-title-hover">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Facturas Sin Pago (<?= count($facturas_sin_pago) ?>)
                </h5>
                <span class="badge bg-danger">Sin referencia de pago</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($facturas_sin_pago)): ?>
                    <div class="p-3 text-muted">
                        <i class="fas fa-check-circle me-2"></i>
                        No hay facturas sin pago
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Fecha Emisión</th>
                                    <th>Importe</th>
                                    <th>Días vencida</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas_sin_pago as $factura): 
                                    $clase_dias = $factura['dias_vencida'] > 30 ? 'danger' : ($factura['dias_vencida'] > 15 ? 'warning' : 'info');
                                ?>
                                <tr>
                                    <td>
                                        <a href="ver_factura.php?no_fact=<?php echo urlencode($factura['no_fact']); ?>" 
                                            class="text-white" 
                                            title="Ver esta factura" data-bs-toggle="tooltip">
                                            <?php echo htmlspecialchars($factura['no_fact'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td><a style="color: var(--win-text-primary);" href="ver_cliente.php?id=<?php echo htmlspecialchars($factura['cliente_id'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'N/A'); ?></a></td>
                                    <td><?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?></td>
                                    <td class="text-end">$ <?= number_format($factura['total_general'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $clase_dias ?>">
                                            <?= $factura['dias_vencida'] ?> días
                                        </span>
                                    </td>
                                    <td>
									<button data-bs-toggle="tooltip" title="Marcar como Pagada Registrando la Referencia de Pago y la Fecha" onclick="marcarComoPagada(
										'<?= $factura['id'] ?>',
										'<?= htmlspecialchars($factura['no_fact']) ?>',
										'<?= htmlspecialchars($factura['cliente_nombre']) ?>',
										'<?= $factura['total_general'] ?>',
										'<?= $factura['fecha_emision'] ?>',
										'Efectivo' // o el método de pago que corresponda
									)" class="btn btn-sm btn-success" title="Marcar como pagada">
										<i class="fas fa-money-bill-wave"></i>
									</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- TERCERA FILA: Contratos Vencidos y Próximos a Vencer -->
                <div class="row">
                    <!-- CONTRATOS VENCIDOS - 6 columnas -->
                    <div class="col-md-6">
                        <div class="card bg-dark text-white border-danger mb-4 shadow collapsible-card" id="card-contratos-vencidos">
                            <div class="card-header bg-danger bg-opacity-25 border-danger d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 card-title-hover">
                                    <i class="fas fa-calendar-times me-2"></i>
                                    Contratos Vencidos (<?= count($contratos_vencidos) ?>)
                                </h5>
                                <?php if (!empty($contratos_vencidos)): ?>
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" title="Total de contratos vencidos">
                                        <i class="fas fa-exclamation-triangle me-1"></i><?= count($contratos_vencidos) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($contratos_vencidos)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                        <p class="mb-0">No hay contratos vencidos</p>
                                        <small>Todos los contratos están al día</small>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush bg-transparent">
                                        <?php foreach ($contratos_vencidos as $contrato): 
                                            $dias_vencido = abs($contrato['dias_vencido']);
                                            
                                            // Determinar clase de fondo según criticidad
                                            if ($dias_vencido > 730) { // Más de 2 años
                                                $bg_class = 'bg-critico-2years';
                                                $bg_style = 'background: linear-gradient(135deg, #4a0019, #8b0000, #b3003c); border-left: 6px solid #ff0040; box-shadow: 0 0 20px rgba(255, 0, 64, 0.7);';
                                                $badge_class = 'bg-dark border border-light';
                                                $icono_adicional = '<i class="fas fa-skull-crosswind ms-1"></i>';
                                                $texto_critico = '<span class="badge bg-white text-danger ms-2" style="font-size: 0.7rem; animation: pulseCritico 1s infinite;">CRÍTICO</span>';
                                            } elseif ($dias_vencido > 365) { // Más de 1 año
                                                $bg_class = 'bg-critico-1year';
                                                $bg_style = 'background: linear-gradient(135deg, #7a0026, #b3003c, #ff0040); border-left: 6px solid #ff6347; box-shadow: 0 0 15px rgba(255, 99, 71, 0.6);';
                                                $badge_class = 'bg-danger border border-light';
                                                $icono_adicional = '<i class="fas fa-exclamation-triangle ms-1"></i>';
                                                $texto_critico = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; animation: pulse 1.5s infinite;">MUY CRÍTICO</span>';
                                            } elseif ($dias_vencido > 180) { // Más de 6 meses
                                                $bg_class = 'bg-critico-6months';
                                                $bg_style = 'background: linear-gradient(135deg, #8b0000, #c0392b, #e74c3c); border-left: 6px solid #ff4500; box-shadow: 0 0 12px rgba(255, 69, 0, 0.5);';
                                                $badge_class = 'bg-danger border border-warning';
                                                $icono_adicional = '<i class="fas fa-exclamation-circle ms-1"></i>';
                                                $texto_critico = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">URGENTE</span>';
                                            } elseif ($dias_vencido > 90) { // Más de 3 meses
                                                $bg_class = 'bg-critico-3months';
                                                $bg_style = 'background: linear-gradient(135deg, #b22222, #cd5c5c, #e9967a); border-left: 6px solid #ff6346; box-shadow: 0 0 8px rgba(255, 99, 71, 0.4);';
                                                $badge_class = 'bg-warning text-dark border border-danger';
                                                $icono_adicional = '<i class="fas fa-exclamation ms-1"></i>';
                                                $texto_critico = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">PENDIENTE</span>';
                                            } elseif ($dias_vencido > 30) { // Más de 1 mes
                                                $bg_class = 'bg-critico-1month';
                                                $bg_style = 'background: linear-gradient(135deg, #cc7a00, #ff9900, #ffbb33); border-left: 6px solid #ffd700; box-shadow: 0 0 5px rgba(255, 215, 0, 0.3);';
                                                $badge_class = 'bg-warning text-dark';
                                                $icono_adicional = '<i class="fas fa-clock ms-1"></i>';
                                                $texto_critico = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">ATENCIÓN</span>';
                                            } else { // Menos de 30 días
                                                $bg_class = 'bg-critico-less30';
                                                $bg_style = 'background: linear-gradient(135deg, #006680, #0099cc, #33ccff); border-left: 6px solid #00ffff; box-shadow: 0 0 5px rgba(0, 255, 255, 0.3);';
                                                $badge_class = 'bg-info';
                                                $icono_adicional = '<i class="fas fa-info-circle ms-1"></i>';
                                                $texto_critico = '';
                                            }
                                            
                                            $tiempo_vencido = formatoTiempoLegible($dias_vencido);
                                        ?>
                                        <a href="editar_cliente.php?id=<?= $contrato['id'] ?>" 
                                           class="list-group-item list-group-item-action text-white border-0" 
                                           style="<?= $bg_style ?> transition: all 0.3s ease; border-bottom: 1px solid rgba(255,255,255,0.1) !important;">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                        <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem; color: #fff; text-shadow: 0 0 10px rgba(255,255,255,0.5);"></i>
                                                        <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($contrato['nombre']) ?></h6>
                                                        <?= $texto_critico ?>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-3 mb-2">
                                                        <small class="text-white-50">
                                                            <i class="fas fa-tag me-1"></i>Código: <?= htmlspecialchars($contrato['codigo'] ?? 'N/A') ?>
                                                        </small>
                                                        <small class="text-white-50">
                                                            <i class="fas fa-file-signature me-1"></i>Contrato: <?= htmlspecialchars($contrato['ContratoNo'] ?? 'N/A') ?>
                                                        </small>
                                                        <small class="text-white-50">
                                                            <i class="fas fa-calendar-alt me-1"></i>Venció: <?= date('d/m/Y', strtotime($contrato['fechafinalcontrato'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="badge <?= $badge_class ?>" style="font-size: 0.85rem; padding: 5px 10px; background: rgba(0,0,0,0.3); border: 1px solid currentColor;">
                                                            <i class="fas fa-clock me-1"></i><?= $tiempo_vencido ?> atrasado <?= $icono_adicional ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ms-2 d-flex flex-column align-items-end">
                                                    <span class="fw-bold" style="font-size: 1.5rem; text-shadow: 0 0 10px rgba(255,255,255,0.5);"><?= $dias_vencido ?></span>
                                                    <small class="text-white-50">días vencido</small>
                                                    <i class="fas fa-chevron-right text-white mt-2" style="opacity: 0.8;"></i>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Resumen de vencidos con estadísticas -->
                                    <div class="card-footer bg-transparent border-secondary p-3">
                                        <?php
                                        // Calcular estadísticas de criticidad
                                        $criticos_2years = count(array_filter($contratos_vencidos, function($c) { return abs($c['dias_vencido']) > 730; }));
                                        $criticos_1year = count(array_filter($contratos_vencidos, function($c) { $d = abs($c['dias_vencido']); return $d > 365 && $d <= 730; }));
                                        $criticos_6months = count(array_filter($contratos_vencidos, function($c) { $d = abs($c['dias_vencido']); return $d > 180 && $d <= 365; }));
                                        $criticos_3months = count(array_filter($contratos_vencidos, function($c) { $d = abs($c['dias_vencido']); return $d > 90 && $d <= 180; }));
                                        $criticos_1month = count(array_filter($contratos_vencidos, function($c) { $d = abs($c['dias_vencido']); return $d > 30 && $d <= 90; }));
                                        $criticos_less30 = count(array_filter($contratos_vencidos, function($c) { return abs($c['dias_vencido']) <= 30; }));
                                        
                                        $total_vencidos = count($contratos_vencidos);
                                        ?>
                                        
                                        <div class="row g-2 mb-3">
                                            <?php if ($criticos_2years > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #4a0019; border: 1px solid #ff0040; animation: pulseCritico 1.5s infinite;">
                                                    <small class="text-white-50 d-block">>2 años</small>
                                                    <span class="fw-bold text-white"><?= $criticos_2years ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($criticos_1year > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #7a0026; border: 1px solid #ff6347;">
                                                    <small class="text-white-50 d-block">>1 año</small>
                                                    <span class="fw-bold text-white"><?= $criticos_1year ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($criticos_6months > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #8b0000; border: 1px solid #ff4500;">
                                                    <small class="text-white-50 d-block">>6 meses</small>
                                                    <span class="fw-bold text-white"><?= $criticos_6months ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($criticos_3months > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #b22222; border: 1px solid #ff6346;">
                                                    <small class="text-white-50 d-block">>3 meses</small>
                                                    <span class="fw-bold text-white"><?= $criticos_3months ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($criticos_1month > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #cc7a00; border: 1px solid #ffbb33;">
                                                    <small class="text-white-50 d-block">>1 mes</small>
                                                    <span class="fw-bold text-white"><?= $criticos_1month ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($criticos_less30 > 0): ?>
                                            <div class="col-4 col-md-2">
                                                <div class="p-2 rounded text-center" style="background: #006680; border: 1px solid #33ccff;">
                                                    <small class="text-white-50 d-block"><30 días</small>
                                                    <span class="fw-bold text-white"><?= $criticos_less30 ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted d-block">Total vencidos:</small>
                                                <span class="h5 text-white mb-0"><?= $total_vencidos ?></span>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">Más antiguo:</small>
                                                <?php
                                                if (!empty($contratos_vencidos)) {
                                                    $max_dias = max(array_column($contratos_vencidos, 'dias_vencido'));
                                                    echo '<span class="badge bg-white text-dark">' . formatoTiempoLegible(abs($max_dias)) . '</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Barra de progreso de criticidad -->
                                        <div class="mt-3">
                                            <div class="progress" style="height: 8px; background: rgba(0,0,0,0.5);">
                                                <?php
                                                if ($total_vencidos > 0) {
                                                    if ($criticos_2years > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_2years/$total_vencidos*100) . '%; background: #4a0019;" data-bs-toggle="tooltip" title="' . $criticos_2years . ' >2 años"></div>';
                                                    if ($criticos_1year > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_1year/$total_vencidos*100) . '%; background: #7a0026;" data-bs-toggle="tooltip" title="' . $criticos_1year . ' >1 año"></div>';
                                                    if ($criticos_6months > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_6months/$total_vencidos*100) . '%; background: #8b0000;" data-bs-toggle="tooltip" title="' . $criticos_6months . ' >6 meses"></div>';
                                                    if ($criticos_3months > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_3months/$total_vencidos*100) . '%; background: #b22222;" data-bs-toggle="tooltip" title="' . $criticos_3months . ' >3 meses"></div>';
                                                    if ($criticos_1month > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_1month/$total_vencidos*100) . '%; background: #cc7a00;" data-bs-toggle="tooltip" title="' . $criticos_1month . ' >1 mes"></div>';
                                                    if ($criticos_less30 > 0) echo '<div class="progress-bar" style="width: ' . ($criticos_less30/$total_vencidos*100) . '%; background: #006680;" data-bs-toggle="tooltip" title="' . $criticos_less30 . ' <30 días"></div>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($total_vencidos > 5): ?>
                                            <div class="mt-3 text-center">
                                                <a href="clientes.php?contract_status=vencido&pagina=1&orden=nombre&direccion=ASC&registros_por_pagina=todos" 
                                                   class="btn btn-sm w-100" 
                                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white;">
                                                    Ver todos (<?= $total_vencidos ?>) <i class="fas fa-arrow-right ms-2"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- CONTRATOS PRÓXIMOS A VENCER - 6 columnas -->
                    <div class="col-md-6">
                        <div class="card bg-dark text-white border-warning mb-4 shadow collapsible-card" id="card-contratos-proximos">
                            <div class="card-header bg-warning bg-opacity-25 border-warning d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 card-title-hover">
                                    <i class="fas fa-clock me-2"></i>
                                    Próximos a Vencer (<?= count($contratos_proximos) ?>)
                                </h5>
                                <?php if (!empty($contratos_proximos)): ?>
                                    <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Vencen en los próximos 30 días">
                                        <i class="fas fa-hourglass-half me-1"></i><?= count($contratos_proximos) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($contratos_proximos)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="fas fa-calendar-check fa-3x mb-3 text-success"></i>
                                        <p class="mb-0">No hay contratos próximos a vencer</p>
                                        <small>Todos los contratos tienen vigencia mayor a 30 días</small>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush bg-transparent">
                                        <?php foreach ($contratos_proximos as $contrato): 
                                            $dias_restantes = $contrato['dias_restantes'];
                                            
                                            // Determinar clase de fondo según urgencia
                                            if ($dias_restantes <= 3) {
                                                $bg_class = 'bg-urgente-3dias';
                                                $bg_style = 'background: linear-gradient(135deg, #b3002d, #ff1a1a, #ff6666); border-left: 6px solid #ff9900; box-shadow: 0 0 15px rgba(255, 26, 26, 0.6); animation: urgentPulse 1s infinite;';
                                                $badge_class = 'bg-danger border border-warning';
                                                $icono_adicional = '<i class="fas fa-exclamation-triangle ms-1"></i>';
                                                $texto_urgente = '<span class="badge bg-white text-danger ms-2" style="font-size: 0.7rem; animation: pulseCritico 0.8s infinite;">¡URGENTE!</span>';
                                            } elseif ($dias_restantes <= 7) {
                                                $bg_class = 'bg-urgente-semana';
                                                $bg_style = 'background: linear-gradient(135deg, #cc3300, #ff6600, #ff944d); border-left: 6px solid #ffaa00; box-shadow: 0 0 12px rgba(255, 102, 0, 0.5);';
                                                $badge_class = 'bg-danger';
                                                $icono_adicional = '<i class="fas fa-exclamation-circle ms-1"></i>';
                                                $texto_urgente = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; animation: pulse 1.5s infinite;">URGENTE</span>';
                                            } elseif ($dias_restantes <= 15) {
                                                $bg_class = 'bg-urgente-2semanas';
                                                $bg_style = 'background: linear-gradient(135deg, #b37500, #ffaa00, #ffcc66); border-left: 6px solid #ffdd55; box-shadow: 0 0 8px rgba(255, 170, 0, 0.4);';
                                                $badge_class = 'bg-warning text-dark';
                                                $icono_adicional = '<i class="fas fa-exclamation-circle ms-1"></i>';
                                                $texto_urgente = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem;">PRÓXIMO</span>';
                                            } else {
                                                $bg_class = 'bg-urgente-mes';
                                                $bg_style = 'background: linear-gradient(135deg, #006680, #0099cc, #66ccff); border-left: 6px solid #33ccff; box-shadow: 0 0 5px rgba(0, 153, 204, 0.3);';
                                                $badge_class = 'bg-info';
                                                $icono_adicional = '<i class="fas fa-clock ms-1"></i>';
                                                $texto_urgente = '';
                                            }
                                            
                                            $tiempo_restante = formatoTiempoLegible($dias_restantes);
                                            $icono = $dias_restantes <= 7 ? 'fa-exclamation-triangle' : ($dias_restantes <= 15 ? 'fa-exclamation-circle' : 'fa-clock');
                                        ?>
                                        <a href="editar_cliente.php?id=<?= $contrato['id'] ?>" 
                                           class="list-group-item list-group-item-action text-white border-0" 
                                           style="<?= $bg_style ?> transition: all 0.3s ease; border-bottom: 1px solid rgba(255,255,255,0.1) !important;">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                        <i class="fas <?= $icono ?>" style="font-size: 1.1rem; color: #fff; text-shadow: 0 0 10px rgba(255,255,255,0.5);"></i>
                                                        <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($contrato['nombre']) ?></h6>
                                                        <?= $texto_urgente ?>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-3 mb-2">
                                                        <small class="text-white-50">
                                                            <i class="fas fa-tag me-1"></i>Código: <?= htmlspecialchars($contrato['codigo'] ?? 'N/A') ?>
                                                        </small>
                                                        <small class="text-white-50">
                                                            <i class="fas fa-file-signature me-1"></i>Contrato: <?= htmlspecialchars($contrato['ContratoNo'] ?? 'N/A') ?>
                                                        </small>
                                                        <small class="text-white-50">
                                                            <i class="fas fa-calendar-alt me-1"></i>Vence: <?= date('d/m/Y', strtotime($contrato['fechafinalcontrato'])) ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="badge <?= $badge_class ?>" style="font-size: 0.85rem; padding: 5px 10px; background: rgba(0,0,0,0.3); border: 1px solid currentColor;">
                                                            <i class="fas fa-hourglass-half me-1"></i><?= $tiempo_restante ?> <?= $icono_adicional ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ms-2 d-flex flex-column align-items-end">
                                                    <span class="fw-bold" style="font-size: 1.5rem; text-shadow: 0 0 10px rgba(255,255,255,0.5);"><?= $dias_restantes ?></span>
                                                    <small class="text-white-50">días restantes</small>
                                                    <i class="fas fa-chevron-right text-white mt-2" style="opacity: 0.8;"></i>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Resumen de próximos con estadísticas -->
                                    <div class="card-footer bg-transparent border-secondary p-3">
                                        <?php
                                        // Calcular estadísticas de urgencia
                                        $urgentes_3dias = count(array_filter($contratos_proximos, function($c) { return $c['dias_restantes'] <= 3; }));
                                        $urgentes_semana = count(array_filter($contratos_proximos, function($c) { return $c['dias_restantes'] > 3 && $c['dias_restantes'] <= 7; }));
                                        $urgentes_2semanas = count(array_filter($contratos_proximos, function($c) { return $c['dias_restantes'] > 7 && $c['dias_restantes'] <= 15; }));
                                        $urgentes_mes = count(array_filter($contratos_proximos, function($c) { return $c['dias_restantes'] > 15; }));
                                        
                                        $total_proximos = count($contratos_proximos);
                                        ?>
                                        
                                        <div class="row g-2 mb-3">
                                            <?php if ($urgentes_3dias > 0): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 rounded text-center" style="background: #b3002d; border: 1px solid #ff9900; animation: urgentPulse 1s infinite;">
                                                    <small class="text-white-50 d-block">≤3 días</small>
                                                    <span class="fw-bold text-white"><?= $urgentes_3dias ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($urgentes_semana > 0): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 rounded text-center" style="background: #cc3300; border: 1px solid #ffaa00;">
                                                    <small class="text-white-50 d-block">≤7 días</small>
                                                    <span class="fw-bold text-white"><?= $urgentes_semana ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($urgentes_2semanas > 0): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 rounded text-center" style="background: #b37500; border: 1px solid #ffdd55;">
                                                    <small class="text-white-50 d-block">≤15 días</small>
                                                    <span class="fw-bold text-white"><?= $urgentes_2semanas ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($urgentes_mes > 0): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 rounded text-center" style="background: #006680; border: 1px solid #33ccff;">
                                                    <small class="text-white-50 d-block">≤30 días</small>
                                                    <span class="fw-bold text-white"><?= $urgentes_mes ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted d-block">Total próximos:</small>
                                                <span class="h5 text-white mb-0"><?= $total_proximos ?></span>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">Más próximo:</small>
                                                <?php
                                                if (!empty($contratos_proximos)) {
                                                    $min_dias = min(array_column($contratos_proximos, 'dias_restantes'));
                                                    echo '<span class="badge bg-white text-dark">' . formatoTiempoLegible($min_dias) . '</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Barra de progreso de urgencia -->
                                        <div class="mt-3">
                                            <div class="progress" style="height: 8px; background: rgba(0,0,0,0.5);">
                                                <?php
                                                if ($total_proximos > 0) {
                                                    if ($urgentes_3dias > 0) echo '<div class="progress-bar" style="width: ' . ($urgentes_3dias/$total_proximos*100) . '%; background: #b3002d; animation: progressPulse 1s infinite;" data-bs-toggle="tooltip" title="' . $urgentes_3dias . ' ≤3 días"></div>';
                                                    if ($urgentes_semana > 0) echo '<div class="progress-bar" style="width: ' . ($urgentes_semana/$total_proximos*100) . '%; background: #cc3300;" data-bs-toggle="tooltip" title="' . $urgentes_semana . ' 4-7 días"></div>';
                                                    if ($urgentes_2semanas > 0) echo '<div class="progress-bar" style="width: ' . ($urgentes_2semanas/$total_proximos*100) . '%; background: #b37500;" data-bs-toggle="tooltip" title="' . $urgentes_2semanas . ' 8-15 días"></div>';
                                                    if ($urgentes_mes > 0) echo '<div class="progress-bar" style="width: ' . ($urgentes_mes/$total_proximos*100) . '%; background: #006680;" data-bs-toggle="tooltip" title="' . $urgentes_mes . ' 16-30 días"></div>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($total_proximos > 5): ?>
                                            <div class="mt-3 text-center">
                                                <a href="clientes.php?contract_status=proximo&pagina=1&orden=nombre&direccion=ASC&registros_por_pagina=todos" 
                                                   class="btn btn-sm w-100" 
                                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white;">
                                                    Ver todos (<?= $total_proximos ?>) <i class="fas fa-arrow-right ms-2"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<!-- Quick Actions (Botones flotantes) - SIEMPRE VISIBLES -->
<div class="win-quick-actions" style="display: flex; flex-direction: column; gap: 8px;">
    <!-- Botón de Ir al Inicio de la Página -->
    <button class="win-quick-action action-home" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Ir al inicio de la página">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Botón de Ir al Final de la Página -->
    <button class="win-quick-action action-end" onclick="window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})" title="Ir al final de la página">
        <i class="fas fa-arrow-down"></i>
    </button>
    
    <!-- Botón: Período Actual y Cierres Contables -->
    <button class="win-quick-action action-calendar" onclick="
        const periodoActual = document.getElementById('card-periodo-actual');
        if (periodoActual) {
            periodoActual.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else {
            document.querySelector('.col-lg-6 .card.border-info').scrollIntoView({behavior: 'smooth', block: 'start'});
        }" 
        title="Ir a Período Actual y Cierres Contables">
        <i class="fas fa-calendar-alt"></i>
    </button>
    
    <!-- Botón: Últimos Cierres realizados -->
    <button class="win-quick-action action-closures" onclick="document.querySelector('.card .card-header.bg-secondary').scrollIntoView({behavior: 'smooth', block: 'start'});" 
            title="Ir a Últimos Cierres realizados">
        <i class="fas fa-history"></i>
    </button>
    
    <!-- Botón: Facturas Pendientes -->
    <button class="win-quick-action action-pending-invoices" onclick="document.getElementById('card-facturas-pendientes').scrollIntoView({behavior: 'smooth', block: 'start'});" 
            title="Ir a Facturas Pendientes">
        <i class="fas fa-file-invoice"></i>
    </button>
    
    <!-- Botón: Facturas Sin Pago -->
    <button class="win-quick-action action-unpaid-invoices" onclick="document.getElementById('card-facturas-sinpago').scrollIntoView({behavior: 'smooth', block: 'start'});" 
            title="Ir a Facturas Sin Pago">
        <i class="fas fa-exclamation-circle"></i>
    </button>
    
    <!-- Botón: Contratos Vencidos y Próximos a vencer -->
    <button class="win-quick-action action-contracts" onclick="
        const contratosVencidos = document.getElementById('card-contratos-vencidos');
        const contratosProximos = document.getElementById('card-contratos-proximos');
        
        if (contratosVencidos) {
            contratosVencidos.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else if (contratosProximos) {
            contratosProximos.scrollIntoView({behavior: 'smooth', block: 'start'});
        }" 
        title="Ir a Contratos Vencidos y Próximos a vencer">
        <i class="fas fa-file-contract"></i>
    </button>
</div>
   <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let themePanelOpen = false;
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;

        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('open');
            } else {
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
                
                guardarPreferencia('sidebar_mini', sidebarMini);
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

        // Toggle Quick Actions
        function toggleQuickActions() {
            const expandedActions = document.getElementById('quickActionsExpanded');
            const mainButton = document.getElementById('mainQuickAction');
            
            if (!expandedActions) return;
            
            const isExpanded = expandedActions.classList.contains('show');
            
            if (isExpanded) {
                expandedActions.classList.remove('show');
                mainButton.innerHTML = '<i class="fas fa-plus"></i>';
                mainButton.title = 'Mostrar acciones rápidas';
                mainButton.style.transform = 'rotate(0deg)';
            } else {
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
            // Cerrar panel de temas al hacer clic en overlay
            document.getElementById('themeOverlay')?.addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            // Inicializar tooltips
            if (typeof bootstrap !== 'undefined') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        container: 'body',
                        trigger: 'hover focus',
                        placement: 'auto',
                        boundary: 'clippingParents',
                        html: true,
                        fallbackPlacements: ['bottom', 'right', 'left'],
                        delay: { "show": 100, "hide": 100 }
                    });
                });
            }
        });
    </script>
	<?php
	    if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
	?>
<!-- Script para Quick Action de Ir al Inicio/Fin y Cards colapsables - TODOS CERRADOS POR DEFECTO -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // 1. QUICK ACTIONS - YA CONFIGURADAS EN EL HTML
    // ============================================
    
    // ============================================
    // 2. CARDS CON CHEVRON PARA ABRIR/CERRAR
    // ============================================
    
    // Seleccionar todos los cards que queremos hacer colapsables
    const collapsibleCards = document.querySelectorAll('.collapsible-card');
    
    collapsibleCards.forEach((card, index) => {
        const cardHeader = card.querySelector('.card-header');
        const cardBody = card.querySelector('.card-body');
        
        if (!cardHeader || !cardBody) return;
        
        // Verificar si ya tiene el botón de colapsar
        if (cardHeader.querySelector('.card-collapse-btn')) return;
        
        // Crear botón de colapsar
        const collapseBtn = document.createElement('button');
        collapseBtn.className = 'btn btn-sm btn-outline-secondary card-collapse-btn';
        collapseBtn.setAttribute('type', 'button');
        collapseBtn.style.cssText = `
            padding: 2px 8px;
            margin-left: 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
            border: none;
            background: transparent;
        `;
        
        // POR DEFECTO: TODOS LOS CARDS COMIENZAN CERRADOS
        const isCollapsed = true;
        
        // Icono inicial (chevron down = cerrado)
        collapseBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
        
        // Aplicar estado inicial - TODOS CERRADOS
        cardBody.style.display = 'none';
        
        // Añadir al header
        const titleElement = cardHeader.querySelector('h5, .h5, .mb-0');
        
        // Agregar clase para efecto hover
        if (titleElement) {
            titleElement.classList.add('card-title-hover');
            titleElement.appendChild(collapseBtn);
        } else {
            const div = document.createElement('div');
            div.className = 'd-flex align-items-center card-title-hover';
            div.innerHTML = cardHeader.innerHTML;
            div.appendChild(collapseBtn);
            cardHeader.innerHTML = '';
            cardHeader.appendChild(div);
        }
        
        // Evento click
        collapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isNowCollapsed = cardBody.style.display === 'none';
            
            if (isNowCollapsed) {
                // Expandir
                cardBody.style.display = '';
                collapseBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                
                // Animación suave
                cardBody.style.opacity = '0';
                cardBody.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    cardBody.style.transition = 'all 0.3s ease';
                    cardBody.style.opacity = '1';
                    cardBody.style.transform = 'translateY(0)';
                }, 10);
            } else {
                // Colapsar
                cardBody.style.transition = 'all 0.3s ease';
                cardBody.style.opacity = '0';
                cardBody.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    cardBody.style.display = 'none';
                    cardBody.style.transition = '';
                }, 300);
                
                collapseBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
            }
        });
    });
    
    // ============================================
    // 3. BOTÓN DE EXPANDIR/COLAPSAR TODO
    // ============================================
    
    const toggleAllBtn = document.getElementById('toggleAllCardsBtn');
    
    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', function() {
            const allCards = document.querySelectorAll('.collapsible-card');
            const isAnyCollapsed = Array.from(allCards).some(card => {
                const body = card.querySelector('.card-body, .card-body.p-0');
                return body && body.style.display === 'none';
            });
            
            if (isAnyCollapsed) {
                // Expandir todos
                allCards.forEach(card => {
                    const body = card.querySelector('.card-body, .card-body.p-0');
                    const btn = card.querySelector('.card-collapse-btn i');
                    
                    if (body && body.style.display === 'none') {
                        body.style.display = '';
                        if (btn) {
                            btn.className = 'fas fa-chevron-up';
                        }
                        
                        // Animación
                        body.style.opacity = '0';
                        body.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            body.style.transition = 'all 0.3s ease';
                            body.style.opacity = '1';
                            body.style.transform = 'translateY(0)';
                        }, 10);
                    }
                });
                
                toggleAllBtn.innerHTML = '<i class="fas fa-compress-alt me-1"></i>Colapsar todo';
            } else {
                // Colapsar todos
                allCards.forEach(card => {
                    const body = card.querySelector('.card-body, .card-body.p-0');
                    const btn = card.querySelector('.card-collapse-btn i');
                    
                    if (body && body.style.display !== 'none') {
                        body.style.transition = 'all 0.3s ease';
                        body.style.opacity = '0';
                        body.style.transform = 'translateY(-10px)';
                        
                        setTimeout(() => {
                            body.style.display = 'none';
                            body.style.transition = '';
                        }, 300);
                        
                        if (btn) {
                            btn.className = 'fas fa-chevron-down';
                        }
                    }
                });
                
                toggleAllBtn.innerHTML = '<i class="fas fa-expand-alt me-1"></i>Expandir todo';
            }
        });
    }
    
    // ============================================
    // 4. ESTILOS ADICIONALES - EFECTO HOVER SUTIL
    // ============================================
    
    const style = document.createElement('style');
    style.textContent = `
        /* Estilo para el botón de home */
        .action-home {
            background: #6c757d !important;
        }
        
        .action-home:hover {
            background: #5a6268 !important;
            transform: scale(1.15) rotate(0deg) !important;
        }
        
        /* Estilo para el botón de ir al final */
        .action-end {
            background: #495057 !important;
        }
        
        .action-end:hover {
            background: #343a40 !important;
            transform: scale(1.15) rotate(0deg) !important;
        }
        
        /* Estilo para el botón de período actual */
        .action-calendar {
            background: #0078d4 !important;
        }
        
        .action-calendar:hover {
            background: #005a9e !important;
        }
        
        /* Estilo para el botón de cierres */
        .action-closures {
            background: #5c2d91 !important;
        }
        
        .action-closures:hover {
            background: #412063 !important;
        }
        
        /* Estilo para el botón de facturas pendientes */
        .action-pending-invoices {
            background: #0d6efd !important;
        }
        
        .action-pending-invoices:hover {
            background: #0b5ed7 !important;
        }
        
        /* Estilo para el botón de facturas sin pago */
        .action-unpaid-invoices {
            background: #dc3545 !important;
        }
        
        .action-unpaid-invoices:hover {
            background: #bb2d3b !important;
        }
        
        /* Estilo para el botón de contratos */
        .action-contracts {
            background: #ffc107 !important;
            color: #212529 !important;
        }
        
        .action-contracts:hover {
            background: #e6a800 !important;
        }
        
        /* Animación para los cards al colapsar/expandir */
        .card-body, .card-body.p-0 {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        /* EFECTO HOVER SUTIL PARA LOS TÍTULOS */
        .card-title-hover {
            transition: color 0.2s ease, transform 0.2s ease;
            cursor: default;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .card-title-hover:hover {
            color: var(--win-accent) !important;
            transform: translateX(2px);
        }
        
        /* Efecto hover para el botón de colapsar */
        .card-collapse-btn {
            transition: all 0.2s ease !important;
            opacity: 0.7;
        }
        
        .card-collapse-btn:hover {
            opacity: 1;
            transform: scale(1.1) !important;
            background: var(--win-accent-light) !important;
        }
        
        .card-collapse-btn:hover i {
            color: var(--win-accent) !important;
        }
        
        /* Estilo para el botón de expandir/colapsar todo */
        .btn-outline-secondary:hover {
            background: var(--win-accent-light) !important;
            border-color: var(--win-accent) !important;
            color: var(--win-accent) !important;
        }
        
        /* Ajuste para que los headers tengan espacio para el botón */
        .card-header h5, .card-header .h5, .card-header .mb-0 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        /* Efecto sutil adicional para los íconos de los títulos */
        .card-title-hover i:not(.fa-chevron-down):not(.fa-chevron-up) {
            transition: transform 0.2s ease;
        }
        
        .card-title-hover:hover i:not(.fa-chevron-down):not(.fa-chevron-up) {
            transform: scale(1.1);
        }
        
        /* Sticky header */
        .sticky-header {
            position: sticky;
            top: 48px;
            z-index: 100;
            background: var(--win-bg-secondary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .win-main-content > .row:first-child .card-body .d-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .btn-outline-secondary.ms-3 {
                margin-left: 0 !important;
            }
            
            .sticky-header {
                top: 48px;
            }
        }
    `;
    
    document.head.appendChild(style);
});

// Función adicional para ir al inicio con diferentes comportamientos
function irAlInicio(opcion = 'smooth') {
    if (opcion === 'smooth') {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    } else if (opcion === 'instant') {
        window.scrollTo(0, 0);
    } else if (opcion === 'top') {
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
    }
}

// Función adicional para ir al final
function irAlFinal(opcion = 'smooth') {
    if (opcion === 'smooth') {
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    } else if (opcion === 'instant') {
        window.scrollTo(0, document.body.scrollHeight);
    } else if (opcion === 'end') {
        document.body.scrollTop = document.body.scrollHeight;
        document.documentElement.scrollTop = document.documentElement.scrollHeight;
    }
}
// ============================================
// 5. FUNCIÓN PARA MARCAR FACTURAS COMO PAGADAS
// ============================================
// Se agrega el parámetro 'tipoPago' al final de los argumentos
function marcarComoPagada(id, noFactura, cliente, importe, fechaEmisionFactura, tipoPago) {
    // ==========================================
    // 1. CONFIGURACIÓN Y ESTILOS
    // ==========================================
    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
    const isDark = theme === 'dark';
    const style = getComputedStyle(document.documentElement);
    const bgColor = style.getPropertyValue('--win-bg-secondary').trim();
    const textColor = style.getPropertyValue('--win-text-primary').trim();
    const accentColor = style.getPropertyValue('--win-accent').trim();

    // Fallback por si no se envía el tipo de pago
    const textoTipoPago = tipoPago || 'No especificado';

    // Configuración base para todos los modals
    const swalConfig = {
        background: bgColor,
        color: textColor,
        width: '700px',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        customClass: { popup: 'mica-effect border-win' }
    };

    // Helpers
    const formatToFull12h = (timeStr) => {
        if (!timeStr) return "00:00:00 AM";
        const parts = timeStr.split(' '); 
        return `${parts[0]}:00 ${parts[1]}`; 
    };

    function formatearFechaDDMMYYYY(fechaISO) {
        if (!fechaISO) return '';
        const fechaPartes = fechaISO.split(' ')[0];
        const [anio, mes, dia] = fechaPartes.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    const fechaEmisionFormateada = formatearFechaDDMMYYYY(fechaEmisionFactura);
    const fechaEmisionISO = fechaEmisionFactura.split(' ')[0];

    // ==========================================
    // 2. FUNCIÓN INTERNA: MOSTRAR FORMULARIO
    // ==========================================
    const mostrarFormularioPago = (datosPrevios = null) => {
        const ahora = new Date();
        
        let valorFecha, valorHora, valorRef;

        // Determinar valores iniciales
        if (datosPrevios) {
            valorFecha = datosPrevios.f;
            valorHora = datosPrevios.h;
            valorRef = datosPrevios.r;
        } else {
            const fechaOperativaActual = (typeof FECHA_HOY_OPERATIVA !== 'undefined') 
                                        ? FECHA_HOY_OPERATIVA 
                                        : ahora.toISOString().split('T')[0];
            
            valorFecha = (fechaEmisionISO > fechaOperativaActual) ? fechaEmisionISO : fechaOperativaActual;
            
            valorHora = ahora.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', hour12: true 
            });
            valorRef = '';
        }

        // Función global para el botón HOY
        window.setPagoHoy = function() {
            const input = document.getElementById('fechaPago');
            if(input) {
                const fOperativa = (typeof FECHA_HOY_OPERATIVA !== 'undefined') 
                                   ? FECHA_HOY_OPERATIVA 
                                   : new Date().toISOString().split('T')[0];
                input.value = fOperativa;
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('change'));
                input.focus();
            }
        };

        const htmlForm = `
            <div class="text-start" style="color: ${textColor}">
<div class="mb-3 border-bottom pb-2" style="border-color: var(--win-border-color) !important;">
                    <h6 class="mb-0">Registrar cobro para: <span class="fw-bold" style="color: ${accentColor}">${cliente}</span></h6>
                    <!-- AQUI MOSTRAMOS EL TIPO DE PAGO TAMBIÉN EN EL PRIMER MODAL -->
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="opacity-100">Factura: <span class="fw-bold" style="color: ${accentColor}">${noFactura}</span> | Importe: <span class="fw-bold" style="color: ${accentColor}">$${importe}</span></small>
                        <span class="badge bg-secondary">${textoTipoPago}</span>
                    </div>
                    <div class="mt-1">
                        <small class="text-warning">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Fecha de emisión: <strong>${fechaEmisionFormateada}</strong>
                        </small>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-2 container-fecha">
                        <label class="form-label small fw-bold">Fecha de pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-calendar-check"></i></span>
                            <input type="date" id="fechaPago" class="form-control win-input-dynamic" 
                                   value="${valorFecha}" min="${fechaEmisionISO}"
                                   title="No puede ser anterior a la emisión">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.setPagoHoy()" 
                                    style="border-color: var(--win-border-color); color: var(--win-text-primary);"
                                    title="Establecer fecha operativa actual">
                                <i class="fas fa-calendar-day"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">Mínimo: ${fechaEmisionFormateada}</small>
                    </div>
                    
                    <div class="col-md-6 mb-2">
                        <label class="form-label small fw-bold">Hora de pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-clock"></i></span>
                            <input type="text" id="horaPago" class="form-control win-input-dynamic" 
                                   value="${valorHora}" readonly>
                        </div>
                    </div>
                    
                    <div class="col-12 mb-2">
                        <label class="form-label small fw-bold">Referencia de Pago:</label>
                        <div class="input-group">
                            <span class="input-group-text win-input-dynamic"><i class="fas fa-receipt"></i></span>
                            <input type="text" id="refPago" class="form-control win-input-dynamic" 
                                   placeholder="EJ: TRANSF-9988" style="text-transform: uppercase;" 
                                   value="${valorRef}" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>
            <style>
                .win-input-dynamic { background-color: var(--win-bg-tertiary) !important; border: 1px solid var(--win-border-color) !important; color: var(--win-text-primary) !important; }
                input[type="date"]::-webkit-calendar-picker-indicator { filter: ${isDark ? 'invert(1)' : 'invert(0)'}; cursor: pointer; }
                .border-win { border: 1px solid var(--win-border_color) !important; }
            </style>
        `;

        Swal.fire({
            ...swalConfig,
            title: datosPrevios ? 'Corregir Pago' : 'Detalles del Pago',
            html: htmlForm,
            showCancelButton: true,
            confirmButtonText: 'Siguiente <i class="fas fa-arrow-right ms-1"></i>',
            cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
            didOpen: () => {
                mdtimepicker('#horaPago', { timeFormat: 'hh:mm tt', theme: isDark ? 'dark' : 'blue', hourPadding: true });
                
                const inputRef = document.getElementById('refPago');
                if (inputRef) {
                    setTimeout(() => {
                        inputRef.focus();
                        inputRef.setSelectionRange(inputRef.value.length, inputRef.value.length);
                    }, 100);
                }

                // Validación Visual
                const fechaPagoInput = document.getElementById('fechaPago');
                const validarVisualmente = () => {
                    const container = fechaPagoInput.closest('.col-md-6');
                    let errorDiv = container.querySelector('.invalid-feedback-custom');

                    if (fechaPagoInput.value && fechaEmisionISO && fechaPagoInput.value < fechaEmisionISO) {
                        fechaPagoInput.classList.add('is-invalid');
                        fechaPagoInput.classList.remove('is-valid');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback-custom text-danger fw-bold mt-1 animate__animated animate__fadeIn';
                            errorDiv.style.fontSize = '0.85em';
                            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Fecha inválida<br>(Anterior a la Fecha de Emitida la Factura)`;
                            container.appendChild(errorDiv);
                        }
                    } else {
                        fechaPagoInput.classList.remove('is-invalid');
                        if (fechaPagoInput.value) fechaPagoInput.classList.add('is-valid');
                        if (errorDiv) errorDiv.remove();
                    }
                };
                fechaPagoInput.addEventListener('change', validarVisualmente);
                fechaPagoInput.addEventListener('input', validarVisualmente);
                validarVisualmente();
            },
            preConfirm: () => {
                const f = document.getElementById('fechaPago').value;
                const h = document.getElementById('horaPago').value;
                const r = document.getElementById('refPago').value.trim();
                
                if (!f || !h || !r) {
                    Swal.showValidationMessage('Todos los campos son obligatorios');
                    return false;
                }
                if (fechaEmisionISO && f < fechaEmisionISO) {
                    const [a, m, d] = f.split('-');
                    Swal.showValidationMessage(`La fecha (${d}/${m}/${a}) no puede ser anterior a la emisión`);
                    return false;
                }
                return { f, h, r: r.toUpperCase() };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                mostrarConfirmacion(result.value);
            }
        });
    };

    // ==========================================
    // 3. FUNCIÓN INTERNA: CONFIRMACIÓN
    // ==========================================
    const mostrarConfirmacion = (datosCapturados) => {
        const fechaMostrar = datosCapturados.f.split('-').reverse().join('/');
        const horaConSegundos = formatToFull12h(datosCapturados.h);

        Swal.fire({
            ...swalConfig,
            title: '¿Confirmar Datos de Pago?',
            icon: 'warning',
            html: `
                <div class="text-start p-3 rounded" style="background: rgba(128,128,128,0.1); color: ${textColor}; line-height: 1.6;">
                    <p class="mb-1"><b>Cliente:</b> <span style="color:yellow">${cliente}</span></p>
                    <p class="mb-1"><b>Factura:</b> ${noFactura}</p>
                    <p class="mb-1"><b>Método de Pago:</b> <span class="badge bg-secondary">${textoTipoPago}</span></p>
                    <p class="mb-3"><b>Total a Cobrar:</b> <span class="text-success fw-bold">$${importe}</span></p>
                    
                    <div style="border-top: 1px solid var(--win-border-color); padding-top: 10px;">
                        <p class="mb-1"><b>Fecha Pago:</b> ${fechaMostrar} a las ${horaConSegundos}</p>
                        <p class="mb-0"><b>Referencia:</b> <span style="color: ${accentColor}">${datosCapturados.r}</span></p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Sí, Marcar Pagada',
            cancelButtonText: '<i class="fas fa-edit me-1"></i> Corregir'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    ...swalConfig,
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const [time, modifier] = datosCapturados.h.split(' ');
                let [hours, minutes] = time.split(':');
                if (hours === '12') hours = '00';
                if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
                const hora24 = `${hours.toString().padStart(2, '0')}:${minutes}:00`;

                const formData = new FormData();
                formData.append('id', id);
                formData.append('fecha_hora', `${datosCapturados.f} ${hora24}`);
                formData.append('ref_pago', datosCapturados.r);
                formData.append('accion', 'marcar_pagada');

                fetch('marcar_factura_pagada.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ 
                            ...swalConfig, icon: 'success', title: '¡Completado!', text: data.message,
                            confirmButtonText: '<i class="fas fa-check me-1"></i> Aceptar'
                        }).then(() => { window.location.reload(); });
                    } else {
                        Swal.fire({ ...swalConfig, icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(() => {
                    Swal.fire({ ...swalConfig, icon: 'error', title: 'Error de Red', text: 'No se pudo contactar con el servidor' });
                });

            } else if (result.dismiss === Swal.DismissReason.cancel) {
                mostrarFormularioPago(datosCapturados);
            }
        });
    };

    mostrarFormularioPago();
}
</script>

</body>
</html>