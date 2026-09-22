<?php
// ============================================================
// exportar_configuracion.php - Entrega la CONFIGURACIÓN del
// sistema para las exportaciones desde configuracion.php.
//
// Formatos:
//   ?formato=csv   → descarga CSV (separador ';', UTF-8 con BOM)
//   ?formato=txt   → descarga TXT (tabulado)
//   ?formato=json  → devuelve los datos en JSON (lo consumen
//                    los exportadores cliente: Excel/PDF/Imprimir)
//
// Contenido (TODOS los datos de configuración):
//   - Configuración General  (configuracion_general)
//   - Rangos de Impuesto     (configuracion_rangos_impuesto vigentes)
//   - Tasas de Contribución  (configuracion_tasas)
// Solo accesible para administradores (Admin y Soft).
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../includes/permisos.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// No autenticados → login
if (empty($_SESSION['logged_in']) && empty($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol_actual = permiso_rol_codigo();
$roles_permitidos = ['Admin', 'Soft'];

// ============================================================
// IDENTIDAD DEL OPERADOR QUE EXPORTA
// Formato: "Nombre y apellidos (Rol)"  p.ej. "Periquito Pérez Sosa (Admin)"
// ============================================================
$exportador_nombre = trim((string)($_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? $_SESSION['username'] ?? ''));
if ($exportador_nombre === '') { $exportador_nombre = 'Usuario'; }
$exportador_rol = trim((string)($_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['user_rol'] ?? $rol_actual));
$exportado_por  = $exportador_nombre . ($exportador_rol !== '' ? ' (' . $exportador_rol . ')' : '');

// Encabezado común de los documentos/archivos exportados
$sistema_nombre = 'Sistema SisGesNom®';
$empresa_nombre = defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom';

if (!in_array($rol_actual, $roles_permitidos, true)) {
    logAction(
        'acceso_denegado',
        'configuracion',
        'Intento de exportar la Configuración del Sistema sin permisos suficientes',
        ['rol_actual' => $rol_actual],
        (int)($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0),
        'failed',
        'Rol sin permiso: ' . $rol_actual,
        $_SESSION['auth_provider'] ?? 'local'
    );
    header('Location: configuracion.php');
    exit;
}

// ============================================================
// FORMATO SOLICITADO
// ============================================================
$formato = strtolower(trim((string)($_GET['formato'] ?? 'csv')));
if (!in_array($formato, ['csv', 'txt', 'json'], true)) {
    $formato = 'csv';
}
// Destino real de la exportación (para la auditoría): excel/word/pdf/print/csv/txt
$destino = strtolower(trim((string)($_GET['destino'] ?? $formato)));
if (!in_array($destino, ['excel', 'word', 'pdf', 'print', 'csv', 'txt'], true)) {
    $destino = $formato;
}

// ============================================================
// DATOS A EXPORTAR
// ============================================================

// 1) Configuración General (todas las filas clave/valor)
try {
    $stmt = $pdo->query(
        "SELECT parametro, valor, tipo_dato, descripcion, fecha_modificacion, usuario_modifico
           FROM configuracion_general
          ORDER BY id ASC"
    );
    $config_gral = $stmt->fetchAll();
} catch (PDOException $e) {
    $config_gral = [];
}

// Traducción del tipo de dato almacenado a etiqueta legible
$tipo_etiqueta = [
    'entero'   => 'Entero',
    'decimal'  => 'Decimal',
    'texto'    => 'Texto',
    'fecha'    => 'Fecha',
    'booleano' => 'Booleano',
];

// 2) Rangos de Impuesto (vigencia vigente, igual que configuracion.php)
$rangos_impuesto = [];
try {
    $stmt = $pdo->query(
        "SELECT * FROM configuracion_rangos_impuesto
          WHERE fecha_vigencia = (SELECT MAX(fecha_vigencia) FROM configuracion_rangos_impuesto)
          ORDER BY desde ASC"
    );
    $rangos_impuesto = $stmt->fetchAll();
} catch (PDOException $e) {
    $rangos_impuesto = [];
}
$fecha_vigencia_rangos = !empty($rangos_impuesto) ? $rangos_impuesto[0]['fecha_vigencia'] : '';

// 3) Tasas de Contribución
$tasas = [];
try {
    $stmt = $pdo->query(
        "SELECT * FROM configuracion_tasas
          ORDER BY fecha_vigencia DESC, id ASC"
    );
    $tasas = $stmt->fetchAll();
} catch (PDOException $e) {
    $tasas = [];
}

$title = 'CONFIGURACIÓN DEL SISTEMA';
$total_filas = count($config_gral) + count($rangos_impuesto) + count($tasas);

// ============================================================
// MODELO DE SECCIONES (filas ya formateadas para cada formato)
// ============================================================
$secciones = [];
$i = 1;

// Configuración general → columnas Parámetro | Valor | Tipo | Descripción
$filas_gral = [];
foreach ($config_gral as $p) {
    $tipo = $tipo_etiqueta[trim((string)$p['tipo_dato'])] ?? trim((string)$p['tipo_dato']);
    $filas_gral[] = [
        $i++,
        $p['parametro'],
        $p['valor'],
        $tipo,
        $p['descripcion'],
    ];
}
$secciones[] = [
    'id'       => 'general',
    'titulo'   => 'Configuración General',
    'columnas' => ['N°', 'Parámetro', 'Valor', 'Tipo', 'Descripción'],
    'filas'    => $filas_gral,
];

// Rangos de impuesto
$filas_rangos = [];
$j = 1;
foreach ($rangos_impuesto as $r) {
    $filas_rangos[] = [
        $j++,
        number_format((float)$r['desde'], 2, '.', ''),
        $r['hasta'] === null ? '' : number_format((float)$r['hasta'], 2, '.', ''),
        number_format((float)$r['tasa'] * 100, 2, ',', '') . ' %',
        number_format((float)$r['monto_fijo'], 2, '.', ''),
        date('d/m/Y', strtotime($r['fecha_vigencia'])),
        trim((string)$r['descripcion']),
    ];
}
$secciones[] = [
    'id'       => 'rangos',
    'titulo'   => 'Rangos de Impuesto' . ($fecha_vigencia_rangos !== '' ? ' (Vigencia: ' . date('d/m/Y', strtotime($fecha_vigencia_rangos)) . ')' : ''),
    'columnas' => ['N°', 'Desde', 'Hasta', 'Tasa', 'Monto Fijo', 'Fecha Vigencia', 'Descripción'],
    'filas'    => $filas_rangos,
];

// Tasas de contribución
$filas_tasas = [];
$k = 1;
foreach ($tasas as $t) {
    $filas_tasas[] = [
        $k++,
        trim((string)$t['nombre_tasa']),
        number_format((float)$t['valor'], 2, '.', ''),
        date('d/m/Y', strtotime($t['fecha_vigencia'])),
        trim((string)$t['descripcion']),
    ];
}
$secciones[] = [
    'id'       => 'tasas',
    'titulo'   => 'Tasas de Contribución',
    'columnas' => ['N°', 'Nombre de la Tasa', 'Valor', 'Fecha Vigencia', 'Descripción'],
    'filas'    => $filas_tasas,
];

// ============================================================
// AUDITAR LA EXPORTACIÓN
// ============================================================
logAction(
    'exportar_reporte',
    'configuracion',
    'Exportación de la Configuración del Sistema (' . strtoupper($destino) . ')',
    ['formato' => $formato, 'destino' => $destino, 'filas' => $total_filas],
    (int)($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0),
    'success',
    null,
    $_SESSION['auth_provider'] ?? 'local'
);

// ============================================================
// SALIDA: JSON (para exportadores del cliente)
// ============================================================
if ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode([
        'success'       => true,
        'generado'      => date('d/m/Y h:i:s A'),
        'usuario'       => $_SESSION['username'] ?? $_SESSION['user_nombre'] ?? 'Sistema',
        'exportado_por' => $exportado_por,
        'sistema'       => $sistema_nombre,
        'empresa'       => $empresa_nombre,
        'titulo'        => $title,
        'total'         => $total_filas,
        'secciones'     => $secciones,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// SALIDA: CSV / TXT (todas las secciones)
// ============================================================
$meta_export = [
    $title . ' - ' . $sistema_nombre,
    'EMPRESA/ENTIDAD: ' . $empresa_nombre,
    'Generado: ' . date('d/m/Y h:i:s A') . '   ·   Exportado por: ' . $exportado_por . '   ·   Total de filas: ' . $total_filas,
];

if ($formato === 'txt') {
    $salida = "\xEF\xBB\xBF";
    $salida .= implode("\r\n", $meta_export) . "\r\n\r\n";
    $primera_seccion = true;
    foreach ($secciones as $sec) {
        if (!$primera_seccion) { $salida .= "\r\n"; }
        $primera_seccion = false;
        $salida .= '==============================' . "\r\n";
        $salida .= $sec['titulo'] . "\r\n";
        $salida .= '==============================' . "\r\n";
        $salida .= implode("\t", $sec['columnas']) . "\r\n";
        foreach ($sec['filas'] as $fila) {
            $salida .= implode("\t", array_map(function ($v) {
                return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], (string)$v);
            }, $fila)) . "\r\n";
        }
    }
    $nombre_archivo = 'configuracion_del_sistema_' . date('Ymd_His') . '.txt';
    $mime = 'text/plain; charset=UTF-8';
} else {
    $salida = "\xEF\xBB\xBF"; // BOM UTF-8
    $campoCsv = function ($v) {
        return '"' . str_replace('"', '""', (string)$v) . '"';
    };
    $salida .= implode("\r\n", array_map($campoCsv, $meta_export)) . "\r\n\r\n";
    $primera_seccion = true;
    foreach ($secciones as $sec) {
        if (!$primera_seccion) { $salida .= "\r\n"; }
        $primera_seccion = false;
        $salida .= implode(';', array_map($campoCsv, [$sec['titulo']])) . "\r\n";
        $salida .= implode(';', array_map($campoCsv, $sec['columnas'])) . "\r\n";
        foreach ($sec['filas'] as $fila) {
            $salida .= implode(';', array_map($campoCsv, $fila)) . "\r\n";
        }
    }
    $nombre_archivo = 'configuracion_del_sistema_' . date('Ymd_His') . '.csv';
    $mime = 'text/csv; charset=UTF-8';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($salida));
echo $salida;
exit;