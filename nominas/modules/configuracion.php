<?php
// modules/configuracion.php - Configuraciones del Sistema
require_once '../config/database.php';
require_once __DIR__ . '/../includes/logger.php';
require_once '../config/mail.php';
require_once '../includes/funciones.php';

asegurarRecargosExtra($pdo);

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
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"></head>
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
    logAction('probar_configuracion_correo', 'configuracion', 'Prueba de configuración de correo', ['host' => $cfg['host'], 'puerto' => $cfg['port'], 'usuario' => $cfg['usuario'], 'destinatario' => $to], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
    echo json_encode(['success' => $res['success'], 'message' => $res['success'] ? 'Correo de prueba enviado a ' . $to : 'Error SMTP: ' . $res['error']]);
    exit;
}


// Configuración empresa
$config_empresa = ['nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', 'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director', 'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab. y Finanzas', 'especialista_gestionRRHH' => defined('ESPECIALISTA_RRHH') ? ESPECIALISTA_RRHH : 'Esp. RRHH', 'especialista_nominas' => defined('ESPECIALISTA_NOMINAS') ? ESPECIALISTA_NOMINAS : ''];
try {
    $stmt = $pdo->query("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_gestionRRHH', 'especialista_nominas')");
    while ($row = $stmt->fetch()) {
        if ($row['parametro'] == 'nombre_empresa') $config_empresa['nombre_empresa'] = $row['valor'];
        if ($row['parametro'] == 'jefe_proyecto') $config_empresa['jefe_proyecto'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestion') $config_empresa['especialista_gestion'] = $row['valor'];
        if ($row['parametro'] == 'especialista_gestionRRHH') $config_empresa['especialista_gestionRRHH'] = $row['valor'];
        if ($row['parametro'] == 'especialista_nominas') $config_empresa['especialista_nominas'] = $row['valor'];
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
            'reeup_empresa', 'nit_empresa', 'jefe_proyecto', 'especialista_gestion', 'especialista_nominas',
            'salario_minimo', 'intendente', 'recargo_nocturno', 'especialista_gestionRRHH',
            'tarifa_nocturnidad_temprana', 'tarifa_nocturnidad_tardia',
            'recargo_extra_diurna', 'recargo_extra_nocturna', 'recargo_doble_turno'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            logAction('guardar_configuracion_general', 'configuracion', 'Configuración general guardada', ['parametros_actualizados' => $params], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
            'reeup_empresa', 'nit_empresa',
            'slogan', 'telefono_empresa', 'email_empresa', 'telefono_soporte', 'email_soporte'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            logAction('guardar_datos_entidad', 'configuracion', 'Datos de la entidad guardados', ['parametros_actualizados' => $params], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Datos de la entidad guardados correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_datos_personal'])) {
        $params = [
            'jefe_proyecto', 'especialista_gestion', 'especialista_nominas',
            'especialista_gestionRRHH', 'intendente'
        ];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
                    $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                    $stmt->execute([$_POST[$param], $param]);
                }
            }
            logAction('guardar_datos_personal', 'configuracion', 'Personal autorizado guardado', ['parametros_actualizados' => $params], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Personal autorizado guardado correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_datos_bancarios'])) {
        $params = ['cuenta_bancaria', 'banco', 'sucursal'];
        
        try {
            foreach ($params as $param) {
                if (isset($_POST[$param])) {
$stmt = $pdo->prepare("INSERT INTO configuracion_general (parametro, valor, tipo_dato) VALUES (?, ?, 'texto')
                                       ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                    $stmt->execute([$param, $_POST[$param]]);
                }
            }
            logAction('guardar_datos_bancarios', 'configuracion', 'Información bancaria guardada', ['parametros_actualizados' => $params], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Información bancaria guardada correctamente";
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
            logAction('guardar_rangos_impuesto', 'configuracion', 'Actualización de rangos de impuesto', ['fecha_vigencia' => $_POST['fecha_vigencia'], 'cantidad_rangos' => count($rangos)], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
            logAction('eliminar_tasa_contribucion', 'configuracion', 'Eliminación de tasa de contribución', ['tasa_id' => (int)$_POST['tasa_id']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
            logAction('agregar_tasa_contribucion', 'configuracion', 'Alta de tasa de contribución', ['nombre_tasa' => $_POST['nombre_tasa']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
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
            'mail_password'   => cifrarMailPassword(trim($_POST['mail_password'] ?? '')),
            'mail_from'       => trim($_POST['mail_from'] ?? ''),
            'mail_from_name'  => trim($_POST['mail_from_name'] ?? ''),
        ];
        
        try {
            foreach ($params as $param => $valor) {
                $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                $stmt->execute([$valor, $param]);
            }
            logAction('guardar_configuracion_correo', 'configuracion', 'Configuración SMTP/correo guardada', ['proveedor' => $params['mail_proveedor']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Configuración de correo guardada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar configuración de correo: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['guardar_config_google'])) {
        require_once '../config/mail.php';
        $params_google = [];
        if (isset($_POST['google_client_id']) && trim($_POST['google_client_id']) !== '') {
            $params_google['google_client_id'] = cifrarSecreto(trim($_POST['google_client_id']));
        }
        if (isset($_POST['google_client_secret']) && trim($_POST['google_client_secret']) !== '') {
            $params_google['google_client_secret'] = cifrarSecreto(trim($_POST['google_client_secret']));
        }
        $params_google['googleoauth'] = (($_POST['googleoauth'] ?? '') === '1') ? 'true' : 'false';
        
        try {
            foreach ($params_google as $param => $valor) {
                $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                $stmt->execute([$valor, $param]);
            }
            logAction('guardar_configuracion_google', 'configuracion', 'Configuración de Google OAuth guardada', ['googleoauth' => $params_google['googleoauth']], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Configuración de Google guardada correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar configuración de Google: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    if (isset($_POST['guardar_config_sistema'])) {
        $params_sistema = [
            'tiempo_para_bloqueo' => max(1, (int)($_POST['tiempo_para_bloqueo'] ?? 10)),
        ];
        $subsistema_nominas = (($_POST['subsistema_nominas'] ?? '0') === '1') ? 1 : 0;
        try {
            foreach ($params_sistema as $param => $valor) {
                $stmt = $pdo->prepare("UPDATE configuracion_general SET valor = ? WHERE parametro = ?");
                $stmt->execute([$valor, $param]);
            }
            $stmt = $pdo->prepare("UPDATE subsistemas SET estado = ? WHERE codigo = '0001'");
            $stmt->execute([$subsistema_nominas]);
            logAction('guardar_configuracion_sistema', 'configuracion', 'Otras configuraciones del sistema guardadas', ['tiempo_para_bloqueo' => $params_sistema['tiempo_para_bloqueo'], 'subsistema_nominas' => $subsistema_nominas], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
            $mensaje = "Otras configuraciones del sistema guardadas correctamente";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al guardar configuración del sistema: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    // Guardado general (botón flotante): se ejecuta junto a los triggers individuales
    if (isset($_POST['guardar_todo']) && $tipo_mensaje === 'success') {
        logAction('guardar_todo_configuracion', 'configuracion', 'Guardado general de configuración', ['secciones' => 'general, entidad, bancaria, rangos, correo, google, sistema'], null, 'success', null, $_SESSION['auth_provider'] ?? 'local');
        $mensaje = "Toda la configuración se ha guardado correctamente";
        $tipo_mensaje = "success";
    }
}

// PRG (Post/Redirect/Get): tras un guardado exitoso, recargar la página para
// que los nuevos valores se apliquen (constantes, formularios, etc.)
if ($tipo_mensaje === 'success' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $botones_refresh = ['guardar_config_general', 'guardar_datos_entidad', 'guardar_datos_personal', 'guardar_datos_bancarios', 'guardar_rangos', 'guardar_config_mail', 'guardar_config_google', 'guardar_config_sistema', 'guardar_todo'];
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

// Catálogo de sucursales bancarias por código de banco (para la validación de cuentas)
$sucursales = [
    '06' => [ // BANDEC
        '5781' => 'BANDEC Sucursal Nuevitas',
        '0001' => 'BANDEC Sucursal Principal La Habana',
        '1001' => 'BANDEC Sucursal Camagüey',
        '5780' => 'BANDEC Sucursal Nuevitas Centro',
        '8888' => 'BANDEC Banca Móvil / Virtual',
        '9999' => 'BANDEC Dirección Nacional / Tarjetas MLC',
        '5960' => 'BANDEC Dirección Provincial Camagüey',
        '5961' => 'BANDEC Plaza de los Trabajadores',
        '5971' => 'BANDEC La Vigía',
        '5981' => 'BANDEC Calle República',
        '5941' => 'BANDEC Av. de los Mártires',
        '5951' => 'BANDEC Reparto Garrido',
        '5783' => 'BANDEC Nuevitas (Calle Máximo Gómez)',
        '5790' => 'BANDEC Playa Santa Lucía (Interno)',
        '5791' => 'BANDEC Playa Santa Lucía (Oficial)',
        '6021' => 'BANDEC Florida',
        '5821' => 'BANDEC Guáimaro',
        '6151' => 'BANDEC Santa Cruz del Sur',
        '5751' => 'BANDEC Minas',
        '5701' => 'BANDEC Esmeralda',
        '6061' => 'BANDEC Vertientes',
        '5841' => 'BANDEC Sibanicú',
        '5731' => 'BANDEC Sierra de Cubitas',
        '6101' => 'BANDEC Jimaguayú',
        '6121' => 'BANDEC Najasa',
        '0650' => 'BANDEC Sucursal Principal La Habana',
        '0651' => 'BANDEC Plaza',
        '0652' => 'BANDEC Cerro',
        '0653' => 'BANDEC Boyeros',
        '0654' => 'BANDEC Marianao',
        '0655' => 'BANDEC Guanabacoa',
        '0656' => 'BANDEC San Miguel',
        '0657' => 'BANDEC 10 de Octubre',
        '0660' => 'BANDEC Habana del Este',
        '7291' => 'BANDEC Pinar del Río (Principal)',
        '7281' => 'BANDEC Pinar del Río (Martí)',
        '7251' => 'BANDEC Viñales',
        '1651' => 'BANDEC Artemisa (Principal)',
        '1681' => 'BANDEC Mariel',
        '1661' => 'BANDEC San Antonio de los Baños',
        '1751' => 'BANDEC San José de las Lajas',
        '1781' => 'BANDEC Güines',
        '1721' => 'BANDEC Santa Cruz del Norte',
        '3151' => 'BANDEC Matanzas (Principal)',
        '3181' => 'BANDEC Varadero',
        '3191' => 'BANDEC Cárdenas',
        '4031' => 'BANDEC Santa Clara (Principal)',
        '4081' => 'BANDEC Sagua la Grande',
        '4131' => 'BANDEC Placetas',
        '5041' => 'BANDEC Cienfuegos (Prado)',
        '5051' => 'BANDEC Cienfuegos (Principal)',
        '5341' => 'BANDEC Sancti Spíritus (Principal)',
        '5381' => 'BANDEC Trinidad',
        '5531' => 'BANDEC Ciego de Ávila (Principal)',
        '5571' => 'BANDEC Morón',
        '5641' => 'BANDEC Cayo Coco',
        '6431' => 'BANDEC Las Tunas (Principal)',
        '6471' => 'BANDEC Puerto Padre',
        '6731' => 'BANDEC Holguín (Principal)',
        '6761' => 'BANDEC Moa',
        '6801' => 'BANDEC Guardalavaca',
        '7541' => 'BANDEC Bayamo (Principal)',
        '7581' => 'BANDEC Manzanillo',
        '8151' => 'BANDEC Santiago (Principal)',
        '8141' => 'BANDEC Santiago (Enramadas)',
        '9041' => 'BANDEC Guantánamo (Principal)',
        '9071' => 'BANDEC Baracoa',
        '9661' => 'BANDEC Nueva Gerona',
        '9000' => 'BANDEC Oficina Central',
    ],
    '12' => [ // BPA
        '0001' => 'BPA Sucursal Principal La Habana',
        '1000' => 'BPA Banca Electrónica / Transfermóvil',
        '1001' => 'BPA Sucursal Camagüey',
        '5962' => 'BPA Calle República',
        '5932' => 'BPA Av. de la Libertad',
        '5992' => 'BPA Plaza de los Trabajadores',
        '5942' => 'BPA La Caridad',
        '5972' => 'BPA Previsora',
        '5982' => 'BPA Lenin',
        '5772' => 'BPA Nuevitas (Agramonte esq. Maceo)',
        '5773' => 'BPA Microdistrito Nuevitas',
        '5774' => 'BPA Puerto de Nuevitas',
        '5782' => 'BPA Nuevitas Puerto',
        '5785' => 'BPA Nuevitas Playa',
        '6012' => 'BPA Florida',
        '5812' => 'BPA Guáimaro',
        '6142' => 'BPA Santa Cruz del Sur',
        '5752' => 'BPA Minas',
        '5692' => 'BPA Esmeralda',
        '6052' => 'BPA Vertientes',
        '5832' => 'BPA Sibanicú',
        '5722' => 'BPA Sierra de Cubitas',
        '6092' => 'BPA Jimaguayú',
        '6112' => 'BPA Najasa',
        '0100' => 'BPA Sucursal Principal (La Habana)',
        '0101' => 'BPA Centro Habana (San Rafael)',
        '0102' => 'BPA Habana Vieja (Obispo)',
        '0103' => 'BPA Plaza de la Revolución',
        '0104' => 'BPA Cerro',
        '0105' => 'BPA 10 de Octubre',
        '0106' => 'BPA Playa (Miramar)',
        '0107' => 'BPA Marianao',
        '0108' => 'BPA Boyeros',
        '0109' => 'BPA Arroyo Naranjo',
        '0110' => 'BPA Cotorro',
        '0111' => 'BPA Habana del Este',
        '0112' => 'BPA Guanabacoa',
        '0113' => 'BPA Regla',
        '0114' => 'BPA San Miguel del Padrón',
        '0115' => 'BPA Lisa',
        '0116' => 'BPA Santiago de las Vegas',
        '0117' => 'BPA La Víbora',
        '0118' => 'BPA Lawton',
        '0123' => 'BPA Alamar',
        '2012' => 'BPA Habana Vieja (Aguiar)',
        '2052' => 'BPA Centro Habana',
        '2132' => 'BPA Vedado (Línea)',
        '2212' => 'BPA Miramar',
        '7751' => 'BPA Pinar del Río (Principal)',
        '7762' => 'BPA Viñales',
        '7100' => 'BPA Principal Pinar del Río (Genérico)',
        '2582' => 'BPA Artemisa',
        '2612' => 'BPA Mariel',
        '2532' => 'BPA San Antonio de los Baños',
        '2422' => 'BPA San José de las Lajas',
        '2462' => 'BPA Güines',
        '2492' => 'BPA Santa Cruz del Norte',
        '3412' => 'BPA Matanzas (Milanés)',
        '3442' => 'BPA Varadero',
        '3452' => 'BPA Cárdenas',
        '6100' => 'BPA Matanzas (Genérico)',
        '4232' => 'BPA Santa Clara (Cuba)',
        '4292' => 'BPA Sagua la Grande',
        '4100' => 'BPA Villa Clara (Genérico)',
        '5142' => 'BPA Cienfuegos (Boulevard)',
        '5100' => 'BPA Cienfuegos (Genérico)',
        '5432' => 'BPA Sancti Spíritus',
        '5472' => 'BPA Trinidad',
        '6532' => 'BPA Ciego de Ávila',
        '6552' => 'BPA Morón',
        '6332' => 'BPA Las Tunas',
        '8100' => 'BPA Las Tunas (Genérico)',
        '6932' => 'BPA Holguín (Frexes)',
        '6992' => 'BPA Moa',
        '3100' => 'BPA Holguín (Genérico)',
        '7432' => 'BPA Bayamo',
        '7452' => 'BPA Manzanillo',
        '9100' => 'BPA Bayamo (Genérico)',
        '8351' => 'BPA Santiago (Plaza de Marte)',
        '8361' => 'BPA Santiago (Garzón)',
        '1100' => 'BPA Santiago (Genérico)',
        '9242' => 'BPA Guantánamo',
        '9272' => 'BPA Baracoa',
        '9652' => 'BPA Nueva Gerona',
        '13100' => 'BPA Isla Juventud (Genérico)',
        '9001' => 'BPA Oficina Central',
    ],
    '05' => [ // BANMET
        '0001' => 'BANMET Sucursal Principal La Habana',
        '7000' => 'BANMET Banca Remota / Nóminas',
        '0200' => 'Metropolitano Sucursal Principal',
        '0201' => 'Metropolitano Vedado (23 y L)',
        '0202' => 'Metropolitano Habana Vieja (Mercaderes)',
        '2321' => 'BANMET Vedado (23 y J)',
        '2341' => 'BANMET Rampa (23 y P)',
        '2421' => 'BANMET Habana Vieja (Obispo)',
        '2461' => 'BANMET Centro Habana (Galiano)',
        '2581' => 'BANMET Playa (3ra y 70)',
        '2621' => 'BANMET Marianao',
        '2741' => 'BANMET 10 de Octubre',
        '2781' => 'BANMET La Víbora',
        '2821' => 'BANMET Arroyo Naranjo',
        '2861' => 'BANMET Santiago de las Vegas',
        '2941' => 'BANMET Cotorro',
        '2971' => 'BANMET Guanabacoa',
        '3021' => 'BANMET Habana del Este (Alamar)',
        '3081' => 'BANMET Regla',
        '3121' => 'BANMET San Miguel del Padrón',
        '3161' => 'BANMET La Lisa',
        '9003' => 'BANMET Oficina Central',
    ],
    '15' => [ // BFI
        '0001' => 'BFI Sucursal Principal La Habana',
        '0150' => 'BFI Sucursal Principal',
        '0151' => 'BFI Vedado (Línea y L)',
        '0152' => 'BFI Miramar (7ma y 78)',
        '0154' => 'BFI Aeropuerto José Martí',
        '8000' => 'BFI Varadero',
        '8001' => 'BFI Cayo Coco',
        '8005' => 'BFI Guardalavaca',
    ],
    '14' => [ // BEC (Banco Exterior de Cuba)
        '1400' => 'BEC Sucursal Principal',
        '1401' => 'BEC Santiago de Cuba',
        '1403' => 'BEC Camagüey',
    ],
    '13' => [ // BISO (Banco de Inversiones)
        '1500' => 'BISO Sucursal Principal',
        '1503' => 'BISO Camagüey',
    ],
    '99' => [ // CASAS DE CAMBIO Y OTRAS INSTITUCIONES
        '2000' => 'CADECA Principal (La Habana)',
        '2001' => 'CADECA Obispo',
        '2005' => 'CADECA Aeropuerto José Martí',
        '2009' => 'CADECA Camagüey',
        '2007' => 'CADECA Varadero',
        '3000' => 'FINCIMEX Sucursal Principal',
        '3001' => 'FINCIMEX Vedado',
        '3005' => 'FINCIMEX Camagüey',
        '0001' => 'Banco Central de Cuba',
    ],
    '98' => [ // BANCOS INTERNACIONALES
        '5000' => 'Nova Scotia Bank',
        '5001' => 'Banco Sabadell',
        '5002' => 'BBVA',
        '5003' => 'Santander',
    ]
];

// Configuración de correo (SMTP)
$config_mail = [
    'activo'     => $config['mail_activo'] ?? '0',
    'proveedor'  => $config['mail_proveedor'] ?? 'custom',
    'host'       => $config['mail_host'] ?? '',
    'port'       => $config['mail_port'] ?? '587',
    'encryption' => $config['mail_encryption'] ?? 'tls',
    'usuario'    => $config['mail_usuario'] ?? '',
    'password'   => descifrarMailPassword($config['mail_password'] ?? ''),
    'from'       => $config['mail_from'] ?? '',
    'from_name'  => $config['mail_from_name'] ?? '',
];
$proveedores_smtp = getProveedoresSMTP();

// Configuración de Google (OAuth) - client_id y client_secret cifrados en BD
$config_google = [
    'client_id'     => descifrarSecreto($config['google_client_id'] ?? ''),
    'client_secret' => descifrarSecreto($config['google_client_secret'] ?? ''),
    'oauth_activo'  => ($config['googleoauth'] ?? 'true') === 'true',
];

// Obtener rangos de impuesto vigentes
$rangos_impuesto = $pdo->query("
    SELECT * FROM configuracion_rangos_impuesto 
    WHERE fecha_vigencia = (SELECT MAX(fecha_vigencia) FROM configuracion_rangos_impuesto)
    ORDER BY desde
")->fetchAll();

$fecha_vigencia_actual = !empty($rangos_impuesto) ? $rangos_impuesto[0]['fecha_vigencia'] : date('Y-m-d');

// Obtener tasas
$tasas = $pdo->query("SELECT * FROM configuracion_tasas ORDER BY fecha_vigencia DESC")->fetchAll();

// Estado del subsistema de Nóminas
$subsistema_nominas_activo = false;
try {
    $stmt = $pdo->query("SELECT estado FROM subsistemas WHERE codigo = '0001' LIMIT 1");
    if ($row = $stmt->fetch()) {
        $subsistema_nominas_activo = (int)$row['estado'] === 1;
    }
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
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
        html[data-theme="win11"] .glass-card { border-radius: 0; }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }
        html[data-theme="win11"] .glass-card:hover { border-color: rgba(0, 120, 212, 0.35); }
        html[data-theme="win11"] .glass-card .p-3.border-bottom:has(.card-collapse-title:hover) { background: rgba(0, 0, 0, 0.55); }
        .card-collapse-title:hover { color: #60a5fa; }

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

        .form-label { color: rgba(255, 255, 255, 0.85); font-size:0.8rem; font-weight: 700; margin-bottom:0.375rem; }
        
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
        
        /* Chevron en selects con clase form-control (card SMTP) */
        .select-wrap { position: relative; }
        .select-wrap::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0.9rem;
            transform: translateY(-50%) rotate(0deg);
            width: 1rem;
            height: 1rem;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            transition: transform 0.2s ease;
        }
        .select-wrap:focus-within::after { transform: translateY(-50%) rotate(180deg); }
        select.form-control.select-chev {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: none !important;
            padding-right: 2.5rem !important;
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
            font-size:0.78rem; font-weight: 700; text-transform: uppercase;
            letter-spacing:0.05rem; color: rgba(255, 255, 255, 0.75);
            padding-bottom:0.5rem; margin-bottom:1rem;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08);
        }
        .section-title i { color: #60a5fa; font-size:0.85rem; }

        .card-collapse-title {
            cursor: pointer;
            user-select: none;
            display: flex;
            flex-grow: 1;
            align-items: center;
            gap:0.5rem;
            font-weight: 700;
            transition: color 0.2s ease;
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
/* ============================================ */
/* RESPONSIVE CONFIGURACIÓN (PC/TABLET/MÓVIL)   */
/* Solo afecta a cada breakpoint; NO sobrescribe PC */
/* ============================================ */

/* ---------- SAFE AREA (Notch iPhone X+) ---------- */
@supports (padding: env(safe-area-inset-top)) {
    .main-container {
        padding-top: max(1.25rem, env(safe-area-inset-top));
        padding-left: max(1.25rem, env(safe-area-inset-left));
        padding-right: max(1.25rem, env(safe-area-inset-right));
        padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
    }
    .scroll-quick-btns {
        right: max(1.25rem, env(safe-area-inset-right));
        bottom: max(1.25rem, env(safe-area-inset-bottom));
    }
}

/* ---------- TABLET (≤ 1024px) ---------- */
@media (max-width: 1024px) {
    .main-container {
        margin-left: 5rem !important;
        padding: 1rem !important;
    }
    .main-container.expanded {
        margin-left: 5rem !important;
    }

    .win-topbar {
        padding: 0.75rem 1rem !important;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .page-title h1 { font-size: 1.15rem; }
    .page-title p  { font-size: 0.72rem; }

    /* Los col-lg-6 pasan a 1 columna en tablet */
    .row.g-4 > .col-lg-6 {
        flex: 1 1 100%;
        max-width: 100%;
    }

    /* Botón "Acciones rápidas" más pequeño */
    #btnAccionesRapidas {
        font-size: 0.75rem !important;
        padding: 0.4rem 0.75rem !important;
    }
}

/* ---------- MÓVIL (≤ 768px) ---------- */
@media (max-width: 768px) {
    /* ---------- BODY: prevenir scroll horizontal ---------- */
    html, body {
        overflow-x: hidden;
        max-width: 100vw;
    }

    /* ---------- SIDEBAR + CONTENEDOR ---------- */
    .win-sidebar {
        transform: translateX(-100%);
        width: 15rem;
    }
    .win-sidebar.mobile-open,
    .win-sidebar.show {
        transform: translateX(0);
    }
    .main-container {
        margin-left: 0 !important;
        padding: 0.625rem !important;
        width: 100% !important;
    }
    .main-container.expanded {
        margin-left: 0 !important;
    }

    /* ---------- TOPBAR: grid con reloj visible ---------- */
    .win-topbar {
        display: grid !important;
        grid-template-columns: 1fr auto;
        align-items: center !important;
        gap: 0.5rem !important;
        padding: 0.5rem 0.75rem !important;
        border-radius: 0.75rem !important;
    }
    .win-topbar > div:first-child {
        min-width: 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem !important;
    }
    .win-topbar > :last-child:not(:first-child) {
        justify-self: end;
        flex-shrink: 0;
    }

    .page-title {
        min-width: 0;
        flex: 1;
    }
    .page-title h1 {
        font-size: 0.95rem !important;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .win-topbar .page-title > p {
        display: none;
    }

    .sidebar-toggle {
        width: 2.25rem;
        height: 2.25rem;
        flex-shrink: 0;
    }

    /* Acciones rápidas a fila completa en móvil */
    #btnAccionesRapidas {
        width: 100%;
        justify-content: center;
        margin-left: 0 !important;
        font-size: 0.78rem !important;
        padding: 0.4rem 0.75rem !important;
        grid-column: 1 / -1;
        order: 3;
    }
    #btnAccionesRapidas.dropdown-toggle::after { margin-left: auto; }

    /* ---------- GLASS CARD ---------- */
    .glass-card {
        border-radius: 0.625rem;
    }
    html[data-theme="win11"] .glass-card { border-radius: 0; }
    .glass-card .p-4 {
        padding: 0.875rem !important;
    }
    .glass-card .p-3 {
        padding: 0.75rem !important;
    }

    /* ---------- HEADERS DE CARD COLAPSABLES ---------- */
    .card-collapse-title {
        font-size: 0.85rem;
        line-height: 1.35;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.375rem;
    }
    .card-collapse-title i:first-child {
        flex-shrink: 0;
    }
    /* Los badges (ACTIVO/INACTIVO, CONFIGURADO) bajan de línea */
    .card-collapse-title .badge {
        font-size: 0.6rem !important;
        padding: 0.15rem 0.5rem !important;
        margin-left: 0 !important;
    }

    /* Header con botón al lado (Tasas → Agregar Tasa) */
    .glass-card .p-3.border-bottom.d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .glass-card .p-3.border-bottom.d-flex.justify-content-between .btn-win {
        width: 100%;
        justify-content: center;
        font-size: 0.78rem;
    }

    /* ---------- FORMULARIOS ---------- */
    .row.g-4 > [class*="col-"],
    form .row > [class*="col-"] {
        flex: 1 1 100%;
        max-width: 100%;
    }
    .form-label {
        font-size: 0.75rem;
    }
    .form-control,
    .form-select {
        font-size: 0.82rem !important;
        padding: 0.55rem 0.75rem !important;
    }
    .input-group-text {
        font-size: 0.82rem;
        padding: 0.55rem 0.65rem;
    }
    small.text-secondary,
    .text-secondary {
        font-size: 0.68rem !important;
    }

    /* Botones de guardar/backup a fila completa */
    form > .btn-win,
    form > .btn-win-primary,
    form .btn-win.w-100 {
        width: 100% !important;
        justify-content: center;
        font-size: 0.82rem;
        padding: 0.6rem 0.875rem;
    }

    /* Botones en fila (Guardar + Probar SMTP) */
    form .d-flex.gap-2.flex-wrap {
        flex-direction: column;
    }
    form .d-flex.gap-2.flex-wrap .btn-win {
        width: 100%;
        justify-content: center;
        font-size: 0.82rem;
    }

    /* ---------- SECCIÓN BACKUP/RESTORE ---------- */
    .row.g-3 > .col-md-6 {
        flex: 1 1 100% !important;
        max-width: 100% !important;
    }
    .col-md-6 > .p-3.rounded {
        padding: 0.875rem !important;
    }
    .col-md-6 > .p-3.rounded .d-flex {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.75rem !important;
    }
    .col-md-6 > .p-3.rounded .d-flex > div:first-child {
        text-align: center;
    }
    .col-md-6 > .p-3.rounded .d-flex > .d-flex {
        flex-direction: column;
        width: 100%;
    }
    .col-md-6 > .p-3.rounded .btn-win {
        width: 100%;
        justify-content: center;
        font-size: 0.78rem;
        padding: 0.55rem 0.875rem;
    }

    /* ---------- TABLAS (rangos, tasas) ---------- */
    .table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        border-radius: 0.5rem;
        position: relative;
        margin-bottom: 0.5rem;
    }
    #rangosTable,
    #tasasTable {
        font-size: 0.75rem;
        min-width: 30rem;
    }
    #rangosTable thead th,
    #tasasTable thead th {
        font-size: 0.62rem !important;
        padding: 0.5rem 0.4rem !important;
        white-space: nowrap;
    }
    #rangosTable td,
    #tasasTable td {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.72rem !important;
        white-space: nowrap;
    }

    /* Inputs dentro de la tabla de rangos */
    #rangosTable .form-control-sm {
        padding: 0.3rem 0.5rem !important;
        font-size: 0.72rem !important;
        min-width: 5rem;
    }
    #rangosTable .btn-win-danger,
    #tasasTable .btn-win-danger {
        padding: 0.25rem 0.4rem !important;
    }

    /* Indicador de scroll horizontal en tablas */
    .table-responsive::after {
        content: '⟷ Desliza para ver más';
        display: block;
        text-align: center;
        font-size: 0.6rem;
        color: rgba(255, 255, 255, 0.35);
        padding: 0.25rem 0;
        background: rgba(0, 0, 0, 0.2);
        pointer-events: none;
    }
    /* Solo mostrar el indicador si hay scroll real */
    .table-responsive:not(:has(table:only-child))::after {
        display: none;
    }

    /* Footer de la tabla de rangos (botón Agregar Rango) */
    #rangosTable tfoot td {
        padding: 0.5rem 0.375rem !important;
    }
    #rangosTable tfoot .btn-win {
        width: 100%;
        justify-content: center;
        font-size: 0.78rem;
    }

    /* ---------- MODALES ---------- */
    .modal-content-win {
        border-radius: 0 !important;
        min-height: 100vh;
    }
    .modal-dialog {
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        min-height: 100vh;
    }
    .modal-header-win {
        border-radius: 0 !important;
        padding: 0.75rem 0.875rem !important;
    }
    .modal-title {
        font-size: 0.9rem;
    }
    .modal-body {
        padding: 0.875rem !important;
        max-height: calc(100vh - 10rem);
        overflow-y: auto;
    }
    .modal-footer-win {
        border-radius: 0 !important;
        padding: 0.625rem 0.875rem !important;
        flex-wrap: wrap;
        gap: 0.375rem;
    }
    .modal-footer-win .btn-win {
        flex: 1 1 calc(50% - 0.25rem);
        justify-content: center;
        font-size: 0.82rem;
    }

    /* ---------- ALERTAS ---------- */
    .alert {
        padding: 0.75rem 0.875rem !important;
        font-size: 0.82rem;
    }
    .alert strong {
        display: block;
        margin-bottom: 0.25rem;
    }
    .alert .btn-close {
        top: 0.5rem;
        right: 0.5rem;
    }
    /* Alerta de recomendación de backup: el icono y texto a columna */
    #alertBackupRecomendacion .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }

    /* ---------- CHECKBOX DE RESTORE ---------- */
    #restoreModal .form-check {
        font-size: 0.78rem;
    }
    #restoreModal .form-check ul {
        margin-left: 0.5rem !important;
    }
    #restoreModal .form-check li {
        font-size: 0.72rem;
        line-height: 1.4;
    }

    /* ---------- BOTÓN DE ARCHIVO (Restore) ---------- */
    #chooseFileLabel {
        flex-wrap: wrap;
        height: auto !important;
        padding: 0.625rem 0.75rem !important;
        gap: 0.5rem;
    }
    #chooseFileLabel .ms-auto.btn-win {
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
        margin-left: 0 !important;
    }

    /* ---------- RELOJ ---------- */
    #liveClock {
        min-width: 4.5rem;
        font-size: 0.8rem;
    }
    .date-badge {
        padding: 0.375rem 0.625rem;
        font-size: 0.78rem;
    }

    /* ---------- BOTONES FLOTANTES ---------- */
    .scroll-quick-btns {
        right: 0.75rem !important;
        bottom: 0.75rem !important;
        gap: 0.5rem !important;
    }
    .scroll-quick-btn {
        width: 2.25rem !important;
        height: 2.25rem !important;
        font-size: 0.85rem !important;
    }

    /* ---------- FOOTER ---------- */
    footer, .footer {
        font-size: 0.7rem !important;
        padding: 0.75rem !important;
    }

    /* ---------- SELECTS CON CHEVRON (SMTP) ---------- */
    .select-wrap::after {
        right: 0.75rem;
        width: 0.85rem;
        height: 0.85rem;
    }
}

/* ---------- MÓVIL PEQUEÑO (≤ 480px) ---------- */
@media (max-width: 480px) {
    .main-container {
        padding: 0.4rem !important;
    }
    .glass-card .p-4 {
        padding: 0.75rem !important;
    }
    .page-title h1 {
        font-size: 0.85rem !important;
        max-width: 55vw;
    }
    .win-topbar {
        padding: 0.4rem 0.5rem !important;
        grid-template-columns: 1fr;
        row-gap: 0.5rem;
    }
    .win-topbar > :last-child:not(:first-child) {
        justify-self: center;
        width: 100%;
    }

    /* Botones de guardar más compactos */
    .btn-win,
    .btn-win-primary,
    .btn-win-success,
    .btn-win-danger,
    .btn-win-warning,
    .btn-win-info {
        font-size: 0.72rem !important;
        padding: 0.4rem 0.7rem !important;
    }

    /* Formularios compactos */
    .form-control,
    .form-select {
        font-size: 0.78rem !important;
        padding: 0.5rem 0.65rem !important;
    }
    .form-label {
        font-size: 0.7rem;
    }

    /* Tablas ultra compactas */
    #rangosTable,
    #tasasTable {
        min-width: 26rem;
        font-size: 0.7rem;
    }
    #rangosTable thead th,
    #tasasTable thead th {
        font-size: 0.58rem !important;
        padding: 0.4rem 0.3rem !important;
    }
    #rangosTable td,
    #tasasTable td {
        padding: 0.35rem 0.3rem !important;
        font-size: 0.68rem !important;
    }
    #rangosTable .form-control-sm {
        font-size: 0.68rem !important;
        padding: 0.25rem 0.4rem !important;
        min-width: 4rem;
    }

    /* Reloj */
    #liveClock {
        font-size: 0.78rem;
    }

    /* Cards headers con badge */
    .card-collapse-title {
        font-size: 0.8rem;
    }
    .card-collapse-title .badge {
        font-size: 0.55rem !important;
    }
}

/* ---------- PANTALLAS GRANDES (≥ 1400px) ---------- */
@media (min-width: 1400px) {
    .main-container {
        max-width: 1800px;
        margin: 0 auto 0 16.25rem;
    }
    .main-container.expanded {
        margin-left: 5rem;
    }
}

/* ---------- ORIENTACIÓN HORIZONTAL EN MÓVIL ---------- */
@media (max-width: 900px) and (orientation: landscape) {
    .modal-dialog {
        min-height: auto;
        max-height: 96vh;
    }
    .modal-content-win {
        min-height: auto;
        max-height: 96vh;
        border-radius: 1rem !important;
    }
    .modal-body {
        max-height: calc(96vh - 10rem);
    }
    /* En horizontal, los formularios vuelven a 2 columnas */
    form .row > .col-md-6 {
        flex: 1 1 calc(50% - 0.5rem);
        max-width: calc(50% - 0.5rem);
    }
    form .row > .col-md-4 {
        flex: 1 1 calc(33.333% - 0.5rem);
        max-width: calc(33.333% - 0.5rem);
    }
    .row.g-4 > .col-lg-6 {
        flex: 1 1 calc(50% - 0.5rem);
        max-width: calc(50% - 0.5rem);
    }
}

/* ==================== INFORMACIÓN BANCARIA ==================== */
/* Para campos bancarios readonly */
input[name="banco"],
input[name="sucursal"],
input[name="bancocliente"] {
    background: var(--panel-2) !important;
    border: 1px solid var(--border) !important;
    color: var(--txt) !important;
    font-weight: 500 !important;
}

input.banco-desconocido {
    border-left: 3px solid #e81123 !important;
    animation: pulseWarning 2s infinite;
}

input.sucursal-no-identificada {
    border-left: 3px solid #ff8c00 !important;
    animation: pulseWarning 2s infinite;
}

@keyframes pulseWarning {
    0% { border-left-color: rgba(232, 17, 35, 0.5); }
    50% { border-left-color: #e81123; }
    100% { border-left-color: rgba(232, 17, 35, 0.5); }
}

/* Contenedor de información de sucursal */
#sucursalInfo {
    display: none;
    background: var(--panel-2);
    border: 1px dashed var(--border) !important;
    border-radius: 0.5rem;
    padding: 0.75rem 0.875rem;
    margin-top: 8px;
    font-size: 13px;
    color: var(--txt);
    line-height: 1.4;
    animation: fadeIn 0.3s ease;
}

#sucursalInfo i {
    color: var(--accent);
    margin-right: 6px;
    font-size: 14px;
}

#sucursalInfo strong {
    font-weight: 600;
    color: var(--txt);
}

#sucursalInfo .text-muted {
    color: var(--muted) !important;
    font-size: 12px;
    display: block;
    margin-top: 2px;
}

/* Información adicional */
#infoAdicional {
    margin-top: 12px;
}

#infoAdicional .alert {
    font-size: 12px;
}

/* ==================== DESGLOSE DE CUENTA BANCARIA ==================== */
.desglose-overlay {
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    z-index: 11000 !important;
    display: none;
    backdrop-filter: blur(5px);
}

.desglose-overlay.open {
    display: block;
}

.cuenta-desglose {
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    z-index: 11001 !important;
    width: 480px;
    max-width: 95vw;
    background: var(--panel-2);
    border: 1px solid var(--accent);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.7);
    display: none;
    color: var(--txt);
}

@media (max-width: 768px) {
    .cuenta-desglose {
        width: 95vw;
        max-height: 85vh;
        padding: 16px;
    }
}

.desglose-close-btn {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background: var(--panel) !important;
    border: 1px solid var(--border) !important;
    color: var(--txt) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    font-size: 14px !important;
    z-index: 11001 !important;
    padding: 0 !important;
    margin: 0 !important;
    transition: all 0.2s ease !important;
}

.desglose-close-btn:hover {
    background: #e81123 !important;
    color: white !important;
    border-color: #e81123 !important;
    transform: scale(1.1) !important;
}

.desglose-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.desglose-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 600;
    color: var(--txt);
    margin: 0;
}

.desglose-title i {
    color: var(--accent);
    font-size: 20px;
}

.desglose-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    flex-wrap: wrap;
}

.desglose-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.desglose-item span:first-child {
    color: var(--muted);
    flex: 1;
    min-width: 120px;
    font-weight: 500;
}

.desglose-valor {
    font-weight: 500;
    color: var(--txt);
    text-align: right;
    flex: 2;
    max-width: 250px;
    word-break: break-word;
    line-height: 1.4;
}

.desglose-numero {
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 16px;
    letter-spacing: 1px;
    margin: 15px 0;
    text-align: center;
    padding: 12px;
    background: var(--panel);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    color: var(--txt);
    line-height: 1.6;
    overflow-wrap: break-word;
    word-break: break-all;
}

.cuenta-desglose .alert {
    border-radius: 0.375rem;
    font-size: 13px;
    padding: 12px;
    margin: 12px 0;
    border-width: 1px;
    border-style: solid;
    line-height: 1.5;
}

.cuenta-desglose .alert-danger {
    background: rgba(232, 17, 35, 0.1) !important;
    border-color: rgba(232, 17, 35, 0.2) !important;
    color: #e81123 !important;
}

.cuenta-desglose .alert-warning {
    background: rgba(255, 140, 0, 0.1) !important;
    border-color: rgba(255, 140, 0, 0.2) !important;
    color: #ff8c00 !important;
}

.cuenta-desglose .alert-info {
    background: rgba(0, 120, 212, 0.1) !important;
    border-color: rgba(0, 120, 212, 0.2) !important;
    color: var(--txt) !important;
}

.cuenta-desglose .alert-success {
    background: rgba(16, 124, 16, 0.1) !important;
    border-color: rgba(16, 124, 16, 0.2) !important;
    color: #107c10 !important;
}

.cuenta-desglose::-webkit-scrollbar {
    width: 6px;
}

.cuenta-desglose::-webkit-scrollbar-track {
    background: var(--panel);
    border-radius: 3px;
}

.cuenta-desglose::-webkit-scrollbar-thumb {
    background: var(--accent);
    border-radius: 3px;
}

.cuenta-desglose::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--accent) 80%, #000);
}

.cuenta-desglose .btn-primary {
    background: var(--accent) !important;
    border: none !important;
    border-radius: 0.375rem !important;
    padding: 10px 20px !important;
    font-weight: 500 !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
    margin-top: 15px !important;
    width: 100% !important;
}

.cuenta-desglose .btn-primary:hover {
    background: color-mix(in srgb, var(--accent) 90%, #000) !important;
    transform: translateY(-2px);
}
/* ==================== FIN DE INFORMACIÓN BANCARIA ==================== */

/* Selector de minutos tipo reloj analógico */
.lock-clock { display:flex; flex-direction:column; align-items:center; gap:0.5rem; }
.lock-clock-svg { width:4.75rem; height:4.75rem; display:block; cursor:pointer; touch-action:none; border-radius:50%; }
.lock-clock-svg:focus-visible { outline:0.125rem solid #60a5fa; outline-offset:0.1875rem; }
.lock-clock-face { fill:var(--panel); stroke:rgba(255,255,255,0.14); stroke-width:3; }
.lock-clock-tick { stroke:rgba(255,255,255,0.45); stroke-width:2.5; }
.lock-clock-tick.major { stroke:#60a5fa; stroke-width:3.5; }
.lock-clock-num { fill:rgba(255,255,255,0.7); font-size:22px; font-weight:600; }
.lock-clock-hand { stroke:#60a5fa; stroke-width:6; stroke-linecap:round; }
.lock-clock-hub { fill:#60a5fa; }
.lock-clock-readout { font-size:0.85rem; font-weight:700; color:#60a5fa; background:rgba(0,120,212,0.15); border:0.0625rem solid rgba(96,165,250,0.35); border-radius:0.5rem; padding:0.15rem 0.6rem; }
#sistemaForm .row > .border { border-color:var(--border) !important; }
html[data-theme="light"] .lock-clock-face { fill:#ffffff; stroke:rgba(0,0,0,0.25); }
html[data-theme="light"] .lock-clock-tick { stroke:rgba(0,0,0,0.4); }
html[data-theme="light"] .lock-clock-tick.major { stroke:var(--accent-dark, #0078d4); }
html[data-theme="light"] .lock-clock-num { fill:rgba(0,0,0,0.65); }
html[data-theme="light"] .lock-clock-hand { stroke:var(--accent-dark, #0078d4); fill:none; }
html[data-theme="light"] .lock-clock-hub { fill:var(--accent-dark, #0078d4); }
html[data-theme="light"] .lock-clock-readout { color:var(--accent-dark, #0078d4); background:rgba(0,120,212,0.12); border:0.0625rem solid rgba(0,120,212,0.3); }
html[data-theme="orgullo"] .lock-clock-face { fill:#ffffff; stroke:rgba(84,52,142,0.35); }
html[data-theme="orgullo"] .lock-clock-tick { stroke:rgba(84,52,142,0.45); }
html[data-theme="orgullo"] .lock-clock-tick.major { stroke:#7c3aed; }
html[data-theme="orgullo"] .lock-clock-num { fill:rgba(51,38,77,0.7); }
html[data-theme="orgullo"] .lock-clock-hand { stroke:#7c3aed; fill:none; }
html[data-theme="orgullo"] .lock-clock-hub { fill:#7c3aed; }
html[data-theme="orgullo"] .lock-clock-readout { color:#7c3aed; background:rgba(139,92,246,0.15); border:0.0625rem solid rgba(167,139,250,0.35); }
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
            <div class="dropdown ms-auto">
                <button type="button" class="btn-win btn-win-sm dropdown-toggle" id="btnAccionesRapidas" title="Acciones rápidas" data-tooltip="Acciones rápidas" data-tooltip-theme="primary" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bolt me-2"></i>Acciones
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-win">
                    <li>
                        <button type="button" class="dropdown-item" id="btnToggleAllCards" title="Expandir o colapsar todas las secciones">
                            <i class="fas fa-expand-alt me-2"></i><span id="btnToggleAllCardsTexto">Expandir todo</span>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item" id="btnDropGuardarTodo" title="Guardar toda la configuración">
                            <i class="fas fa-save me-2" style="color: var(--color-success);"></i>Guardar toda la configuración
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" id="btnDropRecargar" title="Recargar datos y actualizar página">
                            <i class="fas fa-sync-alt me-2" style="color: #60a5fa;"></i>Recargar datos y actualizar página
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Exportar / Imprimir</h6></li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="print" title="Imprimir todos los datos de configuración">
                            <i class="fas fa-print me-2" style="color: #60a5fa;"></i>Imprimir
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="pdf" title="Exportar a PDF (.pdf)">
                            <i class="fas fa-file-pdf me-2" style="color: #ef4444;"></i>Exportar a PDF (.pdf)
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="excel" title="Exportar a Excel (.xlsx)">
                            <i class="fas fa-file-excel me-2" style="color: var(--color-success);"></i>Exportar a Excel (.xlsx)
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="word" title="Exportar a Word (.doc)">
                            <i class="fas fa-file-word me-2" style="color: #3b82f6;"></i>Exportar a Word (.doc)
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="txt" title="Exportar a TXT (.txt)">
                            <i class="fas fa-file-alt me-2" style="color: #f59e0b;"></i>Exportar a TXT (.txt)
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-export="csv" title="Exportar a CSV (.csv)">
                            <i class="fas fa-file-csv me-2" style="color: #22d3ee;"></i>Exportar a CSV (.csv)
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Mensajes de alerta -->
    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show mb-4 fade-in-up" role="alert" id="configAlertMsg">
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
                <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDB" aria-expanded="false" aria-controls="collapseDB">
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
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseConfigGeneral" aria-expanded="false" aria-controls="collapseConfigGeneral">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-sliders-h me-2" style="color: #60a5fa;"></i> Configuración General
                    </h6>
                </div>
                <div id="collapseConfigGeneral" class="collapse">
                <div class="p-4">
                    <form method="POST" id="configGeneralForm">
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
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Recargo Hora Extra Diurna (multiplicador)</label>
                                <input type="number" step="0.01" class="form-control" name="recargo_extra_diurna" value="<?php echo htmlspecialchars($config['recargo_extra_diurna'] ?? '1.50'); ?>" title="Multiplicador de hora extra diurna (1.5 = 150%)" data-tooltip="Multiplicador de hora extra diurna (1.5 = 150%)" data-tooltip-theme="info">
                                <small class="text-secondary">1.5 = 150% del salario hora</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Recargo Hora Extra Nocturna (multiplicador)</label>
                                <input type="number" step="0.01" class="form-control" name="recargo_extra_nocturna" value="<?php echo htmlspecialchars($config['recargo_extra_nocturna'] ?? '2.00'); ?>" title="Multiplicador de hora extra nocturna Nt 7-23h y Nt 23-7h (2.0 = 200%)" data-tooltip="Multiplicador de hora extra nocturna (2.0 = 200%)" data-tooltip-theme="info">
                                <small class="text-secondary">2.0 = 200% del salario hora</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Recargo Doble Turno (multiplicador)</label>
                                <input type="number" step="0.01" class="form-control" name="recargo_doble_turno" value="<?php echo htmlspecialchars($config['recargo_doble_turno'] ?? '2.00'); ?>" title="Multiplicador de doble turno (2.0 = 200%)" data-tooltip="Multiplicador de doble turno (2.0 = 200%)" data-tooltip-theme="info">
                                <small class="text-secondary">2.0 = 200% del salario hora</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" name="guardar_config_general" class="btn-win btn-win-primary" title="Guardar configuración general" data-tooltip="Guardar configuración general" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Configuración General
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Tasas del Sistema -->
        <div class="col-lg-6 fade-in-up" style="animation-delay: 0.1s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTasas" aria-expanded="false" aria-controls="collapseTasas">
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
        
        <!-- Rangos de Impuesto (Ingresos Personales) -->
        <div class="col-12 fade-in-up" style="animation-delay: 0.11s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseRangos" aria-expanded="false" aria-controls="collapseRangos">
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
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][desde]" value="15000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][hasta]" value="20000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][tasa]" value="7.5" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[3][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
                                        <tr><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][desde]" value="20000.01" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][hasta]" value="25000"></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][tasa]" value="10" required></td><td><input type="number" step="0.01" class="form-control form-control-sm" name="rangos[4][monto_fijo]" value="0"></td><td><button type="button" class="btn-win btn-win-danger btn-win-sm" onclick="confirmarEliminarRango(this)" title="Eliminar rango" data-tooltip="Eliminar rango" data-tooltip-theme="danger"><i class="fas fa-trash"></i></button></td></tr>
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
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" name="guardar_rangos" class="btn-win btn-win-primary" title="Guardar rangos de impuesto" data-tooltip="Guardar rangos de impuesto" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Rangos de Impuesto
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Datos de la Entidad -->
        <div class="col-12 fade-in-up" style="animation-delay: 0.12s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDatosEntidad" aria-expanded="false" aria-controls="collapseDatosEntidad">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-building me-2" style="color: #a78bfa;"></i> Datos de la Entidad
                    </h6>
                </div>
                <div id="collapseDatosEntidad" class="collapse">
                <div class="p-4">
                    <form method="POST" id="datosEntidadForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de la Empresa/PDL/CCS/MyPime/TCP</label>
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
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" name="guardar_datos_entidad" class="btn-win btn-win-primary" title="Guardar datos de la entidad" data-tooltip="Guardar datos de la entidad" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Datos de la Entidad
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>

        <!-- Personal Autorizado -->
        <div class="col-12 fade-in-up" style="animation-delay: 0.13s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapsePersonal" aria-expanded="false" aria-controls="collapsePersonal">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-user-tie me-2" style="color: #f472b6;"></i> Personal Autorizado y Especialistas
                    </h6>
                </div>
                <div id="collapsePersonal" class="collapse">
                <div class="p-4">
                    <form method="POST" id="datosPersonalForm">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Jefe / Director</label>
                                <input type="text" class="form-control" name="jefe_proyecto" value="<?php echo htmlspecialchars($config['jefe_proyecto'] ?? JEFE_PROYECTO); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Especialista en Gestión Económica</label>
                                <input type="text" class="form-control" name="especialista_gestion" value="<?php echo htmlspecialchars($config['especialista_gestion'] ?? ESPECIALISTA); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Especialista en Nóminas</label>
                                <input type="text" class="form-control" name="especialista_nominas" value="<?php echo htmlspecialchars($config['especialista_nominas'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Especialista en Gestión de los Recursos Humanos</label>
                                <input type="text" class="form-control" name="especialista_gestionRRHH" value="<?php echo htmlspecialchars($config['especialista_gestionRRHH'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Intendente Local del CAM</label>
                                <input type="text" class="form-control" name="intendente" value="<?php echo htmlspecialchars($config['intendente'] ?? 'Eladio Francisco Ávalos'); ?>">
                                <small class="text-secondary">Aprueba la plantilla de cargos</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" name="guardar_datos_personal" class="btn-win btn-win-primary" title="Guardar personal autorizado" data-tooltip="Guardar personal autorizado" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Personal Autorizado
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>

        <!-- Información Bancaria -->
        <div class="col-12 fade-in-up" style="animation-delay: 0.14s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseBancaria" aria-expanded="false" aria-controls="collapseBancaria">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-coins me-2" style="color: #60a5fa;"></i> Información Bancaria
                        <i class="fas fa-info-circle info-icon ms-2" onclick="toggleDesgloseCuenta(event)" style="cursor:pointer; color: var(--accent); font-size:0.85rem;" title="Ver estructura de la cuenta bancaria" data-tooltip="Ver estructura de la cuenta bancaria" data-tooltip-theme="primary"></i>
                    </h6>
                </div>
                <div id="collapseBancaria" class="collapse">
                <div class="p-4">
                    <form method="POST" id="bancariaForm">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label d-flex justify-content-between align-items-center">
                                    <span>Cuenta Bancaria <i class="fas fa-info-circle info-icon ms-1" onclick="toggleDesgloseCuenta(event)" style="cursor:pointer; color: var(--accent);" title="Ver desglose de la cuenta" data-tooltip="Ver desglose de la cuenta" data-tooltip-theme="primary"></i></span>
                                    <span class="badge" style="background: var(--color-success); font-size: 10px;">14/16 d&iacute;gitos</span>
                                </label>
                                <input type="text" class="form-control" name="cuenta_bancaria" id="cuentaBancaria"
                                    value="<?php echo htmlspecialchars($config['cuenta_bancaria'] ?? ''); ?>"
                                    oninput="actualizarDesglose()" maxlength="16">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Banco Instituci&oacute;n (Autom&aacute;tico)</label>
                                <input type="text" class="form-control fw-bold" name="banco" id="nombreBanco"
                                    value="<?php echo htmlspecialchars($config['banco'] ?? ''); ?>" readonly 
                                    style="background-color: var(--panel-2); color: var(--accent) !important;">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label class="form-label">Sucursal</label>
                                <input type="text" class="form-control text-center fw-bold" name="sucursal" id="nombreSucursal" readonly>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Tipo de Cuenta</label>
                                <input type="text" class="form-control" name="tipocuenta" id="tipoCuentaInput" readonly>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">N&uacute;mero de Cliente</label>
                                <input type="text" class="form-control fw-bold" name="bancocliente" id="nocliente" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div id="sucursalInfo" class="w-100 rounded border p-3" 
                                     style="background-color: var(--panel-2); min-height: 45px; border-style: dashed !important;">
                                    <span class="text-muted small">An&aacute;lisis de cuenta bancaria...</span>
                                </div>
                                <div id="infoAdicional" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            <button type="submit" name="guardar_datos_bancarios" class="btn-win btn-win-primary" title="Guardar información bancaria" data-tooltip="Guardar información bancaria" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Información Bancaria
                            </button>
                        </div>
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
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseMail" aria-expanded="false" aria-controls="collapseMail">
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
                                <div class="select-wrap">
                                <select class="form-control select-chev" name="mail_activo" id="mail_activo" title="Activar/desactivar correo" data-tooltip="Activar/desactivar correo" data-tooltip-theme="secondary">
                                    <option value="1" <?php echo $config_mail['activo'] === '1' ? 'selected' : ''; ?>>Sí, activado</option>
                                    <option value="0" <?php echo $config_mail['activo'] !== '1' ? 'selected' : ''; ?>>No, desactivado</option>
                                </select>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Proveedor SMTP</label>
                                <div class="select-wrap">
                                <select class="form-control select-chev" name="mail_proveedor" id="mail_proveedor" title="Proveedor de correo" data-tooltip="Proveedor de correo" data-tooltip-theme="secondary">
                                    <?php foreach ($proveedores_smtp as $clave => $prov): ?>
                                        <option value="<?php echo $clave; ?>" <?php echo $config_mail['proveedor'] === $clave ? 'selected' : ''; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cifrado</label>
                                <div class="select-wrap">
                                <select class="form-control select-chev" name="mail_encryption" id="mail_encryption" title="Tipo de encriptación" data-tooltip="Tipo de encriptación" data-tooltip-theme="secondary">
                                    <option value="tls" <?php echo $config_mail['encryption'] === 'tls' ? 'selected' : ''; ?>>STARTTLS (puerto 587)</option>
                                    <option value="ssl" <?php echo $config_mail['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL/TLS (puerto 465)</option>
                                    <option value="none" <?php echo $config_mail['encryption'] === 'none' ? 'selected' : ''; ?>>Sin cifrado</option>
                                </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Servidor SMTP (Host)</label>
                                <input type="text" class="form-control" name="mail_host" id="mail_host" value="<?php echo htmlspecialchars($config_mail['host']); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Puerto</label>
                                <input type="number" class="form-control" name="mail_port" id="mail_port" value="<?php echo htmlspecialchars($config_mail['port']); ?>" min="1" max="65535">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Usuario SMTP</label>
                                <input type="text" class="form-control" name="mail_usuario" id="mail_usuario" value="<?php echo htmlspecialchars($config_mail['usuario']); ?>" placeholder="cuenta@gmail.com" autocomplete="off">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Contraseña SMTP</label>
                                <div class="password-wrapper">
                                <input type="password" class="form-control" name="mail_password" id="mail_password" value="<?php echo htmlspecialchars($config_mail['password']); ?>" placeholder="••••••••••••" autocomplete="new-password">
                                <button type="button" class="password-toggle" id="toggleMailPassword" tabindex="-1" title="Mostrar/ocultar contraseña" data-tooltip="Mostrar/ocultar contraseña" data-tooltip-theme="warning"><i class="fas fa-eye"></i></button>
                                </div>
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

    <!-- Configuración de Google (OAuth) -->
    <div class="row g-4 mt-1">
        <div class="col-12 fade-in-up" style="animation-delay: 0.12s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseGoogle" aria-expanded="false" aria-controls="collapseGoogle">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fab fa-google me-2" style="color: #ea4335;"></i> Configuración Google (OAuth 2.0)
                        <span class="badge ms-2" style="background: <?php echo (!empty($config_google['client_id'])) ? 'var(--color-success)' : '#ef4444'; ?>; font-size:0.65rem;"><?php echo (!empty($config_google['client_id'])) ? 'CONFIGURADO' : 'NO CONFIGURADO'; ?></span>
                    </h6>
                </div>
                <div id="collapseGoogle" class="collapse">
                <div class="p-4">
                    <form method="POST" id="googleForm">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check form-switch" style="display:flex; align-items:center; gap:0.625rem; padding-left:0; margin-bottom:0;">
                                    <input type="checkbox" class="form-check-input" style="width:2.4em; height:1.25em; cursor:pointer; margin-left:0;" name="googleoauth" id="googleoauth" value="1" <?php echo $config_google['oauth_activo'] ? 'checked' : ''; ?> onchange="toggleGoogleOauth()">
                                    <label class="form-check-label mb-0" for="googleoauth" id="googleoauthLabel" style="color:#d1d5db; cursor:pointer; font-size:0.9rem;"><i class="fas fa-power-off me-1"></i> Activar/Desactivar Servicio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Google Client ID</label>
                                <input type="text" class="form-control" name="google_client_id" id="google_client_id" value="<?php echo htmlspecialchars($config_google['client_id']); ?>" placeholder="5820987538-...apps.googleusercontent.com" autocomplete="off" <?php echo $config_google['oauth_activo'] ? '' : 'disabled'; ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Google Client Secret</label>
                                <div class="password-wrapper">
                                <input type="password" class="form-control" name="google_client_secret" id="google_client_secret" value="<?php echo htmlspecialchars($config_google['client_secret']); ?>" placeholder="GOCSPX-..." autocomplete="new-password" <?php echo $config_google['oauth_activo'] ? '' : 'disabled'; ?>>
                                <button type="button" class="password-toggle" id="toggleGoogleSecret" tabindex="-1" title="Mostrar/ocultar secret" data-tooltip="Mostrar/ocultar secret" data-tooltip-theme="warning" <?php echo $config_google['oauth_activo'] ? '' : 'disabled'; ?>><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="guardar_config_google" class="btn-win btn-win-primary" title="Guardar configuración de Google" data-tooltip="Guardar configuración de Google" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                        </div>
                        <p class="text-secondary mt-3 mb-0" style="font-size:0.78rem;">
                            <i class="fas fa-info-circle me-1"></i>Obtenga sus credenciales en <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" style="color: #60a5fa;">Google Cloud Console</a>. El Client ID y Client Secret se almacenan cifrados en la base de datos.
                        </p>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Otras Configuraciones del Sistema -->
    <div class="row g-4 mt-1">
        <div class="col-12 fade-in-up" style="animation-delay: 0.14s;">
            <div class="glass-card">
                <div class="p-3 border-bottom border-white-10">
                    <h6 class="mb-0 fw-semibold card-collapse-title collapsed" data-bs-toggle="collapse" data-bs-target="#collapseSistema" aria-expanded="false" aria-controls="collapseSistema">
                        <i class="fas fa-chevron-down collapse-chevron"></i><i class="fas fa-wrench me-2" style="color: #60a5fa;"></i> Otras Configuraciones del Sistema
                        <span class="badge ms-2" id="badgeSistemaEstado" style="background: <?php echo $subsistema_nominas_activo ? 'var(--color-success)' : '#ef4444'; ?>; font-size:0.65rem;">SISTEMA/<?php echo $subsistema_nominas_activo ? 'ACTIVO' : 'INACTIVO'; ?></span>
                        <span class="badge ms-2" id="badgeSistemaMin" style="background: #0078d4; font-size:0.65rem;"><?php echo (int)($config['tiempo_para_bloqueo'] ?? 10); ?> MIN</span>
                    </h6>
                </div>
                <div id="collapseSistema" class="collapse">
                <div class="p-4">
                    <form method="POST" id="sistemaForm">
                        <div class="row">
                            <div class="col-md-4 mb-3 border rounded p-3 text-center">
                                <label class="form-label d-block">Subsistema de Nóminas</label>
                                <div class="form-check form-switch" style="display:flex; align-items:center; justify-content:center; gap:0.625rem; padding-left:0; margin-bottom:0;">
                                    <input type="checkbox" class="form-check-input" style="width:2.4em; height:1.25em; cursor:pointer; margin-left:0;" name="subsistema_nominas" id="subsistema_nominas" value="1" <?php echo $subsistema_nominas_activo ? 'checked' : ''; ?>>
                                    <label class="form-check-label mb-0" for="subsistema_nominas" style="color:#d1d5db; cursor:pointer; font-size:0.9rem;"><i class="fas fa-power-off me-1"></i> Activar/Desactivar</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 border rounded p-3 text-center">
                                <label class="form-label d-block">Tiempo antes de cerrarse la sesión<br>al estar el bloqueo de pantalla</label>
                                <div class="lock-clock mx-auto">
                                    <svg class="lock-clock-svg" id="lockClockSvg" viewBox="0 0 200 200" tabindex="0" role="slider" aria-label="Minutos para bloqueo de sesión" aria-valuemin="1" aria-valuemax="60" aria-valuenow="<?php echo (int)($config['tiempo_para_bloqueo'] ?? 10); ?>">
                                        <circle cx="100" cy="100" r="94" class="lock-clock-face"></circle>
                                        <g id="lockClockTicks"></g>
                                        <text x="100" y="42" class="lock-clock-num" text-anchor="middle">60</text>
                                        <text x="160" y="105" class="lock-clock-num" text-anchor="middle">15</text>
                                        <text x="100" y="170" class="lock-clock-num" text-anchor="middle">30</text>
                                        <text x="40" y="105" class="lock-clock-num" text-anchor="middle">45</text>
                                        <line id="lockClockHand" x1="100" y1="100" x2="100" y2="30" class="lock-clock-hand"></line>
                                        <circle cx="100" cy="100" r="6" class="lock-clock-hub"></circle>
                                    </svg>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="lock-clock-readout" id="lockClockReadout">10 min</div>
                                        <button type="button" id="btnSistemaDefault" class="btn-win" style="padding:0.3rem 0.55rem; font-size:0.75rem; line-height:1; border-radius:8px;" title="Restaurar valor por defecto" data-tooltip="Restaurar valor por defecto" data-tooltip-theme="primary"><i class="fas fa-undo"></i></button>
                                    </div>
                                    <input type="hidden" name="tiempo_para_bloqueo" id="tiempo_para_bloqueo" value="<?php echo (int)($config['tiempo_para_bloqueo'] ?? 10); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 border rounded p-3 text-center">
                                <?php
                                require_once '../includes/licencia.php';
                                $lic_info_datos  = licencia_leer();
                                $lic_info_activa = licencia_activada() && $lic_info_datos !== null;
                                $lic_info_vence  = ($lic_info_datos !== null) ? licencia_vencimiento($lic_info_datos) : null;
                                ?>
                                <label class="form-label d-block text-secondary mb-2"><i class="fas fa-id-card me-1" style="color: #60a5fa;"></i> Licencia del Sistema</label>
                                <span class="badge d-inline-block mb-2" style="background: <?php echo $lic_info_activa ? 'var(--color-success)' : ($lic_info_datos !== null && $lic_info_vence !== null && $lic_info_vence < time() ? '#ef4444' : '#f59e0b'); ?>; font-size:0.7rem;">
                                    <?php echo htmlspecialchars(licencia_etiqueta_estado($lic_info_datos, 'LICENCIA ', $lic_info_activa)); ?>
                                </span>
                                <div class="w-100 small text-start" style="color:#cbd5e1;">
                                    <div class="d-flex justify-content-between border-bottom border-white-10 py-1">
                                        <span class="text-secondary">Registro</span>
                                        <span class="fw-semibold text-end" style="max-width:58%; word-break:break-word;"><?php echo htmlspecialchars(($lic_info_datos['registro'] ?? '') !== '' ? $lic_info_datos['registro'] : '—'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom border-white-10 py-1">
                                        <span class="text-secondary">Usuario</span>
                                        <span class="fw-semibold text-end" style="max-width:58%; word-break:break-word;"><?php echo htmlspecialchars(($lic_info_datos['usuario'] ?? '') !== '' ? $lic_info_datos['usuario'] : '—'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom border-white-10 py-1">
                                        <span class="text-secondary">Tipo</span>
                                        <span class="fw-semibold text-end"><?php echo htmlspecialchars(($lic_info_datos['info']['nombre'] ?? '') !== '' ? $lic_info_datos['info']['nombre'] : '—'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom border-white-10 py-1">
                                        <span class="text-secondary">Vence el</span>
                                        <span class="fw-semibold text-end"><?php echo htmlspecialchars(licencia_texto_vencimiento($lic_info_datos)); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom border-white-10 py-1">
                                        <span class="text-secondary">Huella del PC</span>
                                        <span class="fw-semibold text-end" style="font-family:'Consolas','Courier New',monospace; font-size:0.78rem;"><?php echo htmlspecialchars(licencia_fingerprint_equipo()); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-secondary">Licencia</span>
                                        <span class="fw-semibold text-end" style="font-family:'Consolas','Courier New',monospace; font-size:0.78rem;"><?php echo htmlspecialchars(($lic_info_datos !== null && ($lic_info_datos['serial'] ?? '') !== '') ? licencia_formatear_serial($lic_info_datos['serial']) : '—'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="guardar_config_sistema" class="btn-win btn-win-primary" title="Guardar otras configuraciones del sistema" data-tooltip="Guardar otras configuraciones del sistema" data-tooltip-theme="success">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                        </div>
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
                        <input type="file" name="backup_file" id="restoreFile" accept=".sql,.zip" required class="visually-hidden">
                        <label for="restoreFile" id="chooseFileLabel" role="button" tabindex="0" class="form-control d-flex align-items-center" style="cursor:pointer;height:2.875rem;border-radius:0.5rem;">
                            <i class="fas fa-folder-open me-2 text-teal" style="color:var(--accent,#14b8a6);"></i>
                            <span id="restoreFileName" class="text-secondary">Seleccionar archivo…</span>
                            <span class="ms-auto btn-win btn-win-primary btn-win-sm">Examinar</span>
                        </label>
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

<!-- Modal de confirmación para guardado general -->
<div class="modal fade" id="guardarTodoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-win">
            <div class="modal-header modal-header-win">
                <h5 class="modal-title"><i class="fas fa-save me-2" style="color: var(--color-success);"></i> ¿Guardar configuración?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info mb-4" style="background: rgba(96, 165, 250, 0.12); border: 0.0625rem solid rgba(96, 165, 250, 0.35); border-radius: 0.75rem;">
                    <div style="display: flex; align-items: flex-start; gap:0.75rem;">
                        <i class="fas fa-info-circle fa-2x" style="color: #60a5fa;"></i>
                        <div>
                            <strong style="color: #60a5fa;">Confirmación</strong>
                            <p class="mb-0 mt-1" style="color: #d1d5db;">Esta acción guardará simultáneamente la configuración de todas las secciones de este módulo.</p>
                        </div>
                    </div>
                </div>
                <h6 class="mb-2" style="color: #e5e7eb;"><i class="fas fa-list-check me-2" style="color: var(--color-success);"></i> Se guardarán todos los cambios realizados en:</h6>
                <ul class="mb-4" style="color: #d1d5db; line-height: 1.9; padding-left: 1.25rem;">
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Configuración General</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Datos de la Entidad</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Personal Autorizado</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Información Bancaria</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Rangos de Impuesto (Ingresos Personales)</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Configuración de Correo (SMTP)</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Google OAuth</li>
                    <li><i class="fas fa-check-circle me-2" style="color: var(--color-success);"></i> Seguridad del Sistema</li>
                </ul>
            </div>
            <div class="modal-footer modal-footer-win">
                <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" class="btn-win btn-win-success" id="btnConfirmarGuardarTodo" title="Sí, guardar cambios" data-tooltip="Sí, guardar cambios" data-tooltip-theme="success">
                    <i class="fas fa-check me-1"></i> Sí, guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert211.js"></script>
<script src="../js/cropper.min.js"></script>
<script src="../js/exceljs.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script>
/* Configuración para las exportaciones (js/configuracion_export.js) */
window.CONFIG_EXPORT = {
    endpoint: 'exportar_configuracion.php',
    titulo: 'Configuración del Sistema',
    sistema: 'Sistema SisGesNom®',
    empresa: <?php echo json_encode($config_empresa['nombre_empresa'] ?? (defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom'), JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../js/configuracion_export.js?v=<?php echo @filemtime(__DIR__ . '/../js/configuracion_export.js') ?: time(); ?>"></script>

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

// Mensaje de guardado como toast SweetAlert (tras PRG)
(function () {
    const alertMsg = document.getElementById('configAlertMsg');
    if (!alertMsg) return;
    const esError = alertMsg.classList.contains('alert-danger') || alertMsg.classList.contains('alert-warning');
    setTimeout(function () {
        Swal.fire({
            icon: esError ? 'error' : 'success',
            title: esError ? 'Ocurrió un error' : 'Guardado exitoso',
            text: alertMsg.textContent.trim(),
            background: 'var(--panel)', color: 'var(--txt)',
            confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido',
            confirmButtonColor: esError ? '#ef4444' : '#10b981',
            timer: 3500,
            timerProgressBar: true,
            toast: true,
            backdrop: false,
            position: 'top-end',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () {
                const bar = Swal.getTimerProgressBar();
                if (bar) bar.style.background = esError ? '#ef4444' : '#10b981';
            }
        });
        Swal.getTimerProgressBar && setTimeout(function () { alertMsg.remove(); }, 100);
    }, 80);

    // Limpiar msg/tipo de la URL para que al recargar (o al guardar tema) no se repita el toast
    try {
        let url = new URL(window.location.href);
        url.searchParams.delete('msg');
        url.searchParams.delete('tipo');
        history.replaceState(null, '', url.toString());
    } catch (e) {}
})();

// Backup y Restore
function realizarBackupManual() {
    Swal.close();

    // ---- Estilos ----
    if (!document.getElementById('sr-orb-backup-styles')) {
        var st = document.createElement('style');
        st.id = 'sr-orb-backup-styles';
        st.textContent = `
        .sr-ob-popup{
            font-family:inherit!important;
            background:transparent!important;
            border:none!important;
            box-shadow:none!important;
            padding:0!important;
            overflow:visible!important;
            animation:none!important;
        }
        .sr-ob-popup .swal2-html-container{
            margin:0!important; padding:0!important; overflow:visible!important;
        }
        .sr-ob-popup .swal2-actions{ display:none!important; }

        .sr-ob{
            position:relative;
            background:var(--bg);
            color:var(--txt);
            border-radius:1.25rem;
            border:1px solid rgba(var(--blue-soft-rgb),.18);
            padding:2rem 1.75rem 1.75rem;
            overflow:hidden;
            box-shadow:
                0 2rem 4rem rgba(0,0,0,.35),
                0 0 0 1px rgba(255,255,255,.03) inset;
            text-align:left;
            animation:srObIn .4s cubic-bezier(.2,.8,.2,1) both;
        }
        @keyframes srObIn{
            from{opacity:0;transform:scale(.94) translateY(10px);filter:blur(4px)}
            to{opacity:1;transform:scale(1) translateY(0);filter:blur(0)}
        }

        .sr-ob::before,
        .sr-ob::after{
            content:'';position:absolute;border-radius:50%;
            filter:blur(70px);opacity:.28;pointer-events:none;z-index:0;
        }
        .sr-ob::before{
            width:16rem;height:16rem;
            top:-8rem;left:-5rem;
            background:radial-gradient(circle,
                rgba(var(--color-success-soft-rgb),.9), transparent 70%);
        }
        .sr-ob::after{
            width:14rem;height:14rem;
            bottom:-7rem;right:-4rem;
            background:radial-gradient(circle,
                rgba(var(--blue-soft-rgb),.7), transparent 70%);
        }

        .sr-ob-close{
            position:absolute;top:.875rem;right:.875rem;z-index:3;
            width:2rem;height:2rem;border-radius:.5rem;
            border:none;background:transparent;
            color:var(--muted);cursor:pointer;
            display:flex;align-items:center;justify-content:center;
            font-size:.8125rem;
            transition:background .15s,color .15s,transform .2s;
        }
        .sr-ob-close:hover{
            background:rgba(var(--red-rgb),.12);
            color:var(--red);
            transform:rotate(90deg);
        }

        .sr-ob-head{
            position:relative;z-index:2;
            display:flex;flex-direction:column;align-items:center;
            text-align:center;
            margin-bottom:1.75rem;
        }
        .sr-ob-badge{
            display:inline-flex;align-items:center;gap:.5rem;
            padding:.375rem .875rem;
            border-radius:999px;
            background:rgba(var(--color-success-soft-rgb),.1);
            border:1px solid rgba(var(--color-success-soft-rgb),.22);
            font-size:.6875rem;font-weight:600;
            color:var(--txt);
            letter-spacing:.02em;
            margin-bottom:.875rem;
        }
        .sr-ob-badge-icon{
            color:var(--color-success-soft);
            font-size:.75rem;
        }
        .sr-ob-badge-dot{
            width:.375rem;height:.375rem;border-radius:50%;
            background:var(--color-success-soft);
            box-shadow:0 0 0 0 rgba(var(--color-success-soft-rgb),.5);
            animation:srObDot 2s cubic-bezier(.4,0,.6,1) infinite;
        }
        @keyframes srObDot{
            0%,100%{ box-shadow:0 0 0 0 rgba(var(--color-success-soft-rgb),.5); }
            50%    { box-shadow:0 0 0 .3125rem rgba(var(--color-success-soft-rgb),0); }
        }
        .sr-ob-title{
            font-size:1.5rem;font-weight:700;
            color:var(--txt);
            letter-spacing:-.025em;
            line-height:1.1;
            margin:0;
        }
        .sr-ob-sub{
            font-size:.8125rem;color:var(--muted);
            margin-top:.375rem;
            line-height:1.4;
            max-width:24rem;
        }

        .sr-ob-grid{
            position:relative;z-index:2;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:.75rem;
            margin-bottom:1rem;
        }
        @media (max-width:540px){
            .sr-ob-grid{ grid-template-columns:1fr; }
        }

        .sr-ob-block{
            display:flex;align-items:flex-start;gap:.75rem;
            padding:1rem;
            border-radius:.875rem;
            border:1px solid rgba(var(--blue-soft-rgb),.15);
            background:rgba(var(--blue-soft-rgb),.04);
        }
        .sr-ob-block--full{ grid-column:1 / -1; }
        .sr-ob-block-icon{
            width:2.25rem;height:2.25rem;border-radius:.5rem;flex-shrink:0;
            display:flex;align-items:center;justify-content:center;
            font-size:.9375rem;
            color:var(--sb-accent);
            background:var(--sb-icon-bg);
        }
        .sr-ob-block--success{
            --sb-accent:var(--color-success-soft);
            --sb-icon-bg:rgba(var(--color-success-soft-rgb),.14);
        }
        .sr-ob-block--warning{
            --sb-accent:var(--amber);
            --sb-icon-bg:rgba(var(--amber-soft-rgb),.14);
        }
        .sr-ob-block--info{
            --sb-accent:var(--blue);
            --sb-icon-bg:rgba(var(--blue-soft-rgb),.14);
        }
        .sr-ob-block-txt{flex:1;min-width:0}
        .sr-ob-block-title{
            font-size:.8125rem;font-weight:700;
            color:var(--txt);
            line-height:1.2;
            margin:0 0 .25rem;
        }
        .sr-ob-block-desc{
            font-size:.6875rem;color:var(--muted);
            line-height:1.4;
            margin:0;
        }

        .sr-ob-list{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:.375rem .875rem;
            list-style:none;
            padding:0;margin:0;
            font-size:.75rem;
            color:var(--muted);
        }
        .sr-ob-list li{
            display:flex;align-items:center;gap:.5rem;
            line-height:1.4;
        }
        .sr-ob-list i{
            color:var(--color-success-soft);
            font-size:.6875rem;
            flex-shrink:0;
        }
        @media (max-width:540px){
            .sr-ob-list{ grid-template-columns:1fr; }
        }

        .sr-ob-footer{
            position:relative;z-index:2;
            display:flex;align-items:center;justify-content:flex-end;
            gap:.5rem;
            margin-top:1.25rem;
            padding-top:1.25rem;
            border-top:1px solid rgba(var(--blue-soft-rgb),.15);
        }

        .sr-ob-btn{
            display:inline-flex;align-items:center;justify-content:center;gap:.375rem;
            padding:.625rem 1.125rem;
            border-radius:.5rem;
            font-size:.75rem;font-weight:600;
            font-family:inherit;
            cursor:pointer;
            border:1px solid rgba(var(--blue-soft-rgb),.2);
            background:transparent;
            color:var(--txt);
            transition:background .12s,border-color .12s,filter .12s;
        }
        .sr-ob-btn:hover{
            background:rgba(var(--blue-soft-rgb),.08);
            border-color:rgba(var(--blue-soft-rgb),.35);
        }
        .sr-ob-btn:active{ background:rgba(var(--blue-soft-rgb),.15); }
        .sr-ob-btn i{ font-size:.6875rem;opacity:.85; }
        .sr-ob-btn--primary{
            background:var(--color-success-soft);
            border-color:var(--color-success-soft);
            color:#fff;
        }
        .sr-ob-btn--primary:hover{
            background:var(--color-success-soft);
            border-color:var(--color-success-soft);
            filter:brightness(1.08);
        }
        .sr-ob-btn--primary i{ opacity:1; }
        .sr-ob-btn--danger{
            background:var(--red);
            border-color:var(--red);
            color:#fff;
        }
        .sr-ob-btn--danger:hover{
            background:var(--red);
            border-color:var(--red);
            filter:brightness(1.08);
        }

        .sr-ob-spinner{
            position:relative;
            width:5rem;height:5rem;margin:0 auto 1.25rem;
            border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            font-size:1.75rem;
            color:var(--color-success-soft);
            background:rgba(var(--color-success-soft-rgb),.1);
            border:1px solid rgba(var(--color-success-soft-rgb),.25);
        }
        .sr-ob-spinner::before,
        .sr-ob-spinner::after{
            content:'';position:absolute;inset:-.25rem;
            border-radius:50%;
            border:2px solid transparent;
            border-top-color:var(--color-success-soft);
            animation:srObSpin 1.4s linear infinite;
        }
        .sr-ob-spinner::after{
            inset:-.5rem;
            border-top-color:rgba(var(--color-success-soft-rgb),.35);
            animation-duration:1.8s;
            animation-direction:reverse;
        }
        @keyframes srObSpin{
            to{ transform:rotate(360deg); }
        }

        .sr-ob-detail{
            display:flex;justify-content:space-between;
            align-items:center;
            padding:.5rem 0;
            font-size:.75rem;
            border-bottom:1px solid rgba(var(--blue-soft-rgb),.1);
        }
        .sr-ob-detail:last-child{ border-bottom:none; }
        .sr-ob-detail-lbl{
            color:var(--muted);
            display:flex;align-items:center;gap:.375rem;
        }
        .sr-ob-detail-val{
            color:var(--txt);
            font-weight:600;
            word-break:break-all;text-align:right;
            max-width:60%;
        }

        .sr-ob.sr-pulse{
            animation:srObPulse .5s ease-in-out both;
        }
        @keyframes srObPulse{
            0%   { transform:scale(1); }
            35%  { transform:scale(1.03); }
            65%  { transform:scale(.985); }
            100% { transform:scale(1); }
        }

        @media (prefers-reduced-motion: reduce){
            .sr-ob,
            .sr-ob-badge-dot,
            .sr-ob-spinner::before,
            .sr-ob-spinner::after,
            .sr-ob.sr-pulse{ animation:none; }
        }
        `;
        document.head.appendChild(st);
    }

    // ---- HTML confirmación ----
    var html = '';
    html += '<div class="sr-ob">';

    html +=   '<button type="button" class="sr-ob-close" id="srObmClose" title="Cerrar" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>';

    html +=   '<div class="sr-ob-head">';
    html +=     '<div class="sr-ob-badge">';
    html +=       '<span class="sr-ob-badge-dot"></span>';
    html +=       '<i class="fas fa-database sr-ob-badge-icon"></i>';
    html +=       'Backup del sistema';
    html +=     '</div>';
    html +=     '<h1 class="sr-ob-title">Salva del Sistema Manual</h1>';
    html +=     '<p class="sr-ob-sub">Se creará una copia de seguridad completa de tu sistema.</p>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-grid">';

    // Contenido
    html +=     '<div class="sr-ob-block sr-ob-block--full">';
    html +=       '<div class="sr-ob-block-icon" style="color:var(--blue);background:rgba(var(--blue-soft-rgb),.14);"><i class="fas fa-list-check"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<p class="sr-ob-block-title">Contenido de la copia</p>';
    html +=         '<ul class="sr-ob-list">';
    html +=           '<li><i class="fas fa-circle-check"></i> Estructura completa de la BD</li>';
    html +=           '<li><i class="fas fa-circle-check"></i> Datos de empleados y nóminas</li>';
    html +=           '<li><i class="fas fa-circle-check"></i> Configuración y tasas del sistema</li>';
    html +=           '<li><i class="fas fa-circle-check"></i> Historial de vacaciones y submayores</li>';
    html +=         '</ul>';
    html +=       '</div>';
    html +=     '</div>';

    // Formato
    html +=     '<div class="sr-ob-block sr-ob-block--success">';
    html +=       '<div class="sr-ob-block-icon"><i class="fas fa-file-zipper"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<p class="sr-ob-block-title">Formato</p>';
    html +=         '<p class="sr-ob-block-desc">ZIP comprimido</p>';
    html +=       '</div>';
    html +=     '</div>';

    // Duración
    html +=     '<div class="sr-ob-block sr-ob-block--warning">';
    html +=       '<div class="sr-ob-block-icon"><i class="fas fa-hourglass-half"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<p class="sr-ob-block-title">Duración</p>';
    html +=         '<p class="sr-ob-block-desc">Unos segundos</p>';
    html +=       '</div>';
    html +=     '</div>';

    html +=   '</div>';

    html +=   '<div class="sr-ob-footer">';
    html +=     '<button type="button" class="sr-ob-btn" id="srObmCancel"><i class="fas fa-ban me-1"></i> Cancelar</button>';
    html +=     '<button type="button" class="sr-ob-btn sr-ob-btn--primary" id="srObmConfirm"><i class="fas fa-download me-1"></i> Generar Backup</button>';
    html +=   '</div>';

    html += '</div>';

    // ---- Modal confirmación ----
    Swal.fire({
        html: html,
        width: '42rem',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: true,
        padding: '0',
        customClass: { popup: 'sr-ob-popup' },
        didOpen: function () {
            var cont  = Swal.getContainer();
            var popup = Swal.getPopup();

            if (cont) {
                cont.style.background = 'rgba(0,0,0,.55)';
                cont.style.backdropFilter = 'blur(8px)';
                cont.style.alignItems = 'center';
            }
            if (popup) {
                popup.style.border = 'none';
                popup.style.padding = '0';
                popup.style.overflow = 'visible';
                popup.style.background = 'transparent';
                popup.style.boxShadow = 'none';
            }

            var close   = document.getElementById('srObmClose');
            var cancel  = document.getElementById('srObmCancel');
            var confirm = document.getElementById('srObmConfirm');

            if (close)  close.addEventListener('click',  function (e) { e.preventDefault(); e.stopPropagation(); Swal.close(); });
            if (cancel) cancel.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); Swal.close(); });
            if (confirm) confirm.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                Swal.close();
                ejecutarBackupManualOrb();
            });

            // Pulse al clic fuera
            if (!window.__srObmOutside) {
                window.__srObmOutside = function (e) {
                    var pop = (typeof Swal.getPopup === 'function') ? Swal.getPopup() : null;
                    if (!pop) return;
                    if (!pop.contains(e.target)) {
                        var box = pop.querySelector('.sr-ob');
                        if (!box) return;
                        box.classList.remove('sr-pulse');
                        void box.offsetWidth;
                        box.classList.add('sr-pulse');
                        setTimeout(function () { box.classList.remove('sr-pulse'); }, 520);
                    }
                };
                document.addEventListener('click', window.__srObmOutside, true);
            }
            if (!window.__srObmCleanup) {
                window.__srObmCleanup = function () {
                    if (window.__srObmOutside) {
                        document.removeEventListener('click', window.__srObmOutside, true);
                        window.__srObmOutside = null;
                    }
                    window.__srObmCleanup = null;
                };
                var check = setInterval(function () {
                    if (typeof Swal.isVisible !== 'function' || !Swal.isVisible()) {
                        clearInterval(check);
                        if (window.__srObmCleanup) window.__srObmCleanup();
                    }
                }, 300);
            }
        }
    });
}

/* ============================================================
   Progreso + resultado del backup manual (Glass Orb)
   ============================================================ */

function ejecutarBackupManualOrb() {
    mostrarProgresoBackupManualOrb();

    fetch('../ajax/backup_db.php', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            mostrarExitoBackupManualOrb(data);
        } else {
            mostrarErrorBackupManualOrb(data.message);
        }
    })
    .catch(function () {
        Swal.close();
        mostrarErrorBackupManualOrb('Error de conexión con el servidor');
    });
}

function mostrarProgresoBackupManualOrb() {
    var html = '';
    html += '<div class="sr-ob">';

    html +=   '<div class="sr-ob-head">';
    html +=     '<div class="sr-ob-badge">';
    html +=       '<span class="sr-ob-badge-dot"></span>';
    html +=       '<i class="fas fa-database sr-ob-badge-icon"></i>';
    html +=       'Backup del sistema';
    html +=     '</div>';
    html +=     '<h1 class="sr-ob-title">Generando Backup…</h1>';
    html +=     '<p class="sr-ob-sub">Este proceso puede tardar unos segundos. No cierres esta ventana.</p>';
    html +=   '</div>';

    html +=   '<div style="position:relative;z-index:2;text-align:center;">';
    html +=     '<div class="sr-ob-spinner"><i class="fas fa-file-zipper"></i></div>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-grid" style="margin-top:1.25rem;">';
    html +=     '<div class="sr-ob-block sr-ob-block--full">';
    html +=       '<div class="sr-ob-block-icon" style="color:var(--color-success-soft);background:rgba(var(--color-success-soft-rgb),.14);"><i class="fas fa-hourglass-half"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<p class="sr-ob-block-title">Creando copia completa</p>';
    html +=         '<p class="sr-ob-block-desc">Empaquetando base de datos y configuraciones en un archivo ZIP.</p>';
    html +=       '</div>';
    html +=     '</div>';
    html +=   '</div>';

    html += '</div>';

    Swal.fire({
        html: html,
        width: '42rem',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        padding: '0',
        customClass: { popup: 'sr-ob-popup' },
        didOpen: function () {
            var cont  = Swal.getContainer();
            var popup = Swal.getPopup();
            if (cont) {
                cont.style.background = 'rgba(0,0,0,.55)';
                cont.style.backdropFilter = 'blur(8px)';
                cont.style.alignItems = 'center';
            }
            if (popup) {
                popup.style.border = 'none';
                popup.style.padding = '0';
                popup.style.overflow = 'visible';
                popup.style.background = 'transparent';
                popup.style.boxShadow = 'none';
            }
        }
    });
}

function mostrarExitoBackupManualOrb(data) {
    var html = '';
    html += '<div class="sr-ob">';

    html +=   '<button type="button" class="sr-ob-close" id="srObmOkClose" title="Cerrar" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>';

    html +=   '<div class="sr-ob-head">';
    html +=     '<div class="sr-ob-badge">';
    html +=       '<span class="sr-ob-badge-dot"></span>';
    html +=       '<i class="fas fa-circle-check sr-ob-badge-icon"></i>';
    html +=       'Backup completado';
    html +=     '</div>';
    html +=     '<h1 class="sr-ob-title">¡Listo!</h1>';
    html +=     '<p class="sr-ob-sub">La copia se generó correctamente. Puedes descargarla ahora.</p>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-grid">';
    html +=     '<div class="sr-ob-block sr-ob-block--full">';
    html +=       '<div class="sr-ob-block-icon" style="color:var(--color-success-soft);background:rgba(var(--color-success-soft-rgb),.14);"><i class="fas fa-file-zipper"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<div class="sr-ob-detail">';
    html +=           '<span class="sr-ob-detail-lbl"><i class="fas fa-file me-1"></i> Archivo</span>';
    html +=           '<span class="sr-ob-detail-val">' + (data.filename || '—') + '</span>';
    html +=         '</div>';
    html +=         '<div class="sr-ob-detail">';
    html +=           '<span class="sr-ob-detail-lbl"><i class="fas fa-weight-hanging me-1"></i> Tamaño</span>';
    html +=           '<span class="sr-ob-detail-val">' + (data.size || '—') + '</span>';
    html +=         '</div>';
    html +=       '</div>';
    html +=     '</div>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-footer">';
    html +=     '<a href="../' + data.download_url + '" download class="sr-ob-btn sr-ob-btn--primary" style="text-decoration:none;"><i class="fas fa-download me-1"></i> Descargar</a>';
    html +=     '<button type="button" class="sr-ob-btn" id="srObmOkDone"><i class="fas fa-check me-1"></i> Entendido</button>';
    html +=   '</div>';

    html += '</div>';

    Swal.fire({
        html: html,
        width: '42rem',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: true,
        padding: '0',
        customClass: { popup: 'sr-ob-popup' },
        didOpen: function () {
            var cont  = Swal.getContainer();
            var popup = Swal.getPopup();
            if (cont) {
                cont.style.background = 'rgba(0,0,0,.55)';
                cont.style.backdropFilter = 'blur(8px)';
                cont.style.alignItems = 'center';
            }
            if (popup) {
                popup.style.border = 'none';
                popup.style.padding = '0';
                popup.style.overflow = 'visible';
                popup.style.background = 'transparent';
                popup.style.boxShadow = 'none';
            }
            var close = document.getElementById('srObmOkClose');
            var done  = document.getElementById('srObmOkDone');
            if (close) close.addEventListener('click', function () { Swal.close(); });
            if (done)  done.addEventListener('click',  function () { Swal.close(); });
        }
    });
}

function mostrarErrorBackupManualOrb(mensaje) {
    var html = '';
    html += '<div class="sr-ob">';

    html +=   '<button type="button" class="sr-ob-close" id="srObmErrClose" title="Cerrar" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>';

    html +=   '<div class="sr-ob-head">';
    html +=     '<div class="sr-ob-badge" style="background:rgba(var(--red-rgb),.1);border-color:rgba(var(--red-rgb),.22);">';
    html +=       '<span class="sr-ob-badge-dot" style="background:var(--red);box-shadow:0 0 0 0 rgba(var(--red-rgb),.5);"></span>';
    html +=       '<i class="fas fa-triangle-exclamation sr-ob-badge-icon" style="color:var(--red);"></i>';
    html +=       'Error';
    html +=     '</div>';
    html +=     '<h1 class="sr-ob-title">No se pudo completar</h1>';
    html +=     '<p class="sr-ob-sub">Ocurrió un problema al generar el backup.</p>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-grid">';
    html +=     '<div class="sr-ob-block sr-ob-block--full" style="border-color:rgba(var(--red-rgb),.22);background:rgba(var(--red-rgb),.06);">';
    html +=       '<div class="sr-ob-block-icon" style="color:var(--red);background:rgba(var(--red-rgb),.14);"><i class="fas fa-circle-exclamation"></i></div>';
    html +=       '<div class="sr-ob-block-txt">';
    html +=         '<p class="sr-ob-block-title" style="color:var(--red);">Detalles del error</p>';
    html +=         '<p class="sr-ob-block-desc" style="word-break:break-word;">' + (mensaje || 'Error desconocido') + '</p>';
    html +=       '</div>';
    html +=     '</div>';
    html +=   '</div>';

    html +=   '<div class="sr-ob-footer">';
    html +=     '<button type="button" class="sr-ob-btn sr-ob-btn--danger" id="srObmErrDone"><i class="fas fa-check me-1"></i> Entendido</button>';
    html +=   '</div>';

    html += '</div>';

    Swal.fire({
        html: html,
        width: '42rem',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: true,
        padding: '0',
        customClass: { popup: 'sr-ob-popup' },
        didOpen: function () {
            var cont  = Swal.getContainer();
            var popup = Swal.getPopup();
            if (cont) {
                cont.style.background = 'rgba(0,0,0,.55)';
                cont.style.backdropFilter = 'blur(8px)';
                cont.style.alignItems = 'center';
            }
            if (popup) {
                popup.style.border = 'none';
                popup.style.padding = '0';
                popup.style.overflow = 'visible';
                popup.style.background = 'transparent';
                popup.style.boxShadow = 'none';
            }
            var close = document.getElementById('srObmErrClose');
            var done  = document.getElementById('srObmErrDone');
            if (close) close.addEventListener('click', function () { Swal.close(); });
            if (done)  done.addEventListener('click',  function () { Swal.close(); });
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
document.getElementById('restoreFile')?.addEventListener('change', function() {
    const nombreEl = document.getElementById('restoreFileName');
    const sizeEl = document.getElementById('fileSizeInfo');
    if (this.files && this.files.length > 0) {
        const f = this.files[0];
        if (nombreEl) { nombreEl.textContent = f.name; nombreEl.classList.remove('text-secondary'); nombreEl.classList.add('text-light'); }
        if (sizeEl) sizeEl.textContent = 'Tamaño: ' + (f.size / 1024 / 1024).toFixed(2) + ' MB';
        document.getElementById('chooseFileLabel')?.classList.add('border-success');
    } else {
        if (nombreEl) { nombreEl.textContent = 'Seleccionar archivo…'; nombreEl.classList.add('text-secondary'); nombreEl.classList.remove('text-light'); }
        if (sizeEl) sizeEl.textContent = '';
        document.getElementById('chooseFileLabel')?.classList.remove('border-success');
    }
});
document.getElementById('restoreForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('restoreFile');
    if (!fileInput.files || fileInput.files.length === 0) { Swal.fire({ title: 'Error', text: 'Seleccione un archivo', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); return; }
    const formData = new FormData(this);
	
    // CERRAR EL MODAL DE BOOTSTRAP ANTES DE MOSTRAR EL SWAL DE CARGA
    if (restoreModalInstance) {
        restoreModalInstance.hide();
    }

    // Reiniciar el progreso para que el modal arranque siempre desde cero
    // y no muestre el estado de la restauración anterior.
    try {
        await fetch('../ajax/restore_progress.php?reset=1', { cache: 'no-store' });
    } catch (err) { /* continuar igualmente */ }
	
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
        if (data.success) {
            Swal.fire({
                title: 'Restauración Completada',
                html: `<pre style="background:#2d2d3a; color:#e2e8f0 !important; padding:0.75rem; border-radius:0.5rem; white-space:pre-wrap; word-break:break-word;">${data.message}</pre>`,
                icon: 'success',
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i> Recargar',
                denyButtonText: '<i class="fas fa-file-lines me-2"></i> Ver log',
                background: 'var(--panel)', color: 'var(--txt)',
            }).then((result) => {
                if (result.isDenied) { verLogRestauracion(); }
                else { location.reload(); }
            });
        }
        else { Swal.fire({ title: 'Error', html: `<pre style="background:#2d2d3a; padding:0.75rem; border-radius:0.5rem; color:#fca5a5 !important; white-space:pre-wrap; word-break:break-word;">${data.message}</pre>`, icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); }
    })
    .catch(() => { clearInterval(restorePoll); Swal.fire({ title: 'Error de Conexión', text: 'No se pudo conectar', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' }); });
});

// Escapa HTML para inyectar texto de forma segura dentro de un <pre> de SweetAlert
function escaparHTML(str) {
    return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Abre una ventana de impresión con el texto indicado
function imprimirTexto(titulo, texto) {
    const win = window.open('', '_blank', 'width=900,height=700');
    if (!win) {
        Swal.fire({ title: 'Impresión bloqueada', text: 'Permita las ventanas emergentes para imprimir el log.', icon: 'warning', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }
    win.document.write(
        '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>' + escaparHTML(titulo) + '</title>' +
        '<style>' +
        'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;}' +
        'h1{font-size:1rem;margin:0 0 0.75rem;}' +
        'pre{font-family:Consolas,"Courier New",monospace;font-size:0.75rem;line-height:1.35;white-space:pre-wrap;word-break:break-word;border:1px solid #ccc;border-radius:6px;padding:12px;}' +
        '</style></head><body>' +
        '<h1>' + escaparHTML(titulo) + '</h1>' +
        '<pre>' + escaparHTML(texto) + '</pre>' +
        '</body></html>'
    );
    win.document.close();
    win.focus();
    win.print();
}

// Muestra el log de restauraciones (logs/restore_log.json) en un modal
function verLogRestauracion() {
    fetch('../ajax/restore_log.php?t=' + Date.now(), { cache: 'no-store' })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) { throw new Error(resp.message || 'No se pudo leer el log'); }
            const logs = Array.isArray(resp.logs) ? resp.logs : [];
            const entrada = logs.length ? logs[0] : null;
            const texto = entrada ? JSON.stringify(entrada, null, 2) : 'No hay registros de restauración.';
            Swal.fire({
                title: 'Log de restauración',
                html: '<pre style="text-align:left; max-height:55vh; overflow:auto; background:#2d2d3a; color:#e2e8f0 !important; padding:0.75rem; border-radius:0.5rem; font-size:0.8rem; white-space:pre-wrap; word-break:break-word;">' + escaparHTML(texto) + '</pre>',
                icon: 'info',
                width: '52rem',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i> Recargar',
                denyButtonText: '<i class="fas fa-print me-2"></i> Imprimir',
                cancelButtonText: '<i class="fas fa-times me-2"></i> Cerrar',
                preDeny: () => { imprimirTexto('Log de restauración', texto); return false; },
                background: 'var(--panel)', color: 'var(--txt)'
            }).then((res) => {
                if (res.isConfirmed) { location.reload(); }
            });
        })
        .catch(err => {
            Swal.fire({ title: 'Error', text: err.message || 'No se pudo leer el log', icon: 'error', background: 'var(--panel)', color: 'var(--txt)' });
        });
}

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

document.getElementById('toggleMailPassword')?.addEventListener('click', function() {
    const input = document.getElementById('mail_password');
    const icon = this.querySelector('i');
    if (!input || !icon) return;
    const esPass = input.type === 'password';
    input.type = esPass ? 'text' : 'password';
    icon.className = esPass ? 'fas fa-eye-slash' : 'fas fa-eye';
});

document.getElementById('toggleGoogleSecret')?.addEventListener('click', function() {
    const input = document.getElementById('google_client_secret');
    const icon = this.querySelector('i');
    if (!input || !icon) return;
    const esPass = input.type === 'password';
    input.type = esPass ? 'text' : 'password';
    icon.className = esPass ? 'fas fa-eye-slash' : 'fas fa-eye';
});

function toggleGoogleOauth() {
    const activo = document.getElementById('googleoauth')?.checked === true;
    ['google_client_id', 'google_client_secret', 'toggleGoogleSecret'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.disabled = !activo;
    });
}

document.getElementById('mail_proveedor')?.addEventListener('change', function() {
    const prov = proveedoresSMTP[this.value];
    const host = document.getElementById('mail_host');
    const port = document.getElementById('mail_port');
    const enc = document.getElementById('mail_encryption');
    if (!prov || !host || !port || !enc) return;

    if (this.value === 'custom') {
        host.value = '';
        port.value = '';
        enc.innerHTML = '';
        ['tls','ssl','none'].forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v === 'tls' ? 'STARTTLS (puerto 587)' : v === 'ssl' ? 'SSL/TLS (puerto 465)' : 'Sin cifrado';
            enc.appendChild(opt);
        });
        enc.value = 'tls';
        enc.disabled = false;
        return;
    }

    host.value = prov.host || '';
    port.value = prov.puerto || '';

    enc.innerHTML = '';
    const recomendado = prov.encriptacion || 'tls';
    const definiciones = {
        tls: 'STARTTLS (puerto 587)',
        ssl: 'SSL/TLS (puerto 465)',
        none: 'Sin cifrado'
    };
    Object.keys(definiciones).forEach(v => {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = definiciones[v];
        enc.appendChild(opt);
    });
    enc.value = recomendado;
    enc.disabled = false;
});

// Al cargar, preseleccionar el cifrado recomendado según el proveedor guardado (sin bloquear opciones)
(function initEncryptionOptions() {
    const provSel = document.getElementById('mail_proveedor');
    const enc = document.getElementById('mail_encryption');
    if (!provSel || !enc) return;
    const prov = proveedoresSMTP[provSel.value];
    if (!prov || provSel.value === 'custom') return;
    const recomendado = prov.encriptacion || 'tls';
    if (Array.from(enc.options).some(o => o.value === recomendado)) enc.value = recomendado;
})();

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
#btnGuardarTodo { background: linear-gradient(135deg, #059669, #10b981); }
#btnGuardarTodo i { animation: none; }
@media print { .scroll-quick-btns { display: none !important; } }
</style>
<div class="scroll-quick-btns">
    <button type="button" class="scroll-quick-btn" id="btnGuardarTodo" title="Guardar toda la configuración" data-tooltip="Guardar toda la configuración" data-tooltip-theme="success">
        <i class="fas fa-save"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnRecargarPagina" title="Recargar datos y actualizar página" data-tooltip="Recargar datos y actualizar página" data-tooltip-theme="primary">
        <i class="fas fa-sync-alt"></i>
    </button>
    <button type="button" class="scroll-quick-btn" id="btnCollapseAll" title="Expandir todas las tarjetas" data-tooltip="Expandir todas las tarjetas" data-tooltip-theme="primary">
        <i class="fas fa-chevron-down"></i>
    </button>
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

<!-- Recarga de página con modal animado -->
<style>
.recarga-ico { font-size: 1.75rem; color: #60a5fa; margin-bottom: 0.5rem; }
</style>
<script>
function recargarConfiguracion() {
    if (window.Swal) {
        Swal.fire({
            title: '<div class="recarga-ico"><i class="fa-solid fa-sync fa-spin"></i></div> RECARGANDO DATOS DE CONFIGURACIÓN...',
            html: '<div style="color:#94a3b8; font-size:0.95rem;">Se están actualizando los datos del sistema.</div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            background: '#0f172a',
            color: '#e2e8f0',
            didOpen: function () {
                setTimeout(function () { window.location.reload(); }, 1200);
            }
        });
    } else {
        window.location.reload();
    }
}
</script>

<!-- Guardado general (botón flotante de disco) -->
<script>
(function () {
    var btn = document.getElementById('btnGuardarTodo');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var modalEl = document.getElementById('guardarTodoModal');
        if (!modalEl) return;
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    });

    var recargar = document.getElementById('btnRecargarPagina');
    if (recargar) {
        recargar.addEventListener('click', recargarConfiguracion);
    }

    var confirmar = document.getElementById('btnConfirmarGuardarTodo');
    if (!confirmar) return;

    confirmar.addEventListener('click', function () {
        var formIds = ['configGeneralForm', 'rangosForm', 'datosEntidadForm', 'datosPersonalForm', 'bancariaForm', 'mailForm', 'googleForm', 'sistemaForm'];
        var hiddenForm = document.createElement('form');
        hiddenForm.method = 'POST';
        hiddenForm.style.display = 'none';
        document.body.appendChild(hiddenForm);

        formIds.forEach(function (id) {
            var f = document.getElementById(id);
            if (!f) return;
            var elements = f.elements;
            for (var i = 0; i < elements.length; i++) {
                var el = elements[i];
                if (!el.name) continue;
                if (el.type === 'submit' || el.type === 'button' || el.tagName === 'FIELDSET') continue;
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = el.name;
                inp.value = (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? (el.value || '1') : '') : el.value;
                hiddenForm.appendChild(inp);
            }
            var submitBtn = f.querySelector('button[type="submit"][name^="guardar_"]');
            if (submitBtn && submitBtn.name) {
                var tr = document.createElement('input');
                tr.type = 'hidden';
                tr.name = submitBtn.name;
                tr.value = submitBtn.value || '1';
                hiddenForm.appendChild(tr);
            }
        });

        var todo = document.createElement('input');
        todo.type = 'hidden';
        todo.name = 'guardar_todo';
        todo.value = '1';
        hiddenForm.appendChild(todo);

        hiddenForm.submit();
    });
})();
</script>

<!-- Expandir / Colapsar todos los cards (botón flotante) -->
<script>
(function () {
    var btn = document.getElementById('btnCollapseAll');
    if (!btn) return;
    var titles = document.querySelectorAll('.card-collapse-title');
    var todasColapsadas = true;

    function aplicar(expandir) {
        titles.forEach(function (title) {
            var target = title.getAttribute('data-bs-target');
            if (!target) return;
            var el = document.querySelector(target);
            if (!el) return;
            var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            if (expandir) { inst.show(); } else { inst.hide(); }
        });
        todasColapsadas = !expandir;
        actualizarEstado();
    }

    function actualizarEstado() {
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = todasColapsadas ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }
        btn.title = todasColapsadas ? 'Expandir todas las tarjetas' : 'Colapsar todas las tarjetas';
    }

    btn.addEventListener('click', function () { aplicar(todasColapsadas); });
    actualizarEstado();
})();
</script>

<!-- Items del dropdown de acciones rápidas (delegan en los botones flotantes) -->
<script>
(function () {
    var dropGuardar = document.getElementById('btnDropGuardarTodo');
    if (dropGuardar) {
        dropGuardar.addEventListener('click', function () {
            var guardar = document.getElementById('btnGuardarTodo');
            if (guardar) guardar.click();
        });
    }
    var dropRecargar = document.getElementById('btnDropRecargar');
    if (dropRecargar) {
        dropRecargar.addEventListener('click', function () {
            var recargar = document.getElementById('btnRecargarPagina');
            if (recargar) recargar.click();
        });
    }
})();
</script>

<!-- Expandir / Colapsar todos los cards -->
<script>
(function () {
    var btn = document.getElementById('btnToggleAllCards');
    var texto = document.getElementById('btnToggleAllCardsTexto');
    if (!btn || !texto) return;
    var selectores = ['#collapseDB', '#collapseConfigGeneral', '#collapseTasas', '#collapseDatosEntidad', '#collapsePersonal', '#collapseBancaria', '#collapseMail', '#collapseRangos'];

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

<!-- Popup de desglose de cuenta bancaria -->
<div class="desglose-overlay" id="desgloseOverlay"></div>
<div class="cuenta-desglose" id="desgloseCuenta" style="display: none;"></div>

<script>
// ==================== INFORMACIÓN BANCARIA ====================
let desgloseVisible = false;
let cuentaValida = false;

const bancosCuba = {
    '01': 'Banco Nacional de Cuba (BNC)',
    '03': 'Banco de Crédito y Comercio (BANDEC)',
    '05': 'Banco Metropolitano S.A. (BANMET)',
    '06': 'Banco de Crédito y Comercio (BANDEC)',
    '08': 'Banco Financiero Internacional S.A. (BFI)',
    '09': 'Banco Exterior de Cuba (BEC)',
    '10': 'Banco Internacional de Comercio S.A. (BICSA)',
    '12': 'Banco Popular de Ahorro (BPA)',
    '13': 'Banco de Inversiones (BISO)',
    '14': 'Banco Exterior de Cuba (BEC)',
    '15': 'Banco Financiero Internacional (BFI)',
    '20': 'Banco Central de Cuba (BCC)',
    '21': 'Banco de Cuba para el Comercio Exterior (BANCEC)',
    '22': 'Banco de Desarrollo Local (BDL)',
    '23': 'Banco de Inversiones de Holguín',
    '25': 'Banco de la Construcción',
    '30': 'Caja de Ahorros',
    '35': 'Financiera Nacional (FINATUR)',
    '40': 'Banco de la Industria Alimentaria (BINAL)',
    '45': 'Banco de la Industria Ligera (BANIL)',
    '50': 'Banco de la Industria Sidero-Mecánica (BANISME)',
    '55': 'Banco de la Industria Químico-Farmacéutica (BANIQ)',
    '60': 'Banco de la Industria de Materiales de Construcción (BANIMAT)',
    '65': 'Banco de la Industria de Bienes de Consumo (BANICON)',
    '70': 'Banco de la Industria Agropecuaria (BANAGRO)',
    '75': 'Banco de la Industria Forestal (BANIF)',
    '80': 'Banco de la Industria Pesquera (BANIPES)',
    '85': 'Banco de la Industria del Turismo (BANITUR)',
    '90': 'Banco de la Industria de Transporte (BANITRANS)',
    '95': 'Banco de la Industria de Comunicaciones (BANICOM)',
    '98': 'Bancos Internacionales',
    '99': 'Casas de Cambio y Otras Instituciones'
};

const tiposCuenta = {
    '01': 'Cuenta de Ahorro CUP (Básica)',
    '02': 'Cuenta de Ahorro para la Vivienda',
    '03': 'Cuenta de Ahorro a Plazo Fijo CUP',
    '04': 'Cuenta en Dólares Estadounidenses (USD)',
    '05': 'Cuenta en Moneda Libremente Convertible (MLC)',
    '06': 'Cuenta en Euros (EUR)',
    '07': 'Cuenta Mixta (CUP/USD)',
    '08': 'Cuenta de Ahorro Joven (BPA)',
    '09': 'Cuenta de Ahorro Escolar',
    '10': 'Cuenta Corriente Empresarial CUP',
    '11': 'Cuenta Corriente Persona Natural CUP',
    '12': 'Cuenta Corriente USD (BFI/BICSA)',
    '13': 'Cuenta Corriente EUR',
    '14': 'Cuenta Corriente MLC',
    '15': 'Cuenta Corriente para TCP/Empresa',
    '16': 'Cuenta Corriente Mixta',
    '17': 'Cuenta Corriente para Inversiones',
    '18': 'Cuenta Corriente Offshore',
    '19': 'Cuenta Corriente Internacional',
    '20': 'Ahorro a Plazo Fijo Largo',
    '21': 'Ahorro para el Retiro',
    '22': 'Ahorro para la Vivienda Especial',
    '23': 'Ahorro para Estudios',
    '24': 'Ahorro para Salud',
    '25': 'Ahorro USD a Plazo Fijo',
    '26': 'Tarjeta Magnética CUP / Jubilados',
    '27': 'Ahorro EUR a Plazo Fijo',
    '28': 'Ahorro MLC a Plazo Fijo',
    '29': 'Ahorro para Emergencias',
    '30': 'Tarjeta de Débito CUP',
    '31': 'Tarjeta de Débito USD',
    '32': 'Tarjeta de Débito MLC',
    '33': 'Tarjeta de Crédito Nacional',
    '34': 'Tarjeta de Crédito Internacional',
    '35': 'Tarjeta Prepago',
    '36': 'Tarjeta Virtual',
    '37': 'Tarjeta MLC (BANDEC Nacional)',
    '38': 'Banca Móvil / Virtual',
    '39': 'Cuenta Digital',
    '40': 'Cuenta Gubernamental',
    '41': 'Cuenta de Organizaciones Sociales',
    '42': 'Cuenta de Gastos Institucionales',
    '43': 'Cuenta de Empresa Estatal',
    '44': 'Cuenta de Empresa Mixta',
    '45': 'Cuenta de Inversión Extranjera',
    '46': 'Cuenta de Proyectos de Desarrollo',
    '47': 'Cuenta de ONG/Organismos',
    '48': 'Cuenta de Fondos Especiales',
    '49': 'Cuenta de Asociaciones',
    '50': 'Cuenta de Nómina Estatal',
    '51': 'Cuenta de Nómina Empresa Mixta',
    '52': 'Cuenta de Nómina TCP',
    '53': 'Cuenta de Remesas',
    '54': 'Cuenta para Pensionados',
    '55': 'Cuenta para Beneficiarios Sociales',
    '56': 'Cuenta de Subsidios',
    '57': 'Banca Remota / Nóminas (BANMET)',
    '58': 'Cuenta de Incentivos',
    '59': 'Cuenta de Bonificaciones',
    '60': 'Cuenta de Inversión Corto Plazo',
    '61': 'Cuenta de Inversión Largo Plazo',
    '62': 'Cuenta de Fondos Mutuos',
    '63': 'Cuenta de Valores',
    '64': 'Cuenta de Bonos',
    '65': 'Cuenta de Acciones',
    '66': 'Cuenta de Fondo de Inversión',
    '67': 'Cuenta de Capital de Riesgo',
    '68': 'Cuenta de Inversión Extranjera Directa',
    '69': 'Cuenta de Portafolio',
    '70': 'Cuenta de Corresponsalía Bancaria',
    '71': 'Cuenta para Importaciones',
    '72': 'Cuenta para Exportaciones',
    '73': 'Cuenta de Financiamiento Externo',
    '74': 'Cuenta de Cartas de Crédito',
    '75': 'Cuenta de Garantías',
    '76': 'Cuenta de Operaciones Cambiarias',
    '77': 'Cuenta de Divisas',
    '78': 'Cuenta de Transferencias Internacionales',
    '79': 'Cuenta de Compensación',
    '80': 'Cuenta MLC Persona Natural',
    '81': 'Cuenta MLC Empresa',
    '82': 'Tarjeta MLC con Cuenta',
    '83': 'Cuenta USD Persona Natural',
    '84': 'Cuenta EUR Persona Natural',
    '85': 'Cuenta en Libras Esterlinas (GBP)',
    '86': 'Cuenta en Dólares Canadienses (CAD)',
    '87': 'Cuenta en Dólares Australianos (AUD)',
    '88': 'Cuenta en Yuanes Chinos (CNY)',
    '89': 'Cuenta en Dólares Caribeños (XCD)',
    '90': 'Cuenta de Casa de Cambio (CADECA)',
    '91': 'Cuenta de FinCimex',
    '92': 'Cuenta de Operaciones Especiales',
    '93': 'Cuenta de Fideicomiso',
    '94': 'Cuenta de Garantía',
    '95': 'Cuenta de Depósito Judicial',
    '96': 'Cuenta de Secuestro',
    '97': 'Cuenta de Administración',
    '98': 'Cuenta Temporal',
    '99': 'Otras Cuentas Especiales'
};

const sucursalesCubaJS = <?php echo json_encode($sucursales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function obtenerNombreSucursal(codigoBanco, codigoSucursal) {
    if (!codigoBanco || !codigoSucursal || codigoSucursal.length !== 4) {
        return 'Sucursal no identificada para ese banco';
    }
    const sucursalesBanco = sucursalesCubaJS[codigoBanco];
    if (!sucursalesBanco) {
        return 'Sucursal no identificada para ese banco';
    }
    return sucursalesBanco[codigoSucursal] || 'Sucursal no identificada para ese banco';
}

function actualizarDesglose() {
    const cuentaInput = document.getElementById('cuentaBancaria');
    let cuenta = cuentaInput.value.trim();
    
    const cuentaLimpia = cuenta.replace(/\D/g, '');
    const longitud = cuentaLimpia.length;
    
    const codigoBanco = longitud >= 2 ? cuentaLimpia.substring(0, 2) : '';
    const codigoSucursal = longitud >= 6 ? cuentaLimpia.substring(2, 6) : '';
    const codigoTipoCuenta = longitud >= 8 ? cuentaLimpia.substring(6, 8) : '';
    const numeroCuenta = longitud >= 8 ? cuentaLimpia.substring(8) : '';
    
    const nombreBanco = bancosCuba[codigoBanco] || 'Banco Desconocido';
    const nombreSucursal = obtenerNombreSucursal(codigoBanco, codigoSucursal);
    const descripcionTipo = tiposCuenta[codigoTipoCuenta] || 'Tipo de cuenta desconocido';
    
    const nombreBancoInput = document.getElementById('nombreBanco');
    const nombreSucursalInput = document.getElementById('nombreSucursal');
    const noClienteInput = document.getElementById('nocliente');
    const tipoCuentaInput = document.getElementById('tipoCuentaInput');
    const sucursalInfo = document.getElementById('sucursalInfo');
    const infoAdicional = document.getElementById('infoAdicional');
    const cuentaError = document.getElementById('cuentaError');
    
    const esValida = validarCuentaBancariaCompleta(cuentaLimpia);
    cuentaValida = esValida;
    
    if (cuentaLimpia.length > 0) {
        if (esValida) {
            cuentaInput.classList.remove('is-invalid');
            cuentaInput.classList.add('is-valid');
            if (cuentaError) cuentaError.style.display = 'none';
        } else {
            cuentaInput.classList.remove('is-valid');
            cuentaInput.classList.add('is-invalid');
            if (cuentaError) cuentaError.style.display = 'block';
            
            if (!esValida && longitud >= 8) {
                const desglose = document.getElementById('desgloseCuenta');
                if (desglose && desglose.style.display !== 'block') {
                    desglose.style.display = 'block';
                    desgloseVisible = true;
                }
            }
        }
    } else {
        cuentaInput.classList.remove('is-valid', 'is-invalid');
        if (cuentaError) cuentaError.style.display = 'none';
    }
    
    if (nombreBancoInput) {
        nombreBancoInput.value = (longitud >= 2) ? nombreBanco : '';
        if (nombreBanco === 'Banco Desconocido' && longitud >= 2) {
            nombreBancoInput.classList.add('banco-desconocido');
            nombreBancoInput.style.color = '#e81123';
            nombreBancoInput.style.fontWeight = '600';
        } else {
            nombreBancoInput.classList.remove('banco-desconocido');
            nombreBancoInput.style.color = '';
            nombreBancoInput.style.fontWeight = '';
        }
    }
    
    if (nombreSucursalInput) {
        nombreSucursalInput.value = codigoSucursal;
        if (nombreSucursal === 'Sucursal no identificada para ese banco' && longitud >= 6) {
            nombreSucursalInput.classList.add('sucursal-no-identificada');
            nombreSucursalInput.style.color = '#ff8c00';
            nombreSucursalInput.style.fontWeight = '600';
        } else {
            nombreSucursalInput.classList.remove('sucursal-no-identificada');
            nombreSucursalInput.style.color = '';
            nombreSucursalInput.style.fontWeight = '';
        }
    }
    
    if (sucursalInfo && codigoSucursal) {
        let icono, color, estilo;
        if (nombreSucursal === 'Sucursal no identificada para ese banco') {
            icono = 'fa-exclamation-triangle';
            color = 'text-warning';
            estilo = 'sucursal-no-identificada';
        } else {
            icono = 'fa-building';
            color = 'text-primary';
            estilo = '';
        }
        
        sucursalInfo.innerHTML = `
            <div class="d-inline-flex align-items-center">
                <i class="fas ${icono} me-2 ${color}"></i>
                <span class="${estilo} me-2">
                    <strong>${nombreSucursal}</strong>
                </span>
                <span class="text-muted">|</span>
                <small class="text-muted ms-2">Código Sucursal: ${codigoSucursal}</small>
            </div>
        `;
        sucursalInfo.style.display = 'flex';
    } else if (sucursalInfo) {
        sucursalInfo.innerHTML = '<span class="text-muted small">Esperando datos de cuenta...</span>';
    }
    
    if (noClienteInput) {
        if (longitud === 16) {
            noClienteInput.value = numeroCuenta;
        } else if (longitud === 14) {
            noClienteInput.value = numeroCuenta + ' (6 dígitos)';
        } else {
            noClienteInput.value = '';
        }
    }
    
    if (tipoCuentaInput) {
        if (codigoTipoCuenta) {
            tipoCuentaInput.value = `${codigoTipoCuenta} → ${descripcionTipo}`;
            if (descripcionTipo === 'Tipo de cuenta desconocido') {
                tipoCuentaInput.style.color = '#ff8c00';
                tipoCuentaInput.style.fontWeight = '600';
            } else {
                tipoCuentaInput.style.color = '';
                tipoCuentaInput.style.fontWeight = '';
            }
        } else {
            tipoCuentaInput.value = '';
        }
    }
    
    if (infoAdicional) {
        let mensajes = [];
        if (esValida) {
            if (nombreBanco === 'Banco Desconocido') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Banco no reconocido`);
            }
            if (nombreSucursal === 'Sucursal no identificada para ese banco') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Sucursal no registrada`);
            }
            if (descripcionTipo === 'Tipo de cuenta desconocido') {
                mensajes.push(`<i class="fas fa-exclamation-triangle text-warning me-1"></i> Tipo de cuenta no reconocido`);
            }
            
            if (mensajes.length > 0) {
                infoAdicional.innerHTML = `
                    <div class="alert alert-warning p-2 mb-2 w-100">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Cuenta válida pero con advertencias:</strong>
                        <ul class="mb-0 mt-1">${mensajes.map(msg => `<li style="font-size: 12px;">${msg}</li>`).join('')}</ul>
                    </div>
                    <div class="alert alert-success p-2 mb-0 w-100">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Cuenta válida:</strong> ${longitud} dígitos - Formato ${longitud === 16 ? 'estándar' : 'antiguo'}
                    </div>`;
            } else {
                infoAdicional.innerHTML = `
                    <div class="alert alert-success p-2 mb-0 w-100">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Cuenta válida:</strong> ${longitud} dígitos - Formato ${longitud === 16 ? 'estándar' : 'antiguo'}
                    </div>`;
            }
        } else if (cuentaLimpia.length > 0) {
            infoAdicional.innerHTML = `
                <div class="alert alert-warning p-2 mb-0 w-100">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Validación:</strong> ${getMensajeError(cuentaLimpia)}
                </div>`;
        } else {
            infoAdicional.innerHTML = '';
        }
    }
    
    actualizarDesglosePopup(cuentaLimpia, longitud, codigoBanco, nombreBanco, 
                           codigoSucursal, nombreSucursal, 
                           codigoTipoCuenta, descripcionTipo, numeroCuenta);
    
    if ((nombreBanco === 'Banco Desconocido' || nombreSucursal === 'Sucursal no identificada para ese banco') && cuentaLimpia.length >= 6) {
        if (!desgloseVisible) {
            abrirDesglose(null);
        }
    }
}

function actualizarDesglosePopup(cuenta, longitud, codigoBanco, nombreBanco, 
                                codigoSucursal, nombreSucursal, 
                                codigoTipoCuenta, descripcionTipo, numeroCuenta) {
    const desglose = document.getElementById('desgloseCuenta');
    if (!desglose) return;
    
    const esValida = validarCuentaBancariaCompleta(cuenta);
    
    let badgeClass, badgeText;
    if (cuenta.length === 0) {
        badgeClass = 'bg-secondary';
        badgeText = 'Sin datos';
    } else if (esValida) {
        badgeClass = longitud === 16 ? 'bg-success' : 'bg-warning';
        badgeText = longitud + ' dígitos - ' + (longitud === 16 ? 'Estándar' : 'Antiguo');
    } else {
        badgeClass = 'bg-danger';
        badgeText = longitud + ' dígitos - Inválido';
    }
    
    let visualizacion = '';
    if (cuenta.length >= 2) {
        visualizacion = cuenta.substring(0, 2);
        if (cuenta.length >= 6) {
            visualizacion += ' - ' + cuenta.substring(2, 6);
            if (cuenta.length >= 8) {
                visualizacion += ' - ' + cuenta.substring(6, 8);
                if (cuenta.length > 8) {
                    visualizacion += ' - ' + cuenta.substring(8);
                }
            }
        }
    }
    
    let contenido = `
        <button type="button" class="desglose-close-btn" onclick="cerrarDesglose(event)" title="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="desglose-header">
            <div class="desglose-title">
                <i class="fas fa-credit-card"></i>
                <span>Desglose de Cuenta Bancaria</span>
            </div>
        </div>
        
        <div class="desglose-numero">
            ${visualizacion || 'No ingresado'}
            <span class="badge ${badgeClass} ms-2">${badgeText}</span>
        </div>
        
        <div class="desglose-item">
            <span>Número completo:</span>
            <span class="desglose-valor">${cuenta || 'No ingresado'}</span>
        </div>`;
    
    if (codigoBanco) {
        contenido += `
        <div class="desglose-item">
            <span>Banco (AA):</span>
            <span class="desglose-valor ${nombreBanco === 'Banco Desconocido' ? 'banco-desconocido' : ''}">
                <i class="fas ${nombreBanco === 'Banco Desconocido' ? 'fa-exclamation-triangle text-danger' : 'fa-bank'} me-1"></i>
                ${codigoBanco} → ${nombreBanco}
            </span>
        </div>`;
    }
    
    if (codigoSucursal) {
        contenido += `
        <div class="desglose-item">
            <span>Sucursal (BBBB):</span>
            <span class="desglose-valor ${nombreSucursal.includes('no identificada') ? 'sucursal-no-identificada' : ''}">
                <i class="fas ${nombreSucursal.includes('no identificada') ? 'fa-exclamation-circle text-warning' : 'fa-building'} me-1"></i>
                ${codigoSucursal} → ${nombreSucursal}
            </span>
        </div>`;
    }
    
    if (codigoTipoCuenta) {
        contenido += `
        <div class="desglose-item">
            <span>Tipo cuenta (CC):</span>
            <span class="desglose-valor ${descripcionTipo === 'Tipo de cuenta desconocido' ? 'text-warning' : ''}">
                <i class="fas fa-credit-card me-1"></i>
                ${codigoTipoCuenta} → ${descripcionTipo}
            </span>
        </div>`;
    }
    
    if (numeroCuenta) {
        const digitos = longitud === 16 ? '8 dígitos' : 
                       longitud === 14 ? '6 dígitos' : 
                       numeroCuenta.length + ' dígitos';
        contenido += `
        <div class="desglose-item">
            <span>Número cuenta:</span>
            <span class="desglose-valor">${numeroCuenta} <small class="text-muted">(${digitos})</small></span>
        </div>`;
    }
    
    if (nombreBanco === 'Banco Desconocido') {
        contenido += `
        <div class="alert alert-danger mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Banco Desconocido:</strong> El código <strong>${codigoBanco}</strong> no está registrado en el sistema.
        </div>`;
    }
    
    if (nombreSucursal.includes('no identificada')) {
        contenido += `
        <div class="alert alert-warning mt-2">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Sucursal no identificada:</strong> La sucursal <strong>${codigoSucursal}</strong> no está registrada para este banco.
        </div>`;
    }
    
    if (descripcionTipo === 'Tipo de cuenta desconocido') {
        contenido += `
        <div class="alert alert-warning mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Tipo de cuenta desconocido:</strong> El código <strong>${codigoTipoCuenta}</strong> no está registrado.
        </div>`;
    }
    
    if (!esValida && cuenta.length > 0) {
        contenido += `
        <div class="alert alert-danger mt-2">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error de validación:</strong> ${getMensajeError(cuenta)}
        </div>`;
    }
    
    if (cuenta.length >= 6) {
        contenido += `
        <div class="alert alert-info mt-2">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Nota:</strong> El sistema valida bancos, sucursales y tipos de cuenta según los registros oficiales.
        </div>`;
    }
    
    contenido += `
    <div class="d-grid gap-2 mt-3">
        <button type="button" class="btn btn-primary" onclick="cerrarDesglose(event)">
            <i class="fas fa-check me-2"></i>Entendido
        </button>
    </div>`;
    
    desglose.innerHTML = contenido;
}

function validarCuentaBancariaCompleta(cuenta) {
    const longitud = cuenta.length;
    
    if (longitud !== 14 && longitud !== 16) {
        return false;
    }
    
    if (!/^\d+$/.test(cuenta)) {
        return false;
    }
    
    const codigoBanco = cuenta.substring(0, 2);
    if (!bancosCuba[codigoBanco]) {
        return false;
    }
    
    if (longitud >= 8) {
        const codigoTipo = cuenta.substring(6, 8);
        if (!tiposCuenta[codigoTipo]) {
            return false;
        }
    }
    
    return true;
}

function toggleDesgloseCuenta(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (!desglose || !overlay) return false;
    
    if (desgloseVisible) {
        cerrarDesglose();
        return false;
    }
    
    actualizarDesglose();
    
    overlay.classList.add('open');
    desglose.style.display = 'block';
    desgloseVisible = true;
    
    document.addEventListener('keydown', cerrarDesgloseConESC);
    overlay.addEventListener('click', cerrarDesglose);
    
    return false;
}

function cerrarDesglose(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (desglose) {
        desglose.style.display = 'none';
    }
    
    if (overlay) {
        overlay.classList.remove('open');
        overlay.removeEventListener('click', cerrarDesglose);
    }
    
    desgloseVisible = false;
    document.removeEventListener('keydown', cerrarDesgloseConESC);
    
    return false;
}

function cerrarDesgloseConESC(event) {
    if (event.key === 'Escape' && desgloseVisible) {
        cerrarDesglose();
    }
}

function getMensajeError(cuenta) {
    const longitud = cuenta.length;
    
    if (longitud === 0) {
        return 'Ingrese una cuenta bancaria';
    }
    
    if (!/^\d+$/.test(cuenta)) {
        return 'Solo se permiten números';
    }
    
    if (longitud < 14) {
        return 'Faltan ' + (14 - longitud) + ' dígitos (mínimo 14)';
    }
    
    if (longitud > 16) {
        return 'Sobran ' + (longitud - 16) + ' dígitos (máximo 16)';
    }
    
    if (longitud !== 14 && longitud !== 16) {
        return 'Debe tener 14 o 16 dígitos exactos';
    }
    
    const codigoBanco = cuenta.substring(0, 2);
    if (!bancosCuba[codigoBanco]) {
        return 'Código de banco ' + codigoBanco + ' no reconocido (Banco Desconocido)';
    }
    
    if (longitud >= 8) {
        const codigoTipo = cuenta.substring(6, 8);
        if (!tiposCuenta[codigoTipo]) {
            return 'Tipo de cuenta ' + codigoTipo + ' no reconocido';
        }
    }
    
    return 'Formato de cuenta inválido';
}

function abrirDesglose(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (!desglose || !overlay) return false;
    
    actualizarDesglose();
    
    overlay.classList.add('open');
    desglose.style.display = 'block';
    desgloseVisible = true;
    
    overlay.addEventListener('click', cerrarDesglose);
    document.addEventListener('keydown', cerrarDesgloseConESC);
    
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    const cuentaInput = document.getElementById('cuentaBancaria');
    if (cuentaInput) {
        actualizarDesglose();
        cuentaInput.addEventListener('input', actualizarDesglose);
    }
});

document.addEventListener('click', function(event) {
    const desglose = document.getElementById('desgloseCuenta');
    const overlay = document.getElementById('desgloseOverlay');
    
    if (event.target.closest('.desglose-close-btn')) {
        event.preventDefault();
        event.stopPropagation();
        cerrarDesglose();
        return false;
    }
    
    if (event.target === overlay && desgloseVisible) {
        cerrarDesglose();
        return false;
    }
    
    if (desgloseVisible && event.target.closest('.btn-primary')) {
        const btn = event.target.closest('.btn-primary');
        if (btn && btn.textContent.includes('Entendido')) {
            event.preventDefault();
            event.stopPropagation();
            cerrarDesglose();
            return false;
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (desgloseVisible && event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        cerrarDesglose();
    }
});

// Validación al guardar la información bancaria
document.getElementById('bancariaForm')?.addEventListener('submit', function(e) {
    const cuentaInput = document.getElementById('cuentaBancaria');
    const cuenta = (cuentaInput?.value.trim().replace(/\D/g, '') || '');
    if (cuenta && !validarCuentaBancariaCompleta(cuenta)) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Error en cuenta bancaria',
            html: `
                <div style="text-align: left; font-size: 14px;">
                    <p><strong>La cuenta bancaria no es válida.</strong></p>
                    <div class="alert alert-danger mt-3">
                        <strong>Error:</strong> ${getMensajeError(cuenta)}
                    </div>
                    <p class="mt-3">Por favor, corrija la cuenta bancaria antes de guardar.</p>
                </div>
            `,
            confirmButtonText: '<i class="fas fa-check me-1"></i>Entendido',
            width: 550,
            background: 'var(--panel)',
            color: 'var(--txt)'
        });
        cuentaInput?.focus();
        cuentaInput?.classList.add('is-invalid');
        cuentaInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
// ==================== FIN DE INFORMACIÓN BANCARIA ====================

(function() {
    const svg = document.getElementById('lockClockSvg');
    if (!svg) return;
    const hand = document.getElementById('lockClockHand');
    const ticks = document.getElementById('lockClockTicks');
    const readout = document.getElementById('lockClockReadout');
    const input = document.getElementById('tiempo_para_bloqueo');
    let minutes = parseInt(input.value, 10);
    minutes = isNaN(minutes) ? 10 : Math.min(60, Math.max(1, minutes));

    const NS = 'http://www.w3.org/2000/svg';
    for (let i = 0; i < 60; i++) {
        const major = i % 5 === 0;
        const a = (i * 6 - 90) * Math.PI / 180;
        const r1 = major ? 76 : 84;
        const line = document.createElementNS(NS, 'line');
        line.setAttribute('x1', (100 + Math.cos(a) * r1).toFixed(2));
        line.setAttribute('y1', (100 + Math.sin(a) * r1).toFixed(2));
        line.setAttribute('x2', (100 + Math.cos(a) * 90).toFixed(2));
        line.setAttribute('y2', (100 + Math.sin(a) * 90).toFixed(2));
        line.setAttribute('class', major ? 'lock-clock-tick major' : 'lock-clock-tick');
        ticks.appendChild(line);
    }

    function setMinutes(m) {
        minutes = ((Math.round(m) - 1) % 60 + 60) % 60 + 1;
        input.value = minutes;
        hand.setAttribute('transform', 'rotate(' + (minutes * 6) + ' 100 100)');
        readout.textContent = minutes + ' min';
        svg.setAttribute('aria-valuenow', minutes);
        const badgeMin = document.getElementById('badgeSistemaMin');
        if (badgeMin) badgeMin.textContent = minutes + ' MIN';
    }

    function minutesFromEvent(e) {
        const rect = svg.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        let deg = Math.atan2(x, -y) * 180 / Math.PI;
        if (deg < 0) deg += 360;
        let m = Math.round(deg / 6) % 60;
        return m === 0 ? 60 : m;
    }

    let dragging = false;
    svg.addEventListener('pointerdown', function(e) {
        dragging = true;
        svg.setPointerCapture(e.pointerId);
        setMinutes(minutesFromEvent(e));
        e.preventDefault();
    });
    svg.addEventListener('pointermove', function(e) {
        if (dragging) setMinutes(minutesFromEvent(e));
    });
    svg.addEventListener('pointerup', function() { dragging = false; });
    svg.addEventListener('pointercancel', function() { dragging = false; });
    svg.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowRight') { setMinutes(minutes + 1); e.preventDefault(); }
        else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') { setMinutes(minutes - 1); e.preventDefault(); }
    });

    window.__lockSetMinutes = setMinutes;
    setMinutes(minutes);
})();

(function() {
    const btn = document.getElementById('btnSistemaDefault');
    if (!btn) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (typeof window.__lockSetMinutes === 'function') window.__lockSetMinutes(10);
    });
})();

(function() {
    const chk = document.getElementById('subsistema_nominas');
    const badge = document.getElementById('badgeSistemaEstado');
    if (!chk || !badge) return;
    chk.addEventListener('change', function() {
        badge.textContent = 'SISTEMA/' + (chk.checked ? 'ACTIVO' : 'INACTIVO');
        badge.style.background = chk.checked ? 'var(--color-success)' : '#ef4444';
    });
})();
</script>

</body>
</html>
