<?php
// licencia.php - Pantalla de registro de licencia (serial).
// Corre antes del login y NO requiere base de datos.
// Almacena los datos UNA sola vez, cifrados, en el registro de Windows
// o en un archivo cifrado del disco (Linux/otros).
// Soporta licencias por tiempo (1, 3, 6 meses, 1 ó 2 años) o permanentes.

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (!is_file(__DIR__ . '/includes/licencia.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Aplicación en mantenimiento</title>
        <link rel="stylesheet" href="/nominas/css/font-awesome6.4.0/css/all.min.css">
        <script src="/nominas/js/sweetalert2.all.min.js"></script>
        <style>
            body {
                margin:0;
                padding:0;
                background: linear-gradient(160deg, #f59e0b 0%, #7f1d1d 45%, #1c1917 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height:100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>
    </head>
    <body>
        <script>
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-shield-alt" style="color:#fbbf24"></i> Módulo de licencia no encontrado',
            html: 'Falta el archivo <b>includes/licencia.php</b>.<br>No es posible verificar la licencia de este sistema.<br>Reinstale los archivos de la aplicación.',
            confirmButtonText: '<i class="fas fa-sync-alt"></i> Reintentar',
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-download"></i> Reinstalar',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#3b82f6',
            allowOutsideClick: false,
            backdrop: 'rgba(0,0,0,0.85)'
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.reload();
            } else {
                var w = window.open('/InstalarBD/InstalarBD.php', '_blank');
                if (!w) { window.location.href = '/InstalarBD/InstalarBD.php'; }
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}
require_once __DIR__ . '/includes/licencia.php';

// Endpoint interno para validar en vivo el serial sin exponer el secreto al navegador.
if (isset($_REQUEST['validar_serial']) && (string)$_REQUEST['validar_serial'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $v_nombre  = isset($_REQUEST['nombre'])  ? trim((string)$_REQUEST['nombre'])  : '';
    $v_usuario = isset($_REQUEST['usuario']) ? trim((string)$_REQUEST['usuario']) : '';
    $v_serial  = isset($_REQUEST['serial'])  ? trim((string)$_REQUEST['serial'])  : '';
    $resp = array('ok' => false, 'motivo' => '');
    if ($v_nombre === '') {
        $resp['motivo'] = 'nombre';
    } elseif ($v_usuario === '') {
        $resp['motivo'] = 'usuario';
    } elseif (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){4}$/', strtoupper($v_serial)) !== 1) {
        $resp['motivo'] = 'formato';
    } elseif (!licencia_validar_serial($v_nombre, $v_usuario, $v_serial)) {
        $resp['motivo'] = 'invalida';
    } else {
        $resp['ok'] = true;
    }
    echo json_encode($resp);
    exit;
}

// Licencia previamente guardada (para diagnosticar y mostrar el motivo).
$lic_guardada = licencia_leer();

// Diagnóstico del estado de la licencia guardada para anunciarlo al usuario.
$lic_diagnostico = 'inexistente'; // inexistente | invalida | vencida | corrupta
if ($lic_guardada !== null) {
    $lic_diagnostico = 'invalida'; // descifrada pero la llave no coincide
    if (licencia_validar_serial($lic_guardada['registro'], $lic_guardada['usuario'], $lic_guardada['serial'])) {
        $lic_diagnostico = licencia_vencida($lic_guardada) ? 'vencida' : 'ok';
    }
} elseif (licencia_tiene_valor_guardado()) {
    $lic_diagnostico = 'corrupta'; // hay datos pero no se pueden descifrar/leer
}

$mensaje_diagnostico = '';
switch ($lic_diagnostico) {
    case 'vencida':
        $mensaje_diagnostico = 'La licencia registrada en este equipo <b>ha vencido</b>.<br>Introduzca una nueva llave de licencia para continuar.';
        break;
    case 'invalida':
        $mensaje_diagnostico = 'El registro de licencia almacenado en este equipo <b>no coincide</b> con la llave serial.<br>Verifique los datos o solicite una llave válida.';
        break;
    case 'corrupta':
        $mensaje_diagnostico = 'El registro de licencia almacenado está dañado o ha sido indebidamente alterado.<br>Introduzca un registro nuevo.';
        break;
}

// Si ya está registrado y NO vence, ir directo al login.
if (licencia_activada()) {
    header('Location: login.php');
    exit;
}

$error        = '';
$guardado_ok  = false;
$examinar_ok  = false;
$nombre       = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : '';
$usuario      = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
$serial       = isset($_POST['serial'])  ? trim((string)$_POST['serial'])  : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ***** Examinar archivo .lic: rellena los campos con los datos de la licencia *****
    if (isset($_POST['examinar']) && $_POST['examinar'] === '1') {
        if (empty($_FILES['archivo_lic']['tmp_name']) || ($_FILES['archivo_lic']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Debe <b>seleccionar un archivo .lic</b> válido para examinar.';
        } else {
            $ext = strtolower(pathinfo($_FILES['archivo_lic']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'lic') {
                $error = 'El archivo debe tener extensión <b>.lic</b>.';
            } else {
                $imp_datos = licencia_leer_archivo_lic($_FILES['archivo_lic']['tmp_name']);
                if ($imp_datos === null) {
                    $error = 'El archivo <b>.lic</b> seleccionado no es válido o su llave interna no corresponde.<br>Revise el archivo o solicite uno nuevo.';
                } else {
                    $fp_actual = licencia_fingerprint_machine();
                    if ($imp_datos['huella'] !== '' && !hash_equals($imp_datos['huella'], $fp_actual)) {
                        $error = 'Esta licencia fue emitida para <b>otro equipo</b>.<br>' .
                                 'Huella destino&nbsp;: <b>' . htmlspecialchars(licencia_formatear_fingerprint($imp_datos['huella'])) . '</b><br>' .
                                 'Este equipo&nbsp;&nbsp;&nbsp;&nbsp;: <b>' . htmlspecialchars(licencia_formatear_fingerprint($fp_actual)) . '</b><br>' .
                                 'Solicite al proveedor una licencia para esta PC.';
                    } else {
                        $nombre  = $imp_datos['registro'];
                        $usuario = $imp_datos['usuario'];
                        $serial  = licencia_formatear_serial($imp_datos['serial']);
                        $examinar_ok     = true;
                        $examinar_tipo   = $imp_datos['info']['nombre'];
                        $examinar_periodo = ($imp_datos['info']['meses'] !== null)
                            ? ((int)$imp_datos['info']['meses'] === 1 ? '1 mes' : (int)$imp_datos['info']['meses'] . ' meses')
                            : 'Permanente';
                        $examinar_nota   = ($imp_datos['huella'] !== '') ? '<br><b>Esta licencia es exclusiva de este equipo.</b>' : '';
                        $examinar_hasta  = ($imp_datos['info']['meses'] !== null) ? licencia_texto_vencimiento($imp_datos, 'Sin vencimiento (Permanente)') : 'Sin vencimiento (Permanente)';
                    }
                }
            }
        }
    } elseif ($nombre === '') {
        $error = 'Debe indicar el <b>Nombre de Registro</b>.';
    } elseif ($usuario === '') {
        $error = 'Debe indicar el <b>Usuario del Registro</b>.';
    } elseif (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){4}$/', strtoupper($serial)) !== 1) {
        $error = 'La <b>Llave Serial</b> no tiene el formato válido (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX).';
    } elseif (!licencia_validar_serial($nombre, $usuario, $serial)) {
        $error = 'La <b>Llave Serial</b> no corresponde a los datos de registro introducidos.<br>Verifique Nombre y Usuario, o solicite una llave válida.';
    } else {
        $guardado = licencia_guardar($nombre, $usuario, $serial);
        if ($guardado !== false) {
            $guardado_ok  = true;
            $guardado_tipo   = isset($guardado['info']['nombre']) ? $guardado['info']['nombre'] : 'Permanente';
            $guardado_meses  = isset($guardado['info']['meses']) ? $guardado['info']['meses'] : null;
            $guardado_hasta  = ($guardado_meses !== null) ? licencia_texto_vencimiento($guardado, 'Sin vencimiento (Permanente)') : 'Sin vencimiento (Permanente)';
        } else {
            $error = 'No se pudo guardar la licencia en este equipo.<br>Compruebe que la aplicación tiene permisos de escritura.';
        }
    }
}

// Feedback de éxito con SweetAlert y redirección al login.
if ($guardado_ok) {
    // Al instalarse/reinstalarse la licencia se cierra SIEMPRE la sesión
    // del usuario que esté logueado (si hubiera alguna en este equipo).
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params_lic_inst = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params_lic_inst["path"],
                $params_lic_inst["domain"],
                $params_lic_inst["secure"],
                $params_lic_inst["httponly"]
            );
        }
        @session_destroy();
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registro de Licencia · Completado</title>
        <link rel="icon" type="image/x-icon" href="../images/favicons/nominas.ico">
        <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
        <script src="js/sweetalert211.js"></script>
    </head>
    <body>
    <script>
    Swal.fire({
        icon: 'success',
        title: '<i class="fas fa-check-circle" style="color:#4ade80"></i> Registro completado',
        html: 'El sistema ha sido <b>registrado correctamente</b>.<br>' +
              'Tipo de licencia: <b><?php echo $guardado_tipo; ?></b>.<br>' +
              'Vence: <b><?php echo $guardado_hasta; ?></b>.<br>' +
              'Redirigiendo al inicio de sesión&hellip;',
        confirmButtonText: '<i class="fas fa-arrow-right-to-bracket"></i> Ir al Login',
        allowOutsideClick: false,
        background: '#1e1e2f',
        color: '#ffffff',
        confirmButtonColor: '#3b82f6'
    }).then(function (result) {
        window.location.href = 'login.php' + (result.isConfirmed ? '' : '');
    });
    setTimeout(function () { window.location.href = 'login.php'; }, 6000);
    </script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>SISGESNOM· Registro de Licencia</title>
    <link rel="icon" type="image/x-icon" href="../images/favicons/nominas.ico">
    <link rel="stylesheet" href="css/font-awesome6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <script src="js/sweetalert211.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: linear-gradient(135deg, #0A0F1A 0%, #0C111D 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            padding:1.25rem;
        }
        body::before {
            content:"";
            position:fixed; inset:0;
            background-image:
                repeating-linear-gradient(45deg, transparent, transparent 1.875rem, rgba(59,130,246,0.08) 1.875rem, rgba(59,130,246,0.08) 3.75rem),
                repeating-linear-gradient(-45deg, transparent, transparent 2.1875rem, rgba(139,92,246,0.06) 2.1875rem, rgba(139,92,246,0.06) 4.375rem);
            pointer-events:none;
            z-index:0;
        }
        .lic-container {
            position:relative; z-index:1;
            width:100%;
            max-width:32rem;
        }
        .lic-card {
            background: rgba(15, 23, 42, 0.92);
            border: 0.0625rem solid rgba(59,130,246,0.25);
            border-radius:1.25rem;
            box-shadow: 0 1.25rem 3.125rem rgba(0,0,0,0.55);
            overflow:hidden;
            backdrop-filter: blur(0.625rem);
        }
        .titlebar-lic {
            display:flex; align-items:center; justify-content:space-between;
            padding:0.875rem 1.25rem;
            background: rgba(2,6,23,0.6);
            border-bottom: 0.0625rem solid rgba(59,130,246,0.2);
        }
        .titlebar-title { color:#f1f5f9; font-size:0.95rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; }
        .titlebar-title i { color:#fbbf24; }
        .logo-area {
            display:flex; align-items:center; justify-content:center; gap:1rem;
            padding:1.5rem 1.25rem 0.75rem;
        }
        .logo-area h1 { color:#ffffff; font-size:1.25rem; font-weight:700; letter-spacing:0.5px; }
        .logo-area .subtitle { color:#94a3b8; font-size:0.85rem; margin-top:0.25rem; }
        .badge-lic {
            display:inline-block; margin-top:0.5rem; padding:0.25rem 0.75rem;
            background: rgba(251,191,36,0.15); color:#fbbf24;
            border:0.0625rem solid rgba(251,191,36,0.4);
            border-radius:2rem; font-size:0.7rem; font-weight:600; letter-spacing:0.5px;
        }
        .lic-body { padding:1.25rem 1.5rem 1.5rem; }
        .input-group { margin-bottom:1rem; }
        .input-group label { display:block; color:#cbd5e1; font-size:0.82rem; margin-bottom:0.4rem; }
        .input-group label i { color:#60a5fa; margin-right:0.35rem; }
        .input-with-icon { position:relative; }
        .input-with-icon > i { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#64748b; font-size:0.9rem; }
        .input-with-icon input {
            width:100%;
            padding:0.875rem 1rem 0.875rem 2.75rem;
            background: rgba(0,0,0,0.4);
            border:0.0625rem solid rgba(59,130,246,0.25);
            border-radius:0.875rem;
            color:#f1f5f9;
            font-size:0.95rem;
            outline:none;
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        .input-with-icon input:focus {
            border-color:#3b82f6;
            box-shadow:0 0 0 0.1875rem rgba(59,130,246,0.2);
        }
        .input-with-icon input::placeholder { color:#64748b; }
        .input-with-icon input[type="file"] {
            padding:0.7rem 1rem 0.7rem 2.75rem;
            color:#cbd5e1;
            font-size:0.85rem;
            cursor:pointer;
        }
        .input-with-icon input[type="file"]::-webkit-file-upload-button {
            background: rgba(34,197,94,0.15);
            color:#4ade80;
            border:0.0625rem solid rgba(34,197,94,0.35);
            border-radius:0.5rem;
            padding:0.375rem 0.75rem;
            margin-right:0.75rem;
            cursor:pointer;
            font-weight:600;
        }
        .serial-input { text-transform:uppercase; letter-spacing:2px; font-family:'Consolas', monospace; padding-right:3rem; }
        .serial-check {
            position:absolute;
            right:1.125rem;
            top:50%;
            transform:translateY(-50%);
            font-size:1.25rem;
            pointer-events:none;
            display:none;
        }
        .serial-check.valid i { color:#22c55e; }
        .serial-check.invalid i { color:#ef4444; }
        .hint { margin-top:0.375rem; font-size:0.72rem; color:#64748b; }
        .btn-registrar {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:1;
            width:auto;
            padding:0.9rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border:none;
            border-radius:0.875rem;
            color:#ffffff;
            font-size:1rem;
            font-weight:600;
            cursor:pointer;
            transition:transform 0.15s, box-shadow 0.2s;
        }
        .btn-registrar:hover { transform:translateY(-2px); box-shadow:0 0.625rem 1.25rem rgba(59,130,246,0.35); }
        .btn-registrar:active { transform:translateY(0); }
        .btn-home {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:1;
            width:auto;
            margin-top:0;
            padding:0.75rem;
            background: rgba(148,163,184,0.1);
            border:0.0625rem solid rgba(148,163,184,0.25);
            border-radius:0.875rem;
            color:#cbd5e1;
            font-size:0.9rem;
            cursor:pointer;
            transition:background 0.2s;
        }
        .btn-home:hover { background: rgba(148,163,184,0.2); }
        .lic-actions { display:flex; gap:0.7rem; margin-top:0.7rem; }
        .lic-divider {
            display:flex; align-items:center; justify-content:center;
            margin:1.1rem 0 1rem;
        }
        .lic-divider span {
            display:flex; align-items:center; gap:0.5rem;
            color:#64748b; font-size:0.8rem;
            background: rgba(148,163,184,0.08);
            padding:0.4rem 1rem;
            border-radius:2rem;
            border:0.0625rem solid rgba(148,163,184,0.2);
        }
        .lic-divider b { color:#cbd5e1; }
        .lic-footer {
            padding:0.75rem 1.5rem 1rem;
            text-align:center;
            color:#475569;
            font-size:0.72rem;
        }
        .lic-footer b { color:#64748b; }
        .fp-box {
            margin-bottom:1rem;
            padding:0.9rem 1rem 0.8rem;
            background: rgba(0,0,0,0.3);
            border:0.0625rem solid rgba(148,163,184,0.25);
            border-radius:0.875rem;
        }
        .fp-label { color:#94a3b8; font-size:0.78rem; margin-bottom:0.55rem; }
        .fp-label b { color:#cbd5e1; }
        .fp-value {
            font-family:'Consolas', monospace;
            letter-spacing:1px;
            color:#fbbf24;
            font-size:1.05rem;
            font-weight:600;
            text-align:center;
            user-select:all;
        }
        .fp-box .hint { margin-top:0.5rem; text-align:center; }
        .fp-copiar { cursor:pointer; opacity:0.7; font-size:0.8rem; color:#94a3b8; margin-left:0.5rem; transition:opacity .15s ease, color .15s ease; }
        .fp-copiar:hover, .fp-copiar:focus { opacity:1; color:#60a5fa; outline:none; }
    </style>
</head>
<body>
    <div class="lic-container">
        <div class="lic-card">
            <div class="titlebar-lic">
                <div class="titlebar-title">
                    <i class="fas fa-key"></i> Registro de Licencia
                </div>
                <div class="titlebar-title" style="font-size:0.8rem; color:#64748b;">
                    <i class="fas fa-shield-halved"></i> Activación única
                </div>
            </div>

            <div class="logo-area">
                <div style="text-align:center;">
                    <h1>SISGESNOM®</h1>
                    <div class="subtitle">Sistema de Gestión de Nóminas y Trabajadores</div>
                    <?php if ($lic_guardada !== null && licencia_vencida($lic_guardada)): ?>
                        <div class="badge-lic" style="background:rgba(248,113,113,0.15); color:#f87171; border-color:rgba(248,113,113,0.4);">
                            <i class="fas fa-triangle-exclamation"></i> LICENCIA VENCIDA &mdash; INGRESE UNA NUEVA
                        </div>
                    <?php else: ?>
                        <div class="badge-lic"><i class="fas fa-lock-open"></i> PENDIENTE DE REGISTRO</div>
                    <?php endif; ?>
                    <div class="fp-box" style="margin-top:0.85rem;">
                        <div class="fp-value">
                            <span style="color:#94a3b8; font-family:'Segoe UI',Arial,sans-serif; font-size:0.8rem; font-weight:600; letter-spacing:0;">Código Equipo:&nbsp;</span><?php echo licencia_fingerprint_equipo(); ?>
                            <i class="fas fa-copy fp-copiar" role="button" tabindex="0" title="Copiar huella del PC al portapapeles" aria-label="Copiar huella del PC al portapapeles" data-fp-titulo="Copiar huella del PC al portapapeles" data-fp-copiar="<?php echo htmlspecialchars(licencia_fingerprint_equipo()); ?>" onclick="copiarHuella(this)"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lic-body">
                <form method="POST" action="" id="licForm" autocomplete="off">
                    <div class="input-group">
                        <label><i class="fas fa-building"></i> Nombre de Registro</label>
                        <div class="input-with-icon">
                            <i class="fas fa-building"></i>
                            <input type="text" name="nombre" id="nombre" placeholder="Nombre de la empresa o instalación"
                                   value="<?php echo htmlspecialchars($nombre); ?>" maxlength="120" autofocus>
                        </div>
                    </div>

                    <div class="input-group">
                        <label><i class="fas fa-user"></i> Usuario del Registro</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="usuario" id="usuario" placeholder="Usuario o administrador responsable"
                                   value="<?php echo htmlspecialchars($usuario); ?>" maxlength="80">
                        </div>
                    </div>

                    <div class="input-group">
                        <label><i class="fas fa-hashtag"></i> Llave Serial</label>
                        <div class="input-with-icon">
                            <i class="fas fa-key"></i>
                            <input type="text" name="serial" id="serial" class="serial-input" maxlength="29"
                                   placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"
                                   value="<?php echo htmlspecialchars($serial); ?>">
                            <span id="serialCheck" class="serial-check"></span>
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Formato: 25 caracteres en 5 grupos de 5, separados por guiones.</div>
                    </div>

                    <div class="lic-actions">
                        <button type="submit" class="btn-registrar" id="btnRegistrar">
                            <i class="fas fa-key"></i> Activar Licencia
                        </button>
                        <button type="button" class="btn-home" id="btnHome">
                            <i class="fas fa-arrow-left"></i> Regresar al Inicio
                        </button>
                    </div>
                </form>

                <div class="lic-divider"><span><i class="fas fa-arrow-right"></i> o importe desde un archivo <b>.lic</b> <i class="fas fa-arrow-left"></i></span></div>

                <form method="POST" action="" id="licImport" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="examinar" value="1">
                    <div class="input-group">
                        <label><i class="fas fa-file-import"></i> Archivo de Licencia (.lic)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-file-shield"></i>
                            <input type="file" name="archivo_lic" id="archivo_lic" accept=".lic"
                                   placeholder="Seleccione el archivo .lic" onchange="this.form.submit()">
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Elija un archivo <b>.lic</b>: se verificará automáticamente y se rellenarán los campos con los datos de la licencia.</div>
                    </div>
                </form>
            </div>

            <div class="lic-footer">
                Este programa se distribuye bajo registro de licencia.<br>
                Los datos introducidos se almacenan <b>cifrados</b> y de forma <b>única</b> en este equipo.
            </div>
        </div>
    </div>

    <?php if ($error !== ''): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'error',
            title: '<i class="fas fa-exclamation-triangle" style="color:#f87171"></i> Registro no válido',
            html: '<?php echo $error; ?>',
            confirmButtonText: '<i class="fas fa-check"></i> Entendido',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6'
        });
    });
    </script>
    <?php elseif ($mensaje_diagnostico !== ''): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-circle-exclamation" style="color:#fbbf24"></i> Licencia no válida',
            html: '<?php echo $mensaje_diagnostico; ?>',
            confirmButtonText: '<i class="fas fa-key"></i> Registrar nueva licencia',
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6'
        });
    });
    </script>
    <?php elseif ($examinar_ok): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'success',
            title: '<i class="fas fa-file-circle-check" style="color:#4ade80"></i> Archivo de licencia v&aacute;lido',
            html: '<div style="text-align:left; font-size:0.9rem;">' +
                  'Licencia generada a favor de: <b><?php echo htmlspecialchars($nombre); ?></b><br>' +
                  'Tipo: <b><?php echo htmlspecialchars($examinar_tipo); ?></b><br>' +
                  'Per&iacute;odo de Validez: <b><?php echo htmlspecialchars($examinar_periodo); ?></b><br>' +
                  'Vence: <b><?php echo htmlspecialchars($examinar_hasta); ?></b><br>' +
                  '<?php echo $examinar_nota; ?>' +
                  '<hr style="border-color:rgba(148,163,184,0.2); margin:0.6rem 0;">' +
                  'Revise los datos y presione <b>Activar Licencia</b> para completar el registro.</div>',
            confirmButtonText: '<i class="fas fa-key"></i> Activar Licencia',
            allowOutsideClick: false,
            background: '#1e1e2f',
            color: '#ffffff',
            confirmButtonColor: '#3b82f6'
        }).then(function () {
            var btn = document.getElementById('btnRegistrar');
            if (btn) { btn.focus(); }
        });
    });
    </script>
    <?php endif; ?>

    <script>
    function copiarHuella(icono) {
        var texto = icono.getAttribute('data-fp-copiar') || '';
        if (!texto) return;
        var restaurar = icono.getAttribute('data-fp-titulo') || 'Copiar huella del PC al portapapeles';
        var confirmar = function () {
            icono.className = 'fas fa-check fp-copiar';
            icono.setAttribute('title', 'Copiado al portapapeles');
            setTimeout(function () {
                icono.className = 'fas fa-copy fp-copiar';
                icono.setAttribute('title', restaurar);
            }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(confirmar).catch(function () { copiarHuellaFallback(texto); confirmar(); });
        } else {
            copiarHuellaFallback(texto);
            confirmar();
        }
    }

    function copiarHuellaFallback(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var serial = document.getElementById('serial');
        var nombreInput = document.getElementById('nombre');
        var usuarioInput = document.getElementById('usuario');
        var serialCheck = document.getElementById('serialCheck');

        function licEstadoSerial(nombre, usuario, llave) {
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'validar_serial=1&nombre=' + encodeURIComponent(nombre) +
                      '&usuario=' + encodeURIComponent(usuario) +
                      '&serial=' + encodeURIComponent(llave)
            }).then(function (r) { return r.json(); });
        }

        function licMostrarEstado(nombre, usuario, llave) {
            var limpia = llave.replace(/[^A-Z0-9]/g, '');
            serialCheck.className = 'serial-check';
            serialCheck.innerHTML = '';
            serialCheck.style.display = 'none';
            if (limpia.length !== 25) { return; }
            if (nombre === '' || usuario === '') {
                serialCheck.innerHTML = '<i class="fas fa-circle-question" title="Complete Nombre y Usuario para validar"></i>';
                serialCheck.style.display = 'inline-block';
                return;
            }
            serialCheck.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            serialCheck.style.display = 'inline-block';
            licEstadoSerial(nombre, usuario, llave).then(function (j) {
                if (j && j.ok === true) {
                    serialCheck.className = 'serial-check valid';
                    serialCheck.innerHTML = '<i class="fas fa-check-circle"></i>';
                } else {
                    serialCheck.className = 'serial-check invalid';
                    serialCheck.innerHTML = '<i class="fas fa-times-circle"></i>';
                }
                serialCheck.style.display = 'inline-block';
            }).catch(function () {
                serialCheck.className = 'serial-check invalid';
                serialCheck.innerHTML = '<i class="fas fa-times-circle"></i>';
                serialCheck.style.display = 'inline-block';
            });
        }

        function licVerificar() {
            licMostrarEstado(nombreInput.value.trim(), usuarioInput.value.trim(), serial.value);
        }

        // Autoconvertir a mayúsculas mientras se escribe.
        serial.addEventListener('input', function () {
            serial.value = serial.value.toUpperCase();
            licVerificar();
        });

        // Polegar: rellenar guiones automáticamente (un grupo por foco).
        serial.addEventListener('keyup', function () {
            var val = serial.value.replace(/[^A-Z0-9]/g, '').slice(0, 25);
            var grupos = [];
            for (var i = 0; i < val.length; i += 5) {
                grupos.push(val.substr(i, 5));
            }
            serial.value = grupos.join('-');
            licVerificar();
        });

        nombreInput.addEventListener('input', licVerificar);
        usuarioInput.addEventListener('input', licVerificar);

        // Al cargar (p. ej. tras examinar un .lic) muestra ya el estado del serial.
        licVerificar();

        document.getElementById('btnHome').addEventListener('click', function () {
            window.location.href = '../index.php';
        });

        var form = document.getElementById('licForm');
        form.addEventListener('submit', function (e) {
            var v = serial.value.replace(/[^A-Z0-9]/g, '');
            if (v.length !== 25) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Llave incompleta',
                    html: 'La llave serial debe contener <b>25 caracteres</b> en 5 grupos de 5.',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    background: '#1e1e2f',
                    color: '#ffffff',
                    confirmButtonColor: '#3b82f6'
                });
                serial.focus();
                return;
            }
        });

        var formImport = document.getElementById('licImport');
        formImport.addEventListener('submit', function (e) {
            var f = document.getElementById('archivo_lic');
            if (!f.files || f.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Archivo no seleccionado',
                    html: 'Seleccione primero un archivo de licencia <b>.lic</b>.',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    background: '#1e1e2f',
                    color: '#ffffff',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            var nombre = f.files[0].name;
            if (!nombre.toLowerCase().endsWith('.lic')) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Extensión no válida',
                    html: 'El archivo debe tener extensión <b>.lic</b>.',
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    background: '#1e1e2f',
                    color: '#ffffff',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
        });
    });
    </script>
</body>
</html>