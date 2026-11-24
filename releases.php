<?php header('Content-Type: text/html; charset=UTF-8'); $base = '/servicios'; ?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novedades y versiones | UnicornioSoftware</title>
    <meta name="description" content="Consulta las novedades, mejoras y correcciones de cada versión de la aplicación y el sistema UnicornioSoftware.">
    <link rel="icon" type="image/png" href="/desamedida/assets/images/favicon.png">
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
    <style>
    html { scroll-padding-top: 5.75rem; }
    :root {
        --abismo:#0a0f1a; --panel:#0b0e14; --tarjeta:#090e18; --acero:#1e293b;
        --cian:#00d4ff; --fuego:#7949B5; --fuego-h:#9b6bd1;
        --txt:#ffffff; --gris:#d1d5db; --gris-2:#9ca3af; --gris-3:#6b7280;
        --nav-h:5rem; --wrap:80rem;
    }
    body{background:var(--abismo);color:var(--txt);margin:0;font-family:"Exo 2",system-ui,sans-serif;}
    a{color:inherit;text-decoration:none;}
    .wrap { width:100%; max-width:var(--wrap); margin-inline:auto; padding-inline:clamp(1.25rem,4vw,3rem); }
    .nav { position:fixed; inset:0 0 auto 0; z-index:50; height:var(--nav-h); background:rgba(10,15,26,.88); -webkit-backdrop-filter:blur(18px) saturate(140%); backdrop-filter:blur(18px) saturate(140%); border-bottom:1px solid rgba(30,41,59,.7); }
    .nav-in { height:100%; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .marca { display:inline-flex; align-items:center; gap:.65rem; flex:0 0 auto; text-decoration:none; }
    .marca img { width:2.5rem; height:2.5rem; object-fit:contain; filter:drop-shadow(0 0 10px rgba(0,212,255,.2)); }
    .marca-txt b { display:block; font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; font-size:1.5rem; line-height:.9; letter-spacing:.03em; }
    .marca-txt b i { font-style:normal; color:var(--cian); } .marca-txt b em { font-style:normal; color:var(--fuego); }
    .marca-txt small { margin-top:.2rem; font-size:.58rem; letter-spacing:.22em; text-transform:uppercase; color:var(--gris-3); font-weight:600; display:block; }
    .nav-links { display:none; align-items:center; gap:1.4rem; }
    .nav-links a { font-family:"Exo 2",sans-serif; font-size:.82rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); transition:color .2s; display:inline-flex; align-items:center; gap:.5rem; }
    .nav-links a i { color:var(--gris-3); font-size:1.1rem; transition:color .2s; }
    .nav-links a:hover,.nav-links a:focus-visible,.nav-links a.is-active { color:var(--txt); }
    .nav-links a:hover i,.nav-links a:focus-visible i,.nav-links a.is-active i { color:var(--cian); }
    .nav-acc { display:flex; align-items:center; gap:.6rem; }
    .nav-acc .btn { display:none; }
    .menu-btn { display:inline-grid; place-items:center; width:2.75rem; height:2.75rem; border:1px solid var(--acero); border-radius:2px; background:transparent; color:var(--gris-2); font-size:1.1rem; cursor:pointer; }
    .menu-movil { display:none; padding:.25rem 1.25rem 1.25rem; background:rgba(10,15,26,.97); border-bottom:1px solid rgba(30,41,59,.7); }
    .menu-movil.is-open { display:block; }
    .menu-movil a { display:flex; align-items:center; gap:.6rem; padding:.7rem 0; font-family:"Exo 2",sans-serif; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); border-bottom:1px solid rgba(30,41,59,.45); }
    .menu-movil a i { width:1.2rem; color:var(--gris-3); font-size:1rem; }
    @media (min-width:900px){ .nav-links{display:flex;} .nav-acc .btn{display:inline-flex;} .menu-btn,.menu-movil{display:none !important;} }
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;padding:.78rem 1.35rem;font-family:"Exo 2",sans-serif;font-size:.74rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;border-radius:2px;border:1px solid transparent;cursor:pointer;white-space:nowrap;transition:.25s;text-decoration:none;}
    .btn i{font-size:1rem;}
    .btn:hover{transform:translateY(-2px);}
    .btn-fuego{background:var(--fuego);color:#fff;box-shadow:0 14px 30px -14px rgba(121,73,181,.85);}
    .btn-topbar-home{background:transparent;color:var(--fuego);border:2px solid #7949B5;border-radius:6px;padding:.72rem 1.35rem;gap:.5rem;box-shadow:none;}
    .btn-topbar-home:hover{background:#7949B5;color:#fff;border-color:#7949B5;box-shadow:none;}
    .btn-topbar-home i{font-size:1rem;}
    .btn-ghost{background:rgba(255,255,255,.02);color:var(--gris);border-color:rgba(255,255,255,.14);}
    .btn-ghost:hover{border-color:rgba(0,212,255,.35);color:var(--txt);}

    .rel-hero{padding:calc(var(--nav-h) + 3.5rem) 0 3rem;position:relative;overflow:hidden;background:radial-gradient(ellipse at 20% 0%,rgba(255,107,0,.12),transparent 55%),radial-gradient(ellipse at 85% 100%,rgba(0,212,255,.08),transparent 45%),var(--abismo);border-bottom:1px solid rgba(30,41,59,.5);}
    .rel-hero .wrap{position:relative;z-index:1;}
    .eyebrow{display:inline-flex;align-items:center;gap:.5rem;padding:.45rem .9rem;border-radius:999px;background:rgba(121,73,181,.14);border:1px solid rgba(121,73,181,.35);color:#c084fc;font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;}
    .eyebrow::before{content:"";width:2rem;height:2px;background:var(--fuego);border-radius:2px;}
    .rel-hero h1{font-family:"Bebas Neue",Impact,sans-serif;font-size:clamp(2.1rem,5.2vw,3.8rem);line-height:.92;margin:.7rem 0 1rem;text-transform:uppercase;letter-spacing:.03em;}
    .rel-hero h1 .reg-mark,.reg-mark{font-size:.52em;vertical-align:super;margin-left:.05em;font-family:"Exo 2",sans-serif;font-weight:700;color:#fff !important;}
    .rel-hero h1 span{color:var(--cian);}
    .rel-hero p{max-width:52rem;color:var(--gris-2);font-size:1rem;line-height:1.8;margin:0;}
    .rel-meta{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem;}
    .rel-pill{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .9rem;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:var(--gris);font-size:.78rem;font-family:"Exo 2",sans-serif;}
    .rel-pill strong{color:var(--txt);}
    .rel-pill i{color:var(--cian);}

    .rel-main{padding:3rem 0 4rem;}
    .rel-layout{display:grid;gap:2rem;}
    @media(min-width:1024px){.rel-layout{grid-template-columns:minmax(0,1fr) 320px;align-items:start;}}
    .timeline{position:relative;display:grid;gap:1.5rem;}
    .timeline::before{content:"";position:absolute;left:1.15rem;top:.5rem;bottom:.5rem;width:4px;border-radius:999px;background:linear-gradient(180deg,#7949B5,rgba(121,73,181,.4),transparent);}
    @media(max-width:640px){.timeline::before{left:.85rem;}}
    .release{position:relative;padding-left:3.25rem;scroll-margin-top:calc(var(--nav-h) + 6.5rem);}
    @media(max-width:640px){.release{padding-left:2.5rem;}}
    .release-dot{position:absolute;left:.45rem;top:1.45rem;width:1.45rem;height:1.45rem;border-radius:50%;background:#1E293B;border:2px solid #334155;box-shadow:0 4px 12px rgba(0,0,0,.35);z-index:1;display:grid;place-items:center;transition:border-color .25s,background .25s,color .25s;}
    .release-dot i{font-size:.55rem;color:#94a3b8;line-height:1;}
    .release:hover .release-dot{border-color:#7949B5;}
    .release:hover .release-dot i{color:#fff;}
    .release.is-latest .release-dot{left:.35rem;top:1.25rem;width:2rem;height:2rem;border:0;background:#7949B5;box-shadow:0 0 0 4px #0A0F1A,0 10px 24px -6px rgba(121,73,181,.7);}
    .release.is-latest .release-dot i{font-size:.72rem;color:#fff;}
    .release.is-latest .release-dot::before{content:"";position:absolute;inset:-4px;border-radius:50%;background:#7949B5;opacity:.65;filter:blur(3px);animation:dotPulse 1.8s ease-in-out infinite;z-index:-1;}
    @keyframes dotPulse{0%,100%{opacity:.65;transform:scale(1);}50%{opacity:.25;transform:scale(1.15);}}
    @media(max-width:640px){.release-dot{left:.15rem;}.release.is-latest .release-dot{left:.05rem;}}
    .release-card{position:relative;isolation:isolate;background:linear-gradient(145deg,#131b2c,#0a0f1a);border:1px solid var(--acero);border-radius:.9rem;overflow:hidden;box-shadow:0 10px 24px -18px rgba(0,0,0,.9);transition:transform .4s cubic-bezier(.4,0,.2,1),border-color .4s ease,box-shadow .4s ease;}
    .release-card .card__sheen{position:absolute;inset:0;z-index:0;pointer-events:none;opacity:0;background:radial-gradient(290px circle at var(--pointer-x,50%) var(--pointer-y,50%),var(--card-sheen,rgba(121,73,181,.22)),transparent 75%);transition:opacity .22s ease;}
    .release-card:hover .card__sheen{opacity:1;}
    .release-card > *:not(.card__sheen){position:relative;z-index:1;}
    .release-card:hover{border-color:rgba(121,73,181,.55);box-shadow:0 26px 54px -28px rgba(0,0,0,.95),0 0 0 1px rgba(121,73,181,.12);}
    .release.is-latest .release-card{--card-sheen:rgba(121,73,181,.32);border:2px solid #7949B5;background:linear-gradient(145deg,rgba(121,73,181,.28),#151c30 38%,#0A0F1A 100%);box-shadow:0 0 0 1px rgba(121,73,181,.35),0 28px 60px -24px rgba(121,73,181,.55),0 18px 40px -20px rgba(0,0,0,.8);}
    .release.is-latest .release-card:hover{border-color:#9b6bd1;box-shadow:0 0 0 1px rgba(155,107,209,.45),0 32px 64px -24px rgba(121,73,181,.6),0 18px 40px -20px rgba(0,0,0,.8);}

    .release-top{display:flex;flex-wrap:wrap;gap:.75rem 1rem;align-items:flex-start;justify-content:space-between;padding:1.35rem 1.35rem 1rem;border-bottom:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(255,255,255,.03),transparent);}
    .release.is-latest .release-top{background:transparent;border-bottom-color:rgba(255,255,255,.15);}
    .release-ver{font-family:"Bebas Neue",Impact,sans-serif;font-size:2.2rem;line-height:1;letter-spacing:.03em;margin:0;color:var(--txt);display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;}
    .release-code{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gris-3);padding:.25rem .55rem;border:1px solid rgba(255,255,255,.1);border-radius:999px;background:rgba(255,255,255,.03);}
    .release-latest{display:inline-flex;align-items:center;gap:.45rem;padding:.28rem .7rem;border-radius:999px;font-family:"Exo 2",sans-serif;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;background:#7949B5;color:#fff;box-shadow:0 8px 20px -8px rgba(121,73,181,.7);}
    .release-latest::before{content:"";width:.45rem;height:.45rem;border-radius:50%;background:#fff;animation:pulseLatestDot 1.4s infinite;}
    @keyframes pulseLatestDot{0%{transform:scale(1);opacity:1;}70%{transform:scale(2.2);opacity:0;}100%{opacity:0;}}
    .release-date{font-family:"Exo 2",sans-serif;font-size:.8rem;color:var(--gris-3);white-space:nowrap;display:inline-flex;align-items:center;gap:.45rem;}
    .release-date i{color:var(--cian);}
    .release-title{margin:.45rem 0 0;font-size:1rem;font-weight:700;color:var(--gris);font-family:"Exo 2",sans-serif;}
    .release-body{padding:1.15rem 1.35rem 1.35rem;}
    .release-summary{margin:0;color:var(--gris-2);line-height:1.75;font-size:.92rem;}

    .change-group{margin-top:1rem;padding:1rem;border-radius:.85rem;border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.02);}
    .change-group h3{margin:0 0 .75rem;display:flex;align-items:center;gap:.55rem;font-family:"Exo 2",sans-serif;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}
    .change-group h3 i{width:1.7rem;height:1.7rem;display:inline-grid;place-items:center;border-radius:.45rem;font-size:.8rem;}
    .change-group ul{margin:0;padding:0 0 0 1.1rem;list-style:none;display:grid;gap:.65rem;}
    .change-group li{position:relative;padding-left:2.35rem;color:var(--gris-2);line-height:1.65;font-size:.9rem;}
    .change-group li::before{content:"\f00c";font-family:"Font Awesome 7 Free","Font Awesome 6 Free";font-weight:900;position:absolute;left:.35rem;top:.2rem;font-size:.9rem;line-height:1;}
    .group-novedades{border-color:rgba(0,212,255,.16);background:rgba(0,212,255,.03);}
    .group-novedades h3{color:#7dd3fc;} .group-novedades h3 i{background:rgba(0,212,255,.14);color:var(--cian);} .group-novedades li::before{color:var(--cian);}
    .group-correcciones{border-color:rgba(255,107,0,.16);background:rgba(255,107,0,.03);}
    .group-correcciones h3{color:#fdba74;} .group-correcciones h3 i{background:rgba(121,73,181,.14);color:var(--fuego);} .group-correcciones li::before{color:var(--fuego);}

    .side-card{background:linear-gradient(145deg,#101827,#0a0f1a);border:1px solid rgba(30,41,59,.85);border-radius:1rem;padding:1.25rem;position:sticky;top:calc(var(--nav-h) + 1rem);}
    .side-card h3{font-family:"Bebas Neue",Impact,sans-serif;font-size:1.7rem;line-height:1;margin:0 0 .5rem;letter-spacing:.03em;text-transform:uppercase;}
    .side-card p{color:var(--gris-2);font-size:.9rem;line-height:1.7;margin:0 0 1rem;}
    .side-actions{display:grid;gap:.65rem;} .side-actions .btn{width:100%;}
    .side-note{margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,.08);color:var(--gris-3);font-size:.78rem;line-height:1.6;}
    .support-card{margin-top:2rem;padding:2rem 1.5rem;border-radius:1rem;border:1px solid rgba(255,255,255,.08);background:linear-gradient(145deg,#131b2c,#0a0f1a);text-align:center;transition:transform .28s ease,box-shadow .28s ease,border-color .28s ease;}
    .support-card > div{display:flex;flex-direction:column;align-items:center;}
    .support-card h2{font-family:"Bebas Neue",Impact,sans-serif;font-size:clamp(1.7rem,4vw,2.3rem);margin:0;line-height:1;letter-spacing:.03em;text-transform:uppercase;color:var(--txt);}
    .support-card p{margin:.75rem 0 1.25rem;color:var(--gris-2);max-width:38rem;line-height:1.7;font-size:.95rem;}
    .support-card .btn{min-width:180px;}
    
    .rel-loading{display:flex;align-items:center;gap:.75rem;margin-top:1rem;color:var(--gris-2);font-family:"Exo 2",sans-serif;font-size:.9rem;}
    .rel-loading .spin{width:1.15rem;height:1.15rem;border:2px solid rgba(121,73,181,.25);border-top-color:#7949B5;border-radius:50%;animation:relspin .9s linear infinite;flex:0 0 auto;}
    @keyframes relspin{to{transform:rotate(360deg)}}
    .breadcrumbs-wrap{position:sticky;top:calc(var(--nav-h) + .6rem);z-index:40;margin:0 0 1.25rem;padding:.65rem .75rem;border-radius:10px;background:rgba(10,15,26,.88);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(121,73,181,.18);box-shadow:0 10px 30px -20px rgba(0,0,0,.8);}
    .breadcrumbs{display:flex;flex-wrap:wrap;gap:.6rem;margin:0;padding:0;list-style:none;}
    .breadcrumbs a,.breadcrumbs span{display:inline-flex;align-items:center;gap:.45rem;padding:.45rem .8rem;border-radius:6px;background:transparent;border:2px solid #7949B5;color:#7949B5;font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-decoration:none;transition:background .2s,color .2s,border-color .2s;}
    .breadcrumbs a:hover,.breadcrumbs a.is-active{background:#7949B5;color:#fff;border-color:#7949B5;}
    .breadcrumbs .bc-latest{background:rgba(121,73,181,.18);border-color:#7949B5;color:#c084fc;}.breadcrumbs .bc-latest:hover,.breadcrumbs .bc-latest.is-active{background:#7949B5;color:#fff;border-color:#7949B5;}
    .breadcrumbs .bc-rev{display:inline-flex;align-items:center;padding:.08rem .4rem;border-radius:999px;background:rgba(121,73,181,.28);border:1px solid rgba(121,73,181,.55);color:#fff;font-size:.62rem;letter-spacing:.03em;text-transform:uppercase;}
    .release-right{display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;}
    .release-platforms{display:inline-flex;align-items:center;gap:.5rem;padding:.32rem .7rem;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#cbd5e1;font-family:"Exo 2",sans-serif;font-size:.68rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;white-space:nowrap;}
    .release-platforms i{color:#94a3b8;font-size:.85rem;}
    .release.is-latest .release-platforms{background:rgba(0,0,0,.2);border-color:rgba(255,255,255,.12);color:#e2e8f0;}
    @media(max-width:640px){.release-right{align-items:flex-start;}}

    .loading,.error{padding:1.5rem;border-radius:1rem;border:1px dashed rgba(255,255,255,.18);color:var(--gris-2);font-family:"Exo 2",sans-serif;}
    .error{border-color:rgba(239,68,68,.35);color:#fca5a5;}

    .scroll-fabs{position:fixed;right:1.15rem;bottom:1.15rem;z-index:55;display:flex;flex-direction:column;gap:.55rem;}
    .scroll-fab{display:inline-grid;place-items:center;width:2.85rem;height:2.85rem;border-radius:999px;border:1px solid rgba(0,212,255,.35);color:var(--cian);background:rgba(10,15,26,.72);font-size:1rem;cursor:pointer;opacity:0;visibility:hidden;transform:translateY(8px) scale(.92);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);transition:opacity .2s,transform .2s,visibility .2s,background .2s,color .2s,border-color .2s;}
    .scroll-fab.is-visible{opacity:1;visibility:visible;transform:none;}
    .scroll-fab:hover{color:var(--abismo);background:var(--cian);border-color:var(--cian);transform:translateY(-2px);}
    .scroll-fab:focus-visible{outline:2px solid var(--cian);outline-offset:3px;}
    @media(max-width:30rem){.scroll-fabs{right:.8rem;bottom:.8rem;}.scroll-fab{width:2.55rem;height:2.55rem;font-size:.9rem;}}

    .pie{padding:3.5rem 0 0;background:#060a12;border-top:1px solid rgba(30,41,59,.7);}
    .pie-grid{display:grid;gap:2.25rem;padding-bottom:2.5rem;grid-template-columns:minmax(0,1fr);}
    @media(min-width:640px){.pie-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(min-width:1024px){.pie-grid{grid-template-columns:1.2fr 1fr 1fr 1fr;gap:2.5rem;}}
    .pie-marca{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem;}
    .pie-marca img{width:2.4rem;height:2.4rem;object-fit:contain;}
    .pie-grid p,.pie-grid a{color:var(--gris-2);font-size:.86rem;line-height:1.7;}
    .pie-grid a:hover{color:var(--txt);}
    .pie h4{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--txt);margin-bottom:.85rem;}
    .pie-lista{list-style:none;display:grid;gap:.45rem;padding:0;margin:0;}
    .pie-lista a,.pie-legal a{display:inline-flex;align-items:center;gap:.5rem;}
    .pie-lista a i,.pie-legal a i{color:var(--gris-3);font-size:.8rem;width:.9rem;text-align:center;}
    .pie-legal{display:grid;gap:.45rem;}
    
    .pie-social{display:flex;flex-wrap:nowrap;gap:.55rem;margin:.2rem 0 1rem;overflow-x:auto;}
    .pie-social a{width:2.2rem;height:2.2rem;flex:0 0 2.2rem;border-radius:999px;border:1px solid rgba(255,255,255,.12);display:inline-flex;align-items:center;justify-content:center;color:var(--gris-2);transition:.2s;background:rgba(255,255,255,.02);}
    .pie-social a:hover{color:var(--txt);border-color:rgba(121,73,181,.55);background:rgba(121,73,181,.12);transform:translateY(-1px);}
    .pie-social svg{width:1rem;height:1rem;display:block;}
    .pie-legal-title{margin-top:1.2rem;}

    .pie-barra{display:flex;flex-direction:column;gap:.5rem;padding:1.15rem 0 1.6rem;border-top:1px solid rgba(30,41,59,.55);color:var(--gris-3);font-size:.75rem;}
    @media(min-width:640px){.pie-barra{flex-direction:row;align-items:center;justify-content:space-between;}}
    </style>
</head>
<body>
    <header class="nav" id="topbar">
        <div class="wrap nav-in">
            <a href="/index.php" class="marca" aria-label="Ir a la portada de UnicornioSoftware">
                <img src="/images/logo.png" alt="Logo UnicornioSoftware" width="40" height="40">
                <span class="marca-txt"><b><i>Unicornio</i><em>Software</em></b><small>Donde tu visión toma forma</small></span>
            </a>
            <nav class="nav-links" aria-label="Navegación principal">
                <a href="/index.php#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
                <a href="/index.php#ingenieria"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ingeniería</a>
                <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a>
                <a href="/releases.php" class="is-active"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Novedades</a>
                <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
            </nav>
            <div class="nav-acc">
                <a class="btn btn-topbar-home" href="/index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
                <button type="button" class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="menuMovil" aria-label="Abrir menú"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="menu-movil" id="menuMovil">
            <div class="wrap">
                <a href="/index.php#ecosistema"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ecosistema</a>
                <a href="/index.php#ingenieria"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ingeniería</a>
                <a href="/about.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Sobre nosotros</a>
                <a href="/releases.php"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Novedades</a>
                <a href="/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contacto</a>
                <a href="/index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            </div>
        </div>
    </header>

    <main>
        <section class="rel-hero">
            <div class="wrap">
                <span class="eyebrow">Historial de versiones y cambios</span>
                <h1>Novedades <span style="color:var(--fuego);">Ecosistema Financiero</span> de UnicornioSoftware<span class="reg-mark">&reg;</span></h1>
                <p>Aquí puedes ver, de forma sencilla, qué ha mejorado en cada versión: encontrarás correcciones, novedades y cambios importantes para saber qué trae cada actualización. Evolución continua, notas de lanzamiento y actualizaciones oficiales del software.</p>
                <div class="rel-meta">
                    <span class="rel-pill"><i class="fa-solid fa-tag" aria-hidden="true"></i> Versión actual: <strong id="currentVersion">Cargando...</strong></span>
                    <span class="rel-pill"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Última actualización: <strong id="currentDate">—</strong></span>
                    <span class="rel-pill"><i class="fa-brands fa-android" aria-hidden="true"></i> App Android disponible</span>
                </div>
                <div class="rel-loading" id="releasesLoading" aria-live="polite"><span class="spin" aria-hidden="true"></span><span>Cargando la línea de tiempo de versiones…</span></div>
            </div>
        </section>

        <section class="rel-main">
            <div class="wrap rel-layout">
                <div>
                                        <nav aria-label="Versiones" class="breadcrumbs-wrap"><ul class="breadcrumbs" id="versionBreadcrumbs"></ul></nav>
                    <div id="releasesTimeline" class="timeline" aria-live="polite">
                        <div class="loading"><span class="rel-loading" style="margin:0"><span class="spin" aria-hidden="true"></span><span>Cargando la línea de tiempo de versiones…</span></span></div>
                    </div>

                                        <div class="support-card release-card">
                        <div>
                            <h2>¿Tienes alguna sugerencia o encontraste un fallo?</h2>
                            <p>Tu retroalimentación impulsa nuestras próximas versiones. Escríbenos directamente a nuestro equipo de soporte.</p>
                            <a class="btn btn-fuego" href="<?= $base ?>/soporte.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Contactar con soporte</a>
                        </div>
                    </div>
                </div>

                <aside class="side-card" aria-label="Descarga de la aplicación">
                    <h3>Descarga la app</h3>
                    <p>Instala la aplicación para Android y recibe las mejoras más recientes.</p>
                    <div class="side-actions">
                        <a class="btn btn-fuego" id="downloadBtn" href="https://github.com/kakycu/TransNuBeT/raw/main/android-apk/dist/EcosisUnicorn.apk" download><i class="fa-brands fa-android" aria-hidden="true"></i> Descargar APK</a>
                        <a class="btn btn-ghost" href="/index.php#apk-download"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a la portada</a>
                    </div>
                    <div class="side-note" id="sideNote">La información de versiones se carga desde el archivo oficial de novedades.</div>
                </aside>
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

    <div class="scroll-fabs">
        <button type="button" class="scroll-fab" id="btnScrollTop" title="Ir hasta arriba" aria-label="Ir hasta arriba de la página"><i class="fa-solid fa-angles-up" aria-hidden="true"></i></button>
        <button type="button" class="scroll-fab" id="btnScrollBottom" title="Ir hasta abajo" aria-label="Ir hasta abajo de la página"><i class="fa-solid fa-angles-down" aria-hidden="true"></i></button>
    </div>

    <script>
    (function () {
        var btn = document.getElementById('menuBtn');
        var menu = document.getElementById('menuMovil');
        if (btn && menu) {
            btn.addEventListener('click', function () {
                var abierto = menu.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            });
        }
        var arriba = document.getElementById('btnScrollTop');
        var abajo = document.getElementById('btnScrollBottom');
        function actualizarScroll() {
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            if (arriba) arriba.classList.toggle('is-visible', y > 140);
            if (abajo) abajo.classList.toggle('is-visible', y < max - 140);
        }
        if (arriba) arriba.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        if (abajo) abajo.addEventListener('click', function () { window.scrollTo({ top: Math.max(0, document.documentElement.scrollHeight - window.innerHeight), behavior: 'smooth' }); });
        window.addEventListener('scroll', actualizarScroll, { passive: true });
        window.addEventListener('resize', actualizarScroll);
        actualizarScroll();

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
        function fmtDate(iso) {
            if (!iso) return '';
            var p = String(iso).split('-');
            if (p.length !== 3) return esc(iso);
            var m = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
            var mi = parseInt(p[1], 10) - 1;
            return parseInt(p[2],10) + ' de ' + (m[mi] || p[1]) + ' de ' + p[0];
        }
        function groupHTML(title, icon, cls, items) {
            if (!Array.isArray(items) || !items.length) return '';
            return '<div class="change-group ' + cls + '"><h3><i class="fa-solid ' + icon + '" aria-hidden="true"></i> ' + esc(title) + '</h3><ul>' +
                items.map(function (it) { return '<li>' + esc(it) + '</li>'; }).join('') + '</ul></div>';
        }
        
        // Brillo spotlight sobre tarjetas (como ecosistema en index.php)
        (function () {
            function mover(e) {
                var r = this.getBoundingClientRect();
                this.style.setProperty('--pointer-x', (e.clientX - r.left) + 'px');
                this.style.setProperty('--pointer-y', (e.clientY - r.top) + 'px');
            }
            function bindSpotlight(root) {
                var tarjetas = (root || document).querySelectorAll('.release-card[data-spotlight]');
                for (var i = 0; i < tarjetas.length; i++) {
                    if (tarjetas[i].__spotBound) continue;
                    tarjetas[i].__spotBound = true;
                    tarjetas[i].addEventListener('pointermove', mover);
                }
            }
            window.__bindReleaseSpotlight = bindSpotlight;
            bindSpotlight(document);
        })();

        var timeline = document.getElementById('releasesTimeline');
        var currentVersion = document.getElementById('currentVersion');
        var currentDate = document.getElementById('currentDate');
        var downloadBtn = document.getElementById('downloadBtn');
        var sideNote = document.getElementById('sideNote');
        var urls = ['android-apk/dist/version.json', 'https://raw.githubusercontent.com/kakycu/TransNuBeT/main/android-apk/dist/version.json'];
        
        function releaseAnchor(c) {
            var vc = parseInt(c.versionCode,10)||0;
            if (vc > 0) return 'release-v' + vc;
            var vn = String(c.versionName || '').trim().replace(/[^0-9A-Za-z.]+/g, '-');
            return 'release-v' + (vn || '0');
        }
        function renderBreadcrumbs(changes, latestCode) {
            var bc = document.getElementById('versionBreadcrumbs');
            if (!bc) return;
            var counts = {};
            changes.forEach(function (c) {
                var n = String(c.versionName || c.versionCode || '');
                counts[n] = (counts[n] || 0) + 1;
            });
            bc.innerHTML = changes.map(function(c, idx){
                var vc = parseInt(c.versionCode,10)||0; var vn = String(c.versionName||vc||'');
                var latest = idx===0 || vc===latestCode;
                var dup = counts[vn] > 1;
                var label = 'v' + esc(vn) + (dup ? ' <span class="bc-rev">Rev. ' + esc(vc) + '</span>' : '');
                return '<li><a href="#' + esc(releaseAnchor(c)) + '" class="' + (latest?'bc-latest':'') + '">' + (latest?'<i class="fa-solid fa-star" aria-hidden="true"></i> ':'') + label + '</a></li>';
            }).join('');
        }
        function hideReleasesLoading(){ var l=document.getElementById('releasesLoading'); if(l) l.style.display='none'; }


        function bindReleaseSpy() {
            var links = Array.prototype.slice.call(document.querySelectorAll('#versionBreadcrumbs a[href^="#release-v"]'));
            var sections = links.map(function (a) {
                var id = (a.getAttribute('href') || '').slice(1);
                return id ? document.getElementById(id) : null;
            }).filter(Boolean);
            if (!links.length || !sections.length) return;
            function setActive(id) {
                links.forEach(function (a) {
                    var on = (a.getAttribute('href') || '') === '#' + id;
                    a.classList.toggle('is-active', on);
                    if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
                });
            }
            if ('IntersectionObserver' in window) {
                var current = sections[0].id;
                var obs = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) { if (e.isIntersecting) current = e.target.id; });
                    setActive(current);
                }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });
                sections.forEach(function (s) { obs.observe(s); });
                setActive(current);
            } else {
                var onScroll = function () {
                    var y = window.scrollY + (window.innerHeight * 0.28);
                    var current = sections[0].id;
                    for (var i = 0; i < sections.length; i++) {
                        var r = sections[i].getBoundingClientRect();
                        var top = r.top + window.scrollY;
                        if (top <= y) current = sections[i].id;
                    }
                    setActive(current);
                };
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
            }
            links.forEach(function (a) {
                a.addEventListener('click', function () {
                    var id = (a.getAttribute('href') || '').slice(1);
                    if (id) setActive(id);
                });
            });
        }

        function render(data) {
            var changes = Array.isArray(data.changes) ? data.changes : [];
            var latestCode = parseInt(data.versionCode, 10) || 0;
            var latestName = String(data.versionName || latestCode);
            currentVersion.textContent = 'v' + latestName + ' (Rev. ' + latestCode + ')';
            currentDate.textContent = fmtDate(data.releasedAt) || '-';
            renderBreadcrumbs(changes, latestCode);
            hideReleasesLoading();
            if (data.apkUrl) downloadBtn.href = data.apkUrl;
            sideNote.textContent = 'Mostrando ' + changes.length + ' actualizaciones. La versión más reciente aparece primero.';
            if (!changes.length) { hideReleasesLoading(); timeline.innerHTML = '<div class="error">No hay novedades disponibles por ahora.</div>'; return; }
            timeline.innerHTML = changes.map(function (c, idx) {
                var vc = parseInt(c.versionCode, 10) || 0;
                var vn = String(c.versionName || vc || '');
                var latest = idx === 0 || vc === latestCode;
                var novedades = Array.isArray(c.novedades) ? c.novedades : [];
                var correcciones = Array.isArray(c.correcciones) ? c.correcciones : [];
                var legacy = (!novedades.length && !correcciones.length && Array.isArray(c.items)) ? c.items : [];
                var body = groupHTML('Novedades y Mejoras', 'fa-wand-magic-sparkles', 'group-novedades', novedades) +
                           groupHTML('Correcciones de Errores', 'fa-screwdriver-wrench', 'group-correcciones', correcciones) +
                           groupHTML('Cambios', 'fa-circle-check', 'group-novedades', legacy);
                return '<article id="' + esc(releaseAnchor(c)) + '" class="release ' + (latest ? 'is-latest' : '') + '">' +
                    '<span class="release-dot" aria-hidden="true"><i class="fa-solid ' + (latest ? 'fa-star' : 'fa-code-branch') + '"></i></span>' +
                    '<div class="release-card" data-spotlight style="--card-sheen:rgba(121,73,181,.22)"><span class="card__sheen" aria-hidden="true"></span>' +
                        '<div class="release-top"><div>' +
                            '<h2 class="release-ver">v' + esc(vn) + ' <span class="release-code">Rev. ' + esc(vc) + '</span>' + (latest ? ' <span class="release-latest">Último Lanzamiento</span>' : '') + '</h2>' +
                            '<p class="release-title">' + esc(c.title || 'Novedades') + '</p>' +
                        '</div><div class="release-right"><span class="release-platforms"><i class="fa-brands fa-windows" aria-hidden="true"></i> Windows &nbsp;<i class="fa-brands fa-android" aria-hidden="true"></i> Android</span><div class="release-date"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> ' + esc(fmtDate(c.date)) + '</div></div></div>' +
                        '<div class="release-body">' +
                            (c.summary ? '<p class="release-summary">' + esc(c.summary) + '</p>' : '') +
                            body +
                        '</div>' +
                    '</div></article>';
            }).join('');
            if (window.__bindReleaseSpotlight) window.__bindReleaseSpotlight();
            bindReleaseSpy();
        }
        function load(i) {
            if (i >= urls.length) { timeline.innerHTML = '<div class="error">No se pudieron cargar las novedades. Intenta recargar la página.</div>'; return; }
            fetch(urls[i], { cache: 'no-store', headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(render)
                .catch(function () { load(i + 1); });
        }
        load(0);
    })();
    </script>
</body>
</html>

