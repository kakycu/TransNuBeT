<?php
// registro.php
// Página pública de registro para nuevos usuarios

// ========================================================================
// 1. CONFIGURACIÓN INICIAL Y PROTECCIÓN CONTRA MANTENIMIENTO
// ========================================================================
session_start();


// ========================================================================
// AUTENTICACIÓN DE PROGRAMADOR (si se envió el formulario)
// ========================================================================
$programador_autenticado = false;
$auth_error = '';

// Verificar si se enviaron credenciales de programador
if (isset($_POST['programador_auth'])) {
    $programador_usuario = $_POST['programador_usuario'] ?? '';
    $programador_password = $_POST['programador_password'] ?? '';
    
    if (!empty($programador_usuario) && !empty($programador_password)) {
        try {
            require_once 'config/database.php';
            $db = Database::getConnection();
            
            // Buscar usuario con rol programador (rol_id = 5) y obtener información del rol
            $sql = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                    FROM clasif_usuarios u
                    LEFT JOIN clasif_rol r ON u.rol_id = r.id
                    WHERE u.usuario = :usuario 
                    AND u.rol_id = 5  -- Rol programador
                    AND u.activo = 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':usuario' => $programador_usuario]);
            $programador = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($programador) {
                if (password_verify($programador_password, $programador['password'])) {
                    // Autenticación exitosa
                    $_SESSION['programador_autenticado'] = true;
                    $_SESSION['programador_id'] = $programador['id'];
                    $_SESSION['programador_nombre'] = $programador['nombre'];
                    $_SESSION['programador_apellidos'] = $programador['apellidos'] ?? '';
                    $_SESSION['programador_rol'] = $programador['rol_nombre'] ?? 'Programador';
                    $_SESSION['programador_rol_codigo'] = $programador['rol_codigo'] ?? '';
                    $programador_autenticado = true;
                    
                    // Redirigir para limpiar POST
                    header('Location: registro.php?access_bypass=true');
                    exit();
                } else {
                    $auth_error = 'Credenciales incorrectas, verifique su Contraseña';
                    // Mostrar información del usuario encontrado (pero con contraseña incorrecta)
                    if (isset($programador['nombre']) && isset($programador['apellidos'])) {
                        $auth_error .= '. Usuario Encontrado: ' . $programador['nombre'] . ' ' . 
                                     $programador['apellidos'] . ' (Rol: ' . 
                                     ($programador['rol_nombre'] ?? 'Programador') . ')';
                    }
                }
            } else {
                // Usuario no encontrado o no es programador
                $auth_error = 'Usuario no encontrado o no tiene rol de programador';
                
                // Verificar si el usuario existe pero no es programador
                $sql_usuario_no_programador = "SELECT u.*, r.descripcion as rol_nombre, r.codigo as rol_codigo
                                              FROM clasif_usuarios u
                                              LEFT JOIN clasif_rol r ON u.rol_id = r.id
                                              WHERE u.usuario = :usuario 
                                              AND u.activo = 1";
                
                $stmt_usuario = $db->prepare($sql_usuario_no_programador);
                $stmt_usuario->execute([':usuario' => $programador_usuario]);
                $usuario_no_programador = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario_no_programador) {
                    $auth_error .= '. Las Credenciales de: ' . 
                                 $usuario_no_programador['nombre'] . ' ' . 
                                 ($usuario_no_programador['apellidos'] ?? '') . 
                                 ' (Rol actual: ' . 
                                 ($usuario_no_programador['rol_nombre'] ?? 'Desconocido') . 
                                 ' - No son de un usuario Programador)';
                }
            }
        } catch (Exception $e) {
            $auth_error = 'Error en la autenticación: ' . $e->getMessage();
        }
    } else {
        $auth_error = 'Usuario y contraseña requeridos';
    }
}

if (isset($_POST['logout_programador'])) {
    // Registrar información del programador que cerró sesión (opcional para logs)
    $programador_info = '';
    if (isset($_SESSION['programador_nombre']) && isset($_SESSION['programador_apellidos'])) {
        $programador_info = $_SESSION['programador_nombre'] . ' ' . $_SESSION['programador_apellidos'];
    }
    
    unset($_SESSION['programador_autenticado']);
    unset($_SESSION['programador_id']);
    unset($_SESSION['programador_nombre']);
    unset($_SESSION['programador_apellidos']);
    unset($_SESSION['programador_rol']);
    unset($_SESSION['programador_rol_codigo']);
    
    // Redirigir sin parámetros
    header('Location: registro.php');
    exit();
}

// Verificar si ya está autenticado como programador
if (isset($_SESSION['programador_autenticado']) && $_SESSION['programador_autenticado'] === true) {
    $programador_autenticado = true;
}


// ========================================================================
// 2. MANEJADOR DE ESTADO (AJAX) - Mismo que en login.php
// ========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    $response = ['db_ok' => false, 'maintenance' => false];
    
    try {
        // PRIMERO: Incluir el archivo que define Database
        if (file_exists('config/database.php')) {
            require_once 'config/database.php';
            
            // Verificar si la clase Database existe
            if (class_exists('Database')) {
                $db = Database::getConnection();
                $response['db_ok'] = true;
                
                // Verificar mantenimiento
                try {
                    $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && isset($result['modo_mantenimiento'])) {
                        $valor = $result['modo_mantenimiento'];
                        // Convertir a booleano
                        $response['maintenance'] = ($valor !== 0);
                    }
                } catch (Exception $e) { 
                    // Error en consulta, mantenemos false
                    error_log("Error en consulta mantenimiento: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: Clase Database no existe");
            }
        } else {
            error_log("ERROR: Archivo config/database.php no existe");
        }
    } catch (Exception $e) { 
        $response['db_ok'] = false; 
        error_log("Error general: " . $e->getMessage());
    }
    
    // Limpiar buffer de salida
    if (ob_get_length()) ob_clean();
    
    echo json_encode($response);
    exit();
}

// ========================================================================
// 3. VERIFICAR SI EL SISTEMA ESTÁ EN MANTENIMIENTO
// ========================================================================
$en_mantenimiento = false;
$es_programador = isset($_GET['access_bypass']) && $_GET['access_bypass'] === 'true';

// Solo verificar mantenimiento si NO es bypass
if (!$es_programador) {
    try {
        if (file_exists('config/database.php')) {
            require_once 'config/database.php';
            $db = Database::getConnection();
            
            // Verificar mantenimiento
            $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] !== 0) {
                $en_mantenimiento = true;
            }
        }
    } catch (Exception $e) {
        // Si hay error en la base de datos, permitir registro (fallback)
        $en_mantenimiento = false;
    }
}

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

// ========================================================================
// 4. PROCESAMIENTO DEL FORMULARIO DE REGISTRO
// ========================================================================
$error = '';
$exito = '';
$datos_usuario = [
    'nombre' => '',
    'apellidos' => '',
    'no_ci' => '',
    'direccion_particular' => '',
    'telefono_contacto' => '',
    'email' => '',
    'usuario' => ''
];

// Inicializar array de roles disponibles
$roles_disponibles = [];

if (file_exists('config/database.php')) {
    require_once 'config/database.php';
}

try {
    $db = Database::getConnection();
    
    // Obtener roles disponibles (solo rol 2 - Visualizador para registro público)
    $stmt_roles = $db->query("SELECT * FROM clasif_rol WHERE id = 2 ORDER BY descripcion ASC");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar Formulario POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['programador_auth'])) {
        $nombre = trim($_POST['nombre']);
        $apellidos = trim($_POST['apellidos']);
        $no_ci = trim($_POST['no_ci']);
        $direccion = trim($_POST['direccion_particular']);
        $telefono = trim($_POST['telefono_contacto']);
        $email = trim($_POST['email']);
        $usuario_login = trim($_POST['usuario']);
        $password = $_POST['password'];
        // CORRECCIÓN: Verificar si existe confirm_password antes de usarlo
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // VALIDACIONES
        if (empty($nombre) || empty($apellidos) || empty($no_ci) || 
            empty($email) || empty($usuario_login) || empty($password)) {
            throw new Exception("Todos los campos marcados con * son obligatorios.");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("Las contraseñas no coinciden.");
        }
        
        // VALIDACIÓN PHP: EMAIL
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }
        
        // VALIDACIÓN PHP: TELÉFONO
        if (!empty($telefono) && !preg_match('/^[+]?[0-9]{8,15}$/', $telefono)) { 
            throw new Exception("El teléfono no es válido. Solo números y '+' (mínimo 8 dígitos).");
        }
        
        // VALIDACIÓN PHP: CI
        if (!preg_match('/^\d{11}$/', $no_ci)) {
            throw new Exception("El CI debe tener 11 dígitos numéricos.");
        }
        
        // Verificar si el usuario, email o CI ya existen
        $stmt_check = $db->prepare("SELECT id FROM clasif_usuarios WHERE usuario = :u OR email = :e OR no_ci = :ci");
        $stmt_check->execute([
            ':u' => $usuario_login,
            ':e' => $email,
            ':ci' => $no_ci
        ]);
        
        if ($stmt_check->rowCount() > 0) {
            throw new Exception("Los campos usuario, email o CI ya existen en la base d datos del sistema.");
        }
        
        // Configuración predeterminada para nuevos usuarios
        $rol_id = 2; // Visualizador por defecto
        $activo = 0; // Inactivo hasta aprobación del administrador
        $ruta_foto = '';
        
        // Manejar foto de perfil
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/usuarios/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'user_' . time() . '_' . uniqid() . '.' . $file_extension;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_filename)) {
                    $ruta_foto = $upload_dir . $new_filename;
                }
            }
        }
        
        // INSERTAR NUEVO USUARIO
$id_para_insertar = 0;

// 1. Intentar encontrar el primer hueco en la secuencia de IDs
// Esta consulta busca el primer 'id' donde 'id + 1' no existe.
// COALESCE(MIN(t1.id + 1), 1) asegura que si la tabla está vacía, devuelve 1.
// Ejemplo: Si IDs son 1,2,5,6, buscará el 3. Si IDs son 1,2,3, buscará el 4.
$sql_find_gap = "
    SELECT COALESCE(MIN(t1.id + 1), 1)
    FROM clasif_usuarios t1
    LEFT JOIN clasif_usuarios t2 ON t1.id + 1 = t2.id
    WHERE t2.id IS NULL;
";
$stmt_find_gap = $db->prepare($sql_find_gap);
$stmt_find_gap->execute();
$id_para_insertar = $stmt_find_gap->fetchColumn();

// Asegurarse de que el ID es un entero positivo
$id_para_insertar = (int)$id_para_insertar;
if ($id_para_insertar <= 0) {
    $id_para_insertar = 1; // Fallback por seguridad
}
// --- Fin de la lógica para encontrar el ID ---


// INSERTAR NUEVO USUARIO con el ID calculado
$sql_insert = "INSERT INTO clasif_usuarios
    (id, nombre, apellidos, no_ci, direccion_particular, telefono_contacto,
    rol_id, foto, usuario, password, email, activo, fecha_registro)
    VALUES
    (:id_val, :n, :a, :ci, :d, :t, :r, :f, :u, :p, :e, :act, NOW())"; // Añadir ':id_val' aquí

$stmt = $db->prepare($sql_insert);
$stmt->execute([
    ':id_val' => $id_para_insertar, // Pasar el ID calculado aquí
    ':n' => $nombre,
    ':a' => $apellidos,
    ':ci' => $no_ci,
    ':d' => $direccion,
    ':t' => $telefono,
    ':r' => $rol_id,
    ':f' => $ruta_foto,
    ':u' => $usuario_login,
    ':p' => password_hash($password, PASSWORD_DEFAULT),
    ':e' => $email,
    ':act' => $activo
]);

// Registrar en histórico
// Importante: Cuando se inserta un ID explícitamente, lastInsertId() podría no ser fiable o devolver 0
// Usamos el ID que acabamos de insertar.
$usuario_id = $id_para_insertar; // Usar el ID que se acaba de asignar
$sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address)
                VALUES (?, ?, ?, ?, ?)";
$stmt_log = $db->prepare($sql_log);
$stmt_log->execute([
    'REGISTRO',
    'Nuevo usuario registrado desde formulario público',
    $usuario_id, // Usar el ID explícitamente asignado
    $nombre . ' ' . $apellidos,
    $_SERVER['REMOTE_ADDR']
]);

        
        // Éxito - mostrar mensaje y limpiar formulario
        $exito = "¡Registro exitoso! Su cuenta ha sido creada. Un administrador la activará próximamente.";
        $datos_usuario = [
            'nombre' => '',
            'apellidos' => '',
            'no_ci' => '',
            'direccion_particular' => '',
            'telefono_contacto' => '',
            'email' => '',
            'usuario' => ''
        ];
        
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    
    // Persistencia de datos en caso de error
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $datos_usuario['nombre'] = $_POST['nombre'] ?? '';
        $datos_usuario['apellidos'] = $_POST['apellidos'] ?? '';
        $datos_usuario['no_ci'] = $_POST['no_ci'] ?? '';
        $datos_usuario['direccion_particular'] = $_POST['direccion_particular'] ?? '';
        $datos_usuario['telefono_contacto'] = $_POST['telefono_contacto'] ?? '';
        $datos_usuario['email'] = $_POST['email'] ?? '';
        $datos_usuario['usuario'] = $_POST['usuario'] ?? '';
    }
}
// Config WhatsApp
function obtenerConfiguracionWhatsApp() {
    try {
        $db = Database::getConnection();
        $sql = "SELECT whatsapp_numero, whatsapp_ON FROM configuracion_sistema LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch();
        return $config ? ['whatsapp_numero' => $config['whatsapp_numero'] ?? null, 'whatsapp_ON' => $config['whatsapp_ON'] ?? 0, 'whatsapp_activo' => ($config['whatsapp_ON'] == 1)] : ['whatsapp_numero' => null, 'whatsapp_ON' => 0, 'whatsapp_activo' => false];
    } catch (Exception $e) { return ['whatsapp_numero' => null, 'whatsapp_ON' => 0, 'whatsapp_activo' => false]; }
}
$configWhatsApp = obtenerConfiguracionWhatsApp();
$whatsapp_numero_formateado = ($configWhatsApp['whatsapp_activo'] && $configWhatsApp['whatsapp_numero']) ? preg_replace('/\D/', '', $configWhatsApp['whatsapp_numero']) : null;
if ($whatsapp_numero_formateado && strlen($whatsapp_numero_formateado) === 8 && !str_starts_with($whatsapp_numero_formateado, '53')) $whatsapp_numero_formateado = '53' . $whatsapp_numero_formateado;
// ========================================================================
// 5. HTML - PÁGINA DE REGISTRO PÚBLICA
// ========================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - SISFACT PDL Visiones</title>
    <link rel="icon" type="image/x-icon" href="assets/logov.png">
    
    <!-- Bootstrap 5 -->
    <link href="css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    
    <style>
        :root {
            --primary-color: #0078d4;
            --primary-light: rgba(0, 120, 212, 0.1);
            --success-color: #107c10;
            --danger-color: #e81123;
            --warning-color: #ff8c00;
            --bg-dark: #0a0f1a;
            --bg-card: rgba(10, 20, 35, 0.98);
            --text-light: #f8f9fa;
            --text-muted: #a0c8ff;
            --border-color: rgba(255, 255, 255, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            position: relative;
            line-height: 1.2;
        }
        
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('assets/login.avif') center/cover no-repeat;
            filter: brightness(0.3) saturate(0.8) blur(2px);
            z-index: -2;
        }
        
        body::after {
            content: "";
            position: fixed;
            right: -8%;
            top: -8%;
            width: 35%;
            height: 35%;
            background: radial-gradient(circle, rgba(0, 123, 255, 0.1), transparent 60%);
            filter: blur(50px);
            z-index: -1;
        }
        
        .registro-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            min-height: 0;
            overflow: hidden;
        }
        
        .registro-wrapper {
            width: 100%;
            max-width: 950px;
            height: 95vh;
            max-height: 750px;
            display: flex;
            flex-direction: column;
        }
        
        .registro-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 1);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .registro-header {
            padding: 5px 18px 8px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 0px;
        }
        
        .registro-logo {
            width: 50px;
            height: 60px;
            filter: drop-shadow(0 3px 6px rgba(0, 123, 255, 0.2));
        }
        
        .titles-container {
            display: flex;
            flex-direction: column;
            line-height: 0.5;
        }
        
        .main-title {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #007bff, #0056b3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
            line-height: 1.5;
        }
        
        .sub-title {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin-top: 2px;
            line-height: 0.5;
        }
        
        .back-to-login {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.82rem;
            padding: 5px 10px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            background: rgba(0, 0, 0, 0.2);
            line-height: 1;
        }
        
        .back-to-login:hover {
            background: rgba(0, 120, 212, 0.15);
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .registro-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            opacity: 1;
            text-align: left;
            margin-top: 4px;
            padding-top: 0px;
            border-top: 1px dashed rgba(255, 255, 255, 0.05);
            line-height: 0.5;
        }
        
        .subtitle-icon {
            color: #007bff;
            margin-right: 4px;
        }
        
        /* Estilos para alerts con botón de cierre */
        .alert-registro {
            border-radius: 7px;
            border: none;
            padding: 10px 35px 10px 15px;
            margin-bottom: 12px;
            font-size: 0.82rem;
            line-height: 1.2;
            position: relative;
            animation: fadeInAlert 0.5s ease;
        }
        
        @keyframes fadeInAlert {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: rgba(16, 124, 16, 0.15);
            border-left: 3px solid var(--success-color);
            color: #b3ffb3;
        }
        
        .alert-danger {
            background: rgba(232, 17, 35, 0.15);
            border-left: 3px solid var(--danger-color);
            color: #ffb3b3;
        }
        
        .alert-close-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: transparent;
            border: none;
            color: inherit;
            font-size: 0.9rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s ease;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .alert-close-btn:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .ya-tengo-cuenta-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.82rem;
            padding: 6px 0;
            transition: all 0.2s ease;
            margin-top: 4px;
            line-height: 1;
        }
        
        .ya-tengo-cuenta-btn:hover {
            color: var(--primary-color);
        }
        
        .registro-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px 18px;
            display: flex;
            flex-direction: column;
        }
        
        .form-scroll-container {
            flex: 1;
            overflow-y: auto;
            padding-right: 6px;
        }
        
        /* Personalizar scrollbar */
        .form-scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .form-scroll-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 2px;
        }
        
        .form-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 0;
            height: 100%;
        }
        
        .form-column {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .form-section {
            margin-bottom: 12px;
            padding: 5px;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 9px;
            border-left: 3px solid var(--primary-color);
            flex-shrink: 0;
            line-height: 1;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.1;
        }
        
        .section-title i {
            font-size: 0.9rem;
        }
        
        .form-label {
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 0.82rem;
            line-height: 1.1;
        }
        
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 7px 9px;
            border-radius: 6px;
            font-size: 0.82rem;
            height: 36px;
            transition: all 0.2s ease;
            line-height: 1.2;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.4);
            border-color: var(--primary-color);
            color: var(--text-light);
            box-shadow: 0 0 0 0.15rem rgba(0, 120, 212, 0.2);
        }
        
        textarea.form-control {
            height: auto;
            min-height: 65px;
            resize: vertical;
            font-size: 0.82rem;
            line-height: 1.2;
            padding-top: 6px;
            padding-bottom: 6px;
        }
        
        .input-group-text {
            background-color: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.82rem;
            padding: 0 8px;
            line-height: 1;
        }
        
        .password-meter {
            height: 3px;
            background: rgba(255, 255, 255, 0.08);
            margin-top: 4px;
            border-radius: 1.5px;
            overflow: hidden;
        }
        
        .meter-bar {
            height: 100%;
            width: 0%;
            transition: all 0.2s ease;
        }
        
        .meter-text {
            font-size: 9px;
            margin-top: 3px;
            text-align: right;
            min-height: 12px;
            line-height: 1;
        }
        
        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            background: rgba(0, 0, 0, 0.15);
            border-radius: 9px;
            margin-bottom: 12px;
            line-height: 1.2;
        }
        
        .avatar-preview {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            overflow: hidden;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-placeholder {
            font-size: 34px;
            color: var(--text-muted);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border: none;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 7px;
            transition: all 0.2s ease;
            width: 100%;
            line-height: 1.1;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 120, 212, 0.25);
        }
        
        .btn-outline-light {
            border-color: var(--border-color);
            color: var(--text-light);
            padding: 8px 16px;
            font-size: 0.82rem;
            border-radius: 7px;
            line-height: 1.1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--text-light);
        }
        
        .login-link {
            text-align: center;
            margin: 10px 0 6px;
            color: var(--text-muted);
            font-size: 0.82rem;
            line-height: 1.2;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin-left: 3px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .required::after {
            content: " *";
            color: var(--danger-color);
            font-size: 0.85em;
        }
        
        .form-check {
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .form-check-input {
            background-color: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            width: 1em;
            height: 1em;
            margin-top: 0.12em;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            color: var(--text-light);
            font-size: 0.82rem;
            line-height: 1.2;
            padding-left: 5px;
        }
        
        .form-check-label a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        .terms-info {
            background: rgba(228, 17, 10, 0.8);
            border-radius: 5px;
            padding: 8px;
            margin-top: 6px;
            font-size: 0.88rem;
            line-height: 1.2;
        }
        
        .registro-footer {
            padding: 10px 18px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            flex-shrink: 0;
            line-height: 1.1;
        }
        
        .footer-text {
            color: var(--text-muted);
            font-size: 0.72rem;
            margin-bottom: 2px;
            line-height: 1.1;
        }
        
        .footer-version {
            color: var(--text-muted);
            font-size: 0.68rem;
            opacity: 0.7;
            line-height: 1;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }
        
        /* MODAL MANTENIMIENTO (igual que en login.php) */
        .win11-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);

            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .win11-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .win11-modal {
            background: rgba(32, 32, 32, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.1, 0.9, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .win11-modal-overlay.active .win11-modal {
            transform: scale(1);
        }
        
        .win11-modal-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(255, 255, 255, 0.02);
        }
        
        .modal-title-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }
        
        .win11-modal-close {
            background: none;
            border: none;
            color: #aaa;
            font-size: 1rem;
            padding: 5px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .win11-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .win11-modal-body {
            padding: 25px 25px;
            color: #ddd;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .win11-modal-footer {
            padding: 15px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-btn {
            padding: 8px 20px;
            border-radius: 4px;
            border: 1px solid transparent;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .modal-btn-primary {
            background: #0078d4;
            color: #fff;
            border: 1px solid #0078d4;
        }
        
        .modal-btn-primary:hover {
            background: #006cc1;
        }
        
        .modal-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .maintenance-overlay {
            background: rgba(0, 0, 0, 0.95) !important;
            backdrop-filter: blur(10px);
            z-index: 99999 !important;
        }
        
        .maintenance-icon-pulse {
            animation: pulseMaint 2s infinite;
            color: #ffc107;
        }
        
        @keyframes pulseMaint {
            0% {
                transform: scale(1);
                text-shadow: 0 0 0 rgba(255, 193, 7, 0.7);
            }
            50% {
                transform: scale(1.1);
                text-shadow: 0 0 20px rgba(255, 193, 7, 1);
            }
            100% {
                transform: scale(1);
                text-shadow: 0 0 0 rgba(255, 193, 7, 0.7);
            }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .registro-wrapper {
                height: 100vh;
                max-height: none;
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-column {
                min-height: auto;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .registro-header {
                padding: 10px 14px 6px;
            }
            
            .registro-body {
                padding: 10px 14px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .header-top {
                flex-direction: column;
                gap: 6px;
                text-align: center;
            }
            
            .logo-section {
                justify-content: center;
            }
            
            .back-to-login {
                margin-top: 4px;
            }
        }
        
        @media (max-height: 700px) {
            .registro-wrapper {
                max-height: 680px;
            }
            
            .registro-header {
                padding: 10px 14px 5px;
            }
            
            .registro-body {
                padding: 10px 14px;
            }
            
            .form-section {
                padding: 10px;
                margin-bottom: 10px;
            }
            
            .section-title {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            
            .form-control, .form-select {
                padding: 6px 8px;
                height: 34px;
                font-size: 0.8rem;
            }
            
            textarea.form-control {
                min-height: 60px;
            }
            
            .avatar-preview {
                width: 75px;
                height: 75px;
            }
            
            .avatar-placeholder {
                font-size: 28px;
            }
        }
        
        @media (max-height: 600px) {
            .registro-wrapper {
                max-height: 580px;
            }
            
            .avatar-preview {
                width: 65px;
                height: 65px;
            }
            
            .avatar-placeholder {
                font-size: 24px;
            }
            
            .form-section {
                padding: 8px;
                margin-bottom: 8px;
            }
            
            .form-grid {
                gap: 8px;
                margin-bottom: 8px;
            }
        }
/* Estilos para el modal de autenticación - Coinciden con el diseño principal */
.win11-modal-overlay#authProgramadorModal {
    z-index: 10001; /* Un poco más alto que el modal de mantenimiento */
}

.win11-modal-overlay#authProgramadorModal .win11-modal {
    animation: modalAppear 0.3s cubic-bezier(0.1, 0.9, 0.2, 1);
}

@keyframes modalAppear {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Estilos para inputs dentro del modal */
.win11-modal-overlay#authProgramadorModal input {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

.win11-modal-overlay#authProgramadorModal input::placeholder {
    color: rgba(160, 200, 255, 0.5);
}

/* Efecto hover para botón de mostrar contraseña */
.win11-modal-overlay#authProgramadorModal button[type="button"]:active {
    transform: scale(0.95);
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
    opacity: 0.4 !important;
}

[data-theme="light"] ::placeholder {
    color: #666666 !important;
}
    </style>
</head>
<body>
    <!-- MODAL MANTENIMIENTO -->
    <div class="win11-modal-overlay maintenance-overlay <?php echo $en_mantenimiento ? 'active' : ''; ?>" 
     id="maintenanceModal" data-no-close-outside="true">
      <div class="win11-modal" style="border: 1px solid #ffc107; box-shadow: 0 0 40px rgba(255, 193, 7, 0.2); width: 900px; max-width: 900px; max-height: 700px;">
        
        <!-- Header -->
        <div class="win11-modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.3);">
          <div class="modal-title-wrapper">
            <div class="modal-icon maintenance-icon-pulse">
              <i class="fa-solid fa-helmet-safety"></i>
            </div>
            <h3 class="modal-title" style="color: #ffc107;">🛠️ Modo Mantenimiento Activo</h3>
          </div>
        </div>
        
        <!-- Body -->
        <div class="win11-modal-body" style="padding: 15px;">
          <p style="font-size: 1.1em; color: #fff; margin-bottom: 20px; text-align: center;">
            <strong>SISFACT PDL Visiones</strong> se encuentra actualmente en modo de mantenimiento. 
            Estamos realizando mejoras para ofrecerte un mejor servicio.
          </p>

          <!-- Bloque: Qué está pasando -->
          <div style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 10px; margin-bottom: 25px; border-left: 3px solid #0dcaf0;">
              <h5 style="color: #0dcaf0; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center;">
                  <i class="fa-solid fa-circle-info me-2"></i> ¿Qué está pasando?
              </h5>
              
              <div style="color: #ccc; line-height: 0.9;  max-height: 800px;">
                  <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                      <i class="fa-solid fa-arrow-up-right-dots me-3" style="color: #0dcaf0; margin-top: 3px;"></i>
                      <div>
                          <strong style="color: #fff;">Actualización del sistema</strong>
                          <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                              Mejoras en funcionalidades y corrección de errores
                          </div>
                      </div>
                  </div>
                  
                  <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                      <i class="fa-solid fa-shield-halved me-3" style="color: #20c997; margin-top: 3px;"></i>
                      <div>
                          <strong style="color: #fff;">Mejoras de seguridad</strong>
                          <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                              Implementación de nuevas medidas de protección
                          </div>
                      </div>
                  </div>
                  
                  <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                      <i class="fa-solid fa-gauge-high me-3" style="color: #ff6b6b; margin-top: 3px;"></i>
                      <div>
                          <strong style="color: #fff;">Optimización de rendimiento</strong>
                          <div style="color: #aaa; font-size: 0.9em; margin-top: 3px;">
                              Velocidad mejorada y mayor estabilidad
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <div style="background: rgba(255, 193, 7, 0.05); border-radius: 8px; padding: 15px; border: 1px dashed rgba(255, 193, 7, 0.3);">
            <h5 style="color: #ffc107; font-size: 0.95rem; margin-bottom: 10px; text-align: center;">Para emergencias, contacta con soporte:</h5>
        <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <?php if ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado): ?>
            <a href="https://api.whatsapp.com/send?phone=<?php echo $whatsapp_numero_formateado; ?>" target="_blank" 
               class="btn btn-sm btn-outline-success" style="border-radius: 20px; text-decoration: none; color: white; border: 1px solid white; padding: 2px 10px;">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </a>
            <?php endif; ?>
            <a href="mailto:soporte_pdlvisiones@gmail.com" class="btn btn-sm btn-outline-light" style="border-radius: 20px; text-decoration: none; color: white; border: 1px solid white; padding: 2px 10px;">
                <i class="fa-solid fa-envelope me-1"></i> Correo
            </a>
            <a href="tel:+5359860773" class="btn btn-sm btn-outline-info" style="border-radius: 20px; text-decoration: none; color: #0dcaf0; border: 1px solid #0dcaf0; padding: 2px 10px;">
                <i class="fa-solid fa-phone me-1"></i> +53 5986 0773
            </a>
        </div>
          </div>
        </div>
        
        <!-- Footer -->
    <div class="win11-modal-footer" style="justify-content: space-between; background: rgba(0,0,0,0.2);">
      
      <!-- GRUPO IZQUIERDA: Acciones administrativas/visuales -->
      <div style="display: flex; gap: 10px;">
          <button class="modal-btn modal-btn-secondary" onclick="window.location.href='?access_bypass=true'">
            <i class="fa-solid fa-unlock-keyhole me-1"></i> 🔓 Acceso Programador
          </button>

          <!-- NUEVO BOTÓN: Solo mirar -->
          <button class="modal-btn modal-btn-secondary" onclick="closeMaintenanceModal()">
            <i class="fa-solid fa-eye me-1"></i> Solo mirar
          </button>
      </div>

      <!-- GRUPO DERECHA: Navegación y Estado -->
      <div style="display: flex; gap: 10px;">
          <button class="modal-btn modal-btn-primary" onclick="window.location.href='login.php<?php echo isset($es_programador) && $es_programador ? '?access_bypass=true' : ''; ?>'">
            <i class="fa-solid fa-home"></i> Inicio
          </button>
          
          <button id="btn-recheck-maint" class="modal-btn modal-btn-primary" onclick="recheckMaintenanceStatus()" 
                  style="background-color: #ffc107; color: #000; border: none; font-weight: bold;">
            <i class="fa-solid fa-rotate-right me-2"></i> Volver a comprobar
          </button>
      </div>
    </div>




      </div>
    </div>

    <div class="registro-container">
        <div class="registro-wrapper">
            <div class="registro-card">
                <!-- Header -->
                <div class="registro-header">
                    <div class="header-top">
                        <div class="logo-section">
                            <img src="assets/logov.png" alt="Logo PDL Visiones" class="registro-logo">
                            <div class="titles-container">
                                <h1 class="main-title">Registro de Usuario</h1>
                                <div class="sub-title">Registro para Nuevos Usuarios en el Sistema de Facturación - SISFACT PDL Visiones</div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="registro-subtitle">
                        <i class="fas fa-clipboard-list subtitle-icon"></i>
                        Complete el formulario para crear su cuenta en SISFACT PDL Visiones
                    </p>
                    
                    
<!-- Mantenemos las alertas HTML originales -->
<?php if ($exito): ?>
    <div class="alert alert-success alert-registro animate__animated animate__fadeIn mt-2">
        <i class="fas fa-check-circle me-1"></i> <?php echo $exito; ?>
        <button type="button" class="alert-close-btn" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-registro animate__animated animate__fadeIn mt-2">
        <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
        <button type="button" class="alert-close-btn" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<!-- Script unificado para SweetAlerts - Adaptado al estilo de tu página -->
<?php if ($exito || $error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                <?php if ($exito): ?>
                    Swal.fire({
                        title: '<div class="swal-title-container"><i class="fas fa-check-circle fa-lg me-2" style="color: #28a745;"></i><span style="color: #f8f9fa;">¡Operación Exitosa!</span></div>',
                        html: '<div class="swal-content"><?php echo addslashes($exito); ?></div>',
                        background: '#0a0f1a',
                        color: '#f8f9fa',
                        icon: false,
                        showCloseButton: true,
                        closeButtonHtml: '<i class="fas fa-times"></i>',
                        showConfirmButton: true,
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Aceptar',
                        confirmButtonColor: '#0078d4',
                        buttonsStyling: false,
						allowOutsideClick: false,
                        customClass: {
                            popup: 'swal-dark-theme',
                            closeButton: 'swal-close-btn',
                            confirmButton: 'swal-confirm-btn',
                            title: 'swal-title'
                        },
                        backdrop: 'rgba(0,0,0,0.7)',
                        showClass: {
                            popup: 'animate__animated animate__fadeInUp'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOut'
                        }
                    });
                <?php elseif ($error): ?>
                    Swal.fire({
                        title: '<div class="swal-title-container"><i class="fas fa-exclamation-triangle fa-lg me-2" style="color: #e81123;"></i><span style="color: #f8f9fa;">Error en la Operación</span></div>',
                        html: '<div class="swal-content"><?php echo addslashes($error); ?></div>',
                        background: '#0a0f1a',
                        color: '#f8f9fa',
                        icon: false,
                        showCloseButton: true,
                        closeButtonHtml: '<i class="fas fa-times"></i>',
                        showConfirmButton: true,
                        confirmButtonText: '<i class="fas fa-times me-2"></i>Entendido',
                        confirmButtonColor: '#e81123',
                        buttonsStyling: false,
                        customClass: {
                            popup: 'swal-dark-theme',
                            closeButton: 'swal-close-btn',
                            confirmButton: 'swal-confirm-btn-error',
                            title: 'swal-title'
                        },
                        backdrop: 'rgba(0,0,0,0.7)',
                        allowOutsideClick: false,
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOut'
                        }
                    });
                <?php endif; ?>
            }, 100);
        });
    </script>
    
    <style>
        /* SweetAlert personalizado para coincidir con el estilo de la página */
        .swal-dark-theme {
            border: 2px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 14px !important;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 1) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        }
        
        .swal-close-btn {
            color: #adb5bd !important;
            font-size: 1rem !important;
            width: 2rem !important;
            height: 2rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 50% !important;
            transition: all 0.3s ease !important;
            top: 0.75rem !important;
            right: 0.75rem !important;
            background: transparent !important;
            border: none !important;
        }
        
        .swal-close-btn:hover {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
            transform: rotate(90deg) !important;
        }
        
        .swal-title {
            color: #f8f9fa !important;
            font-size: 1.3rem !important;
            font-weight: 700 !important;
            padding-bottom: 0.75rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            margin-bottom: 1rem !important;
            text-align: left !important;
        }
        
        .swal-title-container {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
        }
        
        .swal-content {
            padding: 0.5rem 0 !important;
            line-height: 1.5 !important;
            font-size: 0.95rem !important;
            color: #e9ecef !important;
            text-align: left !important;
        }
        
        /* Botón de confirmación general */
        .swal-confirm-btn {
            background: linear-gradient(135deg, #0078d4, #0056b3) !important;
            border: none !important;
            padding: 9px 24px !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            border-radius: 7px !important;
            transition: all 0.2s ease !important;
            line-height: 1.1 !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(0, 120, 212, 0.2) !important;
        }
        
        .swal-confirm-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(0, 120, 212, 0.4) !important;
            background: linear-gradient(135deg, #006cc1, #004a9c) !important;
        }
        
        /* Botón de error específico */
        .swal-confirm-btn-error {
            background: linear-gradient(135deg, #e81123, #c00000) !important;
            border: none !important;
            padding: 9px 24px !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            border-radius: 7px !important;
            transition: all 0.2s ease !important;
            line-height: 1.1 !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(232, 17, 35, 0.2) !important;
        }
        
        .swal-confirm-btn-error:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(232, 17, 35, 0.4) !important;
            background: linear-gradient(135deg, #d00000, #a00000) !important;
        }
        
        /* Ajustes para el contenedor SweetAlert */
        .swal2-container {
            z-index: 1060 !important;
        }
        
        /* Fondo del backdrop */
        .swal2-backdrop-show {
            background-color: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: blur(4px) !important;
        }
        
        /* Iconos dentro del SweetAlert */
        .swal-dark-theme .fa-check-circle,
        .swal-dark-theme .fa-exclamation-triangle {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        
        /* Animaciones específicas */
        @keyframes swalFadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes swalFadeInDown {
            from {
                opacity: 0;
                transform: translateY(-15px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .swal-dark-theme.animate__fadeInUp {
            animation: swalFadeInUp 0.4s cubic-bezier(0.1, 0.9, 0.2, 1) !important;
        }
        
        .swal-dark-theme.animate__fadeInDown {
            animation: swalFadeInDown 0.4s cubic-bezier(0.1, 0.9, 0.2, 1) !important;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .swal-dark-theme {
                width: 90% !important;
                margin: 0 auto !important;
            }
            
            .swal-title {
                font-size: 1.1rem !important;
            }
            
            .swal-content {
                font-size: 0.85rem !important;
            }
            
            .swal-confirm-btn,
            .swal-confirm-btn-error {
                padding: 8px 18px !important;
                font-size: 0.85rem !important;
            }
        }
    </style>
<?php endif; ?>
                
                <!-- Cuerpo del formulario -->
                <div class="registro-body">
                    <div class="form-scroll-container">
                        <form action="" method="POST" enctype="multipart/form-data" id="registroForm" novalidate autocomplete="off">
                            <div class="form-row">
                                <!-- Columna Izquierda: Foto y Credenciales -->
                                <div class="form-column">
                                    <!-- Foto de Perfil -->
                                    <div class="avatar-section">
                                        <div class="avatar-preview mb-1">
                                            <?php if (!empty($datos_usuario['foto_temp'])): ?>
                                                <img id="imagePreview" src="<?php echo htmlspecialchars($datos_usuario['foto_temp']); ?>">
                                            <?php else: ?>
                                                <div id="placeholderPreview" class="avatar-placeholder">
                                                    <i class="fas fa-user-plus"></i>
                                                </div>
                                                <img id="imagePreview" src="" style="display: none;">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <label class="btn btn-outline-light btn-sm">
                                            <i class="fas fa-upload me-1"></i> Subir Foto
                                            <input type="file" name="foto" id="imageUpload" 
                                                   accept=".png, .jpg, .jpeg, .webp" 
                                                   hidden 
                                                   onchange="previewImage(this)">
                                        </label>
                                        <small class="text-muted mt-1" style="font-size: 0.72rem; line-height: 1.1;">PNG, JPG, WEBP (Máx. 2MB)</small>
                                    </div>
                                    
                                    <!-- Credenciales de Acceso -->
                                    <div class="form-section">
                                        <h3 class="section-title">
                                            <i class="fas fa-key"></i> Credenciales de Acceso
                                        </h3>
                                        
                                        <div class="mb-2">
                                            <label class="form-label required">Usuario</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control" name="usuario" 
                                                       id="inp_usuario" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['usuario']); ?>"
                                                       required autofocus>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.72rem; line-height: 1.1;">Nombre para iniciar sesión</small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label required">Contraseña</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" name="password" 
                                                       id="password" required autocomplete="new-password">
                                                <button class="btn btn-outline-light" type="button" 
                                                        onclick="togglePass('password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-meter">
                                                <div class="meter-bar" id="meterBar"></div>
                                            </div>
                                            <div class="meter-text" id="meterText"></div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label required">Confirmar Contraseña</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                <input type="password" class="form-control" name="confirm_password" 
                                                       id="confirm_password" required autocomplete="new-password">
                                                <button class="btn btn-outline-light" type="button" 
                                                        onclick="togglePass('confirm_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Términos y Condiciones -->
                                    <div class="form-section">
                                        <h3 class="section-title">
                                            <i class="fas fa-file-contract"></i> Términos
                                        </h3>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="terminos" required>
                                            <label class="form-check-label" for="terminos">
                                                Acepto los <a href="#" class="text-primary">términos</a> 
                                                y <a href="#" class="text-primary">privacidad</a>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Columna Derecha: Información Personal -->
                                <div class="form-column">
                                    <div class="form-section">
                                        <h3 class="section-title">
                                            <i class="fas fa-user-circle"></i> Información Personal
                                        </h3>
                                        
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label class="form-label required">Nombre(s)</label>
                                                <input type="text" class="form-control" name="nombre" 
                                                       id="inp_nombre" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['nombre']); ?>"
                                                       required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label required">Apellidos</label>
                                                <input type="text" class="form-control" name="apellidos" 
                                                       id="inp_apellidos" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['apellidos']); ?>"
                                                       required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label required">CI</label>
                                                <input type="text" class="form-control" name="no_ci" 
                                                       id="inp_ci" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['no_ci']); ?>"
                                                       maxlength="11"
                                                       oninput="validarCI(this)"
                                                       required>
                                                <div id="ciFeedback" class="mt-1 small" style="display: none; line-height: 1;"></div>
                                                <small class="text-muted" style="font-size: 0.72rem; line-height: 1.1;">11 dígitos</small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label required">Email</label>
                                                <input type="email" class="form-control" name="email" 
                                                       id="inp_email" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['email']); ?>"
                                                       required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Teléfono</label>
                                                <input type="tel" class="form-control" name="telefono_contacto" 
                                                       id="inp_telefono" 
                                                       value="<?php echo htmlspecialchars($datos_usuario['telefono_contacto']); ?>"
                                                       oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                                       placeholder="+535xxxxxxx"
                                                       maxlength="15">
                                                <small class="text-muted" style="font-size: 0.72rem; line-height: 1.1;">Opcional</small>
                                            </div>
                                            
                                            <div class="form-group full-width">
                                                <label class="form-label">Dirección</label>
                                                <textarea class="form-control" name="direccion_particular" 
                                                          rows="2"><?php echo htmlspecialchars($datos_usuario['direccion_particular']); ?></textarea>
                                                <small class="text-muted" style="font-size: 0.72rem; line-height: 1.1;">Opcional</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Botones de acción -->
                                    <div class="action-buttons">
                                        <a href="login.php" class="btn btn-outline-light">
                                            <i class="fas fa-arrow-left me-1"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-user-plus me-1"></i> Registrarse
                                        </button>
                                    </div>
                                    
                                    <!-- Enlace Login adicional -->
                                    <div class="login-link">
										<i class="fas fa-sign-in-alt"></i>
                                        ¿Ya tienes una cuenta? <a href="login.php">Inicia Sesión</a>
                                    </div>
                                       <div class="terms-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <small>
                                                Su cuenta será revisada por un Administrador antes de ser activada.
                                            </small>
                                        </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="registro-footer" id="regfooter">
                    <p class="footer-text">
                        &copy; <?php echo date('Y'); ?> SISFACT PDL Visiones - Sistema de Facturación & Servicios de Impresión
                    </p>
                    <p class="footer-version">Versión 2.2.3</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
    <script src="js/sweetalert211.js"></script>
    
    <script>
        // Configuración SweetAlert
        const SwalTheme = Swal.mixin({
            background: '#1e2a3a',
            color: '#eee',
            confirmButtonColor: '#ffc107',
            customClass: {
                container: 'swal2-container',
                popup: 'swal2-popup',
                confirmButton: 'swal2-confirm'
            }
        });

        const Toast = Swal.mixin({
            toast: false,
            position: 'center',
            showConfirmButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i> Aceptar',
            confirmButtonColor: '#0078d4',
            background: '#0a0f1a',
            color: '#f8f9fa',
            customClass: {
                popup: 'border border-secondary rounded shadow-lg'
            }
        });

        // ==========================================
        //  FUNCIONES PARA MANTENIMIENTO (igual que en login.php)
        // ==========================================
        function openMaintenanceModal() {
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMaintenanceModal() {
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        function disableRegisterAccess() {
            const btn = document.querySelector('#registroForm button[type="submit"]');
            const inputs = document.querySelectorAll('#registroForm input, #registroForm textarea, #registroForm select');
            const fileInput = document.getElementById('imageUpload');

            if(btn) {
                btn.disabled = true;
                btn.classList.add('btn-disabled-maint', 'invalid');
                btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento';
                
                // Deshabilitar inputs
                inputs.forEach(input => {
                    input.disabled = true;
                });
                
                if(fileInput) {
                    fileInput.disabled = true;
                }

                // Inyectar botón de refrescar debajo del botón de registro si no existe
                if(!document.getElementById('btn-refresh-register')){
                    const refreshBtn = document.createElement('button');
                    refreshBtn.type = 'button';
                    refreshBtn.id = 'btn-refresh-register';
                    refreshBtn.className = 'btn btn-primary animate__animated animate__fadeIn';
                    refreshBtn.style.backgroundColor = '#ffc107';
                    refreshBtn.style.color = 'white';
                    refreshBtn.style.marginTop = '10px';
                    refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Comprobar Estado';
                    refreshBtn.title = 'Verificar si el mantenimiento ha finalizado';
                    
                    refreshBtn.onclick = (e) => {
                        e.preventDefault();
                        recheckMaintenanceStatus(refreshBtn);
                    };
                    
                    // Insertar después del botón de registro
                    btn.parentNode.insertBefore(refreshBtn, btn.nextSibling);

                }
            }
        }

function recheckMaintenanceStatus(callerBtn = null) {
    let btn = callerBtn || document.getElementById('btn-recheck-maint');
    let originalContent = '';
    let icon = null;

    if (btn) {
        originalContent = btn.innerHTML;
        btn.disabled = true;
        icon = btn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-rotate-right'); 
            icon.classList.add('fa-spinner', 'fa-spin');
        } else {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
    }

    // Añadir timestamp para evitar caché
    fetch('registro.php?action=check_status&t=' + Date.now())
        .then(response => {
            console.log("Status:", response.status, "OK:", response.ok);
            
            // Verificar si la respuesta es JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new TypeError("La respuesta no es JSON");
            }
            
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            
            return response.text().then(text => {
                console.log("Respuesta raw:", text.substring(0, 200) + "...");
                return JSON.parse(text);
            });
        })
        .then(data => {
            console.log("Datos parseados:", data);
            
            setTimeout(() => {
                if (data.db_ok && data.maintenance) {
                    const now = new Date();
                    const fecha = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                    let hours = now.getHours();
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    const ampm = hours >= 12 ? 'P. M.' : 'A. M.';
                    hours = hours % 12; 
                    hours = hours ? hours : 12;
                    const hora = `${hours.toString().padStart(2, '0')}:${minutes} ${ampm}`;

                    SwalTheme.fire({
                        icon: 'info',
                        title: 'Sistema en mantenimiento',
                        html: `
                            <div style="font-size: 1.1em; color: #e0e0e0;">
                                El sistema continúa en mantenimiento.<br>
                                <span style="color: #ffc107; font-size: 0.9em; margin-top: 15px; display: block; background: rgba(255,193,7,0.1); padding: 8px; border-radius: 5px;">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Última verificación:<br>
                                    <strong>${fecha} - ${hora}</strong>
                                </span>
                            </div>
                        `,
                        confirmButtonText: '<i class="fa-solid fa-check me-2"></i> Entendido',
                        buttonsStyling: true,
                        allowOutsideClick: false,
                        didOpen: () => {
                            const container = Swal.getContainer();
                            if(container) container.style.zIndex = '200000';
                            const b = Swal.getConfirmButton();
                            if(b) b.style.color = '#000'; 
                        }
                    });

                } else if (data.db_ok && !data.maintenance) {
                    SwalTheme.fire({
                        icon: 'success',
                        title: '¡Mantenimiento finalizado!',
                        text: 'El sistema está operativo nuevamente. Recargando...',
                        timer: 2000,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            const container = Swal.getContainer();
                            if(container) container.style.zIndex = '200000';
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error('Error de base de datos: ' + JSON.stringify(data));
                }

                if (btn) {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }
            }, 800);
        })
        .catch(error => {
            console.error('Error de comprobación:', error);
            
            SwalTheme.fire({
                icon: 'error',
                title: 'Error de conexión',
                html: `
                    <div style="text-align: left; font-size: 0.9em;">
                        <p>No se pudo verificar el estado del sistema.</p>
                        <p style="color: #ffc107; margin-top: 10px;">
                            <i class="fa-solid fa-bug me-1"></i> Detalle: ${error.message}
                        </p>
                    </div>
                `,
                confirmButtonText: '<i class="fa-solid fa-rotate-right me-2"></i> Reintentar',
                confirmButtonColor: '#d33',
                allowOutsideClick: false,
                didOpen: () => {
                    const container = Swal.getContainer();
                    if(container) container.style.zIndex = '200000';
                }
            });
            
            if (btn) {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }
        });
}
// ==========================================
// FUNCIONES DE AUTENTICACIÓN
// ==========================================
function toggleAuthPassword() {
    const input = document.getElementById('authPassword');
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        button.style.color = '#ffc107';
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        button.style.color = '#a0c8ff';
    }
}

function openAuthModal() {
    const modal = document.getElementById('authProgramadorModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Auto-focus en el primer input
        setTimeout(() => {
            const firstInput = modal.querySelector('input[name="programador_usuario"]');
            if (firstInput) firstInput.focus();
        }, 300);
    }
}

function closeAuthModal() {
    const modal = document.getElementById('authProgramadorModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// ==========================================
// AL CARGAR LA PÁGINA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const isProgrammerBypass = urlParams.get('access_bypass') === 'true';
const authForm = document.getElementById('authProgramadorForm');
    if (!authForm) return;
    
    // Agregar evento a ambos inputs
    const inputs = authForm.querySelectorAll('input[type="text"], input[type="password"]');
    
    inputs.forEach(input => {
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                
                // Si es el input de usuario, pasar al de contraseña
                if (this.name === 'programador_usuario') {
                    const passwordInput = document.getElementById('authPassword');
                    if (passwordInput) {
                        passwordInput.focus();
                    }
                } 
                // Si es el de contraseña, enviar formulario
                else if (this.name === 'programador_password') {
                    document.getElementById('authProgramadorForm').submit();
                }
            }
        });
	});

	
    // Verificar si es modo programador pero NO está autenticado
    if (isProgrammerBypass) {
        // PHP ya verificó si está autenticado en sesión
        const needsAuth = <?php echo $programador_autenticado ? 'false' : 'true'; ?>;
        
        if (needsAuth) {
            // Mostrar modal de autenticación después de un breve delay
            setTimeout(() => {
                openAuthModal();
            }, 300);
        } else {
            // Ya autenticado, mostrar badges
            mostrarBadgesProgramador();
            
            // Actualizar enlaces para mantener parámetro
            actualizarEnlacesProgramador();
        }
    } else {
        // No es modo programador, verificar mantenimiento normal
        if (!<?php echo $programador_autenticado ? 'true' : 'false'; ?>) {
            fetch('registro.php?action=check_status')
                .then(res => res.json())
                .then(data => {
                    if (data.db_ok && data.maintenance) {
                        openMaintenanceModal();
                        disableRegisterAccess();
                    }
                })
                .catch(e => console.error(e));
        }
    }
    
    // Prevenir cierre del modal haciendo clic fuera
    document.addEventListener('click', function(e) {
        const authModal = document.getElementById('authProgramadorModal');
        if (authModal && authModal.classList.contains('active') && 
            e.target.classList.contains('win11-modal-overlay')) {
            e.stopPropagation();
            e.preventDefault();
        }
    });
});

// ==========================================
// MOSTRAR BADGES DE PROGRAMADOR
// ==========================================
function mostrarBadgesProgramador() {
    // Solo si está autenticado
    if (!<?php echo $programador_autenticado ? 'true' : 'false'; ?>) return;
    
    // Badge izquierdo (rojo)
    const badge = document.createElement('div');
    badge.innerHTML = '<i class="fa-solid fa-user-secret"></i> Modo Programador';
    Object.assign(badge.style, {
        position: 'fixed',
        bottom: '10px',
        left: '10px',
        background: '#dc3545',
        color: 'white',
        padding: '6px 12px',
        borderRadius: '6px',
        fontSize: '12px',
        fontWeight: '600',
        zIndex: '999998',
        boxShadow: '0 4px 12px rgba(220, 53, 69, 0.4)',
        userSelect: 'none',
        border: '1px solid rgba(255, 255, 255, 0.1)',
        backdropFilter: 'blur(10px)',
        animation: 'badgeAppear 0.5s ease'
    });
    badge.id = 'programadorBadge';
    document.body.appendChild(badge);

    // Badge derecho (amarillo - salir)
    const exitBadge = document.createElement('div');
    exitBadge.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Salir del modo';
    exitBadge.title = "Cerrar sesión de programador y volver a vista normal";
    
    exitBadge.onclick = function() {
        // Crear formulario para cerrar sesión
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'logout_programador';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    };

    Object.assign(exitBadge.style, {
        position: 'fixed',
        bottom: '10px',
        right: '10px',
        background: 'linear-gradient(135deg, #ffc107, #e0a800)',
        color: '#000',
        padding: '6px 12px',
        borderRadius: '6px',
        fontSize: '12px',
        fontWeight: '600',
        zIndex: '999998',
        boxShadow: '0 4px 12px rgba(255, 193, 7, 0.4)',
        cursor: 'pointer',
        border: '1px solid rgba(0, 0, 0, 0.1)',
        backdropFilter: 'blur(10px)',
        transition: 'all 0.2s ease',
        animation: 'badgeAppear 0.5s ease 0.1s both'
    });
    exitBadge.id = 'exitProgramadorBadge';
    
    exitBadge.onmouseenter = function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 6px 16px rgba(255, 193, 7, 0.5)';
    };
    
    exitBadge.onmouseleave = function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.4)';
    };

    document.body.appendChild(exitBadge);
}

// ==========================================
// ACTUALIZAR ENLACES
// ==========================================
function actualizarEnlacesProgramador() {
    // Solo actualizar si estamos autenticados
    if (!<?php echo $programador_autenticado ? 'true' : 'false'; ?>) return;
    
    const links = document.querySelectorAll('a[href="login.php"]');
    links.forEach(link => {
        const url = new URL(link.href);
        if (!url.searchParams.has('access_bypass')) {
            url.searchParams.set('access_bypass', 'true');
            link.href = url.toString();
        }
    });
}

// Añadir animación para badges
const style = document.createElement('style');
style.textContent = `
    @keyframes badgeAppear {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Versión con más información (fecha de nacimiento y género)
function validarCI(input) {
    const ci = input.value.replace(/[\s-]/g, '');
    const feedback = document.getElementById('ciFeedback');
    
    if (!ci) {
        feedback.style.display = 'none';
        input.classList.remove('is-valid', 'is-invalid');
        return false;
    }
    
    if (!/^\d{11}$/.test(ci)) {
        feedback.style.display = 'block';
        feedback.className = 'mt-1 small text-danger fw-bold animate__animated animate__fadeIn';
        feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> 11 dígitos requeridos';
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
    
    const año = ci.substr(0, 2);
    const mes = ci.substr(2, 2);
    const dia = ci.substr(4, 2);
    
    const añoCompletoNum = parseInt(año) < 30 ? 2000 + parseInt(año) : 1900 + parseInt(año);
    const añoCompletoStr = parseInt(año) < 30 ? `20${año}` : `19${año}`;
    
    if (mes < '01' || mes > '12') {
        feedback.style.display = 'block';
        feedback.className = 'mt-1 small text-danger fw-bold animate__animated animate__fadeIn';
        feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> Mes inválido';
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
    
    const maxDias = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][parseInt(mes) - 1];
    
    // Validación para 29 de febrero
    if (parseInt(mes) === 2 && parseInt(dia) === 29) {
        const esBisiesto = (añoCompletoNum % 4 === 0 && añoCompletoNum % 100 !== 0) || (añoCompletoNum % 400 === 0);
        if (!esBisiesto) {
            feedback.style.display = 'block';
            feedback.className = 'mt-1 small text-danger fw-bold animate__animated animate__fadeIn';
            feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> 29/02 solo en años bisiestos';
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            return false;
        }
    } else if (dia < '01' || parseInt(dia) > maxDias) {
        feedback.style.display = 'block';
        feedback.className = 'mt-1 small text-danger fw-bold animate__animated animate__fadeIn';
        feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> Día inválido';
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        return false;
    }
    
    const digitoGenero = parseInt(ci.charAt(9));
    const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    
    feedback.style.display = 'block';
    feedback.className = 'mt-1 small text-success fw-bold animate__animated animate__fadeIn';
    feedback.innerHTML = `<i class="fas fa-check-circle me-1"></i> CI válido | <span class="text-success">${iconoGenero} ${genero}<br><i class="fas fa-calendar-alt ms-0 me-0"></i> ${dia}/${mes}/${añoCompletoStr}</span>`;
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    return true;
}

            // ==========================================
            //  MEDIDOR DE CONTRASEÑA
            // ==========================================
            document.getElementById('password').addEventListener('input', function() {
                let val = this.value;
                let bar = document.getElementById('meterBar');
                let text = document.getElementById('meterText');
                let score = 0;
                
                // Criterios de seguridad
                if (val.length > 0) score++;
                if (val.length >= 8) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[a-z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;
                
                bar.className = 'meter-bar';
                
                if (val.length === 0) {
                    bar.style.width = '0%';
                    text.innerHTML = '';
                } else if (score < 3) {
                    bar.style.width = '30%';
                    bar.style.backgroundColor = '#e81123';
                    text.innerHTML = '<span style="color:#e81123">Débil</span>';
                } else if (score < 5) {
                    bar.style.width = '60%';
                    bar.style.backgroundColor = '#ff8c00';
                    text.innerHTML = '<span style="color:#ff8c00">Media</span>';
                } else {
                    bar.style.width = '100%';
                    bar.style.backgroundColor = '#107c10';
                    text.innerHTML = '<span style="color:#107c10">Fuerte</span>';
                }
            });

            // ==========================================
            //  VALIDACIÓN FORMULARIO COMPLETO
            // ==========================================
            document.getElementById('registroForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                let msg = "";
                let focusInput = null;
                
                // Limpiar errores visuales previos
                let inputs = this.querySelectorAll('.form-control, .form-select, .form-check-input');
                inputs.forEach(input => {
                    if (input.id !== 'inp_ci') {
                        input.classList.remove('is-invalid');
                    }
                });
                
                // Validar campos obligatorios
                const campos = [
                    { id: 'inp_nombre', nombre: 'Nombre' },
                    { id: 'inp_apellidos', nombre: 'Apellidos' },
                    { id: 'inp_ci', nombre: 'Carnet de Identidad' },
                    { id: 'inp_email', nombre: 'Email' },
                    { id: 'inp_usuario', nombre: 'Usuario' },
                    { id: 'password', nombre: 'Contraseña' }
                ];
                
                campos.forEach(campo => {
                    const input = document.getElementById(campo.id);
                    if (!input.value.trim()) {
                        msg += `• Falta el campo "${campo.nombre}"<br>`;
                        input.classList.add('is-invalid');
                        if (!focusInput) focusInput = input;
                    }
                });
                
                // Validar CI específicamente
                const ciInput = document.getElementById('inp_ci');
                if (ciInput.value.trim() && !validarCI(ciInput)) {
                    msg += "• El Carnet de Identidad no es válido<br>";
                    ciInput.classList.add('is-invalid');
                    if (!focusInput) focusInput = ciInput;
                }
                
                // Validar email
                const emailInput = document.getElementById('inp_email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailInput.value.trim() && !emailRegex.test(emailInput.value)) {
                    msg += "• El formato del correo electrónico no es válido<br>";
                    emailInput.classList.add('is-invalid');
                    if (!focusInput) focusInput = emailInput;
                }
                
                // Validar teléfono
                const telefonoInput = document.getElementById('inp_telefono');
                if (telefonoInput.value.trim()) {
                    const phoneRegex = /^\+?[0-9]{8,15}$/;
                    if (!phoneRegex.test(telefonoInput.value)) {
                        msg += "• El teléfono no es válido (ej: +5352712861)<br>";
                        telefonoInput.classList.add('is-invalid');
                        if (!focusInput) focusInput = telefonoInput;
                    }
                }
                
                // Validar contraseñas
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (password && password.length < 6) {
                    msg += "• La contraseña debe tener al menos 6 caracteres<br>";
                    document.getElementById('password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('password');
                }
                
                if (password !== confirm) {
                    msg += "• Las contraseñas no coinciden<br>";
                    document.getElementById('confirm_password').classList.add('is-invalid');
                    if (!focusInput) focusInput = document.getElementById('confirm_password');
                }
                
                // Validar términos y condiciones
                if (!document.getElementById('terminos').checked) {
                    msg += "• Debe aceptar los términos y condiciones<br>";
                    document.getElementById('terminos').classList.add('is-invalid');
                }
                
                // Mostrar errores o enviar formulario
                if (msg) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Datos Incorrectos',
                        html: '<div style="text-align:left; font-size: 0.82rem; line-height: 1.2;">' + msg + '</div>',
                        showCloseButton: true,
                        allowOutsideClick: false,
                        didClose: () => {
                            if (focusInput) {
                                focusInput.focus();
                                focusInput.scrollIntoView({ behavior: "smooth", block: "center" });
                            }
                        }
                    });
                } else {
                    this.submit();
                }
            });

            // ==========================================
            //  FUNCIONES AUXILIARES
            // ==========================================
            function togglePass(id) {
                const input = document.getElementById(id);
                const button = input.parentNode.querySelector('button');
                const icon = button.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
            
            function previewImage(input) {
                if (input.files && input.files[0]) {
                    // Validar tamaño (máx 2MB)
                    if (input.files[0].size > 2 * 1024 * 1024) {
                        Toast.fire({
                            icon: 'error',
                            title: 'Archivo muy grande',
                            text: 'La imagen no debe superar los 2MB',
                            showCloseButton: true,
                            allowOutsideClick: false
                        });
                        input.value = '';
                        return;
                    }
                    
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('imagePreview');
                        const placeholder = document.getElementById('placeholderPreview');
                        
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
            
            // Inicializar validación CI al cargar
            const ciInput = document.getElementById('inp_ci');
            if (ciInput.value) {
                validarCI(ciInput);
            }
            
            // Ajustar altura del scroll container
            function adjustScrollHeight() {
                const scrollContainer = document.querySelector('.form-scroll-container');
                if (scrollContainer) {
                    const headerHeight = document.querySelector('.registro-header').offsetHeight;
                    const footerHeight = document.querySelector('.registro-footer').offsetHeight;
                    const bodyHeight = document.querySelector('.registro-body').offsetHeight;
                    
                    scrollContainer.style.maxHeight = (bodyHeight - 25) + 'px';
                }
            }
            // Ajustar en carga y redimensionamiento
            adjustScrollHeight();
            window.addEventListener('resize', adjustScrollHeight);

    </script>
<!-- MODAL DE AUTENTICACIÓN DE PROGRAMADOR (mismo estilo) -->
<div class="win11-modal-overlay" id="authProgramadorModal" data-no-close-outside="true">
    <div class="win11-modal" style="border: 1px solid #ffc107; box-shadow: 0 0 40px rgba(255, 193, 7, 0.2); width: 450px;">
        
        <!-- Header -->
        <div class="win11-modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.3);">
            <div class="modal-title-wrapper">
                <div class="modal-icon" style="color: #ffc107;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h3 class="modal-title" style="color: #ffc107;">🔐 Autenticación de Programador</h3>
            </div>
            <button class="win11-modal-close" onclick="window.location.href='registro.php'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="win11-modal-body">
            <?php if ($auth_error): ?>
                <div style="background: rgba(220, 53, 69, 0.15); border-left: 3px solid #dc3545; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #dc3545;"></i>
                    <span style="color: #ffb3b3;"><?php echo htmlspecialchars($auth_error); ?></span>
                </div>
            <?php endif; ?>
            
            <div style="background: rgba(13, 202, 240, 0.08); border-radius: 6px; padding: 12px; margin-bottom: 20px; border-left: 3px solid #0dcaf0;">
                <p style="margin: 0; color: #a0c8ff; font-size: 0.9rem; line-height: 1.4;">
                    <i class="fas fa-info-circle me-1" style="color: #0dcaf0;"></i>
                    Ingresa las credenciales de un usuario con <strong>rol Programador</strong> para acceder en modo mantenimiento.
                </p>
            </div>
            
            <form method="POST" action="" id="authProgramadorForm">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: #f8f9fa; margin-bottom: 6px; font-size: 0.9rem;">
                        <i class="fas fa-user me-1" style="color: #0078d4;"></i> Usuario
                    </label>
                    <div style="display: flex; align-items: center;">
                        <span style="background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); border-right: none; padding: 8px 12px; border-radius: 6px 0 0 6px; color: #a0c8ff;">
                            <i class="fas fa-user-tie"></i>
                        </span>
                        <input type="text" 
                               name="programador_usuario" 
                               required 
                               placeholder="Usuario programador"
                               style="flex: 1; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); color: #f8f9fa; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 0.9rem; outline: none; transition: all 0.2s;"
                               onfocus="this.style.borderColor='#ffc107'; this.style.boxShadow='0 0 0 2px rgba(255, 193, 7, 0.2)';"
                               onblur="this.style.borderColor='rgba(255, 255, 255, 0.08)'; this.style.boxShadow='none';">
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #f8f9fa; margin-bottom: 6px; font-size: 0.9rem;">
                        <i class="fas fa-lock me-1" style="color: #0078d4;"></i> Contraseña
                    </label>
                    <div style="display: flex; align-items: center;">
                        <span style="background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); border-right: none; padding: 8px 12px; border-radius: 6px 0 0 6px; color: #a0c8ff;">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" 
                               name="programador_password" 
                               required 
                               placeholder="Contraseña"
                               id="authPassword"
                               style="flex: 1; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); color: #f8f9fa; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 0.9rem; outline: none; transition: all 0.2s; border-right: none;"
                               onfocus="this.style.borderColor='#ffc107'; this.style.boxShadow='0 0 0 2px rgba(255, 193, 7, 0.2)';"
                               onblur="this.style.borderColor='rgba(255, 255, 255, 0.08)'; this.style.boxShadow='none';">
                        <button type="button" 
                                onclick="toggleAuthPassword()"
                                style="background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); border-left: none; color: #a0c8ff; padding: 8px 12px; border-radius: 0 6px 6px 0; cursor: pointer; transition: all 0.2s;"
                                onmouseover="this.style.background='rgba(255, 193, 7, 0.1)'; this.style.color='#ffc107';"
                                onmouseout="this.style.background='rgba(0, 0, 0, 0.25)'; this.style.color='#a0c8ff';">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <input type="hidden" name="programador_auth" value="1">
                <input type="hidden" name="access_bypass" value="true">
            </form>
        </div>
        
        <!-- Footer -->
        <div class="win11-modal-footer">
            <button class="modal-btn modal-btn-secondary" onclick="window.location.href='registro.php'">
                <i class="fas fa-times me-2"></i> Cancelar
            </button>
            <button class="modal-btn modal-btn-primary" 
                    onclick="document.getElementById('authProgramadorForm').submit()"
                    style="background: #ffc107; color: #000; border: 1px solid #ffc107; font-weight: bold;">
                <i class="fas fa-key me-2"></i> Verificar Credenciales
            </button>
        </div>
    </div>
</div>
</body>
</html>