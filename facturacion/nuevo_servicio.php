<?php
// nuevo_servicio.php - Windows 11 Dark Mode
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

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

// Inicializar variables
$error = '';
$success = '';
$es_admin = false;

// Obtener información del usuario desde la base de datos para verificar rol
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
        $_SESSION['error'] = "Usuario no encontrado";
        header('Location: servicios.php');
        exit();
    }
    
    // Verificar si el usuario es administrador (rol_id = 1)
    if ($usuario['rol_id'] != 1 && $usuario['rol_id'] != 3 && $usuario['rol_id'] != 4) {
    // Solo se ejecuta si rol_id NO es 1, 3 ni 4
        // Guardar mensaje para SweetAlert en sesión
        $_SESSION['swal_no_privilegios'] = [
            'titulo' => 'Acceso Denegado',
            'mensaje' => 'Solo usuarios con rol de <strong>Administrador, Editor o Supervisor</strong> pueden crear nuevos servicios.',
            'tipo_usuario' => $usuario['rol_nombre'] ?? 'Usuario',
            'icono' => 'error',
            'rol_id' => $usuario['rol_id'] ?? 0
        ];
        
        // Redirigir a servicios.php donde se mostrará el SweetAlert
        header('Location: servicios.php');
        exit();
    }
    
    // Si llegamos aquí, el usuario ES administrador
    $es_admin = true;
    
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
    
    // Obtener categorías para el select
    $sql_categorias = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE activo = 1 ORDER BY codigo";
    $stmt_categorias = $db->query($sql_categorias);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas para el histórico
    try {
        $sql_total_historico = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total_historico);
        $estadisticas_historico = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_historico = $estadisticas_historico['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $total_historico = 0;
    }
    
    // Inicializar variables del formulario
    $codigo = '';
    $descripcion = '';
    $categoria_id = '';
    $costo = '';
    $activo = 1;
    
    // Si hay datos de formulario en sesión (por error anterior), usarlos
    if (isset($_SESSION['form_data'])) {
        $codigo = $_SESSION['form_data']['codigo'] ?? '';
        $descripcion = $_SESSION['form_data']['descripcion'] ?? '';
        $categoria_id = $_SESSION['form_data']['categoria_id'] ?? '';
        $costo = $_SESSION['form_data']['costo'] ?? '';
        $activo = $_SESSION['form_data']['activo'] ?? 1;
        
        // Limpiar datos de sesión
        unset($_SESSION['form_data']);
    }
    
    // Mensajes de éxito/error (si existen de procesamiento previo)
    $error = $_SESSION['error'] ?? '';
    $success = $_SESSION['success'] ?? '';
    
    // Limpiar mensajes de sesión
    unset($_SESSION['error']);
    unset($_SESSION['success']);
    
} catch (Exception $e) {
    $error = "Error al cargar datos del formulario";
    $categorias = [];
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
    <title>Nuevo Servicio - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
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

        /* Contenido principal */
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

        /* Quick Actions - Simple */
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
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Responsive */
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
                bottom: 16px;
                right: 16px;
                width: 50px;
                height: 50px;
                font-size: 18px;
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
        
        /* Controles de cantidad */
        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cantidad-input {
            width: 70px;
            text-align: center;
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-primary);
            border-radius: var(--win-radius-sm);
            padding: 0.375rem 0.5rem;
        }

        .cantidad-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: var(--win-bg-tertiary);
            border: 1px solid var(--win-border-color);
            color: var(--win-text-secondary);
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .cantidad-btn:hover {
            background: var(--win-bg-tertiary);
            border-color: var(--win-accent);
            color: var(--win-accent);
        }

        .cantidad-btn i {
            font-size: 12px;
        }

        /* Modal */
        .modal-content {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border-color);
            border-radius: var(--win-radius);
        }

        .modal-header {
            background: var(--win-bg-tertiary);
            border-bottom: 1px solid var(--win-border_color);
            color: var(--win-text-primary);
        }

        .modal-body {
            color: var(--win-text-primary);
        }

        .modal-footer {
            background: var(--win-bg-tertiary);
            border-top: 1px solid var(--win-border_color);
        }

        /* Totales */
        .totales-container {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius);
            padding: 1.5rem;
            border: 1px solid var(--win-border-color);
        }

        .total-label {
            color: var(--win-text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .total-value {
            color: var(--win-text-primary);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .total-general {
            color: var(--win-accent) !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
        }

        /* Estilos para la información del cliente */
        .cliente-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid var(--win-border-color);
        }

        .cliente-info-label {
            font-size: 12px;
            color: var(--win-text-secondary);
            font-weight: 500;
            flex: 1;
            opacity: 0.8;
        }

        .cliente-info-value {
            font-size: 12px;
            color: var(--win-text-primary);
            font-weight: 400;
            text-align: right;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Badge de administrador */
        .admin-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        
        /* Estilos para la verificación de código */
        .codigo-disponible {
            color: #198754;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .codigo-no-disponible {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .codigo-verificando {
            color: #6c757d;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .input-group-codigo {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .input-group-codigo .form-control {
            flex: 1;
        }
        
        .input-group-codigo .btn {
            white-space: nowrap;
        }
    /* Estilo específico para mejorar visibilidad de form-text */
    .form-text {
        font-size: 0.875em;
        margin-top: 0.25rem;
    }

    [data-theme="dark"] .form-text {
        color: #a6a6a6 !important;
        opacity: 0.9 !important;
    }

    [data-theme="light"] .form-text {
        color: #6c757d !important;
        opacity: 1 !important;
    }

    /* Estilo para los íconos dentro de form-text */
    .form-text i {
        opacity: 0.8;
        margin-right: 4px;
    }

    [data-theme="dark"] .form-text i {
        color: var(--win-accent);
    }

    [data-theme="light"] .form-text i {
        color: var(--win-accent);
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
            <i class="fas fa-plus-circle"></i>
            <span style="color: var(--win-text-primary);">NUEVO SERVICIO - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
			<i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <!-- Espacio flexible -->
        <div style="flex: 1;"></div>
        
        <!-- Badge de administrador -->
        <?php if ($es_admin): ?>
        <div class="admin-badge d-none d-md-block me-2">
            <i class="fas fa-crown me-1"></i>ADMIN
        </div>
        <?php endif; ?>
        
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
                                <?php if ($es_admin): ?>
                                <span class="admin-badge ms-1" style="font-size: 8px; padding: 1px 4px;">
                                    <i class="fas fa-crown"></i>
                                </span>
                                <?php endif; ?>
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
                            Dashboard<span class="win-nav-badge" 
              title="Fecha de Cierre Actual: <?php echo date('t') . ' de ' . $mes_actual_es; ?>" 
              style="width: auto; border-radius: 4px; padding: 2px 8px; font-weight: normal; font-size: 10px; cursor: help;">
            F/Cierre: <?php echo date('t'); ?> / <?php echo substr($mes_actual_es, 0, 3); ?>
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
                <a href="nuevo_servicio.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-plus-circle"></i>
                    <span class="win-nav-text">Nuevo Servicio</span>
                    <?php if ($es_admin): ?>
                    <span class="win-nav-badge admin-badge" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-crown"></i>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="usuarios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-user-cog"></i>
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
<li class="mt-3 mb-2 px-3"><small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">CONFIGURACIÓN</small></li>
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
                    <span class="win-nav-badge"><?php echo $total_historico; ?></span>
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
            <small class="text-muted d-block fw-bold">Plan <?php echo substr($mes_actual_es, 0, 3) . ' / ' . date('Y')?> (CUP)</small>
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

    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <div>
                <h1 class="h2 mb-0" style="color: var(--win-text-primary);">
                    <i class="fas fa-plus-circle me-2" style="color: var(--win-accent);"></i>Nuevo Servicio
                </h1>
                <p class="text-muted mb-0">
                    <p class="text-muted mb-0">Complete los datos para crear un Nuevo Servicio - Fecha Operaciones: <strong><?php echo $mes_actual_es . ' ' . date('Y'); ?></strong></p>
                </p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="servicios.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Volver a Servicios
                </a>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Error al crear servicio</h6>
                        <p class="mb-0"><?php echo $error; ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success) && !empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show highlight-success" role="alert">
                <div class="d-flex">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1">¡Servicio creado exitosamente!</h6>
                        <p class="mb-0"><?php echo $success; ?></p>
                        <small class="text-muted">El servicio ha sido registrado en el sistema y está disponible para su uso.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <div class="alert alert-info mb-4">
                <i class="fas fa-lightbulb me-2"></i>
                <strong>¿Qué desea hacer ahora?</strong>
                <div class="mt-2">
                    <a href="nuevo_servicio.php" class="btn btn-sm btn-primary me-2">
                        <i class="fas fa-plus me-1"></i>Crear otro servicio
                    </a>
                    <a href="servicios.php" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-list me-1"></i>Ver todos los servicios
                    </a>
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-tachometer-alt me-1"></i>Volver al Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>


        <!-- Formulario de creación -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-edit me-1"></i>Información del Nuevo Servicio
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- El formulario debe apuntar a un archivo de procesamiento -->
                        <form method="POST" id="formNuevoServicio" action="procesar_nuevo_servicio.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="codigo" class="form-label">
                                        <i class="fas fa-hashtag me-1 text-primary"></i>Código *
										<span class="text-muted small">(Obligatorio)</span>
                                    </label>
                                    <div class="input-group-codigo">
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                               value="<?php echo htmlspecialchars($codigo); ?>" 
                                               maxlength="10" title="Solo letras mayúsculas y números (2-10 caracteres)"
                                               oninput="verificarCodigoEnTiempoReal()">
                                        <button type="button" class="btn btn-outline-info" onclick="generarCodigo()" title="Generar código sugerido">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </div>
                                    <div id="codigoFeedback" class="mt-1"></div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Código único del servicio (ej: CR01, FS01). Solo mayúsculas y números. (Hasta 10 Caracteres)
                                    </div>
                                </div>
                                
<div class="col-md-6 mb-3">
    <label for="categoria_id" class="form-label">
        <i class="fas fa-tags me-1 text-primary"></i>Categorías
    </label>
    <select class="form-select <?php echo isset($_SESSION['form_data']['categoria_id']) && empty($categorias) ? 'is-invalid' : ''; ?>" 
            id="categoria_id" name="categoria_id">
        <option value="">Seleccione una categoría</option>
        <?php if (empty($categorias)): ?>
            <option value="" disabled>No hay categorías disponibles</option>
        <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?php echo $categoria['id']; ?>" 
                    <?php echo $categoria_id == $categoria['id'] ? 'selected' : ''; ?>
                    data-codigo="<?php echo htmlspecialchars($categoria['codigo']); ?>">
                    <?php echo htmlspecialchars($categoria['codigo'] . ' - ' . $categoria['descripcion']); ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
    <div id="categoriaFeedback" class="mt-1"></div>
    <div class="form-text">
        <i class="fas fa-info-circle me-1"></i>
        Asignar a una categoría ayuda en la organización y búsqueda.
        <?php if (empty($categorias)): ?>
            <span class="text-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                No hay categorías activas. <a href="categorias.php">Crear categorías primero</a>
            </span>
        <?php endif; ?>
    </div>
</div>
								
								<div class="col-12 mb-3">
                                    <label for="descripcion" class="form-label">
                                        <i class="fas fa-align-left me-1 text-primary"></i>Descripción *
										<span class="text-muted small">(Obligatorio)</span>
                                    </label>
                                    <input type="text" class="form-control" id="descripcion" name="descripcion" 
                                           value="<?php echo htmlspecialchars($descripcion); ?>" 
                                           maxlength="200">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Descripción detallada del servicio (máximo 200 caracteres)
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="costo" class="form-label">
                                        <i class="fas fa-dollar-sign me-1 text-primary"></i>Costo ($) * <span class="text-muted small">(Obligatorio)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="costo" name="costo" 
                                               step="0.01" min="0.01" 
                                               value="<?php echo htmlspecialchars($costo); ?>">
                                        <span class="input-group-text">CUP</span>
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Costo del servicio en pesos cubanos (CUP)
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-toggle-on me-1 text-primary"></i>Estado
                                    </label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                               <?php echo $activo ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo">
                                            Servicio activo (disponible para facturación)
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Los servicios inactivos no estarán disponibles para facturación
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top border-color">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="servicios.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </a>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary me-2" onclick="limpiarFormulario()">
                                            <i class="fas fa-eraser me-1"></i>Limpiar
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i>Crear Servicio
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-info-circle me-1"></i>Información Adicional
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">
                                <i class="fas fa-user-shield me-2"></i>Usuario Registrador
                            </h6>
                            <div class="d-flex align-items-center">
                                <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                    <?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="color: var(--win-text-primary); font-weight: 500;">
                                        <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador'); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php if ($es_admin): ?>
                                        <span class="admin-badge" style="font-size: 18px; padding: 1px 4px;">
                                            <i class="fas fa-crown me-1"></i>ADMIN
                                        </span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">
                                <i class="fas fa-calendar-alt me-2"></i>Fecha de Creación
                            </h6>
                            <div class="badge bg-secondary">
                                <i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i:s'); ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <div>
							    <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Notas importantes:</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    <li class="small">Todos los campos marcados con * son obligatorios</li>
                                    <li class="small">El código debe ser único en el sistema</li>
                                    <li class="small">Se registrará esta acción en el histórico</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <div>
								<i class="fas fa-lightbulb me-2"></i>
                                <strong>Consejos:</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    <li class="small">Use códigos descriptivos (ej: CR01 para Consulta de Retina)</li>
                                    <li class="small">Asigne categorías para mejor organización</li>
                                    <li class="small">Revise el costo antes de guardar</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                            <i class="fas fa-bolt me-1"></i>Acciones Rápidas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="servicios.php" class="btn btn-outline-primary">
                                <i class="fas fa-cog me-1"></i>Gestionar Servicios
                            </a>
                            <a href="categorias.php" class="btn btn-outline-secondary">
                                <i class="fas fa-tags me-1"></i>Gestionar Categorías
                            </a>
                            <button type="button" class="btn btn-outline-info" onclick="limpiarFormulario()">
                                <i class="fas fa-eraser me-1"></i>Limpiar Formulario
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Quick Action Simple -->
    <?php if ($es_admin): ?>
    <button class="win-quick-action" onclick="document.getElementById('formNuevoServicio').submit()" title="Crear servicio">
        <i class="fas fa-save"></i>
    </button>
    <?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;
        let timeoutVerificacion = null;
        
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
            }
        }
        
        // Generar código sugerido
        function generarCodigo() {
            const descripcion = document.getElementById('descripcion').value.trim();
            let codigoSugerido = '';
            
            if (descripcion) {
                // Tomar las primeras letras de cada palabra
                const palabras = descripcion.toUpperCase().split(' ');
                if (palabras.length >= 2) {
                    codigoSugerido = palabras[0].charAt(0) + palabras[1].charAt(0) + '01';
                } else if (palabras.length === 1 && palabras[0].length >= 2) {
                    codigoSugerido = palabras[0].substring(0, 2).toUpperCase() + '01';
                } else {
                    codigoSugerido = 'SV01';
                }
                
                // Verificar que solo contenga letras y números
                codigoSugerido = codigoSugerido.replace(/[^A-Z0-9]/g, '');
                
                // Asegurar longitud entre 2-10 caracteres
                if (codigoSugerido.length > 10) {
                    codigoSugerido = codigoSugerido.substring(0, 10);
                } else if (codigoSugerido.length < 2) {
                    codigoSugerido = 'SV01';
                }
                
                document.getElementById('codigo').value = codigoSugerido;
                
                // Verificar disponibilidad del código generado
                setTimeout(() => {
                    verificarCodigoEnTiempoReal();
                }, 100);
                
                Swal.fire({
                    title: 'Código Generado',
                    text: `Se ha generado el código: ${codigoSugerido}`,
                    icon: 'success',
                    confirmButtonText: '<i class="fas fa-check me-1"></i>OK',
                    timer: 2000
                });
            } else {
                Swal.fire({
                    title: 'Información requerida',
                    text: 'Por favor ingrese una descripción primero',
                    icon: 'warning',
                    confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido'
                });
                document.getElementById('descripcion').focus();
            }
        }
        
        // Limpiar formulario
        function limpiarFormulario() {
            Swal.fire({
                title: '¿Limpiar formulario?',
                text: 'Todos los datos ingresados serán eliminados',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fas fa-broom me-1"></i>Sí, limpiar',
                cancelButtonText: '<i class="fas fa-check me-1"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formNuevoServicio').reset();
                    document.getElementById('activo').checked = true;
                    
                    // Limpiar mensaje de verificación
                    document.getElementById('codigoFeedback').innerHTML = '';
                    document.getElementById('codigoFeedback').className = 'mt-1';
                    
                    // Enfocar en el primer campo
                    document.getElementById('codigo').focus();
                    
                    Swal.fire({
                        title: 'Formulario limpiado',
                        text: 'Puede comenzar a ingresar nuevos datos',
                        icon: 'success',
                        timer: 1500,
confirmButtonText: '<i class="fas fa-broom me-1"></i>Sí, limpiar'
                    });
                }
            });
        }
        
// Verificar código en tiempo real - VERSIÓN DEBUG
function verificarCodigoEnTiempoReal() {
    const codigoInput = document.getElementById('codigo');
    const codigo = codigoInput.value.trim().toUpperCase();
    const feedback = document.getElementById('codigoFeedback');
    
        
    // Limpiar timeout anterior
    if (timeoutVerificacion) {
        clearTimeout(timeoutVerificacion);
    }
    
    // Validar formato básico
    const codigoRegex = /^[A-Z0-9]{2,10}$/;
    
    // Actualizar input a mayúsculas
    codigoInput.value = codigo;
    
    if (codigo.length < 2) {
        feedback.innerHTML = '';
        feedback.className = 'mt-1';
        codigoInput.classList.remove('is-invalid', 'is-valid');
        return;
    }
    
    if (!codigoRegex.test(codigo)) {
        feedback.innerHTML = '<span class="codigo-no-disponible"><i class="fas fa-times-circle me-1"></i>Formato inválido. Solo letras mayúsculas y números (2-10 caracteres)</span>';
        feedback.className = 'codigo-no-disponible mt-1';
        codigoInput.classList.remove('is-valid');
        codigoInput.classList.add('is-invalid');
        return;
    }
    
    // Mostrar mensaje de verificación
    feedback.innerHTML = '<span class="codigo-verificando"><i class="fas fa-spinner fa-spin me-1"></i>Verificando disponibilidad...</span>';
    feedback.className = 'codigo-verificando mt-1';
    codigoInput.classList.remove('is-invalid', 'is-valid');
    
    // Debug: Mostrar URL que se intentará llamar
    const url = `verificar_codigo_servicio.php?codigo=${encodeURIComponent(codigo)}`;
        
    // Esperar 500ms antes de hacer la petición
    timeoutVerificacion = setTimeout(() => {
        // Hacer petición AJAX para verificar el código
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {

            if (data.disponible) {
                feedback.innerHTML = `<span class="codigo-disponible"><i class="fas fa-check-circle me-1"></i>${data.mensaje}</span>`;
                feedback.className = 'codigo-disponible mt-1';
                codigoInput.classList.remove('is-invalid');
                codigoInput.classList.add('is-valid');
            } else {
                feedback.innerHTML = `<span class="codigo-no-disponible"><i class="fas fa-times-circle me-1"></i>${data.mensaje}</span>`;
                feedback.className = 'codigo-no-disponible mt-1';
                codigoInput.classList.remove('is-valid');
                codigoInput.classList.add('is-invalid');
            }
        })
        .catch(error => {
            feedback.innerHTML = '<span class="codigo-no-disponible"><i class="fas fa-exclamation-triangle me-1"></i>Error al verificar disponibilidad</span>';
            feedback.className = 'codigo-no-disponible mt-1';
            codigoInput.classList.remove('is-valid');
        });
    }, 500);
}
// Validar formulario - VERSIÓN CORREGIDA
document.getElementById('formNuevoServicio').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const codigo = document.getElementById('codigo').value.trim();
    const descripcion = document.getElementById('descripcion').value.trim();
    const costo = document.getElementById('costo').value;
    const categoriaSelect = document.getElementById('categoria_id');
    const categoriaId = categoriaSelect.value;
    const codigoInput = document.getElementById('codigo');
    
    // 1. VALIDAR FORMATO DEL CÓDIGO PRIMERO
    const codigoRegex = /^[A-Z0-9]{2,10}$/;
    if (!codigoRegex.test(codigo)) {
        Swal.fire({
            title: 'Error en el código',
            html: `
                <div class="text-start">
                    <p>El código <strong>"${codigo}"</strong> no cumple con el formato requerido.</p>
                    <div class="alert alert-danger p-2 mt-2">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <small>Debe contener solo letras mayúsculas y números (2-10 caracteres)</small>
                    </div>
                    <div class="alert alert-info p-2 mt-2">
                        <i class="fas fa-lightbulb me-2"></i>
                        <small><strong>Ejemplos válidos:</strong> CR01, EXAMEN01, RX123, CONSULTA1</small>
                    </div>
                </div>
            `,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-edit me-1"></i> Corregir',
            confirmButtonColor: '#d33',
            focusConfirm: false,
            allowOutsideClick: false,
            width: '500px'
        }).then(() => {
            document.getElementById('codigo').focus();
            document.getElementById('codigo').select();
            // Agregar clase de error visual
            codigoInput.classList.remove('is-valid');
            codigoInput.classList.add('is-invalid');
        });
        return;
    }
    
    // 2. Verificar si el código tiene la clase is-invalid (no disponible en DB)
    if (codigoInput.classList.contains('is-invalid')) {
        Swal.fire({
            title: 'Código no disponible',
            text: 'El código ingresado ya existe en el sistema. Por favor ingrese un código diferente.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
            confirmButtonColor: '#d33'
        });
        codigoInput.focus();
        return;
    }
    
    // 3. Verificar si el código tiene la clase is-valid (disponible verificada por AJAX)
    if (!codigoInput.classList.contains('is-valid')) {
        Swal.fire({
            title: 'Código no verificado',
            html: `
                <div class="text-start">
                    <p>El código <strong>"${codigo}"</strong> necesita ser verificado.</p>
                    <div class="alert alert-warning p-2 mt-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <small>Por favor, espere a que se complete la verificación de disponibilidad.</small>
                    </div>
                    <p class="text-muted small mt-2">La verificación automática tarda unos segundos...</p>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
            confirmButtonColor: '#ffc107',
            width: '450px'
        });
        codigoInput.focus();
        return;
    }
    
    // 4. Validar que el código no esté vacío (ya debería estar validado por el regex)
    if (!codigo) {
        Swal.fire({
            title: 'Código vacío',
            text: 'El código es obligatorio',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
        });
        document.getElementById('codigo').focus();
        return;
    }
    
    if (!descripcion) {
        Swal.fire({
            title: 'ERROR!!!',
            html: 'El campo <strong style="color:red;">«Descripción»</strong> es Obligatorio.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
        });
        document.getElementById('descripcion').focus();
        return;
    }
    
    if (!costo || parseFloat(costo) <= 0) {
        Swal.fire({
            title: 'ERROR!!!',
            html: 'El campo <strong style="color:red;">«Costo»</strong> es Obligatorio y el Valor debe ser Mayor que $ 0.00 Pesos.',
            icon: 'error',
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido'
        });
        document.getElementById('costo').focus();
        return;
    }
    
    // VALIDAR CATEGORÍA (si se seleccionó una)
    if (categoriaId) {
        // Verificar que la categoría sea un número válido
        if (isNaN(parseInt(categoriaId)) || parseInt(categoriaId) <= 0) {
            // Solo mostrar validación visual, no SweetAlert
            document.getElementById('categoria_id').classList.add('is-invalid');
            document.getElementById('categoria_id').focus();
            return;
        }
    }
    
    // Obtener el texto de la categoría seleccionada
    let categoriaTexto = 'No asignada';
    if (categoriaId && categoriaSelect.selectedIndex > 0) {
        categoriaTexto = categoriaSelect.options[categoriaSelect.selectedIndex].text;
    }
    
    // Confirmar creación
    Swal.fire({
        title: '¿Crear nuevo servicio?',
        html: `
            <div class="text-start">
                <p><strong>Confirme los datos del nuevo servicio:</strong></p>
                <div class="alert alert-info p-3">
                    <p class="mb-1"><strong>Código:</strong> ${codigo}</p>
                    <p class="mb-1"><strong>Descripción:</strong> ${descripcion}</p>
                    <p class="mb-1"><strong>Costo:</strong> $${parseFloat(costo).toFixed(2)} CUP</p>
                    <p class="mb-1"><strong>Categoría:</strong> ${categoriaTexto}</p>
                    <p class="mb-0"><strong>Estado:</strong> ${document.getElementById('activo').checked ? 'Activo' : 'Inactivo'}</p>
                </div>
                <p class="mb-0"><small>Esta acción será registrada en el histórico del sistema.</small></p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-plus-circle me-1"></i>Sí, crear servicio',
        cancelButtonText: '<i class="fas fa-chart-line me-1"></i>Revisar datos',
        reverseButtons: true,
        backdrop: 'rgba(0,0,0,0.7)',
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar carga
            Swal.fire({
                title: 'Creando servicio...',
                html: `
                    <div class="text-center">
                        <i class="fas fa-cog fa-spin fa-2x text-primary mb-3"></i>
                        <p>Guardando en la base de datos...</p>
                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                `,
                showCancelButton: false,
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            // Enviar formulario
            setTimeout(() => {
                document.getElementById('formNuevoServicio').submit();
            }, 1000);
        }
    });
});    
        // Validar código en tiempo real al escribir
        document.getElementById('codigo').addEventListener('input', function() {
            verificarCodigoEnTiempoReal();
        });
        
        // Formatear costo al perder foco
        document.getElementById('costo').addEventListener('blur', function() {
            let value = parseFloat(this.value);
            if (!isNaN(value) && value >= 0) {
                this.value = value.toFixed(2);
            }
        });
        
        // Manejar cambios de tamaño de ventana
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('open');
            }
        });
        
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
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Enfocar en el primer campo
            setTimeout(() => {
                document.getElementById('codigo').focus();
            }, 500);
            
            // Cerrar panel de temas al hacer clic en overlay
            document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
            
            // Cerrar panel de temas con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && themePanelOpen) {
                    cerrarPanelTemas();
                }
            });
            
            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

// Mostrar SweetAlert si hay error de categoría obligatoria
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si hay un mensaje de error específico de categoría obligatoria
    const errorAlert = document.querySelector('.alert-danger');
    if (errorAlert) {
        const errorText = errorAlert.textContent || errorAlert.innerText;
        
        // Detectar si el error menciona "La categoría es obligatoria"
        if (errorText.includes('La categoría es obligatoria') || 
            errorText.includes('categoría es obligatoria') ||
            errorText.includes('categoría obligatoria')) {
            
            // Ocultar la alerta normal
            errorAlert.style.display = 'none';
            
            // Mostrar SweetAlert específico para categoría obligatoria
            Swal.fire({
                title: 'Categoría Requerida',
                html: `
                    <div class="text-start">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Categoría Obligatoria</strong>
                        </div>
                        <p>El sistema requiere que seleccione una categoría para este servicio.</p>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Por favor:</strong><br>
                            1. Seleccione una categoría de la lista desplegable<br>
                            2. Si no hay categorías disponibles, créelas primero
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="categorias.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus me-1"></i>Crear Categorías
                            </a>
                            <button onclick="focusCategoria()" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-right me-1"></i>Seleccionar Categoría
                            </button>
                        </div>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
                confirmButtonColor: '#ffc107',
                allowOutsideClick: false,
                backdrop: 'rgba(0,0,0,0.7)',
                width: '500px',
                showCloseButton: true,
                customClass: {
                    popup: 'border-radius-10'
                },
                didOpen: () => {
                    // Enfocar en el campo de categoría después de un breve retraso
                    setTimeout(() => {
                        document.getElementById('categoria_id').focus();
                        document.getElementById('categoria_id').classList.add('is-invalid');
                    }, 300);
                }
            }).then(() => {
                // Restaurar la alerta original
                errorAlert.style.display = 'block';
                document.getElementById('categoria_id').focus();
            });
        }
    }
    
    // Enfocar en el primer campo
    setTimeout(() => {
        document.getElementById('codigo').focus();
    }, 500);
    
    // Cerrar panel de temas al hacer clic en overlay
    document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);
    
    // Cerrar panel de temas con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && themePanelOpen) {
            cerrarPanelTemas();
        }
    });
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Función para enfocar en la categoría
function focusCategoria() {
    const categoriaSelect = document.getElementById('categoria_id');
    categoriaSelect.focus();
    categoriaSelect.classList.add('is-invalid');
    
    // Cerrar SweetAlert si está abierto
    Swal.close();
}
    </script>
</body>
</html>