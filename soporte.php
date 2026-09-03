<?php
declare(strict_types=1);
session_start();

/* ---------- Nombre de la empresa desde la base de datos ---------- */
$nombreEmpresa = '';
$soporte_whatsapp_raw = '+53 5986 0773';
$email_soporte_raw = 'kakycu@gmail.com';
if (is_file(__DIR__ . '/nominas/config.php')) {
    require_once __DIR__ . '/nominas/config.php';
    try {
        $pdoSoporte = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $q = $pdoSoporte->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('nombre_empresa', 'telefono_soporte', 'email_soporte')");
        $q->execute();
        while ($row = $q->fetch()) {
            if ($row['parametro'] === 'nombre_empresa' && trim((string)$row['valor']) !== '') {
                $nombreEmpresa = trim((string)$row['valor']);
            }
            if ($row['parametro'] === 'telefono_soporte' && trim((string)$row['valor']) !== '') {
                $soporte_whatsapp_raw = trim((string)$row['valor']);
            }
            if ($row['parametro'] === 'email_soporte' && trim((string)$row['valor']) !== '') {
                $email_soporte_raw = trim((string)$row['valor']);
            }
        }
    } catch (Throwable $e) { /* sin BD se usa el respaldo */ }
}
if ($nombreEmpresa === '') $nombreEmpresa = 'SisGesNom';

$soporte_whatsapp = preg_replace('/[^0-9]/', '', $soporte_whatsapp_raw);

$SITE_NAME = $nombreEmpresa . ' - Soporte T&eacute;cnico';
$support_id = "TICKET-" . date('ymd') . "-" . strtoupper(substr(md5(uniqid((string)time(), true)), 0, 6));
$soporte_email = $email_soporte_raw;
$correoDisponible = is_file(__DIR__ . '/correo_privado.php');
$mensaje = null;
$exito = false;

/* ---------- Envio del ticket al correo del administrador ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $correoDisponible) {
    if (!empty($_POST['website'])) {
        $exito = true;
        $mensaje = ['tipo' => 'success', 'titulo' => 'Ticket enviado', 'texto' => 'Hemos recibido tu solicitud.', 'iconoBoton' => 'fa-check', 'claseBoton' => 'wbtn wbtn-accent'];
    } else {
        $tNombre = trim((string)($_POST['nombre'] ?? ''));
        $tCorreo = trim((string)($_POST['email'] ?? ''));
        $tCategoria = trim((string)($_POST['categoria_texto'] ?? ''));
        if ($tCategoria === '') $tCategoria = trim((string)($_POST['categoria'] ?? 'Otro'));
        $tDesc = trim((string)($_POST['descripcion'] ?? ''));
        if ($tNombre === '' || $tDesc === '' || !filter_var($tCorreo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = ['tipo' => 'error', 'titulo' => 'Campos requeridos', 'texto' => 'Completa nombre, un correo valido y la descripcion.', 'iconoBoton' => 'fa-pen-to-square', 'claseBoton' => 'wbtn'];
        } else {
            require __DIR__ . '/lib/phpmailer/Exception.php';
            require __DIR__ . '/lib/phpmailer/PHPMailer.php';
            require __DIR__ . '/lib/phpmailer/SMTP.php';
            require __DIR__ . '/correo_privado.php';

            $tFecha = date('d/m/Y H:i');
            $tIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $eNom = htmlspecialchars($tNombre, ENT_QUOTES, 'UTF-8');
            $eMail = htmlspecialchars($tCorreo, ENT_QUOTES, 'UTF-8');
            $eCat = htmlspecialchars($tCategoria, ENT_QUOTES, 'UTF-8');
            $eDesc = htmlspecialchars($tDesc, ENT_QUOTES, 'UTF-8');
            $eEmp = htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8');
            $eRef = htmlspecialchars($support_id, ENT_QUOTES, 'UTF-8');

            $plantillaTicket = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.08);">
    <tr>
      <td style="background:#1e293b;padding:22px 28px;">
        <table role="presentation" width="100%"><tr>
          <td style="font-size:20px;font-weight:700;color:#ffffff;">{$eEmp}</td>
          <td align="right" style="color:#94a3b8;font-size:13px;">&#127903; Soporte t&eacute;cnico</td>
        </tr></table>
      </td>
    </tr>
    <tr><td style="height:4px;background:linear-gradient(90deg,#14b8a6,#0ea5e9);font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td style="padding:26px 28px 6px;" align="center">
      <div style="display:inline-block;background:#ecfdf5;border:1px solid #10b981;color:#065f46;border-radius:999px;padding:8px 22px;font-size:18px;font-weight:800;letter-spacing:1px;">{$eRef}</div>
      <p style="margin:16px 0 0;color:#334155;font-size:15px;">Nuevo ticket de soporte recibido:</p>
    </td></tr>
    <tr><td style="padding:18px 28px 6px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;">
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">&#128100; Usuario</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:600;">{$eNom}</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#9993; Correo</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;"><a href="mailto:{$eMail}" style="color:#2563eb;font-size:14px;text-decoration:none;">{$eMail}</a></td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">&#128295; Categor&iacute;a</td>
          <td style="padding:12px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">{$eCat}</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;color:#64748b;font-size:13px;">&#128337; Fecha / IP</td>
          <td style="padding:12px 16px;color:#0f172a;font-size:14px;">{$tFecha} &nbsp;&middot;&nbsp; {$tIp}</td>
        </tr>
      </table>
      <div style="margin:20px 0 4px;color:#64748b;font-size:13px;">Categor&iacute;a: <b style="color:#0f172a;">{$eCat}</b> &nbsp;&middot;&nbsp; Descripci&oacute;n del problema:</div>
      <div style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;padding:14px 18px;color:#334155;font-size:14px;line-height:1.7;white-space:pre-wrap;"><b>[{$eCat}]</b> {$eDesc}</div>
    </td></tr>
    <tr><td style="padding:22px 28px 26px;">
      <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">
        Ticket generado automaticamente desde el centro de soporte de {$eEmp}.<br>
        Responde directamente a este correo para contactar al usuario.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 587;
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Username = CORREO_SMTP_USER;
                $mail->Password = CORREO_SMTP_PASS;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(CORREO_SMTP_USER, $nombreEmpresa . ' Soporte');
                $mail->addAddress(CORREO_DESTINATARIO);
                $mail->addReplyTo($tCorreo, $tNombre);
                $mail->Subject = '[TICKET ' . $support_id . '] ' . $tCategoria;
                $mail->isHTML(true);
                $mail->Body = $plantillaTicket;
                $mail->AltBody = "Nuevo ticket de soporte\n\nReferencia: {$support_id}\nUsuario: {$tNombre}\nCorreo: {$tCorreo}\nCategoria: {$tCategoria}\nFecha: {$tFecha}\nIP: {$tIp}\n\nDescripcion:\n{$tDesc}";
                $mail->send();
                $exito = true;
                $mensaje = [
                    'tipo' => 'success',
                    'titulo' => 'Ticket ' . $support_id,
                    'texto' => 'Gracias ' . $tNombre . '. Tu solicitud fue enviada; te responderemos a ' . $tCorreo,
                    'iconoBoton' => 'fa-house',
                    'claseBoton' => 'wbtn wbtn-accent',
                ];
            } catch (Throwable $e) {
                error_log('Soporte: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
                $mensaje = [
                    'tipo' => 'error',
                    'titulo' => 'No se pudo enviar el ticket',
                    'texto' => 'Problema temporal. Usa WhatsApp o intenta mas tarde.',
                    'iconoBoton' => 'fa-rotate-right',
                    'claseBoton' => 'wbtn',
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title><?php echo htmlspecialchars($SITE_NAME); ?></title>
<link rel="icon" type="image/png" href="/images/favicon.png">
<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sweetalert2.min.css">
<link rel="stylesheet" href="/assets/winui.css">
<script src="js/sweetalert211.js"></script>
<style>
:root {
    --primary: #14b8a6;
    --primary-dark: #0d9488;
    --accent: #2dd4bf;
    --bg: #020617;
    --card: #0f172a;
    --panel: #1e293b;
    --border: #334155;
    --text: #e2e8f0;
    --muted: #94a3b8;
    --ok: #22c55e;
    --warn: #f59e0b;
    --fail: #ef4444;
    --wa: #25D366;
    --radius: 12px;
    --shadow: 0 10px 30px rgba(0, 0, 0, .5);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
    min-height: 100vh;
    color: var(--text);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 28px 16px 60px;
}
.bg {
    animation: slide 3s ease-in-out infinite alternate;
    background-image: linear-gradient(-60deg, #6c3 50%, #09f 50%);
    bottom: 0;
    left: -50%;
    opacity: 0.5;
    position: fixed;
    right: -50%;
    top: 0;
    z-index: -1;
}
.bg2 { animation-direction: alternate-reverse; animation-duration: 4s; }
.bg3 { animation-duration: 5s; }
@keyframes slide {
    0% { transform: translateX(-25%); }
    100% { transform: translateX(25%); }
}
.container { width: 100%; max-width: 860px; }
header.top { text-align: center; color: #fff; margin-bottom: 22px; }
header.top .logo { display: flex; align-items: center; justify-content: center; gap: 18px; }
header.top .logo-img { height: 78px; width: auto; max-width: 42vw; }
header.top .logo-text { text-align: left; }
header.top .logo-title { font-size: 28px; font-weight: 800; letter-spacing: .5px; line-height: 1.25; white-space: nowrap; }
header.top .logo-brand { font-size: 24px; font-weight: 800; color: #5eead4; letter-spacing: 1px; margin-top: 4px; }
header.top .headline {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
header.top .sub { opacity: .85; font-size: 14px; }
header.top .badges { font-size: 12px; }
header.top .badges span {
    background: rgba(255,255,255,.12);
    color: #cbd5e1;
    padding: 4px 12px;
    border-radius: 20px;
    margin: 0 4px;
    display: inline-block;
}
.wizard { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.modal-titlebar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
    border-bottom: 1px solid rgba(255, 255, 255, .1);
    text-align: left;
}
.modal-titlebar .tt-icon { font-size: 18px; color: #fca5a5; }
.modal-titlebar .tt-text {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .3px;
    flex: 1;
}
.step-body { padding: 4px 15px 12px; }
.panel-desc { color: var(--muted); font-size: 13.5px; line-height: 1.7; margin-bottom: 18px; }
.ticket-ref { color: var(--accent); font-weight: 700; }
.section-title {
    font-size: 12.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    margin-bottom: 8px;
}
.contact-box {
    background: rgba(0,0,0,.3);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    text-align: center;
    margin-bottom: 10px;
}
.contact-box i { font-size: 1.6rem; color: #60a5fa; margin-right: 8px; vertical-align: middle; }
.contact-box a { color: #60a5fa; text-decoration: none; font-size: 1.1rem; font-weight: 600; }
.contact-box a:hover { text-decoration: underline; }
.contact-box small { color: var(--muted); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 8px; }
.form-label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; font-weight: 500; }
.form-input, .form-textarea, .form-select {
    width: 100%;
    background: rgba(255,255,255,.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 7px 11px;
    color: #fff;
    outline: none;
    transition: all .2s ease;
    font-size: 13px;
    font-family: inherit;
}
.form-input:focus, .form-textarea:focus, .form-select:focus {
    border-color: var(--primary);
    background: rgba(255,255,255,.08);
    box-shadow: 0 0 0 3px rgba(20,184,166,.2);
}
.form-input::placeholder, .form-textarea::placeholder { color: rgba(255,255,255,.3); }
.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 14px;
    padding-right: 40px;
    cursor: pointer;
}
.form-select option { background-color: var(--panel); color: #fff; }
.btn {
    padding: 12px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn:hover:not(:disabled) { transform: translateY(-1px); }
.btn-whatsapp { background: var(--wa); color: #022c22; }
.btn-whatsapp:hover:not(:disabled) { background: #1eb956; }
.btn-email { background: var(--primary); color: #022c22; }
.btn-email:hover:not(:disabled) { background: var(--primary-dark); }
.btn-enviar { background: #4cc2ff; color: #081722; font-weight: 700; }
.btn-enviar:hover:not(:disabled) { background: #63cbff; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: #cbd5e1; }
.btn-outline:hover:not(:disabled) { border-color: var(--primary); color: var(--accent); }
.btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 5px; }
.btn-group.tres { grid-template-columns: repeat(3, 1fr); margin-top: 14px; }
.faq-section { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border); display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.faq-item { background: rgba(255,255,255,.02); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.faq-question { padding: 12px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 500; font-size: 13px; transition: background .2s; user-select: none; }
.faq-question:hover { background: rgba(255,255,255,.05); }
.faq-question i { font-size: 10px; transition: transform .3s ease; color: var(--accent); }
.faq-answer { max-height: 0; overflow: hidden; transition: max-height .3s cubic-bezier(0,1,0,1), padding .3s ease; color: var(--muted); font-size: 12px; background: rgba(0,0,0,.1); }
.faq-answer-inner { padding: 12px 15px; }
.faq-item.active .faq-answer { max-height: 500px; border-top: 1px solid var(--border); }
.faq-item.active .faq-question i { transform: rotate(90deg); }
.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
.info-box {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
}
.info-box i { font-size: 18px; color: var(--accent); margin-bottom: 8px; display: block; }
.info-box h4 { font-size: 13px; margin-bottom: 4px; }
.info-box p { font-size: 12px; color: var(--muted); }
.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: 20px 0 5px;
}
.actions .btn { flex: 1; min-width: 140px; }
.footer-links {
    margin-top: 26px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}
.footer-links a { color: var(--muted); text-decoration: none; font-size: 13px; }
.footer-links a:hover { color: var(--accent); text-decoration: underline; }
footer {
    text-align: center;
    color: rgba(255,255,255,.92);
    font-size: 12.5px;
    margin-top: 22px;
    padding: 14px 18px;
    border-radius: var(--radius);
    background: linear-gradient(-60deg, rgba(34,197,94,.18) 50%, rgba(14,165,233,.18) 50%);
    background-size: 200% 200%;
    animation: footerSlide 6s ease-in-out infinite alternate;
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: var(--shadow);
    letter-spacing: .3px;
}
@keyframes footerSlide {
    0% { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
}
@media (max-width: 768px) {
    .info-grid { grid-template-columns: 1fr 1fr; }
    .form-grid-2 { grid-template-columns: 1fr; }
    .btn-group { grid-template-columns: 1fr; }
    .btn-group.tres { grid-template-columns: 1fr; }
    .actions .btn { min-width: 100%; }
    .step-body { padding: 14px 16px 14px; }
    .logo-title { font-size: 20px; white-space: normal; }
    .logo-brand { font-size: 18px; }
    .logo-img { height: 70px; }
}
</style>
</head>
<body>
<div class="bg"></div>
<div class="bg bg2"></div>
<div class="bg bg3"></div>

<div class="container">
    <header class="top">
        <div class="logo">
            <img src="/images/sigesnom.png" alt="SisGesNom" class="logo-img">
            <div class="logo-text">
                <div class="logo-title">Sistema de Gesti&oacute;n de N&oacute;minas y Trabajadores</div>
                <div class="logo-brand"><?php echo htmlspecialchars(mb_strtoupper($nombreEmpresa), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <div class="headline">
            <div class="sub">Centro de Soporte T&eacute;cnico</div>
            <div class="badges">
                <span>Versi&oacute;n del sistema <?php echo '1.0.2'; ?></span>
                <span>Referencia: <?php echo $support_id; ?></span>
                <span>Sistemas en l&iacute;nea</span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar">
            <i class="fa-solid fa-headset tt-icon"></i>
            <span class="tt-text"><?php echo $SITE_NAME .' ' .$support_id; ?></span>
        </div>
        <div class="step-body">
            <p class="panel-desc">
                &iquest;<b>Necesitas ayuda con el Sistema de Gesti&oacute;n de N&oacute;minas?</b>
                Cont&aacute;ctanos a trav&eacute;s de los siguientes canales oficiales o crea un ticket de atenci&oacute;n.
                Referencia: <span class="ticket-ref"><?php echo $support_id; ?></span>
            </p>

            <div class="contact-box">
                <i class="fas fa-envelope"></i> <a href="mailto:<?php echo $soporte_email; ?>"><?php echo $soporte_email; ?></a>
                &nbsp;&nbsp;
                <i class="fab fa-whatsapp" style="color:#25D366;"></i> <a href="https://wa.me/<?php echo $soporte_whatsapp; ?>?text=Hola%2C%20necesito%20soporte%20t%C3%A9cnico%20para%20el%20sistema%20de%20n%C3%B3minas"><?php echo htmlspecialchars($soporte_whatsapp_raw); ?></a>
                <br>
                <small>(Horario laboral: Lunes a Viernes, 8:00am &ndash; 5:00pm)</small>
            </div>

            <div class="section-title">Nuevo Ticket de Atenci&oacute;n a Usuarios</div>
            <form id="supportForm" method="post" action="soporte.php">
                <input type="hidden" id="ticket_id" value="<?php echo $support_id; ?>">
                <input type="hidden" name="categoria_texto" id="categoriaTexto" value="">
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Su Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-input" placeholder="Nombre completo" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars((string)$_POST['nombre'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo electr&oacute;nico</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="correo@ejemplo.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars((string)$_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Categor&iacute;a</label>
                    <select id="categoria" name="categoria" class="form-select">
                        <option value="Error de Sistema">Error de Sistema / Bug</option>
                        <option value="Cálculos">Problemas de Cálculos</option>
                        <option value="Edición de Datos">Problemas de Edición e Introducción de Datos</option>
                        <option value="Acceso">Acceso / Contraseña</option>
                        <option value="Solicitud">Nueva Función</option>
                        <option value="Instalación BD">Problema de Instalación de Base de Datos</option>
                        <option value="Conexión BD">Error de Conexión a Base de Datos</option>
                        <option value="Creación Tablas">Error al Crear Tablas</option>
                        <option value="Permisos BD">Problema de Permisos de Base de Datos</option>
                        <option value="Configuración BD">Configuración de Base de Datos</option>
                        <option value="Backup/Restore">Backup o Restauración de BD</option>
                        <option value="Migración BD">Migración de Base de Datos</option>
                        <option value="Requisitos Sistema">Verificación de Requisitos</option>
                        <option value="Configuración Servidor">Configuración del Servidor</option>
                        <option value="Problema PHP">Error PHP</option>
                        <option value="Seguridad">Problema de Seguridad</option>
                        <option value="Actualización">Actualización del Sistema</option>
                        <option value="Otro">Otro Problema</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripci&oacute;n</label>
                    <textarea id="descripcion" name="descripcion" class="form-textarea" rows="2" placeholder="Detalle su problema..." required><?php echo isset($_POST['descripcion']) ? htmlspecialchars((string)$_POST['descripcion'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                </div>

                <div class="btn-group tres">
                    <button type="submit" class="btn btn-enviar">
                        <i class="fas fa-paper-plane"></i> Enviar Ticket directo de la web
                    </button>
                    <button type="button" onclick="sendToEmail()" class="btn btn-email">
                        <i class="fas fa-envelope"></i> Enviar con mi correo
                    </button>
                    <button type="button" onclick="sendToWhatsApp()" class="btn btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                    </button>
                </div>

                <div class="section-title" style="margin-top:20px">Preguntas Frecuentes</div>
                <div class="faq-section">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>&iquest;C&oacute;mo recupero mi contrase&ntilde;a?</span>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Puede solicitar un restablecimiento desde la pantalla de inicio de sesi&oacute;n haciendo clic en
                                <strong>"&iquest;Olvid&oacute; su contrase&ntilde;a?"</strong>. Recibir&aacute; un enlace seguro en su correo institucional.
                            </div>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>&iquest;Funciona sin conexi&oacute;n a internet?</span>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                SisGesNom Sistema de Gesti&oacute;n de N&oacute;minas requiere conexi&oacute;n activa
                                para sincronizar los datos con el servidor central, aunque permite la visualizaci&oacute;n de datos consultados previamente.
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="section-title" style="margin-top:22px">Informaci&oacute;n</div>
            <div class="info-grid">
                <div class="info-box" style="border-left: 3px solid #25D366;">
                    <i class="fab fa-whatsapp" style="color:#25D366;"></i>
                    <h4>WhatsApp</h4>
                    <p>L&iacute;nea directa para urgencias cr&iacute;ticas:<br><b><?php echo htmlspecialchars($soporte_whatsapp_raw); ?></b></p>
                </div>
                <div class="info-box">
                    <i class="fas fa-clock"></i>
                    <h4>Horario</h4>
                    <p>Lun - Vie: 8:00 AM - 5:00 PM<br>S&aacute;bados: 9:00 AM - 1:00 PM</p>
                </div>
                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    <h4>Privacidad</h4>
                    <p>Sus datos est&aacute;n protegidos bajo protocolos de cifrado corporativo.</p>
                </div>
                <div class="info-box">
                    <i class="fas fa-ticket-alt"></i>
                    <h4>Reporte de incidencias</h4>
                    <p>Env&iacute;e un correo con el asunto <b>[INCIDENCIA]</b> describiendo el problema y adjuntando capturas si es posible.</p>
                </div>
            </div>

            <div class="actions">
                <a href="index.php" class="btn btn-outline"><i class="fas fa-house"></i>&nbsp;Ir al Inicio</a>
                <a href="nominas/" class="btn btn-outline"><i class="fas fa-key"></i>&nbsp;Entrar al Sistema</a>
                <button onclick="history.back()" class="btn btn-outline"><i class="fas fa-arrow-left"></i>&nbsp;Regresar atr&aacute;s</button>
            </div>

            <div class="footer-links">
                <a href="terminos.php"><i class="fas fa-file-contract"></i> T&eacute;rminos</a>
                <a href="privacidad.php"><i class="fas fa-lock"></i> Privacidad</a>
				<a href="contacto.php"><i class="fas fa-envelope"></i> Contáctenos</a>
                <a href="index.php"><i class="fas fa-home"></i> Ir al Inicio</a>
            </div>
        </div>
    </div>

    <footer>
        <b><?php echo htmlspecialchars($nombreEmpresa, ENT_QUOTES, 'UTF-8'); ?> - Sistema de Gesti&oacute;n de N&oacute;minas</b><br>
        Soporte T&eacute;cnico | Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>

<script>
    function toggleFaq(element) {
        const faqItem = element.parentElement;
        faqItem.classList.toggle('active');
    }

    function getSelectedCategory() {
        const sel = document.getElementById('categoria');
        return sel.options[sel.selectedIndex].text;
    }

    function sendToWhatsApp() {
        const ticket = document.getElementById('ticket_id').value;
        const nombre = document.getElementById('nombre').value;
        const email = document.getElementById('email').value;
        const cat = getSelectedCategory();
        const desc = document.getElementById('descripcion').value;

        if (!nombre || !email || !desc) {
            swalWin({
                icono: 'error',
                titulo: 'Campos requeridos',
                html: '<div style="text-align:left"><div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-user-slash" style="color:#f87171;margin-right:10px;"></i>Nombre</div>' +
                      '<div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-envelope-circle-check" style="color:#f87171;margin-right:10px;"></i>Correo electr&oacute;nico</div>' +
                      '<div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-comment-slash" style="color:#f87171;margin-right:10px;"></i>Descripci&oacute;n</div></div>',
                pie: '<i class="fas fa-circle-exclamation" style="color:#ffc107;"></i> Todos los campos son obligatorios',
                confirmarTexto: 'Entendido',
                confirmarIcono: 'fa-check',
                confirmarClase: 'wbtn'
            });
            return;
        }

        const msg = `*🚀 SISGESNOM SOPORTE*\n*ID:* ${ticket}\n*Usuario:* ${nombre}\n*Email:* ${email}\n*Categoría:* ${cat}\n*Problema:* ${desc}`;
        window.open(`https://wa.me/<?php echo $soporte_whatsapp; ?>?text=${encodeURIComponent(msg)}`, '_blank');
    }

    function sendToEmail() {
        const ticket = document.getElementById('ticket_id').value;
        const nombre = document.getElementById('nombre').value;
        const email = document.getElementById('email').value;
        const desc = document.getElementById('descripcion').value;

        if (!nombre || !email || !desc) {
            swalWin({
                icono: 'error',
                titulo: 'Campos requeridos',
                html: '<div style="text-align:left"><div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-user-slash" style="color:#f87171;margin-right:10px;"></i>Nombre</div>' +
                      '<div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-envelope-circle-check" style="color:#f87171;margin-right:10px;"></i>Correo electr&oacute;nico</div>' +
                      '<div style="margin:8px 0;padding:8px 12px;background:rgba(239,68,68,.1);border-left:3px solid #ef4444;"><i class="fas fa-comment-slash" style="color:#f87171;margin-right:10px;"></i>Descripci&oacute;n</div></div>',
                pie: '<i class="fas fa-circle-exclamation" style="color:#ffc107;"></i> Todos los campos son obligatorios',
                confirmarTexto: 'Entendido',
                confirmarIcono: 'fa-check',
                confirmarClase: 'wbtn'
            });
            return;
        }

        const subject = `Ticket Soporte: ${ticket}`;
        const body = `Nombre: ${nombre}\nEmail: ${email}\n\nCategoría: ${getSelectedCategory()}\n\nDescripción:\n${desc}`;
        window.location.href = `mailto:<?php echo $soporte_email; ?>?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }
</script>
<?php if ($mensaje): ?>
<script>
window.addEventListener('load', () => swalWin({
    icono: '<?= $mensaje["tipo"] ?>',
    titulo: '<?= htmlspecialchars($mensaje["titulo"], ENT_QUOTES, "UTF-8") ?>',
    texto: '<?= htmlspecialchars($mensaje["texto"], ENT_QUOTES, "UTF-8") ?>',
    confirmarIcono: '<?= $mensaje["iconoBoton"] ?>',
    confirmarClase: '<?= $mensaje["claseBoton"] ?>',
    temporizador: <?= $exito ? '9000' : '0' ?>
})<?= $exito ? '.then(() => { window.location.href = "soporte.php"; })' : '' ?>);
</script>
<?php endif; ?>
<script src="/assets/winui.js"></script>
<script>
document.getElementById('supportForm')?.addEventListener('submit', function(){
    const ct=document.getElementById('categoriaTexto');
    if(ct) ct.value = getSelectedCategory();
    const b=this.querySelector('.btn-enviar');
    if(b){ b.disabled=true; b.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Enviando ticket...'; }
});
</script>
</body>
</html>
