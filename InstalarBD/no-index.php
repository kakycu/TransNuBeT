<?php
http_response_code(403);
$page_title = "Acceso Denegado | SisGesNom";
$current_directory = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/instalarbd/';
$incident_id = strtoupper(substr(md5(time() . rand()), 0, 8));
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title><?php echo $page_title; ?></title>
<link rel="icon" type="image/png" href="/images/favicon.png">
<link rel="stylesheet" href="/css/font-awesome6.4.0/css/all.min.css">
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
.container { width: 100%; max-width: 920px; }
header.top { text-align: center; color: #fff; margin-bottom: 22px; }
header.top .logo { display: flex; align-items: center; justify-content: center; gap: 18px; }
header.top .logo-img { height: 110px; width: auto; max-width: 42vw; }
header.top .logo-text { text-align: left; }
header.top .logo-title { font-size: 28px; font-weight: 800; letter-spacing: .5px; line-height: 1.25; white-space: nowrap; }
header.top .logo-brand { font-size: 24px; font-weight: 800; color: #5eead4; letter-spacing: 1px; margin-top: 4px; }
header.top .logo span { color: #5eead4; }
header.top .headline {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
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
.step-body { padding: 24px 28px 26px; }
.error-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    text-align: center;
    padding: 6px 0 18px;
}
.error-header i {
    display: inline-block;
    font-size: 64px;
    color: #f87171;
    filter: drop-shadow(0 0 22px rgba(248, 113, 113, .5));
    animation: blink 1.1s ease-in-out infinite;
}
.error-header h2 {
    display: inline-block;
    font-size: 32px;
    font-weight: 800;
    color: #fca5a5;
    letter-spacing: .5px;
    animation: blink 1.1s ease-in-out infinite;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: .2; }
}
.alert {
    display: none;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13.5px;
    margin-bottom: 18px;
    line-height: 1.6;
}
.alert.show { display: block; }
.alert.error { background: #450a0a; border: 1px solid #7f1d1d; color: #fca5a5; }
.alert.error b { color: #f87171; }
h2.panel-title { font-size: 20px; color: var(--text); margin-bottom: 6px; }
p.panel-desc { color: var(--muted); font-size: 13.5px; margin-bottom: 18px; }
.section-title {
    font-size: 12.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    margin-bottom: 12px;
}
.links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.flex-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.flex-col {
    flex: 1;
    min-width: 200px;
}
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
    width: 100%;
}
.btn:hover:not(:disabled) { transform: translateY(-1px); }
.btn-primary { background: var(--primary); color: #022c22; }
.btn-primary:hover:not(:disabled) { background: var(--primary-dark); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: #cbd5e1; }
.btn-outline:hover:not(:disabled) { border-color: var(--primary); color: var(--accent); }
.btn-success { background: var(--ok); color: #022c22; }
.btn-success:hover:not(:disabled) { background: #16a34a; }
.info-box {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 18px;
}
.info-box h3 {
    font-size: 12.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-box h3 i { color: var(--primary); }
.data-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 13.5px;
    margin-bottom: 8px;
}
.data-label { color: var(--muted); white-space: nowrap; }
.data-value {
    font-family: Consolas, monospace;
    color: #5eead4;
    word-break: break-all;
    text-align: right;
}
.requirement-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    margin-bottom: 8px;
}
.requirement-item i { font-size: 12px; color: var(--ok); }
.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 18px;
}
.actions .btn { flex: 1; min-width: 140px; }
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
@media (max-width: 560px) {
    .links-grid { grid-template-columns: 1fr; }
    .info-grid { grid-template-columns: 1fr; }
    .flex-row { flex-direction: column; }
    .actions .btn { min-width: 100%; }
    .step-body { padding: 18px 16px 20px; }
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
            <div class="sub">Sistema de Gesti&oacute;n de N&oacute;minas - Instalador</div>
            <div class="badges">
                <span>Versi&oacute;n del sistema <?php echo '1.0.2'; ?></span>
                <span>Base de datos: <?php echo 'SisGesNominas'; ?></span>
                <span>Acceso denegado</span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar">
            <i class="fa-solid fa-ban tt-icon"></i>
            <span class="tt-text">Acceso Denegado</span>
        </div>
        <div class="step-body">
            <div class="error-header">
                <i class="fa-solid fa-lock"></i>
                <h2>Acceso Denegado</h2>
            </div>
            <div class="alert error show">
                <b>Prohibido (Error 403 - Forbidden)</b> No se ha encontrado el archivo <b>index.php</b> en la carpeta del instalador. El listado de directorios ha sido bloqueado para proteger la integridad del servidor.
            </div>

            <h2 class="panel-title">&iquest;Qu&eacute; ocurri&oacute;?</h2>
            <p class="panel-desc">
                El servidor intent&oacute; mostrar la p&aacute;gina de acceso al instalador pero no encontr&oacute;
                ning&uacute;n archivo de &iacute;ndice (index.php o index.html) en esta carpeta.
                Cree y suba un archivo <b>index.php</b> en la carpeta del instalador para que este mensaje desaparezca.
            </p>

            <div class="flex-row">
                <div class="flex-col">
                    <div class="section-title">Accesos disponibles</div>
                    <div class="links-grid">
                        <a class="btn btn-success" href="/instalarbd/InstalarBD.php"><i class="fa-solid fa-rocket"></i>&nbsp;Instalar BD</a>
                        <a class="btn btn-primary" href="/nominas/index.php"><i class="fa-solid fa-key"></i>&nbsp;Acceder al Sistema</a>
                    </div>
                </div>
            </div>

            <div class="section-title" style="margin-top:18px">Informaci&oacute;n</div>
            <div class="info-grid">
                <div class="info-box">
                    <h3><i class="fa-solid fa-fingerprint"></i> Auditor&iacute;a T&eacute;cnica</h3>
                    <div class="data-row">
                        <span class="data-label">Recurso:</span>
                        <span class="data-value"><?php echo htmlspecialchars($current_directory); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">IP Cliente:</span>
                        <span class="data-value"><?php echo htmlspecialchars($client_ip); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">ID Incidente:</span>
                        <span class="data-value">#<?php echo $incident_id; ?></span>
                    </div>
                </div>

                <div class="info-box">
                    <h3><i class="fa-solid fa-key"></i> Requisitos de Acceso</h3>
                    <div class="requirement-item">
                        <i class="fa-solid fa-check"></i> Poseer archivo index.php o index.html
                    </div>
                    <div class="requirement-item">
                        <i class="fa-solid fa-check"></i> Permisos de lectura CHMOD 644
                    </div>
                    <div class="requirement-item">
                        <i class="fa-solid fa-check"></i> Token de sesi&oacute;n administrativa
                    </div>
                </div>
            </div>

            <div class="actions">
                <button onclick="history.back()" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i>&nbsp;Regresar
                </button>
                <a href="/soporte.php" class="btn btn-outline">
                    <i class="fa-solid fa-headset"></i>&nbsp;Soporte IT
                </a>
            </div>
        </div>
    </div>

    <footer>
        <b>SisGesNom - Sistema de Gesti&oacute;n de N&oacute;minas</b><br>
        Instalador del m&oacute;dulo de n&oacute;minas | Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>
</body>
</html>
