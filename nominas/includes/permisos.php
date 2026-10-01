<?php
// includes/permisos.php - Sistema central de permisos por rol
// Roles: Admin, Visor, Editor, Super, Soft

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Devuelve el código de rol del usuario actual (sesión, con refresh desde BD si es posible)
 */
function permiso_rol_codigo() {
    static $codigo = null;
    if ($codigo !== null) return $codigo;

    // Refrescar desde BD si $pdo está disponible (la sesión puede estar desactualizada)
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
    if ($user_id && isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT r.codigo AS rol_codigo FROM clasif_usuarios u LEFT JOIN clasif_rol r ON u.rol_id = r.id WHERE u.id = ?");
            $stmt->execute([$user_id]);
            $rol_bd = $stmt->fetchColumn();
            if ($rol_bd) {
                $_SESSION['rol_codigo'] = $rol_bd;
                $codigo = $rol_bd;
                return $codigo;
            }
        } catch (PDOException $e) {
            // Fallback a sesión
        }
    }

    $codigo = $_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? '';
    return $codigo;
}

/**
 * Módulos restringidos a Administrador (1), Contador/Editor (3) y Programador (5).
 * El Visualizador (2) y el Supervisor General (4) no pueden usarlos.
 */
function permisos_modulos_snc() {
    return ['snc225', 'domiciliacion_tarjetas'];
}

/**
 * Matriz de permisos por rol y módulo.
 * Acciones: ver, crear, editar, eliminar, exportar
 */
function permiso_matriz() {
    $TODO = ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true, 'exportar' => true];
    $SOLO_LECTURA = ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false, 'exportar' => true];
    $NADA = ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false, 'exportar' => false];
    $SIN_CREAR = ['ver' => true, 'crear' => false, 'editar' => true, 'eliminar' => true, 'exportar' => true];

    $modulos = ['dashboard', 'empleados', 'nominas', 'reportes', 'clasificadores', 'configuracion', 'usuarios', 'bandecnom', 'submayor', 'solapines', 'snc225', 'domiciliacion_tarjetas'];

    foreach ($modulos as $m) {
        $matriz['Admin'][$m] = $TODO;
        $matriz['Soft'][$m] = in_array($m, ['bandecnom'], true) ? $NADA : $TODO;
    }

    // Visualizador: sin acceso a SNC-225 ni a Domiciliación de Tarjetas
    $matriz['Visor'] = [
        'dashboard' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false, 'exportar' => false],
        'empleados' => $SOLO_LECTURA,
        'nominas' => $SOLO_LECTURA,
        'reportes' => $SOLO_LECTURA,
        'clasificadores' => $SOLO_LECTURA,
        'configuracion' => $NADA,
        'usuarios' => $NADA,
        'bandecnom' => $NADA,
        'submayor' => $SOLO_LECTURA,
        'solapines' => $SOLO_LECTURA,
        'snc225' => $NADA,
        'domiciliacion_tarjetas' => $NADA,
    ];

    $matriz['Editor'] = [
        'dashboard' => $TODO,
        'empleados' => $TODO,
        'nominas' => $TODO,
        'reportes' => $TODO,
        'clasificadores' => $NADA,
        'configuracion' => $NADA,
        'usuarios' => $NADA,
        'bandecnom' => $TODO,
        'submayor' => $TODO,
        'solapines' => $TODO,
        'snc225' => $TODO,
        'domiciliacion_tarjetas' => $TODO,
    ];

    // Supervisor General: también sin acceso a SNC-225 ni a Domiciliación de Tarjetas
    $matriz['Super'] = [
        'dashboard' => $TODO,
        'empleados' => $SIN_CREAR,
        'nominas' => $SIN_CREAR,
        'reportes' => $TODO,
        'clasificadores' => $TODO,
        'configuracion' => $TODO,
        'usuarios' => $NADA,
        'bandecnom' => $NADA,
        'submayor' => $TODO,
        'solapines' => $SOLO_LECTURA,
        'snc225' => $NADA,
        'domiciliacion_tarjetas' => $NADA,
    ];

    return $matriz;
}

/**
 * ¿Puede el usuario actual realizar $accion en $modulo?
 */
function permiso_puede($modulo, $accion = 'ver') {
    $codigo = permiso_rol_codigo();
    $matriz = permiso_matriz();
    if (isset($matriz[$codigo][$modulo][$accion])) {
        return (bool)$matriz[$codigo][$modulo][$accion];
    }
    // Seguridad por defecto: denegar
    return false;
}

/**
 * Muestra una página completa con SweetAlert de acceso denegado y redirige al dashboard.
 * Estilo dark (Win11) y emite la librería SweetAlert2 si no está presente.
 */
function permiso_denegar_acceso($modulo_nombre = '') {
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_in_modules = (strpos($current_script, '/modules/') !== false);
    $dashboard_url = $is_in_modules ? '../dashboard.php' : 'dashboard.php';
    $swal_src = $is_in_modules ? '../js/sweetalert2.all.min.js' : 'js/sweetalert2.all.min.js';
    $modulo_nombre = $modulo_nombre ? htmlspecialchars($modulo_nombre) : 'este módulo';
    $rol_actual = htmlspecialchars(permiso_rol_codigo() ?: 'Usuario');

    if (ob_get_level()) ob_clean();
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Acceso denegado - NOMINAS</title>
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <style>
        html, body { height:100%; margin:0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0c0c11;
            overflow: hidden;
        }
        .denied-bg {
            position: fixed; inset:0; z-index: -1;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(239, 68, 68, 0.13) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(59, 130, 246, 0.10) 0%, transparent 50%),
                linear-gradient(135deg, #0a0a0a 0%, #151522 45%, #0d0d14 100%);
        }
        .denied-bg::after {
            content: ''; position: absolute; inset:0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 0.0625rem, transparent 0.0625rem),
                linear-gradient(90deg, rgba(255,255,255,0.025) 0.0625rem, transparent 0.0625rem);
            background-size: 2.25rem 2.25rem;
        }
        .swal2-dark { border: 0.0625rem solid rgba(255,255,255,0.12) !important; border-radius: 1.125rem !important; }
        .swal2-title { color: #ffffff !important; }
        .swal2-html-container { color: #d1d5db !important; }
    </style>
</head>
<body>
    <div class="denied-bg"></div>
    <script>
    (function () {
        var modulo = <?php echo json_encode($modulo_nombre); ?>;
        var rol = <?php echo json_encode($rol_actual); ?>;
        var dash = <?php echo json_encode($dashboard_url); ?>;
        var swalSrc = <?php echo json_encode($swal_src); ?>;

        function show() {
            Swal.fire({
                title: '<i class="fas fa-shield-halved" style="color: #f87171;"></i> Acceso denegado',
                html:
                    '<div style="text-align:left;">' +
                        '<div style="display:flex;align-items:flex-start;gap:0.625rem;background:rgba(239,68,68,0.10);border:0.0625rem solid rgba(239,68,68,0.25);padding:0.75rem 0.875rem;border-radius:0.75rem;margin-bottom:0.875rem;">' +
                            '<i class="fas fa-exclamation-triangle" style="color:#f87171;font-size:1.125rem;margin-top:0.125rem;"></i>' +
                            '<span style="font-size:0.875rem;color:#e5e7eb;">Su rol <strong style="color:#93c5fd;">&laquo;' + rol + '&raquo;</strong> no tiene permisos para acceder a <strong style="color:#fbbf24;">' + modulo + '</strong>.</span>' +
                        '</div>' +
                        '<p style="margin:0 0 0.75rem;font-size:0.8125rem;color:#9ca3af;">' +
                            '<i class="fas fa-info-circle" style="margin-right:0.375rem;"></i>Contacte al administrador si considera que deber&iacute;a tener acceso a esta secci&oacute;n.' +
                        '</p>' +
                        '<div style="background:rgba(0,0,0,0.35);border:0.0625rem solid rgba(255,255,255,0.08);border-radius:0.75rem;padding:0.625rem 0.875rem;font-size:0.75rem;color:#c4b5fd;">' +
                            '<i class="fas fa-user-tag" style="margin-right:0.375rem;"></i>Su rol actual: <strong>' + rol + '</strong>' +
                        '</div>' +
                    '</div>',
                icon: 'error',
                confirmButtonColor: '#0ea5e9',
                confirmButtonText: '<i class="fas fa-arrow-left" style="margin-right:0.375rem;"></i> Volver al Dashboard',
                background: '#17171f',
                color: '#ffffff',
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: { popup: 'swal2-dark' }
            }).then(function () {
                window.location.href = dash;
            });
        }

        if (window.Swal && Swal.fire) {
            show();
        } else {
            // SweetAlert2 no está cargado: se emite bajo demanda
            var s = document.createElement('script');
            s.src = swalSrc;
            s.onload = show;
            s.onerror = function () { window.location.href = dash; };
            document.head.appendChild(s);
        }
    })();
    </script>
</body>
</html><?php
    exit;
}

/**
 * Para respuestas AJAX: corta con JSON de error 403
 */
function permiso_denegar_ajax($mensaje = null) {
    if ($mensaje === null) {
        $mensaje = 'No tiene permisos suficientes para realizar esta operación. Si considera que esto es un error, contacte al administrador del sistema.';
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $mensaje, 'denied' => true]);
    exit;
}

/**
 * Carpetas del sistema (Exportaciones / Descargas) del Dashboard.
 * Acceso: Administrador (1), Visualizador (2), Contador/Editor (3),
 * Supervisor General (4) y Programador (5); cada uno segun sus permisos.
 */
function carpetas_sistema_roles_permitidos() {
    return ['Admin', 'Visor', 'Editor', 'Super', 'Soft'];
}

/**
 * ¿El rol actual puede usar las carpetas del sistema?
 */
function carpetas_sistema_permitido() {
    return in_array(permiso_rol_codigo(), carpetas_sistema_roles_permitidos(), true);
}

/**
 * Ruta del perfil del usuario de Windows.
 * Apache no expone $_SERVER['USERPROFILE'], hay que usar getenv().
 */
function carpetas_perfil_usuario() {
    $perfil = trim((string)getenv('USERPROFILE'));
    if ($perfil === '') {
        $perfil = trim((string)($_SERVER['USERPROFILE'] ?? ''));
    }
    if ($perfil === '') {
        $drive = trim((string)getenv('HOMEDRIVE'));
        $path  = trim((string)getenv('HOMEPATH'));
        if ($drive !== '' && $path !== '') {
            $perfil = $drive . $path;
        }
    }
    return $perfil;
}

/**
 * Definición de las carpetas del sistema, con su metadata y ruta resuelta.
 * Compartida por carpetas.php (listado) y abrir_carpeta.php (apertura).
 *
 * @return array<string, array<string, mixed>>
 */
function carpetas_sistema_definicion() {
    $perfil = carpetas_perfil_usuario();

    return [
        'exportaciones' => [
            'titulo'      => 'Carpeta Exportaciones',
            'icono'       => 'fa-file-export',
            'descripcion' => 'Archivos generados por el sistema (DBF, Excel, PDF, ZIP).',
            'descargable' => true,
            'ruta'        => realpath(__DIR__ . '/../modules/exports'),
        ],
        'descargas' => [
            'titulo'      => 'Carpeta Descargas',
            'icono'       => 'fa-download',
            'descripcion' => 'Carpeta de Descargas del equipo donde el navegador guarda los archivos.',
            'descargable' => false,
            'ruta'        => $perfil !== '' ? realpath($perfil . DIRECTORY_SEPARATOR . 'Downloads') : false,
        ],
    ];
}

/**
 * Permisos del explorador de carpetas segun el rol.
 *
 * 1 Admin -> descargar, eliminar y abrir; 2 Visor -> sin acceso al modulo;
 * 3 Editor -> descargar y abrir; 4 Super -> solo descargar; 5 Soft -> descargar y abrir.
 *
 * @return array<int, array{clave: string, etiqueta: string, icono: string}>
 */
function carpetas_sistema_permisos_rol($codigo)
{
    $descargar = ['clave' => 'descargar', 'etiqueta' => 'Descargar', 'icono' => 'fa-download'];
    $eliminar  = ['clave' => 'eliminar',  'etiqueta' => 'Eliminar',  'icono' => 'fa-trash-alt'];
    $abrir     = ['clave' => 'abrir',     'etiqueta' => 'Abrir',     'icono' => 'fa-folder-open'];

    switch ($codigo) {
        case 'Admin':
            return [$descargar, $eliminar, $abrir];
        case 'Soft':
        case 'Editor':
            return [$descargar, $abrir];
        default:
            return [$descargar];
    }
}

/**
 * El rol actual puede realizar una accion en el explorador de carpetas?
 *
 * @param string $accion descargar|eliminar|abrir
 */
function carpetas_sistema_puede($accion)
{
    $accion = strtolower(trim((string)$accion));

    foreach (carpetas_sistema_permisos_rol(permiso_rol_codigo()) as $permiso) {
        if ($permiso['clave'] === $accion) {
            return true;
        }
    }

    return false;
}

/**
 * Resuelve una clave de carpeta válida y existente.
 *
 * @return array{0: string, 1: string}|null [clave, ruta real] o null si no aplica.
 */
function carpetas_sistema_resolver($clave) {
    $definicion = carpetas_sistema_definicion();
    $clave = trim((string)$clave);

    if (!isset($definicion[$clave]) || $definicion[$clave]['ruta'] === false) {
        return null;
    }

    $ruta = $definicion[$clave]['ruta'];
    if (!is_dir($ruta)) {
        return null;
    }

    return [$clave, $ruta];
}
