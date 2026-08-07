<?php
// modules/users.php - Perfil de Usuario
require_once '../config.php';
require_once '../includes/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Datos del usuario desde sesión
$user_nombre_completo = $_SESSION['usuario_nombre'] ?? $_SESSION['user_nombre'] ?? 'Usuario';
$user_rol_codigo = $_SESSION['usuario_rol'] ?? $_SESSION['rol_codigo'] ?? '';
$usuario_actual_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;

// Roles con permisos para ver el perfil de otros usuarios
$roles_administracion = ['Admin', 'Super', 'Soft'];

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
$es_admin = in_array($user_rol_codigo, $roles_administracion);
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

// ==========================================
// RESOLVER FOTO DEL USUARIO
// ==========================================
$defaultSvg = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 549.62 605.05"><g transform="translate(-91.414 -149.93)"><g transform="matrix(11.705 0 0 11.705 -1944.4 1569.9)" stroke="#fff" stroke-width="0.1"><path transform="matrix(3.8528 0 0 -3.8528 -3551.4 48.489)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115h5.4115z" fill="#0080ff"/><path transform="matrix(2.5762 0 0 2.5762 -2309.2 -185.48)" d="m978.4 31.352c0 2.9887-2.4228 5.4115-5.4115 5.4115s-5.4115-2.4228-5.4115-5.4115 2.4228-5.4115 5.4115-5.4115 5.4115 2.4228-5.4115 5.4115z" fill="#0080ff"/></g></g></svg>');
$user_foto = $defaultSvg;

if (!empty($usuario['foto'])) {
    $foto_valor = $usuario['foto'];

    if (strpos($foto_valor, 'data:image') === 0) {
        $user_foto = $foto_valor;
    } elseif (filter_var($foto_valor, FILTER_VALIDATE_URL)) {
        $user_foto = $foto_valor;
    } elseif (strlen($foto_valor) > 200 && strpos($foto_valor, '/') === false && strpos($foto_valor, '.') === false) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $foto_valor);
        finfo_close($finfo);
        $user_foto = 'data:' . ($mime_type ?: 'image/jpeg') . ';base64,' . base64_encode($foto_valor);
    } else {
        $foto_limpia = ltrim($foto_valor, './');
        if (strpos($foto_limpia, 'assets/imagenes/') === 0) {
            $foto_limpia = substr($foto_limpia, strlen('assets/imagenes/'));
        }
        $nombre_archivo = basename($foto_limpia);
        $ruta_foto = '../assets/imagenes/usuarios/' . $nombre_archivo;
        $ruta_absoluta = $_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/assets/imagenes/usuarios/' . $nombre_archivo;
        if (file_exists($ruta_absoluta)) {
            $user_foto = $ruta_foto;
        } elseif ($es_admin && $usuario['foto'] != $nombre_archivo) {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/NOMINAS/' . $foto_valor)) {
                $user_foto = '../' . $foto_valor;
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDL TransNuBeT | Perfil de Usuario</title>
    <link rel="icon" type="image/png" href="../../images/favicons/nominas.ico">

    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: #0c0c0c; overflow-x: hidden; color: #ffffff; }

        .win11-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f1a 100%);
        }
        .win11-bg::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(0, 120, 212, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(16, 124, 16, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .main-container { margin-left: 260px; transition: all 0.3s ease; min-height: 100vh; padding: 20px; }
        .main-container.expanded { margin-left: 80px; }

        .win-topbar {
            background: rgba(20, 20, 25, 0.7); backdrop-filter: blur(20px); border-radius: 16px;
            padding: 12px 24px; margin-bottom: 24px; border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; justify-content: space-between; align-items: center;
            position: relative !important; z-index: 100 !important;
        }
        .sidebar-toggle { background: rgba(255, 255, 255, 0.05); border: none; color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .sidebar-toggle:hover { background: rgba(255, 255, 255, 0.1); transform: scale(1.02); }
        .page-title h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .page-title p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); margin: 4px 0 0; }

        .glass-card {
            background: rgba(28, 28, 35, 0.6); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .glass-card:hover { transform: translateY(-2px); background: rgba(35, 35, 45, 0.7); border-color: rgba(0, 120, 212, 0.3); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }

        /* Cabecera del perfil */
        .profile-cover {
            height: 130px;
            background: linear-gradient(135deg, rgba(0, 120, 212, 0.35) 0%, rgba(139, 92, 246, 0.35) 100%);
            border-radius: 12px 12px 0 0;
            position: relative;
            overflow: hidden;
        }
        .profile-cover::after {
            content: ''; position: absolute; inset: 0;
            background-image: radial-gradient(circle at 85% 20%, rgba(255,255,255,0.15) 0%, transparent 50%);
        }
        .profile-header {
            padding: 0 24px 24px;
            position: relative;
        }
        .profile-avatar-large {
            width: 110px; height: 110px; border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.15);
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative;
            margin-top: -55px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }
        .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
        .profile-avatar-large .iniciales {
            font-size: 2.4rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;
        }
        .profile-name-lg { font-size: 1.6rem; font-weight: 700; margin: 0; }
        .profile-username { font-size: 0.95rem; color: rgba(255, 255, 255, 0.5); }

        .info-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            transition: all 0.2s ease;
            height: 100%;
        }
        .info-item:hover { background: rgba(96, 165, 250, 0.08); border-color: rgba(96, 165, 250, 0.2); }
        .info-icon {
            width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
            background: rgba(96, 165, 250, 0.1); border: 1px solid rgba(96, 165, 250, 0.2);
            display: flex; align-items: center; justify-content: center;
            color: #60a5fa; font-size: 1rem;
        }
        .info-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.4); font-weight: 600; }
        .info-value { font-size: 0.9rem; color: #f1f5f9; margin-top: 2px; word-break: break-word; }
        .info-value code { color: #93c5fd; }

        .stat-box { text-align: center; padding: 16px 8px; }
        .stat-box .h4 { margin: 0; font-weight: 700; }
        .stat-box small { color: rgba(255, 255, 255, 0.45); }

        .btn-win {
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: white; font-size: 0.85rem; transition: all 0.2s;
            cursor: pointer; padding: 8px 16px; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .btn-win:hover { background: rgba(0, 120, 212, 0.6); border-color: #0078d4; transform: translateY(-1px); color: white; }
        .btn-win-primary { background: linear-gradient(135deg, #0078d4, #00a8e8); border: none; }
        .btn-win-primary:hover { background: linear-gradient(135deg, #0086e8, #00b8ff); transform: translateY(-1px); }
        .btn-win-outline { background: transparent; }

        .badge-rol { font-size: 0.78rem; padding: 6px 14px; }

        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
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
            <button class="sidebar-toggle" id="sidebarToggleBtn">
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
        <a href="configuracion.php" class="btn-win btn-win-outline btn-win-sm"><i class="fas fa-arrow-left me-1"></i> Volver a Gestión de Usuarios</a>
    </div>
    <?php endif; ?>

    <?php if ($error_no_encontrado): ?>
    <div class="glass-card fade-in-up p-5 text-center" style="animation-delay: 0.08s;">
        <i class="fas fa-user-slash" style="font-size: 3.5rem; color: #f87171; margin-bottom: 16px;"></i>
        <h4 class="mb-2">Usuario no encontrado</h4>
        <p class="mb-4" style="color: rgba(255, 255, 255, 0.55);">
            El usuario solicitado no existe o fue eliminado del sistema.
        </p>
        <a href="configuracion.php" class="btn-win btn-win-primary">
            <i class="fas fa-cog me-1"></i> Ir a Gestión de Usuarios
        </a>
    </div>
    <?php endif; ?>

    <?php if (!$error_no_encontrado): ?>
    <!-- Cabecera del perfil -->
    <div class="glass-card fade-in-up mb-4" style="animation-delay: 0.08s; overflow: hidden;">
        <div class="profile-cover"></div>
        <div class="profile-header d-flex flex-column flex-sm-row align-items-sm-end gap-3">
            <div class="profile-avatar-large">
                <img src="<?php echo htmlspecialchars($user_foto); ?>" alt="Foto de perfil"
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
            </div>
        </div>
    </div>

    <!-- Información de contacto -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.1s;">
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="info-label">Correo Electrónico</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['email'] ?? 'No especificado'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.14s;">
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                <div>
                    <div class="info-label">Teléfono de Contacto</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['telefono_contacto'] ?? 'No especificado'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.18s;">
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-id-badge"></i></div>
                <div>
                    <div class="info-label">Carné de Identidad</div>
                    <div class="info-value"><code><?php echo htmlspecialchars($usuario['no_ci']); ?></code></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 fade-in-up" style="animation-delay: 0.22s;">
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-user-shield"></i></div>
                <div>
                    <div class="info-label">Rol del Sistema</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dirección + Registro -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8 fade-in-up" style="animation-delay: 0.26s;">
            <div class="glass-card p-4 h-100">
                <h6 class="mb-3 fw-semibold"><i class="fas fa-map-marker-alt me-2" style="color: #60a5fa;"></i>Dirección Particular</h6>
                <p class="mb-0" style="color: rgba(255, 255, 255, 0.85); font-size: 0.92rem;">
                    <?php echo htmlspecialchars($usuario['direccion_particular'] ?? 'No especificada'); ?>
                </p>
            </div>
        </div>
        <div class="col-lg-4 fade-in-up" style="animation-delay: 0.3s;">
            <div class="glass-card p-4 h-100">
                <h6 class="mb-3 fw-semibold"><i class="fas fa-history me-2" style="color: #60a5fa;"></i>Registro del Usuario</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="info-icon"><i class="fas fa-calendar-plus"></i></div>
                    <div>
                        <div class="info-label">Fecha de Registro</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="info-icon"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="info-label">Última Actualización</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($usuario['fecha_actualizacion'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones -->
    <div class="fade-in-up" style="animation-delay: 0.34s;">
        <?php if ($es_propio): ?>
            <a href="configuracion.php?tab=perfil" class="btn-win btn-win-primary">
                <i class="fas fa-lock me-1"></i> Cambiar Contraseña
            </a>
            <?php if ($es_admin): ?>
                <a href="configuracion.php" class="btn-win">
                    <i class="fas fa-cog me-1"></i> Gestionar Usuarios
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="configuracion.php" class="btn-win btn-win-primary">
                <i class="fas fa-user-edit me-1"></i> Gestionar Usuarios
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
</body>
</html>
