<?php
// perfil.php - Perfil de usuario con recorte de imagen 7x7cm
require_once 'config/header.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener configuración del tema Windows 11
$tema_windows = $_SESSION['tema_windows'] ?? 'dark';
$color_accent = $_SESSION['color_accent'] ?? '#0078d4';
$sidebar_mini = $_SESSION['sidebar_mini'] ?? false;

// Inicializar variables
$cant_categ = 0;
$cant_Users = 0;
$cant_UsersAct = 0;
$clientes_todos = 0;
$servicios_count = 0;
$facturas_anual = 0;
$estadisticas = ['total' => 0];

$usuario = [];
$roles = [];
$avatar_placeholder = strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1));
$mensaje = '';
$tipo_mensaje = '';

try {
    $db = Database::getConnection();
    
    // Total de facturas del año
    $sql_facturas_anual = "SELECT COUNT(*) as total FROM tbl_fact 
                           WHERE YEAR(fecha_emision) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($sql_facturas_anual);
    $stmt->execute();
    $facturas_anual = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de todas las facturas
    $sql_facturas_total = "SELECT COUNT(*) as total FROM tbl_fact";
    $stmt = $db->prepare($sql_facturas_total);
    $stmt->execute();
    $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cant_users_activos = "SELECT COUNT(*) as total FROM clasif_usuarios WHERE activo = 1";
    $stmt = $db->prepare($sql_cant_users_activos);
    $stmt->execute();
    $cant_UsersAct = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cant_users = "SELECT COUNT(*) as total FROM clasif_usuarios";
    $stmt = $db->prepare($sql_cant_users);
    $stmt->execute();
    $cant_Users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_clientes_todos = "SELECT COUNT(*) as totalc FROM clasif_clientes";
    $stmt = $db->prepare($sql_clientes_todos);
    $stmt->execute();
    $clientes_todos = $stmt->fetch(PDO::FETCH_ASSOC)['totalc'];
    
    $sql_clientes_activos = "SELECT COUNT(*) as total FROM clasif_clientes WHERE activo = 1";
    $stmt = $db->prepare($sql_clientes_activos);
    $stmt->execute();
    $clientes_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_cant_categ = "SELECT COUNT(*) as totalcat FROM clasif_cat_de_serv";
    $stmt = $db->prepare($sql_cant_categ);
    $stmt->execute();
    $cant_categ = $stmt->fetch(PDO::FETCH_ASSOC)['totalcat'];

    $sql_servicios_count = "SELECT COUNT(*) as total FROM clasif_serv WHERE activo = 1";
    $stmt = $db->prepare($sql_servicios_count);
    $stmt->execute();
    $servicios_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    //Total registros para histórico
    try {
        $sql_total = "SELECT COUNT(*) as total FROM historico_operaciones";
        $stmt = $db->query($sql_total);
        $estadisticas['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error al contar histórico: " . $e->getMessage());
        $estadisticas['total'] = 0;
    }
    
    // Obtener datos del usuario con su rol
    $sql_usuario = "SELECT 
                        u.*, 
                        r.descripcion as rol_nombre,
                        r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.id = :id";
    $stmt = $db->prepare($sql_usuario);
    $stmt->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
    // Obtener todos los roles disponibles
    $sql_roles = "SELECT * FROM clasif_rol ORDER BY id";
    $stmt_roles = $db->prepare($sql_roles);
    $stmt_roles->execute();
    $roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
    
    // Actualizar avatar placeholder
    $avatar_placeholder = strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1));
    
} catch (Exception $e) {
    error_log("Error al cargar datos del usuario: " . $e->getMessage());
    $mensaje = "Error al cargar los datos del perfil. Por favor, intente nuevamente.";
    $tipo_mensaje = 'danger';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
    try {
        $db = Database::getConnection();
        
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        // Validar contraseñas
        if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
            throw new Exception("Todos los campos de contraseña son obligatorios");
        }
        
        if ($password_nueva !== $password_confirmar) {
            throw new Exception("Las contraseñas nuevas no coinciden");
        }
        
        if (strlen($password_nueva) < 6) {
            throw new Exception("La nueva contraseña debe tener al menos 6 caracteres");
        }
        
        // Obtener contraseña actual del usuario
        $sql_password = "SELECT password FROM clasif_usuarios WHERE id = :id";
        $stmt_password = $db->prepare($sql_password);
        $stmt_password->execute(['id' => $_SESSION['usuario_id']]);
        $usuario_password = $stmt_password->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_password || !password_verify($password_actual, $usuario_password['password'])) {
            throw new Exception("La contraseña actual es incorrecta");
        }
        
        // Actualizar contraseña
        $sql_update_password = "UPDATE clasif_usuarios SET 
                               password = :password,
                               fecha_actualizacion = NOW()
                               WHERE id = :id";
        
        $stmt_update = $db->prepare($sql_update_password);
        $hashed_password = password_hash($password_nueva, PASSWORD_DEFAULT);
        $result = $stmt_update->execute([
            'password' => $hashed_password,
            'id' => $_SESSION['usuario_id']
        ]);
        
        if ($result) {
            // Registrar en historial
            $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                       VALUES ('CAMBIO_PASSWORD', 'Cambio de contraseña de usuario', :usuario_id, :nombre, :ip)";
            $stmt_log = $db->prepare($sql_log);
            $stmt_log->execute([
                'usuario_id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $mensaje = "Contraseña actualizada correctamente";
            $tipo_mensaje = 'success';
            
            echo '<script>
                setTimeout(function() {
                    window.location.href = window.location.href;
                }, 1000);
            </script>';
        } else {
            throw new Exception("Error al actualizar la contraseña");
        }
        
    } catch (Exception $e) {
        error_log("Error al cambiar contraseña: " . $e->getMessage());
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Procesar actualización de perfil
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
    try {
        $db = Database::getConnection();
        
        // Validar y limpiar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
        $direccion_particular = trim($_POST['direccion_particular'] ?? '');
        $no_ci = trim($_POST['no_ci'] ?? '');
        $rol_id = intval($_POST['rol_id'] ?? $usuario['rol_id']);
        
        // Validaciones básicas
        if (empty($nombre) || empty($apellidos)) {
            throw new Exception("Nombre y apellidos son obligatorios");
        }
        
        if (empty($no_ci)) {
            throw new Exception("El carnet de identidad es obligatorio");
        }
        
        // Validar que el rol sea válido
        $rol_valido = false;
        foreach ($roles as $rol) {
            if ($rol['id'] == $rol_id) {
                $rol_valido = true;
                break;
            }
        }
        
        if (!$rol_valido) {
            throw new Exception("Rol seleccionado no válido");
        }
        
        // Verificar si el usuario puede cambiar el rol (solo administradores)
        $puede_cambiar_rol = ($usuario['rol_codigo'] == 'Admin' || $usuario['rol_codigo'] == 'Super');
        if (!$puede_cambiar_rol && $rol_id != $usuario['rol_id']) {
            throw new Exception("No tiene permisos para cambiar el rol");
        }
        
        // Actualizar datos del usuario
        $sql_update = "UPDATE clasif_usuarios SET 
                      nombre = :nombre,
                      apellidos = :apellidos,
                      telefono_contacto = :telefono,
                      direccion_particular = :direccion,
                      no_ci = :no_ci,
                      rol_id = :rol_id,
                      fecha_actualizacion = NOW()
                      WHERE id = :id";
        
        $stmt_update = $db->prepare($sql_update);
        $result = $stmt_update->execute([
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'telefono' => $telefono_contacto,
            'direccion' => $direccion_particular,
            'no_ci' => $no_ci,
            'rol_id' => $rol_id,
            'id' => $_SESSION['usuario_id']
        ]);
        
        if ($result) {
            // Actualizar sesión
            $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellidos;
            $_SESSION['usuario_rol'] = $usuario['rol_codigo'];
            
            // Registrar en historial
            $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                       VALUES ('ACTUALIZACION_PERFIL', 'Actualización de perfil de usuario', :usuario_id, :nombre, :ip)";
            $stmt_log = $db->prepare($sql_log);
            $stmt_log->execute([
                'usuario_id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $mensaje = "Perfil actualizado correctamente";
            $tipo_mensaje = 'success';
            
            // Recargar datos del usuario
            $stmt = $db->prepare($sql_usuario);
            $stmt->execute(['id' => $_SESSION['usuario_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avatar_placeholder = strtoupper(substr($nombre, 0, 1));
        } else {
            throw new Exception("Error al actualizar el perfil");
        }
        
    } catch (Exception $e) {
        error_log("Error al actualizar perfil: " . $e->getMessage());
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Procesar subida de foto RECORTADA (similar a nuevo_usuario.php)
if (isset($_POST['imagen_recortada']) && !empty($_POST['imagen_recortada'])) {
    try {
        $db = Database::getConnection();
        
        $directorio_usuarios = 'assets/imagenes/usuarios/';
        
        // Crear directorio si no existe
        if (!file_exists($directorio_usuarios)) {
            mkdir($directorio_usuarios, 0777, true);
        }
        
        // Eliminar foto anterior si existe
        if (!empty($usuario['foto']) && file_exists($usuario['foto'])) {
            if (strpos($usuario['foto'], 'assets/imagenes/usuarios/') === 0) {
                @unlink($usuario['foto']);
            }
        }
        
        // Decodificar imagen base64
        $imagen_base64 = $_POST['imagen_recortada'];
        $imagen_base64 = str_replace('data:image/jpeg;base64,', '', $imagen_base64);
        $imagen_base64 = str_replace(' ', '+', $imagen_base64);
        $imagen_decodificada = base64_decode($imagen_base64);
        
        if ($imagen_decodificada === false) {
            throw new Exception("Error al decodificar la imagen");
        }
        
        // Generar nombre único
        $nombre_unico = 'user_' . $_SESSION['usuario_id'] . '_' . time() . '_' . uniqid() . '.jpg';
        $ruta_completa = $directorio_usuarios . $nombre_unico;
        
        // Guardar imagen
        if (file_put_contents($ruta_completa, $imagen_decodificada)) {
            // Actualizar en base de datos
            $sql = "UPDATE clasif_usuarios SET foto = :foto WHERE id = :id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                'foto' => $ruta_completa,
                'id' => $_SESSION['usuario_id']
            ]);
            
            if ($result) {
                // Registrar en historial
                $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) 
                           VALUES ('ACTUALIZACION_FOTO', 'Actualización de foto de perfil', :usuario_id, :nombre, :ip)";
                $stmt_log = $db->prepare($sql_log);
                $stmt_log->execute([
                    'usuario_id' => $_SESSION['usuario_id'],
                    'nombre' => $_SESSION['usuario_nombre'],
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $mensaje = "Foto de perfil actualizada correctamente";
                $tipo_mensaje = 'success';
                
                // Recargar datos del usuario
                $stmt = $db->prepare($sql_usuario);
                $stmt->execute(['id' => $_SESSION['usuario_id']]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                throw new Exception("Error al guardar la foto en la base de datos");
            }
        } else {
            throw new Exception("Error al guardar la imagen en el servidor");
        }
        
    } catch (Exception $e) {
        error_log("Error al subir foto: " . $e->getMessage());
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Colores de temas Windows 11
$temas_windows = [
    'dark' => [
        'nombre' => 'Windows Dark',
        'bg_primary' => '#0d0d0d',
        'bg_secondary' => '#1a1a1a',
        'bg_tertiary' => '#2d2d2d',
        'text_primary' => '#ffffff',
        'text_secondary' => '#b3b3b3',
        'border_color' => '#404040',
        'accent_color' => $color_accent
    ],
    'light' => [
        'nombre' => 'Windows Light',
        'bg_primary' => '#f3f3f3',
        'bg_secondary' => '#ffffff',
        'bg_tertiary' => '#fafafa',
        'text_primary' => '#1a1a1a',
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

// Obtener mes actual en español
$meses_completos = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$mes_actual_es = $meses_completos[date('n') - 1];

// Calcular antigüedad
$fecha_registro = new DateTime($usuario['fecha_registro'] ?? date('Y-m-d H:i:s'));
$fecha_actual = new DateTime();
$antiguedad = $fecha_registro->diff($fecha_actual);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $tema_windows; ?>" data-accent="<?php echo $color_accent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - SISFACT PDL VISIONES</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <!-- Cropper.js -->
    <link rel="stylesheet" href="css/cropper.min.css">
    <script src="js/cropper.min.js"></script>
<!-- Chatbot -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
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
            background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #ffffff));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }

        .win-sidebar-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        /* Estilos para avatar grande */
        .profile-avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 15px;
            position: relative;
            cursor: pointer;
            overflow: hidden;
            border: 4px solid var(--win-accent);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: var(--win-transition);
            background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #ffffff));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 600;
        }

        .profile-avatar-large:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-large .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--win-transition);
            color: white;
            font-size: 24px;
        }

        .profile-avatar-large:hover .avatar-overlay {
            opacity: 1;
        }

        /* Activity timeline */
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--win-border-color);
        }

        .activity-item {
            position: relative;
            margin-bottom: 1rem;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-dot {
            position: absolute;
            left: -30px;
            top: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--win-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .activity-content {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm);
            padding: 0.5rem;
            border-left: 3px solid var(--win-accent);
        }
        /* Activity timeline */
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--win-border-color);
        }

        .activity-item {
            position: relative;
            margin-bottom: 1rem;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-dot {
            position: absolute;
            left: -30px;
            top: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--win-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .activity-content {
            background: var(--win-bg-tertiary);
            border-radius: var(--win-radius-sm);
            padding: 0.5rem;
            border-left: 3px solid var(--win-accent);
        }

        .activity-time {
            color: var(--win-text-secondary);
            font-size: 0.875rem;
        }

        /* Status toggle */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-label {
            font-weight: 500;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-active {
            background-color: #28a745;
            color: white;
        }

        .status-inactive {
            background-color: #dc3545;
            color: white;
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
            
            .profile-avatar-large {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
        }
        /* Modal de recorte - Estilos mejorados */
        .modal-xl {
            max-width: 1200px;
        }

        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            min-height: 400px;
            border-radius: 8px;
            overflow: hidden;
        }

        .preview-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            border: 3px solid var(--win-accent);
            border-radius: 8px;
            overflow: hidden;
        }

        #previewCanvas {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Mejoras para el cropper */
        .cropper-container {
            max-height: 500px;
        }

        .cropper-view-box {
            outline: 2px solid var(--win-accent);
            outline-color: var(--win-accent);
            box-shadow: 0 0 0 1px white;
        }

        .cropper-line {
            background-color: var(--win-accent);
        }

        .cropper-point {
            background-color: var(--win-accent);
            width: 8px;
            height: 8px;
            opacity: 1;
        }

        .crop-controls .btn {
            text-align: left;
            padding: 10px 15px;
        }

        .crop-controls .btn i {
            width: 20px;
            text-align: center;
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
/* Estilos específicos para tema oscuro */
[data-theme="dark"] .dropdown-menu {
    background-color: var(--win-bg-secondary) !important;
    border-color: var(--win-border-color) !important;
}

[data-theme="dark"] .dropdown-item {
    color: var(--win-text-primary) !important;
}

[data-theme="dark"] .dropdown-item:hover {
    background-color: var(--win-accent-light) !important;
    color: var(--win-accent) !important;
}

[data-theme="dark"] .dropdown-header {
    background-color: var(--win-bg-tertiary) !important;
    color: var(--win-text-primary) !important;
}

[data-theme="dark"] .dropdown-divider {
    border-color: var(--win-border-color) !important;
}

[data-theme="dark"] .text-muted {
    color: var(--win-text-secondary) !important;
}

/* Asegurar que todos los textos en el dropdown sean del color correcto */
[data-theme="dark"] .dropdown-menu *:not(.badge):not(.btn) {
    color: var(--win-text-primary) !important;
}

/* Botones en tema oscuro */
[data-theme="dark"] .btn-outline-secondary {
    color: var(--win-text-secondary);
    border-color: var(--win-border-color);
    background-color: transparent;
}

[data-theme="dark"] .btn-outline-secondary:hover {
    background-color: var(--win-bg-tertiary);
    color: var(--win-text-primary);
    border-color: var(--win-border-color);
}

/* Inputs en tema oscuro */
[data-theme="dark"] .form-control,
[data-theme="dark"] .form-select {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-border-color);
    color: var(--win-text-primary);
}

[data-theme="dark"] .form-control:focus,
[data-theme="dark"] .form-select:focus {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-accent);
    color: var(--win-text-primary);
    box-shadow: 0 0 0 0.25rem var(--win-accent-light);
}

/* Cards en tema oscuro */
[data-theme="dark"] .card {
    background-color: var(--win-bg-secondary);
    border-color: var(--win-border-color);
}

[data-theme="dark"] .card-header {
    background-color: var(--win-bg-tertiary) !important;
    border-color: var(--win-border-color) !important;
    color: var(--win-text-primary) !important;
}

/* Alertas en tema oscuro */
[data-theme="dark"] .alert {
    background-color: var(--win-bg-tertiary);
    border-color: var(--win-border-color);
    color: var(--win-text-primary);
}

/* Badges en tema oscuro */
[data-theme="dark"] .badge {
    color: white !important;
}

/* Botón cerrar en tema oscuro */
[data-theme="dark"] .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
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
        
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'dark' ? 'active' : ''; ?>" data-theme="dark">
                    <i class="fas fa-moon mb-2"></i>
                    <div>Oscuro</div>
                </div>
            </div>
            <div class="col-6">
                <div class="win-theme-option <?php echo $tema_windows == 'light' ? 'active' : ''; ?>" data-theme="light">
                    <i class="fas fa-sun mb-2"></i>
                    <div>Claro</div>
                </div>
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
        
        <button class="btn btn-primary w-100" onclick="guardarConfiguracion()">
            <i class="fas fa-save"></i> Guardar cambios
        </button>
    </div>

    <!-- Navbar principal -->
    <nav class="win-navbar mica-effect">
        <button class="btn btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="win-navbar-brand">
            <img src="assets/logov.png" alt="Logo" width="48" height="48">
            <span style="color: var(--win-text-primary);">PERFIL DE USUARIO - SISFACT PDL Visiones</span>
        </div>
        
        <div class="win-nav-search d-none d-md-block">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en el sistema...">
        </div>
        
        <div style="flex: 1;"></div>
        
        <button class="btn btn-outline-secondary" onclick="abrirPanelTemas()" title="Personalizar">
            <i class="fas fa-palette"></i>
        </button>
        
        <?= renderNotificationsDropdown() ?>
        
        <div class="dropdown">
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="win-sidebar-user-avatar">
                    <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo $avatar_placeholder; ?>
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
                        <div class="win-sidebar-user-avatar me-2" style="width: 32px; height: 32px;">
                            <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo $avatar_placeholder; ?>
                            <?php endif; ?>
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
                    <div class="win-sidebar-user-avatar position-relative overflow-hidden">
                        <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Avatar">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; 
                                        background: linear-gradient(135deg, var(--win-accent), color-mix(in srgb, var(--win-accent) 70%, #ffffff)); 
                                        color: white; font-weight: 600;">
                                <?php echo $avatar_placeholder; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="win-sidebar-user-info">
                        <h6 style="color: var(--win-text-primary);"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></h6>
                        <small style="color: var(--win-text-secondary);"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Administrador'); ?></small>
                    </div>
                </div>
            </div>
        </a>
        <ul class="win-nav">
            <li class="win-nav-item">
                <a href="dashboard.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-tachometer-alt"></i>
                    Dashboard
                    <span class="win-nav-badge" 
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
                    <i class="win-nav-icon fas fa-layer-group"></i>
                    <span class="win-nav-text">Categorías</span>
                    <span class="win-nav-badge"><?php echo isset($cant_categ) ? $cant_categ : '0'; ?></span>
                </a>
            </li>
            <li class="win-nav-item">
                <a href="servicios.php" class="win-nav-link">
                    <i class="win-nav-icon fas fa-list"></i>
                    <span class="win-nav-text">Servicios</span>
                    <span class="win-nav-badge"><?php echo isset($servicios_count) ? $servicios_count : '0'; ?></span>
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
            <li class="win-nav-item">
                <a href="perfil.php" class="win-nav-link active">
                    <i class="win-nav-icon fas fa-user"></i>
                    <span class="win-nav-text">Mi Perfil</span>
                    <span class="win-nav-badge"><?php echo $avatar_placeholder; ?></span>
                </a>
            </li>
        </ul>
        
        <?php
        $finanzas = Database::getProgresoFinanciero();
        ?>
        <div class="mt-4 px-3">
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

            <div class="progress" style="height: 6px; background-color: var(--win-bg-tertiary); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?php echo min($finanzas['porcentaje'], 100); ?>%; background-color: <?php echo $finanzas['color']; ?>;" 
                     aria-valuenow="<?php echo $finanzas['porcentaje']; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-2 align-items-center">
                <div class="d-flex flex-column">
                    <small class="text-muted" style="font-size: 12px;">
                        <strong>$<?php echo number_format($finanzas['real'], 2); ?></strong>
                    </small>
                    <small style="font-size: 12px; color: var(--win-text-secondary); opacity: 0.8;">
                        <i class="fas fa-file-invoice me-1"></i><?php echo $finanzas['cantidad']; ?> facturas
                    </small>
                </div>
                
                <small class="text-end text-success" style="font-size: 12px;">
                    Meta PLAN:<br>$<?php echo number_format($finanzas['meta'], 2); ?>
                </small>
            </div>
        </div>
    </aside>

    <!-- Contenido principal -->
    <main class="win-main-content <?php echo $sidebar_mini ? 'sidebar-mini' : ''; ?>">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2" style="color: var(--win-text-primary);">
                    <i class="fas fa-user me-2" style="color: var(--win-accent);"></i>Mi Perfil
                </h1>
                <p class="text-muted mb-0">Administra tu información personal y preferencias</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left me-1"></i>Volver
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Columna izquierda: Información del perfil -->
            <div class="col-lg-4 mb-4">
                <!-- Tarjeta de perfil -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0" style="color: var(--win-text-primary);"><i class="fas fa-id-card me-2"></i>Información del Perfil</h5>
                    </div>
                    <div class="card-body text-center">
                        <!-- Avatar con funcionalidad de recorte -->
                        <div class="profile-avatar-large mb-3" onclick="document.getElementById('foto_perfil').click()">
                            <?php if (!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($usuario['foto'] . '?t=' . time()); ?>" alt="Foto de perfil" id="profileImagePreview">
                            <?php else: ?>
                                <span id="profileImagePreview"><?php echo $avatar_placeholder; ?></span>
                                <img src="" style="display: none;" id="profileImagePreviewImg">
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        
                        <!-- Formulario oculto para subir foto -->
                        <form method="POST" action="" enctype="multipart/form-data" id="formFoto" style="display: none;">
                            <input type="hidden" name="imagen_recortada" id="imagen_recortada">
                        </form>
                        
                        <!-- Botón para cambiar foto -->
                        <button type="button" class="btn btn-outline-primary w-100 mt-2" onclick="document.getElementById('foto_perfil').click()">
                            <i class="fas fa-camera me-2"></i>Cambiar foto de perfil
                        </button>
                        
                        <!-- Input file oculto -->
                        <input type="file" id="foto_perfil" accept="image/*" style="display: none;" onchange="cargarImagenParaRecorte(this)">
                        
                        <small class="text-muted d-block mt-1">
							<i class="fas fa-image me-1"></i>
                            Formatos: JPG, PNG, GIF, WebP (Máx. 5MB)</small>
                        </small>
                        
                        <!-- Información del usuario -->
                        <h4 class="mb-1 mt-3" style="color: var(--win-text-primary);">
                            <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>
                        </h4>
                        
                        <!-- Rol y departamento -->
                        <div class="mb-3">
                            <span class="badge bg-primary mb-2">
                                <?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?>
                            </span>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="mt-4">
                            <h6 class="mb-2" style="color: var(--win-text-primary);">Información de la cuenta</h6>
                            <div class="text-start">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Usuario ID:</small>
                                    <small class="fw-bold">#<?php echo $usuario['id']; ?></small>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Fecha registro:</small>
                                    <small class="fw-bold">
                                        <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Estado:</small>
                                    <span class="badge <?php echo $usuario['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Antigüedad:</small>
                                    <small class="fw-bold">
                                        <?php 
                                            if ($antiguedad->y > 0) {
                                                echo $antiguedad->y . ' año' . ($antiguedad->y > 1 ? 's' : '');
                                            } elseif ($antiguedad->m > 0) {
                                                echo $antiguedad->m . ' mes' . ($antiguedad->m > 1 ? 'es' : '');
                                            } else {
                                                echo $antiguedad->d . ' día' . ($antiguedad->d > 1 ? 's' : '');
                                            }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tarjeta de actividad reciente -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0" style="color: var(--win-text-primary);"><i class="fas fa-history me-2"></i>Actividad Reciente</h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php
                            try {
                                $sql_actividad = "SELECT * FROM historico_operaciones 
                                                WHERE usuario_id = :usuario_id 
                                                ORDER BY fecha_hora DESC 
                                                LIMIT 5";
                                $stmt_actividad = $db->prepare($sql_actividad);
                                $stmt_actividad->execute(['usuario_id' => $_SESSION['usuario_id']]);
                                $actividades = $stmt_actividad->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($actividades)): ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-0">No hay actividad registrada</p>
                                    </div>
                                <?php else:
                                    foreach ($actividades as $actividad): ?>
                                        <div class="activity-item">
                                            <div class="activity-dot">
                                                <?php
                                                $icon = 'fa-info-circle';
                                                if (strpos($actividad['operacion'], 'LOGIN') !== false) $icon = 'fa-sign-in-alt';
                                                elseif (strpos($actividad['operacion'], 'LOGOUT') !== false) $icon = 'fa-sign-out-alt';
                                                elseif (strpos($actividad['operacion'], 'PERFIL') !== false) $icon = 'fa-user-edit';
                                                elseif (strpos($actividad['operacion'], 'PASSWORD') !== false) $icon = 'fa-key';
                                                elseif (strpos($actividad['operacion'], 'FACTURA') !== false) $icon = 'fa-file-invoice';
                                                elseif (strpos($actividad['operacion'], 'CLIENTE') !== false) $icon = 'fa-user-plus';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1" style="color: var(--win-text-primary);">
                                                        <?php echo htmlspecialchars($actividad['operacion']); ?>
                                                    </h6>
                                                    <small class="activity-time">
                                                        <?php echo date('g:i:s A', strtotime($actividad['fecha_hora'])); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-0 text-muted small">
                                                    <?php echo htmlspecialchars($actividad['descripcion']); ?>
                                                </p>
                                                <small class="text-muted d-block mt-1">
                                                    <?php echo date('d/m/Y', strtotime($actividad['fecha_hora'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif;
                            } catch (Exception $e) {
                                echo '<p class="text-muted text-center">Error al cargar actividad</p>';
                            }
                            ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="historial.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-history me-1"></i>Ver historial completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Edición de perfil -->
            <div class="col-lg-8 mb-4">
                <!-- Formulario de edición de perfil -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0" style="color: var(--win-text-primary);"><i class="fas fa-edit me-2"></i>Editar Información Personal</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formPerfil">
                            <input type="hidden" name="accion" value="actualizar_perfil">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                           value="<?php echo htmlspecialchars($usuario['apellidos'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="no_ci" class="form-label">Carnet de Identidad *</label>
                                    <input type="text" class="form-control" id="no_ci" name="no_ci" 
                                           value="<?php echo htmlspecialchars($usuario['no_ci'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="telefono_contacto" class="form-label">Teléfono de contacto</label>
                                    <input type="tel" class="form-control" id="telefono_contacto" name="telefono_contacto" 
                                           value="<?php echo htmlspecialchars($usuario['telefono_contacto'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="direccion_particular" class="form-label">Dirección particular</label>
                                    <textarea class="form-control" id="direccion_particular" name="direccion_particular" 
                                              rows="2"><?php echo htmlspecialchars($usuario['direccion_particular'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="usuario" class="form-label">Nombre de usuario</label>
                                    <input type="text" class="form-control" id="usuario" 
                                           value="<?php echo htmlspecialchars($usuario['usuario'] ?? ''); ?>" 
                                           readonly>
                                    <small class="text-muted">El nombre de usuario no se puede cambiar</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="rol_id" class="form-label">Rol</label>
                                    <select class="form-select" id="rol_id" name="rol_id" <?php echo ($usuario['rol_codigo'] != 'Admin' && $usuario['rol_codigo'] != 'Super') ? 'disabled' : ''; ?> >
                                        <?php foreach ($roles as $rol): ?>
                                            <option value="<?php echo $rol['id']; ?>" 
                                                <?php echo ($rol['id'] == $usuario['rol_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rol['descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($usuario['rol_codigo'] != 'Admin' && $usuario['rol_codigo'] != 'Super'): ?>
                                        <small class="text-muted">Solo los Administradores y Supervisores pueden cambiar el rol</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($usuario['email'] ?? 'No especificado'); ?>" 
                                           readonly>
                                    <small class="text-muted">Contacte al administrador para cambiar el email</small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i>Restablecer
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cambio de contraseña -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0" style="color: var(--win-text-primary);"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formPassword">
                            <input type="hidden" name="accion" value="cambiar_password">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password_actual" class="form-label">Contraseña actual</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_actual" 
                                               name="password_actual" required>
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="togglePassword('password_actual')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password_nueva" class="form-label">Nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_nueva" 
                                               name="password_nueva" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="togglePassword('password_nueva')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmar" class="form-label">Confirmar nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_confirmar" 
                                               name="password_confirmar" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="togglePassword('password_confirmar')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="resetPasswordForm()">
                                    <i class="fas fa-undo me-1"></i>Limpiar
                                </button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key me-1"></i>Cambiar contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para recortar imagen -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="background: var(--win-bg-secondary); color: var(--win-text-primary);">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-crop-alt me-2" style="color: var(--win-accent);"></i>
                        Recortar imagen a 7x7cm
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropper()"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="img-container" style="max-height: 500px; overflow: hidden; background: #333; border-radius: 8px; padding: 10px;">
                                <img id="imageToCrop" src="" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="mb-2">Vista previa 7x7cm</h6>
                                <div class="preview-container" style="width: 200px; height: 200px; margin: 0 auto; overflow: hidden; border: 3px solid var(--win-accent); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: #2d2d2d;">
                                    <canvas id="previewCanvas" width="200" height="200" style="width: 100%; height: 100%; display: block;"></canvas>
                                </div>
                            </div>

                            <div class="text-center mb-3">
                                <small class="text-muted d-block">
                                    <i class="fas fa-info-circle me-1"></i>
                                    La imagen final será de <strong>7x7cm (827x827 píxeles)</strong>
                                </small>
                            </div>

                            <div class="crop-controls p-3" style="background: var(--win-bg-tertiary); border-radius: 8px;">
                                <h6 class="mb-2">Controles</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(-90)">
                                        <i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" onclick="rotarImagen(90)">
                                        <i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetearCrop()">
                                        <i class="fas fa-sync-alt me-2"></i>Resetear recorte
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="zoomImagen(0.1)">
                                        <i class="fas fa-search-plus me-2"></i>Acercar
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="zoomImagen(-0.1)">
                                        <i class="fas fa-search-minus me-2"></i>Alejar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropper()">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="aplicarRecorte()">
                        <i class="fas fa-check me-2"></i>Aplicar recorte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>

    <script>
        // ==========================================
        // FUNCIONES PARA RECORTE DE IMAGEN
        // ==========================================

        let cropper = null;
        let previewCanvas = null;
        let previewCtx = null;

        function cargarImagenParaRecorte(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Validar tamaño (máximo 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'La imagen no debe superar los 5MB',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    });
                    input.value = '';
                    return;
                }

                // Validar tipo de archivo
                if (!file.type.match('image.*')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Solo se permiten archivos de imagen',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    });
                    input.value = '';
                    return;
                }

                // Mostrar loading
                Swal.fire({
                    title: 'Cargando imagen...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                });

                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageToCrop = document.getElementById('imageToCrop');
                    imageToCrop.src = e.target.result;

                    imageToCrop.onload = function() {
                        Swal.close();

                        previewCanvas = document.getElementById('previewCanvas');
                        previewCtx = previewCanvas.getContext('2d');
                        
                        previewCtx.fillStyle = '#2d2d2d';
                        previewCtx.fillRect(0, 0, 200, 200);

                        if (cropper) {
                            cropper.destroy();
                        }

                        cropper = new Cropper(imageToCrop, {
                            aspectRatio: 1,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            cropBoxResizable: true,
                            cropBoxMovable: true,
                            guides: true,
                            center: true,
                            highlight: true,
                            background: true,
                            responsive: true,
                            restore: true,
                            zoomable: true,
                            rotatable: true,
                            scalable: true,
                            wheelZoomRatio: 0.1,
                            minContainerWidth: 500,
                            minContainerHeight: 400,
                            crop: function(event) {
                                actualizarPreview(event);
                            },
                            ready: function() {
                                const containerData = cropper.getContainerData();
                                const cropBoxSize = Math.min(containerData.width, containerData.height) * 0.8;
                                cropper.setCropBoxData({
                                    width: cropBoxSize,
                                    height: cropBoxSize,
                                    left: (containerData.width - cropBoxSize) / 2,
                                    top: (containerData.height - cropBoxSize) / 2
                                });
                                
                                setTimeout(() => {
                                    const cropData = cropper.getData();
                                    actualizarPreview({ detail: cropData });
                                }, 100);
                            }
                        });

                        const modal = new bootstrap.Modal(document.getElementById('cropModal'));
                        modal.show();
                    };
                };
                reader.readAsDataURL(file);
            }
        }

        function actualizarPreview(event) {
            if (!cropper || !previewCtx) return;
            
            try {
                const canvas = cropper.getCroppedCanvas({
                    width: 200,
                    height: 200,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });
                
                if (canvas) {
                    previewCtx.drawImage(canvas, 0, 0, 200, 200);
                }
            } catch (error) {
                console.error('Error actualizando preview:', error);
            }
        }

        function rotarImagen(grados) {
            if (cropper) {
                cropper.rotate(grados);
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }

        function zoomImagen(factor) {
            if (cropper) {
                cropper.zoom(factor);
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }

        function resetearCrop() {
            if (cropper) {
                cropper.reset();
                setTimeout(() => {
                    const cropData = cropper.getData();
                    actualizarPreview({ detail: cropData });
                }, 50);
            }
        }

        function limpiarCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            if (previewCtx) {
                previewCtx.fillStyle = '#2d2d2d';
                previewCtx.fillRect(0, 0, 200, 200);
            }
            
            document.getElementById('foto_perfil').value = '';
        }

        function aplicarRecorte() {
            if (cropper) {
                try {
                    Swal.fire({
                        title: 'Procesando imagen...',
                        text: 'Aplicando recorte',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    });

                    const canvas = cropper.getCroppedCanvas({
                        width: 827,
                        height: 827,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });

                    const imagenRecortadaBase64 = canvas.toDataURL('image/jpeg', 0.9);

                    document.getElementById('imagen_recortada').value = imagenRecortadaBase64;

                    // Actualizar preview en el perfil
                    const profileImage = document.getElementById('profileImagePreview');
                    const profileImageImg = document.getElementById('profileImagePreviewImg');
                    
                    if (profileImage.tagName === 'SPAN') {
                        profileImage.style.display = 'none';
                        profileImageImg.src = imagenRecortadaBase64;
                        profileImageImg.style.display = 'block';
                    } else {
                        profileImage.src = imagenRecortadaBase64;
                    }

                    const modal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                    modal.hide();

                    limpiarCropper();

                    Swal.close();

                    // Enviar formulario automáticamente
                    document.getElementById('formFoto').submit();

                } catch (error) {
                    console.error('Error al recortar:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo procesar la imagen. Intente nuevamente.',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    });
                }
            }
        }

        // ==========================================
        // FUNCIONES EXISTENTES
        // ==========================================

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                button.className = 'fas fa-eye';
            }
        }

        function resetForm() {
            Swal.fire({
                title: '¿Restablecer formulario?',
                text: 'Se perderán los cambios no guardados',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-redo me-2"></i>Sí, restablecer',
                cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.reload();
                }
            });
        }

        function resetPasswordForm() {
            document.getElementById('password_actual').value = '';
            document.getElementById('password_nueva').value = '';
            document.getElementById('password_confirmar').value = '';
        }

        // Variables del sidebar y temas
        let sidebarMini = <?php echo $sidebar_mini ? 'true' : 'false'; ?>;
        let themePanelOpen = false;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.win-main-content');
            
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('open');
            } else {
                sidebarMini = !sidebarMini;
                sidebar.classList.toggle('mini');
                main.classList.toggle('sidebar-mini');
            }
        }

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

        document.getElementById('themeOverlay').addEventListener('click', cerrarPanelTemas);

        function guardarConfiguracion() {
            const tema = document.querySelector('.win-theme-option.active').dataset.theme;
            const color = document.querySelector('.win-color-option.active').dataset.color;
            const sidebarMini = document.getElementById('toggleSidebarMini').checked;
            
            Swal.fire({
                title: 'Guardando configuración...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
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
                        showConfirmButton: false,
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
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

        // Inicializar tema
        document.querySelectorAll('.win-theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.win-theme-option').forEach(opt => 
                    opt.classList.remove('active'));
                this.classList.add('active');
                
                const theme = this.dataset.theme;
                document.documentElement.setAttribute('data-theme', theme);
            });
        });

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

        // Confirmaciones de formularios
        document.addEventListener('DOMContentLoaded', function() {
            const formPerfil = document.getElementById('formPerfil');
            if (formPerfil) {
                formPerfil.addEventListener('submit', function(e) {
                    e.preventDefault();

                    Swal.fire({
                        title: '¿Guardar cambios?',
                        text: "Se actualizará tu información personal en el sistema",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0078d4',
                        cancelButtonColor: '#dc3545',
                        confirmButtonText: '<i class="fas fa-save me-1"></i> Sí, guardar',
                        cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Guardando...',
                                text: 'Por favor espere',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            this.submit();
                        }
                    });
                });
            }

            const formPassword = document.getElementById('formPassword');
            if (formPassword) {
                formPassword.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const nueva = document.getElementById('password_nueva').value;
                    const confirmar = document.getElementById('password_confirmar').value;

                    if (nueva !== confirmar) {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Error', 
                            text: 'Las contraseñas no coinciden',
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                        });
                        return;
                    }
                    if (nueva.length < 6) {
                        Swal.fire({ 
                            icon: 'warning', 
                            title: 'Seguridad', 
                            text: 'La contraseña debe tener al menos 6 caracteres',
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                        });
                        return;
                    }

                    Swal.fire({
                        title: '¿Cambiar contraseña?',
                        text: "Tendrás que usar la nueva contraseña en tu próximo inicio de sesión",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e81123',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-key me-1"></i> Sí, cambiar',
                        cancelButtonText: '<i class="fas fa-close me-2"></i>Cancelar',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Procesando...',
                                didOpen: () => { Swal.showLoading(); }
                            });
                            this.submit();
                        }
                    });
                });
            }

            // Prevenir reenvío con F5
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>

    <?php if (!empty($mensaje)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $tipo_mensaje; ?>',
                title: '<?php echo $tipo_mensaje === "success" ? "¡Excelente!" : "Atención"; ?>',
                text: '<?php echo str_replace("'", "\'", $mensaje); ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000'
            });
        });
    </script>
    <?php endif; ?>

    <?php
        if (file_exists('config/footer.php')) {
            include 'config/footer.php';
        }
    ?>
</body>
</html>