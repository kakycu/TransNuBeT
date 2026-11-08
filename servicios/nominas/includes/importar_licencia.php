<?php
// includes/importar_licencia.php - Importa (reemplaza) la licencia del equipo
// a partir de un archivo .lic, sin cerrar la sesión ni salir del sistema.
// Solo disponible para el rol 1 (Administrador).
// Responde siempre JSON para que el boton lo muestre con SweetAlert2.
// Nota: vive en /includes, por lo que las redirecciones suben un nivel con "../".

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* Respuesta JSON y salida. */
function lic_importar_responder($ok, $mensaje, $extra = array()) {
    echo json_encode(array_merge(array(
        'ok'      => (bool)$ok,
        'mensaje' => (string)$mensaje,
    ), (array)$extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

// database.php ya carga includes/licencia.php e includes/permisos.php.

// Acceso protegido: solo POST, sesion iniciada, token CSRF y rol 1 (Admin).
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lic_importar_responder(false, 'Solicitud no permitida.');
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    lic_importar_responder(false, 'Sesión no iniciada. Vuelva a iniciar sesión.');
}

// Solo el Administrador (rol 1) puede importar una licencia nueva.
if (permiso_rol_codigo() !== 'Admin') {
    lic_importar_responder(false, 'Solo el Administrador puede importar una licencia.');
}

$csrf_esperado = (string)($_SESSION['csrf_importar_licencia'] ?? '');
$csrf_recibido = (string)($_POST['csrf'] ?? '');
if ($csrf_esperado === '' || !hash_equals($csrf_esperado, $csrf_recibido)) {
    lic_importar_responder(false, 'La sesion de seguridad expiro. Recargue la pagina e intente de nuevo.');
}

// El motor de licencias debe estar disponible.
if (!function_exists('licencia_importar_texto') || !function_exists('licencia_fingerprint_machine')) {
    lic_importar_responder(false, 'El modulo de licencias no esta disponible en esta instalacion.');
}

/**
 * Analiza un texto de licencia pegado a mano, sin instalarlo.
 * Se usa para validar en vivo mientras el usuario escribe o pega.
 * @param string $contenido
 * @return array {ok: bool, mensaje: string, datos: array|null}
 */
function lic_importar_analizar_texto($contenido) {
    $texto = trim((string)$contenido);
    if ($texto === '') {
        return array('ok' => false, 'mensaje' => 'Pegue el texto de la licencia.', 'datos' => null);
    }
    if (strlen($texto) > 8192) {
        return array('ok' => false, 'mensaje' => 'El texto de la licencia es demasiado largo.', 'datos' => null);
    }
    $datos = licencia_leer_texto_lic($texto);
    if ($datos === null) {
        return array('ok' => false, 'mensaje' => 'La licencia no es valida: esta danada o no fue generada por este sistema.', 'datos' => null);
    }
    $huella_este = licencia_fingerprint_machine();
    if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $huella_este)) {
        return array('ok' => false, 'mensaje' => 'Esta licencia fue emitida para otro equipo. No se puede instalar en este computador.', 'datos' => null);
    }
    return array('ok' => true, 'mensaje' => 'Licencia valida.', 'datos' => $datos);
}

/**
 * Resume los datos de una licencia para mostrarlos en pantalla.
 * @param array $datos
 * @return array
 */
function lic_importar_resumen($datos) {
    return array(
        'registro' => $datos['registro'],
        'usuario'  => $datos['usuario'],
        'serial'   => licencia_formatear_serial($datos['serial']),
        'termino'  => isset($datos['info']['nombre']) ? $datos['info']['nombre'] : '',
        'vence'    => licencia_vencimiento($datos) === null
            ? 'Permanente'
            : date('d/m/Y', licencia_vencimiento($datos)),
        'estado'   => licencia_etiqueta_estado($datos, '', true),
    );
}

// Accion "validar": solo comprueba el texto pegado, NO toca la licencia instalada.
$accion = strtolower(trim((string)($_POST['accion'] ?? 'importar')));
if ($accion === 'validar') {
    $analisis = lic_importar_analizar_texto($_POST['texto'] ?? '');
    if ($analisis['ok'] === false) {
        lic_importar_responder(false, $analisis['mensaje']);
    }
    lic_importar_responder(true, 'Licencia valida.', lic_importar_resumen($analisis['datos']));
}

// Dos modos: "archivo" (subida de .lic) o "manual" (texto pegado).
$modo = strtolower(trim((string)($_POST['modo'] ?? 'archivo')));
if ($modo === '') $modo = 'archivo';
if (!in_array($modo, array('archivo', 'manual'), true)) {
    lic_importar_responder(false, 'Modo de importacion no valido.');
}

$tmp = '';
$etiqueta_origen = '';

if ($modo === 'manual') {
    // Texto de la licencia pegado a mano (blob cifrado en Base64).
    $texto = trim((string)($_POST['texto'] ?? ''));
    $etiqueta_origen = 'texto pegado';
    $analisis = lic_importar_analizar_texto($texto);
    if ($analisis['ok'] === false) {
        lic_importar_responder(false, $analisis['mensaje']);
    }
} else {
    // Validar la subida del archivo .lic
    if (!isset($_FILES['archivo_lic']) || !is_array($_FILES['archivo_lic'])) {
        lic_importar_responder(false, 'No se recibio ningun archivo.');
    }

    $archivo = $_FILES['archivo_lic'];
    $etiqueta_origen = (string)($archivo['name'] ?? 'archivo .lic');
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $motivos = array(
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamano permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamano permitido.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subio incompleto. Intente de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo.',
            UPLOAD_ERR_EXTENSION  => 'Una extension del servidor detuvo la subida.',
        );
        $codigo = (int)($archivo['error'] ?? 0);
        lic_importar_responder(false, $motivos[$codigo] ?? ('Error al subir el archivo (codigo ' . $codigo . ').'));
    }

    $tmp = (string)($archivo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        lic_importar_responder(false, 'El archivo recibido no es valido.');
    }

    $extension = strtolower((string)pathinfo($etiqueta_origen, PATHINFO_EXTENSION));
    if ($extension !== 'lic') {
        lic_importar_responder(false, 'El archivo debe tener la extension .lic');
    }

    $limite = 256 * 1024; // 256 KB: un .lic es un JSON cifrado muy pequeño
    if ((int)($archivo['size'] ?? 0) > $limite) {
        lic_importar_responder(false, 'El archivo .lic es demasiado grande.');
    }
}

// Leer y validar el contenido antes de tocar la licencia instalada.
$datos = ($modo === 'manual')
    ? licencia_leer_texto_lic($texto)
    : licencia_leer_archivo_lic($tmp);
if ($datos === null) {
    lic_importar_responder(false, 'La licencia no es valida: esta danada o no fue generada por este sistema.');
}

$huella_este = licencia_fingerprint_machine();
if ($datos['huella'] !== '' && !hash_equals($datos['huella'], $huella_este)) {
    lic_importar_responder(false, 'Esta licencia fue emitida para otro equipo. No se puede instalar en este computador.');
}

// Reemplazo: la licencia actual se borra primero porque el motor no
// permite importar sobre una licencia que ya este activa.
$tenia_licencia = licencia_activada();
$licencia_previa = licencia_leer();

if ($tenia_licencia) {
    licencia_borrar();
}

$instalada = ($modo === 'manual')
    ? licencia_importar_texto($texto)
    : licencia_importar_archivo($tmp);

if ($instalada === false) {
    // Si algo fallo al escribir, intentar dejar el sistema como estaba.
    if ($tenia_licencia && is_array($licencia_previa)) {
        @licencia_guardar(
            $licencia_previa['registro'],
            $licencia_previa['usuario'],
            $licencia_previa['serial'],
            $licencia_previa['fecha_activacion']
        );
        $mensaje = 'No se pudo instalar la nueva licencia. Se conservo la licencia anterior.';
    } else {
        $mensaje = 'No se pudo instalar la licencia. No se pudo escribir en el almacen del sistema.';
    }
    lic_importar_responder(false, $mensaje);
}

// Confirmacion final: la licencia debe quedar activa y vigente.
if (!licencia_activada()) {
    lic_importar_responder(false, 'La licencia se instalo, pero no quedo activa. Revise el contenido pegado o el archivo .lic.');
}

$estado = licencia_etiqueta_estado($instalada, '', true);

// Auditoria
if (function_exists('logAction')) {
    logAction(
        'importar_licencia',
        'licencia',
        'Licencia importada (' . $modo . '): ' . $instalada['registro']
            . ' | usuario: ' . $instalada['usuario']
            . ' | termino: ' . (isset($instalada['info']['nombre']) ? $instalada['info']['nombre'] : '')
            . ' | origen: ' . $etiqueta_origen
            . ($tenia_licencia ? ' | reemplazo la licencia anterior' : ' | primera instalacion'),
        array('serial' => licencia_formatear_serial($instalada['serial']), 'modo' => $modo),
        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'success',
        null,
        $_SESSION['auth_provider'] ?? 'local'
    );
}

lic_importar_responder(true, 'Licencia instalada correctamente.', array(
    'estado'    => $estado,
    'registro'  => $instalada['registro'],
    'usuario'   => $instalada['usuario'],
    'termino'   => isset($instalada['info']['nombre']) ? $instalada['info']['nombre'] : '',
    'reemplazo' => (bool)$tenia_licencia,
    'vence'     => licencia_vencimiento($instalada) === null
        ? 'Permanente'
        : date('d/m/Y', licencia_vencimiento($instalada)),
));
