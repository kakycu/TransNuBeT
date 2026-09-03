<?php
// Si no existe la configuración del sistema, avisar y redirigir al instalador
if (!file_exists(__DIR__ . '/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
        <title>Sistema sin configurar</title>
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script src="js/sweetalert2.all.min.js"></script>
        <style>
            body {
                margin:0;
                padding:0;
                background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height:100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    </head>
    <body>
        <script>
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-cogs" style="color:#f87171"></i> Configuración no encontrada',
            html: 'No existe la <b>configuración del sistema</b>.<br>Deberá reinstalar la base de datos y sus archivos.',
            confirmButtonText: '<i class="fas fa-cogs"></i> Ir al Instalador',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6',
            allowOutsideClick: false,
            backdrop: 'rgba(0,0,0,0.85)'
        }).then(function (result) {
            if (result.isConfirmed) {
                var w = window.open('../InstalarBD/InstalarBD.php', '_blank');
                if (!w) { window.location.href = '../InstalarBD/InstalarBD.php'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ============================================================
// 1. VERIFICAR ESTADO DE MYSQL (capturando excepción)
// Credenciales desde config.php (nombre de BD y contraseña)
// ============================================================
$db_ok = false;
$db_error = null;
$host = DB_HOST;
$user = DB_USER;
$password = DB_PASS;  // Contraseña de MySQL (desde config.php)
$database = DB_NAME;

try {
    $test_conn = new mysqli($host, $user, $password, $database);
    $db_ok = true;
    $test_conn->close();
} catch (mysqli_sql_exception $e) {
    $db_ok = false;
    $db_error = $e->getMessage();
}

// ============================================================
// 2. SI LA BD ESTÁ CONECTADA, CARGAR CONFIGURACIÓN Y PDO
// ============================================================
$COMPANY_NAME = defined('COMPANY_NAME') ? COMPANY_NAME : 'SIN NOMBRE';
$SITE_VERSION = defined('SITE_VERSION') ? SITE_VERSION : 'v2.0.1';
$SITE_NAME = $COMPANY_NAME . ' - Nóminas';
$SLOGAN = defined('SLOGAN') ? SLOGAN : 'Eslogan Corporativo Identificativo.';
$restabpw = 0; // valor por defecto
$pdo = null;

if ($db_ok) {
    // Incluir el archivo que define $pdo (asumiendo que está correctamente configurado)
    require_once 'config/database.php';
    
    // Cargar configuración desde la base de datos
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'configuracion_general'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'site_name')");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (isset($configs['nombre_empresa']) && !empty($configs['nombre_empresa'])) {
                $COMPANY_NAME = $configs['nombre_empresa'];
                $SITE_NAME = $COMPANY_NAME . ' - Nóminas';
            }
        }
        
        // Obtener configuración de WhatsApp y restablecimiento
        $stmt = $pdo->prepare("SELECT restabpw FROM configuracion_sistema LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $restabpw = $row['restabpw'];
        }
    } catch (PDOException $e) {
        error_log("Error al cargar configuración: " . $e->getMessage());
    }
}

// Correo de soporte: desde configuracion_general (parametro 'email_soporte'); si está vacío en la BD, kakycu@gmail.com
$email_soporte = 'kakycu@gmail.com';
if ($db_ok && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'email_soporte' LIMIT 1");
        $stmt->execute();
        $tmp_correo = trim((string)$stmt->fetchColumn());
        if ($tmp_correo !== '') $email_soporte = $tmp_correo;
    } catch (Exception $e) {
        $email_soporte = 'kakycu@gmail.com';
    }
}
$email_soporte_js = htmlspecialchars($email_soporte, ENT_QUOTES, 'UTF-8');

// ¿Está configurado el correo SMTP para recuperación de contraseña?
$mail_configurado = false;
if ($db_ok && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracion_general WHERE parametro = 'mail_activo' LIMIT 1");
        $stmt->execute();
        $mail_configurado = ((string)$stmt->fetchColumn() === '1');
    } catch (Exception $e) {
        $mail_configurado = false;
    }
}

// Detectar bypass de programador (solo funciona si la BD está OK, pero se muestra igual)
$es_programador = isset($_GET['access_bypass']) && $_GET['access_bypass'] === 'true';

// Verificar si ya está logueado (solo si hay BD)
if ($db_ok && isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// ============================================================
// 2B. LOGIN CON GOOGLE (OAuth 2.0) - verificado antes del AJAX
// ============================================================
$google_client_id = '';
$google_client_secret = '';
$google_configurado = false;
$redirect_uri_google = '';
if ($db_ok && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('google_client_id','google_client_secret')");
        $stmt->execute();
        $gcfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $google_client_id = trim($gcfg['google_client_id'] ?? '');
        $google_client_secret = trim($gcfg['google_client_secret'] ?? '');
        $google_configurado = ($google_client_id !== '');
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $gHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $gDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/login.php')), '/');
        $redirect_uri_google = $proto . '://' . $gHost . $gDir . '/login.php?action=google_callback';
    } catch (Exception $e) {
        $google_configurado = false;
    }
}

$flash_login = $_SESSION['login_flash'] ?? '';
if ($flash_login !== '') {
    unset($_SESSION['login_flash']);
}

if ($db_ok && $google_configurado && isset($_GET['action']) && $_GET['action'] === 'google') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['google_popup'] = (isset($_GET['popup']) && $_GET['popup'] === '1') ? 1 : 0;
    $google_state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $google_state;
    $auth_params = [
        'client_id'     => $google_client_id,
        'redirect_uri'  => $redirect_uri_google,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'state'         => $google_state,
    ];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($auth_params));
    exit;
}

if ($db_ok && $google_configurado && isset($_GET['action']) && $_GET['action'] === 'google_callback') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_GET['error'])) {
        $_SESSION['login_flash'] = 'google_cancelado';
        header('Location: login.php'); exit;
    }
    $cb_state = $_GET['state'] ?? '';
    $cb_code = $_GET['code'] ?? '';
    $session_state = $_SESSION['google_state'] ?? '';
    unset($_SESSION['google_state']);
    if ($cb_code === '' || $cb_state === '' || $session_state === '' || !hash_equals($session_state, $cb_state)) {
        $_SESSION['login_flash'] = 'google_state_invalido';
        header('Location: login.php'); exit;
    }
    // Intercambiar código por token
    $token_res = null;
    try {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $cb_code,
                'client_id'     => $google_client_id,
                'client_secret' => $google_client_secret,
                'redirect_uri'  => $redirect_uri_google,
                'grant_type'    => 'authorization_code',
            ]),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $token_res = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
    } catch (Exception $e) { $token_res = null; }
    if (empty($token_res['access_token'])) {
        $_SESSION['login_flash'] = 'google_error';
        header('Location: login.php'); exit;
    }
    // Obtener perfil del usuario
    $userinfo = null;
    try {
        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token_res['access_token']],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $userinfo = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
    } catch (Exception $e) { $userinfo = null; }

    $email_google = strtolower(trim($userinfo['email'] ?? ''));
    if ($email_google === '') {
        $_SESSION['login_flash'] = 'google_error';
        header('Location: login.php'); exit;
    }

    // ============================================================
    // EXTRAER DATOS DEL PERFIL DE GOOGLE
    // ============================================================
    $google_id       = $userinfo['sub'] ?? '';
    $google_nombre   = $userinfo['given_name'] ?? '';
    $google_apellido = $userinfo['family_name'] ?? '';
    $google_completo = $userinfo['name'] ?? ($google_nombre . ' ' . $google_apellido);
    $google_foto     = $userinfo['picture'] ?? '';
    // Google devuelve la foto en 96px (=s96-c); solicitar una versión más grande
    if ($google_foto !== '' && strpos($google_foto, 'googleusercontent.com') !== false) {
        $google_foto = preg_replace('/=s[0-9]+-c(\?.*)?$/', '=s256-c', $google_foto);
        if (strpos($google_foto, '=s256-c') === false) {
            $google_foto .= '=s256-c';
        }
    }

    // Buscar email en clasif_usuarios
    if (ob_get_level()) ob_end_clean();
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.codigo as rol_codigo, r.descripcion as rol_descripcion 
            FROM clasif_usuarios u 
            LEFT JOIN clasif_rol r ON u.rol_id = r.id 
            WHERE u.email = ? AND u.activo = 1
        ");
        $stmt->execute([$email_google]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $user_data = false; }

    if (!$user_data) {
        $_SESSION['login_flash'] = 'google_email_no_existe';
        header('Location: login.php'); exit;
    }

    // OPCIONAL: Si el usuario no tiene foto en la BD, guardamos la foto de Google
    if (empty($user_data['foto']) && !empty($google_foto)) {
        try {
            $updFoto = $pdo->prepare("UPDATE clasif_usuarios SET foto = ? WHERE id = ?");
            $updFoto->execute([$google_foto, $user_data['id']]);
            $user_data['foto'] = $google_foto; // Actualizar para la sesión actual
        } catch (Exception $e) { }
    }

    $enMantenimiento = false;
    try {
        $stmtMaint = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
        $stmtMaint->execute();
        $maintResult = $stmtMaint->fetch(PDO::FETCH_ASSOC);
        $enMantenimiento = ($maintResult && $maintResult['modo_mantenimiento'] >= 1);
    } catch (Exception $e) {}

    if ($enMantenimiento && $user_data['rol_id'] != 5) {
        $_SESSION['login_flash'] = "El sistema se encuentra en MANTENIMIENTO. Su rol no tiene permisos para acceder en este momento.";
        header('Location: login.php'); exit;
    }

    // ============================================================
    // REGISTRAR EN SESIÓN (Priorizando BD o Google según prefieras)
    // ============================================================
    $_SESSION['user_id']          = $user_data['id'];
    $_SESSION['username']         = $user_data['usuario'];
    $_SESSION['user_nombre']      = !empty($user_data['nombre']) ? ($user_data['nombre'] . ' ' . $user_data['apellidos']) : $google_completo;
    $_SESSION['user_foto']        = !empty($user_data['foto']) ? $user_data['foto'] : $google_foto;
    $_SESSION['user_ci']          = $user_data['no_ci'];
    $_SESSION['user_email']       = $user_data['email'];
    $_SESSION['rol_id']           = $user_data['rol_id'];
    $_SESSION['rol_codigo']       = $user_data['rol_codigo'];
    $_SESSION['rol_descripcion']  = $user_data['rol_descripcion'];
	$_SESSION['user_foto']        = !empty($google_foto) ? $google_foto : ($user_data['foto'] ?? '');
    $_SESSION['auth_provider']    = 'google'; // Para saber que inició con Google
    $_SESSION['logged_in']        = true;
    $_SESSION['login_time']       = time();

    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_stmt = $pdo->prepare("
            INSERT INTO sys_log_accesos (usuario_id, ip_address, user_agent, fecha_acceso) 
            VALUES (?, ?, ?, NOW())
        ");
        $log_stmt->execute([$user_data['id'], $ip_address, $user_agent]);
    } catch (PDOException $e) {}

    if (!empty($_SESSION['google_popup'])) {
        unset($_SESSION['google_popup']);
        $ultimo_google = null;
        try {
            $ulg = $pdo->prepare("SELECT fecha_acceso FROM sys_log_accesos WHERE usuario_id = ? ORDER BY fecha_acceso DESC LIMIT 1 OFFSET 1");
            $ulg->execute([$user_data['id']]);
            $rowG = $ulg->fetch(PDO::FETCH_ASSOC);
            if ($rowG) $ultimo_google = $rowG['fecha_acceso'];
        } catch (PDOException $e) {}
        $perfil_js = json_encode([
            'nombre' => trim(($user_data['nombre'] ?? '') . ' ' . ($user_data['apellidos'] ?? '')),
            'email'  => $user_data['email'] ?? '',
            'foto'   => !empty($google_foto) ? $google_foto : ($user_data['foto'] ?? ''),
            'ci'     => $user_data['no_ci'] ?? '',
            'rol'    => $user_data['rol_descripcion'] ?? $user_data['rol_codigo'] ?? '',
            'ultimo_acceso' => $ultimo_google,
            'redirect' => 'dashboard.php'
        ]);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Completando acceso...</title></head><body><script>if (window.opener) { window.opener.postMessage({ type: "google_auth_ok", perfil: ' . $perfil_js . ' }, "*"); } window.close();</script></body></html>';
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

if ($db_ok && $google_configurado && isset($_GET['action']) && $_GET['action'] === 'google_token') {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Respuesta JSON para el flujo con ventana emergente de Google (Google Identity Services)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    $credential = trim($_POST['credential'] ?? '');
    if ($credential === '') {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'No se recibió la credencial de Google.']);
        exit;
    }

    // ============================================================
    // VERIFICAR EL ID TOKEN (JWT) DEVUELTO POR EL POPUP DE GOOGLE
    // ============================================================
    $idinfo = null;
    try {
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $respBody = (string)curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $idinfo = json_decode($respBody, true);
        }
    } catch (Exception $e) { $idinfo = null; }

    if (!is_array($idinfo) || empty($idinfo['sub'])) {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'No se pudo verificar la sesión de Google.']);
        exit;
    }

    // Validar audiencia (debe ser nuestro client_id), emisor y expiración
    if (($idinfo['aud'] ?? '') !== $google_client_id) {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'El token de Google no corresponde a esta aplicación.']);
        exit;
    }
    $iss_val = $idinfo['iss'] ?? '';
    if ($iss_val !== 'https://accounts.google.com' && $iss_val !== 'accounts.google.com') {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'Emisor de token de Google no válido.']);
        exit;
    }
    if (isset($idinfo['exp']) && time() > intval($idinfo['exp'])) {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'El token de Google ha expirado. Vuelva a intentarlo.']);
        exit;
    }
    $email_verificado = $idinfo['email_verified'] ?? false;
    if ($email_verificado !== true && (string)$email_verificado !== 'true') {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'El correo de Google no está verificado.']);
        exit;
    }

    $email_google = strtolower(trim($idinfo['email'] ?? ''));
    if ($email_google === '') {
        echo json_encode(['ok' => false, 'error' => 'google_error', 'message' => 'Google no devolvió un correo asociado.']);
        exit;
    }

    // ============================================================
    // EXTRAER DATOS DEL PERFIL DE GOOGLE
    // ============================================================
    $google_id       = $idinfo['sub'] ?? '';
    $google_nombre   = $idinfo['given_name'] ?? '';
    $google_apellido = $idinfo['family_name'] ?? '';
    $google_completo = $idinfo['name'] ?? ($google_nombre . ' ' . $google_apellido);
    $google_foto     = $idinfo['picture'] ?? '';
    // Google devuelve la foto en 96px (=s96-c); solicitar una versión más grande
    if ($google_foto !== '' && strpos($google_foto, 'googleusercontent.com') !== false) {
        $google_foto = preg_replace('/=s[0-9]+-c(\?.*)?$/', '=s256-c', $google_foto);
        if (strpos($google_foto, '=s256-c') === false) {
            $google_foto .= '=s256-c';
        }
    }

    // Buscar email en clasif_usuarios
    if (ob_get_level()) ob_end_clean();
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.codigo as rol_codigo, r.descripcion as rol_descripcion 
            FROM clasif_usuarios u 
            LEFT JOIN clasif_rol r ON u.rol_id = r.id 
            WHERE u.email = ? AND u.activo = 1
        ");
        $stmt->execute([$email_google]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $user_data = false; }

    if (!$user_data) {
        echo json_encode(['ok' => false, 'error' => 'google_email_no_existe', 'message' => 'Su cuenta de Google no está registrada en el sistema. Solicite su cuenta al administrador para poder acceder.']);
        exit;
    }

    // OPCIONAL: Si el usuario no tiene foto en la BD, guardamos la foto de Google
    if (empty($user_data['foto']) && !empty($google_foto)) {
        try {
            $updFoto = $pdo->prepare("UPDATE clasif_usuarios SET foto = ? WHERE id = ?");
            $updFoto->execute([$google_foto, $user_data['id']]);
            $user_data['foto'] = $google_foto; // Actualizar para la sesión actual
        } catch (Exception $e) { }
    }

    $enMantenimiento = false;
    try {
        $stmtMaint = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
        $stmtMaint->execute();
        $maintResult = $stmtMaint->fetch(PDO::FETCH_ASSOC);
        $enMantenimiento = ($maintResult && $maintResult['modo_mantenimiento'] >= 1);
    } catch (Exception $e) {}

    if ($enMantenimiento && $user_data['rol_id'] != 5) {
        echo json_encode(['ok' => false, 'error' => 'MANTENIMIENTO', 'message' => 'El sistema se encuentra en MANTENIMIENTO. Su rol no tiene permisos para acceder en este momento.']);
        exit;
    }

    // ============================================================
    // REGISTRAR EN SESIÓN (Priorizando BD o Google según prefieras)
    // ============================================================
    $_SESSION['user_id']          = $user_data['id'];
    $_SESSION['username']         = $user_data['usuario'];
    $_SESSION['user_nombre']      = !empty($user_data['nombre']) ? ($user_data['nombre'] . ' ' . $user_data['apellidos']) : $google_completo;
    $_SESSION['user_foto']        = !empty($user_data['foto']) ? $user_data['foto'] : $google_foto;
    $_SESSION['user_ci']          = $user_data['no_ci'];
    $_SESSION['user_email']       = $user_data['email'];
    $_SESSION['rol_id']           = $user_data['rol_id'];
    $_SESSION['rol_codigo']       = $user_data['rol_codigo'];
    $_SESSION['rol_descripcion']  = $user_data['rol_descripcion'];
	$_SESSION['user_foto']        = !empty($google_foto) ? $google_foto : ($user_data['foto'] ?? '');
    $_SESSION['auth_provider']    = 'google'; // Para saber que inició con Google
    $_SESSION['logged_in']        = true;
    $_SESSION['login_time']       = time();

    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_stmt = $pdo->prepare("
            INSERT INTO sys_log_accesos (usuario_id, ip_address, user_agent, fecha_acceso) 
            VALUES (?, ?, ?, NOW())
        ");
        $log_stmt->execute([$user_data['id'], $ip_address, $user_agent]);
    } catch (PDOException $e) {}

    // Último acceso (para el aviso de bienvenida del login AJAX)
    $ultimo_acceso = null;
    try {
        $ul_stmt = $pdo->prepare("SELECT fecha_acceso FROM sys_log_accesos WHERE usuario_id = ? ORDER BY fecha_acceso DESC LIMIT 1 OFFSET 1");
        $ul_stmt->execute([$user_data['id']]);
        $ul_row = $ul_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ul_row) $ultimo_acceso = $ul_row['fecha_acceso'];
    } catch (PDOException $e) {}

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'nombre' => trim(($user_data['nombre'] ?? '') . ' ' . ($user_data['apellidos'] ?? '')),
        'usuario' => $user_data['usuario'],
        'ci' => $user_data['no_ci'] ?? '',
        'rol' => $user_data['rol_descripcion'] ?? $user_data['rol_codigo'] ?? '',
        'foto' => !empty($google_foto) ? $google_foto : ($user_data['foto'] ?? ''),
        'email' => $user_data['email'] ?? '',
        'redirect' => 'dashboard.php',
        'ultimo_acceso' => $ultimo_acceso
    ]);
    exit;
}

$error = '';
if ($flash_login !== '') {
    $error = $flash_login;
}
$username = '';

// ============================================================
// 3. MANEJADORES AJAX (API INTERNA) - con verificación de BD
// ============================================================
if ((isset($_GET['action']) || isset($_GET['ajax'])) && $db_ok) {
    if (ob_get_length()) ob_clean();
    error_reporting(0);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $action = $_GET['action'] ?? $_GET['ajax'];
    
    try {
        // A) Verificar estado de mantenimiento
        if ($action === 'check_status') {
            // Verificar conexión a MySQL en este momento
            $dbOkNow = false;
            $testConn = @new mysqli($host, $user, $password, $database);
            if (!$testConn->connect_error) {
                $dbOkNow = true;
                $testConn->close();
            }
            $response = ['db_ok' => $dbOkNow, 'maintenance' => false];
            if ($dbOkNow) {
                // Si la BD está OK, también consultar mantenimiento
                try {
                    $stmt = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && isset($result['modo_mantenimiento']) && $result['modo_mantenimiento'] != 0) {
                        $response['maintenance'] = true;
                    }
                } catch (Exception $e) { }
            }
            echo json_encode($response);
            exit;
        }
        
        // B) Obtener Imagen y Rol
        if ($action === 'get_imagen') {
            $dato_entrada = trim($_GET['usuario'] ?? '');
            if (empty($dato_entrada)) {
                echo json_encode(['success' => false, 'error' => 'Usuario vacío']);
                exit;
            }
            
            $defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');
            
            $sql = "SELECT foto, rol_id, nombre, apellidos, usuario, no_ci FROM clasif_usuarios 
                    WHERE usuario = ? OR email = ? OR no_ci = ? 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $roles = [1 => 'Administrador', 2 => 'Visualizador', 3 => 'Contador / Editor', 4 => 'Supervisor General', 5 => 'Programador'];
                $rol_nombre = isset($result['rol_id']) ? ($roles[$result['rol_id']] ?? 'Usuario') : 'Usuario';
                $imagen = !empty($result['foto']) ? $result['foto'] : $defaultSvg;
                $nombre_completo = trim(($result['nombre'] ?? '') . ' ' . ($result['apellidos'] ?? ''));
                echo json_encode(['success' => true, 'imagen' => $imagen, 'rol' => $rol_nombre, 'nombre_completo' => $nombre_completo, 'usuario' => $result['usuario'] ?? '', 'ci' => $result['no_ci'] ?? '']);
            } else {
                echo json_encode(['success' => false, 'imagen' => $defaultSvg, 'rol' => null, 'nombre_completo' => null, 'usuario' => null]);
            }
            exit;
        }
        
        // C) Recuperación de contraseña por correo electrónico (verificación por email + CI + nombre)
        if ($action === 'forgot_password') {
            require_once 'config/mail.php';
            asegurarColumnasResetToken($pdo);
            asegurarParamsMail($pdo);
            asegurarTarifasNocturnidad($pdo);

            $email = trim($_POST['email'] ?? '');
            $no_ci = trim($_POST['no_ci'] ?? '');
            $nombre_completo = trim($_POST['nombre_completo'] ?? '');

            if (empty($email)) { echo json_encode(['success' => false, 'message' => 'Ingrese su correo electrónico']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success' => false, 'message' => 'El formato del correo no es válido']); exit; }
            if (empty($no_ci)) { echo json_encode(['success' => false, 'message' => 'Ingrese su carné de identidad']); exit; }
            if (!preg_match('/^\d{11}$/', $no_ci)) { echo json_encode(['success' => false, 'message' => 'El carné de identidad debe contener 11 dígitos']); exit; }
            if (empty($nombre_completo)) { echo json_encode(['success' => false, 'message' => 'Ingrese su nombre completo']); exit; }

            $sql = "SELECT id, usuario, nombre, apellidos, email, no_ci, activo FROM clasif_usuarios WHERE email = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'El correo electrónico no está registrado en el sistema']);
                exit;
            }

            if ((int)$user['activo'] !== 1) {
                echo json_encode(['success' => false, 'message' => 'Su cuenta está desactivada. Contacte al administrador del sistema.']);
                exit;
            }

            // 1) Verificar carné de identidad (ignorando guiones/espacios)
            $ci_normalizado = preg_replace('/[^0-9]/', '', $no_ci);
            $user_ci_normalizado = isset($user['no_ci']) ? preg_replace('/[^0-9]/', '', $user['no_ci']) : '';
            if (empty($user_ci_normalizado) || $user_ci_normalizado !== $ci_normalizado) {
                echo json_encode(['success' => false, 'message' => 'El carné de identidad no coincide con el correo registrado']);
                exit;
            }

            // 2) Verificar nombre completo (insensible a mayúsculas)
            $nombre_real = trim(($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? ''));
            if (strtolower(trim($nombre_completo)) !== strtolower($nombre_real)) {
                echo json_encode(['success' => false, 'message' => 'El nombre completo no coincide con el correo registrado']);
                exit;
            }

            $token = bin2hex(random_bytes(32));

            $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET reset_token = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?");
            $stmtUpd->execute([$token, $user['id']]);

            $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            if ($base === '' || $base === '.') $base = '';
            $enlace = $esquema . '://' . $host . $base . '/login.php?reset_token=' . $token;

            $nombre = trim(($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? ''));
            $empresa = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom - Nóminas';
            $subject = 'Restablecimiento de contraseña - ' . $empresa;
            $anio = date('Y');

            // ============================================================
            // PLANTILLA MEJORADA DE CORREO - ESTILO WINDOWS 11 DARK MODE
            // ============================================================
            $htmlBody = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Restablecimiento de contraseña</title>
    <style>
        /* Reset y estilos base */
        * {
            margin:0;
            padding:0;
            box-sizing: border-box;
        }
        body {
            background-color: #1a1a1a;
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            line-height:1.5;
            padding:1.25rem;
        }
        .email-container {
            max-width:36.25rem;
            margin:0 auto;
            background: #2d2d2d;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.6);
            border: 0.0625rem solid rgba(255,255,255,0.08);
        }
        /* HEADER - Estilo Windows 11 */
        .header {
            background: #202020;
            padding:1.75rem 2rem 1.5rem;
            border-bottom: 0.0625rem solid rgba(255,255,255,0.06);
            position: relative;
        }
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom:0.75rem;
        }
        .header-icon {
            display: flex;
            align-items: center;
            gap:0.75rem;
        }
        .header-icon .icon-circle {
            width:2.5rem;
            height:2.5rem;
            background: linear-gradient(135deg, #0078d4, #0066b3);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size:1.125rem;
            color: #fff;
            box-shadow: 0 0.125rem 0.75rem rgba(0,120,212,0.3);
        }
        .header-icon .app-name {
            color: #e8e8e8;
            font-size:0.8125rem;
            font-weight: 500;
            letter-spacing:0.0188rem;
        }
        .header-icon .app-name span {
            color: #8a8a8a;
            font-weight: 400;
        }
        .header-badge {
            background: rgba(0,120,212,0.15);
            color: #6cb4f0;
            font-size:0.625rem;
            font-weight: 600;
            padding:0.25rem 0.75rem;
            border-radius: 0.75rem;
            border: 0.0625rem solid rgba(0,120,212,0.2);
            letter-spacing:0.0312rem;
            text-transform: uppercase;
        }
        .header-title {
            color: #f0f0f0;
            font-size:1.25rem;
            font-weight: 600;
            letter-spacing:-0.0188rem;
        }
        .header-subtitle {
            color: #8a8a8a;
            font-size:0.8125rem;
            margin-top:0.25rem;
        }
        /* BODY */
        .body {
            padding:2rem 2rem 1.5rem;
            color: #d4d4d4;
        }
        .greeting {
            font-size:0.9375rem;
            color: #e8e8e8;
            margin-bottom:1.25rem;
        }
        .greeting strong {
            color: #ffffff;
            font-weight: 600;
        }
        .message-text {
            font-size:0.875rem;
            color: #c8c8c8;
            margin-bottom:1.125rem;
            line-height:1.6;
        }
        .message-text strong {
            color: #e8e8e8;
        }
        /* CARD - Estilo Windows 11 */
        .info-card {
            background: #252525;
            border-radius: 0.5rem;
            padding:1.25rem 1.5rem;
            margin:1.25rem 0 1.5rem;
            border: 0.0625rem solid rgba(255,255,255,0.06);
            border-left: 0.25rem solid #0078d4;
        }
        .info-card .card-row {
            display: flex;
            justify-content: space-between;
            padding:0.375rem 0;
            font-size:0.8125rem;
            border-bottom: 0.0625rem solid rgba(255,255,255,0.04);
        }
        .info-card .card-row:last-child {
            border-bottom: none;
        }
        .info-card .card-label {
            color: #8a8a8a;
        }
        .info-card .card-value {
            color: #e8e8e8;
            font-weight: 500;
        }
        /* BOTÓN - Estilo Windows 11 */
        .btn-container {
            text-align: center;
            margin:1.75rem 0 1.25rem;
        }
        .btn-reset {
            display: inline-block;
            background: #0078d4;
            color: #ffffff !important;
            padding:0.875rem 2.5rem;
            border-radius: 0.25rem;
            text-decoration: none;
            font-weight: 600;
            font-size:0.875rem;
            letter-spacing:0.0188rem;
            transition: background 0.2s ease;
            box-shadow: 0 0.25rem 1rem rgba(0,120,212,0.25);
            border: 0.0625rem solid rgba(255,255,255,0.1);
        }
        .btn-reset:hover {
            background: #006cc1;
            text-decoration: none;
        }
        .btn-reset i {
            margin-right:0.5rem;
        }
        /* Enlace alternativo */
        .link-box {
            background: #1f1f1f;
            border-radius: 0.375rem;
            padding:0.875rem 1.125rem;
            margin:1rem 0 1.5rem;
            border: 0.0625rem solid rgba(255,255,255,0.05);
        }
        .link-box .link-label {
            font-size:0.6875rem;
            color: #8a8a8a;
            text-transform: uppercase;
            letter-spacing:0.0312rem;
            margin-bottom:0.375rem;
        }
        .link-box .link-url {
            font-size:0.75rem;
            color: #6cb4f0;
            word-break: break-all;
            font-family: "Consolas", "Courier New", monospace;
        }
        .link-box .link-url a {
            color: #6cb4f0;
            text-decoration: none;
        }
        .link-box .link-url a:hover {
            text-decoration: underline;
        }
        /* DIVIDER */
        .divider {
            border: none;
            border-top: 0.0625rem solid rgba(255,255,255,0.06);
            margin:1.5rem 0 1.25rem;
        }
        /* FOOTER - Estilo Windows 11 */
        .footer {
            padding:1.25rem 2rem 1.5rem;
            background: #202020;
            border-top: 0.0625rem solid rgba(255,255,255,0.06);
        }
        .footer .security-badge {
            display: flex;
            align-items: center;
            gap:0.5rem;
            font-size:0.6875rem;
            color: #8a8a8a;
            margin-bottom:0.75rem;
        }
        .footer .security-badge i {
            color: #6cb4f0;
        }
        .footer .company-info {
            font-size:0.75rem;
            color: #6a6a6a;
            line-height:1.6;
        }
        .footer .company-info strong {
            color: #a0a0a0;
        }
        .footer .copyright {
            font-size:0.625rem;
            color: #5a5a5a;
            margin-top:0.625rem;
            padding-top:0.625rem;
            border-top: 0.0625rem solid rgba(255,255,255,0.04);
        }
        .footer .copyright i {
            margin-right:0.25rem;
        }
        /* Mensaje de seguridad */
        .security-note {
            background: rgba(255,170,0,0.06);
            border-radius: 0.375rem;
            padding:0.875rem 1.125rem;
            margin:1rem 0 0.25rem;
            border-left: 0.1875rem solid #ffa500;
        }
        .security-note .note-title {
            color: #ffa500;
            font-size:0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap:0.5rem;
        }
        .security-note .note-text {
            color: #b0b0b0;
            font-size:0.75rem;
            margin-top:0.25rem;
            line-height:1.5;
        }
        /* Estilos para iconos (fallback) */
        .icon-emoji {
            font-size:1rem;
        }
        /* Responsive */
        @media (max-width: 480px) {
            .body { padding:1.5rem 1.125rem; }
            .header { padding:1.25rem 1.125rem; }
            .footer { padding:1rem 1.125rem 1.25rem; }
            .header-title { font-size:1.0625rem; }
            .btn-reset { padding:0.75rem 1.75rem; font-size:0.8125rem; width:100%; text-align: center; }
            .info-card { padding:1rem; }
            .header-top { flex-wrap: wrap; gap:0.5rem; }
            .header-badge { font-size:0.5625rem; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- HEADER -->
        <div class="header">
            <div class="header-top">
                <div class="header-icon">
                    <div class="icon-circle">🔐</div>
                    <div class="app-name">SisGesNom <span>· Nóminas</span></div>
                </div>
                <div class="header-badge">🔒 Seguro</div>
            </div>
            <div class="header-title">Restablecimiento de contraseña</div>
            <div class="header-subtitle">Solicitud verificada correctamente</div>
        </div>
        
        <!-- BODY -->
        <div class="body">
            <p class="greeting">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            
            <p class="message-text">
                Hemos recibido una solicitud para restablecer la contraseña de su cuenta 
                <strong>' . htmlspecialchars($user['usuario']) . '</strong> en el sistema 
                <strong>' . htmlspecialchars($empresa) . '</strong>.
            </p>
            
            <!-- Info Card estilo Windows 11 -->
            <div class="info-card">
                <div class="card-row">
                    <span class="card-label">👤 Usuario</span>
                    <span class="card-value">' . htmlspecialchars($user['usuario']) . '</span>
                </div>
                <div class="card-row">
                    <span class="card-label">📧 Correo</span>
                    <span class="card-value">' . htmlspecialchars($email) . '</span>
                </div>
                <div class="card-row">
                    <span class="card-label">⏱️ Válido hasta</span>
                    <span class="card-value">30 minutos</span>
                </div>
            </div>
            
            <!-- Botón principal -->
            <div class="btn-container">
                <a href="' . htmlspecialchars($enlace) . '" class="btn-reset">
                    🔑 Restablecer contraseña
                </a>
            </div>
            
            <!-- Enlace alternativo -->
            <div class="link-box">
                <div class="link-label">📋 Enlace alternativo (copiar y pegar)</div>
                <div class="link-url">
                    <a href="' . htmlspecialchars($enlace) . '">' . htmlspecialchars($enlace) . '</a>
                </div>
            </div>
            
            <hr class="divider">
            
            <!-- Nota de seguridad -->
            <div class="security-note">
                <div class="note-title">
                    <span>🛡️</span> ¿No solicitó este cambio?
                </div>
                <div class="note-text">
                    Si usted no solicitó restablecer su contraseña, ignore este correo. 
                    Su contraseña actual seguirá siendo válida y no se realizará ningún cambio.
                </div>
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="footer">
            <div class="security-badge">
                <span>🔒</span> Este mensaje ha sido enviado desde un sistema seguro
            </div>
            <div class="company-info">
                <strong>' . htmlspecialchars($empresa) . '</strong><br>
                Sistema de Gestión de Nóminas y Empleados
            </div>
            <div class="copyright">
                <span>© ' . date('Y') . ' ' . htmlspecialchars($empresa) . ' · Todos los derechos reservados</span>
            </div>
        </div>
    </div>
</body>
</html>';

            // Versión en texto plano (mejorada)
            $textBody = "═══════════════════════════════════════════════════════\n"
                . "        RESTABLECIMIENTO DE CONTRASEÑA\n"
                . "═══════════════════════════════════════════════════════\n\n"
                . "Hola {$nombre},\n\n"
                . "Hemos recibido una solicitud para restablecer la contraseña\n"
                . "de su cuenta de usuario '{$user['usuario']}' en el sistema\n"
                . "'{$empresa}'.\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  DATOS DE LA SOLICITUD\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  Usuario    : {$user['usuario']}\n"
                . "  Correo     : {$email}\n"
                . "  Válido     : 30 minutos\n"
                . "───────────────────────────────────────────────────────────\n\n"
                . "Para restablecer su contraseña, abra el siguiente enlace:\n\n"
                . "  {$enlace}\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  ¿NO SOLICITÓ ESTE CAMBIO?\n"
                . "───────────────────────────────────────────────────────────\n\n"
                . "Si usted no solicitó restablecer su contraseña, ignore\n"
                . "este mensaje. Su contraseña actual seguirá siendo\n"
                . "válida y no se realizará ningún cambio.\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  {$empresa}\n"
                . "  Sistema de Gestión de Nóminas y Empleados\n"
                . "───────────────────────────────────────────────────────────\n"
                . "© " . date('Y') . " {$empresa} · Todos los derechos reservados\n";

            $envio = enviarCorreo($pdo, $email, $nombre, $subject, $htmlBody, $textBody);

            if (!$envio['success'] && $envio['error'] === 'mail_not_configured') {
                echo json_encode(['success' => true, 'action' => 'email_no_config', 'message' => 'El correo SMTP no está configurado. Se generó un enlace de recuperación de prueba.', 'link' => $enlace]);
                exit;
            }
            if (!$envio['success']) {
                echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo: ' . $envio['error']]);
                exit;
            }

            echo json_encode(['success' => true, 'action' => 'email_enviado']);
            exit;
        }

        // D) Restablecer contraseña con token
        if ($action === 'reset_password') {
            require_once 'config/mail.php';
            asegurarColumnasResetToken($pdo);
            asegurarTarifasNocturnidad($pdo);

            $token = trim($_POST['token'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($token)) { echo json_encode(['success' => false, 'message' => 'El enlace no es válido. Solicite uno nuevo.']); exit; }
            if (strlen($password) < 6) { echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']); exit; }

            $stmt = $pdo->prepare("SELECT id, usuario FROM clasif_usuarios WHERE reset_token = ? AND reset_expira > NOW() AND activo = 1 LIMIT 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'El enlace de recuperación no es válido o ha expirado. Solicite uno nuevo.']);
                exit;
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, reset_token = NULL, reset_expira = NULL WHERE id = ?");
            $stmtUpd->execute([$hashed, $user['id']]);

            echo json_encode(['success' => true, 'action' => 'reset_ok', 'usuario' => $user['usuario']]);
            exit;
        }

        // E) Verificar token de recuperación
        if ($action === 'verify_token') {
            $token = trim($_POST['token'] ?? '');
            
            if (empty($token)) {
                echo json_encode(['success' => false, 'message' => 'Token no proporcionado']);
                exit;
            }
            
            try {
                // Limpiar tokens expirados automáticamente
                $stmtClean = $pdo->prepare("
                    UPDATE clasif_usuarios 
                    SET reset_token = NULL, reset_expira = NULL 
                    WHERE reset_expira < NOW() AND reset_token IS NOT NULL
                ");
                $stmtClean->execute();
                
                // Verificar si el token existe
                $stmt = $pdo->prepare("
                    SELECT id, usuario, reset_expira, 
                           TIMESTAMPDIFF(SECOND, NOW(), reset_expira) as tiempo_restante 
                    FROM clasif_usuarios 
                    WHERE reset_token = ? AND activo = 1
                ");
                $stmt->execute([$token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    // El token no existe en la base de datos
                    echo json_encode([
                        'success' => false, 
                        'message' => 'El enlace de recuperación no es válido o ya ha sido utilizado.',
                        'codigo' => 'token_no_existe'
                    ]);
                    exit;
                }
                
                // Verificar si el token ha expirado
                if ($user['tiempo_restante'] <= 0) {
                    // Limpiar el token expirado
                    $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET reset_token = NULL, reset_expira = NULL WHERE id = ?");
                    $stmtUpd->execute([$user['id']]);
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => 'El enlace de recuperación ha expirado.',
                        'codigo' => 'token_expirado'
                    ]);
                    exit;
                }
                
                // Token válido
                echo json_encode([
                    'success' => true,
                    'message' => 'Token válido',
                    'tiempo_restante' => intval($user['tiempo_restante']),
                    'usuario' => $user['usuario']
                ]);
                exit;
                
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error al verificar el token: ' . $e->getMessage()]);
                exit;
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error servidor: ' . $e->getMessage()]);
        exit;
    }
} elseif ((isset($_GET['action']) || isset($_GET['ajax'])) && !$db_ok) {
    // Si la BD está desconectada y se hace una petición AJAX, responder con error
    header('Content-Type: application/json');
    echo json_encode(['db_ok' => false, 'error' => $db_error]);
    exit;
}

// ============================================================
// 4. PROCESAR LOGIN (POST) - SOLO SI LA BD ESTÁ CONECTADA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {
    $dato_entrada = trim($_POST['username'] ?? '');
    $password_input = trim($_POST['password'] ?? '');
    
    if (empty($dato_entrada) || empty($password_input)) {
        $error = 'complete_campos';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.codigo as rol_codigo, r.descripcion as rol_descripcion 
                FROM clasif_usuarios u 
                LEFT JOIN clasif_rol r ON u.rol_id = r.id 
                WHERE (u.usuario = ? OR u.email = ? OR u.no_ci = ?) AND u.activo = 1
            ");
            $stmt->execute([$dato_entrada, $dato_entrada, $dato_entrada]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($password_input, $user_data['password'])) {
                $enMantenimiento = false;
                try {
                    $stmtMaint = $pdo->prepare("SELECT modo_mantenimiento FROM configuracion_sistema LIMIT 1");
                    $stmtMaint->execute();
                    $maintResult = $stmtMaint->fetch(PDO::FETCH_ASSOC);
                    $enMantenimiento = ($maintResult && $maintResult['modo_mantenimiento'] >= 1);
                } catch (Exception $e) {}
                
                if ($enMantenimiento && $user_data['rol_id'] != 5) {
                    $error = "El sistema se encuentra en MANTENIMIENTO. Su rol no tiene permisos para acceder en este momento.";
                } else {
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['username'] = $user_data['usuario'];
                    $_SESSION['user_nombre'] = $user_data['nombre'] . ' ' . $user_data['apellidos'];
                    $_SESSION['user_ci'] = $user_data['no_ci'];
                    $_SESSION['user_email'] = $user_data['email'];
					
					$_SESSION['user_foto'] = $user_data['foto'] ?? '';
					$_SESSION['auth_provider'] = 'local';

					
                    $_SESSION['rol_id'] = $user_data['rol_id'];
                    $_SESSION['rol_codigo'] = $user_data['rol_codigo'];
                    $_SESSION['rol_descripcion'] = $user_data['rol_descripcion'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    try {
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $log_stmt = $pdo->prepare("
                            INSERT INTO sys_log_accesos (usuario_id, ip_address, user_agent, fecha_acceso) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $log_stmt->execute([$user_data['id'], $ip_address, $user_agent]);
                    } catch (PDOException $e) {}
                    
                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

                    if ($isAjax) {
                        $ultimo_acceso = null;
                        try {
                            $ul_stmt = $pdo->prepare("SELECT fecha_acceso FROM sys_log_accesos WHERE usuario_id = ? ORDER BY fecha_acceso DESC LIMIT 1 OFFSET 1");
                            $ul_stmt->execute([$user_data['id']]);
                            $ul_row = $ul_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($ul_row) $ultimo_acceso = $ul_row['fecha_acceso'];
                        } catch (PDOException $e) {}

                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => true,
                            'nombre' => trim($user_data['nombre'] . ' ' . $user_data['apellidos']),
                            'usuario' => $user_data['usuario'],
                            'ci' => $user_data['no_ci'] ?? '',
                            'rol' => $user_data['rol_descripcion'] ?? $user_data['rol_codigo'] ?? '',
                            'foto' => $user_data['foto'] ?? '',
                            'redirect' => 'dashboard.php',
                            'ultimo_acceso' => $ultimo_acceso
                        ]);
                        exit();
                    }

                    header('Location: dashboard.php');
                    exit();
                }
            } else {
                $error = 'credenciales_invalidas';
            }
        } catch (PDOException $e) {
            $error = 'error_sistema';
        }
    }

    if (!empty($error) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

// ============================================================
// 5. FUNCIONES AUXILIARES (solo para la vista)
// ============================================================
function obtenerConfiguracionWhatsApp($pdo) {
    if (!$pdo) return ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    try {
        $stmt = $pdo->prepare("SELECT whatsapp_numero, whatsapp_ON, restabpw FROM configuracion_sistema LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return $config ? [
            'whatsapp_numero' => $config['whatsapp_numero'] ?? null,
            'whatsapp_activo' => ($config['whatsapp_ON'] == 1),
            'restabpw' => $config['restabpw'] ?? 0
        ] : ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    } catch (Exception $e) {
        return ['whatsapp_numero' => null, 'whatsapp_activo' => false, 'restabpw' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($SITE_NAME); ?> · Acceso al Sistema</title>
    <link rel="icon" type="image/png" href="../images/favicons/nominas.ico">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <!-- Base responsive global (mobile-first) -->
    <link rel="stylesheet" href="assets/css/responsive.css">
    <script src="js/sweetalert211.js"></script>
    <!-- Google Identity Services (ventana emergente de Google) -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

<style>
/* ========== RESET Y ESTILOS BASE ========== */
* {
    margin:0;
    padding:0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height:100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding:1.25rem;
}

body::before {
    content: "";
    position: fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background-image: url('../images/sigesnom.png');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
    pointer-events: none;
    z-index: 0;
}

body::after {
    content: "";
    position: fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background-image: 
        repeating-linear-gradient(45deg, transparent, transparent 1.875rem, rgba(59, 130, 246, 0.08) 1.875rem, rgba(59, 130, 246, 0.08) 3.75rem),
        repeating-linear-gradient(-45deg, transparent, transparent 2.1875rem, rgba(139, 92, 246, 0.06) 2.1875rem, rgba(139, 92, 246, 0.06) 4.375rem);
    pointer-events: none;
    animation: waveMove 8s ease-in-out infinite;
    z-index: 0;
}

@keyframes waveMove {
    0%, 100% { background-position: 0 0, 0 0; }
    25% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
    50% { background-position: 2.5rem 2.5rem, -2.5rem -2.5rem; }
    75% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
}

/* ========== CONTENEDOR PRINCIPAL ========== */
.login-container {
    position: relative;
    z-index: 1;
    width:100%;
    max-width:42.5rem;  /* ← más ancho */
    animation: fadeInUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
}
.login-card {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(0.625rem);
    border-radius: 2rem;
    border: 0.0625rem solid rgba(59, 130, 246, 0.25);
    padding:2.5rem 3rem;  /* ← más espacio lateral */
    box-shadow: 0 1.5625rem 3.125rem -0.75rem rgba(0, 0, 0, 0.5);
    transition: padding 0.3s ease;
}

/* ===== BARRA DE TÍTULO (estilo ventana) ===== */
.login-card .titlebar-login {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: -2.5rem -3rem 1.5rem;
    padding: 0.625rem 1rem 0.625rem 1.25rem;
    border-bottom: 0.0625rem solid rgba(59, 130, 246, 0.15);
    background: rgba(30, 41, 59, 0.4);
    border-radius: 2rem 2rem 0 0;
    user-select: none;
    cursor: default;
    cursor: grab;
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.titlebar-login:hover {
    background: rgba(37, 99, 235, 0.16);
    border-bottom-color: rgba(59, 130, 246, 0.4);
}

.titlebar-login.dragging {
    cursor: grabbing;
    background: rgba(37, 99, 235, 0.28);
    border-bottom-color: rgba(59, 130, 246, 0.6);
    box-shadow: 0 0 0.75rem rgba(59, 130, 246, 0.25);
}

.login-card.dragging {
    transition: none !important;
}

.titlebar-login .titlebar-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #cbd5e1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: 0.02rem;
}

.titlebar-login .titlebar-buttons {
    display: flex;
    gap: 0.375rem;
}

.titlebar-login .titlebar-btn {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0.0625rem solid rgba(59, 130, 246, 0.2);
    border-radius: 0.5rem;
    background: rgba(59, 130, 246, 0.08);
    color: #cbd5e1;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.titlebar-login .titlebar-btn:hover {
    background: rgba(59, 130, 246, 0.2);
    color: #fff;
    transform: translateY(-0.0625rem);
}

.titlebar-login .titlebar-btn.tb-close:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.4);
    color: #fff;
}

.login-card.collapsed {
    padding-bottom: 1rem;
}

.login-card.collapsed .logo-area,
.login-card.collapsed form,
.login-card.collapsed .info-message {
    display: none;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(1.875rem); }
    to { opacity: 1; transform: translateY(0); }
}


/* ========== LOGO Y TÍTULOS ========== */
.logo-area {
    text-align: center;
    margin-bottom:2rem;
    display: flex;
    align-items: center;
    gap:1.25rem;
}

.logo-left {
    width:6.25rem;
    height:6.25rem;
    flex-shrink:0;
}

.logo-right {
    width:6.25rem;
    height:6.25rem;
    flex-shrink:0;
}

.logo-center {
    flex:1;
    min-width:0;
}

.logo-center h1 {
    margin:0;
}

.logo-icon2 {
    width:6.25rem;
    height:6.25rem;
    border-radius: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin:0 auto;
    box-shadow: 0 0.625rem 1.5625rem -0.3125rem rgba(37, 99, 235, 0.4);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.logo-icon2:hover {
    transform: scale(1.3);
}

.logo-area h1 {
    font-size:1.6rem;
    font-weight: 700;
    background: linear-gradient(145deg, #ffffff, #94a3f0);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    letter-spacing:-0.0312rem;
}

.logo-area .subtitle {
    font-size:0.8rem;
    color: #94a3b8;
    margin-top:0.5rem;
}

/* ========== INPUTS Y GRUPOS ========== */
.input-group {
    margin-bottom:1.5rem;
}

.input-group label {
    display: block;
    font-size:0.8rem;
    font-weight: 500;
    color: #cbd5e1;
    margin-bottom:0.5rem;
}

/* Contenedor general de los inputs con iconos */
.input-with-icon {
    position: relative;
    overflow: visible;  /* Para que el avatar pueda sobresalir */
}

/* Icono izquierdo (común para ambos campos) */
.input-with-icon i:first-child {
    position: absolute;
    left:1rem;
    top:50%;
    transform: translateY(-50%);
    color: #5a6a8a;
    font-size:1rem;
    z-index: 1;
    transition: color 0.2s ease;
}

/* Estilo base de los inputs */
.input-with-icon input {
    width:100%;
    padding:0.875rem 4.375rem 0.875rem 2.8125rem;  /* Padding derecho ampliado para el avatar grande */
    background: rgba(0, 0, 0, 0.4);
    border: 0.0625rem solid rgba(59, 130, 246, 0.2);
    border-radius: 1rem;
    font-size:0.95rem;
    color: #f1f5f9;
    transition: all 0.2s ease;
}

.input-with-icon input:focus {
    outline: none;
    border-color: #3b82f6;
    background: rgba(0, 0, 0, 0.6);
    box-shadow: 0 0 0 0.1875rem rgba(59, 130, 246, 0.1);
}

.input-with-icon input::placeholder {
    color: #5a6a8a;
}

/* ========== AVATAR DE USUARIO (GRANDE Y SOBRESALIENTE) ========== */
.user-avatar-container {
    position: absolute;
    right:-0.5rem;           /* Sobresale por la derecha */
    top:50%;
    transform: translateY(-50%);
    width:3.5rem;
    height:3.5rem;
    z-index: 2;
    pointer-events: none;
    filter: drop-shadow(0 0.25rem 0.5rem rgba(0, 0, 0, 0.3));
}

.user-avatar {
    width:100%;
    height:100%;
    border-radius: 50%;
    border: 0.1875rem solid #3b82f6;
    object-fit: cover;
    background: rgba(0, 0, 0, 0.6);
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 0 0 0.125rem rgba(59, 130, 246, 0.3), 0 0.5rem 1.25rem rgba(0, 0, 0, 0.3);
}

.user-avatar.visible {
    opacity: 1;
    transform: scale(1.02);
}

.user-avatar-container:hover .user-avatar {
    transform: scale(1.05);
    border-color: #60a5fa;
    box-shadow: 0 0 0 0.1875rem rgba(96, 165, 250, 0.5), 0 0.5rem 1.25rem rgba(0, 0, 0, 0.4);
}

/* ========== BOTÓN TOGGLE PASSWORD (OJITO) ========== */
/* Aseguramos que el contenedor del input de contraseña tenga position relative y overflow visible */
.input-group:has(#password) > div {
    position: relative !important;
}

/* Botón toggle con posicionamiento absoluto (centrado verticalmente) y animaciones */
.toggle-password-btn {
    position: absolute;
    right:0.75rem;
    top:50%;
    transform: translateY(-50%);
    width:2.125rem;
    height:2.125rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.15);
    border: none;
    cursor: pointer;
    padding:0;
    margin:0;
    transition: all 0.2s ease;
    z-index: 10;
}

.toggle-password-btn i {
    font-size:1rem;
    display: block;
    transition: all 0.2s ease;
    color: #60a5fa;
}

.toggle-password-btn:hover {
    background: rgba(59, 130, 246, 0.3);
    transform: translateY(-50%) scale(1.05);
}

.toggle-password-btn:hover i {
    color: #ffffff;
}

.toggle-password-btn:active {
    transform: translateY(-50%) scale(0.95);
}

/* ========== VALIDACIÓN DE CI (BORDES VERDE/ROJO) ========== */
.input-with-icon input.is-valid {
    border-color: var(--color-success) !important;
    box-shadow: 0 0 0.625rem rgba(34, 197, 94, 0.2);
}

.input-with-icon input.is-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0.625rem rgba(239, 68, 68, 0.2);
}

.ci-feedback {
    display: none;
    width:100%;
    text-align: left;
    margin-top:0.3125rem;
    font-size:0.75rem;
    padding-left:0.625rem;
}

.user-info-feedback {
    color: var(--color-success);
    font-size:0.78rem;
    font-weight: 600;
}

/* ========== CONTADOR DE CARACTERES ========== */
.char-counter {
    position: absolute;
    top:-1.125rem;
    right:0.3125rem;
    font-size:0.7rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
    z-index: 10;
    pointer-events: none;
    transition: all 0.3s ease;
    opacity: 0;
}

.char-counter.show {
    opacity: 1;
}

/* ========== BOTONES Y ENLACES ========== */
.button-group {
    display: flex;
    gap:0.75rem;
    margin-top:1rem;
}

.btn-login {
    flex: 2;
    position: relative;
    overflow: hidden;
    background: linear-gradient(95deg, #2563eb, #1d4ed8);
    border: none;
    padding:0.875rem 1.5rem;
    border-radius: 3.75rem;
    font-weight: 700;
    font-size:1rem;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap:0.75rem;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 0.375rem 1.25rem rgba(37, 99, 235, 0.35);
}

/* Luz que recorre el botón periódicamente (cada 3s) */
.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -80%;
    width: 40%;
    height: 100%;
    background: linear-gradient(105deg, transparent, rgba(255, 255, 255, 0.45), transparent);
    transform: skewX(-20deg);
    animation: btnLoginShine 3s linear infinite;
    z-index: 1;
    pointer-events: none;
}

.btn-login:hover {
    transform: translateY(-0.125rem);
    filter: brightness(1.08);
    box-shadow: 0 0.75rem 1.75rem rgba(37, 99, 235, 0.5);
}

.btn-login:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.btn-login:disabled::before {
    animation: none;
    opacity: 0;
}

@keyframes btnLoginShine {
    0%, 75% { left: -80%; }
    100% { left: 135%; }
}

.google-divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.75rem 0 1.25rem;
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.google-login-btn-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 0.25rem;
    min-height: 2.75rem;
}
.google-login-btn-container iframe {
    max-width: 100%;
    margin: 0 auto;
    border: none;
    transition: filter 0.25s ease, transform 0.25s ease;
}
.google-login-btn-container iframe:hover {
    filter: brightness(1.04);
    transform: scale(1.015);
}
.google-divider::before,
.google-divider::after {
    content: '';
    flex: 1;
    height: 0.0625rem;
    background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.3));
}
.google-divider::after {
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.3), transparent);
}

/* ========== BOTÓN GOOGLE PERSONALIZADO (Material Design 3 + borde degradado animado) ========== */
.btn-google-login {
    position: relative;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-content: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    background: #ffffff;
    border: none;
    color: #3c4043;
    font-family: 'Google Sans', Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    font-weight: 500;
    font-size: 0.9375rem;
    letter-spacing: 0.01em;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(60, 64, 67, 0.3), 0 1px 2px rgba(60, 64, 67, 0.15);
    transition: all 0.2s ease;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

/* Borde degradado animado (anillo glow) siempre visible */
.btn-google-login::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    padding: 0.125rem;
    background: linear-gradient(90deg, #4285F4, #EA4335, #FBBC05, #34A853, #4285F4);
    background-size: 300% 100%;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: googleRing 3s linear infinite;
    pointer-events: none;
    z-index: 2;
}

/* Resplandor exterior animado (glow) */
.btn-google-login::after {
    content: '';
    position: absolute;
    inset: -0.125rem;
    border-radius: calc(0.5rem + 0.125rem);
    background: linear-gradient(90deg, rgba(66, 133, 244, 0.55), rgba(234, 67, 53, 0.35), rgba(251, 188, 5, 0.35), rgba(52, 168, 83, 0.55), rgba(66, 133, 244, 0.55));
    background-size: 300% 100%;
    animation: googleRing 3s linear infinite;
    filter: blur(0.375rem);
    z-index: -1;
    pointer-events: none;
}

@keyframes googleRing {
    0% { background-position: 0% 50%; }
    100% { background-position: 300% 50%; }
}

@keyframes googleSpin {
    to { transform: rotate(360deg); }
}

/* ========== ENVUELTO DEL BOTÓN NATIVO GSI con anillo animado de colores Google ========== */
.gsi-wrap {
    position: relative;
    isolation: isolate;
    width: fit-content;
    max-width: 100%;
    margin: 0 auto 0.5rem;
    border-radius: 0.5625rem; /* 9px */
    padding: 0.1875rem;       /* grosor del anillo (3px) */
    overflow: hidden;
}
.gsi-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    padding: 0.1875rem; /* grosor del anillo (3px) */
    background: linear-gradient(90deg, #4285F4, #EA4335, #FBBC05, #34A853, #4285F4);
    background-size: 300% 100%;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: googleRing 3s linear infinite;
    pointer-events: none;
    z-index: 0;
}
.gsi-inner {
    position: relative;
    z-index: 1;
    width: fit-content;
    border-radius: 0.375rem; /* 6px */
    background: #202124;
    overflow: hidden;
}
.gsi-inner iframe {
    display: block;
    border: none;
}
.gsi-logo-tapete {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #202124;
    z-index: 2;
    pointer-events: none;
}
.gsi-logo-tapete svg {
    width: 1.25rem;
    height: 1.25rem;
}
.gsi-logo-tapete.gsi-derecha {
    left: auto;
    right: 0;
}

.btn-google-login svg,
.btn-google-login .google-btn-text {
    position: relative;
    z-index: 3;
    flex-shrink: 0;
}

.btn-google-login .google-btn-text {
    font-family: inherit;
    font-weight: 500;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

/* Perfil de Google dentro del botón (avatar + nombre + correo) */
.google-user-avatar {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.google-user-avatar-inicial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #1a73e8;
    color: #ffffff;
    font-size: 0.8125rem;
    font-weight: 600;
}
.google-user-bloque {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
    text-align: left;
}
.google-user-nombre {
    max-width: 100%;
    font-size: 0.9rem;
    font-weight: 500;
    color: #3c4043;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.google-user-email {
    max-width: 100%;
    font-size: 0.75rem;
    color: #5f6368;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.google-user-seleccion {
    flex-shrink: 0;
}

.btn-google-login:hover {
    background: #fafafa;
    box-shadow: 0 1px 3px rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
}

.btn-google-login:active {
    background: #f1f3f4;
    box-shadow: 0 1px 3px rgba(60, 64, 67, 0.3);
    transform: translateY(1px);
}

.btn-google-login.loading {
    pointer-events: none;
    opacity: 0.85;
}
.btn-google-login.loading svg {
    animation: googleSpin 1s linear infinite;
}
.btn-google-login.loading .google-btn-text {
    opacity: 0.85;
}

/* Deshabilitado (Google no configurado) */
.btn-google-login.disabled,
.btn-google-login:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    filter: grayscale(0.4);
    box-shadow: none;
}
.btn-google-login.disabled::before,
.btn-google-login.disabled::after {
    animation: none;
}

.btn-back-home {
    flex: 1;
    background: rgba(30, 41, 59, 0.6);
    border: 0.0625rem solid #475569;
    padding:0.875rem 1.25rem;
    border-radius: 3.75rem;
    font-weight: 500;
    font-size:0.9rem;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap:0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-back-home:hover {
    background: #334155;
    border-color: #ef4444;
    color: #fca5a5;
}

.forgot-link {
    display: block;
    text-align: right;
    margin-bottom:0.9375rem;
    color: #94a3b8;
    text-decoration: none;
    font-size:0.8rem;
    transition: all 0.2s;
}

.forgot-link:hover {
    color: #60a5fa;
    text-decoration: underline;
}

.forgot-link.disabled-link {
    pointer-events: none;
    opacity: 0.5;
}

.btn-login.btn-disabled-maint {
    background: #555 !important;
    color: #aaa !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

/* ========== PIE DE PÁGINA Y MENSAJES ========== */
.login-footer {
    margin-top:1.75rem;
    text-align: center;
    font-size:0.7rem;
    color: #FFFFFF;
}

.info-message {
    text-align: center;
    margin-top:1.25rem;
    padding:0.75rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 0.75rem;
    border-left: 0.1875rem solid #3b82f6;
}

.info-message i {
    color: #3b82f6;
    margin-right:0.5rem;
}

.info-message span {
    font-size:0.75rem;
    color: #94a3b8;
}

.info-message strong {
    color: #60a5fa;
}

.info-message a {
    color: #60a5fa;
    text-decoration: none;
    transition: all 0.2s ease;
}

.info-message a:hover {
    text-decoration: underline;
    color: #3b82f6;
}

/* ========== BADGES (PROGRAMADOR) ========== */
.programmer-badge {
    position: fixed;
    bottom:0.625rem;
    left:0.625rem;
    background: #dc3545;
    color: white;
    padding:0.3125rem 0.625rem;
    border-radius: 0.3125rem;
    font-size:0.75rem;
    z-index: 999999;
    box-shadow: 0 0 0.625rem rgba(220, 53, 69, 0.5);
}

.exit-badge {
    position: fixed;
    bottom:0.625rem;
    right:0.625rem;
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #000;
    padding:0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-size:0.75rem;
    font-weight: 600;
    z-index: 999998;
    box-shadow: 0 0.25rem 0.75rem rgba(255, 193, 7, 0.4);
    cursor: pointer;
    border: 0.0625rem solid rgba(0, 0, 0, 0.1);
}

/* ========== MODALES (RECUPERACIÓN, MANTENIMIENTO) ========== */
.modal-form-field {
    margin-bottom:0.9375rem;
    text-align: left;
}

.modal-form-field label {
    display: block;
    color: #cbd5e1;
    font-size:0.8rem;
    margin-bottom:0.3125rem;
    font-weight: 500;
}

.modal-form-field input, .modal-form-field select {
    width:100%;
    padding:0.625rem 0.75rem;
    background: rgba(0, 0, 0, 0.4);
    border: 0.0625rem solid rgba(59, 130, 246, 0.3);
    border-radius: 0.75rem;
    color: #f1f5f9;
    font-size:0.9rem;
    transition: all 0.2s ease;
}

.modal-form-field input:focus, .modal-form-field select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.125rem rgba(59, 130, 246, 0.2);
}

.modal-form-field input::placeholder {
    color: #5a6a8a;
}

.modal-form-field select option {
    background: #0f172a;
    color: #f1f5f9;
}

.row-2-columns {
    display: flex;
    gap:0.625rem;
    margin-bottom:0.9375rem;
}

.row-2-columns .modal-form-field {
    flex: 1;
    margin-bottom:0;
}

/* Modal mantenimiento estilo Win11 */
.win11-modal-overlay {
    position: fixed;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(0.5rem);
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
    border: 0.0625rem solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    width:90%;
    max-width:31.25rem;
    box-shadow: 0 1.25rem 3.125rem rgba(0,0,0,0.5);
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
    padding:0.9375rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.05);
    background: rgba(255, 255, 255, 0.02);
}

.modal-title-wrapper {
    display: flex;
    align-items: center;
    gap:0.75rem;
}

.modal-icon {
    width:2rem;
    height:2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size:1.2rem;
}

.modal-title {
    margin:0;
    font-size:1.1rem;
    font-weight: 600;
    color: #fff;
}

.win11-modal-body {
    padding:1.5625rem 1.5625rem;
    color: #ddd;
    font-size:0.95rem;
    line-height:1.6;
}

.win11-modal-footer {
    padding:0.9375rem 1.5625rem;
    background: rgba(0, 0, 0, 0.2);
    border-top: 0.0625rem solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: flex-end;
    gap:0.625rem;
}

.modal-btn {
    padding:0.5rem 1.25rem;
    border-radius: 0.25rem;
    border: 0.0625rem solid transparent;
    font-size:0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-btn-primary {
    background: #0078d4;
    color: #fff;
    border: 0.0625rem solid #0078d4;
}

.modal-btn-primary:hover {
    background: #006cc1;
}

.modal-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 0.0625rem solid rgba(255, 255, 255, 0.1);
}

.modal-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.maintenance-icon-pulse {
    animation: pulseMaint 2s infinite;
    color: #ffc107;
}

@keyframes pulseMaint {
    0% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
    50% { transform: scale(1.1); text-shadow: 0 0 1.25rem rgba(255, 193, 7, 1); }
    100% { transform: scale(1); text-shadow: 0 0 0 rgba(255, 193, 7, 0.7); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 480px) {
    .login-card {
        padding:1.875rem 1.5rem;
    }
    .logo-area {
        flex-direction: column;
        gap:1rem;
    }
    .logo-left {
        width:5.5rem;
        height:5.5rem;
    }
    .logo-right {
        width:5.5rem;
        height:5.5rem;
    }
    .logo-center h1 {
        font-size:1.3rem;
    }
    .button-group {
        flex-direction: column;
        gap:0.625rem;
    }
    .btn-login, .btn-back-home {
        width:100%;
        flex: auto;
    }
    /* Ajuste del avatar en móviles (un poco más pequeño) */
    .user-avatar-container {
        width:3rem;
        height:3rem;
        right:-0.25rem;
    }
    .input-with-icon input {
        padding-right:3.75rem;
    }
}
/* Estilos para el icono de la llave (mismo que el de usuario) */
.input-group:has(#password) .input-with-icon i:first-child,
.input-group:has(#password) > div > i:first-child {
    color: #5a6a8a;
    transition: color 0.2s ease;
    font-size:1rem;
}

/* Botón toggle - animaciones */
.toggle-password-btn {
    transition: all 0.2s ease;
}

.toggle-password-btn i {
    transition: all 0.2s ease;
    font-size:1rem;
    color: #60a5fa;
}

.toggle-password-btn:hover {
    background: rgba(59, 130, 246, 0.3);
    transform: translateY(-50%) scale(1.05);
}

.toggle-password-btn:hover i {
    color: #ffffff;
}

.toggle-password-btn:active {
    transform: translateY(-50%) scale(0.95);
}
/* ========== FILA DE DOS COLUMNAS ========== */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap:1.25rem;
    margin-bottom:0.625rem;
}

/* En móviles, vuelven a una columna */
@media (max-width: 480px) {
    .form-row {
        grid-template-columns: 1fr;
        gap:1rem;
    }
}

/* Pequeño ajuste para el avatar en móviles (opcional) */
@media (max-width: 480px) {
    .user-avatar-container {
        width:3rem;
        height:3rem;
        right:-0.25rem;
    }
    .input-with-icon input {
        padding-right:3.75rem;
    }
}
/* Enlaces legales en el footer */
.login-footer-links {
    display: flex;
    justify-content: center;
    gap:1rem;
    flex-wrap: wrap;
    margin-top:0.75rem;
    padding-top:0.5rem;
    border-top: 0.0625rem solid rgba(255, 255, 255, 0.08);
}

.login-footer-links a {
    color: #94a3b8;
    text-decoration: none;
    font-size:0.7rem;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    gap:0.375rem;
}

.login-footer-links a:hover {
    color: #60a5fa;
    text-decoration: underline;
}
        /* Estilo para el logo de copyright */
        .copyright-logo {
            display: inline-flex;
            align-items: center;
            gap:0.375rem;
            vertical-align: middle;
        }

        .copyright-logo img {
            width:1.25rem;
            height:1.25rem;
            vertical-align: middle;
        }
        .unicorn-icon {
            width:1.875rem;
            height:auto;
            filter: brightness(0) invert(1);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.3); }
        }
        @keyframes moveRight {
            0% { transform: translateX(0); }
            100% { transform: translateX(0.375rem); }
        }
        .swal2-welcome-progress {
            background-color: #60a5fa !important;
            height:0.25rem !important;
        }
        .anim-credenciales {
            font-size:1.5rem;
            text-align: center;
            animation: credColorPulse 1s ease-in-out infinite;
        }
        @keyframes credColorPulse {
            0%, 100% { color: #ef4444; }
            50% { color: #facc15; }
        }
</style>

</head>
<body>

<div class="login-container" id="loginWrap">
    <div class="login-card" id="loginCard">
        <div class="titlebar-login" id="loginTitlebar">
            <div class="titlebar-title">
                <i class="fas fa-sign-in-alt"></i> Inicio de Sesión en SigesNom®
            </div>
            <div class="titlebar-buttons">
                <button type="button" class="titlebar-btn tb-collapse" id="btnCollapseLogin" title="Colapsar / Expandir" data-tooltip="Colapsar / Expandir" data-tooltip-theme="info">
                    <i class="fas fa-chevron-down" id="collapseIcon"></i>
                </button>
                <button type="button" class="titlebar-btn tb-close" id="btnCloseLogin" title="Salir" data-tooltip="Salir" data-tooltip-theme="danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="logo-area">
            <div class="logo-left">
                <div class="logo-icon2">
                    <img src="../images/sigesnom.png" alt="Sisgesnom Logo"
                         style="width:100%; height:100%; object-fit: contain; border-radius: inherit;"
                         onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon\'></i>';">
                </div>
            </div>
            <div class="logo-center">
                <h1>SISGESNOM · <?php echo $SITE_VERSION; ?></h1>
                <div class="subtitle">Sistema de Gestión de Nóminas y Trabajadores</div>
                <h3 style="color:white;"><?php echo htmlspecialchars($COMPANY_NAME); ?></h3>
                <h4 class="anim-credenciales" style="margin:0.625rem 0 0.3125rem;">TECLEE SUS CREDENCIALES DE ACCESO</h4>
            </div>
            <div class="logo-right">
                <div class="logo-icon2">
                    <img src="../images/LogoTN.png" alt="TransNuBeT Logo"
                         style="width:100%; height:100%; object-fit: contain; border-radius: inherit;"
                         onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon\'></i>';">
                </div>
            </div>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="form-row">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Usuario / Email / CI</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope" id="dynamicIcon"></i>
                        <input type="text" name="username" id="username" placeholder="Ingrese su credencial" 
                               value="<?php echo htmlspecialchars($username); ?>" autocomplete="off" autofocus maxlength="100" <?php if (!$db_ok) echo 'disabled'; ?>>
                        <div class="user-avatar-container">
                            <img id="userAvatar" class="user-avatar" src="" alt="Foto de usuario">
                        </div>
                        <div class="char-counter" id="charCounter">0/100</div>
                    </div>
                    <div id="ciFeedback" class="ci-feedback"></div>
                    <div id="userInfoFeedback" class="ci-feedback user-info-feedback"></div>
                </div>

                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Contraseña</label>
                    <div style="position: relative;">
                        <i class="fas fa-key" style="position: absolute; left:1rem; top:50%; transform: translateY(-50%); z-index: 1;"></i>
                        <input type="password" name="password" id="password" placeholder="Ingrese su contraseña" autocomplete="off"
                               style="width:100%; padding:0.875rem 3.125rem 0.875rem 2.8125rem; background: rgba(0,0,0,0.4); border: 0.0625rem solid rgba(59,130,246,0.2); border-radius: 1rem; color: #f1f5f9; font-size:0.95rem;" <?php if (!$db_ok) echo 'disabled'; ?>>
                        <button type="button" class="toggle-password-btn" id="togglePassword" 
                                style="position: absolute; right:0.75rem; top:50%; transform: translateY(-50%); width:2.125rem; height:2.125rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59,130,246,0.15); border: none; cursor: pointer; padding:0; margin:0;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <a href="#" class="forgot-link" id="forgotPasswordLink" <?php if (!$db_ok) echo 'style="pointer-events: none; opacity: 0.5;"'; ?>>
                <i class="fas fa-key me-1"></i> ¿Olvidaste la contraseña?
            </a>

            <div class="button-group">
                <button type="submit" class="btn-login" id="btnLogin" <?php if (!$db_ok) echo 'disabled'; ?>>
                    <i class="fas fa-arrow-right-to-bracket"></i> Iniciar Sesión
                </button>
                <button type="button" class="btn-back-home" id="backHomeBtn">
                    <i class="fas fa-arrow-left"></i> Regresar
                </button>
            </div>

            <?php if ($db_ok): ?>
            <div class="google-divider">
                <span>o continúa con</span>
            </div>
            <div id="googleLoginBtn" class="google-login-btn-container">
                <?php if (!$google_configurado): ?>
                <div class="btn-google-login disabled" style="pointer-events: none; opacity: 0.5;" title="Login con Google no configurado por el administrador">
                    <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    <span class="google-btn-text">Continuar con Google</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>

        <div class="info-message">
            <i class="fas fa-info-circle"></i>
            <span>¿Necesita una cuenta? Solicítela por correo a <strong><a href="#" id="solicitarCuentaLink"><?php echo htmlspecialchars($email_soporte, ENT_QUOTES, 'UTF-8'); ?></a></strong></span>
        </div>

        <div class="login-footer">
            <p><i class="fas fa-shield-alt"></i> Sistema seguro · Datos encriptados · Listos para: <span style="font-weight: bold;"><?php echo htmlspecialchars($COMPANY_NAME); ?></span></p>
            <div class="login-footer-links">
                <a href="../terminos.php"><i class="fas fa-file-contract"></i> Términos</a>
                <a href="../privacidad.php"><i class="fas fa-lock"></i> Privacidad</a>
                <a href="../soporte.php"><i class="fas fa-headset"></i> Soporte</a>
				<a href="../contacto.php"><i class="fas fa-envelope"></i> Contáctenos</a>
            </div>
    <!-- Línea de copyright -->
    <div style="text-align: center; margin-top:1rem; padding:0.75rem; font-size:0.7rem; color: #5a6a8a;">
        <div class="copyright-logo">
            <img src="../images/Unicorn.png" alt="Unicornio" class="unicorn-icon">
            <span style="font-weight:bold;font-size:0.875rem;color:white;">Copyright © <?php echo date('Y'); ?> UnicornioSoftware° - Kaky&reg;. Todos los derechos reservados.</span>
        </div>
    </div>
        </div>
    </div>
</div>

<!-- MODAL MANTENIMIENTO -->
<div class="win11-modal-overlay" id="maintenanceModal">
  <div class="win11-modal" style="border: 0.0625rem solid #ffc107; box-shadow: 0 0 2.5rem rgba(255, 193, 7, 0.2);">
    <div class="win11-modal-header" style="border-bottom: 0.0625rem solid rgba(255, 193, 7, 0.3);">
      <div class="modal-title-wrapper">
        <div class="modal-icon maintenance-icon-pulse">
          <i class="fa-solid fa-helmet-safety"></i>
        </div>
        <h3 class="modal-title" style="color: #ffc107;">🛠️ Modo Mantenimiento Activo</h3>
      </div>
    </div>
    <div class="win11-modal-body">
      <p style="font-size:1.1em; color: #fff; margin-bottom:1.25rem; text-align: center;">
        <strong><?php echo htmlspecialchars($COMPANY_NAME); ?></strong> se encuentra actualmente en modo de mantenimiento.
      </p>
      <div style="background: rgba(255, 193, 7, 0.05); border-radius: 0.5rem; padding:0.9375rem; border: 0.0625rem dashed rgba(255, 193, 7, 0.3);">
        <p style="color: #ffc107; text-align: center; margin-bottom:0;">Por favor, intente más tarde.</p>
      </div>
    </div>
    <div class="win11-modal-footer">
      <button class="modal-btn modal-btn-secondary" onclick="closeMaintenanceModal()">
        <i class="fa-solid fa-eye me-1"></i> Solo mirar
      </button>
      <button class="modal-btn modal-btn-primary" onclick="window.location.href='?access_bypass=true'">
        <i class="fa-solid fa-unlock-keyhole me-1"></i> Acceso Programador
      </button>
      <button id="btn-recheck-maint" class="modal-btn modal-btn-primary" onclick="recheckMaintenanceStatus()" 
              style="background-color: #ffc107; color: #000; border: none;">
        <i class="fa-solid fa-rotate-right me-2"></i> Comprobar
      </button>
    </div>
  </div>
</div>

<script>
// ==========================================
// VARIABLES DE ESTADO DEL SERVIDOR (PHP)
// ==========================================
const dbOk = <?php echo json_encode($db_ok); ?>;
const dbErrorMsg = <?php echo json_encode($db_error); ?>;
const companyName = '<?php echo addslashes($COMPANY_NAME); ?>';
const restabpw = <?php echo intval($restabpw); ?>;
const mailConfigurado = <?php echo $mail_configurado ? 'true' : 'false'; ?>;
const googleClientId = <?php echo json_encode($google_client_id); ?>;
const googleConfigurado = <?php echo $google_configurado ? 'true' : 'false'; ?>;

// ==========================================
// LOGIN CON GOOGLE
// Principal: botón nativo de Google (GSI) si carga.
// Respaldo: botón personalizado (Material Design 3) con foto, nombre y correo
// del último usuario (guardados localmente); abre Google en ventana emergente.
// ==========================================
const GOOGLE_PERFIL_STORAGE = 'transnubet_googlebtn_perfil';
const GOOGLE_ONETAP_SKIP = 'transnubet_google_onetap_skip';
const GOOGLE_ICONO_SVG = '<svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
let googleGisInicializado = false;

function googleOnetapMarcar(gastado) {
    try {
        if (gastado) {
            localStorage.setItem(GOOGLE_ONETAP_SKIP, String(Date.now()));
        } else {
            localStorage.removeItem(GOOGLE_ONETAP_SKIP);
        }
    } catch (err) {}
}

function googleOnetapScto() {
    try {
        const v = parseInt(localStorage.getItem(GOOGLE_ONETAP_SKIP) || '0', 10);
        return v > 0 && (Date.now() - v) < 24 * 60 * 60 * 1000;
    } catch (err) {
        return false;
    }
}

function googleMoment(notificacion) {
    try {
        const mostrada = typeof notificacion.isDisplayed === 'function' && notificacion.isDisplayed();
        const omitida = typeof notificacion.isSkippedMoment === 'function' && notificacion.isSkippedMoment();
        const descartada = typeof notificacion.isDismissedMoment === 'function' && notificacion.isDismissedMoment();
        if (!mostrada && (omitida || descartada)) googleOnetapMarcar(true);
    } catch (err) {}
}

function escHtmlValor(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function mostrarErrorGoogle(titulo, mensaje, esAdvertencia) {
    Swal.fire({
        title: '<i class="' + (esAdvertencia ? 'fas fa-exclamation-triangle' : 'fas fa-times-circle') + '" style="color: ' + (esAdvertencia ? '#f59e0b' : '#ef4444') + '; font-size:2rem;"></i><br>' + titulo,
        html: '<p style="font-size:1rem;">' + mensaje + '</p>',
        icon: esAdvertencia ? 'warning' : 'error',
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: esAdvertencia ? '#f59e0b' : '#ef4444',
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        allowOutsideClick: false
    });
}

function prepararGoogle() {
    if (!googleConfigurado || !dbOk) return;
    // Siempre visible: botón personalizado al instante (con perfil si existe)
    mostrarBotonGooglePersonalizado();
    if (window.google && google.accounts && google.accounts.id) {
        inicializarBotonoGoogle();
        return;
    }
    // Si GSI llega después, se sustituye por el nativo con el anillo animado
    let esperado = 0;
    const timer = setInterval(function() {
        esperado += 100;
        if (window.google && google.accounts && google.accounts.id) {
            clearInterval(timer);
            inicializarBotonoGoogle();
        } else if (esperado > 20000) {
            clearInterval(timer);
        }
    }, 100);
}

function inicializarBotonoGoogle() {
    if (googleGisInicializado || !googleConfigurado || !dbOk) return;
    const contenedor = document.getElementById('googleLoginBtn');
    if (!contenedor) return;
    if (!window.google || !google.accounts || !google.accounts.id) return;
    contenedor.innerHTML = '<div id="gsiWrap" class="gsi-wrap"><div class="gsi-inner" id="gsiInner"></div></div>';
    const lupaInner = contenedor.querySelector('.gsi-inner');
    if (!lupaInner) return;
    googleGisInicializado = true;
    let hintEmail = '';
    try {
        const p = JSON.parse(localStorage.getItem(GOOGLE_PERFIL_STORAGE) || 'null');
        if (p && p.email) hintEmail = p.email;
    } catch (err) {}
    google.accounts.id.initialize({
        client_id: googleClientId,
        callback: handleGoogleCredential,
        auto_select: false,
        cancel_on_tap_outside: true,
        prompt_parent_id: 'gsiWrap',
        login_hint: hintEmail || undefined,
        itp_support: true,
        moment_callback: googleMoment
    });
    google.accounts.id.renderButton(lupaInner, {
        type: 'standard',
        theme: 'filled_black',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular',
        logo_alignment: 'left',
        width: 320
    });
    const derecha = document.createElement('span');
    derecha.className = 'gsi-logo-tapete gsi-derecha';
    derecha.innerHTML = GOOGLE_ICONO_SVG;
    lupaInner.appendChild(derecha);
    // Ajustar wrapper y núcleo al tamaño real del botón nativo de Google
    let intentosAjuste = 0;
    const ajustarAlNativo = function() {
        const iframe = lupaInner.querySelector('iframe');
        if (iframe && iframe.offsetWidth > 0 && iframe.offsetHeight > 0) {
            lupaInner.style.width = iframe.offsetWidth + 'px';
            lupaInner.style.height = iframe.offsetHeight + 'px';
            const tapetes = lupaInner.querySelectorAll('.gsi-logo-tapete');
            for (let i = 0; i < tapetes.length; i++) tapetes[i].style.width = iframe.offsetHeight + 'px';
        } else if (intentosAjuste < 30) {
            intentosAjuste += 1;
            setTimeout(ajustarAlNativo, 100);
        }
    };
    ajustarAlNativo();
    if (!googleOnetapScto()) {
        setTimeout(function() {
            if (window.google && google.accounts && google.accounts.id) google.accounts.id.prompt();
        }, 800);
    }
}

function handleGoogleCredential(respuesta) {
    if (!respuesta || !respuesta.credential) return;
    const contenedor = document.getElementById('googleLoginBtn');
    if (contenedor) contenedor.style.opacity = '0.6';
    fetch('login.php?action=google_token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'credential=' + encodeURIComponent(respuesta.credential)
    })
    .then(function(r) { return r.json().catch(function() { return { ok: false, mensaje: 'El servidor devolvió una respuesta inválida.' }; }); })
    .then(function(data) {
        if (contenedor) contenedor.style.opacity = '1';
        if (data && data.ok) {
            if (data.email || data.nombre) {
                try {
                    localStorage.setItem(GOOGLE_PERFIL_STORAGE, JSON.stringify({
                        nombre: data.nombre || '',
                        email: data.email || '',
                        foto: data.foto || ''
                    }));
                } catch (err) {}
            }
            googleOnetapMarcar(false);
            mostrarBienvenida(data);
        } else {
            mostrarErrorGoogle('Acceso no verificado', (data && data.mensaje) || 'No se pudo verificar el acceso con Google. Inténtelo de nuevo.', false);
        }
    })
    .catch(function() {
        if (contenedor) contenedor.style.opacity = '1';
        mostrarErrorGoogle('Error de conexión', 'No se pudo completar el acceso con Google.', false);
    });
}

function mostrarBotonGooglePersonalizado() {
    const contenedor = document.getElementById('googleLoginBtn');
    if (!contenedor) return;

    let perfil = null;
    try { perfil = JSON.parse(localStorage.getItem(GOOGLE_PERFIL_STORAGE) || 'null'); } catch (err) { perfil = null; }

    const iconoSVG = GOOGLE_ICONO_SVG;

    let interno;
    if (perfil && perfil.email) {
        const nombre = perfil.nombre || 'Usuario de Google';
        const email = perfil.email;
        const foto = perfil.foto || '';
        const avatarHtml = foto
            ? '<img src="' + escHtmlValor(foto) + '" class="google-user-avatar" alt="" onerror="this.style.display=`none`;">'
            : '<span class="google-user-avatar google-user-avatar-inicial">' + escHtmlValor(nombre.charAt(0).toUpperCase()) + '</span>';
        interno = iconoSVG
            + '<span class="google-user-seleccion">' + avatarHtml + '</span>'
            + '<span class="google-user-bloque">'
            + '<span class="google-user-nombre">' + escHtmlValor(nombre) + '</span>'
            + (email ? '<span class="google-user-email">' + escHtmlValor(email) + '</span>' : '')
            + '</span>';
    } else {
        interno = iconoSVG + '<span class="google-btn-text">Continuar con Google</span>';
    }

    contenedor.innerHTML = '<button type="button" id="googleFallbackBtn" class="btn-google-login" style="display:inline-flex; width:100%; align-items:center; justify-content:center; gap:0.75rem; cursor:pointer;">' + interno + '</button>';
    const btn = document.getElementById('googleFallbackBtn');
    if (btn) btn.addEventListener('click', abrirPopupGoogle);
}

function abrirPopupGoogle() {
    const btn = document.getElementById('googleFallbackBtn');
    if (btn) {
        btn.classList.add('loading');
        btn.disabled = true;
    }
    const ancho = 520, alto = 620;
    const izq = Math.max(0, Math.round((window.screen.width - ancho) / 2));
    const arr = Math.max(0, Math.round((window.screen.height - alto) / 2));
    const win = window.open('login.php?action=google&popup=1', 'googleAuthPopup', 'width=' + ancho + ',height=' + alto + ',left=' + izq + ',top=' + arr);
    if (!win) {
        if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
        mostrarErrorGoogle('Bloqueador de Ventanas', 'Permita las ventanas emergentes para continuar con Google, o revise su navegador.', true);
        return;
    }
    // Si el usuario cierra la ventana de Google (o cancela), habilitar de nuevo el botón
    const cierrePopup = setInterval(function() {
        if (win && win.closed) {
            clearInterval(cierrePopup);
            if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
        }
    }, 400);
    window.addEventListener('message', function listener(e) {
        if (e.data && e.data.type === 'google_auth_ok') {
            clearInterval(cierrePopup);
            window.removeEventListener('message', listener);
            const perfil = e.data.perfil || {};
            if (perfil.email || perfil.nombre) {
                try { localStorage.setItem(GOOGLE_PERFIL_STORAGE, JSON.stringify(perfil)); } catch (err) {}
            }
            googleOnetapMarcar(false);
            mostrarBienvenida(perfil);
        }
    });
}

if (dbOk && googleConfigurado) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararGoogle);
    } else {
        prepararGoogle();
    }
}

// ==========================================
// SI LA BD ESTÁ DESCONECTADA, MOSTRAR ALERTA CON BOTÓN COMPROBAR
// ==========================================
if (!dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '⚠️ Error de conexión con la base de datos',
            html: `<div style="text-align: left;">
                        <p><strong>MySQL:</strong> <span style="color: #ef4444;">✗ Desconectado</span></p>
                        <p><strong>Error:</strong> <span style="color: #fca5a5;">${dbErrorMsg || 'No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión'}</span></p>
                        <hr style="margin:0.75rem 0; border-color: rgba(255,255,255,0.1);">
                        <p><i class="fas fa-exclamation-triangle"></i> El sistema de nóminas no está disponible temporalmente.</p>
                        <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                        ✉️ <strong>Email:</strong> <a href="mailto:<?php echo $email_soporte_js; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($email_soporte, ENT_QUOTES, 'UTF-8'); ?></a></p>
                    </div>`,
            icon: 'error',
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#ef4444',
            confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
            showCancelButton: true,
            cancelButtonColor: '#3b82f6',
            cancelButtonText: '<i class="fas fa-sync-alt"></i> Comprobar estado',
            allowOutsideClick: false
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                // Se presionó "Comprobar estado"
                checkDatabaseStatus();
            }
        });
        // Deshabilitar campos y botones manualmente
        document.getElementById('username').disabled = true;
        document.getElementById('password').disabled = true;
        document.getElementById('btnLogin').disabled = true;
        document.getElementById('forgotPasswordLink').style.pointerEvents = 'none';
        document.getElementById('forgotPasswordLink').style.opacity = '0.5';
    });
}

// ==========================================
// FUNCIONES AUXILIARES (solo se ejecutan si dbOk)
// ==========================================
let debounceTimer;
const MAX_LENGTH = 100;

function updateInputIcon(value) {
    const dynamicIcon = document.getElementById('dynamicIcon');
    let newIconClass = 'fa-user';
    value = value.trim();
    if (value.length === 0) newIconClass = 'fa-user';
    else if (value.includes('@')) newIconClass = 'fa-envelope';
    else if (/^\d{11}$/.test(value)) newIconClass = 'fa-address-card';
    else if (/^\d+$/.test(value)) newIconClass = 'fa-hashtag';
    dynamicIcon.className = 'fas ' + newIconClass;
}

function mostrarBienvenida(datos) {
    const nombre = datos.nombre || 'Usuario';
    const ci = datos.ci || '';
    const rol = datos.rol || 'Usuario';
    const foto = datos.foto || '';
    const redirect = datos.redirect || 'dashboard.php';
    const ultimoAcceso = datos.ultimo_acceso;

    let digitoGenero = 99;
    if (ci && ci.length >= 10 && /^\d{10,}$/.test(ci)) {
        digitoGenero = parseInt(ci.charAt(9));
    }
    const esFemenino = digitoGenero % 2 !== 0;
    const saludo = esFemenino ? 'Bienvenida de nuevo' : 'Bienvenido de nuevo';
    const iconoGenero = esFemenino ? '👩‍💼' : '👨‍💼';

    const ahora = new Date();
    const horaActual = ahora.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const fechaActual = ahora.toLocaleDateString('es-VE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    let ultimoAccesoHtml = '';
    if (ultimoAcceso) {
        const fechaUl = new Date(ultimoAcceso);
        const diffMs = ahora - fechaUl;
        const diffMin = Math.floor(diffMs / 60000);
        const diffHrs = Math.floor(diffMs / 3600000);
        const diffDias = Math.floor(diffMs / 86400000);
        let tiempoTranscurrido = '';
        let colorTiempo = '';
        if (diffMin < 1) { tiempoTranscurrido = 'Hace un momento'; colorTiempo = '#22c55e'; }
        else if (diffMin < 60) { tiempoTranscurrido = 'Hace ' + diffMin + ' minuto(s)'; colorTiempo = '#22c55e'; }
        else if (diffHrs < 24) { tiempoTranscurrido = 'Hace ' + diffHrs + ' hora(s)'; colorTiempo = '#f59e0b'; }
        else { tiempoTranscurrido = 'Hace ' + diffDias + ' día(s)'; colorTiempo = '#ef4444'; }
        const fechaFormateada = fechaUl.toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + fechaUl.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' });
        ultimoAccesoHtml = '<div style="font-size:0.85rem; color: ' + colorTiempo + '; margin-top:0.9375rem; padding-top:0.625rem; border-top: 0.0625rem solid rgba(255,255,255,0.1);"><i class="fa-solid fa-clock-rotate-left" style="margin-right:0.3125rem;"></i><span style="font-weight: 500;">Último acceso:</span> <span style="font-weight: 700;">' + tiempoTranscurrido + '</span> — ' + fechaFormateada + '</div>';
    }

    Swal.fire({
        title: '<div style="font-size:1.6rem; margin-bottom:0.75rem; font-weight: 800; color: #60a5fa;">' + saludo + ' ' + iconoGenero + '</div>',
        html: `
            <div style="display: flex; flex-direction: row; align-items: center; gap:0.9375rem; margin-bottom:1.25rem; background: rgba(255,255,255,0.05); padding:0.9375rem; border-radius: 0.75rem;">
                <div style="flex-shrink: 0;">
                    <img src="${foto}" 
                         style="width:5.3125rem; height:5.3125rem; border-radius: 50%; border: 0.1875rem solid #60a5fa; object-fit: cover;" 
                         alt="Foto de perfil"
                         onerror="this.src='data:image/svg+xml;base64,<?php echo base64_encode(file_get_contents(__DIR__ . '/assets/default-avatar.png') ?: ''); ?>'">
                </div>
                <div style="flex-grow: 1; text-align: left;">
                    <div style="font-size:1.3rem; margin-bottom:0.5rem; color: #60a5fa; font-weight: 600;">
                        <i class="fa-solid fa-user-check" style="margin-right:0.5rem;"></i>${nombre}
                    </div>
                    <div style="margin-bottom:0.3125rem;">
                        <span style="background: rgba(139, 92, 246, 0.2); color: #b38cff; padding:0.25rem 0.75rem; border-radius: 0.9375rem; font-size:0.85rem; font-weight: 600;">
                            <i class="fa-solid fa-shield-halved" style="margin-right:0.3125rem;"></i>${rol}
                        </span>
                    </div>
                    <div style="display: flex; gap:0.9375rem; font-size:0.82rem; color: #94a3b8;">
                        <span><i class="fa-regular fa-clock" style="margin-right:0.3125rem;"></i>${horaActual}</span>
                        <span><i class="fa-regular fa-calendar" style="margin-right:0.3125rem;"></i>${fechaActual}</span>
                    </div>
                </div>
            </div>
            
            <div style="background: rgba(34, 197, 94, 0.1); padding:0.875rem; border-radius: 0.625rem; margin:0.75rem 0; border: 0.0625rem solid rgba(34, 197, 94, 0.2);">
                <div style="display: flex; align-items: center; margin-bottom:0.5rem;">
                    <div style="width:0.625rem; height:0.625rem; background: #22c55e; border-radius: 50%; margin-right:0.625rem; animation: pulse 1.5s infinite;"></div>
                    <span style="font-weight: 600; color: #e2e8f0;">Sesión iniciada correctamente</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <i class="fa-solid fa-arrow-right" style="color: #60a5fa; margin-right:0.625rem; animation: moveRight 1s infinite alternate;"></i>
                    <span style="color: #94a3b8;">Redirigiendo al sistema...</span>
                </div>
            </div>

            ${ultimoAccesoHtml}
        `,
        background: 'rgba(10, 20, 30, 0.95)',
        backdrop: 'rgba(0,0,0,0.5)',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: '30rem',
        timer: 5000,
        timerProgressBar: true,
        customClass: { timerProgressBar: 'swal2-welcome-progress' },
        didOpen: () => {
            const bar = Swal.getTimerProgressBar();
            if (bar) { bar.style.backgroundColor = '#60a5fa'; bar.style.height = '0.25rem'; }
        },
        willClose: () => {
            window.location.href = redirect;
        }
    });
}

function cargarImagenUsuario(usuario) {
    const userInfo = document.getElementById('userInfoFeedback');
    if (!usuario.trim()) {
        document.getElementById('userAvatar').classList.remove('visible');
        if (userInfo) userInfo.style.display = 'none';
        return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetch(`login.php?ajax=get_imagen&usuario=${encodeURIComponent(usuario)}`)
            .then(response => response.json())
            .then(data => {
                // Si el campo cambió desde que se hizo la petición, descartar la respuesta
                const inputActual = document.getElementById('username');
                if (!inputActual || inputActual.value.trim() !== usuario.trim()) return;
                const userAvatar = document.getElementById('userAvatar');
                if (data.success) {
                    userAvatar.src = data.imagen;
                    if (userInfo) {
                        if (data.nombre_completo) {
                            let html = '<div style="display: flex; flex-direction: column; gap:0.375rem; line-height:1.4;">';
                            html += '<div style="display: flex; align-items: center; gap:0.5rem;"><span style="width:0.5rem; height:0.5rem; min-width:0.5rem; background: #22c55e; border-radius: 50%; display: inline-block;"></span><span style="font-size:0.82rem;"><strong style="color: #94a3b8; font-weight: 600;">Nombre:</strong> <span style="color: #22c55e; font-weight: 700;">' + data.nombre_completo + '</span></span></div>';
                            html += '<div style="display: flex; align-items: center; gap:0.5rem;"><span style="width:0.5rem; height:0.5rem; min-width:0.5rem; background: #60a5fa; border-radius: 50%; display: inline-block;"></span><span style="font-size:0.82rem;"><strong style="color: #94a3b8; font-weight: 600;">Rol:</strong> <span style="color: #60a5fa; font-weight: 700;">' + (data.rol || 'Sin rol') + '</span></span></div>';
                            if (data.ci && data.ci.length >= 10 && /^\d{10,}$/.test(data.ci)) {
                                const anio = parseInt(data.ci.substring(0, 2));
                                const mes = parseInt(data.ci.substring(2, 4));
                                const dia = parseInt(data.ci.substring(4, 6));
                                const digitoGenero = parseInt(data.ci.charAt(9));
                                const ahora = new Date();
                                let anioCompleto = anio + (anio > 30 ? 1900 : 2000);
                                let fechaNac = new Date(anioCompleto, mes - 1, dia);
                                let edad = ahora.getFullYear() - fechaNac.getFullYear();
                                const mesHoy = ahora.getMonth();
                                if (mesHoy < (mes - 1) || (mesHoy === (mes - 1) && ahora.getDate() < dia)) edad--;
                                const sexo = digitoGenero % 2 !== 0 ? 'Femenino' : 'Masculino';
                                const colorSexo = digitoGenero % 2 !== 0 ? '#f472b6' : '#60a5fa';
                                html += '<div style="display: flex; align-items: center; gap:0.5rem;"><span style="width:0.5rem; height:0.5rem; min-width:0.5rem; background: #a78bfa; border-radius: 50%; display: inline-block;"></span><span style="font-size:0.82rem;"><strong style="color: #94a3b8; font-weight: 600;">Edad:</strong> <span style="color: #e2e8f0; font-weight: 700;">' + edad + ' años</span> <strong style="color: #94a3b8; font-weight: 600; margin-left:0.625rem;">Sexo:</strong> <span style="color: ' + colorSexo + '; font-weight: 700;">' + sexo + '</span></span></div>';
                            }
                            html += '</div>';
                            userInfo.innerHTML = html;
                            userInfo.style.display = 'block';
                        } else {
                            userInfo.style.display = 'none';
                        }
                    }
                } else {
                    userAvatar.src = data.imagen;
                    if (userInfo) {
                        userInfo.innerHTML = '<div style="display: flex; align-items: center; gap:0.5rem;"><i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size:0.85rem;"></i><span style="font-size:0.82rem; color: #f59e0b; font-weight: 700;">USUARIO INEXISTENTE</span></div>';
                        userInfo.style.display = 'block';
                    }
                }
                userAvatar.classList.add('visible');
            })
            .catch(() => {});
    }, 600);
}

function validarCI(ci) {
    ci = ci.replace(/[\s-]/g, '');
    if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: '11 dígitos requeridos' };
    const año = ci.substr(0, 2);
    const mes = ci.substr(2, 2);
    const dia = ci.substr(4, 2);
    if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes inválido' };
    const diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const maxDias = diasPorMes[parseInt(mes) - 1];
    if (parseInt(mes) === 2 && parseInt(dia) === 29) {
        const añoCompleto = parseInt(año) < 30 ? 2000 + parseInt(año) : 1900 + parseInt(año);
        const esBisiesto = (añoCompleto % 4 === 0 && añoCompleto % 100 !== 0) || (añoCompleto % 400 === 0);
        if (!esBisiesto) return { valido: false, mensaje: '29/02 solo válido en años bisiestos' };
    } else if (dia < '01' || parseInt(dia) > maxDias) {
        return { valido: false, mensaje: 'Día inválido' };
    }
    const digitoGenero = parseInt(ci.charAt(9));
    const genero = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
    const iconoGenero = digitoGenero % 2 === 0 ? '<i class="fas fa-mars me-1"></i>' : '<i class="fas fa-venus me-1"></i>';
    const añoCompleto = parseInt(año) < 30 ? `20${año}` : `19${año}`;
    return { valido: true, mensaje: `<i class="fas fa-check-circle me-1"></i> CI válido | ${iconoGenero} ${genero} | Nac: ${dia}/${mes}/${añoCompleto}`, genero };
}

function procesarCI(valor) {
    const ciFeedback = document.getElementById('ciFeedback');
    const usernameInput = document.getElementById('username');
    if (/^\d{11}$/.test(valor)) {
        const resultado = validarCI(valor);
        ciFeedback.style.display = 'block';
        if (resultado.valido) {
            ciFeedback.innerHTML = resultado.mensaje;
            ciFeedback.style.color = '#22c55e';
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
        } else {
            ciFeedback.innerHTML = `<i class="fas fa-times-circle me-1"></i> ${resultado.mensaje}`;
            ciFeedback.style.color = '#ef4444';
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
        }
    } else {
        ciFeedback.style.display = 'none';
        usernameInput.classList.remove('is-valid', 'is-invalid');
    }
}

// ==========================================
// EVENTOS (solo si dbOk)
// ==========================================
if (dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        const usernameInput = document.getElementById('username');
        const charCounter = document.getElementById('charCounter');
        const userAvatar = document.getElementById('userAvatar');
        
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const inputLength = this.value.length;
                const valor = this.value.trim();
                charCounter.textContent = `${inputLength}/${MAX_LENGTH}`;
                if (inputLength === 0) charCounter.classList.remove('show');
                else {
                    charCounter.classList.add('show');
                    if (inputLength > (MAX_LENGTH - 10)) charCounter.style.color = '#ef4444';
                    else charCounter.style.color = 'rgba(255, 255, 255, 0.5)';
                }
                updateInputIcon(valor);
                procesarCI(valor);
                if (valor.length === 0) {
                    userAvatar.classList.remove('visible');
                    const userInfo = document.getElementById('userInfoFeedback');
                    if (userInfo) { userInfo.innerHTML = ''; userInfo.style.display = 'none'; }
                }
                else cargarImagenUsuario(valor);
            });
            usernameInput.addEventListener('focus', function() {
                if (this.value.length > 0) charCounter.classList.add('show');
                if (this.value.trim()) cargarImagenUsuario(this.value);
            });
            usernameInput.addEventListener('blur', function() {
                if (this.value.length === 0) charCounter.classList.remove('show');
            });
            if (usernameInput.value.trim()) {
                const val = usernameInput.value.trim();
                updateInputIcon(val);
                cargarImagenUsuario(val);
                procesarCI(val);
            }
        }
        
        // Toggle password
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
    });
}

// ==========================================
// BOTÓN REGRESAR (siempre funcional)
// ==========================================
const backHomeBtn = document.getElementById('backHomeBtn');
if (backHomeBtn) {
    backHomeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '<i class="fas fa-arrow-left" style="color: #f59e0b; font-size:2rem;"></i><br>¿Regresar al inicio?',
            html: '<p style="font-size:1rem;">Se cerrará el acceso al sistema de nóminas.</p>',
            icon: 'question',
            background: '#0f172a',
            color: '#e2e8f0',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: '<i class="fas fa-arrow-left"></i> Sí, regresar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '../index.php';
        });
    });
}

// ==========================================
// RECUPERACIÓN DE CONTRASEÑA (solo si dbOk)
// ==========================================
function openForgotPassword() {
    Swal.fire({
        title: '🔐 Recuperar Contraseña',
        html: `
            <div style="text-align: center; padding:0.625rem 0;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom:1.25rem;">
                    <div style="background: linear-gradient(135deg, #3b82f6, #2563eb); width:3.125rem; height:3.125rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right:0.9375rem; box-shadow: 0 0.25rem 0.9375rem rgba(59, 130, 246, 0.3);">
                        <i class="fa-solid fa-envelope" style="font-size:1.5rem; color: white;"></i>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin:0; color: #60a5fa; font-size:1.1rem;">Recuperación de Acceso</h3>
                        <p style="margin:0.3125rem 0 0 0; color: #8899aa; font-size:0.85rem;">Complete todos los campos para verificar su identidad</p>
                    </div>
                </div>
                
                <div style="background: rgba(59, 130, 246, 0.05); border-radius: 0.75rem; padding:1.25rem; margin-bottom:1.25rem; border: 0.0625rem solid rgba(59, 130, 246, 0.1);">
                    <p style="color: #60a5fa; margin-bottom:0.9375rem; text-align: center; font-size:0.95rem;">
                        <i class="fa-solid fa-circle-info" style="margin-right:0.5rem;"></i> 
                        Ingrese sus datos de verificación
                    </p>
                    
                    <!-- Fila de campos: Correo y Carné -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap:0.9375rem; margin-bottom:1.25rem;">
                        <!-- Campo Correo -->
                        <div>
                            <label style="display: block; color: #eee; margin-bottom:0.5rem; font-size:0.9rem; font-weight: 500;">
                                <i class="fa-solid fa-at" style="color: #3b82f6; margin-right:0.5rem;"></i> 
                                Correo Electrónico
                            </label>
                            <div style="position: relative;">
                                <input type="email" id="forgotEmail" 
                                       style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.5rem; border-radius: 0.5rem; border: 0.0625rem solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size:0.95rem; transition: all 0.3s ease;"
                                       placeholder="Ej: juan.perez@gmail.com" 
                                       autocomplete="email">
                                <div style="position: absolute; left:0.75rem; top:50%; transform: translateY(-50%); color: #3b82f6;">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                            </div>
                            <small style="color: #8899aa; font-size:0.75rem; display: block; margin-top:0.3125rem;">
                                El correo registrado en el sistema
                            </small>
                        </div>
                        
                        <!-- Campo Carné de Identidad -->
                        <div>
                            <label style="display: block; color: #eee; margin-bottom:0.5rem; font-size:0.9rem; font-weight: 500;">
                                <i class="fa-solid fa-id-card" style="color: 2; margin-right:0.5rem;"></i> 
                                Carné de Identidad
                            </label>
                            <div style="position: relative;">
                                <input type="text" id="forgotCarnet" 
                                       style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.5rem; border-radius: 0.5rem; border: 0.0625rem solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size:0.95rem; transition: all 0.3s ease;"
                                       placeholder="Ej: 85010112345" 
                                       autocomplete="off"
                                       maxlength="11">
                                <div style="position: absolute; left:0.75rem; top:50%; transform: translateY(-50%); color: 2;">
                                    <i class="fa-solid fa-address-card"></i>
                                </div>
                                <div style="position: absolute; right:0.75rem; top:50%; transform: translateY(-50%); color: #8899aa; font-size:0.8rem; font-weight: bold;">
                                    11 dígitos
                                </div>
                            </div>
                            <small style="color: #8899aa; font-size:0.75rem; display: block; margin-top:0.3125rem;">
                                Sin guiones ni espacios
                            </small>
                        </div>
                    </div>
                    
                    <!-- Campo Nombre Completo -->
                    <div>
                        <label style="display: block; color: #eee; margin-bottom:0.5rem; font-size:0.9rem; font-weight: 500;">
                            <i class="fa-solid fa-user-circle" style="color: #a78bfa; margin-right:0.5rem;"></i> 
                            Nombre Completo
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="forgotNombre" 
                                   style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.5rem; border-radius: 0.5rem; border: 0.0625rem solid rgba(255,255,255,0.15); background: rgba(0, 0, 0, 0.4); color: white; font-size:0.95rem; transition: all 0.3s ease;"
                                   placeholder="Ej: Juan Pérez Rodríguez" 
                                   autocomplete="name">
                            <div style="position: absolute; left:0.75rem; top:50%; transform: translateY(-50%); color: #a78bfa;">
                                <i class="fa-solid fa-signature"></i>
                            </div>
                        </div>
                        <small style="color: #8899aa; font-size:0.75rem; display: block; margin-top:0.3125rem;">
                            Como aparece registrado en el sistema
                        </small>
                    </div>
                </div>
                
                <!-- Nota informativa -->
                <div style="background: rgba(251, 191, 36, 0.08); border-radius: 0.5rem; padding:0.75rem; border-left: 0.1875rem solid #fbbf24; margin-top:0.9375rem;">
                    <div style="display: flex; align-items: center;">
                        <i class="fa-solid fa-shield-alt" style="color: #fbbf24; margin-right:0.625rem; font-size:0.9rem;"></i>
                        <span style="color: #fbbf24; font-size:0.85rem; font-weight: 500;">
                            Verificación de seguridad: Se validarán los tres campos antes de enviar el enlace
                        </span>
                    </div>
                </div>
                
                <p style="margin:0.75rem 0 0 0; color: #8899aa; font-size:0.78rem; text-align: left;">
                    <i class="fa-solid fa-circle-info" style="color: #60a5fa; margin-right:0.375rem;"></i>El enlace será válido por 30 minutos.
                </p>
            </div>
        `,
        background: '#0f172a',
        color: '#eee',
        width: 620,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-paper-plane" style="margin-right:0.5rem;"></i> Enviar enlace',
        confirmButtonColor: '#3b82f6',
        cancelButtonText: '<i class="fa-solid fa-times" style="margin-right:0.5rem;"></i> Cancelar',
        cancelButtonColor: '#475569',
        allowOutsideClick: false,
        preConfirm: () => {
            const email = document.getElementById('forgotEmail').value.trim();
            const carnet = document.getElementById('forgotCarnet').value.trim();
            const nombre = document.getElementById('forgotNombre').value.trim();
            
            if (!email) { Swal.showValidationMessage('Por favor ingrese su correo electrónico'); return false; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { Swal.showValidationMessage('El formato del correo no es válido'); return false; }
            if (!carnet) { Swal.showValidationMessage('Por favor ingrese su número de carné de identidad'); return false; }
            if (!/^\d{11}$/.test(carnet)) { Swal.showValidationMessage('El número de carné debe contener exactamente 11 dígitos numéricos'); return false; }
            if (!nombre) { Swal.showValidationMessage('Por favor ingrese su nombre completo'); return false; }
            
            return { email: email, no_ci: carnet, nombre_completo: nombre };
        }
    }).then((result) => {
        if (result.isConfirmed) recoverPassword(result.value);
    });
}

function openRecoverModal(e) {
    if(e) e.preventDefault();
    Swal.fire({
        title: '🔒 Recuperación de Contraseña',
        html: `<div style="text-align: center; padding:1.25rem;">
                    <i class="fa-solid fa-shield-cat" style="font-size:3rem; color: #3b82f6; margin-bottom:0.9375rem;"></i>
                    <p style="font-size:1.1em; color: #fff; margin-bottom:0.9375rem;"><strong>¿Has olvidado tu contraseña?</strong></p>
                    <p style="color: #94a3b8; font-size:0.95rem; margin-bottom:1.25rem;">Por razones de seguridad, el restablecimiento de contraseñas debe ser solicitado directamente al administrador del sistema.</p>
                    <div style="background: rgba(59, 130, 246, 0.1); border-radius: 0.5rem; padding:0.9375rem; border: 0.0625rem dashed rgba(59, 130, 246, 0.3);">
                        <h5 style="color: #60a5fa; margin-bottom:0.625rem;">Contacta con soporte:</h5>
                        <a href="mailto:<?php echo $email_soporte_js; ?>" style="display: inline-block; background: #3b82f6; color: white; padding:0.5rem 1rem; border-radius: 1.25rem; text-decoration: none;">
                            <i class="fa-solid fa-envelope me-2"></i> Correo
                        </a>
                    </div>
                </div>`,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonText: '<i class="fas fa-check"></i> Entendido',
        confirmButtonColor: '#3b82f6'
    });
}

// ==========================================
// FUNCIÓN MEJORADA DE RECUPERACIÓN DE CONTRASEÑA
// CON PROGRESS BAR FUNCIONAL
// ==========================================
function recoverPassword(data) {
    // Crear el modal con progress bar
    const progressModal = Swal.fire({
        title: '📧 Enviando solicitud',
        html: `
            <div style="padding:1.25rem 0.625rem;">
                <div style="display: flex; flex-direction: column; align-items: center; gap:1.25rem;">
                    <!-- Spinner animado -->
                    <div style="position: relative; width:3.75rem; height:3.75rem;">
                        <div style="position: absolute; top:0; left:0; width:100%; height:100%; border: 0.25rem solid rgba(59, 130, 246, 0.1); border-radius: 50%;"></div>
                        <div style="position: absolute; top:0; left:0; width:100%; height:100%; border: 0.25rem solid transparent; border-top-color: #3b82f6; border-radius: 50%; animation: spinProgress 0.8s linear infinite;"></div>
                        <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); font-size:1.25rem; color: #3b82f6;">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso estilo Windows 11 -->
                    <div style="width:100%; max-width:25rem;">
                        <div style="display: flex; justify-content: space-between; font-size:0.75rem; color: #94a3b8; margin-bottom:0.375rem;">
                            <span id="progressLabel">Verificando datos...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div style="width:100%; height:0.375rem; background: rgba(255,255,255,0.08); border-radius: 0.25rem; overflow: hidden; position: relative;">
                            <div id="progressBar" style="width:0%; height:100%; background: linear-gradient(90deg, #3b82f6, #60a5fa); border-radius: 0.25rem; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    <!-- Estado actual -->
                    <div id="statusMessage" style="font-size:0.8125rem; color: #94a3b8; min-height:1.25rem; text-align: center;">
                        Inicializando...
                    </div>
                </div>
            </div>
            
            <style>
                @keyframes spinProgress {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                @keyframes pulseGlow {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                .progress-glow {
                    animation: pulseGlow 1.5s ease-in-out infinite;
                }
            </style>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: 500,
        didOpen: () => {
            // Iniciar la animación de progreso
            startProgressSimulation();
        }
    });

    // Función para simular el progreso
    function startProgressSimulation() {
        let progress = 0;
        const steps = [
            { progress: 10, label: '🔍 Verificando credenciales...' },
            { progress: 25, label: '✅ Validando carné de identidad...' },
            { progress: 40, label: '✅ Verificando nombre completo...' },
            { progress: 55, label: '🔄 Generando token de seguridad...' },
            { progress: 70, label: '📝 Preparando correo electrónico...' },
            { progress: 85, label: '🚀 Enviando correo...' }
        ];

        let stepIndex = 0;
        
        // Función para actualizar la barra
        function updateProgress(targetProgress, label) {
            const bar = document.getElementById('progressBar');
            const percent = document.getElementById('progressPercent');
            const statusMsg = document.getElementById('statusMessage');
            const progressLabel = document.getElementById('progressLabel');
            
            // Animación suave
            const startProgress = parseFloat(bar.style.width) || 0;
            const duration = 600; // ms
            const startTime = performance.now();
            
            function animate(currentTime) {
                const elapsed = currentTime - startTime;
                const progressElapsed = Math.min(elapsed / duration, 1);
                
                // Easing suave
                const ease = 1 - Math.pow(1 - progressElapsed, 3);
                const currentProgress = startProgress + (targetProgress - startProgress) * ease;
                
                bar.style.width = Math.min(currentProgress, 100) + '%';
                percent.textContent = Math.round(Math.min(currentProgress, 100)) + '%';
                
                if (progressElapsed < 1) {
                    requestAnimationFrame(animate);
                } else {
                    bar.style.width = targetProgress + '%';
                    percent.textContent = targetProgress + '%';
                    
                    if (progressLabel) progressLabel.textContent = label || 'Procesando...';
                    if (statusMsg) {
                        statusMsg.textContent = label || 'Procesando...';
                        statusMsg.style.color = '#60a5fa';
                    }
                }
            }
            
            requestAnimationFrame(animate);
        }

        // Iniciar la simulación
        let currentStep = 0;
        
        function processNextStep() {
            if (currentStep < steps.length) {
                const step = steps[currentStep];
                updateProgress(step.progress, step.label);
                currentStep++;
                setTimeout(processNextStep, 700 + Math.random() * 300);
            } else {
                // Esperar a que el servidor responda
                updateProgress(95, '⏳ Esperando respuesta del servidor...');
            }
        }

        setTimeout(processNextStep, 300);
        
        // Guardar referencia para actualizar desde la respuesta
        window._progressUpdate = updateProgress;
    }

    // Función para actualizar el progreso manualmente
    function updateProgressManually(progress, label, isComplete = false) {
        const bar = document.getElementById('progressBar');
        const percent = document.getElementById('progressPercent');
        const statusMsg = document.getElementById('statusMessage');
        const progressLabel = document.getElementById('progressLabel');
        
        if (bar) {
            bar.style.width = Math.min(progress, 100) + '%';
            bar.style.transition = 'width 0.5s ease';
        }
        if (percent) percent.textContent = Math.round(Math.min(progress, 100)) + '%';
        if (progressLabel) progressLabel.textContent = label || 'Procesando...';
        if (statusMsg) {
            statusMsg.textContent = label || 'Procesando...';
            statusMsg.style.color = isComplete ? '#22c55e' : '#60a5fa';
        }
        
        if (isComplete && bar) {
            bar.style.background = 'linear-gradient(90deg, #22c55e, #4ade80)';
        }
    }

    // Realizar la petición fetch
    const formData = new URLSearchParams(data);
    
    fetch('login.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => {
        // Actualizar progreso al recibir respuesta
        updateProgressManually(90, '📥 Procesando respuesta del servidor...');
        return response.json();
    })
    .then(result => {
        // Completar la barra
        updateProgressManually(100, '✅ ¡Completado!', true);
        
        // Pequeña pausa para mostrar el 100%
        setTimeout(() => {
            progressModal.close();
            
            if (!result.success) {
                Swal.fire({
                    title: '❌ No se pudo enviar',
                    text: result.message,
                    icon: 'error',
                    background: '#0f172a',
                    color: '#eee',
                    confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>Intentar de nuevo',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => { 
                    openForgotPassword(); 
                });
                return;
            }
            
            if (result.action === 'email_no_config' && result.link) {
                Swal.fire({
                    title: '📧 Enlace de recuperación',
                    html: `<div style="text-align: center; padding:0.625rem;">
                                <div style="background: rgba(251,191,36,0.1); border-radius: 0.75rem; padding:1rem; margin-bottom:1rem; border: 0.0625rem solid rgba(251,191,36,0.2);">
                                    <p style="color: #fbbf24; margin-bottom:0.5rem; font-weight: 600;">
                                        <i class="fas fa-exclamation-triangle"></i> Correo SMTP no configurado
                                    </p>
                                    <p style="color: #94a3b8; font-size:0.85rem; margin-bottom:0.5rem;">${result.message || 'El correo SMTP no está configurado.'}</p>
                                </div>
                                <p style="color: #94a3b8; font-size:0.8rem; margin-bottom:0.375rem;">🔗 Enlace de prueba (válido 30 min):</p>
                                <div style="background: rgba(0,0,0,0.3); border: 0.0625rem solid rgba(251,191,36,0.2); border-radius: 0.5rem; padding:0.75rem; word-break: break-all;">
                                    <a href="${result.link}" style="color: #60a5fa; text-decoration: none;">${result.link}</a>
                                </div>
                            </div>`,
                    icon: 'warning',
                    background: '#0f172a',
                    color: '#eee',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#3b82f6',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                return;
            }
            
            Swal.fire({
                title: '✅ ¡Correo enviado!',
                html: `<div style="text-align: center; padding:0.625rem;">
                            <div style="background: rgba(34,197,94,0.1); border-radius: 0.75rem; padding:1rem; margin-bottom:0.75rem; border: 0.0625rem solid rgba(34,197,94,0.2);">
                                <p style="color: 2; font-weight: 600; margin-bottom:0.25rem;">
                                    <i class="fas fa-check-circle"></i> Envío exitoso
                                </p>
                                <p style="color: #94a3b8; font-size:0.9rem; margin:0;">
                                    Se ha enviado un enlace a su correo electrónico
                                </p>
                            </div>
                            <div style="background: rgba(59,130,246,0.05); border-radius: 0.5rem; padding:0.75rem; border: 0.0625rem solid rgba(59,130,246,0.1);">
                                <p style="color: #94a3b8; font-size:0.8rem; margin:0;">
                                    <i class="fas fa-clock" style="color: #fbbf24;"></i> El enlace es válido por <strong style="color: #60a5fa;">30 minutos</strong>
                                </p>
                            </div>
                        </div>`,
                icon: 'success',
                background: '#0f172a',
                color: '#eee',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                confirmButtonColor: '#22c55e',
                allowOutsideClick: false,
                allowEscapeKey: false
            });
        }, 600);
    })
    .catch(error => {
        progressModal.close();
        Swal.fire({
            title: '❌ Error de conexión',
            text: 'No se pudo establecer conexión con el servidor. Verifique su conexión a internet.',
            icon: 'error',
            background: '#0f172a',
            color: '#fff',
            confirmButtonText: '<i class="fas fa-sync-alt me-2"></i> Reintentar',
            confirmButtonColor: '#ef4444',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            // Reabrir el modal de recuperación
            openForgotPassword();
        });
    });
}

// ==========================================
// FUNCIÓN MEJORADA PARA MOSTRAR FORMULARIO DE NUEVA CONTRASEÑA
// CON VERIFICACIÓN DE TOKEN, TEMPORIZADOR Y MANEJO DE EXPIRACIÓN
// ==========================================
function mostrarFormularioNuevaPassword(token) {
    // Primero verificar si el token existe y es válido en el servidor
    Swal.fire({
        title: '🔍 Verificando enlace...',
        html: '<div style="padding:1.25rem;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i></div>',
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: 'rgba(0, 0, 0, 0.8)',
        width: 400
    });

    // Verificar token en el servidor
    const formData = new URLSearchParams({ token: token, action: 'verify_token' });
    
    fetch('login.php?action=verify_token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        Swal.close();
        
        if (!result.success) {
            // Token inválido o expirado
            let titulo = '❌ Enlace inválido';
            let icono = 'error';
            let botonColor = '#ef4444';
            let botonTexto = '<i class="fas fa-times"></i> Cerrar';
            let mostrarReintentar = true;
            let mensaje = '';
            
            // Verificar el tipo de error
            if (result.codigo === 'token_no_existe') {
                mensaje = `
                    <div style="text-align: center; padding:0.625rem 0;">
                        <div style="background: rgba(239, 68, 68, 0.08); border-radius: 0.75rem; padding:1.25rem; border: 0.0625rem solid rgba(239, 68, 68, 0.2); margin-bottom:1.25rem;">
                            <div style="font-size:3rem; margin-bottom:0.625rem;">🔗❌</div>
                            <p style="color: #ef4444; font-size:1.1rem; font-weight: 600; margin:0 0 0.5rem 0;">
                                Enlace no válido
                            </p>
                            <p style="color: #94a3b8; font-size:0.95rem; margin:0;">
                                El enlace de recuperación no existe en el sistema o ya ha sido utilizado.
                            </p>
                        </div>
                        <div style="background: rgba(251, 191, 36, 0.05); border-radius: 0.5rem; padding:1rem; border: 0.0625rem solid rgba(251, 191, 36, 0.1);">
                            <p style="color: #94a3b8; font-size:0.85rem; margin:0;">
                                <i class="fas fa-info-circle" style="color: #3b82f6; margin-right:0.5rem;"></i>
                                Esto puede ocurrir si:
                            </p>
                            <ul style="color: #94a3b8; font-size:0.8rem; text-align: left; margin:0.5rem 0 0 0; padding-left:1.25rem;">
                                <li>El enlace ya fue utilizado para restablecer la contraseña</li>
                                <li>El enlace ha sido manipulado o es incorrecto</li>
                                <li>La cuenta asociada ha sido desactivada</li>
                            </ul>
                        </div>
                        <div style="margin-top:1.25rem; padding:1rem; background: rgba(59, 130, 246, 0.05); border-radius: 0.5rem; border: 0.0625rem solid rgba(59, 130, 246, 0.1);">
                            <p style="color: #60a5fa; font-size:0.9rem; margin:0;">
                                <i class="fas fa-lightbulb" style="margin-right:0.5rem;"></i>
                                Para obtener un nuevo enlace, solicite nuevamente la recuperación de contraseña desde el inicio de sesión.
                            </p>
                        </div>
                    </div>
                `;
                titulo = '🔗 Enlace no válido';
                icono = 'error';
                botonColor = '#ef4444';
                botonTexto = '<i class="fas fa-arrow-left me-2"></i> Ir al inicio de sesión';
                mostrarReintentar = true;
                
            } else if (result.codigo === 'token_expirado' || result.message.toLowerCase().includes('expirado')) {
                mensaje = `
                    <div style="text-align: center; padding:0.625rem 0;">
                        <div style="background: rgba(251, 191, 36, 0.08); border-radius: 0.75rem; padding:1.25rem; border: 0.0625rem solid rgba(251, 191, 36, 0.2); margin-bottom:1.25rem;">
                            <div style="font-size:3rem; margin-bottom:0.625rem;">⏰</div>
                            <p style="color: #fbbf24; font-size:1.1rem; font-weight: 600; margin:0 0 0.5rem 0;">
                                Enlace expirado
                            </p>
                            <p style="color: #94a3b8; font-size:0.95rem; margin:0;">
                                El enlace de recuperación ha expirado. Los enlaces son válidos por 30 minutos.
                            </p>
                        </div>
                        <div style="background: rgba(59, 130, 246, 0.05); border-radius: 0.5rem; padding:1rem; border: 0.0625rem solid rgba(59, 130, 246, 0.1);">
                            <p style="color: #60a5fa; font-size:0.9rem; margin:0;">
                                <i class="fas fa-sync-alt" style="margin-right:0.5rem;"></i>
                                Solicite un nuevo enlace de recuperación desde el inicio de sesión.
                            </p>
                        </div>
                    </div>
                `;
                titulo = '⏰ Enlace expirado';
                icono = 'warning';
                botonColor = '#f59e0b';
                botonTexto = '<i class="fas fa-arrow-left me-2"></i> Ir al inicio de sesión';
                mostrarReintentar = true;
                
            } else {
                // Otro tipo de error
                mensaje = `
                    <div style="text-align: center; padding:0.625rem 0;">
                        <div style="background: rgba(239, 68, 68, 0.08); border-radius: 0.75rem; padding:1rem; border: 0.0625rem solid rgba(239, 68, 68, 0.2); margin-bottom:1rem;">
                            <p style="color: #ef4444; font-size:1rem; margin:0;">
                                <i class="fas fa-exclamation-triangle" style="margin-right:0.5rem;"></i>
                                ${result.message || 'El enlace de recuperación no es válido.'}
                            </p>
                        </div>
                        <p style="color: #94a3b8; font-size:0.9rem; margin:0;">
                            <i class="fas fa-info-circle" style="color: #3b82f6; margin-right:0.375rem;"></i>
                            Por favor, solicite un nuevo enlace de recuperación.
                        </p>
                    </div>
                `;
                titulo = '❌ Error';
                icono = 'error';
                botonColor = '#ef4444';
                botonTexto = '<i class="fas fa-arrow-left me-2"></i> Ir al inicio de sesión';
                mostrarReintentar = true;
            }
            
            Swal.fire({
                title: titulo,
                html: mensaje,
                icon: icono,
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: botonColor,
                confirmButtonText: botonTexto,
                showCancelButton: mostrarReintentar,
                cancelButtonColor: '#475569',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                width: 560
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.replace('login.php');
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.replace('login.php');
                }
            });
            return;
        }
        
        // Token válido - Mostrar el formulario con temporizador
        const tiempoRestante = result.tiempo_restante || 1800; // segundos (30 min por defecto)
        mostrarFormularioConTimer(token, tiempoRestante);
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            title: '❌ Error de conexión',
            html: `
                <div style="text-align: center; padding:0.625rem 0;">
                    <p style="color: #94a3b8; margin-bottom:1rem;">
                        No se pudo verificar el enlace. Verifique su conexión a internet.
                    </p>
                    <p style="color: #94a3b8; font-size:0.85rem;">
                        <i class="fas fa-info-circle" style="color: #3b82f6; margin-right:0.375rem;"></i>
                        Si el problema persiste, solicite un nuevo enlace desde el inicio de sesión.
                    </p>
                </div>
            `,
            icon: 'error',
            background: '#0f172a',
            color: '#fff',
            confirmButtonText: '<i class="fas fa-sync-alt me-2"></i> Reintentar',
            confirmButtonColor: '#3b82f6',
            showCancelButton: true,
            cancelButtonColor: '#475569',
            cancelButtonText: '<i class="fas fa-arrow-left me-2"></i> Inicio de sesión',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                mostrarFormularioNuevaPassword(token);
            } else {
                window.location.replace('login.php');
            }
        });
    });
}

// ==========================================
// FUNCIÓN PARA MOSTRAR FORMULARIO CON TEMPORIZADOR
// ==========================================
function mostrarFormularioConTimer(token, tiempoRestante) {
    let tiempo = tiempoRestante;
    let intervalo;
    
    // Función para formatear el tiempo
    function formatTime(segundos) {
        const minutos = Math.floor(segundos / 60);
        const segs = segundos % 60;
        return `${minutos}:${segs.toString().padStart(2, '0')}`;
    }
    
    // Función para actualizar el temporizador
    function actualizarTimer() {
        const timerElement = document.getElementById('timerDisplay');
        const progressBar = document.getElementById('timerProgress');
        if (timerElement) {
            timerElement.textContent = formatTime(tiempo);
        }
        if (progressBar) {
            const porcentaje = (tiempo / tiempoRestante) * 100;
            progressBar.style.width = porcentaje + '%';
            // Cambiar color según el tiempo restante
            if (porcentaje < 20) {
                progressBar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
            } else if (porcentaje < 50) {
                progressBar.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
            } else {
                progressBar.style.background = 'linear-gradient(90deg, #22c55e, #4ade80)';
            }
        }
        if (tiempo <= 0) {
            clearInterval(intervalo);
            // Mostrar mensaje de expiración
            Swal.fire({
                title: '⏰ Tiempo agotado',
                html: `
                    <div style="text-align: center; padding:0.625rem 0;">
                        <div style="background: rgba(251, 191, 36, 0.08); border-radius: 0.75rem; padding:1rem; border: 0.0625rem solid rgba(251, 191, 36, 0.2); margin-bottom:1rem;">
                            <p style="color: #fbbf24; font-size:1.1rem; margin:0;">
                                <i class="fas fa-clock" style="margin-right:0.5rem;"></i>
                                El tiempo para restablecer la contraseña ha expirado.
                            </p>
                        </div>
                        <p style="color: #94a3b8; font-size:0.9rem; margin:0;">
                            <i class="fas fa-info-circle" style="color: #3b82f6; margin-right:0.375rem;"></i>
                            Por favor, solicite un nuevo enlace de recuperación desde el inicio de sesión.
                        </p>
                    </div>
                `,
                icon: 'warning',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: '<i class="fas fa-arrow-left me-2"></i> Ir al inicio de sesión',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.location.replace('login.php');
            });
        }
    }
    
    // Crear el modal con el formulario y temporizador
    const resetModal = Swal.fire({
        title: '🔑 Establecer nueva contraseña',
        html: `
            <div style="text-align: center; padding:0.625rem 0;">
                <!-- Badge de verificación -->
                <div style="background: rgba(34, 197, 94, 0.08); border: 0.0625rem solid rgba(34, 197, 94, 0.2); border-radius: 0.625rem; padding:0.75rem 1rem; margin-bottom:1rem; display: flex; align-items: center; justify-content: center; gap:0.625rem;">
                    <span style="color: 2; font-size:1.125rem;">✅</span>
                    <span style="color: #94a3b8; font-size:0.85rem;">Enlace verificado correctamente</span>
                </div>
                
                <!-- Temporizador -->
                <div style="margin-bottom:1.25rem; background: rgba(0,0,0,0.3); border-radius: 0.625rem; padding:0.875rem 1.125rem; border: 0.0625rem solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom:0.5rem;">
                        <span style="color: #94a3b8; font-size:0.8rem;">
                            <i class="fas fa-hourglass-half" style="color: #3b82f6; margin-right:0.375rem;"></i>
                            Tiempo restante:
                        </span>
                        <span id="timerDisplay" style="color: #60a5fa; font-weight: 700; font-size:1.1rem; font-family: 'Courier New', monospace;">
                            ${formatTime(tiempo)}
                        </span>
                    </div>
                    <div style="width:100%; height:0.25rem; background: rgba(255,255,255,0.08); border-radius: 0.125rem; overflow: hidden;">
                        <div id="timerProgress" style="width:100%; height:100%; background: linear-gradient(90deg, 2, #4ade80); border-radius: 0.125rem; transition: width 0.5s ease;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size:0.6rem; color: #5a6a8a; margin-top:0.25rem;">
                        <span>${Math.floor(tiempoRestante / 60)} min</span>
                        <span>0 min</span>
                    </div>
                </div>
                
                <!-- Campo Nueva Contraseña -->
                <div style="margin-bottom:1rem; text-align: left;">
                    <label style="display: block; color: #e2e8f0; margin-bottom:0.5rem; font-size:0.9rem; font-weight: 500;">
                        <i class="fa-solid fa-lock" style="color: #3b82f6; margin-right:0.5rem;"></i> Nueva Contraseña
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="resetPass" 
                               style="width:100%; padding:0.75rem 2.75rem 0.75rem 1rem; border-radius: 0.5rem; border: 0.0625rem solid rgba(255,255,255,0.1); background: rgba(0, 0, 0, 0.4); color: white; font-size:0.95rem; transition: all 0.3s ease;"
                               placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                        <button type="button" id="toggleResetPass" 
                                style="position: absolute; right:0.625rem; top:50%; transform: translateY(-50%); background: transparent; border: none; color: #64748b; cursor: pointer; padding:0.25rem 0.5rem; border-radius: 0.25rem; transition: all 0.2s ease;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordStrength" style="margin-top:0.375rem; font-size:0.75rem; color: #64748b;">
                        <span id="strengthText">La contraseña debe tener al menos 6 caracteres</span>
                    </div>
                </div>
                
                <!-- Campo Confirmar Contraseña -->
                <div style="margin-bottom:1rem; text-align: left;">
                    <label style="display: block; color: #e2e8f0; margin-bottom:0.5rem; font-size:0.9rem; font-weight: 500;">
                        <i class="fa-solid fa-lock" style="color: 2; margin-right:0.5rem;"></i> Confirmar Contraseña
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="resetPass2" 
                               style="width:100%; padding:0.75rem 2.75rem 0.75rem 1rem; border-radius: 0.5rem; border: 0.0625rem solid rgba(255,255,255,0.1); background: rgba(0, 0, 0, 0.4); color: white; font-size:0.95rem; transition: all 0.3s ease;"
                               placeholder="Repita la contraseña" autocomplete="new-password">
                        <button type="button" id="toggleResetPass2" 
                                style="position: absolute; right:0.625rem; top:50%; transform: translateY(-50%); background: transparent; border: none; color: #64748b; cursor: pointer; padding:0.25rem 0.5rem; border-radius: 0.25rem; transition: all 0.2s ease;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="matchFeedback" style="margin-top:0.375rem; font-size:0.75rem;"></div>
                </div>
                
                <!-- Requisitos de contraseña -->
                <div style="background: rgba(59, 130, 246, 0.03); border-radius: 0.5rem; padding:0.75rem 1rem; border: 0.0625rem solid rgba(59, 130, 246, 0.06); text-align: left;">
                    <p style="color: #94a3b8; font-size:0.75rem; margin:0;">
                        <i class="fas fa-info-circle" style="color: #3b82f6; margin-right:0.375rem;"></i>
                        La contraseña debe contener al menos:
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap:0.75rem; margin-top:0.375rem;">
                        <span id="reqLength" style="font-size:0.7rem; color: #64748b; display: flex; align-items: center; gap:0.25rem;">
                            <i class="fas fa-circle" style="font-size:0.375rem;"></i> 6 caracteres
                        </span>
                        <span id="reqNumber" style="font-size:0.7rem; color: #64748b; display: flex; align-items: center; gap:0.25rem;">
                            <i class="fas fa-circle" style="font-size:0.375rem;"></i> 1 número
                        </span>
                        <span id="reqSpecial" style="font-size:0.7rem; color: #64748b; display: flex; align-items: center; gap:0.25rem;">
                            <i class="fas fa-circle" style="font-size:0.375rem;"></i> 1 carácter especial
                        </span>
                    </div>
                </div>
            </div>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        width: 520,
        showConfirmButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-key" style="margin-right:0.5rem;"></i> Restablecer',
        confirmButtonColor: '#22c55e',
        cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
        cancelButtonColor: '#475569',
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: 'rgba(0, 0, 0, 0.85)',
        preConfirm: () => {
            // Verificar si el tiempo ha expirado
            if (tiempo <= 0) {
                Swal.showValidationMessage('⏰ El tiempo para restablecer la contraseña ha expirado. Solicite un nuevo enlace.');
                return false;
            }
            
            const p1 = document.getElementById('resetPass').value;
            const p2 = document.getElementById('resetPass2').value;
            
            // Validaciones
            if (!p1) {
                Swal.showValidationMessage('❌ Ingrese la nueva contraseña');
                return false;
            }
            if (p1.length < 6) {
                Swal.showValidationMessage('❌ La contraseña debe tener al menos 6 caracteres');
                return false;
            }
            if (p1 !== p2) {
                Swal.showValidationMessage('❌ Las contraseñas no coinciden');
                return false;
            }
            
            // Verificar fuerza de contraseña
            const hasNumber = /\d/.test(p1);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(p1);
            
            if (!hasNumber || !hasSpecial) {
                Swal.showValidationMessage('⚠️ La contraseña debe contener al menos 1 número y 1 carácter especial');
                return false;
            }
            
            return { token: token, password: p1 };
        },
        didOpen: () => {
            // Iniciar el temporizador
            intervalo = setInterval(() => {
                tiempo--;
                actualizarTimer();
            }, 1000);
            
            // ========== EVENTOS DEL FORMULARIO ==========
            
            // Toggle visibility para campo 1
            const toggleBtn1 = document.getElementById('toggleResetPass');
            const passInput1 = document.getElementById('resetPass');
            if (toggleBtn1 && passInput1) {
                toggleBtn1.addEventListener('click', function() {
                    const type = passInput1.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput1.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            }
            
            // Toggle visibility para campo 2
            const toggleBtn2 = document.getElementById('toggleResetPass2');
            const passInput2 = document.getElementById('resetPass2');
            if (toggleBtn2 && passInput2) {
                toggleBtn2.addEventListener('click', function() {
                    const type = passInput2.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput2.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            }
            
            // Validación en tiempo real para contraseña 1
            if (passInput1) {
                passInput1.addEventListener('input', function() {
                    const val = this.value;
                    const hasNumber = /\d/.test(val);
                    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(val);
                    const hasLength = val.length >= 6;
                    
                    // Actualizar requisitos
                    const reqLength = document.getElementById('reqLength');
                    const reqNumber = document.getElementById('reqNumber');
                    const reqSpecial = document.getElementById('reqSpecial');
                    const strengthText = document.getElementById('strengthText');
                    
                    if (reqLength) {
                        reqLength.style.color = hasLength ? '#22c55e' : '#64748b';
                        reqLength.innerHTML = hasLength ? '<i class="fas fa-check-circle" style="color:2;"></i> 6 caracteres' : '<i class="fas fa-circle" style="font-size:0.375rem;"></i> 6 caracteres';
                    }
                    if (reqNumber) {
                        reqNumber.style.color = hasNumber ? '#22c55e' : '#64748b';
                        reqNumber.innerHTML = hasNumber ? '<i class="fas fa-check-circle" style="color:2;"></i> 1 número' : '<i class="fas fa-circle" style="font-size:0.375rem;"></i> 1 número';
                    }
                    if (reqSpecial) {
                        reqSpecial.style.color = hasSpecial ? '#22c55e' : '#64748b';
                        reqSpecial.innerHTML = hasSpecial ? '<i class="fas fa-check-circle" style="color:2;"></i> 1 carácter especial' : '<i class="fas fa-circle" style="font-size:0.375rem;"></i> 1 carácter especial';
                    }
                    
                    // Texto de fuerza
                    if (strengthText) {
                        if (val.length === 0) {
                            strengthText.textContent = 'La contraseña debe tener al menos 6 caracteres';
                            strengthText.style.color = '#64748b';
                        } else if (hasLength && hasNumber && hasSpecial) {
                            strengthText.textContent = '✅ Contraseña segura';
                            strengthText.style.color = '#22c55e';
                        } else if (hasLength) {
                            strengthText.textContent = '⚠️ Añade números y caracteres especiales para mayor seguridad';
                            strengthText.style.color = '#fbbf24';
                        } else {
                            strengthText.textContent = '❌ Mínimo 6 caracteres';
                            strengthText.style.color = '#ef4444';
                        }
                    }
                    
                    // Verificar coincidencia si el segundo campo tiene valor
                    if (passInput2 && passInput2.value) {
                        verificarCoincidencia(passInput1, passInput2);
                    }
                });
            }
            
            // Validación en tiempo real para contraseña 2
            if (passInput2) {
                passInput2.addEventListener('input', function() {
                    verificarCoincidencia(passInput1, passInput2);
                });
            }
            
            // Función para verificar coincidencia
            function verificarCoincidencia(p1, p2) {
                const feedback = document.getElementById('matchFeedback');
                if (!feedback) return;
                
                if (!p2.value) {
                    feedback.textContent = '';
                    feedback.style.color = '#64748b';
                    return;
                }
                
                if (p1.value === p2.value) {
                    feedback.innerHTML = '✅ Las contraseñas coinciden';
                    feedback.style.color = '#22c55e';
                } else {
                    feedback.innerHTML = '❌ Las contraseñas no coinciden';
                    feedback.style.color = '#ef4444';
                }
            }
        },
        willClose: () => {
            // Limpiar el intervalo cuando se cierra el modal
            if (intervalo) {
                clearInterval(intervalo);
            }
        }
    }).then((result) => {
        // Limpiar intervalo al cerrar
        if (intervalo) {
            clearInterval(intervalo);
        }
        
        if (result.isConfirmed && result.value) {
            // Verificar nuevamente que el tiempo no haya expirado
            if (tiempo <= 0) {
                Swal.fire({
                    title: '⏰ Tiempo agotado',
                    text: 'El tiempo para restablecer la contraseña ha expirado. Solicite un nuevo enlace.',
                    icon: 'warning',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    confirmButtonColor: '#f59e0b',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    window.location.replace('login.php');
                });
                return;
            }
            // Mostrar modal de progreso mientras se procesa
            mostrarProgresoReset(result.value);
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Redirigir al login si cancela
            window.location.replace('login.php');
        }
    });
}

// ==========================================
// FUNCIÓN PARA MOSTRAR PROGRESO DEL RESTABLECIMIENTO
// ==========================================
function mostrarProgresoReset(data) {
    const progressModal = Swal.fire({
        title: '🔄 Restableciendo contraseña',
        html: `
            <div style="padding:1.25rem 0.625rem;">
                <div style="display: flex; flex-direction: column; align-items: center; gap:1.25rem;">
                    <!-- Spinner -->
                    <div style="position: relative; width:3.75rem; height:3.75rem;">
                        <div style="position: absolute; top:0; left:0; width:100%; height:100%; border: 0.25rem solid rgba(34, 197, 94, 0.1); border-radius: 50%;"></div>
                        <div style="position: absolute; top:0; left:0; width:100%; height:100%; border: 0.25rem solid transparent; border-top-color: 2; border-radius: 50%; animation: spinProgress 0.8s linear infinite;"></div>
                        <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); font-size:1.25rem; color: 2;">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div style="width:100%; max-width:25rem;">
                        <div style="display: flex; justify-content: space-between; font-size:0.75rem; color: #94a3b8; margin-bottom:0.375rem;">
                            <span id="resetProgressLabel">Verificando token...</span>
                            <span id="resetProgressPercent">0%</span>
                        </div>
                        <div style="width:100%; height:0.375rem; background: rgba(255,255,255,0.08); border-radius: 0.25rem; overflow: hidden; position: relative;">
                            <div id="resetProgressBar" style="width:0%; height:100%; background: linear-gradient(90deg, 2, #4ade80); border-radius: 0.25rem; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    <!-- Estado -->
                    <div id="resetStatusMessage" style="font-size:0.8125rem; color: #94a3b8; min-height:1.25rem; text-align: center;">
                        Inicializando...
                    </div>
                </div>
            </div>
            
            <style>
                @keyframes spinProgress {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `,
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: 'rgba(0, 0, 0, 0.9)',
        width: 500,
        didOpen: () => {
            // Simular progreso
            let progress = 0;
            const steps = [
                { progress: 15, label: '🔍 Verificando token de seguridad...' },
                { progress: 35, label: '✅ Validando usuario...' },
                { progress: 55, label: '🔐 Generando nuevo hash de contraseña...' },
                { progress: 75, label: '💾 Actualizando base de datos...' },
                { progress: 90, label: '⏳ Finalizando proceso...' }
            ];
            
            let stepIndex = 0;
            
            function updateResetProgress(targetProgress, label) {
                const bar = document.getElementById('resetProgressBar');
                const percent = document.getElementById('resetProgressPercent');
                const statusMsg = document.getElementById('resetStatusMessage');
                const progressLabel = document.getElementById('resetProgressLabel');
                
                if (bar) bar.style.width = targetProgress + '%';
                if (percent) percent.textContent = targetProgress + '%';
                if (progressLabel) progressLabel.textContent = label || 'Procesando...';
                if (statusMsg) {
                    statusMsg.textContent = label || 'Procesando...';
                    statusMsg.style.color = '#60a5fa';
                }
            }
            
            function processResetStep() {
                if (stepIndex < steps.length) {
                    const step = steps[stepIndex];
                    updateResetProgress(step.progress, step.label);
                    stepIndex++;
                    setTimeout(processResetStep, 600 + Math.random() * 300);
                } else {
                    updateResetProgress(95, '⏳ Esperando confirmación del servidor...');
                }
            }
            
            setTimeout(processResetStep, 300);
            
            // Guardar referencia para actualizar desde respuesta
            window._resetProgressUpdate = updateResetProgress;
        }
    });
    
    // Realizar la petición fetch
    const fd = new URLSearchParams(data);
    
    fetch('login.php?action=reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd
    })
    .then(resp => resp.json())
    .then(res => {
        // Actualizar progreso al 100%
        const bar = document.getElementById('resetProgressBar');
        const percent = document.getElementById('resetProgressPercent');
        const statusMsg = document.getElementById('resetStatusMessage');
        
        if (bar) {
            bar.style.width = '100%';
            bar.style.background = 'linear-gradient(90deg, #22c55e, #4ade80)';
        }
        if (percent) percent.textContent = '100%';
        if (statusMsg) {
            statusMsg.textContent = '✅ ¡Completado!';
            statusMsg.style.color = '#22c55e';
        }
        
        setTimeout(() => {
            progressModal.close();
            
            if (res.success) {
                Swal.fire({
                    title: '✅ ¡Contraseña restablecida!',
                    html: `<div style="text-align: center; padding:0.625rem;">
                                <div style="background: rgba(34,197,94,0.08); border-radius: 0.75rem; padding:1rem; border: 0.0625rem solid rgba(34,197,94,0.2);">
                                    <p style="color: 2; font-weight: 600; font-size:1.1rem; margin-bottom:0.25rem;">
                                        <i class="fas fa-check-circle"></i> Éxito
                                    </p>
                                    <p style="color: #94a3b8; font-size:0.9rem; margin:0;">
                                        Ya puede iniciar sesión con su nueva contraseña
                                    </p>
                                </div>
                            </div>`,
                    icon: 'success',
                    background: '#0f172a',
                    color: '#eee',
                    confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i> Iniciar sesión',
                    confirmButtonColor: '#22c55e',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    window.location.replace('login.php?reset_ok=1');
                });
            } else {
                Swal.fire({
                    title: '❌ Error',
                    text: res.message || 'No se pudo restablecer la contraseña',
                    icon: 'error',
                    background: '#0f172a',
                    color: '#eee',
                    confirmButtonText: '<i class="fas fa-times"></i> Cerrar',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    window.location.replace('login.php');
                });
            }
        }, 500);
    })
    .catch(error => {
        progressModal.close();
        Swal.fire({
            title: '❌ Error de conexión',
            text: 'No se pudo conectar con el servidor. Verifique su conexión a internet.',
            icon: 'error',
            background: '#0f172a',
            color: '#fff',
            confirmButtonText: '<i class="fas fa-sync-alt me-2"></i> Reintentar',
            confirmButtonColor: '#ef4444',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Reintentar con el mismo token
                mostrarFormularioNuevaPassword(data.token);
            }
        });
    });
}

if (dbOk) {
    const forgotLink = document.getElementById('forgotPasswordLink');
    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (restabpw == 1 || mailConfigurado) openForgotPassword();
            else openRecoverModal();
        });
    }
}

// ==========================================
// ENLACE DE RECUPERACIÓN CON TOKEN (?reset_token=)
// ==========================================
if (dbOk) {
    const params = new URLSearchParams(window.location.search);
    const resetToken = params.get('reset_token');
    if (resetToken) {
        document.addEventListener('DOMContentLoaded', function() {
            mostrarFormularioNuevaPassword(resetToken);
        });
    }
}

// ==========================================
// SOLICITAR CUENTA (envío por correo)
// ==========================================
const solicitarCuentaLink = document.getElementById('solicitarCuentaLink');
if (solicitarCuentaLink) {
    solicitarCuentaLink.addEventListener('click', function(e) {
        e.preventDefault();
        const modalContent = `
            <div style="text-align: left; max-height:31.25rem; overflow-y: auto; padding-right:0.625rem;">
                <div class="modal-form-field">
                    <label><i class="fas fa-user"></i> Nombre y Apellidos</label>
                    <input type="text" id="nombre_apellidos" placeholder="Ej: Juan Pérez García" autocomplete="off">
                </div>
                <div class="row-2-columns" style="display: flex; gap:0.625rem; margin-bottom:0.9375rem;">
                    <div class="modal-form-field" style="flex:1;">
                        <label><i class="fas fa-id-card"></i> N° de Identidad (CI)</label>
                        <input type="text" id="ci" placeholder="Ej: 12345678901" autocomplete="off">
                    </div>
                    <div class="modal-form-field" style="flex:1;">
                        <label><i class="fas fa-phone-alt"></i> <strong style="color: #60a5fa;">No. Teléfono</strong></label>
                        <input type="tel" id="telefono" placeholder="Ej: 32415418" autocomplete="off">
                    </div>
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <input type="text" id="direccion" placeholder="Ej: Calle 5ta #123, Nuevitas" autocomplete="off">
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-briefcase"></i> Área de trabajo</label>
                    <input type="text" id="area" placeholder="Ej: Recursos Humanos, Contabilidad, TI" autocomplete="off">
                </div>
                <div class="modal-form-field">
                    <label><i class="fas fa-cubes"></i> Subsistema al que solicita acceso</label>
                    <select id="subsistema">
                        <option value="">-- Seleccione un subsistema --</option>
                        <option value="Nóminas">📊 Nóminas</option>
                        <option value="Facturación">📄 Facturación</option>
                        <option value="Costos">💰 Costos</option>
                        <option value="Inventarios">📦 Inventarios</option>
                        <option value="Administración">🛠️ Administración</option>
                    </select>
                    <small style="color: #ffffff; font-size:0.65rem; display: block; margin-top:0.375rem;">
                        <i class="fas fa-info-circle"></i> <strong>Información importante:</strong> Su solicitud será revisada por el equipo de soporte en un plazo máximo de 24 horas hábiles.
                    </small>
                </div>
            </div>
        `;
        Swal.fire({
            title: '<i class="fas fa-envelope" style="color: #3b82f6;"></i> Solicitud de Acceso',
            html: modalContent,
            background: '#0f172a',
            color: '#e2e8f0',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#475569',
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Enviar Solicitud',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            preConfirm: () => {
                const nombre = document.getElementById('nombre_apellidos').value.trim();
                const ci = document.getElementById('ci').value.trim();
                const telefono = document.getElementById('telefono').value.trim();
                const direccion = document.getElementById('direccion').value.trim();
                const area = document.getElementById('area').value.trim();
                const subsistema = document.getElementById('subsistema').value;
                if (!nombre) { Swal.showValidationMessage('❌ Por favor, ingrese su Nombre y Apellidos'); return false; }
                if (!ci) { Swal.showValidationMessage('❌ Por favor, ingrese su N° de Identidad (CI)'); return false; }
                if (!telefono) { Swal.showValidationMessage('❌ Por favor, ingrese su N° de Teléfono'); return false; }
                if (!direccion) { Swal.showValidationMessage('❌ Por favor, ingrese su Dirección'); return false; }
                if (!area) { Swal.showValidationMessage('❌ Por favor, ingrese su Área de trabajo'); return false; }
                if (!subsistema) { Swal.showValidationMessage('❌ Por favor, seleccione un Subsistema'); return false; }
                return { nombre, ci, telefono, direccion, area, subsistema };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const { nombre, ci, telefono, direccion, area, subsistema } = result.value;
                const currentYear = <?php echo date('Y'); ?>;
                const body = `%0D%0A%0D%0A--- DATOS DEL SOLICITANTE ---%0D%0A%0D%0A` +
                    `Nombre y Apellidos: ${encodeURIComponent(nombre)}%0D%0A` +
                    `N° de Identidad (CI): ${encodeURIComponent(ci)}%0D%0A` +
                    `N° de Teléfono: ${encodeURIComponent(telefono)}%0D%0A` +
                    `Dirección: ${encodeURIComponent(direccion)}%0D%0A` +
                    `Área de trabajo: ${encodeURIComponent(area)}%0D%0A%0D%0A` +
                    `--- DATOS DE LA SOLICITUD ---%0D%0A%0D%0A` +
                    `Subsistema al que solicita acceso: ${encodeURIComponent(subsistema)}%0D%0A%0D%0A` +
                    `--- INFORMACIÓN ADICIONAL ---%0D%0A%0D%0A` +
                    `Sistema de Gestión de Nóminas y Trabajadores%0D%0A` +
                    `<?php echo rawurlencode($SLOGAN); ?>%0D%0A%0D%0A` +
                    `Copyright © ${currentYear} UnicornioSoftware°%0D%0A`;
                const mailtoLink = `mailto:<?php echo $email_soporte_js; ?>?subject=SOLICITUD%20DE%20ACCESO%20-%20${encodeURIComponent(subsistema)}%20-%20${encodeURIComponent(nombre)}&body=${body}`;
                Swal.fire({
                    title: '<i class="fas fa-check-circle" style="color: 2;"></i> ¡Solicitud lista!',
                    html: `<p>Se abrirá su cliente de correo para enviar la solicitud.</p>`,
                    icon: 'success',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#22c55e',
                    confirmButtonText: '<i class="fas fa-envelope"></i> Abrir correo'
                }).then(() => { window.location.href = mailtoLink; });
            }
        });
    });
}

// ==========================================
// MANTENIMIENTO Y MODO PROGRAMADOR (solo si dbOk)
// ==========================================
function openMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeMaintenanceModal() {
    const modal = document.getElementById('maintenanceModal');
    if (modal) { modal.classList.remove('active'); document.body.style.overflow = 'auto'; }
}
function disableLoginAccess() {
    const btn = document.getElementById('btnLogin');
    const user = document.getElementById('username');
    const pass = document.getElementById('password');
    const forgotLink = document.querySelector('.forgot-link');
    if(btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Mantenimiento'; }
    if(user) user.disabled = true;
    if(pass) pass.disabled = true;
    if(forgotLink) forgotLink.classList.add('disabled-link');
}
function recheckMaintenanceStatus() {
    let btn = document.getElementById('btn-recheck-maint');
    let originalContent = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }
    fetch('login.php?action=check_status')
        .then(response => response.json())
        .then(data => {
            if (data.maintenance) {
                Swal.fire({ icon: 'info', title: 'Sistema en mantenimiento', text: 'El sistema continúa en mantenimiento.', background: '#0f172a', confirmButtonColor: '#ffc107' });
            } else {
                Swal.fire({ icon: 'success', title: '¡Mantenimiento finalizado!', text: 'El sistema está operativo nuevamente.', timer: 2000, showConfirmButton: false, background: '#0f172a' }).then(() => { location.reload(); });
            }
            if (btn) { btn.innerHTML = originalContent; btn.disabled = false; }
        })
        .catch(() => { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar.' }); if (btn) { btn.innerHTML = originalContent; btn.disabled = false; } });
}

if (dbOk) {
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isProgrammerBypass = urlParams.get('access_bypass') === 'true';
        if (!isProgrammerBypass) {
            fetch('login.php?action=check_status')
                .then(res => res.json())
                .then(data => { if (data.maintenance) { openMaintenanceModal(); disableLoginAccess(); } })
                .catch(e => console.error(e));
        } else {
            const badge = document.createElement('div');
            badge.innerHTML = '<i class="fa-solid fa-user-secret"></i> Modo Programador';
            badge.className = 'programmer-badge';
            document.body.appendChild(badge);
            const exitBadge = document.createElement('div');
            exitBadge.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i> Salir del modo';
            exitBadge.className = 'exit-badge';
            exitBadge.onclick = function() { window.location.replace(window.location.origin + window.location.pathname); };
            document.body.appendChild(exitBadge);
        }
    });
}

// ==========================================
// MOSTRAR ERRORES DE LOGIN CON SWEETALERT
// ==========================================
<?php if ($error && $db_ok): ?>
document.addEventListener('DOMContentLoaded', function() {
    let titulo = '', mensaje = '', icono = 'error', botonColor = '#ef4444';
    <?php if ($error === 'complete_campos'): ?>
        titulo = 'Campos Vacíos';
        mensaje = 'Por favor, complete todos los campos del formulario.';
        icono = 'warning';
        botonColor = '#f59e0b';
    <?php elseif ($error === 'credenciales_invalidas'): ?>
        titulo = 'Credenciales Incorrectas';
        mensaje = 'Usuario, carné o contraseña incorrectos. Verifique sus credenciales.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'error_sistema'): ?>
        titulo = 'Error del Sistema';
        mensaje = 'Ocurrió un error al procesar su solicitud. Intente más tarde.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'google_email_no_existe'): ?>
        titulo = 'Acceso Denegado';
        mensaje = 'Su cuenta de Google no está registrada en el sistema. Solicite su cuenta al administrador para poder acceder.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'google_no_configurado'): ?>
        titulo = 'No Disponible';
        mensaje = 'El inicio de sesión con Google aún no está configurado. Contacte al administrador del sistema.';
        icono = 'warning';
        botonColor = '#f59e0b';
    <?php elseif ($error === 'google_error'): ?>
        titulo = 'Error de Google';
        mensaje = 'No se pudo completar la autenticación con Google. Intente nuevamente.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'google_state_invalido'): ?>
        titulo = 'Solicitud Inválida';
        mensaje = 'La verificación de seguridad de Google falló. Vuelva a intentarlo.';
        icono = 'error';
        botonColor = '#ef4444';
    <?php elseif ($error === 'google_cancelado'): ?>
        titulo = 'Autenticación Cancelada';
        mensaje = 'Usted canceló el inicio de sesión con Google.';
        icono = 'warning';
        botonColor = '#f59e0b';
    <?php else: ?>
        titulo = 'Acceso Denegado';
        mensaje = '<?php echo addslashes($error); ?>';
        icono = 'error';
        botonColor = '#ef4444';
    <?php endif; ?>
    Swal.fire({
        title: '<i class="fas ' + (icono === 'error' ? 'fa-times-circle' : 'fa-exclamation-triangle') + '" style="color: ' + botonColor + '; font-size:2rem;"></i><br>' + titulo,
        html: '<p style="font-size:1rem;">' + mensaje + '</p>',
        icon: icono,
        background: '#0f172a',
        color: '#e2e8f0',
        confirmButtonColor: botonColor,
        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
        allowOutsideClick: false
    }).then(() => {
        <?php if ($error === 'credenciales_invalidas'): ?>
            document.getElementById('password').value = '';
            document.getElementById('password').focus();
        <?php endif; ?>
    });
});
<?php endif; ?>

// ==========================================
// LOGIN AJAX + MODAL BIENVENIDA
// ==========================================
if (dbOk) {
    const form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const userVal = document.getElementById('username') ? document.getElementById('username').value.trim() : '';
            const passVal = document.getElementById('password') ? document.getElementById('password').value.trim() : '';
            if (!userVal || !passVal) {
                e.preventDefault();
                let mensaje = '';
                if (!userVal && !passVal) mensaje = 'Por favor, complete todos los campos del formulario.';
                else if (!userVal) mensaje = 'El campo <strong>Usuario/Email/CI</strong> es obligatorio.';
                else if (!passVal) mensaje = 'El campo <strong>Contraseña</strong> es obligatorio.';
                Swal.fire({
                    title: '<i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size:2rem;"></i><br><?php echo addslashes($COMPANY_NAME); ?>',
                    html: '<p style="font-size:1rem;">' + mensaje + '</p>',
                    icon: 'warning',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: '<i class="fas fa-edit me-2"></i> Completar campos'
                });
                return false;
            }

            e.preventDefault();
            const btn = this.querySelector('.btn-login');
            const btnHtmlOriginal = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verificando...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('login.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) {
                    btn.innerHTML = btnHtmlOriginal;
                    btn.disabled = false;
                    let msg = 'Nombre de usuario y/o contraseña incorrectos.';
                    if (data.error === 'complete_campos') msg = 'Complete todos los campos.';
                    else if (data.error === 'credenciales_invalidas') msg = 'Nombre de usuario y/o contraseña incorrectos. Verifique sus credenciales.';
                    else if (data.error === 'error_sistema') msg = 'Ocurrió un error al procesar su solicitud. Intente más tarde.';
                    else if (data.error && data.error.includes('MANTENIMIENTO')) msg = data.error;
                    Swal.fire({
                        title: '<i class="fas fa-times-circle" style="color: #ef4444; font-size:2rem;"></i><br><?php echo addslashes($COMPANY_NAME); ?>',
                        html: '<p style="font-size:1rem;">' + msg + '</p>',
                        icon: 'error',
                        background: '#0f172a',
                        color: '#e2e8f0',
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: '<i class="fas fa-redo me-2"></i> Intentar de nuevo'
                    });
                    return;
                }

                const nombre = data.nombre || 'Usuario';
                const ci = data.ci || '';
                const rol = data.rol || 'Usuario';
                const foto = data.foto || '';
                const redirect = data.redirect || 'dashboard.php';
                const ultimoAcceso = data.ultimo_acceso;

                mostrarBienvenida({ nombre, ci, rol, foto, redirect, ultimo_acceso: ultimoAcceso });
            })
            .catch(() => {
                btn.innerHTML = btnHtmlOriginal;
                btn.disabled = false;
                Swal.fire({
                    title: '<i class="fas fa-wifi" style="color: #ef4444; font-size:2rem;"></i><br>Error de Conexión',
                    html: '<p style="font-size:1rem;">No se pudo conectar al servidor. Verifique su conexión e inténtelo de nuevo.</p>',
                    icon: 'error',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fas fa-redo me-2"></i> Reintentar'
                });
            });
        });
    }
}

// ==========================================
// MENSAJES DE SESIÓN EXPIRADA O CERRADA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['expired']) && $_GET['expired'] == 1 && $db_ok): ?>
    Swal.fire({
        html: `
            <div id="swal-expired-titlebar" style="position:absolute;top:0;left:0;right:0;height:2.25rem;background:linear-gradient(90deg,#1a1a2e,#16213e);border-bottom:0.0625rem solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;padding:0 0.625rem;border-radius:1rem 1rem 0 0;cursor:default;user-select:none;">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <i class="fas fa-clock-rotate-left" style="font-size:0.75rem;color:#f59e0b;"></i>
                    <span style="font-size:0.78rem;color:#94a3b8;font-weight:600;">Sesión expirada</span>
                </div>
                <div style="display:flex;gap:0.25rem;">
                    <button onclick="Swal.close()" style="width:1.75rem;height:1.75rem;border:none;background:transparent;color:#94a3b8;border-radius:0.25rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;font-size:0.85rem;" onmouseenter="this.style.background='rgba(239,68,68,0.25)';this.style.color='#ef4444'" onmouseleave="this.style.background='transparent';this.style.color='#94a3b8'"><i class="fas fa-xmark"></i></button>
                </div>
            </div>
            <div style="padding-top:2rem;text-align:center;">
                <div style="width:5rem;height:5rem;border-radius:50%;background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.05));display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;animation:expiredPulseIcon 2s ease-in-out infinite;border:0.125rem solid rgba(245,158,11,0.2);">
                    <i class="fas fa-clock-rotate-left" style="font-size:2.2rem;color:#f59e0b;"></i>
                </div>
                <div style="font-size:1.4rem;font-weight:800;color:#e2e8f0;margin-bottom:0.375rem;">Sesión expirada</div>
                <div style="font-size:0.88rem;color:#94a3b8;margin-bottom:1.25rem;">Su tiempo de sesión ha finalizado por seguridad</div>
                <div style="background:rgba(245,158,11,0.08);padding:0.75rem;border-radius:0.625rem;border:0.0625rem solid rgba(245,158,11,0.15);">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#f59e0b;font-weight:600;font-size:0.88rem;">
                        <i class="fas fa-shield-halved"></i>
                        <span><?php echo addslashes($COMPANY_NAME); ?></span>
                    </div>
                    <div style="font-size:0.78rem;color:#64748b;margin-top:0.25rem;">Inicie sesión nuevamente para continuar</div>
                </div>
            </div>
        `,
        background: 'rgba(10, 20, 30, 0.95)',
        backdrop: 'rgba(0,0,0,0.5)',
        showConfirmButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión',
        width: '28rem',
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: { popup: 'swal2-expired-popup' },
        didOpen: function() {
            var popup = Swal.getPopup();
            if (popup) {
                popup.style.border = '0.09375rem solid rgba(255,255,255,0.5)';
                popup.style.borderRadius = '1rem';
                popup.style.overflow = 'hidden';
                popup.style.paddingTop = '0';
            }
            var existing = document.getElementById('swal2-expired-keyframes');
            if (!existing) {
                var style = document.createElement('style');
                style.id = 'swal2-expired-keyframes';
                style.textContent = '@keyframes expiredPulseIcon{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(245,158,11,0.2)}50%{transform:scale(1.06);box-shadow:0 0 0 0.75rem rgba(245,158,11,0)}}.swal2-expired-popup .swal2-html-container{margin:0!important;padding:0.75rem 1.5rem 1rem!important;}.swal2-expired-popup .swal2-actions{margin-top:0.5rem!important;}.swal2-expired-popup .swal2-styled{border-radius:0.5rem!important;font-weight:600!important;padding:0.625rem 1.5rem!important;}';
                document.head.appendChild(style);
            }
        }
    });
    <?php elseif (isset($_GET['logout']) && $_GET['logout'] == 1 && $db_ok): ?>
    (function() {
        var userNombre = '<?php echo addslashes($_GET["user"] ?? "Usuario"); ?>';
        var ahora = new Date();
        var horaActual = ahora.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        var fechaActual = ahora.toLocaleDateString('es-VE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        Swal.fire({
            html: `
                <div id="swal-logout-titlebar" style="position:absolute;top:0;left:0;right:0;height:2.25rem;background:linear-gradient(90deg,#1a1a2e,#16213e);border-bottom:0.0625rem solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;padding:0 0.625rem;border-radius:1rem 1rem 0 0;cursor:default;user-select:none;">
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <i class="fas fa-right-from-bracket" style="font-size:0.75rem;color:#ef4444;"></i>
                        <span style="font-size:0.78rem;color:#94a3b8;font-weight:600;">Cerrar sesión</span>
                    </div>
                    <div style="display:flex;gap:0.25rem;">
                        <button onclick="Swal.close()" style="width:1.75rem;height:1.75rem;border:none;background:transparent;color:#94a3b8;border-radius:0.25rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;font-size:0.85rem;" onmouseenter="this.style.background='rgba(239,68,68,0.25)';this.style.color='#ef4444'" onmouseleave="this.style.background='transparent';this.style.color='#94a3b8'"><i class="fas fa-xmark"></i></button>
                    </div>
                </div>
                <div style="padding-top:2rem;text-align:center;">
                    <div style="width:5rem;height:5rem;border-radius:50%;background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(239,68,68,0.05));display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;animation:logoutPulseIcon 2s ease-in-out infinite;border:0.125rem solid rgba(239,68,68,0.2);">
                        <i class="fas fa-right-from-bracket" style="font-size:2.2rem;color:#ef4444;"></i>
                    </div>
                    <div style="font-size:0.9rem;color:#64748b;margin-bottom:0.25rem;">Sesión finalizada</div>
                    <div style="font-size:1.4rem;font-weight:800;color:#e2e8f0;margin-bottom:0.125rem;">Hasta pronto, ${userNombre}</div>
                    <div style="font-size:0.88rem;color:#94a3b8;margin-bottom:1.25rem;">Ha cerrado sesión correctamente</div>
                    <div style="background:rgba(255,255,255,0.04);border-radius:0.75rem;padding:0.875rem;margin-bottom:1.25rem;border:0.0625rem solid rgba(255,255,255,0.08);">
                        <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;font-size:0.85rem;color:#94a3b8;">
                            <span><i class="fa-regular fa-clock" style="color:#60a5fa;margin-right:0.375rem;"></i>${horaActual}</span>
                            <span><i class="fa-regular fa-calendar" style="color:#60a5fa;margin-right:0.375rem;"></i>${fechaActual}</span>
                        </div>
                    </div>
                    <div style="background:rgba(59,130,246,0.08);padding:0.75rem;border-radius:0.625rem;border:0.0625rem solid rgba(59,130,246,0.15);">
                        <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#60a5fa;font-weight:600;font-size:0.88rem;">
                            <i class="fas fa-shield-halved"></i>
                            <span><?php echo addslashes($COMPANY_NAME); ?></span>
                        </div>
                        <div style="font-size:0.78rem;color:#64748b;margin-top:0.25rem;">Sistema de Gestión de Nóminas</div>
                    </div>
                </div>
            `,
            background: 'rgba(10, 20, 30, 0.95)',
            backdrop: 'rgba(0,0,0,0.5)',
            showConfirmButton: true,
            confirmButtonColor: '#22c55e',
            confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión',
            width: '28rem',
            showCancelButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: { popup: 'swal2-logout-popup' },
            didOpen: function() {
                var popup = Swal.getPopup();
                if (popup) {
                    popup.style.border = '0.09375rem solid rgba(255,255,255,0.5)';
                    popup.style.borderRadius = '1rem';
                    popup.style.overflow = 'hidden';
                    popup.style.paddingTop = '0';
                    var existing = document.getElementById('swal2-logout-keyframes');
                    if (!existing) {
                        var style = document.createElement('style');
                        style.id = 'swal2-logout-keyframes';
                        style.textContent = '@keyframes logoutPulseIcon{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(239,68,68,0.2)}50%{transform:scale(1.06);box-shadow:0 0 0 0.75rem rgba(239,68,68,0)}}.swal2-logout-popup .swal2-html-container{margin:0!important;padding:0.75rem 1.5rem 1rem!important;}.swal2-logout-popup .swal2-actions{margin-top:0.5rem!important;}.swal2-logout-popup .swal2-styled{border-radius:0.5rem!important;font-weight:600!important;padding:0.625rem 1.5rem!important;}';
                        document.head.appendChild(style);
                    }
                }
            }
        });
    })();
    <?php elseif (isset($_GET['reset_ok']) && $_GET['reset_ok'] == 1 && $db_ok): ?>
    Swal.fire({
        html: `
            <div style="position:absolute;top:0;left:0;right:0;height:2.25rem;background:linear-gradient(90deg,#1a1a2e,#16213e);border-bottom:0.0625rem solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;padding:0 0.625rem;border-radius:1rem 1rem 0 0;cursor:default;user-select:none;">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <i class="fas fa-check-circle" style="font-size:0.75rem;color:#22c55e;"></i>
                    <span style="font-size:0.78rem;color:#94a3b8;font-weight:600;">Contraseña restablecida</span>
                </div>
                <div style="display:flex;gap:0.25rem;">
                    <button onclick="Swal.close()" style="width:1.75rem;height:1.75rem;border:none;background:transparent;color:#94a3b8;border-radius:0.25rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;font-size:0.85rem;" onmouseenter="this.style.background='rgba(239,68,68,0.25)';this.style.color='#ef4444'" onmouseleave="this.style.background='transparent';this.style.color='#94a3b8'"><i class="fas fa-xmark"></i></button>
                </div>
            </div>
            <div style="padding-top:2rem;text-align:center;">
                <div style="width:5rem;height:5rem;border-radius:50%;background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(34,197,94,0.05));display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;border:0.125rem solid rgba(34,197,94,0.2);">
                    <i class="fas fa-key" style="font-size:2.2rem;color:#22c55e;"></i>
                </div>
                <div style="font-size:1.4rem;font-weight:800;color:#e2e8f0;margin-bottom:0.375rem;">Contraseña actualizada</div>
                <div style="font-size:0.88rem;color:#94a3b8;margin-bottom:1.25rem;">Su contraseña ha sido restablecida correctamente.<br>Inicie sesión con su nueva contraseña.</div>
                <div style="background:rgba(59,130,246,0.08);padding:0.75rem;border-radius:0.625rem;border:0.0625rem solid rgba(59,130,246,0.15);">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#60a5fa;font-weight:600;font-size:0.88rem;">
                        <i class="fas fa-shield-halved"></i>
                        <span><?php echo addslashes($COMPANY_NAME); ?></span>
                    </div>
                    <div style="font-size:0.78rem;color:#64748b;margin-top:0.25rem;">Sistema de Gestión de Nóminas</div>
                </div>
            </div>
        `,
        background: 'rgba(10, 20, 30, 0.95)',
        backdrop: 'rgba(0,0,0,0.5)',
        showConfirmButton: true,
        confirmButtonColor: '#22c55e',
        confirmButtonText: '<i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión',
        width: '28rem',
        allowOutsideClick: false,
        customClass: { popup: 'swal2-reset-popup' },
        didOpen: function() {
            var popup = Swal.getPopup();
            if (popup) {
                popup.style.border = '0.09375rem solid rgba(255,255,255,0.5)';
                popup.style.borderRadius = '1rem';
                popup.style.overflow = 'hidden';
                popup.style.paddingTop = '0';
            }
            var existing = document.getElementById('swal2-reset-styles');
            if (!existing) {
                var style = document.createElement('style');
                style.id = 'swal2-reset-styles';
                style.textContent = '.swal2-reset-popup .swal2-html-container{margin:0!important;padding:0.75rem 1.5rem 1rem!important;}.swal2-reset-popup .swal2-actions{margin-top:0.5rem!important;}.swal2-reset-popup .swal2-styled{border-radius:0.5rem!important;font-weight:600!important;padding:0.625rem 1.5rem!important;}';
                document.head.appendChild(style);
            }
        }
    });
    <?php endif; ?>
    <?php if (isset($_GET['logout']) || isset($_GET['expired']) || isset($_GET['reset_ok'])): ?>
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname);
    }
    <?php endif; ?>
});

// ==========================================
// FUNCIÓN PARA COMPROBAR ESTADO DE LA BD
// ==========================================
function checkDatabaseStatus() {
    Swal.fire({
        title: 'Comprobando conexión...',
        html: '<div class="loading-spinner-small" style="margin:0 auto;"></div>',
        background: '#0f172a',
        color: '#e2e8f0',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    
    fetch('login.php?action=check_status')
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.db_ok) {
                Swal.fire({
                    title: '✅ Base de datos conectada',
                    text: 'MySQL está funcionando correctamente. La página se recargará para activar el sistema.',
                    icon: 'success',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#22c55e',
                    confirmButtonText: '<i class="fas fa-sync-alt"></i> Recargar ahora'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: '❌ MySQL desconectado',
                    html: `<div style="text-align: left;">
                            <p><strong>Error:</strong> ${dbErrorMsg || 'No se puede establecer conexión'}</p>
                            <p>📱 <strong>Móvil Empresarial:</strong> <a href="tel:+5359860773" style="color:#60a5fa;">+53 5 2712861</a><br>
                            ✉️ <strong>Email:</strong> <a href="mailto:<?php echo $email_soporte_js; ?>" style="color:#60a5fa;"><?php echo htmlspecialchars($email_soporte, ENT_QUOTES, 'UTF-8'); ?></a></p>
                            </div>`,
                    icon: 'error',
                    background: '#0f172a',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fas fa-tools"></i> Entendido',
                    showCancelButton: true,
                    cancelButtonColor: '#3b82f6',
                    cancelButtonText: '<i class="fas fa-sync-alt"></i> Reintentar'
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        checkDatabaseStatus(); // reintentar
                    }
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                title: 'Error de red',
                text: 'No se pudo verificar el estado. Intente más tarde.',
                icon: 'error',
                background: '#0f172a',
                color: '#e2e8f0',
                confirmButtonColor: '#ef4444'
            });
        });
}

// Asociar al botón si existe (se puede agregar un botón flotante si se desea, pero por ahora se usa solo en alerta)
 document.getElementById('btnCheckDb')?.addEventListener('click', checkDatabaseStatus);
</script>

<script>
// Barra de título del login: colapsar/expandir, salir y drag & drop
(function () {
    var card = document.getElementById('loginCard');
    var wrap = document.getElementById('loginWrap');
    var titlebar = document.getElementById('loginTitlebar');
    var btnCollapse = document.getElementById('btnCollapseLogin');
    var btnClose = document.getElementById('btnCloseLogin');
    var collapseIcon = document.getElementById('collapseIcon');

    if (btnCollapse) {
        btnCollapse.addEventListener('click', function (e) {
            e.preventDefault();
            var collapsed = card.classList.toggle('collapsed');
            if (collapseIcon) {
                collapseIcon.className = collapsed ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            }
            btnCollapse.setAttribute('data-tooltip', collapsed ? 'Expandir' : 'Colapsar');
            var userInput = document.getElementById('username');
            if (userInput && !userInput.disabled) {
                userInput.focus();
            }
        });
    }

    if (btnClose) {
        btnClose.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/nominas/index.php';
        });
    }

    // Doble click en la barra de título para colapsar/expandir
    if (titlebar) {
        titlebar.addEventListener('dblclick', function (e) {
            if (e.target.closest('.titlebar-btn')) return;
            var collapsed = card.classList.toggle('collapsed');
            if (collapseIcon) {
                collapseIcon.className = collapsed ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            }
            btnCollapse.setAttribute('data-tooltip', collapsed ? 'Expandir' : 'Colapsar');
            var userInput = document.getElementById('username');
            if (userInput && !userInput.disabled && !collapsed) {
                userInput.focus();
            }
        });
    }

    // Drag & drop de la tarjeta mediante la barra de título
    if (titlebar) {
        var container = wrap || card;
        var startX = 0, startY = 0, origL = 0, origT = 0, dragging = false;

        titlebar.addEventListener('mousedown', function (e) {
            if (e.target.closest('.titlebar-btn')) return;
            e.preventDefault();
            // Posición actual real del contenedor (relativa al viewport)
            var r = container.getBoundingClientRect();
            origL = r.left;
            origT = r.top;
            startX = e.clientX;
            startY = e.clientY;
            dragging = true;
            titlebar.classList.add('dragging');
            card.classList.add('dragging');
            document.body.style.cursor = 'grabbing';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            var r = container.getBoundingClientRect();
            var w = r.width || container.offsetWidth;
            var h = r.height || container.offsetHeight;
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var newL = origL + dx;
            var newT = origT + dy;
            newL = Math.max(0, Math.min(newL, vw - w));
            newT = Math.max(0, Math.min(newT, vh - h));
            container.style.position = 'fixed';
            container.style.left = newL + 'px';
            container.style.top = newT + 'px';
            container.style.margin = '0';
            container.style.zIndex = '2';
        });

        document.addEventListener('mouseup', function () {
            if (!dragging) return;
            dragging = false;
            titlebar.classList.remove('dragging');
            card.classList.remove('dragging');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        });
    }
})();
</script>
</body>
</html>