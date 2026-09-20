<?php
ob_start(); // <--- IMPORTANTE: Inicia búfer para evitar error de headers
// contabilizar.php
require_once 'config/header.php';
date_default_timezone_set('America/New_York'); 

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

//LIMPIAR VARIABLES DE ALERTA SI NO VENIMOS DE UN POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $mensajes_importantes = [];
    
    if (isset($_SESSION['swal_error']) && strpos($_SESSION['swal_error']['text'] ?? '', 'Consulte al administrador') !== false) {
        $mensajes_importantes['error'] = $_SESSION['swal_error'];
    }
    
    if (isset($_SESSION['swal_warning']) && strpos($_SESSION['swal_warning']['text'] ?? '', 'Consulte al administrador') !== false) {
        $mensajes_importantes['warning'] = $_SESSION['swal_warning'];
    }
    
    unset($_SESSION['swal_success'], $_SESSION['swal_error'], $_SESSION['swal_warning']);
    
    if (isset($mensajes_importantes['error'])) {
        $_SESSION['swal_error'] = $mensajes_importantes['error'];
    }
    
    if (isset($mensajes_importantes['warning'])) {
        $_SESSION['swal_warning'] = $mensajes_importantes['warning'];
    }
}

// Obtener configuración del tema
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

$id = $_GET['id'] ?? 0;
$accion = $_GET['accion'] ?? 'contabilizar'; // 'contabilizar' o 'descontabilizar'

// Validar acción
if (!in_array($accion, ['contabilizar', 'descontabilizar'])) {
    $_SESSION['swal_error'] = [
        'title' => 'Acción no válida',
        'text' => 'La acción especificada no es válida.',
        'icon' => 'error',
        'confirmButtonText' => '<i class="fas fa-arrow-left me-1"></i> Volver'
    ];
    header('Location: facturas.php');
    exit();
}

// Inicializar variables
$factura = [];
$detalles = [];
$errores = [];
// Inicializar variable de fecha operativa por defecto para evitar undefined
$hoy_operativo = date('Y-m-d'); 

try {
    $db = Database::getConnection();
    
    // Obtener usuario actual y verificar permisos
    $sql_usuario = "SELECT u.*, r.descripcion as rol_nombre
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt_usuario = $db->prepare($sql_usuario);
    $stmt_usuario->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    //Determinar permisos según rol
    $esAdmin = ($usuario['rol_id'] == 1);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);

    // -------------------------------------------------------------------------
    // VALIDACIÓN ESTRICTA DE ROLES Y MENSAJES DE ERROR
    // -------------------------------------------------------------------------

    // 1. Validar permiso general de acceso
    $puedeEntrar = ($esAdmin || $esEditor || $esSuper);
    if (!$puedeEntrar) {
        $_SESSION['swal_error'] = [
            'title' => 'Acceso Denegado',
            'text' => 'Su rol actual no tiene permisos para acceder a este módulo.',
            'icon' => 'error',
            'confirmButtonText' => '<i class="fas fa-lock me-1"></i> Entendido'
        ];
        //header('Location: ver_factura.php?id=' . $id);
		header('Location: facturas.php');
        exit();
    }

    // 2. Validar permiso específico para DESCONTABILIZAR
    // Regla: Los Editores (rol 3) NO pueden descontabilizar. Solo Admin (1) y Super (4).
    if ($accion == 'descontabilizar') {
        if (!$esAdmin && !$esSuper) {
            $_SESSION['swal_error'] = [
                'title' => 'Permiso Insuficiente',
                'text' => 'Lo sentimos, la acción de <b>DESCONTABILIZAR</b> es exclusiva de Administradores y Supervisores.<br><br>Su rol actual es: <b>' . htmlspecialchars($usuario['rol_nombre']) . '</b>',
                'icon' => 'error',
                'confirmButtonText' => '<i class="fas fa-user-shield me-1"></i> Entendido'
            ];
			//header('Location: ver_factura.php?id=' . $id);
			header('Location: facturas.php');
            exit();
        }
    }

    // 3. Validar permiso específico para CONTABILIZAR FACTURAS ANULADAS
    // Regla: Los Editores no pueden contabilizar facturas que estén ANULADAS.
    if ($accion == 'contabilizar' && isset($factura['estado']) && $factura['estado'] == 'ANULADA') {
        if (!$esAdmin && !$esSuper) {
            $_SESSION['swal_error'] = [
                'title' => 'Acción Restringida',
                'text' => 'Una factura <b>ANULADA</b> solo puede ser contabilizada por un Administrador o Supervisor.',
                'icon' => 'warning',
                'confirmButtonText' => '<i class="fas fa-exclamation-circle me-1"></i> Entendido'
            ];
			//header('Location: ver_factura.php?id=' . $id);
			header('Location: facturas.php');
        }
    }
    
    // -------------------------------------------------------------------------
    // FIN VALIDACIÓN DE ROLES
    // -------------------------------------------------------------------------

    // ==================== LÓGICA DE FECHAS OPERATIVAS (CORREGIDA) ====================
    // Implementación de la lógica solicitada para calcular $hoy_operativo
    
    // 1. Obtener configuración (Manejo robusto si las funciones no están cargadas)
    if (function_exists('obtenerMesCierreOperaciones') && function_exists('obtenerAnioCierreOperaciones')) {
        $mes_cierre_num = obtenerMesCierreOperaciones(); 
        $anio_cierre_num = obtenerAnioCierreOperaciones(); 
    } else {
        // Fallback: Consultar directamente si las funciones helper no existen
        try {
            $stmt_cfg = $db->query("SELECT mes_cierre, anio_cierre FROM tbl_configuracion LIMIT 1");
            $cfg = $stmt_cfg->fetch(PDO::FETCH_ASSOC);
            $mes_cierre_num = $cfg['mes_cierre'] ?? date('n');
            $anio_cierre_num = $cfg['anio_cierre'] ?? date('Y');
        } catch (Exception $e) {
            $mes_cierre_num = date('n');
            $anio_cierre_num = date('Y');
        }
    }

    // Obtener día actual real del servidor/PC
    $dia_actual_real = (int)date('j');

    // --- INICIO CORRECCIÓN: AJUSTE DE DÍAS ---
    // 1. Averiguar cuántos días tiene REALMENTE el mes operativo (ej: Abril=30, Feb Bisiesto=29)
    $dias_maximos_mes = cal_days_in_month(CAL_GREGORIAN, (int)$mes_cierre_num, (int)$anio_cierre_num);

    // 2. Comparar y elegir el menor. 
    // Si hoy es 31 y el mes tiene 30, elige 30. Si hoy es 15 y el mes tiene 30, elige 15.
    $dia_final = min($dia_actual_real, $dias_maximos_mes);
    // --- FIN CORRECCIÓN ---

    // Construir la fecha "Hoy Operativo" (Año BD + Mes BD + Día Validado)
    $hoy_operativo = sprintf("%04d-%02d-%02d", $anio_cierre_num, $mes_cierre_num, $dia_final);
    // ==================== FIN LÓGICA FECHAS ====================


    // Obtener datos de la factura
    $sql = "SELECT f.*, c.nombre as cliente_nombre, c.NIT as cliente_nit, 
                   t.descripcion as tipo_pago_desc
            FROM tbl_fact f 
            LEFT JOIN clasif_clientes c ON f.cliente_id = c.id 
            LEFT JOIN tipos_pago t ON f.tipo_pago_id = t.id
            WHERE f.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Factura no encontrada',
            'icon' => 'error',
            'confirmButtonText' => '<i class="fas fa-arrow-left me-1"></i> Volver'
        ];
        header('Location: facturas.php');
        exit();
    }
    
    // Verificar el estado actual de la factura
    $estado_actual = $factura['estado'];
    $esContabilizacion = ($accion == 'contabilizar');
    $esDescontabilizacion = ($accion == 'descontabilizar');
    
    // Validaciones específicas según la acción
    if ($esContabilizacion && $estado_actual == 'CONTABILIZADA') {
        $_SESSION['swal_warning'] = [
            'title' => 'Factura Ya Contabilizada',
            'text' => 'Esta factura ya está CONTABILIZADA.<br><span class="text-danger">NO ES NECESARIO VOLVER A CONTABILIZAR</span>',
            'icon' => 'warning',
            'confirmButtonText' => '<i class="fas fa-check me-1"></i> Entendido'
        ];
        header('Location: ver_factura.php?id=' . $id);
        exit();
    }
    
    if ($esDescontabilizacion && $estado_actual != 'CONTABILIZADA') {
        $_SESSION['swal_error'] = [
            'title' => 'Acción no permitida',
            'text' => 'Solo se pueden descontabilizar facturas que estén en estado CONTABILIZADA.',
            'icon' => 'error',
            'confirmButtonText' => '<i class="fas fa-check me-1"></i> Entendido'
        ];
        header('Location: ver_factura.php?id=' . $id);
        exit();
    }
    
    // Para editores (rol 3), solo permitir contabilizar facturas pendientes
    if ($esEditor && !$esAdmin) {
        if ($estado_actual == 'ANULADA' && $esContabilizacion) {
            $_SESSION['swal_error'] = [
                'title' => 'Factura Anulada',
                'text' => 'No se puede contabilizar una factura ANULADA. Solo administradores y supervisores pueden realizar esta acción.',
                'icon' => 'error',
                'confirmButtonText' => '<i class="fas fa-check me-1"></i> Entendido'
            ];
            header('Location: ver_factura.php?id=' . $id);
            exit();
        }
        
        if ($esDescontabilizacion) {
            $_SESSION['swal_error'] = [
                'title' => 'Acción no permitida',
                'text' => 'Solo administradores, editores y supervisores pueden descontabilizar facturas.',
                'icon' => 'error',
                'confirmButtonText' => '<i class="fas fa-check me-1"></i> Entendido'
            ];
            header('Location: ver_factura.php?id=' . $id);
            exit();
        }
    }
    
    // Obtener detalles de la factura
    $sql_detalles = "SELECT d.*, s.descripcion as servicio_desc, s.codigo as servicio_codigo
                     FROM tbl_fact_detalle d
                     LEFT JOIN clasif_serv s ON d.servicio_id = s.id
                     WHERE d.factura_id = ?
                     ORDER BY d.id";
    $stmt_detalles = $db->prepare($sql_detalles);
    $stmt_detalles->execute([$id]);
    $detalles = $stmt_detalles->fetchAll();
    
} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $_SESSION['swal_error'] = [
        'title' => 'Error',
        'text' => "Error al cargar los datos de la factura: " . $e->getMessage(),
        'icon' => 'error',
        'confirmButtonText' => '<i class="fas fa-arrow-left me-1"></i> Volver'
    ];
    header('Location: facturas.php');
    exit();
}

// Procesar contabilización/descontabilización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== INICIO PROCESO " . strtoupper($accion) . " ===");
    
    try {
        $db->beginTransaction();
        
        $no_factura = $factura['no_fact'];
        
        // --- LOGICA CORREGIDA PARA ACTUALIZAR ESTADO ---
        if ($accion == 'contabilizar') {
            // Caso 1: Contabilizar
            $nuevo_estado = 'CONTABILIZADA';
            $texto_accion = 'contabilizada';
            
            // UTILIZAR LÓGICA DE FECHA OPERATIVA SI NO SE ENVÍA O COMO BASE
            // Combinamos la fecha operativa con la hora actual si no viene del POST, 
            // aunque el formulario se rellena con $hoy_operativo por defecto.
            $fecha_contabilizacion = $_POST['fecha_contabilizacion'] ?? ($hoy_operativo . ' ' . date('H:i:s'));
            
            $sql_update = "UPDATE tbl_fact 
                           SET estado = ?, 
                               fecha_contabilizacion = ?, 
                               usuario_contabilizacion = ? 
                           WHERE id = ?";
            
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->execute([
                $nuevo_estado, 
                $fecha_contabilizacion, 
                $_SESSION['usuario_id'], 
                $id
            ]);
            
        } else {
            // Caso 2: Descontabilizar (Forzar limpieza de fecha)
            $nuevo_estado = 'PENDIENTE';
            $texto_accion = 'descontabilizada';
            
            $sql_update = "UPDATE tbl_fact 
                           SET estado = 'PENDIENTE', 
                               fecha_contabilizacion = NULL, 
                               usuario_contabilizacion = NULL 
                           WHERE id = ?";
                           
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->execute([$id]);
        }
        
        // Verificar errores en la consulta
        if ($stmt_update->rowCount() === 0 && $stmt_update->errorCode() !== '00000') {
            throw new Exception("Error al actualizar la factura en base de datos.");
        }
        
        
        // --- REGISTRAR ACTIVIDAD ---
        try {
            $tipo_operacion = ($accion == 'contabilizar') ? 'CONTABILIZACION_FACTURA' : 'DESCONTABILIZACION_FACTURA';
            
            $sql_log = "INSERT INTO historico_operaciones 
                       (operacion, descripcion, usuario_id, usuario_nombre, fecha_operacion, ip_address) 
                       VALUES (?, ?, ?, ?, NOW(), ?)";
            $stmt_log = $db->prepare($sql_log);
            $stmt_log->execute([
                $tipo_operacion,
                'Factura ' . $no_factura . ' ' . $texto_accion . ' - Total: $' . number_format($factura['total_general'], 2),
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nombre'] ?? 'Usuario',
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            error_log("Error log actividad: " . $e->getMessage());
        }
        
        $db->commit();
        
        $_SESSION['swal_success'] = [
            'title' => '¡Éxito!',
            'text' => 'Factura ' . $no_factura . ' ' . $texto_accion . ' correctamente',
            'icon' => 'success',
            'confirmButtonText' => '<i class="fas fa-arrow-right me-1"></i> Continuar'
        ];
        
        //header('Location: ver_factura.php?id=' . $id);
		header('Location: facturas.php');
        exit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        $errores[] = 'Error: ' . $e->getMessage();
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Error al procesar la factura: ' . $e->getMessage(),
            'icon' => 'error',
            'confirmButtonText' => '<i class="fas fa-arrow-left me-1"></i> Volver'
        ];
    }
}

// Obtener estadísticas para los badges del sidebar
try {
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->query($sql_facturas_total);
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->query($sql_clientes_total);
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->query($sql_categorias_total);
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->query($sql_servicios_total);
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->query($sql_usuarios_total);
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $estadisticas = [];
    $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
    $stmt = $db->query($sql_total);
    $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $total_facturas = 0; $total_clientes = 0; $total_categorias = 0; 
    $total_servicios = 0; $total_usuarios = 0; $estadisticas['total'] = 0;
}

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($accion == 'contabilizar') ? 'Contabilizar' : 'Descontabilizar'; ?> Factura - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">

<!-- MDTimePicker para el reloj analógico -->
<link rel="stylesheet" href="css/mdtimepicker.css">
<script src="js/mdtimepicker.min.js"></script>

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
            border-color: var(--win-border_color);
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

        /* Tooltips */
        .tooltip {
            --bs-tooltip-bg: var(--win-bg-tertiary);
            --bs-tooltip-color: var(--win-text-primary);
			z-index: 10000 !important; /* Asegura que flote sobre todo */
			opacity: 1 !important;
        }
.tooltip-inner {
    background-color: var(--win-accent) !important; /* Fondo del color de acento */
    color: #ffffff !important; /* Texto blanco siempre */
    box-shadow: 0 4px 8px rgba(0,0,0,0.2); /* Sombra para resaltar */
    font-size: 0.85rem;
}

/* La flechita del tooltip */
.tooltip-arrow::before {
    border-top-color: var(--win-accent) !important;
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
        
        /* ==================== ESTILOS PARA PAGINACIÓN ==================== */
        .pagination .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
            transition: var(--win-transition);
        }
        
        .pagination .page-link:hover {
            background-color: var(--win-accent-light);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--win-accent);
            border-color: var(--win-accent);
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-secondary);
            opacity: 0.5;
        }
        
        .pagination-container {
            margin-top: 20px;
        }
        
        .pagination-info {
            font-size: 0.9em;
            color: var(--win-text-secondary);
            margin-bottom: 10px;
        }
        
        .registros-selector {
            width: 80px !important;
            display: inline-block;
            margin-left: 10px;
        }
        /* ==================== FIN ESTILOS PAGINACIÓN ==================== */

        /* ==================== ESTILOS PARA ESTADÍSTICAS DE FACTURAS (COMPACTAS) ==================== */
        .estadisticas-compactas-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .estadistica-compacta-card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius-sm);
            padding: 12px;
            transition: var(--win-transition);
            position: relative;
            overflow: hidden;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .estadistica-compacta-card:hover {
            border-color: var(--win-accent);
            transform: translateY(-2px);
            box-shadow: var(--win-shadow);
        }

        .estadistica-compacta-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--win-accent);
            border-radius: var(--win-radius-sm) 0 0 var(--win-radius-sm);
        }

        .estadistica-compacta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .estadistica-compacta-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--win-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .estadistica-compacta-icon {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            background: var(--win-accent-light);
            color: var(--win-accent);
            flex-shrink: 0;
        }

        .estadistica-compacta-values {
            text-align: left;
        }

        .estadistica-compacta-cantidad {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--win-text-primary);
            line-height: 1;
            margin-bottom: 4px;
        }

        .estadistica-compacta-importe {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--win-accent);
        }

        .estadistica-compacta-subtitle {
            font-size: 0.7rem;
            color: var(--win-text-secondary);
            margin-top: 4px;
            opacity: 0.8;
        }

        /* Colores específicos para cada tipo de estadística (más sutiles) */
        .estadistica-hoy::before {
            background: #28a745;
        }

        .estadistica-hoy .estadistica-compacta-icon {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .estadistica-hoy .estadistica-compacta-importe {
            color: #28a745;
        }

        .estadistica-mes::before {
            background: #007bff;
        }

        .estadistica-mes .estadistica-compacta-icon {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }

        .estadistica-mes .estadistica-compacta-importe {
            color: #007bff;
        }

        .estadistica-anio::before {
            background: #6f42c1;
        }

        .estadistica-anio .estadistica-compacta-icon {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .estadistica-anio .estadistica-compacta-importe {
            color: #6f42c1;
        }

        .estadistica-total::before {
            background: #fd7e14;
        }

        .estadistica-total .estadistica-compacta-icon {
            background: rgba(253, 126, 20, 0.1);
            color: #fd7e14;
        }

        .estadistica-total .estadistica-compacta-importe {
            color: #fd7e14;
        }

        .estadistica-contabilizadas::before {
            background: #20c997;
        }

        .estadistica-contabilizadas .estadistica-compacta-icon {
            background: rgba(32, 201, 151, 0.1);
            color: #20c997;
        }

        .estadistica-contabilizadas .estadistica-compacta-importe {
            color: #20c997;
        }

        /* NUEVO ESTILO PARA PAGADAS MES */
        .estadistica-pagadas-mes::before {
            background: #0dcaf0; /* Cyan */
        }

        .estadistica-pagadas-mes .estadistica-compacta-icon {
            background: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }

        .estadistica-pagadas-mes .estadistica-compacta-importe {
            color: #0dcaf0;
        }

        /* Estadísticas por estado (compactas) */
        .estadisticas-estado-compactas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .estadistica-estado-compacta {
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius-sm);
            padding: 10px;
            text-align: center;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .estado-badge-mini {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.7rem;
            margin-bottom: 6px;
        }

        .estado-cantidad-mini {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .estado-importe-mini {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--win-accent);
        }

        /* Responsive para estadísticas compactas */
        @media (max-width: 1200px) {
            .estadisticas-compactas-container {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .estadisticas-compactas-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .estadistica-compacta-cantidad {
                font-size: 1.2rem;
            }
            
            .estadistica-compacta-importe {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .estadisticas-compactas-container {
                grid-template-columns: 1fr;
            }
            
            .estadisticas-estado-compactas {
                grid-template-columns: 1fr;
            }
        }
        /* ==================== FIN ESTILOS PARA ESTADÍSTICAS DE FACTURAS (COMPACTAS) ==================== */

        /* Estilos para el dropdown de usuario */
        .dropdown-menu {
            background-color: var(--win-bg-secondary);
            border: 1px solid var(--win-border_color);
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
		
/* Botón de colapsar estilo Windows 11 */
.btn-card-toggle {
    color: var(--win-text-primary) !important; /* Color más brillante */
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px; /* Bordes redondeados Win11 */
    transition: all 0.2s ease-in-out;
    background-color: var(--win-bg-tertiary); /* Fondo sutil para que se vea el botón */
    border: 1px solid var(--win-border-color);
}

.btn-card-toggle:hover {
    background-color: var(--win-accent-light); /* Fondo del color de acento al pasar mouse */
    color: var(--win-accent) !important;
    border-color: var(--win-accent);
}

/* Animación y grosor del icono */
.btn-card-toggle .toggle-icon {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.85rem;
    -webkit-text-stroke: 0.5px; /* Hace el icono un poquito más grueso y visible */
}

/* Rotación: Cuando está colapsado (cerrado), apunta hacia abajo */
.btn-card-toggle.collapsed .toggle-icon {
    transform: rotate(180deg);
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
            <span style="color: var(--win-text-primary);">FACTURAS - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
			<i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Acciones del navbar -->
		
        <a href="editar_factura.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>Ir a Editar: <strong> <?php echo $factura['no_fact']; ?></strong>
        </a>
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
                        <?php if (!empty($usuario['foto'])): ?><img src="<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)); endif; ?>
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
						  data-bs-toggle="tooltip"
						  title="Fecha de Cierre Actual: <?php echo fechaInicioFormateada(13); ?>" 
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
                    <span class="win-nav-badge"><?php echo $total_facturas; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="facturas.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-calculator me-2"></i>
                    <span class="win-nav-text"> <?php echo strtoupper($accion); ?>: <strong> <?php echo $factura['no_fact']; ?></strong></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="clientes.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Clientes</span>
                    <span class="win-nav-badge"><?php echo $total_clientes; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="categorias.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo $total_categorias; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo $total_servicios; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-users"></i>
                    <span class="win-nav-text">Usuarios</span>
                    <span class="win-nav-badge"><?php echo $total_usuarios; ?></span>
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
                    <!-- Cantidad de Facturas -->
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


    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <!-- Encabezado de página -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0" style="color: var(--win-text-primary);">
                    <i class="fas <?php echo ($accion == 'contabilizar') ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2" 
                       style="color: <?php echo ($accion == 'contabilizar') ? '#28a745' : '#ffc107'; ?>;"></i>
                    <?php echo ($accion == 'contabilizar') ? 'Contabilizar' : 'Descontabilizar'; ?> Factura
                </h1>
                <div>
                    <?php
                    $badge_class = '';
                    switch ($factura['estado']) {
                        case 'CONTABILIZADA':
                            $badge_class = 'estado-contabilizada';
                            break;
                        case 'PENDIENTE':
                            $badge_class = 'estado-pendiente';
                            break;
                        case 'ANULADA':
                            $badge_class = 'estado-anulada';
                            break;
                    }
                    ?>
                    <span class="badge badge-estado <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars($factura['estado']); ?>
                    </span>
                    <?php if ($esAdmin || $esEditor): ?>
                        <span class="badge bg-info ms-2">
                            <?php echo $esAdmin ? 'Administrador' : 'Editor'; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-0">Número: <strong><?php echo htmlspecialchars($factura['no_fact']); ?></strong></p>
                    <p class="text-muted mb-0">Cliente: <strong><?php echo htmlspecialchars($factura['cliente_nombre']); ?></strong></p>
                    <p class="text-muted mb-0">Fecha: <strong><?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?></strong></p>
                </div>
                <div>
                    <a href="ver_factura.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-eye me-1"></i>Ver Factura
                    </a>
                    <a href="facturas.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-1"></i>Lista de Facturas
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes de error -->
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger mb-4">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados:</h5>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Mostrar mensaje de error de sesión si existe -->
        <?php if (isset($_SESSION['swal_error'])): ?>
            <div class="alert alert-danger mb-4">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['swal_error']['title']; ?></h5>
                <p class="mb-0"><?php echo $_SESSION['swal_error']['text']; ?></p>
                <?php unset($_SESSION['swal_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Resumen de la factura -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="text-light" class="mb-0"><i class="fas fa-info-circle me-2"></i>Detalles de la Factura</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($factura['cliente_nombre']); ?></p>
                                <p><strong>NIT:</strong> <?php echo htmlspecialchars($factura['cliente_nit'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Forma de Pago:</strong> <?php echo htmlspecialchars($factura['tipo_pago_desc']); ?></p>
                                <p><strong>Observaciones:</strong> <?php echo htmlspecialchars($factura['observaciones'] ?? 'Ninguna'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Detalles de servicios -->
                        <h6 class="mt-4 mb-3"><i class="fas fa-list-alt me-2"></i>Servicios</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">P. Unitario</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (is_array($detalles) && count($detalles) > 0): ?>
                                        <?php foreach ($detalles as $detalle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($detalle['servicio_codigo'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($detalle['servicio_desc'] ?? 'N/A'); ?></td>
                                                <td class="text-end"><?php echo number_format($detalle['cantidad'], 0); ?></td>
                                                <td class="text-end">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                                <td class="text-end">$<?php echo number_format($detalle['total_linea'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No hay servicios registrados
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <!-- Totales -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="text-light" class="mb-0"><i class="fas fa-calculator me-2"></i>Totales</h5>
                    </div>
                    <div class="card-body">
                        <div class="total-box">
                            <div class="row mb-3">
                                <div class="col">
                                    <span class="text-muted">Subtotal:</span>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-bold">$<?php echo number_format($factura['subtotal'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col">
                                    <span class="h5">TOTAL GENERAL:</span>
                                </div>
                                <div class="col-auto">
                                    <span class="h4 fw-bold" style="color: #28a745;">$<?php echo number_format($factura['total_general'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulario de contabilización/descontabilización -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="text-light" class="mb-0">
                            <i class="fas <?php echo ($accion == 'contabilizar') ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                            <?php echo ($accion == 'contabilizar') ? 'Confirmar Contabilización' : 'Confirmar Descontabilización'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formContabilizar">
                            <?php if ($accion == 'contabilizar'): ?>
                            <div class="mb-3">
                                <label for="fecha_contabilizacion" class="form-label">Fecha de Contabilización</label>
                                <!-- UTILIZAMOS LA FECHA OPERATIVA CALCULADA EN EL VALUE -->
                                <input type="datetime-local" 
                                       class="form-control" 
                                       id="fecha_contabilizacion" 
                                       name="fecha_contabilizacion"
                                       value="<?php echo $hoy_operativo . 'T' . date('H:i'); ?>"
                                       required>
                                <small class="text-muted">Fecha operativa calculada según cierre: <?php echo $hoy_operativo; ?></small>
                            </div>
                            <?php else: ?>
                            <!-- No enviar fecha para descontabilización -->
                            <?php endif; ?>
                            
                            <div class="alert <?php echo ($accion == 'contabilizar') ? 'alert-warning' : 'alert-danger'; ?>">
                                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Advertencia</h6>
                                <p class="mb-0">
                                    <?php if ($accion == 'contabilizar'): ?>
                                        Una vez contabilizada, la factura cambiará su estado a <span class="badge bg-success">CONTABILIZADA</span>.
                                        <?php if ($esEditor && !$esAdmin): ?>
                                            <br><strong>Nota:</strong> Como editor, solo puedes contabilizar facturas pendientes, no facturas anuladas.
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Una vez descontabilizada, la factura cambiará su estado a <span class="badge bg-warning">PENDIENTE</span>.
                                        <br><strong>Nota:</strong> Solo administradores pueden descontabilizar facturas.
                                        <br><br>Se eliminará la fecha de contabilización y los asientos contables asociados.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn <?php echo ($accion == 'contabilizar') ? 'btn-success' : 'btn-warning'; ?> btn-lg">
                                    <i class="fas <?php echo ($accion == 'contabilizar') ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                                    <?php echo ($accion == 'contabilizar') ? 'Confirmar Contabilización' : 'Confirmar Descontabilización'; ?>
                                </button>
                                <a href="facturas.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validación del formulario antes de enviar
            document.getElementById('formContabilizar').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const accion = "<?php echo $accion; ?>";
                const textoAccion = (accion === 'contabilizar') ? 'contabilizar' : 'descontabilizar';
                const textoAccionMayus = textoAccion.charAt(0).toUpperCase() + textoAccion.slice(1);
                const estadoNuevo = (accion === 'contabilizar') ? 'CONTABILIZADA' : 'PENDIENTE';
                const colorConfirmar = (accion === 'contabilizar') ? '#28a745' : '#ffc107';
                const icono = (accion === 'contabilizar') ? 'warning' : 'warning';
                
                let mensajeAdicional = '';
                if (accion === 'descontabilizar') {
                    mensajeAdicional = '<br><br><span class="text-danger"><i class="fas fa-exclamation-circle"></i> Se eliminará la fecha de contabilización y los asientos contables.</span>';
                }
                
                Swal.fire({
                    title: '¿Confirmar ' + textoAccionMayus + '?',
                    html: `¿Está seguro de ${textoAccion} la factura <strong><?php echo htmlspecialchars($factura['no_fact']); ?></strong>?<br><br>
                           <strong>Cliente:</strong> <?php echo htmlspecialchars($factura['cliente_nombre']); ?><br>
                           <strong>Total:</strong> $<?php echo number_format($factura['total_general'], 2); ?><br>
                           <strong>Nuevo estado:</strong> <span class="badge ${(accion === 'contabilizar') ? 'bg-success' : 'bg-warning'}">${estadoNuevo}</span>
                           ${mensajeAdicional}`,
                    icon: icono,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, ' + textoAccion,
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                    confirmButtonColor: colorConfirmar,
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar carga
                        Swal.fire({
                            title: textoAccionMayus + ' factura...',
                            html: 'Procesando la Acción de: ' + textoAccion + ' factura <?php echo htmlspecialchars($factura['no_fact']); ?>',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Enviar el formulario
                        this.submit();
                    }
                });
            });
            
            // Mostrar SweetAlert si hay mensajes en sesión
            <?php if (isset($_SESSION['swal_success'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_success']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_success']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_success']['icon']; ?>',
                    confirmButtonText: '<i class="fas fa-arrow-right me-1"></i> Continuar',
                    confirmButtonColor: 'var(--win-accent)'
                });
                <?php unset($_SESSION['swal_success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['swal_error'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_error']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_error']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_error']['icon']; ?>',
                    confirmButtonText: '<i class="fas fa-arrow-left me-1"></i> Volver',
                    confirmButtonColor: 'var(--win-accent)'
                });
                <?php unset($_SESSION['swal_error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['swal_warning'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_warning']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_warning']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_warning']['icon']; ?>',
                    confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                    confirmButtonColor: 'var(--win-accent)'
                });
                <?php unset($_SESSION['swal_warning']); ?>
            <?php endif; ?>
			
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
            });

        });
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        
        
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

    </script>
	<!-- Chatbot JS -->
<script src="js/chatbot.js"></script>
<script src="css/chatbot.css"></script>
	<?php
		if (file_exists('config/footer.php')) {
			include 'config/footer.php';
		}
	?>
</body>
</html>
<?php
ob_end_flush();
?>