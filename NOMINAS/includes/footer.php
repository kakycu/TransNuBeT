<?php
// footer.php - Pie de página común para todo el sistema
// Ubicación: /includes/footer.php (al mismo nivel que sidebar.php)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración por defecto si no existe la variable global de empresa
if (!isset($config_empresa)) {
    $config_empresa = [
        'nombre_empresa' => 'PDL TransNuBeT',
        'jefe_proyecto' => 'Dainelys León Reyes',
        'especialista_gestion' => 'Mailén Pérez García'
    ];
}

// Detectar si estamos en una subcarpeta (modules) o en la raíz para ajustar las rutas de recursos
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_modules = (strpos($current_script, '/modules/') !== false);
$base_prefix = $is_in_modules ? '../' : '';
?>

<style>
/* === ESTILOS CORPORATIVOS DE PIE DE PÁGINA (DASHBOARD GLASS) === */
.corporate-footer {
    margin-top: 28px;
    background: rgba(16, 16, 22, 0.6) !important;
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.corporate-bar {
    padding: 20px 24px;
    background: rgba(10, 10, 15, 0.4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.corporate-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: center;
}

.corporate-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.corporate-icon {
    width: 42px;
    height: 42px;
    background: rgba(96, 165, 250, 0.08);
    border: 1px solid rgba(96, 165, 250, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #60a5fa;
    transition: all 0.3s ease;
}

.corporate-item:hover .corporate-icon {
    background: rgba(96, 165, 250, 0.15);
    border-color: #60a5fa;
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(96, 165, 250, 0.2);
}

.corporate-info {
    display: flex;
    flex-direction: column;
}

.corporate-info strong {
    font-size: 0.75rem;
    font-weight: 600;
    color: #f1f5f9;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.corporate-info span {
    font-size: 0.68rem;
    color: #64748b;
    margin-top: 1px;
}

.corporate-bottom {
    padding: 16px 24px;
    background: rgba(0, 0, 0, 0.15);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.corporate-bottom .row > div {
    transition: all 0.2s;
}

.corporate-bottom .row > div:hover {
    transform: translateY(-1px);
}

.corporate-bottom strong {
    color: #f1f5f9;
    font-size: 0.85rem;
}

.corporate-bottom small {
    color: #64748b;
    font-size: 0.72rem;
}

.corporate-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    background: rgba(96, 165, 250, 0.1);
    border-radius: 100px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #60a5fa;
    border: 1px solid rgba(96, 165, 250, 0.2);
    transition: all 0.2s;
}

.corporate-badge:hover {
    background: rgba(96, 165, 250, 0.15);
    border-color: #60a5fa;
    box-shadow: 0 0 10px rgba(96, 165, 250, 0.15);
}

.corporate-copyright {
    padding: 12px 24px;
    background: rgba(5, 5, 8, 0.4);
    font-size: 0.7rem;
    color: #64748b;
}

.copyright-logo {
    display: flex;
    align-items: center;
    gap: 8px;
}

.copyright-logo strong {
    color: #cbd5e1;
    font-weight: 600;
}

.copyright-links {
    display: flex;
    align-items: center;
    gap: 12px;
}

.copyright-link {
    color: #64748b;
    text-decoration: none;
    font-size: 0.7rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.copyright-link:hover {
    color: #60a5fa;
}

@media (max-width: 768px) {
    .corporate-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .corporate-bottom .row > div {
        text-align: center !important;
        margin-bottom: 12px;
    }
    .corporate-bottom .row > div:last-child {
        margin-bottom: 0;
    }
    .corporate-copyright .d-flex {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
}
</style>

<!-- Pie de página Rediseñado de Alto Contraste -->
<div class="corporate-footer fade-in-up" style="animation-delay: 0.15s;">
    <div class="corporate-bar">
        <div class="corporate-grid">
            <div class="corporate-item">
                <div class="corporate-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="corporate-info">
                    <strong>Certificación ISO 27001</strong>
                    <span>Seguridad de la información · Datos protegidos</span>
                </div>
            </div>
            <div class="corporate-item">
                <div class="corporate-icon">
                    <i class="fas fa-server"></i>
                </div>
                <div class="corporate-info">
                    <strong>Infraestructura Local 100%</strong>
                    <span>Sin conexión a servicios externos · Soberanía total</span>
                </div>
            </div>
            <div class="corporate-item">
                <div class="corporate-icon">
                    <i class="fab fa-github-alt"></i>
                </div>
                <div class="corporate-info">
                    <strong>Open Source · Shareware v2.0</strong>
                    <span>Código abierto · Sin fines de lucro · Libre distribución</span>
                </div>
            </div>
            <div class="corporate-item">
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
                        <i class="fas fa-user-check" style="color: #60a5fa; font-size: 1.1rem;"></i>
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
                    <span>v2.0 · Estable</span>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <div class="d-flex align-items-center justify-content-md-end justify-content-center gap-3">
                    <div class="d-flex align-items-center gap-2 text-start text-md-end">
                        <div>
                            <strong><?php echo htmlspecialchars($config_empresa['jefe_proyecto']); ?></strong>
                            <br><small>Jefe de Proyecto · Director Técnico</small>
                        </div>
                        <i class="fas fa-certificate" style="color: #a78bfa; font-size: 1.1rem; margin-left: 6px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="corporate-copyright">
        <div class="d-flex flex-wrap justify-content-center justify-content-md-between align-items-center gap-3">
            <div class="copyright-logo">
                <img src="<?php echo $base_prefix; ?>../images/unicorn.png" alt="Unicornio" width="20" height="20" style="filter: brightness(0) invert(1);" onerror="this.style.display='none';">
                <span>Copyright © <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></strong> · Todos los derechos reservados</span>
            </div>
            <div class="copyright-links">
                <a href="<?php echo $base_prefix; ?>../../terminos.php" class="copyright-link"><i class="fas fa-file-contract"></i> Términos</a>
                <span style="color: rgba(255,255,255,0.15)">|</span>
                <a href="<?php echo $base_prefix; ?>../../privacidad.php" class="copyright-link"><i class="fas fa-lock"></i> Privacidad</a>
                <span style="color: rgba(255,255,255,0.15)">|</span>
                <a href="<?php echo $base_prefix; ?>../../soporte.php" class="copyright-link"><i class="fas fa-envelope"></i> Soporte</a>
            </div>
        </div>
    </div>
</div>
