<?php
// editar_servicio.php - Windows 11 Dark Mode
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// ==================== VALIDACIÓN INICIAL CON SWEETALERT ====================
// Obtener ID del cliente a editar
$servicio_id = isset($_GET['id']) ? $_GET['id'] : '';

// Verificar si el ID está presente y no está vacío
if (empty($servicio_id)) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo...</title>
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
        <style>
            body { background: #1f1f1f; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: Arial, sans-serif; }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            title: "Error",
            text: "No se ha especificado un ID de Servicios válido.",
            icon: "error",
            background: "#1f1f1f",
            color: "#fff",
            confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
            confirmButtonColor: "#3085d6",
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            window.location.href = "servicios.php";
        });
        </script>
    </body>
    </html>';
    exit();
}

// Validar que el ID sea numérico
if (!is_numeric($servicio_id)) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo...</title>
        <link rel="stylesheet" href="css/sweetalert2.min.css">
        <script src="js/sweetalert211.js"></script>
        <style>
            body { background: #1f1f1f; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: Arial, sans-serif; }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            title: "Error",
            text: "El ID del Servicio debe ser un número válido.",
            icon: "error",
            background: "#1f1f1f",
            color: "#fff",
            confirmButtonText: "<i class=\'fas fa-check\'></i> Aceptar",
            confirmButtonColor: "#3085d6",
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            window.location.href = "servicios.php";
        });
        </script>
    </body>
    </html>';
    exit();
}
// ==================== FIN VALIDACIÓN INICIAL ====================

$servicio_id = $_GET['id'];

// Verificar si se recibió un ID de servicio
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: servicios.php');
    exit();
}

// Obtener información del usuario desde la base de datos para verificar rol
try {
    $db = Database::getConnection();
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
        // Guardar mensaje para SweetAlert en sesión
        $_SESSION['swal_no_privilegios'] = [
            'titulo' => 'Acceso Denegado',
            'mensaje' => 'Solo usuarios con roles de <strong>Administrador, Edito o Supervisor</strong> pueden editar servicios.',
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
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error al verificar permisos de usuario";
    header('Location: servicios.php');
    exit();
}

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

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

$tema_actual = $temas_windows[$tema_windows];

// Variables para el formulario
$error = '';
$success = '';
$servicio = null;
$categorias = [];

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
    
    // Obtener estadísticas para los badges del sidebar
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_total = "SELECT COUNT(*) as total FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_total);
    $stmt->execute();
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_categorias_total = "SELECT COUNT(*) as total FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_categorias_total);
    $stmt->execute();
    $total_categorias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_servicios_total = "SELECT COUNT(*) as total FROM clasif_serv";
    $stmt = $db->prepare($sql_servicios_total);
    $stmt->execute();
    $total_servicios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_usuarios_total = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_usuarios_total);
    $stmt->execute();
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener el servicio a editar
    $sql = "SELECT * FROM clasif_serv WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $servicio_id]);
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicio) {
        $error = "Servicio no encontrado";
    }
    
    // Obtener categorías para el select
    $sql_categorias = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE activo = 1 ORDER BY descripcion";
    $stmt_categorias = $db->query($sql_categorias);
    $categorias = $stmt_categorias->fetchAll();
    
    // Obtener uso del servicio para mostrar advertencia
    $sql_uso = "SELECT COUNT(*) as total FROM tbl_fact_detalle WHERE servicio_id = :servicio_id";
    $stmt_uso = $db->prepare($sql_uso);
    $stmt_uso->execute(['servicio_id' => $servicio_id]);
    $uso_servicio = $stmt_uso->fetch(PDO::FETCH_ASSOC);
    
    // Procesar el formulario si se envió
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $codigo = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria_id = $_POST['categoria_id'] ?? null;
        $costo = floatval($_POST['costo'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        if (empty($codigo)) {
            $error = "El código es obligatorio";
        } elseif (empty($descripcion)) {
            $error = "La descripción es obligatoria";
        } elseif ($costo <= 0) {
            $error = "El costo debe ser mayor a 0";
        } else {
            // Verificar si el código ya existe (excluyendo el servicio actual)
            $sql_check = "SELECT id FROM clasif_serv WHERE codigo = :codigo AND id != :id";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->execute(['codigo' => $codigo, 'id' => $servicio_id]);
            $existe = $stmt_check->fetch();
            
            if ($existe) {
                $error = "Ya existe un servicio con el código '$codigo'";
            } else {
                // Actualizar el servicio
                $sql_update = "UPDATE clasif_serv 
                               SET codigo = :codigo, 
                                   descripcion = :descripcion, 
                                   categoria_id = :categoria_id, 
                                   costo = :costo, 
                                   activo = :activo 
                               WHERE id = :id";
                
                $stmt_update = $db->prepare($sql_update);
                $result = $stmt_update->execute([
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                    'categoria_id' => $categoria_id,
                    'costo' => $costo,
                    'activo' => $activo,
                    'id' => $servicio_id
                ]);
                
                if ($result) {
                    $success = "Servicio actualizado correctamente";
                    
                    // Actualizar los datos del servicio en la variable
                    $servicio['codigo'] = $codigo;
                    $servicio['descripcion'] = $descripcion;
                    $servicio['categoria_id'] = $categoria_id;
                    $servicio['costo'] = $costo;
                    $servicio['activo'] = $activo;
                    
                    // Registrar en el histórico
                    $sql_historico = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                                      VALUES (:operacion, :descripcion, :usuario_id, :usuario_nombre, :ip_address)";
                    $stmt_historico = $db->prepare($sql_historico);
                    $stmt_historico->execute([
                        'operacion' => 'EDITAR_SERVICIO',
                        'descripcion' => "Servicio {$codigo} actualizado",
                        'usuario_id' => $_SESSION['usuario_id'],
                        'usuario_nombre' => $_SESSION['usuario_nombre'],
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                } else {
                    $error = "Error al actualizar el servicio";
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error al cargar editar servicio: " . $e->getMessage());
    $error = "Error al cargar los datos del servicio";
}

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Estadísticas para el sidebar
$estadisticas = [];
$sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
$stmt = $db->query($sql_total);
$estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Servicio - SISFACT PDL Visiones</title>
	<link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
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
            border-right: 1px solid var(--win-border_color);
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
            border-bottom: 1px solid var(--win-border_color);
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
            font-weight: 600;
            transition: var(--win-transition);
        }

        .win-nav-link:hover .win-nav-badge {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
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

        /* Estilos para contenido */
        .win-main-content .card {
            background: var(--win-bg-secondary);
            border: 1px solid var(--win-border_color);
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
            border-bottom: 1px solid var(--win-border_color);
            padding: 1rem 1.25rem;
        }

        .win-main-content .card-body {
            padding: 1.25rem;
            color: var(--win-text-primary);
        }

        /* Formularios */
        .win-main-content .form-control,
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border_color);
            color: var(--win-text-primary);
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
        }

        .win-main-content .form-check-input:checked {
            background-color: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
        }

        .win-main-content .btn-primary {
            background: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-primary:hover {
            background: color-mix(in srgb, var(--win-accent) 90%, black);
            border-color: color-mix(in srgb, var(--win-accent) 90%, black);
        }

        .win-main-content .btn-outline-primary {
            color: var(--win-accent);
            border-color: var(--win-accent);
        }

        .win-main-content .btn-outline-primary:hover {
            background: var(--win-accent);
            color: white;
        }

        .win-main-content .border-bottom {
            border-bottom-color: var(--win-border_color) !important;
        }

        .win-main-content .text-muted {
            color: var(--win-text-secondary) !important;
        }

        .win-main-content .bg-light {
            background-color: var(--win-bg-tertiary) !important;
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
            position: relative;
            z-index: 1001;
        }

        .win-quick-action:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        /* Quick Actions Expandibles */
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
            z-index: 1002;
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

        /* Formularios */
        .win-main-content .form-control,
        .win-main-content .form-select {
            background-color: var(--win-bg-tertiary);
            border-color: var(--win-border-color);
            color: var(--win-text-primary);
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
        }



/* Hacer más visible el texto cuando el campo está enfocado */
.form-control:focus + .form-text {
    color: var(--win-accent) !important;
    opacity: 1 !important;
    font-weight: 500;
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
            }
        }

        @media (max-width: 768px) {
            .win-main-content {
                padding: 16px;
            }
            
            .win-nav-search {
                display: none;
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
            background: var(--win-border_color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--win-accent);
        }
/* Estilos para los textos de ayuda del formulario */
.win-main-content .form-text {
    color: var(--win-text-secondary) !important;
    font-size: 0.85rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.win-main-content .text-muted {
    color: var(--win-text-secondary) !important;
}

.win-main-content .alert .text-muted {
    color: #664d03 !important; /* Para mantener contraste en alertas */
}

/* Estilos para información del servicio */
.win-main-content .info-item {
    margin-bottom: 0.5rem;
}

.win-main-content .info-label {
    display: block;
    font-size: 0.75rem;
    color: var(--win-text-secondary);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.125rem;
}

.win-main-content .info-value {
    display: block;
    color: var(--win-text-primary);
    font-size: 1rem;
    font-weight: 500;
}

/* Estilos para el select de facturas */
.win-main-content select option {
    background-color: var(--win-bg-secondary);
    color: var(--win-text-primary);
    padding: 8px;
}

.win-main-content select option:checked {
    background-color: var(--win-accent);
    color: white;
}

.win-main-content select option:hover {
    background-color: var(--win-bg-tertiary);
}

/* Estilos para el modal de Windows */
.win-modal {
    background: var(--win-bg-secondary);
    border: 1px solid var(--win-border_color);
    border-radius: var(--win-radius);
    color: var(--win-text-primary);
}

.win-modal .swal2-title {
    color: var(--win-text-primary);
}

.win-modal .swal2-content {
    color: var(--win-text-primary);
}

.win-modal .swal2-confirm {
    background: var(--win-accent) !important;
    border-color: var(--win-accent) !important;
}
/* Estilos para el combo de categorías */
.form-select option[disabled][selected] {
    color: #6c757d !important;
    font-style: italic;
}

.form-select:valid:not([value=""]) {
    border-color: var(--win-accent);
    background-color: var(--win-bg-tertiary);
}

.form-select:invalid {
    border-color: #dc3545;
    background-color: rgba(220, 53, 69, 0.1);
}

/* Estilo para información de categoría seleccionada */
#categoriaInfo {
    background: rgba(var(--win-accent-rgb, 0, 120, 212), 0.1);
    border-left-color: var(--win-accent);
    margin-top: 8px;
}

#categoriaInfo i {
    color: var(--win-accent);
}

/* Indicador visual de selección válida */
.categoria-valida {
    border-color: #28a745 !important;
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.categoria-invalida {
    border-color: #dc3545 !important;
    background-color: rgba(220, 53, 69, 0.1) !important;
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


        [data-theme="light"] {
            --win-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Placeholders personalizados para tema dark */
        [data-theme="dark"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="dark"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders personalizados para tema light */
        [data-theme="light"] ::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] :-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] ::-ms-input-placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para formularios específicos */
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .form-control::placeholder,
        [data-theme="light"] .form-select::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para el buscador de navbar */
        [data-theme="dark"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .win-nav-search input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        /* Placeholders para search-box en tarjetas */
        [data-theme="dark"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
        }
        
        [data-theme="light"] .search-box input::placeholder {
            color: var(--win-text-secondary) !important;
            opacity: 0.7 !important;
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

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <!-- Botón hamburguesa para móvil -->
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48" style="vertical-align: middle; margin-right: 8px;">
            <span style="color: var(--win-text-primary);">EDITAR SERVICIOS - SISFACT PDL Visiones</span>
        </div>
        
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
                <a href="categorias.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-edit"></i>
                    <span class="win-nav-text">Editar Servicio:</span>
                    <span class="win-nav-badge"><?php echo htmlspecialchars($servicio['codigo']); ?></span>
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
        <?php if ($error && $error !== 'Servicio no encontrado'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($uso_servicio) && $uso_servicio['total'] > 0): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Servicio en Uso</h6>
                        <p class="mb-0">
                            Este servicio está siendo utilizado en <strong><?php echo $uso_servicio['total']; ?> factura(s)</strong>.
                            Si necesita eliminarlo, considere desactivarlo primero.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-edit me-2" style="color: var(--win-accent);"></i>Editar Servicio
                </h1>
                <p class="text-muted mb-0">Actualice la información del servicio - <?php echo $mes_actual_es . ' ' . date('Y'); ?></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="servicios.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>Volver a Servicios
                </a>
            </div>
        </div>

        <?php if (!$servicio && $error === 'Servicio no encontrado'): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h4 class="mb-3">Servicio no encontrado</h4>
                    <p class="text-muted mb-4">El servicio que intenta editar no existe o ha sido eliminado.</p>
                    <a href="servicios.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Volver a Servicios
                    </a>
                </div>
            </div>
        <?php elseif ($servicio): ?>
            <!-- Formulario de edición -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="m-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-edit me-1"></i>Información del Servicio
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formEditarServicio">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código * (Hasta 20 Caracteres)</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                               value="<?php echo htmlspecialchars($servicio['codigo']); ?>" 
                                               maxlength="20">
                                        <div class="form-text">Código único del servicio (ej: CR01, FS01)</div>
                                    </div>
                                    
<div class="col-md-6 mb-3">
    <label for="categoria_id" class="form-label">Categoría *</label>
    <select class="form-select" id="categoria_id" name="categoria_id">
        <option value="" selected>Seleccione una categoría</option>
        <?php foreach ($categorias as $categoria): ?>
            <option value="<?php echo $categoria['id']; ?>" 
                <?php echo $servicio['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>
                data-codigo="<?php echo htmlspecialchars($categoria['codigo']); ?>"
                data-descripcion="<?php echo htmlspecialchars($categoria['descripcion']); ?>">
                <?php echo htmlspecialchars($categoria['codigo'] . ' - ' . $categoria['descripcion']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text" id="categoriaInfo">
        <i class="fas fa-info-circle me-1"></i>
        <span id="categoriaSeleccionada">
            <?php 
            if ($servicio['categoria_id']) {
                // Mostrar la categoría actual seleccionada
                foreach ($categorias as $cat) {
                    if ($cat['id'] == $servicio['categoria_id']) {
                        echo "Categoría seleccionada: <strong>" . htmlspecialchars($cat['codigo']) . 
                             " - " . htmlspecialchars($cat['descripcion']) . "</strong>";
                        break;
                    }
                }
            } else {
                echo "Seleccione una categoría para este servicio";
            }
            ?>
        </span>
    </div>
</div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="descripcion" class="form-label">Descripción * (Hasta 200 Caracteres)</label>
                                        <input type="text" class="form-control" id="descripcion" name="descripcion" 
                                               value="<?php echo htmlspecialchars($servicio['descripcion']); ?>" 
                                               maxlength="200">
                                        <div class="form-text">Descripción detallada del servicio</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="costo" class="form-label">Costo ($) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="costo" name="costo" 
                                                   step="0.01" min="0.01" 
                                                   value="<?php echo number_format($servicio['costo'], 2, '.', ''); ?>" 
                                                   >
                                        </div>
                                        <div class="form-text">Costo del servicio en pesos cubanos</div>
                                    </div>
                                    
<div class="col-md-6 mb-3">
    <label class="form-label">Estado del Servicio</label>
    
    <!-- Tarjeta de estado -->
    <div class="estado-card <?php echo $servicio['activo'] ? 'activo' : 'inactivo'; ?>" id="estadoCard">
        <div class="d-flex align-items-center">
            <div class="form-check form-switch me-3">
                <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                       <?php echo $servicio['activo'] ? 'checked' : ''; ?>
                       onchange="toggleEstadoServicio(this.checked)">
            </div>
            <div>
                <h6 class="mb-1" id="estadoTitulo" style="color: <?php echo $servicio['activo'] ? '#4caf50' : '#dc3545'; ?>;">
                    <?php echo $servicio['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                </h6>
                <p class="mb-0 small" id="estadoMensaje">
                    <?php echo $servicio['activo'] ? 
                           '<i class="fas fa-check me-1"></i>Disponible para facturación' : 
                           '<i class="fas fa-ban me-1"></i>No disponible para facturación'; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Información del impacto -->
    <div class="win-form-help <?php echo $servicio['activo'] ? 'success' : 'warning'; ?>" id="estadoImpacto">
        <i class="fas <?php echo $servicio['activo'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <span id="impactoTexto">
            <?php echo $servicio['activo'] ? 
                   'Los Facturadores pueden seleccionar este servicio en nuevas facturas.' : 
                   'Los Facturadores no podrán seleccionar este servicio en nuevas facturas.'; ?>
        </span>
    </div>
</div>
                                </div>
                                
<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
    <div>
        <a href="servicios.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Volver sin guardar
        </a>
    </div>
    
    <div class="d-flex gap-2">
        <!-- Botón de eliminar con advertencia visual -->
        <button type="button" class="btn btn-outline-danger" 
                onclick="confirmarEliminacion()"
                title="Eliminar permanentemente este servicio"
                data-bs-toggle="tooltip">
            <i class="fas fa-trash me-1"></i>Eliminar
        </button>
        
        <!-- Botón de guardar -->
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-1"></i>Guardar
        </button>
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
                <i class="fas fa-info-circle me-1"></i>Información del Servicio
            </h6>
        </div>
        <div class="card-body">
            <!-- Información en columnas -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="info-item">
                        <span class="info-label">Código:</span>
                        <span class="info-value"><?php echo htmlspecialchars($servicio['codigo']); ?></span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-item">
                        <span class="info-label">Costo:</span>
                        <span class="info-value">$<?php echo isset($servicio['costo']) ? number_format($servicio['costo'], 2) : '0.00'; ?></span>
                    </div>
                </div>
<div class="col-6">
    <div class="info-item">
        <span class="info-label">Estado:</span>
        <span class="info-value">
            <span class="badge <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'bg-success' : 'bg-danger'; ?>" 
                  id="estadoBadge">
                <i class="fas <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'fa-check' : 'fa-ban'; ?> me-1"></i>
                <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 'Activo' : 'Inactivo'; ?>
            </span>
        </span>
        <small class="text-muted d-block mt-1" id="estadoDetalle">
            <?php echo (isset($servicio['activo']) && $servicio['activo']) ? 
                   'Disponible para facturación' : 
                   'No disponible para facturación'; ?>
        </small>
    </div>
</div>
                <div class="col-6">
                    <div class="info-item">
                        <span class="info-label">ID:</span>
                        <span class="info-value">#<?php echo $servicio['id']; ?></span>
                    </div>
                </div>
            </div>
            <!-- Categoría del servicio con enlace y tooltip -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 style="color: var(--win-text-secondary) !important; margin-bottom: 0;">
                        <i class="fas fa-tag me-1"></i>Categoría
                    </h6>
                    <?php if ($servicio['categoria_id']): ?>
    <div class="btn-group" role="group">
        <a href="editar_categoria.php?id=<?php echo $servicio['categoria_id']; ?>" 
           class="btn btn-sm btn-outline-warning" 
           data-bs-toggle="tooltip" 
           data-bs-placement="top" 
           data-bs-title="Editar esta categoría"
           title="Editar esta categoría">
            <i class="fas fa-edit fa-xs"></i>
        </a>
        <a href="categorias.php" 
           class="btn btn-sm btn-outline-secondary" 
           data-bs-toggle="tooltip" 
           data-bs-placement="top" 
           data-bs-title="Ver todas las categorías"
           title="Ver todas las categorías">
            <i class="fas fa-list fa-xs"></i>
        </a>
    </div>
<?php endif; ?>
                </div>
                
                <?php 
                // Obtener información de la categoría
                $categoria_info_text = "Sin categoría asignada";
                $categoria_codigo = "";
                $categoria_id = null;
                
                if ($servicio['categoria_id']) {
                    try {
                        $sql_cat_info = "SELECT id, codigo, descripcion FROM clasif_cat_de_serv WHERE id = :id";
                        $stmt_cat_info = $db->prepare($sql_cat_info);
                        $stmt_cat_info->execute(['id' => $servicio['categoria_id']]);
                        $categoria_info = $stmt_cat_info->fetch(PDO::FETCH_ASSOC);
                        
                        if ($categoria_info) {
                            $categoria_id = $categoria_info['id'];
                            $categoria_codigo = $categoria_info['codigo'];
                            $categoria_info_text = "<strong>" . htmlspecialchars($categoria_info['codigo']) . "</strong> - " . 
                                                   htmlspecialchars($categoria_info['descripcion']);
                        }
                    } catch (Exception $e) {
                        $categoria_info_text = "Error al cargar categoría";
                    }
                }
                ?>
                
                <div class="p-3 rounded d-flex justify-content-between align-items-center" style="background: var(--win-bg-tertiary) !important;">
                    <span><?php echo $categoria_info_text; ?></span>
                    <?php if ($categoria_codigo): ?>
                        <a href="editar_categoria.php?id=<?php echo $categoria_id; ?>" 
                           class="badge bg-primary text-decoration-none"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="top" 
                           data-bs-title="Editar esta categoría"
                           title="Editar esta categoría">
                            <?php echo htmlspecialchars($categoria_codigo); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
            </div>
            <!-- Descripción completa -->
            <div class="mb-4">
                <h6 class="text-muted mb-2">Descripción</h6>
                <div class="p-1 bg-light rounded" style="background: var(--win-bg-tertiary) !important;">
                    <?php echo htmlspecialchars($servicio['descripcion']); ?>
                </div>
            </div>

<?php
// Obtener facturas que usan este servicio
$total_facturas = 0;
$facturas = [];

try {
    $db = Database::getConnection();
    
    // 1. Primero contamos cuántas facturas usan este servicio
    $sql_count = "SELECT COUNT(DISTINCT factura_id) as total 
                 FROM tbl_fact_detalle 
                 WHERE servicio_id = :servicio_id";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute(['servicio_id' => $servicio['id']]);
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_facturas = $count_result['total'] ?? 0;
    
    // 2. Si hay facturas, obtenemos la información básica (modificado según dump)
    if ($total_facturas > 0) {
        // Consulta corregida según el dump de MySQL
        $sql_facturas = "SELECT DISTINCT f.id, f.no_fact, f.fecha_emision, c.nombre as cliente_nombre
                        FROM tbl_fact_detalle fd
                        INNER JOIN tbl_fact f ON fd.factura_id = f.id
                        LEFT JOIN clasif_clientes c ON f.cliente_id = c.id
                        WHERE fd.servicio_id = :servicio_id
                        ORDER BY f.fecha_emision DESC, f.no_fact DESC
                        ";
        
        $stmt_facturas = $db->prepare($sql_facturas);
        $stmt_facturas->execute(['servicio_id' => $servicio['id']]);
        $facturas_data = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($facturas_data as $factura) {
            $facturas[] = [
                'id' => $factura['id'],
                'numero' => $factura['no_fact'],
                'fecha' => isset($factura['fecha_emision']) ? date('d/m/Y', strtotime($factura['fecha_emision'])) : 'Sin fecha',
                'cliente' => $factura['cliente_nombre'] ?? 'Sin cliente'
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Error al obtener facturas para servicio {$servicio['id']}: " . $e->getMessage());
}
?>
            <!-- Combo de números de factura -->
            <?php if ($total_facturas > 0 && !empty($facturas)): ?>
                <div class="mb-4">
					<h6 class="text-muted mb-2">Facturas Incluyen el Servicio</h6>
                    <div class="input-group mb-3">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
<select class="form-select" id="facturasCombo">
    <option value="" selected>Seleccione una factura</option>
    <?php 
    // Ordenar las facturas por fecha (ya vienen ordenadas de la consulta)
    // Pero si quieres asegurarte, puedes usar usort
    usort($facturas, function($a, $b) {
        return strtotime($b['fecha_original'] ?? '') - strtotime($a['fecha_original'] ?? '');
    });
    
    foreach ($facturas as $factura): 
    ?>
        <?php 
        $texto_opcion = $factura['numero'];
        
        // Agregar fecha siempre primero para que sea lo más visible
        if (!empty($factura['fecha']) && $factura['fecha'] !== 'Sin fecha') {
            $texto_opcion = $factura['fecha'] . " - " . $texto_opcion;
        }
        
        // Agregar cliente si está disponible
        if (!empty($factura['cliente']) && $factura['cliente'] !== 'Sin cliente') {
            $cliente_corto = strlen($factura['cliente']) > 20 ? substr($factura['cliente'], 0, 20) . '...' : $factura['cliente'];
            $texto_opcion .= " - " . $cliente_corto;
        }
        ?>
        
        <option value="<?php echo $factura['id']; ?>" 
                title="Fecha: <?php echo htmlspecialchars($factura['fecha']); ?> - Cliente: <?php echo htmlspecialchars($factura['cliente']); ?>">
            <?php echo htmlspecialchars($texto_opcion); ?>
        </option>
    <?php endforeach; ?>
</select>
                        <button class="btn btn-outline-primary" type="button" onclick="verFacturaActual()" title="Abrir factura seleccionada">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                    
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Este servicio está incluido en<span class="badge bg-light ms-2"><?php echo $total_facturas; ?></span> factura(s).
                    </div>
                    
                    <!-- Botón para ver todas las facturas -->
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="mostrarTodasFacturas(<?php echo $servicio['id']; ?>, <?php echo $total_facturas; ?>)">
                            <i class="fas fa-list me-1"></i>Ver todas las facturas (<?php echo $total_facturas; ?>)
                        </button>
                    </div>
                </div>
            <?php elseif ($total_facturas > 0 && empty($facturas)): ?>
                <!-- Caso especial: sabemos que hay facturas pero no pudimos obtener detalles -->
                <div class="mb-4">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-file-invoice me-1"></i>
                        Facturas que incluyen este servicio
                        <span class="badge bg-warning ms-2"><?php echo $total_facturas; ?></span>
                    </h6>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Este servicio está en uso en <?php echo $total_facturas; ?> factura(s).
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="verFacturasServicio(<?php echo $servicio['id']; ?>)">
                            Ver lista completa
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Este servicio no está siendo utilizado en ninguna factura actualmente.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scripts mejorados -->
<script>
    function verFacturaSeleccionada(facturaId) {
        if (facturaId) {
            window.open('ver_factura.php?id=' + facturaId, '_blank');
        }
    }
    
    function verFacturaActual() {
        const select = document.getElementById('facturasCombo');
        if (select && select.value) {
            window.open('ver_factura.php?id=' + select.value, '_blank');
        } else {
            Swal.fire({
                title: 'Seleccione una factura',
                text: 'Por favor, seleccione una factura de la lista primero.',
                icon: 'info',
                confirmButtonColor: '#3085d6',
				 confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
				
            });
        }
    }
    
function mostrarTodasFacturas(servicioId, totalFacturas) {
    // Crear un modal con todas las facturas
    Swal.fire({
        title: `Todas las facturas (${totalFacturas})`,
        html: `
            <div class="text-start" style="color: var(--win-text-primary);">
                <p style="color: var(--win-text-primary);">Cargando lista completa de facturas...</p>
                <div class="text-center py-3">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--win-accent);"></i>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        width: '800px',
        backdrop: true,
        allowOutsideClick: false,
        allowEscapeKey: true,
        customClass: {
            popup: 'win-modal',
            closeButton: 'win-modal-close',
            title: 'win-modal-title',
            htmlContainer: 'win-modal-content'
        }
    });
    
    // Usar el endpoint correcto
    fetch(`obtener_todas_facturas.php?servicio_id=${servicioId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.facturas && data.facturas.length > 0) {
                let html = `<div class="text-start" style="color: var(--win-text-primary);">
                    <p style="color: var(--win-text-primary); margin-bottom: 1rem;">El servicio está en <strong>${data.total}</strong> factura(s):</p>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover" id="tablaFacturasServicio">
                            <thead style="background-color: var(--win-bg-tertiary); color: var(--win-text-primary);">
                                <tr>
                                    <th style="border-color: var(--win-border_color);">No. Fact.</th>
                                    <th style="border-color: var(--win-border_color);">Fecha</th>
                                    <th style="border-color: var(--win-border_color);">Cliente</th>
                                    <th style="border-color: var(--win-border_color);">Acción</th>
                                </tr>
                            </thead>
                            <tbody style="background-color: var(--win-bg-secondary); color: var(--win-text-primary);">`;
                
                data.facturas.forEach(factura => {
                    html += `<tr style="border-color: var(--win-border_color);">
                        <td style="border-color: var(--win-border_color);"><strong>${factura.numero}</strong></td>
                        <td style="border-color: var(--win-border_color);">${factura.fecha}</td>
                        <td style="border-color: var(--win-border_color);">${factura.cliente}</td>
                        <td style="border-color: var(--win-border_color);">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="window.open('ver_factura.php?id=${factura.id}', '_blank')"
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="Ver detalles de la factura">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning ms-1" 
                                    onclick="window.open('editar_factura.php?id=${factura.id}', '_blank')"
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="Editar factura">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                
                html += `</tbody>
                        </table>
                    </div>`;
                
                if (data.total > data.facturas.length) {
                    html += `<div class="alert mt-2" style="background-color: var(--win-accent-light); color: var(--win-text-primary); border-color: var(--win-accent);">
                        <i class="fas fa-info-circle me-2" style="color: var(--win-accent);"></i>
                        Mostrando ${data.facturas.length} de ${data.total} facturas (últimas 50)
                    </div>`;
                }
                
                html += `</div>`;
                
                Swal.fire({
                    title: `<div style="color: var(--win-text-primary);">Facturas del servicio <span style="color: var(--win-accent);">(${data.total})</span></div>`,
                    html: html,
                    width: '850px',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fas fa-times me-1"></i> Cerrar',
                    showCancelButton: false,
                    showCloseButton: true,
                    backdrop: true,
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                    confirmButtonColor: '#6c757d',
                    customClass: {
                        popup: 'win-modal',
                        header: 'win-modal-header',
                        title: 'win-modal-title',
                        htmlContainer: 'win-modal-content',
                        confirmButton: 'win-modal-confirm',
                        closeButton: 'win-modal-close'
                    },
                    didOpen: () => {
                        // Inicializar tooltips después de que el modal se abra
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll('#swal2-html-container [data-bs-toggle="tooltip"]'));
                        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl, {
                                container: '#swal2-html-container'
                            });
                        });
                        
                        // Aplicar estilos adicionales a la tabla
                        const tabla = document.querySelector('#swal2-html-container #tablaFacturasServicio');
                        if (tabla) {
                            // Estilos para las filas al pasar el mouse
                            const filas = tabla.querySelectorAll('tbody tr');
                            filas.forEach(fila => {
                                fila.addEventListener('mouseenter', function() {
                                    this.style.backgroundColor = 'var(--win-bg-tertiary)';
                                    this.style.transition = 'background-color 0.2s ease';
                                });
                                fila.addEventListener('mouseleave', function() {
                                    this.style.backgroundColor = '';
                                });
                            });
                        }
                    }
                });
            } else {
                Swal.fire({
                    title: 'Sin resultados',
                    text: data.message || 'No se encontraron facturas para este servicio.',
                    icon: 'info',
                    confirmButtonColor: '#3085d6',
                    showCloseButton: true,
                    allowOutsideClick: false,
                    backdrop: true
                });
            }
        })
        .catch(error => {
            console.error('Error fetching facturas:', error);
            Swal.fire({
                title: 'Error',
                text: 'No se pudieron cargar las facturas. Intente nuevamente.',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                showCloseButton: true,
                allowOutsideClick: false,
                backdrop: true
            });
        });
}
	
	function verFacturasServicio(servicioId) {
        // Redirigir a página especial para ver facturas del servicio
        window.open(`facturas_por_servicio.php?id=${servicioId}`, '_blank');
    }
</script>

<!-- Script adicional -->
<script>
function mostrarListaFacturas(servicioId) {
    Swal.fire({
        title: 'Facturas con este servicio',
        html: `
            <div class="text-start">
                <p>Obteniendo lista de facturas...</p>
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false
    });
    
    // Realizar petición AJAX
    $.ajax({
        url: 'obtener_facturas_servicio.php',
        type: 'GET',
        data: { id: servicioId },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                let html = '<div class="text-start">';
                html += `<p>El servicio está en ${data.total} factura(s):</p>`;
                html += '<div class="list-group" style="max-height: 300px; overflow-y: auto;">';
                
                data.facturas.forEach(factura => {
                    html += `<a href="ver_factura.php?id=${factura.id}" target="_blank" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Factura #${factura.numero}</h6>
                            <small>${factura.fecha}</small>
                        </div>
                        <p class="mb-1 small text-muted">${factura.cliente || 'Cliente no especificado'}</p>
                    </a>`;
                });
                
                html += '</div></div>';
                
                Swal.fire({
                    title: 'Facturas encontradas',
                    html: html,
                    width: '600px',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fas fa-close me-2"></i> Cerrar',
                    showCancelButton: false,
                    confirmButtonColor: '#3085d6'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'No se pudieron obtener las facturas',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        },
        error: function(xhr, status, error) {
            // Manejar error de parseo JSON
            if (xhr.status === 200) {
                // El servidor respondió pero no con JSON válido
                Swal.fire({
                    title: 'Error de formato',
                    text: 'La respuesta del servidor no es válida. Verifica que el archivo PHP solo envíe JSON.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                Swal.fire({
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor: ' + error,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        }
    });
}
</script>
            </div>
        <?php endif; ?>
    </main>

    <!-- Scripts -->
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
                sidebar.classList.toggle('open');
            } else {
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
                localStorage.setItem('tema_windows', theme);
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
                localStorage.setItem('color_accent', color);
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

        
// Confirmar eliminación CON VERIFICACIÓN LOCAL
function confirmarEliminacion() {
    const uso = <?php echo $uso_servicio['total'] ?? 0; ?>;
    const servicioCodigo = "<?php echo htmlspecialchars($servicio['codigo'] ?? ''); ?>";
    const servicioDescripcion = "<?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?>";
    const servicioId = <?php echo $servicio_id; ?>;
    
    if (uso > 0) {
        // Está en uso, mostrar opciones con SweetAlert
        Swal.fire({
            title: 'Servicio en Uso',
            html: `
                <div class="text-start">
                    <p>El servicio <strong>"${servicioCodigo}"</strong> está siendo utilizado en <strong>${uso} factura(s)</strong>.</p>
                    <div class="alert alert-warning p-3 mt-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Advertencia:</strong> Eliminar este servicio afectará las facturas existentes.
                    </div>
                    <p class="mb-0">¿Qué desea hacer?</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            showConfirmButton: true,
            showCloseButton: true,
            confirmButtonText: '<i class="fas fa-trash me-2"></i> Eliminar Servicio',
            denyButtonText: '<i class="fas fa-cancel me-2"></i> Desactivar Servicio',
            cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
            confirmButtonColor: '#d33',
            denyButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            backdrop: 'rgba(0,0,0,0.8)',
            footer: '<span class="text-muted small">Recomendación: Desactive el servicio para nuevas facturas</span>'
        }).then((result) => {
            if (result.isConfirmed) {
                // ELIMINAR SERVICIO - Confirmación adicional
                confirmarEliminacionForzada(servicioId, servicioCodigo, uso);
            } else if (result.isDenied) {
                // Desactivar servicio
                desactivarServicio(servicioId, servicioCodigo);
            }
        });
    } else {
        // No está en uso, proceder con confirmación normal
        Swal.fire({
            title: '¿Está seguro?',
            html: `
                <div class="text-start">
                    <p>Esta acción eliminará permanentemente el servicio:</p>
                    <div class="alert alert-warning p-3">
                        <strong>${servicioCodigo}</strong><br>
                        ${servicioDescripcion}
                    </div>
                    <p class="mb-0"><strong>Esta acción no se puede deshacer.</strong></p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<i class="fas fa-trash me-2"></i> Sí, eliminar',
            cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
            backdrop: 'rgba(0,0,0,0.7)'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Eliminando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                window.location.href = 'eliminar_servicio.php?id=' + servicioId;
            }
        });
    }
}

// Función para confirmar eliminación forzada (cuando está en uso)
function confirmarEliminacionForzada(servicioId, servicioCodigo, usoCount) {
    Swal.fire({
        title: 'Confirmar Eliminación',
        html: `
            <div class="text-start">
                <div class="alert alert-danger p-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>ADVERTENCIA:</strong> Este servicio está en <strong>${usoCount} factura(s)</strong>.
                </div>
                <p>¿Está seguro de eliminar el servicio <strong>"${servicioCodigo}"</strong>?</p>
                <p class="text-danger small">
                    <i class="fas fa-info-circle me-1"></i>
                    Las facturas existentes mantendrán los datos históricos pero perderán la referencia a este servicio.
                </p>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i> Sí, eliminar de todos modos',
        cancelButtonText: '<i class="fas fa-cancel me-2"></i> Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        backdrop: 'rgba(0,0,0,0.8)'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando...',
                text: 'Eliminando servicio del sistema',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirigir a eliminar_servicio.php
            window.location.href = 'eliminar_servicio.php?id=' + servicioId + '&forzar=1';
        }
    });
}

// Función para desactivar servicio
function desactivarServicio(servicioId, servicioCodigo) {
    // Enviar formulario con activo=0
    document.getElementById('activo').checked = false;
    document.getElementById('formEditarServicio').submit();
}

// Función para mostrar detalles de uso (opcional - mantengo por si la necesitas)
function mostrarDetallesUso(usoCount, servicioId) {
    Swal.fire({
        title: 'Detalles de Uso',
        html: `
            <div class="text-start">
                <p>Este servicio está siendo utilizado en <strong>${usoCount} factura(s)</strong>.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>Para eliminar este servicio, primero debe:</small>
                    <ol class="mt-2 mb-0">
                        <li>Desactivar el servicio (para nuevas facturas)</li>
                        <li>O modificar las facturas existentes que lo contienen</li>
                        <li>Contactar al administrador si necesita eliminarlo completamente</li>
                    </ol>
                </div>
                <p class="mb-0 text-muted small">Servicio ID: ${servicioId}</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        confirmButtonColor: '#0d6efd',
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-file-invoice me-2"></i> Ver Facturas',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            // Redirigir a facturas con filtro por este servicio
            window.location.href = 'facturas.php?servicio=' + servicioId;
        }
    });
}
// Validar formulario con mejor feedback
document.getElementById('formEditarServicio').addEventListener('submit', function(e) {
    const codigo = document.getElementById('codigo').value.trim();
    const descripcion = document.getElementById('descripcion').value.trim();
    const costo = document.getElementById('costo').value;
    const categoria_id = document.getElementById('categoria_id').value;
    const estado = document.getElementById('activo').checked;
    
    // Array para almacenar errores
    const errores = [];
    
    // Validar código
    if (!codigo) {
        errores.push({
            campo: 'codigo',
            mensaje: 'El campo <strong>Código</strong> es obligatorio.',
            ayuda: 'El código debe ser único en el sistema (ej: CR01, FS01)'
        });
    }
    
    // Validar descripción
    if (!descripcion) {
        errores.push({
            campo: 'descripcion',
            mensaje: 'El campo <strong>Descripción</strong> es obligatorio.',
            ayuda: 'Proporcione una descripción clara del servicio'
        });
    }
    
    // Validar costo
    if (!costo || parseFloat(costo) <= 0) {
        errores.push({
            campo: 'costo',
            mensaje: 'El <strong>Costo</strong> debe ser mayor a 0.',
            ayuda: 'Ejemplos válidos: 10.00, 25.50, 100.00'
        });
    }
    
    // Validar categoría
    if (!categoria_id) {
        errores.push({
            campo: 'categoria_id',
            mensaje: 'Debe seleccionar una <strong>Categoría</strong>.',
            ayuda: 'El servicio debe pertenecer a una categoría existente'
        });
    }
    
    // Si hay errores, mostrarlos
    if (errores.length > 0) {
        e.preventDefault();
        
        // Mostrar el primer error
        const primerError = errores[0];
        
        Swal.fire({
            title: 'Campos Requeridos',
            html: `
                <div class="text-start">
                    <p>${primerError.mensaje}</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        ${primerError.ayuda}
                    </div>
                    ${errores.length > 1 ? 
                        `<p class="text-danger small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            También hay ${errores.length - 1} error(es) más en el formulario.
                        </p>` : ''
                    }
                </div>
            `,
            icon: 'error',
            confirmButtonText: '<i class="fas fa-hand-point-right me-2"></i> Corregir',
            confirmButtonColor: '#dc3545'
        }).then(() => {
            // Enfocar el campo con error
            document.getElementById(primerError.campo).focus();
            
            // Resaltar visualmente todos los campos con error
            errores.forEach(error => {
                const campo = document.getElementById(error.campo);
                if (campo) {
                    campo.classList.add('animate__animated', 'animate__shakeX');
                    campo.style.borderColor = '#dc3545';
                    campo.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.25)';
                    
                    // Remover animación después de un tiempo
                    setTimeout(() => {
                        campo.classList.remove('animate__animated', 'animate__shakeX');
                    }, 1000);
                }
            });
        });
        return;
    }
    
    // Obtener información de la categoría seleccionada
    const categoriaSelect = document.getElementById('categoria_id');
    const categoriaSeleccionada = categoriaSelect.options[categoriaSelect.selectedIndex];
    const categoriaCodigo = categoriaSeleccionada.getAttribute('data-codigo') || '';
    const categoriaDescripcion = categoriaSeleccionada.getAttribute('data-descripcion') || '';
    
    // Mostrar resumen antes de guardar
    if (e.submitter && e.submitter.type === 'submit') {
        e.preventDefault();
        
        Swal.fire({
            title: 'Confirmar Cambios',
            html: `
                <div class="text-start">
                    <p>¿Está seguro de guardar los siguientes cambios?</p>
                    <div class="alert alert-info p-3">
                        <div class="row">
                            <div class="col-6">
                                <strong>Código:</strong><br>
                                <span class="badge bg-primary">${codigo}</span>
                            </div>
                            <div class="col-6">
                                <strong>Costo:</strong><br>
                                <span class="badge bg-success">$${parseFloat(costo).toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Descripción:</strong><br>
                                <small>${descripcion}</small>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Categoría:</strong><br>
                                <span class="badge bg-info">${categoriaCodigo}</span><br>
                                <small>${categoriaDescripcion}</small>
                            </div>
                            <div class="col-6">
                                <strong>Estado:</strong><br>
                                <span class="badge ${estado ? 'bg-success' : 'bg-danger'}">
                                    ${estado ? 'Activo' : 'Inactivo'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Esta acción actualizará el servicio en el sistema.
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save me-2"></i> Sí, Guardar',
            cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar animación de guardado
                Swal.fire({
                    title: 'Guardando Cambios',
                    html: `
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--win-accent);"></i>
                            </div>
                            <div class="progress" style="height: 6px; border-radius: 3px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     style="width: 100%; background-color: var(--win-accent);"></div>
                            </div>
                            <p class="mt-3">Actualizando información del servicio...</p>
                        </div>
                    `,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        // Simular progreso
                        let progress = 0;
                        const interval = setInterval(() => {
                            progress += 10;
                            if (progress >= 90) {
                                clearInterval(interval);
                            }
                        }, 100);
                    }
                });
                
                // Enviar formulario después de un breve delay
                setTimeout(() => {
                    document.getElementById('formEditarServicio').submit();
                }, 1500);
            }
        });
    }
});
        
		// Manejar cambios de tamaño de ventana
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('open');
            }
        });
        
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Formatear costo al perder foco
            const costoInput = document.getElementById('costo');
            if (costoInput) {
                costoInput.addEventListener('blur', function() {
                    let value = parseFloat(this.value);
                    if (!isNaN(value) && value >= 0) {
                        this.value = value.toFixed(2);
                    }
                });
            }
            
            // Mostrar advertencia si el servicio está en uso
            <?php if (isset($uso_servicio) && $uso_servicio['total'] > 0): ?>
            setTimeout(() => {
                Swal.fire({
                    title: 'Servicio en Uso',
                    html: `
                        <div class="text-start">
                            <p>Este servicio está siendo utilizado en <strong><?php echo $uso_servicio['total']; ?> factura(s)</strong>.</p>
                            <div class="alert alert-info p-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Se recomienda desactivarlo en lugar de eliminarlo para mantener la integridad de los datos.</small>
                            </div>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                    confirmButtonColor: '#0d6efd',
                    showCancelButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            }, 1000);
            <?php endif; ?>
        });
// Función para cambiar el estado del servicio
function toggleEstadoServicio(estaActivo) {
    const estadoCard = document.getElementById('estadoCard');
    const estadoTitulo = document.getElementById('estadoTitulo');
    const estadoMensaje = document.getElementById('estadoMensaje');
    const estadoBadge = document.getElementById('estadoBadge');
    const estadoDetalle = document.getElementById('estadoDetalle');
    const estadoImpacto = document.getElementById('estadoImpacto');
    const impactoTexto = document.getElementById('impactoTexto');
    const usoCount = <?php echo $uso_servicio['total'] ?? 0; ?>;
    
    // Animación de transición
    estadoCard.style.transition = 'all 0.3s ease';
    
    if (estaActivo) {
        // Cambiar a activo
        estadoCard.classList.remove('inactivo');
        estadoCard.classList.add('activo');
        
        estadoTitulo.textContent = 'ACTIVO';
        estadoTitulo.style.color = '#4caf50';
        
        estadoMensaje.innerHTML = '<i class="fas fa-check me-1 text-success"></i>Disponible para facturación';
        
        estadoBadge.className = 'badge bg-success animate__animated animate__bounceIn';
        estadoBadge.innerHTML = '<i class="fas fa-check me-1"></i>Activo';
        
        estadoDetalle.className = 'text-success d-block mt-1';
        estadoDetalle.innerHTML = '<i class="fas fa-check-circle me-1"></i>Disponible para facturación';
        
        // Actualizar panel de impacto
        estadoImpacto.className = 'win-form-help success';
        estadoImpacto.innerHTML = '<i class="fas fa-check-circle"></i><span id="impactoTexto">Los clientes pueden seleccionar este servicio en nuevas facturas.</span>';
        
        // Efecto visual
        reproducirEfectoSonido('success');
        
        // Mostrar toast de éxito
        mostrarToastEstado('success', 'Servicio Activado', 'Ahora está disponible para facturación');
        
    } else {
        // Cambiar a inactivo
        estadoCard.classList.remove('activo');
        estadoCard.classList.add('inactivo');
        
        estadoTitulo.textContent = 'INACTIVO';
        estadoTitulo.style.color = '#dc3545';
        
        estadoMensaje.innerHTML = '<i class="fas fa-ban me-1 text-danger"></i>No disponible para facturación';
        
        estadoBadge.className = 'badge bg-danger animate__animated animate__headShake';
        estadoBadge.innerHTML = '<i class="fas fa-ban me-1"></i>Inactivo';
        
        estadoDetalle.className = 'text-danger d-block mt-1';
        estadoDetalle.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>No disponible para facturación';
        
        // Actualizar panel de impacto con advertencia si está en uso
        if (usoCount > 0) {
            estadoImpacto.className = 'win-form-help warning';
            estadoImpacto.innerHTML = `<i class="fas fa-exclamation-triangle"></i>
                <span id="impactoTexto">Atención: Este servicio está en ${usoCount} factura(s). Al desactivarlo, no estará disponible para nuevas facturas.</span>`;
        } else {
            estadoImpacto.className = 'win-form-help warning';
            estadoImpacto.innerHTML = '<i class="fas fa-exclamation-circle"></i><span id="impactoTexto">Los clientes no podrán seleccionar este servicio en nuevas facturas.</span>';
        }
        
        // Efecto visual
        reproducirEfectoSonido('warning');
        
        // Mostrar toast de advertencia
        mostrarToastEstado('warning', 'Servicio Desactivado', 'Ya no estará disponible para nuevas facturas');
    }
    
    // Remover clases de animación después de un tiempo
    setTimeout(() => {
        estadoBadge.classList.remove('animate__animated', 'animate__bounceIn', 'animate__headShake');
    }, 1000);
    
    // Si hay uso, mostrar alerta adicional
    if (!estaActivo && usoCount > 0) {
        setTimeout(() => {
            Swal.fire({
                title: 'Servicio en Uso',
                html: `
                    <div class="text-start">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atención:</strong> Este servicio está en <strong>${usoCount} factura(s)</strong>.
                        </div>
                        <p class="mb-0">Los clientes no podrán seleccionarlo en nuevas facturas, pero las existentes mantendrán el servicio.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
                confirmButtonColor: '#ff9800',
                showCancelButton: false
            });
        }, 500);
    }
}

// Función para mostrar notificaciones toast
function mostrarToastEstado(tipo, titulo, mensaje) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: tipo,
        title: titulo,
        text: mensaje,
        iconColor: tipo === 'success' ? '#4caf50' : '#ff9800'
    });
}

// Función para efectos de sonido (opcional)
function reproducirEfectoSonido(tipo) {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        if (tipo === 'success') {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
            
        } else if (tipo === 'warning') {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(349.23, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(293.66, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        }
    } catch (e) {
        console.log('Audio no soportado:', e);
    }
}

// Inicializar estado al cargar la página
// Inicializar estado al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const estadoInicial = document.getElementById('activo').checked;
    
    // Inicializar validación de categoría
    validarCategoriaEnTiempoReal();
    verificarCategoriaActiva();
    
    // También mejorar visibilidad de textos de ayuda
    mejorarVisibilidadTextosAyuda();
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Formatear costo al perder foco
    const costoInput = document.getElementById('costo');
    if (costoInput) {
        costoInput.addEventListener('blur', function() {
            let value = parseFloat(this.value);
            if (!isNaN(value) && value >= 0) {
                this.value = value.toFixed(2);
                
                // Validar mínimo
                if (value <= 0) {
                    this.classList.add('animate__animated', 'animate__shakeX');
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.25)';
                    
                    setTimeout(() => {
                        this.classList.remove('animate__animated', 'animate__shakeX');
                    }, 500);
                }
            }
        });
    }
    
    // Mostrar advertencia si el servicio está en uso
    <?php if (isset($uso_servicio) && $uso_servicio['total'] > 0): ?>
    setTimeout(() => {
        Swal.fire({
            title: 'Servicio en Uso',
            html: `
                <div class="text-start">
                    <p>Este servicio está siendo utilizado en <strong><?php echo $uso_servicio['total']; ?> factura(s)</strong>.</p>
                    <div class="alert alert-info p-2">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Se recomienda desactivarlo en lugar de eliminarlo para mantener la integridad de los datos.</small>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Cambiar la categoría de un servicio en uso no afectará las facturas existentes.
                    </p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
            confirmButtonColor: '#0d6efd',
            showCancelButton: false,
            timer: 6000,
            timerProgressBar: true
        });
    }, 1000);
    <?php endif; ?>
});

// Función para mejorar visibilidad de textos de ayuda
function mejorarVisibilidadTextosAyuda() {
    // Encontrar todos los form-text y mejorarlos
    const formTexts = document.querySelectorAll('.form-text');
    
    formTexts.forEach(text => {
        // Solo aplicar si no tiene clase win-form-help ya
        if (!text.classList.contains('win-form-help')) {
            text.style.opacity = '1';
            text.style.color = 'var(--win-text-secondary)';
            text.style.fontSize = '0.85rem';
            text.style.marginTop = '6px';
        }
    });
}
// Función para validar y mostrar información de la categoría en tiempo real
function validarCategoriaEnTiempoReal() {
    const categoriaSelect = document.getElementById('categoria_id');
    const categoriaInfo = document.getElementById('categoriaInfo');
    const categoriaSeleccionadaSpan = document.getElementById('categoriaSeleccionada');
    
    categoriaSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const valor = this.value;
        
        if (valor) {
            // Categoría válida seleccionada
            this.classList.remove('categoria-invalida');
            this.classList.add('categoria-valida');
            
            const codigo = selectedOption.getAttribute('data-codigo') || '';
            const descripcion = selectedOption.getAttribute('data-descripcion') || '';
            
            // Actualizar información mostrada
            categoriaSeleccionadaSpan.innerHTML = `
                <strong>Categoría seleccionada:</strong><br>
                <span class="badge bg-info me-1">${codigo}</span>
                ${descripcion}
            `;
            
            // Cambiar estilo de la info
            categoriaInfo.style.background = 'rgba(40, 167, 69, 0.1)';
            categoriaInfo.style.borderLeftColor = '#28a745';
            
            // Efecto visual
            this.classList.add('animate__animated', 'animate__pulse');
            setTimeout(() => {
                this.classList.remove('animate__animated', 'animate__pulse');
            }, 500);
            
        } else {
            // No se seleccionó categoría
            this.classList.remove('categoria-valida');
            this.classList.add('categoria-invalida');
            
            categoriaSeleccionadaSpan.innerHTML = '<span class="text-danger">Debe seleccionar una categoría</span>';
            
            // Cambiar estilo de la info
            categoriaInfo.style.background = 'rgba(220, 53, 69, 0.1)';
            categoriaInfo.style.borderLeftColor = '#dc3545';
            
            // Efecto visual
            this.classList.add('animate__animated', 'animate__shakeX');
            setTimeout(() => {
                this.classList.remove('animate__animated', 'animate__shakeX');
            }, 500);
        }
    });
    
    // Validar inicialmente
    if (categoriaSelect.value) {
        categoriaSelect.classList.add('categoria-valida');
    } else {
        categoriaSelect.classList.add('categoria-invalida');
    }
}

// Función para verificar si la categoría seleccionada está activa
function verificarCategoriaActiva() {
    const categoriaSelect = document.getElementById('categoria_id');
    
    // Obtener estado de las categorías desde PHP
    const categoriasActivas = [
        <?php 
        foreach ($categorias as $cat) {
            echo "{id: {$cat['id']}, activa: true},";
        }
        ?>
    ];
    
    categoriaSelect.addEventListener('change', function() {
        const categoriaId = this.value;
        
        // Verificar si la categoría seleccionada está en la lista de activas
        const categoriaEncontrada = categoriasActivas.find(cat => cat.id == categoriaId);
        
        if (categoriaEncontrada) {
            // Categoría activa
            this.style.borderColor = '#28a745';
            this.style.boxShadow = '0 0 0 0.25rem rgba(40, 167, 69, 0.25)';
            
            // Mostrar notificación si se cambió la categoría
            if (categoriaId !== '<?php echo $servicio['categoria_id']; ?>') {
                mostrarToastCategoria('success', 'Categoría asignada', 'El servicio ahora pertenece a esta categoría');
            }
        } else if (categoriaId) {
            // Categoría seleccionada pero no está activa (en teoría no debería pasar)
            this.style.borderColor = '#ffc107';
            this.style.boxShadow = '0 0 0 0.25rem rgba(255, 193, 7, 0.25)';
            
            mostrarToastCategoria('warning', 'Categoría no disponible', 
                'Esta categoría puede no estar disponible actualmente');
        }
    });
}

// Función para notificaciones toast de categoría
function mostrarToastCategoria(tipo, titulo, mensaje) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: 'var(--win-bg-secondary)',
        color: 'var(--win-text-primary)',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: tipo,
        title: titulo,
        text: mensaje,
        iconColor: tipo === 'success' ? '#28a745' : '#ffc107'
    });
}
    </script>
    <?php
	if (file_exists('config/footer.php')) {
        include 'config/footer.php';
    }
	?>
</body>
</html>