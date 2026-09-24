<?php
// ============================================================
// limpiar_logs.php - Depuración de registros de auditoría
// ------------------------------------------------------------
// Elimina los registros de audit_logs con más de 365 días y
// REEDIFICA la cadena de hashes (hash_chain) de los registros
// restantes, para que historico.php no marque falsos positivos
// tras una purga legítima.
//
// USO (CLI / cron):
//   php.exe limpiar_logs.php             -> purga >365 días
//   php.exe limpiar_logs.php --days=90   -> purga >90 días (pruebas)
//
// USO (web, requiere sesión Admin/Soft):
//   /nominas/limpiar_logs.php?days=365
//
// SIN Composer ni librerías externas.
// ============================================================

require_once __DIR__ . '/config.php';              // constantes de BD
require_once __DIR__ . '/logger.php';              // canonicalDetallesAuditoria()

$es_cli = (PHP_SAPI === 'cli');

if ($es_cli) {
    // Conectar PDO directamente (el proyecto usa $pdo global)
    $pdo = null;
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        fwrite(STDERR, '[auditoria] No se pudo conectar a MySQL: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
} else {
    // En web: validar licencia, sesión y rol antes de continuar
    require_once __DIR__ . '/includes/licencia.php';
    if (!licencia_activada()) {
        header('Location: licencia.php');
        exit;
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $rol_actual = permiso_rol_codigo();
    if (!in_array($rol_actual, ['Admin', 'Soft'], true)) {
        logAction('acceso_denegado', 'auditoria', 'Intento de ejecutar la limpieza de logs sin permisos', ['rol_actual' => $rol_actual], (int)$_SESSION['user_id'], 'failed', 'Rol sin permiso: ' . $rol_actual, $_SESSION['auth_provider'] ?? 'local');
        header('Location: modules/historico.php');
        exit;
    }
}

// Días a conservar (por defecto 365)
if ($es_cli) {
    $dias = 365;
    foreach ($argv as $arg) {
        if (preg_match('/^--days=(\d+)$/i', $arg, $m)) { $dias = max(1, (int)$m[1]); }
    }
} else {
    $dias = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 365;
}

function salidaCli($mensaje) {
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

// ============================================================
// 1. CONTAR Y ELIMINAR REGISTROS ANTIGUOS
// ============================================================
$ahora = new DateTime('now');
$ahora->sub(new DateInterval('P' . $dias . 'D'));
$corte = $ahora->format('Y-m-d H:i:s');

$stmtDel = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
$stmtDel->execute([$corte]);
$eliminados = $stmtDel->rowCount();

// ============================================================
// 2. REEDIFICAR LA CADENA DE HASHES (para no marcar falsos positivos)
// ============================================================
$rebuild = 0;
$prevId  = '';
$filas = $pdo->query('SELECT * FROM audit_logs ORDER BY id ASC')->fetchAll();

$updHash = $pdo->prepare('UPDATE audit_logs SET hash_chain = ? WHERE id = ?');
foreach ($filas as $fila) {
    $canonico = implode('|', [
        $fila['user_id'] === null ? 'NULL' : (string)$fila['user_id'],
        ($fila['username'] ?? '') !== ''      ? $fila['username']  : 'NULL',
        ($fila['user_email'] ?? '') !== ''    ? $fila['user_email'] : 'NULL',
        $fila['auth_provider'],
        $fila['action_type'],
        $fila['module'],
        $fila['description'],
        canonicalDetallesAuditoria($fila['details']),
        $fila['ip_address'],
        ($fila['user_agent'] ?? '') !== ''    ? $fila['user_agent']  : 'NULL',
        ($fila['request_method'] ?? '') !== ''? $fila['request_method'] : 'NULL',
        ($fila['request_url'] ?? '') !== ''   ? $fila['request_url'] : 'NULL',
        $fila['status'],
        $fila['error_message'] !== null ? $fila['error_message'] : 'NULL',
        $fila['created_at'],
    ]);
    $entrada = ($prevId === '' ? '' : $prevId . '|') . $canonico;
    $updHash->execute([hash('sha256', $entrada), $fila['id']]);
    $prevId = (string)$fila['id'];
    $rebuild++;
}

// ============================================================
// 3. AUDITAR LA PROPIA LIMPIEZA
// ============================================================
logAction(
    'purgar_logs_antiguos',
    'auditoria',
    'Limpieza de registros anteriores a ' . $dias . ' días',
    ['eliminados' => $eliminados, 'conservados' => $rebuild, 'corte' => $corte],
    null,
    'success',
    null,
    'system'
);

// ============================================================
// 4. RESULTADO
// ============================================================
if ($es_cli) {
    salidaCli("[auditoria] Registros eliminados (>{$dias} días): {$eliminados}");
    salidaCli("[auditoria] Registros conservados con hash_chain rebuidado: {$rebuild}");
    salidaCli("[auditoria] Limpieza completada correctamente.");
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'eliminados' => $eliminados,
        'conservados' => $rebuild,
        'mensaje' => 'Limpieza de logs completada.'
    ]);
    exit;
}