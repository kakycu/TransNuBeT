<?php
// ============================================================
// eliminar_historico.php - Borrado del Histórico de Operaciones
// ------------------------------------------------------------
// Endpoint JSON para el botón flotante "Eliminar todo" y el
// modal de solicitud de eliminación de modules/historico.php.
//
// Acciones (POST):
//   * eliminar_todo    -> vacía por completo audit_logs
//   * eliminar_antiguos-> borra los registros con más de N días
//                         y re-encadena la cadena de hashes
//   * eliminar_rango   -> borra un rango de fechas y re-encadena
//   * eliminar_registro-> borra un registro concreto y re-encadena
//
// eliminar_todo: antes de borrar exporta todos los registros a
// nominas/logs/ (logs-sisgestonHistorico-fecha-hora.log) y reinicia
// el autoincrement de la tabla a 1.
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// CONTROL DE ACCESO
// ------------------------------------------------------------
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida. Vuelva a iniciar sesión.']);
    exit;
}

$rol_actual  = permiso_rol_codigo();
$user_id     = (int)$_SESSION['user_id'];
$auth_provider = $_SESSION['auth_provider'] ?? 'local';

if (!in_array($rol_actual, ['Admin', 'Soft'], true)) {
    logAction(
        'acceso_denegado',
        'historico',
        'Intento de eliminar el Histórico de Operaciones sin permisos suficientes',
        ['rol_actual' => $rol_actual],
        $user_id,
        'failed',
        'Rol sin permiso: ' . $rol_actual,
        $auth_provider
    );
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar el histórico.']);
    exit;
}

// ------------------------------------------------------------
// RE-ENCADENADO DE LA CADENA DE HASHES
// (evita falsos positivos de "registro alterado" tras una purga)
// ------------------------------------------------------------
if (!function_exists('historicoReencadenar')) {
    function historicoReencadenar(PDO $pdo) {
        $prevId = '';
        $filas  = $pdo->query('SELECT * FROM audit_logs ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $upd    = $pdo->prepare('UPDATE audit_logs SET hash_chain = ? WHERE id = ?');

        foreach ($filas as $fila) {
            $canonico = implode('|', [
                $fila['user_id'] === null ? 'NULL' : (string)$fila['user_id'],
                ($fila['username'] ?? '') !== ''       ? $fila['username']  : 'NULL',
                ($fila['user_email'] ?? '') !== ''     ? $fila['user_email'] : 'NULL',
                $fila['auth_provider'],
                $fila['action_type'],
                $fila['module'],
                $fila['description'],
                canonicalDetallesAuditoria($fila['details']),
                $fila['ip_address'],
                ($fila['user_agent'] ?? '') !== ''     ? $fila['user_agent']  : 'NULL',
                ($fila['request_method'] ?? '') !== '' ? $fila['request_method'] : 'NULL',
                ($fila['request_url'] ?? '') !== ''    ? $fila['request_url'] : 'NULL',
                $fila['status'],
                $fila['error_message'] !== null ? $fila['error_message'] : 'NULL',
                $fila['created_at'],
            ]);
            $entrada = ($prevId === '' ? '' : $prevId . '|') . $canonico;
            $upd->execute([hash('sha256', $entrada), $fila['id']]);
            $prevId = (string)$fila['id'];
        }

        return count($filas);
    }
}

$accion = (string)($_POST['accion'] ?? '');

try {
    switch ($accion) {

        // ----------------------------------------------------
        // Eliminar TODO el histórico
        // ----------------------------------------------------
        case 'eliminar_todo':
            $captcha  = (string)($_POST['captcha'] ?? '');
            $esperado = (string)($_SESSION['captcha_borrar_historico'] ?? '');
            if ($esperado === '' || $captcha === '' || !hash_equals($esperado, $captcha)) {
                echo json_encode(['success' => false, 'message' => 'El código de confirmación no es válido. No se eliminó nada.']);
                exit;
            }
            unset($_SESSION['captcha_borrar_historico']);

            // ----------------------------------------------------
            // 1) Exportar TODOS los registros a nominas/logs/ antes
            //    de borrarlos. Si el respaldo falla, NO se elimina nada.
            // ----------------------------------------------------
            $filas = $pdo->query('SELECT * FROM audit_logs ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

            $dir_logs = __DIR__ . '/../logs';
            if (!is_dir($dir_logs)) {
                @mkdir($dir_logs, 0755, true);
            }
            if (!is_dir($dir_logs)) {
                echo json_encode(['success' => false, 'message' => 'No se pudo crear la carpeta de respaldo de logs. No se eliminó nada.']);
                exit;
            }

            $archivo = $dir_logs . '/logs-sisgestonHistorico-' . date('dmY') . '-' . date('His') . '.log';

            $user_login  = trim((string)($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''));
            $user_nombre = trim((string)($_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? ''));

            $contenido  = '// Logs Eliminados fecha: ' . date('d/m/Y') . '    Hora: ' . date('H:i:s') . "\n";
            $contenido .= '// Usuario que elimino: user: ' . ($user_login !== '' ? $user_login : '-') .
                          '    y nombre completo del user: ' . ($user_nombre !== '' ? $user_nombre : '-') . "\n";
            $contenido .= "// --------------------------------------------------------------------------------------------------\n";

            foreach ($filas as $fila) {
                $detalles = '';
                if ($fila['details'] !== null && $fila['details'] !== '') {
                    $json = json_encode($fila['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $detalles = $json === false ? '' : $json;
                }
                $detalles = str_replace(["\r", "\n"], ' ', $detalles);
                $desc     = str_replace(["\r", "\n"], ' ', (string)$fila['description']);
                $error_msg = $fila['error_message'] !== null ? str_replace(["\r", "\n"], ' ', $fila['error_message']) : '';

                $contenido .= sprintf(
                    "// #%d | %s | [%s] | %s | %s (%s) | accion: %s | modulo: %s | IP: %s | descripcion: %s | detalles: %s | hash_chain: %s%s\n",
                    (int)$fila['id'],
                    $fila['created_at'],
                    $fila['status'],
                    $fila['auth_provider'],
                    (string)($fila['username'] ?? '-'),
                    (string)($fila['user_email'] ?? '-'),
                    $fila['action_type'],
                    $fila['module'],
                    $fila['ip_address'],
                    $desc,
                    $detalles !== '' ? $detalles : '-',
                    (string)($fila['hash_chain'] ?? '-'),
                    $error_msg !== '' ? ' | error: ' . $error_msg : ''
                );
            }

            if (file_put_contents($archivo, $contenido, LOCK_EX) === false) {
                echo json_encode(['success' => false, 'message' => 'No se pudo escribir el respaldo de los logs. No se eliminó nada.']);
                exit;
            }

            $total = count($filas);

            // ----------------------------------------------------
            // 2) Vaciar la tabla y reiniciar el autoincrement a 1
            //    (el respaldo en archivo queda como constancia).
            // ----------------------------------------------------
            $pdo->exec('DELETE FROM audit_logs');
            $pdo->exec('ALTER TABLE audit_logs AUTO_INCREMENT = 1');

            echo json_encode([
                'success'    => true,
                'eliminados' => $total,
                'archivo'    => basename($archivo),
                'message'    => 'Histórico eliminado por completo y respaldado.',
            ]);
            break;

        // ----------------------------------------------------
        // Eliminar registros con más de N días
        // ----------------------------------------------------
        case 'eliminar_antiguos':
            $dias = max(1, (int)($_POST['dias'] ?? 365));
            $ahora = new DateTime('now');
            $ahora->sub(new DateInterval('P' . $dias . 'D'));
            $corte = $ahora->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
            $stmt->execute([$corte]);
            $eliminados  = $stmt->rowCount();
            $conservados = historicoReencadenar($pdo);

            logAction(
                'purgar_logs_antiguos',
                'historico',
                'Limpieza de registros anteriores a ' . $dias . ' días',
                ['eliminados' => $eliminados, 'conservados' => $conservados, 'corte' => $corte],
                $user_id,
                'success',
                null,
                $auth_provider
            );

            echo json_encode([
                'success'     => true,
                'eliminados'  => $eliminados,
                'conservados' => $conservados,
                'message'     => 'Limpieza completada.',
            ]);
            break;

        // ----------------------------------------------------
        // Eliminar un rango de fechas
        // ----------------------------------------------------
        case 'eliminar_rango':
            $desde = trim((string)($_POST['fecha_desde'] ?? ''));
            $hasta = trim((string)($_POST['fecha_hasta'] ?? ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                echo json_encode(['success' => false, 'message' => 'Debe indicar un rango de fechas válido (desde y hasta).']);
                exit;
            }
            if ($desde > $hasta) {
                echo json_encode(['success' => false, 'message' => 'La fecha "desde" no puede ser mayor que la fecha "hasta".']);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)');
            $stmt->execute([$desde . ' 00:00:00', $hasta . ' 00:00:00']);
            $eliminados  = $stmt->rowCount();
            $conservados = historicoReencadenar($pdo);

            logAction(
                'eliminar_historico_parcial',
                'historico',
                'Eliminación de registros del histórico entre ' . $desde . ' y ' . $hasta,
                ['eliminados' => $eliminados, 'conservados' => $conservados, 'desde' => $desde, 'hasta' => $hasta],
                $user_id,
                'success',
                null,
                $auth_provider
            );

            echo json_encode([
                'success'     => true,
                'eliminados'  => $eliminados,
                'conservados' => $conservados,
                'message'     => 'Registros del rango eliminados.',
            ]);
            break;

        // ----------------------------------------------------
        // Eliminar UN registro concreto (re-encadena el resto)
        // ----------------------------------------------------
        case 'eliminar_registro':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Registro no válido.']);
                exit;
            }

            $stmtSel = $pdo->prepare('SELECT id, action_type, module, description, username FROM audit_logs WHERE id = ?');
            $stmtSel->execute([$id]);
            $reg = $stmtSel->fetch(PDO::FETCH_ASSOC);

            if (!$reg) {
                echo json_encode(['success' => false, 'message' => 'El registro indicado ya no existe.']);
                exit;
            }

            $stmtDel = $pdo->prepare('DELETE FROM audit_logs WHERE id = ?');
            $stmtDel->execute([$id]);
            $eliminados  = $stmtDel->rowCount();
            $conservados = historicoReencadenar($pdo);

            logAction(
                'eliminar_historico_registro',
                'historico',
                'Eliminación de un registro del histórico (# ' . $id . ')',
                [
                    'id_eliminado'      => $id,
                    'accion_original'   => $reg['action_type'],
                    'modulo'            => $reg['module'],
                    'usuario_original'  => $reg['username'],
                    'descripcion'       => $reg['description'],
                    'conservados'       => $conservados,
                ],
                $user_id,
                'success',
                null,
                $auth_provider
            );

            echo json_encode([
                'success'     => true,
                'eliminados'  => $eliminados,
                'conservados' => $conservados,
                'message'     => 'Registro eliminado correctamente.',
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            break;
    }
} catch (Throwable $e) {
    logAction(
        'error_sistema',
        'historico',
        'Error al eliminar registros del histórico',
        ['accion' => $accion],
        $user_id,
        'failed',
        $e->getMessage(),
        $auth_provider
    );
    echo json_encode(['success' => false, 'message' => 'Error al procesar la eliminación: ' . $e->getMessage()]);
}
