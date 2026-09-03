<?php
// modules/configuracion.php - Configuraciones del Sistema
require_once '../config/database.php';
require_once '../config/mail.php';
require_once '../includes/funciones.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Control de acceso por rol
if (!permiso_puede('configuracion', 'ver')) {
    permiso_denegar_acceso('Configuración');
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '';

$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// ============================================
// AJAX: PROBAR CONFIGURACIÓN SMTP
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'probar_mail') {
    require_once '../config/mail.php';
    header('Content-Type: application/json');

    $prov = getProveedoresSMTP();
    $cfg = [
        'activo'     => 1,
        'proveedor'  => trim($_POST['mail_proveedor'] ?? 'custom'),
        'host'       => trim($_POST['mail_host'] ?? ''),
        'port'       => (int)($_POST['mail_port'] ?? 587),
        'encryption' => trim($_POST['mail_encryption'] ?? 'tls'),
        'usuario'    => trim($_POST['mail_usuario'] ?? ''),
        'password'   => trim($_POST['mail_password'] ?? ''),
        'from'       => trim($_POST['mail_from'] ?? ''),
        'from_name'  => trim($_POST['mail_from_name'] ?? ''),
    ];
    if ($cfg['proveedor'] !== 'custom' && isset($prov[$cfg['proveedor']])) {
        if (empty($cfg['host']))       $cfg['host']       = $prov[$cfg['proveedor']]['host'];
        if (empty($cfg['port']))       $cfg['port']       = $prov[$cfg['proveedor']]['puerto'];
        if (empty($cfg['encryption'])) $cfg['encryption'] = $prov[$cfg['proveedor']]['encriptacion'];
    }

    if (empty($cfg['host']) || empty($cfg['usuario'])) {
        echo json_encode(['success' => false, 'message' => 'Complete el servidor SMTP y el usuario antes de probar.']);
        exit;
    }

    $to = !empty($cfg['usuario']) ? $cfg['usuario'] : $cfg['from'];
    $empresa = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom';

    $etiquetaCifrado = ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'Sin cifrado'][$cfg['encryption']] ?? strtoupper($cfg['encryption']);
    $servidor = !empty($cfg['host']) ? htmlspecialchars($cfg['host']) : '—';
    $puerto = (int)$cfg['port'] ?: '—';
    $usuario = !empty($cfg['usuario']) ? htmlspecialchars($cfg['usuario']) : '—';
    $remitente = !empty($cfg['from']) ? htmlspecialchars($cfg['from']) : $usuario;
    $auth = 'Sí';

    $htmlCorreo = '
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes"></head>
    <body style="margin:0;padding:0;background:linear-gradient(145deg,#f6f9fc 0%,#e6f0f5 100%);font-family:Segoe UI, Roboto, Arial, sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(145deg,#f6f9fc 0%,#e6f0f5 100%);padding:1.875rem 0.75rem;">
            <tr><td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:38.75rem;width:100%;background:rgba(255,255,255,0.9);border-radius:3rem;padding:2.5rem 2rem;box-shadow:0 1.5625rem 3.125rem -0.75rem rgba(0,20,30,0.35);border:0.0625rem solid rgba(255,255,255,0.5);">
                    <tr>
                        <td style="text-align:center;padding-bottom:0.5rem;">
                            <div style="width:4.5rem;height:4.5rem;margin:0 auto 1rem;border-radius:50%;background:linear-gradient(145deg,#2f7b9c,#1f5b77);display:block;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="font-size:2.125rem;line-height:4.5rem;color:#ffffff;">&#128231;</td></tr></table>
                            </div>
                            <h1 style="margin:0;font-size:2rem;font-weight:600;color:#0b2b3b;letter-spacing:-0.01em;">Prueba SMTP</h1>
                            <p style="margin:0.5rem 0 0;font-size:0.9375rem;color:#2c5a72;">Verifica la conexi&#243;n y el env&#237;o con tu servidor de correo</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:1.25rem 0.25rem 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:rgba(63,152,188,0.08);border-radius:1.25rem;border-left:0.25rem solid #3d8eb0;padding:1.125rem 1.25rem;">
                                <tr>
                                    <td style="color:#134157;font-size:0.9375rem;line-height:1.7;">
                                        <strong style="color:#1d4b60;">&#9989; &#161;Envio exitoso!</strong> El correo de prueba se envi&#243; correctamente. Tu aplicaci&#243;n ya puede usar este servidor SMTP para el env&#237;o de correos.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:1.5rem 0.25rem 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#1d4b60;padding-bottom:0.75rem;">Configuraci&#243;n verificada</td>
                                </tr>
                                <tr>
                                    <td style="padding:0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:0.0625rem solid #d6e4ec;border-radius:1.375rem;overflow:hidden;">
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;width:40%;">Servidor SMTP</td>
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.875rem;color:#0f2b38;font-weight:600;">' . $servidor . '</td>
                                            </tr>
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;">Puerto</td>
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.875rem;color:#0f2b38;font-weight:600;">' . $puerto . '</td>
                                            </tr>
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;">Seguridad</td>
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.875rem;color:#0f2b38;font-weight:600;">' . $etiquetaCifrado . '</td>
                                            </tr>
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;">Usuario</td>
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.875rem;color:#0f2b38;">' . $usuario . '</td>
                                            </tr>
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;">Remitente</td>
                                                <td style="padding:0.875rem 1.125rem;border-bottom:0.0625rem solid #e8f0f5;font-size:0.875rem;color:#0f2b38;">' . $remitente . '</td>
                                            </tr>
                                            <tr style="background:#ffffff;">
                                                <td style="padding:0.875rem 1.125rem;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;color:#30758f;">Autenticaci&#243;n</td>
                                                <td style="padding:0.875rem 1.125rem;font-size:0.875rem;color:#1a7a4b;font-weight:600;">&#9989; ' . $auth . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:1.75rem 0.25rem 0;text-align:center;font-size:0.75rem;color:#4f7d94;border-top:0.0625rem solid rgba(0,0,0,0.05);">
                            <span>&#128737;</span> Las credenciales no se almacenan en este correo &middot; ' . $empresa . ' &middot; Correo autom&#225;tico de prueba
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';

    $res = enviarCorreoConConfig($cfg, $to, $cfg['from_name'], '✅ Prueba SMTP - ' . $empresa, $htmlCorreo, '');
    echo json_encode(['success' => $res['success'], 'message' => $res['success'] ? 'Correo de prueba enviado a ' . $to : 'Error SMTP: ' . $res['error']]);
    exit;
}


// Configuración empresa
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas', 'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH'];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_gestionRRHH')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestionRRHH') $config_empresa['especialista_gestionRRHH'] = $row['valor'];
    }
} catch (PDOException $e) {}

// Ruta del logo
$ruta_logo = '../../images/logocorto.png';
$logo_base64 = '';
if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

// Procesar guardado de configuración
// Tras un guardado exitoso se hace redirect (PRG) con el mensaje en la URL
$mensaje = $_GET['msg'] ?? '';
$tipo_mensaje = $_GET['tipo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_config_general'])) {
        $params = [
            'horas_mensuales', 'dias_mensuales', 'horas_jornada_diaria',
            'tasa_contribucion_especial', 'nombre_empresa', 'direccion_empresa',
            'reeup_empresa', 'nit_empresa', 'jefe_proyecto', 'especialista_gestion', 
            'salario_minimo', 'intendente', 'recargo_nocturno', 'especialista_gestionRRHH',
            'tarifa_nocturnidad_temprana', 'tarifa_nocturnidad_tardia'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            $mensaje = "Configuración general guardada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_datos_entidad'])) {
        $params = [
            'nombre_empresa', 'direccion_empresa',
            'reeup_empresa', 'nit_empresa', 'jefe_proyecto', 'especialista_gestion', 
            'intendente', 'especialista_gestionRRHH',
            'slogan', 'telefono_empresa', 'email_empresa', 'telefono_soporte', 'email_soporte'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            $mensaje = "Datos de la entidad guardados correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_rangos'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM configuracion_rangos_impuesto WHERE fecha_vigencia = ?");
            $stmt->execute([$_POST['fecha_vigencia']]);
            
            $rangos = $_POST['rangos'];
            foreach ($rangos as $rango) {
                if ($rango['desde'] !== '' && $rango['tasa'] !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO configuracion_rangos_impuesto (desde, hasta, tasa, monto_fijo, fecha_vigencia, descripcion)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $rango['desde'],
                        $rango['hasta'] ?: null,
                        $rango['tasa'] / 100,
                        $rango['monto_fijo'] ?? 0,
                        $_POST['fecha_vigencia'],
                        $rango['descripcion'] ?? ''
                    ]);
                }
            }
            $mensaje = "Rangos de impuesto actualizados correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar rangos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['eliminar_tasa'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM configuracion_tasas WHERE id = ?");
            $stmt->execute([$_POST['tasa_id']]);
            $mensaje = "Tasa eliminada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar tasa: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['agregar_tasa'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO configuracion_tasas (nombre_tasa, valor, fecha_vigencia, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nombre_tasa'],
                $_POST['valor_tasa'],
                $_POST['fecha_vigencia_tasa'],
                $_POST['descripcion_tasa']
            ]);
            $mensaje = "Tasa agregada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al agregar tasa: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_config_mail'])) {
        require_once '../config/mail.php';
        asegurarParamsMail($pdo);
        $params = [
            'mail_activo'     => (($_POST['mail_activo'] ?? '0') === '1') ? '1' : '0',
            'mail_proveedor'  => trim($_POST['mail_proveedor'] ?? 'custom'),
            'mail_host'       => trim($_POST['mail_host'] ?? ''),
            'mail_port'       => trim($_POST['mail_port'] ?? '587'),
            'mail_encryption' => trim($_POST['mail_encryption'] ?? 'tls'),
            'mail_usuario'    => trim($_POST['mail_usuario'] ?? ''),
            'mail_password'   => trim($_POST['mail_password'] ?? ''),
            'mail_from'       => trim($_POST['mail_from'] ?? ''),
            'mail_from_name'  => trim($_POST['mail_from_name'] ?? ''),
        ];
        
        try {
            foreach ($params as $param => $valor) {
                $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                $stmt->execute([$valor, $param]);
            }
            $mensaje = "Configuración de correo guardada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar configuración de correo: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// PRG (Post/Redirect/Get): tras un guardado exitoso, recargar la página para
// que los nuevos valores se apliquen (constantes, formularios, etc.)
if ($tipo_mensaje === 'success' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $botones_refresh = ['guardar_config_general', 'guardar_datos_entidad', 'guardar_rangos', 'guardar_config_mail'];
    foreach ($botones_refresh as $b) {
        if (isset($_POST[$b])) {
            $url = strtok($_SERVER['REQUEST_URI'], '?');
            header('Location: ' . $url . '?msg=' . urlencode($mensaje) . '&tipo=success');
            exit;
        }
    }
}

// Obtener configuración actual
$config = [];
$stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general");
while ($row = $stmt->fetch()) {
    $config[$row['parametro']] = $row['valor'];
}

// Configuración de correo (SMTP)
$config_mail = [
    'activo'     => $config['mail_activo'] ?? '0',
    'proveedor'  => $config['mail_proveedor'] ?? 'custom',
    'host'       => $config['mail_host'] ?? '',
    'port'       => $config['mail_port'] ?? '587',
    'encryption' => $config['mail_encryption'] ?? 'tls',
    'usuario'    => $config['mail_usuario'] ?? '',
    'password'   => $config['mail_password'] ?? '',
    'from'       => $config['mail_from'] ?? '',
    'from_name'  => $config['mail_from_name'] ?? '',
];
$proveedores_smtp = getProveedoresSMTP();

// Obtener rangos de impuesto vigentes
$rangos_impuesto = $pdo->query("
    SELECT * FROM configuracion_rangos_impuesto 
    WHERE fecha_vigencia = (SELECT MAX(fecha_vigencia) FROM configuracion_rangos_impuesto)
    ORDER BY desde
")->fetchAll();

$fecha_vigencia_actual = !empty($rangos_impuesto) ? $rangos_impuesto[0]['fecha_vigencia'] : date('Y-m-d');

// Obtener tasas
$tasas = $pdo->query("SELECT * FROM configuracion_tasas ORDER BY fecha_vigencia DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?> | Configuración</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/cropper.css">
    
    <style>
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

        .win11-bg {
            position: fixed; top:0; left:0; width:100%; height:100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top:0; left:0; width:100%; height:100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .glass-card {
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        .win-sidebar {
            position: fixed; left:0; top:0; height:100vh; width:16.25rem;
            background: var(--panel); backdrop-filter: blur(1.875rem);
            border-right: 0.0625rem solid rgba(255, 255, 255, 0.08); z-index: 1000;
            transition: all 0.3s ease; display: flex; flex-direction: column;
        }
        .win-sidebar.collapsed { width:5rem; }
        .win-sidebar.collapsed .sidebar-text, .win-sidebar.collapsed .sidebar-expand-only { display: none; }
        .win-sidebar.collapsed .nav-item { justify-content: center; padding:0.75rem; }
        .win-sidebar.collapsed .nav-item i { margin:0; font-size:1.5rem; }

        .sidebar-logo { padding:1.5rem 1.25rem; border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08); margin-bottom:1.25rem; text-align: center; }
        .sidebar-logo h3 { font-size:1.2rem; font-weight: 600; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin:0; }
        .sidebar-logo small { font-size:0.7rem; color: rgba(255, 255, 255, 0.5); }

        .sidebar-nav { flex: 1; padding:0 0.75rem; }
        .nav-item {
            display: flex; align-items: center; gap:0.875rem; padding:0.75rem 1rem;
            margin-bottom:0.375rem; border-radius: 0.75rem;
            color: rgba(255, 255, 255, 0.7); transition: all 0.2s;
            cursor: pointer; text-decoration: none;
        }
        .nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }
        .nav-item.active { background: rgba(0, 120, 212, 0.2); color: #60a5fa; border-left: 0.1875rem solid #60a5fa; }
        .nav-item i { width:1.5rem; font-size:1.2rem; text-align: center; }
        .nav-item span { font-size:0.9rem; font-weight: 500; }

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        /* User Menu & Dropdowns */
/* ========================================== */
/* FIX: Menú de usuario por encima de todo    */
/* ========================================== */

.user-menu {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-toggle {
    position: relative !important;
    z-index: 9999 !important;
}

.user-menu .dropdown-menu {
    z-index: 99999 !important;
    position: absolute !important;
    top:100% !important;
    right:0 !important;
    left:auto !important;
    min-width:13.75rem !important;
    background: rgba(32, 32, 40, 0.98) !important;
    backdrop-filter: blur(1.25rem) !important;
    border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 0.75rem !important;
    padding:0.5rem !important;
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.5) !important;
}

/* Asegurar que el topbar no interfiera */
.win-topbar {
    position: relative !important;
    z-index: 100 !important;
}

/* Asegurar que el main-container no bloquee el dropdown */
.main-container {
    position: relative !important;
    z-index: 1 !important;
}

/* Para el botón del avatar */
.user-avatar {
    position: relative !important;
    z-index: 9999 !important;
}
        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1);
            border-radius: 0.625rem; color: white; font-size:0.85rem; transition: all 0.2s;
            cursor: pointer; padding:0.5rem 1rem; display: inline-flex; align-items: center; gap:0.5rem;
            text-decoration: none;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-0.0625rem); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-0.0625rem); }
        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }
        .btn-win-success { background: rgba(var(--color-success-rgb), 0.2); border-color: rgba(var(--color-success-rgb), 0.5); }
        .btn-win-success:hover { background: rgba(var(--color-success-rgb), 0.4); border-color: var(--color-success); }
        .btn-win-warning { background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5); }
        .btn-win-warning:hover { background: rgba(245, 158, 11, 0.4); border-color: #f59e0b; }

        .form-label { color: rgba(255, 255, 255, 0.85); font-size:0.8rem; font-weight: 500; margin-bottom:0.375rem; }
        
        .form-control, .form-select, input.form-control, textarea.form-control, select.form-select {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 0.625rem !important;
            color: #ffffff !important;
            padding:0.5rem 0.75rem !important;
            font-size:0.85rem !important;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--panel) !important;
            border-color: #60a5fa !important;
            outline: none !important;
            box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2) !important;
            color: #ffffff !important;
        }
        
        .input-group-text {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* Iconos nativos de fecha/hora visibles en tema oscuro */
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
            opacity: 0.9;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover,
        input[type="time"]::-webkit-calendar-picker-indicator:hover,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        /* ========================================== */
        /* ESTILOS OSCUROS PARA TABLAS - DARK THEME   */
        /* ========================================== */
        
        .table-responsive {
            border-radius: 0.75rem;
            overflow-x: auto;
            background: rgba(15, 15, 20, 0.4);
            border: 0.0625rem solid rgba(255, 255, 255, 0.05);
        }
        
        #rangosTable, #tasasTable {
            background: transparent !important;
            margin-bottom:0;
        }
        #rangosTable thead th, #tasasTable thead th {
            background: rgba(22, 22, 30, 0.98) !important;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
            color: rgba(255, 255, 255, 0.9) !important;
            font-size:0.7rem !important;
            text-transform: uppercase !important;
            letter-spacing:0.0312rem !important;
            padding:0.75rem 0.5rem !important;
            font-weight: 600 !important;
            position: sticky;
            top:0;
            z-index: 10;
        }
        #rangosTable tbody tr, #tasasTable tbody tr {
            background: var(--panel-2) !important;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.05) !important;
        }
        #rangosTable tbody tr:hover, #tasasTable tbody tr:hover {
            background: rgba(96, 165, 250, 0.1) !important;
        }
        #rangosTable td, #tasasTable td {
            padding:0.625rem 0.5rem !important;
            vertical-align: middle !important;
            border: none !important;
            background: transparent !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        #rangosTable .form-control-sm {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 0.5rem !important;
            color: #ffffff !important;
            padding:0.375rem 0.625rem !important;
            font-size:0.75rem !important;
        }
        #rangosTable .btn-win-danger, #tasasTable .btn-win-danger {
            background: rgba(220, 53, 69, 0.2) !important;
            border: 0.0625rem solid rgba(220, 53, 69, 0.5) !important;
            border-radius: 0.375rem !important;
            padding:0.25rem 0.5rem !important;
            color: #f87171 !important;
        }
        #rangosTable .btn-win-danger:hover, #tasasTable .btn-win-danger:hover {
            background: rgba(220, 53, 69, 0.4) !important;
            color: #ffffff !important;
        }
        #rangosTable tfoot td {
            background: var(--panel) !important;
            padding:0.75rem 0.5rem !important;
            border-top: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
        }
        #rangosTable .btn-win-sm {
            background: rgba(59, 130, 246, 0.2) !important;
            border: 0.0625rem solid rgba(59, 130, 246, 0.5) !important;
            color: #60a5fa !important;
            padding:0.3125rem 0.75rem !important;
            font-size:0.7rem !important;
            border-radius: 0.5rem !important;
        }
        
        /* Avatar preview */
        .avatar-preview {
            width:6.25rem;
            height:6.25rem;
            border-radius: 50%;
            border: 0.1875rem solid #60a5fa;
            margin:0 auto;
            overflow: hidden;
            background: var(--panel);
            position: relative;
            cursor: pointer;
        }
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height:100%;
            width:100%;
            flex-direction: column;
            font-size:2rem;
            color: rgba(255, 255, 255, 0.4);
        }
        .edit-overlay {
            position: absolute;
            bottom:0;
            left:0;
            right:0;
            background: rgba(0, 0, 0, 0.75);
            color: white;
            text-align: center;
            padding:0.5rem;
            font-size:0.5625rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0 0 50% 50%;
        }
        .avatar-preview:hover .edit-overlay { opacity: 1; }
        
        /* Crop modal */
        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 1.25rem 1.25rem;
            min-height:25rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .preview-container {
            width:9.375rem;
            height:9.375rem;
            margin:0 auto;
            overflow: hidden;
            border: 0.1875rem solid #60a5fa;
            border-radius: 50%;
            background: #2d2d2d;
        }
        
        .alert-success { background: rgba(var(--color-success-rgb), 0.2); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.3); color: var(--color-success); border-radius: 0.625rem; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 0.0625rem solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 0.625rem; }
        .alert-info { background: rgba(59, 130, 246, 0.2); border: 0.0625rem solid rgba(59, 130, 246, 0.3); color: #60a5fa; border-radius: 0.625rem; }
        .alert-warning { background: rgba(245, 158, 11, 0.15); border: 0.0625rem solid rgba(245, 158, 11, 0.3); color: #fbbf24; border-radius: 0.625rem; }
        
        .modal-content-win {
            background: linear-gradient(135deg, var(--card), var(--panel-2));
            backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.25rem;
            color: var(--txt);
        }
        .modal-header-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.125rem 1.125rem 0 0;
        }
        .modal-footer-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-top: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 0 0 1.125rem 1.125rem;
        }
        
        .date-badge {
            background: rgba(255, 255, 255, 0.08);
            padding:0.5rem 1rem;
            border-radius: 0.75rem;
            font-size:0.85rem;
            color: #ffffff;
        }
        #liveClock { display: inline-block; min-width:5.3125rem; text-align: center; }
        
        .text-muted, .text-secondary { color: #9ca3af !important; }
        .text-white-50 { color: rgba(255, 255, 255, 0.5) !important; }
        
        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        
        hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        
        .swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }
        .swal2-styled.swal2-confirm { background-color: var(--color-success) !important; }
        .swal2-styled.swal2-cancel { background-color: #6b7280 !important; }
        
        .search-box { position: relative; }
        .search-box i { position: absolute; left:0.75rem; top:50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); z-index: 10; }
        .search-box input { padding-left:2rem !important; }
		#backupNamePreview {
			background: rgba(96, 165, 250, 0.1);
			padding:0.125rem 0.5rem;
			border-radius: 0.25rem;
			font-family: monospace;
			font-size:0.85rem;
		}

		#backupNameModal .form-check-input:checked {
			background-color: var(--color-success);
			border-color: var(--color-success);
		}

		#backupNameModal .form-check-label {
			cursor: pointer;
		}
::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    opacity: 1 !important;
}

:-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

::-ms-input-placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
}

/* Para inputs específicos que puedan necesitar más visibilidad */
.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4) !important;
}

.form-control-sm::placeholder {
    color: rgba(255, 255, 255, 0.35) !important;
    font-size:0.75rem !important;
}

        /* ========================================== */
        /* MODAL USUARIO MEJORADO - DISEÑO Y UX       */
        /* ========================================== */

        .modal-avatar-header {
            width:3.5rem; height:3.5rem; border-radius: 50%;
            border: 0.125rem solid #60a5fa; overflow: hidden;
            background: var(--panel); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .modal-avatar-header img { width:100%; height:100%; object-fit: cover; display: block; }
        .modal-avatar-header .avatar-iniciales-lg {
            width:100%; height:100%; font-size:1.15rem; font-weight: 700;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center; color: #fff;
        }

        .mode-badge {
            display: inline-flex; align-items: center; gap:0.375rem;
            padding:0.1875rem 0.625rem; border-radius: 1.25rem; font-size:0.66rem;
            font-weight: 600; letter-spacing:0.0312rem; text-transform: uppercase;
            white-space: nowrap;
        }
        .mode-badge.create { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success-soft); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.4); }
        .mode-badge.edit { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 0.0625rem solid rgba(59, 130, 246, 0.4); }

        .modal-subtitle { font-size:0.75rem; color: rgba(255, 255, 255, 0.5); }

        .section-title {
            display: flex; align-items: center; gap:0.5rem;
            font-size:0.78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing:0.05rem; color: rgba(255, 255, 255, 0.75);
            padding-bottom:0.5rem; margin-bottom:1rem;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08);
        }
        .section-title i { color: #60a5fa; font-size:0.85rem; }

        .card-collapse-title {
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap:0.5rem;
            transition: color 0.2s ease;
        }
        .card-collapse-title:hover {
            color: #60a5fa;
        }
        .card-collapse-title:hover .collapse-chevron {
            color: #60a5fa;
        }
        .card-collapse-title .collapse-chevron {
            font-size:0.7rem;
            color: rgba(255, 255, 255, 0.4);
            transition: transform 0.25s ease;
        }
        .card-collapse-title:not(.collapsed) .collapse-chevron {
            transform: rotate(180deg);
            color: #60a5fa;
        }

        .usuario-modal-body {
            max-height:calc(100vh - 16.25rem);
            overflow-y: auto;
        }
        .usuario-modal-body::-webkit-scrollbar { width:0.375rem; }
        .usuario-modal-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 0.625rem; }

        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right:2.625rem !important; }
        .password-toggle {
            position: absolute; right:0.375rem; top:50%; transform: translateY(-50%);
            background: transparent; border: none; color: rgba(255, 255, 255, 0.5);
            padding:0.375rem 0.5rem; cursor: pointer; z-index: 5;
        }
        .password-toggle:hover { color: #60a5fa; }

        .form-usuario-status {
            display: flex; align-items: center; gap:0.5rem;
            padding:0.625rem 0.875rem; border-radius: 0.625rem; font-size:0.78rem;
            margin-bottom:1.125rem;
        }
        .form-usuario-status.edit { background: rgba(59, 130, 246, 0.1); border: 0.0625rem solid rgba(59, 130, 246, 0.25); color: #93c5fd; }
        .form-usuario-status.create { background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.25); color: var(--color-success-soft); }

        .btn-guardar-loading { pointer-events: none; opacity: 0.7; }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-cog me-2" style="color: #60a5fa;"></i>Configuración del Sistema</h1>
                <p><i class="fas fa-sliders-h me-1"></i> Parámetros generales y configuración de impuestos</p>
            </div>
            <button type="button" class="btn-win btn-win-sm ms-auto" id="btnToggleAllCards" title="Expandir o colapsar todas las secciones" data-tooltip="Expandir o colapsar todas las secciones" data-tooltip-theme="primary">
                <i class="fas fa-expand-alt me-2"></i><span id="btnToggleAllCardsTexto">Expandir todo</span>
            </button>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Mensajes de alerta -->
    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show mb-4 fade-in-up" role="alert">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" title="Cerrar notificación" data-tooltip="Cerrar notificación" data-tooltip-theme="danger"></button>
    </div>
    <?php endif; ?>

    <!-- Recomendación de backup -->
    <div class="alert alert-info alert-dismissible fade show mb-4 fade-in-up" id="alertBackupRecomendacion" style="background: rgba(59, 130, 246, 0.12); border: 0.0625rem solid rgba(59, 130, 246, 0.35); border-radius: 0.75rem;">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" onclick="localStorage.setItem('backupAlertClosedV2', 'true');" title="Cerrar notificación" data-tooltip="Cerrar notificación" data-tooltip-theme="danger"></button>
        <div class="d-flex align-items-start gap-3">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:2.25rem; height:2.25rem; border-radius: 0.625rem; background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-size:1rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <strong style="color: var(--accent-dark);">Recomendación:</strong>
                <span style="color: var(--muted);">Realice un backup antes de hacer cambios importantes en la configuración o antes de restaurar datos.</span>
            </div>
        </div>
    </div>
    <script>
        if (localStorage.getItem('backupAlertClosedV2') === 'true') {
            document.getElementById('alertBackupRecomendacion').style.display = 'none';
        }
    </script>
    
<!-- Sección de Base de Datos -->
<div class="row g-4 mb-4">
    <div class="col-12 fade-in-up" style="animation-delay: 0.02s;">
        <div class="glass-card">
            <div class="p-3 border-bottom border-white-10">
                <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseDB" aria-expanded="false" aria-controls="collapseDB">
                    <i class="fas fa-chevron-down collapse-chevron"></i>
                    <i class="fas fa-database me-1" style="color: #f59e0b;"></i> 
                    Base de Datos (SALVAS Y RESTAURAS)
                </h6>
				
            </div>
            <div id="collapseDB" class="collapse">
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.2);">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <i class="fas fa-download fa-2x mb-2" style="color: var(--color-success);"></i>
                                    <h6 class="mb-1">Salvar Base de Datos</h6>
                                    <small class="text-white-50">Crear copia de seguridad completa (Backup ZIP)</small>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-win btn-win-success" id="btnBackupManualCard" title="Generar backup del sistema" data-tooltip="Generar backup del sistema" data-tooltip-theme="info">
                                        <i class="fas fa-download me-2"></i> Generar Backup
                                    </button>
                                    <button class="btn-win btn-win-primary" id="btnBackupWithNameCard" data-bs-toggle="modal" data-bs-target="#backupNameModal" title="Backup con nombre personalizado" data-tooltip="Backup con nombre personalizado" data-tooltip-theme="info">
                                        <i class="fas fa-file-export me-2"></i> Backup con nombre
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(245, 158, 11, 0.1); border: 0.0625rem solid rgba(245, 158, 11, 0.2);">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <i class="fas fa-upload fa-2x mb-2" style="color: #f59e0b;"></i>
                                    <h6 class="mb-1">Restaurar Base de Datos</h6>
                                    <small class="text-white-50">Importar backup SQL/ZIP</small>
                                </div>
                                <button class="btn-win btn-win-warning" id="btnRestoreBackupCard" title="Restaurar base de datos" data-tooltip="Restaurar base de datos" data-tooltip-theme="warning">
                                    <i class="fas fa-upload me-2"></i> Restaurar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
	
    <div class="row g-4">
        <!-- Configuración General -->
        <div class="col-lg-6 fade-in-up" style="animation-delay: 0.05s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseConfigGeneral" aria-expanded="false" aria-controls="collapseConfigGeneral">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-sliders-h me-2" style="color: #60a5fa;"></i> Configuración General
                    </h6>
                </div>
                <div id="collapseConfigGeneral" class="collapse">
                <div class="p-4">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Horas laborables mensuales</label>
                                <input type="number" class="form-control" name="horas_mensuales" value="<?php echo htmlspecialchars($config['horas_mensuales'] ?? '192'); ?>" title="Horas laborales mensuales según la ley" data-tooltip="Horas laborales mensuales según la ley" data-tooltip-theme="info">
                                <small class="text-secondary">24 días × 8 horas = 192 horas</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Días laborables mensuales</label>
                                <input type="number" class="form-control" name="dias_mensuales" value="<?php echo htmlspecialchars($config['dias_mensuales'] ?? '24'); ?>" title="Días laborales del mes" data-tooltip="Días laborales del mes" data-tooltip-theme="info">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Horas por jornada diaria</label>
                                <input type="number" class="form-control" name="horas_jornada_diaria" value="<?php echo htmlspecialchars($config['horas_jornada_diaria'] ?? '8'); ?>" title="Horas de jornada diaria" data-tooltip="Horas de jornada diaria" data-tooltip-theme="info">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Salario mínimo mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="salario_minimo" value="<?php echo htmlspecialchars($config['salario_minimo'] ?? '2100'); ?>" title="Salario mínimo vigente en CUP" data-tooltip="Salario mínimo vigente en CUP" data-tooltip-theme="info">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tasa de Contribución Especial (%)</label>
                                <input type="number" step="0.01" class="form-control" name="tasa_contribucion_especial" value="<?php echo htmlspecialchars($config['tasa_contribucion_especial'] ?? '5'); ?>" title="Tasa de contribución especial al estado" data-tooltip="Tasa de contribución especial al estado" data-tooltip-theme="info">
                                <small class="text-secondary">Porcentaje aplicado al salario devengado</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Recargo Nocturno (multiplicador)</label>
                                <input type="number" step="0.01" class="form-control" name="recargo_nocturno" value="<?php echo htmlspecialchars($config['recargo_nocturno'] ?? '1.25'); ?>" title="Multiplicador de recargo nocturno (1.25 = 25% extra)" data-tooltip="Multiplicador de recargo nocturno (1.25 = 25% extra)" data-tooltip-theme="info">
                                <small class="text-secondary">Multiplicador del salario base (1.25 = 25% extra)</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nocturnidad Temprana Nt 7-23h ($/h)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="tarifa_nocturnidad_temprana" value="<?php echo htmlspecialchars($config['tarifa_nocturnidad_temprana'] ?? '0.60'); ?>" title="Tarifa fija por hora de nocturnidad temprana (7pm-11pm)" data-tooltip="Tarifa fija por hora de nocturnidad temprana" data-tooltip-theme="info">
                                </div>
                                <small class="text-secondary">Res. 15/2026 MTSS</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nocturnidad Tardía Nt 23-7h ($/h)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="tarifa_nocturnidad_tardia" value="<?php echo htmlspecialchars($config['tarifa_nocturnidad_tardia'] ?? '1.15'); ?>" title="Tarifa fija por hora de nocturnidad tardía (11pm-7am)" data-tooltip="Tarifa fija por hora de nocturnidad tardía" data-tooltip-theme="info">
                                </div>
                                <small class="text-secondary">Res. 15/2026 MTSS</small>
                            </div>
                        </div>
                        <button type="submit" name="guardar_config_general" class="btn-win btn-win-primary w-100" title="Guardar configuración general" data-tooltip="Guardar configuración general" data-tooltip-theme="success">
                            <i class="fas fa-save me-1"></i> Guardar Configuración General
                        </button>
                    </form>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Tasas del Sistema -->
        <div class="col-lg-6 fade-in-up" style="animation-delay: 0.1s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseTasas" aria-expanded="false" aria-controls="collapseTasas">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-percent me-2" style="color: #f59e0b;"></i> Tasas del Sistema
                    </h6>
                    <button type="button" class="btn-win btn-win-sm btn-win-success" data-bs-toggle="modal" data-bs-target="#agregarTasaModal" title="Agregar nueva tasa" data-tooltip="Agregar nueva tasa" data-tooltip-theme="success">
                        <i class="fas fa-plus-circle me-1"></i> Agregar Tasa
                    </button>
                </div>
                <div id="collapseTasas" class="collapse">
                <div class="p-4">
                    <div class="table-responsive">
                        <table class="table table-sm" id="tasasTable">
                            <thead><tr><th>Tasa</th><th>Valor</th><th>Vigencia</th><th>Descripción</th><th style="width:2.5rem"></th></tr></thead>
                            <tbody>
                                <?php foreach ($tasas as $tasa): ?>
                                <tr>
                                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $tasa['nombre_tasa'])); ?></strong></td>
                                    <td><?php echo $tasa['valor']; ?>%</td>
                                    <td><?php echo date('d/m/Y', strtotime($tasa['fecha_vigencia'])); ?></td>
                                    <td><?php echo $tasa['descripcion']; ?></td>
                                    <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarTasa(this, <?php echo $tasa['id']; ?>, '<?php echo addslashes($tasa['nombre_tasa']); ?>')" title="Eliminar tasa" data-tooltip="Eliminar tasa" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tasas)): ?>
                                <tr><td colspan="5" class="text-center text-secondary">No hay tasas configuradas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Datos de la Entidad -->
        <div class="col-12 fade-in-up" style="animation-delay: 0.12s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseDatosEntidad" aria-expanded="false" aria-controls="collapseDatosEntidad">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-building me-2" style="color: #a78bfa;"></i> Datos de la Entidad y Especialistas
                    </h6>
                </div>
                <div id="collapseDatosEntidad" class="collapse">
                <div class="p-4">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de la Empresa</label>
                                <input type="text" class="form-control" name="nombre_empresa" value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? COMPANY_NAME); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" class="form-control" name="direccion_empresa" value="<?php echo htmlspecialchars($config['direccion_empresa'] ?? 'Carretera Central Km 5, Camagüey, Cuba'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">REEUP / Código de Identificación Fiscal</label>
                                <input type="text" class="form-control" name="reeup_empresa" value="<?php echo htmlspecialchars($config['reeup_empresa'] ?? '319-1-02264'); ?>">
                                <small class="text-secondary">Registro Estatal de Entidades y Unidades Presupuestadas</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIT (Número de Identificación Tributaria)</label>
                                <input type="text" class="form-control" name="nit_empresa" value="<?php echo htmlspecialchars($config['nit_empresa'] ?? '1018569663222'); ?>">
                                <small class="text-secondary">Número de Identificación Tributaria</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tel&eacute;fono de Contacto de la Empresa</label>
                                <input type="text" class="form-control" name="telefono_empresa" value="<?php echo htmlspecialchars($config['telefono_empresa'] ?? '+53 5 2712861'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Correo Contacto Empresa</label>
                                <input type="email" class="form-control" name="email_empresa" value="<?php echo htmlspecialchars($config['email_empresa'] ?? 'kakycu@gmail.com'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tel&eacute;fono de Contacto de Soporte</label>
                                <input type="text" class="form-control" name="telefono_soporte" value="<?php echo htmlspecialchars($config['telefono_soporte'] ?? '+53 5986 0773'); ?>">
                                <small class="text-secondary">N&uacute;mero que se muestra en soporte.php</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Correo Contacto Soporte</label>
                                <input type="email" class="form-control" name="email_soporte" value="<?php echo htmlspecialchars($config['email_soporte'] ?? 'kakycu@gmail.com'); ?>">
                                <small class="text-secondary">Correo que se muestra en soporte.php</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Eslogan</label>
                                <input type="text" class="form-control" name="slogan" value="<?php echo htmlspecialchars($config['slogan'] ?? 'Transformando Nuevitas en beneficio de todos.'); ?>">
                                <small class="text-secondary">Eslogan de la entidad</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jefe de Proyecto</label>
                                <input type="text" class="form-control" name="jefe_proyecto" value="<?php echo htmlspecialchars($config['jefe_proyecto'] ?? JEFE_PROYECTO); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialista en Gestión Económica</label>
                                <input type="text" class="form-control" name="especialista_gestion" value="<?php echo htmlspecialchars($config['especialista_gestion'] ?? ESPECIALISTA); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Intendente Local del CAM</label>
                                <input type="text" class="form-control" name="intendente" value="<?php echo htmlspecialchars($config['intendente'] ?? 'Eladio Francisco Ávalos'); ?>">
                                <small class="text-secondary">Aprueba la plantilla de cargos</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialista de Gestión de los Recursos Humanos</label>
                                <input type="text" class="form-control" name="especialista_gestionRRHH" value="<?php echo htmlspecialchars($config['especialista_gestionRRHH'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" name="guardar_datos_entidad" class="btn-win btn-win-primary w-100" title="Guardar datos de la entidad" data-tooltip="Guardar datos de la entidad" data-tooltip-theme="success">
                            <i class="fas fa-save me-1"></i> Guardar Datos de la Entidad
                        </button>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuración de Correo (SMTP) -->
    <div class="row g-4 mt-1">
        <div class="col-12 fade-in-up" style="animation-delay: 0.1s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseMail" aria-expanded="false" aria-controls="collapseMail">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-envelope-open-text me-2" style="color: #f59e0b;"></i> Configuración de Correo (SMTP)
                        <span class="badge ms-2" style="background: <?php echo $config_mail['activo'] === '1' ? 'var(--color-success)' : '#ef4444'; ?>; font-size:0.65rem;"><?php echo $config_mail['activo'] === '1' ? 'ACTIVO' : 'INACTIVO'; ?></span>
                    </h6>
                </div>
                <div id="collapseMail" class="collapse">
                <div class="p-4">
                    <form method="POST" id="mailForm">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Correo activo para recuperación</label>
                                <select class="form-control" name="mail_activo" id="mail_activo" title="Activar/desactivar correo" data-tooltip="Activar/desactivar correo" data-tooltip-theme="secondary">
                                    <option value="1" <?php echo $config_mail['activo'] === '1' ? 'selected' : ''; ?>>Sí, activado</option>
                                    <option value="0" <?php echo $config_mail['activo'] !== '1' ? 'selected' : ''; ?>>No, desactivado</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Proveedor SMTP</label>
                                <select class="form-control" name="mail_proveedor" id="mail_proveedor" title="Proveedor de correo" data-tooltip="Proveedor de correo" data-tooltip-theme="secondary">
                                    <?php foreach ($proveedores_smtp as $clave => $prov): ?>
                                        <option value="<?php echo $clave; ?>" <?php echo $config_mail['proveedor'] === $clave ? 'selected' : ''; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cifrado</label>
                                <select class="form-control" name="mail_encryption" id="mail_encryption" title="Tipo de encriptación" data-tooltip="Tipo de encriptación" data-tooltip-theme="secondary">
                                    <option value="tls" <?php echo $config_mail['encryption'] === 'tls' ? 'selected' : ''; ?>>STARTTLS (puerto 587)</option>
                                    <option value="ssl" <?php echo $config_mail['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL/TLS (puerto 465)</option>
                                    <option value="none" <?php echo $config_mail['encryption'] === 'none' ? 'selected' : ''; ?>>Sin cifrado</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Servidor SMTP (Host)</label>
                                <input type="text" class="form-control" name="mail_host" id="mail_host" value="<?php echo htmlspecialchars($config_mail['host']); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Puerto</label>
                                <input type="number" class="form-control" name="mail_port" id="mail_port" value="<?php echo htmlspecialchars($config_mail['port']); ?>" min="1" max="65535">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario SMTP</label>
                                <input type="text" class="form-control" name="mail_usuario" id="mail_usuario" value="<?php echo htmlspecialchars($config_mail['usuario']); ?>" placeholder="cuenta@gmail.com" autocomplete="off">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña SMTP / Contraseña de aplicación</label>
                                <input type="password" class="form-control" name="mail_password" id="mail_password" value="<?php echo htmlspecialchars($config_mail['password']); ?>" placeholder="••••••••••••" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Correo remitente (From)</label>
                                <input type="email" class="form-control" name="mail_from" id="mail_from" value="<?php echo htmlspecialchars($config_mail['from']); ?>" placeholder="noreply@entidad.cu">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del remitente</label>
                                <input type="text" class="form-control" name="mail_from_name" id="mail_from_name" value="<?php echo htmlspecialchars($config_mail['from_name']); ?>" placeholder="<?php echo defined('COMPANY_NAME') ? htmlspecialchars(COMPANY_NAME) : 'SisGesNom'; ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="guardar_config_mail" class="btn-win btn-win-primary" title="Guardar configuración de correo" data-tooltip="Guardar configuración de correo" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                            <button type="button" class="btn-win btn-win-info" id="btnProbarMail" title="Enviar correo de prueba" data-tooltip="Enviar correo de prueba" data-tooltip-theme="info">
                                <i class="fas fa-paper-plane me-1"></i> Probar envío
                            </button>
                        </div>
                        <p class="text-secondary mt-3 mb-0" style="font-size:0.78rem;">
                            <i class="fas fa-info-circle me-1"></i>Gmail: use una "Contraseña de aplicación" de Google con verificación en dos pasos. El enlace de recuperación de contraseñas se envía desde este servidor SMTP y es válido por 30 minutos.
                        </p>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rangos de Impuesto (Ingresos Personales) -->
    <div class="row g-4 mt-1">
        <div class="col-12 fade-in-up" style="animation-delay: 0.15s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title" data-bs-toggle="collapse" data-bs-target="#collapseRangos" aria-expanded="false" aria-controls="collapseRangos">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-chart-line me-2" style="color: var(--color-success);"></i> Rangos de Impuesto (Ingresos Personales)
                    </h6>
                </div>
                <div id="collapseRangos" class="collapse">
                <div class="p-4">
                    <form method="POST" id="rangosForm">
                        <div class="mb-3">
                            <label class="form-label">Fecha Vigencia</label>
                            <input type="date" class="form-control" name="fecha_vigencia" value="<?php echo $fecha_vigencia_actual; ?>" required title="Fecha de entrada en vigencia" data-tooltip="Fecha de entrada en vigencia" data-tooltip-theme="secondary">
                            <small class="text-secondary">Fecha desde la cual aplican estos rangos</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm" id="rangosTable">
                                <thead>
                                    <tr><th>Desde (CUP)</th><th>Hasta (CUP)</th><th>Tasa (%)</th><th>Monto Fijo</th><th style="width:2.5rem"></th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($rangos_impuesto)): ?>
                                        <?php foreach ($rangos_impuesto as $index => $rango): ?>
                                        <tr>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][desde]" value="<?php echo $rango['desde']; ?>" required></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][hasta]" value="<?php echo $rango['hasta']; ?>"></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][tasa]" value="<?php echo $rango['tasa'] * 100; ?>" required></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[<?php echo $index; ?>][monto_fijo]" value="<?php echo $rango['monto_fijo']; ?>"></td>
                                            <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][desde]" value="0" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][hasta]" value="3260"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][tasa]" value="0" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[0][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][desde]" value="3260.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][hasta]" value="9510"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][tasa]" value="3" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[1][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][desde]" value="9510.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][hasta]" value="15000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][tasa]" value="5" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[2][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][desde]" value="15000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][hasta]" value="20000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][tasa]" value="7.5" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][desde]" value="20000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][hasta]" value="25000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][tasa]" value="10" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][desde]" value="25000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][hasta]" value="30000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][tasa]" value="15" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[5][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][desde]" value="30000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][hasta]" value=""></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][tasa]" value="20" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[6][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot><tr><td colspan="5"><button type="button" class="btn-win btn-win-sm" onclick="agregarFila()" title="Agregar nuevo rango" data-tooltip="Agregar nuevo rango" data-tooltip-theme="success"><i class="fas fa-plus-circle me-1"></i> Agregar Rango</button></td></tr></tfoot>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Los rangos se aplican en orden ascendente. Dejar "Hasta" en blanco para el último rango.
                        </div>
                        <button type="submit" name="guardar_rangos" class="btn-win btn-win-primary w-100" title="Guardar rangos de impuesto" data-tooltip="Guardar rangos de impuesto" data-tooltip-theme="success">
                            <i class="fas fa-save me-1"></i> Guardar Rangos de Impuesto
                        </button>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Modal para agregar tasa -->
<div class="modal fade" id="agregarTasaModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color: #60a5fa;"></i>Agregar Nueva Tasa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
            </div>
            <form method="POST" id="agregarTasaForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="agregar_tasa" value="1">
                    <div class="mb-3"><label class="form-label">Nombre de la Tasa *</label><input type="text" class="form-control" name="nombre_tasa" required placeholder="Ej: contribucion_especial, recargo_nocturno"><small class="text-secondary">Identificador único de la tasa</small></div>
                    <div class="mb-3"><label class="form-label">Valor (%) *</label><input type="number" step="0.01" class="form-control" name="valor_tasa" required placeholder="Ej: 5.00"></div>
                    <div class="mb-3"><label class="form-label">Fecha de Vigencia *</label><input type="date" class="form-control" name="fecha_vigencia_tasa" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion_tasa" rows="3" placeholder="Describa el propósito de esta tasa"></textarea></div>
                </div>
                <div class="modal-footer modal-footer-win">
                    <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cancelar</button>
                    <button type="submit" class="btn-win btn-win-primary btn-win-sm" title="Guardar tasa" data-tooltip="Guardar tasa" data-tooltip-theme="success"><i class="fas fa-save me-1"></i> Guardar Tasa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Restaurar Backup -->
<div class="modal fade" id="restoreModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-database me-2" style="color: #f97316;"></i> Restaurar Base de Datos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning mb-4" style="background: rgba(245, 158, 11, 0.12); border: 0.0625rem solid rgba(245, 158, 11, 0.35); border-radius: 0.75rem;">
                    <div style="display: flex; align-items: flex-start; gap:0.75rem;">
                        <i class="fas fa-exclamation-triangle fa-2x" style="color: #fbbf24;"></i>
                        <div>
                            <strong style="color: #fbbf24;">¡Precaución!</strong>
                            <p class="mb-0 mt-1" style="color: #d1d5db;">Esta acción SOBRESCRIBIRÁ todos los datos actuales. Asegúrate de tener un backup antes de continuar.</p>
                        </div>
                    </div>
                </div>
                <div class="mb-4 p-3" style="background: rgba(96, 165, 250, 0.08); border-radius: 0.625rem;">
                    <div class="restore-info-text"><i class="fas fa-info-circle me-2"></i> <strong>¿Qué se restaurará?</strong></div>
                    <ul class="restore-info-list mt-2" style="list-style: none; padding-left:0;">
                        <li><i class="fas fa-check-circle text-success me-2"></i> Estructura completa de la base de datos</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Datos de empleados y nóminas</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Configuración del sistema y tasas</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i> Historial de vacaciones y submayores</li>
                    </ul>
                </div>
                <form id="restoreForm" enctype="multipart/form-data">
                    <div class="form-group mb-3">
                        <label class="form-label mb-2"><i class="fas fa-file-archive me-1"></i> Seleccionar archivo de backup:</label>
                        <input type="file" name="backup_file" id="restoreFile" class="form-control" accept=".sql,.zip" required>
                        <small class="text-secondary mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i> Formatos soportados: .sql, .zip (máximo 300MB)
                        </small>
                        <small class="text-secondary d-block mt-1" id="fileSizeInfo"></small>
                    </div>
                    
                    <!-- CHECKBOX DE CONFIRMACIÓN - OBLIGATORIO -->
                    <div class="form-check mb-4 p-3" style="background: rgba(220, 53, 69, 0.08); border-radius: 0.5rem; border-left: 0.1875rem solid #dc3545;">
                        <input type="checkbox" class="form-check-input" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore" style="color: #fca5a5;">
                            <i class="fas fa-exclamation-triangle me-1"></i> 
                            <strong>Confirmo que:</strong>
                            <ul style="margin:0.3125rem 0 0 1.25rem; padding-left:0; list-style: none;">
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Tengo un backup actual de la base de datos</li>
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Comprendo que se sobrescribirán todos los datos</li>
                                <li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Deseo proceder con la restauración</li>
                            </ul>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-win btn-win-warning w-100" id="btnRestore" disabled title="Restaurar backup" data-tooltip="Restaurar backup" data-tooltip-theme="warning">
                        <i class="fas fa-upload me-2"></i> Restaurar Backup
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Backup con nombre personalizado -->
<div class="modal fade" id="backupNameModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-file-export me-2" style="color: var(--color-success);"></i> Backup con nombre personalizado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info mb-4" style="background: rgba(96, 165, 250, 0.12); border: 0.0625rem solid rgba(96, 165, 250, 0.35); border-radius: 0.75rem;">
                    <div style="display: flex; align-items: flex-start; gap:0.75rem;">
                        <i class="fas fa-info-circle fa-2x" style="color: #60a5fa;"></i>
                        <div>
                            <strong style="color: #60a5fa;">Backup personalizado</strong>
                            <p class="mb-0 mt-1" style="color: #d1d5db;">Asigna un nombre descriptivo a tu backup para identificarlo fácilmente.</p>
                        </div>
                    </div>
                </div>
                <form id="backupNameForm">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-tag me-1"></i> Nombre del backup <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="backupNombreInput" 
                               placeholder="Ej: backup_pre_actualizacion_2026_07_12" 
                               required maxlength="50">
                        <small class="text-secondary d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i> 
                            Se agregará automáticamente la fecha y hora: 
                            <span id="backupNamePreview" style="color: #60a5fa; font-family: monospace;">backup_sistema_YYYY_MM_DD_HH_MM</span>
                        </small>
                        <small class="text-secondary d-block mt-1">
                            <i class="fas fa-info-circle me-1"></i> 
                            Máximo 50 caracteres (solo letras, números, guiones y guiones bajos)
                        </small>
                    </div>
                    <div class="mb-3 p-3" style="background: rgba(var(--color-success-rgb), 0.05); border-radius: 0.5rem;">
                        <h6 class="text-muted small"><i class="fas fa-list-check me-1"></i> Contenido del backup:</h6>
                        <ul class="text-muted small" style="list-style: none; padding-left:0; margin-bottom:0;">
                            <li><i class="fas fa-check-circle text-success me-1"></i> Estructura completa de la base de datos</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Datos de empleados y nóminas</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Configuración del sistema y tasas</li>
                            <li><i class="fas fa-check-circle text-success me-1"></i> Historial de vacaciones y submayores</li>
                        </ul>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmBackupName" required>
                        <label class="form-check-label" for="confirmBackupName" style="color: #d1d5db;">
                            <i class="fas fa-check-circle me-1" style="color: var(--color-success);"></i> 
                            Confirmo que deseo crear este backup con el nombre especificado
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer modal-footer-win">
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" class="btn-win btn-win-success" id="btnConfirmBackupWithName" title="Generar backup con nombre" data-tooltip="Generar backup con nombre" data-tooltip-theme="info">
                    <i class="fas fa-download me-1"></i> Generar Backup
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/cropper.min.js"></script>

<script>
// Clock y Sidebar
const usuarioActualId = <?php echo $usuario_actual_id; ?>;

function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const clockSpan = document.getElementById('liveClock');
    if (clockSpan) clockSpan.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000); updateClock();

// Backup y Restore
function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: '<div style="text-align: left;"><p><i class="fas fa-info-circle me-2"></i> Se creará una copia de seguridad completa.</p><p><small>La copia incluirá: empleados, nóminas, configuración y vacaciones.</small></p><div class="alert alert-info mt-2"><i class="fas fa-clock me-1"></i> El archivo se guardará en formato ZIP</div></div>',
        icon: 'info', showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        background: 'var(--panel)', color: 'var(--txt)'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });
            fetch('../ajax/backup_db.php', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ title: '<i class="fas fa-check-circle me-2"></i> Backup Completado', html: `<p>Archivo: ${data.filename}</p><p>Tamaño: ${data.size}</p><a href="../${data.download_url}" class="btn btn-success" download><i class="fas fa-download me-2"></i> Descargar</a>`, icon: 'success', background: 'var(--panel)', color: 'var(--txt)', confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'});
                } else { Swal.fire({ title: '<i class="fas fa-exclamation-triangle me-2"></i> Error', text: data.message, icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); }
            })
            .catch(() => { Swal.fire({ title: 'Error', text: 'Error de conexión', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); });
        }
    });
}
// ==========================================
// BACKUP CON NOMBRE PERSONALIZADO
// ==========================================

function realizarBackupConNombre(nombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
        html: `<p>Creando backup: <strong>${nombre}</strong></p><p class="text-muted small">Este proceso puede tomar unos segundos...</p>`,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#fff'
    });

    fetch('../ajax/backup_db.php?nombre_custom=' + encodeURIComponent(nombre), { 
        method: 'GET', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                title: '<i class="fas fa-check-circle me-2" style="color: 1;"></i> Backup Completado',
                html: `
                    <div class="text-start">
                        <p><strong>Archivo:</strong> ${data.filename}</p>
                        <p><strong>Tamaño:</strong> ${data.size}</p>
                        <p><strong>Nombre asignado:</strong> ${data.nombre || nombre}</p>
                        <div class="mt-3">
                            <a href="../${data.download_url}" class="btn btn-success w-100" download>
                                <i class="fas fa-download me-2"></i> Descargar Backup
                            </a>
                        </div>
                    </div>
                `,
                icon: 'success',
                background: '#1a1a2e',
                color: '#fff',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
            });
        } else {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Error',
                text: data.message || 'Error al generar el backup',
                icon: 'error',
                background: '#1a1a2e',
                color: '#fff'
            });
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-wifi me-2" style="color: #dc3545;"></i> Error de Conexión',
            text: 'No se pudo conectar con el servidor',
            icon: 'error',
            background: '#1a1a2e',
            color: '#fff'
        });
    });
}

// Previsualizar nombre del backup
document.getElementById('backupNombreInput')?.addEventListener('input', function() {
    const nombre = this.value.trim() || 'backup_sistema';
    // Limpiar caracteres especiales para previsualización
    const nombreLimpio = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
    const fecha = new Date();
    const fechaStr = fecha.getFullYear() + '_' + 
                     String(fecha.getMonth() + 1).padStart(2, '0') + '_' + 
                     String(fecha.getDate()).padStart(2, '0') + '_' + 
                     String(fecha.getHours()).padStart(2, '0') + '_' + 
                     String(fecha.getMinutes()).padStart(2, '0');
    const nombreCompleto = nombreLimpio + '_' + fechaStr;
    document.getElementById('backupNamePreview').textContent = nombreCompleto;
    document.getElementById('backupNamePreview').style.color = nombreLimpio.length > 0 ? '#60a5fa' : '#f59e0b';
});

// Botón confirmar backup con nombre
document.getElementById('btnConfirmBackupWithName')?.addEventListener('click', function() {
    const nombreInput = document.getElementById('backupNombreInput');
    let nombre = nombreInput.value.trim();
    
    if (!nombre) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Nombre requerido',
            text: 'Por favor, ingresa un nombre para identificar el backup',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        nombreInput.focus();
        return;
    }
    
    // Limpiar caracteres especiales
    nombre = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
    
    if (nombre.length < 3) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Nombre muy corto',
            text: 'El nombre debe tener al menos 3 caracteres',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff'
        });
        nombreInput.focus();
        return;
    }
    
    const confirmCheckbox = document.getElementById('confirmBackupName');
    if (!confirmCheckbox.checked) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i> Confirmación requerida',
            text: 'Debes marcar la casilla de confirmación para crear el backup',
            icon: 'warning',
            background: '#1a1a2e',
            color: '#fff',
			confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
        });
        return;
    }
    
    // Cerrar modal y ejecutar backup
    bootstrap.Modal.getInstance(document.getElementById('backupNameModal')).hide();
    
    // Limpiar el campo para la próxima vez
    setTimeout(() => {
        nombreInput.value = '';
        confirmCheckbox.checked = false;
        document.getElementById('backupNamePreview').textContent = 'backup_sistema_YYYY_MM_DD_HH_MM';
        document.getElementById('backupNamePreview').style.color = '#60a5fa';
    }, 300);
    
    realizarBackupConNombre(nombre);
});

// Evento para tecla Enter en el input
document.getElementById('backupNombreInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnConfirmBackupWithName').click();
    }
});

// ==========================================
// FUNCIONES EXISTENTES (mantener las que ya tienes)
// ==========================================

function realizarBackupManual() {
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: '<div style="text-align: left;"><p><i class="fas fa-info-circle me-2"></i> Se creará una copia de seguridad completa.</p><p><small>La copia incluirá: empleados, nóminas, configuración y vacaciones.</small></p><div class="alert alert-info mt-2"><i class="fas fa-clock me-1"></i> El archivo se guardará en formato ZIP</div></div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#fff'
            });
            fetch('../ajax/backup_db.php', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle me-2"></i> Backup Completado',
                        html: `<p>Archivo: ${data.filename}</p><p>Tamaño: ${data.size}</p><a href="../${data.download_url}" class="btn btn-success" download><i class="fas fa-download me-2"></i> Descargar</a>`,
                        icon: 'success',
                        background: '#1a1a2e',
                        color: '#fff',
                        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
                    });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-exclamation-triangle me-2"></i> Error',
                        text: data.message,
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#fff'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: '#fff'
                });
            });
        }
    });
}


// Event listeners para los botones
document.getElementById('btnBackupManualCard')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    realizarBackupManual(); 
});

document.getElementById('btnRestoreBackup')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    restaurarBackup(); 
});

document.getElementById('btnRestoreBackupCard')?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    restaurarBackup(); 
});

// 1. Crear una variable global para la instancia del modal
let restoreModalInstance = null;

function restaurarBackup() {
    const modalEl = document.getElementById('restoreModal');
    
    // 2. Solo crear la instancia si no existe
    if (!restoreModalInstance) {
        restoreModalInstance = new bootstrap.Modal(modalEl);
    }
    
    restoreModalInstance.show();
}

// 3. AGREGAR ESTO: Limpieza forzosa al cerrar cualquier modal
document.addEventListener('hidden.bs.modal', function () {
    // Si quedan backdrops huérfanos, los eliminamos
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(b => b.remove());
    // Devolvemos el scroll al cuerpo
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
});


document.getElementById('btnRestoreBackup')?.addEventListener('click', (e) => { e.preventDefault(); restaurarBackup(); });
document.getElementById('confirmRestore')?.addEventListener('change', function() { document.getElementById('btnRestore').disabled = !this.checked; });
document.getElementById('restoreForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('restoreFile');
    if (!fileInput.files || fileInput.files.length === 0) { Swal.fire({ title: 'Error', text: 'Seleccione un archivo', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); return; }
    const formData = new FormData(this);
	
    // CERRAR EL MODAL DE BOOTSTRAP ANTES DE MOSTRAR EL SWAL DE CARGA
    if (restoreModalInstance) {
        restoreModalInstance.hide();
    }
	
    Swal.fire({
        title: 'Restaurando...',
        html: '<style>.restore-spinner{width:2.375rem;height:2.375rem;margin:0 auto;border:0.25rem solid #334155;border-top-color:#14b8a6;border-radius:50%;animation:restore-spin .8s linear infinite;}@keyframes restore-spin{to{transform:rotate(360deg);}}</style>' +
              '<div class="text-center mb-2"><div class="restore-spinner"></div></div>' +
              '<p id="restoreStep" class="mb-2 text-light">Iniciando...</p>' +
              '<div class="progress" style="height:1.25rem; background:#2d2d3a; border-radius:0.625rem; overflow:hidden;">' +
              '<div id="restoreProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%; background:linear-gradient(90deg,#3b82f6,#8b5cf6);">0%</div></div>' +
              '<p class="mt-2 mb-0"><span id="restoreProgressTable" class="text-info"></span>' +
              '<span id="restoreProgressPct" class="float-end text-light"></span></p>',
        allowOutsideClick: false,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#fff'
    });

    const restorePoll = setInterval(() => {
        fetch('../ajax/restore_progress.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(d => {
                const pct = d.percent || 0;
                const bar = document.getElementById('restoreProgressBar');
                const stepEl = document.getElementById('restoreStep');
                const tblEl = document.getElementById('restoreProgressTable');
                const pctEl = document.getElementById('restoreProgressPct');
                if (bar) { bar.style.width = pct + '%'; bar.textContent = pct + '%'; }
                if (stepEl && d.step) stepEl.textContent = d.step;
                if (tblEl) tblEl.textContent = d.table ? 'Tabla: ' + d.table : '';
                if (pctEl && d.total) pctEl.textContent = (d.processed || 0) + ' / ' + d.total + ' consultas';
            })
            .catch(() => {});
    }, 600);

    fetch('../ajax/restore_db.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        clearInterval(restorePoll);
        const bar = document.getElementById('restoreProgressBar');
        if (bar) { bar.style.width = '100%'; bar.textContent = '100%'; }
        if (data.success) { Swal.fire({ title: 'Restauración Completada', html: `<pre style="background:#2d2d3a; padding:0.75rem; border-radius:0.5rem;">${data.message}</pre>`, icon: 'success', confirmButtonText: '<i class="fas fa-check me-2"></i> Recargar', background: 'var(--panel)', color: 'var(--txt)',  }).then(() => location.reload()); }
        else { Swal.fire({ title: 'Error', html: `<pre style="background:#2d2d3a; padding:0.75rem; border-radius:0.5rem; color:#fca5a5;">${data.message}</pre>`, icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); }
    })
    .catch(() => { clearInterval(restorePoll); Swal.fire({ title: 'Error de Conexión', text: 'No se pudo conectar', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); });
});

// Funciones para rangos y tasas
function agregarFila() {
    const tbody = document.querySelector('#rangosTable tbody');
    const rowCount = tbody.children.length;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `<td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][desde]" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][hasta]"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][tasa]" required></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[${rowCount}][monto_fijo]" value="0"></td>
                        <td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td>`;
    tbody.appendChild(newRow);
}

function confirmarEliminarRango(btn) {
    const row = btn.closest('tr');
    const valor = (campo) => (row.querySelector(`input[name$="[${campo}]"]`)?.value ?? '').trim();
    const desde = valor('desde'), hasta = valor('hasta'), tasa = valor('tasa');

    Swal.fire({
        title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Eliminar Rango',
        html: `<p>¿Eliminar el rango <strong>${desde || '—'} – ${hasta || '∞'}</strong> CUP con tasa <strong>${tasa || '—'}%</strong>?</p>
               <p class="text-secondary mb-0" style="font-size:.85rem;">Los cambios se aplicarán al pulsar "Guardar Rangos de Impuesto".</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, eliminar',
        cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
        background: 'var(--panel)', color: 'var(--txt)'
    }).then((result) => {
        if (result.isConfirmed) {
            row.remove();
            Swal.fire({ title: '<i class="fas fa-check-circle me-2"></i> Eliminado', text: 'Rango eliminado', icon: 'success', timer: 1500, showConfirmButton: false, background: 'var(--panel)', color: 'var(--txt)' });
        }
    });
}

function confirmarEliminarTasa(btn, tasaId, tasaNombre) {
    const row = btn.closest('tr');
    const celdas = row ? row.children : [];
    const valor = celdas[1]?.textContent.trim() ?? '';
    const vigencia = celdas[2]?.textContent.trim() ?? '';

    Swal.fire({
        title: '<i class="fas fa-exclamation-triangle text-danger me-2"></i> Eliminar Tasa',
        html: `<p>¿Eliminar la tasa <strong>${tasaNombre}</strong>?</p>
               <p class="text-secondary mb-0" style="font-size:.9rem;"><i class="fas fa-percent me-1"></i>${valor || '—'} · <i class="fas fa-calendar-alt me-1"></i>${vigencia || '—'}</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Sí, eliminar',
        cancelButtonText: '<i class="fas fa-close me-2"></i> Cancelar',
        background: 'var(--panel)', color: 'var(--txt)'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form'); form.method = 'POST'; form.style.display = 'none'; form.innerHTML = `<input type="hidden" name="eliminar_tasa" value="1"><input type="hidden" name="tasa_id" value="${tasaId}">`;
            document.body.appendChild(form); form.submit();
        }
    });
}

document.getElementById('rangosForm')?.addEventListener('submit', function(e) {
    const rangos = document.querySelectorAll('#rangosTable tbody tr');
    let tieneRangoValido = false;
    for (let rango of rangos) {
        const desde = rango.querySelector('input[name*="[desde]"]')?.value;
        const tasa = rango.querySelector('input[name*="[tasa]"]')?.value;
        if (desde && parseFloat(desde) >= 0 && tasa && parseFloat(tasa) >= 0) { tieneRangoValido = true; break; }
    }
    if (!tieneRangoValido) { e.preventDefault(); Swal.fire({ title: '<i class="fas fa-exclamation-triangle me-2"></i> Error', text: 'Debe tener al menos un rango válido', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); }
});

document.getElementById('btnBackupManualCard')?.addEventListener('click', (e) => { e.preventDefault(); realizarBackupManual(); });
document.getElementById('btnRestoreBackupCard')?.addEventListener('click', (e) => { e.preventDefault(); restaurarBackup(); });

// ============================================
// CONFIGURACIÓN SMTP
// ============================================
const proveedoresSMTP = <?php echo json_encode($proveedores_smtp); ?>;

document.getElementById('mail_proveedor')?.addEventListener('change', function() {
    const prov = proveedoresSMTP[this.value];
    if (prov && this.value !== 'custom') {
        const host = document.getElementById('mail_host');
        const port = document.getElementById('mail_port');
        if (!host.value.trim()) host.value = prov.host || '';
        if (!port.value.trim()) port.value = prov.puerto || '';
        document.getElementById('mail_encryption').value = prov.encriptacion || 'tls';
    }
});

document.getElementById('btnProbarMail')?.addEventListener('click', function() {
    const form = document.getElementById('mailForm');
    if (!form) return;
    const fd = new FormData(form);
    Swal.fire({
        title: '<i class="fas fa-paper-plane" style="color: #3b82f6;"></i> Probando Envio Servidor SMTP...',
        html: '<div style="padding:1.25rem;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i></div>',
        background: '#0f172a', color: '#eee', showConfirmButton: false, allowOutsideClick: false
    });
    fetch('configuracion.php?ajax=probar_mail', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            Swal.fire({
                title: res.success ? '✅ Envío exitoso' : '❌ Error SMTP',
                text: res.message,
                icon: res.success ? 'success' : 'error',
                background: '#0f172a', color: '#eee',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                confirmButtonColor: res.success ? '#22c55e' : '#ef4444'
            });
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error de conexión', background: '#0f172a', color: '#fff' });
        });
});

</script>

<!-- Botones flotantes de navegación rápida -->
<style>
.scroll-quick-btns { position: fixed; right:1.25rem; bottom:1.25rem; display: flex; flex-direction: column; gap:0.625rem; z-index: 950; }
.scroll-quick-btn {
    width:2.75rem; height:2.75rem; border-radius: 0.75rem; border: 0.0625rem solid rgba(148, 163, 184, .35);
    background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; font-size:1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, .5); transition: all .2s;
}
.scroll-quick-btn:hover { transform: translateY(-0.125rem); filter: brightness(1.15); }
.scroll-quick-btn.hidden { opacity: 0; pointer-events: none; transform: translateY(0.5rem); }
@media print { .scroll-quick-btns { display: none !important; } }
</style>
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnScrollTop" title="Ir al principio" data-tooltip="Ir al principio" data-tooltip-theme="primary">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnScrollBottom" title="Ir al final" data-tooltip="Ir al final" data-tooltip-theme="primary">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>
<script>
(function () {
    var btnTop = document.getElementById('btnScrollTop');
    var btnBottom = document.getElementById('btnScrollBottom');
    if (!btnTop || !btnBottom) return;
    function actualizarVisibilidad() {
        var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        var y = window.scrollY || document.documentElement.scrollTop;
        btnTop.classList.toggle('hidden', y < 150);
        btnBottom.classList.toggle('hidden', maxScroll <= 150 || y > maxScroll - 150);
    }
    btnTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    btnBottom.addEventListener('click', function () { window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }); });
    window.addEventListener('scroll', actualizarVisibilidad);
    actualizarVisibilidad();
})();
</script>

<!-- Expandir / Colapsar todos los cards -->
<script>
(function () {
    var btn = document.getElementById('btnToggleAllCards');
    var texto = document.getElementById('btnToggleAllCardsTexto');
    if (!btn || !texto) return;
    var selectores = ['#collapseDB', '#collapseConfigGeneral', '#collapseTasas', '#collapseDatosEntidad', '#collapseMail', '#collapseRangos'];

    function todosAbiertos() {
        return selectores.every(function (sel) {
            var el = document.querySelector(sel);
            return el && el.classList.contains('show');
        });
    }

    function actualizarBoton() {
        var abiertos = todosAbiertos();
        texto.textContent = abiertos ? 'Colapsar todo' : 'Expandir todo';
        var icono = btn.querySelector('i');
        if (icono) icono.className = abiertos ? 'fas fa-compress-alt me-2' : 'fas fa-expand-alt me-2';
    }

    btn.addEventListener('click', function () {
        var abiertos = todosAbiertos();
        selectores.forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!el) return;
            var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            if (abiertos) { inst.hide(); } else { inst.show(); }
        });
        actualizarBoton();
    });

    selectores.forEach(function (sel) {
        var el = document.querySelector(sel);
        if (!el) return;
        el.addEventListener('shown.bs.collapse', actualizarBoton);
        el.addEventListener('hidden.bs.collapse', actualizarBoton);
    });

    actualizarBoton();
})();
</script>

</body>
</html>
