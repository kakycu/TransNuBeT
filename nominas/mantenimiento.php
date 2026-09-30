<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/subsistemas.php';

header('Content-Type: text/html; charset=utf-8');

$motivo = ($_GET['motivo'] ?? ($_SESSION['bloqueo_motivo'] ?? 'mantenimiento')) === 'subsistema_inactivo'
    ? 'subsistema_inactivo'
    : 'mantenimiento';

$es_programador = (int)($_SESSION['rol_id'] ?? 0) === 5;
$nombre_usuario = trim(($_SESSION['user_nombre'] ?? '') . ' ' . (($_SESSION['user_nombre'] ?? '') === '' ? ($_SESSION['username'] ?? '') : ''));
if ($nombre_usuario === '') {
    $nombre_usuario = $_SESSION['username'] ?? 'Usuario';
}

$email_soporte = 'kakycu@gmail.com';
$whatsapp = null;
try {
    $stmtW = $pdo->prepare("SELECT parametro, valor FROM configuracion_general WHERE parametro IN ('email_soporte','whatsapp_numero','whatsapp_ON')");
    $stmtW->execute();
    $cfgW = $stmtW->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($cfgW['email_soporte'])) {
        $email_soporte = trim((string)$cfgW['email_soporte']);
    }
    if (isset($cfgW['whatsapp_ON']) && (string)$cfgW['whatsapp_ON'] === '1' && !empty($cfgW['whatsapp_numero'])) {
        $whatsapp = preg_replace('/\D+/', '', (string)$cfgW['whatsapp_numero']);
    }
} catch (Exception $e) { }

if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json; charset=utf-8');
    $activo = subsistema_activo($pdo, '0001');
    echo json_encode([
        'maintenance' => modo_mantenimiento_activo($pdo),
        'subsistema'  => (bool)$activo,
        'rol'         => (int)($_SESSION['rol_id'] ?? 0),
    ]);
    exit;
}

$titulo      = $motivo === 'subsistema_inactivo' ? 'Módulo deshabilitado' : 'Sistema en mantenimiento';
$subtitulo   = $motivo === 'subsistema_inactivo'
    ? 'El administrador desactivó el módulo de Nóminas.'
    : 'El equipo técnico está realizando mejoras en el sistema.';
$color_prim  = $motivo === 'subsistema_inactivo' ? '#f59e0b' : '#ffc107';
$icono       = $motivo === 'subsistema_inactivo' ? 'fa-toggle-off' : 'fa-helmet-safety';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($titulo); ?> - <?php echo htmlspecialchars($COMPANY_NAME ?? 'Sistema'); ?></title>
<link rel="icon" type="image/x-icon" href="../images/favicons/nominas.ico">
<link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    background: #0d1117;
    font-family: 'Segoe UI', Tahoma, sans-serif;
    color: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    overflow-x: hidden;
}

/* ===== BARRA SUPERIOR ===== */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 0.625rem 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: #0d1117;
    background: linear-gradient(90deg, var(--bar-color, #ffc107), #ffe08a, var(--bar-color, #ffc107));
    background-size: 200% 100%;
    animation: barPulse 3s ease-in-out infinite;
    box-shadow: 0 0.1875rem 0.75rem rgba(0, 0, 0, 0.45);
    text-align: center;
}
.topbar i { font-size: 0.9375rem; }
@keyframes barPulse {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.maintenance-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background:
        radial-gradient(circle at 20% 30%, rgba(255,193,7,0.08), transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,0.08), transparent 50%);
    pointer-events: none;
}
.gear { position: fixed; color: rgba(255,193,7,0.06); font-size: 9rem; animation: spin 22s linear infinite; }
.gear-1 { top: 12%; left: 8%; }
.gear-2 { bottom: 14%; right: 10%; font-size: 12rem; animation-duration: 30s; animation-direction: reverse; }
.gear-3 { top: 45%; right: 4%; font-size: 6rem; animation-duration: 17s; }
@keyframes spin { to { transform: rotate(360deg); } }

.card {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 34rem;
    background: rgba(22, 27, 34, 0.92);
    border: 0.0625rem solid rgba(255,193,7,0.22);
    border-radius: 1.25rem;
    padding: 2.5rem 1.75rem 1.75rem;
    text-align: center;
    box-shadow: 0 1.25rem 3.75rem rgba(0,0,0,0.55);
    margin-top: 3.5rem;
}
.icon {
    width: 5rem; height: 5rem;
    margin: 0 auto 1.25rem;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.25rem;
    color: var(--color-main, #ffc107);
    background: rgba(255,193,7,0.1);
    border: 0.0625rem solid rgba(255,193,7,0.3);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(255,193,7,0.35); } 50% { box-shadow: 0 0 0 1.25rem rgba(255,193,7,0); } }

h1 { font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--color-main, #ffc107); }
.sub { color: #9aa4b2; font-size: 0.9375rem; line-height: 1.6; margin-bottom: 1.5rem; }
.sub strong { color: #e6edf3; }

.badge-prog {
    display: inline-block;
    background: rgba(220,53,69,0.15);
    color: #ff6b6b;
    font-size: 0.8125rem;
    padding: 0.3125rem 0.625rem;
    border-radius: 0.3125rem;
    border: 0.0625rem solid rgba(220,53,69,0.4);
    margin-bottom: 1.25rem;
    font-weight: 600;
}

.info-box {
    background: rgba(255,255,255,0.03);
    border: 0.0625rem solid rgba(255,255,255,0.07);
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: left;
    margin-bottom: 0.875rem;
}
.info-box h4 { font-size: 0.875rem; color: var(--color-main, #ffc107); margin-bottom: 0.375rem; }
.info-box p { font-size: 0.8125rem; color: #9aa4b2; line-height: 1.55; }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%;
    padding: 0.75rem 1rem;
    margin-top: 0.625rem;
    border-radius: 0.625rem;
    border: 0.0625rem solid transparent;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .2s;
}
.btn-refresh { background: var(--color-main, #ffc107); color: #0d1117; }
.btn-refresh:hover { filter: brightness(1.1); }
.btn-refresh:disabled { opacity: .6; cursor: not-allowed; }
.btn-prog { background: rgba(220,53,69,0.15); color: #ff6b6b; border-color: rgba(220,53,69,0.4); }
.btn-prog:hover { background: rgba(220,53,69,0.28); }
.btn-wa { background: rgba(37,211,102,0.12); color: #25d366; border-color: rgba(37,211,102,0.3); }
.btn-wa:hover { background: rgba(37,211,102,0.22); }
.btn-mail { background: rgba(59,130,246,0.12); color: #60a5fa; border-color: rgba(59,130,246,0.3); }
.btn-mail:hover { background: rgba(59,130,246,0.22); }
.btn-logout { background: transparent; color: #8b949e; border-color: rgba(255,255,255,0.12); }
.btn-logout:hover { color: #fca5a5; border-color: #ef4444; }

.titulo-soporte { font-size: 0.8125rem; color: #8b949e; margin-top: 1.5rem; margin-bottom: 0.25rem; }
</style>
</head>
<body>

<div class="topbar" style="--bar-color: <?php echo $color_prim; ?>">
    <i class="fa-solid <?php echo $icono; ?>"></i>
    <span><?php echo htmlspecialchars($titulo); ?> &mdash; El acceso se encuentra restringido</span>
</div>

<div class="maintenance-bg"></div>
<div class="gear gear-1"><i class="fas fa-cog"></i></div>
<div class="gear gear-2"><i class="fas fa-cog"></i></div>
<div class="gear gear-3"><i class="fas fa-cog"></i></div>

<div class="card" style="--color-main: <?php echo $color_prim; ?>">
    <div class="icon"><i class="fa-solid <?php echo $icono; ?>"></i></div>
    <h1><?php echo htmlspecialchars($titulo); ?></h1>
    <p class="sub">
        Hola <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>,
        detectamos que tienes una sesi&oacute;n activa, pero <?php echo htmlspecialchars($subtitulo); ?>
        <br>El acceso al panel est&aacute; temporalmente deshabilitado.
    </p>

    <?php if ($es_programador): ?>
        <span class="badge-prog">
            <i class="fa-solid fa-user-secret"></i> Usted es Programador, puede acceder en Modo Mantenimiento
        </span>
    <?php endif; ?>

    <div class="info-box">
        <h4><i class="fa-solid fa-circle-info"></i> &iquest;Qu&eacute; est&aacute; pasando?</h4>
        <p><?php echo $motivo === 'subsistema_inactivo'
                ? 'Un administrador desactiv&oacute; el m&oacute;dulo de N&oacute;minas. No podr&aacute; usar el sistema hasta que vuelva a habilitarse.'
                : 'El equipo t&eacute;cnico est&aacute; realizando mejoras. Durante este tiempo el acceso al panel de control est&aacute; deshabilitado.'; ?></p>
    </div>

    <div class="info-box">
        <h4><i class="fa-solid fa-clock"></i> Tiempo estimado</h4>
        <p>El mantenimiento suele completarse en 30-60 minutos. Le notificaremos cuando el sistema vuelva a estar disponible.</p>
    </div>

    <?php if ($es_programador): ?>
        <a href="login.php?access_bypass=true" class="btn btn-prog">
            <i class="fa-solid fa-user-secret"></i> Acceder como Programador
        </a>
    <?php endif; ?>

    <?php if ($whatsapp): ?>
        <a href="https://api.whatsapp.com/send?phone=<?php echo $whatsapp; ?>" target="_blank" rel="noopener" class="btn btn-wa">
            <i class="fab fa-whatsapp"></i> WhatsApp Soporte
        </a>
    <?php endif; ?>

    <a href="mailto:<?php echo htmlspecialchars($email_soporte); ?>" class="btn btn-mail">
        <i class="fa-solid fa-envelope"></i> Enviar Correo
    </a>

    <button class="btn btn-refresh" id="btnRecheck" onclick="recheckStatus(this)">
        <i class="fa-solid fa-rotate-right"></i> Verificar estado nuevamente
    </button>

    <a href="logout.php" class="btn btn-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesi&oacute;n
    </a>
</div>

<script>
function recheckStatus(btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando...';

    fetch('mantenimiento.php?action=check_status')
        .then(r => r.json())
        .then(data => {
            setTimeout(() => {
                if (data.maintenance || data.subsistema === false) {
                    btn.innerHTML = data.maintenance
                        ? '<i class="fa-solid fa-clock"></i> A\u00fan en mantenimiento'
                        : '<i class="fa-solid fa-toggle-off"></i> M\u00f3dulo a\u00fan deshabilitado';
                    setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
                } else {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> \u00a1Sistema disponible!';
                    setTimeout(() => { window.location.href = 'dashboard.php'; }, 1000);
                }
            }, 1000);
        })
        .catch(() => {
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Error de conexi\u00f3n';
            setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
        });
}
setInterval(() => recheckStatus(document.getElementById('btnRecheck')), 120000);
</script>
</body>
</html>
