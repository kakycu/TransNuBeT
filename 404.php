<?php
http_response_code(404);
$page_title = "Recurso No Encontrado | SisGesNom";
$current_directory = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
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
    text-align: center;
    padding: 6px 0 18px;
}
.error-header i {
    display: block;
    font-size: 84px;
    color: #f87171;
    margin-bottom: 12px;
    filter: drop-shadow(0 0 22px rgba(248, 113, 113, .5));
    animation: blink 1.1s ease-in-out infinite;
}
.error-header h2 {
    font-size: 22px;
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
                <div class="logo-brand">SIGESNOM&reg;</div>
            </div>
        </div>
        <div class="headline">
            <div class="sub">Sistema de Gesti&oacute;n de N&oacute;minas</div>
            <div class="badges">
                <span>Versi&oacute;n del sistema <?php echo '1.0.2'; ?></span>
                <span>Base de datos: <?php echo 'SisGesNominas'; ?></span>
                <span>Recurso no encontrado</span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar">
            <i class="fa-solid fa-magnifying-glass tt-icon"></i>
            <span class="tt-text">Recurso No Encontrado</span>
        </div>
        <div class="step-body">
            <div class="error-header">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h2>Recurso No Encontrado</h2>
            </div>
            <div class="alert error show">
                <b>No Encontrado (Error 404 - Not Found)</b><br>
                El recurso solicitado no existe o ha sido movido a otra ubicaci&oacute;n.
            </div>

            <h2 class="panel-title">Recurso No Encontrado</h2>
            <p class="panel-desc">
                Verifique la direcci&oacute;n escrita en el navegador o utilice los accesos
                disponibles para continuar.
            </p>

            <div class="section-title">Informaci&oacute;n</div>
            <div class="info-grid">
                <div class="info-box">
                    <h3><i class="fa-solid fa-fingerprint"></i> Auditor&iacute;a T&eacute;cnica</h3>
                    <div class="data-row">
                        <span class="data-label">Recurso solicitado:</span>
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
                    <h3><i class="fa-solid fa-circle-exclamation"></i> Posibles Causas</h3>
                    <div class="data-row">
                        <span class="data-label">Direcci&oacute;n URL:</span>
                        <span class="data-value">Escrita incorrectamente</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Archivo:</span>
                        <span class="data-value">Movido o eliminado</span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Enlace:</span>
                        <span class="data-value">Desactualizado</span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button onclick="history.back()" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i>&nbsp;Regresar
                </button>
                <a href="/nominas/index.php" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i>&nbsp;Acceder al Sistema
                </a>
                <a href="/instalarbd/InstalarBD.php" class="btn btn-success">
                    <i class="fa-solid fa-rocket"></i>&nbsp;Instalar BD
                </a>
                <a href="/soporte.php" class="btn btn-outline">
                    <i class="fa-solid fa-headset"></i>&nbsp;Soporte IT
                </a>
            </div>
        </div>
    </div>

    <footer>
        <b>SisGesNom - Sistema de Gesti&oacute;n de N&oacute;minas</b><br>
        Portal principal | Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>
</body>
</html>
