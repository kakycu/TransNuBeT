<?php
// modules/users.php - Perfil de Usuario
require_once '../config/database.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Configuración regional para fechas en español
setlocale(LC_TIME, 'es_ES.utf8', 'spanish');

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// Cumpleaños del usuario logueado (fecha de nacimiento derivada del CI)
$cumpleanios_usuario_hoy = false;
$anios_cumplidos = 0;
$ci_logueado = preg_replace('/\D/', '', $_SESSION['usuario_ci'] ?? $_SESSION['user_ci'] ?? '');
if (strlen($ci_logueado) >= 6) {
    $mm_ci = (int)substr($ci_logueado, 2, 2);
    $dd_ci = (int)substr($ci_logueado, 4, 2);
    if (checkdate($mm_ci, $dd_ci, 2000) && ($mm_ci * 100 + $dd_ci) === (int)date('md')) {
        $cumpleanios_usuario_hoy = true;
        $yy_ci = (int)substr($ci_logueado, 0, 2);
        $anio_nac_ci = ($yy_ci <= (int)date('y')) ? (2000 + $yy_ci) : (1900 + $yy_ci);
        $anios_cumplidos = (int)date('Y') - $anio_nac_ci;
    }
}

// El permiso para ver perfiles de otros se evalúa con permiso_puede('usuarios', 'ver')

// Determinar el id a mostrar
$id = isset($_GET['id']) ? (int)$_GET['id'] : $usuario_actual_id;
if ($id <= 0) {
    $id = $usuario_actual_id;
}

// Cargar datos del usuario solicitado
$stmt = $pdo->prepare("
    SELECT u.*, r.codigo AS rol_codigo, r.descripcion AS rol_nombre
    FROM clasif_usuarios u
    LEFT JOIN clasif_rol r ON u.rol_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$usuario = $stmt->fetch();

$error_no_encontrado = false;
$es_admin = permiso_puede('usuarios', 'ver');
$puede_editar_usuario = permiso_puede('usuarios', 'editar');
$es_propio = ($id == $usuario_actual_id);

if (!$usuario) {
    if ($id == $usuario_actual_id) {
        $error_no_encontrado = true;
    } else {
        header('Location: users.php?id=' . $usuario_actual_id);
        exit();
    }
}

if (!$error_no_encontrado) {

// Control de acceso: perfil propio siempre; otros solo roles de administración
if (!$es_propio && !$es_admin) {
    header('Location: users.php?id=' . $usuario_actual_id);
    exit();
}

$nombre_completo = trim($usuario['nombre'] . ' ' . $usuario['apellidos']);
$iniciales = strtoupper(mb_substr($usuario['nombre'], 0, 1) . mb_substr($usuario['apellidos'], 0, 1));

$apellidos_parts = explode(' ', trim($usuario['apellidos'] ?? ''), 2);
$primer_apellido = $apellidos_parts[0] ?? '';
$segundo_apellido = $apellidos_parts[1] ?? '';

// ==========================================
// DATOS PERSONALES DERIVADOS DEL CI (AAMMDD + ... + digito sexo)
// ==========================================
function datosPersonalesDesdeCI($ci) {
    $ci = preg_replace('/\D/', '', (string)$ci);
    if (strlen($ci) !== 11) return null;
    $yy = (int)substr($ci, 0, 2);
    $mm = (int)substr($ci, 2, 2);
    $dd = (int)substr($ci, 4, 2);
    if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) return null;
    $anio2 = (int)date('y');
    $anio = ($yy <= $anio2) ? (2000 + $yy) : (1900 + $yy);
    try {
        $nac = new DateTime(sprintf('%04d-%02d-%02d', $anio, $mm, $dd));
    } catch (Exception $e) {
        return null;
    }
    $hoy = new DateTime('today');
    $edad_actual = $hoy->diff($nac)->y;

    // Próximo cumpleaños (este año si aún no pasó, si no el del próximo año)
    $prox = clone $nac;
    $prox->setDate((int)$hoy->format('Y'), (int)$nac->format('m'), (int)$nac->format('d'));
    if ($prox < $hoy) {
        $prox->modify('+1 year');
    }
    $dias_restantes = (int)$hoy->diff($prox)->format('%a');

    // Sexo: el décimo dígito del CI, par = masculino, impar = femenino
    $sexo = (((int)substr($ci, 9, 1)) % 2 === 0) ? 'Masculino' : 'Femenino';

    return [
        'fecha_nacimiento'   => $nac->format('d/m/Y'),
        'edad_actual'        => $edad_actual,
        'proximo_cumpleanos' => $prox->format('d/m/Y'),
        'edad_a_cumplir'     => $edad_actual + 1,
        'dias_para_cumple'   => $dias_restantes,
        'sexo'               => $sexo,
        'sexo_icono'         => ($sexo === 'Masculino') ? 'fa-mars' : 'fa-venus',
        'sexo_color'         => ($sexo === 'Masculino') ? '#60a5fa' : '#f472b6',
    ];
}
$datos_personales = datosPersonalesDesdeCI($usuario['no_ci'] ?? '');

// ==========================================
// CARGAR LOGO PARA MARCA DE AGUA EN CABECERA
// ==========================================
$logo_base64 = '';
$ruta_logo = __DIR__ . '/../../images/LogoTN.png';
if (file_exists($ruta_logo)) {
    $logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($ruta_logo));
}

// ==========================================
// RESOLVER FOTO DEL USUARIO
// ==========================================
$perfil_default_svg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228-5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');

$is_google_auth = (isset($_SESSION['auth_provider']) && $_SESSION['auth_provider'] === 'google');
$perfil_foto = $perfil_default_svg;

// Si es el perfil propio y viene de Google, usar la foto de Google
if ($es_propio && $is_google_auth && !empty($_SESSION['user_foto'])) {
    $perfil_foto = $_SESSION['user_foto'];
} elseif (!empty($usuario['foto'])) {
    $foto_valor = $usuario['foto'];

    if (strpos($foto_valor, 'data:image') === 0) {
        $perfil_foto = $foto_valor;
    } elseif (filter_var($foto_valor, FILTER_VALIDATE_URL)) {
        $perfil_foto = $foto_valor;
    } elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false && strpos($foto_valor, '.') === false) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $foto_valor);
        finfo_close($finfo);
        $perfil_foto = 'data:' . ($mime_type ?: 'image/jpeg') . ';base64,' . base64_encode($foto_valor);
    } else {
        $foto_rel = ltrim($foto_valor, './');
        $url_prefix = '../';   // users.php siempre se sirve desde /modules/
        $root_abs = dirname(__DIR__); // carpeta NOMINAS
        $candidatas = [];
        if (strpos($foto_rel, 'assets/') === 0) {
            $candidatas[] = $foto_rel;
        } else {
            $candidatas[] = 'assets/imagenes/' . $foto_rel;
            $candidatas[] = 'assets/imagenes/usuarios/' . basename($foto_rel);
            $candidatas[] = 'assets/imagenes/trabajadores/' . basename($foto_rel);
        }
        $perfil_foto = $perfil_default_svg;
        foreach ($candidatas as $cand) {
            if (file_exists($root_abs . '/' . $cand)) {
                $perfil_foto = $url_prefix . $cand;
                break;
            }
        }
    }
}

// Badge de rol (mismo estilo que configuración)
$rol_badge_clase = 'bg-secondary';
if ($usuario['rol_nombre'] == 'Administrador del Sistema') $rol_badge_clase = 'bg-danger';
elseif ($usuario['rol_nombre'] == 'Programador') $rol_badge_clase = 'bg-dark';
elseif ($usuario['rol_nombre'] == 'Supervisor General') $rol_badge_clase = 'bg-warning text-dark';
elseif ($usuario['rol_nombre'] == 'Contador / Editor') $rol_badge_clase = 'bg-info';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'SisGesNom'; ?> | Perfil de Usuario</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/cropper.css">

    <style>
	
        * { margin:0; padding:0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); overflow-x: hidden; color: #ffffff; }

        .win11-bg {
            position: fixed; top:0; left:0; width:100%; height:100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top:0; left:0; width:100%; height:100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .main-container { margin-left:16.25rem; transition: all 0.3s ease; min-height:100vh; padding:1.25rem; }
        .main-container.expanded { margin-left:5rem; }

        .win-topbar {
            background: var(--panel); backdrop-filter: blur(1.25rem); border-radius: 1rem;
            padding:0.75rem 1.5rem; margin-bottom:1.5rem; border: 0.0625rem solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            position: relative !important; z-index: 100 !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width:2.5rem; height:2.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size:1.5rem; font-weight: 600; margin:0; }
        .page-title p { font-size:0.8rem; color: rgba(255, 255, 255, 0.5); margin:0.25rem 0 0; }

        .glass-card {
            background: var(--panel-2); backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.06); border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            position: relative;
            overflow: hidden;
        }
        .glass-card:hover { transform: translateY(-0.125rem); background: var(--panel-2); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3); }

        /* Iconos de fondo para las cards */
        .glass-card .card-icon-bg {
            position: absolute;
            right:-0.625rem;
            bottom:-0.625rem;
            font-size:5rem;
            opacity: 0.08;
            pointer-events: none;
            z-index: 0;
            transform: rotate(-8deg);
        }
        .glass-card .card-content {
            position: relative;
            z-index: 1;
        }

        /* Variantes de color para los iconos de fondo */
        .glass-card .card-icon-bg.address { color: #60a5fa; }
        .glass-card .card-icon-bg.register { color: #f472b6; }
        .glass-card .card-icon-bg.birthday { color: #fbbf24; }
        .glass-card .card-icon-bg.cake { color: var(--color-success-soft); }
        .glass-card .card-icon-bg.email { color: #8b5cf6; }
        .glass-card .card-icon-bg.phone { color: #22d3ee; }
        .glass-card .card-icon-bg.ci { color: #f59e0b; }
        .glass-card .card-icon-bg.role { color: #ef4444; }

        /* Cabecera del perfil */
        .profile-cover {
            height:8.125rem;
            background: linear-gradient(135deg, rgba(0, 120, 212, 0.35) 0%, rgba(139, 92, 246, 0.35) 100%);
            border-radius: 0.75rem 0.75rem 0 0;
            position: relative;
            overflow: hidden;
        }
        .profile-cover::after {
            content: ''; position: absolute; inset:0;
            background-image: radial-gradient(circle at 85% 20%, rgba(255,255,255,0.15) 0%, transparent 50%);
        }
        .profile-cover-logo {
            position: absolute; inset:-60% -10% -60% -10%; z-index: 1;
            transform: rotate(-8deg);
            background-repeat: repeat;
            background-size: 5.25rem 5.25rem;
            opacity: 0.13;
            filter: grayscale(1) brightness(2);
            pointer-events: none;
        }
        .profile-cover-text {
            position: absolute; inset:0; z-index: 2;
            display: flex; align-items: center; justify-content: center;
            color: #ffffff; font-size:1.45rem; font-weight: 700;
            letter-spacing:0.1875rem; text-transform: uppercase;
            text-shadow: 0 0.125rem 0.875rem rgba(0, 0, 0, 0.55);
            pointer-events: none;
        }
        .profile-cover-text small {
            font-weight: 800; font-size:1.2rem; letter-spacing:0.125rem;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.25); border-radius: 62.4375rem; padding:0.25rem 0.875rem;
            border: 0.0625rem solid rgba(255, 255, 255, 0.25);
        }
        .profile-header {
            padding:0 1.5rem 1.5rem;
            position: relative;
        }
        .profile-avatar-large {
            width:6.875rem; height:6.875rem; border-radius: 50%;
            border: 0.25rem solid rgba(255, 255, 255, 0.15);
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative;
            margin-top:-3.4375rem;
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.4);
        }
        .profile-avatar-large img { width:100% !important; height:100% !important; max-width:100% !important; max-height:100% !important; object-fit: cover !important; object-position: center !important; border-radius: 50%; position: absolute; top:0; left:0; right:0; bottom:0; margin:0; }
        .profile-avatar-large .iniciales {
            font-size:2.4rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; justify-content: center; width:100%; height:100%;
        }
        .profile-name-lg { font-size:1.6rem; font-weight: 700; margin:0; }
        .profile-username { font-size:0.95rem; color: rgba(255, 255, 255, 0.5); }

        .info-item {
            display: flex; align-items: flex-start; gap:0.875rem;
            padding:0.875rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 0.0625rem solid rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            height:100%;
            position: relative;
            overflow: hidden;
        }
        .info-item:hover { background: rgba(96, 165, 250, 0.08); border-color: rgba(96, 165, 250, 0.2); }
        
        .info-item .card-icon-bg {
            position: absolute;
            right:-0.3125rem;
            bottom:-0.3125rem;
            font-size:3.5rem;
            opacity: 0.06;
            pointer-events: none;
            z-index: 0;
            transform: rotate(-5deg);
        }
        .info-item .info-content {
            position: relative;
            z-index: 1;
        }

        .info-icon {
            width:2.5rem; height:2.5rem; border-radius: 0.625rem; flex-shrink: 0;
            background: rgba(96, 165, 250, 0.1); border: 0.0625rem solid rgba(96, 165, 250, 0.2);
            display: flex; align-items: center; justify-content: center;
            color: #60a5fa; font-size:1rem;
            position: relative;
            z-index: 1;
        }
        .info-label { font-size:0.68rem; text-transform: uppercase; letter-spacing:0.0312rem; color: rgba(255, 255, 255, 0.4); font-weight: 600; }
        .info-value { font-size:0.9rem; color: #f1f5f9; margin-top:0.125rem; word-break: break-word; }
        .info-value code { color: #93c5fd; }

        .stat-box { text-align: center; padding:1rem 0.5rem; }
        .stat-box .h4 { margin:0; font-weight: 700; }
        .stat-box small { color: rgba(255, 255, 255, 0.45); }

        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 0.0625rem solid rgba(255, 255, 255, 0.1);
            border-radius: 0.625rem; color: white; font-size:0.85rem; transition: all 0.2s;
            cursor: pointer; padding:0.5rem 1rem; display: inline-flex; align-items: center; gap:0.5rem;
            text-decoration: none;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-0.0625rem); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-0.0625rem); }
        .btn-win-outline { background: transparent; }

        .pass-toggle {
            background: var(--panel); border: 0.0625rem solid rgba(255, 255, 255, 0.1);
            color: #9ca3af; transition: all 0.2s; border-radius: 0 0.625rem 0.625rem 0;
        }
        .pass-toggle:hover { color: #fff; background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.25); }

        .badge-rol { font-size:0.78rem; padding:0.375rem 0.875rem; }

        /* Dropdown de acciones del perfil */
        .perfil-acciones { position: relative; }
        .perfil-acciones .dropdown-menu {
            --bs-dropdown-bg:#16161f;
            --bs-dropdown-color:#e2e8f0;
            --bs-dropdown-border-color:rgba(96, 165, 250, 0.35);
            --bs-dropdown-link-color:#e2e8f0;
            --bs-dropdown-link-hover-color:#ffffff;
            --bs-dropdown-link-hover-bg:rgba(96, 165, 250, 0.2);
            --bs-dropdown-link-active-color:#ffffff;
            --bs-dropdown-link-active-bg:rgba(96, 165, 250, 0.35);
            background-color: #16161f !important;
            background-image: none !important;
            backdrop-filter: none;
            border: 0.0625rem solid rgba(96, 165, 250, 0.35);
            border-radius: 0.75rem;
            min-width:13.125rem;
            padding:0.375rem;
            box-shadow: 0 0.75rem 2.5rem rgba(0, 0, 0, 0.55);
            z-index: 1080 !important;
        }
        .perfil-acciones .dropdown-item {
            color: #e2e8f0;
            border-radius: 0.5rem;
            padding:0.625rem 0.875rem;
            font-size:0.85rem;
            transition: all 0.15s ease;
        }
        .perfil-acciones .dropdown-item i { width:1.125rem; text-align: center; }
        .perfil-acciones .dropdown-item:hover { background: rgba(var(--accent-rgb), 0.2); color: #ffffff; }
        .perfil-acciones .dropdown-item:active { background: rgba(96, 165, 250, 0.35); color: #ffffff; }
        .perfil-acciones .dropdown-item.text-danger { color: #f87171 !important; }
        .perfil-acciones .dropdown-item.text-danger:hover { background: rgba(239, 68, 68, 0.2) !important; color: #ffffff !important; }
        .perfil-acciones .dropdown-divider { border-color: rgba(148, 163, 184, 0.35); border-top-width: 0.0625rem; opacity: 1; margin:0.5rem 0.25rem; }
        .perfil-acciones .dropdown-toggle::after { color: rgba(255, 255, 255, 0.6); }

        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: translateY(0); } }

        ::-webkit-scrollbar { width:0.5rem; height:0.5rem; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 0.625rem; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }

        /* ========================================== */
        /* MODAL USUARIO MEJORADO - DISEÑO Y UX       */
        /* ========================================== */

        .btn-win-sm { padding:0.25rem 0.75rem; font-size:0.75rem; color: #ffffff !important; }
        .btn-win-sm:hover { color: #ffffff !important; }
        .btn-win-danger { background: rgba(220, 53, 69, 0.2); border-color: rgba(220, 53, 69, 0.5); }
        .btn-win-danger:hover { background: rgba(220, 53, 69, 0.4); border-color: #dc3545; }

        .form-label { color: rgba(255, 255, 255, 0.85); font-size:0.8rem; font-weight: 500; margin-bottom:0.375rem; }

        .form-control, .form-select, input.form-control, textarea.form-control, select.form-select {
            background: var(--panel) !important;
            border: 0.0625rem solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 0.625rem !important;
            color: #ffffff !important;
            padding:0.5rem 0.75rem !important;
            font-size:0.85rem !important;
        }

        /* Fix: chevron visible en selects (tema oscuro) */
        .form-select, select.form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            background-color: var(--panel) !important;
            padding-right:2.5rem !important;
        }

        /* Chevron apuntando arriba mientras el dropdown está abierto */
        .form-select.select-open {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2360a5fa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 15 12 9 18 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 1rem !important;
            background-color: var(--panel) !important;
            padding-right:2.5rem !important;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--panel) !important;
            border-color: #60a5fa !important;
            outline: none !important;
            box-shadow: 0 0 0 0.125rem rgba(96, 165, 250, 0.2) !important;
            color: #ffffff !important;
        }

        .form-check-input { background-color: var(--panel); border-color: rgba(255, 255, 255, 0.2); }
        .form-check-input:checked { background-color: var(--color-success); border-color: var(--color-success); }
        .form-check-input:disabled { background-color: var(--panel-2); border-color: rgba(255, 255, 255, 0.15); }
        .form-check-input:checked:disabled { background-color: rgba(96, 165, 250, 0.35); border-color: rgba(96, 165, 250, 0.5); }
        .form-check-label { color: rgba(255, 255, 255, 0.85); }

        .text-muted, .text-secondary { color: #9ca3af !important; }

        .modal-content-win {
            background: linear-gradient(135deg, var(--card), var(--panel-2));
            backdrop-filter: blur(0.625rem);
            border: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.25rem;
            color: var(--txt);
        }
        .modal-header-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-bottom: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 1.125rem 1.125rem 0 0;
        }
        .modal-footer-win {
            background: linear-gradient(90deg, var(--panel), var(--panel-2));
            border-top: 0.0625rem solid rgba(96, 165, 250, 0.3);
            border-radius: 0 0 1.125rem 1.125rem;
        }

        .modal-avatar-header {
            width:3.5rem; height:3.5rem; border-radius: 50%;
            border: 0.125rem solid #60a5fa; overflow: hidden;
            background: var(--panel); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .modal-avatar-header img { width:100% !important; height:100% !important; max-width:100% !important; max-height:100% !important; object-fit: cover !important; object-position: center !important; border-radius: 50%; display: block; position: absolute; top:0; left:0; right:0; bottom:0; margin:0; }
        .modal-avatar-header .avatar-iniciales-lg {
            width:100%; height:100%; font-size:1.15rem; font-weight: 700;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center; color: #fff;
        }

        .mode-badge {
            display: inline-flex; align-items: center; gap:0.375rem;
            padding:0.1875rem 0.625rem; border-radius: 1.25rem; font-size:0.66rem;
            font-weight: 600; letter-spacing:0.0312rem; text-transform: uppercase;
            white-space: nowrap;
        }
        .mode-badge.create { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success-soft); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.4); }
        .mode-badge.edit { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 0.0625rem solid rgba(59, 130, 246, 0.4); }

        .modal-subtitle { font-size:0.75rem; color: rgba(255, 255, 255, 0.5); }

        .section-title {
            display: flex; align-items: center; gap:0.5rem;
            font-size:0.78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing:0.05rem; color: rgba(255, 255, 255, 0.75);
            padding-bottom:0.5rem; margin-bottom:1rem;
            border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.08);
        }
        .section-title i { color: #60a5fa; font-size:0.85rem; }

        .usuario-modal-body {
            max-height:calc(100vh - 16.25rem);
            overflow-y: auto;
        }
        .usuario-modal-body::-webkit-scrollbar { width:0.375rem; }
        .usuario-modal-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 0.625rem; }

        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right:2.625rem !important; }
        .password-toggle {
            position: absolute; right:0.375rem; top:50%; transform: translateY(-50%);
            background: transparent; border: none; color: rgba(255, 255, 255, 0.5);
            padding:0.375rem 0.5rem; cursor: pointer; z-index: 5;
        }
        .password-toggle:hover { color: #60a5fa; }

        .form-usuario-status {
            display: flex; align-items: center; gap:0.5rem;
            padding:0.625rem 0.875rem; border-radius: 0.625rem; font-size:0.78rem;
            margin-bottom:1.125rem;
        }
        .form-usuario-status.edit { background: rgba(59, 130, 246, 0.1); border: 0.0625rem solid rgba(59, 130, 246, 0.25); color: #93c5fd; }
        .form-usuario-status.create { background: rgba(var(--color-success-rgb), 0.1); border: 0.0625rem solid rgba(var(--color-success-rgb), 0.25); color: var(--color-success-soft); }

        .btn-guardar-loading { pointer-events: none; opacity: 0.7; }

        /* Avatar preview */
        .avatar-preview {
            width:6.25rem;
            height:6.25rem;
            border-radius: 50%;
            border: 0.1875rem solid #60a5fa;
            margin:0 auto;
            overflow: hidden;
            background: var(--panel);
            position: relative;
            cursor: pointer;
        }
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height:100%;
            width:100%;
            flex-direction: column;
            font-size:2rem;
            color: rgba(255, 255, 255, 0.4);
        }
        .edit-overlay {
            position: absolute;
            bottom:0;
            left:0;
            right:0;
            background: rgba(0, 0, 0, 0.75);
            color: white;
            text-align: center;
            padding:0.5rem;
            font-size:0.5625rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0 0 50% 50%;
        }
        .avatar-preview:hover .edit-overlay { opacity: 1; }

        /* Crop modal */
        .img-container {
            background-color: #2d2d2d;
            background-image: linear-gradient(45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(-45deg, #3d3d3d 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, #3d3d3d 75%),
                              linear-gradient(-45deg, transparent 75%, #3d3d3d 75%);
            background-size: 1.25rem 1.25rem;
            min-height:25rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .preview-container {
            width:9.375rem;
            height:9.375rem;
            margin:0 auto;
            overflow: hidden;
            border: 0.1875rem solid #60a5fa;
            border-radius: 50%;
            background: #2d2d2d;
        }

        hr { opacity: 1; border-color: rgba(148, 163, 184, 0.25); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }

        .swal2-popup { background: var(--panel) !important; color: var(--txt) !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }
        .info-value a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Menú lateral" data-tooltip="Menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-user-circle me-2" style="color: #60a5fa;"></i>Perfil de Usuario</h1>
                <p><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($es_propio ? 'Su información personal y de acceso' : 'Información del usuario del sistema'); ?></p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <?php if (!$es_propio): ?>
    <div class="mb-3 fade-in-up" style="animation-delay: 0.05s;">
        <a href="usuarios.php" class="btn-win btn-win-outline btn-win-sm" title="Volver a Gestión de Usuarios" data-tooltip="Volver a Gestión de Usuarios" data-tooltip-theme="primary"><i class="fas fa-arrow-left me-1"></i> Volver a Gestión de Usuarios</a>
    </div>
    <?php endif; ?>

    <?php if ($error_no_encontrado): ?>
    <div class="glass-card fade-in-up p-5 text-center" style="animation-delay: 0.08s;">
        <i class="fas fa-user-slash" style="font-size:3.5rem; color: #f87171; margin-bottom:1rem;"></i>
        <h4 class="mb-2">Usuario no encontrado</h4>
        <p class="mb-4" style="color: rgba(255, 255, 255, 0.55);">
            El usuario solicitado no existe o fue eliminado del sistema.
        </p>
        <a href="usuarios.php" class="btn-win btn-win-primary">
            <i class="fas fa-user-shield me-1"></i> Ir a Gestión de Usuarios
        </a>
    </div>
    <?php endif; ?>

    <?php if (!$error_no_encontrado): ?>
    <!-- Cabecera del perfil -->
    <div class="glass-card fade-in-up mb-4" style="animation-delay: 0.08s; position: relative; z-index: 20; overflow: visible;">
        <div class="profile-cover">
            <?php if ($logo_base64): ?>
            <div class="profile-cover-logo" style="background-image: url('<?php echo $logo_base64; ?>');"></div>
            <?php endif; ?>
            <div class="profile-cover-text">
                PERFIL DEL USUARIO<span class="mx-2" style="color: rgba(255,255,255,0.4);">:</span>
                <small><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($usuario['usuario']); ?></small>
            </div>
        </div>
        <div class="profile-header d-flex flex-column flex-sm-row align-items-sm-end gap-3">
            <div class="profile-avatar-large">
                <img src="<?php echo htmlspecialchars($perfil_foto); ?>" alt="Foto de perfil"
				     referrerpolicy="no-referrer" 
                     style="display: none;"
                     onload="this.style.display='block'; this.parentElement.querySelector('.iniciales').style.display='none';"
                     onerror="this.style.display='none'; this.parentElement.querySelector('.iniciales').style.display='flex';">
                <span class="iniciales" style="display: flex;"><?php echo htmlspecialchars($iniciales); ?></span>
            </div>
            <div class="flex-grow-1 pb-1">
                <h2 class="profile-name-lg"><?php echo htmlspecialchars($nombre_completo); ?></h2>
					<div class="profile-username">
						<i class="fas fa-at me-1"></i><?php echo htmlspecialchars($usuario['usuario']); ?>
						<span class="mx-2" style="color: rgba(255,255,255,0.15)">|</span>
						<i class="fas fa-fingerprint me-1"></i>CI: <?php echo htmlspecialchars($usuario['no_ci']); ?>
						<?php if ($datos_personales): ?>
						<span class="mx-2" style="color: rgba(255,255,255,0.15)">|</span>
						<i class="fas <?php echo $datos_personales['sexo_icono']; ?>" style="color: <?php echo $datos_personales['sexo_color']; ?>;"></i>
						<?php echo htmlspecialchars($datos_personales['sexo']); ?>
						<?php endif; ?>
						<?php if ($es_propio): ?>
						<span class="mx-2" style="color: rgba(255,255,255,0.15)">|</span>
						<!-- Badge de tipo de autenticación -->
						<?php if ($is_google_auth): ?>
							<span class="badge bg-danger"><i class="fab fa-google me-1"></i>Acceso Google</span>
						<?php else: ?>
							<span class="badge bg-info"><i class="fas fa-database me-1"></i>Acceso Base Datos</span>
						<?php endif; ?>

						<!-- Badge de estado de contraseña (siempre visible) -->
						<span class="mx-2" style="color: rgba(255,255,255,0.15)">|</span>
						<?php if ($is_google_auth): ?>
							<span class="badge bg-secondary fst-italic" style="cursor: default; opacity: 0.7;">
								<i class="fas fa-lock me-1"></i>Contraseña no modificable
							</span>
						<?php else: ?>
							<a href="<?= $base_prefix ?>modules/users.php?id=<?= $user_id ?>&cambiar_pass=1" 
							   class="badge bg-warning text-dark text-decoration-none">
								<i class="fas fa-edit me-1"></i>Cambiar contraseña
							</a>
						<?php endif; ?>
						<?php endif; ?>
					</div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 pb-1">
                <span class="badge badge-rol <?php echo $rol_badge_clase; ?>">
                    <i class="fas fa-user-tag me-1"></i><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?>
                </span>
                <span class="badge badge-rol <?php echo $usuario['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                    <i class="fas fa-<?php echo $usuario['activo'] ? 'check-circle' : 'ban'; ?> me-1"></i>
                    <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                </span>
<div class="dropdown perfil-acciones">
    <button type="button" class="btn-win btn-win-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones" data-tooltip="Acciones" data-tooltip-theme="primary">
        <i class="fas fa-ellipsis-vertical me-1"></i> Acciones
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); editarUsuario(<?php echo (int)$id; ?>);"><i class="fas fa-user-edit me-2" style="color:#60a5fa;"></i>Editar Perfil</a></li>
        <?php if ($es_propio): ?>
        <li>
            <?php if ($is_google_auth): ?>
                <a class="dropdown-item disabled text-muted fst-italic" 
                   href="#" 
                   tabindex="-1" 
                   aria-disabled="true" 
                   style="pointer-events: none; cursor: default; opacity: 0.6;">
                    <i class="fas fa-key me-2" style="color:#9ca3af;"></i>Cambiar Contraseña
                </a>
            <?php else: ?>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalCambiarPassword">
                    <i class="fas fa-key me-2" style="color:#fbbf24;"></i>Cambiar Contraseña
                </a>
            <?php endif; ?>
        </li>
        <?php endif; ?>
        <?php if (($es_propio && $es_admin) || !$es_propio): ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="usuarios.php"><i class="fas fa-user-cog me-2" style="color:#a78bfa;"></i>Gestionar Usuarios</a></li>
        <?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" id="btnCerrarSesionPerfil"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
    </ul>
</div>
            </div>
        </div>
    </div>

    <!-- Información de contacto con iconos de fondo -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.1s;">
            <div class="info-item">
                <i class="fas fa-envelope card-icon-bg email"></i>
                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                <div class="info-content">
                    <div class="info-label">Correo Electrónico</div>
                    <div class="info-value">
                        <?php if (!empty($usuario['email'])): ?>
                            <a href="mailto:<?php echo rawurlencode($usuario['email']); ?>?subject=<?php echo rawurlencode('Correo desde la Página del Perfil del Sistema de Nominas'); ?>">
                                <?php echo htmlspecialchars($usuario['email']); ?>
                            </a>
                        <?php else: ?>
                            No especificado
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.14s;">
            <div class="info-item">
                <i class="fas fa-phone-alt card-icon-bg phone"></i>
                <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="info-content">
                    <div class="info-label">Teléfono de Contacto</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['telefono_contacto'] ?? 'No especificado'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.18s;">
            <div class="info-item">
                <i class="fas fa-id-badge card-icon-bg ci"></i>
                <div class="info-icon"><i class="fas fa-id-badge"></i></div>
                <div class="info-content">
                    <div class="info-label">Carné de Identidad</div>
                    <div class="info-value"><code><?php echo htmlspecialchars($usuario['no_ci']); ?></code></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.22s;">
            <div class="info-item">
                <i class="fas fa-user-shield card-icon-bg role"></i>
                <div class="info-icon"><i class="fas fa-user-shield"></i></div>
                <div class="info-content">
                    <div class="info-label">Rol del Sistema</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?></div>
                </div>
            </div>
        </div>
    </div>





    <!-- Dirección + Registro + Datos Personales en una sola fila con iconos de fondo -->
    <div class="row g-3 mb-4">
        <!-- Dirección Particular - col-2 -->
        <div class="col-3 fade-in-up" style="animation-delay: 0.26s;">
            <div class="glass-card p-3 h-100">
                <i class="fas fa-map-marker-alt card-icon-bg address"></i>
                <div class="card-content">
                    <h6 class="mb-2 fw-semibold" style="font-size:0.75rem;"><i class="fas fa-map-marker-alt me-2" style="color: #60a5fa;"></i>Dirección</h6>
                    <p class="mb-0" style="color: rgba(255, 255, 255, 0.85); font-size:0.8rem; word-break: break-word;">
                        <?php echo htmlspecialchars($usuario['direccion_particular'] ?? 'No especificada'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Registro del Usuario - col-2 -->
        <div class="col-3 fade-in-up" style="animation-delay: 0.3s;">
            <div class="glass-card p-3 h-100">
                <i class="fas fa-history card-icon-bg register"></i>
                <div class="card-content">
                    <h6 class="mb-2 fw-semibold" style="font-size:0.75rem;"><i class="fas fa-history me-2" style="color: #f472b6;"></i>Registro</h6>
                    <div class="d-flex flex-column gap-1">
                        <div>
                            <div class="info-label" style="font-size:0.6rem;">Fecha de Registro</div>
                            <div class="info-value" style="font-size:0.75rem;"><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></div>
                        </div>
                        <div>
                            <div class="info-label" style="font-size:0.6rem;">Última Actualización</div>
                            <div class="info-value" style="font-size:0.75rem;"><?php echo date('d/m/Y', strtotime($usuario['fecha_actualizacion'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Datos Personales - Nacimiento - col-2 -->
        <div class="col-3 fade-in-up" style="animation-delay: 0.34s;">
            <div class="glass-card p-3 h-100">
                <i class="fas fa-calendar-alt card-icon-bg birthday"></i>
                <div class="card-content">
                    <h6 class="mb-2 fw-semibold" style="font-size:0.75rem;"><i class="fas fa-calendar-alt me-2" style="color: #fbbf24;"></i>Nacimiento</h6>
                    <?php if ($datos_personales): ?>
                    <div class="d-flex flex-column gap-1">
                        <div>
                            <div class="info-label" style="font-size:0.6rem;">Fecha de Nacimiento</div>
                            <div class="info-value" style="font-size:0.75rem;"><?php echo htmlspecialchars($datos_personales['fecha_nacimiento']); ?></div>
                        </div>
                        <div>
                            <div class="info-label" style="font-size:0.6rem;">Edad Actual</div>
                            <div class="info-value" style="font-size:0.75rem;"><strong><?php echo $datos_personales['edad_actual']; ?></strong> años</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="mb-0" style="color: rgba(255,255,255,0.5); font-size:0.7rem;">
                        <i class="fas fa-exclamation-triangle me-1" style="color:#f59e0b;"></i> CI inválido
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
<!-- Datos Personales - Cumpleaños - col-2 -->
<div class="col-3 fade-in-up" style="animation-delay: 0.38s;">
    <div class="glass-card p-3 h-100">
        <i class="fas fa-birthday-cake card-icon-bg cake"></i>
        <div class="card-content">
<h6 class="mb-2 fw-semibold" style="font-size:0.75rem;">
    <i class="fas fa-birthday-cake me-2" style="color: var(--color-success-soft);"></i>
    Cumpleaños
    <?php if ($datos_personales): ?>
    <span style="color: var(--color-success-soft); font-size:0.65rem; margin-left:0.25rem;">
        (Cumple <?php echo $datos_personales['edad_a_cumplir']; ?> años)
    </span>
    <?php endif; ?>
</h6>
            <?php if ($datos_personales): ?>
            <div class="d-flex flex-column gap-1">
                <div>
                    <div class="info-value" style="font-size:0.75rem;"><?php echo htmlspecialchars($datos_personales['proximo_cumpleanos']); ?></div>
                    <?php if ((int)$datos_personales['dias_para_cumple'] === 0): ?>
                    <div class="info-value" style="font-size:0.65rem;color:#fbbf24;font-weight:600;"><i class="fas fa-birthday-cake me-1"></i>¡HOY!</div>
                    <?php else: ?>
                    <div class="info-value" style="font-size:0.65rem;color:rgba(255,255,255,0.5);">en <?php echo $datos_personales['dias_para_cumple']; ?> día(s)</div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="info-label" style="font-size:0.6rem;">Sexo</div>
                    <div class="info-value" style="font-size:0.75rem;">
                        <i class="fas <?php echo $datos_personales['sexo_icono']; ?> me-1" style="color: <?php echo $datos_personales['sexo_color']; ?>;"></i>
                        <?php echo htmlspecialchars($datos_personales['sexo']); ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <p class="mb-0" style="color: rgba(255,255,255,0.5); font-size:0.7rem;">
                <i class="fas fa-exclamation-triangle me-1" style="color:#f59e0b;"></i> CI inválido
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
	
	</div>




    <!-- Sección de Datos Personales completa (oculta) -->
    <div class="glass-card fade-in-up p-4 mb-4" style="animation-delay: 0.42s; display:none;">
        <!-- Este bloque se oculta porque ya mostramos los datos en la fila superior -->
    </div>

    <?php endif; ?>

    <?php if ($es_propio): ?>
    <!-- Modal Cambiar Contraseña -->
    <div class="modal fade" id="modalCambiarPassword" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #1a1a21; border: 0.0625rem solid rgba(255,255,255,0.08); border-radius: 0.875rem; color: #fff;">
                <div class="modal-header" style="border-bottom: 0.0625rem solid rgba(255,255,255,0.08);">
                    <h5 class="modal-title"><i class="fas fa-key me-2" style="color: #60a5fa;"></i> Cambiar Contraseña</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
                </div>
                <form id="formCambiarPassword">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.85);">Contraseña actual</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="passActual" required autocomplete="current-password"
                                       style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.1); color: var(--txt);">
                                <button type="button" class="btn pass-toggle" tabindex="-1" onclick="togglePassVisibility('passActual', this)" title="Mostrar / ocultar contraseña" data-tooltip="Mostrar / ocultar contraseña" data-tooltip-theme="warning">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.85);">Nueva contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="passNueva" required minlength="6" autocomplete="new-password"
                                       style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.1); color: var(--txt);">
                                <button type="button" class="btn pass-toggle" tabindex="-1" onclick="togglePassVisibility('passNueva', this)" title="Mostrar / ocultar contraseña" data-tooltip="Mostrar / ocultar contraseña" data-tooltip-theme="warning">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-white-50">Mínimo 6 caracteres</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.85);">Confirmar nueva contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="passConfirmar" required autocomplete="new-password"
                                       style="background: var(--panel); border: 0.0625rem solid rgba(255,255,255,0.1); color: var(--txt);">
                                <button type="button" class="btn pass-toggle" tabindex="-1" onclick="togglePassVisibility('passConfirmar', this)" title="Mostrar / ocultar contraseña" data-tooltip="Mostrar / ocultar contraseña" data-tooltip-theme="warning">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div id="passFeedback" class="small text-danger d-none"></div>
                    </div>
                    <div class="modal-footer" style="border-top: 0.0625rem solid rgba(255,255,255,0.08);">
                        <button type="button" class="btn-win" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cancelar</button>
                        <button type="submit" class="btn-win btn-win-primary" id="btnGuardarPass" title="Guardar contraseña" data-tooltip="Guardar contraseña" data-tooltip-theme="success"><i class="fas fa-save me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$error_no_encontrado): ?>
    <!-- Modal para crear/editar usuarios con crop -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-win">
                <div class="modal-header modal-header-win d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="modal-avatar-header" id="headerAvatarModal">
                            <div class="avatar-iniciales-lg" id="headerAvatarIniciales">+</div>
                            <img id="headerAvatarImg" src="" alt="" style="display:none;">
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <h5 class="modal-title mb-0" id="modalUsuarioTitle"><i class="fas fa-user-edit me-2"></i> Editar Perfil</h5>
                                <span class="mode-badge edit" id="modalModeBadge"><i class="fas fa-edit"></i> Editar</span>
                            </div>
                            <span class="modal-subtitle" id="modalUsuarioSubtitle">Perfil del usuario del sistema</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"></button>
                </div>
                <form id="formUsuario" method="POST" autocomplete="off">
                    <input type="hidden" name="action" id="usuarioAction" value="editar">
                    <input type="hidden" name="usuario_id" id="usuarioId" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="imagen_recortada" id="imagen_recortada_usuario" value="">
                    <input type="hidden" name="eliminar_foto" id="eliminarFotoUsuarioInput" value="0">
                    <div class="modal-body p-4 usuario-modal-body">
                        <div class="form-usuario-status edit" id="formUsuarioStatus">
                            <i class="fas fa-user-edit"></i>
                            <span id="formUsuarioStatusText">Editando el perfil del usuario</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="avatar-preview mb-2" id="avatarPreviewUsuario" onclick="abrirEditorFotoUsuario()" style="width:7.5rem; height:7.5rem;">
                                        <div id="fotoPlaceholderUsuario" class="avatar-placeholder"><i class="fas fa-camera"></i><small style="font-size:0.6rem; margin-top:0.25rem;">Foto</small></div>
                                        <img id="imagePreviewUsuario" src="" style="display: none; width:100%; height:100%; object-fit: cover;">
                                        <div class="edit-overlay py-1"><i class="fas fa-crop-alt me-1"></i> Editar</div>
                                    </div>
                                    <div class="d-grid gap-1">
                                        <label class="btn-win btn-win-sm py-1" style="font-size:0.7rem;" title="Subir foto de perfil" data-tooltip="Subir foto de perfil" data-tooltip-theme="info"><i class="fas fa-upload me-1"></i> Subir Foto<input type="file" id="imageUploadUsuario" accept="image/jpeg,image/png" hidden onchange="cargarImagenUsuario(this)"></label>
                                        <button type="button" class="btn-win btn-win-danger btn-win-sm py-1" id="btnEliminarFotoUsuario" style="display: none;" onclick="window.eliminarFotoUsuario()" title="Eliminar foto de perfil" data-tooltip="Eliminar foto de perfil" data-tooltip-theme="danger"><i class="fas fa-trash-alt me-1"></i> Eliminar</button>
                                    </div>
                                    <div class="form-check form-switch d-flex justify-content-center mt-3 mb-1">
                                        <input type="checkbox" class="form-check-input" name="activo" id="activo_usuario" checked>
                                        <label class="form-check-label" for="activo_usuario" id="estadoUsuarioLabel">Usuario Activo</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="section-title"><i class="fas fa-key"></i> Datos de Acceso</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Usuario <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="usuario" id="usuario" required data-tooltip="Nombre de usuario para acceso" data-tooltip-theme="info">
                                        <small class="text-muted" id="usuarioFeedback"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contraseña <span class="text-muted">(opcional)</span></label>
                                        <div class="password-wrapper">
                                            <input type="password" class="form-control" name="password" id="password" autocomplete="new-password" data-tooltip="Contraseña de acceso" data-tooltip-theme="warning">
                                            <button type="button" class="password-toggle" id="togglePasswordBtn" onclick="togglePassword()" tabindex="-1" data-tooltip="Mostrar/ocultar contraseña" data-tooltip-theme="warning"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <small class="text-muted" id="passHelp">Dejar en blanco para mantener la actual</small>
                                    </div>
                                </div>
                                <div class="section-title"><i class="fas fa-id-card"></i> Datos Personales</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" class="form-control" name="nombre" id="nombre" required data-tooltip="Nombre completo del usuario" data-tooltip-theme="info"><small class="text-muted" id="nombreFeedback"></small></div>
                                    <div class="col-md-4"><label class="form-label">Primer Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="primer_apellido" id="primer_apellido" required data-tooltip="Primer apellido" data-tooltip-theme="info"><small class="text-muted" id="apellido1Feedback"></small></div>
                                    <div class="col-md-4"><label class="form-label">Segundo Apellido <span class="text-danger">*</span></label><input type="text" class="form-control" name="segundo_apellido" id="segundo_apellido" required data-tooltip="Segundo apellido" data-tooltip-theme="info"><small class="text-muted" id="apellido2Feedback"></small></div>
                                    <div class="col-md-6"><label class="form-label">Carnet de Identidad <span class="text-danger">*</span></label><input type="text" class="form-control" name="no_ci" id="no_ci" maxlength="11" required data-tooltip="Número de Carnet de Identidad" data-tooltip-theme="info"><small class="text-muted" id="ciFeedbackUsuario"></small></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="email" data-tooltip="Correo electrónico de contacto" data-tooltip-theme="info"><small class="text-muted" id="emailFeedback"></small></div>
                                    <div class="col-md-6"><label class="form-label">Teléfono de Contacto</label><input type="text" class="form-control" name="telefono_contacto" id="telefono_contacto" maxlength="15" data-tooltip="Número de teléfono de contacto" data-tooltip-theme="info"><small class="text-muted" id="telefonoFeedback"></small></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Rol <span class="text-danger">*</span></label>
                                        <select class="form-select" name="rol_id" id="rol_id" required data-tooltip="Asignar rol al usuario" data-tooltip-theme="primary">
                                            <option value="">-- Seleccione un rol --</option>
                                            <?php $stmt_roles2 = $pdo->query("SELECT id, descripcion FROM clasif_rol ORDER BY id"); while ($rol2 = $stmt_roles2->fetch()): ?>
                                            <option value="<?php echo $rol2['id']; ?>"><?php echo htmlspecialchars($rol2['descripcion']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-12"><label class="form-label">Dirección Particular</label><textarea class="form-control" name="direccion_particular" id="direccion_particular" rows="2"></textarea></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer modal-footer-win">
                        <button type="button" class="btn-win btn-win-danger btn-win-sm" data-bs-dismiss="modal" title="Cancelar" data-tooltip="Cancelar" data-tooltip-theme="danger"><i class="fas fa-times me-1"></i> Cancelar</button>
                        <button type="submit" class="btn-win btn-win-primary btn-win-sm" id="btnGuardarUsuario" title="Guardar cambios" data-tooltip="Guardar cambios" data-tooltip-theme="success"><i class="fas fa-save me-1"></i> <span id="btnGuardarUsuarioText">Guardar Cambios</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para recortar imagen -->
    <div class="modal fade" id="cropModalUsuario" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="background: rgba(25, 25, 35, 0.98); backdrop-filter: blur(1.25rem); border: 0.0625rem solid rgba(255, 255, 255, 0.1); border-radius: 1.25rem;">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-crop-alt me-2"></i> Recortar imagen de perfil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()" title="Cerrar recortador" data-tooltip="Cerrar recortador" data-tooltip-theme="danger"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8"><div class="img-container" style="max-height:31.25rem; overflow: hidden; background: #333; border-radius: 0.5rem; padding:0.625rem;"><img id="imageToCropUsuario" src="" style="max-width:100%; display: block;"></div></div>
                        <div class="col-md-4">
                            <div class="text-center mb-3"><h6>Vista previa</h6><div class="preview-container"><canvas id="previewCanvasUsuario" width="150" height="150"></canvas></div></div>
                            <div class="crop-controls p-3">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(-90)" title="Rotar izquierda 90°" data-tooltip="Rotar izquierda 90°" data-tooltip-theme="primary"><i class="fas fa-undo-alt me-2"></i>Rotar izquierda 90°</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="rotarImagenUsuario(90)" title="Rotar derecha 90°" data-tooltip="Rotar derecha 90°" data-tooltip-theme="primary"><i class="fas fa-redo-alt me-2"></i>Rotar derecha 90°</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetearCropUsuario()" title="Resetear recorte" data-tooltip="Resetear recorte" data-tooltip-theme="primary"><i class="fas fa-sync-alt me-2"></i>Resetear recorte</button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(0.1)" title="Acercar" data-tooltip="Acercar" data-tooltip-theme="primary"><i class="fas fa-search-plus me-2"></i>Acercar</button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="zoomImagenUsuario(-0.1)" title="Alejar" data-tooltip="Alejar" data-tooltip-theme="primary"><i class="fas fa-search-minus me-2"></i>Alejar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="limpiarCropperUsuario()" title="Cancelar recorte" data-tooltip="Cancelar recorte" data-tooltip-theme="danger"><i class="fas fa-times me-2"></i>Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="aplicarRecorteUsuario()" title="Aplicar recorte" data-tooltip="Aplicar recorte" data-tooltip-theme="success"><i class="fas fa-check me-2"></i>Aplicar recorte</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/cropper.min.js"></script>
<script>
function togglePassVisibility(id, btn) {
    var input = document.getElementById(id);
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    if (btn) {
        var icon = btn.querySelector('i');
        if (icon) icon.className = 'fas ' + (show ? 'fa-eye-slash' : 'fa-eye');
    }
}
</script>

<script>
// ===== Gestión de Perfil (réplica del modal de usuarios.php) =====
const usuarioActualId = <?php echo (int)$usuario_actual_id; ?>;
const puedeEditarUsuario = <?php echo $puede_editar_usuario ? 'true' : 'false'; ?>;

// Logout desde el menú de acciones del perfil
const btnCerrarSesionPerfil = document.getElementById('btnCerrarSesionPerfil');
if (btnCerrarSesionPerfil) {
    btnCerrarSesionPerfil.addEventListener('click', function(e) {
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
                background: '#1a1a2e',
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

let cropperUsuario = null, previewCanvasUsuario = null, previewCtxUsuario = null;

function validarNombreCampo(valor, feedbackId, minLength = 3) {
    const limpio = valor.trim();
    if (limpio.length === 0) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio').show(); return false; }
    if (limpio.length < minLength) { $(feedbackId).html(`<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo ${minLength} caracteres`).show(); return false; }
    if (!/^[a-zA-ZáéíóúñÑüÜ\s]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Solo letras').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function togglePassword() {
    const pass = document.getElementById('password');
    const btn = document.getElementById('togglePasswordBtn');
    if (pass.type === 'password') {
        pass.type = 'text';
        if (btn) btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        pass.type = 'password';
        if (btn) btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

function validarUsuario(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Obligatorio').show(); return false; }
    if (limpio.length < 4) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo 4 caracteres').show(); return false; }
    if (!/^[a-zA-Z0-9_.\-@]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Solo letras, números, _ - . @').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarCI(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length !== 11 || !/^\d{11}$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Debe tener 11 dígitos').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarEmail(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Email inválido').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarTelefono(value, feedbackId) {
    const limpio = value.trim();
    if (limpio.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (!/^[\d\s\-()+]{7,15}$/.test(limpio)) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Teléfono inválido').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

function validarPassword(value, feedbackId) {
    if (value.length === 0) { $(feedbackId).html('').hide(); return true; }
    if (value.length < 6) { $(feedbackId).html('<i class="fas fa-exclamation-circle text-danger me-1"></i> Mínimo 6 caracteres').show(); return false; }
    $(feedbackId).html('<i class="fas fa-check-circle text-success me-1"></i> Válido').show(); return true;
}

$(document).ready(function() {
    $('#usuario').on('blur', function() { validarUsuario(this.value, '#usuarioFeedback'); });
    $('#nombre').on('blur', function() { validarNombreCampo(this.value, '#nombreFeedback'); });
    $('#primer_apellido').on('blur', function() { validarNombreCampo(this.value, '#apellido1Feedback'); });
    $('#segundo_apellido').on('blur', function() { validarNombreCampo(this.value, '#apellido2Feedback'); });
    $('#no_ci').on('blur', function() { validarCI(this.value, '#ciFeedbackUsuario'); });
    $('#email').on('blur', function() { validarEmail(this.value, '#emailFeedback'); });
    $('#telefono_contacto').on('blur', function() { validarTelefono(this.value, '#telefonoFeedback'); });
    $('#password').on('blur', function() { validarPassword(this.value, '#passHelp'); });

    // Chevron del select: arriba mientras el dropdown está abierto, abajo al cerrar
    $(document)
        .on('mousedown', '.form-select', function() { $(this).addClass('select-open'); })
        .on('keydown', '.form-select', function(e) {
            if (/^(Enter|Space|ArrowDown|ArrowUp|PageDown|PageUp|Home|End)$/.test(e.key)) $(this).addClass('select-open');
        })
        .on('change blur', '.form-select', function() { $(this).removeClass('select-open'); });
});

function strpos_eq(haystack, needle) { return haystack.indexOf(needle) === 0; }

function abrirEditorFotoUsuario() {
    const imagePreview = document.getElementById('imagePreviewUsuario');
    const imageUpload = document.getElementById('imageUploadUsuario');

    if (!imageUpload) return;

    if (imagePreview && imagePreview.style.display === 'block' && imagePreview.src && imagePreview.src !== '' && imagePreview.src !== window.location.href) {
        fetch(imagePreview.src, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    fetch(imagePreview.src)
                        .then(res => res.blob())
                        .then(blob => {
                            const file = new File([blob], "foto_actual.jpg", { type: "image/jpeg" });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            imageUpload.files = dataTransfer.files;
                            cargarImagenUsuario(imageUpload);
                        })
                        .catch(() => {
                            Swal.fire({
                                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i> Foto no disponible',
                                text: 'La foto actual no se puede editar. Puede subir una nueva.',
                                icon: 'warning',
                                confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir Nueva',
                                background: '#1a1a2e',
                                color: '#ffffff'
                            }).then(() => {
                                imageUpload.click();
                            });
                        });
                } else {
                    Swal.fire({
                        title: '<i class="fas fa-info-circle text-info me-2"></i> Sin foto',
                        html: 'No hay foto registrada o ha sido eliminada.<br>Puede subir una nueva.',
                        icon: 'info',
                        confirmButtonText: '<i class="fas fa-upload me-2"></i> Subir foto',
                        background: '#1a1a2e',
                        color: '#ffffff'
                    }).then(() => {
                        imageUpload.click();
                    });
                }
            })
            .catch(() => {
                imageUpload.click();
            });
    } else {
        imageUpload.click();
    }
}

function cargarImagenUsuario(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    if (!file.type.match(/^image\/(jpeg|png)$/)) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-image me-2"></i> Error', text: 'Solo se permiten imágenes JPG o PNG', background: 'var(--panel)', color: 'var(--txt)' });
        input.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-image me-2"></i> Error', text: 'La imagen no debe superar 5 MB', background: 'var(--panel)', color: 'var(--txt)' });
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageToCrop = document.getElementById('imageToCropUsuario');
        imageToCrop.src = e.target.result;
        imageToCrop.onload = function() {
            previewCanvasUsuario = document.getElementById('previewCanvasUsuario');
            previewCtxUsuario = previewCanvasUsuario.getContext('2d');
            previewCtxUsuario.fillStyle = '#2d2d2d';
            previewCtxUsuario.fillRect(0, 0, 150, 150);
            if (cropperUsuario) cropperUsuario.destroy();
            cropperUsuario = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 1, cropBoxResizable: true, cropBoxMovable: true, guides: true, center: true, highlight: true, background: true, responsive: true, restore: true, zoomable: true, rotatable: true, scalable: true, wheelZoomRatio: 0.1, minContainerWidth: Math.min(500, (window.innerWidth || 480) - 48), minContainerHeight: Math.min(400, (window.innerHeight || 700) - 160), crop: function(event) { actualizarPreviewUsuario(); }, ready: function() { const containerData = cropperUsuario.getContainerData(); const cropBoxSize = Math.min(containerData.width, containerData.height)*0.8; cropperUsuario.setCropBoxData({ width: cropBoxSize, height: cropBoxSize, left: (containerData.width-cropBoxSize)/2, top: (containerData.height-cropBoxSize)/2 }); setTimeout(() => actualizarPreviewUsuario(), 100); } });
            new bootstrap.Modal(document.getElementById('cropModalUsuario')).show();
        };
    };
    reader.readAsDataURL(file);
}

function actualizarPreviewUsuario() {
    if (!cropperUsuario || !previewCtxUsuario) return;
    try {
        const canvas = cropperUsuario.getCroppedCanvas({ width: 150, height: 150 });
        if (canvas) previewCtxUsuario.drawImage(canvas, 0, 0, 150, 150);
    } catch (error) { console.error('Error actualizando preview:', error); }
}

function rotarImagenUsuario(deg) { if(cropperUsuario) cropperUsuario.rotate(deg); }

function resetearCropUsuario() { if(cropperUsuario) { cropperUsuario.reset(); setTimeout(() => actualizarPreviewUsuario(), 50); } }

function zoomImagenUsuario(factor) { if(cropperUsuario) cropperUsuario.zoom(factor); setTimeout(() => actualizarPreviewUsuario(), 50); }

function limpiarCropperUsuario() { if(cropperUsuario){ cropperUsuario.destroy(); cropperUsuario=null; } document.getElementById('imageUploadUsuario').value=''; }

function aplicarRecorteUsuario() {
    if(cropperUsuario) {
        const b64 = cropperUsuario.getCroppedCanvas({width:500,height:500}).toDataURL('image/jpeg',0.9);
        document.getElementById('imagen_recortada_usuario').value = b64;

        const imagePreview = document.getElementById('imagePreviewUsuario');
        const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
        const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');

        if (imagePreview) {
            imagePreview.src = b64;
            imagePreview.style.display = 'block';
            imagePreview.style.objectFit = 'cover';
            imagePreview.style.borderRadius = '50%';
        }
        if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        if (btnEliminarFoto) btnEliminarFoto.style.display = 'inline-flex';

        const headerImg = document.getElementById('headerAvatarImg');
        const headerIniciales = document.getElementById('headerAvatarIniciales');
        if (headerImg && headerIniciales) {
            headerImg.src = b64;
            headerImg.style.display = 'block';
            headerIniciales.style.display = 'none';
        }

        document.getElementById('eliminarFotoUsuarioInput').value = '0';

        bootstrap.Modal.getInstance(document.getElementById('cropModalUsuario')).hide();
        limpiarCropperUsuario();
    }
}

function eliminarFotoUsuario() {
    Swal.fire({
        title: '<i class="fas fa-trash-alt text-danger me-2"></i> Eliminar foto',
        text: '¿Seguro que desea eliminar la foto de este usuario?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> Eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i> Cancelar',
        background: '#1a1a2e',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            const imagePreview = document.getElementById('imagePreviewUsuario');
            const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
            const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');
            const headerImg = document.getElementById('headerAvatarImg');
            const headerIniciales = document.getElementById('headerAvatarIniciales');

            if (imagePreview) { imagePreview.style.display = 'none'; imagePreview.src = ''; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
            if (headerImg && headerIniciales) { headerImg.style.display = 'none'; headerImg.src = ''; headerIniciales.style.display = 'flex'; }

            document.getElementById('eliminarFotoUsuarioInput').value = '1';
            document.getElementById('imagen_recortada_usuario').value = '';
        }
    });
}

function editarUsuario(id) {
    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });
    fetch('../ajax/get_usuario.php?id=' + id)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message || 'No se pudo cargar el usuario', background: 'var(--panel)', color: 'var(--txt)' });
            return;
        }
        const u = data.usuario;

        document.getElementById('formUsuario').reset();
        document.getElementById('usuarioAction').value = 'editar';
        document.getElementById('usuarioId').value = u.id;
        document.getElementById('imagen_recortada_usuario').value = '';
        document.getElementById('eliminarFotoUsuarioInput').value = '0';

        document.getElementById('usuario').value = u.usuario || '';
        document.getElementById('password').value = '';
        document.getElementById('nombre').value = u.nombre || '';

        const apellidos = (u.apellidos || '').split(' ');
        document.getElementById('primer_apellido').value = apellidos[0] || '';
        document.getElementById('segundo_apellido').value = apellidos.slice(1).join(' ') || '';

        document.getElementById('no_ci').value = u.no_ci || '';
        document.getElementById('email').value = u.email || '';
        document.getElementById('telefono_contacto').value = u.telefono_contacto || '';
        document.getElementById('direccion_particular').value = u.direccion_particular || '';
        document.getElementById('rol_id').value = u.rol_id || '';
        document.getElementById('activo_usuario').checked = u.activo == 1;

        // Según los permisos: solo quien puede editar usuarios modifica el rol y el estado
        // El propio usuario logueado nunca puede desactivarse a sí mismo, aunque sea admin
        const esPerfilPropio = u.id == usuarioActualId;
        const rolSelect = document.getElementById('rol_id');
        const activoChk = document.getElementById('activo_usuario');
        rolSelect.disabled = !puedeEditarUsuario;
        activoChk.disabled = !puedeEditarUsuario || esPerfilPropio;
        if (!puedeEditarUsuario) {
            rolSelect.setAttribute('title', 'Solo un administrador puede modificar el rol');
        } else {
            rolSelect.removeAttribute('title');
        }
        if (esPerfilPropio) {
            activoChk.setAttribute('title', 'No puede desactivar su propia cuenta');
        } else {
            activoChk.removeAttribute('title');
        }

        $('#usuarioFeedback, #nombreFeedback, #apellido1Feedback, #apellido2Feedback, #ciFeedbackUsuario, #emailFeedback, #telefonoFeedback, #passHelp').html('').hide();

        const title = document.getElementById('modalUsuarioTitle');
        const subtitle = document.getElementById('modalUsuarioSubtitle');
        const badge = document.getElementById('modalModeBadge');
        const status = document.getElementById('formUsuarioStatus');
        const statusText = document.getElementById('formUsuarioStatusText');
        const btnText = document.getElementById('btnGuardarUsuarioText');

        const nombreCompleto = (u.nombre || '') + ' ' + (u.apellidos || '');
        if (title) title.innerHTML = '<i class="fas fa-user-edit me-2"></i> Editar Perfil';
        if (subtitle) subtitle.textContent = nombreCompleto;
        if (badge) { badge.className = 'mode-badge edit'; badge.innerHTML = '<i class="fas fa-edit"></i> Editar'; }
        if (status) { status.className = 'form-usuario-status edit'; status.querySelector('i').className = 'fas fa-user-edit'; }
        if (statusText) statusText.textContent = 'Editando el usuario ' + (u.usuario || '');
        if (btnText) btnText.textContent = 'Guardar Cambios';

        const passHelp = document.getElementById('passHelp');
        if (passHelp) passHelp.textContent = 'Dejar en blanco para mantener la contraseña actual';
        const toggleBtn = document.getElementById('togglePasswordBtn');
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        const pass = document.getElementById('password');
        if (pass) pass.type = 'password';

        const headerImg = document.getElementById('headerAvatarImg');
        const headerIniciales = document.getElementById('headerAvatarIniciales');
        const imagePreview = document.getElementById('imagePreviewUsuario');
        const fotoPlaceholder = document.getElementById('fotoPlaceholderUsuario');
        const btnEliminarFoto = document.getElementById('btnEliminarFotoUsuario');

        let foto_url = u.foto || '';
        let foto_existe = false;
        if (foto_url) {
            if (strpos_eq(foto_url, 'assets/')) foto_url = '../' + foto_url;
            foto_existe = true;
        }

        if (foto_existe && foto_url) {
            if (headerImg && headerIniciales) { headerImg.src = foto_url; headerImg.style.display = 'block'; headerIniciales.style.display = 'none'; }
            if (imagePreview) { imagePreview.src = foto_url; imagePreview.style.display = 'block'; imagePreview.style.objectFit = 'cover'; imagePreview.style.borderRadius = '50%'; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'inline-flex';
        } else {
            const iniciales = ((u.nombre || '')[0] || '') + ((u.apellidos || '')[0] || '');
            if (headerImg && headerIniciales) { headerImg.style.display = 'none'; headerImg.src = ''; headerIniciales.style.display = 'flex'; headerIniciales.textContent = iniciales.toUpperCase(); }
            if (imagePreview) { imagePreview.style.display = 'none'; imagePreview.src = ''; }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
            if (btnEliminarFoto) btnEliminarFoto.style.display = 'none';
        }

        const estadoLabel = document.getElementById('estadoUsuarioLabel');
        if (estadoLabel) estadoLabel.textContent = u.activo == 1 ? 'Usuario Activo' : 'Usuario Inactivo';

        new bootstrap.Modal(document.getElementById('modalUsuario')).show();
    })
    .catch(() => {
        Swal.close();
        Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión al cargar el usuario', background: 'var(--panel)', color: 'var(--txt)' });
    });
}

const formUsuarioEl = document.getElementById('formUsuario');
if (formUsuarioEl) formUsuarioEl.addEventListener('submit', function(e) {
    e.preventDefault();

    const btnGuardar = document.getElementById('btnGuardarUsuario');
    const btnTexto = document.getElementById('btnGuardarUsuarioText');

    const okUsuario = validarUsuario(document.getElementById('usuario').value, '#usuarioFeedback');
    const okNombre = validarNombreCampo(document.getElementById('nombre').value, '#nombreFeedback');
    const okAp1 = validarNombreCampo(document.getElementById('primer_apellido').value, '#apellido1Feedback');
    const okAp2 = validarNombreCampo(document.getElementById('segundo_apellido').value, '#apellido2Feedback');
    const okCI = validarCI(document.getElementById('no_ci').value, '#ciFeedbackUsuario');
    const okEmail = validarEmail(document.getElementById('email').value, '#emailFeedback');
    const okTel = validarTelefono(document.getElementById('telefono_contacto').value, '#telefonoFeedback');

    const passwordVal = document.getElementById('password').value;
    const okPassword = passwordVal ? validarPassword(passwordVal, '#passHelp') : true;

    const rolVal = document.getElementById('rol_id').value;
    if (!rolVal) { Swal.fire({ icon: 'warning', title: '<i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Seleccione un rol', text: 'Debe seleccionar el rol del usuario', background: 'var(--panel)', color: 'var(--txt)' }); return; }

    if (!okUsuario || !okNombre || !okAp1 || !okAp2 || !okCI || !okEmail || !okTel || !okPassword) {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Campos inválidos', text: 'Revise los campos marcados en rojo', background: 'var(--panel)', color: 'var(--txt)' });
        return;
    }

    btnGuardar.classList.add('btn-guardar-loading');
    btnGuardar.disabled = true;
    if (btnTexto) btnTexto.textContent = 'Guardando...';

    Swal.fire({ title: '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: 'var(--panel)', color: 'var(--txt)' });

    const fd = new FormData(this);
    // Si el select/checkbox están deshabilitados (sin permiso) no viajan en FormData:
    // el servidor igual los sobreescribe desde la BD para no-administradores.
    const rolSelect = document.getElementById('rol_id');
    const activoChk = document.getElementById('activo_usuario');
    if (rolSelect.disabled) fd.append('rol_id', rolSelect.value);
    if (activoChk.disabled) fd.append('activo', activoChk.checked ? '1' : '0');

    fetch('../ajax/guardar_usuario.php', { method: 'POST', body: fd })
    .then(response => response.json())
    .then(data => {
        if (data.success) { Swal.fire({ icon: 'success', title: '<i class="fas fa-check-circle me-2"></i> Completado', text: data.message, timer: 1500, showConfirmButton: false, background: 'var(--panel)', color: 'var(--txt)' }); setTimeout(() => location.reload(), 1500); }
        else {
            Swal.fire({ icon: 'error', title: '<i class="fas fa-exclamation-circle me-2"></i> Error', text: data.message, background: 'var(--panel)', color: 'var(--txt)' });
            btnGuardar.classList.remove('btn-guardar-loading');
            btnGuardar.disabled = false;
            if (btnTexto) btnTexto.textContent = 'Guardar Cambios';
            $('#btnGuardarUsuario i').attr('class', 'fas fa-save me-1');
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: '<i class="fas fa-wifi me-2"></i> Error', text: 'Error de conexión', background: 'var(--panel)', color: 'var(--txt)' });
        btnGuardar.classList.remove('btn-guardar-loading');
        btnGuardar.disabled = false;
        if (btnTexto) btnTexto.textContent = 'Guardar Cambios';
        $('#btnGuardarUsuario i').attr('class', 'fas fa-save me-1');
    });
});
</script>

<?php if ($es_propio): ?>
<script>
(function() {
    const form = document.getElementById('formCambiarPassword');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const feedback = document.getElementById('passFeedback');
        const passNueva = document.getElementById('passNueva').value;
        const passConfirmar = document.getElementById('passConfirmar').value;
        feedback.classList.add('d-none');

        if (passNueva !== passConfirmar) {
            feedback.textContent = 'Las contraseñas no coinciden';
            feedback.classList.remove('d-none');
            return;
        }
        if (passNueva.length < 6) {
            feedback.textContent = 'La contraseña debe tener al menos 6 caracteres';
            feedback.classList.remove('d-none');
            return;
        }

        const btn = document.getElementById('btnGuardarPass');
        btn.disabled = true;

        fetch('../ajax/cambiar_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'password_actual=' + encodeURIComponent(document.getElementById('passActual').value) +
                  '&password_nueva=' + encodeURIComponent(passNueva) +
                  '&password_confirm=' + encodeURIComponent(passConfirmar)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            if (res.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '<i class="fas fa-check-circle" style="color: 2"></i> Contraseña actualizada',
                        text: res.message,
                        icon: 'success',
                        confirmButtonColor: '#22c55e',
                        background: '#1F1F1F',
                        color: '#FFFFFF'
                    }).then(function() {
                        form.reset();
                        const modalEl = document.getElementById('modalCambiarPassword');
                        if (modalEl && window.bootstrap) bootstrap.Modal.getInstance(modalEl)?.hide();
                    });
                } else {
                    alert(res.message);
                    form.reset();
                }
            } else {
                feedback.textContent = res.message || 'Error al cambiar la contraseña';
                feedback.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled = false;
            feedback.textContent = 'Error de conexión. Intente de nuevo.';
            feedback.classList.remove('d-none');
        });
    });

    // Si se llegó con ?cambiar_pass=1, abrir el modal automáticamente
    const params = new URLSearchParams(window.location.search);
    if (params.get('cambiar_pass') === '1') {
        const modalEl = document.getElementById('modalCambiarPassword');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
})();
</script>
<?php endif; ?>

<?php if ($cumpleanios_usuario_hoy): ?>
<script>
window.PERFIL_CUMPLEANOS = {
    nombre: <?php echo json_encode($user_nombre_completo, JSON_UNESCAPED_UNICODE); ?>,
    anios: <?php echo (int)$anios_cumplidos; ?>
};
</script>
<script src="../js/birthday_animation.js"></script>
<?php endif; ?>

</body>
</html>