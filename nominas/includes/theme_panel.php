<?php
/**
 * theme_panel.php - Panel de personalización flyout
 *
 * Se incluye en user_menu.php al final del body.
 * Contiene el panel deslizante desde la derecha con 10 opciones de tema.
 */
?>
<style id="theme-panel-css">
/* ===== THEME PANEL FLYOUT ===== */
.tp-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:10500;opacity:0;visibility:hidden;transition:opacity 0.3s ease,visibility 0s linear 0.3s;}
.tp-overlay.active{opacity:1;visibility:visible;transition:opacity 0.3s ease;}

.tp-panel{position:fixed;top:0;right:0;width:23.75rem;max-width:92vw;height:100vh;height:100dvh;background:var(--panel,#10151f);border-left:0.0625rem solid var(--border,rgba(255,255,255,0.08));z-index:10501;display:flex;flex-direction:column;box-shadow:-0.5rem 0 2rem rgba(0,0,0,0.3);transform:translateX(102%);visibility:hidden;transition:transform 0.35s cubic-bezier(0.4,0,0.2,1),visibility 0s linear 0.35s;}
.tp-panel.active{transform:translateX(0);visibility:visible;transition:transform 0.35s cubic-bezier(0.4,0,0.2,1);}

/* Respetar el toggle global de animaciones */
.no-animations .tp-panel,.no-animations .tp-overlay{transition:none!important;}

.tp-header{display:flex;align-items:center;justify-content:space-between;padding:1.125rem 1.375rem 0.875rem;border-bottom:0.0625rem solid var(--border,rgba(255,255,255,0.08));flex-shrink:0;}
.tp-header h3{margin:0;font-size:1rem;font-weight:700;color:var(--txt,#e8edf6);display:flex;align-items:center;gap:0.5rem;}
.tp-header h3 i{color:var(--accent,#3b82f6);font-size:0.95rem;}
.tp-close{background:none;border:none;color:var(--muted,#97a5bb);font-size:1.1rem;cursor:pointer;padding:0.375rem;border-radius:0.5rem;transition:all 0.2s;display:flex;align-items:center;justify-content:center;}
.tp-close:hover{background:rgba(255,255,255,0.08);color:var(--txt,#e8edf6);}

.tp-body{flex:1;overflow-y:auto;padding:1.125rem 1.375rem 1.5rem;}
.tp-body::-webkit-scrollbar{width:0.3125rem;}
.tp-body::-webkit-scrollbar-track{background:transparent;}
.tp-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.15);border-radius:0.625rem;}

/* Section */
.tp-section{margin-bottom:1.375rem;}
.tp-section-title{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.0625rem;color:var(--muted,#97a5bb);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.375rem;}
.tp-section-title i{font-size:0.7rem;color:var(--accent,#3b82f6);}
.tp-divider{border:none;border-top:0.0625rem solid var(--border,rgba(255,255,255,0.06));margin:1.25rem 0;}

/* Theme toggle */
.tp-theme-grid{display:flex;gap:0.625rem;flex-wrap:wrap;}
.tp-theme-btn{flex:1 1 calc(33.333% - 0.47rem);padding:0.875rem 0.625rem;border-radius:0.75rem;border:0.125rem solid var(--border,rgba(255,255,255,0.08));background:transparent;cursor:pointer;text-align:center;transition:all 0.25s;display:flex;flex-direction:column;align-items:center;gap:0.375rem;}
.tp-theme-btn:hover{border-color:rgba(255,255,255,0.2);background:rgba(255,255,255,0.03);}
.tp-theme-btn.active{border-color:var(--accent,#3b82f6);background:rgba(59,130,246,0.08);}
.tp-theme-btn i{font-size:1.4rem;}
.tp-theme-btn span{font-size:0.75rem;font-weight:600;color:var(--txt,#e8edf6);}
.tp-theme-btn.dark-theme i{color:#fbbf24;}
.tp-theme-btn.light-theme i{color:#f97316;}
.tp-theme-btn.blue-theme i{color:#60a5fa;}
.tp-theme-btn.verde-theme i{color:#34d399;}
.tp-theme-btn.orgullo-theme i{color:#a78bfa;}

/* Accent colors */
.tp-accent-grid{display:flex;gap:0.5rem;flex-wrap:wrap;}
.tp-accent-dot{width:2rem;height:2rem;border-radius:50%;border:0.1875rem solid transparent;cursor:pointer;transition:all 0.2s;position:relative;}
.tp-accent-dot:hover{transform:scale(1.15);}
.tp-accent-dot.active{border-color:var(--txt,#e8edf6);box-shadow:0 0 0 0.125rem var(--panel,#10151f),0 0 0 0.25rem currentColor;}

/* Radio group */
.tp-radio-group{display:flex;gap:0.375rem;}
.tp-radio{flex:1;padding:0.5rem 0.375rem;border-radius:0.5rem;border:0.0625rem solid var(--border,rgba(255,255,255,0.1));background:transparent;cursor:pointer;text-align:center;font-size:0.75rem;font-weight:600;color:var(--muted,#97a5bb);transition:all 0.2s;}
.tp-radio:hover{border-color:rgba(255,255,255,0.2);color:var(--txt,#e8edf6);}
.tp-radio.active{border-color:var(--accent,#3b82f6);background:rgba(59,130,246,0.1);color:var(--accent,#3b82f6);}

/* Device selector */
#tpDeviceGroup .tp-radio{display:flex;flex-direction:column;align-items:center;gap:0.25rem;padding:0.5625rem 0.375rem;}
#tpDeviceGroup .tp-radio i{font-size:1.05rem;}
#tpDeviceGroup .tp-radio span{font-size:0.65rem;font-weight:600;}

/* Toggle switch */
.tp-row{display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0;}
.tp-row-label{font-size:0.82rem;color:var(--txt,#e8edf6);font-weight:500;}
.tp-toggle{position:relative;width:2.75rem;height:1.5rem;cursor:pointer;flex-shrink:0;}
.tp-toggle input{display:none;}
.tp-toggle-track{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.12);border-radius:0.75rem;transition:background 0.3s;}
.tp-toggle input:checked+.tp-toggle-track{background:var(--accent,#3b82f6);}
.tp-toggle-knob{position:absolute;top:0.1875rem;left:0.1875rem;width:1.125rem;height:1.125rem;background:#fff;border-radius:50%;transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);box-shadow:0 0.0625rem 0.1875rem rgba(0,0,0,0.2);}
.tp-toggle input:checked~.tp-toggle-knob{transform:translateX(1.25rem);}

/* Slider */
.tp-slider-row{padding:0.625rem 0;}
.tp-slider-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;}
.tp-slider-header span{font-size:0.82rem;color:var(--txt,#e8edf6);font-weight:500;}
.tp-slider-val{font-size:0.75rem;color:var(--accent,#3b82f6);font-weight:700;min-width:2.1875rem;text-align:right;}
.tp-slider{-webkit-appearance:none;width:100%;height:0.375rem;border-radius:0.1875rem;background:rgba(255,255,255,0.12);outline:none;transition:background 0.2s;}
.tp-slider::-webkit-slider-thumb{-webkit-appearance:none;width:1.125rem;height:1.125rem;border-radius:50%;background:var(--accent,#3b82f6);cursor:pointer;box-shadow:0 0.0625rem 0.25rem rgba(0,0,0,0.3);transition:transform 0.2s;}
.tp-slider::-webkit-slider-thumb:hover{transform:scale(1.15);}

/* Content width chips */
.tp-chips{display:flex;gap:0.375rem;}
.tp-chip{flex:1;padding:0.5rem 0.375rem;border-radius:0.5rem;border:0.0625rem solid var(--border,rgba(255,255,255,0.1));background:transparent;cursor:pointer;text-align:center;font-size:0.72rem;font-weight:600;color:var(--muted,#97a5bb);transition:all 0.2s;}
.tp-chip:hover{border-color:rgba(255,255,255,0.2);color:var(--txt,#e8edf6);}
.tp-chip.active{border-color:var(--accent,#3b82f6);background:rgba(59,130,246,0.1);color:var(--accent,#3b82f6);}

/* Footer buttons */
.tp-footer{padding:1rem 1.375rem;border-top:0.0625rem solid var(--border,rgba(255,255,255,0.08));display:flex;flex-direction:column;gap:0.5rem;flex-shrink:0;}
.tp-btn-save{width:100%;padding:0.6875rem;border:none;border-radius:0.625rem;background:var(--accent,#3b82f6);color:#fff;font-size:0.85rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
.tp-btn-save:hover{filter:brightness(1.15);transform:translateY(-0.0625rem);}
.tp-btn-reset{width:100%;padding:0.5625rem;border:0.0625rem solid var(--border,rgba(255,255,255,0.1));border-radius:0.625rem;background:transparent;color:var(--muted,#97a5bb);font-size:0.8rem;font-weight:600;cursor:pointer;transition:all 0.2s;}
.tp-btn-reset:hover{border-color:rgba(239,68,68,0.4);color:#ef4444;background:rgba(239,68,68,0.05);}

/* Badge de configuración personalizada en el botón de settings */
.tp-custom-badge{position:absolute;top:-0.3125rem;right:-0.3125rem;min-width:1rem;height:1rem;padding:0 0.2188rem;border-radius:999px;background:#ef4444;color:#fff;font-size:0.625rem;font-weight:800;line-height:1rem;text-align:center;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.4);z-index:10;pointer-events:none;animation:tpBadgePop 0.25s ease;}
@keyframes tpBadgePop{from{transform:scale(0);}to{transform:scale(1);}}

/* Focus mode indicator */
[data-theme="light"] .tp-panel{background:#ffffff;border-left-color:rgba(0,0,0,0.1);}
[data-theme="light"] .tp-header{border-bottom-color:rgba(0,0,0,0.08);}
[data-theme="light"] .tp-header h3{color:#1f2937;}
[data-theme="light"] .tp-close{color:#6b7280;}
[data-theme="light"] .tp-close:hover{background:rgba(0,0,0,0.05);color:#1f2937;}
[data-theme="light"] .tp-section-title{color:#6b7280;}
[data-theme="light"] .tp-theme-btn{border-color:rgba(0,0,0,0.1);}
[data-theme="light"] .tp-theme-btn:hover{border-color:rgba(0,0,0,0.2);background:rgba(0,0,0,0.02);}
[data-theme="light"] .tp-theme-btn.active{border-color:var(--accent);background:rgba(59,130,246,0.06);}
[data-theme="light"] .tp-theme-btn span{color:#1f2937;}
[data-theme="light"] .tp-radio{border-color:rgba(0,0,0,0.12);color:#6b7280;}
[data-theme="light"] .tp-radio:hover{border-color:rgba(0,0,0,0.2);color:#1f2937;}
[data-theme="light"] .tp-radio.active{border-color:var(--accent);background:rgba(59,130,246,0.06);color:var(--accent);}
[data-theme="light"] .tp-row-label{color:#1f2937;}
[data-theme="light"] .tp-toggle-track{background:rgba(0,0,0,0.12);}
[data-theme="light"] .tp-slider{background:rgba(0,0,0,0.12);}
[data-theme="light"] .tp-chip{border-color:rgba(0,0,0,0.12);color:#6b7280;}
[data-theme="light"] .tp-chip:hover{border-color:rgba(0,0,0,0.2);color:#1f2937;}
[data-theme="light"] .tp-chip.active{border-color:var(--accent);background:rgba(59,130,246,0.06);color:var(--accent);}
[data-theme="light"] .tp-divider{border-top-color:rgba(0,0,0,0.06);}
[data-theme="light"] .tp-footer{border-top-color:rgba(0,0,0,0.08);}
[data-theme="light"] .tp-btn-reset{border-color:rgba(0,0,0,0.12);color:#6b7280;}
[data-theme="light"] .tp-btn-reset:hover{border-color:rgba(239,68,68,0.4);color:#dc2626;background:rgba(239,68,68,0.04);}
/* Blue theme panel overrides — lighter */
[data-theme="blue"] .tp-panel{background:#1a3050;border-left-color:rgba(130,190,255,0.18);}
[data-theme="blue"] .tp-header{border-bottom-color:rgba(130,190,255,0.15);}
[data-theme="blue"] .tp-header h3{color:#dce8ff;}
[data-theme="blue"] .tp-close{color:#9dbde8;}
[data-theme="blue"] .tp-close:hover{background:rgba(10,20,40,0.5);color:#dce8ff;}
[data-theme="blue"] .tp-section-title{color:#9dbde8;}
[data-theme="blue"] .tp-section-title i{color:var(--accent);}
[data-theme="blue"] .tp-theme-btn{border-color:rgba(130,190,255,0.18);color:#dce8ff;}
[data-theme="blue"] .tp-theme-btn:hover{border-color:rgba(59,130,246,0.3);background:rgba(10,20,40,0.4);}
[data-theme="blue"] .tp-theme-btn.active{border-color:var(--accent);background:rgba(59,130,246,0.12);}
[data-theme="blue"] .tp-theme-btn span{color:#dce8ff;}
[data-theme="blue"] .tp-radio{border-color:rgba(130,190,255,0.18);color:#9dbde8;}
[data-theme="blue"] .tp-radio:hover{border-color:rgba(59,130,246,0.3);color:#dce8ff;}
[data-theme="blue"] .tp-radio.active{border-color:var(--accent);background:rgba(59,130,246,0.12);color:var(--accent);}
[data-theme="blue"] .tp-row-label{color:#dce8ff;}
[data-theme="blue"] .tp-toggle-track{background:rgba(130,190,255,0.15);}
[data-theme="blue"] .tp-slider{background:rgba(130,190,255,0.15);}
[data-theme="blue"] .tp-slider-val{color:var(--accent);}
[data-theme="blue"] .tp-chip{border-color:rgba(130,190,255,0.18);color:#9dbde8;}
[data-theme="blue"] .tp-chip:hover{border-color:rgba(59,130,246,0.3);color:#dce8ff;}
[data-theme="blue"] .tp-chip.active{border-color:var(--accent);background:rgba(59,130,246,0.12);color:var(--accent);}
[data-theme="blue"] .tp-divider{border-top-color:rgba(130,190,255,0.12);}
[data-theme="blue"] .tp-footer{border-top-color:rgba(130,190,255,0.15);}
[data-theme="blue"] .tp-btn-reset{border-color:rgba(130,190,255,0.18);color:#9dbde8;}
[data-theme="blue"] .tp-btn-reset:hover{border-color:rgba(239,68,68,0.4);color:#ef4444;background:rgba(239,68,68,0.06);}
/* Verde / Naturaleza theme panel overrides */
[data-theme="verde"] .tp-panel{background:#162a18;border-left-color:rgba(120,200,130,0.18);}
[data-theme="verde"] .tp-header{border-bottom-color:rgba(120,200,130,0.15);}
[data-theme="verde"] .tp-header h3{color:#d4f0d8;}
[data-theme="verde"] .tp-close{color:#8ec99a;}
[data-theme="verde"] .tp-close:hover{background:rgba(5,25,10,0.5);color:#d4f0d8;}
[data-theme="verde"] .tp-section-title{color:#8ec99a;}
[data-theme="verde"] .tp-section-title i{color:var(--accent);}
[data-theme="verde"] .tp-theme-btn{border-color:rgba(120,200,130,0.18);color:#d4f0d8;}
[data-theme="verde"] .tp-theme-btn:hover{border-color:rgba(52,211,153,0.3);background:rgba(5,25,10,0.4);}
[data-theme="verde"] .tp-theme-btn.active{border-color:var(--accent);background:rgba(52,211,153,0.12);}
[data-theme="verde"] .tp-theme-btn span{color:#d4f0d8;}
[data-theme="verde"] .tp-radio{border-color:rgba(120,200,130,0.18);color:#8ec99a;}
[data-theme="verde"] .tp-radio:hover{border-color:rgba(52,211,153,0.3);color:#d4f0d8;}
[data-theme="verde"] .tp-radio.active{border-color:var(--accent);background:rgba(52,211,153,0.12);color:var(--accent);}
[data-theme="verde"] .tp-row-label{color:#d4f0d8;}
[data-theme="verde"] .tp-toggle-track{background:rgba(120,200,130,0.15);}
[data-theme="verde"] .tp-slider{background:rgba(120,200,130,0.15);}
[data-theme="verde"] .tp-slider-val{color:var(--accent);}
[data-theme="verde"] .tp-chip{border-color:rgba(120,200,130,0.18);color:#8ec99a;}
[data-theme="verde"] .tp-chip:hover{border-color:rgba(52,211,153,0.3);color:#d4f0d8;}
[data-theme="verde"] .tp-chip.active{border-color:var(--accent);background:rgba(52,211,153,0.12);color:var(--accent);}
[data-theme="verde"] .tp-divider{border-top-color:rgba(120,200,130,0.12);}
[data-theme="verde"] .tp-footer{border-top-color:rgba(120,200,130,0.15);}
[data-theme="verde"] .tp-btn-reset{border-color:rgba(120,200,130,0.18);color:#8ec99a;}
[data-theme="verde"] .tp-btn-reset:hover{border-color:rgba(239,68,68,0.4);color:#ef4444;background:rgba(239,68,68,0.06);}
/* Orgullo / Púrpura semiclaro theme panel overrides */
[data-theme="orgullo"] .tp-panel{background:#f0e8fa;border-left-color:rgba(139,92,246,0.28);}
[data-theme="orgullo"] .tp-header{border-bottom-color:rgba(139,92,246,0.2);}
[data-theme="orgullo"] .tp-header h3{color:#33264d;}
[data-theme="orgullo"] .tp-close{color:#77689c;}
[data-theme="orgullo"] .tp-close:hover{background:rgba(139,92,246,0.12);color:#33264d;}
[data-theme="orgullo"] .tp-section-title{color:#77689c;}
[data-theme="orgullo"] .tp-section-title i{color:#7c3aed;}
[data-theme="orgullo"] .tp-divider{border-top-color:rgba(139,92,246,0.16);}
[data-theme="orgullo"] .tp-theme-btn{border-color:rgba(139,92,246,0.25);}
[data-theme="orgullo"] .tp-theme-btn:hover{border-color:rgba(139,92,246,0.45);background:rgba(139,92,246,0.07);}
[data-theme="orgullo"] .tp-theme-btn.active{border-color:var(--accent);background:rgba(59,130,246,0.06);}
[data-theme="orgullo"] .tp-theme-btn span{color:#33264d;}
[data-theme="orgullo"] .tp-radio{border-color:rgba(51,38,77,0.15);color:#77689c;}
[data-theme="orgullo"] .tp-radio:hover{border-color:rgba(139,92,246,0.45);color:#33264d;}
[data-theme="orgullo"] .tp-radio.active{border-color:var(--accent);background:rgba(59,130,246,0.06);color:var(--accent);}
[data-theme="orgullo"] .tp-row-label{color:#33264d;}
[data-theme="orgullo"] .tp-toggle-track{background:rgba(51,38,77,0.15);}
[data-theme="orgullo"] .tp-slider{background:rgba(51,38,77,0.15);}
[data-theme="orgullo"] .tp-chip{border-color:rgba(51,38,77,0.15);color:#77689c;}
[data-theme="orgullo"] .tp-chip:hover{border-color:rgba(139,92,246,0.45);color:#33264d;}
[data-theme="orgullo"] .tp-chip.active{border-color:var(--accent);background:rgba(59,130,246,0.06);color:var(--accent);}
[data-theme="orgullo"] .tp-footer{border-top-color:rgba(139,92,246,0.2);}
[data-theme="orgullo"] .tp-btn-reset{border-color:rgba(51,38,77,0.15);color:#77689c;}
[data-theme="orgullo"] .tp-btn-reset:hover{border-color:rgba(239,68,68,0.4);color:#dc2626;background:rgba(239,68,68,0.04);}


</style>

<!-- Overlay -->
<div class="tp-overlay" id="tpOverlay"></div>

<!-- Panel -->
<div class="tp-panel" id="tpPanel">
    <div class="tp-header">
        <h3><i class="fas fa-sliders"></i> Personalizaci&oacute;n</h3>
        <button class="tp-close" id="tpClose" data-tooltip="Cerrar" data-tooltip-theme="secondary"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="tp-body">
        <!-- ====== TEMA ====== -->
        <div class="tp-section">
            <div class="tp-section-title"><i class="fas fa-circle-half-stroke"></i> Tema del Sistema</div>
            <div class="tp-theme-grid">
                <button class="tp-theme-btn dark-theme" data-tp-theme="dark">
                    <i class="fas fa-moon"></i>
                    <span>Oscuro</span>
                </button>
                <button class="tp-theme-btn light-theme" data-tp-theme="light">
                    <i class="fas fa-sun"></i>
                    <span>Claro</span>
                </button>
                <button class="tp-theme-btn blue-theme" data-tp-theme="blue">
                    <i class="fas fa-water"></i>
                    <span>Mar</span>
                </button>
                <button class="tp-theme-btn verde-theme" data-tp-theme="verde">
                    <i class="fas fa-leaf"></i>
                    <span>Naturaleza</span>
                </button>
                <button class="tp-theme-btn orgullo-theme" data-tp-theme="orgullo">
                    <i class="fas fa-gem"></i>
                    <span>Orgullo</span>
                </button>
            </div>
        </div>

        <hr class="tp-divider">

        <!-- ====== COLOR DE ÉNFASIS ====== -->
        <div class="tp-section">
            <div class="tp-section-title"><i class="fas fa-palette"></i> Color de &Eacute;nfasis</div>
            <div class="tp-accent-grid" id="tpAccentGrid">
                <div class="tp-accent-dot" data-accent="blue" style="background:#3b82f6;" data-tooltip="Azul" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="purple" style="background:#8b5cf6;" data-tooltip="P&uacute;rpura" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="green" style="background:#10b981;" data-tooltip="Verde" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="amber" style="background:#f59e0b;" data-tooltip="&Aacute;mbar" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="red" style="background:#ef4444;" data-tooltip="Rojo" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="cyan" style="background:#06b6d4;" data-tooltip="Cian" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="teal" style="background:#14b8a6;" data-tooltip="Teal" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="pink" style="background:#ec4899;" data-tooltip="Rosa" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="indigo" style="background:#6366f1;" data-tooltip="Índigo" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="rose" style="background:#f43f5e;" data-tooltip="Rosado" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="orange" style="background:#f97316;" data-tooltip="Naranja" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="lime" style="background:#84cc16;" data-tooltip="Lima" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="fuchsia" style="background:#d946ef;" data-tooltip="Fucsia" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="slate" style="background:#64748b;" data-tooltip="Pizarra" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="forest" style="background:#228b22;" data-tooltip="Verde Bosque" data-tooltip-theme="info"></div>
                <div class="tp-accent-dot" data-accent="navy" style="background:#1e3a8a;" data-tooltip="Azul Oscuro" data-tooltip-theme="info"></div>
            </div>
        </div>

        <hr class="tp-divider">

        <!-- ====== DISEÑO ====== -->
        <div class="tp-section">
            <div class="tp-section-title"><i class="fas fa-paint-brush"></i> Dise&ntilde;o</div>

            <div class="tp-row">
                <span class="tp-row-label">Radio de bordes</span>
            </div>
            <div class="tp-radio-group" id="tpRadiusGroup">
                <button class="tp-radio" data-val="6">0.375rem</button>
                <button class="tp-radio active" data-val="10">0.625rem</button>
                <button class="tp-radio" data-val="16">1rem</button>
            </div>

            <div class="tp-row" style="margin-top:0.75rem;">
                <span class="tp-row-label">Tama&ntilde;o de fuente</span>
            </div>
            <div class="tp-radio-group" id="tpFontGroup">
                <button class="tp-radio" data-val="0.85">S</button>
                <button class="tp-radio active" data-val="1.0">M</button>
                <button class="tp-radio" data-val="1.1">L</button>
            </div>

            <div class="tp-row" style="margin-top:0.75rem;">
                <span class="tp-row-label">Densidad tablas</span>
            </div>
            <div class="tp-radio-group" id="tpDensityGroup">
                <button class="tp-radio" data-val="compact">C</button>
                <button class="tp-radio active" data-val="normal">N</button>
                <button class="tp-radio" data-val="comfortable">Co</button>
            </div>
        </div>

        <hr class="tp-divider">

        <!-- ====== INTERFAZ ====== -->
        <div class="tp-section">
            <div class="tp-section-title"><i class="fas fa-desktop"></i> Interfaz</div>

            <div class="tp-row">
                <span class="tp-row-label">Vista de dispositivo</span>
            </div>
            <div class="tp-radio-group" id="tpDeviceGroup">
                <button class="tp-radio" data-val="pc" data-tooltip="Monitor PC" data-tooltip-theme="info"><i class="fas fa-desktop"></i><span>PC</span></button>
                <button class="tp-radio" data-val="tableta" data-tooltip="Tablet" data-tooltip-theme="info"><i class="fas fa-tablet-screen-button"></i><span>Tablet</span></button>
                <button class="tp-radio" data-val="movil" data-tooltip="M&oacute;vil" data-tooltip-theme="info"><i class="fas fa-mobile-screen"></i><span>M&oacute;vil</span></button>
            </div>
            <div class="tp-row" id="tpPerDevInfo" style="padding-top:0.375rem;">
                <span class="tp-row-label" style="font-size:0.72rem;color:var(--muted,#97a5bb);">Perfil activo: <strong id="tpPerDevName" style="color:var(--accent,#3b82f6);">PC</strong>&nbsp;<span id="tpPerDevMode" style="opacity:0.75;">(auto)</span></span>
            </div>
            <div class="tp-row" style="padding-top:0.5rem;">
                <span class="tp-row-label">Detectar dispositivo autom&aacute;ticamente</span>
                <label class="tp-toggle"><input type="checkbox" id="tpPerDevice"><span class="tp-toggle-track"></span><span class="tp-toggle-knob"></span></label>
            </div>

            <div class="tp-row">
                <span class="tp-row-label">Sidebar compacto</span>
                <label class="tp-toggle"><input type="checkbox" id="tpSidebarCompact"><span class="tp-toggle-track"></span><span class="tp-toggle-knob"></span></label>
            </div>

            <div class="tp-slider-row">
                <div class="tp-slider-header">
                    <span>Opacidad sidebar</span>
                    <span class="tp-slider-val" id="tpOpacityVal">95%</span>
                </div>
                <input type="range" class="tp-slider" id="tpSidebarOpacity" min="10" max="100" value="95" step="5">
            </div>

            <div class="tp-row" style="margin-top:0.25rem;">
                <span class="tp-row-label">Ancho del contenido</span>
            </div>
            <div class="tp-chips" id="tpContentWidthGroup">
                <button class="tp-chip active" data-val="100">100%</button>
                <button class="tp-chip" data-val="1200">75rem</button>
                <button class="tp-chip" data-val="1400">87.5rem</button>
            </div>

            <div class="tp-row" style="margin-top:0.75rem;">
                <span class="tp-row-label">Animaciones</span>
                <label class="tp-toggle"><input type="checkbox" id="tpAnimations" checked><span class="tp-toggle-track"></span><span class="tp-toggle-knob"></span></label>
            </div>

            <div class="tp-row">
                <span class="tp-row-label">Modo enfoque</span>
                <label class="tp-toggle"><input type="checkbox" id="tpFocusMode"><span class="tp-toggle-track"></span><span class="tp-toggle-knob"></span></label>
            </div>

            <div class="tp-row">
                <span class="tp-row-label">Tooltips</span>
                <label class="tp-toggle"><input type="checkbox" id="tpTooltips" checked><span class="tp-toggle-track"></span><span class="tp-toggle-knob"></span></label>
            </div>
        </div>
    </div>

    <div class="tp-footer">
        <button class="tp-btn-save" id="tpSave"><i class="fas fa-check"></i> Guardar Cambios</button>
        <button class="tp-btn-reset" id="tpReset"><i class="fas fa-rotate-left"></i> Restaurar Predeterminado</button>
    </div>
</div>

<script>
(function(){
/* ===== CONSTANTS ===== */
var STORAGE_KEY='transnubet_theme';
var ACCENTS={blue:{c:'#3b82f6',r:'59,130,246'},purple:{c:'#8b5cf6',r:'139,92,246'},green:{c:'#10b981',r:'16,185,129'},amber:{c:'#f59e0b',r:'245,158,11'},red:{c:'#ef4444',r:'239,68,68'},cyan:{c:'#06b6d4',r:'6,182,212'},teal:{c:'#14b8a6',r:'20,184,166'},pink:{c:'#ec4899',r:'236,72,153'},indigo:{c:'#6366f1',r:'99,102,241'},rose:{c:'#f43f5e',r:'244,63,94'},orange:{c:'#f97316',r:'249,115,22'},lime:{c:'#84cc16',r:'132,204,22'},fuchsia:{c:'#d946ef',r:'217,70,239'},slate:{c:'#64748b',r:'100,116,139'},forest:{c:'#228b22',r:'34,139,34'},navy:{c:'#1e3a8a',r:'30,58,138'}};
var DEFAULTS={theme:'dark',radius:'10',font_size:'1.0',density:'normal',sidebar_compact:'false',sidebar_opacity:'95',content_width:'100',animations:'true',focus_mode:'false',tooltips:'true',per_device:'true'};
var ACCDEF={dark:'blue',light:'blue',blue:'cyan',verde:'green',orgullo:'purple'};

/* ===== HELPERS ===== */
var PER_KEY='transnubet_per_device';
function raw(k,d){try{var v=localStorage.getItem(k);return v!==null?v:d;}catch(e){return d;}}
function rawSet(k,v){try{localStorage.setItem(k,v);}catch(e){}}
/* Misma logica estable que theme_config.php: lado corto de pantalla primero
   (inmune a la carrera del <meta viewport>), innerWidth como respaldo */
function curDev(){
    var sw=(window.screen&&window.screen.width)||0, sh=(window.screen&&window.screen.height)||0;
    var corto=(sw>0&&sh>0)?Math.min(sw,sh):0;
    if(corto>0&&corto<=520)return'movil';
    var w=window.innerWidth||document.documentElement.clientWidth;
    return w<=768?'movil':(w<=1024?'tableta':'pc');
}
function pdev(){return raw(PER_KEY,'true')!=='false';}
function effDev(){return pdev()?curDev():(raw('transnubet_device_manual',null)||curDev());}
/* Con perfiles activos, lee/escribe la clave del dispositivo activo (@pc/@tableta/@movil).
   PER_KEY y device_manual siempre van sin sufijo (son los selectores). */
function g(k,d){var kk=(k!==PER_KEY&&k!=='transnubet_device_manual')?k+'@'+effDev():k;return raw(kk,d);}
function s(k,v){try{localStorage.setItem((k!==PER_KEY&&k!=='transnubet_device_manual')?k+'@'+effDev():k,v);}catch(e){}}

/* ===== DOM REFS ===== */
var panel=document.getElementById('tpPanel');
var overlay=document.getElementById('tpOverlay');
var closeBtn=document.getElementById('tpClose');
var saveBtn=document.getElementById('tpSave');
var resetBtn=document.getElementById('tpReset');

/* Re-parent al <body>: ancestros con transform/backdrop-filter (win-topbar.fade-in-up)
   rompen position:fixed y el panel no cubriría todo el viewport */
if(panel&&panel.parentElement!==document.body){
    document.body.appendChild(overlay);
    document.body.appendChild(panel);
}

/* ===== OPEN/CLOSE ===== */
function openPanel(){
    panel.classList.add('active');
    overlay.classList.add('active');
    loadCurrentValues();
}
function closePanel(){
    panel.classList.remove('active');
    overlay.classList.remove('active');
}

/* Find the settings button (might be #tpSettingsBtn or #themeToggleBtn) */
var settingsBtn=document.getElementById('tpSettingsBtn')||document.getElementById('themeToggleBtn');
if(settingsBtn){settingsBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();openPanel();});}

/* Badge: contador de configuración personalizada sobre el botón de settings */
if(settingsBtn&&!document.getElementById('tpCustomBadge')){
    settingsBtn.style.position='relative';
    var cb=document.createElement('span');
    cb.id='tpCustomBadge';
    cb.className='tp-custom-badge';
    cb.style.display='none';
    settingsBtn.appendChild(cb);
}
if(closeBtn){closeBtn.addEventListener('click',closePanel);}
if(overlay){overlay.addEventListener('click',closePanel);}
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&panel.classList.contains('active'))closePanel();});

/* ===== LOAD CURRENT VALUES ===== */
function loadCurrentValues(){
    /* Theme */
    var theme=g(STORAGE_KEY,'dark');
    document.querySelectorAll('.tp-theme-btn').forEach(function(b){b.classList.toggle('active',b.dataset.tpTheme===theme);});
    /* Accent: por tema, con default propio */
    var th=g(STORAGE_KEY,'dark');
    var accent=g('transnubet_accent_'+th,null)||ACCDEF[th]||'blue';
    document.querySelectorAll('.tp-accent-dot').forEach(function(d){d.classList.toggle('active',d.dataset.accent===accent);});
    /* Radius */
    var radius=g('transnubet_radius','10');
    document.querySelectorAll('#tpRadiusGroup .tp-radio').forEach(function(b){b.classList.toggle('active',b.dataset.val===radius);});
    /* Font */
    var fs=g('transnubet_font_size','1.0');
    document.querySelectorAll('#tpFontGroup .tp-radio').forEach(function(b){b.classList.toggle('active',b.dataset.val===fs);});
    /* Density */
    var den=g('transnubet_density','normal');
    document.querySelectorAll('#tpDensityGroup .tp-radio').forEach(function(b){b.classList.toggle('active',b.dataset.val===den);});
    /* Sidebar compact */
    document.getElementById('tpSidebarCompact').checked=(g('transnubet_sidebar_compact','false')==='true');
    /* Sidebar opacity */
    var op=g('transnubet_sidebar_opacity','95');
    document.getElementById('tpSidebarOpacity').value=op;
    document.getElementById('tpOpacityVal').textContent=op+'%';
    /* Content width */
    var cw=g('transnubet_content_width','100');
    document.querySelectorAll('#tpContentWidthGroup .tp-chip').forEach(function(b){b.classList.toggle('active',b.dataset.val===cw);});
    /* Animations */
    document.getElementById('tpAnimations').checked=(g('transnubet_animations','true')==='true');
    /* Focus mode */
    document.getElementById('tpFocusMode').checked=(g('transnubet_focus_mode','false')==='true');
    /* Tooltips */
    document.getElementById('tpTooltips').checked=(g('transnubet_tooltips','true')==='true');
    /* Vista de dispositivo */
    var pd=pdev();
    var eff=effDev();
    document.querySelectorAll('#tpDeviceGroup .tp-radio').forEach(function(b){b.classList.toggle('active',b.dataset.val===eff);});
    document.getElementById('tpPerDevice').checked=pd;
    document.getElementById('tpPerDevName').textContent={pc:'PC',tableta:'Tablet',movil:'M\u00F3vil'}[eff]||eff;
    document.getElementById('tpPerDevMode').textContent=pd?'(auto)':'(manual)';
}

/* ===== LIVE PREVIEW (click without saving) ===== */
/* Theme buttons */
document.querySelectorAll('.tp-theme-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        document.querySelectorAll('.tp-theme-btn').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        applyTheme(btn.dataset.tpTheme);
        var na=g('transnubet_accent_'+btn.dataset.tpTheme,null)||ACCDEF[btn.dataset.tpTheme]||'blue';
        document.querySelectorAll('.tp-accent-dot').forEach(function(d){d.classList.toggle('active',d.dataset.accent===na);});
        applyAccent(na);
    });
});

/* Accent dots */
document.querySelectorAll('.tp-accent-dot').forEach(function(dot){
    dot.addEventListener('click',function(){
        document.querySelectorAll('.tp-accent-dot').forEach(function(d){d.classList.remove('active');});
        dot.classList.add('active');
        applyAccent(dot.dataset.accent);
    });
});

/* Radio groups */
['tpRadiusGroup','tpFontGroup','tpDensityGroup'].forEach(function(gid){
    document.querySelectorAll('#'+gid+' .tp-radio').forEach(function(btn){
        btn.addEventListener('click',function(){
            document.querySelectorAll('#'+gid+' .tp-radio').forEach(function(b){b.classList.remove('active');});
            btn.classList.add('active');
        });
    });
});

/* Content width chips */
document.querySelectorAll('#tpContentWidthGroup .tp-chip').forEach(function(chip){
    chip.addEventListener('click',function(){
        document.querySelectorAll('#tpContentWidthGroup .tp-chip').forEach(function(c){c.classList.remove('active');});
        chip.classList.add('active');
    });
});

/* Sidebar opacity slider */
document.getElementById('tpSidebarOpacity').addEventListener('input',function(){
    document.getElementById('tpOpacityVal').textContent=this.value+'%';
});

/* Modo enfoque en vivo */
document.getElementById('tpFocusMode').addEventListener('change',function(){
    document.documentElement.classList.toggle('focus-mode',this.checked);
    s('transnubet_focus_mode',this.checked?'true':'false');
    updateCustomBadge();
});

/* Animaciones en vivo */
document.getElementById('tpAnimations').addEventListener('change',function(){
    document.documentElement.classList.toggle('no-animations',!this.checked);
    s('transnubet_animations',this.checked?'true':'false');
    updateCustomBadge();
});

/* Tooltips en vivo */
document.getElementById('tpTooltips').addEventListener('change',function(){
    document.documentElement.classList.toggle('no-tooltips',!this.checked);
    s('transnubet_tooltips',this.checked?'true':'false');
    updateCustomBadge();
});

/* Vista de dispositivo: selección manual (desactiva el modo automático) */
document.querySelectorAll('#tpDeviceGroup .tp-radio').forEach(function(btn){
    btn.addEventListener('click',function(){
        if(btn.dataset.val===effDev())return;
        rawSet(PER_KEY,'false');
        rawSet('transnubet_device_manual',btn.dataset.val);
        window.location.reload();
    });
});

/* Detección automática: vuelve al perfil del dispositivo real */
document.getElementById('tpPerDevice').addEventListener('change',function(){
    if(this.checked){try{localStorage.removeItem('transnubet_device_manual');}catch(e){}}
    rawSet(PER_KEY,this.checked?'true':'false');
    window.location.reload();
});

/* ===== APPLY FUNCTIONS ===== */
function applyTheme(t){
    document.documentElement.setAttribute('data-theme',t);
    if(t==='dark'||t==='blue'||t==='verde'||t==='orgullo'){document.documentElement.classList.add('dark');}else{document.documentElement.classList.remove('dark');}
    var icon=document.getElementById('themeToggleIcon');
    if(icon){icon.className=(t==='light'||t==='orgullo')?'fas fa-moon':'fas fa-sun';}
    s(STORAGE_KEY,t);
}
function applyAccent(a){
    var ac=ACCENTS[a]||ACCENTS.blue;
    var st=document.getElementById('theme-config-vars');
    if(st){st.style.setProperty('--accent',ac.c);st.style.setProperty('--accent-rgb',ac.r);}
    document.documentElement.style.setProperty('--accent',ac.c);
    document.documentElement.style.setProperty('--accent-rgb',ac.r);
}

/* ===== SAVE ===== */
saveBtn.addEventListener('click',function(){
    var theme=document.querySelector('.tp-theme-btn.active').dataset.tpTheme;
    var accent=document.querySelector('.tp-accent-dot.active').dataset.accent;
    var radius=document.querySelector('#tpRadiusGroup .tp-radio.active').dataset.val;
    var font_size=document.querySelector('#tpFontGroup .tp-radio.active').dataset.val;
    var density=document.querySelector('#tpDensityGroup .tp-radio.active').dataset.val;
    var sidebar_compact=document.getElementById('tpSidebarCompact').checked?'true':'false';
    var sidebar_opacity=document.getElementById('tpSidebarOpacity').value;
    var content_width=document.querySelector('#tpContentWidthGroup .tp-chip.active').dataset.val;
    var animations=document.getElementById('tpAnimations').checked?'true':'false';
    var focus_mode=document.getElementById('tpFocusMode').checked?'true':'false';
    var tooltips=document.getElementById('tpTooltips').checked?'true':'false';

    /* Persist */
    s(STORAGE_KEY,theme);
    s('transnubet_accent_'+theme,accent);
    s('transnubet_radius',radius);
    s('transnubet_font_size',font_size);
    s('transnubet_density',density);
    s('transnubet_sidebar_compact',sidebar_compact);
    s('transnubet_sidebar_opacity',sidebar_opacity);
    s('transnubet_content_width',content_width);
    s('transnubet_animations',animations);
    s('transnubet_focus_mode',focus_mode);
    s('transnubet_tooltips',tooltips);

    /* Apply all */
    applyTheme(theme);
    applyAccent(accent);

    /* Re-apply config vars */
    var st=document.getElementById('theme-config-vars');
    if(st){st.remove();}
    var script=document.querySelector('script[src*="theme_config"],script');
    /* Force page to re-read config */
    window.location.reload();
});

/* ===== RESET ===== */
resetBtn.addEventListener('click',function(){
    Object.keys(DEFAULTS).forEach(function(k){
        var lk=k==='theme'?STORAGE_KEY:'transnubet_'+k;
        s(lk,DEFAULTS[k]);
    });
    try{
        Object.keys(localStorage).forEach(function(k){
            if(k.indexOf('transnubet_accent_')===0){localStorage.removeItem(k);}
        });
    }catch(e){}
    try{localStorage.removeItem('transnubet_device_manual');}catch(e){}
    window.location.reload();
});

/* ===== CUSTOM CONFIG BADGE ===== */
function countCustom(){
    var n=0;
    Object.keys(DEFAULTS).forEach(function(k){
        var lk=(k==='theme')?STORAGE_KEY:'transnubet_'+k;
        if(g(lk,DEFAULTS[k])!==DEFAULTS[k])n++;
    });
    var th=g(STORAGE_KEY,'dark');
    if((g('transnubet_accent_'+th,null)||ACCDEF[th]||'blue')!==ACCDEF[th])n++;
    return n;
}
function updateCustomBadge(){
    var b=document.getElementById('tpCustomBadge');
    if(!b)return;
    var n=countCustom();
    b.textContent=(n>9)?'9+':String(n);
    b.style.display=(n>0)?'flex':'none';
}
updateCustomBadge();

/* ===== EXPOSE openPanel globally ===== */
window.themePanelOpen=openPanel;
window.themePanelClose=closePanel;

})();


</script>
