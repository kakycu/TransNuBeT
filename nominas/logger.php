<?php
// ============================================================
// logger.php - Sistema de auditoría reutilizable
// ------------------------------------------------------------
// Función logAction(): registra en la tabla audit_logs cada
// operación relevante (login, logout, create, update, delete,
// view, export, error, access_denied) junto con datos de
// contexto (IP real, user agent, método HTTP, URL) y un
// hash_chain encadenado que permite detectar manipulaciones.
//
// USO:
//   require_once 'config/database.php';   // define $pdo
//   require_once 'logger.php';
//   logAction('create', 'usuarios', 'Se creó el usuario x', ['clave' => 'valor']);
//
// REQUISITOS:
//   * PDO con prepared statements (jamás concatenación).
//   * Nunca guardar contraseñas, tokens ni cookies en details.
//   * La zona horaria se fija al inicio: America/Havana.
// ============================================================

// Zona horaria única para toda la auditoría (configurable aquí).
if (date_default_timezone_get() !== 'America/Havana') {
    date_default_timezone_set('America/Havana');
}

// ------------------------------------------------------------
// CATÁLOGO DE ACCIONES DE AUDITORÍA
// ------------------------------------------------------------
// El action_type lo decide el SISTEMA según cada operación y NO
// está limitado por la base de datos (columna VARCHAR). Esta lista
// es el catálogo oficial: action => descripción breve. Sirve de
// documentación y también alimenta los filtros de historico.php.
// Si se añade una operación nueva, se registra AQUÍ con el mismo
// action_type que se pase a logAction().
// ------------------------------------------------------------
if (!defined('LOG_ACCIONES')) {
    define('LOG_ACCIONES', [
        // --- Autenticación / sesión ---
        'iniciar_sesion'              => 'Inicio de sesión (usuario/contraseña)',
        'iniciar_sesion_google'       => 'Inicio de sesión con Google',
        'cerrar_sesion'               => 'Cierre de sesión',
        'solicitar_recuperar_password'=> 'Solicitud de recuperación de contraseña',
        'verificar_token_recuperacion'=> 'Verificación de token de recuperación',
        'restablecer_password'        => 'Restablecimiento de contraseña por token',
        // --- Usuarios del sistema ---
        'crear_usuario'               => 'Crear usuario',
        'editar_usuario'              => 'Editar usuario',
        'activar_usuario'             => 'Activar usuario',
        'desactivar_usuario'          => 'Desactivar usuario',
        'eliminar_usuario'            => 'Eliminar usuario',
        'cambiar_password'            => 'Cambio de contraseña propia',
        'resetear_password_usuario'   => 'Reset de contraseña de un usuario',
        'descartar_solicitud_reset'   => 'Descartar solicitud de reset pendiente',
        'restablecer_password_pendiente' => 'Restablecer contraseña pendiente',
        // --- Trabajadores ---
        'crear_trabajador'            => 'Crear trabajador',
        'editar_trabajador'           => 'Editar trabajador',
        'dar_baja_trabajador'         => 'Dar de baja trabajador',
        'reactivar_trabajador'        => 'Reactivar trabajador',
        'eliminar_trabajador'         => 'Eliminar trabajador',
        'exportar_anexo14'            => 'Exportar Anexo 14',
        // --- Nóminas ---
        'generar_nomina_automatica'   => 'Generar nómina automática',
        'regenerar_nomina'            => 'Regenerar nómina',
        'generar_nomina_extraordinaria'=> 'Generar nómina extraordinaria',
        'generar_nomina_bono'         => 'Generar nómina de bono',
        'generar_nomina_ajuste'       => 'Generar nómina de ajuste',
        'agregar_vacaciones_nomina'   => 'Agregar vacaciones a nómina',
        'agregar_bono_trabajador'     => 'Agregar bono a trabajador',
        'agregar_extraordinaria_trabajador' => 'Agregar extraordinaria a trabajador',
        'agregar_trabajador_nomina_automatica' => 'Agregar trabajador a nómina automática',
        'actualizar_nomina_borrador'  => 'Actualizar nómina en borrador',
        'eliminar_nomina_completa'    => 'Eliminar nómina completa',
        'eliminar_nomina_borradores'  => 'Eliminar nóminas en borrador',
        'eliminar_nomina_individual'  => 'Eliminar nómina individual',
        'contabilizar_nomina'         => 'Contabilizar nómina',
        'revertir_nomina'             => 'Revertir nómina contabilizada',
        'corregir_cuadre_nominas'     => 'Corregir cuadre de nóminas',
        'corregir_cuadre_pendientes'  => 'Corregir cuadre de pendientes',
        'corregir_cierres_nomina'     => 'Corregir cierres de nómina',
        'exportar_nomina_sc406'       => 'Exportar nómina oficial (SC-4-06)',
        'exportar_nomina_acreditativa'=> 'Exportar nómina acreditativa BANDEC',
        // --- Clasificadores ---
        'crear_clasificador'          => 'Crear clasificador',
        'editar_clasificador'         => 'Editar clasificador',
        'eliminar_clasificador'       => 'Eliminar clasificador',
        'cambiar_estado_clasificador' => 'Cambiar estado de clasificador',
        'exportar_clasificador'       => 'Exportar clasificador',
        // --- Configuración ---
        'guardar_configuracion_general' => 'Guardar configuración general',
        'guardar_datos_entidad'       => 'Guardar datos de la entidad',
        'guardar_rangos_impuesto'     => 'Guardar rangos de impuesto',
        'agregar_tasa_contribucion'   => 'Agregar tasa de contribución',
        'eliminar_tasa_contribucion'  => 'Eliminar tasa de contribución',
        'guardar_configuracion_correo'=> 'Guardar configuración de correo',
        'guardar_configuracion_google'=> 'Guardar configuración de Google OAuth',
        'probar_configuracion_correo' => 'Probar configuración de correo',
        // --- Vacaciones ---
        'registrar_movimiento_vacaciones' => 'Registrar movimiento en submayor de vacaciones',
        'exportar_submayor_vacaciones'=> 'Exportar submayor de vacaciones',
        // --- Reportes / exportaciones generales ---
        'exportar_reporte'            => 'Exportar reporte (PDF/Excel/PNG)',
        'exportar_trabajadores_sin_nomina' => 'Exportar trabajadores sin nómina',
        'exportar_trabajadores_sin_cuenta' => 'Exportar trabajadores sin cuenta bancaria',
        'exportar_cumpleanos'         => 'Exportar cumpleaños',
        'generar_solapines'           => 'Generar solapines',
        'generar_solapin'             => 'Generar solapín individual',
        // --- Sistema ---
        'crear_backup_base_datos'     => 'Crear backup de la base de datos',
        'restaurar_base_datos'        => 'Restaurar la base de datos',
        'acceso_denegado'             => 'Intento de acceso a un módulo sin permisos',
        'purgar_logs_antiguos'        => 'Limpieza automática de registros antiguos',
        'eliminar_historico'          => 'Eliminación total del histórico de operaciones',
        'eliminar_historico_parcial'  => 'Eliminación parcial del histórico de operaciones',
        'eliminar_historico_registro' => 'Eliminación de un registro del histórico de operaciones',
        'error_sistema'               => 'Error interno no clasificado',
    ]);
}

// ------------------------------------------------------------
// VALORES PERMITIDOS (deben coincidir con los ENUM de la tabla).
// Pueden sobrescribirse definiendo estas constantes ANTES de
// incluir este archivo.
// ------------------------------------------------------------
// NOTA: action_type ya NO se valida contra una lista: lo decide
// el sistema (catálogo LOG_ACCIONES) y es un VARCHAR en la BD.
if (!defined('LOG_AUTH_PROVIDERS')) {
    define('LOG_AUTH_PROVIDERS', ['local', 'google', 'system', 'anonimo']);
}
if (!defined('LOG_STATUSES')) {
    define('LOG_STATUSES', ['success', 'failed']);
}
if (!defined('LOG_STATUS_LABELS')) {
    define('LOG_STATUS_LABELS', ['success' => 'Satisfactorio', 'failed' => 'Falló']);
}

// Devuelve la etiqueta en español del estado de auditoría.
if (!function_exists('etiquetaEstadoLog')) {
    function etiquetaEstadoLog(string $estado): string
    {
        return LOG_STATUS_LABELS[$estado] ?? $estado;
    }
}

// Claves/sufijos que NUNCA deben persistirse en details
// (contraseñas, tokens, cookies, secretos, etc.).
if (!defined('LOG_FORBIDDEN_KEYS')) {
    define('LOG_FORBIDDEN_KEYS', ['password', 'pass', 'contrase',
        'contrasena', 'token', 'cookie', 'session_cookie', 'secret',
        'api_key', 'apikey', 'jwt', 'credential', 'authorization',
        'access_token', 'refresh_token', 'auth_code']);
}

/**
 * Trunca un texto a la longitud máxima de una columna.
 * Usa mb_substr cuando está disponible para no partir
 * caracteres multibyte (utf8mb4).
 */
function truncarParaAuditoria(string $texto, int $max): string {
    $texto = trim($texto);
    if (strlen($texto) <= $max) {
        return $texto;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $max, 'UTF-8');
    }
    return substr($texto, 0, $max);
}

/**
 * Devuelve la IP real del cliente, en este orden:
 *   CF-Connecting-IP → X-Forwarded-For (primera IP) → X-Real-IP → REMOTE_ADDR
 * Siempre se valida con filter_var() y se devuelve algo válido.
 */
function obtenerIpRealAuditoria(): string {
    $candidatas = [];

    // 1) Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidatas[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    // 2) X-Forwarded-For: se usa la PRIMERA IP de la lista
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $primera = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        $candidatas[] = trim($primera);
    }

    // 3) X-Real-IP
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $candidatas[] = $_SERVER['HTTP_X_REAL_IP'];
    }

    // 4) REMOTE_ADDR (fuente directa de la conexión)
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidatas[] = $_SERVER['REMOTE_ADDR'];
    }

    foreach ($candidatas as $ip) {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

/**
 * Elimina recursivamente de $detalles cualquier clave que pueda
 * contener secretos (contraseñas, tokens, cookies, etc.).
 * Regla estricta: NUNCA guardar este tipo de datos.
 */
function limpiarDetallesAuditoria(array $detalles): array {
    foreach ($detalles as $clave => $valor) {
        $clave_baja = mb_strtolower((string)$clave, 'UTF-8');
        $prohibida = false;
        foreach (LOG_FORBIDDEN_KEYS as $palabra) {
            if (strpos($clave_baja, $palabra) !== false) {
                $prohibida = true;
                break;
            }
        }
        if ($prohibida) {
            unset($detalles[$clave]);
            continue;
        }
        if (is_array($valor)) {
            $detalles[$clave] = limpiarDetallesAuditoria($valor);
        }
    }
    return $detalles;
}

/**
 * Ordena recursivamente las claves de un valor JSON decodificado
 * (stdClass / array) para que la representación canónica sea
 * independiente del ORDEN de las claves.
 *
 * MySQL NO garantiza el orden de las claves en columnas JSON
 * (empíricamente las devuelve reordenadas), así que sin este
 * orden la verificación del hash_chain fallaría en objetos con
 * varias claves.
 */
function ordenarClavesDeterministicas($dato) {
    if ($dato instanceof stdClass) {
        $props = get_object_vars($dato);
        ksort($props);
        foreach ($props as $k => $v) {
            $props[$k] = ordenarClavesDeterministicas($v);
        }
        return (object)$props;
    }
    if (is_array($dato)) {
        foreach ($dato as $k => $v) {
            $dato[$k] = ordenarClavesDeterministicas($v);
        }
    }
    return $dato;
}

/**
 * Representación canónica de details para el hash_chain.
 *
 * MySQL NORMALIZA la columna JSON al almacenarla (añade espacios
 * tras cada ':' y ',' y puede REORDENAR las claves). Para que el
 * hash_chain calculado al insertar coincida con el que se recalcula
 * al verificar (historico.php):
 *   1. se decodifica el JSON,
 *   2. se ordenan las claves recursivamente (orden determinístico),
 *   3. se re-encodea a su forma compacta (decode + encode).
 *   - null / JSON inválido -> 'NULL'
 *   - resto                -> JSON compacto con json_encode()
 */
function canonicalDetallesAuditoria(?string $detailsJson): string {
    if ($detailsJson === null) {
        return 'NULL';
    }
    $decodificado = json_decode($detailsJson);
    if ($decodificado === null && json_last_error() !== JSON_ERROR_NONE) {
        return 'NULL';
    }
    $compacto = json_encode(
        ordenarClavesDeterministicas($decodificado),
        JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );
    return ($compacto === false) ? 'NULL' : $compacto;
}

/**
 * Registra una operación en la tabla audit_logs.
 *
 * Parámetros:
 *   $actionType    : acción CONCRETA definida por el sistema y documentada
 *                    en LOG_ACCIONES (ej. iniciar_sesion, crear_trabajador, ...).
 *   $module        : módulo/sistema afectado (máx. 50).
 *   $description   : descripción legible del evento (máx. 255).
 *   $details       : array con contexto adicional; se guarda como JSON.
 *   $userId        : id del usuario (si es null se toma de $_SESSION).
 *   $status        : success|failed.
 *   $errorMessage  : mensaje de error cuando status = failed.
 *   $authProvider  : local|google|system|anonimo.
 *
 * Devuelve true si el INSERT se realizó, false si falló
 * (NUNCA lanza excepción que pueda romper la aplicación).
 */
function logAction(
    string $actionType,
    string $module,
    string $description,
    array  $details = [],
    ?int   $userId = null,
    string $status = 'success',
    ?string $errorMessage = null,
    string $authProvider = 'anonimo'
): bool {

    global $pdo;

    // ---- Validación de valores ENUM (para no romper el INSERT) ----
    $actionType = trim($actionType);
    if ($actionType === '') {
        error_log('[auditoria] action_type vacío; no se registró la operación.');
        return false;
    }
    $actionType = truncarParaAuditoria($actionType, 100);
    if (!in_array($authProvider, LOG_AUTH_PROVIDERS, true)) {
        error_log("[auditoria] auth_provider inválido: {$authProvider}");
        return false;
    }
    if (!in_array($status, LOG_STATUSES, true)) {
        error_log("[auditoria] status inválido: {$status}");
        return false;
    }

    // ---- Sin conexión PDO no se puede auditar ----
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log('[auditoria] No hay conexión PDO disponible; no se pudo registrar la operación.');
        return false;
    }

    // ---- Datos del usuario actual (parámetro > sesión > null) ----
    if ($userId === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    $username = '';
    $userEmail = '';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $username = trim((string)($_SESSION['username'] ?? ''));
        $userEmail = trim((string)($_SESSION['user_email'] ?? ''));
    }

    // Si no están en sesión y hay un userId, se intentan obtener de la tabla
    // real de usuarios (clasif_usuarios). Cualquier fallo aquí NO bloquea
    // la auditoría: se dejan como NULL.
    if ($userId !== null && ($username === '' || $userEmail === '')) {
        try {
            $stmtUser = $pdo->prepare('SELECT usuario, email FROM clasif_usuarios WHERE id = ? LIMIT 1');
            $stmtUser->execute([$userId]);
            $filaUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($filaUser) {
                if ($username === '') $username = (string)$filaUser['usuario'];
                if ($userEmail === '') $userEmail = (string)$filaUser['email'];
            }
        } catch (PDOException $e) {
            // La tabla de usuarios puede llamarse distinto en otro entorno:
            // se ignora y se continúa.
        }
    }

    // ---- Filtrado estricto de secretos en details ----
    $details = limpiarDetallesAuditoria($details);

    // ---- Contexto HTTP automático ----
    $ipAddress       = obtenerIpRealAuditoria();
    $userAgent       = truncarParaAuditoria((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 500);
    $requestMethod   = strtoupper(truncarParaAuditoria((string)($_SERVER['REQUEST_METHOD'] ?? ''), 10));
    $requestUrl      = truncarParaAuditoria((string)($_SERVER['REQUEST_URI'] ?? ''), 500);

    // ---- Normalización de campos libres y límites de columna ----
    $authProvider    = in_array($authProvider, LOG_AUTH_PROVIDERS, true) ? $authProvider : 'anonimo';
    $module          = truncarParaAuditoria($module, 50);
    $description     = truncarParaAuditoria($description, 255);
    $username        = truncarParaAuditoria($username, 100);
    $userEmail       = truncarParaAuditoria($userEmail, 150);
    $errorMessage    = (is_string($errorMessage) && $errorMessage !== '')
                       ? $errorMessage
                       : null;

    // ---- Fecha en BD: SIEMPRE 24h (Y-m-d H:i:s) ----
    $createdAt = date('Y-m-d H:i:s');

    // ---- details como JSON (sin escapar Unicode) ----
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
    if ($detailsJson === false) {
        $detailsJson = null; // No guardar nada antes que romper el registro
    }

    // ============================================================
    // HASH CHAIN (DETECCIÓN DE MANIPULACIÓN)
    // ------------------------------------------------------------
    // hash_chain = SHA-256 de (id anterior + datos del registro actual).
    //   - Si es el primer registro, la base es cadena vacía.
    //   - Para poder recalcular en historico.php se usa el MISMO
    //     formato canónico separado por '|' (NULL si el campo no
    //     tiene valor, para que el hash sea determinístico).
    // ============================================================
    $prevId = '';
    try {
        $stmtPrev = $pdo->query('SELECT id FROM audit_logs ORDER BY id DESC LIMIT 1');
        $prevCol  = $stmtPrev->fetchColumn();
        if ($prevCol !== false) {
            $prevId = (string)$prevCol;
        }
    } catch (PDOException $e) {
        // Tabla no existe o falla la consulta: se trata como primer registro.
        $prevId = '';
    }

    $registroCanonico = implode('|', [
        $userId === null   ? 'NULL' : (string)$userId,
        $username  !== ''   ? $username  : 'NULL',
        $userEmail !== ''   ? $userEmail : 'NULL',
        $authProvider,
        $actionType,
        $module,
        $description,
        canonicalDetallesAuditoria($detailsJson),
        $ipAddress,
        $userAgent  !== ''   ? $userAgent  : 'NULL',
        $requestMethod !== '' ? $requestMethod : 'NULL',
        $requestUrl !== ''   ? $requestUrl : 'NULL',
        $status,
        $errorMessage !== null ? $errorMessage : 'NULL',
        $createdAt,
    ]);

    $entradaHash = ($prevId === '' ? '' : $prevId . '|') . $registroCanonico;
    $hashChain   = hash('sha256', $entradaHash);

    // ============================================================
    // INSERT CON PREPARED STATEMENTS
    // ============================================================
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs
                (user_id, username, user_email, auth_provider, action_type,
                 module, description, details, ip_address, user_agent,
                 request_method, request_url, status, error_message,
                 hash_chain, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            $userId,
            $username !== ''   ? $username  : null,
            $userEmail !== ''  ? $userEmail : null,
            $authProvider,
            $actionType,
            $module,
            $description,
            $detailsJson,
            $ipAddress,
            $userAgent !== ''  ? $userAgent  : null,
            $requestMethod !== '' ? $requestMethod : null,
            $requestUrl !== '' ? $requestUrl : null,
            $status,
            $errorMessage,
            $hashChain,
            $createdAt,
        ]);
    } catch (PDOException $e) {
        // NUNCA romper la aplicación por un fallo de auditoría.
        error_log('[auditoria] No se pudo insertar el registro: ' . $e->getMessage());
        return false;
    }
}