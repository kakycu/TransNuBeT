<?php
// solic_userinfo/index.php - Consulta de salario protegida por CLAVE.
// Antes de entrar exige una clave que debe existir en el sistema
// (contraseñas de clasif_usuarios activos, verificadas por api.php).
// El reporte se muestra en una ventana con barra de títulos y X a la derecha
// que NO se cierra al hacer clic fuera.
// Tema visual: MAR (azul profundo) con acento CYAN, barra lateral izquierda y
// el footer corporativo del sistema fijo abajo.

require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$su_autorizado = !empty($_SESSION['su_acceso']);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>

<!DOCTYPE html>
<html lang="es" data-theme="blue">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Consulta de Salario - <?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon_tn_64.png?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon_tn_32.png?v=1">
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <script src="../js/sweetalert2.all.min.js"></script>
    <script src="../js/pdfmake.min.js"></script>
    <script src="../js/vfs_fonts.js"></script>
    <style>
        :root {
            /* ===== Tema MAR (paleta del tema "blue" del sistema) ===== */
            --mar-bg: #152540;
            --mar-texto: #dce8ff;
            --mar-texto-suave: rgba(180, 210, 255, 0.65);
            --mar-superficie: rgba(12, 22, 42, 0.97);
            --mar-tarjeta: rgba(16, 30, 56, 0.72);
            --mar-borde: rgba(130, 190, 255, 0.14);
            --mar-hover: rgba(10, 20, 40, 0.5);

            /* ===== Color de énfasis: CYAN ===== */
            --accent: #06b6d4;
            --accent-rgb: 6, 182, 212;
            --accent-dark: #0891b2;
            --accent-light: #67e8f9;
            --accent-bg: rgba(6, 182, 212, 0.12);

            /* ===== Barra lateral (variables win-sidebar del tema blue) ===== */
            --sb-ancho: 15rem;
            --win-sidebar-bg: rgba(12, 22, 42, 0.97);
            --win-sidebar-border: rgba(130, 190, 255, 0.12);
            --win-sidebar-text: rgba(180, 210, 255, 0.78);
            --win-sidebar-text-dim: rgba(130, 190, 255, 0.5);
            --win-sidebar-item-bg: rgba(130, 190, 255, 0.05);
            --win-sidebar-item-border: rgba(130, 190, 255, 0.08);

            --exito: #34d399;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            background: var(--mar-bg);
            color: var(--mar-texto);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 15.5rem; /* espacio para el footer fijo */
        }

        /* ===================== BARRA LATERAL IZQUIERDA ===================== */
        .su-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 4500;
            width: var(--sb-ancho);
            display: flex; flex-direction: column;
            background: var(--win-sidebar-bg);
            border-right: 0.0625rem solid var(--win-sidebar-border);
            backdrop-filter: blur(0.9375rem);
            overflow-y: auto;
        }
        .su-side-logo {
            display: flex; align-items: center; justify-content: center;
            padding: 1.1rem 0.9rem;
            border-bottom: 0.0625rem solid var(--win-sidebar-border);
        }
        .su-side-logo img { height: 3rem; max-width: 100%; object-fit: contain; }
        .su-side-marca {
            text-align: center; font-size: 0.68rem; letter-spacing: 0.06em;
            color: var(--win-sidebar-text-dim); text-transform: uppercase;
            padding: 0.5rem 0.75rem 0.25rem;
        }
        .su-side-cat {
            font-size: 0.66rem; font-weight: 700; letter-spacing: 0.09em;
            text-transform: uppercase; color: var(--win-sidebar-text-dim);
            padding: 1rem 1.1rem 0.4rem;
        }
        .su-side-nav { display: flex; flex-direction: column; gap: 0.2rem; padding: 0 0.55rem 1rem; }
        .su-side-nav a {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.55rem 0.7rem; border-radius: 0.5rem;
            color: var(--win-sidebar-text); text-decoration: none; font-size: 0.82rem;
            background: var(--win-sidebar-item-bg);
            border: 0.0625rem solid var(--win-sidebar-item-border);
            transition: all 0.2s ease;
        }
        .su-side-nav a i { width: 1.1rem; text-align: center; color: var(--accent-light); transition: color 0.2s ease; }
        .su-side-nav a:hover {
            background: var(--mar-hover); border-color: rgba(var(--accent-rgb), 0.35);
            color: var(--accent-light); transform: translateX(0.15rem);
        }
        .su-side-nav a:hover i { color: var(--accent); }
        .su-side-pie {
            margin-top: auto; padding: 0.85rem 1rem;
            border-top: 0.0625rem solid var(--win-sidebar-border);
            font-size: 0.66rem; color: var(--win-sidebar-text-dim); text-align: center;
        }
        .su-side-sep {
            height: 0.0625rem; margin: 0.7rem 0.55rem 0.6rem;
            background: linear-gradient(90deg, transparent, rgba(239,68,68,0.45), transparent);
        }
        .su-side-salir {
            display: flex; align-items: center; gap: 0.65rem; width: calc(100% - 1.1rem);
            margin: 0 0.55rem; padding: 0.55rem 0.7rem; border-radius: 0.5rem;
            font-size: 0.82rem; font-family: inherit; text-align: left;
            color: #fca5a5; background: rgba(239,68,68,0.08);
            border: 0.0625rem solid rgba(239,68,68,0.25); cursor: pointer;
            transition: all 0.2s ease;
        }
        .su-side-salir i { width: 1.1rem; text-align: center; color: #f87171; transition: color 0.2s ease; }
        .su-side-salir:hover { background: rgba(239,68,68,0.22); border-color: rgba(239,68,68,0.5); color:#fff; transform: translateX(0.15rem); }

        /* ===================== CONTENIDO PRINCIPAL ===================== */
        .su-contenido { margin-left: var(--sb-ancho); padding: 1.5rem 1.25rem 1rem; }
        .su-contenedor { max-width: 68rem; margin: 0 auto; }

        /* Encabezado de página (estilo cabecera del modal original) */
        .su-cabecera {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.25rem; margin-bottom: 1.25rem;
            border: 0.0625rem solid var(--mar-borde); border-radius: 1rem;
            background: linear-gradient(135deg, rgba(var(--accent-rgb), 0.16) 0%, rgba(var(--accent-rgb), 0.05) 100%);
        }
        .su-cabecera p.su-cabecera-empresa {
            margin: 0.15rem 0 0; font-size: 0.98rem; font-weight: 700;
            color: var(--accent-light); letter-spacing: 0.03em;
        }
        .su-cabecera p.su-cabecera-slogan {
            margin: 0.05rem 0 0.2rem; font-size: 0.79rem; font-style: italic;
            color: var(--mar-texto-suave);
        }
        .su-cabecera-icono {
            width: 3rem; height: 3rem; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            border-radius: 0.875rem; box-shadow: 0 0.25rem 0.875rem rgba(var(--accent-rgb), 0.35);
        }
        .su-cabecera h1 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #fff; }
        .su-cabecera p { margin: 0; font-size: 0.85rem; color: var(--mar-texto-suave); }

        /* ===== Reloj analógico plano + hora digital + fecha ===== */
        .su-reloj {
            display: flex; align-items: center; gap: 0.95rem;
            margin-left: auto; padding-left: 1.1rem; flex: 0 0 auto;
        }
        .su-reloj-analog {
            width: 5rem; height: 5rem;
            filter: drop-shadow(0 0.3rem 0.55rem rgba(0,0,0,0.38));
        }
        .su-reloj-texto { display: flex; flex-direction: column; gap: 0.2rem; }
        .su-reloj-hora {
            font-size: 1.32rem; font-weight: 700; color: #fff;
            letter-spacing: 0.04em; font-variant-numeric: tabular-nums;
            display: flex; align-items: baseline; gap: 0.4rem;
        }
        .su-reloj-hora i { color: var(--accent-light); font-size: 1rem; align-self: center; }
        .su-reloj-hora small { font-size: 0.68rem; font-weight: 600; color: var(--accent-light); letter-spacing: 0.08em; }
        .su-reloj-fecha { font-size: 0.79rem; color: var(--mar-texto-suave); }
        .su-reloj-fecha i { color: var(--accent-light); margin-right: 0.3rem; }

        /* Logo sigesnom bajo el texto de consulta pública (compacto, sin scroll) */
        .su-logo-central { display: flex; justify-content: center; margin: 0.7rem auto 0; }
        .su-logo-central img {
            width: min(20rem, 68vw); height: auto;
            filter: drop-shadow(0 0.35rem 0.7rem rgba(0,0,0,0.4));
        }

        .su-tarjeta {
            border: 0.0625rem solid var(--mar-borde); border-radius: 1rem;
            background: var(--mar-tarjeta); padding: 1.25rem;
        }
        .su-fila { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .su-campo { flex: 1 1 14rem; min-width: 12rem; }
        .su-campo label { display: block; margin-bottom: 0.25rem; font-size: 0.8rem; color: rgba(220,232,255,0.85); }
        .su-campo label i { color: var(--accent-light); margin-right: 0.25rem; }

        .su-input, .su-select {
            width: 100%; padding: 0.45rem 0.625rem; font-size: 0.875rem;
            color: #fff; outline: none;
            background: rgba(10, 20, 40, 0.6);
            border: 0.0625rem solid rgba(130, 190, 255, 0.3); border-radius: 0.375rem;
        }
        .su-input:focus, .su-select:focus { border-color: var(--accent); box-shadow: 0 0 0 0.1875rem rgba(var(--accent-rgb), 0.18); }
        .su-select option { background: #0c162a; color: #fff; }
        .su-select:disabled { opacity: 0.55; cursor: not-allowed; }

        .su-buscador { position: relative; }
        .su-buscador-grupo { display: flex; }
        .su-buscador-grupo .su-adorno {
            display: flex; align-items: center; padding: 0 0.625rem;
            background: rgba(var(--accent-rgb), 0.15); border: 0.0625rem solid rgba(130, 190, 255, 0.3);
            border-right: none; border-radius: 0.375rem 0 0 0.375rem; color: var(--accent-light);
        }
        .su-buscador-grupo .su-input { border-radius: 0 0.375rem 0.375rem 0; }
        .su-btn-limpiar {
            padding: 0 0.7rem; cursor: pointer; color: #fca5a5;
            background: rgba(239,68,68,0.2); border: 0.0625rem solid rgba(239,68,68,0.3);
            border-left: none; border-radius: 0 0.375rem 0.375rem 0;
        }
        .su-btn-desplegar {
            padding: 0 0.7rem; cursor: pointer; color: var(--accent-light);
            background: rgba(var(--accent-rgb), 0.18); border: 0.0625rem solid rgba(var(--accent-rgb), 0.35);
            border-left: none; transition: background 0.2s;
        }
        .su-btn-desplegar:hover { background: rgba(var(--accent-rgb), 0.38); color: #fff; }
        .su-resultados {
            position: absolute; top: calc(100% + 0.25rem); left: 0; right: 0; z-index: 1100;
            max-height: 15.625rem; overflow-y: auto; display: none;
            background: #0c162a; border: 0.0625rem solid rgba(130, 190, 255, 0.3); border-radius: 0.375rem;
        }
        .su-resultados a {
            display: block; padding: 0.45rem 0.75rem; color: #fff;
            text-decoration: none; font-size: 0.85rem; cursor: pointer;
        }
        .su-resultados a:hover { background: rgba(var(--accent-rgb), 0.15); color: var(--accent-light); }
        .su-resultados small { color: var(--mar-texto-suave); }
        .su-info-trabajador { margin-top: 0.4rem; font-size: 0.8rem; color: var(--mar-texto-suave); }
        .su-info-trabajador strong { color: #fff; }

        .su-btn-generar {
            flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.55rem 1.1rem; font-size: 0.875rem; font-weight: 600; color: #fff; cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.25rem 0.875rem rgba(var(--accent-rgb), 0.3);
        }
        .su-btn-generar:hover { filter: brightness(1.15); transform: translateY(-0.0625rem); }
        .su-btn-generar:disabled { opacity: 0.45; cursor: not-allowed; filter: none; transform: none; box-shadow: none; }

        /* SweetAlert siempre por delante del portal de acceso, la ventana-modal y los ISO */
        .swal2-container { z-index: 99999 !important; }

        /* ===================== VENTANA-MODAL ===================== */
        .su-overlay {
            position: fixed; inset: 0; z-index: 7000; display: none;
            background: rgba(2, 6, 23, 0.78); backdrop-filter: blur(0.2rem);
        }
        .su-overlay.abierta { display: block; }
        .su-ventana {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
            width: min(68rem, 94vw); height: min(52rem, 88vh);
            display: flex; flex-direction: column;
            background: var(--mar-superficie); border: 0.0625rem solid rgba(255,255,255,0.14);
            border-radius: 0.75rem; overflow: hidden;
            box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.65);
        }
        .su-barra-titulos {
            display: flex; align-items: center; gap: 0.75rem; flex: 0 0 auto;
            padding: 0.5rem 0.875rem; cursor: move; user-select: none;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            border-bottom: 0.0625rem solid rgba(255,255,255,0.18);
        }
        /* Botón X pegado al EXTREMO derecho de la barra (final del modal) */
        .su-btn-x {
            order: 10; margin-left: auto;
            width: 1.75rem; height: 1.75rem; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; color: #fff; cursor: pointer;
            background: rgba(255, 255, 255, 0.16); border: none;
            border-radius: 0.375rem; transition: background 0.2s;
        }
        .su-btn-x:hover { background: #ef4444; color: #fff; }
        .su-titulo-icono {
            width: 2.25rem; height: 2.25rem; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.14); border-radius: 0.625rem; color: #fff;
        }
        .su-titulos h5 { margin: 0; font-size: 1.02rem; font-weight: 700; color: #fff; }
        .su-titulos p { margin: 0; font-size: 0.73rem; color: rgba(255,255,255,0.75); }

        .su-ventana-cuerpo { flex: 1 1 auto; overflow-y: auto; padding: 1.25rem; }
        .su-pie {
            flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center;
            gap: 0.75rem; padding: 0.75rem 1rem; flex-wrap: wrap;
            border-top: 0.0625rem solid var(--mar-borde); background: rgba(0,0,0,0.28);
        }
        .su-pie-grupo { display: flex; gap: 0.5rem; position: relative; }

        .su-btn {
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.5rem 0.9rem; font-size: 0.82rem; font-weight: 600;
            color: var(--mar-texto); cursor: pointer;
            background: rgba(130,190,255,0.1); border: 0.0625rem solid rgba(130,190,255,0.3);
            border-radius: 0.5rem;
        }
        .su-btn:hover { background: var(--mar-hover); border-color: var(--accent); color: var(--accent-light); }
        .su-btn-primario { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-color: transparent; color: #fff; }
        .su-btn-primario:hover { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); filter: brightness(1.15); color: #fff; }
        .su-btn-exito { background: linear-gradient(135deg, #059669, #047857); border-color: transparent; color: #fff; }
        .su-btn-exito:hover { background: linear-gradient(135deg, #059669, #047857); filter: brightness(1.15); color: #fff; }

        .su-dropdown { position: relative; }
        .su-dropdown-menu {
            display: none; position: absolute; right: 0; bottom: calc(100% + 0.3rem); z-index: 7100;
            min-width: 13rem; padding: 0.3rem 0;
            background: #0c162a; border: 0.0625rem solid var(--mar-borde); border-radius: 0.5rem;
            box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.5);
        }
        .su-dropdown-menu.abierto { display: block; }
        .su-dropdown-menu a {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.45rem 0.9rem; color: var(--mar-texto); text-decoration: none; font-size: 0.82rem;
        }
        .su-dropdown-menu a:hover { background: rgba(var(--accent-rgb), 0.15); color: var(--accent-light); }

        /* ===== Resultados (mismo aspecto que el modal original) ===== */
        .su-centrado { text-align: center; margin-bottom: 0.9rem; }
        .su-centrado h4 { margin: 0 0 0.25rem; letter-spacing: 0.02em; color: #fff; }
        .su-centrado .empresa { margin: 0 0 0.4rem; color: var(--mar-texto-suave); font-size: 0.85rem; }
        .su-meta { display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem; }
        .su-tabla-scroll { overflow-x: auto; }
        table.su-tabla { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.su-tabla thead th {
            position: sticky; top: 0; background: #12233f; z-index: 1;
            padding: 0.55rem 0.75rem; text-align: right; color: var(--accent-light); font-weight: 600;
            border-bottom: 0.0625rem solid var(--mar-borde);
        }
        table.su-tabla thead th:first-child { text-align: left; }
        table.su-tabla td { padding: 0.5rem 0.75rem; border-bottom: 0.0625rem solid rgba(130,190,255,0.07); text-align: right; }
        table.su-tabla td:first-child { text-align: left; }
        table.su-tabla tbody tr:hover { background: var(--mar-hover); }
        tr.su-sep-tipo td { background: rgba(var(--accent-rgb), 0.12) !important; font-weight: 700; }
        .su-badge {
            display: inline-block; padding: 0.25rem 0.625rem; border-radius: 0.75rem;
            font-size: 0.72rem; font-weight: 600; white-space: normal;
        }
        .su-neto { font-weight: 700; color: var(--exito); }
        tfoot tr td { background: rgba(52,211,153,0.1); font-weight: 700; color: var(--exito); padding: 0.55rem 0.75rem; text-align: right; }
        tfoot tr td:first-child { text-align: left; color: #fff; }
        .sin-datos { text-align: center; color: var(--mar-texto-suave); padding: 1rem 0; }

        /* ===================== FOOTER CORPORATIVO FIJO ===================== */
        .su-corp-footer {
            --corp-bg: rgba(10, 16, 30, 0.92);
            --corp-border: rgba(130, 190, 255, 0.1);
            --corp-bar-bg: rgba(10, 20, 40, 0.4);
            --corp-bar-border: rgba(130, 190, 255, 0.08);
            --corp-bottom-bg: rgba(0, 0, 0, 0.18);
            --corp-bottom-border: rgba(130, 190, 255, 0.07);
            --corp-copy-bg: rgba(0, 0, 0, 0.3);
            --corp-text-strong: #f1f5f9;
            --corp-text-muted: #8fa3c4;
            --corp-accent: var(--accent);
            --corp-accent-2: var(--accent-light);
            --corp-icon-bg: rgba(var(--accent-rgb), 0.08);
            --corp-icon-border: rgba(var(--accent-rgb), 0.18);
            --corp-icon-hover-bg: rgba(var(--accent-rgb), 0.18);
            --corp-icon-hover-border: var(--accent);
            --corp-separator: rgba(143, 163, 196, 0.35);
            position: fixed; left: var(--sb-ancho); right: 0; bottom: 0; z-index: 4200;
            background: var(--corp-bg) !important;
            backdrop-filter: blur(0.9375rem); -webkit-backdrop-filter: blur(0.9375rem);
            border-top: 0.0625rem solid var(--corp-border) !important;
            border-radius: 1rem 1rem 0 0;
            overflow: hidden;
        }
        .corp-bar { padding: 0.85rem 1.25rem; background: var(--corp-bar-bg); border-bottom: 0.0625rem solid var(--corp-bar-border); }
        .corp-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(12.5rem, 1fr));
            gap: 0.9rem; align-items: center;
        }
        .corp-item { display: flex; align-items: center; gap: 0.7rem; cursor: pointer; }
        .corp-icono {
            width: 2.4rem; height: 2.4rem; flex: 0 0 auto;
            background: var(--corp-icon-bg); border: 0.0625rem solid var(--corp-icon-border);
            border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; color: var(--corp-accent); transition: all 0.3s ease;
        }
        .corp-item:hover .corp-icono {
            background: var(--corp-icon-hover-bg); border-color: var(--corp-icon-hover-border);
            transform: scale(1.06); box-shadow: 0 0 0.9375rem rgba(var(--accent-rgb), 0.25);
        }
        .corp-info { display: flex; flex-direction: column; }
        .corp-info strong { font-size: 0.72rem; font-weight: 600; color: var(--corp-text-strong); letter-spacing: 0.0188rem; text-transform: uppercase; }
        .corp-info span { font-size: 0.66rem; color: var(--corp-text-muted); margin-top: 0.0625rem; }
        .corp-bottom {
            padding: 0.7rem 1.25rem; background: var(--corp-bottom-bg);
            border-bottom: 0.0625rem solid var(--corp-bottom-border);
            display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.9rem; align-items: center;
        }
        .corp-persona { display: flex; align-items: center; gap: 0.55rem; }
        .corp-persona.der { justify-content: flex-end; text-align: right; }
        .corp-persona strong { color: var(--corp-text-strong); font-size: 0.82rem; }
        .corp-persona small { color: var(--corp-text-muted); font-size: 0.68rem; }
        .corp-persona i { color: var(--corp-accent); font-size: 1.05rem; }
        .corp-persona i.violeta { color: var(--corp-accent-2); }
        .corp-insignia {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.3125rem 0.875rem; background: rgba(var(--accent-rgb), 0.1);
            border-radius: 6.25rem; font-size: 0.7rem; font-weight: 600;
            color: var(--corp-accent); border: 0.0625rem solid rgba(var(--accent-rgb), 0.25);
        }
        .corp-copy {
            padding: 0.55rem 1.25rem; background: var(--corp-copy-bg);
            font-size: 0.7rem; color: var(--corp-text-muted);
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.6rem;
        }
        .corp-copy-logo { display: flex; align-items: center; gap: 0.5rem; }
        .corp-copy-logo img { filter: brightness(0) invert(1); opacity: 0.92; }
        .corp-copy-logo strong { color: var(--corp-text-muted); font-weight: 600; }
        .corp-links { display: flex; align-items: center; gap: 0.75rem; }
        .corp-link { color: var(--corp-text-muted); text-decoration: none; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 0.25rem; transition: color 0.2s; }
        .corp-link:hover { color: var(--corp-accent); }

        /* Modal informativo del footer (ISO 27001, etc.) */
        .iso-overlay {
            position: fixed; inset: 0; background: rgba(2, 6, 23, 0.72);
            z-index: 9000; display: none; flex-direction: column;
            align-items: center; justify-content: center; padding: 1.25rem;
        }
        .iso-caja {
            background: #101c33; color: #dbe6f8; border-radius: 0.75rem;
            box-shadow: 0 1.25rem 3.75rem rgba(0, 0, 0, 0.6);
            max-width: 35rem; width: 100%; overflow: hidden;
            border: 0.0625rem solid rgba(130, 190, 255, 0.14);
        }
        .iso-barra {
            display: flex; align-items: center; gap: 0.625rem; padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-bottom: 0.0625rem solid rgba(255,255,255,0.12);
        }
        .iso-barra .tt-icono { font-size: 1.125rem; color: #fff; }
        .iso-barra .tt-texto { font-size: 0.9375rem; font-weight: 700; color: #fff; flex: 1; }
        .iso-cerrar {
            width: 1.875rem; height: 1.875rem; border: none; cursor: pointer;
            background: rgba(255,255,255,0.14); color: #fff; font-size: 1.125rem;
            line-height: 1; border-radius: 0.5rem; transition: all 0.2s;
        }
        .iso-cerrar:hover { background: #ef4444; }
        .iso-cuerpo { padding: 1.5rem 1.75rem 1.625rem; text-align: left; }
        .iso-cuerpo .iso-empresa { font-size: 1.0625rem; font-weight: 700; color: #fff; line-height: 1.35; margin-bottom: 0.375rem; }
        .iso-cuerpo .iso-empresa i { color: var(--accent-light); margin-right: 0.5rem; }
        .iso-cuerpo .iso-sub { font-size: 0.8125rem; color: #93a8cc; margin-bottom: 1.125rem; }
        .iso-scroll { max-height: 18.75rem; overflow-y: auto; padding-right: 0.375rem; }
        .iso-scroll h4 { font-size: 0.9062rem; font-weight: 700; color: var(--accent-light); margin: 0.875rem 0 0.375rem; }
        .iso-scroll h4:first-child { margin-top: 0; }
        .iso-scroll p { font-size: 0.8125rem; color: #c3d2ec; line-height: 1.55; margin-bottom: 0.625rem; }
        .iso-acciones {
            display: flex; gap: 0.625rem; justify-content: flex-end; margin-top: 1.125rem;
            padding-top: 0.875rem; border-top: 0.0625rem solid rgba(130,190,255,0.12);
        }
        .iso-btn-ok {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff;
            border: none; border-radius: 0.5rem; padding: 0.625rem 1.5rem;
            font-size: 0.8438rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .iso-btn-ok:hover { transform: translateY(-0.0625rem); box-shadow: 0 0.375rem 1.125rem rgba(var(--accent-rgb), 0.4); }

        /* ===================== PORTAL DE ACCESO POR CLAVE ===================== */
        .su-gate-overlay {
            position: fixed; inset: 0; z-index: 9500;
            display: none; align-items: center; justify-content: center; padding: 1rem;
            /* Translúcido: la página se ve detrás, atenuada y bloqueada */
            background:
                radial-gradient(circle at 15% 15%, rgba(6,182,212,.12), transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(6,182,212,.07), transparent 45%),
                linear-gradient(160deg, rgba(11,23,48,.80) 0%, rgba(21,37,64,.74) 45%, rgba(2,6,23,.82) 100%);
            backdrop-filter: blur(0.1875rem);
            -webkit-backdrop-filter: blur(0.1875rem);
        }
        .su-gate-overlay.abierta { display: flex; }
        .gate-caja {
            width: min(26rem, 94vw);
            background: rgba(12, 22, 42, 0.92);
            border: 0.0625rem solid rgba(6,182,212,0.35);
            border-radius: 1rem;
            box-shadow: 0 1.5rem 3.5rem rgba(0,0,0,0.6), 0 0 2rem rgba(6,182,212,0.08);
            padding: 2rem 1.75rem 1.75rem;
            text-align: center;
        }
        .gate-icono {
            width: 3.6rem; height: 3.6rem; margin: 0 auto 1rem;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-radius: 50%;
            box-shadow: 0 0.4rem 1.2rem rgba(var(--accent-rgb), 0.4);
            font-size: 1.4rem; color: #fff;
        }
        .gate-caja h1 { margin: 0 0 0.25rem; font-size: 1.18rem; color: #fff; }
        .gate-caja h2 { margin: 0 0 0.35rem; font-size: 0.92rem; font-weight: 600; color: var(--accent-light); letter-spacing: 0.03em; }
        .gate-caja .gate-slogan { margin: 0 0 1.35rem; font-size: 0.78rem; font-style: italic; color: rgba(180,210,255,0.55); }
        .gate-alerta {
            margin: -0.55rem 0 1.15rem;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            animation: gateFadeColores 2.6s ease-in-out infinite;
        }
        @keyframes gateFadeColores {
            0%, 100% { color: #facc15; text-shadow: 0 0 0.75rem rgba(250,204,21,0.35); }
            50%      { color: #38bdf8; text-shadow: 0 0 0.75rem rgba(56,189,248,0.35); }
        }
        .gate-campo { position: relative; margin-bottom: 1rem; text-align: left; }
        .gate-campo label { display: block; font-size: 0.8rem; margin-bottom: 0.3rem; color: rgba(220,232,255,0.85); }
        .gate-campo input {
            width: 100%; padding: 0.65rem 2.7rem 0.65rem 0.8rem; font-size: 0.95rem;
            color: #fff; outline: none; letter-spacing: 0.12em;
            background: rgba(10, 20, 40, 0.6);
            border: 0.0625rem solid rgba(130, 190, 255, 0.3); border-radius: 0.5rem;
        }
        .gate-campo input:focus { border-color: var(--accent); box-shadow: 0 0 0 0.1875rem rgba(var(--accent-rgb), 0.18); }
        .gate-ojo {
            position: absolute; right: 0.5rem; bottom: 0.45rem;
            width: 2rem; height: 2rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            background: transparent; border: none; color: rgba(180,210,255,0.6);
        }
        .gate-ojo:hover { color: var(--accent-light); }
        .gate-botones { display: flex; gap: 0.625rem; }
        .gate-botones .gate-btn { flex: 1; width: auto; }
        button.gate-btn.gate-btn-inicio,
        button.gate-btn.gate-btn-inicio:hover {
            background: #22c55e;
            box-shadow: 0 0.35rem 1rem rgba(34,197,94,0.30);
        }
        button.gate-btn.gate-btn-inicio:hover { background: #16a34a; filter: none; }
        .gate-btn {
            width: 100%; padding: 0.7rem 1rem; font-size: 0.95rem; font-weight: 700;
            color: #fff; cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.35rem 1rem rgba(var(--accent-rgb), 0.35);
            transition: filter 0.2s, transform 0.1s;
        }
        .gate-btn:hover { filter: brightness(1.12); }
        .gate-btn:active { transform: translateY(0.05rem); }
        .gate-btn[disabled] { opacity: 0.65; cursor: wait; }
        .gate-nota { margin-top: 1.1rem; font-size: 0.78rem; color: #ffffff; opacity: 1; line-height: 1.55; }
        .gate-nota i { color: var(--accent-light); margin-right: 0.25rem; }

        /* ===================== RESPONSIVE (PC / TABLETA / MÓVIL) ===================== */
        /* Botón hamburguesa y fondo oscuro del menú móvil */
        .su-btn-menu {
            display: none; position: fixed; top: 0.7rem; left: 0.7rem; z-index: 4600;
            width: 2.6rem; height: 2.6rem; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff; border: none; border-radius: 0.625rem;
            box-shadow: 0 0.35rem 1rem rgba(0,0,0,0.45); cursor: pointer; font-size: 1.05rem;
        }
        .su-sidebar-fondo {
            display: none; position: fixed; inset: 0; z-index: 4400;
            background: rgba(2, 6, 23, 0.6);
        }

        @media (max-width: 64rem) {
            :root { --sb-ancho: 12.5rem; }
            .su-contenido { padding: 1.25rem 1rem 1rem; }
            .su-ventana { height: min(52rem, 90vh); }
        }

        @media (max-width: 48rem) {
            :root { --sb-ancho: 0rem; }
            body { padding-bottom: 27rem; padding-top: 4.3rem; }
            .su-btn-menu { display: flex; }
            /* La barra lateral se convierte en cajón deslizante */
            .su-sidebar {
                position: fixed; top: 0; bottom: 0; left: -17rem;
                width: min(15rem, 82vw); transition: left 0.28s ease;
                box-shadow: 0.5rem 0 2rem rgba(0,0,0,0.55);
            }
            .su-sidebar.abierta { left: 0; }
            .su-sidebar-fondo.abierto { display: block; }
            .su-contenido { margin-left: 0; padding: 1.1rem 0.85rem 0.5rem; }
            .su-corp-footer { left: 0; border-radius: 0; }
            .corp-grid { grid-template-columns: 1fr; }
            .corp-bottom { grid-template-columns: 1fr; text-align: center; }
            .corp-persona, .corp-persona.der { justify-content: center; text-align: center; }
            .corp-copy { justify-content: center; text-align: center; }
            /* Pie MINIMIZADO en móvil (no interfiere con el contenido) */
            .corp-min-btn { display: none; }
            body.su-movil .corp-min-btn {
                display: flex; align-items: center; justify-content: center;
                position: absolute; top: 0.4rem; right: 0.55rem;
                width: 1.9rem; height: 1.9rem; cursor: pointer;
                background: rgba(6,182,212,0.14);
                border: 0.0625rem solid rgba(6,182,212,0.35); border-radius: 50%;
                color: #67e8f9; font-size: 0.72rem; z-index: 2;
                transition: transform 0.25s ease, background 0.2s ease;
            }
            body.su-movil .corp-min-btn i { transition: transform 0.25s ease; }
            body.su-movil .su-corp-footer.su-footer-abierto .corp-min-btn i { transform: rotate(180deg); }
            body.su-movil .su-corp-footer:not(.su-footer-abierto) .corp-bar,
            body.su-movil .su-corp-footer:not(.su-footer-abierto) .corp-bottom { display: none; }
            body.su-movil .su-corp-footer:not(.su-footer-abierto) { padding-bottom: 0.15rem; }
            /* Ventana-modal a pantalla completa en móvil */
            .su-ventana { width: 98vw; height: 94vh; border-radius: 0; }
            .su-ventana-cuerpo { padding: 0.85rem; }
            .su-cabecera { flex-direction: column; text-align: center; align-items: center; gap: 0.6rem; }
            .su-reloj { margin: 0.65rem auto 0; padding-left: 0; }
            .su-meta { gap: 0.8rem; }
            .su-logo-central img { width: min(11rem, 55vw); }
        }

        @media (max-width: 30rem) {
            .su-cabecera h1 { font-size: 1.02rem; }
            .su-btn-generar { width: 100%; justify-content: center; }
            .su-pie { flex-direction: column; align-items: stretch; }
            .su-pie-grupo { justify-content: center; flex-wrap: wrap; }
            table.su-tabla { font-size: 0.78rem; }
            table.su-tabla thead th, table.su-tabla td { padding: 0.4rem 0.5rem; }
            .corp-info strong { font-size: 0.68rem; }
            .iso-cuerpo { padding: 1.1rem 1.1rem 1.2rem; }
        }
    </style>
</head>
<body>

<!-- ===================== BARRA LATERAL IZQUIERDA ===================== -->
<button type="button" class="su-btn-menu" id="suBtnMenu" title="Menú" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
<div class="su-sidebar-fondo" id="suSidebarFondo"></div>
<aside class="su-sidebar" id="suSidebar">
    <div class="su-side-logo">
        <a href="../../index.php" title="Regresar al inicio"><img src="../../images/LogoTN.png" alt="Logo"></a>
    </div>
    <div class="su-side-marca"><?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="su-side-cat">Navegación</div>
    <nav class="su-side-nav">
        <a href="../index.php" data-tooltip-theme="info"><i class="fas fa-right-to-bracket"></i> Logearse en el sistema</a>
        <a href="../../index.php"><i class="fas fa-house"></i> Inicio</a>
        <a href="../../fcostoCharangon/index.php"><i class="fas fa-calculator"></i> Cálculo de la Ficha de Costo del Charangón</a>
		<a href="../../fcostoCharangon/index.php"><i class="fas fa-calculator"></i> Cálculo de la Ficha de Costo Papel Adhesivo Transparente A4</a>
        <div class="su-side-sep"></div>
        <button type="button" class="su-side-salir" id="suBtnSalir" title="Cerrar sesión y regresar al inicio"><i class="fas fa-right-from-bracket"></i> Salir / Cerrar</button>
    </nav>
    <div class="su-side-pie">
        <i class="fas fa-shield-halved"></i> Consulta pública<br>sin registro
    </div>
</aside>

<!-- ===================== CONTENIDO ===================== -->
<div class="su-contenido">
    <div class="su-contenedor">
        <!-- Cabecera de página -->
        <div class="su-cabecera">
            <div class="su-cabecera-icono"><i class="fas fa-user-tie fa-lg" style="color:#fff;"></i></div>
            <div>
                <h1>Listado de Salarios Devengados por Trabajador</h1>
                <p class="su-cabecera-empresa"><?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="su-cabecera-slogan"><?php echo htmlspecialchars(SLOGAN, ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Busque un trabajador y seleccione el mes o "Todos" para sumar el total del año por tipo de nómina</p>
            </div>
            <!-- Reloj analógico plano + hora digital + fecha -->
            <div class="su-reloj">
                <svg class="su-reloj-analog" viewBox="0 0 100 100" aria-hidden="true">
                    <circle cx="50" cy="50" r="47" fill="rgba(10,20,40,0.72)" stroke="#06b6d4" stroke-width="2.5"/>
                    <g id="suRelojTicks"></g>
                    <line id="suManecillaHora" x1="50" y1="52" x2="50" y2="29" stroke="#ffffff" stroke-width="4.5" stroke-linecap="round"/>
                    <line id="suManecillaMinuto" x1="50" y1="52" x2="50" y2="19" stroke="#67e8f9" stroke-width="3" stroke-linecap="round"/>
                    <line id="suManecillaSegundo" x1="50" y1="56" x2="50" y2="16" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="50" cy="50" r="3.2" fill="#06b6d4" stroke="#ffffff" stroke-width="1"/>
                </svg>
                <div class="su-reloj-texto">
                    <div class="su-reloj-hora" id="suRelojHora"><i class="fas fa-clock"></i> --:--:--</div>
                    <div class="su-reloj-fecha" id="suRelojFecha"><i class="fas fa-calendar-day"></i> Hoy es:</div>
                </div>
            </div>
        </div>

        <div class="su-tarjeta">
            <div class="su-fila">
                <!-- Búsqueda del trabajador -->
                <div class="su-campo" style="flex:2 1 18rem;">
                    <label for="listadoBuscarTrabajador"><i class="fas fa-user"></i> Nombre del Trabajador</label>
                    <div class="su-buscador">
                        <div class="su-buscador-grupo">
                            <span class="su-adorno"><i class="fas fa-search"></i></span>
                            <input type="text" id="listadoBuscarTrabajador" class="su-input" placeholder="Buscar por nombre, código o CI..." autocomplete="off">
                            <button type="button" id="listadoBtnLista" class="su-btn-desplegar" title="Mostrar lista de trabajadores"><i class="fas fa-chevron-down"></i></button>
                            <button type="button" id="listadoLimpiarTrabajador" class="su-btn-limpiar" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>
                        </div>
                        <div id="listadoResultadosBusqueda" class="su-resultados"></div>
                    </div>
                    <input type="hidden" id="listadoTrabajadorId" value="">
                    <div id="listadoTrabajadorInfo" class="su-info-trabajador"></div>
                </div>
                <!-- Año -->
                <div class="su-campo" style="flex:0 1 7.5rem;">
                    <label for="listadoAnio"><i class="fas fa-calendar-alt"></i> Año</label>
                    <select id="listadoAnio" class="su-select">
                        <option value="">-- Años --</option>
                    </select>
                </div>
                <!-- Estado -->
                <div class="su-campo" style="flex:0 1 9rem;">
                    <label for="listadoEstado"><i class="fas fa-circle-info"></i> Estado</label>
                    <select id="listadoEstado" class="su-select">
                        <option value="">Todos</option>
                        <option value="borrador">Borrador</option>
                        <option value="contabilizado">Contabilizado</option>
                    </select>
                </div>
                <!-- Modo -->
                <div class="su-campo" style="flex:0 1 10.5rem;">
                    <label for="listadoModo"><i class="fas fa-layer-group"></i> Modo</label>
                    <select id="listadoModo" class="su-select">
                        <option value="consolidado">Consolidado</option>
                        <option value="completo">Completo (por mes)</option>
                    </select>
                </div>
                <!-- Mes -->
                <div class="su-campo" style="flex:0 1 8.5rem;">
                    <label for="listadoMes"><i class="fas fa-calendar-day"></i> Mes</label>
                    <select id="listadoMes" class="su-select" disabled>
                        <option value="">-- Mes --</option>
                    </select>
                </div>
                <button type="button" id="btnGenerarListadoDevengado" class="su-btn-generar" disabled title="Generar reporte de devengado">
                    <i class="fas fa-chart-bar"></i><span>Generar Reporte</span>
                </button>
            </div>
        </div>

        <p style="text-align:center; color:rgba(180,210,255,0.45); font-size:0.78rem; margin-top:1rem;">
            <i class="fas fa-shield-halved"></i> Consulta pública de salario — no requiere usuario ni contraseña.
        </p>

        <!-- Logo sigesnom grande -->
        <div class="su-logo-central">
            <img src="../../images/sigesnom.png" alt="SisGesNom — Sistema de Gestión de Nómina">
        </div>
    </div>
</div>

<!-- ===================== FOOTER CORPORATIVO (FIJO) ===================== -->
<footer class="su-corp-footer">
    <button type="button" class="corp-min-btn" id="corpMinBtn" title="Mostrar / ocultar información del pie"><i class="fas fa-chevron-up"></i></button>
    <div class="corp-bar">
        <div class="corp-grid">
            <div class="corp-item" onclick="abrirModalISO27001()">
                <div class="corp-icono"><i class="fas fa-shield-halved"></i></div>
                <div class="corp-info">
                    <strong>Certificación NC-ISO/IEC 27001</strong>
                    <span>Seguridad de la información · Datos protegidos</span>
                </div>
            </div>
            <div class="corp-item" onclick="abrirModalFooter('infra')">
                <div class="corp-icono"><i class="fas fa-server"></i></div>
                <div class="corp-info">
                    <strong>Infraestructura Local 100%</strong>
                    <span>Sin conexión a servicios externos · Soberanía total</span>
                </div>
            </div>
            <div class="corp-item" onclick="abrirModalFooter('opensource')">
                <div class="corp-icono"><i class="fas fa-code-branch"></i></div>
                <div class="corp-info">
                    <strong>Open Source · Shareware <?php echo htmlspecialchars(SITE_VERSION, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Código abierto · Sin fines de lucro · Libre distribución</span>
                </div>
            </div>
            <div class="corp-item" onclick="abrirModalFooter('auditable')">
                <div class="corp-icono"><i class="fas fa-chart-line"></i></div>
                <div class="corp-info">
                    <strong>Auditable y Transparente</strong>
                    <span>Traza completa · Cumplimiento normativo</span>
                </div>
            </div>
        </div>
    </div>

    <div class="corp-bottom">
        <div class="corp-persona">
            <i class="fas fa-user-check"></i>
            <div>
                <strong><?php echo htmlspecialchars(ESPECIALISTA, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                <small>Especialista en Gestión Económica</small>
            </div>
        </div>
        <div>
            <span class="corp-insignia"><i class="fas fa-shield-heart"></i> <?php echo htmlspecialchars(SITE_VERSION, ENT_QUOTES, 'UTF-8'); ?> · Estable</span>
        </div>
        <div class="corp-persona der">
            <div>
                <strong><?php echo htmlspecialchars(JEFE_PROYECTO, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                <small>Jefe de Proyecto · Director Técnico</small>
            </div>
            <i class="fas fa-certificate violeta"></i>
        </div>
    </div>

    <div class="corp-copy">
        <div class="corp-copy-logo">
            <img src="../../images/unicorn.png" alt="Unicornio" width="20" height="20" onerror="this.style.display='none';">
            <span>Copyright © <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></strong> · Todos los derechos reservados</span>
        </div>
        <div class="corp-links">
            <a href="../../terminos.php" class="corp-link"><i class="fas fa-file-contract"></i> Términos</a>
            <span>|</span>
            <a href="../../privacidad.php" class="corp-link"><i class="fas fa-lock"></i> Privacidad</a>
            <span>|</span>
            <a href="../../soporte.php" class="corp-link"><i class="fas fa-headset"></i> Soporte</a>
			<span>|</span>
            <a href="../../contacto.php" class="corp-link"><i class="fas fa-envelope"></i> Contáctenos</a>
        </div>
    </div>
</footer>

<!-- ===================== PORTAL DE ACCESO POR CLAVE (delante de la página) ===================== -->
<div class="su-gate-overlay<?php echo $su_autorizado ? '' : ' abierta'; ?>" id="suGateOverlay">
    <div class="gate-caja">
        <div class="gate-icono"><i class="fas fa-lock"></i></div>
        <h1>Registro y Control de Salarios</h1>
        <h2><?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="gate-slogan"><?php echo htmlspecialchars(SLOGAN, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="gate-alerta">Debe ser un usuario registrado en el sistema para poder acceder</p>
        <form id="formAcceso" autocomplete="off" novalidate>
            <div class="gate-campo">
                <label for="gateClave"><i class="fas fa-key"></i> Clave de acceso</label>
                <input type="password" id="gateClave" name="clave" placeholder="••••••••••" required maxlength="255" autocomplete="current-password">
                <button type="button" class="gate-ojo" id="gateOjo" title="Mostrar / ocultar clave"><i class="fas fa-eye"></i></button>
            </div>
            <div class="gate-botones">
                <button type="submit" class="gate-btn" id="gateBtn" title="Verificar clave y acceder"><i class="fas fa-unlock"></i> Consultar</button>
                <button type="button" class="gate-btn gate-btn-inicio" id="gateBtnInicio" title="Ir a la página principal"><i class="fas fa-house"></i> Regresar Inicio</button>
            </div>
        </form>
        <p class="gate-nota"><i class="fas fa-shield-halved"></i> Esta consulta es privada.<br>Se requiere una clave registrada en el sistema para continuar.</p>
    </div>
</div>

<!-- Modal informativo del footer (mismo contenido que includes/footer.php) -->
<div class="iso-overlay" id="modalISO27001">
    <div class="iso-caja">
        <div class="iso-barra">
            <i class="fas fa-shield-halved tt-icono" id="modalISOIcon"></i>
            <span class="tt-texto" id="modalISOTitulo">Información del Sistema</span>
            <button type="button" class="iso-cerrar" aria-label="Cerrar" title="Cerrar" onclick="cerrarModalISO27001()"><i class="fas fa-times"></i></button>
        </div>
        <div class="iso-cuerpo">
            <div class="iso-empresa"><i class="fas fa-building"></i><?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="iso-sub" id="modalISOSub"></div>
            <div class="iso-scroll" id="modalISOContenido"></div>
            <div class="iso-acciones">
                <button type="button" class="iso-btn-ok" title="Entendido" onclick="cerrarModalISO27001()"><i class="fas fa-check"></i> Entendido</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- VENTANA-MODAL DE RESULTADOS (no se cierra al clic fuera)   -->
<!-- ========================================================== -->
<div class="su-overlay" id="suOverlay">
    <div class="su-ventana" id="suVentana" role="dialog" aria-modal="true">
        <div class="su-barra-titulos" id="suBarraTitulos">
            <button type="button" class="su-btn-x" id="suBtnX" title="Cerrar"><i class="fas fa-times"></i></button>
            <div class="su-titulo-icono"><i class="fas fa-user-tie"></i></div>
            <div class="su-titulos">
                <h5>Listado Total Salario Devengado por Trabajador</h5>
                <p>Reporte generado para el período seleccionado</p>
            </div>
        </div>

        <div class="su-ventana-cuerpo">
            <div id="printAreaListadoDevengado" style="display:none;">
                <div class="su-centrado">
                    <h4>LISTADO TOTAL SALARIO DEVENGADO</h4>
                    <p class="empresa">Empresa: <?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="su-meta">
                        <span style="color:var(--accent-light);"><strong>MES:</strong> <span id="listadoMesTexto"></span></span>
                        <span style="color:var(--exito);"><strong>NOMBRE DEL TRABAJADOR:</strong> <span id="listadoNombreTexto"></span></span>
                    </div>
                </div>
                <div class="su-tabla-scroll">
                    <table class="su-tabla" id="tablaListadoDevengado">
                        <thead>
                            <tr>
                                <th>TIP. NÓMINA</th>
                                <th style="text-align:center;">CANT. NOM</th>
                                <th>DEVENGADO</th>
                                <th>DEDUCCIONES</th>
                                <th>CESS</th>
                                <th>SAL. NETO</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL GENERAL</td>
                                <td id="listadoTotalCantidad" style="text-align:center;">0</td>
                                <td id="listadoTotalDevengado">$0.00</td>
                                <td id="listadoTotalDeducciones">$0.00</td>
                                <td id="listadoTotalCess">$0.00</td>
                                <td id="listadoTotalNeto">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="su-pie">
            <button type="button" class="su-btn" id="btnCerrarVentana" title="Cerrar"><i class="fas fa-times"></i>Cerrar</button>
            <div class="su-pie-grupo">
                <button type="button" class="su-btn su-btn-primario" id="btnImprimirListadoDevengado" disabled title="Imprimir reporte de devengado"><i class="fas fa-print"></i> Imprimir Reporte</button>
                <div class="su-dropdown">
                    <button type="button" class="su-btn su-btn-exito" id="btnExportarListadoDevengado" disabled title="Exportar reporte de devengado"><i class="fas fa-file-export"></i> Exportar <i class="fas fa-caret-up"></i></button>
                    <div class="su-dropdown-menu" id="suMenuExportar">
                        <a href="#" data-export-listado="xls" title="Descargar reporte en formato Excel"><i class="fas fa-file-excel" style="color:#21a366;"></i> Exportar a Excel (.xls)</a>
                        <a href="#" data-export-listado="docx" title="Descargar reporte en formato Word"><i class="fas fa-file-word" style="color:#60a5fa;"></i> Exportar a Word (.doc)</a>
                        <a href="#" data-export-listado="pdf" title="Descargar reporte en formato PDF"><i class="fas fa-file-pdf" style="color:#f40f02;"></i> Exportar a PDF</a>
                        <a href="#" data-export-listado="txt" title="Descargar reporte en texto plano"><i class="fas fa-file-alt" style="color:#cbd5e1;"></i> Exportar a TXT</a>
                        <a href="#" data-export-listado="csv" title="Descargar reporte en formato CSV"><i class="fas fa-file-csv" style="color:#22d3ee;"></i> Exportar a CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ============ Datos iniciales servidos por PHP ============
    // Sin sesión verificada NO se cargan trabajadores, años ni logo:
    // la página queda vacía detrás del portal de acceso.
    // Global (window) para que también lo lean los scripts posteriores.
    window.SU_AUTORIZADO = <?php echo $su_autorizado ? 'true' : 'false'; ?>;
    var INICIAL = <?php
        if ($su_autorizado) {
            $inicial_trabajadores = $pdo->query("SELECT id, codigo, ci, nombre_completo, activo FROM trabajadores ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
            $inicial_anios = array_map('intval', $pdo->query("SELECT DISTINCT YEAR(periodo_desde) AS anio FROM nominas ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN));
            $inicial_ruta_logo = __DIR__ . '/../../images/logotn.png';
            $inicial_logo_b64 = '';
            if (file_exists($inicial_ruta_logo)) {
                $inicial_logo_b64 = 'data:image/' . pathinfo($inicial_ruta_logo, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($inicial_ruta_logo));
            }
        } else {
            $inicial_trabajadores = [];
            $inicial_anios = [];
            $inicial_logo_b64 = '';
        }
        echo json_encode([
            'trabajadores' => $inicial_trabajadores,
            'anios' => $inicial_anios,
            'nombreEmpresa' => COMPANY_NAME,
            'reeup' => REEUP_EMPRESA,
            'nitEmpresa' => NIT,
            'jefeProyecto' => JEFE_PROYECTO,
            'especialistaGestion' => ESPECIALISTA,
            'usuarioNombre' => 'Consulta Pública',
            'logoBase64' => $inicial_logo_b64,
        ], JSON_UNESCAPED_UNICODE);
    ?>;

    var MESES_ESP = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var LISTADO_ESTADO_TXT = { '': 'TODOS', 'borrador': 'BORRADOR', 'contabilizado': 'CONTABILIZADO' };
    var nombreEmpresa = INICIAL.nombreEmpresa;
    var reeup = INICIAL.reeup || '';
    var nitEmpresa = INICIAL.nitEmpresa || '';
    var jefeProyecto = INICIAL.jefeProyecto || '';
    var especialistaGestion = INICIAL.especialistaGestion || '';
    var usuarioNombre = INICIAL.usuarioNombre || '';
    var logoBase64 = INICIAL.logoBase64 || '';
    var trabajadoresTodos = INICIAL.trabajadores || [];
    var aniosDisponibles = INICIAL.anios || [];

    // ============ Estado global ============
    var datosActual = null;
    var trabajadorActual = null;
    var anioActual = 0;
    var mesActual = 0;
    var mesTextoActual = '';

    function $(sel) { return document.querySelector(sel); }

    function listadoEscapeHtml(text) {
        return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function listadoFormatoNumero(valor) {
        return parseFloat(valor || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function listadoMoneda(valor) { return '$' + listadoFormatoNumero(valor); }

    function listadoTipoNominaLabel(tipo) {
        var map = {
            'automatica': 'AUTOMÁTICA',
            'extraordinaria': 'HORAS EXTRAS Y NOCTURNIDAD',
            'vacaciones': 'VACACIONES',
            'bono': 'BONO (RENDIMIENTO)',
            'ajuste': 'NÓMINA DE AJUSTES'
        };
        return map[tipo] || String(tipo || '').toUpperCase();
    }
    function listadoTipoNominaBadge(tipo) {
        var map = {
            'automatica':     { label: 'AUTOMÁTICA', bg: 'rgba(96,165,250,0.18)', border: '#60a5fa', color: '#93c5fd' },
            'extraordinaria': { label: 'HORAS EXTRAS Y NOCTURNIDAD', bg: 'rgba(245,158,11,0.18)', border: '#f59e0b', color: '#fcd34d' },
            'vacaciones':     { label: 'VACACIONES', bg: 'rgba(139,92,246,0.18)', border: '#8b5cf6', color: '#c4b5fd' },
            'bono':           { label: 'BONO (RENDIMIENTO)', bg: 'rgba(16,185,129,0.18)', border: '#10b981', color: '#6ee7b7' },
            'ajuste':         { label: 'NÓMINA DE AJUSTES', bg: 'rgba(6,182,212,0.18)', border: '#06b6d4', color: '#67e8f9' }
        };
        var c = map[tipo] || { label: String(tipo || '').toUpperCase(), bg: 'rgba(148,163,184,0.18)', border: '#94a3b8', color: '#cbd5e1' };
        return '<span class="su-badge" style="background:' + c.bg + '; border:0.0625rem solid ' + c.border + '; color:' + c.color + ';">' + listadoEscapeHtml(c.label) + '</span>';
    }
    function listadoSwalError(mensaje) {
        Swal.fire({
            title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Atención',
            text: mensaje, icon: 'warning',
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        });
    }
    function peticionGet(params) {
        var qs = Object.keys(params).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        return fetch('api.php?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r){ return r.json(); });
    }

    // ----------------------------------------------------------
    // Búsqueda de trabajador (idéntica al modal original)
    // ----------------------------------------------------------
    function listadoPintarOpciones(lista) {
        var cont = $('#listadoResultadosBusqueda');
        if (!lista.length) {
            cont.innerHTML = '<a>Sin resultados</a>';
            cont.style.display = 'block';
            return;
        }
        var html = '';
        lista.forEach(function(t) {
            html += '<a class="listado-opt-trabajador" href="#" data-id="' + t.id + '">'
                + '<i class="fas fa-user" style="margin-right:0.5rem;color:#67e8f9;"></i>' + listadoEscapeHtml(t.nombre_completo)
                + ' <small>(' + listadoEscapeHtml(t.codigo || 'S/C') + ' · ' + listadoEscapeHtml(t.ci || 'S/CI') + ')</small>'
                + '</a>';
        });
        cont.innerHTML = html;
        cont.style.display = 'block';
    }

    function listadoBuscar(texto) {
        var term = (texto || '').toLowerCase().trim();
        var cont = $('#listadoResultadosBusqueda');
        if (term.length < 2) { cont.style.display = 'none'; cont.innerHTML = ''; return; }

        var matches = trabajadoresTodos.filter(function(t) {
            var hayNombre = (t.nombre_completo || '').toLowerCase().indexOf(term) !== -1;
            var hayCodigo = (t.codigo || '').toLowerCase().indexOf(term) !== -1;
            var hayCI = (t.ci || '').toLowerCase().indexOf(term) !== -1;
            return hayNombre || hayCodigo || hayCI;
        });

        listadoPintarOpciones(matches.slice(0, 40));
    }

    // Despliega la lista completa de trabajadores (como un select)
    function listadoAlternarLista() {
        var cont = $('#listadoResultadosBusqueda');
        if (cont.style.display === 'block') { cont.style.display = 'none'; cont.innerHTML = ''; return; }
        var ordenados = trabajadoresTodos.slice().sort(function(a, b) {
            return (a.nombre_completo || '').localeCompare(b.nombre_completo || '', 'es');
        });
        $('#listadoBuscarTrabajador').focus();
        listadoPintarOpciones(ordenados.slice(0, 200));
    }

    function listadoSeleccionarTrabajador(t) {
        $('#listadoTrabajadorId').value = t.id;
        $('#listadoBuscarTrabajador').value = t.nombre_completo;
        $('#listadoResultadosBusqueda').style.display = 'none';
        $('#listadoResultadosBusqueda').innerHTML = '';
        $('#listadoTrabajadorInfo').innerHTML = '<i class="fas fa-check-circle" style="color:var(--exito);"></i> Seleccionado: <strong>' + listadoEscapeHtml(t.nombre_completo) + '</strong> <small>(' + listadoEscapeHtml(t.codigo || 'S/C') + ' · ' + listadoEscapeHtml(t.ci || 'S/CI') + ')</small>';
        listadoCargarMeses();
    }

    // ----------------------------------------------------------
    // Carga de años y meses
    // ----------------------------------------------------------
    function listadoCargarAnios() {
        var select = $('#listadoAnio');
        var html = '<option value="">-- Años --</option>';
        aniosDisponibles.forEach(function(a) { html += '<option value="' + a + '">' + a + '</option>'; });
        select.innerHTML = html;
        if (aniosDisponibles.length) {
            select.value = String(aniosDisponibles[0]);
            listadoCargarMeses();
        }
    }

    function listadoToggleBotones(habilitar) {
        ['#btnGenerarListadoDevengado'].forEach(function(sel) {
            var b = $(sel);
            b.disabled = !habilitar;
            b.style.opacity = habilitar ? 1 : 0.45;
        });
    }

    function listadoCargarMeses() {
        var anio = parseInt($('#listadoAnio').value) || 0;
        var trabajadorId = parseInt($('#listadoTrabajadorId').value) || 0;
        var estado = $('#listadoEstado').value || '';
        var select = $('#listadoMes');

        select.disabled = true;
        select.innerHTML = '<option value="">-- Mes --</option>';
        listadoToggleBotones(false);
        if (!anio) return;

        peticionGet({ accion: 'meses', anio: anio, trabajador_id: trabajadorId, estado: estado }).then(function(r) {
            if (r.success && r.meses.length) {
                var html = '<option value="">-- Mes --</option><option value="0">Todos</option>';
                r.meses.forEach(function(m) { html += '<option value="' + m + '">' + MESES_ESP[m] + '</option>'; });
                select.innerHTML = html;
                select.disabled = false;
                listadoToggleBotones(true);
            } else {
                select.innerHTML = '<option value="">(Sin datos)</option>';
                listadoToggleBotones(false);
            }
        });
    }

    // ----------------------------------------------------------
    // Generar y renderizar reporte (dentro de la ventana-modal)
    // ----------------------------------------------------------
    function listadoGenerarReporte() {
        var trabajadorId = parseInt($('#listadoTrabajadorId').value) || 0;
        var anio = parseInt($('#listadoAnio').value) || 0;
        var mesVal = $('#listadoMes').value;
        var mes = mesVal === '' ? 0 : parseInt(mesVal) || 0;
        var estado = $('#listadoEstado').value || '';

        if (!trabajadorId) { listadoSwalError('Debe buscar y seleccionar un trabajador.'); return; }
        if (!anio || mesVal === '') { listadoSwalError('Debe seleccionar el año y el mes que contengan datos.'); return; }

        Swal.fire({
            title: '<i class="fas fa-spinner fa-spin"></i> Consultando...',
            text: 'Buscando nóminas del trabajador en el período seleccionado.',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });

        peticionGet({
            accion: 'listado',
            trabajador_id: trabajadorId,
            anio: anio,
            mes: mes,
            estado: estado,
            modo: $('#listadoModo').value || 'consolidado'
        }).then(function(r) {
            Swal.close();
            if (!r.success) {
                Swal.fire({ title: 'Error', text: r.error || 'No se pudo obtener el reporte.', icon: 'error' });
                return;
            }
            datosActual = r;
            trabajadorActual = r.trabajador;
            anioActual = anio;
            mesActual = mes;
            mesTextoActual = (mes === 0) ? 'TODOS LOS MESES - ' + anio : MESES_ESP[mes] + ' ' + anio;
            listadoRender();
            abrirVentana();
        })['catch'](function() {
            Swal.close();
            listadoSwalError('Error de conexión al intentar obtener el reporte.');
        });
    }

    function listadoRender() {
        if (!datosActual) return;
        var esCompleto = (datosActual.modo === 'completo');
        $('#listadoMesTexto').textContent = mesTextoActual;
        $('#listadoNombreTexto').textContent = trabajadorActual.nombre_completo;

        var thead = $('#tablaListadoDevengado thead tr');
        if (esCompleto) {
            thead.innerHTML = '<th>TIP. NÓMINA</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th>';
        } else {
            thead.innerHTML = '<th>TIP. NÓMINA</th><th style="text-align:center;">CANT. NOM</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th>';
        }

        var tbody = $('#tablaListadoDevengado tbody');
        if (!datosActual.filas.length) {
            tbody.innerHTML = '<tr><td colspan="' + (esCompleto ? 5 : 6) + '" class="sin-datos"><i class="fas fa-info-circle"></i> No hay nóminas registradas para este trabajador en el período seleccionado.</td></tr>';
        } else if (esCompleto) {
            var tipoActual = '';
            var html = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    html += '<tr class="su-sep-tipo"><td colspan="5">' + listadoTipoNominaBadge(f.tipo_nomina) + '</td></tr>';
                }
                html += '<tr>'
                    + '<td style="padding-left:1.5rem;font-style:italic;color:rgba(220,232,255,0.7);">' + MESES_ESP[f.mes_num] + '</td>'
                    + '<td>' + listadoMoneda(f.devengado) + '</td>'
                    + '<td>' + listadoMoneda(f.deducciones) + '</td>'
                    + '<td>' + listadoMoneda(f.cess) + '</td>'
                    + '<td class="su-neto">' + listadoMoneda(f.neto) + '</td>'
                    + '</tr>';
            });
            tbody.innerHTML = html;
        } else {
            var tipoActual2 = '';
            var html2 = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual2) {
                    tipoActual2 = f.tipo_nomina;
                    html2 += '<tr class="su-sep-tipo"><td colspan="6">' + listadoTipoNominaBadge(f.tipo_nomina) + '</td></tr>';
                }
                html2 += '<tr>'
                    + '<td></td>'
                    + '<td style="text-align:center;">' + f.cantidad + '</td>'
                    + '<td>' + listadoMoneda(f.devengado) + '</td>'
                    + '<td>' + listadoMoneda(f.deducciones) + '</td>'
                    + '<td>' + listadoMoneda(f.cess) + '</td>'
                    + '<td class="su-neto">' + listadoMoneda(f.neto) + '</td>'
                    + '</tr>';
            });
            tbody.innerHTML = html2;
        }
        var tc = $('#listadoTotalCantidad');
        tc.textContent = datosActual.totales.cantidad;
        tc.style.display = esCompleto ? 'none' : '';
        $('#listadoTotalDevengado').textContent = listadoMoneda(datosActual.totales.devengado);
        $('#listadoTotalDeducciones').textContent = listadoMoneda(datosActual.totales.deducciones);
        $('#listadoTotalCess').textContent = listadoMoneda(datosActual.totales.cess);
        $('#listadoTotalNeto').textContent = listadoMoneda(datosActual.totales.neto);
        $('#printAreaListadoDevengado').style.display = '';
        ['#btnImprimirListadoDevengado', '#btnExportarListadoDevengado'].forEach(function(sel) { $(sel).disabled = false; });
    }

    // ----------------------------------------------------------
    // Ventana-modal (barra de títulos + X a la izquierda)
    // ----------------------------------------------------------
    var arrastrando = null;

    function abrirVentana() {
        var overlay = $('#suOverlay');
        overlay.classList.add('abierta');
        var v = $('#suVentana');
        v.style.left = '50%'; v.style.top = '50%'; v.style.transform = 'translate(-50%, -50%)';
        $('.su-ventana-cuerpo').scrollTop = 0;
    }
    function cerrarVentana() {
        // Mismo reinicio que hidden.bs.modal en nominas.php
        $('#suOverlay').classList.remove('abierta');
        datosActual = null;
        trabajadorActual = null;
        $('#listadoTrabajadorId').value = '';
        $('#listadoBuscarTrabajador').value = '';
        $('#listadoResultadosBusqueda').style.display = 'none';
        $('#listadoResultadosBusqueda').innerHTML = '';
        $('#listadoTrabajadorInfo').innerHTML = '';
        $('#printAreaListadoDevengado').style.display = 'none';
        $('#listadoEstado').value = '';
        $('#listadoModo').value = 'consolidado';
        var mesSel = $('#listadoMes');
        mesSel.innerHTML = '<option value="">-- Mes --</option>';
        mesSel.disabled = true;
        listadoToggleBotones(false);
        $('#btnImprimirListadoDevengado').disabled = true;
        $('#btnExportarListadoDevengado').disabled = true;
        listadoCargarAnios();
    }

    // Arrastre de la ventana por la barra de títulos
    document.addEventListener('mousedown', function(e) {
        if (e.target.closest('#suBtnX')) return;
        var barra = e.target.closest('#suBarraTitulos');
        if (!barra) return;
        var v = $('#suVentana');
        var r = v.getBoundingClientRect();
        v.style.transform = 'none';
        v.style.left = r.left + 'px';
        v.style.top = r.top + 'px';
        arrastrando = { dx: e.clientX - r.left, dy: e.clientY - r.top };
        e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
        if (!arrastrando) return;
        var v = $('#suVentana');
        var x = e.clientX - arrastrando.dx;
        var y = e.clientY - arrastrando.dy;
        v.style.left = Math.max(0, Math.min(window.innerWidth - 80, x)) + 'px';
        v.style.top = Math.max(0, Math.min(window.innerHeight - 40, y)) + 'px';
    });
    document.addEventListener('mouseup', function() { arrastrando = null; });

    // Clic FUERA de la ventana: NO cierra (requisito). Solo la X o "Cerrar".

    // ----------------------------------------------------------
    // HTML de la tabla (tema claro) para impresión/exportación
    // ----------------------------------------------------------
    function estadoSeleccionadoTxt() {
        return LISTADO_ESTADO_TXT[$('#listadoEstado').value || ''] || 'TODOS';
    }
    function listadoTablaHtml() {
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var filas = '';
        if (esCompleto) {
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas += '<tr style="background:#e8edf3;"><td colspan="' + numCols + '"><b>' + listadoEscapeHtml(listadoTipoNominaLabel(f.tipo_nomina)) + '</b></td></tr>';
                }
                filas += '<tr><td style="padding-left:1.25rem;font-style:italic;">' + listadoEscapeHtml(MESES_ESP[f.mes_num]) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.devengado) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.deducciones) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.cess) + '</td>'
                    + '<td><b>' + listadoFormatoNumero(f.neto) + '</b></td></tr>';
            });
        } else {
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    filas += '<tr style="background:#e8edf3;"><td colspan="' + numCols + '"><b>' + listadoEscapeHtml(listadoTipoNominaLabel(f.tipo_nomina)) + '</b></td></tr>';
                }
                filas += '<tr><td></td><td style="text-align:center;">' + f.cantidad + '</td>'
                    + '<td>' + listadoFormatoNumero(f.devengado) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.deducciones) + '</td>'
                    + '<td>' + listadoFormatoNumero(f.cess) + '</td>'
                    + '<td><b>' + listadoFormatoNumero(f.neto) + '</b></td></tr>';
            });
        }
        if (!datosActual.filas.length) {
            filas = '<tr><td colspan="' + numCols + '" style="text-align:center;">No hay nóminas registradas para este trabajador en el período seleccionado.</td></tr>';
        }
        var t = datosActual.totales;
        var theadHtml = esCompleto
            ? '<thead><tr><th>TIP. NÓMINA</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr></thead>'
            : '<thead><tr><th>TIP. NÓMINA</th><th style="text-align:center;">CANT. NOM</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr></thead>';
        return theadHtml + '<tbody>' + filas + '</tbody>'
            + '<tfoot><tr class="total-row">'
            + '<td><b>TOTAL GENERAL</b></td>'
            + (esCompleto ? '' : '<td style="text-align:center;"><b>' + t.cantidad + '</b></td>')
            + '<td><b>' + listadoFormatoNumero(t.devengado) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.deducciones) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.cess) + '</b></td>'
            + '<td><b>' + listadoFormatoNumero(t.neto) + '</b></td>'
            + '</tr></tfoot>';
    }

    function firmasHtml(estiloLinea) {
        return '<table style="width:100%;border:none;border-collapse:collapse;margin-top:3.4375rem;"><tr>'
            + '<td style="width:25%;text-align:center;"><p><b>Elaborado por:</b></p>' + estiloLinea + '<span style="font-size:8pt;color:#444;">Especialista de Nóminas</span></td>'
            + '<td style="width:25%;text-align:center;"><p><b>Revisado por:</b></p>' + estiloLinea + '<b>' + listadoEscapeHtml((especialistaGestion || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Especialista en Gestión Económica</span></td>'
            + '<td style="width:25%;text-align:center;"><p><b>Aprobado por:</b></p>' + estiloLinea + '<b>' + listadoEscapeHtml((jefeProyecto || '').toUpperCase()) + '</b><br><span style="font-size:8pt;color:#444;">Director de Proyecto</span></td>'
            + '<td style="width:25%;text-align:center;"><p><b>Contabilizado por:</b></p>' + estiloLinea + '<span style="font-size:8pt;color:#444;">Área Contable y Financiera</span></td>'
            + '</tr></table>';
    }
    function pieDocumento() {
        return 'Documento generado por el Sistema de Gestión de Nóminas - Usuario: ' + listadoEscapeHtml(usuarioNombre || '');
    }

    var PRINT_TOOLBAR_HTML = '<style>#auto-hide-toolbar{transition:transform 0.3s ease}#auto-hide-toolbar.hidden{transform:translateY(-100%)}</style>'
        + '<link rel="stylesheet" href="/nominas/css/font-awesome6.4.0/css/all.min.css">'
        + '<div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#0e7490,#0891b2);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #06b6d4;">'
        + '<span style="color:#cffafe;font-weight:bold;font-size:0.8125rem;"><i class="fas fa-eye"></i> VISTA PREVIA DE IMPRESIÓN</span>'
        + '<button onclick="window.print()" title="Imprimir documento" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;"><i class="fas fa-print"></i> Imprimir</button>'
        + '<button onclick="window.close()" title="Cerrar vista previa" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;"><i class="fas fa-xmark"></i> Cerrar</button>'
        + '</div><div style="height:3.4375rem;"></div>'
        + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||0;window.addEventListener("scroll",function(){var y=window.scrollY||0;if(y>lastY+4&&y>60){tb.classList.add("hidden")}else if(y<lastY-4){tb.classList.remove("hidden")}lastY=y},{passive:true});})();<\/script>';

    function listadoImprimir() {
        if (!datosActual) { listadoSwalError('Primero genere el reporte.'); return; }
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES');
        var tablaHtml = listadoTablaHtml();

        var win = window.open('', '_blank');
        if (!win) { listadoSwalError('No se pudo abrir la ventana de impresión.'); return; }
        win.document.write(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            + '<title>Listado Total Salario Devengado</title>'
            + '<style>'
            + '@page { size: portrait; margin:12mm; }'
            + 'body { font-family: Arial, sans-serif; font-size:10pt; color: #000; margin:0; padding:0; }'
            + '.header-container { width:100%; border: 0.0625rem solid #000; border-collapse: collapse; margin-bottom:1.125rem; }'
            + '.header-container td { border: 0.0625rem solid #000; padding:0.5rem; vertical-align: middle; }'
            + '.logo-cell { width:3.75rem; text-align: center; }'
            + '.title-cell { text-align: center; font-weight: bold; font-size:12pt; }'
            + '.meta-cell { font-size:8pt; width:11.875rem; }'
            + '.main-table { border-collapse: collapse; margin-top:0.5rem; table-layout: auto; width:auto; margin-left:auto; margin-right:auto; }'
            + '.main-table th { background-color: #0891b2; color: #ffffff; font-weight: bold; padding:0.5rem; border: 0.0625rem solid #000; text-align: right; -webkit-print-color-adjust: exact; }'
            + '.main-table th:first-child { text-align: left; }'
            + '.main-table td { border: 0.0625rem solid #000; padding:0.375rem 0.625rem; text-align: right; }'
            + '.main-table td:first-child { text-align: left; }'
            + '.total-row { background-color: #f0f4f8; font-weight: bold; }'
            + '.total-row td { border-top: 0.125rem solid #000; color: #0e7490; }'
            + '.info-line { text-align: center; margin:0.25rem 0; }'
            + '.sig-line { border-top: 0.0625rem solid #000; width:90%; margin:2.8125rem auto 0.3125rem auto; }'
            + '.footer-info { margin-top:0.9375rem; font-size:8pt; color: #666; text-align: center; border-top: 0.5pt solid #eee; padding-top:0.3125rem; }'
            + '.filtro-info { background-color: #f8f9fa; font-weight: bold; color: #0e7490; padding:0.25rem 0.625rem; border-radius: 0.25rem; display: inline-block; }'
            + '@media print { .no-print { display: none !important; } }'
            + '</style></head><body>' + PRINT_TOOLBAR_HTML
            + '<table class="header-container"><tr>'
            + '<td class="logo-cell">' + (logoBase64 ? '<img src="' + logoBase64 + '" width="50">' : '') + '</td>'
            + '<td class="title-cell">LISTADO TOTAL SALARIO DEVENGADO<br><span style="font-size:10pt;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</span></td>'
            + '<td class="meta-cell"><strong>Emisión:</strong> ' + fechaHora + '<br><strong>REEUP:</strong> ' + listadoEscapeHtml(reeup) + '<br><strong>NIT:</strong> ' + listadoEscapeHtml(nitEmpresa) + '</td>'
            + '</tr></table>'
            + '<div style="text-align:center; margin-bottom:0.375rem;"><span class="filtro-info">ESTADO: ' + estadoSeleccionadoTxt() + '</span> &nbsp; <span class="filtro-info">MODO: ' + (datosActual.modo === 'completo' ? 'COMPLETO (POR MES)' : 'CONSOLIDADO') + '</span></div>'
            + '<div class="info-line"><strong>MES:</strong> ' + listadoEscapeHtml(mesTextoActual) + '</div>'
            + '<div class="info-line"><strong>NOMBRE DEL TRABAJADOR:</strong> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</div>'
            + '<table class="main-table">' + tablaHtml + '</table>'
            + '<div style="margin-top:3.4375rem;">' + firmasHtml('<div class="sig-line"></div>') + '</div>'
            + '<div class="footer-info">' + pieDocumento() + '</div>'
            + '</body></html>'
        );
        win.document.close();
    }

    // ----------------------------------------------------------
    // Descargas y exportaciones (clon de nominas.php)
    // ----------------------------------------------------------
    function listadoDescargar(contenido, nombreArchivo, mime) {
        var blob = new Blob(['\ufeff' + contenido], { type: mime || 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
    }
    function listadoNombreArchivo(extension) {
        var sufijo = anioActual + (mesActual > 0 ? '-' + String(mesActual).padStart(2, '0') : '_TODOS');
        return 'Listado_Salario_Devengado_' + (trabajadorActual ? trabajadorActual.nombre_completo.replace(/[^A-Za-z0-9]+/g, '_') : 'Trabajador') + '_' + sufijo + '.' + extension;
    }

    function listadoExportarXls() {
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES');
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var tabla = '<table border="1">';
        tabla += '<tr><th colspan="' + numCols + '" style="text-align:center;font-size:0.875rem;">LISTADO TOTAL SALARIO DEVENGADO</th></tr>'
            + '<tr><td colspan="' + numCols + '" style="text-align:center;font-weight:bold;font-size:0.75rem;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>REEUP:</b> ' + listadoEscapeHtml(reeup) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>NIT:</b> ' + listadoEscapeHtml(nitEmpresa) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>Emisión:</b> ' + fechaHora + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>ESTADO:</b> ' + estadoSeleccionadoTxt() + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>MES:</b> ' + listadoEscapeHtml(mesTextoActual) + '</td></tr>'
            + '<tr><td colspan="' + numCols + '"><b>NOMBRE DEL TRABAJADOR:</b> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</td></tr>';
        if (esCompleto) {
            tabla += '<tr><th>TIP. NÓMINA</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr>';
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    tabla += '<tr><td colspan="' + numCols + '"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                tabla += '<tr><td>' + MESES_ESP[f.mes_num] + '</td><td>' + listadoFormatoNumero(f.devengado) + '</td><td>' + listadoFormatoNumero(f.deducciones) + '</td><td>' + listadoFormatoNumero(f.cess) + '</td><td>' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        } else {
            tabla += '<tr><th>TIP. NÓMINA</th><th style="text-align:center;">CANT. NOM</th><th>DEVENGADO</th><th>DEDUCCIONES</th><th>CESS</th><th>SAL. NETO</th></tr>';
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    tabla += '<tr><td colspan="' + numCols + '"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                tabla += '<tr><td></td><td style="text-align:center;">' + f.cantidad + '</td><td>' + listadoFormatoNumero(f.devengado) + '</td><td>' + listadoFormatoNumero(f.deducciones) + '</td><td>' + listadoFormatoNumero(f.cess) + '</td><td>' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        }
        var t = datosActual.totales;
        tabla += '<tr><td><b>TOTAL GENERAL</b></td>' + (esCompleto ? '' : '<td><b>' + t.cantidad + '</b></td>') + '<td><b>' + listadoFormatoNumero(t.devengado) + '</b></td><td><b>' + listadoFormatoNumero(t.deducciones) + '</b></td><td><b>' + listadoFormatoNumero(t.cess) + '</b></td><td><b>' + listadoFormatoNumero(t.neto) + '</b></td></tr>';
        tabla += '</table>';
        tabla += firmasHtml('<br><br><br><br>_____________________________________')
            + '<p style="font-size:8pt;color:#666;text-align:center;margin-top:0.9375rem;">' + pieDocumento() + '</p>';
        listadoDescargar(tabla, listadoNombreArchivo('xls'), 'application/vnd.ms-excel;charset=utf-8');
    }

    function listadoExportarCsv() {
        var num = function(v) { return parseFloat(v || 0).toFixed(2); };
        var esCompleto = (datosActual.modo === 'completo');
        var filas = [];
        filas.push('LISTADO TOTAL SALARIO DEVENGADO');
        filas.push('EMPRESA,' + '"' + (nombreEmpresa || '') + '"');
        filas.push('REEUP,' + '"' + (reeup || '') + '"');
        filas.push('NIT,' + '"' + (nitEmpresa || '') + '"');
        filas.push('MES,' + '"' + mesTextoActual + '"');
        filas.push('NOMBRE DEL TRABAJADOR,' + '"' + trabajadorActual.nombre_completo + '"');
        filas.push('ESTADO,' + '"' + estadoSeleccionadoTxt() + '"');
        filas.push('');
        if (esCompleto) {
            filas.push('TIP. NÓMINA,DEVENGADO,DEDUCCIONES,CESS,SAL. NETO');
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    filas.push('"' + listadoTipoNominaLabel(f.tipo_nomina) + '",,,,');
                }
                filas.push('"' + MESES_ESP[f.mes_num] + '",' + num(f.devengado) + ',' + num(f.deducciones) + ',' + num(f.cess) + ',' + num(f.neto));
            });
        } else {
            filas.push('TIP. NÓMINA,CANT. NOM,DEVENGADO,DEDUCCIONES,CESS,SAL. NETO');
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    filas.push('"' + listadoTipoNominaLabel(f.tipo_nomina) + '",,,,');
                }
                filas.push('",' + f.cantidad + ',' + num(f.devengado) + ',' + num(f.deducciones) + ',' + num(f.cess) + ',' + num(f.neto));
            });
        }
        var t = datosActual.totales;
        if (esCompleto) {
            filas.push('"TOTAL GENERAL",' + num(t.devengado) + ',' + num(t.deducciones) + ',' + num(t.cess) + ',' + num(t.neto));
        } else {
            filas.push('"TOTAL GENERAL",' + t.cantidad + ',' + num(t.devengado) + ',' + num(t.deducciones) + ',' + num(t.cess) + ',' + num(t.neto));
        }
        listadoDescargar(filas.join('\r\n'), listadoNombreArchivo('csv'), 'text/csv;charset=utf-8');
    }

    function listadoExportarTxt() {
        function padRight(s, n) { s = String(s || ''); return s.length >= n ? s : s + ' '.repeat(n - s.length); }
        function padLeft(s, n) { s = String(s || ''); return s.length >= n ? s : ' '.repeat(n - s.length) + s; }
        function padCenter(s, n) { s = String(s || ''); if (s.length >= n) return s; var left = Math.floor((n - s.length) / 2); return ' '.repeat(left) + s + ' '.repeat(n - s.length - left); }
        var esCompleto = (datosActual.modo === 'completo');
        var lineas = [];
        lineas.push('========================================================================');
        lineas.push('                LISTADO TOTAL SALARIO DEVENGADO');
        lineas.push('========================================================================');
        lineas.push('Empresa: ' + nombreEmpresa);
        lineas.push('MES: ' + mesTextoActual);
        lineas.push('NOMBRE DEL TRABAJADOR: ' + trabajadorActual.nombre_completo);
        lineas.push('ESTADO: ' + estadoSeleccionadoTxt());
        lineas.push('------------------------------------------------------------------------');
        if (esCompleto) {
            lineas.push(padRight('TIP. NÓMINA', 28) + padLeft('DEVENGADO', 14) + padLeft('DEDUCCIONES', 14) + padLeft('CESS', 12) + padLeft('SAL. NETO', 14));
            lineas.push('------------------------------------------------------------------------');
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    lineas.push(padRight('--- ' + listadoTipoNominaLabel(f.tipo_nomina) + ' ---', 72));
                }
                lineas.push(padRight(MESES_ESP[f.mes_num], 28) + padLeft(listadoFormatoNumero(f.devengado), 14) + padLeft(listadoFormatoNumero(f.deducciones), 14) + padLeft(listadoFormatoNumero(f.cess), 12) + padLeft(listadoFormatoNumero(f.neto), 14));
            });
        } else {
            lineas.push(padRight('TIP. NÓMINA', 28) + padCenter('CANT', 8) + padLeft('DEVENGADO', 14) + padLeft('DEDUCCIONES', 14) + padLeft('CESS', 12) + padLeft('SAL. NETO', 14));
            lineas.push('------------------------------------------------------------------------');
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    lineas.push(padRight('--- ' + listadoTipoNominaLabel(f.tipo_nomina) + ' ---', 82));
                }
                lineas.push(padRight('', 28) + padCenter(f.cantidad, 8) + padLeft(listadoFormatoNumero(f.devengado), 14) + padLeft(listadoFormatoNumero(f.deducciones), 14) + padLeft(listadoFormatoNumero(f.cess), 12) + padLeft(listadoFormatoNumero(f.neto), 14));
            });
        }
        lineas.push('------------------------------------------------------------------------');
        var t = datosActual.totales;
        if (esCompleto) {
            lineas.push(padRight('TOTAL GENERAL', 28) + padLeft(listadoFormatoNumero(t.devengado), 14) + padLeft(listadoFormatoNumero(t.deducciones), 14) + padLeft(listadoFormatoNumero(t.cess), 12) + padLeft(listadoFormatoNumero(t.neto), 14));
        } else {
            lineas.push(padRight('TOTAL GENERAL', 28) + padCenter(t.cantidad, 8) + padLeft(listadoFormatoNumero(t.devengado), 14) + padLeft(listadoFormatoNumero(t.deducciones), 14) + padLeft(listadoFormatoNumero(t.cess), 12) + padLeft(listadoFormatoNumero(t.neto), 14));
        }
        lineas.push('========================================================================');
        listadoDescargar(lineas.join('\r\n'), listadoNombreArchivo('txt'), 'text/plain;charset=utf-8');
    }

    function listadoExportarDocx() {
        var now = new Date();
        var fechaHora = now.toLocaleDateString('es-ES') + ' - ' + now.toLocaleTimeString('es-ES');
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;

        var contenido = '<div class="WordSection1">'
            + '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%;">'
            + '<tr>'
            + '<td style="width:3.75rem;text-align:center;">' + (logoBase64 ? '<img src="' + logoBase64 + '" width="34" height="41" style="width:0.9cm;height:1.08cm;">' : '') + '</td>'
            + '<td style="text-align:center;font-weight:bold;font-size:0.875rem;">LISTADO TOTAL SALARIO DEVENGADO<br><span style="font-size:0.6875rem;font-weight:normal;">' + listadoEscapeHtml((nombreEmpresa || '').toUpperCase()) + '</span></td>'
            + '<td style="width:11.875rem;font-size:8pt;"><b>Emisión:</b> ' + fechaHora + '<br><b>REEUP:</b> ' + listadoEscapeHtml(reeup) + '<br><b>NIT:</b> ' + listadoEscapeHtml(nitEmpresa) + '</td>'
            + '</tr></table>'
            + '<p style="text-align:center;"><b>ESTADO:</b> ' + estadoSeleccionadoTxt() + '</p>'
            + '<p><b>MES:</b> ' + listadoEscapeHtml(mesTextoActual) + '</p>'
            + '<p><b>NOMBRE DEL TRABAJADOR:</b> ' + listadoEscapeHtml(trabajadorActual.nombre_completo) + '</p>'
            + '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:auto;margin:0 auto;">';
        if (esCompleto) {
            contenido += '<tr style="background:#0891b2;"><th style="color:#fff;">TIP. NÓMINA</th><th style="color:#fff;text-align:right;">DEVENGADO</th><th style="color:#fff;text-align:right;">DEDUCCIONES</th><th style="color:#fff;text-align:right;">CESS</th><th style="color:#fff;text-align:right;">SAL. NETO</th></tr>';
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    contenido += '<tr><td colspan="' + numCols + '" style="background:#e8edf3;"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                contenido += '<tr><td style="font-style:italic;">' + MESES_ESP[f.mes_num] + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.devengado) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.deducciones) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.cess) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        } else {
            contenido += '<tr style="background:#0891b2;"><th style="color:#fff;">TIP. NÓMINA</th><th style="color:#fff;text-align:center;">CANT. NOM</th><th style="color:#fff;text-align:right;">DEVENGADO</th><th style="color:#fff;text-align:right;">DEDUCCIONES</th><th style="color:#fff;text-align:right;">CESS</th><th style="color:#fff;text-align:right;">SAL. NETO</th></tr>';
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    contenido += '<tr><td colspan="' + numCols + '" style="background:#e8edf3;"><b>' + listadoTipoNominaLabel(f.tipo_nomina) + '</b></td></tr>';
                }
                contenido += '<tr><td></td><td style="text-align:center;">' + f.cantidad + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.devengado) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.deducciones) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.cess) + '</td><td style="text-align:right;">' + listadoFormatoNumero(f.neto) + '</td></tr>';
            });
        }
        var t = datosActual.totales;
        contenido += '<tr style="background:#eee;"><th>TOTAL GENERAL</th>' + (esCompleto ? '' : '<th style="text-align:center;">' + t.cantidad + '</th>') + '<th style="text-align:right;">' + listadoFormatoNumero(t.devengado) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.deducciones) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.cess) + '</th><th style="text-align:right;">' + listadoFormatoNumero(t.neto) + '</th></tr>';
        contenido += '</table>';
        contenido += firmasHtml('<p style="margin-top:2.5rem;border-top:0.0625rem solid #000;width:90%;margin-left:auto;margin-right:auto;"></p>')
            + '<p style="font-size:8pt;color:#666;text-align:center;margin-top:0.9375rem;">' + pieDocumento() + '</p>'
            + '</div>';

        var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>Listado Total Salario Devengado</title><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]--><style>img { width:0.9cm !important; height:1.08cm !important; }</style></head><body>' + contenido + '</body></html>';
        listadoDescargar(html, listadoNombreArchivo('doc'), 'application/msword;charset=utf-8');
    }

    function listadoExportarPdf() {
        var esCompleto = (datosActual.modo === 'completo');
        var numCols = esCompleto ? 5 : 6;
        var body = [];
        if (esCompleto) {
            body.push([
                { text: 'TIP. NÓMINA', style: 'tableHeader' },
                { text: 'DEVENGADO', style: 'tableHeader', alignment: 'right' },
                { text: 'DEDUCCIONES', style: 'tableHeader', alignment: 'right' },
                { text: 'CESS', style: 'tableHeader', alignment: 'right' },
                { text: 'SAL. NETO', style: 'tableHeader', alignment: 'right' }
            ]);
            var tipoActual = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActual) {
                    tipoActual = f.tipo_nomina;
                    body.push([{ text: listadoTipoNominaLabel(f.tipo_nomina), bold: true, colSpan: numCols }, {}, {}, {}, {}]);
                }
                body.push([
                    { text: MESES_ESP[f.mes_num] },
                    { text: listadoFormatoNumero(f.devengado), alignment: 'right' },
                    { text: listadoFormatoNumero(f.deducciones), alignment: 'right' },
                    { text: listadoFormatoNumero(f.cess), alignment: 'right' },
                    { text: listadoFormatoNumero(f.neto), alignment: 'right' }
                ]);
            });
        } else {
            body.push([
                { text: 'TIP. NÓMINA', style: 'tableHeader' },
                { text: 'CANT. NOM', style: 'tableHeader', alignment: 'center' },
                { text: 'DEVENGADO', style: 'tableHeader', alignment: 'right' },
                { text: 'DEDUCCIONES', style: 'tableHeader', alignment: 'right' },
                { text: 'CESS', style: 'tableHeader', alignment: 'right' },
                { text: 'SAL. NETO', style: 'tableHeader', alignment: 'right' }
            ]);
            var tipoActualB = '';
            datosActual.filas.forEach(function(f) {
                if (f.tipo_nomina !== tipoActualB) {
                    tipoActualB = f.tipo_nomina;
                    body.push([{ text: listadoTipoNominaLabel(f.tipo_nomina), bold: true, colSpan: numCols }, {}, {}, {}, {}, {}]);
                }
                body.push([
                    { text: '' },
                    { text: String(f.cantidad), alignment: 'center' },
                    { text: listadoFormatoNumero(f.devengado), alignment: 'right' },
                    { text: listadoFormatoNumero(f.deducciones), alignment: 'right' },
                    { text: listadoFormatoNumero(f.cess), alignment: 'right' },
                    { text: listadoFormatoNumero(f.neto), alignment: 'right' }
                ]);
            });
        }
        var t = datosActual.totales;
        if (esCompleto) {
            body.push([
                { text: 'TOTAL GENERAL', bold: true, fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.devengado), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.deducciones), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.cess), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.neto), bold: true, alignment: 'right', fillColor: '#f0f4f8' }
            ]);
        } else {
            body.push([
                { text: 'TOTAL GENERAL', bold: true, fillColor: '#f0f4f8' },
                { text: String(t.cantidad), bold: true, alignment: 'center', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.devengado), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.deducciones), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.cess), bold: true, alignment: 'right', fillColor: '#f0f4f8' },
                { text: listadoFormatoNumero(t.neto), bold: true, alignment: 'right', fillColor: '#f0f4f8' }
            ]);
        }

        var docDefinition = {
            pageSize: 'LETTER',
            pageOrientation: 'portrait',
            pageMargins: [30, 30, 30, 40],
            content: [
                {
                    table: {
                        widths: [50, '*', 160],
                        body: [[
                            logoBase64 ? { image: logoBase64, width: 40, alignment: 'center' } : { text: '' },
                            { stack: [
                                { text: 'LISTADO TOTAL SALARIO DEVENGADO', fontSize: 12, bold: true, alignment: 'center' },
                                { text: (nombreEmpresa || '').toUpperCase(), fontSize: 9, alignment: 'center', margin: [0, 2, 0, 0] }
                            ]},
                            { stack: [
                                { text: 'Emisión: ' + new Date().toLocaleDateString('es-ES') + ' - ' + new Date().toLocaleTimeString('es-ES'), fontSize: 8, alignment: 'right' },
                                { text: 'REEUP: ' + (reeup || ''), fontSize: 8, alignment: 'right' },
                                { text: 'NIT: ' + (nitEmpresa || ''), fontSize: 8, alignment: 'right' }
                            ]}
                        ]]
                    },
                    layout: 'noBorders',
                    margin: [0, 0, 0, 10]
                },
                { text: 'MES: ' + mesTextoActual, fontSize: 10, bold: true, margin: [0, 4, 0, 2] },
                { text: 'NOMBRE DEL TRABAJADOR: ' + trabajadorActual.nombre_completo, fontSize: 10, bold: true, margin: [0, 0, 0, 2] },
                { text: 'ESTADO: ' + estadoSeleccionadoTxt(), fontSize: 9, margin: [0, 0, 0, 8] },
                {
                    columns: [
                        { width: '*', text: '' },
                        {
                            width: 'auto',
                            table: {
                                headerRows: 1,
                                widths: esCompleto ? ['*', '*', '*', '*', '*'] : ['*', '*', '*', '*', '*', '*'],
                                body: body
                            },
                            layout: { fillColor: function(rowIndex) { return rowIndex === 0 ? '#0891b2' : null; } }
                        },
                        { width: '*', text: '' }
                    ]
                },
                { text: '', margin: [0, 22, 0, 0] },
                {
                    columns: [
                        { width: '*', stack: [ { text: 'Elaborado por:', bold: true, fontSize: 9 }, { text: '', margin: [0, 30, 0, 0] }, { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] }, { text: 'Especialista de Nóminas', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] } ] },
                        { width: '*', stack: [ { text: 'Revisado por:', bold: true, fontSize: 9 }, { text: (especialistaGestion || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 30, 0, 0] }, { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] }, { text: 'Especialista en Gestión Económica', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] } ] },
                        { width: '*', stack: [ { text: 'Aprobado por:', bold: true, fontSize: 9 }, { text: (jefeProyecto || '').toUpperCase(), bold: true, fontSize: 8, margin: [0, 30, 0, 0] }, { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] }, { text: 'Director de Proyecto', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] } ] },
                        { width: '*', stack: [ { text: 'Contabilizado por:', bold: true, fontSize: 9 }, { text: '', margin: [0, 30, 0, 0] }, { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 130, y2: 0, lineWidth: 0.7 }] }, { text: 'Área Contable y Financiera', fontSize: 7.5, color: '#444444', margin: [0, 2, 0, 0] } ] }
                    ]
                }
            ],
            styles: { tableHeader: { color: '#ffffff', bold: true } },
            footer: function(currentPage, pageCount) {
                return { text: pieDocumento() + '   Página ' + currentPage + ' de ' + pageCount, fontSize: 7, color: '#666', alignment: 'center', margin: [0, 10, 0, 0] };
            }
        };
        pdfMake.createPdf(docDefinition).download(listadoNombreArchivo('pdf'));
    }

    function listadoExportar(tipo) {
        if (!datosActual) { listadoSwalError('Primero genere el reporte.'); return; }
        if (tipo === 'xls') listadoExportarXls();
        else if (tipo === 'csv') listadoExportarCsv();
        else if (tipo === 'txt') listadoExportarTxt();
        else if (tipo === 'docx') listadoExportarDocx();
        else if (tipo === 'pdf') listadoExportarPdf();
    }

    // ----------------------------------------------------------
    // Eventos (mismos del modal original)
    // ----------------------------------------------------------
    $('#listadoBuscarTrabajador').addEventListener('input', function() { listadoBuscar(this.value); });

    // Botón desplegar: muestra la lista completa de trabajadores
    $('#listadoBtnLista').addEventListener('click', function() {
        listadoAlternarLista();
    });

    $('#listadoLimpiarTrabajador').addEventListener('click', function() {
        $('#listadoTrabajadorId').value = '';
        $('#listadoBuscarTrabajador').value = '';
        $('#listadoResultadosBusqueda').style.display = 'none';
        $('#listadoResultadosBusqueda').innerHTML = '';
        $('#listadoTrabajadorInfo').innerHTML = '';
        listadoCargarMeses();
    });

    document.addEventListener('click', function(e) {
        var opt = e.target.closest('.listado-opt-trabajador');
        if (opt) {
            e.preventDefault();
            var id = parseInt(opt.getAttribute('data-id')) || 0;
            var t = null;
            trabajadoresTodos.forEach(function(item) { if (parseInt(item.id) === id) t = item; });
            if (t) listadoSeleccionarTrabajador(t);
            return;
        }
        // Cerrar lista de resultados al hacer clic fuera
        if (!e.target.closest('.su-buscador')) {
            $('#listadoResultadosBusqueda').style.display = 'none';
        }
        // Cerrar menú de exportación al hacer clic fuera
        if (!e.target.closest('.su-dropdown')) {
            $('#suMenuExportar').classList.remove('abierto');
        }
    });

    $('#listadoAnio').addEventListener('change', function() {
        $('#printAreaListadoDevengado').style.display = 'none';
        listadoCargarMeses();
    });
    $('#listadoMes').addEventListener('change', function() {
        $('#printAreaListadoDevengado').style.display = 'none';
    });
    $('#listadoEstado').addEventListener('change', function() { listadoCargarMeses(); });

    $('#btnGenerarListadoDevengado').addEventListener('click', function(e) { e.preventDefault(); listadoGenerarReporte(); });
    $('#btnImprimirListadoDevengado').addEventListener('click', function(e) { e.preventDefault(); listadoImprimir(); });
    $('#btnCerrarVentana').addEventListener('click', cerrarVentana);
    $('#suBtnX').addEventListener('click', cerrarVentana);

    $('#btnExportarListadoDevengado').addEventListener('click', function(e) {
        e.preventDefault();
        $('#suMenuExportar').classList.toggle('abierto');
    });
    document.querySelectorAll('[data-export-listado]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            $('#suMenuExportar').classList.remove('abierto');
            listadoExportar(a.getAttribute('data-export-listado'));
        });
    });

    // Inicio
    listadoCargarAnios();
})();
</script>

<!-- Modales informativos del footer (contenido idéntico a includes/footer.php) -->
<script>
var CONTENIDO_MODALES_FOOTER = {
    iso: {
        icono: 'fa-shield-halved',
        titulo: 'Certificación NC-ISO/IEC 27001',
        sub: 'Seguridad de la información · Datos protegidos',
        html: '<h4>¿Qué es la norma ISO/IEC 27001?</h4>' +
            '<p>La ISO/IEC 27001 es la norma más conocida del mundo para sistemas de gestión de la seguridad de la información (SGSI). Esta norma define los requisitos que debe cumplir un SGSI.</p>' +
            '<p>La norma ISO/IEC 27001 proporciona a las empresas de cualquier tamaño y de todos los sectores orientaciones para establecer, implantar, mantener y mejorar de manera continua un sistema de gestión de la seguridad de la información.</p>' +
            '<p>La conformidad con la ISO/IEC 27001 implica que una organización o empresa ha implantado un sistema para gestionar los riesgos relacionados con la seguridad de los datos que posee o maneja, y que este sistema respeta todas las buenas prácticas y principios contemplados en esta Norma Internacional.</p>' +
            '<h4>¿Por qué es importante la norma ISO/IEC 27001?</h4>' +
            '<p>Con el aumento de la ciberdelincuencia y la aparición constante de nuevas amenazas, puede parecer difícil o incluso imposible gestionar los riesgos cibernéticos. La ISO/IEC 27001 ayuda a las organizaciones a ser conscientes de dichos riesgos y a identificar y abordar los puntos débiles de forma proactiva.</p>' +
            '<p>La norma promueve un enfoque integral de la seguridad que abarca a las personas, las políticas y la tecnología, siendo una herramienta clave para la gestión de riesgos, la resiliencia cibernética y la excelencia operativa.</p>'
    },
    infra: {
        icono: 'fa-server',
        titulo: 'Infraestructura Local 100%',
        sub: 'Sin conexión a servicios externos · Soberanía total',
        html: '<h4>¿Qué significa Infraestructura Local 100%?</h4>' +
            '<p>Todo el sistema se ejecuta de forma íntegra dentro de la red o infraestructura de su institución. No se depende de servidores, servicios ni plataformas externas para su funcionamiento.</p>' +
            '<p>La base de datos, la lógica de negocio y los datos de los trabajadores residen en su propio servidor, bajo su control absoluto y con plena disponibilidad incluso sin conexión a internet.</p>' +
            '<h4>Ventajas de la infraestructura local</h4>' +
            '<p><b>Soberanía total:</b> los datos nunca salen de su organización ni son procesados por terceros.</p>' +
            '<p><b>Independencia:</b> no depende de la disponibilidad de servicios externos ni de planes de pago por suscripción.</p>' +
            '<p><b>Latencia mínima:</b> al operar en red local, las operaciones son más rápidas y fiables.</p>' +
            '<p><b>Privacidad:</b> al no haber conexiones externas, se elimina la exposición de información sensible a internet.</p>'
    },
    opensource: {
        icono: 'fa-code-branch',
        titulo: 'Open Source · Shareware <?php echo htmlspecialchars(SITE_VERSION, ENT_QUOTES, 'UTF-8'); ?>',
        sub: 'Código abierto · Sin fines de lucro · Libre distribución',
        html: '<h4>Software Libre y de Código Abierto</h4>' +
            '<p>Este sistema es de código abierto: puede estudiarse, usarse y distribuirse libremente. Su filosofía promueve la transparencia, la colaboración y la mejora continua por parte de la comunidad.</p>' +
            '<h4>Modelo Shareware</h4>' +
            '<p>El modelo shareware permite evaluar y utilizar el sistema sin fines de lucro. Se fomenta la libre distribución para que cada institución pueda beneficiarse de la herramienta.</p>' +
            '<h4>Compromisos del proyecto</h4>' +
            '<p><b>Sin fines de lucro:</b> el desarrollo persigue el beneficio colectivo, no la comercialización.</p>' +
            '<p><b>Libre distribución:</b> puede copiarse y compartirse respetando su licencia.</p>' +
            '<p><b>Transparencia:</b> el código es auditable por cualquier especialista que lo requiera.</p>'
    },
    auditable: {
        icono: 'fa-chart-line',
        titulo: 'Auditable y Transparente',
        sub: 'Traza completa · Cumplimiento normativo',
        html: '<h4>¿Qué es un sistema auditable?</h4>' +
            '<p>Un sistema auditable registra de forma fiable cada operación realizada, permitiendo reconstruir el historial completo de acciones sobre los datos y demostrar su integridad ante cualquier revisión interna o externa.</p>' +
            '<h4>Traza completa</h4>' +
            '<p>Queda constancia de quién realizó cada operación, cuándo y qué cambios se efectuaron sobre los registros de nóminas, trabajadores y configuraciones, garantizando la trazabilidad total del proceso.</p>' +
            '<h4>Cumplimiento normativo</h4>' +
            '<p>El sistema está alineado con la normativa laboral y contable vigente en el país (Ley 116 del Código de Trabajo y disposiciones complementarias), facilitando la conciliación, la fiscalización y la rendición de cuentas.</p>' +
            '<p><b>Transparencia:</b> cada resultado se sustenta en datos verificables y reproducibles, lo que refuerza la confianza en los procesos de pago y control.</p>'
    }
};

function abrirModalISO27001() { abrirModalFooter('iso'); }
function abrirModalFooter(tipo) {
    var cfg = CONTENIDO_MODALES_FOOTER[tipo] || CONTENIDO_MODALES_FOOTER.iso;
    document.getElementById('modalISOIcon').className = 'fas ' + cfg.icono + ' tt-icono';
    document.getElementById('modalISOTitulo').textContent = cfg.titulo;
    document.getElementById('modalISOSub').textContent = cfg.sub;
    document.getElementById('modalISOContenido').innerHTML = cfg.html;
    document.getElementById('modalISO27001').style.display = 'flex';
}
function cerrarModalISO27001() {
    document.getElementById('modalISO27001').style.display = 'none';
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarModalISO27001();
});
</script>

<!-- Tema oscuro de SweetAlert2 alineado al tema MAR (azul profundo) con acento CYAN -->
<script>
(function () {
    'use strict';
    var DARK_THEME = {
        background: '#101e38',
        color: '#dce8ff',
        confirmButtonColor: '#06b6d4',
        cancelButtonColor: '#243a5e',
        reverseButtons: true,
        backdrop: 'rgba(2, 6, 23, 0.78)'
    };
    var DARK_CUSTOM_CLASS = {
        popup: 'swal-dark-popup',
        title: 'swal-dark-title',
        htmlContainer: 'swal-dark-html',
        confirmButton: 'swal-dark-confirm',
        cancelButton: 'swal-dark-cancel'
    };
    function injectDarkCss() {
        var css =
            '.swal-dark-popup{border:1px solid rgba(6,182,212,0.35) !important;border-radius:16px !important;' +
            'backdrop-filter:blur(18px) !important;font-family:"Segoe UI",Tahoma,sans-serif;}' +
            '.swal-dark-title{color:#ffffff !important;}' +
            '.swal-dark-html{color:#dce8ff !important;}' +
            '.swal-dark-confirm,.swal-dark-cancel{border-radius:0.5rem !important;font-weight:600 !important;}';
        var style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }
    function applyTheme() {
        if (!window.Swal || typeof Swal.fire !== 'function') return;
        injectDarkCss();
        var origFire = Swal.fire.bind(Swal);
        Swal.fire = function (arg) {
            var opts = (typeof arg === 'string') ? { title: arg } : (arg || {});
            var merged = Object.assign({}, DARK_THEME, opts);
            if (opts.customClass && typeof opts.customClass === 'object') {
                merged.customClass = Object.assign({}, DARK_CUSTOM_CLASS, opts.customClass);
            } else {
                merged.customClass = DARK_CUSTOM_CLASS;
            }
            return origFire(merged);
        };
    }
    applyTheme();
})();
</script>

<!-- Portal de acceso por clave + botón Salir -->
<script>
(function () {
    var EMPRESA = <?php echo json_encode(COMPANY_NAME, JSON_UNESCAPED_UNICODE); ?>;

    function escapar(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function(c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    // ---------- Portal de acceso (si no hay sesión verificada) ----------
    if (!window.SU_AUTORIZADO) {
        var portal = document.getElementById('suGateOverlay');
        portal.classList.add('abierta');
        portal.style.display = 'flex'; // garantizado aunque falle la clase CSS
        document.body.style.overflow = 'hidden';
    }

    var input = document.getElementById('gateClave');
    var btn = document.getElementById('gateBtn');

    // Foco inicial en el campo de clave
    if (!window.SU_AUTORIZADO) {
        setTimeout(function () { input.focus(); }, 150);
    }

    document.getElementById('gateOjo').addEventListener('click', function () {
        var esPass = input.type === 'password';
        input.type = esPass ? 'text' : 'password';
        this.innerHTML = esPass ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });

    // Regresar al inicio del sitio
    document.getElementById('gateBtnInicio').addEventListener('click', function () {
        window.location.href = '../../index.php';
    });

    document.getElementById('formAcceso').addEventListener('submit', function (e) {
        e.preventDefault();
        var clave = input.value;
        if (!clave) {
            Swal.fire({
                icon: 'warning',
                title: 'Clave requerida',
                html: 'Debe introducir su clave de acceso para continuar.',
                confirmButtonText: '<i class="fas fa-check"></i> Entendido'
            });
            input.focus();
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        fetch('api.php?accion=verificar_clave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'clave=' + encodeURIComponent(clave)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-unlock"></i> Verificar acceso';
            if (d && d.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Bienvenido!',
                    html: 'Bienvenido <strong>' + escapar(d.nombre) + '</strong>, va ha acceder al Registro y control de salarios de trabajadores de:<br><strong>' + escapar(EMPRESA) + '</strong>.',
                    confirmButtonText: '<i class="fas fa-arrow-right"></i> Continuar',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(function () { window.location.reload(); });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: d && d.bloqueado ? 'Acceso bloqueado temporalmente' : 'No tiene acceso',
                    html: escapar(d && d.error) || 'La clave no existe dentro del sistema.',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido'
                }).then(function () { input.value = ''; input.focus(); });
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-unlock"></i> Verificar acceso';
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                html: 'No se pudo contactar con el servidor. Intente nuevamente.',
                confirmButtonText: '<i class="fas fa-rotate-right"></i> Reintentar'
            });
        });
    });

    // ---------- Cierre de acceso compartido (misma rutina que suBtnSalir) ----------
    function cerrarAccesoSu(despues) {
        fetch('api.php?accion=cerrar_acceso', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { success: true }; })
            .then(function () {
                try { localStorage.clear(); sessionStorage.clear(); } catch (e) {}
                document.cookie.split(';').forEach(function (c) {
                    var nombre = c.split('=')[0].trim();
                    if (nombre) {
                        document.cookie = nombre + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
                        document.cookie = nombre + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=' + window.location.pathname;
                    }
                });
                if (typeof despues === 'function') { despues(); }
                else { window.location.replace(window.location.pathname); }
            });
    }

    // ---------- Botón Salir / Cerrar (regresa al estado inicial, con confirmación) ----------
    document.getElementById('suBtnSalir').addEventListener('click', function () {
        Swal.fire({
            icon: 'question',
            title: '¿Salir / Cerrar?',
            html: 'Se cerrará su acceso y se eliminarán las cookies y datos de la sesión.<br>La página volverá a su estado inicial.',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-right-from-bracket"></i> Sí, salir',
            cancelButtonText: '<i class="fas fa-ban"></i> Cancelar',
            confirmButtonColor: '#dc2626'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            cerrarAccesoSu();
        });
    });

    // ---------- Opciones del menú lateral: cerrar acceso SIN modal y luego navegar ----------
    document.querySelectorAll('.su-side-nav a').forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            e.preventDefault();
            var destino = this.href;
            cerrarAccesoSu(function () { window.location.replace(destino); });
        });
    });
})();
</script>

<!-- Reloj analógico plano, hora digital y fecha -->
<script>
(function () {
    var DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var svgNS = 'http://www.w3.org/2000/svg';

    // Marcas de las horas (flat)
    var ticks = document.getElementById('suRelojTicks');
    if (ticks) {
        for (var i = 0; i < 12; i++) {
            var mayor = (i % 3 === 0);
            var ln = document.createElementNS(svgNS, 'line');
            ln.setAttribute('x1', '50'); ln.setAttribute('y1', '7');
            ln.setAttribute('x2', '50'); ln.setAttribute('y2', mayor ? '13' : '11');
            ln.setAttribute('stroke', mayor ? '#67e8f9' : 'rgba(103,232,249,0.45)');
            ln.setAttribute('stroke-width', mayor ? '2.5' : '1.5');
            ln.setAttribute('stroke-linecap', 'round');
            ln.setAttribute('transform', 'rotate(' + (i * 30) + ' 50 50)');
            ticks.appendChild(ln);
        }
    }

    function p2(n) { return (n < 10 ? '0' : '') + n; }

    function actualizarReloj() {
        var f = new Date();
        var h = f.getHours();
        var sufijo = h >= 12 ? 'p.m.' : 'a.m.';
        var h12 = h % 12; if (h12 === 0) h12 = 12;

        var horaEl = document.getElementById('suRelojHora');
        var fechaEl = document.getElementById('suRelojFecha');
        if (!horaEl || !fechaEl) return;

        horaEl.innerHTML = '<i class="fas fa-clock"></i> ' + h12 + ':' + p2(f.getMinutes()) + ':' + p2(f.getSeconds())
            + ' <small>' + sufijo + '</small>';
        fechaEl.innerHTML = '<i class="fas fa-calendar-day"></i> Hoy es: ' + DIAS[f.getDay()] + ', '
            + f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear();

        var seg = f.getSeconds();
        var min = f.getMinutes() + seg / 60;
        var hor = (h % 12) + min / 60;
        document.getElementById('suManecillaSegundo').setAttribute('transform', 'rotate(' + (seg * 6) + ' 50 50)');
        document.getElementById('suManecillaMinuto').setAttribute('transform', 'rotate(' + (min * 6) + ' 50 50)');
        document.getElementById('suManecillaHora').setAttribute('transform', 'rotate(' + (hor * 30) + ' 50 50)');
    }

    actualizarReloj();
    setInterval(actualizarReloj, 1000);
})();
</script>

<!-- Menú lateral móvil (cajón deslizante) -->
<script>
(function () {
    var sidebar = document.getElementById('suSidebar');
    var fondo = document.getElementById('suSidebarFondo');
    function cerrarMenu() {
        sidebar.classList.remove('abierta');
        fondo.classList.remove('abierto');
    }
    document.getElementById('suBtnMenu').addEventListener('click', function () {
        sidebar.classList.toggle('abierta');
        fondo.classList.toggle('abierto');
    });
    fondo.addEventListener('click', cerrarMenu);
    sidebar.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', cerrarMenu); });
})();
</script>

<!-- Pie minimizado automaticamente en moviles -->
<script>
(function () {
    'use strict';
    var esMovil = window.matchMedia('(max-width: 48rem)').matches
        || /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry|Opera Mini/i.test(navigator.userAgent);
    if (!esMovil) return;
    var footer = document.querySelector('.su-corp-footer');
    var btn = document.getElementById('corpMinBtn');
    if (!footer || !btn) return;
    document.body.classList.add('su-movil');
    footer.classList.remove('su-footer-abierto'); // inicia minimizado
    btn.addEventListener('click', function () {
        footer.classList.toggle('su-footer-abierto');
    });
    // Al girar/redimensionar a escritorio, restaurar el pie completo
    window.matchMedia('(max-width: 48rem)').addEventListener('change', function (e) {
        if (!e.matches) {
            document.body.classList.remove('su-movil');
            footer.classList.remove('su-footer-abierto');
        }
    });
})();
</script>
</body>
</html>
