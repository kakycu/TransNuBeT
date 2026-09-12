<?php
// config/mail.php - Configuración de correo SMTP (autoconfigurable por proveedor)

require_once __DIR__ . '/migraciones.php';

/**
 * Clave simétrica derivada para cifrar la contraseña SMTP en BD.
 * Si algún día cambia DB_PASS, migrar la contraseña con sqlDato.
 */
function mailCipherKey() {
    $base = defined('DB_PASS') ? DB_PASS : 'default_smtp_vault';
    return hash('sha256', 'SMTP_MAIL_VAULT_' . $base . '_2026', true);
}

/**
 * Cifra una contraseña SMTP para almacenarla en BD.
 * Devuelve el valor cifrado en base64 (sin prefijo), IV (16 bytes) + ciphertext.
 */
function cifrarMailPassword($plana) {
    if ($plana === '') return '';
    $iv = openssl_random_pseudo_bytes(16);
    $cifrado = openssl_encrypt($plana, 'AES-256-CBC', mailCipherKey(), OPENSSL_RAW_DATA, $iv);
    if ($iv === false || $cifrado === false) return '';
    return base64_encode($iv . $cifrado);
}

/**
 * Descifra una contraseña SMTP almacenada.
 * Valores legacy (texto plano) se devuelven tal cual.
 */
function descifrarMailPassword($guardada) {
    if ($guardada === '') return '';
    // Compatibilidad con formato antiguo ("vault:" + base64)
    if (strpos($guardada, 'vault:') === 0) {
        $guardada = substr($guardada, 6);
    }
    $raw = base64_decode($guardada, true);
    if ($raw === false || strlen($raw) <= 16) return $guardada;
    $iv = substr($raw, 0, 16);
    $datos = substr($raw, 16);
    $plana = openssl_decrypt($datos, 'AES-256-CBC', mailCipherKey(), OPENSSL_RAW_DATA, $iv);
    return ($plana === false) ? $guardada : $plana;
}

/**
 * Cifra cualquier secreto de configuración (p. ej. credenciales OAuth)
 * reutilizando el mismo esquema de cifrado de la contraseña SMTP.
 */
function cifrarSecreto($plana) {
    return cifrarMailPassword($plana);
}

/**
 * Descifra un secreto de configuración almacenado.
 * Valores legacy (texto plano) se devuelven tal cual.
 */
function descifrarSecreto($guardada) {
    return descifrarMailPassword($guardada);
}

/**
 * Lista de proveedores SMTP predeterminados.
 * Clave interna => datos predefinidos (host, puerto, encriptación).
 */
function getProveedoresSMTP() {
    return [
        'gmail'    => ['nombre' => 'Gmail',                  'host' => 'smtp.gmail.com',        'puerto' => 587, 'encriptacion' => 'tls'],
        'outlook'  => ['nombre' => 'Outlook / Hotmail',      'host' => 'smtp-mail.outlook.com', 'puerto' => 587, 'encriptacion' => 'tls'],
        'yahoo'    => ['nombre' => 'Yahoo Mail',             'host' => 'smtp.mail.yahoo.com',   'puerto' => 465, 'encriptacion' => 'ssl'],
        'zoho'     => ['nombre' => 'Zoho Mail',              'host' => 'smtp.zoho.com',         'puerto' => 587, 'encriptacion' => 'tls'],
        'icloud'   => ['nombre' => 'iCloud Mail',            'host' => 'smtp.mail.me.com',      'puerto' => 587, 'encriptacion' => 'tls'],
        'etecsa'   => ['nombre' => 'ETECSA / Nauta',         'host' => 'smtp.nauta.cu',         'puerto' => 25,  'encriptacion' => 'none'],
        'smtp2go'  => ['nombre' => 'SMTP2GO',               'host' => 'smtp.smtp2go.com',      'puerto' => 587, 'encriptacion' => 'tls'],
        'sendpulse'=> ['nombre' => 'SendPulse',             'host' => 'smtp-pulse.com',        'puerto' => 465, 'encriptacion' => 'ssl'],
        'mailtrap' => ['nombre' => 'Mailtrap',              'host' => 'live.smtp.mailtrap.io', 'puerto' => 587, 'encriptacion' => 'tls'],
        'mailersend'=>['nombre' => 'MailerSend',            'host' => 'smtp.mailersend.net',   'puerto' => 587, 'encriptacion' => 'tls'],
        'brevo'    => ['nombre' => 'Brevo',                 'host' => 'smtp-relay.brevo.com',  'puerto' => 587, 'encriptacion' => 'tls'],
        'mailjet'  => ['nombre' => 'Mailjet',               'host' => 'in-v3.mailjet.com',     'puerto' => 587, 'encriptacion' => 'tls'],
        'postmark' => ['nombre' => 'Postmark',              'host' => 'smtp.postmarkapp.com',  'puerto' => 587, 'encriptacion' => 'tls'],
        'sendgrid' => ['nombre' => 'SendGrid',              'host' => 'smtp.sendgrid.net',     'puerto' => 587, 'encriptacion' => 'tls'],
        'elastic'  => ['nombre' => 'Elastic Email',         'host' => 'smtp.elasticemail.com', 'puerto' => 587, 'encriptacion' => 'tls'],
        'custom'   => ['nombre' => 'Personalizado (Manual)', 'host' => '',                      'puerto' => 587, 'encriptacion' => 'tls'],
    ];
}

/**
 * Carga la configuración SMTP desde configuracion_general.
 * Para proveedores predefinidos rellena host/puerto/encriptación automáticamente.
 */
function cargarConfigMail($pdo) {
    asegurarParamsMail($pdo);

    $cfg = [
        'activo'     => 0,
        'proveedor'  => 'custom',
        'host'       => '',
        'port'       => 587,
        'encryption' => 'tls',
        'usuario'    => '',
        'password'   => '',
        'from'       => '',
        'from_name'  => '',
    ];

    try {
        $stmt = $pdo->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('mail_activo','mail_proveedor','mail_host','mail_port','mail_encryption','mail_usuario','mail_password','mail_from','mail_from_name')");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            switch ($row['parametro']) {
                case 'mail_activo':     $cfg['activo']     = (int)$row['valor']; break;
                case 'mail_proveedor':  $cfg['proveedor']  = $row['valor'];      break;
                case 'mail_host':       $cfg['host']       = $row['valor'];      break;
                case 'mail_port':       $cfg['port']       = (int)$row['valor']; break;
                case 'mail_encryption': $cfg['encryption'] = $row['valor'];      break;
                case 'mail_usuario':    $cfg['usuario']    = $row['valor'];      break;
                case 'mail_password':   $cfg['password']   = descifrarMailPassword($row['valor']); break;
                case 'mail_from':       $cfg['from']       = $row['valor'];      break;
                case 'mail_from_name':  $cfg['from_name']  = $row['valor'];      break;
            }
        }
    } catch (PDOException $e) {}

    $proveedores = getProveedoresSMTP();
    if (isset($proveedores[$cfg['proveedor']]) && $cfg['proveedor'] !== 'custom') {
        if (empty($cfg['host']))       $cfg['host']       = $proveedores[$cfg['proveedor']]['host'];
        if (empty($cfg['port']))       $cfg['port']       = $proveedores[$cfg['proveedor']]['puerto'];
        if (empty($cfg['encryption'])) $cfg['encryption'] = $proveedores[$cfg['proveedor']]['encriptacion'];
    }

    return $cfg;
}

/**
 * Envía un correo usando PHPMailer + SMTP.
 * Retorna ['success' => bool, 'error' => mensaje|'mail_not_configured'].
 */
function enviarCorreo($pdo, $to, $toName, $subject, $html, $text) {
    $cfg = cargarConfigMail($pdo);

    if ((int)$cfg['activo'] !== 1 || empty($cfg['host']) || empty($cfg['usuario'])) {
        return ['success' => false, 'error' => 'mail_not_configured'];
    }

    return enviarCorreoConConfig($cfg, $to, $toName, $subject, $html, $text);
}

/**
 * Envía una notificación al usuario cuando su contraseña fue cambiada.
 * Retorna ['success' => bool, 'error' => mensaje|'mail_not_configured'|'sin_email'].
 */
function notificarPasswordCambiada($pdo, $id) {
    $id = intval($id);
    if ($id <= 0) {
        return ['success' => false, 'error' => 'sin_email'];
    }

    try {
        $stmt = $pdo->prepare("SELECT nombre, apellidos, usuario, email FROM clasif_usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'DB Error'];
    }

    if (!$usr) {
        return ['success' => false, 'error' => 'sin_email'];
    }

    $email = trim((string)($usr['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'sin_email'];
    }

    $nombre_completo = trim(($usr['nombre'] ?? '') . ' ' . ($usr['apellidos'] ?? ''));
    $empresa = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom - Nóminas';
    $subject = 'Su contraseña ha sido cambiada - ' . $empresa;
    $anio = date('Y');

    $htmlBody = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contraseña cambiada</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, sans-serif; }
        body { background:#0f0f17; color:#e4e4e7; padding:2rem 1rem; }
        .email-container {
            max-width:560px;
            margin:0 auto;
            background:#16161e;
            border-radius:1rem;
            overflow:hidden;
            border:0.0625rem solid rgba(255,255,255,0.08);
            box-shadow: 0 1.5rem 3rem rgba(0,0,0,0.55);
        }
        .header {
            background: linear-gradient(135deg, rgba(16,185,129,0.18), rgba(16,185,129,0.05) 70%), #16161e;
            padding:1.75rem 1.5rem;
            border-bottom:0.0625rem solid rgba(255,255,255,0.06);
        }
        .header-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
        .header-icon { display:flex; align-items:center; gap:0.625rem; }
        .icon-circle {
            width:2.5rem; height:2.5rem; border-radius:0.75rem;
            background: linear-gradient(135deg, #10b981, #059669);
            display:flex; align-items:center; justify-content:center;
            font-size:1.25rem; color:#fff;
        }
        .app-name { font-size:0.8125rem; font-weight:600; color:#cbd5e1; }
        .app-name span { color:#64748b; font-weight:400; }
        .header-badge {
            font-size:0.625rem; font-weight:600; letter-spacing:0.05em;
            color:#34d399; background:rgba(16,185,129,0.12);
            border:0.0625rem solid rgba(16,185,129,0.3);
            padding:0.3125rem 0.625rem; border-radius:999px;
        }
        .header-title { font-size:1.375rem; font-weight:700; color:#fff; letter-spacing:-0.01em; }
        .header-subtitle { font-size:0.8125rem; color:#94a3b8; margin-top:0.25rem; }
        .body { padding:1.5rem; }
        .greeting { font-size:1rem; margin-bottom:0.875rem; color:#f1f5f9; }
        .greeting strong { color:#fff; }
        .message-text { font-size:0.8125rem; line-height:1.6; color:#cbd5e1; margin-bottom:1.125rem; }
        .message-text strong { color:#f1f5f9; }
        .info-card {
            background:rgba(255,255,255,0.03);
            border:0.0625rem solid rgba(255,255,255,0.07);
            border-radius:0.625rem;
            padding:1rem 1.125rem;
            margin-bottom:1.125rem;
        }
        .card-row { display:flex; align-items:center; justify-content:space-between; padding:0.375rem 0; }
        .card-row + .card-row { border-top:0.0625rem solid rgba(255,255,255,0.05); }
        .card-label { font-size:0.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; }
        .card-value { font-size:0.8125rem; color:#f1f5f9; font-weight:600; word-break:break-all; text-align:right; }
        .ok-box {
            background:rgba(16,185,129,0.12);
            border:0.0625rem solid rgba(16,185,129,0.35);
            padding:0.4rem 0.875rem; border-radius:0.5rem;
            font-size:0.8125rem; color:#34d399;
            display:inline-block; font-weight:600;
        }
        .warning-note {
            background:rgba(255,170,0,0.06);
            border-radius:0.5rem;
            padding:0.875rem 1.125rem;
            border-left:0.1875rem solid #ffa500;
        }
        .note-title { color:#ffa500; font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; }
        .note-text { color:#a1a1aa; font-size:0.75rem; margin-top:0.25rem; line-height:1.5; }
        .footer {
            background:#12121a;
            padding:1.25rem 1.5rem;
            text-align:center;
            border-top:0.0625rem solid rgba(255,255,255,0.06);
        }
        .security-badge { font-size:0.6875rem; color:#8a8a8a; margin-bottom:0.75rem; }
        .company-info { font-size:0.75rem; color:#6a6a6a; line-height:1.6; }
        .company-info strong { color:#a0a0a0; }
        .copyright {
            font-size:0.625rem; color:#5a5a5a; margin-top:0.625rem;
            padding-top:0.625rem; border-top:0.0625rem solid rgba(255,255,255,0.04);
        }
        @media (max-width: 480px) {
            .body { padding:1.5rem 1.125rem; }
            .header { padding:1.25rem 1.125rem; }
            .footer { padding:1rem 1.125rem 1.25rem; }
            .header-title { font-size:1.0625rem; }
            .info-card { padding:1rem; }
            .header-top { flex-wrap:wrap; gap:0.5rem; }
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
            <div class="header-title">Su contraseña fue actualizada</div>
            <div class="header-subtitle">Cambio de contraseña exitoso</div>
        </div>

        <!-- BODY -->
        <div class="body">
            <p class="greeting">Hola <strong>' . htmlspecialchars($nombre_completo) . '</strong>,</p>

            <p class="message-text">
                Le confirmamos que la contraseña de su cuenta
                <strong>' . htmlspecialchars($usr['usuario']) . '</strong> en el sistema
                <strong>' . htmlspecialchars($empresa) . '</strong> fue cambiada correctamente.
            </p>

            <!-- Info Card -->
            <div class="info-card">
                <div class="card-row">
                    <span class="card-label">👤 Usuario</span>
                    <span class="card-value">' . htmlspecialchars($usr['usuario']) . '</span>
                </div>
                <div class="card-row">
                    <span class="card-label">🔑 Estado</span>
                    <span class="card-value"><span class="ok-box">✅ Contraseña actualizada</span></span>
                </div>
            </div>

            <!-- Nota de seguridad -->
            <div class="warning-note">
                <div class="note-title">
                    <span>🛡️</span> ¿No realizó este cambio?
                </div>
                <div class="note-text">
                    Si usted no cambió su contraseña, contacte de inmediato al administrador del sistema.
                    Es posible que su cuenta haya sido comprometida.
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
                <span>© ' . $anio . ' ' . htmlspecialchars($empresa) . ' · Todos los derechos reservados</span>
            </div>
        </div>
    </div>
</body>
</html>';

    $textBody = "═══════════════════════════════════════════════════════\n"
        . "        CONTRASEÑA ACTUALIZADA\n"
        . "═══════════════════════════════════════════════════════\n\n"
        . "Hola {$nombre_completo},\n\n"
        . "Le confirmamos que la contraseña de su cuenta de usuario\n"
        . "'{$usr['usuario']}' en el sistema '{$empresa}' fue cambiada\n"
        . "correctamente.\n\n"
        . "───────────────────────────────────────────────────────────\n"
        . "  ¿NO REALIZÓ ESTE CAMBIO?\n"
        . "───────────────────────────────────────────────────────────\n\n"
        . "Si usted no cambió su contraseña, contacte de inmediato al\n"
        . "administrador del sistema.\n\n"
        . "───────────────────────────────────────────────────────────\n"
        . "  {$empresa}\n"
        . "  Sistema de Gestión de Nóminas y Empleados\n"
        . "───────────────────────────────────────────────────────────\n"
        . "© " . date('Y') . " {$empresa} · Todos los derechos reservados\n";

    return enviarCorreo($pdo, $email, $nombre_completo, $subject, $htmlBody, $textBody);
}

/**
 * Envía un correo con una configuración SMTP dada (útil para probar sin guardar).
 */
function enviarCorreoConConfig($cfg, $to, $toName, $subject, $html, $text) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['usuario'];
        $mail->Password   = $cfg['password'];
        $mail->Port       = (int)$cfg['port'];
        if ($cfg['encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($cfg['encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }
        $mail->CharSet    = 'UTF-8';

        // Nauta y varios proveedores exigen que el remitente pertenezca a la cuenta autenticada.
        $fromEmail = !empty($cfg['from']) ? $cfg['from'] : $cfg['usuario'];
        if (strtolower(trim($fromEmail)) !== strtolower(trim($cfg['usuario']))) {
            $fromEmail = $cfg['usuario'];
        }
        $mail->setFrom($fromEmail, !empty($cfg['from_name']) ? $cfg['from_name'] : 'SisGesNom');
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        if (!empty($text)) {
            $mail->AltBody = $text;
        }
        $mail->Encoding = \PHPMailer\PHPMailer\PHPMailer::ENCODING_QUOTED_PRINTABLE;

        $mail->send();
        return ['success' => true, 'error' => ''];
    } catch (\Exception $e) {
        $detalle = $e->getMessage();
        try {
            $smtpErr = $mail->getSMTPInstance()->getError();
            if (!empty($smtpErr['detail'])) $detalle = $smtpErr['detail'];
        } catch (\Throwable $t) {}
        return ['success' => false, 'error' => $detalle];
    }
}
