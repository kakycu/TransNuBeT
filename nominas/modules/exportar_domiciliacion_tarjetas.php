<?php
// modules/exportar_domiciliacion_tarjetas.php
require_once '../config/database.php';
require_once '../includes/funciones.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/domiciliacion_dbf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'mensaje' => 'Sesion expirada. Vuelva a iniciar sesion.']);
    exit();
}

if (!permiso_puede('domiciliacion_tarjetas', 'exportar')) {
    permiso_denegar_acceso('Exportar domiciliacion de tarjetas');
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Metodo no permitido.']);
    exit();
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [$ids];

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function($v) { return $v > 0; })));

// Plantilla en blanco: misma estructura de 16 campos, sin registros.
if (($_POST['accion'] ?? '') === 'plantilla') {
    $carpeta = __DIR__ . '/exports/';
    if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

    $archivoSalida = domiciliacion_generar_dbf($carpeta . 'domiciliacion_tarjetas_plantilla.dbf', []);

    if (!$archivoSalida || !file_exists($archivoSalida)) {
        echo json_encode(['success' => false, 'mensaje' => 'Error al escribir el archivo DBF.']);
        exit();
    }

    logAction('exportar_domiciliacion_tarjetas', 'exportar_domiciliacion_tarjetas',
        'Plantilla DBF en blanco de domiciliacion de tarjetas',
        ['archivo' => basename($archivoSalida)],
        null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

    echo json_encode([
        'success'    => true,
        'plantilla'  => true,
        'archivo'    => basename($archivoSalida),
        'ruta'       => str_replace('\\', '/', $archivoSalida),
        'registros'  => 0,
        'descarga'   => 'exports/' . basename($archivoSalida)
    ]);
    exit();
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'mensaje' => 'Debe seleccionar al menos un trabajador.']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT id, codigo, ci, nombres, primer_apellido, segundo_apellido
    FROM trabajadores
    WHERE id IN ($placeholders)
    ORDER BY codigo ASC
");
$stmt->execute($ids);
$seleccionados = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($seleccionados)) {
    echo json_encode(['success' => false, 'mensaje' => 'Los trabajadores seleccionados no existen.']);
    exit();
}

$rechazados = [];
$registros  = [];
foreach ($seleccionados as $t) {
    $errores = domiciliacion_validar_trabajador($t);
    if (!empty($errores)) {
        $rechazados[] = ['codigo' => $t['codigo'], 'ci' => $t['ci'], 'errores' => $errores];
        continue;
    }
    $registros[] = domiciliacion_preparar_registro($t);
}

if (empty($registros)) {
    echo json_encode([
        'success'   => false,
        'mensaje'   => 'Ningun trabajador seleccionado cumple con los datos requeridos (nombres, primer apellido, segundo apellido y carnet valido).',
        'rechazados' => $rechazados
    ]);
    exit();
}

$carpeta = __DIR__ . '/exports/';
if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

$nombreArchivo   = 'domiciliacion_tarjetas_' . date('Ymd_His');
$archivoSalida = domiciliacion_generar_dbf($carpeta . $nombreArchivo . '.dbf', $registros);

if (!$archivoSalida || !file_exists($archivoSalida)) {
    logAction('exportar_domiciliacion_tarjetas', 'exportar_domiciliacion_tarjetas',
        'Fallo al generar el archivo DBF de domiciliacion de tarjetas',
        ['registros' => count($registros)], null, 'error',
        'No se pudo escribir el archivo DBF', $_SESSION['auth_provider'] ?? 'local');

    echo json_encode(['success' => false, 'mensaje' => 'Error al escribir el archivo DBF.']);
    exit();
}

logAction('exportar_domiciliacion_tarjetas', 'exportar_domiciliacion_tarjetas',
    'Exportacion de domiciliacion de tarjetas al banco',
    [
        'archivo'     => basename($archivoSalida),
        'registros'   => count($registros),
        'descartados' => count($rechazados)
    ],
    null, 'success', null, $_SESSION['auth_provider'] ?? 'local');

echo json_encode([
    'success'    => true,
    'archivo'    => basename($archivoSalida),
    'ruta'       => str_replace('\\', '/', $archivoSalida),
    'registros'  => count($registros),
    'rechazados' => count($rechazados),
    'detalle'    => $rechazados,
    'descarga'   => 'exports/' . basename($archivoSalida)
]);