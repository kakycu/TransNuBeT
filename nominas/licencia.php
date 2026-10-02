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
// Se carga tambien en el render para exponer el destinatario de la solicitud
// al boton que abre el cliente de correo local (mailto:).
require_once __DIR__ . '/includes/solicitud_licencia_correo.php';

/**
 * Verifica el texto pegado/escrito de una licencia contra este equipo.
 * No instala nada: solo informa si el texto es una licencia vigente para esta PC.
 * @param string $texto Texto Base64 de la licencia.
 * @return array{ok:bool, mensaje:string, datos:?array, huella_destino:string, huella_equipo:string}
 */
function licencia_texto_verificar($texto) {
    $resp = array(
        'ok'             => false,
        'mensaje'        => 'Pegue el texto de la licencia para verificarlo.',
        'datos'          => null,
        'huella_destino' => '',
        'huella_equipo'  => '',
    );
    $texto = trim((string)$texto);
    if ($texto === '') {
        return $resp;
    }
    if (strlen($texto) > 8192) {
        $resp['mensaje'] = 'El texto es demasiado largo para ser una licencia.';
        return $resp;
    }
    $datos = licencia_leer_texto_lic($texto);
    if ($datos === null) {
        $resp['mensaje'] = 'El texto no corresponde a una licencia valida. Verifique que se copio completo.';
        return $resp;
    }
    $fp_actual = licencia_fingerprint_machine();
    if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $fp_actual)) {
        $resp['mensaje']        = 'Esta licencia fue emitida para otro equipo. Solicite al proveedor una licencia para esta PC.';
        $resp['huella_destino'] = licencia_formatear_fingerprint($datos['huella']);
        $resp['huella_equipo']  = licencia_formatear_fingerprint($fp_actual);
        $resp['datos']          = $datos;
        return $resp;
    }
    $meses = isset($datos['info']['meses']) ? $datos['info']['meses'] : null;
    $resp['ok']      = true;
    $resp['mensaje'] = 'Licencia valida para este equipo.';
    $resp['datos']   = array(
        'registro'  => $datos['registro'],
        'usuario'   => $datos['usuario'],
        'serial'    => licencia_formatear_serial($datos['serial']),
        'termino'   => isset($datos['info']['nombre']) ? $datos['info']['nombre'] : 'Permanente',
        'periodo'   => ($meses !== null) ? ((int)$meses === 1 ? '1 mes' : (int)$meses . ' meses') : 'Permanente',
        'vence'     => ($meses !== null) ? licencia_texto_vencimiento($datos, 'Sin vencimiento (Permanente)') : 'Sin vencimiento (Permanente)',
        'exclusiva' => ($datos['huella'] !== ''),
    );
    return $resp;
}

// Endpoint interno para validar en vivo el texto de una licencia (pegado o escrito).
if (isset($_REQUEST['validar_texto']) && (string)$_REQUEST['validar_texto'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $resp = licencia_texto_verificar(($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['licencia_texto'])) ? $_POST['licencia_texto'] : '');
    $json = array('ok' => $resp['ok'], 'mensaje' => $resp['mensaje']);
    if ($resp['ok'] && is_array($resp['datos'])) {
        $json = array_merge($json, $resp['datos']);
    } elseif ($resp['huella_destino'] !== '') {
        $json['huella_destino'] = $resp['huella_destino'];
        $json['huella_equipo']  = $resp['huella_equipo'];
    }
    echo json_encode($json);
    exit;
}

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

// Endpoint interno: envia la solicitud de licencia por correo electronico.
if (isset($_REQUEST['solicitar_licencia']) && (string)$_REQUEST['solicitar_licencia'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/includes/solicitud_licencia_correo.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('ok' => false, 'mensaje' => 'Metodo no permitido.'));
        exit;
    }

    $entrada = array(
        'nombre'    => isset($_POST['nombre'])    ? $_POST['nombre']    : '',
        'apellidos' => isset($_POST['apellidos']) ? $_POST['apellidos'] : '',
        'ci'        => isset($_POST['ci'])        ? $_POST['ci']        : '',
        'email'     => isset($_POST['email'])     ? $_POST['email']     : '',
        'generica'  => isset($_POST['generica'])  ? $_POST['generica']  : '0',
        'usuario'   => isset($_POST['usuario'])   ? $_POST['usuario']   : '',
        'entidad'   => isset($_POST['entidad'])   ? $_POST['entidad']   : '',
        'periodo'   => isset($_POST['periodo'])   ? $_POST['periodo']   : '',
    );

    $validacion = solicitud_licencia_validar($entrada);
    if (!$validacion['ok']) {
        echo json_encode(array(
            'ok'       => false,
            'mensaje'  => 'Revise los campos marcados.',
            'errores'  => $validacion['errores'],
        ));
        exit;
    }

    $envio = solicitud_licencia_enviar($validacion['datos']);
    if ($envio['success']) {
        echo json_encode(array(
            'ok'      => true,
            'mensaje' => 'Su solicitud fue enviada. Le responderemos con la licencia generada.',
        ));
        exit;
    }

    $mensajes = array(
        'mail_not_configured' => 'El envio de correo no esta configurado en el sistema. Contacte al administrador.',
        'sin_email'           => 'No se pudo determinar el destinatario de la solicitud.',
    );
    $detalle = isset($mensajes[$envio['error']]) ? $mensajes[$envio['error']] : ('No se pudo enviar la solicitud: ' . $envio['error']);

    echo json_encode(array('ok' => false, 'mensaje' => $detalle));
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
    // ***** Examinar texto de licencia pegado/escrito: rellena los campos con sus datos *****
    if (isset($_POST['examinar_texto']) && $_POST['examinar_texto'] === '1') {
        $texto_lic = licencia_texto_verificar(isset($_POST['licencia_texto']) ? $_POST['licencia_texto'] : '');
        if (!$texto_lic['ok']) {
            $error = $texto_lic['mensaje'];
            if ($texto_lic['huella_destino'] !== '') {
                $error = $texto_lic['mensaje']
                    . '<br>Huella destino&nbsp;: <b>' . htmlspecialchars($texto_lic['huella_destino']) . '</b><br>'
                    . 'Este equipo&nbsp;&nbsp;&nbsp;&nbsp;: <b>' . htmlspecialchars($texto_lic['huella_equipo']) . '</b>';
            }
        } else {
            $nombre  = $texto_lic['datos']['registro'];
            $usuario = $texto_lic['datos']['usuario'];
            $serial  = $texto_lic['datos']['serial'];
            $examinar_ok      = true;
            $examinar_tipo    = $texto_lic['datos']['termino'];
            $examinar_periodo = $texto_lic['datos']['periodo'];
            $examinar_nota    = $texto_lic['datos']['exclusiva'] ? '<br><b>Esta licencia es exclusiva de este equipo.</b>' : '';
            $examinar_hasta   = $texto_lic['datos']['vence'];
        }
    // ***** Examinar archivo .lic: rellena los campos con los datos de la licencia *****
    } elseif (isset($_POST['examinar']) && $_POST['examinar'] === '1') {
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
<html lang="es" data-theme="win11" class="dark">
<head>
    <?php
    // Esta pantalla siempre muestra el tema Win11 Dark, aunque el usuario tenga otro seleccionado.
    if (!defined('TN_THEME_FORZADO')) { define('TN_THEME_FORZADO', 'win11'); }
    include 'includes/theme_early.php';
    ?>
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
            background: var(--bg, #1c1c1c);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            padding:1.25rem;
        }
        body::before { content:none; }
        .lic-container {
            position:relative; z-index:1;
            width:100%;
            max-width:56rem;
        }
        .lic-card {
            background: var(--card, #262626);
            border: 0.0625rem solid #3d3d3d;
            border-radius:0.75rem;
            box-shadow: var(--shadow, 0 0.25rem 0.75rem rgba(0,0,0,0.25));
            overflow:hidden;
        }
        .titlebar-lic {
            display:flex; align-items:center; justify-content:space-between;
            gap:0.75rem; flex-wrap:wrap;
            padding:0 0 0.5rem;
            border-bottom: 0.0625rem solid #3d3d3d;
        }
        .titlebar-title { color:var(--txt,#ffffff); font-size:0.95rem; font-weight:600; display:flex; align-items:center; gap:0.5rem; }
        .titlebar-title i { color:var(--accent,#0078d4); }
        .lic-layout {
            display:flex; align-items:stretch;
            background: var(--panel, #202020);
            border-bottom: 0.0625rem solid #3d3d3d;
        }
        .lic-rail {
            flex:0 0 9rem; display:flex; align-items:center; justify-content:center;
            padding:0.7rem; border-right:0.0625rem solid #3d3d3d;
        }
        .lic-rail .lic-logo-img { max-width:100%; max-height:14.75rem; width:auto; height:auto; display:block; }
        .lic-head {
            flex:1 1 auto; min-width:0;
            display:flex; flex-direction:column; justify-content:center;
            padding:0.6rem 1.25rem;
        }
        @media (max-width:44rem) {
            .lic-layout { flex-direction:column; }
            .lic-rail { flex:none; border-right:none; border-bottom:0.0625rem solid #3d3d3d; padding:0.75rem; }
            .lic-rail .lic-logo-img { max-height:4.5rem; }
            .lic-head { padding:0.75rem 1rem; }
        }
        .lic-logo-txt { min-width:0; }
        .logo-area { padding:0.75rem 0 0; }
        .logo-area h1 { color:var(--txt,#ffffff); font-size:1.25rem; font-weight:700; letter-spacing:0.5px; }
        .logo-area .subtitle { color:var(--muted,#9d9d9d); font-size:0.85rem; font-weight:400; letter-spacing:0; }
        .lic-col .fp-box { margin:0.85rem 0 0; }
        .lic-col .fp-box .fp-label { color:var(--muted,#9d9d9d); font-family:'Segoe UI',Arial,sans-serif; font-size:0.8rem; font-weight:600; letter-spacing:0; }
        .badge-lic {
            display:inline-block; margin-top:0.5rem; padding:0.25rem 0.75rem;
            background: var(--accent-bg, rgba(0,120,212,0.18)); color: var(--accent-light, #4cc2ff);
            border:0.0625rem solid var(--accent, #0078d4);
            border-radius:2rem; font-size:0.7rem; font-weight:600; letter-spacing:0.5px;
        }
        .lic-body { padding:0.65rem 1.25rem 1.75rem; }
        .lic-panel-manual { margin-bottom:0.7rem; }
        .lic-manual-grid { display:block; }
        .lic-panel-manual .fp-box { margin:0.6rem 0 0; padding:0.55rem 0.75rem 0.5rem; }
        .lic-acciones {
            display:flex; justify-content:flex-start; align-items:center; gap:0.7rem;
            margin:1rem 0 0 0.25rem; padding:1.1rem 0 0 0;
            border-top:0.0625rem solid #3d3d3d;
        }
        .lic-acciones .btn-registrar, .lic-acciones .btn-home { flex:0 0 auto; min-width:12rem; }
        @media (max-width:34rem) { .lic-acciones { flex-direction:column; } }
        .lic-cols { display:grid; grid-template-columns:1fr 1fr; gap:0 1.25rem; align-items:stretch; }
        .lic-col { min-width:0; display:flex; flex-direction:column; }
        .lic-col > .lic-panel { flex:1 1 auto; }
        .lic-panel {
            padding:0.6rem 0.75rem 0.2rem;
            border:0.0625rem solid #3d3d3d;
            border-radius:0.5rem;
            background:var(--panel, #202020);
        }
        .lic-panel + .lic-panel { margin-top:0.75rem; }
        .lic-col-titulo {
            display:flex; align-items:center; gap:0.4rem;
            color:var(--txt,#ffffff); font-size:0.8rem; font-weight:600;
            margin-bottom:0.7rem; padding-bottom:0.45rem;
            border-bottom:0.0625rem solid #3d3d3d;
        }
        .lic-col-titulo i { color:var(--accent,#0078d4); }
        .lic-col-titulo b { color:var(--accent-light,#4cc2ff); }
        .lic-col .input-group:last-child { margin-bottom:0.5rem; }
        @media (max-width:56rem) {
            .lic-cols { grid-template-columns:1fr; gap:0.75rem; }
        }
        .input-group { margin-bottom:0.65rem; }
        .input-fila { display:grid; grid-template-columns:1fr 1fr; gap:0 0.875rem; align-items:start; }
        .input-fila.full { grid-template-columns:1fr; }
        @media (max-width:34rem) { .input-fila { grid-template-columns:1fr; } }
        .input-group label { display:block; color:#e5e5e5; font-size:0.82rem; margin-bottom:0.4rem; }
        .input-group label i { color:var(--accent,#0078d4); margin-right:0.35rem; }
        .input-with-icon { position:relative; }
        .input-with-icon > i { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--muted,#9d9d9d); font-size:0.9rem; }
        .input-with-icon input {
            width:100%;
            padding:0.75rem 1rem 0.75rem 2.75rem;
            background: #2b2b2b;
            border:0.0625rem solid #3d3d3d;
            border-radius:0.25rem;
            color:var(--txt,#ffffff);
            font-size:0.95rem;
            outline:none;
            transition:border-color 0.15s, background 0.15s;
        }
        .input-with-icon input:hover { border-color:#4d4d4d; }
        .input-with-icon input:focus {
            border-color:var(--accent,#0078d4);
            background:#2f2f2f;
        }
        .input-with-icon input::placeholder { color:var(--faint,#6b6b6b); }
        .input-with-icon input[type="file"] {
            padding:0.6rem 1rem 0.6rem 2.75rem;
            color:#e5e5e5;
            font-size:0.85rem;
            cursor:pointer;
        }
        .input-with-icon input[type="file"]::-webkit-file-upload-button {
            background: #3d3d3d;
            color:#ffffff;
            border:0.0625rem solid #4d4d4d;
            border-radius:0.1875rem;
            padding:0.3rem 0.7rem;
            margin-right:0.7rem;
            cursor:pointer;
            font-weight:600;
        }
        .input-with-icon input[type="file"]::-webkit-file-upload-button:hover { background:#4d4d4d; }
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
        .hint { margin-top:0.375rem; font-size:0.72rem; color:var(--muted,#9d9d9d); }
        .lic-texto {
            width:100%; resize:vertical; min-height:4.5rem;
            padding:0.6rem 0.7rem; border-radius:0.25rem;
            border:0.0625rem solid #3d3d3d; background:#2b2b2b; color:var(--txt,#ffffff);
            font-family:'Consolas','Courier New',monospace; font-size:0.75rem; line-height:1.5;
        }
        .lic-texto:hover { border-color:#4d4d4d; }
        .lic-texto:focus { outline:none; border-color:var(--accent,#0078d4); background:#2f2f2f; }
        .lic-estado {
            display:flex; align-items:flex-start; gap:0.45rem; margin-top:0.5rem;
            padding:0.5rem 0.6rem; font-size:0.75rem; line-height:1.45;
            border:1px solid transparent; border-radius:0.25rem;
        }
        .lic-estado i { margin-top:0.1rem; flex-shrink:0; }
        .lic-estado-espera { color:#d4d4d4; background:#2b2b2b; border-color:#3d3d3d; }
        .lic-estado-espera i { color:var(--muted,#9d9d9d); }
        .lic-estado-ok { color:#bbf7d0; background:rgba(34,197,94,0.14); border-color:rgba(34,197,94,0.35); }
        .lic-estado-ok i { color:#22c55e; }
        .lic-estado-error { color:#fecaca; background:rgba(239,68,68,0.14); border-color:rgba(239,68,68,0.35); }
        .lic-estado-error i { color:#ef4444; }
        .lic-estado-datos { display:block; margin-top:0.25rem; opacity:0.9; }
        .btn-registrar {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:1;
            width:auto;
            padding:0.75rem;
            background: var(--accent, #0078d4);
            border:0.0625rem solid var(--accent, #0078d4);
            border-radius:0.25rem;
            color:#ffffff;
            font-size:0.95rem;
            font-weight:600;
            cursor:pointer;
            transition:background 0.15s;
        }
        .btn-registrar:hover { background: color-mix(in srgb, var(--accent,#0078d4) 85%, #000); }
        .btn-registrar:active { background: color-mix(in srgb, var(--accent,#0078d4) 75%, #000); }
        .btn-home {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:1;
            width:auto;
            margin-top:0;
            padding:0.75rem;
            background: transparent;
            border:0.0625rem solid #4d4d4d;
            border-radius:0.25rem;
            color:#ffffff;
            font-size:0.9rem;
            cursor:pointer;
            transition:background 0.15s, border-color 0.15s;
        }
        .btn-home:hover { background:#2d2d2d; border-color:var(--accent,#0078d4); color:var(--accent-light,#4cc2ff); }
        .lic-actions { display:flex; gap:0.7rem; margin-top:0.7rem; }
        .lic-divider {
            display:flex; align-items:center; justify-content:center;
            margin:1.1rem 0 1rem;
        }
        .lic-divider span {
            display:flex; align-items:center; gap:0.5rem;
            color:var(--muted,#9d9d9d); font-size:0.8rem;
            background: #2b2b2b;
            padding:0.4rem 1rem;
            border-radius:2rem;
            border:0.0625rem solid #3d3d3d;
        }
        .lic-divider b { color:#e5e5e5; }
        .lic-footer {
            padding:0.75rem 1.5rem 1rem;
            text-align:center;
            color:var(--faint,#6b6b6b);
            font-size:0.72rem;
        }
        .lic-footer b { color:var(--muted,#9d9d9d); }
        /* Modal de solicitud por correo */
        .btn-solicitar {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:0 0 auto; min-width:12rem;
            padding:0.75rem;
            background: rgba(0,120,212,0.12);
            border:0.0625rem solid var(--accent, #0078d4);
            border-radius:0.25rem;
            color: var(--accent-light, #4cc2ff);
            font-size:0.9rem;
            cursor:pointer;
            transition:background 0.15s;
        }
        .btn-solicitar:hover { background: rgba(0,120,212,0.24); }
        .lic-modal-overlay {
            position:fixed; inset:0; z-index:1200;
            display:flex; align-items:center; justify-content:center;
            padding:1.25rem;
            background: rgba(0,0,0,0.72);
        }
        .lic-modal-overlay[hidden] { display:none; }
        /* Swal2 usa z-index 1060: sin esto sus alertas quedan tapadas detras del overlay (1200). */
        body > .swal2-container { z-index:1300; }
        /* Boton que abre el cliente de correo local (mailto:) */
        .btn-correo {
            display:inline-flex; align-items:center; justify-content:center; gap:0.5rem;
            flex:0 0 auto; min-width:11rem;
            padding:0.75rem;
            background: rgba(34,197,94,0.12);
            border:0.0625rem solid #22c55e;
            border-radius:0.25rem;
            color:#4ade80;
            font-size:0.9rem;
            font-family:inherit;
            cursor:pointer;
            transition:background 0.15s;
        }
        .btn-correo:hover { background: rgba(34,197,94,0.24); }
        .lic-modal {
            width:100%; max-width:44rem; max-height:calc(100vh - 2.5rem);
            display:flex; flex-direction:column;
            background: var(--card, #262626);
            border:0.0625rem solid #3d3d3d;
            border-radius:0.75rem;
            box-shadow: var(--shadow, 0 0.25rem 0.75rem rgba(0,0,0,0.25));
            overflow:hidden;
        }
        .lic-modal-head {
            display:flex; align-items:center; justify-content:space-between; gap:0.75rem;
            padding:0.75rem 1rem;
            background: var(--panel, #202020);
            border-bottom:0.0625rem solid #3d3d3d;
        }
        .lic-modal-title { display:flex; align-items:center; gap:0.5rem; color:var(--txt,#ffffff); font-size:0.95rem; font-weight:600; }
        .lic-modal-title i { color:var(--accent,#0078d4); }
        .lic-modal-x {
            background:transparent; border:0.0625rem solid transparent; border-radius:0.25rem;
            color:var(--muted,#9d9d9d); font-size:1rem; padding:0.3rem 0.5rem; cursor:pointer;
        }
        .lic-modal-x:hover { background:#2d2d2d; color:#ffffff; }
        .lic-modal-body { padding:0.9rem 1rem; overflow-y:auto; }
        .lic-modal-intro { margin:0 0 0.75rem; font-size:0.8rem; line-height:1.55; color:#e5e5e5; }
        .lic-modal-intro b { color:var(--accent-light,#4cc2ff); }
        .lic-modal-fp {
            display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;
            padding:0.6rem 0.7rem; margin-bottom:0.85rem;
            background:#232323; border:0.0625rem solid #3d3d3d; border-radius:0.375rem;
        }
        .lic-modal-fp-label { font-size:0.78rem; color:var(--muted,#9d9d9d); }
        .lic-modal-fp-label i { color:var(--accent,#0078d4); margin-right:0.3rem; }
        .lic-modal-fp-value { font-family:'Consolas', monospace; letter-spacing:1px; font-size:0.8rem; color:#ffffff; font-weight:600; }
        .lic-modal-fp .copiar-valor { color:var(--muted,#9d9d9d); cursor:pointer; font-size:0.8rem; }
        .lic-modal-fp .copiar-valor:hover { color:var(--accent-light,#4cc2ff); }
        .lic-modal .input-group { margin-bottom:0.6rem; }
        .lic-modal .input-group label { display:block; color:#e5e5e5; font-size:0.8rem; margin-bottom:0.35rem; }
        .lic-modal .input-group label i { color:var(--accent,#0078d4); margin-right:0.3rem; }
        .lic-modal input[type="text"], .lic-modal input[type="email"], .lic-select {
            width:100%;
            padding:0.6rem 0.7rem;
            background:#2b2b2b;
            border:0.0625rem solid #3d3d3d;
            border-radius:0.25rem;
            color:var(--txt,#ffffff);
            font-size:0.9rem;
            font-family:inherit;
            outline:none;
        }
        .lic-modal input[type="text"]:hover, .lic-modal input[type="email"]:hover, .lic-select:hover { border-color:#4d4d4d; }
        .lic-modal input[type="text"]:focus, .lic-modal input[type="email"]:focus, .lic-select:focus { border-color:var(--accent,#0078d4); background:#2f2f2f; }
        .lic-modal input[type="text"].error, .lic-modal input[type="email"].error, .lic-select.error { border-color:#ef4444; }
        .sol-feedback {
            display:block; margin-top:0.3rem; min-height:0.85rem;
            font-size:0.72rem; line-height:1.25; color:var(--muted,#9d9d9d);
        }
        .sol-feedback:empty { display:none; }
        .sol-feedback.ok { color:#22c55e; }
        .sol-feedback.mal { color:#ef4444; }
        /* Correo + slider "Generica" en la misma linea */
        .input-linea { display:flex; align-items:center; gap:0.6rem; }
        .input-linea input[type="email"] { flex:1 1 auto; min-width:0; }
        .lic-modal .input-group label.lic-switch {
            display:inline-flex; align-items:center; gap:0.4rem;
            flex:0 0 auto; margin:0; cursor:pointer; user-select:none;
        }
        .lic-switch input { position:absolute; opacity:0; width:0; height:0; }
        .lic-switch-track {
            position:relative; display:inline-block; flex:0 0 auto;
            width:2.6rem; height:1.4rem; border-radius:1rem;
            background:#2b2b2b; border:0.0625rem solid #4d4d4d;
            transition:background 0.15s, border-color 0.15s;
        }
        .lic-switch .lic-switch-track::after {
            content:''; position:absolute; top:0.12rem; left:0.12rem;
            width:1.05rem; height:1.05rem; border-radius:50%;
            background:#9d9d9d; transition:transform 0.15s, background 0.15s;
        }
        .lic-switch input:checked + .lic-switch-track { background:rgba(34,197,94,0.28); border-color:#22c55e; }
        .lic-switch input:checked + .lic-switch-track::after { transform:translateX(1.2rem); background:#22c55e; }
        .lic-switch input:focus-visible + .lic-switch-track { outline:2px solid var(--accent,#0078d4); outline-offset:2px; }
        .lic-switch-txt { font-size:0.78rem; color:#e5e5e5; white-space:nowrap; }
        .lic-switch input:checked ~ .lic-switch-txt { color:#4ade80; font-weight:600; }
        /* Tooltip declarativo via data-tooltip. Se exige la clase .has-tip para no
           secuestrar el ::after de otros componentes (p.ej. el boton del slider).
           Oculto con display:none: un ::after con opacity:0 sigue ocupando espacio
           y provokeia barra de scroll horizontal dentro del modal. */
        .has-tip[data-tooltip] { position:relative; }
        .has-tip[data-tooltip]::after {
            content:attr(data-tooltip);
            position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
            width:max-content; max-width:15rem; padding:0.45rem 0.6rem;
            background:#1e1e2f; border:0.0625rem solid #3d3d3d; border-radius:0.3rem;
            color:#ffffff; font-size:0.72rem; line-height:1.35; text-align:left;
            white-space:normal; display:none;
            pointer-events:none; z-index:10;
        }
        .has-tip[data-tooltip]:hover::after, .has-tip[data-tooltip]:focus-visible::after { display:block; animation:tipIn 0.15s ease-out; }
        /* Ancla a la derecha para elementos pegados al borde derecho del modal. */
        .tip-der[data-tooltip]::after { left:auto; right:0; transform:none; }
        @keyframes tipIn { from { opacity:0; } to { opacity:1; } }
        .lic-modal-foot {
            display:flex; justify-content:flex-start; gap:0.7rem; flex-wrap:wrap;
            padding:0.75rem 1rem;
            background: var(--panel, #202020);
            border-top:0.0625rem solid #3d3d3d;
        }
        .lic-modal-foot .btn-registrar, .lic-modal-foot .btn-home { flex:0 0 auto; min-width:11rem; }
        .fp-box {
            margin-bottom:1rem;
            padding:0.75rem 0.875rem 0.7rem;
            background: #232323;
            border:0.0625rem solid #3d3d3d;
            border-radius:0.375rem;
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
        .copiar-valor { cursor:pointer; opacity:0.7; font-size:0.8rem; color:#94a3b8; transition:opacity .15s ease, color .15s ease; }
        .input-with-icon > i.copiar-valor { left:auto; width:auto; font-size:0.8rem; color:#94a3b8; }
        .copiar-valor:hover, .copiar-valor:focus { opacity:1; color:#60a5fa; outline:none; }
        .input-with-icon > i.copiar-campo { position:absolute; right:1rem; top:50%; transform:translateY(-50%); }
        .input-with-icon > i.copiar-serial { right:3rem; }
        .input-group:has(#nombre) input, .input-group:has(#usuario) input { padding-right:3rem; }
        .input-with-icon input.serial-input { padding-right:5.5rem; }
    </style>
</head>
<body>
    <div class="lic-container">
        <div class="lic-card">
            <div class="lic-layout">
            <div class="lic-rail">
                <img src="../images/favicon1.png" alt="SISGESNOM" class="lic-logo-img">
            </div>

            <div class="lic-head">
            <div class="titlebar-lic">
                <div class="titlebar-title">
                    <i class="fas fa-key"></i> Formulario de Registro / Solicitud de Licencia
                </div>
                <div class="titlebar-title" style="font-size:0.8rem; color:#9d9d9d;">
                    <i class="fas fa-shield-halved"></i> Activación única
                </div>
            </div>

            <div class="logo-area">
                <div class="lic-logo-txt">
                    <h1>SISGESNOM&reg; <span class="subtitle">- Sistema de Gesti&oacute;n de N&oacute;minas y Trabajadores</span></h1>
                    <?php if ($lic_guardada !== null && licencia_vencida($lic_guardada)): ?>
                        <div class="badge-lic" style="background:rgba(248,113,113,0.15); color:#f87171; border-color:rgba(248,113,113,0.4);">
                            <i class="fas fa-triangle-exclamation"></i> LICENCIA VENCIDA &mdash; INGRESE UNA NUEVA
                        </div>
                    <?php else: ?>
                        <div class="badge-lic"><i class="fas fa-lock-open"></i> PENDIENTE DE REGISTRO</div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
            </div>

            <div class="lic-body">
                <div class="lic-panel lic-panel-manual">
                <div class="lic-col-titulo"><i class="fas fa-key"></i> Registro manual de la licencia</div>
                <form method="POST" action="" id="licForm" autocomplete="off">
                    <div class="lic-manual-grid">
                    <div class="input-fila">
                    <div class="input-group">
                        <label><i class="fas fa-building"></i> Nombre de Registro</label>
                        <div class="input-with-icon">
                            <i class="fas fa-building"></i>
                            <input type="text" name="nombre" id="nombre" placeholder="Nombre de la empresa o instalación"
                                   value="<?php echo htmlspecialchars($nombre); ?>" maxlength="120" autofocus>
                            <?php if ($nombre !== ''): ?>
                            <i class="fas fa-copy copiar-valor copiar-campo" role="button" tabindex="0" title="Copiar el nombre de registro al portapapeles" aria-label="Copiar el nombre de registro al portapapeles" data-copiar-titulo="Copiar el nombre de registro al portapapeles" data-copiar-valor="<?php echo htmlspecialchars($nombre); ?>" onclick="copiarAlPortapapeles(this)"></i>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="input-group">
                        <label><i class="fas fa-user"></i> Usuario del Registro</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="usuario" id="usuario" placeholder="Usuario o administrador responsable"
                                   value="<?php echo htmlspecialchars($usuario); ?>" maxlength="80">
                            <?php if ($usuario !== ''): ?>
                            <i class="fas fa-copy copiar-valor copiar-campo" role="button" tabindex="0" title="Copiar el usuario del registro al portapapeles" aria-label="Copiar el usuario del registro al portapapeles" data-copiar-titulo="Copiar el usuario del registro al portapapeles" data-copiar-valor="<?php echo htmlspecialchars($usuario); ?>" onclick="copiarAlPortapapeles(this)"></i>
                            <?php endif; ?>
                        </div>
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
                            <?php if ($serial !== ''): ?>
                            <i class="fas fa-copy copiar-valor copiar-campo copiar-serial" role="button" tabindex="0" title="Copiar la llave serial al portapapeles" aria-label="Copiar la llave serial al portapapeles" data-copiar-titulo="Copiar la llave serial al portapapeles" data-copiar-valor="<?php echo htmlspecialchars(licencia_formatear_serial($serial)); ?>" onclick="copiarAlPortapapeles(this)"></i>
                            <?php endif; ?>
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Formato: 25 caracteres en 5 grupos de 5, separados por guiones.</div>
                    </div>

                    <div class="fp-box">
                        <div class="fp-value">
                            <span class="fp-label">C&oacute;digo Equipo:&nbsp;</span><?php echo licencia_fingerprint_equipo(); ?>
                            <i class="fas fa-copy fp-copiar" role="button" tabindex="0" title="Copiar huella del PC al portapapeles" aria-label="Copiar huella del PC al portapapeles" data-fp-titulo="Copiar huella del PC al portapapeles" data-fp-copiar="<?php echo htmlspecialchars(licencia_fingerprint_equipo()); ?>" onclick="copiarHuella(this)"></i>
                        </div>
                    </div>
                    </div>
                </form>
                </div>

                <div class="lic-cols">
                <div class="lic-col">
                <div class="lic-panel">
                <div class="lic-col-titulo"><i class="fas fa-file-import"></i> Importar desde un archivo <b>.lic</b></div>
                <form method="POST" action="" id="licImport" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="examinar" value="1">
                    <div class="input-group">
                        <label><i class="fas fa-file-shield"></i> Archivo de Licencia (.lic)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-folder-open"></i>
                            <input type="file" name="archivo_lic" id="archivo_lic" accept=".lic"
                                   placeholder="Seleccione el archivo .lic" onchange="this.form.submit()">
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Elija un archivo <b>.lic</b>: se verificará automáticamente y se rellenarán los campos con los datos de la licencia.</div>
                    </div>
                </form>
                </div>
                </div>

                <div class="lic-col">
                <div class="lic-panel">
                <div class="lic-col-titulo"><i class="fas fa-file-signature"></i> Pegar o escribir el texto</div>
                <form method="POST" action="" id="licTextoForm" autocomplete="off">
                    <input type="hidden" name="examinar_texto" value="1">
                    <div class="input-group">
                        <label><i class="fas fa-key"></i> Texto de la Licencia</label>
                        <textarea class="lic-texto" name="licencia_texto" id="licencia_texto" rows="3" spellcheck="false"
                                  oninput="licTextoValidar()"
                                  placeholder="Pegue o escriba aqui la licencia, por ejemplo:&#10;vRJjlNjzgMVgJMKilzYLsrJ8UXCsRJbNyMIipaBM6gfzzTWAbduUOctveeW0hD4..."><?php echo htmlspecialchars(isset($_POST['licencia_texto']) ? (string)$_POST['licencia_texto'] : ''); ?></textarea>
                        <div class="lic-estado lic-estado-espera" id="licTextoEstado">
                            <i class="fas fa-circle-info"></i>
                            <span id="licTextoEstadoMsg">Pegue el texto de la licencia para verificarlo.</span>
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Se verifica al instante; si es correcta se rellenan el nombre, el usuario y la llave serial.</div>
                    </div>
                    <div class="lic-actions">
                        <button type="submit" class="btn-registrar" id="btnExaminarTexto">
                            <i class="fas fa-magnifying-glass"></i> Verificar y Rellenar
                        </button>
                    </div>
                </form>
                </div>
                </div>
                </div>

                <div class="lic-acciones">
                    <button type="submit" class="btn-registrar" id="btnRegistrar" form="licForm">
                        <i class="fas fa-key"></i> Activar Licencia
                    </button>
                    <button type="button" class="btn-solicitar" id="btnSolicitar">
                        <i class="fas fa-envelope"></i> Solicitar Licencia por Correo
                    </button>
                    <button type="button" class="btn-home" id="btnHome">
                        <i class="fas fa-arrow-left"></i> Regresar al Inicio
                    </button>
                </div>
            </div>

            <div class="lic-footer">
                Este programa se distribuye bajo registro de licencia. Los datos introducidos se almacenan <b>cifrados</b> y de forma <b>única</b> en este equipo.
            </div>
        </div>
    </div>

    <!-- Modal: solicitud de licencia por correo -->
    <div class="lic-modal-overlay" id="solOverlay" hidden>
        <div class="lic-modal" role="dialog" aria-modal="true" aria-labelledby="solTitulo">
            <div class="lic-modal-head">
                <div class="lic-modal-title" id="solTitulo">
                    <i class="fas fa-envelope"></i> Solicitar Licencia por Correo
                </div>
                <button type="button" class="lic-modal-x" id="solCerrar" aria-label="Cerrar">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="lic-modal-body">
                <p class="lic-modal-intro">
                    Complete sus datos y el periodo de validez deseado. Se enviara la solicitud con el
                    <b>Codigo de equipo</b> de esta PC y la licencia sera remitida al
                    <b>correo electronico</b> que registre.
                </p>
                <div class="lic-modal-fp">
                    <span class="lic-modal-fp-label"><i class="fas fa-fingerprint"></i> Codigo de Equipo:</span>
                    <span class="lic-modal-fp-value" id="solHuella"><?php echo htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine())); ?></span>
                    <i class="fas fa-copy" id="solCopiarHuella" role="button" tabindex="0" title="Copiar el codigo de equipo al portapapeles" aria-label="Copiar el codigo de equipo al portapapeles" data-copiar-valor="<?php echo htmlspecialchars(licencia_formatear_fingerprint(licencia_fingerprint_machine())); ?>" onclick="copiarAlPortapapeles(this)"></i>
                </div>

                <form id="solForm" autocomplete="off" novalidate>
                    <div class="input-fila">
                        <div class="input-group">
                            <label for="solNombre"><i class="fas fa-user"></i> Nombre</label>
                            <input type="text" id="solNombre" name="nombre" maxlength="60" placeholder="Nombres">
                        </div>
                        <div class="input-group">
                            <label for="solApellidos"><i class="fas fa-user"></i> Apellidos</label>
                            <input type="text" id="solApellidos" name="apellidos" maxlength="60" placeholder="Apellidos">
                        </div>
                    </div>
                    <div class="input-fila">
                        <div class="input-group">
                            <label for="solCi"><i class="fas fa-id-card"></i> Carne de identidad</label>
                            <input type="text" id="solCi" name="ci" maxlength="11" inputmode="numeric" placeholder="11 digitos">
                            <small class="sol-feedback" id="solCiFeedback"></small>
                        </div>
                        <div class="input-group">
                            <label for="solUsuario"><i class="fas fa-user-tie"></i> Usuario</label>
                            <input type="text" id="solUsuario" name="usuario" maxlength="60" placeholder="Usuario del sistema">
                        </div>
                    </div>
                    <div class="input-fila">
                        <div class="input-group">
                            <label for="solEntidad"><i class="fas fa-building"></i> Entidad</label>
                            <input type="text" id="solEntidad" name="entidad" maxlength="120" placeholder="Empresa o entidad">
                        </div>
                        <div class="input-group">
                            <label for="solPeriodo"><i class="fas fa-calendar-days"></i> Periodo de validez</label>
                            <select id="solPeriodo" name="periodo" class="lic-select">
                                <option value="">Seleccione un periodo</option>
                                <?php foreach (solicitud_licencia_periodos() as $codigo => $periodoTexto): ?>
                                <option value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($periodoTexto, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="input-fila full">
                        <div class="input-group">
                            <label for="solEmail"><i class="fas fa-envelope"></i> Correo electronico</label>
                            <div class="input-linea">
                                <input type="email" id="solEmail" name="email" maxlength="120" placeholder="correo@ejemplo.com" spellcheck="false" autocapitalize="off" autocomplete="email">
                                <label class="lic-switch has-tip tip-der" for="solGenerica" data-tooltip="Se generará automáticamente en la máquina que instale la licencia.">
                                    <input type="checkbox" id="solGenerica" name="generica" value="1">
                                    <span class="lic-switch-track"></span>
                                    <span class="lic-switch-txt">Genérica</span>
                                </label>
                            </div>
                            <small class="sol-feedback" id="solEmailFeedback"></small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="lic-modal-foot">
                <button type="button" class="btn-home" id="solCancelar">
                    <i class="fas fa-xmark"></i> Cancelar
                </button>
                <button type="button" class="btn-registrar" id="solEnviar">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitud
                </button>
                <button type="button" class="btn-correo" id="solCorreo" data-destino="<?php echo htmlspecialchars(solicitud_licencia_destino(), ENT_QUOTES, 'UTF-8'); ?>" title="Abre el cliente de correo configurado en esta PC con la solicitud ya redactada">
                    <i class="fas fa-envelope-open-text"></i> Enviar con mi Correo
                </button>
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
    function copiarAlPortapapeles(icono) {
    var texto = icono.getAttribute('data-copiar-valor') || icono.getAttribute('data-fp-copiar') || '';
    if (!texto) return;
    var restaurar = icono.getAttribute('data-copiar-titulo') || icono.getAttribute('data-fp-titulo') || 'Copiar al portapapeles';
    var clase = icono.className;
    var confirmar = function () {
        icono.className = clase.replace('fa-copy', 'fa-check');
        icono.setAttribute('title', 'Copiado al portapapeles');
        setTimeout(function () {
            icono.className = clase;
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

function copiarHuella(icono) { copiarAlPortapapeles(icono); }

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

    /* ---------- Verificacion en vivo del texto de licencia (check / X) ---------- */

    var licTextoTemporizador = null;

    function licTextoEscapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto == null ? '' : texto;
        return div.innerHTML;
    }

    function licTextoEstado(estado, mensaje, datos) {
        var caja = document.getElementById('licTextoEstado');
        var texto = document.getElementById('licTextoEstadoMsg');
        if (!caja || !texto) return;
        caja.className = 'lic-estado lic-estado-' + estado;
        var icono = caja.querySelector('i');
        if (icono) {
            icono.className = estado === 'ok' ? 'fas fa-circle-check'
                             : (estado === 'error' ? 'fas fa-circle-xmark' : 'fas fa-circle-info');
        }
        texto.innerHTML = licTextoEscapar(mensaje) + (datos ? '<span class="lic-estado-datos">' + datos + '</span>' : '');
    }

    /* Se dispara con oninput al escribir o pegar; espera a que el usuario termine. */
    function licTextoValidar() {
        var campo = document.getElementById('licencia_texto');
        if (!campo) return;
        if (licTextoTemporizador) clearTimeout(licTextoTemporizador);
        var texto = campo.value.trim();
        if (texto.length < 20) {
            licTextoEstado('espera', 'Pegue el texto de la licencia para verificarlo.');
            return;
        }
        licTextoEstado('espera', 'Verificando la licencia...');
        licTextoTemporizador = setTimeout(function () { licTextoComprobar(texto); }, 400);
    }

    function licTextoComprobar(texto) {
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: 'validar_texto=1&licencia_texto=' + encodeURIComponent(texto)
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              if (j && j.ok === true) {
                  var detalle = '<b>Registro:</b> ' + licTextoEscapar(j.registro)
                              + ' &nbsp;|&nbsp; <b>Usuario:</b> ' + licTextoEscapar(j.usuario)
                              + ' &nbsp;|&nbsp; <b>Termino:</b> ' + licTextoEscapar(j.termino)
                              + ' &nbsp;|&nbsp; <b>Vence:</b> ' + licTextoEscapar(j.vence)
                              + (j.exclusiva ? '<br>Licencia exclusiva de este equipo.' : '');
                  licTextoEstado('ok', 'Licencia valida para este equipo.', detalle);
                  licTextoRellenar(j);
              } else {
                  var extra = '';
                  if (j && j.huella_destino) {
                      extra = '<span class="lic-estado-datos"><b>Huella destino:</b> ' + licTextoEscapar(j.huella_destino)
                            + '<br><b>Este equipo:</b> ' + licTextoEscapar(j.huella_equipo) + '</span>';
                  }
                  licTextoEstado('error', (j && j.mensaje) ? j.mensaje : 'No se pudo verificar la licencia.', extra);
              }
          })
          .catch(function () {
              licTextoEstado('error', 'No se pudo verificar la licencia. Intente de nuevo.');
          });
    }

    /* Rellena nombre, usuario y serial con los datos de la licencia verificada. */
    function licTextoRellenar(j) {
        var nombre  = document.getElementById('nombre');
        var usuario = document.getElementById('usuario');
        var serial  = document.getElementById('serial');
        if (!nombre || !usuario || !serial) return;
        if (nombre.value  !== j.registro) { nombre.value  = j.registro; }
        if (usuario.value !== j.usuario)  { usuario.value = j.usuario; }
        if (serial.value   !== j.serial)   { serial.value   = j.serial; }
        // Una sola verificacion con los tres datos ya rellenados.
        serial.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var serial = document.getElementById('serial');
        var nombreInput = document.getElementById('nombre');
        var usuarioInput = document.getElementById('usuario');
        var serialCheck = document.getElementById('serialCheck');
        var licSerialSolicitud = 0;

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
            // Descarta respuestas viejas: solo aplica la verificacion mas reciente.
            var solicitud = ++licSerialSolicitud;
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
                if (solicitud !== licSerialSolicitud) { return; }
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

        // --- Solicitud de licencia por correo ---
        (function () {
            var overlay  = document.getElementById('solOverlay');
            var btnAbrir = document.getElementById('btnSolicitar');
            var btnCerrar= document.getElementById('solCerrar');
            var btnCancel= document.getElementById('solCancelar');
            var btnEnviar= document.getElementById('solEnviar');
            var btnCorreo= document.getElementById('solCorreo');
            if (!overlay || !btnAbrir || !btnCorreo) { return; }

            var CAMPOS = [
                { id: 'solNombre',    clave: 'nombre' },
                { id: 'solApellidos', clave: 'apellidos' },
                { id: 'solCi',        clave: 'ci' },
                { id: 'solEmail',     clave: 'email' },
                { id: 'solUsuario',   clave: 'usuario' },
                { id: 'solEntidad',   clave: 'entidad' },
                { id: 'solPeriodo',   clave: 'periodo' }
            ];

            function abrir() {
                overlay.hidden = false;
                document.getElementById('solNombre').focus();
            }
            function cerrar() {
                overlay.hidden = true;
                limpiarErrores();
            }

            // Mismo criterio que validarCI() en login.php: 11 digitos, fecha de
            // nacimiento valida y digito de genero en la posicion 10.
            function validarCI(ci) {
                ci = String(ci).replace(/[\s-]/g, '');
                if (!/^\d{11}$/.test(ci)) return { valido: false, mensaje: 'Debe contener 11 digitos numericos' };
                var anio = ci.substr(0, 2);
                var mes  = ci.substr(2, 2);
                var dia  = ci.substr(4, 2);
                if (mes < '01' || mes > '12') return { valido: false, mensaje: 'Mes de nacimiento invalido' };
                var diasPorMes = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                var maxDias = diasPorMes[parseInt(mes, 10) - 1];
                if (parseInt(mes, 10) === 2 && parseInt(dia, 10) === 29) {
                    var anioLargo = parseInt(anio, 10) < 30 ? 2000 + parseInt(anio, 10) : 1900 + parseInt(anio, 10);
                    var bisiesto = (anioLargo % 4 === 0 && anioLargo % 100 !== 0) || (anioLargo % 400 === 0);
                    if (!bisiesto) return { valido: false, mensaje: '29/02 solo es valido en anos bisiestos' };
                } else if (dia < '01' || parseInt(dia, 10) > maxDias) {
                    return { valido: false, mensaje: 'Dia de nacimiento invalido' };
                }
                var digitoGenero = parseInt(ci.charAt(9), 10);
                var genero    = digitoGenero % 2 === 0 ? 'Masculino' : 'Femenino';
                var anioTexto = parseInt(anio, 10) < 30 ? '20' + anio : '19' + anio;
                return {
                    valido: true,
                    mensaje: 'CI valido | ' + genero + ' | Nac: ' + dia + '/' + mes + '/' + anioTexto,
                    icono: digitoGenero % 2 === 0 ? 'fa-mars' : 'fa-venus'
                };
            }

            // Espejo en cliente de FILTER_VALIDATE_EMAIL del servidor: sin espacios,
            // un unico @, ningun punto consecutivo y dominio con extension de 2+ letras.
            var RE_EMAIL = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;

            function validarEmail(email) {
                var v = String(email).trim();
                if (v === '') return { valido: false, mensaje: 'Ingrese su correo electronico' };
                if (v.indexOf('..') !== -1 || v.charAt(0) === '.' || v.charAt(v.length - 1) === '.') {
                    return { valido: false, mensaje: 'Formato de correo electronico invalido' };
                }
                if (!RE_EMAIL.test(v)) return { valido: false, mensaje: 'Formato de correo electronico invalido' };
                return { valido: true, mensaje: 'Correo electronico valido' };
            }

            function mostrarFeedback(id, resultado) {
                var box = document.getElementById(id + 'Feedback');
                if (!box) return;
                box.classList.remove('ok', 'mal');
                if (!resultado) { box.innerHTML = ''; return; }
                if (resultado.valido) {
                    box.classList.add('ok');
                    box.innerHTML = '<i class="fas fa-check-circle me-1"></i>'
                        + (resultado.icono ? '<i class="fas ' + resultado.icono + ' me-1"></i>' : '')
                        + resultado.mensaje;
                } else {
                    box.classList.add('mal');
                    box.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + resultado.mensaje;
                }
            }

            function limpiarErrores() {
                CAMPOS.forEach(function (c) {
                    document.getElementById(c.id).classList.remove('error');
                    mostrarFeedback(c.id, null);
                });
            }

            btnAbrir.addEventListener('click', abrir);
            btnCerrar.addEventListener('click', cerrar);
            btnCancel.addEventListener('click', cerrar);

            // Sin cierre por clic fuera, igual que el resto de modales.
            overlay.addEventListener('mousedown', function (e) {
                if (e.target === overlay) { e.preventDefault(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !overlay.hidden) { cerrar(); }
            });

            CAMPOS.forEach(function (c) {
                document.getElementById(c.id).addEventListener('input', function () {
                    this.classList.remove('error');
                });
            });

            // El CI solo admite digitos; el detalle se muestra al completar los 11.
            document.getElementById('solCi').addEventListener('input', function () {
                var digitos = this.value.replace(/\D/g, '').slice(0, 11);
                if (this.value !== digitos) { this.value = digitos; }
                mostrarFeedback('solCi', digitos.length === 11 ? validarCI(digitos) : null);
            });

            document.getElementById('solEmail').addEventListener('input', function () {
                var v = this.value.trim();
                mostrarFeedback('solEmail', (v === '' || v.indexOf('@') === -1) ? null : validarEmail(v));
            });

            function alerta(titulo, texto, icono) {
                Swal.fire({
                    icon: icono || 'warning',
                    title: titulo,
                    html: texto,
                    confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                    background: '#262626',
                    color: '#ffffff',
                    confirmButtonColor: '#0078d4'
                });
            }

            // Recolecta y valida el formulario: obligatorios, CI de 11 digitos y
            // correo valido. Devuelve los valores normalizados o null si hay errores.
            function prepararSolicitud() {
                limpiarErrores();

                var valores = {};
                var faltan = [];
                CAMPOS.forEach(function (c) {
                    var el = document.getElementById(c.id);
                    var v = (el.value || '').trim();
                    valores[c.clave] = v;
                    if (v === '') {
                        el.classList.add('error');
                        faltan.push(el);
                    }
                });

                if (faltan.length > 0) {
                    alerta('Faltan datos', 'Complete todos los campos para enviar la solicitud.');
                    faltan[0].focus();
                    return null;
                }

                // Carne de identidad: 11 digitos con fecha de nacimiento valida.
                var ci = document.getElementById('solCi');
                var resCI = validarCI(ci.value);
                if (!resCI.valido) {
                    ci.classList.add('error');
                    mostrarFeedback('solCi', resCI);
                    alerta('Carne de identidad invalido', resCI.mensaje + '.');
                    ci.focus();
                    return null;
                }

                // Correo: debe ser una direccion valida para recibir la licencia.
                var mail = document.getElementById('solEmail');
                var resMail = validarEmail(mail.value);
                if (!resMail.valido) {
                    mail.classList.add('error');
                    mostrarFeedback('solEmail', resMail);
                    alerta('Correo electronico invalido', resMail.mensaje + '.');
                    mail.focus();
                    return null;
                }

                valores.ci       = ci.value.replace(/\D/g, '');
                valores.email    = mail.value.trim();
                // Slider "Generica": la licencia se emite al equipo que la instala.
                var chkGenerica   = document.getElementById('solGenerica');
                valores.generica = (chkGenerica && chkGenerica.checked) ? '1' : '0';
                valores.huella   = (document.getElementById('solHuella').textContent || '').trim();
                valores.destino  = (btnCorreo.getAttribute('data-destino') || '').trim();
                return valores;
            }

            // Envio directo por el SMTP configurado en el sistema.
            btnEnviar.addEventListener('click', function () {
                var datos = prepararSolicitud();
                if (!datos) { return; }

                var fd = new FormData();
                CAMPOS.forEach(function (c) { fd.append(c.clave, datos[c.clave]); });
                fd.append('generica', datos.generica);

                btnEnviar.disabled = true;
                var textoBoton = btnEnviar.innerHTML;
                btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

                fetch(location.pathname + '?solicitar_licencia=1', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j && j.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Solicitud enviada',
                                html: j.mensaje,
                                confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                                background: '#262626',
                                color: '#ffffff',
                                confirmButtonColor: '#0078d4'
                            }).then(function () {
                                document.getElementById('solForm').reset();
                                cerrar();
                            });
                        } else {
                            if (j && j.errores) {
                                Object.keys(j.errores).forEach(function (k) {
                                    CAMPOS.forEach(function (c) {
                                        if (c.clave === k) { document.getElementById(c.id).classList.add('error'); }
                                    });
                                });
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo enviar',
                                html: (j && j.mensaje) ? j.mensaje : 'Ocurrio un error inesperado.',
                                confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                                background: '#262626',
                                color: '#ffffff',
                                confirmButtonColor: '#0078d4'
                            });
                        }
                    })
                    .catch(function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexion',
                            html: 'No se pudo contactar al servidor. Verifique su conexion e intente nuevamente.',
                            confirmButtonText: '<i class="fas fa-check"></i> Entendido',
                            background: '#262626',
                            color: '#ffffff',
                            confirmButtonColor: '#0078d4'
                        });
                    })
                    .then(function () {
                        btnEnviar.disabled = false;
                        btnEnviar.innerHTML = textoBoton;
                    });
            });

            // Envio usando el cliente de correo local predeterminado (mailto:).
            btnCorreo.addEventListener('click', function () {
                var d = prepararSolicitud();
                if (!d) { return; }

                var sel     = document.getElementById('solPeriodo');
                var periodo = sel.options[sel.selectedIndex].text;
                var linea   = new Array(41).join('=');
                var cuerpo  = [
                    'SOLICITUD DE LICENCIA SisGesNom',
                    linea,
                    '',
                    'Nombre:             ' + d.nombre,
                    'Apellidos:          ' + d.apellidos,
                    'Carne de identidad: ' + d.ci,
                    'Correo electronico: ' + d.email,
                    'Usuario:            ' + d.usuario,
                    'Entidad:            ' + d.entidad,
                    'Periodo de validez: ' + periodo,
                    'Codigo de equipo:   ' + d.huella
                ];
                if (d.generica === '1') { cuerpo.push('Tipo:               Generica'); }
                cuerpo.push('', linea);
                var texto = cuerpo.join('\n');
                var asunto = 'Solicitud de licencia - ' + d.entidad + ' (' + periodo + ')';

                // Se dispara un enlace real para no alterar la URL de la pagina.
                // encodeURI (no encodeURIComponent) para no escapar la arroba del destinatario.
                var enlace = document.createElement('a');
                enlace.href = 'mailto:' + encodeURI(d.destino)
                    + '?subject=' + encodeURIComponent(asunto)
                    + '&body=' + encodeURIComponent(texto);
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);
            });
        })();
    });
    </script>
</body>
</html>