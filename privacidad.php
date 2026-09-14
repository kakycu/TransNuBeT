<?php
// privacidad.php
session_start();
$COMPANY_NAME = 'SisGesNom®';
$SITE_NAME = $COMPANY_NAME . ' - Política de Privacidad';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($SITE_NAME); ?></title>
<link rel="icon" type="image/png" href="/images/favicon.png">
<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
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
html { height: 100%; -webkit-text-size-adjust: 100%; }
body {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(160deg, #0f766e 0%, #0f172a 45%, #020617 100%);
    min-height: 100vh;
    color: var(--text);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: clamp(12px, 3vw, 28px) clamp(8px, 2vw, 16px) clamp(32px, 5vw, 60px);
    overflow-x: hidden;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.bg {
    animation: slide 3s ease-in-out infinite alternate;
    background-image: linear-gradient(-60deg, #6c3 50%, #09f 50%);
    bottom: 0; left: -50%; opacity: 0.5;
    position: fixed; right: -50%; top: 0; z-index: -1;
}
.bg2 { animation-direction: alternate-reverse; animation-duration: 4s; }
.bg3 { animation-duration: 5s; }
@keyframes slide {
    0% { transform: translateX(-25%); }
    100% { transform: translateX(25%); }
}
.container { width: 100%; max-width: min(1200px, 96vw); }

/* ===== HEADER ===== */
header.top { text-align: center; color: #fff; margin-bottom: clamp(14px, 3vw, 22px); }
header.top .logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(8px, 2vw, 18px);
    flex-wrap: wrap;
}
header.top .logo-img {
    height: clamp(48px, 12vw, 110px);
    width: auto;
    max-width: 45vw;
    object-fit: contain;
}
header.top .logo-text {
    text-align: center;
    min-width: 0;
    flex: 1 1 200px;
}
header.top .logo-title {
    font-size: clamp(13px, 3.2vw, 28px);
    font-weight: 800;
    letter-spacing: .3px;
    line-height: 1.3;
    white-space: normal;
    word-break: break-word;
}
header.top .logo-brand {
    font-size: clamp(12px, 2.8vw, 24px);
    font-weight: 800;
    color: #5eead4;
    letter-spacing: 1px;
    margin-top: 4px;
    word-break: break-word;
}
header.top .headline {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
    padding: 0 6px;
}
header.top .sub { opacity: .85; font-size: clamp(12px, 2.5vw, 14px); }
header.top .badges {
    font-size: clamp(10px, 2.2vw, 12px);
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}
header.top .badges span {
    background: rgba(255,255,255,.12);
    color: #cbd5e1;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-block;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ===== WIZARD ===== */
.wizard { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.modal-titlebar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
    border-bottom: 1px solid rgba(255, 255, 255, .1);
    text-align: left;
    flex-wrap: wrap;
}
.modal-titlebar .tt-icon { font-size: 18px; color: #fca5a5; flex-shrink: 0; }
.modal-titlebar .tt-text {
    font-size: clamp(12px, 2.6vw, 15px);
    font-weight: 700;
    color: #fff;
    letter-spacing: .3px;
    flex: 1 1 auto;
    min-width: 0;
    word-break: break-word;
}
.step-body { padding: clamp(16px, 3vw, 24px) clamp(14px, 3vw, 28px) clamp(18px, 3vw, 26px); }

.section-title {
    font-size: clamp(11px, 2.3vw, 12.5px);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    margin-bottom: 12px;
}

/* ===== LEGAL ===== */
.legal h1 {
    font-size: clamp(18px, 4.5vw, 26px);
    font-weight: 800;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    flex-wrap: wrap;
    word-break: break-word;
    line-height: 1.25;
}
.legal h1 i { color: var(--accent); flex-shrink: 0; }
.legal .updated {
    color: var(--muted);
    font-size: clamp(12px, 2.5vw, 13px);
    margin-bottom: clamp(14px, 3vw, 22px);
}
.legal .updated strong { color: var(--warn); }
.legal h2 {
    font-size: clamp(14px, 3vw, 15px);
    font-weight: 700;
    color: var(--accent);
    margin: clamp(16px, 3vw, 22px) 0 10px;
    padding-left: 12px;
    border-left: 3px solid var(--primary);
    word-break: break-word;
}
.legal p, .legal li {
    line-height: 1.7;
    color: var(--muted);
    font-size: clamp(12.5px, 2.6vw, 13.5px);
    word-break: break-word;
}
.legal ul {
    margin: 4px 0 14px clamp(16px, 4vw, 22px);
    padding-left: 0;
}
.legal li { margin-bottom: 4px; }

/* ===== ACCIONES ===== */
.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: clamp(16px, 3vw, 20px) 0 5px;
}
.actions .btn { flex: 1 1 160px; min-width: 130px; }
.btn {
    padding: clamp(9px, 2.4vw, 12px) clamp(12px, 2.8vw, 16px);
    border: none;
    border-radius: 8px;
    font-size: clamp(12px, 2.6vw, 13.5px);
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: inherit;
    text-align: center;
    line-height: 1.3;
    word-break: break-word;
}
.btn:hover:not(:disabled) { transform: translateY(-1px); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: #cbd5e1; }
.btn-outline:hover:not(:disabled) { border-color: var(--primary); color: var(--accent); }

/* ===== FOOTER LINKS ===== */
.footer-links {
    margin-top: clamp(18px, 3vw, 26px);
    padding-top: 16px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px clamp(12px, 3vw, 20px);
    justify-content: center;
    flex-wrap: wrap;
}
.footer-links a {
    color: var(--muted);
    text-decoration: none;
    font-size: clamp(11px, 2.4vw, 13px);
}
.footer-links a:hover { color: var(--accent); text-decoration: underline; }

/* ===== FOOTER ===== */
footer {
    text-align: center;
    color: rgba(255,255,255,.92);
    font-size: clamp(11px, 2.3vw, 12.5px);
    margin-top: clamp(16px, 3vw, 22px);
    padding: clamp(12px, 2.5vw, 14px) clamp(12px, 3vw, 18px);
    border-radius: var(--radius);
    background: linear-gradient(-60deg, rgba(34,197,94,.18) 50%, rgba(14,165,233,.18) 50%);
    background-size: 200% 200%;
    animation: footerSlide 6s ease-in-out infinite alternate;
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: var(--shadow);
    letter-spacing: .3px;
    word-break: break-word;
}
@keyframes footerSlide {
    0% { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .container { max-width: 96vw; }
}
@media (max-width: 768px) {
    header.top .logo { flex-direction: column; gap: 10px; }
    header.top .logo-img { max-width: 55vw; }
    header.top .logo-text { text-align: center; flex: 1 1 auto; }
    .actions .btn { flex: 1 1 100%; }
    .legal h1 { font-size: clamp(16px, 5vw, 22px); }
}
@media (max-width: 480px) {
    header.top .logo-img { max-width: 70vw; height: clamp(40px, 14vw, 60px); }
    header.top .badges span { font-size: 10px; padding: 3px 8px; }
    .modal-titlebar { padding: 10px 12px; }
    .legal h1 { font-size: 16px; gap: 6px; }
    .legal h1 i { font-size: 18px; }
    .legal h2 { font-size: 13.5px; padding-left: 8px; }
    .legal p, .legal li { font-size: 12px; }
    .legal ul { margin-left: 14px; }
    footer { padding: 12px 12px; }
    .footer-links { gap: 8px 12px; }
}
@media (max-width: 360px) {
    header.top .logo-title { font-size: 12px; }
    header.top .logo-brand { font-size: 11px; }
    .legal h1 { font-size: 14.5px; }
    .btn { font-size: 11px; padding: 8px 10px; }
    .footer-links a { font-size: 10.5px; }
}
@media (prefers-reduced-motion: reduce) {
    .bg, .bg2, .bg3, footer { animation: none; }
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
                <div class="logo-brand">SIGESNOM&reg;</div>
            </div>
        </div>
        <div class="headline">
            <div class="sub">Pol&iacute;tica de Privacidad</div>
            <div class="badges">
                <span>Versi&oacute;n del sistema <?php echo '1.0.2'; ?></span>
                <span>Actualizado: 25/05/2026</span>
                <span>Informaci&oacute;n confidencial</span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar">
            <i class="fa-solid fa-lock tt-icon"></i>
            <span class="tt-text"><?php echo $SITE_NAME; ?></span>
        </div>
        <div class="step-body">
            <div class="legal">
                <h1><i class="fa-solid fa-shield-halved"></i> Pol&iacute;tica de Privacidad</h1>
                <p class="updated">&Uacute;ltima actualizaci&oacute;n: <strong>25/05/2026</strong></p>

                <h2>1. Datos que recopilamos</h2>
                <p>El Sistema recopila informaci&oacute;n personal de los trabajadores y usuarios autorizados, incluyendo: nombre completo, carn&eacute; de identidad, n&uacute;mero de tel&eacute;fono, direcci&oacute;n, &aacute;rea de trabajo, salario, datos bancarios, historial de pagos y vacaciones. Tambi&eacute;n se almacenan registros de acceso (IP, fecha, hora).</p>

                <h2>2. Uso de la informaci&oacute;n</h2>
                <p>Los datos se utilizan exclusivamente para:</p>
                <ul>
                    <li>Gestionar n&oacute;minas, pagos y deducciones legales.</li>
                    <li>Control de vacaciones y licencias.</li>
                    <li>Generar reportes internos y cumplir con obligaciones fiscales (CESS, impuestos).</li>
                    <li>Auditor&iacute;as de seguridad y trazabilidad de acciones.</li>
                </ul>

                <h2>3. Protecci&oacute;n de datos</h2>
                <p>Toda la informaci&oacute;n se almacena en servidores locales con acceso restringido mediante autenticaci&oacute;n robusta y cifrado de contrase&ntilde;as. No compartimos datos personales con terceros, excepto cuando sea requerido por las autoridades competentes de la Rep&uacute;blica de Cuba.</p>

                <h2>4. Derechos del usuario</h2>
                <p>Usted tiene derecho a solicitar la correcci&oacute;n, eliminaci&oacute;n o limitaci&oacute;n del tratamiento de sus datos personales, siempre que no afecte obligaciones legales. Para ejercer estos derechos, comun&iacute;quese con el administrador del sistema.</p>

                <h2>5. Cookies y seguimiento</h2>
                <p>El Sistema utiliza cookies de sesi&oacute;n estrictamente necesarias para el funcionamiento (autenticaci&oacute;n). No se emplean cookies de terceros ni sistemas de anal&iacute;tica externos.</p>

                <h2>6. Consentimiento</h2>
                <p>Al utilizar el Sistema, usted otorga su consentimiento expl&iacute;cito para el tratamiento de sus datos personales conforme a esta pol&iacute;tica.</p>
            </div>

            <div class="actions">
                <a href="index.php" class="btn btn-outline"><i class="fas fa-house"></i>&nbsp;Ir al Inicio</a>
                <a href="nominas/" class="btn btn-outline"><i class="fas fa-key"></i>&nbsp;Entrar al Sistema</a>
                <button onclick="history.back()" class="btn btn-outline"><i class="fas fa-arrow-left"></i>&nbsp;Regresar atr&aacute;s</button>
            </div>

            <div class="footer-links">
                <a href="terminos.php"><i class="fas fa-file-contract"></i> T&eacute;rminos</a>
				<a href="soporte.php"><i class="fas fa-headset"></i> Soporte</a>
				<a href="contacto.php"><i class="fas fa-lock"></i> Contáctenos</a>
                <a href="index.php"><i class="fas fa-home"></i> Ir al Inicio</a>
            </div>
        </div>
    </div>

    <footer>
        <b>SisGesNom - Sistema de Gesti&oacute;n de N&oacute;minas</b><br>
        Pol&iacute;tica de Privacidad | Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>
</body>
</html>
