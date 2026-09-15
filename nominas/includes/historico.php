<?php
// includes/historico.php - Sistema de Histórico de Actividades (logs de operaciones)
// Tabla: historico_operaciones

/**
 * Formatea una fecha/hora de MySQL a formato 12 horas (ej: 14/09/2026 08:15:22 p. m.)
 */
function formatoFechaHora12h($fecha_hora) {
    if (empty($fecha_hora) || $fecha_hora === '0000-00-00 00:00:00') return '';
    $ts = strtotime($fecha_hora);
    return date('d/m/Y h:i:s A', $ts);
}

/**
 * Registrar operación en el histórico del sistema.
 * @param string $tipo Tipo de operación (LOGIN, LOGOUT, CREAR_*, EDITAR_*, ELIMINAR_*, CONFIGURACION, etc.)
 * @param string $descripcion Descripción detallada en lenguaje natural
 * @param PDO|null $pdo Conexión (opcional; usa la global $pdo del sistema)
 * @param int|null $usuario_id ID del usuario (opcional; usa la sesión actual)
 * @return bool
 */
function registrarOperacion($tipo, $descripcion, $pdo = null, $usuario_id = null) {
    global $pdo;
    $db = ($pdo instanceof PDO) ? $pdo : (isset($pdo) && $pdo instanceof PDO ? $pdo : null);
    if (!$db) return false;

    try {
        if ($usuario_id === null) {
            $usuario_id = (int)($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Nombre del usuario (CONCAT nombre + apellidos de clasif_usuarios)
        $usuario_nombre = null;
        if ($usuario_id > 0) {
            try {
                $stmt_u = $db->prepare("SELECT CONCAT(nombre, ' ', apellidos) AS nombre_completo FROM clasif_usuarios WHERE id = ?");
                $stmt_u->execute([$usuario_id]);
                $usuario_nombre = $stmt_u->fetchColumn();
            } catch (PDOException $e) {
                $usuario_nombre = null;
            }
        }
        if (!$usuario_nombre) {
            $usuario_nombre = trim(($_SESSION['usuario_nombre'] ?? '') . ' ' . ($_SESSION['usuario_apellidos'] ?? ''));
            if (trim($usuario_nombre) === '') $usuario_nombre = $_SESSION['username'] ?? null;
        }

        $stmt = $db->prepare("INSERT INTO historico_operaciones
            (usuario_id, operacion, descripcion, ip_address, fecha_hora, usuario_nombre)
            VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$usuario_id, $tipo, $descripcion, $ip, $usuario_nombre]);
        return true;
    } catch (Exception $e) {
        error_log('Error al registrar operación: ' . $e->getMessage());
        return false;
    }
}

/**
 * Último acceso (LOGIN) de un usuario, para el mensaje de bienvenida.
 * @param PDO $pdo Conexión
 * @param int $usuario_id
 * @return string|null fecha_hora del acceso anterior al actual, o null si no hay
 */
function obtenerUltimoAcceso($pdo, $usuario_id) {
    if (!$pdo || !$usuario_id) return null;
    try {
        $stmt = $pdo->prepare("SELECT fecha_hora FROM historico_operaciones
                               WHERE usuario_id = ? AND operacion = 'LOGIN'
                               ORDER BY fecha_hora DESC LIMIT 1 OFFSET 1");
        $stmt->execute([$usuario_id]);
        $f = $stmt->fetchColumn();
        return $f ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Determina icono FontAwesome + color según el tipo de operación.
 */
function obtenerIconoTipo($operacion) {
    $mapa = [
        'LOGIN'             => ['fa-sign-in-alt',     'success'],
        'LOGOUT'            => ['fa-sign-out-alt',    'warning'],
        'CREAR_USUARIO'     => ['fa-user-plus',       'success'],
        'EDITAR_USUARIO'    => ['fa-user-edit',       'info'],
        'ELIMINAR_USUARIO'  => ['fa-user-minus',      'danger'],
        'CAMBIO_ESTADO'     => ['fa-toggle-on',       'info'],
        'CAMBIO_PASSWORD'   => ['fa-key',             'warning'],
        'RESET_PASSWORD'    => ['fa-key',             'warning'],
        'CREAR_EMPLEADO'    => ['fa-user-plus',       'success'],
        'EDITAR_EMPLEADO'   => ['fa-user-edit',       'info'],
        'BAJA_EMPLEADO'     => ['fa-user-minus',      'danger'],
        'CREAR_NOMINA'      => ['fa-file-invoice',    'success'],
        'EDITAR_NOMINA'     => ['fa-edit',            'info'],
        'FAJA_NOMINA'       => ['fa-file-invoice-dollar', 'primary'],
        'ELIMINAR_NOMINA'   => ['fa-trash',           'danger'],
        'CERRAR_NOMINA'     => ['fa-lock',            'warning'],
        'CREAR_CLASIFICADOR'=> ['fa-plus-circle',     'success'],
        'EDITAR_CLASIFICADOR'=> ['fa-edit',           'info'],
        'ELIMINAR_CLASIFICADOR'=> ['fa-trash',        'danger'],
        'CREAR_BANCO'       => ['fa-building-columns','success'],
        'ACEPTAR_BANCO'     => ['fa-check-circle',    'success'],
        'RECHAZAR_BANCO'    => ['fa-xmark-circle',    'danger'],
        'EXPORTAR_BANCO'    => ['fa-file-export',     'primary'],
        'GENERAR_SOLAPIN'   => ['fa-id-card',         'info'],
        'IMPRIMIR_SOLAPINES'=> ['fa-print',           'primary'],
        'CONFIGURACION'     => ['fa-gear',            'secondary'],
        'ACTUALIZAR_CONFIG' => ['fa-gear',            'info'],
        'BACKUP'            => ['fa-database',        'primary'],
        'RESTAURAR'         => ['fa-clock-rotate-left','warning'],
        'REPORTE'           => ['fa-chart-bar',       'info'],
        'EXPORTAR'          => ['fa-file-export',     'primary'],
        'VACACIONES'        => ['fa-palm-tree',       'success'],
        'SUBMATOR'          => ['fa-book',            'info'],
        'SNC225'            => ['fa-file-lines',      'info'],
        'ACTA'              => ['fa-file-signature',  'info'],
        'VISTA_HISTORICO'   => ['fa-clock-rotate-left','info'],
        'LIMPIAR_HISTORICO' => ['fa-broom',           'danger'],
        'BORRAR_REGISTRO'   => ['fa-trash',           'danger'],
    ];
    if (isset($mapa[$operacion])) {
        return ['icono' => $mapa[$operacion][0], 'clase' => $mapa[$operacion][1]];
    }
    // Coincidencia parcial (p. ej. CREAR_ALGO específico)
    $op = strtoupper($operacion);
    if (strpos($op, 'ELIMINAR') !== false) return ['icono' => 'fa-trash', 'clase' => 'danger'];
    if (strpos($op, 'CREAR') !== false || substr($op, 0, 5) === 'ALTA' || strpos($op, 'INSERT') !== false) return ['icono' => 'fa-plus-circle', 'clase' => 'success'];
    if (strpos($op, 'EDITAR') !== false || strpos($op, 'MODIF') !== false || substr($op, 0, 6) === 'EDICIO') return ['icono' => 'fa-edit', 'clase' => 'info'];
    if (strpos($op, 'CERRAR') !== false) return ['icono' => 'fa-lock', 'clase' => 'warning'];
    if (strpos($op, 'EXPORT') !== false || strpos($op, 'IMPRIMIR') !== false || strpos($op, 'PDF') !== false) return ['icono' => 'fa-file-export', 'clase' => 'primary'];
    return ['icono' => 'fa-circle-info', 'clase' => 'secondary'];
}

/**
 * Tipos únicos de operaciones registrados (para el filtro).
 */
function obtenerTiposOperacion($pdo) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT operacion FROM historico_operaciones WHERE operacion IS NOT NULL AND operacion <> '' ORDER BY operacion");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Estadísticas generales del histórico.
 */
function obtenerEstadisticasHistorial($pdo) {
    $def = ['total' => 0, 'hoy' => 0, 'usuarios_activos' => 0, 'tipos_unicos' => 0];
    if (!$pdo) return $def;
    try {
        $r = [];
        $r['total'] = (int)$pdo->query("SELECT COUNT(*) FROM historico_operaciones")->fetchColumn();
        $r['hoy'] = (int)$pdo->query("SELECT COUNT(*) FROM historico_operaciones WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
        $r['usuarios_activos'] = (int)$pdo->query("SELECT COUNT(DISTINCT usuario_id) FROM historico_operaciones WHERE usuario_id IS NOT NULL")->fetchColumn();
        $r['tipos_unicos'] = (int)$pdo->query("SELECT COUNT(DISTINCT operacion) FROM historico_operaciones")->fetchColumn();
        return $r;
    } catch (PDOException $e) {
        return $def;
    }
}

/**
 * Lista de usuarios con nombre completo (para filtro de usuario).
 */
function obtenerUsuariosParaFiltro($pdo) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellidos) AS nombre_completo, usuario, activo FROM clasif_usuarios ORDER BY nombre, apellidos");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}