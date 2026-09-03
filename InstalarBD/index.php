<?php
// index.php - Página de acceso a la carpeta del instalador.
// Reemplaza el error "403 Forbidden" del servidor con una página
// en el estilo de las ventanas y modales de InstalarBD.php.
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>Acceso Restringido - <?php echo 'SisGesNom'; ?></title>
<link rel="icon" type="image/png" href="../images/favicon.png">
<link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
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
    grid-template-columns: repeat(4, 1fr);
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
    .info-grid { grid-template-columns: 1fr 1fr; }
    .flex-row { flex-direction: column; }
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
            <div class="sub">Sistema de Gestión de Nóminas - Instalador</div>
            <div class="badges">
                <span>Versión del sistema <?php echo '1.0.2'; ?></span>
                <span>Base de datos: <?php echo 'SisGesNominas'; ?></span>
                <span>Acceso restringido</span>
            </div>
        </div>
    </header>

    <div class="wizard">
        <div class="modal-titlebar">
            <i class="fa-solid fa-triangle-exclamation tt-icon"></i>
            <span class="tt-text">Acceso Restringido</span>
        </div>
        <div class="step-body">
            <div class="error-header">
                <i class="fa-solid fa-shield-halved"></i>
                <h2>Acceso Restringido</h2>
            </div>
            <div class="alert error show">
                <b>Prohibido (Error 403 - Forbidden)</b><br>
                No tiene permiso para acceder a este recurso.<br>
                Adem&aacute;s, se produjo un error 403 (Forbidden) al intentar usar ErrorDocument para manejar la solicitud.
            </div>

            <h2 class="panel-title">Accesos disponibles</h2>
            <p class="panel-desc">Seleccione una de las siguientes opciones para continuar.</p>

            <!-- Instalación y Sistema en dos columnas -->
            <div class="flex-row">
                <div class="flex-col">
                    <div class="section-title">Instalaci&oacute;n</div>
                    <div class="links-grid">
                        <a class="btn btn-success" href="InstalarBD.php"><i class="fa-solid fa-rocket"></i>&nbsp;Instalar BD</a>
                    </div>
                </div>
                <div class="flex-col">
                    <div class="section-title">Sistema</div>
                    <div class="links-grid">
                        <a class="btn btn-primary" href="../index.php"><i class="fa-solid fa-house"></i>&nbsp;Inicio</a>
                        <a class="btn btn-primary" href="../nominas/index.php"><i class="fa-solid fa-key"></i>&nbsp;Logearse al Sistema</a>
                    </div>
                </div>
            </div>

            <!-- Información - 4 botones en una línea -->
            <div class="section-title" style="margin-top:18px">Informaci&oacute;n</div>
            <div class="info-grid">
                <a class="btn btn-outline" href="../soporte.php"><i class="fa-solid fa-headset"></i>&nbsp;Soporte</a>
                <a class="btn btn-outline" href="../privacidad.php"><i class="fa-solid fa-shield-halved"></i>&nbsp;Privacidad</a>
                <a class="btn btn-outline" href="../terminos.php"><i class="fa-solid fa-file-contract"></i>&nbsp;T&eacute;rminos</a>
                <a class="btn btn-outline" href="../Explorer.html"><i class="fa-solid fa-circle-info"></i>&nbsp;Sobre el autor</a>
            </div>
        </div>
    </div>

    <footer>
        <b>SisGesNom - Sistema de Gestión de Nóminas</b><br>
        Instalador del m&oacute;dulo de n&oacute;minas | Copyright &copy; 2000 - <?php echo date('Y'); ?>. All Right Reserved to UnicornioSoftware&reg;
    </footer>
</div>
</body>
</html>