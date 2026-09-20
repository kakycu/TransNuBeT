<?php
// bloquear_sesion.php - Pantalla de bloqueo de sesión (estilo login.php con barra de título tipo ventana)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/logger.php';

// Si no hay sesión iniciada, salir al login
$uid = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
if (empty($uid) || empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// Marcar la sesión como bloqueada (bloqueo anti-atrás server-side en config/database.php)
$_SESSION['sesion_bloqueada'] = true;
$_SESSION['sesion_bloqueada_tiempo'] = time();

// Guardar el origen al que se debe regresar tras desbloquear
// Se guarda el path relativo a la raíz de la app (ej. "modules/trabajadores.php")
// en lugar del basename, para que el retorno no pierda el directorio /modules.
if (empty($_SESSION['bloqueo_origen']) || $_SESSION['bloqueo_origen'] === 'bloquear_sesion.php') {
    $origen = 'dashboard.php';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '') {
        $ref_url   = parse_url($ref);
        $ref_path  = ltrim($ref_url['path'] ?? '', '/');
        $base_dir  = ltrim(rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/login.php')), '/'), '/');
        if ($base_dir === '') {
            if ($ref_path !== '') $origen = $ref_path;
        } elseif (strpos($ref_path, $base_dir . '/') === 0) {
            $origen = substr($ref_path, strlen($base_dir) + 1);
            if ($origen === '') $origen = 'index.php';
        }
        // Conservar la query string para volver al mismo estado (ej. módulos con filtros)
        if (!empty($ref_url['query'])) {
            $origen .= '?' . $ref_url['query'];
        }
    }
    if ($origen === 'bloquear_sesion.php' || $origen === 'login.php'
        || $origen === 'verificar_contrasena_bloqueo.php' || $origen === 'limpiar_bloqueo.php') {
        $origen = 'dashboard.php';
    }
    $_SESSION['bloqueo_origen'] = $origen;
}

$user_id = (int) $uid;

// ============================================================
// DATOS DEL USUARIO (con foto y rol)
// ============================================================
$usuario = [];
$stmt = $pdo->prepare("SELECT u.*, r.codigo AS rol_codigo, r.descripcion AS rol_descripcion
                       FROM clasif_usuarios u
                       LEFT JOIN clasif_rol r ON u.rol_id = r.id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Nombre completo e iniciales
$nombre_completo = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
$nombre_completo = $nombre_completo !== '' ? $nombre_completo : ($_SESSION['user_nombre'] ?? 'Usuario');
$iniciales = '';
foreach (preg_split('/\s+/', trim($nombre_completo)) as $palabra) {
    if ($iniciales !== '' && strlen($iniciales) >= 2) break;
    if ($palabra !== '') $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
}
$iniciales = $iniciales !== '' ? $iniciales : 'U';

$rol_descripcion = $usuario['rol_descripcion'] ?? $_SESSION['rol_descripcion'] ?? 'Usuario';
$user_ci         = $usuario['no_ci'] ?? $_SESSION['user_ci'] ?? '';
$user_email      = $usuario['email'] ?? $_SESSION['user_email'] ?? '';
$user_ip         = $_SESSION['lock_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'No disponible';

// Foto del usuario (BLOB/base64, URL o ruta)
$foto_usuario = '';
if (!empty($usuario['foto'])) {
    $foto = $usuario['foto'];
    if (strpos($foto, 'data:image') === 0) {
        $foto_usuario = $foto;
    } elseif (filter_var($foto, FILTER_VALIDATE_URL)) {
        $foto_usuario = $foto;
    } elseif (strlen($foto) > 200 && strpos($foto, '/') === false && strpos($foto, '.') === false) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $foto);
        finfo_close($finfo);
        $foto_usuario = 'data:' . ($mime ?: 'image/jpeg') . ';base64,' . base64_encode($foto);
    } else {
        $foto_rel  = ltrim($foto, './');
        $root_abs  = __DIR__;
        $candidatas = [];
        if (strpos($foto_rel, 'assets/') === 0) {
            $candidatas[] = $foto_rel;
        } else {
            $candidatas[] = 'assets/imagenes/' . $foto_rel;
            $candidatas[] = 'assets/imagenes/usuarios/' . basename($foto_rel);
            $candidatas[] = 'assets/imagenes/trabajadores/' . basename($foto_rel);
        }
        foreach ($candidatas as $cand) {
            if (file_exists($root_abs . '/' . $cand)) { $foto_usuario = $cand; break; }
        }
    }
}

// ============================================================
// FUNCIONES DE FORMATO (fechas en español)
// ============================================================
function _fechaBloqueoLarga($ts) {
    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $dias[(int)date('w', $ts)] . ', ' . (int)date('d', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}
function _fechaBloqueoHora($ts) {
    return _fechaBloqueoLarga($ts) . ' - ' . date('h:i:s A', $ts);
}

// Hora en que se bloqueó la sesión
$ts_bloqueo        = (int)($_SESSION['sesion_bloqueada_tiempo'] ?? time());
$hora_bloqueo_txt  = _fechaBloqueoHora($ts_bloqueo);

// Último inicio de sesión (audit_logs)
$ultimo_login_txt = 'No registrado';
try {
    $q = $pdo->prepare("SELECT created_at FROM audit_logs
                        WHERE user_id = ? AND action_type IN ('iniciar_sesion', 'iniciar_sesion_google') AND status = 'success'
                        ORDER BY created_at DESC, id DESC LIMIT 1");
    $q->execute([$user_id]);
    if ($fila = $q->fetch()) {
        $ultimo_login_txt = _fechaBloqueoHora(strtotime($fila['created_at']));
    }
} catch (Exception $e) {}

// Última operación registrada en audit_logs
$ultima_op_txt = 'No registrada';
try {
    $q = $pdo->prepare("SELECT action_type, module, description, created_at FROM audit_logs
                        WHERE user_id = ? AND status = 'success'
                        ORDER BY created_at DESC, id DESC LIMIT 1");
    $q->execute([$user_id]);
    if ($fila = $q->fetch()) {
        $desc_op = !empty($fila['description']) ? $fila['description'] : $fila['action_type'];
        $ultima_op_txt = $desc_op . ' (' . _fechaBloqueoHora(strtotime($fila['created_at'])) . ')';
    }
} catch (Exception $e) {}

// Info del navegador (resumida)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ua_txt = 'No disponible';
if (preg_match('/(Chrome)\/([0-9.]+)/', $ua, $m)) { $ua_txt = $m[1] . ' ' . $m[2]; }
elseif (preg_match('/(Firefox)\/([0-9.]+)/', $ua, $m)) { $ua_txt = $m[1] . ' ' . $m[2]; }
elseif (preg_match('/(Edge)\/([0-9.]+)/', $ua, $m)) { $ua_txt = $m[1] . ' ' . $m[2]; }
elseif (!empty($ua)) { $ua_txt = substr($ua, 0, 60) . '...'; }

// ============================================================
// MARCAS (footer y modal)
// ============================================================
$bloqueo_brand    = 'SISGESNOM';
$bloqueo_version  = defined('SITE_VERSION') ? SITE_VERSION : 'v2.0.1';
$bloqueo_company  = defined('COMPANY_NAME') ? COMPANY_NAME : 'PDL TransNuBeT®';

// Duración del cierre automático (segundos)
$LOCK_SEGUNDOS = 600;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Sesión Bloqueada - <?php echo htmlspecialchars($bloqueo_company); ?></title>
    <link rel="icon" type="image/x-icon" href="../images/favicons/nominas.ico">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="css/sweetalert2.min.css">

<style>
    /* ========== RESET Y ESTILOS BASE (igual que login.php) ========== */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 1.25rem;
        overflow: hidden;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background-image: url('../images/sigesnom.png');
        background-repeat: no-repeat;
        background-position: center center;
        background-size: contain;
        pointer-events: none;
        z-index: 0;
    }

    body::after {
        content: "";
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background-image:
            repeating-linear-gradient(45deg, transparent, transparent 1.875rem, rgba(59, 130, 246, 0.08) 1.875rem, rgba(59, 130, 246, 0.08) 3.75rem),
            repeating-linear-gradient(-45deg, transparent, transparent 2.1875rem, rgba(139, 92, 246, 0.06) 2.1875rem, rgba(139, 92, 246, 0.06) 4.375rem);
        pointer-events: none;
        animation: waveMove 8s ease-in-out infinite;
        z-index: 0;
    }

    @keyframes waveMove {
        0%, 100% { background-position: 0 0, 0 0; }
        25% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
        50% { background-position: 2.5rem 2.5rem, -2.5rem -2.5rem; }
        75% { background-position: 1.25rem 1.25rem, -1.25rem -1.25rem; }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(1.25rem); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ========== CONTENEDOR PRINCIPAL (tarjeta glass estilo login) ========== */
    .lock-screen {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 42.5rem;
        animation: fadeInUp 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }

    .lock-container {
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(0.625rem);
        -webkit-backdrop-filter: blur(0.625rem);
        border-radius: 2rem;
        border: 0.0625rem solid rgba(59, 130, 246, 0.25);
        padding: 2.5rem 3rem;
        box-shadow: 0 1.5625rem 3.125rem -0.75rem rgba(0, 0, 0, 0.5);
        text-align: center;
        transition: padding 0.3s ease;
        margin: 1.25rem auto;
        box-sizing: border-box;
        overflow: visible;
    }

    /* ===== BARRA DE TÍTULO (estilo ventana, como login.php) ===== */
    .titlebar-lock {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: -2.5rem -3rem 1.5rem;
        padding: 0.625rem 1rem 0.625rem 1.25rem;
        border-bottom: 0.0625rem solid rgba(59, 130, 246, 0.15);
        background: rgba(30, 41, 59, 0.4);
        border-radius: 2rem 2rem 0 0;
        user-select: none;
        cursor: default;
        cursor: grab;
        transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .titlebar-lock:hover {
        background: rgba(37, 99, 235, 0.16);
        border-bottom-color: rgba(59, 130, 246, 0.4);
    }

    .titlebar-lock.dragging {
        cursor: grabbing;
        background: rgba(37, 99, 235, 0.28);
        border-bottom-color: rgba(59, 130, 246, 0.6);
        box-shadow: 0 0 0.75rem rgba(59, 130, 246, 0.25);
    }

    .lock-container.dragging { transition: none !important; }

    .titlebar-lock .titlebar-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        letter-spacing: 0.02rem;
    }

    .titlebar-lock .titlebar-buttons {
        display: flex;
        gap: 0.375rem;
    }

    .titlebar-lock .titlebar-btn {
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0.0625rem solid rgba(59, 130, 246, 0.2);
        border-radius: 0.5rem;
        background: rgba(59, 130, 246, 0.08);
        color: #cbd5e1;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .titlebar-lock .titlebar-btn:hover {
        background: rgba(59, 130, 246, 0.2);
        color: #fff;
        transform: translateY(-0.0625rem);
    }

    .titlebar-lock .titlebar-btn.tb-info:hover {
        background: rgba(59, 130, 246, 0.3);
        border-color: rgba(59, 130, 246, 0.5);
        color: #fff;
    }

    /* Colapsado: ocultar cuerpo y reducir el relleno inferior */
    .lock-container.collapsed { padding-bottom: 1rem; }
    .lock-container.collapsed .lock-top,
    .lock-container.collapsed .user-info-card,
    .lock-container.collapsed .status-box,
    .lock-container.collapsed .unlock-form,
    .lock-container.collapsed .lock-footer { display: none; }

    /* ===== CABECERA: candado animado + "Sesión Bloqueada" a su lado ===== */
    .lock-top {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1.125rem;
        margin-bottom: 1.75rem;
    }

    .lock-icon {
        font-size: 2.5rem;
        color: #3b82f6;
        animation: lockPulse 2s infinite ease-in-out;
        text-shadow: 0 0 1.25rem rgba(59, 130, 246, 0.45);
        line-height: 1;
    }

    @keyframes lockPulse {
        0%, 100% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.08) rotate(-4deg); }
    }

    .lock-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #e2e8f0;
        letter-spacing: 0.02rem;
        text-align: left;
    }

    /* ===== TARJETA DE USUARIO ===== */
    .user-info-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .user-avatar-large {
        width: 6rem;
        height: 6rem;
        min-width: 6rem;
        min-height: 6rem;
        background: linear-gradient(135deg, #1e3a8a, #3b82f6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.75rem;
        font-weight: 600;
        border: 0.1875rem solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, 0.35);
        position: relative;
        overflow: hidden;
        margin-bottom: 0.375rem;
    }

    .user-avatar-large.has-photo {
        background-color: transparent;
        border: none;
    }

    .user-avatar-large .avatar-img {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        border-radius: 50%;
        z-index: 2;
    }

    .avatar-iniciales { z-index: 1; }

    .user-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #ffffff;
        line-height: 1.3;
    }

    .user-role {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #c4b5fd;
        background: rgba(139, 92, 246, 0.16);
        border: 0.0625rem solid rgba(139, 92, 246, 0.3);
        padding: 0.25rem 0.875rem;
        border-radius: 2rem;
    }

    /* Hora - Fecha en una misma línea */
    .user-meta {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    .user-meta .meta-sep { color: #475569; }

    /* ===== ESTADO (verde, con punto pulsante) ===== */
    .status-box {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        text-align: left;
        background: rgba(16, 185, 129, 0.1);
        border: 0.0625rem solid rgba(16, 185, 129, 0.28);
        border-radius: 1rem;
        padding: 0.75rem 1.125rem;
        margin-bottom: 1.5rem;
    }

    .status-dot {
        width: 0.75rem;
        height: 0.75rem;
        min-width: 0.75rem;
        background: #22c55e;
        border-radius: 50%;
        box-shadow: 0 0 0 rgba(34, 197, 94, 0.5);
        animation: statusPulse 1.6s infinite ease-out;
    }

    @keyframes statusPulse {
        0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
        80%, 100% { box-shadow: 0 0 0 0.625rem rgba(34, 197, 94, 0); }
    }

    .status-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #34d399;
        line-height: 1.2;
    }

    .status-sub {
        font-size: 0.82rem;
        color: #a7f3d0;
        opacity: 0.85;
        line-height: 1.35;
    }

    .status-sub .fw-bold { color: #ffffff; }

    /* ===== FORMULARIO DE DESBLOQUEO ===== */
    .unlock-form { width: 100%; }

    .input-group-lock {
        position: relative;
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .input-group-lock .form-control-lock {
        width: 100%;
        background: rgba(255, 255, 255, 0.06);
        border: 0.0625rem solid rgba(255, 255, 255, 0.15);
        color: #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem 2.75rem 0.75rem 1rem;
        font-size: 0.95rem;
        outline: none;
        transition: all 0.25s ease;
    }

    .input-group-lock .form-control-lock::placeholder { color: rgba(148, 163, 184, 0.6); }

    .input-group-lock .form-control-lock:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.18);
    }

    .toggle-password {
        position: absolute;
        right: 0.625rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.3125rem;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .toggle-password:hover { background: rgba(255, 255, 255, 0.08); color: #3b82f6; }

    .error-message {
        color: #f87171;
        font-size: 0.8rem;
        margin-top: 0.3125rem;
        display: none;
        background: rgba(239, 68, 68, 0.1);
        padding: 0.5625rem 0.75rem;
        border-radius: 0.5rem;
        border: 0.0625rem solid rgba(239, 68, 68, 0.25);
        text-align: left;
    }

    .button-row {
        display: flex;
        align-items: center;
        gap: 0;
        margin-top: 0.875rem;
    }

    .button-row .elegant-btn {
        flex: 1;
        padding: 0.8125rem 1rem;
        border-radius: 0.75rem;
        font-weight: 600;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.925rem;
        cursor: pointer;
        border: none;
    }

    .button-row .btn-unlock {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
    }

    .button-row .btn-unlock:hover { transform: translateY(-0.0625rem); box-shadow: 0 0.5rem 1.1rem rgba(37, 99, 235, 0.35); }

    .button-row .btn-logout {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        background: rgba(239, 68, 68, 0.1);
        border: 0.0625rem solid rgba(239, 68, 68, 0.3);
        color: #f87171;
    }

    .button-row .btn-logout:hover {
        background: rgba(239, 68, 68, 0.18);
        transform: translateY(-0.0625rem);
    }

    .button-divider {
        padding: 0 0.625rem;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 500;
    }

    /* ===== FOOTER ===== */
    .lock-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.375rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 0.0625rem solid rgba(255, 255, 255, 0.07);
        font-size: 0.78rem;
        color: #64748b;
    }

    .lock-footer i { color: #10b981; }
    .lock-footer .sep { color: #334155; }

    /* ===== BADGE TEMPORIZADOR (arriba-derecha) ===== */
    .session-time {
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(0.5rem);
        -webkit-backdrop-filter: blur(0.5rem);
        border: 0.0625rem solid rgba(59, 130, 246, 0.28);
        border-radius: 1rem;
        padding: 0.625rem 1rem;
        min-width: 11.5rem;
        text-align: center;
        z-index: 5;
        color: #cbd5e1;
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.35);
    }

    .session-time-head {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06rem;
        color: #94a3b8;
    }

    .session-time-head i { color: #f59e0b; }

    .session-time-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #ffffff;
        margin: 0.25rem 0 0.375rem;
        font-variant-numeric: tabular-nums;
    }

    .session-time-value .mts { font-size: 0.72rem; font-weight: 500; color: #94a3b8; margin-left: 0.2rem; }

    .session-time-progress {
        height: 0.25rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2rem;
        overflow: hidden;
    }

    .session-time-progress div {
        height: 100%;
        width: 100%;
        background: linear-gradient(90deg, #22c55e, #3b82f6);
        border-radius: 2rem;
        transition: width 1s linear;
    }

    /* ===== MODAL INFORMACIÓN ===== */
    .lock-info-modal {
        text-align: left;
        max-height: 60vh;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .lock-info-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.625rem 0;
        border-bottom: 0.0625rem solid rgba(148, 163, 184, 0.15);
    }

    .lock-info-row:last-child { border-bottom: none; }

    .lock-info-row > i {
        margin-top: 0.2rem;
        width: 1.5rem;
        text-align: center;
        color: #3b82f6;
    }

    .lock-info-row .lock-info-body { flex: 1; min-width: 0; }

    .lock-info-row .lock-info-body strong {
        display: block;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04rem;
        color: #64748b;
        margin-bottom: 0.15rem;
    }

    .lock-info-row .lock-info-body span {
        font-size: 0.9rem;
        color: #e2e8f0;
        word-break: break-word;
    }

    [data-theme="light"] .lock-info-row .lock-info-body span { color: #1f2937; }
    [data-theme="light"] .lock-info-row .lock-info-body strong { color: #6b7280; }
    [data-theme="light"] .lock-info-row { border-bottom-color: rgba(0, 0, 0, 0.1); }

    @keyframes shakeLock {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-0.5rem); }
        40%, 80% { transform: translateX(0.5rem); }
    }

    .lock-shake { animation: shakeLock 0.5s ease-in-out; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        body { padding: 0.75rem; }
        .lock-container { padding: 1.375rem 0.875rem; margin: 0.5rem auto; }
        .titlebar-lock { margin: -1.375rem -0.875rem 1.25rem; }
        .lock-title { font-size: 1.2rem; }
        .session-time { top: 0.5rem; right: 0.5rem; min-width: 10rem; padding: 0.5rem 0.75rem; }
        .session-time-value { font-size: 0.95rem; }
    }
</style>
</head>
<body>

    <!-- Tiempo de sesión bloqueada (arriba-derecha) -->
    <div class="session-time" id="sessionTimer">
        <div class="session-time-head">
            <i class="fas fa-hourglass-half"></i>
            <span>Cierra en:</span>
        </div>
        <div class="session-time-value">
            <span id="timerDisplay">00:10:00</span><span class="mts">mts</span>
        </div>
        <div class="session-time-progress"><div id="timerProgress"></div></div>
    </div>

    <div class="lock-screen">
        <div class="lock-container" id="lockCard">
            <!-- BARRA DE TÍTULO (estilo ventana, como login.php) -->
            <div class="titlebar-lock" id="lockTitlebar">
                <div class="titlebar-title">
                    <i class="fas fa-lock"></i> Sesión bloqueada
                </div>
                <div class="titlebar-buttons">
                    <button type="button" class="titlebar-btn tb-collapse" id="btnCollapseLock" title="Colapsar / Expandir">
                        <i class="fas fa-chevron-down" id="collapseIcon"></i>
                    </button>
                    <button type="button" class="titlebar-btn tb-info" id="btnInfoLock" title="Información de la sesión">
                        <i class="fas fa-circle-question"></i>
                    </button>
                </div>
            </div>

            <!-- CABECERA: candado animado + título a su lado -->
            <div class="lock-top">
                <div class="lock-icon"><i class="fas fa-lock"></i></div>
                <h1 class="lock-title">Sesión Bloqueada</h1>
            </div>

            <!-- FOTO + NOMBRE + ROL + HORA - FECHA -->
            <div class="user-info-card">
                <div class="user-avatar-large <?php echo !empty($foto_usuario) ? 'has-photo' : ''; ?>" id="userAvatar">
                    <?php if (!empty($foto_usuario)): ?>
                        <img class="avatar-img"
                             src="<?php echo htmlspecialchars($foto_usuario); ?>"
                             alt="Foto de perfil"
                             onerror="this.remove(); var p=this.parentElement; if(p&&p.querySelector('.avatar-iniciales')){p.querySelector('.avatar-iniciales').style.display='flex';}">
                    <?php endif; ?>
                    <span class="avatar-iniciales" style="<?php echo !empty($foto_usuario) ? 'display:none;' : 'display:flex;'; ?>"><?php echo htmlspecialchars($iniciales); ?></span>
                </div>

                <div class="user-name"><?php echo htmlspecialchars($nombre_completo); ?></div>

                <div class="user-role">
                    <i class="fas fa-user-tag"></i>
                    <span><?php echo htmlspecialchars($rol_descripcion); ?></span>
                </div>

                <!-- Hora - Fecha en una misma línea -->
                <div class="user-meta">
                    <span id="currentTime"><?php echo date('h:i:s A'); ?></span>
                    <span class="meta-sep">-</span>
                    <span id="currentDate"><?php echo _fechaBloqueoLarga(time()); ?></span>
                </div>
            </div>

            <!-- ESTADO VERDE CON PUNTO PULSANTE -->
            <div class="status-box">
                <span class="status-dot"></span>
                <div>
                    <div class="status-title">Sesión bloqueada</div>
                    <div class="status-sub">
                        A los <span class="fw-bold" id="closeInLabel">00:10:00</span> la sesión se cerrará automáticamente.
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE DESBLOQUEO -->
            <form class="unlock-form" id="unlockForm" novalidate>
                <div class="input-group-lock">
                    <input type="password" class="form-control-lock" id="passwordInput"
                           placeholder="Ingresa tu contraseña para desbloquear"
                           required autocomplete="current-password">
                    <button type="button" class="toggle-password" id="togglePassword" tabindex="-1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="error-message" id="errorMessage">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="errorText">Contraseña incorrecta</span>
                </div>

                <div class="button-row">
                    <button type="submit" class="elegant-btn btn-unlock">
                        <i class="fas fa-unlock"></i>
                        <span>Desbloquear</span>
                    </button>
                    <div class="button-divider"><span>o</span></div>
                    <button type="button" class="elegant-btn btn-logout" id="logoutButton">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </button>
                </div>
            </form>

            <!-- FOOTER -->
            <div class="lock-footer">
                <i class="fas fa-shield-alt"></i>
                <span>Sistema protegido</span>
                <span class="sep">&bull;</span>
                <span><?php echo htmlspecialchars($bloqueo_brand . ' ' . $bloqueo_version); ?></span>
                <span class="sep">&bull;</span>
                <span><?php echo htmlspecialchars($bloqueo_company); ?></span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/sweetalert211.js"></script>
    <script>
    (function () {
        'use strict';

        // ============================================================
        // DATOS DEL SERVIDOR
        // ============================================================
        var LOCK_SEGUNDOS = <?php echo (int) $LOCK_SEGUNDOS; ?>;
        var lockOrigin = <?php echo json_encode($_SESSION['bloqueo_origen'] ?? 'dashboard.php'); ?>;
        var lockUser = '<?php echo addslashes(htmlspecialchars($nombre_completo)); ?>';
        var maxAttempts = 3;
        var unlockAttempts = 0;

        // Tiempo restante (se reinicia en cada carga de la pantalla)
        var sessionStartTime = Date.now();

        // ============================================================
        // RELOJ (12h) + FECHA + FECHA-HORA DEL CONTADOR
        // ============================================================
        var diasSem = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        var mesesAnio = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        function pad2(n) { return (n < 10 ? '0' : '') + n; }

        function fechaLargaES(d) {
            return diasSem[d.getDay()] + ', ' + d.getDate() + ' de ' + mesesAnio[d.getMonth()] + ' de ' + d.getFullYear();
        }

        function hora12(d) {
            var h = d.getHours();
            var ampm = h >= 12 ? 'pm' : 'am';
            h = h % 12; if (h === 0) h = 12;
            return pad2(h) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds()) + ' ' + ampm;
        }

        function formatHMS(totalSeg) {
            var h = Math.floor(totalSeg / 3600);
            var m = Math.floor((totalSeg % 3600) / 60);
            var s = totalSeg % 60;
            return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
        }

        var timerDisplayEl = document.getElementById('timerDisplay');
        var closeInLabelEl = document.getElementById('closeInLabel');
        var timerProgressEl = document.getElementById('timerProgress');

        function updateDateTime() {
            var now = new Date();
            var elTime = document.getElementById('currentTime');
            var elDate = document.getElementById('currentDate');
            if (elTime) elTime.textContent = hora12(now);
            if (elDate) elDate.textContent = fechaLargaES(now);

            // Cuenta regresiva (HH:MM:SS) y barra de progreso
            var elapsed = Math.floor((Date.now() - sessionStartTime) / 1000);
            var remaining = Math.max(0, LOCK_SEGUNDOS - elapsed);
            var txt = formatHMS(remaining);
            if (timerDisplayEl) timerDisplayEl.textContent = txt;
            if (closeInLabelEl) closeInLabelEl.textContent = txt;
            if (timerProgressEl) {
                timerProgressEl.style.width = ((remaining / LOCK_SEGUNDOS) * 100).toFixed(2) + '%';
            }
            return remaining;
        }

        // ============================================================
        // CIERRE AUTOMÁTICO AL LLEGAR A 0
        // ============================================================
        var autoClosed = false;

        function cerrarPorInactividad() {
            if (autoClosed) return;
            autoClosed = true;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión expirada',
                    html: '<p>La sesión bloqueada ha expirado.</p><p>Serás redirigido a la página de inicio de sesión.</p>',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Continuar',
                    background: 'rgba(15, 23, 42, 0.95)',
                    color: '#e2e8f0',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false
                }).then(function () { window.location.href = 'logout.php'; });
            } else {
                window.location.href = 'logout.php';
            }
        }

        // ============================================================
        // BARRA DE TÍTULO: colapsar/expandir + info + doble clic + drag
        // ============================================================
        var card = document.getElementById('lockCard');
        var titlebar = document.getElementById('lockTitlebar');
        var btnCollapse = document.getElementById('btnCollapseLock');
        var btnInfo = document.getElementById('btnInfoLock');
        var collapseIcon = document.getElementById('collapseIcon');

        function toggleCollapse() {
            var collapsed = card.classList.toggle('collapsed');
            if (collapseIcon) {
                collapseIcon.className = collapsed ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            }
            if (btnCollapse) {
                btnCollapse.title = collapsed ? 'Expandir' : 'Colapsar / Expandir';
            }
        }

        if (btnCollapse) {
            btnCollapse.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleCollapse();
            });
        }

        // ============================================================
        // MODAL DE INFORMACIÓN DE LA SESIÓN (botón ?)
        // ============================================================
        var infoHTML = '' +
            '<div class="lock-info-modal">' +
            '<div class="lock-info-row">' +
            '  <i class="fas fa-lock"></i>' +
            '  <div class="lock-info-body"><strong>Hora en que se bloqueó la sesión</strong><span><?php echo addslashes(htmlspecialchars($hora_bloqueo_txt)); ?></span></div>' +
            '</div>' +
            '<div class="lock-info-row">' +
            '  <i class="fas fa-sign-in-alt"></i>' +
            '  <div class="lock-info-body"><strong>Último inicio de sesión</strong><span><?php echo addslashes(htmlspecialchars($ultimo_login_txt)); ?></span></div>' +
            '</div>' +
            '<div class="lock-info-row">' +
            '  <i class="fas fa-history"></i>' +
            '  <div class="lock-info-body"><strong>Última operación registrada</strong><span><?php echo addslashes(htmlspecialchars($ultima_op_txt)); ?></span></div>' +
            '</div>' +
            '<div class="lock-info-row">' +
            '  <i class="fas fa-network-wired"></i>' +
            '  <div class="lock-info-body"><strong>Rol del usuario</strong><span><?php echo addslashes(htmlspecialchars($rol_descripcion)); ?></span></div>' +
            '</div>' +
            '<div class="lock-info-row">' +
            '  <i class="fas fa-hourglass-half"></i>' +
            '  <div class="lock-info-body"><strong>Cierre automático</strong><span>La sesión se cerrará al cabo de <?php echo (int)($LOCK_SEGUNDOS / 60); ?> minutos si no se desbloquea.</span></div>' +
            '</div>' +
            '</div>';

        if (btnInfo) {
            btnInfo.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof Swal === 'undefined') return;
                Swal.fire({
                    title: '<i class="fas fa-circle-info" style="color:#3b82f6"></i> Información de la sesión',
                    html: infoHTML,
                    icon: null,
                    width: '34rem',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                    confirmButtonColor: '#3b82f6',
                    background: '<?php echo (isset($_COOKIE) && !empty($_COOKIE['transnubet_theme']) && $_COOKIE['transnubet_theme'] === 'light') ? '#ffffff' : 'rgba(15, 23, 42, 0.96)'; ?>',
                    color: '#ffffff',
                    backdrop: 'rgba(0, 0, 0, 0.65)',
                    allowOutsideClick: true
                });
            });
        }

        if (titlebar) {
            // Doble clic para colapsar/expandir
            titlebar.addEventListener('dblclick', function (e) {
                if (e.target.closest('.titlebar-btn')) return;
                toggleCollapse();
            });

            // Drag & drop de la tarjeta (igual que login.php)
            var startX = 0, startY = 0, origL = 0, origT = 0, dragging = false;
            titlebar.addEventListener('mousedown', function (e) {
                if (e.target.closest('.titlebar-btn')) return;
                e.preventDefault();
                var r = card.getBoundingClientRect();
                origL = r.left;
                origT = r.top;
                startX = e.clientX;
                startY = e.clientY;
                dragging = true;
                titlebar.classList.add('dragging');
                card.classList.add('dragging');
                document.body.style.cursor = 'grabbing';
                document.body.style.userSelect = 'none';
            });
            document.addEventListener('mousemove', function (e) {
                if (!dragging) return;
                var dx = e.clientX - startX;
                var dy = e.clientY - startY;
                var r = card.getBoundingClientRect();
                var w = r.width || card.offsetWidth;
                var h = r.height || card.offsetHeight;
                var vw = window.innerWidth;
                var vh = window.innerHeight;
                var newL = Math.max(0, Math.min(vw - w, origL + dx));
                var newT = Math.max(0, Math.min(vh - h, origT + dy));
                card.style.position = 'fixed';
                card.style.margin = '0';
                card.style.left = newL + 'px';
                card.style.top = newT + 'px';
            });
            document.addEventListener('mouseup', function () {
                if (!dragging) return;
                dragging = false;
                titlebar.classList.remove('dragging');
                card.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            });
        }

        // ============================================================
        // MOSTRAR/OCULTAR CONTRASEÑA
        // ============================================================
        var togglePassword = document.getElementById('togglePassword');
        var passwordInput = document.getElementById('passwordInput');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                var isPass = passwordInput.type === 'password';
                passwordInput.type = isPass ? 'text' : 'password';
                var icon = togglePassword.querySelector('i');
                if (icon) icon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
                togglePassword.title = isPass ? 'Ocultar contraseña' : 'Mostrar contraseña';
            });
        }

        // ============================================================
        // ENVIAR DESBLOQUEO
        // ============================================================
        var unlockForm = document.getElementById('unlockForm');
        var logoutButton = document.getElementById('logoutButton');

        function mostrarError(msg) {
            var errorBox = document.getElementById('errorMessage');
            var errorText = document.getElementById('errorText');
            if (errorText) errorText.textContent = msg;
            if (errorBox) errorBox.style.display = 'block';
            var form = unlockForm;
            if (form) {
                form.classList.remove('lock-shake');
                void form.offsetWidth;
                form.classList.add('lock-shake');
                setTimeout(function () { form.classList.remove('lock-shake'); }, 600);
            }
            if (passwordInput) {
                passwordInput.style.borderColor = '#ef4444';
                passwordInput.style.boxShadow = '0 0 0 0.2rem rgba(239,68,68,0.2)';
                setTimeout(function () {
                    passwordInput.style.borderColor = '';
                    passwordInput.style.boxShadow = '';
                }, 1500);
                passwordInput.value = '';
                passwordInput.focus();
            }
        }

        if (unlockForm) {
            unlockForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                var unlockButton = unlockForm.querySelector('.btn-unlock');
                var errorBox = document.getElementById('errorMessage');

                if (!passwordInput || passwordInput.value.trim() === '') {
                    mostrarError('La contraseña no puede estar vacía');
                    return;
                }

                unlockAttempts++;
                if (unlockAttempts >= maxAttempts) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Demasiados intentos',
                            html: '<p>Has excedido el número máximo de intentos permitidos.</p><p>La sesión se cerrará por seguridad.</p>',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido',
                            confirmButtonColor: '#ef4444',
                            background: 'rgba(15, 23, 42, 0.95)',
                            color: '#e2e8f0',
                            allowOutsideClick: false
                        }).then(function () { window.location.href = 'logout.php'; });
                    } else {
                        window.location.href = 'logout.php';
                    }
                    return;
                }

                if (unlockButton) {
                    unlockButton.disabled = true;
                    unlockButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Verificando...</span>';
                }
                if (errorBox) errorBox.style.display = 'none';

                try {
                    var fd = new FormData();
                    fd.append('password', passwordInput.value);

                    var resp = await fetch('verificar_contrasena_bloqueo.php', { method: 'POST', body: fd });
                    var text = await resp.text();
                    var data;
                    try { data = JSON.parse(text); } catch (e2) { throw new Error('Respuesta no válida del servidor'); }

                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '¡Sesión desbloqueada!',
                                text: 'Bienvenido de nuevo, ' + lockUser,
                                timer: 1200,
                                showConfirmButton: false,
                                background: 'rgba(15, 23, 42, 0.95)',
                                color: '#e2e8f0'
                            });
                        }
                        try { await fetch('limpiar_bloqueo.php', { method: 'POST' }); } catch (eIgn) {}
                        window.location.href = lockOrigin;
                    } else {
                        throw new Error(data.error || 'Contraseña incorrecta');
                    }
                } catch (err) {
                    mostrarError(err.message || 'Error del servidor');
                } finally {
                    if (unlockButton) {
                        unlockButton.disabled = false;
                        unlockButton.innerHTML = '<i class="fas fa-unlock"></i><span>Desbloquear</span>';
                    }
                }
            });
        }

        // ============================================================
        // CERRAR SESIÓN
        // ============================================================
        if (logoutButton) {
            logoutButton.addEventListener('click', function (e) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'question',
                        title: '¿Cerrar sesión?',
                        html: '<p>¿Estás seguro que deseas cerrar la sesión activa?</p><p class="swal2-sm-text">Serás redirigido a la página de inicio de sesión.</p>',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-sign-out-alt me-2"></i>Sí, cerrar sesión',
                        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#6b7280',
                        background: 'rgba(15, 23, 42, 0.95)',
                        color: '#e2e8f0',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then(function (r) {
                        if (r.isConfirmed) {
                            Swal.fire({
                                title: 'Cerrando sesión...',
                                allowOutsideClick: false,
                                didOpen: function () { Swal.showLoading(); },
                                background: 'rgba(15, 23, 42, 0.95)',
                                color: '#e2e8f0'
                            });
                            window.location.href = 'logout.php';
                        }
                    });
                } else {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Intro enfoca el campo de contraseña
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && document.activeElement && document.activeElement.id !== 'passwordInput') {
                if (passwordInput && !passwordInput.disabled) passwordInput.focus();
            }
        });

        // ============================================================
        // INICIALIZACIÓN
        // ============================================================
        var secondsTimer = LOCK_SEGUNDOS;
        updateDateTime();
        setInterval(function () {
            var remaining = updateDateTime();
            if (remaining === 0) { cerrarPorInactividad(); }
        }, 1000);

        setTimeout(function () {
            if (passwordInput && !passwordInput.disabled) passwordInput.focus();
        }, 400);

        // Prevenir navegación hacia atrás fuera de la pantalla de bloqueo
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, null, location.href);
        });
    })();
    </script>
</body>
</html>