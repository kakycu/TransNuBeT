<?php
// ============================================================
// historico.php - Histórico de Operaciones (auditoría)
// ------------------------------------------------------------
// Página exclusiva para administradores (roles Admin y Soft).
// Rediseñada tomando como referencia facturacion/historico_view.php:
//   * Tarjetas de estadísticas (total, hoy, usuarios activos, tipos).
//   * Barra de acciones: Actualizar, "Actualizar con opciones"
//     (recarga / auto-refresco) y Exportar (Excel/Word/PDF/CSV/TXT/
//     Imprimir), generadas en el cliente desde js/historico_export.js.
//   * Filtros por GET (compartibles por URL): fechas, usuario,
//     proveedor, acción, módulo, estado, IP y texto libre.
//   * Ordenamiento por columnas (whitelist) alternando ASC/DESC.
//   * Paginación server-side configurable (5/10/20/50/100/200) con
//     primera/anterior/números/siguiente/última e "Ir a página".
//   * Verificación de integridad del hash_chain (marca en rojo).
//   * Modal con detalles JSON formateados.
//   * Botón flotante "Eliminar todo" + modal de solicitud de
//     eliminación (escribir captcha alfanumérico) contra eliminar_historico.php.
//   * Alerta si hay más de 5 logins fallidos desde una misma IP
//     en los últimos 10 minutos.
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/funciones.php';

// ============================================================
// CONTROL DE ACCESO (solo admin)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// No autenticados → al login
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol_actual = permiso_rol_codigo();
$roles_permitidos = ['Admin', 'Soft'];

if (!in_array($rol_actual, $roles_permitidos, true)) {
    // ===== Auditar el acceso denegado y mostrar el aviso estándar =====
    logAction(
        'acceso_denegado',
        'historico',
        'Intento de acceso al Histórico de Operaciones sin permisos suficientes',
        ['rol_actual' => $rol_actual],
        (int)$_SESSION['user_id'],
        'failed',
        'Rol sin permiso: ' . $rol_actual,
        $_SESSION['auth_provider'] ?? 'local'
    );
    permiso_denegar_acceso('Histórico de Operaciones');
}

// Permiso de borrado (endpoint eliminar_historico.php lo vuelve a validar)
$permiso_borrar = in_array($rol_actual, ['Admin', 'Soft'], true);

// Código de confirmación tipo captcha para el vaciado completo del histórico
// (se guarda en sesión y se valida de nuevo en el servidor)
if (empty($_SESSION['captcha_borrar_historico'])) {
    $_SESSION['captcha_borrar_historico'] = generarCaptchaAlfanumerico(8);
}
$captcha_borrar_historico = $_SESSION['captcha_borrar_historico'];

// ============================================================
// IDENTIDAD DEL OPERADOR (para exportaciones e impresión)
// Formato: "Nombre y apellidos (Rol)"  p.ej. "Periquito Pérez Sosa (Admin)"
// ============================================================
$user_nombre_completo = trim((string)($_SESSION['user_nombre'] ?? $_SESSION['usuario_nombre'] ?? $_SESSION['username'] ?? ''));
if ($user_nombre_completo === '') { $user_nombre_completo = 'Usuario'; }
$user_rol_codigo = trim((string)($_SESSION['rol_codigo'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['user_rol'] ?? $rol_actual));
$usuario_operador = $user_nombre_completo . ($user_rol_codigo !== '' ? ' (' . $user_rol_codigo . ')' : '');

// ============================================================
// FILTROS (todos por GET para poder compartir la URL)
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

// Saneamiento adicional de los selects
$providers_validos = ['local', 'google', 'system', 'anonimo'];
$statuses_validos  = ['success', 'failed'];
if ($f_provider !== '' && !in_array($f_provider, $providers_validos, true)) { $f_provider = ''; }
if ($f_status !== '' && !in_array($f_status, $statuses_validos, true)) { $f_status = ''; }
// Solo se aceptan acciones del catálogo oficial (prepared statements, pero por higiene)
$f_action = array_values(array_intersect($f_action, array_keys(LOG_ACCIONES)));
$f_module = substr($f_module, 0, 50);

// ============================================================
// LISTAS PARA LOS SELECTS DE ROL Y NOMBRE Y APELLIDOS
// (solo usuarios/roles con actividad registrada)
// ============================================================
$roles_disponibles = [];
$usuarios_disponibles = [];
try {
    $roles_disponibles = $pdo->query(
        "SELECT COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) AS rol
         FROM clasif_rol cr
         WHERE COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) <> ''
         GROUP BY rol
         ORDER BY rol"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $roles_disponibles = []; }

try {
    $usuarios_disponibles = $pdo->query(
        "SELECT cu.id,
                CONCAT_WS(' ', NULLIF(cu.nombre, ''), NULLIF(cu.apellidos, '')) AS nombre,
                cu.usuario
         FROM clasif_usuarios cu
         WHERE cu.activo = 1 OR EXISTS (SELECT 1 FROM audit_logs a WHERE a.user_id = cu.id)
         ORDER BY nombre, cu.usuario"
    )->fetchAll();
} catch (PDOException $e) { $usuarios_disponibles = []; }

if ($f_rol !== '' && !in_array($f_rol, $roles_disponibles, true)) { $f_rol = ''; }
if ($f_usuario_id > 0) {
    $ids_usuarios_validos = array_map(static fn($u) => (int)$u['id'], $usuarios_disponibles);
    if (!in_array($f_usuario_id, $ids_usuarios_validos, true)) { $f_usuario_id = 0; }
}

// ============================================================
// ORDENAMIENTO (whitelist estricta)
// ============================================================
$cols_orden = ['id', 'created_at', 'username', 'action_type'];
$col_orden  = (string)($_GET['col'] ?? 'id');
if (!in_array($col_orden, $cols_orden, true)) { $col_orden = 'id'; }
$orden = strtoupper((string)($_GET['orden'] ?? 'DESC'));
if ($orden !== 'ASC' && $orden !== 'DESC') { $orden = 'DESC'; }
$orden_opuesto = ($orden === 'ASC') ? 'DESC' : 'ASC';

// ============================================================
// CONSTRUCCIÓN DEL WHERE (con named placeholders)
// ============================================================
$where  = [];
$params = [];
$n = 0;

if ($f_fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_fecha_desde)) {
    $n++;
    $where[]  = 't1.created_at >= :fd' . $n;
    $params['fd' . $n] = $f_fecha_desde . ' 00:00:00';
}
if ($f_fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_fecha_hasta)) {
    $n++;
    $where[]  = 't1.created_at < DATE_ADD(:fh' . $n . ', INTERVAL 1 DAY)';
    $params['fh' . $n] = $f_fecha_hasta . ' 00:00:00';
}
if ($f_usuario !== '') {
    $n++;
    $where[]  = '(t1.username LIKE :usn' . $n . ' OR t1.user_email LIKE :use' . $n . ')';
    $params['usn' . $n] = '%' . $f_usuario . '%';
    $params['use' . $n] = '%' . $f_usuario . '%';
}
if ($f_provider !== '') {
    $n++;
    $where[]  = 't1.auth_provider = :prov' . $n;
    $params['prov' . $n] = $f_provider;
}
if (!empty($f_action)) {
    $in = [];
    foreach ($f_action as $a) {
        $n++;
        $in[] = ':act' . $n;
        $params['act' . $n] = $a;
    }
    $where[] = 't1.action_type IN (' . implode(', ', $in) . ')';
}
if ($f_module !== '') {
    $n++;
    $where[]  = 't1.module = :mod' . $n;
    $params['mod' . $n] = $f_module;
}
if ($f_status !== '') {
    $n++;
    $where[]  = 't1.status = :est' . $n;
    $params['est' . $n] = $f_status;
}
if ($f_ip !== '') {
    $n++;
    $where[]  = 't1.ip_address LIKE :ip' . $n;
    $params['ip' . $n] = '%' . $f_ip . '%';
}
if ($f_texto !== '') {
    $n++;
    $where[]  = '(t1.description LIKE :txd' . $n . ' OR t1.details LIKE :txj' . $n . ')';
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
// PAGINACIÓN (registros por página configurable y persistente)
// ============================================================
$opciones_registros = [5, 10, 20, 50, 100, 200];

$registros_por_pagina = isset($_GET['registros_por_pagina'])
    ? (int)$_GET['registros_por_pagina']
    : (isset($_SESSION['registros_por_pagina'])
        ? (int)$_SESSION['registros_por_pagina']
        : 5);
if (!in_array($registros_por_pagina, $opciones_registros, true)) {
    $registros_por_pagina = 5;
}
$_SESSION['registros_por_pagina'] = $registros_por_pagina;

// Compatibilidad con el parámetro antiguo "pag"
$pagina = isset($_GET['pagina'])
    ? max(1, (int)$_GET['pagina'])
    : (isset($_GET['pag']) ? max(1, (int)$_GET['pag']) : 1);

// ============================================================
// CONTEO TOTAL
// ============================================================
$stmtCount = $pdo->prepare(
    'SELECT COUNT(*) FROM audit_logs t1
     LEFT JOIN clasif_usuarios cu ON cu.id = t1.user_id
     LEFT JOIN clasif_rol cr ON cr.id = cu.rol_id ' . $where_sql
);
$stmtCount->execute($params);
$total_registros = (int)$stmtCount->fetchColumn();

$total_paginas = ($total_registros > 0 && $registros_por_pagina > 0)
    ? (int)ceil($total_registros / $registros_por_pagina)
    : 1;
$pagina = max(1, min($pagina, $total_paginas));
$offset = ($pagina - 1) * $registros_por_pagina;

// ============================================================
// UTILIDADES DE VISTA (escapado / URL de orden)
// ============================================================
if (!function_exists('he')) {
    function he($valor) {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('urlOrden')) {
    function urlOrden($col, $orden, $orden_opuesto) {
        $query = $_GET;
        $query['col']    = $col;
        $query['orden']  = ($query['col'] === $col && ($query['orden'] ?? '') === $orden)
                           ? $orden_opuesto : $orden;
        $query['pagina'] = 1;
        unset($query['pag']);
        return '?' . http_build_query($query);
    }
}

// URL conservando los filtros actuales (para exportar / paginar)
if (!function_exists('urlCon')) {
    function urlCon(array $cambios = [], array $quitar = []) {
        $query = $_GET;
        foreach ($quitar as $q) { unset($query[$q]); }
        foreach ($cambios as $k => $v) { $query[$k] = $v; }
        return '?' . http_build_query($query);
    }
}

// ============================================================
// EXPORTACIÓN
// Las exportaciones (Excel/Word/PDF/Imprimir/CSV/TXT) se generan
// en el cliente desde js/historico_export.js, que solicita el
// conjunto COMPLETO de registros filtrados a exportar_historico.php
// (sin paginación, con todos los campos).
// ============================================================

// ============================================================
// CONSULTA PRINCIPAL (+ prev_id para verificar hash_chain)
// ============================================================
$sql = "SELECT t1.*,
               CONCAT_WS(' ', NULLIF(cu.nombre, ''), NULLIF(cu.apellidos, '')) AS usuario_nombre,
               COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) AS usuario_rol,
               (SELECT MAX(t2.id) FROM audit_logs t2 WHERE t2.id < t1.id) AS prev_id
        FROM audit_logs t1
        LEFT JOIN clasif_usuarios cu ON cu.id = t1.user_id
        LEFT JOIN clasif_rol cr ON cr.id = cu.rol_id
        $where_sql
        ORDER BY t1.$col_orden $orden, t1.id $orden
        LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// ============================================================
// CONJUNTO COMPLETO PARA LA NAVEGACIÓN DEL MODAL DE DETALLE
// Incluye TODOS los registros filtrados (sin paginación) para
// poder recorrer el historial completo (primero/anterior/
// siguiente/último y buscador) y no solo los de la página actual.
// ============================================================
$hist_registros = [];
try {
    $sql_completo = "SELECT t1.*,
               CONCAT_WS(' ', NULLIF(cu.nombre, ''), NULLIF(cu.apellidos, '')) AS usuario_nombre,
               COALESCE(NULLIF(cr.codigo, ''), NULLIF(cr.descripcion, '')) AS usuario_rol,
               (SELECT MAX(t2.id) FROM audit_logs t2 WHERE t2.id < t1.id) AS prev_id
        FROM audit_logs t1
        LEFT JOIN clasif_usuarios cu ON cu.id = t1.user_id
        LEFT JOIN clasif_rol cr ON cr.id = cu.rol_id
        $where_sql
        ORDER BY t1.$col_orden $orden, t1.id $orden";
    $stmtCompleto = $pdo->prepare($sql_completo);
    $stmtCompleto->execute($params);
    $registros_completos = $stmtCompleto->fetchAll();
    foreach ($registros_completos as $reg) {
        $canonico = implode('|', [
            $reg['user_id'] === null ? 'NULL' : (string)$reg['user_id'],
            ($reg['username'] ?? '') !== ''    ? $reg['username']  : 'NULL',
            ($reg['user_email'] ?? '') !== ''  ? $reg['user_email'] : 'NULL',
            $reg['auth_provider'],
            $reg['action_type'],
            $reg['module'],
            $reg['description'],
            canonicalDetallesAuditoria($reg['details']),
            $reg['ip_address'],
            ($reg['user_agent'] ?? '') !== ''    ? $reg['user_agent']  : 'NULL',
            ($reg['request_method'] ?? '') !== ''? $reg['request_method'] : 'NULL',
            ($reg['request_url'] ?? '') !== ''   ? $reg['request_url'] : 'NULL',
            $reg['status'],
            $reg['error_message'] !== null ? $reg['error_message'] : 'NULL',
            $reg['created_at'],
        ]);
        $entrada_hash = ($reg['prev_id'] !== null && $reg['prev_id'] !== false)
                        ? $reg['prev_id'] . '|' . $canonico
                        : $canonico;
        $hash_recalc = hash('sha256', $entrada_hash);
        $integro = ($reg['hash_chain'] !== null && hash_equals($reg['hash_chain'], $hash_recalc));
        $hist_registros[] = [
            'id'       => (int)$reg['id'],
            'userid'   => $reg['user_id'] === null ? '' : (int)$reg['user_id'],
            'usuario'  => $reg['username'] ?? '',
            'email'    => $reg['user_email'] ?? '',
            'rol'      => $reg['usuario_rol'] ?? '',
            'nombre'   => $reg['usuario_nombre'] ?? '',
            'provider' => $reg['auth_provider'],
            'accion'   => $reg['action_type'],
            'modulo'   => $reg['module'],
            'desc'     => $reg['description'],
            'details'  => $reg['details'] !== null
                          ? json_encode(json_decode($reg['details'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                          : '{}',
            'ip'       => $reg['ip_address'],
            'ua'       => $reg['user_agent'] ?? '',
            'metodo'   => $reg['request_method'] ?? '',
            'url'      => $reg['request_url'] ?? '',
            'estado'   => $reg['status'],
            'error'    => $reg['error_message'] ?? '',
            'fecha'    => $reg['created_at'],
            'hash'     => $reg['hash_chain'] ?? '',
            'integro'  => $integro ? '1' : '0',
        ];
    }
} catch (PDOException $e) {
    $hist_registros = [];
}

// ============================================================
// ESTADÍSTICAS (globales, como en la referencia)
// ============================================================
$estadisticas = ['total' => 0, 'hoy' => 0, 'usuarios_activos' => 0, 'tipos_unicos' => 0];
try {
    $estadisticas['total']           = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $estadisticas['hoy']             = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
    $estadisticas['usuarios_activos'] = (int)$pdo->query('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE user_id IS NOT NULL')->fetchColumn();
    $estadisticas['tipos_unicos']    = (int)$pdo->query('SELECT COUNT(DISTINCT action_type) FROM audit_logs')->fetchColumn();
} catch (PDOException $e) { /* se mantienen en 0 */ }

// ============================================================
// MÓDULOS DISPONIBLES (para el select)
// ============================================================
$modulos_disponibles = [];
try {
    $modulos_disponibles = $pdo->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $modulos_disponibles = []; }

// ============================================================
// ALERTA: >5 logins fallidos desde la misma IP en los últimos 10 min
// Por cada IP sospechosa se detallan las credenciales intentadas y,
// si corresponden a un usuario registrado, su nombre y apellidos.
// ============================================================
$alertas_ips = [];
try {
    $stmtAl = $pdo->query(
        "SELECT ip_address, COUNT(*) AS intentos
         FROM audit_logs
         WHERE action_type = 'iniciar_sesion'
           AND status = 'failed'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         GROUP BY ip_address
         HAVING COUNT(*) > 5
         ORDER BY intentos DESC"
    );
    $filas_sospechosas = $stmtAl->fetchAll();

    if (!empty($filas_sospechosas)) {
        $ips            = array_values(array_unique(array_map('strval', array_column($filas_sospechosas, 'ip_address'))));
        $in_ph          = implode(',', array_fill(0, count($ips), '?'));

        $stmtDet = $pdo->prepare(
            "SELECT ip_address, description, details
             FROM audit_logs
             WHERE action_type = 'iniciar_sesion'
               AND status = 'failed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
               AND ip_address IN ($in_ph)
             ORDER BY id ASC"
        );
        $stmtDet->execute($ips);
        $tentativas = $stmtDet->fetchAll();

        // Índice de credenciales reales (usuario, email o carné de identidad)
        // de clasif_usuarios → usuario canónico + nombre y apellidos.
        $credenciales_reales = [];
        $usuarios_clasi = $pdo->query("SELECT usuario, email, no_ci, nombre, apellidos FROM clasif_usuarios")->fetchAll();
        foreach ($usuarios_clasi as $u) {
            $info_usuario = [
                'usuario'         => (string)($u['usuario'] ?? ''),
                'nombre_completo' => trim((string)($u['nombre'] ?? '') . ' ' . (string)($u['apellidos'] ?? '')),
            ];
            foreach ([$info_usuario['usuario'], (string)($u['email'] ?? ''), (string)($u['no_ci'] ?? '')] as $credencial) {
                if ($credencial !== '') {
                    $credenciales_reales[mb_strtolower($credencial, 'UTF-8')] = $info_usuario;
                }
            }
        }

        // Agrupar tentativas por IP → credencial intentada (insensible a mayúsculas).
        $detalle_por_ip = [];
        foreach ($tentativas as $t) {
            $ip = (string)$t['ip_address'];

            // Se prefiere details.usuario_intentado (datos nuevos) y, si no,
            // se extrae de la descripción (registros antiguos).
            $credencial = '';
            $det = json_decode((string)$t['details'], true);
            if (is_array($det) && isset($det['usuario_intentado']) && is_scalar($det['usuario_intentado'])) {
                $credencial = trim((string)$det['usuario_intentado']);
            }
            if ($credencial === '' && preg_match('/Intento de inicio de sesión fallido para:\s*(.*)$/u', (string)$t['description'], $m)) {
                $credencial = trim($m[1]);
            }
            if ($credencial === '') { $credencial = '¿desconocido?'; }

            $clave = mb_strtolower($credencial, 'UTF-8');
            if (!isset($detalle_por_ip[$ip][$clave])) {
                $info = $credenciales_reales[$clave] ?? null;
                $detalle_por_ip[$ip][$clave] = [
                    'credencial'      => $credencial,
                    'intentos'        => 0,
                    'registrado'      => $info !== null,
                    'usuario'         => $info ? $info['usuario'] : null,
                    'nombre_completo' => $info ? $info['nombre_completo'] : null,
                ];
            }
            $detalle_por_ip[$ip][$clave]['intentos']++;
        }

        // Estructura final (mismo orden que la detección: por nº de intentos).
        foreach ($filas_sospechosas as $fila) {
            $ip = (string)$fila['ip_address'];
            $credenciales = $detalle_por_ip[$ip] ?? [];
            uasort($credenciales, static fn($a, $b) => $b['intentos'] <=> $a['intentos']);
            $alertas_ips[] = [
                'ip_address'   => $ip,
                'intentos'     => (int)$fila['intentos'],
                'credenciales' => array_values($credenciales),
            ];
        }
    }
} catch (PDOException $e) { $alertas_ips = []; }

// ============================================================
// RESUMEN DE FILTROS (encabezado de las exportaciones)
// ============================================================
$filtros_resumen = [];
if ($f_fecha_desde !== '') { $filtros_resumen[] = 'Desde: ' . $f_fecha_desde; }
if ($f_fecha_hasta !== '') { $filtros_resumen[] = 'Hasta: ' . $f_fecha_hasta; }
if ($f_usuario !== '')     { $filtros_resumen[] = 'Usuario/Email: ' . $f_usuario; }
if ($f_rol !== '')         { $filtros_resumen[] = 'Rol: ' . $f_rol; }
if ($f_usuario_id > 0) {
    $nombre_filtro = '';
    foreach ($usuarios_disponibles as $u) {
        if ((int)$u['id'] === $f_usuario_id) {
            $nombre_filtro = trim((string)$u['nombre']) !== '' ? (string)$u['nombre'] : (string)$u['usuario'];
            break;
        }
    }
    $filtros_resumen[] = 'Nombre y apellidos: ' . $nombre_filtro;
}
if ($f_provider !== '')    { $filtros_resumen[] = 'Proveedor: ' . $f_provider; }
if (!empty($f_action))     { $filtros_resumen[] = 'Acciones: ' . implode(', ', $f_action); }
if ($f_module !== '')      { $filtros_resumen[] = 'Módulo: ' . $f_module; }
if ($f_status !== '')      { $filtros_resumen[] = 'Estado: ' . etiquetaEstadoLog($f_status); }
if ($f_ip !== '')          { $filtros_resumen[] = 'IP: ' . $f_ip; }
if ($f_texto !== '')       { $filtros_resumen[] = 'Texto: ' . $f_texto; }
$filtros_resumen = empty($filtros_resumen)
    ? 'Sin filtros aplicados'
    : ('Filtros → ' . implode(' · ', $filtros_resumen));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/theme_early.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Histórico de Operaciones · Auditoría</title>
    <link rel="icon" type="image/x-icon" href="../../images/favicons/nominas.ico">

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="../css/font-awesome6.4.0/css/all.min.css">
    <link href="../css/bootstrap5.3.0/bootstrap.min.css" rel="stylesheet">
    <link href="../css/sweetalert2.min.css" rel="stylesheet">

    <!-- Estilos específicos de la auditoría -->
    <link rel="stylesheet" href="CSS/historico.css?v=<?php echo @filemtime(__DIR__ . '/CSS/historico.css') ?: time(); ?>">
</head>
<body class="historico-body">

<div class="win11-bg"></div>

<?php include '../includes/sidebar.php'; ?>

<main class="main-container" id="mainContainer">
    <!-- Top Bar -->
    <div class="win-topbar fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggleBtn" title="Alternar menú lateral" data-tooltip="Alternar menú lateral" data-tooltip-theme="primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1><i class="fas fa-history me-2" style="color: #60a5fa;"></i>Histórico de Operaciones</h1>
                <p><i class="fas fa-shield-halved me-1"></i> Trazabilidad y transparencia de las operaciones del sistema</p>
            </div>
        </div>
        <?php include '../includes/user_menu.php'; ?>
    </div>

    <!-- Barra de acciones (Actualizar / opciones / exportar) -->
    <div class="audit-toolbar fade-in-up">
        <div class="audit-toolbar-group">
            <button type="button" class="btn-win btn-win-primary" onclick="recargarManteniendo()" title="Recargar la página con los filtros actuales" data-tooltip="Actualizar" data-tooltip-theme="primary">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>

            <div class="dropdown audit-dropdown">
                <button type="button" class="btn-win dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Opciones de actualización" data-tooltip="Actualizar con opciones" data-tooltip-theme="primary">
                    <i class="fas fa-rotate"></i> Actualizar con opciones
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Refresco</h6></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="recargarManteniendo()"><i class="fas fa-rotate-right"></i> Recargar manteniendo filtros</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="activarAutoRefresh(30)"><i class="fas fa-clock"></i> Auto-actualizar cada 30 s</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="activarAutoRefresh(60)"><i class="fas fa-clock"></i> Auto-actualizar cada 1 min</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="activarAutoRefresh(300)"><i class="fas fa-clock"></i> Auto-actualizar cada 5 min</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="desactivarAutoRefresh()"><i class="fas fa-stop"></i> Desactivar auto-actualización</a></li>
                </ul>
            </div>

            <button type="button" class="btn-win" data-export="print" title="Imprimir la vista filtrada (paginada, con numeración)" data-tooltip="Imprimir" data-tooltip-theme="primary" aria-label="Imprimir"><i class="fas fa-print"></i></button>
            <div class="dropdown audit-dropdown">
                <button type="button" class="btn-win dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar los registros filtrados" data-tooltip="Exportar" data-tooltip-theme="success">
                    <i class="fas fa-file-export"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Exportar histórico</h6></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-export="excel" title="Todos los campos en Excel (.xlsx)" data-tooltip="Excel (.xlsx)" data-tooltip-theme="success"><i class="fas fa-file-excel text-success"></i> Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-export="word" title="Todos los campos en Word (.doc) horizontal" data-tooltip="Word (.doc)" data-tooltip-theme="primary"><i class="fas fa-file-word text-primary"></i> Word (.doc)</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-export="pdf" title="Todos los campos en PDF horizontal" data-tooltip="PDF (.pdf)" data-tooltip-theme="danger"><i class="fas fa-file-pdf text-danger"></i> PDF (.pdf)</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-export="csv" title="Todos los campos en CSV" data-tooltip="CSV (.csv)" data-tooltip-theme="info"><i class="fas fa-file-csv text-info"></i> CSV (.csv)</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-export="txt" title="Todos los campos en TXT" data-tooltip="TXT (.txt)" data-tooltip-theme="secondary"><i class="fas fa-file-alt text-warning"></i> TXT (.txt)</a></li>
                </ul>
            </div>

            <a href="historico.php" class="btn-win" title="Limpiar todos los filtros" data-tooltip="Limpiar filtros" data-tooltip-theme="primary"><i class="fas fa-eraser"></i> Limpiar filtros</a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="audit-stats fade-in-up">
        <div class="audit-stat audit-card">
            <div>
                <div class="audit-stat-label">Total Registros</div>
                <div class="audit-stat-value"><?php echo number_format($estadisticas['total'], 0, ',', '.'); ?></div>
            </div>
            <i class="fas fa-database"></i>
        </div>
        <div class="audit-stat audit-card">
            <div>
                <div class="audit-stat-label">Registros Hoy</div>
                <div class="audit-stat-value"><?php echo number_format($estadisticas['hoy'], 0, ',', '.'); ?></div>
            </div>
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="audit-stat audit-card">
            <div>
                <div class="audit-stat-label">Usuarios Activos</div>
                <div class="audit-stat-value"><?php echo number_format($estadisticas['usuarios_activos'], 0, ',', '.'); ?></div>
            </div>
            <i class="fas fa-user-check"></i>
        </div>
        <div class="audit-stat audit-card" title="Cantidad de tipos distintos de acción (action_type) registrados en todo el histórico. Es un dato global: no cambia al aplicar filtros." data-tooltip="Tipos distintos de operación registrados (global)" data-tooltip-theme="primary">
            <div>
                <div class="audit-stat-label">Tipos de Operación</div>
                <div class="audit-stat-value"><?php echo number_format($estadisticas['tipos_unicos'], 0, ',', '.'); ?></div>
            </div>
            <i class="fas fa-tags"></i>
        </div>
    </div>

    <!-- Alerta de intentos fallidos -->
    <?php if (!empty($alertas_ips)): ?>
        <div class="audit-alert audit-alert-danger fade-in-up" id="alertaLoginsFallidos" role="alert">
            <div class="audit-alert-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="audit-alert-body">
                <strong>Actividad sospechosa detectada:</strong>
                se han registrado más de 5 inicios de sesión <b>fallidos</b> desde la misma IP en los últimos 10 minutos.
                <ul class="audit-alert-list">
                    <?php foreach ($alertas_ips as $alerta_ip): ?>
                        <li>
                            IP <b><?php echo he($alerta_ip['ip_address']); ?></b> — <b><?php echo (int)$alerta_ip['intentos']; ?></b> intentos fallidos
                            <?php if (!empty($alerta_ip['credenciales'])): ?>
                                <ul class="audit-alert-sublist">
                                    <?php foreach ($alerta_ip['credenciales'] as $cred): ?>
                                        <li>
                                            Usuario intentado: <b><?php echo he($cred['credencial']); ?></b> ·
                                            <?php if ($cred['registrado']): ?>
                                                usuario existente: <b><?php echo he($cred['usuario']); ?></b> (<?php echo he($cred['nombre_completo']); ?>)
                                            <?php else: ?>
                                                usuario <b>no registrado</b>
                                            <?php endif; ?>
                                            · <b><?php echo (int)$cred['intentos']; ?></b> intento(s)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="audit-alert-close" onclick="cerrarAlerta('alertaLoginsFallidos')" title="Cerrar aviso" data-tooltip="Cerrar aviso" data-tooltip-theme="danger"><i class="fas fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Card: Filtros -->
    <div class="glass-card fade-in-up audit-card">
        <div class="audit-card-header" id="cabeceraFiltros" role="button" aria-expanded="false" aria-controls="filtrosPanel">
            <h6 class="mb-0 fw-semibold" style="cursor:pointer;">
                <i class="fas fa-sliders me-2" style="color: #60a5fa;"></i> Filtros
                <i class="fas fa-chevron-down audit-chevron"></i>
            </h6>
            <div class="d-flex align-items-center gap-2">
                <span class="audit-badge-count"><?php echo count($f_action); ?> acciones</span>
                <a href="historico.php" class="btn-win btn-win-sm" title="Limpiar todos los filtros" data-tooltip="Limpiar filtros" data-tooltip-theme="primary"><i class="fas fa-eraser me-1"></i> Limpiar</a>
            </div>
        </div>
        <div class="collapse" id="filtrosPanel">
            <form method="get" action="historico.php" class="audit-filtros" id="filtrosForm">
                <div class="audit-grid">
                    <div class="audit-field">
                        <label for="f_fecha_desde">Desde</label>
                        <input type="date" id="f_fecha_desde" name="fecha_desde" value="<?php echo he($f_fecha_desde); ?>">
                    </div>
                    <div class="audit-field">
                        <label for="f_fecha_hasta">Hasta</label>
                        <input type="date" id="f_fecha_hasta" name="fecha_hasta" value="<?php echo he($f_fecha_hasta); ?>">
                    </div>
                    <div class="audit-field">
                        <label for="f_usuario">Usuario / Email</label>
                        <input type="text" id="f_usuario" name="usuario" placeholder="admin o admin@..." value="<?php echo he($f_usuario); ?>">
                    </div>
                    <div class="audit-field">
                        <label for="f_rol">Rol</label>
                        <select id="f_rol" name="rol">
                            <option value="">Todos</option>
                            <?php foreach ($roles_disponibles as $rol_op): ?>
                                <option value="<?php echo he($rol_op); ?>" <?php echo ($f_rol === $rol_op) ? 'selected' : ''; ?>><?php echo he($rol_op); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label for="f_usuario_id">Nombre y apellidos</label>
                        <select id="f_usuario_id" name="usuario_id">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios_disponibles as $u_op): ?>
                                <?php
                                    $u_nombre = trim((string)$u_op['nombre']);
                                    $u_etiqueta = $u_nombre !== ''
                                        ? $u_nombre . (trim((string)$u_op['usuario']) !== '' ? ' (' . $u_op['usuario'] . ')' : '')
                                        : (string)$u_op['usuario'];
                                ?>
                                <option value="<?php echo (int)$u_op['id']; ?>" <?php echo ($f_usuario_id === (int)$u_op['id']) ? 'selected' : ''; ?>><?php echo he($u_etiqueta); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label for="f_provider">Proveedor</label>
                        <select id="f_provider" name="auth_provider">
                            <option value="">Todos</option>
                            <?php foreach ($providers_validos as $prov): ?>
                                <option value="<?php echo $prov; ?>" <?php echo ($f_provider === $prov) ? 'selected' : ''; ?>><?php echo ucfirst($prov); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label for="f_module">Módulo</label>
                        <select id="f_module" name="module">
                            <option value="">Todos</option>
                            <?php foreach ($modulos_disponibles as $mod): ?>
                                <option value="<?php echo he($mod); ?>" <?php echo ($f_module === $mod) ? 'selected' : ''; ?>><?php echo he($mod); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label for="f_status">Estado</label>
                        <select id="f_status" name="status">
                            <option value="">Todos</option>
                            <?php foreach ($statuses_validos as $est): ?>
                                <option value="<?php echo $est; ?>" <?php echo ($f_status === $est) ? 'selected' : ''; ?>><?php echo etiquetaEstadoLog($est); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label for="f_ip">Dirección IP</label>
                        <input type="text" id="f_ip" name="ip" placeholder="192.168.1.1" value="<?php echo he($f_ip); ?>">
                    </div>
                    <div class="audit-field">
                        <label for="f_texto">Texto en descripción / detalles</label>
                        <input type="text" id="f_texto" name="texto" placeholder="ej. trabajador, nómina..." value="<?php echo he($f_texto); ?>">
                    </div>
                </div>
                <div class="audit-field audit-field-full">
                    <label>Acciones (<span class="audit-count" id="accionesSel">0</span> seleccionadas)</label>
                    <div class="audit-acciones">
                        <?php foreach (LOG_ACCIONES as $accion_clave => $accion_desc): ?>
                            <label class="audit-chequeo" title="<?php echo he($accion_desc); ?>">
                                <input type="checkbox" name="action_type[]" value="<?php echo he($accion_clave); ?>" <?php echo in_array($accion_clave, $f_action, true) ? 'checked' : ''; ?>>
                                <span><?php echo he($accion_clave); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="audit-filtros-acciones">
                    <button type="submit" class="btn-win btn-win-primary"><i class="fas fa-filter me-1"></i> Aplicar filtros</button>
                    <a href="historico.php" class="btn-win btn-win-sm"><i class="fas fa-eraser me-1"></i> Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Card: Resultados -->
    <div class="glass-card fade-in-up audit-card">
        <div class="audit-card-header">
            <h6 class="mb-0 fw-semibold">
                <i class="fas fa-table-list me-2" style="color: #60a5fa;"></i> Registros de operaciones
                <span class="audit-badge-count"><?php echo number_format($total_registros, 0, ',', '.'); ?> en total</span>
            </h6>
            <span class="audit-badge-count">Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>
        </div>

        <?php if (empty($registros)): ?>
            <div class="audit-vacio">
                <i class="fas fa-circle-info"></i>
                <p>No se encontraron registros con los filtros aplicados.</p>
                <a href="historico.php" class="btn-win btn-win-sm"><i class="fas fa-rotate me-1"></i> Ver todas las actividades</a>
            </div>
        <?php else: ?>
            <div class="audit-tabla-wrap">
                <table class="audit-tabla" id="tablaAuditoria">
                    <thead>
                        <tr>
                            <th class="audit-col-min" title="Integridad de la cadena de hashes" data-tooltip="Integridad" data-tooltip-theme="primary"></th>
                            <th class="audit-col-min">Acciones</th>
                            <th class="audit-col-min"><a href="<?php echo he(urlOrden('id', 'ASC', $orden_opuesto)); ?>"># ID <?php if ($col_orden === 'id') echo $orden === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'; ?></a></th>
                            <th><a href="<?php echo he(urlOrden('created_at', 'DESC', $orden_opuesto)); ?>">Fecha <i class="fas fa-clock"></i></a></th>
                            <th><a href="<?php echo he(urlOrden('username', 'ASC', $orden_opuesto)); ?>">Usuario</a></th>
                            <th>Rol</th>
                            <th>Nombre y apellidos</th>
                            <th>Proveedor</th>
                            <th><a href="<?php echo he(urlOrden('action_type', 'ASC', $orden_opuesto)); ?>">Acción</a></th>
                            <th>Módulo</th>
                            <th>IP</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($registros as $reg):
                            // ---- Verificación de integridad del hash_chain ----
                            $canonico = implode('|', [
                                $reg['user_id'] === null ? 'NULL' : (string)$reg['user_id'],
                                ($reg['username'] ?? '') !== ''    ? $reg['username']  : 'NULL',
                                ($reg['user_email'] ?? '') !== ''  ? $reg['user_email'] : 'NULL',
                                $reg['auth_provider'],
                                $reg['action_type'],
                                $reg['module'],
                                $reg['description'],
                                canonicalDetallesAuditoria($reg['details']),
                                $reg['ip_address'],
                                ($reg['user_agent'] ?? '') !== ''    ? $reg['user_agent']  : 'NULL',
                                ($reg['request_method'] ?? '') !== ''? $reg['request_method'] : 'NULL',
                                ($reg['request_url'] ?? '') !== ''   ? $reg['request_url'] : 'NULL',
                                $reg['status'],
                                $reg['error_message'] !== null ? $reg['error_message'] : 'NULL',
                                $reg['created_at'],
                            ]);
                            $entrada_hash = ($reg['prev_id'] !== null && $reg['prev_id'] !== false)
                                            ? $reg['prev_id'] . '|' . $canonico
                                            : $canonico;
                            $hash_recalc = hash('sha256', $entrada_hash);
                            $integro = ($reg['hash_chain'] !== null && hash_equals($reg['hash_chain'], $hash_recalc));

                            $fecha_vista = date('d/m/Y h:i:s A', strtotime($reg['created_at']));
                            $detalles_json = $reg['details'] !== null
                                ? json_encode(json_decode($reg['details'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '{}';
                        ?>
                        <tr class="<?php echo $integro ? 'audit-fila' : 'audit-fila audit-fila-alterada'; ?>">
                            <td class="audit-col-min">
                                <?php if (!$integro): ?>
                                    <span class="audit-alertico" title="Registro posiblemente alterado" data-tooltip="Registro posiblemente alterado" data-tooltip-theme="danger">⚠️</span>
                                <?php else: ?>
                                    <i class="fas fa-link" style="color: var(--color-muted);" title="Cadena íntegra" data-tooltip="Cadena íntegra" data-tooltip-theme="primary"></i>
                                <?php endif; ?>
                            </td>
                            <td class="audit-col-min">
                                <div class="audit-acciones-celda">
                                    <button type="button" class="btn-win btn-win-sm btn-ver-detalle"
                                            onclick="abrirDetalle(this)"
                                            data-id="<?php echo (int)$reg['id']; ?>"
                                            data-userid="<?php echo $reg['user_id'] === null ? '' : (int)$reg['user_id']; ?>"
                                            data-usuario="<?php echo he($reg['username']); ?>"
                                            data-email="<?php echo he($reg['user_email']); ?>"
                                            data-rol="<?php echo he($reg['usuario_rol'] ?? ''); ?>"
                                            data-nombre="<?php echo he($reg['usuario_nombre'] ?? ''); ?>"
                                            data-provider="<?php echo he($reg['auth_provider']); ?>"
                                            data-accion="<?php echo he($reg['action_type']); ?>"
                                            data-modulo="<?php echo he($reg['module']); ?>"
                                            data-desc="<?php echo he($reg['description']); ?>"
                                            data-details="<?php echo he($detalles_json); ?>"
                                            data-ip="<?php echo he($reg['ip_address']); ?>"
                                            data-ua="<?php echo he($reg['user_agent']); ?>"
                                            data-metodo="<?php echo he($reg['request_method']); ?>"
                                            data-url="<?php echo he($reg['request_url']); ?>"
                                            data-estado="<?php echo he($reg['status']); ?>"
                                            data-error="<?php echo he($reg['error_message']); ?>"
                                            data-fecha="<?php echo he($reg['created_at']); ?>"
                                            data-hash="<?php echo he($reg['hash_chain']); ?>"
                                            data-integro="<?php echo $integro ? '1' : '0'; ?>"
                                            title="Ver detalle formateado del registro" data-tooltip="Ver detalle" data-tooltip-theme="primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($permiso_borrar): ?>
                                    <button type="button" class="btn-win btn-win-sm btn-del-registro"
                                            onclick="eliminarRegistro(this)"
                                            data-id="<?php echo (int)$reg['id']; ?>"
                                            data-accion="<?php echo he($reg['action_type']); ?>"
                                            data-usuario="<?php echo he($reg['username']); ?>"
                                            title="Eliminar este registro del histórico" data-tooltip="Eliminar registro" data-tooltip-theme="danger">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="audit-col-min"><?php echo (int)$reg['id']; ?></td>
                            <td class="audit-fecha"><?php echo he($fecha_vista); ?></td>
                            <td>
                                <div class="audit-usuario"><?php echo he($reg['username']); ?></div>
                                <div class="audit-sub"><?php echo he($reg['user_email']); ?></div>
                            </td>
                            <td>
                                <?php if (($reg['usuario_rol'] ?? '') !== ''): ?>
                                    <span class="audit-badge audit-rol"><?php echo he($reg['usuario_rol']); ?></span>
                                <?php else: ?>
                                    <span class="audit-sub">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="audit-nombre"><?php echo he(($reg['usuario_nombre'] ?? '') !== '' ? $reg['usuario_nombre'] : '—'); ?></td>
                            <td><span class="audit-badge audit-prov-<?php echo he($reg['auth_provider']); ?>"><?php echo he($reg['auth_provider']); ?></span></td>
                            <td>
                                <div class="audit-usuario"><?php echo he($reg['action_type']); ?></div>
                                <div class="audit-sub"><?php echo he($reg['description']); ?></div>
                            </td>
                            <td><span class="audit-badge audit-modulo"><?php echo he($reg['module']); ?></span></td>
                            <td class="audit-ip"><?php echo he($reg['ip_address']); ?></td>
                            <td><span class="audit-estado audit-estado-<?php echo he($reg['status']); ?>"><?php echo he(etiquetaEstadoLog($reg['status'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card: Paginación -->
    <?php if ($total_registros > 0): ?>
    <div class="glass-card fade-in-up audit-card audit-paginacion-card">
        <div class="audit-pag-row">
            <div class="audit-pag-selector">
                <label for="registrosPorPagina">Registros por página:</label>
                <select id="registrosPorPagina" onchange="cambiarRegistrosPorPagina(this.value)">
                    <?php foreach ($opciones_registros as $opcion): ?>
                        <option value="<?php echo $opcion; ?>" <?php echo $registros_por_pagina == $opcion ? 'selected' : ''; ?>><?php echo $opcion; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-paginacion-info">
                Mostrando
                <strong><?php echo min($total_registros, $offset + 1); ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?></strong>
                de <strong><?php echo number_format($total_registros, 0, ',', '.'); ?></strong> registros
                (Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)
            </div>

            <?php if ($total_paginas > 1): ?>
            <nav class="audit-paginacion-pages" aria-label="Paginación del histórico">
                <a class="audit-pag <?php echo $pagina == 1 ? 'audit-pag-disabled' : ''; ?>" href="<?php echo he(urlCon(['pagina' => 1], ['pag'])); ?>" title="Primera" data-tooltip="Primera" data-tooltip-theme="primary"><i class="fas fa-angle-double-left"></i></a>
                <a class="audit-pag <?php echo $pagina == 1 ? 'audit-pag-disabled' : ''; ?>" href="<?php echo he(urlCon(['pagina' => max(1, $pagina - 1)], ['pag'])); ?>" title="Anterior" data-tooltip="Anterior" data-tooltip-theme="primary"><i class="fas fa-angle-left"></i></a>

                <?php
                $pagina_inicio = max(1, $pagina - 2);
                $pagina_fin    = min($total_paginas, $pagina + 2);
                if ($pagina_inicio == 1) { $pagina_fin = min(5, $total_paginas); }
                if ($pagina_fin == $total_paginas) { $pagina_inicio = max(1, $total_paginas - 4); }

                if ($pagina_inicio > 1): ?>
                    <a class="audit-pag" href="<?php echo he(urlCon(['pagina' => 1], ['pag'])); ?>">1</a>
                    <?php if ($pagina_inicio > 2): ?><span class="audit-pag audit-pag-puntos">...</span><?php endif; ?>
                <?php endif;

                for ($i = $pagina_inicio; $i <= $pagina_fin; $i++): ?>
                    <?php if ($i === $pagina): ?>
                        <span class="audit-pag audit-pag-actual"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a class="audit-pag" href="<?php echo he(urlCon(['pagina' => $i], ['pag'])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor;

                if ($pagina_fin < $total_paginas): ?>
                    <?php if ($pagina_fin < $total_paginas - 1): ?><span class="audit-pag audit-pag-puntos">...</span><?php endif; ?>
                    <a class="audit-pag" href="<?php echo he(urlCon(['pagina' => $total_paginas], ['pag'])); ?>"><?php echo $total_paginas; ?></a>
                <?php endif; ?>

                <a class="audit-pag <?php echo $pagina == $total_paginas ? 'audit-pag-disabled' : ''; ?>" href="<?php echo he(urlCon(['pagina' => min($total_paginas, $pagina + 1)], ['pag'])); ?>" title="Siguiente" data-tooltip="Siguiente" data-tooltip-theme="primary"><i class="fas fa-angle-right"></i></a>
                <a class="audit-pag <?php echo $pagina == $total_paginas ? 'audit-pag-disabled' : ''; ?>" href="<?php echo he(urlCon(['pagina' => $total_paginas], ['pag'])); ?>" title="Última" data-tooltip="Última" data-tooltip-theme="primary"><i class="fas fa-angle-double-right"></i></a>
            </nav>

            <div class="audit-pag-jump">
                <label for="jumpToPage">Ir a:</label>
                <input type="number" id="jumpToPage" min="1" max="<?php echo $total_paginas; ?>" value="<?php echo $pagina; ?>">
                <button type="button" class="btn-win btn-win-sm" onclick="jumpToPage()" title="Ir a la página"><i class="fas fa-arrow-right"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</main>

<?php if ($permiso_borrar): ?>
<!-- Botón flotante: eliminar todo el histórico -->
<button type="button" class="audit-fab audit-fab-danger" id="btnDeleteAll"
        onclick="confirmarVaciadoCompleto()"
        title="ELIMINAR TODA LA TRAZA" data-tooltip="Eliminar todo el histórico" data-tooltip-theme="danger">
    <i class="fas fa-trash-can"></i>
</button>
<?php endif; ?>

<!-- Modal: Detalles del Registro (igual al de /facturacion/historico_view.php) -->
<div class="modal fade audit-det" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered audit-det-dialog">
        <div class="modal-content">
            <div class="modal-header audit-det-header">
                <div class="d-flex align-items-center w-100 gap-2">
                    <div class="audit-det-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-0">
                            Detalles del Registro:
                            <span class="audit-det-id" id="detId"></span>
                        </h5>
                    </div>
                    <button type="button" class="audit-modal-cerrar" data-bs-dismiss="modal" title="Cerrar" data-tooltip="Cerrar" data-tooltip-theme="danger"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="audit-det-nav">
                    <button type="button" class="audit-nav-btn" id="detBtnPrimero" onclick="detNavegar('primero')" title="Primer registro" data-tooltip="Primer registro" data-tooltip-theme="primary" data-tooltip-position="bottom"><i class="fas fa-angle-double-left"></i></button>
                    <button type="button" class="audit-nav-btn" id="detBtnAnterior" onclick="detNavegar('anterior')" title="Anterior" data-tooltip="Anterior" data-tooltip-theme="primary" data-tooltip-position="bottom"><i class="fas fa-angle-left"></i></button>
                    <div class="audit-det-nav-selector">
                        <input type="text" id="detNavBuscar" placeholder="🔍 Buscar registro..." autocomplete="off" oninput="detRenderDropdown(this.value)" onfocus="detRenderDropdown(this.value)" onkeydown="detTeclaBusqueda(event)">
                        <button type="button" class="audit-det-nav-clear" id="detNavClear" onclick="detLimpiarBusqueda()" title="Limpiar búsqueda" style="display:none;"><i class="fas fa-xmark"></i></button>
                        <div class="audit-det-nav-dropdown" id="detNavDropdown"></div>
                    </div>
                    <span class="audit-det-nav-counter"><strong id="detRegistroActual">0</strong> / <strong id="detTotalRegistros">0</strong></span>
                    <button type="button" class="audit-nav-btn" id="detBtnSiguiente" onclick="detNavegar('siguiente')" title="Siguiente" data-tooltip="Siguiente" data-tooltip-theme="primary" data-tooltip-position="bottom"><i class="fas fa-angle-right"></i></button>
                    <button type="button" class="audit-nav-btn" id="detBtnUltimo" onclick="detNavegar('ultimo')" title="Último registro" data-tooltip="Último registro" data-tooltip-theme="primary" data-tooltip-position="bottom"><i class="fas fa-angle-double-right"></i></button>
                </div>
            </div>

            <div class="modal-body">
                <!-- Resumen compacto (siempre visible) -->
                <div class="audit-det-resumen">
                    <div class="audit-det-res-item">
                        <i class="fas fa-user-circle"></i>
                        <div class="min-w-0 audit-det-campos">
                            <div class="audit-det-campo"><span class="audit-det-label">Usuario:</span><span class="audit-det-valor" id="detUsuario"></span></div>
                            <div class="audit-det-campo"><span class="audit-det-label">Rol:</span><span class="audit-det-valor" id="detRol"></span></div>
                            <div class="audit-det-campo"><span class="audit-det-label">Nombre y Apellidos:</span><span class="audit-det-valor" id="detNombre"></span></div>
                            <div class="audit-det-campo"><span class="audit-det-label">Correo:</span><span class="audit-det-valor audit-det-wrap" id="detEmail"></span></div>
                        </div>
                    </div>
                    <div class="audit-det-res-item">
                        <i class="fas fa-clock"></i>
                        <div class="min-w-0 audit-det-campos">
                            <div class="audit-det-campo"><span class="audit-det-label">FECHA Y HORA:</span><span class="audit-det-valor" id="detFecha"></span><span class="audit-det-valor" id="detHora"></span></div>
                        </div>
                    </div>
                    <div class="audit-det-res-item">
                        <i class="fas fa-tag"></i>
                        <div class="min-w-0 audit-det-campos">
                            <div class="audit-det-campo"><span class="audit-det-label">TIPO DE OPERACIÓN:</span><span id="detTipo"></span></div>
                        </div>
                    </div>
                    <div class="audit-det-res-item">
                        <i class="fas fa-network-wired"></i>
                        <div class="min-w-0 audit-det-campos">
                            <div class="audit-det-campo"><span class="audit-det-label">DIRECCIÓN IP:</span><span class="audit-det-valor" id="detIp"></span><span class="audit-det-sub" id="detLocalizacion"></span></div>
                        </div>
                    </div>
                    <div class="audit-det-alterado text-danger" id="detAlterado" hidden>
                        <i class="fas fa-triangle-exclamation me-1"></i>REGISTRO ALTERADO
                    </div>
                </div>

                <!-- Tarjeta plegable: Descripción -->
                <section class="audit-det-card">
                    <div class="audit-det-card-head" data-bs-toggle="collapse" data-bs-target="#detColDesc" role="button" aria-expanded="true" aria-controls="detColDesc">
                        <span class="audit-det-card-title"><i class="fas fa-align-left"></i>Descripción completa</span>
                        <span class="audit-det-card-tools">
                            <button type="button" class="btn-win btn-win-sm" onclick="event.stopPropagation();copiarDetalle(this)"><i class="fas fa-copy me-1"></i>Copiar</button>
                            <i class="fas fa-chevron-down audit-det-chevron"></i>
                        </span>
                    </div>
                    <div class="collapse show" id="detColDesc">
                        <div class="audit-det-card-body">
                            <div class="audit-det-box">
                                <div class="audit-det-scroll audit-det-scroll-desc"><p id="detDescripcion" class="mb-0"></p></div>
                                <div class="audit-det-sub text-end mt-2" id="detCaracteres"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tarjeta plegable: Información adicional -->
                <section class="audit-det-card">
                    <div class="audit-det-card-head collapsed" data-bs-toggle="collapse" data-bs-target="#detColInfo" role="button" aria-expanded="false" aria-controls="detColInfo">
                        <span class="audit-det-card-title"><i class="fas fa-code"></i>Información adicional <span class="audit-det-tag" id="detInfoTipo">JSON</span></span>
                        <span class="audit-det-card-tools">
                            <button type="button" class="btn-win btn-win-sm" onclick="event.stopPropagation();copiarInfo(this)"><i class="fas fa-copy me-1"></i>Copiar</button>
                            <i class="fas fa-chevron-down audit-det-chevron"></i>
                        </span>
                    </div>
                    <div class="collapse" id="detColInfo">
                        <div class="audit-det-card-body">
                            <div class="audit-det-box"><div class="audit-det-scroll audit-det-scroll-json"><pre id="detInfo"></pre></div></div>
                        </div>
                    </div>
                </section>

                <!-- Tarjeta plegable: Datos técnicos -->
                <section class="audit-det-card">
                    <div class="audit-det-card-head collapsed" data-bs-toggle="collapse" data-bs-target="#detColTec" role="button" aria-expanded="false" aria-controls="detColTec">
                        <span class="audit-det-card-title"><i class="fas fa-list-ul"></i>Datos técnicos</span>
                        <span class="audit-det-card-tools">
                            <button type="button" class="btn-win btn-win-sm" onclick="event.stopPropagation();copiarTecnico(this)"><i class="fas fa-copy me-1"></i>Copiar</button>
                            <i class="fas fa-chevron-down audit-det-chevron"></i>
                        </span>
                    </div>
                    <div class="collapse" id="detColTec">
                        <div class="audit-det-card-body">
                            <div class="audit-det-grid">
                                <div><div class="audit-det-label">MÓDULO</div><div class="audit-det-valor" id="detModulo"></div></div>
                                <div><div class="audit-det-label">PROVEEDOR DE ACCESO</div><div class="audit-det-valor" id="detProvider"></div></div>
                                <div><div class="audit-det-label">MÉTODO HTTP</div><div class="audit-det-valor" id="detMetodo"></div></div>
                                <div><div class="audit-det-label">ESTADO</div><div class="audit-det-valor" id="detEstado"></div></div>
                                <div class="audit-det-span"><div class="audit-det-label">URL SOLICITADA</div><div class="audit-det-valor audit-det-wrap" id="detUrl"></div></div>
                                <div class="audit-det-span"><div class="audit-det-label">NAVEGADOR (USER-AGENT)</div><div class="audit-det-valor audit-det-wrap" id="detUa"></div></div>
                                <div class="audit-det-span" id="detErrorWrap"><div class="audit-det-label">MENSAJE DE ERROR</div><div class="audit-det-valor audit-det-wrap" id="detError"></div></div>
                                <div class="audit-det-span"><div class="audit-det-label">CADENA DE HASH (SHA-256)</div><div class="audit-det-valor audit-det-wrap audit-det-mono" id="detHash"></div></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <div class="d-flex justify-content-between align-items-center w-100 gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <small class="audit-det-label"><i class="fas fa-database me-1"></i><span id="detTamanio"></span></small>
                        <?php if ($permiso_borrar): ?>
                        <button type="button" class="btn-win btn-win-sm btn-del-registro" onclick="eliminarRegistro(_detBtn)" title="Eliminar este registro del histórico" data-tooltip="Eliminar registro" data-tooltip-theme="danger">
                            <i class="fas fa-trash-can me-1"></i>Eliminar registro
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn-win btn-win-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cerrar</button>
                        <button type="button" class="btn-win btn-win-sm" onclick="copiarTodo(this)"><i class="fas fa-copy me-1"></i>Copiar Todo</button>
                        <button type="button" class="btn-win btn-win-sm btn-win-primary" onclick="exportarDetalle()"><i class="fas fa-download me-1"></i>Exportar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/bootstrap5.3.0/bootstrap.bundle.min.js"></script>
<script src="../js/sweetalert2.all.min.js"></script>
<script src="../js/exceljs.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script src="../js/historico.js?v=<?php echo @filemtime(__DIR__ . '/../js/historico.js') ?: time(); ?>"></script>
<script>
/* Configuración para las exportaciones (js/historico_export.js) */
window.HIST_EXPORT = {
    endpoint: 'exportar_historico.php',
    titulo: 'Histórico de Operaciones',
    sistema: 'Sistema SisGesNom®',
    empresa: <?php echo json_encode(defined('COMPANY_NAME') ? COMPANY_NAME : 'SisGesNom', JSON_UNESCAPED_UNICODE); ?>,
    operador: <?php echo json_encode($usuario_operador, JSON_UNESCAPED_UNICODE); ?>,
    filtros: <?php echo json_encode($filtros_resumen, JSON_UNESCAPED_UNICODE); ?>
};

/* Registros COMPLETOS filtrados (sin paginación) para recorrer
   todo el historial desde el modal "Detalles del Registro". */
window.HIST_REGISTROS = <?php echo json_encode($hist_registros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

/* Código de confirmación (captcha) para el vaciado completo del histórico */
window.HIST_CAPTCHA = <?php echo json_encode($captcha_borrar_historico, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../js/historico_export.js?v=<?php echo @filemtime(__DIR__ . '/../js/historico_export.js') ?: time(); ?>"></script>
<script>
/* ============================================================
   Paginación + actualización + borrado del Histórico
   ============================================================ */
function cambiarRegistrosPorPagina(valor) {
    var url = new URL(window.location.href);
    url.searchParams.set('registros_por_pagina', valor);
    url.searchParams.set('pagina', 1);
    url.searchParams.delete('pag');
    window.location.href = url.toString();
}

function jumpToPage() {
    var input = document.getElementById('jumpToPage');
    if (!input) return;
    var pagina = parseInt(input.value, 10);
    var total  = parseInt(input.getAttribute('max'), 10);
    if (pagina >= 1 && pagina <= total) {
        var url = new URL(window.location.href);
        url.searchParams.set('pagina', pagina);
        url.searchParams.delete('pag');
        window.location.href = url.toString();
    }
}

function recargarManteniendo() {
    window.location.reload();
}

/* Auto-actualización */
var _auditAutoRefresh = null;
function _armarAutoRefresh(seg) {
    if (_auditAutoRefresh) { clearInterval(_auditAutoRefresh); _auditAutoRefresh = null; }
    _auditAutoRefresh = setInterval(function () { window.location.reload(); }, seg * 1000);
}
function activarAutoRefresh(seg) {
    _armarAutoRefresh(seg);
    try { sessionStorage.setItem('auditAutoRefresh', seg); } catch (e) {}
    if (window.Swal) {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Auto-actualización cada ' + seg + ' s',
            timer: 2500, showConfirmButton: false, backdrop: false
        });
    }
}
function desactivarAutoRefresh() {
    if (_auditAutoRefresh) { clearInterval(_auditAutoRefresh); _auditAutoRefresh = null; }
    try { sessionStorage.removeItem('auditAutoRefresh'); } catch (e) {}
}
(function () {
    var seg = 0;
    try { seg = parseInt(sessionStorage.getItem('auditAutoRefresh') || '0', 10); } catch (e) {}
    if (seg > 0) { _armarAutoRefresh(seg); }
})();

/* ------------------------------------------------------------
   Modal "Detalles del Registro" (equivalente a
   /facturacion/historico_view.php: copiar, exportar, tamaño...)
   ------------------------------------------------------------ */
var _detActual = null;
var _detLista = [];
var _detIndex = -1;
var _detBtn = null;

function _esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function _detHora12(hms) {
    var p = String(hms || '').split(':');
    if (p.length < 3) return '';
    var h = parseInt(p[0], 10);
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12; if (h === 0) h = 12;
    return ('0' + h).slice(-2) + ':' + p[1] + ':' + p[2] + ' ' + ampm;
}

function _detHora24(hms) { return String(hms || '').slice(0, 8); }

function _detLocalizacion(ip) {
    if (!ip) return 'No disponible';
    if (ip === '127.0.0.1' || ip === '::1') return 'Localhost';
    if (ip.indexOf('192.168.') === 0) return 'Red local';
    if (ip.indexOf('10.') === 0) return 'Red privada';
    if (ip.indexOf('172.') === 0) {
        var o = parseInt(ip.split('.')[1], 10);
        if (o >= 16 && o <= 31) return 'Red privada';
    }
    return 'Externa';
}

function _detClaseBadge(tipo) {
    var t = String(tipo || '').toLowerCase();
    if (t.indexOf('login') !== -1 || t.indexOf('iniciar') !== -1) return 'audit-tipo-login';
    if (t.indexOf('logout') !== -1 || t.indexOf('cerrar') !== -1) return 'audit-tipo-salir';
    if (t.indexOf('eliminar') !== -1 || t.indexOf('borrar') !== -1 || t.indexOf('delete') !== -1 || t.indexOf('purgar') !== -1) return 'audit-tipo-eliminar';
    if (t.indexOf('error') !== -1 || t.indexOf('denegado') !== -1 || t.indexOf('failed') !== -1) return 'audit-tipo-error';
    if (t.indexOf('export') !== -1 || t.indexOf('imprimir') !== -1 || t.indexOf('reporte') !== -1) return 'audit-tipo-exportar';
    if (t.indexOf('editar') !== -1 || t.indexOf('actualizar') !== -1 || t.indexOf('update') !== -1 || t.indexOf('guardar') !== -1 || t.indexOf('cambiar') !== -1) return 'audit-tipo-editar';
    if (t.indexOf('crear') !== -1 || t.indexOf('nuevo') !== -1 || t.indexOf('insert') !== -1 || t.indexOf('registrar') !== -1) return 'audit-tipo-crear';
    return 'audit-tipo-default';
}

function _lblEstadoLog(est) {
    var m = { 'success': 'Satisfactorio', 'failed': 'Falló' };
    return m[est] || est || '—';
}

function abrirDetalle(btn) {
    var d = btn.dataset;
    _detActual = d;
    _detRefrescarLista();
    _detIndex = _detLista.indexOf(btn);
    if (_detIndex < 0) _detIndex = _detIndexPorId(d.id);
    _detBtn = btn;

    var usuario = d.usuario || 'Sistema';

    document.getElementById('detId').textContent = 'ID: #' + (d.id || '');

    var alt = document.getElementById('detAlterado');
    if (alt) { alt.hidden = (d.integro === '1'); }
    document.getElementById('detUsuario').textContent = usuario;
    document.getElementById('detRol').textContent = d.rol || '—';
    document.getElementById('detEmail').textContent = d.email || '—';
    document.getElementById('detNombre').textContent = d.nombre || '—';

    var fecha = '', hora12 = '', hora24 = '';
    if (d.fecha) {
        var partes = String(d.fecha).split(' ');
        var f = (partes[0] || '').split('-');
        if (f.length === 3) fecha = f[2] + '/' + f[1] + '/' + f[0];
        if (partes[1]) { hora12 = _detHora12(partes[1]); hora24 = _detHora24(partes[1]); }
    }
    document.getElementById('detFecha').textContent = fecha || '—';
    var elHora = document.getElementById('detHora');
    elHora.textContent = hora12;
    if (hora24) { elHora.title = 'Formato 24h: ' + hora24; elHora.style.cursor = 'help'; }

    document.getElementById('detTipo').innerHTML =
        '<span class="audit-tipo ' + _detClaseBadge(d.accion) + '">' + _esc(d.accion) + '</span>';

    document.getElementById('detIp').textContent = d.ip || 'No disponible';
    document.getElementById('detLocalizacion').textContent = _detLocalizacion(d.ip);

    var desc = d.desc || 'Sin descripción disponible';
    document.getElementById('detDescripcion').textContent = desc;
    var palabras = desc.split(/\s+/).filter(function (w) { return w.length > 0; }).length;
    document.getElementById('detCaracteres').textContent = desc.length + ' caracteres · ' + palabras + ' palabras';

    var info = d.details || '';
    var infoEl = document.getElementById('detInfo');
    var infoTipo = document.getElementById('detInfoTipo');
    try {
        if (info && info.trim() !== '' && info.trim() !== '{}') {
            infoEl.textContent = JSON.stringify(JSON.parse(info), null, 2);
            infoTipo.textContent = 'JSON';
        } else {
            infoEl.textContent = 'Sin información adicional';
            infoTipo.textContent = 'VACÍO';
        }
    } catch (e) {
        infoEl.textContent = info || 'Sin información adicional';
        infoTipo.textContent = 'TEXTO';
    }

    document.getElementById('detModulo').textContent = d.modulo || '—';
    document.getElementById('detProvider').textContent = d.provider || '—';
    document.getElementById('detMetodo').textContent = d.metodo || '—';
    document.getElementById('detEstado').textContent = _lblEstadoLog(d.estado);
    document.getElementById('detUrl').textContent = d.url || '—';
    document.getElementById('detUa').textContent = d.ua || '—';
    document.getElementById('detHash').textContent = d.hash || '—';

    var errWrap = document.getElementById('detErrorWrap');
    if (d.error) { errWrap.style.display = ''; document.getElementById('detError').textContent = d.error; }
    else { errWrap.style.display = 'none'; }

    var bytes = new Blob([desc + ' ' + info]).size;
    var tam = bytes < 1024 ? bytes + ' bytes'
            : (bytes < 1048576 ? (bytes / 1024).toFixed(2) + ' KB'
            : (bytes / 1048576).toFixed(2) + ' MB');
    document.getElementById('detTamanio').textContent = 'Tamaño: ' + tam;

    _detActualizarNav();

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();
}

/* ------------------------------------------------------------
   Navegación de registros dentro del modal "Detalles del Registro"
   (primero / anterior / siguiente / último + buscador, igual que
   el modal de edición de empleados.php)
   ------------------------------------------------------------ */
function _detRefrescarLista() {
    if (window.HIST_REGISTROS && window.HIST_REGISTROS.length) {
        _detLista = window.HIST_REGISTROS.map(function (r) { return { dataset: r }; });
    } else {
        _detLista = Array.prototype.slice.call(document.querySelectorAll('button.btn-ver-detalle'));
    }
}

function _detIndexPorId(id) {
    for (var k = 0; k < _detLista.length; k++) {
        if (_detLista[k] && _detLista[k].dataset && String(_detLista[k].dataset.id) === String(id)) return k;
    }
    return -1;
}

function _detEtiqueta(btn) {
    var d = (btn && btn.dataset) || {};
    var txt = '#' + (d.id || '') + ' · ' + (d.usuario || 'Sistema');
    if (d.accion) txt += ' · ' + d.accion;
    return txt;
}

function _detOcultarDropdown() {
    var dd = document.getElementById('detNavDropdown');
    if (dd) dd.style.display = 'none';
}

function _detActualizarNav() {
    var actual = document.getElementById('detRegistroActual');
    var total = document.getElementById('detTotalRegistros');
    if (actual) actual.textContent = _detIndex >= 0 ? (_detIndex + 1) : 0;
    if (total) total.textContent = _detLista.length;

    var bP = document.getElementById('detBtnPrimero');
    var bA = document.getElementById('detBtnAnterior');
    var bS = document.getElementById('detBtnSiguiente');
    var bU = document.getElementById('detBtnUltimo');
    var alInicio = _detIndex <= 0;
    var alFinal = _detIndex < 0 || _detIndex >= _detLista.length - 1;

    if (bP) bP.disabled = alInicio;
    if (bA) bA.disabled = alInicio;
    if (bS) bS.disabled = alFinal;
    if (bU) bU.disabled = alFinal;

    var inp = document.getElementById('detNavBuscar');
    if (inp && _detIndex >= 0) {
        var d = _detLista[_detIndex].dataset || {};
        inp.value = '';
        inp.placeholder = '🔍 ' + ((d.usuario || 'Registro') + ' · ' + (d.accion || '')) + '...';
    }
    _detOcultarDropdown();
}

function detNavegar(act) {
    if (!_detLista.length) return;
    var nx = _detIndex;
    if (act === 'primero') nx = 0;
    else if (act === 'anterior') nx = _detIndex - 1;
    else if (act === 'siguiente') nx = _detIndex + 1;
    else if (act === 'ultimo') nx = _detLista.length - 1;
    if (nx >= 0 && nx < _detLista.length) abrirDetalle(_detLista[nx]);
}

function detRenderDropdown(q) {
    var dd = document.getElementById('detNavDropdown');
    if (!dd) return;
    if (!_detLista.length) _detRefrescarLista();

    q = String(q || '').trim().toLowerCase();
    var res = _detLista.map(function (b, i) { return { b: b, i: i }; }).filter(function (o) {
        if (!q) return true;
        var d = o.b.dataset || {};
        var txt = ((d.id || '') + ' ' + (d.usuario || '') + ' ' + (d.email || '') + ' ' + (d.accion || '') + ' ' + (d.modulo || '') + ' ' + (d.desc || '')).toLowerCase();
        return txt.indexOf(q) !== -1;
    }).slice(0, 100);

    if (!res.length) {
        dd.innerHTML = '<div class="audit-nav-vacio">Sin coincidencias</div>';
    } else {
        dd.innerHTML = res.map(function (o) {
            var cls = o.i === _detIndex ? ' active' : '';
            return '<button type="button" class="audit-nav-item' + cls + '" onclick="detIrA(' + o.i + ')">' + _esc(_detEtiqueta(o.b)) + '</button>';
        }).join('');
    }
    dd.style.display = 'block';

    var clr = document.getElementById('detNavClear');
    if (clr) clr.style.display = q ? 'flex' : 'none';
}

function detIrA(idx) {
    if (idx >= 0 && idx < _detLista.length) abrirDetalle(_detLista[idx]);
}

function detLimpiarBusqueda() {
    var inp = document.getElementById('detNavBuscar');
    if (inp) { inp.value = ''; inp.focus(); }
    _detOcultarDropdown();
}

function detTeclaBusqueda(e) {
    var dd = document.getElementById('detNavDropdown');
    var items = dd ? dd.querySelectorAll('.audit-nav-item') : [];
    if (e.key === 'Enter') {
        e.preventDefault();
        if (items.length) items[0].click();
    } else if (e.key === 'Escape') {
        _detOcultarDropdown();
    } else if (e.key === 'ArrowDown' && items.length) {
        e.preventDefault();
        items[0].focus();
    }
}

document.addEventListener('click', function (e) {
    var sel = document.querySelector('.audit-det-nav-selector');
    if (sel && !sel.contains(e.target)) _detOcultarDropdown();
});
document.addEventListener('keydown', function (e) {
    var modal = document.getElementById('modalDetalle');
    if (!modal || !modal.classList.contains('show')) return;
    var tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); detNavegar('anterior'); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); detNavegar('siguiente'); }
});

function _detTexto() {
    var d = _detActual || {};
    var sistema = (window.HIST_EXPORT && window.HIST_EXPORT.sistema) || 'Sistema SisGesNom®';
    var info = document.getElementById('detInfo');
    return 'DETALLES DEL REGISTRO HISTÓRICO\n' +
        '=====================================\n' +
        'ID: #' + (d.id || '') + '\n' +
        'Usuario: ' + (d.usuario || 'Sistema') + (d.email ? ' (' + d.email + ')' : '') + '\n' +
        'Proveedor: ' + (d.provider || '—') + '\n' +
        'Fecha: ' + document.getElementById('detFecha').textContent + '\n' +
        'Hora: ' + document.getElementById('detHora').textContent + '\n' +
        'Tipo: ' + (d.accion || '') + '\n' +
        'Módulo: ' + (d.modulo || '—') + '\n' +
        'IP: ' + (d.ip || '—') + ' (' + _detLocalizacion(d.ip) + ')\n' +
        'Método: ' + (d.metodo || '—') + '\n' +
        'URL: ' + (d.url || '—') + '\n' +
        'Estado: ' + _lblEstadoLog(d.estado) + (d.error ? ' — ' + d.error : '') + '\n' +
        'Navegador: ' + (d.ua || '—') + '\n' +
        'Hash: ' + (d.hash || '—') + '\n\n' +
        'DESCRIPCIÓN:\n' + (d.desc || '') + '\n\n' +
        'INFORMACIÓN ADICIONAL:\n' + (info ? info.textContent : '') + '\n\n' +
        '=====================================\n' +
        'Sistema: ' + sistema;
}

function _toastExito(titulo, texto) {
    if (!window.Swal) return;
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: titulo, text: texto,
        timer: 2200, showConfirmButton: false, timerProgressBar: true,
        backdrop: false,
        customClass: { container: 'audit-swal-borrar' }
    });
}

function copiarDetalle(btn) {
    var contenido = _detTexto();
    var marcar = function () {
        if (!btn) return;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado';
        setTimeout(function () { btn.innerHTML = original; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(contenido).then(function () {
            _toastExito('¡Datos copiados!', 'La información del registro se copió al portapapeles.');
            marcar();
        }).catch(function () { _copiarRespaldo(contenido); });
    } else {
        _copiarRespaldo(contenido);
    }
}

function copiarInfo(btn) {
    var info = document.getElementById('detInfo');
    if (!info) return;
    var contenido = info.textContent || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(contenido).then(function () {
            _toastExito('¡Copiado!', 'La información adicional se copió al portapapeles.');
        }).catch(function () { _copiarRespaldo(contenido); });
    } else {
        _copiarRespaldo(contenido);
    }
}

function copiarTecnico(btn) {
    var d = _detActual || {};
    var lineas = [
        'DATOS TÉCNICOS DEL REGISTRO',
        '=====================================',
        'ID: #' + (d.id || ''),
        'Módulo: ' + (d.modulo || '—'),
        'Proveedor de acceso: ' + (d.provider || '—'),
        'Método HTTP: ' + (d.metodo || '—'),
        'Estado: ' + _lblEstadoLog(d.estado),
        'URL solicitada: ' + (d.url || '—'),
        'Navegador (User-Agent): ' + (d.ua || '—')
    ];
    if (d.error) { lineas.push('Mensaje de error: ' + d.error); }
    lineas.push('Cadena de hash (SHA-256): ' + (d.hash || '—'));
    var contenido = lineas.join('\n');
    var marcar = function () {
        if (!btn) return;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado';
        setTimeout(function () { btn.innerHTML = original; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(contenido).then(function () {
            _toastExito('¡Datos copiados!', 'Los datos técnicos se copiaron al portapapeles.');
            marcar();
        }).catch(function () { _copiarRespaldo(contenido); });
    } else {
        _copiarRespaldo(contenido);
    }
}

function copiarTodo(btn) {
    var contenido = _detTexto();
    var marcar = function () {
        if (!btn) return;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado';
        setTimeout(function () { btn.innerHTML = original; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(contenido).then(function () {
            _toastExito('¡Datos copiados!', 'Se copió todo el contenido del registro.');
            marcar();
        }).catch(function () { _copiarRespaldo(contenido); });
    } else {
        _copiarRespaldo(contenido);
    }
}

function _copiarRespaldo(contenido) {
    if (!window.Swal) { window.prompt('Copie el texto:', contenido); return; }
    Swal.fire({
        title: 'Copie manualmente',
        input: 'textarea',
        inputValue: contenido,
        customClass: { container: 'audit-swal-borrar' },
        confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
    });
}

function exportarDetalle() {
    var contenido = _detTexto();
    var d = _detActual || {};
    var blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'registro_historico_' + (d.id || 'registro') + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
    _toastExito('¡Exportado!', 'Registro exportado correctamente.');
}

/* ------------------------------------------------------------
   Eliminar UN registro desde la tabla
   ------------------------------------------------------------ */
function eliminarRegistro(btn) {
    var d = (btn && btn.dataset) || {};
    var id = d.id || null;
    var usuario = d.usuario || 'Desconocido';
    var accion = d.accion || '';
    var operador = (window.HIST_EXPORT && window.HIST_EXPORT.operador) || '';

    if (!id) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El ID del registro es inválido.',
            customClass: { container: 'audit-swal-borrar' },
            confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
        });
        return;
    }

    Swal.fire({
        title: '¿Estás seguro?',
        html: '<div class="audit-del-reg">' +
              '<p class="audit-del-reg-linea">Se eliminará el registro ID: <strong>#' + _esc(id) + '</strong><br>' +
              'Del usuario: <strong>' + _esc(usuario) + '</strong>' +
              (accion ? '<br>Tipo de operación: <strong>' + _esc(accion) + '</strong>' : '') + '</p>' +
              (operador ? '<div class="audit-del-reg-operador"><i class="fas fa-user-shield me-2"></i>Operación hecha por: <strong>' + _esc(operador) + '</strong></div>' : '') +
              '<div class="audit-del-reg-aviso"><i class="fas fa-link me-2"></i>La cadena de hashes de los registros restantes se recalculará automáticamente. La acción quedará auditada.</div>' +
              '</div>',
        icon: 'warning',
        customClass: { container: 'audit-swal-borrar audit-swal-del' },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        showLoaderOnConfirm: true,
        allowOutsideClick: false,
        preConfirm: function () {
            var fd = new FormData();
            fd.append('accion', 'eliminar_registro');
            fd.append('id', id);
            return fetch('eliminar_historico.php', { method: 'POST', body: fd })
                .then(function (r) { if (!r.ok) throw new Error('Error HTTP: ' + r.status); return r.json(); })
                .catch(function (e) { Swal.showValidationMessage('Error de conexión: ' + e.message); });
        }
    }).then(function (res) {
        if (res.isConfirmed && res.value && res.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                text: res.value.message || '',
                customClass: { container: 'audit-swal-borrar' },
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            }).then(function () { window.location.reload(); });
        } else if (res.isConfirmed && res.value && res.value.success === false) {
            Swal.fire({
                icon: 'error',
                title: 'No se pudo eliminar',
                text: res.value.message || '',
                customClass: { container: 'audit-swal-borrar' },
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            });
        }
    });
}

/* ------------------------------------------------------------
   Modal de solicitud de eliminación + vaciado completo
   ------------------------------------------------------------ */
function confirmarVaciadoCompleto() {
    var codigoEsperado = (window.HIST_CAPTCHA || '').toString();
    Swal.fire({
        title: false,
        html: '<div class="audit-borrar-titlebar">' +
              '<span class="audit-borrar-titlebar-titulo"><i class="fas fa-trash-alt me-2"></i>Vaciado completo del histórico</span>' +
              '<button type="button" class="audit-borrar-titlebar-cerrar" id="auditCerrarVaciado" title="Cerrar" aria-label="Cerrar"><i class="fas fa-times"></i></button>' +
              '</div>' +
              '<div class="audit-borrar-wrap">' +
              '<div class="audit-borrar-alerta text-danger"><i class="fas fa-exclamation-circle me-2"></i>Confirmación crítica requerida</div>' +
              '<div class="audit-borrar-palabra text-danger">' + _esc(codigoEsperado) + '</div>' +
              '<div class="audit-borrar-ayuda"><i class="fas fa-keyboard me-2"></i>Escribe exactamente el código de 8 caracteres de arriba (mayúsculas y minúsculas) en el campo de abajo</div>' +
              '<div class="audit-borrar-info">' +
              '<div class="titulo"><i class="fas fa-info-circle me-2"></i>Importante</div>' +
              '<ul>' +
              '<li>Esta acción es permanente e irreversible.</li>' +
              '<li>Se eliminarán <b>todos</b> los registros del histórico.</li>' +
              '<li>El código debe escribirse exactamente como se muestra (distingue mayúsculas y minúsculas).</li>' +
              '<li>Se registrará esta acción en el sistema.</li>' +
              '</ul></div></div>',
        customClass: { container: 'audit-swal-borrar audit-swal-del' },
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Confirmar eliminación',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#ef4444',
        showLoaderOnConfirm: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        input: 'text',
        inputPlaceholder: 'Escriba el código mostrado arriba para confirmar...',
        inputAttributes: { 'autocomplete': 'off', 'spellcheck': 'false', 'autocapitalize': 'off' },
        didOpen: function () {
            var btnCerrar = document.getElementById('auditCerrarVaciado');
            if (btnCerrar) {
                btnCerrar.addEventListener('click', function () { Swal.close(); });
            }
        },
        preConfirm: function (inputText) {
            var captcha = typeof inputText === 'string' ? inputText.trim() : '';
            if (captcha === '' || captcha !== codigoEsperado) {
                Swal.showValidationMessage('El código ingresado no coincide');
                return false;
            }
            var formData = new FormData();
            formData.append('accion', 'eliminar_todo');
            formData.append('captcha', captcha);
            return fetch('eliminar_historico.php', { method: 'POST', body: formData })
                .then(function (response) {
                    if (!response.ok) throw new Error('Error HTTP: ' + response.status);
                    return response.json();
                })
                .catch(function (error) {
                    Swal.showValidationMessage('Error de conexión: ' + error.message);
                });
        }
    }).then(function (result) {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                title: 'Histórico vaciado',
                html: '<div style="padding:6px 0;">Registros eliminados: <b>' + (result.value.eliminados || 0) + '</b>' +
                      (result.value.archivo ? '<div style="margin-top:8px;font-size:.85rem;opacity:.85;"><i class="fas fa-file-export me-2"></i>Respaldo guardado: <b>' + _esc(result.value.archivo) + '</b></div>' : '') + '</div>',
                icon: 'success',
                customClass: { container: 'audit-swal-borrar' },
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            }).then(function () { window.location.reload(); });
        } else if (result.isConfirmed && result.value && result.value.success === false) {
            Swal.fire({
                icon: 'error',
                title: 'No se pudo eliminar',
                text: result.value.message || '',
                customClass: { container: 'audit-swal-borrar' },
                confirmButtonText: '<i class="fas fa-check me-2"></i>Entendido'
            });
        }
    });
}
</script>
</body>
</html>
