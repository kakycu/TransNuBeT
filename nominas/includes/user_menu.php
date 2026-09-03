<?php
// includes/user_menu.php - Menú de usuario unificado (misma lógica que sidebar.php)

// Detectar ubicación actual
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_modules = (strpos($current_script, '/modules/') !== false);
$base_prefix = $is_in_modules ? '../' : '';

// Variable para controlar si mostrar el reloj
$show_clock = $show_clock ?? true;

// Recuperar datos del usuario de la sesión
$user_nombre_completo = $_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? '';
$user_rol_descripcion = $_SESSION['rol_descripcion'] ?? $user_rol_codigo;
$user_ci = $_SESSION['user_ci'] ?? $_SESSION['usuario_ci'] ?? '';
$user_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;

$user_email     = $_SESSION['user_email'] ?? $_SESSION['usuario_email'] ?? '';
$is_google_auth = (isset($_SESSION['auth_provider']) && $_SESSION['auth_provider'] === 'google');


// Roles autorizados para restaurar la base de datos (todos pueden hacer salvas)
$puede_restaurar = in_array(strtolower(trim($user_rol_codigo)), ['admin', 'soft', 'editor'], true);

// ==========================================
// OBTENER EL TIPO DE USUARIO REAL DESDE LA BD
// (no depender solo de la sesión, que puede estar desactualizada)
// ==========================================
if ($user_id && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.codigo AS rol_codigo, r.descripcion AS rol_descripcion
            FROM clasif_usuarios u
            LEFT JOIN clasif_rol r ON u.rol_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $rol_db = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rol_db && !empty($rol_db['rol_descripcion'])) {
            $user_rol_codigo = $rol_db['rol_codigo'];
            $user_rol_descripcion = $rol_db['rol_descripcion'];
        }
    } catch (PDOException $e) {
        // Mantener los valores de sesión
    }
}

// ==========================================
// MATRIZ DE PERMISOS PARA EL MODAL DE ROLES
// ==========================================
$matriz_permisos_json = '';
if (function_exists('permiso_matriz')) {
    $matriz_permisos_json = json_encode(permiso_matriz(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ==========================================
// OBTENER FOTO DEL USUARIO - MISMA LÓGICA QUE SIDEBAR.PHP
// ==========================================
$user_foto_menu = null;
$defaultSvgMenu = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228 5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

if ($is_google_auth && !empty($_SESSION['user_foto'])) {
    $user_foto_menu = $_SESSION['user_foto'];
} elseif ($user_id) {
    try {
        // Verificar si $pdo existe (desde database.php)
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT foto FROM clasif_usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['foto'])) {
                $foto_valor = $result['foto'];
                
                // CASO 1: Es un BLOB (datos binarios)
                if (strpos($foto_valor, 'data:image') === 0) {
                    $user_foto_menu = $foto_valor;
                }
                // CASO 2: Es una URL absoluta
                elseif (filter_var($foto_valor, FILTER_VALIDATE_URL)) {
                    $user_foto_menu = $foto_valor;
                }
                // CASO 3: Es un BLOB (datos binarios largos sin texto)
                elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false && strpos($foto_valor, '.') === false) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $foto_valor);
                    finfo_close($finfo);
                    $user_foto_menu = 'data:' . ($mime_type ?: 'image/jpeg') . ';base64,' . base64_encode($foto_valor);
                }
                // CASO 4: Es una ruta de archivo (manejo según ubicación)
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

                    $user_foto_menu = $defaultSvgMenu;
                    foreach ($candidatas as $cand) {
                        if (file_exists($root_abs . '/' . $cand)) {
                            $user_foto_menu = $url_prefix . $cand;
                            break;
                        }
                    }
                }
            } else {
                $user_foto_menu = $defaultSvgMenu;
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
                        $foto_valor = $result['foto'];
                        if (strpos($foto_valor, 'data:image') === 0) {
                            $user_foto_menu = $foto_valor;
                        } elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false) {
                            $user_foto_menu = 'data:image/jpeg;base64,' . base64_encode($foto_valor);
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
                            $user_foto_menu = $defaultSvgMenu;
                            foreach ($candidatas as $cand) {
                                if (file_exists($root_abs . '/' . $cand)) { $user_foto_menu = $url_prefix . $cand; break; }
                            }
                        }
                    } else {
                        $user_foto_menu = $defaultSvgMenu;
                    }
                } else {
                    $user_foto_menu = $defaultSvgMenu;
                }
            } else {
                $user_foto_menu = $defaultSvgMenu;
            }
        }
    } catch (Exception $e) {
        $user_foto_menu = $defaultSvgMenu;
    }
} else {
    $user_foto_menu = $defaultSvgMenu;
}

// Si por algún motivo no hay foto válida, usar default
if (!$user_foto_menu || $user_foto_menu === '') {
    $user_foto_menu = $defaultSvgMenu;
}

// Obtener iniciales
$user_iniciales_menu = '';
$nombre_parts_menu = explode(' ', trim($user_nombre_completo));
if (count($nombre_parts_menu) >= 2) {
    $user_iniciales_menu = strtoupper(substr($nombre_parts_menu[0], 0, 1) . substr($nombre_parts_menu[1], 0, 1));
} else {
    $user_iniciales_menu = strtoupper(substr($user_nombre_completo, 0, 2));
}
?>

<style>
.user-menu-container {
    display: flex;
    align-items: center;
    gap:1rem;
}
.date-badge {
    background: rgba(255, 255, 255, 0.08);
    padding:0.5rem 1rem;
    border-radius: 0.75rem;
    font-size:0.85rem;
    color: #ffffff;
}
#liveClockMenu {
    display: inline-block;
    min-width:5.3125rem;
    text-align: center;
}

/* ===== Botón de personalización (#tpSettingsBtn): adaptado a tema y acento ===== */
#tpSettingsBtn {
    background: rgba(var(--accent-rgb), 0.08) !important;
    border: 0.0625rem solid rgba(var(--accent-rgb), 0.3) !important;
    color: var(--accent) !important;
    cursor: pointer;
    transition: all 0.2s;
}
#tpSettingsBtn:hover {
    background: rgba(var(--accent-rgb), 0.2) !important;
    border-color: var(--accent) !important;
    color: var(--accent) !important;
    transform: translateY(-0.0625rem);
}
[data-theme="light"] #tpSettingsBtn {
    background: rgba(var(--accent-rgb), 0.07) !important;
    border-color: rgba(var(--accent-rgb), 0.35) !important;
    color: var(--accent-dark) !important;
}
[data-theme="light"] #tpSettingsBtn:hover {
    background: rgba(var(--accent-rgb), 0.15) !important;
    border-color: var(--accent-dark) !important;
    color: var(--accent-dark) !important;
}
.user-avatar {
    width: 2.5rem;
    height: 2.5rem;
    min-width: 2.5rem;
    min-height: 2.5rem;
    background: linear-gradient(135deg, var(--accent), var(--accent-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    padding: 0 !important;
    border: none !important;
}

.user-avatar-img {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important;
    object-position: center !important;
    border-radius: 50% !important;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    margin: 0;
    z-index: 2;
}
.user-avatar:hover {
    transform: scale(1.18);
}

.user-avatar-iniciales {
    z-index: 1;
    font-size:1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width:100%;
    height:100%;
    color: white;
}
.dropdown-menu-win {
    background: rgba(32, 32, 40, 0.98) !important;
    backdrop-filter: blur(1.25rem) !important;
    border: 0.0625rem solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 0.75rem !important;
    padding:0.5rem !important;
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.4) !important;
}
.dropdown-menu-win .dropdown-item {
    color: #ffffff !important;
    border-radius: 0.5rem !important;
    padding:0.625rem 1rem !important;
    font-size:0.9rem !important;
}
.dropdown-menu-win .dropdown-item:hover {
    background: rgba(var(--accent-rgb), 0.2) !important;
}
.dropdown-menu-win .dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.2) !important;
}
.dropdown-menu-win .dropdown-divider {
    border-color: rgba(255, 255, 255, 0.1) !important;
}
/* Agregar o modificar estas líneas en el style del user_menu.php */
.dropdown-menu-win .dropdown-item-text {
    color: #ffffff !important;
}

.dropdown-menu-win .dropdown-item-text .fw-bold {
    color: #ffffff !important;
}

.dropdown-menu-win .dropdown-item-text small {
    color: var(--accent) !important;
}

.dropdown-menu-win .badge {
    background: linear-gradient(135deg, var(--accent), var(--accent-light)) !important;
    color: white !important;
}
/* ===== SUBMENÚ DE GOOGLE (SIEMPRE A LA IZQUIERDA) ===== */
.dropdown-submenu {
    position: relative;
}

.dropdown-submenu .dropdown-submenu-menu {
    position: absolute;
    top: 0;
    right: 100%;
    margin-top: -0.5rem;
    margin-right: 0.125rem;
    display: none;
    min-width: 12.5rem;
}

/* Mostrar el submenú al pasar el mouse (hover) */
.dropdown-submenu:hover > .dropdown-submenu-menu,
.dropdown-submenu:focus-within > .dropdown-submenu-menu {
    display: block;
}

/* Flecha indicadora del submenú (apunta hacia la izquierda y está al inicio) */
.dropdown-submenu > .dropdown-toggle::before {
    content: "";
    display: inline-block;
    border-top: 0.3em solid transparent;
    border-right: 0.3em solid; /* <--- Flecha hacia la izquierda */
    border-bottom: 0.3em solid transparent;
    border-left: 0;
    vertical-align: middle;
    margin-right: 0.4rem; /* <--- Espacio entre la flecha y el icono de Google */
}

/* En pantallas pequeñas, el submenú se abre hacia abajo */
@media (max-width: 576px) {
    .dropdown-submenu .dropdown-submenu-menu {
        position: static;
        right: auto;
        margin-left: 1rem; /* Sangría para móviles */
    }
}
/* Impedir que el submenú bloqueado se abra */
.disabled-submenu:hover > .dropdown-submenu-menu,
.disabled-submenu:focus-within > .dropdown-submenu-menu {
    display: none !important;
}
</style>

<!-- ===== SISTEMA DE TEMA CLARO / OSCURO (TransNuBeT) ===== -->
<style id="themeSystemStyles">
/* ===== VARIABLES DE COLOR DE ÉXITO (verde) DEPENDIENTES DEL TEMA ===== */
:root {
    --bg:#090d14;
    --panel:#10151f;
    --panel-2:#151b2a;
    --card:rgba(17, 23, 35, 0.88);
    --card-hover:rgba(22, 29, 44, 0.95);
    --border:rgba(255, 255, 255, 0.07);
    --border-2:rgba(255, 255, 255, 0.13);
    --txt:#e8edf6;
    --muted:#97a5bb;
    --faint:#64748b;
    --blue:var(--accent);
    --indigo:#6366f1;
    --violet:var(--accent-light);
    --green:var(--color-success);
    --amber:#f59e0b;
    --red:#ef4444;
    --cyan:#06b6d4;
    --shadow:0 0.75rem 2.125rem rgba(0, 0, 0, 0.35);
    --color-success:#10b981;
    --color-success-rgb:16, 185, 129;
    --color-success-soft:#34d399;
    --color-success-soft-rgb:52, 211, 153;
    --indigo-soft:#818cf8;
    --violet-soft:var(--accent);
    --amber-soft:#fcd34d;
    --amber-soft-rgb:252, 211, 77;
    --blue-soft:#93c5fd;
    --blue-soft-rgb:147, 197, 253;
}
/* FORZAR AL CONTENEDOR PADRE (WIN-TOPBAR) A OCUPAR EL 100% */
.win-topbar,
.main-header,
.header-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    box-sizing: border-box !important;
}

/* Si el header usa padding, asegurarse de que no encaja los elementos */
.win-topbar > *,
.main-header > *,
.header-wrapper > * {
    flex-shrink: 0 !important;
}

/* Utilidades Bootstrap de éxito dependientes del tema */
.text-success { color: var(--color-success) !important; }
.bg-success, .badge.bg-success { background-color: rgba(var(--color-success-rgb), var(--bs-bg-opacity, 1)) !important; }
.btn-success { background-color: var(--color-success) !important; border-color: var(--color-success) !important; }
.alert-success { color: var(--color-success) !important; }

[data-theme="light"] body { background: #dbe8fb !important; color: #1f2937 !important; }

/* ===== VARIABLES CSS (submayor_vacaciones.php y similares) ===== */
html[data-theme="light"] {
    --bg:#e1ecfb;
    --panel:#ffffff;
    --panel-2:#f3f5f9;
    --card:#ffffff;
    --card-hover:#f7f9fc;
    --border:rgba(0,0,0,0.12);
    --border-2:rgba(0,0,0,0.18);
    --txt:#1f2937;
    --muted:#4b5563;
    --faint:#6b7280;
    --blue:var(--accent-dark);
    --indigo:#6366f1;
    --violet:var(--accent-light);
    --green:#059669;
    --amber:#d97706;
    --red:#dc2626;
    --cyan:#0891b2;
    --shadow:0 0.75rem 2.125rem rgba(0,0,0,0.12);
    --color-success:#047857;
    --color-success-rgb:4, 120, 87;
    --color-success-soft:#059669;
    --color-success-soft-rgb:5, 150, 105;
    --indigo-soft:#4f46e5;
    --violet-soft:#7c3aed;
    --amber-soft:#b45309;
    --amber-soft-rgb:180, 83, 9;
    --blue-soft:#1d4ed8;
    --blue-soft-rgb:29, 78, 216;
}
[data-theme="light"] .win11-bg { background: linear-gradient(135deg, #d6e6fb 0%, #c9def7 50%, #e1ecfb 100%) !important; }
[data-theme="light"] .win11-bg::before {
    background-image: radial-gradient(circle at 20% 80%, rgba(var(--accent-rgb),0.10) 0%, transparent 50%),
                      radial-gradient(circle at 80% 20%, rgba(16,124,16,0.07) 0%, transparent 50%) !important;
}
[data-theme="light"] .glass-card {
    background: rgba(255,255,255,0.80) !important;
    border: 0.0625rem solid rgba(0,0,0,0.08) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .glass-card:hover {
    background: rgba(255,255,255,0.94) !important;
    border-color: rgba(var(--accent-rgb),0.35) !important;
}
[data-theme="light"] .win-sidebar {
    background: rgba(255,255,255,0.94) !important;
    border-right: 0.0625rem solid rgba(0,0,0,0.08) !important;
}
[data-theme="light"] {
    --win-sidebar-bg:rgba(255,255,255,0.94);
    --win-sidebar-border:rgba(0,0,0,0.1);
}
[data-theme="light"] .sidebar-footer { border-top: 0.0625rem solid rgba(0,0,0,0.08) !important; }
[data-theme="light"] .sidebar-logo { border-bottom: 0.0625rem solid rgba(0,0,0,0.08) !important; }
[data-theme="light"] .sidebar-logo small { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .nav-item { background: transparent !important; color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .nav-item:hover { background: rgba(var(--accent-rgb),0.16) !important; color: var(--accent-dark) !important; border-color: rgba(var(--accent-rgb),0.22) !important; transform: translateX(0.25rem); }
[data-theme="light"] .nav-item:hover i { color: var(--accent-dark) !important; transform: scale(1.12); }
[data-theme="light"] .nav-item.active { background: var(--accent) !important; color: #fff !important; border-color: transparent !important; border-radius:0.5rem !important; }
[data-theme="light"] .nav-item.active i { color: #fff !important; }
[data-theme="light"] .nav-item.active .sidebar-text { color: #fff !important; }
[data-theme="light"] .nav-item.active .nav-badge { background: rgba(255,255,255,0.25) !important; color: #fff !important; border-color: rgba(255,255,255,0.3) !important; }
[data-theme="light"] .nav-item.active .nav-group-chevron { color: #fff !important; }
[data-theme="light"] .main-container { color: #1f2937 !important; }
[data-theme="light"] .win-topbar {
    background: rgba(255,255,255,0.82) !important;
    border: 0.0625rem solid rgba(0,0,0,0.08) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .sidebar-toggle { background: rgba(0,0,0,0.05) !important; color: #1f2937 !important; }
[data-theme="light"] .sidebar-toggle:hover { background: rgba(var(--accent-rgb),0.15) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .page-title p { color: rgba(0,0,0,0.5) !important; }
/* ===== Botones primarios: base azul y hover ámbar (ambos temas) ===== */
.btn-win-primary {
    background: linear-gradient(135deg, var(--accent), var(--accent-light)) !important;
    border: none !important;
    color: #ffffff !important;
}
.btn-win-primary:hover {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    border: none !important;
    color: #ffffff !important;
    transform: translateY(-0.0625rem);
}
[data-theme="light"] .btn-win {
    background: rgba(0,0,0,0.04) !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .btn-win-primary {
    background: linear-gradient(135deg, var(--accent-dark), var(--accent)) !important;
    border: none !important;
    color: #ffffff !important;
}
[data-theme="light"] .btn-win-primary:hover {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    border: none !important;
    color: #ffffff !important;
    transform: translateY(-0.0625rem);
}
[data-theme="light"] .btn-win:not(.btn-win-primary):hover { background: rgba(var(--accent-rgb),0.12) !important; border-color: var(--accent-dark) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .btn-win-sm {
    background: rgba(0,0,0,0.04) !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .btn-win-sm:not(.btn-win-primary):hover { background: rgba(var(--accent-rgb),0.12) !important; border-color: var(--accent-dark) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .stat-card { color: #1f2937 !important; }
[data-theme="light"] .stat-card h6 { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .stat-card h3 { color: #111827 !important; }
[data-theme="light"] .stat-card .stat-icon { background: rgba(0,0,0,0.05) !important; }
[data-theme="light"] .nav-tabs-modern .nav-link {
    background: rgba(0,0,0,0.04) !important;
    color: rgba(0,0,0,0.7) !important;
    border: 0.0625rem solid rgba(0,0,0,0.06) !important;
}
[data-theme="light"] .nav-tabs-modern .nav-link.active { background: rgba(var(--accent-rgb),0.18) !important; color: var(--accent-dark) !important; border-color: var(--accent-dark) !important; }
[data-theme="light"] .nav-tabs-modern .nav-link:hover { background: rgba(var(--accent-rgb),0.15) !important; color: var(--accent-dark) !important; border-color: rgba(var(--accent-rgb),0.3) !important; }
[data-theme="light"] label, [data-theme="light"] .form-label, [data-theme="light"] .text-muted, [data-theme="light"] small, [data-theme="light"] .small { color: rgba(0,0,0,0.75) !important; }
[data-theme="light"] .text-muted i { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .form-control-custom, [data-theme="light"] .form-select {
    background-color: rgba(255,255,255,0.92) !important;
    border: 0.0625rem solid rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .form-control, [data-theme="light"] .form-control-sm, [data-theme="light"] .form-control-lg {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] option { background: #ffffff !important; color: #1f2937 !important; }

/* ===== INPUT GROUP / BUSCADORES (claro) ===== */
[data-theme="light"] .input-group-text {
    background: #e9eef5 !important;
    border: 0.0625rem solid rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .input-group-text.bg-dark {
    background-color: #e9eef5 !important;
    border-color: rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .input-group-text.text-white,
[data-theme="light"] .input-group-text.text-info,
[data-theme="light"] .input-group-text.text-nocturno { color: #1f2937 !important; }
[data-theme="light"] .input-group .form-control,
[data-theme="light"] .input-group .form-select {
    background-color: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .input-group .btn { border-color: rgba(0,0,0,0.18) !important; }
[data-theme="light"] .search-box input { background: #ffffff !important; border: 0.0625rem solid rgba(0,0,0,0.18) !important; color: #1f2937 !important; }
[data-theme="light"] .search-box input::placeholder { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .table-custom { color: #1f2937 !important; }
[data-theme="light"] .table-custom th, [data-theme="light"] .table-custom td { border-bottom: 0.0625rem solid rgba(0,0,0,0.08) !important; color: #1f2937 !important; }
[data-theme="light"] .table-custom th { color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .table-custom thead tr th { background: rgba(240,242,247,0.96) !important; border-bottom: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .table-custom tfoot td { background: rgba(240,242,247,0.96) !important; border-top: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .table-custom tr:hover td { background: rgba(var(--accent-rgb),0.07) !important; }
[data-theme="light"] table.dataTable tbody tr.odd td { background-color: rgba(0,0,0,0.015) !important; color: #1f2937 !important; }
[data-theme="light"] table.dataTable tbody tr.even td { background-color: rgba(0,0,0,0.045) !important; color: #1f2937 !important; }
[data-theme="light"] .data-table-wrapper { border: 0.0625rem solid rgba(0,0,0,0.08) !important; background: rgba(255,255,255,0.55) !important; }
[data-theme="light"] .badge-borrador { background: rgba(245,158,11,0.16) !important; border: 0.0625rem solid #f59e0b !important; color: #b45309 !important; }
[data-theme="light"] .badge-contabilizado { background: rgba(16,185,129,0.16) !important; border: 0.0625rem solid #10b981 !important; color: #047857 !important; }
[data-theme="light"] .dataTables_wrapper { color: #1f2937 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_length,
[data-theme="light"] .dataTables_wrapper .dataTables_filter,
[data-theme="light"] .dataTables_wrapper .dataTables_info,
[data-theme="light"] .dataTables_wrapper .dataTables_processing { color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button {
    background: rgba(255,255,255,0.92) !important;
    border: 0.0625rem solid rgba(0,0,0,0.15) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(var(--accent-rgb),0.2) !important; border-color: var(--accent-dark) !important; color: #111827 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-right,
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-left {
    font-size:0.8rem !important; color: var(--accent-dark) !important;
}
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-right,
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-left { color: #ffffff !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_length select,
[data-theme="light"] .dataTables_wrapper .dataTables_filter input {
    background: rgba(255,255,255,0.92) !important;
    border: 0.0625rem solid rgba(0,0,0,0.15) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .footer-card { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .footer-card hr { border-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .footer-card small { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .date-badge { background: rgba(0,0,0,0.05) !important; color: #1f2937 !important; }

[data-theme="light"] .alert { border: 0.0625rem solid rgba(0,0,0,0.08) !important; }
[data-theme="light"] .alert-success { background: #d1fae5 !important; border: 0.0625rem solid #10b981 !important; color: #065f46 !important; }
[data-theme="light"] .alert-info { background: #dbeafe !important; border: 0.0625rem solid var(--accent) !important; color: #1e40af !important; }
[data-theme="light"] .alert-warning { background: #fef3c7 !important; border: 0.0625rem solid #f59e0b !important; color: #92400e !important; }
[data-theme="light"] .alert-danger { background: #fee2e2 !important; border: 0.0625rem solid #ef4444 !important; color: #991b1b !important; }
[data-theme="light"] .search-box input { background: rgba(255,255,255,0.92) !important; border: 0.0625rem solid rgba(0,0,0,0.15) !important; color: #1f2937 !important; }
[data-theme="light"] .search-box input::placeholder { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .search-box i { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] ::-webkit-scrollbar-track { background: rgba(0,0,0,0.06) !important; }
[data-theme="light"] ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2) !important; }
[data-theme="light"] ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.3) !important; }
[data-theme="light"] .edit-input { background: rgba(0,0,0,0.04) !important; border: 0.0625rem solid rgba(var(--blue-soft-rgb),0.5) !important; color: #1f2937 !important; }
[data-theme="light"] .dias-input, [data-theme="light"] .selected-worker-card .dias-input { background: rgba(255,255,255,0.95) !important; color: #1f2937 !important; }
[data-theme="light"] .worker-detail { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .worker-name { color: #111827 !important; }

/* ===== MODALES (claro) ===== */
[data-theme="light"] .modal-content, [data-theme="light"] .modal-content-modern {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .modal-header { border-bottom: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .modal-footer { border-top: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .modal-content .modal-title, [data-theme="light"] .modal-content-modern .modal-title { color: #111827 !important; }
[data-theme="light"] .modal-content *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .modal-content-modern *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #1f2937 !important;
}
/* ===== Botón de cerrar (X) con hover en ambos temas ===== */
.btn-close, .btn-close-white {
    box-sizing: content-box;
    width:1em;
    height:1em;
    color: #000000;
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    border: 0;
    filter: invert(1);
    opacity: 0.85;
    border-radius: 50%;
    transition: transform 0.2s ease, opacity 0.2s ease, background-color 0.2s ease;
}
.btn-close:hover, .btn-close-white:hover {
    opacity: 1;
    transform: rotate(90deg) scale(1.1);
    background-color: rgba(255,255,255,0.15);
}
.swal2-close { border-radius: 50%; transition: transform 0.2s ease, background-color 0.2s ease; }
.swal2-close:hover { transform: rotate(90deg) scale(1.1); background-color: rgba(0,0,0,0.1); }
[data-theme="light"] .btn-close-white { filter: none !important; opacity: 0.65 !important; color: #1f2937 !important; background-color: transparent; }
[data-theme="light"] .modal .btn-close,
[data-theme="light"] .modal-content .btn-close,
[data-theme="light"] .modal-content-modern .btn-close,
[data-theme="light"] .btn-close { filter: none !important; opacity: 0.7 !important; color: #1f2937 !important; background-color: transparent; }
[data-theme="light"] .modal .btn-close:hover,
[data-theme="light"] .modal-content .btn-close:hover,
[data-theme="light"] .modal-content-modern .btn-close:hover,
[data-theme="light"] .btn-close:hover { opacity: 1 !important; transform: rotate(90deg) scale(1.1); background-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .swal2-close { color: #1f2937 !important; }
[data-theme="light"] .swal2-close:hover { background-color: rgba(0,0,0,0.08); }
/* ===== Botón de cerrar personalizado (X) visible en tema claro ===== */
[data-theme="light"] .btn-close-custom {
    border-color: rgba(0,0,0,0.5) !important;
    color: #1f2937 !important;
    background: transparent !important;
    opacity: 0.9;
}
[data-theme="light"] .btn-close-custom:hover {
    background-color: #ef4444 !important;
    border-color: #ef4444 !important;
    color: #ffffff !important;
    opacity: 1;
}

/* ===== SWEETALERT2: usa variables de tema (anula los estilos fijos de Swal.fire) ===== */
.swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
[data-theme="light"] .swal2-popup { background: #ffffff !important; color: #1f2937 !important; }
[data-theme="light"] .swal2-title { color: #111827 !important; }

/* ===== Modales propios de módulos (claro) ===== */
[data-theme="light"] .modal-content-win,
[data-theme="light"] .modal-header-win,
[data-theme="light"] .modal-footer-win { color: #1f2937 !important; }
[data-theme="light"] .tipo-nomina-preview,
[data-theme="light"] .tipo-nomina-dropdown,
[data-theme="light"] .tipo-nomina-option { color: #1f2937 !important; }
[data-theme="light"] .card-option-descuento { color: #1f2937 !important; border-color: rgba(0,0,0,0.15) !important; }
[data-theme="light"] .worker-list-container, [data-theme="light"] .bono-list-container {
    background: rgba(0,0,0,0.03) !important; border: 0.0625rem solid rgba(0,0,0,0.08) !important;
}
[data-theme="light"] .worker-item { border-bottom: 0.0625rem solid rgba(0,0,0,0.06) !important; }
[data-theme="light"] .worker-item:hover { background: rgba(var(--accent-rgb),0.08) !important; }
[data-theme="light"] .worker-item.selected { background: rgba(var(--accent-rgb),0.14) !important; border-left: 0.1875rem solid var(--accent-dark) !important; }
[data-theme="light"] .worker-avatar { background: rgba(var(--accent-rgb),0.12) !important; color: var(--accent-dark) !important; }

/* ===== DROPDOWNS (claro) ===== */
[data-theme="light"] .dropdown-menu-win {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.15) !important;
}
[data-theme="light"] .dropdown-menu-win .dropdown-item { color: #1f2937 !important; transition: all 0.15s ease; }
[data-theme="light"] .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb),0.22) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239,68,68,0.12) !important; color: #b91c1c !important; }
[data-theme="light"] .dropdown-divider { border-top: 0.0625rem solid rgba(0,0,0,0.18) !important; border-color: rgba(0,0,0,0.18) !important; }
[data-theme="light"] .dropdown-menu-win .dropdown-divider { border-top: 0.0625rem solid rgba(0,0,0,0.18) !important; border-color: rgba(0,0,0,0.18) !important; }
[data-theme="light"] .dropdown-menu-win .dropdown-item-text { color: #1f2937 !important; }
[data-theme="light"] .dropdown-menu-win .dropdown-item-text small { color: #2563eb !important; }

/* ===== SWEETALERT (claro) ===== */
[data-theme="light"] .swal2-popup {
    background: #ffffff !important;
    color: #1f2937 !important;
    border: 0.0625rem solid rgba(0,0,0,0.1) !important;
}
[data-theme="light"] .swal2-popup *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.swal2-icon):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #1f2937 !important;
}
[data-theme="light"] .swal2-input, [data-theme="light"] .swal2-textarea {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.2) !important;
    color: #1f2937 !important;
}

/* ===== COLVIS DATATABLES (claro) ===== */
[data-theme="light"] .dt-button-collection {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.15) !important;
}
[data-theme="light"] .dt-button-collection .dt-button { color: #1f2937 !important; }
[data-theme="light"] .dt-button-collection .dt-button:hover { background: rgba(var(--accent-rgb),0.1) !important; color: #111827 !important; }
[data-theme="light"] .dt-button-collection .dt-button.active { background: rgba(var(--accent-rgb),0.18) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .dt-button-collection .dt-button-collection-title { color: rgba(0,0,0,0.5) !important; border-bottom: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .buttons-colvis { background: rgba(0,0,0,0.04) !important; border: 0.0625rem solid rgba(0,0,0,0.12) !important; color: #1f2937 !important; }
[data-theme="light"] .buttons-colvis:hover { background: rgba(var(--accent-rgb),0.12) !important; border-color: var(--accent-dark) !important; color: var(--accent-dark) !important; }

/* ===== CARDS / CONTENEDORES ===== */
[data-theme="light"] .card { background: #ffffff !important; color: #1f2937 !important; border: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .card-header { background: rgba(0,0,0,0.03) !important; border-bottom: 0.0625rem solid rgba(0,0,0,0.08) !important; color: #111827 !important; }
/* ===== TÍTULOS DE TARJETAS / PANELES (claro): más oscuros para distinguirlos ===== */
[data-theme="light"] .card-title:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header-custom,
[data-theme="light"] .card-header h1:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header h2:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header h3:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header h4:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header h5:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white),
[data-theme="light"] .card-header h6:not(.text-success):not(.text-danger):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white):not(.card-title):not(.card-collapse-title):not(.modal-title):not(.win-card-title):not(.card-header-title) { color: #111827 !important; }
[data-theme="light"] .card-body { color: #1f2937 !important; }
[data-theme="light"] .stat-card { background: #ffffff !important; color: #1f2937 !important; border: 0.0625rem solid rgba(0,0,0,0.06) !important; }
[data-theme="light"] .stat-card h3 { color: #111827 !important; }
[data-theme="light"] .preview-card { color: #1f2937 !important; }
[data-theme="light"] .info-row { color: #1f2937 !important; border-bottom: 0.0625rem solid rgba(0,0,0,0.06) !important; }
[data-theme="light"] .selected-worker-card { color: #1f2937 !important; border: 0.0625rem solid rgba(var(--accent-rgb),0.25) !important; }
[data-theme="light"] .selected-worker-card .worker-name { color: var(--accent-dark) !important; }
[data-theme="light"] .selected-worker-card .form-control { background: rgba(255,255,255,0.9) !important; border: 0.0625rem solid rgba(var(--blue-soft-rgb),0.5) !important; color: #1f2937 !important; }
[data-theme="light"] .selected-worker-card .text-muted,
[data-theme="light"] .selected-worker-card span:not(.badge):not(.importe-preview) { color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .importe-preview { color: #047857 !important; }
[data-theme="light"] .rango-dias-selector { background: rgba(0,0,0,0.03) !important; }
[data-theme="light"] .rango-btn { background: rgba(0,0,0,0.04) !important; border: 0.0625rem solid rgba(0,0,0,0.1) !important; color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .rango-btn:hover { background: rgba(var(--accent-rgb),0.12) !important; border-color: var(--accent-dark) !important; color: #111827 !important; }
[data-theme="light"] .rango-btn.active { background: rgba(var(--accent-rgb),0.2) !important; border-color: var(--accent-dark) !important; color: #111827 !important; }
[data-theme="light"] .btn-agregar-mas { background: rgba(16,185,129,0.14) !important; border: 0.0625rem solid #10b981 !important; color: #047857 !important; }
[data-theme="light"] .dias-num { color: #111827 !important; }

/* ===== CAPTURA GLOBAL: TEXTO BLANCO -> OSCURO EN SUPERFICIES CLARAS ===== */
[data-theme="light"] .card:not([class*="bg-primary"]):not([class*="bg-dark"]):not([class*="bg-secondary"]):not([class*="bg-info"]):not([class*="bg-success"]):not([class*="bg-danger"]):not([class*="bg-gradient"]) *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .glass-card *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .stat-card *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .preview-card *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .info-row *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .selected-worker-card *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .modal-content-modern *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .swal2-popup *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .dropdown-menu-win *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .sidebar-profile *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title):not(.card-option.selected *),
[data-theme="light"] .win-sidebar .profile-info *:not(i):not(button):not(.btn):not(.btn-close):not(.btn-close-white):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #1f2937 !important;
}

/* ===== TABLAS (claro) ===== */
[data-theme="light"] .table-dark,
[data-theme="light"] .table-dark > :not(caption) > * > *,
[data-theme="light"] .table-dark th,
[data-theme="light"] .table-dark td {
    --bs-table-bg:#ffffff;
    --bs-table-color:#1f2937;
    --bs-table-border-color:rgba(0,0,0,0.1);
    --bs-table-striped-bg:rgba(0,0,0,0.03);
    --bs-table-striped-color:#1f2937;
    --bs-table-active-bg:rgba(var(--accent-rgb),0.12);
    --bs-table-active-color:#111827;
    --bs-table-hover-bg:rgba(var(--accent-rgb),0.08);
    --bs-table-hover-color:#111827;
    background-color: #ffffff !important;
    color: #1f2937 !important;
    border-color: rgba(0,0,0,0.1) !important;
}
[data-theme="light"] .table > :not(caption) > * > * {
    box-shadow: inset 0 0 0 624.9375rem var(--bs-table-bg-state, var(--bs-table-bg-type, var(--bs-table-bg))) !important;
}
[data-theme="light"] .table-dark thead th,
[data-theme="light"] .table-dark thead td {
    background-color: #f1f3f7 !important;
    color: #111827 !important;
    border-color: rgba(0,0,0,0.1) !important;
}
[data-theme="light"] .table-dark.table-striped > tbody > tr:nth-of-type(odd) > * {
    --bs-table-accent-bg:rgba(0,0,0,0.03);
    background-color: rgba(0,0,0,0.03) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .table-dark tbody tr:hover > *,
[data-theme="light"] .table-dark tbody tr:hover td {
    --bs-table-accent-bg:rgba(var(--accent-rgb),0.08);
    background-color: rgba(var(--accent-rgb),0.08) !important;
    color: #111827 !important;
}
[data-theme="light"] .table > :not(caption) > * > * { color: #1f2937 !important; }
[data-theme="light"] .table thead th { color: #111827 !important; }
[data-theme="light"] .table-striped { --bs-table-striped-bg:rgba(0,0,0,0.03) !important; --bs-table-striped-color:#1f2937 !important; }
[data-theme="light"] .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg-type:var(--bs-table-striped-bg) !important; --bs-table-color-type:var(--bs-table-striped-color) !important; }

/* Separadores / hr (claro) */
[data-theme="light"] hr { border-top: 0.0625rem solid rgba(0,0,0,0.15) !important; opacity: 1 !important; }
[data-theme="light"] .dropdown-divider { border-top: 0.0625rem solid rgba(0,0,0,0.18) !important; opacity: 1 !important; }

/* DataTables: fondo del wrapper, inputs y paginación en claro */
[data-theme="light"] .dataTables_wrapper { color: #1f2937 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_length select,
[data-theme="light"] .dataTables_wrapper .dataTables_filter input { border: 0.0625rem solid rgba(0,0,0,0.2) !important; background-color: #ffffff !important; color: #1f2937 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button { color: #1f2937 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { border: 0.0625rem solid rgba(0,0,0,0.15) !important; background: rgba(0,0,0,0.04) !important; color: #111827 !important; }
[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { border: 0.0625rem solid var(--accent-dark) !important; background: rgba(var(--accent-rgb),0.1) !important; color: #111827 !important; }

/* ===== Tablas de dashboard (colores inline sueltos) en claro ===== */
[data-theme="light"] [style*="color: #e2e8f0"],
[data-theme="light"] [style*="color:#e2e8f0"] { color: #1f2937 !important; }
[data-theme="light"] [style*="color: #94a3b8"],
[data-theme="light"] [style*="color:#94a3b8"] { color: #4b5563 !important; }
[data-theme="light"] [style*="rgba(255,255,255,0.3)"] { color: #6b7280 !important; }
[data-theme="light"] [style*="rgba(255,255,255,0.6)"] { color: #4b5563 !important; }
[data-theme="light"] [style*="border-top: 0.125rem solid rgba(255,255,255,0.1)"],
[data-theme="light"] tfoot [style*="rgba(255,255,255,0.1)"] { border-top-color: rgba(0,0,0,0.15) !important; }
[data-theme="light"] tfoot [style*="rgba(255,255,255,0.1)"] td { color: #111827 !important; }

/* DataTables: forzar stripe claro (pinta con box-shadow) en tema claro */
[data-theme="light"] table.dataTable.stripe > tbody > tr.odd > *,
[data-theme="light"] table.dataTable.display > tbody > tr.odd > * { box-shadow: inset 0 0 0 624.9375rem rgba(0,0,0,0.035) !important; }
[data-theme="light"] table.dataTable.hover > tbody > tr:hover > *,
[data-theme="light"] table.dataTable.display > tbody > tr:hover > * { box-shadow: inset 0 0 0 624.9375rem rgba(var(--accent-rgb),0.08) !important; }
[data-theme="light"] table.dataTable tbody tr.selected > * { box-shadow: inset 0 0 0 624.9375rem rgba(var(--accent-rgb),0.18) !important; color: #111827 !important; }

/* ===== Títulos de cards y modales: color de acento + hover (ambos temas) ===== */
.card-title, .modal-title, .win-card-title, .card-collapse-title, .card-header-title {
    color: var(--blue);
    font-weight: 700;
    transition: color 0.2s ease;
}
.card-title:hover, .modal-title:hover, .win-card-title:hover, .card-collapse-title:hover, .card-header-title:hover { color: var(--blue-soft); }

/* ===== BARRA DE TÍTULO DE CARDS: color según el tema (discreta) ===== */
.glass-card > .p-3.border-bottom,
.card-header,
.win-card-header {
    background: linear-gradient(180deg, rgba(var(--accent-rgb),0.30) 0%, rgba(var(--accent-rgb),0.08) 100%);
    border-bottom: 0.0625rem solid rgba(var(--accent-rgb),0.35) !important;
}
.glass-card > .p-3.border-bottom .card-collapse-title,
.glass-card > .p-3.border-bottom .card-header-title,
.card-header .card-title,
.card-header h5, .card-header h6,
.win-card-header h5, .win-card-header h6 { color: var(--violet-soft); }
[data-theme="light"] .glass-card > .p-3.border-bottom,
[data-theme="light"] .card-header,
[data-theme="light"] .win-card-header {
    background: linear-gradient(180deg, rgba(var(--accent-rgb),0.26) 0%, rgba(var(--accent-rgb),0.10) 100%);
    border-bottom: 0.0625rem solid rgba(var(--accent-rgb),0.45) !important;
}
[data-theme="light"] .glass-card > .p-3.border-bottom .card-collapse-title,
[data-theme="light"] .glass-card > .p-3.border-bottom .card-header-title { color: var(--accent-dark) !important; }

/* ===== TABLAS `.table-dark` ADAPTATIVAS AL TEMA ===== */
.table-dark {
    --bs-table-color:var(--txt);
    --bs-table-bg:#161b26;
    --bs-table-striped-bg:rgba(255,255,255,0.04);
    --bs-table-border-color:rgba(255,255,255,0.09);
    border-color: rgba(255,255,255,0.09);
    color: var(--txt);
}
[data-theme="light"] .table-dark {
    --bs-table-color:var(--txt);
    --bs-table-bg:#ffffff;
    --bs-table-striped-bg:rgba(0,0,0,0.03);
    --bs-table-border-color:rgba(0,0,0,0.10);
    border-color: rgba(0,0,0,0.10);
    color: var(--txt);
}
[data-theme="light"] .table-dark tbody td { color: var(--txt) !important; }
[data-theme="light"] .table-dark tbody td.text-muted { color: var(--muted) !important; }
.table-dark thead th { background: rgba(var(--accent-rgb),0.14); }
[data-theme="light"] .table-dark thead th { background: rgba(var(--accent-rgb),0.10); }

/* ===== BADGE DE SECCIÓN EN BARRAS DE TARJETAS ===== */
.badge-win { background: #4f46e5; color: #ffffff; }
[data-theme="light"] .badge-win { background: #0891b2; color: #ffffff; }

/* ===== Títulos de cards y modales diferenciados del cuerpo (claro) ===== */
[data-theme="light"] .card-title,
[data-theme="light"] .modal-title,
[data-theme="light"] .win-card-title,
[data-theme="light"] h6.fw-semibold,
[data-theme="light"] h5.fw-semibold,
[data-theme="light"] .card-header-title { color: var(--accent-dark) !important; font-weight: 700 !important; transition: color 0.2s ease; }
[data-theme="light"] .card-title:hover,
[data-theme="light"] .modal-title:hover,
[data-theme="light"] .win-card-title:hover,
[data-theme="light"] h6.fw-semibold:hover,
[data-theme="light"] h5.fw-semibold:hover,
[data-theme="light"] .card-header-title:hover { color: #1d4ed8 !important; }
[data-theme="light"] .card-header { background: rgba(var(--accent-rgb),0.06) !important; border-bottom: 0.0625rem solid rgba(0,0,0,0.1) !important; color: var(--accent-dark) !important; transition: background-color 0.2s ease; }
[data-theme="light"] .card-header:hover { background: rgba(var(--accent-rgb),0.1) !important; }
[data-theme="light"] .stat-label,
[data-theme="light"] .card-subtitle { color: #4b5563 !important; }

/* Nombre completo en el menú de usuario (dentro de .fw-bold) */
[data-theme="light"] .dropdown-menu-win .dropdown-item-text .fw-bold { color: #111827 !important; }

/* ===== TOOLTIPS (legibles en modo claro) ===== */
[data-theme="light"] .tooltip .tooltip-inner { background: #1f2937 !important; color: #ffffff !important; }
[data-theme="light"] .tooltip .tooltip-arrow::before { border-top-color: #1f2937 !important; border-bottom-color: #1f2937 !important; border-left-color: #1f2937 !important; border-right-color: #1f2937 !important; }

/* ===== DROPDOWNS (base Bootstrap) ===== */
[data-theme="light"] .dropdown-menu { background: #ffffff !important; color: #1f2937 !important; border: 0.0625rem solid rgba(0,0,0,0.12) !important; }
[data-theme="light"] .dropdown-item { color: #1f2937 !important; }
[data-theme="light"] .dropdown-item:hover { background: rgba(var(--accent-rgb),0.22) !important; color: #111827 !important; }
[data-theme="light"] .dropdown-item-text { color: #1f2937 !important; }

/* ===== DROPDOWN EXPORT PERSONALIZADO (bandecnom.php) ===== */
[data-theme="light"] .btn-export-dropdown {
    background: rgba(0,0,0,0.04) !important;
    border: 0.0625rem solid rgba(0,0,0,0.15) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .btn-export-dropdown:hover {
    background: rgba(var(--accent-rgb),0.12) !important;
    border-color: var(--accent-dark) !important;
    color: #111827 !important;
}
[data-theme="light"] .dropdown-export-menu {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.12) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.18) !important;
}
[data-theme="light"] .dropdown-export-item { color: #1f2937 !important; }
[data-theme="light"] .dropdown-export-item:hover { background: rgba(var(--accent-rgb),0.22) !important; color: #111827 !important; }
[data-theme="light"] .dropdown-export-item { border-top: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .dropdown-export-item:first-child { border-top: none !important; }

/* ===== COMBOS (dark-select) bandecnom.php ===== */
[data-theme="light"] .dark-select {
    background-color: #ffffff !important;
    border: 0.0625rem solid rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.875rem center !important;
    background-size: 0.875rem 0.75rem !important;
    padding-right:2.5rem !important;
}
[data-theme="light"] .dark-select:hover { border-color: var(--accent-dark) !important; background-color: #ffffff !important; }
[data-theme="light"] .dark-select:focus { border-color: var(--accent-dark) !important; box-shadow: 0 0 0 0.1875rem rgba(var(--accent-rgb),0.2) !important; }
[data-theme="light"] .dark-select option { background: #ffffff !important; color: #1f2937 !important; }
[data-theme="light"] .dark-select::placeholder { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .filter-label { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .filter-label i { color: var(--accent-dark) !important; }
[data-theme="light"] input::placeholder,
[data-theme="light"] textarea::placeholder { color: rgba(0,0,0,0.4) !important; }
/* ===== ICONOS DE FONDO EN CARDS (tema claro) ===== */
[data-theme="light"] .glass-card .card-icon-bg {
    opacity: 0.15 !important;
}
[data-theme="light"] .glass-card .card-icon-bg.address { color: #2563eb !important; }
[data-theme="light"] .glass-card .card-icon-bg.register { color: #db2777 !important; }
[data-theme="light"] .glass-card .card-icon-bg.birthday { color: #d97706 !important; }
[data-theme="light"] .glass-card .card-icon-bg.cake { color: #059669 !important; }

[data-theme="light"] .info-item .card-icon-bg {
    opacity: 0.15 !important;
}
[data-theme="light"] .info-item .card-icon-bg.email { color: #7c3aed !important; }
[data-theme="light"] .info-item .card-icon-bg.phone { color: #0891b2 !important; }
[data-theme="light"] .info-item .card-icon-bg.ci { color: #d97706 !important; }
[data-theme="light"] .info-item .card-icon-bg.role { color: #dc2626 !important; }

/* Para el tema oscuro */
[data-theme="dark"] .glass-card .card-icon-bg {
    opacity: 0.08 !important;
}
[data-theme="dark"] .info-item .card-icon-bg {
    opacity: 0.06 !important;
}
/* ===== SELECTOR DE NÓMINA (nominas.php) ===== */
[data-theme="light"] .tipo-nomina-preview {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(var(--accent-rgb),0.35) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .tipo-nomina-preview:hover {
    border-color: var(--accent-dark) !important;
    background: #f3f6fb !important;
}
[data-theme="light"] .tipo-nomina-preview i:last-child { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .tipo-nomina-dropdown {
    background: #ffffff !important;
    border: 0.0625rem solid rgba(var(--accent-rgb),0.25) !important;
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important;
}
[data-theme="light"] .tipo-nomina-option { color: #1f2937 !important; }
[data-theme="light"] .tipo-nomina-option:hover { background: rgba(var(--accent-rgb),0.1) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .tipo-nomina-select option { background: #ffffff !important; color: #1f2937 !important; }

/* ===== CHECKBOXES / RADIOS ===== */
[data-theme="light"] .form-check-input {
    background-color: #ffffff !important;
    border-color: rgba(0,0,0,0.3) !important;
    accent-color: var(--accent-dark) !important;
}
[data-theme="light"] .form-check-input:checked {
    background-color: var(--accent-dark) !important;
    border-color: var(--accent-dark) !important;
}
[data-theme="light"] .form-check-label { color: #1f2937 !important; }
[data-theme="light"] .card-checkbox { border-top: 0.0625rem solid rgba(0,0,0,0.1) !important; }
[data-theme="light"] .card-checkbox label { color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .card-checkbox label:hover { color: var(--accent-dark) !important; }

/* ===== SIDEBAR TEXTOS ===== */
[data-theme="light"] .win-sidebar { color: #1f2937 !important; }
[data-theme="light"] .win-sidebar .nav-item { color: rgba(0,0,0,0.7) !important; }
[data-theme="light"] .win-sidebar .nav-item:hover { color: var(--accent-dark) !important; }
[data-theme="light"] .win-sidebar .nav-item.active { color: #fff !important; }
[data-theme="light"] .win-sidebar .nav-item span { color: inherit !important; }
[data-theme="light"] .sidebar-nav { color: #1f2937 !important; }
[data-theme="light"] .nav-category { color: rgba(0,0,0,0.45) !important; }
[data-theme="light"] .nav-group-chevron { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .nav-badge { background: rgba(var(--accent-rgb),0.15) !important; border: 0.0625rem solid rgba(var(--accent-rgb),0.3) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .sidebar-logo .sidebar-text { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .page-title h1 { color: #111827 !important; }
[data-theme="light"] .page-title p { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .swal2-popup hr { border-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .modal-content-modern hr { border-color: rgba(0,0,0,0.1) !important; }

/* ===== TEXT-MUTED ===== */
[data-theme="light"] .text-muted { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .text-white-50 { color: rgba(0,0,0,0.5) !important; }

/* ===== ICONOS FONTAWESOME (heredan el color del tema, excepto los semanticos) ===== */
[data-theme="light"] i.fas:not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white):not(.card-option.selected *),
[data-theme="light"] i.far:not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white):not(.card-option.selected *),
[data-theme="light"] i.fab:not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.text-white):not(.card-option.selected *) { color: inherit !important; }

/* ===== CARETS / CHEVRONS (carets de dropdowns y listas colapsables) ===== */
/* El caret de Bootstrap (.dropdown-toggle::after) es un triangulo CSS que hereda currentColor.
   Por defecto lo ponemos oscuro en light; lo dejamos blanco solo en botones de fondo solido de color. */
[data-theme="light"] .dropdown-toggle::after { border-top-color: rgba(0, 0, 0, 0.55) !important; }
[data-theme="light"] .dropup .dropdown-toggle::after { border-bottom-color: rgba(0, 0, 0, 0.55) !important; border-top-color: transparent !important; }
[data-theme="light"] .dropdown-toggle-split::before { border-right-color: rgba(0, 0, 0, 0.55) !important; }
[data-theme="light"] .btn-win-primary.dropdown-toggle::after,
[data-theme="light"] .btn-primary-glass.dropdown-toggle::after,
[data-theme="light"] .btn-success-glass.dropdown-toggle::after { border-top-color: rgba(255, 255, 255, 0.9) !important; }
/* Chevrons de paneles/listas colapsables (configuracion.php, empleados.php, etc.) */
[data-theme="light"] i.fas.collapse-chevron,
[data-theme="light"] i.fas.card-collapse-chevron { color: rgba(0, 0, 0, 0.5) !important; }

/* ===== VENTANA PERSONALIZADA "NO SE PUEDE CONTABILIZAR" (nominas.php) ===== */
/* Tema oscuro (por defecto) */
.ventana-cuadre-overlay {
    position: fixed;
    inset:0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.6);
    font-family: 'Segoe UI Variable', 'Segoe UI', Tahoma, Verdana, sans-serif;
}
.ventana-cuadre-win {
    width:53.75rem;
    max-width:95vw;
    max-height:90vh;
    background: #202020;
    border: 0.0625rem solid rgba(255, 255, 255, 0.08);
    border-radius: 0.5rem;
    box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ventana-cuadre-titlebar {
    display: flex;
    align-items: center;
    gap:0.625rem;
    height:2rem;
    padding:0 0.5rem 0 0.75rem;
    background: #2b1616;
    color: #fca5a5;
    border-bottom: 0.0625rem solid rgba(248, 113, 113, 0.35);
    flex-shrink: 0;
}
.ventana-cuadre-titlebar-icon { color: #f87171; font-size:0.8125rem; }
.ventana-cuadre-title {
    font-weight: 600;
    font-size:0.75rem;
    flex: 1;
    letter-spacing:0.0125rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ventana-cuadre-close {
    border: none;
    background: transparent;
    color: #fca5a5;
    font-size:0.875rem;
    line-height:1;
    cursor: pointer;
    width:2.875rem;
    height:2rem;
    border-radius: 0;
}
.ventana-cuadre-close:hover { background: #C42B1C; color: #fff; }
.ventana-cuadre-body {
    padding:1rem 1.25rem;
    overflow-y: auto;
    background: #202020;
    color: #e6e6e6;
    font-size:0.8125rem;
}
.ventana-cuadre-danger { color: #f87171; }
.ventana-cuadre-list {
    max-height:13.75rem;
    overflow-y: auto;
    border: 0.0625rem solid rgba(248, 113, 113, 0.45);
    border-radius: 0.5rem;
    padding:0.5rem 0.75rem;
    background: rgba(248, 113, 113, 0.16);
}
.ventana-cuadre-list ul { margin:0; font-size:0.78rem; padding-left:1.125rem; }
.ventana-cuadre-list-note {
    margin-top:0.5rem;
    margin-bottom:0;
    font-size:0.75rem;
    color: #fbbf24;
}
.ventana-cuadre-warn {
    padding:0.5rem 0.625rem;
    margin-top:0.5rem;
    border-radius: 0.5rem;
    background: rgba(251, 191, 36, 0.1);
    border: 0.0625rem solid rgba(251, 191, 36, 0.3);
    text-align: left;
}
.ventana-cuadre-warn i { color: #fbbf24; }
.ventana-cuadre-warn span { color: #fcd34d; }
.ventana-cuadre-footer {
    display: flex;
    gap:0.5rem;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    padding:0.75rem 1rem;
    background: #202020;
    border-top: 0.0625rem solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}
.ventana-cuadre-export { margin-left:auto; }
.ventana-cuadre-btn-primary {
    border: none;
    border-radius: 0.25rem;
    padding:0.4375rem 1.125rem;
    font-size:0.8125rem;
    font-weight: 600;
    cursor: pointer;
    background: #0078D4;
    color: #ffffff;
}
.ventana-cuadre-btn-primary:hover { background: #1a86da; }
.ventana-cuadre-btn-ghost {
    border: 0.0625rem solid rgba(255, 255, 255, 0.12);
    border-radius: 0.25rem;
    padding:0.4375rem 1.125rem;
    font-size:0.8125rem;
    font-weight: 600;
    cursor: pointer;
    background: #2d2d2d;
    color: #e6e6e6;
}
.ventana-cuadre-btn-ghost:hover { background: #3a3a3a; }

/* Tema claro */
[data-theme="light"] .ventana-cuadre-overlay { background: rgba(0, 0, 0, 0.45); }
[data-theme="light"] .ventana-cuadre-win {
    background: #f3f3f3;
    border: 0.0625rem solid rgba(0, 0, 0, 0.15);
    box-shadow: 0 0.875rem 2.75rem rgba(0, 0, 0, 0.25);
}
[data-theme="light"] .ventana-cuadre-titlebar { background: #fde2e2; color: #991b1b; border-bottom-color: rgba(220, 38, 38, 0.3); }
[data-theme="light"] .ventana-cuadre-titlebar-icon { color: #dc2626; }
[data-theme="light"] .ventana-cuadre-close { color: #991b1b; }
[data-theme="light"] .ventana-cuadre-close:hover { background: #C42B1C; color: #fff; }
[data-theme="light"] .ventana-cuadre-body { background: #ffffff; color: #1f2937; }
[data-theme="light"] .ventana-cuadre-danger { color: #dc2626; }
[data-theme="light"] .ventana-cuadre-list {
    border-color: #f5a3a3;
    background: #fee2e2;
}
[data-theme="light"] .ventana-cuadre-list-note { color: #b45309; }
[data-theme="light"] .ventana-cuadre-warn {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.35);
}
[data-theme="light"] .ventana-cuadre-warn i { color: #d97706; }
[data-theme="light"] .ventana-cuadre-warn span { color: #92400e; }
[data-theme="light"] .ventana-cuadre-footer { background: #f3f3f3; border-top-color: rgba(0, 0, 0, 0.1); }
[data-theme="light"] .ventana-cuadre-btn-ghost {
    background: #e2e8f0;
    color: #1f2937;
    border-color: rgba(0, 0, 0, 0.15);
}
[data-theme="light"] .ventana-cuadre-btn-ghost:hover { background: #cbd5e1; }

/* ===== ALERTA INYECTADA "REVISIÓN DE CUADRE PREVIA" (nominas.php) ===== */
.cuadre-alert-inyectado {
    background: rgba(239, 68, 68, 0.12);
    border: 0.0625rem solid rgba(239, 68, 68, 0.35);
    color: #fecaca;
    border-radius: 0.625rem;
    padding:0.75rem 1rem;
}
.cuadre-alert-inyectado .btn-close { filter: invert(1); }
.cuadre-alert-detalle { max-height:20rem; overflow-y: auto; margin-top:0.625rem; }
.cuadre-val-almacenado { color: #f87171; }
.cuadre-val-recalculado { color: #34d399; }
.cuadre-val-diferencia { color: #fbbf24; font-weight: 600; }
.cuadre-nota { font-size:0.78rem; color: #fbbf24; }

[data-theme="light"] .cuadre-alert-inyectado {
    background: #fee2e2;
    border-color: #ef4444;
    color: #991b1b;
}
[data-theme="light"] .cuadre-alert-inyectado .btn-close { filter: none; }
[data-theme="light"] .cuadre-alert-inyectado .btn-warning { color: #92400e; border-color: #f59e0b; }
[data-theme="light"] .cuadre-val-almacenado { color: #dc2626; }
[data-theme="light"] .cuadre-val-recalculado { color: #047857; }
[data-theme="light"] .cuadre-val-diferencia { color: #b45309; }
[data-theme="light"] .cuadre-nota { color: #b45309; }

/* ===== BOTONES DE EXPORTACIÓN DEL CUADRE (cuadreBotonesHtml) ===== */
.cuadre-export-group { display: flex; flex-wrap: wrap; gap:0.375rem; }
.cuadre-export-btn {
    border: 0.0625rem solid rgba(255, 255, 255, 0.2);
    border-radius: 0.4375rem;
    padding:0.375rem 0.75rem;
    font-size:0.75rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap:0.375rem;
    background: rgba(255, 255, 255, 0.06);
    color: #e6e6e6;
}
.cuadre-export-btn:hover { filter: brightness(1.15); }
.cuadre-export-btn--imprimir { border-color: rgba(96, 165, 250, 0.4); background: rgba(96, 165, 250, 0.15); color: #93c5fd; }
.cuadre-export-btn--xls { border-color: rgba(33, 163, 102, 0.4); background: rgba(33, 163, 102, 0.15); color: #6ee7b7; }
.cuadre-export-btn--pdf { border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
.cuadre-export-btn--txt { border-color: rgba(234, 179, 8, 0.4); background: rgba(234, 179, 8, 0.15); color: #fde047; }
.cuadre-export-btn--docx { border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.15); color: #93c5fd; }

[data-theme="light"] .cuadre-export-btn {
    border-color: rgba(0, 0, 0, 0.2);
    background: rgba(0, 0, 0, 0.04);
    color: #1f2937;
}
[data-theme="light"] .cuadre-export-btn:hover {
    background: rgba(0, 120, 212, 0.1);
    border-color: var(--accent-dark);
    color: var(--accent-dark);
}
[data-theme="light"] .cuadre-export-btn--imprimir { border-color: rgba(37, 99, 235, 0.35); background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }
[data-theme="light"] .cuadre-export-btn--xls { border-color: rgba(5, 150, 105, 0.35); background: rgba(5, 150, 105, 0.1); color: #047857; }
[data-theme="light"] .cuadre-export-btn--pdf { border-color: rgba(220, 38, 38, 0.35); background: rgba(220, 38, 38, 0.08); color: #b91c1c; }
[data-theme="light"] .cuadre-export-btn--txt { border-color: rgba(217, 119, 6, 0.35); background: rgba(217, 119, 6, 0.1); color: #b45309; }
[data-theme="light"] .cuadre-export-btn--docx { border-color: rgba(37, 99, 235, 0.35); background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }

/* ===== MODAL CUADRE DE NÓMINAS CONTABILIZADAS (tema claro) ===== */
[data-theme="light"] #modalCuadre #cuadreAlerta {
    color: #991b1b !important;
    background: #fee2e2 !important;
    border-color: #ef4444 !important;
}
[data-theme="light"] #modalCuadre #cuadreAlerta:has(.fa-check-circle) {
    color: #065f46 !important;
    background: #d1fae5 !important;
    border-color: #10b981 !important;
}
[data-theme="light"] #modalCuadre #cuadreNota { color: #b45309 !important; }
[data-theme="light"] .cuadre-link-id { color: #1d4ed8 !important; }
[data-theme="light"] .cuadre-link-num { color: #1d4ed8 !important; }
[data-theme="light"] .cuadre-link:hover { color: inherit !important; }

/* ===== MODALES / GLOBAL: flecha del select en tema claro ===== */
[data-theme="light"] .form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 0.9rem !important;
    padding-right:2.4rem !important;
}
/* selects con clase .form-control (p. ej. configuracion.php: mail_activo/proveedor/encryption):
   quitan la flecha nativa del navegador y usan una sola flecha SVG oscura con tamaño correcto */
[data-theme="light"] select.form-control {
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 0.9rem !important;
    padding-right:2.2rem !important;
}

/* ===== submayor_vacaciones.php (componentes con color hardcodeado) ===== */
[data-theme="light"] .stat-card-prof { background: #ffffff !important; border-color: rgba(0,0,0,0.12) !important; }
[data-theme="light"] .stat-card-prof .stat-value { color: #1f2937 !important; }
[data-theme="light"] .stat-card-prof .stat-label { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .stat-card-prof .stat-sub { color: rgba(0,0,0,0.55) !important; }
[data-theme="light"] .stat-card-prof .stat-bar { background: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .form-control-prof,
[data-theme="light"] .form-select-prof {
    background-color: #ffffff !important; border-color: rgba(0,0,0,0.18) !important; color: #1f2937 !important;
}
[data-theme="light"] .form-select-prof {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 0.875rem 0.75rem !important;
}
[data-theme="light"] .form-control-prof::placeholder,
[data-theme="light"] .form-select-prof::placeholder { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .form-control-prof:focus,
[data-theme="light"] .form-select-prof:focus { border-color: var(--accent-dark) !important; box-shadow: 0 0 0 0.1875rem rgba(var(--accent-rgb),0.15) !important; }
[data-theme="light"] input[type="date"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="light"] input[type="time"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="light"] input[type="datetime-local"].form-control-prof::-webkit-calendar-picker-indicator { filter: none !important; }
[data-theme="light"] .f-label { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .f-label i { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .badge-factor { background: rgba(var(--accent-rgb),0.12) !important; border-color: rgba(var(--accent-rgb),0.3) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .badge-pill-prof { background: rgba(0,0,0,0.04) !important; border-color: rgba(0,0,0,0.12) !important; color: #4b5563 !important; }
[data-theme="light"] .badge-9 { background: rgba(var(--accent-rgb),0.12) !important; border-color: rgba(var(--accent-rgb),0.3) !important; color: #6d28d9 !important; }
[data-theme="light"] .btn-prof { background: rgba(0,0,0,0.04) !important; border-color: rgba(0,0,0,0.12) !important; color: #1f2937 !important; }
[data-theme="light"] .btn-prof:hover { background: rgba(0,0,0,0.08) !important; border-color: rgba(0,0,0,0.2) !important; color: #1f2937 !important; }
[data-theme="light"] .btn-icon-sm { background: rgba(0,0,0,0.04) !important; border-color: rgba(0,0,0,0.12) !important; color: #4b5563 !important; }
[data-theme="light"] .btn-icon-sm:hover { background: rgba(0,0,0,0.08) !important; color: #1f2937 !important; }
[data-theme="light"] .btn-primary-solid { background: linear-gradient(135deg, #2563eb, #7c3aed) !important; border: none !important; color: #ffffff !important; }
[data-theme="light"] .btn-primary-solid:hover { background: linear-gradient(135deg, #1d4ed8, #6d28d9) !important; color: #ffffff !important; box-shadow: 0 0.5rem 1.375rem rgba(var(--accent-rgb),0.30) !important; transform: translateY(-0.0625rem); }
[data-theme="light"] .btn-success-solid { background: linear-gradient(135deg, #047857, #059669) !important; border: none !important; color: #ffffff !important; }
[data-theme="light"] .btn-success-solid:hover { background: linear-gradient(135deg, #065f46, #059669) !important; color: #ffffff !important; box-shadow: 0 0.5rem 1.375rem rgba(4,120,87,0.30) !important; transform: translateY(-0.0625rem); }
[data-theme="light"] .btn-danger-soft { background: rgba(239,68,68,0.08) !important; border-color: rgba(239,68,68,0.25) !important; color: #b91c1c !important; }
[data-theme="light"] .btn-danger-soft:hover { background: rgba(239,68,68,0.16) !important; color: #991b1b !important; }
[data-theme="light"] .ajuste-seccion {
    background: #ffffff !important;
    border-color: rgba(0,0,0,0.12) !important;
}
[data-theme="light"] .ajuste-seccion div:not(.stat-value) { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .ajuste-seccion .stat-value[style*="93c5fd"] { color: #1d4ed8 !important; }
[data-theme="light"] .ajuste-seccion .stat-value[style*="a78bfa"] { color: #5b21b6 !important; }
[data-theme="light"] .ajuste-seccion .stat-value[style*="6ee7b7"] { color: #047857 !important; }
[data-theme="light"] .tab-prof.active {
    color: #1e40af !important;
    border-color: rgba(37,99,235,0.45) !important;
    box-shadow: inset 0 0 0 0.0625rem rgba(37,99,235,0.15) !important;
}
[data-theme="light"] .tab-prof .count { background: rgba(0,0,0,0.06) !important; }
[data-theme="light"] .badge-acumulacion { background: rgba(4,120,87,0.12) !important; border-color: rgba(4,120,87,0.35) !important; color: #047857 !important; }
[data-theme="light"] .badge-baja { background: rgba(180,83,9,0.12) !important; border-color: rgba(180,83,9,0.35) !important; color: #b45309 !important; }
[data-theme="light"] .badge-excedido { background: rgba(185,28,28,0.12) !important; border-color: rgba(185,28,28,0.35) !important; color: #b91c1c !important; }
[data-theme="light"] .badge-factor { background: rgba(29,78,216,0.1) !important; border-color: rgba(29,78,216,0.3) !important; color: #1d4ed8 !important; }
[data-theme="light"] .badge-activo { background: rgba(4,120,87,0.12) !important; border-color: rgba(4,120,87,0.35) !important; color: #047857 !important; }
[data-theme="light"] .tabs-prof {
    background: rgba(0,0,0,0.04) !important;
    border-color: rgba(0,0,0,0.12) !important;
}
[data-theme="light"] .modal-content-prof { background: #ffffff !important; border-color: rgba(0,0,0,0.15) !important; color: #1f2937 !important; }

/* ===== Select2 (tema claro) ===== */
[data-theme="light"] .select2-container--default .select2-selection--single,
[data-theme="light"] .select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #ffffff !important;
    border-color: rgba(0,0,0,0.18) !important;
}
[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered { color: #1f2937 !important; }
[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: #333 transparent transparent transparent !important; }
[data-theme="light"] .select2-dropdown { background-color: #ffffff !important; border-color: rgba(0,0,0,0.15) !important; }
[data-theme="light"] .select2-container--default .select2-results__option { color: #1f2937 !important; }
[data-theme="light"] .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background: rgba(var(--accent-rgb),0.15) !important; color: #1f2937 !important; }
[data-theme="light"] .select2-container--default .select2-results__option[aria-selected="true"],
[data-theme="light"] .select2-container--default .select2-results__option--selected { background: rgba(var(--accent-rgb),0.15) !important; color: #5b21b6 !important; }

/* ===== .dt-search (buscador personalizado) tema claro ===== */
[data-theme="light"] .dt-search .input-group-text {
    background-color: #ffffff !important; border-color: rgba(0,0,0,0.18) !important; color: #1d4ed8 !important;
}
[data-theme="light"] .dt-search .form-control { background-color: #ffffff !important; border-color: rgba(0,0,0,0.18) !important; color: #1f2937 !important; }
[data-theme="light"] .dt-search .btn-outline-secondary { border-color: rgba(0,0,0,0.18) !important; color: rgba(0,0,0,0.7) !important; }

/* ===== option de selects (tema claro) ===== */
[data-theme="light"] option { background: #ffffff !important; color: #1f2937 !important; }

/* ===== DataTables / tablas: filas de títulos (thead) en claro ===== */
[data-theme="light"] table.dataTable thead th,
[data-theme="light"] .dataTables_wrapper .dataTable thead th,
[data-theme="light"] .data-table thead th {
    background: #eef2f7 !important;
    color: #111827 !important;
    border-bottom: 0.0625rem solid rgba(0,0,0,0.12) !important;
}
[data-theme="light"] .table-prof thead th,
[data-theme="light"] #tablaHistorialIndividual thead th,
[data-theme="light"] #tablaHistorialIndividual thead tr:first-child th,
[data-theme="light"] #tablaHistorialIndividual thead tr:last-child th {
    background: #eef2f7 !important;
    color: #111827 !important;
    border-bottom: 0.125rem solid rgba(var(--accent-rgb),0.25) !important;
}
[data-theme="light"] .table-prof tfoot td { background: #eef2f7 !important; color: #111827 !important; }
[data-theme="light"] .table-prof tbody td { border-bottom-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .table-prof tbody tr:hover td { background: rgba(var(--accent-rgb),0.07) !important; }

/* ===== Títulos de modales (todas las variantes) en claro ===== */
[data-theme="light"] .modal-title { color: #111827 !important; }
[data-theme="light"] .modal-header h5,
[data-theme="light"] .modal-header h4,
[data-theme="light"] .modal-header h3 { color: #111827 !important; }
[data-theme="light"] .modal-header { color: #111827 !important; }

/* ===== users.php ===== */
[data-theme="light"] .stat-box small { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .stat-box .h4 { color: #111827 !important; }
[data-theme="light"] .profile-username { color: rgba(0,0,0,0.55) !important; }
[data-theme="light"] .info-label { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .info-value { color: #1f2937 !important; }
[data-theme="light"] .info-value code { color: var(--accent-dark) !important; }
[data-theme="light"] .info-item { background: rgba(0,0,0,0.03) !important; border-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .info-item:hover { background: rgba(var(--accent-rgb),0.06) !important; border-color: rgba(var(--accent-rgb),0.2) !important; }
[data-theme="light"] .perfil-acciones .dropdown-item { color: #1f2937 !important; }
[data-theme="light"] .perfil-acciones .dropdown-item:hover { background: rgba(var(--accent-rgb),0.22) !important; color: #1f2937 !important; }
[data-theme="light"] .perfil-acciones .dropdown-item.text-danger { color: #dc2626 !important; }
[data-theme="light"] .perfil-acciones .dropdown-item.text-danger:hover { background: rgba(239,68,68,0.12) !important; color: #dc2626 !important; }
[data-theme="light"] .perfil-acciones .dropdown-divider { border-color: rgba(0,0,0,0.12) !important; }
[data-theme="light"] .perfil-acciones .dropdown-toggle::after { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .pass-toggle { background: rgba(0,0,0,0.04) !important; border-color: rgba(0,0,0,0.18) !important; color: #4b5563 !important; }
[data-theme="light"] .pass-toggle:hover { background: rgba(0,0,0,0.08) !important; color: #1f2937 !important; }
[data-theme="light"] .password-toggle { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .password-toggle:hover { color: var(--accent-dark) !important; }
[data-theme="light"] .modal-subtitle { color: rgba(0,0,0,0.55) !important; }
[data-theme="light"] .section-title { color: #111827 !important; border-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .section-title i { color: var(--accent-dark) !important; }
[data-theme="light"] .form-usuario-status.edit { background: rgba(var(--accent-rgb),0.08) !important; border-color: rgba(var(--accent-rgb),0.25) !important; color: #1d4ed8 !important; }
[data-theme="light"] .form-usuario-status.create { background: rgba(16,185,129,0.08) !important; border-color: rgba(16,185,129,0.25) !important; color: #047857 !important; }
[data-theme="light"] .avatar-placeholder { color: rgba(0,0,0,0.4) !important; }
[data-theme="light"] .modal-avatar-header,
[data-theme="light"] .avatar-preview { background: rgba(0,0,0,0.05) !important; border-color: var(--accent-dark) !important; }

/* ===== usuarios.php (contenido; la barra lateral se mantiene oscura) ===== */
[data-theme="light"] .stat-card h6 { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .stat-card h3 { color: #1f2937 !important; }

/* ===== empleados.php: donut género / edad / collapses ===== */
[data-theme="light"] .card-collapse-title { color: #1f2937 !important; }
[data-theme="light"] .donut-hole { background: #f3f3f3 !important; }
[data-theme="light"] .donut-hole .stat-value { color: #1f2937 !important; }
[data-theme="light"] .donut-hole .stat-label { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .progress-custom { background: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .border-white-10 { border-color: rgba(0,0,0,0.1) !important; }

/* ORGULLO: hueco del donut claro para que el texto central sea legible */
[data-theme="orgullo"] .donut-hole { background: #f4eefb !important; }
[data-theme="orgullo"] .donut-hole .stat-value { color: #4c1d95 !important; }
[data-theme="orgullo"] .donut-hole .stat-label { color: #6d5b91 !important; }

/* ===== bandecnom.php: guía "¿Cómo usar el exportador?" y selección ===== */
[data-theme="light"] .alert-guide {
    background: linear-gradient(135deg, #eef4ff, #f4f8ff) !important;
    border-color: rgba(var(--accent-rgb),0.35) !important;
}
[data-theme="light"] .guide-header {
    background: rgba(var(--accent-rgb),0.1) !important;
    border-bottom-color: rgba(var(--accent-rgb),0.2) !important;
    color: var(--accent-dark) !important;
}
[data-theme="light"] .guide-close { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .guide-close:hover { background: rgba(0,0,0,0.06) !important; color: #1f2937 !important; }
[data-theme="light"] .step { background: rgba(0,0,0,0.04) !important; }
[data-theme="light"] .step:hover { background: rgba(var(--accent-rgb),0.08) !important; }
[data-theme="light"] .step-text strong { color: #1f2937 !important; }
[data-theme="light"] .step-text small { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .guide-requirements { background: rgba(0,0,0,0.03) !important; border-left-color: var(--accent-dark) !important; }
[data-theme="light"] .guide-requirements > i { color: var(--accent-dark) !important; }
[data-theme="light"] .guide-requirements li { color: #374151 !important; }
[data-theme="light"] .card-option:not(.selected):not(:hover) {
    background: #ffffff !important;
    border-color: rgba(0,0,0,0.12) !important;
}
[data-theme="light"] .card-option:not(.selected):not(:hover) h5 { color: #111827 !important; }
[data-theme="light"] .card-option:not(.selected):not(:hover) p { color: rgba(0,0,0,0.55) !important; }
/* En hover/selección el color lo pone el formato (bandecnom.php) */
[data-theme="light"] .card-option.selected h5,
[data-theme="light"] .card-option.selected p { color: #ffffff !important; }
[data-theme="light"] .card-option.selected .badge-format { background: rgba(255,255,255,0.2) !important; border: 0.0625rem solid rgba(255,255,255,0.45) !important; color: #ffffff !important; }

/* ===== nominas.php: chevron del selector de tipo de nómina ===== */
[data-theme="light"] .tipo-nomina-preview i:last-child { color: rgba(0,0,0,0.5) !important; }
[data-theme="light"] .tipo-nomina-preview i:first-child { color: var(--accent-dark) !important; }
.tipo-nomina-preview i:last-child { font-size:0.6rem !important; }
.btn-win .fa-chevron-down { font-size:0.75rem !important; }

/* ===== clasificadores.php: fichas de selección ===== */
[data-theme="light"] .clasificador-selector { background: rgba(0,0,0,0.04) !important; }
[data-theme="light"] .clasificador-btn { color: rgba(0,0,0,0.6) !important; }
[data-theme="light"] .clasificador-btn:hover { background: rgba(0,0,0,0.06) !important; color: #1f2937 !important; }
[data-theme="light"] .clasificador-btn.active { background: var(--accent-dark) !important; color: #ffffff !important; }
[data-theme="light"] .perm-card { background: #ffffff !important; border-color: rgba(0,0,0,0.12) !important; }
[data-theme="light"] .perm-card:hover { border-color: rgba(var(--accent-rgb),0.4) !important; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.12) !important; }
[data-theme="light"] .perm-card-head { background: linear-gradient(135deg, rgba(var(--accent-rgb),0.10), rgba(var(--accent-rgb),0.07)) !important; border-bottom-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .perm-card-rol { color: #1d4ed8 !important; }
[data-theme="light"] .perm-row { border-bottom-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .perm-row-nombre { color: #374151 !important; }
[data-theme="light"] .perm-chip-off { background: rgba(0,0,0,0.05) !important; color: #6b7280 !important; border-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .perm-chip-on { background: rgba(4,120,87,0.12) !important; color: #047857 !important; border-color: rgba(4,120,87,0.35) !important; }
[data-theme="light"] .mode-badge.create { background: rgba(4,120,87,0.12) !important; color: #047857 !important; border-color: rgba(4,120,87,0.35) !important; }
[data-theme="light"] .form-usuario-status.create { background: rgba(4,120,87,0.1) !important; color: #047857 !important; }
[data-theme="light"] .nav-selector select option { background: #ffffff !important; color: #1f2937 !important; }
[data-theme="light"] .form-control.text-white { background-color: #ffffff !important; color: #1f2937 !important; border-color: rgba(0,0,0,0.18) !important; }
[data-theme="light"] .modal-preview-table th { background: #eef2f7 !important; color: #111827 !important; }
[data-theme="light"] .badge-disfrute { background: rgba(180,83,9,0.12) !important; border-color: rgba(180,83,9,0.35) !important; color: #b45309 !important; }
[data-theme="light"] .badge-ajuste { background: rgba(29,78,216,0.1) !important; border-color: rgba(29,78,216,0.3) !important; color: #1d4ed8 !important; }
[data-theme="light"] #tablaSaldos tbody tr.vacaciones-excedidas .saldo-cierre { color: #b91c1c !important; }
[data-theme="light"] .action-btn.edit { color: #1d4ed8 !important; }
[data-theme="light"] .action-btn.toggle { color: #b45309 !important; }
[data-theme="light"] .perm-card-rol { color: #1d4ed8 !important; }
[data-theme="light"] .estado-activo { color: #047857 !important; }
[data-theme="light"] .mode-badge.edit { background: rgba(29,78,216,0.1) !important; border-color: rgba(29,78,216,0.3) !important; color: #1d4ed8 !important; }
[data-theme="light"] .form-usuario-status.edit { background: rgba(29,78,216,0.1) !important; border-color: rgba(29,78,216,0.3) !important; color: #1d4ed8 !important; }

/* ===== Textos con colores claros inline: oscurecer en claro ===== */
[data-theme="light"] [style*="var(--accent)"],
[data-theme="light"] [style*="#93c5fd"],
[data-theme="light"] [style*="#a5b4fc"],
[data-theme="light"] [style*="#c4b5fd"],
[data-theme="light"] [style*="#aeb9cf"] { color: #1d4ed8 !important; }
[data-theme="light"] [style*="#34d399"],
[data-theme="light"] [style*="#4ade80"],
[data-theme="light"] [style*="#86efac"],
[data-theme="light"] [style*="#6ee7b7"] { color: #047857 !important; }
[data-theme="light"] [style*="#fbbf24"],
[data-theme="light"] [style*="#fcd34d"] { color: #b45309 !important; }
[data-theme="light"] [style*="#fca5a5"] { color: #b91c1c !important; }
[data-theme="light"] [style*="var(--accent)"] { color: #5b21b6 !important; }
[data-theme="light"] [style*="color:#e2e8f0"][style*="rgba(52,211,153"] { color: #047857 !important; border-color: rgba(4,120,87,0.35) !important; }
[data-theme="light"] [style*="color:#e2e8f0"][style*="rgba(96,165,250"] { color: #1d4ed8 !important; border-color: rgba(29,78,216,0.35) !important; }
[data-theme="light"] [style*="color:#e2e8f0"][style*="rgba(251,191,36"] { color: #b45309 !important; border-color: rgba(180,83,9,0.35) !important; }
[data-theme="light"] [style*="background:#0ea5e9"] { color: #ffffff !important; }
/* ===== Salva/Restaura modal — Windows 11 style ===== */
@keyframes win-modal-pulse {
    0% { transform: scale(1); }
    30% { transform: scale(1.04); }
    60% { transform: scale(0.97); }
    100% { transform: scale(1); }
}
.salva-restaura-popup { border: none !important; box-shadow: none !important; background: transparent !important; overflow: visible !important; padding: 0 !important; border-radius: 0 !important; max-width: 37.5rem; }
.salva-restaura-popup .swal2-html-container { margin: 0 !important; padding: 0 !important; }
.salva-restaura-container { background: transparent !important; }
.salva-restaura-popup.win-modal-pulse { animation: win-modal-pulse 0.35s cubic-bezier(0.36, 0.07, 0.19, 0.97); }

.win-modal-titlebar {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.5rem 0.5rem 0.875rem;
    background: var(--accent);
    border: none;
    border-radius: 0.5rem 0.5rem 0 0;
    user-select: none;
    cursor: default;
}
.win-modal-titlebar-icon {
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    color: #fff;
    flex-shrink: 0;
}
.win-modal-titlebar-text {
    flex: 1;
    color: #fff;
    line-height: 1.3;
}
.win-modal-titlebar-btns {
    display: flex;
    gap: 0;
}
.win-modal-titlebar-btns button {
    width: 2.75rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 0.75rem;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    border-radius: 0;
}
.win-modal-titlebar-btns button:hover { background: rgba(255,255,255,0.2); color: #fff; }
.win-modal-titlebar-btns .win-modal-btn-close:hover { background: #e81123; color: #fff; }

.win-modal-body {
    background: #1e1e2e;
    border: none;
    border-radius: 0 0 0.5rem 0.5rem;
    padding: 1.25rem;
    color: #e2e8f0;
}
.win-modal-footer {
    border-top: 0.0625rem solid rgba(255,255,255,0.06);
    margin-top: 0.875rem;
    padding-top: 0.75rem;
    text-align: center;
    font-size: 0.7188rem;
    color: rgba(255,255,255,0.35);
}

/* ===== Light theme ===== */
[data-theme="light"] .salva-restaura-popup { border-radius: 0 !important; }
[data-theme="light"] .win-modal-titlebar { background: var(--accent); }
[data-theme="light"] .win-modal-titlebar-icon { color: #fff; }
[data-theme="light"] .win-modal-titlebar-text { color: #fff; }
[data-theme="light"] .win-modal-titlebar-btns button { color: rgba(255,255,255,0.7); }
[data-theme="light"] .win-modal-titlebar-btns button:hover { background: rgba(255,255,255,0.2); color: #fff; }
[data-theme="light"] .win-modal-titlebar-btns .win-modal-btn-close:hover { background: #e81123; color: #fff; }
[data-theme="light"] .win-modal-body { background: #ffffff; color: #1f2937; }
[data-theme="light"] .win-modal-footer { border-color: rgba(0,0,0,0.06); color: #9ca3af; }
[data-theme="light"] .permisos-leyenda { background: rgba(0,0,0,0.03) !important; border-color: rgba(0,0,0,0.1) !important; color: #4b5563 !important; }
[data-theme="light"] .permisos-leyenda { background: rgba(0,0,0,0.03) !important; border-color: rgba(0,0,0,0.1) !important; color: #4b5563 !important; }
[data-theme="light"] .table-custom th,
[data-theme="light"] .table-custom td { border-bottom-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .table-custom th { color: var(--accent-dark) !important; }
[data-theme="light"] .filter-label { color: #6b7280 !important; }

/* ===== Inputs genéricos oscuros (dark-input / dark-textarea) ===== */
[data-theme="light"] .dark-input,
[data-theme="light"] .dark-textarea {
    background: #ffffff !important;
    border-color: rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] .dark-input:focus,
[data-theme="light"] .dark-textarea:focus {
    border-color: var(--accent-dark) !important;
    box-shadow: 0 0 0 0.1875rem rgba(var(--accent-rgb),0.15) !important;
}

/* ===== clasificadores.php: botón Limpiar (estilos inline oscuros) ===== */
[data-theme="light"] #btnLimpiarFiltros {
    background: rgba(0,0,0,0.05) !important;
    border-color: rgba(0,0,0,0.18) !important;
    color: #1f2937 !important;
}
[data-theme="light"] #btnLimpiarFiltros:hover {
    background: rgba(0,0,0,0.09) !important;
    color: #1f2937 !important;
}

/* ===== bandecnom.php: panel de totales (card) ===== */
[data-theme="light"] .totals-panel {
    background: #ffffff !important;
    border-color: rgba(var(--accent-rgb),0.35) !important;
}
[data-theme="light"] .total-label { color: rgba(0,0,0,0.55) !important; }
[data-theme="light"] .total-value { color: #1f2937 !important; }
[data-theme="light"] .total-value.positive { color: #16a34a !important; }
[data-theme="light"] .total-value.negative { color: #dc2626 !important; }
[data-theme="light"] .total-label-acreditar { color: rgba(0,0,0,0.55) !important; }
[data-theme="light"] .total-value-acreditar { color: #16a34a !important; }
[data-theme="light"] .total-item-acreditar {
    background: linear-gradient(135deg, rgba(var(--accent-rgb),0.24), rgba(var(--accent-rgb),0.12)) !important;
    border-color: rgba(var(--accent-rgb),0.7) !important;
    box-shadow: 0 0 0 0.125rem rgba(var(--accent-rgb),0.22), 0 0.5rem 1.375rem rgba(var(--accent-rgb),0.2) !important;
}
[data-theme="light"] .total-item-acreditar:hover {
    background: linear-gradient(135deg, rgba(var(--accent-rgb),0.32), rgba(var(--accent-rgb),0.16)) !important;
    border-color: rgba(0,140,232,0.85) !important;
    box-shadow: 0 0 0 0.125rem rgba(var(--accent-rgb),0.28), 0 0.625rem 1.625rem rgba(var(--accent-rgb),0.26) !important;
}
[data-theme="light"] .total-divider {
    background: linear-gradient(to bottom, transparent, var(--accent-dark), transparent) !important;
}
[data-theme="light"] .stat-badge.success { background: rgba(22,163,74,0.50) !important; color: #15803d !important; border-color: rgba(22,163,74,0.3) !important; }
[data-theme="light"] .stat-badge.danger { background: rgba(220,38,38,0.50) !important; color: #b91c1c !important; border-color: rgba(220,38,38,0.3) !important; }
[data-theme="light"] .stat-badge.info { background: rgba(37,99,235,0.50) !important; color: #1d4ed8 !important; border-color: rgba(37,99,235,0.3) !important; }

/* ===== Hover más oscuro (tema claro): menús, botones, tarjetas ===== */
[data-theme="light"] .dropdown-item:hover,
[data-theme="light"] .dropdown-menu-win .dropdown-item:hover { background: rgba(var(--accent-rgb),0.22) !important; }
[data-theme="light"] .perfil-acciones .dropdown-item:hover { background: rgba(var(--accent-rgb),0.22) !important; }
[data-theme="light"] .btn-win:not(.btn-win-primary):hover { background: rgba(0,0,0,0.08) !important; color: #1f2937 !important; }
[data-theme="light"] .info-item:hover { background: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .clasificador-btn:hover { background: rgba(0,0,0,0.10) !important; color: #1f2937 !important; }

/* ===== Alert-info: colores oscuros → adaptables al tema ===== */
[data-theme="light"] .alert-info { background: rgba(var(--accent-rgb),0.08) !important; border-color: rgba(var(--accent-rgb),0.25) !important; color: var(--accent-dark) !important; }
[data-theme="light"] .alert-info strong, [data-theme="light"] .alert-info b { color: var(--accent-dark) !important; }
[data-theme="light"] .alert-info .btn-close { filter: invert(1); }

/* ===== Textos hardcoded dark (#cbd5e1, #93c5fd, #e2e8f0) → variables ===== */
[data-theme="light"] [style*="color: #cbd5e1"] { color: var(--muted) !important; }
[data-theme="light"] [style*="color:#cbd5e1"] { color: var(--muted) !important; }
[data-theme="light"] [style*="color: #93c5fd"] { color: var(--accent) !important; }
[data-theme="light"] [style*="color:#93c5fd"] { color: var(--accent) !important; }
[data-theme="light"] [style*="color: #e2e8f0"] { color: #1f2937 !important; }
[data-theme="light"] [style*="color:#e2e8f0"] { color: #1f2937 !important; }
[data-theme="light"] [style*="color: #60a5fa"] { color: var(--accent-dark) !important; }
[data-theme="light"] [style*="color:#60a5fa"] { color: var(--accent-dark) !important; }
[data-theme="light"] [style*="background: rgba(59, 130, 246"] { background: rgba(var(--accent-rgb),0.1) !important; }
[data-theme="light"] [style*="background:rgba(59,130,246"] { background: rgba(var(--accent-rgb),0.1) !important; }
[data-theme="light"] [style*="background: rgba(96, 165, 250"] { background: rgba(var(--accent-rgb),0.1) !important; }
[data-theme="light"] [style*="background:rgba(96,165,250"] { background: rgba(var(--accent-rgb),0.1) !important; }

/* ===== configuracion.php: tablas Rangos de Impuesto y Tasas del Sistema ===== */
[data-theme="light"] .table-responsive {
    background: #ffffff !important;
    border-color: rgba(0,0,0,0.12) !important;
}
[data-theme="light"] #rangosTable thead th,
[data-theme="light"] #tasasTable thead th {
    background: #f1f5f9 !important;
    border-bottom-color: rgba(0,0,0,0.12) !important;
    color: #1f2937 !important;
}
[data-theme="light"] #rangosTable tbody tr,
[data-theme="light"] #tasasTable tbody tr {
    background: #ffffff !important;
    border-bottom-color: rgba(0,0,0,0.08) !important;
}
[data-theme="light"] #rangosTable tbody tr:hover,
[data-theme="light"] #tasasTable tbody tr:hover {
    background: rgba(var(--accent-rgb),0.08) !important;
}
[data-theme="light"] #rangosTable td,
[data-theme="light"] #tasasTable td {
    color: #1f2937 !important;
}

/* ===== BORDES DE CARDS MÁS VISIBLES EN TEMA CLARO ===== */
[data-theme="light"] .glass-card,
[data-theme="light"] .card:not([class*="bg-primary"]):not([class*="bg-dark"]):not([class*="bg-secondary"]):not([class*="bg-info"]):not([class*="bg-success"]):not([class*="bg-danger"]):not([class*="bg-gradient"]),
[data-theme="light"] .stat-card:not(.selected),
[data-theme="light"] .stat-card-prof:not(.selected),
[data-theme="light"] .preview-card:not(.selected),
[data-theme="light"] .perm-card:not(.selected),
[data-theme="light"] .total-item:not(:hover),
[data-theme="light"] .card-option:not(.selected):not(:hover),
[data-theme="light"] .card-option-descuento:not(.selected),
[data-theme="light"] .modal-content-win,
[data-theme="light"] .modal-content-prof,
[data-theme="light"] .modal-content-modern,
[data-theme="light"] .selected-worker-card {
    border-color: rgba(0, 0, 0, 0.28) !important;
}

/* ======================================================
   TEMA AZUL (Light Navy / LogoTN) — más claro que dark
   ====================================================== */
[data-theme="blue"] body { background: #152540 !important; color: #dce8ff !important; }

[data-theme="blue"] {
    --bg:#152540;
    --panel:#1a3050;
    --panel-2:#1e3858;
    --card:rgba(26,48,80,0.82);
    --card-hover:rgba(30,56,88,0.95);
    --border:rgba(130,190,255,0.18);
    --border-2:rgba(130,190,255,0.28);
    --txt:#dce8ff;
    --muted:#9dbde8;
    --faint:#6a9bd4;
    --shadow:0 0.75rem 2.125rem rgba(0,0,0,0.35);
    --win-sidebar-bg:rgba(12,22,42,0.97);
    --win-sidebar-border:rgba(130,190,255,0.12);
}
[data-theme="blue"] .win11-bg { background: linear-gradient(135deg, #152540 0%, #1a3050 50%, #1e3858 100%) !important; }
[data-theme="blue"] .win11-bg::before {
    background-image: radial-gradient(circle at 20% 80%, rgba(59,130,246,0.12) 0%, transparent 50%),
                      radial-gradient(circle at 80% 20%, rgba(59,130,246,0.08) 0%, transparent 50%) !important;
}
[data-theme="blue"] .glass-card {
    background: rgba(26,48,80,0.82) !important;
    border: 0.0625rem solid rgba(130,190,255,0.18) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .glass-card:hover {
    background: rgba(22,42,72,0.92) !important;
    border-color: rgba(59,130,246,0.35) !important;
}
[data-theme="blue"] .win-sidebar {
    background: rgba(12,22,42,0.97) !important;
    border-right: 0.0625rem solid rgba(130,190,255,0.12) !important;
}
[data-theme="blue"] .sidebar-footer { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .sidebar-logo { border-bottom: 0.0625rem solid rgba(130,190,255,0.1) !important; }
[data-theme="blue"] .sidebar-logo small { color: rgba(130,190,255,0.5) !important; }
[data-theme="blue"] .nav-item { background: transparent !important; color: rgba(180,210,255,0.78) !important; }
[data-theme="blue"] .nav-item:hover { background: rgba(10,20,40,0.65) !important; color: var(--accent) !important; border-color: rgba(59,130,246,0.2) !important; }
[data-theme="blue"] .nav-item:hover i { color: var(--accent) !important; }
[data-theme="blue"] .nav-item.active { background: var(--accent) !important; color: #fff !important; border-color: transparent !important; border-radius:0.5rem !important; }
[data-theme="blue"] .nav-item.active i { color: #fff !important; }
[data-theme="blue"] .nav-item.active .sidebar-text { color: #fff !important; }
[data-theme="blue"] .nav-item.active .nav-badge { background: rgba(255,255,255,0.25) !important; color: #fff !important; border-color: rgba(255,255,255,0.3) !important; }
[data-theme="blue"] .nav-item.active .nav-group-chevron { color: #fff !important; }
[data-theme="blue"] .main-container { color: #dce8ff !important; }
[data-theme="blue"] .win-topbar {
    background: rgba(20,36,60,0.85) !important;
    border: 0.0625rem solid rgba(130,190,255,0.15) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .sidebar-toggle { background: rgba(130,190,255,0.06) !important; color: #dce8ff !important; }
[data-theme="blue"] .sidebar-toggle:hover { background: rgba(10,20,40,0.5) !important; color: var(--accent) !important; }
[data-theme="blue"] .page-title p { color: #9dbde8 !important; }
/* Botones azul — hover oscuro */
[data-theme="blue"] .btn-win-primary {
    background: linear-gradient(135deg, var(--accent), var(--accent-light)) !important;
    border: none !important; color: #ffffff !important;
}
[data-theme="blue"] .btn-win-primary:hover {
    background: linear-gradient(135deg, var(--accent-dark), #1e40af) !important;
    border: none !important; color: #ffffff !important;
    transform: translateY(-0.0625rem);
}
[data-theme="blue"] .btn-win {
    background: rgba(130,190,255,0.06) !important;
    border: 0.0625rem solid rgba(130,190,255,0.2) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .btn-win:not(.btn-win-primary):hover { background: rgba(10,20,40,0.5) !important; border-color: var(--accent) !important; color: var(--accent) !important; }
[data-theme="blue"] .btn-win-sm {
    background: rgba(130,190,255,0.06) !important;
    border: 0.0625rem solid rgba(130,190,255,0.2) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .btn-win-sm:not(.btn-win-primary):hover { background: rgba(10,20,40,0.4) !important; border-color: var(--accent) !important; color: var(--accent) !important; }
[data-theme="blue"] .stat-card { color: #dce8ff !important; }
[data-theme="blue"] .stat-card h6 { color: #9dbde8 !important; }
[data-theme="blue"] .stat-card h3 { color: #dce8ff !important; }
[data-theme="blue"] .stat-card .stat-icon { background: rgba(59,130,246,0.1) !important; }
/* Nav tabs azul */
[data-theme="blue"] .nav-tabs-modern .nav-link {
    background: rgba(130,190,255,0.06) !important;
    color: #9dbde8 !important;
    border: 0.0625rem solid rgba(130,190,255,0.1) !important;
}
[data-theme="blue"] .nav-tabs-modern .nav-link.active { background: rgba(59,130,246,0.15) !important; color: var(--accent) !important; border-color: var(--accent) !important; }
[data-theme="blue"] .nav-tabs-modern .nav-link:hover { background: rgba(10,20,40,0.4) !important; color: var(--accent) !important; border-color: rgba(59,130,246,0.3) !important; }
[data-theme="blue"] label, [data-theme="blue"] .form-label, [data-theme="blue"] .text-muted, [data-theme="blue"] small, [data-theme="blue"] .small { color: #9dbde8 !important; }
[data-theme="blue"] .text-muted i { color: #6a9bd4 !important; }
[data-theme="blue"] .form-control-custom, [data-theme="blue"] .form-select {
    background-color: rgba(26,48,80,0.92) !important;
    border: 0.0625rem solid rgba(130,190,255,0.25) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .form-control, [data-theme="blue"] .form-control-sm, [data-theme="blue"] .form-control-lg {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.25) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] option { background: #1a3050 !important; color: #dce8ff !important; }
/* Input group / Buscadores azul */
[data-theme="blue"] .input-group-text {
    background: rgba(59,130,246,0.1) !important;
    border: 0.0625rem solid rgba(130,190,255,0.22) !important;
    color: #93c5fd !important;
}
[data-theme="blue"] .input-group-text.bg-dark {
    background-color: rgba(59,130,246,0.1) !important;
    border-color: rgba(130,190,255,0.22) !important;
    color: #93c5fd !important;
}
[data-theme="blue"] .input-group-text.text-white,
[data-theme="blue"] .input-group-text.text-info,
[data-theme="blue"] .input-group-text.text-nocturno { color: #93c5fd !important; }
[data-theme="blue"] .input-group .form-control,
[data-theme="blue"] .input-group .form-select {
    background-color: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.25) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .input-group .btn { border-color: rgba(130,190,255,0.2) !important; }
[data-theme="blue"] .search-box input { background: #1a3050 !important; border: 0.0625rem solid rgba(130,190,255,0.25) !important; color: #dce8ff !important; }
[data-theme="blue"] .search-box input::placeholder { color: #6a9bd4 !important; }
[data-theme="blue"] .table-custom { color: #dce8ff !important; }
[data-theme="blue"] .table-custom th, [data-theme="blue"] .table-custom td { border-bottom: 0.0625rem solid rgba(130,190,255,0.12) !important; color: #dce8ff !important; }
[data-theme="blue"] .table-custom th { color: #9dbde8 !important; }
[data-theme="blue"] .table-custom thead tr th { background: rgba(59,130,246,0.12) !important; border-bottom: 0.0625rem solid rgba(130,190,255,0.18) !important; }
[data-theme="blue"] .table-custom tfoot td { background: rgba(59,130,246,0.12) !important; border-top: 0.0625rem solid rgba(130,190,255,0.18) !important; }
[data-theme="blue"] .table-custom tr:hover td { background: rgba(10,20,40,0.4) !important; }
[data-theme="blue"] table.dataTable tbody tr.odd td { background-color: rgba(59,130,246,0.04) !important; color: #dce8ff !important; }
[data-theme="blue"] table.dataTable tbody tr.even td { background-color: rgba(59,130,246,0.07) !important; color: #dce8ff !important; }
[data-theme="blue"] .data-table-wrapper { border: 0.0625rem solid rgba(130,190,255,0.15) !important; background: rgba(26,48,80,0.55) !important; }
[data-theme="blue"] .badge-borrador { background: rgba(245,158,11,0.2) !important; border: 0.0625rem solid #f59e0b !important; color: #fcd34d !important; }
[data-theme="blue"] .badge-contabilizado { background: rgba(16,185,129,0.2) !important; border: 0.0625rem solid #10b981 !important; color: #6ee7b7 !important; }
[data-theme="blue"] .dataTables_wrapper { color: #dce8ff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_length,
[data-theme="blue"] .dataTables_wrapper .dataTables_filter,
[data-theme="blue"] .dataTables_wrapper .dataTables_info,
[data-theme="blue"] .dataTables_wrapper .dataTables_processing { color: #9dbde8 !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button {
    background: rgba(26,48,80,0.92) !important;
    border: 0.0625rem solid rgba(130,190,255,0.2) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: rgba(10,20,40,0.5) !important; border-color: var(--accent) !important; color: #dce8ff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-right,
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-left {
    font-size:0.8rem !important; color: var(--accent) !important;
}
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-right,
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-left { color: #ffffff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_length select,
[data-theme="blue"] .dataTables_wrapper .dataTables_filter input {
    background: rgba(26,48,80,0.92) !important;
    border: 0.0625rem solid rgba(130,190,255,0.2) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .footer-card { color: #9dbde8 !important; }
[data-theme="blue"] .footer-card hr { border-color: rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .footer-card small { color: #6a9bd4 !important; }
[data-theme="blue"] .date-badge { background: rgba(59,130,246,0.12) !important; color: var(--accent) !important; }
/* Alerts azul */
[data-theme="blue"] .alert { border: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .alert-success { background: rgba(16,185,129,0.15) !important; border: 0.0625rem solid #10b981 !important; color: #6ee7b7 !important; }
[data-theme="blue"] .alert-info { background: rgba(59,130,246,0.15) !important; border: 0.0625rem solid var(--accent) !important; color: #93c5fd !important; }
[data-theme="blue"] .alert-warning { background: rgba(245,158,11,0.15) !important; border: 0.0625rem solid #f59e0b !important; color: #fcd34d !important; }
[data-theme="blue"] .alert-danger { background: rgba(239,68,68,0.15) !important; border: 0.0625rem solid #ef4444 !important; color: #fca5a5 !important; }
[data-theme="blue"] .search-box i { color: #6a9bd4 !important; }
[data-theme="blue"] ::-webkit-scrollbar-track { background: rgba(130,190,255,0.06) !important; }
[data-theme="blue"] ::-webkit-scrollbar-thumb { background: rgba(59,130,246,0.3) !important; }
[data-theme="blue"] ::-webkit-scrollbar-thumb:hover { background: rgba(59,130,246,0.5) !important; }
[data-theme="blue"] .edit-input { background: rgba(59,130,246,0.08) !important; border: 0.0625rem solid rgba(147,197,253,0.5) !important; color: #dce8ff !important; }
[data-theme="blue"] .dias-input, [data-theme="blue"] .selected-worker-card .dias-input { background: rgba(26,48,80,0.95) !important; color: #dce8ff !important; }
[data-theme="blue"] .worker-detail { color: #9dbde8 !important; }
[data-theme="blue"] .worker-name { color: #dce8ff !important; }
/* Modales azul */
[data-theme="blue"] .modal-content, [data-theme="blue"] .modal-content-modern {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.18) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .modal-header { border-bottom: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .modal-footer { border-top: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .modal-content .modal-title, [data-theme="blue"] .modal-content-modern .modal-title { color: #dce8ff !important; }
[data-theme="blue"] .modal-content *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .modal-content-modern *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #dce8ff !important;
}
/* Botón cerrar azul */
[data-theme="blue"] .btn-close-white { filter: none !important; opacity: 0.7 !important; color: #93c5fd !important; background-color: transparent; }
[data-theme="blue"] .modal .btn-close,
[data-theme="blue"] .modal-content .btn-close,
[data-theme="blue"] .modal-content-modern .btn-close,
[data-theme="blue"] .btn-close { filter: none !important; opacity: 0.7 !important; color: #93c5fd !important; background-color: transparent; }
[data-theme="blue"] .modal .btn-close:hover,
[data-theme="blue"] .modal-content .btn-close:hover,
[data-theme="blue"] .modal-content-modern .btn-close:hover,
[data-theme="blue"] .btn-close:hover { opacity: 1 !important; transform: rotate(90deg) scale(1.1); background-color: rgba(10,20,40,0.5) !important; }
[data-theme="blue"] .swal2-close { color: #93c5fd !important; }
[data-theme="blue"] .swal2-close:hover { background-color: rgba(10,20,40,0.5); }
[data-theme="blue"] .btn-close-custom {
    border-color: rgba(59,130,246,0.5) !important;
    color: #93c5fd !important;
    background: transparent !important;
    opacity: 0.9;
}
[data-theme="blue"] .btn-close-custom:hover {
    background-color: #ef4444 !important;
    border-color: #ef4444 !important;
    color: #ffffff !important;
    opacity: 1;
}
/* SweetAlert azul */
[data-theme="blue"] .swal2-popup { background: #1a3050 !important; color: #dce8ff !important; }
[data-theme="blue"] .swal2-title { color: #dce8ff !important; }
[data-theme="blue"] .swal2-popup *:not(i):not(button):not(.btn):not(.badge):not(.importe-preview):not(.swal2-icon):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #dce8ff !important;
}
[data-theme="blue"] .swal2-input, [data-theme="blue"] .swal2-textarea {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.25) !important;
    color: #dce8ff !important;
}
/* ColVis DataTables azul */
[data-theme="blue"] .dt-button-collection {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.18) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.4) !important;
}
[data-theme="blue"] .dt-button-collection .dt-button { color: #dce8ff !important; }
[data-theme="blue"] .dt-button-collection .dt-button:hover { background: rgba(10,20,40,0.5) !important; color: #dce8ff !important; }
[data-theme="blue"] .dt-button-collection .dt-button.active { background: rgba(59,130,246,0.2) !important; color: var(--accent) !important; }
[data-theme="blue"] .dt-button-collection .dt-button-collection-title { color: #6a9bd4 !important; border-bottom: 0.0625rem solid rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .buttons-colvis { background: rgba(130,190,255,0.06) !important; border: 0.0625rem solid rgba(130,190,255,0.18) !important; color: #dce8ff !important; }
[data-theme="blue"] .buttons-colvis:hover { background: rgba(10,20,40,0.4) !important; border-color: var(--accent) !important; color: var(--accent) !important; }
/* Cards / contenedores azul */
[data-theme="blue"] .card { background: rgba(26,48,80,0.82) !important; color: #dce8ff !important; border: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .card-header { background: rgba(59,130,246,0.1) !important; border-bottom: 0.0625rem solid rgba(130,190,255,0.15) !important; color: #dce8ff !important; }
[data-theme="blue"] .card-body { color: #dce8ff !important; }
[data-theme="blue"] .stat-card { background: rgba(26,48,80,0.82) !important; color: #dce8ff !important; border: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .stat-card h3 { color: #dce8ff !important; }
[data-theme="blue"] .preview-card { color: #dce8ff !important; }
[data-theme="blue"] .info-row { color: #dce8ff !important; border-bottom: 0.0625rem solid rgba(130,190,255,0.1) !important; }
[data-theme="blue"] .selected-worker-card { color: #dce8ff !important; border: 0.0625rem solid rgba(59,130,246,0.3) !important; }
[data-theme="blue"] .selected-worker-card .worker-name { color: var(--accent) !important; }
[data-theme="blue"] .selected-worker-card .form-control { background: rgba(26,48,80,0.9) !important; border: 0.0625rem solid rgba(147,197,253,0.5) !important; color: #dce8ff !important; }
[data-theme="blue"] .selected-worker-card .text-muted,
[data-theme="blue"] .selected-worker-card span:not(.badge):not(.importe-preview) { color: #9dbde8 !important; }
[data-theme="blue"] .importe-preview { color: #6ee7b7 !important; }
[data-theme="blue"] .rango-dias-selector { background: rgba(59,130,246,0.06) !important; }
[data-theme="blue"] .rango-btn { background: rgba(59,130,246,0.08) !important; border: 0.0625rem solid rgba(130,190,255,0.15) !important; color: #9dbde8 !important; }
[data-theme="blue"] .rango-btn:hover { background: rgba(10,20,40,0.5) !important; border-color: var(--accent) !important; color: #dce8ff !important; }
[data-theme="blue"] .rango-btn.active { background: rgba(59,130,246,0.2) !important; border-color: var(--accent) !important; color: #dce8ff !important; }
[data-theme="blue"] .btn-agregar-mas { background: rgba(16,185,129,0.18) !important; border: 0.0625rem solid #10b981 !important; color: #6ee7b7 !important; }
[data-theme="blue"] .dias-num { color: #dce8ff !important; }
/* Modales propios azul */
[data-theme="blue"] .modal-content-win,
[data-theme="blue"] .modal-header-win,
[data-theme="blue"] .modal-footer-win { color: #dce8ff !important; }
[data-theme="blue"] .tipo-nomina-preview,
[data-theme="blue"] .tipo-nomina-dropdown,
[data-theme="blue"] .tipo-nomina-option { color: #dce8ff !important; }
[data-theme="blue"] .card-option-descuento { color: #dce8ff !important; border-color: rgba(130,190,255,0.2) !important; }
[data-theme="blue"] .worker-list-container, [data-theme="blue"] .bono-list-container {
    background: rgba(59,130,246,0.06) !important; border: 0.0625rem solid rgba(130,190,255,0.12) !important;
}
[data-theme="blue"] .worker-item { border-bottom: 0.0625rem solid rgba(130,190,255,0.1) !important; }
[data-theme="blue"] .worker-item:hover { background: rgba(10,20,40,0.4) !important; }
[data-theme="blue"] .worker-item.selected { background: rgba(59,130,246,0.18) !important; border-left: 0.1875rem solid var(--accent-dark) !important; }
[data-theme="blue"] .worker-avatar { background: rgba(59,130,246,0.15) !important; color: var(--accent) !important; }
/* Dropdowns azul — fondo claro, hover oscuro */
[data-theme="blue"] .dropdown-menu-win {
    background: rgba(22,40,68,0.98) !important;
    border: 0.0625rem solid rgba(130,190,255,0.18) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.4) !important;
}
[data-theme="blue"] .dropdown-menu-win .dropdown-item { color: #dce8ff !important; transition: all 0.15s ease; }
[data-theme="blue"] .dropdown-menu-win .dropdown-item:hover { background: rgba(10,20,40,0.55) !important; color: var(--accent) !important; }
[data-theme="blue"] .dropdown-menu-win .dropdown-item.text-danger:hover { background: rgba(239,68,68,0.15) !important; color: #fca5a5 !important; }
[data-theme="blue"] .dropdown-divider { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; border-color: rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .dropdown-menu-win .dropdown-divider { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; border-color: rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .dropdown-menu-win .dropdown-item-text { color: #dce8ff !important; }
[data-theme="blue"] .dropdown-menu-win .dropdown-item-text small { color: #60a5fa !important; }
/* SweetAlert modal azul */
[data-theme="blue"] .swal2-popup {
    background: #1a3050 !important;
    color: #dce8ff !important;
    border: 0.0625rem solid rgba(130,190,255,0.15) !important;
}
/* Tooltip azul */
[data-theme="blue"] .tooltip .tooltip-inner { background: #1e3858 !important; color: #dce8ff !important; }
[data-theme="blue"] .tooltip .tooltip-arrow::before { border-top-color: #1e3858 !important; border-bottom-color: #1e3858 !important; border-left-color: #1e3858 !important; border-right-color: #1e3858 !important; }
/* Dropdown base azul */
[data-theme="blue"] .dropdown-menu { background: #1a3050 !important; color: #dce8ff !important; border: 0.0625rem solid rgba(130,190,255,0.18) !important; }
[data-theme="blue"] .dropdown-item { color: #dce8ff !important; }
[data-theme="blue"] .dropdown-item:hover { background: rgba(10,20,40,0.55) !important; color: var(--accent) !important; }
[data-theme="blue"] .dropdown-item-text { color: #dce8ff !important; }
/* Dropdown export azul */
[data-theme="blue"] .btn-export-dropdown {
    background: rgba(130,190,255,0.06) !important;
    border: 0.0625rem solid rgba(130,190,255,0.2) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .btn-export-dropdown:hover {
    background: rgba(10,20,40,0.4) !important;
    border-color: var(--accent) !important;
    color: var(--accent) !important;
}
[data-theme="blue"] .dropdown-export-menu {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.18) !important;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.4) !important;
}
[data-theme="blue"] .dropdown-export-item { color: #dce8ff !important; }
[data-theme="blue"] .dropdown-export-item:hover { background: rgba(10,20,40,0.55) !important; color: var(--accent) !important; }
[data-theme="blue"] .dropdown-export-item { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .dropdown-export-item:first-child { border-top: none !important; }
/* Combos dark-select azul */
[data-theme="blue"] .dark-select {
    background-color: #1a3050 !important;
    border: 0.0625rem solid rgba(130,190,255,0.25) !important;
    color: #dce8ff !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2393c5fd' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.875rem center !important;
    background-size: 0.875rem 0.75rem !important;
    padding-right:2.5rem !important;
}
[data-theme="blue"] .dark-select:hover { border-color: var(--accent) !important; background-color: rgba(10,20,40,0.5) !important; }
[data-theme="blue"] .dark-select:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 0.1875rem rgba(59,130,246,0.25) !important; }
[data-theme="blue"] .dark-select option { background: #1a3050 !important; color: #dce8ff !important; }
[data-theme="blue"] .dark-select::placeholder { color: #6a9bd4 !important; }
[data-theme="blue"] .filter-label { color: #9dbde8 !important; }
[data-theme="blue"] .filter-label i { color: var(--accent) !important; }
[data-theme="blue"] input::placeholder,
[data-theme="blue"] textarea::placeholder { color: #6a9bd4 !important; }
/* Iconos fondo cards azul */
[data-theme="blue"] .glass-card .card-icon-bg { opacity: 0.12 !important; }
[data-theme="blue"] .info-item .card-icon-bg { opacity: 0.1 !important; }
/* Selector nomina azul */
[data-theme="blue"] .tipo-nomina-preview {
    background: rgba(26,48,80,0.92) !important;
    border: 0.0625rem solid rgba(59,130,246,0.4) !important;
    color: #dce8ff !important;
}
[data-theme="blue"] .tipo-nomina-preview:hover {
    border-color: var(--accent) !important;
    background: rgba(10,20,40,0.5) !important;
}
[data-theme="blue"] .tipo-nomina-preview i:last-child { color: #6a9bd4 !important; }
[data-theme="blue"] .tipo-nomina-dropdown {
    background: #1a3050 !important;
    border: 0.0625rem solid rgba(59,130,246,0.3) !important;
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.4) !important;
}
[data-theme="blue"] .tipo-nomina-option { color: #dce8ff !important; }
[data-theme="blue"] .tipo-nomina-option:hover { background: rgba(10,20,40,0.4) !important; color: var(--accent) !important; }
[data-theme="blue"] .tipo-nomina-select option { background: #1a3050 !important; color: #dce8ff !important; }
/* Checkboxes azul */
[data-theme="blue"] .form-check-input {
    background-color: #1a3050 !important;
    border-color: rgba(130,190,255,0.35) !important;
    accent-color: var(--accent) !important;
}
[data-theme="blue"] .form-check-input:checked {
    background-color: var(--accent) !important;
    border-color: var(--accent) !important;
}
[data-theme="blue"] .form-check-label { color: #dce8ff !important; }
[data-theme="blue"] .card-checkbox { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; }
[data-theme="blue"] .card-checkbox label { color: #9dbde8 !important; }
[data-theme="blue"] .card-checkbox label:hover { color: var(--accent) !important; }
/* Sidebar textos azul */
[data-theme="blue"] .win-sidebar { color: #dce8ff !important; }
[data-theme="blue"] .win-sidebar .nav-item { color: rgba(180,210,255,0.78) !important; }
/* Tablas dark azul */
[data-theme="blue"] .table-dark {
    --bs-table-color:#dce8ff;
    --bs-table-bg:#1a3050;
    --bs-table-striped-bg:rgba(59,130,246,0.06);
    --bs-table-border-color:rgba(130,190,255,0.12);
    border-color: rgba(130,190,255,0.12);
    color: #dce8ff;
}
[data-theme="blue"] .table-dark thead th { background: rgba(59,130,246,0.18); }
[data-theme="blue"] table.dataTable.stripe > tbody > tr.odd > *,
[data-theme="blue"] table.dataTable.display > tbody > tr.odd > * { box-shadow: inset 0 0 0 624.9375rem rgba(59,130,246,0.05) !important; }
[data-theme="blue"] table.dataTable.hover > tbody > tr:hover > *,
[data-theme="blue"] table.dataTable.display > tbody > tr:hover > * { box-shadow: inset 0 0 0 624.9375rem rgba(10,20,40,0.45) !important; }
[data-theme="blue"] table.dataTable tbody tr.selected > * { box-shadow: inset 0 0 0 624.9375rem rgba(59,130,246,0.2) !important; color: #dce8ff !important; }
/* Títulos cards azul */
[data-theme="blue"] .card-title,
[data-theme="blue"] .modal-title,
[data-theme="blue"] .win-card-title,
[data-theme="blue"] h6.fw-semibold,
[data-theme="blue"] h5.fw-semibold,
[data-theme="blue"] .card-header-title { color: var(--accent) !important; font-weight: 700 !important; }
[data-theme="blue"] .card-header { background: rgba(59,130,246,0.1) !important; border-bottom: 0.0625rem solid rgba(130,190,255,0.15) !important; }
[data-theme="blue"] .stat-label,
[data-theme="blue"] .card-subtitle { color: #9dbde8 !important; }
/* Dropdown nombre completo azul */
[data-theme="blue"] .dropdown-menu-win .dropdown-item-text .fw-bold { color: #dce8ff !important; }
/* Separadores azul */
[data-theme="blue"] hr { border-top: 0.0625rem solid rgba(130,190,255,0.15) !important; opacity: 1 !important; }
[data-theme="blue"] .dropdown-divider { border-top: 0.0625rem solid rgba(130,190,255,0.12) !important; opacity: 1 !important; }
/* DataTables azul */
[data-theme="blue"] .dataTables_wrapper .dataTables_length select,
[data-theme="blue"] .dataTables_wrapper .dataTables_filter input { border: 0.0625rem solid rgba(130,190,255,0.25) !important; background-color: #1a3050 !important; color: #dce8ff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button { color: #dce8ff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { border: 0.0625rem solid rgba(130,190,255,0.2) !important; background: rgba(59,130,246,0.1) !important; color: #dce8ff !important; }
[data-theme="blue"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { border: 0.0625rem solid var(--accent) !important; background: rgba(10,20,40,0.5) !important; color: #dce8ff !important; }
/* Tablas dashboard colores inline azul */
[data-theme="blue"] [style*="color: #e2e8f0"],
[data-theme="blue"] [style*="color:#e2e8f0"] { color: #dce8ff !important; }
[data-theme="blue"] [style*="color: #94a3b8"],
[data-theme="blue"] [style*="color:#94a3b8"] { color: #9dbde8 !important; }
[data-theme="blue"] [style*="rgba(255,255,255,0.3)"] { color: #6a9bd4 !important; }
[data-theme="blue"] [style*="rgba(255,255,255,0.6)"] { color: #9dbde8 !important; }
[data-theme="blue"] [style*="border-top: 0.125rem solid rgba(255,255,255,0.1)"],
[data-theme="blue"] tfoot [style*="rgba(255,255,255,0.1)"] { border-top-color: rgba(130,190,255,0.15) !important; }
[data-theme="blue"] tfoot [style*="rgba(255,255,255,0.1)"] td { color: #dce8ff !important; }
/* Badge seccion azul */
[data-theme="blue"] .badge-win { background: #3b82f6; color: #ffffff; }
/* Tabs azul */
[data-theme="blue"] .tab-content { color: #dce8ff !important; }
[data-theme="blue"] .tab-pane { color: #dce8ff !important; }
[data-theme="blue"] .tab-pane .text-muted { color: #9dbde8 !important; }
/* Captura global texto azul */
[data-theme="blue"] .card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .glass-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .stat-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .modal-content-modern *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .swal2-popup *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .dropdown-menu-win *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .sidebar-profile *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="blue"] .win-sidebar .profile-info *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #dce8ff !important;
}
/* Focus mode azul */
[data-theme="blue"] .focus-tab {
    background:rgba(20,36,60,0.92);
    color:#9dbde8;
    box-shadow:0.125rem 0 0.625rem rgba(0,0,0,0.25);
    border-top:1px solid rgba(59,130,246,0.4);
    border-right:1px solid rgba(59,130,246,0.4);
    border-bottom:1px solid rgba(59,130,246,0.4);
}
[data-theme="blue"] .focus-tab:hover,
[data-theme="blue"] .focus-tab:focus-visible {
    color:#dce8ff;
}
[data-theme="blue"] .sidebar-close-focus {
    background:rgba(130,190,255,0.1);
    color:#9dbde8;
}
[data-theme="blue"] .sidebar-close-focus:hover,
[data-theme="blue"] .sidebar-close-focus:focus-visible {
    color:var(--accent);
}
/* ======================================================
   TEMA VERDE / NATURALEZA — verde oscuro natural
   ====================================================== */
[data-theme="verde"] body { background: #0f1f12 !important; color: #d4f0d8 !important; }
[data-theme="verde"] {
    --bg:#0f1f12;
    --panel:#162a18;
    --panel-2:#1a301e;
    --card:rgba(22,42,24,0.82);
    --card-hover:rgba(28,52,30,0.95);
    --border:rgba(120,200,130,0.18);
    --border-2:rgba(120,200,130,0.28);
    --txt:#d4f0d8;
    --muted:#8ec99a;
    --faint:#5ea86a;
    --shadow:0 0.75rem 2.125rem rgba(0,0,0,0.35);
    --win-sidebar-bg:rgba(8,18,10,0.97);
    --win-sidebar-border:rgba(120,200,130,0.12);
}
[data-theme="verde"] .win11-bg { background: linear-gradient(135deg, #0f1f12 0%, #162a18 50%, #1a301e 100%) !important; }
[data-theme="verde"] .win11-bg::before {
    background-image: radial-gradient(circle at 20% 80%, rgba(52,211,153,0.12) 0%, transparent 50%),
                      radial-gradient(circle at 80% 20%, rgba(52,211,153,0.08) 0%, transparent 50%) !important;
}
[data-theme="verde"] .glass-card {
    background: rgba(22,42,24,0.82) !important;
    border: 0.0625rem solid rgba(120,200,130,0.18) !important;
    color: #d4f0d8 !important;
}
[data-theme="verde"] .glass-card:hover {
    background: rgba(18,38,20,0.92) !important;
    border-color: rgba(52,211,153,0.35) !important;
}
[data-theme="verde"] .win-sidebar {
    background: rgba(8,18,10,0.97) !important;
    border-right: 0.0625rem solid rgba(120,200,130,0.12) !important;
}
[data-theme="verde"] .sidebar-footer { border-top: 0.0625rem solid rgba(120,200,130,0.12) !important; }
[data-theme="verde"] .sidebar-logo { border-bottom: 0.0625rem solid rgba(120,200,130,0.1) !important; }
[data-theme="verde"] .sidebar-logo small { color: rgba(120,200,130,0.5) !important; }
[data-theme="verde"] .nav-item { background: transparent !important; color: rgba(180,230,185,0.78) !important; }
[data-theme="verde"] .nav-item:hover { background: rgba(5,25,10,0.65) !important; color: var(--accent) !important; border-color: rgba(52,211,153,0.2) !important; }
[data-theme="verde"] .nav-item:hover i { color: var(--accent) !important; }
[data-theme="verde"] .nav-item.active { background: var(--accent) !important; color: #fff !important; border-color: transparent !important; border-radius:0.5rem !important; }
[data-theme="verde"] .nav-item.active i { color: #fff !important; }
[data-theme="verde"] .nav-item.active .sidebar-text { color: #fff !important; }
[data-theme="verde"] .nav-item.active .nav-badge { background: rgba(255,255,255,0.25) !important; color: #fff !important; border-color: rgba(255,255,255,0.3) !important; }
[data-theme="verde"] .nav-item.active .nav-group-chevron { color: #fff !important; }
[data-theme="verde"] .main-container { color: #d4f0d8 !important; }
[data-theme="verde"] .win-topbar {
    background: rgba(12,28,14,0.85) !important;
    border-bottom: 0.0625rem solid rgba(120,200,130,0.15) !important;
    color: #d4f0d8 !important;
}
[data-theme="verde"] .page-title h1 { color: #d4f0d8 !important; }
[data-theme="verde"] .page-title p { color: #8ec99a !important; }
[data-theme="verde"] .sidebar-toggle { color: #8ec99a !important; }
[data-theme="verde"] .sidebar-toggle:hover { background: rgba(5,25,10,0.5) !important; color: var(--accent) !important; }
[data-theme="verde"] .badge { background: rgba(52,211,153,0.2) !important; color: #6ee7b7 !important; }
[data-theme="verde"] .badge.bg-success { background: #059669 !important; color: #ffffff !important; }
[data-theme="verde"] .badge-codigo { background: rgba(52,211,153,0.2) !important; color: #6ee7b7 !important; }
[data-theme="verde"] .dropdown-menu-win {
    background: rgba(16,36,18,0.98) !important;
    border: 0.0625rem solid rgba(120,200,130,0.2) !important;
}
[data-theme="verde"] .dropdown-menu-win .dropdown-item { color: rgba(212,240,216,0.88) !important; }
[data-theme="verde"] .dropdown-menu-win .dropdown-item:hover { background: rgba(5,25,10,0.55) !important; color: var(--accent) !important; }
[data-theme="verde"] .dropdown-menu-win .dropdown-divider { border-top-color: rgba(120,200,130,0.12) !important; }
[data-theme="verde"] hr { border-top: 0.0625rem solid rgba(120,200,130,0.15) !important; opacity: 1 !important; }
[data-theme="verde"] .dropdown-divider { border-top: 0.0625rem solid rgba(120,200,130,0.12) !important; opacity: 1 !important; }
[data-theme="verde"] .btn-win-primary { background: linear-gradient(135deg, var(--accent), var(--accent-dark)) !important; color: #fff !important; }
[data-theme="verde"] .btn-win-primary:hover { filter: brightness(0.85) !important; }
[data-theme="verde"] .btn-win:not(.btn-win-primary) { border-color: rgba(120,200,130,0.3) !important; color: #6ee7b7 !important; }
[data-theme="verde"] .btn-win:hover:not(.btn-win-primary) { background: rgba(5,25,10,0.5) !important; border-color: var(--accent) !important; color: var(--accent) !important; }
[data-theme="verde"] .btn-win-sm:hover:not(.btn-win-primary) { background: rgba(5,25,10,0.4) !important; color: #6ee7b7 !important; }
[data-theme="verde"] .card-header-title { color: var(--accent-dark) !important; font-weight: 700 !important; }
[data-theme="verde"] .card-header { background: rgba(52,211,153,0.1) !important; border-bottom: 0.0625rem solid rgba(120,200,130,0.15) !important; }
[data-theme="verde"] .stat-label,
[data-theme="verde"] .card-subtitle { color: #8ec99a !important; }
[data-theme="verde"] .dropdown-menu-win .dropdown-item-text .fw-bold { color: #d4f0d8 !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_length select,
[data-theme="verde"] .dataTables_wrapper .dataTables_filter input { border: 0.0625rem solid rgba(120,200,130,0.25) !important; background-color: #162a18 !important; color: #d4f0d8 !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button { color: #d4f0d8 !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { border: 0.0625rem solid rgba(120,200,130,0.2) !important; background: rgba(52,211,153,0.1) !important; color: #d4f0d8 !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { border: 0.0625rem solid var(--accent) !important; background: rgba(5,25,10,0.5) !important; color: #d4f0d8 !important; }
[data-theme="verde"] .badge-win { background: #10b981; color: #ffffff; }
[data-theme="verde"] .tab-content { color: #d4f0d8 !important; }
[data-theme="verde"] .tab-pane { color: #d4f0d8 !important; }
[data-theme="verde"] .tab-pane .text-muted { color: #8ec99a !important; }
[data-theme="verde"] [style*="color: #e2e8f0"],
[data-theme="verde"] [style*="color:#e2e8f0"] { color: #d4f0d8 !important; }
[data-theme="verde"] [style*="color: #94a3b8"],
[data-theme="verde"] [style*="color:#94a3b8"] { color: #8ec99a !important; }
[data-theme="verde"] [style*="rgba(255,255,255,0.3)"] { color: #5ea86a !important; }
[data-theme="verde"] [style*="rgba(255,255,255,0.6)"] { color: #8ec99a !important; }
[data-theme="verde"] [style*="border-top: 0.125rem solid rgba(255,255,255,0.1)"],
[data-theme="verde"] tfoot [style*="rgba(255,255,255,0.1)"] { border-top-color: rgba(120,200,130,0.15) !important; }
[data-theme="verde"] tfoot [style*="rgba(255,255,255,0.1)"] td { color: #d4f0d8 !important; }
[data-theme="verde"] .card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .glass-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .stat-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .modal-content-modern *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .swal2-popup *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .dropdown-menu-win *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .sidebar-profile *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="verde"] .win-sidebar .profile-info *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #d4f0d8 !important;
}
[data-theme="verde"] .focus-tab {
    background:rgba(12,28,14,0.92);
    color:#8ec99a;
    box-shadow:0.125rem 0 0.625rem rgba(0,0,0,0.25);
    border-top:1px solid rgba(52,211,153,0.4);
    border-right:1px solid rgba(52,211,153,0.4);
    border-bottom:1px solid rgba(52,211,153,0.4);
}
[data-theme="verde"] .focus-tab:hover,
[data-theme="verde"] .focus-tab:focus-visible {
    color:#d4f0d8;
}
[data-theme="verde"] .sidebar-close-focus {
    background:rgba(120,200,130,0.1);
    color:#8ec99a;
}
[data-theme="verde"] .sidebar-close-focus:hover,
[data-theme="verde"] .sidebar-close-focus:focus-visible {
    color:var(--accent);
}
/* ===== VERDE: TABLAS / DATATABLES ===== */
[data-theme="verde"] .table,
[data-theme="verde"] .table-dark,
[data-theme="verde"] .table > :not(caption) > * > *,
[data-theme="verde"] .table-dark > :not(caption) > * > * {
    --bs-table-bg:#162a18;
    --bs-table-color:#d4f0d8;
    --bs-table-border-color:rgba(120,200,130,0.14);
    --bs-table-striped-bg:rgba(52,211,153,0.05);
    --bs-table-striped-color:#d4f0d8;
    --bs-table-active-bg:rgba(52,211,153,0.14);
    --bs-table-active-color:#eafbe9;
    --bs-table-hover-bg:rgba(52,211,153,0.12);
    --bs-table-hover-color:#eafbe9;
}
[data-theme="verde"] .table thead th,
[data-theme="verde"] .table-dark thead th,
[data-theme="verde"] table.dataTable thead th,
[data-theme="verde"] .table-custom th {
    background:rgba(52,211,153,0.1) !important;
    color:#6ee7b7 !important;
    border-bottom:0.0938rem solid rgba(52,211,153,0.35) !important;
}
[data-theme="verde"] .table tbody tr:hover td,
[data-theme="verde"] .table tbody tr:hover th,
[data-theme="verde"] .dataTable tbody tr:hover td,
[data-theme="verde"] .dataTable tbody tr:hover th {
    background-color:rgba(52,211,153,0.12) !important;
    color:#eafbe9 !important;
}
[data-theme="verde"] table.dataTable > tbody > tr:hover > * {
    box-shadow:inset 0 0 0 62rem rgba(52,211,153,0.12) !important;
    color:#eafbe9 !important;
}
[data-theme="verde"] table.dataTable > tbody > tr.selected > *,
[data-theme="verde"] .table tbody tr.selected td { background-color:rgba(52,211,153,0.22) !important; color:#ffffff !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_info,
[data-theme="verde"] .dataTables_wrapper .dataTables_length,
[data-theme="verde"] .dataTables_wrapper .dataTables_filter,
[data-theme="verde"] .dataTables_wrapper .dataTables_filter label { color:#8ec99a !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_length select,
[data-theme="verde"] .dataTables_wrapper .dataTables_filter input {
    background:#101f13 !important; color:#d4f0d8 !important;
    border:0.0625rem solid rgba(120,200,130,0.25) !important;
}
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button { color:#8ec99a !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { background:var(--accent) !important; color:#06280f !important; border-color:var(--accent) !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:rgba(52,211,153,0.15) !important; color:#6ee7b7 !important; }
[data-theme="verde"] .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { color:#5ea86a !important; }
[data-theme="verde"] .dt-buttons .btn,
[data-theme="verde"] .buttons-colvis { color:#8ec99a !important; border-color:rgba(120,200,130,0.3) !important; }
[data-theme="verde"] .dt-buttons .btn:hover,
[data-theme="verde"] .buttons-colvis:hover { background:rgba(52,211,153,0.14) !important; color:#6ee7b7 !important; }
/* ===== VERDE: TABS ===== */
[data-theme="verde"] .clasificador-btn { color:rgba(212,240,216,0.78) !important; }
[data-theme="verde"] .clasificador-btn:hover { background:rgba(52,211,153,0.12) !important; color:var(--accent) !important; }
[data-theme="verde"] .clasificador-btn.active { background:var(--accent) !important; color:#06280f !important; }
[data-theme="verde"] .nav-tabs-modern .nav-link { color:#8ec99a !important; border-bottom-color:transparent !important; }
[data-theme="verde"] .nav-tabs-modern .nav-link:hover { color:#6ee7b7 !important; background:rgba(52,211,153,0.08) !important; }
[data-theme="verde"] .nav-tabs-modern .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:rgba(52,211,153,0.1) !important; }
[data-theme="verde"] .tabs-prof .nav-link { color:#8ec99a !important; }
[data-theme="verde"] .tabs-prof .nav-link.active { color:var(--accent) !important; border-color:rgba(52,211,153,0.35) !important; background:rgba(52,211,153,0.1) !important; }
/* ===== VERDE: BOTONES DE ÉNFASIS (no semánticos) ===== */
[data-theme="verde"] .btn-win-primary,
[data-theme="verde"] .btn-win-primary:hover { background:linear-gradient(135deg,var(--accent),#059669) !important; color:#ffffff !important; }
[data-theme="verde"] .btn-outline-secondary,
[data-theme="verde"] .btn-outline-primary { color:#8ec99a !important; border-color:rgba(120,200,130,0.45) !important; }
[data-theme="verde"] .btn-outline-secondary:hover,
[data-theme="verde"] .btn-outline-primary:hover { background:rgba(52,211,153,0.15) !important; color:#6ee7b7 !important; }
[data-theme="verde"] .btn-primary-glass { background:linear-gradient(135deg,var(--accent),#059669) !important; }
[data-theme="verde"] .btn-primary-glass:hover { box-shadow:0 0.25rem 0.9375rem rgba(52,211,153,0.3) !important; }
[data-theme="verde"] .btn-exportar.dbf { background:linear-gradient(135deg,var(--accent),#059669) !important; }
[data-theme="verde"] .scroll-quick-btn { background:linear-gradient(135deg,var(--accent),#059669) !important; }
[data-theme="verde"] .ventana-cuadre-btn-primary { background:linear-gradient(135deg,var(--accent),#059669) !important; }
[data-theme="verde"] .rango-btn:hover { background:rgba(52,211,153,0.15) !important; border-color:var(--accent) !important; color:#6ee7b7 !important; }
[data-theme="verde"] .rango-btn.active { background:var(--accent) !important; border-color:var(--accent) !important; color:#06280f !important; }
[data-theme="verde"] .cuadre-export-btn--imprimir,
[data-theme="verde"] .cuadre-export-btn--docx { border-color:rgba(52,211,153,0.45) !important; background:rgba(52,211,153,0.1) !important; color:#6ee7b7 !important; }
/* ======================================================
   TEMA ORGULLO — púrpura semiclaro
   ====================================================== */
[data-theme="orgullo"] body { background: #f4eefb !important; color: #33264d !important; }
[data-theme="orgullo"] {
    --bg:#f4eefb;
    --panel:#ece2f8;
    --panel-2:#e7dcf4;
    --card:rgba(255,255,255,0.78);
    --card-hover:rgba(255,255,255,0.92);
    --border:rgba(139,92,246,0.18);
    --border-2:rgba(139,92,246,0.3);
    --txt:#33264d;
    --muted:#6d5b91;
    --faint:#9b87c4;
    --shadow:0 0.75rem 2.125rem rgba(76,29,149,0.12);
    --win-sidebar-bg:rgba(250,247,254,0.97);
    --win-sidebar-border:rgba(139,92,246,0.12);
}
[data-theme="orgullo"] .win11-bg { background: linear-gradient(135deg, #f2ebfa 0%, #ece2f8 50%, #f8f5fd 100%) !important; }
[data-theme="orgullo"] .win11-bg::before {
    background-image: radial-gradient(circle at 20% 80%, rgba(139,92,246,0.10) 0%, transparent 50%),
                      radial-gradient(circle at 80% 20%, rgba(139,92,246,0.06) 0%, transparent 50%) !important;
}
[data-theme="orgullo"] .glass-card {
    background: rgba(255,255,255,0.78) !important;
    border: 0.0625rem solid rgba(139,92,246,0.16) !important;
    color: #33264d !important;
}
[data-theme="orgullo"] .glass-card:hover {
    background: rgba(255,255,255,0.92) !important;
    border-color: rgba(139,92,246,0.32) !important;
}
[data-theme="orgullo"] .win-sidebar {
    background: rgba(250,247,254,0.97) !important;
    border-right: 0.0625rem solid rgba(139,92,246,0.14) !important;
}
[data-theme="orgullo"] .sidebar-footer { border-top: 0.0625rem solid rgba(139,92,246,0.12) !important; }
[data-theme="orgullo"] .sidebar-logo { border-bottom: 0.0625rem solid rgba(139,92,246,0.1) !important; }
[data-theme="orgullo"] .sidebar-logo small { color: rgba(109,91,145,0.55) !important; }
[data-theme="orgullo"] .nav-item { background: transparent !important; color: rgba(51,38,77,0.75) !important; }
[data-theme="orgullo"] .nav-item:hover { background: rgba(139,92,246,0.07) !important; color: var(--accent-dark) !important; border-color: rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .nav-item:hover i { color: var(--accent-dark) !important; }
[data-theme="orgullo"] .nav-item.active { background: var(--accent) !important; color: #fff !important; border-color: transparent !important; border-radius:0.5rem !important; }
[data-theme="orgullo"] .nav-item.active i { color: #fff !important; }
[data-theme="orgullo"] .nav-item.active .sidebar-text { color: #fff !important; }
[data-theme="orgullo"] .nav-item.active .nav-badge { background: rgba(255,255,255,0.25) !important; color: #fff !important; border-color: rgba(255,255,255,0.3) !important; }
[data-theme="orgullo"] .nav-item.active .nav-group-chevron { color: #fff !important; }
[data-theme="orgullo"] .main-container { color: #33264d !important; }
[data-theme="orgullo"] .win-topbar {
    background: rgba(253,251,255,0.85) !important;
    border-bottom: 0.0625rem solid rgba(139,92,246,0.15) !important;
    color: #33264d !important;
}
[data-theme="orgullo"] .page-title h1 { color: #33264d !important; }
[data-theme="orgullo"] .page-title p { color: #7c3aed !important; }
[data-theme="orgullo"] .sidebar-toggle { color: #7c3aed !important; }
[data-theme="orgullo"] .sidebar-toggle:hover { background: rgba(139,92,246,0.08) !important; color: var(--accent-dark) !important; }
[data-theme="orgullo"] .badge { background: rgba(139,92,246,0.13) !important; color: #6d28d9 !important; }
[data-theme="orgullo"] .badge.bg-success { background: #059669 !important; color: #ffffff !important; }
[data-theme="orgullo"] .badge-codigo { background: rgba(139,92,246,0.13) !important; color: #6d28d9 !important; }
[data-theme="orgullo"] .dropdown-menu-win {
    background: rgba(253,251,255,0.98) !important;
    border: 0.0625rem solid rgba(139,92,246,0.2) !important;
}
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item { color: rgba(51,38,77,0.85) !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item:hover { background: rgba(139,92,246,0.08) !important; color: var(--accent-dark) !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-divider { border-top-color: rgba(139,92,246,0.14) !important; }
[data-theme="orgullo"] hr { border-top: 0.0625rem solid rgba(139,92,246,0.15) !important; opacity: 1 !important; }
[data-theme="orgullo"] .dropdown-divider { border-top: 0.0625rem solid rgba(139,92,246,0.14) !important; opacity: 1 !important; }
[data-theme="orgullo"] .btn-win-primary { background: linear-gradient(135deg, var(--accent), var(--accent-dark)) !important; color: #fff !important; }
[data-theme="orgullo"] .btn-win-primary:hover { filter: brightness(0.95) !important; }
[data-theme="orgullo"] .btn-win:not(.btn-win-primary) { border-color: rgba(139,92,246,0.35) !important; color: #6d28d9 !important; }
[data-theme="orgullo"] .btn-win:hover:not(.btn-win-primary) { background: rgba(139,92,246,0.08) !important; border-color: var(--accent) !important; color: var(--accent-dark) !important; }
[data-theme="orgullo"] .btn-win-sm:hover:not(.btn-win-primary) { background: rgba(139,92,246,0.08) !important; color: #6d28d9 !important; }
[data-theme="orgullo"] .card-header-title { color: var(--accent-dark) !important; font-weight: 700 !important; }
[data-theme="orgullo"] .card-collapse-title { color: var(--accent-dark) !important; font-weight: 700 !important; }
[data-theme="orgullo"] .card-collapse-title:hover { color: var(--accent) !important; }
[data-theme="orgullo"] .card-header { background: rgba(139,92,246,0.06) !important; border-bottom: 0.0625rem solid rgba(139,92,246,0.14) !important; }
[data-theme="orgullo"] .stat-label,
[data-theme="orgullo"] .card-subtitle { color: #6d5b91 !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item-text .fw-bold { color: #33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_length select,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_filter input { border: 0.0625rem solid rgba(139,92,246,0.28) !important; background-color: #ffffff !important; color: #33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button { color: #493a66 !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { border: 0.0625rem solid rgba(139,92,246,0.25) !important; background: rgba(139,92,246,0.09) !important; color: #33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { border: 0.0625rem solid var(--accent) !important; background: rgba(139,92,246,0.08) !important; color: #33264d !important; }
[data-theme="orgullo"] .badge-win { background: #10b981; color: #ffffff; }
[data-theme="orgullo"] .tab-content { color: #33264d !important; }
[data-theme="orgullo"] .tab-pane { color: #33264d !important; }
[data-theme="orgullo"] .tab-pane .text-muted { color: #6d5b91 !important; }
[data-theme="orgullo"] [style*="color: #e2e8f0"],
[data-theme="orgullo"] [style*="color:#e2e8f0"] { color: #33264d !important; }
[data-theme="orgullo"] [style*="color: #94a3b8"],
[data-theme="orgullo"] [style*="color:#94a3b8"] { color: #6d5b91 !important; }
[data-theme="orgullo"] [style*="rgba(255,255,255,0.3)"] { color: #9b7fd4 !important; }
[data-theme="orgullo"] [style*="rgba(255,255,255,0.6)"] { color: #6d5b91 !important; }
[data-theme="orgullo"] [style*="border-top: 0.125rem solid rgba(255,255,255,0.1)"],
[data-theme="orgullo"] tfoot [style*="rgba(255,255,255,0.1)"] { border-top-color: rgba(139,92,246,0.16) !important; }
[data-theme="orgullo"] tfoot [style*="rgba(255,255,255,0.1)"] td { color: #33264d !important; }
[data-theme="orgullo"] .card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .glass-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .stat-card *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .modal-content-modern *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .swal2-popup *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .dropdown-menu-win *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .sidebar-profile *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title),
[data-theme="orgullo"] .win-sidebar .profile-info *:not(i):not(button):not(.btn):not(.btn-close):not(.badge):not(.importe-preview):not(.text-danger):not(.text-success):not(.text-warning):not(.text-info):not(.text-primary):not(.card-title):not(.modal-title):not(.card-collapse-title):not(.win-card-title):not(.card-header-title) {
    color: #33264d !important;
}
/* Focus mode orgullo */
[data-theme="orgullo"] .focus-tab {
    background:rgba(253,251,255,0.95);
    color:#7c3aed;
    box-shadow:0.125rem 0 0.625rem rgba(76,29,149,0.18);
    border-top:1px solid rgba(139,92,246,0.45);
    border-right:1px solid rgba(139,92,246,0.45);
    border-bottom:1px solid rgba(139,92,246,0.45);
}
[data-theme="orgullo"] .focus-tab:hover,
[data-theme="orgullo"] .focus-tab:focus-visible {
    color:#4c1d95;
}
[data-theme="orgullo"] .sidebar-close-focus {
    background:rgba(139,92,246,0.1);
    color:#7c3aed;
}
[data-theme="orgullo"] .sidebar-close-focus:hover,
[data-theme="orgullo"] .sidebar-close-focus:focus-visible {
    color:var(--accent-dark);
}
/* ===== ORGULLO: TABLAS / DATATABLES ===== */
[data-theme="orgullo"] .table-dark,
[data-theme="orgullo"] .table-dark > :not(caption) > * > *,
[data-theme="orgullo"] .table-dark th,
[data-theme="orgullo"] .table-dark td {
    --bs-table-bg:#fdfbff;
    --bs-table-color:#33264d;
    --bs-table-border-color:rgba(139,92,246,0.14);
    --bs-table-striped-bg:rgba(139,92,246,0.05);
    --bs-table-striped-color:#33264d;
    --bs-table-active-bg:rgba(139,92,246,0.12);
    --bs-table-active-color:#2a1f42;
    --bs-table-hover-bg:rgba(139,92,246,0.09);
    --bs-table-hover-color:#2a1f42;
    background-color:#fdfbff !important;
    color:#33264d !important;
    border-color:rgba(139,92,246,0.14) !important;
}
[data-theme="orgullo"] .table > :not(caption) > * > * {
    box-shadow: inset 0 0 0 624.9375rem var(--bs-table-bg-state, var(--bs-table-bg-type, var(--bs-table-bg))) !important;
    color:#33264d !important;
}
[data-theme="orgullo"] .table thead th,
[data-theme="orgullo"] table.dataTable thead th,
[data-theme="orgullo"] .dataTables_wrapper .dataTable thead th,
[data-theme="orgullo"] .data-table thead th {
    background:#ece2f8 !important;
    color:#2a1f42 !important;
    border-bottom:0.0625rem solid rgba(139,92,246,0.3) !important;
}
[data-theme="orgullo"] .table-dark thead th,
[data-theme="orgullo"] .table-dark thead td { background:#ece2f8 !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table-striped { --bs-table-striped-bg:rgba(139,92,246,0.045) !important; --bs-table-striped-color:#33264d !important; }
[data-theme="orgullo"] .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg-type:var(--bs-table-striped-bg) !important; --bs-table-color-type:var(--bs-table-striped-color) !important; }
[data-theme="orgullo"] .table-dark.table-striped > tbody > tr:nth-of-type(odd) > * { background-color:rgba(139,92,246,0.05) !important; color:#33264d !important; }
[data-theme="orgullo"] .table-dark tbody tr:hover > *,
[data-theme="orgullo"] .table-dark tbody tr:hover td { background-color:rgba(139,92,246,0.18) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table-dark tbody td.text-muted { color:#6d5b91 !important; }
[data-theme="orgullo"] table.dataTable.stripe > tbody > tr.odd > *,
[data-theme="orgullo"] table.dataTable.display > tbody > tr.odd > * { box-shadow: inset 0 0 0 624.9375rem rgba(139,92,246,0.045) !important; }
[data-theme="orgullo"] table.dataTable.hover > tbody > tr:hover > *,
[data-theme="orgullo"] table.dataTable.display > tbody > tr:hover > * { box-shadow: inset 0 0 0 624.9375rem rgba(139,92,246,0.18) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] table.dataTable tbody tr.selected > * { box-shadow: inset 0 0 0 624.9375rem rgba(139,92,246,0.18) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] table.dataTable tbody tr.odd td { background-color:rgba(139,92,246,0.03) !important; color:#33264d !important; }
[data-theme="orgullo"] table.dataTable tbody tr.even td { background-color:rgba(139,92,246,0.055) !important; color:#33264d !important; }
[data-theme="orgullo"] .table-custom { color:#33264d !important; }
[data-theme="orgullo"] .table-custom th,
[data-theme="orgullo"] .table-custom td { border-bottom:0.0625rem solid rgba(139,92,246,0.12) !important; color:#33264d !important; }
[data-theme="orgullo"] .table-custom th { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .table-custom thead tr th { background:rgba(236,226,248,0.96) !important; border-bottom:0.0625rem solid rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .table-custom tfoot td { background:rgba(236,226,248,0.96) !important; border-top:0.0625rem solid rgba(139,92,246,0.2) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table-custom tr:hover td { background:rgba(139,92,246,0.16) !important; box-shadow:inset 0 0 0 624.9375rem rgba(139,92,246,0.16) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table-prof thead th { background:#ece2f8 !important; color:#2a1f42 !important; border-bottom:0.125rem solid rgba(var(--accent-rgb),0.3) !important; }
[data-theme="orgullo"] .table-prof tfoot td { background:#ece2f8 !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table-prof tbody td { border-bottom-color:rgba(139,92,246,0.1) !important; }
[data-theme="orgullo"] .table-prof tbody tr:hover td { background:rgba(139,92,246,0.16) !important; box-shadow:inset 0 0 0 624.9375rem rgba(139,92,246,0.16) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] #tablaHistorialIndividual thead th,
[data-theme="orgullo"] #tablaHistorialIndividual thead tr:first-child th,
[data-theme="orgullo"] #tablaHistorialIndividual thead tr:last-child th { background:#ece2f8 !important; color:#2a1f42 !important; }
[data-theme="orgullo"] #rangosTable thead th,
[data-theme="orgullo"] #tasasTable thead th { background:#ece2f8 !important; color:#2a1f42 !important; }
[data-theme="orgullo"] #rangosTable td,
[data-theme="orgullo"] #tasasTable td { color:#33264d !important; }
[data-theme="orgullo"] #rangosTable tbody tr,
[data-theme="orgullo"] #tasasTable tbody tr { background:transparent !important; color:#33264d !important; }
[data-theme="orgullo"] #rangosTable tbody tr:hover,
[data-theme="orgullo"] #tasasTable tbody tr:hover { background:rgba(139,92,246,0.06) !important; }
[data-theme="orgullo"] .data-table-wrapper { border:0.0625rem solid rgba(139,92,246,0.14) !important; background:rgba(255,255,255,0.55) !important; }
[data-theme="orgullo"] .dataTables_wrapper { color:#33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_length,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_filter,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_info,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_processing { color:#5b4a80 !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_length select,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_filter input { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button { background:rgba(255,255,255,0.92) !important; border:0.0625rem solid rgba(139,92,246,0.25) !important; color:#33264d !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background:rgba(124,58,237,0.16) !important; border-color:var(--accent-dark) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button.current { background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#ffffff !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-right,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button .fa-chevron-left { font-size:0.8rem !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-right,
[data-theme="orgullo"] .dataTables_wrapper .dataTables_paginate .paginate_button.current .fa-chevron-left { color:#ffffff !important; }
[data-theme="orgullo"] .dt-button-collection { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.25) !important; box-shadow:0 0.5rem 2rem rgba(76,29,149,0.15) !important; }
[data-theme="orgullo"] .dt-button-collection .dt-button { color:#33264d !important; }
[data-theme="orgullo"] .dt-button-collection .dt-button:hover { background:rgba(124,58,237,0.14) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .dt-button-collection .dt-button.active { background:rgba(124,58,237,0.2) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .dt-button-collection .dt-button-collection-title { color:#6d5b91 !important; border-bottom:0.0625rem solid rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .buttons-colvis { background:rgba(139,92,246,0.08) !important; border:0.0625rem solid rgba(139,92,246,0.3) !important; color:#33264d !important; }
[data-theme="orgullo"] .buttons-colvis:hover { background:rgba(124,58,237,0.16) !important; border-color:var(--accent-dark) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] tfoot [style*="rgba(255,255,255,0.1)"] td { color:#2a1f42 !important; }
/* ===== ORGULLO: ALERTAS ===== */
[data-theme="orgullo"] .alert { border:0.0625rem solid rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .alert-success { background:#d1fae5 !important; border:0.0625rem solid #10b981 !important; color:#065f46 !important; }
[data-theme="orgullo"] .alert-info { background:#ede9fe !important; border:0.0625rem solid var(--accent) !important; color:#5b21b6 !important; }
[data-theme="orgullo"] .alert-warning { background:#fef3c7 !important; border:0.0625rem solid #f59e0b !important; color:#92400e !important; }
[data-theme="orgullo"] .alert-danger { background:#fee2e2 !important; border:0.0625rem solid #ef4444 !important; color:#991b1b !important; }
/* ===== ORGULLO: DROPDOWNS (hover más oscuro) ===== */
[data-theme="orgullo"] .dropdown-menu {
    --bs-dropdown-bg:#ffffff;
    --bs-dropdown-link-color:#33264d;
    --bs-dropdown-link-hover-bg:rgba(124,58,237,0.16);
    --bs-dropdown-link-hover-color:#4c1d95;
    --bs-dropdown-link-active-bg:rgba(124,58,237,0.28);
    --bs-dropdown-link-active-color:#3b1580;
    background:#ffffff !important;
    border:0.0625rem solid rgba(139,92,246,0.25) !important;
    box-shadow:0 0.5rem 2rem rgba(76,29,149,0.15) !important;
}
[data-theme="orgullo"] .dropdown-menu .dropdown-item { color:#33264d !important; transition:all 0.15s ease; }
[data-theme="orgullo"] .dropdown-menu-win {
    background:#ffffff !important;
    border:0.0625rem solid rgba(139,92,246,0.25) !important;
    box-shadow:0 0.5rem 2rem rgba(76,29,149,0.15) !important;
}
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item { color:#33264d !important; transition:all 0.15s ease; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item:hover { background:rgba(124,58,237,0.18) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item.text-danger:hover { background:rgba(239,68,68,0.14) !important; color:#b91c1c !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item-text { color:#33264d !important; }
[data-theme="orgullo"] .dropdown-menu-win .dropdown-item-text small { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-menu { background:#ffffff !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-item { color:#33264d !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-item:hover { background:rgba(124,58,237,0.2) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-item.text-danger { color:#dc2626 !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-item.text-danger:hover { background:rgba(239,68,68,0.14) !important; color:#b91c1c !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-divider { border-color:rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .perfil-acciones .dropdown-toggle::after { color:#5b4a80 !important; }
[data-theme="orgullo"] .dropdown-export-menu { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .dropdown-export-item { color:#33264d !important; }
[data-theme="orgullo"] .dropdown-export-item:hover { background:rgba(124,58,237,0.18) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .tipo-nomina-dropdown { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] .tipo-nomina-option { color:#33264d !important; }
[data-theme="orgullo"] .tipo-nomina-option:hover { background:rgba(124,58,237,0.16) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .tipo-nomina-preview { background:#f4eefb !important; color:#33264d !important; border-color:rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .nav-selector select option { background:#ffffff; color:#33264d; }
/* ===== ORGULLO: INPUTS / BUSCADORES ===== */
[data-theme="orgullo"] .input-group-text { background:#ece2f8 !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#5b21b6 !important; }
[data-theme="orgullo"] .input-group-text.bg-dark { background-color:#ece2f8 !important; border-color:rgba(139,92,246,0.35) !important; color:#5b21b6 !important; }
[data-theme="orgullo"] .input-group-text.text-white,
[data-theme="orgullo"] .input-group-text.text-info,
[data-theme="orgullo"] .input-group-text.text-nocturno { color:#5b21b6 !important; }
[data-theme="orgullo"] .input-group .form-control,
[data-theme="orgullo"] .input-group .form-select { background-color:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .input-group .btn { border-color:rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .search-box input { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .search-box input::placeholder { color:#9b87c4 !important; }
[data-theme="orgullo"] .search-box i { color:#9b87c4 !important; }
[data-theme="orgullo"] .dt-search .input-group-text { background-color:#ece2f8 !important; border-color:rgba(139,92,246,0.35) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .dt-search .form-control { background-color:#ffffff !important; border-color:rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .dt-search .btn-outline-secondary { border-color:rgba(139,92,246,0.35) !important; color:#5b4a80 !important; }
[data-theme="orgullo"] .form-control, [data-theme="orgullo"] .form-control-sm, [data-theme="orgullo"] .form-control-lg { background-color:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .form-select { background-color:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] option { background:#ffffff !important; color:#33264d !important; }
[data-theme="orgullo"] .dark-input,
[data-theme="orgullo"] .dark-textarea { background:#ffffff !important; border-color:rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .dark-input:focus,
[data-theme="orgullo"] .dark-textarea:focus { border-color:var(--accent-dark) !important; box-shadow:0 0 0 0.1875rem rgba(139,92,246,0.18) !important; }
[data-theme="orgullo"] .dark-select { background:#ffffff !important; border-color:rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .edit-input { background:rgba(255,255,255,0.95) !important; border:0.0625rem solid rgba(139,92,246,0.45) !important; color:#33264d !important; }
[data-theme="orgullo"] .dias-input, [data-theme="orgullo"] .selected-worker-card .dias-input { background:#ffffff !important; color:#33264d !important; }
[data-theme="orgullo"] input::placeholder, [data-theme="orgullo"] textarea::placeholder { color:#9b87c4 !important; }
[data-theme="orgullo"] .form-check-input { background-color:#ffffff; border-color:rgba(139,92,246,0.5); }
[data-theme="orgullo"] .form-check-input:checked { background-color:var(--accent); border-color:var(--accent-dark); }
[data-theme="orgullo"] .pass-toggle { background:rgba(139,92,246,0.1) !important; border-color:rgba(139,92,246,0.35) !important; color:#5b4a80 !important; }
[data-theme="orgullo"] .pass-toggle:hover { background:rgba(124,58,237,0.18) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .password-toggle { color:#6d5b91 !important; }
[data-theme="orgullo"] .password-toggle:hover { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .select2-container--default .select2-selection--single { background-color:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .select2-container--default .select2-selection--single .select2-selection__rendered { color:#33264d !important; }
[data-theme="orgullo"] .select2-container--default .select2-selection--single .select2-selection__arrow b { border-top-color:#6d5b91 !important; }
[data-theme="orgullo"] .select2-dropdown { background-color:#ffffff !important; border-color:rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] .select2-container--default .select2-search--dropdown .select2-search__field { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .select2-container--default .select2-results__option { color:#33264d !important; }
[data-theme="orgullo"] .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background:rgba(124,58,237,0.2) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .select2-container--default .select2-results__option[aria-selected="true"],
[data-theme="orgullo"] .select2-container--default .select2-results__option--selected { background:rgba(139,92,246,0.14) !important; color:#5b21b6 !important; }
/* ===== ORGULLO: LISTAS / INFO ===== */
[data-theme="orgullo"] .list-group { --bs-list-group-bg:#ffffff; --bs-list-group-color:#33264d; --bs-list-group-border-color:rgba(139,92,246,0.2); --bs-list-group-action-hover-bg:rgba(124,58,237,0.14); --bs-list-group-action-hover-color:#4c1d95; --bs-list-group-action-active-bg:rgba(124,58,237,0.22); --bs-list-group-action-active-color:#3b1580; }
[data-theme="orgullo"] .worker-item { background:rgba(255,255,255,0.85) !important; border-color:rgba(139,92,246,0.18) !important; color:#33264d !important; }
[data-theme="orgullo"] .worker-item:hover { background:rgba(124,58,237,0.1) !important; border-color:var(--accent) !important; }
[data-theme="orgullo"] .worker-item.selected { background:rgba(124,58,237,0.16) !important; border-color:var(--accent-dark) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .worker-name { color:#2a1f42 !important; }
[data-theme="orgullo"] .worker-detail { color:#6d5b91 !important; }
[data-theme="orgullo"] .worker-avatar { background:#ece2f8 !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .info-item { background:rgba(139,92,246,0.05) !important; border-color:rgba(139,92,246,0.16) !important; }
[data-theme="orgullo"] .info-item:hover { background:rgba(124,58,237,0.1) !important; border-color:rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .info-label { color:#6d5b91 !important; }
[data-theme="orgullo"] .info-value { color:#33264d !important; }
[data-theme="orgullo"] .info-value code { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .stat-box small { color:#6d5b91 !important; }
[data-theme="orgullo"] .stat-box .h4 { color:#2a1f42 !important; }
[data-theme="orgullo"] .profile-username { color:#5b4a80 !important; }
[data-theme="orgullo"] .modal-subtitle { color:#6d5b91 !important; }
[data-theme="orgullo"] .section-title { color:#2a1f42 !important; border-color:rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .section-title i { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .filter-label { color:#6d5b91 !important; }
[data-theme="orgullo"] label, [data-theme="orgullo"] .form-label { color:#33264d !important; }
[data-theme="orgullo"] .text-muted { color:#6d5b91 !important; }
[data-theme="orgullo"] .nav-tabs-modern .nav-link { color:#5b4a80 !important; }
[data-theme="orgullo"] .nav-tabs-modern .nav-link:hover { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .nav-tabs-modern .nav-link.active { background:rgba(124,58,237,0.14) !important; color:#4c1d95 !important; border-color:rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] h5.fw-semibold, [data-theme="orgullo"] h6.fw-semibold { color:#2a1f42 !important; }
[data-theme="orgullo"] .badge-borrador { background:rgba(245,158,11,0.16) !important; border:0.0625rem solid #f59e0b !important; color:#b45309 !important; }
[data-theme="orgullo"] .badge-contabilizado { background:rgba(16,185,129,0.16) !important; border:0.0625rem solid #10b981 !important; color:#047857 !important; }
/* ===== ORGULLO: MODALES ===== */
[data-theme="orgullo"] .modal-content, [data-theme="orgullo"] .modal-content-modern { background:#ffffff !important; color:#33264d !important; border:0.0625rem solid rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .modal-header { color:#2a1f42 !important; border-bottom-color:rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .modal-header h3, [data-theme="orgullo"] .modal-header h4, [data-theme="orgullo"] .modal-header h5 { color:#2a1f42 !important; }
[data-theme="orgullo"] .modal-footer { border-top-color:rgba(139,92,246,0.2) !important; }
/* ===== ORGULLO: VENTANA CUADRE (nominas.php) ===== */
[data-theme="orgullo"] .ventana-cuadre-overlay { background:rgba(46,16,101,0.4); }
[data-theme="orgullo"] .ventana-cuadre-win { background:#faf7fe; border:0.0625rem solid rgba(139,92,246,0.35); box-shadow:0 0.875rem 2.75rem rgba(76,29,149,0.25); }
[data-theme="orgullo"] .ventana-cuadre-titlebar { background:#ece2f8; color:#5b21b6; border-bottom-color:rgba(139,92,246,0.35); }
[data-theme="orgullo"] .ventana-cuadre-titlebar-icon { color:var(--accent-dark); }
[data-theme="orgullo"] .ventana-cuadre-close { color:#5b21b6; }
[data-theme="orgullo"] .ventana-cuadre-close:hover { background:#dc2626; color:#fff; }
[data-theme="orgullo"] .ventana-cuadre-body { background:#ffffff; color:#33264d; }
[data-theme="orgullo"] .ventana-cuadre-danger { color:#dc2626; }
[data-theme="orgullo"] .ventana-cuadre-list { border-color:#fecaca; background:#fee2e2; color:#991b1b; }
[data-theme="orgullo"] .ventana-cuadre-list-note { color:#b45309; }
[data-theme="orgullo"] .ventana-cuadre-warn { background:rgba(245,158,11,0.12); border-color:rgba(245,158,11,0.35); }
[data-theme="orgullo"] .ventana-cuadre-warn i { color:#d97706; }
[data-theme="orgullo"] .ventana-cuadre-warn span { color:#92400e; }
[data-theme="orgullo"] .ventana-cuadre-footer { background:#f4eefb; border-top-color:rgba(139,92,246,0.25); }
[data-theme="orgullo"] .ventana-cuadre-btn-ghost { background:#ece2f8; color:#33264d; border-color:rgba(139,92,246,0.3); }
[data-theme="orgullo"] .ventana-cuadre-btn-ghost:hover { background:rgba(124,58,237,0.2); color:#4c1d95; }
/* ===== ORGULLO: SCROLLBAR / TEXTOS INLINE ===== */
[data-theme="orgullo"] ::-webkit-scrollbar-track { background:rgba(139,92,246,0.06) !important; }
[data-theme="orgullo"] ::-webkit-scrollbar-thumb { background:rgba(139,92,246,0.28) !important; }
[data-theme="orgullo"] ::-webkit-scrollbar-thumb:hover { background:rgba(139,92,246,0.45) !important; }
[data-theme="orgullo"] [style*="color: #cbd5e1"], [data-theme="orgullo"] [style*="color:#cbd5e1"] { color:#493a66 !important; }
[data-theme="orgullo"] [style*="color: #93c5fd"], [data-theme="orgullo"] [style*="color:#93c5fd"],
[data-theme="orgullo"] [style*="color: #60a5fa"], [data-theme="orgullo"] [style*="color:#60a5fa"],
[data-theme="orgullo"] [style*="color: #a5b4fc"], [data-theme="orgullo"] [style*="#a5b4fc"] { color:#6d28d9 !important; }
[data-theme="orgullo"] [style*="#aeb9cf"] { color:#6d5b91 !important; }
[data-theme="orgullo"] [style*="#c4b5fd"] { color:#6d28d9 !important; }
[data-theme="orgullo"] [style*="border-top: 0.125rem solid rgba(255,255,255,0.1)"],
[data-theme="orgullo"] tfoot [style*="rgba(255,255,255,0.1)"] { border-top-color:rgba(139,92,246,0.2) !important; }
/* ===== ORGULLO: HOVER FILAS (Bootstrap table-hover + genérico) ===== */
[data-theme="orgullo"] .table-hover { --bs-table-hover-bg:rgba(139,92,246,0.18); --bs-table-hover-color:#2a1f42; }
[data-theme="orgullo"] .table-hover > tbody > tr:hover > * { --bs-table-accent-bg:rgba(139,92,246,0.18); box-shadow: inset 0 0 0 624.9375rem rgba(139,92,246,0.18) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .table tbody tr:hover td,
[data-theme="orgullo"] .table tbody tr:hover th { background-color:rgba(139,92,246,0.16) !important; box-shadow:inset 0 0 0 624.9375rem rgba(139,92,246,0.16) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] .dataTable tbody tr:hover td,
[data-theme="orgullo"] .dataTable tbody tr:hover th { background-color:rgba(139,92,246,0.16) !important; box-shadow:inset 0 0 0 624.9375rem rgba(139,92,246,0.16) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] table.dataTable > tbody > tr:hover > *,
[data-theme="orgullo"] table.table > tbody > tr:hover > * { box-shadow:inset 0 0 0 624.9375rem rgba(139,92,246,0.18) !important; color:#2a1f42 !important; }
/* ===== ORGULLO: ALERT-SUCCESS MÁS VISIBLE ===== */
[data-theme="orgullo"] .alert-success { background:#a7f3d0 !important; border:0.0625rem solid #059669 !important; color:#064e3b !important; }
/* ===== ORGULLO: BADGES ESTADO LABORAL (empleados.php) ===== */
html[data-theme="orgullo"] .badge.badge-estado-activo { background:rgba(4,120,87,0.16) !important; color:#047857 !important; border:0.0625rem solid rgba(4,120,87,0.5) !important; }
html[data-theme="orgullo"] .badge.badge-estado-inactivo { background:rgba(185,28,28,0.14) !important; color:#b91c1c !important; border:0.0625rem solid rgba(185,28,28,0.45) !important; }
[data-theme="orgullo"] .badge.bg-success, [data-theme="orgullo"] .badge.text-bg-success { background:#059669 !important; color:#ffffff !important; }
[data-theme="orgullo"] .badge.bg-danger, [data-theme="orgullo"] .badge.text-bg-danger { background:#dc2626 !important; color:#ffffff !important; }
[data-theme="orgullo"] .badge.bg-warning, [data-theme="orgullo"] .badge.text-bg-warning { background:#d97706 !important; color:#ffffff !important; }
[data-theme="orgullo"] .badge.bg-info, [data-theme="orgullo"] .badge.text-bg-info { background:#0891b2 !important; color:#ffffff !important; }
[data-theme="orgullo"] .badge.bg-primary, [data-theme="orgullo"] .badge.text-bg-primary { background:var(--accent-dark) !important; color:#ffffff !important; }
/* ===== ORGULLO: MODAL TARJETA SNC-225 (snc225.php) ===== */
[data-theme="orgullo"] #modalTarjeta .modal-content { background:#f4eefb !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] #modalTarjeta .modal-header { background:#ece2f8 !important; border-bottom:0.0625rem solid rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] #modalTarjeta .modal-title,
[data-theme="orgullo"] #modalTarjeta .modal-body,
[data-theme="orgullo"] #modalTarjeta td, [data-theme="orgullo"] #modalTarjeta th,
[data-theme="orgullo"] #modalTarjeta label, [data-theme="orgullo"] #modalTarjeta p,
[data-theme="orgullo"] #modalTarjeta span:not(.badge):not(.badge-borrador):not(.badge-contabilizado),
[data-theme="orgullo"] #modalTarjeta strong, [data-theme="orgullo"] #modalTarjeta h5, [data-theme="orgullo"] #modalTarjeta h6 { color:#33264d !important; }
[data-theme="orgullo"] #modalTarjeta thead th { background:#ece2f8 !important; color:#2a1f42 !important; }
[data-theme="orgullo"] #modalTarjeta tbody tr:hover td { background:rgba(139,92,246,0.16) !important; }
[data-theme="orgullo"] #modalTarjeta .snc-modal-nota { background:rgba(139,92,246,0.1) !important; color:#5b21b6 !important; border-top:0.0625rem solid rgba(139,92,246,0.3) !important; font-style:italic; }
/* Cajas inyectadas por JS dentro de la tarjeta */
[data-theme="orgullo"] #modalTarjeta div[style*="rgba(251,191,36"] { background:#fef3c7 !important; border-color:#d97706 !important; color:#92400e !important; }
[data-theme="orgullo"] #modalTarjeta div[style*="rgba(96,165,250"] { background:#ece2f8 !important; border-color:#a78bfa !important; }
[data-theme="orgullo"] #modalTarjeta div[style*="rgba(52,211,153"] { background:#d1fae5 !important; border-color:#059669 !important; }
[data-theme="orgullo"] #modalTarjeta span[style*="#34d399"] { color:#047857 !important; }
[data-theme="orgullo"] #modalTarjeta div[style*="rgba(255,255,255,0.5"] { color:#6d5b91 !important; }
[data-theme="orgullo"] .snc-modal-nota { background:rgba(139,92,246,0.1); color:#5b21b6; border-top:0.0625rem solid rgba(139,92,246,0.3); }
/* ===== ORGULLO: FILTROS SNC (etiquetas de color legibles) ===== */
[data-theme="orgullo"] #sncFiltrosWrap { background:rgba(255,255,255,0.65) !important; border-color:rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] #sncFiltrosToggle span { color:#33264d !important; }
[data-theme="orgullo"] #sncFiltrosToggle .fa-filter { color:var(--accent-dark) !important; }
[data-theme="orgullo"] #sncFiltrosChevron { color:#6d5b91 !important; }
[data-theme="orgullo"] #sncFiltrosWrap label { color:#5b4a80 !important; }
html[data-theme="orgullo"] .snc-filtro-label,
[data-theme="orgullo"] #sncFiltrosWrap .snc-filtro-label { color:#6d28d9 !important; }
html[data-theme="orgullo"] .snc-filtro-label-red,
[data-theme="orgullo"] #sncFiltrosWrap .snc-filtro-label-red { color:#b91c1c !important; }
html[data-theme="orgullo"] .snc-filtro-label-violet,
[data-theme="orgullo"] #sncFiltrosWrap .snc-filtro-label-violet { color:#7c3aed !important; }
html[data-theme="orgullo"] .snc-filtro-label-green,
[data-theme="orgullo"] #sncFiltrosWrap .snc-filtro-label-green { color:#15803d !important; }
html[data-theme="orgullo"] .snc-filtro-label-amber,
[data-theme="orgullo"] #sncFiltrosWrap .snc-filtro-label-amber { color:#b45309 !important; }
/* ===== ORGULLO: BORDES VIOLETA OSCURO EN CARDS ===== */
[data-theme="orgullo"] .glass-card,
[data-theme="orgullo"] .card:not([class*="bg-primary"]):not([class*="bg-dark"]):not([class*="bg-secondary"]):not([class*="bg-info"]):not([class*="bg-success"]):not([class*="bg-danger"]):not([class*="bg-gradient"]),
[data-theme="orgullo"] .stat-card:not(.selected),
[data-theme="orgullo"] .stat-card-prof:not(.selected),
[data-theme="orgullo"] .preview-card:not(.selected),
[data-theme="orgullo"] .perm-card:not(.selected),
[data-theme="orgullo"] .total-item:not(:hover),
[data-theme="orgullo"] .card-option:not(.selected):not(:hover),
[data-theme="orgullo"] .card-option-descuento:not(.selected),
[data-theme="orgullo"] .modal-content-win,
[data-theme="orgullo"] .modal-content-prof,
[data-theme="orgullo"] .modal-content-modern,
[data-theme="orgullo"] .selected-worker-card,
[data-theme="orgullo"] .data-table-wrapper { border-color:rgba(109,40,217,0.45) !important; }
[data-theme="orgullo"] .glass-card:hover,
[data-theme="orgullo"] .stat-card:not(.selected):hover,
[data-theme="orgullo"] .perm-card:hover { border-color:#6d28d9 !important; }
/* ===== ORGULLO: CLASIFICADORES ===== */
[data-theme="orgullo"] .clasificador-btn { color:#493a66 !important; }
[data-theme="orgullo"] .clasificador-btn:hover { background:rgba(124,58,237,0.16) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .clasificador-btn.active { background:var(--accent) !important; color:#ffffff !important; }
[data-theme="orgullo"] .modal-glass .modal-content { background:#f4eefb !important; border:0.0625rem solid rgba(139,92,246,0.35) !important; color:#33264d !important; backdrop-filter:none; }
[data-theme="orgullo"] .modal-glass .modal-header,
[data-theme="orgullo"] .modal-glass .modal-footer { border-color:rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] .modal-glass .modal-title { color:#2a1f42 !important; }
/* ===== ORGULLO: BOTONES CON COLOR DE ÉNFASIS (todas las páginas) ===== */
/* GLOBAL — header.php / footer.php / user_menu.php (todas las páginas) */
[data-theme="orgullo"] .btn-win-primary,
[data-theme="orgullo"] .btn-win-primary:hover { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-win-outline { background:transparent !important; border-color:rgba(139,92,246,0.45) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .btn-win-outline:hover { background:rgba(124,58,237,0.12) !important; }
[data-theme="orgullo"] .btn-win-sm:not(.btn-win-primary):not(.btn-win-danger):not(.btn-win-warning):not(.btn-win-success):not(.btn-win-info) { color:var(--accent-dark) !important; border-color:rgba(139,92,246,0.4) !important; }
[data-theme="orgullo"] .btn-win-sm:not(.btn-win-primary):hover:not(.btn-win-danger):not(.btn-win-warning):not(.btn-win-success):not(.btn-win-info) { background:rgba(124,58,237,0.1) !important; }
[data-theme="orgullo"] .btn-outline-primary { color:var(--accent-dark) !important; border-color:var(--accent-dark) !important; }
[data-theme="orgullo"] .btn-outline-primary:hover { background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-outline-secondary { color:#5b4a80 !important; border-color:rgba(139,92,246,0.45) !important; }
[data-theme="orgullo"] .btn-outline-secondary:hover { background:rgba(124,58,237,0.14) !important; border-color:var(--accent-dark) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] footer .form-control, [data-theme="orgullo"] .corporate-footer .form-control { background:#ffffff !important; color:#33264d !important; }
[data-theme="orgullo"] .scroll-quick-btn { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; }
[data-theme="orgullo"] .btn-iso-entendido { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; color:#ffffff !important; }
/* bandecnom.php */
[data-theme="orgullo"] .btn-exportar.dbf { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; box-shadow:0 0.25rem 0.9375rem rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .btn-export-dropdown { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.4) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .btn-export-dropdown:hover { background:rgba(124,58,237,0.12) !important; }
/* clasificadores.php */
[data-theme="orgullo"] .btn-primary-glass { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-primary-glass:hover { box-shadow:0 0.25rem 0.9375rem rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .btn-primary-glass.dropdown-toggle::after,
[data-theme="orgullo"] .btn-primary-glass .dropdown-toggle-split::before { color:#ffffff !important; }
/* nominas.php */
[data-theme="orgullo"] .ventana-cuadre-btn-primary { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; }
[data-theme="orgullo"] .ventana-cuadre-btn-primary:hover { filter:brightness(1.08); }
[data-theme="orgullo"] .rango-btn { color:#493a66 !important; border-color:rgba(139,92,246,0.35) !important; }
[data-theme="orgullo"] .rango-btn:hover { background:rgba(124,58,237,0.14) !important; border-color:var(--accent-dark) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .rango-btn.active { background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#ffffff !important; }
[data-theme="orgullo"] .cuadre-export-btn { background:#ffffff !important; border-color:rgba(139,92,246,0.4) !important; color:#493a66 !important; }
[data-theme="orgullo"] .cuadre-export-btn:hover { background:rgba(124,58,237,0.12) !important; border-color:var(--accent-dark) !important; color:var(--accent-dark) !important; filter:none; }
[data-theme="orgullo"] .cuadre-export-btn--imprimir,
[data-theme="orgullo"] .cuadre-export-btn--docx { border-color:rgba(124,58,237,0.45) !important; background:rgba(124,58,237,0.1) !important; color:var(--accent-dark) !important; }
[data-theme="orgullo"] .cuadre-export-btn--xls { border-color:rgba(5,150,105,0.4) !important; color:#047857 !important; }
[data-theme="orgullo"] .cuadre-export-btn--pdf { border-color:rgba(220,38,38,0.4) !important; color:#b91c1c !important; }
[data-theme="orgullo"] .cuadre-export-btn--txt { border-color:rgba(217,119,6,0.4) !important; color:#b45309 !important; }
/* snc225.php */
[data-theme="orgullo"] .btn-icon.btn-icon-primary { background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-icon.btn-icon-primary:hover { background:var(--accent-dark) !important; }
/* ===== ORGULLO: BANDECNOM — PANEL DE TOTALES ===== */
[data-theme="orgullo"] .totals-panel { background:#f4eefb !important; border-color:rgba(139,92,246,0.45) !important; }
[data-theme="orgullo"] .total-item { background:#ffffff !important; border-color:rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .total-item:hover { border-color:#7c3aed !important; box-shadow:0 0 0 0.125rem rgba(139,92,246,0.2), 0 0.5rem 1.375rem rgba(139,92,246,0.18) !important; }
[data-theme="orgullo"] .total-label { color:#5b4a80 !important; }
[data-theme="orgullo"] .total-value { color:#33264d !important; }
[data-theme="orgullo"] .total-value.positive { color:#16a34a !important; }
[data-theme="orgullo"] .total-value.negative { color:#dc2626 !important; }
[data-theme="orgullo"] .total-label-acreditar { color:#493a66 !important; }
[data-theme="orgullo"] .total-value-acreditar { color:#047857 !important; }
[data-theme="orgullo"] .total-item-acreditar {
    background:linear-gradient(135deg, rgba(124,58,237,0.22), rgba(139,92,246,0.12)) !important;
    border-color:#8b5cf6 !important;
    box-shadow:0 0 0 0.125rem rgba(139,92,246,0.22), 0 0.5rem 1.375rem rgba(139,92,246,0.22) !important;
}
[data-theme="orgullo"] .total-item-acreditar:hover {
    background:linear-gradient(135deg, rgba(124,58,237,0.32), rgba(139,92,246,0.18)) !important;
    border-color:#6d28d9 !important;
    box-shadow:0 0 0 0.125rem rgba(139,92,246,0.3), 0 0.625rem 1.625rem rgba(124,58,237,0.28) !important;
}
[data-theme="orgullo"] .total-divider { background:linear-gradient(to bottom, transparent, #7c3aed, transparent) !important; }
[data-theme="orgullo"] .stat-badge.success { color:#15803d !important; border-color:rgba(22,163,74,0.4) !important; }
[data-theme="orgullo"] .stat-badge.danger { color:#b91c1c !important; border-color:rgba(220,38,38,0.4) !important; }
[data-theme="orgullo"] .stat-badge.info { color:#1d4ed8 !important; border-color:rgba(37,99,235,0.4) !important; }
/* ===== ORGULLO: FOOTER CORPORATIVO ===== */
[data-theme="orgullo"] .corporate-footer {
    --corp-bg:rgba(228,214,248,0.97);
    --corp-border:rgba(109,40,217,0.55);
    --corp-bar-bg:rgba(109,40,217,0.08);
    --corp-bar-border:rgba(124,58,237,0.25);
    --corp-bottom-bg:rgba(109,40,217,0.12);
    --corp-bottom-border:rgba(124,58,237,0.22);
    --corp-copy-bg:rgba(109,40,217,0.1);
    --corp-text-strong:#241a3d;
    --corp-text-muted:#4a3a70;
    --corp-text-copy:#2e2250;
    --corp-accent:#5b21b6;
    --corp-accent-2:#6d28d9;
    --corp-icon-bg:rgba(109,40,217,0.14);
    --corp-icon-border:rgba(109,40,217,0.4);
    --corp-icon-hover-bg:rgba(109,40,217,0.24);
    --corp-icon-hover-border:#5b21b6;
    --corp-badge-bg:rgba(109,40,217,0.16);
    --corp-badge-border:rgba(109,40,217,0.4);
    --corp-badge-hover-bg:rgba(109,40,217,0.26);
    --corp-badge-hover-border:#5b21b6;
    --corp-separator:rgba(46,34,80,0.4);
    --corp-logo-filter:none;
    box-shadow:0 0.625rem 1.875rem rgba(76,29,149,0.16) !important;
}
/* ===== VERDE (NATURALEZA): FOOTER CORPORATIVO ===== */
[data-theme="verde"] .corporate-footer {
    --corp-bg:rgba(13,30,17,0.94);
    --corp-border:rgba(74,222,128,0.28);
    --corp-bar-bg:rgba(0,0,0,0.22);
    --corp-bar-border:rgba(74,222,128,0.15);
    --corp-bottom-bg:rgba(74,222,128,0.07);
    --corp-bottom-border:rgba(74,222,128,0.14);
    --corp-copy-bg:rgba(0,0,0,0.26);
    --corp-text-strong:#d4f0d8;
    --corp-text-muted:#8fc9a1;
    --corp-text-copy:#b7e2c0;
    --corp-accent:#4ade80;
    --corp-accent-2:#34d399;
    --corp-icon-bg:rgba(74,222,128,0.09);
    --corp-icon-border:rgba(74,222,128,0.22);
    --corp-icon-hover-bg:rgba(74,222,128,0.16);
    --corp-icon-hover-border:#4ade80;
    --corp-badge-bg:rgba(74,222,128,0.11);
    --corp-badge-border:rgba(74,222,128,0.22);
    --corp-badge-hover-bg:rgba(74,222,128,0.17);
    --corp-badge-hover-border:#4ade80;
    --corp-separator:rgba(212,240,216,0.22);
    --corp-logo-filter:brightness(0) invert(1);
    box-shadow:0 0.625rem 1.875rem rgba(0,0,0,0.3) !important;
}
/* ===== AZUL (MAR): FOOTER CORPORATIVO ===== */
[data-theme="blue"] .corporate-footer {
    --corp-bg:#074D9C;
    --corp-border:rgba(3,40,80,0.6);
    --corp-bar-bg:rgba(0,0,0,0.12);
    --corp-bar-border:rgba(255,255,255,0.16);
    --corp-bottom-bg:rgba(0,0,0,0.15);
    --corp-bottom-border:rgba(255,255,255,0.14);
    --corp-copy-bg:rgba(0,0,0,0.18);
    --corp-text-strong:#ffffff;
    --corp-text-muted:#cfe3f7;
    --corp-text-copy:#e3effb;
    --corp-accent:#bfe0ff;
    --corp-accent-2:#93c5fd;
    --corp-icon-bg:rgba(255,255,255,0.1);
    --corp-icon-border:rgba(255,255,255,0.25);
    --corp-icon-hover-bg:rgba(255,255,255,0.18);
    --corp-icon-hover-border:#ffffff;
    --corp-badge-bg:rgba(255,255,255,0.12);
    --corp-badge-border:rgba(255,255,255,0.25);
    --corp-badge-hover-bg:rgba(255,255,255,0.2);
    --corp-badge-hover-border:#ffffff;
    --corp-separator:rgba(255,255,255,0.35);
    --corp-logo-filter:brightness(0) invert(1);
    box-shadow:0 0.625rem 1.875rem rgba(2,32,64,0.35) !important;
}
[data-theme="blue"] .iso27001-overlay { background:rgba(2,32,64,0.55) !important; }
[data-theme="blue"] .iso27001-box { background:#074D9C !important; border:0.0625rem solid rgba(255,255,255,0.22) !important; color:#ffffff !important; }
[data-theme="blue"] .iso27001-titlebar { border-bottom:0.0625rem solid rgba(255,255,255,0.18) !important; background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; }
[data-theme="blue"] .iso27001-titlebar .tt-icon,
[data-theme="blue"] .iso27001-titlebar .tt-text { color:#ffffff !important; }
[data-theme="blue"] .iso27001-close { background:rgba(255,255,255,0.12) !important; color:#ffffff !important; }
[data-theme="blue"] .iso27001-close:hover { background:#ef4444 !important; color:#ffffff !important; }
[data-theme="blue"] .iso27001-body { color:#e3effb !important; }
[data-theme="blue"] .iso27001-body .iso-empresa { color:#ffffff !important; }
[data-theme="blue"] .iso27001-body .iso-empresa i,
[data-theme="blue"] .iso27001-body h4 { color:#bfe0ff !important; }
[data-theme="blue"] .iso27001-body .iso-sub { color:#cfe3f7 !important; }
[data-theme="blue"] .iso27001-body p { color:#dcebfa !important; }
[data-theme="blue"] .iso27001-body .actions { border-top:0.0625rem solid rgba(255,255,255,0.2) !important; }
[data-theme="blue"] .iso27001-body .btn-iso-entendido { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; color:#ffffff !important; border:none !important; }
[data-theme="blue"] .iso27001-body .btn-iso-entendido:hover { filter:brightness(1.1); box-shadow:0 0.375rem 1.125rem rgba(2,32,64,0.4); }
[data-theme="orgullo"] .copyright-link { color:#493a66 !important; font-weight:500; }
[data-theme="orgullo"] .copyright-link:hover { color:#6d28d9 !important; }
[data-theme="orgullo"] .corporate-copyright a,
[data-theme="orgullo"] .corporate-bottom a { color:#6d28d9 !important; }
/* ===== ORGULLO: MODALES FOOTER ISO-27001 ===== */
[data-theme="orgullo"] .iso27001-overlay { background:rgba(46,16,101,0.45) !important; }
[data-theme="orgullo"] .iso27001-box { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.3) !important; color:#33264d !important; }
[data-theme="orgullo"] .iso27001-titlebar { border-bottom:0.0625rem solid rgba(139,92,246,0.3) !important; }
[data-theme="orgullo"] .iso27001-close { background:rgba(139,92,246,0.12) !important; color:#33264d !important; }
[data-theme="orgullo"] .iso27001-close:hover { background:#ef4444 !important; color:#ffffff !important; }
[data-theme="orgullo"] .iso27001-body { color:#33264d !important; }
[data-theme="orgullo"] .iso27001-body .iso-empresa { color:#2a1f42 !important; }
[data-theme="orgullo"] .iso27001-body .iso-empresa i { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .iso27001-body .iso-sub { color:#6d5b91 !important; }
[data-theme="orgullo"] .iso27001-body h4 { color:var(--accent-dark) !important; }
[data-theme="orgullo"] .iso27001-body p { color:#493a66 !important; }
[data-theme="orgullo"] .iso27001-body .actions { border-top:0.0625rem solid rgba(139,92,246,0.2) !important; }
/* ===== ORGULLO: TABLAS RANGOS/TASAS (configuracion.php) ===== */
[data-theme="orgullo"] .table-responsive { background:#ffffff !important; border-color:rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] #rangosTable thead th,
[data-theme="orgullo"] #tasasTable thead th { background:#ece2f8 !important; border-bottom-color:rgba(139,92,246,0.3) !important; color:#2a1f42 !important; }
[data-theme="orgullo"] #rangosTable tbody tr,
[data-theme="orgullo"] #tasasTable tbody tr { background:#ffffff !important; border-bottom-color:rgba(139,92,246,0.12) !important; }
[data-theme="orgullo"] #rangosTable tbody tr:hover,
[data-theme="orgullo"] #tasasTable tbody tr:hover { background:rgba(124,58,237,0.16) !important; }
[data-theme="orgullo"] #rangosTable td,
[data-theme="orgullo"] #tasasTable td { color:#33264d !important; background:transparent !important; }
[data-theme="orgullo"] #rangosTable .form-control-sm,
[data-theme="orgullo"] #tasasTable .form-control-sm { background:#ffffff !important; border:0.0625rem solid rgba(139,92,246,0.4) !important; color:#33264d !important; }
/* ===== ORGULLO: COMPONENTES PROF (submayor_vacaciones.php) ===== */
[data-theme="orgullo"] .stat-card-prof { background:#ffffff !important; border-color:rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .stat-card-prof .stat-value { color:#2a1f42 !important; }
[data-theme="orgullo"] .stat-card-prof .stat-label { color:#6d5b91 !important; }
[data-theme="orgullo"] .stat-card-prof .stat-sub { color:#6d5b91 !important; }
[data-theme="orgullo"] .stat-card-prof .stat-bar { background:rgba(139,92,246,0.15) !important; }
[data-theme="orgullo"] .form-control-prof,
[data-theme="orgullo"] .form-select-prof { background-color:#ffffff !important; border-color:rgba(139,92,246,0.35) !important; color:#33264d !important; }
[data-theme="orgullo"] .form-control-prof::placeholder,
[data-theme="orgullo"] .form-select-prof::placeholder { color:#9b87c4 !important; }
[data-theme="orgullo"] .form-control-prof:focus,
[data-theme="orgullo"] .form-select-prof:focus { border-color:var(--accent-dark) !important; box-shadow:0 0 0 0.1875rem rgba(139,92,246,0.18) !important; }
[data-theme="orgullo"] input[type="date"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="orgullo"] input[type="time"].form-control-prof::-webkit-calendar-picker-indicator,
[data-theme="orgullo"] input[type="datetime-local"].form-control-prof::-webkit-calendar-picker-indicator { filter:none !important; }
[data-theme="orgullo"] .f-label { color:#6d5b91 !important; }
[data-theme="orgullo"] .f-label i { color:#9b87c4 !important; }
[data-theme="orgullo"] .badge-factor { background:rgba(124,58,237,0.12) !important; border-color:rgba(124,58,237,0.35) !important; color:#6d28d9 !important; }
[data-theme="orgullo"] .badge-pill-prof { background:rgba(139,92,246,0.08) !important; border-color:rgba(139,92,246,0.25) !important; color:#5b4a80 !important; }
[data-theme="orgullo"] .badge-9 { background:rgba(124,58,237,0.12) !important; border-color:rgba(124,58,237,0.3) !important; color:#6d28d9 !important; }
[data-theme="orgullo"] .btn-prof { background:rgba(139,92,246,0.08) !important; border-color:rgba(139,92,246,0.25) !important; color:#33264d !important; }
[data-theme="orgullo"] .btn-prof:hover { background:rgba(124,58,237,0.16) !important; border-color:var(--accent-dark) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .btn-icon-sm { background:rgba(139,92,246,0.08) !important; border-color:rgba(139,92,246,0.25) !important; color:#5b4a80 !important; }
[data-theme="orgullo"] .btn-icon-sm:hover { background:rgba(124,58,237,0.16) !important; color:#4c1d95 !important; }
[data-theme="orgullo"] .btn-primary-solid { background:linear-gradient(135deg,var(--accent),var(--accent-dark)) !important; border:none !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-success-solid { background:linear-gradient(135deg,#047857,#059669) !important; border:none !important; color:#ffffff !important; }
[data-theme="orgullo"] .btn-danger-soft { background:rgba(239,68,68,0.1) !important; border-color:rgba(239,68,68,0.3) !important; color:#b91c1c !important; }
[data-theme="orgullo"] .btn-danger-soft:hover { background:rgba(239,68,68,0.18) !important; color:#991b1b !important; }
[data-theme="orgullo"] .ajuste-seccion { background:#ffffff !important; border-color:rgba(139,92,246,0.25) !important; }
[data-theme="orgullo"] .ajuste-seccion div:not(.stat-value) { color:#6d5b91 !important; }
[data-theme="orgullo"] .tab-prof.active { color:#5b21b6 !important; border-color:rgba(139,92,246,0.5) !important; box-shadow:inset 0 0 0 0.0625rem rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .tab-prof .count { background:rgba(139,92,246,0.12) !important; }
[data-theme="orgullo"] .badge-acumulacion { background:rgba(4,120,87,0.14) !important; border-color:rgba(4,120,87,0.4) !important; color:#047857 !important; }
[data-theme="orgullo"] .badge-baja { background:rgba(180,83,9,0.14) !important; border-color:rgba(180,83,9,0.4) !important; color:#b45309 !important; }
[data-theme="orgullo"] .badge-excedido { background:rgba(185,28,28,0.14) !important; border-color:rgba(185,28,28,0.4) !important; color:#b91c1c !important; }
[data-theme="orgullo"] .badge-activo { background:rgba(4,120,87,0.14) !important; border-color:rgba(4,120,87,0.4) !important; color:#047857 !important; }
[data-theme="orgullo"] .tabs-prof { background:rgba(139,92,246,0.07) !important; border-color:rgba(139,92,246,0.2) !important; }
[data-theme="orgullo"] .modal-content-prof { background:#ffffff !important; border-color:rgba(139,92,246,0.3) !important; color:#33264d !important; }
/* ===== ORGULLO: TABLA SNC (snc225.php) ===== */
html[data-theme="orgullo"] .table-snc { color:#33264d !important; }
html[data-theme="orgullo"] .table-snc thead th { background:#ece2f8 !important; color:#2a1f42 !important; border-bottom:0.0625rem solid rgba(139,92,246,0.35) !important; }
html[data-theme="orgullo"] .table-snc tbody tr { border-bottom:0.0625rem solid rgba(139,92,246,0.12) !important; }
html[data-theme="orgullo"] .table-snc tbody tr:hover { background:rgba(139,92,246,0.16) !important; }
html[data-theme="orgullo"] .table-snc tbody td { color:#33264d !important; border-bottom-color:rgba(139,92,246,0.12) !important; }
html[data-theme="orgullo"] .table-snc tbody td.text-muted { color:#6d5b91 !important; }
html[data-theme="orgullo"] .table-snc td a { color:var(--accent-dark) !important; }
/* ===== ORGULLO: BOTONES DE CERRAR (X) VISIBLES ===== */
[data-theme="orgullo"] .btn-close-white { filter:none !important; opacity:0.65 !important; color:#33264d !important; background-color:transparent; }
[data-theme="orgullo"] .modal .btn-close,
[data-theme="orgullo"] .modal-content .btn-close,
[data-theme="orgullo"] .modal-content-modern .btn-close,
[data-theme="orgullo"] #modalTarjeta .btn-close,
[data-theme="orgullo"] .btn-close { filter:none !important; opacity:0.7 !important; color:#33264d !important; background-color:transparent; }
[data-theme="orgullo"] .modal .btn-close:hover,
[data-theme="orgullo"] .modal-content .btn-close:hover,
[data-theme="orgullo"] .modal-content-modern .btn-close:hover,
[data-theme="orgullo"] #modalTarjeta .btn-close:hover,
[data-theme="orgullo"] .btn-close:hover { opacity:1 !important; transform:rotate(90deg) scale(1.1); background-color:rgba(139,92,246,0.15) !important; }
[data-theme="orgullo"] .swal2-close { filter:none !important; color:#33264d !important; }
[data-theme="orgullo"] .swal2-close:hover { background-color:rgba(139,92,246,0.12); }
[data-theme="orgullo"] .btn-close-custom { border-color:rgba(51,38,77,0.5) !important; color:#33264d !important; background:transparent !important; opacity:0.9; }
[data-theme="orgullo"] .btn-close-custom:hover { background-color:#ef4444 !important; border-color:#ef4444 !important; color:#ffffff !important; opacity:1; }
/* ===== ORGULLO: MODAL SALVA/RESTAURA (Windows 11 style) ===== */
[data-theme="orgullo"] .win-modal-titlebar { background:var(--accent); }
[data-theme="orgullo"] .win-modal-body { background:#cbc3e3; color:#33264d; }
[data-theme="orgullo"] .win-modal-footer { border-color:rgba(51,38,77,0.25); color:#493a66; }
[data-theme="orgullo"] .permisos-leyenda { background:rgba(255,255,255,0.5) !important; border-color:rgba(51,38,77,0.2) !important; color:#33264d !important; }
[data-theme="orgullo"] .filter-label { color:#33264d !important; }
/* ===== SIDEBAR: SIN BORDES EN REPOSO, SOLO AL HOVER (todos los temas) ===== */
html .win-sidebar .nav-item,
html .win-sidebar .nav-submenu .nav-item {
    border-color: transparent !important;
    outline: none !important;
    box-shadow: none;
}
html .win-sidebar .nav-item:hover:not(.active),
html .win-sidebar .nav-submenu .nav-item:hover:not(.active) {
    border-color: var(--accent) !important;
}
html .win-sidebar .nav-item.active,
html .win-sidebar .nav-submenu .nav-item.active,
html .win-sidebar .nav-item.active:hover {
    border-color: transparent !important;
    border-left-color: transparent !important;
}
html .win-sidebar.collapsed .nav-submenu .nav-item:hover:not(.active) { border-color: var(--accent) !important; }
</style>

<script src="<?php echo $base_prefix; ?>js/sweetalert-dark.js"></script>

<div class="user-menu-container">
    <?php if ($show_clock): ?>
    <div class="date-badge d-none d-md-block" data-tooltip="<?php echo date('l, d \d\e F \d\e Y'); ?>" data-tooltip-theme="secondary">
        <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d/m/Y'); ?>
        <span class="mx-1">•</span>
        <i class="fas fa-clock me-1"></i> <span id="liveClockMenu"></span>
    </div>
    <?php endif; ?>
    
    <button type="button" id="tpSettingsBtn" class="btn-win" title="Personalización" data-tooltip="Personalización" data-tooltip-theme="gradient" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:0.75rem;flex-shrink:0;">
        <i class="fas fa-sliders"></i>
        <i class="fas fa-sun" id="themeToggleIcon" style="display:none;"></i>
    </button>
    
    <div class="dropdown">
        <div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false" data-tooltip="Menú de usuario" data-tooltip-theme="gradient">
            <img src="<?php echo htmlspecialchars($user_foto_menu); ?>" 
                 alt="Avatar" 
				 referrerpolicy="no-referrer" 
                 class="user-avatar-img" 
                 style="display: none;"
                 onload="this.style.display='block'; this.parentElement.querySelector('.user-avatar-iniciales').style.display='none';"
                 onerror="this.style.display='none'; this.parentElement.querySelector('.user-avatar-iniciales').style.display='flex';">
            <span class="user-avatar-iniciales" style="display: flex;"><?php echo htmlspecialchars($user_iniciales_menu); ?></span>
        </div>
        <ul class="dropdown-menu dropdown-menu-win dropdown-menu-end">
            <li>
				<div class="dropdown-item-text">
					<div class="fw-bold mb-1"><?php echo htmlspecialchars($user_nombre_completo); ?></div>
					<small><i class="fas fa-id-card me-1"></i> CI: <?php echo htmlspecialchars($user_ci); ?></small>
					<div class="mt-1 d-flex flex-column gap-1">
						<span class="badge" id="rolBadge" title="Ver permisos por tipo de usuario" onclick="mostrarPermisosRol()" data-tooltip="Ver permisos por tipo de usuario" data-tooltip-theme="info" style="background: linear-gradient(135deg, var(--accent), var(--accent-light)); cursor: pointer;">
							<i class="fas fa-user-tag me-1"></i> <?php echo htmlspecialchars($user_rol_descripcion ?: 'Usuario'); ?> <i class="fas fa-info-circle ms-1" style="font-size:0.75rem; opacity: 0.85;"></i>
						</span>
						<?php if ($is_google_auth): ?>
							<span class="badge bg-danger" style="font-size:0.65rem; width:fit-content;">
								<i class="fab fa-google me-1"></i>Logueado con Google
							</span>
						<?php else: ?>
							<span class="badge bg-secondary" style="font-size:0.65rem; width:fit-content;">
								<i class="fas fa-database me-1"></i>Logueado desde Base Datos
							</span>
						<?php endif; ?>
					</div>
				</div>
            </li>
            <li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="<?php echo $base_prefix; ?>modules/users.php?id=<?php echo $user_id; ?>"><i class="fas fa-user me-2"></i> Mi Perfil</a></li>
    <li><a class="dropdown-item <?= $is_google_auth ? 'disabled text-muted fst-italic' : '' ?>"
       href="<?= $is_google_auth ? '#' : $base_prefix . 'modules/users.php?id=' . $user_id . '&cambiar_pass=1' ?>"
       <?= $is_google_auth ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
        <i class="fas fa-lock me-2"></i> Cambiar Contraseña
    </a><li>
			<li><hr class="dropdown-divider"></li>
<!-- ===== SUBMENÚ DE GOOGLE (SIEMPRE VISIBLE) ===== -->
<li class="dropdown-submenu <?= $is_google_auth ? '' : 'disabled-submenu' ?>">
    <a class="dropdown-item <?= $is_google_auth ? '' : 'text-muted' ?>" href="#" id="googleSubmenuToggle" 
       <?= $is_google_auth ? '' : 'tabindex="-1" aria-disabled="true" style="pointer-events:none; opacity:0.6;"' ?>>
        
        <!-- Flecha a la izquierda -->
        <i class="fas fa-caret-left me-1" style="font-size:0.7rem; color:<?= $is_google_auth ? '#4285F4' : '#6b7280'; ?>;"></i>
        
        <!-- Icono de Google o Candado -->
        <?php if ($is_google_auth): ?>
            <i class="fab fa-google me-2" style="color:#4285F4;"></i> Cuenta y APPS de Google
        <?php else: ?>
            <i class="fas fa-lock me-2" style="color:#6b7280;"></i> Cuenta y APPS de Google
        <?php endif; ?>
    </a>

    <!-- Submenú (Solo se muestra si está logueado con Google) -->
    <?php if ($is_google_auth): ?>
    <ul class="dropdown-menu dropdown-menu-win dropdown-submenu-menu">
        <!-- Accesos directos a aplicaciones -->
        <li>
            <a class="dropdown-item" href="https://contacts.google.com/" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-address-book me-2" style="color:#34d399;"></i> Abrir Google Contacts
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://mail.google.com/" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-envelope me-2" style="color:#f87171;"></i> Abrir Gmail
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://drive.google.com/" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-cloud me-2" style="color:#fbbf24;"></i> Abrir Google Drive
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://calendar.google.com/calendar/r" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-calendar-alt me-2" style="color:#60a5fa;"></i> Abrir Google Calendar
            </a>
        </li>

        <li><hr class="dropdown-divider"></li>

        <!-- APPS / HERRAMIENTAS DE GOOGLE (sub-submenú) -->
        <li class="dropdown-submenu">
            <a class="dropdown-item" href="#" id="googleAppsSubmenuToggle">
                <i class="fas fa-caret-left me-1" style="font-size:0.7rem; color:#4285F4;"></i>
                <i class="fas fa-th-large me-2" style="color:#4285F4;"></i> Apps / herramientas
            </a>
            <ul class="dropdown-menu dropdown-menu-win dropdown-submenu-menu">
                <li>
                    <a class="dropdown-item" href="https://meet.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-video me-2" style="color:#34d399;"></i> Google Meet (videollamadas)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://docs.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-alt me-2" style="color:#60a5fa;"></i> Google Docs
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://sheets.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-table me-2" style="color:#34d399;"></i> Google Sheets
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://slides.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-images me-2" style="color:#fbbf24;"></i> Google Slides
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://mail.google.com/tasks/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-tasks me-2" style="color:#a78bfa;"></i> Google Tasks
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://keep.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-sticky-note me-2" style="color:#fbbf24;"></i> Google Keep
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://photos.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-camera me-2" style="color:#34d399;"></i> Google Fotos
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://translate.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-language me-2" style="color:#60a5fa;"></i> Google Translate
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://maps.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-map-marked-alt me-2" style="color:#34d399;"></i> Google Maps
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-youtube me-2" style="color:#ef4444;"></i> YouTube
                    </a>
                </li>
            </ul>
        </li>

        <li><hr class="dropdown-divider"></li>

        <!-- SEGURIDAD DE GOOGLE (sub-submenú) -->
        <li class="dropdown-submenu">
            <a class="dropdown-item" href="#" id="googleSeguridadSubmenuToggle">
			<!-- Flecha a la izquierda -->
			<i class="fas fa-caret-left me-1" style="font-size:0.7rem; color:<?= $is_google_auth ? '#4285F4' : '#6b7280'; ?>;"></i>
                <i class="fas fa-shield-halved me-2" style="color:#34d399;"></i> SEGURIDAD DE GOOGLE
            </a>
            <ul class="dropdown-menu dropdown-menu-win dropdown-submenu-menu">
                <li>
                    <a class="dropdown-item" href="https://passwords.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-lock me-2" style="color:#fbbf24;"></i> Gestor de contraseñas
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://myaccount.google.com/signinoptions/two-step-verification" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-mobile-alt me-2" style="color:#34d399;"></i> Verificación en 2 pasos
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://myaccount.google.com/device-activity" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-laptop me-2" style="color:#60a5fa;"></i> Dispositivos vinculados
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://myactivity.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-history me-2" style="color:#a78bfa;"></i> Actividad reciente / mis datos
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://myaccount.google.com/data-and-privacy" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-user-shield me-2" style="color:#f87171;"></i> Datos y privacidad
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="https://takeout.google.com/" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-download me-2" style="color:#fbbf24;"></i> Descargar tus datos (Google Takeout)
                    </a>
                </li>
            </ul>
        </li>

        <!-- Gestión de seguridad y privacidad -->
        <li>
            <a class="dropdown-item" href="https://myaccount.google.com/security-checkup" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-shield-halved me-2" style="color:#60a5fa;"></i> Revisión de seguridad
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://myaccount.google.com/privacycheckup" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-user-shield me-2" style="color:#a78bfa;"></i> Revisión de privacidad
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://adssettings.google.com" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-ad me-2" style="color:#fbbf24;"></i> Personalización de anuncios
            </a>
        </li>

        <li><hr class="dropdown-divider"></li>
        
        <li>
            <a class="dropdown-item" href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-key me-2" style="color:#f87171;"></i> Revocar acceso SisGesNom
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="https://myaccount.google.com/signinoptions/password" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-key me-2" style="color:#fbbf24;"></i> Cambiar Contraseña de Google
            </a>
        </li>
    </ul>
    <?php endif; ?>
</li>
</li>

<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="#" id="salvaRestauraBtn"><i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva / Restaura</a></li>
<li><hr class="dropdown-divider"></li>
<!-- NUEVO: Sobre el autor con enlace fijo ../explorer.html -->
<li><a class="dropdown-item" href="../../explorer.html"><i class="fas fa-info-circle me-2"></i> Sobre el autor</a></li>
<li><a class="dropdown-item text-danger" href="#" id="logoutUserMenuBtn"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<script>
(function () {
    var THEME_KEY = 'transnubet_theme';
    window.themeColor = function (name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback || '';
    };
    window.exportColor = function () {
        var oscuro = document.documentElement.getAttribute('data-theme') !== 'light';
        var V = function (n, fb) { var v = getComputedStyle(document.documentElement).getPropertyValue(n).trim(); return v || fb; };
        return {
            a: oscuro ? '#1a1a2e' : '#ffffff',
            b: oscuro ? '#0f0f1a' : '#eef1f7',
            titulo: V('--txt', oscuro ? '#ffffff' : '#1f2937'),
            muted: V('--muted', oscuro ? '#94a3b8' : '#4b5563'),
            faint: V('--faint', oscuro ? '#6b7280' : '#6b7280'),
            blue: V('--blue', oscuro ? 'var(--accent)' : 'var(--accent-dark)'),
            success: V('--color-success-soft', oscuro ? '#4ade80' : '#059669'),
            amber: V('--amber', oscuro ? '#fbbf24' : '#d97706'),
            zebra: oscuro ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.03)',
            borde: oscuro ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.07)',
            bordeCab: oscuro ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
            linea: oscuro ? 'rgba(var(--accent-rgb),0.25)' : 'rgba(var(--accent-rgb),0.25)',
            lineaFuerte: oscuro ? 'rgba(var(--accent-rgb),0.3)' : 'rgba(var(--accent-rgb),0.3)',
            lineaAmber: oscuro ? 'rgba(251,191,36,0.3)' : 'rgba(217,119,6,0.3)',
            headerBg: oscuro ? 'rgba(var(--accent-rgb),0.15)' : 'rgba(var(--accent-rgb),0.08)',
            totalBg: oscuro ? 'rgba(var(--accent-rgb),0.12)' : 'rgba(var(--accent-rgb),0.08)'
        };
    };
    window.themeApply = function (t) {
        document.documentElement.setAttribute('data-theme', t);
        if (t === 'dark' || t === 'blue' || t === 'verde' || t === 'orgullo') { document.documentElement.classList.add('dark'); }
        else { document.documentElement.classList.remove('dark'); }
        var icon = document.getElementById('themeToggleIcon');
        if (icon) { icon.className = (t === 'light' || t === 'orgullo') ? 'fas fa-moon' : 'fas fa-sun'; }
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: t } }));
    };
})();
</script>

<script>
// Matriz de permisos por rol (generada en PHP)
var PERMISOS_MATRIZ = <?php echo $matriz_permisos_json ?: '{}'; ?>;
var ROL_ACTUAL = <?php echo json_encode($user_rol_codigo ?: ''); ?>;
var PERMISOS_EMPRESA = <?php echo json_encode(defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom'); ?>;
var PERMISOS_USUARIO = <?php echo json_encode($user_nombre_completo ?: 'Usuario'); ?>;
var PUEDE_RESTAURAR = <?php echo $puede_restaurar ? 'true' : 'false'; ?>;
var AJAX_PREFIX = '<?php echo $base_prefix; ?>';
var AJAX_BACKUP_URL = '<?php echo $base_prefix; ?>ajax/backup_db.php';
var AJAX_RESTORE_URL = '<?php echo $base_prefix; ?>ajax/restore_db.php';

var PERMISOS_NOMBRES_MODULOS = {
    'dashboard': 'Panel de control', 'empleados': 'Empleados', 'nominas': 'Nóminas',
    'reportes': 'Reportes', 'clasificadores': 'Clasificadores', 'configuracion': 'Configuración',
    'usuarios': 'Usuarios', 'bandecnom': 'Banco (Exportar)', 'submayor': 'Submayor Vacaciones', 'solapines': 'Solapines'
};
var PERMISOS_ACCIONES = [
    ['ver', 'Ver', '#34d399'],
    ['crear', 'Crear', 'var(--accent)'],
    ['editar', 'Editar', '#fbbf24'],
    ['eliminar', 'Eliminar', '#f87171'],
    ['exportar', 'Exportar', 'var(--accent)']
];

function permEsc(t) {
    if (t === null || t === undefined) return '';
    return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// ============================================
// DIÁLOGOS DE ERROR PROFESIONALES (Swal dark)
// ============================================
function notificarError(titulo, detalle, opciones) {
    var o = opciones || {};
    var t = titulo || 'Ocurrió un error';
    var d = detalle || 'Se produjo un problema inesperado durante la operación. Por favor, inténtelo de nuevo o contacte al administrador del sistema si el problema persiste.';
    var icono = o.icono || 'fa-circle-exclamation';
    var color = o.color || '#f87171';
    var html =
        '<div style="display:flex;gap:0.875rem;align-items:flex-start;text-align:left;padding:0.25rem 0.125rem;">' +
            '<div style="width:2.875rem;height:2.875rem;border-radius:0.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:' + color + '1f;border:0.0625rem solid ' + color + '40;">' +
                '<i class="fas ' + icono + '" style="color:' + color + ';font-size:1.1875rem;"></i>' +
            '</div>' +
            '<div style="flex:1;min-width:0;padding-top:0.125rem;">' +
                '<div style="font-size:1.02rem;font-weight:700;color:#f1f5f9;margin-bottom:0.375rem;">' + permEsc(t) + '</div>' +
                '<div style="font-size:0.82rem;line-height:1.6;color:var(--muted);">' + permEsc(d) + '</div>' +
            '</div>' +
        '</div>';
    var cfg = {
        title: '',
        html: html,
        icon: 'none',
        background: '#1a1a2e',
        color: '#e5e7eb',
        width:o.width || '29.375rem',
        confirmButtonColor: o.confirmButtonColor || 'var(--accent)',
        confirmButtonText: o.confirmButtonText || '<i class="fas fa-check me-2"></i>Entendido',
        showCloseButton: o.showCloseButton !== false,
        customClass: { popup: 'swal-dark-popup' }
    };
    if (o.timer) cfg.timer = o.timer;
    if (o.showConfirmButton === false) cfg.showConfirmButton = false;
    if (o.showCancelButton) { cfg.showCancelButton = true; cfg.cancelButtonColor = '#64748b'; cfg.cancelButtonText = o.cancelButtonText || 'Cancelar'; }
    return Swal.fire(cfg);
}

function notificarAccesoDenegado(detalle, opciones) {
    var o = opciones || {};
    var d = detalle || 'No tiene permisos suficientes para realizar esta operación. Si considera que esto es un error, contacte al administrador del sistema.';
    var rol = (typeof ROL_ACTUAL !== 'undefined' && ROL_ACTUAL) ? ROL_ACTUAL : 'Usuario';
    var html =
        '<div style="text-align:center;padding:0.125rem;">' +
            '<div style="width:4.125rem;height:4.125rem;margin:0 auto 0.875rem;border-radius:1.125rem;display:flex;align-items:center;justify-content:center;background:#f8717122;border:0.0625rem solid #f8717140;">' +
                '<i class="fas fa-user-shield" style="color:#f87171;font-size:1.625rem;"></i>' +
            '</div>' +
            '<div style="font-size:1.1rem;font-weight:800;color:#f8fafc;margin-bottom:0.5rem;">Acceso denegado</div>' +
            '<div style="font-size:0.85rem;line-height:1.6;color:var(--muted);margin:0 auto;max-width:20.625rem;">' + permEsc(d) + '</div>' +
            '<div style="margin-top:1rem;font-size:0.78rem;color:var(--faint);background:#ffffff0a;border:0.0625rem solid #ffffff14;border-radius:0.625rem;padding:0.5rem 0.875rem;display:inline-block;">' +
                '<i class="fas fa-user-tag me-2" style="color:#93c5fd;"></i>Su rol actual: <strong style="color:#93c5fd;">' + permEsc(rol) + '</strong>' +
            '</div>' +
        '</div>';
    var cfg = {
        title: '',
        html: html,
        icon: 'none',
        background: '#1a1a2e',
        color: '#e5e7eb',
        width:o.width || '29.375rem',
        confirmButtonColor: o.confirmButtonColor || '#dc2626',
        confirmButtonText: o.confirmButtonText || '<i class="fas fa-check me-2"></i>Entendido',
        showCloseButton: o.showCloseButton !== false,
        customClass: { popup: 'swal-dark-popup' }
    };
    if (o.timer) cfg.timer = o.timer;
    if (o.showConfirmButton === false) cfg.showConfirmButton = false;
    if (o.showCancelButton) { cfg.showCancelButton = true; cfg.cancelButtonColor = '#64748b'; cfg.cancelButtonText = o.cancelButtonText || 'Regresar'; }
    return Swal.fire(cfg);
}

function permFechaHora12h() {
    var now = new Date();
    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    var h24 = now.getHours();
    var ampm = h24 >= 12 ? 'PM' : 'AM';
    var h12 = h24 % 12; if (h12 === 0) h12 = 12;
    var min = String(now.getMinutes()).padStart(2, '0');
    var sec = String(now.getSeconds()).padStart(2, '0');
    return day + '/' + month + '/' + year + ' - ' + String(h12).padStart(2, '0') + ':' + min + ':' + sec + ' ' + ampm;
}

function permFechaArchivo() {
    var now = new Date();
    return String(now.getFullYear()) + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
}

function userMenuJsBase() {
    return (window.location.pathname.indexOf('/modules/') !== -1) ? '../js/' : 'js/';
}

// Datos estructurados de la matriz para reporte (PDF / Impresión)
function permisosDatosReporte() {
    var m = PERMISOS_MATRIZ || {};
    var order = ['Admin', 'Editor', 'Super', 'Soft', 'Visor'];
    var acciones = PERMISOS_ACCIONES.map(function (a) { return [a[0], a[1]]; });
    var roles = [];
    order.forEach(function (rol) {
        var def = m[rol];
        if (!def) return;
        var filas = [];
        Object.keys(def).forEach(function (mod) {
            var perms = def[mod] || {};
            filas.push({ modulo: PERMISOS_NOMBRES_MODULOS[mod] || mod, celdas: acciones.map(function (a) { return !!perms[a[0]]; }) });
        });
        roles.push({ rol: rol, filas: filas });
    });
    return { roles: roles, acciones: acciones };
}

// CSS para la impresión (mismo diseño que los reportes de empleados/nóminas)
var PERMISOS_PRINT_CSS = `
    @page { size: letter landscape; margin:12mm 10mm 20mm 10mm; }
    body { font-family: 'Arial', sans-serif !important; font-size:9pt !important; color: #000000 !important; background-color: #ffffff !important; margin:0 !important; padding:0 !important; }
    .rep-perm-cabecera { width:100% !important; border-collapse: collapse !important; margin-bottom:0.625rem !important; border: none !important; }
    .rep-perm-cabecera td { border: none !important; padding:0 !important; vertical-align: middle !important; background-color: #ffffff !important; }
    .rep-perm-cabecera-titulo { border-bottom: 0.125rem solid #004B87 !important; padding-bottom:0.5rem !important; }
    .rep-perm-empresa { font-size:13pt !important; font-weight: bold !important; color: #004B87 !important; }
    .rep-perm-titulo { font-size:11pt !important; font-weight: bold !important; color: #333333 !important; }
    .rep-perm-meta { font-size:8pt !important; color: #444444 !important; margin-top:0.25rem !important; }
    .rep-perm-cabecera-datos { width:14.375rem !important; text-align: right !important; font-size:8pt !important; color: #444444 !important; line-height:1.5 !important; border-bottom: 0.125rem solid #004B87 !important; padding-bottom:0.5rem !important; }
    .rep-perm-tabla { width:100% !important; border-collapse: collapse !important; page-break-inside: auto !important; }
    .rep-perm-tabla thead { display: table-header-group !important; }
    .rep-perm-tabla th { background-color: #004B87 !important; color: #ffffff !important; border: 0.5pt solid #000000 !important; padding:0.25rem 0.1875rem !important; font-size:7.5pt !important; font-weight: bold !important; text-align: center !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .rep-perm-tabla td { border: 0.5pt solid #000000 !important; padding:0.1875rem 0.3125rem !important; font-size:8pt !important; }
    .rep-perm-tabla td.rep-perm-mod { text-align: left !important; }
    .rep-perm-tabla td.rep-perm-alt { background-color: #f0f4f8 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .rep-perm-tabla td.rep-perm-rol { background-color: #0b3d63 !important; color: #ffffff !important; font-weight: bold !important; font-size:9pt !important; text-align: left !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .rep-perm-tabla td.rep-perm-rol span { color: #9fd0ff !important; font-weight: normal !important; }
    .rep-perm-tabla td.rep-perm-si { color: #006600 !important; font-weight: bold !important; text-align: center !important; }
    .rep-perm-tabla td.rep-perm-no { color: #999999 !important; text-align: center !important; }
    .rep-perm-footer { width:100% !important; border-collapse: collapse !important; margin-top:0.875rem !important; }
    .rep-perm-footer td { border: none !important; padding:0 !important; }
    .rep-perm-firma { text-align: center !important; font-size:8pt !important; }
    .rep-perm-firma-linea { border-bottom: 0.0625rem solid #000000 !important; width:12.5rem !important; margin:1.875rem auto 0.25rem auto !important; }
    .rep-perm-firma-cargo { font-weight: bold !important; }
    .rep-perm-firma-sub { color: #666666 !important; }
    .rep-perm-footer-pag { display: flex !important; justify-content: space-between !important; font-size:7pt !important; color: #666666 !important; margin-top:0.5rem !important; }
    @media print { .no-print { display: none !important; } }
`;

function construirHTMLPermisos() {
    var d = permisosDatosReporte();
    var fechaHora = permFechaHora12h();
    var html = '';
    html += '<table class="rep-perm-cabecera"><tr>';
    html += '<td><div class="rep-perm-cabecera-titulo"><div class="rep-perm-empresa">' + permEsc(PERMISOS_EMPRESA) + '</div>';
    html += '<div class="rep-perm-titulo">PERMISOS POR TIPO DE USUARIO</div>';
    html += '<div class="rep-perm-meta">Generado por: ' + permEsc(PERMISOS_USUARIO) + '  |  Emisión: ' + fechaHora + '</div></div></td>';
    html += '<td class="rep-perm-cabecera-datos">Sistema de Nóminas<br>Gestión de Accesos<br>' + fechaHora + '</td>';
    html += '</tr></table>';
    html += '<table class="rep-perm-tabla"><thead><tr><th style="text-align:left;">Módulo</th>';
    d.acciones.forEach(function (a) { html += '<th>' + a[1] + '</th>'; });
    html += '</tr></thead><tbody>';
    d.roles.forEach(function (r) {
        html += '<tr><td class="rep-perm-rol" colspan="' + (d.acciones.length + 1) + '">' + permEsc(r.rol) + (r.rol === ROL_ACTUAL ? ' <span>(TU ROL)</span>' : '') + '</td></tr>';
        r.filas.forEach(function (f, fi) {
            var alt = (fi % 2 === 1) ? ' rep-perm-alt' : '';
            html += '<tr>';
            html += '<td class="rep-perm-mod' + alt + '">' + permEsc(f.modulo) + '</td>';
            f.celdas.forEach(function (ok) {
                html += '<td class="' + (ok ? 'rep-perm-si' : 'rep-perm-no') + alt + '">' + (ok ? 'Sí' : '—') + '</td>';
            });
            html += '</tr>';
        });
    });
    html += '</tbody></table>';
    html += '<table class="rep-perm-footer"><tr><td class="rep-perm-firma"><div class="rep-perm-firma-linea"></div><div class="rep-perm-firma-cargo">' + permEsc(PERMISOS_USUARIO) + '</div><div class="rep-perm-firma-sub">Generó el reporte de permisos</div></td></tr></table>';
    html += '<div class="rep-perm-footer-pag"><span>' + permEsc(PERMISOS_EMPRESA) + ' - PERMISOS POR TIPO DE USUARIO</span><span>Página 1 de 1</span></div>';
    return html;
}

// Impresión: abre ventana con HTML + CSS (mismo enfoque que empleados.php)
function imprimirPermisos() {
    var win = window.open('', '_blank');
    if (!win) {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            Swal.fire({
                title: '<i class="fas fa-external-link-alt me-2" style="color:var(--amber);"></i> Permiso requerido',
                html: '<div class="text-center"><p>El navegador bloqueó la ventana emergente.</p><p class="text-muted small">Permita las ventanas emergentes para este sitio e inténtelo de nuevo.</p></div>',
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                confirmButtonColor: 'var(--accent)',
                background: '#1a1a2e',
                color: '#ffffff'
            });
        }
        return;
    }
    var contenido = construirHTMLPermisos();
    win.document.open();
    win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>PERMISOS POR TIPO DE USUARIO</title><style>' + PERMISOS_PRINT_CSS + '#auto-hide-toolbar{transition:transform 0.3s ease;}#auto-hide-toolbar.hidden{transform:translateY(-100%);}</style></head><body><div id="auto-hide-toolbar" class="no-print" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:0.625rem 1.25rem;display:flex;justify-content:center;align-items:center;gap:0.875rem;box-shadow:0 0.25rem 1rem rgba(0,0,0,0.35);font-family:Arial,sans-serif;border-bottom:0.1875rem solid #1e40af;transition:transform 0.3s ease;"><span style="color:#e0e7ff;font-weight:bold;font-size:0.8125rem;letter-spacing:0.0312rem;">🖨️ VISTA PREVIA DE IMPRESIÓN</span><button onclick="window.print()" style="padding:0.5625rem 1.375rem;background:#22c55e;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">🖨️ Imprimir</button><button onclick="window.close()" style="padding:0.5625rem 1.375rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;font-size:0.8125rem;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:0.375rem;box-shadow:0 0.125rem 0.375rem rgba(0,0,0,0.2);">✖ Cerrar</button></div>' + contenido + '<script>(function(){var tb=document.getElementById("auto-hide-toolbar");if(!tb)return;var lastY=window.scrollY||window.pageYOffset,ticking=false;function ch(){if(!ticking){window.requestAnimationFrame(function(){var curY=window.scrollY||document.documentElement.scrollTop||window.pageYOffset||0;if(curY>lastY&&curY>60)tb.classList.add("hidden");else tb.classList.remove("hidden");lastY=curY;ticking=false;});ticking=true;}}window.addEventListener("scroll",ch);document.addEventListener("scroll",ch);})();<\/script></body></html>');
    win.document.close();
}

// Emite pdfmake + vfs_fonts bajo demanda (los mismos .js de empleados.php)
function permEmitPdfLibs(cb) {
    if (window.pdfMake && pdfMake.vfs) { cb(); return; }
    var base = userMenuJsBase();
    var s1 = document.createElement('script');
    s1.src = base + 'pdfmake.min.js';
    s1.onload = function () {
        var s2 = document.createElement('script');
        s2.src = base + 'vfs_fonts.js';
        s2.onload = function () { cb(); };
        document.head.appendChild(s2);
    };
    s1.onerror = function () {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            Swal.close();
            Swal.fire({
                title: '<i class="fas fa-file-pdf me-2" style="color:#f87171;"></i> Error',
                text: 'No se pudo cargar la librería para generar el PDF.',
                icon: 'error',
                confirmButtonColor: '#ef4444',
                background: '#1a1a2e',
                color: '#ffffff'
            });
        }
    };
    document.head.appendChild(s1);
}

// Exportar a PDF con pdfmake (mismo estilo que el reporte de empleados)
function exportarPermisosPDF() {
    if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
        Swal.fire({
            title: '<i class="fas fa-spinner fa-pulse me-2" style="color:#f59e0b;"></i> Generando PDF...',
            text: 'Preparando el documento.',
            allowOutsideClick: false,
            didOpen: function () { Swal.showLoading(); },
            background: '#17171f',
            color: '#ffffff'
        });
    }
    permEmitPdfLibs(function () {
        var d = permisosDatosReporte();
        var fechaHora = permFechaHora12h();
        var bodyRows = [];
        var cabecera = [{ text: 'Módulo', style: 'th', alignment: 'left' }];
        d.acciones.forEach(function (a) { cabecera.push({ text: a[1], style: 'th', alignment: 'center' }); });
        bodyRows.push(cabecera);
        d.roles.forEach(function (r) {
            bodyRows.push([{ text: r.rol + (r.rol === ROL_ACTUAL ? '  (TU ROL)' : ''), colSpan: d.acciones.length + 1, style: 'rolHeader' }]);
            r.filas.forEach(function (f, fi) {
                var zebra = (fi % 2 === 1) ? '#f0f4f8' : null;
                var fila = [{ text: f.modulo, style: 'modulo', fillColor: zebra }];
                f.celdas.forEach(function (ok) {
                    fila.push({ text: ok ? 'Sí' : '—', alignment: 'center', style: ok ? 'si' : 'no', fillColor: zebra });
                });
                bodyRows.push(fila);
            });
        });
        var doc = {
            pageSize: 'LETTER',
            pageOrientation: 'landscape',
            pageMargins: [30, 55, 30, 45],
            content: [
                {
                    columns: [
                        {
                            width:'*',
                            stack: [
                                { text: PERMISOS_EMPRESA, style: 'empresa' },
                                { text: 'PERMISOS POR TIPO DE USUARIO', style: 'titulo' },
                                { text: 'Generado por: ' + PERMISOS_USUARIO + '  |  Emisión: ' + fechaHora, style: 'meta' }
                            ]
                        }
                    ]
                },
                {
                    table: {
                        headerRows: 1,
                        widths: ['*', 42, 42, 42, 42, 48],
                        body: bodyRows
                    },
                    layout: {
                        hLineWidth: function () { return 0.5; },
                        vLineWidth: function () { return 0.5; },
                        hLineColor: function () { return '#000000'; },
                        vLineColor: function () { return '#000000'; }
                    }
                }
            ],
            styles: {
                empresa: { fontSize: 13, bold: true, color: '#004B87' },
                titulo: { fontSize: 11, bold: true, margin:[0, 2, 0, 4] },
                meta: { fontSize: 8, color: '#444444', margin:[0, 2, 0, 10] },
                th: { fillColor: '#004B87', color: '#FFFFFF', bold: true, fontSize: 8 },
                rolHeader: { fillColor: '#0b3d63', color: '#FFFFFF', bold: true, fontSize: 9, margin:[2, 3, 0, 3] },
                modulo: { fontSize: 8, margin:[2, 2, 0, 2] },
                si: { fontSize: 8, bold: true, color: '#006600' },
                no: { fontSize: 8, color: '#999999' }
            },
            footer: function (currentPage, pageCount) {
                return {
                    columns: [
                        { text: PERMISOS_EMPRESA + ' - PERMISOS POR TIPO DE USUARIO', alignment: 'left', fontSize: 7 },
                        { text: 'Página ' + currentPage + ' de ' + pageCount, alignment: 'right', fontSize: 7 }
                    ],
                    margin:[30, 0, 30, 0]
                };
            }
        };
        pdfMake.createPdf(doc).download('Permisos_Por_Tipo_Usuario_' + permFechaArchivo() + '.pdf');
        if (typeof Swal !== 'undefined' && typeof Swal.close === 'function') { Swal.close(); }
    });
}

// Modal con la información de permisos por tipo de usuario
function mostrarPermisosRol() {
    if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') return;
    var m = PERMISOS_MATRIZ || {};
    var rolesOrder = ['Admin', 'Editor', 'Super', 'Soft', 'Visor'];
    var acciones = PERMISOS_ACCIONES;
    var nombresModulos = PERMISOS_NOMBRES_MODULOS;
    var html = '';

    // Rol actual del usuario
    html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:0.625rem;background:rgba(14,165,233,0.12);border:0.0625rem solid rgba(14,165,233,0.35);border-radius:0.75rem;padding:0.75rem 0.875rem;margin-bottom:0.75rem;flex-wrap:wrap;">';
    html += '<div style="font-size:0.8125rem;color:#cbd5e1;"><i class="fas fa-user-shield" style="color:var(--blue);margin-right:0.375rem;"></i>Su usuario posee el rol de:</div>';
    html += '<span style="display:inline-flex;align-items:center;gap:0.375rem;background:linear-gradient(135deg,var(--accent),var(--accent-light));color:#fff;font-weight:700;padding:0.375rem 0.875rem;border-radius:6.1875rem;font-size:0.8125rem;"><i class="fas fa-user-tag"></i>' + permEsc(ROL_ACTUAL || 'Usuario') + '</span>';
    html += '</div>';

    // Botones de exportación / impresión (mismos .js que empleados.php)
    // Colores adaptados al tema activo (claros: light/orgullo)
    var temaModal = document.documentElement.getAttribute('data-theme');
    var temaClaro = (temaModal === 'light' || temaModal === 'orgullo');
    var cPdf = temaClaro ? '#b91c1c' : '#fca5a5';
    var cImp = temaClaro ? '#92400e' : '#fcd34d';
    html += '<div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;flex-wrap:wrap;">';
    html += '<button type="button" onclick="exportarPermisosPDF()" style="flex:1;min-width:8.125rem;padding:0.5625rem 0.75rem;border-radius:0.5rem;border:0.0625rem solid rgba(239,68,68,0.4);background:rgba(239,68,68,0.12);color:' + cPdf + ';font-weight:600;font-size:0.7812rem;cursor:pointer;"><i class="fas fa-file-pdf" style="margin-right:0.375rem;"></i> Exportar PDF</button>';
    html += '<button type="button" onclick="imprimirPermisos()" style="flex:1;min-width:8.125rem;padding:0.5625rem 0.75rem;border-radius:0.5rem;border:0.0625rem solid rgba(245,158,11,0.4);background:rgba(245,158,11,0.12);color:' + cImp + ';font-weight:600;font-size:0.7812rem;cursor:pointer;"><i class="fas fa-print" style="margin-right:0.375rem;"></i> Imprimir</button>';
    html += '</div>';

    html += '<div style="max-height:52vh; overflow:auto; text-align:left; padding-right:0.25rem;">';
    rolesOrder.forEach(function (rol) {
        var def = m[rol];
        if (!def) return;
        var esActual = (rol === ROL_ACTUAL);
        html += '<div style="margin-bottom:0.875rem;">';
        html += '<div style="display:flex;align-items:center;gap:0.5rem;background:' + (esActual ? 'rgba(14,165,233,0.18)' : 'rgba(var(--accent-rgb),0.10)') + ';border:0.0625rem solid ' + (esActual ? 'rgba(14,165,233,0.5)' : 'rgba(var(--accent-rgb),0.25)') + ';border-radius:0.625rem;padding:0.5rem 0.75rem;margin-bottom:0.375rem;">';
        html += '<i class="fas fa-user-tag" style="color:#93c5fd;"></i><span style="font-weight:700;color:#93c5fd;">' + rol + '</span>';
        if (esActual) html += '<span style="font-size:0.625rem;background:#0ea5e9;color:#fff;padding:0.125rem 0.5rem;border-radius:6.1875rem;">TU ROL</span>';
        html += '</div>';
        html += '<table style="width:100%;border-collapse:collapse;font-size:0.7812rem;">';
        html += '<tr>' + '<td style="padding:0.25rem 0.375rem;color:#9ca3af;font-size:0.6875rem;text-transform:uppercase;">Módulo</td>' + acciones.map(function (a) { return '<td style="padding:0.25rem 0.375rem;color:#9ca3af;font-size:0.6875rem;text-transform:uppercase;text-align:center;">' + a[1] + '</td>'; }).join('') + '</tr>';
        Object.keys(def).forEach(function (mod) {
            var perms = def[mod];
            var etiqueta = nombresModulos[mod] || mod;
            html += '<tr>';
            html += '<td style="padding:0.3125rem 0.375rem;color:#e5e7eb;border-top:0.0625rem solid rgba(255,255,255,0.06);">' + etiqueta + '</td>';
            acciones.forEach(function (a) {
                var ok = perms && perms[a[0]];
                html += '<td style="padding:0.3125rem 0.375rem;text-align:center;border-top:0.0625rem solid rgba(255,255,255,0.06);">' + (ok ? '<i class="fas fa-check-circle" style="color:' + a[2] + ';"></i>' : '<i class="fas fa-minus-circle" style="color:#4b5563;"></i>') + '</td>';
            });
            html += '</tr>';
        });
        html += '</table></div>';
    });
    html += '</div>';
    Swal.fire({
        title: '<i class="fas fa-shield-halved" style="color:var(--blue);"></i> Permisos por tipo de usuario',
        html: html,
        width:'55rem',
        heightAuto: false,
        showCloseButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Cerrar',
        confirmButtonColor: 'var(--accent)',
        background: '#17171f',
        color: '#ffffff'
    });
}

function mostrarModalSalvaRestaura() {
    if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') {
        var s = document.createElement('script');
        s.src = (window.location.pathname.indexOf('/modules/') !== -1 ? '../js/' : 'js/') + 'sweetalert2.all.min.js';
        s.onload = function () { mostrarModalSalvaRestaura(); };
        document.head.appendChild(s);
        return;
    }
    var restoreDisabled = !PUEDE_RESTAURAR;
    var html = '';
    html += '<div class="win-modal-titlebar">';
    html += '<div class="win-modal-titlebar-icon"><i class="fas fa-database"></i></div>';
    html += '<div class="win-modal-titlebar-text">';
    html += '<div style="font-size:0.8125rem;font-weight:600;">Salva / Restaura</div>';
    html += '<div style="font-size:0.6875rem;font-weight:400;opacity:0.7;">Gestión de copias de seguridad del sistema</div>';
    html += '</div>';
    html += '<div class="win-modal-titlebar-btns">';
    html += '<button type="button" class="win-modal-btn-close" onclick="Swal.close();" title="Cerrar"><i class="fas fa-times"></i></button>';
    html += '</div>';
    html += '</div>';
    html += '<div class="win-modal-body">';
    html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.625rem;">';

    html += '<button type="button" onclick="accionSalvaOrdinaria()" data-tooltip="Salva Ordinaria" data-tooltip-theme="success" style="display:flex;flex-direction:column;align-items:flex-start;gap:0.625rem;text-align:left;padding:1rem;border-radius:0.875rem;border:0.0625rem solid rgba(var(--color-success-soft-rgb),0.35);background:rgba(var(--color-success-soft-rgb),0.06);color:var(--txt);cursor:pointer;font-family:inherit;transition:.15s;" onmouseover="this.style.background=\'rgba(var(--color-success-soft-rgb),0.25)\';this.style.borderColor=\'rgba(var(--color-success-soft-rgb),0.6)\'" onmouseout="this.style.background=\'rgba(var(--color-success-soft-rgb),0.06)\';this.style.borderColor=\'rgba(var(--color-success-soft-rgb),0.35)\'">' +
        '<span style="width:2.375rem;height:2.375rem;border-radius:0.625rem;display:flex;align-items:center;justify-content:center;font-size:1.0625rem;color:var(--color-success-soft);background:rgba(var(--color-success-soft-rgb),0.15);"><i class="fas fa-download"></i></span>' +
        '<span style="font-weight:700;font-size:0.8125rem;">Salva Ordinaria</span>' +
        '<span style="font-size:0.6875rem;color:var(--muted);line-height:1.3;">Copia completa del sistema</span>' +
        '</button>';

    html += '<button type="button" onclick="accionSalvaConNombre()" data-tooltip="Salva con nombre" data-tooltip-theme="info" style="display:flex;flex-direction:column;align-items:flex-start;gap:0.625rem;text-align:left;padding:1rem;border-radius:0.875rem;border:0.0625rem solid rgba(var(--blue-soft-rgb),0.35);background:rgba(var(--blue-soft-rgb),0.06);color:var(--txt);cursor:pointer;font-family:inherit;transition:.15s;" onmouseover="this.style.background=\'rgba(var(--blue-soft-rgb),0.25)\';this.style.borderColor=\'rgba(var(--blue-soft-rgb),0.6)\'" onmouseout="this.style.background=\'rgba(var(--blue-soft-rgb),0.06)\';this.style.borderColor=\'rgba(var(--blue-soft-rgb),0.35)\'">' +
        '<span style="width:2.375rem;height:2.375rem;border-radius:0.625rem;display:flex;align-items:center;justify-content:center;font-size:1.0625rem;color:var(--blue);background:rgba(var(--blue-soft-rgb),0.15);"><i class="fas fa-file-export"></i></span>' +
        '<span style="font-weight:700;font-size:0.8125rem;">Salva con nombre (Recomendada)</span>' +
        '<span style="font-size:0.6875rem;color:var(--muted);line-height:1.3;">Backup con etiqueta</span>' +
        '</button>';

    if (restoreDisabled) {
        html += '<div style="display:flex;flex-direction:column;align-items:flex-start;gap:0.625rem;text-align:left;padding:1rem;border-radius:0.875rem;border:0.0625rem solid rgba(148,163,184,0.2);background:rgba(148,163,184,0.04);color:var(--faint);">' +
            '<span style="width:2.375rem;height:2.375rem;border-radius:0.625rem;display:flex;align-items:center;justify-content:center;font-size:1.0625rem;background:rgba(148,163,184,0.12);"><i class="fas fa-upload"></i></span>' +
            '<span style="font-weight:700;font-size:0.8125rem;">Restaurar</span>' +
            '<span style="font-size:0.6875rem;line-height:1.3;">Solo Admin/Soft/Editor</span>' +
            '</div>';
    } else {
        html += '<button type="button" onclick="accionRestaurar()" data-tooltip="Restaurar" data-tooltip-theme="warning" style="display:flex;flex-direction:column;align-items:flex-start;gap:0.625rem;text-align:left;padding:1rem;border-radius:0.875rem;border:0.0625rem solid rgba(var(--amber-soft-rgb),0.35);background:rgba(var(--amber-soft-rgb),0.06);color:var(--txt);cursor:pointer;font-family:inherit;transition:.15s;" onmouseover="this.style.background=\'rgba(var(--amber-soft-rgb),0.25)\';this.style.borderColor=\'rgba(var(--amber-soft-rgb),0.6)\'" onmouseout="this.style.background=\'rgba(var(--amber-soft-rgb),0.06)\';this.style.borderColor=\'rgba(var(--amber-soft-rgb),0.35)\'">' +
            '<span style="width:2.375rem;height:2.375rem;border-radius:0.625rem;display:flex;align-items:center;justify-content:center;font-size:1.0625rem;color:var(--amber);background:rgba(var(--amber-soft-rgb),0.15);"><i class="fas fa-upload"></i></span>' +
            '<span style="font-weight:700;font-size:0.8125rem;">Restaurar</span>' +
            '<span style="font-size:0.6875rem;color:var(--muted);line-height:1.3;">Recuperar desde copia</span>' +
            '</button>';
    }

    html += '</div>';
    html += '<div class="win-modal-footer"><i class="fas fa-info-circle me-1"></i> Incluye toda la Base de Datos y configuraciones. (formato ZIP).</div>';
    html += '</div>';

    Swal.fire({
        html: html,
        width:'37.5rem',
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: 'transparent',
        color: '#e2e8f0',
        customClass: { popup: 'salva-restaura-popup', container: 'salva-restaura-container' },
        padding:'0',
        didOpen: function () {
            var cont = (typeof Swal.getContainer === 'function') ? Swal.getContainer() : document.querySelector('.swal2-container');
            if (cont) { cont.style.background = 'transparent'; cont.style.alignItems = 'center'; }
            var popup = Swal.getPopup();
            if (popup) {
                popup.style.border = 'none';
                popup.style.background = 'transparent';
                popup.style.boxShadow = 'none';
                popup.style.padding = '0';
                popup.style.overflow = 'visible';
            }
            if (cont) {
                cont.addEventListener('click', function (e) {
                    if (e.target === cont && popup) {
                        popup.classList.remove('win-modal-pulse');
                        void popup.offsetWidth;
                        popup.classList.add('win-modal-pulse');
                    }
                });
            }
        }
    });
}

function accionSalvaOrdinaria() {
    Swal.close();
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #fbbf24;"></i> Salva del Sistema Manual',
        html: '<div style="text-align: left;"><p><i class="fas fa-info-circle me-2"></i> Se creará una copia de seguridad completa.</p><p><small>La copia incluirá: empleados, nóminas, configuración y vacaciones.</small></p><div class="alert alert-info mt-2"><i class="fas fa-clock me-1"></i> El archivo se guardará en formato ZIP</div></div>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        confirmButtonText: '<i class="fas fa-download me-2"></i>Generar Backup',
        background: '#1a1a2e',
        color: '#fff'
    }).then(function (result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#1a1a2e',
                color: '#fff'
            });
            fetch(AJAX_BACKUP_URL, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle me-2"></i> Backup Completado',
                        html: `<p>Archivo: ${data.filename}</p><p>Tamaño: ${data.size}</p><a href="${AJAX_PREFIX}${data.download_url}" class="btn btn-success" download><i class="fas fa-download me-2"></i> Descargar</a>`,
                        icon: 'success',
                        background: '#1a1a2e',
                        color: '#fff',
                        confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
                    });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-exclamation-triangle me-2"></i> Error',
                        text: data.message,
                        icon: 'error',
                        background: '#1a1a2e',
                        color: '#fff'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión',
                    icon: 'error',
                    background: '#1a1a2e',
                    color: '#fff'
                });
            });
        }
    });
}

function accionSalvaConNombre() {
    Swal.close();
    var html = '<div style="text-align:left;">';
    html += '<div class="alert alert-info mb-4" style="background: rgba(96, 165, 250, 0.12); border: 0.0625rem solid rgba(96, 165, 250, 0.35); border-radius: 0.75rem;">';
    html += '<div style="display: flex; align-items: flex-start; gap:0.75rem;">';
    html += '<i class="fas fa-info-circle fa-2x" style="color: var(--accent);"></i>';
    html += '<div><strong style="color: var(--accent);">Backup personalizado</strong><p class="mb-0 mt-1" style="color: #d1d5db;">Asigna un nombre descriptivo a tu backup para identificarlo fácilmente.</p></div>';
    html += '</div></div>';
    html += '<div class="mb-3"><label class="form-label"><i class="fas fa-tag me-1"></i> Nombre del backup <span class="text-danger">*</span></label>';
    html += '<input type="text" class="form-control" id="swalBackupName" placeholder="Ej: backup_pre_actualizacion_2026_07_12" maxlength="50" style="background:#1a1a2e;border:0.0625rem solid rgba(255,255,255,0.15);color:#fff;">';
    html += '<small class="text-secondary d-block mt-2"><i class="fas fa-info-circle me-1"></i> Se agregará automáticamente la fecha y hora: <span id="swalBackupPreview" style="color: var(--accent); font-family: monospace;">backup_sistema_YYYY_MM_DD_HH_MM</span></small>';
    html += '<small class="text-secondary d-block mt-1"><i class="fas fa-info-circle me-1"></i> Máximo 50 caracteres (solo letras, números, guiones y guiones bajos)</small>';
    html += '</div>';
    html += '<div class="mb-3 p-3" style="background: rgba(16, 185, 129, 0.05); border-radius: 0.5rem;">';
    html += '<h6 class="small" style="color: #9ca3af; margin-bottom:0.375rem;"><i class="fas fa-list-check me-1"></i> Contenido del backup:</h6>';
    html += '<ul class="small" style="list-style: none; padding-left:0; margin-bottom:0; color: #9ca3af;">';
    html += '<li style="color: #9ca3af;"><i class="fas fa-check-circle text-success me-1"></i> Estructura completa de la base de datos</li>';
    html += '<li style="color: #9ca3af;"><i class="fas fa-check-circle text-success me-1"></i> Datos de empleados y nóminas</li>';
    html += '<li style="color: #9ca3af;"><i class="fas fa-check-circle text-success me-1"></i> Configuración del sistema y tasas</li>';
    html += '<li style="color: #9ca3af;"><i class="fas fa-check-circle text-success me-1"></i> Historial de vacaciones y submayores</li>';
    html += '</ul></div>';
    html += '<div class="form-check"><input type="checkbox" class="form-check-input" id="swalBackupConfirm">';
    html += '<label class="form-check-label" for="swalBackupConfirm" style="color: #d1d5db; white-space: nowrap;"><i class="fas fa-check-circle me-1" style="color: #10b981;"></i> Confirmo que deseo crear este backup con el nombre especificado</label></div>';
    html += '</div>';
    Swal.fire({
        title: '<i class="fas fa-file-export me-2" style="color: #10b981;"></i> Backup con nombre personalizado',
        html: html,
        width:'43.75rem',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: '<i class="fas fa-download me-1"></i> Generar Backup',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCloseButton: true,
        didOpen: function () {
            var input = document.getElementById('swalBackupName');
            var cb = document.getElementById('swalBackupConfirm');
            var btn = Swal.getConfirmButton();
            if (cb && btn) btn.disabled = true;
            if (cb) cb.addEventListener('change', function () { if (btn) btn.disabled = !cb.checked; });
            if (input) {
                var updatePreview = function () {
                    var preview = document.getElementById('swalBackupPreview');
                    if (!preview) return;
                    var nombre = input.value.trim() || 'backup_sistema';
                    var limpio = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
                    var fecha = new Date();
                    var fechaStr = fecha.getFullYear() + '_' + String(fecha.getMonth() + 1).padStart(2, '0') + '_' + String(fecha.getDate()).padStart(2, '0') + '_' + String(fecha.getHours()).padStart(2, '0') + '_' + String(fecha.getMinutes()).padStart(2, '0');
                    preview.textContent = limpio + '_' + fechaStr;
                    preview.style.color = limpio.length > 0 ? 'var(--accent)' : '#f59e0b';
                };
                input.addEventListener('input', updatePreview);
                updatePreview();
            }
        },
        preConfirm: function () {
            var input = document.getElementById('swalBackupName');
            var cb = document.getElementById('swalBackupConfirm');
            var nombre = input ? input.value.trim() : '';
            if (!nombre) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle me-1"></i> Por favor, ingresa un nombre para identificar el backup');
                return false;
            }
            var limpio = nombre.replace(/[^a-zA-Z0-9_\-]/g, '_');
            if (limpio.length < 3) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle me-1"></i> El nombre debe tener al menos 3 caracteres');
                return false;
            }
            if (!cb || !cb.checked) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle me-1"></i> Debes marcar la casilla de confirmación para crear el backup');
                return false;
            }
            return limpio;
        }
    }).then(function (result) {
        if (result.isConfirmed && result.value) {
            realizarBackupConNombre(result.value);
        }
    });
}

function realizarBackupConNombre(nombre) {
    Swal.fire({
        title: '<i class="fas fa-spinner fa-pulse me-2"></i> Generando Backup...',
        html: `<p>Creando backup: <strong>${nombre}</strong></p><p class="text-muted small">Este proceso puede tomar unos segundos...</p>`,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        background: '#1a1a2e',
        color: '#fff'
    });

    fetch(AJAX_BACKUP_URL + '?nombre_custom=' + encodeURIComponent(nombre), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                title: '<i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Backup Completado',
                html: `
                    <div class="text-start">
                        <p><strong>Archivo:</strong> ${data.filename}</p>
                        <p><strong>Tamaño:</strong> ${data.size}</p>
                        <p><strong>Nombre asignado:</strong> ${data.nombre || nombre}</p>
                        <div class="mt-3">
                            <a href="${AJAX_PREFIX}${data.download_url}" class="btn btn-success w-100" download>
                                <i class="fas fa-download me-2"></i> Descargar Backup
                            </a>
                        </div>
                    </div>
                `,
                icon: 'success',
                background: '#1a1a2e',
                color: '#fff',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Entendido'
            });
        } else {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Error',
                text: data.message || 'Error al generar el backup',
                icon: 'error',
                background: '#1a1a2e',
                color: '#fff'
            });
        }
    })
    .catch(() => {
        Swal.close();
        Swal.fire({
            title: '<i class="fas fa-wifi me-2" style="color: #dc3545;"></i> Error de Conexión',
            text: 'No se pudo conectar con el servidor',
            icon: 'error',
            background: '#1a1a2e',
            color: '#fff'
        });
    });
}

function accionRestaurar() {
    if (!PUEDE_RESTAURAR) return;
    Swal.close();
    var html = '<div style="text-align:left;">';
    html += '<div class="alert alert-warning mb-4" style="background: rgba(245, 158, 11, 0.12); border: 0.0625rem solid rgba(245, 158, 11, 0.35); border-radius: 0.75rem;">';
    html += '<div style="display: flex; align-items: flex-start; gap:0.75rem;">';
    html += '<i class="fas fa-exclamation-triangle fa-2x" style="color: #fbbf24;"></i>';
    html += '<div><strong style="color: #fbbf24;">¡Precaución!</strong><p class="mb-0 mt-1" style="color: #d1d5db;">Esta acción SOBRESCRIBIRÁ todos los datos actuales. Asegúrate de tener un backup antes de continuar.</p></div>';
    html += '</div></div>';
    html += '<div class="mb-4 p-3" style="background: rgba(96, 165, 250, 0.08); border-radius: 0.625rem;">';
    html += '<div style="color:#d1d5db;"><i class="fas fa-info-circle me-2"></i> <strong>¿Qué se restaurará?</strong></div>';
    html += '<ul style="list-style: none; padding-left:0; margin-top:0.5rem;" class="mb-0">';
    html += '<li><i class="fas fa-check-circle text-success me-2"></i> Estructura completa de la base de datos</li>';
    html += '<li><i class="fas fa-check-circle text-success me-2"></i> Datos de empleados y nóminas</li>';
    html += '<li><i class="fas fa-check-circle text-success me-2"></i> Configuración del sistema y tasas</li>';
    html += '<li><i class="fas fa-check-circle text-success me-2"></i> Historial de vacaciones y submayores</li>';
    html += '</ul></div>';
    html += '<div class="form-group mb-3">';
    html += '<label class="form-label mb-2"><i class="fas fa-file-archive me-1"></i> Seleccionar archivo de backup:</label>';
    html += '<input type="file" name="backup_file" id="swalRestoreFile" class="form-control" accept=".sql,.zip" required style="background:#1a1a2e;border:0.0625rem solid rgba(255,255,255,0.15);color:#fff;">';
    html += '<small class="text-secondary d-block mt-2"><i class="fas fa-info-circle me-1"></i> Formatos soportados: .sql, .zip (máximo 300MB)</small>';
    html += '<small class="text-secondary d-block mt-1" id="swalFileSizeInfo"></small>';
    html += '</div>';
    html += '<div class="form-check mb-4 p-3" style="background: rgba(220, 53, 69, 0.08); border-radius: 0.5rem; border-left: 0.1875rem solid #dc3545;">';
    html += '<input type="checkbox" class="form-check-input" id="swalRestoreConfirm" required>';
    html += '<label class="form-check-label" for="swalRestoreConfirm" style="color: #fca5a5;">';
    html += '<i class="fas fa-exclamation-triangle me-1"></i> <strong>Confirmo que:</strong>';
    html += '<ul style="margin:0.3125rem 0 0 1.25rem; padding-left:0; list-style: none;">';
    html += '<li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Tengo un backup actual de la base de datos</li>';
    html += '<li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Comprendo que se sobrescribirán todos los datos</li>';
    html += '<li><i class="fas fa-check-circle text-success me-1" style="font-size:0.7rem;"></i> Deseo proceder con la restauración</li>';
    html += '</ul></label></div>';
    html += '</div>';
    Swal.fire({
        title: '<i class="fas fa-database me-2" style="color: #f97316;"></i> Restaurar Base de Datos',
        html: html,
        width:'37.5rem',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: '<i class="fas fa-upload me-2"></i> Restaurar Backup',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCloseButton: true,
        didOpen: function () {
            var cb = document.getElementById('swalRestoreConfirm');
            var btn = Swal.getConfirmButton();
            var fi = document.getElementById('swalRestoreFile');
            if (cb && btn) {
                btn.disabled = true;
                cb.addEventListener('change', function () { btn.disabled = !cb.checked; });
            }
            if (fi) {
                fi.addEventListener('change', function () {
                    var info = document.getElementById('swalFileSizeInfo');
                    if (!info) return;
                    if (fi.files && fi.files.length > 0) {
                        var sizeMB = (fi.files[0].size / 1048576).toFixed(2);
                        var color = fi.files[0].size > 300 * 1048576 ? '#fca5a5' : '#4ade80';
                        info.innerHTML = '<i class="fas fa-database me-1"></i> Tamaño del archivo: <strong style="color:' + color + ';">' + sizeMB + ' MB</strong>';
                    } else {
                        info.textContent = '';
                    }
                });
            }
        },
        preConfirm: function () {
            var fi = document.getElementById('swalRestoreFile');
            if (!fi || !fi.files || fi.files.length === 0) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle me-1"></i> Seleccione un archivo de backup');
                return false;
            }
            if (fi.files[0].size > 300 * 1048576) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle me-1"></i> El archivo supera el tamaño máximo permitido (300MB)');
                return false;
            }
            return fi.files[0];
        }
    }).then(function (result) {
        if (result.isConfirmed && result.value) {
            realizarRestaurarFetch(result.value);
        }
    });
}

function realizarRestaurarFetch(file) {
    var formData = new FormData();
    formData.append('backup_file', file);
    var progressUrl = AJAX_RESTORE_URL.replace('restore_db.php', 'restore_progress.php');
    Swal.fire({
        title: 'Restaurando...',
        html: '<style>.restore-spinner{width:2.375rem;height:2.375rem;margin:0 auto;border:0.25rem solid #334155;border-top-color:#14b8a6;border-radius:50%;animation:restore-spin .8s linear infinite;}@keyframes restore-spin{to{transform:rotate(360deg);}}</style>' +
              '<div class="text-center mb-2"><div class="restore-spinner"></div></div>' +
              '<p id="restoreStep" class="mb-2 text-light">Iniciando...</p>' +
              '<div class="progress" style="height:1.25rem; background:#2d2d3a; border-radius:0.625rem; overflow:hidden;">' +
              '<div id="restoreProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%; background:linear-gradient(90deg,var(--accent),var(--accent-light));">0%</div></div>' +
              '<p class="mt-2 mb-0"><span id="restoreProgressTable" class="text-info"></span>' +
              '<span id="restoreProgressPct" class="float-end text-light"></span></p>',
        allowOutsideClick: false,
        showConfirmButton: false,
        background: '#1a1a2e',
        color: '#fff'
    });

    var restorePoll = setInterval(function () {
        fetch(progressUrl, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var pct = d.percent || 0;
                var bar = document.getElementById('restoreProgressBar');
                var stepEl = document.getElementById('restoreStep');
                var tblEl = document.getElementById('restoreProgressTable');
                var pctEl = document.getElementById('restoreProgressPct');
                if (bar) { bar.style.width = pct + '%'; bar.textContent = pct + '%'; }
                if (stepEl && d.step) stepEl.textContent = d.step;
                if (tblEl) tblEl.textContent = d.table ? 'Tabla: ' + d.table : '';
                if (pctEl && d.total) pctEl.textContent = (d.processed || 0) + ' / ' + d.total + ' consultas';
            })
            .catch(function () {});
    }, 600);

    fetch(AJAX_RESTORE_URL, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        clearInterval(restorePoll);
        var bar = document.getElementById('restoreProgressBar');
        if (bar) { bar.style.width = '100%'; bar.textContent = '100%'; }
        if (data.success) {
            Swal.fire({
                title: 'Restauración Completada',
                html: `<pre style="background:#2d2d3a; padding:0.75rem; border-radius:0.5rem;">${data.message}</pre>`,
                icon: 'success',
                confirmButtonText: '<i class="fas fa-check me-2"></i> Recargar',
                background: '#1a1a2e',
                color: '#fff'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Error',
                html: `<pre style="background:#2d2d3a; padding:0.75rem; border-radius:0.5rem; color:#fca5a5;">${data.message}</pre>`,
                icon: 'error',
                background: '#1a1a2e',
                color: '#fff'
            });
        }
    })
    .catch(() => {
        clearInterval(restorePoll);
        Swal.fire({
            title: 'Error de Conexión',
            text: 'No se pudo conectar',
            icon: 'error',
            background: '#1a1a2e',
            color: '#fff'
        });
    });
}

// Abrir el modal desde el menú del usuario
document.getElementById('salvaRestauraBtn')?.addEventListener('click', function (e) {
    e.preventDefault();
    mostrarModalSalvaRestaura();
});

// Reloj
if (document.getElementById('liveClockMenu')) {
    function updateClockMenu() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        const clockElement = document.getElementById('liveClockMenu');
        if (clockElement) clockElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
    }
    updateClockMenu();
    setInterval(updateClockMenu, 1000);
}

// Logout
const logoutUserMenuBtn = document.getElementById('logoutUserMenuBtn');
if (logoutUserMenuBtn) {
    logoutUserMenuBtn.addEventListener('click', function(e) {
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

<?php include __DIR__ . '/theme_panel.php'; ?>
