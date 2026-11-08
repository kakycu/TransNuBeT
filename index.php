<?php
/**
 * Landing público del Entorno de Aplicaciones Web de UnicornioSoftware® Cuba.
 *
 * Vive en la raíz del sitio para que http://localhost/ muestre esta portada.
 * Todo el sistema (portada de servicios, nóminas, facturación, paneles…) quedó
 * bajo /servicios/. Los enlaces antiguos sin /servicios/ se redirigen desde el
 * .htaccess de la raíz.
 *
 * Estilos: tokens del tema NeuroAI (fondo #070a12, superficies glass, acento
 * violeta #6d5dfc y cian #24d6ff, tipografía Inter).
 */

$anio = (int)date('Y');
$base = '/servicios';

$ecosistema = [
    [
        'titulo'  => 'Ecosistema Financiero',
        'texto'   => 'Facturación, nóminas, costos e inventarios en un solo lugar: entra al sistema completo y elige el módulo que necesitas.',
        'icono'   => 'fa-solid fa-wallet',
        'url'     => $base . '/index.php',
        'etiqueta'=> 'Portada del sistema',
        'destacada' => true,
    ],
    [
        'titulo'  => 'Gestión de Nóminas',
        'texto'   => 'Cálculo de nóminas, cierres mensuales, vacaciones, contribuciones y exportaciones bancarias.',
        'icono'   => 'fa-solid fa-money-check-dollar',
        'url'     => $base . '/nominas/',
        'etiqueta'=> 'Módulo de nóminas',
    ],
    [
        'titulo'  => 'Facturación Electrónica',
        'texto'   => 'Emisión de facturas, clientes, servicios y reportes de cierre con control de usuarios.',
        'icono'   => 'fa-solid fa-file-invoice-dollar',
        'url'     => $base . '/facturacion/index.php',
        'etiqueta'=> 'Módulo de facturación',
    ],
    [
        'titulo'  => 'Panel del Servidor',
        'texto'   => 'Estado de Apache, PHP y MySQL: versión, uptime real del motor y lanzadores de herramientas.',
        'icono'   => 'fa-solid fa-server',
        'url'     => $base . '/phpinfo/',
        'etiqueta'=> 'Información del servidor',
    ],
    [
        'titulo'  => 'Instalador de Base de Datos',
        'texto'   => 'Asistente para crear o restaurar la base de datos del sistema cuando se prepara un equipo nuevo.',
        'icono'   => 'fa-solid fa-database',
        'url'     => $base . '/InstalarBD/InstalarBD.php',
        'etiqueta'=> 'Preparar el sistema',
    ],
    [
        'titulo'  => 'Soporte y Contacto',
        'texto'   => 'Documentación de soporte, contacto directo y canales de atención para la operación diaria.',
        'icono'   => 'fa-solid fa-headset',
        'url'     => $base . '/soporte.php',
        'etiqueta'=> 'Ayuda y contacto',
    ],
];

$servicios = [
    ['icono' => 'fa-solid fa-database',      'titulo' => 'Base de datos',   'texto' => 'Motor MySQL con integridad referencial, respaldo periódico y restauración confiable.'],
    ['icono' => 'fa-brands fa-php',          'titulo' => 'PHP 8.1',         'texto' => 'Compatibilidad con el stack local y rendimiento estable para todos los módulos.'],
    ['icono' => 'fa-solid fa-shield-halved', 'titulo' => 'Seguridad',       'texto' => 'Comunicación cifrada, autenticación reforzada y secretos fuera del código.'],
    ['icono' => 'fa-solid fa-arrows-rotate', 'titulo' => 'Sincronización',  'texto' => 'Información siempre al día entre módulos, sin cálculos repetidos ni desfases.'],
    ['icono' => 'fa-solid fa-clock',         'titulo' => 'Soporte 24/7',    'texto' => 'Acompañamiento continuo para la operación y resolución de incidencias.'],
    ['icono' => 'fa-solid fa-lock',          'titulo' => 'Licencia local',  'texto' => 'Todo funciona en el equipo del cliente: los datos no salen de la red local.'],
];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®</title>
<meta name="description" content="Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®. Facturación, nóminas y gestión en una plataforma local.">
<link rel="icon" type="image/png" href="/images/logo.png">
<link rel="apple-touch-icon" href="/images/logo.png">
<link rel="stylesheet" href="<?= $base ?>/css/font-awesome6.4.0/css/all.min.css">
<script>
    /* Aplica el tema guardado antes de pintar para que no haya parpadeo. */
    (function () {
        try {
            var tema = localStorage.getItem('theme');
            document.documentElement.setAttribute('data-bs-theme', tema === 'light' ? 'light' : 'dark');
        } catch (e) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    })();
</script>
<style>
    /* ===== Tokens del tema (NeuroAI), oscuro por defecto ===== */
    :root {
        --bg:            #070a12;
        --bg-soft:       #0b1020;
        --surface:       rgba(18, 26, 45, .72);
        --surface-strong:rgba(24, 33, 56, .82);
        --surface-solid: #121a2d;
        --tile:          rgba(255, 255, 255, .05);
        --panel:         rgba(11, 16, 32, .82);
        --header-bg:     rgba(7, 10, 18, .78);
        --footer-bg:     rgba(9, 13, 24, .74);
        --overlay-top:   rgba(7, 10, 18, .32);
        --overlay-bottom:rgba(7, 10, 18, .78);
        --fondo-imagen:  url("/images/fondo.jpg");
        --fondo-opacidad: 1.55;
        --shine:         rgba(255, 255, 255, .10);
        --card-hover-border: rgba(255, 255, 255, .28);
        --card-hover-shadow: 0 0 0 1px rgba(36, 214, 255, .12), 0 1.25rem 4rem rgba(109, 93, 252, .30);
        --card-icon-color: #cfd6ff;
        --card-top:      rgba(255, 255, 255, .05);
        --card-bottom:   rgba(255, 255, 255, .015);
        --destacada-top: rgba(109, 93, 252, .16);
        --destacada-bottom: rgba(18, 26, 45, .72);
        --border:        rgba(255, 255, 255, 0.12);
        --border-strong: rgba(255, 255, 255, 0.18);
        --txt:           #f7f9ff;
        --muted:         #a8b3c7;
        --subtle:        #7f8ba3;
        --accent:        #6d5dfc;
        --accent-soft:   rgba(109, 93, 252, 0.18);
        --cyan:          #24d6ff;
        --mint:          #2ee59d;
        --radius:        .75rem;
        --radius-lg:     1rem;
        --shadow:        0 1.5rem 4rem rgba(0, 0, 0, .35);
        --shadow-glow:   0 0 0 1px rgba(36, 214, 255, .08), 0 1.25rem 4rem rgba(109, 93, 252, .22);
        --switch-bg:     rgba(255, 255, 255, .08);
        --switch-border: rgba(255, 255, 255, .18);
        --switch-thumb:  #232c38;
        --switch-thumb-border: rgba(255, 255, 255, .22);
        --switch-sun:    #8b9bb4;
        --switch-moon:   #24d6ff;
        --max:           72rem;
    }
    /* ===== Tema claro (mismo mecanismo que el panel phpinfo) ===== */
    [data-bs-theme="light"] {
        --bg:            #eef2fa;
        --bg-soft:       #f7f9fd;
        --surface:       rgba(255, 255, 255, .84);
        --surface-strong:rgba(255, 255, 255, .96);
        --surface-solid: #ffffff;
        --tile:          rgba(15, 23, 42, .04);
        --panel:         rgba(255, 255, 255, .76);
        --header-bg:     rgba(255, 255, 255, .86);
        --footer-bg:     rgba(255, 255, 255, .84);
        --overlay-top:   rgba(238, 242, 250, .55);
        --overlay-bottom:rgba(238, 242, 250, .92);
        --fondo-imagen:  url("/images/fondo-dia.jpg");
        --fondo-opacidad: 1.55;
        --shine:         rgba(99, 90, 230, .26);
        --card-hover-border: rgba(91, 75, 219, .5);
        --card-hover-shadow: 0 0 0 1px rgba(14, 116, 144, .2), 0 1.1rem 2.8rem rgba(91, 75, 219, .30);
        --card-icon-color: #4c3fd0;
        --card-top:      rgba(255, 255, 255, .92);
        --card-bottom:   rgba(255, 255, 255, .55);
        --destacada-top: rgba(109, 93, 252, .12);
        --destacada-bottom: rgba(255, 255, 255, .86);
        --border:        rgba(15, 23, 42, 0.12);
        --border-strong: rgba(15, 23, 42, 0.20);
        --txt:           #101828;
        --muted:         #475467;
        --subtle:        #667085;
        --accent:        #5b4bdb;
        --accent-soft:   rgba(91, 75, 219, 0.12);
        --cyan:          #0e7490;
        --mint:          #047857;
        --shadow:        0 1.5rem 4rem rgba(16, 24, 40, .12);
        --shadow-glow:   0 0 0 1px rgba(14, 116, 144, .10), 0 1.25rem 4rem rgba(91, 75, 219, .16);
        --switch-bg:     #e2e8f2;
        --switch-border: #cfd7e4;
        --switch-thumb:  #ffffff;
        --switch-thumb-border: #cfd7e4;
        --switch-sun:    #f59e0b;
        --switch-moon:   #94a3b8;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    /* Que el navbar fijo no tape el título al saltar a una sección. */
    section[id] { scroll-margin-top: 4.75rem; }
    body {
        margin: 0;
        background: var(--bg);
        color: var(--txt);
        font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }
    /* Fondo fotográfico (imagen descargada en /images/fondo.jpg) + capa de
     * oscurecido y resplandores del tema para no perder legibilidad. */
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -2;
        background: var(--fondo-imagen) center center / cover no-repeat;
        opacity: var(--fondo-opacidad);
        pointer-events: none;
        transition: opacity .3s ease;
    }
    body::after {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        background:
            radial-gradient(60rem 40rem at 15% -5%, rgba(36, 214, 255, .12), transparent 60%),
            radial-gradient(55rem 38rem at 88% 0%, rgba(109, 93, 252, .18), transparent 60%),
            linear-gradient(180deg, var(--overlay-top) 0%, var(--overlay-bottom) 85%);
        pointer-events: none;
    }
    a { color: inherit; }
    img { max-width: 100%; display: block; }
    .skip {
        position: absolute; top: .75rem; left: .75rem;
        padding: .6rem .9rem; border-radius: .5rem;
        background: var(--accent); color: #fff;
        transform: translateY(-160%); transition: transform .2s;
        z-index: 100;
    }
    .skip:focus { transform: translateY(0); }

    .wrap { width: min(100% - 2.5rem, var(--max)); margin-inline: auto; }
    .section { padding-block: clamp(3.5rem, 7vw, 6rem); }
    .stack > * + * { margin-top: 1rem; }

    /* ===== Barra superior ===== */
    .site-header {
        position: sticky; top: 0; z-index: 50;
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        background: var(--header-bg);
        border-bottom: 1px solid var(--border);
    }
    .nav {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; min-height: 4.25rem;
    }
    .brand { display: inline-flex; align-items: center; gap: .7rem; text-decoration: none; font-weight: 800; min-width: 0; }
    .brand-logo {
        width: 2.9rem; height: 2.9rem; flex: 0 0 auto;
        object-fit: contain;
        padding: .18rem;
        border-radius: .7rem;
        background: var(--tile);
        border: 1px solid var(--border);
    }
    .brand-name { font-size: clamp(.76rem, 1.5vw, .92rem); line-height: 1.25; letter-spacing: -.01em; }
    .brand-name small {
        display: block;
        font-size: clamp(.55rem, 1vw, .63rem);
        font-weight: 600; letter-spacing: .06em;
        color: var(--subtle);
    }
    .nav-links { display: none; align-items: center; gap: 1.6rem; }
    .nav-links a {
        display: inline-flex; align-items: center; gap: .42rem;
        text-decoration: none; color: var(--muted); font-size: .92rem; font-weight: 500;
        transition: color .15s;
    }
    .nav-links a i { font-size: .8rem; color: var(--subtle); transition: color .15s; }
    .nav-links a:hover, .nav-links a:focus-visible { color: var(--txt); }
    .nav-links a:hover i, .nav-links a:focus-visible i { color: var(--cyan); }

    /* ===== Botones ===== */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .7rem 1.15rem; border-radius: .65rem;
        border: 1px solid transparent; cursor: pointer;
        font: inherit; font-weight: 700; font-size: .92rem;
        text-decoration: none; white-space: nowrap;
        transition: transform .15s, background .15s, border-color .15s, box-shadow .15s;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
        color: #fff;
        background: linear-gradient(135deg, var(--accent) 0%, #5d4fd6 100%);
        box-shadow: 0 .6rem 1.6rem rgba(109, 93, 252, .35);
    }
    .btn-primary:hover, .btn-primary:focus-visible {
        transform: translateY(-1px);
        box-shadow: 0 0 0 1px rgba(36, 214, 255, .08), 0 1.25rem 4rem rgba(109, 93, 252, .22);
    }
    .btn-glass {
        color: var(--txt); background: var(--surface);
        border-color: var(--border-strong);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    }
    .btn-glass:hover { border-color: var(--border-strong); background: var(--surface-strong); }
    .btn-sm { padding: .55rem .9rem; font-size: .82rem; }

    /* ===== Hero ===== */
    .hero { padding-block: clamp(4rem, 9vw, 7.5rem) clamp(3rem, 6vw, 5rem); }
    .badge {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .38rem .8rem; border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--cyan) 40%, transparent);
        background: color-mix(in srgb, var(--cyan) 10%, transparent);
        color: var(--txt); font-size: .76rem; font-weight: 700;
        letter-spacing: .02em;
    }
    .badge .dot {
        width: .45rem; height: .45rem; border-radius: 50%;
        background: var(--mint); box-shadow: 0 0 0 .25rem rgba(46, 229, 157, .18);
    }
    h1 {
        margin: 1.1rem 0 0;
        font-size: clamp(2.35rem, 5.6vw, 4rem);
        line-height: 1.06; letter-spacing: -.03em; font-weight: 800;
        max-width: 22ch;
    }
    .text-gradient {
        background: linear-gradient(135deg, var(--accent) 0%, var(--cyan) 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .lead { margin-top: 1.1rem; max-width: 60ch; color: var(--muted); font-size: clamp(1rem, 1.6vw, 1.12rem); }
    .hero-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.9rem; }
    .hero-note { margin-top: 1.1rem; color: var(--subtle); font-size: .82rem; }

    /* ===== Métricas ===== */
    .metrics {
        display: grid; gap: 1px; margin-top: clamp(2.5rem, 5vw, 4rem);
        grid-template-columns: repeat(2, 1fr);
        background: var(--border);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }
    .metric { padding: 1.15rem 1.25rem; background: var(--panel); }
    .metric i { display: block; color: var(--cyan); font-size: .95rem; margin-bottom: .4rem; }
    .metric b { display: block; font-size: 1.35rem; letter-spacing: -.02em; }
    .metric span { color: var(--subtle); font-size: .78rem; }

    /* ===== Títulos de sección ===== */
    .eyebrow {
        text-transform: uppercase; letter-spacing: .14em;
        font-size: .72rem; font-weight: 800; color: var(--cyan);
    }
    h2 {
        margin: .55rem 0 0;
        font-size: clamp(1.7rem, 3.4vw, 2.4rem);
        line-height: 1.15; letter-spacing: -.02em; font-weight: 800;
    }
    .section-head p { margin: .8rem 0 0; max-width: 62ch; color: var(--muted); }

    /* ===== Tarjetas glass ===== */
    .grid { display: grid; gap: 1.1rem; margin-top: 2rem; grid-template-columns: 1fr; }
    .card {
        position: relative; overflow: hidden;
        display: flex; flex-direction: column;
        padding: 1.35rem;
        border-radius: var(--radius-lg);
        background:
            linear-gradient(180deg, var(--card-top), var(--card-bottom)),
            var(--surface);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        text-decoration: none; color: inherit;
        transition: border-color .18s, box-shadow .18s, transform .18s, background-color .18s;
    }
    /* Barrido de luz del tema NeuroAI: una franja diagonal cruza la tarjeta al
     * pasar el ratón (::after de -130% a 135%, transición de .6s). */
    .card::after {
        content: "";
        position: absolute; top: 0; left: -130%;
        width: 55%; height: 100%;
        background: linear-gradient(120deg, transparent, var(--shine), transparent);
        transform: skew(-18deg);
        transition: left .6s;
        pointer-events: none;
    }
    a.card:hover::after, a.card:focus-visible::after { left: 135%; }
    a.card:hover, a.card:focus-visible {
        transform: translateY(-3px);
        border-color: var(--card-hover-border);
        box-shadow: var(--card-hover-shadow);
        outline: none;
    }
    .card-icon {
        display: grid; place-items: center;
        width: 2.8rem; height: 2.8rem; border-radius: .8rem;
        background: linear-gradient(135deg, rgba(109, 93, 252, .25), rgba(36, 214, 255, .14));
        border: 1px solid var(--border-strong);
        color: var(--card-icon-color); font-size: 1.1rem;
        margin-bottom: 1rem;
        transition: transform .18s ease, background .18s ease, border-color .18s ease, color .18s ease;
    }
    /* En hover el icono toma el gradiente del tema: así el efecto se nota
     * igual en modo oscuro y en modo claro. */
    a.card:hover .card-icon, a.card:focus-visible .card-icon {
        transform: translateY(-2px) scale(1.06);
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        border-color: transparent;
        color: #fff;
    }
    .card h3 { margin: 0; font-size: 1.08rem; letter-spacing: -.01em; transition: color .18s ease; }
    a.card:hover h3, a.card:focus-visible h3 { color: var(--accent); }
    .card p { margin: .55rem 0 0; color: var(--muted); font-size: .9rem; flex: 1; }
    .card-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: .75rem; margin-top: 1.15rem;
        color: var(--muted); font-size: .76rem;
    }
    .card-foot .arrow { color: var(--cyan); transition: transform .16s; }
    a.card:hover .arrow { transform: translateX(.25rem); }
    .card.is-featured {
        background:
            linear-gradient(180deg, var(--destacada-top), var(--destacada-bottom));
        border-color: rgba(109, 93, 252, .45);
    }
    .card.is-featured .card-icon {
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        color: #fff;
    }
    .tag {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .2rem .55rem; border-radius: 999px;
        background: var(--tile); border: 1px solid var(--border);
        color: var(--muted); font-size: .68rem; font-weight: 700;
    }

    /* ===== Servicios ===== */
    .features { display: grid; gap: 1px; margin-top: 2rem; background: var(--border); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
    .feature { padding: 1.4rem 1.35rem; background: var(--panel); }
    .feature i { color: var(--cyan); font-size: 1.05rem; }
    .feature h3 { margin: .65rem 0 0; font-size: .98rem; }
    .feature p { margin: .4rem 0 0; color: var(--muted); font-size: .85rem; }

    /* ===== CTA ===== */
    .cta {
        position: relative; overflow: hidden;
        margin-top: 1rem; padding: clamp(2rem, 5vw, 3.25rem);
        border-radius: 1.25rem;
        background:
            radial-gradient(30rem 18rem at 10% 0%, rgba(36, 214, 255, .16), transparent 60%),
            radial-gradient(30rem 18rem at 90% 100%, rgba(109, 93, 252, .22), transparent 60%),
            var(--surface);
        border: 1px solid rgba(109, 93, 252, .32);
        box-shadow: var(--shadow);
    }
    .cta h2 { max-width: 24ch; }
    .cta p { margin: .8rem 0 0; max-width: 60ch; color: var(--muted); }
    /* El CTA cierra la página: sin el padding completo de .section para no
     * dejar un hueco enorme entre los servicios y el bloque final. */
    .cta-section { padding-block: 0 clamp(3.5rem, 6vw, 5rem); }

    /* ===== Pie (estructura de la portada de facturación) =====
     * Cuatro columnas: marca, enlaces, contacto con botones y redes + legal. */
    .site-footer {
        margin-top: clamp(3rem, 6vw, 5rem);
        border-top: 1px solid rgba(109, 93, 252, .38);
        background: var(--footer-bg);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, .35);
        /* Interlineado compacto, como se pidió para todo el pie. */
        line-height: 1;
    }
    .footer-grid {
        display: grid;
        gap: 2rem;
        grid-template-columns: 1fr;
        padding-block: clamp(2.5rem, 5vw, 3.5rem);
    }
    .footer-col { color: var(--muted); font-size: .86rem; min-width: 0; }
    .footer-col h4 {
        margin: 0 0 .9rem;
        color: var(--txt);
        font-size: .76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .12em;
    }
    .footer-col h4 i { color: var(--cyan); margin-right: .45rem; }
    .footer-col p { margin: 0 0 .9rem; line-height: 1; }
    .footer-col a { color: var(--muted); text-decoration: none; transition: color .15s; }
    .footer-col a:hover, .footer-col a:focus-visible { color: var(--cyan); }

    .footer-brand { display: flex; flex-direction: column; align-items: flex-start; gap: .55rem; }
    .footer-logo {
        width: 3.4rem; height: 3.4rem; object-fit: contain;
        padding: .22rem; margin-bottom: .35rem;
        border-radius: .8rem;
        background: var(--tile);
        border: 1px solid var(--border);
    }
    .footer-brand strong { color: var(--txt); font-size: 1.02rem; letter-spacing: -.01em; }
    .footer-brand small { color: var(--subtle); font-size: .72rem; }
    .footer-slogan { color: var(--mint); font-weight: 700; font-size: .8rem; margin: 0; }

    .footer-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; }
    .footer-list a { display: inline-flex; align-items: center; gap: .5rem; }
    .footer-list i { width: 1rem; text-align: center; color: var(--subtle); font-size: .8rem; transition: color .15s; }
    .footer-list a:hover i { color: var(--cyan); }

    .footer-contact p { margin-bottom: 1rem; }
    .footer-contact a { display: inline-flex; align-items: center; gap: .5rem; }
    .footer-contact i { color: var(--subtle); font-size: .8rem; width: 1rem; text-align: center; }
    .footer-contact small { display: inline-flex; align-items: flex-start; gap: .5rem; color: var(--subtle); font-size: .76rem; line-height: 1; }
    .footer-buttons { display: flex; flex-wrap: wrap; gap: .55rem; }
    /* Los botones del pie no deben heredar el color de enlace de .footer-col a. */
    .footer-col .btn { color: var(--txt); text-decoration: none; }
    .footer-col .btn i { color: inherit; opacity: .9; }
    .footer-buttons .btn-primary,
    .footer-buttons .btn-primary:hover,
    .footer-buttons .btn-primary:focus-visible { color: #fff; }

    .social-links { display: flex; flex-wrap: wrap; gap: .55rem; }
    .social-link {
        display: inline-grid; place-items: center;
        width: 2.4rem; height: 2.4rem;
        border-radius: .7rem;
        background: var(--tile);
        border: 1px solid var(--border);
        color: var(--muted); font-size: .92rem;
        transition: transform .15s, color .15s, border-color .15s, background .15s;
    }
    .social-link:hover, .social-link:focus-visible {
        transform: translateY(-2px);
        color: #fff;
        border-color: rgba(109, 93, 252, .6);
        background: rgba(109, 93, 252, .22);
        outline: none;
    }
    .footer-legal-title { margin-top: 1.4rem !important; }
    .footer-legal-links { display: flex; flex-wrap: wrap; gap: .4rem .95rem; font-size: .78rem; }
    .footer-legal-links a { display: inline-flex; align-items: center; gap: .35rem; }

    .footer-bar {
        border-top: 1px solid var(--border);
        padding-block: 1rem;
        color: var(--subtle);
        font-size: .76rem;
    }
    .footer-bar .wrap { display: flex; flex-direction: column; gap: .3rem; }
    .footer-bar i { color: var(--cyan); }

    /* ===== Interruptor de tema (sol / luna), igual que el panel phpinfo ===== */
    .theme-switch {
        display: inline-flex; align-items: center;
        padding: 0; background: transparent; border: 0;
        line-height: 0; cursor: pointer; flex: 0 0 auto;
    }
    .theme-switch-track {
        position: relative;
        display: inline-flex; align-items: center; justify-content: space-between;
        width: 64px; height: 32px; padding: 0 10px;
        border-radius: 999px;
        background-color: var(--switch-bg);
        border: 1px solid var(--switch-border);
        transition: background-color .25s ease, border-color .25s ease;
    }
    .theme-switch-icon {
        position: relative; z-index: 1;
        font-size: .8rem; color: var(--muted); opacity: .55;
        transition: color .25s ease, opacity .25s ease;
    }
    .theme-switch-thumb {
        position: absolute; top: 2px; left: 2px; z-index: 0;
        width: 26px; height: 26px; border-radius: 50%;
        background-color: var(--switch-thumb);
        border: 1px solid var(--switch-thumb-border);
        box-shadow: 0 2px 6px rgba(16, 24, 40, .35);
        transition: transform .28s cubic-bezier(.4, .1, .3, 1), background-color .25s ease;
    }
    [data-bs-theme="light"] .theme-switch-thumb { transform: translateX(0); }
    [data-bs-theme="dark"]  .theme-switch-thumb { transform: translateX(32px); }
    [data-bs-theme="light"] .theme-switch-sun { color: var(--switch-sun); opacity: 1; }
    [data-bs-theme="dark"]  .theme-switch-moon { color: var(--switch-moon); opacity: 1; }
    [data-bs-theme="light"] .theme-switch-moon,
    [data-bs-theme="dark"]  .theme-switch-sun { opacity: .45; }
    .theme-switch:hover .theme-switch-track,
    .theme-switch:focus-visible .theme-switch-track {
        border-color: var(--switch-moon);
        background-color: var(--accent-soft);
    }
    .theme-switch:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 999px; }

    /* ===== Botones flotantes: ir hasta arriba / ir hasta abajo ===== */
    .scroll-fabs {
        position: fixed;
        right: 1.15rem;
        bottom: 1.15rem;
        z-index: 60;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .scroll-fab {
        display: flex; align-items: center; justify-content: center;
        width: 2.9rem; height: 2.9rem;
        padding: 0;
        border-radius: 50%;
        background-color: var(--surface-strong);
        color: var(--accent);
        border: 1px solid var(--border);
        box-shadow: 0 8px 20px -10px rgba(16, 24, 40, .55);
        cursor: pointer;
        opacity: 0; visibility: hidden;
        transform: translateY(8px) scale(.92);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        transition: opacity .2s ease, transform .2s ease, visibility .2s ease,
                    background-color .2s ease, color .2s ease, border-color .2s ease;
    }
    .scroll-fab.is-visible { opacity: 1; visibility: visible; transform: none; }
    .scroll-fab:hover { color: #fff; background-color: var(--accent); border-color: var(--accent); transform: translateY(-2px); }
    .scroll-fab:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

    /* Agrupación del interruptor y el botón de acceso en el topbar */
    .nav-acciones { display: flex; align-items: center; gap: .65rem; }

    /* ===== Responsive ===== */
    @media (min-width: 640px) {
        .metrics { grid-template-columns: repeat(4, 1fr); }
        .grid { grid-template-columns: repeat(2, 1fr); }
        .features { grid-template-columns: repeat(2, 1fr); }
    }
    @media (min-width: 720px) {
        .footer-grid { grid-template-columns: 1.5fr 1fr 1.25fr 1fr; }
        .footer-bar .wrap { flex-direction: row; align-items: center; justify-content: space-between; }
    }
    @media (min-width: 1024px) {
        .nav-links { display: flex; }
        .grid { grid-template-columns: repeat(3, 1fr); }
        .features { grid-template-columns: repeat(3, 1fr); }
        .footer-grid { grid-template-columns: 1.6fr 1fr 1.3fr 1.1fr; }
    }
    @media (max-width: 480px) {
        /* En pantallas muy estrechas el nombre completo tiene que caber junto
         * al interruptor de tema y al botón: se reducen logo y tipografía. */
        .brand-logo { width: 2.35rem; height: 2.35rem; }
        .brand-name { font-size: .68rem; }
        .brand-name small { font-size: .49rem; letter-spacing: .02em; }
        .btn-sm { padding: .5rem .6rem; font-size: .76rem; }
        .btn-texto { display: none; }
        .nav { gap: .5rem; }
        .nav-acciones { gap: .45rem; }
        .theme-switch-track { width: 50px; height: 26px; padding: 0 7px; }
        .theme-switch-thumb { width: 20px; height: 20px; }
        [data-bs-theme="dark"] .theme-switch-thumb { transform: translateX(24px); }
        .theme-switch-icon { font-size: .68rem; }
        .scroll-fabs { right: .8rem; bottom: .8rem; }
        .scroll-fab { width: 2.55rem; height: 2.55rem; font-size: .9rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        * { transition: none !important; }
        .card::after { display: none; }
    }
</style>
</head>
<body>
<a class="skip" href="#contenido"><i class="fa-solid fa-arrow-down"></i> Saltar al contenido</a>

<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="/" title="Entorno de Aplicaciones Web de UnicornioSoftware® Cuba. Kaky®">
            <img class="brand-logo" src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <span class="brand-name">Entorno de Aplicaciones Web
                <small>de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></small>
            </span>
        </a>
        <nav class="nav-links" aria-label="Secciones">
            <a href="#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
            <a href="#servicios"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Servicios</a>
            <a href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Soporte</a>
            <a href="<?= $base ?>/explorer.php"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Autor</a>
        </nav>
        <div class="nav-acciones">
            <button class="theme-switch" id="themeToggle" type="button" role="switch"
                    aria-checked="true" aria-label="Cambiar entre tema claro y oscuro"
                    title="Cambiar a tema claro">
                <span class="theme-switch-track">
                    <i class="fa-solid fa-sun theme-switch-icon theme-switch-sun" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon theme-switch-icon theme-switch-moon" aria-hidden="true"></i>
                    <span class="theme-switch-thumb"></span>
                </span>
            </button>
            <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php" title="Entrar al sistema">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> <span class="btn-texto">Entrar al sistema</span>
            </a>
        </div>
    </div>
</header>

<main id="contenido">
    <!-- ===== Hero ===== -->
    <section class="wrap hero">
        <span class="badge"><span class="dot"></span> Plataforma local · v10.0.1</span>
        <h1>El ecosistema de gestión <span class="text-gradient">que impulsa tu operación</span></h1>
        <p class="lead">
            Facturación, nóminas, costos e inventarios en una plataforma que corre en tu propia red.
            Elige un módulo del ecosistema y empieza a trabajar: los datos no salen de tu equipo.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="#ecosistema">
                <i class="fa-solid fa-layer-group"></i> Explorar el ecosistema
            </a>
            <a class="btn btn-glass" href="<?= $base ?>/phpinfo/">
                <i class="fa-solid fa-server"></i> Estado del servidor
            </a>
        </div>
        <p class="hero-note">
            <i class="fa-solid fa-shield-halved"></i>
            Instalación local · sin dependencia de la nube · respaldo periódico
        </p>

        <div class="metrics" role="list">
            <div class="metric" role="listitem"><i class="fa-solid fa-cubes" aria-hidden="true"></i><b>4</b><span>Módulos de gestión</span></div>
            <div class="metric" role="listitem"><i class="fa-brands fa-php" aria-hidden="true"></i><b>PHP 8.1</b><span>Servidor local</span></div>
            <div class="metric" role="listitem"><i class="fa-solid fa-database" aria-hidden="true"></i><b>MySQL 5.7</b><span>Base de datos</span></div>
            <div class="metric" role="listitem"><i class="fa-solid fa-headset" aria-hidden="true"></i><b>24/7</b><span>Soporte técnico</span></div>
        </div>
    </section>

    <!-- ===== Ecosistema ===== -->
    <section class="wrap section" id="ecosistema">
        <div class="section-head">
            <span class="eyebrow">Opciones y servicios</span>
            <h2>Elige por dónde entrar</h2>
            <p>
                Cada acceso abre una parte del sistema. Si es tu primera vez, empieza por el
                <strong>Ecosistema Financiero</strong>: desde ahí llegas a todos los módulos.
            </p>
        </div>

        <div class="grid">
            <?php foreach ($ecosistema as $item): ?>
                <a class="card<?= !empty($item['destacada']) ? ' is-featured' : '' ?>" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="card-icon"><i class="<?= htmlspecialchars($item['icono'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                    <h3><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="card-foot">
                        <span class="tag"><?= htmlspecialchars($item['etiqueta'], ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="fa-solid fa-arrow-right arrow" aria-hidden="true"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Servicios ===== -->
    <section class="wrap section" id="servicios">
        <div class="section-head">
            <span class="eyebrow">Lo que incluye</span>
            <h2>Servicios de la plataforma</h2>
            <p>Una base técnica pensada para la operación diaria y para no depender de servicios externos.</p>
        </div>

        <div class="features">
            <?php foreach ($servicios as $s): ?>
                <div class="feature">
                    <i class="<?= htmlspecialchars($s['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <h3><?= htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($s['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== CTA ===== -->
    <section class="wrap cta-section">
        <div class="cta">
            <span class="eyebrow">Empieza ahora</span>
            <h2>Todo el sistema, a un clic desde aquí</h2>
            <p>
                La portada del sistema reúne los accesos a cada módulo, el estado de la licencia
                y la información de soporte de tu instalación.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= $base ?>/index.php">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Ir al Ecosistema Financiero
                </a>
                <a class="btn btn-glass" href="<?= $base ?>/soporte.php">
                    <i class="fa-solid fa-headset"></i> Hablar con soporte
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="wrap footer-grid">

        <!-- Columna 1: Marca -->
        <div class="footer-col footer-brand">
            <img class="footer-logo" src="/images/logo.png" alt="UnicornioSoftware Cuba">
            <strong>Entorno de Aplicaciones Web</strong>
            <small>de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></small>
            <p>
                Facturación, nóminas y gestión en una plataforma local.<br>
                &copy; <?= $anio ?> Todos los derechos reservados.
            </p>
            <p class="footer-slogan">«Donde tu visión toma forma»</p>
        </div>

        <!-- Columna 2: Enlaces -->
        <nav class="footer-col" aria-label="Enlaces del pie">
            <h4><i class="fa-solid fa-compass" aria-hidden="true"></i> Explorar</h4>
            <ul class="footer-list">
                <li><a href="<?= $base ?>/index.php"><i class="fa-solid fa-wallet" aria-hidden="true"></i> Ecosistema Financiero</a></li>
                <li><a href="<?= $base ?>/nominas/"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a></li>
                <li><a href="<?= $base ?>/facturacion/index.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a></li>
                <li><a href="<?= $base ?>/phpinfo/"><i class="fa-solid fa-server" aria-hidden="true"></i> Servidor Info PHP</a></li>
                <li><a href="<?= $base ?>/InstalarBD/InstalarBD.php"><i class="fa-solid fa-database" aria-hidden="true"></i> Instalador de BD</a></li>
            </ul>
        </nav>

        <!-- Columna 3: Contacto -->
        <div class="footer-col footer-contact">
            <h4><i class="fa-solid fa-headset" aria-hidden="true"></i> Contacto</h4>
            <p>
                <a href="mailto:kakycu@gmail.com"><i class="fa-solid fa-envelope" aria-hidden="true"></i> kakycu@gmail.com</a><br>
                <a href="tel:+53552712861"><i class="fa-solid fa-phone" aria-hidden="true"></i> +53 5 5271 2861</a><br>
                <small><i class="fa-solid fa-clock" aria-hidden="true"></i> Lun - Vie, 8:00 AM - 5:00 PM<br>Sábados: 9:00 AM - 1:00 PM</small>
            </p>
            <div class="footer-buttons">
                <button type="button" class="btn btn-glass btn-sm" id="irArriba">
                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Ir al inicio
                </button>
                <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Iniciar sesión
                </a>
            </div>
        </div>

        <!-- Columna 4: Redes sociales y legal -->
        <div class="footer-col">
            <h4><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Síguenos</h4>
            <div class="social-links">
                <a class="social-link" href="https://www.facebook.com/unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.x.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)">
                    <!-- La marca X no viene en el build de Font Awesome del proyecto: se dibuja con su SVG oficial. -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>
                </a>
                <a class="social-link" href="https://www.linkedin.com/unicorniocuba" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.instagram.com/unicorniosoftcuba" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                <a class="social-link" href="https://www.youtube.com/@unicorniosoftwarecuba" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                <a class="social-link" href="https://api.whatsapp.com/send?phone=5352712861" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
            </div>
            <h4 class="footer-legal-title"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Legal</h4>
            <p class="footer-legal-links">
                <a href="<?= $base ?>/terminos.php"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                <a href="<?= $base ?>/privacidad.php"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                <a href="<?= $base ?>/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
            </p>
        </div>
    </div>

    <div class="footer-bar">
        <div class="wrap">
            <span>&copy; <?= $anio ?> Entorno de Aplicaciones Web de UnicornioSoftware<sup>&reg;</sup> Cuba. Kaky<sup>&reg;</sup></span>
            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Plataforma local · v10.0.1</span>
        </div>
    </div>
</footer>

<!-- Botones flotantes: ir hasta arriba / ir hasta abajo -->
<div class="scroll-fabs">
    <button type="button" class="scroll-fab" id="btnScrollTop"
            title="Ir hasta arriba" aria-label="Ir hasta arriba de la página">
        <i class="fa-solid fa-angles-up"></i>
    </button>
    <button type="button" class="scroll-fab" id="btnScrollBottom"
            title="Ir hasta abajo" aria-label="Ir hasta abajo de la página">
        <i class="fa-solid fa-angles-down"></i>
    </button>
</div>

<script>
    // Cambio de tema claro / oscuro (mismo mecanismo que el panel phpinfo).
    (function () {
        var boton = document.getElementById('themeToggle');
        var raiz = document.documentElement;

        function sincronizar(tema) {
            var esDark = tema !== 'light';
            if (boton) {
                boton.setAttribute('aria-checked', esDark ? 'true' : 'false');
                boton.title = esDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
            }
        }

        sincronizar(raiz.getAttribute('data-bs-theme'));
        if (boton) {
            boton.addEventListener('click', function () {
                var actual = raiz.getAttribute('data-bs-theme') === 'light' ? 'light' : 'dark';
                var nuevo = actual === 'dark' ? 'light' : 'dark';
                raiz.setAttribute('data-bs-theme', nuevo);
                try { localStorage.setItem('theme', nuevo); } catch (e) {}
                sincronizar(nuevo);
            });
        }
    })();

    // Botón "Ir al inicio" del pie y botones flotantes arriba/abajo.
    (function () {
        var botonPie = document.getElementById('irArriba');
        var arriba = document.getElementById('btnScrollTop');
        var abajo = document.getElementById('btnScrollBottom');

        function irArriba() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        function irAbajo() {
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            window.scrollTo({ top: max, behavior: 'smooth' });
        }

        if (botonPie) { botonPie.addEventListener('click', irArriba); }
        if (arriba) { arriba.addEventListener('click', irArriba); }
        if (abajo) { abajo.addEventListener('click', irAbajo); }

        // Cada botón aparece solo cuando tiene sentido.
        function actualizar() {
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            if (arriba) { arriba.classList.toggle('is-visible', y > 140); }
            if (abajo) { abajo.classList.toggle('is-visible', y < max - 140); }
        }
        window.addEventListener('scroll', actualizar, { passive: true });
        window.addEventListener('resize', actualizar);
        actualizar();
    })();
</script>
</body>
</html>
