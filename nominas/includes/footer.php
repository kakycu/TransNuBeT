<?php
// footer.php - Pie de página común para todo el sistema
// Ubicación: /includes/footer.php (al mismo nivel que sidebar.php)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración por defecto si no existe la variable global de empresa
if (!isset($config_empresa)) {
    $config_empresa = [
        'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
        'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
        'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab y Finanzas'
    ];
}

// Detectar si estamos en una subcarpeta (modules) o en la raíz para ajustar las rutas de recursos
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_modules = (strpos($current_script, '/modules/') !== false);
$base_prefix = $is_in_modules ? '../' : '';
?>

<style>
/* === ESTILOS CORPORATIVOS DE PIE DE PÁGINA (DASHBOARD GLASS) === */
/* Variables de tema: se definen aquí en oscuro y se sobrescriben en claro */
.corporate-footer {
    --corp-bg:rgba(16, 16, 22, 0.6);
    --corp-border:rgba(255, 255, 255, 0.05);
    --corp-bar-bg:rgba(10, 10, 15, 0.4);
    --corp-bar-border:rgba(255, 255, 255, 0.04);
    --corp-bottom-bg:rgba(0, 0, 0, 0.04);
    --corp-bottom-border:rgba(255, 255, 255, 0.04);
    --corp-copy-bg:rgba(5, 5, 8, 0.4);
    --corp-text-strong:#f1f5f9;
    --corp-text-muted:#64748b;
    --corp-text-copy:#cbd5e1;
    --corp-accent:#60a5fa;
    --corp-accent-2:#a78bfa;
    --corp-icon-bg:rgba(96, 165, 250, 0.08);
    --corp-icon-border:rgba(96, 165, 250, 0.15);
    --corp-icon-hover-bg:rgba(96, 165, 250, 0.15);
    --corp-icon-hover-border:#60a5fa;
    --corp-badge-bg:rgba(96, 165, 250, 0.1);
    --corp-badge-border:rgba(96, 165, 250, 0.2);
    --corp-badge-hover-bg:rgba(96, 165, 250, 0.15);
    --corp-badge-hover-border:#60a5fa;
    --corp-separator:rgba(255, 255, 255, 0.15);
    --corp-logo-filter:brightness(0) invert(1);
    margin-top:1.75rem;
    background: var(--corp-bg) !important;
    backdrop-filter: blur(0.9375rem);
    -webkit-backdrop-filter: blur(0.9375rem);
    border: 0.0625rem solid var(--corp-border) !important;
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s ease;
    animation-delay: 0.15s;
}

.corporate-bar {
    padding:1.25rem 1.5rem;
    background: var(--corp-bar-bg);
    border-bottom: 0.0625rem solid var(--corp-bar-border);
}

.corporate-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12.5rem, 1fr));
    gap:1.25rem;
    align-items: center;
}

.corporate-item {
    display: flex;
    align-items: center;
    gap:0.75rem;
    cursor: pointer;
}

.corporate-icon {
    width:2.625rem;
    height:2.625rem;
    background: var(--corp-icon-bg);
    border: 0.0625rem solid var(--corp-icon-border);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size:1.15rem;
    color: var(--corp-accent);
    transition: all 0.3s ease;
}

.corporate-item:hover .corporate-icon {
    background: var(--corp-icon-hover-bg);
    border-color: var(--corp-icon-hover-border);
    transform: scale(1.05);
    box-shadow: 0 0 0.9375rem rgba(96, 165, 250, 0.2);
}

.corporate-info {
    display: flex;
    flex-direction: column;
}

.corporate-info strong {
    font-size:0.75rem;
    font-weight: 600;
    color: var(--corp-text-strong);
    letter-spacing:0.0188rem;
    text-transform: uppercase;
}

.corporate-info span {
    font-size:0.68rem;
    color: var(--corp-text-muted);
    margin-top:0.0625rem;
}

.corporate-bottom {
    padding:1rem 1.5rem;
    background: var(--corp-bottom-bg);
    border-bottom: 0.0625rem solid var(--corp-bottom-border);
}

.corporate-bottom .row > div {
    transition: all 0.2s;
}

.corporate-bottom .row > div:hover {
    transform: translateY(-0.0625rem);
}

.corporate-bottom strong {
    color: var(--corp-text-strong);
    font-size:0.85rem;
}

.corporate-bottom small {
    color: var(--corp-text-muted);
    font-size:0.72rem;
}

/* Iconos de acento (antes estilos inline) */
.corporate-accent-icon {
    color: var(--corp-accent);
    font-size:1.1rem;
}
.corporate-accent-icon.violet {
    color: var(--corp-accent-2);
    margin-left:0.375rem;
}

.corporate-badge {
    display: inline-flex;
    align-items: center;
    gap:0.375rem;
    padding:0.3125rem 0.875rem;
    background: var(--corp-badge-bg);
    border-radius: 6.25rem;
    font-size:0.7rem;
    font-weight: 600;
    color: var(--corp-accent);
    border: 0.0625rem solid var(--corp-badge-border);
    transition: all 0.2s;
}

.corporate-badge:hover {
    background: var(--corp-badge-hover-bg);
    border-color: var(--corp-badge-hover-border);
    box-shadow: 0 0 0.625rem rgba(96, 165, 250, 0.15);
}

.corporate-copyright {
    padding:0.75rem 1.5rem;
    background: var(--corp-copy-bg);
    font-size:0.7rem;
    color: var(--corp-text-muted);
}

.copyright-logo {
    display: flex;
    align-items: center;
    gap:0.5rem;
}

.copyright-logo img {
    filter: var(--corp-logo-filter);
}

.copyright-logo strong {
    color: var(--corp-text-copy);
    font-weight: 600;
}

.copyright-links {
    display: flex;
    align-items: center;
    gap:0.75rem;
}

.copyright-link {
    color: var(--corp-text-muted);
    text-decoration: none;
    font-size:0.7rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap:0.25rem;
}

.copyright-link:hover {
    color: var(--corp-accent);
}

/* Separador de enlaces legales (antes estilo inline) */
.corporate-separator {
    color: var(--corp-separator);
}

@media (max-width: 768px) {
    .corporate-grid {
        grid-template-columns: 1fr;
        gap:1rem;
    }
    .corporate-bottom .row > div {
        text-align: center !important;
        margin-bottom:0.75rem;
    }
    .corporate-bottom .row > div:last-child {
        margin-bottom:0;
    }
    .corporate-copyright .d-flex {
        flex-direction: column;
        text-align: center;
        gap:0.75rem;
    }
}

/* === MODAL VENTANA ESTILO INSTALADOR (ISO 27001) === */
.iso27001-overlay {
    position: fixed;
    inset:0;
    background: rgba(2, 6, 23, 0.72);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding:1.25rem;
}
.iso27001-box {
    background: #15161d;
    color: #e2e8f0;
    border-radius: 0.75rem;
    box-shadow: 0 1.25rem 3.75rem rgba(0, 0, 0, 0.6);
    max-width:35rem;
    width:100%;
    text-align: center;
    position: relative;
    overflow: hidden;
    border: 0.0625rem solid rgba(255, 255, 255, 0.08);
}
.iso27001-titlebar {
    display: flex;
    align-items: center;
    gap:0.625rem;
    padding:0.75rem 1rem;
    background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
    border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.1);
    text-align: left;
}
.iso27001-titlebar .tt-icon {
    font-size:1.125rem;
    color: #5eead4;
}
.iso27001-titlebar .tt-text {
    font-size:0.9375rem;
    font-weight: 700;
    color: #fff;
    letter-spacing:0.0188rem;
    flex: 1;
}
.iso27001-close {
    width:1.875rem;
    height:1.875rem;
    border: none;
    background: rgba(255, 255, 255, 0.12);
    color: #e2e8f0;
    font-size:1.125rem;
    line-height:1;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: all 0.2s;
}
.iso27001-close:hover { background: #ef4444; color: #fff; }
.iso27001-body {
    padding:1.5rem 1.75rem 1.625rem;
    text-align: left;
}
.iso27001-body .iso-empresa {
    font-size:1.0625rem;
    font-weight: 700;
    color: #fff;
    line-height:1.35;
    margin-bottom:0.375rem;
}
.iso27001-body .iso-empresa i {
    color: #5eead4;
    margin-right:0.5rem;
}
.iso27001-body .iso-sub {
    font-size:0.8125rem;
    color: #94a3b8;
    margin-bottom:1.125rem;
}
.iso27001-body .iso-scroll {
    max-height:18.75rem;
    overflow-y: auto;
    padding-right:0.375rem;
}
.iso27001-body h4 {
    font-size:0.9062rem;
    font-weight: 700;
    color: #5eead4;
    margin:0.875rem 0 0.375rem;
}
.iso27001-body h4:first-child { margin-top:0; }
.iso27001-body p {
    font-size:0.8125rem;
    color: #cbd5e1;
    line-height:1.55;
    margin-bottom:0.625rem;
}
.iso27001-body .actions {
    display: flex;
    gap:0.625rem;
    justify-content: flex-end;
    margin-top:1.125rem;
    padding-top:0.875rem;
    border-top: 0.0625rem solid rgba(255, 255, 255, 0.08);
}
.iso27001-body .btn-iso-entendido {
    background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding:0.625rem 1.5rem;
    font-size:0.8438rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.iso27001-body .btn-iso-entendido:hover {
    transform: translateY(-0.0625rem);
    box-shadow: 0 0.375rem 1.125rem rgba(15, 118, 110, 0.4);
}

/* === TEMA CLARO: PIE DE PÁGINA CORPORATIVO (autocontenido) === */
/* Solo se sobrescriben las variables; las reglas base las consumen */
[data-theme="light"] .corporate-footer {
    --corp-bg:rgba(255, 255, 255, 0.96);
    --corp-border:#000000;
    --corp-bar-bg:rgba(0, 0, 0, 0.035);
    --corp-bar-border:rgba(0, 0, 0, 0.08);
    --corp-bottom-bg:rgba(0, 0, 0, 0.2);
    --corp-bottom-border:rgba(0, 0, 0, 0.08);
    --corp-copy-bg:rgba(0, 0, 0, 0.05);
    --corp-text-strong:#111827;
    --corp-text-muted:#4b5563;
    --corp-text-copy:#111827;
    --corp-accent:#1d4ed8;
    --corp-accent-2:#6d28d9;
    --corp-icon-bg:rgba(0, 120, 212, 0.12);
    --corp-icon-border:rgba(0, 120, 212, 0.35);
    --corp-icon-hover-bg:rgba(0, 120, 212, 0.2);
    --corp-icon-hover-border:#0078d4;
    --corp-badge-bg:rgba(0, 120, 212, 0.15);
    --corp-badge-border:rgba(0, 120, 212, 0.35);
    --corp-badge-hover-bg:rgba(0, 120, 212, 0.2);
    --corp-badge-hover-border:#0078d4;
    --corp-separator:rgba(0, 0, 0, 0.25);
    --corp-logo-filter:none;
    box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, 0.08) !important;
}

/* === TEMA CLARO: MODAL ISO 27001 (autocontenido) === */
[data-theme="light"] .iso27001-overlay { background: rgba(15, 23, 42, 0.5) !important; }
[data-theme="light"] .iso27001-box { background: #ffffff !important; border: 0.0625rem solid rgba(0, 0, 0, 0.1) !important; color: #1f2937 !important; }
[data-theme="light"] .iso27001-titlebar { border-bottom: 0.0625rem solid rgba(0, 0, 0, 0.1) !important; }
[data-theme="light"] .iso27001-titlebar .tt-text { color: #ffffff !important; }
[data-theme="light"] .iso27001-close { background: rgba(0, 0, 0, 0.06) !important; color: #1f2937 !important; }
[data-theme="light"] .iso27001-close:hover { background: #ef4444 !important; color: #ffffff !important; }
[data-theme="light"] .iso27001-body { color: #1f2937 !important; }
[data-theme="light"] .iso27001-body .iso-empresa { color: #111827 !important; }
[data-theme="light"] .iso27001-body .iso-empresa i { color: #0f766e !important; }
[data-theme="light"] .iso27001-body .iso-sub { color: #4b5563 !important; }
[data-theme="light"] .iso27001-body h4 { color: #0f766e !important; }
[data-theme="light"] .iso27001-body p { color: #374151 !important; }
[data-theme="light"] .iso27001-body .actions { border-top: 0.0625rem solid rgba(0, 0, 0, 0.08) !important; }
[data-theme="light"] .iso27001-body .btn-iso-entendido { color: #ffffff !important; }
</style>

<style>
/* ===== Pie minimizable en movil ===== */
.corp-min-btn{display:none;}
@media (max-width:768px){
    .corporate-footer{position:relative;}
    .corp-min-btn{
        display:flex;align-items:center;justify-content:center;
        position:absolute;top:-0.9375rem;left:50%;transform:translateX(-50%);
        width:1.875rem;height:1.875rem;border-radius:50%;
        border:0.0625rem solid rgba(var(--accent-rgb),0.35);
        background:var(--panel,#16213e);color:var(--accent,#3b82f6);
        cursor:pointer;padding:0;
        box-shadow:0 0.125rem 0.625rem rgba(0,0,0,0.35);
        transition:transform 0.15s ease,box-shadow 0.15s ease;
    }
    .corp-min-btn:hover{transform:translateX(-50%) scale(1.08);}
    .corp-min-btn i{transition:transform 0.25s ease;font-size:0.7rem;}
    .corporate-footer.pie-minimizado .corporate-bar,
    .corporate-footer.pie-minimizado .corporate-bottom{display:none;}
    .corporate-footer.pie-minimizado .corp-min-btn i{transform:rotate(180deg);}
}
</style>

<!-- Pie de página Rediseñado de Alto Contraste -->
<div class="corporate-footer fade-in-up">
    <button type="button" id="corpMinBtn" class="corp-min-btn" aria-label="Mostrar u ocultar el detalle del pie">
        <i class="fas fa-chevron-down"></i>
    </button>
    <div class="corporate-bar">
        <div class="corporate-grid">
            <div class="corporate-item" id="isoCertItem" onclick="abrirModalISO27001()" data-tooltip="Certificación NC-ISO/IEC 27001" data-tooltip-theme="success">
                <div class="corporate-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="corporate-info">
                    <strong>Certificación NC-ISO/IEC 27001</strong>
                    <span>Seguridad de la información · Datos protegidos</span>
                </div>
            </div>
            <div class="corporate-item" onclick="abrirModalFooter('infra')" data-tooltip="Infraestructura Local 100%" data-tooltip-theme="info">
                <div class="corporate-icon">
                    <i class="fas fa-server"></i>
                </div>
                <div class="corporate-info">
                    <strong>Infraestructura Local 100%</strong>
                    <span>Sin conexión a servicios externos · Soberanía total</span>
                </div>
            </div>
            <div class="corporate-item" onclick="abrirModalFooter('opensource')" data-tooltip="Open Source · Shareware" data-tooltip-theme="primary">
                <div class="corporate-icon">
                    <i class="fas fa-code-branch"></i>
                </div>
                <div class="corporate-info">
                    <strong>Open Source · Shareware <?php echo defined('SITE_VERSION') ? htmlspecialchars(SITE_VERSION) : 'v2.0'; ?></strong>
                    <span>Código abierto · Sin fines de lucro · Libre distribución</span>
                </div>
            </div>
            <div class="corporate-item" onclick="abrirModalFooter('auditable')" data-tooltip="Sistema Auditable y Transparente" data-tooltip-theme="gradient">
                <div class="corporate-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="corporate-info">
                    <strong>Auditable y Transparente</strong>
                    <span>Traza completa · Cumplimiento normativo</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="corporate-bottom">
        <div class="row align-items-center">
            <div class="col-md-5 text-md-start">
                <div class="d-flex align-items-center justify-content-md-start justify-content-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-user-check corporate-accent-icon"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($config_empresa['especialista_gestion']); ?></strong>
                            <br><small>Especialista en Gestión Económica</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 text-center my-3 my-md-0">
                <div class="corporate-badge">
                    <i class="fas fa-shield-heart"></i>
                    <span><?php echo defined('SITE_VERSION') ? htmlspecialchars(SITE_VERSION) : 'v2.0'; ?> · Estable</span>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <div class="d-flex align-items-center justify-content-md-end justify-content-center gap-3">
                    <div class="d-flex align-items-center gap-2 text-start text-md-end">
                        <div>
                            <strong><?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?></strong>
                            <br><small>Jefe de Proyecto · Director Técnico</small>
                        </div>
                        <i class="fas fa-certificate corporate-accent-icon violet"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="corporate-copyright">
        <div class="d-flex flex-wrap justify-content-center justify-content-md-between align-items-center gap-3">
            <div class="copyright-logo">
                <img src="<?php echo $base_prefix; ?>../images/Unicorn.png" alt="Unicornio" width="20" height="20" class="corporate-logo-img" onerror="this.style.display='none';">
                <span>Copyright © <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></strong> · Todos los derechos reservados</span>
            </div>
            <div class="copyright-links">
                <a href="<?php echo $base_prefix; ?>../../terminos.php" class="copyright-link" data-tooltip="Términos y Condiciones" data-tooltip-theme="secondary"><i class="fas fa-file-contract"></i> Términos</a>
                <span class="corporate-separator">|</span>
                <a href="<?php echo $base_prefix; ?>../../privacidad.php" class="copyright-link" data-tooltip="Política de Privacidad" data-tooltip-theme="secondary"><i class="fas fa-lock"></i> Privacidad</a>
                <span class="corporate-separator">|</span>
                <a href="<?php echo $base_prefix; ?>../../soporte.php" class="copyright-link" data-tooltip="Soporte Técnico" data-tooltip-theme="secondary"><i class="fas fa-envelope"></i> Soporte</a>
                <span class="corporate-separator">|</span>
                <a href="<?php echo $base_prefix; ?>../../contacto.php" class="copyright-link" data-tooltip="Contacto" data-tooltip-theme="secondary"><i class="fas fa-address-book"></i> Contacto</a>
            </div>
        </div>
    </div>
</div>

<?php
// Aviso global: clasificadores vacíos (barra con X, siempre presente en cada página)
if (!function_exists('avisarClasificadoresVacios') && file_exists(__DIR__ . '/funciones.php')) {
    require_once __DIR__ . '/funciones.php';
}
if (function_exists('avisarClasificadoresVacios') && isset($pdo) && $pdo instanceof PDO) {
    avisarClasificadoresVacios($pdo);
}
?>

<!-- Modal Ventana: Información del Sistema (ISO 27001, Infraestructura, Open Source, Auditable) -->
<div class="iso27001-overlay" id="modalISO27001" style="display: none;">
    <div class="iso27001-box">
        <div class="iso27001-titlebar">
            <i class="fas fa-shield-halved tt-icon" id="modalISOIcon"></i>
            <span class="tt-text" id="modalISOTitulo">Información del Sistema</span>
            <button type="button" class="iso27001-close" aria-label="Cerrar" onclick="cerrarModalISO27001()" data-tooltip="Cerrar" data-tooltip-theme="danger">&times;</button>
        </div>
        <div class="iso27001-body">
            <div class="iso-empresa"><i class="fas fa-building"></i><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></div>
            <div class="iso-sub" id="modalISOSub"></div>
            <div class="iso-scroll" id="modalISOContenido"></div>
            <div class="actions">
                <button type="button" class="btn-iso-entendido" onclick="cerrarModalISO27001()" data-tooltip="Entendido" data-tooltip-theme="success"><i class="fas fa-check me-2"></i> Entendido</button>
            </div>
        </div>
    </div>
</div>

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
            '<p>La ISO/IEC 27001 promueve un enfoque integral de la seguridad de la información, que abarca a las personas, las políticas y la tecnología. Un sistema de gestión de la seguridad de la información implantado conforme a esta norma es una herramienta clave para la gestión de riesgos, la resiliencia cibernética y la excelencia operativa.</p>'
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
        titulo: 'Open Source · Shareware <?php echo defined('SITE_VERSION') ? htmlspecialchars(SITE_VERSION) : 'v2.0'; ?>',
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

function abrirModalISO27001() {
    abrirModalFooter('iso');
}

function abrirModalFooter(tipo) {
    var cfg = CONTENIDO_MODALES_FOOTER[tipo] || CONTENIDO_MODALES_FOOTER.iso;
    document.getElementById('modalISOIcon').className = 'fas ' + cfg.icono + ' tt-icon';
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

<!-- ============================================
     TOOLTIP SYSTEM - 100% JavaScript
     Crea tooltips DOM para TODOS los elementos
     ============================================ -->
<style>
.tt-box{position:absolute;z-index:999999;padding:0.5rem 0.875rem;background:linear-gradient(135deg,#0f172a,#1e293b);color:#f1f5f9;font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;font-size:.75rem;font-weight:500;line-height:1.5;white-space:nowrap;border-radius:0.5rem;box-shadow:0 0.25rem 1.25rem rgba(0,0,0,.4);pointer-events:none;opacity:0;transition:opacity .2s,transform .2s;transform:translateY(0.25rem) scale(.95);border:none;margin:0;max-width:17.5rem}
.tt-box.visible{opacity:1;transform:translateY(0) scale(1)}
.tt-box::after{content:'';position:absolute;bottom:-0.375rem;left:50%;transform:translateX(-50%);border:0.375rem solid transparent;border-top:0.375rem solid #1e293b}
.tt-box.tt-bottom::after{bottom:auto;top:-0.375rem;border-top:none;border-bottom:0.375rem solid #1e293b}
.tt-box.tt-left::after{bottom:auto;top:50%;left:auto;right:-0.375rem;transform:translateY(-50%);border-top:0.375rem solid transparent;border-bottom:0.375rem solid transparent;border-left:0.375rem solid #1e293b;border-right:none}
.tt-box.tt-right::after{bottom:auto;top:50%;left:-0.375rem;transform:translateY(-50%);border-top:0.375rem solid transparent;border-bottom:0.375rem solid transparent;border-right:0.375rem solid #1e293b;border-left:none}
.tt-box.tt-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 0.25rem 1.25rem rgba(99,102,241,.4)}
.tt-box.tt-primary::after{border-top-color:#8b5cf6}.tt-box.tt-primary.tt-bottom::after{border-top-color:transparent;border-bottom-color:#8b5cf6}.tt-box.tt-primary.tt-left::after{border-left-color:#8b5cf6}.tt-box.tt-primary.tt-right::after{border-right-color:#8b5cf6}
.tt-box.tt-success{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 0.25rem 1.25rem rgba(16,185,129,.4)}
.tt-box.tt-success::after{border-top-color:#059669}.tt-box.tt-success.tt-bottom::after{border-top-color:transparent;border-bottom-color:#059669}.tt-box.tt-success.tt-left::after{border-left-color:#059669}.tt-box.tt-success.tt-right::after{border-right-color:#059669}
.tt-box.tt-danger{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 0.25rem 1.25rem rgba(239,68,68,.4)}
.tt-box.tt-danger::after{border-top-color:#dc2626}.tt-box.tt-danger.tt-bottom::after{border-top-color:transparent;border-bottom-color:#dc2626}.tt-box.tt-danger.tt-left::after{border-left-color:#dc2626}.tt-box.tt-danger.tt-right::after{border-right-color:#dc2626}
.tt-box.tt-warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#1e293b;box-shadow:0 0.25rem 1.25rem rgba(245,158,11,.4)}
.tt-box.tt-warning::after{border-top-color:#d97706}.tt-box.tt-warning.tt-bottom::after{border-top-color:transparent;border-bottom-color:#d97706}.tt-box.tt-warning.tt-left::after{border-left-color:#d97706}.tt-box.tt-warning.tt-right::after{border-right-color:#d97706}
.tt-box.tt-info{background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 0.25rem 1.25rem rgba(59,130,246,.4)}
.tt-box.tt-info::after{border-top-color:#2563eb}.tt-box.tt-info.tt-bottom::after{border-top-color:transparent;border-bottom-color:#2563eb}.tt-box.tt-info.tt-left::after{border-left-color:#2563eb}.tt-box.tt-info.tt-right::after{border-right-color:#2563eb}
.tt-box.tt-secondary{background:linear-gradient(135deg,#64748b,#94a3b8);box-shadow:0 0.25rem 1.25rem rgba(100,116,139,.3)}
.tt-box.tt-secondary::after{border-top-color:#94a3b8}.tt-box.tt-secondary.tt-bottom::after{border-top-color:transparent;border-bottom-color:#94a3b8}.tt-box.tt-secondary.tt-left::after{border-left-color:#94a3b8}.tt-box.tt-secondary.tt-right::after{border-right-color:#94a3b8}
.tt-box.tt-dark{background:linear-gradient(135deg,#0f172a,#1e293b);box-shadow:0 0.25rem 1.25rem rgba(15,23,42,.5)}
.tt-box.tt-gradient{background:linear-gradient(135deg,#667eea,#764ba2);box-shadow:0 0.25rem 1.25rem rgba(102,126,234,.5)}
.tt-box.tt-gradient::after{border-top-color:#764ba2}.tt-box.tt-gradient.tt-bottom::after{border-top-color:transparent;border-bottom-color:#764ba2}
.tt-box.tt-glass{background:rgba(30,30,40,.9);border:0.0625rem solid rgba(255,255,255,.2);backdrop-filter:blur(0.75rem)}
.tt-box.tt-neon{background:rgba(5,10,20,.95);color:#00ff88;border:0.0625rem solid rgba(0,255,136,.5);text-shadow:0 0 0.375rem rgba(0,255,136,.5)}
.tt-box.tt-neon::after{border-top-color:rgba(5,10,20,.95)}.tt-box.tt-neon.tt-bottom::after{border-top-color:transparent;border-bottom-color:rgba(5,10,20,.95)}
.tt-box.tt-cyan{background:linear-gradient(135deg,#06b6d4,#0891b2);box-shadow:0 0.25rem 1.25rem rgba(6,182,212,.4)}
.tt-box.tt-cyan::after{border-top-color:#0891b2}.tt-box.tt-cyan.tt-bottom::after{border-top-color:transparent;border-bottom-color:#0891b2}
.tt-box.tt-pink{background:linear-gradient(135deg,#ec4899,#db2777);box-shadow:0 0.25rem 1.25rem rgba(236,72,153,.4)}
.tt-box.tt-pink::after{border-top-color:#db2777}.tt-box.tt-pink.tt-bottom::after{border-top-color:transparent;border-bottom-color:#db2777}
.tt-box.tt-light{background:linear-gradient(135deg,#fff,#f1f5f9);color:#1e293b;border:0.0625rem solid #e2e8f0;box-shadow:0 0.25rem 1.25rem rgba(0,0,0,.15)}
.tt-box.tt-light::after{border-top-color:#f1f5f9}.tt-box.tt-light.tt-bottom::after{border-top-color:transparent;border-bottom-color:#f1f5f9}
@media (max-width: 480px){.tt-box{font-size:.6875rem;padding:0.375rem 0.625rem;max-width:11.25rem;white-space:normal;text-align:center}}
html.no-tooltips .tt-box{display:none!important}
</style>
<script>
(function(){
    // Eliminar title nativo de todos los elementos con data-tooltip
    document.querySelectorAll('[data-tooltip]').forEach(function(el){el.removeAttribute('title')});
    var box=null, current=null;
    function getBox(){
        if(!box){
            box=document.createElement('div');
            box.className='tt-box';
            box.setAttribute('aria-hidden','true');
            document.body.appendChild(box);
        }
        return box;
    }
    function show(e){
        if(document.documentElement.classList.contains('no-tooltips'))return;
        var el=e.target.closest('[data-tooltip]');
        if(!el)return;
        var text=el.getAttribute('data-tooltip');
        if(!text)return;
        var theme=el.getAttribute('data-tooltip-theme')||'';
        var pos=el.getAttribute('data-tooltip-position')||'top';
        var b=getBox();
        b.textContent=text;
        b.className='tt-box'+(theme?' tt-'+theme:'')+(pos?' tt-'+pos:'');
        current=el;

        var rect=el.getBoundingClientRect();
        var bw=b.offsetWidth, bh=b.offsetHeight;
        var gap=10;
        var top,left;

        if(pos==='bottom'){top=rect.bottom+gap+window.scrollY}
        else if(pos==='left'){top=rect.top+(rect.height/2)-(bh/2)+window.scrollY;left=rect.left-bw-gap+window.scrollX}
        else if(pos==='right'){top=rect.top+(rect.height/2)-(bh/2)+window.scrollY;left=rect.right+gap+window.scrollX}
        else{top=rect.top-bh-gap+window.scrollY}

        if(pos!=='left'&&pos!=='right'){left=rect.left+(rect.width/2)-(bw/2)+window.scrollX}

        if(left<8)left=8;
        if(left+bw>window.innerWidth-8)left=window.innerWidth-bw-8;
        if(top<8)top=rect.bottom+gap+window.scrollY;

        b.style.top=top+'px';
        b.style.left=left+'px';
        requestAnimationFrame(function(){b.classList.add('visible')});
    }
    function hide(){
        if(box)box.classList.remove('visible');
        current=null;
    }
    document.addEventListener('mouseenter',function(e){
        var el=e.target.closest('[data-tooltip]');
        if(!el)return;
        if(current===el)return;
        hide();
        show(e);
    },true);
    document.addEventListener('mouseleave',function(e){
        var el=e.target.closest('[data-tooltip]');
        if(!el)return;
        hide();
    },true);
    document.addEventListener('focusin',function(e){
        var el=e.target.closest('[data-tooltip]');
        if(!el)return;
        show({target:el});
    },true);
    document.addEventListener('focusout',function(){hide()},true);
})();
</script>
<script>
/* ===== Pie minimizable en movil ===== */
(function(){
    var pie=document.querySelector('.corporate-footer');
    var btn=document.getElementById('corpMinBtn');
    if(!pie||!btn)return;
    function esMovil(){
        return window.innerWidth<=768||document.documentElement.getAttribute('data-device')==='movil';
    }
    function aplicar(min){
        if(!esMovil()){pie.classList.remove('pie-minimizado');return;}
        pie.classList.toggle('pie-minimizado',min);
    }
    function preferencia(){
        try{return localStorage.getItem('transnubet_pie_min')!=='0';}catch(e){return true;}
    }
    btn.addEventListener('click',function(e){
        e.stopPropagation();
        var min=!pie.classList.contains('pie-minimizado');
        aplicar(min);
        try{localStorage.setItem('transnubet_pie_min',min?'1':'0');}catch(e2){}
    });
    aplicar(esMovil()?preferencia():false);
    var temporizador=null;
    window.addEventListener('resize',function(){
        clearTimeout(temporizador);
        temporizador=setTimeout(function(){
            aplicar(esMovil()?preferencia():false);
        },200);
    });
})();
</script>
