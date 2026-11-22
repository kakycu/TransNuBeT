<?php
date_default_timezone_set('America/Havana');
$base = '/servicios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Iniciar sesión | Inventarios | UnicornioSoftware Cuba</title>
<meta name="description" content="Acceso al subsistema Inventarios de UnicornioSoftware. Módulo en desarrollo.">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="/images/logo.png">
<link rel="stylesheet" href="/css/font-awesome6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/fonts/fuentes.css">
<link rel="stylesheet" href="/css/styleindex.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gris-2:#6b7280;--gris-3:#4b5563;--tarjeta:#111827;--abismo:#0a0f1a;--acero:#1e293b;--cian:#00d4ff;--fuego:#ff6b00;--gris:#9ca3af;--gris2:#6b7280;--blanco:#fff;--accent:#8b5cf6;--accent-rgb:139,92,246}
body{min-height:100vh;display:flex;flex-direction:column;background:var(--abismo);color:var(--blanco);font-family:"Exo 2",sans-serif;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
main{flex:1;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;padding:calc(var(--nav-h,5rem) + 2.5rem) 1.25rem 2.5rem}
.bg-glow{position:absolute;inset:0;background:radial-gradient(circle at center,rgba(var(--accent-rgb),.07) 0%,transparent 55%);z-index:0}
.bg-grid{position:absolute;inset:0;opacity:.08;background-image:linear-gradient(rgba(30,41,59,.6) 1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.6) 1px,transparent 1px);background-size:48px 48px;z-index:0}
.card{position:relative;z-index:1;width:100%;max-width:52rem;padding:2rem;border-radius:.85rem;background:rgba(5,8,15,.72);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);box-shadow:0 25px 50px -15px rgba(0,0,0,.9),0 0 60px rgba(var(--accent-rgb),.05)}
.card-grid{display:grid;gap:2rem;align-items:stretch}
@media(min-width:760px){.card-grid{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:2.5rem}.card-head{text-align:left;margin-bottom:0;display:flex;flex-direction:column;justify-content:center}.module-img{margin:0 0 1.25rem}.back-link-wrap{text-align:left}}
.card::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(to right,transparent,rgba(var(--accent-rgb),.55),rgba(255,107,0,.45),transparent)}
.card-head{text-align:center;padding:.5rem 0}
.module-img{display:block;width:min(100%,260px);height:auto;margin:0 auto 1.25rem;border-radius:.85rem;border:1px solid rgba(255,255,255,.12);box-shadow:0 20px 45px -20px rgba(0,0,0,.8)}
.card h1{font-family:"Bebas Neue",Impact,sans-serif;font-weight:400;font-size:clamp(2.2rem,5vw,3rem);letter-spacing:.04em;line-height:.95;color:#fff;margin:0}
.card .sub{margin-top:.65rem;color:#b0b8c4;font-size:.92rem;line-height:1.7;max-width:28rem;margin-left:auto;margin-right:auto}
@media(min-width:760px){.card .sub{margin-left:0;margin-right:0}}
.card-form{display:flex;flex-direction:column;justify-content:center}
.dev-notice{display:flex;gap:.75rem;align-items:flex-start;margin-bottom:1.35rem;padding:.9rem 1rem;border-radius:.65rem;background:rgba(var(--accent-rgb),.08);border:1px solid rgba(var(--accent-rgb),.28);color:#e5e7eb;font-size:.84rem;line-height:1.65}
.dev-notice i{color:var(--accent);margin-top:.15rem;flex:0 0 auto}
.dev-notice strong{color:#fff}
.field{margin-bottom:1.1rem}
.field label{display:block;font-size:.68rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#9ca3af;margin-bottom:.5rem}
.input-wrap{position:relative}
.input-wrap>i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#6b7280;font-size:.9rem}
.input-wrap input{width:100%;padding:.85rem 1rem .85rem 2.75rem;border-radius:.4rem;border:1px solid #334155;background:rgba(17,24,39,.65);color:#e5e7eb;font-family:"Exo 2",sans-serif;font-size:.9rem;outline:none;transition:border-color .2s,box-shadow .2s}
.input-wrap input:disabled{opacity:.55;cursor:not-allowed}
.input-wrap input::placeholder{color:#4b5563}
.btn-login{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;padding:.95rem 1rem;border:none;border-radius:.4rem;background:linear-gradient(135deg,#4b5563,#374151);color:#9ca3af;font-family:"Exo 2",sans-serif;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;cursor:not-allowed;box-shadow:none}
.btn-login i{font-size:.85rem}
.back-link{display:inline-flex;align-items:center;gap:.5rem;margin-top:1.25rem;color:var(--gris);font-size:.82rem;font-weight:600;transition:color .2s}
.back-link:hover{color:var(--cian)}
.footer-note{text-align:center;padding:1.25rem;color:#4b5563;font-size:.72rem;letter-spacing:.04em}
.back-link-wrap{text-align:center}
@media(max-width:480px){.card{padding:1.5rem 1.25rem 1.75rem}}

.wrap{width:100%;max-width:80rem;margin:0 auto;padding:0 clamp(1.25rem,4vw,3rem)}
.bebas{font-family:"Bebas Neue",Impact,sans-serif;font-weight:400;line-height:.92;letter-spacing:.03em}
/* ===== Pie ===== */
    .pie { border-top: 1px solid var(--acero); padding-block: 3rem 1.5rem; background: #080c15; }
    .pie-grid { display: grid; gap: 2rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 720px) { .pie-grid { grid-template-columns: 1.5fr 1fr 1fr; } }
    @media (min-width: 1080px) { .pie-grid { grid-template-columns: 1.6fr 1.05fr 1fr 1fr; } }
    .pie-lista { list-style: none; display: grid; gap: .2rem; }
    .pie-lista a { display: inline-flex; align-items: center; gap: .45rem; }
    .pie-lista a i { color: var(--gris-3); font-size: .78em; transition: color .2s; }
    .pie-lista a:hover i { color: var(--fuego); }
    .pie-social { display: flex; flex-wrap: nowrap; gap: .45rem; margin-bottom: 1.3rem; }
    .pie .pie-social a {
        display: grid; place-items: center;
        width: 2.1rem; height: 2.1rem; border-radius: 50%;
        border: 1px solid var(--acero); color: var(--gris-2);
        background: rgba(255, 255, 255, .03);
        transition: color .2s, border-color .2s, background .2s, transform .2s;
    }
    .pie .pie-social a:hover,
    .pie .pie-social a:focus-visible {
        color: var(--abismo); background: var(--cian); border-color: var(--cian);
        transform: translateY(-2px);
    }
    .pie .pie-social a i,
    .pie .pie-social a svg { color: inherit; transition: transform .2s; }
    .pie .pie-social a:hover i,
    .pie .pie-social a:hover svg,
    .pie .pie-social a:focus-visible i,
    .pie .pie-social a:focus-visible svg { transform: scale(1.18); }
    .pie-social svg { width: .82rem; height: .82rem; }
    .pie-legal-title { margin-top: .4rem; }
    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: .5rem;
        background: var(--tarjeta);
        border: 1px solid var(--acero);
        border-radius: .5rem;
        padding: .5rem;
        min-width: 140px;
        z-index: 50;
        opacity: 0;
        visibility: hidden;
        transition: opacity .2s, visibility .2s;
    }
    .pie-legal { display: flex; flex-wrap: wrap; gap: .3rem 1rem; }
    .pie h4 {
        font-family: "Exo 2", sans-serif; font-size: .74rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .14em; color: var(--gris-2);
        margin-bottom: .8rem;
    }
    .pie a, .pie p { color: var(--gris-3); font-size: .86rem; line-height: 1.9; }
    .pie a:hover { color: var(--cian); }
    .pie-marca { display: flex; align-items: center; gap: .7rem; margin-bottom: .8rem; }
    .pie-marca img { width: 2.2rem; height: 2.2rem; object-fit: contain; }
    .pie-barra {
        margin-top: 2.5rem; padding-top: 1.2rem;
        border-top: 1px solid rgba(30, 41, 59, .8);
        display: flex; flex-wrap: wrap; gap: .6rem; justify-content: space-between;
        color: var(--gris-3); font-size: .78rem;
    }
</style>
</head>
<body>
<nav class="nav">
    <div class="wrap nav-in">
        <a class="marca" href="/index.php" title="UnicornioSoftware Cuba">
            <img src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <span class="marca-txt">
                <b><i>UNICORNIO</i><em>SOFTWARE</em></b>
                <small>Cuba · Gestión empresarial</small>
            </span>
        </a>
                <div class="nav-links">
            <a href="/index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Equipo</a>
            <a href="/index.php#precios"><i class="fa-solid fa-tags" aria-hidden="true"></i> Precios</a>
            <a href="/legal.php"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Info Legal</a>
            <a href="/servicios/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Soporte</a>
            <a href="/desamedida.php"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
            <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
        </div>

        <div class="nav-acc">
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Abrir menú" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="menu-movil" id="menuMovil">
        <a href="/index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
        <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Equipo</a>
        <a href="/index.php#precios"><i class="fa-solid fa-tags" aria-hidden="true"></i> Precios</a>
        <a href="/legal.php"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Info Legal</a>
        <a href="/servicios/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Soporte</a>
        <a href="/desamedida.php"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> A medida</a>
        <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
    </div>
</nav>

<main>
    <div class="bg-glow" aria-hidden="true"></div>
    <div class="bg-grid" aria-hidden="true"></div>

    <div class="card">
        <div class="card-grid">
            <div>
                <div class="card-head">
            <img class="module-img" src="/servicios/images/inventario.png" alt="Subsistema Inventarios" width="960" height="540" loading="eager" decoding="async">
            <h1>SISTEMA INVENTARIOS</h1>
            <p class="sub">Control de existencias, movimientos y almacenes.</p>
        </div>
            </div>
            <div class="card-form">
                <div class="dev-notice" role="status">
            <i class="fa-solid fa-person-digging" aria-hidden="true"></i>
            <div><strong>Módulo en desarrollo.</strong> El acceso a Inventarios aún no está habilitado. Estamos trabajando para incorporarlo al ecosistema UnicornioSoftware. Puedes volver al inicio o contactarnos para más información.</div>
        </div>

                <form action="#" method="post" autocomplete="off" onsubmit="return false;" aria-label="Formulario de acceso deshabilitado">
            <div class="field">
                <label for="login-email">Usuario / Email / CI / Teléfono</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <input type="text" id="login-email" name="email" placeholder="Usuario, email, CI o teléfono" disabled aria-disabled="true">
                </div>
            </div>
            <div class="field">
                <label for="login-password">Contraseña</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    <input type="password" id="login-password" name="password" placeholder="••••••••" disabled aria-disabled="true">
                </div>
            </div>
            <button type="submit" class="btn-login" disabled aria-disabled="true">
                Acceso no disponible <i class="fa-solid fa-lock" aria-hidden="true"></i>
            </button>
        </form>

                <div class="back-link-wrap">
                    <a class="back-link" href="/index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al inicio</a>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="pie">
    <div class="wrap">
        <div class="pie-grid">
            <div>
                <div class="pie-marca">
                    <img src="/images/logo.png" alt="">
                    <span class="bebas" style="font-size:1.5rem"><i style="font-style:normal;color:var(--cian)">UNICORNIO</i><i style="font-style:normal;color:var(--fuego)">SOFTWARE</i></span>
                </div>
                <p>Entorno de Aplicaciones Web de UnicornioSoftware&#174; Cuba. Kaky&#174;. Facturación, nóminas, costos e inventarios en tu propia red.</p>
                <p style="margin-top:.6rem;color:var(--gris-2);font-style:italic">&laquo;Donde tu visión toma forma&raquo;</p>
            </div>

            <nav aria-label="Enlaces del pie">
                <h4>Explorar</h4>
                <ul class="pie-lista">
                    <li><a href="<?= $base ?>/index.php"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Ecosistema Financiero</a></li>
                    <li><a href="<?= $base ?>/nominas/"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a></li>
                    <li><a href="<?= $base ?>/facturacion/index.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a></li>
                    <li><a href="<?= $base ?>/costos/index.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a></li>
                    <li><a href="<?= $base ?>/inventarios/index.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a></li>
                    <li><a href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a></li>
                </ul>
            </nav>

            <div>
                <h4>Contacto</h4>
                <p><a href="mailto:kakycu@gmail.com"><i class="fa-solid fa-envelope" aria-hidden="true"></i> kakycu@gmail.com</a></p>
                <p><a href="tel:+5352712861"><i class="fa-solid fa-phone" aria-hidden="true"></i> +53 5 2712861</a></p>
                <p><i class="fa-solid fa-clock" aria-hidden="true"></i> Lun - Vie, 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</p>
                <p><a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Centro de soporte</a></p>
                <p><a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a></p>
            </div>

            <div>
                <h4>Síguenos</h4>
                <div class="pie-social">
                    <a href="https://www.facebook.com/unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                    <a href="https://www.x.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                    <a href="https://www.instagram.com/unicorniosoftcuba" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                    <a href="https://www.youtube.com/@unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                    <a href="https://api.whatsapp.com/send?phone=5352712861" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
                </div>

                <h4 class="pie-legal-title">Legal</h4>
                <p class="pie-legal">
                    <a href="/legal.php#terminos"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                    <a href="/legal.php#privacidad"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                    <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
                </p>
            </div>
        </div>
        <div class="pie-barra">
            <span>© <?= date('Y') ?> UnicornioSoftware® Cuba. Todos los derechos reservados.</span>
            <span>Licencia registrada · datos alojados en este equipo</span>
        </div>
    </div>
</footer>
</body>
</html>
