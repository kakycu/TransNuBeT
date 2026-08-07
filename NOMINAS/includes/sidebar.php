<?php
// sidebar.php - Maneja rutas de fotos tanto en raíz como en modules

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración por defecto
if (!isset($config_empresa)) {
    $config_empresa = [
        'nombre_empresa' => 'PDL TransNuBeT',
        'jefe_proyecto' => 'Dainelys León Reyes',
        'especialista_gestion' => 'Mailén Pérez García'
    ];
}

// Detectar ubicación actual
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_modules = (strpos($current_script, '/modules/') !== false);
$base_prefix = $is_in_modules ? '../' : '';
$current_file = basename($current_script);

// Recuperar información de sesión
$user_nombre = $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_desc = $_SESSION['rol_descripcion'] ?? 'Administrador';
$user_id = $_SESSION['user_id'] ?? null;

// ==========================================
// OBTENER FOTO DEL USUARIO - MANEJO DE RUTAS
// ==========================================
$user_foto = null;
$defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

if ($user_id) {
    try {
        // Verificar si $pdo existe (desde database.php)
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['foto'])) {
                $foto_valor = $result['foto'];
                
                // ==========================================
                // CASO 1: Es un BLOB (datos binarios)
                // ==========================================
                if (strpos($foto_valor, 'data:image') === 0) {
                    $user_foto = $foto_valor;
                }
                // ==========================================
                // CASO 2: Es una URL absoluta
                // ==========================================
                elseif (filter_var($foto_valor, FILTER_VALIDATE_URL)) {
                    $user_foto = $foto_valor;
                }
                // ==========================================
                // CASO 3: Es un BLOB (datos binarios largos sin texto)
                // ==========================================
                elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false && strpos($foto_valor, '.') === false) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $foto_valor);
                    finfo_close($finfo);
                    $user_foto = 'data:' . ($mime_type ?: 'image/jpeg') . ';base64,' . base64_encode($foto_valor);
                }
                // ==========================================
                // CASO 4: Es una ruta de archivo (manejo según ubicación)
                // ==========================================
                else {
                    // Limpiar la ruta (eliminar posibles duplicados)
                    $foto_limpia = ltrim($foto_valor, './');
                    
                    // Verificar si la ruta ya contiene 'assets/imagenes/'
                    if (strpos($foto_limpia, 'assets/imagenes/') === 0) {
                        $foto_limpia = substr($foto_limpia, strlen('assets/imagenes/'));
                    }
                    
                    // Solo mantener el nombre del archivo si es necesario
                    $nombre_archivo = basename($foto_limpia);
                    
                    // Construir la ruta CORRECTA según dónde estemos
                    if ($is_in_modules) {
                        // Estamos en /modules/ -> usar ../assets/imagenes/trabajadores/
                        $ruta_foto = '../assets/imagenes/trabajadores/' . $nombre_archivo;
                    } else {
                        // Estamos en la raíz -> usar assets/imagenes/trabajadores/
                        $ruta_foto = 'assets/imagenes/trabajadores/' . $nombre_archivo;
                    }
                    
                    // Verificar si el archivo existe
                    if (file_exists($ruta_foto)) {
                        $user_foto = $ruta_foto;
                    } else {
                        // Intentar con la ruta original como está
                        if (file_exists($foto_valor)) {
                            $user_foto = $foto_valor;
                        } elseif ($is_in_modules && file_exists('../' . $foto_valor)) {
                            $user_foto = '../' . $foto_valor;
                        } else {
                            $user_foto = $defaultSvg;
                        }
                    }
                }
            } else {
                $user_foto = $defaultSvg;
            }
        } else {
            // Si $pdo no está disponible, intentar conectar
            $db_path = __DIR__ . '/../config/database.php';
            if (file_exists($db_path)) {
                require_once $db_path;
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['foto'])) {
                        // Misma lógica de manejo de ruta...
                        $foto_valor = $result['foto'];
                        if (strpos($foto_valor, 'data:image') === 0) {
                            $user_foto = $foto_valor;
                        } elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false) {
                            $user_foto = 'data:image/jpeg;base64,' . base64_encode($foto_valor);
                        } else {
                            $nombre_archivo = basename($foto_valor);
                            $user_foto = $is_in_modules ? '../assets/imagenes/trabajadores/' . $nombre_archivo : 'assets/imagenes/trabajadores/' . $nombre_archivo;
                        }
                    } else {
                        $user_foto = $defaultSvg;
                    }
                } else {
                    $user_foto = $defaultSvg;
                }
            } else {
                $user_foto = $defaultSvg;
            }
        }
    } catch (Exception $e) {
        $user_foto = $defaultSvg;
    }
} else {
    $user_foto = $defaultSvg;
}

// Si por algún motivo no hay foto válida, usar default
if (!$user_foto || $user_foto === '') {
    $user_foto = $defaultSvg;
}

// Obtener iniciales para el avatar
$user_iniciales = '';
$nombre_parts = explode(' ', trim($user_nombre));
if (count($nombre_parts) >= 2) {
    $user_iniciales = strtoupper(substr($nombre_parts[0], 0, 1) . substr($nombre_parts[1], 0, 1));
} else {
    $user_iniciales = strtoupper(substr($user_nombre, 0, 2));
}

// Determinar enlace del perfil
$profile_link = $base_prefix . 'modules/users.php?id=' . $user_id;
if (!file_exists(__DIR__ . '/../modules/users.php') && !file_exists(__DIR__ . '/modules/users.php')) {
    $profile_link = $base_prefix . 'modules/configuracion.php?tab=perfil';
}
?>

<style>
/* ==========================================
   ESTILOS AVANZADOS DE ADMIN DASHBOARD (GLASS)
   ========================================== */
:root {
    --win-sidebar-width: 265px;
    --win-sidebar-collapsed-width: 75px;
    --win-accent-color: #60a5fa;
    --win-accent-gradient: linear-gradient(135deg, #60a5fa, #a78bfa);
    --win-sidebar-bg: rgba(16, 16, 22, 0.95);
    --win-sidebar-border: rgba(255, 255, 255, 0.06);
}

.win-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: var(--win-sidebar-width);
    background: var(--win-sidebar-bg);
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border-right: 1px solid var(--win-sidebar-border);
    z-index: 1000;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
}

.sidebar-text, .nav-category {
    opacity: 1;
    visibility: visible;
    transition: opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.win-sidebar.collapsed {
    width: var(--win-sidebar-collapsed-width);
}

.win-sidebar.collapsed .sidebar-text,
.win-sidebar.collapsed .nav-category {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    width: 0;
    margin: 0;
    padding: 0;
}

.win-sidebar.collapsed .sidebar-logo {
    padding: 20px 10px;
}

.win-sidebar.collapsed .sidebar-logo img {
    width: 42px !important;
    height: 42px !important;
}

.win-sidebar.collapsed .sidebar-profile {
    padding: 10px 0;
    margin: 0 10px 15px 10px;
    background: transparent;
    border-color: transparent;
    justify-content: center;
}

.win-sidebar.collapsed .nav-item {
    justify-content: center;
    padding: 11px 0;
    margin: 3px 8px;
    gap: 0;
}

.win-sidebar.collapsed .nav-item i {
    margin: 0;
    font-size: 1.25rem;
}

.sidebar-logo {
    padding: 24px 20px 15px 20px;
    transition: padding 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-logo img {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    object-fit: contain;
    width: 100px !important;
    height: 100px !important;
}

.sidebar-logo h3 {
    font-size: 1.15rem;
    font-weight: 600;
    background: var(--win-accent-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin: 8px 0 0 0;
    line-height: 1.2;
    letter-spacing: 0.3px;
}

.sidebar-logo small {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    display: block;
    margin-top: 1px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.sidebar-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin: 0 12px 15px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.profile-avatar-link {
    text-decoration: none;
    flex-shrink: 0;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: block;
    line-height: 0;
    border-radius: 50%;
}

.profile-avatar-link:hover {
    transform: scale(1.1);
    cursor: pointer;
}

.profile-avatar-link:hover .profile-avatar {
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3), 0 8px 20px rgba(0, 0, 0, 0.3);
    border-color: var(--win-accent-color);
}

.profile-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: #fff;
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    overflow: hidden;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}

.profile-avatar-iniciales {
    z-index: 1;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.profile-info {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.profile-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #f8fafc;
    text-overflow: ellipsis;
    overflow: hidden;
}

.profile-role {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.45);
    text-overflow: ellipsis;
    overflow: hidden;
    margin-top: 1px;
}

.profile-avatar-link {
    position: relative;
}

.profile-avatar-link::after {
    content: "Ver perfil";
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 0.7rem;
    padding: 4px 8px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 100;
}

.profile-avatar-link:hover::after {
    opacity: 1;
    visibility: visible;
    bottom: -25px;
}

.win-sidebar.collapsed .sidebar-profile {
    flex-direction: column;
    padding: 10px 5px;
}

.win-sidebar.collapsed .profile-info {
    display: none;
}

.win-sidebar.collapsed .profile-avatar {
    width: 40px;
    height: 40px;
}

.win-sidebar.collapsed .profile-avatar-link:hover {
    transform: scale(1.15);
}

.nav-category {
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.3);
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 15px 14px 6px 14px;
    margin-top: 5px;
    display: block;
}

.sidebar-nav {
    flex: 1;
    padding: 0 10px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 14px;
    margin: 2px 4px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}

.nav-item i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    transition: transform 0.2s ease, color 0.2s ease;
}

.nav-item .sidebar-text {
    font-size: 0.88rem;
    font-weight: 500;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.03);
}

.nav-item:hover i {
    transform: scale(1.08);
    color: var(--win-accent-color);
}

.nav-item.active {
    background: rgba(96, 165, 250, 0.1);
    color: var(--win-accent-color);
    font-weight: 600;
    border-color: rgba(96, 165, 250, 0.15);
    border-left: 3px solid var(--win-accent-color) !important;
    border-radius: 0 8px 8px 0;
}

.nav-item.active i {
    color: var(--win-accent-color);
}

.nav-badge {
    margin-left: auto;
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 20px;
    border: 1px solid rgba(96, 165, 250, 0.3);
}

.sidebar-footer {
    border-top: 1px solid var(--win-sidebar-border);
    padding: 12px 10px;
}

#logoutSidebarBtn {
    color: rgba(248, 113, 113, 0.85);
}

#logoutSidebarBtn:hover {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.18);
}

#logoutSidebarBtn:hover i {
    color: #f87171;
}

.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}
.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 4px;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.18);
}

@media (max-width: 768px) {
    .win-sidebar {
        width: var(--win-sidebar-width);
        transform: translateX(-100%);
    }
    .win-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 8px 0 32px rgba(0, 0, 0, 0.5);
    }
}
</style>

<div class="win-sidebar" id="winSidebar">
    <div class="sidebar-logo text-center">
        <div class="logo">
            <img src="<?php echo $base_prefix; ?>../images/LogoTN.png" alt="Transnubet Logo" 
                 style="width: 75px; height: 75px; margin: 0 auto;"
                 onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon fa-2x mb-2\' style=\'color: #60a5fa;\'></i>';"><br>
            <h3 class="sidebar-text"><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h3>
        </div>
        <small class="sidebar-text">Sistema de Gestión de Nóminas</small>
    </div>

    <div class="sidebar-profile">
        <a href="<?php echo $profile_link; ?>" class="profile-avatar-link" title="Ver perfil de usuario">
            <div class="profile-avatar" id="profileAvatar">
                <img src="<?php echo htmlspecialchars($user_foto); ?>" 
                     alt="Foto de perfil" 
                     class="profile-avatar-img" 
                     style="display: none;"
                     onload="this.style.display='block'; this.parentElement.querySelector('.profile-avatar-iniciales').style.display='none';"
                     onerror="this.style.display='none'; this.parentElement.querySelector('.profile-avatar-iniciales').style.display='flex';">
                <span class="profile-avatar-iniciales" style="display: flex;"><?php echo htmlspecialchars($user_iniciales); ?></span>
            </div>
        </a>
        <div class="profile-info sidebar-text">
            <span class="profile-name"><?php echo htmlspecialchars($user_nombre); ?></span>
            <span class="profile-role"><?php echo htmlspecialchars($user_rol_desc); ?></span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <span class="nav-category">General</span>
        <a href="<?php echo $base_prefix; ?>dashboard.php" class="nav-item <?php echo ($current_file == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <span class="nav-category">Personal</span>
        <a href="<?php echo $base_prefix; ?>modules/empleados.php" class="nav-item <?php echo ($current_file == 'empleados.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span class="sidebar-text">Empleados</span>
        </a>

        <span class="nav-category">Nóminas y Procesos</span>
        <a href="<?php echo $base_prefix; ?>modules/nominas.php" class="nav-item <?php echo ($current_file == 'nominas.php') ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i>
            <span class="sidebar-text">Nóminas</span>
        </a>
        <a href="<?php echo $base_prefix; ?>modules/submayor_vacaciones.php" class="nav-item <?php echo ($current_file == 'submayor_vacaciones.php') ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span class="sidebar-text">Submayor Vac.</span>
        </a>

        <span class="nav-category">Análisis e Informes</span>
        <a href="<?php echo $base_prefix; ?>modules/reportes.php" class="nav-item <?php echo ($current_file == 'reportes.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span class="sidebar-text">Reportes</span>
        </a>
        <?php if ($current_file == 'bandecnom.php'): ?>
        <a href="<?php echo $base_prefix; ?>modules/bandecnom.php" class="nav-item <?php echo ($current_file == 'bandecnom.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span class="sidebar-text">Exportar Banco</span>
            <span class="nav-badge sidebar-text">BETA</span>
        </a>
        <?php endif; ?>
        <span class="nav-category">Clasificadores</span>
        <a href="<?php echo $base_prefix; ?>modules/clasificadores.php" class="nav-item <?php echo ($current_file == 'clasificadores.php') ? 'active' : ''; ?>">
            <i class="fas fa-folder-tree"></i>
            <span class="sidebar-text">Clasificadores</span>
        </a>
        <span class="nav-category">Administración</span>
        <a href="<?php echo $base_prefix; ?>modules/configuracion.php" class="nav-item <?php echo ($current_file == 'configuracion.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span class="sidebar-text">Configuración</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="nav-item" id="logoutSidebarBtn" style="cursor: pointer;">
            <i class="fas fa-sign-out-alt"></i>
            <span class="sidebar-text">Cerrar Sesión</span>
        </div>
    </div>
</div>

<script>
(function() {
    const sidebar = document.getElementById('winSidebar');
    const mainContainer = document.getElementById('mainContainer');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    
    if (!sidebar) return;

    if (window.innerWidth > 768) {
        if (localStorage.getItem('winSidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            if (mainContainer) mainContainer.classList.add('expanded');
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                if (mainContainer) mainContainer.classList.toggle('expanded');
                localStorage.setItem('winSidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
})();

// Logout
const logoutSidebarBtn = document.getElementById('logoutSidebarBtn');
if (logoutSidebarBtn) {
    logoutSidebarBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '<i class="fas fa-sign-out-alt" style="color: #ef4444"></i> Cerrar sesión',
                text: '¿Está seguro que desea salir del sistema?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#D13438',
                cancelButtonColor: '#2D2D2D',
                confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, salir',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                background: '#1F1F1F',
                color: '#FFFFFF'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo $base_prefix; ?>logout.php';
                }
            });
        } else {
            window.location.href = '<?php echo $base_prefix; ?>logout.php';
        }
    });
}
</script>