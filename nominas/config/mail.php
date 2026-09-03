<?php
// config/mail.php - Configuración de correo SMTP (autoconfigurable por proveedor)

require_once __DIR__ . '/migraciones.php';

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
                case 'mail_password':   $cfg['password']   = $row['valor'];      break;
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
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
