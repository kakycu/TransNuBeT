<?php
// ============================================================
// exportar_historico.php - Entrega el Histórico de Operaciones
// para las exportaciones, respetando los MISMOS filtros que
// historico.php (parámetros GET).
//
// Formatos:
//   ?formato=csv   → descarga CSV (separador ';', UTF-8 con BOM)
//   ?formato=txt   → descarga TXT (tabulado)
//   ?formato=json  → devuelve los registros en JSON (lo consumen
//                    los exportadores cliente: Excel/Word/PDF/Imprimir)
//
// En todos los casos se exportan TODOS los campos de audit_logs y
// el conjunto COMPLETO de registros filtrados (sin paginación).
// Solo accesible para administradores (Admin y Soft).
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// No autenticados → login
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
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
        'historico',
        'Intento de exportar el Histórico de Operaciones sin permisos suficientes',
        ['rol_actual' => $rol_actual],
        (int)$_SESSION['user_id'],
        'failed',
        'Rol sin permiso: ' . $rol_actual,
        $_SESSION['auth_provider'] ?? 'local'
    );
    header('Location: historico.php');
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
// REPLICAR FILTROS DE historico.php (misma lógica)
// ============================================================
$f_fecha_desde  = trim((string)($_GET['fecha_desde'] ?? ''));
$f_fecha_hasta  = trim((string)($_GET['fecha_hasta'] ?? ''));
$f_usuario      = trim((string)($_GET['usuario'] ?? ''));
$f_provider     = trim((string)($_GET['auth_provider'] ?? ''));
$f_action       = isset($_GET['action_type']) && is_array($_GET['action_type'])
                  ? array_values(array_filter(array_map('strval', $_GET['action_type'])))
                  : [];
$f_module       = trim((string)($_GET['module'] ?? ''));
$f_status       = trim((string)($_GET['status'] ?? ''));
$f_ip           = trim((string)($_GET['ip'] ?? ''));
$f_texto        = trim((string)($_GET['texto'] ?? ''));
$f_rol          = trim((string)($_GET['rol'] ?? ''));
$f_usuario_id   = (int)($_GET['usuario_id'] ?? 0);

$providers_validos = ['local', 'google', 'system', 'anonimo'];
$statuses_validos  = ['success', 'failed'];
if ($f_provider !== '' && !in_array($f_provider, $providers_validos, true)) { $f_provider = ''; }
if ($f_status !== '' && !in_array($f_status, $statuses_validos, true)) { $f_status = ''; }
$f_action = array_values(array_intersect($f_action, array_keys(LOG_ACCIONES)));
$f_module = substr($f_module, 0, 50);
$f_rol    = substr($f_rol, 0, 50);
if ($f_usuario_id < 0) { $f_usuario_id = 0; }

$where  = [];
$params = [];
$n = 0;

if ($f_fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_fecha_desde)) {
    $n++;
    $where[]  = 'created_at >= :fd' . $n;
    $params['fd' . $n] = $f_fecha_desde . ' 00:00:00';
}
if ($f_fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_fecha_hasta)) {
    $n++;
    $where[]  = 'created_at < DATE_ADD(:fh' . $n . ', INTERVAL 1 DAY)';
    $params['fh' . $n] = $f_fecha_hasta . ' 00:00:00';
}
if ($f_usuario !== '') {
    $n++;
    $where[]  = '(username LIKE :usn' . $n . ' OR user_email LIKE :use' . $n . ')';
    $params['usn' . $n] = '%' . $f_usuario . '%';
    $params['use' . $n] = '%' . $f_usuario . '%';
}
if ($f_provider !== '') {
    $n++;
    $where[]  = 'auth_provider = :prov' . $n;
    $params['prov' . $n] = $f_provider;
}
if (!empty($f_action)) {
    $in = [];
    foreach ($f_action as $a) {
        $n++;
        $in[] = ':act' . $n;
        $params['act' . $n] = $a;
    }
    $where[] = 'action_type IN (' . implode(', ', $in) . ')';
}
if ($f_module !== '') {
    $n++;
    $where[]  = 'module = :mod' . $n;
    $params['mod' . $n] = $f_module;
}
if ($f_status !== '') {
    $n++;
    $where[]  = 'status = :est' . $n;
    $params['est' . $n] = $f_status;
}
if ($f_ip !== '') {
    $n++;
    $where[]  = 'ip_address LIKE :ip' . $n;
    $params['ip' . $n] = '%' . $f_ip . '%';
}
if ($f_texto !== '') {
    $n++;
    $where[]  = '(description LIKE :txd' . $n . ' OR details LIKE :txj' . $n . ')';
    $params['txd' . $n] = '%' . $f_texto . '%';
    $params['txj' . $n] = '%' . $f_texto . '%';
}
if ($f_rol !== '') {
    $n++;
    $where[]  = "COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) = :rol" . $n;
    $params['rol' . $n] = $f_rol;
}
if ($f_usuario_id > 0) {
    $n++;
    $where[]  = 'cu.id = :uid' . $n;
    $params['uid' . $n] = $f_usuario_id;
}

$where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

// ============================================================
// ORDENAMIENTO (misma whitelist que historico.php)
// ============================================================
$cols_orden = ['id', 'created_at', 'username', 'action_type'];
$col_orden  = (string)($_GET['col'] ?? 'id');
if (!in_array($col_orden, $cols_orden, true)) { $col_orden = 'id'; }
$orden = strtoupper((string)($_GET['orden'] ?? 'DESC'));
if ($orden !== 'ASC' && $orden !== 'DESC') { $orden = 'DESC'; }

// ============================================================
// CONSULTA (TODOS los campos, sin límite)
// ============================================================
$sql = "SELECT t1.id, t1.user_id, t1.username, t1.user_email, t1.auth_provider, t1.action_type,
               t1.module, t1.description, t1.details, t1.ip_address, t1.user_agent,
               t1.request_method, t1.request_url, t1.status, t1.error_message,
               t1.hash_chain, t1.created_at,
               CONCAT_WS(' ', NULLIF(cu.nombre, ''), NULLIF(cu.apellidos, '')) AS usuario_nombre,
               COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) AS usuario_rol
        FROM audit_logs t1
        LEFT JOIN clasif_usuarios cu ON cu.id = t1.user_id
        LEFT JOIN clasif_rol cr ON cr.id = cu.rol_id
        $where_sql
        ORDER BY t1.$col_orden $orden, t1.id $orden";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$total = count($registros);

// ============================================================
// AUDITAR LA EXPORTACIÓN
// ============================================================
logAction(
    'exportar_reporte',
    'historico',
    'Exportación del Histórico de Operaciones (' . strtoupper($destino) . ')',
    ['formato' => $formato, 'destino' => $destino, 'registros' => $total],
    (int)$_SESSION['user_id'],
    'success',
    null,
    $_SESSION['auth_provider'] ?? 'local'
);

// ============================================================
// SALIDA: JSON
// ============================================================
if ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode([
        'success'       => true,
        'generado'      => date('d/m/Y h:i:s A'),
        'usuario'       => $_SESSION['username'] ?? $_SESSION['user_nombre'] ?? 'Sistema',
        'exportado_por' => $exportado_por,
        'total'         => $total,
        'registros'     => $registros,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// SALIDA: CSV / TXT (todos los campos)
// ============================================================
$encabezados = [
    'N°', 'ID', 'ID Usuario', 'Usuario', 'Rol', 'Nombre y apellidos', 'Email', 'Proveedor', 'Acción',
    'Módulo', 'Descripción', 'Detalles (JSON)', 'IP', 'Navegador (User-Agent)',
    'Método', 'URL', 'Estado', 'Error', 'Hash (cadena)', 'Fecha/Hora',
];

$filas = [];
$i = 1;
foreach ($registros as $reg) {
    $filas[] = [
        $i++,
        $reg['id'],
        $reg['user_id'] === null ? '' : $reg['user_id'],
        ($reg['username'] ?? '') !== '' ? $reg['username'] : 'Sistema',
        $reg['usuario_rol'] ?? '',
        $reg['usuario_nombre'] ?? '',
        $reg['user_email'] ?? '',
        $reg['auth_provider'] ?? '',
        $reg['action_type'] ?? '',
        $reg['module'] ?? '',
        $reg['description'] ?? '',
        $reg['details'] ?? '',
        $reg['ip_address'] ?? '',
        $reg['user_agent'] ?? '',
        $reg['request_method'] ?? '',
        $reg['request_url'] ?? '',
        etiquetaEstadoLog($reg['status'] ?? ''),
        $reg['error_message'] ?? '',
        $reg['hash_chain'] ?? '',
        date('d/m/Y h:i:s A', strtotime($reg['created_at'])),
    ];
}

// Encabezado informativo (título, empresa y quién exporta)
$meta_export = [
    'HISTÓRICO DE OPERACIONES - ' . $sistema_nombre,
    'EMPRESA/ENTIDAD: ' . $empresa_nombre,
    'Generado: ' . date('d/m/Y h:i:s A') . '   ·   Exportado por: ' . $exportado_por . '   ·   Total de registros: ' . $total,
];

if ($formato === 'txt') {
    $salida = "\xEF\xBB\xBF";
    $salida .= implode("\r\n", $meta_export) . "\r\n\r\n";
    $salida .= implode("\t", $encabezados) . "\r\n";
    foreach ($filas as $fila) {
        $salida .= implode("\t", array_map(function ($v) {
            return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], (string)$v);
        }, $fila)) . "\r\n";
    }
    $nombre_archivo = 'historico_operaciones_' . date('Ymd_His') . '.txt';
    $mime = 'text/plain; charset=UTF-8';
} else {
    $salida = "\xEF\xBB\xBF"; // BOM UTF-8
    $campoCsv = function ($v) {
        return '"' . str_replace('"', '""', (string)$v) . '"';
    };
    $salida .= implode("\r\n", array_map($campoCsv, $meta_export)) . "\r\n\r\n";
    $salida .= implode(';', array_map($campoCsv, $encabezados)) . "\r\n";
    foreach ($filas as $fila) {
        $salida .= implode(';', array_map($campoCsv, $fila)) . "\r\n";
    }
    $nombre_archivo = 'historico_operaciones_' . date('Ymd_His') . '.csv';
    $mime = 'text/csv; charset=UTF-8';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($salida));
echo $salida;
exit;
