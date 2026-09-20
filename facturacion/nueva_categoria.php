<?php
// nueva_categoria.php - Windows 11 Dark Mode
require_once 'config/header.php';

date_default_timezone_set('America/New_York'); 

// Verificar autenticación
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

// Inicializar variables de base de datos
$db = null;
$usuario = null;
$total_categorias = 0;
$total_facturas = 0;
$total_clientes = 0;
$total_servicios = 0;
$total_usuarios = 0;
$total_historico = 0;
$codigo_sugerido = 'CAT01';

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
    
   // Verificar permisos para crear clientes
    if (!$esAdmin && !$esEditor && !$esSuper) {
	// Guardar mensaje para SweetAlert en sesión
		$_SESSION['swal_no_privilegios'] = [
			'titulo' => 'Acceso Denegado',
			'mensaje' => 'Solo usuarios con rol de <strong>Administrador, Editor o Supervisor</strong> pueden crear nuevas <strong>Categorías</strong>.',
			'tipo_usuario' => $usuario['rol_nombre'] ?? 'Usuario',
			'icono' => 'error',
			'rol_id' => $usuario['rol_id'] ?? 0
		];
        
        // Redirigir a servicios.php donde se mostrará el SweetAlert
        header('Location: categorias.php');
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
    
    // Función para generar sugerencia de código basada en la descripción
    function generarSugerenciaCodigo($descripcion, $db) {
        // Limpiar la descripción
        $descripcion = trim($descripcion);
        
        if (empty($descripcion)) {
            return 'CAT01';
        }
        
        // Buscar palabras clave en la descripción
        $palabras = preg_split('/\s+/', strtoupper($descripcion));
        
        // Intentar crear un código de 3-4 letras basado en las primeras letras de las palabras
        $codigo_sugerido = '';
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 0) {
                $codigo_sugerido .= substr($palabra, 0, 1);
                if (strlen($codigo_sugerido) >= 3) {
                    break;
                }
            }
        }
        
        // Si no tenemos al menos 2 caracteres, usar CAT
        if (strlen($codigo_sugerido) < 2) {
            $codigo_sugerido = 'CAT';
        }
        
        // Buscar el próximo número disponible para este prefijo
        $sql = "SELECT codigo FROM clasif_cat_de_serv WHERE codigo LIKE :codigo_base ORDER BY codigo DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        
        $codigo_base = $codigo_sugerido . '%';
        $stmt->execute(['codigo_base' => $codigo_base]);
        $ultimo_codigo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ultimo_codigo && !empty($ultimo_codigo['codigo'])) {
            // Extraer el número del último código
            $ultimo = $ultimo_codigo['codigo'];
            if (preg_match('/^([A-Z]+)(\d+)$/', $ultimo, $matches)) {
                $prefijo = $matches[1];
                $numero = intval($matches[2]);
                $nuevo_numero = $numero + 1;
                return $prefijo . str_pad($nuevo_numero, 2, '0', STR_PAD_LEFT);
            }
        }
        
        // Si no hay códigos con este prefijo, empezar con 01
        return $codigo_sugerido . '01';
    }
    
    // Variable para el código sugerido
    if (isset($_POST['descripcion']) && !empty($_POST['descripcion'])) {
        $codigo_sugerido = generarSugerenciaCodigo($_POST['descripcion'], $db);
    } else {
        // Sugerir un código basado en la última categoría
        $sql_ultimo_codigo = "SELECT codigo FROM clasif_cat_de_serv ORDER BY id DESC LIMIT 1";
        $stmt_codigo = $db->query($sql_ultimo_codigo);
        $ultimo_codigo = $stmt_codigo->fetch(PDO::FETCH_ASSOC);
        
        if ($ultimo_codigo && !empty($ultimo_codigo['codigo'])) {
            $codigo_actual = $ultimo_codigo['codigo'];
            if (preg_match('/^([A-Z]+)(\d+)$/', $codigo_actual, $matches)) {
                $prefijo = $matches[1];
                $numero = intval($matches[2]);
                $codigo_sugerido = $prefijo . str_pad($numero + 1, 2, '0', STR_PAD_LEFT);
            } else {
                $codigo_sugerido = 'CAT' . ($total_categorias + 1);
            }
        } else {
            $codigo_sugerido = 'CAT01';
        }
    }
    
} catch (Exception $e) {
    error_log("Error al cargar datos: " . $e->getMessage());
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Procesar formulario de creación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        // Validar datos requeridos
        $required_fields = ['codigo', 'descripcion'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es requerido");
            }
        }
        
        // Verificar si el código ya existe
        $sql_check_codigo = "SELECT COUNT(*) as count FROM clasif_cat_de_serv WHERE codigo = :codigo";
        $stmt_check = $db->prepare($sql_check_codigo);
        $stmt_check->execute(['codigo' => trim($_POST['codigo'])]);
        $existe_codigo = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existe_codigo['count'] > 0) {
            throw new Exception("El código " . $_POST['codigo'] . " ya existe. Por favor use otro código.");
        }
        
        // Preparar datos para insertar
        $datos = [
            'codigo' => strtoupper(trim($_POST['codigo'])),
            'descripcion' => trim($_POST['descripcion']),
            'activo' => isset($_POST['activo']) ? 1 : 1, // Por defecto activo
            'fecha_creacion' => date('Y-m-d H:i:s')
        ];
        
        // Iniciar transacción solo si no hay errores anteriores
        if ($db) {
            $db->beginTransaction();
        }
        
        // Insertar nueva categoría
        $sql_insert = "INSERT INTO clasif_cat_de_serv (codigo, descripcion, activo, fecha_creacion) 
                      VALUES (:codigo, :descripcion, :activo, :fecha_creacion)";
        
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute($datos);
        
        $nueva_categoria_id = $db->lastInsertId();
        
        // Registrar actividad
        $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $db->prepare($sql_log);
        $stmt_log->execute([
            'CREAR_CATEGORIA',
            'Categoría ' . $datos['descripcion'] . ' creada (Código: ' . $datos['codigo'] . ')',
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nombre'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        if ($db) {
            $db->commit();
        }
        
        // Preguntar si quiere agregar servicios
        $_SESSION['nueva_categoria_id'] = $nueva_categoria_id;
        $_SESSION['nueva_categoria_nombre'] = $datos['descripcion'];
        $_SESSION['nueva_categoria_codigo'] = $datos['codigo'];
        
        // Mostrar mensaje de éxito con opción para agregar servicios
        echo "<script>
            Swal.fire({
                title: '¡Categoría creada exitosamente!',
                html: `Categoría <strong>" . addslashes($datos['codigo']) . " - " . addslashes($datos['descripcion']) . "</strong> ha sido creada.`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '" . $color_accent . "',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class=\"fas fa-plus me-2\"></i>Agregar Servicios',
                cancelButtonText: '<i class=\"fas fa-list me-2\"></i>Ver Categorías',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'nuevo_servicio.php?categoria_id=" . $nueva_categoria_id . "';
                } else {
                    window.location.href = 'categorias.php';
                }
            });
        </script>";
        exit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error al crear la categoría: " . $e->getMessage();
    }
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
    <title>Nueva Categoría - SISFACT PDL Visiones</title>
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

        /* Botones */
        .win-main-content .btn {
            border-radius: var(--win-radius-sm);
            transition: var(--win-transition);
            font-weight: 500;
            padding: 0.5rem 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Quick Actions */
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

        /* Estados */
        .estado-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--win-radius-sm);
            font-weight: 500;
            display: inline-block;
        }

        .estado-activo {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }

        .estado-inactivo {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        /* Ayuda de campos */
        .field-help {
            font-size: 0.75rem;
            color: var(--win-text-secondary);
            margin-top: 0.25rem;
            display: block;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }

        /* CORRECCIÓN: Textos muted más visibles */
        .text-muted {
            color: var(--win-text-secondary) !important;
            opacity: 0.9;
        }

        [data-theme="dark"] .text-muted {
            color: #a0a0a0 !important;
        }

        [data-theme="light"] .text-muted {
            color: #6c757d !important;
        }

        small.text-muted {
            font-size: 0.875em;
            opacity: 0.9;
        }

        [data-theme="dark"] small.text-muted {
            color: #b0b0b0 !important;
        }

        /* Botones con iconos */
        .btn-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Sugerencia de código */
        .sugerencia-codigo {
            background: var(--win-accent-light);
            border: 1px dashed var(--win-accent);
            border-radius: var(--win-radius-sm);
            padding: 0.5rem;
            margin-top: 0.5rem;
            cursor: pointer;
            transition: var(--win-transition);
        }

        .sugerencia-codigo:hover {
            background: var(--win-accent);
            color: white;
        }

        .sugerencia-codigo i {
            margin-right: 0.5rem;
        }

        /* Estilos para los modales según tema */
        .swal2-popup {
            background: var(--win-bg-secondary) !important;
            border: 1px solid var(--win-border-color) !important;
            border-radius: var(--win-radius) !important;
            color: var(--win-text-primary) !important;
        }

        .swal2-title {
            color: var(--win-text-primary) !important;
        }

        .swal2-html-container {
            color: var(--win-text-secondary) !important;
        }

        .swal2-confirm {
            background: var(--win-accent) !important;
            border-color: var(--win-accent) !important;
        }

        .swal2-cancel {
            background: var(--win-bg-tertiary) !important;
            border-color: var(--win-border-color) !important;
            color: var(--win-text-primary) !important;
        }

        .swal2-icon {
            border-color: var(--win-accent) !important;
        }

        .swal2-icon.swal2-success [class^=swal2-success-line] {
            background-color: var(--win-accent) !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring {
            border-color: var(--win-accent-light) !important;
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
        
        <button class="btn btn-primary w-100 btn-icon" onclick="guardarConfiguracion()">
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
            <span style="color: var(--win-text-primary);">NUEVO CATEGORÍA - SISFACT PDL Visiones</span>
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
                <a href="nueva_categorias.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-tags"></i>
                    <span class="win-nav-text">Nueva Categoría</span>
					<?php if ($es_admin): ?>
                    <span class="win-nav-badge admin-badge" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-crown"></i>
                    </span>
                    <?php endif; ?>
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
        
        <div class="mt-4 px-3">
            <small class="text-muted fw-bold">Categorías activas</small>
            <div class="mt-1">
                <small class="text-muted"><?php echo $total_categorias; ?> categorías
				registradas</small>
            </div>
        </div>
<?php
// Obtener datos globales de facturación (CUP)
$finanzas = Database::getProgresoFinanciero();
?>
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
                    <i class="fas fa-tag me-2" style="color: var(--win-accent);"></i>Nueva Categoría
                </h1>
                <p class="text-muted mb-0">Complete los datos para registrar una nueva categoría de servicios - Fecha Operaciones: <strong><?php echo $mes_actual_es . ' ' . date('Y'); ?></strong></p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="categorias.php" class="btn btn-sm btn-outline-secondary btn-icon">
                    <i class="fas fa-arrow-left"></i> Volver a Categorías
                </a>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="categoriaForm" class="animate__animated animate__fadeIn">
            <input type="hidden" name="crear" value="1">
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Información de la categoría -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-info-circle me-2" style="color: var(--win-accent);"></i>Información de la Categoría
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label required">Descripción</label>
                                    <input type="text" class="form-control" name="descripcion" required
                                           placeholder="Descripción completa de la categoría"
                                           value="<?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?>"
                                           maxlength="100"
                                           id="descripcionInput"
                                           oninput="generarSugerenciaCodigo()">
                                    <span class="field-help">Nombre descriptivo de la categoría (máx. 100 caracteres)</span>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Código</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="codigo" required
                                               value="<?php echo htmlspecialchars($_POST['codigo'] ?? $codigo_sugerido); ?>"
                                               placeholder="Ej: IMP1, FS01, SERV01"
                                               id="codigoInput"
                                               onblur="validarCodigoUnico()"
                                               maxlength="20"
                                               style="text-transform: uppercase;">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generarCodigoAutomatico()" title="Generar código automático">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </div>
                                    <span class="field-help">Código único de la categoría (máx. 20 caracteres)</span>
                                    <div id="codigoError" class="invalid-feedback d-none"></div>
                                    
                                    <!-- Sugerencia de código -->
                                    <div id="sugerenciaCodigo" class="sugerencia-codigo d-none mt-2" onclick="usarSugerencia()">
                                        <i class="fas fa-lightbulb"></i>
                                        <span id="textoSugerencia"></span>
                                        <small class="float-end">Haz clic para usar</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estado de la Categoría</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" 
                                               id="flexSwitchCheckChecked" checked>
                                        <label class="form-check-label" for="flexSwitchCheckChecked">
                                            <i class="fas fa-check-circle text-success me-1"></i> Activa
                                        </label>
                                    </div>
                                    <span class="field-help">Las categorías inactivas no aparecerán en los servicios</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ejemplos de códigos existentes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-list me-2" style="color: var(--win-accent);"></i>Categorías Existentes (Ejemplos)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th style="color: var(--win-text-primary);">Código</th>
                                            <th style="color: var(--win-text-primary);">Descripción</th>
                                            <th style="color: var(--win-text-primary);">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Obtener algunas categorías existentes para mostrar como ejemplos
                                        if ($db) {
                                            try {
                                                $sql_ejemplos = "SELECT codigo, descripcion, activo FROM clasif_cat_de_serv ORDER BY id LIMIT 5";
                                                $stmt_ejemplos = $db->query($sql_ejemplos);
                                                $ejemplos = $stmt_ejemplos->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($ejemplos as $ejemplo):
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($ejemplo['codigo']); ?></span></td>
                                            <td style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($ejemplo['descripcion']); ?></td>
                                            <td>
                                                <?php if ($ejemplo['activo'] == 1): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Activa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Inactiva</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php 
                                                endforeach; 
                                            } catch (Exception $e) { 
                                        ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No se pudieron cargar los ejemplos</td>
                                        </tr>
                                        <?php 
                                            } 
                                        } else { 
                                        ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No hay conexión a la base de datos</td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>Los códigos pueden ser cualquier combinación de letras y números (hasta 20 caracteres)
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Resumen de creación -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-clipboard-check me-2" style="color: var(--win-accent);"></i>Resumen de la Nueva Categoría
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fas fa-toggle-on me-1"></i> Estado:</span>
                                <span class="fw-bold estado-activo">
                                    <i class="fas fa-check-circle me-1"></i> ACTIVA
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fas fa-hashtag me-1"></i> Código:</span>
                                <span class="fw-bold" id="codigoResumen"><?php echo htmlspecialchars($codigo_sugerido); ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted d-block mb-1"><i class="fas fa-align-left me-1"></i> Descripción:</span>
                                <div class="fw-bold text-wrap" id="descripcionResumen" style="color: var(--win-text-primary); min-height: 24px;">
                                    <?php echo !empty($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : '(Ingrese una descripción)'; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fas fa-calendar me-1"></i> Fecha creación:</span>
                                <span class="fw-bold"><?php echo date('d/m/Y'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fas fa-layer-group me-1"></i> Categorías totales:</span>
                                <span class="fw-bold"><?php echo $total_categorias + 1; ?></span>
                            </div>
                            <hr>
                            <div class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i>Los campos marcados con <span class="text-danger">*</span> son obligatorios
                            </div>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold" style="color: var(--win-text-primary);">
                                <i class="fas fa-bolt me-2" style="color: var(--win-accent);"></i>Acciones Rápidas
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-icon" onclick="generarCodigoAutomatico()">
                                    <i class="fas fa-magic"></i> Generar Código
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-icon" onclick="limpiarFormulario()">
                                    <i class="fas fa-broom"></i> Limpiar Formulario
                                </button>
                                <button type="button" class="btn btn-outline-info btn-icon" onclick="verCategoriasExistentes()">
                                    <i class="fas fa-eye"></i> Ver Categorías
                                </button>
                                <button type="button" class="btn btn-success btn-icon" onclick="document.getElementById('btnGuardar').click()">
                                    <i class="fas fa-save"></i> Guardar Categoría
                                </button>
                            </div>
                            <hr>
                            <div class="small text-muted">
                                <i class="fas fa-lightbulb me-1"></i>Consejo: Escribe la descripción primero para generar un código sugerido automáticamente.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="d-flex justify-content-between pt-3 border-top">
                <div>
                    <a href="categorias.php" class="btn btn-outline-secondary btn-icon">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2 btn-icon" onclick="previsualizarCategoria()">
                        <i class="fas fa-eye"></i> Previsualizar
                    </button>
                    <button type="submit" class="btn btn-primary btn-icon" id="btnGuardar">
                        <i class="fas fa-check-circle"></i> Crear Categoría
                    </button>
                </div>
            </div>
        </form>
    </main>

    <!-- Quick Action -->
    <?php if ($esAdmin || $esEditor): ?>
    <button class="win-quick-action" onclick="document.getElementById('btnGuardar').click()" title="Crear categoría">
        <i class="fas fa-tag"></i>
    </button>
    <?php endif; ?>

    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Variables globales
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
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
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
        
        // Generar sugerencia de código basada en la descripción
        function generarSugerenciaCodigo() {
            const descripcion = document.getElementById('descripcionInput').value.trim();
            const codigoInput = document.getElementById('codigoInput');
            const sugerenciaDiv = document.getElementById('sugerenciaCodigo');
            const textoSugerencia = document.getElementById('textoSugerencia');
            
            // Actualizar descripción en resumen
            document.getElementById('descripcionResumen').textContent = descripcion || '(Ingrese una descripción)';
            
            if (descripcion.length < 3) {
                sugerenciaDiv.classList.add('d-none');
                return;
            }
            
            // Crear código basado en las primeras letras de las palabras
            const palabras = descripcion.toUpperCase().split(' ');
            let codigoSugerido = '';
            
            // Tomar las primeras letras de las primeras palabras
            for (let palabra of palabras) {
                if (palabra.length > 0 && codigoSugerido.length < 3) {
                    codigoSugerido += palabra.charAt(0);
                }
            }
            
            // Si no tenemos suficientes letras, usar más letras de la primera palabra
            if (codigoSugerido.length < 2 && palabras[0].length > 1) {
                codigoSugerido = palabras[0].substring(0, 3);
            }
            
            // Agregar número secuencial (generalmente 01 para nuevas categorías)
            codigoSugerido += '01';
            
            // Limitar a 20 caracteres
            codigoSugerido = codigoSugerido.substring(0, 20);
            
            // Mostrar sugerencia
            textoSugerencia.textContent = `Sugerencia: ${codigoSugerido}`;
            sugerenciaDiv.classList.remove('d-none');
            
            // Si el campo de código está vacío, llenarlo con la sugerencia
            if (!codigoInput.value.trim()) {
                codigoInput.value = codigoSugerido;
                actualizarResumenCodigo();
            }
        }
        
        // Usar sugerencia de código
        function usarSugerencia() {
            const textoSugerencia = document.getElementById('textoSugerencia').textContent;
            const codigo = textoSugerencia.replace('Sugerencia: ', '');
            document.getElementById('codigoInput').value = codigo;
            actualizarResumenCodigo();
            
            // Ocultar sugerencia
            document.getElementById('sugerenciaCodigo').classList.add('d-none');
            
            // Validar código
            validarCodigoUnico();
        }
        
        // Generar código automático
        function generarCodigoAutomatico() {
            const descripcion = document.getElementById('descripcionInput').value.trim();
            const codigoInput = document.getElementById('codigoInput');
            
            if (descripcion) {
                // Llamar al servidor para generar un código basado en la descripción
                fetch(`generar_codigo_categoria.php?descripcion=${encodeURIComponent(descripcion)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.codigo_sugerido) {
                            codigoInput.value = data.codigo_sugerido;
                            actualizarResumenCodigo();
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Código generado',
                                text: `Se generó el código: ${data.codigo_sugerido}`,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            
                            validarCodigoUnico();
                        }
                    })
                    .catch(error => {
                        console.error('Error al generar código:', error);
                        // Generar localmente si falla
                        generarSugerenciaCodigo();
                        usarSugerencia();
                    });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Descripción requerida',
                    text: 'Por favor, ingrese una descripción primero para generar un código automático.',
                    confirmButtonText: 'Entendido'
                });
            }
        }
        
        // Actualizar resumen del código
        function actualizarResumenCodigo() {
            const codigo = document.getElementById('codigoInput').value.trim().toUpperCase();
            document.getElementById('codigoResumen').textContent = codigo || '(Sin código)';
        }
        
        // Validar que el código sea único
        function validarCodigoUnico() {
            const codigoInput = document.getElementById('codigoInput');
            const codigoError = document.getElementById('codigoError');
            const codigo = codigoInput.value.trim().toUpperCase();
            
            if (!codigo) {
                codigoInput.classList.remove('is-invalid');
                codigoError.classList.add('d-none');
                return;
            }
            
            // Validar formato (máximo 20 caracteres, letras, números y guiones)
            if (!/^[A-Z0-9\-_]{1,20}$/.test(codigo)) {
                codigoInput.classList.add('is-invalid');
                codigoError.textContent = 'El código debe tener máximo 20 caracteres (solo letras, números, guiones y guiones bajos)';
                codigoError.classList.remove('d-none');
                return;
            }
            
            // Verificar si el código ya existe
            fetch('verificar_codigo_categoria.php?codigo=' + encodeURIComponent(codigo))
                .then(response => response.json())
                .then(data => {
                    if (data.existe) {
                        codigoInput.classList.add('is-invalid');
                        codigoError.textContent = 'Este código ya está en uso por otra categoría';
                        codigoError.classList.remove('d-none');
                    } else {
                        codigoInput.classList.remove('is-invalid');
                        codigoError.classList.add('d-none');
                    }
                })
                .catch(error => {
                    console.error('Error al verificar código:', error);
                });
        }
        
        // Limpiar formulario
        function limpiarFormulario() {
            Swal.fire({
                title: '¿Limpiar formulario?',
                text: "Se perderán todos los datos ingresados",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fas fa-broom me-2"></i>Sí, limpiar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-warning me-2',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Resetear formulario
                    document.getElementById('categoriaForm').reset();
                    
                    // Restaurar valores generados
                    document.getElementById('codigoInput').value = '<?php echo htmlspecialchars($codigo_sugerido); ?>';
                    document.getElementById('flexSwitchCheckChecked').checked = true;
                    
                    // Actualizar resúmenes
                    document.getElementById('codigoResumen').textContent = '<?php echo htmlspecialchars($codigo_sugerido); ?>';
                    document.getElementById('descripcionResumen').textContent = '(Ingrese una descripción)';
                    
                    // Limpiar errores
                    document.getElementById('codigoError').classList.add('d-none');
                    document.getElementById('codigoInput').classList.remove('is-invalid');
                    document.getElementById('sugerenciaCodigo').classList.add('d-none');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Formulario limpiado',
                        text: 'Todos los campos han sido restablecidos',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
        
        
        // Previsualizar categoría
        function previsualizarCategoria() {
            const codigo = document.getElementById('codigoInput').value.trim().toUpperCase();
            const descripcion = document.getElementById('descripcionInput').value.trim();
            const estado = document.getElementById('flexSwitchCheckChecked').checked ? 'Activa' : 'Inactiva';
            
            if (!codigo || !descripcion) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Datos incompletos',
                    text: 'Complete el código y la descripción para previsualizar.',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            Swal.fire({
                title: 'Previsualización de Categoría',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <strong><i class="fas fa-hashtag me-2"></i>Código:</strong>
                            <div class="mt-1 p-2 bg-light rounded">${codigo}</div>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-align-left me-2"></i>Descripción:</strong>
                            <div class="mt-1 p-2 bg-light rounded">${descripcion}</div>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-toggle-on me-2"></i>Estado:</strong>
                            <div class="mt-1">
                                <span class="badge ${estado === 'Activa' ? 'bg-success' : 'bg-secondary'}">
                                    <i class="fas ${estado === 'Activa' ? 'fa-check' : 'fa-times'} me-1"></i>${estado}
                                </span>
                            </div>
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
        
        // Ver categorías existentes
        function verCategoriasExistentes() {
            window.open('categorias.php', '_blank');
        }
        
        // Validar formulario antes de enviar
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('categoriaForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const codigo = form.querySelector('input[name="codigo"]').value.trim().toUpperCase();
                    const descripcion = form.querySelector('input[name="descripcion"]').value.trim();
                    const codigoError = document.getElementById('codigoError');
                    
                    // Validar campos requeridos
                    if (!codigo || !descripcion) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Campos requeridos',
                            text: 'Los campos Código y Descripción son requeridos',
                            confirmButtonText: 'Entendido'
                        });
                        return false;
                    }
                    
                    // Validar formato del código
                    if (!/^[A-Z0-9\-_]{1,20}$/.test(codigo)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Formato inválido',
                            text: 'El código debe tener máximo 20 caracteres (solo letras, números, guiones y guiones bajos)',
                            confirmButtonText: 'Entendido'
                        });
                        return false;
                    }
                    
                    // Verificar si hay errores de código
                    if (codigoError && !codigoError.classList.contains('d-none')) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Código duplicado',
                            text: 'El código ingresado ya está en uso. Por favor, genere uno nuevo.',
                            confirmButtonText: 'Entendido'
                        });
                        return false;
                    }
                    
                    // Mostrar confirmación
                    Swal.fire({
                        title: '¿Crear nueva categoría?',
                        html: `
                            <div class="text-start">
                                <p><strong><i class="fas fa-hashtag me-2"></i>Código:</strong> ${codigo}</p>
                                <p><strong><i class="fas fa-align-left me-2"></i>Descripción:</strong> ${descripcion}</p>
                                <p><strong><i class="fas fa-toggle-on me-2"></i>Estado:</strong> ${form.querySelector('#flexSwitchCheckChecked').checked ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>'}</p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '<?php echo $color_accent; ?>',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-check-circle me-2"></i> Crear Categoría',
                        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'btn btn-primary me-2',
                            cancelButton: 'btn btn-secondary'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Mostrar loading
                            const btnGuardar = document.getElementById('btnGuardar');
                            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';
                            btnGuardar.disabled = true;
                            
                            // Convertir código a mayúsculas antes de enviar
                            form.querySelector('input[name="codigo"]').value = codigo;
                            
                            // Enviar formulario
                            form.submit();
                        }
                    });
                    
                    return false;
                });
            }
            
            // Actualizar texto del switch de estado
            const estadoSwitch = document.getElementById('flexSwitchCheckChecked');
            if (estadoSwitch) {
                estadoSwitch.addEventListener('change', function() {
                    const label = this.nextElementSibling;
                    if (this.checked) {
                        label.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Activa';
                        document.querySelector('.estado-activo').innerHTML = '<i class="fas fa-check-circle me-1"></i> ACTIVA';
                    } else {
                        label.innerHTML = '<i class="fas fa-times-circle text-secondary me-1"></i> Inactiva';
                        document.querySelector('.estado-activo').innerHTML = '<i class="fas fa-times-circle me-1"></i> INACTIVA';
                        document.querySelector('.estado-activo').className = 'fw-bold estado-inactivo';
                    }
                });
            }
            
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
            
            // Inicializar validación de código
            const codigoInput = document.getElementById('codigoInput');
            if (codigoInput) {
                // Convertir a mayúsculas automáticamente
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                    actualizarResumenCodigo();
                });
                
                // Validar al perder foco
                codigoInput.addEventListener('blur', validarCodigoUnico);
            }
            
            // Actualizar descripción en resumen
            const descripcionInput = document.getElementById('descripcionInput');
            if (descripcionInput) {
                descripcionInput.addEventListener('input', function() {
                    document.getElementById('descripcionResumen').textContent = this.value || '(Ingrese una descripción)';
                });
            }
            
            // Generar sugerencia al cargar si hay descripción
            if (descripcionInput.value.trim()) {
                generarSugerenciaCodigo();
            }
        });
    </script>
</body>
</html>