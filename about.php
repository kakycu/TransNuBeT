<?php $base = '/servicios'; ?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre nosotros | UnicornioSoftware</title>
    <meta name="description"
        content="Conoce a UnicornioSoftware: equipo, experiencia, metodología y las soluciones que construimos para negocios y organizaciones en Cuba.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="/about.php">
    <meta property="og:site_name" content="UnicornioSoftware">
    <meta property="og:title" content="Sobre nosotros | UnicornioSoftware">
    <meta property="og:description" content="El equipo detrás de UnicornioSoftware. Experiencia técnica, metodología clara y soluciones adaptadas a necesidades reales.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Sobre nosotros | UnicornioSoftware">
    <meta name="twitter:description" content="Conoce al equipo, la trayectoria y la forma de trabajo de UnicornioSoftware.">

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

    <link rel="canonical" href="/about.php">
    <style>
    html { scroll-padding-top: 5.75rem; }
    :root {
        --abismo:#0a0f1a; --panel:#0b0e14; --tarjeta:#090e18; --acero:#1e293b;
        --cian:#00d4ff; --fuego:#ff6b00; --fuego-h:#ff8a33;
        --txt:#ffffff; --gris:#d1d5db; --gris-2:#9ca3af; --gris-3:#6b7280;
        --nav-h:5rem; --wrap:80rem; --radio:.5rem;
        --muted:#93a4bd; --border:#1e293b; --border-strong:#2b3b55;
        --tile:rgba(255,255,255,.04); --mint:#22c55e; --cyan:#00d4ff;
        --accent:#ff6b00; --accent-soft:rgba(255,107,0,.14);
        --surface:#0d1422; --surface-solid:#0d1422; --surface-strong:#131b2c;
        --card-hover-border:#2b3b55; --danger:#ef4444; --danger-txt:#fca5a5;
        --danger-soft:rgba(239,68,68,.12); --danger-border:rgba(239,68,68,.38);
        --warn:#f59e0b; --radius-lg:1rem; --shadow:0 24px 60px -30px #000;
        --shadow-glow:0 0 0 1px rgba(0,212,255,.18);
    }
    .wrap { width:100%; max-width:var(--wrap); margin-inline:auto; padding-inline:clamp(1.25rem,4vw,3rem); }
    .nav { position:fixed; inset:0 0 auto 0; z-index:50; height:var(--nav-h); background:rgba(10,15,26,.88); -webkit-backdrop-filter:blur(18px) saturate(140%); backdrop-filter:blur(18px) saturate(140%); border-bottom:1px solid rgba(30,41,59,.7); }
    .nav-in { height:100%; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .marca { display:inline-flex; align-items:center; gap:.65rem; flex:0 0 auto; text-decoration:none; }
    .marca img { width:2.5rem; height:2.5rem; object-fit:contain; filter:drop-shadow(0 0 10px rgba(0,212,255,.2)); }
    .marca-txt b { display:block; font-family:"Bebas Neue",Impact,sans-serif; font-weight:400; font-size:1.5rem; line-height:.9; letter-spacing:.03em; }
    .marca-txt b i { font-style:normal; color:var(--cian); }
    .marca-txt b em { font-style:normal; color:var(--fuego); }
    .marca-txt small { margin-top:.2rem; font-size:.58rem; letter-spacing:.22em; text-transform:uppercase; color:var(--gris-3); font-weight:600; }
    .nav-links { display:none; align-items:center; gap:1.4rem; }
    .nav-links a { font-family:"Exo 2",sans-serif; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); transition:color .2s; }
    .nav-links a:hover,.nav-links a:focus-visible { color:var(--txt); }
    .nav-links a i { margin-right:.38rem; color:var(--gris-3); font-size:.78em; transition:color .2s; }
    .nav-links a:hover i,.nav-links a:focus-visible i { color:var(--cian); }
    .nav-acc { display:flex; align-items:center; gap:.6rem; }
    .nav-acc .btn { display:none; }
    .menu-btn { display:inline-grid; place-items:center; width:2.75rem; height:2.75rem; border:1px solid var(--acero); border-radius:2px; background:transparent; color:var(--gris-2); font-size:1.1rem; cursor:pointer; transition:color .2s,border-color .2s; }
    .menu-btn:hover,.menu-btn:focus-visible { color:var(--txt); border-color:rgba(0,212,255,.4); }
    .menu-movil { display:none; padding:.25rem 1.25rem 1.25rem; background:rgba(10,15,26,.97); border-bottom:1px solid rgba(30,41,59,.7); }
    .menu-movil.is-open { display:block; }
    .menu-movil a { display:block; padding:.7rem 0; font-family:"Exo 2",sans-serif; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--gris-2); border-bottom:1px solid rgba(30,41,59,.45); transition:color .2s; }
    .menu-movil a:last-child { border-bottom:0; }
    .menu-movil a:hover,.menu-movil a:focus-visible { color:var(--txt); }
    .menu-movil a i { width:1.15rem; margin-right:.55rem; color:var(--gris-3); font-size:.88em; transition:color .2s; }
    .menu-movil a:hover i,.menu-movil a:focus-visible i { color:var(--cian); }
    .menu-movil-titulo { display:block; padding:1rem 0 .45rem; font-family:"Exo 2",sans-serif; font-size:.62rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--gris-3); }
    .nav-login { position:relative; display:none; }
    .nav-acc .nav-login { display:none; }
    .nav-login .btn { display:inline-flex; align-items:center; gap:.55rem; }
    .nav-login-caret { font-size:.65em; opacity:.75; transition:transform .25s; }
    .nav-login.is-open .nav-login-caret { transform:rotate(180deg); }
    .nav-login-menu { position:absolute; top:calc(100% + .65rem); right:0; min-width:14.5rem; padding:.45rem; background:rgba(13,20,34,.96); border:1px solid rgba(30,41,59,.9); border-radius:.5rem; box-shadow:0 24px 48px -20px rgba(0,0,0,.85); -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px); opacity:0; visibility:hidden; transform:translateY(-6px); transition:opacity .2s,transform .2s,visibility .2s; z-index:60; }
    .nav-login.is-open .nav-login-menu { opacity:1; visibility:visible; transform:translateY(0); }
    .nav-login-menu a { display:flex; align-items:center; gap:.7rem; padding:.65rem .75rem; border-radius:.35rem; font-family:"Exo 2",sans-serif; font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--gris-2); transition:color .2s,background .2s; }
    .nav-login-menu a:hover,.nav-login-menu a:focus-visible { color:var(--txt); background:rgba(0,212,255,.08); }
    .nav-login-menu a i { width:1rem; color:var(--cian); font-size:.9rem; text-align:center; }
    @media (min-width:900px){ .nav-links{display:flex;} .nav-acc .btn{display:inline-flex;} .nav-acc .nav-login{display:block;} .menu-btn,.menu-movil{display:none !important;} }
    @media (min-width:900px) and (max-width:1179px){ .nav-in{gap:.45rem;} .nav-links{gap:.5rem;} .marca img{width:2.25rem;height:2.25rem;} .marca-txt b{font-size:1.3rem;} .marca-txt small{font-size:.52rem;letter-spacing:.14em;} .nav-acc .btn{padding:.55rem .8rem;font-size:.64rem;letter-spacing:.06em;} .nav-links a{font-size:.72rem;letter-spacing:.03em;white-space:nowrap;} }
    @media (min-width:1180px) and (max-width:1399px){ .nav-in{gap:.65rem;} .nav-links{gap:.72rem;} .nav-links a{font-size:.82rem;letter-spacing:.04em;} .nav-acc .btn{padding:.6rem .95rem;font-size:.68rem;} }
    @media (min-width:1400px){ .nav-links{gap:1.3rem;} .nav-links a{font-size:.89rem;} }

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;padding:.78rem 1.35rem;font-family:"Exo 2",sans-serif;font-size:.74rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;border-radius:2px;border:1px solid transparent;cursor:pointer;white-space:nowrap;transition:transform .25s,box-shadow .25s,background .25s,color .25s,border-color .25s;}
    .btn:hover{transform:translateY(-2px);} .btn:active{transform:translateY(0);}
    .btn-fuego{background:var(--fuego);color:var(--abismo);box-shadow:0 14px 30px -14px rgba(255,107,0,.85);}
    .btn-fuego:hover{background:var(--fuego-h);box-shadow:0 20px 36px -14px rgba(255,107,0,.95);}
    .btn-cian{background:var(--cian);color:var(--abismo);box-shadow:0 14px 30px -14px rgba(0,212,255,.75);}
    .btn-cian:hover{background:#33dcff;box-shadow:0 20px 36px -14px rgba(0,212,255,.9);}
    .btn-ghost{background:rgba(255,255,255,.02);color:var(--gris);border-color:rgba(255,255,255,.14);}
    .btn-ghost:hover{color:var(--txt);border-color:rgba(0,212,255,.45);background:rgba(0,212,255,.05);}
    .btn-verde{background:#25D366;color:#0A0F1A;box-shadow:0 14px 30px -14px rgba(37,211,102,.75);}
    .btn-verde:hover{background:#2be06f;box-shadow:0 20px 36px -14px rgba(37,211,102,.9);}
    .btn-sm{padding:.6rem 1rem;font-size:.68rem;}

    .scroll-progress{position:fixed;inset:0 0 auto 0;height:3px;z-index:60;background:rgba(255,255,255,.04);pointer-events:none;}
    .scroll-progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--cian),var(--fuego));box-shadow:0 0 12px rgba(0,212,255,.55);}

    .pie{padding:3.5rem 0 0;background:#060a12;border-top:1px solid rgba(30,41,59,.7);}
    .pie-grid{display:grid;gap:2.25rem;padding-bottom:2.5rem;grid-template-columns:minmax(0,1fr);}
    @media(min-width:640px){.pie-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(min-width:1024px){.pie-grid{grid-template-columns:1.3fr 1fr 1fr 1fr;gap:2.5rem;}}
    .pie-marca{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem;}
    .pie-marca img{width:2.4rem;height:2.4rem;object-fit:contain;}
    .pie-grid p,.pie-grid a{color:var(--gris-2);font-size:.86rem;line-height:1.7;}
    .pie-grid a:hover,.pie-grid a:focus-visible{color:var(--txt);}
    .pie h4{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--txt);margin-bottom:.85rem;}
    .pie-lista{list-style:none;display:grid;gap:.45rem;}
    .pie-lista a{display:inline-flex;align-items:center;gap:.5rem;}
    .pie-lista a i{color:var(--gris-3);font-size:.8rem;width:.9rem;text-align:center;transition:color .2s;}
    .pie-lista a:hover i{color:var(--cian);}
    .pie-social{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1rem;}
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
    .scroll-fab:focus-visible{outline:2px solid var(--cian);outline-offset:3px;}
    @media(max-width:30rem){.scroll-fabs{right:.8rem;bottom:.8rem;}.scroll-fab{width:2.55rem;height:2.55rem;font-size:.9rem;}}

    .nav-links a{font-size:.84rem;letter-spacing:.04em;}
    .nav-links a i{display:inline-block;margin-right:.32rem;font-size:.9em;}
    .menu-movil a{font-size:.86rem;}
    .menu-movil a i{width:1.25rem;margin-right:.6rem;font-size:1.05em;}
    .nav-login-menu a i{font-size:.95rem;}
    @media(min-width:900px){.nav-links{gap:.7rem;}.nav-links a{font-size:.72rem;letter-spacing:.03em;}}
    @media(min-width:1180px){.nav-links{gap:.8rem;}.nav-links a{font-size:.82rem;}.nav-links a i{font-size:.92em;}}
    @media(min-width:1400px){.nav-links a{font-size:.89rem;}.nav-links a i{font-size:.95em;}}

    .eyebrow{display:inline-flex;align-items:center;gap:.7rem;font-family:"Exo 2",sans-serif;font-size:.7rem;font-weight:800;letter-spacing:.22em;text-transform:uppercase;color:var(--cian);}
    .eyebrow::before{content:"";width:2rem;height:2px;background:var(--fuego);border-radius:2px;flex:0 0 auto;}
    .sec{padding:5.5rem 0;border-top:1px solid rgba(30,41,59,.5);position:relative;overflow:clip;}
    .sec-head{max-width:46rem;}
    .sec-head .eyebrow{color:var(--fuego);}
    .sec-head h2{margin:.6rem 0 .8rem;font-size:clamp(2rem,4.4vw,3rem);text-transform:uppercase;}
    .sec-head p{color:var(--gris-2);font-size:1rem;line-height:1.75;}
    .bebas{font-family:"Bebas Neue",Impact,sans-serif;font-weight:400;letter-spacing:.03em;line-height:.95;}

    .about-hero{position:relative;overflow:hidden;padding:calc(var(--nav-h) + 4rem) 0 5rem;background:radial-gradient(ellipse at 20% 0%,rgba(255,107,0,.12),transparent 55%),radial-gradient(ellipse at 85% 100%,rgba(0,212,255,.08),transparent 45%),var(--abismo);}
    .about-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 60%,var(--abismo));pointer-events:none;}
    .about-hero .wrap{position:relative;z-index:1;}
    .about-hero-grid{display:grid;gap:2.5rem;align-items:center;}
    .about-hero-copy{text-align:center;}
    .about-hero-brand{display:flex;justify-content:center;align-items:center;}
    .about-hero-logo-wrap{position:relative;padding:1.5rem;border-radius:1.5rem;background:linear-gradient(145deg,rgba(255,107,0,.08),rgba(0,212,255,.06));border:1px solid rgba(255,107,0,.2);box-shadow:0 24px 60px -24px rgba(255,107,0,.35),inset 0 1px 0 rgba(255,255,255,.04);}
    .about-hero-logo-wrap::after{content:"";position:absolute;inset:-1px;border-radius:inherit;background:linear-gradient(135deg,rgba(255,107,0,.25),transparent 40%,transparent 60%,rgba(0,212,255,.15));pointer-events:none;opacity:.6;}
    .about-hero-logo{display:block;width:min(100%,340px);height:auto;filter:drop-shadow(0 18px 40px rgba(255,107,0,.18));}
    .about-kicker{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.22em;text-transform:uppercase;color:var(--fuego);margin-bottom:1rem;}
    .about-hero h1{font-size:clamp(2.8rem,7vw,5.8rem);text-transform:uppercase;max-width:11ch;line-height:.92;margin:0 auto 1.5rem;}
    .about-hero h1 span{color:var(--cian);}
    .about-hero p{max-width:42rem;color:var(--gris-2);font-size:1.05rem;line-height:1.8;margin:0 auto;}
    .about-hero .btn-volver{margin-top:2rem;}
    .hero-scroll{position:absolute;left:50%;bottom:1.5rem;z-index:5;color:rgba(255,255,255,.55);font-size:1.6rem;line-height:1;text-decoration:none;animation:hero-flecha 2.4s cubic-bezier(.45,0,.55,1) infinite;transition:color .2s;}
    .hero-scroll:hover,.hero-scroll:focus-visible{color:var(--cian);}
    @keyframes hero-flecha{0%,100%{transform:translateX(-50%) translateY(0);opacity:.55}50%{transform:translateX(-50%) translateY(8px);opacity:1}}
    @media(min-width:900px){.about-hero-grid{grid-template-columns:1fr minmax(280px,420px);gap:3rem;}.about-hero-copy{text-align:left;}.about-hero h1,.about-hero p{margin-left:0;margin-right:0;}.about-hero-brand{justify-content:flex-end;}}

    .grid{display:grid;gap:1rem;margin-top:2.5rem;grid-template-columns:minmax(0,1fr);}
    @media(min-width:640px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(min-width:1024px){.grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
    .card{position:relative;display:flex;flex-direction:column;gap:.85rem;padding:1.5rem;background:linear-gradient(145deg,#131b2c,#0a0f1a);border:1px solid var(--acero);border-radius:.9rem;box-shadow:0 10px 24px -18px rgba(0,0,0,.9);transition:transform .4s cubic-bezier(.4,0,.2,1),border-color .4s ease,box-shadow .4s ease;overflow:hidden;isolation:isolate;}
    .card__sheen{position:absolute;inset:0;z-index:0;pointer-events:none;opacity:0;background:radial-gradient(290px circle at var(--pointer-x,50%) var(--pointer-y,50%),var(--card-sheen,rgba(0,212,255,.18)),transparent 75%);transition:opacity .22s ease;}
    .card:hover{transform:translateY(-6px);border-color:rgba(0,212,255,.55);box-shadow:0 26px 54px -28px rgba(0,0,0,.95);}
    .card:hover .card__sheen{opacity:1;}
    .card > *:not(.card__sheen){position:relative;z-index:1;}
    .card-head{display:flex;align-items:center;gap:.85rem;padding-bottom:.75rem;margin-bottom:.1rem;border-bottom:1px solid rgba(255,255,255,.06);}
    .card-ico{flex:0 0 auto;display:grid;place-items:center;width:3rem;height:3rem;border-radius:.7rem;background:rgba(0,212,255,.09);border:1px solid rgba(0,212,255,.22);color:var(--cian);font-size:1.15rem;transition:transform .35s cubic-bezier(.34,1.56,.64,1);}
    .card:hover .card-ico{transform:scale(1.12) rotate(6deg);}
    .card h3{font-family:"Bebas Neue",Impact,sans-serif;font-weight:400;font-size:1.55rem;line-height:1;letter-spacing:.03em;text-transform:uppercase;}
    .card-sub{margin-top:.25rem;font-family:"Exo 2",sans-serif;font-size:.62rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--cian);}
    .card p{color:var(--gris-2);font-size:.9rem;line-height:1.65;}
    .card.destacada{border-color:rgba(255,107,0,.45);background:linear-gradient(145deg,#22140c,#0a0f1a 62%);}
    .card.destacada .card-ico{background:rgba(255,107,0,.12);border-color:rgba(255,107,0,.35);color:var(--fuego);}
    .card.destacada .card-sub{color:var(--fuego);}
    .card.destacada:hover{border-color:var(--fuego);box-shadow:0 26px 54px -26px rgba(255,107,0,.35);}

    .card.reveal{opacity:0;transform:translateY(30px);transition:opacity .8s cubic-bezier(.22,.8,.24,1),transform .8s cubic-bezier(.22,.8,.24,1),border-color .4s ease,box-shadow .4s ease;transition-delay:var(--d,0ms);will-change:opacity,transform;}
    .card.reveal.active,.reveal.active{opacity:1;transform:none;}
    .card.reveal.active:hover{transform:translateY(-6px);}
    .reveal{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.22,.8,.24,1),transform .7s cubic-bezier(.22,.8,.24,1);}
    .reveal.active{opacity:1;transform:none;}

    .pasos{display:grid;gap:1rem;margin-top:2.5rem;grid-template-columns:minmax(0,1fr);}
    @media(min-width:640px){.pasos{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(min-width:1024px){.pasos{grid-template-columns:repeat(3,minmax(0,1fr));}}
    .paso{padding:1.5rem;border:1px solid rgba(30,41,59,.8);border-radius:.9rem;background:rgba(13,20,34,.55);}
    .paso-num{font-family:"Bebas Neue",Impact,sans-serif;font-size:2.4rem;line-height:1;color:rgba(0,212,255,.35);margin-bottom:.5rem;}
    .paso h3{font-family:"Exo 2",sans-serif;font-size:.82rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--txt);margin-bottom:.45rem;}
    .paso p{color:var(--gris-2);font-size:.88rem;line-height:1.7;}

    .historia-grid{display:grid;gap:2rem;margin-top:2.5rem;}
    @media(min-width:980px){.historia-grid{grid-template-columns:1.15fr .85fr;align-items:start;}}
    .historia-texto p{color:var(--gris-2);line-height:1.85;margin-bottom:1rem;}
    .historia-texto strong{color:var(--txt);}
    .cita{margin-top:1.5rem;padding:1.2rem 1.25rem;border-left:3px solid var(--fuego);background:rgba(255,107,0,.06);color:var(--gris);font-family:"Exo 2",sans-serif;font-weight:700;letter-spacing:.02em;}
    .hitos{display:grid;gap:1rem;}
    .hito{padding:1.25rem;border:1px solid rgba(30,41,59,.8);border-radius:.8rem;background:linear-gradient(145deg,#101827,#0a0f1a);display:flex;gap:1rem;align-items:flex-start;}
    .hito-ico{flex-shrink:0;width:2.5rem;height:2.5rem;border-radius:999px;display:flex;align-items:center;justify-content:center;font-size:.85rem;margin-top:.1rem;}
    .hito-ico.is-fuego{background:rgba(255,107,0,.2);border:1px solid rgba(255,107,0,.4);color:var(--fuego);}
    .hito-ico.is-cian{background:rgba(0,240,255,.1);border:1px solid rgba(0,240,255,.3);color:var(--cian);}
    .hito-k{font-family:"Exo 2",sans-serif;font-size:.66rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--cian);margin-bottom:.35rem;}
    .hito h3{font-family:"Exo 2",sans-serif;font-size:.9rem;font-weight:800;color:var(--txt);margin-bottom:.35rem;}
    .hito p{color:var(--gris-2);font-size:.88rem;line-height:1.65;margin:0;}
    .hito .hito-k{margin-bottom:.35rem;}
    .hito p{color:var(--gris-2);font-size:.86rem;line-height:1.65;}

    .equipo-card{position:relative;isolation:isolate;overflow:hidden;padding:clamp(1.5rem,3vw,2rem);background:rgba(0,0,0,.4);border:1px solid rgba(30,41,59,.6);border-radius:1rem;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);}
    .equipo-card > *:not(.card__sheen){position:relative;z-index:1;}
    .equipo-grid{display:grid;gap:2rem;}
    @media(min-width:1024px){.equipo-grid{grid-template-columns:8fr 4fr;gap:2.5rem;align-items:center;}}
    .pilares{display:grid;gap:1.6rem;}
    @media(min-width:640px){.pilares{grid-template-columns:repeat(3,minmax(0,1fr));gap:1.5rem;}}
    .pilar{padding:1.1rem 1rem;border:1px solid rgba(30,41,59,.65);border-radius:.8rem;background:rgba(255,255,255,.02);transition:transform .28s ease,border-color .28s ease,box-shadow .28s ease,background-color .28s ease;}
    .pilar:hover,.pilar:focus-within{transform:translateY(-6px);border-color:rgba(255,107,0,.35);box-shadow:0 14px 28px rgba(0,0,0,.28),0 0 0 1px rgba(255,107,0,.12);background:rgba(255,107,0,.04);}
    @media(prefers-reduced-motion:reduce){.pilar{transition:none;}.pilar:hover,.pilar:focus-within{transform:none;}}
    .pilar-ico{display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.5rem;margin-bottom:.75rem;font-size:1rem;}
    .pilar-ico.is-fuego{background:rgba(255,107,0,.1);border:1px solid rgba(255,107,0,.2);color:var(--fuego);}
    .pilar-ico.is-cian{background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.2);color:var(--cian);}
    .pilar h3{font-family:"Exo 2",sans-serif;font-size:.8rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--txt);margin-bottom:.4rem;}
    .pilar p{color:var(--gris-2);font-size:.78rem;line-height:1.65;}
    .compromiso{display:flex;flex-direction:column;align-items:flex-start;gap:1rem;}
    @media(min-width:1024px){.compromiso{border-left:1px solid rgba(255,255,255,.1);padding-left:2rem;}}
    .compromiso h4{font-family:"Exo 2",sans-serif;font-size:.8rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--txt);}
    .compromiso p{margin-top:.35rem;color:var(--gris-2);font-size:.78rem;line-height:1.65;}
    .btn-outline-fuego{display:inline-flex;align-items:center;gap:.5rem;padding:.8rem 1.4rem;border:1px solid rgba(255,107,0,.75);border-radius:.25rem;color:var(--fuego);font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;background:linear-gradient(180deg,rgba(255,107,0,.12) 0%,rgba(120,50,0,.18) 100%);box-shadow:inset 0 1px 0 rgba(255,180,120,.25),0 8px 24px -12px rgba(255,107,0,.45);transition:background .3s ease,color .3s ease,transform .3s ease,box-shadow .3s ease,border-color .3s ease;}
    .btn-outline-fuego:hover{background:linear-gradient(180deg,rgba(255,138,51,.95) 0%,rgba(255,107,0,.95) 100%);color:var(--abismo);border-color:var(--fuego-h);box-shadow:inset 0 1px 0 rgba(255,220,180,.35),0 14px 30px -12px rgba(255,107,0,.65);transform:translateY(-1px);}

    .dos-lineas{display:grid;gap:1.25rem;margin-top:2.5rem;}
    @media(min-width:900px){.dos-lineas{grid-template-columns:repeat(2,minmax(0,1fr));}}
    .linea{padding:1.75rem;border:1px solid rgba(30,41,59,.85);border-radius:1rem;background:linear-gradient(145deg,#121b2b,#0a0f1a);transition:transform .28s ease,border-color .28s ease,box-shadow .28s ease;}
    .linea:hover,.linea:focus-within{transform:translateY(-6px);border-color:rgba(0,212,255,.35);box-shadow:0 16px 32px rgba(0,0,0,.3),0 0 0 1px rgba(0,212,255,.12);}
    .linea.is-fuego{border-color:rgba(255,107,0,.3);}
    .linea.is-fuego:hover,.linea.is-fuego:focus-within{border-color:rgba(255,107,0,.45);box-shadow:0 16px 32px rgba(0,0,0,.3),0 0 0 1px rgba(255,107,0,.14);}
    @media(prefers-reduced-motion:reduce){.linea{transition:none;}.linea:hover,.linea:focus-within{transform:none;}}
    .linea-k{font-family:"Exo 2",sans-serif;font-size:.66rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--cian);margin-bottom:.5rem;}
    .linea.is-fuego .linea-k{color:var(--fuego);}
    .linea h3{font-family:"Bebas Neue",Impact,sans-serif;font-size:2.2rem;line-height:1;text-transform:uppercase;margin-bottom:.75rem;}
    .linea p{color:var(--gris-2);font-size:.92rem;line-height:1.75;margin-bottom:1rem;}
    .linea ul{list-style:none;display:grid;gap:.55rem;margin-bottom:1.25rem;}
    .linea li{color:var(--gris);font-size:.86rem;line-height:1.6;padding-left:1.1rem;position:relative;}
    .linea li::before{content:"";position:absolute;left:0;top:.55em;width:.45rem;height:.45rem;border-radius:50%;background:var(--cian);}
    .linea.is-fuego li::before{background:var(--fuego);}
    .linea hr{border:0;border-top:1px solid rgba(148,163,184,.2);margin:0 0 1rem;}
    .linea a{font-family:"Exo 2",sans-serif;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--cian);transition:color .2s ease;}
    .linea.is-fuego a{color:var(--fuego);}
    .linea a:hover,.linea a:focus-visible{color:#fff;}

    .cta{padding:2rem;border:1px solid rgba(30,41,59,.8);border-radius:1rem;background:linear-gradient(145deg,rgba(255,107,0,.08),rgba(0,212,255,.04) 55%,rgba(10,15,26,.9));text-align:center;}
    .cta .eyebrow{display:block;font-family:"Bebas Neue",Impact,sans-serif;font-size:clamp(2rem,4.5vw,3.4rem);font-weight:400;letter-spacing:.03em;line-height:.95;color:var(--fuego);margin:0 0 .6rem;text-transform:uppercase;}
    .cta h2{font-size:clamp(1.2rem,2.4vw,1.8rem);text-transform:uppercase;margin:.5rem 0 .8rem;line-height:1.2;}
    .cta p{color:var(--gris-2);max-width:42rem;line-height:1.75;margin:0 auto 1.5rem;}
    .hero-acciones{display:flex;flex-wrap:wrap;gap:.85rem;justify-content:center;}

    .maestro-modal{display:none;}
    .maestro-modal.is-open{display:flex;}
    .maestro-modal-shell{max-height:calc(100vh - 2rem);overflow:hidden;display:flex;flex-direction:column;width:100%;max-width:32rem;margin:auto;background:#0d1526;border:1px solid var(--acero);border-radius:1rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);position:relative;z-index:10;}
    .maestro-modal-header{flex-shrink:0;padding:3.5rem 1.5rem 1rem;}
    .maestro-modal-scroll{overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;padding:0 1.5rem .5rem;flex:1 1 auto;}
    .maestro-modal-footer{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.5rem 1.5rem;border-top:1px solid rgba(30,41,59,.7);background:#0d1526;}
    .maestro-modal > [data-maestro-close].fixed{position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);}
    .maestro-modal-shell > button[data-maestro-close]{position:absolute;top:1rem;right:1rem;z-index:20;width:2.5rem;height:2.5rem;border-radius:999px;display:flex;align-items:center;justify-content:center;border:1px solid var(--acero);color:#94a3b8;background:rgba(13,21,38,.8);cursor:pointer;transition:color .2s,border-color .2s;}
    .maestro-modal-shell > button[data-maestro-close]:hover{color:var(--fuego);border-color:var(--fuego);}
    @media(min-width:640px){.maestro-modal-shell{max-height:85vh;}.maestro-modal-header{padding:3.5rem 2rem 1rem;}.maestro-modal-scroll{padding:0 2rem .5rem;}.maestro-modal-footer{flex-direction:row;padding:1rem 2rem 1.5rem;}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto;}.reveal,.card.reveal{opacity:1;transform:none;transition:none;}.btn:hover,.card:hover{transform:none;}}
    </style>
</head>
<body class="bg-abismo text-white font-inter antialiased selection:bg-fuego selection:text-white">

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
            <a href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
            <a href="#historia"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historia</a>
            <a href="#equipo"><i class="fa-solid fa-users" aria-hidden="true"></i> Equipo</a>
            <a href="#especializacion"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Especialización</a>
            <a href="#metodologia"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Metodología</a>
            <a href="#soluciones"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Soluciones</a>
        </div>

        <div class="nav-acc">
            <div class="nav-login" id="navLogin">
                <button type="button" class="btn btn-cian btn-sm" id="navLoginBtn"
                        aria-haspopup="true" aria-expanded="false" aria-controls="navLoginMenu">
                    <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Iniciar sesión
                    <i class="fa-solid fa-chevron-down nav-login-caret" aria-hidden="true"></i>
                </button>
                <div class="nav-login-menu" id="navLoginMenu" role="menu" aria-labelledby="navLoginBtn">
                    <a role="menuitem" href="<?= $base ?>/nominas/login.php"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a>
                    <a role="menuitem" href="<?= $base ?>/facturacion/login.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a>
                    <a role="menuitem" href="<?= $base ?>/costos/login.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a>
                    <a role="menuitem" href="<?= $base ?>/inventarios/login.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a>
                    <a role="menuitem" href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a>
                </div>
            </div>
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Abrir menú" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="menu-movil" id="menuMovil">
        <a href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i> Inicio</a>
        <a href="#historia"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historia</a>
        <a href="#equipo"><i class="fa-solid fa-users" aria-hidden="true"></i> Equipo</a>
        <a href="#especializacion"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Especialización</a>
        <a href="#metodologia"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Metodología</a>
        <a href="#soluciones"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Soluciones</a>
        <span class="menu-movil-titulo">Iniciar sesión</span>
        <a href="<?= $base ?>/nominas/login.php"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i> Nóminas</a>
        <a href="<?= $base ?>/facturacion/login.php"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Facturación</a>
        <a href="<?= $base ?>/costos/login.php"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Costos</a>
        <a href="<?= $base ?>/inventarios/login.php"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Inventarios</a>
        <a href="<?= $base ?>/administracion/index.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Administración</a>
    </div>
</nav>

<main>
        <section class="about-hero" id="inicio">
        <div class="wrap about-hero-grid">
            <div class="about-hero-copy">
                <p class="about-kicker">Sobre nosotros</p>
                <h1 class="bebas">EL TALENTO<br><span>DETRÁS DE CADA SOLUCIÓN.</span></h1>
                <p>UnicornioSoftware nace de la experiencia de profesionales dedicados al desarrollo de software, herramientas empresariales y soluciones digitales adaptadas a necesidades reales. Unimos conocimiento técnico y visión práctica para construir tecnología útil, confiable y cercana a quienes la usan.</p>
                <a href="/index.php" class="btn-outline-fuego btn-volver"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Regresar a la página principal</a>
            </div>
            <div class="about-hero-brand">
                <div class="about-hero-logo-wrap">
                    <img src="/images/sigesnom.png" alt="Logo UnicornioSoftware" class="about-hero-logo" width="1024" height="1024" loading="eager" decoding="async">
                </div>
            </div>
        </div>
        <a href="#historia" class="hero-scroll" aria-label="Desplazarse hacia abajo"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></a>
    </section>
<section class="sec" id="historia">
    <div class="wrap">
        <div class="historia-grid">
            <div class="historia-texto reveal">
                <span class="eyebrow">Nuestra historia</span>
                <h2 class="bebas" style="margin-top:.5rem;margin-bottom:1.5rem;font-size:clamp(1.5rem,4vw,3rem);letter-spacing:.025em;line-height:1;text-transform:uppercase;">DE LA EXPERIENCIA TÉCNICA A LA VISIÓN EMPRESARIAL</h2>
                <p>UnicornioSoftware surge de la unión entre experiencia técnica y una visión clara: crear software que funcione de verdad para las personas y los negocios que lo necesitan. Antes de consolidar el ecosistema actual, nuestro equipo venía trabajando en el desarrollo de aplicaciones, sistemas de gestión y soluciones adaptadas a distintos contextos operativos.</p>
                <p>Esa experiencia nos permitió entender un problema común: muchos negocios necesitaban herramientas diseñadas para su realidad, no programas genéricos difíciles de adaptar. De ahí nace el <strong>Ecosistema Financiero</strong>, nuestro conjunto de aplicaciones, como respuesta práctica a necesidades concretas de facturación, nóminas, costos, inventarios y administración.</p>
                <p>Hoy combinamos el desarrollo de productos propios con soluciones a medida, manteniendo un enfoque sencillo: escuchar, diseñar con criterio y entregar software que aporte valor real, con datos bajo control del cliente y una operación clara.</p>
                <div class="cita">"Donde tu visión toma forma."</div>
            </div>
            <div class="hitos reveal">
                <article class="hito">
                    <span class="hito-ico is-fuego"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></span>
                    <div>
                        <p class="hito-k">Origen</p>
                        <h3>Problemas reales, soluciones propias</h3>
                        <p>Identificamos necesidades concretas de gestión en negocios y organizaciones, y decidimos construir software adaptado a ese contexto.</p>
                    </div>
                </article>
                <article class="hito">
                    <span class="hito-ico is-cian"><i class="fa-solid fa-cubes" aria-hidden="true"></i></span>
                    <div>
                        <p class="hito-k">Primeros módulos</p>
                        <h3>Un ecosistema que crece por utilidad</h3>
                        <p>Facturación, nóminas, costos e inventarios se consolidaron como módulos prácticos, pensados para trabajar en la red local del cliente.</p>
                    </div>
                </article>
                <article class="hito">
                    <span class="hito-ico is-fuego"><i class="fa-solid fa-rocket" aria-hidden="true"></i></span>
                    <div>
                        <p class="hito-k">Expansión</p>
                        <h3>Productos propios y desarrollo a medida</h3>
                        <p>Ampliamos el trabajo hacia nuevas soluciones, mejoras continuas y proyectos personalizados según los procesos de cada cliente.</p>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>
    <section class="sec" id="equipo">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Quiénes la hacen posible</span>
                <h2 class="bebas">EL EQUIPO DETRÁS DE UNICORNIOSOFTWARE</h2>
                <p>Un equipo que combina ingeniería, diseño y cercanía para construir software útil, mantenerlo y hacerlo crecer junto a cada proyecto.</p>
            </div>

            <div class="equipo-card reveal" data-spotlight style="--card-sheen: rgba(255, 107, 0, .18); margin-top:2.5rem;">
                <span class="card__sheen" aria-hidden="true"></span>
                <div class="equipo-grid">
                    <div class="pilares">
                        <div class="pilar">
                            <span class="pilar-ico is-fuego"><i class="fa-solid fa-code" aria-hidden="true"></i></span>
                            <h3>Ingeniería y arquitectura</h3>
                            <p>Diseñamos sistemas de escritorio, bases de datos locales y aplicaciones web con foco en estabilidad, mantenimiento y uso real.</p>
                        </div>
                        <div class="pilar">
                            <span class="pilar-ico is-cian"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                            <h3>Diseño y experiencia</h3>
                            <p>Creamos interfaces claras para simplificar tareas complejas y hacer que el software se adapte al ritmo de trabajo del negocio.</p>
                        </div>
                        <div class="pilar">
                            <span class="pilar-ico is-fuego"><i class="fa-solid fa-headset" aria-hidden="true"></i></span>
                            <h3>Cercanía y soporte</h3>
                            <p>Acompañamos a cada cliente con trato directo, comunicación clara y respuestas ágiles durante la implementación y el uso diario.</p>
                        </div>
                    </div>
                    <div class="compromiso">
                        <div>
                            <h4>Compromiso y visión</h4>
                            <p>Conocemos el contexto local y construimos soluciones pensando en la operación real: datos bajo control, procesos claros y tecnología que resuelve.</p>
                        </div>
                        <a class="btn-outline-fuego" href="<?= $base ?>/soporte.php">
                            <i class="fa-solid fa-headset" aria-hidden="true"></i> Hablar con soporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="fundadores" class="py-24 bg-abismo border-b border-acero">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <span class="text-fuego font-exo font-bold uppercase tracking-widest text-sm">Quienes lo hacen posible</span>
                <h2 class="font-bebas text-2xl sm:text-4xl md:text-6xl lg:text-7xl text-white mt-2 tracking-wider">LOS MAESTROS QUE LO FORJAN</h2>
                <div class="w-20 h-1 bg-fuego mx-auto rounded-full mt-4 mb-4"></div>
                <p class="text-gray-400 mt-4 max-w-2xl mx-auto">Dos trayectorias profesionales, una misma visión: construir tecnología que transforme ideas en soluciones.</p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-8 reveal">
                <article data-maestro-tarjeta="" class="maestro-card group relative mx-auto w-full max-w-[340px] aspect-[3/4] rounded-2xl overflow-hidden border border-acero bg-acero/30 shadow-lg shadow-black/40 transition-all duration-300 cursor-pointer hover:-translate-y-1 hover:border-fuego/50 hover:shadow-[0_20px_45px_-15px_rgba(255,107,0,0.35)] focus-within:border-fuego/50">
                    <img src="/images/franklin-ramos-lamadrid.jpg" alt="Retrato de Franklin Ramos Lamadrid, fundador de UnicornioSoftware" width="720" height="720" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover object-top transition-transform duration-300 ease-out group-hover:scale-[1.04]">
                    <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-[#05080f] via-[#05080f]/75 to-transparent" aria-hidden="true"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6">
                        <h3 class="font-bebas text-2xl text-white tracking-wider leading-tight">Franklin Ramos Lamadrid (Kaky)</h3>
                        <p class="text-fuego text-[11px] font-exo font-bold uppercase tracking-widest mt-1 mb-4">Fundador · Maestría y Desarrollo</p>
                        <button type="button" data-maestro-open="modal-maestro-franklin" class="flex w-full items-center justify-between gap-2 font-exo font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-sm border border-fuego/40 text-fuego bg-black/40 hover:bg-fuego hover:text-abismo transition-colors duration-300">
                            Conocer al maestro
                            <i class="fas fa-plus text-[10px] transition-transform duration-300 group-hover:rotate-45" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <div id="modal-maestro-franklin" role="dialog" aria-modal="true" aria-labelledby="maestro-franklin-nombre" class="maestro-modal fixed inset-0 z-50 flex items-start sm:items-center justify-center overflow-y-auto overscroll-contain p-4 py-8" aria-hidden="true">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" data-maestro-close=""></div>
        <div class="relative z-10 my-auto w-full max-w-lg max-h-[calc(100vh-2rem)] sm:max-h-[85vh] flex flex-col bg-[#0d1526] border border-acero rounded-2xl shadow-2xl shadow-black/60 overflow-hidden maestro-modal-shell">
            <button type="button" data-maestro-close="" aria-label="Cerrar perfil" class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full flex items-center justify-center border border-acero text-gray-400 bg-[#0d1526]/80 hover:text-fuego hover:border-fuego transition-colors">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="maestro-modal-header p-6 pt-14 sm:p-8 sm:pt-14 pb-4 sm:pb-4">
                <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                    <img src="/images/franklin-ramos-lamadrid.jpg" alt="" width="720" height="720" loading="lazy" decoding="async" class="w-28 h-28 sm:w-32 sm:h-32 rounded-xl object-cover object-top border border-fuego/30 shrink-0">
                    <div class="text-center sm:text-left">
                        <p class="text-fuego text-[11px] font-exo font-black uppercase tracking-widest mb-1">Fundador · Ingeniería y Desarrollo</p>
                        <h3 id="maestro-franklin-nombre" class="font-bebas text-3xl text-white tracking-wider">FRANKLIN RAMOS LAMADRID</h3>
						<h3 id="maestro-franklin-nombre" class="font-bebas text-3xl text-g text-indigo-400 tracking-wider">Kaky</h3>
                    </div>
                </div>
            </div>
            <div class="overflow-y-auto overscroll-contain px-6 sm:px-8 pb-2 maestro-modal-scroll">
<div class="space-y-4 text-gray-400 text-sm leading-relaxed">
    <p>Máster en Ciencias Informáticas con más de una década de experiencia en desarrollo de software, videojuegos, simulación y dirección de proyectos tecnológicos.</p>
    <p>Desarrollador full-stack con amplia experiencia en el diseño y desarrollo de interfaces de usuario, así como en la implementación de arquitecturas escalables y eficientes para sistemas empresariales. Mi enfoque principal es crear soluciones tecnológicas que no solo sean funcionales, sino también intuitivas y atractivas para los usuarios finales.</p>
    <p>Entre mis proyectos destacados se encuentra el Sistema de Gestión Empresarial, una plataforma integral diseñada para optimizar la gestión de clientes, inventarios y facturación. Este sistema combina tecnologías modernas como React para el frontend, Node.js para el backend y MongoDB como base de datos, lo que permite una experiencia de usuario fluida y un rendimiento óptimo en entornos empresariales.</p>
    <p>Su trayectoria se forjó en estudios de desarrollo, la academia y proyectos especializados — Vertical Soft Studios, Virtual Games Creations, Cátedra de Ciencias Informáticas y en la Universidad de Camagüey, dirigiendo equipos y definiendo arquitecturas de productos.</p>
    <p>Hoy aplica esa experiencia al diseño, desarrollo y evolución de las soluciones de UnicornioSoftware.</p>
</div>
            </div>
            <div class="maestro-modal-footer mt-8 pt-6 border-t border-acero/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="https://www.facebook.com/kakycu" target="_blank" rel="noopener noreferrer" class="group inline-flex items-center justify-center gap-2 font-exo font-bold text-sm uppercase tracking-wider px-5 py-2.5 rounded-sm border border-[#1877F2]/50 text-[#1877F2] bg-[#1877F2]/5 hover:bg-[#1877F2] hover:text-white hover:border-[#1877F2] hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(24,119,242,.45)] transition-all duration-300">
                    <i class="fab fa-facebook-f text-base transition-transform duration-300 group-hover:scale-110" aria-hidden="true"></i> Facebook
                </a>
                <a href="/servicios/explorer.php" class="group inline-flex items-center justify-center gap-2 font-exo font-bold text-sm uppercase tracking-wider px-5 py-2.5 rounded-sm border border-[#00d4ff]/50 text-[#00d4ff] bg-[#00d4ff]/5 hover:bg-[#00d4ff] hover:text-[#0a0f1a] hover:border-[#00d4ff] hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(0,212,255,.45)] transition-all duration-300">
                    <i class="fa-solid fa-briefcase text-base transition-transform duration-300 group-hover:scale-110" aria-hidden="true"></i> Portafolio
                </a>
                <button type="button" data-maestro-close="" class="font-exo font-bold text-sm uppercase tracking-wider px-6 py-2.5 border border-acero text-gray-400 hover:text-white hover:border-gray-400 transition-colors rounded-sm">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    <section class="sec" id="especializacion">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Capacidades</span>
                <h2 class="bebas">ÁREAS DE ESPECIALIZACIÓN</h2>
                <p>Conocimiento técnico y enfoque práctico que respaldan cada solución que desarrollamos.</p>
            </div>
            <div class="grid">
                <article class="card reveal destacada" data-spotlight style="--card-sheen: rgba(255, 107, 0, .22)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-code" aria-hidden="true"></i></span>
                        <div><h3>Ingeniería de software</h3><p class="card-sub">Arquitectura y desarrollo</p></div>
                    </div>
                    <p>Diseño y desarrollo de aplicaciones empresariales, bases de datos, módulos de gestión y soluciones personalizadas según el proceso de cada negocio.</p>
                </article>
                <article class="card reveal" data-spotlight style="--card-sheen: rgba(0, 212, 255, .18)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                        <div><h3>Aplicaciones web</h3><p class="card-sub">Sistemas en red</p></div>
                    </div>
                    <p>Plataformas y paneles accesibles desde el navegador, pensados para equipos de trabajo, control operativo y acceso seguro en la red local o según el proyecto.</p>
                </article>
                <article class="card reveal destacada" data-spotlight style="--card-sheen: rgba(255, 107, 0, .22)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-database" aria-hidden="true"></i></span>
                        <div><h3>Datos y automatización</h3><p class="card-sub">Información confiable</p></div>
                    </div>
                    <p>Modelado de datos, reportes, control de operaciones y automatización de tareas para reducir errores y ganar visibilidad sobre el negocio.</p>
                </article>
                <article class="card reveal" data-spotlight style="--card-sheen: rgba(0, 212, 255, .18)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></span>
                        <div><h3>Apps y experiencias interactivas</h3><p class="card-sub">Más allá del escritorio</p></div>
                    </div>
                    <p>Desarrollo de aplicaciones móviles y experiencias interactivas cuando el proyecto lo requiere, con tecnologías adaptadas al objetivo y al contexto de uso.</p>
                </article>
                <article class="card reveal destacada" data-spotlight style="--card-sheen: rgba(255, 107, 0, .22)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                        <div><h3>Seguridad y control local</h3><p class="card-sub">Datos del cliente</p></div>
                    </div>
                    <p>Soluciones pensadas para que la información operativa permanezca bajo control del cliente, con arquitectura definida según las necesidades de cada instalación.</p>
                </article>
                <article class="card reveal" data-spotlight style="--card-sheen: rgba(0, 212, 255, .18)">
                    <span class="card__sheen" aria-hidden="true"></span>
                    <div class="card-head">
                        <span class="card-ico"><i class="fa-solid fa-compass-drafting" aria-hidden="true"></i></span>
                        <div><h3>Dirección de proyectos</h3><p class="card-sub">Del problema a la solución</p></div>
                    </div>
                    <p>Acompañamiento en el análisis, definición del alcance, planificación y evolución del producto para que la solución crezca con el negocio.</p>
                </article>
            </div>
        </div>
    </section>
    <section class="sec" id="metodologia">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Metodología</span>
                <h2 class="bebas">DE LA NECESIDAD AL SISTEMA</h2>
                <p>Un proceso claro para transformar un problema real en una solución tecnológica útil y sostenible.</p>
            </div>
            <div class="pasos">
                <article class="paso reveal"><div class="paso-num">01</div><h3>Análisis de necesidades</h3><p>Entendemos el problema real, el contexto operativo y las restricciones del proyecto antes de proponer una solución.</p></article>
                <article class="paso reveal"><div class="paso-num">02</div><h3>Diseño de la solución</h3><p>Definimos la estructura, los flujos, los datos y el modelo de funcionamiento más adecuado para el negocio.</p></article>
                <article class="paso reveal"><div class="paso-num">03</div><h3>Desarrollo iterativo</h3><p>Construimos por entregas visibles, con retroalimentación constante para ajustar a tiempo lo que haga falta.</p></article>
                <article class="paso reveal"><div class="paso-num">04</div><h3>Pruebas y calidad</h3><p>Validamos funcionalidad, rendimiento y usabilidad antes de poner el sistema en producción.</p></article>
                <article class="paso reveal"><div class="paso-num">05</div><h3>Implementación</h3><p>Instalamos, configuramos, capacitamos al equipo y acompañamos el arranque operativo.</p></article>
                <article class="paso reveal"><div class="paso-num">06</div><h3>Evolución y soporte</h3><p>Corregimos, mejoramos y adaptamos el software a medida que el negocio cambia o crece.</p></article>
            </div>
        </div>
    </section>

    <section class="sec" id="soluciones">
        <div class="wrap">
            <div class="sec-head reveal">
                <span class="eyebrow">Lo que construimos</span>
                <h2 class="bebas">NUESTRAS LÍNEAS DE TRABAJO</h2>
                <p>Dos formas de aportar valor: productos propios para la gestión diaria y desarrollos a medida para necesidades específicas.</p>
            </div>
            <div class="dos-lineas">
                <article class="linea is-fuego reveal">
                    <p class="linea-k">Línea de productos · Ecosistema</p>
                    <h3>Ecosistema Financiero</h3>
                    <p>Familia de aplicaciones para la gestión empresarial: nóminas, facturación, costos, inventarios y administración, pensadas para operar en la red local del cliente.</p>
                    <ul>
                        <li><strong style="color:#fff">Soberanía de datos:</strong> la información operativa permanece bajo resguardo del cliente.</li>
                        <li><strong style="color:#fff">Operatividad local:</strong> módulos diseñados para funcionar con estabilidad en el entorno real del negocio.</li>
                        <li><strong style="color:#fff">Especialización práctica:</strong> cada módulo resuelve un área concreta de la gestión.</li>
                    </ul>
                    <hr>
                    <a href="<?= $base ?>/index.php">Explorar el ecosistema &rarr;</a>
                </article>
                <article class="linea reveal">
                    <p class="linea-k">Línea de servicios · A medida</p>
                    <h3>Software personalizado</h3>
                    <p>Diseñamos y desarrollamos soluciones específicas cuando el proyecto requiere un flujo, una integración o una experiencia que no resuelve un producto estándar.</p>
                    <ul>
                        <li><strong style="color:#fff">Análisis real:</strong> partimos del proceso del cliente, no de una plantilla genérica.</li>
                        <li><strong style="color:#fff">Arquitectura adecuada:</strong> elegimos la tecnología según el objetivo, el contexto y el mantenimiento.</li>
                        <li><strong style="color:#fff">Acompañamiento:</strong> desde la idea inicial hasta la implementación y evolución.</li>
                    </ul>
                    <hr>
                    <a href="/desamedida.php">Ver desarrollo a medida &rarr;</a>
                </article>
            </div>
        </div>
    </section>

    <section class="sec" id="contacto">
        <div class="wrap">
            <div class="cta reveal">
                <span class="eyebrow">¿Tienes un proyecto en mente?</span>
                <h2 class="bebas">CONVERTIMOS TU IDEA EN UNA SOLUCIÓN TECNOLÓGICA.</h2>
                <p>Conversemos sobre lo que necesitas. Te ayudamos a definir el alcance, la mejor aproximación y los próximos pasos para construirlo.</p>
                <div class="hero-acciones">
                    <a class="btn btn-verde" href="/desamedida.php"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> Solicitar software a medida</a>
                    <a class="btn btn-ghost" href="<?= $base ?>/contacto.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contactar con UnicornioSoftware</a>
                    <a class="btn-outline-fuego" href="/index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Regresar a la página principal</a>
                </div>
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
            <span>© <?= date('Y') ?> UnicornioSoftware Cuba. Todos los derechos reservados.</span>
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
            }, { threshold: 0.15 });
            document.querySelectorAll('.reveal').forEach(function (el) { observer.observe(el); });
        });
/* Botones flotantes: arriba / abajo según la posición de lectura */
(function () {
    var arriba = document.getElementById('btnScrollTop');
    var abajo = document.getElementById('btnScrollBottom');
    var progreso = document.getElementById('scrollProgress');
    if (!arriba && !abajo) { return; }
    function irArriba() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function irAbajo() {
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        window.scrollTo({ top: max, behavior: 'smooth' });
    }
    if (arriba) { arriba.addEventListener('click', irArriba); }
    if (abajo) { abajo.addEventListener('click', irAbajo); }
    function actualizar() {
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        if (arriba) { arriba.classList.toggle('is-visible', y > 140); }
        if (abajo) { abajo.classList.toggle('is-visible', y < max - 140); }
        if (progreso) { progreso.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%'; }
    }
    window.addEventListener('scroll', actualizar, { passive: true });
    window.addEventListener('resize', actualizar);
    actualizar();
})();
/* Menú móvil */
(function () {
    var btn = document.getElementById('menuBtn');
    var menu = document.getElementById('menuMovil');
    if (!btn || !menu) { return; }
    btn.addEventListener('click', function () {
        var abierto = menu.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        btn.innerHTML = abierto ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
    });
    menu.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        }
    });
})();
/* Desplegable de acceso a los servicios (topbar) */
(function () {
    var cont = document.getElementById('navLogin');
    var btn = document.getElementById('navLoginBtn');
    if (!cont || !btn) { return; }
    function cerrar() { cont.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var abierto = cont.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) { if (!cont.contains(e.target)) { cerrar(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' || e.key === 'Esc') { cerrar(); } });
})();
/* Brillos sobre las tarjetas: el resplandor sigue al puntero. */
(function () {
    var tarjetas = document.querySelectorAll('[data-spotlight]');
    function mover(e) {
        var r = this.getBoundingClientRect();
        this.style.setProperty('--pointer-x', (e.clientX - r.left) + 'px');
        this.style.setProperty('--pointer-y', (e.clientY - r.top) + 'px');
    }
    for (var i = 0; i < tarjetas.length; i++) { tarjetas[i].addEventListener('pointermove', mover); }
})();
/* Modal de perfiles del equipo */
(function () {
    var abrirBtns = document.querySelectorAll('[data-maestro-open]');
    var cerrarBtns = document.querySelectorAll('[data-maestro-close]');
    function abrir(id) {
        var modal = document.getElementById(id);
        if (!modal) { return; }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function cerrar(modal) {
        if (!modal) { return; }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    abrirBtns.forEach(function (btn) {
        var id = btn.getAttribute('data-maestro-open');
        btn.addEventListener('click', function () { abrir(id); });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(id); }
        });
    });
    cerrarBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { cerrar(btn.closest('.maestro-modal')); });
    });
    document.querySelectorAll('.maestro-modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) { cerrar(modal); } });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            document.querySelectorAll('.maestro-modal.is-open').forEach(cerrar);
        }
    });
})();
/* Enlaces del topbar a las secciones de esta página */
(function () {
    var enlaces = document.querySelectorAll('nav.nav a[href^="#"]');
    enlaces.forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            var id = this.getAttribute('href').slice(1);
            var destino = id ? document.getElementById(id) : null;
            if (!destino) { return; }
            e.preventDefault();
            var menu = document.getElementById('menuMovil');
            var menuBtn = document.getElementById('menuBtn');
            if (menu) { menu.classList.remove('is-open'); }
            if (menuBtn) { menuBtn.setAttribute('aria-expanded', 'false'); menuBtn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>'; }
            var yAntes = window.scrollY || document.documentElement.scrollTop || 0;
            destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + id);
            window.setTimeout(function () {
                var yAhora = window.scrollY || document.documentElement.scrollTop || 0;
                if (Math.abs(yAhora - yAntes) < 5 && Math.abs(destino.getBoundingClientRect().top - 92) > 8) {
                    document.documentElement.style.scrollBehavior = 'auto';
                    destino.scrollIntoView({ behavior: 'auto', block: 'start' });
                    document.documentElement.style.scrollBehavior = '';
                }
            }, 700);
        });
    });
})();
    </script>
</body>

</html>