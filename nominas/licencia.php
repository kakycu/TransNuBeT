<?php
// licencia.php - Pantalla de registro de licencia (serial).
// Corre antes del login y NO requiere base de datos.
// Almacena los datos UNA sola vez, cifrados, en el registro de Windows
// o en un archivo cifrado del disco (Linux/otros).

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/licencia.php';

// Si ya está registrado, ir directo al login.
if (licencia_activada()) {
    header('Location: login.php');
    exit;
}

$error        = '';
$guardado_ok  = false;
$nombre       = isset($_POST['nombre'])  ? trim((string)$_POST['nombre'])  : '';
$usuario      = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
$serial       = isset($_POST['serial'])  ? trim((string)$_POST['serial'])  : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($nombre === '') {
        $error = 'Debe indicar el <b>Nombre de Registro</b>.';
    } elseif ($usuario === '') {
        $error = 'Debe indicar el <b>Usuario del Registro</b>.';
    } elseif (preg_match('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){4}$/', strtoupper($serial)) !== 1) {
        $error = 'La <b>Llave Serial</b> no tiene el formato válido (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX).';
    } elseif (!licencia_validar_serial($nombre, $usuario, $serial)) {
        $error = 'La <b>Llave Serial</b> no corresponde a los datos de registro introducidos.<br>Verifique Nombre y Usuario, o solicite una llave válida.';
    } else {
        if (licencia_guardar($nombre, $usuario, $serial)) {
            $guardado_ok = true;
        } else {
            $error = 'No se pudo guardar la licencia en este equipo.<br>Compruebe que la aplicación tiene permisos de escritura.';
        }
    }
}

// Feedback de éxito con SweetAlert y redirección al login.
if ($guardado_ok) {
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
        html: 'El sistema ha sido <b>registrado correctamente</b>.<br>Redirigiendo al inicio de sesión&hellip;',
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
        .serial-input { text-transform:uppercase; letter-spacing:2px; font-family:'Consolas', monospace; }
        .hint { margin-top:0.375rem; font-size:0.72rem; color:#64748b; }
        .btn-registrar {
            width:100%;
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
            display:flex; align-items:center; justify-content:center; gap:0.5rem;
            width:100%;
            margin-top:0.7rem;
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
        .lic-footer {
            padding:0.75rem 1.5rem 1rem;
            text-align:center;
            color:#475569;
            font-size:0.72rem;
        }
        .lic-footer b { color:#64748b; }
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
                    <div class="badge-lic"><i class="fas fa-lock-open"></i> PENDIENTE DE REGISTRO</div>
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
                        </div>
                        <div class="hint"><i class="fas fa-circle-info"></i> Formato: 25 caracteres en 5 grupos de 5, separados por guiones.</div>
                    </div>

                    <button type="submit" class="btn-registrar" id="btnRegistrar">
                        <i class="fas fa-key"></i> Activar Licencia
                    </button>
                    <button type="button" class="btn-home" id="btnHome">
                        <i class="fas fa-arrow-left"></i> Regresar al Inicio
                    </button>
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
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var serial = document.getElementById('serial');

        // Autoconvertir a mayúsculas mientras se escribe.
        serial.addEventListener('input', function () {
            serial.value = serial.value.toUpperCase();
        });

        // Polegar: rellenar guiones automáticamente (un grupo por foco).
        serial.addEventListener('keyup', function () {
            var val = serial.value.replace(/[^A-Z0-9]/g, '').slice(0, 25);
            var grupos = [];
            for (var i = 0; i < val.length; i += 5) {
                grupos.push(val.substr(i, 5));
            }
            serial.value = grupos.join('-');
        });

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
    });
    </script>
</body>
</html>