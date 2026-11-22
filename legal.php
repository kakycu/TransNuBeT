<?php $base = '/servicios'; ?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad y Términos de Servicio — UnicornioSoftware</title>
    <meta name="description" content="Política de Privacidad y Términos de Servicio de UnicornioSoftware. Conoce cómo tratamos tus datos y las condiciones de uso de nuestros sistemas.">
    <meta name="robots" content="index, follow">

    <meta property="og:type" content="website">
    <meta property="og:url" content="/legal.php">
    <meta property="og:title" content="Legal — UnicornioSoftware">
    <meta property="og:description" content="Política de Privacidad y Términos de Servicio de UnicornioSoftware.">
    <meta property="og:site_name" content="UnicornioSoftware">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Legal — UnicornioSoftware">
    <meta name="twitter:description" content="Política de Privacidad y Términos de Servicio de UnicornioSoftware.">

    <link rel="icon" type="image/png" href="/desamedida/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/desamedida/assets/images/favicon.png">
    <link rel="preload" as="font" type="font/woff2" href="/desamedida/fonts/bebas-neue/bebas-neue-v16-latin-regular.woff2" crossorigin>
    <link rel="stylesheet" href="/desamedida/assets/css/styles.css?v=20261117c">

    <link rel="preload" as="style" href="/desamedida/fa/fontawesome.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style" href="/desamedida/fa/solid.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style" href="/desamedida/fa/brands.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="/desamedida/fa/fontawesome.min.css">
        <link rel="stylesheet" href="/desamedida/fa/solid.min.css">
        <link rel="stylesheet" href="/desamedida/fa/brands.min.css">
    </noscript>

    <link rel="canonical" href="/legal.php">
    <style>
    html { scroll-padding-top: 5.75rem; }
    :root {
        --abismo:#0a0f1a; --panel:#0b0e14; --tarjeta:#090e18; --acero:#1e293b;
        --cian:#00d4ff; --fuego:#ff6b00; --fuego-h:#ff8a33;
        --txt:#ffffff; --gris:#d1d5db; --gris-2:#9ca3af; --gris-3:#6b7280;
        --nav-h:5rem; --wrap:80rem;
    }
    body { background:var(--abismo); color:var(--txt); font-family:"Inter","Segoe UI",Roboto,Arial,sans-serif; margin:0; }
    .wrap { width:100%; max-width:var(--wrap); margin-inline:auto; padding-inline:clamp(1.25rem,4vw,3rem); box-sizing:border-box; }
    .bebas { font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; line-height:.95; letter-spacing:.03em; }
    .nav { position:fixed; inset:0 0 auto 0; z-index:50; height:var(--nav-h); background:rgba(10,15,26,.9); -webkit-backdrop-filter:blur(18px) saturate(140%); backdrop-filter:blur(18px) saturate(140%); border-bottom:1px solid rgba(30,41,59,.7); }
    .nav-in { height:100%; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .marca { display:inline-flex; align-items:center; gap:.65rem; flex:0 0 auto; text-decoration:none; color:inherit; }
    .marca img { width:2.5rem; height:2.5rem; object-fit:contain; filter:drop-shadow(0 0 10px rgba(0,212,255,.2)); }
    .marca-txt b { display:block; font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; font-size:1.5rem; line-height:.9; letter-spacing:.03em; }
    .marca-txt b i { font-style:normal; color:var(--cian); }
    .marca-txt b em { font-style:normal; color:var(--fuego); }
    .marca-txt small { display:block; margin-top:.2rem; font-size:.58rem; letter-spacing:.22em; text-transform:uppercase; color:var(--gris-3); font-weight:600; }
    .nav-links { display:none; align-items:center; gap:1.5rem; }
    .nav-links a { display:inline-flex; align-items:center; gap:.45rem; font-family:"Exo 2",sans-serif; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); text-decoration:none; transition:color .2s; }
    .nav-links a i { font-size:1em; color:var(--gris-3); transition:color .2s; }
    .nav-links a:hover i,.nav-links a:focus-visible i { color:var(--cian); }
    .nav-links .nav-icon-btn { width:2rem; height:2rem; justify-content:center; border:1px solid var(--acero); border-radius:.35rem; background:rgba(255,255,255,.03); padding:0; flex-shrink:0; }
    .nav-links .nav-icon-btn i { font-size:1rem; color:var(--gris-2); }
    .nav-links .nav-icon-btn:hover,.nav-links .nav-icon-btn:focus-visible { border-color:rgba(0,212,255,.45); background:rgba(0,212,255,.08); color:var(--txt); }
    .nav-links .nav-icon-btn:hover i,.nav-links .nav-icon-btn:focus-visible i { color:var(--cian); }
    .nav-links a:hover,.nav-links a:focus-visible { color:var(--txt); }
    .menu-btn { display:inline-grid; place-items:center; width:2.75rem; height:2.75rem; border:1px solid var(--acero); border-radius:2px; background:transparent; color:var(--gris-2); font-size:1.1rem; cursor:pointer; transition:color .2s,border-color .2s; }
    .menu-btn:hover,.menu-btn:focus-visible { color:var(--txt); border-color:rgba(0,212,255,.4); }
    .menu-movil { display:none; padding:.25rem 1.25rem 1.25rem; background:rgba(10,15,26,.97); border-bottom:1px solid rgba(30,41,59,.7); }
    .menu-movil.is-open { display:block; }
    .menu-movil a { display:flex; align-items:center; gap:.6rem; padding:.7rem 0; font-family:"Exo 2",sans-serif; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); border-bottom:1px solid rgba(30,41,59,.45); text-decoration:none; transition:color .2s; }
    .menu-movil a i { width:1.25rem; text-align:center; font-size:1.05em; color:var(--gris-3); transition:color .2s; }
    .menu-movil a:hover i,.menu-movil a:focus-visible i { color:var(--cian); }
    .menu-movil a:last-child { border-bottom:0; }
    .menu-movil a:hover,.menu-movil a:focus-visible { color:var(--txt); }
    @media (min-width:768px){ .nav-links{display:flex;} .menu-btn,.menu-movil{display:none !important;} }

    .legal-hero { padding:calc(var(--nav-h) + 4rem) 0 4rem; border-bottom:1px solid var(--acero); background:#05080f; position:relative; overflow:hidden; }
    .legal-hero::before { content:""; position:absolute; inset:0; background:radial-gradient(ellipse at 50% 0%,rgba(255,107,0,.08),transparent 55%),radial-gradient(ellipse at 80% 100%,rgba(0,212,255,.06),transparent 45%); pointer-events:none; }
    .legal-hero-inner { position:relative; z-index:1; max-width:56rem; margin:0 auto; padding:0 1.5rem; text-align:center; }
    .eyebrow { display:inline-block; font-family:"Exo 2",sans-serif; font-size:.72rem; font-weight:800; letter-spacing:.22em; text-transform:uppercase; color:var(--fuego); margin-bottom:.75rem; }
    .legal-hero h1 { font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; font-size:clamp(2.8rem,7vw,4.5rem); line-height:.95; letter-spacing:.03em; color:#fff; margin:0; text-transform:uppercase; }
    .legal-hero p { color:var(--gris-2); margin:1rem auto 0; max-width:36rem; line-height:1.75; font-size:1rem; }
    .legal-hero .updated { color:var(--gris-3); font-size:.75rem; margin-top:1rem; }
    .print-brand { display:none; }
    .hero-actions { display:flex; flex-wrap:wrap; justify-content:center; gap:1rem; margin-top:2rem; }
    .hero-btn { display:inline-flex; align-items:center; gap:.5rem; font-family:"Exo 2",sans-serif; font-size:.72rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; padding:.85rem 1.5rem; border:1px solid var(--acero); border-radius:.25rem; color:var(--gris); text-decoration:none; transition:all .25s ease; background:transparent; }
    .hero-btn:hover,.hero-btn:focus-visible { color:#fff; transform:translateY(-2px); }
    .hero-btn.is-fuego:hover,.hero-btn.is-fuego:focus-visible { border-color:var(--fuego); box-shadow:0 10px 24px -12px rgba(255,107,0,.45); }
    .hero-btn.is-cian:hover,.hero-btn.is-cian:focus-visible { border-color:var(--cian); box-shadow:0 10px 24px -12px rgba(0,212,255,.35); }
    .hero-btn.is-print:hover,.hero-btn.is-print:focus-visible { border-color:var(--gris-2); box-shadow:0 10px 24px -12px rgba(255,255,255,.18); }

    .legal-main { max-width:56rem; margin:0 auto; padding:5rem 1.5rem; }
    .legal-section { scroll-margin-top:7rem; }
    .legal-divider { border:0; border-top:1px solid var(--acero); margin:4rem 0; }
    .sec-head { display:flex; align-items:center; gap:1rem; margin-bottom:2.5rem; padding-bottom:1rem; border-bottom:1px solid var(--acero); }
    .sec-icon { width:2.5rem; height:2.5rem; border-radius:.4rem; display:grid; place-items:center; flex-shrink:0; font-size:1rem; }
    .sec-icon.is-fuego { background:rgba(255,107,0,.2); border:1px solid rgba(255,107,0,.4); color:var(--fuego); }
    .sec-icon.is-cian { background:rgba(0,212,255,.1); border:1px solid rgba(0,212,255,.3); color:var(--cian); }
    .sec-head h2 { font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; font-size:clamp(2rem,4.5vw,3rem); line-height:1; letter-spacing:.04em; color:#fff; margin:0; text-transform:uppercase; }

    .legal-content { display:grid; gap:2rem; color:var(--gris-2); line-height:1.75; }
    .legal-block h3 { font-family:"Exo 2",sans-serif; font-size:.82rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#fff; margin:0 0 .75rem; display:flex; align-items:center; gap:.5rem; }
    .legal-block h3 .dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .dot.fuego { background:var(--fuego); }
    .dot.cian { background:var(--cian); }
    .legal-block p { margin:0; font-size:.95rem; }
    .legal-block p + p { margin-top:.75rem; }
    .legal-block strong { color:#fff; }
    .legal-block a { color:var(--fuego); text-decoration:none; transition:color .2s; }
    .legal-block a:hover { color:#ff9a4d; text-decoration:underline; }
    .legal-list { list-style:none; padding:0; margin:.75rem 0 0 1rem; display:grid; gap:.6rem; }
    .legal-list li { display:flex; align-items:flex-start; gap:.5rem; font-size:.95rem; }
    .legal-list li i { font-size:.55rem; margin-top:.6rem; flex-shrink:0; }
    .legal-list li i.fuego { color:var(--fuego); }
    .legal-list li i.cian { color:var(--cian); }

    .reveal { opacity:0; transform:translateY(24px); transition:opacity .7s cubic-bezier(.22,.8,.24,1),transform .7s cubic-bezier(.22,.8,.24,1); }
    .reveal.active { opacity:1; transform:none; }

    .pie{padding:3.5rem 0 0;background:#060a12;border-top:1px solid rgba(30,41,59,.7);}
    .pie-grid{display:grid;gap:2.25rem;padding-bottom:2.5rem;grid-template-columns:minmax(0,1fr);}
    @media(min-width:640px){.pie-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(min-width:1024px){.pie-grid{grid-template-columns:1.3fr 1fr 1fr 1fr;gap:2.5rem;}}
    .pie-marca{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem;text-decoration:none;color:inherit;}
    .pie-marca img{width:2.4rem;height:2.4rem;object-fit:contain;}
    .pie-grid p,.pie-grid a{color:var(--gris-2);font-size:.86rem;line-height:1.7;text-decoration:none;}
    .pie-grid a:hover,.pie-grid a:focus-visible{color:var(--txt);}
    .pie h4{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--txt);margin:0 0 .85rem;}
    .pie-lista{list-style:none;padding:0;margin:0;display:grid;gap:.45rem;}
    .pie-lista a{display:inline-flex;align-items:center;gap:.5rem;}
    .pie-lista a i{color:var(--gris-3);font-size:.8rem;width:.9rem;text-align:center;transition:color .2s;}
    .pie-lista a:hover i{color:var(--cian);}
    .pie-social{display:flex;flex-wrap:nowrap;gap:.55rem;margin-top:1rem;}
    .pie-social a{display:inline-grid;place-items:center;width:2.25rem;height:2.25rem;border-radius:999px;border:1px solid rgba(255,255,255,.12);color:var(--gris-2);font-size:.9rem;transition:color .25s,border-color .25s,background .25s,transform .25s;}
    .pie-social a:hover{color:var(--cian);border-color:rgba(0,212,255,.45);background:rgba(0,212,255,.08);transform:translateY(-2px);}
    .pie-social svg{width:1em;height:1em;display:block;}
    .pie-legal-title{margin-top:1.4rem;}
    .pie-legal{display:grid;gap:.45rem;}
    .pie-legal a{display:inline-flex;align-items:center;gap:.5rem;}
    .pie-legal a i{color:var(--gris-3);font-size:.8rem;width:.9rem;text-align:center;transition:color .2s;}
    .pie-legal a:hover i{color:var(--cian);}
    .pie-barra{display:flex;flex-direction:column;gap:.5rem;padding:1.15rem 0 1.6rem;border-top:1px solid rgba(30,41,59,.55);color:var(--gris-3);font-size:.75rem;}
    @media(min-width:640px){.pie-barra{flex-direction:row;align-items:center;justify-content:space-between;}}

    .scroll-fabs{position:fixed;right:1.15rem;bottom:1.15rem;z-index:55;display:flex;flex-direction:column;gap:.55rem;}
    .scroll-fab{display:inline-grid;place-items:center;width:2.85rem;height:2.85rem;border-radius:999px;border:1px solid rgba(0,212,255,.35);color:var(--cian);background:rgba(10,15,26,.72);font-size:1rem;cursor:pointer;opacity:0;visibility:hidden;transform:translateY(8px) scale(.92);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);transition:opacity .2s,transform .2s,visibility .2s,background .2s,color .2s,border-color .2s;}
    .scroll-fab.is-visible{opacity:1;visibility:visible;transform:none;}
    .scroll-fab:hover{color:var(--abismo);background:var(--cian);border-color:var(--cian);transform:translateY(-2px);}
    .scroll-progress{position:fixed;inset:0 0 auto 0;height:3px;z-index:60;background:rgba(255,255,255,.04);pointer-events:none;}
    .scroll-progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--cian),var(--fuego));box-shadow:0 0 12px rgba(0,212,255,.55);}

    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto;}.reveal{opacity:1;transform:none;transition:none;}.hero-btn:hover,.scroll-fab:hover{transform:none;}}

    /* ===== IMPRESIÓN: salida tipo documento Word (carta, Calibri 11 pt) ===== */
    @media print {
        @page { size: letter; margin: 17.5mm 13.4mm 17.5mm 30mm; }

        html, body { background:#fff !important; color:#000 !important; }
        body { font-family:"Calibri","Segoe UI",Arial,sans-serif !important; font-size:11pt; line-height:1.08; margin:0; }

        .scroll-progress, .nav, .menu-movil, .scroll-fabs, .pie,
        .hero-actions, .eyebrow, .legal-divider, .sec-icon,
        .legal-block h3 .dot, .legal-list li i, #top { display:none !important; }

        .legal-hero { padding:0 0 14pt !important; border:0 !important; background:#fff !important; text-align:center; }
        .legal-hero::before { display:none !important; }
        .legal-hero-inner { max-width:none; padding:0; }
        .print-brand { display:block !important; font-size:11pt; line-height:1.08; margin:0 0 6pt; color:#000; }
        .legal-hero h1 {
            font-family:"Calibri Light","Calibri",Arial,sans-serif !important;
            font-size:18pt !important; font-weight:700 !important;
            color:#000 !important; letter-spacing:0 !important;
            line-height:1.1 !important; margin:0 0 6pt; text-transform:uppercase;
        }
        .legal-hero p { color:#000 !important; font-size:11pt; line-height:1.08; margin:0 0 4pt; max-width:none; }
        .legal-hero .updated { color:#000 !important; margin-top:2pt; }

        .legal-main { max-width:none; margin:0; padding:0; }
        .legal-section { scroll-margin-top:0; }
        .legal-section.reveal { opacity:1 !important; transform:none !important; }

        .sec-head { display:block; margin:8pt 0 8pt; padding:0; border:0; }
        .sec-head h2 {
            font-family:"Calibri",Arial,sans-serif !important;
            font-size:11pt !important; font-weight:700 !important;
            color:#000 !important; text-transform:uppercase;
            letter-spacing:0; line-height:1.08; margin:0;
            break-after:avoid-page; page-break-after:avoid;
        }
        #privacidad .sec-head h2 { text-decoration:underline; }

        .legal-content { display:block; color:#000 !important; line-height:1.08; }
        .legal-block { margin:0 0 8pt; }
        .legal-block h3 {
            display:block; font-family:"Calibri",Arial,sans-serif !important;
            font-size:11pt !important; font-weight:700 !important;
            color:#000 !important; text-transform:none;
            letter-spacing:0; margin:0 0 8pt;
            break-after:avoid-page; page-break-after:avoid;
        }
        .legal-block p {
            font-size:11pt; line-height:1.08; color:#000 !important;
            margin:0 0 8pt; text-align:justify;
            orphans:3; widows:3;
        }
        .legal-block p + p { margin-top:0; }
        .legal-block strong { color:#000 !important; font-weight:700; }
        .legal-block a { color:#000 !important; text-decoration:underline; }

        .legal-list {
            list-style:disc; display:block;
            margin:0 0 8pt 1.5em; padding-left:1.5em;
        }
        .legal-list li {
            display:list-item; font-size:11pt; line-height:1.08;
            color:#000 !important; margin:0 0 2pt; text-align:justify;
        }
        .legal-list li::marker { color:#000; }
        .legal-block a[href]::after { content:""; }
    }
    </style>
</head>

<body>
    <div id="top" aria-hidden="true"></div>
    <div class="scroll-progress" aria-hidden="true"><span id="scrollProgress"></span></div>

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
                <a href="/index.php" class="nav-icon-btn" aria-label="Ir a la página principal" title="Página principal"><i class="fa-solid fa-house" aria-hidden="true"></i></a>
                <a href="#top"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Inicio</a>
                <a href="#privacidad"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
                <a href="#terminos"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
                <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a>
            </div>
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Abrir menú" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
        <div class="menu-movil" id="menuMovil">
            <a href="/index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Página principal</a>
            <a href="#top"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Inicio</a>
            <a href="#privacidad"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacidad</a>
            <a href="#terminos"><i class="fa-solid fa-file-contract" aria-hidden="true"></i> Términos</a>
            <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a>
        </div>
    </nav>

    <header class="legal-hero">
        <div class="legal-hero-inner">
            <p class="print-brand">Documentos Legales - UnicornioSoftware&reg;</p>
            <span class="eyebrow">Documentos Legales</span>
            <h1>PRIVACIDAD &amp; TÉRMINOS</h1>
            <p>Todo lo que necesitas saber sobre cómo tratamos tus datos y las condiciones de uso de los sistemas de UnicornioSoftware. Sin letra pequeña.</p>
            <p class="updated">Última actualización: <strong>22 de noviembre de 2026</strong></p>
            <div class="hero-actions">
                <a href="#privacidad" class="hero-btn is-fuego">
                    <i class="fa-solid fa-shield-halved" style="color:var(--fuego)" aria-hidden="true"></i> Política de Privacidad
                </a>
                <a href="#terminos" class="hero-btn is-cian">
                    <i class="fa-solid fa-file-contract" style="color:var(--cian)" aria-hidden="true"></i> Términos de Servicio
                </a>
                <button type="button" class="hero-btn is-print" id="btnImprimir" title="Imprimir documento (Ctrl+P)">
                    <i class="fa-solid fa-print" style="color:var(--gris)" aria-hidden="true"></i> Imprimir documento
                </button>
            </div>
        </div>
    </header>

    <main class="legal-main">

        <section id="privacidad" class="legal-section reveal">
            <div class="sec-head">
                <div class="sec-icon is-fuego"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h2>POLÍTICA DE PRIVACIDAD</h2>
            </div>

            <div class="legal-content">
                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 1. Quién es el responsable</h3>
                    <p>El responsable del tratamiento de los datos es <strong>UnicornioSoftware</strong>, desarrollador del ecosistema de gestión empresarial y del sistema <strong>SiGesNom®</strong>. El software está diseñado para instalarse y operar en la infraestructura local del cliente, manteniendo el control de la información operativa en su propio entorno.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 2. Qué datos recopilamos</h3>
                    <p>Para la correcta prestación del servicio y la seguridad del sistema, podemos tratar la siguiente información:</p>
                    <ul class="legal-list">
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span><strong>Datos de usuarios y trabajadores:</strong> nombre completo, carné de identidad, teléfono, dirección, área de trabajo, salario, datos bancarios, historial de pagos y vacaciones, según los módulos utilizados.</span></li>
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span><strong>Datos de acceso y seguridad:</strong> usuario, dirección IP, fecha, hora y registros de actividad necesarios para autenticación, auditoría y trazabilidad.</span></li>
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span><strong>Datos técnicos de licencia:</strong> identificador de hardware/huella del equipo y datos de activación o validación de licencia, utilizados para verificar la autorización de uso en el equipo correspondiente.</span></li>
                    </ul>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 3. Uso de la información</h3>
                    <p>La información recopilada se utiliza exclusivamente para:</p>
                    <ul class="legal-list">
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span>Gestionar nóminas, pagos, deducciones legales, vacaciones y licencias.</span></li>
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span>Generar reportes internos y apoyar el cumplimiento de obligaciones fiscales o laborales aplicables.</span></li>
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span>Controlar el acceso autorizado, la seguridad del sistema y la trazabilidad de acciones.</span></li>
                        <li><i class="fa-solid fa-chevron-right fuego" aria-hidden="true"></i><span>Validar la autenticidad de la licencia y brindar soporte técnico cuando el usuario lo solicite.</span></li>
                    </ul>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 4. Confidencialidad y seguridad local</h3>
                    <p><strong>Tus datos comerciales son privados.</strong> La información operativa que procesas dentro del sistema (nóminas, trabajadores, pagos, reportes y datos internos de tu organización) reside en el servidor o dispositivo local donde se instala la solución. UnicornioSoftware no accede a la información operativa o financiera de tu negocio, salvo cuando el cliente autoriza expresamente un soporte técnico puntual y limitado a la incidencia.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 5. Protección de datos</h3>
                    <p>La información se almacena en la infraestructura local definida por el cliente, con acceso restringido mediante credenciales, roles y medidas de seguridad como cifrado de contraseñas. El cliente es responsable de proteger el acceso físico y lógico al equipo o servidor donde se aloja el sistema.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 6. Compartición de datos</h3>
                    <p><strong>UnicornioSoftware no vende, alquila ni comparte</strong> tu información personal o comercial con terceros con fines publicitarios o comerciales. Solo podrá existir comunicación de datos cuando sea requerida por autoridades competentes conforme a la legislación aplicable, o cuando el propio cliente la autorice expresamente para soporte o servicios solicitados.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 7. Derechos del usuario</h3>
                    <p>Usted puede solicitar la corrección, actualización, eliminación o limitación del tratamiento de sus datos personales, siempre que ello no entre en conflicto con obligaciones legales, laborales, fiscales o de auditoría. Para ejercer estos derechos, contacte al administrador del sistema o utilice nuestro <a href="<?= $base ?>/contacto.php">formulario de contacto</a>.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 8. Cookies y seguimiento</h3>
                    <p>El sistema utiliza cookies o mecanismos de sesión estrictamente necesarios para la autenticación y el funcionamiento seguro. No se emplean cookies publicitarias de terceros ni sistemas de analítica externos dentro del sistema.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot fuego"></span> 9. Consentimiento</h3>
                    <p>Al utilizar el Sistema, usted reconoce y acepta el tratamiento de los datos necesarios para su operación conforme a esta Política de Privacidad y a la normativa aplicable.</p>
                </div>
            </div>
        </section>

        <hr class="legal-divider">

        <section id="terminos" class="legal-section reveal">
            <div class="sec-head">
                <div class="sec-icon is-cian"><i class="fa-solid fa-file-contract" aria-hidden="true"></i></div>
                <h2>TÉRMINOS DE SERVICIO</h2>
            </div>

            <div class="legal-content">
                <div class="legal-block">
                    <h3><span class="dot cian"></span> 1. Aceptación de los términos</h3>
                    <p>Al acceder y utilizar los sistemas de UnicornioSoftware, incluyendo <strong>SiGesNom®</strong> y los módulos del ecosistema, usted acepta quedar vinculado por estos Términos y Condiciones, así como por las leyes y regulaciones aplicables. Si no está de acuerdo con alguno de estos términos, debe abstenerse de utilizar el sistema.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 2. Licencia de uso y propiedad intelectual</h3>
                    <p>Al adquirir o activar un producto de UnicornioSoftware, se otorga una <strong>licencia de uso</strong> sobre el software, no una cesión de propiedad. El código fuente, diseño, bases de datos, logotipos, documentación y demás elementos asociados son propiedad exclusiva de <strong>UnicornioSoftware®</strong> y están protegidos por las leyes de propiedad intelectual aplicables. Queda prohibida su reproducción, distribución, modificación o ingeniería inversa sin autorización expresa por escrito.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 3. Uso autorizado del sistema</h3>
                    <p>El sistema se proporciona para la gestión interna autorizada de la organización o negocio del cliente. El acceso está restringido a usuarios autorizados mediante credenciales personales e intransferibles. Cada usuario es responsable de mantener la confidencialidad de su contraseña y de todas las actividades realizadas bajo su cuenta.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 4. Periodo de prueba y evaluación</h3>
                    <p>Cuando se ofrezca un periodo de prueba, demostración o evaluación, este tendrá como único fin valorar el funcionamiento del software antes de su adquisición o activación definitiva. UnicornioSoftware se reserva el derecho de limitar, suspender o finalizar el acceso de prueba ante uso abusivo, intentos de evasión de restricciones técnicas o incumplimiento de estos términos.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 5. Activación, huella de equipo y abuso de licencia</h3>
                    <p>Las licencias pueden estar vinculadas a un equipo o entorno autorizado mediante mecanismos técnicos de activación, como la huella del equipo. El <strong>abuso de la licencia</strong>, incluyendo la distribución no autorizada de archivos o claves de activación, la manipulación de validaciones, la ingeniería inversa o el uso fuera de los términos acordados, puede dar lugar a la <strong>suspensión o revocación</strong> de la licencia sin perjuicio de las acciones legales correspondientes.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 6. Responsabilidad por datos y respaldos</h3>
                    <p>El cliente es responsable de mantener copias de seguridad actualizadas de su base de datos, archivos y configuraciones. UnicornioSoftware no se hace responsable por pérdidas de datos causadas por fallos de hardware, cortes eléctricos, desastres, errores de operación, acceso indebido por falta de resguardo del cliente o ausencia de respaldos adecuados.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 7. Limitación de responsabilidad</h3>
                    <p>El sistema se proporciona "tal cual", según sus funcionalidades disponibles al momento de la entrega o activación. En la medida permitida por la legislación aplicable, UnicornioSoftware no será responsable por daños indirectos, incidentales, consecuentes, pérdida de beneficios, interrupción del negocio o imposibilidad de uso derivados del empleo o la imposibilidad de empleo del software.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 8. Reembolsos</h3>
                    <p>Salvo acuerdo comercial escrito que indique lo contrario, una vez entregada, activada o puesta en uso una licencia definitiva, <strong>no se aceptan devoluciones ni reembolsos</strong>, especialmente cuando el cliente ha tenido la posibilidad de evaluar el sistema previamente mediante demostración, periodo de prueba o validación funcional.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 9. Modificaciones a estos términos</h3>
                    <p>UnicornioSoftware se reserva el derecho de actualizar estos Términos y Condiciones. Los cambios relevantes serán publicados en esta página y, cuando corresponda, comunicados por los canales habituales. El uso continuado del sistema tras la publicación o notificación de cambios implica la aceptación de los términos actualizados.</p>
                </div>

                <div class="legal-block">
                    <h3><span class="dot cian"></span> 10. Legislación aplicable y contacto legal</h3>
                    <p>Estos términos se rigen por las leyes de la República de Cuba. Cualquier disputa será atendida ante las instancias competentes conforme a la normativa aplicable. Para consultas legales, solicitudes o notificaciones formales, puede contactarnos a través de nuestro <a href="<?= $base ?>/contacto.php">formulario de contacto</a>.</p>
                </div>
            </div>
        </section>

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
                        <li><a href="<?= $base ?>/costos/login.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a></li>
                        <li><a href="<?= $base ?>/inventarios/login.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a></li>
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
                        <a href="<?= $base ?>/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
                    </p>
                </div>
            </div>
            <div class="pie-barra">
                <span>© <?= date('Y') ?> UnicornioSoftware® Cuba. Todos los derechos reservados.</span>
                <span>Licencia registrada · datos alojados en este equipo</span>
            </div>
        </div>
    </footer>

    <div class="scroll-fabs">
        <button type="button" class="scroll-fab" id="btnScrollTop" title="Ir hasta arriba" aria-label="Ir hasta arriba de la página">
            <i class="fa-solid fa-angles-up" aria-hidden="true"></i>
        </button>
        <button type="button" class="scroll-fab" id="btnScrollBottom" title="Ir hasta abajo" aria-label="Ir hasta abajo de la página">
            <i class="fa-solid fa-angles-down" aria-hidden="true"></i>
        </button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { entry.target.classList.add('active'); observer.unobserve(entry.target); }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal').forEach(function (el) { observer.observe(el); });
    });
    (function () {
        var btn = document.getElementById('menuBtn');
        var menu = document.getElementById('menuMovil');
        if (!btn || !menu) return;
        btn.addEventListener('click', function () {
            var abierto = menu.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            btn.innerHTML = abierto ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        });
        menu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                menu.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                btn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
            });
        });
    })();
    (function () {
        var btnPrint = document.getElementById('btnImprimir');
        if (!btnPrint) return;
        btnPrint.addEventListener('click', function () { window.print(); });
    })();
    (function () {
        var arriba = document.getElementById('btnScrollTop');
        var abajo = document.getElementById('btnScrollBottom');
        var progreso = document.getElementById('scrollProgress');
        if (!arriba && !abajo) return;
        if (arriba) arriba.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        if (abajo) abajo.addEventListener('click', function () {
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            window.scrollTo({ top: max, behavior: 'smooth' });
        });
        function actualizar() {
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            if (arriba) arriba.classList.toggle('is-visible', y > 140);
            if (abajo) abajo.classList.toggle('is-visible', y < max - 140);
            if (progreso) progreso.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%';
        }
        window.addEventListener('scroll', actualizar, { passive: true });
        window.addEventListener('resize', actualizar);
        actualizar();
    })();
    </script>
</body>
</html>
