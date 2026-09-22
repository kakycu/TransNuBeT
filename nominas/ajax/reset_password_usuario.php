<?php
require_once '../config/database.php';
require_once '../config/mail.php';
require_once __DIR__ . '/../logger.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
    exit;
}

// Cualquier usuario puede resetear SU propia contraseña; para otros solo Admin
$id_actual = intval($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
if ($id !== $id_actual && permiso_rol_codigo() !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado para resetear esta contraseña']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre, apellidos, usuario, email, activo FROM clasif_usuarios WHERE id = ?");
$stmt->execute([$id]);
$usr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usr) {
    echo json_encode(['success' => false, 'message' => 'El usuario no existe']);
    exit;
}

// Generar contraseña aleatoria
$longitud = 12;
$caracteres = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$nueva_password = '';
$max = strlen($caracteres) - 1;
for ($i = 0; $i < $longitud; $i++) {
    $nueva_password .= $caracteres[random_int(0, $max)];
}

$hashed = password_hash($nueva_password, PASSWORD_DEFAULT);

try {
    // Guardar nueva contraseña y limpiar tokens previos
    $stmt = $pdo->prepare("UPDATE clasif_usuarios SET password = ?, reset_token = NULL, reset_expira = NULL, fecha_actualizacion = NOW() WHERE id = ?");
    $stmt->execute([$hashed, $id]);

    // ===== AUDITORÍA: reset de contraseña de un usuario =====
    logAction(
        'resetear_password_usuario',
        'usuarios',
        'Reset de contraseña de un usuario',
        ['user_id' => (int)$id, 'usuario' => $usr['usuario']],
        (int)$id,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );

    $nombre_completo = trim(($usr['nombre'] ?? '') . ' ' . ($usr['apellidos'] ?? ''));

    // Enviar correo solo si el usuario tiene email configurado
    $correo_enviado = false;
    $aviso_correo = '';
    if (!empty($usr['email']) && filter_var($usr['email'], FILTER_VALIDATE_EMAIL)) {
        // Generar token para permitir cambiar la contraseña desde el correo
        $token = bin2hex(random_bytes(32));
        $stmtUpd = $pdo->prepare("UPDATE clasif_usuarios SET reset_token = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?");
        $stmtUpd->execute([$token, $id]);

        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if (strpos($base, '/ajax') === strlen($base) - 5 || strpos($base, '/ajx') !== false) {
            $base = dirname($base);
        }
        if ($base === '' || $base === '.') $base = '';
        $enlace = $esquema . '://' . $host . $base . '/login.php?reset_token=' . $token;

        $empresa = defined('SITE_NAME') ? SITE_NAME : 'SisGesNom - Nóminas';
        $subject = 'Su contraseña fue restablecida - ' . $empresa;
        $anio = date('Y');

        $htmlBody = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contraseña restablecida</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, sans-serif; }
        body { background:#0f0f17; color:#e4e4e7; padding:2rem 1rem; }
        .body { background:#0f0f17; padding:2.5rem 1.25rem; }
        .email-container {
            max-width:560px;
            margin:0 auto;
            background:#16161e;
            border-radius:1rem;
            overflow:hidden;
            border:0.0625rem solid rgba(255,255,255,0.08);
            box-shadow: 0 1.5rem 3rem rgba(0,0,0,0.55);
        }
        .header {
            background: linear-gradient(135deg, rgba(59,130,246,0.18), rgba(59,130,246,0.05) 70%), #16161e;
            padding:1.75rem 1.5rem;
            border-bottom:0.0625rem solid rgba(255,255,255,0.06);
        }
        .header-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
        .header-icon { display:flex; align-items:center; gap:0.625rem; }
        .icon-circle {
            width:2.5rem; height:2.5rem; border-radius:0.75rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display:flex; align-items:center; justify-content:center;
            font-size:1.25rem; color:#fff;
        }
        .app-name { font-size:0.8125rem; font-weight:600; color:#cbd5e1; }
        .app-name span { color:#64748b; font-weight:400; }
        .header-badge {
            font-size:0.625rem; font-weight:600; letter-spacing:0.05em;
            color:#34d399; background:rgba(16,185,129,0.12);
            border:0.0625rem solid rgba(16,185,129,0.3);
            padding:0.3125rem 0.625rem; border-radius:999px;
        }
        .header-title { font-size:1.375rem; font-weight:700; color:#fff; letter-spacing:-0.01em; }
        .header-subtitle { font-size:0.8125rem; color:#94a3b8; margin-top:0.25rem; }
        .body { padding:1.5rem; }
        .greeting { font-size:1rem; margin-bottom:0.875rem; color:#f1f5f9; }
        .greeting strong { color:#fff; }
        .message-text { font-size:0.8125rem; line-height:1.6; color:#cbd5e1; margin-bottom:1.125rem; }
        .message-text strong { color:#f1f5f9; }
        .info-card {
            background:rgba(255,255,255,0.03);
            border:0.0625rem solid rgba(255,255,255,0.07);
            border-radius:0.625rem;
            padding:1rem 1.125rem;
            margin-bottom:1.125rem;
        }
        .card-row { display:flex; align-items:center; justify-content:space-between; padding:0.375rem 0; }
        .card-row + .card-row { border-top:0.0625rem solid rgba(255,255,255,0.05); }
        .card-label { font-size:0.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; }
        .card-value { font-size:0.8125rem; color:#f1f5f9; font-weight:600; word-break:break-all; text-align:right; }
        .card-value .pass-box {
            background:rgba(59,130,246,0.12);
            border:0.0625rem dashed rgba(59,130,246,0.4);
            padding:0.25rem 0.5rem; border-radius:0.375rem;
            font-family:"Cascadia Code", Consolas, monospace;
            font-size:0.875rem; color:#60a5fa;
            display:inline-block;
        }
        .btn-container { text-align:center; margin:1.25rem 0 0.5rem; }
        .btn-reset {
            display:inline-block;
            background:linear-gradient(135deg, #3b82f6, #2563eb);
            color:#ffffff !important;
            text-decoration:none;
            padding:0.75rem 1.75rem;
            border-radius:0.5rem;
            font-size:0.875rem;
            font-weight:600;
            box-shadow:0 0.5rem 1.25rem rgba(37,99,235,0.35);
        }
        .btn-reset:hover { background:linear-gradient(135deg, #60a5fa, #3b82f6); }
        .note-under-btn { font-size:0.625rem; color:#64748b; text-align:center; margin-top:0.375rem; }
        .link-box {
            background:rgba(255,255,255,0.02);
            border:0.0625rem solid rgba(255,255,255,0.06);
            border-radius:0.5rem;
            padding:0.75rem 1rem;
            margin:1rem 0 0.25rem;
        }
        .link-label { font-size:0.625rem; color:#64748b; margin-bottom:0.3125rem; }
        .link-url { font-size:0.6875rem; word-break:break-all; }
        .link-url a { color:#60a5fa; text-decoration:none; }
        hr.divider { border:none; border-top:0.0625rem solid rgba(255,255,255,0.07); margin:1.125rem 0; }
        .security-note {
            background:rgba(255,170,0,0.06);
            border-radius:0.5rem;
            padding:0.875rem 1.125rem;
            border-left:0.1875rem solid #ffa500;
        }
        .note-title { color:#ffa500; font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; }
        .note-text { color:#a1a1aa; font-size:0.75rem; margin-top:0.25rem; line-height:1.5; }
        .footer {
            background:#12121a;
            padding:1.25rem 1.5rem;
            text-align:center;
            border-top:0.0625rem solid rgba(255,255,255,0.06);
        }
        .security-badge { font-size:0.6875rem; color:#8a8a8a; margin-bottom:0.75rem; }
        .company-info { font-size:0.75rem; color:#6a6a6a; line-height:1.6; }
        .company-info strong { color:#a0a0a0; }
        .copyright {
            font-size:0.625rem; color:#5a5a5a; margin-top:0.625rem;
            padding-top:0.625rem; border-top:0.0625rem solid rgba(255,255,255,0.04);
        }
        @media (max-width: 480px) {
            .body { padding:1.5rem 1.125rem; }
            .header { padding:1.25rem 1.125rem; }
            .footer { padding:1rem 1.125rem 1.25rem; }
            .header-title { font-size:1.0625rem; }
            .btn-reset { padding:0.75rem 1.75rem; font-size:0.8125rem; width:100%; text-align:center; }
            .info-card { padding:1rem; }
            .header-top { flex-wrap:wrap; gap:0.5rem; }
            .header-badge { font-size:0.5625rem; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- HEADER -->
        <div class="header">
            <div class="header-top">
                <div class="header-icon">
                    <div class="icon-circle">🔑</div>
                    <div class="app-name">SisGesNom <span>· Nóminas</span></div>
                </div>
                <div class="header-badge">🔒 Seguro</div>
            </div>
            <div class="header-title">Su contraseña fue restablecida</div>
            <div class="header-subtitle">Un administrador reseteó su contraseña</div>
        </div>

        <!-- BODY -->
        <div class="body">
            <p class="greeting">Hola <strong>' . htmlspecialchars($nombre_completo) . '</strong>,</p>

            <p class="message-text">
                Un administrador restableció la contraseña de su cuenta
                <strong>' . htmlspecialchars($usr['usuario']) . '</strong> en el sistema
                <strong>' . htmlspecialchars($empresa) . '</strong>.
            </p>

            <!-- Info Card -->
            <div class="info-card">
                <div class="card-row">
                    <span class="card-label">👤 Usuario</span>
                    <span class="card-value">' . htmlspecialchars($usr['usuario']) . '</span>
                </div>
                <div class="card-row">
                    <span class="card-label">🔑 Nueva contraseña</span>
                    <span class="card-value"><span class="pass-box">' . htmlspecialchars($nueva_password) . '</span></span>
                </div>
            </div>

            <!-- Botón principal -->
            <div class="btn-container">
                <a href="' . htmlspecialchars($enlace) . '" class="btn-reset">
                    🔐 Cambiar mi contraseña
                </a>
            </div>
            <div class="note-under-btn">Si lo desea, puede cambiar esta contraseña temporal. El enlace es válido por 30 minutos.</div>

            <!-- Enlace alternativo -->
            <div class="link-box">
                <div class="link-label">📋 Enlace para cambiar la contraseña</div>
                <div class="link-url">
                    <a href="' . htmlspecialchars($enlace) . '">' . htmlspecialchars($enlace) . '</a>
                </div>
            </div>

            <hr class="divider">

            <!-- Nota de seguridad -->
            <div class="security-note">
                <div class="note-title">
                    <span>🛡️</span> Consejo de seguridad
                </div>
                <div class="note-text">
                    Por seguridad, le recomendamos cambiar esta contraseña temporal por una propia lo antes posible.
                    Si usted no solicitó este reseteo, contacte al administrador del sistema.
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <div class="security-badge">
                <span>🔒</span> Este mensaje ha sido enviado desde un sistema seguro
            </div>
            <div class="company-info">
                <strong>' . htmlspecialchars($empresa) . '</strong><br>
                Sistema de Gestión de Nóminas y Empleados
            </div>
            <div class="copyright">
                <span>© ' . $anio . ' ' . htmlspecialchars($empresa) . ' · Todos los derechos reservados</span>
            </div>
        </div>
    </div>
</body>
</html>';

            $textBody = "═══════════════════════════════════════════════════════\n"
                . "        CONTRASEÑA RESTABLECIDA\n"
                . "═══════════════════════════════════════════════════════\n\n"
                . "Hola {$nombre_completo},\n\n"
                . "Un administrador restableció la contraseña de su cuenta\n"
                . "de usuario '{$usr['usuario']}' en el sistema '{$empresa}'.\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  DATOS DE SU NUEVA CONTRASEÑA TEMPORAL\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  Usuario           : {$usr['usuario']}\n"
                . "  Nueva contraseña  : {$nueva_password}\n"
                . "───────────────────────────────────────────────────────────\n\n"
                . "Para cambiarla por una propia, abra el siguiente enlace\n"
                . "(válido por 30 minutos):\n\n"
                . "  {$enlace}\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  ¿NO SOLICITÓ ESTE CAMBIO?\n"
                . "───────────────────────────────────────────────────────────\n\n"
                . "Si usted no solicitó este reseteo, contacte al administrador.\n\n"
                . "───────────────────────────────────────────────────────────\n"
                . "  {$empresa}\n"
                . "  Sistema de Gestión de Nóminas y Empleados\n"
                . "───────────────────────────────────────────────────────────\n"
                . "© " . date('Y') . " {$empresa} · Todos los derechos reservados\n";

            $envio = enviarCorreo($pdo, $usr['email'], $nombre_completo, $subject, $htmlBody, $textBody);

            if ($envio['success']) {
                $correo_enviado = true;
            } else {
                // Si el correo no está configurado o falla, el token queda guardado igualmente
                $aviso_correo = ($envio['error'] === 'mail_not_configured')
                    ? 'No se pudo enviar el correo (SMTP no configurado).'
                    : 'No se pudo enviar el correo: ' . $envio['error'];
                $aviso_correo .= ' La nueva contraseña quedó establecida de todas formas.';
                $stmtUpd2 = $pdo->prepare("UPDATE clasif_usuarios SET reset_token = NULL, reset_expira = NULL WHERE id = ?");
                $stmtUpd2->execute([$id]);
            }
        }
    $respuesta = ['success' => true, 'message' => 'Contraseña reseteada correctamente', 'nueva_password' => $nueva_password, 'usuario' => $usr['usuario'] ?? '', 'nombre' => $nombre_completo];
    if ($correo_enviado) {
        $respuesta['correo'] = 'enviado';
        $respuesta['correo_texto'] = 'Se envió la nueva contraseña al correo del usuario.';
    } elseif (!empty($usr['email'])) {
        $respuesta['correo'] = 'no_enviado';
        $respuesta['correo_texto'] = $aviso_correo;
    } else {
        $respuesta['correo'] = 'sin_email';
        $respuesta['correo_texto'] = 'El usuario no tiene correo registrado.';
    }

    echo json_encode($respuesta);
    

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>