<?php
// sidebar.php - Maneja rutas de fotos tanto en raíz como en modules

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración por defecto (desde constantes centrales con fallback)
if (!isset($config_empresa)) {
    $config_empresa = [
        'nombre_empresa' => defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom',
        'jefe_proyecto' => defined('JEFE_PROYECTO') ? JEFE_PROYECTO : 'Nombre Director',
        'especialista_gestion' => defined('ESPECIALISTA') ? ESPECIALISTA : 'Esp. Contab y Finanzas'
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

$is_google_auth = (isset($_SESSION['auth_provider']) && $_SESSION['auth_provider'] === 'google');

// ==========================================
// OBTENER EL TIPO DE USUARIO REAL DESDE LA BD
// (no depender solo de la sesión, que puede estar desactualizada)
// ==========================================
if ($user_id && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.descripcion AS rol_descripcion
            FROM clasif_usuarios u
            LEFT JOIN clasif_rol r ON u.rol_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $rol_db = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rol_db && !empty($rol_db['rol_descripcion'])) {
            $user_rol_desc = $rol_db['rol_descripcion'];
        }
    } catch (PDOException $e) {
        // Mantener el valor de sesión
    }
}

// ==========================================
// OBTENER FOTO DEL USUARIO - MANEJO DE RUTAS
// ==========================================
$user_foto = null;
$defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

if ($is_google_auth && !empty($_SESSION['user_foto'])) {
    $user_foto = $_SESSION['user_foto'];
} elseif ($user_id) {
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
                    // Limpiar la ruta (eliminar posibles duplicados y prefijos)
                    $foto_rel = ltrim($foto_valor, './');
                    $url_prefix = $is_in_modules ? '../' : '';
                    $root_abs = dirname(__DIR__); // carpeta NOMINAS
                    $candidatas = [];

                    // Si la ruta es relativa a la raíz de NOMINAS (assets/imagenes/usuarios, trabajadores, ...), usarla tal cual
                    if (strpos($foto_rel, 'assets/') === 0) {
                        $candidatas[] = $foto_rel;
                    } else {
                        $candidatas[] = 'assets/imagenes/' . $foto_rel;
                        $candidatas[] = 'assets/imagenes/trabajadores/' . basename($foto_rel);
                        $candidatas[] = 'assets/imagenes/usuarios/' . basename($foto_rel);
                    }

                    $user_foto = $defaultSvg;
                    foreach ($candidatas as $cand) {
                        if (file_exists($root_abs . '/' . $cand)) {
                            $user_foto = $url_prefix . $cand;
                            break;
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
                            $foto_rel = ltrim($foto_valor, './');
                            $url_prefix = $is_in_modules ? '../' : '';
                            $root_abs = dirname(__DIR__);
                            $candidatas = [];
                            if (strpos($foto_rel, 'assets/') === 0) {
                                $candidatas[] = $foto_rel;
                            } else {
                                $candidatas[] = 'assets/imagenes/' . $foto_rel;
                                $candidatas[] = 'assets/imagenes/trabajadores/' . basename($foto_rel);
                                $candidatas[] = 'assets/imagenes/usuarios/' . basename($foto_rel);
                            }
                            $user_foto = $defaultSvg;
                            foreach ($candidatas as $cand) {
                                if (file_exists($root_abs . '/' . $cand)) { $user_foto = $url_prefix . $cand; break; }
                            }
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
    --win-sidebar-width:16.5625rem;
    --win-sidebar-collapsed-width:4.6875rem;
    --win-accent-color:var(--accent, #60a5fa);
    --win-accent-gradient:linear-gradient(135deg, var(--accent, #60a5fa), var(--accent-light, #a78bfa));
    --win-sidebar-bg:rgba(16, 16, 22, 0.95);
    --win-sidebar-border:rgba(255, 255, 255, 0.06);
    --win-sidebar-text:rgba(255, 255, 255, 0.7);
    --win-sidebar-text-dim:rgba(255, 255, 255, 0.45);
    --win-sidebar-cat-color:rgba(255, 255, 255, 0.3);
    --win-sidebar-item-bg:rgba(255, 255, 255, 0.03);
    --win-sidebar-item-border:rgba(255, 255, 255, 0.05);
    --win-sidebar-profile-bg:rgba(255, 255, 255, 0.03);
    --win-sidebar-profile-border:rgba(255, 255, 255, 0.05);
    --win-sidebar-scrollbar:rgba(255, 255, 255, 0.08);
    --win-sidebar-scrollbar-hover:rgba(255, 255, 255, 0.18);
    --win-sidebar-logo-small:rgba(255, 255, 255, 0.4);
    --win-sidebar-submenu-border:rgba(255, 255, 255, 0.08);
}
html[data-theme="light"] {
    --win-sidebar-bg:rgba(255, 255, 255, 0.95);
    --win-sidebar-border:rgba(0, 0, 0, 0.08);
    --win-sidebar-text:rgba(31, 41, 55, 0.75);
    --win-sidebar-text-dim:rgba(31, 41, 55, 0.5);
    --win-sidebar-cat-color:rgba(31, 41, 55, 0.35);
    --win-sidebar-item-bg:rgba(0, 0, 0, 0.02);
    --win-sidebar-item-border:rgba(0, 0, 0, 0.04);
    --win-sidebar-profile-bg:rgba(0, 0, 0, 0.02);
    --win-sidebar-profile-border:rgba(0, 0, 0, 0.05);
    --win-sidebar-scrollbar:rgba(0, 0, 0, 0.06);
    --win-sidebar-scrollbar-hover:rgba(0, 0, 0, 0.12);
    --win-sidebar-logo-small:rgba(31, 41, 55, 0.5);
    --win-sidebar-submenu-border:rgba(0, 0, 0, 0.06);
}
html[data-theme="blue"] {
    --win-sidebar-bg:rgba(12, 22, 42, 0.97);
    --win-sidebar-border:rgba(130, 190, 255, 0.12);
    --win-sidebar-text:rgba(180, 210, 255, 0.78);
    --win-sidebar-text-dim:rgba(130, 190, 255, 0.5);
    --win-sidebar-cat-color:rgba(130, 190, 255, 0.35);
    --win-sidebar-item-bg:rgba(130, 190, 255, 0.05);
    --win-sidebar-item-border:rgba(130, 190, 255, 0.08);
    --win-sidebar-profile-bg:rgba(130, 190, 255, 0.05);
    --win-sidebar-profile-border:rgba(130, 190, 255, 0.08);
    --win-sidebar-scrollbar:rgba(59, 130, 246, 0.12);
    --win-sidebar-scrollbar-hover:rgba(59, 130, 246, 0.25);
    --win-sidebar-logo-small:rgba(130, 190, 255, 0.5);
    --win-sidebar-submenu-border:rgba(59, 130, 246, 0.12);
}
html[data-theme="verde"] {
    --win-sidebar-bg:rgba(8, 18, 10, 0.97);
    --win-sidebar-border:rgba(120, 200, 130, 0.12);
    --win-sidebar-text:rgba(180, 230, 185, 0.78);
    --win-sidebar-text-dim:rgba(120, 200, 130, 0.5);
    --win-sidebar-cat-color:rgba(120, 200, 130, 0.35);
    --win-sidebar-item-bg:rgba(120, 200, 130, 0.05);
    --win-sidebar-item-border:rgba(120, 200, 130, 0.08);
    --win-sidebar-profile-bg:rgba(120, 200, 130, 0.05);
    --win-sidebar-profile-border:rgba(120, 200, 130, 0.08);
    --win-sidebar-scrollbar:rgba(52, 211, 153, 0.12);
    --win-sidebar-scrollbar-hover:rgba(52, 211, 153, 0.25);
    --win-sidebar-logo-small:rgba(120, 200, 130, 0.5);
    --win-sidebar-submenu-border:rgba(52, 211, 153, 0.12);
}
html[data-theme="orgullo"] {
    --win-sidebar-bg:rgba(250, 247, 254, 0.97);
    --win-sidebar-border:rgba(139, 92, 246, 0.14);
    --win-sidebar-text:rgba(51, 38, 77, 0.78);
    --win-sidebar-text-dim:rgba(109, 91, 145, 0.6);
    --win-sidebar-cat-color:rgba(124, 58, 237, 0.45);
    --win-sidebar-item-bg:rgba(139, 92, 246, 0.06);
    --win-sidebar-item-border:rgba(139, 92, 246, 0.1);
    --win-sidebar-profile-bg:rgba(139, 92, 246, 0.06);
    --win-sidebar-profile-border:rgba(139, 92, 246, 0.1);
    --win-sidebar-scrollbar:rgba(139, 92, 246, 0.15);
    --win-sidebar-scrollbar-hover:rgba(139, 92, 246, 0.3);
    --win-sidebar-logo-small:rgba(109, 91, 145, 0.55);
    --win-sidebar-submenu-border:rgba(139, 92, 246, 0.12);
}

.win-sidebar {
    position: fixed;
    left:0;
    top:0;
    height:100vh;
    width:var(--win-sidebar-width);
    background: var(--win-sidebar-bg);
    backdrop-filter: blur(1.875rem);
    -webkit-backdrop-filter: blur(1.875rem);
    border-right: 0.0625rem solid var(--win-sidebar-border);
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
    width:var(--win-sidebar-collapsed-width);
}

.main-container {
    margin-left:var(--win-sidebar-width);
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.main-container.expanded {
    margin-left:var(--win-sidebar-collapsed-width);
}

/* Red de seguridad: si el sidebar está colapsado, el contenido se alinea automáticamente */
body:has(.win-sidebar.collapsed) .main-container {
    margin-left:var(--win-sidebar-collapsed-width);
}

.win-sidebar.collapsed .sidebar-text,
.win-sidebar.collapsed .nav-category {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    width:0;
    margin:0;
    padding:0;
}

.win-sidebar.collapsed .sidebar-logo {
    padding:1.25rem 0.625rem;
}

.win-sidebar.collapsed .sidebar-logo img {
    width:2.625rem !important;
    height:2.625rem !important;
}

.win-sidebar.collapsed .sidebar-profile {
    padding:0.625rem 0;
    margin:0 0.625rem 0.9375rem 0.625rem;
    background: transparent;
    border-color: transparent;
    justify-content: center;
}

.win-sidebar.collapsed .nav-item {
    justify-content: center;
    padding:0.6875rem 0;
    margin:0.1875rem 0.5rem;
    gap:0;
}

.win-sidebar.collapsed .nav-item i {
    margin:0;
    font-size:1.25rem;
}

.sidebar-logo {
    padding:1rem 1.25rem 0.9375rem 1.25rem;
    transition: padding 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-logo img {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    object-fit: contain;
    width:5.25rem !important;
    height:5.25rem !important;
}
.sidebar-logo-img {
    width:4.6875rem !important;
    height:4.6875rem !important;
    margin:0 auto;
}

.sidebar-logo h3 {
    font-size:1rem;
    font-weight: 600;
    background: var(--win-accent-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin:0.5rem 0 0 0;
    line-height:1.2;
    letter-spacing:0.0188rem;
}

.sidebar-logo small {
    font-size:0.65rem;
    color: var(--win-sidebar-logo-small);
    display: block;
    margin-top:0.0225rem;
    letter-spacing:0.0312rem;
    text-transform: uppercase;
}
.sidebar-subtitle { color: var(--win-sidebar-text) !important; }
.sidebar-version { opacity: 0.9; font-size:0.72rem; color: var(--win-sidebar-text-dim) !important; text-transform: none !important; }

.sidebar-profile {
    display: flex;
    align-items: center;
    gap:0.75rem;
    padding:0.425rem 0.75rem;
    margin:0 0.75rem 0.9375rem 0.75rem;
    background: var(--win-sidebar-profile-bg);
    border: 0.0625rem solid var(--win-sidebar-profile-border);
    border-radius: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.sidebar-profile:hover {
    background: var(--accent-bg2);
    border-color: var(--accent-bg);
    transform: translateY(-0.1625rem);
}

.profile-avatar-link {
    text-decoration: none;
    flex-shrink: 0;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: block;
    line-height:0;
    border-radius: 50%;
}

.profile-avatar-link:hover {
    transform: scale(1.18);
    cursor: pointer;
}

.profile-avatar-link:hover .profile-avatar {
    box-shadow: 0 0 0 0.1875rem var(--accent-bg), 0 0.5rem 1.25rem rgba(0, 0, 0, 0.3);
    border-color: var(--accent);
}

.profile-avatar {
    width:4.125rem;
    height:4.125rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent-light));
    color: #fff;
    font-weight: 700;
    font-size:0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0.25rem 0.75rem var(--accent-bg);
    overflow: hidden;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 0.125rem solid transparent;
}

.profile-avatar-img {
    width:100% !important;
    height:100% !important;
    max-width:100% !important;
    max-height:100% !important;
    object-fit: cover !important;
    object-position: center !important;
    border-radius: 50%;
    position: absolute;
    top:0;
    left:0;
    right:0;
    bottom:0;
    margin:0;
    z-index: 2;
}
.profile-avatar-img-hidden,
.profile-avatar-iniciales-hidden { display: none !important; }
.profile-avatar-img-visible { display: block; }

.profile-avatar-iniciales {
    z-index: 1;
    font-size:0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width:100%;
    height:100%;
}
.profile-avatar-iniciales-visible { display: flex; }

.profile-info {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.profile-name {
    font-size:0.82rem;
    font-weight: 600;
    color: var(--win-sidebar-text);
    text-overflow: ellipsis;
    overflow: hidden;
}

.profile-role {
    font-size:0.68rem;
    color: var(--win-sidebar-text-dim);
    text-overflow: ellipsis;
    overflow: hidden;
    margin-top:0.0625rem;
}

.profile-avatar-link {
    position: relative;
}

.profile-avatar-link::after {
    content: "Ver perfil";
    position: absolute;
    bottom:-1.875rem;
    left:50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size:0.7rem;
    padding:0.25rem 0.5rem;
    border-radius: 0.375rem;
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
    bottom:-1.5625rem;
}

.win-sidebar.collapsed .sidebar-profile {
    flex-direction: column;
    padding:0.625rem 0.3125rem;
}

.win-sidebar.collapsed .profile-info {
    display: none;
}

.win-sidebar.collapsed .profile-avatar {
    width:2.5rem;
    height:2.5rem;
}

.win-sidebar.collapsed .profile-avatar-link:hover {
    transform: scale(1.15);
}

.nav-category {
    font-size:0.65rem;
    font-weight: 700;
    color: var(--win-sidebar-cat-color);
    letter-spacing:0.225rem;
    text-transform: uppercase;
    padding:0.0375rem 0.875rem 0.15rem 0.875rem;
    margin-top:0.3125rem;
    display: block;
}

.sidebar-nav {
    flex: 1;
    padding:0 0.625rem;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
}

.nav-item {
    display: flex;
    align-items: center;
    gap:0.75rem;
    padding:0.3625rem 0.875rem;
    margin:0.125rem 0.25rem;
    border-radius: 0.5rem;
    color: var(--win-sidebar-text);
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 0.0625rem solid transparent;
    cursor: pointer;
}

.nav-item i {
    font-size:1.1rem;
    width:1.25rem;
    text-align: center;
    color: inherit;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.25s ease;
}

.nav-item .sidebar-text {
    font-size:0.80rem;
    font-weight: 700;
    transition: color 0.25s ease, transform 0.25s ease, opacity 0.25s ease;
}

.nav-item .nav-badge {
    transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
}

.nav-item:hover {
    background: var(--accent-bg);
    color: var(--accent);
    border-color: var(--accent);
    transform: translateX(0.25rem);
}

.nav-item:hover i {
    transform: scale(1.45);
    color: var(--accent);
}

.nav-item:hover .sidebar-text {
    transform: translateX(0.125rem);
}

.nav-item:hover .nav-badge {
    transform: scale(2.05);
}

.nav-item:active {
    transform: translateX(0.25rem) scale(0.98);
}

.nav-item.active {
    background: var(--accent) !important;
    color: #fff !important;
    font-weight: 600;
    border-color: transparent !important;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3);
}

.nav-item.active i {
    color: #fff !important;
    transform: scale(1.05);
}
.nav-item.active .sidebar-text {
    color: #fff !important;
}
.nav-item.active .nav-badge {
    background: rgba(255,255,255,0.25) !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.3) !important;
}

.nav-badge {
    margin-left:auto;
    background: var(--accent-bg);
    color: var(--accent);
    font-size:0.65rem;
    font-weight: 600;
    padding:0.125rem 0.375rem;
    border-radius: 1.25rem;
    border: 0.0625rem solid var(--accent-bg);
}

/* Submenú colapsable (Configuración > Usuarios) */
.nav-group .nav-group-chevron {
    margin-left:auto;
    font-size:0.7rem;
    color: var(--win-sidebar-text-dim);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.25s ease;
}
.nav-group:hover .nav-group-chevron {
    color: var(--accent);
    transform: scale(1.2);
}
.nav-group.open .nav-group-chevron {
    transform: rotate(180deg) scale(1.1);
    color: var(--accent);
}
.nav-submenu {
    display: none;
    padding-left:0.375rem;
    overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
}
.nav-group.open > .nav-submenu {
    display: block;
}
.nav-submenu .nav-item {
    padding-left:1.25rem;
    margin-left:0.75rem;
    border-left: 0.0625rem solid var(--win-sidebar-submenu-border);
    animation: submenuFadeIn 0.25s ease forwards;
    opacity: 0;
}
@keyframes submenuFadeIn {
    from { opacity: 0; transform: translateX(-0.5rem); }
    to { opacity: 1; transform: translateX(0); }
}
.nav-submenu .nav-item.active {
    border-left: 0.1875rem solid var(--accent) !important;
}
.win-sidebar.collapsed .nav-group .nav-group-chevron {
    display: none;
}
/* Submenú apilado bajo el icono padre cuando el sidebar está colapsado */
.win-sidebar.collapsed .nav-group .nav-submenu {
    display: block;
    padding-left:0;
}
.win-sidebar.collapsed .nav-submenu .nav-item {
    justify-content: center;
    padding:0.6875rem 0;
    margin:0.1875rem 0.5rem;
    border-left: none;
    gap:0;
}
.win-sidebar.collapsed .nav-submenu .nav-item.active {
    border-left: none;
    border-radius: 0.5rem;
}
.win-sidebar.collapsed .nav-submenu .nav-item i {
    margin:0;
    font-size:1.25rem;
}

.sidebar-footer {
    border-top: 0.0625rem solid var(--win-sidebar-border);
    padding:0.75rem 0.625rem;
}

#logoutSidebarBtn {
    color: var(--red) !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

#logoutSidebarBtn:hover {
    background: var(--accent-bg) !important;
    color: var(--accent) !important;
    border-color: var(--accent) !important;
    transform: translateX(0.25rem);
}

#logoutSidebarBtn:hover i {
    color: var(--accent) !important;
    transform: scale(1.15) rotate(-10deg);
}

.sidebar-nav::-webkit-scrollbar {
    width:0.25rem;
}
.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--win-sidebar-scrollbar);
    border-radius: 0.25rem;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--win-sidebar-scrollbar-hover);
}

@media (max-width: 768px) {
    .win-sidebar {
        width:var(--win-sidebar-width);
        transform: translateX(-100%);
    }
    .win-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 0.5rem 0 2rem rgba(0, 0, 0, 0.5);
    }
    /* En movil la pestañita |> siempre visible (misma mecanica del modo enfoque) */
    html:not(.focus-mode) .focus-tab { display:flex; }
    /* Flecha: derecha=abrir, izquierda=abierto (coherente con modo enfoque) */
    .win-sidebar.mobile-open + .focus-tab { opacity:1; color:var(--accent); }
    .win-sidebar.mobile-open + .focus-tab i { transform:rotate(180deg); }
    /* X de cierre visible en la esquina superior izquierda al abrir el sidebar */
    .win-sidebar .sidebar-close-focus { left:0.5rem; right:auto; }
    .win-sidebar.mobile-open .sidebar-close-focus { display:flex; }
}

/* ===== Pestañita del modo enfoque ===== */
.focus-tab {
    display:none;
    position:fixed;
    left:0;
    top:50%;
    transform:translateY(-50%);
    width:1.375rem;
    height:4.25rem;
    border:none;
    border-radius:0 0.875rem 0.875rem 0;
    background:rgba(17,24,39,0.82);
    -webkit-backdrop-filter:blur(10px);
    backdrop-filter:blur(10px);
    border-top:1px solid rgba(var(--accent-rgb),0.4);
    border-right:1px solid rgba(var(--accent-rgb),0.4);
    border-bottom:1px solid rgba(var(--accent-rgb),0.4);
    border-left:none;
    color:#94a3b8;
    font-size:0.75rem;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    z-index:999;
    padding:0;
    opacity:0.7;
    transition:opacity 0.2s ease,width 0.2s ease,color 0.2s ease;
}
.focus-tab:hover,
.focus-tab:focus-visible {
    opacity:1;
    width:1.75rem;
    color:#f1f5f9;
}
.focus-tab i {
    transition:transform 0.3s ease;
}
[data-theme="light"] .focus-tab {
    background:rgba(255,255,255,0.92);
    color:var(--accent-dark);
    box-shadow:0.125rem 0 0.625rem rgba(0,0,0,0.12);
}
[data-theme="light"] .focus-tab:hover,
[data-theme="light"] .focus-tab:focus-visible {
    color:var(--accent-dark);
}
[data-theme="blue"] .focus-tab {
    background:rgba(20,36,60,0.92);
    color:#9dbde8;
    box-shadow:0.125rem 0 0.625rem rgba(0,0,0,0.25);
    border-top:1px solid rgba(59,130,246,0.4);
    border-right:1px solid rgba(59,130,246,0.4);
    border-bottom:1px solid rgba(59,130,246,0.4);
    border-left:none;
}
[data-theme="blue"] .focus-tab:hover,
[data-theme="blue"] .focus-tab:focus-visible {
    color:#dce8ff;
}
[data-theme="verde"] .focus-tab {
    background:rgba(12,28,14,0.92);
    color:#8ec99a;
    box-shadow:0.125rem 0 0.625rem rgba(0,0,0,0.25);
    border-top:1px solid rgba(52,211,153,0.4);
    border-right:1px solid rgba(52,211,153,0.4);
    border-bottom:1px solid rgba(52,211,153,0.4);
    border-left:none;
}
[data-theme="verde"] .focus-tab:hover,
[data-theme="verde"] .focus-tab:focus-visible {
    color:#d4f0d8;
}

/* Revelar el sidebar en modo enfoque (gana a html.focus-mode .win-sidebar) */
html.focus-mode .win-sidebar:hover,
html.focus-mode .win-sidebar:focus-within,
html.focus-mode body:has(.focus-tab:hover) .win-sidebar,
html.focus-mode .win-sidebar.focus-pinned,
html.focus-mode .win-sidebar.mobile-open {
    transform:translateX(0)!important;
}
/* La pestañita solo existe en modo enfoque */
html.focus-mode .focus-tab { display:flex!important; }
/* Flecha: derecha=abrir (oculto), izquierda=cerrar (revelado/fijado) */
.win-sidebar.focus-pinned + .focus-tab { opacity:1; color:var(--accent); }
.win-sidebar.focus-pinned + .focus-tab i,
html.focus-mode .win-sidebar:hover + .focus-tab i,
html.focus-mode .win-sidebar:focus-within + .focus-tab i,
html.focus-mode body:has(.focus-tab:hover) .win-sidebar + .focus-tab i {
    transform:rotate(180deg);
}

/* X para ocultar el sidebar fijado en modo enfoque */
.sidebar-close-focus {
    display:none;
    position:absolute;
    top:0.5rem;
    right:0.5rem;
    width:1.75rem;
    height:1.75rem;
    border-radius:50%;
    border:none;
    background:rgba(255,255,255,0.08);
    color:#94a3b8;
    font-size:0.8rem;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    z-index:1050;
    padding:0;
    transition:background 0.2s ease,color 0.2s ease;
}
.sidebar-close-focus:hover,
.sidebar-close-focus:focus-visible {
    background:rgba(var(--accent-rgb),0.18);
    color:var(--accent);
}
[data-theme="light"] .sidebar-close-focus {
    background:rgba(0,0,0,0.06);
    color:#64748b;
}
[data-theme="light"] .sidebar-close-focus:hover,
[data-theme="light"] .sidebar-close-focus:focus-visible {
    color:var(--accent-dark);
}
[data-theme="blue"] .sidebar-close-focus {
    background:rgba(130,190,255,0.1);
    color:#9dbde8;
}
[data-theme="blue"] .sidebar-close-focus:hover,
[data-theme="blue"] .sidebar-close-focus:focus-visible {
    color:var(--accent);
}
[data-theme="verde"] .sidebar-close-focus {
    background:rgba(120,200,130,0.1);
    color:#8ec99a;
}
[data-theme="verde"] .sidebar-close-focus:hover,
[data-theme="verde"] .sidebar-close-focus:focus-visible {
    color:var(--accent);
}
html.focus-mode .win-sidebar.focus-pinned .sidebar-close-focus { display:flex!important; }

/* Modo enfoque con sidebar oculto: contenido a todo lo ancho */
html.focus-mode .main-container {
    margin-left:0!important;
    margin-right:0!important;
    width:auto!important;
    max-width:none!important;
}
html.focus-mode .main-container > * {
    max-width:none!important;
}
html.focus-mode .page-content,
html.focus-mode .fluid-container {
    max-width:none!important;
}

/* Sidebar colapsado (sin modo enfoque): contenido a todo lo ancho también */
.main-container.expanded {
    width:auto!important;
    max-width:none!important;
}
.main-container.expanded > * {
    max-width:none!important;
}
.main-container.expanded .page-content,
.main-container.expanded .fluid-container {
    max-width:none!important;
}
/* En móvil el drawer se superpone: el contenido siempre usa todo el ancho */
@media (max-width: 768px) {
    .main-container,
    .main-container > *,
    .main-container .page-content,
    .main-container .fluid-container {
        max-width:none!important;
    }
}
</style>

<div class="win-sidebar" id="winSidebar">
    <button type="button" class="sidebar-close-focus" id="sidebarCloseFocusBtn" aria-label="Ocultar menú lateral" title="Ocultar menú">
        <i class="fas fa-times"></i>
    </button>
    <div class="sidebar-logo text-center">
        <div class="logo">
            <img src="<?php echo $base_prefix; ?>../images/LogoTN.png" alt="Transnubet Logo" 
                 class="sidebar-logo-img"
                 onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-cloud-moon fa-2x mb-2\' style=\'color:var(--accent);\'></i>';"><br>
            <h3 class="sidebar-text"><?php echo htmlspecialchars($config_empresa['nombre_empresa']); ?></h3>
        </div>
        <small class="sidebar-text sidebar-subtitle">Sistema de Gestión de Nóminas</small>
        <small class="sidebar-text sidebar-version"><?php echo defined('SITE_VERSION') ? htmlspecialchars(SITE_VERSION) : 'v2.0.1'; ?> UnicornioSoftware°</small>
    </div>

<div class="sidebar-profile" style="position: relative;">
    
    <!-- Enlace invisible que cubre TODO el div -->
    <a href="<?php echo $profile_link; ?>" 
       class="profile-avatar-link" 
       title="Ver perfil de usuario" 
       data-tooltip="Ver perfil de usuario" 
       data-tooltip-theme="info"
       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; text-decoration: none; opacity: 0;">
    </a>

    <!-- Contenido original intacto -->
    <div class="profile-avatar" id="profileAvatar">
        <img src="<?php echo htmlspecialchars($user_foto); ?>" 
             alt="Foto de perfil" 
             referrerpolicy="no-referrer"
             class="profile-avatar-img profile-avatar-img-hidden"
             onload="this.classList.remove('profile-avatar-img-hidden');this.classList.add('profile-avatar-img-visible');this.parentElement.querySelector('.profile-avatar-iniciales').classList.add('profile-avatar-iniciales-hidden');"
             onerror="this.classList.add('profile-avatar-img-hidden');this.parentElement.querySelector('.profile-avatar-iniciales').classList.remove('profile-avatar-iniciales-hidden');">
        <span class="profile-avatar-iniciales profile-avatar-iniciales-visible"><?php echo htmlspecialchars($user_iniciales); ?></span>
    </div>

    <div class="profile-info sidebar-text">
        <span class="profile-name"><?php echo htmlspecialchars($user_nombre); ?></span>
        <span class="profile-role"><?php echo htmlspecialchars($user_rol_desc); ?></span>
        <div class="mt-1">
            <?php if ($is_google_auth): ?>
                <span class="badge bg-danger" style="font-size:0.6rem; padding:0.15rem 0.45rem;">
                    <i class="fab fa-google me-1"></i>Logueado con Google
                </span>
            <?php else: ?>
                <span class="badge bg-secondary" style="font-size:0.6rem; padding:0.15rem 0.45rem;">
                    <i class="fas fa-database me-1"></i>Logueado desde Base Datos
                </span>
            <?php endif; ?>
        </div>
    </div>

</div>
    
    <nav class="sidebar-nav">
        <span class="nav-category">General</span>
        <?php if (permiso_puede('dashboard', 'ver')): ?>
        <a href="<?php echo $base_prefix; ?>dashboard.php" class="nav-item <?php echo ($current_file == 'dashboard.php') ? 'active' : ''; ?>" data-tooltip="Dashboard" data-tooltip-theme="primary">
            <i class="fas fa-chart-line"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if (permiso_puede('empleados', 'ver')): ?>
        <span class="nav-category">Personal</span>
        <div class="nav-group <?php echo (in_array($current_file, ['empleados.php', 'snc225.php'])) ? 'open' : ''; ?>" id="empleadosNavGroup">
            <a href="<?php echo $base_prefix; ?>modules/empleados.php" class="nav-item <?php echo ($current_file == 'empleados.php') ? 'active' : ''; ?>" data-tooltip="Gestión de Empleados" data-tooltip-theme="primary">
                <i class="fas fa-users"></i>
                <span class="sidebar-text">Empleados</span>
                <i class="fas fa-chevron-down nav-group-chevron sidebar-expand-only" id="empleadosChevron"></i>
            </a>
            <div class="nav-submenu" id="empleadosSubmenu">
                <a href="<?php echo $base_prefix; ?>modules/snc225.php" class="nav-item <?php echo ($current_file == 'snc225.php') ? 'active' : ''; ?>" data-tooltip="Tarjeta SNC-225" data-tooltip-theme="primary">
                    <i class="fas fa-id-card"></i>
                    <span class="sidebar-text">SNC - 225</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (permiso_puede('nominas', 'ver') || permiso_puede('submayor', 'ver')): ?>
        <span class="nav-category">Nóminas y Procesos</span>
        <?php if (permiso_puede('nominas', 'ver')): ?>
        <a href="<?php echo $base_prefix; ?>modules/nominas.php" class="nav-item <?php echo ($current_file == 'nominas.php') ? 'active' : ''; ?>" data-tooltip="Gestión de Nóminas" data-tooltip-theme="primary">
            <i class="fas fa-calculator"></i>
            <span class="sidebar-text">Nóminas</span>
        </a>
        <?php endif; ?>
        <?php if (permiso_puede('submayor', 'ver')): ?>
        <a href="<?php echo $base_prefix; ?>modules/submayor_vacaciones.php" class="nav-item <?php echo ($current_file == 'submayor_vacaciones.php') ? 'active' : ''; ?>" data-tooltip="Submayor de Vacaciones" data-tooltip-theme="primary">
            <i class="fas fa-book"></i>
            <span class="sidebar-text">Submayor Vac.</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (permiso_puede('reportes', 'ver') || permiso_puede('bandecnom', 'ver') || permiso_puede('clasificadores', 'ver')): ?>
        <span class="nav-category">Análisis e Informes</span>
        <?php if (permiso_puede('reportes', 'ver')): ?>
        <a href="<?php echo $base_prefix; ?>modules/reportes.php" class="nav-item <?php echo ($current_file == 'reportes.php') ? 'active' : ''; ?>" data-tooltip="Reportes y Estadísticas" data-tooltip-theme="primary">
            <i class="fas fa-chart-bar"></i>
            <span class="sidebar-text">Reportes</span>
        </a>
        <?php endif; ?>
        <?php if (permiso_puede('bandecnom', 'ver')): ?>
        <a href="<?php echo $base_prefix; ?>modules/bandecnom.php" class="nav-item <?php echo ($current_file == 'bandecnom.php') ? 'active' : ''; ?>" data-tooltip="Exportar al Banco" data-tooltip-theme="primary">
            <i class="fas fa-file-export"></i>
            <span class="sidebar-text">Exportar Banco</span>
            <span class="nav-badge sidebar-text">BETA</span>
        </a>
        <?php endif; ?>
        <?php if (permiso_puede('clasificadores', 'ver')): ?>
        <span class="nav-category">Clasificadores</span>
        <a href="<?php echo $base_prefix; ?>modules/clasificadores.php" class="nav-item <?php echo ($current_file == 'clasificadores.php') ? 'active' : ''; ?>" data-tooltip="Gestión de Clasificadores" data-tooltip-theme="primary">
            <i class="fas fa-folder-tree"></i>
            <span class="sidebar-text">Clasificadores</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (permiso_puede('configuracion', 'ver') || permiso_puede('usuarios', 'ver')): ?>
        <span class="nav-category">Administración</span>
        <div class="nav-group <?php echo (in_array($current_file, ['configuracion.php', 'usuarios.php'])) ? 'open' : ''; ?>" id="configNavGroup">
            <?php if (permiso_puede('configuracion', 'ver')): ?>
            <a href="<?php echo $base_prefix; ?>modules/configuracion.php" class="nav-item <?php echo ($current_file == 'configuracion.php') ? 'active' : ''; ?>" data-tooltip="Configuración del Sistema" data-tooltip-theme="primary">
                <i class="fas fa-cog"></i>
                <span class="sidebar-text">Configuración</span>
                <i class="fas fa-chevron-down nav-group-chevron sidebar-expand-only" id="configChevron"></i>
            </a>
            <?php endif; ?>
            <?php if (permiso_puede('usuarios', 'ver')): ?>
            <div class="nav-submenu" id="configSubmenu">
                <a href="<?php echo $base_prefix; ?>modules/usuarios.php" class="nav-item <?php echo ($current_file == 'usuarios.php') ? 'active' : ''; ?>" data-tooltip="Gestión de Usuarios" data-tooltip-theme="primary">
                    <i class="fas fa-user-shield"></i>
                    <span class="sidebar-text">Usuarios</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="nav-item" id="logoutSidebarBtn" data-tooltip="Cerrar Sesión" data-tooltip-theme="danger">
            <i class="fas fa-sign-out-alt"></i>
            <span class="sidebar-text">Cerrar Sesión</span>
        </div>
    </div>
</div>

<button type="button" class="focus-tab" id="focusTab" aria-label="Mostrar u ocultar el menú lateral" aria-expanded="false" title="Mostrar menú">
    <i class="fas fa-chevron-right"></i>
</button>

<script>
(function() {
    // El botón y #mainContainer se renderizan DESPUÉS de este include,
    // por eso todo se inicializa al cargar el DOM y el click se delega.
    function initSidebar() {
        const sidebar = document.getElementById('winSidebar');
        const mainContainer = document.getElementById('mainContainer');
        if (!sidebar) return;

        // Avatar: sincroniza foto/iniciales aunque el evento load no llegue (cache, bfcache)
        function syncProfileAvatar() {
            sidebar.querySelectorAll('.profile-avatar-img').forEach(function(img) {
                const ini = img.parentElement.querySelector('.profile-avatar-iniciales');
                if (!ini) return;
                if (img.complete && img.naturalWidth > 0) {
                    img.classList.remove('profile-avatar-img-hidden');
                    img.classList.add('profile-avatar-img-visible');
                    ini.classList.add('profile-avatar-iniciales-hidden');
                    ini.classList.remove('profile-avatar-iniciales-visible');
                } else if (img.complete) {
                    img.classList.add('profile-avatar-img-hidden');
                    img.classList.remove('profile-avatar-img-visible');
                    ini.classList.remove('profile-avatar-iniciales-hidden');
                    ini.classList.add('profile-avatar-iniciales-visible');
                }
            });
        }
        syncProfileAvatar();
        window.addEventListener('pageshow', syncProfileAvatar);

        // Modo enfoque: pestañita y botón de alternar comparten el fijado del sidebar
        const focusTab = document.getElementById('focusTab');
        const setFocusPinned = function(on) {
            sidebar.classList.toggle('focus-pinned', on);
            if (focusTab) focusTab.setAttribute('aria-expanded', on ? 'true' : 'false');
        };

        if (focusTab) {
            focusTab.addEventListener('click', function(e) {
                e.stopPropagation();
                /* En movil (fuera del modo enfoque) la pestañita usa mobile-open
                   para reutilizar el backdrop y el cierre por toque externo */
                var esMovil = window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil';
                if (esMovil && !document.documentElement.classList.contains('focus-mode')) {
                    var abierto = sidebar.classList.toggle('mobile-open');
                    focusTab.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                } else {
                    setFocusPinned(!sidebar.classList.contains('focus-pinned'));
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    setFocusPinned(false);
                    if ((window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil')
                        && !document.documentElement.classList.contains('focus-mode')) {
                        sidebar.classList.remove('mobile-open');
                    }
                }
            });
        }

        const closeFocusBtn = document.getElementById('sidebarCloseFocusBtn');
        if (closeFocusBtn) {
            closeFocusBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                setFocusPinned(false);
                /* En movil la X tambien cierra el mobile-open */
                if ((window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil')
                    && !document.documentElement.classList.contains('focus-mode')) {
                    sidebar.classList.remove('mobile-open');
                    if (focusTab) focusTab.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (window.innerWidth > 768) {
            if (localStorage.getItem('winSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                if (mainContainer) mainContainer.classList.add('expanded');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target || !e.target.closest) return;
            if (!e.target.closest('#sidebarToggleBtn')) return;
            e.stopPropagation();
            if (document.documentElement.classList.contains('focus-mode')) {
                setFocusPinned(!sidebar.classList.contains('focus-pinned'));
            } else if (window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil') {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                if (mainContainer) mainContainer.classList.toggle('expanded');
                localStorage.setItem('winSidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });

        document.addEventListener('click', function(e) {
            if ((window.innerWidth <= 768 || document.documentElement.getAttribute('data-device') === 'movil') && !sidebar.contains(e.target) && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }

    function initSubmenu() {
        const configNavGroup = document.getElementById('configNavGroup');
        const configChevron = document.getElementById('configChevron');
        if (configNavGroup && configChevron) {
            configChevron.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                configNavGroup.classList.toggle('open');
            });
        }
        const empleadosNavGroup = document.getElementById('empleadosNavGroup');
        const empleadosChevron = document.getElementById('empleadosChevron');
        if (empleadosNavGroup && empleadosChevron) {
            empleadosChevron.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                empleadosNavGroup.classList.toggle('open');
            });
        }
    }

    function onReady() {
        initSidebar();
        initSubmenu();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
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