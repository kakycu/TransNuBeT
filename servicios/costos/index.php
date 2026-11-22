<?php
date_default_timezone_set('America/Havana');
$base = '/servicios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Costos · En desarrollo | UnicornioSoftware Cuba</title>
<meta name="description" content="El subsistema de Costos de UnicornioSoftware está en desarrollo. Muy pronto disponible.">
<link rel="icon" type="image/png" href="/images/logo.png">
<link rel="stylesheet" href="/css/font-awesome6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/fonts/fuentes.css">
<link rel="stylesheet" href="/css/portada-licencia.css">
<link rel="stylesheet" href="/css/styleindex.css">
<style>
    .dev-hero{position:relative;min-height:100vh;display:flex;align-items:center;padding:calc(var(--nav-h,5rem) + 3rem) 0 4rem;overflow:hidden;background:var(--abismo,#0a0f1a)}
    .dev-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 18% 20%,rgba(255,167,38,.18),transparent 45%),radial-gradient(ellipse at 82% 75%,rgba(0,212,255,.12),transparent 45%),linear-gradient(180deg,#05080f 0%,#0a0f1a 100%)}
    .dev-bg::after{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(30,41,59,.18) 1px,transparent 1px),linear-gradient(90deg,rgba(30,41,59,.18) 1px,transparent 1px);background-size:48px 48px;opacity:.35}
    .dev-wrap{position:relative;z-index:2;width:100%;max-width:80rem;margin:0 auto;padding:0 clamp(1.25rem,4vw,3rem);display:grid;gap:2.5rem;grid-template-columns:minmax(0,1fr);align-items:center}
    @media(min-width:960px){.dev-wrap{grid-template-columns:1.1fr .9fr}}
    .dev-kicker{display:inline-flex;align-items:center;gap:.55rem;font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:#ffa726;margin-bottom:1rem}
    .dev-kicker .dot{width:.5rem;height:.5rem;border-radius:50%;background:#ffa726;box-shadow:0 0 0 6px rgba(255,167,38,.15);animation:dev-pulse 1.8s ease-in-out infinite}
    @keyframes dev-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.4);opacity:.7}}
    .dev-title{font-family:"Bebas Neue",Impact,sans-serif;font-weight:400;line-height:.92;letter-spacing:.03em;font-size:clamp(3rem,8vw,6rem);margin:0;color:#fff;text-transform:uppercase}
    .dev-title span{color:#ffa726}
    .dev-lead{margin:1.25rem 0 0;max-width:42rem;color:#d1d5db;font-size:1.02rem;line-height:1.85}
    .dev-badge{display:inline-flex;align-items:center;gap:.6rem;margin-top:1.5rem;padding:.7rem 1rem;border-radius:.65rem;background:rgba(255,167,38,.1);border:1px solid rgba(255,167,38,.28);color:#ffd28a;font-family:"Exo 2",sans-serif;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .dev-actions{display:flex;flex-wrap:wrap;gap:.85rem;margin-top:2rem}
    .dev-btn{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;padding:.9rem 1.3rem;border-radius:.55rem;text-decoration:none;font-family:"Exo 2",sans-serif;font-size:.74rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;transition:transform .2s,box-shadow .2s,border-color .2s,color .2s}
    .dev-btn:hover{transform:translateY(-2px)}
    .dev-btn-primary{background:linear-gradient(135deg,#ff6b00,#ff8a33);color:#0a0f1a;box-shadow:0 16px 36px -18px rgba(255,107,0,.7)}
    .dev-btn-ghost{border:1px solid rgba(255,255,255,.16);color:#fff;background:rgba(255,255,255,.02)}
    .dev-btn-ghost:hover{border-color:rgba(0,212,255,.45);color:#00d4ff}
    .dev-card{background:linear-gradient(160deg,rgba(17,24,39,.95),rgba(10,15,26,.98));border:1px solid rgba(30,41,59,.95);border-radius:1rem;padding:1.5rem;box-shadow:0 30px 80px -40px rgba(0,0,0,.8)}
    .dev-card-icon{width:4rem;height:4rem;border-radius:1rem;display:grid;place-items:center;font-size:1.7rem;color:#ffa726;background:rgba(255,167,38,.12);border:1px solid rgba(255,167,38,.28);margin-bottom:1.25rem}
    .dev-card h2{font-family:"Exo 2",sans-serif;font-size:1rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#fff;margin:0 0 .75rem}
    .dev-card p{color:#9ca3af;line-height:1.75;margin:0 0 1rem;font-size:.93rem}
    .dev-list{list-style:none;padding:0;margin:0;display:grid;gap:.7rem}
    .dev-list li{display:flex;gap:.7rem;align-items:flex-start;color:#d1d5db;font-size:.9rem;line-height:1.6}
    .dev-list i{color:#ffa726;margin-top:.2rem;width:1rem;text-align:center;flex:0 0 1rem}
    .dev-note{margin-top:1.25rem;padding:1rem 1.1rem;border-radius:.75rem;background:rgba(0,212,255,.06);border:1px solid rgba(0,212,255,.18);color:#bfefff;font-size:.88rem;line-height:1.7}
    .dev-note strong{color:#fff}
    .dev-hero-img{display:block;width:min(100%,280px);height:auto;border-radius:1rem;border:1px solid rgba(255,255,255,.1);box-shadow:0 24px 60px -30px rgba(0,0,0,.75);margin:0 0 1.5rem}
</style>
</head>
<body id="top">
<!-- ===== Navegación ===== -->
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
            <a class="btn btn-cian btn-sm" href="/servicios/costos/login.php"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Iniciar sesión</a>
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
        <span class="menu-movil-titulo">Acceso</span>
        <a href="/servicios/costos/login.php"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Iniciar sesión</a>
    </div>
</nav>

<main class="dev-hero">
    <div class="dev-bg" aria-hidden="true"></div>
    <div class="dev-wrap">
        <div>
            <img class="dev-hero-img" src="/servicios/images/costos.png" alt="Subsistema de Costos" width="960" height="540" loading="eager" decoding="async">
            <div class="dev-kicker"><span class="dot" aria-hidden="true"></span> Subsistema en construcción</div>
            <h1 class="dev-title">COSTOS<br><span>EN DESARROLLO</span></h1>
            <p class="dev-lead">
                Estamos construyendo el subsistema de <strong style="color:#fff;">Costos</strong> para que puedas controlar fichas de costo, analizar gastos reales, comparar consumos y tomar decisiones con datos confiables dentro del ecosistema UnicornioSoftware.
            </p>
            <div class="dev-badge"><i class="fa-solid fa-person-digging" aria-hidden="true"></i> Módulo en fase de desarrollo</div>
            <div class="dev-actions">
                <a class="dev-btn dev-btn-primary" href="/index.php#ecosistema"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Ver ecosistema</a>
                <a class="dev-btn dev-btn-ghost" href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Solicitar información</a>
            </div>
        </div>

        <aside class="dev-card" aria-label="Información del subsistema Costos">
            <div class="dev-card-icon"><i class="fa-solid fa-calculator" aria-hidden="true"></i></div>
            <h2>¿Qué traerá este subsistema?</h2>
            <p>El objetivo es darle a tu operación una visión clara del costo real de productos y servicios, evitando cálculos dispersos y decisiones a ciegas.</p>
            <ul class="dev-list">
                <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong>Fichas de costo:</strong> estructura de materiales, gastos y componentes por producto o servicio.</span></li>
                <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong>Control de consumos:</strong> seguimiento de recursos utilizados y variaciones.</span></li>
                <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong>Análisis y reportes:</strong> indicadores para evaluar rentabilidad y eficiencia.</span></li>
                <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong>Integración:</strong> pensado para conectarse con el resto del ecosistema UnicornioSoftware.</span></li>
            </ul>
            <div class="dev-note"><strong>¿Necesitas esta solución?</strong> Podemos orientarte sobre el alcance previsto o desarrollar una alternativa a medida mientras el módulo se completa.</div>
        </aside>
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
                    <li><a href="<?= $base ?>/admin/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a></li>
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

<script>
(function () {
    var btn = document.getElementById('menuBtn');
    var menu = document.getElementById('menuMovil');
    if (btn && menu) {
        btn.addEventListener('click', function () {
            var abierto = menu.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            btn.innerHTML = abierto ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        });
    }
})();
</script>
</body>
</html>
