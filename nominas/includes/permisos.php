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

    $modulos = ['dashboard', 'empleados', 'nominas', 'cierres', 'reportes', 'clasificadores', 'configuracion', 'usuarios', 'bandecnom', 'submayor', 'solapines', 'snc225', 'domiciliacion_tarjetas'];

    foreach ($modulos as $m) {
        $matriz['Admin'][$m] = $TODO;
        $matriz['Soft'][$m] = $TODO;
    }

    // Soft es un rol de consulta y exportacion. En cierres se queda en
    // solo lectura porque cerrar consolida la nomina del ano y reabrir deshace
    // un cierre: son decisiones que exceden a un usuario de solo lectura.
    $matriz['Soft']['bandecnom'] = $NADA;
    $matriz['Soft']['cierres']    = $SOLO_LECTURA;

    // Visualizador: sin acceso a SNC-225 ni a Domiciliación de Tarjetas
    $matriz['Visor'] = [
        'dashboard' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false, 'exportar' => false],
        'empleados' => $SOLO_LECTURA,
        'nominas' => $SOLO_LECTURA,
        'cierres' => $NADA,
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
        'cierres' => $SOLO_LECTURA,
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
        // El Supervisor General cierra y reabre periodos, pero no borra cierres:
        // un cierre reabierto se conserva como 'revertido' con su motivo.
        'cierres' => ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => false, 'exportar' => true],
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
 * Carpetas del sistema (Exportaciones / Descargas / Salvas) del Dashboard.
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
            'descargaDir' => 'exports',
            'ruta'        => realpath(__DIR__ . '/../modules/exports'),
        ],
        'salvas' => [
            'titulo'      => 'Carpeta de Salvas',
            'icono'       => 'fa-box-archive',
            'descripcion' => 'Copias de seguridad y salvas generadas por el sistema.',
            'descargable' => true,
            'descargaDir' => '../backups',
            'ruta'        => realpath(__DIR__ . '/../backups'),
        ],
        'descargas' => [
            'titulo'      => 'Carpeta Descargas',
            'icono'       => 'fa-download',
            'descripcion' => 'Carpeta de Descargas del equipo donde el navegador guarda los archivos.',
            // Fuera del DocumentRoot: la descarga pasa por el endpoint
            // descargar_archivo.php (PATH_INFO) en vez de una URL directa.
            'descargable' => true,
            'descargaDir' => 'descargar_archivo.php/descargas',
            'ruta'        => $perfil !== '' ? realpath($perfil . DIRECTORY_SEPARATOR . 'Downloads') : false,
        ],
        'temp' => [
            'titulo'      => 'Carpeta Temporales',
            'icono'       => 'fa-hourglass-half',
            'descripcion' => 'Archivos y carpetas temporales de trabajo del sistema.',
            'descargable' => true,
            'descargaDir' => '../temp',
            'ruta'        => realpath(__DIR__ . '/../temp'),
        ],
        'logs' => [
            'titulo'      => 'Carpeta de Logs',
            'icono'       => 'fa-clipboard-list',
            'descripcion' => 'Registros de eventos y errores (logs) del sistema.',
            'descargable' => true,
            'descargaDir' => '../logs',
            'ruta'        => realpath(__DIR__ . '/../logs'),
        ],
    ];
}

/**
 * Permisos del explorador de carpetas segun el rol.
 *
 * 1 Admin  -> descargar, eliminar, abrir y vaciar;
 * 2 Visor  -> sin acceso al modulo;
 * 3 Editor -> descargar y abrir;
 * 4 Super  -> descargar y vaciar;
 * 5 Soft   -> descargar, abrir y vaciar.
 *
 * "vaciar" borra TODO el contenido de la carpeta abierta y queda reservado a
 * los roles 1, 4 y 5: la misma lista valida el boton en carpetas.php y el
 * endpoint vaciar_carpeta.php.
 *
 * @return array<int, array{clave: string, etiqueta: string, icono: string}>
 */
function carpetas_sistema_permisos_rol($codigo)
{
    $descargar = ['clave' => 'descargar', 'etiqueta' => 'Descargar', 'icono' => 'fa-download'];
    $eliminar  = ['clave' => 'eliminar',  'etiqueta' => 'Eliminar',  'icono' => 'fa-trash-alt'];
    $abrir     = ['clave' => 'abrir',     'etiqueta' => 'Abrir',     'icono' => 'fa-folder-open'];
    $vaciar    = ['clave' => 'vaciar',    'etiqueta' => 'Vaciar',    'icono' => 'fa-trash-can'];

    switch ($codigo) {
        case 'Admin':
            return [$descargar, $eliminar, $abrir, $vaciar];
        case 'Soft':
            return [$descargar, $abrir, $vaciar];
        case 'Super':
            return [$descargar, $vaciar];
        case 'Editor':
            return [$descargar, $abrir];
        default:
            return [$descargar];
    }
}

/**
 * El rol actual puede realizar una accion en el explorador de carpetas?
 *
 * @param string $accion descargar|eliminar|abrir|vaciar
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

/**
 * Normaliza una ruta relativa dentro de una carpeta del sistema
 * (subcarpetas creadas al extraer un ZIP, p. ej. "MI_ZIP/informes").
 *
 * Devuelve la cadena normalizada ('' para la raiz) o null si la ruta es
 * invalida ( '..' , rutas absolutas, unidades, nulos).
 *
 * @return string|null
 */
function carpetas_ruta_relativa($ruta)
{
    $ruta = str_replace('\\', '/', (string)$ruta);
    $ruta = trim($ruta);

    if ($ruta === '' || strpos($ruta, "\0") !== false) {
        return $ruta === '' ? '' : null;
    }
    if (strpos($ruta, '/') === 0 || preg_match('#^[A-Za-z]:#', $ruta) === 1) {
        return null;
    }

    $trozos = [];
    foreach (explode('/', $ruta) as $parte) {
        $parte = trim($parte);
        if ($parte === '' || $parte === '.') {
            continue;
        }
        if ($parte === '..') {
            return null;
        }
        $trozos[] = $parte;
    }

    return implode('/', $trozos);
}

/**
 * Resuelve una ruta relativa dentro de una carpeta del sistema y devuelve su
 * ruta real absoluta, o false si no existe o se sale de la raiz permitida.
 *
 * @param string $raiz      Ruta absoluta de la carpeta del sistema.
 * @param string $relativa  Ruta relativa ('' = la propia raiz).
 * @return string|false
 */
function carpetas_ruta_resolver($raiz, $relativa = '')
{
    $relativa = carpetas_ruta_relativa($relativa);
    if ($relativa === null) {
        return false;
    }

    $raizLimpia = rtrim($raiz, "/\\");
    if ($relativa === '') {
        return $raizLimpia;
    }

    $candidata = $raizLimpia . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $relativa);

    $real = realpath($candidata);
    if ($real === false || !is_dir($real)) {
        return false;
    }

    $prefijo = $raizLimpia . DIRECTORY_SEPARATOR;
    if (stripos(rtrim($real, "/\\") . DIRECTORY_SEPARATOR, $prefijo) !== 0) {
        return false;
    }

    return $real;
}

/**
 * Normaliza una ruta declarada dentro de un ZIP.
 *
 * Devuelve null si la ruta puede salirse del directorio de destino
 * (zip-slip: "../../windows/...", rutas absolutas, unidades, nulos).
 *
 * @return string|null
 */
function carpetas_nombre_seguro($nombre)
{
    $nombre = str_replace('\\', '/', (string)$nombre);
    $nombre = ltrim($nombre, '/');

    if ($nombre === '' || strpos($nombre, "\0") !== false) {
        return null;
    }
    if (preg_match('#^[A-Za-z]:#', $nombre) === 1) {
        return null;
    }

    $limpias = [];
    foreach (explode('/', $nombre) as $parte) {
        if ($parte === '' || $parte === '.') {
            continue;
        }
        if ($parte === '..') {
            return null;
        }
        $limpias[] = $parte;
    }

    return count($limpias) ? implode('/', $limpias) : null;
}

/**
 * Borra un archivo o una carpeta con todo su contenido.
 * No sigue enlaces simbolicos: un enlace se elimina como tal, sin recorrerlo.
 *
 * @return string|null null si todo se borro; el motivo del fallo en caso contrario.
 */
/**
 * Borra un fichero teniendo en cuenta Windows: los archivos con el atributo
 * "Solo lectura" (muy comunes al descomprimir ZIP) hacen fallar unlink(),
 * asi que antes se quita ese atributo con chmod().
 *
 * @param string $ruta Ruta absoluta del fichero.
 * @return bool true si el fichero dejo de existir.
 */
function carpetas_borrar_fichero($ruta)
{
    @chmod($ruta, 0666);
    return @unlink($ruta);
}

function carpetas_borrar_recursivo($ruta)
{
    if (is_link($ruta)) {
        return carpetas_borrar_fichero($ruta)
            ? null
            : 'no se pudo eliminar el enlace "' . basename($ruta) . '"';
    }

    if (is_dir($ruta)) {
        $items = @scandir($ruta);
        if ($items === false) {
            return 'no se pudo leer la carpeta "' . basename($ruta) . '"';
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $error = carpetas_borrar_recursivo($ruta . DIRECTORY_SEPARATOR . $item);
            if ($error !== null) {
                return $error;
            }
        }
        return @rmdir($ruta) ? null : 'no se pudo eliminar la carpeta "' . basename($ruta) . '"';
    }

    if (is_file($ruta)) {
        return carpetas_borrar_fichero($ruta)
            ? null
            : 'no se pudo eliminar el archivo "' . basename($ruta) . '"';
    }

    return null;   // ya no existe
}

/**
 * Elimina una carpeta (con todo su contenido) y comprueba antes de nada que
 * esta dentro de la raiz permitida y que no es la propia raiz.
 *
 * @param string $ruta Ruta absoluta de la carpeta a borrar.
 * @param string $raiz Ruta absoluta de la carpeta del sistema.
 * @return string|null null si se elimino; el motivo del fallo en caso contrario.
 */
function carpetas_eliminar_arbol($ruta, $raiz)
{
    $rutaReal = realpath($ruta);
    $raizReal = realpath($raiz);

    if ($rutaReal === false || !is_dir($rutaReal)) {
        return 'La carpeta no existe.';
    }
    if ($raizReal === false) {
        return 'La carpeta de origen no existe.';
    }

    $rutaLimpia = rtrim($rutaReal, "/\\");
    $raizLimpia = rtrim($raizReal, "/\\");

    if (strcasecmp($rutaLimpia, $raizLimpia) === 0) {
        return 'No se puede eliminar la raiz de la carpeta del sistema.';
    }

    $prefijo = $raizLimpia . DIRECTORY_SEPARATOR;
    if (stripos($rutaLimpia . DIRECTORY_SEPARATOR, $prefijo) !== 0) {
        return 'La carpeta esta fuera de la carpeta permitida.';
    }

    $error = carpetas_borrar_recursivo($rutaLimpia);

    return $error !== null ? ucfirst($error) . '.' : null;
}

/**
 * Token CSRF de sesion para las operaciones de escritura del explorador
 * (extraer / descargar entradas de un ZIP). Se genera una sola vez.
 */
function carpetas_csrf_token()
{
    if (empty($_SESSION['csrf_carpetas'])) {
        $_SESSION['csrf_carpetas'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_carpetas'];
}

/**
 * Comprueba un token CSRF con comparacion constante.
 */
function carpetas_csrf_valido($token)
{
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_carpetas'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_carpetas'], $token);
}

/**
 * Carpetas del sistema que sirven como destino de una extraccion.
 * Solo las que existen en disco y a las que el rol puede escribir ("abrir").
 *
 * @param string $actual Clave de la carpeta que contiene el ZIP (se marca como tal).
 * @return array<int, array{clave: string, titulo: string, icono: string, actual: bool}>
 */
function carpetas_zip_destinos($actual = '')
{
    $destinos = [];

    foreach (carpetas_sistema_definicion() as $clave => $info) {
        if ($info['ruta'] === false || !is_dir($info['ruta'])) {
            continue;
        }
        $destinos[] = [
            'clave'   => $clave,
            'titulo'  => $info['titulo'],
            'icono'   => $info['icono'],
            'actual'  => $clave === $actual,
        ];
    }

    return $destinos;
}

/**
 * CRC-32 de una entrada del ZIP en el mismo formato que hash_final()
 * (8 digitos hexadecimales en minusculas), para poder compararlo.
 *
 * Ojo con el entorno: PHP corre en 32 bits, donde 0xFFFFFFFF es un double y
 * "crc & 0xFFFFFFFF" provocaria un Deprecated; por eso la cuenta se hace con
 * dos mitades de 16 bits, siempre dentro del rango del int. La clave del
 * central directory se llama "crc" en estas builds de ZipArchive.
 *
 * Devuelve '' si el ZIP no trae CRC (entonces no se valida).
 */
function carpetas_zip_crc_esperado($stat)
{
    $crc = null;
    if (isset($stat['crc'])) {
        $crc = $stat['crc'];
    } elseif (isset($stat['crc32'])) {
        $crc = $stat['crc32'];
    }

    if ($crc === null || !is_numeric($crc)) {
        return '';
    }

    $crc = (float)$crc;
    if ($crc < 0) {
        $crc += 4294967296;          // valor sin signo
    }
    $crc = fmod($crc, 4294967296);
    if ($crc < 0) {
        $crc += 4294967296;
    }

    $alto = (int)floor($crc / 65536);            // 0..65535
    $bajo = (int)($crc - ($alto * 65536));       // 0..65535

    return sprintf('%04x%04x', $alto, $bajo);
}

/**
 * Indica que entradas de un ZIP van cifradas con contraseña.
 *
 * ZipArchive no expone el bit de cifrado, asi que se recorre el central
 * directory (registro PK\x01\x02): su general purpose flag, bit 0, marca
 * "entrada protegida con contraseña". El orden de esos registros es el mismo
 * que el de los indices de statIndex().
 *
 * Si algo no cuadra (registro corrupto, offsets raros) se devuelve todo en
 * falso: como mucho no se mostrara el aviso, nunca se rompe el visor.
 *
 * @param string $ruta Ruta absoluta del .zip
 * @return array{total: int, cifradas: int, mapa: array<int, bool>}
 */
function carpetas_zip_flags($ruta)
{
    $res = ['total' => 0, 'cifradas' => 0, 'mapa' => []];

    $ruta = (string)$ruta;
    if ($ruta === '' || !is_file($ruta)) {
        return $res;
    }

    $tam = (int)@filesize($ruta);
    if ($tam < 22) {
        return $res;
    }

    $h = @fopen($ruta, 'rb');
    if ($h === false) {
        return $res;
    }

    // El EOCD (PK\x05\x06) esta al final; con el comentario del ZIP (65535
    // bytes maximos) bastan ~66 KB de cola.
    $colaTam = min($tam, 65557);
    if (@fseek($h, $tam - $colaTam) !== 0) {
        fclose($h);
        return $res;
    }
    $cola = (string)fread($h, $colaTam);

    $pos = strrpos($cola, "PK\x05\x06");
    if ($pos === false || strlen($cola) - $pos < 22) {
        fclose($h);
        return $res;
    }

    $eocd    = substr($cola, $pos, 22);
    $total   = unpack('v', substr($eocd, 10, 2))[1];   // entradas totales
    $tamCD   = unpack('V', substr($eocd, 12, 4))[1];   // tamano del CD
    $offCD   = unpack('V', substr($eocd, 16, 4))[1];   // offset del CD

    if ($total < 1 || $tamCD < 46 || $offCD < 0 || ($offCD + $tamCD) > $tam) {
        fclose($h);
        return $res;
    }

    if (@fseek($h, $offCD) !== 0) {
        fclose($h);
        return $res;
    }
    $cd = (string)fread($h, $tamCD);
    fclose($h);

    $len = strlen($cd);
    if ($len < 46) {
        return $res;
    }

    $i = 0;
    $n = 0;
    while (($i + 46) <= $len) {
        if (substr($cd, $i, 4) !== "PK\x01\x02") {
            break;
        }

        $flag     = unpack('v', substr($cd, $i + 8, 2))[1];
        $namelen  = unpack('v', substr($cd, $i + 28, 2))[1];
        $extralen = unpack('v', substr($cd, $i + 30, 2))[1];
        $comlen   = unpack('v', substr($cd, $i + 32, 2))[1];

        $cifrada = ((int)$flag & 1) === 1;
        $res['mapa'][$n] = $cifrada;   // clave = numero de entrada (statIndex)
        $res['total']++;
        if ($cifrada) {
            $res['cifradas']++;
        }

        $i += 46 + $namelen + $extralen + $comlen;
        $n++;
    }

    return $res;
}

/**
 * Aplica la contraseña del usuario al ZIP abierto.
 *
 * @param ZipArchive $zip      ZIP ya abierto.
 * @param string     $password Contraseña introducida ('' = sin contraseña).
 * @return bool true si se aplico (habia contraseña)
 */
function carpetas_zip_aplicar_password($zip, $password)
{
    $password = (string)$password;
    if ($password === '') {
        return false;
    }

    return (bool)$zip->setPassword($password);
}

/**
 * Detecta si el ultimo fallo del ZIP se debio a una contraseña errónea.
 *
 * @param ZipArchive $zip
 * @return bool
 */
function carpetas_zip_password_fallida($zip)
{
    $estado = (string)$zip->getStatusString();

    return stripos($estado, 'password') !== false
        || stripos($estado, 'crypt') !== false
        || stripos($estado, 'encryption') !== false;
}
