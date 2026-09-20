<?php
// login.php

// 1. INICIO DE SESIÓN Y CONFIGURACIÓN
// =======================================================================
session_start();
require_once 'config/init.php';

// Detectar si se solicita el "Bypass" visual (Solo para ocultar el modal, no da acceso real)
$es_programador = isset($_GET['access_bypass']) && $_GET['access_bypass'] === 'true';

// Si ya está logueado, mandar al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Variables iniciales
$error = '';
$nombre_usuario = '';
$usuario_imagen = ''; 

// SVG por defecto (reutilizable)
$defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

// =======================================================================
// 2. MANEJADORES AJAX (API INTERNA)
// =======================================================================
if (isset($_GET['action']) || isset($_GET['ajax'])) {
    // LIMPIAR CUALQUIER SALIDA PREVIA
    if (ob_get_length()) ob_clean();
    
    // Desactivar reporte de errores para evitar warnings en JSON
    error_reporting(0);
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $action = $_GET['action'] ?? $_GET['ajax']; // Soporte para ambos parámetros

    try {
        $db = Database::getConnection();

        // A) Verificar estado de mantenimiento
        if ($action === 'check_status') {
            $response = ['db_ok' => true, 'maintenance' => false];
            $sql = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] != 0) {
                    $response['maintenance'] = true;
                }
            } catch (Exception $e) { 
                // Ignorar error SQL puntual
            }
            echo json_encode($response);
            exit;
        }

        // B) Obtener Imagen y Rol (OPTIMIZADO - Multicampo)
        if ($action === 'get_imagen') {
            $dato_entrada = trim($_GET['usuario'] ?? '');
            
            if (empty($dato_entrada)) { 
                echo json_encode(['success' => false, 'error' => 'Usuario vacío']);
                exit;
            }

            // SVG por defecto (definido aquí para asegurar que existe)
            $defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');
            
            // Quitamos el + para comparar solo números
            $dato_telf = str_replace('+', '', $dato_entrada);

            // Consulta SQL mejorada
            $sql = "SELECT foto, rol_id FROM clasif_usuarios 
                    WHERE usuario = ? 
                       OR email = ? 
                       OR no_ci = ? 
                       OR REPLACE(telefono_contacto, '+', '') = ? 
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            // Pasamos $dato_telf (sin +) como cuarto parámetro
            $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada, $dato_telf]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $roles = [1 => 'Administrador', 2 => 'Visualizador', 3 => 'Facturador', 4 => 'Supervisor', 5 => 'Programador'];
                $rol_nombre = isset($result['rol_id']) ? ($roles[$result['rol_id']] ?? 'Usuario') : 'Usuario';
                $imagen = !empty($result['foto']) ? $result['foto'] : $defaultSvg;
                
                // Asegurar que la imagen sea válida
                if (empty($imagen)) {
                    $imagen = $defaultSvg;
                }
                
                echo json_encode([
                    'success' => true, 
                    'imagen' => $imagen, 
                    'rol' => $rol_nombre
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'imagen' => $defaultSvg, 
                    'rol' => null,
                    'error' => 'Usuario no encontrado'
                ]);
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Error servidor: ' . $e->getMessage()
        ]); 
        exit;
    }
}
// =======================================================================
// 2.5 MANEJADOR DE RECUPERACIÓN DE CONTRASEÑA (VERIFICACIÓN + RESET)
// =======================================================================
if (isset($_GET['action']) && $_GET['action'] === 'forgot_password') {
    header('Content-Type: application/json');
    
    try {
        $usuario = trim($_POST['usuario'] ?? '');
        $no_ci = trim($_POST['no_ci'] ?? ''); 
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $step = trim($_POST['step'] ?? 'verify'); // NUEVO: Detectar el paso (verify o reset)
        
        // --- VALIDACIONES BÁSICAS ---
        if (empty($usuario)) { echo json_encode(['success' => false, 'message' => 'Ingrese su usuario']); exit; }
        if (empty($nombre_completo)) { echo json_encode(['success' => false, 'message' => 'Ingrese su nombre completo']); exit; }
        if (empty($no_ci)) { echo json_encode(['success' => false, 'message' => 'Ingrese su carné de identidad']); exit; }
        if (!preg_match('/^\d{11}$/', $no_ci)) { echo json_encode(['success' => false, 'message' => 'El carné debe tener 11 dígitos']); exit; }
        
        $db = Database::getConnection();
        
        // 1. BUSCAR USUARIO
        $sql = "SELECT id, nombre, apellidos, usuario, no_ci FROM clasif_usuarios WHERE usuario = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) { echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit; }

        // 2. VERIFICAR CARNÉ
        $no_ci_normalized = preg_replace('/[^0-9]/', '', $no_ci);
        $user_ci_normalized = isset($user['no_ci']) ? preg_replace('/[^0-9]/', '', $user['no_ci']) : '';

        if (empty($user_ci_normalized) || $user_ci_normalized !== $no_ci_normalized) {
            echo json_encode(['success' => false, 'message' => 'El número de carné no coincide']); exit;
        }

        // 3. VERIFICAR NOMBRE
        $nombre_real = $user['nombre'] . ' ' . $user['apellidos'];
        if (strtolower(trim($nombre_completo)) !== strtolower(trim($nombre_real))) {
            echo json_encode(['success' => false, 'message' => 'El nombre completo no coincide']); exit;
        }
        
        // --- LÓGICA DE PASOS ---
        
        // CASO 1: SOLO VERIFICAR (Devolvemos éxito para mostrar el modal de confirmación)
        if ($step === 'verify') {
            echo json_encode([
                'success' => true, 
                'action' => 'confirm_required', // Bandera para el JS
                'usuario' => $user['usuario'],
                'nombre' => $nombre_real,
                'no_ci' => $user['no_ci']
            ]);
            exit;
        }
        
        // CASO 2: RESETEAR CONTRASEÑA (Solo si step es 'reset')
        if ($step === 'reset') {
            $nueva_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
            $hashed_password = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $sqlUpdate = "UPDATE clasif_usuarios SET password = ? WHERE id = ?";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([$hashed_password, $user['id']]);
            
            // Log
            try {
                $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) VALUES (?, ?, ?, ?, ?)";
                $stmt_log = $db->prepare($sql_log);
                $stmt_log->execute(['PASSWORD_RESET', 'Contraseña restablecida', $user['id'], $nombre_real, $_SERVER['REMOTE_ADDR']]);
            } catch (Exception $e) {}
            
            echo json_encode([
                'success' => true, 
                'action' => 'reset_success',
                'nueva_password' => $nueva_password,
                'usuario' => $user['usuario'],
                'nombre' => $nombre_real
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
    }
    exit;
}

// Config WhatsApp
function obtenerConfiguracionWhatsApp() {
    try {
        $db = Database::getConnection();
        $sql = "SELECT whatsapp_numero, whatsapp_ON, restabpw FROM configuracion_sistema LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return $config ? [
            'whatsapp_numero' => $config['whatsapp_numero'] ?? null, 
            'whatsapp_activo' => ($config['whatsapp_ON'] == 1),
            'restabpw' => $config['restabpw'] ?? 0 
        ] : [
            'whatsapp_numero' => null, 
            'whatsapp_activo' => false,
            'restabpw' => 0
        ];
    } catch (Exception $e) { 
        return [
            'whatsapp_numero' => null, 
            'whatsapp_activo' => false,
            'restabpw' => 0
        ]; 
    }
}

$configWhatsApp = obtenerConfiguracionWhatsApp();
$restabpw = $configWhatsApp['restabpw']; 

$whatsapp_numero_formateado = ($configWhatsApp['whatsapp_activo'] && $configWhatsApp['whatsapp_numero']) ? preg_replace('/\D/', '', $configWhatsApp['whatsapp_numero']) : null;
if ($whatsapp_numero_formateado && strlen($whatsapp_numero_formateado) === 8 && !str_starts_with($whatsapp_numero_formateado, '53')) $whatsapp_numero_formateado = '53' . $whatsapp_numero_formateado;


// =======================================================================
// 3. PROCESAR LOGIN (POST) - MODIFICADO PARA MULTIPLES CAMPOS
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dato_entrada = trim($_POST['usuario'] ?? ''); // Puede ser usuario, email, telf o CI
    $password = trim($_POST['password'] ?? '');

    try {
        $db = Database::getConnection();

		// Quitamos el + para la comparación
        $dato_telf = str_replace('+', '', $dato_entrada);
		
        // 1. Obtener datos del usuario (Busca por cualquiera de los 4 campos)
                $sql = "SELECT id, nombre, apellidos, password, rol_id, foto, activo, no_ci 
                FROM clasif_usuarios 
                WHERE usuario = ? 
                   OR email = ? 
                   OR no_ci = ? 
                   OR REPLACE(telefono_contacto, '+', '') = ? 
                LIMIT 1";

        $stmt = $db->prepare($sql);
        // Pasamos $dato_telf como cuarto parámetro
        $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada, $dato_telf]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
		
        if ($user) {
            // Verificar si está activo
            if ($user['activo'] == 1) {
                // Verificar contraseña
                if (password_verify($password, $user['password'])) {
                    
                    // 2. VERIFICAR MANTENIMIENTO
                    $sqlConf = "SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1";
                    $stmtConf = $db->prepare($sqlConf);
                    $stmtConf->execute();
                    $resConf = $stmtConf->fetch(PDO::FETCH_ASSOC);
                    $enMantenimiento = ($resConf && $resConf['modo_mantenimiento'] >= 1);

                    // Si hay mantenimiento y NO es programador (Rol 5), bloquear.
                    if ($enMantenimiento && $user['rol_id'] != 5) {
                        $error = "El sistema se encuentra en <b>MANTENIMIENTO</b>.<br>Su rol no tiene permisos para acceder en este momento.<br>Acceso permitido solo a: <strong style=\"color:green;\"> Programadores.</strong>";
                    } else {
                        // --- ACCESO CONCEDIDO ---
                        session_regenerate_id(true);

                        $roles = [1=>'Administrador', 2=>'Visualizador', 3=>'Facturador/Editor', 4=>'Supervisor', 5=>'Programador'];
                        $nombre_rol = $roles[$user['rol_id']] ?? 'Usuario';
                        $usuario_imagen = !empty($user['foto']) ? $user['foto'] : $defaultSvg;
                        $nombre_usuario = $user['nombre'] . ' ' . $user['apellidos'];
						

						// Determinar saludo según género desde el CI
						$saludo = 'Bienvenido'; // Por defecto masculino

						if (!empty($user['no_ci']) && preg_match('/^\d{11}$/', $user['no_ci'])) {
							$digitoGenero = intval(substr($user['no_ci'], 9, 1)); // Décimo dígito (posición 9)
							// Si es impar (1,3,5,7,9) = Femenino
							if ($digitoGenero % 2 != 0) {
								$saludo = 'Bienvenida';
							}
						}

                        // ---------------------------------------------------------
                        // DETECTAR TIPO DE LOGIN PARA EL LOG
                        // ---------------------------------------------------------
                        $tipo_login = 'Usuario'; // Valor por defecto
                        
                        if (strpos($dato_entrada, '@') !== false) {
                            $tipo_login = 'Email';
                        } elseif (preg_match('/^\d{11}$/', $dato_entrada)) {
                            $tipo_login = 'CI'; // Carné de Identidad
                        } elseif (preg_match('/^[+]?[0-9\s-]{8,15}$/', $dato_entrada)) {
                            $tipo_login = 'Teléfono';
                        }
                        
                        $descripcion_log = "Inicio de sesión exitoso (Vía: $tipo_login)";
                        // ---------------------------------------------------------

                        // Log
                        try {
                            $sql_log = "INSERT INTO historico_operaciones (operacion, descripcion, usuario_id, usuario_nombre, ip_address) VALUES (?, ?, ?, ?, ?)";
                            $stmt_log = $db->prepare($sql_log);
                            $stmt_log->execute(['LOGIN', $descripcion_log, $user['id'], $nombre_usuario, $_SERVER['REMOTE_ADDR']]);
                        } catch (Exception $e) {}

                        // Session setup
                        $_SESSION['usuario_id'] = $user['id'];
                        $_SESSION['usuario_nombre'] = $nombre_usuario;
                        $_SESSION['usuario_rol'] = $user['rol_id'];
                        $_SESSION['rol_id'] = $user['rol_id'];
                        $_SESSION['usuario_imagen'] = $usuario_imagen;

						// Determinar redirección según el rol
						$redirect_url = 'dashboard.php'; // Por defecto
						if ($user['rol_id'] == 2) {
							$redirect_url = 'capturador_facturas.php'; // Rol Visualizador va a fact_temp
						}

                        // SweetAlert Éxito
                        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login Exitoso</title>
    <script src="js/sweetalert211.js"></script>
      <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('assets/login.avif');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 0;
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        @keyframes moveRight {
            0% { transform: translateX(0); }
            100% { transform: translateX(5px); }
        }
        
        .swal2-container {
            z-index: 1000 !important;
        }
    </style>
</head>
<body>
    <?php
    // Obtener el último acceso del usuario desde histórico_operaciones
    $ultimo_acceso = '';
    $fecha_formateada = '';
    $hora_formateada = '';
    $mensaje_ultimo_acceso = '';
    
    // CORRECCIÓN: Usar el ID de la sesión, no la variable $usuario_id que no existe
    if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
        $usuario_id_actual = $_SESSION['usuario_id'];
        
        try {
            // Obtener conexión PDO
            $db = Database::getConnection();
            
            if (!$db) {
                error_log("Error: Conexión a BD no disponible");
                $mensaje_ultimo_acceso = 'Error de conexión';
            } else {
                // Verificar si hay registros de login para este usuario
                $sql_verificar = "SELECT COUNT(*) as total FROM historico_operaciones WHERE usuario_id = ? AND operacion = 'login'";
                $stmt_verificar = $db->prepare($sql_verificar);
                $stmt_verificar->execute([$usuario_id_actual]);
                $row_verificar = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
                $total_logins = $row_verificar['total'];
                
                if ($total_logins == 0) {
                    // No hay ningún login registrado
                    $mensaje_ultimo_acceso = 'Primer acceso';
                } else if ($total_logins == 1) {
                    // Solo tiene el login actual, no hay accesos previos
                    $mensaje_ultimo_acceso = 'Primer acceso';
                } else {
                    // Tiene más de un login, obtener el penúltimo
                    $sql_ultimo_acceso = "SELECT fecha_hora 
                                          FROM historico_operaciones 
                                          WHERE usuario_id = ? 
                                          AND operacion = 'login' 
                                          ORDER BY fecha_hora DESC 
                                          LIMIT 1, 1"; // El segundo registro (el penúltimo login)
                    
                    $stmt = $db->prepare($sql_ultimo_acceso);
                    $stmt->execute([$usuario_id_actual]);
                    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($resultado) {
                        $ultimo_acceso = $resultado['fecha_hora'];
                        
                        // Formatear la fecha para mostrarla
                        $timestamp = strtotime($ultimo_acceso);
                        $fecha_formateada = date('d/m/Y', $timestamp);
                        $hora_formateada = date('h:i:s A', $timestamp); // Formato 12h con AM/PM
                        $mensaje_ultimo_acceso = $fecha_formateada . ' a las ' . $hora_formateada;
                    } else {
                        $mensaje_ultimo_acceso = 'No disponible';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error al obtener último acceso: " . $e->getMessage());
            $mensaje_ultimo_acceso = 'Error en consulta';
        }
    } else {
        $mensaje_ultimo_acceso = 'Primer acceso'; // Si no hay sesión aún, asumimos primer acceso
    }
    
    // Para depuración - puedes ver esto en la consola del navegador
    echo "<script>console.log('Mensaje último acceso: " . addslashes($mensaje_ultimo_acceso) . "');</script>";
    echo "<script>console.log('Usuario ID: " . ($_SESSION['usuario_id'] ?? 'No disponible') . "');</script>";
    
    // Sanitizar variables para JavaScript (evitar errores de escape)
    $nombre_usuario_js = json_encode($nombre_usuario ?? 'Usuario', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nombre_rol_js = json_encode($nombre_rol ?? 'Usuario', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $usuario_imagen_js = json_encode($usuario_imagen ?? 'assets/default-avatar.png', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $mensaje_ultimo_acceso_js = json_encode($mensaje_ultimo_acceso, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	
	$saludo_js = json_encode($saludo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>
    
    <script>
    // Variables seguras desde PHP
    var nombreUsuario = <?php echo $nombre_usuario_js; ?>;
    var nombreRol = <?php echo $nombre_rol_js; ?>;
    var usuarioImagen = <?php echo $usuario_imagen_js; ?>;
    var mensajeUltimoAcceso = <?php echo $mensaje_ultimo_acceso_js; ?>;

var saludo = <?php echo $saludo_js; ?>; 
var tituloBienvenida = saludo + ' de nuevo!';

    // Depuración en consola
    console.log('Mensaje último acceso en JS:', mensajeUltimoAcceso);
    
    // Función para obtener la hora actual en formato hh:mm:ss AM/PM
    function obtenerHoraActual() {
        const fecha = new Date();
        let horas = fecha.getHours();
        const minutos = fecha.getMinutes().toString().padStart(2, '0');
        const segundos = fecha.getSeconds().toString().padStart(2, '0');
        const ampm = horas >= 12 ? 'PM' : 'AM';
        
        horas = horas % 12;
        horas = horas ? horas : 12;
        const horasStr = horas.toString().padStart(2, '0');
        
        return `${horasStr}:${minutos}:${segundos} ${ampm}`;
    }
    
    // Función para obtener la fecha actual en formato dd/mm/yyyy
    function obtenerFechaActual() {
        const fecha = new Date();
        const dia = fecha.getDate().toString().padStart(2, '0');
        const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
        const anio = fecha.getFullYear();
        return `${dia}/${mes}/${anio}`;
    }
    
    // Determinar color y estilo para el último acceso
    var claseUltimoAcceso = '';
    var iconoUltimoAcceso = 'fa-regular fa-clock';
    var colorUltimoAcceso = '#888';
    
    if (mensajeUltimoAcceso === 'Primer acceso') {
        iconoUltimoAcceso = 'fa-regular fa-star';
        colorUltimoAcceso = '#28a745';
    } else if (mensajeUltimoAcceso === 'No disponible' || mensajeUltimoAcceso === 'Error en consulta' || mensajeUltimoAcceso === 'Error de conexión') {
        iconoUltimoAcceso = 'fa-regular fa-circle-exclamation';
        colorUltimoAcceso = '#dc3545';
    }
    
    // Asegurar que mensajeUltimoAcceso tenga un valor por defecto
    if (!mensajeUltimoAcceso || mensajeUltimoAcceso === '') {
        mensajeUltimoAcceso = 'No disponible';
        iconoUltimoAcceso = 'fa-regular fa-circle-exclamation';
        colorUltimoAcceso = '#dc3545';
    }

    Swal.fire({
        title: '<div style="font-size: 2.5rem; margin-bottom: 10px;">👋</div><div style="font-size: 1.8rem; margin-bottom: 15px; font-weight: 800; color: #007bff;">' + tituloBienvenida + '</div>',
        html: `
            <div style="display: flex; flex-direction: row; align-items: center; gap: 15px; margin-bottom: 20px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px;">
                <div style="flex-shrink: 0;">
                    <img src="` + usuarioImagen + `" 
                         style="width: 90px; height: 90px; border-radius: 50%; border: 3px solid #007bff; object-fit: cover;" 
                         alt="Foto de perfil"
                         onerror="this.src='assets/default-avatar.png'">
                </div>
                <div style="flex-grow: 1; text-align: left;">
                    <div style="font-size: 1.4rem; margin-bottom: 8px; color: #007bff; font-weight: 600;">
                        <i class="fa-solid fa-user-check" style="margin-right: 8px;"></i>` + nombreUsuario + `
                    </div>
                    <div style="margin-bottom: 5px;">
                        <span style="background: rgba(111, 66, 193, 0.2); color: #b38cff; padding: 4px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 600;">
                            <i class="fa-solid fa-tag" style="margin-right: 5px;"></i>` + nombreRol + `
                        </span>
                    </div>
                    <div style="display: flex; gap: 15px; font-size: 0.85rem; color: #aaa;">
                        <span><i class="fa-regular fa-clock" style="margin-right: 5px;"></i><span id="hora-actual">${obtenerHoraActual()}</span></span>
                        <span><i class="fa-regular fa-calendar" style="margin-right: 5px;"></i>${obtenerFechaActual()}</span>
                    </div>
                </div>
            </div>
            
            <div style="background: rgba(0, 123, 255, 0.1); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid rgba(0, 123, 255, 0.2);">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <div style="width: 10px; height: 10px; background: #28a745; border-radius: 50%; margin-right: 10px; animation: pulse 1.5s infinite;"></div>
                    <span style="font-weight: 600;">Sesi\u00f3n iniciada correctamente</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <i class="fa-solid fa-arrow-right" style="color: #007bff; margin-right: 10px; animation: moveRight 1s infinite alternate;"></i>
                    <span>Redirigiendo al sistema...</span>
                </div>
            </div>
            
            <div style="font-size: 0.85rem; color: ` + colorUltimoAcceso + `; margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                <i class="` + iconoUltimoAcceso + `" style="margin-right: 5px;"></i>
                <span style="font-weight: 500;">\u00daltimo acceso:</span> 
                <span><strong>` + mensajeUltimoAcceso + `</strong></span>
            </div>
        `,
        background: 'rgba(10, 20, 30, 0.95)',
        backdrop: 'rgba(0,0,0,0.5)',
        showConfirmButton: false,
        width: '500px',
        timer: 6500,
        timerProgressBar: true,
        didOpen: () => {
            const intervalo = setInterval(() => {
                const elementoHora = document.getElementById('hora-actual');
                if (elementoHora) {
                    elementoHora.textContent = obtenerHoraActual();
                }
            }, 1000);
            
            // Guardar el intervalo en el objeto Swal
            if (Swal.getTimerProgressBar()) {
                Swal.getTimerProgressBar().intervalo = intervalo;
            }
            
            const progressBar = Swal.getTimerProgressBar();
            if (progressBar) {
                progressBar.style.backgroundColor = '#007bff';
                progressBar.style.height = '4px';
            }
        },
        willClose: () => {
            const progressBar = Swal.getTimerProgressBar();
            if (progressBar && progressBar.intervalo) {
                clearInterval(progressBar.intervalo);
            }
            window.location.href = '<?php echo $redirect_url; ?>';
        }
    });
    </script>
</body>
</html>
					<?php
                        exit();
                    }
                } else { $error = "Contraseña incorrecta"; }
            } else { $error = "Usuario inactivo. Contacte al administrador"; }
        } else { $error = "Usuario / Datos no encontrados"; }
    } catch (Exception $e) { $error = "Error en el servidor."; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SISFACT PDL Visiones - Iniciar Sesión</title>
  <link rel="icon" type="image/x-icon" href="assets/logov.png">
  <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css" />
  <script src="js/sweetalert211.js"></script>
  <link rel="stylesheet" href="css/Animate4.1.1/animate.min.css">

  <style>
    /* ========================================= */
    /* CSS CRÍTICO Y ESTILOS */
    /* ========================================= */
    .swal2-container { z-index: 999999 !important; }

    .register-link-combo {
        color: #374151; text-decoration: none; font-weight: 500; padding: 7px 22px; display: inline-flex;
        align-items: center; border: 1.5px solid #e5e7eb; border-radius: 20px; transition: all 0.3s ease;
    }
    .register-link-combo:hover { color: #2563eb; border-color: #2563eb; background-color: rgba(37, 99, 235, 0.05); padding-right: 26px; padding-left: 18px; }
    .register-link-combo .fa-user-plus { transition: transform 0.3s ease; }
    .register-link-combo .arrow-icon { font-size: 0.85rem; opacity: 0; transform: translateX(-10px); transition: all 0.3s ease; }
    .register-link-combo:hover .fa-user-plus { transform: scale(1.1); }
    .register-link-combo:hover .arrow-icon { opacity: 1; transform: translateX(5px); }
    
    /* Enlace Olvidaste Contraseña */
    .forgot-link { color: #a0c8ff; font-size: 0.85rem; text-decoration: none; transition: all 0.3s ease; display: inline-block; position: relative; }
    .forgot-link:hover { color: #fff; text-shadow: 0 0 8px rgba(0, 123, 255, 0.6); }
    .forgot-link::after { content: ''; position: absolute; width: 0; height: 1px; bottom: -2px; left: 0; background-color: #007bff; transition: width 0.3s ease; }
    .forgot-link:hover::after { width: 100%; }

    /* Estado deshabilitado para mantenimiento */
    .register-link-combo.disabled-link, .forgot-link.disabled-link { pointer-events: none; opacity: 0.5; filter: grayscale(100%); border-color: #444; }

    *, *::before, *::after { box-sizing: border-box; }
    :root { --accent: #007bff; --card-bg: rgba(10, 20, 35, 0.85); --input-bg: rgba(0, 0, 0, 0.4); --success: #007bff; --error: #ff4757; }
    body { margin: 0; font-family: "Poppins", sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; color: #eee; overflow: hidden; background: #0a0f1a; }
    body::before { content: ""; position: fixed; inset: 0; background: url('assets/login.avif') center/cover no-repeat; filter: brightness(0.32) saturate(0.82) blur(1.5px); transform-origin: center; animation: bgZoom 26s ease-in-out infinite alternate; z-index: -3; }
    @keyframes bgZoom { from{transform:scale(1)} to{transform:scale(1.06)} }
    body::after { content: ""; position: fixed; right: -15%; top: -10%; width: 55%; height: 55%; background: radial-gradient(circle, rgba(0, 123, 255, 0.16), transparent 60%); filter: blur(80px); z-index: -2; }
    
    .login-card { background: var(--card-bg); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-radius: 16px; padding: 36px 28px; width: 96%; max-width: 420px; box-shadow: 0 18px 45px rgba(0,0,0,0.7); position: relative; text-align: center; animation: fadeInUp 0.95s ease both; overflow: visible; border: 1px solid rgba(0, 123, 255, 0.1); }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    .btn-close { position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); color: #fff; padding: 8px 10px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; display: inline-flex; gap: 8px; align-items: center; transition: transform .18s ease, background .18s ease; }
    .btn-close:hover { transform: translateY(-3px); background: rgba(255,255,255,0.06); }
    
    .login-title img { width: 120px; margin-bottom: 0px; filter: drop-shadow(0 6px 18px rgba(0, 123, 255, 0.50)); }
    .login-title h2 { margin: 1px 0 4px; font-size: 1.15rem; font-weight:700; color: #a0c8ff; }
    .login-title p { margin: 0px 0 5px; opacity: 1; font-size: 14px; color: #a0c8ff; }
	.login-title h4 { margin: 0 0 5px; font-size: 0.9rem; animation: glowingBlueSwitch 0.4s infinite alternate; }

    @keyframes glowingBlueSwitch { from { color: white; text-shadow: 0 0 5px rgba(255, 255, 255, 0.3); } to { color: #0088ff; text-shadow: 0 0 10px rgba(0, 136, 255, 0.5); } }
    
    .user-avatar-container { position: absolute; right: 25px; top: 50%; transform: translateY(-50%); width: 60px; height: 60px; z-index: 2; pointer-events: none; }
    .user-avatar { width: 100%; height: 100%; border-radius: 50%; border: 2px solid rgba(0, 123, 255, 0.5); object-fit: cover; background: rgba(0, 0, 0, 0.5); opacity: 0; transition: opacity 0.3s ease; }
    .user-avatar.visible { opacity: 1; }
    
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9a9a9a; font-size: 1.05rem; pointer-events: none; }
    .input-group { position: relative; margin-bottom: 10px; }
    .input-custom { width: 100%; padding: 12px 44px 12px 40px; border: 2px solid rgba(255,255,255,0.08); background: var(--input-bg); color: #fff; border-radius: 10px; font-size: 1rem; transition: border-color .22s ease, box-shadow .22s ease, transform .18s ease, background .22s ease; outline: 0px; display: block; margin-bottom: 5px; }
    .input-custom:focus { border-color: var(--accent); box-shadow: 0 6px 26px rgba(0, 123, 255, 0.12); transform: translateY(-2px); background: rgba(0,0,0,0.52); }
    .input-custom:disabled { opacity: 0.6; cursor: not-allowed; border-color: #555; }

    .btn-login { width: 100%; padding: 12px; margin-top: 6px; background: var(--accent); color: #fff; border: none; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; position: relative; overflow: hidden; }
    .btn-login:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0, 123, 255, 0.14); }
    .btn-login:disabled { opacity: 0.6; cursor: not-allowed; background: #555; box-shadow: none; transform: none; }
    .btn-disabled-maint { background: #555 !important; color: #aaa !important; cursor: not-allowed !important; }
    
    .login-footer { margin-top: 14px; opacity: 0.7; font-size: 0.85rem; color: #a0c8ff; }
    
    @keyframes fadeInRole { 0% { opacity: 0; transform: translateY(8px) scale(0.95); filter: blur(2px); } 100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }
    @keyframes fadeOutRole { 0% { opacity: 1; transform: translateY(0) scale(1); } 100% { opacity: 0; transform: translateY(-8px) scale(0.95); } }
    @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    @keyframes shakeError { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); } 20%, 40%, 60%, 80% { transform: translateX(3px); } }
    @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
    
    #userRole, #userNotFound { transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); padding: 10px 16px; border-radius: 12px; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); margin-top: 12px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 44px; border: 1px solid transparent; animation: fadeInRole 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; transform-origin: center; }
    .user-status-container { margin-top: 10px; position: relative; min-height: 50px; }
    #userRole { background: linear-gradient(135deg, rgba(0, 123, 255, 0.12) 0%, rgba(0, 123, 255, 0.08) 50%, rgba(0, 123, 255, 0.15) 100%); border-color: rgba(0, 123, 255, 0.2); animation: fadeInRole 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    #userRole::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 100%; background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.15) 50%, transparent 100%); animation: gradientShift 3s infinite linear; z-index: 1; pointer-events: none; }
    #userRole::after { content: ''; position: absolute; top: -1px; left: -1px; right: -1px; height: 3px; background: linear-gradient(90deg, #007bff, #0056b3, #00b4d8, #007bff); background-size: 300% 100%; animation: gradientShift 4s infinite linear; border-radius: 12px 12px 0 0; z-index: 2; }
    #userNotFound { background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.06) 50%, rgba(220, 53, 69, 0.12) 100%); border-color: rgba(220, 53, 69, 0.2); animation: fadeInRole 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards, shakeError 0.5s ease 0.3s; }
    #userNotFound::after { content: ''; position: absolute; top: -1px; left: -1px; right: -1px; height: 3px; background: linear-gradient(90deg, #dc3545, #ff6b6b, #ff8e8e, #dc3545); background-size: 300% 100%; animation: gradientShift 4s infinite linear; border-radius: 12px 12px 0 0; z-index: 2; }
    #userRole i, #userNotFound i { font-size: 1rem; animation: floatIcon 3s infinite ease-in-out; position: relative; z-index: 3; }
    #userRole i { background: linear-gradient(135deg, #007bff, #0056b3); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 2px 4px rgba(0, 123, 255, 0.3)); }
    #userNotFound i { background: linear-gradient(135deg, #dc3545, #ff6b6b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 2px 4px rgba(220, 53, 69, 0.3)); }
    #roleText, #notFoundText { font-size: 0.9rem; font-weight: 700; letter-spacing: 0.3px; position: relative; z-index: 3; }
    #roleText { background: linear-gradient(135deg, #007bff 0%, #0056b3 50%, #00b4d8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.15); }
    #notFoundText { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 50%, #ff8e8e 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.15); }
    .hiding { animation: fadeOutRole 0.4s ease forwards !important; }
    
    .char-counter { position: absolute; bottom: -20px; right: 5px; font-size: 0.75rem; font-weight: 600; color: rgba(255, 255, 255, 0.4); z-index: 10; pointer-events: none; transition: all 0.3s ease; opacity: 0; background: transparent; border: none; padding: 0; letter-spacing: 0.5px; }
    .char-counter.show { opacity: 1; transform: translateY(0); }
    .char-counter.hide { opacity: 0; transform: translateY(2px); }
    
    .toggle-pass { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: rgba(0, 123, 255, 0.1); color: #a0c8ff; cursor: pointer; padding: 8px; border-radius: 10px; border: 1px solid rgba(0, 123, 255, 0.2); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); user-select: none; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 2; }
    .toggle-pass:hover { background: rgba(0, 123, 255, 0.2); color: #ffffff; border-color: rgba(0, 123, 255, 0.4); transform: translateY(-50%) scale(1.05); box-shadow: 0 6px 20px rgba(0, 123, 255, 0.25); }
    .toggle-pass:active { transform: translateY(-50%) scale(0.95); transition: transform 0.1s ease; }
    .toggle-pass.active { background: rgba(0, 123, 255, 0.25); color: #ffffff; border-color: rgba(0, 123, 255, 0.5); box-shadow: 0 4px 16px rgba(0, 123, 255, 0.3), inset 0 0 0 1px rgba(255, 255, 255, 0.1); }
    .toggle-pass i { font-size: 1.1rem; animation: iconRotate 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); position: relative; z-index: 1; }
    .toggle-pass.active i { animation: iconPulse 2s infinite; }
    @keyframes iconPulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.8; transform: scale(1.05); } }
    @keyframes iconRotate { 0% { transform: rotate(0deg) scale(0.8); opacity: 0.5; } 50% { transform: rotate(180deg) scale(1.2); } 100% { transform: rotate(360deg) scale(1); opacity: 1; } }

    /* MANTENIMIENTO MODAL ESTILO WINDOWS 11 */
    .win11-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 10000; opacity: 0; transition: opacity 0.3s ease; }
    .win11-modal-overlay.active { display: flex; opacity: 1; }
    .win11-modal { background: rgba(32, 32, 32, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.1, 0.9, 0.2, 1); display: flex; flex-direction: column; overflow: hidden; }
    .win11-modal-overlay.active .win11-modal { transform: scale(1); }
    .win11-modal-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.05); background: rgba(255, 255, 255, 0.02); }
    .modal-title-wrapper { display: flex; align-items: center; gap: 12px; }
    .modal-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .modal-title { margin: 0; font-size: 1.1rem; font-weight: 600; color: #fff; }
    .win11-modal-body { padding: 25px 25px; color: #ddd; font-size: 0.95rem; line-height: 1.6; }
    .win11-modal-footer { padding: 15px 25px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.05); display: flex; justify-content: flex-end; gap: 10px; }
    .modal-btn { padding: 8px 20px; border-radius: 4px; border: 1px solid transparent; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
    .modal-btn-primary { background: #0078d4; color: #fff; border: 1px solid #0078d4; }
    .modal-btn-primary:hover { background: #006cc1; }
    .modal-btn-secondary { background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1); }
    .modal-btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
    .maintenance-overlay { background: rgba(0, 0, 0, 0.95) !important; backdrop-filter: blur(10px); z-index: 99999 !important; }
    .maintenance-icon-pulse { animation: pulseMaint 2s infinite; color: #ffc107; }
    @keyframes pulseMaint { 0% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); } 50% { transform: scale(1.1); text-shadow: 0 0 20px rgba(255, 193, 7, 1); } 100% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); } }
	/* Bordes de validación CI en tema oscuro */
	.input-custom.is-valid { border-color: #28a745 !important; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2); }
	.input-custom.is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 10px rgba(220, 53, 69, 0.2); }
  </style>
</head>
<body>

<!-- TARJETA LOGIN -->
<div class="login-card animate__animated animate__fadeInDown" role="dialog" aria-labelledby="loginTitle">
    <a class="btn-close" href="index.php" aria-label="Cerrar"><i class="fa-solid fa-door-closed"></i> Cerrar</a>
    <div class="login-title" id="loginTitle">
        <img src="assets/logov.png" alt="Logo PDL Visiones">
        <h2>SISFACT PDL Visiones</h2>
        <p>Sistema de Facturación & Servicios de Impresión</p>
		<h4>TECLEE SUS CREDENCIALES DE ACCESO</h4>
    </div>

    <!-- IMPORTANTE: AQUI SE AGREGO id="loginForm" PARA QUE EL SCRIPT FUNCIONE -->
    <form method="POST" action="" autocomplete="off" novalidate id="loginForm">
		<div class="input-group">
			<i class="fa-solid fa-user input-icon" id="dynamicIcon" aria-hidden="true"></i>
			<input type="text" name="usuario" id="usuario" class="input-custom" placeholder="Usuario / Email / Teléfono / CI" required autofocus autocomplete="username" maxlength="100" />
			<div class="user-avatar-container">
				<img id="userAvatar" class="user-avatar" src="" alt="Foto de usuario">
			</div>
			<div class="char-counter" id="charCounter">0/100</div>
			
			<!-- NUEVO: Contenedor para el feedback del CI -->
			<div id="ciFeedback" style="display:none; width:100%; text-align:left; margin-top:8px; font-size: 0.85rem; padding-left: 10px;"></div>
		</div>

		<div class="input-group password-group" style="margin-bottom:5px;">
			<i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
			<input type="password" name="password" id="password" class="input-custom" placeholder="Contraseña" required autocomplete="current-password" />
			<button type="button" id="togglePass" class="toggle-pass" aria-label="Mostrar contraseña" title="Mostrar contraseña">
			  <i class="fa-solid fa-eye"></i>
			</button>
		</div>

		<?php
			// Determinar qué función usar
			$forgotFunction = $restabpw == 1 ? 'openForgotPassword()' : 'openRecoverModal(event)';
		?>

		<!-- LINK OLVIDASTE CONTRASEÑA -->
		<div style="text-align: right; margin-bottom: 12px;">
			<a href="#" class="forgot-link" onclick="<?php echo $forgotFunction; ?>">
				<i class="fa-solid fa-key me-1" style="font-size:0.8em;"></i> ¿Olvidaste la contraseña? (ALT+F)
			</a>
		</div>

        <button type="submit" id="btnLogin" class="btn-login"><i class="fa-solid fa-right-to-bracket" style="margin-right:8px"></i> Iniciar Sesión</button>
    </form>

    <!-- BOTÓN REGISTRAR -->
    <div class="mt-3 text-center">
        <p class="mb-2" style="color: #a0c8ff; font-size: 0.9rem;">¿Nuevo en el sistema?</p>
        <a href="registro.php<?php echo isset($es_programador) && $es_programador ? '?access_bypass=true' : ''; ?>" class="register-link-combo">
            <i class="fas fa-user-plus me-1"></i> Crear Nueva Cuenta <i class="fas fa-arrow-right ms-1 arrow-icon"></i>
        </a>
    </div>

    <div class="login-footer">
        <small>Versión 2.3.3 • &copy; <?= date('Y'); ?> SISFACT PDL Visiones</small>
        <div class="user-status-container">
            <div id="userRole" style="display: none;" data-role="">
                <i class="fa-solid fa-user-check"></i> <span id="roleText"></span>
            </div>
            <div id="userNotFound" style="display: none;">
                <i class="fa-solid fa-user-slash"></i> <span id="notFoundText">Usuario no encontrado</span>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MANTENIMIENTO -->
<div class="win11-modal-overlay maintenance-overlay" id="maintenanceModal" data-no-close-outside="true">
  <div class="win11-modal" style="border: 1px solid #ffc107; box-shadow: 0 0 40px rgba(255, 193, 7, 0.2); width: 900px; max-width: 900px; max-height: 700px;">
    
    <!-- Header -->
    <div class="win11-modal-header" style="border-bottom: 1px solid rgba(255, 193, 7, 0.3);">
      <div class="modal-title-wrapper">
        <div class="modal-icon maintenance-icon-pulse">
          <i class="fa-solid fa-helmet-safety"></i>
        </div>
        <h3 class="modal-title" style="color: #ffc107;">🛠️ Modo Mantenimiento Activo</h3>
        <p style="color: #ffc107;font-size:14px;">➡️<span style="color: white;"> Estas en:</span> Página de Logearse en el Sistema</p>
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
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="win11-modal-footer" style="justify-content: space-between; background: rgba(0,0,0,0.2);">
      <div style="display: flex; gap: 10px;">
          <!-- Botón Programador (BYPASS) -->
          <button class="modal-btn modal-btn-secondary" onclick="window.location.href='?access_bypass=true'">
            <i class="fa-solid fa-unlock-keyhole me-1"></i> 🔓 Acceso Programador
          </button>
          
          <button class="modal-btn modal-btn-secondary" onclick="closeMaintenanceModal()">
            <i class="fa-solid fa-eye me-1"></i> Solo mirar
          </button>
      </div>

      <div style="display: flex; gap: 10px;">
          <button class="modal-btn modal-btn-primary" onclick="window.location.href='index.php'">
            <i class="fa-solid fa-home"></i> Inicio
          </button>
          <button id="btn-recheck-maint" class="modal-btn modal-btn-primary" onclick="recheckMaintenanceStatus()" 
                  style="background-color: #ffc107; color: #000; border: none; font-weight: bold;">
            <i class="fa-solid fa-rotate-right me-2"></i> Comprobar
          </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL RECUPERAR CONTRASEÑA -->
<div class="win11-modal-overlay" id="recoverModal" style="z-index: 999998;">
  <div class="win11-modal" style="border: 1px solid #007bff; box-shadow: 0 0 40px rgba(0, 123, 255, 0.2); width: 100%; max-width: 500px;">
    
    <!-- Header -->
    <div class="win11-modal-header" style="border-bottom: 1px solid rgba(0, 123, 255, 0.3);">
      <div class="modal-title-wrapper">
        <div class="modal-icon" style="color: #007bff;">
          <i class="fa-solid fa-user-lock"></i>
        </div>
        <h3 class="modal-title" style="color: #a0c8ff;">Recuperar Acceso</h3>
      </div>
      <button class="win11-modal-close" onclick="closeRecoverModal()" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <!-- Body -->
    <div class="win11-modal-body" style="padding: 25px; text-align: center;">
      <div style="font-size: 3rem; margin-bottom: 15px; color: #007bff; opacity: 0.8;">
        <i class="fa-solid fa-shield-cat"></i>
      </div>
      
      <p style="font-size: 1.1em; color: #fff; margin-bottom: 10px;">
        <strong>¿Has olvidado tu contraseña?</strong>
      </p>
      <p style="color: #aaa; font-size: 0.95rem; margin-bottom: 25px;">
        Por razones de seguridad, el restablecimiento de contraseñas debe ser solicitado directamente al administrador del sistema.
      </p>

      <div style="background: rgba(0, 123, 255, 0.05); border-radius: 8px; padding: 20px; border: 1px dashed rgba(0, 123, 255, 0.3);">
        <h5 style="color: #007bff; font-size: 0.95rem; margin-bottom: 15px;">Selecciona un medio de contacto:</h5>
        
<div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
    <!-- Enlace de WhatsApp - Siempre visible -->
    <a href="<?php echo ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado) ? 
        'https://api.whatsapp.com/send?phone=' . $whatsapp_numero_formateado . '&text=Hola, necesito restablecer mi contraseña en SISFACT.' : 'javascript:void(0)'; ?>" 
       target="<?php echo ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado) ? '_blank' : ''; ?>"
       class="btn btn-sm btn-outline-success" 
       style="border-radius: 20px; text-decoration: none; 
              <?php if (!($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado)): ?>
              opacity: 0.5; cursor: not-allowed; pointer-events: none;
              <?php endif; ?>
              color: <?php echo ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado) ? 'white' : '#888'; ?>; 
              border: 1px solid <?php echo ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado) ? '#28a745' : '#666'; ?>; 
              background-color: <?php echo ($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado) ? 'rgba(40, 167, 69, 0.2)' : 'rgba(102, 102, 102, 0.1)'; ?>; 
              padding: 8px 16px; transition: all 0.3s;">
        <i class="fab fa-whatsapp me-2"></i> WhatsApp
        <?php if (!($configWhatsApp['whatsapp_activo'] && $whatsapp_numero_formateado)): ?>
        <span style="font-size: 0.7em; margin-left: 5px; color: #ff6b6b;">(No disponible)</span>
        <?php endif; ?>
    </a>
    
    <!-- Enlace de Correo - Siempre visible -->
    <a href="mailto:soporte_pdlvisiones@gmail.com?subject=Restablecer Contraseña SISFACT" 
       class="btn btn-sm btn-outline-light" 
       target="_blank"
       style="border-radius: 20px; text-decoration: none; color: white; border: 1px solid rgba(255,255,255,0.5); background-color: rgba(255, 255, 255, 0.1); padding: 8px 16px; transition: all 0.3s;">
        <i class="fa-solid fa-envelope me-2"></i> Correo
    </a>
</div>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="win11-modal-footer" style="background: rgba(0,0,0,0.2);">
      <button class="modal-btn modal-btn-secondary" onclick="closeRecoverModal()">
        <i class="fas fa-close"></i> Cancelar
      </button>
      <button class="modal-btn modal-btn-primary" onclick="closeRecoverModal()">
        <i class="fas fa-check"></i> Entendido
      </button>
    </div>
  </div>
</div>

<script>
// ==========================================
//  LÓGICA DE VALIDACIÓN CI (Tu función)
// ==========================================
function validarCI(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: '11 dígitos requeridos' };
    
    const año = ci.substr(0, 2); 
    const mes = ci.substr(2, 2); 
    const dia = ci.substr(4, 2);
    
    if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes inválido' };
    
    const diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const maxDias = diasPorMes[parseInt(mes) - 1];
    
    // Validación especial para 29 de febrero
    if (parseInt(mes) === 2 && parseInt(dia) === 29) {
        const añoCompleto = parseInt(año) < 30 ? 2000 + parseInt(año) : 1900 + parseInt(año);
        const esBisiesto = (añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0);
        if (!esBisiesto) {
            return { valido: false, mensaje: '29/02 solo válido en años bisiestos' };
        }
    } else if (dia < '01' || parseInt(dia) > maxDias) {
        return { valido: false, mensaje: 'Día inválido' };
    }
    
    const digitoGenero = parseInt(ci.charAt(9));
    const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 30 ? `20${año}` : `19${año}`;
    
    return { 
        valido: true, 
        mensaje: `<i class="fas fa-check-circle me-1"></i><span style="color: cyan;"> CI válido </span><span class="text-success" style="color:#28a745">${iconoGenero} ${genero} | <i class="fas fa-cake-candles ms-1"></i> ${dia}/${mes}/${añoCompleto}</span>`, 
        genero: genero 
    };
}


// Toggle password
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('togglePass'); 
  const pwd = document.getElementById('password'); 
  const icon = toggle.querySelector('i');
  function updateToggleState() {
	if (pwd.type === 'password') {
		pwd.type = 'text';
		icon.classList.remove('fa-eye');
		icon.classList.add('fa-eye-slash');
		toggle.classList.add('active');
		toggle.title = 'Ocultar contraseña';
		toggle.style.background = 'rgba(0, 123, 255, 0.25)';
		toggle.style.borderColor = 'rgba(0, 123, 255, 0.5)';
		toggle.style.boxShadow = '0 4px 16px rgba(0, 123, 255, 0.3)';
	} else { 
		pwd.type = 'password';
		icon.classList.remove('fa-eye-slash');
		icon.classList.add('fa-eye');
		toggle.classList.remove('active');
		toggle.title = 'Mostrar contraseña';
		toggle.style.background = 'rgba(0, 123, 255, 0.1)';
		toggle.style.borderColor = 'rgba(0, 123, 255, 0.2)';
		toggle.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
	}
}
  toggle.addEventListener('click', (e) => { e.preventDefault(); updateToggleState(); toggle.style.transform = 'translateY(-50%) scale(0.95)'; setTimeout(() => { toggle.style.transform = 'translateY(-50%) scale(1)'; }, 150); });
  pwd.addEventListener('focus', () => { toggle.style.opacity = '1'; });
  pwd.addEventListener('blur', () => { toggle.style.opacity = '0.9'; });
});

// Cargar Imagen Usuario y CAMBIAR ÍCONO DINÁMICO
document.addEventListener('DOMContentLoaded', function() {
    const usuarioInput = document.getElementById('usuario'); 
    const userAvatar = document.getElementById('userAvatar'); 
    const charCounter = document.getElementById('charCounter');
    const dynamicIcon = document.getElementById('dynamicIcon');
    const ciFeedback = document.getElementById('ciFeedback');
    const MAX_LENGTH = 100;
    let debounceTimer; 

    // Funciones auxiliares UI
    function ocultarMensaje(elementId) { 
		const elemento = document.getElementById(elementId);
		if (!elemento) return;
		if (elemento.style.display === 'flex' || elemento.style.display === 'block') {
			elemento.classList.add('hiding');
			setTimeout(() => {
				elemento.style.display = 'none';
				elemento.classList.remove('hiding');
				elemento.style.opacity = '0';
				elemento.style.transform = 'translateY(8px) scale(0.95)';
			}, 400);
		}
	}
	
    function mostrarElemento(elementId) {
		const elemento = document.getElementById(elementId); 
		if (!elemento) return; 
		if (elementId === 'userRole') ocultarMensaje('userNotFound'); 
		else if (elementId === 'userNotFound') ocultarMensaje('userRole'); 
		elemento.style.display = 'flex'; 
		elemento.style.opacity = '0'; 
		elemento.style.transform = 'translateY(8px) scale(0.95)'; 
		elemento.style.animation = 'none'; 
		void elemento.offsetWidth; 
		setTimeout(() => { 
			elemento.style.opacity = '1'; 
			elemento.style.transform = 'translateY(0) scale(1)'; 
			elemento.style.animation = ''; 
		}, 10); 
	}

// Cambiar ícono dinámico
    function updateInputIcon(value) {
        let newIconClass = 'fa-user'; 
        let color = '#9a9a9a'; 
        value = value.trim();

        if (value.length === 0) { newIconClass = 'fa-user'; } 
        else if (value.includes('@')) { newIconClass = 'fa-envelope'; color = '#e0a800'; } 
        else if (/^\d{11}$/.test(value)) { newIconClass = 'fa-address-card'; color = '#28a745'; }
        else if (/^[+]?[0-9\s-]{8,15}$/.test(value)) { newIconClass = 'fa-phone'; color = '#17a2b8'; }
        else if (/^\d+$/.test(value)) { newIconClass = 'fa-hashtag'; }

        if (!dynamicIcon.classList.contains(newIconClass)) {
            dynamicIcon.style.transform = 'translateY(-50%) scale(0.5)';
            dynamicIcon.style.opacity = '0.5';
            setTimeout(() => {
                dynamicIcon.className = 'fa-solid input-icon ' + newIconClass;
                dynamicIcon.style.color = color;
                dynamicIcon.style.transform = 'translateY(-50%) scale(1.2)';
                dynamicIcon.style.opacity = '1';
                setTimeout(() => { dynamicIcon.style.transform = 'translateY(-50%) scale(1)'; }, 150);
            }, 100);
        } else { dynamicIcon.style.color = color; }
    }

    // Cargar imagen (AJAX)
    function cargarImagenUsuario(usuario) {
        if (!usuario.trim()) { 
            userAvatar.classList.remove('visible'); 
            ocultarMensaje('userRole'); 
            ocultarMensaje('userNotFound'); 
            return; 
        }
        
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            // Si parece un CI válido o es un email/user, buscamos
            fetch(`login.php?ajax=get_imagen&usuario=${encodeURIComponent(usuario)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userAvatar.src = data.imagen; 
                        userAvatar.classList.add('visible'); 
                        ocultarMensaje('userNotFound');
                        if (data.rol && data.rol.trim() !== '') { 
                            document.getElementById('roleText').textContent = data.rol; 
                            document.getElementById('userRole').setAttribute('data-role', data.rol); 
                            mostrarElemento('userRole'); 
                        } 
                    } else {
                        const notFoundText = document.getElementById('notFoundText'); 
                        notFoundText.textContent = 'Datos no encontrados'; 
                        mostrarElemento('userNotFound');
                        userAvatar.src = data.imagen; 
                        userAvatar.classList.add('visible'); 
                    }
                })
                .catch(error => console.error('Error fetching image', error));
        }, 600); 
    }

    // ==========================================
    // FUNCIÓN UI INTEGRADA PARA CI
    // ==========================================
    function procesarCI(valor) {
        // Solo validamos si son 11 dígitos numéricos
        if (/^\d{11}$/.test(valor)) {
            const resultado = validarCI(valor);
            ciFeedback.style.display = 'block';
            
            if (resultado.valido) {
                ciFeedback.innerHTML = resultado.mensaje;
                ciFeedback.style.color = '#28a745'; // Verde
                usuarioInput.classList.remove('is-invalid');
                usuarioInput.classList.add('is-valid');
            } else {
                ciFeedback.innerHTML = `<i class="fas fa-times-circle me-1"></i> ${resultado.mensaje}`;
                ciFeedback.style.color = '#dc3545'; // Rojo
                usuarioInput.classList.remove('is-valid');
                usuarioInput.classList.add('is-invalid');
            }
        } else {
            // Si no son 11 dígitos, limpiamos el feedback de CI
            ciFeedback.style.display = 'none';
            ciFeedback.innerHTML = '';
            usuarioInput.classList.remove('is-valid', 'is-invalid');
        }
    }
	
	
 // Event Listeners
    usuarioInput.addEventListener('input', function() { 
        const inputLength = this.value.length; 
        const valor = this.value.trim();

        // 1. Contador
        charCounter.textContent = `${inputLength}/${MAX_LENGTH}`; 
        if (inputLength === 0) { charCounter.classList.remove('show'); charCounter.classList.add('hide'); } 
        else { charCounter.classList.remove('hide'); charCounter.classList.add('show'); 
               if (inputLength > (MAX_LENGTH - 10)) charCounter.style.color = '#ff4757'; 
               else charCounter.style.color = 'rgba(255, 255, 255, 0.5)'; 
        } 
        
        // 2. Icono
        updateInputIcon(valor);

        // 3. Validación CI (Integrada)
        procesarCI(valor);

        // 4. Buscar Avatar
        if (valor.length === 0) { userAvatar.classList.remove('visible'); ocultarMensaje('userRole'); ocultarMensaje('userNotFound'); } 
        else { cargarImagenUsuario(valor); } 
    });

    usuarioInput.addEventListener('focus', function() { 
        if (this.value.length > 0) { charCounter.classList.remove('hide'); charCounter.classList.add('show'); setTimeout(() => { charCounter.style.opacity = '1'; }, 10); } 
        if (this.value.trim()) { cargarImagenUsuario(this.value); }
    });
    
    usuarioInput.addEventListener('blur', function() { 
        if (this.value.length === 0) { charCounter.classList.remove('show'); charCounter.classList.add('hide'); } 
    });
    
    // Inicializar
    if (usuarioInput.value.trim()) { 
        const val = usuarioInput.value.trim();
        updateInputIcon(val);
        cargarImagenUsuario(val); 
        procesarCI(val); // Verificar CI al cargar
    }
});

// Inactividad
document.addEventListener('DOMContentLoaded', function() {
    let inactivityTime = function() {
        let time; const warningTime = 120;
        const resetTimer = () => { clearTimeout(time); time = setTimeout(showInactivityWarning, warningTime * 1000); };
        const showInactivityWarning = () => {
            Swal.fire({ 
                title: 'Sesión inactiva', 
                text: 'El formulario se limpiará en 30 segundos.', 
                icon: 'info', background: '#0a0f1a', color: '#eee', 
                showCancelButton: false, confirmButtonText: '<i class="fa-solid fa-arrow-right"></i> Continuar', confirmButtonColor: '#007bff', 
                timer: 30000, timerProgressBar: true 
            }).then((result) => { 
                if (result.dismiss === Swal.DismissReason.timer) { 
                    document.querySelector('form').reset(); 
                    document.getElementById('userAvatar').classList.remove('visible'); 
                    document.getElementById('usuario').focus(); 
                } 
                resetTimer(); 
            });
        };
        window.onload = resetTimer; document.onmousemove = resetTimer; document.onkeypress = resetTimer; document.onclick = resetTimer; document.oninput = resetTimer; resetTimer();
    };
    inactivityTime();
});

// =========================================================
// LÓGICA DE MANTENIMIENTO Y MODO PROGRAMADOR
// =========================================================
const SwalTheme = Swal.mixin({ background: '#1e2a3a', color: '#eee', confirmButtonColor: '#ffc107' });

function openMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
}

function closeMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.remove('active'); document.body.style.overflow = 'auto'; }
}

// LOGICA RECUPERAR CONTRASEÑA
function openRecoverModal(e) {
    if(e) e.preventDefault();
    const modal = document.getElementById('recoverModal');
    if (modal) { modal.classList.add('active'); }
}

function closeRecoverModal() {
    const modal = document.getElementById('recoverModal');
    if (modal) { modal.classList.remove('active'); }
}

function disableLoginAccess() {
    // 1. Deshabilitar Botón e Inputs de Login
    const btn = document.getElementById('btnLogin');
    const user = document.getElementById('usuario');
    const pass = document.getElementById('password');
    const forgotLink = document.querySelector('.forgot-link');

    if(btn) {
        btn.disabled = true;
        btn.classList.add('btn-disabled-maint');
        btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento';
        
        if(user) user.disabled = true;
        if(pass) pass.disabled = true;
        if(forgotLink) forgotLink.classList.add('disabled-link');

        // Botón de refrescar
        if(!document.getElementById('btn-refresh-login')){
            const refreshBtn = document.createElement('button');
            refreshBtn.type = 'button';
            refreshBtn.id = 'btn-refresh-login';
            refreshBtn.className = 'btn-login animate__animated animate__fadeIn';
            refreshBtn.style.backgroundColor = '#ffc107';
            refreshBtn.style.color = '#000';
            refreshBtn.style.marginTop = '10px';
            refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Comprobar Estado';
            refreshBtn.onclick = (e) => { e.preventDefault(); recheckMaintenanceStatus(refreshBtn); };
            btn.parentNode.insertBefore(refreshBtn, btn.nextSibling);
        }
    }

    // 2. Detener parpadeo del subtítulo
    const subtitle = document.querySelector('.login-title h4');
    if (subtitle) {
        subtitle.style.animation = 'none';
        subtitle.style.color = '#6c757d';
        subtitle.style.textShadow = 'none';
        subtitle.textContent = 'SISTEMA EN MANTENIMIENTO';
    }

    // 3. Deshabilitar botón registro
    const regLink = document.querySelector('.register-link-combo');
    if (regLink) {
        regLink.classList.add('disabled-link');
        regLink.innerHTML = '<i class="fas fa-user-slash me-1"></i> Registro Suspendido';
    }
}

function recheckMaintenanceStatus(callerBtn = null) {
    let btn = callerBtn || document.getElementById('btn-recheck-maint');
    let originalContent = '';
    
    if (btn) {
        originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    fetch('login.php?action=check_status')
        .then(response => response.json())
        .then(data => {
            setTimeout(() => {
                if (data.maintenance) {
                    const now = new Date();
					const hora = now.toLocaleTimeString('en-US', { 
						hour: '2-digit', 
						minute: '2-digit', 
						second: '2-digit',
						hour12: true 
					});
                    SwalTheme.fire({
                        icon: 'info', title: 'Sistema en mantenimiento',
                        html: `El sistema continúa en mantenimiento.<br>Hora: ${hora}`,
                        confirmButtonText: '<i class="fas fa-check"></i> Entendido'
                    });
                } else {
                    SwalTheme.fire({
                        icon: 'success', title: '¡Mantenimiento finalizado!',
                        text: 'El sistema está operativo nuevamente.',
                        timer: 2000, showConfirmButton: false
                    }).then(() => { location.reload(); });
                }

                if (btn) {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }
            }, 800);
        })
        .catch(error => {
            SwalTheme.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar.' });
            if (btn) { btn.innerHTML = originalContent; btn.disabled = false; }
        });
}

// Lógica de carga Inicial para Mantenimiento vs Programador
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Detectar si venimos del botón "Acceder como Programador" (Bypass URL)
    const urlParams = new URLSearchParams(window.location.search);
    const isProgrammerBypass = urlParams.get('access_bypass') === 'true';

    // 2. Si NO es bypass, verificamos si hay mantenimiento
    if (!isProgrammerBypass) {
        fetch('login.php?action=check_status')
            .then(res => res.json())
            .then(data => {
                if (data.maintenance) {
                    openMaintenanceModal();
                    disableLoginAccess(); // Bloqueamos visualmente los inputs
                }
            })
            .catch(e => console.error(e));
    } else {
        // 3. Si ES bypass, mostramos los badges visuales
        
        // Badge Izquierdo
        const badge = document.createElement('div');
        badge.innerHTML = '<i class="fa-solid fa-user-secret"></i> Modo Programador';
        Object.assign(badge.style, {
            position: 'fixed', bottom: '10px', left: '10px',
            background: '#dc3545', color: 'white', padding: '5px 10px',
            borderRadius: '5px', fontSize: '12px', zIndex: '999999',
            boxShadow: '0 0 10px rgba(220, 53, 69, 0.5)'
        });
        document.body.appendChild(badge);
		
        // Badge Derecho (Salir)
        const exitBadge = document.createElement('div');
        exitBadge.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Salir del modo';
        exitBadge.title = "Cerrar sesión de programador y volver a vista normal";
        
        exitBadge.onclick = function() {
            // Limpiar URL y recargar
            const cleanUrl = window.location.origin + window.location.pathname;
            window.location.replace(cleanUrl);
        };

        Object.assign(exitBadge.style, {
            position: 'fixed', bottom: '10px', right: '10px',
            background: 'linear-gradient(135deg, #ffc107, #e0a800)',
            color: '#000', padding: '6px 12px', borderRadius: '6px',
            fontSize: '12px', fontWeight: '600', zIndex: '999998',
            boxShadow: '0 4px 12px rgba(255, 193, 7, 0.4)',
            cursor: 'pointer', border: '1px solid rgba(0, 0, 0, 0.1)'
        });
        document.body.appendChild(exitBadge);
    }
});
// =========================================================
// FUNCIONALIDAD "¿OLVIDASTE LA CONTRASEÑA?"
// =========================================================
function openForgotPassword() {
    Swal.fire({
        title: '🔐 Recuperar Contraseña',
        html: `
            <div style="text-align: center; padding: 10px 0;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <div style="background: linear-gradient(135deg, #007bff, #0056b3); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);">
                        <i class="fa-solid fa-key" style="font-size: 1.5rem; color: white;"></i>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0; color: #a0c8ff; font-size: 1.1rem;">Recuperación de Acceso</h3>
                        <p style="margin: 5px 0 0 0; color: #8899aa; font-size: 0.85rem;">Complete todos los campos requeridos</p>
                    </div>
                </div>
                
                <div style="background: rgba(0, 123, 255, 0.05); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(0, 123, 255, 0.1);">
                    <p style="color: #a0c8ff; margin-bottom: 15px; text-align: center; font-size: 0.95rem;">
                        <i class="fa-solid fa-circle-info" style="margin-right: 8px;"></i> 
                        Ingrese sus datos de verificación
                    </p>
                    
                    <!-- FILA DE CAMPOS: Usuario y Carné uno al lado del otro -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <!-- Campo Usuario -->
                        <div>
                            <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                                <i class="fa-solid fa-user" style="color: #007bff; margin-right: 8px;"></i> 
                                Nombre de Usuario
                            </label>
                            <div style="position: relative;">
                                <input type="text" id="forgotUsuario" 
                                       style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); 
                                              background: rgba(10, 20, 35, 0.6); color: white; font-size: 0.95rem;
                                              transition: all 0.3s ease;" 
                                       placeholder="Ej: juan.perez" 
                                       autocomplete="username">
                                <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #007bff;">
                                    <i class="fa-solid fa-at"></i>
                                </div>
                            </div>
                            <small style="color: #8899aa; font-size: 0.75rem; display: block; margin-top: 5px;">
                                Su nombre de acceso al sistema
                            </small>
                        </div>
                        
                        <!-- Campo Carné de Identidad -->
                        <div>
                            <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                                <i class="fa-solid fa-id-card" style="color: #28a745; margin-right: 8px;"></i> 
                                Carné de Identidad
                            </label>
                            <div style="position: relative;">
                                <input type="text" id="forgotCarnet" 
                                       style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); 
                                              background: rgba(10, 20, 35, 0.6); color: white; font-size: 0.95rem;
                                              transition: all 0.3s ease;" 
                                       placeholder="Ej: 85010112345" 
                                       autocomplete="off"
                                       maxlength="11">
                                <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #28a745;">
                                    <i class="fa-solid fa-address-card"></i>
                                </div>
                                <div style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #8899aa; font-size: 0.8rem; font-weight: bold;">
                                    11 dígitos
                                </div>
                            </div>
                            <small style="color: #8899aa; font-size: 0.75rem; display: block; margin-top: 5px;">
                                Sin guiones ni espacios
                            </small>
                        </div>
                    </div>
                    
                    <!-- Campo Nombre Completo (abajo) -->
                    <div>
                        <label style="display: block; color: #eee; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500;">
                            <i class="fa-solid fa-user-circle" style="color: #6f42c1; margin-right: 8px;"></i> 
                            Nombre Completo
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="forgotNombre" 
                                   style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); 
                                          background: rgba(10, 20, 35, 0.6); color: white; font-size: 0.95rem;
                                          transition: all 0.3s ease;" 
                                   placeholder="Ej: Juan Pérez Rodríguez" 
                                   autocomplete="name">
                            <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6f42c1;">
                                <i class="fa-solid fa-signature"></i>
                            </div>
                        </div>
                        <small style="color: #8899aa; font-size: 0.75rem; display: block; margin-top: 5px;">
                            Como aparece registrado en el sistema
                        </small>
                    </div>
                </div>
                
                <!-- Nota informativa -->
                <div style="background: rgba(255, 193, 7, 0.08); border-radius: 8px; padding: 12px; border-left: 3px solid #ffc107; margin-top: 15px;">
                    <div style="display: flex; align-items: center;">
                        <i class="fa-solid fa-shield-alt" style="color: #ffc107; margin-right: 10px; font-size: 0.9rem;"></i>
                        <span style="color: #ffc107; font-size: 0.85rem; font-weight: 500;">
                            Verificación de seguridad: Se validarán todos los campos antes de proceder
                        </span>
                    </div>
                </div>
            </div>
        `,
        background: '#1e2a3a',
        color: '#eee',
        width: 600,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-key" style="margin-right: 8px;"></i> Recuperar Contraseña',
        confirmButtonColor: '#007bff',
        cancelButtonText: '<i class="fa-solid fa-times" style="margin-right: 8px;"></i> Cancelar',
        cancelButtonColor: '#6c757d',
        showCloseButton: true,
        closeButtonHtml: '<i class="fa-solid fa-xmark"></i>',
        allowOutsideClick: false,
        allowEscapeKey: true,
        customClass: {
            container: 'forgot-password-modal',
            popup: 'forgot-password-popup'
        },
        didOpen: () => {
            // Agregar efectos de focus a los inputs
            const inputs = document.querySelectorAll('#forgotUsuario, #forgotCarnet, #forgotNombre');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#007bff';
                    this.style.boxShadow = '0 0 0 2px rgba(0, 123, 255, 0.2)';
                    this.style.background = 'rgba(10, 20, 35, 0.8)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = 'rgba(255,255,255,0.15)';
                    this.style.boxShadow = 'none';
                    this.style.background = 'rgba(10, 20, 35, 0.6)';
                });
                
                // Efecto al pasar el mouse
                input.addEventListener('mouseenter', function() {
                    if (document.activeElement !== this) {
                        this.style.borderColor = 'rgba(255,255,255,0.25)';
                    }
                });
                
                input.addEventListener('mouseleave', function() {
                    if (document.activeElement !== this) {
                        this.style.borderColor = 'rgba(255,255,255,0.15)';
                    }
                });
            });
            
            // Auto-seleccionar el primer campo
            setTimeout(() => {
                const firstInput = document.getElementById('forgotUsuario');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        },
        preConfirm: () => {
            const usuario = document.getElementById('forgotUsuario').value.trim();
            const carnet = document.getElementById('forgotCarnet').value.trim();
            const nombre = document.getElementById('forgotNombre').value.trim();
            
            if (!usuario) {
                Swal.showValidationMessage('<div style="text-align: left; padding: 10px;">Por favor ingrese su nombre de usuario</div>');
                return false;
            }
            
            if (!carnet) {
                Swal.showValidationMessage('<div style="text-align: left; padding: 10px;">Por favor ingrese su número de carné de identidad</div>');
                return false;
            }
            
            // Validar formato básico del carné (solo números, 11 dígitos)
            const carnetRegex = /^\d{11}$/;
            if (!carnetRegex.test(carnet)) {
                Swal.showValidationMessage('<div style="text-align: left; padding: 10px;">El número de carné debe contener exactamente 11 dígitos numéricos</div>');
                return false;
            }
            
            if (!nombre) {
                Swal.showValidationMessage('<div style="text-align: left; padding: 10px;">Por favor ingrese su nombre completo</div>');
                return false;
            }
            
            return { 
                usuario: usuario, 
                no_ci: carnet,
                nombre_completo: nombre 
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            recoverPassword(result.value);
        }
    });
}


function recoverPassword(data) {
    // 1. Mostrar loading inicial
    Swal.fire({
        title: 'Verificando existencia...',
        html: '<div style="padding: 20px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #007bff;"></i></div>',
        background: '#1e2a3a',
        color: '#eee',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    // 2. Preparar datos para VERIFICACIÓN (Paso 1)
    const formData = new URLSearchParams(data);
    formData.append('step', 'verify'); // Indicamos al PHP que solo verifique

    // 3. Primera llamada al servidor
    fetch('login.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        // A) Si hay error (usuario no existe o datos mal), mostrar error
        if (!result.success) {
            Swal.fire({
                title: '❌ Datos Incorrectos',
                text: result.message,
                icon: 'error',
                background: '#1e2a3a',
                color: '#eee',
                confirmButtonText: 'Intentar de nuevo',
                confirmButtonColor: '#dc3545'
            }).then(() => { openForgotPassword(); }); // Volver a abrir el form
            return;
        }

        // B) Si existe (success == true), mostrar MODAL DE CONFIRMACIÓN
        if (result.action === 'confirm_required') {
            Swal.fire({
                title: '⚠️ Confirmación Requerida',
                html: `
                    <div style="text-align: left; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); font-size: 0.95rem;">
                        <div style="margin-bottom: 8px;">
                            <span style="color: #8899aa;">El Usuario:</span> <strong style="color: #fff;">${result.usuario}</strong>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="color: #8899aa;">Con No. CI:</span> <strong style="color: #28a745;">${result.no_ci}</strong>
                        </div>
                        <div>
                            <span style="color: #8899aa;">Nombre:</span> <strong style="color: #a0c8ff;">${result.nombre}</strong>
                        </div>
                        <div style="margin-top: 10px; padding-top:10px; border-top: 1px dashed #555; color: #20c997; font-weight:bold;">
                            <i class="fa-solid fa-check"></i> ¡EXISTE EN EL SISTEMA!
                        </div>
                    </div>
                    
                    <p style="margin-top: 20px; font-size: 1.1em; color: #eee;">
                        ¿Está seguro que desea restablecer su contraseña?
                    </p>
                `,
                icon: 'question',
                background: '#1e2a3a',
                color: '#eee',
                
                // Configuración solicitada:
                showCancelButton: true,
                showCloseButton: true, // X para cancelar
                cancelButtonText: '<i class="fas fa-times me-2"></i> Entendido',
                confirmButtonText: '<i class="fa-solid fa-check"></i> SÍ, Restablecer',
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                allowOutsideClick: false, // No click fuera
                allowEscapeKey: false,
                reverseButtons: true
            }).then((confirmResult) => {
                
                // 4. Si el usuario dice SÍ, hacemos la SEGUNDA llamada (Reset)
                if (confirmResult.isConfirmed) {
                    
                    // Loading de proceso
                    Swal.fire({
                        title: 'Restableciendo...',
                        html: '<div style="padding: 20px;"><i class="fa-solid fa-cog fa-spin fa-2x" style="color: #ffc107;"></i></div>',
                        background: '#1e2a3a',
                        color: '#eee',
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });

                    // Cambiamos el paso a 'reset'
                    formData.set('step', 'reset');

                    fetch('login.php?action=forgot_password', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                    .then(resp => resp.json())
                    .then(finalResult => {
                        if(finalResult.success && finalResult.action === 'reset_success') {
                            // MOSTRAR LA NUEVA CONTRASEÑA
                            Swal.fire({
                                title: '✅ ¡Contraseña Restablecida!',
                                html: `
                                    <div style="text-align: center; padding: 15px;">
                                        <div style="background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                                            <p style="color: #eee; margin-bottom: 10px;">Su nueva contraseña es:</p>
                                            <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border: 1px dashed #28a745;">
                                                <code style="font-size: 1.8rem; font-weight: bold; color: #28a745; letter-spacing: 2px;">
                                                    ${finalResult.nueva_password}
                                                </code>
                                            </div>
                                        </div>
                                        <p style="color: #888; font-size: 0.85rem;">Cópiela e inicie sesión inmediatamente.</p>
                                    </div>
                                `,
                                background: '#1e2a3a',
                                color: '#eee',
                                confirmButtonText: '<i class="fa-solid fa-check"></i> Entendido',
                                confirmButtonColor: '#28a745',
                                allowOutsideClick: false,
                                showCloseButton: true
                            }).then(() => {
                                // Copiar al portapapeles
                                navigator.clipboard.writeText(finalResult.nueva_password);
                                // Llenar formulario
                                document.getElementById('usuario').value = finalResult.usuario;
                                document.getElementById('password').value = finalResult.nueva_password;
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo restablecer' });
                        }
                    });
                }
            });
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Error de conexión', background: '#1e2a3a', color: '#fff' });
    });
}

// =========================================================
// VALIDACIÓN DE CAMPOS VACÍOS CON SWEETALERT
// =========================================================
// Añadir al evento DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Atajo de teclado (Alt+F)
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'f') {
            e.preventDefault();
			<?php if ($restabpw == 1): ?>
                openForgotPassword(); // Si está activado en BD, abre el formulario real
            <?php else: ?>
                openRecoverModal();   // Si está desactivado, abre el modal de "Contactar Admin"
            <?php endif; ?>
        }
    });

    // AQUI COMIENZA LA VALIDACIÓN DE CAMPOS VACIOS
    const loginForm = document.getElementById('loginForm');
    
    // Verificamos que el formulario exista antes de agregar el evento
    if (loginForm) {
        const userInput = document.getElementById('usuario');
        const passInput = document.getElementById('password');

        loginForm.addEventListener('submit', function(e) {
            const userVal = userInput.value.trim();
            const passVal = passInput.value.trim();
            let errorMessage = '';
            let focusTarget = null;

            // Caso 1: Ambos vacíos
            if (!userVal && !passVal) {
                errorMessage = 'Por favor, ingresa tus credenciales de acceso.';
                focusTarget = userInput;
            } 
            // Caso 2: Solo falta usuario
            else if (!userVal) {
                errorMessage = 'El campo de <b>Usuario/Identificación</b> es obligatorio.';
                focusTarget = userInput;
            } 
            // Caso 3: Solo falta contraseña
            else if (!passVal) {
                errorMessage = 'La <b>Contraseña</b> no puede estar vacía.';
                focusTarget = passInput;
            }

            // Si hay error, detenemos el envío y mostramos alerta
            if (errorMessage !== '') {
                e.preventDefault(); // Evita que se recargue la página

                Swal.fire({
                    icon: 'warning',
                    title: 'Campos Vacíos',
                    html: errorMessage,
                    background: '#1e2a3a',
                    color: '#fff',
                    confirmButtonColor: '#ffc107',
                    confirmButtonText: '<i class="fa-solid fa-pen"></i> Completar',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                }).then(() => {
                    // Poner el foco en el campo vacío automáticamente
                    if (focusTarget) {
                        focusTarget.focus();
                        // Pequeña animación visual en el input vacío
                        focusTarget.style.transition = 'all 0.3s';
                        focusTarget.style.borderColor = '#ffc107';
                        focusTarget.style.boxShadow = '0 0 15px rgba(255, 193, 7, 0.3)';
                        setTimeout(() => {
                            focusTarget.style.borderColor = ''; 
                            focusTarget.style.boxShadow = '';
                        }, 2000);
                    }
                });
            }
        });
    }
});
</script>

<?php if (!empty($error)): ?>
<script>
// Sanitizar el mensaje manualmente
var errorMessage = <?php echo json_encode($error); ?>;

Swal.fire({
    icon: 'error',
    title: 'Acceso Denegado',
    html: errorMessage,
    background: '#1e2a3a',
    color: '#fff',
    confirmButtonColor: '#ff4757',
    confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
}).then(() => {
    // Después de cerrar, enfocar usuario
    setTimeout(function() {
        var userField = document.getElementById('usuario');
        if (userField) {
            userField.focus();
            userField.style.border = '2px solid #ffc107';
            setTimeout(function() {
                userField.style.border = '';
            }, 1000);
        }
    }, 100);
});
</script>
<?php endif; ?>

</body>
</html>