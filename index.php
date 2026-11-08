<?php
/**
 * Landing público de PDL TransNuBeT.
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
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>PDL TransNuBeT · Ecosistema de gestión</title>
<meta name="description" content="Ecosistema de gestión financiera, nóminas y facturación. Plataforma local PDL TransNuBeT.">
<link rel="icon" type="image/x-icon" href="<?= $base ?>/images/favicons/LogoCorto.ico">
<link rel="stylesheet" href="<?= $base ?>/css/font-awesome6.4.0/css/all.min.css">
<style>
    /* ===== Tokens del tema (NeuroAI) ===== */
    :root {
        --bg:            #070a12;
        --bg-soft:       #0b1020;
        --surface:       rgba(18, 26, 45, .72);
        --surface-solid: #121a2d;
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
        --max:           72rem;
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
    /* Resplandor de fondo, como el .bg-gradient-glow del tema */
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        background:
            radial-gradient(60rem 40rem at 15% -5%, rgba(36, 214, 255, .16), transparent 60%),
            radial-gradient(55rem 38rem at 88% 0%, rgba(109, 93, 252, .22), transparent 60%),
            radial-gradient(50rem 30rem at 50% 110%, rgba(46, 229, 157, .10), transparent 60%);
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
        background: rgba(7, 10, 18, .78);
        border-bottom: 1px solid var(--border);
    }
    .nav {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; min-height: 4.25rem;
    }
    .brand { display: inline-flex; align-items: center; gap: .75rem; text-decoration: none; font-weight: 800; }
    .brand-mark {
        display: grid; place-items: center;
        width: 2.6rem; height: 2.6rem; border-radius: .8rem;
        background: linear-gradient(135deg, var(--accent) 0%, var(--cyan) 100%);
        box-shadow: 0 0 0 1px rgba(255,255,255,.12) inset;
        color: #fff; font-size: 1.15rem;
    }
    .brand-name { font-size: 1.02rem; letter-spacing: -.01em; }
    .brand-name small { display: block; font-size: .66rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--subtle); }
    .nav-links { display: none; align-items: center; gap: 1.6rem; }
    .nav-links a {
        text-decoration: none; color: var(--muted); font-size: .92rem; font-weight: 500;
        transition: color .15s;
    }
    .nav-links a:hover, .nav-links a:focus-visible { color: var(--txt); }

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
    .btn-primary:hover { box-shadow: 0 .7rem 2rem rgba(109, 93, 252, .5); }
    .btn-glass {
        color: var(--txt); background: rgba(18, 26, 45, .6);
        border-color: var(--border-strong);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    }
    .btn-glass:hover { border-color: rgba(255,255,255,.3); background: rgba(24, 33, 56, .75); }
    .btn-sm { padding: .55rem .9rem; font-size: .82rem; }

    /* ===== Hero ===== */
    .hero { padding-block: clamp(4rem, 9vw, 7.5rem) clamp(3rem, 6vw, 5rem); }
    .badge {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .38rem .8rem; border-radius: 999px;
        border: 1px solid rgba(36, 214, 255, .35);
        background: rgba(36, 214, 255, .08);
        color: #c9f4ff; font-size: .76rem; font-weight: 700;
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
    .metric { padding: 1.15rem 1.25rem; background: rgba(11, 16, 32, .82); }
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
        position: relative;
        display: flex; flex-direction: column;
        padding: 1.35rem;
        border-radius: var(--radius-lg);
        background: var(--surface);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        text-decoration: none; color: inherit;
        transition: transform .16s, border-color .16s, box-shadow .16s, background-color .16s;
    }
    a.card:hover, a.card:focus-visible {
        transform: translateY(-3px);
        border-color: rgba(255,255,255,.28);
        box-shadow: var(--shadow-glow);
        outline: none;
    }
    .card-icon {
        display: grid; place-items: center;
        width: 2.8rem; height: 2.8rem; border-radius: .8rem;
        background: linear-gradient(135deg, rgba(109, 93, 252, .25), rgba(36, 214, 255, .14));
        border: 1px solid var(--border-strong);
        color: #cfd6ff; font-size: 1.1rem;
        margin-bottom: 1rem;
    }
    .card h3 { margin: 0; font-size: 1.08rem; letter-spacing: -.01em; }
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
            linear-gradient(180deg, rgba(109, 93, 252, .16), rgba(18, 26, 45, .72));
        border-color: rgba(109, 93, 252, .45);
    }
    .card.is-featured .card-icon {
        background: linear-gradient(135deg, var(--accent), var(--cyan));
        color: #fff;
    }
    .tag {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .2rem .55rem; border-radius: 999px;
        background: rgba(255,255,255,.06); border: 1px solid var(--border);
        color: var(--muted); font-size: .68rem; font-weight: 700;
    }

    /* ===== Servicios ===== */
    .features { display: grid; gap: 1px; margin-top: 2rem; background: var(--border); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
    .feature { padding: 1.4rem 1.35rem; background: rgba(11, 16, 32, .82); }
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
            rgba(18, 26, 45, .72);
        border: 1px solid rgba(109, 93, 252, .32);
        box-shadow: var(--shadow);
    }
    .cta h2 { max-width: 24ch; }
    .cta p { margin: .8rem 0 0; max-width: 60ch; color: var(--muted); }
    /* El CTA cierra la página: sin el padding completo de .section para no
     * dejar un hueco enorme entre los servicios y el bloque final. */
    .cta-section { padding-block: 0 clamp(3.5rem, 6vw, 5rem); }

    /* ===== Pie ===== */
    .site-footer { margin-top: clamp(3rem, 6vw, 5rem); border-top: 1px solid var(--border); padding-block: 2rem; }
    .foot-grid { display: flex; flex-direction: column; gap: 1.25rem; }
    .foot-brand { display: flex; align-items: center; gap: .75rem; }
    .foot-brand small { display: block; color: var(--subtle); font-size: .76rem; }
    .foot-links { display: flex; flex-wrap: wrap; gap: .4rem 1.25rem; }
    .foot-links a { color: var(--muted); text-decoration: none; font-size: .84rem; }
    .foot-links a:hover { color: var(--txt); }
    .foot-legal { color: var(--subtle); font-size: .76rem; }
    .foot-legal a { color: var(--subtle); text-decoration: none; }
    .foot-legal a:hover { color: var(--txt); }

    /* ===== Responsive ===== */
    @media (min-width: 640px) {
        .metrics { grid-template-columns: repeat(4, 1fr); }
        .grid { grid-template-columns: repeat(2, 1fr); }
        .features { grid-template-columns: repeat(2, 1fr); }
        .foot-grid { flex-direction: row; align-items: center; justify-content: space-between; }
    }
    @media (min-width: 1024px) {
        .nav-links { display: flex; }
        .grid { grid-template-columns: repeat(3, 1fr); }
        .features { grid-template-columns: repeat(3, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        * { transition: none !important; }
    }
</style>
</head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>

<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="/">
            <span class="brand-mark"><i class="fa-solid fa-cubes"></i></span>
            <span class="brand-name">PDL TransNuBeT<sup>&reg;</sup>
                <small>Ecosistema de gestión</small>
            </span>
        </a>
        <nav class="nav-links" aria-label="Secciones">
            <a href="#ecosistema">Ecosistema</a>
            <a href="#servicios">Servicios</a>
            <a href="<?= $base ?>/soporte.php">Soporte</a>
        </nav>
        <a class="btn btn-primary btn-sm" href="<?= $base ?>/index.php">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar al sistema
        </a>
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
            <div class="metric" role="listitem"><b>4</b><span>Módulos de gestión</span></div>
            <div class="metric" role="listitem"><b>PHP 8.1</b><span>Servidor local</span></div>
            <div class="metric" role="listitem"><b>MySQL 5.7</b><span>Base de datos</span></div>
            <div class="metric" role="listitem"><b>24/7</b><span>Soporte técnico</span></div>
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
    <div class="wrap foot-grid">
        <div class="foot-brand">
            <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-cubes"></i></span>
            <div>
                <strong>PDL TransNuBeT<sup>&reg;</sup></strong>
                <small>Ecosistema de gestión · v10.0.1</small>
            </div>
        </div>
        <nav class="foot-links" aria-label="Enlaces del pie">
            <a href="<?= $base ?>/index.php">Ecosistema Financiero</a>
            <a href="<?= $base ?>/nominas/">Nóminas</a>
            <a href="<?= $base ?>/facturacion/index.php">Facturación</a>
            <a href="<?= $base ?>/phpinfo/">Servidor</a>
            <a href="<?= $base ?>/soporte.php">Soporte</a>
        </nav>
    </div>
    <div class="wrap">
        <p class="foot-legal">
            &copy; <?= $anio ?> USBWebserverUnicornio · Unicornio Software Cuba<sup>&reg;</sup> ·
            <a href="<?= $base ?>/terminos.php">Términos</a> ·
            <a href="<?= $base ?>/privacidad.php">Privacidad</a> ·
            <a href="<?= $base ?>/contacto.php">Contacto</a>
        </p>
    </div>
</footer>
</body>
</html>
