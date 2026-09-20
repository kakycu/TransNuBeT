<?php
// ver_cliente.php - Windows 11 Dark Mode - Full Version
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Inicializar variables
$error = '';
$success = '';

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

// ==================== VALIDACIÓN INICIAL CON SWEETALERT ====================
// Obtener ID del cliente
$cliente_id = isset($_GET['id']) ? $_GET['id'] : '';

// Función para mostrar SweetAlert y redirigir
function mostrarErrorYRedirigir($mensaje, $destino = 'clientes.php') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo...</title>
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
        <style>
            body { 
                background: #1f1f1f; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0; 
                font-family: Arial, sans-serif; 
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            title: "Error",
            text: ' . json_encode($mensaje) . ',
            icon: "error",
            background: "#1f1f1f",
            color: "#fff",
            confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
            confirmButtonColor: "#3085d6",
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            window.location.href = ' . json_encode($destino) . ';
        });
        </script>
    </body>
    </html>';
    exit();
}

// Verificar si el ID está presente y no está vacío
if (empty($cliente_id)) {
    mostrarErrorYRedirigir('No se ha especificado un ID de cliente válido.');
}

// Validar que el ID sea numérico
if (!is_numeric($cliente_id)) {
    mostrarErrorYRedirigir('El ID del cliente debe ser un número válido.');
}

// Convertir a entero
$cliente_id = (int)$cliente_id;
// ==================== FIN VALIDACIÓN INICIAL ====================
try {
    $db = Database::getConnection();
    
    // Obtener usuario actual
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
    
    // Determinar permisos según rol
    $esAdmin = ($usuario['rol_id'] == 1);
    $esEditor = ($usuario['rol_id'] == 3);
    $esSuper = ($usuario['rol_id'] == 4);
    $esSoloLectura = ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4);
    
    // Obtener datos del cliente con campos calculados de la base de datos
    $sql_cliente = "SELECT *, DATE_ADD(fechaRegistro, INTERVAL vigenciapor YEAR) as fechaVence,
                    DATE_ADD(fechaRegistro, INTERVAL (vigenciapor + IF(renovac = 1, si_renova_cant, 0)) YEAR) as fechafinalcontrato
                    FROM clasif_clientes WHERE id = :id";
    $stmt_cliente = $db->prepare($sql_cliente);
    $stmt_cliente->execute(['id' => $cliente_id]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    
if (!$cliente) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo...</title>
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
        <style>
            body { 
                background: #1f1f1f; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0; 
                font-family: Arial, sans-serif; 
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            title: "Error",
            text: "El cliente con ID ' . $cliente_id . ' no existe en la base de datos.",
            icon: "error",
            background: "#1f1f1f",
            color: "#fff",
            confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
            confirmButtonColor: "#3085d6",
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            window.location.href = "clientes.php";
        });
        </script>
    </body>
    </html>';
    exit();
}
    
    
// Calcular estado actual usando los campos calculados de la BD
$hoy = new DateTime();
$fecha_final_total = new DateTime($cliente['fechafinalcontrato']);

// Calcular días de diferencia
$diferencia = $hoy->diff($fecha_final_total);
$dias_diferencia = $diferencia->days;
$anios_diferencia = $diferencia->y;
$meses_diferencia = $diferencia->m;
$dias_restantes = $diferencia->d;

// Crear texto para la diferencia de tiempo
if ($fecha_final_total < $hoy) {
    // Ya venció
    $es_vencido = true;
    
    // Crear texto con años, meses y días vencidos
    $texto_tiempo = "";
    if ($anios_diferencia > 0) {
        $texto_tiempo .= $anios_diferencia . " año" . ($anios_diferencia != 1 ? 's' : '');
    }
    if ($meses_diferencia > 0) {
        if ($texto_tiempo != "") $texto_tiempo .= ", ";
        $texto_tiempo .= $meses_diferencia . " mes" . ($meses_diferencia != 1 ? 'es' : '');
    }
    if ($dias_restantes > 0 || ($anios_diferencia == 0 && $meses_diferencia == 0)) {
        if ($texto_tiempo != "") $texto_tiempo .= " y ";
        $texto_tiempo .= $dias_restantes . " día" . ($dias_restantes != 1 ? 's' : '');
    }
    
    $dias_texto = "Vencido hace $texto_tiempo";
} else {
    // Aún no vence
    $es_vencido = false;
    
    // Crear texto con años, meses y días por vencer
    $texto_tiempo = "";
    if ($anios_diferencia > 0) {
        $texto_tiempo .= $anios_diferencia . " año" . ($anios_diferencia != 1 ? 's' : '');
    }
    if ($meses_diferencia > 0) {
        if ($texto_tiempo != "") $texto_tiempo .= ", ";
        $texto_tiempo .= $meses_diferencia . " mes" . ($meses_diferencia != 1 ? 'es' : '');
    }
    if ($dias_restantes > 0 || ($anios_diferencia == 0 && $meses_diferencia == 0)) {
        if ($texto_tiempo != "") $texto_tiempo .= " y ";
        $texto_tiempo .= $dias_restantes . " día" . ($dias_restantes != 1 ? 's' : '');
    }
    
    $dias_texto = "Vence en $texto_tiempo";
    
    // Verificar si faltan 7 días o menos (solo días, sin contar meses o años)
    $alerta_proximo_vencimiento = ($dias_diferencia <= 7);
}

if ($fecha_final_total < $hoy) {
    $estado_cliente = 'VENCIDO';
    $estado_color = 'estado-vencido';
} elseif ($cliente['activo'] == 1) {
    $estado_cliente = 'ACTIVO';
    $estado_color = 'estado-activo';
} else {
    $estado_cliente = 'INACTIVO';
    $estado_color = 'estado-inactivo';
}

// Variable adicional para mostrar el valor de la BD
$estado_bd = $activo_bd == 1 ? 'ACTIVO (1)' : 'INACTIVO (0)';
    
    // Obtener estadísticas para los badges del sidebar
    try {
        $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
        $stmt = $db->prepare($sql_facturas_total);
        $stmt->execute();
        $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar facturas: " . $e->getMessage());
        $total_facturas = 0;
    }
    
    try {
        $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
        $stmt = $db->prepare($sql_clientes_total);
        $stmt->execute();
        $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar clientes: " . $e->getMessage());
        $total_clientes = 0;
    }
    
    try {
        $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
        $stmt = $db->prepare($sql_categorias_total);
        $stmt->execute();
        $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar categorías: " . $e->getMessage());
        $total_categorias = 0;
    }
    
    try {
        $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
        $stmt = $db->prepare($sql_servicios_total);
        $stmt->execute();
        $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar servicios: " . $e->getMessage());
        $total_servicios = 0;
    }
    
    try {
        $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
        $stmt = $db->prepare($sql_usuarios_total);
        $stmt->execute();
        $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar usuarios: " . $e->getMessage());
        $total_usuarios = 0;
    }
    
    try {
        $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total_historico);
        $estadisticas_historico = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_historico = $estadisticas_historico['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $total_historico = 0;
    }
    
    // Obtener facturas del cliente con información de pago
    $sql_facturas = "SELECT f.*, 
                    tp.descripcion as tipo_pago,
                    u.nombre as usuario_nombre
                    FROM tbl_fact f
                    LEFT JOIN tipos_pago tp ON f.tipo_pago_id = tp.id
                    LEFT JOIN clasif_usuarios u ON f.usuario_id = u.id
                    WHERE f.cliente_id = :id_cliente 
                    ORDER BY f.fecha_emision DESC LIMIT 10";
    $stmt_facturas = $db->prepare($sql_facturas);
    $stmt_facturas->execute(['id_cliente' => $cliente_id]);
    $facturas = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total facturado
    $sql_total_facturado = "SELECT SUM(total_general) as total FROM tbl_fact WHERE cliente_id = :id_cliente";
    $stmt_total = $db->prepare($sql_total_facturado);
    $stmt_total->execute(['id_cliente' => $cliente_id]);
    $total_facturado = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Obtener servicios para mostrar en las facturas
    if (count($facturas) > 0) {
        $factura_ids = array_column($facturas, 'id');
        $placeholders = implode(',', array_fill(0, count($factura_ids), '?'));
        
        $sql_servicios_facturas = "SELECT fd.factura_id, GROUP_CONCAT(cs.descripcion SEPARATOR ', ') as servicios
                                  FROM tbl_fact_detalle fd
                                  INNER JOIN clasif_serv cs ON fd.servicio_id = cs.id
                                  WHERE fd.factura_id IN ($placeholders)
                                  GROUP BY fd.factura_id";
        $stmt_servicios = $db->prepare($sql_servicios_facturas);
        $stmt_servicios->execute($factura_ids);
        $servicios_por_factura = $stmt_servicios->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Agregar servicios a cada factura
        foreach ($facturas as &$factura) {
            $factura['servicios'] = $servicios_por_factura[$factura['id']] ?? 'N/A';
        }
        unset($factura); // Romper la referencia
    }
    
} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Obtener mes actual en español
$meses_completos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_es = $meses_completos[date('n') - 1];
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Cliente - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">

    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">

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

        /* Efecto Mica */
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

        /* Navbar */
        .win-navbar {
            height: 48px;
            background: var(--win-bg-secondary);
            border-bottom: 1px solid var(--win-border-color);
            padding: 0 16px;
            position: fixed;
            top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; align-items: center; gap: 12px;
        }

        .win-navbar-brand { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .win-navbar-brand i { color: var(--win-accent); }

        .win-nav-search { flex: 1; max-width: 400px; position: relative; margin-bottom: 5px; padding: 0 5px; }
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
        .win-nav-search input:focus { outline: none; border-color: var(--win-accent); box-shadow: 0 0 0 2px var(--win-accent-light); }
        .win-nav-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--win-text-secondary); font-size: 14px; }

        /* Sidebar */
        .win-sidebar {
            width: 260px;
            background: var(--win-bg-secondary);
            border-right: 1px solid var(--win-border-color);
            height: calc(100vh - 48px);
            position: fixed; left: 0; top: 48px; z-index: 999;
            transition: var(--win-transition);
            overflow-y: auto; padding: 16px 0;
        }
        .win-sidebar.mini { width: 68px; }
        .win-sidebar-header { padding: 0 16px 16px; border-bottom: 1px solid var(--win-border-color); margin-bottom: 16px; }
        .win-sidebar-user { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-sidebar-user:hover { background: var(--win-bg-tertiary); }
        .win-sidebar-user-avatar { width: 36px; height: 36px; background: var(--win-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .win-sidebar-user-info h6 { margin: 0; font-size: 14px; font-weight: 500; }
        .win-sidebar-user-info small { color: var(--win-text-secondary); font-size: 12px; }
        .win-sidebar.mini .win-sidebar-user-info { display: none; }

        .win-nav { list-style: none; padding: 0; margin: 0; }
        .win-nav-item { margin: 2px 8px; }
        .win-nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--win-text-secondary); text-decoration: none; border-radius: var(--win-radius-sm); transition: var(--win-transition); font-size: 14px; position: relative; }
        .win-nav-link:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }
        .win-nav-link.active { background: var(--win-accent-light); color: var(--win-accent); font-weight: 500; }
        .win-nav-link.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--win-accent); border-radius: 0 2px 2px 0; }
        .win-nav-icon { width: 20px; text-align: center; font-size: 16px; }
        .win-sidebar.mini .win-nav-text { display: none; }
        .win-nav-badge { margin-left: auto; background: var(--win-accent); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; min-width: 20px; text-align: center; }

        /* Main Content */
        .win-main-content { margin-left: 260px; margin-top: 48px; padding: 24px; transition: var(--win-transition); min-height: calc(100vh - 48px); }
        .win-main-content.sidebar-mini { margin-left: 68px; }

        /* Cards & Forms */
        .win-main-content .card { background: var(--win-bg-secondary); border: 1px solid var(--win-border-color); border-radius: var(--win-radius); transition: var(--win-transition); margin-bottom: 1.5rem; }
        .win-main-content .card:hover { border-color: var(--win-accent); box-shadow: var(--win-shadow); }
        .win-main-content .card-header { background: var(--win-bg-tertiary); border-bottom: 1px solid var(--win-border_color); padding: 1rem 1.25rem; }
        .win-main-content .card-body { padding: 1.25rem; color: var(--win-text-primary); }
        .win-main-content .form-control, .win-main-content .form-select { background-color: var(--win-bg-tertiary); border: 1px solid var(--win-border_color); color: var(--win-text-primary); border-radius: var(--win-radius-sm); transition: var(--win-transition); }
        .win-main-content .form-control:focus { background-color: var(--win-bg-tertiary); border-color: var(--win-accent); color: var(--win-text-primary); box-shadow: 0 0 0 0.25rem var(--win-accent-light); }
        .win-main-content .form-control[readonly] { background-color: rgba(0,0,0,0.2); border-color: var(--win-border_color); opacity: 0.8; cursor: not-allowed; font-weight: bold; }
        [data-theme="light"] .win-main-content .form-control[readonly] { background-color: #e9ecef; }
        .win-main-content .form-label { color: var(--win-text-primary); font-weight: 500; margin-bottom: 0.5rem; }

        /* Buttons */
        .win-main-content .btn { border-radius: var(--win-radius-sm); transition: var(--win-transition); font-weight: 500; padding: 0.5rem 1rem; }
        .win-main-content .btn-primary { background: var(--win-accent); border-color: var(--win-accent); }
        .win-main-content .btn-primary:hover { background: color-mix(in srgb, var(--win-accent) 90%, black); transform: translateY(-1px); }
        .win-main-content .btn-outline-secondary { color: var(--win-text-secondary); border-color: var(--win-border_color); }
        .win-main-content .btn-outline-secondary:hover { background: var(--win-bg-tertiary); color: var(--win-text-primary); }

        /* Theme Panel */
        .win-theme-panel { position: fixed; top: 48px; right: 0; width: 300px; background: var(--win-bg-secondary); border-left: 1px solid var(--win-border_color); height: calc(100vh - 48px); z-index: 1001; transform: translateX(100%); transition: var(--win-transition); padding: 24px; overflow-y: auto; }
        .win-theme-panel.open { transform: translateX(0); }
        .win-theme-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: none; }
        .win-theme-overlay.open { display: block; }
        .win-theme-option { padding: 16px; border: 2px solid var(--win-border_color); border-radius: var(--win-radius); cursor: pointer; transition: var(--win-transition); text-align: center; color: var(--win-text-primary); }
        .win-theme-option:hover { border-color: var(--win-accent); }
        .win-theme-option.active { border-color: var(--win-accent); background: var(--win-accent-light); }
        .win-color-option { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: var(--win-transition); }
        .win-color-option.active { border-color: white; box-shadow: 0 0 0 2px var(--win-bg-secondary); }

        /* Validations & Helpers */
        .required::after { content: " *"; color: #dc3545; }
        .is-valid { border-color: #198754 !important; }
        .is-invalid { border-color: #dc3545 !important; }
        .invalid-feedback { display: none; color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .was-validated .form-control:invalid ~ .invalid-feedback { display: block; }
        .field-help { font-size: 0.75rem; color: var(--win-text-secondary); margin-top: 0.25rem; display: block; }
        
        /* Dropdown & Modal */
        .dropdown-menu { background-color: var(--win-bg-secondary); border: 1px solid var(--win-border_color); border-radius: var(--win-radius); }
        .dropdown-item { color: var(--win-text-primary); transition: var(--win-transition); border-radius: var(--win-radius-sm); margin: 2px 4px; }
        .dropdown-item:hover { background-color: var(--win-accent-light); color: var(--win-accent); }
        
        /* Resumen del Cliente */
        .resumen-cliente {
            background: linear-gradient(135deg, var(--win-bg-secondary), var(--win-bg-tertiary));
            border: 1px solid var(--win-border_color);
            border-radius: var(--win-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--win-shadow);
        }
        
        .resumen-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--win-text-primary);
            border-bottom: 2px solid var(--win-accent);
            padding-bottom: 0.5rem;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .resumen-item {
            display: flex;
            flex-direction: column;
        }
        
        .resumen-label {
            font-size: 0.875rem;
            color: var(--win-text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .resumen-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--win-text-primary);
        }
        
        .estado-activo {
            color: #28a745 !important;
        }
        
        .estado-inactivo {
            color: #dc3545 !important;
        }
        
        .estado-vencido {
            color: #ffc107 !important;
        }
        
        /* Contadores */
        .char-counter {
            font-size: 0.75rem;
            text-align: right;
            margin-top: 0.25rem;
            color: var(--win-text-secondary);
        }
        
        .char-counter.warning {
            color: #ffc107;
        }
        
        .char-counter.danger {
            color: #dc3545;
        }
        
        /* Quick Action Button */
        .win-quick-action {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--win-accent);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: var(--win-transition);
            z-index: 99;
        }
        
        .win-quick-action:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }
        
        .win-quick-action:active {
            transform: scale(0.95);
        }
        
        /* Alerta de estado */
        .alerta-estado {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            animation: pulseRed 2s infinite;
            font-weight: bold;
        }
        
        .alerta-estado.activo {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #28a745;
            animation: pulseGreen 2s infinite;
        }
        
        .alerta-estado.vencido {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.5);
            color: #ffc107;
            animation: pulseYellow 2s infinite;
        }
        
        @keyframes pulseRed { 
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.2); } 
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } 
        }
        
        @keyframes pulseGreen { 
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.2); } 
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); } 
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); } 
        }
        
        @keyframes pulseYellow { 
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.2); } 
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); } 
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } 
        }
        
        /* Tabla de facturas */
        .table-custom {
            background-color: var(--win-bg-secondary);
            color: var(--win-text-primary);
            border-color: var(--win-border_color);
        }
        
        .table-custom thead th {
            background-color: var(--win-bg-tertiary);
            border-bottom-color: var(--win-border_color);
            color: var(--win-text-primary);
            font-weight: 600;
        }
        
        .table-custom tbody tr:hover {
            background-color: var(--win-bg-tertiary);
        }
        
        .table-custom td, .table-custom th {
            border-color: var(--win-border_color);
            padding: 0.75rem;
        }
        
        /* Badge para montos */
        .badge-monto {
            font-size: 0.85em;
            padding: 0.25em 0.6em;
            border-radius: 4px;
        }
        
.badge-contabilizada {
    background-color: rgba(40, 167, 69, 0.2);  /* VERDE */
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.badge-anulada {
    background-color: rgba(220, 53, 69, 0.2);  /* ROJO */
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.badge-pagado {
    background-color: rgba(0, 123, 255, 0.2);  /* AZUL */
    color: #007bff;
    border: 1px solid rgba(0, 123, 255, 0.3);
}
.badge-cerrada {
    background-color: #6c757d;  /* Gris */
    color: white;
}
.badge-pendiente {
    background-color: rgba(255, 193, 7, 0.2);  /* AMARILLO */
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}        
        .text-muted { color: #e0e0e0 !important; opacity: 0.9; }
        [data-theme="light"] .text-muted { color: #555555 !important; opacity: 1; }
        
        /* Información adicional */
        .info-adicional {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm);
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid var(--win-accent);
        }
        
        .info-adicional h6 {
            color: var(--win-accent);
            margin-bottom: 10px;
        }
        
        /* Columnas de pago */
        .columna-pago {
            font-size: 0.85rem;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .columna-pago .fecha {
            color: var(--win-text-secondary);
            font-size: 0.8rem;
        }
        
        .columna-pago .referencia {
            font-weight: 500;
            color: var(--win-text-primary);
        }
/* Añade estos estilos en la sección CSS */
.alerta-estado.proximo {
    background-color: rgba(255, 193, 7, 0.2);
    border: 1px solid rgba(255, 193, 7, 0.5);
    color: #ffc107;
    animation: pulseYellow 2s infinite;
}

.alerta-estado.proximo strong {
    color: #ffc107;
}

/* Animación para alerta próxima */
@keyframes pulseYellow { 
    0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.2); } 
    70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); } 
    100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } 
}

/* Badge para días */
.badge-dias {
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    margin-left: 5px;
}

.badge-dias.vencido {
    background-color: rgba(220, 53, 69, 0.2);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.badge-dias.proximo {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
    animation: pulse 1.5s infinite;
}

.badge-dias.activo {
    background-color: rgba(40, 167, 69, 0.2);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}
    </style>
</head>
<body>
    <!-- Theme Panel -->
    <div class="win-theme-overlay" id="themeOverlay" onclick="cerrarPanelTemas()"></div>
    <div class="win-theme-panel" id="themePanel">
        <div class="win-theme-header">
            <h5 class="mb-3" style="color: var(--win-text-primary);">Personalización</h5>
            <h6 style="color: var(--win-text-primary);">Tema del sistema</h6>
        </div>
        <div class="win-theme-options mb-4">
            <div class="win-theme-option mb-2 <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark"><i class="fas fa-moon mb-2"></i><div>Oscuro</div></div>
            <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light"><i class="fas fa-sun mb-2"></i><div>Claro</div></div>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Color de acento</h6>
        <div class="win-color-options mb-4">
            <?php foreach ($colores_accent as $color => $nombre): ?>
                <div class="win-color-option <?php echo $color_accent == $color ? 'active' : ''; ?>" style="background-color: <?php echo $color; ?>;" data-color="<?php echo $color; ?>" title="<?php echo $nombre; ?>"></div>
            <?php endforeach; ?>
        </div>
        <h6 class="mb-3" style="color: var(--win-text-primary);">Opciones de interfaz</h6>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleSidebarMini" <?php echo $sidebar_mini ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleSidebarMini" style="color: var(--win-text-primary);">Sidebar compacto</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAnimations" checked>
            <label class="form-check-label" for="toggleAnimations" style="color: var(--win-text-primary);">Animaciones</label>
        </div>
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()"><i class="fas fa-save"></i> Guardar cambios</button>
    </div>

    <!-- Navbar -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="win-navbar-brand"><i class="fas fa-user"></i><span style="color: var(--win-text-primary);">VER CLIENTE - SISFACT PDL Visiones</span></div>
        <div class="win-nav-search d-none d-md-block"><i class="fas fa-search"></i><input type="text" placeholder="Buscar en el sistema..."></div>
        <div style="flex: 1;"></div>
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar"><i class="fas fa-palette"></i></button>
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

    <!-- Sidebar -->
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
                    <i class="win-nav-icon fas fa-tachometer-alt"></i> Dashboard
                    <span class="win-nav-badge" title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>" style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">F/Cierre: <?php echo date('t'); ?> / <?php echo substr($mes_actual_es, 0, 3); ?></span>
                </a>
                <li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Sistema PDL VISIONES</small></li>
            </li>
            <li class="win-nav-item"><a href="facturas.php" class="win-nav-link"><i class="win-nav-icon fas fa-file-invoice"></i><span class="win-nav-text">Facturas</span><span class="win-nav-badge"><?php echo $total_facturas; ?></span></a></li>
            <li class="win-nav-item"><a href="clientes.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Clientes</span><span class="win-nav-badge"><?php echo $total_clientes; ?></span></a></li>
            <li class="win-nav-item">
                <a href="ver_cliente.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-user-check"></i>
                    <span class="win-nav-text">Ver Cliente:</span>
					<span class="win-nav-badge"><?php echo htmlspecialchars($cliente['ContratoNo'] ?? ''); ?>- ID: <?php echo htmlspecialchars($cliente['id'] ?? ''); ?></span>
                </a>
            </li>
            <li class="win-nav-item"><a href="categorias.php" class="win-nav-link"><i class="win-nav-icon fas fa-tags"></i><span class="win-nav-text">Categorías</span><span class="win-nav-badge"><?php echo $total_categorias; ?></span></a></li>
            <li class="win-nav-item"><a href="servicios.php" class="win-nav-link"><i class="win-nav-icon fas fa-list"></i><span class="win-nav-text">Servicios</span><span class="win-nav-badge"><?php echo $total_servicios; ?></span></a></li>
            <li class="win-nav-item"><a href="usuarios.php" class="win-nav-link"><i class="win-nav-icon fas fa-users"></i><span class="win-nav-text">Usuarios</span><span class="win-nav-badge"><?php echo $total_usuarios; ?></span></a></li>
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
            <li class="win-nav-item"><a href="configuracion.php" class="win-nav-link"><i class="win-nav-icon fas fa-cog"></i><span class="win-nav-text">Configuración</span></a></li>
            <li class="win-nav-item"><a href="historico_view.php" class="win-nav-link"><i class="win-nav-icon fas fa-history"></i><span class="win-nav-text">Histórico</span><span class="win-nav-badge"><?php echo $total_historico; ?></span></a></li>
        </ul>
        
        <?php $finanzas = Database::getProgresoFinanciero(); ?>
        <div class="mt-4 px-3">
            <div class="d-flex justify-content-between align-items-end mb-1">
                <div><small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small><small style="font-size: 10px; color: <?php echo $finanzas['color']; ?>;"><?php echo $finanzas['mensaje']; ?></small></div>
                <h5 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><?php echo number_format($finanzas['porcentaje'], 1); ?>%</h5>
            </div>
            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" role="progressbar" style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>; transition: width 1s ease-in-out;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column"><small class="text-muted" style="font-size: 12px;"><strong>$<?php echo number_format($finanzas['real'], 2); ?></strong></small><small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;"><i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas</small></div>
                <small class="text-end text-success" style="font-size: 12px;">Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?></small>
            </div>
        </div>
    </aside>

    <!-- Contenido -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);"><i class="fas fa-user me-2" style="color: var(--win-accent);"></i>Ver Cliente</h1>
                <p class="text-muted mb-0">Información detallada del cliente - Fecha Operaciones: <strong><?php echo $mes_actual_es . ' ' . date('Y'); ?></strong></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="clientes.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i>Volver a Clientes</a>
                <?php if ($esAdmin || $esEditor || $esSuper): ?>
                <a href="editar_cliente.php?id=<?php echo $cliente_id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit me-1"></i>Editar Cliente</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: <?php echo json_encode($error); ?>,
                    background: '#1f1f1f',
                    color: '#fff',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#3085d6'
                });
            });
            </script>
        <?php endif; ?>

        <!-- RESUMEN DEL CLIENTE -->
        <div class="resumen-cliente animate__animated animate__fadeIn">
            <div class="resumen-title">
                <i class="fas fa-file-contract me-2" style="color: var(--win-accent);"></i>Resumen del Cliente/Contrato
                <small class="float-end text-muted" style="font-size: 0.75rem;">ID: <?php echo $cliente['id']; ?> | Registrado: <?php echo date('d/m/Y', strtotime($cliente['fechaRegistro'])); ?></small>
            </div>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <span class="resumen-label">Estado del Contrato:</span>
                    <span class="resumen-value <?php echo $estado_color; ?>"><?php echo $estado_cliente; ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Código:</span>
                    <span class="resumen-value"><?php echo htmlspecialchars($cliente['codigo']); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Contrato:</span>
                    <span class="resumen-value"><?php echo htmlspecialchars($cliente['ContratoNo']); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fecha Inicio:</span>
                    <span class="resumen-value"><?php echo date('d/m/Y', strtotime($cliente['fechaRegistro'])); ?></span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fecha Vencimiento:</span>
                    <span class="resumen-value"><?php echo date('d/m/Y', strtotime($cliente['fechafinalcontrato'])); ?></span>
                </div>
<div class="resumen-item">
    <span class="resumen-label">Estado BD:</span>
    <span class="resumen-value" style="color: <?php echo $cliente['activo'] == 1 ? '#28a745' : '#dc3545'; ?>;">
        <?php echo $cliente['activo'] == 1 ? 'ACTIVO (1)' : 'INACTIVO (0)'; ?>
        <i class="fas fa-<?php echo $cliente['activo'] == 1 ? 'check-circle' : 'times-circle'; ?> ms-1"></i>
    </span>
</div>
<div class="resumen-item">
    <span class="resumen-label">Tiempo:</span>
    <span class="resumen-value <?php 
        if ($estado_cliente == 'VENCIDO') echo 'estado-vencido';
        elseif (isset($alerta_proximo_vencimiento) && $alerta_proximo_vencimiento) echo 'estado-vencido';
        else echo 'estado-activo';
    ?>">
        <?php if ($estado_cliente == 'VENCIDO'): ?>
            <i class="fas fa-calendar-times me-1"></i>
            <?php 
                $tiempo = [];
                if ($anios_diferencia > 0) $tiempo[] = $anios_diferencia . "a";
                if ($meses_diferencia > 0) $tiempo[] = $meses_diferencia . "m";
                if ($dias_restantes > 0) $tiempo[] = $dias_restantes . "d";
                echo implode(" ", $tiempo) . " vencido" . ($anios_diferencia > 0 || $meses_diferencia > 0 || $dias_restantes > 1 ? 's' : '');
            ?>
        <?php elseif ($estado_cliente == 'ACTIVO'): ?>
            <?php if (isset($alerta_proximo_vencimiento) && $alerta_proximo_vencimiento): ?>
                <i class="fas fa-clock me-1"></i>
                <?php echo $dias_diferencia; ?> día<?php echo $dias_diferencia != 1 ? 's' : ''; ?>
                <small class="text-muted ms-1">(<?php echo $texto_tiempo; ?>)</small>
            <?php else: ?>
                <i class="fas fa-calendar-check me-1"></i>
                <?php 
                    $tiempo = [];
                    if ($anios_diferencia > 0) $tiempo[] = $anios_diferencia . " año" . ($anios_diferencia != 1 ? 's' : '');
                    if ($meses_diferencia > 0) $tiempo[] = $meses_diferencia . " mes" . ($meses_diferencia != 1 ? 'es' : '');
                    if ($dias_restantes > 0) $tiempo[] = $dias_restantes . " día" . ($dias_restantes != 1 ? 's' : '');
                    
                    if (count($tiempo) > 1) {
                        $ultimo = array_pop($tiempo);
                        echo implode(", ", $tiempo) . " y " . $ultimo;
                    } else {
                        echo $tiempo[0] ?? "0 días";
                    }
                ?>
            <?php endif; ?>
        <?php else: ?>
            <i class="fas fa-calendar me-1"></i>Inactivo
        <?php endif; ?>
    </span>
</div>
            </div>
        </div>

        
		
		
		<div class="row">
            <div class="col-lg-8">

<!-- Información Básica -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-info-circle me-2" style="color: var(--win-accent);"></i>Información Básica del Cliente</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fas fa-barcode me-1" style="color: var(--win-accent);"></i>Código Único</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['codigo']); ?>" readonly>
                        <span class="field-help">Código único del cliente</span>
                    </div>
                    <div class="col-md-9 mb-3">
                        <label class="form-label"><i class="fas fa-building me-1" style="color: var(--win-accent);"></i>Nombre Empresa, PDL IMDL, MIPYMES, etc.</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" readonly>
                        <span class="field-help">Nombre completo de la empresa o cliente</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label"><i class="fas fa-user-tie me-1" style="color: var(--win-accent);"></i>Responsable de la Entidad</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['ResponsableEntidad']); ?>" readonly>
                        <span class="field-help">Responsable de la Entidad o Autorizada a Firmar Contratos</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            <i class="fas fa-id-card me-1" style="color: var(--win-accent);"></i>
                            Carné de Identidad (CI)
                        </label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['NoCIResp'] ?? ''); ?>" readonly>
                        <span class="field-help">Identificación Registro Ciudadano</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label"><i class="fas fa-map-marker-alt me-1" style="color: var(--win-accent);"></i>Dirección</label>
                        <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
                        <span class="field-help">Dirección física completa</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fas fa-qrcode me-1" style="color: var(--win-accent);"></i>Código REEUP</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['CodReup']); ?>" readonly>
                        <span class="field-help">Formato: xxx.x.xxxx</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fas fa-file-invoice me-1" style="color: var(--win-accent);"></i>NIT: Identificación Tributaria</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['NIT']); ?>" readonly>
                        <span class="field-help">Código de 11 dígitos</span>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label"><i class="fas fa-sticky-note me-1" style="color: var(--win-accent);"></i>Observaciones</label>
                        <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($cliente['observaciones']); ?></textarea>
                        <span class="field-help">Observaciones adicionales</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos Bancarios -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-university me-2" style="color: var(--win-accent);"></i>DATOS BANCARIOS Y CONTRACTUALES</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fas fa-file-signature me-1" style="color: var(--win-accent);"></i>Número de Contrato</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['ContratoNo']); ?>" readonly>
                        <span class="field-help">Número de contrato CVIS-####</span>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fas fa-code-branch me-1" style="color: var(--win-accent);"></i>Sucursal Bancaria</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['SucursalCobroLocalidad']); ?>" readonly>
                        <span class="field-help">Número de sucursal</span>
                    </div>
                    <div class="col-5 mb-3">
                        <label class="form-label"><i class="fas fa-credit-card me-1" style="color: var(--win-accent);"></i>Cuenta Bancaria</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['NoCtaDeudor']); ?>" readonly>
                        <span class="field-help">Cuenta bancaria del cliente</span>
                    </div>
                </div>
            </div>
        </div>
				
				
				<!-- Últimas Facturas -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-file-invoice me-2" style="color: var(--win-accent);"></i>Últimas Facturas del Cliente:<br><span class="text-info"><?php echo htmlspecialchars($cliente['nombre']); ?></span></h6>
                        <a href="facturas.php?cliente_id=<?php echo $cliente_id; ?>" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($facturas) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>N° Factura</th>
                                            <th>Fecha Emisión</th>
                                            <th>Importe</th>
                                            <th>Estado</th>
                                            <th>Fecha Pago</th>
                                            <th>Referencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($facturas as $factura): ?>
                                            <tr>
                                                <td><a href="ver_factura.php?id=<?php echo $factura['id']; ?>" class="text-decoration-none"><?php echo $factura['no_fact']; ?></a></td>
                                                <td><?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?></td>
                                                <td><strong>$<?php echo number_format($factura['total_general'], 2); ?></strong></td>
<td>
    <?php 
    $estado = $factura['estado'] ?? 'PENDIENTE';
    $badge_class = '';
    $badge_text = '';
    
    // Lógica especial para facturas CERRADAS
    if ($estado === 'CERRADA') {
        // Verificar si tiene fecha_pago (no vacía, no null)
        if (!empty($factura['fecha_pago'])) {
            $badge_class = 'badge-pagado';      // AZUL
            $badge_text = 'PAGADA';
        } else {
            $badge_class = 'badge-cerrada';
            $badge_text = 'CERRADA';
        }
    } else {
        // Lógica normal para los demás estados
        switch ($estado) {
            case 'PAGADA':
                $badge_class = 'badge-pagado';      // AZUL
                $badge_text = 'PAGADA';
                break;
            case 'CONTABILIZADA':
                $badge_class = 'badge-contabilizada'; // VERDE
                $badge_text = 'CONTABILIZADA';
                break;
            case 'ANULADA':
                $badge_class = 'badge-anulada';     // ROJO
                $badge_text = 'ANULADA';
                break;
            case 'PENDIENTE':
            default:
                $badge_class = 'badge-pendiente';   // AMARILLO
                $badge_text = 'PENDIENTE';
                break;
        }
    }
    ?>
    <span class="badge badge-monto <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
</td>
<td class="columna-pago">
    <?php 
    $tiene_fecha_pago = !empty($factura['fecha_pago']) && 
                        $factura['fecha_pago'] != '0000-00-00' && 
                        $factura['fecha_pago'] != '0000-00-00 00:00:00';
    
    if ($tiene_fecha_pago):
        // Extraer solo la parte de la fecha (YYYY-MM-DD)
        $fecha_solo = substr($factura['fecha_pago'], 0, 10);
        echo date('d/m/Y', strtotime($fecha_solo));
    else:
        echo '-';
    endif;
    ?>
</td>
<td class="columna-pago">
    <?php 
    $tiene_referencia = !empty($factura['Ref_pago']) && 
                        trim($factura['Ref_pago']) !== '' && 
                        $factura['Ref_pago'] !== '0';
    
    if ($tiene_referencia):
        echo htmlspecialchars($factura['Ref_pago']);
    else:
        echo '-';
    endif;
    ?>
</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-invoice fa-3x mb-3" style="color: var(--win-text-secondary);"></i>
                                <p class="text-muted">No hay facturas registradas para este cliente.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Información de Contacto -->
                <div class="card mb-4">
                    <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-address-card me-2" style="color: var(--win-accent);"></i>Información de Contacto</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" readonly>
                            <span class="field-help">Teléfono de contacto</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['email']); ?>" readonly>
                            <span class="field-help">Correo electrónico de contacto</span>
                        </div>
                        
                        <!-- Alerta de estado -->
  <div class="alerta-estado <?php 
    if ($estado_cliente == 'ACTIVO') {
        if (isset($alerta_proximo_vencimiento) && $alerta_proximo_vencimiento) {
            echo 'vencido'; // Usar estilo de vencido para alerta
        } else {
            echo 'activo';
        }
    } elseif ($estado_cliente == 'VENCIDO') {
        echo 'vencido';
    } else {
        echo '';
    }
?>">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php if ($estado_cliente == 'VENCIDO'): ?>
        <strong>CONTRATO VENCIDO</strong><br>
        <?php echo date('d/m/Y', strtotime($cliente['fechafinalcontrato'])); ?> 
        - <?php echo $dias_texto; ?>
    <?php elseif ($estado_cliente == 'ACTIVO'): ?>
        <?php if (isset($alerta_proximo_vencimiento) && $alerta_proximo_vencimiento): ?>
            <strong>¡ATENCIÓN! POR VENCERSE</strong><br>
        <?php else: ?>
            <strong>CONTRATO ACTIVO</strong><br>
        <?php endif; ?>
        Vence: <?php echo date('d/m/Y', strtotime($cliente['fechafinalcontrato'])); ?><br>
        <?php echo $dias_texto; ?>
        <?php if (isset($alerta_proximo_vencimiento) && $alerta_proximo_vencimiento): ?>
            <div class="mt-1"><i class="fas fa-clock me-1"></i>Falta<?php echo $dias_diferencia != 1 ? 'n' : ''; ?> solo <?php echo $dias_diferencia; ?> día<?php echo $dias_diferencia != 1 ? 's' : ''; ?></div>
        <?php endif; ?>
    <?php else: ?>
        <strong>CONTRATO INACTIVO</strong><br>
        <?php echo date('d/m/Y', strtotime($cliente['fechafinalcontrato'])); ?>
    <?php endif; ?>
</div>
                    </div>
                </div>

                <!-- Fechas Contractuales -->
                <div class="card mb-4">
                    <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-calendar-check me-2" style="color: var(--win-accent);"></i>FECHAS CONTRACTUALES</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Fecha de Registro</label>
                            <input type="date" class="form-control" value="<?php echo $cliente['fechaRegistro']; ?>" readonly>
                            <span class="field-help">Fecha de registro del cliente</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vigencia (Años)</label>
                            <input type="text" class="form-control" value="<?php echo $cliente['vigenciapor']; ?> años" readonly>
                            <span class="field-help">Vigencia en años enteros</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha Vencimiento (Inicial)</label>
                            <input type="date" class="form-control" value="<?php echo $cliente['fechaVence']; ?>" readonly>
                            <span class="field-help">Fecha de vencimiento inicial</span>
                        </div>
                        
                        <?php if ($cliente['renovac'] == 1 && $cliente['si_renova_cant'] > 0): ?>
                            <hr class="border-secondary my-3">
                            <div class="mb-3">
                                <div class="info-adicional">
                                    <h6><i class="fas fa-redo me-2"></i>Renovación del Contrato</h6>
                                    <p class="mb-2"><strong>Años adicionales:</strong> <?php echo $cliente['si_renova_cant']; ?> años</p>
                                    <p class="mb-0"><strong>Fecha final total:</strong> <?php echo date('d/m/Y', strtotime($cliente['fechafinalcontrato'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="card mb-4">
                    <div class="card-header"><h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-chart-bar me-2" style="color: var(--win-accent);"></i>Estadísticas</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Facturado:</span>
                                <strong>$<?php echo number_format($total_facturado, 2); ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo min(($total_facturado/10000)*100, 100); ?>%; background-color: var(--win-accent);"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Facturas Registradas:</span>
                                <strong><?php echo count($facturas); ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tiempo como Cliente:</span>
                                <strong>
                                    <?php 
                                        $fecha_registro = new DateTime($cliente['fechaRegistro']);
                                        $hoy = new DateTime();
                                        $intervalo = $fecha_registro->diff($hoy);
                                        echo $intervalo->y . ' años, ' . $intervalo->m . ' meses';
                                    ?>
                                </strong>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <small class="text-muted">Última actualización: <?php echo date('d/m/Y H:i'); ?></small>
                        </div>
                    </div>
                </div>
                    <!-- Observaciones -->
                    <div class="card mb-4">
                        <div class="card-header fw-bold" style="color: var(--win-text-primary);"><i class="fas fa-comment-alt me-2" style="color: var(--win-accent);"></i>Observaciones</div>
                        <div class="card-body">
                            <textarea class="form-control" name="observaciones" rows="2" readonly><?php echo htmlspecialchars($cliente['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
            </div>
        </div>

        
		
		
		<div class="d-flex justify-content-between pt-3 border-top mb-5">
            <a href="clientes.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver a Clientes</a>
            <div>
                <?php if ($esAdmin || $esEditor || $esSuper): ?>
                <a href="editar_cliente.php?id=<?php echo $cliente_id; ?>" class="btn btn-primary me-2"><i class="fas fa-edit me-1"></i>Editar Cliente</a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-danger" onclick="confirmarEliminar()"><i class="fas fa-trash me-1"></i>Eliminar Cliente</button>
            </div>
        </div>
    </main>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>

    <script>
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
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
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
        
        // Cerrar panel con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themePanelOpen) {
                cerrarPanelTemas();
            }
        });
        
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
        
        // Confirmar eliminación del cliente
        function confirmarEliminar() {
            Swal.fire({
                title: '¿Eliminar Cliente?',
                html: `
                    <div style="text-align: left;">
                        <p><strong>¿Está seguro de eliminar este cliente?</strong></p>
                        <hr>
                        <div style="background: rgba(220, 53, 69, 0.1); padding: 10px; border-radius: 5px; margin: 10px 0;">
                            <p><strong>Empresa:</strong> <?php echo addslashes($cliente['nombre']); ?></p>
                            <p><strong>Código:</strong> <?php echo addslashes($cliente['codigo']); ?></p>
                            <p><strong>Contrato:</strong> <?php echo addslashes($cliente['ContratoNo']); ?></p>
                        </div>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer. Se eliminarán todos los datos asociados al cliente.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sí, Eliminar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                background: '#1f1f1f',
                color: '#fff',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirigir para eliminar
                    window.location.href = 'eliminar_cliente.php?id=<?php echo $cliente_id; ?>';
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            let t = localStorage.getItem('tema_windows'); 
            if(t) document.documentElement.setAttribute('data-theme', t);
            let c = localStorage.getItem('color_accent'); 
            if(c) { 
                document.documentElement.style.setProperty('--win-accent', c); 
                document.documentElement.style.setProperty('--win-accent-light', c + '20'); 
            }
        });
    </script>
</body>
</html>